<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/rest_api.php --- lap 4.0.5 --- 2024-12-03 ---
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
// Copyright (c) 2016-2024 saldi.dk aps
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
// 20251007 PHR changed name on log file
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

function fetch_from_table($select,$from,$where,$order_by,$limit) {
	global $db,$db_skriv_id;
	global $brugernavn;
	global $webservice;
	
	$log=fopen("../temp/$db/.ht_rest_api.log","a");
	
	$allowed_tables = array('adresser','batch_kob','batch_salg','kassekladde','lagerstatus','mylabel','openpost','ordrer','ordrelinjer');
	array_push($allowed_tables,'transaktioner','varer','shop_ordrer','shop_varer','rental');
	if ($_SERVER['REMOTE_ADDR'] == '91.235.100.32')  array_push($allowed_tables,'mysale','regnskab');

	fwrite($log,__line__." Query: select ".$select." from ".$from." where ".$where." order by ".$order_by." limit ".$limit."\n");
	if (strpos($select,';') || strpos($from,';') || strpos($where,';') || strpos($order_by,';') || strpos($limit,';')) {
		fwrite($log,__line__." sql_injection attempt\n");
		fclose ($log);
		return 'sql_injection attempt';
		exit;
	}
	(strpos($from,','))?$q_tables=explode(',',$from):$q_tables[0]=$from;
		fwrite($log,count($q_tables)." $q_tables[0] \n");
	for ($i=0;$i<count($q_tables);$i++) {		
		if (in_array($q_tables[$i],$allowed_tables)) fwrite($log,__line__." legal tabel ($q_tables[$i]) \n");
		else {
			fwrite($log,__line__." illegal tabel ($q_tables[$i]) \n");
			fclose ($log);
			return "illegal tabel ($q_tables[$i])";
			exit;
		}
	}
	$qtxt="select $select from $from";
	if ($where) $qtxt.=" where $where";
	if ($order_by) $qtxt.=" order by $order_by";
	if ($limit) $qtxt.=" limit $limit";
	fwrite($log,__line__." Query: $qtxt\n");
	$result = array();
	$x=0;
	$y=0;
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($y < db_num_fields($q)) {
		$fieldName[$y] = db_field_name($q,$y); 
		$fieldType[$y] = db_field_type($q,$y);
		$result[$x][$y]=$fieldName[$y]."(".$fieldType[$y].")";
		fwrite($log,__line__." result ".$result[$x][$y]."\n");
		$y++;
	}
	$x++;
  while($result[$x]=db_fetch_array($q)) $x++;
	fclose ($log);
	return $result;
} #endfunc fetch_from_table

function update_table($update,$set,$where) {
	global $db,$db_skriv_id;
	global $brugernavn;
	global $webservice;
	
	$log=fopen("../temp/$db/.ht_rest_api.log","a");
	$allowed_tables=array('ordrer','shop_ordrer','ordrelinjer','varer','shop_varer','vare_lev','adresser','ansatte','shop_adresser','kladdeliste','kassekladde');
	fwrite($log,__line__." Query: update ".$update." set ".$set." where ".$where."\n");
	if (strpos($update,';') || strpos($set,';') || strpos($where,';')) {
		fwrite($log,__line__." sql_injection attempt\n");
		fclose ($log);
		return 'sql_injection attempt';
		exit;
	}
	if (in_array($update,$allowed_tables)) fwrite($log,__line__." legal tabel ($update) \n");
	else {
		fwrite($log,__line__." illegal tabel ($update) \n");
		fclose ($log);
		return "illegal tabel ($update)";
		exit;
	}
	(strpos($set,','))?$q_sets=explode(',',$set):$q_sets[0]=$set;
	for ($i=0;$i<count($q_sets);$i++) {
		list ($q_set_a,$q_set_b)=explode("=",$q_sets[$i]);
		if (strtolower(trim($q_set_a))=='id') {
			fwrite($log,__line__." illegal field ($q_sets[$i]) \n");
			fclose ($log);
			return "illegal field ($q_sets[$i])";
			exit;
	} else fwrite($log,__line__." legal field ($q_sets[$i]) \n");
	}
	$qtxt="update $update set $set where $where";
#	if ($where) $qtxt.=" where $where";
	fwrite($log,__line__." Query: $qtxt\n");
	$result = db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$x=0;
	$y=0;
	fclose ($log);
	return $result;
}

function insert_into_table($insert,$fields,$values) {
	global $db,$db_skriv_id;
	global $brugernavn;
	global $webservice;
	
	$log=fopen("../temp/$db/.ht_rest_api.log","a");

	$valchk=explode(",",$values);
	for ($x=0;$x<count($valchk);$x++) {
		if (substr($valchk[$x],0,1) != "'" || substr($valchk[$x],-1) != "'") {
			return "Each value must be surrounded by ' (apostrophes) !";
		}
	}
	$allowed_tables=array('ordrer','shop_ordrer','ordrelinjer','varer','shop_varer','vare_lev','adresser','ansatte','shop_adresser','kladdeliste','kassekladde');
	fwrite($log,__line__." Query: insert into ".$insert." fields ".$fields." values ".$values."\n");
	if (strpos($insert,';') || strpos($fields,';') || strpos($values,';')) {
		fwrite($log,__line__." sql_injection attempt\n");
		fclose ($log);
		return 'sql_injection attempt';
		exit;
	}
	if (in_array($insert,$allowed_tables)) fwrite($log,__line__." legal tabel ($insert) \n");
	else {
		fwrite($log,__line__." illegal tabel ($insert) \n");
		fclose ($log);
		return "illegal tabel ($insert)";
		exit;
	}
	(strpos($fields,','))?$q_fieldss=explode(',',$fields):$q_fieldss[0]=$fields;
	for ($i=0;$i<count($q_fieldss);$i++) {
		if (strtolower(trim($q_fieldss[$i]))=='id') {
			fwrite($log,__line__." illegal field ($q_fieldss[$i]) \n");
			fclose ($log);
			return "illegal field ($q_fieldss[$i])";
			exit;
		} else fwrite($log,__line__." legal field ($q_fieldss[$i]) \n");
	}
	$qtxt="insert into $insert ($fields) values ($values)";
	fwrite($log,__line__." Query: $qtxt\n");
	$result = db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$x=0;
	$y=0;
	fclose ($log);
	return $result;
}

function insert_shop_order($brugernavn,$shopOrderId,$shop_fakturanr,$shop_addr_id,$saldi_kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$cvrnr,$ean,$institution,$tlf,$email,$udskriv_til,$ref,$kontakt,$lev_firmanavn,$lev_addr1,$lev_addr2,$lev_postnr,$lev_bynavn,$lev_land,$lev_tlf,$lev_email,$lev_kontakt,$betalingsbet,$betalingsdage,$betalings_id,$ordredate,$lev_date,$momssats,$valuta,$valutakurs,$gruppe,$afd,$projekt,$ekstra1,$ekstra2,$ekstra3,$ekstra4,$ekstra5,$nettosum,$momssum,$lager,$shop_status,$notes) {

	global $db,$db_skriv_id;
	global $brugernavn;
	global $webservice;
	global $regnaar;

	$betalingsdage=(int)$betalingsdage;

	// to be removed in 4.0.2 -->
	$qtxt = "select data_type from information_schema.columns where table_name = 'ordrer' and column_name = 'shop_status'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		if ($r['data_type'] == 'integer') {
			db_modify("ALTER table ordrer alter column shop_status TYPE varchar(20)",__FILE__ . " linje " . __LINE__);
		}
	}
	// <--
	list($master,$db_skriv_id)=explode('_',$db);
	$log=fopen("../temp/$db/.ht_rest_api.log","a");
	fwrite($log,__line__." Ordredate: $ordredate\n");
/*	
	if (strpos('',$ordredate)) list($ordredate,$tidspkt)=explode(' ',$ordredate);
	else $tidspkt=(date('H:i')); 
	$tidspkt=substr($tidspkt,0,5);
*/
	if (strlen($ordredate)>10) { #20210107
		$tidspkt=substr($ordredate,11); 
		#list($ordredate,$tidspkt)=explode(' ',$ordredate);
	}	else $tidspkt=(date('H:i')); 
	$tidspkt=substr($tidspkt,0,5);
	fwrite($log,__line__." Ordredate: $ordredate\n");
	fwrite($log,__line__." Tidspkt: $tidspkt\n");
	
	fwrite($log,__line__." Ordredate: $ordredate\n");
	fwrite($log,__line__." Tidspkt: $tidspkt\n");
	
	fwrite($log,__line__." $brugernavn,$shopOrderId,$shop_fakturanr,$shop_addr_id,$saldi_kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$cvrnr,$ean,$institution,$tlf,$email,$udskriv_til,$ref,$kontakt,$lev_firmanavn,$lev_addr1,$lev_addr2,$lev_postnr,$lev_bynavn,$lev_land,$lev_tlf,$lev_email,$lev_kontakt,$betalingsbet,$betalingsdage,$betalings_id,$ordredate,$lev_date,$momssats,$valuta,$valutakurs,$gruppe,$afd,$projekt,$ekstra1,$ekstra2,$ekstra3,$ekstra4,$ekstra5,$nettosum,$momssum,$lager,$shop_status,$notes\n");
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
    $shopOrderId = (int)$shopOrderId;
    $shop_addr_id = (int)$shop_addr_id;

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

	$art='DO';
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
		// Primary check: Check if shop order ID already exists
		fwrite($log,__line__." Checking for existing shop order: $shopOrderId\n");
		$qtxt = "select id,saldi_id from shop_ordrer where shop_id='$shopOrderId'";
		fwrite($log,__line__." $qtxt\n");
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['id']) { 
			fwrite($log,__line__." DUPLICATE DETECTED: Order id $shopOrderId already exists in saldi (saldi_id: $r[saldi_id])\n");
			fclose ($log);
			return "Order id: $shopOrderId exists in saldi (internal ID: $r[saldi_id])";
		}
		
		// Additional check: Verify no duplicate orders with same customer details and total amount
		if ($nettosum > 0 && $saldi_addr_id) {
			$duplicate_window = date('Y-m-d', strtotime('-1 day')); // Check last 24 hours
			$qtxt = "select o.id, o.ordrenr, o.sum from ordrer o ";
			$qtxt.= "where o.konto_id='$saldi_addr_id' and o.sum='$nettosum' ";
			$qtxt.= "and o.ordredate >= '$duplicate_window' and o.art='DO'";
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
	$qtxt="select saldi_id from shop_adresser where shop_id='$shop_addr_id'";
	fwrite($log,__line__." $qtxt\n");
	$r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$saldi_addr_id=$r['saldi_id'];
	fwrite($log,__line__." saldi_addr_id='$saldi_addr_id'\n");
	if (!$saldi_addr_id) {
		if ($saldi_kontonr) {
			$qtxt="select id, kontonr from adresser where kontonr='$saldi_kontonr'";
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
			$qtxt.="tlf='$tlf'";
			fwrite($log,__line__." $qtxt\n");
			$qtxt=chk4utf8($qtxt);
			if ($r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$saldi_addr_id=$r['id']*1;
				$kontonr=$saldi_kontonr=$r['kontonr']; #20190822
				fwrite($log,__line__." saldi_addr_id $saldi_addr_id\n");
				fwrite($log,__line__." kontonr $saldi_kontonr\n");
			}
		}
		if ($saldi_addr_id && $shop_addr_id) {
			$qtxt="insert into shop_adresser(saldi_id,shop_id)values('$saldi_addr_id','$shop_addr_id')";
			fwrite($log,__line__." $qtxt\n");
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);  
		} elseif (!$saldi_addr_id) { #if ($shop_addr_id) {
				$qtxt="select id from adresser where art = 'D' and kontonr='$num_tlf'";
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
				$qtxt.="('$kontonr','".db_escape_string($firmanavn)."','".db_escape_string($addr1)."','".db_escape_string($addr2)."',";
				$qtxt.="'".db_escape_string($postnr)."','".db_escape_string($bynavn)."','".db_escape_string($land)."',";
				$qtxt.="'".db_escape_string($cvrnr)."','".db_escape_string($ean)."','".db_escape_string($email)."','".db_escape_string($tlf)."',";
				$qtxt.="'$gruppe','D','$betalingsbet','$betalingsdage','".db_escape_string($kontakt)."',";
				$qtxt.="'".db_escape_string($lev_firmanavn)."','".db_escape_string($lev_addr1)."','".db_escape_string($lev_addr2)."',";
				$qtxt.="'".db_escape_string($lev_postnr)."','".db_escape_string($lev_bynavn)."','".db_escape_string($lev_land)."',";
				$qtxt.="'".db_escape_string($lev_kontakt)."','".db_escape_string($lev_tlf)."','".db_escape_string($lev_email)."','')";
				fwrite($log,__line__." $qtxt\n");
				$qtxt=chk4utf8($qtxt);
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select id from adresser where kontonr='$kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__));
				$saldi_addr_id=$r['id'];
			} 
			if ($shop_addr_id) {
				fwrite($log,__line__." insert into shop_adresser(saldi_id,shop_id)values('$saldi_addr_id','$shop_addr_id')\n");
				db_modify("insert into shop_adresser(saldi_id,shop_id)values('$saldi_addr_id','$shop_addr_id')",__FILE__ . " linje " . __LINE__);  
			}
	} else {
		fwrite($log,__line__." select kontonr from adresser where id = '$saldi_addr_id'\n");
		$r=db_fetch_array(db_select("select kontonr from adresser where id = '$saldi_addr_id'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['kontonr'];
		fwrite($log,__line__." kontonr $kontonr\n");
	}
	// Generate unique order number with additional safety checks
	$max_attempts = 5;
	$attempt = 0;
	$ordrenr_found = false;
	
	while (!$ordrenr_found && $attempt < $max_attempts) {
		$attempt++;
		fwrite($log,__line__." Attempting to generate unique ordrenr (attempt $attempt)\n");
		
		$qtxt="select max(ordrenr) as max_ordrenr from ordrer where art='DO'";
		fwrite($log,__line__." $qtxt\n");
		$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
		$ordrenr = ($r['max_ordrenr'] ? $r['max_ordrenr'] : 0) + 1;
		
		// Double-check that this order number doesn't exist
		$qtxt="select id from ordrer where ordrenr='$ordrenr' and art='DO'";
		$check_r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if (!$check_r['id']) {
			$ordrenr_found = true;
			fwrite($log,__line__." Generated unique ordrenr: $ordrenr\n");
		} else {
			fwrite($log,__line__." Ordrenr $ordrenr already exists, retrying...\n");
			sleep(1); // Brief pause to avoid rapid retries
		}
	}
	
	if (!$ordrenr_found) {
		fwrite($log,__line__." ERROR: Could not generate unique ordrenr after $max_attempts attempts\n");
		fclose ($log);
		return "Error: Could not generate unique order number";
	}
	$projektnr=0;
	$qtxt = "select box1 from grupper where art='DG' and kodenr = '$gruppe' ";
	$qtxt.= "order by fiscal_year desc limit 1";
	fwrite($log,__line__." $qtxt\n");
	$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
	$momsgruppe=str_replace('S','',$r['box1']);
	$qtxt = "select box2 from grupper where art='SM' and kodenr = '$momsgruppe' ";
	$qtxt.= "order by fiscal_year desc limit 1";
	$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
	$momssats=$r['box2']*1;
	if (!$valuta)$valuta='DKK';
	if ($valuta=='DKK') {
		$valutakurs=100;
	} else {
		$qtxt="select box2 from grupper where art='VK' and box1 = '$valuta'";
		fwrite($log,__line__." $qtxt\n");
		if ($r=db_fetch_array(db_modify($qtxt,__FILE__ . " linje " . __LINE__))) $valutakurs=$r['box2']*1;
		else $valutakurs=100;
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
	$qtxt.= "tidspkt,phone,shop_status,shop_id,notes,fakturadate)";
	$qtxt.= " values ";
	$qtxt.= "('$ordrenr','$saldi_addr_id','$kontonr','".db_escape_string($firmanavn)."','".db_escape_string($addr1)."',";
	$qtxt.= "'".db_escape_string($addr2)."','".db_escape_string($postnr)."','".db_escape_string($bynavn)."',";
	$qtxt.= "'".db_escape_string($land)."','".db_escape_string($kontakt)."','".db_escape_string($email)."',";
	$qtxt.= "'$udskriv_til','$art','$projektnr','$momssats','$betalingsbet','$betalingsdage','$betalings_id','0',";
	$qtxt.= "'$ordredate','$valuta','$valutakurs','$afd','$ref','','$ekstra1','$ekstra2','$ekstra3',";
	$qtxt.= "'$ekstra4','$ekstra5','$shop_fakturanr','$cvrnr','$ean','$nettosum','$momssum',";
	$qtxt.= "'".db_escape_string($lev_firmanavn)."','".db_escape_string($lev_addr1)."','".db_escape_string($lev_addr2)."',";
	$qtxt.= "'".db_escape_string($lev_postnr)."','".db_escape_string($lev_bynavn)."','".db_escape_string($lev_kontakt)."',";
	$qtxt.= "'".db_escape_string($tidspkt)."','".db_escape_string($tlf)."','$shop_status',";
	$qtxt.= "'$shopOrderId','".db_escape_string($notes)."', '$ordredate')";
	fwrite($log,__line__." $qtxt\n");
	$qtxt=chk4utf8 ($qtxt);
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="select id from ordrer where ordrenr='$ordrenr' and kontonr='$kontonr'";
	fwrite($log,__line__." $qtxt\n");
	$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
	$saldi_ordre_id=$r['id'];
	fwrite($log,__line__."saldi_ordre_id: $saldi_ordre_id\n");
	
	// Enhanced shop_ordrer insertion with duplicate prevention
	if ($saldi_ordre_id && $shopOrderId) {
		// Final check before inserting into shop_ordrer table
		$qtxt_check="select id from shop_ordrer where shop_id='$shopOrderId'";
		$check_r=db_fetch_array(db_select($qtxt_check,__FILE__ . " linje " . __LINE__));
		if ($check_r['id']) {
			fwrite($log,__line__." ERROR: Race condition detected - shop_id $shopOrderId already exists in shop_ordrer\n");
			// Clean up the order we just created since it's a duplicate
			db_modify("delete from ordrer where id='$saldi_ordre_id'",__FILE__ . " linje " . __LINE__);
			fclose ($log);
			return "Error: Duplicate shop order detected during insertion";
		}
		
		$qtxt="insert into shop_ordrer(saldi_id,shop_id)values('$saldi_ordre_id','$shopOrderId')";
		fwrite($log,__line__." $qtxt\n");
		$insert_result = db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		
		if (!$insert_result) {
			fwrite($log,__line__." ERROR: Failed to insert into shop_ordrer table\n");
			// Clean up the order we just created
			db_modify("delete from ordrer where id='$saldi_ordre_id'",__FILE__ . " linje " . __LINE__);
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
	
	$varenr = NULL;
	$linje_id = $ordresum = 0;
	$shop_varenr = trim($shop_varenr);

	list($master,$db_skriv_id)=explode('_',$db);
	$lager = (int)$lager;
	$log=fopen("../temp/$db/.ht_rest_api.log","a");
	fwrite($log,__line__." ".date("Y-m-d H:i:s")."\n");
	fwrite($log,__line__." insert_shop_orderline($ordre_id,$shop_vare_id,$shop_varenr,$antal,$beskrivelse,$pris,$momsfri,$rabat,$lager,$stregkode,$shop_variant,$discountType)\n");
	if ($ordre_id && is_numeric($ordre_id)) {
		$qtxt="select status,momssats from ordrer where id='$ordre_id'";
		fwrite($log,__line__." ".$qtxt."\n");
		$r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$momssats=$r['momssats'];
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
		$qtxt="select saldi_id, saldi_variant from shop_varer where shop_variant = '$shop_variant'"; #20220615
		fwrite($log,__line__." $qtxt\n");
		$r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$vare_id=$r['saldi_id'];
		$saldi_variant=$r['saldi_variant'];
	} elseif ($shop_vare_id) {
		$qtxt="select saldi_id from shop_varer where shop_id='$shop_vare_id'";
		fwrite($log,__line__." $qtxt\n");
		$r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$vare_id=$r['saldi_id'];
	} elseif ($shop_varenr) {
		$qtxt="select id from varer where UPPER(varenr)=UPPER('$shop_varenr') or UPPER(varenr_alias)=UPPER('$shop_varenr') or UPPER(stregkode)=UPPER('$shop_varenr')";
		$qtxt=chk4utf8($qtxt);
		fwrite($log,__line__." $qtxt\n");
		$r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$vare_id=$r['id'];
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
	include("../includes/ordrefunc.php");
	if ($vare_id) {
		$qtxt="select varenr,samlevare from varer where id='$vare_id'";
		fwrite($log,__line__." $qtxt\n");
		$r=db_fetch_array (db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$samlevare=$r['samlevare'];
		fwrite($log,__line__." Vare_id $vare_id - Samlevare $samlevare\n");
		if ($saldi_variant) {
			$qtxt="select variant_stregkode FROM variant_varer where id='$saldi_variant'";
			fwrite($log,__line__." $qtxt\n");
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$varenr=$r['variant_stregkode'];
				fwrite($log,__line__." Varenr set to $varenr\n");
			}
		}
		if ($stregkode) {
			$qtxt="select id FROM variant_varer where variant_stregkode='$stregkode'";
			fwrite($log,__line__." $qtxt\n");
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$varenr=$stregkode;
				fwrite($log,__line__." Varenr set to $varenr\n");
			}
		}
	} else {
		if ($stregkode) {
			$qtxt="select id,samlevare from varer where stregkode='$stregkode'";
			fwrite($log,__line__." $qtxt\n");
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$vare_id=$r['id'];
				$samlevare=$r['samlevare'];
#				$shop_variant=0;
				fwrite($log,__line__." Vare_id $vare_id - Samlevare $samlevare\n");
			}
		}
		if (!$vare_id && $stregkode) {
			$qtxt="select id,vare_id from variant_varer where variant_stregkode='$stregkode'";
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
			if ($shop_varenr) $qtxt="select id,samlevare from varer where UPPER(varenr)=UPPER('$shop_varenr') or UPPER(varenr_alias)=UPPER('$shop_varenr') or UPPER(stregkode)=UPPER('$shop_varenr')";
			else $qtxt="select id,samlevare from varer where varenr='$shop_vare_id' or varenr_alias='$shop_vare_id' or stregkode='$shop_vare_id'";
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
			$qtxt="insert into shop_varer(saldi_id,shop_id,saldi_variant,shop_variant)values('$vare_id','$shop_vare_id','$saldi_variant','$shop_variant')";
			fwrite($log,__line__." $qtxt\n");
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);  
		}
	}
	if (($vare_id)) {
		$qtxt="update varer set publiceret='on' where id = '$vare_id'";
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
		fwrite($log,__line__." opret_saet($ordre_id,$vare_id,$pris*1.25,25,$antal,on,$lager)\n");
		opret_saet($ordre_id,$vare_id,$pris*1.25,25,$antal,on,$lager);
	} elseif($vare_id) {
		fwrite ($log,__line__." Antal: $antal\n");
		fwrite ($log,__line__." Beskrivelse: $beskrivelse\n");
		fwrite ($log,__line__." Lager: $lager\n");
		fwrite ($log,__line__." Vnr: $varenr\n");
		
		fwrite($log,__line__." opret_ordrelinje($ordre_id,$vare_id,".db_escape_string(chk4utf8($varenr)).",$antal,".db_escape_string(chk4utf8($beskrivelse)).",$pris,$rabat,'100','DO',$momsfri,$posnr,'0','','','','0','','','','','',$lager,".__line__.")\n");
		$lineSum = opret_ordrelinje($ordre_id,$vare_id,db_escape_string(chk4utf8($varenr)),$antal,db_escape_string(chk4utf8($beskrivelse)),$pris,$rabat,'100','DO',$momsfri,$posnr,'0','','',$discountType,'0','','','','','',$lager,__LINE__);
		
		fwrite($log,__line__." LineSum =  $lineSum\n");
		$qtxt = "select max(id) as id from ordrelinjer where ordre_id = '$ordre_id' and vare_id = '$vare_id'"; 
		fwrite($log,__line__." $qtxt\n");
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $linje_id = $r['id'];
		else $linje_id = 0;
	} else {
		$qtxt="insert into ordrelinjer(ordre_id,beskrivelse,posnr,vare_id,antal,pris,rabat,lager,momsfri)";
		$qtxt.=" values ";
		$qtxt.="('$ordre_id','','$posnr','0','0','0','0','0','')";
		fwrite($log,__line__." $qtxt\n");
		$qtxt=chk4utf8($qtxt);
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$posnr+=100;
		$qtxt="insert into ordrelinjer(ordre_id,beskrivelse,posnr,vare_id,antal,pris,rabat,lager,momsfri)";
		$qtxt.=" values ";
		$qtxt.="('$ordre_id','".db_escape_string($beskrivelse)."','$posnr','0','0','0','0','0','')";
		fwrite($log,__line__." $qtxt\n");
		$qtxt=chk4utf8($qtxt);
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

function fakturer_ordre($saldi_id,$udskriv_til,$pos_betaling) {
	global $db,$db_skriv_id;
	global $baseCurrency,$brugernavn;
	global $webservice;
	global $regnaar;
	#return "$nettosum,$momssum";
	
	include("../includes/ordrefunc.php");
	
	$log=fopen("../temp/$db/.ht_rest_api.log","a");
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
	$qtxt="select antal,pris,rabat,momssats from ordrelinjer where ordre_id='$saldi_id' and vare_id > 0";
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
	$qtxt="update ordrer set fakturadate=ordredate where id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
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
		return($svar);
	}
	fclose ($log);
	transaktion ('commit');
	return($saldi_id); 
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

	if (!file_exists("../temp")) mkdir("../temp",0777);
	if (!file_exists("../temp/$db")) mkdir("../temp/$db",0777);
	$log=fopen("../temp/$db/.ht_rest_api.log","a");
	if (isset($_GET['db'])) {
		$db=$_GET['db'];
		(strpos($db,'_'))?list($master,$db_skriv_id)=explode('_',$db):$master=$db;
		fwrite($log,__line__." $master,$db_skriv_id\n");
	}	else {
		fwrite($log,__line__." Missing db\n");
		fclose($log);
		return 'missing db';
	}
	$qtxt="select id,lukket from regnskab where db='$db'"; #20201223
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
		if ($api_key != $_GET['key']) {
			$log=fopen("../temp/$db/.ht_rest_api.log","a");
			fwrite($log,__line__." Access denied (key) $api_key != $_GET[key]\n");
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
$possible_url = array("fetch_from_table,update_table,insert_shop_order,insert_shop_orderline");
$value = "An error has occurred";
if (isset($_GET['action'])){# && in_array($_GET['action'], $possible_url)){
	$action=trim($_GET['action']);
	$svar = access_check();
	if ($svar == 'OK') {#exit(json_encode($value));
		$log=fopen("../temp/$db/.ht_rest_api.log","a");
		fwrite($log,__line__." Action:".$_GET['action']."\n");
		if ($action=='fetch_from_table') {
			fclose ($log);
			$select    = if_isset($_GET['select']);
			$from      = if_isset($_GET['from']);
			$where     = if_isset($_GET['where']);
			$where     = str_replace("**","%",$where);
			$order_by  = if_isset($_GET['order_by']);
			$limit     = if_isset($_GET['limit']);
			if ($select && $from) $value = fetch_from_table($select,$from,$where,$order_by,$limit);
##############################################
		} elseif ($action=='get_sold_labels') {
			fclose ($log);
			$value = get_sold_labels();
##############################################
		} elseif ($action=='get_all_labels') {
			fclose ($log);
			$value = get_all_labels();
##############################################
		} elseif ($action=='update_table') {
			fclose ($log);
			$value = update_table($_GET['update'],$_GET['set'],str_replace("**","%",$_GET['where']));
##############################################
		}	elseif ($action=='insert_into_table') {
			fclose ($log);
			$value = insert_into_table($_GET['insert'],$_GET['fields'],$_GET['values']);
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
			$addr1         = if_isset($_GET['addr1']);
			$addr2         = if_isset($_GET['addr2']);
			$afd           = if_isset($_GET['afd'])*1;
			$betalings_id  = if_isset($_GET['betalings_id']);
			$betalingsbet  = if_isset($_GET['betalingsbet']);
			$betalingsdage = if_isset($_GET['betalingsdage']);
			$bynavn        = if_isset($_GET['bynavn']);
			$cvr           = if_isset($_GET['cvr']);
			$firmanavn     = if_isset($_GET['firmanavn']);
			$land          = if_isset($_GET['land']);
			$shop_addr_id  = if_isset($_GET['shop_addr_id']);
			$shopOrderId   = if_isset($_GET['shop_ordre_id']);
			if (!$shopOrderId) $shopOrderId = 0;
			$shop_fakturanr = if_isset($_GET['shop_fakturanr']);
			if (!$shop_fakturanr) $shop_fakturanr=$shopOrderId;
			$postnr         = if_isset($_GET['postnr']);
			$ean            = (int)if_isset($_GET['ean']);
			if (!$ean) $ean = '';
			$institution    = if_isset($_GET['institution']);
			$tlf            = if_isset($_GET['tlf']);
			$email          = if_isset($_GET['email']);
			$udskriv_til    = if_isset($_GET['udskriv_til']);
			$ref            = if_isset($_GET['ref']);
			$kontakt        = if_isset($_GET['kontakt']);
			$lager          = if_isset($_GET['lager']);
			if (!$lager) $lager = 1;
			$lev_firmanavn  = if_isset($_GET['lev_firmanavn']);
			$lev_addr1      = if_isset($_GET['lev_addr1']);
			$lev_addr2      = if_isset($_GET['lev_addr2']);
			$lev_postnr     = if_isset($_GET['lev_postnr']);
			$lev_bynavn     = if_isset($_GET['lev_bynavn']);
			$lev_land       = if_isset($_GET['lev_land']);
			$lev_tlf        = if_isset($_GET['lev_tlf']);
			$lev_email      = if_isset($_GET['lev_email']);
			$lev_kontakt    = if_isset($_GET['lev_kontakt']);
			$ordredate      = if_isset($_GET['ordredate']);
			$lev_date       = if_isset($_GET['lev_date']);
			$momssats       = if_isset($_GET['momssats']);
			$valuta         = if_isset($_GET['valuta']);
			$valutakurs     = if_isset($_GET['valutakurs']);
			$gruppe         = if_isset($_GET['gruppe']);
			$nettosum       = if_isset($_GET['nettosum'])*1;
			$momssum        = if_isset($_GET['momssum'])*1;
			$projekt        = if_isset($_GET['projekt']);
			$ekstra1        = if_isset($_GET['ekstra1']);
			$ekstra2        = if_isset($_GET['ekstra2']);
			$ekstra3        = if_isset($_GET['ekstra3']);
			$ekstra4        = if_isset($_GET['ekstra4']);
			$ekstra5        = if_isset($_GET['ekstra5']);
			$notes          = if_isset($_GET['notes']);
			$saldi_kontonr  = if_isset($_GET['saldi_kontonr']);
			$pos_betaling   = if_isset($_GET['pos_betaling']);
			$shop_status    = if_isset($_GET['shop_status']);
			$fil = fopen('../temp/addr1.php','w');
			fwrite($fil,"<?php $"."addr1='$addr1' ?>\n");
			fclose($fil);
			$log=fopen("../temp/$db/.ht_rest_api.log","a");
			fwrite($log,__line__." Saldi kontonr $saldi_kontonr -> ");
			$saldi_kontonr = str_replace('+45','',$saldi_kontonr);  //20240423
			$saldi_kontonr = str_replace('+','',$saldi_kontonr);
			$saldi_kontonr = str_replace(' ','',$saldi_kontonr);
			fwrite($log,__line__." Saldi kontonr $saldi_kontonr\n");
			$params = "$brugernavn,$shopOrderId,$shop_addr_id,$saldi_kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$cvr,$ean,";
			$params.= "$institution,$tlf,$email,$udskriv_til";
			$params.= "$ref,$kontakt,$lev_firmanavn,$lev_addr1,$lev_addr2,$lev_postnr,$lev_bynavn,$lev_land,$lev_tlf,$lev_email,$lev_kontakt,";
			$params.= "$betalingsbet,$betalingsdage,$betalings_id,$ordredate,$lev_date,$momssats,$valuta,$valutakurs,$gruppe,$afd,$projekt,";
			$params.= "$ekstra1,$ekstra2,$ekstra3,$ekstra4,$ekstra5,$nettosum,$momssum,$lager,$pos_betaling,$shop_status,$notes";
			fclose ($log);
			$value = insert_shop_order($brugernavn,$shopOrderId,$shop_fakturanr,$shop_addr_id,$saldi_kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$cvr,$ean,$institution,$tlf,$email,$udskriv_til,$ref,$kontakt,$lev_firmanavn,$lev_addr1,$lev_addr2,$lev_postnr,$lev_bynavn,$lev_land,$lev_tlf,$lev_email,$lev_kontakt,$betalingsbet,$betalingsdage,$betalings_id,$ordredate,$lev_date,$momssats,$valuta,$valutakurs,$gruppe,$afd,$projekt,$ekstra1,$ekstra2,$ekstra3,$ekstra4,$ekstra5,$nettosum,$momssum,$lager,$shop_status,$notes);
##############################################
		} elseif ($action=='insert_shop_orderline') {
			$log=fopen("../temp/$db/.ht_rest_api.log","a");
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
			$ordre_id= if_isset($_GET['saldi_ordre_id']);
			$pos_betaling= if_isset($_GET['pos_betaling']);
			$udskriv_til= if_isset($_GET['udskriv_til']);
			fclose ($log);
			$value = fakturer_ordre($ordre_id,$udskriv_til,$pos_betaling);
##############################################
		} else {
			$value="Illegal action ($action)";
			fclose ($log);
		}
	} else $value=$svar;
} else $value= if_isset($_GET['action']);

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

//return JSON array
exit(json_encode($value));
?> 
