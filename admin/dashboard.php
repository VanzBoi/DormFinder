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
<p class="lead-copy">A clear view of the places, people, and activity on DormFinder.</p><div class="stats-grid"><?php foreach ($stats as $label=>$value): ?><div class="stat-card"><span><?= $label ?></span><strong><?= e($value) ?></strong></div><?php endforeach; ?></div><div class="panel"><h2>Help good listings find their students.</h2><p>Review pending submissions, check their details, and publish approved listings.</p><a class="btn btn-primary" href="<?= url('admin/dormitories.php?status=pending') ?>">Review pending listings</a></div><div class="alert alert-info mt-4">Revenue is simulated. Only successful prototype transactions are included.</div>
<?php require ROOT . '/includes/dashboard-end.php'; ?>
