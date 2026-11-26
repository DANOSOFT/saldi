<?php
// 20261126 CDX/NTR Isolate the real product-lookup controller, CSRF validator and online tenant-switch guard.
// 20261126 CDX/NTR Exercise classic-menu body output and allow content-type headers without simulating a redirect.

namespace ProductLookupSecurityFixture;

final class Finished extends \RuntimeException {}
final class Denied extends \RuntimeException {}
final class State
{
    public static int $writes = 0;
    public static int $status = 200;
    public static int $itemId = 41;
}

function session_start() { return true; }
function session_id() { return 'synthetic-session'; }
function get_settings_value(...$args) { return null; }
function headers_sent() { return false; }
function http_response_code($status = null) { if ($status !== null) State::$status = $status; return State::$status; }
function header($value) { if (str_starts_with($value, 'Location:')) { State::$status = 302; throw new Finished(); } }
function audit_log(...$args) {}
function findtekst($key, $language) { return explode('|', $key, 2)[1]; }
function db_select($sql, $where) { return ['art' => 'DO', 'status' => 1]; }
function db_fetch_array($row) { return $row; }
function externalPriceListActive() { return ['id' => 1]; }
function externalPriceListFind($list, $number) { return ['varenr' => $number]; }
function externalPriceListCreateItem($row, $list) { State::$writes++; return 41; }
function externalPriceListSelectItem($order, $number) { State::$writes++; return ['status' => 200, 'itemId' => State::$itemId, 'art' => 'DO']; }

/** @return void Execute the existing online.php guard, which clears stale requests. */
function onlineGuard($bg = null): void
{
    global $db;
    $sprog_id = 1;
    $source = file_get_contents(dirname(__DIR__, 4) . '/includes/online.php');
    $start = strpos($source, "if (isset(\$_POST['thisDb'])");
    $source = substr($source, $start, strpos($source, '$labelprint = 0;', $start) - $start);
    $source = preg_replace('/^\s*include_once\([^\n]*;/m', '', $source);
    eval('namespace ' . __NAMESPACE__ . ';' . $source);
    $online = file_get_contents(dirname(__DIR__, 4) . '/includes/online.php');
    $start = strpos($online, "if (\$bg != 'nix')");
    $body = substr($online, $start, strpos($online, '// Check if this is an API call', $start) - $start);
    eval($body);
    $isApiCall = $inScaffoldingContext = false;
    $title = '';
    $firstFolder = 'debitor';
    $path = '/ntr/debitor/productLookup.php';
    $buttonColor = $buttonTxtColor = '#fff';
    $start = strpos($online, 'if (!$isApiCall && substr($title');
    eval(substr($online, $start));
}

/** @return void Load the actual CSRF validator, replacing only its process exit with an observable exception. */
function boot(): void
{
    State::$writes = 0;
    State::$status = 200;
    State::$itemId = 41;
    if (!function_exists(__NAMESPACE__ . '\perm_require_post_csrf')) {
        $source = file_get_contents(dirname(__DIR__, 4) . '/includes/permissions.php');
        $start = strpos($source, 'function perm_require_post_csrf(');
        $source = substr($source, $start, strpos($source, "\n/**", $start) - $start);
        $source = str_replace('exit;', 'throw new Denied();', $source);
        eval('namespace ' . __NAMESPACE__ . ';' . $source);
    }
}
