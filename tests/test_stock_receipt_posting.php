<?php
// 20260921 CDX/LH Execute real receipt posting, including two independent PostgreSQL workers.
// 20260921 CDX/LH Preserve exact thousandths at large positive and negative stock balances.
// 20260921 CDX/LH Cover unrestricted numeric stock, exact fine-scale rollback and legacy default warehouses.
// 20260922 CDX/LH Prefer literal warehouse1, preserve legacy0 fallback and reject only selected-literal duplicates.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) {
    throw new RuntimeException($message);
});
$dsn = getenv('SALDI_TEST_DSN');
if (!$dsn) {
    exit("SKIP: set SALDI_TEST_DSN to an isolated PostgreSQL fixture.\n");
}
$connection = pg_connect($dsn);
$db_type = 'postgresql';
$db_transaktion_depth = 0;
$db_modify_fejl = false;
$brugernavn = "fixture's receiver";
$db = 'receipt-fixture';
function db_select($sql, $where = '') {
    return pg_query($GLOBALS['connection'], $sql);
}
function db_fetch_array($result) {
    return pg_fetch_assoc($result);
}
function db_modify($sql, $where = '') {
    return db_select($sql);
}
function db_escape_string($value) {
    return pg_escape_string($GLOBALS['connection'], $value);
}
function get_relative() {
    return '';
}
function db_log_append($path, $text) {
}
require_once __DIR__ . '/../includes/std_func.php';
$source = file_get_contents(__DIR__ . '/../lager/modtagelse.php');
$start = strpos($source, 'function receiptQuantity(');
$end = strpos($source, '@session_start();', $start);
eval(substr($source, $start, $end - $start));
$start = strpos($source, 'function modtag($liste_id)');
$end = strpos($source, '\n?>', $start);
if ($end === false) {
    $end = strpos($source, "\n?>", $start);
}
eval(substr($source, $start, $end - $start));
$source = file_get_contents(__DIR__ . '/../includes/db_query.php');
$start = strpos($source, "if (!function_exists('transaktion'))");
$end = strpos($source, "if (!function_exists('db_escape_string'))", $start);
eval(substr($source, $start, $end - $start));
function receiptValue($sql) {
    return pg_fetch_result(db_select($sql), 0, 0);
}
function postingCheck($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}
if (($argv[1] ?? '') === '--worker') {
    $schema = $argv[2];
    if (!preg_match('/^receipt_[a-f0-9]+$/D', $schema)) {
        throw new RuntimeException('Unsafe fixture schema');
    }
    db_select("SET search_path TO $schema");
    db_select("SET application_name TO '" . db_escape_string($argv[4]) . "'");
    ob_start();
    $result = modtag((int)$argv[3]);
    ob_end_clean();
    echo json_encode(['result' => (bool)$result]);
    exit;
}
$schema = 'receipt_' . bin2hex(random_bytes(8));
db_select("CREATE SCHEMA $schema; SET search_path TO $schema");
try {
    db_select("CREATE TABLE modtageliste(id integer PRIMARY KEY,modtaget text,modtaget_af text,modtagdate date,tidspkt time);
CREATE TABLE modtagelser(id integer PRIMARY KEY,liste_id integer,vare_id integer,antal numeric(15,3),lager numeric(15,3));
CREATE TABLE varer(id integer PRIMARY KEY,beholdning numeric(15,3));
CREATE TABLE ordrer(id integer PRIMARY KEY,art text,status text,ordredate date);
CREATE TABLE ordrelinjer(id integer PRIMARY KEY,ordre_id integer,vare_id integer,antal numeric(15,3),leveres numeric(15,3),lager integer,variant_id integer,batch_due_date date,batch_batch_no text);
CREATE TABLE batch_kob(id serial PRIMARY KEY,kobsdate date,vare_id integer,variant_id integer,linje_id integer,ordre_id integer,antal numeric(15,3),rest numeric(15,3),lager integer,due_date date,batch_no text);
CREATE TABLE lagerstatus(id serial PRIMARY KEY,vare_id integer,variant_id integer,lager integer,beholdning numeric(15,3));
CREATE TABLE variant_varer(id integer PRIMARY KEY,vare_id integer,variant_beholdning numeric(15,3));");
    function postingReset() {
        db_select("TRUNCATE modtageliste,modtagelser,varer,ordrer,ordrelinjer,batch_kob,lagerstatus,variant_varer RESTART IDENTITY;
INSERT INTO modtageliste VALUES(1,'-',NULL,NULL,NULL),(2,'-',NULL,NULL,NULL);
INSERT INTO modtagelser VALUES(1,1,1,1,99),(2,2,1,1,88);
INSERT INTO varer VALUES(1,7);
INSERT INTO ordrer VALUES(1,'KO','1',CURRENT_DATE-3);
INSERT INTO ordrelinjer VALUES(1,1,1,1,1,1,0,CURRENT_DATE+20,'LOT-1');
INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,0,9,7);");
        $GLOBALS['db_modify_fejl'] = false;
    }
    function receiptSnapshot() {
        $result = [];
        foreach (['modtageliste','modtagelser','varer','ordrelinjer','batch_kob','lagerstatus','variant_varer'] as $table) {
            $result[$table] = receiptValue("SELECT COALESCE(json_agg(t ORDER BY id)::text,'[]') FROM $table t");
        }
        return $result;
    }
    postingReset();
    postingCheck(modtag(1), 'actual receipt posts successfully');
    postingCheck(receiptValue('SELECT beholdning FROM varer') === '8.000', 'global item stock increments once');
    postingCheck(receiptValue('SELECT beholdning FROM lagerstatus WHERE lager=1') === '1.000' && receiptValue('SELECT beholdning FROM lagerstatus WHERE lager=9') === '7.000', 'new purchase warehouse row created, unrelated warehouse unchanged');
    postingCheck(receiptValue("SELECT COUNT(*) FROM batch_kob WHERE vare_id=1 AND variant_id=0 AND linje_id=1 AND ordre_id=1 AND lager=1 AND antal=1 AND rest=1 AND due_date=CURRENT_DATE+20 AND batch_no='LOT-1' AND kobsdate=CURRENT_DATE") === '1', 'purchase batch retains exact warehouse quantity identity and expiry metadata');
    postingCheck(receiptValue('SELECT modtaget_af FROM modtageliste WHERE id=1') === "fixture's receiver" && receiptValue('SELECT leveres FROM ordrelinjer') === '0.000', 'receiver is escaped and outstanding delivery is consumed');
    $before = receiptSnapshot();
    postingCheck(modtag(1) && receiptSnapshot() === $before, 'completed list replay is an exact no-op');

    postingReset();
    db_select('UPDATE ordrelinjer SET lager=0;INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,0,1,2);UPDATE varer SET beholdning=9');
    postingCheck(modtag(1) && receiptValue('SELECT beholdning FROM lagerstatus WHERE lager=1') === '3.000' && receiptValue('SELECT COUNT(*) FROM lagerstatus WHERE lager=1') === '1', 'zero warehouse defaults to one and updates existing row without duplicates');

    postingReset();
    db_select("UPDATE modtagelser SET antal=10 WHERE id=1;UPDATE ordrelinjer SET antal=4,leveres=4;INSERT INTO ordrelinjer VALUES(2,1,1,4,4,3,0,NULL,NULL),(3,1,1,4,4,4,0,NULL,NULL)");
    postingCheck(modtag(1) && receiptValue("SELECT string_agg(linje_id||':'||antal||':'||lager,',' ORDER BY linje_id) FROM batch_kob") === '1:4.000:1,2:4.000:3,3:2.000:4', 'FIFO allocation uses each line outstanding quantity and its warehouse');
    postingCheck(receiptValue("SELECT string_agg(lager||':'||beholdning,',' ORDER BY lager) FROM lagerstatus") === '1:4.000,3:4.000,4:2.000,9:7.000', 'split receipt preserves exact warehouse quantities');

    postingReset();
    db_select('UPDATE ordrelinjer SET variant_id=2;INSERT INTO variant_varer VALUES(2,1,3)');
    postingCheck(modtag(1) && receiptValue('SELECT variant_beholdning FROM variant_varer') === '4.000' && receiptValue('SELECT variant_id FROM lagerstatus WHERE lager=1') === '2' && receiptValue('SELECT variant_id FROM batch_kob') === '2', 'receipt preserves purchase variant and increments its stock');

    foreach (['over-receipt','duplicate-warehouse','missing-variant'] as $failure) {
        postingReset();
        if ($failure === 'over-receipt') {
            db_select('UPDATE modtagelser SET antal=2 WHERE id=1');
        } elseif ($failure === 'duplicate-warehouse') {
            db_select('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,0,1,2),(1,0,1,3)');
        } else {
            db_select('UPDATE ordrelinjer SET variant_id=999');
        }
        $before = receiptSnapshot();
        ob_start();
        $result = modtag(1);
        ob_end_clean();
        postingCheck(!$result && receiptSnapshot() === $before, "$failure rejects with full rollback");
    }
    postingReset();
    db_select("CREATE FUNCTION discard_warehouse() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RETURN NULL; END';CREATE TRIGGER discard BEFORE INSERT ON lagerstatus FOR EACH ROW EXECUTE FUNCTION discard_warehouse()");
    $before = receiptSnapshot();
    ob_start();
    $result = modtag(1);
    ob_end_clean();
    postingCheck(!$result && receiptSnapshot() === $before, 'silently discarded warehouse insert rolls back batch item and list writes');
    db_select('DROP TRIGGER discard ON lagerstatus');

    // NUMERIC(15,3) can represent these balances exactly; a PHP float cannot
    // conserve the 0.001 receipt after subtracting two 100-billion balances.
    function largeFractionalReceipt() {
        postingReset();
        db_select("UPDATE varer SET beholdning=100000000000.000;
UPDATE modtagelser SET antal=0.001 WHERE id=1;
UPDATE ordrelinjer SET antal=100000000000.001,leveres=0.001,variant_id=2;
DELETE FROM lagerstatus;
INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,2,1,100000000000.000);
INSERT INTO variant_varer VALUES(2,1,100000000000.000);
INSERT INTO batch_kob(kobsdate,vare_id,variant_id,linje_id,ordre_id,antal,rest,lager) VALUES(CURRENT_DATE-1,1,2,1,1,100000000000.000,100000000000.000,1)");
    }
    largeFractionalReceipt();
    postingCheck(modtag(1), 'a 0.001 receipt succeeds against large exact decimal balances');
    foreach (['SELECT beholdning FROM varer', 'SELECT beholdning FROM lagerstatus', 'SELECT variant_beholdning FROM variant_varer', 'SELECT SUM(antal) FROM batch_kob'] as $query) {
        postingCheck(receiptValue($query) === '100000000000.001', 'large item warehouse variant and purchase batch totals preserve the exact thousandth');
    }
    postingCheck(receiptValue('SELECT leveres FROM ordrelinjer') === '0.000' && receiptValue('SELECT COUNT(*) FROM batch_kob WHERE antal=0.001 AND rest=0.001') === '1', 'only the exact outstanding thousandth is allocated');
    largeFractionalReceipt();
    db_select('UPDATE varer SET beholdning=-100000000000.000;UPDATE lagerstatus SET beholdning=-100000000000.000;UPDATE variant_varer SET variant_beholdning=-100000000000.000');
    postingCheck(modtag(1) && receiptValue('SELECT beholdning FROM varer') === '-99999999999.999' && receiptValue('SELECT beholdning FROM lagerstatus') === '-99999999999.999' && receiptValue('SELECT variant_beholdning FROM variant_varer') === '-99999999999.999', 'negative stock balances also retain a received thousandth exactly');

    largeFractionalReceipt();
    db_select('UPDATE varer SET beholdning=999999999999.998;UPDATE lagerstatus SET beholdning=999999999999.998;UPDATE variant_varer SET variant_beholdning=999999999999.998;UPDATE batch_kob SET antal=999999999999.998,rest=999999999999.998;UPDATE ordrelinjer SET antal=999999999999.999');
    postingCheck(modtag(1) && receiptValue('SELECT beholdning FROM varer') === '999999999999.999' && receiptValue('SELECT beholdning FROM lagerstatus') === '999999999999.999' && receiptValue('SELECT variant_beholdning FROM variant_varer') === '999999999999.999' && receiptValue('SELECT SUM(antal) FROM batch_kob') === '999999999999.999', 'full NUMERIC(15,3) positive range preserves its final thousandth');

    db_select("CREATE FUNCTION corrupt_fractional_quantity() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN IF TG_TABLE_NAME=''variant_varer'' THEN NEW.variant_beholdning=NEW.variant_beholdning+0.001; ELSIF TG_TABLE_NAME=''batch_kob'' THEN NEW.antal=NEW.antal+0.001; ELSE NEW.beholdning=NEW.beholdning+0.001; END IF; RETURN NEW; END'");
    foreach (['varer','lagerstatus','variant_varer','batch_kob'] as $table) {
        largeFractionalReceipt();
        $event = $table === 'batch_kob' ? 'INSERT' : 'UPDATE';
        db_select("CREATE TRIGGER corrupt_thousandth BEFORE $event ON $table FOR EACH ROW EXECUTE FUNCTION corrupt_fractional_quantity()");
        $before = receiptSnapshot();
        ob_start();
        $result = modtag(1);
        ob_end_clean();
        postingCheck(!$result && receiptSnapshot() === $before, "$table genuine 0.001 mismatch rolls back every receipt write at large balances");
        db_select("DROP TRIGGER corrupt_thousandth ON $table");
    }

    // Parent holds the product lock until both fresh processes are waiting.
    // Neither process shares a PHP transaction counter or database connection.
    foreach ([[false,1], [true,1], [false,2]] as [$sameList,$capacity]) {
        postingReset();
        db_select("UPDATE ordrelinjer SET antal=$capacity,leveres=$capacity");
        db_select('BEGIN;SELECT id FROM varer WHERE id=1 FOR UPDATE');
        $workers = [];
        foreach ([1,2] as $number) {
            $name = $schema . '_' . $number;
            $process = proc_open([PHP_BINARY,__FILE__,'--worker',$schema,(string)($sameList ? 1 : $number),$name], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
            $workers[] = [$process,$pipes];
        }
        $waiting = false;
        for ($attempt=0; $attempt<100; $attempt++) {
            db_select('SELECT pg_stat_clear_snapshot()');
            if ((int)receiptValue("SELECT COUNT(*) FROM pg_stat_activity WHERE application_name IN ('{$schema}_1','{$schema}_2') AND wait_event_type='Lock'") === 2) {
                $waiting = true;
                break;
            }
            usleep(20000);
        }
        db_select('COMMIT');
        $results = [];
        foreach ($workers as [$process,$pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
            postingCheck($status === 0 && $stderr === '', 'independent receipt worker exits without warnings');
            $results[] = json_decode($stdout,true)['result'];
        }
        postingCheck($waiting, 'both receipt workers demonstrably overlapped on database locks');
        $expectedReceived = $sameList ? 1 : $capacity;
        postingCheck(array_sum($results) === ($sameList ? 2 : $capacity) && (int)receiptValue('SELECT COUNT(*) FROM batch_kob') === $expectedReceived && (float)receiptValue('SELECT beholdning FROM varer') === 7.0+$expectedReceived && (float)receiptValue('SELECT beholdning FROM lagerstatus WHERE lager=1') === (float)$expectedReceived && receiptValue('SELECT COUNT(*) FROM lagerstatus WHERE lager=1') === '1', $sameList ? 'concurrent replay produces exactly one receipt' : 'competing lists conserve available quantity without lost increments or duplicate warehouse rows');
    }
    postingReset();
    $before = receiptSnapshot();
    transaktion('begin');
    postingCheck(modtag(1) && (int)$GLOBALS['db_transaktion_depth'] === 1, 'nested receipt keeps ownership with the outer transaction');
    transaktion('rollback');
    postingCheck(receiptSnapshot() === $before, 'outer rollback reverses successful nested receipt completely');
    // Production stock/order/batch columns are unrestricted NUMERIC, unlike variants.
    foreach (['modtagelser' => ['antal','lager'], 'varer' => ['beholdning'], 'ordrelinjer' => ['antal','leveres'], 'batch_kob' => ['antal','rest'], 'lagerstatus' => ['beholdning']] as $table => $columns) {
        foreach ($columns as $column) {
            db_select("ALTER TABLE $table ALTER COLUMN $column TYPE numeric");
        }
    }
    foreach (['7.1665', '-7.1665', '10000000000000000.123456'] as $balance) {
        postingReset();
        db_select("UPDATE varer SET beholdning=$balance;UPDATE lagerstatus SET beholdning=$balance,lager=1;UPDATE modtagelser SET antal=0.0005 WHERE id=1;UPDATE ordrelinjer SET antal=1.123956,leveres=0.0005;INSERT INTO batch_kob(linje_id,antal,rest) VALUES(1,1.123456,1.123456)");
        postingCheck(modtag(1), 'existing staged fine-scale receipt posts against unrestricted decimal balances');
        postingCheck(receiptValue("SELECT beholdning=($balance+0.0005) FROM varer") === 't' && receiptValue("SELECT beholdning=($balance+0.0005) FROM lagerstatus") === 't' && receiptValue('SELECT SUM(antal)=1.123956 AND SUM(rest)=1.123956 FROM batch_kob') === 't' && receiptValue('SELECT leveres=0 FROM ordrelinjer') === 't', 'item warehouse historical batch and outstanding quantities conserve all digits exactly');
        $before = receiptSnapshot();
        postingCheck(modtag(1) && receiptSnapshot() === $before, 'fine-scale completed receipt replay makes no changes');
    }
    postingReset();
    db_select("UPDATE modtagelser SET antal=0.001 WHERE id=1;UPDATE ordrelinjer SET antal=0.000123,leveres=0.000123;INSERT INTO ordrelinjer VALUES(2,1,1,0.000877,0.000877,3,0,NULL,NULL)");
    postingCheck(modtag(1) && receiptValue('SELECT beholdning=7.001 FROM varer') === 't' && receiptValue('SELECT COUNT(*) FROM batch_kob WHERE (linje_id=1 AND antal=0.000123 AND rest=0.000123) OR (linje_id=2 AND antal=0.000877 AND rest=0.000877)') === '2' && receiptValue('SELECT COUNT(*) FROM ordrelinjer WHERE leveres=0') === '2', 'FIFO allocation preserves sub-thousandth outstanding amounts across separate warehouses');

    postingReset();
    db_select('UPDATE modtagelser SET antal=0.0005 WHERE id=1;UPDATE ordrelinjer SET variant_id=2;INSERT INTO variant_varer VALUES(2,1,3)');
    $before = receiptSnapshot();
    ob_start();
    $result = modtag(1);
    $diagnostic = ob_get_clean();
    postingCheck(!$result && receiptSnapshot() === $before && strpos($diagnostic, 'Variant 2:') !== false, 'fixed-scale variant cannot silently round a fine-scale receipt and rolls back with an identified diagnostic');

    db_select("CREATE FUNCTION corrupt_millionth_quantity() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN IF TG_TABLE_NAME=''batch_kob'' THEN NEW.antal=NEW.antal+0.000001; ELSE NEW.beholdning=NEW.beholdning+0.000001; END IF; RETURN NEW; END'");
    foreach (['varer','lagerstatus','batch_kob'] as $table) {
        postingReset();
        db_select('UPDATE lagerstatus SET lager=1;UPDATE varer SET beholdning=7.1665;UPDATE modtagelser SET antal=0.0005 WHERE id=1');
        $event = $table === 'batch_kob' ? 'INSERT' : 'UPDATE';
        db_select("CREATE TRIGGER corrupt_millionth BEFORE $event ON $table FOR EACH ROW EXECUTE FUNCTION corrupt_millionth_quantity()");
        $before = receiptSnapshot();
        ob_start();
        $result = modtag(1);
        ob_end_clean();
        postingCheck(!$result && receiptSnapshot() === $before, "$table one-millionth mismatch rejects with full rollback rather than a tolerance");
        db_select("DROP TRIGGER corrupt_millionth ON $table");
    }
    foreach ([0,1] as $purchaseWarehouse) {
        postingReset();
        db_select("UPDATE ordrelinjer SET lager=$purchaseWarehouse;INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,0,0,2),(1,2,0,5);UPDATE varer SET beholdning=14");
        $legacyId = receiptValue('SELECT id FROM lagerstatus WHERE lager=0 AND variant_id=0');
        postingCheck(modtag(1) && receiptValue("SELECT beholdning=3 AND lager=0 FROM lagerstatus WHERE id=$legacyId") === 't' && receiptValue('SELECT COUNT(*) FROM lagerstatus WHERE lager<=1 AND variant_id=0') === '1' && receiptValue('SELECT lager FROM batch_kob') === '1' && receiptValue('SELECT beholdning=5 FROM lagerstatus WHERE variant_id=2') === 't' && receiptValue('SELECT beholdning=7 FROM lagerstatus WHERE lager=9') === 't', 'legacy default warehouse is updated in place without duplicating or touching another variant or warehouse');
    }
    // Literal warehouse1 is authoritative when legacy0 and normalized1 coexist.
    foreach ([0,1] as $purchaseWarehouse) {
        postingReset();
        db_select("UPDATE ordrelinjer SET lager=$purchaseWarehouse;INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,0,0,2.1665),(1,0,1,3.123456);UPDATE varer SET beholdning=12.289956");
        $legacyBefore = receiptValue('SELECT row_to_json(s)::text FROM lagerstatus s WHERE lager=0');
        postingCheck(modtag(1) && receiptValue('SELECT beholdning=4.123456 FROM lagerstatus WHERE lager=1') === 't' && receiptValue('SELECT row_to_json(s)::text FROM lagerstatus s WHERE lager=0') === $legacyBefore && receiptValue('SELECT beholdning=13.289956 FROM varer') === 't' && receiptValue('SELECT COUNT(*) FROM lagerstatus WHERE lager IN (0,1)') === '2' && receiptValue('SELECT lager=1 AND antal=1 AND rest=1 FROM batch_kob') === 't', 'simultaneous legacy0 and literal1 route one exact increment to literal1 only');
        $before = receiptSnapshot();
        postingCheck(modtag(1) && receiptSnapshot() === $before, 'receipt replay with both default warehouse representations is a no-op');
    }
    postingReset();
    db_select('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,0,0,2),(1,0,0,3),(1,0,1,4)');
    $legacyBefore = receiptValue('SELECT json_agg(s ORDER BY id)::text FROM lagerstatus s WHERE lager=0');
    postingCheck(modtag(1) && receiptValue('SELECT beholdning=5 FROM lagerstatus WHERE lager=1') === 't' && receiptValue('SELECT json_agg(s ORDER BY id)::text FROM lagerstatus s WHERE lager=0') === $legacyBefore, 'unselected legacy duplicate rows stay unchanged when literal1 is unambiguous');
    foreach ([0,1] as $duplicateWarehouse) {
        postingReset();
        db_select("INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,0,$duplicateWarehouse,2),(1,0,$duplicateWarehouse,3)");
        if ($duplicateWarehouse === 1) {
            db_select('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,0,0,4)');
        }
        $before = receiptSnapshot();
        ob_start();
        $result = modtag(1);
        ob_end_clean();
        postingCheck(!$result && receiptSnapshot() === $before, 'duplicates of the selected literal warehouse reject fully without falling back or updating all');
    }
} finally {
    if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) {
        db_select('ROLLBACK');
    }
    db_select("DROP SCHEMA $schema CASCADE");
}
