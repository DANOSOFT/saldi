<?php
// tests/characterization/includes/support/run_formular_fax_to_mobile.php
//
// Child-process runner for the SST-898 login step (FormularFaxToMobileLoginTest). Each run is one login with its
// own PostgreSQL session. It runs the SST-898 lines of includes/betweenUpdates.php as they stand, with
// db_select(), db_fetch_array(), db_modify() and db_escape_string() defined here (every write is recorded) and the
// real includes/std_func.php loaded, as index/login.php and debitor/pos_ordre.php load it before betweenUpdates.php.
//
// Usage: php run_formular_fax_to_mobile.php <db>
// Prints a single JSON object on the last line of stdout: {"writes": [<SQL of each write>]} or {"error": "..."}.
//
// History:
// 20261118 CL/MMR SST-898 (review 4) Created.
// 20261118 CL/MMR SST-898 (review 5) Loads the real includes/std_func.php (if_array()), with a __DIR__ path.

if ($argc < 2) {
    fwrite(STDERR, "usage: php run_formular_fax_to_mobile.php <db>\n");
    exit(2);
}
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$conn = pg_connect(sprintf(
    "host='%s' dbname='%s' user='%s' password='%s' connect_timeout=3",
    addslashes(getenv('SALDI_CHAR_PGHOST') ?: 'postgres'),
    addslashes($argv[1]),
    addslashes(getenv('SALDI_CHAR_PGUSER') ?: 'user'),
    addslashes(getenv('SALDI_CHAR_PGPASS') ?: '')
), PGSQL_CONNECT_FORCE_NEW);
if ($conn === false) {
    echo json_encode(['error' => "could not connect to {$argv[1]}"]) . "\n";
    exit(1);
}

$writes = [];

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
    $GLOBALS['writes'][] = $qtext;
    db_select($qtext, $spor);
    return '0' . chr(9) . 'query accepted';
}

function db_escape_string($value) {
    return pg_escape_string($GLOBALS['conn'], (string)$value);
}

$repo = dirname(__DIR__, 4);
$out = [];
try {
    require_once __DIR__ . '/../../../../includes/std_func.php';
    $db_type = 'postgresql';
    $source = (string)file_get_contents("$repo/includes/betweenUpdates.php");
    $pattern = "~require_once __DIR__ \\. '/opdat_func/formularFaxToMobile\\.php';\\s*formularFaxToMobile\\(\\);\\s*formularFaxToMobileTekster\\(\\);~";
    if (!preg_match($pattern, $source, $match)) {
        throw new RuntimeException('SST-898 step not found in includes/betweenUpdates.php');
    }
    eval(str_replace('__DIR__', var_export("$repo/includes", true), $match[0]));
    $out['writes'] = $writes;
} catch (Throwable $e) {
    $out['error'] = get_class($e) . ': ' . $e->getMessage();
}
echo json_encode($out) . "\n";
