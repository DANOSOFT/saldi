<?php
// tests/shopify/support/run_pos_delivery.php
//
// Child-process runner for the POS delivery choices (K33) and the wished delivery date (K34):
// bootstraps the legacy database globals against one test tenant, includes the shipped
// systemdata/diverseIncludes/posSaleSettings.php and debitor/pos_ordre_includes/saleDeliveryFields.php,
// runs the given steps in order and prints one JSON result per step.
//   php run_pos_delivery.php <test_db> <UTF8|LATIN9> <postgresql|mysqli> '<steps json>'
//
// LATIN9 connects the way a LATIN9 installation does (client encoding LATIN9, pages in
// ISO-8859-15): posted strings are converted to ISO-8859-15 before a step sees them, and the
// strings a step returns are converted back to UTF-8 for the JSON. mysqli only names the database
// type: the functions must answer without a single query there, and any query would fail on the
// missing mysqli extension.
//
// Steps:
//   ["enabled"]                               pos_delivery_enabled()
//   ["choices", bool activeOnly]              pos_delivery_choices()
//   ["save_choices", {post}]                  pos_save_delivery_choices(post); alerts are returned
//   ["render_settings"]                       pos_render_delivery_choices_settings() output
//   ["sale_save", orderId, valg, dato]        pos_save_sale_delivery(); returns the refusal or null
//   ["render_sale", orderId, status]          pos_render_sale_delivery_fields() output
//   ["reset_sql"]                             pos_reset_sale_delivery_sql()
//   ["save_choices_utf8", {post}]             as save_choices, but posted as UTF-8 also on LATIN9 (a top-menu page)
//   ["attaches", valg]                        pos_delivery_choice_attaches()
//
// History:
// 20261120 CL/LH K33/K34: created.

if ($argc < 5) {
    fwrite(STDERR, "usage: php run_pos_delivery.php <test_db> <UTF8|LATIN9> <postgresql|mysqli> '<steps json>'\n");
    exit(2);
}
[, $testDb, $encode, $dbType, $stepsJson] = $argv;
$steps = json_decode($stepsJson, true, 512, JSON_THROW_ON_ERROR);

require_once __DIR__ . '/ShopifyEnv.php';
ShopifyEnv::assertSafeTestDb($testDb);

// The legacy layer reads the request; without it every query warns, and a child that fills its
// stderr pipe blocks before the parent reads it.
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = '/saldi/debitor/pos_ordre.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
error_reporting(E_ALL & ~E_DEPRECATED);

$GLOBALS['runAlerts'] = [];
function alert($msg)
{
    $GLOBALS['runAlerts'][] = $msg;
}

$db_type = $dbType;
$db_encode = $encode;
$brugernavn = 'k33test';
$sqdb = ShopifyEnv::masterDb();
$db = $testDb;
$db_skriv_id = null;
$webservice = true;
$sprog_id = 1;
$regnaar = 1;

require_once ShopifyEnv::repoRoot() . '/includes/db_query.php';
if ($dbType === 'postgresql') {
    $connection = db_connect(ShopifyEnv::pgHost(), ShopifyEnv::pgUser(), ShopifyEnv::pgPass(), $testDb);
}
require_once ShopifyEnv::repoRoot() . '/includes/std_func.php';
require_once ShopifyEnv::repoRoot() . '/systemdata/diverseIncludes/posSaleSettings.php';
require_once ShopifyEnv::repoRoot() . '/debitor/pos_ordre_includes/saleDeliveryFields.php';

$in = function ($v) use ($encode, &$in) {
    if ($encode !== 'LATIN9') {
        return $v;
    }
    if (is_string($v)) {
        return mb_convert_encoding($v, 'ISO-8859-15', 'UTF-8');
    }
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $x) {
            $out[$k] = $in($x);
        }
        return $out;
    }
    return $v;
};
$out = function ($v) use ($encode, &$out) {
    if ($encode !== 'LATIN9') {
        return $v;
    }
    if (is_string($v)) {
        return mb_convert_encoding($v, 'UTF-8', 'ISO-8859-15');
    }
    if (is_array($v)) {
        return array_map($out, $v);
    }
    return $v;
};

$results = [];
foreach ($steps as $step) {
    $GLOBALS['runAlerts'] = [];
    $name = array_shift($step);
    switch ($name) {
        case 'enabled':
            $r = pos_delivery_enabled();
            break;
        case 'choices':
            $r = pos_delivery_choices((bool)($step[0] ?? true));
            break;
        case 'save_choices':
            pos_save_delivery_choices($in($step[0]));
            $r = null;
            break;
        case 'save_choices_utf8':
            pos_save_delivery_choices($step[0]);
            $r = null;
            break;
        case 'attaches':
            $r = pos_delivery_choice_attaches($step[0]);
            break;
        case 'render_settings':
            ob_start();
            pos_render_delivery_choices_settings($sprog_id);
            $r = ob_get_clean();
            break;
        case 'sale_save':
            $r = pos_save_sale_delivery((int)$step[0], $in($step[1]), $in($step[2]));
            break;
        case 'render_sale':
            ob_start();
            pos_render_sale_delivery_fields((int)$step[0], (int)$step[1], $sprog_id);
            $r = ob_get_clean();
            break;
        case 'reset_sql':
            $r = pos_reset_sale_delivery_sql();
            break;
        default:
            fwrite(STDERR, "unknown step $name\n");
            exit(2);
    }
    $results[] = ['step' => $name, 'result' => $out($r), 'alerts' => $out($GLOBALS['runAlerts'])];
}
echo json_encode($results, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), "\n";
