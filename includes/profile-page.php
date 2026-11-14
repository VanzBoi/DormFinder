<?php
$user=require_role('student','owner'); $errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (strlen(post('name'))<2 || strlen(post('name'))>100) $errors[]='Name must be 2–100 characters.';
    if (!filter_var(post('email'),FILTER_VALIDATE_EMAIL) || strlen(post('email'))>190) $errors[]='Enter a valid email.';
    if (strlen(post('phone'))>30 || (post('phone')!=='' && !preg_match('/^[0-9+() .-]{7,30}$/D',post('phone')))) $errors[]='Enter a valid phone number or leave it blank.';
    $hash=scalar('SELECT password_hash FROM users WHERE id=?',[$user['id']]);
    if (!password_verify(post('current_password'),$hash)) $errors[]='Enter your current password to save changes.';
    if (post('new_password')!=='' && (strlen(post('new_password'))<10 || strlen(post('new_password'))>72)) $errors[]='New password must be 10–72 bytes.';
    if (!$errors) {
        try {
            query('UPDATE users SET name=?,email=?,phone=?,password_hash=? WHERE id=?',[post('name'),strtolower(post('email')),post('phone'),post('new_password')!=='' ? password_hash(post('new_password'),PASSWORD_DEFAULT) : $hash,$user['id']]);
            session_regenerate_id(true); log_activity('profile.update'); flash('Your profile has been updated.'); redirect($user['role'] . '/profile.php');
        } catch (PDOException $ex) { if ($ex->getCode()!=='23000') throw $ex; $errors[]='That email is already in use.'; }
    }
}
$title='Make yourself at home'; require __DIR__ . '/dashboard-start.php';
?>
<div class="panel form-panel"><?php errors_box($errors); ?><form method="post"><?= csrf() ?><?php field('Full name','name',$_SERVER['REQUEST_METHOD']==='POST' ? post('name') : $user['name'],'text','required maxlength="100"'); field('Email address','email',$_SERVER['REQUEST_METHOD']==='POST' ? post('email') : $user['email'],'email','required maxlength="190"'); field('Phone number (optional)','phone',$_SERVER['REQUEST_METHOD']==='POST' ? post('phone') : $user['phone'],'tel','maxlength="30"'); field('Current password','current_password','','password','required autocomplete="current-password"'); field('New password (optional)','new_password','','password','minlength="10" maxlength="72" autocomplete="new-password"'); ?><button class="btn btn-primary">Save profile</button></form></div>
<?php require __DIR__ . '/dashboard-end.php'; ?>
