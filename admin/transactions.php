<?php
require dirname(__DIR__) . '/includes/bootstrap.php'; require_role('admin');
$where=['1=1']; $params=[];
if (get('q')!=='') { $where[]='(t.reference LIKE ? OR u.name LIKE ? OR d.name LIKE ?)'; $params=array_fill(0,3,'%' . get('q') . '%'); }
if (in_array(get('status'),['success','failed'],true)) { $where[]='t.status=?'; $params[]=get('status'); }
if (in_array(get('method'),['gcash','maya','bank'],true)) { $where[]='t.payment_method=?'; $params[]=get('method'); }
$join=' FROM unlock_transactions t JOIN users u ON u.id=t.user_id JOIN dormitories d ON d.id=t.dormitory_id WHERE ' . implode(' AND ',$where);
$total=(int)scalar('SELECT COUNT(*)' . $join,$params);
$transactions=rows('SELECT t.*,u.name student,d.name dormitory' . $join . ' ORDER BY t.id DESC LIMIT 15 OFFSET ' . ((page_number()-1)*15),$params);
$title='Simulated transactions'; require ROOT . '/includes/dashboard-start.php';
?>
<p>Immutable transaction history. Historical amounts stay unchanged when the unlock fee changes.</p><a href="<?= url('admin/settings.php') ?>">Configure the simulated unlock fee</a><form method="get" class="inline-filter mt-4"><?php field('Reference, student, or dormitory','q',get('q'),'search'); select_field('Status','status',[['id'=>'success','name'=>'Successful'],['id'=>'failed','name'=>'Failed']],get('status')); select_field('Method','method',[['id'=>'gcash','name'=>'GCash'],['id'=>'maya','name'=>'Maya'],['id'=>'bank','name'=>'Bank Transfer']],get('method')); ?><button class="btn btn-primary">Filter</button></form><?php require ROOT . '/includes/transaction-table.php'; paginate($total,15); require ROOT . '/includes/dashboard-end.php'; ?>
