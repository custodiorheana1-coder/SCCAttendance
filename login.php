<?php
require_once __DIR__.'/config.php';
$organizationName = get_ssc_setting('organization_name', 'Student Entry and Exit Using a Barcode and SMS Integration System');
if (is_admin()) { header('Location: admin/index.php'); exit; }
$conn = db();
$error = '';
$hasAdmin = (int)$conn->query('SELECT COUNT(*) c FROM admin_users')->fetch_assoc()['c'] > 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$hasAdmin && ($_POST['action'] ?? '') === 'create_admin') {
    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['admin_username'] ?? '');
    $newPassword = $_POST['admin_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    if (!$name || !$username || $newPassword === '' || $newPassword !== $confirmPassword) {
      $error = 'Enter a name, username, matching passwords, and a password.';
    } else {
      $hash = password_hash($newPassword, PASSWORD_DEFAULT);
      $create = $conn->prepare('INSERT INTO admin_users(full_name,email,password_hash) VALUES(?,?,?)');
      $create->bind_param('sss', $name, $username, $hash);
      if ($create->execute()) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$create->insert_id;
        $_SESSION['admin_name'] = $name;
        header('Location: admin/index.php'); exit;
      }
      $error = 'Unable to create the administrator account. The username may already exist.';
    }
  }
  if (($hasAdmin || ($_POST['action'] ?? '') !== 'create_admin') && ($_POST['action'] ?? '') !== 'create_admin') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $stmt = $conn->prepare('SELECT id,full_name,email,password_hash,account_status FROM admin_users WHERE email=? LIMIT 1');
  $stmt->bind_param('s', $username);
  $stmt->execute();
  $admin = $stmt->get_result()->fetch_assoc();
  if ($admin && password_verify($password, $admin['password_hash'])) {
    if ($admin['account_status'] !== 'ACTIVE') {
      $activate = $conn->prepare("UPDATE admin_users SET account_status='ACTIVE' WHERE id=?");
      $activate->bind_param('i', $admin['id']);
      $activate->execute();
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $up = $conn->prepare('UPDATE admin_users SET last_login=NOW() WHERE id=?');
    $up->bind_param('i', $admin['id']); $up->execute();
    $log = $conn->prepare("INSERT INTO activity_logs(actor_type,actor_id,action,entity_type,entity_id,details) VALUES('ADMIN',?,'Login','admin_user',?,?)");
    $log->bind_param('iis', $admin['id'], $admin['id'], $admin['full_name']); $log->execute();
    header('Location: admin/index.php'); exit;
  }

  $stmt = $conn->prepare('SELECT id,full_name,username,email,password_hash,must_change_password,account_status FROM ssc_users WHERE username=? OR email=? LIMIT 1');
  $stmt->bind_param('ss', $username, $username);
  $stmt->execute();
  $sscUser = $stmt->get_result()->fetch_assoc();
  if ($sscUser && password_verify($password, $sscUser['password_hash'])) {
    if ($sscUser['account_status'] !== 'ACTIVE') {
      $activate = $conn->prepare("UPDATE ssc_users SET account_status='ACTIVE' WHERE id=?");
      $activate->bind_param('i', $sscUser['id']);
      $activate->execute();
    }
    session_regenerate_id(true);
    $_SESSION['ssc_id'] = (int)$sscUser['id'];
    $_SESSION['ssc_name'] = $sscUser['full_name'];
    $_SESSION['ssc_username'] = $sscUser['username'];
    $_SESSION['ssc_must_change_password'] = (int)$sscUser['must_change_password'];
    $up = $conn->prepare('UPDATE ssc_users SET last_login=NOW() WHERE id=?');
    $up->bind_param('i', $sscUser['id']); $up->execute();
    $log = $conn->prepare("INSERT INTO activity_logs(actor_type,actor_id,action,entity_type,entity_id,details) VALUES('SSC',?,'Login','ssc_user',?,?)");
    $log->bind_param('iis', $sscUser['id'], $sscUser['id'], $sscUser['full_name']); $log->execute();
    header('Location: ' . ((int)$sscUser['must_change_password'] === 1 ? 'change_password.php' : 'admin/index.php')); exit;
  }

  $error = 'Invalid username or password.';
  }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>SSC Login</title>
<style>
  :root{font-family:Arial,Helvetica,sans-serif;color:#1e293b;background:#f5f3ff}
  *{box-sizing:border-box}
  body{min-height:100vh;margin:0;background:linear-gradient(135deg,#ede9fe 0%,#fff 52%,#e0e7ff 100%);display:flex;align-items:center}
  .login-wrap{width:100%;max-width:448px;margin:0 auto;padding:24px}
  .brand{margin-bottom:24px}
  .brand-row{display:flex;align-items:center;gap:0}.brand-row>div:last-child{min-width:0;flex:1;text-align:center}
  .brand-mark{width:46px;height:46px;flex:0 0 46px;margin-right:-6px;border-radius:14px;background:#7c3aed;color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 5px 14px rgba(76,29,149,.25);font-weight:700;font-size:18px;overflow:hidden}
  .brand-mark img{width:100%;height:100%;object-fit:cover}
  .brand-name{font-size:14px;line-height:1.3;letter-spacing:1.5px;text-transform:uppercase;color:#64748b}
  .brand-title{margin-top:4px;text-align:center;font-size:24px;line-height:1.25;font-weight:700;color:#1e293b}
  .login-card{padding:20px;background:#fff;border-radius:16px;box-shadow:0 12px 30px rgba(30,41,59,.12)}
  .field{margin-bottom:12px}.field label{display:block;font-size:14px;color:#475569}.field input{width:100%;margin-top:4px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:4px;font:inherit;outline:none}.field input:focus{border-color:#8b5cf6;box-shadow:0 0 0 3px rgba(139,92,246,.2)}
  .button-row{display:flex;justify-content:flex-end;margin-top:16px}.button{border:0;border-radius:8px;background:#7c3aed;color:#fff;padding:10px 16px;font-weight:600;font:inherit;cursor:pointer}.button:hover{background:#6d28d9}
  .error{margin-bottom:14px;padding:10px 12px;border-radius:8px;background:#fee2e2;color:#991b1b;font-size:14px}
  .modal-backdrop{position:fixed;inset:0;z-index:10;background:rgba(15,23,42,.5);display:flex;align-items:center;justify-content:center;padding:20px}
  .modal{width:100%;max-width:440px;padding:24px;background:#fff;border-radius:16px;box-shadow:0 20px 50px rgba(15,23,42,.25)}
  .modal h2{margin:0;color:#1e1b4b;font-size:22px}.modal p{margin:8px 0 18px;color:#64748b;font-size:14px;line-height:1.5}.modal .button{width:100%;margin-top:4px}
  .setup,.back{display:block;text-align:center;font-size:14px;text-decoration:none}.setup{margin-top:18px;color:#6d28d9}.back{margin-top:14px;color:#64748b}.setup:hover,.back:hover{text-decoration:underline}@media(max-width:520px){.login-wrap{padding:16px}.brand-row{align-items:flex-start}.brand-mark{width:40px;height:40px;flex-basis:40px;margin-right:-4px}.brand-name{font-size:11px;letter-spacing:1px;overflow-wrap:anywhere}.brand-title{font-size:21px}.login-card{padding:16px}.button-row{justify-content:stretch}.button-row .button{width:100%}}
</style>
</head>
<body>
  <div class="login-wrap">
    <div class="brand"><div class="brand-row"><div class="brand-mark" aria-hidden="true">SSC</div><div><div class="brand-name"><?php echo h($organizationName); ?></div><div class="brand-title">Admin Login</div></div></div></div>
    <form method="post" class="login-card">
      <?php if($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
      <div class="field"><label for="username">Username</label><input id="username" name="username" required autocomplete="username"></div>
      <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
      <div class="button-row"><button class="button" type="submit">Login</button></div>
    </form>
    <?php if(!$hasAdmin): ?><div class="setup">Administrator setup is required before access.</div><?php endif; ?>
    <a class="back" href="index.php">Back to event attendance</a>
  </div>
  <?php if(!$hasAdmin): ?><div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="setup-title"><form method="post" class="modal"><input type="hidden" name="action" value="create_admin"><h2 id="setup-title">Create Admin Account</h2><p>No administrator account exists yet. Create the first account to access all management functions.</p><?php if($error): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?><div class="field"><label for="full_name">Full Name</label><input id="full_name" name="full_name" required autofocus></div><div class="field"><label for="admin_username">Username</label><input id="admin_username" name="admin_username" required autocomplete="username"></div><div class="field"><label for="admin_password">Password</label><input id="admin_password" name="admin_password" type="password" required autocomplete="new-password"></div><div class="field"><label for="confirm_password">Confirm Password</label><input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password"></div><button class="button" type="submit">Create Admin Account</button></form></div><?php endif; ?>
</body>
</html>
