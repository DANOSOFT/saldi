<?php
// Session-based navigation stack.
// Stores nav history in a per-session JSON file (NOT in $_SESSION) to avoid
// race conditions where concurrent AJAX requests overwrite the PHP session.
//
// Usage:
//   nav_push()               — called once per page load in online.php
//   nav_back_url($returside) — use in back buttons instead of $returside directly

if (!defined('NAV_STACK_MAX'))   define('NAV_STACK_MAX',   10);
if (!defined('NAV_DEFAULT_URL')) define('NAV_DEFAULT_URL', '../index/menu.php');

if (!function_exists('nav_push')):

function _nav_file(): string {
    return dirname(__DIR__, 2) . '/temp/nav_' . preg_replace('/[^a-zA-Z0-9]/', '', session_id()) . '.json';
}

function _nav_read(): array {
    $file = _nav_file();
    if (!is_file($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function _nav_write(array $stack): void {
    file_put_contents(_nav_file(), json_encode($stack), LOCK_EX);
}

function _nav_should_record(string $url): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') return false;
    foreach (['luk.php', 'logud.php', 'login.php', 'ajax=1'] as $p) {
        if (strpos($url, $p) !== false) return false;
    }
    return true;
}

function _nav_path(string $url): string {
    $url = preg_replace('/[&?]returside=[^&]*/', '', $url);
    $url = preg_replace('/#.*$/', '', $url);
    return rtrim($url, '?&');
}

function nav_push(?string $current_url = null, bool $popup = false): void {
    if ($popup) return;
    if ($current_url === null) $current_url = $_SERVER['REQUEST_URI'];
    if (!_nav_should_record($current_url)) return;

    $stack = _nav_read();
    $norm  = _nav_path($current_url);

    // Skip if same page as top of stack (reload / meta-refresh)
    if (!empty($stack) && _nav_path(end($stack)) === $norm) return;

    // If page already exists earlier in stack (user went back), truncate there
    for ($i = count($stack) - 1; $i >= 0; $i--) {
        if (_nav_path($stack[$i]) === $norm) {
            array_splice($stack, $i);
            break;
        }
    }

    $stack[] = $current_url;

    if (count($stack) > NAV_STACK_MAX) {
        array_splice($stack, 0, count($stack) - NAV_STACK_MAX);
    }

    _nav_write($stack);
}

function nav_back_url(?string $returside = null): string {
    // Priority 1: nav file history — reflects where the user actually came from
    $stack = _nav_read();
    if (count($stack) >= 2) {
        return $stack[count($stack) - 2];
    }
    // Priority 2: explicit returside — fallback for direct/bookmarked links
    if ($returside && strpos($returside, 'luk.php') === false) {
        return $returside;
    }
    return NAV_DEFAULT_URL;
}

endif;
