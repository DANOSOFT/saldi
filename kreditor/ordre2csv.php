<?php
// -------------kreditor/ordre2csv.php----------patch 5.0.0------2026-07-06----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2026 Danosoft.ApS
// ----------------------------------------------------------------------
// 03/02/2025 PBLM fixed lev_varenummer
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20260612 MJ Use creditor order number, not internal id, for exported CSV filename.
// 20260706 MJ CSV filename now uses creditorSuggestion/creditorOrder/creditorInvoice prefix and order number.

@session_start();
$s_id = session_id();
$title = "Ordreeksport";
$css = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$ordre_id = if_isset($_GET['id']);
if (!$ordre_id)
	$ordre_id = 0;

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #A
print "<tr><td valign=top>";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #B1
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=../includes/luk.php accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$title</td>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
print "</tbody></table></td></tr>"; #B1 slut
print "<tr><td valign=top>";
print "<table width=\"400\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #B2

$r = db_fetch_array(db_select("select ordrenr, status, art from ordrer where id = '$ordre_id'", __FILE__ . " linje " . __LINE__));
$ordrenr = $r['ordrenr'] ? $r['ordrenr'] : $ordre_id;
$csvPrefix = "creditorOrder";
if ($r['art'] == 'KO') {
	if ($r['status'] <= 1) $csvPrefix = "creditorSuggestion";
	elseif ($r['status'] > 2) $csvPrefix = "creditorInvoice";
}
$filnavn = "../temp/" . $db . "/" . $csvPrefix . $ordrenr . ".csv";
#echo "Filnavn $filnavn<br>";
$fp = fopen($filnavn, "w");

fwrite($fp, "Pos" . ";" . "Vores varenummer" . ";" . "Deres varenummer" . ";" . "Beskrivelse" . ";" . "Antal" . ";" . "Pris" . ";" . "Rabat" . ";" . "I alt" . "\n");
$q = db_select("select * from ordrelinjer where ordre_id = $ordre_id order by posnr", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$beskrivelse = str_replace(";", " ", $r['beskrivelse']);
	$varenr = str_replace(";", " ", $r['varenr']);
	$lev_vnr = str_replace(";", " ", $r['lev_varenr']);
	if ($charset == 'UTF-8') {
		$beskrivelse = mb_convert_encoding($beskrivelse, 'ISO-8859-1', 'UTF-8');
		$varenr = mb_convert_encoding($varenr, 'ISO-8859-1', 'UTF-8');
		$lev_vnr = mb_convert_encoding($lev_vnr, 'ISO-8859-1', 'UTF-8');
	}
	$antal = dkdecimal($r['antal']);
	$pris = dkdecimal($r['pris']);
	$rabat = dkdecimal($r['rabat']);
	$ialt = dkdecimal($r['pris'] * $r['antal'] - ($r['pris'] * $r['antal'] / 100 * $r['rabat']));

	fwrite($fp, $r["posnr"] . ";" . $varenr . ";" . $lev_vnr . ";" . $beskrivelse . ";" . $antal . ";" . $pris . ";" . $rabat . ";" . $ialt . "\n");
	# Get serialnumbers for export
	$q2 = db_select("select serienr from serienr where kobslinje_id = '$r[id]'", __FILE__ . " linje " . __LINE__);
	while ($r2 = db_fetch_array($q2)) {
		$serienr = $r2["serienr"];
		fwrite($fp,";".$varenr.";".$lev_vnr.";".'sn:'.$serienr.";".";".";"."\n");
	}
}
fclose($fp);

print "<tr><td align=center> Klik her: </td><td $top_bund title=\"&Aring;bner csv filen. H&oslash;jreklik for at gemme\"> <a href=\"$filnavn\">&Aring;ben ordrefil</a></td></tr>";

print "</tbody></table></td></tr>"; #B2 slut
print "</tbody></table>"; #A slut
?>
</body>

</html>
