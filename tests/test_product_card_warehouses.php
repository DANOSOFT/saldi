<?php
// Product-card regression checks use temporary tables only.
// 20260923 CDX/PHR Cover fiscal-year duplicates and sparse warehouse numbers.
if (!getenv('SALDI_CHAR_DSN')) {
    echo "SKIP: set SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS.\n";
    exit;
}
$db = new PDO(getenv('SALDI_CHAR_DSN'), getenv('SALDI_CHAR_PGUSER'), getenv('SALDI_CHAR_PGPASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function db_select($sql, $context) { return $GLOBALS['db']->query($sql); }
function db_fetch_array($result) { return $result->fetch(PDO::FETCH_ASSOC); }
function db_modify($sql, $context) { return $GLOBALS['db']->exec($sql); }
function db_escape_string($value) { return substr($GLOBALS['db']->quote($value), 1, -1); }
function findtekst($number, $language) { return (string)$number; }
function lagerreguler($id, $quantity, $cost, $warehouse, $date, $variant) {
    $GLOBALS['adjustments'][$warehouse] = $quantity;
}
function sync_shop_vare($id, $quantity, $warehouse) { $GLOBALS['syncedWarehouse'] = $warehouse; }
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
function section($source, $start, $end) {
    $offset = strpos($source, $start);
    if ($offset === false || ($finish = strpos($source, $end, $offset)) === false) {
        throw new RuntimeException('Source section not found');
    }
    return substr($source, $offset, $finish - $offset);
}
$controller = file_get_contents(__DIR__ . '/../lager/varekort.php');
$load = section($controller, '$warehouseNames = array();', '$opener =');
$save = section($controller, '    if ($id && is_array($lagerlok)) {', '    ######### varianter');
$view = file_get_contents(__DIR__ . '/../lager/productCardIncludes/showLocations.php');
$locations = section($view, "\tif (\$stockItem) {", "\tprint \"<input");
$db->beginTransaction();
try {
    $db->exec("CREATE TEMP TABLE grupper (id int, art text, kodenr text, beskrivelse text, fiscal_year int);
        CREATE TEMP TABLE lagerstatus (id int, vare_id int, lager int, beholdning numeric, lok1 text);
        CREATE TEMP TABLE batch_kob (vare_id int, lager int, antal numeric);
        CREATE TEMP TABLE batch_salg (vare_id int, lager int, antal numeric)");
    $db->exec("INSERT INTO grupper SELECT n, 'LG', n::text, 'Warehouse '||n, 9 FROM generate_series(1,10) n;
        INSERT INTO grupper SELECT n+10, 'LG', n::text, 'Warehouse '||n, 10 FROM generate_series(1,10) n");
    $regnaar = 10;
    eval($load);
    check(count($warehouseNames) === 10 && array_keys($warehouseNames) === range(1,10), 'Twenty fiscal-year definitions produce ten warehouses');
    $db->exec("DELETE FROM grupper WHERE kodenr NOT IN ('1','3','10');
        UPDATE grupper SET beskrivelse='Old name' WHERE kodenr='3' AND fiscal_year=9;
        UPDATE grupper SET beskrivelse='Current name' WHERE kodenr='3' AND fiscal_year=10");
    eval($load);
    check($warehouseNames === [1=>'Warehouse 1',3=>'Current name',10=>'Warehouse 10'], 'Sparse numbers and current-year names are retained');
    $regnaar = 9;
    eval($load);
    check($warehouseNames[3] === 'Old name', 'Selected fiscal year takes precedence over newer years');
    $regnaar = 11;
    eval($load);
    check($warehouseNames[3] === 'Current name', 'Missing fiscal year falls back to latest definition');
    $db->exec("INSERT INTO lagerstatus VALUES (1,4899,1,2,'A'),(3,4899,3,4,'B'),(10,4899,10,-7,'C');
        INSERT INTO batch_kob VALUES (4899,1,2),(4899,3,4),(4899,10,-7)");
    $id = 4899; $stockItem = true; $sprog_id = 1;
    $vare_varianter = $variantVarerId = $lagerid = [];
    ob_start();
    eval($locations);
    $html = ob_get_clean();
    check(substr_count($html, 'name="lagerlok[') === 3 && strpos($html, 'name="lagerlok[10]"') !== false, 'Location fields use real warehouse numbers exactly once');
    check($lagerbeh === [1=>2,3=>4,10=>-7], 'Stock quantities remain attached to the correct warehouses');
    $lagerlok = [1=>'A2',3=>"B's shelf",10=>'C2',2=>'Unconfigured'];
    eval($save);
    check($db->query('SELECT lok1 FROM lagerstatus WHERE lager=3')->fetchColumn() === "B's shelf", 'Saving an escaped location updates the correct warehouse');
    check($db->query('SELECT lok1 FROM lagerstatus WHERE lager=10')->fetchColumn() === 'C2', 'Saving includes sparse warehouse ten');
    check((int)$db->query('SELECT count(*) FROM lagerstatus')->fetchColumn() === 3, 'No phantom stock rows are introduced');
    $stockStart = strpos($controller, 'if ($confirmStockChange &&');
    $stockSave = section(substr($controller, $stockStart), 'if ($stockItem) {', 'if (!$returside) {');
    $ny_lagerbeh = [1=>2,3=>5,10=>-6];
    $samlevare = false; $variant_vare_id = []; $kostpris = [0=>1]; $api_fil = true;
    $adjustments = [];
    eval($stockSave);
    check($adjustments === [3=>5,10=>-6], 'Stock adjustments target only changed, configured warehouse numbers');
    $lagerbeh = $ny_lagerbeh;
    $adjustments = [];
    eval($stockSave);
    check($adjustments === [] && $syncedWarehouse === 10, 'Unchanged stock synchronizes the last actual warehouse');
    $hidden = file_get_contents(__DIR__ . '/../lager/productCardIncludes/hiddenVars.php');
    ob_start();
    eval(section($hidden, 'foreach ($warehouseNames', '?>'));
    $html = ob_get_clean();
    check(strpos($html, "name='ny_lagerbeh[10]'") !== false && strpos($html, "name='ny_lagerbeh[2]'") === false, 'Stock confirmation preserves sparse warehouse keys');
    $db->exec('DELETE FROM grupper');
    eval($load);
    check($warehouseNames === [1=>''], 'Accounts without definitions retain warehouse-one fallback');
} finally {
    $db->rollBack();
}
