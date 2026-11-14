<?php
require dirname(__DIR__) . '/includes/bootstrap.php'; $user=require_role('student'); $title='Your unlocked places'; require ROOT . '/includes/dashboard-start.php';
$where='ul.user_id=? AND ' . public_condition();
$total=(int)scalar('SELECT COUNT(*) FROM dormitories d JOIN unlocked_listings ul ON ul.dormitory_id=d.id WHERE ' . $where,[$user['id']]);
$listings=rows(listing_select() . ' JOIN unlocked_listings ul ON ul.dormitory_id=d.id WHERE ' . $where . ' ORDER BY ul.created_at DESC LIMIT 9 OFFSET ' . ((page_number()-1)*9),[$user['id']]);
?>
<p>The full picture, whenever you need it. Inactive or archived properties are hidden; your transaction history remains available.</p><?php if ($listings): ?><div class="cards-grid dashboard-cards"><?php listing_cards($listings); ?></div><?php paginate($total); else: ?><div class="empty-state"><h2>No places unlocked yet.</h2><a class="btn btn-primary" href="<?= url('browse.php') ?>">Find a place</a></div><?php endif; require ROOT . '/includes/dashboard-end.php'; ?>
