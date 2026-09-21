<?php
// 20260920 CDX/LH Exercise the complete CSV importer with local transport and temporary PostgreSQL tables.
// 20260921 CDX/LH Distinguish referenced identity conflicts from unrelated historical duplicates.
namespace SaldiVaresyncTest;
if (PHP_SAPI !== 'cli') exit('CLI only');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new \RuntimeException($message); });
$dsn = getenv('SALDI_TEST_DSN');
if (!$dsn) exit("SKIP: set SALDI_TEST_DSN to a PostgreSQL test connection.\n");
$GLOBALS['csvConnection'] = pg_connect($dsn);
require_once(__DIR__ . '/../includes/std_func.php');
$source = file_get_contents(__DIR__ . '/../api/varesync.php');
// Namespace isolates transport/stock adapters; importer logic is the complete production function.
eval('namespace SaldiVaresyncTest; ' . preg_replace('/^<\?php|\?>\s*$/', '', $source));
function db_select($sql, $location = '') {
    $result = pg_query($GLOBALS['csvConnection'], $sql);
    if (!$result) throw new \RuntimeException(pg_last_error($GLOBALS['csvConnection']));
    return $result;
}
function db_modify($sql, $location = '') { return db_select($sql, $location); }
function db_fetch_array($result) { return pg_fetch_assoc($result); }
function db_num_rows($result) { return pg_num_rows($result); }
function db_escape_string($value) { return pg_escape_string($GLOBALS['csvConnection'], (string)$value); }
function shell_exec($command) { return ''; }
function system($command) {
    foreach (array('shop_products.csv' => 'csvProducts', 'shop_variants.csv' => 'csvVariants') as $name => $fixture) {
        if (strpos($command, '/files/' . $name) !== false) {
            file_put_contents('../temp/' . $GLOBALS['db'] . '/' . $name, $GLOBALS[$fixture]);
        }
    }
    return '';
}
function lagerreguler($id, $qty, $unused, $unused2, $date, $variant) {
    $GLOBALS['stockCalls'][] = array((int)$id, (float)$qty, (int)$variant);
    db_modify('UPDATE varer SET beholdning=' . (float)$qty . ' WHERE id=' . (int)$id);
}
function dkdecimal($value) { return number_format((float)$value, 2, ',', '.'); }
function csvScalar($sql) { return pg_fetch_row(db_select($sql))[0]; }
function checkCsv($condition, $message) {
    if (!$condition) throw new \RuntimeException($message);
    echo "PASS: $message\n";
}
$work = sys_get_temp_dir() . '/saldi-csv-regression-' . getmypid();
mkdir($work . '/api', 0777, true);
$GLOBALS['db'] = 'csv_regression';
$GLOBALS['brugernavn'] = 'csv-regression';
mkdir($work . '/temp/' . $GLOBALS['db'], 0777, true);
chdir($work . '/api');
db_modify("CREATE TEMP TABLE varer (id serial PRIMARY KEY,varenr text,stregkode text,beskrivelse text,salgspris numeric,kostpris numeric,gruppe numeric,beholdning numeric,lukket text,min_lager int,varianter int DEFAULT 0)");
db_modify("CREATE TEMP TABLE shop_varer (id serial PRIMARY KEY,saldi_id int,shop_id int,saldi_variant int,shop_variant int)");
db_modify("CREATE TEMP TABLE grupper (art text,box4 text)");
db_modify("INSERT INTO grupper VALUES ('API','http://local-fixture.invalid/api.php')");
db_modify("CREATE TEMP TABLE settings (var_name text,var_grp text,var_value text)");
db_modify("CREATE TEMP TABLE varianter (id serial PRIMARY KEY,beskrivelse text)");
db_modify("CREATE TEMP TABLE variant_typer (id serial PRIMARY KEY,variant_id int,beskrivelse text)");
db_modify("CREATE TEMP TABLE variant_varer (id serial PRIMARY KEY,vare_id int,variant_type int,variant_beholdning numeric,variant_stregkode text,lager int,variant_salgspris numeric,variant_kostpris numeric,variant_vejlpris numeric,variant_id int)");
$variantHeader = '"varenr";"parent_id";"variant_id";"stregkode";"variant";"variant_type";"variant_text"';
foreach (array('8col', 'cost', 'qty', 'qty_crlf', 'qty_headerless', 'empty', 'variants') as $dialect) {
    foreach (array('UTF-8', 'ISO-8859-1') as $encoding) {
        db_modify('TRUNCATE varer,shop_varer,variant_typer,variant_varer,varianter RESTART IDENTITY');
        $fields = array('id', 'varenr', 'stregkode', 'salgspris');
        if ($dialect === 'cost') $fields[] = 'kostpris';
        $fields = array_merge($fields, array('beskrivelse', 'gruppe', 'tilbud', 'notes'));
        if (in_array($dialect, array('qty', 'qty_crlf', 'qty_headerless'), true)) $fields[] = 'qty';
        $rows = array('"' . implode('";"', $fields) . '"');
        for ($i = 1; $i <= 3; $i++) {
            $values = array(100 + $i, 'SKU-' . $i, 'EAN' . (100 + $i), '100.50');
            if ($dialect === 'cost') $values[] = '50';
            $values = array_merge($values, array('Dansk æøå; quoted product', '1', '', ''));
            if (in_array($dialect, array('qty', 'qty_crlf', 'qty_headerless'), true)) $values[] = '5';
            $rows[] = '"' . implode('";"', $values) . '"';
        }
        if ($dialect === 'empty') $rows = array($rows[0]);
        // Headerless legacy CSV has eight columns; quantity dialect requires its qty header.
        if ($dialect === 'qty_headerless') array_shift($rows);
        $GLOBALS['csvProducts'] = mb_convert_encoding(implode($dialect === 'qty_crlf' ? "\r\n" : "\n", $rows) . "\n", $encoding, 'UTF-8');
        $GLOBALS['csvVariants'] = $variantHeader . "\n";
        if ($dialect === 'variants') {
            db_modify("INSERT INTO varianter(beskrivelse) VALUES ('Size')");
            for ($i = 1; $i <= 3; $i++) {
                $GLOBALS['csvVariants'] .= '"SKU-' . $i . '";"' . (100 + $i) . '";"' . (200 + $i) . '";"V-EAN-' . $i . '";"Size ' . $i . '";"Size";"Variant æøå"' . "\n";
            }
            $GLOBALS['csvVariants'] = mb_convert_encoding($GLOBALS['csvVariants'], $encoding, 'UTF-8');
        }
        $GLOBALS['stockCalls'] = array();
        ob_start();
        try { varesync(1); } finally { ob_end_clean(); }
        $expected = $dialect === 'empty' ? '0' : '3';
        checkCsv(csvScalar('SELECT count(*) FROM varer') === $expected, "$dialect / $encoding imports exact product count without PHP warnings");
        if ($dialect === 'empty') continue;
        checkCsv(csvScalar("SELECT count(*) FROM varer WHERE beskrivelse='Dansk æøå; quoted product'") === '3', "$dialect / $encoding preserves Danish text and quoted separator");
        checkCsv(csvScalar('SELECT count(*) FROM shop_varer WHERE shop_variant=0') === '3', "$dialect / $encoding creates product bindings");
        if ($dialect === 'cost') checkCsv(csvScalar('SELECT count(*) FROM varer WHERE kostpris=50') === '3', "$encoding imports cost prices");
        if (in_array($dialect, array('qty', 'qty_crlf'), true)) checkCsv(csvScalar('SELECT count(*) FROM varer WHERE beholdning=5') === '3', "$dialect / $encoding imports quantity instead of processing qty header as a number");
        if ($dialect === 'variants') {
            checkCsv(csvScalar('SELECT count(*) FROM variant_typer') === '3' && csvScalar('SELECT count(*) FROM variant_varer') === '3', "$encoding creates initial variant types and products from empty tables");
            checkCsv(csvScalar('SELECT count(*) FROM shop_varer WHERE saldi_variant>0 AND shop_variant>0') === '3', "$encoding creates three variant bindings");
        }
    }
}
// Identity conflicts must leave both products and housekeeping mappings unchanged.
foreach (['incoming-sku', 'incoming-normalized-sku', 'incoming-shop-id', 'existing-sku', 'existing-shop-id', 'existing-product-binding', 'conflicting-binding'] as $conflict) {
    db_modify('TRUNCATE varer,shop_varer,variant_typer,variant_varer,varianter RESTART IDENTITY');
    db_modify("INSERT INTO varer(varenr,beskrivelse,salgspris,kostpris,stregkode) VALUES ('EXISTING','Keep',10,4,'')");
    db_modify("INSERT INTO shop_varer(saldi_id,shop_id,saldi_variant,shop_variant) VALUES (1,99,NULL,NULL)");
    if ($conflict === 'existing-sku') db_modify("INSERT INTO varer(varenr,beskrivelse) VALUES ('EXISTING','Also keep')");
    if (in_array($conflict, ['existing-shop-id', 'existing-product-binding'], true)) {
        db_modify("INSERT INTO varer(varenr,beskrivelse) VALUES ('OTHER-OLD','Also keep')");
        db_modify("INSERT INTO shop_varer(saldi_id,shop_id,saldi_variant,shop_variant) VALUES (2,99,NULL,NULL)");
    }
    $firstSku = in_array($conflict, ['existing-sku', 'existing-shop-id', 'existing-product-binding'], true) ? 'EXISTING' : 'NEW';
    $secondSku = $conflict === 'incoming-sku' ? 'NEW' : ($conflict === 'incoming-normalized-sku' ? ' NEW ' : 'OTHER');
    $secondShop = $conflict === 'incoming-shop-id' ? 101 : 102;
    $firstShop = in_array($conflict, ['conflicting-binding', 'existing-shop-id'], true) ? 99 : ($conflict === 'existing-product-binding' ? 0 : 101);
    $GLOBALS['csvProducts'] = "id;varenr;stregkode;salgspris;beskrivelse;gruppe;tilbud;notes\n$firstShop;$firstSku;B1;100;New;1;;\n$secondShop;$secondSku;B2;200;Other;1;;\n";
    $GLOBALS['csvVariants'] = $variantHeader . "\n";
    $before = csvScalar("SELECT md5(string_agg(row_to_json(v)::text,',' ORDER BY id)) FROM varer v") . csvScalar("SELECT md5(string_agg(row_to_json(s)::text,',' ORDER BY id)) FROM shop_varer s");
    $failed = false;
    ob_start();
    try { varesync(1); } catch (\RuntimeException $error) { $failed = true; } finally { ob_end_clean(); }
    $after = csvScalar("SELECT md5(string_agg(row_to_json(v)::text,',' ORDER BY id)) FROM varer v") . csvScalar("SELECT md5(string_agg(row_to_json(s)::text,',' ORDER BY id)) FROM shop_varer s");
    checkCsv($failed && $before === $after, "$conflict rejects complete batch before product or mapping writes");
}
// Unrelated historical ambiguity cannot block an otherwise valid new catalog.
foreach (['sku', 'shop-id', 'both'] as $historical) {
    db_modify('TRUNCATE varer,shop_varer,variant_typer,variant_varer,varianter RESTART IDENTITY');
    $oldSku = $historical === 'shop-id' ? 'OTHER-OLD' : 'HISTORICAL';
    $oldShopId = $historical === 'sku' ? 98 : 99;
    db_modify("INSERT INTO varer(varenr,beskrivelse,salgspris,kostpris,stregkode) VALUES ('HISTORICAL','Keep first',10,4,''),('$oldSku','Keep second',20,8,'')");
    db_modify("INSERT INTO shop_varer(saldi_id,shop_id,saldi_variant,shop_variant) VALUES (1,99,NULL,NULL),(2,$oldShopId,NULL,NULL),(1,99,NULL,NULL)");
    $oldProducts = csvScalar("SELECT md5(string_agg(row_to_json(v)::text,',' ORDER BY id)) FROM varer v WHERE id<=2");
    $oldMappings = csvScalar("SELECT md5(string_agg(row_to_json(s)::text,',' ORDER BY id)) FROM shop_varer s WHERE id<=3");
    $GLOBALS['csvProducts'] = "id;varenr;stregkode;salgspris;beskrivelse;gruppe;tilbud;notes;qty\n101;NEW;NEW-BARCODE;100;New;1;;;5\n";
    $GLOBALS['csvVariants'] = $variantHeader . "\n";
    ob_start();
    try { varesync(1); } finally { ob_end_clean(); }
    checkCsv(csvScalar("SELECT count(*) FROM varer v JOIN shop_varer s ON s.saldi_id=v.id WHERE v.varenr='NEW' AND v.beholdning=5 AND s.shop_id=101") === '1', "unrelated historical $historical ambiguity permits exact new product/stock/binding import");
    checkCsv($oldProducts === csvScalar("SELECT md5(string_agg(row_to_json(v)::text,',' ORDER BY id)) FROM varer v WHERE id<=2") && $oldMappings === csvScalar("SELECT md5(string_agg(row_to_json(s)::text,',' ORDER BY id)) FROM shop_varer s WHERE id<=3"), "unrelated historical $historical rows remain byte-for-byte unchanged, including nullable duplicate mappings");
}
db_modify('TRUNCATE varer,shop_varer,variant_typer,variant_varer,varianter RESTART IDENTITY');
// A pre-existing row with a different ID verifies that linking never selects an arbitrary matching row.
db_modify("INSERT INTO varer(id,varenr,beskrivelse,salgspris,kostpris,stregkode) VALUES (50,'OTHER','Keep',1,1,'')");
$GLOBALS['csvProducts'] = "id;varenr;stregkode;salgspris;beskrivelse;gruppe;tilbud;notes;qty\n101;Owner's SKU;B1;100;Owner product;1;;;5\n";
$GLOBALS['csvVariants'] = $variantHeader . "\n";
ob_start();
try { varesync(1); varesync(1); } finally { ob_end_clean(); }
checkCsv(csvScalar("SELECT count(*) FROM varer WHERE varenr='Owner''s SKU'") === '1', 'repeated synchronization retains one quoted product identity');
checkCsv(csvScalar("SELECT count(*) FROM shop_varer s JOIN varer v ON v.id=s.saldi_id WHERE v.varenr='Owner''s SKU' AND s.shop_id=101") === '1', 'shop binding uses exact inserted product ID');
checkCsv(csvScalar("SELECT beholdning FROM varer WHERE varenr='Owner''s SKU'") === '5', 'quoted SKU stock uses the same identity as storage');

db_modify('TRUNCATE varer,shop_varer,variant_typer,variant_varer,varianter RESTART IDENTITY');
db_modify("CREATE FUNCTION pg_temp.csv_identity_trigger() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NEW.varenr='TRIGGER-MAIN' THEN INSERT INTO varer(varenr,beskrivelse,salgspris,kostpris,stregkode) VALUES ('TRIGGER-SIDE','Side effect',0,0,''); END IF; RETURN NEW; END \$\$");
db_modify('CREATE TRIGGER csv_identity BEFORE INSERT ON varer FOR EACH ROW EXECUTE FUNCTION pg_temp.csv_identity_trigger()');
$GLOBALS['csvProducts'] = "id;varenr;stregkode;salgspris;beskrivelse;gruppe;tilbud;notes\n101;TRIGGER-MAIN;B1;100;Main;1;;\n";
$GLOBALS['csvVariants'] = $variantHeader . "\n";
ob_start();
try { varesync(1); } finally { ob_end_clean(); }
checkCsv(csvScalar("SELECT count(*) FROM shop_varer s JOIN varer v ON v.id=s.saldi_id WHERE v.varenr='TRIGGER-MAIN' AND s.shop_id=101") === '1', 'RETURNING binds the inserted row even when a trigger advances the same sequence');
