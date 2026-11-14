<header class="site-header"><div class="container nav-wrap">
<a class="brand" href="<?= url() ?>"><span class="brand-icon"><i class="bi bi-houses-fill" aria-hidden="true"></i></span>Dorm<span>Finder</span><span class="brand-dot">.</span></a>
<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Toggle navigation"><i class="bi bi-list" aria-hidden="true"></i></button>
<nav id="site-nav" aria-label="Main navigation"><a href="<?= url('browse.php') ?>">Find a dorm</a><a href="<?= url('index.php#how-it-works') ?>">How it works</a><a href="<?= url('compare.php') ?>">Compare <span id="compare-count"></span></a>
<?php if ($viewer): ?><a href="<?= url(dashboard_url()) ?>">My dashboard</a><form action="<?= url('auth/logout.php') ?>" method="post"><?= csrf() ?><button class="btn btn-outline-primary btn-sm">Log out</button></form>
<?php else: ?><a href="<?= url('auth/login.php') ?>">Log in</a><a class="btn btn-primary" href="<?= url('auth/register.php') ?>">Get started <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><?php endif; ?>
</nav></div></header>
