<?php
require __DIR__ . '/includes/bootstrap.php'; $user=require_role('student');
$t=one('SELECT t.*,d.name FROM unlock_transactions t JOIN dormitories d ON d.id=t.dormitory_id WHERE t.reference=? AND t.user_id=?',[get('reference'),$user['id']]);
if (!$t) abort_page(404,'Transaction not found.');
$title='Simulated transaction result'; require ROOT . '/includes/header.php';
?>
<section class="container section narrow"><div class="panel text-center"><span class="success-symbol"><i class="bi bi-<?= $t['status']==='success' ? 'check2-circle' : 'x-circle' ?>" aria-hidden="true"></i></span><span class="eyebrow">SIMULATION ONLY · NO REAL PAYMENT</span><h1><?= $t['status']==='success' ? 'The details are yours.' : 'That simulation did not complete.' ?></h1><p><?= $t['status']==='success' ? 'You can now view the full details for ' : 'No access was granted for ' ?><?= e($t['name']) ?>.</p><p>Reference: <strong><?= e($t['reference']) ?></strong><br>Amount: <?= money($t['amount']) ?><br>Status: <?= e($t['status']) ?></p><a class="btn btn-primary" href="<?= url(($t['status']==='success' ? 'dorm-details.php?id=' : 'unlock.php?id=') . $t['dormitory_id']) ?>"><?= $t['status']==='success' ? 'Explore full details' : 'Try again' ?></a></div></section>
<?php require ROOT . '/includes/footer.php'; ?>
