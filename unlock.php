<?php
require __DIR__ . '/includes/bootstrap.php'; require ROOT . '/includes/unlock-service.php';
$user=require_role('student'); $d=listing((int)get('id'));
if (!$d || !is_public($d)) abort_page(404,'This listing is not available.');
if (has_unlock((int)$d['id'])) { flash('You already unlocked this listing. No additional payment is needed.'); redirect('dorm-details.php?id=' . $d['id']); }
$errors=[];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { $result=simulate_unlock((int)$user['id'],(int)$d['id'],post('payment_method'),post('simulate_failure') === '1'); redirect('unlock-confirmation.php?reference=' . urlencode($result['reference'])); }
    catch (DomainException $ex) { $errors[]=$ex->getMessage(); }
}
$title='Unlock the full picture'; require ROOT . '/includes/header.php';
?>
<section class="container section narrow"><span class="eyebrow">ONE STEP CLOSER</span><h1>Unlock the full picture.</h1><p>Get the details you need to decide if this place is right for you.</p><div class="panel"><img class="checkout-image" src="<?= e(cover_url((int)$d['id'])) ?>" alt="<?= e($d['name']) ?>"><h2><?= e($d['name']) ?></h2><p><?= e($d['location']) ?> · <?= money($d['monthly_rent']) ?> / month</p><div class="alert alert-info"><strong>Academic simulation only.</strong> No money is collected. Never enter a real payment account or bank details.</div><?php errors_box($errors); ?><form method="post"><?= csrf() ?><?php select_field('Simulated payment method','payment_method',[['id'=>'gcash','name'=>'GCash (simulation)'],['id'=>'maya','name'=>'Maya (simulation)'],['id'=>'bank','name'=>'Bank Transfer (simulation)']],post('payment_method'),true,'Choose a method'); ?><div class="checkout-total"><span>One-time listing unlock</span><strong><?= money(scalar("SELECT setting_value FROM settings WHERE setting_key='unlock_fee'")) ?></strong></div><label class="check-label"><input type="checkbox" name="simulate_failure" value="1"> Demonstrate a failed transaction (no access granted)</label><button class="btn btn-primary w-100 mt-3">Confirm simulated unlock <i class="bi bi-unlock" aria-hidden="true"></i></button></form><p class="small mt-3">This unlock does not reserve a room. Pricing is a prototype assumption still requiring validation.</p></div></section>
<?php require ROOT . '/includes/footer.php'; ?>
