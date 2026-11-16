<?php
// ------------- lager/happyhour.php ---------- ver 5.1.0 --- 2026-11-04 ---
// LICENS
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261104 LOE MB-62 A read-only Items user may look at a product's happy-hour prices but not save them.
//              The prices print without inputs and without the button, so the page offers no refused action.
@session_start();
$s_id = session_id();

$modulnr = 9;
$title = "HappyHour";
$css = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

// MB-62 a read-only Items user may not write happy-hour prices: the form below inserts, updates and
// deletes varetilbud rows, and $modulnr alone lets that right into the page.
$mb62_readonly = ($rettigheder !== '' && (substr($rettigheder, 9, 1) === '2' || substr($rettigheder, 9, 1) < '1'));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mb62_readonly) {
	error_log('MB-62: happy hour change refused for user ' . $brugernavn . ' (Items right ' . substr($rettigheder, 9, 1) . ')');
	print "<div style='text-align:center;margin-top:14px;'><a target='_top' title='Tilbage til varekortet'";
	print " href='varekort.php?id=" . (int) if_isset($_GET['vare_id']) . "'";
	print " style='display:inline-block;padding:8px 18px;border:1px solid #18689b;border-radius:5px;background:#1c7cb9;"
		. "color:#fff;text-decoration:none;font:14px Arial,Helvetica,sans-serif;'>&#8592; " . findtekst('30|Tilbage', $sprog_id) . "</a></div>\n";
	exit;
}

$submit = if_isset($_POST['submit']);
$vare_id = if_isset($_GET['vare_id']);

if (isset($_POST['salgspris']) && $_POST['salgspris']) {
	$id = if_isset($_POST['id'], array());
	$startdato = $_POST['startdato'];
	$slutdato = $_POST['slutdato'];
	$starttid = $_POST['starttid'];
	$sluttid = $_POST['sluttid'];
	$salgspris = $_POST['salgspris'];
	$kostpris = $_POST['kostpris'];
	$ugedag = $_POST['ugedag'];
	$slet = $_POST['slet'];

	$startdag = $startdato;
	$slutdag = $slutdato;


	for ($x = 0; $x <= count($id) + 1; $x++) {
		if ($slet[$x] && $id[$x]) {
			db_modify("delete from varetilbud where id=$id[$x]", __FILE__ . " linje " . __LINE__);
		} else {
			$startdag[$x] = strtotime(usdate($startdato[$x]));
			$slutdag[$x] = strtotime(usdate($slutdato[$x]));

			if (!$starttid[$x]) {
				$starttid[$x] = "00:00:00";
			} else {
				$starttid[$x] = tjektid($starttid[$x]);
			}

			if (!$sluttid[$x]) {
				$sluttid[$x] = "24:00:00";
			} else {
				$sluttid[$x] = tjektid($sluttid[$x]);
			}

			$salgspris[$x] = (float)$salgspris[$x] * 0.8;
			$kostpris[$x] = (float)$kostpris[$x];

			if ($id[$x]) {
				$qtxt = "update varetilbud set";
				$qtxt .= " startdag='$startdag[$x]',slutdag='$slutdag[$x]',starttid='$starttid[$x]',sluttid='$sluttid[$x]',ugedag='$ugedag[$x]',";
				$qtxt .= "salgspris=" . usdecimal($salgspris[$x]) . ",kostpris=" . usdecimal($kostpris[$x]);
				$qtxt .= " where id='$id[$x]'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			} elseif ($startdag[$x] && $slutdag[$x] && $starttid[$x] && $sluttid[$x] && $ugedag[$x] && $salgspris[$x]) {
				$qtxt = "insert into varetilbud";
				$qtxt .= " (vare_id,startdag,slutdag,starttid,sluttid,ugedag,salgspris,kostpris)";
				$qtxt .= " values ";
				$qtxt .= "('$vare_id','$startdag[$x]','$slutdag[$x]','$starttid[$x]','$sluttid[$x]','$ugedag[$x]','" . usdecimal($salgspris[$x]) . "',";
				$qtxt .= "'" . usdecimal($kostpris[$x]) . "')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}
	}
}


$r = db_fetch_array(db_select("select beskrivelse, salgspris, kostpris from varer where id = $vare_id", __FILE__ . " linje " . __LINE__));
$varenavn = $r['beskrivelse'];
$varepris = $r['salgspris'];
$varekost = $r['kostpris'];

$x = 0;
$id = array();
$startdato[$x] = array();
$slutdato[$x] = array();
$starttid[$x] = array();
$sluttid[$x] = array();
$salgspris[$x] = array();
$kostpris[$x] = array();
$ugedag[$x] = array();

$qtxt = "select * from varetilbud where vare_id='$vare_id' order by startdag,ugedag,starttid";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$id[$x] = $r['id'];
	$startdato[$x] = date("d-m-Y", $r['startdag']);
	$slutdato[$x] = date("d-m-Y", $r['slutdag']);
	$starttid[$x] = $r['starttid'];
	$sluttid[$x] = $r['sluttid'];
	$salgspris[$x] = dkdecimal($r['salgspris'] / 0.8);
	$kostpris[$x] = dkdecimal($r['kostpris']);
	$ugedag[$x] = $r['ugedag'];
	$x++;
}
# MB-62 a read-only user gets the prices without a form, so the page offers nothing its refusal would take back
if (!$mb62_readonly) print "<form name=\"happyhour\" action=\"happyhour.php?vare_id=$vare_id\" method=\"POST\">";
print "<div align=\"center\">\n";
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
print "<td width=\"10%\" $top_bund><a href=\"varekort.php?id=$vare_id\" accesskey=\"L\">Luk</a></td>\n";
print "<td width=\"80%\" $top_bund>$title</td>\n";
print "<td width=\"10%\" $top_bund><br></td>\n";
print "</tbody></table>\n";
print "</td></tr>\n";
print "<tr><td align=\"center\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td colspan=\"2\"></td><td colspan=\"2\" align=\"center\"><b><big>$varenavn</big><b></td></tr>";
print "<tr><td colspan=\"2\"></td><td>Salgspris</td><td align=\"right\">" . dkdecimal($varepris, 2) . "</td></tr>";
print "<tr><td colspan=\"2\"></td><td>Kostpris</td><td align=\"right\">" . dkdecimal($varekost, 2) . "</td></tr>";
print "<tr><td colspan=\"7\"><hr></td></tr>";
print "<tr><td align=\"center\">Aktiv fra</td><td align=\"center\">Aktiv til</td><td align=\"center\" style=\"width:90px\">Fra kl</td><td align=\"center\" style=\"width:90px\">Til kl.</td><td align=\"center\">Tilbudspris<br>(Incl. moms)</td><td align=\"center\">Kostpris<br>(Ex. moms)</td><td align=\"center\">Ugedag</td><td align=\"center\">Slet</td></tr>";

$ugedagsnavn = array(1 => 'Mandag', 2 => 'Tirsdag', 3 => 'Onsdag', 4 => 'Torsdag', 5 => 'Fredag', 6 => 'Lørdag', 7 => 'Søndag');
for ($x = 0; $x < count($id); $x++) {
	print "<tr>";
	if ($mb62_readonly) {
		print "<td>$startdato[$x]</td><td>$slutdato[$x]</td><td>$starttid[$x]</td><td>$sluttid[$x]</td>";
		print "<td style=\"text-align:right\">$salgspris[$x]</td><td style=\"text-align:right\">$kostpris[$x]</td>";
		print "<td align=\"center\">" . ifset($ugedagsnavn, (int)$ugedag[$x], '') . "</td><td></td>";
		print "</tr>";
		continue;
	}
	print "<td>";
	print "<input type=\"hidden\" name=\"id[$x]\" value=\"$id[$x]\">";
	print "<input type=\"text\" style=\"width:90px\" name=\"startdato[$x]\" value=\"$startdato[$x]\">";
	print "</td>";
	print "<td><input type=\"text\" style=\"width:90px\" name=\"slutdato[$x]\" value=\"$slutdato[$x]\"></td>";
	print "<td><input type=\"text\" style=\"width:90px\" name=\"starttid[$x]\" value=\"$starttid[$x]\"></td>";
	print "<td><input type=\"text\" style=\"width:90px\" name=\"sluttid[$x]\" value=\"$sluttid[$x]\"></td>";
	print "<td><input type=\"text\" style=\"width:80px;text-align:right\" name=\"salgspris[$x]\" value=\"$salgspris[$x]\"></td>";
	print "<td><input type=\"text\" style=\"width:80px;text-align:right\" name=\"kostpris[$x]\" value=\"$kostpris[$x]\"></td>";
	print "
    <td>
        <select style=\"width:50px;text-align:right\" name=\"ugedag[$x]\">
            <option value=\"1\"" . ($ugedag[$x] == 1 ? " selected" : "") . ">Mandag</option>
            <option value=\"2\"" . ($ugedag[$x] == 2 ? " selected" : "") . ">Tirsdag</option>
            <option value=\"3\"" . ($ugedag[$x] == 3 ? " selected" : "") . ">Onsdag</option>
            <option value=\"4\"" . ($ugedag[$x] == 4 ? " selected" : "") . ">Torsdag</option>
            <option value=\"5\"" . ($ugedag[$x] == 5 ? " selected" : "") . ">Fredag</option>
            <option value=\"6\"" . ($ugedag[$x] == 6 ? " selected" : "") . ">Lørdag</option>
            <option value=\"7\"" . ($ugedag[$x] == 7 ? " selected" : "") . ">Søndag</option>
        </select>
    </td>";
	print "<td><input type=\"checkbox\" name=\"slet[$x]\"></td>";
	print "</tr>";
}
$x++;
if ($mb62_readonly) {
	print "</tbody></table>\n";
	print "</td></tr></tbody></table>\n";
	print "<div style='text-align:center;margin-top:14px;'><a href=\"varekort.php?id=$vare_id\" style='display:inline-block;padding:8px 18px;border:1px solid #18689b;border-radius:5px;background:#1c7cb9;color:#fff;text-decoration:none;font:14px Arial,Helvetica,sans-serif;'>&#8592; " . findtekst('30|Tilbage', $sprog_id) . "</a></div>\n";
	exit;
}
print "<tr>";
print "<td>";
print "<input type=\"text\" style=\"width:90px\" name=\"startdato[$x]\" value=\"\">";
print "</td>";
print "<td><input type=\"text\" style=\"width:90px\" name=\"slutdato[$x]\" value=\"\"></td>";
print "<td><input type=\"text\" style=\"width:90px\" name=\"starttid[$x]\" value=\"\"></td>";
print "<td><input type=\"text\" style=\"width:90px\" name=\"sluttid[$x]\" value=\"\"></td>";
print "<td><input type=\"text\" style=\"width:80px;text-align:right\" name=\"salgspris[$x]\" value=\"\"</td>";
print "<td><input type=\"text\" style=\"width:80px;text-align:right\" name=\"kostpris[$x]\" value=\"\"></td>";
print "
<td>
	<select style=\"width:50px;text-align:right\" name=\"ugedag[$x]\">
		<option value=\"1\">Mandag</option>
		<option value=\"2\">Tirsdag</option>
		<option value=\"3\">Onsdag</option>
		<option value=\"4\">Torsdag</option>
		<option value=\"5\">Fredag</option>
		<option value=\"6\">Lørdag</option>
		<option value=\"7\">Søndag</option>
	</select>
</td>";
print "</tr>";
print "<tr><td colspan=\"8\" align=\"center\"><br></td></tr>";
print "<tr><td colspan=\"8\" align=\"center\"><input type=\"submit\" name=\"opdater\" value=\"Opdater\"></td></tr>";
print "</td></tr></tbody></table>\n";
print "</td></tr></tbody></table>\n";
print "</form>";

function tjektid($tid)
{
	list($h, $i, $s) = explode(":", $tid);
	$h = (int)$h;
	if ($h > 24)
		$h = 24;
	$i = (int)$i;
	if ($i > 59)
		$i = 59;
	$s = (int)$s;
	if ($s > 59)
		$s = 59;
	if ($h == 24) {
		$i = 0;
		$s = 0;
	}
	$tid = "$h:$i:$s";
	return ($tid);
}
?>