<?php
require dirname(__DIR__) . '/includes/bootstrap.php'; require_role('admin'); $errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $amount=decimal(post('unlock_fee'));
    if (!$amount) $errors[]='Enter a positive fee with no more than two decimal places.';
    else { query("UPDATE settings SET setting_value=? WHERE setting_key='unlock_fee'",[$amount]); log_activity('settings.unlock_fee','Simulated fee changed to PHP ' . $amount); flash('Simulated unlock fee updated. Existing transactions retain their original amount.'); redirect('admin/settings.php'); }
}
$title='Prototype settings'; require ROOT . '/includes/dashboard-start.php';
?>
<div class="panel form-panel"><h2>One-time simulated unlock fee</h2><p>This price is a startup assumption requiring validation. No real payment is collected.</p><?php errors_box($errors); ?><form method="post"><?= csrf() ?><?php field('Unlock fee (₱)','unlock_fee',post('unlock_fee') ?: scalar("SELECT setting_value FROM settings WHERE setting_key='unlock_fee'"),'number','required min="0.01" max="99999999.99" step="0.01"'); ?><button class="btn btn-primary">Save fee</button></form></div>
<?php require ROOT . '/includes/dashboard-end.php'; ?>
