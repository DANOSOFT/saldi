<?php
// 20260920 CDX/LH Verify API debtor allocation and deletion against disposable PostgreSQL tables.
if (PHP_SAPI !== 'cli') exit('CLI only');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
$dsn = getenv('SALDI_CHAR_PG_DSN') ?: getenv('SALDI_TEST_DSN');
if (!$dsn) throw new RuntimeException('Set SALDI_CHAR_PG_DSN to a disposable PostgreSQL database');
$connection = pg_connect($dsn);
if (!$connection) throw new RuntimeException('PostgreSQL connection failed');
$db = 'api-record-regression'; $db_type = 'pgsql'; $brugernavn = 'api-record-regression';
$db_modify_fejl = false; $db_transaktion_depth = 0;
$testRoot = sys_get_temp_dir() . '/saldi-api-record-' . getmypid();
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
require_once(__DIR__ . '/../includes/std_func.php');
require_once(__DIR__ . '/../api/restApiIncludes/getNextAccountNo.php');
require_once(__DIR__ . '/../api/restApiIncludes/createDebitor.php');
require_once(__DIR__ . '/../api/restApiIncludes/deleteOrder.php');
function scalarRecord($sql) { return pg_fetch_row(db_select($sql))[0]; }
function checkRecord($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function recordSnapshot() {
    $state = [];
    foreach (['adresser','kontakt_emails','ordrer','ordrelinjer','shop_ordrer','batch_salg'] as $table) {
        $state[$table] = pg_fetch_all(db_select("SELECT * FROM $table ORDER BY id"));
    }
    return $state;
}
$addressColumns = 'kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,cvrnr,ean,email,tlf,art,betalingsbet,kontakt,lev_firmanavn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_land,lev_kontakt,lev_tlf,lev_email,lukket';
$defs = implode(',', array_map(function ($column) { return "$column text"; }, explode(',', $addressColumns)));
db_modify("CREATE TEMP TABLE adresser(id serial PRIMARY KEY,gruppe int,betalingsdage int,$defs)");
db_modify('CREATE TEMP TABLE kontakt_emails(id serial PRIMARY KEY,konto_id int,email text,email_type text)');
db_modify('CREATE TEMP TABLE ordrer(id int PRIMARY KEY,status int)');
db_modify('CREATE TEMP TABLE ordrelinjer(id int PRIMARY KEY,ordre_id int)');
db_modify('CREATE TEMP TABLE shop_ordrer(id serial PRIMARY KEY,saldi_id int,shop_id int)');
db_modify('CREATE TEMP TABLE batch_salg(id int PRIMARY KEY,ordre_id int)');
$_GET = [];
checkRecord(getNextAccountNo('D') === 1, 'Empty debtor range starts at positive account1');
db_modify("INSERT INTO adresser(kontonr,art) VALUES ('25','D'),('90000000','D'),('99','K'),('X40','D')");
checkRecord(getNextAccountNo('D') === 26 && getNextAccountNo('K') === 100, 'Next numeric account is scoped by type and bounded below phone-number accounts');
$_GET = ['minNo'=>'1000','maxNo'=>'1002'];
checkRecord(getNextAccountNo('D') === 1000, 'Explicit inclusive minimum is respected');
$_GET += ['firmanavn'=>"Customer's company",'gruppe'=>'1','cvr'=>'12345678','ean'=>'05790000000001','email'=>'first@test.invalid'];
$first = CreateDebitor();
checkRecord(preg_match('/^[0-9]+,1000$/D', $first) === 1, 'First automatic debtor receives minimum account');
checkRecord(scalarRecord("SELECT cvrnr||'|'||ean FROM adresser WHERE kontonr='1000'") === '12345678|05790000000001', 'CVR and EAN retain exact supplied strings');
checkRecord(scalarRecord("SELECT firmanavn FROM adresser WHERE kontonr='1000'") === "Customer's company", 'Quoted customer metadata round trips');
checkRecord(preg_match('/^[0-9]+,1001$/D', CreateDebitor()) === 1, 'Repeated automatic creation advances without redeclaring helper');
checkRecord(preg_match('/^[0-9]+,1002$/D', CreateDebitor()) === 1, 'Inclusive upper account bound is usable');
$before = recordSnapshot();
checkRecord(strpos(CreateDebitor(), 'No available account number') === 0 && recordSnapshot() === $before, 'Exhausted range reports error and makes no writes');
$_GET['minNo']='100.5';
checkRecord(strpos(CreateDebitor(), 'Invalid account number range') === 0 && recordSnapshot() === $before, 'Malformed bounds cannot create a debtor');
$_GET['minNo']='1000'; $_GET['maxNo']='9223372036854775808';
checkRecord(strpos(CreateDebitor(), 'Invalid account number range') === 0 && recordSnapshot() === $before, 'Overflow bounds are rejected without integer coercion');
$_GET['maxNo']='999';
checkRecord(strpos(CreateDebitor(), 'Invalid account number range') === 0 && recordSnapshot() === $before, 'Reversed range is rejected without writes');
$_GET['maxNo']='1002'; $_GET['kontonr']='1001';
checkRecord(CreateDebitor() === 'Account 1001 allready exists' && recordSnapshot() === $before, 'Duplicate explicit account preserves existing debtor and contacts');
$_GET['kontonr']='1200'; $_GET['email']='fail@test.invalid';
db_modify("ALTER TABLE kontakt_emails ADD CONSTRAINT reject_email CHECK (email <> 'fail@test.invalid')");
checkRecord(CreateDebitor() === 'Failed to create debtor contact email' && recordSnapshot() === $before, 'Failed contact write rolls back successful debtor insert');
checkRecord($db_transaktion_depth === 0 && pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE, 'Failed create fully closes transaction');
$_GET['email']='valid@test.invalid';
transaktion('begin');
checkRecord(preg_match('/^[0-9]+,1200$/D', CreateDebitor()) === 1, 'Nested successful creation returns its allocated account');
checkRecord(scalarRecord("SELECT count(*) FROM pg_locks WHERE pid=pg_backend_pid() AND relation='adresser'::regclass AND mode='ShareRowExclusiveLock' AND granted") === '1', 'Allocation holds table lock until outer transaction completes');
transaktion('rollback');
checkRecord(recordSnapshot() === $before, 'Caller rollback undoes nested debtor and email creation');
checkRecord(preg_match('/^[0-9]+,1200$/D', CreateDebitor()) === 1, 'Later independent creation resets failure state');
db_modify("INSERT INTO ordrer VALUES (10,0),(11,4),(12,0),(13,0)");
db_modify("INSERT INTO ordrelinjer VALUES (10,10),(11,11),(12,12),(13,13)");
db_modify("INSERT INTO shop_ordrer(saldi_id,shop_id) VALUES (10,110),(10,110),(11,111),(12,112),(13,113)");
db_modify('INSERT INTO batch_salg VALUES(12,12)');
$before = recordSnapshot();
checkRecord(strpos(deleteOrder(11), 'invoiced') !== false && recordSnapshot() === $before, 'Posted order is preserved');
checkRecord(strpos(deleteOrder(12), 'delivered') !== false && recordSnapshot() === $before, 'Delivered order is preserved');
checkRecord(deleteOrder('13 OR 1=1') === 'Invalid orderID' && recordSnapshot() === $before, 'Invalid deletion identifier changes no rows');
db_modify('CREATE TEMP TABLE deletion_blocker(order_id int REFERENCES ordrer(id))');
db_modify('INSERT INTO deletion_blocker VALUES(13)');
checkRecord(strpos(deleteOrder(13), 'Failed to delete') === 0 && recordSnapshot() === $before, 'Header failure restores previously deleted lines and shop mappings');
checkRecord($db_transaktion_depth === 0 && pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE, 'Failed deletion fully closes transaction');
checkRecord(deleteOrder(10) === 0, 'Editable undelivered order can be deleted');
checkRecord(scalarRecord('SELECT count(*) FROM ordrer WHERE id=10') === '0' && scalarRecord('SELECT count(*) FROM ordrelinjer WHERE ordre_id=10') === '0' && scalarRecord('SELECT count(*) FROM shop_ordrer WHERE saldi_id=10') === '0', 'Deletion removes header lines and all duplicate mappings');
$after = recordSnapshot();
checkRecord(deleteOrder(10) === 'Order ID 10 not found' && recordSnapshot() === $after, 'Repeated deletion is harmless and explicit');
checkRecord(count($after['ordrer']) === 3 && count($after['shop_ordrer']) === 3, 'Other orders and mappings remain intact');
unlink($testRoot . '/temp/' . $db . '/rest_api.log');
rmdir($testRoot . '/temp/' . $db); rmdir($testRoot . '/temp');
chdir(sys_get_temp_dir()); rmdir($testRoot . '/api'); rmdir($testRoot);
