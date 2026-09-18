<?php
require_once dirname(__DIR__).'/config.php';
$conn=db();
$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $fineId=(int)($_POST['fine_id']??0);
    $amount=(float)($_POST['amount']??0);
    $method=$_POST['payment_method']??'CASH';
    $reference=trim($_POST['reference_number']??'');
    $remarks=trim($_POST['remarks']??'');
    if($fineId>0&&$amount>0){
        $s=$conn->prepare('INSERT INTO payments(fine_id,amount,payment_method,reference_number,remarks,recorded_by) VALUES(?,?,?,?,?,?)');
        $admin=(int)($_SESSION['admin_id']??0);
        $s->bind_param('idsssi',$fineId,$amount,$method,$reference,$remarks,$admin);
        if($s->execute()){
            $conn->query("UPDATE fines f SET status=CASE WHEN (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.fine_id=f.id)>=f.amount THEN 'PAID' ELSE 'PARTIALLY PAID' END WHERE f.id=$fineId");
            $message='Payment recorded and fine balance updated.';
        }
    }
}
$result=$conn->query("SELECT f.id,f.amount,f.status,f.reason,e.event_name,s.id student_id,s.first_name,s.last_name,COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.fine_id=f.id),0) paid FROM fines f JOIN events e ON e.id=f.event_id JOIN students s ON s.id=f.student_id ORDER BY s.last_name,s.first_name,f.created_at DESC");
$students=[];
while($fine=$result->fetch_assoc()){
    $id=(int)$fine['student_id'];
    if(!isset($students[$id])){$students[$id]=['name'=>$fine['last_name'].', '.$fine['first_name'],'fines'=>[],'fineTotal'=>0,'paidTotal'=>0];}
    $students[$id]['fines'][]=$fine;
    $students[$id]['fineTotal']+=(float)$fine['amount'];
    $students[$id]['paidTotal']+=(float)$fine['paid'];
}
$pageTitle='Fines & Payments';
ob_start();
?>
<div class="space-y-6">
<?php if($message): ?><div class="rounded-lg bg-emerald-100 p-3 text-emerald-800"><?php echo h($message); ?></div><?php endif; ?>
<div class="rounded-2xl bg-white p-5 shadow">
<div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div class="text-lg font-semibold">Payment management</div><label class="relative w-full sm:w-72"><span class="sr-only">Search student name</span><input id="paymentStudentSearch" type="search" placeholder="Search student name" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"></label></div>
<div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left"><th class="px-2 py-2">Student and fines</th><th class="px-2 py-2">Fine</th><th class="px-2 py-2">Paid</th><th class="px-2 py-2">Balance</th><th class="px-2 py-2">Status</th><th class="px-2 py-2">Record payment</th></tr></thead><tbody id="paymentRows">
<?php foreach($students as $student): $balanceTotal=max(0,$student['fineTotal']-$student['paidTotal']); $firstOpenFine=null; foreach($student['fines'] as $fine){if((float)$fine['amount']>(float)$fine['paid']){$firstOpenFine=$fine;break;}}; ?>
<tr class="border-b align-top" data-student-name="<?php echo h($student['name']); ?>">
<td class="px-2 py-3"><div class="font-semibold text-slate-900"><?php echo h($student['name']); ?></div><div class="mt-2 space-y-2"><?php foreach($student['fines'] as $fine): ?><div class="border-l-2 border-indigo-200 pl-3 text-xs text-slate-600"><?php echo h($fine['event_name'].' - '.$fine['reason']); ?></div><?php endforeach; ?></div><div class="mt-3 border-t pt-2 text-xs font-semibold">TOTAL</div></td>
<td class="px-2 py-3"><div class="space-y-2"><?php foreach($student['fines'] as $fine): ?><div>PHP <?php echo number_format($fine['amount'],2); ?></div><?php endforeach; ?></div><div class="mt-3 border-t pt-2 font-semibold">PHP <?php echo number_format($student['fineTotal'],2); ?></div></td>
<td class="px-2 py-3"><div class="space-y-2"><?php foreach($student['fines'] as $fine): ?><div>PHP <?php echo number_format($fine['paid'],2); ?></div><?php endforeach; ?></div><div class="mt-3 border-t pt-2 font-semibold">PHP <?php echo number_format($student['paidTotal'],2); ?></div></td>
<td class="px-2 py-3"><div class="space-y-2"><?php foreach($student['fines'] as $fine): ?><div>PHP <?php echo number_format(max(0,(float)$fine['amount']-(float)$fine['paid']),2); ?></div><?php endforeach; ?></div><div class="mt-3 border-t pt-2 font-semibold">PHP <?php echo number_format($balanceTotal,2); ?></div></td>
<td class="px-2 py-3"><div class="space-y-2"><?php foreach($student['fines'] as $fine): ?><div><?php echo h($fine['status']); ?></div><?php endforeach; ?></div><div class="mt-3 border-t pt-2 font-semibold">TOTAL</div></td>
<td class="px-2 py-3"><?php if($firstOpenFine): ?><form method="post" class="grid min-w-52 gap-2"><select id="fineSelect<?php echo (int)$student['fines'][0]['student_id']; ?>" name="fine_id" class="rounded border px-2 py-1" onchange="updatePaymentLimit(this)"><?php foreach($student['fines'] as $fine): $fineBalance=max(0,(float)$fine['amount']-(float)$fine['paid']); ?><option value="<?php echo (int)$fine['id']; ?>" data-balance="<?php echo h($fineBalance); ?>" <?php echo $fineBalance<=0?'disabled':''; ?>><?php echo h($fine['event_name']); ?> - Balance PHP <?php echo number_format($fineBalance,2); ?></option><?php endforeach; ?></select><input type="hidden" name="payment_method" value="CASH"><input name="amount" type="number" step="0.01" min="0.01" max="<?php echo h(max(0,(float)$firstOpenFine['amount']-(float)$firstOpenFine['paid'])); ?>" placeholder="Amount" required class="rounded border px-2 py-1"><div class="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-sm text-slate-600">CASH</div><input name="remarks" placeholder="Remarks" class="rounded border px-2 py-1"><button class="rounded bg-indigo-700 px-3 py-1 text-xs text-white">Record payment</button></form><?php else: ?><span class="text-emerald-700">PAID</span><?php endif; ?></td>
</tr>
<?php endforeach; ?><tr id="paymentNoResults" class="hidden"><td colspan="6" class="px-2 py-8 text-center text-slate-500">No students found.</td></tr></tbody></table></div></div></div>
<script>function updatePaymentLimit(select){var option=select.options[select.selectedIndex],amount=select.form.elements.amount;amount.max=option.dataset.balance;amount.value='';}document.getElementById('paymentStudentSearch').addEventListener('input',function(){var query=this.value.trim().toLowerCase(),rows=document.querySelectorAll('#paymentRows tr[data-student-name]'),matches=0;rows.forEach(function(row){var match=row.dataset.studentName.toLowerCase().indexOf(query)!==-1;row.classList.toggle('hidden',!match);if(match)matches++;});document.getElementById('paymentNoResults').classList.toggle('hidden',matches!==0);});</script>
<?php $content=ob_get_clean(); include __DIR__.'/_layout.php';