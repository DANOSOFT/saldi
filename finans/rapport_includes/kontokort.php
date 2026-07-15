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

function kontokort($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
                   $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart,
                   $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,
                   $simulering, $lagerbev, $page = 1, $per_page = 50){

	global $afd_navn, $ansatte, $ansatte_id;
	global $bgcolor, $bgcolor4, $bgcolor5;
	global $connection, $csv;
	global $db;
	global $md, $menu;
	global $prj_navn_fra, $prj_navn_til;
	global $top_bund;
	global $sprog_id;
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
	if ($menu == 'T') {
		$leftbutton = "<a title=\"Klik her for at komme til forsiden af rapporter\" href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\" accesskey=\"L\"><i class='fa fa-close fa-lg'></i> &nbsp;Luk</a>";
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">";
		print "<div class=\"headerbtnLft headLink\">$leftbutton</div>";
		print "<div class=\"headerTxt\">$title</div>";
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>"; 
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

		print "<td width='75%' align='center' style='$topStyle'>".findtekst('2173|Rapport - kontokort', $sprog_id)."</td>\n";
		print "<td width='5%' align='center' style='$buttonStyle'><a href='$csvfile' style='color:#ffffff'>csv</a></td>\n";

		print "</tbody></table>";
		print "</td></tr>";
		($simulering) ? $tmp = "Simuleret kontokort" : $tmp = "Kontokort";
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
		print "<td width=\"80%\" $top_bund>".findtekst('2173|Rapport - kontokort', $sprog_id)."</td>";
		print "<td width=\"10%\" $top_bund><a href='$csvfile'>csv</a></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>";
		($simulering) ? $tmp = "Simuleret kontokort" : $tmp = "Kontokort";
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
print "<div style=\"overflow-y: auto; max-height: calc(100vh - 140px);\">";
print "<table style=\"width:100%; border-collapse:collapse;\" class='dataTable' id='datapg'>";

// Sticky header
print "<thead style=\"position: sticky; top: 0; background-color: #eeeef0; z-index: 10;\">";
print "<tr>";
print "<th style=\"text-align:left; padding:8px 4px;\"><b>Dato</b></th>";
print "<th style=\"text-align:left; padding:8px 4px;\"><b>Bilag</b></th>";
print "<th style=\"text-align:left; padding:8px 4px;\"><b>Tekst</b></th>";
print "<th style=\"text-align:right; padding:8px 4px;\"><b>Debet</b></th>";
print "<th style=\"text-align:right; padding:8px 4px;\"><b>Kredit</b></th>";
print "<th style=\"text-align:right; padding:8px 4px;\"><b>Saldo</b></th>";
print "</tr>";
print "</thead>";

// Data body
print "<tbody>";
	fwrite($csv, "\"Dato\";\"Bilag\";\"Tekst\";\"Debet\";\"Kredit\";\"Saldo\"\n");
	####
	$total_rows = 0;
    for ($x = 0; $x < count($kontonr); $x++) {
        if (in_array($kontonr[$x], $ktonr) || $primo[$x]) {
            $cnt = db_fetch_array(db_select(
                "SELECT COUNT(*) as c FROM transaktioner 
                 WHERE kontonr=$kontonr[$x] 
                   AND transdate>='$regnstart' AND transdate<='$regnslut' $dim",
                __FILE__ . " linje " . __LINE__
            ));
            $total_rows += (int)$cnt['c'];
        }
    }
    $total_pages  = max(1, ceil($total_rows / $per_page));
   
    $rows_to_skip = ($page - 1) * $per_page;
    $rows_printed = 0;
	####
	
   for ($x = 0; $x < count($kontonr); $x++) {
	if (in_array($kontonr[$x], $ktonr) || $primo[$x]) {
 		$linjebg = $bgcolor5;
            // Count rows for this account to see if we can skip it entirely
            $acct_cnt = (int)db_fetch_array(db_select(
                "SELECT COUNT(*) as c FROM transaktioner
                 WHERE kontonr=$kontonr[$x]
                   AND transdate>='$regnstart' AND transdate<='$regnslut' $dim",
                __FILE__ . " linje " . __LINE__
            ))['c'];

            if ($rows_to_skip >= $acct_cnt) {
                $rows_to_skip -= $acct_cnt;
                continue;  // skip header, primosaldo, everything for this account
            }

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
			$kontosum = $primo[$x];
			$query = db_select("select debet, kredit from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' $dim order by transdate,pos,bilag,id", __FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				$kontosum = $kontosum + afrund($row['debet'], 2) - afrund($row['kredit'], 2);
			}
			$query = db_select("select debet, kredit from simulering where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' $dim order by transdate,bilag,id", __FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				$kontosum = $kontosum + afrund($row['debet'], 2) - afrund($row['kredit'], 2);
			}
			if ($primokurs[$x])
				$tmp = $kontosum * 100 / $primokurs[$x];
			else
				$tmp = $kontosum;
			#if (!$dim) #20180226 
			print "<tr bgcolor=\"$linjebg\"><td></td><td></td><td>  Primosaldo </td><td></td><td></td><td align=right>" . dkdecimal($tmp, 2) . "</td></tr>";
			fwrite($csv, ";;Primosaldo;;;" . dkdecimal($tmp, 2) . "\n");
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
					while ($a <= count($transdate) and $sim_transdate[$sim] > $transdate[$a])
						$a++;
					for ($b = count($transdate); $b > $a; $b--) {
						$transdate[$b] = $transdate[$b - 1];
						$bilag[$b] = $bilag[$b - 1];
						$beskrivelse[$b] = $beskrivelse[$b - 1];
						$debet[$b] = $debet[$b - 1];
						$kredit[$b] = $kredit[$b - 1];
					}
					$transdate[$b] = $sim_transdate[$sim];
					$bilag[$b] = $sim_bilag[$sim];
					$beskrivelse[$b] = $sim_beskrivelse[$sim] . "(Simuleret)";
					$debet[$b] = $sim_debet[$sim];
					$kredit[$b] = $sim_kredit[$sim];
					$sim_transdate[$sim] = NULL;
					$sim++;
				}
			}
		
			for ($tr = 0; $tr < count($transdate) + count($sim_transdate); $tr++) {
			if ($transdate[$tr] && ($debet[$tr] || $kredit[$tr])) {

                // Always accumulate kontosum — even for skipped rows
                // (moved up so it runs before the skip check)
                $debet_val  = afrund($debet[$tr], 2);
                $kredit_val = afrund($kredit[$tr], 2);

                if ($rows_to_skip > 0) {
                    $kontosum += $debet_val - $kredit_val;
                    $rows_to_skip--;
                    continue;
                }
                if ($rows_printed >= $per_page) {
                    break;
                }

				($linjebg != $bgcolor5) ? $linjebg = $bgcolor5 : $linjebg = $bgcolor;
				print "<tr bgcolor=\"$linjebg\"><td>  " . dkdato($transdate[$tr]) . " </td>";
					fwrite($csv, dkdato($transdate[$tr]) . ";");
					($kladde_id[$tr]) ? $js = "onclick=\"window.open('kassekladde.php?kladde_id=$kladde_id[$tr]&visipop=on')\"" : $js = NULL;
					print "<td title='Kladde: $kladde_id[$tr]' $js>$bilag[$tr]</td><td>$kontonr[$x] : $beskrivelse[$tr] </td>";
					fwrite($csv, "$bilag[$tr];$kontonr[$x] : " . mb_convert_encoding($beskrivelse[$tr], 'ISO-8859-1', 'UTF-8') . ";");
					if ($kontovaluta[$x]) {
						if ($transvaluta[$tr] == '-1')
							$tmp = 0;
						else
							$tmp = $debet[$tr] * 100 / $transkurs[$tr];
						$title = "DKK " . dkdecimal($debet[$tr] * 1, 2) . " Kurs: " . dkdecimal($transkurs[$tr], 2);
					} else {
						$tmp = $debet[$tr];
						$title = NULL;
					}
					print "<td align=\"right\" title=\"$title\">" . dkdecimal($tmp, 2) . "</td>";
					fwrite($csv, dkdecimal($tmp, 2) . ";");
					if ($kontovaluta[$x]) {
						if ($transvaluta[$tr] == '-1')
							$tmp = 0;
						else
							$tmp = $kredit[$tr] * 100 / $transkurs[$tr];
						$title = "DKK " . dkdecimal($kredit[$tr] * 1, 2) . " Kurs: " . dkdecimal($transkurs[$tr], 2);
					} else {
						$tmp = $kredit[$tr];
						$title = NULL;
					}
					print "<td align=\"right\" title=\"$title\">" . dkdecimal($tmp, 2) . "</td>";
					fwrite($csv, dkdecimal($tmp, 2) . ";");
					#$kontosum = $kontosum + afrund($debet[$tr], 2) - afrund($kredit[$tr], 2);
					if ($kontovaluta[$x]) {
						$tmp = $kontosum * 100 / $transkurs[$tr];
						$title = "DKK " . dkdecimal($kontosum, 2) . " Kurs: " . dkdecimal($transkurs[$tr], 2);
					} else {
						$tmp = $kontosum;
						$title = NULL;
					}
					print "<td align=\"right\" title=\"$title\">" . dkdecimal($tmp, 2) . "</td></tr>";
					fwrite($csv, dkdecimal($tmp, 2) . "\n");
                $rows_printed++;
			}
			
		}
          if ($rows_printed >= $per_page) break; // stop processing more rows once we've printed enough for the current page
		}
	}
				   			   
	print "<tr><td colspan=6></td></tr>";
	print "</tbody></table>";
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

    echo "
    <div style='position:fixed; bottom:0; left:0; width:100%; background:#f4f4f4;
                border-top:2px solid #ddd; z-index:200; box-shadow:0 -2px 6px rgba(0,0,0,0.1);'>
        <div id='footer-box' style='display:flex; align-items:center; gap:10px;
                                    justify-content:flex-end; padding:6px 16px;'>
            <span id='page-status' style='display:flex;'>
                {$offsetFrom}-{$offsetTo}&nbsp;af&nbsp;{$total_rows} 
            </span>
            |
            <span style='display:flex; align-items:center; gap:4px;'>
                <label style='font-size:0.9em; color:#666;'>Linjer pr. side</label>
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
	####
	print "</tbody>";
	print "</table>";
	print "</div>"; // closes scrollable div
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