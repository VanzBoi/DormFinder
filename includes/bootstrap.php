<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$base = dirname($script);
if (in_array(basename($base), ['auth','student','owner','admin'], true)) $base = dirname($base);
// Normalise after both dirname() calls: on Windows dirname('/auth') returns '\', which would make links like '\/student/...'.
$base = str_replace('\\', '/', $base);
define('BASE_URL', rtrim($base === '.' ? '' : $base, '/'));
date_default_timezone_set('Asia/Manila');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'path'=>BASE_URL . '/']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");
require ROOT . '/config/database.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/auth.php';
set_exception_handler(function (Throwable $error): void {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo']->inTransaction()) $GLOBALS['pdo']->rollBack();
    error_log((string)$error);
    http_response_code(500);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>DormFinder needs attention</title><link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"><main class="container section"><h1>We couldn’t complete that request.</h1><p>Please try again. If you are setting up DormFinder, start MySQL in XAMPP and import database/dormfinder.sql. Check config/database.php and the PHP error log for details.</p><a href="' . e(url()) . '">Return home</a></main></html>';
});
if ($_SERVER['REQUEST_METHOD'] === 'POST') check_csrf();
