<?php
// 20260922 CDX/LUI Initialize receipt demand for products without sales orders and reuse the decimal helper.
// 20260921 CDX/LH Render receipt descriptions as text rather than numeric quantities.
// 20260921 CDX/LH Receive lists once and conserve purchase batch/item/warehouse quantities atomically.
// 20260921 CDX/LH Compare and allocate NUMERIC(15,3) stock using exact integer thousandths.
// ------------- kreditor/modtagelse.php ----- (modul nr 6)------ lap 2.0.4----2026-09-22-------
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
// 20260921 CDX/LH Validate receipt quantities, use inserted IDs and migrate edited request lookups to ifset.
// 20260921 CDX/LH Preserve unrestricted database decimals, validate input scale and accept punctuated scans and legacy default warehouses.
// 20260922 CDX/LH Preserve numeric EAN-first scans and prefer literal warehouse1 before legacy0 fallback.

/** Parse new nonnegative input, accepting at most three meaningful decimals.
 * @return string|null Exact decimal text; null is invalid and zero deletes an edited row.
 */
function receiptQuantity($input)
{
	if (!is_scalar($input)) {
		return null;
	}
	$value = trim((string)$input);
	if (preg_match('/^\d{1,3}(?:\.\d{3})+,\d+$/D', $value)) {
		$value = str_replace('.', '', $value);
	}
	if (!preg_match('/^\d+(?:[.,]\d+)?$/D', $value)) {
		return null;
	}
	$value = receiptStockNumber(str_replace(',', '.', $value));
	$parts = explode('.', $value);
	return strlen(ifset($parts, 1, '')) <= 3 ? $value : null;
}

/** Normalize database decimal text without imposing a scale or using floats.
 * @return string Canonical exact decimal, including legacy blank/null as zero.
 */
function receiptStockNumber($value, string $field = 'lagerbeholdning'): string
{
	$text = $value === null || $value === '' ? '0' : (string)$value;
	if (!preg_match('/^(-?)([0-9]+)(?:\.([0-9]+))?$/D', $text, $parts)) {
		throw new RuntimeException('Ugyldig decimalværdi i ' . $field);
	}
	$whole = ltrim($parts[2], '0');
	$whole = $whole === '' ? '0' : $whole;
	$fraction = rtrim(ifset($parts, 3, ''), '0');
	$result = $whole . ($fraction === '' ? '' : '.' . $fraction);
	return $parts[1] === '-' && $result !== '0' ? '-' . $result : $result;
}

/** Use the database decimal engine for arithmetic on already validated quantities.
 * @return string Exact decimal result, or -1/0/1 for comparison.
 */
function receiptStockMath($left, string $operation, $right): string
{
	$left = receiptStockNumber($left);
	$right = receiptStockNumber($right);
	// Decimal literals prevent an integer overflow in otherwise integral sums.
	$left .= strpos($left, '.') === false ? '.0' : '';
	$right .= strpos($right, '.') === false ? '.0' : '';
	if ($operation === '+' || $operation === '-') {
		$expression = "($left $operation $right)";
	} elseif ($operation === 'min' || $operation === 'max') {
		$function = $operation === 'min' ? 'LEAST' : 'GREATEST';
		$expression = "$function($left,$right)";
	} elseif ($operation === 'compare') {
		$expression = "CASE WHEN $left < $right THEN -1 WHEN $left > $right THEN 1 ELSE 0 END";
	} else {
		throw new InvalidArgumentException('Ugyldig lagerberegning');
	}
	$row = db_fetch_array(db_select("SELECT $expression AS quantity", __FILE__ . ' linje ' . __LINE__));
	if (!$row || !array_key_exists('quantity', $row)) {
		throw new RuntimeException('Lagerberegningen kunne ikke udføres');
	}
	return receiptStockNumber($row['quantity']);
}

/** Read this connection's generated ID, never another session's maximum ID. */
function receiptInsertedId($table)
{
	global $db_type;
	if (!in_array($table, array('modtageliste', 'modtagelser'), true)) {
		throw new InvalidArgumentException('Ugyldig modtagelsestabel');
	}
	$sql = in_array($db_type, array('mysql', 'mysqli'), true)
		? 'SELECT LAST_INSERT_ID() AS id'
		: "SELECT currval(pg_get_serial_sequence('$table', 'id')) AS id";
	$row = db_fetch_array(db_select($sql, __FILE__ . ' linje ' . __LINE__));
	if (!$row || (int)$row['id'] < 1) {
		throw new RuntimeException('Modtagelsens ID kunne ikke findes');
	}
	return (int)$row['id'];
}

@session_start();
$s_id = session_id();

$antal_ny = NULL;
$modtag = NULL;
$varenr = NULL;
$i_ordre = '0';
$antal = null;

$modulnr = 6;
$title = "Varemodtagelse";
$css = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

$returside = ifset($_GET, 'returside', '');
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
	$modtag = trim((string)ifset($_POST, 'modtag', ''));
	$antal = receiptQuantity(ifset($_POST, 'antal', ''));
	$pluk = ifset($_POST, 'pluk');
	$rawQuantity = ifset($_POST, 'antal_ny', '');
	$antal_ny = is_scalar($rawQuantity) ? trim((string)$rawQuantity) : '';
	$varenr = trim((string)ifset($_POST, 'varenr', ''));
	$quantityValid = is_scalar($rawQuantity);
	$parsedQuantity = receiptQuantity($rawQuantity);
	$modtaget_antal = $bestilt_antal = 0;

	// A spaced scan carries a quantity and an item identifier. Punctuation in
	// the identifier is valid; SQL escaping happens after identifying the item.
	$scannedItem = null;
	if (strlen($antal_ny) > 10 && preg_match('/^(\S+)\s+(\S+)$/D', $antal_ny, $scan)) {
		// A numeric EAN also parses as a quantity: preserve the legacy
		// long-item-first rule before considering quantity-first input.
		if (strlen($scan[1]) > 10 && receiptQuantity($scan[2]) !== null) {
			$scannedItem = $scan[1];
		} elseif (receiptQuantity($scan[1]) !== null) {
			$scannedItem = $scan[2];
		}
	} elseif (strlen($antal_ny) > 10 && $antal !== null
		&& !preg_match('/^\d+[.,]\d+$/D', $antal_ny)
		&& str_starts_with($antal_ny, $antal)) {
		// Unspaced concatenation is ambiguous with a large integer quantity.
		// Only a known item after the old quantity prefix selects the scan route.
		$candidate = substr($antal_ny, strlen($antal));
		$candidateSql = db_escape_string($candidate);
		if ($candidate !== '' && db_fetch_array(db_select("SELECT id FROM varer WHERE varenr='$candidateSql'", __FILE__ . ' linje ' . __LINE__))) {
			$scannedItem = $candidate;
		}
	}
	if ($scannedItem !== null) {
		$varenr = $scannedItem;
		$id = 0;
	} else {
		$antal = $parsedQuantity;
		// A new item uses its outstanding quantity; an edited row needs explicit input.
		$quantityValid = $quantityValid && ($antal !== null || (!$id && $antal_ny === ''));
	}
	$varenr = db_escape_string($varenr);

	if (!$quantityValid) {
		print "<script>alert('Ugyldigt antal. Brug højst 3 decimaler og et positivt tal eller 0 for at slette linjen.');</script>";
	} elseif ($pluk) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/massefakt.php\">";
	} elseif ($varenr) {
		$q = db_select("select modtagelser.antal as antal from modtagelser, modtageliste where modtagelser.varenr = '$varenr' and modtagelser.liste_id = modtageliste.id and modtageliste.modtaget!='V' and modtagelser.id != $id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$modtaget_antal = receiptStockMath($modtaget_antal, '+', $r['antal']);
		}
		$q = db_select("select ordrelinjer.id as id, ordrelinjer.antal as antal from ordrelinjer, ordrer where ordrelinjer.varenr = '$varenr' and ordrelinjer.ordre_id = ordrer.id and (ordrer.status='1' or ordrer.status='2') and ordrer.art='KO'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$bestilt_antal = receiptStockMath($bestilt_antal, '+', $r['antal']);
			$q2 = db_select("select antal from batch_kob where linje_id=$r[id]", __FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				$bestilt_antal = receiptStockMath($bestilt_antal, '-', $r2['antal']);
			}
		}

		$diff = receiptStockMath($bestilt_antal, '-', $modtaget_antal);

		if ($diff[0] === '-') {
			$diff = '0';
		}
		if (!$diff && $bestilt_antal) {
			print "<BODY onLoad=\"javascript:alert('alle bestilte varer med varenr: $varenr er modtaget')\">";
		} elseif (!$diff) {
			print "<BODY onLoad=\"javascript:alert('Der er ikke nogle &aring;bne indk&oslash;bsordrer p&aring; varenr $varenr')\">";
			$varenr = '';
		} elseif ($id && receiptStockMath($antal, 'compare', $diff) === '1') {
			print "<BODY onLoad=\"javascript:alert('Der kan maksimalt modtages $diff af varenr: $varenr')\">";
			$antal = $diff;
		} elseif ($id) {
			if ($antal !== '0' && $antal !== null) {
				$q2 = db_select("select ordrelinjer.id as id, ordrelinjer.antal as antal from ordrelinjer, ordrer where ordrelinjer.varenr = '$varenr' and ordrelinjer.ordre_id = ordrer.id and (ordrer.status='1' or ordrer.status='2') and ordrer.art='DO'", __FILE__ . " linje " . __LINE__);
				while ($r2 = db_fetch_array($q2)) {
					$i_ordre = receiptStockMath($i_ordre, '+', $r2['antal']);
					$q3 = db_select("select antal from batch_salg where linje_id=$r2[id]", __FILE__ . " linje " . __LINE__);
					while ($r3 = db_fetch_array($q3)) {
						$i_ordre = receiptStockMath($i_ordre, '-', $r3['antal']);
					}
				}
				if (receiptStockMath($i_ordre, 'compare', $antal) === '1') {
					$leveres = $antal;
					$lager = '0';
				} else {
					$leveres = $i_ordre;
					$lager = receiptStockMath($antal, '-', $leveres);
				}
				db_modify("update modtagelser set antal=$antal, leveres=$leveres, lager=$lager where id='$id'", __FILE__ . " linje " . __LINE__);
			} else {
				db_modify("delete from modtagelser where id='$id'", __FILE__ . " linje " . __LINE__);
			}
			$id = 0;
		} else {
			$antal = $diff;
			if ($r = db_fetch_array(db_select("select * from varer where varenr='$varenr'", __FILE__ . " linje " . __LINE__))) {
				if (!$liste_id) {
					$initdate = date('Y-m-d');
					$creator = db_escape_string($brugernavn);
					db_modify("insert into modtageliste (initdate,init_af,modtaget) values ('$initdate','$creator','-')", __FILE__ . " linje " . __LINE__);
					$liste_id = receiptInsertedId('modtageliste');
				}
				$q2 = db_select("select ordrelinjer.id as id, ordrelinjer.antal as antal from ordrelinjer, ordrer where ordrelinjer.varenr = '$varenr' and ordrelinjer.ordre_id = ordrer.id and (ordrer.status='1' or ordrer.status='2') and ordrer.art='DO'", __FILE__ . " linje " . __LINE__);
				while ($r2 = db_fetch_array($q2)) {
					$i_ordre = receiptStockMath($i_ordre, '+', $r2['antal']);
					$q3 = db_select("select antal from batch_salg where linje_id=$r2[id]", __FILE__ . " linje " . __LINE__);
					while ($r3 = db_fetch_array($q3)) {
						$i_ordre = receiptStockMath($i_ordre, '-', $r3['antal']);
					}
				}
				if (receiptStockMath($i_ordre, 'compare', $antal) === '1') {
					$leveres = $antal;
					$lager = '0';
				} else {
					$leveres = $i_ordre;
					$lager = receiptStockMath($antal, '-', $leveres);
				}
				db_modify("insert into modtagelser (vare_id, varenr, beskrivelse, antal,liste_id,leveres,lager) values ('$r[id]', '$varenr', '" . db_escape_string($r['beskrivelse']) . "', '$antal','$liste_id','$leveres','$lager')", __FILE__ . " linje " . __LINE__);
				$id = receiptInsertedId('modtagelser');
				$fokus = "antal_ny";
			} else {
				print "<BODY onLoad=\"javascript:alert('Varenummer $varenr eksisterer ikke')\">";
			}
		}

	}
	if ($modtag && $quantityValid) {
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
	print "<tr><td>$r[varenr]</td><td align=right>".dkDecimal($r['antal'])."</td><td>".htmlspecialchars((string)$r['beskrivelse'], ENT_QUOTES, 'UTF-8')."</td><td align=right>".dkDecimal($r['leveres'])."</td><td align=right>$r[lager]</td>";
	if ($modtaget == '-')
		print "<td align=center><a href=modtagelse.php?liste_id=$liste_id&id=$r[id]>ret</a></td></tr>";
	else
		print "</tr>";
}
if ($modtaget == '-') {
	$r = db_fetch_array(db_select("select * from modtagelser where liste_id=$liste_id and id=$id", __FILE__ . " linje " . __LINE__))
		?: array('varenr' => '', 'antal' => '', 'beskrivelse' => '');
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

/** Receive each list once, keeping item, purchase batches and warehouse stock atomic. */
function modtag($liste_id)
{
	global $brugernavn, $db_modify_fejl;

	$liste_id = (int)$liste_id;
	$started = false;
	try {
		if ($liste_id < 1 || !transaktion('begin')) {
			throw new RuntimeException('Modtagelsen kunne ikke startes');
		}
		$started = true;
		$list = db_fetch_array(db_select("SELECT modtaget FROM modtageliste WHERE id=$liste_id FOR UPDATE", __FILE__ . ' linje ' . __LINE__));
		if (!$list) {
			throw new RuntimeException('Modtagelseslisten findes ikke');
		}
		if ($list['modtaget'] === 'V') {
			$committed = transaktion('commit');
			$started = false;
			return $committed && !$db_modify_fejl;
		}
		$write = function ($sql) {
			global $db_modify_fejl;
			$result = db_modify($sql, __FILE__ . ' linje ' . __LINE__);
			if ($result === false || $db_modify_fejl || (is_string($result) && substr($result, 0, 2) === "1\t")) {
				throw new RuntimeException('Modtagelsen kunne ikke gemmes');
			}
		};
		$date = date('Y-m-d');
		$time = date('H:i:s');
		$rows = db_select("SELECT * FROM modtagelser WHERE liste_id=$liste_id ORDER BY vare_id,id FOR UPDATE", __FILE__ . ' linje ' . __LINE__);
		$receivedRows = 0;
		while ($row = db_fetch_array($rows)) {
			$productId = (int)$row['vare_id'];
			$remaining = receiptStockNumber($row['antal'], 'modtagelseslinje ' . (int)$row['id']);
			if ($productId < 1 || ($remaining === '0' || $remaining[0] === '-')) {
				throw new RuntimeException('Modtagelseslinjen har ugyldigt varenummer eller antal');
			}
			// The product lock serializes separate lists, including creation of a
			// previously absent warehouse row. Lock products in ascending order.
			$product = db_fetch_array(db_select("SELECT beholdning FROM varer WHERE id=$productId FOR UPDATE", __FILE__ . ' linje ' . __LINE__));
			if (!$product) {
				throw new RuntimeException('Varen findes ikke');
			}
			$expectedProductStock = receiptStockMath(receiptStockNumber($product['beholdning'], 'vare ' . $productId), '+', $remaining);
			$lines = db_select("SELECT l.* FROM ordrelinjer l JOIN ordrer o ON o.id=l.ordre_id WHERE l.vare_id=$productId AND o.status IN ('1','2') AND o.art='KO' ORDER BY o.ordredate,o.id,l.id FOR UPDATE", __FILE__ . ' linje ' . __LINE__);
			while ($line = db_fetch_array($lines)) {
				if (($remaining === '0' || $remaining[0] === '-')) {
					break;
				}
				$lineId = (int)$line['id'];
				$orderId = (int)$line['ordre_id'];
				$batch = db_fetch_array(db_select("SELECT COALESCE(SUM(antal),0) AS received FROM batch_kob WHERE linje_id=$lineId", __FILE__ . ' linje ' . __LINE__));
				$beforeReceived = receiptStockNumber($batch['received'], 'købsbatch for linje ' . $lineId);
				$outstanding = receiptStockMath('0', 'max', receiptStockMath($line['antal'], '-', $beforeReceived));
				$quantity = receiptStockMath($remaining, 'min', $outstanding);
				if ($quantity === '0') {
					continue;
				}
				$warehouse = (int)ifset($line, 'lager', 0);
				if (!$warehouse) {
					$warehouse = 1;
				}
				$variantId = (int)ifset($line, 'variant_id', 0);
				if ($warehouse < 1 || $variantId < 0) {
					throw new RuntimeException('Ugyldigt lager eller variant på købslinjen');
				}
				// Other stock writers can legitimately create both literal 0 and 1.
				// Prefer the requested literal; only fall back to legacy 0 if 1 is absent.
				$warehouseCondition = "lager=$warehouse";
				$stockQuery = db_select("SELECT id,beholdning FROM lagerstatus WHERE vare_id=$productId AND COALESCE(variant_id,0)=$variantId AND $warehouseCondition ORDER BY id FOR UPDATE", __FILE__ . ' linje ' . __LINE__);
				$stock = db_fetch_array($stockQuery);
				if (!$stock && $warehouse === 1) {
					$legacyQuery = db_select("SELECT id,beholdning FROM lagerstatus WHERE vare_id=$productId AND COALESCE(variant_id,0)=$variantId AND lager=0 ORDER BY id FOR UPDATE", __FILE__ . ' linje ' . __LINE__);
					$legacyStock = db_fetch_array($legacyQuery);
					if ($legacyStock) {
						$warehouseCondition = 'lager=0';
						$stockQuery = $legacyQuery;
						$stock = $legacyStock;
					}
				}
				if (db_fetch_array($stockQuery)) {
					throw new RuntimeException('Flere lagerbeholdninger for samme vare og lager');
				}
				$expectedWarehouseStock = receiptStockMath($stock ? $stock['beholdning'] : '0', '+', $quantity);
				$quantitySql = $quantity;
				$due = ifset($line, 'batch_due_date') ? "'" . db_escape_string($line['batch_due_date']) . "'" : 'NULL';
				$batchNo = ifset($line, 'batch_batch_no') ? "'" . db_escape_string($line['batch_batch_no']) . "'" : 'NULL';
				$write("INSERT INTO batch_kob(kobsdate,vare_id,variant_id,linje_id,ordre_id,antal,rest,lager,due_date,batch_no) VALUES('$date',$productId,$variantId,$lineId,$orderId,$quantitySql,$quantitySql,$warehouse,$due,$batchNo)");
				$write("UPDATE varer SET beholdning=COALESCE(beholdning,0)+$quantitySql WHERE id=$productId");
				if ($stock) {
					$stockId = (int)$stock['id'];
					$write("UPDATE lagerstatus SET beholdning=COALESCE(beholdning,0)+$quantitySql WHERE id=$stockId");
				} else {
					$write("INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES($productId,$variantId,$warehouse,$quantitySql)");
				}
				if ($variantId) {
					$variant = db_fetch_array(db_select("SELECT variant_beholdning FROM variant_varer WHERE id=$variantId AND vare_id=$productId FOR UPDATE", __FILE__ . ' linje ' . __LINE__));
					if (!$variant) {
						throw new RuntimeException('Købslinjens variant findes ikke');
					}
					$write("UPDATE variant_varer SET variant_beholdning=COALESCE(variant_beholdning,0)+$quantitySql WHERE id=$variantId");
					$afterVariant = db_fetch_array(db_select("SELECT variant_beholdning FROM variant_varer WHERE id=$variantId", __FILE__ . ' linje ' . __LINE__));
					if (receiptStockNumber($afterVariant['variant_beholdning']) !== receiptStockMath($variant['variant_beholdning'], '+', $quantity)) {
						throw new RuntimeException('Variant ' . $variantId . ': lagerændringen kunne ikke gemmes præcist');
					}
				}
				$toReceive = receiptStockMath('0', 'max', receiptStockMath($line['leveres'], '-', $quantity));
				$write("UPDATE ordrelinjer SET leveres=$toReceive WHERE id=$lineId");
				$afterBatch = db_fetch_array(db_select("SELECT COALESCE(SUM(antal),0) AS received FROM batch_kob WHERE linje_id=$lineId", __FILE__ . ' linje ' . __LINE__));
				$afterStock = db_fetch_array(db_select("SELECT COALESCE(SUM(beholdning),0) AS stock FROM lagerstatus WHERE vare_id=$productId AND COALESCE(variant_id,0)=$variantId AND $warehouseCondition", __FILE__ . ' linje ' . __LINE__));
				if (receiptStockNumber($afterBatch['received']) !== receiptStockMath($beforeReceived, '+', $quantity) || receiptStockNumber($afterStock['stock']) !== $expectedWarehouseStock) {
					throw new RuntimeException('Modtagelsens batch og lagerbeholdning stemmer ikke');
				}
				$remaining = receiptStockMath($remaining, '-', $quantity);
			}
			$afterProduct = db_fetch_array(db_select("SELECT beholdning FROM varer WHERE id=$productId", __FILE__ . ' linje ' . __LINE__));
			if ($remaining !== '0' || receiptStockNumber($afterProduct['beholdning']) !== $expectedProductStock) {
				throw new RuntimeException('Modtagelsesantal overstiger åbne købslinjer eller stemmer ikke med lager');
			}
			$receivedRows++;
		}
		if (!$receivedRows) {
			throw new RuntimeException('Modtagelseslisten er tom');
		}
		$actor = db_escape_string($brugernavn);
		$write("UPDATE modtageliste SET modtaget='V',modtaget_af='$actor',modtagdate='$date',tidspkt='$time' WHERE id=$liste_id");
		$posted = db_fetch_array(db_select("SELECT modtaget FROM modtageliste WHERE id=$liste_id", __FILE__ . ' linje ' . __LINE__));
		if (!$posted || $posted['modtaget'] !== 'V') {
			throw new RuntimeException('Modtagelseslisten kunne ikke afsluttes');
		}
		$committed = transaktion('commit');
		$started = false;
		return $committed && !$db_modify_fejl;
	} catch (Throwable $error) {
		if ($started) {
			transaktion('rollback');
		}
		print '<script>alert(' . json_encode($error->getMessage(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ');</script>';
		return false;
	}
}

?>

</body>

</html>
<script language="javascript">
	document.modtagelse.<?php echo $fokus ?>.focus();
</script>