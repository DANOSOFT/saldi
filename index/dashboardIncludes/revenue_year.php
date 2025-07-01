<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//
// --- systemdata/financialYearInc/createAccountPrimo --- ver 4.1.1 --- 2025-06-29 --
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------------
// 20250629 - PHR setting $regnstart & $regnsslut if not set

// Definer første og sidste dag for regnskabsåret
$firstDayOfYear = date('Y-m-d', strtotime($regnstart)); // Første dag i regnskabsåret
$lastDayOfYear = date('Y-m-d', strtotime($regnslut)); // Sidste dag i regnskabsåret
if ($regnstart < '1970-01-01') $regnstart = date("Y")."-01-01";
if ($regnsslut < '1970-01-01') $regnslut = date("Y")."-12-31";

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

