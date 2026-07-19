<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport_includes/moms_afstemning.php --- patch 5.0.0 --- 2026-07-19 ---
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
// but WITHOUT ANY WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft.ApS
// ----------------------------------------------------------------------
// 20260716 MJ R4 – Momsafstemning (momssandsynliggoerelse): pr. (konto, momskode)
//                  sammenligner bogfoert moms med beregnet moms. Differencer fremhaeves.
//                  CSV-eksport.

function moms_afstemning($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
                         $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart,
                         $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,
                         $simulering, $lagerbev) {

    global $db, $md, $menu, $sprog_id, $top_bund;
    global $buttonStyle, $topStyle;
    global $ansatte_id, $afd_navn, $ansatte;

    $regnaar    = (int)$regnaar;
    $maaned_fra = trim($maaned_fra);
    $maaned_til = trim($maaned_til);
    $konto_fra  = trim($konto_fra);
    $konto_til  = trim($konto_til);

    for ($x = 1; $x <= 12; $x++) {
        if ($maaned_fra == $md[$x]) $maaned_fra = $x;
        if ($maaned_til == $md[$x]) $maaned_til = $x;
        if (strlen($maaned_fra) == 1) $maaned_fra = '0'.$maaned_fra;
        if (strlen($maaned_til) == 1) $maaned_til = '0'.$maaned_til;
    }
    $mf = $maaned_fra;
    $mt = $maaned_til;

    $row = db_fetch_array(db_select("select * from grupper where kodenr='$regnaar' and art='RA'", __FILE__." linje ".__LINE__));
    $startmaaned = (int)($row['box1'] ?? 1);
    $startaar    = (int)($row['box2'] ?? $regnaar);
    $slutmaaned  = (int)($row['box3'] ?? 12);
    $slutaar     = (int)($row['box4'] ?? $regnaar);
    $startdato   = 1;
    $slutdato    = 31;

    if ($aar_fra < $aar_til) {
        if ($maaned_til > $slutmaaned)      $aar_til = $aar_fra;
        elseif ($maaned_fra < $startmaaned) $aar_fra = $aar_til;
    }
    if ($aar_fra)    $startaar    = $aar_fra;
    if ($aar_til)    $slutaar     = $aar_til;
    if ($maaned_fra) $startmaaned = $maaned_fra;
    if ($maaned_til) $slutmaaned  = $maaned_til;
    if ($dato_fra)   $startdato   = $dato_fra;
    if ($dato_til)   $slutdato    = $dato_til;

    $startdato = (int)$startdato;
    if ($startdato < 10) $startdato = '0'.$startdato;
    while (!checkdate($startmaaned, $startdato, $startaar)) { $startdato--; if ($startdato < 28) break; }
    while (!checkdate($slutmaaned,  $slutdato,  $slutaar))  { $slutdato--;  if ($slutdato  < 28) break; }
    if (strlen((string)$startdato) < 2) $startdato = '0'.$startdato;

    $regnstart = "$startaar-$startmaaned-$startdato";
    $regnslut  = "$slutaar-$slutmaaned-$slutdato";

    $dim = '';
    if ($afd || $afd == '0') $dim = "AND t.afd = $afd ";

    // configurable tolerance — persisted in session so it survives report navigation
    if (array_key_exists('tolerance', $_GET) && $_GET['tolerance'] !== '') {
        $tolerance = abs((float)$_GET['tolerance']);
        $_SESSION['moms_afstemning_tolerance'] = $tolerance;
    } else {
        $tolerance = isset($_SESSION['moms_afstemning_tolerance']) ? (float)$_SESSION['moms_afstemning_tolerance'] : 0;
    }

    $konto_filter = '';
    if ($konto_fra && $konto_til && $konto_fra != $konto_til)
        $konto_filter = "AND kp.kontonr >= '$konto_fra' AND kp.kontonr <= '$konto_til' ";
    elseif ($konto_fra)
        $konto_filter = "AND kp.kontonr = '$konto_fra' ";

    $csvfile = "../temp/$db/moms_afstemning.csv";
    $csv     = fopen($csvfile, "w");

    $title    = 'Rapport • Momsafstemning';
    $back_url = "rapport.php?rapportart=moms_afstemning&regnaar=$regnaar"
              . "&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra"
              . "&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til"
              . "&konto_fra=$konto_fra&konto_til=$konto_til"
              . "&ansat_fra=$ansat_fra&ansat_til=$ansat_til"
              . "&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til"
              . "&simulering=$simulering&lagerbev=$lagerbev&tolerance=$tolerance";
    $csv_btn  = "<a href='$csvfile' style='color:#ffffff; text-decoration:none;'><i class='fa fa-download'></i> CSV</a>";

    include("../includes/topline_settings.php");

    if ($menu == 'T') {
        include_once '../includes/top_header.php';
        include_once '../includes/top_menu.php';
        print "<div id=\"header\">";
        print "<div class=\"headerbtnLft headLink\"><a href=\"$back_url\" accesskey=\"L\"><i class='fa fa-close fa-lg'></i> Luk</a></div>";
        print "<div class=\"headerTxt\">Momsafstemning</div>";
        print "<div class=\"headerbtnRght headLink\">$csv_btn</div>";
        print "</div>";
        print "<div class='content-noside'>";
    } elseif ($menu == 'S') {
        $back_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
        print "<table bgcolor='#eeeef0' width='100%' cellpadding='0' cellspacing='0' border='0'><tbody><tr><td colspan=8 align=center>";
        print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody>";
        print "<td width='5%'><a href=\"javascript:confirmClose('$back_url','')\" accesskey=L>"
            . "<button class='headerbtn' type='button' style='$buttonStyle; width:100%; display:flex; align-items:center; gap:5px;'>"
            . "$back_icon Tilbage</button></a></td>";
        print "<td width='80%' align='center' style='$topStyle'>Momsafstemning</td>";
        print "<td width='10%' align='center' style='$buttonStyle'>$csv_btn</td>";
        print "<td width='5%'></td>";
        print "</tbody></table></td></tr></tbody></table>";
    } else {
        print "<table width='100%' cellpadding='0' cellspacing='1px' border='0' valign='top' align='center'>";
        print "<tr><td colspan='6' height='8'>";
        print "<table width='100%' align='center' border='0' cellspacing='3' cellpadding='0'><tbody>";
        print "<td width='10%' $top_bund><a accesskey=L href='$back_url'>Luk</a></td>";
        print "<td width='80%' $top_bund>Rapport - Momsafstemning</td>";
        print "<td width='10%' $top_bund><a href='$csvfile'>CSV</a></td>";
        print "</tbody></table></td></tr></table>";
    }

    $row = db_fetch_array(db_select("select firmanavn from adresser where art='S'", __FILE__." linje ".__LINE__));
    $firmanavn = $row ? $row['firmanavn'] : '';
    print "<div style='padding:8px 12px;'><big><b>$firmanavn</b></big><br>";
    print "Periode: " . dkdato($regnstart) . " – " . dkdato($regnslut);
    print " &nbsp;|&nbsp; Tolerance: " . dkdecimal($tolerance, 2) . " kr.";
    print "</div>";

    // Quarter shortcuts
    $q_base = "rapport.php?rapportart=moms_afstemning&regnaar=$regnaar&dato_fra=01&dato_til=31"
            . "&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til"
            . "&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev"
            . "&tolerance=$tolerance";
    print "<div class='no-print' style='padding:2px 12px 6px; font-size:0.9em; color:#555;'>Genveje: ";
    foreach (['Q1'=>[1,3],'Q2'=>[4,6],'Q3'=>[7,9],'Q4'=>[10,12]] as $ql => $qm) {
        print "<a href='$q_base&maaned_fra={$qm[0]}&aar_fra=$startaar&maaned_til={$qm[1]}&aar_til=$startaar' style='margin-right:8px;'>$ql</a>";
    }
    print "<a href='$q_base&maaned_fra=$startmaaned&aar_fra=$startaar&maaned_til=$slutmaaned&aar_til=$slutaar'>Hele &aring;ret</a></div>";

    // tolerance filter form
    print "<form method='GET' action='rapport.php' style='padding:4px 12px; display:inline-block;'>";
    foreach (['rapportart'=>'moms_afstemning','regnaar'=>$regnaar,'dato_fra'=>$startdato,
              'maaned_fra'=>$mf,'aar_fra'=>$aar_fra,'dato_til'=>$slutdato,'maaned_til'=>$mt,
              'aar_til'=>$aar_til,'konto_fra'=>$konto_fra,'konto_til'=>$konto_til,
              'ansat_fra'=>$ansat_fra,'ansat_til'=>$ansat_til,'afd'=>$afd,
              'projekt_fra'=>$projekt_fra,'projekt_til'=>$projekt_til,
              'simulering'=>$simulering,'lagerbev'=>$lagerbev] as $k => $v) {
        print "<input type='hidden' name='" . htmlspecialchars($k) . "' value='" . htmlspecialchars($v) . "'>";
    }
    print "Tolerance (kr.): <input type='number' name='tolerance' step='0.01' min='0' value='"
        . htmlspecialchars($tolerance) . "' style='width:70px;'> "
        . "<input type='submit' value='Opdater'></form>";

    // Main aggregation query:
    // Per (kontonr, momskode): sum net turnover, sum booked VAT from t.moms,
    // derive calculated VAT = turnover * rate / 100
    $qtxt = "SELECT"
          . " kp.kontonr,"
          . " kp.beskrivelse AS konto_navn,"
          . " kp.moms AS momskode,"
          . " g.beskrivelse AS moms_navn,"
          . " CAST(COALESCE(NULLIF(g.box2,''),NULL) AS NUMERIC(10,4)) AS momssats,"
          . " SUM(t.debet - t.kredit) AS omsaetning,"
          . " SUM(t.moms) AS bogfoert_moms"
          . " FROM transaktioner t"
          . " JOIN kontoplan kp ON kp.kontonr = t.kontonr AND kp.regnskabsaar = '$regnaar'"
          . " JOIN ("
          .   " SELECT DISTINCT ON (art, kodenr) art, kodenr, beskrivelse, box2"
          .   " FROM grupper WHERE art IN ('SM','KM','YM','EM')"
          .   " ORDER BY art, kodenr, fiscal_year DESC NULLS LAST"
          . " ) g ON g.art = UPPER(SUBSTRING(kp.moms FROM 1 FOR 1)) || 'M'"
          .   " AND CAST(g.kodenr AS TEXT) = SUBSTRING(kp.moms FROM 2)"
          . " WHERE t.transdate >= '$regnstart' AND t.transdate <= '$regnslut'"
          . " AND kp.moms IS NOT NULL AND kp.moms != ''"
          . " $konto_filter $dim"
          . " GROUP BY kp.kontonr, kp.beskrivelse, kp.moms, g.beskrivelse, g.box2"
          . " ORDER BY kp.kontonr, kp.moms";

    $q = db_select($qtxt, __FILE__." linje ".__LINE__);

    print "<div style='overflow-x:auto; padding:0 12px;'>";
    print "<table class='dataTable' border='0' cellspacing='1' width='100%'>";
    print "<thead><tr style='position:sticky; top:0; background:#eeeef0;'>";
    print "<th align='left'  style='width:80px'>Konto</th>";
    print "<th align='left'>Kontonavn</th>";
    print "<th align='left'  style='width:70px'>Momskode</th>";
    print "<th align='left'  style='width:100px'>Momsnavn</th>";
    print "<th align='right' style='width:50px'>Sats %</th>";
    print "<th align='right' style='width:120px'>Omsaetning</th>";
    print "<th align='right' style='width:120px'>Beregnet moms</th>";
    print "<th align='right' style='width:120px'>Bogfoert moms</th>";
    print "<th align='right' style='width:120px'>Difference</th>";
    print "</tr></thead><tbody>";

    fwrite($csv, mb_convert_encoding(
        "Konto;Kontonavn;Momskode;Momsnavn;Sats %;Omsaetning;Beregnet moms;Bogfoert moms;Difference\n",
        'ISO-8859-1', 'UTF-8'));

    // Buffer results so we can show summary before the table
    $rows = [];
    while ($r = db_fetch_array($q)) $rows[] = $r;

    // Compute summary
    $total_rows = count($rows);
    $diff_rows  = 0;
    foreach ($rows as $r) {
        $s = ($r['momssats'] !== null) ? (float)$r['momssats'] : 0;
        if ($s > 0 && abs(afrund((float)$r['bogfoert_moms'] - afrund((float)$r['omsaetning'] * $s / 100, 2), 2)) > $tolerance)
            $diff_rows++;
    }
    if ($total_rows > 0) {
        $sum_bg    = $diff_rows > 0 ? '#ffe0e0' : '#d4edda';
        $sum_color = $diff_rows > 0 ? '#c00'    : '#155724';
        print "<div style='padding:6px 12px; margin:4px 12px 8px; border-radius:4px; background:$sum_bg; color:$sum_color;'>";
        if ($diff_rows > 0)
            print "<b>$diff_rows af $total_rows r&aelig;kker</b> har afvigelse over tolerance (" . dkdecimal($tolerance, 2) . " kr.).";
        else
            print "<b>Ingen afvigelser</b> fundet i $total_rows r&aelig;kker.";
        print "</div>";
    }

    $row_bg = ['#ffffff','#f5f5f5'];
    $row_i  = 0;
    $has_rows = false;

    foreach ($rows as $row) {
        $has_rows    = true;
        $sats        = ($row['momssats'] !== null) ? (float)$row['momssats'] : 0;
        $omsaetning  = (float)$row['omsaetning'];
        $bogfoert    = (float)$row['bogfoert_moms'];
        $beregnet    = ($sats > 0) ? afrund($omsaetning * $sats / 100, 2) : 0;
        $diff        = afrund($bogfoert - $beregnet, 2);
        $abs_diff    = abs($diff);

        $row_style = ($abs_diff > $tolerance && $sats > 0)
            ? "background:#ffe0e0;"
            : "background:" . $row_bg[$row_i % 2] . ";";
        $row_i++;

        $diff_style = ($abs_diff > $tolerance && $sats > 0) ? " style='color:#c00; font-weight:bold;'" : '';

        print "<tr style='$row_style'>";
        print "<td>" . htmlspecialchars($row['kontonr']) . "</td>";
        print "<td>" . htmlspecialchars($row['konto_navn']) . "</td>";
        print "<td>" . htmlspecialchars($row['momskode']) . "</td>";
        print "<td style='font-size:0.85em;'>" . htmlspecialchars($row['moms_navn'] ?? '') . "</td>";
        print "<td align='right'>" . ($sats > 0 ? dkdecimal($sats, 2) : '') . "</td>";
        print "<td align='right'>" . dkdecimal($omsaetning, 2) . "</td>";
        print "<td align='right'>" . ($sats > 0 ? dkdecimal($beregnet, 2) : '<span style="color:#aaa">–</span>') . "</td>";
        print "<td align='right'>" . dkdecimal($bogfoert, 2) . "</td>";
        print "<td align='right'$diff_style>" . ($sats > 0 ? dkdecimal($diff, 2) : '<span style="color:#aaa">–</span>') . "</td>";
        print "</tr>\n";

        fwrite($csv, mb_convert_encoding(
            $row['kontonr'] . ";"
            . $row['konto_navn'] . ";"
            . $row['momskode'] . ";"
            . ($row['moms_navn'] ?? '') . ";"
            . ($sats > 0 ? dkdecimal($sats, 2) : '') . ";"
            . "\"" . dkdecimal($omsaetning, 2) . "\";"
            . "\"" . ($sats > 0 ? dkdecimal($beregnet, 2) : '') . "\";"
            . "\"" . dkdecimal($bogfoert, 2) . "\";"
            . "\"" . ($sats > 0 ? dkdecimal($diff, 2) : '') . "\"\n",
            'ISO-8859-1', 'UTF-8'));
    } // end foreach $rows

    if (!$has_rows) {
        print "<tr><td colspan='9' align='center' style='padding:16px; color:#888;'>"
            . "Ingen posteringer med momskode i den valgte periode.</td></tr>";
    }

    print "</tbody></table></div>";
    fclose($csv);

    if ($menu == 'T') {
        include_once '../includes/topmenu/footer.php';
    } else {
        include_once '../includes/oldDesign/footer.php';
    }
}
?>
