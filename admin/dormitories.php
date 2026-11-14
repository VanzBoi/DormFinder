<?php
require dirname(__DIR__) . '/includes/bootstrap.php'; require_role('admin'); $errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)post('id'); $action=post('action'); $d=listing($id);
    if (!$d) abort_page(404,'Listing not found.');
    if ($action==='reject' && (post('reason')==='' || strlen(post('reason'))>500)) $errors[]='Provide a rejection reason of up to 500 bytes.';
    elseif ($action==='archive' && post('confirm')!=='1') $errors[]='Confirm archiving this listing.';
    else {
        $pdo=db(); $pdo->beginTransaction();
        try {
            one('SELECT id FROM dormitories WHERE id=? FOR UPDATE',[$id]);
            switch ($action) {
                case 'approve': query("UPDATE dormitories SET status='approved',rejection_reason=NULL WHERE id=?",[$id]); break;
                case 'reject': query("UPDATE dormitories SET status='rejected',rejection_reason=? WHERE id=?",[post('reason'),$id]); break;
                case 'activate': query('UPDATE dormitories SET is_active=1 WHERE id=?',[$id]); break;
                case 'deactivate': query('UPDATE dormitories SET is_active=0 WHERE id=?',[$id]); break;
                case 'archive': query('UPDATE dormitories SET deleted_at=NOW(),is_active=0 WHERE id=?',[$id]); break;
                default: throw new DomainException('Unknown listing action.');
            }
            log_activity('listing.' . $action,'Listing #' . $id); $pdo->commit(); flash('Listing updated. Historical records have been preserved.'); redirect('admin/dormitories.php');
        } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
    }
}
$where=['d.deleted_at IS NULL']; $params=[];
if (get('q')!=='') { $where[]='d.name LIKE ?'; $params[]='%' . get('q') . '%'; }
if (in_array(get('status'),['pending','approved','rejected'],true)) { $where[]='d.status=?'; $params[]=get('status'); }
if (get('university')!=='') { $where[]='d.university_id=?'; $params[]=(int)get('university'); }
if (in_array(get('active'),['0','1'],true)) { $where[]='d.is_active=?'; $params[]=get('active'); }
$condition=implode(' AND ',$where); $total=(int)scalar('SELECT COUNT(*) FROM dormitories d WHERE ' . $condition,$params);
$listings=rows(listing_select() . ' WHERE ' . $condition . ' ORDER BY d.id DESC LIMIT 10 OFFSET ' . ((page_number()-1)*10),$params);
$title='Manage dormitories'; require ROOT . '/includes/dashboard-start.php';
?>
<a class="btn btn-primary mb-4" href="<?= url('admin/add-listing.php') ?>">Add a listing</a><?php errors_box($errors); ?><form method="get" class="filter-panel"><div class="filter-grid"><?php field('Search name','q',get('q'),'search'); select_field('Review status','status',[['id'=>'pending','name'=>'Pending'],['id'=>'approved','name'=>'Approved'],['id'=>'rejected','name'=>'Rejected']],get('status')); select_field('University','university',rows('SELECT * FROM universities ORDER BY name'),get('university')); select_field('Visibility','active',[['id'=>'1','name'=>'Active'],['id'=>'0','name'=>'Inactive']],get('active')); ?><button class="btn btn-primary">Filter listings</button></div></form><div class="table-responsive panel table-panel"><table class="table"><thead><tr><th>Listing / owner</th><th>Price / slots</th><th>Status</th><th>Manage</th></tr></thead><tbody><?php foreach ($listings as $d): ?><tr><td><strong><?= e($d['name']) ?></strong><small class="d-block"><?= e($d['owner_name']) ?></small><small><?= e($d['university']) ?></small></td><td><?= money($d['monthly_rent']) ?><small class="d-block"><?= $d['available_slots'] ?>/<?= $d['capacity'] ?> slots</small></td><td><span class="status-badge <?= e($d['status']) ?>"><?= e($d['status']) ?></span><small class="d-block"><?= $d['is_active'] ? 'Active' : 'Inactive' ?></small></td><td class="admin-actions"><a href="<?= url('dorm-details.php?id=' . $d['id']) ?>">Review</a> · <a href="<?= url('admin/edit-listing.php?id=' . $d['id']) ?>">Edit</a><form method="post" class="mt-2"><?= csrf() ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="btn btn-sm btn-primary" name="action" value="approve">Approve</button> <button class="btn btn-sm btn-outline-primary" name="action" value="<?= $d['is_active'] ? 'deactivate' : 'activate' ?>"><?= $d['is_active'] ? 'Deactivate' : 'Activate' ?></button></form><details><summary>Reject or archive</summary><form method="post"><?= csrf() ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><label for="reason-<?= $d['id'] ?>">Rejection reason</label><input class="form-control form-control-sm" id="reason-<?= $d['id'] ?>" name="reason" maxlength="500" required><button name="action" value="reject" class="btn btn-sm btn-outline-danger mt-2">Reject</button></form><form method="post" class="mt-3"><?= csrf() ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><label class="check-label"><input type="checkbox" name="confirm" value="1" required> Confirm archive</label><button class="btn btn-sm btn-outline-danger" name="action" value="archive">Archive listing</button></form></details></td></tr><?php endforeach; ?><?php if (!$listings): ?><tr><td colspan="4">No matching listings.</td></tr><?php endif; ?></tbody></table></div><?php paginate($total,10); require ROOT . '/includes/dashboard-end.php'; ?>
