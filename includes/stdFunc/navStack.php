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

function _nav_is_recordable(string $url): bool {
    // index/main.php is the SPA shell that hosts every other page in its
    // content iframe, not content itself — recording it lets nav_back_url()
    // hand a back button the shell's own URL, which gets loaded *into* the
    // iframe and nests a second shell (and sidebar) inside the first.
    foreach (['luk.php', 'logud.php', 'login.php', 'ajax=1', 'index/main.php'] as $p) {
        if (strpos($url, $p) !== false) return false;
    }
    return true;
}

function _nav_read(): array {
    $file = _nav_file();
    if (!is_file($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) return [];
    // Re-filter on every read, not just on push: a stack file written before
    // index/main.php was excluded can still hold that entry, and nav_back_url()
    // must never hand it out just because it predates this rule.
    return array_values(array_filter($data, '_nav_is_recordable'));
}

function _nav_write(array $stack): void {
    file_put_contents(_nav_file(), json_encode($stack), LOCK_EX);
}

function _nav_should_record(string $url): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') return false;
    return _nav_is_recordable($url);
}

function _nav_path(string $url): string {
    $url = preg_replace('/[&?]returside=[^&]*/', '', $url);
    $url = preg_replace('/#.*$/', '', $url);
    return rtrim($url, '?&');
}

function _nav_is_safe_target(string $url): bool {
    // Browsers strip leading control characters/whitespace before parsing a URL's
    // scheme, so "\tjavascript:..." would otherwise slip past the checks below.
    $trimmed = preg_replace('/^[\x00-\x20]+/', '', $url);
    // Reject anything with a URI scheme (incl. javascript:) or a protocol-relative
    // //host/... prefix — htmlspecialchars() alone doesn't stop a browser from
    // treating either as an absolute/external navigation target.
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $trimmed)) return false;
    if (strpos($trimmed, '//') === 0) return false;
    return true;
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
    // Priority 2: explicit returside — fallback for direct/bookmarked links.
    // Request-controlled, so it's validated here (unlike the stack above, which
    // only ever holds relative REQUEST_URI values PHP itself recorded).
    if ($returside && strpos($returside, 'luk.php') === false && _nav_is_safe_target($returside)) {
        return $returside;
    }
    return NAV_DEFAULT_URL;
}

endif;
