<?php
// 20261113 CDX/NTR SD-599 Execute card registration with a disposable terminal file and database boundary doubles.

$scenario = $argv[1];
$entry = $argv[2];
$root = dirname(__DIR__, 3);
$fixture = sys_get_temp_dir() . '/saldi-card-' . bin2hex(random_bytes(8));
mkdir($fixture . '/temp/terminal', 0700, true);
mkdir($fixture . '/debitor', 0700);
chdir($fixture . '/debitor');
$db = 'terminal';
$regnaar = 1;
$sprog_id = 2;
$queries = [];
$warnings = [];

function db_select($sql, $source)
{
    global $queries, $scenario;
    $queries[] = $sql;
    if (str_contains($sql, "kodenr = '1'")) {
        return ['box5' => 'Visa'];
    }
    if (str_contains($sql, "kodenr = '2'")) {
        return ['box5' => '1000'];
    }
    if (str_contains($sql, 'select konto_id')) {
        return ['konto_id' => $scenario === 'mismatch' ? 9 : 0];
    }
    if (str_contains($sql, 'select count(id)')) {
        return ['tmp' => 0];
    }
    throw new RuntimeException('Unexpected SQL: ' . $sql);
}

function db_fetch_array($row)
{
    return $row;
}

function dkdecimal($amount, $decimals)
{
    return number_format($amount, $decimals, ',', '.');
}

function findtekst($id, $language)
{
    return 'text-' . $id;
}

set_error_handler(function ($severity, $message) use (&$warnings) {
    $warnings[] = $message;
    return true;
});

register_shutdown_function(function () use ($fixture, &$queries, &$warnings) {
    $html = ob_get_clean();
    $log = file_get_contents($fixture . '/temp/terminal/godkendt.txt');
    foreach (glob($fixture . '/temp/terminal/*') as $path) {
        unlink($path);
    }
    rmdir($fixture . '/temp/terminal');
    rmdir($fixture . '/temp');
    rmdir($fixture . '/debitor');
    rmdir($fixture);
    echo json_encode(['html' => $html, 'log' => $log, 'queries' => $queries, 'warnings' => $warnings, 'core_loaded' => function_exists('bogfor')]);
});
ob_start();
if ($scenario !== 'fallback') {
    file_put_contents($fixture . '/temp/terminal/41-7.txt', "Visa\t" . ($scenario === 'approved' ? '1250.5' : '0.005'));
}
require $root . ($entry === 'legacy' ? '/includes/ordrefunc.php' : '/includes/orderFuncIncludes/cardPayment.php');
registrer_betaling(7, 41, $scenario === 'fallback' ? 'Visa' : '!', 1250.5, 'DKK', 100, '127.0.0.1');
