<?php
require_once dirname(__DIR__).'/config.php';
$conn = db();
function metric($conn, $sql) { $row = $conn->query($sql)->fetch_assoc(); return (int)($row['value'] ?? 0); }
$active = active_event($conn);
$upcomingEvents = $conn->query("SELECT event_name,event_date,start_time,end_time,venue,status FROM events WHERE status IN ('UPCOMING','DRAFT') AND CONCAT(event_date,' ',start_time) >= NOW() ORDER BY event_date ASC, start_time ASC LIMIT 1");
$systemTitle = get_ssc_setting('organization_name', 'Student Entry and Exit Using a Barcode and SMS Integration System');
$metrics = [
	['Total Students', "SELECT COUNT(*) value FROM students", 'bg-white'],
	['Total SSC Users', "SELECT COUNT(*) value FROM ssc_users", 'bg-white'],
	['Total Events', "SELECT COUNT(*) value FROM events", 'bg-white'],
	['Active Events', "SELECT COUNT(*) value FROM events WHERE status='ACTIVE'", 'bg-emerald-50'],
	['Completed Events', "SELECT COUNT(*) value FROM events WHERE status IN ('COMPLETED','CLOSED')", 'bg-white'],
	['Present Students', "SELECT COUNT(*) value FROM event_attendance WHERE attendance_status='PRESENT'", 'bg-emerald-50'],
	['Absent Students', "SELECT COUNT(*) value FROM event_attendance WHERE attendance_status='ABSENT'", 'bg-rose-50'],
	['Late Students', "SELECT COUNT(*) value FROM event_attendance WHERE attendance_status='LATE'", 'bg-amber-50'],
	['Excused Students', "SELECT COUNT(*) value FROM event_attendance WHERE attendance_status='EXCUSED'", 'bg-white'],
	['Students With Unpaid Fines', "SELECT COUNT(DISTINCT student_id) value FROM fines WHERE status IN ('UNPAID','PARTIALLY PAID')", 'bg-rose-50'],
	['Total Unpaid Fines', "SELECT COUNT(*) value FROM fines WHERE status IN ('UNPAID','PARTIALLY PAID')", 'bg-rose-50'],
	['Total Paid Fines', "SELECT COUNT(*) value FROM fines WHERE status='PAID'", 'bg-emerald-50'],
	['Pending Clearances', "SELECT COUNT(*) value FROM clearance WHERE status='PENDING'", 'bg-white'],
	['Clearance On Hold', "SELECT COUNT(*) value FROM clearance WHERE status='ON HOLD'", 'bg-rose-50'],
	['Signed Clearances', "SELECT COUNT(*) value FROM clearance WHERE status='SIGNED'", 'bg-emerald-50']
];
foreach ($metrics as &$item) { $item[1] = metric($conn, $item[1]); }
$pageTitle='Admin Dashboard'; ob_start();
?>
<div class="space-y-6">
	<div class="rounded-3xl bg-gradient-to-r from-slate-950 via-indigo-950 to-indigo-900 p-5 text-white shadow-xl sm:p-6"><div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"><div class="min-w-0"><div class="text-xs font-semibold tracking-[.18em] text-violet-200">Welcome to</div><div class="mt-1 text-2xl font-bold"><?php echo h($systemTitle); ?></div><div id="dashboardClock" class="mt-2 text-sm text-white/70">Asia/Manila · Loading time...</div></div><div class="grid grid-cols-3 gap-2 lg:flex lg:flex-nowrap"><a href="events.php" class="inline-flex items-center justify-center rounded-lg bg-emerald-500 px-3 py-2 text-xs font-semibold text-white shadow-lg shadow-emerald-950/30 transition hover:-translate-y-0.5 hover:bg-emerald-400 sm:px-4 sm:text-sm">Manage Events</a><a href="students.php" class="inline-flex items-center justify-center rounded-lg bg-violet-500 px-3 py-2 text-xs font-semibold text-white shadow-lg shadow-violet-950/30 transition hover:-translate-y-0.5 hover:bg-violet-400 sm:px-4 sm:text-sm">Manage Students</a><a href="attendance.php" class="inline-flex items-center justify-center rounded-lg bg-slate-600 px-3 py-2 text-xs font-semibold text-white shadow-lg shadow-slate-950/30 transition hover:-translate-y-0.5 hover:bg-slate-500 sm:px-4 sm:text-sm">Attendance Reports</a></div></div></div>
	<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"><?php foreach($metrics as $metric): ?><div class="rounded-2xl <?php echo $metric[2]; ?> p-5 shadow"><div class="text-sm text-slate-500"><?php echo h($metric[0]); ?></div><div class="mt-1 text-3xl font-bold"><?php echo number_format($metric[1]); ?></div></div><?php endforeach; ?></div>
	<div class="grid gap-4 lg:grid-cols-2"><div class="rounded-2xl bg-white p-5 shadow"><div class="mb-4 text-lg font-semibold">Admin responsibilities</div><div class="grid gap-2 sm:grid-cols-2"><a href="students.php" class="rounded-lg bg-slate-50 p-3 hover:bg-indigo-50">Student management</a><a href="ssc_users.php" class="rounded-lg bg-slate-50 p-3 hover:bg-indigo-50">SSC accounts</a><a href="events.php" class="rounded-lg bg-slate-50 p-3 hover:bg-indigo-50">Events and participants</a><a href="payments.php" class="rounded-lg bg-slate-50 p-3 hover:bg-indigo-50">Fines and payments</a><a href="activity_logs.php" class="rounded-lg bg-slate-50 p-3 hover:bg-indigo-50">Activity logs</a><a href="attendance.php" class="rounded-lg bg-slate-50 p-3 hover:bg-indigo-50">Attendance monitoring</a></div></div><div class="space-y-4"><div class="rounded-2xl bg-white p-5 shadow"><div class="mb-4 text-lg font-semibold">Event status</div><?php if($active): ?><div class="rounded-xl bg-emerald-50 p-4"><div class="font-semibold text-emerald-900"><?php echo h($active['event_name']); ?></div><div class="mt-1 text-sm text-emerald-700"><?php echo h($active['venue'] ?? ''); ?> · <?php echo h($active['event_date']); ?></div><div class="mt-2 text-xs font-semibold uppercase tracking-wide text-emerald-700">ACTIVE</div></div><?php else: ?><div class="rounded-xl bg-slate-50 p-4 text-slate-600">No active event. Student scanning is disabled.</div><?php endif; ?></div><div class="rounded-2xl bg-white p-5 shadow"><div class="mb-3 flex items-center justify-between"><div class="text-lg font-semibold">Incoming events</div><a href="events.php" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">View all</a></div><?php if($upcomingEvents && $upcomingEvents->num_rows): ?><div class="space-y-2"><?php while($incoming=$upcomingEvents->fetch_assoc()): ?><div class="rounded-xl bg-slate-50 p-3"><div class="font-semibold text-slate-800"><?php echo h($incoming['event_name']); ?></div><div class="mt-1 text-sm text-slate-500"><?php echo h($incoming['event_date']); ?> · <?php echo h(substr($incoming['start_time'],0,5).' - '.substr($incoming['end_time'],0,5)); ?><?php if($incoming['venue']): ?> · <?php echo h($incoming['venue']); ?><?php endif; ?></div><div class="mt-1 text-xs font-semibold uppercase tracking-wide text-indigo-600"><?php echo h($incoming['status']); ?></div></div><?php endwhile; ?></div><?php else: ?><div class="rounded-xl bg-slate-50 p-4 text-slate-500">No incoming events.</div><?php endif; ?></div></div></div>
</div>
<script>
(function () {
  var clock = document.getElementById('dashboardClock');
  function updateClock() {
    if (clock) {
      clock.textContent = 'Asia/Manila · ' + new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'medium',
        timeZone: 'Asia/Manila'
      }).format(new Date());
    }
  }
  updateClock();
  setInterval(updateClock, 1000);
})();
</script>
<?php $content=ob_get_clean(); include __DIR__.'/_layout.php';
