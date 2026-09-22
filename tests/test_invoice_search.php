<?php
// tests/test_invoice_search.php --- 2026-09-22
// Copyright (c) 2026 Danosoft ApS
// 20260922 CDX/PHR Exercise payment aggregation and candidate ranking through the actual endpoint.
// Run with SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS (temporary PostgreSQL tables only).

if (!getenv('SALDI_CHAR_DSN')) {
    fwrite(STDERR, "SKIP: Set SALDI_CHAR_DSN for isolated PostgreSQL fixtures.\n");
    exit(0);
}
if (($argv[1] ?? '') === 'endpoint') {
    error_reporting(E_ALL);
    set_error_handler(function ($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    $testDb = new PDO(getenv('SALDI_CHAR_DSN'), getenv('SALDI_CHAR_PGUSER'), getenv('SALDI_CHAR_PGPASS'),
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $testDb->exec("CREATE TEMP TABLE adresser (id integer, kontonr text, art text, firmanavn text, gruppe text);
        CREATE TEMP TABLE openpost (id integer, konto_id integer, konto_nr text, faktnr text, amount numeric(15,3),
            transdate date, beskrivelse text, udlignet text, valuta text);
        CREATE TEMP TABLE ordrer (konto_id integer, fakturanr text, art text, betalings_id text);
        CREATE TEMP TABLE grupper (art text, kodenr text, fiscal_year integer, box5 text);
        INSERT INTO adresser VALUES (1,'10','D','Alpha','1'),(2,'20','K','Beta','1'),(3,'30','D','Gamma','1'),(4,'40','D','No open invoices','1');
        INSERT INTO grupper VALUES ('DG','1',1,'5800'),('KG','1',1,'5900');
        INSERT INTO openpost VALUES
            (11,1,'10','100',500,'2026-09-01','Invoice A','0','DKK'),
            (12,2,'20','100',500,'2026-09-01','Invoice B',NULL,'DKK'),
            (13,3,'30','200',-500,'2026-09-01','Credit','0','DKK'),
            (14,1,'10','300',125,'2026-09-01','Partial','0','DKK'),
            (15,1,'10','400',500,'2026-09-01','Settled','1','DKK'),
            (16,1,'10','',500,'2026-09-01','Blank','0','DKK');
        INSERT INTO ordrer VALUES
            (1,'100','DO','A-FIND'),(1,'100','DK','Z-DISPLAY'),(1,'100','OT','ZZ-IGNORE'),
            (1,'100','DO',''),(1,'100','DO',NULL),(2,'100','KO','BETA-ID'),(3,'200','DK','CREDIT-ID'),
            (1,'','DO','EMPTY-INVOICE-ID');");
    function db_select($sql, $trace) {
        return $GLOBALS['testDb']->query($sql);
    }
    function db_fetch_array($query) {
        return $query->fetch(PDO::FETCH_ASSOC);
    }
    function db_escape_string($value) {
        return substr($GLOBALS['testDb']->quote($value), 1, -1);
    }
    require __DIR__ . '/../finans/kassekladde_includes/autoSettlement.php';
    $regnaar = 1;
    $_GET = json_decode($argv[2], true);
    if ($_GET['mode'] === '__accounts') {
        $entry = true;
        $entryContext = ['account'=>$_GET['account'] ?? '', 'accountType'=>$_GET['accountType'] ?? ''];
        $page = file_get_contents(__DIR__ . '/../finans/autoudlign.php');
        $start = strpos($page, '$accountOptions = [];');
        eval(substr($page, $start, strpos($page, '$amount_fmt =', $start) - $start));
        echo json_encode(['results'=>$accountOptions, 'selectedAccountId'=>$selectedAccountId]);
        exit;
    }

    $source = file_get_contents(__DIR__ . '/../finans/kassekladde_includes/invoiceSearch.php');
    // Skip login/bootstrap only; execute the endpoint's actual SQL, ranking and JSON response.
    eval(substr($source, strpos($source, '$search =')));
    exit;
}
function invoiceSearchFixture(array $parameters): array {
    $parameters += ['mode'=>'open_post','currentAmount'=>'500','search'=>'','hintTokens'=>'[]','descWords'=>'[]'];
    $pipes = [];
    $process = proc_open([PHP_BINARY, __FILE__, 'endpoint', json_encode($parameters)],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || $errors !== '') {
        throw new RuntimeException('Endpoint failed: ' . $errors);
    }
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}
function invoiceSearchAssert(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$accounts = invoiceSearchFixture(['mode'=>'__accounts']);
invoiceSearchAssert(array_column($accounts['results'],'id') === [1,2,3], 'Selector includes accounts without eligible open invoices.');
$assigned = invoiceSearchFixture(['mode'=>'__accounts','account'=>'40','accountType'=>'D']);
invoiceSearchAssert($assigned['selectedAccountId'] === '4' && count($assigned['results']) === 1, 'Assigned account disappeared when no open invoice exists.');
$all = invoiceSearchFixture([]);
invoiceSearchAssert($all['pagination']['total'] === 4 && count($all['results']) === 4, 'Duplicate orders multiplied open posts.');
invoiceSearchAssert($all['autoSelectId'] === null, 'Equal amount candidates were not kept ambiguous.');
$byId = array_column($all['results'], null, 'id');
invoiceSearchAssert($byId[11]['betalings_id'] === 'Z-DISPLAY', 'Displayed payment ID differs from the previous MAX lookup.');
invoiceSearchAssert($byId[12]['betalings_id'] === 'BETA-ID', 'Same invoice number crossed account boundaries.');
invoiceSearchAssert($byId[14]['betalings_id'] === '', 'Missing payment ID hid an open post.');
foreach ([
    [['search'=>'A-FIND'], [11]],
    [['search'=>'a-find'], [11]],
    [['search'=>'BETA-ID'], [12]],
    [['search'=>'IGNORE'], []],
    [['search'=>'EMPTY-INVOICE-ID'], []],
    [['search'=>"x' OR 1=1 --"], []],
    [['account'=>'10','accountType'=>'D'], [11,14]],
    [['account'=>'10','accountType'=>'K'], []],
    [['account'=>'','accountType'=>'D'], []],
    [['account'=>[]], []],
    [['search'=>'125,00'], [14]],
    [['search'=>'Beta'], [12]],
    [['search'=>'100'], [11,12]],
] as [$parameters,$ids]) {
    $response = invoiceSearchFixture($parameters);
    $actual = array_column($response['results'], 'id');
    sort($actual);
    invoiceSearchAssert($actual === $ids && $response['pagination']['total'] === count($ids), 'Incorrect search/filter results: ' . json_encode($parameters));
}
$credit = invoiceSearchFixture(['currentAmount'=>'-500']);
invoiceSearchAssert($credit['autoSelectId'] === 13, 'Signed amount ranking changed.');
$regular = invoiceSearchFixture(['mode'=>'','account'=>'10','accountType'=>'D','search'=>'A-FIND']);
invoiceSearchAssert(count($regular['results']) === 1 && $regular['results'][0]['offsetAccount'] === '5800', 'Ordinary invoice lookup changed.');
echo "PASS: actual endpoint aggregation, any-payment-ID search, account scope, missing IDs, duplicate orders, ambiguity, signed amounts and ordinary lookup.\n";
