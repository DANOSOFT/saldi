<?php
// Define first and last day of the fiscal year

$firstDayOfFiscalYear = date('Y-m-d', strtotime($regnstart));
$lastDayOfFiscalYear = date('Y-m-d', strtotime($regnslut));


$currentMonthDay = date('-m-d');

// Calculate today's date within the fiscal year
$currentDateThisYear = date('Y', strtotime($regnstart)) . $currentMonthDay;
$fiscalYear = date('Y', strtotime($regnstart));

if ($currentDateThisYear < $firstDayOfFiscalYear) {
    $fiscalYear++;
}


// Find Monday of the current week (start of the week) and Sunday (end of the week)
$today = mktime(0, 0, 0, date('m'), date('d'), $fiscalYear);
$dayOfWeek = date('N', $today); // 1 (mandag) til 7 (søndag)

$startOfWeek = date('Y-m-d', strtotime("-" . ($dayOfWeek - 1) . " days", $today)); // Start of the week (Monday)
$endOfWeek = date('Y-m-d', strtotime("+" . (7 - $dayOfWeek) . " days", $today));   // End of the week (Sunday)

// Fetch revenue for the current week in the fiscal year
$q = db_select("
SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
FROM transaktioner T
WHERE T.transdate >= '$startOfWeek'
AND T.transdate <= '$endOfWeek'
AND T.kontonr > $kontomin
AND T.kontonr < $kontomaks
", __FILE__ . " linje " . __LINE__);
$weeklyRevenue = db_fetch_array($q)[0];

// Find the same week last year
$startOfWeekLastYear = date('Y-m-d', strtotime('-1 year', strtotime($startOfWeek)));
$endOfWeekLastYear = date('Y-m-d', strtotime('-1 year', strtotime($endOfWeek)));

// Fetch revenue for the same week last year
$q = db_select("
SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
FROM transaktioner T
WHERE T.transdate >= '$startOfWeekLastYear'
AND T.transdate <= '$endOfWeekLastYear'
AND T.kontonr > $kontomin
AND T.kontonr < $kontomaks
", __FILE__ . " linje " . __LINE__);
$weeklyRevenueLastYear = db_fetch_array($q)[0];

// Calculate the difference between this year's and last year's revenue
$weeklyRevenueDiff = $weeklyRevenue - $weeklyRevenueLastYear;
$weeklyRevenueStatus = $weeklyRevenueDiff > 0 ? 
    "<span style='color: #15b79f'>" . formatNumber(abs($weeklyRevenueDiff)) . " kr</span> <span style='color: #999'>".findtekst('2385|mere end sidste år til dato', $sprog_id)."</span>" 
    : 
    "<span style='color: #ea3c3c'>" . formatNumber(abs($weeklyRevenueDiff)) . " kr</span> <span style='color: #999'>".findtekst('2386|mindre end sidste år til dato', $sprog_id)."</span>";

// Output

key_value(findtekst('2679|Omsætning denne uge, ekskl. moms', $sprog_id), formatNumber($weeklyRevenue ? $weeklyRevenue : 0) . " kr", "<hr style='margin: 1em 0em; background-color: #ddd; border: none; height: 1px'>$weeklyRevenueStatus");
 