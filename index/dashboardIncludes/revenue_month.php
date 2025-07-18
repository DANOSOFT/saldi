<?php
// Definer første og sidste dag for regnskabsåret
$firstDayOfYear = date('Y-m-d', strtotime($regnstart)); // Første dag i regnskabsåret
$lastDayOfYear = date('Y-m-d', strtotime($regnslut)); // Sidste dag i regnskabsåret

// Hent dags dato (kun måned og dag)
$currentMonthDay = date('-m-d');

// Beregn dagens dato for dette regnskabsår
$currentDateThisYear = date('Y', strtotime($regnstart)) . $currentMonthDay;

$regnskabsår = date('Y', strtotime($regnstart));
if ($currentDateThisYear < $firstDayOfYear) {
    $regnskabsår++;
}

// Første dag i denne måned baseret på $regnstart
$firstDayOfMonth = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, $regnskabsår)); // Første dag i denne måned baseret på regnskabsåret

// Slutdatoen skal være den nuværende dag i regnskabsåret, ikke det aktuelle år
$currentDay = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d'), $regnskabsår)); // Dagens dato i regnskabsåret

// Sammenligning for denne måned, baseret på regnskabsåret
$q = db_select("
SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
FROM transaktioner T
WHERE T.transdate <= '$currentDay'
AND T.transdate >= '$firstDayOfMonth'
AND T.kontonr > $kontomin
AND T.kontonr < $kontomaks
", __FILE__ . " linje " . __LINE__);
$revenue = db_fetch_array($q)[0];

// Beregn første dag i samme måned sidste år
$firstDayOfMonthLastYear = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, $regnskabsår - 1)); // Første dag i samme måned sidste år
$currentDayLastYear = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d'), $regnskabsår - 1)); // Dagens dato sidste år

// Sammenligning for denne måned, sidste år baseret på regnskabsåret
$q = db_select("
SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
FROM transaktioner T
WHERE T.transdate <= '$currentDayLastYear'
AND T.transdate >= '$firstDayOfMonthLastYear'
AND T.kontonr > $kontomin
AND T.kontonr < $kontomaks
", __FILE__ . " linje " . __LINE__);
$revenue_last = db_fetch_array($q)[0];

// Beregn forskellen mellem indeværende år og sidste år
$revenue_diff = $revenue - $revenue_last;
$revenue_status = $revenue_diff > 0 ? 
    "<span style='color: #15b79f'>" . formatNumber(abs($revenue_diff)) . " kr</span> <span style='color: #999'>".findtekst('2385|mere end sidste år til dato', $sprog_id)."</span>" 
    : 
    "<span style='color: #ea3c3c'>" . formatNumber(abs($revenue_diff)) . " kr</span> <span style='color: #999'>".findtekst('2386|mindre end sidste år til dato', $sprog_id)."</span>";

key_value(findtekst('2384|Omsætning denne måned, ekskl. moms', $sprog_id), formatNumber($revenue ? $revenue : 0)." kr", "<hr style='margin: 1em 0em; background-color: #ddd; border: none; height: 1px'>$revenue_status");