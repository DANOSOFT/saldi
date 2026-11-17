<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|
//
// ------ systemdata/importer_varelokationer.php ------------ ver 5.1.1 -- 2026-11-13 --
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------------
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20260416 PHP8
// 20261015 LOE SST-875 A location from the CSV file is stored without the file's line break, and an
//                 empty field is stored empty instead of as a line break.
// 20260916 SS Declared $permission_key (roles & permissions, phase 3)
// 20261008 SS The page on the new page head (page_bar) instead of the blue bar: title, Tilbage only outside the shell.
// 20261009 SS The page-head title through findtekst so the English and Norwegian UI show it translated.
// 20261031 CL/ASR Tilbage goes to Indstillinger > Import & eksport (settingsSection.php?s=import_export.data), not via the old diverse.php?sektion=div_io redirect.
// 20261101 CL/ASR Tilbage is the shared settings button above the title (settings_back_button(), css/saldi-kit.css), as on every settings page, instead of page_bar's link.
//                 It goes to the settings page the user came from, or the hub; Alt+L kept.
// 20261102 CL/ASR Tilbage goes to the hub, a fixed target, so it never loops between settings pages; settings_back_button() adds Alt+L itself.
//                 The kit is linked as ?v=20261102.
// 20261102 CL/ASR The standard settings layout (settings_layout_open(), includes/settings/layout.php; owner, 2 Nov 2026: every page reached from Indstillinger keeps the left menu).
//                 The left menu of Finans with Import & eksport marked, Tilbage above the title back to Import & eksport, the breadcrumb Indstillinger / Finans / Import & eksport / this page.
//                 The page's own Tilbage and page_bar title are gone; its form and steps are unchanged.
//                 The div that centres the page's content opens inside the content column, so the title and the menu are not centred.
// 20261102 CL/ASR Crashed on an account without warehouses (owner, 2 Nov 2026): count() of a list that was never set.
//                 The warehouses are now counted as the item card counts them - the LG groups once each although they are copied to every fiscal year, and the one standard warehouse 1 when there are none - so such an account imports onto warehouse 1, where its stock rows are.
//                 Also: the warehouse from the form must be one of those (it went into the SQL unchecked); the file's charset is the one chosen on the page (a global $charset hid it, so a UTF-8 file was converted as ISO-8859-1); the location goes on every stock row of the item on the warehouse, as the item card saves it, not only the first; an item number from the file is escaped when shown; blank lines are skipped; the three lines of instructions show (a missing quote hid them); the item lookup is exact, then any case, then numeric value, each taking the first item instead of the last.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Import&eacute;r varelokationer";

include("../includes/connect.php");
$permission_key = 'settings.import_export';
include("../includes/online.php");
include("../includes/std_func.php");
$title = findtekst('7008|Importér varelokationer', $sprog_id);
include("../includes/topline_settings.php");
$returside="settingsSection.php?s=import_export.data";

// The standard settings layout (includes/settings/layout.php): Tilbage, the title and the left menu with the page's row marked.
include_once("../includes/settings/layout.php");
settings_layout_open(array('title' => $title));
print "<div align=\"center\">\n";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1


print "</tr></tbody></table></td></tr>";

$submit=if_isset($_POST['submit']);
$lagre=lokation_lagre($regnaar);

if($submit) {
	$lager=(int)if_isset($_POST, 0, 'lager');
	$tegnset=(if_isset($_POST, '', 'tegnset') == 'UTF-8') ? 'UTF-8' : 'ISO-8859-1';
	if (!isset($lagre[$lager])) {
		print "<tr><td align='center'>Lageret findes ikke - v&aelig;lg et lager p&aring; listen</td></tr>";
		upload($lagre);
	} elseif (isset($_FILES['uploadedfile']['name']) && basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			overfoer_data($filnavn,$lager,$tegnset);
		} else echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
	} else upload($lagre);
} else upload($lagre);

print "</tbody></table>";
settings_layout_close();
print "</body></html>";
#####################################################################################################
/**
 * The warehouses an item can have a location on, counted as the item card counts them (lager/varekort.php).
 *
 * The LG groups by their number, once each although they are copied to every fiscal year. An account
 * without warehouses has the one standard warehouse, number 1, where its stock rows are.
 *
 * @param string $regnaar The current fiscal year, whose copy of a warehouse gives its name.
 * @return array<int, string> Warehouse names keyed by warehouse number.
 */
function lokation_lagre($regnaar) {
	$lagre=array();
	$qtxt = "select kodenr,beskrivelse from grupper where art='LG' ";
	$qtxt.= "order by case when fiscal_year=".(int)$regnaar." then 0 else 1 end, coalesce(fiscal_year,0) desc,id desc";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$nr=(int)$r['kodenr'];
		if ($nr > 0 && !isset($lagre[$nr])) $lagre[$nr]=(string)$r['beskrivelse'];
	}
	if (!$lagre) $lagre=array(1 => '');
	ksort($lagre, SORT_NUMERIC);
	return $lagre;
}

/**
 * Prints the upload form: the file's charset, the warehouse and the file.
 *
 * @param array<int, string> $lagre The warehouses, from lokation_lagre().
 * @return void
 */
function upload($lagre){
	print "<form enctype='multipart/form-data' action='importer_varelokationer.php' method='POST'>\n";
	print "<tr><td width='100%' align='center'><table border='0' cellspacing='0' cellpadding='0'><tbody>\n";
	print "<tr><td colspan='2'>Der kan importeres lokationer for 1 lager ad gangen</td></tr>";
	print "<tr><td colspan='2'>Filen skal bestå af 2 kolonner adskilt af \";\" eller tabulatortegn</td></tr>";
	print "<tr><td colspan='2'>Kolonne 1 skal indeholde varenummer, kolonne 2 skal indeholde lokationen</td></tr>";
	print "<tr><td colspan='2'><hr></td></tr>";
	print "<tr><td>Tegnsæt</td>";
	print "<td align=\"center\"><select style=\"width:160px;text-align:left;\" name=\"tegnset\">";
	print "<option value=\"ISO-8859-1\">ISO-8859-1 (Windows)</option>";
	print "<option value=\"UTF-8\">UTF-8 (Mac/Linux)</option>";
	print "</select></td></tr><tr><td colspan='2'><hr></td></tr>";	
	print "<tr><td>Lager</td>";
	print "<td align=\"center\"><select style=\"width:160px;text-align:left;\" name=\"lager\">";
	foreach ($lagre as $nr => $navn) {
		$tekst = ($navn !== '') ? $nr.":".htmlspecialchars($navn, ENT_QUOTES) : $nr;
		print "<option value=\"$nr\">$tekst</option>";
	}
	print "</select></td></tr><tr><td colspan='2'><hr></td></tr>";	
	print "<tr><td colspan='2' align='center'><input type='hidden' name='MAX_FILE_SIZE' value='2900000'>\n";
	print "<input name='uploadedfile' type='file' /><br /></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td colspan= '2' align='center'><input type='submit' style=\"width:200px\" name='submit' value='Hent' /></td></tr>\n";
	print "<tr><td>&nbsp;</td></tr>\n";
	print "</form>\n";
} # end function upload


/**
 * Reads a CSV file of item numbers and locations and stores the locations on the selected lager.
 *
 * One line is one item: the item number first, then the location, separated by tab, ";" or "," - the one
 * the first lines use most. The line break that ends the line in the file is stripped first, so a
 * location is stored exactly as the file spells it and an empty field is stored empty.
 * The item is found by its exact number, else the number in any case, else the same numeric value.
 * The location goes on every stock row the item has on the warehouse, as the item card saves it; an item
 * without a row there gets one with no stock.
 *
 * @param string $filnavn The CSV file to read.
 * @param int $lager The lager (warehouse) the locations belong to, a number from lokation_lagre().
 * @param string $tegnset The charset chosen for the file on the page, 'UTF-8' or 'ISO-8859-1'.
 * @return void
 */
function overfoer_data($filnavn,$lager,$tegnset) {
	global $db_encode;

	$lager=(int)$lager;
	$dbTegnset=($db_encode == 'UTF8') ? 'UTF-8' : 'ISO-8859-1';
	$upd=0;
	$imp=0;
	$linjer=file($filnavn);
	if (!is_array($linjer)) $linjer=array();

	// the separator the first lines use most; on a tie tab before ";" before ",", and ";" when none is used
	$proeve='';
	foreach (array_slice($linjer,0,3) as $linje) if (trim($linje) !== '') $proeve=$linje;
	$splitter=';';
	$flest=0;
	foreach (array(chr(9), ';', ',') as $tegn) {
		$antal=substr_count($proeve,$tegn);
		if ($antal > $flest) {
			$flest=$antal;
			$splitter=$tegn;
		}
	}

	print "<tr><td colspan=2><hr></td></tr>\n";

	$varenumre=$smaa=$tal=array();
	$q=db_select("select id,varenr from varer order by id",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)){
		$nr=(string)$r['varenr'];
		if (!isset($varenumre[$nr])) $varenumre[$nr]=(int)$r['id'];
		if (!isset($smaa[strtolower($nr)])) $smaa[strtolower($nr)]=(int)$r['id'];
		if (is_numeric($nr) && !isset($tal[(string)($nr*1)])) $tal[(string)($nr*1)]=(int)$r['id'];
	}
	$harRaekke=array();
	$q=db_select("select distinct vare_id from lagerstatus where lager='$lager'",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) $harRaekke[(int)$r['vare_id']]=1;

	transaktion('begin');
	foreach ($linjer as $linje) {
		if ($dbTegnset=='UTF-8' && $tegnset!='UTF-8') $linje=mb_convert_encoding($linje, 'UTF-8', 'ISO-8859-1');
		elseif ($dbTegnset!='UTF-8' && $tegnset=='UTF-8') $linje=mb_convert_encoding($linje, 'ISO-8859-1', 'UTF-8');
		# the line break that ends the line in the file is not part of the last field: without this
		# a location is stored with \r\n or \n at the end, and an empty field becomes a line break
		$linje=rtrim($linje,"\r\n");
		if (trim($linje) === '') continue;
		$felter=explode($splitter,$linje);
		$varenr=$felter[0];
		$lokation=isset($felter[1]) ? $felter[1] : '';
		$vare_id=0;
		if (isset($varenumre[$varenr])) $vare_id=$varenumre[$varenr];
		elseif (isset($smaa[strtolower($varenr)])) $vare_id=$smaa[strtolower($varenr)];
		elseif (is_numeric($varenr) && isset($tal[(string)($varenr*1)])) $vare_id=$tal[(string)($varenr*1)]; #20161015
		if (!$vare_id) {
			print "<tr><td align='center'>Varenr \"".htmlspecialchars($varenr, ENT_QUOTES)."\" ikke fundet i varelisten</td></tr>";
			continue;
		}
		if (isset($harRaekke[$vare_id])) {
			$qtxt="update lagerstatus set lok1='".db_escape_string($lokation)."' where vare_id='$vare_id' and lager='$lager'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$upd++;
		} elseif ($lokation !== '') {
			$qtxt = "insert into lagerstatus(vare_id,lager,variant_id,beholdning,lok1) values ";
			$qtxt.= "('$vare_id','$lager','0','0','".db_escape_string($lokation)."')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$harRaekke[$vare_id]=1;
			$imp++;
		}
	}
	transaktion('commit');
	print "<tr><td align='center'>$upd lokationer opdateret i lager $lager</td></tr>";
	print "<tr><td align='center'>$imp lokationer oprettet i lager $lager</td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}
?>
