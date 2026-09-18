<?php
require_once dirname(__DIR__).'/config.php';
$conn = db();
$search = trim($_GET['search'] ?? '');
$like = '%'.$search.'%';
$query = $conn->prepare("SELECT event_name,event_type,venue,event_date,start_time,end_time,status FROM events WHERE (event_date < CURDATE() OR status IN ('COMPLETED','CLOSED','CANCELLED')) AND (?='' OR event_name LIKE ? OR event_type LIKE ? OR venue LIKE ?) ORDER BY event_date DESC, start_time DESC LIMIT 300");
$query->bind_param('ssss', $search, $like, $like, $like);
$query->execute();
$events = $query->get_result();
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="events-history.csv"');
	$output = fopen('php://output', 'w');
	fputcsv($output, ['Event', 'Type', 'Date', 'Time', 'Venue', 'Status']);
	while ($event = $events->fetch_assoc()) {
		fputcsv($output, [$event['event_name'], $event['event_type'], $event['event_date'], $event['start_time'].' - '.$event['end_time'], $event['venue'], $event['status']]);
	}
	fclose($output);
	exit;
}
$pageTitle = 'Events History';
ob_start();
?>
<div class="rounded-2xl bg-white p-5 shadow">
	<div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden"><div class="text-lg font-semibold">Events History</div><div class="flex w-full flex-wrap gap-2 sm:w-auto"><form class="flex min-w-0 flex-1 gap-2 sm:w-80"><input name="search" value="<?php echo h($search); ?>" placeholder="Search event history" aria-label="Search event history" class="min-w-0 flex-1 rounded border px-3 py-2 text-sm"><button class="rounded bg-slate-900 px-3 py-2 text-sm text-white">Search</button></form><a href="?export=csv&amp;search=<?php echo urlencode($search); ?>" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Download CSV</a><button type="button" onclick="window.print()" class="rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Save PDF</button></div></div>
	<div class="hidden text-lg font-semibold print:block">Events History<?php if($search): ?> - <?php echo h($search); ?><?php endif; ?></div>
	<div class="overflow-x-auto">
		<table class="min-w-full text-sm">
			<thead><tr class="border-b text-left"><th class="px-2 py-2">Event</th><th class="px-2 py-2">Type</th><th class="px-2 py-2">Date</th><th class="px-2 py-2">Time</th><th class="px-2 py-2">Venue</th><th class="px-2 py-2">Status</th></tr></thead>
			<tbody>
				<?php if ($events && $events->num_rows): ?>
					<?php while ($event = $events->fetch_assoc()): ?>
						<tr class="border-b align-top">
							<td class="px-2 py-3 font-medium"><?php echo h($event['event_name']); ?></td>
							<td class="px-2 py-3"><?php echo h($event['event_type'] ?? ''); ?></td>
							<td class="px-2 py-3"><?php echo h($event['event_date']); ?></td>
							<td class="px-2 py-3"><?php echo h($event['start_time'].' - '.$event['end_time']); ?></td>
							<td class="px-2 py-3"><?php echo h($event['venue'] ?? ''); ?></td>
							<td class="px-2 py-3"><?php echo h($event['status']); ?></td>
						</tr>
					<?php endwhile; ?>
				<?php else: ?>
					<tr><td colspan="6" class="px-2 py-6 text-center text-slate-500">No past events found.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
<style>@page{margin:0}@media print{aside,.admin-content>header,.admin-content>footer{display:none!important}.admin-content{margin-left:0!important}.admin-content main{padding:12mm!important}.admin-content main>div{box-shadow:none!important}.admin-content table{font-size:11px}}</style>
<?php $content = ob_get_clean(); include __DIR__.'/_layout.php';
