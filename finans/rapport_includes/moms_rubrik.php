<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport_includes/moms_rubrik.php --- patch 5.0.0 --- 2026-07-22 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft.ApS
// ----------------------------------------------------------------------
// 20260716 MJ R3 – Momsrubrikker (A-varer, A-ydelser, B-varer, B-ydelser, C):
//                  summerer nettobeloeb pr. rubrik baseret paa box5 paa momskoder.
//                  Kraever at box5 (Rubrik) er sat i Indstillinger -> Moms.
//                  CSV-eksport.
// 20260722 CL/MJ  COALESCE(debet,0)/COALESCE(kredit,0) i SUM: NULL-aritmetik
//                 gav NULL (vist som 0) for rene debet- eller kreditkonti.

function moms_rubrik($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
                     $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart,
                     $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,
                     $simulering, $lagerbev) {

    global $db, $md, $menu, $sprog_id, $top_bund;
    global $buttonStyle, $topStyle;
    global $ansatte_id, $afd_navn, $ansatte;

    $regnaar    = (int)$regnaar;
    $maaned_fra = trim($maaned_fra);
    $maaned_til = trim($maaned_til);

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

    $csvfile = "../temp/$db/moms_rubrik.csv";
    $csv     = fopen($csvfile, "w");

    $title    = 'Rapport • Momsrubrikker (A/B/C)';
    $back_url = "rapport.php?rapportart=moms_rubrik&regnaar=$regnaar"
              . "&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra"
              . "&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til"
              . "&konto_fra=$konto_fra&konto_til=$konto_til"
              . "&ansat_fra=$ansat_fra&ansat_til=$ansat_til"
              . "&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til"
              . "&simulering=$simulering&lagerbev=$lagerbev";
    $csv_btn  = "<a href='$csvfile' style='color:#ffffff; text-decoration:none;'><i class='fa fa-download'></i> CSV</a>";

    include("../includes/topline_settings.php");

    if ($menu == 'T') {
        include_once '../includes/top_header.php';
        include_once '../includes/top_menu.php';
        print "<div id=\"header\">";
        print "<div class=\"headerbtnLft headLink\"><a href=\"$back_url\" accesskey=\"L\"><i class='fa fa-close fa-lg'></i> Luk</a></div>";
        print "<div class=\"headerTxt\">Momsrubrikker (A/B/C)</div>";
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
        print "<td width='85%' align='center' style='$topStyle'>Momsrubrikker (A/B/C)</td>";
        print "<td width='10%' align='center' style='$buttonStyle'>$csv_btn</td>";
        print "</tbody></table></td></tr></tbody></table>";
    } else {
        print "<table width='100%' cellpadding='0' cellspacing='1px' border='0' valign='top' align='center'>";
        print "<tr><td colspan='6' height='8'>";
        print "<table width='100%' align='center' border='0' cellspacing='3' cellpadding='0'><tbody>";
        print "<td width='10%' $top_bund><a accesskey=L href='$back_url'>Luk</a></td>";
        print "<td width='80%' $top_bund>Rapport - Momsrubrikker (A/B/C)</td>";
        print "<td width='10%' $top_bund><a href='$csvfile'>CSV</a></td>";
        print "</tbody></table></td></tr></table>";
    }

    $row = db_fetch_array(db_select("select firmanavn from adresser where art='S'", __FILE__." linje ".__LINE__));
    $firmanavn = $row ? $row['firmanavn'] : '';
    print "<div style='padding:8px 12px;'><big><b>$firmanavn</b></big><br>";
    print "Periode: " . dkdato($regnstart) . " – " . dkdato($regnslut) . "</div>";

    // Quarter shortcuts
    $q_base = "rapport.php?rapportart=moms_rubrik&regnaar=$regnaar&dato_fra=01&dato_til=31"
            . "&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til"
            . "&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev";
    print "<div class='no-print' style='padding:2px 12px 6px; font-size:0.9em; color:#555;'>Genveje: ";
    foreach (['Q1'=>[1,3],'Q2'=>[4,6],'Q3'=>[7,9],'Q4'=>[10,12]] as $ql => $qm) {
        print "<a href='$q_base&maaned_fra={$qm[0]}&aar_fra=$startaar&maaned_til={$qm[1]}&aar_til=$startaar' style='margin-right:8px;'>$ql</a>";
    }
    print "<a href='$q_base&maaned_fra=$startmaaned&aar_fra=$startaar&maaned_til=$slutmaaned&aar_til=$slutaar'>Hele &aring;ret</a></div>";

    // Rubrik labels per spec section 2.3
    $rubrikker = [
        'A-varer'   => 'Rubrik A – varer: V&aelig;rdien uden moms af varek&oslash;b i andre EU-lande',
        'A-ydelser' => 'Rubrik A – ydelser: V&aelig;rdien uden moms af ydelsesk&oslash;b i andre EU-lande',
        'B-varer'   => 'Rubrik B – varer: V&aelig;rdien af varesalg uden moms til andre EU-lande (B2B)',
        'B-ydelser' => 'Rubrik B – ydelser: V&aelig;rdien af visse ydelsessalg uden moms til andre EU-lande',
        'C'         => 'Rubrik C: V&aelig;rdien af andre varer og ydelser leveret uden afgift',
    ];

    // Aggregate: SUM(debet - kredit) per rubrik using box5 on the VAT code row
    $qtxt = "SELECT g.box5 AS rubrik, SUM(COALESCE(t.debet, 0) - COALESCE(t.kredit, 0)) AS nettobeloeb"
          . " FROM transaktioner t"
          . " JOIN kontoplan kp ON kp.kontonr = t.kontonr AND kp.regnskabsaar = '$regnaar'"
          . " JOIN ("
          .   " SELECT DISTINCT ON (art, kodenr) art, kodenr, box5"
          .   " FROM grupper WHERE art IN ('SM','KM','YM','EM')"
          .   " ORDER BY art, kodenr, fiscal_year DESC NULLS LAST"
          . " ) g ON g.art = UPPER(SUBSTRING(kp.moms FROM 1 FOR 1)) || 'M'"
          .   " AND CAST(g.kodenr AS TEXT) = SUBSTRING(kp.moms FROM 2)"
          . " WHERE t.transdate >= '$regnstart' AND t.transdate <= '$regnslut'"
          . " AND kp.moms IS NOT NULL AND kp.moms != ''"
          . " AND g.box5 IS NOT NULL AND g.box5 != ''"
          . " $dim"
          . " GROUP BY g.box5"
          . " ORDER BY g.box5";
    $q = db_select($qtxt, __FILE__." linje ".__LINE__);
    $rubrik_sum = [];
    while ($r = db_fetch_array($q)) $rubrik_sum[$r['rubrik']] = (float)$r['nettobeloeb'];

    // Check if any rubrik mapping exists
    $has_mapping = db_fetch_array(db_select(
        "SELECT 1 FROM grupper WHERE art IN ('SM','KM','YM','EM') AND box5 IS NOT NULL AND box5 != '' LIMIT 1",
        __FILE__." linje ".__LINE__));
    if (!$has_mapping) {
        print "<div style='padding:16px 12px; color:#c00;'>";
        print "<b>Rubrik-mapping ikke konfigureret.</b><br>";
        print "Gaa til <a href='../systemdata/syssetup.php?valg=moms'>Indstillinger &rarr; Moms</a> ";
        print "og udfyld feltet <b>Rubrik</b> for de momskoder, der skal indg&aring; i rubrik-rapporten.";
        print "</div>";
    }

    print "<div style='padding:0 12px;'>";
    print "<table class='dataTable' border='0' cellspacing='1' width='600px'>";
    print "<thead><tr style='background:#eeeef0;'>";
    print "<th align='left'>Rubrik</th>";
    print "<th align='left'>Beskrivelse</th>";
    print "<th align='right' style='width:140px'>Nettobeloeb</th>";
    print "</tr></thead><tbody>";

    fwrite($csv, mb_convert_encoding("Rubrik;Beskrivelse;Nettobeloeb\n", 'ISO-8859-1', 'UTF-8'));

    $grand = 0;
    foreach ($rubrikker as $kode => $beskrivelse) {
        $sum = $rubrik_sum[$kode] ?? null;
        $bg  = ($sum === null) ? '#fafafa' : '#ffffff';
        $sum_txt = ($sum !== null) ? dkdecimal($sum, 2) : '<span style="color:#aaa">0,00</span>';
        $sum_csv = ($sum !== null) ? dkdecimal($sum, 2) : '0,00';
        if ($sum !== null) $grand += $sum;
        print "<tr style='background:$bg;'>";
        print "<td><b>$kode</b></td>";
        print "<td>$beskrivelse</td>";
        print "<td align='right'>$sum_txt</td>";
        print "</tr>";
        fwrite($csv, mb_convert_encoding("$kode;$beskrivelse;\"$sum_csv\"\n", 'ISO-8859-1', 'UTF-8'));
    }

    print "<tr style='background:#c8d8e8; font-weight:bold;'>";
    print "<td colspan='2' align='right'>I alt</td>";
    print "<td align='right'>" . dkdecimal($grand, 2) . "</td>";
    print "</tr>";
    print "</tbody></table></div>";
    fclose($csv);

    if ($menu == 'T') {
        include_once '../includes/topmenu/footer.php';
    } else {
        include_once '../includes/oldDesign/footer.php';
    }
}
?>
