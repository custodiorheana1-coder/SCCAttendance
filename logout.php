<?php
require_once __DIR__.'/config.php';
$conn = db();
if (!empty($_SESSION['admin_id'])) {
  $log = $conn->prepare("INSERT INTO activity_logs(actor_type,actor_id,action,entity_type,entity_id,details) VALUES('ADMIN',?,'Logout','admin_user',?,?)");
  $id = (int)$_SESSION['admin_id']; $name = $_SESSION['admin_name'] ?? ''; $log->bind_param('iis', $id, $id, $name); $log->execute();
}
if (!empty($_SESSION['ssc_id'])) {
  $log = $conn->prepare("INSERT INTO activity_logs(actor_type,actor_id,action,entity_type,entity_id,details) VALUES('SSC',?,'Logout','ssc_user',?,?)");
  $id = (int)$_SESSION['ssc_id']; $name = $_SESSION['ssc_name'] ?? ''; $log->bind_param('iis', $id, $id, $name); $log->execute();
}
$_SESSION = []; session_destroy(); header('Location: login.php'); exit;
