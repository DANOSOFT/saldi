<?php
// 20260908 CDX/LH Exercise account matching and journal assignment with isolated SQLite fixtures.
// Run: php tests/test_auto_settlement.php (pdo_sqlite; no application bootstrap).
// Optional isolated Postgres: SALDI_CHAR_DSN, SALDI_CHAR_PGUSER, SALDI_CHAR_PGPASS.
// Fixtures use connection-local temporary tables only.

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/../finans/kassekladde_includes/autoSettlement.php';

function db_select($sql, $trace) {
    if ($GLOBALS['testDb']->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $sql = str_replace(' FOR UPDATE', '', $sql);
    }
    return $GLOBALS['testDb']->query($sql);
}
function db_fetch_array($query) {
    return $query->fetch(PDO::FETCH_ASSOC);
}
function db_escape_string($value) {
    return substr($GLOBALS['testDb']->quote($value), 1, -1);
}
function db_modify($sql, $trace) {
    if ($GLOBALS['failUpdate'] && strpos($sql, 'UPDATE kassekladde') === 0) return "1\tInjected failure";
    $GLOBALS['testDb']->exec($sql);
    return "0\tquery accepted";
}
function fixture() {
    $db = new PDO(getenv('SALDI_CHAR_DSN') ?: 'sqlite::memory:', getenv('SALDI_CHAR_PGUSER') ?: null, getenv('SALDI_CHAR_PGPASS') ?: null);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TEMP TABLE kladdeliste (id INTEGER PRIMARY KEY, bogfort TEXT);
        CREATE TEMP TABLE adresser (id INTEGER PRIMARY KEY, kontonr TEXT, art TEXT);
        CREATE TEMP TABLE openpost (id INTEGER PRIMARY KEY, konto_id INTEGER, konto_nr TEXT, faktnr TEXT, amount NUMERIC, udlignet TEXT);
        CREATE TEMP TABLE kassekladde (id INTEGER PRIMARY KEY, kladde_id INTEGER, debet TEXT, kredit TEXT,
            d_type TEXT, k_type TEXT, faktura TEXT, amount NUMERIC, transdate TEXT, valuta TEXT, valutakurs NUMERIC, beskrivelse TEXT);
        INSERT INTO kladdeliste VALUES (99, '-'), (100, '-');
        INSERT INTO adresser VALUES (29, '1009', 'K'), (30, '1009', 'D'), (31, '2000', 'K');
        INSERT INTO openpost VALUES (101,29,'1009','INV-1',1991.50,'0'), (102,30,'1009','INV-1',1991.50,'0'),
            (103,31,'2000','INV-1',1991.50,'0'), (104,29,'1009','PAID',1991.50,'1'), (105,29,'1009','NULL-OPEN',150,NULL);
        INSERT INTO kassekladde VALUES (1,99,'','5800','F','F','',500,'2026-09-03','DKK',100,'alcar'),
            (2,100,'','5800','F','F','',500,'2026-09-03','DKK',100,'other journal');");
    $GLOBALS['testDb'] = $db;
    $GLOBALS['failUpdate'] = false;
    return $db;
}
function entry($id = 1) {
    return $GLOBALS['testDb']->query('SELECT * FROM kassekladde WHERE id = ' . (int)$id)->fetch(PDO::FETCH_ASSOC);
}
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function reject($callback, $message) {
    $before = $GLOBALS['testDb']->query('SELECT * FROM kassekladde ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    try {
        $callback();
    } catch (RuntimeException $error) {
        check($before === $GLOBALS['testDb']->query('SELECT * FROM kassekladde ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'Rejected save changed a journal row');
        check(strpos($error->getMessage(), $message) !== false, 'Unexpected rejection: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $message);
}

$passed = 0;
foreach ([[500,500,true], [-500,500,false], [500,-500,false], [-500,-500,true], [500,499,false], [500,null,false]] as [$amount,$target,$expected]) {
    check(autoSettlementAmountMatches($amount,$target) === $expected, 'Automatic matching ignored payment direction');
    ++$passed;
}
$exact = ['id'=>101,'amountMatch'=>true,'_score'=>40];
$partial = ['id'=>102,'amountMatch'=>false,'_score'=>0];
$tied = array_fill(0, 51, $exact);
foreach ([[$tied,null], [[$exact],101], [[$exact,$partial],101], [[$partial],null], [[],null]] as [$rows,$expected]) {
    check(autoSettlementBestCandidateId($rows) === $expected, 'Automatic selection ignored global ambiguity');
    ++$passed;
}

foreach ([['1009','K',[101,105]], ['1009','D',[102]], ['2000','K',[103]], ['404','K',[]],
    ['','',[]], ['1009','',[]], ['','K',[]], ['1009','F',[]], [[], 'K', []], ["1009' OR 1=1 --",'K',[]]] as [$number,$type,$expected]) {
    $db = fixture();
    $where = autoSettlementAccountWhere($number, $type);
    $ids = $db->query("SELECT openpost.id FROM openpost JOIN adresser ON adresser.id = openpost.konto_id
        WHERE (openpost.udlignet != '1' OR openpost.udlignet IS NULL) AND ($where) ORDER BY openpost.id")->fetchAll(PDO::FETCH_COLUMN);
    check($ids === $expected, 'Account/type filter widened to unrelated rows');
    ++$passed;
}

foreach ([['', '5800', 'F', 'F', 29, 101, '1009', '5800', 'K', 'F'],
          ['0', '5800', 'F', 'F', 29, 101, '1009', '5800', 'K', 'F'],
          [null, '5800', 'F', 'F', 29, 101, '1009', '5800', 'K', 'F'],
          ['5800', '', 'F', 'F', 30, 102, '5800', '1009', 'F', 'D'],
          ['1009', '5800', 'K', 'F', 29, 101, '1009', '5800', 'K', 'F'],
          ['5800', '1009', 'F', 'D', 30, 102, '5800', '1009', 'F', 'D'],
          ['1009', '', 'K', 'F', 29, 101, '1009', '', 'K', 'F']] as $case) {
    [$debit,$credit,$dt,$kt,$accountId,$postId,$newDebit,$newCredit,$newDt,$newKt] = $case;
    $db = fixture();
    $db->prepare('UPDATE kassekladde SET debet=?, kredit=?, d_type=?, k_type=? WHERE id=1')->execute([$debit,$credit,$dt,$kt]);
    $before = entry();
    saveAutoSettlement(99, 1, $postId, $accountId, autoSettlementSnapshot($before));
    $after = entry();
    check([$after['debet'],$after['kredit'],$after['d_type'],$after['k_type'],$after['faktura']] === [$newDebit,$newCredit,$newDt,$newKt,'INV-1'], 'Wrong assignment side or account');
    check($after['amount'] === $before['amount'], 'Partial-payment amount was changed');
    check($db->query('SELECT udlignet FROM openpost WHERE id=101')->fetchColumn() === '0', 'Invoice itself was reconciled prematurely');
    reject(fn() => saveAutoSettlement(99, 1, $postId, $accountId, autoSettlementSnapshot($before)), 'line has changed');
    ++$passed;
}
foreach ([
    [99,1,101,0,'Choose an account'], [99,1,101,31,'no longer available'], [99,1,104,29,'no longer available'],
    [99,1,999,29,'no longer available'], [99,2,101,29,'line has changed'], [100,1,101,29,'line has changed'],
    [99,999,101,29,'line has changed'], [99,[1],101,29,'Choose an account'], [99,1,'101 OR 1=1',29,'Choose an account'],
] as [$journal,$id,$post,$account,$message]) {
    fixture();
    $snapshot = autoSettlementSnapshot(entry());
    reject(fn() => saveAutoSettlement($journal,$id,$post,$account,$snapshot), $message);
    ++$passed;
}
foreach (['debet'=>'1009', 'kredit'=>'5900', 'amount'=>'499', 'faktura'=>'other', 'transdate'=>'2026-09-04', 'valuta'=>'EUR', 'beskrivelse'=>'edited'] as $field=>$value) {
    $db = fixture();
    $snapshot = autoSettlementSnapshot(entry());
    $db->prepare("UPDATE kassekladde SET $field=? WHERE id=1")->execute([$value]);
    reject(fn() => saveAutoSettlement(99,1,101,29,$snapshot), 'line has changed');
    ++$passed;
}
foreach (['', ' ', null] as $invoice) {
    $db = fixture();
    $db->prepare('UPDATE openpost SET faktnr=? WHERE id=101')->execute([$invoice]);
    reject(fn() => saveAutoSettlement(99,1,101,29,autoSettlementSnapshot(entry())), 'no invoice reference');
    ++$passed;
}
foreach (['V', 'S'] as $posted) {
    $db = fixture();
    $db->prepare('UPDATE kladdeliste SET bogfort=? WHERE id=99')->execute([$posted]);
    reject(fn() => saveAutoSettlement(99,1,101,29,autoSettlementSnapshot(entry())), 'no longer open');
    ++$passed;
}
$db = fixture();
$db->exec("UPDATE kassekladde SET debet='1009', d_type='K' WHERE id=1");
reject(fn() => saveAutoSettlement(99,1,102,30,autoSettlementSnapshot(entry())), 'different account');
++$passed;
$db = fixture();
$db->exec("UPDATE adresser SET art='F' WHERE id=29");
reject(fn() => saveAutoSettlement(99,1,101,29,autoSettlementSnapshot(entry())), 'no longer available');
++$passed;
fixture();
$GLOBALS['failUpdate'] = true;
reject(fn() => saveAutoSettlement(99,1,101,29,autoSettlementSnapshot(entry())), 'could not be saved');
++$passed;
fixture();
saveAutoSettlement(99,1,105,29,autoSettlementSnapshot(entry()));
check(entry()['faktura'] === 'NULL-OPEN', 'NULL udlignet should remain eligible');
++$passed;
echo "PASS: $passed account-filter and settlement cases (" . $GLOBALS['testDb']->getAttribute(PDO::ATTR_DRIVER_NAME) . ", E_ALL).\n";
