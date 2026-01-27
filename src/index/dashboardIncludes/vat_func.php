<?php
function vat_info($regnstart, $regnslut) {
    # Omsætningsgraf
    global $regnaar, $sprog_id;

    $qtxt = "select * from grupper where art = 'MR' and fiscal_year = $regnaar";
    $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    $kontomin = (int)$r["box1"];
    $kontomaks = (int)$r["box2"];

    // Definer første og sidste dag for regnskabsåret
    $firstDayOfYear = date('Y-m-d', strtotime($regnstart)); // Første dag i regnskabsåret
    $lastDayOfYear = date('Y-m-d', strtotime($regnslut)); // Sidste dag i regnskabsåret

    // Beregn første og sidste dag for det foregående regnskabsår
    $firstDayOfLastYear = date('Y-m-d', strtotime('-1 year', strtotime($firstDayOfYear))); // Første dag sidste regnskabsår
    $lastDayOfLastYear = date('Y-m-d', strtotime('-1 year', strtotime($lastDayOfYear))); // Sidste dag sidste regnskabsår
    // If the calculated date is before the first day of the fiscal year, adjust it to the next year
    if ($lastDayOfLastYear < $firstDayOfLastYear) {
        $lastDayOfLastYear = date('Y-m-d', strtotime('+1 year', strtotime($lastDayOfLastYear)));
    }

    // Sammenligning for dette regnskabsår
    $qtxt = "
    SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
    FROM transaktioner T
    WHERE T.transdate <= '$lastDayOfYear'
    AND T.transdate >= '$firstDayOfYear'
    AND T.kontonr >= $kontomin
    AND T.kontonr <= $kontomaks
    ";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    $revenue = db_fetch_array($q)[0];

    // Sammenligning for sidste regnskabsår
    $qtxt = "
    SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
    FROM transaktioner T
    WHERE T.transdate <= '$lastDayOfLastYear'
    AND T.transdate >= '$firstDayOfLastYear'
    AND T.kontonr >= $kontomin
    AND T.kontonr <= $kontomaks
    ";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    $revenue_last = db_fetch_array($q)[0];

    // Beregn forskellen mellem indeværende regnskabsår og sidste regnskabsår
    $revenue_diff = $revenue - $revenue_last;
    $revenue_status = $revenue_diff > 0 ? 
        "<span style='color: #15b79f'>" . formatNumber(abs($revenue_diff)) . " kr</span> <span style='color: #999'>".findtekst('2385|mere end sidste år til dato', $sprog_id)."</span>" 
        : 
        "<span style='color: #ea3c3c'>" . formatNumber(abs($revenue_diff)) . " kr</span> <span style='color: #999'>".findtekst('2386|mindre end sidste år til dato', $sprog_id)."</span>";

    key_value(findtekst('2301|Momsangivelse for året', $sprog_id), formatNumber($revenue ? $revenue : 0)." kr", "<hr style='margin: 1em 0em; background-color: #ddd; border: none; height: 1px'>$revenue_status");
}

