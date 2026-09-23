<?php
// 20260923 CDX/PHR Cover month bounds and period-limited matching without database writes.
require_once __DIR__ . '/../includes/alignOpenpostIncludes/period.php';
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
function db_escape_string($value) { return str_replace("'", "''", $value); }
$period = openpostSettlementPeriod('2025-12-31', '2026-03-04', null, null, '2026-01-01');
check($period['from'] === '2025-12' && $period['to'] === '2026-03', 'Defaults cover first through last open-post month');
check(array_keys($period['months']) === ['2025-12','2026-01','2026-02','2026-03'], 'Dropdown includes every month across the year boundary');
$period = openpostSettlementPeriod('2024-01-10', '2024-04-01', '2024-02', '2024-02', '2024-01-01');
check($period['start'] === '2024-02-01' && $period['end'] === '2024-03-01', 'End boundary includes the whole leap-year February');
$invalid = openpostSettlementPeriod('2026-01-01', '2026-03-01', ['invalid'], '2099-01', '2026-01-01');
check($invalid['from'] === '2026-01' && $invalid['to'] === '2026-03', 'Invalid and out-of-range inputs fall back to allowed bounds');
$reverse = openpostSettlementPeriod('2026-01-01', '2026-03-01', '2026-03', '2026-01', '2026-01-01');
check($reverse['from'] === $reverse['to'], 'Reversed bounds resolve to a valid one-month period');
$empty = openpostSettlementPeriod(null, null, null, null, '2026-06-14');
check(count($empty['months']) === 1 && $empty['from'] === '2026-06', 'No dated open posts use the anchor month');
$sql = openpostSettlementCandidateQuery(41, 37635, $period);
check(strpos($sql, "transdate>='2024-02-01' AND transdate<'2024-03-01'") !== false && strpos($sql, 'konto_id=41 AND id<>37635') !== false, 'Shared candidate SQL limits account, excludes anchor and applies both month boundaries');
ob_start();
renderOpenpostSettlementPeriod($period, ['post_id'=>37635, 'returside'=>"a&b='c'"]);
$html = ob_get_clean();
check(substr_count($html, '<select ') === 2 && strpos($html, 'Februar 2024') !== false && strpos($html, 'method=\'get\'') !== false, 'Two month/year dropdowns change period using a read-only GET form');
check(strpos($html, 'a&amp;b=&#039;c&#039;') !== false, 'Return context is HTML-escaped');
// Feed the existing matcher only the rows admitted by the same date bounds.
$fixtures = [['2024-01-31',100], ['2024-02-01',40], ['2024-02-29',60], ['2024-03-01',100]];
$amount = [-100];
foreach ($fixtures as [$date,$value]) {
    if ($date >= $period['start'] && $date < $period['end']) { $amount[] = $value; }
}
$udlign = []; $findMatchTimeLimit = 60;
ob_start();
require __DIR__ . '/../includes/alignOpenpostIncludes/findMatch.php';
ob_end_clean();
check($amount === [-100,40,60] && $udlign === [1=>'on',2=>'on'], 'Find modposter matches only included dates, including February 29');
