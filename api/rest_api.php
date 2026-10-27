<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/rest_api.php --- ver 5.0.0 --- 2026-10-27 ---
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
// Copyright (c) 2016-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20180307 Tilføjet 'pos_betaling' i 'fakturer_ordre' 
// 20180316 Tilføjet 'lagerstatus' i '$allowed_tables' i funktion 'fetch_from_table'
// 20180406 tidspkt indsættes nu ved oprettelse af ordrer og genbruges ved fakturering. 
// 20180426 Tilføjet shop_fakturanr
// 20181218 Tilføjet 'lukket' i into adresser i function insert_shop_order
// 20181227 PHR Diverse tilretninger i forhold til EAN / Kontosalg i funktion fakturer_ordre 
// 20190114 PHR Sets $shop_variant to 0 if not set. 20190114
// 20190123 PHR Moved "write to 'pos_betalinger'" to prior to 'bogfor_nu' as it makes query in 'pos_betalinger' 20190123
// 20190318 PHR Active year in ledger ($regnaar) is set to to the year that matcher the actual time. 20190318
// 20190426 PHR Enhanged function chk4utf8 to better recognition of ISO-8859 chars and added html_entity_decode
// 20190819 PHR function insert_shop_order. 'lev_firmanavn' is now set to 'lev_kontakt' if not set.
// 20190822	PHR changed $kontonr to $saldi_kontonr which prevents multiple creations of same customer. 
// 20190828	PHR minor update to comment above and 'varegruppe' added til 'opret_ordrelinje'. 
// 20201223 PHR Check for closed or non existing account. 
// 20210107 PHR Changed subtraction of 'tidspkt' as it always was set to transfer time 
// 20210314 PHR Corrected error in qtxt: "insert into ordrer' 
// 20210415 PHR Added support for fetch from mysale from ip 91.235.100.32
// 20211027 PHR Added $notes to insert_shop_order.
// 20220215 PHR function insert_shop_order now accepts 0 as shopOrderId. If so table shop_ordrer id left unchanged.
// 20220215 PHR function insert_shop_orderline now returns line id instead of sum.'
// 20220309 PHR Forgot 'as id' in above. :|
// 20220324 PHR	Added call to functon delete order.
// 20220615 PHR function insert_shop_orderline - If item does not exist it will no longer be created
// 20220616 PHR function insert_shop_orderline - added 'saldi_id' as it was missing from query
// 20220711 PHR function insert_shop_orderline - trimming $shop_varenr as unwanted space may occur.
// 20240423 PHR +45 is now removed from saldi_kontonr
// 20241014 PBLM removed fakturadate from insert_shop_order
// 20241203 PHR	added baseCurrency
// 08/01/2025 PBLM change the search criteria for adresser so it no longer search for addr and firmanavn but only uses tlf in insert_shop_order
// 08/01/2025 PBLM change some *1 to (int)
// 04-02-2025 PBLM added discountType to insert_shop_orderline
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20260223 PHR Fixed currency error (Valutakurs)
// 20260608 PHR removed fakturadate from insert_shop_order. It is set in 'fakturer_ordre'
// 20260622 PHR Improved set fakturadate in 'fakturer_ordre
// 20260717 CX/PHR Corrected lev_date to levdate in 'fakturer_ordre'
// 20260729 CL/SZ fakturer_ordre: check $db_modify_fejl before the transaction commits so a
//                failed write in the posting transaction returns an error instead of the
//                order id, since a failed statement aborts the Postgres transaction and the
//                subsequent commit is silently treated as a rollback (SD-595)
// 20260727 CL/SZ Validated/escaped $_GET['db'] in access_check() to close a
//                pre-auth SQL injection on the master connection (SD-588)
// 20260729 CoPilot/NTR - reported by codeRabbit - access check reworking missing db check and logging.
// 20260728 CL/SZ Removed update_table/insert_into_table (unused arbitrary-SQL
//                surface) and narrowed fetch_from_table() to the one known
//                query shape actually used; hardened the access_check() API
//                key comparison (SD-589)
// 20260911 Sawaneh insert_shop_order dispatch: blank the literal "dummyvalue" Shoptech
//                     sends for empty address fields via strip_placeholder_value(); if_isset
//                     calls in that block converted to ifset (JOB-115)
// 20260917 CDX/PHR Restore fixed WooCommerce item lookups with escaped values and legacy response rows.
// 20260917 CDX/PHR Log rejected query fields so external integrations can be diagnosed without API credentials.
// 20260922 CDX/PHR Restore legacy read-table access with parsed queries and migrate fetch inputs to ifset.
// 20261001 CDX/PHR Route pos_50 shop orders and order lines to warehouse 4 after warehouse 10 closes.
// 20261026 CL/LH Ported the 20260917/20260922 fetch_from_table() production hotfix from live into master.
// 20261027 CL/NTR fetch_from_table(): return 'query failed' when db_select() fails instead of
//                reading field metadata from a false result (CodeRabbit).
// 20261023 CL/MMR SST-884 insert_shop_order: an existing customer's CVR/EAN from the card is used when the shop sends none.
//                 The shop's value still wins when it is filled in. cvrnr/ean are escaped in the order insert.
// 20261023 CL/MMR SST-884 insert_shop_order escapes every value it puts into its SQL, cast to string so absent fields stay ''.
//                 A ' in a shop field (ekstra1, ref, kontonr, tlf ...) no longer breaks the query and drops the order.
// 20261027 CL/MMR SST-904 insert_shop_order() decodes the shop's text values (restApiShopText()) before they are escaped, instead of decoding the finished SQL with chk4utf8().
//                 A name like O&#039;Brien became an unescaped quote in the SQL, and the customer and order were not created.
//                 insert_shop_orderline() does the same for the item number lookup and the comment line.
//                 insert_shop_orderline() casts its ids and escapes every shop identifier it puts into SQL (variant, item id, item number, barcode), and includes ordrefunc.php via __DIR__.


// ----------------------------------------------------------------------

date_default_timezone_set('Europe/Copenhagen');

include("../includes/connect.php");
include("../includes/db_query.php");
include("../includes/std_func.php");

$db=NULL;
$db_skriv_id=1;
$brugernavn=NULL;
$db_skriv_id=NULL;
$webservice='on';

// SD-589 removed update_table()/insert_into_table() and the raw select/from/where
// pass-through. Integrations still read through fetch_from_table() (stock export,
// WooCommerce item lookups, legacy table reads), so requests are parsed and compiled
// against the table allowlist in rest_api_fetch.php; request text never reaches SQL as is.
require_once __DIR__ . '/rest_api_fetch.php';

function fetch_from_table($select, $from, $where, $order_by, $limit) {
	global $db;
	$request = restApiFetchRequest($select, $from, $where ?? '');
	$readSql = $request === null
		? restApiFetchSql($select, $from, $where ?? '', $order_by ?? '', $limit ?? '', $_SERVER['REMOTE_ADDR'] ?? '')
		: null;
	$log = fopen("../temp/$db/rest_api.log", 'a');
	if ($request === null && $readSql === null) {
		// JSON keeps each request on one log line; authentication parameters are excluded.
		$rejectedQuery = json_encode(['select' => $select, 'from' => $from, 'where' => $where],
			JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		fwrite($log, __LINE__ . " rejected: unsupported query shape " . $rejectedQuery . "\n");
		fclose($log);
		return 'unsupported query shape';
	}
	if ($readSql !== null) {
		$qtxt = $readSql;
	} elseif ($request['kind'] === 'stock') {
		$qtxt = 'select varenr, beholdning from varer order by varenr';
	} elseif ($request['kind'] === 'item') {
		$sku = db_escape_string($request['sku']);
		$qtxt = "select id from varer where varenr='$sku' order by id limit 1";
	} else {
		$shopId = db_escape_string($request['shop_id']);
		$variantId = db_escape_string($request['shop_variant']);
		$qtxt = "select id from shop_varer where shop_id='$shopId' and shop_variant='$variantId' order by id limit 1";
	}
	fwrite($log, __LINE__ . " Query: $qtxt\n");
	$q = db_select($qtxt, __FILE__ . ' line ' . __LINE__);
	if (!$q) {
		fwrite($log, __LINE__ . " query failed\n");
		fclose($log);
		return 'query failed';
	}
	if ($readSql !== null) {
		// Keep ssl3's metadata row, numeric/named row keys and trailing false sentinel.
		$result = [[]];
		for ($column = 0; $column < db_num_fields($q); $column++) {
			$result[0][] = db_field_name($q, $column) . '(' . db_field_type($q, $column) . ')';
		}
		while ($row = db_fetch_array($q)) {
			$result[] = $row;
		}
		$result[] = false;
		fclose($log);
		return $result;
	}
	$result = $request['kind'] === 'stock' ? [] : [['id(' . db_field_type($q, 0) . ')']];
	while ($r = db_fetch_array($q)) {
		if ($request['kind'] === 'stock') {
			$result[] = ['varenr' => $r['varenr'], 'beholdning' => $r['beholdning']];
		} else {
			$result[] = [0 => $r['id'], 'id' => $r['id']];
		}
	}
	fclose($log);
	return $result;
} #endfunc fetch_from_table

/**
 * SST-884: CVR and EAN for a shop order. The shop's own values win; when the shop sends an empty
 * value and the customer already has a card, the card's value is used, so e-invoices to existing
 * business customers carry the CVR the card has (DK-R-017 otherwise).
 *
 * @param int|string $saldi_addr_id adresser.id of the customer, empty for none
 * @param string     $cvrnr         CVR sent by the shop
 * @param string|int $ean           EAN sent by the shop
 * @return array{0: string, 1: string} [cvrnr, ean]
 */
function shop_order_cvr_ean_from_card($saldi_addr_id, $cvrnr, $ean) {
	$cvrnr = trim((string)$cvrnr);
	$ean = trim((string)$ean);
	$saldi_addr_id = (int)$saldi_addr_id;
	if ($saldi_addr_id && ($cvrnr === '' || $ean === '')) {
		$qtxt = "select cvrnr, ean from adresser where id = '$saldi_addr_id'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			if ($cvrnr === '') $cvrnr = trim((string)$r['cvrnr']);
			if ($ean === '') $ean = trim((string)$r['ean']);
		}
	}
	return array($cvrnr, $ean);
}

function insert_shop_order($brugernavn,$shopOrderId,$shop_fakturanr,$shop_addr_id,$saldi_kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$cvrnr,$ean,$institution,$tlf,$email,$udskriv_til,$ref,$kontakt,$lev_firmanavn,$lev_addr1,$lev_addr2,$lev_postnr,$lev_bynavn,$lev_land,$lev_tlf,$lev_email,$lev_kontakt,$betalingsbet,$betalingsdage,$betalings_id,$ordredate,$lev_date,$momssats,$valuta,$valutakurs,$gruppe,$afd,$projekt,$ekstra1,$ekstra2,$ekstra3,$ekstra4,$ekstra5,$nettosum,$momssum,$lager,$shop_status,$notes,$sprog,$art='DO') {

	global $db,$db_skriv_id;
	global $brugernavn;
	global $webservice;
	global $regnaar;

	$betalingsdage=(int)$betalingsdage;
	// SST-904: the shop's text values are decoded once here, before any of them is escaped into SQL.
	// Decoding the finished SQL (chk4utf8()) turned O&#039;Brien into an unescaped quote and the insert failed.
	$firmanavn = restApiShopText($firmanavn);
	$addr1 = restApiShopText($addr1);
	$addr2 = restApiShopText($addr2);
	$postnr = restApiShopText($postnr);
	$bynavn = restApiShopText($bynavn);
	$land = restApiShopText($land);
	$cvrnr = restApiShopText($cvrnr);
	$ean = restApiShopText($ean);
	$institution = restApiShopText($institution);
	$tlf = restApiShopText($tlf);
	$email = restApiShopText($email);
	$udskriv_til = restApiShopText($udskriv_til);
	$ref = restApiShopText($ref);
	$kontakt = restApiShopText($kontakt);
	$lev_firmanavn = restApiShopText($lev_firmanavn);
	$lev_addr1 = restApiShopText($lev_addr1);
	$lev_addr2 = restApiShopText($lev_addr2);
	$lev_postnr = restApiShopText($lev_postnr);
	$lev_bynavn = restApiShopText($lev_bynavn);
	$lev_land = restApiShopText($lev_land);
	$lev_tlf = restApiShopText($lev_tlf);
	$lev_email = restApiShopText($lev_email);
	$lev_kontakt = restApiShopText($lev_kontakt);
	$betalingsbet = restApiShopText($betalingsbet);
	$ekstra1 = restApiShopText($ekstra1);
	$ekstra2 = restApiShopText($ekstra2);
	$ekstra3 = restApiShopText($ekstra3);
	$ekstra4 = restApiShopText($ekstra4);
	$ekstra5 = restApiShopText($ekstra5);
	$notes = restApiShopText($notes);
	$sprog = restApiShopText($sprog);
	// Route this customer's shop orders to the replacement warehouse.
	if ($db === 'pos_50') {
		$lager = 4;
	}
	if(strtolower($ekstra1) == "betalingskort") {
		$ekstra1 = "Ukendt kort";
	}
	// to be removed in 4.0.2 -->
	$qtxt = "select data_type from information_schema.columns where table_name = 'ordrer' and column_name = 'shop_status'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		if ($r['data_type'] == 'integer') {
			db_modify("ALTER table ordrer alter column shop_status TYPE varchar(20)",__FILE__ . " linje " . __LINE__);
		}
	}
	// <--
	// Add afd column to shop_adresser if not exists (multi-shop support)
	$qtxt = "select column_name from information_schema.columns where table_name = 'shop_adresser' and column_name = 'afd'";
	if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER TABLE shop_adresser ADD COLUMN afd integer DEFAULT 0",__FILE__ . " linje " . __LINE__);
	}
	list($master,$db_skriv_id)=explode('_',$db);
	$log=fopen("../temp/$db/rest_api.log","a");
	fwrite($log,__line__." Ordredate: $ordredate\n");
/*	
	if (strpos('',$ordredate)) list($ordredate,$tidspkt)=explode(' ',$ordredate);
	else $tidspkt=(date('H:i')); 
	$tidspkt=substr($tidspkt,0,5);
*/
	// if ip is 172.105.246.144 set afd to 4
	if (get_ip() == '172.105.246.144') {
		$afd = 4;
	}

	if (strlen($ordredate)>10) { #20210107
		$tidspkt=substr($ordredate,11); 
		#list($ordredate,$tidspkt)=explode(' ',$ordredate);
	}	else $tidspkt=(date('H:i')); 
	$tidspkt=substr($tidspkt,0,5);
	fwrite($log,__line__." Ordredate: $ordredate\n");
	fwrite($log,__line__." Tidspkt: $tidspkt\n");
	
	fwrite($log,__line__." Ordredate: $ordredate\n");
	fwrite($log,__line__." Tidspkt: $tidspkt\n");
	
	fwrite($log,__line__." $brugernavn,$shopOrderId,$shop_fakturanr,$shop_addr_id,$saldi_kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$cvrnr,$ean,$institution,$tlf,$email,$udskriv_til,$ref,$kontakt,$lev_firmanavn,$lev_addr1,$lev_addr2,$lev_postnr,$lev_bynavn,$lev_land,$lev_tlf,$lev_email,$lev_kontakt,$betalingsbet,$betalingsdage,$betalings_id,$ordredate,$lev_date,$momssats,$valuta,$valutakurs,$gruppe,$afd,$projekt,$ekstra1,$ekstra2,$ekstra3,$ekstra4,$ekstra5,$nettosum,$momssum,$lager,$shop_status,$notes,$sprog\n");
	fwrite($log,__line__." Brugernavn: $brugernavn\n");
	fwrite($log,__line__." Shop Ordre id: $shopOrderId afd $afd\n");
	fwrite($log,__line__." Shop Kunde id: $shop_addr_id\n");
	fwrite($log,__line__." Saldi konto nr: $saldi_kontonr\n");
	
	if (!is_numeric($shopOrderId)) {
		fwrite($log,__line__." Ordernumber not numeric ($shopOrderId)\n");
		return "Ordernumber not numeric ($shopOrderId)";
		exit;
	}
	$tlf=str_replace(" ","",$tlf);
	$num_tlf=(int)str_replace("+","",$tlf);

	// Convert to integers
	fwrite($log,__line__." Shop Ordre id: $shopOrderId\n");
	$shopOrderId = (int)$shopOrderId;
    $shop_addr_id = (int)$shop_addr_id;
	fwrite($log,__line__." Shop Ordre id: $shopOrderId\n");

	if (!$shopOrderId || !is_integer($shopOrderId)) {
		fwrite($log,__line__." Illegal order id ($shopOrderId)\n");
		fclose ($log);
		return "Illegal order id ($shopOrderId)";
		exit;
	}

#	if (!$shop_addr_id || !is_integer($shop_addr_id)) {
#		fwrite($log,__line__." Illegal customer id ($shop_addr_id)\n");
#		fclose ($log);
#		return "Illegal customer id ($shop_addr_id)";
#		exit;
#	}

	if (!$firmanavn) {
		$firmanavn=$kontakt;
		$kontakt='';
	}
	if (!$lev_firmanavn) {
		$lev_firmanavn=$lev_kontakt;
		$lev_kontakt='';
	}
	sleep (1);
	
	// Enhanced duplication prevention checks
	if ($shopOrderId) {
		// Primary check: Check if shop order ID already exists with same art type
		fwrite($log,__line__." Checking for existing shop order: $shopOrderId (art: $art)\n");
		$qtxt = "select so.id,so.saldi_id from shop_ordrer so join ordrer o on o.id=so.saldi_id where so.shop_id='".db_escape_string((string)$shopOrderId)."' and o.art='".db_escape_string((string)$art)."'";
		fwrite($log,__line__." $qtxt\n");
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			fwrite($log,__line__." DUPLICATE DETECTED: Order id $shopOrderId (art: $art) already exists in saldi (saldi_id: $r[saldi_id])\n");
			fclose ($log);
			return "Order id: $shopOrderId exists in saldi (internal ID: $r[saldi_id])";
		}
		
		// Additional check: Verify no duplicate orders with same customer details and total amount
		if ($nettosum > 0 && $saldi_addr_id) {
			$duplicate_window = date('Y-m-d', strtotime('-1 day')); // Check last 24 hours
			$qtxt = "select o.id, o.ordrenr, o.sum from ordrer o ";
			$qtxt.= "where o.konto_id='".db_escape_string((string)$saldi_addr_id)."' and o.sum='".db_escape_string((string)$nettosum)."' ";
			$qtxt.= "and o.ordredate >= '".db_escape_string((string)$duplicate_window)."' and o.art='DO'";
			fwrite($log,__line__." Checking for potential duplicate by amount and customer: $qtxt\n");
			$duplicate_check = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			$duplicate_count = 0;
			while ($dup_r = db_fetch_array($duplicate_check)) {
				$duplicate_count++;
				fwrite($log,__line__." Found potential duplicate: Order $dup_r[ordrenr] (ID: $dup_r[id]) with same amount $dup_r[sum]\n");
			}
			if ($duplicate_count > 2) { // Allow max 2 orders with same amount per day per customer
				fwrite($log,__line__." POTENTIAL DUPLICATE WARNING: Customer $saldi_addr_id has $duplicate_count orders with amount $nettosum in last 24h\n");
				// Log warning but don't block - this could be legitimate
			}
		}
	}
	$qtxt="select saldi_id from shop_adresser where shop_id='".db_escape_string((string)$shop_addr_id)."' and afd='".db_escape_string((string)$afd)."'";
	fwrite($log,__line__." $qtxt\n");
	$r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$saldi_addr_id=$r['saldi_id'];
	fwrite($log,__line__." saldi_addr_id='$saldi_addr_id'\n");
	if (!$saldi_addr_id) {
		if ($saldi_kontonr) {
			$qtxt="select id, kontonr from adresser where kontonr='".db_escape_string((string)$saldi_kontonr)."'";
			fwrite($log,__line__." $qtxt\n");
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$saldi_addr_id=$r['id'];
				$kontonr=$r['kontonr']; #20190822
				fwrite($log,__line__." saldi_addr_id $saldi_addr_id\n");
				fwrite($log,__line__." kontonr $kontonr\n");
			} else $kontonr=$saldi_kontonr;
			fwrite($log,__line__." Kontonr $kontonr=$saldi_kontonr\n");
		} elseif ($tlf) {
			$qtxt="select id,kontonr from adresser where art = 'D' and "; 
			$qtxt.="tlf='".db_escape_string((string)$tlf)."'";
			fwrite($log,__line__." $qtxt\n");
			if ($r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$saldi_addr_id=$r['id']*1;
				$kontonr=$saldi_kontonr=$r['kontonr']; #20190822
				fwrite($log,__line__." saldi_addr_id $saldi_addr_id\n");
				fwrite($log,__line__." kontonr $saldi_kontonr\n");
			}
		}
		if ($saldi_addr_id && $shop_addr_id) {
			$qtxt="insert into shop_adresser(saldi_id,shop_id,afd)values('".db_escape_string((string)$saldi_addr_id)."','".db_escape_string((string)$shop_addr_id)."','".db_escape_string((string)$afd)."')";
			fwrite($log,__line__." $qtxt\n");
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);  
		} elseif (!$saldi_addr_id) { #if ($shop_addr_id) {
				$qtxt="select id from adresser where art = 'D' and kontonr='".db_escape_string((string)$num_tlf)."'";
				fwrite($log,__line__." $qtxt\n");
				if ($tlf && !$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					if (!$kontonr) $kontonr=$num_tlf;
					fwrite($log,__line__." kontonr $kontonr\n");
				} elseif (!$kontonr) { 
					$x=0;
					$qtxt="select kontonr from adresser where art = 'D' order by kontonr";
					fwrite($log,__line__." $qtxt\n");
					$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while($r=db_fetch_array($q)) {
						$ktonr[$x]=$r['kontonr'];
#						fwrite($log,__line__." kontonr $kontonr\n");
						$x++;
					}
					$kontonr=1000;
					while(in_array($kontonr,$ktonr)) $kontonr++;
				}
				fwrite($log,__line__." ". date("H:i:s") ." kontonr $kontonr\n");
				$qtxt = "insert into adresser";
				$qtxt.= "(kontonr,firmanavn,addr1,addr2,";
				$qtxt.= "postnr,bynavn,land,cvrnr,ean,email,tlf,";
				$qtxt.= "gruppe,art,betalingsbet,betalingsdage,kontakt,";
				$qtxt.= "lev_firmanavn,lev_addr1,lev_addr2,";
				$qtxt.= "lev_postnr,lev_bynavn,lev_land,";
				$qtxt.= "lev_kontakt,lev_tlf,lev_email,lukket)";
				$qtxt.= " values ";
				$qtxt.="('".db_escape_string((string)$kontonr)."','".db_escape_string($firmanavn)."','".db_escape_string($addr1)."','".db_escape_string($addr2)."',";
				$qtxt.="'".db_escape_string($postnr)."','".db_escape_string($bynavn)."','".db_escape_string($land)."',";
				$qtxt.="'".db_escape_string($cvrnr)."','".db_escape_string($ean)."','".db_escape_string($email)."','".db_escape_string($tlf)."',";
				$qtxt.="'".db_escape_string((string)$gruppe)."','D','".db_escape_string((string)$betalingsbet)."','".db_escape_string((string)$betalingsdage)."','".db_escape_string($kontakt)."',";
				$qtxt.="'".db_escape_string($lev_firmanavn)."','".db_escape_string($lev_addr1)."','".db_escape_string($lev_addr2)."',";
				$qtxt.="'".db_escape_string($lev_postnr)."','".db_escape_string($lev_bynavn)."','".db_escape_string($lev_land)."',";
				$qtxt.="'".db_escape_string($lev_kontakt)."','".db_escape_string($lev_tlf)."','".db_escape_string($lev_email)."','')";
				fwrite($log,__line__." $qtxt\n");
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select id from adresser where kontonr='".db_escape_string((string)$kontonr)."' and art = 'D'",__FILE__ . " linje " . __LINE__));
				$saldi_addr_id=$r['id'];
			} 
			if ($shop_addr_id) {
				fwrite($log,__line__." insert into shop_adresser(saldi_id,shop_id)values('$saldi_addr_id','$shop_addr_id')\n");
				db_modify("insert into shop_adresser(saldi_id,shop_id,afd)values('".db_escape_string((string)$saldi_addr_id)."','".db_escape_string((string)$shop_addr_id)."','".db_escape_string((string)$afd)."')",__FILE__ . " linje " . __LINE__);
			}
	} else {
		fwrite($log,__line__." select kontonr from adresser where id = '$saldi_addr_id'\n");
		$r=db_fetch_array(db_select("select kontonr from adresser where id = '".db_escape_string((string)$saldi_addr_id)."'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['kontonr'];
		fwrite($log,__line__." kontonr $kontonr\n");
	}
	list($cvrnr, $ean) = shop_order_cvr_ean_from_card($saldi_addr_id, $cvrnr, $ean); # SST-884
	fwrite($log,__line__." cvrnr '$cvrnr' ean '$ean'\n");
	$ordrenr = get_next_order_number("DO");
	$projektnr=0;
	$qtxt = "select box1 from grupper where art='DG' and kodenr = '".db_escape_string((string)$gruppe)."' ";
	$qtxt.= "order by fiscal_year desc limit 1";
	fwrite($log,__line__." $qtxt\n");
	$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
	$momsgruppe=str_replace('S','',$r['box1']);
	$qtxt = "select box2 from grupper where art='SM' and kodenr = '".db_escape_string((string)$momsgruppe)."' ";
	$qtxt.= "order by fiscal_year desc limit 1";
	$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
	$momssats=$r['box2']*1;
	if (!$valuta)$valuta='DKK';
	if ($valuta=='DKK') {
		$valutakurs=100;
	} else {
		$qtxt="select box2 from grupper where art='VK' and box1 = '".db_escape_string((string)$valuta)."'";
		fwrite($log,__line__." $qtxt\n");
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $valutakurs=$r['box2']; #20260223
		else $valutakurs=100;
		fwrite($log,__line__." Valutakurs $valutakurs\n");
		if (strpos($valutakurs,',')) $valutakurs = usdecimal($valutakurs);
		$valutakurs = (float)$valutakurs;
	}
	if (strtolower($betalingsbet) == 'kreditkort') $betalingsbet = 'Kreditkort';
	fwrite($log,__line__." afd $afd\n");
	$qtxt = "insert into ordrer ";
	$qtxt.= "(ordrenr,konto_id,kontonr,firmanavn,addr1,";
	$qtxt.= "addr2,postnr,bynavn,land,";
	$qtxt.= "kontakt,email,udskriv_til,art,projekt,momssats,betalingsbet,";
	$qtxt.= "betalingsdage,betalings_id,status,ordredate,valuta,valutakurs,afd,ref,hvem,";
	$qtxt.= "felt_1,felt_2,felt_3,felt_4,felt_5,kundeordnr,cvrnr,ean,sum,moms,"; 
	$qtxt.= "lev_navn,lev_addr1,lev_addr2,";
	$qtxt.= "lev_postnr,lev_bynavn,lev_kontakt,";
	$qtxt.= "tidspkt,phone,shop_status,shop_id,notes,sprog)";
	$qtxt.= " values ";
	$qtxt.= "('".db_escape_string((string)$ordrenr)."','".db_escape_string((string)$saldi_addr_id)."','".db_escape_string((string)$kontonr)."','".db_escape_string($firmanavn)."','".db_escape_string($addr1)."',";
	$qtxt.= "'".db_escape_string($addr2)."','".db_escape_string($postnr)."','".db_escape_string($bynavn)."',";
	$qtxt.= "'".db_escape_string($land)."','".db_escape_string($kontakt)."','".db_escape_string($email)."',";
	$initial_status = ($art == 'DK') ? '1' : '0';
	$qtxt.= "'".db_escape_string((string)$udskriv_til)."','".db_escape_string((string)$art)."','".db_escape_string((string)$projektnr)."','".db_escape_string((string)$momssats)."','".db_escape_string((string)$betalingsbet)."','".db_escape_string((string)$betalingsdage)."','".db_escape_string((string)$betalings_id)."','".db_escape_string((string)$initial_status)."',";
	$qtxt.= "'".db_escape_string((string)$ordredate)."','".db_escape_string((string)$valuta)."','".db_escape_string((string)$valutakurs)."','".db_escape_string((string)$afd)."','".db_escape_string((string)$ref)."','','".db_escape_string((string)$ekstra1)."','".db_escape_string((string)$ekstra2)."','".db_escape_string((string)$ekstra3)."',";
	$qtxt.= "'".db_escape_string((string)$ekstra4)."','".db_escape_string((string)$ekstra5)."','".db_escape_string((string)$shop_fakturanr)."','".db_escape_string($cvrnr)."','".db_escape_string($ean)."','".db_escape_string((string)$nettosum)."','".db_escape_string((string)$momssum)."',";
	$qtxt.= "'".db_escape_string($lev_firmanavn)."','".db_escape_string($lev_addr1)."','".db_escape_string($lev_addr2)."',";
	$qtxt.= "'".db_escape_string($lev_postnr)."','".db_escape_string($lev_bynavn)."','".db_escape_string($lev_kontakt)."',";
	$qtxt.= "'".db_escape_string($tidspkt)."','".db_escape_string($tlf)."','".db_escape_string((string)$shop_status)."',";
	$qtxt.= "'".db_escape_string((string)$shopOrderId)."','".db_escape_string($notes)."','".db_escape_string((string)$sprog)."')";
	fwrite($log,__line__." $qtxt\n");
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="select id from ordrer where ordrenr='".db_escape_string((string)$ordrenr)."' and kontonr='".db_escape_string((string)$kontonr)."'";
	fwrite($log,__line__." $qtxt\n");
	$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
	$saldi_ordre_id=$r['id'];
	fwrite($log,__line__."saldi_ordre_id: $saldi_ordre_id\n");
	
	// Enhanced shop_ordrer insertion with duplicate prevention
	if ($saldi_ordre_id && $shopOrderId) {
		// Final check before inserting into shop_ordrer table
		$qtxt_check="select so.id from shop_ordrer so join ordrer o on o.id=so.saldi_id where so.shop_id='".db_escape_string((string)$shopOrderId)."' and o.art='".db_escape_string((string)$art)."'";
		$check_r=db_fetch_array(db_select($qtxt_check,__FILE__ . " linje " . __LINE__));
		if ($check_r['id']) {
			fwrite($log,__line__." ERROR: Race condition detected - shop_id $shopOrderId (art: $art) already exists in shop_ordrer\n");
			// Clean up the order we just created since it's a duplicate
			db_modify("delete from ordrer where id='".db_escape_string((string)$saldi_ordre_id)."'",__FILE__ . " linje " . __LINE__);
			fclose ($log);
			return "Error: Duplicate shop order detected during insertion";
		}
		
		$qtxt="insert into shop_ordrer(saldi_id,shop_id)values('".db_escape_string((string)$saldi_ordre_id)."','".db_escape_string((string)$shopOrderId)."')";
		fwrite($log,__line__." $qtxt\n");
		$insert_result = db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		
		if (!$insert_result) {
			fwrite($log,__line__." ERROR: Failed to insert into shop_ordrer table\n");
			// Clean up the order we just created
			db_modify("delete from ordrer where id='".db_escape_string((string)$saldi_ordre_id)."'",__FILE__ . " linje " . __LINE__);
			fclose ($log);
			return "Error: Failed to create shop order mapping";
		}
		
		fwrite($log,__line__." Successfully created order mapping: saldi_id=$saldi_ordre_id, shop_id=$shopOrderId\n");
	}  
	fclose ($log);
	return $saldi_ordre_id;
}

function insert_shop_orderline($brugernavn,$ordre_id,$shop_vare_id,$shop_varenr,$antal,$beskrivelse,$pris,$momsfri,$rabat,$lager,$stregkode,$shop_variant,$varegruppe,$discountType) {

	global $db,$db_skriv_id;
	global $brugernavn;
	global $momssats;
	global $webservice;
	global $regnaar;
	
	// Route this customer's shop orders to the replacement warehouse.
	if ($db === 'pos_50') {
		$lager = 4;
	}

	$varenr = NULL;
	$linje_id = $ordresum = 0;
	$shop_varenr = trim($shop_varenr);
	// SST-904: the shop's identifiers as SQL values, decoded once and escaped; the raw values stay for the log and the order line
	$shopVarenrSql = db_escape_string((string)restApiShopText($shop_varenr));
	$shopVareIdSql = db_escape_string((string)restApiShopText($shop_vare_id));
	$shopVariantSql = db_escape_string((string)restApiShopText($shop_variant));
	$stregkodeSql = db_escape_string((string)restApiShopText($stregkode));

	list($master,$db_skriv_id)=explode('_',$db);
	$lager*=1;
	$log=fopen("../temp/$db/rest_api.log","a");
	fwrite($log,__line__." ".date("Y-m-d H:i:s")."\n");
	
	fwrite($log,__line__." insert_shop_orderline($ordre_id,$shop_vare_id,$shop_varenr,$antal,$beskrivelse,$pris,$momsfri,$rabat,$lager,$stregkode,$shop_variant,$discountType)\n");
	if ($ordre_id && is_numeric($ordre_id)) {
		$ordre_id = (int)$ordre_id;
		$qtxt="select status,momssats,valutakurs,art from ordrer where id='$ordre_id'";
		fwrite($log,__line__." ".$qtxt."\n");
		$r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$momssats=$r['momssats'];
		$ordre_valutakurs=$r['valutakurs']*1;
		if (!$ordre_valutakurs) $ordre_valutakurs=100;
		$ordre_art=$r['art'];
		fwrite($log,__line__." Momssats $momssats\n");
		if ($r['status'] > 2) {
			fwrite($log,__line__." Order ID $ordre_id allready invoiced\n");
			fclose ($log);
			return "Order ID $ordre_id allready invoiced";
			exit;
		}
	} else {
		fwrite($log,__line__." Invalid order ID $ordre_id\n");
		fclose ($log);
		return "Invalid order ID $ordre_id";
		exit;
	}
	fwrite($log,__line__." $ordre_id status $r[status]\n");
	if ($shop_variant) {
		$qtxt="select saldi_id, saldi_variant from shop_varer where shop_variant = '$shopVariantSql'"; #20220615
		fwrite($log,__line__." $qtxt\n");
		$r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$vare_id=$r['saldi_id'];
		$saldi_variant=$r['saldi_variant'];
	} elseif ($shop_varenr) {
		$qtxt="select id from varer where UPPER(varenr)=UPPER('$shopVarenrSql') or UPPER(varenr_alias)=UPPER('$shopVarenrSql') or UPPER(stregkode)=UPPER('$shopVarenrSql')";
		fwrite($log,__line__." $qtxt\n");
		$r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$vare_id=$r['id'];
	} elseif ($shop_vare_id) {
		$qtxt="select saldi_id from shop_varer where shop_id='$shopVareIdSql'";
		fwrite($log,__line__." $qtxt\n");
		$r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$vare_id=$r['saldi_id'];
	} elseif ($beskrivelse) {
		$vare_id='0';
		$varenr='';
	} else {
		fwrite($log,__line__." missing Item ID and Item number\n");
		fclose($log);
		return "missing Item ID and Item number";
		exit;
	}
	fwrite($log,__line__." Vare ID $vare_id\n");
	fwrite($log,__line__." Stregkode $stregkode\n");
	include(__DIR__ . "/../includes/ordrefunc.php");
	if ($vare_id) {
		$vare_id = (int)$vare_id;
		$qtxt="select varenr,samlevare from varer where id='$vare_id'";
		fwrite($log,__line__." $qtxt\n");
		$r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$samlevare=$r['samlevare'];
		if (!$varenr) $varenr=$r['varenr'];
		fwrite($log,__line__." Vare_id $vare_id - Samlevare $samlevare - Varenr $varenr\n");
		if ($saldi_variant) {
			$qtxt="select variant_stregkode FROM variant_varer where id='" . (int)$saldi_variant . "'";
			fwrite($log,__line__." $qtxt\n");
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$varenr=$r['variant_stregkode'];
				fwrite($log,__line__." Varenr set to $varenr\n");
			}
		}
		if ($stregkode) {
			$qtxt="select id FROM variant_varer where variant_stregkode='$stregkodeSql'";
			fwrite($log,__line__." $qtxt\n");
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$varenr=$stregkode;
				fwrite($log,__line__." Varenr set to $varenr\n");
			}
		}
	} else {
		if ($stregkode) {
			$qtxt="select id,samlevare from varer where stregkode='$stregkodeSql'";
			fwrite($log,__line__." $qtxt\n");
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$vare_id=$r['id'];
				$samlevare=$r['samlevare'];
#				$shop_variant=0;
				fwrite($log,__line__." Vare_id $vare_id - Samlevare $samlevare\n");
			}
		}
		if (!$vare_id && $stregkode) {
			$qtxt="select id,vare_id from variant_varer where variant_stregkode='$stregkodeSql'";
			fwrite($log,__line__." $qtxt\n");
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$vare_id=$r['vare_id'];
				$varenr=$stregkode;
				$samlevare=0;
				$saldi_variant=$r['id'];
				fwrite($log,__line__." Vare_id $vare_id - Shop variant $shop_variant - Saldi variant $saldi_variant\n");
			}
		}
		if (!$vare_id && ($shop_varenr || $shop_vare_id)) {
			if ($shop_varenr) $qtxt="select id,samlevare from varer where UPPER(varenr)=UPPER('$shopVarenrSql') or UPPER(varenr_alias)=UPPER('$shopVarenrSql') or UPPER(stregkode)=UPPER('$shopVarenrSql')";
			else $qtxt="select id,samlevare from varer where varenr='$shopVareIdSql' or varenr_alias='$shopVareIdSql' or stregkode='$shopVareIdSql'";
			fwrite($log,__line__." $qtxt\n");
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$vare_id=$r['id'];
			$samlevare=$r['samlevare'];
			fwrite($log,__line__." Vare_id $vare_id - Samlevare $samlevare\n");
		}	
/*
		if (!$vare_id) {
			$qtxt="select id,samlevare from varer where beskrivelse='$beskrivelse'";
			fwrite($log,__line__." $qtxt\n");
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$vare_id=$r['id'];
			$samlevare=$r['samlevare'];
		fwrite($log,__line__." Vare_id $vare_id - Samlevare $samlevare\n");
		}	
*/
		fwrite($log,__line__." Vare_id $vare_id - Shop_vare_id $shop_vare_id - Shop_varenr $shop_varenr - Beskr. $beskrivelse\n");
		if (!$vare_id && ($shop_vare_id || $shop_varenr) && $beskrivelse) {
			fwrite($log,__line__." Vare $varenr eksisterer ikke\n");
/* #20220615
			if (!$varegruppe) $varegruppe=1;
			($shop_varenr)?$varenr=$shop_varenr:$varenr=$shop_vare_id;
			if ($shop_kostpris) $kostpris=$shop_kostpris;
			elseif ($shop_dg) $kostpris=$pris-$pris/100*$shop_dg;
			else $kostpris=0;
			$qtxt="insert into varer(varenr,beskrivelse,salgspris,kostpris,gruppe)values('$varenr','".db_escape_string($beskrivelse)." (INDSAT FRA SHOP)','$pris','$kostpris','$varegruppe')";
			fwrite($log,__line__." $qtxt\n");
			$qtxt=chk4utf8($qtxt);
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="select id from varer where varenr='$varenr'";
			fwrite($log,__line__." $qtxt\n");
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$vare_id=$r['id'];
			$samlevare='';
			$smlv='on';
*/
		} elseif ($vare_id) fwrite($log,__line__." Vare $varenr eksisterer\n");
		if ($vare_id && $shop_vare_id) {
			if (!$saldi_variant) $saldi_variant=0; 	
			if (!$shop_variant) $shop_variant=0; #20190104
			$qtxt="insert into shop_varer(saldi_id,shop_id,saldi_variant,shop_variant)values('" . (int)$vare_id . "','$shopVareIdSql','" . (int)$saldi_variant . "','" . db_escape_string((string)restApiShopText($shop_variant)) . "')";
			fwrite($log,__line__." $qtxt\n");
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);  
		}
	}
	if (($vare_id)) {
		$qtxt="update varer set publiceret='on' where id = '" . (int)$vare_id . "'";
		fwrite($log,__line__." $qtxt\n");
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$posnr=0;
	$qtxt="select max(posnr) as posnr from ordrelinjer where ordre_id='$ordre_id'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$posnr=$r['posnr'];
	}
	$posnr+=100;
	fwrite($log,__line__." Samlevare = $samlevare\n");
	if ($samlevare && $samlevare == 'on') {
		fwrite($log,__line__." Samlevare = $samlevare\n");
		fwrite($log,__line__." opret_saet($ordre_id,$vare_id,\$pris*(1+$momssats/100),$momssats,$antal,on,$lager)\n");
		// 20260920 CDX/MJ SST-794 Use the order's own momssats instead of a hardcoded 25%. The rate
		//             is already loaded from ordrer at :459 and is 0 for an export customer, so the
		//             old code added 25% to a price that never carried VAT and then told
		//             opret_saet() to treat it as VAT-inclusive at 25%. Passing the real rate keeps
		//             a 25% order at exactly the same net price and leaves a 0% order untouched.
		$saet_momssats = $momssats * 1;
		$saet_pris = $pris * (1 + $saet_momssats / 100);
		opret_saet($ordre_id,$vare_id,$saet_pris,$saet_momssats,$antal,'on',$lager);
	} elseif($vare_id) {
		fwrite ($log,__line__." Antal: $antal\n");
		fwrite ($log,__line__." Beskrivelse: $beskrivelse\n");
		fwrite ($log,__line__." Lager: $lager\n");
		fwrite ($log,__line__." Vnr: $varenr\n");
		
		// 20260225 PBLM Convert shop price from order currency to DKK before opret_ordrelinje
		// opret_ordrelinje will convert it back from DKK to order currency using valutakurs
		$shop_pris = $pris;
		if ($ordre_valutakurs && $ordre_valutakurs != 100) {
			$pris = $pris * $ordre_valutakurs / 100;
			fwrite($log,__line__." Converted price from order currency to DKK: $shop_pris -> $pris (valutakurs=$ordre_valutakurs)\n");
		}
		
		$line_art = $ordre_art ? $ordre_art : 'DO';
		fwrite($log,__line__." opret_ordrelinje($ordre_id,$vare_id,".db_escape_string(chk4utf8($varenr)).",$antal,".db_escape_string(chk4utf8($beskrivelse)).",$pris,$rabat,'100','$line_art',$momsfri,$posnr,'0','','',$discountType,'0','','','',$lager,".__line__.")\n");
		$lineSum = opret_ordrelinje($ordre_id,$vare_id,db_escape_string(chk4utf8($varenr)),$antal,db_escape_string(chk4utf8($beskrivelse)),$pris,$rabat,'100',$line_art,$momsfri,$posnr,'0','','',$discountType,'0','','','',$lager,__LINE__);
		
		fwrite($log,__line__." LineSum =  $lineSum\n");
		$qtxt = "select max(id) as id from ordrelinjer where ordre_id = '$ordre_id' and vare_id = '" . (int)$vare_id . "'"; 
		fwrite($log,__line__." $qtxt\n");
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $linje_id = $r['id'];
		else $linje_id = 0;
	}else{
		$qtxt="insert into ordrelinjer(ordre_id,beskrivelse,posnr,vare_id,antal,pris,rabat,lager,momsfri)";
		$qtxt.=" values ";
		$qtxt.="('$ordre_id','','" . (int)$posnr . "','0','0','0','0','0','')";
		fwrite($log,__line__." $qtxt\n");
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$posnr+=100;
		$qtxt="insert into ordrelinjer(ordre_id,beskrivelse,posnr,vare_id,antal,pris,rabat,lager,momsfri)";
		$qtxt.=" values ";
		$qtxt.="('$ordre_id','".db_escape_string(restApiShopText($beskrivelse))."','" . (int)$posnr . "','0','0','0','0','0','')";
		fwrite($log,__line__." $qtxt\n");
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "select max(id) as id 	from ordrelinjer where ordre_id = '$ordre_id' and vare_id = '0'"; 
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $linje_id = $r['id'];
		else $linje_id = 0;
	}
	$ordresum+=$pris*$antal;
	fwrite($log,__line__." Linje ID $linje_id oprettet\n");
	fclose ($log);
	return $linje_id;
} # endfunc - insert_shop_orderline

function fakturer_ordre($saldi_id,$udskriv_til,$pos_betaling,$fakturadate = null) {
	global $db,$db_skriv_id;
	global $baseCurrency,$brugernavn;
	global $webservice;
	global $regnaar;
	global $db_modify_fejl; #20260729 SZ (SD-595)
	#return "$nettosum,$momssum";
	
	include("../includes/ordrefunc.php");
	
	$log=fopen("../temp/$db/rest_api.log","a");
	fwrite($log,__line__." ".date("Y-m-d H:i:s")."\n");

	$qtxt = "select var_value from settings where var_name = 'baseCurrency'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$baseCurrency = $r['var_value'];
	} else $baseCurrency = 'DKK';
	if (!$baseCurrency) $baseCurrency = 'DKK';

	fwrite($log,__line__." base currency: $baseCurrency \n");

	$qtxt="select * from ordrelinjer where ordre_id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$linjesum=0;
	while ($r=db_fetch_array($q)) {
		$linjesum+=$r['antal']*$r['pris']-($r['antal']*$r['pris']*$r['rabat']/100);
		fwrite($log,__line__." $linjesum+=$r[antal]*$r[pris]-($r[antal]*$r[pris]*$r[rabat]/100)\n");
	}
	$qtxt="select betalingsbet,tidspkt,sum,moms,felt_1,felt_2 from ordrer where id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$ordresum=$r['sum'];
	$ordremoms=$r['moms'];
	$betalingsbet=$r['betalingsbet'];
	$betalingstype=$r['felt_1'];
	$betalingsum=$r['felt_2'];
	$tidspkt=$r['tidspkt'];
#	$r=db_fetch_array(db_select("select * from ordrer where id = '$saldi_id'",__FILE__ . " linje " . __LINE__));
#	$betalt=$r['sum']+$r['moms'];
#	$korttype=$r['felt_1'];
	$varesum=$varemoms=0;
	$qtxt="select antal,pris,rabat,momssats from ordrelinjer where ordre_id='$saldi_id' and vare_id >= 0";
	fwrite($log,__line__." $qtxt\n");
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$linjepris=$r['antal']*($r['pris']-$r['pris']*$r['rabat']/100);
		fwrite($log,__line__." Svar : $linjepris=$r[antal]*($r[pris]-$r[pris]*$r[rabat]/100)\n");
		$linjemoms=$linjepris*$r['momssats']/100;
		$varesum+=afrund($linjepris,3);
		$varemoms+=afrund($linjemoms,3);
		fwrite($log,__line__." $varesum -> $varemoms\n");
	}
	fwrite($log,__line__." abs($ordresum-$varesum)>0.01 || abs($ordremoms-$varemoms)>0.01)\n");
	if (abs($ordresum-$varesum)>0.01 || abs($ordremoms-$varemoms)>0.01) {
		$svar='Error in amount ('.$ordresum.'+'.$ordremoms.') vs. item amount ('.$varesum.'+'.$varemoms.')';
		fwrite($log,__line__." Svar : $svar\n");
		fclose($log);
		return($svar);
		exit;
	}
	transaktion('begin');
	if ($fakturadate) {
		$qtxt="update ordrer set fakturadate='$fakturadate' where id='$saldi_id'";
		fwrite($log,__line__." $qtxt\n");
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
	} else {
		$qtxt="update ordrer set fakturadate=ordredate, levdate=ordredate where id='$saldi_id'";
		fwrite($log,__line__." $qtxt\n");
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
	}
	transaktion('commit');
	if ($db_modify_fejl) { #20260729 SZ a write failed while setting fakturadate; do not report success (SD-595)
		fwrite($log,__line__." Svar : Database write failed while updating fakturadato for order $saldi_id\n");
		fclose($log);
		return "Database write failed while updating fakturadato for order $saldi_id";
	}
	$qtxt="select fakturadate from ordrer where id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$fakturadate = $r['fakturadate'];
	if (!$fakturadate) {
		return "fakturadate not set";
		exit;
	}
	transaktion('begin');
	$qtxt="update ordrelinjer set leveres = antal where ordre_id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
	$svar=levering($saldi_id,'on',NULL,'on');
	fwrite($log,__line__." Betalingsbet: $betalingsbet\n");
	if ($betalingsbet!='Forud' && $betalingsbet!='Lb. Md' && $betalingsbet!='Netto') {
		$betalingsdiff=abs($ordresum+$ordremoms-$betalingsum);
		if ($pos_betaling && $betalingsdiff >= 0.01) {
			fwrite($log,__line__." Ordresum : $ordresum\n");
			fwrite($log,__line__." Ordremoms : $ordremoms\n");
			fwrite($log,__line__." Betalingssum : $betalingsum\n");
			$svar='Error in amount ('.$ordresum.'+'.$ordremoms.') vs. paid amount ('.$betalingsum.') : diff '.$betalingsdiff;
		}
	}
	if ($svar=='OK') {
/*
		if ($pos_betaling) { #20190123
			$qtxt="insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs)values('$saldi_id','$betalingstype','$betalingsum','DKK','100')";
			$qtxt=chk4utf8($qtxt);
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			fwrite($log,__line__." Ordre ID $saldi_id faktureret ($svar)\n");
		}
*/		
		$svar=bogfor($saldi_id,'on');
		if ($tidspkt) {	
			$qtxt="update ordrer set tidspkt='$tidspkt' where id='$saldi_id'";
			fwrite($log,__line__." $qtxt\n");
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
		}
	} 
	if ($svar != 'OK') {
		fwrite($log,__line__." Svar : $svar\n");
		fclose($log);
		transaktion('rollback'); #20260805 CL/SZ close the outer transaction on failure too, not just success - otherwise db_transaktion_depth never returns to 0 and the next invoice call in this request won't reset $db_modify_fejl (SD-595, CodeRabbit)
		return($svar);
	}
	transaktion ('commit');
	if ($db_modify_fejl) { #20260729 SZ a write failed inside the posting transaction; Postgres rolls it back on commit but $svar was reported OK, so surface the failure instead of a phantom success id (SD-595)
		fclose($log);
		return "Database write failed while posting order $saldi_id";
	}
	// Send invoice email to customer if enabled - after the commit/failure check
	// so a failed posting write never triggers a customer email (SD-595, CodeRabbit) #20260805 CL/SZ
	try {
		$email_result = send_api_invoice_email($saldi_id);
		fwrite($log,__line__." Invoice email result: $email_result\n");
	} catch (Exception $e) {
		fwrite($log,__line__." Invoice email error: " . $e->getMessage() . "\n");
	} catch (Error $e) {
		fwrite($log,__line__." Invoice email fatal error: " . $e->getMessage() . "\n");
	}
	fclose ($log);

	return($saldi_id);
}

function create_credit_note($shop_ordre_id) {
	global $db, $db_skriv_id, $brugernavn, $webservice, $regnaar;

	$log = fopen("../temp/$db/rest_api.log", "a");
	fwrite($log, __line__ . " create_credit_note($shop_ordre_id)\n");

	if (!$shop_ordre_id || !is_numeric($shop_ordre_id)) {
		fwrite($log, __line__ . " Invalid shop_ordre_id ($shop_ordre_id)\n");
		fclose($log);
		return "Invalid shop_ordre_id ($shop_ordre_id)";
	}

	// 1. Look up original Saldi order via shop_ordrer
	$r = db_fetch_array(db_select("SELECT saldi_id FROM shop_ordrer WHERE shop_id='$shop_ordre_id'", __FILE__ . " linje " . __LINE__));
	if (!$r['saldi_id']) {
		fwrite($log, __line__ . " Original order not found in Saldi for shop_id $shop_ordre_id\n");
		fclose($log);
		return "Original order not found for shop_id $shop_ordre_id";
	}
	$original_saldi_id = (int)$r['saldi_id'];
	fwrite($log, __line__ . " Found original Saldi order id: $original_saldi_id\n");

	// 2. Load original order details
	$orig = db_fetch_array(db_select("SELECT * FROM ordrer WHERE id='$original_saldi_id'", __FILE__ . " linje " . __LINE__));
	if (!$orig['id']) {
		fwrite($log, __line__ . " Original order id $original_saldi_id not found in ordrer table\n");
		fclose($log);
		return "Original order $original_saldi_id not found";
	}
	if ((int)$orig['status'] < 3) {
		fwrite($log, __line__ . " Original order $original_saldi_id is not yet invoiced (status=$orig[status])\n");
		fclose($log);
		return "Original order $original_saldi_id is not yet invoiced";
	}
	if ($orig['art'] != 'DO') {
		fwrite($log, __line__ . " Original order $original_saldi_id is not a DO order (art=$orig[art])\n");
		fclose($log);
		return "Original order $original_saldi_id is not a DO order (art=$orig[art])";
	}

	// 3. Duplicate check - only one credit note per original order
	$dup = db_fetch_array(db_select("SELECT id FROM ordrer WHERE kred_ord_id='$original_saldi_id' AND art='DK'", __FILE__ . " linje " . __LINE__));
	if ($dup['id']) {
		fwrite($log, __line__ . " Credit note already exists (id=$dup[id]) for order $original_saldi_id\n");
		fclose($log);
		return "Credit note already exists for order $original_saldi_id";
	}

	// 4. Generate next order number for DK
	$new_ordrenr = get_next_order_number('DK');
	fwrite($log, __line__ . " New credit note ordrenr: $new_ordrenr\n");

	$today = date('Y-m-d');
	$credit_sum  = (float)$orig['sum']  * -1;
	$credit_moms = (float)$orig['moms'] * -1;

	// 5. Insert new DK credit note order copying customer/address from original
	$qtxt  = "INSERT INTO ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land,";
	$qtxt .= " kontakt, email, art, kred_ord_id, projekt, momssats, betalingsbet, betalingsdage, betalings_id,";
	$qtxt .= " status, ordredate, fakturadate, valuta, valutakurs, afd, ref, hvem,";
	$qtxt .= " felt_1, felt_2, felt_3, felt_4, felt_5, kundeordnr, cvrnr, ean, sum, moms,";
	$qtxt .= " lev_navn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt,";
	$qtxt .= " tidspkt, phone, shop_status, shop_id, notes, sprog)";
	$qtxt .= " VALUES ";
	$qtxt .= "('$new_ordrenr','$orig[konto_id]','$orig[kontonr]','".db_escape_string($orig['firmanavn'])."','".db_escape_string($orig['addr1'])."','".db_escape_string($orig['addr2'])."',";
	$qtxt .= "'".db_escape_string($orig['postnr'])."','".db_escape_string($orig['bynavn'])."','".db_escape_string($orig['land'])."',";
	$qtxt .= "'".db_escape_string($orig['kontakt'])."','".db_escape_string($orig['email'])."',";
	$qtxt .= "'DK','$original_saldi_id','$orig[projekt]','$orig[momssats]','$orig[betalingsbet]','$orig[betalingsdage]','',";
	$qtxt .= "'0','$today','$today','$orig[valuta]','$orig[valutakurs]','$orig[afd]','$orig[ref]','',";
	$qtxt .= "'','','','','','$orig[kundeordnr]','".db_escape_string($orig['cvrnr'])."','".db_escape_string($orig['ean'])."','$credit_sum','$credit_moms',";
	$qtxt .= "'".db_escape_string($orig['lev_navn'])."','".db_escape_string($orig['lev_addr1'])."','".db_escape_string($orig['lev_addr2'])."',";
	$qtxt .= "'".db_escape_string($orig['lev_postnr'])."','".db_escape_string($orig['lev_bynavn'])."','".db_escape_string($orig['lev_kontakt'])."',";
	$qtxt .= "'$orig[tidspkt]','".db_escape_string($orig['phone'])."','','0','".db_escape_string($orig['notes'])."','$orig[sprog]')";
	fwrite($log, __line__ . " $qtxt\n");
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);

	// 6. Get new credit note id
	$r2 = db_fetch_array(db_select("SELECT id FROM ordrer WHERE ordrenr='$new_ordrenr'", __FILE__ . " linje " . __LINE__));
	$credit_saldi_id = (int)$r2['id'];
	if (!$credit_saldi_id) {
		fwrite($log, __line__ . " Failed to retrieve credit note id after insert\n");
		fclose($log);
		return "Failed to create credit note order";
	}
	fwrite($log, __line__ . " Credit note order id: $credit_saldi_id\n");

	// 7. Copy order lines with negated antal
	$q = db_select("SELECT * FROM ordrelinjer WHERE ordre_id='$original_saldi_id'", __FILE__ . " linje " . __LINE__);
	$line_count = 0;
	while ($line = db_fetch_array($q)) {
		$neg_antal = (float)$line['antal'] * -1;
		$orig_line_id = (int)$line['id'];
		$lqtxt  = "INSERT INTO ordrelinjer (ordre_id, varenr, beskrivelse, posnr, vare_id, antal, pris, rabat, momssats, lager, momsfri, varegruppe, kred_linje_id)";
		$lqtxt .= " VALUES ";
		$lqtxt .= "('$credit_saldi_id','".db_escape_string($line['varenr'])."','".db_escape_string($line['beskrivelse'])."',";
		$lqtxt .= "'$line[posnr]','$line[vare_id]','$neg_antal','$line[pris]','$line[rabat]','$line[momssats]',";
		$lqtxt .= "'$line[lager]','$line[momsfri]','$line[varegruppe]','$orig_line_id')";
		fwrite($log, __line__ . " $lqtxt\n");
		db_modify($lqtxt, __FILE__ . " linje " . __LINE__);
		$line_count++;
	}
	fwrite($log, __line__ . " Copied $line_count order lines\n");

	// 8. Invoice the credit note
	fclose($log);
	$result = fakturer_ordre($credit_saldi_id, '', '', null);

	$log = fopen("../temp/$db/rest_api.log", "a");
	fwrite($log, __line__ . " fakturer_ordre result: $result\n");
	fclose($log);

	// fakturer_ordre() returns $credit_saldi_id back verbatim on success, or an
	// error string on failure - propagate the failure instead of reporting the
	// credit note as posted when it is still unposted (SD-595, CodeRabbit) #20260805 CL/SZ
	if ($result != $credit_saldi_id) {
		return "Credit note $credit_saldi_id created but invoicing failed: $result";
	}

	return $credit_saldi_id;
}

function get_sold_labels() {
    $data = array();
    $result = db_select("SELECT 
    SUBSTR(ol.varenr, 6) AS kontonr, 
    ol.varenr, 
    ol.beskrivelse, 
    ol.pris, 
    ol.antal, 
    o.fakturadate AS date, 
    o.tidspkt, 
    a.firmanavn, 
    a.email, 
    v.kostpris AS sats
FROM 
    ordrelinjer ol
LEFT JOIN 
    ordrer o ON o.id = ol.ordre_id
LEFT JOIN 
    adresser a ON a.kontonr = SUBSTR(ol.varenr, 6)
LEFT JOIN 
    varer v ON v.varenr = ol.varenr
WHERE 
    (ol.varenr LIKE 'kb%' OR ol.varenr LIKE 'kn%')
    AND o.status >= 3", __FILE__ . " linje " . __LINE__);

    while ($r = db_fetch_array($result)) {
        $data[] = $r;
    }

    return $data;
}

function get_all_labels() {
    $data = array();
    $result = db_select("SELECT 
    ml.description, 
    ml.price, 
    ml.sold, 
    ml.hidden,
	ml.barcode,
	ml.lastprint,
    a.firmanavn, 
	a.kontonr,
    a.email
FROM mylabel ml
LEFT JOIN adresser a ON a.id = ml.account_id
", __FILE__ . " linje " . __LINE__);

    while ($r = db_fetch_array($result)) {
        $data[] = $r;
    }

    return $data;
}

function access_check(){
	global $sqhost;
	global $squser;
	global $sqpass;
	global $db,$db_skriv_id;
	global $brugernavn;
	global $webservice;
	global $regnaar;

	if (isset($_GET['db']) && is_string($_GET['db'])) {
		$db=$_GET['db'];
		if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,24}\z/',$db)) {
			return 'missing db';
		}
	} else {
		return 'missing db';
	}

	if (!file_exists("../temp") && !mkdir("../temp",0750) && !file_exists("../temp")) {
		return 'Unable to create temp directory';
	}
	if (!file_exists("../temp/$db") && !mkdir("../temp/$db",0750) && !file_exists("../temp/$db")) {
		return 'Unable to create temp directory';
	}
	$log=fopen("../temp/$db/rest_api.log","a");
	if (!$log) {
		return 'Unable to open log file';
	}
	(strpos($db,'_'))?list($master,$db_skriv_id)=explode('_',$db):$master=$db;
	fwrite($log,__line__." $master,$db_skriv_id\n");

	$qtxt="select id,lukket from regnskab where db='".db_escape_string($db)."'"; #20201223
	fwrite($log,__line__." $qtxt\n");
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['id']) {
		if ($r['lukket'] == 'on') {
			fwrite($log,__line__." Account $db closed\n");
			fclose($log);
			return( "Account $db closed");
		} 
	} else {
		fwrite($log,__line__." Non existing account $db\n");
		fclose($log);
		return( "Non existing account $db");
	}
	
	$ip=$_SERVER['REMOTE_ADDR'];
	fwrite($log,date("Y-m-d H:i:s")."\n");
	fwrite($log,__line__." ip: $ip, db $db\n");
	if ($ip == '138.201.85.87') {
		fwrite($log,__line__." ".date("H:i:s")."\n");
		sleep(15);
		fwrite($log,__line__." ".date("H:i:s")."\n");
	}
	$connection = db_connect ("$sqhost", "$squser", "$sqpass", "$db", __FILE__ . " linje " . __LINE__);
	if (!$connection) {
		fwrite($log,__line__." Unable to connect to $db\n");
		fclose($log);
		return( "Unable to connect to $db");
		exit;
	}
	if (isset($_GET['saldiuser'])) {
		$brugernavn=$_GET['saldiuser'];
		fwrite($log,__line__." saldibruger: $brugernavn\n");
	}	else {
		fwrite($log,__line__." Missing saldiuser\n");
		fclose($log);
		return 'Missing saldiuser';
	}
	if ($db != $master) {
		$year=date("Y"); #20190318 --->
		$month=date("m");
		$del1="(box1<='$month' and box2<='$year' and box3>='$month' and box4>='$year')";
		$del2="(box1<='$month' and box2<='$year' and box3<'$month' and box4>'$year')";
		$del3="(box1>'$month' and box2<'$year' and box3>='$month' and box4>='$year')";
		$qtxt="select kodenr from grupper where art='RA' and ($del1 or $del2 or $del3)"; #20190318
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$regnaar=$r['kodenr']*1;
		} elseif ($r=db_fetch_array(db_select("select max(kodenr) as kodenr from grupper where art='RA' and box5='on'",__FILE__ . " linje " . __LINE__))) {
			$regnaar=$r['kodenr']*1;
		}	else {
			fwrite($log,__line__." Missing year in ledger\n");
			fclose($log);
			return 'Missing ledger';
		} #<---
		$q=db_select("select * from grupper where art = 'API' and kodenr = '1'",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select * from grupper where art = 'API' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
		$api_key=trim($r['box1']);
		if (strpos($r['box2'],',')) $ip_list=explode(',',trim($r['box2']));
		else $ip_list[0]=trim($r['box2']);
		if (!$api_key || !hash_equals($api_key,(string) (isset($_GET['key']) ? $_GET['key'] : ''))) {
			$log=fopen("../temp/$db/rest_api.log","a");
			fwrite($log,__line__." Access denied (key)\n");
			return "Access denied (key)";
		} elseif (!in_array($ip,$ip_list) && !in_array('*',$ip_list)) {
			fwrite($log,__line__." Access denied (key) ($ip) != $r[box2]\n");
			return "Access denied (ip)";
		} else {
			fwrite($log,__line__." Access granted $ip\n");
			return 'OK';
		}
		fclose ($log);
		exit;
	} elseif ($ip != '91.235.100.32') return 'Wrong IP';
	return 'OK';
}
$possible_url = array("fetch_from_table,insert_shop_order,insert_shop_orderline");
$value = "An error has occurred";
if (isset($_GET['action'])){# && in_array($_GET['action'], $possible_url)){
	$action=trim($_GET['action']);
	$svar = access_check();
	if ($svar == 'OK') {#exit(json_encode($value));
		$log=fopen("../temp/$db/rest_api.log","a");
		fwrite($log,__line__." Action:".$_GET['action']."\n");
		if ($action=='fetch_from_table') {
			fclose ($log);
			// Read-only legacy queries are compiled before execution.
			$select = ifset($_GET, 'select', '');
			$from = ifset($_GET, 'from', '');
			$where = ifset($_GET, 'where', '');
			if (is_string($where)) {
				$where = str_replace('**', '%', $where);
			}
			$orderBy = ifset($_GET, 'order_by', '');
			$limit = ifset($_GET, 'limit', '');
			if ($select && $from) {
				$value = fetch_from_table($select, $from, $where, $orderBy, $limit);
			}
##############################################
		} elseif ($action=='get_sold_labels') {
			fclose ($log);
			$value = get_sold_labels();
##############################################
		} elseif ($action=='get_all_labels') {
			fclose ($log);
			$value = get_all_labels();
##############################################
		}	elseif ($action=='getNextAccountNo') {
			fclose ($log);
			include("restApiIncludes/getNextAccountNo.php");
			$value = getNextAccountNo('');
##############################################
		}	elseif ($action=='createDebitor') {
			fclose ($log);
			include("restApiIncludes/createDebitor.php");
			$value = createDebitor('');
##############################################
		}	elseif ($action=='deleteOrder' ) {
			include("restApiIncludes/deleteOrder.php");
			$orderId = if_isset($_GET['orderId']);
			fwrite($log,__line__." orderId $orderId\n");
			fclose ($log);
			if ($orderId) $value = deleteOrder($orderId);
			else $value = "missing orderId";
##############################################
		}	elseif ($action=='insert_shop_order') {
			$addr1         = strip_placeholder_value(ifset($_GET, 'addr1'));
			$addr2         = strip_placeholder_value(ifset($_GET, 'addr2'));
			$afd           = (int)ifset($_GET, 'afd');
			$betalings_id  = ifset($_GET, 'betalings_id');
			$betalingsbet  = ifset($_GET, 'betalingsbet');
			$betalingsdage = ifset($_GET, 'betalingsdage');
			$bynavn        = strip_placeholder_value(ifset($_GET, 'bynavn'));
			$cvr           = strip_placeholder_value(ifset($_GET, 'cvr'));
			$firmanavn     = strip_placeholder_value(ifset($_GET, 'firmanavn'));
			$land          = strip_placeholder_value(ifset($_GET, 'land'));
			$shop_addr_id  = ifset($_GET, 'shop_addr_id');
			$shopOrderId   = ifset($_GET, 'shop_ordre_id');
			if (!$shopOrderId) $shopOrderId = 0;
			$shop_fakturanr = ifset($_GET, 'shop_fakturanr');
			if (!$shop_fakturanr) $shop_fakturanr=$shopOrderId;
			$postnr         = strip_placeholder_value(ifset($_GET, 'postnr'));
			$ean            = (int)ifset($_GET, 'ean');
			if (!$ean) $ean = '';
			$institution    = strip_placeholder_value(ifset($_GET, 'institution'));
			$tlf            = ifset($_GET, 'tlf');
			$email          = ifset($_GET, 'email');
			$udskriv_til    = ifset($_GET, 'udskriv_til');
			$ref            = ifset($_GET, 'ref');
			$kontakt        = strip_placeholder_value(ifset($_GET, 'kontakt'));
			$lager          = ifset($_GET, 'lager');
			if (!$lager) $lager = 1;
			$lev_firmanavn  = strip_placeholder_value(ifset($_GET, 'lev_firmanavn'));
			$lev_addr1      = strip_placeholder_value(ifset($_GET, 'lev_addr1'));
			$lev_addr2      = strip_placeholder_value(ifset($_GET, 'lev_addr2'));
			$lev_postnr     = strip_placeholder_value(ifset($_GET, 'lev_postnr'));
			$lev_bynavn     = strip_placeholder_value(ifset($_GET, 'lev_bynavn'));
			$lev_land       = strip_placeholder_value(ifset($_GET, 'lev_land'));
			$lev_tlf        = strip_placeholder_value(ifset($_GET, 'lev_tlf'));
			$lev_email      = strip_placeholder_value(ifset($_GET, 'lev_email'));
			$lev_kontakt    = strip_placeholder_value(ifset($_GET, 'lev_kontakt'));
			$ordredate      = ifset($_GET, 'ordredate');
			$lev_date       = ifset($_GET, 'lev_date');
			$momssats       = ifset($_GET, 'momssats');
			$valuta         = ifset($_GET, 'valuta');
			$valutakurs     = ifset($_GET, 'valutakurs');
			$gruppe         = ifset($_GET, 'gruppe');
			$nettosum       = ifset($_GET, 'nettosum')*1;
			$momssum        = ifset($_GET, 'momssum')*1;
			$projekt        = ifset($_GET, 'projekt');
			$ekstra1        = ifset($_GET, 'ekstra1');
			$ekstra2        = ifset($_GET, 'ekstra2');
			$ekstra3        = ifset($_GET, 'ekstra3');
			$ekstra4        = ifset($_GET, 'ekstra4');
			$ekstra5        = ifset($_GET, 'ekstra5');
			$notes          = ifset($_GET, 'notes');
			$sprog          = ifset($_GET, 'sprog');
			$saldi_kontonr  = ifset($_GET, 'saldi_kontonr');
			$pos_betaling   = ifset($_GET, 'pos_betaling');
			$shop_status    = ifset($_GET, 'shop_status');
			$art            = ifset($_GET, 'art');
			if (!$art) $art = 'DO';

			$fil = fopen('../temp/addr1.php','w');
			fwrite($fil,"<?php $"."addr1='$addr1' ?>\n");
			fclose($fil);
			$log=fopen("../temp/$db/rest_api.log","a");
			fwrite($log,__line__." Saldi kontonr $saldi_kontonr -> ");
			$saldi_kontonr = str_replace('+45','',$saldi_kontonr);  //20240423
			$saldi_kontonr = str_replace('+','',$saldi_kontonr);
			$saldi_kontonr = str_replace(' ','',$saldi_kontonr);
			fwrite($log,__line__." Saldi kontonr $saldi_kontonr\n");
			fwrite($log,__line__." Afd $afd Ekstra5 $ekstra5\n");
			$params = "$brugernavn,$shopOrderId,$shop_addr_id,$saldi_kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$cvr,$ean,";
			$params.= "$institution,$tlf,$email,$udskriv_til";
			$params.= "$ref,$kontakt,$lev_firmanavn,$lev_addr1,$lev_addr2,$lev_postnr,$lev_bynavn,$lev_land,$lev_tlf,$lev_email,$lev_kontakt,";
			$params.= "$betalingsbet,$betalingsdage,$betalings_id,$ordredate,$lev_date,$momssats,$valuta,$valutakurs,$gruppe,$afd,$projekt,";
			$params.= "$ekstra1,$ekstra2,$ekstra3,$ekstra4,$ekstra5,$nettosum,$momssum,$lager,$pos_betaling,$shop_status,$notes,$sprog";
			fwrite($log,__line__." insert_shop_order($brugernavn,$shopOrderId,$shop_fakturanr,$shop_addr_id,$saldi_kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$cvr,$ean,$institution,$tlf,$email,$udskriv_til,$ref,$kontakt,$lev_firmanavn,$lev_addr1,$lev_addr2,$lev_postnr,$lev_bynavn,$lev_land,$lev_tlf,$lev_email,$lev_kontakt,$betalingsbet,$betalingsdage,$betalings_id,$ordredate,$lev_date,$momssats,$valuta,$valutakurs,$gruppe,$afd,$projekt,$ekstra1,$ekstra2,$ekstra3,$ekstra4,$ekstra5,$nettosum,$momssum,$lager,$shop_status,$notes,$sprog\n");
			fclose ($log);
			$value = insert_shop_order($brugernavn,$shopOrderId,$shop_fakturanr,$shop_addr_id,$saldi_kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$cvr,$ean,$institution,$tlf,$email,$udskriv_til,$ref,$kontakt,$lev_firmanavn,$lev_addr1,$lev_addr2,$lev_postnr,$lev_bynavn,$lev_land,$lev_tlf,$lev_email,$lev_kontakt,$betalingsbet,$betalingsdage,$betalings_id,$ordredate,$lev_date,$momssats,$valuta,$valutakurs,$gruppe,$afd,$projekt,$ekstra1,$ekstra2,$ekstra3,$ekstra4,$ekstra5,$nettosum,$momssum,$lager,$shop_status,$notes,$sprog,$art);
##############################################
		} elseif ($action=='insert_shop_orderline') {
			$log=fopen("../temp/$db/rest_api.log","a");
			$ordre_id= if_isset($_GET['saldi_ordre_id']);
			fwrite($log,__line__." ordre_id >$ordre_id<\n");
			$vare_id= if_isset($_GET['vare_id']);
			$varenr= if_isset($_GET['varenr']);
			fwrite($log,__line__." varenr >$varenr<\n");
			$stregkode= if_isset($_GET['stregkode']);
			$antal= if_isset($_GET['antal']);
			fwrite($log,__line__." antal >$antal<\n");
			$beskrivelse= if_isset($_GET['beskrivelse']);
			$pris= if_isset($_GET['pris']);
			$momsfri= if_isset($_GET['momsfri']);
			$rabat= if_isset($_GET['rabat']);
			$lager= if_isset($_GET['lager']);
			$variant= if_isset($_GET['variant']);
			$varegruppe= if_isset($_GET['varegruppe']);
			$discountType= if_isset($_GET['discountType']);
			$params="$brugernavn,$ordre_id,$vare_id,$varenr,$antal,$beskrivelse,$pris,$momsfri,$rabat,$lager,$stregkode,$variant,$varegruppe";
			fwrite($log,__line__." insert_shop_ordeline($params)\n");
			fclose ($log);
			$value = insert_shop_orderline($brugernavn,$ordre_id,$vare_id,$varenr,$antal,$beskrivelse,$pris,$momsfri,$rabat,$lager,$stregkode,$variant,$varegruppe,$discountType);
##############################################
		} elseif ($action=='fakturer_ordre') {
			$log=fopen("../temp/$db/rest_api.log","a");
			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http';
			$host = $_SERVER['HTTP_HOST'];
			$uri = $_SERVER['REQUEST_URI'];
			fwrite($log,__line__." protocol $protocol host $host uri $uri\n");
			$ordre_id= if_isset($_GET['saldi_ordre_id']);
			$pos_betaling= if_isset($_GET['pos_betaling']);
			$udskriv_til= if_isset($_GET['udskriv_til']);
			$fakturadate= if_isset($_GET['fakturadate']);
			fclose ($log);
			$value = fakturer_ordre($ordre_id,$udskriv_til,$pos_betaling,$fakturadate);
##############################################
		} elseif ($action == 'credit_shop_order') {
			$shop_ordre_id = (int)if_isset($_GET['shop_ordre_id']);
			fclose($log);
			$value = create_credit_note($shop_ordre_id);
##############################################
		} else {
			$value="Illegal action ($action)";
			fclose ($log);
		}
	} else $value=$svar;
} else $value= if_isset($_GET['action']);

/**
 * SST-904: a text value from the shop as it is stored: ISO-8859-1 becomes UTF-8 (valid UTF-8, plain
 * ASCII included, is kept) and HTML entities are decoded (&#039; -> ', &amp; -> &). Use it on each
 * value before db_escape_string(), never on finished SQL, so a decoded quote is escaped like any other.
 *
 * @param mixed $value The value as the shop sent it.
 * @return mixed The decoded string; null stays null.
 */
function restApiShopText($value) {
	if ($value === null) {
		return null;
	}
	$text = (string)$value;
	if (!mb_check_encoding($text, 'UTF-8')) {
		$text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
	}
	return html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
}

function chk4utf8 ($text) {
	$enc='IS0-8859';
	$fil=fopen("../temp/tekst.txt","a");
	fwrite ($fil,"$text\n");
	$text=' '.$text;
	$tmp="À,Á,Â,Ã,Ä,Å,Æ,Ç,È,É,Ê,Ë,Ì,Í,Î,Ï,Ð,Ñ,Ò,Ó,Ô,Õ,Ö,Ø,Ù,Ú,Û,Ü,Ý,Þ,ß,à,á,â,ã,ä,å,æ,ç,è,é,ê,ë,ì,í,î,ï,ð,ñ,ò,ó,ô,õ,ö,ø,ù,ú,û,ü,ý,þ,ÿ";
	$chkstr=explode(',',$tmp);
	for ($x=0;$x<count($chkstr);$x++) {
		if (strpos($text,$chkstr[$x])) {
			$enc='UTF-8';
			break 1;
		}
	}
	$text=trim($text);
	if ($enc=='IS0-8859') {
		$text=mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
		fwrite ($fil,"UTF: $text\n");
		$text=trim($text);
	}
	$text=html_entity_decode($text);
	fclose($fil);
	return($text);
}

/**
 * Send invoice email with PDF to customer after order is invoiced via API
 * 
 * Settings used (from settings table, var_grp = 'api'):
 *   - api_invoice_email_enabled: 'on' to enable sending invoice to customer
 * 
 * This function uses send_invoice_pdf.php to generate and send the invoice.
 * 
 * @param int $ordre_id The order ID
 * @return string Result message
 */
function send_api_invoice_email($ordre_id) {
	global $db, $db_id;
	
	$log = fopen("../temp/$db/.ht_rest_api.log", "a");
	fwrite($log, __line__." send_api_invoice_email called for order $ordre_id\n");
	
	// Check if invoice email is enabled
	$qtxt = "select var_value from settings where var_name = 'api_invoice_email_enabled' and var_grp = 'api'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$enabled = $r['var_value'];
	
	if ($enabled != 'on') {
		fwrite($log, __line__." API invoice email is disabled\n");
		fclose($log);
		return "Invoice email disabled";
	}
	
	// Get db_id if not set
	if (!isset($db_id) || !$db_id) {
		list($master, $db_id) = explode('_', $db);
	}
	
	fwrite($log, __line__." Including send_invoice_pdf.php\n");
	
	// Include the invoice PDF generator
	define('API_INCLUDE', true);
	include_once(__DIR__ . '/send_invoice_pdf.php');
	
	// Call the function to generate and send the invoice PDF
	$result = send_invoice_pdf($ordre_id, $db, $db_id);
	
	fwrite($log, __line__." send_invoice_pdf result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . " - " . $result['message'] . "\n");
	fclose($log);
	
	return $result['message'];
}

//return JSON array
exit(json_encode($value));
?> 
