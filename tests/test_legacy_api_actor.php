<?php
// 20260921 CDX/LH Actual legacy access_check and order ingestion bind the configured rights-free actor.
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
function db_connect($host, $user, $password, $database = "", $location = "") { return $GLOBALS["connection"]; }
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
function findtekst($text, $language) { return $text; }
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

$start = strpos($source, 'function access_check(){');
$end = strpos($source, '$possible_url =', $start);
eval(substr($source, $start, $end - $start));
$sqhost = $squser = $sqpass = '';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$schema = 'saldi_actor_test_' . getmypid() . '_' . bin2hex(random_bytes(4));
db_modify("CREATE SCHEMA $schema");
db_modify("SET search_path TO $schema");
function actorState() {
    $state = [];
    foreach (['regnskab','brugere','grupper','adresser','shop_adresser','ordrer','shop_ordrer'] as $table) {
        $state[$table] = pg_fetch_all(db_select("SELECT * FROM $table ORDER BY id"));
    }
    return $state;
}
function authorizeActor($label = 'caller-label') {
    $_GET = ['db'=>'api_order_test','saldiuser'=>$label,'key'=>'fixture-key'];
    return access_check();
}
try {
        $addressColumns = 'kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,cvrnr,ean,email,tlf,art,betalingsbet,kontakt,lev_firmanavn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_land,lev_kontakt,lev_tlf,lev_email,lukket';
        $defs = implode(',', array_map(fn($column) => "$column text", explode(',', $addressColumns)));
        db_modify("CREATE TABLE adresser(id serial PRIMARY KEY,gruppe int,betalingsdage int,$defs)");
        $orderColumns = 'kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,email,udskriv_til,art,projekt,betalingsbet,betalings_id,valuta,ref,hvem,felt_1,felt_2,felt_3,felt_4,felt_5,kundeordnr,cvrnr,ean,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,tidspkt,phone,shop_status,notes,sprog';
        $defs = implode(',', array_map(fn($column) => "$column text", explode(',', $orderColumns)));
        db_modify("CREATE TABLE ordrer(id serial PRIMARY KEY,ordrenr int,konto_id int,momssats numeric,betalingsdage int,status int,ordredate date,valutakurs numeric,afd int,sum numeric,moms numeric,shop_id int,$defs)");
        db_modify('CREATE TABLE shop_ordrer(id serial PRIMARY KEY,saldi_id int REFERENCES ordrer(id),shop_id int)');
        db_modify('CREATE TABLE shop_adresser(id serial PRIMARY KEY,saldi_id int REFERENCES adresser(id),shop_id int,afd int)');
        db_modify('CREATE TABLE grupper(id serial PRIMARY KEY,art text,kodenr int,box1 text,box2 text,box3 text,box4 text,box5 text,box6 text,fiscal_year int)');
        db_modify("INSERT INTO grupper(art,kodenr,box1,box2,fiscal_year) VALUES('DG',1,'S1','',2026),('SM',1,'','25',2026),('VK',1,'EUR','745',2026)");

    db_modify('CREATE TABLE regnskab(id serial PRIMARY KEY, db text, lukket text)');
    db_modify("INSERT INTO regnskab(db,lukket) VALUES('api_order_test','')");
    db_modify('CREATE TABLE brugere(id integer PRIMARY KEY,brugernavn text,rettigheder text)');
    db_modify("INSERT INTO brugere VALUES(7,'shopapi','000000'),(8,'admin','111111'),(9,'reader','000002'),(10,'blank-rights',''),(11,'null-rights',NULL),(12,'','000'),(13,'shop''api','000'),(14,'padded-rights',' 000000 '),(15,'single-zero','0'),(16,'padded-single-zero',' 0 '),(17,'double-zero','00'),(18,'padded-double-zero',' 00 ')");
    $year=date('Y');
    db_modify("INSERT INTO grupper(art,kodenr,box1,box2,box3,box4,box5) VALUES('RA',1,'1','$year','12','$year','on'),('API',1,'fixture-key','127.0.0.1','7','','')");
    $before=actorState();
    foreach (['not-a-user','admin',"caller'; DROP TABLE ordrer; --",''] as $label) {
        checkOrder(authorizeActor($label)==='OK' && $GLOBALS['brugernavn']==='shopapi', 'Valid key and IP bind the configured actor regardless of caller label');
        checkOrder(actorState()===$before, 'Authentication itself makes no database writes');
    }
    foreach (['','0','-1','1.0','1e1','7 OR 1=1','2147483648','999999999999999999999','999','8','9','10','11','12','15','16'] as $configured) {
        db_modify("UPDATE grupper SET box3='".db_escape_string($configured)."' WHERE art='API'");
        $before=actorState();
        checkOrder(authorizeActor('admin')==='Access denied (user)' && $GLOBALS['brugernavn']===null, 'Invalid or privileged configured actor is denied without retaining caller authority');
        checkOrder(actorState()===$before, 'Denied actor leaves every fixture table unchanged');
    }
    db_modify("UPDATE grupper SET box3=NULL WHERE art='API'");
    $before=actorState();
    checkOrder(authorizeActor()==='Access denied (user)' && actorState()===$before, 'Missing configured actor is denied without writes');
    db_modify("UPDATE grupper SET box3='7' WHERE art='API'");
    $before=actorState();
    $_GET=['db'=>'api_order_test','key'=>'fixture-key'];
    checkOrder(access_check()==='Missing saldiuser' && actorState()===$before, 'Missing legacy caller label retains established response and makes no writes');
    checkOrder(authorizeActor(['admin'])==='Missing saldiuser' && actorState()===$before, 'Nonscalar caller label fails safely');
    $_GET=['db'=>'api_order_test','saldiuser'=>'admin','key'=>'wrong-key'];
    checkOrder(access_check()==='Access denied (key)' && actorState()===$before, 'Wrong key remains rejected before actor attribution');
    $_SERVER['REMOTE_ADDR']='127.0.0.2';
    checkOrder(authorizeActor()==='Access denied (ip)' && actorState()===$before, 'Wrong IP remains rejected before actor attribution');
    $_SERVER['REMOTE_ADDR']='127.0.0.1';
    foreach (['not-a-user','admin'] as $index=>$label) {
        checkOrder(authorizeActor($label)==='OK', 'Configured actor authorizes a normal order');
        $id=createTestOrder(['shopOrderId'=>(string)(70001+$index),'shop_fakturanr'=>(string)(70001+$index)]);
        checkOrder(ctype_digit((string)$id), 'Actual production ingestion creates the requested order');
        checkOrder(orderScalar("SELECT hvem FROM ordrer WHERE id=$id")==='shopapi', 'Persisted order actor is configured user, never the supplied caller label');
    }
    db_modify("UPDATE grupper SET box3='14' WHERE art='API'");
    $before=actorState();
    checkOrder(authorizeActor('admin')==='OK' && $GLOBALS['brugernavn']==='padded-rights' && actorState()===$before, 'Whitespace-normalized explicit zero rights match online authorization semantics');
    foreach (['17'=>'double-zero','18'=>'padded-double-zero'] as $id=>$name) {
        db_modify("UPDATE grupper SET box3='$id' WHERE art='API'");
        $before=actorState();
        checkOrder(authorizeActor('admin')==='OK' && $GLOBALS['brugernavn']===$name && actorState()===$before, 'Explicit truthy zero-only permissions remain valid and read-only during authentication');
    }
    db_modify("UPDATE grupper SET box3='13' WHERE art='API'");
    checkOrder(authorizeActor('admin')==='OK', 'Valid configured username containing an apostrophe is supported');
    $id=createTestOrder(['shopOrderId'=>'70003','shop_fakturanr'=>'70003']);
    checkOrder(ctype_digit((string)$id) && orderScalar("SELECT hvem FROM ordrer WHERE id=$id")==="shop'api", 'Configured actor is safely escaped and preserved in actual order row');
    db_modify("UPDATE grupper SET box6='' WHERE art='API'");
    $selectorSource=file_get_contents(__DIR__.'/../systemdata/sys_div_func.php');
    $start=strpos($selectorSource,'function api_valg() {');
    $end=strpos($selectorSource,'} # endfunc api_valg',$start)+1;
    eval(substr($selectorSource,$start,$end-$start));
    $bgcolor=$bgcolor5=$buttonStyle='';$bruger_id=7;$sprog_id=1;
    $_SERVER['REQUEST_URI']='/saldi/systemdata/diverse.php';
    $_SERVER['HTTP_HOST']='fixture.invalid';$_SERVER['PHP_SELF']='/saldi/systemdata/diverse.php';
    $before=actorState();
    ob_start();api_valg();$html=ob_get_clean();
    preg_match_all("/<option value='([0-9]+)'>/",$html,$options);
    $ids=array_map('intval',$options[1]);sort($ids);
    checkOrder($ids===[7,13,14,17,18] && actorState()===$before, 'Actual API settings selector offers exactly authenticated rights-free users without writes');
    db_modify("UPDATE brugere SET rettigheder='001' WHERE id IN (7,13,14,17,18)");
    $before=actorState();
    ob_start();api_valg();$html=ob_get_clean();
    checkOrder(strpos($html,'Ingen brugere uden rettigheder')!==false && strpos($html,"name='api_bruger'")===false && actorState()===$before, 'Actual settings explain missing eligible service account rather than offer privileged choices');
} finally {
    if (pg_transaction_status($connection)!==PGSQL_TRANSACTION_IDLE) transaktion('rollback');
    db_modify("DROP SCHEMA $schema CASCADE");
    $log=$testRoot.'/temp/'.$db.'/rest_api.log';
    if (file_exists($log)) unlink($log);
    rmdir($testRoot.'/temp/'.$db);rmdir($testRoot.'/temp');
    chdir(sys_get_temp_dir());rmdir($testRoot.'/api');rmdir($testRoot);
}
