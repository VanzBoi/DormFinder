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
            session_regenerate_id(true); $_SESSION['user_id'] = db()->lastInsertId(); $_SESSION['csrf']=bin2hex(random_bytes(32)); log_activity('auth.register'); flash('Welcome to DormFinder. Make yourself at home.'); redirect($role . '/dashboard.php');
        } catch (PDOException $ex) { if ($ex->getCode() !== '23000') throw $ex; $errors[]='That email is already registered. Please log in.'; }
    }
}
$title='Make yourself at home'; require ROOT . '/includes/header.php';
?>
<section class="container auth-layout"><div class="auth-story"><span class="eyebrow">FIND YOUR PEOPLE. FIND YOUR PLACE.</span><h1>Every new chapter<br>needs a home base.</h1><p>Join DormFinder to save your favorites<br>or share your space with students.</p><img src="<?= url('assets/images/room-3.jpg') ?>" alt="Illustrative comfortable bedroom"></div><div class="auth-form"><span class="eyebrow">LET’S GET YOU SETTLED</span><h2>Create your account.</h2><p>It’s free to join and explore.</p><?php errors_box($errors); ?><form method="post"><?= csrf() ?><?php field('Full name','name',post('name'),'text','required maxlength="100" autocomplete="name"'); field('Email address','email',post('email'),'email','required maxlength="190" autocomplete="email"'); select_field('I am a','role',[['id'=>'student','name'=>'Student looking for a home'],['id'=>'owner','name'=>'Dormitory owner']],$role,true,'Choose your role'); field('Password (10–72 bytes)','password','','password','required minlength="10" maxlength="72" autocomplete="new-password"'); field('Confirm password','password_confirmation','','password','required minlength="10" maxlength="72" autocomplete="new-password"'); ?><button class="btn btn-primary w-100">Create account <i class="bi bi-arrow-right" aria-hidden="true"></i></button></form><p class="mt-4">Already have an account? <a href="<?= url('auth/login.php') ?>">Log in</a></p></div></section>
<?php require ROOT . '/includes/footer.php'; ?>
