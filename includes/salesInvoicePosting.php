<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/salesInvoicePosting.php --- ver 5.1.1 --- 2026-11-13 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// -------------------------------------------------------------------------
// 20261113 CDX/NTR Extract debtor invoice ledger posting from ordrefunc.php (SD-599).

require_once __DIR__ . '/invoicePosting.php';

/**
 * Post debtor invoices or POS batches with the existing caller-owned transaction.
 *
 * @param int|string $id Order ID or the legacy POS closing list.
 * @param string $kilde Posting source, including Dagsafslutning for POS closings.
 * @return string Existing posting status or validation message.
 */
function post_sales_invoice($id, $kilde) {

	include(__DIR__ . '/genberegn.php');
	include(__DIR__ . '/forfaldsdag.php');
	global $baseCurrency;
	$brugernavn;
	global $db, $db_skriv_id;
	global $regnaar, $reportNumber;
	global $valuta;
	global $valutakurs;
	global $difkto;
	global $title;

	$korttyper = $kortkonti = $vatAccount = array();
	// 20260312 next 2 lines
	if (!$valuta)    $valuta      = $baseCurrency;
	if (!$valutakurs) $valutakurs = 100;

	include __DIR__ . '/opdat_func/ensureInvoiceReportNumber.php';

	($kilde == 'webservice') ? $webservice = 'webservice' : $webservice = NULL;
	$webservice = 'webservice';
	$amount = $lineVatTotal = 0;
	$indbetaling = $konto_id = $kontonr = $no_faktbill = NULL;
	$svar = "OK";

	$reportNumber *= 1;
	$regnaar = $regnaar * 1;

	#20130820 -->
	$x = 0;
	if ($webservice)
		$qtxt = "select distinct(kontonr) as kontonr from kontoplan where (kontotype='D' or kontotype='S')";
	else
		$qtxt = "select kontonr,moms from kontoplan where regnskabsaar='$regnaar' and (kontotype='D' or kontotype='S')";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$kontoplan[$x] = $r['kontonr'];
		$x++;
	}
	# <-- 20130820
	$d_kontrol = 0;
	$k_kontrol = 0;
	$logdate = date("Y-m-d");
	$logtime = date("H:i");
	$uxtid = date("U");
	$q = db_select("select box1, box2, box3, box4, box5 from grupper where art='RB'", __FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		if (trim($r['box3']) == "on")
			$faktbill = 1;
		else {
			$faktbill = 0;
		}
		if (trim($r['box4']) == "on")
			$modtbill = 1;
		else
			$modtbill = 0;
		if (trim($r['box5']) == "on") {
			$no_faktbill = 1;
			$faktbill = 0;
		} else
			$no_faktbill = 0;
	}
	$x = 0;
	$qtxt = "select distinct(box1) from grupper where art = 'SM' and box2 != '0.00' and fiscal_year = '$regnaar' order by box1"; #20190428
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$vatAccount[$x] = $r['box1'];
		$vatAmount[$x] = 0;
		$exVatAmount[$x] = 0;
		$x++;
	}

	$projekt = array();
	$idliste = array();
	if (is_numeric($id))
		$tmp = "id = '" . $id . "'";
	else {
		$idliste = explode(",", $id);
		$antal = count($idliste);
		$tmp = "(id = '" . $idliste[0] . "'";
		for ($x = 1; $x < $antal; $x++)
			$tmp .= " or id = '" . $idliste[$x] . "'";
		$tmp .= ")";
	}
	$x = 0;
	$moms = 0;
	$sum = 0;
	$modtaget = 0;
	$modtaget2 = 0;
	$betaling = array(); #20150518 (Denne og de to næste er flyttet fra "over select * from pos_betalinger")
	$modtaget = array(); #20150518
	$bnr = -1; #20150518
	$qtxt = "select * from ordrer where $tmp";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$art = $r['art'];
		$afd = $r['afd'] * 1; # 21081206
		if ($kilde == 'Dagsafslutning')
			$art = 'PO';
		$ordre_id = $r['id']; #20120905
		$konto_id = $r['konto_id'];
		$kontonr = str_replace(" ", "", $r['kontonr']);
		$kundeordnr = trim($r['kundeordnr']);
		$firmanavn = trim($r['firmanavn']);
		$modtagelse = $r['modtagelse'];
		$transdate = ($r['fakturadate']);
		$fakturanr = $r['fakturanr'];
		$ordrenr = $r['ordrenr'];
		$momssats = $r['momssats'] * 1;
		$valuta = $r['valuta'];
		$kred_ord_id = $r['kred_ord_id'];
		if (!$valuta)
			$valuta = $baseCurrency;
		$projekt[0] = $r['projekt'];
		$betalingsbet = $r['betalingsbet'];
		$betalingsdage = (int)$r['betalingsdage'];
		$betalt = (float)$r['betalt'];
		$felt_1 = $r['felt_1'];
		$felt_2 = $r['felt_2'];
		$felt_3 = $r['felt_3'];
		$felt_4 = $r['felt_4'];
		$felt_5 = $r['felt_5'];
		$betalings_id = $r['betalings_id'];
		if ($felt_1 && is_numeric($felt_2) && is_numeric($felt_4) && is_numeric($felt_5) && (int)$felt_5 > 0) { #20171004 Alm. ordre der behandles som pos
			$qtxt = "select id from pos_betalinger where ordre_id='$ordre_id' limit 1";
			if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$art = 'PO';
			} else {
				$qtxt = "insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs)values('$ordre_id','$felt_1','$felt_2','$baseCurrency','100')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				if ($felt_3 && $felt_4 > 0) {
					$qtxt = "insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs)values('$ordre_id','$felt_3','$felt_4','$baseCurrency','100')";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
				$art = 'PO';
			}
		}
		if ($art == 'PO') { #20150505
			// 20260305 To make sure that cash sale is not acconted on account sale account.
			if ($felt_1 != 'Konto' && $felt_3 != 'Konto') $konto_id	= 0;
			// -----
			$qtxt = "select * from pos_betalinger where ordre_id='$ordre_id' order by betalingstype";
			$q2 = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				if (in_array($r2['betalingstype'], $betaling)) {
					for ($c = 0; $c < count($betaling); $c++) {
						if ($betaling[$c] == $r2['betalingstype']) {
							$modtaget[$c] += $r2['amount'];
						}
					}
				} else {
					$bnr++;
					$betaling[$bnr] = $r2['betalingstype'];
					$modtaget[$bnr] = $r2['amount'];
				}
			}
			$kasse = (int)$r['felt_5'];
			if ($betalingsbet == 'Kontant') {
				$konto_id = 0;
				$kontonr = NULL;
			}
		} else
			$kasse = 0;
		if ($momssats)
			$moms += $r['moms'] * 1;
		$sum += $r['sum'] + $r['moms'];
		$ordreantal = $x;
		$forfaldsdate = usdate(forfaldsdag($r['fakturadate'], $betalingsbet, $betalingsdage));
		if ($art == 'PO')
			$r2 = db_fetch_array(db_select("select id, afd from ansatte where initialer = '$r[ref]'", __FILE__ . " linje " . __LINE__));
		else
			$r2 = db_fetch_array(db_select("select id, afd from ansatte where navn = '$r[ref]'", __FILE__ . " linje " . __LINE__));
		#		$afd=$r2['afd']*1;#sikkerhed for at 'afd' har en vaerdi # udkommenteret 20181128
		$ansat = $r2['id'] * 1;
		if ($no_faktbill == 1)
			$bilag = '0';
		else
			$bilag = trim($fakturanr);
		$udlign = 0;
		$qtxt = "select pris,vat_price,antal,rabat,momssats,momsfri,vat_account from ordrelinjer where ordre_id='$ordre_id' order by vat_account";
		$q2 = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			if ($r2['momssats'] && !$r2['momsfri']) {
				if (!$r2['vat_account'])
					$r2['vat_account'] = $vatAccount[0]; # kan fjernes fra ver 3.7.6
				$lineTotal = afrund($r2['pris'] * $r2['antal'] - $r2['pris'] * $r2['antal'] * $r2['rabat'] / 100, 2);
				$lineVat = afrund($lineTotal * $r2['momssats'] / 100, 2);
				for ($v = 0; $v < count($vatAccount); $v++) {
					if ($r2['vat_account'] == $vatAccount[$v]) {
						$exVatAmount[$v] += $lineTotal;
						$vatAmount[$v] += $lineVat;
						$lineVatTotal += $lineVat;
					}
				}
			}
		}
	}
	if ($kilde == 'Dagsafslutning') { #20180911
		$konto_id = '0';
		$kontonr = NULL;
	}
	# 20260824 Sawaneh JOB-056 ->
	if ($art != 'PO' && ($balancediff = sales_invoice_balance_difference($id, $sum, $valuta, $valutakurs))) {
		$svar = "Bogf&oslash;ring afbrudt: ordren balancerer ikke (difference " . dkdecimal($balancediff, 2) . " $baseCurrency). ";
		$svar .= "Ordrens moms stemmer ikke med ordrelinjernes momssatser - &aring;bn ordren, kontroller konto og momssats p&aring; linjerne og gem den igen f&oslash;r bogf&oslash;ring.";
		$message = $db . " | Bogfoering blokeret foer postering: ordre_id=$id, diff=$balancediff | " . __FILE__ . " linje " . __LINE__ . " | " . $brugernavn . " " . date("Y-m-d H:i:s");
		$headers = 'From: fejl@saldi.dk' . "\r\n" . 'Reply-To: fejl@saldi.dk' . "\r\n" . 'X-Mailer: PHP/' . phpversion();
		mail('fejl@saldi.dk', 'SALDI Fejl', $message, $headers);
		return ($svar);
	}
	# <- 20260824 Sawaneh JOB-056
	if ($konto_id && $kontonr) {
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'", __FILE__ . " linje " . __LINE__));
		$debitorgruppe = $r['gruppe'];
		$r = db_fetch_array(db_select("select box1 from grupper where art='DG' and kodenr='$debitorgruppe' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
		$momskode = substr(trim($r['box1']), 1, 1);
		if ($moms && !$momskode)
			return ("Debitorgruppe $debitorgruppe ikke tilnkyttet en momsgruppe");
	} else { #saa er det en kontantordre
		$tmparray = array();
		$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
		$tmparray = explode(chr(9), $r['box7']);
		$momskode = $tmparray[$kasse - 1];
		if ($moms && !$momskode)
			return ("Fejl i momskode for kasse $kasse!");
	}
	if (!is_numeric($id) || $betalingsbet == 'Kontant' || $betalingsbet == 'Kreditkort' || ($betalingsbet == 'Forud' && $art == 'PO' && $felt_1 != 'konto')) { #20130820+20150306
		$tmp = NULL;
		$openpost = NULL;
		$r = db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '3'", __FILE__ . " linje " . __LINE__));
		if ($betalingsbet == "Kontant")
			$tmp = $r['box7'];
		elseif ($betalingsbet == "Kreditkort")
			$tmp = $r['box10'];
		if ($tmp) {
			if (in_array($tmp, $kontoplan)) {
				$openpost = NULL;
				$kontonr = $tmp;
			} elseif ($art != 'PO') {	#20170802
				$tekst = "Kontonummer for $betalingsbet er ikke gyldigt\\nTjek Indstillinger > Diverse >Ordrerelaterede valg";
				return ($tekst);
			} else
				$openpost = NULL;
		} else
			$openpost = 1;
	} elseif ($art == 'PO' && !$kontonr)
		$openpost = NULL; #20130917 + #20140115
	else
		$openpost = 1;
	$posting = invoice_posting_for_type($art);
	$receivable = $posting->prepareReceivable([
		'invoiceNumber' => $fakturanr,
		'openItem' => $openpost,
		'orderId' => $id,
		'source' => $kilde,
		'webservice' => $webservice,
		'paymentId' => $betalings_id,
		'customerOrderNumber' => $kundeordnr,
		'paymentTerms' => $betalingsbet,
		'register' => $kasse,
		'referenceOrderId' => $kred_ord_id,
		'customerId' => $konto_id,
		'total' => $sum,
		'transactionDate' => $transdate,
	]);
	$beskrivelse = $receivable['description'];
	$udlign = $receivable['settled'];
	$udlign_date = $receivable['settlementDate'];
	$udlign_id = $receivable['settlementId'];
	$qtxt = "select id,ordre_id from transaktioner where ordre_id='$ordre_id'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$tekst = "Bogf&oslash;ring afbrudt - tjek kontrolspor id $r[id] oid $r[ordre_id]";
		print "<BODY onLoad=\"javascript:alert('$tekst')\">";
		return ($tekst);
	}
	$hmlog = fopen("../temp/$db/hmlog.log", "a");
	if (is_numeric($id))
		$id *= 1;
	fwrite($hmlog, __LINE__ . " $kontonr && $konto_id && $openpost && " . is_integer($id) . "\n");
	if ($kontonr && $konto_id && $openpost && is_integer($id)) { #20161211
		$tmp = $sum;
		$qtxt = "select id from openpost where konto_id='$konto_id' and konto_nr='$kontonr' and faktnr='$fakturanr' and amount='$tmp' ";
		$qtxt .= "and beskrivelse='$beskrivelse' and udlignet='$udlign' and transdate='$transdate' and kladde_id='$udlign' and refnr='$id' ";
		$qtxt .= "and valuta='$valuta' and valutakurs='$valutakurs' and forfaldsdate='$forfaldsdate'";
		fwrite($hmlog, __LINE__ . "  $qtxt\n");
		if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			echo "$qtxt<br>";
			$tekst = "Bogf&oslash;ring afbrudt - tjek kontrolspor Fakt. nr $fakturanr";
			print "<BODY onLoad=\"javascript:alert('$tekst')\">";
			return ($tekst);
		}
		if ($udlign && $udlign_id && $udlign_date) {
			$qtxt = "insert into openpost (konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,udlign_id,udlign_date,transdate,uxtid,kladde_id,refnr,";
			$qtxt .= "valuta,valutakurs,forfaldsdate,projekt) values ('$konto_id','$kontonr','$fakturanr','$tmp','$beskrivelse','$udlign','$udlign_id',";
			$qtxt .= "'$udlign_date','$transdate','$uxtid','$udlign','$id','$valuta','$valutakurs','$forfaldsdate','$projekt[0]')";
		} else {
			$qtxt = "insert into openpost (konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,transdate,uxtid,kladde_id,refnr,valuta,valutakurs,";
			$qtxt .= "forfaldsdate,projekt) values ('$konto_id','$kontonr','$fakturanr','$tmp','$beskrivelse','$udlign','$transdate','$uxtid','$udlign',";
			$qtxt .= "'$id','$valuta','$valutakurs','$forfaldsdate','$projekt[0]')";
		}
		fwrite($hmlog, __LINE__ . "  $qtxt\n");
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select max(id) as id from openpost where konto_id = '$konto_id' and faktnr = '$fakturanr' and refnr='$id'", __FILE__ . " linje " . __LINE__));
		$openpost_id = $r['id'];
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'", __FILE__ . " linje " . __LINE__));
		$r = db_fetch_array(db_select("select beskrivelse, box2 from grupper where art = 'DG' and kodenr='$r[gruppe]' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
		$kontonr = $r['box2']; # Kontonr aendres fra at vaere leverandoerkontonr til finanskontonr

		$tekst = "Kontonummer for Debitorgruppe `$r[beskrivelse]` er ikke gyldigt";
		if (!$kontonr && $webservice)
			return ($tekst);
		elseif (!$kontonr)
			print "<BODY onLoad=\"javascript:alert('$tekst')\">";
	}
	#	fwrite($log, __line__." if ($webservice)\n");
	if ($webservice) { # 20181128
		$log = fopen("../temp/$db/bfweb.log", "a");
		fwrite($log, "\n" . date("H:s:i") . "\n");
		$qtxt = "select * from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'";
		fwrite($log, __LINE__ . " $qtxt\n");
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r['box5'])
			$korttyper = explode(chr(9), $r['box5']);
		if ($r['box6'])
			$kortkonti = explode(chr(9), $r['box6']);
		$txt = "if (";
		$txt .= count($korttyper);
		$txt .= " && ";
		$txt .= $felt_2;
		$txt .= " == ";
		$txt .= $sum;
		$txt .= ")";
		fwrite($log, __LINE__ . " $txt\n");
		if (count($korttyper) && $felt_2 == $sum) { # 20181128
			for ($b = 0; $b < count($korttyper); $b++) {
				fwrite($log, __LINE__ . " if ($korttyper[$b]==$felt_1)\n");
				if (strtolower($korttyper[$b]) == strtolower($felt_1)) {
					$kontonr = $kortkonti[$b];
					fwrite($log, __LINE__ . " $kontonr=$kortkonti[$b]\n");
				}
			}
		}
	}
	fclose($log);

	if (!in_array($kontonr, $kontoplan) && $art != 'PO' && !$webservice) {
		$tekst = "Kontonummer $kontonr findes ikke i kontoplanen for regnskabsaar $regnaar";
		return ($tekst);
		exit;
	}
	# if "$art=='PO' && $sum<br>"; # 20181127 rettet fra ($art=='PO' && $sum) da der ikke er nogen sum ved hævning af kontanter!!
	if ($art == 'PO') { #saa er det en kontantordre (POS)
		$retur = $sum;
		$tmparray = array();
		$qtxt = "select box6 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$div_kort_kto = trim($r['box6']);
		$qtxt = "select * from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if (!$konto_id) { #20140616
			$tmparray = explode(chr(9), $r['box2']);
			$kassekto = $tmparray[$kasse - 1];
			$kontonr = $kassekto;
		} #else
		$tmparray = explode(chr(9), $r['box3']);
		#		if (!$afd) $afd=$tmparray[$kasse-1]*1; # udkommenteret 20181128
		$tmparray = explode(chr(9), $r['box5']);
		$l_cardNames = explode(chr(9), strtolower($r['box5']));
		$korttyper = explode(chr(9), $r['box5']);
		$l_cartTypes = explode(chr(9), strtolower($r['box5']));
		$kortkonti = explode(chr(9), $r['box6']);
		$kortnavn = NULL;

		/*
					  if ($div_kort_kto) { #20140129
						  $i=count($korttyper); #20231210
						  $qtxt = "select distinct(betalingstype) as paymenttype from pos_betalinger, ordrer ";
						  $qtxt.= "where betalingstype != 'Kontant' and betalingstype != 'Konto' and betalingstype != '!' ";
						  $qtxt.= "and pos_betalinger.amount != 0 ";
						  $qtxt.= "and ordre_id = pos_betalinger.ordre_id and ordrer.status = 3";
						  $q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
						  while ($r = db_fetch_array($q)) {
							  if (!in_array(strtolower($r['paymenttype']),$l_cartTypes)) {
								  if ($div_kort_kto) {
									  $korttyper[$i] = $r['paymenttype'];
									  $kortkonti[$i] = $div_kort_kto;
									  $i++;
								  } else {
									  alert("Konto for $r[paymenttype] mangler");
									  return "Konto for $r[paymenttype] mangler";
								  }
							  }
						  }
					  }
			  */
		if ($div_kort_kto) { #20140129
			for ($b = 0; $b < count($betaling); $b++) {
				if (
					!in_array(strtolower($betaling[$b]), $l_cardNames)
					&& $betaling[$b] != 'Kontant' && $betaling[$b] != 'Konto'
				) {
					$i = count($kortkonti);
					$korttyper[$i] = $betaling[$b];
					$l_cardNames[$i] = strtolower($betaling[$b]);
					$kortkonti[$i] = $div_kort_kto;
				}
			}
			for ($b = 0; $b < count($betaling); $b++) { #20150505
				if (strpos($betaling[$b], "|"))
					list($betaling[$b], $kortnavn) = explode("|", $betaling[$b]);
			}
			$i = count($korttyper);
			$korttyper[$i] = 'UnknownCard';
			$kortkonti[$i] = $div_kort_kto;
		}
		for ($x = 0; $x < count($kortkonti); $x++) { #20150505
			for ($b = 0; $b < count($betaling); $b++) {
				$cardVat = $cardVatPercent = $cardVatAccount = 0;
				if (strtolower($betaling[$b]) == strtolower($korttyper[$x])) { #20170816
					$kontonr = $kortkonti[$x]; #20150507
					$qtxt = "select moms from kontoplan where kontonr='$kontonr' and regnskabsaar = '$regnaar'";
					if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
						$kodenr = (int) substr($r['moms'], 1);
						$qtxt = "select box1,box2 from grupper where art = 'SM' ";
						$qtxt .= "and kode='S' and kodenr = '$kodenr' and fiscal_year = '$regnaar'";
						if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
							$cardVatAccount = $r['box1'];
							$cardVatPercent = $r['box2'];
						}
					}
					$debet = afrund($modtaget[$b], 2);
					if ($debet && $cardVatAccount && $cardVatPercent) {
						$cardVat = afrund($debet * $cardVatPercent / (100 + $cardVatPercent), 2);
						$debet -= $cardVat;
					}
					$kredit = '0';
					$d_kontrol = $d_kontrol + $debet;
					$k_kontrol = $k_kontrol + $kredit;
					$retur = $retur - $modtaget[$b];
					$sum = $sum - $modtaget[$b];
					if ($debet) {
						$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,debet,kredit,kladde_id,afd,logdate,logtime,";
						$qtxt .= "projekt,ansat,report_number,kasse_nr,moms,faktura,ordre_id) values ";
						$qtxt .= "('0','$transdate','$beskrivelse','$kontonr','$debet','$kredit','0','$afd','$logdate','$logtime',";
						$qtxt .= "'$projekt[0]','$ansat','$reportNumber','$kasse','$cardVat',";
						(is_numeric($id)) ? $qtxt .= "'$fakturanr','$id')" : $qtxt .= "'0','0')";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
						$qtxt = "update kontoplan set saldo=saldo+'$debet' where kontonr='$kontonr' and regnskabsaar='$regnaar'";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					}
					if ($cardVat) {
						$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,debet,kredit,kladde_id,afd,logdate,logtime,";
						$qtxt .= "projekt,ansat,report_number,kasse_nr,moms,faktura,ordre_id) values ";
						$qtxt .= "('0','$transdate','$beskrivelse','$cardVatAccount','$cardVat','$kredit','0','$afd','$logdate','$logtime',";
						$qtxt .= "'$projekt[0]','$ansat','$reportNumber','$kasse','0',";
						(is_numeric($id)) ? $qtxt .= "'$fakturanr','$id')" : $qtxt .= "'0','0')";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
						$qtxt = "update kontoplan set saldo=saldo+'$cardVat' where kontonr='$cardVatAccount' and regnskabsaar='$regnaar'";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					}
				}
			}
		}
		if (!$konto_id) {
			$kontonr = $kassekto; #20150518 (Ellers fortsætter den med at bogføre på samme kontonr) #20150521
		}
	}
	$sum = afrund($sum, 3);
	if ($sum) {
		if ($sum > 0) {
			$debet = $sum;
			$kredit = '0';
		} else {
			$debet = '0';
			$kredit = $sum * -1;
		}
		if ($valutakurs) {
			$kredit = afrund($kredit * $valutakurs / 100, 3);
			$debet = afrund($debet * $valutakurs / 100, 3);
		} # Omregning til DKR.
		$d_kontrol = $d_kontrol + $debet;
		$k_kontrol = $k_kontrol + $kredit;
		$debet = afrund($debet, 2);
		$kredit = afrund($kredit, 2);
		if (is_numeric($id)) {
			$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kontonr','$fakturanr','$debet','$kredit','0',$afd,'$logdate','$logtime','$projekt[0]','$ansat','$id','$kasse')";
		} else {
			$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kontonr','0','$debet','$kredit','0',$afd,'$logdate','$logtime','$projekt[0]','$ansat','0','$kasse')";
		}
		fwrite($hmlog, __LINE__ . "  $qtxt\n");
		fclose($hmlog);
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$tmp = $debet - $kredit;
		$qtxt = "update kontoplan set saldo=saldo+'$tmp' where kontonr='$kontonr' and regnskabsaar='$regnaar'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	if ($valutakurs)
		$maxdif = 2; #Der tillades 2 oeres afrundingsdiff
	$p = 0;
	$projektliste = '';
	if (is_numeric($id))
		$tmp = "ordre_id = '" . $id . "'";
	else {
		$idliste = explode(",", $id);
		$antal = count($idliste);
		$tmp = "(ordre_id = '" . $idliste[0] . "'";
		for ($x = 1; $x < $antal; $x++)
			$tmp .= " or ordre_id = '" . $idliste[$x] . "'";
		$tmp .= ")";
	}
	db_modify("update ordrelinjer set projekt='' where projekt is NULL and $tmp", __FILE__ . " linje " . __LINE__);
	$q = db_select("select distinct(projekt) from ordrelinjer where $tmp and vare_id >'0'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$p++;
		$projekt[$p] = trim($r['projekt']);
		($projektliste) ? $projektliste .= "<br>" . $projekt[$p] : $projektliste = $projekt[$p];
	}
	($p) ? $projektantal = $p : $projektantal = 1;
	if ($projektliste && $openpost) {
		db_modify("update openpost set projekt='$projektliste' where id='$openpost_id'", __FILE__ . " linje " . __LINE__);
	}
	for ($t = 1; $t <= 2; $t++) {
		for ($p = 1; $p <= $projektantal; $p++) {
			$y = 0;
			$tjek = array();
			$bogf_konto = array();
			if (is_numeric($id))
				$tmp = "ordre_id = '" . $id . "'";
			else {
				$idliste = explode(",", $id);
				$antal = count($idliste);
				$tmp = "(ordre_id = '" . $idliste[0] . "'";
				for ($x = 1; $x < $antal; $x++)
					$tmp .= " or ordre_id = '" . $idliste[$x] . "'";
				$tmp .= ")";
			}
			if ($t == 1) {
				$qtxt = "select * from ordrelinjer where $tmp and projekt='$projekt[$p]' and posnr>='0' and bogf_konto > 0 order by bogf_konto,vat_account"; #20191127
			} else {
				$qtxt = "select * from ordrelinjer where $tmp and projekt='$projekt[$p]' and posnr<'0' order by bogf_konto,vat_account";
			}
			$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if ($valutakurs && $valutakurs != 100)
					$maxdif = $maxdif + 2; #Og yderligere 2 pr ordrelinje.
				$tmp = $r['bogf_konto'] . "|" . $r['vat_account'];
				#				if (!in_array($tmp,$bogf_vat) && $r['bogf_konto']) {

				if (!in_array($r['bogf_konto'], $bogf_konto)) {
					$y++;
					$bogf_konto[$y] = $r['bogf_konto'];
					$vat_account[$y] = $r['vat_account'] * 1;
					$bogf_vat[$y] = $bogf_konto[$y] . "|" . $vat_account[$y];

					if ($r['rabatart'] == 'amount') {
						$linjesum = $r['pris'] * $r['antal'] - ($r['rabat'] * $r['antal']); #20140424b
						($r['procent'] || $r['procent'] != '') ? $pris[$y] = $linjesum * $r['procent'] / 100 : $pris[$y] = $linjesum; #20140424b
					} else {
						$linjesum = $r['pris'] * $r['antal'] - ($r['pris'] * $r['antal'] * $r['rabat'] / 100); #20140424b
						($r['procent'] || $r['procent'] != '') ? $pris[$y] = $linjesum * $r['procent'] / 100 : $pris[$y] = $linjesum; #20140424b
						if ($art == 'PO')
							$pris[$y] = afrund($pris[$y], 3);
						else
							$pris[$y] = afrund($pris[$y], 3); #Afrunding tilfoejet 20090126 grundet diff i ordre 98 i saldi_104 -- 20110207 ændret til 2 decimaler ordre_id 1325 saldi_329
					}
					($r['momssats'] && !$r['momsfri']) ? $linjemoms[$y] = afrund($linjesum / 100 * $r['momssats'], 3) : $linjemoms[$y] = 0; #20191127

				} else {
					for ($a = 1; $a <= $y; $a++) {
						if ($bogf_konto[$a] == $r['bogf_konto'] && $vat_account[$a] == $r['vat_account']) {
							if ($r['rabatart'] == 'amount') {
								$linjesum = $r['pris'] * $r['antal'] - ($r['rabat'] * $r['antal']); #20140424b
							} else {
								$linjesum = $r['pris'] * $r['antal'] - ($r['pris'] * $r['antal'] * $r['rabat'] / 100); #20140424b
							}
							($r['procent'] || $r['procent'] != '') ? $pris[$a] += $linjesum * $r['procent'] / 100 : $pris[$a] += $linjesum; #20140424b
							if ($art == 'PO')
								$pris[$a] = afrund($pris[$a], 3);
							else
								$pris[$a] = afrund($pris[$a], 3); #Afrunding tilfoejet 20090126 grundet diff i ordre 98 i saldi_104 -- 20110207 ændret til 2 decimaler ordre_id 1325 saldi_329
							if ($r['momssats'] && !$r['momsfri'])
								$linjemoms[$a] += afrund($linjesum / 100 * $r['momssats'], 3);
						}
					}
				}
			}
			$ordrelinjer = $y;
			if ($indbetaling)
				$ordrelinjer = 0;
			for ($y = 1; $y <= $ordrelinjer; $y++) {
				if ($bogf_konto[$y] && $pris[$y]) {
					if ($pris[$y] > 0) {
						$kredit = $pris[$y];
						$debet = 0;
						$vat = $linjemoms[$y] * -1;
					} else {
						$kredit = 0;
						$debet = $pris[$y] * -1;
						$vat = $linjemoms[$y] * -1;
					}
					if ($t == 1 && $valutakurs) {
						$kredit = $kredit * $valutakurs / 100;
						$debet = $debet * $valutakurs / 100;
						$vat = $vat * $valutakurs / 100;
					} # Omregning til DKR.
					$kredit = afrund($kredit, 3);
					$debet = afrund($debet, 3);
					$d_kontrol = $d_kontrol + $debet;
					$k_kontrol = $k_kontrol + $kredit;
					$debet = afrund($debet, 2);
					$kredit = afrund($kredit, 2);
					$vat = afrund($vat, 2);
					#						$linjemoms[$y]=afrund($linjemoms[$y]*1,2);
					if (is_numeric($id)) {
						$qtxt = "insert into transaktioner ";
						$qtxt .= "(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
						$qtxt .= "projekt,ansat,ordre_id,kasse_nr,moms)";
						$qtxt .= " values ";
						$qtxt .= "('0','$transdate','$beskrivelse','$bogf_konto[$y]','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime',";
						$qtxt .= "'$projekt[$p]','$ansat','$id','$kasse',$vat)";
					} else {
						$qtxt = "insert into transaktioner ";
						$qtxt .= "(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
						$qtxt .= "projekt,ansat,ordre_id,kasse_nr,moms)";
						$qtxt .= " values ";
						$qtxt .= "('0','$transdate','$beskrivelse','$bogf_konto[$y]','0','$debet','$kredit','0','$afd','$logdate',";
						$qtxt .= "'$logtime','$projekt[$p]','$ansat','0','$kasse',$vat)";
					}
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					$tmp = $debet - $kredit;
					$qtxt = "update kontoplan set saldo=saldo+'$tmp' where kontonr='$bogf_konto[$y]' and regnskabsaar='$regnaar'";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					if ($linjemoms[$y]) {
						if (!$vat_account[$y]) {
							include_once(__DIR__ . '/stdFunc/findAccountVat.php');
							$vat_account[$y] = findAccountVat($bogf_konto[$y]);
						}
						if (!$vat_account[$y]) {
							# 20260914 CL/SZ SST-744: bail out before $d_kontrol/$k_kontrol are touched for this
							# line, so a VAT/account setup problem can never leave the control totals holding an
							# amount that was never actually posted anywhere.
							return ("Kontroller moms & momsopsætning: kontonr $bogf_konto[$y] har ingen momskode, men ordre $id har en linje med moms");
						}
						if ($linjemoms[$y] > 0) {
							$kredit = $linjemoms[$y];
							$debet = 0;
						} else {
							$kredit = 0;
							$debet = $linjemoms[$y] * -1;
						}
						if ($t == 1 && $valutakurs) {
							$kredit = $kredit * $valutakurs / 100;
							$debet = $debet * $valutakurs / 100;
						} # Omregning til DKR.
						$kredit = afrund($kredit, 3);
						$debet = afrund($debet, 3);
						$d_kontrol = $d_kontrol + $debet;
						$k_kontrol = $k_kontrol + $kredit;
						$debet = afrund($debet, 2);
						$kredit = afrund($kredit, 2);
						if (is_numeric($id)) {
							$qtxt = "insert into transaktioner ";
							$qtxt .= "(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
							$qtxt .= "projekt,ansat,ordre_id,kasse_nr,moms)";
							$qtxt .= " values ";
							$qtxt .= "('0','$transdate','$beskrivelse','$vat_account[$y]','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime',";
							$qtxt .= "'$projekt[$p]','$ansat','$id','$kasse',0)";
							// echo __line__." $qtxt<br>";
						} else {
							$qtxt = "insert into transaktioner ";
							$qtxt .= "(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
							$qtxt .= "projekt,ansat,ordre_id,kasse_nr,moms)";
							$qtxt .= " values ";
							$qtxt .= "('0','$transdate','$beskrivelse','$vat_account[$y]','0','$debet','$kredit','0','$afd','$logdate',";
							$qtxt .= "'$logtime','$projekt[$p]','$ansat','0','$kasse',0)";
							// echo __line__." $qtxt<br>";
						}
						if ($vat_account[$y] == '1')
							exit;
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
						$tmp = $debet - $kredit;
						$qtxt = "update kontoplan set saldo=saldo+'$tmp' where kontonr='$vat_account[$y]' and regnskabsaar='$regnaar'";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					}
				} elseif ($pris[$y]) {
					$svar = "Fejl i kontoopsætning";
					if (!$webservice)
						print "<BODY onLoad=\"javascript:alert('$svar')\">";
					else
						return ("$svar");
					exit;
				}
			}
		}
	}
	$moms = afrund($moms, 2);
	$lineVatTotal = afrund($lineVatTotal, 2);
	while (count($vatAccount) && afrund($moms - $lineVatTotal, 2) >= 0.01) { #20190311 #20260824 Sawaneh JOB-056 count() da tom kontoliste ellers giver uendelig loekke
		for ($v = 0; $v < count($vatAccount); $v++) {
			if (afrund($moms - $lineVatTotal, 2) >= 0.01) {
				$vatAmount[$v] += 0.01;
				$lineVatTotal += 0.01;
				$moms = afrund($moms, 2);
				$lineVatTotal = afrund($lineVatTotal, 2);
			}
		}
	}
	while (count($vatAccount) && afrund($lineVatTotal - $moms, 2) >= 0.01) { #20190311 #20260824 Sawaneh JOB-056 count()
		for ($v = 0; $v < count($vatAccount); $v++) {
			if (afrund($lineVatTotal - $moms, 2) >= 0.01) {
				$vatAmount[$v] -= 0.01;
				$lineVatTotal -= 0.01;
				$moms = afrund($moms, 2);
				$lineVatTotal = afrund($lineVatTotal, 2);
			}
		}
	}
	if (abs($moms - $lineVatTotal) >= 0.01) {
		alert("Momsfejl $moms != $lineVatTotal");
		return ("Momsfejl $moms != $lineVatTotal");
		exit;
	}

	#xit;
#				$exVatAmount[$v]+=$lineTotal;
#				$vatAmount[$v]+=$lineVat;
#				$lineVatTotal+=$lineVat;
/*
		for ($v=0;$v<count($vatAccount);$v++) {
			if ($vatAmount[$v] > 0) {$kredit=$vatAmount[$v]; $debet='0';}
			else {$kredit='0'; $debet=$vatAmount[$v]*-1;}
			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.
			$kredit=afrund($kredit,3);$debet=afrund($debet,3);
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			$diff=afrund($d_kontrol-$k_kontrol,3);
			$absdiff=abs($diff);
			if ($vatAmount[$v] && $valutakurs && $valutakurs!=100 && $absdiff>=0.01 && $absdiff<=0.05) {
				if ($debet > 0) {
					$debet=$debet+$diff;
					$d_kontrol=$d_kontrol+$diff;
				} elseif ($kredit > 0) {
					$kredit=$kredit+$diff;
					$k_kontrol=$k_kontrol+$diff;
				}
			}
			if ($debet || $kredit) { # 20190220
				if (is_numeric($id)) {
					$qtxt ="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id,kasse_nr)";
					$qtxt.=" values ";
					$qtxt.="('0', '$transdate', '$beskrivelse', '$vatAccount[$v]', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id','$kasse')";
				} else {
					$qtxt ="insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id,kasse_nr)";
					$qtxt.=" values ";
					$qtxt.="('0', '$transdate', '$beskrivelse', '$vatAccount[$v]', '0', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '0','$kasse')";
				}
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$tmp=$debet-$kredit;
				$qtxt="update kontoplan set saldo=saldo+'$tmp' where kontonr='$vatAccount[$v]' and regnskabsaar='$regnaar'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
*/
	/*
						if ($momskode) {
							$query = db_select("select box1 from grupper where art='SM' and kodenr='$momskode'",__FILE__ . " linje " . __LINE__);
							$row = db_fetch_array($query);
							if ($box1=trim($row['box1'])) {# 20150522
								if ($moms > 0) {$kredit=$moms; $debet='0';}
								else {$kredit='0'; $debet=$moms*-1;}
								if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.
								$kredit=afrund($kredit,3);$debet=afrund($debet,3);
								$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
								$diff=afrund($d_kontrol-$k_kontrol,3);
								$absdiff=abs($diff);
								if ($moms && $valutakurs && $valutakurs!=100 && $absdiff>=0.01 && $absdiff<=0.05) {
									if ($debet > 0) {
										$debet=$debet+$diff;
										$d_kontrol=$d_kontrol+$diff;
									} elseif ($kredit > 0) {
										$kredit=$kredit+$diff;
										$k_kontrol=$k_kontrol+$diff;
									}
								}
								$moms=afrund($moms,2);
								if ($moms) {
									if (is_numeric($id)) {
										$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id,kasse_nr) values ('0', '$transdate', '$beskrivelse', '$box1', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id','$kasse')";
									} else {
										$qtxt="insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id,kasse_nr) values ('0', '$transdate', '$beskrivelse', '$box1', '0', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '0','$kasse')";
									}
									db_modify($qtxt,__FILE__ . " linje " . __LINE__);
									$tmp=$debet-$kredit;
									$qtxt="update kontoplan set saldo=saldo+'$tmp' where kontonr='$box1' and regnskabsaar='$regnaar'";
									db_modify($qtxt,__FILE__ . " linje " . __LINE__);
								}
								$valutakurs=$valutakurs*1;
							} elseif ($moms) return ("Fejl i momsopsætning");

						}
				*/
	if (is_numeric($id)) {
		$tmp = "id = '" . $id . "'";
	} else {
		$idliste = explode(",", $id);
		$antal = count($idliste);
		$tmp = "(id = '" . $idliste[0] . "'";
		for ($x = 1; $x < $antal; $x++)
			$tmp .= " or id = '" . $idliste[$x] . "'";
		$tmp .= ")";
	}
	$tidspkt = date("H:i");
	$qtxt = "update ordrer set status='4',report_number='$reportNumber',valutakurs='$valutakurs' where $tmp";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	if (is_numeric($id))
		$tmp = "ordre_id = '" . $id . "'";
	else {
		$idliste = explode(",", $id);
		$antal = count($idliste);
		$tmp = "(ordre_id = '" . $idliste[0] . "'";
		for ($x = 1; $x < $antal; $x++)
			$tmp .= " or ordre_id = '" . $idliste[$x] . "'";
		$tmp .= ")";
	}
	$qtxt = "delete from ordrelinjer where $tmp and posnr < 0 "; #20260630
	$qtxt.= "and exists (select 1 from ordrelinjer ol_keep where ol_keep.ordre_id = ordrelinjer.ordre_id and ol_keep.posnr >= 0)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$d_kontrol = afrund($d_kontrol, 2);
	$k_kontrol = afrund($k_kontrol, 2);
	if ($brugernavn == 'saldi')
		echo __LINE__ . " $d_kontrol != $k_kontrol<br>";

	if ($diff = afrund($d_kontrol - $k_kontrol, 2)) {
		$debet = 0;
		$kredit = 0;
		if ($diff < 0)
			$debet = $diff * -1;
		else
			$kredit = $diff;
		$debet = afrund($debet, 2);
		$kredit = afrund($kredit, 2);
		/*if ($brugernavn == 'saldi') {
				  echo __LINE__ . " Valuta $valuta - Diff $diff - D $debet K $kredit<br>";
				  #	exit;
			  }
			  if ($art == 'PO') { #20140628
				  $r = db_fetch_array(db_select("select box9 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__)); #20170826 +4 linjer
				  $diffkonti = explode(chr(9), $r['box9']);
				  $difkto = $diffkonti[$kasse - 1];
				  $maxdiff = 0.5;
				  /* 20170826
								   $r= db_fetch_array(db_select("select box1,box2 from grupper where grupper.art='OreDif'",__FILE__ . " linje " . __LINE__));
								   $difkto=$r['box2']*1;
								   $maxdif=$r['box1']*100;
						   *//*
				  if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$difkto' and regnskabsaar='$regnaar'", __FILE__ . " linje " . __LINE__))) {
					  return ("Kontonr $difkto (Øredifferencer) eksisterer ikke");
				  }
				  $diff = 0;

				  $q = db_select("select debet,kredit from transaktioner where beskrivelse='$beskrivelse' and logdate='$logdate' and logtime='$logtime' and kasse_nr='$kasse'", __FILE__ . " linje " . __LINE__);
				  while ($r = db_fetch_array($q)) {
					  $d = afrund($r['debet'], 2);
					  $k = afrund($r['kredit'], 2);
					  $diff += $d - $k;
				  }
				  if ($diff) {
					  if ($diff < 0)
						  $debet = $diff * -1;
					  else
						  $kredit = $diff;
					  $debet = afrund($debet, 2);
					  $kredit = afrund($kredit, 2);
					  $qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
					  $qtxt .= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
					  if (is_numeric($id)) {
						  $qtxt .= "('0','$transdate','$beskrivelse','$difkto','$fakturanr','$debet','$kredit',";
						  $qtxt .= "'0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id','$kasse','$reportNumber')";
					  } else {
						  $qtxt .= "('0','$transdate','$beskrivelse','$difkto','0','$debet','$kredit',";
						  $qtxt .= "'0','$afd','$logdate','$logtime','$projekt[0]','$ansat','0','$kasse','$reportNumber')";
					  }
					  $moms = afrund($moms, 2);
					  if ($moms) {
						  if (is_numeric($id)) {
							  $qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id,kasse_nr) values ('0', '$transdate', '$beskrivelse', '$box1', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id','$kasse')";
						  } else {
							  $qtxt = "insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id,kasse_nr) values ('0', '$transdate', '$beskrivelse', '$box1', '0', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '0','$kasse')";
						  }
						  db_modify($qtxt, __FILE__ . " linje " . __LINE__);
						  $tmp = $debet - $kredit;
						  $qtxt = "update kontoplan set saldo=saldo+'$tmp' where kontonr='$box1' and regnskabsaar='$regnaar'";
						  db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					  }
					  $valutakurs = $valutakurs * 1;
				  } elseif ($moms)
					  return ("Fejl i momsopsætning");

			  }
			  if (is_numeric($id)) {
				  $tmp = "id = '" . $id . "'";
			  } else {
				  $idliste = explode(",", $id);
				  $antal = count($idliste);
				  $tmp = "(id = '" . $idliste[0] . "'";
				  for ($x = 1; $x < $antal; $x++)
					  $tmp .= " or id = '" . $idliste[$x] . "'";
				  $tmp .= ")";
			  }
			  $tidspkt = date("H:i");
			  $qtxt = "update ordrer set status='4',report_number='$reportNumber',valutakurs='$valutakurs' where $tmp";
			  db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			  if (is_numeric($id))
				  $tmp = "ordre_id = '" . $id . "'";
			  else {
				  $idliste = explode(",", $id);
				  $antal = count($idliste);
				  $tmp = "(ordre_id = '" . $idliste[0] . "'";
				  for ($x = 1; $x < $antal; $x++)
					  $tmp .= " or ordre_id = '" . $idliste[$x] . "'";
				  $tmp .= ")";
			  }
			  db_modify("delete from ordrelinjer where $tmp and posnr < 0", __FILE__ . " linje " . __LINE__);
			  $d_kontrol = afrund($d_kontrol, 2);
			  $k_kontrol = afrund($k_kontrol, 2);
			  if ($diff = afrund($d_kontrol - $k_kontrol, 2)) {
				  $debet = 0;
				  $kredit = 0;
				  if ($diff < 0)
					  $debet = $diff * -1;
				  else
					  $kredit = $diff;
				  $debet = afrund($debet, 2);
				  $kredit = afrund($kredit, 2);*/
		if ($art == 'PO') { #20140628
			$r = db_fetch_array(db_select("select box9 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__)); #20170826 +4 linjer
			$diffkonti = explode(chr(9), $r['box9']);
			$difkto = $diffkonti[$kasse - 1];
			$maxdiff = 0.5;
			/* 20170826
								$r= db_fetch_array(db_select("select box1,box2 from grupper where grupper.art='OreDif'",__FILE__ . " linje " . __LINE__));
								$difkto=$r['box2']*1;
								$maxdif=$r['box1']*100;
						*/
			if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$difkto' and regnskabsaar='$regnaar'", __FILE__ . " linje " . __LINE__))) {
				return ("Kontonr $difkto (Øredifferencer) eksisterer ikke");
			}
			$diff = 0;

			$q = db_select("select debet,kredit from transaktioner where beskrivelse='$beskrivelse' and logdate='$logdate' and logtime='$logtime' and kasse_nr='$kasse'", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$d = afrund($r['debet'], 2);
				$k = afrund($r['kredit'], 2);
				$diff += $d - $k;
			}
			if ($diff) {
				if ($diff < 0)
					$debet = $diff * -1;
				else
					$kredit = $diff;
				$debet = afrund($debet, 2);
				$kredit = afrund($kredit, 2);
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt .= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
				if (is_numeric($id)) {
					$qtxt .= "('0','$transdate','$beskrivelse','$difkto','$fakturanr','$debet','$kredit',";
					$qtxt .= "'0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id','$kasse','$reportNumber')";
				} else {
					$qtxt .= "('0','$transdate','$beskrivelse','$difkto','0','$debet','$kredit',";
					$qtxt .= "'0','$afd','$logdate','$logtime','$projekt[0]','$ansat','0','$kasse','$reportNumber')";
				}
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$tmp = $debet - $kredit;
				db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$difkto' and regnskabsaar='$regnaar'", __FILE__ . " linje " . __LINE__);
			}
		} elseif ($valuta != $baseCurrency && abs($diff) <= $maxdif) { #Der maa max vaere en afvigelse paa 1 oere pr ordrelinje m fremmed valuta;
			$r = db_fetch_array(db_select("select box3 as kursdifkto from grupper where grupper.art='VK' and grupper.box1='$valuta'", __FILE__ . " linje " . __LINE__));
			$kursdifkto = $r['kursdifkto'] * 1;
			if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$kursdifkto' and regnskabsaar='$regnaar'", __FILE__ . " linje " . __LINE__))) {
				return ("Kontonr $kursdifkto (kursdiff) eksisterer ikke");
			}
			$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
			$qtxt .= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
			if (is_numeric($id)) {
				$qtxt .= "('0','$transdate','$beskrivelse','$kursdifkto','$fakturanr','$debet','$kredit',";
				$qtxt .= "'0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id','$kasse','$reportNumber')";
			} else {
				$qtxt .= "('0','$transdate','$beskrivelse','$kursdifkto','0','$debet','$kredit',";
				$qtxt .= "'0','$afd','$logdate','$logtime','$projekt[0]','$ansat','0','$kasse','$reportNumber')";
			}
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			$tmp = $debet - $kredit;
			db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$kursdifkto' and regnskabsaar='$regnaar'", __FILE__ . " linje " . __LINE__);
		} elseif (abs($diff) < 0.05) {
			$r = db_fetch_array(db_select("select * from grupper where art = 'OreDif'", __FILE__ . " linje " . __LINE__));
			$difkto = $r['box2'];
			if (!$difkto) {
				return (__LINE__ . ' Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff');
			}
			$qtxt = "select id from kontoplan where kontotype = 'D' and kontonr = '$difkto' and regnskabsaar='$regnaar'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt .= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
				if (is_numeric($id)) {
					$qtxt .= "('0','$transdate','$beskrivelse','$difkto','$fakturanr','$debet','$kredit'";
					$qtxt .= ",'0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id','$kasse','$reportNumber')";
				} else {
					$qtxt .= "('0','$transdate','$beskrivelse','$difkto','0','$debet','$kredit'";
					$qtxt .= ",'0','$afd','$logdate','$logtime','$projekt[0]','$ansat','0','$kasse','$reportNumber')";
				}
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$tmp = $debet - $kredit;
				db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$difkto' and regnskabsaar='$regnaar'", __FILE__ . " linje " . __LINE__);
			} else {
				return (__LINE__ . ' Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff');
			}
		} else {
			$svar = "Der er konstateret en uoverensstemmelse i posteringssummen, ID $ordre_id ordre $ordrenr, d=$d_kontrol, k=$k_kontrol kontakt Saldi p&aring; telefon 4690 2208";
			/* if ($art=='PO') echo "$svar<br>"; */
			$message = $db . " | Uoverensstemmelse i posteringssum: ordre_id=$id, d=$d_kontrol, k=$k_kontrol | " . __FILE__ . " linje " . __LINE__ . " | " . $brugernavn . " " . date("Y-m-d H:i:s");
			$headers = 'From: fejl@saldi.dk' . "\r\n" . 'Reply-To: fejl@saldi.dk' . "\r\n" . 'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'SALDI Fejl', $message, $headers);
			return ("$svar"); #20130506
			exit;
		}
	}
	# 201701004 ->
	$qtxt = "select debet,kredit from transaktioner where beskrivelse='$beskrivelse' and logdate='$logdate' and logtime='$logtime' and kasse_nr='$kasse'";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$diff = 0;
	while ($r = db_fetch_array($q)) {
		$diff += afrund($r['debet'], 2) - afrund($r['kredit'], 2);
	}
	if ($diff = afrund($diff, 2)) {
		if ($diff > 0) {
			$debet = 0;
			$kredit = $diff;
		} else {
			$debet = $diff * -1;
			$kredit = 0;
		}
		if (!$difkto) {
			$r = db_fetch_array(db_select("select * from grupper where art = 'OreDif'", __FILE__ . " linje " . __LINE__));
			$difkto = $r['box2'];
			if (!$difkto)
				return ('Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff');
		}
		$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
		$qtxt .= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
		$qtxt .= "('0','$transdate','$beskrivelse','$difkto','$fakturanr','$debet','$kredit'";
		$qtxt .= ",'0','$afd','$logdate','$logtime','$projekt[0]','$ansat',";
		(is_numeric($id)) ? $qtxt .= "'$id'," : $qtxt .= "'0',";
		$qtxt .= "'$kasse','$reportNumber')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$tmp = $debet - $kredit;
		db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$difkto' and regnskabsaar='$regnaar'", __FILE__ . " linje " . __LINE__);
	}
	# <- 201701004
	if ($konto_id) {
		$r = db_fetch_array(db_select("select sum(amount) as amount from openpost where udlignet!='1' and konto_id = '$konto_id'", __FILE__ . " linje " . __LINE__));
		$saldo = $r['amount'] * 1;
		db_modify("update adresser set saldo='$saldo' where id='$konto_id'", __FILE__ . " linje " . __LINE__);
	}
	if ($title != "Massefakturering" && !$webservice && $art != 'PO')
		genberegn($regnaar);
	#xit;
	return ($svar);
} # endfunc bogfor_nu

function sales_invoice_balance_difference($id, $headerTotal, $valuta, $valutakurs)
{
	# 20260824 Sawaneh JOB-056
	# Toerkoersel af posteringsberegningen i bogfor_nu: forudsiger den debet/kredit-difference
	# bogfoeringen vil skabe, saa en ordre i ubalance kan afvises FOER der skrives til
	# transaktioner/openpost. Tolerancerne spejler dem kursdifference-/oeredifference-
	# posteringerne allerede opfanger, saa ordrer der bogfoeres rent i dag paavirkes ikke.
	global $baseCurrency;
	if (!$valutakurs)
		$valutakurs = 100;
	$maxdif = 2;
	if (is_numeric($id))
		$tmp = "ordre_id = '" . $id . "'";
	else {
		$idliste = explode(",", $id);
		$antal = count($idliste);
		$tmp = "(ordre_id = '" . $idliste[0] . "'";
		for ($x = 1; $x < $antal; $x++)
			$tmp .= " or ordre_id = '" . $idliste[$x] . "'";
		$tmp .= ")";
	}
	$p = 0;
	$projekt = array();
	$q = db_select("select distinct(coalesce(projekt,'')) as projekt from ordrelinjer where $tmp and vare_id >'0'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$p++;
		$projekt[$p] = trim($r['projekt']);
	}
	$projektantal = ($p) ? $p : 1;
	if (!$p)
		$projekt[1] = '';
	$kontrol = afrund(afrund($headerTotal, 3) * $valutakurs / 100, 3);
	for ($t = 1; $t <= 2; $t++) {
		for ($p = 1; $p <= $projektantal; $p++) {
			$y = 0;
			$konto = $vatkonto = $net = $vat = array();
			if ($t == 1)
				$qtxt = "select * from ordrelinjer where $tmp and coalesce(projekt,'')='$projekt[$p]' and posnr>='0' and bogf_konto > 0 order by bogf_konto,vat_account";
			else
				$qtxt = "select * from ordrelinjer where $tmp and coalesce(projekt,'')='$projekt[$p]' and posnr<'0' order by bogf_konto,vat_account";
			$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if ($valutakurs != 100)
					$maxdif += 2;
				if (!in_array($r['bogf_konto'], $konto)) {
					$y++;
					$konto[$y] = $r['bogf_konto'];
					$vatkonto[$y] = $r['vat_account'] * 1;
					if ($r['rabatart'] == 'amount') {
						$linjesum = $r['pris'] * $r['antal'] - ($r['rabat'] * $r['antal']);
						($r['procent'] || $r['procent'] != '') ? $net[$y] = $linjesum * $r['procent'] / 100 : $net[$y] = $linjesum;
					} else {
						$linjesum = $r['pris'] * $r['antal'] - ($r['pris'] * $r['antal'] * $r['rabat'] / 100);
						($r['procent'] || $r['procent'] != '') ? $net[$y] = $linjesum * $r['procent'] / 100 : $net[$y] = $linjesum;
						$net[$y] = afrund($net[$y], 3);
					}
					($r['momssats'] && !$r['momsfri']) ? $vat[$y] = afrund($linjesum / 100 * $r['momssats'], 3) : $vat[$y] = 0;
				} else {
					for ($a = 1; $a <= $y; $a++) {
						if ($konto[$a] == $r['bogf_konto'] && $vatkonto[$a] == $r['vat_account']) {
							if ($r['rabatart'] == 'amount')
								$linjesum = $r['pris'] * $r['antal'] - ($r['rabat'] * $r['antal']);
							else
								$linjesum = $r['pris'] * $r['antal'] - ($r['pris'] * $r['antal'] * $r['rabat'] / 100);
							($r['procent'] || $r['procent'] != '') ? $net[$a] += $linjesum * $r['procent'] / 100 : $net[$a] += $linjesum;
							$net[$a] = afrund($net[$a], 3);
							if ($r['momssats'] && !$r['momsfri'])
								$vat[$a] += afrund($linjesum / 100 * $r['momssats'], 3);
						}
					}
				}
			}
			for ($x = 1; $x <= $y; $x++) {
				if (!$konto[$x] || !$net[$x])
					continue;
				if ($t == 1)
					$kontrol -= afrund($net[$x] * $valutakurs / 100, 3) + afrund($vat[$x] * $valutakurs / 100, 3);
				else
					$kontrol -= afrund($net[$x], 3) + afrund($vat[$x], 3);
			}
		}
	}
	$diff = afrund($kontrol, 2);
	if (!$diff)
		return NULL;
	if ($valuta != $baseCurrency && abs($diff) <= $maxdif)
		return NULL;
	if (abs($diff) < 0.05)
		return NULL;
	return $diff;
}
