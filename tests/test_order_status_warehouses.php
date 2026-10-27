<?php
// 20260923 CDX/PHR Regression coverage for duplicate warehouse aliases in order status.
// All database fixtures are temporary and rolled back.
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
function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}
$source = file_get_contents(__DIR__ . '/../lager/lister/ordrestatus.php');
$start = strpos($source, '// Prefer the selected fiscal-year name');
$end = strpos($source, '// Add lager_total field', $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Warehouse SQL generation block not found');
}
$block = substr($source, $start, $end - $start);
$db->beginTransaction();
try {
    $db->exec("CREATE TEMP TABLE grupper (id int, art text, kodenr text, beskrivelse text, fiscal_year int);
        CREATE TEMP TABLE varer (id int);
        CREATE TEMP TABLE lagerstatus (vare_id int, lager int, beholdning numeric);
        INSERT INTO varer VALUES (4899);
        INSERT INTO grupper SELECT n, 'LG', n::text, 'Warehouse '||n, 9 FROM generate_series(1,10) n;
        INSERT INTO grupper SELECT n+10, 'LG', n::text, 'Warehouse '||n, 10 FROM generate_series(1,10) n;
        INSERT INTO lagerstatus VALUES (4899,1,2),(4899,1,3),(4899,10,-7)");
    $regnaar = 10; $columns = [];
    eval($block);
    check(count($columns) === 10 && count(array_unique($lagere)) === 10, 'Twenty definitions generate ten distinct warehouse columns');
    $sql = 'WITH LagerSummary AS (SELECT vare_id,lager,SUM(beholdning) AS beholdning FROM lagerstatus GROUP BY vare_id,lager) SELECT '
        . $SQLLagerFetch . ' v.id FROM varer v ' . $SQLLagerJoin . ' ORDER BY v.id LIMIT 100 OFFSET 0';
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    check(count($rows) === 1 && (float)$rows[0]['lager1'] === 5.0 && (float)$rows[0]['lager10'] === -7.0, 'Generated joins execute without duplicate aliases and preserve aggregate stock');
    check((float)$rows[0]['lager2'] === 0.0, 'Missing warehouse stock renders as zero');
    $db->exec("DELETE FROM grupper WHERE kodenr NOT IN ('1','3','10');
        UPDATE grupper SET beskrivelse='Old name' WHERE kodenr='3' AND fiscal_year=9;
        UPDATE grupper SET beskrivelse='New name' WHERE kodenr='3' AND fiscal_year=10");
    $columns = [];
    eval($block);
    check($lagere === ['lager1','lager3','lager10'] && $columns[1]['headerName'] === 'New name', 'Sparse warehouse numbers sort numerically and use selected-year labels');
    $regnaar = 9; $columns = [];
    eval($block);
    check($columns[1]['headerName'] === 'Old name', 'Selected older year takes precedence');
    $regnaar = 11; $columns = [];
    eval($block);
    check($columns[1]['headerName'] === 'New name', 'Missing year falls back to latest warehouse definition');
    $db->exec('DELETE FROM grupper');
    $columns = [];
    eval($block);
    check($columns === [] && $SQLLagerJoin === '' && $SQLLagerFetch === '', 'No warehouse definitions produce no warehouse joins');
} finally {
    $db->rollBack();
}
