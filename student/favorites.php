<?php
require dirname(__DIR__) . '/includes/bootstrap.php'; $user=require_role('student');
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)post('id');
    if (scalar('SELECT COUNT(*) FROM favorites WHERE user_id=? AND dormitory_id=?',[$user['id'],$id])) { query('DELETE FROM favorites WHERE user_id=? AND dormitory_id=?',[$user['id'],$id]); flash('Removed from your saved places.'); }
    else { $d=listing($id); if (!$d || !is_public($d)) abort_page(404,'This listing is not available.'); query('INSERT IGNORE INTO favorites (user_id,dormitory_id) VALUES (?,?)',[$user['id'],$id]); flash('Saved to your places.'); }
    redirect('student/favorites.php');
}
$title='Your saved places'; require ROOT . '/includes/dashboard-start.php';
$where='f.user_id=? AND ' . public_condition();
$total=(int)scalar('SELECT COUNT(*) FROM dormitories d JOIN favorites f ON f.dormitory_id=d.id WHERE ' . $where,[$user['id']]);
$listings=rows(listing_select() . ' JOIN favorites f ON f.dormitory_id=d.id WHERE ' . $where . ' ORDER BY f.created_at DESC LIMIT 9 OFFSET ' . ((page_number()-1)*9),[$user['id']]);
?>
<p>Good possibilities, all in one place. Unavailable listings are hidden.</p><?php if ($listings): ?><div class="cards-grid dashboard-cards"><?php listing_cards($listings); ?></div><?php paginate($total); else: ?><div class="empty-state"><h2>Make a little room for your favorites.</h2><a class="btn btn-primary" href="<?= url('browse.php') ?>">Explore dorms</a></div><?php endif; require ROOT . '/includes/dashboard-end.php'; ?>
