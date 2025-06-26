<?php
// Definer første og sidste dag for regnskabsåret
$firstDayOfYear = date('Y-m-d', strtotime($regnstart)); // Første dag i regnskabsåret
$lastDayOfYear = date('Y-m-d', strtotime($regnslut)); // Sidste dag i regnskabsåret

// Hent dags dato (kun måned og dag)
$currentMonthDay = date('-m-d');

// Beregn dagens dato for dette regnskabsår
$currentDateThisYear = date('Y', strtotime($regnstart)) . $currentMonthDay;
// If the calculated date is before the first day of the fiscal year, adjust it to the next year
if ($currentDateThisYear < $firstDayOfYear) {
    $currentDateThisYear = date('Y', strtotime('+1 year', strtotime($regnstart))) . $currentMonthDay;
}

// Beregn første dag for sidste regnskabsår
$firstDayOfLastYear = date('Y-m-d', strtotime('-1 year', strtotime($regnstart))); // Første dag sidste regnskabsår

// Beregn dagens dato for sidste regnskabsår
$currentDateLastYear = date('Y', strtotime('-1 year', strtotime($regnstart))) . $currentMonthDay;
// If the calculated date is before the first day of the fiscal year, adjust it to the next year
if ($currentDateLastYear < $firstDayOfLastYear) {
    $currentDateLastYear = date('Y', strtotime('+1 year', strtotime($currentDateLastYear))) . $currentMonthDay;
}

#echo $currentDateThisYear . " - " . $firstDayOfYear;
#echo $regnstart . " - " . $regnslut;
// Sammenligning for dette regnskabsår op til dags dato
$qtxt = "
SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
FROM transaktioner T
WHERE T.transdate <= '$currentDateThisYear'
AND T.transdate >= '$regnstart'
AND T.kontonr > $kontomin
AND T.kontonr < $kontomaks
";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$revenue = db_fetch_array($q)[0];

// Sammenligning for sidste regnskabsår op til dags dato
$qtxt = "
SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
FROM transaktioner T
WHERE T.transdate <= '$currentDateLastYear'
AND T.transdate >= '$firstDayOfLastYear'
AND T.kontonr > $kontomin
AND T.kontonr < $kontomaks
";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$revenue_last = db_fetch_array($q)[0];

// Beregn forskellen mellem indeværende regnskabsår og sidste regnskabsår
$revenue_diff = $revenue - $revenue_last;
$revenue_status = $revenue_diff > 0 ? 
    "<span style='color: #15b79f'>" . formatNumber(abs($revenue_diff)) . " kr</span> <span style='color: #999'>".findtekst('2385|mere end sidste år til dato', $sprog_id)."</span>" 
    : 
    "<span style='color: #ea3c3c'>" . formatNumber(abs($revenue_diff)) . " kr</span> <span style='color: #999'>".findtekst('2386|mindre end sidste år til dato', $sprog_id)."</span>";

key_value(findtekst('2383|Omsætning for året, ekskl. moms', $sprog_id), formatNumber($revenue ? $revenue : 0)." kr", "<hr style='margin: 1em 0em; background-color: #ddd; border: none; height: 1px'>$revenue_status");

