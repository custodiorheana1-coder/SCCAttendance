<?php
require_once dirname(__DIR__).'/config.php';
require_admin_account();

$conn = db();
$message = '';
$error = '';
$createdPassword = '';
$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
$temporaryPassword = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $status = 'INACTIVE';
    $permissions = json_encode($_POST['permissions'] ?? []);

    if (!$name || !$username || !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username) ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Enter a full name, a username using letters, numbers, dot, underscore, or hyphen, a valid email, and a password of at least 8 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $statement = $conn->prepare(
            'INSERT INTO ssc_users(full_name,username,email,password_hash,permissions,account_status) VALUES(?,?,?,?,?,?)'
        );
        $statement->bind_param('ssssss', $name, $username, $email, $hash, $permissions, $status);

        if ($statement->execute()) {
            $createdPassword = $password;
$message = 'SSC account added. Save this temporary password and give it to the user:';
            $log = $conn->prepare(
                "INSERT INTO activity_logs(actor_type,actor_id,action,entity_type,entity_id,details)
                 VALUES('ADMIN',?,'SSC account creation','ssc_user',?,?)"
            );
            $admin = (int)($_SESSION['admin_id'] ?? 0);
            $id = $statement->insert_id;
            $log->bind_param('iis', $admin, $id, $name);
            $log->execute();
        } else {
            $error = $conn->errno === 1062
                ? 'Unable to add account. Username or email may already exist.'
                : 'Unable to add the SSC account.';
        }
    }
}

$rows = $conn->query(
    'SELECT id,full_name,username,email,permissions,account_status,last_login,created_at
     FROM ssc_users ORDER BY full_name'
);
$pageTitle = 'SSC Account Management';
ob_start();
?>
<div class="space-y-6">
  <?php if ($message): ?><div class="rounded-lg bg-emerald-100 p-3 text-emerald-800"><?php echo h($message); ?><?php if ($createdPassword): ?><div class="mt-2 rounded bg-white/70 px-3 py-2 font-mono font-bold tracking-wider"><?php echo h($createdPassword); ?></div><?php endif; ?></div><?php endif; ?>
  <?php if ($error): ?><div class="rounded-lg bg-rose-100 p-3 text-rose-800"><?php echo h($error); ?></div><?php endif; ?>

  <div class="rounded-2xl bg-white p-5 shadow">
    <div class="mb-4 text-lg font-semibold">Add SSC user</div>
    <form method="post" class="grid gap-3 md:grid-cols-4">
      <input id="fullName" name="full_name" required placeholder="Full name" class="rounded border px-3 py-2">
      <input id="username" name="username" required minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]{3,80}" autocomplete="username" placeholder="Username" class="rounded border px-3 py-2">
      <input id="email" name="email" required type="email" placeholder="Email" class="rounded border px-3 py-2">
      <div class="relative"><label id="temporaryPasswordLabel" for="temporaryPassword" class="pointer-events-none absolute left-3 top-0 z-10 hidden -translate-y-1/2 bg-slate-50 px-1 text-xs font-semibold text-slate-600">Password</label><div class="flex gap-2"><input id="temporaryPassword" name="password" required type="text" minlength="8" pattern="[A-Za-z0-9]+" autocomplete="new-password" value="<?php echo h($temporaryPassword); ?>" placeholder="Password" readonly class="min-w-0 flex-1 rounded border bg-slate-50 px-3 py-2 font-mono"><button type="button" id="regeneratePassword" disabled class="rounded border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">New</button></div></div>
      <input type="hidden" name="account_status" value="INACTIVE">
      <div class="flex flex-wrap gap-4 text-sm md:col-span-3">
        <label><input type="checkbox" name="permissions[]" value="attendance" checked> Attendance</label>
        <label><input type="checkbox" name="permissions[]" value="fines" checked> Fines</label>
        <label><input type="checkbox" name="permissions[]" value="clearance" checked> Clearance</label>
        <label><input type="checkbox" name="permissions[]" value="reports" checked> Reports</label>
      </div>
      <button class="w-fit justify-self-start rounded bg-indigo-700 px-4 py-2 font-semibold text-white hover:bg-indigo-600 md:col-span-1">Create SSC account</button>
    </form>
  </div>

  <script>(function () { var input = document.getElementById("temporaryPassword"); var label = document.getElementById("temporaryPasswordLabel"); var button = document.getElementById("regeneratePassword"); var fields = [document.getElementById("fullName"), document.getElementById("username"), document.getElementById("email")]; if (!input || !button || fields.some(function (field) { return !field; })) return; var chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789"; function generate() { var value = ""; for (var i = 0; i < 12; i++) value += chars.charAt(Math.floor(Math.random() * chars.length)); input.value = value; } function sync() { var complete = fields.every(function (field) { return field.value.trim() !== ""; }); if (complete && input.value === "") generate(); if (!complete) input.value = ""; label.classList.toggle("hidden", input.value === ""); input.placeholder = input.value === "" ? "Password" : ""; button.disabled = !complete; } fields.forEach(function (field) { field.addEventListener("input", sync); }); button.addEventListener("click", generate); sync(); })();</script>
  <div class="rounded-2xl bg-white p-5 shadow">
    <div class="mb-4 text-lg font-semibold">SSC users and activity</div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="border-b text-left">
          <th class="px-2 py-2">Name</th>
          <th class="px-2 py-2">Username</th>
          <th class="px-2 py-2">Email</th>
          <th class="px-2 py-2">Permissions</th>
          <th class="px-2 py-2">Status</th>
          <th class="px-2 py-2">Last login</th>
        </tr></thead>
        <tbody>
          <?php while ($user = $rows->fetch_assoc()): ?>
            <tr class="border-b">
              <td class="px-2 py-3 font-medium"><?php echo h($user['full_name']); ?></td>
              <td class="px-2 py-3"><?php echo h($user['username']); ?></td>
              <td class="px-2 py-3"><?php echo h($user['email']); ?></td>
              <td class="px-2 py-3"><?php echo h(implode(', ', (array)json_decode($user['permissions'] ?? '[]', true))); ?></td>
              <td class="px-2 py-3"><?php echo h($user['account_status']); ?></td>
              <td class="px-2 py-3"><?php echo h($user['last_login'] ?? 'Never'); ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__.'/_layout.php';
