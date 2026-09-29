<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport_includes/kontokort.php-----patch 5.0.0 ----2026-04-30----- 
//                           LICENSE
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20190924 PHR Added option 'Poster uden afd". when "afdelinger" is used. $afd='0'
// 20210107 PHR Corrected error in 'deferred financial year'.
// 20210125 PHR added csv option.
// 20210211 PHR some cleanup
// 20210301 PHR error in csv.
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20260309 LOE Fixed execessive error logging relating to undefined array keys.
// 20260430 LOE Updated the top menu and made the report header sticky when scrolling.
// 20260901 CL/LAH Fixed Saldo column showing the same value on every line:
//                  running balance was only accumulated for rows skipped by
//                  pagination, never for the printed rows.
// 20260911 MJ SST-769 Rate-adjustment rows (valuta = -1) showed 0,00 in debet/kredit while
//                  still moving the balance, because the DKK amount only went into the cell title.
//                  Show it as a labelled DKK figure so currency accounts can be reconciled.
// 20260915 CDX/PHR Include simulated rows in pagination and keep merged row metadata aligned.
// 20260929 MJ SST-809 Print did not match the screen. Four causes, all of them
//                  pagination leaking into the printout:
//                  1) The rows were counted with a COUNT(*) of their own and
//                     printed from a list built separately, so anything merged
//                     into that list afterwards was uncounted - stock rows from
//                     Lagerbevaegelser always, and zero-amount rows, which the
//                     count excluded but the renderer printed because
//                     pg_fetch_array() returns numeric(15,3) as "0.000" and PHP
//                     reads that string as true. Build once, count the built
//                     rows, print the built rows.
//                  2) An account with an opening balance but no movements in the
//                     period was dropped from every page: the skip test
//                     rows_to_skip >= acct_cnt holds when both are zero.
//                  3) No way to print more than the loaded page. Added
//                     udskriv=on, which renders the whole report in one pass.
//                  4) The printout used the screen's scroll container, sticky
//                     header and fixed pagination bar - hence "only page 1", the
//                     meaningless footer and its clipped last line. @media print
//                     releases the container and hides the bar; the footer moved
//                     into a tfoot, which browsers repeat on every printed page.
//                  Column widths are now declared, and the date column is
//                  nowrap, so a posting cannot break over two lines.
//                  Texts 5325-5326 added to importfiler/tekster.csv.

function kontokort($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
                   $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart,
                   $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,
                   $simulering, $lagerbev, $page = 1, $per_page = 50, $udskriv = false){

	global $afd_navn, $ansatte, $ansatte_id;
	global $bgcolor, $bgcolor4, $bgcolor5;
	global $connection, $csv;
	global $db;
	global $md, $menu;
	global $prj_navn_fra, $prj_navn_til;
	global $top_bund;
	global $sprog_id;

	// SST-809 The screen's pagination is a navigation aid. A browser print of
	// the screen can never contain more than the rows loaded for the current
	// page, which is why the customer's printout stopped after page 1 and why
	// printing from page 2 showed something else again. "Udskriv alle linjer"
	// renders the report in one pass instead, so the printout is the whole
	// kontokort regardless of where the user was on screen.
	$udskriv = $udskriv ? true : false;
	if ($udskriv) {
		$page     = 1;
		$per_page = 1000000000;
	}

	$query = db_select("select firmanavn, cvrnr from adresser where art='S'", __FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query))
		$firmanavn = $row['firmanavn'];
		$vatNo     = $row['cvrnr'];
		$regnaar = (int)$regnaar; #fordi den er i tekstformat og skal vaere numerisk

	#	list ($aar_fra, $maaned_fra) = explode(" ", $maaned_fra);
#	list ($aar_til, $maaned_til) = explode(" ", $maaned_til);

	$maaned_fra = trim($maaned_fra);
	$maaned_til = trim($maaned_til);
	$aar_fra = trim($aar_fra);
	$aar_til = trim($aar_til);

	$konto_fra = trim($konto_fra);
	$konto_til = trim($konto_til);

	$mf = (int)$maaned_fra;
	$mt = (int)$maaned_til;
	if ($mf < 10) $mf = '0'.$mf;
	if ($mt < 10) $mt = '0'.$mt;

	for ($x = 1; $x <= 12; $x++) {
		if ($maaned_fra == $md[$x]) {
			$maaned_fra = $x;
		}
		if ($maaned_til == $md[$x]) {
			$maaned_til = $x;
		}
		if (strlen($maaned_fra) == 1) {
			$maaned_fra = "0" . $maaned_fra;
		}
		if (strlen($maaned_til) == 1) {
			$maaned_til = "0" . $maaned_til;
		}
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'", __FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	#	$regnaar=$row[kodenr];
	$startmaaned = $row['box1'] * 1;
	$startaar = $row['box2'] * 1;
	$slutmaaned = $row['box3'] * 1;
	$slutaar = $row['box4'] * 1;
	$slutdato = 31;

	if ($aar_fra < $aar_til) { #20210107
		if ($maaned_til > $slutmaaned)
			$aar_til = $aar_fra;
		elseif ($maaned_fra < $startmaaned)
			$aar_fra = $aar_til;
	}
	$regnaarstart = $startaar . "-" . $startmaaned . "-" . '01';

	($startaar >= '2015') ? $aut_lager = 'on' : $aut_lager = NULL;

	if ($aut_lager && $lagerbev) {
		$x = 0;
		$varekob = array();
		$q = db_select("select box1,box2,box3 from grupper where art = 'VG' and box8 = 'on'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($r['box1'] && $r['box2'] && !in_array($r['box3'], $varekob)) {
				$varelager_i[$x] = $r['box1'];
				$varelager_u[$x] = $r['box2'];
				$varekob[$x] = $r['box3'];
				$x++;
			}
		}
		$q = db_select("select box1,box2,box11 from grupper where art = 'VG' and box8 = 'on' and box11 != ''", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($r['box1'] && $r['box2'] && !in_array($r['box11'], $varekob)) {
				$varelager_i[$x] = $r['box1'];
				$varelager_u[$x] = $r['box2'];
				$varekob[$x] = $r['box11'];
				$x++;
			}
		}
		$q = db_select("select box1,box2,box13 from grupper where art = 'VG' and box8 = 'on' and box13 != ''", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($r['box1'] && $r['box2'] && !in_array($r['box13'], $varekob)) {
				$varelager_i[$x] = $r['box1'];
				$varelager_u[$x] = $r['box2'];
				$varekob[$x] = $r['box13'];
				$x++;
			}
		}
	}



	if ($aar_fra)
		$startaar = $aar_fra;
	if ($aar_til)
		$slutaar = $aar_til;
	if ($maaned_fra)
		$startmaaned = $maaned_fra;
	if ($maaned_til)
		$slutmaaned = $maaned_til;
	if ($dato_fra)
		$startdato = $dato_fra;
	if ($dato_til)
		$slutdato = $dato_til;

	$startdato *= 1;
	if ($startdato < 10)
		$startdato = '0' . $startdato;

	while (!checkdate($startmaaned, $startdato, $startaar)) {
		$startdato = $startdato - 1;
		if ($startdato < 28)
			break 1;
	}

	while (!checkdate($slutmaaned, $slutdato, $slutaar)) {
		$slutdato = $slutdato - 1;
		if ($slutdato < 28)
			break 1;
	}

	$regnstart = $startaar . "-" . $startmaaned . "-" . $startdato;
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

	$title = "Rapport • Kontokort";

	include("../includes/topline_settings.php");
#print "<div style=\"position: sticky; top: 0; z-index: 100; background-color: white;\">";

	#	print "  <a accesskey=L href=\"rapport.php?rapportart=Kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&afd=$afd\">Luk</a><br><br>";
	$csvfile = "../temp/$db/rapport.csv";
	$csv = fopen($csvfile, "w");

	// SST-809 "Udskriv" opens the same report un-paginated, in a popup, the way
	// the debitor/kreditor kontoudtog does. Same selection, same renderer - so
	// the printout cannot drift from the screen the way a separate print-only
	// page would.
	$udskrivUrl = "kontokort_standalone.php?" . http_build_query(array(
		'regnaar'     => $regnaar,
		'maaned_fra'  => $maaned_fra,
		'maaned_til'  => $maaned_til,
		'aar_fra'     => $aar_fra,
		'aar_til'     => $aar_til,
		'dato_fra'    => $dato_fra,
		'dato_til'    => $dato_til,
		'konto_fra'   => $konto_fra,
		'konto_til'   => $konto_til,
		'rapportart'  => $rapportart,
		'ansat_fra'   => $ansat_fra,
		'ansat_til'   => $ansat_til,
		'afd'         => $afd,
		'projekt_fra' => $projekt_fra,
		'projekt_til' => $projekt_til,
		'simulering'  => $simulering,
		'lagerbev'    => $lagerbev,
		'udskriv'     => 'on',
	));
	$udskrivJs  = "window.open('" . htmlspecialchars($udskrivUrl, ENT_QUOTES, 'UTF-8')
	            . "','kontokortprint','width=1000,height=700,scrollbars=yes,resizable=yes')";
	$txtUdskriv = findtekst('880|Udskriv', $sprog_id);
	// SST-809 One title for both the screen heading and the print footer. They were
	// separate - a hard-coded Danish heading and a findtekst() footer - so on any other
	// language the printout disagreed with the screen it was printed from.
	$rapportTitel = $simulering
		? findtekst('2175|Simuleret kontokort', $sprog_id)
		: findtekst('133|Kontokort', $sprog_id);
	$titUdskriv = htmlspecialchars(findtekst('5325|Udskriv alle linjer', $sprog_id), ENT_QUOTES, 'UTF-8');

	if ($menu == 'T') {
		$leftbutton = "<a title=\"Klik her for at komme til forsiden af rapporter\" href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\" accesskey=\"L\"><i class='fa fa-close fa-lg'></i> &nbsp;Luk</a>";
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">";
		print "<div class=\"headerbtnLft headLink\">$leftbutton</div>";
		print "<div class=\"headerTxt\">$title</div>";
		$rightbutton = $udskriv ? "&nbsp;&nbsp;&nbsp;"
			: "<a href=\"javascript:void(0);\" onclick=\"$udskrivJs\" title=\"$titUdskriv\"><i class='fa fa-print fa-lg'></i> $txtUdskriv</a>";
		print "<div class=\"headerbtnRght headLink\">$rightbutton</div>";
		print "</div>";
		print "<div class='content-noside'>";
	} elseif ($menu == 'S') {
		
		#########
		$tilbage_icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
		#########
		print "<table bgcolor='#eeeef0' width = 100% cellpadding='0' cellspacing='0' border='0' id='tableA'><tbody>";
		print "<tr><td colspan=8 align=center>";
		print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody>";

		print "<td width=\"5%\">$color
			<a href=\"javascript:confirmClose('rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev','')\" accesskey=L>
			   <button class='headerbtn' type='button' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
		print "$tilbage_icon" .findtekst('30|Tilbage', $sprog_id)."</button></a></td>";

		print "<td width='70%' align='center' style='$topStyle'>".findtekst('2173|Rapport - kontokort', $sprog_id)."</td>\n";
		print "<td width='5%' align='center' style='$buttonStyle'><a href='$csvfile' style='color:#ffffff'>csv</a></td>\n";
		if (!$udskriv)
			print "<td width='5%' align='center' style='$buttonStyle'><a href=\"javascript:void(0);\" onclick=\"$udskrivJs\" title=\"$titUdskriv\" style='color:#ffffff'>$txtUdskriv</a></td>\n";

		print "</tbody></table>";
		print "</td></tr>";
		$tmp = $rapportTitel;
		print "<tr><td colspan='4'><big><big><big>  $tmp</big></big></big></td>";
		print "<td colspan=6 align=right>";
		#######################
		
		?>
			<style>
			/* Existing styles for buttons */
			.headerbtn, .center-btn {
				display: flex;
				align-items: center;
				text-decoration: none;
				gap: 5px;
			}
			a:link{
					text-decoration: none;
				}

			</style>
		<?php

		#######################
	} else {
		print "<table width=100% cellpadding=\"0\" cellspacing=\"1px\" border=\"0\" valign = \"top\" align='center' id='tableTop'> ";
		print "<tr><td colspan=\"6\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\">".findtekst('2172|Luk', $sprog_id)."</a></td>";
		print "<td width=\"70%\" $top_bund>".findtekst('2173|Rapport - kontokort', $sprog_id)."</td>";
		print "<td width=\"10%\" $top_bund><a href='$csvfile'>csv</a></td>";
		if (!$udskriv)
			print "<td width=\"10%\" $top_bund><a href=\"javascript:void(0);\" onclick=\"$udskrivJs\" title=\"$titUdskriv\">$txtUdskriv</a></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>";
		$tmp = $rapportTitel;
		print "<tr><td colspan=\"4\"><big><big><big>  $tmp</big></big></big></td>";
		#		fwrite($csv,"$tmp;");
		print "<td colspan=6 align=right>";
	}
	
	$dim = '';
	if ($afd || $afd == '0' || $ansat_fra || $projekt_fra) {
		if ($afd || $afd == '0')
			$dim = "and afd = $afd ";
		if ($ansat_fra && $ansat_til) {
			$tmp = str_replace(",", " or ansat=", $ansatte_id);
			$dim = $dim . " and (ansat=$tmp) ";
		} elseif ($ansat_fra)
			$dim = $dim . "and ansat = '$ansat_fra' ";
		$projekt_fra = str2low($projekt_fra);
		$projekt_til = str2low($projekt_til);
		if ($projekt_fra && $projekt_til && $projekt_fra != $projekt_til)
			$dim = $dim . " and lower(projekt) >= '$projekt_fra' and lower(projekt) <= '$projekt_til' ";
		elseif ($projekt_fra) {
			$tmp = str_replace("?", "_", $projekt_fra);
			if (substr($tmp, -1) == '_') {
				while (substr($tmp, -1) == '_')
					$tmp = substr($tmp, 0, strlen($tmp) - 1);
				$tmp = str2low($tmp) . "%";
			}
			$dim = $dim . "and lower(projekt) LIKE '$tmp' ";
		}
	}
	$x = 0;
	$valdate = array();
	$valkode = array();
	$q = db_select("select * from valuta order by gruppe,valdate desc", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$y = $x - 1;
		//compare with y, already set.
		if ((!$x) || $r['gruppe'] != ($valkode[$y] ?? null) || ($valdate[$y] ?? null) >= $regnstart) {
			$valkode[$x] = $r['gruppe'];
			$valkurs[$x] = $r['kurs'];
			$valdate[$x] = $r['valdate'];
			$x++;
		}
	}

	$x = 0;
	$kontonr = array();
	$qtxt = "select * from kontoplan where regnskabsaar='$regnaar' and kontonr>='$konto_fra' and kontonr<='$konto_til' order by kontonr";
	$q = db_select("$qtxt", __FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)) {
		$kontonr[$x] = $row['kontonr'] * 1;
		$kontobeskrivelse[$x] = $row['beskrivelse'];
		$kontotype[$x] = $row['kontotype'];
		$kontomoms[$x] = $row['moms'];
		$kontovaluta[$x] = $row['valuta'];
		$kontokurs[$x] = $row['valutakurs'];
		if (!$dim && $kontotype[$x] == "S")
			$primo[$x] = afrund($row['primo'], 2);
		else
			$primo[$x] = 0;
		if ($primo[$x] && $kontovaluta[$x]) {
			for ($y = 0; $y <= count($valkode); $y++) {
				if ($valkode[$y] == $kontovaluta[$x] && $valdate[$y] <= $regnstart) {
					$primokurs[$x] = $valkurs[$y];
					break 1;
				}
			}
		} else
			$primokurs[$x] = 100;
		$x++;
	}

	$ktonr = array();
	$x = 0;
	$qtxt = "select distinct(kontonr) as kontonr from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr>='$konto_fra' and kontonr<='$konto_til' $dim";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ktonr[$x] = $r['kontonr'];
		$x++;
	}
	if ($simulering) {
		$qtxt = "select distinct(kontonr) as kontonr from simulering where transdate>='$regnstart' and transdate<='$regnslut' and kontonr>='$konto_fra' and kontonr<='$konto_til' $dim";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['kontonr'], $ktonr)) {
				$ktonr[$x] = $r['kontonr'];
				$x++;
			}
		}
	}
	if ($aut_lager && $lagerbev) {
		for ($i = 0; $i < count($varekob); $i++) {
			if (!in_array($varekob[$i], $ktonr)) {
				$ktonr[$x] = $varekob[$i];
				$x++;
			}
		}
		for ($i = 0; $i < count($varelager_i); $i++) {
			if (!in_array($varelager_i[$i], $ktonr)) {
				$ktonr[$x] = $varelager_i[$i];
				$x++;
			}
		}
		for ($i = 0; $i < count($varelager_u); $i++) {
			if (!in_array($varelager_u[$i], $ktonr)) {
				$ktonr[$x] = $varelager_u[$i];
				$x++;
			}
		}
	}

	sort($kontonr);
	$kontosum = 0;
	$founddate = false;
	######
	// Open sticky wrapper for the top section 
print "<div style=\"position: sticky; top: 0; z-index: 100; \">";

print "<table style='width:100%;' class='dataTable1' border='0' cellspacing='1' cellpadding='1'>";
print "<tr>";
print "<td style='width:70%;'></td>"; 
print "<th style='text-align:right; white-space:nowrap;'>
        <b>Regnskabs&aring;r</b> $regnaar
      </th>";
print "</tr>";

/* DATA ROW */
print "<tr>";
if ($startdato < 10) $startdato = "0" . (int)$startdato;

print "<td></td>"; 

print "<td style='text-align:right; white-space:nowrap;'>
        $startdato/$mf $startaar - $slutdato/$mt $slutaar
      </td>";

print "</tr>";
print "</table>";
if ($csv) fwrite($csv, ";; $startdato / $mf - $slutdato / $mt\n");
if ($ansat_fra) {
    if (!$ansat_til || $ansat_fra == $ansat_til)
        print "<tr><td>Medarbejder</td><td>$ansatte</td></tr>";
    else
        print "<tr><td>Medarbejdere</td><td>$ansatte</td></tr>";
}
if ($afd || $afd == '0')
    print "<tr><td>Afdeling</td><td>$afd_navn</td></tr>";
if ($projekt_fra) {
    print "<td>Projekt:</td><td>";
    if (!strstr($projekt_fra, "?")) {
        if ($projekt_til && $projekt_fra != $projekt_til)
            print "Fra: $projekt_fra, $prj_navn_fra<br>Til : $projekt_til, $prj_navn_til";
        else
            print "$projekt_fra, $prj_navn_fra";
    } else
        print "$projekt_fra, $prj_navn_fra";
    print "</td></tr>";
}
if ($menu != 'T') print "</tbody></table>";
print "<tr><td colspan=5><big><b>cvr: $vatNo | $firmanavn</b></big></td></tr>";
print "</tbody></table>";   // close the info table

// Close the sticky wrapper 
print "</div>";

// scrollable data table with sticky thead 



	######
// SST-809 The scroll container and the sticky thead are screen affordances.
// Browsers routinely clip a scrollable element to its visible box when
// printing, so the print view does not use one at all; @media print releases it
// for a plain Ctrl+P of the paginated screen.
if ($udskriv)
	print "<div class=\"kontokort-data\">";
else
	print "<div class=\"kontokort-data\" style=\"overflow-y: auto; max-height: calc(100vh - 140px);\">";
print "<table style=\"width:100%; border-collapse:collapse;\" class='dataTable' id='datapg'>";

// Sticky header
if ($udskriv)
	print "<thead>";
else
	print "<thead style=\"position: sticky; top: 0; background-color: #eeeef0; z-index: 10;\">";
print "<tr>";
// SST-809 .dataTable is table-layout:fixed, and with no width on any column the
// six share the width equally. On paper, where the page is narrower than the
// screen, a sixth was not enough for a dd-mm-yyyy date and every line wrapped
// onto two. Give the columns explicit widths so the date column's share does
// not depend on the medium.
print "<th style=\"text-align:left; padding:8px 4px; width:11%; white-space:nowrap;\"><b>" . findtekst('438|Dato', $sprog_id) . "</b></th>";
print "<th style=\"text-align:left; padding:8px 4px; width:9%;\"><b>" . findtekst('671|Bilag', $sprog_id) . "</b></th>";
print "<th style=\"text-align:left; padding:8px 4px; width:44%;\"><b>" . findtekst('1163|Tekst', $sprog_id) . "</b></th>";
print "<th style=\"text-align:right; padding:8px 4px; width:12%;\"><b>" . findtekst('1000|Debet', $sprog_id) . "</b></th>";
print "<th style=\"text-align:right; padding:8px 4px; width:12%;\"><b>" . findtekst('1001|Kredit', $sprog_id) . "</b></th>";
print "<th style=\"text-align:right; padding:8px 4px; width:12%;\"><b>" . findtekst('1073|Saldo', $sprog_id) . "</b></th>";
print "</tr>";
print "</thead>";

// Data body
print "<tbody>";
	fwrite($csv, "\"Dato\";\"Bilag\";\"Tekst\";\"Debet\";\"Kredit\";\"Saldo\"\n");
	####
	// SST-809 The report used to size its pages with a COUNT(*) of its own, and
	// then print from a row list assembled separately further down. Anything
	// merged into that list afterwards was invisible to the count: simulated
	// entries (counted since #627) and the synthetic stock rows from
	// Lagerbevaegelser (never counted at all). rows_to_skip therefore described
	// a shorter report than the one being printed, so every page boundary after
	// the first injected row landed in the wrong place - which is why page 2 of
	// a simulated kontokort opened mid-account, on rows that were not where the
	// user left off. The rows are now built once below, counted from the built
	// list, and printed from that same list, so the count and the page cannot
	// describe different reports.
	$total_rows     = 0;
	$accountRows    = array();
	$accountRowList = array();
	$accountOnPage  = array();
	$accountOffset = array();
	$accountPrimo   = array();
	$windowFrom     = ($page - 1) * $per_page;
	$windowTo       = $windowFrom + $per_page;

	for ($x = 0; $x < count($kontonr); $x++) {
		if (in_array($kontonr[$x], $ktonr) || $primo[$x]) {
			$kontosum = $primo[$x];
			$query = db_select("select debet, kredit from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' $dim order by transdate,pos,bilag,id", __FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				$kontosum = $kontosum + afrund($row['debet'], 2) - afrund($row['kredit'], 2);
			}
			$query = db_select("select debet, kredit from simulering where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' $dim order by transdate,bilag,id", __FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				$kontosum = $kontosum + afrund($row['debet'], 2) - afrund($row['kredit'], 2);
			}
			$accountPrimo[$x] = $kontosum;
			$print = 1;
			$tr = 0;
			$transdate = array();
			// $qtxt = "select * from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnstart' and transdate<='$regnslut' $dim ";
			// $qtxt .= "order by transdate,pos,bilag,id";
			// $query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			#########
			$qtxt  = "SELECT * FROM transaktioner 
           WHERE kontonr=$kontonr[$x] 
             AND transdate>='$regnstart' AND transdate<='$regnslut' $dim ";
			$qtxt .= "ORDER BY transdate, pos, bilag, id";
			// No LIMIT — fetch all rows so kontosum stays accurate across pages
			$query = db_select($qtxt, __FILE__ . " linje " . __LINE__); 
			###########
			while ($row = db_fetch_array($query)) {
				$transdate[$tr] = $row['transdate'];
				$bilag[$tr] = $row['bilag'];
				$kladde_id[$tr] = $row['kladde_id'];
				$beskrivelse[$tr] = $row['beskrivelse'];
				$debet[$tr] = $row['debet'];
				$kredit[$tr] = $row['kredit'];
				$transvaluta[$tr] = $row['valuta'];
				if ($kontovaluta[$x]) {
					for ($y = 0; $y <= count($valkode); $y++) {
						if ($valkode[$y] == $kontovaluta[$x] && $valdate[$y] <= $transdate[$tr]) {
							$transkurs[$tr] = $valkurs[$y];
							break 1;
						}
					}
				} else
					$transkurs[$tr] = 100;
				$tr++;
			}

			if ($lagerbev && $aut_lager && (in_array($kontonr[$x], $varekob) || in_array($kontonr[$x], $varelager_i) || in_array($kontonr[$x], $varelager_u))) {
				$z = 0;
				$lager = array();
				$gruppe = array();
				$q = db_select("select kodenr,box1,box2 from grupper where art = 'VG' and box8 = 'on' and (box1 = '$kontonr[$x]' or box2 = '$kontonr[$x]' or box3 = '$kontonr[$x]' or box11 = '$kontonr[$x]' or box13 = '$kontonr[$x]')", __FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					if ($r['box1']) {
						#						$lager_i[$z]=$r['box1'];
#						$lager_u[$z]=$r['box2'];
						$gruppe[$z] = $r['kodenr'];
						$z++;
					}
				}
				$y = 0;
				$vare_id = array();
				for ($z = 0; $z < count($gruppe); $z++) {
					$q = db_select("select id,kostpris from varer where gruppe = '$gruppe[$z]' order by id", __FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$vare_id[$y] = $r['id'];
						$kostpris[$y] = $r['kostpris'];
						$y++;
					}
				}
				$z = -1;
				$kobsdate = array();
				$kobsdebet = array();
				$kobskredit = array();
				$q = db_select("select vare_id,ordre_id,antal,kobsdate from batch_kob where kobsdate >= '$regnstart' and kobsdate <= '$regnslut' order by kobsdate,vare_id", __FILE__ . " linje " . __LINE__); #20170516
				while ($r = db_fetch_array($q)) {
					if ($z >= 0 && isset($kobsdate[$z]) && $r['kobsdate'] == $kobsdate[$z] && $r['ordre_id'] && $r['ordre_id'] == $soid[$z]) {
						for ($y = 0; $y < count($vare_id); $y++) {
							if ($r['vare_id'] == $vare_id[$y]) {
								if ($kontotype[$x] == 'D') {
									if ($r['antal'] > 0)
										$kobskredit[$z] += $r['antal'] * $kostpris[$y];
									else
										$kobsdebet[$z] -= $r['antal'] * $kostpris[$y];
								} elseif (in_array($kontonr[$x], $varelager_i)) {
									if ($r['antal'] > 0)
										$kobsdebet[$z] += $r['antal'] * $kostpris[$y];
									else
										$kobskredit[$z] -= $r['antal'] * $kostpris[$y];
								}
							}
						}
					} else {
						for ($y = 0; $y < count($vare_id); $y++) {
							if ($r['vare_id'] == $vare_id[$y]) {
								if ($kontotype[$x] == 'D') {
									$z++;
									$koid[$z] = $r['ordre_id'];
									if (isset($koid[$z - 1]) && $koid[$z] == $koid[$z - 1])
										$kobsfakt[$z] = $kobsfakt[$z - 1];
									else {
										$r2 = db_fetch_array(db_select("select fakturanr from ordrer where id='$koid[$z]'", __FILE__ . " linje " . __LINE__));
										$kobsfakt[$z] = $r2['fakturanr'];
									}
									$kobsdate[$z] = $r['kobsdate'];
									if ($r['antal'] > 0) {
										$kobskredit[$z] = $r['antal'] * $kostpris[$y];
										$kobsdebet[$z] = 0;
									} else {
										$kobsdebet[$z] = $r['antal'] * $kostpris[$y] * -1;
										$kobskredit[$z] = 0;
									}
									#									$z++;
								} elseif (in_array($kontonr[$x], $varelager_i)) {
									$z++;
									$koid[$z] = $r['ordre_id'];
									if (isset($koid[$z - 1]) && $koid[$z] == $koid[$z - 1])
										$kobsfakt[$z] = $kobsfakt[$z - 1];
									else {
										$r2 = db_fetch_array(db_select("select fakturanr from ordrer where id='$koid[$z]'", __FILE__ . " linje " . __LINE__));
										$kobsfakt[$z] = $r2['fakturanr'];
									}
									$kobsdate[$z] = $r['kobsdate'];
									if ($r['antal'] > 0) {
										$kobsdebet[$z] = $r['antal'] * $kostpris[$y];
										$kobskredit[$z] = 0;
									} else {
										$kobskredit[$z] = $r['antal'] * $kostpris[$y] * -1;
										$kobsdebet[$z] = 0;
									}
									#									$z++;
								}
							}
						}
					}
				}
				$z = -1;
				$salgsdate = array();
				$salgsdebet = array();
				$salgkredit = array();
				$q = db_select("select ordre_id,vare_id,antal,salgsdate from batch_salg where salgsdate >= '$regnstart' and salgsdate <= '$regnslut' order by salgsdate,vare_id", __FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					if ($z >= 0 && isset($salgsdate[$z]) && $r['salgsdate'] == $salgsdate[$z] && $r['ordre_id'] && $r['ordre_id'] == $soid[$z]) {
						for ($y = 0; $y < count($vare_id); $y++) {
							if ($r['vare_id'] == $vare_id[$y]) {
								if ($kontotype[$x] == 'D') {
									if ($r['antal'] > 0)
										$salgsdebet[$z] += $r['antal'] * $kostpris[$y];
									else
										$salgskredit[$z] -= $r['antal'] * $kostpris[$y];
								} elseif (in_array($kontonr[$x], $varelager_u)) {
									if ($r['antal'] > 0)
										$salgskredit[$z] += $r['antal'] * $kostpris[$y];
									else
										$salgsdebet[$z] -= $r['antal'] * $kostpris[$y];
								}
							}
						}
					} else {

						for ($y = 0; $y < count($vare_id); $y++) {
							if ($r['vare_id'] == $vare_id[$y]) {
								if ($kontotype[$x] == 'D') {
									$z++;
									$soid[$z] = $r['ordre_id'];
									if ($soid[$z] == $soid[$z - 1])
										$salgsfakt[$z] = $salgsfakt[$z - 1];
									else {
										$r2 = db_fetch_array(db_select("select fakturanr from ordrer where id='$soid[$z]'", __FILE__ . " linje " . __LINE__));
										$salgsfakt[$z] = $r2['fakturanr'];
									}
									$salgsdate[$z] = $r['salgsdate'];
									if ($r['antal'] > 0) {
										$salgsdebet[$z] = $r['antal'] * $kostpris[$y];
										$salgskredit[$z] = 0;
									} else {
										$salgskredit[$z] = $r['antal'] * $kostpris[$y] * -1;
										$salgsdebet[$z] = 0;
									}
									#									$z++;
								} elseif (in_array($kontonr[$x], $varelager_u)) {
									$z++;
									$soid[$z] = $r['ordre_id'];
									if (isset($soid[$z - 1]) && $soid[$z] == $soid[$z - 1])
										$salgsfakt[$z] = $salgsfakt[$z - 1];
									else {
										$r2 = db_fetch_array(db_select("select fakturanr from ordrer where id='$soid[$z]'", __FILE__ . " linje " . __LINE__));
										$salgsfakt[$z] = $r2['fakturanr'];
									}
									$salgsdate[$z] = $r['salgsdate'];
									if ($r['antal'] > 0) {
										$salgskredit[$z] = $r['antal'] * $kostpris[$y];
										$salgsdebet[$z] = 0;
									} else {
										$salgsdebet[$z] = $r['antal'] * $kostpris[$y] * -1;
										$salgskredit[$z] = 0;
									}
									#									$z++;
								}
							}
						}
					}
				}
				$dato = $regnstart;
				$y = 0;
				$tr = 0;
				$kd = 0;
				$sd = 0;
				$trd = array();
				while ($dato <= $regnslut) {
					while (isset($transdate[$tr]) && $transdate[$tr] == $dato) {
						$trd[$y] = $dato;
						$bil[$y] = $bilag[$tr];
						$besk[$y] = $beskrivelse[$tr];
						$deb[$y] = $debet[$tr];
						$kre[$y] = $kredit[$tr];
						$tr++;
						$y++;
					}
					while (isset($kobsdate[$kd]) && $kobsdate[$kd] == $dato) {
						$trd[$y] = $dato;
						$bil[$y] = 0;
						$besk[$y] = "lagertransaktion - Køb  F: $kobsfakt[$kd]";
						$deb[$y] = $kobsdebet[$kd];
						$kre[$y] = $kobskredit[$kd];
						$kd++;
						$y++;
					}
					while (isset($salgsdate[$sd]) && $salgsdate[$sd] == $dato) {
						$trd[$y] = $dato;
						$bil[$y] = 0;
						$besk[$y] = "lagertransaktion - Salg  F: $salgsfakt[$sd]";
						$deb[$y] = $salgsdebet[$sd];
						$kre[$y] = $salgskredit[$sd];
						$sd++;
						$y++;
					}
					list($yy, $mm, $dd) = explode("-", $dato);
					$dd++;
					if (!checkdate($mm, $dd, $yy)) {
						$dd = 1;
						$mm++;
						if ($mm > 12) {
							$mm = 1;
							$yy++;
						}
					}
					$dd *= 1;
					$mm *= 1;
					if (strlen($dd) < 2)
						$dd = '0' . $dd;
					if (strlen($mm) < 2)
						$mm = '0' . $mm;
					$dato = $yy . "-" . $mm . "-" . $dd;
				}
				for ($y = 0; $y < count($trd); $y++) {
					$transdate[$y] = $trd[$y];
					$bilag[$y] = $bil[$y];
					$beskrivelse[$y] = $besk[$y];
					$debet[$y] = $deb[$y];
					$kredit[$y] = $kre[$y];
				}
			}
			$sim_transdate = array();
			if ($simulering) {
				$sim = 0;
				$sim_kontonr = array();
				$q = db_select("select * from simulering where kontonr='$kontonr[$x]' and transdate>='$regnstart' and transdate<='$regnslut' $dim order by transdate,bilag,id", __FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$sim_id[$sim] = $r['id'];
					$sim_transdate[$sim] = $r['transdate'];
					$sim_bilag[$sim] = $r['bilag'];
					$sim_kontonr[$sim] = $r['kontonr'];
					$sim_beskrivelse[$sim] = $r['beskrivelse'];
					$sim_debet[$sim] = $r['debet'];
					$sim_kredit[$sim] = $r['kredit'];
					$a = 0;
					while ($a < count($transdate) && $sim_transdate[$sim] > $transdate[$a]) {
						$a++;
					}
					for ($b = count($transdate); $b > $a; $b--) {
						$transdate[$b] = $transdate[$b - 1];
						$bilag[$b] = $bilag[$b - 1];
						$beskrivelse[$b] = $beskrivelse[$b - 1];
						$debet[$b] = $debet[$b - 1];
						$kredit[$b] = $kredit[$b - 1];
						$kladde_id[$b] = $kladde_id[$b - 1] ?? null;
						$transvaluta[$b] = $transvaluta[$b - 1] ?? null;
						$transkurs[$b] = $transkurs[$b - 1] ?? 100;
					}
					$transdate[$b] = $sim_transdate[$sim];
					$bilag[$b] = $sim_bilag[$sim];
					$beskrivelse[$b] = $sim_beskrivelse[$sim] . "(Simuleret)";
					$debet[$b] = $sim_debet[$sim];
					$kredit[$b] = $sim_kredit[$sim];
					$kladde_id[$b] = $r['kladde_id'];
					$transvaluta[$b] = $r['valuta'];
					$transkurs[$b] = $r['valutakurs'] ?: 100;
					$sim_transdate[$sim] = NULL;
					$sim++;
				}
			}
		
			// SST-809 Collapse the parallel arrays this account just built into one
			// list of rows, and let every value the print pass needs travel with
			// its own row. That also repairs the Lagerbevaegelser path: it rebuilt
			// transdate/bilag/beskrivelse/debet/kredit in merged order but left
			// kladde_id/valuta/valutakurs on the old indexes, so a currency
			// account converted a row's amounts at a different row's rate.
			$built = array();
			for ($tr = 0; $tr < count($transdate); $tr++) {
				if (!$transdate[$tr]) {
					continue;
				}
				$rowdebet  = isset($debet[$tr])  ? $debet[$tr]  : 0;
				$rowkredit = isset($kredit[$tr]) ? $kredit[$tr] : 0;
				// A posting with nothing in either amount column moves no balance.
				// The row count has excluded those ever since pagination arrived,
				// but the renderer tested the raw values - and pg_fetch_array()
				// hands numeric(15,3) back as the string "0.000", which PHP reads
				// as true. So the page carried a row the pagination did not know
				// about. Decide it numerically, once, here.
				if ((float) $rowdebet == 0 && (float) $rowkredit == 0) {
					continue;
				}
				$built[] = array(
					'transdate'   => $transdate[$tr],
					'bilag'       => isset($bilag[$tr]) ? $bilag[$tr] : '',
					'beskrivelse' => isset($beskrivelse[$tr]) ? $beskrivelse[$tr] : '',
					'debet'       => $rowdebet,
					'kredit'      => $rowkredit,
					'kladde_id'   => isset($kladde_id[$tr]) ? $kladde_id[$tr] : NULL,
					'valuta'      => isset($transvaluta[$tr]) ? $transvaluta[$tr] : NULL,
					'kurs'        => (isset($transkurs[$tr]) && $transkurs[$tr]) ? $transkurs[$tr] : 100,
				);
			}
			$acct_cnt = count($built);
			$acctFrom = $total_rows;
			$accountOffset[$x] = $acctFrom;
			$acctTo   = $total_rows + $acct_cnt;

			// Which accounts this page shows is decided here, once, while the
			// running offset is known, and the print pass below just obeys it.
			// An account whose rows overlap the page's window is on the page. One
			// with no printable rows - holding only an opening balance, or only
			// zero-amount postings - has no span to overlap with, so it belongs
			// on whichever page its position falls in. The old test,
			// rows_to_skip >= acct_cnt, was true for those accounts on every
			// page because both sides were zero, so an account with a primo and
			// no movements in the period was dropped from the report entirely -
			// even though the condition above exists to include it.
			if ($acct_cnt) {
				$accountOnPage[$x] = ($acctFrom < $windowTo && $acctTo > $windowFrom);
			} else {
				$accountOnPage[$x] = ($acctFrom >= $windowFrom && $acctFrom < $windowTo);
			}

			// The balance has to run over every row of an account, so it is all
			// of that account's rows or none of them. For the accounts this page
			// does not show there is no reason to hold them: a full year of
			// postings across the whole chart of accounts is a lot of memory,
			// and before pagination this loop only ever held one account's rows.
			$accountRows[$x]    = $acct_cnt;
			$accountRowList[$x] = $accountOnPage[$x] ? $built : array();
			$total_rows         = $acctTo;
		}
	}

	$total_pages  = max(1, ceil($total_rows / $per_page));

	// An account with no printable rows sits at a single offset instead of spanning a
	// range, so the half-open window test above cannot place one whose offset is exactly
	// total_rows - a primo-only account after the last row. With total_rows an exact
	// multiple of per_page there is no page whose window contains it, and it was dropped
	// from every screen page while print mode (one huge page) still showed it: the same
	// screen-versus-print divergence this report is being fixed for. total_pages is only
	// known now, so those accounts are placed here, on the page their offset falls on and
	// never past the last one.
	foreach ($accountRows as $x => $acct_cnt) {
		if ($acct_cnt) {
			continue;
		}
		$acctPage = min($total_pages, (int) floor($accountOffset[$x] / $per_page) + 1);
		$accountOnPage[$x] = ($acctPage == $page);
	}
	$rowOffset = 0;

	for ($x = 0; $x < count($kontonr); $x++) {
		if (!isset($accountRows[$x])) {
			continue;
		}
		$acct_cnt = $accountRows[$x];
		if (!$accountOnPage[$x]) {
			$rowOffset += $acct_cnt;
			continue;
		}

		$linjebg = $bgcolor5;
		print "<tr><td colspan=6><hr></td></tr>";
		fwrite($csv, "-----------\n");
		print "<tr bgcolor=\"$bgcolor5\">
				<td></td>
				<td></td>
				<td colspan=4>
					<b>$kontonr[$x]</b> :
					<b>$kontobeskrivelse[$x]</b> :
					<b>$kontomoms[$x]</b>
				</td>
			</tr>";

		fwrite($csv, ";;$kontonr[$x] : " . mb_convert_encoding($kontobeskrivelse[$x], 'ISO-8859-1', 'UTF-8') . " : $kontomoms[$x]\n");

		print "<tr><td colspan=6><hr></td></tr>";
		fwrite($csv, "-----------\n");

		$kontosum = $accountPrimo[$x];
		if ($primokurs[$x])
			$tmp = $kontosum * 100 / $primokurs[$x];
		else
			$tmp = $kontosum;
		#if (!$dim) #20180226
		print "<tr bgcolor=\"$linjebg\"><td></td><td></td><td>  Primosaldo </td><td></td><td></td><td align=right>" . dkdecimal($tmp, 2) . "</td></tr>";
		fwrite($csv, ";;Primosaldo;;;" . dkdecimal($tmp, 2) . "\n");

		foreach ($accountRowList[$x] as $i => $row) {
			// The balance runs across every row of the account, including rows an
			// earlier page already showed, so the Saldo column is correct from the
			// first line of whichever page is being rendered.
			$kontosum += afrund($row['debet'], 2) - afrund($row['kredit'], 2);
			$rowIndex = $rowOffset + $i;
			if ($rowIndex < $windowFrom) {
				continue;
			}
			if ($rowIndex >= $windowTo) {
				break;
			}

				($linjebg != $bgcolor5) ? $linjebg = $bgcolor5 : $linjebg = $bgcolor;
				print "<tr bgcolor=\"$linjebg\"><td class=\"kontokort-dato\">  " . dkdato($row['transdate']) . " </td>";
					fwrite($csv, dkdato($row['transdate']) . ";");
					($row['kladde_id']) ? $js = "onclick=\"window.open('kassekladde.php?kladde_id={$row['kladde_id']}&visipop=on')\"" : $js = NULL;
					print "<td title='Kladde: {$row['kladde_id']}' $js>{$row['bilag']}</td><td>$kontonr[$x] : {$row['beskrivelse']} </td>";
					fwrite($csv, "{$row['bilag']};$kontonr[$x] : " . mb_convert_encoding($row['beskrivelse'], 'ISO-8859-1', 'UTF-8') . ";");
					// SST-769 A rate adjustment is posted in DKK only (valuta = -1, valutakurs = 100),
					// so there is no foreign-currency amount to convert. Showing 0,00 left a row that
					// moved the balance with no visible amount, which is why MEDSHOP could not
					// reconcile their currency accounts. Show the DKK figure, labelled in the column
					// so it is never read as an amount in the account's own currency, and export the
					// bare number so the CSV still reconciles.
					if ($kontovaluta[$x]) {
						if ($row['valuta'] == '-1') {
							$csvval = $row['debet'] * 1;
							$vis    = $csvval ? 'DKK ' . dkdecimal($csvval, 2) : dkdecimal(0, 2);
							$title  = findtekst('5234|Kursregulering bogført i DKK', $sprog_id);
						} else {
							$csvval = $row['debet'] * 100 / $row['kurs'];
							$vis    = dkdecimal($csvval, 2);
							$title  = "DKK " . dkdecimal($row['debet'] * 1, 2) . " Kurs: " . dkdecimal($row['kurs'], 2);
						}
					} else {
						$csvval = $row['debet'];
						$vis    = dkdecimal($csvval, 2);
						$title  = NULL;
					}
					print "<td align=\"right\" title=\"$title\">$vis</td>";
					fwrite($csv, dkdecimal($csvval, 2) . ";");
					if ($kontovaluta[$x]) {
						if ($row['valuta'] == '-1') {
							$csvval = $row['kredit'] * 1;
							$vis    = $csvval ? 'DKK ' . dkdecimal($csvval, 2) : dkdecimal(0, 2);
							$title  = findtekst('5234|Kursregulering bogført i DKK', $sprog_id);
						} else {
							$csvval = $row['kredit'] * 100 / $row['kurs'];
							$vis    = dkdecimal($csvval, 2);
							$title  = "DKK " . dkdecimal($row['kredit'] * 1, 2) . " Kurs: " . dkdecimal($row['kurs'], 2);
						}
					} else {
						$csvval = $row['kredit'];
						$vis    = dkdecimal($csvval, 2);
						$title  = NULL;
					}
					print "<td align=\"right\" title=\"$title\">$vis</td>";
					fwrite($csv, dkdecimal($csvval, 2) . ";");
					#$kontosum = $kontosum + afrund($row['debet'], 2) - afrund($row['kredit'], 2);
					if ($kontovaluta[$x]) {
						$tmp = $kontosum * 100 / $row['kurs'];
						$title = "DKK " . dkdecimal($kontosum, 2) . " Kurs: " . dkdecimal($row['kurs'], 2);
					} else {
						$tmp = $kontosum;
						$title = NULL;
					}
					print "<td align=\"right\" title=\"$title\">" . dkdecimal($tmp, 2) . "</td></tr>";
					fwrite($csv, dkdecimal($tmp, 2) . "\n");
		}
		$rowOffset += $acct_cnt;
	}

	print "<tr><td colspan=6></td></tr>";
	print "</tbody>";

	// SST-809 A real print footer, in place of the screen's pagination bar. That
	// bar printed "1-50 af 76", a rows-per-page dropdown and two arrow icons -
	// the meaningless footer the customer reported - and being position:fixed it
	// was also clipped when the printout broke across pages. A tfoot is repeated
	// by the browser on every printed page, in full, which a fixed element
	// cannot be. It is hidden on screen, where the pagination bar does the job.
	$tmp = htmlspecialchars($rapportTitel, ENT_QUOTES, 'UTF-8');
	print "<tfoot class=\"kontokort-printfoot\">";
	print "<tr><td colspan=6>";
	print "<b>" . htmlspecialchars((string) $firmanavn, ENT_QUOTES, 'UTF-8') . "</b>";
	print " | cvr: " . htmlspecialchars((string) $vatNo, ENT_QUOTES, 'UTF-8');
	print " | $tmp";
	print " | " . findtekst('899|Periode', $sprog_id) . ": $startdato/$mf $startaar - $slutdato/$mt $slutaar";
	print " | " . findtekst('5326|Udskrevet', $sprog_id) . ": " . date('d-m-Y');
	print "</td></tr>";
	print "</tfoot>";
	print "</table>";
	#####
	$base_url = "kontokort_standalone.php?" . http_build_query([
        'regnaar'     => $regnaar,
        'maaned_fra'  => $maaned_fra,
        'maaned_til'  => $maaned_til,
        'aar_fra'     => $aar_fra,
        'aar_til'     => $aar_til,
        'dato_fra'    => $dato_fra,
        'dato_til'    => $dato_til,
        'konto_fra'   => $konto_fra,
        'konto_til'   => $konto_til,
        'rapportart'  => $rapportart,
        'ansat_fra'   => $ansat_fra,
        'ansat_til'   => $ansat_til,
        'afd'         => $afd,
        'projekt_fra' => $projekt_fra,
        'projekt_til' => $projekt_til,
        'simulering'  => $simulering,
        'lagerbev'    => $lagerbev,
    ]);

    ####
	// --- Pagination calculations (matches render_table_footer pattern) ---
    $offsetFrom  = (($page - 1) * $per_page) + 1;
    $offsetTo    = min($total_rows, $page * $per_page);
    $nextpage    = min($total_rows, $page * $per_page);           // offset value for next
    $lastpage    = max(0, ($page - 2) * $per_page);               // offset value for prev
    $nextpagestatus = ($page >= $total_pages) ? 'disabled' : '';
    $lastpagestatus = ($page <= 1)            ? 'disabled' : '';

   
    // Build page number buttons with ellipsis — matches render_table_footer pattern
    $pageRange = 2;
    $startPage = max(1, $page - $pageRange);
    $endPage   = min($total_pages, $page + $pageRange);

    $pageButtons = '';
    if ($startPage > 1) {
        $pageButtons .= "<a href='{$base_url}&per_page={$per_page}&page=1' class='navbutton'>1</a>";
        if ($startPage > 2) $pageButtons .= "<span>...</span>";
    }
    for ($p = $startPage; $p <= $endPage; $p++) {
        $activeStyle = ($p === $page) ? "style='text-decoration:underline; font-weight:bold;'" : "";
        $pageButtons .= "<a href='{$base_url}&per_page={$per_page}&page={$p}' class='navbutton' {$activeStyle}>{$p}</a>";
    }
    if ($endPage < $total_pages) {
        if ($endPage < $total_pages - 1) $pageButtons .= "<span>...</span>";
        $pageButtons .= "<a href='{$base_url}&per_page={$per_page}&page={$total_pages}' class='navbutton'>{$total_pages}</a>";
    }

    $prevUrl = $base_url . '&page=' . ($page - 1);
    $nextUrl = $base_url . '&page=' . ($page + 1);

   // Build rows-per-page options
    $rowCounts = [25, 50, 100, 250, 500];
    $rowCountOptions = '';
    foreach ($rowCounts as $count) {
        $sel = ($count === $per_page) ? 'selected' : '';
        $rowCountOptions .= "<option value='{$count}' {$sel}>{$count}</option>";
    }

    // SST-809 Screen only: this is the bar that printed as a meaningless footer.
    // The print view does not emit it at all, and @media print hides it for a
    // plain Ctrl+P of the screen.
    $txtLinjerPrSide = findtekst('2125|Linjer pr. side', $sprog_id);
    if (!$udskriv) {
    echo "
    <div id='kontokort-pagebar' style='position:fixed; bottom:0; left:0; width:100%; background:#f4f4f4;
                border-top:2px solid #ddd; z-index:200; box-shadow:0 -2px 6px rgba(0,0,0,0.1);'>
        <div id='footer-box' style='display:flex; align-items:center; gap:10px;
                                    justify-content:flex-end; padding:6px 16px;'>
            <span id='page-status' style='display:flex;'>
                {$offsetFrom}-{$offsetTo}&nbsp;af&nbsp;{$total_rows}
            </span>
            |
            <span style='display:flex; align-items:center; gap:4px;'>
                <label style='font-size:0.9em; color:#666;'>{$txtLinjerPrSide}</label>
                <select onchange=\"window.location.href='{$base_url}&page=1&per_page=' + this.value\"
                        style='height:24px; cursor:pointer;'>
                    {$rowCountOptions}
                </select>
            </span>
            |
            <span id='navbuttons' style='display:flex; align-items:center; gap:3px;'>
                <a href='{$prevUrl}' " . ($page <= 1 ? "style='pointer-events:none;opacity:0.4;'" : "") . ">
                    <svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'>
                        <path d='M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z'/>
                    </svg>
                </a>
                {$pageButtons}
                <a href='{$nextUrl}' " . ($page >= $total_pages ? "style='pointer-events:none;opacity:0.4;'" : "") . ">
                    <svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'>
                        <path d='M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z'/>
                    </svg>
                </a>
            </span>
        </div>
    </div>";
    }
	####
	// SST-809 The tbody and table are closed above, before the tfoot. The stray
	// second pair that used to be printed here left the markup unbalanced, which
	// browsers recover from differently - and print layout is exactly where that
	// recovery starts to show.
	print "</div>"; // closes the data wrapper opened before the table

	// SST-809 Print rules for a plain Ctrl+P of the paginated screen: release the
	// scroll container so the rows are not clipped to the visible box, demote the
	// sticky header so it does not overlay data, hide the fixed pagination bar,
	// show the tfoot instead so every printed page carries a real footer, and
	// keep a posting on one line.
	print "
<style>
	.kontokort-printfoot { display: none; }
	@media print {
		#kontokort-pagebar { display: none !important; }
		.kontokort-data {
			overflow: visible !important;
			max-height: none !important;
		}
		#datapg thead { position: static !important; }
		.kontokort-printfoot {
			display: table-footer-group;
			font-size: 8pt;
		}
		#datapg .kontokort-dato { white-space: nowrap; }
		#datapg tr { page-break-inside: avoid; }
	}
</style>";

	if ($udskriv) {
		// Opened from the Udskriv button, so go straight to the print dialog.
		print "<script>window.addEventListener('load', function () { window.print(); });</script>";
	}

	fclose($csv);

	if ($menu == 'T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}
}# endfunc kontokort
#################################################################################################
?>
<style>
	
        .navbutton {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 24px;
            min-width: 24px;
            padding: 0 4px;
            border: 1px solid #ccc;
            background: #fff;
            color: #000;
            text-decoration: none;
            font-size: 0.85em;
            cursor: pointer;
            box-sizing: border-box;
        }
        .navbutton:hover {
            background-color: #e8e8e8;
        }
	#datapg td {
     padding-right: 8px;
	}
    
</style>