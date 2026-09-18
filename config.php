<?php
$DB_HOST = '127.0.0.1';
$DB_NAME = 'event_attendance';
$DB_USER = 'root';
$DB_PASS = '';
date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function db() {
    static $conn;
    if ($conn) return $conn;
    $conn = @new mysqli($GLOBALS['DB_HOST'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASS'], $GLOBALS['DB_NAME']);
    if ($conn->connect_errno) { http_response_code(500); die('Database connection error. Import schema.sql first.'); }
    $conn->set_charset('utf8mb4');
    $conn->autocommit(true);
    $legacyTables = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('ssc_users','scc_users','ssc_settings','scc_settings')");
    $tables = [];
    while ($legacyTables && ($table = $legacyTables->fetch_assoc())) { $tables[$table['TABLE_NAME']] = true; }
    if (isset($tables['scc_users']) && !isset($tables['ssc_users'])) { $conn->query('RENAME TABLE scc_users TO ssc_users'); }
    if (isset($tables['scc_settings']) && !isset($tables['ssc_settings'])) { $conn->query('RENAME TABLE scc_settings TO ssc_settings'); }
    $legacyColumn = $conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_requests' AND COLUMN_NAME='scc_user_id'");
    $newColumn = $conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_requests' AND COLUMN_NAME='ssc_user_id'");
    if ($legacyColumn && $newColumn && (int)$legacyColumn->fetch_assoc()['c'] && !(int)$newColumn->fetch_assoc()['c']) {
        $conn->query('ALTER TABLE event_requests CHANGE COLUMN scc_user_id ssc_user_id INT NOT NULL');
    }
    $conn->query("CREATE TABLE IF NOT EXISTS ssc_settings (
        setting_key VARCHAR(80) PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $conn->query("ALTER TABLE ssc_users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash");
    $conn->query("CREATE TABLE IF NOT EXISTS event_requests (event_request_id INT AUTO_INCREMENT PRIMARY KEY, ssc_user_id INT NOT NULL, event_name VARCHAR(180) NOT NULL, description TEXT NULL, event_type VARCHAR(80) NULL, venue VARCHAR(180) NULL, event_date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, scan_in_start TIME NOT NULL, scan_in_end TIME NOT NULL, scan_out_start TIME NOT NULL, scan_out_end TIME NOT NULL, fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0, status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING', admin_note VARCHAR(255) NULL, reviewed_by INT NULL, reviewed_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_event_requests_status (status), FOREIGN KEY (ssc_user_id) REFERENCES ssc_users(id) ON DELETE CASCADE)");
    finalize_expired_events($conn);
    return $conn;
}
function finalize_expired_events($conn) {
    $expired = "(e.event_date < CURDATE() OR (e.event_date = CURDATE() AND e.end_time <= CURTIME()))";

    $attendance = $conn->query("INSERT IGNORE INTO event_attendance
        (student_id,event_id,attendance_status,recorded_date,remarks)
        SELECT es.student_id,e.id,'ABSENT',e.event_date,'Automatically marked absent after the event ended.'
        FROM event_students es
        JOIN events e ON e.id=es.event_id
        WHERE e.attendance_required=1 AND e.status IN ('UPCOMING','ACTIVE') AND $expired
          AND NOT EXISTS (
              SELECT 1 FROM event_attendance a
              WHERE a.event_id=es.event_id AND a.student_id=es.student_id
          )");

    $conn->query("INSERT IGNORE INTO fines(event_id,student_id,amount,reason,status)
        SELECT a.event_id,a.student_id,e.fine_amount,
               CONCAT('Absent from event: ',e.event_name),'UNPAID'
        FROM event_attendance a
        JOIN events e ON e.id=a.event_id
        WHERE a.attendance_status='ABSENT' AND e.fine_amount>0
          AND e.attendance_required=1 AND e.status IN ('UPCOMING','ACTIVE') AND $expired
          AND NOT EXISTS (
              SELECT 1 FROM fines f
              WHERE f.event_id=a.event_id AND f.student_id=a.student_id
          )");

    $conn->query("UPDATE events e SET status='COMPLETED'
        WHERE e.status IN ('UPCOMING','ACTIVE') AND e.attendance_required=1 AND $expired");
}
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function is_admin() { return !empty($_SESSION['admin_id']) || !empty($_SESSION['ssc_id']); }
function require_admin_account() {
    require_admin();
    if (empty($_SESSION['admin_id'])) {
        http_response_code(403);
        exit('Administrator access required.');
    }
}

function require_admin($allowPasswordChange = false) {
    if (!is_admin()) { header('Location: ../login.php'); exit; }
    if (!$allowPasswordChange && !empty($_SESSION['ssc_id']) && !empty($_SESSION['ssc_must_change_password'])) {
        header('Location: ../change_password.php'); exit;
    }
}
function get_ssc_setting($key, $default = '') {
    $conn = db();
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $conn->prepare('SELECT setting_value FROM ssc_settings WHERE setting_key=? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $cache[$key] = $row ? $row['setting_value'] : $default;
}
function active_event($conn) {
    $sql = "SELECT * FROM events WHERE status='ACTIVE' AND attendance_required=1 AND event_date=CURDATE() ORDER BY start_time LIMIT 1";
    $result = $conn->query($sql);
    return $result ? $result->fetch_assoc() : null;
}
function event_window_open($event, $direction) {
    if (!$event) return false;
    $now = date('Y-m-d H:i:s');
    $start = $direction === 'IN' ? $event['scan_in_start'] : $event['scan_out_start'];
    $end = $direction === 'IN' ? $event['scan_in_end'] : $event['scan_out_end'];
    return $now >= ($event['event_date'].' '.$start) && $now <= ($event['event_date'].' '.$end);
}
function event_label($event) { return $event ? $event['event_name'].' Â· '.date('M j, Y', strtotime($event['event_date'])) : 'No active event'; }
