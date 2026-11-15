<?php
$actor=require_role('owner','admin'); $isAdmin=$actor['role']==='admin'; $id=(int)get('id');
$existing=$id ? listing($id) : null;
if ($id && (!$existing || !can_manage($existing))) abort_page(404,'Listing not found or not yours to edit.');
$defaults=['name'=>'','location'=>'','exact_address'=>'','university_id'=>'','room_type_id'=>'','monthly_rent'=>'','capacity'=>'','available_slots'=>'','description'=>'','house_rules'=>'','curfew'=>'','security_info'=>'','contact_number'=>'','map_url'=>'','is_active'=>1,'owner_id'=>$isAdmin ? '' : $actor['id']];
$data=$existing ?? $defaults; $errors=[];
$amenityIds=$id ? array_column(rows('SELECT amenity_id FROM dormitory_amenities WHERE dormitory_id=?',[$id]),'amenity_id') : [];
$images=$id ? rows('SELECT id,file_path,is_public FROM dormitory_images WHERE dormitory_id=? ORDER BY id',[$id]) : [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    foreach ($defaults as $key=>$unused) $data[$key]=post($key);
    $data['owner_id']=$isAdmin ? (int)post('owner_id') : $actor['id'];
    $data['is_active']=post('is_active')==='1' ? 1 : 0;
    $amenityIds=array_values(array_unique(array_map('intval',is_array($_POST['amenities'] ?? null) ? $_POST['amenities'] : [])));
    $remove=array_values(array_unique(array_map('intval',is_array($_POST['remove_images'] ?? null) ? $_POST['remove_images'] : [])));
    foreach (['name'=>160,'location'=>120,'exact_address'=>255,'description'=>5000,'house_rules'=>5000,'curfew'=>255,'security_info'=>5000,'contact_number'=>30] as $key=>$max) {
        if ($data[$key]==='' || strlen($data[$key])>$max) $errors[]=ucfirst(str_replace('_',' ',$key)) . ' is required and must be at most ' . $max . ' bytes.';
    }
    if (!preg_match('/^[0-9+() .-]{7,30}$/D',$data['contact_number'])) $errors[]='Enter a valid contact number.';
    $rent=decimal($data['monthly_rent']); if (!$rent) $errors[]='Rent must be greater than zero, with at most two decimal places.';
    foreach (['capacity','available_slots'] as $key) if (!preg_match('/^(0|[1-9][0-9]{0,4})$/D',$data[$key])) $errors[]='Capacity and available slots must be whole numbers from 0 to 99,999.';
    if ((int)$data['available_slots']>(int)$data['capacity']) $errors[]='Available slots cannot exceed capacity.';
    foreach (['university_id'=>'universities','room_type_id'=>'room_types'] as $key=>$table) if (!scalar("SELECT COUNT(*) FROM $table WHERE id=?",[(int)$data[$key]])) $errors[]='Select a valid ' . str_replace('_id','',$key) . '.';
    if (!scalar("SELECT COUNT(*) FROM users WHERE id=? AND role='owner' AND status='active'",[$data['owner_id']])) $errors[]='Select an active owner.';
    $validAmenities=array_map('intval',array_column(rows('SELECT id FROM amenities'),'id'));
    if (!$amenityIds || array_diff($amenityIds,$validAmenities)) $errors[]='Choose at least one valid amenity.';
    if ($data['map_url']!=='' && (strlen($data['map_url'])>1000 || !filter_var($data['map_url'],FILTER_VALIDATE_URL) || strtolower((string)parse_url($data['map_url'],PHP_URL_SCHEME))!=='https')) $errors[]='Map link must be a valid HTTPS URL or left blank.';
    $knownImages=array_map('intval',array_column($images,'id'));
    if (array_diff($remove,$knownImages)) $errors[]='You can only remove images from this listing.';
    $uploads=[]; $fileSet=$_FILES['images'] ?? null;
    if ($fileSet && is_array($fileSet['error'])) foreach ($fileSet['error'] as $k=>$error) {
        if ($error===UPLOAD_ERR_NO_FILE) continue;
        if ($error!==UPLOAD_ERR_OK) { $errors[]='An image could not be uploaded. Check the file and PHP upload limits.'; continue; }
        $tmp=$fileSet['tmp_name'][$k]; $size=$fileSet['size'][$k];
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp); $dimensions=@getimagesize($tmp);
        if (!is_uploaded_file($tmp) || $size>5*1024*1024 || !in_array($mime,['image/jpeg','image/png','image/webp'],true) || !$dimensions || $dimensions[0]*$dimensions[1]>24000000 || ($dimensions['mime'] ?? '')!==$mime) { $errors[]='Images must be genuine JPEG, PNG, or WebP files, at most 5 MB and 24 megapixels.'; continue; }
        $uploads[]=['tmp'=>$tmp,'mime'=>$mime,'extension'=>['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]];
    }
    $count=count($images)-count($remove)+count($uploads);
    if ($count<1 || $count>8) $errors[]='Keep between one and eight images on your listing.';
    if (!$errors) {
        $savedPaths=[]; $pdo=db(); $pdo->beginTransaction();
        try {
            if ($id) {
                $locked=one('SELECT * FROM dormitories WHERE id=? FOR UPDATE',[$id]);
                if (!$locked || $locked['deleted_at'] || (!$isAdmin && (int)$locked['owner_id']!==(int)$actor['id'])) throw new DomainException('This listing is no longer editable.');
            }
            $columns=array_keys($defaults); $values=[]; foreach ($columns as $column) $values[]=$column==='monthly_rent' ? $rent : $data[$column];
            if ($id) query('UPDATE dormitories SET ' . implode(',',array_map(fn($c)=>$c . '=?',$columns)) . ",status='pending',rejection_reason=NULL WHERE id=?",[...$values,$id]);
            else { query('INSERT INTO dormitories (' . implode(',',$columns) . ') VALUES (' . implode(',',array_fill(0,count($columns),'?')) . ')',$values); $id=(int)$pdo->lastInsertId(); }
            foreach ($remove as $imageId) query('DELETE FROM dormitory_images WHERE id=? AND dormitory_id=?',[$imageId,$id]);
            foreach ($uploads as $upload) {
                if (db_setting('DORMFINDER_STORAGE_DRIVER','file')==='database') {
                    // Hosts without a durable disk keep the image bytes in the database; image.php serves them.
                    $bytes=file_get_contents($upload['tmp']); if ($bytes===false) throw new RuntimeException('Could not read the uploaded image.');
                    query('INSERT INTO dormitory_images (dormitory_id,file_path,image_data,image_mime,is_public) VALUES (?,?,?,?,0)',[$id,'database:' . bin2hex(random_bytes(20)) . '.' . $upload['extension'],$bytes,$upload['mime']]);
                    continue;
                }
                $path='uploads/dormitories/' . bin2hex(random_bytes(20)) . '.' . $upload['extension'];
                if (!move_uploaded_file($upload['tmp'],ROOT . '/' . $path)) throw new RuntimeException('Could not store the uploaded image.');
                $savedPaths[]=$path; query('INSERT INTO dormitory_images (dormitory_id,file_path,is_public) VALUES (?,?,0)',[$id,$path]);
            }
            query('UPDATE dormitory_images SET is_public=0 WHERE dormitory_id=?',[$id]);
            $first=scalar('SELECT MIN(id) FROM dormitory_images WHERE dormitory_id=?',[$id]);
            query('UPDATE dormitory_images SET is_public=1 WHERE id=?',[$first]);
            query('DELETE FROM dormitory_amenities WHERE dormitory_id=?',[$id]);
            foreach ($amenityIds as $amenity) query('INSERT INTO dormitory_amenities VALUES (?,?)',[$id,$amenity]);
            log_activity('listing.submit','Listing #' . $id . ' submitted for review'); $pdo->commit();
            // Removed files are kept private on disk for recovery; no public URL serves them.
            flash('Listing saved and submitted for administrator review.'); redirect($isAdmin ? 'admin/dormitories.php' : 'owner/listings.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($savedPaths as $path) if (is_file(ROOT . '/' . $path)) unlink(ROOT . '/' . $path);
            throw $ex;
        }
    }
}
$title=$existing ? 'Edit your listing' : 'Make room for a new chapter'; require __DIR__ . '/dashboard-start.php';
?>
<p>Every new or edited listing is submitted for review. Editing an approved listing temporarily removes it from public search until it is approved again.</p><?php errors_box($errors); ?><form class="panel" method="post" enctype="multipart/form-data"><?= csrf() ?><div class="form-grid"><?php
if ($isAdmin) select_field('Listing owner','owner_id',rows("SELECT id,name FROM users WHERE role='owner' AND status='active' ORDER BY name"),$data['owner_id'],true,'Choose owner');
field('Dormitory name','name',$data['name'],'text','required maxlength="160"'); field('General location / neighborhood','location',$data['location'],'text','required maxlength="120"'); field('Exact address (protected)','exact_address',$data['exact_address'],'text','required maxlength="255"');
select_field('Nearby university','university_id',rows('SELECT * FROM universities ORDER BY name'),$data['university_id'],true,'Choose university'); select_field('Room type','room_type_id',rows('SELECT * FROM room_types ORDER BY name'),$data['room_type_id'],true,'Choose room type'); field('Monthly rent (₱)','monthly_rent',$data['monthly_rent'],'number','required min="0.01" max="99999999.99" step="0.01"'); field('Maximum capacity','capacity',$data['capacity'],'number','required min="0" max="99999" step="1"'); field('Available slots','available_slots',$data['available_slots'],'number','required min="0" max="99999" step="1"'); field('Contact number (protected)','contact_number',$data['contact_number'],'tel','required maxlength="30"'); field('Curfew policy (protected)','curfew',$data['curfew'],'text','required maxlength="255"'); field('Map URL (optional, protected)','map_url',$data['map_url'],'url','maxlength="1000" placeholder="https://..."');
?></div><?php textarea_field('Basic description','description',$data['description']); textarea_field('House rules (protected)','house_rules',$data['house_rules']); textarea_field('Security information (protected)','security_info',$data['security_info']); ?><fieldset class="form-field"><legend>Amenities</legend><div class="amenity-list"><?php foreach (rows('SELECT * FROM amenities ORDER BY name') as $amenity): ?><label class="check-label"><input type="checkbox" name="amenities[]" value="<?= $amenity['id'] ?>" <?= in_array($amenity['id'],$amenityIds) ? 'checked' : '' ?>> <?= e($amenity['name']) ?></label><?php endforeach; ?></div></fieldset><div class="form-field"><label for="images">Room images (JPEG, PNG, WebP · 5 MB each · up to 8 total)</label><input class="form-control" type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/webp" <?= !$images ? 'required' : '' ?>><p class="small">The first remaining photo is the free preview. Additional photos require an unlock.</p></div><?php if ($images): ?><div class="image-manager"><?php foreach ($images as $im): ?><label><img src="<?= image_url((int)$im['id']) ?>" alt="Current listing photo"><span><input type="checkbox" name="remove_images[]" value="<?= $im['id'] ?>"> Remove image</span></label><?php endforeach; ?></div><?php endif; ?><label class="check-label"><input type="checkbox" name="is_active" value="1" <?= $data['is_active'] ? 'checked' : '' ?>> Active after approval (uncheck to keep this listing inactive)</label><button class="btn btn-primary mt-4">Save & submit for review</button></form>
<?php require __DIR__ . '/dashboard-end.php'; ?>
