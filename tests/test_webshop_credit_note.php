<?php
// 20260920 CDX/LH Verify credit creation and nested transactions against isolated PostgreSQL temporary tables.
// Run with SALDI_TEST_DSN pointing to a disposable PostgreSQL database.
if (PHP_SAPI !== 'cli') exit('CLI only');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
$dsn = getenv('SALDI_TEST_DSN');
if (!$dsn) exit("SKIP: set SALDI_TEST_DSN to a PostgreSQL test connection.\n");
$connection = pg_connect($dsn);
if (!$connection) throw new RuntimeException('Could not connect to PostgreSQL');
$db = 'credit_regression';
$db_type = 'pgsql';
$brugernavn = 'credit-regression';
$db_modify_fejl = false;
$db_transaktion_depth = 0;
$creditTestRoot = sys_get_temp_dir() . '/saldi-credit-tests-' . getmypid();
function get_relative() { return $GLOBALS['creditTestRoot'] . '/'; }
function db_log_append($path, $data, $mode = 'a') { }
require_once(__DIR__ . '/../includes/db_query.php');
require_once(__DIR__ . '/../includes/std_func.php');
// Load only the production action; importing the endpoint itself would authenticate a request.
$source = file_get_contents(__DIR__ . '/../api/rest_api.php');
$source = substr($source, strpos($source, 'function create_credit_note('));
$function = ''; $depth = 0; $opened = false;
foreach (token_get_all('<?php ' . $source) as $token) {
    if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
    $function .= is_array($token) ? ($token[0] === T_DIR ? var_export($creditTestRoot . '/api', true) : $token[1]) : $token;
    if ($token === '{') { $depth++; $opened = true; }
    if ($token === '}') $depth--;
    if ($opened && !$depth) break;
}
mkdir($creditTestRoot . '/api', 0777, true);
mkdir($creditTestRoot . '/temp/' . $db, 0777, true);
eval($function);
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
function scalarCredit($sql) { return pg_fetch_row(db_select($sql))[0]; }
function checkCredit($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function fakturer_ordre($id, $print, $payment, $date, $sendEmail = true) {
    checkCredit(!$sendEmail, 'Defer credit-note email until the outer commit');
    transaktion('begin');
    db_modify("UPDATE ordrer SET fakturadate=ordredate WHERE id=$id");
    transaktion('commit');
    transaktion('begin');
    db_modify("INSERT INTO test_postings VALUES ($id, -250)");
    transaktion('begin');
    if ($GLOBALS['postingMode'] === 'fail') {
        transaktion('rollback');
        transaktion('rollback');
        return 'Injected posting failure';
    }
    db_modify("UPDATE ordrer SET status=4 WHERE id=$id");
    transaktion('commit');
    transaktion('commit');
    return $id;
}
function send_api_invoice_email($id) {
    checkCredit($GLOBALS['db_transaktion_depth'] === 0 && pg_transaction_status($GLOBALS['connection']) === PGSQL_TRANSACTION_IDLE, 'Email observes a committed transaction');
    $GLOBALS['emails'][] = $id;
    return 'test mail recorded';
}
$headerText = 'kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,email,projekt,betalingsbet,valuta,ref,kundeordnr,cvrnr,ean,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,tidspkt,phone,notes,sprog,omvbet';
$definitions = implode(',', array_map(function ($name) { return "$name text"; }, explode(',', $headerText)));
db_modify("CREATE TEMP TABLE ordrer (id serial PRIMARY KEY,ordrenr int,art text,kred_ord_id int,status int,ordredate date,fakturadate date,sum numeric,moms numeric,shop_id int,konto_id int,momssats numeric,betalingsdage int,valutakurs numeric,afd int,$definitions)");
db_modify("CREATE TEMP TABLE shop_ordrer (shop_id int,saldi_id int)");
db_modify("CREATE TEMP TABLE ordrelinjer (id serial PRIMARY KEY,ordre_id int,varenr text,beskrivelse text,posnr int,vare_id int,antal numeric,pris numeric,rabat numeric,momssats numeric,lager int,momsfri text,kred_linje_id int,leveres numeric,leveret numeric,procent numeric,rabatart text,variant_id int,kostpris numeric,bogf_konto int,vat_account int,enhed text,samlevare text DEFAULT '0',omvbet text)");
db_modify("CREATE TEMP TABLE test_postings (ordre_id int,amount numeric)");
db_modify("INSERT INTO ordrer (ordrenr,art,status,sum,moms,konto_id,momssats,betalingsbet,valuta,valutakurs,notes) VALUES (1,'DO',4,200,50,10,25,'Netto','DKK',100,'Customer''s quoted note')");
db_modify("INSERT INTO shop_ordrer VALUES (101,1)");
db_modify("INSERT INTO ordrelinjer (ordre_id,varenr,beskrivelse,posnr,vare_id,antal,pris,rabat,momssats,lager,momsfri,procent,rabatart,variant_id,kostpris,bogf_konto,vat_account,enhed,samlevare) VALUES (1,'P1','Quoted '' item',1,9,2,100,0,25,2,'',100,'P',12,55,1000,66100,'stk',0),(1,'CHILD','Child',2,10,2,0,0,0,2,'on',100,'P',13,0,1000,0,'stk',1),(1,'INTERNAL','Generated stock entry',-1,0,1,55,0,0,0,'',100,'P',0,0,1000,0,'',0)");
$emails = array(); $postingMode = 'success';
$id = create_credit_note(101);
checkCredit(is_int($id) && $id > 1, 'Create a posted credit note using the real schema without varegruppe');
checkCredit(scalarCredit("SELECT count(*) FROM ordrelinjer WHERE ordre_id=$id") === '2', 'Copy customer lines without generated negative bookkeeping positions');
$row = db_fetch_array(db_select("SELECT * FROM ordrelinjer WHERE ordre_id=$id AND posnr=1"));
checkCredit((float)$row['antal'] === -2.0 && (float)$row['procent'] === 100.0 && $row['rabatart'] === 'P' && $row['variant_id'] === '12' && $row['vat_account'] === '66100' && (float)$row['kostpris'] === 55.0, 'Preserve quantity reversal, discount, variant, VAT account and cost');
checkCredit(scalarCredit("SELECT samlevare FROM ordrelinjer WHERE ordre_id=$id AND posnr=2") === $row['id'], 'Remap bundle child to the new parent line');
checkCredit(scalarCredit("SELECT notes FROM ordrer WHERE id=$id") === "Customer's quoted note", 'Copy quoted customer metadata safely');
checkCredit(count($emails) === 1, 'Send exactly one post-commit mail');
$result = create_credit_note(101);
checkCredit(strpos($result, 'already exists') !== false && scalarCredit("SELECT count(*) FROM ordrer WHERE art='DK'") === '1', 'Reject duplicate credit without adding rows');
db_modify("DELETE FROM ordrelinjer WHERE ordre_id=$id"); db_modify("DELETE FROM ordrer WHERE id=$id"); db_modify('DELETE FROM test_postings');
$before = scalarCredit('SELECT count(*) FROM ordrelinjer');
$postingMode = 'fail';
$result = create_credit_note(101);
checkCredit(strpos($result, 'Injected posting failure') !== false, 'Return the posting failure');
checkCredit(scalarCredit("SELECT count(*) FROM ordrer WHERE art='DK'") === '0' && scalarCredit('SELECT count(*) FROM ordrelinjer') === $before && scalarCredit('SELECT count(*) FROM test_postings') === '0', 'Rollback header, lines and delivery/posting writes together');
checkCredit($db_transaktion_depth === 0 && count($emails) === 1, 'Unwind failed nested transaction and send no email');
db_modify("ALTER TABLE ordrelinjer ADD CONSTRAINT reject_credit CHECK (antal >= 0)");
$postingMode = 'success';
$result = create_credit_note(101);
checkCredit($result === 'Failed to copy credit note lines' && scalarCredit("SELECT count(*) FROM ordrer WHERE art='DK'") === '0', 'A failed line insert leaves no orphan header');
db_modify('ALTER TABLE ordrelinjer DROP CONSTRAINT reject_credit');
transaktion('begin'); db_modify('INSERT INTO test_postings VALUES (91,1)');
transaktion('begin'); db_modify('INSERT INTO test_postings VALUES (92,1)'); transaktion('commit');
checkCredit(pg_transaction_status($connection) === PGSQL_TRANSACTION_INTRANS, 'Inner commit leaves the PostgreSQL transaction open');
transaktion('rollback');
checkCredit(scalarCredit('SELECT count(*) FROM test_postings') === '0', 'Outer rollback undoes successful inner work');
transaktion('begin'); db_modify('INSERT INTO test_postings VALUES (93,1)');
transaktion('begin'); transaktion('rollback'); db_modify('INSERT INTO test_postings VALUES (94,1)'); transaktion('commit');
checkCredit(scalarCredit('SELECT count(*) FROM test_postings') === '0' && $db_modify_fejl, 'Inner rollback marks the outer commit rollback-only, including later writes');
transaktion('begin'); db_modify('INSERT INTO test_postings VALUES (95,1)'); transaktion('commit');
checkCredit(scalarCredit('SELECT count(*) FROM test_postings') === '1' && !$db_modify_fejl && $db_transaktion_depth === 0, 'A later independent transaction resets failure state');

db_modify("UPDATE ordrer SET omvbet='on' WHERE id=1");
db_modify("UPDATE ordrelinjer SET samlevare='on',omvbet='on' WHERE id=1");
$id = create_credit_note(101);
checkCredit(is_int($id), 'Create credit with reverse-charge and textual bundle metadata');
checkCredit(scalarCredit("SELECT samlevare FROM ordrelinjer WHERE ordre_id=$id AND posnr=1") === 'on', 'Preserve the bundle master marker instead of treating it as a numeric parent');
checkCredit(scalarCredit("SELECT omvbet FROM ordrelinjer WHERE ordre_id=$id AND posnr=1") === 'on' && scalarCredit("SELECT omvbet FROM ordrer WHERE id=$id") === 'on', 'Preserve reverse-charge flags on both header and lines');
