<?php
require_once dirname(__DIR__).'/config.php';
require_admin_account();
$conn = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['admin_note'] ?? '');
    $check = $conn->prepare("SELECT * FROM event_requests WHERE event_request_id=? AND status='PENDING'");
    $check->bind_param('i', $requestId);
    $check->execute();
    $request = $check->get_result()->fetch_assoc();
    if (!$request) {
        $error = 'Pending event request not found.';
    } elseif ($action === 'reject') {
        $update = $conn->prepare("UPDATE event_requests SET status='REJECTED',admin_note=?,reviewed_by=?,reviewed_at=NOW() WHERE event_request_id=?");
        $adminId = (int)$_SESSION['admin_id'];
        $update->bind_param('sii', $note, $adminId, $requestId);
        $update->execute();
        $message = 'Event request rejected.';
    } elseif ($action === 'approve') {
        $conn->begin_transaction();
        $insert = $conn->prepare("INSERT INTO events(event_name,description,event_type,venue,event_date,start_time,end_time,scan_in_start,scan_in_end,scan_out_start,scan_out_end,fine_amount,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,'DRAFT')");
        $name=$request['event_name']; $description=$request['description']; $type=$request['event_type']; $venue=$request['venue']; $date=$request['event_date']; $start=$request['start_time']; $end=$request['end_time']; $inStart=$request['scan_in_start']; $inEnd=$request['scan_in_end']; $outStart=$request['scan_out_start']; $outEnd=$request['scan_out_end']; $fine=(float)$request['fine_amount'];
        $insert->bind_param('sssssssssssd', $name,$description,$type,$venue,$date,$start,$end,$inStart,$inEnd,$outStart,$outEnd,$fine);
        if (!$insert->execute()) {
            $conn->rollback();
            $error = 'Unable to create the approved event.';
        } else {
            $update = $conn->prepare("UPDATE event_requests SET status='APPROVED',admin_note=?,reviewed_by=?,reviewed_at=NOW() WHERE event_request_id=?");
            $adminId = (int)$_SESSION['admin_id'];
            $update->bind_param('sii', $note, $adminId, $requestId);
            if ($update->execute()) { $conn->commit(); $message = 'Request approved and event created as DRAFT.'; }
            else { $conn->rollback(); $error = 'Unable to approve the request.'; }
        }
    }
}
$rows = $conn->query('SELECT r.*,u.full_name,u.username FROM event_requests r JOIN ssc_users u ON u.id=r.ssc_user_id ORDER BY r.created_at DESC');
$pageTitle = 'Event Requests'; ob_start();
?>
<div class="space-y-6"><div class="rounded-2xl bg-white p-5 shadow"><div class="mb-4 text-lg font-semibold">SSC event requests</div><?php if($message): ?><div class="mb-4 rounded-lg bg-emerald-100 p-3 text-emerald-800"><?php echo h($message); ?></div><?php endif; ?><?php if($error): ?><div class="mb-4 rounded-lg bg-rose-100 p-3 text-rose-800"><?php echo h($error); ?></div><?php endif; ?><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left"><th class="px-2 py-2">Event</th><th class="px-2 py-2">Requested by</th><th class="px-2 py-2">Schedule</th><th class="px-2 py-2">Status</th><th class="px-2 py-2">Action</th></tr></thead><tbody><?php while($row=$rows->fetch_assoc()): ?><tr class="border-b align-top"><td class="px-2 py-3 font-medium"><?php echo h($row['event_name']); ?><div class="text-xs text-slate-500"><?php echo h($row['venue']); ?></div></td><td class="px-2 py-3"><?php echo h($row['full_name']); ?><div class="text-xs text-slate-500">@<?php echo h($row['username']); ?></div></td><td class="px-2 py-3"><?php echo h($row['event_date']); ?><div class="text-xs text-slate-500"><?php echo h($row['start_time'].' - '.$row['end_time']); ?></div></td><td class="px-2 py-3"><?php echo h($row['status']); ?></td><td class="px-2 py-3"><?php if($row['status']==='PENDING'): ?><form method="post" class="space-y-2"><input type="hidden" name="request_id" value="<?php echo (int)$row['event_request_id']; ?>"><input name="admin_note" placeholder="Note (optional)" class="w-full rounded border px-2 py-1"><div class="flex gap-2"><button name="action" value="approve" class="rounded bg-emerald-600 px-2 py-1 text-xs font-semibold text-white">Approve</button><button name="action" value="reject" class="rounded bg-rose-600 px-2 py-1 text-xs font-semibold text-white">Reject</button></div></form><?php else: ?>-<?php endif; ?></td></tr><?php endwhile; ?></tbody></table></div></div></div><?php $content=ob_get_clean(); include __DIR__.'/_layout.php';
