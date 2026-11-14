<?php
require_role('admin'); $errors=[]; $foreign=$table==='universities' ? 'university_id' : 'room_type_id'; $limit=$table==='universities' ? 160 : 80;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)post('id'); $action=post('action'); $name=post('name');
    if ($action==='save' && ($name==='' || strlen($name)>$limit)) $errors[]='Enter a name of up to ' . $limit . ' bytes.';
    elseif ($action==='delete' && post('confirm')!=='1') $errors[]='Confirm deletion first.';
    elseif ($action==='delete' && scalar("SELECT COUNT(*) FROM dormitories WHERE $foreign=?",[$id])) $errors[]='This record is used by a listing and cannot be deleted (including archived listings).';
    elseif (!in_array($action,['save','delete'],true)) $errors[]='Unknown action.';
    else {
        try {
            if ($action==='delete') query("DELETE FROM $table WHERE id=?",[$id]);
            elseif ($id) query("UPDATE $table SET name=? WHERE id=?",[$name,$id]);
            else query("INSERT INTO $table (name) VALUES (?)",[$name]);
            log_activity($table . '.' . $action,$name ?: '#' . $id); flash('Reference data updated.'); redirect('admin/' . $route);
        } catch (PDOException $ex) { if ($ex->getCode()!=='23000') throw $ex; $errors[]='That name already exists, or this record is referenced by a listing.'; }
    }
}
$params=[]; $where='1=1'; if (get('q')!=='') { $where='name LIKE ?'; $params[]='%' . get('q') . '%'; }
$total=(int)scalar("SELECT COUNT(*) FROM $table WHERE $where",$params);
$records=rows("SELECT * FROM $table WHERE $where ORDER BY name LIMIT 10 OFFSET " . ((page_number()-1)*10),$params);
require __DIR__ . '/dashboard-start.php';
?>
<p>Names are unique regardless of capitalization. Referenced records cannot be deleted.</p><?php errors_box($errors); ?><form method="post" class="inline-filter panel"><?= csrf() ?><input type="hidden" name="action" value="save"><?php field('New name','name','','text','required maxlength="' . $limit . '"'); ?><button class="btn btn-primary">Add</button></form><form method="get" class="inline-filter"><?php field('Search','q',get('q'),'search'); ?><button class="btn btn-outline-primary">Search</button></form><div class="panel"><?php foreach ($records as $record): ?><div class="reference-row"><form method="post" class="inline-filter"><?= csrf() ?><input type="hidden" name="id" value="<?= $record['id'] ?>"><label class="visually-hidden" for="name-<?= $record['id'] ?>">Edit name</label><input class="form-control" id="name-<?= $record['id'] ?>" name="name" value="<?= e($record['name']) ?>" required maxlength="<?= $limit ?>"><button class="btn btn-outline-primary" name="action" value="save">Save</button></form><form method="post" class="inline-filter"><?= csrf() ?><input type="hidden" name="id" value="<?= $record['id'] ?>"><label class="check-label"><input type="checkbox" name="confirm" value="1" required> Confirm deletion</label><button class="btn btn-outline-danger" name="action" value="delete">Delete</button></form></div><?php endforeach; ?><?php if (!$records): ?><p>No matching records.</p><?php endif; ?></div><?php paginate($total,10); require __DIR__ . '/dashboard-end.php'; ?>
