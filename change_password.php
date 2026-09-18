<?php
require_once __DIR__.'/config.php';
require_admin(true);
if (empty($_SESSION['ssc_id'])) { header('Location: admin/index.php'); exit; }

$conn = db();
$userId = (int)($_SESSION['ssc_id'] ?? 0);
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $stmt = $conn->prepare('SELECT password_hash FROM ssc_users WHERE id=? AND account_status=\'ACTIVE\' LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user || !password_verify($current, $user['password_hash'])) {
        $error = 'Your current password is incorrect.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Your new password must be at least 8 characters.';
    } elseif ($newPassword !== $confirm) {
        $error = 'The new password and confirmation do not match.';
    } elseif (password_verify($newPassword, $user['password_hash'])) {
        $error = 'Your new password must be different from the temporary password.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $conn->prepare('UPDATE ssc_users SET password_hash=?, must_change_password=0 WHERE id=?');
        $update->bind_param('si', $hash, $userId);
        if (!$update->execute()) {
            $error = 'Unable to update your password. Please try again.';
        } else {
            $_SESSION['ssc_must_change_password'] = 0;
            $message = 'Password changed successfully.';
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Change Password - SSC</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-100 text-slate-800"><main class="mx-auto flex min-h-screen w-full max-w-lg items-center p-4 sm:p-6"><section class="w-full rounded-3xl bg-white p-6 shadow-xl sm:p-8"><div class="mb-6 text-center"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 font-bold text-white shadow-lg">SSC</div><h1 class="mt-4 text-2xl font-bold">Change your password</h1><p class="mt-2 text-sm text-slate-500">For your security, change your temporary password before opening your account.</p></div><?php if ($message): ?><div class="mb-4 rounded-lg bg-emerald-100 p-3 text-sm text-emerald-800"><?php echo h($message); ?></div><a href="admin/index.php" class="block rounded-xl bg-indigo-600 px-4 py-3 text-center font-semibold text-white hover:bg-indigo-500">Continue to dashboard</a><?php else: ?><?php if ($error): ?><div class="mb-4 rounded-lg bg-rose-100 p-3 text-sm text-rose-800"><?php echo h($error); ?></div><?php endif; ?><form method="post" class="space-y-4"><label class="block text-sm font-medium">Current temporary password<input name="current_password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-3"></label><label class="block text-sm font-medium">New password<input name="new_password" type="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-3"><span class="mt-1 block text-xs text-slate-500">Use at least 8 characters.</span></label><label class="block text-sm font-medium">Confirm new password<input name="confirm_password" type="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-3"></label><button class="w-full rounded-xl bg-indigo-600 px-4 py-3 font-semibold text-white hover:bg-indigo-500">Change password and continue</button></form><a href="logout.php" class="mt-4 block text-center text-sm text-slate-500 hover:text-slate-700">Cancel and log out</a><?php endif; ?></section></main></body></html>
