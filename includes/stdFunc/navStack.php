<?php
// Session-based navigation stack.
// Stores nav history in a per-session JSON file (NOT in $_SESSION) to avoid
// race conditions where concurrent AJAX requests overwrite the PHP session.
//
// Usage:
//   nav_push()                        — called once per page load in online.php
//   nav_back_url($returside)          — use in back buttons instead of $returside directly
//   nav_sanitize_returside($url)      — central guard for request-supplied back targets
//   nav_forget() / nav_cleanup_old()  — file cleanup, called from index/logud.php
//
// Known limitation (documented, not fixed here): all tabs of one session share a
// single nav file, so one tab's back-truncation can splice away another tab's
// entries. A real fix needs a per-window key (window.name/tab token). Likewise
// NAV_DEFAULT_URL is relative and only resolves from one-level-deep directories;
// it cannot be made root-relative because installations may live under a subpath.
//
// 20260904 Sawaneh WP-1 of the return-link audit: explicit returside now beats the
//                  stack (with a bare-script guard), luk.php retursides are honoured
//                  so popup Luk closes, print pages are no longer recorded, dedup
//                  compares the path only, and nav files are cleaned up on logout.

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
    // udskriv/formularprint/koekkenprint are transient print/action pages: a back
    // button must never resolve to them (it would re-print the document).
    foreach (['luk.php', 'logud.php', 'login.php', 'ajax=1', 'index/main.php',
              'udskriv.php', 'formularprint.php', 'koekkenprint.php'] as $p) {
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

function _nav_page(string $url): string {
    // Path only, no query/fragment: the same script with different filters counts
    // as the same page when deduplicating, so the stack holds one entry per page
    // (always with the newest query).
    $url = preg_replace('/#.*$/', '', $url);
    $url = preg_replace('/\?.*$/', '', $url);
    return $url;
}

function _nav_is_safe_target(string $url): bool {
    // Browsers strip leading control characters/whitespace before parsing a URL's
    // scheme, so "\tjavascript:..." would otherwise slip past the checks below.
    $trimmed = preg_replace('/^[\x00-\x20]+/', '', $url);
    // Reject anything with a URI scheme (incl. javascript:) — htmlspecialchars()
    // alone doesn't stop a browser from treating it as an absolute navigation target.
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $trimmed)) return false;
    // Reject a leading pair of '/' and/or '\' in any combination. Browsers
    // normalize backslashes to forward slashes for special (http/https) URLs, so
    // "\\host/path" parses the same as "//host/path" — both are protocol-relative,
    // i.e. an external target, not just a plain "//" prefix.
    $lead = substr($trimmed, 0, 2);
    if (strlen($lead) === 2 && strpbrk($lead[0], '/\\') !== false && strpbrk($lead[1], '/\\') !== false) {
        return false;
    }
    return true;
}

function nav_push(?string $current_url = null, bool $popup = false): void {
    if ($popup) return;
    if ($current_url === null) $current_url = $_SERVER['REQUEST_URI'];
    if (!_nav_should_record($current_url)) return;

    $stack = _nav_read();
    $norm  = _nav_page($current_url);

    // Same page as top of stack (reload / filter change / meta-refresh):
    // replace the entry so the stack keeps the newest query instead of the stale one
    if (!empty($stack) && _nav_page(end($stack)) === $norm) {
        $stack[count($stack) - 1] = $current_url;
        _nav_write($stack);
        return;
    }

    // If page already exists earlier in stack (user went back), truncate there
    for ($i = count($stack) - 1; $i >= 0; $i--) {
        if (_nav_page($stack[$i]) === $norm) {
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

function nav_sanitize_returside(?string $url): string {
    // Central guard for request-supplied back targets (returside). They end up in
    // meta refresh / href attributes, so reject anything that could escape an
    // attribute or navigate off-site. Root-relative paths must pass: several pages
    // send urlencode($_SERVER['REQUEST_URI']) as their returside.
    // Do NOT run this over internally-built absolute URLs (the POS print flow
    // hands saldiprint/localprint absolute retursides) — GET/POST values only.
    if ($url === null || $url === '') return '';
    if (strpbrk($url, "<>\"'") !== false) return '';
    if (!_nav_is_safe_target($url)) return '';
    return $url;
}

function nav_back_url(?string $returside = null): string {
    $returside = nav_sanitize_returside($returside);

    // A luk.php returside means "close this window" (popup Luk) — honour it before
    // anything else, or the popup navigates to a page from the opener's history
    // instead of closing.
    if ($returside && strpos($returside, 'luk.php') !== false) {
        return $returside;
    }

    $stack = _nav_read();

    // Prefer the explicit returside: report pages are rendered from POST submits,
    // which nav_push() skips, so the stack can point at the unfiltered form while
    // the returside carries the filters the user chose.
    if ($returside) {
        // ...unless the returside is a bare script name and the stack holds the
        // same script *with* a query (typically the record id) — then the stack
        // entry is the more precise target.
        if (strpos($returside, '?') === false && count($stack) >= 2) {
            $prev = $stack[count($stack) - 2];
            if (strpos($prev, '?') !== false
                && basename((string)parse_url($prev, PHP_URL_PATH)) === basename($returside)) {
                return $prev;
            }
        }
        return $returside;
    }

    if (count($stack) >= 2) {
        return $stack[count($stack) - 2];
    }
    return NAV_DEFAULT_URL;
}

function nav_forget(): void {
    // Called on logout, while the session id is still valid.
    $file = _nav_file();
    if (is_file($file)) @unlink($file);
}

function nav_cleanup_old(int $maxAgeSeconds = 172800): void {
    // Nav files from sessions that never logged out cleanly; sweep on logout.
    $files = glob(dirname(__DIR__, 2) . '/temp/nav_*.json');
    if (!is_array($files)) return;
    foreach ($files as $file) {
        $mtime = @filemtime($file);
        if ($mtime !== false && $mtime < time() - $maxAgeSeconds) @unlink($file);
    }
}

endif;
