<?php
// 20260921 CDX/LH Verify production barcode diagnostics survive a failed variant download.
namespace SaldiVaresyncTest;
if (PHP_SAPI !== 'cli') exit('CLI only');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new \RuntimeException($message); });
$dsn = getenv('SALDI_TEST_DSN');
if (!$dsn) exit("SKIP: set SALDI_TEST_DSN to a PostgreSQL test connection.\n");
if (!isset($argv[1])) {
    foreach (['available', 'missing'] as $mode) {
        $process = proc_open([PHP_BINARY, __FILE__, $mode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start barcode regression child');
        }
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0 || $errors !== '' || strpos($output, 'PASS: completed barcode diagnostics ' . $mode) === false) {
            throw new \RuntimeException('Barcode child did not finish its assertions: ' . $output . $errors);
        }
        echo $output;
    }
    exit(0);
}
$GLOBALS['csvConnection'] = pg_connect($dsn);
require_once(__DIR__ . '/../includes/std_func.php');
$source = file_get_contents(__DIR__ . '/../api/varesync.php');
// Namespace isolates transport/stock adapters; importer logic is the complete production function.
eval('namespace SaldiVaresyncTest; ' . preg_replace('/^<\?php|\?>\s*$/', '', $source));
function db_select($sql, $location = '') {
    $result = pg_query($GLOBALS['csvConnection'], $sql);
    if (!$result) throw new \RuntimeException(pg_last_error($GLOBALS['csvConnection']));
    return $result;
}
function db_modify($sql, $location = '') { return db_select($sql, $location); }
function db_fetch_array($result) { return pg_fetch_assoc($result); }
function db_num_rows($result) { return pg_num_rows($result); }
function db_escape_string($value) { return pg_escape_string($GLOBALS['csvConnection'], (string)$value); }
function shell_exec($command) { return ''; }
function system($command) {
    foreach (array('shop_products.csv' => 'csvProducts', 'shop_variants.csv' => 'csvVariants') as $name => $fixture) {
        if (strpos($command, '/files/' . $name) !== false && $GLOBALS[$fixture] !== null) {
            file_put_contents('../temp/' . $GLOBALS['db'] . '/' . $name, $GLOBALS[$fixture]);
        }
    }
    return '';
}
function lagerreguler($id, $qty, $unused, $unused2, $date, $variant) {
    $GLOBALS['stockCalls'][] = array((int)$id, (float)$qty, (int)$variant);
    db_modify('UPDATE varer SET beholdning=' . (float)$qty . ' WHERE id=' . (int)$id);
}
function dkdecimal($value) { return number_format((float)$value, 2, ',', '.'); }
function csvScalar($sql) { return pg_fetch_row(db_select($sql))[0]; }
function checkCsv($condition, $message) {
    if (!$condition) throw new \RuntimeException($message);
    echo "PASS: $message\n";
}
$work = sys_get_temp_dir() . '/saldi-csv-regression-' . getmypid();
mkdir($work . '/api', 0777, true);
$GLOBALS['db'] = 'csv_regression';
$GLOBALS['brugernavn'] = 'csv-regression';
mkdir($work . '/temp/' . $GLOBALS['db'], 0777, true);
chdir($work . '/api');
db_modify("CREATE TEMP TABLE varer (id serial PRIMARY KEY,varenr text,stregkode text,beskrivelse text,salgspris numeric,kostpris numeric,gruppe numeric,beholdning numeric,lukket text,min_lager int,varianter int DEFAULT 0)");
db_modify("CREATE TEMP TABLE shop_varer (id serial PRIMARY KEY,saldi_id int,shop_id int,saldi_variant int,shop_variant int)");
db_modify("CREATE TEMP TABLE grupper (art text,box4 text)");
db_modify("INSERT INTO grupper VALUES ('API','http://local-fixture.invalid/api.php')");
db_modify("CREATE TEMP TABLE settings (var_name text,var_grp text,var_value text)");
db_modify("CREATE TEMP TABLE varianter (id serial PRIMARY KEY,beskrivelse text)");
db_modify("CREATE TEMP TABLE variant_typer (id serial PRIMARY KEY,variant_id int,beskrivelse text)");
db_modify("CREATE TEMP TABLE variant_varer (id serial PRIMARY KEY,vare_id int,variant_type int,variant_beholdning numeric,variant_stregkode text,lager int,variant_salgspris numeric,variant_kostpris numeric,variant_vejlpris numeric,variant_id int)");
$variantHeader = '"varenr";"parent_id";"variant_id";"stregkode";"variant";"variant_type";"variant_text"';
$mode = $argv[1];
$GLOBALS['csvProducts'] = "id;varenr;stregkode;salgspris;beskrivelse;gruppe;tilbud;notes;qty\n101;OWNER;DUP-123;100;Owner;1;;;5\n102;SECOND;DUP-123;100;Second;1;;;6\n103;THIRD;DUP-123;100;Third;1;;;7\n";
$GLOBALS['csvVariants'] = $mode === 'missing' ? null : $variantHeader . "\n";
$GLOBALS['stockCalls'] = [];
$returned = false;
ob_start();
// Missing variants intentionally retain the existing early-exit behavior. Run
// assertions at shutdown so exit cannot falsely pass or hide persisted changes.
register_shutdown_function(function () use ($mode, &$returned, $work) {
    $output = ob_get_clean();
    try {
        checkCsv($returned === ($mode === 'available'), 'variant transport termination policy is unchanged');
        checkCsv(csvScalar("SELECT string_agg(varenr || ':' || coalesce(stregkode,''),',' ORDER BY id) FROM varer") === 'OWNER:DUP-123,SECOND:,THIRD:', 'first product owns barcode and only later duplicates are cleared');
        checkCsv(csvScalar("SELECT string_agg(v.varenr || ':' || s.shop_id,',' ORDER BY v.id) FROM shop_varer s JOIN varer v ON v.id=s.saldi_id") === 'OWNER:101,SECOND:102,THIRD:103', 'all products retain their exact shop identity');
        foreach (['SECOND:Second', 'THIRD:Third'] as $loser) {
            $message = 'Stregkode DUP-123 bruges også i OWNER:Owner -- stregkode slettet for ' . $loser . '<br>';
            checkCsv(substr_count($output, $message) === 1, 'one exact keeper/loser diagnostic for ' . $loser . ' with variants ' . $mode);
        }
        checkCsv(csvScalar("SELECT string_agg(beholdning::text,',' ORDER BY id) FROM varer") === '5,6,7', 'product quantities remain exact in both transport outcomes');
        checkCsv(count($GLOBALS['stockCalls']) === ($mode === 'available' ? 6 : 3), 'missing variants retain product stock writes but do not execute the final stock pass');
        echo 'PASS: completed barcode diagnostics ' . $mode . "\n";
    } catch (\Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    } finally {
        foreach (glob($work . '/temp/csv_regression/*') as $file) {
            unlink($file);
        }
        chdir(sys_get_temp_dir());
        rmdir($work . '/temp/csv_regression');
        rmdir($work . '/temp');
        rmdir($work . '/api');
        rmdir($work);
    }
});
varesync(1);
$returned = true;
