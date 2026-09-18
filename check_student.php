<?php
require_once dirname(__DIR__).'/config.php';
header('Content-Type: application/json; charset=utf-8');

function scan_code_candidates($value) {
    $value = trim((string)$value);
    if ($value === '') return [];
    $candidates = [$value, trim(str_replace(["\r", "\n", "\t"], '', $value))];
    $decoded = rawurldecode($value);
    if ($decoded !== $value) $candidates[] = trim($decoded);
    $json = json_decode($value, true);
    if (is_array($json)) {
        foreach (['student_id', 'student_no', 'qr', 'qr_code', 'barcode', 'code', 'id'] as $key) {
            if (!empty($json[$key]) && is_scalar($json[$key])) $candidates[] = trim((string)$json[$key]);
        }
    }
    $url = filter_var($value, FILTER_VALIDATE_URL) ? parse_url($value) : false;
    if (is_array($url)) {
        $path = trim((string)($url['path'] ?? ''), '/');
        if ($path !== '') $candidates[] = basename($path);
        parse_str((string)($url['query'] ?? ''), $query);
        foreach (['student_id', 'student_no', 'qr', 'qr_code', 'barcode', 'code', 'id'] as $key) {
            if (!empty($query[$key]) && is_scalar($query[$key])) $candidates[] = trim((string)$query[$key]);
        }
    }
    return array_values(array_unique(array_filter($candidates, function ($candidate) { return $candidate !== ''; })));
}

$code = trim($_POST['qr'] ?? '');
if ($code === '') {
    echo json_encode(['ok' => false, 'error' => 'Student ID is required.']);
    exit;
}

$conn = db();
$statement = $conn->prepare('SELECT id,student_no,first_name,last_name,middle_name,course,year_level,section,email,contact_number,qr_code,account_status FROM students WHERE student_no=? OR qr_code=? LIMIT 1');
$student = null;
foreach (scan_code_candidates($code) as $candidate) {
    $statement->bind_param('ss', $candidate, $candidate);
    $statement->execute();
    $student = $statement->get_result()->fetch_assoc();
    if ($student) break;
}

if (!$student) {
    echo json_encode(['ok' => true, 'exists' => false, 'message' => 'Student ID is not registered.']);
    exit;
}

echo json_encode(['ok' => true, 'exists' => true, 'student' => $student, 'message' => 'Student found.']);