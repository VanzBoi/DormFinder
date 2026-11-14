<?php
declare(strict_types=1);
function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function query(string $sql, array $params = []): PDOStatement { $s = db()->prepare($sql); $s->execute($params); return $s; }
function rows(string $sql, array $params = []): array { return query($sql, $params)->fetchAll(); }
function one(string $sql, array $params = []): ?array { return query($sql, $params)->fetch() ?: null; }
function scalar(string $sql, array $params = []): mixed { return query($sql, $params)->fetchColumn(); }
function post(string $key): string { return is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : ''; }
function get(string $key): string { return is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : ''; }
function money(string|int $amount): string {
    [$whole, $fraction] = array_pad(explode('.', (string)$amount, 2), 2, '00');
    return '₱' . preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) . ($fraction === '00' ? '' : '.' . $fraction);
}
function decimal(string $value): ?string {
    if (!preg_match('/^(0|[1-9][0-9]{0,7})(?:\.([0-9]{1,2}))?$/D', $value, $m)) return null;
    $cents = ((int)$m[1] * 100) + (int)str_pad($m[2] ?? '', 2, '0');
    return $cents > 0 ? $m[1] . '.' . str_pad($m[2] ?? '', 2, '0') : null;
}
function csrf(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'; }
function check_csrf(): void {
    // PHP discards the whole body when it exceeds post_max_size, which would otherwise look like an expired form.
    if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) abort_page(413, 'That upload was too large. Use fewer or smaller images and try again.');
    if (!hash_equals($_SESSION['csrf'], post('csrf'))) abort_page(419, 'Your form expired. Reload the page and try again.');
}
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'][] = [$type, $message]; }
function log_activity(string $action, string $details = ''): void { query('INSERT INTO activity_logs (user_id,action,details) VALUES (?,?,?)', [current_user()['id'] ?? null, $action, substr($details, 0, 500)]); }
function abort_page(int $code, string $message): never {
    http_response_code($code); $title = 'Something needs your attention';
    require __DIR__ . '/header.php';
    echo '<section class="container section"><div class="empty-state"><span class="eyebrow">' . $code . '</span><h1>' . e($message) . '</h1><a class="btn btn-primary" href="' . url() . '">Back to home</a></div></section>';
    require __DIR__ . '/footer.php'; exit;
}
function public_condition(string $alias = 'd'): string { return "$alias.status='approved' AND $alias.is_active=1 AND $alias.deleted_at IS NULL"; }
function listing_select(): string { return 'SELECT d.*,u.name university,r.name room_type,o.name owner_name,o.email owner_email FROM dormitories d JOIN universities u ON u.id=d.university_id JOIN room_types r ON r.id=d.room_type_id JOIN users o ON o.id=d.owner_id'; }
function listing(int $id): ?array { return one(listing_select() . ' WHERE d.id=? AND d.deleted_at IS NULL', [$id]); }
function is_public(array $d): bool { return $d['status'] === 'approved' && (int)$d['is_active'] === 1 && !$d['deleted_at']; }
function can_manage(array $d): bool { $u = current_user(); return $u && ($u['role'] === 'admin' || ($u['role'] === 'owner' && (int)$u['id'] === (int)$d['owner_id'])); }
function has_unlock(int $id): bool {
    $u = current_user();
    return $u && $u['role'] === 'student' && (bool)scalar("SELECT COUNT(*) FROM unlocked_listings ul JOIN unlock_transactions t ON t.id=ul.transaction_id WHERE ul.user_id=? AND ul.dormitory_id=? AND t.status='success'", [$u['id'], $id]);
}
function image_url(int $id): string { return url('image.php?id=' . $id); }
function cover_url(int $id): string {
    $image = scalar('SELECT id FROM dormitory_images WHERE dormitory_id=? AND is_public=1 ORDER BY id LIMIT 1', [$id]);
    return $image ? image_url((int)$image) : url('assets/images/placeholder.svg');
}
function page_number(): int { return max(1, (int)get('page')); }
function paginate(int $total, int $per = 9): void {
    $pages = (int)ceil($total / $per); if ($pages < 2) return;
    echo '<nav aria-label="Pagination" class="pagination-links">';
    $current = page_number();
    foreach (array_unique([1, max(1,$current-1), $current, min($pages,$current+1), $pages]) as $p) {
        if ($p > $pages) continue;
        $params = $_GET; $params['page'] = $p;
        echo '<a class="btn ' . ($current === $p ? 'btn-primary' : 'btn-outline-primary') . '" href="?' . e(http_build_query($params)) . '"' . ($current === $p ? ' aria-current="page"' : '') . '>' . $p . '</a>';
    }
    echo '</nav>';
}
function field(string $label, string $name, mixed $value = '', string $type = 'text', string $extra = ''): void {
    echo '<div class="form-field"><label for="' . e($name) . '">' . e($label) . '</label><input class="form-control" id="' . e($name) . '" name="' . e($name) . '" type="' . e($type) . '" value="' . e($value) . '" ' . $extra . '></div>';
}
function select_field(string $label, string $name, array $options, mixed $value = '', bool $required = false, string $empty = 'All options'): void {
    echo '<div class="form-field"><label for="' . e($name) . '">' . e($label) . '</label><select class="form-select" name="' . e($name) . '" id="' . e($name) . '" ' . ($required ? 'required' : '') . '><option value="">' . e($empty) . '</option>';
    foreach ($options as $o) echo '<option value="' . e($o['id']) . '"' . ((string)$value === (string)$o['id'] ? ' selected' : '') . '>' . e($o['name']) . '</option>';
    echo '</select></div>';
}
function textarea_field(string $label, string $name, mixed $value, bool $required = true): void {
    echo '<div class="form-field"><label for="' . e($name) . '">' . e($label) . '</label><textarea class="form-control" rows="4" maxlength="5000" id="' . e($name) . '" name="' . e($name) . '" ' . ($required ? 'required' : '') . '>' . e($value) . '</textarea></div>';
}
function errors_box(array $errors): void { if ($errors) { echo '<div class="alert alert-danger" role="alert"><ul class="mb-0">'; foreach ($errors as $error) echo '<li>' . e($error) . '</li>'; echo '</ul></div>'; } }
function listing_cards(array $listings): void { foreach ($listings as $d) require __DIR__ . '/listing-card.php'; }
