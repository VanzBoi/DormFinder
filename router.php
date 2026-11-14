<?php
// PHP development server only: mirrors Apache's private-directory protection.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('~(?:^|/)(?:config|includes|database|uploads|tests|scripts|\.git|\.codex|\.agents)(?:/|$)|(?:^|/)\.|\.log$|README\.md$~i', $path)) {
    http_response_code(403); echo 'Forbidden'; return true;
}
return false;
