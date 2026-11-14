<?php
require __DIR__ . '/includes/bootstrap.php';
$img = one('SELECT * FROM dormitory_images WHERE id=?', [(int)get('id')]);
$d = $img ? listing((int)$img['dormitory_id']) : null;
if (!$d || (!is_public($d) && !can_manage($d)) || (!$img['is_public'] && !can_manage($d) && !has_unlock((int)$d['id']))) { http_response_code(404); exit; }
$path = realpath(ROOT . '/' . $img['file_path']);
$allowed = $path && (str_starts_with($path, realpath(ROOT . '/assets/images') . DIRECTORY_SEPARATOR) || str_starts_with($path, realpath(ROOT . '/uploads/dormitories') . DIRECTORY_SEPARATOR));
if (!$allowed || !is_file($path)) { $path = ROOT . '/assets/images/placeholder.svg'; }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/svg+xml'], true)) { http_response_code(404); exit; }
header('Content-Type: ' . $mime); header('Content-Length: ' . filesize($path)); readfile($path);
