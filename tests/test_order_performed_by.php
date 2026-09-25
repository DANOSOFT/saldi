<?php
// tests/test_order_performed_by.php --- 2026-09-18
// Copyright (c) 2026 Danosoft ApS
// 20260918 CDX/PHR Verify the order page's actual employee-save statements using temporary rows.

error_reporting(E_ALL);
$root = dirname(__DIR__);
chdir($root . '/debitor');
require $root . '/includes/connect.php';
require_once $root . '/includes/std_func.php';
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) {
    throw new ErrorException($message, 0, $severity);
});
$_SERVER['REQUEST_URI'] = '/saldi/debitor/ordre.php';
$db = 'employee-test';
$brugernavn = 'test';
$workdir = sys_get_temp_dir() . '/saldi-employee-' . bin2hex(random_bytes(6));
mkdir($workdir . '/debitor', 0700, true);
mkdir($workdir . '/temp/' . $db, 0700, true);
chdir($workdir . '/debitor');
$connection = db_connect($sqhost, $squser, $sqpass, $sqdb);
if (!$connection || in_array($db_type, ['mysql', 'mysqli'], true)) {
    throw new RuntimeException('A local PostgreSQL connection is required.');
}
$source = file_get_contents($root . '/debitor/ordre.php');
$start = strpos($source, "\t\t\t\tif (\$performed_by === null)");
$end = strpos($source, "\t\t\t\tif (strlen(\$levdate)", $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Employee-save block not found.');
}
$readEmployee = substr($source, $start, $end - $start);
if (!preg_match('/^\s*\$qtxt \.= "fakturanr=.*performed_by=.*;$/m', $source, $match)) {
    throw new RuntimeException('Employee UPDATE fragment not found.');
}
$writeEmployee = $match[0];
function employeeTestQuery($sql)
{
    global $connection;
    $result = pg_query($connection, $sql);
    if ($result === false) {
        throw new RuntimeException(pg_last_error($connection));
    }
    return $result;
}
employeeTestQuery('BEGIN');
try {
    // A temporary table shadows ordrer on this connection. No tenant order is changed.
    employeeTestQuery('CREATE TEMP TABLE ordrer (id integer, performed_by text, hvem text, fakturanr text, lev_adr text, tidspkt text, projekt text) ON COMMIT DROP');
    $id = 1;
    $fakturanr = $lev_adr = $tidspkt = '';
    $projekt = [''];
    foreach ([
        [['performed_by' => "Søren O'Neil"], "Søren O'Neil"],
        [['performed_by' => ''], ''],
        [[], 'Existing employee'],
        [['hvem' => 'Old form or system user'], 'Existing employee'],
    ] as [$post, $expected]) {
        employeeTestQuery('DELETE FROM ordrer');
        employeeTestQuery("INSERT INTO ordrer (id,performed_by,hvem) VALUES (1,'Existing employee','system-user')");
        $_POST = $post;
        $performed_by = isset($_POST['performed_by']) && is_string($_POST['performed_by']) ? $_POST['performed_by'] : null;
        eval($readEmployee);
        $qtxt = '';
        eval($writeEmployee);
        employeeTestQuery('UPDATE ordrer SET ' . rtrim($qtxt, ',') . ' WHERE id=1');
        $saved = pg_fetch_assoc(employeeTestQuery('SELECT performed_by,hvem FROM ordrer WHERE id=1'));
        if ($saved['performed_by'] !== $expected || $saved['hvem'] !== 'system-user') {
            throw new RuntimeException('Employee persistence or system-user isolation failed.');
        }
    }
    echo "OK: 4 employee persistence cases; hvem unchanged.\n";
} finally {
    employeeTestQuery('ROLLBACK');
    chdir($root);
    foreach (glob($workdir . '/temp/' . $db . '/.*') as $file) {
        if (is_file($file)) unlink($file);
    }
    rmdir($workdir . '/temp/' . $db);
    rmdir($workdir . '/temp');
    rmdir($workdir . '/debitor');
    rmdir($workdir);
}
