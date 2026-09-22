<?php
// 20260920 CDX/LH Test draft preservation and settlement dates using disposable connection-local tables.
// 20260921 CDX/LH Preserve full PostgreSQL DSN authentication in the PDO fixture.
// Defaults to SQLite memory; SALDI_CHAR_DSN/PGUSER/PGPASS may point to isolated PostgreSQL.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/../includes/genberegn.php';
require_once __DIR__ . '/../finans/kassekladde_includes/journalPostingGuard.php';
$testDsn = getenv('SALDI_CHAR_DSN') ?: 'sqlite::memory:';
$postgresDsn = getenv('SALDI_CHAR_PG_DSN') ?: getenv('SALDI_TEST_DSN');
if ($postgresDsn) {
    require_once __DIR__ . '/characterization/support/RegressionPostgres.php';
    $db = regressionPostgresPdo($postgresDsn);
} else {
    $db = new PDO($testDsn, getenv('SALDI_CHAR_PGUSER') ?: null, getenv('SALDI_CHAR_PGPASS') ?: null);
}
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function db_select($sql, $trace = '') { return $GLOBALS['db']->query($sql); }
function db_fetch_array($rows) { return $rows->fetch(PDO::FETCH_ASSOC); }
function db_modify($sql, $trace = '') { $GLOBALS['db']->exec($sql); return "0\tquery accepted"; }
function db_escape_string($value) { return substr($GLOBALS['db']->quote((string)$value), 1, -1); }
function verifySettlement($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    $GLOBALS['checks']++;
}
$checks = 0;
$db->exec("CREATE TEMP TABLE kassekladde (id INTEGER, kladde_id INTEGER, bilag INTEGER, debet INTEGER, kredit INTEGER,
    amount NUMERIC, beskrivelse TEXT, faktura TEXT);
    INSERT INTO kassekladde VALUES (1,99,9260,0,0,0,'',''), (2,99,9261,1000,5800,10,'Complete','');");
verifySettlement(journalPostingIncompleteError(99) === null, 'Empty placeholder blocks valid posting');
$db->exec("INSERT INTO kassekladde VALUES (3,99,9266,0,0,0,'Saved unfinished draft','')");
$before = $db->query('SELECT * FROM kassekladde ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$error = journalPostingIncompleteError(99);
verifySettlement(strpos($error ?? '', '9266') !== false, 'Missing actionable unfinished voucher number');
verifySettlement($before === $db->query('SELECT * FROM kassekladde ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'Guard changed saved rows');
verifySettlement(journalPostingIncompleteError(100) === null, 'Guard crossed journal boundary');
$db->exec('UPDATE kassekladde SET debet=1000,kredit=5800,amount=10 WHERE id=3');
verifySettlement(journalPostingIncompleteError(99) === null, 'Completing draft does not allow posting');
$db->exec("UPDATE kassekladde SET debet=0,kredit=0,amount=100,beskrivelse='' WHERE id=3");
verifySettlement(journalPostingIncompleteError(99) !== null, 'Amount-only unfinished row is lost');
$db->exec("UPDATE kassekladde SET amount=0,faktura='INV-DRAFT' WHERE id=3");
verifySettlement(journalPostingIncompleteError(99) !== null, 'Invoice-only unfinished row is lost');
$db->exec('DELETE FROM kassekladde WHERE id=3');
verifySettlement(journalPostingIncompleteError(99) === null, 'Explicitly removed draft does not allow posting');
$serial = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
$db->exec("CREATE TEMP TABLE openpost (id $serial, konto_id INTEGER, konto_nr TEXT, faktnr TEXT, amount NUMERIC,
    transdate DATE, udlignet TEXT DEFAULT '0', udlign_id INTEGER, udlign_date DATE, valuta TEXT,
    refnr TEXT, beskrivelse TEXT, kladde_id INTEGER, bilag_id INTEGER, valutakurs NUMERIC, projekt TEXT);
    CREATE TEMP TABLE adresser (id INTEGER PRIMARY KEY, kontonr TEXT, art TEXT);
    INSERT INTO adresser VALUES (29,'1009','D');");
function addOpenItem($account, $invoice, $amount, $date, $currency = 'DKK', $closed = '0') {
    $GLOBALS['db']->prepare("INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,transdate,valuta,udlignet,projekt)
        VALUES (?, '1009', ?, ?, ?, ?, ?, '0')")->execute([$account,$invoice,$amount,$date,$currency,$closed]);
}
function invoiceRows($invoice) {
    $query = $GLOBALS['db']->prepare('SELECT amount,udlignet,udlign_id,udlign_date FROM openpost WHERE faktnr=? ORDER BY id');
    $query->execute([$invoice]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}
$baseCurrency = 'DKK';
foreach ([
    [29,'EXACT',1428,'2025-10-27'], [29,'EXACT',-1428,'2025-10-28'],
    [29,'PARTIAL',1020,'2025-08-22'], [29,'PARTIAL',-520,'2025-10-28'],
    [29,'EARLYPAY',100,'2025-11-02'], [29,'EARLYPAY',-100,'2025-10-28'],
    [29,'ACCOUNT',100,'2025-10-27'], [30,'ACCOUNT',-100,'2025-10-28'],
    [29,'CURRENCY',100,'2025-10-27','EUR'], [29,'CURRENCY',-100,'2025-10-28','DKK'],
    [29,'DUPLICATE',100,'2025-10-27'], [29,'DUPLICATE',100,'2025-10-27'],
    [29,'DUPLICATE',-100,'2025-10-28'], [29,'DUPLICATE',-100,'2025-10-29'],
    [29,'MISSINGDATE',100,null], [29,'MISSINGDATE',-100,'2025-10-28'],
    [29,'ZERO',0,'2025-10-27'], [29,'ZERO',0,'2025-10-28'],
    [29,'BLANKCURRENCY',100,'2025-10-27',''], [29,'BLANKCURRENCY',-100,'2025-10-28','DKK'],
] as $row) addOpenItem(...$row);
equalizeMatchingRecords();
foreach (['EXACT'=>'2025-10-28','EARLYPAY'=>'2025-11-02','BLANKCURRENCY'=>'2025-10-28'] as $invoice=>$date) {
    $rows = invoiceRows($invoice);
    verifySettlement(count($rows) === 2 && $rows[0]['udlignet'] === '1' && $rows[1]['udlignet'] === '1', "$invoice did not settle");
    verifySettlement($rows[0]['udlign_date'] === $date && $rows[1]['udlign_date'] === $date, "$invoice has wrong settlement date");
    verifySettlement($rows[0]['udlign_id'] === $rows[1]['udlign_id'], "$invoice has mismatched group IDs");
}
foreach (['PARTIAL','ACCOUNT','CURRENCY','MISSINGDATE','ZERO'] as $invoice) {
    foreach (invoiceRows($invoice) as $row) {
        verifySettlement($row['udlignet'] === '0' && $row['udlign_date'] === null && $row['udlign_id'] === null, "$invoice incorrectly closed");
    }
}
$duplicates = invoiceRows('DUPLICATE');
verifySettlement(count(array_filter($duplicates, function ($r) { return $r['udlignet'] === '1'; })) === 4, 'Duplicate invoice numbers lost exact pairs');
verifySettlement(count(array_unique(array_column($duplicates,'udlign_id'))) === 2, 'Duplicate invoice pairs share an unrelated settlement group');
$before = $db->query('SELECT * FROM openpost ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
equalizeMatchingRecords();
verifySettlement($before === $db->query('SELECT * FROM openpost ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'Repeated matching changed settled or partial items');

// Exercise the actual immediate journal-payment matching function, not a copy.
$source = file_get_contents(__DIR__ . '/../finans/bogfor.php');
$start = strpos($source, "\nfunction openpost(");
$end = strpos($source, "\n################################################################", $start);
eval(substr($source, $start, $end - $start));
$connection = null;
$regnaar = 4;
$kladde_id = 99;
foreach ([['DIRECT',100,'2025-10-27','2025-10-28','2025-10-28'], ['DIRECTEARLY',100,'2025-11-02','2025-10-28','2025-11-02']] as [$invoice,$amount,$invoiceDate,$paymentDate,$expectedDate]) {
    addOpenItem(29,$invoice,$amount,$invoiceDate);
    openpost('D','1009',9260,$invoice,-$amount,'Test payment',$paymentDate,2,0,100,null,'','0');
    $rows = invoiceRows($invoice);
    verifySettlement(count($rows) === 2 && $rows[0]['udlign_date'] === $expectedDate && $rows[1]['udlign_date'] === $expectedDate, 'Immediate journal match used wrong date');
}
addOpenItem(29,'DIRECTPARTIAL',1020,'2025-08-22');
openpost('D','1009',9261,'DIRECTPARTIAL',-520,'Test partial','2025-10-28',3,0,100,null,'','0');
$rows = invoiceRows('DIRECTPARTIAL');
verifySettlement(count($rows) === 2 && $rows[0]['udlignet'] === '0' && $rows[1]['udlignet'] === '0', 'Immediate partial payment closed unmatched balance');
verifySettlement(array_sum(array_column($rows,'amount')) == 500, 'Partial account balance is not 500');
echo "PASS: $checks draft-preservation and settlement checks.\n";

$db->exec("CREATE TEMP TABLE transaktioner (id INTEGER,kladde_id INTEGER,debet NUMERIC,kredit NUMERIC);
    CREATE TEMP TABLE simulering (id INTEGER,kladde_id INTEGER,debet NUMERIC,kredit NUMERIC);
    CREATE TEMP TABLE kladdeliste (id INTEGER,bogfort TEXT);
    INSERT INTO kladdeliste VALUES (99,'-')");
function transaktion($action) {
    if ($action === 'rollback') $GLOBALS['db']->rollBack();
    else throw new RuntimeException('Unexpected transaction action in guard');
}
$start = strpos($source, "\tif (\$postingBalanceError = journalPostedBalanceError(");
$end = strpos($source, "\tif (abs(\$tjeksum)", $start);
if ($start === false || $end === false) throw new RuntimeException('Posting balance guard not found');
$guard = substr($source,$start,$end-$start);
// Replace process termination only so this isolated test can inspect rollback.
$guard = str_replace('exit;', "throw new RuntimeException('posting-guard-stopped');", $guard);
foreach (['','on'] as $simuler) {
    $table = $simuler ? 'simulering' : 'transaktioner';
    foreach ([[[0.01,0],[0.01,0],[0,0.01]], [[100.01,0],[100.01,0],[100.01,0],[100.01,0],[100.01,0],[0,500.03]]] as $legs) {
        $db->beginTransaction();
        foreach ($legs as [$debit,$credit]) $db->exec("INSERT INTO $table VALUES (1,99,$debit,$credit)");
        $stopped = false;
        ob_start();
        try { eval($guard); } catch (RuntimeException $error) {
            if ($error->getMessage() !== 'posting-guard-stopped') throw $error;
            $stopped = true;
        }
        $output = ob_get_clean();
        verifySettlement($stopped && !$db->inTransaction(), 'Unbalanced generated ledger reached success path');
        verifySettlement((int)$db->query("SELECT COUNT(*) FROM $table WHERE kladde_id=99")->fetchColumn() === 0, 'Rejected ledger/simulation rows persisted');
        verifySettlement($db->query('SELECT bogfort FROM kladdeliste WHERE id=99')->fetchColumn() === '-', 'Rejected journal closed');
        verifySettlement(strpos($output,'afrunding') !== false && strpos($output,'kladden til øre') !== false, 'Rounding rejection lacks actionable guidance');
    }
    foreach ([[[100.01,0],[0,100.01]], [[1.001,0],[0,1.001]]] as $legs) {
        $db->beginTransaction();
        foreach ($legs as [$debit,$credit]) $db->exec("INSERT INTO $table VALUES (1,99,$debit,$credit)");
        verifySettlement(journalPostedBalanceError(99,$simuler) === null, 'Valid balanced ledger precision was rejected');
        $db->rollBack();
    }
}
echo "PASS: $checks total preservation, settlement and exact generated-ledger rollback checks.\n";
