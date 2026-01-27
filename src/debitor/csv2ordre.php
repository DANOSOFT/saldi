<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/csv2ordre.php --- lap 4.1.1 --- 2025-12-19 ---
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
// Copyright (c) 2003-2025 saldi.dk aps
// -----------------------------------------------------------------------
// 20160502 PHR Sat returnconfirm på [Hent] for at hindre dobbeltimport ved dobbeltklik.
// 20190812 PHR	More information from address is imported.
// 20220713 phr '$pris' is now trimmed.  
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20251219 LOE Added new top header design

@session_start();
$s_id = session_id();
$css = "../css/standard.css";

$title = "Importer ordrer fra CSV";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");
include("../includes/topline_settings.php");

$$valg = if_isset($_GET,'ordrer','valg');
$returside = if_isset($_GET, '../debitor/ordreliste.php', 'returside');

print "<div align=\"center\">";

 ############################
     $icon_back = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';

    ##########################
	$center = "";
	$width = "width=10%";
	print "<table width='100%' border='0' cellspacing='0' cellpadding='0'><tbody>\n"; #tabel1 start
	print "<tr><td align='center' valign='top' height='1%'>\n";
	print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody>\n"; #tabel2a start

	$tekst = findtekst('154|Dine ændringer er ikke blevet gemt! Tryk OK for at forlade siden uden at gemme.', $sprog_id);
	print "<td width='10%' id='headerbtn' align=center><a href=\"javascript:confirmClose('$returside')\" accesskey=L>
		  <button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".$icon_back  . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>\n";

	print "<td width='80%' align=center style='$topStyle'>" . $title . "</td>\n";

	print "<td width='10%' align=center style='$buttonStyle;'>
		   <br></td>\n";

	print "</tbody></table>\n"; #tabel2a slut
	print "</td></tr>\n";
	print "<tr><td width=\"100%\" valign=\"top\">";
    ####
    print "</td></tr>\n";

    print "</tbody></table>\n";  # tabel1 slut
    #####

    ?>
    <style>
    .headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none !important;
		gap: 5px;
	}
	#headerbtn a{
		text-decoration: none !important;
	}

    </style>
    <?php

##########################


print "</td></tr>";  //?

if ($_POST) {
	$submit = if_isset($_POST['submit']);
	$gruppe = if_isset($_POST['gruppe']);

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn = "../temp/" . $db . "_" . str_replace(" ", "_", $brugernavn) . ".csv";
		if (move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			overfoer_data($filnavn);
		}
	}
} else {
	upload();
}

print "</tbody></table>";
#####################################################################################################
function upload()
{
	global $charset;
	global $regnaar;

	$x = 0;
	$q = db_select("SELECT kodenr,beskrivelse from grupper where art='DG' AND fiscal_year=$regnaar order by kodenr", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$gruppe[$x] = $r['kodenr'];
		$gruppebeskr[$x] = $r['beskrivelse'];
		$x++;
	}
	print "<tr><td width=100% align=center><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style='margin-top: 20px; width:75%'><tbody>";
	print "<tr><td colspan=\"2\">Denne funktion importerer ordrer fra en tabulatorsapareret fil til ordrer</td></tr>";
	print "<tr><td colspan=\"2\">Filen skal have følgende format:</td></tr>";
	$txt = HtmlEntities("Kundenr <tab> Ordrenr <tab> Dato <tab> Projekt <tab> Telefon <tab> Navn <tab> Adresse1 <tab> Adresse2 <tab> Postnr <tab> Bynavn <tab> Email <tab> Varenummer <tab> Varenavn <tab> Antal <tab> Pris", ENT_COMPAT, $charset);
	$txt .= "<br>" . HtmlEntities("Hvis kundenummer ikke eksisterer i forvejen, oprettes en ny kunde i den valgte debitorgruppe.", ENT_COMPAT, $charset);
	$txt .= "<br>" . HtmlEntities("Hvis der ikke er angivet varenummer søges efter vare med samme navn. Hvis denne ikke findes, indsættes linjen som kommentar.", ENT_COMPAT, $charset);
	print "<tr><td colspan=\"2\">$txt<br></td></tr>";
	print "<tr><td colspan=\"2\"><hr></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"csv2ordre.php\" method=\"POST\">";
	print "<tr><td width=\"150px\">Debitorgruppe</td><td align=\"right\"><select name=\"gruppe\" style=\"width:150px\">\n";
	for ($x = 0; $x < count($gruppe); $x++)
		print "<option value=\"$gruppe[$x]\">$gruppe[$x] $gruppebeskr[$x]</option>\n";
	print "</select></span></td></tr>";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"900000\">";
	print "<tr><td colspan=\"2\"><hr></td></tr>";
	print "<tr><td> V&aelig;lg datafil:</td><td><input name=\"uploadedfile\" type=\"file\"></td></tr>";
	print "<tr><td colspan=\"2\"><hr></td></tr>";
	print "<tr><td colspan=\"2\" align=center><input type=\"submit\" value=\"Hent\"value=\"Godkend\" onclick=\"javascript:return confirm('Importer ordrer?')\"></td></tr>";
	print "</form>";
	print "</tbody></table>";
	print "</td></tr>";
}


function overfoer_data($filnavn)
{
	global $charset;
	global $gruppe;


	$betalingsbet = 'Netto';
	$betalingsdage = 8;

	$x = 0;
	$oid = array();
	$q = db_select("select kontonr from adresser where art = 'D'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$kontonumre[$x] = $r['kontonr'];
		$x++;
	}
	$imp_antal = 0;

	$fp = fopen("$filnavn", "r");
	if ($fp) {
		$pre_kontonr = 0;
		transaktion('begin');
		$fp = fopen("$filnavn", "r");
		if ($fp) {
			$x = 0;
			$imp_antal = 0;
			while (!feof($fp)) {
				$skriv_linje = 0;
				if ($linje = fgets($fp)) {
					$skriv_linje = 1;
					if ($charset == 'UTF-8')
						$linje = mb_convert_encoding($linje, 'UTF-8', 'ISO-8859-1');
					if ($x)
						$pre_kontonr = $kontonr;
					if (strpos($linje, chr(9)))
						list($kontonr, $ordrenr, $dato, $projekt, $telefon, $firmanavn, $addr1, $addr2, $postnr, $bynavn, $email, $varenr, $varenavn, $antal, $pris) = explode(chr(9), $linje);
					elseif (strpos($linje, ';'))
						list($kontonr, $ordrenr, $dato, $projekt, $telefon, $firmanavn, $addr1, $addr2, $postnr, $bynavn, $email, $varenr, $varenavn, $antal, $pris) = explode(';', $linje);
					else
						$kontonr = NULL;
					if (!$kontonr)
						$skriv_linje = 0;
					if (!is_numeric($kontonr))
						$skriv_linje = 0;
					$pris = trim($pris); #20220713
					if ($skriv_linje == 1) {
						if (!in_array($kontonr, $kontonumre)) {
							db_modify("insert into adresser(kontonr,firmanavn,addr1,addr2,postnr,bynavn,email,tlf,gruppe,art,betalingsbet,betalingsdage) values ('$kontonr','" . db_escape_string($firmanavn) . "','" . db_escape_string($addr1) . "','" . db_escape_string($addr2) . "','" . db_escape_string($postnr) . "','" . db_escape_string($bynavn) . "','" . db_escape_string($email) . "','" . db_escape_string($telefon) . "','$gruppe','D','$betalingsbet','$betalingsdage')", __FILE__ . " linje " . __LINE__);
							$kontonumre[count($kontonumre)] = $kontonr;
						}
						if ($pre_kontonr != $kontonr) {
							$qtxt = "select * from adresser where art='D' and kontonr = '$kontonr'";
							$r = db_fetch_array(db_select("$qtxt", __FILE__ . " linje " . __LINE__));
							$konto_id = $r['id'];
							if (!$firmanavn)
								$firmanavn = $r['firmanavn'];
							if (!$addr1)
								$addr1 = $r['addr1'];
							if (!$addr2)
								$addr2 = $r['addr2'];
							if (!$postnr)
								$postnr = trim($r['postnr']);
							if (!$bynavn)
								$bynavn = $r['bynavn'];
							if ($postnr && !$bynavn)
								$bynavn = bynavn($postnr);
							$land = $r['land'];
							$betalingsdage = $r['betalingsdage'];
							$betalingsbet = $r['betalingsbet'];
							$cvrnr = $r['cvrnr'];
							$ean = $r['ean'];
							$institution = $r['institution'];
							if (!$email)
								$email = $r['email'];
							$mail_fakt = $r['mailfakt'];
							($mail_fakt) ? $udskriv_til = 'email' : $udskriv_til = 'PDF';
							if ($r['pbs_nr'] > 0) {
								$pbs_nr = $r['pbs_nr'];
								$pbs = 'bs';
								$udskriv_til = 'PBS';
							}
							$kontakt = $r['kontakt'];
							$gruppe = $r['gruppe'];
							$kontoansvarlig = $r['kontoansvarlig'];

							$lev_firmanavn = $r['lev_firmanavn'];
							$lev_addr1 = $r['lev_addr1'];
							$lev_addr2 = $r['lev_addr2'];
							$lev_postnr = trim($r['lev_postnr']);
							$lev_bynavn = $r['lev_bynavn'];
							if ($lev_postnr && !$lev_bynavn)
								$lev_bynavn = bynavn($lev_postnr);
							$lev_land = $r['lev_land'];
							$lev_kontakt = $r['lev_kontakt'];

							$qtxt = "select max(ordrenr) as ordrenr from ordrer where art='DO'";
							$r = db_fetch_array(db_select("$qtxt", __FILE__ . " linje " . __LINE__));
							$ordrenr = $r['ordrenr'] + 1;

							$projektnr = 0;
							if ($projekt) {
								$qtxt = "select kodenr from grupper where art='PRJ' and beskrivelse = '$projekt'";
								$r = db_fetch_array(db_select("$qtxt", __FILE__ . " linje " . __LINE__));
								$projektnr = $r['kodenr'];
							}
							$qtxt = "select box1 from grupper where art='DG' and kodenr = '$gruppe'";
							$r = db_fetch_array(db_select("$qtxt", __FILE__ . " linje " . __LINE__));
							$momsgruppe = str_replace('S', '', $r['box1']);
							$qtxt = "select box2 from grupper where art='SM' and kodenr = '$momsgruppe'";
							$r = db_fetch_array(db_select("$qtxt", __FILE__ . " linje " . __LINE__));
							$momssats = $r['box2'] * 1;

							$qtxt = "insert into ordrer ";
							$qtxt .= "(ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,email,mail_fakt,udskriv_til,art,projekt,momssats,";
							$qtxt .= "betalingsbet,betalingsdage,status,ordredate,levdate,ean)";
							$qtxt .= " values ";
							$qtxt .= "('$ordrenr','$konto_id','$kontonr','" . db_escape_string($firmanavn) . "','" . db_escape_string($addr1) . "',";
							$qtxt .= "'" . db_escape_string($addr2) . "','" . db_escape_string($postnr) . "','" . db_escape_string($bynavn) . "',";
							$qtxt .= "'" . db_escape_string($email) . "','$mail_fakt','$udskriv_til','DO','$projektnr','$momssats','$betalingsbet','$betalingsdage',";
							$qtxt .= "'1','" . usdate($dato) . "','" . usdate($dato) . "','" . db_escape_string($ean) . "')";
							db_modify($qtxt, __FILE__ . " linje " . __LINE__);
							$r = db_fetch_array($q = db_select("select max(id) as id from ordrer where kontonr='$kontonr'", __FILE__ . " linje " . __LINE__));
							$ordre_id = $r['id'];
							if (!in_array($ordre_id, $oid))
								$oid[count($oid)] = $ordre_id;
							$posnr = 0;
							$imp_antal++;
						}
						$posnr++;
						if ($varenr)
							$qtxt = "select id,varenr,salgspris,beskrivelse from varer where varenr = '$varenr'";
						else
							$qtxt = "select id,varenr,salgspris,beskrivelse from varer where beskrivelse = '$varenavn'";
						$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
						if ($r['id']) {
							opret_ordrelinje($ordre_id, $r['id'], $r['varenr'], usdecimal($antal), $varenavn, usdecimal($pris), 0, 100, 'DO', '', $posnr, '0', 'on', '', '', '0', '', '', '', '', '');
						} else {
							if (!$varenavn)
								$varenavn = "ukendt, $antal stk á $pris";
							$qtxt = "insert into ordrelinjer(ordre_id,posnr,beskrivelse) values ('$ordre_id','$posnr','" . db_escape_string($varenavn) . "')";
							db_modify($qtxt, __FILE__ . " linje " . __LINE__);
						}
					}
					$x++;
				}
			}
		}
		fclose($fp);
		for ($x = 0; $x < count($oid); $x++) {
			$qtxt = "select sum(antal*(pris-(pris*rabat/100))) as ordresum from ordrelinjer where ordre_id='$oid[$x]'";
			$r = db_fetch_array(db_select("$qtxt", __FILE__ . " linje " . __LINE__));
			if ($r['ordresum'])
				db_modify("update ordrer set sum='$r[ordresum]' where id=$oid[$x]", __FILE__ . " linje " . __LINE__);
		}
		transaktion('commit');
	}
	print "</tbody></table>";
	print "</td></tr>";
	print "<BODY onLoad=\"javascript:alert('$imp_antal ordrer importeret')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/ordreliste.php?valg=tilbud\">";
	exit;
} # endfunc overfoer_data

function nummertjek($nummer)
{
	$nummer = trim($nummer);
	$retur = 1;
	$nummerliste = array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0", ",", ".", "-");
	for ($x = 0; $x < strlen($nummer); $x++) {
		if (!in_array($nummer[$x], $nummerliste))
			$retur = 0;
	}
	if ($retur) {
		for ($x = 0; $x < strlen($nummer); $x++) {
			if ($nummer[$x] == ',')
				$komma++;
			elseif ($nummer[$x] == '.')
				$punktum++;
		}
		if ((!$komma) && (!$punktum))
			$retur = 'US';
		elseif (($komma == 1) && (substr($nummer, -3, 1) == ','))
			$retur = 'DK';
		elseif (($punktum == 1) && (substr($nummer, -3, 1) == '.'))
			$retur = 'US';
		elseif (($komma == 1) && (!$punktum))
			$retur = 'DK';
		elseif (($punktum == 1) && (!$komma))
			$retur = 'US';
	}
	return $retur = chr(32);
}
