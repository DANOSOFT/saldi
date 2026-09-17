<?php
// --- tests/test_cash_count_history.php --- 2026-09-17 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20260917 CDX/PHR Exercise historical decimal repair and ambiguous/unsupported reports.
// 20260917 CDX/PHR Check receipts omit repair annotations.
require_once __DIR__ . '/../debitor/cashCountHistoryData.php';
function historyCheck($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function historyFixture($turnover, array $payments)
{
    $rows = [];
    foreach (['50 øre'=>2, 'Dagens omsætning: '=>$turnover, 'Morgenbeholdning'=>1500,
        'Dagens tilgang: '=>842, 'Forventet beholdning DKK: '=>2342,
        'Optalt beholdning DKK: '=>2138, 'Difference DKK: '=>-204,
        'Udtaget fra kasse   DKK: '=>638] + $payments as $description=>$total) {
        $rows[] = compact('description', 'total');
    }
    return $rows;
}
$rows = historyFixture(7783.10, ['Dankort'=>69411, 'MobilePay'=>0]);
$result = cashCountHistoryPrepare($rows, '2025-08-15');
historyCheck($result['rows'][8]['total'] === 6941.1, 'Real report 567: repair factor ten');
historyCheck($result['rows'][0]['total'] === 2, 'Preserve piece counts');
historyCheck($rows[8]['total'] === 69411, 'Do not mutate input');
historyCheck($result['rows'][8]['original'] === 69411, 'Keep original visible');
$correct = historyFixture(7783.10, ['Dankort'=>6941.1]);
historyCheck(cashCountHistoryPrepare($correct, '2025-08-15')['rows'] === $correct, 'Correct decimals unchanged');
$r = cashCountHistoryPrepare(historyFixture(911.41, ['Dankort'=>6941]), '2025-08-15');
historyCheck($r['rows'][8]['total'] === 69.41, 'Repair factor hundred');
$ambiguous = historyFixture(952, ['Dankort'=>100, 'MobilePay'=>100]);
historyCheck(cashCountHistoryPrepare($ambiguous, '2025-08-15')['rows'] === $ambiguous, 'Ambiguous assignments unchanged');
$manual = historyFixture(7783.1, ['Dankort(6.941,10)'=>69411]);
historyCheck(cashCountHistoryPrepare($manual, '2025-08-15')['rows'] === $manual, 'Manual card changes unchanged');
$foreign = $rows;
$foreign[8]['description'] = 'Morgenbeholdning EUR:';
historyCheck(cashCountHistoryPrepare($foreign, '2025-08-15')['rows'] === $foreign, 'Foreign currencies unchanged');
historyCheck(cashCountHistoryPrepare($rows, '2026-09-03')['rows'] === $rows, 'Do not infer the old bug after fix');
$negative = historyFixture(832.5, ['Dankort'=>-95]);
historyCheck(cashCountHistoryPrepare($negative, '2025-08-15')['rows'][8]['total'] === -9.5, 'Negative card refunds');
historyCheck(cashCountHistoryEscape('<script>"') === '&lt;script&gt;&quot;', 'Escape stored labels');
echo "OK: cash count history tests\n";

// Receipt generation is tested without contacting a physical printer.
require_once __DIR__ . '/../debitor/cashCountHistoryPrint.php';
$receipt = cashCountHistoryReceipt(
    ['date'=>'2025-08-15', 'register'=>1, 'report_number'=>567],
    $result,
    ['firmanavn'=>'Testbutik ÆØÅ', 'cvrnr'=>'12345678'],
    'Tester'
);
$utf8 = iconv('CP865', 'UTF-8', $receipt);
historyCheck(strpos($utf8, 'KASSEOPGØRELSE') !== false, 'Danish receipt charset');
historyCheck(strpos($receipt, '6.941,10') !== false, 'Print corrected amount');
historyCheck(strpos($receipt, 'Oprindeligt:') === false && strpos($receipt, ' *') === false, 'No repair annotations');
historyCheck(strpos($receipt, 'decimalrettelser') === false, 'No repair warning');
historyCheck(strpos($receipt, '2025-08-15') !== false, 'Print original count date');
historyCheck(strpos($receipt, 'GENUDSKRIFT') !== false, 'Identify historical copy');
foreach (explode("\n", $receipt) as $line) {
    historyCheck(strlen($line) <= 40, 'Receipt fits normal 40-column layout');
}
historyCheck(strpos(cashCountReceiptText("Bad\x1b\x1d\nlabel"), "\x1b") === false, 'Strip printer control characters');
echo "OK: cash count receipt tests (no physical print)\n";

// Optional PostgreSQL integration. All writes target a session-local TEMP table.
$testDatabase = getenv('CASH_COUNT_TEST_DATABASE');
if ($testDatabase) {
    chdir(__DIR__ . '/../debitor');
    require __DIR__ . '/../includes/connect.php';
    $connection = pg_connect("host=$sqhost dbname=$testDatabase user=$squser password=$sqpass");
    if (!$connection) {
        throw new RuntimeException('Could not connect to cash count test database');
    }
    // The SQL wrappers log every SELECT; keep those logs in a disposable directory.
    $db = 'cash-count-test-' . bin2hex(random_bytes(8));
    $testLogDirectory = __DIR__ . '/../temp/' . $db;
    mkdir($testLogDirectory);
    $brugernavn = 'cash-count-test';
    $db_skriv_id = 0;
    pg_query($connection, 'CREATE TEMP TABLE report (id integer primary key, date date, type text, report_number integer, description text, total numeric)');
    foreach ($rows as $i => $row) {
        pg_query_params($connection, 'INSERT INTO report VALUES ($1,$2,$3,$4,$5,$6)',
            [$i+1, '2025-08-15', 'cashCount', 567, $row['description'], $row['total']]);
    }
    pg_query($connection, "INSERT INTO report VALUES (100,'2025-08-15','cashCount',568,'Dankort',69411)");
    $saved = cashCountHistoryLoadAndRepair(567, '2025-08-15');
    historyCheck((float)pg_fetch_result(pg_query($connection, 'SELECT total FROM report WHERE id=9'), 0, 0) === 6941.1, 'Persist repaired amount');
    historyCheck(!isset($saved['rows'][8]['original']) && $saved['warning'] === '', 'Clean saved result');
    historyCheck((float)pg_fetch_result(pg_query($connection, 'SELECT total FROM report WHERE id=100'), 0, 0) === 69411.0, 'Other reports untouched');
    $savedAgain = cashCountHistoryLoadAndRepair(567, '2025-08-15');
    historyCheck((float)$savedAgain['rows'][8]['total'] === 6941.1, 'Repeated load is idempotent');

    // Two unique corrections; suppress the second write to verify all-or-nothing.
    pg_query($connection, 'UPDATE report SET total=69411 WHERE id=9');
    pg_query($connection, 'UPDATE report SET total=221 WHERE id=10');
    pg_query($connection, 'UPDATE report SET total=7805.20 WHERE id=2');
    pg_query($connection, 'CREATE FUNCTION pg_temp.reject_second_repair() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.id=10 THEN RETURN NULL; END IF; RETURN NEW; END $$');
    pg_query($connection, 'CREATE TRIGGER reject_second_repair BEFORE UPDATE ON report FOR EACH ROW EXECUTE PROCEDURE pg_temp.reject_second_repair()');
    $failed = false;
    try {
        cashCountHistoryLoadAndRepair(567, '2025-08-15');
    } catch (RuntimeException $error) {
        $failed = true;
    }
    historyCheck($failed, 'Detect a write which did not persist');
    historyCheck((float)pg_fetch_result(pg_query($connection, 'SELECT total FROM report WHERE id=9'), 0, 0) === 69411.0, 'Rollback earlier correction on failure');
    pg_close($connection);
    unlink($testLogDirectory . '/.ht_select.log');
    rmdir($testLogDirectory);
    echo "OK: temporary-table persistence, scope, idempotence and rollback tests\n";
}
