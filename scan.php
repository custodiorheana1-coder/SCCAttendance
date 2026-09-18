<?php
require_once dirname(__DIR__).'/config.php';
header('Content-Type: application/json; charset=utf-8');
$conn=db(); $qr=trim($_POST['qr']??''); $eventId=(int)($_POST['event_id']??0);
register_shutdown_function(function () use ($conn) { @$conn->rollback(); });
if(!$qr||!$eventId){echo json_encode(['ok'=>false,'title'=>'SCAN DENIED','message'=>'A valid student code and event are required.']);exit;}
function scan_response($payload, $status = 200) {
	global $student;
	if (isset($payload['grade']) && isset($student['course'])) {
		$payload['course'] = $student['course'];
		$payload['grade'] = $student['year_level'] ?? '';
	}
	http_response_code($status);
	echo json_encode($payload);
	exit;
}
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

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$json = is_array($json) ? $json : [];
$code = trim((string)($json['code'] ?? $_POST['code'] ?? $_POST['qr'] ?? ''));
$scanType = strtolower(trim((string)($json['scan_type'] ?? $_POST['scan_type'] ?? 'qr_or_barcode')));
$eventId = (int)($json['event_id'] ?? $_POST['event_id'] ?? 0);

if ($code === '' || strlen($code) > 255 || $eventId <= 0) {
	scan_response(['ok' => false, 'success' => false, 'title' => 'SCAN DENIED', 'message' => 'A valid student code and event are required.'], 400);
}

$eventStatement = $conn->prepare("SELECT * FROM events WHERE id=? AND status='ACTIVE' AND attendance_required=1 AND event_date=CURDATE() LIMIT 1");
$eventStatement->bind_param('i', $eventId);
$eventStatement->execute();
$event = $eventStatement->get_result()->fetch_assoc();
if (!$event) {
	scan_response(['ok' => false, 'success' => false, 'title' => 'SCAN DENIED', 'message' => 'There is no active school event or activity.']);
}
$emergencyScanOut = get_ssc_setting('emergency_scan_out', '0') === '1';

$studentStatement = $conn->prepare("SELECT id,student_no,first_name,last_name,course,year_level,section,qr_code FROM students WHERE account_status='ACTIVE' AND (student_no=? OR qr_code=?) LIMIT 1");
$student = null;
foreach (scan_code_candidates($code) as $candidate) {
	$studentStatement->bind_param('ss', $candidate, $candidate);
	$studentStatement->execute();
	$student = $studentStatement->get_result()->fetch_assoc();
	if ($student) break;
}
if (!$student) {
	scan_response(['ok' => false, 'success' => false, 'title' => 'SCAN FAILED', 'message' => 'Student ID / QR / Barcode not registered.']);
}

$registration = $conn->prepare('SELECT 1 FROM event_students WHERE event_id=? AND student_id=? LIMIT 1');
$registration->bind_param('ii', $eventId, $student['id']);
$registration->execute();
if (!$registration->get_result()->fetch_row()) {
	$register = $conn->prepare('INSERT IGNORE INTO event_students(event_id,student_id) VALUES(?,?)');
	$register->bind_param('ii', $eventId, $student['id']);
	if (!$register->execute()) {
		scan_response(['ok' => false, 'success' => false, 'title' => 'SCAN DENIED', 'message' => 'Student could not be connected to this event.']);
	}
}

$conn->begin_transaction();
$attendance = $conn->prepare('SELECT * FROM event_attendance WHERE event_id=? AND student_id=? FOR UPDATE');
$attendance->bind_param('ii', $eventId, $student['id']);
$attendance->execute();
$record = $attendance->get_result()->fetch_assoc();
$now = date('Y-m-d H:i:s');
$name = $student['first_name'].' '.$student['last_name'];
$scanner = $scanType === 'camera' ? 'CAMERA' : ($scanType === 'usb' ? 'USB SCANNER' : 'SCANNER');

if (!$record) {
	if (!event_window_open($event, 'IN')) {
		$conn->rollback();
			scan_response(['ok' => false, 'success' => false, 'title' => 'SCAN DENIED', 'message' => 'Scan In is currently closed.']);
	}
	$attendanceStatus = date('H:i:s') > $event['start_time'] ? 'LATE' : 'INCOMPLETE';
	$insert = $conn->prepare('INSERT INTO event_attendance(student_id,event_id,scan_in,attendance_status,scanner,recorded_date) VALUES(?,?,?,?,?,CURDATE())');
	$insert->bind_param('iisss', $student['id'], $eventId, $now, $attendanceStatus, $scanner);
	if (!$insert->execute()) {
		$conn->rollback();
		scan_response(['ok' => false, 'success' => false, 'title' => 'SCAN FAILED', 'message' => 'Attendance could not be recorded.']);
	}
	$conn->commit();
		scan_response(['ok' => true, 'success' => true, 'direction' => 'IN', 'title' => 'SCAN SUCCESSFUL', 'message' => 'Attendance recorded.', 'student' => $name, 'student_record_id' => (int)$student['id'], 'student_id' => $student['student_no'], 'grade' => $student['course'], 'section' => $student['section'], 'event' => $event['event_name'], 'scan_type' => $scanType, 'scan_in' => date('g:i:s A', strtotime($now)), 'scan_out' => null, 'status' => $attendanceStatus]);
}

if (!$record['scan_in']) {
	$conn->rollback();
	scan_response(['ok' => false, 'success' => false, 'title' => 'SCAN OUT DENIED', 'message' => 'You must scan in before scanning out.']);
}
if ($record['scan_out']) {
	$conn->rollback();
	scan_response(['ok' => false, 'success' => false, 'title' => 'ALREADY SCANNED OUT', 'message' => 'You have already completed your attendance for this event.', 'student' => $name, 'student_record_id' => (int)$student['id'], 'student_id' => $student['student_no'], 'grade' => $student['course'], 'section' => $student['section'], 'scan_in' => $record['scan_in'] ? date('g:i A', strtotime($record['scan_in'])) : null, 'scan_out' => $record['scan_out'] ? date('g:i A', strtotime($record['scan_out'])) : null, 'status' => 'SCAN OUT']);
}
if (!$emergencyScanOut && !event_window_open($event, 'OUT')) {
	$conn->rollback();
	scan_response(['ok' => false, 'success' => false, 'title' => 'ALREADY CHECKED IN', 'message' => 'Scan out is available from '.date('g:i A', strtotime($event['scan_out_start'])).' to '.date('g:i A', strtotime($event['scan_out_end'])).'.', 'student' => $name, 'student_record_id' => (int)$student['id'], 'student_id' => $student['student_no'], 'grade' => $student['course'], 'section' => $student['section'], 'scan_in' => date('g:i A', strtotime($record['scan_in'])), 'status' => 'PRESENT']);
}

$update = $conn->prepare("UPDATE event_attendance SET scan_out=?,attendance_status='PRESENT',scanner=? WHERE attendance_id=?");
$update->bind_param('ssi', $now, $scanner, $record['attendance_id']);
if (!$update->execute()) {
	$conn->rollback();
	scan_response(['ok' => false, 'success' => false, 'title' => 'SCAN FAILED', 'message' => 'Attendance could not be updated.']);
}
$conn->commit();
	scan_response(['ok' => true, 'success' => true, 'direction' => 'OUT', 'title' => 'SCAN OUT SUCCESSFUL', 'message' => 'Scan out recorded.', 'student' => $name, 'student_record_id' => (int)$student['id'], 'student_id' => $student['student_no'], 'grade' => $student['course'], 'section' => $student['section'], 'event' => $event['event_name'], 'scan_type' => $scanType, 'scan_in' => $record['scan_in'] ? date('g:i:s A', strtotime($record['scan_in'])) : null, 'scan_out' => date('g:i:s A', strtotime($now)), 'status' => 'PRESENT']);
