<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
if (current_user()) redirect(dashboard_url());
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempts = $_SESSION['login_attempts'] ?? [];
    $attempts = array_filter($attempts, fn($time) => $time > time()-300);
    if (count($attempts) >= 10) $errors[] = 'Too many attempts. Please wait five minutes.';
    else {
        $u = one('SELECT * FROM users WHERE email=?', [strtolower(post('email'))]);
        if ($u && $u['status'] === 'active' && password_verify(post('password'), $u['password_hash'])) {
            session_regenerate_id(true); $_SESSION['user_id'] = $u['id']; $_SESSION['csrf'] = bin2hex(random_bytes(32)); unset($_SESSION['login_attempts']);
            log_activity('auth.login'); redirect($u['role'] . '/dashboard.php');
        }
        $attempts[] = time(); $errors[] = 'The email or password is incorrect, or the account is inactive.';
    }
    $_SESSION['login_attempts'] = $attempts;
}
$title = 'Welcome back'; require ROOT . '/includes/header.php';
?>
<section class="auth-split"><div class="auth-visual"><img src="<?= url('assets/images/room-2.jpg') ?>" alt="" width="1000" height="667" decoding="async"><div><span class="eyebrow">Your next chapter starts here</span><h1>A little closer to feeling at home.</h1><p>Your saved places, your shortlist, and the details that matter to you.</p></div></div>
<div class="auth-panel"><div class="auth-form"><span class="eyebrow">Welcome back</span><h2>Good to see you again.</h2><p>Log in to pick up where you left off.</p><?php errors_box($errors); ?><form method="post"><?= csrf() ?><?php field('Email address','email',post('email'),'email','required maxlength="190" autocomplete="email"'); password_field('Password','password','required maxlength="72" autocomplete="current-password"'); ?><button class="btn btn-primary">Log in <i class="bi bi-arrow-right" aria-hidden="true"></i></button></form><p class="mt-4 mb-0">New around here? <a href="<?= url('auth/register.php') ?>">Create an account</a></p><div class="prototype-note">Academic prototype · Demo credentials are in the project README.</div></div></div></section>
<?php require ROOT . '/includes/footer.php'; ?>
