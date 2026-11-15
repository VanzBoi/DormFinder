<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
if (current_user()) redirect(dashboard_url());
$errors=[]; $role = post('role') ?: (get('role') === 'owner' ? 'owner' : 'student');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (strlen(post('name')) < 2 || strlen(post('name')) > 100) $errors[] = 'Enter a name between 2 and 100 characters.';
    if (!filter_var(post('email'), FILTER_VALIDATE_EMAIL) || strlen(post('email')) > 190) $errors[] = 'Enter a valid email address.';
    if (!in_array($role,['student','owner'],true)) $errors[] = 'Choose a student or owner account.';
    if (strlen(post('password')) < 10 || strlen(post('password')) > 72) $errors[] = 'Use a password between 10 and 72 bytes.';
    if (post('password') !== post('password_confirmation')) $errors[] = 'Your passwords do not match.';
    if (!$errors) {
        try {
            query('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)',[post('name'),strtolower(post('email')),password_hash(post('password'),PASSWORD_DEFAULT),$role]);
            $newId = (int)db()->lastInsertId(); /* read before regenerating: session handlers run their own queries */ session_regenerate_id(true); $_SESSION['user_id'] = $newId; $_SESSION['csrf']=bin2hex(random_bytes(32)); log_activity('auth.register'); flash('Welcome to DormFinder. Make yourself at home.'); redirect($role . '/dashboard.php');
        } catch (PDOException $ex) { if ($ex->getCode() !== '23000') throw $ex; $errors[]='That email is already registered. Please log in.'; }
    }
}
$title='Make yourself at home'; require ROOT . '/includes/header.php';
?>
<section class="auth-split"><div class="auth-visual"><img src="<?= url('assets/images/room-3.jpg') ?>" alt="" width="1000" height="677" decoding="async"><div><span class="eyebrow">Find your people. Find your place.</span><h1>Every new chapter needs a home base.</h1><p>Join DormFinder to save your favorites or share your space with students.</p></div></div>
<div class="auth-panel"><div class="auth-form"><span class="eyebrow">Let’s get you settled</span><h2>Create your account.</h2><p>It’s free to join and explore.</p><?php errors_box($errors); ?><form method="post"><?= csrf() ?><?php field('Full name','name',post('name'),'text','required maxlength="100" autocomplete="name"'); field('Email address','email',post('email'),'email','required maxlength="190" autocomplete="email"'); select_field('I am a','role',[['id'=>'student','name'=>'Student looking for a home'],['id'=>'owner','name'=>'Dormitory owner']],$role,true,'Choose your role'); password_field('Password (10–72 bytes)','password','required minlength="10" maxlength="72" autocomplete="new-password"'); password_field('Confirm password','password_confirmation','required minlength="10" maxlength="72" autocomplete="new-password"'); ?><button class="btn btn-primary">Create account <i class="bi bi-arrow-right" aria-hidden="true"></i></button></form><p class="mt-4 mb-0">Already have an account? <a href="<?= url('auth/login.php') ?>">Log in</a></p></div></div></section>
<?php require ROOT . '/includes/footer.php'; ?>
