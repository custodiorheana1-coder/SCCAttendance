<?php
require_once dirname(__DIR__).'/config.php';
require_admin();

$conn = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'system_name' => trim($_POST['system_name'] ?? ''),
        'organization_name' => trim($_POST['organization_name'] ?? ''),
        'modal_seconds' => (string)max(1, min(10, (int)($_POST['modal_seconds'] ?? 2))),
        'audio_volume' => (string)max(0.05, min(0.5, (float)($_POST['audio_volume'] ?? 0.28))),
        'emergency_scan_out' => isset($_POST['emergency_scan_out']) ? '1' : '0',
    ];
    if ($values['system_name'] === '' || $values['organization_name'] === '') {
        $error = 'System name and organization name are required.';
    } else {
        $statement = $conn->prepare(
            'INSERT INTO ssc_settings(setting_key,setting_value) VALUES(?,?)
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
        );
        $saved = true;
        foreach ($values as $key => $value) {
            $statement->bind_param('ss', $key, $value);
            $saved = $statement->execute() && $saved;
        }
        if ($saved) {
            $message = 'Settings saved and connected to the SSC scanner.';
        } else {
            $error = 'Unable to save settings.';
        }
    }
}

$pageTitle = 'Settings';
ob_start();
?>
<div class="space-y-6">
  <?php if ($message): ?><div class="rounded-lg bg-emerald-100 p-3 text-emerald-800"><?php echo h($message); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="rounded-lg bg-rose-100 p-3 text-rose-800"><?php echo h($error); ?></div><?php endif; ?>
  <div class="rounded-2xl bg-white p-5 shadow">
    <form method="post" class="grid gap-4 md:grid-cols-2">
      <label class="text-sm font-medium text-slate-700">System name<input name="system_name" value="<?php echo h(get_ssc_setting('system_name', 'SSC Event Attendance')); ?>" required class="mt-1 w-full rounded-lg border px-3 py-2"></label>
      <label class="text-sm font-medium text-slate-700">Organization name<input name="organization_name" value="<?php echo h(get_ssc_setting('organization_name', 'Student Entry and Exit Using a Barcode and SMS Integration System')); ?>" required class="mt-1 w-full rounded-lg border px-3 py-2"></label>
      <label class="text-sm font-medium text-slate-700">Scan result modal seconds<input name="modal_seconds" type="number" min="1" max="10" value="<?php echo h(get_ssc_setting('modal_seconds', '2')); ?>" class="mt-1 w-full rounded-lg border px-3 py-2"><span class="mt-1 block text-xs text-slate-500">How long success or error feedback remains visible.</span></label>
    <label class="text-sm font-medium text-slate-700">Scanner audio volume<input name="audio_volume" type="number" min="0.05" max="0.5" step="0.01" value="<?php echo h(get_ssc_setting('audio_volume', '0.28')); ?>" class="mt-1 w-full rounded-lg border px-3 py-2"><span class="mt-1 block text-xs text-slate-500">Browser volume envelope from 0.05 to 0.5.</span></label>
    <label class="flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-slate-700 md:col-span-2"><input name="emergency_scan_out" type="checkbox" value="1" <?php echo get_ssc_setting('emergency_scan_out', '0') === '1' ? 'checked' : ''; ?> class="mt-1 h-4 w-4 accent-rose-600"><span><span class="block font-semibold text-rose-800">Enable emergency scan-out</span><span class="mt-1 block text-xs font-normal text-rose-700">Allow students who already scanned in to scan out before the scheduled exit window, such as for an injury or other emergency. Turn this off after use.</span></span></label>
      <div class="flex justify-end md:col-span-2"><button class="rounded-xl bg-violet-600 px-5 py-2.5 font-semibold text-white shadow-lg shadow-violet-900/20 hover:bg-violet-500">Save settings</button></div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__.'/_layout.php';
