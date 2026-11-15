<?php $viewer = current_user(); ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="Discover your next student home in Cebu City. DormFinder academic prototype with fictional listings."><meta name="theme-color" content="#0B2545"><title><?= e($title ?? 'Find your place') ?> · DormFinder</title><link rel="icon" href="<?= url('assets/images/favicon.svg') ?>" type="image/svg+xml">
<link rel="preload" href="<?= url('assets/fonts/inter-latin.woff2') ?>" as="font" type="font/woff2" crossorigin><link rel="preload" href="<?= url('assets/fonts/plus-jakarta-sans-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= url('assets/fonts/fonts.css') ?>"><link rel="stylesheet" href="<?= url('assets/vendor/bootstrap.min.css') ?>"><link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-icons.css') ?>"><link rel="stylesheet" href="<?= url('assets/css/style.css') ?>"><link rel="stylesheet" href="<?= url('assets/css/animations.css') ?>">
<script defer src="<?= url('assets/js/motion.js') ?>"></script><script defer src="<?= url('assets/js/script.js') ?>"></script></head><body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>
<?php if (empty($appShell)): ?><div class="scroll-sentinel" data-scroll-sentinel aria-hidden="true"></div><?php require __DIR__ . '/navbar.php'; ?>
<main id="main">
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?><div class="toast-stack" aria-live="polite"><?php foreach ($_SESSION['flash'] as [$type,$message]): ?><div class="toast-item toast-<?= e($type) ?>" role="status"><span><?= e($message) ?></span><button class="toast-close" type="button" aria-label="Dismiss message" hidden>&times;</button></div><?php endforeach; ?></div><?php endif; unset($_SESSION['flash']); ?>
