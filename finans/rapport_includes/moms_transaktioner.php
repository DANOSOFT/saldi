<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport_includes/moms_transaktioner.php --- patch 5.0.0 --- 2026-07-19 ---
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
// 20260716 MJ R1 – Posteringer pr. momskode: alle transaktioner med momskode-kolonne,
//                  subtotaler pr. kode og grand total. CSV-eksport.

function moms_transaktioner($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
                             $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart,
                             $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,
                             $simulering, $lagerbev) {

    global $db, $md, $menu, $sprog_id, $top_bund;
    global $buttonStyle, $topStyle;
    global $ansatte_id, $prj_navn_fra, $prj_navn_til;
    global $afd_navn, $ansatte;

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
        if ($maaned_til > $slutmaaned)   $aar_til = $aar_fra;
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

    $filter_kode = db_escape_string(trim(if_isset($_GET, NULL, 'filter_momskode')));

    $dim = '';
    if ($afd || $afd == '0' || $ansat_fra || $projekt_fra) {
        if ($afd || $afd == '0') $dim = "AND t.afd = $afd ";
        if ($ansat_fra && $ansat_til) {
            $tmp = str_replace(",", " or t.ansat=", $ansatte_id);
            $dim .= " AND (t.ansat=$tmp) ";
        } elseif ($ansat_fra) $dim .= "AND t.ansat = '$ansat_fra' ";
        $p_fra = str2low($projekt_fra);
        $p_til = str2low($projekt_til);
        if ($p_fra && $p_til && $p_fra != $p_til)
            $dim .= " AND lower(t.projekt) >= '$p_fra' AND lower(t.projekt) <= '$p_til' ";
        elseif ($p_fra) {
            $tmp = str_replace("?", "_", $p_fra);
            if (substr($tmp, -1) == '_') { while (substr($tmp, -1) == '_') $tmp = substr($tmp, 0, strlen($tmp)-1); $tmp = str2low($tmp).'%'; }
            $dim .= "AND lower(t.projekt) LIKE '$tmp' ";
        }
    }

    $konto_filter = '';
    if ($konto_fra && $konto_til && $konto_fra != $konto_til)
        $konto_filter = "AND t.kontonr >= '$konto_fra' AND t.kontonr <= '$konto_til' ";
    elseif ($konto_fra)
        $konto_filter = "AND t.kontonr = '$konto_fra' ";

    $kode_filter = ($filter_kode !== '') ? "AND COALESCE(kp.moms,'') = '$filter_kode' " : '';

    $csvfile = "../temp/$db/moms_transaktioner.csv";
    $csv     = fopen($csvfile, "w");

    $title    = 'Rapport • Posteringer pr. momskode';
    $back_url = "rapport.php?rapportart=moms_transaktioner&regnaar=$regnaar"
              . "&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra"
              . "&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til"
              . "&konto_fra=$konto_fra&konto_til=$konto_til"
              . "&ansat_fra=$ansat_fra&ansat_til=$ansat_til"
              . "&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til"
              . "&simulering=$simulering&lagerbev=$lagerbev";

    $csv_btn = "<a href='$csvfile' style='color:#ffffff; text-decoration:none;'>"
             . "<i class='fa fa-download'></i> CSV</a>";

    include("../includes/topline_settings.php");

    if ($menu == 'T') {
        include_once '../includes/top_header.php';
        include_once '../includes/top_menu.php';
        print "<div id=\"header\">";
        print "<div class=\"headerbtnLft headLink\"><a href=\"$back_url\" accesskey=\"L\"><i class='fa fa-close fa-lg'></i> Luk</a></div>";
        print "<div class=\"headerTxt\">Posteringer pr. momskode</div>";
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
        print "<td width='85%' align='center' style='$topStyle'>Posteringer pr. momskode</td>";
        print "<td width='10%' align='center' style='$buttonStyle'>$csv_btn</td>";
        print "</tbody></table></td></tr></tbody></table>";
    } else {
        print "<table width='100%' cellpadding='0' cellspacing='1px' border='0' valign='top' align='center'>";
        print "<tr><td colspan='6' height='8'>";
        print "<table width='100%' align='center' border='0' cellspacing='3' cellpadding='0'><tbody>";
        print "<td width='10%' $top_bund><a accesskey=L href='$back_url'>Luk</a></td>";
        print "<td width='80%' $top_bund>Rapport - Posteringer pr. momskode</td>";
        print "<td width='10%' $top_bund><a href='$csvfile'>CSV</a></td>";
        print "</tbody></table></td></tr></table>";
    }

    // --- company + period header ---
    $row = db_fetch_array(db_select("select firmanavn from adresser where art='S'", __FILE__." linje ".__LINE__));
    $firmanavn = $row ? $row['firmanavn'] : '';

    print "<div style='padding:8px 12px;'>";
    print "<big><b>$firmanavn</b></big><br>";
    print "Periode: " . dkdato($regnstart) . " – " . dkdato($regnslut);
    if ($konto_fra) print " &nbsp;|&nbsp; Konto: $konto_fra" . ($konto_til && $konto_til != $konto_fra ? "–$konto_til" : '');
    print "</div>";

    // Quarter shortcuts
    $q_base = "rapport.php?rapportart=moms_transaktioner&regnaar=$regnaar&dato_fra=01&dato_til=31"
            . "&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til"
            . "&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev";
    print "<div class='no-print' style='padding:2px 12px 6px; font-size:0.9em; color:#555;'>Genveje: ";
    foreach (['Q1'=>[1,3],'Q2'=>[4,6],'Q3'=>[7,9],'Q4'=>[10,12]] as $ql => $qm) {
        print "<a href='$q_base&maaned_fra={$qm[0]}&aar_fra=$startaar&maaned_til={$qm[1]}&aar_til=$startaar' style='margin-right:8px;'>$ql</a>";
    }
    print "<a href='$q_base&maaned_fra=$startmaaned&aar_fra=$startaar&maaned_til=$slutmaaned&aar_til=$slutaar'>Hele &aring;ret</a></div>";

    // --- VAT code filter dropdown ---
    $all_codes = [];
    $q = db_select("SELECT DISTINCT kp.moms, g.beskrivelse FROM kontoplan kp"
                 . " LEFT JOIN (SELECT DISTINCT ON (art,kodenr) art, kodenr, beskrivelse FROM grupper"
                 . "   WHERE art IN ('SM','KM','YM','EM') ORDER BY art, kodenr, fiscal_year DESC NULLS LAST) g"
                 . " ON g.art = UPPER(SUBSTRING(kp.moms FROM 1 FOR 1)) || 'M'"
                 . " AND CAST(g.kodenr AS TEXT) = SUBSTRING(kp.moms FROM 2)"
                 . " WHERE kp.regnskabsaar = '$regnaar' AND kp.moms IS NOT NULL AND kp.moms != ''"
                 . " ORDER BY kp.moms", __FILE__." linje ".__LINE__);
    while ($r = db_fetch_array($q)) $all_codes[$r['moms']] = $r['beskrivelse'];

    $filter_url_base = $back_url . ($filter_kode !== '' ? "&filter_momskode=$filter_kode" : '');

    print "<form method='GET' action='rapport.php' style='padding:4px 12px; display:inline-block;'>";
    // pass through all standard params as hidden fields
    foreach (['rapportart'=>'moms_transaktioner','regnaar'=>$regnaar,'dato_fra'=>$startdato,
              'maaned_fra'=>$mf,'aar_fra'=>$aar_fra,'dato_til'=>$slutdato,'maaned_til'=>$mt,
              'aar_til'=>$aar_til,'konto_fra'=>$konto_fra,'konto_til'=>$konto_til,
              'ansat_fra'=>$ansat_fra,'ansat_til'=>$ansat_til,'afd'=>$afd,
              'projekt_fra'=>$projekt_fra,'projekt_til'=>$projekt_til,
              'simulering'=>$simulering,'lagerbev'=>$lagerbev] as $k => $v) {
        print "<input type='hidden' name='" . htmlspecialchars($k) . "' value='" . htmlspecialchars($v) . "'>";
    }
    print "Momskode: <select name='filter_momskode'>";
    $sel_alle = ($filter_kode === '') ? " selected='selected'" : '';
    print "<option value=''$sel_alle>Alle</option>";
    foreach ($all_codes as $kode => $navn) {
        $sel = ($filter_kode === $kode) ? " selected='selected'" : '';
        $label = $kode . ($navn ? " – $navn" : '');
        print "<option value='" . htmlspecialchars($kode) . "'$sel>" . htmlspecialchars($label) . "</option>";
    }
    print "</select> <input type='submit' value='Vis'></form>";
    if ($filter_kode !== '') {
        print " &nbsp;<a href='$back_url'>Vis alle</a>";
    }

    // --- main query ---
    $qtxt = "SELECT"
          . " t.transdate, t.bilag, t.beskrivelse,"
          . " t.kontonr, kp.beskrivelse AS konto_navn,"
          . " COALESCE(kp.moms, '') AS momskode,"
          . " g.beskrivelse AS moms_navn,"
          . " CAST(COALESCE(NULLIF(g.box2,''),NULL) AS NUMERIC(10,2)) AS momssats,"
          . " (t.debet - t.kredit) AS beloeb,"
          . " t.moms AS momsbeloeb"
          . " FROM transaktioner t"
          . " LEFT JOIN kontoplan kp ON kp.kontonr = t.kontonr AND kp.regnskabsaar = '$regnaar'"
          . " LEFT JOIN ("
          .   " SELECT DISTINCT ON (art, kodenr) art, kodenr, beskrivelse, box2"
          .   " FROM grupper WHERE art IN ('SM','KM','YM','EM')"
          .   " ORDER BY art, kodenr, fiscal_year DESC NULLS LAST"
          . " ) g ON kp.moms IS NOT NULL AND kp.moms != ''"
          .   " AND g.art = UPPER(SUBSTRING(kp.moms FROM 1 FOR 1)) || 'M'"
          .   " AND CAST(g.kodenr AS TEXT) = SUBSTRING(kp.moms FROM 2)"
          . " WHERE t.transdate >= '$regnstart' AND t.transdate <= '$regnslut'"
          . " $konto_filter $kode_filter $dim"
          . " ORDER BY COALESCE(kp.moms,'ZZZ') NULLS LAST, t.transdate, t.bilag, t.id";

    $q = db_select($qtxt, __FILE__." linje ".__LINE__);

    // --- HTML table ---
    print "<div style='overflow-x:auto; padding:0 12px;'>";
    print "<table class='dataTable' border='0' cellspacing='1' width='100%'>";
    print "<thead><tr style='position:sticky; top:0; background:#eeeef0;'>";
    print "<th align='left'  style='width:80px'>Dato</th>";
    print "<th align='left'  style='width:70px'>Bilag</th>";
    print "<th align='left'>Tekst</th>";
    print "<th align='left'  style='width:120px'>Konto</th>";
    print "<th align='left'  style='width:70px'>Momskode</th>";
    print "<th align='right' style='width:50px'>Sats %</th>";
    print "<th align='right' style='width:110px'>Bel&oslash;b</th>";
    print "<th align='right' style='width:110px'>Moms</th>";
    print "<th align='right' style='width:110px'>Inkl. moms</th>";
    print "</tr></thead><tbody>";

    fwrite($csv, mb_convert_encoding(
        "Dato;Bilag;Tekst;Konto;Momskode;Sats %;Beloeb;Moms;Inkl. moms\n",
        'ISO-8859-1', 'UTF-8'));

    $prev_kode = null;
    $sub_beloeb = 0; $sub_moms = 0;
    $tot_beloeb = 0; $tot_moms  = 0;
    $row_bg = ['#ffffff','#f5f5f5'];
    $row_i  = 0;

    while ($row = db_fetch_array($q)) {
        $kode    = $row['momskode'];
        $beloeb  = (float)$row['beloeb'];
        $moms    = (float)$row['momsbeloeb'];
        $inkl    = $beloeb + $moms;

        // subtotal break
        if ($prev_kode !== null && $kode !== $prev_kode) {
            $sub_inkl = $sub_beloeb + $sub_moms;
            $label    = ($prev_kode !== '') ? "Subtotal $prev_kode" : 'Subtotal – ingen momskode';
            print "<tr style='background:#e0e8f0; font-weight:bold;'>";
            print "<td colspan='6' align='right'>$label</td>";
            print "<td align='right'>" . dkdecimal($sub_beloeb, 2) . "</td>";
            print "<td align='right'>" . dkdecimal($sub_moms,   2) . "</td>";
            print "<td align='right'>" . dkdecimal($sub_inkl,   2) . "</td>";
            print "</tr>";
            $sub_beloeb = 0; $sub_moms = 0;
        }

        $prev_kode   = $kode;
        $sub_beloeb += $beloeb;
        $sub_moms   += $moms;
        $tot_beloeb += $beloeb;
        $tot_moms   += $moms;

        $bg   = $row_bg[$row_i % 2];
        $row_i++;

        $sats_txt = ($row['momssats'] !== null) ? dkdecimal($row['momssats'], 2) : '';
        $kode_vis = ($kode !== '') ? htmlspecialchars($kode) : '<span style="color:#aaa">–</span>';
        $konto_vis = $row['kontonr'] ? ($row['kontonr'] . ': ' . htmlspecialchars($row['konto_navn'] ?? '')) : '';

        print "<tr style='background:$bg;'>";
        print "<td>" . dkdato($row['transdate']) . "</td>";
        print "<td>" . htmlspecialchars($row['bilag']) . "</td>";
        print "<td>" . htmlspecialchars($row['beskrivelse']) . "</td>";
        print "<td style='font-size:0.85em;'>" . htmlspecialchars($konto_vis) . "</td>";
        print "<td>" . $kode_vis . "</td>";
        print "<td align='right'>" . $sats_txt . "</td>";
        print "<td align='right'>" . dkdecimal($beloeb, 2) . "</td>";
        print "<td align='right'>" . dkdecimal($moms,   2) . "</td>";
        print "<td align='right'>" . dkdecimal($inkl,   2) . "</td>";
        print "</tr>\n";

        fwrite($csv, mb_convert_encoding(
            dkdato($row['transdate']) . ";$row[bilag];"
            . $row['beskrivelse'] . ";"
            . $row['kontonr'] . ";"
            . $kode . ";"
            . $sats_txt . ";"
            . "\"" . dkdecimal($beloeb, 2) . "\";"
            . "\"" . dkdecimal($moms,   2) . "\";"
            . "\"" . dkdecimal($inkl,   2) . "\"\n",
            'ISO-8859-1', 'UTF-8'));
    }

    // last subtotal
    if ($prev_kode !== null) {
        $sub_inkl = $sub_beloeb + $sub_moms;
        $label    = ($prev_kode !== '') ? "Subtotal $prev_kode" : 'Subtotal – ingen momskode';
        print "<tr style='background:#e0e8f0; font-weight:bold;'>";
        print "<td colspan='6' align='right'>$label</td>";
        print "<td align='right'>" . dkdecimal($sub_beloeb, 2) . "</td>";
        print "<td align='right'>" . dkdecimal($sub_moms,   2) . "</td>";
        print "<td align='right'>" . dkdecimal($sub_inkl,   2) . "</td>";
        print "</tr>";
    }

    // grand total
    $tot_inkl = $tot_beloeb + $tot_moms;
    print "<tr style='background:#c8d8e8; font-weight:bold; border-top:2px solid #666;'>";
    print "<td colspan='6' align='right'>I alt</td>";
    print "<td align='right'>" . dkdecimal($tot_beloeb, 2) . "</td>";
    print "<td align='right'>" . dkdecimal($tot_moms,   2) . "</td>";
    print "<td align='right'>" . dkdecimal($tot_inkl,   2) . "</td>";
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
