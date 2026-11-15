<?php
require dirname(__DIR__) . '/includes/bootstrap.php'; require_role('admin'); $title='The bigger picture'; require ROOT . '/includes/dashboard-start.php';
$stats=[
 'Total dormitory listings'=>scalar('SELECT COUNT(*) FROM dormitories WHERE deleted_at IS NULL'),
 'Active listings'=>scalar('SELECT COUNT(*) FROM dormitories d WHERE ' . public_condition()),
 'Pending approval'=>scalar("SELECT COUNT(*) FROM dormitories WHERE status='pending' AND deleted_at IS NULL"),
 'Registered students'=>scalar("SELECT COUNT(*) FROM users WHERE role='student'"),
 'Dormitory owners'=>scalar("SELECT COUNT(*) FROM users WHERE role='owner'"),
 'Simulated transactions'=>scalar('SELECT COUNT(*) FROM unlock_transactions'),
 'Successful simulated revenue'=>money(scalar("SELECT COALESCE(SUM(amount),0) FROM unlock_transactions WHERE status='success'")),
 'Available public slots'=>scalar('SELECT COALESCE(SUM(available_slots),0) FROM dormitories d WHERE ' . public_condition()),
];
?>
<p class="lead-copy">A clear view of the places, people, and activity on DormFinder.</p><div class="stats-grid"><?php $icons=['bi-houses','bi-check2-circle','bi-hourglass-split','bi-mortarboard','bi-building','bi-receipt','bi-cash-coin','bi-door-open']; $n=0; foreach ($stats as $label=>$value) stat_card($label,is_int($value)||ctype_digit((string)$value)?(int)$value:$value,$icons[$n++]); ?></div>
<div class="chart-grid"><?php
bar_chart('Listings by review status',['Approved'=>[(int)scalar("SELECT COUNT(*) FROM dormitories WHERE deleted_at IS NULL AND status='approved'"),'teal'],'Pending'=>[(int)scalar("SELECT COUNT(*) FROM dormitories WHERE deleted_at IS NULL AND status='pending'"),'amber'],'Rejected'=>[(int)scalar("SELECT COUNT(*) FROM dormitories WHERE deleted_at IS NULL AND status='rejected'"),'red']]);
bar_chart('Simulated transactions by method',['GCash'=>[(int)scalar("SELECT COUNT(*) FROM unlock_transactions WHERE payment_method='gcash'"),'blue'],'Maya'=>[(int)scalar("SELECT COUNT(*) FROM unlock_transactions WHERE payment_method='maya'"),'blue'],'Bank'=>[(int)scalar("SELECT COUNT(*) FROM unlock_transactions WHERE payment_method='bank'"),'blue']]);
bar_chart('Transaction outcomes',['Successful'=>[(int)scalar("SELECT COUNT(*) FROM unlock_transactions WHERE status='success'"),'teal'],'Failed'=>[(int)scalar("SELECT COUNT(*) FROM unlock_transactions WHERE status='failed'"),'red']]);
?></div><div class="panel" data-reveal><h2>Help good listings find their students.</h2><p>Review pending submissions, check their details, and publish approved listings.</p><a class="btn btn-primary" href="<?= url('admin/dormitories.php?status=pending') ?>">Review pending listings</a></div><div class="alert alert-info mt-4">Revenue is simulated. Only successful prototype transactions are included.</div>
<?php require ROOT . '/includes/dashboard-end.php'; ?>
