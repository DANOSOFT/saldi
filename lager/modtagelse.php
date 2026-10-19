<?php
// ------------- kreditor/modtagelse.php ----- (modul nr 6)------ ver 5.0.0----2026-10-19-------
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
// Copyright (c) 2004-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260904 Sawaneh WP-1.3c: luk.php returside now set on the popup=1 request flag, not the popup preference
// 20261019 CL/LH MB-75: antal_ny accepts Danish decimals (1,00) as well as dot decimals and is validated, so non-numeric input shows a message instead of a SQL error.
//                All request values in the SQL are now cast or escaped (db_escape_string instead of addslashes).
//                varenr in the alerts is escaped with jsString().
//                liste_id/id read with ifset() and cast to int; braces added in the POST block.

@session_start();
$s_id = session_id();

$antal_ny = NULL;
$modtag = NULL;
$varenr = NULL;
$i_ordre = NULL;

$modulnr = 6;
$title = "Varemodtagelse";
$css = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/std_funk/dkDecimal.php");
include("../includes/topline_settings.php");

$returside = (if_isset($_GET['returside']));
if (!$returside) {
	// 20260904 Sawaneh WP-1.3c: request flag instead of popup preference
	if (!empty($_GET['popup']))
		$returside = "../includes/luk.php";
	else
		$returside = "modtageliste.php";
}
$returside = nav_back_url($returside);
print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

$liste_id = (int)ifset($_GET, 'liste_id', 0);
$id = (int)ifset($_GET, 'id', 0);
$fokus = "varenr";

if ($_POST) {
	$modtag = addslashes(trim(if_isset($_POST['modtag'])));
	# 	$id=if_isset($_POST['id'])*1;
	$antal = (float)if_isset($_POST['antal']);
	$pluk = if_isset($_POST['pluk']);
	$antal_ny = trim($_POST['antal_ny'] ?? '');
	$varenr = trim($_POST['varenr'] ?? '');
	$antalFejl = false;

	if (strlen($antal_ny) > 10) {
		if (strstr($antal_ny, " ")) {
			list($tmp1, $tmp2) = explode(" ", $antal_ny);
			if (strlen($tmp1) > 10) {
				$varenr = $tmp1;
			} else {
				$varenr = $tmp2;
			}
		} else {
			$tmp = strlen($antal);
			$varenr = substr($antal_ny, $tmp);
		}
		$id = 0;
	} else {
		$nyAntal = modtagelseAntal($antal_ny);
		if ($nyAntal === null) {
			$antalFejl = true;
		} else {
			$antal = $nyAntal;
		}
	}
	$qVarenr = db_escape_string($varenr);
	$jsVarenr = htmlspecialchars(jsString(array($varenr)), ENT_QUOTES, 'UTF-8');

	if ($antalFejl) {
		$alert = jsString(array(findtekst('5460|Antal skal være et tal, f.eks. 2 eller 1,5', $sprog_id)));
		print "<BODY onLoad=\"javascript:alert(" . htmlspecialchars($alert, ENT_QUOTES, 'UTF-8') . ")\">";
		$fokus = "antal_ny";
	} elseif ($pluk) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/massefakt.php\">";
	} elseif ($varenr) {
		$q = db_select("select modtagelser.antal as antal from modtagelser, modtageliste where modtagelser.varenr = '$qVarenr' and modtagelser.liste_id = modtageliste.id and modtageliste.modtaget!='V' and modtagelser.id != $id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$modtaget_antal += $r['antal'];
		}
		$q = db_select("select ordrelinjer.id as id, ordrelinjer.antal as antal from ordrelinjer, ordrer where ordrelinjer.varenr = '$qVarenr' and ordrelinjer.ordre_id = ordrer.id and (ordrer.status='1' or ordrer.status='2') and ordrer.art='KO'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$bestilt_antal += $r['antal'];
			$q2 = db_select("select antal from batch_kob where linje_id=$r[id]", __FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				$bestilt_antal = $bestilt_antal - $r2['antal'];
			}
		}

		$diff = $bestilt_antal - $modtaget_antal;

		if ($diff < 0) {
			$diff = 0;
		}
		if (!$diff && $bestilt_antal) {
			print "<BODY onLoad=\"javascript:alert('alle bestilte varer med varenr: ' + $jsVarenr + ' er modtaget')\">";
		} elseif (!$diff) {
			print "<BODY onLoad=\"javascript:alert('Der er ikke nogle &aring;bne indk&oslash;bsordrer p&aring; varenr ' + $jsVarenr)\">";
			$varenr = '';
		} elseif ($id && $antal > $diff) {
			print "<BODY onLoad=\"javascript:alert('Der kan maksimalt modtages $diff af varenr: ' + $jsVarenr)\">";
			$antal = $diff;
		} elseif ($id) {
			if ($antal > 0) {
				$q2 = db_select("select ordrelinjer.id as id, ordrelinjer.antal as antal from ordrelinjer, ordrer where ordrelinjer.varenr = '$qVarenr' and ordrelinjer.ordre_id = ordrer.id and (ordrer.status='1' or ordrer.status='2') and ordrer.art='DO'", __FILE__ . " linje " . __LINE__);
				while ($r2 = db_fetch_array($q2)) {
					$i_ordre = $i_ordre + $r2['antal'];
					$q3 = db_select("select antal from batch_salg where linje_id=$r2[id]", __FILE__ . " linje " . __LINE__);
					while ($r3 = db_fetch_array($q3)) {
						$i_ordre = $i_ordre - $r3['antal'];
					}
				}
				if ($i_ordre > $antal) {
					$leveres = $antal * 1;
					$lager = 0;
				} else {
					$leveres = $i_ordre * 1;
					$lager = $antal - $leveres;
				}
				db_modify("update modtagelser set antal='" . floatval($antal) . "', leveres='" . floatval($leveres) . "', lager='" . floatval($lager) . "' where id='$id'", __FILE__ . " linje " . __LINE__);
			} else {
				db_modify("delete from modtagelser where id='$id'", __FILE__ . " linje " . __LINE__);
			}
			$id = 0;
		} else {
			$antal = $diff;
			if (!$liste_id) {
				$r = db_fetch_array(db_select("select max(id) as id from modtageliste", __FILE__ . " linje " . __LINE__));
				$liste_id = $r['id'] + 1;
				$initdate = date("Y-m-d");
				db_modify("insert into modtageliste (initdate,init_af,modtaget) values ('$initdate','$brugernavn','-')",__FILE__ . " linje " . __LINE__);
			}
			if ($r = db_fetch_array(db_select("select * from varer where varenr='$qVarenr'", __FILE__ . " linje " . __LINE__))) {
				$q2 = db_select("select ordrelinjer.id as id, ordrelinjer.antal as antal from ordrelinjer, ordrer where ordrelinjer.varenr = '$qVarenr' and ordrelinjer.ordre_id = ordrer.id and (ordrer.status='1' or ordrer.status='2') and ordrer.art='DO'", __FILE__ . " linje " . __LINE__);
				while ($r2 = db_fetch_array($q2)) {
					$i_ordre = $i_ordre + $r2['antal'];
					$q3 = db_select("select antal from batch_salg where linje_id=$r2[id]", __FILE__ . " linje " . __LINE__);
					while ($r3 = db_fetch_array($q3)) {
						$i_ordre = $i_ordre - $r3['antal'];
					}
				}
				if ($i_ordre > $antal) {
					$leveres = $antal * 1;
					$lager = 0;
				} else {
					$leveres = $i_ordre * 1;
					$lager = $antal - $leveres;
				}
				db_modify("insert into modtagelser (vare_id, varenr, beskrivelse, antal,liste_id,leveres,lager) values ('$r[id]', '$qVarenr', '" . db_escape_string($r['beskrivelse']) . "', '" . floatval($antal) . "','$liste_id','" . floatval($leveres) . "','" . floatval($lager) . "')", __FILE__ . " linje " . __LINE__);
				$r = db_fetch_array(db_select("select max(id) as id from modtagelser where varenr='$qVarenr' and antal='" . floatval($antal) . "'", __FILE__ . " linje " . __LINE__));
				$id = $r['id'];
				$fokus = "antal_ny";
			} else {
				print "<BODY onLoad=\"javascript:alert('Varenummer ' + $jsVarenr + ' eksisterer ikke')\">";
			}
		}

	}
	if ($modtag && !$antalFejl) {
		modtag($liste_id);
	}
}
############################
$tekst = findtekst(154, $sprog_id);

if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	print "<div class=\"headerbtnLft headLink\"><a href=$returside accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst(30, $sprog_id) . "</a></div>";
	print "<div class=\"headerTxt\">$title</div>";
	if ($liste_id) {
		print "<div class=\"headerbtnRght headLink\"><a accesskey=N href='modtagelse.php' title='Klik her for at love en ny varemodtagelse'><i class='fa fa-plus-square fa-lg'></i></a></div>";
	} else {
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
	}
	print "</div>";
	print "<div class='content-noside'>";
	print "<center><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" valign=\"top\" class='dataTableSmall'><tbody>";
} elseif ($menu == 'S') {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";

	print "<td width=\"10%\"><a href=\"$returside\" accesskey=L>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">" . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>";

	print "<td width=\"80%\" style='$topStyle' align=\"center\"> " . findtekst('566|Varekort', $sprog_id) . "</td>";

	print "<td width=\"10%\" style='$topStyle'></td>";

	if ($liste_id)
		print "<td width=\"10%\" style='$topStyle' align=\"right\"><a href=\"modtagelse.php\" accesskey=N>" . findtekst('39|Ny', $sprog_id) . "</a></td>";

	print "</td></tbody></table>";
	print "</td></tr>";
	print "<td align = center valign = center>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" valign=\"top\"><tbody>";
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=\"$returside\" accesskey=L>" . findtekst('30|Tilbage', $sprog_id) . "</a></td>"; #20210707
	print "<td width=\"80%\" $top_bund align=\"center\"> " . findtekst('566|Varekort', $sprog_id) . "</td>";
	if ($liste_id)
		print "<td width=\"10%\" $top_bund align=\"right\"><a href=\"modtagelse.php\" accesskey=N>" . findtekst('39|Ny', $sprog_id) . "</a>";
	print "</td></tbody></table>";
	print "</td></tr>";
	print "<td align = center valign = center>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" valign=\"top\"><tbody>";
}

if ($r = db_fetch_array(db_select("select modtaget from modtageliste where id='$liste_id'", __FILE__ . " linje " . __LINE__)))
	$modtaget = $r['modtaget'];
else
	$modtaget = '-';
print "<form name=modtagelse action=modtagelse.php?liste_id=$liste_id&id=$id method=post>";
print "<input type=\"hidden\" name=\"antal\" value=\"$antal\">";
#print "<table border=1><tbody>";
print "<tr><td align=center>" . findtekst('917|Varenr.', $sprog_id) . "</td><td align=center>" . findtekst('916|Antal', $sprog_id) . "</td><td align=center>" . findtekst('914|Beskrivelse', $sprog_id) . "</td><td align=center>" . findtekst('1190|Leveres', $sprog_id) . "</td><td align=center>" . findtekst('608|Lager', $sprog_id) . "</td><td></tr>";
$x = 0;
$q = db_select("select * from modtagelser where liste_id=$liste_id and id!=$id", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$x++;
	print "<tr><td>$r[varenr]</td><td align=right>".dkDecimal($r['antal'])."</td><td>".dkDecimal($r['beskrivelse'])."</td><td align=right>".dkDecimal($r['leveres'])."</td><td align=right>$r[lager]</td>";
	if ($modtaget == '-')
		print "<td align=center><a href=modtagelse.php?liste_id=$liste_id&id=$r[id]>ret</a></td></tr>";
	else
		print "</tr>";
}
if ($modtaget == '-') {
	$r = db_fetch_array(db_select("select * from modtagelser where liste_id=$liste_id and id=$id", __FILE__ . " linje " . __LINE__));
	# do not remove spaces around $r[antal] in next line 
	print "<tr><td><input type=\"text\" size=\"15\" name=\"varenr\" value=\"$r[varenr]\"></td><td><input style=text-align:right type=\"text\" size=\"3\" name=\"antal_ny\" value=\" $r[antal] \"></td><td>$r[beskrivelse]</td><td></td><td></td><td><input type=submit value=\"OK\" name=\"ok\"></td></tr>";
	if ($x)
		print "<tr><td colspan=6 align=center><br><br><input type=submit value=\"modtag\" name=\"modtag\"></td></tr>";
} else {
	# print "<tr><td colspan=6 align=center><br><br><input type=submit value=\"udskriv plukliste\" name=\"pluk\"></td></tr>";
}
print "</form>";
print "</tbody></table>";
print "</td></tr></tbody></table>";

function modtag($liste_id)
{
	global $brugernavn;

	transaktion('begin');
	$date = date("Y-m-d");
	$q = db_select("select * from modtagelser where liste_id=$liste_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$modtaget_antal = $r['antal'];
		$bestilt_antal = 0;
		$x = 0;
		$q2 = db_select("select ordrelinjer.id as id, ordrelinjer.antal as antal, ordrelinjer.ordre_id as ordre_id, ordrelinjer.leveres as leveres from ordrelinjer, ordrer where ordrelinjer.vare_id = '$r[vare_id]' and ordrelinjer.ordre_id = ordrer.id and (ordrer.status='1' or ordrer.status='2') and ordrer.art='KO' order by ordrer.ordredate", __FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$bestilt_antal = $bestilt_antal + $r2['antal'];
			$q3 = db_select("select * from batch_kob where linje_id=$r2[id]", __FILE__ . " linje " . __LINE__);
			while ($r3 = db_fetch_array($q3)) {
				$bestilt_antal = $bestilt_antal - $r3['antal'];
			}
			if ($modtaget_antal >= $bestilt_antal) {
				$tmp = $bestilt_antal;
				$modtaget_antal = $modtaget_antal - $tmp;
			} else {
				$tmp = $modtaget_antal;
				$modtaget_antal = 0;
			}
			if ($tmp) {
				// Look up expiry data from ordrelinjer if available
				$_mod_dd = ""; $_mod_bn = "";
				$_mod_ol = db_fetch_array(db_select("SELECT batch_due_date, batch_batch_no FROM ordrelinjer WHERE id=" . intval($r2['id']), __FILE__ . " linje " . __LINE__));
				if ($_mod_ol) {
					if ($_mod_ol['batch_due_date']) $_mod_dd = ",'" . pg_escape_string($_mod_ol['batch_due_date']) . "'"; else $_mod_dd = ",NULL";
					if ($_mod_ol['batch_batch_no']) $_mod_bn = ",'" . pg_escape_string($_mod_ol['batch_batch_no']) . "'"; else $_mod_bn = ",NULL";
				} else { $_mod_dd = ",NULL"; $_mod_bn = ",NULL"; }
				db_modify("insert into batch_kob(kobsdate,vare_id,linje_id,ordre_id,antal,rest,due_date,batch_no)values('$date','$r[vare_id]','$r2[id]','$r2[ordre_id]','$tmp','$tmp'$_mod_dd$_mod_bn)", __FILE__ . " linje " . __LINE__);
				$r4 = db_fetch_array(db_select("select beholdning from varer where id='$r[vare_id]'", __FILE__ . " linje " . __LINE__));
				$tmp2 = $r4['beholdning'] + $tmp;
				db_modify("update varer set beholdning=$tmp2 where id='$r[vare_id]'", __FILE__ . " linje " . __LINE__);
				$tmp2 = $r2['leveres'] - $tmp;
				if ($tmp2 < 0)
					$tmp2 = 0;
				db_modify("update ordrelinjer set leveres=$tmp2 where id='$r2[id]'", __FILE__ . " linje " . __LINE__);
			}
			$mdate = date("Y-m-d");
			$mtid = date("H:m:s");
		}
		db_modify("update modtageliste set modtaget='V', modtaget_af='$brugernavn', modtagdate='$mdate', tidspkt='$mtid' where id='$liste_id'", __FILE__ . " linje " . __LINE__);
	}
	transaktion('commit');
}

/**
 * Parses the quantity typed in the antal_ny field of a goods receipt line.
 *
 * Input containing a comma is Danish (1,5 or 1.000,5) and goes through usdecimal(). Input without a
 * comma is read with a dot as the decimal separator, because the edit field is prefilled with the
 * database value (e.g. 2.000 for numeric(15,3)), which usdecimal() would turn into 2000.
 *
 * @param string $input The trimmed antal_ny value.
 * @return float|null The quantity (0 for empty input, which deletes the line), or null when the input is not a number.
 */
function modtagelseAntal($input) {
	if ($input === '') {
		return 0.0;
	}
	if (strpos($input, ',') === false) {
		return (is_numeric($input) && is_finite((float)$input)) ? (float)$input : null;
	}
	$normalized = str_replace(',', '.', str_replace('.', '', $input));
	if (!is_numeric($normalized) || !is_finite((float)$normalized)) {
		return null;
	}
	return (float)usdecimal($input, 3);
}

?>

</body>

</html>
<script language="javascript">
	document.modtagelse.<?php echo $fokus ?>.focus();
</script>