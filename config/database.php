<?php
declare(strict_types=1);
// XAMPP defaults. Override them with config/local.php (copy config/local.example.php; it is git-ignored)
// or with environment variables, which take precedence over both.
function db_setting(string $key, string $default): string
{
    static $local;
    $local ??= is_file(__DIR__ . '/local.php') ? (array)require __DIR__ . '/local.php' : [];
    $env = getenv($key);
    return $env !== false && $env !== '' ? $env : (string)($local[$key] ?? $default);
}
function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $host = db_setting('DB_HOST', '127.0.0.1');
        $port = db_setting('DB_PORT', '3306');
        $name = db_setting('DB_NAME', 'dormfinder_db');
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", db_setting('DB_USER', 'root'), db_setting('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+08:00'");
    }
    return $pdo;
}
