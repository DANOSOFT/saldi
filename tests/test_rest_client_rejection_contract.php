<?php
// 20260921 CDX/LH Verify the retryable client response using the actual server function and PostgreSQL.
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message) {
    throw new RuntimeException($message);
});
$dsn = getenv('SALDI_TEST_DSN') ?: getenv('SALDI_CHAR_PG_DSN');
if (!$dsn) {
    throw new RuntimeException('Set SALDI_TEST_DSN for an isolated PostgreSQL fixture');
}
$connection = pg_connect($dsn);
pg_query($connection, 'SET search_path TO pg_temp');
$db = 'client_rejection';
$brugernavn = 'client-test';
$work = sys_get_temp_dir() . '/saldi-client-rejection-' . getmypid();
mkdir($work . '/api', 0700, true);
mkdir($work . '/temp/' . $db, 0700, true);
symlink(dirname(__DIR__) . '/includes', $work . '/includes');
chdir($work . '/api');
function db_select($sql, $location = '') {
    if (!preg_match('/^\s*SELECT\b/i', $sql)) {
        throw new RuntimeException('Retryable rejection attempted a non-read query');
    }
    return pg_query($GLOBALS['connection'], $sql);
}
function db_modify($sql, $location = '') {
    throw new RuntimeException('Retryable rejection attempted an application mutation');
}
function db_fetch_array($result) {
    return pg_fetch_assoc($result);
}
function chk4utf8($value) {
    return $value;
}
require_once(__DIR__ . '/../includes/std_func.php');
require_once(__DIR__ . '/../includes/shopOrderInput.php');
$source = file_get_contents(__DIR__ . '/../api/rest_api.php');
$start = strpos($source, 'function insert_shop_orderline(');
$end = strpos($source, '\nfunction fakturer_ordre(', $start);
if ($end === false) {
    $end = strpos($source, "\nfunction fakturer_ordre(", $start);
}
if ($start === false || $end === false) {
    throw new RuntimeException('Missing production line function boundaries');
}
// Preserve the production include's directory when evaluating its function here.
eval(str_replace('__DIR__', var_export(dirname(__DIR__) . '/api', true), substr($source, $start, $end - $start)));
try {
    pg_query($connection, "CREATE TEMP TABLE ordrer(id int,status int,momssats numeric,valutakurs numeric,art text)");
    pg_query($connection, "CREATE TEMP TABLE varer(id int,varenr text,varenr_alias text,stregkode text,samlevare text)");
    pg_query($connection, "INSERT INTO ordrer VALUES(1,0,25,100,'DO')");
    $before = pg_fetch_all(pg_query($connection, 'SELECT * FROM ordrer'));
    ob_start();
    try {
        $result = insert_shop_orderline('client-test', 1, '', 'NOT-IN-SALDI', '1', 'Missing item', '100', '', '0', '1', '', '', '', '');
    } finally {
        $output = ob_get_clean();
    }
    if ($result !== 'Unknown item identity; no line created' || trim($output) !== '' || $before !== pg_fetch_all(pg_query($connection, 'SELECT * FROM ordrer')) || pg_num_rows(pg_query($connection, 'SELECT * FROM varer')) !== 0) {
        throw new RuntimeException('Server no-write rejection contract changed');
    }
    echo "PASS: actual production line function returns exact retryable unknown-SKU response without any application write or PHP warning\n";
} finally {
    foreach (glob($work . '/temp/' . $db . '/*') as $file) {
        unlink($file);
    }
    unlink($work . '/includes');
    chdir(sys_get_temp_dir());
    rmdir($work . '/temp/' . $db);
    rmdir($work . '/temp');
    rmdir($work . '/api');
    rmdir($work);
}
