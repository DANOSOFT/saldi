<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/sys_div_func.php --- ver 5.0.0 -- 2026.10.10 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// -----------------------------------------------------------------------
// Kaldes fra systemdata/diverse.php
// 2013.11.01 Tilføjet fravalg af tjek for forskellige datoer på samme bilag i kasseklasse. Søg 20131101
// 2013.12.10	Tilføjet valg om kort er betalingskort som aktiver betalingsterminal. Søg 21031210
// 2013.12.13	Tilføjet "intern" bilagsopbevaring (box6 under ftp)
// 2014.01.29	Tilføjet valg til automatisk genkendelse af betalingskort (kun ved integreret betalingsterminal) Søg 20140129
// 2014.05.08	Tilføjet valg til bordhåndtering under pos_valg Søg 20140508
// 2014.06.16 Tilføjet mellemkonto til pos kasser. Søg mellemkonto.
// 2014.07.01	FTP ændret til bilag og intern bilagsopbevaring flyttet til owncloud
// 2015.01.05 I sqlquery_io er separator ændret fra <tab> til ; tekster utf8 decodes og der sættes " om.
// 2015.04.11 Tilføjet labelprint under vare_valg.
// 20150417 CA  Topmenudesign tilføjet for Prisliste               søg 20150417
// 20150522 CA  Oprydning i HTML-kode især input - omfattende så ingen søgning
// 20150529 CA  Håndtering af forskellige typer prislister         søg 20150529
// 20150608 PHR Tilføjet link til ../api/hent_varer.php            søg 20150608
// 20150612 CA  Slette prislister                                  søg 20150612
// 20150625 CA  Tilpasning til topmenu
// 20150814 CA  Link til opsætning af prisliste                    søg 20150815
// 20150907 PHR Sætpriser tilføjet under ordre_valg, Søg $saetvareid
// 20151002	PHR	Fjernet mulighed for at trække en brugerliste.
// 20151005	PHR	Labelprint fungerer kun hvis variablen labelprint er sat. (Midlertidig løsning)
// 20151006 PHR Labelprint ændret fra php til html.
// 20160116 PHR Ændret 'bilag' så inputfelter kun vises ved 'egen ftp'
// 20160226 CA  Tilføjet valg af leverandører under prislister.    søg 20160226
// 20160412 PHR Opdelt vare_valg i vare_valg, labels & shop_valg
// 20160601	PHR SMTP kan nu anvendes med brugernavn, adgangskode og kryptering.
// 20161118	PHR	Tilføjet default bord som option for kasse i funktion pos_valg. Søg bordvalg
// 20161125 PHR Indført html som formulargenerator som alternativ til postscript i funktion div_. Søg pv_box3
// 20170123 PHR Tilføjet API_valg
// 20170314 PHR POS Valg - tilføjet mulighed for at sætte 'udtages fra kasse' til 0 som default.
// 20170329 PHR ordre_valg - tilføjet gennemsnitspris til opdat_kostpris
// 20170404 PHR ordre_valg - Straksbogfør skelner nu mellem debitor og kreditorordrer. Dvs debitor;kreditor - Søg # 20170404
// 20170731 PHR Tilføjet 'Nulstil regnskab under kontoindstillinger - 20170731
// 20181029 CA  Tilføjet gavekort og tilgodehavende tilknyttet id  søg 20181029
// 20181126 PHR	Tilvalg - Marker vare som udgået når beholdning går i minus (vare_valg). Søg DisItemIfNeg
// 20181129 PHR	Tilføjet mulighed for at sætte tidszone i regnskabet. Søg DisItemIfNeg
// 20181216 PHR	Tilføjet 'card_enabled' på betalingskort (Pos_valg) og mulighed for ændring af rækkefølge. Søg '$card_enabled'
// 20190107 PHR	Tilføjet 'change_cardvalue' på betalingskort (Pos_valg) og mulighed for ændring af rækkefølge. Søg '$change_cardvalue'
// 20190129 PHR	(vare_valg) Changed 'Momskode for salgspriser på varekort' to 'Vis priser med moms på varekort'. Search '$vatOnItemCard'
// 20190225 MSC - Rettet topmenu design til
// 20190411 LN Set new field, which sets the default value for provision
// 20190421 PHR - Added confirmDescriptionChange, in 'vare_valg'
// 20190614 LN Added argument to chooseProvisionForProductGroup -> $defaultProvision
// 20200316 PHR Function sqlquery_io. Fixed save & delete sql query
// 20200515 PHR	Function 'div_valg' Added 'mySale'
// 20210112 LOE included language file to sprog fuction
// 20210224 LOE An if Fuction added to check if a language is set and available in settings table 
// 20200515 PHR Function 'div_valg' Added 'mySale'
// 20201128 PHR Function 'labels' Added 'labelType'
// 20210110 PHR Function Vare_valg. Added commission. 
// 20210213 PHR Some cleanup
// 20210302 CA  Added reservation of consignment for Danske Fragtmænd - search dfm_
// 20210303 LOE updated engdan function applied here
// 20210305 CA  Added the selection to use debtor number as phone number in orders - search debtor2orderphone
// 20210710 LOE Added some translation for texts on kontoindstillinger diverse section
// 20210711 LOE - Translated some texts for provision function
// 20210712 LOE - Some more translation for vare_valg , Prislister and labels function and also added if empty to correct undefined variable bug.
// 20210713 LOE - More translation  for bilag(), kontoplan_io() rykker_valg functions
// 20210801 CA  Added the selection to use order notes in ordre_valg - search orderNoteEnabled
// 20210802 LOE Translated the remaining title and alert texts
// 20211019 LOE Some bugs fixed
// 20211022 LOE Fixed some bugs
// 20211123 PHR added paperflow
// 20211123 PHR added paperflowId & paperflowBearer
// 20260928 Sawaneh Security 4.0: SQL tool, DocuBizz and Paperflow removed; Vibrant password never shown; MobilePay/QuickPay
//                  secrets write-only; Flatpay login handled server-side; FTP test via PHP ftp_*; variant delete id cast.
// 20220413 PHR Renamed pos_valg til posOptions and moved function to diverse/posOptions.php
// 20231228 PBLM Added mobilePay (diverse valg)
// 20240130 PBLM Added Nemhandel (diverse valg)
// 06-01-2025 PBLM Added a second file to api_valg
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20250503 LOE reordered mix-up text_id from tekster.csv in findtekst()
// 20250513 Sawaneh add max user update in kontoindstillinger()
// 20250526 PHR 'nyt_navn' changed to 'newName' 
// 20250911 LOE modified text 3023 to 2324
// 20251124 PHR	modified 'betalingslister' to choose between none / debitor / kreditor / both
// 20260223 Sawaneh SD-335 added buttonname field to DFM pickup address UI
// 20260304 Sawaneh SD-369 fixed- API URL instead of duplicate Danske Fragtmænd agreement number
// 20260420 NTR SST-578 Fixed QRcode always fetching kasse 2, instead of it's intended kasse
// 20260605 CL/PHR function labels: fixed Standard label read from grupper (was incorrectly reading from labels table); added hidden editRawHTML to keep raw HTML mode after save
// 20260709 Sawaneh Added "Show both delivery address and Extra fields on open orders" setting under Order-related options
// 20260715 CDX/NTR Made the REST API Swagger link relative to the current installation
// 20260727 CL/Sawaneh Translated the last four order-related settings (GS1, 'Vores ref.' stock,
//                     out-of-stock warning, delivery address + extra fields) via findtekst(), tekst_id 9902-9909
// 20260729 NTR Fixed $r being set to a bool due to && without guarding parenteses, causing error when trying to assign $timezone.
//              Changed the tekst_id's of the previous translation to be 3032-3039 instead.
// 20260819 CL/NTR Added loadLabelText()/saveLabelText() so the label editor reads and writes the same
//                 storage lager/labelprint.php prints from. Raw HTML help text now names
//                 $minbeskrivelse/$minpris as the default; translated via findtekst(), tekst_id 5056.
// 20260824 CL/NTR loadLabelText()/saveLabelText() prefer account_id 0 over null legacy rows and only
//                 touch global rows, matching what the label editor shows.
// 20260826 CL/SZ  Added labelTemplateEditableVisually() and forced raw-HTML mode in labels() for any
//                 label the visual editor's field model can't losslessly regenerate (imported
//                 Brother/Dymo templates, hand-written raw HTML, ...) - saving via the visual editor
//                 was silently discarding whatever it doesn't model (MB-18).
// 20260826 SZ    kontoplan_io: removed the redundant "Eksportér kontoplan" header row that was
//                 printed unconditionally right before the real (popup or non-popup) export row
//                 of the same label, making it appear twice under System -> Indstillinger ->
//                 Diverse -> Import & eksport (MB-26).
// 20260827 CL/SZ Removed the dead "MySalesTest" checkbox row and its duplicate mySale
//                query (was mislabeled $mySaleTest but still read var_name='mySale');
//                also dropped the debug echo block referencing it. Never saved
//                ($_POST['mySaleTest'] was read nowhere) and had no consumer. MB-28.
// 20260916 Sawaneh Declared $permission_key (roles & permissions, phase 3)
// 20260928 Sawaneh Removed personlige_valg() (replaced by systemdata/personalSettings.php, no callers left).
// 20261002 Sawaneh Phase 4b batch 1: provision() and orediff() removed (generated sections); div_valg() no longer shows
//                  mySale, print, payment lists, payment days or voucher-date rows, and no longer calls checkip.dyndns.com.
//                  The four debtor-card rows (Debitorkort section since 4a) are gone from it too.
// 20261002 Sawaneh Phase 4b batch 2: GLS, Danske Fragtmænd, QuickPay, Flatpay, Vibrant, MobilePay and Copayone left div_valg()
//                  for Indstillinger » Integrationer (pick-up addresses stay); api_valg() only runs the shop sync.
// 20261005 Sawaneh G1.4: kontoindstillinger() keeps only the user count; name/reset/delete/time zone moved.
// 20261003 Sawaneh G6.3: the SMTP form left kontoindstillinger() for Indstillinger » Dokumenter & e-mail » E-mail.
// 20261004 Sawaneh G5.8 (B-L1): labels() escapes the label name and the custom text lines, and accepts only valg box1/box2.
// 20261004 Sawaneh G2.6: bilag() and testftp() removed (generated section Finans » Bilagsopbevaring).
// 20261003 Sawaneh G3.4: rykker_valg() removed (generated section Salg » Betalingsbetingelser & rykkere).
// 20261004 Sawaneh Pick-up addresses are edited under Indstillinger » Integrationer » Afhentningsadresser; the list and its script are gone from Diverse valg.
// 20260731 MJ api_valg(): close the <form> also when no eligible API user exists
// 20260924 LOE SD-657 The setting that keeps turnover from users without the Indstillinger right.
// 20260929 CDX/PHR Offer legacy and form-based HTML layout choices beside the generator setting.
// 20261002 LOE SST-844 The Flatpay ID popup sends a CSRF token and only reloads when the save succeeded.
// 20261002 LOE SST-847 The Flatpay ID popup no longer writes the login to the browser console.
// 20261008 Sawaneh Settings 4e: the dead prislister() removed (price lists live in Indstillinger » Prislister).
// 20261010 Sawaneh G5.8: the label template functions moved unchanged to includes/labelTemplates.php (shared with
//                  systemdata/labels.php); labels() stays only for the old page, which now redirects.
// 20261011 Sawaneh The include of sys_div_func_includes/chooseProvision.php removed: the file left with 4a on 2 October
//                  (provision lives in Organisation » Provision) and the include has warned on every load since.
// 20261007 CL/SZ SST-843 The Vibrant API key is no longer printed on Diverse valg; the field shows only its last four characters.
//                Creating a Vibrant login and "Vis login" now go through server-side endpoints instead of calling Vibrant from the page.
//                Both calls show an alert when the answer is not JSON, e.g. after the session expired.
//                (Merge into redesign 23/10: that Diverse valg block is gone here - the settings page's masked 'secret' control replaces it.)

include_once("../includes/connect.php"); 
include_once(__DIR__ . "/../includes/labelTemplates.php");

function kontoindstillinger($regnskab, $skiftnavn)
{
	global $bgcolor, $bgcolor5, $sprog_id, $timezone, $db, $sqdb,$sqhost, $squser,$sqpass;
	#	if (isset($_COOKIE['timezone'])) $timezone=$_COOKIE['timezone'];
	#	else {
	$qtxt = "select id,var_value from settings where var_name='timezone'";
	if (($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) && (isset($r['var_value']))) {
		$timezone = $r['var_value'];
		if ($timezone) {
			date_default_timezone_set($timezone);
			// 20261005 Sawaneh timezone cookie removed (settings redesign G1.3); the setting is read by online.php.
		}
	}
	print "<tr><td colspan='6'><hr></td></tr>\n";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst('783|Kontoindstillinger', $sprog_id)."</u></b></td></tr>\n";
	print "<tr><td colspan='6'><br></td></tr>\n";

	$max_users = 1;
	$masterDb  = $sqdb;
	$disabled  = "";
	@session_start();
	$s_id = session_id();
	include("../includes/connect.php");
		$query = "SELECT brugerantal FROM regnskab WHERE db = '$db'";
		$result = db_select($query, __FILE__ . " linje " . __LINE__);
	
		if ($result) {
			if (db_num_rows($result) > 0) {
				$row = db_fetch_array($result);
				$max_users = (int)$row['brugerantal'];
			}
		}
	$permission_key = 'system.indstillinger';
	include("../includes/online.php");
	if($masterDb == "gratis" || $masterDb == "mini") {
		$disabled = "disabled";
	}
	
	print "<form name='maxusers' action='diverse.php?sektion=kontoindstillinger' onsubmit='return confirmUpdate();' method='post'>\n";
	print "<tr><td>Sæt brugere antal:</td>";
	print "<td><input class='inputbox' type='number' style='width:50px' name='max_users' value='" . htmlspecialchars($max_users) . "' $disabled></td></tr>";
	print "<td></td><td><input class='button gray medium' style='width:200px' type='submit' value='Opdater bruger antal' name='update_max_users'></td></tr>\n";
	print "</form>\n";

	print "<script>
		function confirmUpdate() {
			return confirm('Er du sikker på du vil opdatere bruger antal?');
		}
	</script>\n";

	// 20261005 Sawaneh G1.3/G1.4: name, reset and delete moved to Indstillinger » Virksomhed » Abonnement & konto, the
	// time zone to Lokalisering; only the user count stays here until the billing spec replaces it.
	print "<tr><td colspan='6'><hr></td></tr>\n";
	print "<tr><td colspan='6'>" . findtekst('6645|Flyttet til', $sprog_id) . " <a href='settingsSection.php?s=company.account'>" . findtekst('6630|Abonnement & konto', $sprog_id) . "</a> · <a href='settingsSection.php?s=company.localisation'>" . findtekst('6524|Lokalisering', $sprog_id) . "</a></td></tr>\n";
	print "<tr><td colspan='6'><br></td></tr>\n";
} # endfunc kontoindstillinger



function kontoplan_io() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	$x = 0;
	$q = db_select("select * from grupper where art = 'RA' order by  kodenr", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]          = $r['id'];
		$beskrivelse[$x] = $r['beskrivelse'];
		$kodenr[$x]      = $r['kodenr'];
	}
	$antal_regnskabsaar = $x;
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst('1352|Indlæs/udlæs kontoplan', $sprog_id)."</b></u></td></tr>";
	if ($popup) {
		print "<form name=diverse action=diverse.php?sektion=kontoplan_io method=post>";
		print "<tr><td colspan='2'></td>\n";
		print "<td align=center><SELECT class='inputbox' NAME=regnskabsaar title='".findtekst('1354|Vælg det regnskabsår hvor kontoplanen skal eksporteres fra', $sprog_id)."'>";
#		if ($box3[$x]) print"\t<option>$box3[$x]</option>";
		for ($x = 1; $x <= $antal_regnskabsaar; $x++) {
			print "\t<option>$kodenr[$x] : $beskrivelse[$x]</option>";
		}
		print "</select></td>";
		print "<td align = center><input type=submit style='width: 8em' accesskey='e' value='".findtekst('1355|Eksportér', $sprog_id)."' name='submit'></td><tr>";
		print "<tr><td colspan='3'>".findtekst('1356|Importér', $sprog_id)." ".findtekst('1357|kontoplan (erstatter kontoplanen for nyeste regnskabsår)', $sprog_id)." </td>";
		print "<td align = center><input type=submit style='width: 8em' accesskey='i' value='".findtekst('1356|Importér', $sprog_id)."' name='submit'></td><tr>";
		print "</form>";
	} else {
		print "<tr><td colspan='3'>".findtekst('1355|Eksportér', $sprog_id)." kontoplan</td><td align=center title='".findtekst('1354|Vælg det regnskabsår hvor kontoplanen skal eksporteres fra', $sprog_id)."'>";
#		if ($box3[$x]) {
#			print "<form form name=exporter$kodenr[$x] action='exporter_kontoplan.php?aar=$box3[$x]' method='post'>\n";
#			print"<input type='submit' style='width: 8em' value='$box3[$x]'><br>\n";
#			print "</form>\n";
#		}
		for ($x = 1; $x <= $antal_regnskabsaar; $x++) {
			print "";
			print "<form name=exporter$kodenr[$x] action=exporter_kontoplan.php?aar=$kodenr[$x] method=post><input class='button gray medium' type='submit' style='width: 8em' value='$beskrivelse[$x]'></form>\n";
		}
		print "";
		print "</td></tr>\n\n";
		print "<tr><td colspan='3'>".findtekst('1356|Importér', $sprog_id)." ".findtekst('1357|kontoplan (erstatter kontoplanen for nyeste regnskabsår)', $sprog_id)." </td>";
		print "<td align = center><form action='importer_kontoplan.php'><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst('1356|Importér', $sprog_id)."' accesskey='i'></form></td><tr>";
		print "<tr><td colspan='3'>".findtekst('2336|Importer mappingfil til offentlig standard kontoplan', $sprog_id)."</td>";
		print "<td align = center><form action='importAccountMap.php'><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst('1356|Importér', $sprog_id)."' accesskey='i'></form></td><tr>";
#		print "<td align = center><a href='importer_kontoplan.php' style='text-decoration:none' accesskey='i'>Import&eacute;r</a></td><tr>";
	}
#	print "</tbody></table></td></tr>";

} # endfunc kontoplan_io

function kreditor_io() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	$x = 0;
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst('1360|Indlæs', $sprog_id)."/".findtekst('1361|Udlæs', $sprog_id)." ".findtekst('607|Kreditor', $sprog_id)."</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>".findtekst('1355|Eksportér', $sprog_id)." ".findtekst('607|Kreditor', $sprog_id)."</td>";
	if ($popup)	print "<form name=diverse action=diverse.php?sektion=kreditor_io method=post>";
	else print "<form name=diverse action=exporter_kreditor.php method=post>";
	print "<td align = center><input class='button gray medium' type=submit style='width: 8em' value='".findtekst('1355|Eksportér', $sprog_id)."' name='submit'></td><tr>\n\n";
	print "<tr><td colspan='3'>".findtekst('1356|Importér', $sprog_id)." ".findtekst('607|Kreditor', $sprog_id)." </td>\n";
	print "</form>";
	if ($popup)	print "<form name=diverse action=diverse.php?sektion=kreditor_io method=post>";
	else print "<form name=diverse action=importer_kreditor.php method=post>";
	print "<td align = center><input class='button blue medium' type=submit style='width: 8em' value='".findtekst('1356|Importér', $sprog_id)."' name='submit'></td><tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";
} # endfunc kreditor_io
function formular_io() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	$x = 0;
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst('1360|Indlæs', $sprog_id)." ".findtekst('780|Formularer', $sprog_id)."</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>".findtekst('1355|Eksportér', $sprog_id)." ".findtekst('780|Formularer', $sprog_id)."</td>";
	if ($popup)	print "<form name=diverse action=diverse.php?sektion=formular_io method=post>";
	else print "<form name=diverse action=exporter_formular.php method=post>";
	print "<td align = center><input class='button gray medium' type=submit style='width: 8em' value='".findtekst('1355|Eksportér', $sprog_id)."' name='submit'></td><tr>\n\n";
	print "</form>";
	print "<tr><td><br></td></tr>";
	print "<tr><td colspan='3'>".findtekst('1356|Importér', $sprog_id)." ".findtekst('780|Formularer', $sprog_id)."</td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=formular_io method=post>";
	else print "<form name=diverse action=importer_formular.php method=post>";
	print "<td align = center><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst('1356|Importér', $sprog_id)."'></td></tr>\n\n";
	print "</form>";
} # endfunc formular_io

function varer_io() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	$x = 0;
#	print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst('1360|Indlæs', $sprog_id)." ".findtekst('609|Varer', $sprog_id)."</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>".findtekst('1355|Eksportér', $sprog_id)." ".findtekst('609|Varer', $sprog_id)."</td>";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	else print "<td align = center><a href='exporter_varer.php' style='text-decoration:none'><input class='button gray medium' type='button' style='width: 8em'  value='".findtekst('1355|Eksportér', $sprog_id)."'></a></td></tr>\n\n";
	print "<tr><td colspan='3'>".findtekst('1356|Importér', $sprog_id)." ".findtekst('609|Varer', $sprog_id)."</td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	else print "<form name=diverse action=importer_varer.php method=post>";
	print "<td align = center><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst('1356|Importér', $sprog_id)."'></td></tr>\n\n";
	print "</form>";
	$r = db_fetch_array(db_select("select count(id) lagerantal from grupper where art='LG'", __FILE__ . " linje " . __LINE__));
	if ($r['lagerantal']) {
		print "<tr><td colspan='3'>".findtekst('1356|Importér', $sprog_id)." varelokationer</td>\n";
		print "<form name='diverse' action='importer_varelokationer.php' method='post'>";
		print "<td align = center><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst('1356|Importér', $sprog_id)."'></td></tr>\n\n";
		print "</form>";
	}
/*
	print "<tr><td colspan='3'>Import&eacute;r VVSpris fil fra Solar </td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=solar_io method=post>";
	else print "<form name=diverse action=solarvvs.php?sektion=solar_io method=post>";
	print "<td align = center><input type=submit style='width: 8em' value='Import&eacute;r' name='submit'></td><tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";
*/
} # endfunc varer_io
function variantvarer_io() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	$x = 0;
#	print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst('1360|Indlæs', $sprog_id)." ".findtekst('1359|Variantvarer', $sprog_id)."</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>".findtekst('1355|Eksportér', $sprog_id)." ".findtekst('1359|Variantvarer', $sprog_id)."</td>";
	if ($popup) print "<td align = center><input class='button gray medium' type=submit accesskey='e' value='".findtekst('1355|Eksportér', $sprog_id)."' name='submit'></td><tr>\n\n";
	else print "<td align = center><a href='exporter_variantvarer.php' style='text-decoration:none'><input class='button gray medium' type='button' style='width: 8em'  value='".findtekst('1355|Eksportér', $sprog_id)."'></a></td></tr>\n\n";
	print "<tr><td colspan='3'>".findtekst('1356|Importér', $sprog_id)." ".findtekst('1359|Variantvarer', $sprog_id)."</td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=variantvarer_io method=post>";
	else print "<form name=diverse action=importer_variantvarer.php method=post>";
	print "<td align = center><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst('1356|Importér', $sprog_id)."'></td></tr>\n\n";
	print "</form>";
/*
	print "<tr><td colspan='3'>Import&eacute;r VVSpris fil fra Solar </td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=solar_io method=post>";
	else print "<form name=diverse action=solarvvs.php?sektion=solar_io method=post>";
	print "<td align = center><input type=submit style='width: 8em' value='Import&eacute;r' name='submit'></td><tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";
*/
} # endfunc variantvarer_io
function adresser_io() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	$x = 0;
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst('1360|Indlæs', $sprog_id)."/".findtekst('1361|Udlæs', $sprog_id)." ".findtekst('908|Debitorer', $sprog_id)."/".findtekst('607|Kreditor', $sprog_id)."</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>".findtekst('1355|Eksportér', $sprog_id)." ".findtekst('908|Debitorer', $sprog_id)."/".findtekst('607|Kreditor', $sprog_id)."</td>";
	if ($popup) {
		print "<form name=diverse action=diverse.php?sektion=adresser_io method=post>";
		print "<td align = center><input type=submit accesskey='e' style='width: 8em' value='".findtekst('1355|Eksportér', $sprog_id)."' name='submit'></td><tr>";
		print "<tr><td colspan='3'>".findtekst('1356|Importér', $sprog_id)." ".findtekst('908|Debitorer', $sprog_id)."/".findtekst('607|Kreditor', $sprog_id)."</td>";
		print "<td align = center><input type=submit accesskey='i' style='width: 8em' value='".findtekst('1356|Importér', $sprog_id)."' name='submit'></td><tr>";
		print "</form>";
	} else {
		print "<td align = center><form name=impdeb action='exporter_adresser.php'><input class='button gray medium' type='submit' style='width: 8em' value='".findtekst('1355|Eksportér', $sprog_id)."'></form></td></tr>\n\n";
		print "<tr><td colspan='3'>".findtekst('1356|Importér', $sprog_id)." ".findtekst('908|Debitorer', $sprog_id)."/".findtekst('607|Kreditor', $sprog_id)."</td>";
		print "<td align = center><form name=expdeb action='importer_adresser.php'><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst('1356|Importér', $sprog_id)."'></form></td></tr>\n\n";
	}
#	print "</tbody></table></td></tr>";

} # endfunc adresser_io



#require("englishfile.php");




function jobkort () {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$x = 0;
	$q = db_select("select * from grupper where art = 'JOBKORT' order by kodenr", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]          = $r['id'];
		$beskrivelse[$x] = $r['beskrivelse'];
		$kodenr[$x]      = $r['kodenr'];
		$sprogkode[$x]   = $r['box1'];
	}
	$antal_sprog = $x;
	print "<form name=diverse action=diverse.php?sektion=sprog method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>xSprog</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	$tekst1 = findtekst('1|Dansk', $sprog_id);
	$tekst2 = findtekst('2|Vælg aktivt sprog', $sprog_id);
	print "<tr><td>	$tekst1</td><td><SELECT class='inputbox' NAME=sprog title='$tekst2'>";
	if ($box3[$x]) print "<option>$box3[$x]</option>";
	for ($x = 1; $x <= $antal_sprog; $x++) {
		print "<option>$beskrivelse[$x]</option>";
	}
	print "</SELECT></td></tr>";
	print "<tr><td><br></td></tr>";
	$tekst1 = findtekst('3|Gem', $sprog_id);
	print "<tr><td align = right colspan='4'><input type=submit value='$tekst1' name='submit'></td></tr>";
#	print "<td align = center><input type=submit value='$tekst2' name='submit'></td>";
#	print "<td align = center><input type=submit value='$tekst3' name='submit'></td><tr>";
/*
	print "</tbody></table></td></tr>";
*/
	print "</form>";
} # endfunc jobkort 


function div_valg() {
	global $bgcolor, $bgcolor5;
	global $regnaar;
	global $sprog_id;

	$batch = $ebconnect = $extra_ansat = $forskellige_datoer = NULL;
	$dfm_agree = NULL;
	$gruppevalg = $jobkort = $kort = $kuansvalg = $ref = $kua = $smart = $debtor2orderphone = NULL;
	$payment_days = NULL;

	$q           = db_select("select * from grupper where art = 'DIV' and kodenr = '2'", __FILE__ . " linje " . __LINE__);
	$r           = db_fetch_array($q);
	$id          = $r['id'];
	$beskrivelse = $r['beskrivelse'];
	$kodenr      = $r['kodenr'];
	$box1        = $r['box1'];
	$box2        = $r['box2'];
	$box3        = $r['box3'];
	$box4        = $r['box4'];
	$box5        = $r['box5'];
	$box6        = $r['box6'];
	$box7        = $r['box7'];
	$box8        = $r['box8'];
	$box9        = $r['box9'];
	$box10       = $r['box10'];
	if ($box1 == 'on') $gruppevalg = "checked";
	if ($box2 == 'on') $kuansvalg = "checked";
	if ($box3 == 'on') $extra_ansat = "checked";
	if ($box4 == 'on') $forskellige_datoer = "checked";
	if ($box5 == 'on') $debtor2orderphone = "checked";
	if ($box7 == 'on') $jobkort = "checked";
	// if ($box8) $ebconnect = "checked";
	if ($box8 == 'on') $payment_days = "checked";
	if ($box9 == 'on') $ledig = "checked"; # ledig
#	if ($box10 == 'on') $betalingsliste = "checked";


	print "<form name='diverse' id='diverse' action='diverse.php?sektion=div_valg' method='post'>\n";
	print "<tr style='background-color:$bgcolor5'><td colspan='6'><b>".findtekst('794|Diverse valg', $sprog_id)."</b></td></tr>\n";
	print "<tr><td colspan='2'>&nbsp;</td></tr>\n";
	print "<input name='id' type='hidden' value='$id'>\n";
	print "<tr bgcolor='$bgcolor5'>\n<td title='".findtekst('615|Ved at afmærke her får du op til 14 ekstra felter på ansattes stamkort', $sprog_id)."'>".findtekst('616|Tilføj ekstra felter på ansatte', $sprog_id)."</td>\n";
	print "<td title='".findtekst('615|Ved at afmærke her får du op til 14 ekstra felter på ansattes stamkort', $sprog_id)."'>\n";
	print "<!-- 616 : Tilføj ekstra felter på ansatte -->";
	print "<input name='box3' class='inputbox' type='checkbox' $extra_ansat>\n";
	print "</td></tr>\n";

		if (strpos(findtekst('841|Kreditor kontonummer til inkassoselskab', $sprog_id),'kortet er et betalingskort')) {
		db_modify("delete from tekster where (tekst_id='841' or tekst_id='642') and sprog_id='$sprog_id'");
	}
	// print "</td></tr>\n";
	#	print "<tr>\n<td title='".findtekst(642, $sprog_id)."'>".findtekst('841|Kreditor kontonummer til inkassoselskab', $sprog_id)."</td>\n";
	#	print "<td title='".findtekst(642, $sprog_id)."'>\n";
	#	print "    <input name='box5' class='inputbox' type='text' style='width:150px;' placeholder='' value=\"$box5\">\n";
	#	print "</td></tr>\n"; #20131101
	// print "<tr bgcolor='$bgcolor'>\n<td title='".findtekst('527|Afmærk her hvis du har en ftp-konto hos ebConnect og ønsker at kunne sende OIOUBL-fakturaer direkte til modtager.', $sprog_id)."'>".findtekst('526|Integration med ebConnect', $sprog_id)."</td>\n";
	// print "<td title='".findtekst('527|Afmærk her hvis du har en ftp-konto hos ebConnect og ønsker at kunne sende OIOUBL-fakturaer direkte til modtager.', $sprog_id)."'>\n";
	// print "<!-- 526 : Integration med ebConnect -->";
	// print "<input name='box8' class='inputbox' type='checkbox' $ebconnect>\n";
	// print "</td></tr>\n";
	// if ($box8) {
	// 	list($oiourl, $oiobruger, $oiokode) = explode(chr(9), $box8);
	// 	print "<tr bgcolor='$bgcolor'>\n<td title=''>" . findtekst(528, $sprog_id) . "</td>\n";
	// 	print "<td><input name='oiourl' class='inputbox' style='width:150px;' type='text' value='$oiourl'></td>\n</tr>\n";
	// 	print "<tr>\n<td title=''>" . findtekst(529, $sprog_id) . "</td>\n";
	// 	print "<td><input name='oiobruger' class='inputbox' style='width:150px;' type='text' value='$oiobruger'></td>\n</tr>\n";
	// 	print "<tr>\n<td title=''>" . findtekst(530, $sprog_id) . "</td>\n";
	// 	print "<td><input name='oiokode' class='inputbox' style='width:150px;' type='password' value='$oiokode'></td>\n</tr>\n";
	// }

	print "<tr><td colspan='2'>&nbsp;</td></tr>";
	print "<tr><td colspan='2' style='text-align:center'>\n";
	print "     <input class='button green medium' name='submit' type=submit accesskey='g' value='".findtekst('471|Gem/opdatér', $sprog_id)."'>\n";
	print "</td></tr>\n";
	print "</form>\n\n";

} # endfunc div_valg

function ordre_valg() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	global $regnaar;
	global $bruger_id;

	$hurtigfakt = $incl_moms_private = $incl_moms_business = $folge_s_tekst = $negativt_lager = $straks_bogf = $vis_nul_lev = $orderNoteEnabled = NULL;

	$r           = db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '3'", __FILE__ . " linje " . __LINE__));
	$id          = $r['id'];
	$beskrivelse = $r['beskrivelse'];
	$kodenr      = $r['kodenr'];
	
	// Store the original grupper data in a separate variable
	$grupper_data = $r;
	
	// Read VAT options from settings table
	$qtxt = "select var_value from settings where var_name='vatPrivateCustomers' and var_grp='ordre'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		if ($r['var_value']) $incl_moms_private = 'checked';
	}
	
	$qtxt = "select var_value from settings where var_name='vatBusinessCustomers' and var_grp='ordre'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		if ($r['var_value']) $incl_moms_business = 'checked';
	}

	// SD-657: while this is on, users without the Indstillinger right are kept out of turnover and Finans.
	$hideRevenue = (get_settings_value('hideRevenue', 'finans', 'off') === 'on') ? 'checked' : '';
	$rabatvareid = (int)$grupper_data['box2'];
	($grupper_data['box3'] == 'on') ? $folge_s_tekst = "checked" : $folge_s_tekst = NULL;
	($grupper_data['box4'] == 'on') ? $hurtigfakt = "checked" : $hurtigfakt = NULL;
	if (strstr($grupper_data['box5'], ';')) {
		list($straks_deb, $straks_kred) = explode(';', $grupper_data['box5']); #20170404
	} else {
		$straks_deb  = $grupper_data['box5'];
		$straks_kred = $grupper_data['box5'];
	}
	($straks_deb == 'on') ? $straks_deb = 'checked' : $straks_deb = NULL;
	($straks_kred == 'on') ? $straks_kred = 'checked' : $straks_kred = NULL;
	($grupper_data['box6'] == 'on') ? $fifo = "checked" : $fifo = NULL;
	$kontantkonto = $grupper_data['box7'];
	($grupper_data['box8'] == 'on') ? $vis_nul_lev = "checked" : $vis_nul_lev = NULL;
	($grupper_data['box9'] == 'on') ? $negativt_lager = "checked" : $negativt_lager = NULL;
	$kortkonto = $grupper_data['box10'];
	($grupper_data['box11'] == 'on') ? $advar_lav_beh = "checked" : $advar_lav_beh = NULL;
	($grupper_data['box12'] == 'on') ? $procentfakt = "checked" : $procentfakt = NULL;
	list($procenttillag, $procentvare) = explode(chr(9), $grupper_data['box13']);
	($grupper_data['box14'] == 'on') ? $samlet_pris = "checked" : $samlet_pris = NULL;

	$qtxt = "select var_value from settings where var_name='orderNoteEnabled'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		if ($r['var_value']) $orderNoteEnabled = 'checked';
	} else {
		$orderNoteEnabled = NULL;
	}

	$portovarenr = get_settings_value("porto_varnr", "ordre", "");
	$debitoripad = get_settings_value("debitoripad", "ordre", "off");
	$showDB      = get_settings_value("showDB", "ordre", "");
	$showDG      = get_settings_value("showDG", "ordre", "");
	$lockPayment = get_settings_value("lockedInvoiceButton", "debitor", "");
	if($lockPayment === "on"){
		$lockPayment = "checked";
	}
	if ($showDB === "on") {
		$showDB = "checked";
	}
	if ($showDG === "on") {
		$showDG = "checked";
	}
	if ($debitoripad === "on") {
		$debitoripad = "checked";
	}

	$pluklisteEmail    = get_settings_value("pluklisteEmail", "ordre", "");
	$ordreAutocomplete = get_settings_value("ordreAutocomplete", "ordre", "on", $bruger_id);
	if ($ordreAutocomplete === "on") {
		$ordreAutocomplete = "checked";
	}
	$gs1parsing          = get_settings_value("gs1_parsing", "ordre", "off") === "on" ? "checked" : "";
	$ourRefStockSwitch   = get_settings_value("ourRefStockSwitch", "ordre", "off") === "on" ? "checked" : "";
	$stockWarningEnabled = get_settings_value("stockWarningEnabled", "ordre", "off") === "on" ? "checked" : "";
	$showBothAddrExtra   = get_settings_value("showBothAddrExtra", "ordre", "off") === "on" ? "checked" : "";

	$rabatvarenr = NULL;
	if ($rabatvareid) {
		$qtxt = "select varenr from varer where id = '$rabatvareid'";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $rabatvarenr = $r['varenr'];
	}
	#	print "<tr><td colspan='6'><br></td></tr>";
#	print "<tr><td title='".findtekst('732|Vælg om kostpriser skal reguleres til gennemsnitspris', $sprog_id)."'>".findtekst('731|Aut. regulering af kostpriser', $sprog_id)."</td><td title='".findtekst('732|Vælg om kostpriser skal reguleres til gennemsnitspris', $sprog_id)."'>
#		<input name='box6' type='checkbox' $box6></td></tr>";

	$r = db_fetch_array(db_select("select box6,box8 from grupper where art = 'DIV' and kodenr = '5'", __FILE__ . " linje " . __LINE__));
	# OBS $box1,2,3,4,5,7,9 bruges under shop valg!!
	$kostmetode=$r['box6']; #0=opdater ikke kostpris,1=snitpris;2=sidste_købspris
	$kostbeskrivelse[0] = findtekst('2527|Opdater ikke kostpris', $sprog_id);
	$kostbeskrivelse[1] = findtekst('2528|Gennemsnitspris', $sprog_id);
	$kostbeskrivelse[2] = findtekst('2529|Genanskaffelsespris', $sprog_id);
	$saetvareid = $r['box8'];
	if ($saetvareid) {
		$r = db_fetch_array(db_select("select varenr from varer where id = '$saetvareid'", __FILE__ . " linje " . __LINE__));
		$saetvarenr = $r['varenr'];
	}

	print "<form name=diverse action=diverse.php?sektion=ordre_valg method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst('786|Ordrerelaterede valg', $sprog_id)."</u></b></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	$qtxt = "select box12 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'";
	if($r = db_fetch_array(db_select($qtxt, __FILE__ . "linje " . __LINE__)))
		print "<tr><td title='".findtekst('3356|Hvis dette felt afmærkes, låses fakturér-knappen hvis ordren ikke er betalt', $sprog_id)."'>".findtekst('3357|Lås fakturér-knappen, hvis ordren ikke er betalt', $sprog_id)."</td><td><input type='checkbox' class='checkbox' name='lockPayment' $lockPayment><td></tr>";

	print "<tr><td title='Hvis dette felt afmærkes vises priser inkl. moms på salgsordrer'>Vis priser inkl. moms på kundeordrer (private kunder)</td><td><INPUT title='Hvis dette felt afmærkes vises priser inkl. moms på salgsordrer' class='inputbox' type='checkbox' name=vatPrivateCustomers $incl_moms_private></td></tr>";
	print "<tr><td title='Hvis dette felt afmærkes vises priser inkl. moms på salgsordrer'>Vis priser inkl. moms på kundeordrer (erhvervskunder)</td><td><INPUT title='Hvis dette felt afmærkes vises priser inkl. moms på salgsordrer' class='inputbox' type='checkbox' name=vatBusinessCustomers $incl_moms_business></td></tr>";
	print "<tr><td title='".findtekst('188|Hvis dette felt afmærkes inkluderes kommentarlinjer fra tilbud/ordrer på følgesedler', $sprog_id)."'>".findtekst('164|Medtag kommentarer på følgesedler', $sprog_id)."</td><td><INPUT title='".findtekst('188|Hvis dette felt afmærkes inkluderes kommentarlinjer fra tilbud/ordrer på følgesedler', $sprog_id)."' class='inputbox' type='checkbox' name=box3 $folge_s_tekst></td></tr>";
	print "<tr><td title='".findtekst('189|Hvis dette felt afmærkes inkluderes kun linjer med angivet antal på følgesedler', $sprog_id)."'>".findtekst('169|Medtag kun linjer med antal på følgeseddel', $sprog_id)."</td><td><INPUT title='".findtekst('189|Hvis dette felt afmærkes inkluderes kun linjer med angivet antal på følgesedler', $sprog_id)."' class='inputbox' type='checkbox' name=box8 $vis_nul_lev></td></tr>";
	$qtxt = "select id from grupper where art = 'VG' and box9='on'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $hurtigfakt = "onclick='return false'";
	print "<tr><td title='".findtekst('190|Hurtigfakturering anvendes, hvis der ikke er behov for tilbud/følgesedler', $sprog_id)."'>".findtekst('165|Anvend hurtigfakturering (ingen tilbud & automatisk levering ved fakturering)', $sprog_id)."</td><td><INPUT title='".findtekst('190|Hurtigfakturering anvendes, hvis der ikke er behov for tilbud/følgesedler', $sprog_id)."' class='inputbox' type='checkbox' name='box4' $hurtigfakt></td></tr>";
	print "<tr><td title='".findtekst('191|Hvis dette felt ikke er afmærket, skal salgsfakturaer bogføres via kassekladden med [Hent ordrer]-funktionen', $sprog_id)."'>".findtekst('166|Omgående bogføring af salgsordrer', $sprog_id)."</td><td><INPUT title='".findtekst('191|Hvis dette felt ikke er afmærket, skal salgsfakturaer bogføres via kassekladden med [Hent ordrer]-funktionen', $sprog_id)."' class='inputbox' type='checkbox' name='straks_deb' $straks_deb></td></tr>";
	print "<tr><td title='".findtekst('214|Hvis dette felt ikke er afmærket, skal købsfakturaer bogføres via kassekladden med [Hent ordrer]-funktionen', $sprog_id)."'>".findtekst('213|Omgående bogføring af købsordrer', $sprog_id)."</td><td><INPUT title='".findtekst('214|Hvis dette felt ikke er afmærket, skal købsfakturaer bogføres via kassekladden med [Hent ordrer]-funktionen', $sprog_id)."' class='inputbox' type='checkbox' name='straks_kred' $straks_kred></td></tr>";
	print "<tr><td title='".findtekst('313|Hvis dette felt er afmærket styres lager efter FIFO (first in first out) princippet, og kostprisen reguleres automatisk efter sidste varekøb.', $sprog_id)."'>".findtekst('314|Anvend FIFO på lagervarer', $sprog_id)."</td><td><INPUT title='".findtekst('313|Hvis dette felt er afmærket styres lager efter FIFO (first in first out) princippet, og kostprisen reguleres automatisk efter sidste varekøb.', $sprog_id)."' class='inputbox' type='checkbox' name='box6' $fifo></td></tr>";
	print "<tr><td title='".findtekst('732|Vælg om kostpriser skal reguleres til gennemsnitspris, genanskaffelsespris eller ikke skal justeres', $sprog_id)."'>".findtekst('731|Aut. regulering af kostpriser', $sprog_id)."</td><td colspan='1'><SELECT title='".findtekst('732|Vælg om kostpriser skal reguleres til gennemsnitspris, genanskaffelsespris eller ikke skal justeres', $sprog_id)."'class='inputbox' name='kostmetode'>";
	for ($i = 0; $i < 3; $i++) {
		if ($i == $kostmetode) print "<option value=$i>$kostbeskrivelse[$i]</option>";
	}
	for ($i = 0; $i < 3; $i++) {
		if ($i != $kostmetode) print "<option value=$i>$kostbeskrivelse[$i]</option>";
	}
	print "</SELECT></td></tr>";
	if ($kostmetode >= 1) {
		print "<tr><td></td><td colspan='2'><a href='../includes/opdat_kostpriser.php?metode=$kostmetode' target='blank'><INPUT title='".findtekst('738|Klik her for at opdatere kostprisen for alle lagervarer med pris på sidste køb.', $sprog_id)."' type='button' value='".findtekst('739|Opdater kostpriser', $sprog_id)."'></a></td>";
	}
	print "</tr>";
	print "<tr><td title='".findtekst('192|Afmærk dette felt for at tillade negativ lagerbeholdning', $sprog_id)."'>".findtekst('183|Tillad negativ lagerbeholdning', $sprog_id)."</td><td><INPUT title='".findtekst('192|Afmærk dette felt for at tillade negativ lagerbeholdning', $sprog_id)."' class='inputbox' type='checkbox' name='box9' $negativt_lager></td></tr>";
	print "<tr><td title='".findtekst('743|Afmærkes dette felt, bliver det muligt at ændre prisen på bundlinjen i en salgsordre, og der bliver givet en samlet rabat, som ved postering fordeles på de enkelte varer', $sprog_id)."'>".findtekst('742|Anvend samlet pris', $sprog_id)."</td><td><INPUT title='".findtekst('743|Afmærkes dette felt, bliver det muligt at ændre prisen på bundlinjen i en salgsordre, og der bliver givet en samlet rabat, som ved postering fordeles på de enkelte varer', $sprog_id)."' class='inputbox' type='checkbox' name='box14' $samlet_pris></td></tr>";
	print "<tr><td title='".findtekst('680|Afmærkes dette felt, vil der komme en advarsel hvis lagerbeholdningen er for lav når et produkt indsættes', $sprog_id)."'>".findtekst('714|Advar ved for lav lagerbeholdning', $sprog_id)."</td><td><INPUT title='".findtekst('680|Afmærkes dette felt, vil der komme en advarsel hvis lagerbeholdningen er for lav når et produkt indsættes', $sprog_id)."' class='inputbox' type='checkbox' name='box11' $advar_lav_beh></td></tr>";
	print "<tr><td title='".findtekst('682|Afmærkes dette felt, vil et ekstra felt vises på kundebestillinger for procentfakturering af vareværdien. Dette bruges f.eks. ved udlejning af udstyr.', $sprog_id)."'>".findtekst('681|Anvend procentfakturering', $sprog_id)."</td><td><INPUT title='".findtekst('682|Afmærkes dette felt, vil et ekstra felt vises på kundebestillinger for procentfakturering af vareværdien. Dette bruges f.eks. ved udlejning af udstyr.', $sprog_id)."' class='inputbox' type='checkbox' name='box12' $procentfakt></td></tr>";
	print "<tr><td title='".findtekst('684|Skrives en værdi her, vises et redigerbart felt på ordre-siden med den angivne værdi. Procenttillægget er et tillæg til det samlede fakturabeløb før momsberegning.', $sprog_id)."'>".findtekst('683|Procenttillæg', $sprog_id)."</td><td><INPUT title='".findtekst('684|Skrives en værdi her, vises et redigerbart felt på ordre-siden med den angivne værdi. Procenttillægget er et tillæg til det samlede fakturabeløb før momsberegning.', $sprog_id)."' class='inputbox' type='text' style='width:35px;text-align:right;' name='procenttillag' value='$procenttillag'>%</td></tr>";
	print "<tr><td title='".findtekst('686|Angiv her hvilken konto i kontoplanen procenttillægget skal konteres på', $sprog_id)."'>".findtekst('685|Varenr. for procenttillæg', $sprog_id)."</td><td><INPUT title='".findtekst('686|Angiv her hvilken konto i kontoplanen procenttillægget skal konteres på', $sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='procentvare' value='$procentvare'></td></tr>";
	print "<tr><td title='".findtekst('288|For at kunne give rabat på kontantsalg, skal dette felt udfyldes med varenummeret på den vare, der bruges til formålet', $sprog_id)."'>".findtekst('287|Varenr. for rabat', $sprog_id)."</td><td><INPUT title='".findtekst('288|For at kunne give rabat på kontantsalg, skal dette felt udfyldes med varenummeret på den vare, der bruges til formålet', $sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='box2' value='$rabatvarenr'></td></tr>";
	if ($samlet_pris) print "<tr><td title='".findtekst('745|Angives der et varenummer her, bliver det muligt at samle en gruppe varer i en salgsordre som et sæt og give en samlet pris for denne gruppe', $sprog_id)."'>".findtekst('744|Varenr. for sæt', $sprog_id)."</td><td><INPUT title='".findtekst('745|Angives der et varenummer her, bliver det muligt at samle en gruppe varer i en salgsordre som et sæt og give en samlet pris for denne gruppe', $sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='saetvarenr' value='$saetvarenr'></td></tr>";
	print "<tr><td title='".findtekst('688|Angiv hvilken konto betalingen skal konteres på ved kontantsalg. Hvis feltet er tomt oprettes en åben post på beløbet på kundens konto.', $sprog_id)."'>".findtekst('687|Kontonummer for kontantsalg', $sprog_id)."</td><td><INPUT title='".findtekst('688|Angiv hvilken konto betalingen skal konteres på ved kontantsalg. Hvis feltet er tomt oprettes en åben post på beløbet på kundens konto.', $sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='box7' value='$kontantkonto'></td></tr>";
	print "<tr><td title='".findtekst('690|Angiv hvilken konto betalingen skal konteres på ved salg på kreditkort. Hvis feltet er tomt oprettes en åben post på beløbet på kundens konto.', $sprog_id)."'>".findtekst('689|Kontonummer for salg på kreditkort', $sprog_id)."</td><td><INPUT title='".findtekst('690|Angiv hvilken konto betalingen skal konteres på ved salg på kreditkort. Hvis feltet er tomt oprettes en åben post på beløbet på kundens konto.', $sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='box10' value='$kortkonto'></td></tr>";
	print "<tr><td title='".findtekst('1711|Afmærk dette felt for at bruge ordrebemærkning til intern brug', $sprog_id)."'>".findtekst('1714|Anvend ordrebemærkning til internt brug', $sprog_id)."</td><td><INPUT title='".findtekst('1712|Bemærkning til ordre', $sprog_id)."' class='inputbox' type='checkbox' name='orderNoteEnabled' $orderNoteEnabled></td></tr>";
	print "<tr><td title='".findtekst('2370|Dette felt aktiverer debitoripadsystemet hvor dine kunder selv kan skrive en e-mail på en ordre', $sprog_id)."'>".findtekst('2369|Aktiver debitoripad', $sprog_id)."</td><td><INPUT title='".findtekst('2370|Dette felt aktiverer debitoripadsystemet hvor dine kunder selv kan skrive en mail på en ordre', $sprog_id)."' class='inputbox' type='checkbox' name='debitoripad' $debitoripad></td></tr>";
	print "<tr><td title='".findtekst('690|Angiv hvilken konto betalingen skal konteres på ved salg på kreditkort. Hvis feltet er tomt oprettes en åben post på beløbet på kundens konto.', $sprog_id)."'>".findtekst('2400|Ordrebek', $sprog_id)."</td><td><INPUT title='".findtekst('2401|Overblik', $sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='portovarenr' value='$portovarenr'></td></tr>";
	print "<tr><td title='Dette felt deaktiverer visning af DB på ordre siden'>Skjul dækningsbidrag</td><td><INPUT title='Dette felt deaktiverer visning af DB på ordre siden', class='inputbox' type='checkbox' name='showDB' $showDB></td></tr>";
	print "<tr><td title='Dette felt deaktiverer visning af DG på ordre siden'>Skjul dækningsgrad</td><td><INPUT title='Dette felt deaktiverer visning af DG på ordre siden', class='inputbox' type='checkbox' name='showDG' $showDG></td></tr>";
	print "<tr><td title='Angiv en e-mail adresse til modtagelse af pluklister'>Plukliste email</td><td><INPUT title='E-mail adresse til at sende pluklister til' class='inputbox' type='email' style='width:200px;' name='pluklisteEmail' value='$pluklisteEmail'></td></tr>";
	print "<tr><td title='Aktiverer autosøgning/autocomplete på ordresider (bruger specifik indstilling)'>Anvend autosøgning på ordrer</td><td><INPUT title='Aktiverer autosøgning/autocomplete på ordresider' class='inputbox' type='checkbox' name='ordreAutocomplete' $ordreAutocomplete></td></tr>";
	print "<tr><td title='".findtekst('5033|Aktiverer GS1 stregkode-fortolkning ved varesøgning på ordrelinjer (understøtter GTIN, udløbsdato, serienummer m.m.)', $sprog_id)."'>".findtekst('5032|Anvend GS1 stregkodefortolkning', $sprog_id)."</td><td><INPUT title='".findtekst('5033|Aktiverer GS1 stregkode-fortolkning ved varesøgning på ordrelinjer (understøtter GTIN, udløbsdato, serienummer m.m.)', $sprog_id)."' class='inputbox' type='checkbox' name='gs1_parsing' $gs1parsing></td></tr>";
	$ourRefStockTitle = htmlspecialchars(findtekst("5035|Hvis dette felt afmærkes opdateres lageret på ordren ud fra 'Vores ref.' når feltet ændres. Det er slået fra som standard, så andre databaser ikke påvirkes.", $sprog_id), ENT_QUOTES);
	print "<tr><td title='$ourRefStockTitle'>".findtekst("5034|Vælg en anden vare, hvis lageret på 'Vores ref.' er tomt", $sprog_id)."</td><td><INPUT title='$ourRefStockTitle' class='inputbox' type='checkbox' name='ourRefStockSwitch' $ourRefStockSwitch></td></tr>";
	$stockWarningTitle = htmlspecialchars(findtekst('5037|Aktiverer popup-advarsel og krav om begrundelse ved salg af udsolgte varer i både POS og Debitor/Ordre. Godkendelsen logges på ordren.', $sprog_id), ENT_QUOTES);
	print "<tr><td title='$stockWarningTitle'>".findtekst('5036|Advar ved salg af udsolgte varer (popup + begrundelse)', $sprog_id)."</td><td><INPUT title='$stockWarningTitle' class='inputbox' type='checkbox' name='stockWarningEnabled' $stockWarningEnabled></td></tr>";
	print "<tr><td title='".findtekst('5039|Vis både leveringsadresse og ekstrafelter samtidigt på åbne ordrer', $sprog_id)."'>".findtekst('5038|Vis både leveringsadresse og ekstrafelter på åbne ordrer', $sprog_id)."</td><td><INPUT title='".findtekst('5039|Vis både leveringsadresse og ekstrafelter samtidigt på åbne ordrer', $sprog_id)."' class='inputbox' type='checkbox' name='showBothAddrExtra' $showBothAddrExtra></td></tr>";
	#	print "<tr><td title='".findtekst('3117|Angiv antallet af decimaler på rabatfelter på ordrer', $sprog_id)."'>".findtekst('3116|Decimaler på rabat', $sprog_id)."</td><td><INPUT title='".findtekst('3117|Angiv antallet af decimaler på rabatfelter på ordrer', $sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='rabatdecimal' value='$rabatdecimal'></td></tr>";
	print "<tr><td title='".findtekst('5247|Skjul omsætning på ordrelisten og i kasseoptællingen for brugere uden rettigheden Indstillinger. Brugere med rettigheden ser uændret.', $sprog_id)."'>".findtekst('5246|Skjul omsætning for brugere uden adgang til Indstillinger', $sprog_id)."</td><td><INPUT title='".findtekst('5246|Skjul omsætning for brugere uden adgang til Indstillinger', $sprog_id)."' class='inputbox' type='checkbox' name='hideRevenue' $hideRevenue></td></tr>";

	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input class='button green medium' type=submit accesskey='g' value='".findtekst('471|Gem/opdatér', $sprog_id)."' name='submit'></td>";
	print "</form>";
} # endfunc ordre_valg

# ---------------------- varianter ----------------------

function variant_valg() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	global $db;
	global $buttonColor;
	global $buttonTxtColor;
	global $buttonColorHover;

	// Handle delete actions
	if ($delete_var_type = if_isset($_GET['delete_var_type'])) {
		db_modify("delete from variant_typer where id = '$delete_var_type'", __FILE__ . " linje " . __LINE__);
	}
	if ($delete_variant = (int) if_isset($_GET['delete_variant'])) {
		db_modify("delete from variant_typer where variant_id = '$delete_variant'", __FILE__ . " linje " . __LINE__);
		db_modify("delete from varianter where id = '$delete_variant'", __FILE__ . " linje " . __LINE__);
	}

	// Include external CSS file and set CSS custom properties for theme colors
	print "<link rel='stylesheet' href='../css/variant-valg.css'>";
	$primaryColor = $buttonColor      ?: '#3b82f6';
	$primaryHover = $buttonColorHover ?: '#2563eb';
	$primaryText  = $buttonTxtColor   ?: '#ffffff';
	print "<style>:root { --variant-primary-color: $primaryColor; --variant-primary-hover: $primaryHover; --variant-primary-text: $primaryText; }</style>";

	// JavaScript for toggling variant cards
	print "<script>
	function toggleVariantCard(header) {
		var card = header.parentElement;
		card.classList.toggle('collapsed');
	}
	</script>";

	print "<tr><td colspan='6'>";
	print "<div class='variant-container'>";

	// Header
	print "<div class='variant-header'>";
	print "<h2>".str_replace("php","html",findtekst('472|Varianter', $sprog_id))."</h2>";
	print "</div>";

	// Display import message if exists
	if (isset($_SESSION['variant_import_message'])) {
		print "<div class='variant-import-message'>";
		print "<strong>✓ Import resultat:</strong> " . htmlspecialchars($_SESSION['variant_import_message']);
		print "</div>";
		unset($_SESSION['variant_import_message']);
	}

	// Check for rename mode
	$rename_var_type = if_isset($_GET['rename_var_type']);
	$rename_variant  = if_isset($_GET['rename_variant']);

	if ($rename_var_type) {
		$r = db_fetch_array(db_select("select beskrivelse from variant_typer where id=$rename_var_type", __FILE__ . " linje " . __LINE__));
		print "<form name='diverse' action='diverse.php?sektion=variant_valg' method='post'>";
		print "<div class='rename-form'>";
		print "<h3>".findtekst('479|Klik her for at omdøbe denne værdi', $sprog_id)."</h3>";
		print "<input type='hidden' name='rename_var_type' value='$rename_var_type'>";
		print "<input type='text' class='new-variant-input' name='var_type_beskrivelse' value='".htmlspecialchars($r['beskrivelse'])."' autofocus>";
		print "<button type='submit' class='btn-variant btn-primary-variant' name='submit'>".findtekst('471|Gem/opdatér', $sprog_id)."</button>";
		print " <a href='diverse.php?sektion=variant_valg' class='btn-variant btn-secondary-variant'>".findtekst('1355|Annullér', $sprog_id)."</a>";
		print "</div>";
		print "</form>";
	} elseif ($rename_variant) {
		$r = db_fetch_array(db_select("select beskrivelse from varianter where id=$rename_variant", __FILE__ . " linje " . __LINE__));
		print "<form name='diverse' action='diverse.php?sektion=variant_valg' method='post'>";
		print "<div class='rename-form'>";
		print "<h3>".findtekst('477|Klik her for at omdøbe denne variant', $sprog_id)."</h3>";
		print "<input type='hidden' name='rename_varianter' value='$rename_variant'>";
		print "<input type='text' class='new-variant-input' name='variant_beskrivelse' value='".htmlspecialchars($r['beskrivelse'])."' autofocus>";
		print "<button type='submit' class='btn-variant btn-primary-variant' name='submit'>".findtekst('471|Gem/opdatér', $sprog_id)."</button>";
		print " <a href='diverse.php?sektion=variant_valg' class='btn-variant btn-secondary-variant'>".findtekst('1355|Annullér', $sprog_id)."</a>";
		print "</div>";
		print "</form>";
	} else {
		// Import Section
		print "<div class='import-section'>";
		print "<h3><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor' style='width:20px;height:20px;vertical-align:middle;margin-right:8px;stroke-width:2'><path stroke-linecap='round' stroke-linejoin='round' d='M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5'/></svg>Import varianter fra CSV</h3>";
		print "<div class='import-grid'>";
		
		// Import Variant Types (main categories)
		print "<div class='import-box'>";
		print "<form enctype='multipart/form-data' action='diverse.php?sektion=variant_valg_import_types' method='POST'>";
		print "<h4>Import varianttyper</h4>";
		print "<p>Importér varianter (f.eks. Farve, Størrelse). Én variant pr. linje.</p>";
		print "<code>Farve<br>Størrelse<br>Materiale</code>";
		print "<input type='hidden' name='MAX_FILE_SIZE' value='500000'>";
		print "<div class='file-input-wrapper'>";
		print "<span class='file-input-label'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor' style='width:16px;height:16px;stroke-width:2'><path stroke-linecap='round' stroke-linejoin='round' d='M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'/></svg> Vælg CSV fil</span>";
		print "<input type='file' name='variant_types_file' accept='.csv,.txt' onchange='this.form.submit()'>";
		print "</div>";
		print "</form>";
		print "</div>";
		
		// Import Variant Values (values for types)
		print "<div class='import-box'>";
		print "<form enctype='multipart/form-data' action='diverse.php?sektion=variant_valg_import_values' method='POST'>";
		print "<h4>Import variantværdier</h4>";
		print "<p>Importér værdier for varianter. Format: variantnavn;værdi</p>";
		print "<code>Farve;Rød<br>Farve;Blå<br>Størrelse;Small<br>Størrelse;Large</code>";
		print "<input type='hidden' name='MAX_FILE_SIZE' value='500000'>";
		print "<div class='file-input-wrapper'>";
		print "<span class='file-input-label'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor' style='width:16px;height:16px;stroke-width:2'><path stroke-linecap='round' stroke-linejoin='round' d='M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'/></svg> Vælg CSV fil</span>";
		print "<input type='file' name='variant_values_file' accept='.csv,.txt' onchange='this.form.submit()'>";
		print "</div>";
		print "</form>";
		print "</div>";
		
		print "</div>"; // End import-grid
		print "</div>"; // End import-section

		// Load variant data
		$variants = array();
		$q = db_select("select * from varianter order by beskrivelse", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$variant = array(
				'id'          => $r['id'],
				'beskrivelse' => $r['beskrivelse'],
				'values'      => array()
			);
			$q2 = db_select("select * from variant_typer where variant_id=".$r['id']." order by beskrivelse", __FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				$variant['values'][] = array(
					'id' => $r2['id'],
					'beskrivelse' => $r2['beskrivelse']
				);
			}
			$variants[] = $variant;
		}

		print "<form name='diverse' action='diverse.php?sektion=variant_valg' method='post'>";

		if (count($variants) == 0) {
			// Empty state
			print "<div class='variant-card'>";
			print "<div class='empty-state'>";
			print "<div class='empty-state-icon'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor' style='width:48px;height:48px;stroke-width:1.5;color:#9ca3af'><path stroke-linecap='round' stroke-linejoin='round' d='M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'/></svg></div>";
			print "<p>Ingen varianter oprettet endnu</p>";
			print "<p style='color:#9ca3af; margin-top:5px;'>Varianttype for varen, f.eks. farve eller størrelse</p>";
			print "</div>";
			print "</div>";
		} else {
			// Display variants as cards
			$x = 0;
			foreach ($variants as $variant) {
				$x++;
				$valueCount = count($variant['values']);
				
				print "<div class='variant-card'>";
				print "<div class='variant-card-header' onclick='toggleVariantCard(this)'>";
				print "<div class='variant-name'>";
				print "<span class='toggle-icon'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor' style='width:16px;height:16px;stroke-width:2'><path stroke-linecap='round' stroke-linejoin='round' d='M19.5 8.25l-7.5 7.5-7.5-7.5'/></svg></span>";
				print htmlspecialchars($variant['beskrivelse']);
				print "<span class='variant-count'>$valueCount ".($valueCount == 1 ? "Værdi" : "Værdier")."</span>";
				print "</div>";
				print "<div class='variant-card-actions' onclick='event.stopPropagation();'>";
				print "<a href='diverse.php?sektion=variant_valg&rename_variant=".$variant['id']."' class='btn-icon-variant btn-edit' title='Klik her for at omdøbe denne variant'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' d='M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10'/></svg></a>";
				print "<a href='diverse.php?sektion=variant_valg&delete_variant=".$variant['id']."' class='btn-icon-variant btn-delete' title='Klik her for at slette denne variant og tilhørende variant værdier.' onclick=\"return confirm('Vil du slette denne variant og tilhørende variant værdier?')\"><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' d='M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0'/></svg></a>";
				print "</div>";
				print "</div>"; // End card header
				
				print "<div class='variant-card-body'>";
				print "<table class='variant-values-table'>";
				print "<thead><tr><th>Værdi</th><th style='width:100px; text-align:center;'>Handling</th></tr></thead>";
				print "<tbody>";
				
				// Existing values
				foreach ($variant['values'] as $value) {
					print "<tr>";
					print "<td>".htmlspecialchars($value['beskrivelse'])."</td>";
					print "<td style='text-align:center;'>";
					print "<a href='diverse.php?sektion=variant_valg&rename_var_type=".$value['id']."' class='btn-icon-variant btn-edit' title='".findtekst('479|Klik her for at omdøbe denne værdi', $sprog_id)."'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' d='M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10'/></svg></a>";
					print "<a href='diverse.php?sektion=variant_valg&delete_var_type=".$value['id']."' class='btn-icon-variant btn-delete' title='".findtekst('480|Klik her for at slette denne værdi', $sprog_id)."' onclick=\"return confirm('".findtekst('482|Vil du slette denne værdi?', $sprog_id)."')\"><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' d='M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0'/></svg></a>";
					print "</td>";
					print "</tr>";
				}
				
				// Add new value row
				print "<tr class='add-value-row'>";
				print "<td colspan='2'>";
				print "<input type='hidden' name='variant_id[$x]' value='".$variant['id']."'>";
				print "<input type='text' class='add-value-input' name='var_type_beskrivelse[$x]' placeholder='Ny variant værdi...' title='Værdi for varianten, f.eks. \'rød\' eller \'lille\'>";
				print "</td>";
				print "</tr>";
				
				print "</tbody>";
				print "</table>";
				print "</div>"; // End card body
				print "</div>"; // End card
			}
			
			print "<input type='hidden' name='variant_antal' value='$x'>";
		}

		// New variant section
		print "<div class='new-variant-section'>";
		print "<h3><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor' style='width:20px;height:20px;vertical-align:middle;margin-right:8px;stroke-width:2'><path stroke-linecap='round' stroke-linejoin='round' d='M12 4.5v15m7.5-7.5h-15'/></svg>Ny variant</h3>";
		print "<input type='text' class='new-variant-input' name='variant_beskrivelse' placeholder='Varianttype for varen, f.eks. farve eller størrelse...'>";
		print "</div>";

		// Submit button
		print "<div style='margin-top:20px; text-align:center;'>";
		print "<button type='submit' class='btn-variant btn-success-variant' name='submit' accesskey='g' style='padding: 12px 30px; font-size: 15px; display:inline-flex; align-items:center; gap:8px;'>";
		print "<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor' style='width:18px;height:18px;stroke-width:2'><path stroke-linecap='round' stroke-linejoin='round' d='M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'/></svg>Gem/opdatér";
		print "</button>";
		print "</div>";

		print "</form>";
	}

	print "</div>"; // End variant-container
	print "</td></tr>";
} # endfunc variant_valg

// function shop_valg() {
// 	global $sprog_id;
// 	global $bgcolor;
// 	global $bgcolor5;
// 	global $db;
// 	global $labelprint;

// 	#	$hurtigfakt=NULL; $incl_moms_private=NULL; $incl_moms_business=NULL; $folge_s_tekst=NULL; $negativt_lager=NULL; $straks_bogf=NULL; $vis_nul_lev=NULL;
// 	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '5'", __FILE__ . " linje " . __LINE__);
// 	$r = db_fetch_array($q);
// 	$id = $r['id'];
// 	$beskrivelse = $r['beskrivelse'];
// 	$kodenr = $r['kodenr'];
// 	$box2 = trim($r['box2']);
// 	$box3 = trim($r['box3']);
// 	$box4 = trim($r['box4']);
// 	$box5 = trim($r['box5']);
// 	$box7 = trim($r['box7']);
// 	$box9 = trim($r['box9']);
// 	# OBS $box1 bruges under vare_valg!!
// 	# OBS $box8 bruges under ordrelaterede valg!!

// 	print "<form name=diverse action=diverse.php?sektion=shop_valg method=post>";
// 	print "<tr><td colspan='6'><hr></td></tr>";

// 	if ($box2 == '!') $box3 = '1';
// 	print "<tr><td><br></td></tr>";
// 	print "<tr><td title='".findtekst('695|Vælg her om du vil anvende Saldis interne shop eller en ekstern via API.', $sprog_id)."'><!--tekst 826-->".findtekst('695|Vælg her om du vil anvende Saldis interne shop eller en ekstern via API.', $sprog_id)."<!--tekst 826--></td><td colspan='3' title='".findtekst('695|Vælg her om du vil anvende Saldis interne shop eller en ekstern via API.', $sprog_id)."'><select style='text-align:left;width:300px;' name='box3'>";
// 	if (!$box3) print "<option value='0'>".findtekst('697|Ingen webshop', $sprog_id)."<!--tekst 697--></option>";
// 	if ($box3=='1') print "<option value='1'>".findtekst('698|Intern webshop', $sprog_id)."<!--tekst 698--></option>";
// 	if ($box3=='2') print "<option value='2'>".findtekst('699|Ekstern webshop', $sprog_id)."<!--tekst 829--></option>";
// 	if ($box3) print "<option value='0'>".findtekst('697|Ingen webshop', $sprog_id)."<!--tekst 697--></option>";
// 	if ($box3!='1') print "<option value='1'>".findtekst('698|Intern webshop', $sprog_id)."<!--tekst 698--></option>";
// 	if ($box3!='2') print "<option value='2'>".findtekst('699|Ekstern webshop', $sprog_id)."<!--tekst 829--></option>";
// 	print "</select></td></tr>";
// 	if ($box3 == '2') {
// 		print "<tr><td title='".findtekst('503|Hvis der benyttes API til webshop skrives URL til shoppens funktionsmappe her.', $sprog_id)."'><!--tekst 503-->".findtekst('504|Webshop URL', $sprog_id)."<!--tekst 504--></td><td colspan='3' title='".findtekst('503|Hvis der benyttes API til webshop skrives URL til shoppens funktionsmappe her.', $sprog_id)."'><!--tekst 503--><input type='text' style='text-align:left;width:300px;' name='box2' value = '$box2'</td></tr>";
// 		print "<tr><td title=''>".findtekst('733|Tegn kodning for shop', $sprog_id)."<!--tekst 733--></td><td colspan='3' title='".findtekst('733|Tegn kodning for shop', $sprog_id)."'><!--tekst 733--><select style='text-align:left;width:300px;' name='box7'>";
// 		if ($box7 == 'UTF-8') {
// 			print "<option>UTF-8</option>";
// 			print "<option>ISO-8859-1</option>";
// 		} else {
// 			print "<option>ISO-8859-1</option>";
// 			print "<option>UTF-8</option>";
// 		}
// 		print "</select></td></tr>";
// 		if ($apifil = $box2) {
// 			$filnavn = mt_rand() . ".csv";
// 			if (substr($apifil, 0, 4) == 'http') { #20150608
// 				print "<tr><td title='".findtekst('740|Klik her for at hente nye varer fra shop til Saldi.', $sprog_id)."'><!--tekst 740-->".findtekst('741|Hent nye varer fra shop.', $sprog_id)."<!--tekst 741--></td><td colspan='3'  title='".findtekst('740|Klik her for at hente nye varer fra shop til Saldi.', $sprog_id)."'><!--tekst 740--><a href=../api/hent_varer.php target='blank'><input style='text-align:center;width:300px;' type='button' value='".findtekst('741|Hent nye varer fra shop.', $sprog_id)."'><!--tekst 749--></a></td></tr>";
// 				$apifil = str_replace("/?", "sync_saldi_kat.php?", $apifil);
// 				$apifil = $apifil . "&saldi_db=$db&filnavn=$filnavn";
// #				print "<tr><td title='".findtekst(678, $sprog_id)."'><!--tekst 678-->".findtekst(679, $sprog_id)."<!--tekst 679--></td><td colspan='3'  title='".findtekst(678, $sprog_id)."'><!--tekst 678--><a href=$apifil target='blank'><input style='text-align:center;width:300px;' type='button' value='".findtekst(679, $sprog_id)."'><!--tekst 679--></a></td></tr>";
// #				print "<tr><td colspan='3'><span title='Klik her for at hente nye ordrer fra shop'><a href=$apifil target='_blank'>SHOP import</a</span></td></tr>";
// 			}
// 		}
// 	} elseif ($box3 == '1') {
// 		print "<tr><td title='".findtekst('691|Merchant nr tildeles ved oprettelse af betalingsaftale hos Quickpay', $sprog_id)."'><!--tekst 821-->".findtekst('692|Merchant nr:', $sprog_id)."<!--tekst 822--></td><td colspan='3' title='".findtekst('691|Merchant nr tildeles ved oprettelse af betalingsaftale hos Quickpay', $sprog_id)."'><!--tekst 621--><input type='text' style='text-align:left;width:300px;' name='box4' value = '$box4'</td></tr>";
// 		print "<tr><td title='".findtekst('752|Agreement_id tildeles ved oprettelse af betalingsaftale hos Quickpay', $sprog_id)."'><!--tekst 752-->".findtekst('753|Agreement_id', $sprog_id)."<!--tekst 753--></td><td colspan='3' title='".findtekst('752|Agreement_id tildeles ved oprettelse af betalingsaftale hos Quickpay', $sprog_id)."'><!--tekst 752--><input type='text' style='text-align:left;width:300px;' name='box9' value = '$box9'</td></tr>";
// 		print "<tr><td title='".findtekst('693|Md5-secret tildeles ved oprettelse af betalingsaftale hos Quickpay', $sprog_id)."'><!--tekst 823-->".findtekst('694|Md5-secret', $sprog_id)."<!--tekst 824--></td><td colspan='3' title='".findtekst('693|Md5-secret tildeles ved oprettelse af betalingsaftale hos Quickpay', $sprog_id)."'><!--tekst 823--><input type='text' style='text-align:left;width:300px;' name='box5' value = '$box5'</td></tr>";
// 	}
// 	print "<tr><td>";
// 	print "<br></td></tr>";
// 	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey='g' value='".findtekst('471|Gem/opdatér', $sprog_id)."' name='submit'><!--tekst 471--></td>";
// 	print "</form>";
// 	print "<tr><td colspan='6'><hr></td></tr>";
// } # endfunc shop_valg

function api_valg() {
	global $sprog_id;
	// The API settings live under Indstillinger » Integrationer since phase 4b. Only the shop sync still runs here,
	// because api/varesync.php prints its progress while it works.
	print "<tr><td colspan='6'><a href='settingsSection.php?s=integrations.connections&item=webshop'>&laquo; ".findtekst('5537|Integrationer', $sprog_id)."</a></td></tr>";
	print "<tr><td colspan='6'><hr></td></tr>";
	if (isset($_GET['varesync']) && $_GET['varesync']) {
		include("../api/varesync.php");
		varesync($_GET['varesync']);
	}
} # endfunc api_valg


function labels($valg) {
    global $sprog_id;
    global $bgcolor;
    global $bgcolor5;
    global $db;
    global $labelName;
    global $labelprint;
    global $saveLabelRefused;

    if (!$labelName) {
        $labelName = if_isset($_POST['labelName']);
        if (isset($_POST['newLabelName'])) $labelName = $_POST['newLabelName'];
    }
    // B-L1: valg comes from the address bar and the label name from the user; neither reaches the page unescaped.
    if ($valg !== 'box1' && $valg !== 'box2') $valg = '';
    $labelNameHtml = htmlspecialchars((string) $labelName, ENT_QUOTES);
    ($valg == 'box1') ? $txt = 'Vare' : $txt = 'Adresse';
    
    // Check if user wants to edit raw HTML
    $editRawHTML = (isset($_POST['editRawHTML']) || isset($_GET['editRawHTML'])) && !isset($_POST['switchToVisual']);
    
    if (isset($_POST['newLabel'])) {
        print "<form name='diverse' action='diverse.php?sektion=labels&valg=$valg' method='post'>";
        print "<tr bgcolor='$bgcolor5'><td colspan='6' title='".findtekst('737|Her indsættes html kode til formatering af labelprint i varekort. Du kan finde eksempler på <a href=http://forum.saldi.dk/viewtopic.php?f=17&t=1159>Saldi forum</a> under tips og tricks.', $sprog_id)."'><!--tekst 737-->";
        print "<b><u>".findtekst('736|Labelprint', $sprog_id)."<!--tekst 736--> ($txt)</u></b></td></tr>";
        $qtxt = "select $valg from grupper where art = 'LABEL'";
        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $labelText = $r['box1'];
        print "<tr><td><br><br></td></tr>";
        print "<tr><td  valign='top' align='left' title='".findtekst('503|Hvis der benyttes API til webshop skrives URL til shoppens funktionsmappe her.', $sprog_id)."'><b>".findtekst('914|Beskrivelse', $sprog_id)."</b><br>";
        print "<input type='text' style='width:200px' name='newLabelName' pattern='[a-zA-Z0-9+.-]+' required><br>";
        print "".findtekst('1309|Tilladte tegn er: a-z A-Z 0-9', $sprog_id)."</td>";
        print "<td valign='top' align = 'left'><b>".findtekst('803|Skabelon', $sprog_id)."</b><br><select style='width:200px' name='labelTemplate'>";
        print "<option value='A4Label38x21_ens.txt'>".findtekst('1310|A4 38,1 x 21,2 mm, ens labels', $sprog_id)."</option>";
        print "<option value='A4Label38x21.txt'>".findtekst('1311|A4 38,1 x 21,2 mm, mit salg', $sprog_id)."</option>";
        print "<option value='BrotherLabel22606.txt'>".findtekst('1312|Brother 22606', $sprog_id)."</option>";
        print "<option value='BrotherLabel22606MS.txt'>".findtekst('1313|Brother 22606 mit salg', $sprog_id)."</option>";
        print "<option value='DymoLabelArt11354.txt'>Dymo 11354</option>";
        print "<option value='DymoLabelArt11354MS.txt'>".findtekst('1314|Dymo 11354 mit salg', $sprog_id)."</option>";
        print "</td></select></td>";
        print "<td valign='top' align = 'center'>&nbsp<br>";
        print "<input type='submit' style='width:200px' accesskey='s' value='".findtekst('1232|Opret', $sprog_id)."' name='createNewLabel'>";
        print "</td></tr></form>";
    } elseif ($valg) {
        $x          = 0;
        $labelNames = array();
        $qtxt       = "select id, labeltype, labelname from labels order by labelname";
        $q          = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        while ($r = db_fetch_array($q)) {
            $labelNames[$x] = $r['labelname'];
            $x++;
        }
        if (!$labelName) $labelName = 'Standard';
        $txt .= " - " . htmlspecialchars((string) $labelName, ENT_QUOTES);
        print "<tr bgcolor='$bgcolor5'><td colspan='4' title='".findtekst('737|Her indsættes html kode til formatering af labelprint i varekort. Du kan finde eksempler på <a href=http://forum.saldi.dk/viewtopic.php?f=17&t=1159>Saldi forum</a> under tips och tricks.', $sprog_id)."'><!--tekst 737-->";
        print "<b><u>".findtekst('736|Labelprint', $sprog_id)."<!--tekst 736--> ($txt)</u></b></td></tr>";
        
        if ($labelName == 'Standard' || ($valg == 'box1' && in_array($labelName, $labelNames))) {
            $label     = loadLabelText($valg, $labelName);
            $labelText = $label['labeltext'];
            $labelType = $label['labeltype'];
        }

        // Captured before the 'Standard'-and-empty branch below fills $labelText with a
        // display-only placeholder, so the visually-editable check further down judges what is
        // actually stored (empty = nothing to lose) rather than that placeholder, which is
        // written in the labels table's older $beskrivelse/$pris variable names and so never
        // matches what generateLabelTemplate() itself produces (MB-18: an emptied-out label
        // regenerates the placeholder on the next render and was wrongly staying locked out of
        // the visual editor because of that mismatch).
        $storedLabelText = $labelText;

        if (empty($labelType)) $labelType = 'sheet';
        if ($labelName == 'Standard' && empty($labelText)) {
            $labelText = '$cols=1;
$rows=1;
$txtlen=50;
<top>
<style>
#main {
width: 100%;
overflow:hidden;
margin-top: 7mm;
margin-bottom: 0mm;
margin-right: 0mm;
margin-left: 3mm;}

p {
width: 38.1mm;
display: inline-block;
height: 21.2mm;
padding-bottom:0px;
margin-top: 0mm;
margin-bottom: 0mm;
margin-right: 0mm;
margin-left: 1mm;
font-size: 12px}

img {
width: 90%;
height: 5mm;
margin-left:-4px}
</style>	
<div id="main">
</top>

<p>
$varenr<br>
$beskrivelse<br>
Pris $pris<br>
<img src=\'$img\'><br>
</p>

<bottom>
</div>
/bottom;';
        }

        // A label whose template the visual editor's narrow field model can't reproduce
        // exactly (imported Brother/Dymo templates, hand-written raw HTML, ...) must stay in
        // raw-HTML mode - opening it in the visual editor and saving would silently discard
        // whatever it doesn't model (MB-18).
        if (!$editRawHTML && !labelTemplateEditableVisually($storedLabelText)) {
            $editRawHTML = true;
            $forcedRawHTML = true;
        }

        if ($valg == 'box1') {
            // Label selection dropdown - only show if there are custom labels
            $hasMultipleOptions = count($labelNames) > 1 || (count($labelNames) == 1 && $labelName != 'Standard');
            
            if (count($labelNames) > 0) {
                print "<form name='labelvalg' action='diverse.php?sektion=labels&valg=$valg' method='post'>";
                print "<tr><td align='center' colspan='4'>";
                print "Choose Label: <select style='width:200px' name='labelName' onchange='javascript:this.form.submit()'";
                
                // Grey out (disable) if there's only one meaningful option
                if (!$hasMultipleOptions) {
                    print " disabled style='width:200px; background-color:#f0f0f0; color:#999;'";
                }
                
                print ">";
                for ($x = 0; $x < count($labelNames); $x++) {
                    $selected = ($labelName == $labelNames[$x]) ? ' selected' : '';
                    $optionName = htmlspecialchars((string) $labelNames[$x], ENT_QUOTES);
                    print "<option value='$optionName'$selected>$optionName</option>";
                }
                print "</select>";
                
                // Add a hidden field to ensure form submission still works when dropdown is disabled
                if (!$hasMultipleOptions) {
                    print "<input type='hidden' name='labelName' value='" . htmlspecialchars((string) $labelName, ENT_QUOTES) . "'>";
                }
                
                print "<br>";
                print "<input type='submit' style='border:0px;width:100%;height:1px' value=' ' name='labelvalg'></form></td></tr>";
            }
        }
        
		print "<form name='diverse' action='diverse.php?sektion=labels&valg=$valg' method='post'>";
		print "<input type='hidden' name='labelName' value='" . htmlspecialchars((string) $labelName, ENT_QUOTES) . "'>";
		
		if ($editRawHTML) {
			// Raw HTML editing mode
			print "<input type='hidden' name='editRawHTML' value='1'>";
			print "<tr><td colspan='4'>";
			print "<div style='margin-bottom: 10px;'>";
			print "<h3>Rå HTML Editor</h3>";
			print "<p style='color: #666; font-size: 12px;'>".findtekst('5057|Du kan redigere den komplette HTML skabelon her. Brug variabler som \$varenr, \$minbeskrivelse, \$minpris, \$img, osv. \$minbeskrivelse og \$minpris viser kundens egen tekst og pris fra Mit salg og falder tilbage til varens egen, når der printes uden konto. \$beskrivelse og \$pris henter altid varens egen.', $sprog_id)."<!--tekst 5056--></p>";
			if (!empty($forcedRawHTML)) {
				print "<p style='color: #a94442; background-color: #f2dede; padding: 6px; font-size: 12px;'>".findtekst('5078|Denne label indeholder formatering, som den visuelle editor ikke forstår, og kan derfor kun redigeres her som rå HTML - at gemme via den visuelle editor ville slette den formatering, den ikke kan vise. For at få adgang til den visuelle editor igen skal den rå HTML tømmes og gemmes.', $sprog_id)."</p>";
			}
			if (!empty($saveLabelRefused)) {
				print "<p style='color: #a94442; background-color: #f2dede; padding: 6px; font-size: 12px;'>".findtekst('5079|Din ændring blev ikke gemt - denne labels skabelon er ændret siden siden blev indlæst (fx i en anden fane), og kan nu kun redigeres som rå HTML. Genindlæs siden og prøv igen.', $sprog_id)."</p>";
			}
			print "</div>";
			print "<textarea name='rawHTML' style='width: 100%; height: 400px; font-family: monospace; font-size: 12px;'>" . htmlspecialchars($labelText) . "</textarea>";
			print "</td></tr>";
			
			print "<tr><td align='center' colspan='4'>";
			print "<select name='labelType' style='width:100px'>";
			if ($labelType == 'sheet') print "<option value='sheet'>".findtekst('2547|A4 ark', $sprog_id)."</option><option value='label'>".findtekst('1315|Enkel labels', $sprog_id)."</option>";
			else print "<option value='label'>".findtekst('1315|Enkel labels', $sprog_id)."</option><option value='sheet'>".findtekst('2547|A4 ark', $sprog_id)."</option>";
			print "</select>";
			print "<input type='submit' style='width:150px' value='".findtekst('471|Gem/opdatér', $sprog_id)."' name='saveRawHTML'>";
			if (empty($forcedRawHTML)) {
				print "&nbsp;<input type='submit' style='width:150px' value='Skift til Visuel Editor' name='switchToVisual'>";
			}
			if ($valg == 'box1') {
			print "&nbsp;<input type='submit' style='width:150px' value='".findtekst('39|Ny', $sprog_id)." Label' name='newLabel'>";
			if ($labelName != 'Standard') {
				$txt = "Er du sikker på du vil slette label $labelName ?";
				print "&nbsp;<input type='submit' style='width:150px' value='Slet Label' name='deleteLabel' onclick=\"return confirm(" . htmlspecialchars(json_encode($txt), ENT_QUOTES) . ")\">";
			}
			}
			print "</td></tr>";
			
		} else {
			// Visual editing mode
			// Parse the label template for user-friendly editing
			$parsedLabel = parseLabelTemplate($labelText);
			
			// Display user-friendly form fields
			print "<tr><td colspan='4'>";
			print "<div style='display: flex; gap: 20px;'>";
			
			// Left column - Label dimensions and settings
			print "<div style='flex: 1;'>";
			print "<h3>Label ".findtekst('2139|Indstillinger', $sprog_id)."</h3>";
			print "<table>";
			print "<tr><td>Kolonner:</td><td><input type='number' name='cols' value='".$parsedLabel['cols']."' min='1' max='10' style='width:60px;'></td></tr>";
			print "<tr><td>Rækker:</td><td><input type='number' name='rows' value='".$parsedLabel['rows']."' min='1' max='20' style='width:60px;'></td></tr>";
			print "<tr><td>".findtekst("2504|Tekstlængde", $sprog_id)."</td><td><input type='number' name='txtlen' value='".$parsedLabel['txtlen']."' min='10' max='100' style='width:60px;'></td></tr>";
			print "</table>";
			
			print "<h3>Styling</h3>";
			print "<table>";
			print "<tr><td>".findtekst("2411|Bredde", $sprog_id)."</td><td><input type='text' name='width' value='".$parsedLabel['width']."' style='width:80px;'> mm</td></tr>";
			print "<tr><td>".findtekst("1790|Højde", $sprog_id)."</td><td><input type='text' name='height' value='".$parsedLabel['height']."' style='width:80px;'> mm</td></tr>";
			print "<tr><td>Standard ".findtekst('765|Skriftstørrelse', $sprog_id).":</td><td><input type='text' name='font_size' value='".$parsedLabel['font_size']."' style='width:80px;'> px</td></tr>";
			print "<tr><td>Margin Top:</td><td><input type='text' name='margin_top' value='".$parsedLabel['margin_top']."' style='width:80px;'> mm</td></tr>";
			print "<tr><td>Margin ".findtekst("2511|Venstre", $sprog_id).":</td><td><input type='text' name='margin_left' value='".$parsedLabel['margin_left']."' style='width:80px;'> mm</td></tr>";
			print "</table>";
			print "</div>";
			
			// Middle column - Label Content with individual font sizes
			print "<div style='flex: 1;'>";
			print "<h3>Label Indhold & Skriftstørrelser</h3>";
			print "<table>";
			print "<tr><td colspan='3'><strong>Element</strong></td><td><strong>".findtekst('1133|Vis', $sprog_id)."</strong></td><td><strong>".findtekst('765|Skriftstørrelse', $sprog_id)." (px)</strong></td></tr>";

			print "<tr><td colspan='3'>".findtekst("320|Varenummer", $sprog_id)."</td><td><input type='checkbox' name='show_varenr' ".($parsedLabel['show_varenr'] ? 'checked' : '')."></td>";
			print "<td><input type='number' name='varenr_font_size' value='".$parsedLabel['varenr_font_size']."' style='width:60px;' min='6' max='72'></td></tr>";
			
			print "<tr><td colspan='3'>Mærke</td><td><input type='checkbox' name='show_varemrk' ".($parsedLabel['show_varemrk'] ? 'checked' : '')."></td>";
			print "<td><input type='number' name='varemrk_font_size' value='".$parsedLabel['varemrk_font_size']."' style='width:60px;' min='6' max='72'></td></tr>";
			
			print "<tr><td colspan='3'>".findtekst("914|Beskrivelse", $sprog_id)."</td><td><input type='checkbox' name='show_beskrivelse' ".($parsedLabel['show_beskrivelse'] ? 'checked' : '')."></td>";
			print "<td><input type='number' name='beskrivelse_font_size' value='".$parsedLabel['beskrivelse_font_size']."' style='width:60px;' min='6' max='72'></td></tr>";
			
			print "<tr><td colspan='3'>".findtekst("915|Pris", $sprog_id)."</td><td><input type='checkbox' name='show_pris' ".($parsedLabel['show_pris'] ? 'checked' : '')."></td>";
			print "<td><input type='number' name='pris_font_size' value='".$parsedLabel['pris_font_size']."' style='width:60px;' min='6' max='72'></td></tr>";
			
			print "<tr><td colspan='3'>".findtekst("2016|Stregkode", $sprog_id)."</td><td><input type='checkbox' name='show_barcode' ".($parsedLabel['show_barcode'] ? 'checked' : '')."></td>";
			print "<td>N/A</td></tr>";
			
			print "</table>";
			print "</div>";
			
			// Right column - Custom Text Lines with individual font sizes
			print "<div style='flex: 1;'>";
			print "<h3>Brugerdefinerede Tekstlinjer</h3>";
			print "<div>";
			for ($i = 1; $i <= 5; $i++) {
			$textValue = isset($parsedLabel["custom_text_$i"]) ? $parsedLabel["custom_text_$i"] : '';
			$fontSize  = isset($parsedLabel["custom_text_{$i}_size"]) ? $parsedLabel["custom_text_{$i}_size"] : $parsedLabel['font_size'];
			print "<div style='margin-bottom: 10px; border: 1px solid #ccc; padding: 8px;'>";
			print "<label><strong>Linje $i:</strong></label><br>";
			print "<input type='text' name='custom_text_$i' value='" . htmlspecialchars((string) $textValue, ENT_QUOTES) . "' placeholder='Brugerdefineret tekst' style='width:150px; margin-bottom: 5px;'><br>";
			print "<label>".findtekst('765|Skriftstørrelse', $sprog_id).":</label>";
			print "<input type='number' name='custom_text_{$i}_size' value='$fontSize' style='width:60px;' min='6' max='72'> px";
			print "</div>";
			}
			print "</div>";
			print "</div>";
			
			print "</div>";
			print "</td></tr>";
			
			print "<tr><td align='center' colspan='4'>";
			print "<select name='labelType' style='width:100px'>";
			if ($labelType == 'sheet') print "<option value='sheet'>".findtekst('2547|A4 ark', $sprog_id)."</option><option value='label'>".findtekst('1315|Enkel labels', $sprog_id)."</option>";
			else print "<option value='label'>".findtekst('1315|Enkel labels', $sprog_id)."</option><option value='sheet'>".findtekst('2547|A4 ark', $sprog_id)."</option>";
			print "</select>";
			print "<input type='submit' style='width:150px' accesskey='g' value='".findtekst('471|Gem/opdatér', $sprog_id)."' name='saveLabel'>";
			print "&nbsp;<input type='submit' style='width:150px' value='Rediger Rå HTML' name='editRawHTML'>";
			if ($valg == 'box1') {
			print "&nbsp;<input type='submit' style='width:150px' accesskey='n' value='".findtekst('39|Ny', $sprog_id)." Label' name='newLabel'>";
			if ($labelName != 'Standard') {
				$txt = "Er du sikker på du vil slette label $labelName ?";
				print "&nbsp;<input type='submit' style='width:150px' value='Slet Label' name='deleteLabel' onclick=\"return confirm(" . htmlspecialchars(json_encode($txt), ENT_QUOTES) . ")\">";
			}
			}
			print "</td></tr>";
		}
		
		print "</form>";
		} else {
		print "<tr><td>".findtekst('1308|Klik på den labeltype du vil redigere', $sprog_id)."</td><td>";
		print "<a href='diverse.php?sektion=labels&valg=box1'>";
		print "<input type='button'  style='width:100px' value='".findtekst('110|Varer', $sprog_id)."'></a></td></tr>";
		print "<tr><td></td><td><a href=diverse.php?sektion=labels&valg=box2>";
		print "<input type='button' style='width:100px' value='".findtekst('361|Adresse', $sprog_id)."'></a></td></tr>";
    }
} # endfunc labels


 # endfunc prislister



// 20261009 Sawaneh tjekliste() moved to sager/tjeklister.php (checklists left Indstillinger, settings redesign §4, 4e).



function massefakt() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$id    = $levfrist    = 0;
	$batch = $brug_dellev = $brug_mfakt = $folge_s_tekst = $gruppevalg = $kua = $kuansvalg = $ref = $smart = NULL;

	$q = db_select("select * from grupper where art = 'MFAKT' and kodenr = '1'", __FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$id = $r['id'];
		if ($r['box1'] == 'on') $brug_mfakt = 'checked';
		if ($r['box2'] == 'on') $brug_dellev = 'checked';
		$levfrist = $r['box3'];
		if (!$levfrist) $levfrist = 0;
	}
	print "<form name='diverse' action='diverse.php?sektion=massefakt' method='post'>\n";
	print "<tr bgcolor='$bgcolor5'><td colspan='2'><b>".findtekst('200|Massefakturering', $sprog_id)."</b></td></tr>\n";
	print "<tr><td colspan='6'>&nbsp;</td></tr>\n";
	print "<input name='id' type='hidden' value='$id'>\n";
	print "<tr>\n<td title='".findtekst('202|Hvis du aktiverer massefakturering', $sprog_id)."'>".findtekst('201|Aktiver massefakturering', $sprog_id)."</td>\n";
	print "<td><input name='brug_mfakt' class='inputbox' type='checkbox' $brug_mfakt></td>\n</tr>\n";
	print "<tr>\n<td title='".findtekst('204|Hvis du afmærker dette felt', $sprog_id)."'>".findtekst('203|Medtag delleverancer', $sprog_id)."</td>\n";
	print "<td><input name='brug_dellev' class='inputbox' type='checkbox' $brug_dellev></td>\n</tr>\n";
	print "<tr>\n<td title='".findtekst('206|Her angiver du', $sprog_id)."'>".findtekst('205|Frist for dellevering (dage)', $sprog_id)."</td>\n";
	print "<td><input name='levfrist' class='inputbox' type='text' style='text-align:right' size='3' value='$levfrist'></td>\n</tr>\n";
	print "<tr>\n<td>&nbsp;</td>\n";
	print "<td style='text-align:center'><input class='button green medium' name='submit' type='submit' accesskey='g' value='".findtekst('471|Gem/opdatér', $sprog_id)."'></td>\n</tr>\n";
	print "</form>\n\n";
} # endfunc massefakt
#####################################################




?>
