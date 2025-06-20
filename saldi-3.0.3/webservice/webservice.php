<?php

#----------------- webservice/webservice.php --------- 2009.10.20 ----------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------


@session_start();
$s_id=session_id();

ini_set("display_errors", "1");

$bg="nix";
$header="nix";
include("../includes/std_func.php");

$modul=if_isset($_GET['modul']);

system("echo modul = $modul > ../temp/fejl.txt");	

if ($_GET) {
#echo Login($_GET['Login']);
#echo OpretKladde($_GET['s_id']); #Login($_GET['regnskab']);
#echo IndsaetKladdeLinje($_GET['s_id']);
# echo Logoff($s_id);
}


require('nusoap.php');
$server = new soap_server();

$server->configureWSDL('Webservice', 'urn:regnskab');
$server->register("Login",array('regnskab' => 'xsd:string'),array('return' => 'xsd:string'),'urn:regnskab','urn:regnskab#Login');
$server->register("OpretKladde",array('s_id' => 'xsd:string'),array('return' => 'xsd:string'),'urn:s_id','urn:s_id#OpretKladde');
$server->register("IndsaetKladdeLinje",array('s_id' => 'xsd:string'),array('return' => 'xsd:string'),'urn:s_id','urn:s_id#IndsaetKladdeLinje');
$server->register("GetAccountId",array('s_id' => 'xsd:string'),array('return' => 'xsd:string'),'urn:s_id','urn:s_id#GetAccountId');
$server->register("CreateAccount",array('s_id' => 'xsd:string'),array('return' => 'xsd:string'),'urn:s_id','urn:s_id#CreateAccount');
$server->register("CreateOrder",array('s_id' => 'xsd:string'),array('return' => 'xsd:string'),'urn:s_id','urn:s_id#CreateOrder');
$server->register("AddOrderLine",array('s_id' => 'xsd:string'),array('return' => 'xsd:string'),'urn:s_id','urn:s_id#AddOrderLine');
$server->register("OrderToInvoice",array('s_id' => 'xsd:string'),array('return' => 'xsd:string'),'urn:s_id','urn:s_id#OrderToInvoice');
$server->register("SendInvoice",array('s_id' => 'xsd:string'),array('return' => 'xsd:string'),'urn:s_id','urn:s_id#SendInvoice');
$server->register("GetItemId",array('s_id' => 'xsd:string'),array('return' => 'xsd:string'),'urn:s_id','urn:s_id#GetItemId');
$server->register("CreateItem",array('s_id' => 'xsd:string'),array('return' => 'xsd:string'),'urn:s_id','urn:s_id#CreateItem');
$server->register("Logoff",array('s_id' => 'xsd:string'),array('return' => 'xsd:string'),'urn:s_id','urn:s_id#Logoff');

$HTTP_RAW_POST_DATA = isset($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : '';
$server->service($HTTP_RAW_POST_DATA);

function Login($regnskab) {	
	global $s_id;
	global $bg;
	global $header;
	$modulnr=1;
	
	list($regnskab,$brugernavn,$password)=split(chr(9),$regnskab);
	
	skriv_log($db,$brugernavn,"Login");
	$password=md5($password);
	$unixtime=date("U");
	include("../includes/db_query.php");
	include ("../includes/connect.php");
	db_modify("delete from online where  session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array(db_select("select * from regnskab where regnskab = '$regnskab'",__FILE__ . " linje " . __LINE__))) {
		if ($db = trim($r['db'])) {
			$connection = db_connect ($sqhost,$squser,$sqpass,$sqdb);
			if ($connection) {
				db_modify("insert into online (session_id, brugernavn, db, dbuser) values ('$s_id', '$brugernavn', '$db', '$squser')",__FILE__ . " linje " . __LINE__);
				include ("../includes/online.php");
				if ($r = db_fetch_array(db_select("select * from brugere where brugernavn = '$brugernavn' and kode='$password'",__FILE__ . " linje " . __LINE__))) {
					$rettigheder=trim($r['rettigheder']);
					$regnskabsaar=$r['regnskabsaar']*1;
					include ("../includes/connect.php");
			$fp=fopen("../temp/.ht_$db.log","a");
			fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": OK jeg er inde ".$s_id."\n");
			fclose($fp);
					db_modify("update online set regnskabsaar='$regnskabsaar', rettigheder='$rettigheder' where session_id='$s_id'",__FILE__ . " linje " . __LINE__);
					return ($s_id);
				} else {
					db_modify("delete from online where  session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
					return ("wrong password");
				}	
			} else return ("connection failed");
		} else return ("Ukendt regnskab1");
	} else return ("Ukendt regnskab2");
}

function Logoff($s_id) {	
	global $bg;
	global $header;
	$modulnr=1;
	include("../includes/connect.php");
	db_modify("delete from online where session_id='$s_id'",__FILE__ . " linje " . __LINE__);
	return 'GoodBye';
}	

function OpretKladde($s_id) {	
	
	global $bg;
	global $header;
	$modulnr=1;
	
	list($s_id,$kladdenote)=split(chr(9),$s_id);
	$kladdenote=addslashes($kladdenote);
	include("../includes/connect.php");
	list($brugernavn,$db,$regnskabsaar,$regnstart,$regnslut,$charset)=split(chr(9),online($s_id));
	if ($db){
		$x=0;
		$kladdedate=date("Y-m-d");
		$unixtime=date("U");
		db_modify("insert into kladdeliste (kladdedate, bogfort, oprettet_af, tidspkt, kladdenote, hvem) values ('$kladdedate', '-', '$brugernavn', '$unixtime', '$kladdenote', '$brugernavn')",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select id from kladdeliste where oprettet_af = '$brugernavn' and tidspkt = '$unixtime'",__FILE__ . " linje " . __LINE__));
		$kladde_id=$r['id'];
		$r=db_fetch_array(db_select("select MAX(bilag) as bilag from kassekladde where transdate>='$regnstart' and transdate<='$regnslut'",__FILE__ . " linje " . __LINE__));
		$next_bilag=$r['bilag']+1;
		db_modify("update kladdeliste set tidspkt='', hvem='' where id='$kladde_id'",__FILE__ . " linje " . __LINE__);
		return $kladde_id.chr(9).$next_bilag;	
	} else return "OpretKladdeFejl";
}

function CreateAccount($s_id) {	
	
	list($s_id,$art,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$kontakt,$tlf,$fax,$email,$mailfakt,$web,$cvrnr,$gruppe,$bank_navn,$bank_reg,$bank_konto,$bank_fi,$erh,$swift,$notes,$kreditmax,$betalingsbet,$betalingsdage,$ean,$institution,$rabat)=split(chr(9),$s_id);
#	return("$art,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$kontakt,$tlf,$fax,$email,$mailfakt,$web,$cvrnr,$gruppe,$bank_navn,$bank_reg,$bank_konto,$bank_fi,$erh,$swift,$notes,$kreditmax,$betalingsbet,$betalingsdage,$ean,$institution");
	if (!$kontonr) $kontonr=0;
	if (!is_numeric($kontonr)) {
		return ("Account number not numeric");
		exit;
	}
	$art=strtoupper($art);
	if ($art!="D") {
		return ("Art is $art! Must be \"D\"");
		exit;
	} 
	$firmanavn=addslashes($firmanavn);
	$addr1=addslashes($addr1);
	$addr2=addslashes($addr2);
	$postnr=addslashes($postnr);
	$bynavn=addslashes($bynavn);
	$land=addslashes($land);
	$kontakt=addslashes($kontakt);
	$tlf=addslashes($tlf);
	$fax=addslashes($fax);
	$email=addslashes($email);
	$mailfakt=addslashes($mailfakt);
	$web=addslashes($web);
	$cvrnr=addslashes($cvrnr);
	$gruppe=$gruppe*1;
	$bank_navn=addslashes($bank_navn);
	$bank_reg=addslashes($bank_reg);
	$bank_konto=addslashes($bank_konto);
	$bank_fi=addslashes($bank_fi);
	$erh=addslashes($erh);
	$swift=addslashes($swift);
	$notes=addslashes($notes);
	$kreditmax=$kreditmax*1;
	$rabat=$rabat*1;
	$tmp=$betalingsbet;
	$betalingsbet=addslashes($betalingsbet);
	$betalingsdage=$betalingsdage*1;
	$ean=addslashes($ean);
	$institution=addslashes($institution);
	if (!$gruppe) {
		return ("Missing customer group");
		exit;
	}
	list($brugernavn,$db,$regnskabsaar,$regnstart,$regnslut,$charset)=split(chr(9),online($s_id));
	if ($db){
		$x=0;
		if ($kontonr) {
			if (db_fetch_array(db_select("select id from adresser where kontonr='$kontonr' and art = '$art'",__FILE__ . " linje " . __LINE__))) { 
				return ("Account number exist");
				exit;
			}
		} else {
			$r=db_fetch_array(db_select("select kontonr from adresser where kontonr<'999999' and art = '$art'",__FILE__ . " linje " . __LINE__));
			$kontonr = $r['kontonr']+1;
		}
#return("insert into adresser (art,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,tlf,fax,email,mailfakt,web,cvrnr,gruppe,bank_navn,bank_reg,bank_konto,bank_fi,erh,swift,notes,kreditmax,betalingsbet,betalingsdage,ean,institution) values ('$art','$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$tlf','$fax','$email','$mailfakt','$web','$cvrnr','$gruppe','$bank_navn','$bank_reg','$bank_konto','$bank_fi','$erh','$swift','$notes','$kreditmax','$betalingsbet','$betalingsdage','$ean','$institution')");		
		db_modify("insert into adresser (art,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,tlf,fax,email,mailfakt,web,cvrnr,gruppe,bank_navn,bank_reg,bank_konto,bank_fi,erh,swift,notes,kreditmax,betalingsbet,betalingsdage,ean,institution) values ('$art','$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$tlf','$fax','$email','$mailfakt','$web','$cvrnr','$gruppe','$bank_navn','$bank_reg','$bank_konto','$bank_fi','$erh','$swift','$notes','$kreditmax','$betalingsbet','$betalingsdage','$ean','$institution')",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select id from adresser where kontonr='$kontonr' and art = '$art'",__FILE__ . " linje " . __LINE__));
		$id=$r['id'];
		if ($id) return ("$id");
		else return ("Error creating account $kontonr");
		exit;
	} else return ("SystemError: database not found");
} # endfunc CreateAccount

function GetAccountId($s_id) {	
	
	list($s_id,$art,$kontonr)=split(chr(9),$s_id);
	
	if ($art!="D" and $art!="K") {
		return "art must be D or K";
		exit;
	}
	if (!$kontonr) {
		return "Account number missing";
		exit;
	}
	if (!is_numeric($kontonr)) {
		return "Account number not numeric";
		exit;
	}
	list($brugernavn,$db,$regnskabsaar,$regnstart,$regnslut,$charset)=split(chr(9),online($s_id));
	skriv_log($db,$brugernavn,"GetAccountId");
	$r=db_fetch_array(db_select("select id from adresser where kontonr='$kontonr' and art = '$art'",__FILE__ . " linje " . __LINE__));
	$id=$r['id']*1;
	return ("$id");
} # endfunc GetAccountId

function GetItemId($s_id) {
	list($s_id,$varenr)=split(chr(9),$s_id);
	if (!$varenr) {
		return "Item number missing";
		exit;
	}
	$varenr=addslashes($varenr);
	list($brugernavn,$db,$regnskabsaar,$regnstart,$regnslut,$charset)=split(chr(9),online($s_id));
	$r=db_fetch_array(db_select("select id from varer where varenr='$varenr'",__FILE__ . " linje " . __LINE__));
	$id=$r['id']*1;
	if ($id) return ("$id");
	else return ("Item number $varenr not found");
}

function OrderToInvoice($s_id) {
	
	list($s_id,$ordre_id)=split(chr(9),$s_id);
	$ordre_id=$ordre_id*1;
	$webservice='on';
	
	global $db;		
	
	list($brugernavn,$db,$regnskabsaar,$regnstart,$regnslut,$charset)=split(chr(9),online($s_id));
		
	$fp=fopen("../temp/$db/webservice.log","a");
	fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").__FILE__ . " linje " . __LINE__." > $fakturadate < \n");
	fclose($fp);
	
	$r=db_fetch_array(db_select("select status, fakturanr from ordrer where id = '$ordre_id'",__FILE__ . " linje " . __LINE__));
	if ($r['status'] > 2) {
	fclose($fp);
		return ("Invoice allready exist. Invoice nr: $r[fakturanr]");
	} else {
			if ($r['status'] < 2) {		
				db_modify("update ordrer set status = '2' where id = '$ordre_id'",__FILE__ . " linje " . __LINE__);
#				return ("Invoice failed. Order id $ordre_id");
#				exit;
		}
	}
	
$svar='OK';
	include("../includes/ordrefunc.php");
#	include("../debitor/bogfor.php");
	$svar=levering($ordre_id,'on','',$webservice);
	$svar=momsupdat($ordre_id);
	$svar=bogfor($ordre_id,$webservice);
#	bogfor_nu($ordre_id,$webservice);
	return("$svar");
}

function SendInvoice($s_id) {
	list($s_id,$ordre_id)=split(chr(9),$s_id);
	global $db;		
	$ordre_id=$ordre_id*1;
	if (!$ordre_id) return("Missing order ID"); 
	list($brugernavn,$db,$regnskabsaar,$regnstart,$regnslut,$charset)=split(chr(9),online($s_id));
	$webservice='on';
	include("../debitor/formprintfunc.php");
	$svar=formularprint($ordre_id,'4','0',$charset);
	return("$svar");
}


function CreateItem($s_id){
	list($s_id,$varenr,$beskrivelse,$enhed,$enhed2,$forhold,$salgspris,$kostpris,$provisionsfri,$gruppe,$lukket,$notes,$samlevare,$min_lager,$max_lager,$trademark,$retail_price,$special_price,$tier_price,$special_from_date,$special_to_date,$colli,$outer_colli,$open_colli_price,$outer_colli_price,$campaign_cost,$location)=split(chr(9),$s_id);
	
	$varenr=addslashes($varenr);
	$beskrivelse=addslashes($beskrivelse);
	$trademark=addslashes($trademark);
	$enhed=addslashes($enhed);
	$enhed2=addslashes($enhed2);
	$provisionsfri=addslashes($provisionsfri);
	$lukket=addslashes($lukket);
	$notes=addslashes($notes);
	$samlevare=addslashes($samlevare);
	$location=addslashes($location);
	$forhold=$forhold*1;
	$salgspris=$salgspris*1;
	$kostpris=$kostpris*1;
	$gruppe=$gruppe*1;
	$min_lager=$min_lager*1;
	$max_lager=$max_lager*1;
	$retail_price=$retail_price*1;
	$special_price=$special_price*1;
	$tier_price=$tier_price*1;
#	$special_from_date
#	$special_to_date
	$colli=$colli*1;
	$outer_colli=$outer_colli*1;
	$open_colli_price=$open_colli_price*1;
	$outer_colli_price=$outer_colli_price*1;
	$campaign_cost=$campaign_cost*1;
	if ($special_from_date) {
		list($y,$m,$d)=split("-",$special_from_date);
		if (!checkdate($m,$d,$y)) {
			return ("Error in special_from_date: $special_from_date");
			exit;
		}
	} else $special_from_date="2000-01-01";
	if ($special_to_date) {
		list($y,$m,$d)=split("-",$special_to_date);
		if (!checkdate($m,$d,$y)) {
			return ("Error in special_from_date: $special_to_date");
			exit;
		}
	} else $special_to_date="2000-01-01";
	
	list($brugernavn,$db,$regnskabsaar,$regnstart,$regnslut,$charset)=split(chr(9),online($s_id));

	if ($db && $varenr){
		if (db_fetch_array(db_select("select id from varer where varenr='$varenr'",__FILE__ . " linje " . __LINE__))) {
			return('Itemnumber alreary exist');
		} else {
			db_modify("insert into varer (varenr,beskrivelse,enhed,enhed2,forhold,salgspris,kostpris,provisionsfri,gruppe,lukket,notes,samlevare,min_lager,max_lager,trademark,retail_price,special_price,tier_price,special_from_date,special_to_date,colli,outer_colli,open_colli_price,outer_colli_price,campaign_cost,location) values 			('$varenr','$beskrivelse','$enhed','$enhed2','$forhold','$salgspris','$kostpris','$provisionsfri','$gruppe','$lukket','$notes','$samlevare','$min_lager','$max_lager','$trademark','$retail_price','$special_price','$tier_price','$special_from_date','$special_to_date','$colli','$outer_colli','$open_colli_price','$outer_colli_price','$campaign_cost','$location')",__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select id from varer where varenr='$varenr'",__FILE__ . " linje " . __LINE__));
			return ("$r[id]");
		}
	}	else return ("databasen $db doesn't exist");
} # endfunc CreateItem	

function CreateOrder($s_id) {	
	list($s_id,$art,$konto_id,$kontakt,$email,$mail_fakt,$notes,$ean,$institution,$valuta,$sprog,$projekt,$ref,$ordredate,$levdate,$fakturadate,$nextfakt,$amount)=split(chr(9),$s_id);
	
	list($brugernavn,$db,$regnskabsaar,$regnstart,$regnslut,$charset)=split(chr(9),online($s_id));

	skriv_log($db,$brugernavn,"CreateOrder");

	$konto_id=$konto_id*1;
	$art=addslashes($art);
	$kontakt=addslashes($kontakt);
	$email=addslashes($email);
	$mail_fakt=addslashes($mail_fakt);
	$notes=addslashes($notes);
	$rabat=addslashes($rabat);
	$ean=addslashes($ean);
	$institution=addslashes($institution);
	$valuta=addslashes($valuta);
	$sprog=addslashes($sprog);
	$projekt=addslashes($projekt);
	$nextfakt=addslashes($nextfakt);
	
	$amount=$amount*1;
	$momssats=$momssats*1;
	if ($ordredate) {
	list($y,$m,$d)=split("-",$ordredate);
		if (!checkdate($m,$d,$y)) {
			return ("Error in orderdate: $ordredate");
			exit;
		}
	} else $ordredate=date("Y-m-d");
	if ($levdate) {
		list($y,$m,$d)=split("-",$levdate);
		if (!checkdate($m,$d,$y)) {
			return ("Error in deliverydate: $levdate");
			exit;
		}
	} else $levdate=date("Y-m-d");
	if ($fakturadate) {
	list($y,$m,$d)=split("-",$fakturadate);
		if (!checkdate($m,$d,$y)) {
			return ("Error in invoicedate: $fakturadate");
			exit;
		} else $fakturadate=date("Y-m-d");
	}
	
	if ($db){
		$x=0;
		if ($konto_id) {
			$kontoart=(substr($art,0,1));
			$r=db_fetch_array(db_select("select * from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
			$kontonr=$r['kontonr'];
			$firmanavn=addslashes($r['firmanavn']);
			$addr1=addslashes($r['addr1']);
			$addr2=addslashes($r['addr2']);
			$postnr=addslashes($r['postnr']);
			$bynavn=addslashes($r['bynavn']);
			$land=addslashes($r['land']);
			if (!$betalingsdage) $betalingsdage=$r['betalingsdage'];
			if (!$betalingsbet) $betalingsbet=$r['betalingsbet'];
			$cvrnr=addslashes($r['cvrnr']);
			$ean=addslashes($r['ean']);
			$institution=addslashes($r['institution']);
			if (!$email) $email=addslashes($r['email']);
			if (!$mail_fakt) $mail_fakt=$r['mailfakt'];
			if (!$kontakt) $kontakt=addslashes($r['kontakt']);
			$notes=addslashes($r['notes']);
			$gruppe=addslashes($r['gruppe']);
			$kontoansvarlig=addslashes($r['kontoansvarlig']);
			$r = db_fetch_array(db_select("select box1, box3, box4, box6 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
			$tmp=substr($r['box1'],1,1);
			if (!$rabat) $rabat=$r['box6']*1;
			if (!$sprog) $sprog=$r['box4'];
			if (!$valuta) $valuta=$r['box3'];
			$r = db_fetch_array(db_select("select box2 from grupper where art='SM' and kodenr='$tmp'",__FILE__ . " linje " . __LINE__));
			$momssats=$r['box2'];
			if (!$momssats) {
				return ("error in customer group setting");
			}
	
			$r=db_fetch_array(db_select("select MAX(ordrenr) as ordrenr from ordrer where art = 'DO'",__FILE__ . " linje " . __LINE__));
			
			
			$r=db_fetch_array(db_select("select MAX(ordrenr) as ordrenr from ordrer where art = 'DO'",__FILE__ . " linje " . __LINE__));
			$ordrenr=$r['ordrenr']+1;
			
			
			
			$dbtext="insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,email,art,cvrnr,status,ordredate,levdate,fakturadate,mail_fakt,notes,ean,institution,valuta,sprog,projekt,ref,betalingsdage,betalingsbet,sum,momssats) values ('$ordrenr','$konto_id','$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$email','DO','$cvrnr','1','$ordredate','$levdate','$fakturadate','$mail_fakt','$notes','$ean','$institution','$valuta','$sprog','$projekt','$ref','$betalingsdage','$betalingsbet','$amount','$momssats')";
			$fp=fopen("../temp/.ht_$db.log","a");
			fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$dbtext."\n");
			fclose($fp);
			
			db_modify($dbtext,__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select max(id) as id from ordrer where kontonr='$kontonr' and art = '$art'",__FILE__ . " linje " . __LINE__));
			$ordre_id=$r['id'];
			if ($levdate) db_modify("update ordrer set levdate = '$levdate' where id = '$ordre_id'",__FILE__ . " linje " . __LINE__); 
			return ($ordre_id);
		} else return ("missing acount ID");
	} else return ("SystemError database not found");
} #endfunc CreateOrder;


function AddOrderLine($s_id) {	

	list($s_id,$ordre_id,$varenr,$posnr,$antal,$beskrivelse,$pris,$rabat)=split(chr(9),$s_id);
	list($brugernavn,$db,$regnskabsaar,$regnstart,$regnslut,$charset)=split(chr(9),online($s_id));
	if ($db){	
		$x=0;
		$ordre_id=$ordre_id*1;
		$posnr=$posnr*1;
		if (!$posnr) $posnr=1;
		$antal=round($antal+0.0001,2);
		$varenr=addslashes($varenr);
		$beskrivelse=addslashes($beskrivelse);
		$pris=round($pris+0.0001,2);
		$rabat=round($rabat+0.0001,2);
		if ($ordre_id) {
			if ($varenr) {
				if ($r=db_fetch_array(db_select("select * from varer where varenr='$varenr'",__FILE__ . " linje " . __LINE__))) {
					$vare_id=$r['id'];
					if (!$beskrivelse) $beskrivelse=addslashes($r['beskrivelse']);
					db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,posnr,antal,beskrivelse,pris,rabat) values ('$ordre_id','$vare_id','$varenr','$posnr','$antal','$beskrivelse','$pris','$rabat')",__FILE__ . " linje " . __LINE__);
				} else {
					return ("Item no: $varenr does not exits");
					exit;
				} 
			} elseif ($beskrivelse) {
				db_modify("insert into ordrelinjer (ordre_id,posnr,beskrivelse) values ('$ordre_id','$posnr','$beskrivelse')",__FILE__ . " linje " . __LINE__);
			}
			$r=db_fetch_array(db_select("select max(id) as id from ordrelinjer where ordre_id='$ordre_id'",__FILE__ . " linje " . __LINE__));
			$linje_id=$r['id'];
			return ("$linje_id");
		} else return ("Missing order ID");
	} else return ("SystemError database not found");
}

		
function IndsaetKladdeLinje($s_id) {	

	global $bg;
	global $header;
	$modulnr=1;
	
	list($s_id,$kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$fakturanr,$belob,$momsfri,$ansat,$afd,$projekt,$valuta)=split(chr(9),$s_id);
	list($brugernavn,$db,$regnskabsaar,$regnstart,$regnslut,$charset)=split(chr(9),online($s_id));
	if ($db){	
		$x=0;
		$bilag=$bilag*1;
		$transdate=usdate($dato);
		$beskrivelse=addslashes($beskrivelse);
		$debet=$debet*1;
		$kredit=$kredit*1;
		$fakturanr=addslashes($fakturanr);
		$amount=usdecimal($belob);
		$momsfri=addslashes($momsfri);
		$projekt=$projekt*1;
			$afd=$afd*1;
		if ($ansat) $ansat_id=FindAnsatId($ansat);
		else $ansat_id='0';
		if ($valuta) $valuta_id=FindValutaId($valuta);
		else $valuta_id='0';
		db_modify("insert into kassekladde (kladde_id,bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,momsfri,ansat,afd,projekt,valuta) values ('$kladde_id','$bilag','$transdate','$beskrivelse','$d_type','$debet','$k_type','$kredit','$fakturanr','$amount','$momsfri','$ansat_id','$afd','$projekt','$valuta_id')",__FILE__ . " linje " . __LINE__);
		$next_bilag=$bilag+1;
		return $next_bilag;	
	} else return "IndsaetKladdeLinjeFejl";
}

function Online($s_id) {
	global $s_id;
	global $bg;
	global $header;
	global $db;
	global $brugernavn;
	
	
	$modulnr=1;
	include("../includes/connect.php");
	include("../includes/settings.php");
	include("../includes/online.php");
	$r = db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
	$start=trim($r['box2'])."-".trim($r['box1'])."-01";
	$slut=usdate("31-".trim($r['box3'])."-".trim($r['box4']))	; #usdate bruges for at sikre korrekt dato.
	return $brugernavn.chr(9).$db.chr(9).$regnaar.chr(9).$start.chr(9).$slut.chr(9).$charset;
}


function FindAnsatId($initialer) {	
	if ($r = db_fetch_array(db_select("select ansatte.id as id from from adresser, ansatte where adresser.art='S' and ansatte.konto_id=adresser.id and ansatte.initialer = '$initialer'",__FILE__ . " linje " . __LINE__))) {
		$ansat_id=$r['id'];
	} else $ansat_id='0';
	return ($ansat_id);
}

function FindValutaId($valuta) {
	if ($r = db_fetch_array(db_select("select kodenr from grupper where art='VK' and box1 = '$valuta'",__FILE__ . " linje " . __LINE__))) {
		$valuta_id=$r['kodenr'];
	} else $valuta_id='0';
	return ($valuta_id);
}

function skriv_log($db,$brugernavn,$tekst) {
		$fp=fopen("../temp/demo_228/webservice.log","a");
	fwrite($fp,"-- ".$brugernavn."| $tekst |".date("Y-m-d H:i:s").__FILE__ . " linje " . __LINE__."\n");
	fclose($fp);
}

?>