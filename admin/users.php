<?php
require dirname(__DIR__) . '/includes/bootstrap.php'; require_role('admin');
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $target=one('SELECT * FROM users WHERE id=?',[(int)post('id')]);
    if (!$target || $target['role']==='admin') abort_page(403,'Administrator accounts cannot be changed here.');
    $status=post('status'); if (!in_array($status,['active','inactive'],true)) abort_page(422,'Invalid account status.');
    query('UPDATE users SET status=? WHERE id=?',[$status,$target['id']]); log_activity('user.' . $status,'User #' . $target['id']); flash('Account status updated. Inactive accounts lose access on their next request.'); redirect('admin/users.php');
}
$where=['1=1']; $params=[];
if (get('q')!=='') { $where[]='(u.name LIKE ? OR u.email LIKE ?)'; $params[]='%' . get('q') . '%'; $params[]='%' . get('q') . '%'; }
if (in_array(get('role'),['student','owner','admin'],true)) { $where[]='u.role=?'; $params[]=get('role'); }
if (in_array(get('status'),['active','inactive'],true)) { $where[]='u.status=?'; $params[]=get('status'); }
$condition=implode(' AND ',$where); $total=(int)scalar('SELECT COUNT(*) FROM users u WHERE ' . $condition,$params);
$users=rows('SELECT u.*,(SELECT COUNT(*) FROM dormitories d WHERE d.owner_id=u.id AND d.deleted_at IS NULL) listing_count,(SELECT COUNT(*) FROM unlocked_listings ul WHERE ul.user_id=u.id) unlock_count FROM users u WHERE ' . $condition . ' ORDER BY u.id DESC LIMIT 15 OFFSET ' . ((page_number()-1)*15),$params);
$title='People on DormFinder'; require ROOT . '/includes/dashboard-start.php';
?>
<form class="inline-filter" method="get"><?php field('Name or email','q',get('q'),'search'); select_field('Role','role',[['id'=>'student','name'=>'Student'],['id'=>'owner','name'=>'Owner'],['id'=>'admin','name'=>'Admin']],get('role')); select_field('Account status','status',[['id'=>'active','name'=>'Active'],['id'=>'inactive','name'=>'Inactive']],get('status')); ?><button class="btn btn-primary">Filter</button></form><div class="table-responsive panel table-panel"><table class="table"><thead><tr><th>Name / email</th><th>Role</th><th>Status</th><th>Listings / unlocks</th><th>Registered (PHT)</th><th>Action</th></tr></thead><tbody><?php foreach ($users as $u): ?><tr><td><?= e($u['name']) ?><small class="d-block"><?= e($u['email']) ?></small></td><td><?= e($u['role']) ?></td><td><?= e($u['status']) ?></td><td><?= $u['role']==='owner' ? $u['listing_count'] . ' listings' : ($u['role']==='student' ? $u['unlock_count'] . ' unlocks' : '—') ?></td><td><?= e($u['created_at']) ?></td><td><?php if ($u['role']!=='admin'): ?><form method="post"><?= csrf() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="status" value="<?= $u['status']==='active' ? 'inactive' : 'active' ?>"><button class="btn btn-sm btn-outline-primary"><?= $u['status']==='active' ? 'Deactivate' : 'Activate' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$users): ?><tr><td colspan="6">No matching accounts.</td></tr><?php endif; ?></tbody></table></div><?php paginate($total,15); require ROOT . '/includes/dashboard-end.php'; ?>
