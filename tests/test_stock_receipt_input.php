<?php
// 20260921 CDX/LH Exercise actual receipt request handling against isolated PostgreSQL tables.
// 20260921 CDX/LH Cover explicit precision rejection, punctuated scans and exact outstanding quantities.
// 20260922 CDX/LH Verify numeric EAN-first ordering against an existing quantity-shaped SKU.
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
$dsn = getenv('SALDI_TEST_DSN');
if (!$dsn) {
    exit("SKIP: set SALDI_TEST_DSN to a PostgreSQL test connection.\n");
}
$connection = pg_connect($dsn);
$db_type = 'postgresql';
require_once __DIR__ . '/../includes/std_func.php';
function db_select($sql, $location = '') { return pg_query($GLOBALS['connection'], $sql); }
function db_fetch_array($result) { return pg_fetch_assoc($result); }
function db_escape_string($value) { return pg_escape_string($GLOBALS['connection'], $value); }
function db_modify($sql, $location = '') {
    $GLOBALS['receiptWrites'][] = $sql;
    return db_select($sql);
}
function modtag($id) { $GLOBALS['postedReceipt'] = $id; }
function receiptCheck($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}
$source = file_get_contents(__DIR__ . '/../lager/modtagelse.php');
$helperStart = strpos($source, 'function receiptQuantity(');
$helperEnd = strpos($source, '@session_start();', $helperStart);
eval(substr($source, $helperStart, $helperEnd - $helperStart));
$requestStart = strpos($source, 'if ($_POST) {');
$requestEnd = strpos($source, '\n############################', $requestStart);
if ($requestEnd === false) {
    $requestEnd = strpos($source, "\n############################", $requestStart);
}
$request = substr($source, $requestStart, $requestEnd - $requestStart);
$initializerStart = strpos($source, '$antal_ny = NULL;');
$initializer = substr($source, $initializerStart, strpos($source, '$modulnr =', $initializerStart) - $initializerStart);
function receiptRequest($input, $rowId = 1, $listId = 1) {
    global $request, $initializer;
    $_POST = $input;
    $id = $rowId;
    $liste_id = $listId;
    eval($initializer);
    $brugernavn = "fixture's operator";
    $fokus = 'varenr';
    $GLOBALS['receiptWrites'] = [];
    $GLOBALS['postedReceipt'] = null;
    ob_start();
    try {
        eval($request);
        $output = ob_get_contents();
    } finally {
        ob_end_clean();
    }
    return compact('id', 'liste_id', 'output', 'fokus');
}
foreach ([
    'CREATE TEMP TABLE modtageliste(id serial PRIMARY KEY, initdate date, init_af text, modtaget text)',
    'CREATE TEMP TABLE modtagelser(id serial PRIMARY KEY, liste_id integer REFERENCES modtageliste(id), vare_id integer, varenr text, beskrivelse text, antal numeric, leveres numeric, lager numeric)',
    'CREATE TEMP TABLE varer(id integer, varenr text, beskrivelse text)',
    'CREATE TEMP TABLE ordrer(id integer, status text, art text)',
    'CREATE TEMP TABLE ordrelinjer(id integer, ordre_id integer, varenr text, antal numeric)',
    'CREATE TEMP TABLE batch_kob(linje_id integer, antal numeric)',
    'CREATE TEMP TABLE batch_salg(linje_id integer, antal numeric)',
    "INSERT INTO modtageliste(id,modtaget) VALUES(1,'-')",
    "INSERT INTO varer VALUES(1,'SKU','Receipt item'),(2,'1234567890123','Scanned item')",
    "INSERT INTO ordrer VALUES(1,'1','KO'),(2,'1','DO')",
    "INSERT INTO ordrelinjer VALUES(1,1,'SKU',2000),(2,2,'SKU',0.5),(3,1,'1234567890123',2)",
] as $sql) {
    db_select($sql);
}
function resetReceipt() {
    db_select('DELETE FROM modtagelser');
    db_select("INSERT INTO modtagelser(id,liste_id,vare_id,varenr,beskrivelse,antal,leveres,lager) VALUES(1,1,1,'SKU','Receipt item',5,0.5,4.5)");
}
foreach (['1,00' => 1.0, '1.50' => 1.5, ' 2,75 ' => 2.75, '1.234,50' => 1234.5] as $input => $expected) {
    resetReceipt();
    receiptRequest(['varenr' => 'SKU', 'antal_ny' => $input, 'antal' => '5']);
    $row = db_fetch_array(db_select('SELECT * FROM modtagelser WHERE id=1'));
    receiptCheck((float)$row['antal'] === $expected && (float)$row['leveres'] === 0.5 && abs((float)$row['lager'] - ($expected - 0.5)) < 0.00001, "quantity $input updates exact received, reserved and stock amounts");
}
foreach (['bad', '', '-1', '1,2,3', '1e3', '1 OR 1=1', '1,00; DELETE FROM varer', '1.23,45', ['1']] as $input) {
    resetReceipt();
    $before = db_fetch_array(db_select('SELECT row_to_json(m)::text AS state FROM modtagelser m WHERE id=1'))['state'];
    $result = receiptRequest(['varenr' => 'SKU', 'antal_ny' => $input, 'modtag' => 'modtag']);
    $after = db_fetch_array(db_select('SELECT row_to_json(m)::text AS state FROM modtagelser m WHERE id=1'))['state'];
    receiptCheck($GLOBALS['receiptWrites'] === [] && $GLOBALS['postedReceipt'] === null && $before === $after && strpos($result['output'], 'Ugyldigt antal') !== false, 'malformed or missing edited quantity is rejected before any write or receive action');
}
resetReceipt();
receiptRequest(['varenr' => 'SKU', 'antal_ny' => '0,00']);
receiptCheck((int)db_fetch_array(db_select('SELECT COUNT(*) AS n FROM modtagelser'))['n'] === 0, 'explicit decimal zero retains delete-row semantics');
// Sequence gaps make max(id)+1 incorrect even without concurrent users.
db_select("SELECT setval(pg_get_serial_sequence('modtageliste','id'), 80, true)");
db_select("SELECT setval(pg_get_serial_sequence('modtagelser','id'), 170, true)");
$result = receiptRequest(['varenr' => 'SKU', 'antal_ny' => '', 'antal' => ''], 0, 0);
$row = db_fetch_array(db_select('SELECT * FROM modtagelser WHERE id=171'));
receiptCheck($result['liste_id'] === 81 && $result['id'] === 171 && (int)$row['liste_id'] === 81 && (float)$row['antal'] === 2000.0, 'new receipt links actual generated list and row IDs despite sequence gaps');
receiptCheck(db_fetch_array(db_select('SELECT init_af FROM modtageliste WHERE id=81'))['init_af'] === "fixture's operator", 'creator text is safely escaped');
// A higher ID with identical item/quantity must not steal the selection after INSERT.
db_select("CREATE FUNCTION pg_temp.receipt_extra_row() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN IF NEW.id < 9000 THEN INSERT INTO modtagelser(id,liste_id,vare_id,varenr,beskrivelse,antal,leveres,lager) VALUES(9000,NEW.liste_id,NEW.vare_id,NEW.varenr,NEW.beskrivelse,NEW.antal,NEW.leveres,NEW.lager); END IF; RETURN NEW; END'");
db_select('CREATE TRIGGER receipt_extra AFTER INSERT ON modtagelser FOR EACH ROW EXECUTE FUNCTION pg_temp.receipt_extra_row()');
$result = receiptRequest(['varenr' => '1234567890123', 'antal_ny' => '', 'antal' => ''], 0, 1);
receiptCheck($result['id'] === 172 && (int)db_fetch_array(db_select('SELECT MAX(id) AS id FROM modtagelser'))['id'] === 9000, 'receipt selection stays on its generated ID when a higher identical row exists');
db_select('DROP TRIGGER receipt_extra ON modtagelser');
// Legacy scanner concatenates the old quantity with its EAN code, with or without a space.
foreach (['51234567890123', '5 1234567890123'] as $barcode) {
    db_select("DELETE FROM modtagelser WHERE varenr='1234567890123'");
    $result = receiptRequest(['varenr' => 'SKU', 'antal_ny' => $barcode, 'antal' => '5'], 1, 1);
    $row = db_fetch_array(db_select('SELECT varenr,antal FROM modtagelser WHERE id=' . $result['id']));
    receiptCheck($row['varenr'] === '1234567890123' && (float)$row['antal'] === 2.0 && $result['fokus'] === 'antal_ny', 'scanner-appended EAN preserves item selection and outstanding quantity');
}
$result = receiptRequest(['varenr' => 'SKU', 'antal_ny' => '', 'modtag' => 'modtag'], 0, 1);
receiptCheck($GLOBALS['postedReceipt'] === 1, 'receive button remains available when no row is being edited');

// Fable review regressions: input precision is checked before staging or posting.
foreach (['0,0005', '1.23456', '1234567890123,0001'] as $input) {
    resetReceipt();
    $before = db_fetch_array(db_select('SELECT row_to_json(m)::text AS state FROM modtagelser m WHERE id=1'))['state'];
    $result = receiptRequest(['varenr' => 'SKU', 'antal_ny' => $input, 'modtag' => 'modtag']);
    receiptCheck($GLOBALS['receiptWrites'] === [] && $GLOBALS['postedReceipt'] === null && $before === db_fetch_array(db_select('SELECT row_to_json(m)::text AS state FROM modtagelser m WHERE id=1'))['state'] && strpos($result['output'], 'højst 3 decimaler') !== false, 'excess manual precision is explicitly rejected before any write or posting');
}
foreach (['0,001' => '0.001', '1.23000' => '1.23'] as $input => $expected) {
    resetReceipt();
    receiptRequest(['varenr' => 'SKU', 'antal_ny' => $input]);
    receiptCheck(db_fetch_array(db_select("SELECT antal=$expected AS exact FROM modtagelser WHERE id=1"))['exact'] === 't', 'supported fractional input is stored exactly, allowing insignificant trailing zeros');
}
foreach (['ABC-12345678', 'ABC.12345678', 'ABC/12345678', 'ABC_12345678', "ABC'12345678"] as $index => $sku) {
    $escaped = db_escape_string($sku);
    $itemId = 20 + $index;
    db_select("INSERT INTO varer VALUES($itemId,'$escaped','Punctuated scanner item');INSERT INTO ordrelinjer VALUES($itemId,1,'$escaped',2)");
    foreach (["5 $sku", "5$sku", "$sku 5"] as $scan) {
        db_select("DELETE FROM modtagelser WHERE varenr='$escaped'");
        $result = receiptRequest(['varenr' => 'SKU', 'antal_ny' => $scan, 'antal' => '5'], 1, 1);
        $row = db_fetch_array(db_select('SELECT varenr,antal=2 AS exact FROM modtagelser WHERE id=' . $result['id']));
        receiptCheck($row['varenr'] === $sku && $row['exact'] === 't' && $result['fokus'] === 'antal_ny', 'spaced, appended and reversed punctuated scans retain the exact existing SKU');
    }
}
resetReceipt();
db_select('UPDATE ordrelinjer SET antal=90000000000000001.123456 WHERE id=1;UPDATE ordrelinjer SET antal=0.000123 WHERE id=2');
receiptRequest(['varenr' => 'SKU', 'antal_ny' => '50000000000000000', 'antal' => '5']);
receiptCheck(db_fetch_array(db_select('SELECT antal=50000000000000000 AND leveres=0.000123 AND lager=49999999999999999.999877 AS exact FROM modtagelser WHERE id=1'))['exact'] === 't', 'large true integer input is not a scan and preserves fine-scale reservations exactly');
db_select('DELETE FROM modtagelser');
$result = receiptRequest(['varenr' => 'SKU', 'antal_ny' => '', 'antal' => ''], 0, 1);
receiptCheck(db_fetch_array(db_select('SELECT antal=90000000000000001.123456 AND leveres=0.000123 AND lager=90000000000000001.123333 AS exact FROM modtagelser WHERE id=' . $result['id']))['exact'] === 't', 'automatic outstanding receipt preserves existing unrestricted decimal quantity and reservations');

// 20260922 CDX/LH Numeric EAN-first scans must not select a quantity-shaped SKU.
db_select("INSERT INTO varer VALUES(99,'5','Quantity-shaped SKU');INSERT INTO ordrelinjer VALUES(99,1,'5',9)");
foreach (['1234567890123 5', '5 1234567890123', '1234567890123 1,50'] as $scan) {
    db_select("DELETE FROM modtagelser WHERE varenr IN ('1234567890123','5')");
    $result = receiptRequest(['varenr' => 'SKU', 'antal_ny' => $scan, 'antal' => '5'], 1, 1);
    $row = db_fetch_array(db_select('SELECT varenr,antal=2 AS exact FROM modtagelser WHERE id=' . $result['id']));
    receiptCheck($row && $row['varenr'] === '1234567890123' && $row['exact'] === 't' && (int)db_fetch_array(db_select("SELECT COUNT(*) AS n FROM modtagelser WHERE varenr='5'"))['n'] === 0, 'numeric EAN-first and quantity-first scans select the EAN even when quantity is another purchasable SKU');
}
