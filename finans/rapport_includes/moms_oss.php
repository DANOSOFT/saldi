<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport_includes/moms_oss.php --- patch 5.0.0 --- 2026-07-19 ---
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
// 20260716 MJ R2 – OSS B2C EU-salg: oversigt over momspligtige salg og opkraevet
//                  moms pr. land og sats til brug ved OSS-momsangivelse.
//                  Kraever at Land (OSS) er sat i Indstillinger -> Moms paa de
//                  relevante SM-momskoder. Type (varer/ydelser) er valgfrit.
//                  CSV-eksport.
// 20260717 MJ     Tilfoejede detaljeret posteringsoversigt (alle OSS-linjer med dato,
//                  bilag, tekst, beloeb, moms, beloeb inkl. moms) over sammendrag.
//                  Sammendrag omstruktureret med separate kolonner for varer/ydelser.
// 20260720 CL/MJ  Fiscal-year-aware kvartalsgenveje; note om at kontofilter ikke galder OSS.
// 20260720 CL/MJ  Bugfix: t.tekst → t.beskrivelse AS tekst (transaktioner har ikke kolonne tekst).

function moms_oss($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
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
    $fy_startmaaned = $startmaaned;
    $fy_slutmaaned  = $slutmaaned;
    $fy_startaar    = $startaar;
    $fy_slutaar     = $slutaar;
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

    $csvfile = "../temp/$db/moms_oss.csv";
    $csv     = fopen($csvfile, "w");

    $title    = 'Rapport • OSS B2C EU-salg';
    $back_url = "rapport.php?rapportart=moms_oss&regnaar=$regnaar"
              . "&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra"
              . "&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til"
              . "&konto_fra=$konto_fra&konto_til=$konto_til"
              . "&ansat_fra=$ansat_fra&ansat_til=$ansat_til"
              . "&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til"
              . "&simulering=$simulering&lagerbev=$lagerbev";
    $csv_btn   = "<a href='$csvfile' style='color:#ffffff; text-decoration:none;'><i class='fa fa-download'></i> CSV</a>";
    $print_btn = "<button onclick='window.print()' style='background:transparent;border:none;color:#fff;cursor:pointer;padding:0;'><i class='fa fa-print'></i> Print</button>";
    print "<style>@media print{"
        . ".no-print,button,form,a[href*='rapport.php'],a[href*='confirmClose']{display:none!important}"
        . "table{page-break-inside:auto}tr{page-break-inside:avoid}"
        . "body,div{font-size:10pt}"
        . "}</style>";

    include("../includes/topline_settings.php");

    if ($menu == 'T') {
        include_once '../includes/top_header.php';
        include_once '../includes/top_menu.php';
        print "<div id=\"header\">";
        print "<div class=\"headerbtnLft headLink\"><a href=\"$back_url\" accesskey=\"L\"><i class='fa fa-close fa-lg'></i> Luk</a></div>";
        print "<div class=\"headerTxt\">OSS B2C EU-salg</div>";
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
        print "<td width='80%' align='center' style='$topStyle'>OSS B2C EU-salg</td>";
        print "<td width='5%' align='center' style='$buttonStyle'>$print_btn</td>";
        print "<td width='10%' align='center' style='$buttonStyle'>$csv_btn</td>";
        print "</tbody></table></td></tr></tbody></table>";
    } else {
        print "<table width='100%' cellpadding='0' cellspacing='1px' border='0' valign='top' align='center'>";
        print "<tr><td colspan='6' height='8'>";
        print "<table width='100%' align='center' border='0' cellspacing='3' cellpadding='0'><tbody>";
        print "<td width='10%' $top_bund><a accesskey=L href='$back_url'>Luk</a></td>";
        print "<td width='80%' $top_bund>Rapport - OSS B2C EU-salg</td>";
        print "<td width='10%' $top_bund><a href='$csvfile'>CSV</a></td>";
        print "</tbody></table></td></tr></table>";
    }

    $row = db_fetch_array(db_select("select firmanavn from adresser where art='S'", __FILE__." linje ".__LINE__));
    $firmanavn = $row ? $row['firmanavn'] : '';
    print "<div style='padding:8px 12px;'><big><b>$firmanavn</b></big><br>";
    print "Periode: " . dkdato($regnstart) . " – " . dkdato($regnslut) . "</div>";

    // Quarter shortcuts
    $q_base = "rapport.php?rapportart=moms_oss&regnaar=$regnaar&dato_fra=01&dato_til=31"
            . "&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd"
            . "&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev";
    print "<div class='no-print' style='padding:2px 12px 6px; font-size:0.9em; color:#555;'>Genveje: ";
    foreach (['Q1'=>[1,3],'Q2'=>[4,6],'Q3'=>[7,9],'Q4'=>[10,12]] as $ql => $qm) {
        $qaar = ($fy_startaar !== $fy_slutaar && $qm[0] >= $fy_startmaaned) ? $fy_startaar : $fy_slutaar;
        print "<a href='$q_base&maaned_fra={$qm[0]}&aar_fra=$qaar&maaned_til={$qm[1]}&aar_til=$qaar' style='margin-right:8px;'>$ql</a>";
    }
    print "<a href='$q_base&maaned_fra=$fy_startmaaned&aar_fra=$fy_startaar&maaned_til=$fy_slutmaaned&aar_til=$fy_slutaar'>Hele &aring;ret</a></div>";
    print "<div class='no-print' style='padding:0 12px 6px; font-size:0.85em; color:#888;'>"
        . "Kontofilter gælder ikke for OSS-rapporten — den dækker alle konti med OSS-momskoder.</div>";

    // Check if any OSS codes are configured
    $has_oss = db_fetch_array(db_select(
        "SELECT 1 FROM grupper WHERE art = 'SM' AND box6 IS NOT NULL AND box6 != '' LIMIT 1",
        __FILE__." linje ".__LINE__));
    if (!$has_oss) {
        print "<div style='padding:16px 12px; color:#c00;'>";
        print "<b>OSS-momskoder ikke konfigureret.</b><br>";
        print "Gaa til <a href='../systemdata/syssetup.php?valg=moms'>Indstillinger &rarr; Moms</a> ";
        print "og udfyld feltet <b>Land (OSS)</b> for de SM-momskoder, der vedrører salg til EU-privatpersoner.";
        print "</div>";
        if ($menu == 'T') {
            include_once '../includes/topmenu/footer.php';
        } else {
            include_once '../includes/oldDesign/footer.php';
        }
        fclose($csv);
        return;
    }

    // Check whether any OSS codes have Type configured (for info note)
    $has_type = db_fetch_array(db_select(
        "SELECT 1 FROM grupper WHERE art = 'SM' AND box6 IS NOT NULL AND box6 != '' AND box7 IS NOT NULL AND box7 != '' LIMIT 1",
        __FILE__." linje ".__LINE__));

    if (!$has_type) {
        print "<div style='padding:6px 12px 4px; color:#856404; background:#fff3cd; border-radius:4px; margin:0 12px 8px;'>";
        print "<b>Tip:</b> Feltet <b>Type</b> (varer/ydelser) er ikke udfyldt paa OSS-momskoderne. ";
        print "Udfyld det i <a href='../systemdata/syssetup.php?valg=moms'>Indstillinger &rarr; Moms</a> "
            . "for at faa opdelt OSS-angivelsen paa varer og ydelser.";
        print "</div>";
    }

    // Shared JOIN fragment used by both queries
    $oss_join = " JOIN kontoplan kp ON kp.kontonr = t.kontonr AND kp.regnskabsaar = '$regnaar'"
              . " JOIN ("
              .   " SELECT DISTINCT ON (art, kodenr) art, kodenr, beskrivelse, box2, box6, box7"
              .   " FROM grupper"
              .   " WHERE art = 'SM' AND box6 IS NOT NULL AND box6 != ''"
              .   " ORDER BY art, kodenr, fiscal_year DESC NULLS LAST"
              . " ) g ON g.art = UPPER(SUBSTRING(kp.moms FROM 1 FOR 1)) || 'M'"
              .   " AND CAST(g.kodenr AS TEXT) = SUBSTRING(kp.moms FROM 2)"
              . " WHERE t.transdate >= '$regnstart' AND t.transdate <= '$regnslut'"
              . " AND kp.moms IS NOT NULL AND kp.moms != ''"
              . " $dim";

    // ----------------------------------------------------------------
    // SECTION 1 — per-posting detail
    // ----------------------------------------------------------------
    $qtxt_detail = "SELECT"
        . " t.transdate AS dato,"
        . " t.bilag,"
        . " t.beskrivelse AS tekst,"
        . " kp.moms AS momskode,"
        . " g.beskrivelse AS kode_navn,"
        . " CAST(COALESCE(NULLIF(g.box2,''),NULL) AS NUMERIC(10,2)) AS momssats,"
        . " g.box6 AS land,"
        . " COALESCE(NULLIF(TRIM(g.box7),''), '') AS type,"
        . " (t.kredit - t.debet) AS beloeb,"
        . " -t.moms AS momsbeloeb,"
        . " (t.kredit - t.debet) - t.moms AS beloeb_inkl_moms"
        . " FROM transaktioner t"
        . $oss_join
        . " ORDER BY g.box6, t.transdate, t.bilag, t.id";

    $q_detail = db_select($qtxt_detail, __FILE__." linje ".__LINE__);

    $section_h = "style='margin:16px 12px 6px; font-size:1.05em; font-weight:bold; color:#334;'";

    print "<p $section_h>Alle posteringer</p>";
    print "<div style='overflow-x:auto; padding:0 12px;'>";
    print "<table class='dataTable' border='0' cellspacing='1' width='100%'>";
    print "<thead><tr style='background:#eeeef0;'>";
    print "<th align='left'  style='width:90px'>Dato</th>";
    print "<th align='left'  style='width:70px'>Bilag</th>";
    print "<th align='left'>Tekst</th>";
    print "<th align='left'  style='width:70px'>Momskode</th>";
    print "<th align='right' style='width:60px'>Sats %</th>";
    print "<th align='right' style='width:130px'>Beloeb</th>";
    print "<th align='right' style='width:130px'>Moms</th>";
    print "<th align='right' style='width:130px'>Beloeb inkl. moms</th>";
    print "</tr></thead><tbody>";

    fwrite($csv, mb_convert_encoding(
        "ALLE POSTERINGER\n"
        . "Dato;Bilag;Tekst;Momskode;Sats %;Beloeb;Moms;Beloeb inkl. moms\n",
        'ISO-8859-1', 'UTF-8'));

    $row_bg       = ['#ffffff','#f5f5f5'];
    $row_i        = 0;
    $has_rows     = false;
    $cur_land     = null;
    $land_base    = 0.0;
    $land_moms    = 0.0;
    $land_inkl    = 0.0;
    $grand_base   = 0.0;
    $grand_moms   = 0.0;
    $grand_inkl   = 0.0;

    while ($row = db_fetch_array($q_detail)) {
        $has_rows = true;
        $land     = $row['land'] ?? '';
        $base     = (float)$row['beloeb'];
        $moms     = (float)$row['momsbeloeb'];
        $inkl     = (float)$row['beloeb_inkl_moms'];
        $sats     = ($row['momssats'] !== null) ? (float)$row['momssats'] : 0;

        // Land subtotal break
        if ($cur_land !== null && $cur_land !== $land) {
            print "<tr style='background:#dce8f0; font-weight:bold;'>";
            print "<td colspan='5' align='right' style='padding-right:6px;'>Subtotal " . htmlspecialchars($cur_land) . "</td>";
            print "<td align='right'>" . dkdecimal($land_base, 2) . "</td>";
            print "<td align='right'>" . dkdecimal($land_moms, 2) . "</td>";
            print "<td align='right'>" . dkdecimal($land_inkl, 2) . "</td>";
            print "</tr>\n";
            $land_base = 0.0; $land_moms = 0.0; $land_inkl = 0.0;
            $row_i = 0;
        }

        // Land header row
        if ($cur_land !== $land) {
            print "<tr style='background:#c8d8e8;'>";
            print "<td colspan='8' style='padding:4px 8px; font-weight:bold;'>" . htmlspecialchars($land) . "</td>";
            print "</tr>\n";
            $cur_land = $land;
            fwrite($csv, mb_convert_encoding("\n" . $land . "\n", 'ISO-8859-1', 'UTF-8'));
        }

        $land_base  += $base;  $land_moms  += $moms;  $land_inkl  += $inkl;
        $grand_base += $base;  $grand_moms += $moms;  $grand_inkl += $inkl;

        $bg = $row_bg[$row_i % 2];
        $row_i++;

        print "<tr style='background:$bg;'>";
        print "<td>" . dkdato($row['dato']) . "</td>";
        print "<td>" . htmlspecialchars($row['bilag'] ?? '') . "</td>";
        print "<td>" . htmlspecialchars($row['tekst'] ?? '') . "</td>";
        print "<td style='font-size:0.85em;'>" . htmlspecialchars($row['momskode']) . "</td>";
        print "<td align='right'>" . ($sats > 0 ? dkdecimal($sats, 2) : '') . "</td>";
        print "<td align='right'>" . dkdecimal($base, 2) . "</td>";
        print "<td align='right'>" . dkdecimal($moms, 2) . "</td>";
        print "<td align='right'>" . dkdecimal($inkl, 2) . "</td>";
        print "</tr>\n";

        fwrite($csv, mb_convert_encoding(
            dkdato($row['dato']) . ";"
            . ($row['bilag'] ?? '') . ";"
            . ($row['tekst'] ?? '') . ";"
            . $row['momskode'] . ";"
            . ($sats > 0 ? dkdecimal($sats, 2) : '') . ";"
            . "\"" . dkdecimal($base, 2) . "\";"
            . "\"" . dkdecimal($moms, 2) . "\";"
            . "\"" . dkdecimal($inkl, 2) . "\"\n",
            'ISO-8859-1', 'UTF-8'));
    }

    if (!$has_rows) {
        print "<tr><td colspan='8' align='center' style='padding:16px; color:#888;'>"
            . "Ingen OSS B2C-posteringer i den valgte periode.</td></tr>";
    } else {
        // Last land subtotal
        print "<tr style='background:#dce8f0; font-weight:bold;'>";
        print "<td colspan='5' align='right' style='padding-right:6px;'>Subtotal " . htmlspecialchars($cur_land) . "</td>";
        print "<td align='right'>" . dkdecimal($land_base, 2) . "</td>";
        print "<td align='right'>" . dkdecimal($land_moms, 2) . "</td>";
        print "<td align='right'>" . dkdecimal($land_inkl, 2) . "</td>";
        print "</tr>\n";
        // Grand total
        print "<tr style='background:#c8d8e8; font-weight:bold;'>";
        print "<td colspan='5' align='right'>I alt</td>";
        print "<td align='right'>" . dkdecimal($grand_base, 2) . "</td>";
        print "<td align='right'>" . dkdecimal($grand_moms, 2) . "</td>";
        print "<td align='right'>" . dkdecimal($grand_inkl, 2) . "</td>";
        print "</tr>";
        fwrite($csv, mb_convert_encoding(
            "\nI alt;;;;;;\"" . dkdecimal($grand_base, 2) . "\";\"" . dkdecimal($grand_moms, 2) . "\";\"" . dkdecimal($grand_inkl, 2) . "\"\n",
            'ISO-8859-1', 'UTF-8'));
    }

    print "</tbody></table></div>";

    // ----------------------------------------------------------------
    // SECTION 2 — summary for OSS filing (per land × momssats)
    // ----------------------------------------------------------------
    $qtxt_sum = "SELECT"
        . " g.box6 AS land,"
        . " CAST(COALESCE(NULLIF(g.box2,''),NULL) AS NUMERIC(10,2)) AS momssats,"
        . " SUM(CASE WHEN LOWER(TRIM(g.box7)) LIKE 'var%' THEN (t.kredit - t.debet) ELSE 0 END) AS beloeb_varer,"
        . " SUM(CASE WHEN LOWER(TRIM(g.box7)) LIKE 'yd%' OR LOWER(TRIM(g.box7)) LIKE 'ser%'"
        .       " THEN (t.kredit - t.debet) ELSE 0 END) AS beloeb_ydelser,"
        . " SUM(t.kredit - t.debet) AS beloeb_total,"
        . " -SUM(t.moms) AS momsbeloeb"
        . " FROM transaktioner t"
        . $oss_join
        . " GROUP BY g.box6, g.box2"
        . " ORDER BY g.box6, g.box2";

    $q_sum = db_select($qtxt_sum, __FILE__." linje ".__LINE__);

    print "<p $section_h>Salg til private kunder i EU – Oversigt</p>";
    print "<div style='overflow-x:auto; padding:0 12px;'>";
    print "<table class='dataTable' border='0' cellspacing='1' width='100%'>";
    print "<thead><tr style='background:#eeeef0;'>";
    print "<th align='left'>Land</th>";
    print "<th align='right' style='width:60px'>Sats %</th>";
    print "<th align='right' style='width:160px'>Momspligtig beloeb – varer</th>";
    print "<th align='right' style='width:160px'>Momspligtig beloeb – ydelser</th>";
    print "<th align='right' style='width:160px'>Momspligtig beloeb i alt</th>";
    print "<th align='right' style='width:130px'>Momsbeloeb</th>";
    print "</tr></thead><tbody>";

    fwrite($csv, mb_convert_encoding(
        "\nSALG TIL PRIVATE KUNDER I EU – OVERSIGT\n"
        . "Land;Sats %;Momspligtig beloeb – varer;Momspligtig beloeb – ydelser;Momspligtig beloeb i alt;Momsbeloeb\n",
        'ISO-8859-1', 'UTF-8'));

    $row_i      = 0;
    $has_rows2  = false;
    $grand_vare = 0.0;
    $grand_ydel = 0.0;
    $grand_tot  = 0.0;
    $grand_moms = 0.0;

    while ($row = db_fetch_array($q_sum)) {
        $has_rows2    = true;
        $sats         = ($row['momssats'] !== null) ? (float)$row['momssats'] : 0;
        $bv           = (float)$row['beloeb_varer'];
        $by           = (float)$row['beloeb_ydelser'];
        $bt           = (float)$row['beloeb_total'];
        $bm           = (float)$row['momsbeloeb'];
        $grand_vare  += $bv;
        $grand_ydel  += $by;
        $grand_tot   += $bt;
        $grand_moms  += $bm;

        $bg = $row_bg[$row_i % 2];
        $row_i++;

        print "<tr style='background:$bg;'>";
        print "<td><b>" . htmlspecialchars($row['land'] ?? '') . "</b></td>";
        print "<td align='right'>" . ($sats > 0 ? dkdecimal($sats, 2) : '') . "</td>";
        print "<td align='right'>" . ($bv != 0 ? dkdecimal($bv, 2) : '<span style="color:#ccc">–</span>') . "</td>";
        print "<td align='right'>" . ($by != 0 ? dkdecimal($by, 2) : '<span style="color:#ccc">–</span>') . "</td>";
        print "<td align='right'>" . dkdecimal($bt, 2) . "</td>";
        print "<td align='right'>" . dkdecimal($bm, 2) . "</td>";
        print "</tr>\n";

        fwrite($csv, mb_convert_encoding(
            ($row['land'] ?? '') . ";"
            . ($sats > 0 ? dkdecimal($sats, 2) : '') . ";"
            . "\"" . dkdecimal($bv, 2) . "\";"
            . "\"" . dkdecimal($by, 2) . "\";"
            . "\"" . dkdecimal($bt, 2) . "\";"
            . "\"" . dkdecimal($bm, 2) . "\"\n",
            'ISO-8859-1', 'UTF-8'));
    }

    if (!$has_rows2) {
        print "<tr><td colspan='6' align='center' style='padding:16px; color:#888;'>"
            . "Ingen OSS B2C-posteringer i den valgte periode.</td></tr>";
    } else {
        print "<tr style='background:#c8d8e8; font-weight:bold;'>";
        print "<td colspan='2' align='right'>I alt</td>";
        print "<td align='right'>" . ($grand_vare != 0 ? dkdecimal($grand_vare, 2) : '<span style="color:#ccc">–</span>') . "</td>";
        print "<td align='right'>" . ($grand_ydel != 0 ? dkdecimal($grand_ydel, 2) : '<span style="color:#ccc">–</span>') . "</td>";
        print "<td align='right'>" . dkdecimal($grand_tot, 2) . "</td>";
        print "<td align='right'>" . dkdecimal($grand_moms, 2) . "</td>";
        print "</tr>";
        fwrite($csv, mb_convert_encoding(
            "I alt;;"
            . "\"" . dkdecimal($grand_vare, 2) . "\";"
            . "\"" . dkdecimal($grand_ydel, 2) . "\";"
            . "\"" . dkdecimal($grand_tot, 2) . "\";"
            . "\"" . dkdecimal($grand_moms, 2) . "\"\n",
            'ISO-8859-1', 'UTF-8'));
    }

    print "</tbody></table></div>";
    print "<div style='padding:8px 12px; color:#666; font-size:0.85em;'>"
        . "Beloeb ekskl. moms. OSS-koder konfigureres under "
        . "<a href='../systemdata/syssetup.php?valg=moms'>Indstillinger &rarr; Moms</a> "
        . "(felt: Land (OSS))."
        . "</div>";
    fclose($csv);

    if ($menu == 'T') {
        include_once '../includes/topmenu/footer.php';
    } else {
        include_once '../includes/oldDesign/footer.php';
    }
}
?>
