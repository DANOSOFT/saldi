<?php
// ------webservice/webservice.php-----------2.0.7----2009-05-20-13:40---
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
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$bg="nix";
$header="nix";
include("../includes/std_func.php");

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
$server->register("Logoff",array('s_id' => 'xsd:string'),array('return' => 'xsd:string'),'urn:s_id','urn:s_id#Logoff');

$HTTP_RAW_POST_DATA = isset($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : '';
$server->service($HTTP_RAW_POST_DATA);

function Login($regnskab) {	
	global $s_id;
	global $bg;
	global $header;
	$modulnr=1;
	
	list($regnskab,$brugernavn,$password)=split("::",$regnskab);
	$password=md5($password);
	$unixtime=date("U");
	include("../includes/db_query.php");
	include ("../includes/connect.php");
	db_modify("delete from online where  session_id = '$s_id'");
	if ($r = db_fetch_array(db_select("select * from regnskab where regnskab = '$regnskab'"))) {
		if ($db = trim($r['db'])) {
			$connection = db_connect ("'$sqhost'", "'$squser'", "'$sqpass'", "'$sqdb'");
			if (!$connection) die( "Unable to connect to SQL");
			db_modify("insert into online (session_id, brugernavn, db, dbuser) values ('$s_id', '$brugernavn', '$db', '$squser')");
			include ("../includes/online.php");
			if ($r = db_fetch_array(db_select("select * from brugere where brugernavn = '$brugernavn' and kode='$password'"))) {
				$rettigheder=trim($r['rettigheder']);
				$regnskabsaar=$r['regnskabsaar']*1;
				include ("../includes/connect.php");
				db_modify("update online set regnskabsaar='$regnskabsaar', rettigheder='$rettigheder' where session_id='$s_id'");
				return $s_id;
			} else {
				db_modify("delete from online where  session_id = '$s_id'");
				return "forkert password";
			}
		} else return "Ukendt regnskab1";
	} else return "Ukendt regnskab2";
}

function Logoff($s_id) {	
	global $bg;
	global $header;
	$modulnr=1;
	include("../includes/connect.php");
	db_modify("delete from online where session_id='$s_id'");
	return 'Farvel';
}	

function OpretKladde($s_id) {	
	
	global $bg;
	global $header;
	$modulnr=1;
	
	list($s_id,$kladdenote)=split("::",$s_id);
	$kladdenote=addslashes($kladdenote);
	include("../includes/connect.php");
	list($brugernavn,$db,$regnskabsaar,$regnstart,$regnslut)=split("::",online($s_id));
	if ($db){
		$x=0;
		$kladdedate=date("Y-m-d");
		$unixtime=date("U");
		db_modify("insert into kladdeliste (kladdedate, bogfort, oprettet_af, tidspkt, kladdenote, hvem) values ('$kladdedate', '-', '$brugernavn', '$unixtime', '$kladdenote', '$brugernavn')");
		$r=db_fetch_array(db_select("select id from kladdeliste where oprettet_af = '$brugernavn' and tidspkt = '$unixtime'"));
		$kladde_id=$r['id'];
		$r=db_fetch_array(db_select("select MAX(bilag) as bilag from kassekladde where transdate>='$regnstart' and transdate<='$regnslut'"));
		$next_bilag=$r['bilag']+1;
		db_modify("update kladdeliste set tidspkt='', hvem='' where id='$kladde_id'");
		return $kladde_id."::".$next_bilag;	
	} else return "OpretKladdeFejl";
}

function IndsaetKladdeLinje($s_id) {	

	global $bg;
	global $header;
	$modulnr=1;
	
	list($s_id,$kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$fakturanr,$belob,$momsfri,$ansat,$afd,$projekt,$valuta)=split("::",$s_id);
	list($brugernavn,$db,$regnskabsaar,$regnstart,$regnslut)=split("::",online($s_id));
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
		db_modify("insert into kassekladde (kladde_id,bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,momsfri,ansat,afd,projekt,valuta) values ('$kladde_id','$bilag','$transdate','$beskrivelse','$d_type','$debet','$k_type','$kredit','$fakturanr','$amount','$momsfri','$ansat_id','$afd','$projekt','$valuta_id')");
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
	include("../includes/online.php");
	$r = db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'"));
	$start=trim($r['box2'])."-".trim($r['box1'])."-01";
	$slut=usdate("31-".trim($r['box3'])."-".trim($r['box4']))	; #usdate bruges for at sikre korrekt dato.
	return $brugernavn."::".$db."::".$regnaar."::".$start."::".$slut;
}


function FindAnsatId($initialer) {	
	if ($r = db_fetch_array(db_select("select ansatte.id as id from from adresser, ansatte where adresser.art='S' and ansatte.konto_id=adresser.id and ansatte.initialer = '$initialer'"))) {
		$ansat_id=$r['id'];
	} else $ansat_id='0';
	return ($ansat_id);
}

function FindValutaId($valuta) {
	if ($r = db_fetch_array(db_select("select kodenr from grupper where art='VK' and box1 = '$valuta'"))) {
		$valuta_id=$r['kodenr'];
	} else $valuta_id='0';
	return ($valuta_id);
}

?>
