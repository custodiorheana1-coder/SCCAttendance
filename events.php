<?php
require_once dirname(__DIR__).'/config.php';
require_admin_account();

$conn = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $eventId = (int)($_POST['event_id'] ?? 0);

    if ($action === 'create') {
        $name = trim($_POST['event_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type = trim($_POST['event_type'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $date = $_POST['event_date'] ?? '';
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';
        $scanInStart = $_POST['scan_in_start'] ?? '';
        $scanInEnd = $_POST['scan_in_end'] ?? '';
        $scanOutStart = $_POST['scan_out_start'] ?? '';
        $scanOutEnd = $_POST['scan_out_end'] ?? '';
        $fine = (float)($_POST['fine_amount'] ?? 0);

        if (!$name || !$date || !$start || !$end || !$scanInStart || !$scanInEnd || !$scanOutStart || !$scanOutEnd ||
            $fine < 0 || $start > $end || $scanInStart > $scanInEnd || $scanOutStart > $scanOutEnd) {
            $error = 'Complete the event details and use valid time ranges.';
        } else {
            $statement = $conn->prepare(
                'INSERT INTO events(event_name,description,event_type,venue,event_date,start_time,end_time,
                 scan_in_start,scan_in_end,scan_out_start,scan_out_end,fine_amount,status)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,"DRAFT")'
            );
            $statement->bind_param(
                'sssssssssssd',
                $name, $description, $type, $venue, $date, $start, $end,
                $scanInStart, $scanInEnd, $scanOutStart, $scanOutEnd, $fine
            );
            if ($statement->execute()) {
                $message = 'Event created. Register students before activating it.';
            } else {
                $error = 'Unable to create the event.';
            }
        }
    } elseif (in_array($action, ['activate', 'close', 'register_all'], true) && $eventId > 0) {
        $check = $conn->prepare('SELECT id,event_name,status FROM events WHERE id=?');
        $check->bind_param('i', $eventId);
        $check->execute();
        $event = $check->get_result()->fetch_assoc();

        if (!$event) {
            $error = 'Event not found.';
        } elseif ($action === 'activate') {
            $conn->begin_transaction();
            $conn->query("UPDATE events SET status='UPCOMING' WHERE status='ACTIVE'");
            $activate = $conn->prepare("UPDATE events SET status='ACTIVE' WHERE id=? AND event_date=CURDATE()");
            $activate->bind_param('i', $eventId);
            if ($activate->execute() && $activate->affected_rows === 1) {
              $students = $conn->query("SELECT id FROM students WHERE account_status='ACTIVE'");
              $register = $conn->prepare('INSERT IGNORE INTO event_students(event_id,student_id) VALUES(?,?)');
              while ($student = $students->fetch_assoc()) {
                $studentId = (int)$student['id'];
                $register->bind_param('ii', $eventId, $studentId);
                $register->execute();
              }
                $conn->commit();
              $message = 'Event activated and active students registered automatically.';
            } else {
                $conn->rollback();
                $error = 'Only an event scheduled for today can be activated.';
            }
        } elseif ($action === 'close') {
            $close = $conn->prepare("UPDATE events SET status='CLOSED' WHERE id=? AND status IN ('ACTIVE','UPCOMING','DRAFT')");
            $close->bind_param('i', $eventId);
            $message = $close->execute() && $close->affected_rows === 1 ? 'Event closed.' : 'Event could not be closed.';
            if ($message !== 'Event closed.') {
                $error = $message;
                $message = '';
            }
        } else {
            $conn->begin_transaction();
            $clear = $conn->prepare('DELETE FROM event_students WHERE event_id=?');
            $clear->bind_param('i', $eventId);
            $clear->execute();
            $students = $conn->query("SELECT id FROM students WHERE account_status='ACTIVE'");
            $add = $conn->prepare('INSERT INTO event_students(event_id,student_id) VALUES(?,?)');
            $registered = 0;
            while ($student = $students->fetch_assoc()) {
                $studentId = (int)$student['id'];
                $add->bind_param('ii', $eventId, $studentId);
                $add->execute();
                $registered++;
            }
            $conn->commit();
            $message = $registered.' active student(s) registered for the event.';
        }
    }
}

$events = $conn->query(
    'SELECT e.*, COUNT(es.student_id) AS registered_count
     FROM events e LEFT JOIN event_students es ON es.event_id=e.id
     GROUP BY e.id ORDER BY e.event_date DESC, e.start_time DESC'
);
$pageTitle = 'Events & Participants';
ob_start();
?>
<div class="space-y-6">
  <?php if ($message): ?><div class="rounded-lg bg-emerald-100 p-3 text-emerald-800"><?php echo h($message); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="rounded-lg bg-rose-100 p-3 text-rose-800"><?php echo h($error); ?></div><?php endif; ?>

  <div class="rounded-2xl bg-white p-5 shadow">
    <div class="mb-4 text-lg font-semibold">Create event</div>
    <form method="post" class="grid gap-3 md:grid-cols-4">
      <input type="hidden" name="action" value="create">
      <input name="event_name" required placeholder="Event name" class="rounded border px-3 py-2 md:col-span-2">
      <input name="event_type" placeholder="Event type" class="rounded border px-3 py-2">
      <input name="venue" placeholder="Venue" class="rounded border px-3 py-2">
      <textarea name="description" placeholder="Description" class="rounded border px-3 py-2 md:col-span-4"></textarea>
      <label class="text-sm">Event date<input name="event_date" type="date" required value="<?php echo h(date('Y-m-d')); ?>" class="mt-1 w-full rounded border px-3 py-2"></label>
      <label class="text-sm">Start time<input name="start_time" type="time" required class="mt-1 w-full rounded border px-3 py-2"></label>
      <label class="text-sm">End time<input name="end_time" type="time" required class="mt-1 w-full rounded border px-3 py-2"></label>
      <label class="text-sm">Fine amount<input name="fine_amount" type="number" min="0" step="0.01" value="0" class="mt-1 w-full rounded border px-3 py-2"></label>
      <label class="text-sm">Scan-in opens<input name="scan_in_start" type="time" required class="mt-1 w-full rounded border px-3 py-2"></label>
      <label class="text-sm">Scan-in closes<input name="scan_in_end" type="time" required class="mt-1 w-full rounded border px-3 py-2"></label>
      <label class="text-sm">Scan-out opens<input name="scan_out_start" type="time" required class="mt-1 w-full rounded border px-3 py-2"></label>
      <label class="text-sm">Scan-out closes<input name="scan_out_end" type="time" required class="mt-1 w-full rounded border px-3 py-2"></label>
      <button class="rounded bg-indigo-700 px-4 py-2 font-semibold text-white hover:bg-indigo-600 md:col-span-4">Create event</button>
    </form>
  </div>

  <div class="rounded-2xl bg-white p-5 shadow">
    <div class="mb-4 text-lg font-semibold">Events and participants</div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="border-b text-left"><th class="px-2 py-2">Event</th><th class="px-2 py-2">Date / venue</th><th class="px-2 py-2">Scan window</th><th class="px-2 py-2">Participants</th><th class="px-2 py-2">Status</th><th class="px-2 py-2">Actions</th></tr></thead>
        <tbody>
          <?php while ($event = $events->fetch_assoc()): ?>
            <tr class="border-b align-top">
              <td class="px-2 py-3 font-medium"><?php echo h($event['event_name']); ?><div class="text-xs text-slate-500"><?php echo h($event['event_type'] ?? ''); ?></div></td>
              <td class="px-2 py-3"><?php echo h($event['event_date']); ?><div class="text-xs text-slate-500"><?php echo h($event['venue'] ?? ''); ?></div></td>
              <td class="px-2 py-3"><?php echo h($event['scan_in_start'].' - '.$event['scan_in_end']); ?><div class="text-xs text-slate-500">Out: <?php echo h($event['scan_out_start'].' - '.$event['scan_out_end']); ?></div></td>
              <td class="px-2 py-3"><?php echo number_format((int)$event['registered_count']); ?></td>
              <td class="px-2 py-3"><?php echo h($event['status']); ?></td>
              <td class="px-2 py-3"><div class="flex flex-wrap gap-2">
                <?php if ($event['status'] !== 'ACTIVE' && $event['status'] !== 'CLOSED'): ?><form method="post"><input type="hidden" name="action" value="activate"><input type="hidden" name="event_id" value="<?php echo (int)$event['id']; ?>"><button class="rounded bg-emerald-600 px-2 py-1 text-xs text-white">Activate</button></form><?php endif; ?>
                <?php if ($event['status'] !== 'CLOSED'): ?><form method="post"><input type="hidden" name="action" value="register_all"><input type="hidden" name="event_id" value="<?php echo (int)$event['id']; ?>"><button class="rounded bg-indigo-600 px-2 py-1 text-xs text-white">Register all active</button></form><form method="post"><input type="hidden" name="action" value="close"><input type="hidden" name="event_id" value="<?php echo (int)$event['id']; ?>"><button class="rounded bg-slate-700 px-2 py-1 text-xs text-white">Close</button></form><?php endif; ?>
              </div></td>
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
