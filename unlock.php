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
$fee=money(scalar("SELECT setting_value FROM settings WHERE setting_key='unlock_fee'"));
$methods=['gcash'=>['GCash','bi-phone'],'maya'=>['Maya','bi-wallet2'],'bank'=>['Bank Transfer','bi-bank']];
$title='Unlock the full picture'; require ROOT . '/includes/header.php';
?>
<section class="container section"><a class="link-arrow mb-4" href="<?= url('dorm-details.php?id=' . $d['id']) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to <?= e($d['name']) ?></a><div class="unlock-grid">
<div data-reveal="fade"><span class="eyebrow">One step closer</span><h1>Unlock the full picture.</h1><p>Get the details you need to decide if this place is right for you.</p><div class="panel"><img class="checkout-image" src="<?= e(cover_url((int)$d['id'])) ?>" alt="<?= e($d['name']) ?>" width="800" height="560"><h2><?= e($d['name']) ?></h2><p><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= e($d['location']) ?> · <?= money($d['monthly_rent']) ?> / month</p><h3 class="mt-4">What unlocking gives you</h3><ul class="unlock-list"><li><i class="bi bi-check2-circle" aria-hidden="true"></i>Exact address and map link</li><li><i class="bi bi-check2-circle" aria-hidden="true"></i>Owner name, phone and email</li><li><i class="bi bi-check2-circle" aria-hidden="true"></i>House rules, curfew and security notes</li><li><i class="bi bi-check2-circle" aria-hidden="true"></i>All photos and the full amenity list</li></ul></div></div>
<div class="panel" data-reveal="fade"><h2>Transaction summary</h2><div class="alert alert-info"><strong>Academic simulation only.</strong> No money is collected. Never enter a real payment account or bank details.</div><?php errors_box($errors); ?><form method="post"><?= csrf() ?>
<fieldset class="form-field"><legend>Simulated payment method</legend><div class="pay-options"><?php foreach ($methods as $value=>[$label,$icon]): ?><label class="pay-option"><input type="radio" name="payment_method" value="<?= $value ?>" <?= post('payment_method') === $value ? 'checked' : '' ?> required><span class="pay-card"><i class="bi <?= $icon ?>" aria-hidden="true"></i><span><strong><?= $label ?></strong><small>(simulation)</small></span></span></label><?php endforeach; ?></div></fieldset>
<div class="checkout-total"><span>One-time listing unlock</span><strong><?= $fee ?></strong></div><label class="check-label"><input type="checkbox" name="simulate_failure" value="1"> Demonstrate a failed transaction (no access granted)</label><button class="btn btn-primary btn-lg w-100 mt-3">Confirm simulated unlock <i class="bi bi-unlock" aria-hidden="true"></i></button></form><p class="small mt-3 mb-0">This unlock does not reserve a room. Pricing is a prototype assumption still requiring validation.</p></div></div></section>
<?php require ROOT . '/includes/footer.php'; ?>
