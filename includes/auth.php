<?php
declare(strict_types=1);
function current_user(): ?array {
    // Cached per session user id, so a login or registration during this request is seen immediately.
    static $cache = [];
    $id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if (!$id) return null;
    if (!array_key_exists($id, $cache)) $cache[$id] = one("SELECT id,name,email,role,phone,status FROM users WHERE id=? AND status='active'", [$id]);
    if (!$cache[$id]) unset($_SESSION['user_id']);
    return $cache[$id];
}
function require_role(string ...$roles): array {
    $user = current_user();
    if (!$user) { flash('Please sign in to continue.', 'info'); redirect('auth/login.php'); }
    if (!in_array($user['role'], $roles, true)) abort_page(403, 'This page is not available for your account.');
    return $user;
}
function dashboard_url(): string { return (current_user()['role'] ?? 'student') . '/dashboard.php'; }
