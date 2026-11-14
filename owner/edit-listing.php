<?php require dirname(__DIR__) . '/includes/bootstrap.php'; require_role('owner'); if (!(int)get('id')) abort_page(404,'Listing not found.'); require ROOT . '/includes/listing-form.php';
