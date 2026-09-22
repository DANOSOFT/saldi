<?php
// 20260920 CDX/LH Exercise actual shop-order ingestion with PostgreSQL rollback and overlapping workers.
if (PHP_SAPI !== 'cli') exit('CLI only');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$dsn = getenv('SALDI_CHAR_PG_DSN') ?: getenv('SALDI_TEST_DSN');
if (!$dsn) throw new RuntimeException('Set SALDI_CHAR_PG_DSN to a disposable PostgreSQL database');
$connection = pg_connect($dsn);
$db = 'api_order_test'; $db_type = 'pgsql'; $brugernavn = 'order-regression'; $regnaar = 1;
$db_modify_fejl = false; $db_transaktion_depth = 0;
$testRoot = sys_get_temp_dir() . '/saldi-order-' . getmypid();
mkdir($testRoot . '/api', 0777, true); mkdir($testRoot . '/temp/' . $db, 0777, true);
chdir($testRoot . '/api');
function get_relative() { return $GLOBALS['testRoot'] . '/'; }
function db_log_append($path, $data, $mode = 'a') { }
function db_select($sql, $location = '') {
    $result = pg_query($GLOBALS['connection'], $sql);
    if (!$result) throw new RuntimeException(pg_last_error($GLOBALS['connection']));
    return $result;
}
function db_modify($sql, $location = '') {
    pg_send_query($GLOBALS['connection'], $sql);
    $result = pg_get_result($GLOBALS['connection']);
    while (pg_get_result($GLOBALS['connection']) !== false) { }
    if (pg_result_status($result) === PGSQL_FATAL_ERROR) {
        $GLOBALS['db_modify_fejl'] = true;
        return false;
    }
    return $result;
}
function db_fetch_array($result) { return pg_fetch_assoc($result); }
function chk4utf8($text) { return $text; }
require_once(__DIR__ . '/../includes/db_query.php');
ob_start(); require_once(__DIR__ . '/../includes/std_func.php'); $includeWhitespace = ob_get_clean();
if (trim($includeWhitespace) !== '') throw new RuntimeException('Unexpected include output');
require_once(__DIR__ . '/../includes/shopOrderInput.php');
$source = file_get_contents(__DIR__ . '/../api/rest_api.php');
$start = strpos($source, 'function insert_shop_order(');
$end = strpos($source, '\nfunction insert_shop_orderline(', $start);
if ($end === false) $end = strpos($source, "\nfunction insert_shop_orderline(", $start);
eval(substr($source, $start, $end - $start));
function createTestOrder(array $changes = []) {
    $values = ['brugernavn'=>'order-regression','shopOrderId'=>'70001','shop_fakturanr'=>'70001','shop_addr_id'=>'80001',
        'firmanavn'=>'Order test','addr1'=>'Test address','postnr'=>'1234','bynavn'=>'Test city','land'=>'Danmark','tlf'=>'99001234',
        'email'=>'order@test.invalid','betalingsbet'=>'Netto','betalingsdage'=>'8','ordredate'=>'2026-09-04',
        'momssats'=>'25','valuta'=>'DKK','valutakurs'=>'100','gruppe'=>'1','afd'=>'0','projekt'=>'0','nettosum'=>'100','momssum'=>'25','lager'=>'1','art'=>'DO'];
    $values = array_replace($values, $changes);
    $arguments = [];
    foreach ((new ReflectionFunction('insert_shop_order'))->getParameters() as $parameter) {
        $arguments[] = $values[$parameter->getName()] ?? '';
    }
    return insert_shop_order(...$arguments);
}
function checkOrder($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function orderScalar($sql) { return pg_fetch_row(db_select($sql))[0]; }
function orderState() {
    $state = [];
    foreach (['adresser','shop_adresser','ordrer','shop_ordrer'] as $table) {
        $state[$table] = pg_fetch_all(db_select("SELECT * FROM $table ORDER BY id"));
    }
    return $state;
}
$worker = ($argv[1] ?? '') === '--worker';
$schema = $worker ? $argv[2] : 'saldi_order_test_' . getmypid() . '_' . bin2hex(random_bytes(4));
if (!preg_match('/^saldi_order_test_[0-9]+_[a-f0-9]+$/D', $schema)) throw new RuntimeException('Invalid test schema');
if (!$worker) db_modify("CREATE SCHEMA $schema");
db_modify("SET search_path TO $schema");
try {
    if ($worker) {
        echo json_encode(['result'=>createTestOrder(['shopOrderId'=>'70004','shop_fakturanr'=>'70004','shop_addr_id'=>'80004','tlf'=>'99001237'])]);
    } else {
        $addressColumns = 'kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,cvrnr,ean,email,tlf,art,betalingsbet,kontakt,lev_firmanavn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_land,lev_kontakt,lev_tlf,lev_email,lukket';
        $defs = implode(',', array_map(fn($column) => "$column text", explode(',', $addressColumns)));
        db_modify("CREATE TABLE adresser(id serial PRIMARY KEY,gruppe int,betalingsdage int,$defs)");
        $orderColumns = 'kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,email,udskriv_til,art,projekt,betalingsbet,betalings_id,valuta,ref,hvem,felt_1,felt_2,felt_3,felt_4,felt_5,kundeordnr,cvrnr,ean,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,tidspkt,phone,shop_status,notes,sprog';
        $defs = implode(',', array_map(fn($column) => "$column text", explode(',', $orderColumns)));
        db_modify("CREATE TABLE ordrer(id serial PRIMARY KEY,ordrenr int,konto_id int,momssats numeric,betalingsdage int,status int,ordredate date,valutakurs numeric,afd int,sum numeric,moms numeric,shop_id int,$defs)");
        db_modify('CREATE TABLE shop_ordrer(id serial PRIMARY KEY,saldi_id int REFERENCES ordrer(id),shop_id int)');
        db_modify('CREATE TABLE shop_adresser(id serial PRIMARY KEY,saldi_id int REFERENCES adresser(id),shop_id int,afd int)');
        db_modify('CREATE TABLE grupper(id serial PRIMARY KEY,art text,kodenr int,box1 text,box2 text,fiscal_year int)');
        db_modify("INSERT INTO grupper(art,kodenr,box1,box2,fiscal_year) VALUES('DG',1,'S1','',2026),('SM',1,'','25',2026),('VK',1,'EUR','745',2026)");
        $before = orderState();
        foreach ([['shopOrderId'=>'12.5'],['shopOrderId'=>'1e5'],['nettosum'=>'100bad'],['momssum'=>'1,2.3'],['valuta'=>'ZZZ']] as $invalid) {
            $result = createTestOrder($invalid);
            checkOrder(!is_numeric($result) && orderState() === $before, 'Invalid identifier/amount/currency fails before any record changes');
        }
        $first = createTestOrder(['nettosum'=>'100,50','momssum'=>'25,125']);
        checkOrder(ctype_digit((string)$first), 'Actual order ingestion creates a complete order');
        checkOrder(orderScalar("SELECT sum||'|'||moms FROM ordrer WHERE id=$first") === '100.5|25.125', 'Decimal comma amounts preserve all supplied decimal places');
        $before = orderState();
        checkOrder(strpos(createTestOrder(), 'exists in saldi') !== false && orderState() === $before, 'Duplicate replay adds no customer order or mapping');
        db_modify('ALTER TABLE shop_ordrer ADD CONSTRAINT reject_order CHECK(shop_id<>70002)');
        $failed = createTestOrder(['shopOrderId'=>'70002','shop_addr_id'=>'80002','tlf'=>'99001235']);
        checkOrder(!is_numeric($failed) && orderState() === $before, 'Mapping failure rolls back new customer customer-map and order');
        checkOrder($db_transaktion_depth === 0 && pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE, 'Failed ingestion unwinds transaction ownership');
        $new = createTestOrder(['shopOrderId'=>'70003','shop_addr_id'=>'80003','tlf'=>'99001236']);
        checkOrder(ctype_digit((string)$new), 'Next independent ingestion resets prior failure state');
        $before = orderState();
        transaktion('begin');
        $nested = createTestOrder(['shopOrderId'=>'70005','shop_addr_id'=>'80005','tlf'=>'99001238']);
        checkOrder(ctype_digit((string)$nested) && $db_transaktion_depth === 1 && pg_transaction_status($connection) === PGSQL_TRANSACTION_INTRANS, 'Successful nested ingestion leaves caller transaction open');
        transaktion('rollback');
        checkOrder(orderState() === $before, 'Caller rollback removes all nested ingestion writes');
        // Both workers start while this connection holds the allocation lock.
        transaktion('begin');
        db_modify('LOCK TABLE ordrer IN SHARE ROW EXCLUSIVE MODE');
        $workers = [];
        for ($i=0; $i<2; $i++) {
            $pipes = [];
            $process = proc_open([PHP_BINARY,__FILE__,'--worker',$schema],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if (!is_resource($process)) throw new RuntimeException('Worker failed to start');
            $workers[] = [$process,$pipes];
        }
        $waiting = 0; $deadline = microtime(true)+8;
        do {
            $waiting = (int)orderScalar("SELECT count(*) FROM pg_locks WHERE relation='ordrer'::regclass AND NOT granted");
            if ($waiting === 2) break;
            usleep(10000);
        } while (microtime(true)<$deadline);
        transaktion('commit');
        $results = [];
        foreach ($workers as [$process,$pipes]) {
            $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            checkOrder(proc_close($process) === 0 && $err === '', 'Concurrent worker completes without PHP errors');
            $results[] = json_decode($out,true,512,JSON_THROW_ON_ERROR)['result'];
        }
        checkOrder($waiting === 2, 'Two independent connections demonstrably overlap at allocation lock');
        checkOrder(count(array_filter($results, fn($value)=>ctype_digit((string)$value))) === 1, 'Exactly one overlapping duplicate submission succeeds');
        checkOrder(orderScalar('SELECT count(*) FROM ordrer WHERE shop_id=70004') === '1' && orderScalar('SELECT count(*) FROM shop_ordrer WHERE shop_id=70004') === '1', 'Overlapping submission leaves exactly one order and mapping');
        checkOrder(orderScalar('SELECT count(*) FROM shop_adresser WHERE shop_id=80004') === '1' && orderScalar("SELECT count(*) FROM adresser WHERE tlf='99001237'") === '1', 'Overlapping submission leaves exactly one customer and customer mapping');
    }
} finally {
    if (!$worker) {
        if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) transaktion('rollback');
        db_modify("DROP SCHEMA $schema CASCADE");
    }
    $log = $testRoot . '/temp/' . $db . '/rest_api.log';
    if (file_exists($log)) unlink($log);
    rmdir($testRoot . '/temp/' . $db); rmdir($testRoot . '/temp');
    chdir(sys_get_temp_dir()); rmdir($testRoot . '/api'); rmdir($testRoot);
}
