<?php $viewer = current_user(); ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="Discover your next student home in Cebu City. DormFinder academic prototype with fictional listings."><title><?= e($title ?? 'Find your place') ?> · DormFinder</title><link rel="icon" href="<?= url('assets/images/favicon.svg') ?>" type="image/svg+xml"><link rel="stylesheet" href="<?= url('assets/vendor/bootstrap.min.css') ?>"><link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-icons.css') ?>"><link rel="stylesheet" href="<?= url('assets/css/style.css') ?>"><script defer src="<?= url('assets/js/script.js') ?>"></script></head><body>
<a class="skip-link" href="#main">Skip to content</a>
<?php require __DIR__ . '/navbar.php'; ?>
<main id="main">
<?php foreach ($_SESSION['flash'] ?? [] as [$type,$message]): ?><div class="container mt-3"><div class="alert alert-<?= e($type) ?>" role="status"><?= e($message) ?></div></div><?php endforeach; unset($_SESSION['flash']); ?>
