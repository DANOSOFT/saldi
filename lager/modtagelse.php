<?php
// ------------- kreditor/modtagelse.php ----- (modul nr 6)------ ver 5.0.0----2026-10-21-------
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
// 20261021 CL/LH MB-75: modtag() books each received part into the warehouse and variant of its purchase order line: batch_kob gets lager and variant_id, the lagerstatus row is increased (or created) and variant stock follows.
//                Each purchase order line only takes its own outstanding quantity, and receiving more than is open is refused.
//                An invalid warehouse or variant, duplicate lagerstatus rows or a write that does not add up rolls the whole receipt back with a message (texts 5461-5467).
//                A list that is already received is not booked again; modtaget_af is escaped and tidspkt uses minutes (H:i:s).
// 20261027 CL/LH MB-75: The warehouse-1 comment in modtag() no longer claims kreditor/modtag.php matches lager 0/null rows;
//                it states where this receipt and the product card differ.

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

/**
 * Books a goods receipt list into stock: purchase batches, item stock, warehouse stock and variant stock.
 *
 * Each list row is spread over the open purchase order lines (art KO, status 1 or 2) of its item, oldest
 * order first, and every part is booked into the warehouse and variant of that purchase order line.
 * Everything runs in one transaction that is rolled back, with a message, when a line is invalid, more is
 * received than is open, or a write does not add up. A list that is already received is left untouched,
 * so a resubmitted form cannot book it twice.
 *
 * @param int $liste_id The modtageliste id.
 * @return bool True when the list is received (now or before), false when nothing was booked.
 */
function modtag($liste_id)
{
	global $brugernavn, $db_modify_fejl, $sprog_id;

	$liste_id = (int)$liste_id;
	$fejlVarenr = '';
	$started = false;
	try {
		if ($liste_id < 1) {
			throw new RuntimeException('Varemodtagelsen er afbrudt: modtagelseslisten findes ikke eller er tom', 5467);
		}
		transaktion('begin');
		$started = true;
		$list = db_fetch_array(db_select("select modtaget from modtageliste where id=$liste_id for update", __FILE__ . " linje " . __LINE__));
		if (!$list) {
			throw new RuntimeException('Varemodtagelsen er afbrudt: modtagelseslisten findes ikke eller er tom', 5467);
		}
		if ($list['modtaget'] === 'V') {
			transaktion('commit');
			$started = false;
			return !$db_modify_fejl;
		}
		$date = date("Y-m-d");
		$time = date("H:i:s");
		$warehouses = array(1 => true);
		$receivedRows = 0;
		$q = db_select("select * from modtagelser where liste_id=$liste_id order by vare_id, id for update", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$fejlVarenr = (string)$r['varenr'];
			$vareId = (int)$r['vare_id'];
			$remaining = round((float)$r['antal'], 3);
			if ($vareId < 1 || $remaining <= 0) {
				throw new RuntimeException('Varemodtagelsen er afbrudt: en linje har ugyldigt varenummer eller antal', 5466);
			}
			// Locking the item serializes receipts of the same item, including the insert of a missing
			// lagerstatus row. Rows are read in vare_id order, so items are always locked in the same order.
			$vare = db_fetch_array(db_select("select beholdning from varer where id=$vareId for update", __FILE__ . " linje " . __LINE__));
			if (!$vare) {
				throw new RuntimeException('Varemodtagelsen er afbrudt: en linje har ugyldigt varenummer eller antal', 5466);
			}
			$expectedItemStock = (float)$vare['beholdning'] + $remaining;
			$q2 = db_select("select ordrelinjer.* from ordrelinjer, ordrer where ordrelinjer.vare_id=$vareId and ordrelinjer.ordre_id=ordrer.id and (ordrer.status='1' or ordrer.status='2') and ordrer.art='KO' order by ordrer.ordredate, ordrer.id, ordrelinjer.id for update", __FILE__ . " linje " . __LINE__);
			while ($remaining > 0 && ($r2 = db_fetch_array($q2))) {
				$linjeId = (int)$r2['id'];
				$ordreId = (int)$r2['ordre_id'];
				$r3 = db_fetch_array(db_select("select coalesce(sum(antal),0) as antal from batch_kob where linje_id=$linjeId", __FILE__ . " linje " . __LINE__));
				$receivedBefore = (float)$r3['antal'];
				$quantity = round(min($remaining, max(0, (float)$r2['antal'] - $receivedBefore)), 3);
				if ($quantity <= 0) {
					continue;
				}
				$lager = (int)$r2['lager'];
				if (!$lager) {
					$lager = 1;
				}
				if ($lager < 0) {
					throw new RuntimeException('Varemodtagelsen er afbrudt: lageret på indkøbsordrelinjen findes ikke', 5461);
				}
				if (!isset($warehouses[$lager])) {
					$warehouses[$lager] = (bool)db_fetch_array(db_select("select id from grupper where art='LG' and kodenr='$lager'", __FILE__ . " linje " . __LINE__));
				}
				if (!$warehouses[$lager]) {
					throw new RuntimeException('Varemodtagelsen er afbrudt: lageret på indkøbsordrelinjen findes ikke', 5461);
				}
				// ordrelinjer.variant_id is a text column; empty means no variant.
				$variantTekst = trim((string)$r2['variant_id']);
				if ($variantTekst === '') {
					$variantId = 0;
				} elseif (ctype_digit($variantTekst)) {
					$variantId = (int)$variantTekst;
				} else {
					throw new RuntimeException('Varemodtagelsen er afbrudt: varianten på indkøbsordrelinjen findes ikke', 5462);
				}
				$variant = null;
				if ($variantId) {
					$variant = db_fetch_array(db_select("select variant_beholdning from variant_varer where id=$variantId and vare_id=$vareId for update", __FILE__ . " linje " . __LINE__));
					if (!$variant) {
						throw new RuntimeException('Varemodtagelsen er afbrudt: varianten på indkøbsordrelinjen findes ikke', 5462);
					}
				}
				// Warehouse 1 also takes a legacy lagerstatus row with lager 0 or null, so such a row is increased
				// instead of getting a second row next to it. kreditor/modtag.php, the product card and
				// lagerreguler() match lager exactly, so on a tenant with a 0/null row and no lager=1 row
				// this receipt lands on the 0/null row and the product card does not show it under warehouse 1.
				$lagerBetingelse = ($lager == 1) ? "(lager is null or lager<=1)" : "lager=$lager";
				$stockWhere = "vare_id=$vareId and coalesce(variant_id,0)=$variantId and $lagerBetingelse";
				$q4 = db_select("select id, beholdning from lagerstatus where $stockWhere order by id for update", __FILE__ . " linje " . __LINE__);
				$stock = db_fetch_array($q4);
				if ($stock && db_fetch_array($q4)) {
					throw new RuntimeException('Varemodtagelsen er afbrudt: varen har flere lagerstatus-linjer for samme lager', 5463);
				}
				$expectedWarehouseStock = ($stock ? (float)$stock['beholdning'] : 0) + $quantity;
				$qQuantity = "'" . floatval($quantity) . "'";
				$dueDate = ($r2['batch_due_date'] ?? '') !== '' ? "'" . db_escape_string($r2['batch_due_date']) . "'" : "NULL";
				$batchNo = ($r2['batch_batch_no'] ?? '') !== '' ? "'" . db_escape_string($r2['batch_batch_no']) . "'" : "NULL";
				modtagSkriv("insert into batch_kob(kobsdate,vare_id,variant_id,linje_id,ordre_id,antal,rest,lager,due_date,batch_no) values ('$date',$vareId,$variantId,$linjeId,$ordreId,$qQuantity,$qQuantity,$lager,$dueDate,$batchNo)", __FILE__ . " linje " . __LINE__);
				modtagSkriv("update varer set beholdning=coalesce(beholdning,0)+$qQuantity where id=$vareId", __FILE__ . " linje " . __LINE__);
				if ($stock) {
					modtagSkriv("update lagerstatus set beholdning=coalesce(beholdning,0)+$qQuantity where id=" . (int)$stock['id'], __FILE__ . " linje " . __LINE__);
				} else {
					modtagSkriv("insert into lagerstatus(vare_id,variant_id,lager,beholdning) values ($vareId,$variantId,$lager,$qQuantity)", __FILE__ . " linje " . __LINE__);
				}
				if ($variant) {
					modtagSkriv("update variant_varer set variant_beholdning=coalesce(variant_beholdning,0)+$qQuantity where id=$variantId", __FILE__ . " linje " . __LINE__);
					$after = db_fetch_array(db_select("select variant_beholdning from variant_varer where id=$variantId", __FILE__ . " linje " . __LINE__));
					if (!$after || abs((float)$after['variant_beholdning'] - (float)$variant['variant_beholdning'] - $quantity) > 0.0001) {
						throw new RuntimeException('Varemodtagelsen er afbrudt: lager og batch kunne ikke gemmes. Intet er bogført', 5465);
					}
				}
				$leveres = max(0, (float)$r2['leveres'] - $quantity);
				modtagSkriv("update ordrelinjer set leveres='" . floatval($leveres) . "' where id=$linjeId", __FILE__ . " linje " . __LINE__);
				$afterBatch = db_fetch_array(db_select("select coalesce(sum(antal),0) as antal from batch_kob where linje_id=$linjeId", __FILE__ . " linje " . __LINE__));
				$afterStock = db_fetch_array(db_select("select coalesce(sum(beholdning),0) as beholdning from lagerstatus where $stockWhere", __FILE__ . " linje " . __LINE__));
				if (!$afterBatch || !$afterStock || abs((float)$afterBatch['antal'] - $receivedBefore - $quantity) > 0.0001 || abs((float)$afterStock['beholdning'] - $expectedWarehouseStock) > 0.0001) {
					throw new RuntimeException('Varemodtagelsen er afbrudt: lager og batch kunne ikke gemmes. Intet er bogført', 5465);
				}
				$remaining = round($remaining - $quantity, 3);
			}
			if ($remaining > 0) {
				throw new RuntimeException('Varemodtagelsen er afbrudt: der modtages mere, end der er åbent på indkøbsordrerne', 5464);
			}
			$after = db_fetch_array(db_select("select beholdning from varer where id=$vareId", __FILE__ . " linje " . __LINE__));
			if (!$after || abs((float)$after['beholdning'] - $expectedItemStock) > 0.0001) {
				throw new RuntimeException('Varemodtagelsen er afbrudt: lager og batch kunne ikke gemmes. Intet er bogført', 5465);
			}
			$receivedRows++;
		}
		$fejlVarenr = '';
		if (!$receivedRows) {
			throw new RuntimeException('Varemodtagelsen er afbrudt: modtagelseslisten findes ikke eller er tom', 5467);
		}
		$qBruger = db_escape_string($brugernavn);
		modtagSkriv("update modtageliste set modtaget='V', modtaget_af='$qBruger', modtagdate='$date', tidspkt='$time' where id=$liste_id", __FILE__ . " linje " . __LINE__);
		$list = db_fetch_array(db_select("select modtaget from modtageliste where id=$liste_id", __FILE__ . " linje " . __LINE__));
		if (!$list || $list['modtaget'] !== 'V') {
			throw new RuntimeException('Varemodtagelsen er afbrudt: lager og batch kunne ikke gemmes. Intet er bogført', 5465);
		}
		transaktion('commit');
		$started = false;
		return !$db_modify_fejl;
	} catch (Throwable $e) {
		if ($started) {
			transaktion('rollback');
		}
		// findtekst() reads the database, so the message is looked up after the rollback.
		$code = $e->getCode();
		if ($e instanceof RuntimeException && $code >= 5461 && $code <= 5467) {
			$message = findtekst($code . '|' . $e->getMessage(), $sprog_id);
		} else {
			$message = findtekst('5465|Varemodtagelsen er afbrudt: lager og batch kunne ikke gemmes. Intet er bogført', $sprog_id);
		}
		if ($fejlVarenr !== '') {
			$message .= ' (' . findtekst('917|Varenr.', $sprog_id) . ' ' . $fejlVarenr . ')';
		}
		print "<BODY onLoad=\"javascript:alert(" . htmlspecialchars(jsString(array($message)), ENT_QUOTES, 'UTF-8') . ")\">";
		return false;
	}
}

/**
 * Runs one write of modtag() and throws when it failed, so the whole receipt is rolled back.
 *
 * Outside the webservice db_modify() stops the script on a failed query, which leaves the open
 * transaction uncommitted; inside it the failure is reported through its return value and $db_modify_fejl.
 *
 * @param string $qtxt The query.
 * @param string $spor File and line of the caller, for the log.
 * @return void
 */
function modtagSkriv($qtxt, $spor)
{
	global $db_modify_fejl;

	$result = db_modify($qtxt, $spor);
	if ($db_modify_fejl || (is_string($result) && substr($result, 0, 2) === "1\t")) {
		throw new RuntimeException('Varemodtagelsen er afbrudt: lager og batch kunne ikke gemmes. Intet er bogført', 5465);
	}
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