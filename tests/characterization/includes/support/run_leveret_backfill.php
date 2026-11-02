<?php
// tests/characterization/includes/support/run_leveret_backfill.php
//
// Child-process runner for the MB-73 leveret migration (OrderLineDeliveredBackfillTest).
// Each run is one login: its own PostgreSQL session, so advisory locks held by the test
// process are seen as another login's. db_select()/db_modify()/db_fetch_array() are thin
// pg_* wrappers here, defined in this process only.
//
// Modes (argv 2):
//   segment            - eval the MB-73 block of includes/betweenUpdates.php (SET DEFAULT + backfill)
//   function <budget> <window>
//                      - call backfillOrderLineDelivered('postgresql', <budget>, <window>)
//
// Prints a single JSON object on the last line of stdout.
//
// History:
// 20261027 CL/LH MB-73 (review): created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_leveret_backfill.php <db> <segment|function> [budget] [window]\n");
    exit(2);
}
$tenantDb = $argv[1];
$mode = $argv[2];
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$conn = pg_connect(sprintf(
    "host='%s' dbname='%s' user='%s' password='%s' connect_timeout=3",
    addslashes(getenv('SALDI_CHAR_PGHOST') ?: 'postgres'),
    addslashes($tenantDb),
    addslashes(getenv('SALDI_CHAR_PGUSER') ?: 'user'),
    addslashes(getenv('SALDI_CHAR_PGPASS') ?: '')
), PGSQL_CONNECT_FORCE_NEW);
if ($conn === false) {
    echo json_encode(['error' => "could not connect to $tenantDb"]) . "\n";
    exit(1);
}

function db_select($qtext, $spor) {
    $result = pg_query($GLOBALS['conn'], $qtext);
    if ($result === false) {
        throw new RuntimeException(pg_last_error($GLOBALS['conn']) . " -- $qtext");
    }
    return $result;
}

function db_fetch_array($result) {
    return pg_fetch_array($result);
}

function db_modify($qtext, $spor) {
    if (pg_query($GLOBALS['conn'], $qtext) === false) {
        throw new RuntimeException(pg_last_error($GLOBALS['conn']) . " -- $qtext");
    }
    return '0' . chr(9) . 'query accepted';
}

$repo = dirname(__DIR__, 4);
$out = ['status' => null];
try {
    if ($mode === 'segment') {
        $source = file_get_contents("$repo/includes/betweenUpdates.php");
        $start = strpos($source, '// 20261019 CL/LH MB-73: ordrelinjer.leveret had no default');
        $end = strrpos($source, '?>');
        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException('MB-73 block not found in includes/betweenUpdates.php');
        }
        $db_type = 'postgresql';
        // eval()'d code has no __DIR__ of its own; point it at includes/
        $segment = str_replace('__DIR__', var_export("$repo/includes", true), substr($source, $start, $end - $start));
        eval($segment);
        $out['status'] = 'ran';
    } elseif ($mode === 'function') {
        require_once "$repo/includes/opdat_func/backfillOrderLineDelivered.php";
        $out['status'] = backfillOrderLineDelivered('postgresql', (float)($argv[3] ?? 1.0), (int)($argv[4] ?? 20000));
    } else {
        throw new RuntimeException("unknown mode $mode");
    }
} catch (Throwable $e) {
    $out['error'] = get_class($e) . ': ' . $e->getMessage();
}
echo json_encode($out) . "\n";
