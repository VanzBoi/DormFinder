<?php
require __DIR__ . '/includes/bootstrap.php'; $user=require_role('student');
$t=one('SELECT t.*,d.name FROM unlock_transactions t JOIN dormitories d ON d.id=t.dormitory_id WHERE t.reference=? AND t.user_id=?',[get('reference'),$user['id']]);
if (!$t) abort_page(404,'Transaction not found.');
$ok = $t['status']==='success';
$title='Simulated transaction result'; require ROOT . '/includes/header.php';
?>
<section class="container section narrow"><div class="panel text-center"><svg class="result-icon <?= $ok ? 'ok' : 'fail' ?>" viewBox="0 0 70 70" aria-hidden="true" focusable="false"><circle cx="35" cy="35" r="30"/><path d="<?= $ok ? 'M22 36 L31 45 L49 26' : 'M25 25 L45 45 M45 25 L25 45' ?>"/></svg><span class="eyebrow">Simulation only · no real payment</span><h1><?= $ok ? 'The details are yours.' : 'That simulation did not complete.' ?></h1><p><?= $ok ? 'You can now view the full details for ' : 'No access was granted for ' ?><?= e($t['name']) ?>.</p><p>Reference: <strong><?= e($t['reference']) ?></strong><br>Amount: <?= money($t['amount']) ?><br>Status: <?= e($t['status']) ?></p><a class="btn btn-primary btn-lg" href="<?= url(($ok ? 'dorm-details.php?id=' : 'unlock.php?id=') . $t['dormitory_id']) ?>"><?= $ok ? 'Explore full details' : 'Try again' ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div></section>
<?php require ROOT . '/includes/footer.php'; ?>
