<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/ordreliste.php -----patch 4.1.1 ----2025-06-05--------------
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------

// 20121004 Fjernet email fra $selectfelter og indsat søgerutine til emails - søg 20121004
// 20121017 Tilføjet link til oioubl import for automatisk ordreoprettelse - søg 20121017
// 20141106 Ændret søgning på firmanavn så kun det relevante firmanavn kommer med ved søgning. 20141106
// 20141107 tilføjet <center>
// 20150308 Tilføjet "and art like 'D%' and status < '3'" da den fjernede tidspkt på afsluttede ordrer. 20150308
// 20160901	Tilføjet array tekstfelter til søgning med wildcard
// 20161217	PHR Tilføjet vis_lagerstatus. Søg vis_lagerstatus: 
// 20170209	PHR Tilføjet mulighed for at slette ordrer direkte fra liste.
// 20170520	PHR fjernet valutaberegning på kostpriser da det gav forlert DB/DG
// 20170601 PHR	Delvis tilbageført ændringer fra 20141106 da alle fakturaer ikke kommer med ved opslag fra debitorkort 20170601
// 20180418	PHR	Mulighed for at gemme ordre ved klik på kundeordrenr. Søg $gem_id
// 20180907	PHR Tilføjet 'Hent fra shop'
// 20181127 PHR Udlignet sættes til 1 hvis ingen openpost.
// 20181128	PHR Tilføjet kundegruppe som søgefelt
// 20181217 msc Rettet fejl & forbedret design
// 20190107 MSC Rettet overskift fra Kunder - Åbne ordrer til Åbne ordrer
// 20190116 MSC - Rettet ny ordre knap til ny & tilføjet ny knap under Fakturede ordrer.
// 20190213 MSC - Rettet topmenu design til og isset fejl
// 20190215 MSC - Rettet topmenu design til
// 20190320 PHR - Varius improvements in selections related to 'sum_m_moms'
// 20190429 PHR - Removed '\n' from $overlibTxt as ir destroys 'overlib';
// 20190703 PHR - Users can now choose whether they want dropdown. Search $dropDown
// 20190704 RG (Rune Grysbæk) Mysqli implementation 
// 20191008 PHR Added 'ref' to 'tekstfelter' as search in 'ref' did not give any result.   
// 20191022 PHR Added 'isset($checked[$c]) &&' to avoid notice in log.
// 20210318 LOE Translated these text to English #20210318
// 20210319 LOE added this block of code for switching between two selected languages #20210319
// 20210323 LOE Did some general translating and using dynmic variables via findteskt func. for ordrer and tibuld #20210323
// 20210325 LOE Added these variables #20210325
// 20210328 PHR Definition of various variables #20210328
// 20210527 LOE Added these variables 
// 20210623	LOE Created select_valg function to select each box from grupper table available for global usage
// 20110620 MSC Implementing new top menu design
// 20110708 MSC Implementing new top menu design
// 20110713 MSC Implementing new top menu design 
// 20210714 LOE Started translation for JavaScript alerts and title texts 
// 20210715 LOE More translations for the title and confirm texts
// 20210716 MSC Implementing new top menu design & moving hard coded styling, from old design, in standard.css file
// 20210719 LOE Added a text for this title ..unset class error commented out...class variable used without being set
// 20210720 MSC Implementing new top menu design 
// 20210803 MSC Implementing new top menu design 
// 20210812 MSC Implementing new top menu design 
// 20210817 Updated some blocks of codes using translated variables 
// 20210818 This part of the code added ; ordre_id was not set now it is set
// 20210902 MSC - Implementing new design
// 20210906 MSC - Implementing new design
// 20210907 MSC - Implementing new design
// 20211018 LOE - Fixed some bugs
// 20211102 MSC - Implementing new design
// 20220210 PHR - Some cleanup 
// 20220210 PHR - Added queries in function select_valg
// 20220301 PHR - Added "$valg == 'faktura' ||" as invoiced orders should not be locked
// 20220619 PHR Removed misplaced ';' from findtekst(386...
// 20220824 PHR Changed 'hent_ordrer' to wait 30 sec between fetches
// 20230320 MSC - Fixed so tilbud icon would show up, if setting is on and fixed footer in tilbud section
// 20230323 PBLM Fixed minor error
// 20230621 PHR missing $sprog_id
// 20230719 LOE Made some minor modification
// 20230829 MSC - Copy pasted new design into code
// 20231113 PHR Added search for 'Land'
// 20231206 PHR PHP 8 error in 'genberegn'
// 20240528 PHR Added $_SESSION['debitorId']
// 20240815 PHR- $title 
// 20250828 PHR error in translation of 'tilbud'
// 20240906 phr Moved $debitorId to settings as 20240528 didnt work with open orders ??
// 20240106 PBLM Added box5 on line 1187 for the extra api client
// 20250415 LOE Updated some variables using if_isset
// 20250605	PHR Removed konto_id from href
// 26062025 PBLM Added link to the whole line almost

#ob_start();
@session_start();
$s_id=session_id();
print "
<script LANGUAGE=\"JavaScript\">
<!--
function MasseFakt(tekst)
{
	var agree = confirm(tekst);
	if (agree)
		return true ;
	else
    return false ;
}
// -->
</script>
";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/overlib.js\"></script>";



$css="../css/std.css";
global $sprog_id;
$modulnr=5;
$api_encode=NULL;
$check_all=$checked=$cols=NULL;
$dk_dg=NULL; 
$fakturadatoer=$fakturanumre=$firma=$firmanavn=$firmanavn_ant=NULL; 
$genfakt=$genfaktdatoer=$genfakturer=NULL;
$hreftext=$hurtigfakt=NULL; 
$ialt_m_moms=NULL;
$ialt_kostpris=NULL;
$konto_id=$kontonumre=NULL; 
$lev_datoer=$linjebg=NULL; 
$ny_sort=NULL;
$ordreantal=$ordredatoer=$ordrenumre=NULL;
$readonly=$ref[0]=NULL;
$shop_ordre_id=$summer=NULL;
$totalkost=$tr_title=NULL; 
$uncheck_all=$understreg=NULL;
$vis_projekt=$vis_ret_next=$who=NULL;
$tidspkt=0;
$timestamp = date('U');
$find=array(NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
$padding2=$padding=$padding1_5=null; #20211018
include("../includes/connect.php");
include("../includes/std_func.php");
$title=findtekst('1201|Ordreliste • Kunder', $sprog_id);
include("../includes/online.php");
include("../includes/udvaelg.php");
include("../includes/row-hover-style-with-links.js.php");

# >> Date picker scripts <<
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/jquery-3.6.4.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/moment.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/daterangepicker.min.js\" defer></script>";
print '<link rel="stylesheet" type="text/css" href="../css/daterangepicker.css" />';
include("../includes/datepkr.php");

global $color;
 //	
#print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Ordreliste - Kunder</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";

$aa = findtekst(360, $sprog_id);
$firmanavn1 =ucfirst(str_replace(' ','_', $aa));
$bb = findtekst(107, $sprog_id);
$ordrer1 =strtolower(str_replace(' ','_', $bb));
$cc = findtekst(893, $sprog_id);
$faktura1=strtolower(str_replace(' ','_', $cc));
$dd = findtekst(812, $sprog_id);
$tilbud1=strtolower(str_replace(' ','_', $dd));
$ee = findtekst(892,$sprog_id);
$beskrivelse= strtolower(str_replace(' ','_', $ee));//20210527

$ff = findtekst(500,$sprog_id);
$ordrenr1= strtolower(str_replace(' ','_', $ff));
$gg = findtekst(881,$sprog_id);
$ordredate1 = strtolower(str_replace(' ','_', $gg));
$hh = findtekst(804,$sprog_id);
$kontonr1 = strtolower(str_replace(' ','_', $hh));
$ii = findtekst(882,$sprog_id);
$fakturanr1  = strtolower(str_replace(' ','_', $ii));
$jj = findtekst(883,$sprog_id);
$fakturadate1 = strtolower(str_replace(' ','_', $jj));
$kk = findtekst(891,$sprog_id);
$nextfakt1 = strtolower(str_replace(' ','_', $kk));






 #if($h1= db_fetch_array(db_select("select*from grupper where art='OLV' and kode='$valg' and kodenr = '$bruger_id' ",__FILE__ . " linje " . __LINE__))) $q =$h1['box3']; #2021/05/31

 $id = if_isset($_GET, NULL, 'id');
 $konto_id = if_isset($_GET, NULL, 'konto_id'); 
if ($konto_id) {
	$qtxt = "update settings set var_value = '$konto_id' where ";
	$qtxt.= "var_name = 'debitorId' and var_grp = 'debitor' and user_id = '$bruger_id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
} else {
	$qtxt = "select var_value from settings where var_name = 'debitorId' and var_grp = 'debitor' and user_id = '$bruger_id'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))$konto_id  = $r['var_value'];  
#	(isset($_SESSION['debitorId']) && $_SESSION['debitorId']) $konto_id  = $_SESSION['debitorId'];
}
if ($konto_id) $returside = "../debitor/debitorkort.php?id=$konto_id";
else $returside=if_isset($_GET,NULL,'returside');
if (!$returside) $returside = '../index/menu.php';
$valg = strtolower(if_isset($_GET, NULL, 'valg'));
$sort = if_isset($_GET, NULL, 'sort');
$nysort = if_isset($_GET, NULL, 'nysort');
$kontoid = if_isset($_GET, NULL, 'kontoid');
$genberegn = if_isset($_GET, NULL, 'genberegn');
$start = if_isset($_GET, NULL, 'start');
if(empty($start)){$start=0;} #20210817
$vis_lagerstatus = if_isset($_GET, NULL, 'vis_lagerstatus');
$gem = if_isset($_GET, NULL, 'gem');
$gem_id = if_isset($_GET, NULL, 'gem_id');
$download = if_isset($_GET, NULL, 'download');
$hent_nu = if_isset($_GET, NULL, 'hent_nu');
$shop_ordre_id = if_isset($_GET, NULL, 'shop_ordre_id');
$shop_faktura = if_isset($_GET, NULL, 'shop_faktura');
# if ($hent_nu && file_exists("../temp/$db/shoptidspkt.txt")) unlink ("../temp/$db/shoptidspkt.txt");

if (!$returside && $konto_id && !$popup) {
	$returside="debitorkort.php?id=$konto_id";
}
if (isset($_GET['valg'])) setcookie("saldi_ordreliste","$valg");
else $valg = if_isset($_COOKIE,NULL,'saldi_ordreliste');

$r2=db_fetch_array(db_select("select max(id) as id from grupper",__FILE__ . " linje " . __LINE__));

if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '3' and box4='on'",__FILE__ . " linje " . __LINE__))) $hurtigfakt='on';
if ($valg=="tilbud" && $hurtigfakt) $valg="ordrer"; //20210323
if (!$valg) $valg="ordrer";//
$tjek=array("tilbud","ordrer","faktura","pbs");//
//if (!in_array($valg,$tjek)) $valg='ordrer';
if (!in_array($valg,$tjek)) $valg="ordrer";
#if ($valg=="ordrer" && $sort=="fakturanr") $sort="ordrenr";
if ($nysort=='sum_m_moms') $nysort='sum'; 
$sort=str_replace("ordrer.","",$sort);
if ($sort && $nysort==$sort) $sort=$sort." desc";
elseif ($nysort) $sort=$nysort;
db_modify("update ordrer set betalt = '0' where betalt is NULL",__FILE__ . " linje " . __LINE__);

$r2=db_fetch_array(db_select("select max(id) as id from grupper",__FILE__ . " linje " . __LINE__));

if ($r=db_fetch_array(db_select("select id from adresser where art = 'S' and pbs_nr > '0'",__FILE__ . " linje " . __LINE__))) {
 $pbs=1;
} else $pbs=0;


$box5 = select_valg("$valg", "box5");

$box3 = select_valg("$valg", "box3");
$box4 = select_valg("$valg", "box4");
$box6 = select_valg("$valg", "box6");

$qtxt = "select id from grupper where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$qtxt = "insert into grupper (beskrivelse,kode,kodenr,art,box2,box3,box4,box5,box6,box7) values ";
	$qtxt.= "('$beskrivelse','$valg','$bruger_id','OLV','$returside','$box3','$box4','$box5','$box6','100')"; 
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
} else {
		$qtxt = "select * from grupper where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'"; #20210623
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$box6 = $r['box6'];
			$c =explode(",",$box6);
			$c = array_map('trim', $c);
			if(!in_array(trim("$firmanavn1"), $c)){
				$qtxt = "update grupper set beskrivelse='$beskrivelse',kode='$valg',kodenr='$bruger_id',box2='$returside',";
				$qtxt.= "box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='100' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
			}
		} else {
			$qtxt ="update grupper set box3='$box3',box6='$box6' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$qtxt = "select box2,box7,box8,box9 from grupper where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
 		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
		if (!$returside) {
			$returside=$r['box2'];
			if (strstr($returside,"debitorkort.php?id=") && !$konto_id) {
			list($tmp,$konto_id)=explode("=",$returside);
		}
	}
	$linjeantal=$r['box7'];
	if (!$sort) $sort=$r['box8'];
	$find=explode("\n",$r['box9']);
}
if (!$returside) {
#	$r=db_fetch_array(db_select("select box2,box7 from grupper where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__)); 
#	$returside=$r['box2'];
#	$linjeantal=$r['box7'];
	if ($popup) $returside= "../includes/luk.php";          
	else $returside= "../index/menu.php";
} elseif (!$popup && $returside=="../includes/luk.php") $returside="../index/menu.php";
$qtxt = "update grupper set box2 = '$returside', box8 = '$sort' where art = 'OLV' and kode = '$valg' and kodenr = '$bruger_id'"; 
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
if (!$popup) {
	$qtxt = "update ordrer set hvem='', tidspkt='' where hvem='$brugernavn' and art like 'D%' and status < '3'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__); #20150308
}		
$tidspkt=date("U");
 
#if (isset($_POST)) {
if ($submit=if_isset($_POST,NULL,'submit')) {
	if (strstr($submit, "Genfaktur")) $submit="Genfakturer";
	$find=if_isset($_POST['find']);
	$valg=if_isset($_POST['valg']);
	$sort = if_isset($_POST['sort']);
	$nysort = if_isset($_POST['nysort']);
	$firma=if_isset($_POST['firma']);
	$kontoid=if_isset($_POST['kontoid']);
	$firmanavn_ant=if_isset($_POST['firmanavn_antal']);
} elseif (isset($_POST["clear"])) {
    // Clear all search criteria
    $find = array();
    $konto_id = NULL;
    $udvaelg = NULL;
    $kontoid = NULL;
    $firma = NULL;
    $firmanavn_ant = NULL;
    
    // Clear the stored search criteria in the database
    $qtxt = "update grupper set box9='' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
    db_modify($qtxt,__FILE__ . " linje " . __LINE__);
    
    // Also clear the settings for debitorId
    $qtxt = "update settings set var_value = NULL where var_name = 'debitorId' and var_grp = 'debitor' and user_id = '$bruger_id'";
    db_modify($qtxt,__FILE__ . " linje " . __LINE__);
    
    // Redirect to the same page with only the view type preserved
    print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?valg=$valg\">";
    exit;
}


/* 20141106
if (($firma)&&($firmanavn_ant>0)) {
	for ($x=1; $x<=$firmanavn_ant; $x++) {
		$tmp="firmanavn$x";
		if ($firma==$_POST[$tmp]) {
			$tmp="konto_id$x";
			$kontoid=$_POST[$tmp];
		}
	}
}
elseif ($firmanavn_ant>0) {$kontoid='';}
*/
if (!$valg) $valg = "ordrer";//20210323
if (!$sort) $sort='ordrenr desc';

$sort=str_replace("ordrer.","",$sort); #2008.02.05
$sortering=$sort;

if ($valg!="faktura") {//20210323
#	$fakturanumre='';
#	$fakturadatoer='';
	$genfakturer='';
}
if ($valg=="tilbud") {$status="ordrer.status = 0";}
elseif ($valg=="faktura") {$status="ordrer.status >= 3";}
else {$status="(ordrer.status = 1 or ordrer.status = 2)";}

if ($r=db_fetch_array(db_select("select distinct id from ordrer where projekt > '0' and $status",__FILE__ . " linje " . __LINE__))) $vis_projekt='on';


$ordre_id = if_isset($_POST,NULL,'ordre_id'); 
$checked = if_isset($_POST,NULL,'checked');




$slet_valgte=if_isset($_POST,NULL,'slet_valgte'); 
#if ($slet_valgte=='Slet') {
if ($slet_valgte==findtekst(1099, $sprog_id)) { #20210817 applying the translated values for delete here
	
	include("../includes/ordrefunc.php");
	$y=0;
	for ($x=0; $x<count($ordre_id); $x++){
		$c=$ordre_id[$x];
		if ($checked[$c]=="on") {
			slet_ordre($ordre_id[$x]);
		}
	}
}
$y=0; $alert1 = findtekst(1418, $sprog_id); #20210714
if ($submit==findtekst(880, $sprog_id) || $submit=="Send mails"){ #20210817 Added translated variable 
	for ($x=0; $x<count($ordre_id); $x++){
		$c=$ordre_id[$x];
		if ($checked[$c]=="on") {
			$y++;
			if (!$udskriv) $udskriv=$ordre_id[$x];  
			else $udskriv=$udskriv.",".$ordre_id[$x];
		}
	}
	if ($y>0) {
		if ($submit==findtekst(880, $sprog_id)) {
			print "<script>window.open('formularprint.php?id=-1&ordre_antal=$y&skriv=$udskriv&formular=4&udskriv_til=PDF&returside=../includes/luk.php')</script>";
		} elseif ($submit=="Send mails") {
			print "<script>window.open('formularprint.php?id=-1&ordre_antal=$y&skriv=$udskriv&formular=4&udskriv_til=email');\"></script>";
		}
	}
	
	else print "<BODY onLoad=\"javascript:alert('$alert1')\">";
}
if ($submit==findtekst(576, $sprog_id)){ #20210817 Added translated variable 
	for ($x=0; $x<count($ordre_id); $x++){
		$c=$ordre_id[$x];
		if ($checked[$c]=="on") {
			$y++;
			if (!$udskriv) $udskriv=$ordre_id[$x];  
			else $udskriv=$udskriv.",".$ordre_id[$x];
		}
	}
	if ($y>0) {
		print "<script>window.open('formularprint.php?id=-1&ordre_antal=$y&skriv=$udskriv&formular=3&udskriv_til=PDF&returside=../includes/luk.php')</script>";
	}
	
	else print "<BODY onLoad=\"javascript:alert('$alert1')\">";
}
if (isset($_POST['check'])||isset($_POST['uncheck'])) {
	if (isset($_POST['check'])) $check_all='on';
	else $uncheck_all='on';
}
if ($submit=="Genfakturer" || $submit==findtekst(1206, $sprog_id)){ #20210817
	for ($x=0; $x<count($ordre_id); $x++){
		$c=$ordre_id[$x];
		if (isset($checked[$c]) && $checked[$c]=="on") {
			$y++;
			if (!$genfakt) $genfakt=$c;
			else $genfakt=$genfakt.",".$c;
		}
	} $alert2 = findtekst(1419, $sprog_id);
	if ($y>0) {
		if ($submit==findtekst(1206, $sprog_id)) print "<meta http-equiv=\"refresh\" content=\"0;URL=ret_genfakt.php?ordreliste=$genfakt\">";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=genfakturer.php?id=-1&ordre_antal=$y&genfakt=$genfakt\">";
		exit;	
	}
	else print "<BODY onLoad=\"javascript:alert('$alert2')\">";
} 
if ($menu=='T') include_once 'ordLstIncludes/topMenu.php';
elseif ($menu=='S') include_once 'ordLstIncludes/topLine.php';
else include_once 'ordLstIncludes/oldTopLine.php';

////// Tutorial //////

$steps = array();
$steps[] = array(
	"selector" => "#ordrer",
	"content" => "Her ser du en liste af alle dine ordrer."
);
$steps[] = array(
    "selector" => "#ny",
    "content" => "For at oprette en ny ordre, klik her."
);

$steps[] = array(
    "selector" => "#visning",
    "content" => "For at ændre, hvad der vises i oversigten, klik her."
);

include(__DIR__ . "/../includes/tutorial.php");
create_tutorial("order-list", $steps);

////// Tutorial end //////

$qtxt="select box3,box4,box5,box6,box10 from grupper where art = 'OLV' and kodenr = '$bruger_id' and kode='$valg'";
$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$vis_felt=explode(",",$r['box3']);
$feltbredde=explode(",",$r['box4']);
$justering=explode(",",$r['box5']);
$feltnavn=explode(",",$r['box6']);
$vis_feltantal=count($vis_felt);
if ($r['box10']) $dropDown=explode(",",$r['box10']);
else {
$selectfelter=array("firmanavn","konto_id","bynavn","land","lev_navn","lev_addr1","lev_addr2","lev_postnr","lev_bynavn","lev_kontakt","ean","institution","betalingsbet","betalingsdage","art","momssats","ref","betalt","valuta","sprog","mail_fakt","pbs","mail","mail_cc","mail_bcc","mail_subj","mail_text","udskriv_til","kundegruppe");
	for ($i=0;$i<$vis_feltantal;$i++) {
		(in_array(strtolower($vis_felt[$i]),$selectfelter))?$dropDown[$i]='on':$dropDown[$i]='';
		($i<1)?$box10=$dropDown[$i]:$box10.=','.$dropDown[$i];
	}
	$qtxt="update grupper set box10='$box10' where art = 'OLV' and kodenr = '$bruger_id' and kode='$valg'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
}
$tekstfelter=array("cvrnr","email","kontakt","firmanavn","addr1","addr2","ref"); #20160901
$gem_fra=$gem_til=NULL;
if (in_array('kundeordnr',$vis_felt)) {
	for ($i=0;$i<count($vis_felt);$i++) {
		if ($vis_felt[$i]=='kundeordnr') {
			if (strpos($find[$i],":")) list($gem_fra,$gem_til)=explode(":",$find[$i]);
			elseif ($find) $gem_fra=$find[$i];
		}
	}
	if ($gem_fra && $gem_til && $gem_til-$gem_fra > 10) $gem_fra=$gem_til=NULL;
}

####################################################################################
$udvaelg=NULL;
$tmp=trim($find[0]);
for ($x=1;$x<$vis_feltantal;$x++) $tmp=$tmp."\n".trim(if_isset($find[$x]));
$tmp=db_escape_string($tmp);
$qtxt="update grupper set box9='$tmp' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
for ($x=0;$x<$vis_feltantal;$x++) {
	if (!isset($feltbredde[$x]) || !$feltbredde[$x]) $feltbredde[$x]=100;
	if ($feltbredde[$x]<=10) $feltbredde[$x]*=10;
#	if (!$feltbredde[$x]) $feltbredde[$x]=100;
	if (!isset($find[$x]) || $find[$x]=="-") $find[$x]=NULL;
	else $find[$x]=db_escape_string(trim($find[$x]));
# 20141106
	if ($konto_id && $find[$x] && ($vis_felt[$x]=="firmanavn" || $vis_felt[$x]=="kontonr") && !strpos("$find[$x]",":")) { #Tilføjet '$konto_id &&' 
		$d=0;
		$tmplist=array();
		if ($vis_felt[$x]=="firmanavn" && !$konto_id) $q=db_select("select distinct(konto_id) as konto_id from ordrer where firmanavn = '$find[$x]'",__FILE__ . " linje " . __LINE__);
		elseif(!$konto_id) {
			$q=db_select("select distinct(konto_id) as konto_id from ordrer where kontonr = '$find[$x]'",__FILE__ . " linje " . __LINE__);
		}
		while ($r=db_fetch_array($q)) {
			$d++;
			$tmpliste[$d]=$r['konto_id'];
		}
		if ($d) {
			$tmp=$d;
				$udvaelg.="and(ordrer.konto_id='$tmpliste[1]'";
			for($d=2;$d<=$tmp;$d++) {
				$udvaelg.=" or ordrer.konto_id='$tmpliste[$d]'";
			} 
			$udvaelg.=")";
		} elseif (!$konto_id) $udvaelg.="and ordrer.konto_id='0'"; 
	} else {
		// Get the field name from the $vis_felt array
		$tmp=$vis_felt[$x];

		// If the field is 'ordrenr' (order number) and there's a search value
		if ($tmp=='ordrenr' && $find[$x]) {
		    // Limit the length of the order number to 10 characters if it's longer
			if (strlen($find[$x])>=11) $find[$x]=substr($find[$x],0,10);
			$find[$x]*=1;
		}

		// If the field is 'kontonr' (account number) and there's a search value
		if ($tmp=="kontonr" && $find[$x]) {
			$find[$x]*=1;
		}

		// If the field is 'sum_m_moms' (sum including VAT) and there's a search value
		if ($vis_felt[$x]=='sum_m_moms' && $find[$x]) {
			// If the search value contains a range (e.g., a:b)
			if ($vis_felt[$x]=='sum_m_moms' && strpos($find[$x],':')) {
				// Split the range into two values and create the serarch pattern
				list($a,$b) = explode(':',$find[$x]);
				$udvaelg=$udvaelg." and ordrer.sum+ordrer.moms >= '". usdecimal($a) ."' and ordrer.sum+ordrer.moms <= '". usdecimal($b) ."'";
			} else $udvaelg=$udvaelg." and ordrer.sum+ordrer.moms='". usdecimal($find[$x]). "'";

		// If the field is 'kundegruppe' (customer group) and the value is numeric
		} elseif ($vis_felt[$x]=='kundegruppe' && is_numeric($find[$x])) {
			$udvaelg=$udvaelg." and adresser.gruppe='$find[$x]'";

		// If it's a dropdown field and there's a search value
		} elseif ($dropDown[$x] && ($find[$x]||$find[$x]=="0")) {
			$udvaelg=$udvaelg." and ordrer.$tmp='$find[$x]'";

		// If it's a date field or 'nextfakt' (next invoice date) field
		} elseif ((strpos($vis_felt[$x],"date") || $vis_felt[$x]=="nextfakt") && ($find[$x]||$find[$x]=="0")) {
			// If the field is 'nextfakt', set a flag that actiaves the "Genfaktuer" button later in this file
			if ($vis_felt[$x]=="nextfakt") $genfakturer="1";
			$tmp2="ordrer.".$tmp."";
			$udvaelg=$udvaelg.udvaelg($find[$x],$tmp2, 'DATO');

		// If the field is 'sum' and there's a search value
		} elseif ($vis_felt[$x]=="sum" && ($find[$x]||$find[$x]=="0")) {
			$tmp2="ordrer.".$tmp."";
			$udvaelg=$udvaelg.udvaelg($find[$x],$tmp2, 'BELOB');
		
		// If the field is 'betalt' (paid) and there's a search value
		} elseif ($vis_felt[$x]=="betalt" && ($find[$x]||$find[$x]=="0")) {
			$tmp2="ordrer.".$tmp."";
			$udvaelg=$udvaelg.udvaelg($find[$x],$tmp2, 'BELOB');

		// If the field is a text field (as defined in $tekstfelter above) and there's a search value
		} elseif (in_array($vis_felt[$x],$tekstfelter) && $find[$x]) { #20121004 20160901
			$tmp2="ordrer.".$tmp."";
			$udvaelg=$udvaelg.udvaelg($find[$x],$tmp2,'TEXT');

		// If the field is 'land' (country) and there's a search value
		} elseif ($find[$x] && $vis_felt[$x] == 'land') {
			$tmp2="ordrer.".strtolower($tmp)."";
			$udvaelg=$udvaelg.udvaelg($find[$x],$tmp2, 'TEXT');

		// If the field is fakturanr
		} elseif ($vis_felt[$x]=='fakturanr' && $find[$x]) {
			$tmp2="ordrer.".strtolower($tmp)."";
			// If the search value contains a range (e.g., a:b)
			if (strpos($find[$x],':')) {
				// Split the range into two values and create the serarch pattern
				list($a,$b) = explode(':',$find[$x]);
				$udvaelg=$udvaelg." and ordrer.fakturanr >= '". usdecimal($a) ."' and ordrer.fakturanr <= '". usdecimal($b) ."'";
			} else $udvaelg.=udvaelg($find[$x],$tmp2, 'TEXT');
		
		// If there's any other field with a search value
		} elseif ($find[$x]||$find[$x]=="0") {
			$tmp2="ordrer.".$tmp."";
			$udvaelg=$udvaelg.udvaelg($find[$x],$tmp2, 'NR');
		}
	}
}
if ($udvaelg) $linjeantal=10000;
if (strstr($sortering,'fakturanr')) {
	if ($db_type=='mysql' or $db_type=='mysqli') { #RG_mysqli
		$sortering=str_replace("fakturanr","CAST(ordrer.fakturanr AS SIGNED)",$sortering); 
	} else $sortering=str_replace("fakturanr","to_number(textcat('0',ordrer.fakturanr),text(99999999))",$sortering);
} else $sortering="ordrer.".$sortering;
$sortering=str_replace("ordrer.kundegruppe","adresser.gruppe",$sortering);
# if (strstr($udvaelg,'fakturanr')) $udvaelg=str_replace("fakturanr","fakturanr::varchar::numeric",$udvaelg);
$ordreliste="";

if ($valg=="tilbud") $status="ordrer.status < 1";
elseif ($valg=="ordrer" && $hurtigfakt) $status="ordrer.status < 3"; 
elseif ($valg=="ordrer") $status="(ordrer.status = 1 or ordrer.status = 2)"; 
else $status="ordrer.status >= 3";

$ialt=0;
$lnr=0;
if (!$linjeantal) $linjeantal=100;
#$start=0;
$slut=$start+$linjeantal;
$ordreantal=0;

if ($konto_id) $udvaelg=$udvaelg."and konto_id=$konto_id";
$qtxt="select count(ordrer.id) as antal from ordrer";
if (strstr($udvaelg,'adresser')) $qtxt.=",adresser";
$qtxt.=" where (ordrer.art = 'DO' or ordrer.art = 'DK' or (ordrer.art = 'PO' and ordrer.konto_id > '0')) and $status $udvaelg";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$antal=$r['antal'];
	


print " </td></tr>\n<tr><td align=center valign=top>";
#print "<table border=0 valign='top' $class><tbody>\n<tr valign=top align=center>"; 
if ($menu=='T') {
	print "<table border=0 valign='top' class='dataTable' width='100%'><thead>\n<tr valign=top align=center>"; #20210719
} else {
	print "<table border=0 valign='top' width='100%'><tbody>\n<tr valign=top align=center>"; #20210719
}
if ($start>0) {
	$tmp=$start-$linjeantal;
	if ($tmp<0) $tmp=0;
		print "<td class='imgNoTextDeco' style='padding-top: 20px;'><a href=ordreliste.php?start=$tmp&valg=$valg&konto_id=$konto_id><img class='imgInvert imgFade' src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
} else print "<td></td>";
# if ($valg=='tilbud') {
for ($x=0;$x<$vis_feltantal;$x++) {
		if (!$feltbredde[$x]) $feltbredde[$x]*="100";
		elseif ($feltbredde[$x]<15) $feltbredde[$x]*="10";
	if ($feltbredde[$x]) {
		$width="width=\"$feltbredde[$x]px\"";
	} else $width="";
	print "<td  align=$justering[$x] $width style=\"$border solid $bgcolor; padding-top: 20px;\"><b><a href='ordreliste.php?nysort=$vis_felt[$x]&sort=$sort&valg=$valg'>$feltnavn[$x]</b></td>\n";
}
$tmp=$start+$linjeantal;
if ($antal>$slut) { 
	print "<td align=right class='imgNoTextDeco' style='padding-top: 20px;'><a href='ordreliste.php?start=$tmp&valg=$valg&konto_id=$konto_id'><img class='imgInvert imgFade' src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
} else { 
	print "<td align=right class='imgNoTextDeco' style='padding-top: 20px;'></td>";
}
print "</tr>\n";

#################################### Sogefelter ##########################################

print "<form name=\"sogefelter\" action=\"ordreliste.php?konto_id=$konto_id&sort=$sort\" method=\"post\">\n";
print "<input type=hidden name=valg value=$valg>\n";
print "<input type=hidden name=sort value='$ny_sort'>\n";
print "<input type=hidden name=nysort value='$sort'>\n";
print "<input type=hidden name=kontoid value=$kontoid>\n";

# Show date picker
$show_date_pkr = get_settings_value("datepicker", "personlige", "on");
$script = "";
print "<tr><td></td>";
#if ($valg=='tilbud') {
	for ($x=0; $x<$vis_feltantal; $x++) {
		# Hent feltbredde
		if (!$feltbredde[$x]) $feltbredde[$x]*="100";
		elseif ($feltbredde[$x]<15) $feltbredde[$x]*="10";
		if ($feltbredde[$x]) {
			$width="width:$feltbredde[$x]px";
		} else $width="";

		# Hent beskrivelser
		if ($konto_id && ($vis_felt[$x]=="kontonr" || $vis_felt[$x]=="firmanavn")) $span = 'Listen er &aring;bnet fra debitorkort - s&oslash;gefelt deaktiveret';
		elseif (strpos($vis_felt[$x],"nr")) $span = 'Skriv et nummer eller skriv to adskilt af kolon (f.eks 345:350)';
		elseif (strpos($vis_felt[$x],"date") || $vis_felt[$x]=="nextfakt") $span = '';
		elseif ($vis_felt[$x]=="sum") $span = 'Skriv et beb&oslash;b eller to adskilt af kolon (f.eks 525,25:525,50)';
		else $span=''; 

		# Print the input fields
		print "<td align=$justering[$x]><span title= '$span'>";
		if ($konto_id && ($vis_felt[$x]=="kontonr" || $vis_felt[$x]=="firmanavn")) {
			$r=db_fetch_array(db_select("select $vis_felt[$x] as tmp from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
			print "<label class='checkContainerOrdreliste'><input class=\"inputbox\" type=text readonly=$readonly style=\"text-align:$justering[$x];$width;\" name=find[$x] value=\"$r[tmp]\"><span class='checkmarkOrdreliste'></span></label>";
		
		} elseif ($vis_felt[$x]=="kundegruppe") {
			$r=db_fetch_array(db_select("select distinct(gruppe) as tmp from adresser where art='D'",__FILE__ . " linje " . __LINE__));
			print "<label class='checkContainerOrdreliste'><input class=\"inputbox\" type=text style=\"text-align:$justering[$x];$width;\" name=find[$x] value=\"$find[$x]\"><span class='checkmarkOrdreliste'></span></label>";

		} elseif ($vis_felt[$x]=="ordredate" || $vis_felt[$x]=="fakturadate" || $vis_felt[$x]=="levdate" || $vis_felt[$x]=="nextfakt") {
			print "<input type='text' name=find[$x] value='$find[$x]' id='dateout$x' hidden></input>";
			date_picker($find[$x], "find[$x]", "sogefelter", $justering[$x], $width);

		} elseif ($dropDown[$x]) {
			$tmp=$vis_felt[$x];
			print "<SELECT NAME=\"find[$x]\" class=\"inputbox\" style=\"$width;\">";
			if ($valg=="tilbud") $status = "ordrer.status < 1";
			elseif ($valg=="ordrer" && $hurtigfakt) $status  = "ordrer.status <= 2";
			elseif ($valg=="ordrer") $status  = "(ordrer.status >= 1 and ordrer.status <= 2)";
			else $status  = "ordrer.status >= 3";
			$tmp = str_replace('sum_m_moms','sum',$tmp); 
			$qtxt="select distinct($tmp) from ordrer where (art = 'DO' or art = 'DK' or (art = 'PO' and konto_id > '0')) and $status order by $tmp";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			print "<option>".stripslashes($find[$x])."</option>";
			if ($find[$x]) print "<option></option>";
			while ($r=db_fetch_array($q)) {
				print "<option>$r[$tmp]</option>";
			}
			print "</SELECT></td>";			

		} else print "<input class='inputboks' class=\"inputbox\" type=text style=\"text-align:$justering[$x];$width;\" name=find[$x] value=\"$find[$x]\">";
	}
	print "</td>\n";  
print "<td align=center><input class='button blue small ok' type=submit value=\"OK\" name=\"submit\"></td>";
print "<td align=center><input class='button blue small ' type=submit value=\"Ryd\" name=\"clear\"></td>";
print "</form></tr>\n";

print "
<script>
	window.onload = function() {
		$script
	};
</script>
";

if ($menu=='T') {
	print "<tr><th colspan=20 style='padding: 0px; height: 1px;'></th></tr>";
} else {
	print "";
}

if ($menu=='T') {
	print "</thead><tbody>";
} else {
	print "<tr><td colspan=11><hr></td></tr>\n";
}

######################################################################################################################
#if ($genfakt) $checked=array();
if ($vis_lagerstatus) {
	$x=0;
	$qtxt="select kodenr from grupper where art='VG' and box8='on'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$ls_vgr[$x]=$r['kodenr'];
		$x++;
	}
}
print "<form name=ordreliste action=ordreliste.php?valg=$valg$hreftext&start=$start&sort=$sort method=post>\n";
if (strstr($udvaelg,'adresser')) $qtxt="select ordrer.*,adresser.gruppe as kundegruppe from ordrer,adresser ";
else $qtxt="select ordrer.* from ordrer ";
$qtxt.="where (ordrer.art = 'DO' or ordrer.art = 'DK' ";
$qtxt.="or (ordrer.art = 'PO' and ordrer.konto_id > 0)) ";
if (strstr($udvaelg,'adresser')) $qtxt.="and adresser.id=ordrer.konto_id ";
$qtxt.="and ($status $udvaelg) order by $sortering";
$q0 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
$l=0;
$lnr=0;
while ($r0=db_fetch_array($q0)) {
	$lnr++;
	if($lnr>=$start && $lnr<$slut) {
		$ordreantal++;
#		if ($ordreliste) $ordreliste=$ordreliste.",".$id;
#		else $ordreliste=$id;
		$ordre="ordrer".$id;
		$sum=$r0['sum'];
		$kostpris=$r0['kostpris'];
		$valutakurs=$r0['valutakurs']*1;
		$nextfakt=$r0['nextfakt'];
		$sum_m_moms=$r0['sum']+$r0['moms'];
		$moms=$r0['moms'];
		($r0['tidspkt'])? $timestamp = $r0['tidspkt'] : $timestamp = 0;#20210328
		if (strpos($timestamp,':')) $timestamp = strtotime(date('Y/m/d')." ".$timestamp); #20220219
		$who = $r0['hvem'];
		$id=$r0['id']; 
		if ($valg=="faktura") {
			$udlignet=0;
			$qtxt = "select udlignet from openpost where faktnr = '$r0[fakturanr]' and ";
			$qtxt.= "konto_id='$r0[konto_id]' and 	amount='$sum_m_moms'";
			if ($r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$udlignet=(int)$r1['udlignet'];
				if ($udlignet && !$r0['betalt']) {
					db_modify("update ordrer set betalt = 1 where id = '$id'",__FILE__ . " linje " . __LINE__);
				}
			} else { # 20101220 Denne del er indsat grundet enkelte forekomster med manglende faktnr  
				$tmp1="Faktura - ".$r0['fakturanr'];
				$tmp2="Faktura - ".$r0['fakturanr']." - ".$r0['fakturadate'];
				$qtxt="select id,udlignet from openpost where (beskrivelse = '$tmp1' or beskrivelse = '$tmp2') ";
				$qtxt.="and konto_id='$r0[konto_id]' and amount='$sum_m_moms'";
				if ($r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$udlignet=(int)$r1['udlignet'];
					db_modify("update openpost set faktnr='$r0[fakturanr]' where id = '$r1[id]'",__FILE__ . " linje " . __LINE__);
					$message=$db." | ".$tmp2." | ".$brugernavn." ".date("Y-m-d H:i:s")." | $fejltekst";
					$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
					mail('fejl@saldi.dk', 'SALDI Opdater openpost', $message, $headers);
				} else $udlignet=1;
			}
		}
		$href="ordre.php?tjek=$id&id=$id&returside=".urlencode($_SERVER["REQUEST_URI"]);
		$tle1 = findtekst(1421, $sprog_id);
		$tle2 = findtekst(1522, $sprog_id);
		if ($valg == 'faktura' || $tidspkt-($timestamp)>3600 || $who==$brugernavn || $who=='' ) { #20220301
				if ($popup) {
					$javascript = "onClick=\"javascript:$ordre=window.open('$href','$ordre','scrollbars=1,resizable=1');$ordre.focus();\"";
				} else {
					$javascript = "onClick=\"javascript:$ordre=window.location.replace('$href','$ordre')\"";
				}
				$javascript.= "onMouseOver=\"this.style.cursor = 'pointer'\" ";
				$linjetext="";
				($menu=='T')? $understreg='<span>' : $understreg='<span style="text-decoration: underline;">';
				#$linjetext="<span title= '".$tle2." $r0[hvem]'>"; #20210719
			} else {
				$javascript = "onClick=\"javascript:$ordre=window.location.replace('$href','$ordre');\"";
				$understreg= "!<span style=\"text-decoration: none;\">";
				$linjetext="<span title= '".$tle1." $r0[hvem]'>"; #20210714
			}
			if ( $valg == '$ordrer1' && $bgnuance1 ) {
			$q2=db_select("select antal,leveres,leveret from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
			$levstatus=0;
			while ($r2=db_fetch_array($q2)) {
				if ( $levstatus === "Mangler" ) {
					continue;
				} 
				if ( ( $r2['leveret'] > 0 &&  $r2['antal'] > $r2['leveret'] ) || ( $r2['antal'] > ($r2['leveres']+$r2['leveret'] ) ) ) {
					$levstatus="Mangler";
				} elseif ( $r2['leveret'] == 0 && ( $r2['antal'] == $r2['leveres'] ) ) {
					if ( $levstatus === "Leveret" ) {
						$levstatus="Mangler";
					} else {
						$levstatus="Intet";
					}
				} elseif ( ( ! $levstatus == "Intet" ) && $r2['leveret'] > 0 && $r2['antal'] == $r2['leveret'] ) {
					$levstatus="Leveret";
				}
			}
	
			if ( $levstatus == "Mangler" ) {
				$bgnuance=$bgnuance1;
				$color='#000000';
				if ($r0['art']=='DK') {
					$tr_title= findtekst(1422, $sprog_id);  #20210715
				} else {
					$tr_title= findtekst(1423, $sprog_id); 
				}
			} elseif ( $levstatus == "Leveret" ) {
				$bgnuance=0;
				$color='#555555';
				if ($r0['art']=='DK') {
					$tr_title= findtekst(1424, $sprog_id);
				} else {
					$tr_title= findtekst(1425, $sprog_id);
				}
			} else {
				$bgnuance=0;
				$color='#000000';
				if ($r0['art']=='DK') {
					$tr_title= findtekst(1426, $sprog_id);
				} else {
					$tr_title= findtekst(1427, $sprog_id);
				}
			}
			
			$linjebg=linjefarve($linjebg, $bgcolor, $bgcolor5, $bgnuance1, $bgnuance);
			print "<tr bgcolor=\"$linjebg\" title='$tr_title'><td bgcolor=$bgcolor></td>";
        } elseif ($vis_lagerstatus) {
            // Initialize background color for the table rows<
            $linjebg = NULL;
            $spantxt = "<table><tbody>";

            // Add table headers with localized text using the findtekst function
            $spantxt .= "<tr><td>Varenr</td><td>" . findtekst(948, $sprog_id) . "</td><td>" . findtekst(916, $sprog_id) . "</td><td>" . findtekst(1190, $sprog_id) . "</td><td>" . findtekst(1428, $sprog_id) . "</td><td>" . findtekst(1429, $sprog_id) . "</td><td>" . findtekst(1430, $sprog_id) . "</td><td>" . findtekst(976, $sprog_id) . "</td></tr>";

            // Fetch all order lines with non-zero quantities for the specified order ID
            $q = db_select("select * from ordrelinjer where ordre_id='$id' and antal != '0'", __FILE__ . " linje " . __LINE__);

            // Iterate through each order line
            while ($r = db_fetch_array($q)) {
                // Fetch stock and group information for the current product
                $r2 = db_fetch_array(db_select("select beholdning, gruppe from varer where id='$r[vare_id]'", __FILE__ . " linje " . __LINE__));

                // Check if the product group is part of the lagerførte list
                if (in_array($r2['gruppe'], $ls_vgr) || true) {
                    // Calculate stock availability
                    $tmp = find_beholdning($r['vare_id'], NULL);

                    // Determine background color based on stock levels
                    if ($r2['beholdning'] - ($r['antal'] - $r['leveret']) < 0 && $r2['beholdning'] + $tmp[4] - ($r['antal'] - $r['leveret']) >= 0 && in_array($r2['gruppe'], $ls_vgr)) {
                        $spanbg = "#FFFF66"; // Yellow: Low stock but sufficient with pending stock
                    } elseif ($r2['beholdning'] - ($r['antal'] - $r['leveret']) < 0 && in_array($r2['gruppe'], $ls_vgr)) {
                        $spanbg = "#FF4D4D"; // Red: Insufficient stock
                    } elseif ($r['antal'] != $r['leveret']) {
                        $spanbg = "#66FF66"; // Green: Pending levering 
                    } else {
                        $spanbg = "#FF33FF"; // Purple: Sufficient stock
                    }

                    // Add a table row if the background color is not purple (indicating a potential issue)
                    if ($spanbg != "#FF33FF") {
						if ($spanbg != "#66FF66") {
                        $spantxt .= "<tr bgcolor=$spanbg><td>$r[varenr]</td><td align=right>" . dkdecimal($r2['beholdning'] * 1, 0) . "</td>";
                        $spantxt .= "<td align=right>" . dkdecimal($r['antal'] * 1, 0) . "</td><td align=right>" . dkdecimal($r['leveret'] * 1, 0) . "</td>";
                        $spantxt .= "<td align=right>$tmp[1]</td><td align=right>$tmp[2]</td><td align=right>$tmp[3]</td><td align=right>$tmp[4]</td></tr>";
						}

                        // Update the row background color if it indicates stock issues
                        if (true) {
							# If it is green and there is a potential stock issue
                            if (($linjebg == null || $linjebg == "#66FF66") &&
								 $r2['beholdning'] - ($r['antal'] - $r['leveret']) < 0 &&
								 $r2['beholdning'] + $tmp[4] - ($r['antal'] - $r['leveret']) >= 0 &&
								in_array($r2['gruppe'], $ls_vgr)
							) {
                                $linjebg = "#FFFF66"; // Yellow: Potential stock issue

							} elseif (($linjebg == null || $linjebg == "#FFFF66" || $linjebg == "#FF33FF" || $linjebg == "#66FF66") &&
									  $r2['beholdning'] - ($r['antal'] - $r['leveret']) < 0 &&
									  in_array($r2['gruppe'], $ls_vgr)
							) {
                                $linjebg = "#FF4D4D"; // Red: Definite stock issue

                            } elseif ($r['antal'] != $r['leveret'] && $linjebg == null) {
                                $linjebg = "#66FF66"; // Purple: In stock but not delivered
                            } 
                        }
                    }
                }
            }

            // Add a legend explaining the color codes
            $spantxt .= "<tr><td colspan=100><hr></td></tr>";
            $spantxt .= "<tr><td>Magenta</td><td colspan=7>" . findtekst(2403, $sprog_id) . "</td></tr>";
            $spantxt .= "<tr><td>Grøn</td><td colspan=7>" . findtekst(1431, $sprog_id) . "</td></tr>";
            $spantxt .= "<tr><td>Gul</td><td colspan=7>" . findtekst(1432, $sprog_id) . "</td></tr>";
            $spantxt .= "<tr><td>Rød</td><td colspan=7>" . findtekst(1433, $sprog_id) . "</td></tr>";
            $spantxt .= "</tbody></table>";

            // Set the default row background color to purple if no issues were found
            if (!$linjebg) $linjebg = "#FF33FF";

            // Print the table row with the calculated background color
            print "<tr bgcolor=\"$linjebg\" title=''><td bgcolor=\"$bgcolor\">";

            print "</td>";

		} else {
			if ($linjebg!=$bgcolor) {
				$linjebg=$bgcolor; $color='#000000';
			} else {
				$linjebg=$bgcolor5; $color='#000000';
			}
			print "<tr bgcolor=\"$linjebg\" title='$tr_title'><td $TableBG></td>";
		}

		($ordreliste)?$ordreliste=$ordreliste.",".$r0['id']:$ordreliste=$r0['id'];
		if ($r0['art']=='DK') {
			print "<td align=$justering[0] $javascript style='color:$color'>(KN)&nbsp;$linjetext $understreg $r0[ordrenr]</div><br></td>";
		} else if ($r0['restordre']=='1') {
			print "<td align=$justering[0] $javascript style='color:$color' title='Restordre'>(R)&nbsp;$linjetext $understreg $r0[ordrenr]</div><br></td>";
		} else {
			print "<td align=$justering[0] ";
			if ($popup) print " $javascript";
			print " style='color:$color'>";
			if (!$popup) print "<a href='$href'>";
			if ($vis_lagerstatus) {
				print "<span onmouseover=\"return overlib('".$spantxt."', WIDTH=800);\" onmouseout=\"return nd();\">";
			}
			print "$linjetext $understreg $r0[ordrenr]";
			if ($vis_lagerstatus) print "</span>";
			if (!$popup) print "</a>";
			print "</div><br></td>";
		}
#		print "<td></td>";
		$r0['ordredato']=dkdato($r0['ordredate']);
#		print "<td>$ordredato<br></td>";
#		$levdato=dkdato($r0['levdate']);
#		print "<td>$levdato<br></td>";
#		print"<td></td>";
		for ($x=1;$x<$vis_feltantal;$x++) {
		print "<td align=$justering[$x] style='color:$color'>";
			if ($vis_felt[$x]=="sum" || $vis_felt[$x]=='sum_m_moms' || $vis_felt[$x]=='moms') {
				if ($genberegn) $kostpris=genberegn($id);
				if ($valutakurs && $valutakurs!=100) {
					$sum=$sum*$valutakurs/100;
					$sum_m_moms=$sum_m_moms*$valutakurs/100;
					$moms=$moms*$valutakurs/100;
#					$kostpris=$kostpris*$valutakurs/100; #20170520
#					$sum=bidrag($sum, $moms, $kostpris,'1');
#					print "a".dkdecimal($sum,2);
#					$tmp=dkdecimal($sum,2);
				} elseif ($valg!="faktura") {
					 if ($vis_felt[$x]=="sum") print dkdecimal($sum,2);
					 elseif ($vis_felt[$x]=="sum_m_moms") print dkdecimal($sum_m_moms,2);
					 elseif ($vis_felt[$x]=="moms") print dkdecimal($moms,2);
				}
				if ($valg=="faktura") {
					$sum=bidrag($vis_felt[$x],$sum,$moms,$sum_m_moms,$kostpris,$udlignet);
#					if ($checked[$id]=='on' || $check_all) $checked[$id]='checked';
#					print "<td align=right><input class=\"inputbox\" type=\"checkbox\" name=\"checked[$ordreantal]\" $checked[$id]></td>";
#					print "<input type=hidden name=ordre_id[$ordreantal] value=$id>";
				} 
			} elseif ($vis_felt[$x]=='kundeordnr' && $valg=="faktura") {
				$tmp=$vis_felt[$x];
				print "<a href='ordre.php?id=$r0[id]'>$r0[$tmp]</a>";
			} elseif (strpos($vis_felt[$x],"date") || $vis_felt[$x]=="nextfakt") {
	 			print dkdato($r0[$vis_felt[$x]]);
			} else {
				$tmp=$vis_felt[$x];
				if (isset($r0[$tmp])) print$r0[$tmp];
			}
			print "</td>"; 
		}
		if (!isset($checked[$id])) $checked[$id]=NULL;
		if ($uncheck_all) $checked[$id]=NULL;
		elseif ($checked[$id]=='on' || $check_all) $checked[$id]='checked';
		
		if ($valg=="faktura" || ($valg=="ordrer" && $nextfakt)) {
			$vis_ret_next=1;
			print "<td align=left><label class='checkContainerOrdreliste'><input class=\"inputbox deliveryNoteSelect\" type=\"checkbox\" name=\"checked[$id]\" id='$id' $checked[$id]><span class='checkmarkOrdreliste'></span></label></td>";
			
		} else {
			if ($checked[$id]=='on' || $check_all) $checked[$id]='checked';
			print "<td>
				<label class='checkContainerOrdreliste'>
					<input class=\"inputbox\" type=\"checkbox\" name=\"checked[$id]\" $checked[$id]>
					<span class='checkmarkOrdreliste'></span>
				</label>";
			print "</td>";
		}
		
		if ($valg == "ordrer") {
			?>
			<td>
				<div style="display:flex;gap:5px;">
					<a href="formularprint.php?id=<?php print $r0["id"]; ?>&formular=9&udskriv_til=PDF" target="_blank" title="Klik for at printe plukliste"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M216-96q-29.7 0-50.85-21.15Q144-138.3 144-168v-412q-21-8-34.5-26.5T96-648v-144q0-29.7 21.15-50.85Q138.3-864 168-864h624q29.7 0 50.85 21.15Q864-821.7 864-792v144q0 23-13.5 41.5T816-580v411.86Q816-138 794.85-117T744-96H216Zm0-480v408h528v-408H216Zm-48-72h624v-144H168v144Zm216 240h192v-72H384v72Zm96 36Z"/></svg></a>
					<a href="formularprint.php?id=<?php print $r0["id"]; ?>&formular=2&udskriv_til=PDF" target="_blank" title="Klik for at printe ordrebekræftigelse"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z"/></svg></a>
					<?php if ($row['email']) {
						?> <a href="formularprint.php?id=<?php print $r0["id"]; ?>&formular=2&udskriv_til=email" target="_blank" title="Klik for at sende ordrebekræftigelse via email" onclick="return confirm('Er du sikker på, at du vil sende ordrebekræftigelse?\nKundens mail: <?php print $r0['email']; ?>')"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M168-192q-29.7 0-50.85-21.16Q96-234.32 96-264.04v-432.24Q96-726 117.15-747T168-768h624q29.7 0 50.85 21.16Q864-725.68 864-695.96v432.24Q864-234 842.85-213T792-192H168Zm312-240L168-611v347h624v-347L480-432Zm0-85 312-179H168l312 179Zm-312-94v-85 432-347Z"/></svg></a> <?php
					} ?>
				</div>
			</td>
			<?php
		} else if ($valg == "faktura") {
			?>
			<td>
				<div style="display:flex;gap:5px;">
					<a href="formularprint.php?id=<?php print $r0["id"]; ?>&formular=3&udskriv_til=PDF" target="_blank" title="Klik for at printe følgeseddel"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M216-96q-29.7 0-50.85-21.15Q144-138.3 144-168v-412q-21-8-34.5-26.5T96-648v-144q0-29.7 21.15-50.85Q138.3-864 168-864h624q29.7 0 50.85 21.15Q864-821.7 864-792v144q0 23-13.5 41.5T816-580v411.86Q816-138 794.85-117T744-96H216Zm0-480v408h528v-408H216Zm-48-72h624v-144H168v144Zm216 240h192v-72H384v72Zm96 36Z"/></svg></a>
					<a href="formularprint.php?id=<?php print $r0["id"]; ?>&formular=4&udskriv_til=PDF" target="_blank" title="Klik for at printe faktura"><svg  xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z"/></svg></a>
					<?php if ($row['email']) {
						?> <a href="formularprint.php?id=<?php print $r0["id"]; ?>&formular=4&udskriv_til=email" target="_blank" title="Klik for at sende faktura via email" onclick="return confirm('Er du sikker på, at du vil sende fakturaen?\nKundens mail: <?php print $r0['email']; ?>')"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M168-192q-29.7 0-50.85-21.16Q96-234.32 96-264.04v-432.24Q96-726 117.15-747T168-768h624q29.7 0 50.85 21.16Q864-725.68 864-695.96v432.24Q864-234 842.85-213T792-192H168Zm312-240L168-611v347h624v-347L480-432Zm0-85 312-179H168l312 179Zm-312-94v-85 432-347Z"/></svg></a> <?php
					} ?>
				</div>
			</td>
			<?php
		} else if ($valg == "tilbud") {
			?>
			<td>
				<div style="display:flex;gap:5px;">
					<a href="formularprint.php?id=<?php print $r0["id"]; ?>&formular=1&udskriv_til=PDF" target="_blank" title="Klik for at printe tilbud"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z"/></svg></a>
					<?php if ($row['email']) {
						?> <a href="formularprint.php?id=<?php print $r0["id"]; ?>&formular=1&udskriv_til=email" target="_blank" title="Klik for at sende tilbud via email"  onclick="return confirm('Er du sikker på, at du vil sende fakturaen?\nKundens mail: <?php print $r0['email']; ?>')"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M168-192q-29.7 0-50.85-21.16Q96-234.32 96-264.04v-432.24Q96-726 117.15-747T168-768h624q29.7 0 50.85 21.16Q864-725.68 864-695.96v432.24Q864-234 842.85-213T792-192H168Zm312-240L168-611v347h624v-347L480-432Zm0-85 312-179H168l312 179Zm-312-94v-85 432-347Z"/></svg></a> <?php
					} ?>
				</div>
			</td>
			<?php
		}

		$q3=db_select("select sum(kostpris * antal) as sum_kostpris
					  from ordrelinjer
				  	  where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		$r3=db_fetch_array($q3);
		$kostpris_fetch = $r3["sum_kostpris"];

		print "<input type=hidden name=ordre_id[$l] value=$id>"; #20210818
		$ialt+=$sum;
		$ialt_m_moms+=$sum_m_moms;
		$ialt_kostpris+=$kostpris_fetch;
		$l++;
		print "</tr>\n";
	}# endif ($lnr>=$start && $lnr<$slut)
}# endwhile
$colspan=$vis_feltantal+2;
if (!$l && $udvaelg) {
	$colspan=$vis_feltantal+2;
	print "<tr><td align='center' colspan='$colspan'>";
 	print "<b><big>Ingen ordrer matcher de angivne søgekriterier<big></b>";
	print "</tr>";
}
if ($menu=='T') {
	print "</tbody><tfoot>\n";
} else {
	print "<tr><td colspan='$colspan'><hr></td></tr>\n";
}
if ($valg) {		
	if ($vis_projekt) $colspan++;
	if ($check_all) {
		print "<tr><td align='right' colspan='$colspan'><input type=\"submit\" style=\"width:100px\"; name=\"uncheck\" value=\"".findtekst(90,$sprog_id)."\">";
	} else {
		print "<tr><td align='right' colspan='$colspan'><input type=\"submit\" style=\"width:100px\"; name=\"check\" value=\"".findtekst(89,$sprog_id)."\">";
	}
#	print "<tr><td align=right colspan=$colspan><input type=\"submit\" style=\"width:100px\"; name=\"opdat\" value=\"Opdater\">";
	print "	</td></tr>\n";
	print "<input type=hidden name=ordre_antal value='$ordreantal'>";
	print "<input type=hidden name=valg value='$valg'>";
	print "<input type=hidden name=ordrenumre value='$ordrenumre'>";
	print "<input type=hidden name=kontonumre value='$kontonumre'>";
	print "<input type=hidden name=fakturanumre value='$fakturanumre'>";
	print "<input type=hidden name=ordredatoer value='$ordredatoer'>";
	print "<input type=hidden name=lev_datoer value='$lev_datoer'>";
	print "<input type=hidden name=fakturadatoer value='$fakturadatoer'>";
	print "<input type=hidden name=genfaktdatoer value='$genfaktdatoer'>";
	print "<input type=hidden name=summer value='$summer'>";
	print "<input type=hidden name=ref value='$ref[0]'>";
	print "<input type=hidden name=firma value='$firma'>";
	print "<input type=hidden name=kontoid value='$kontoid'>";
	print "<input type=hidden name=sort value='$sort'>";
	print "<input type=hidden name=nysort value='$nysort'>";
	print "</tr><tr><td colspan=$colspan align=right>";
	if ($valg=="faktura") {
		if ($genfakturer) print "<input type=submit value=\"Genfaktur&eacute;r\" name=\"submit\">&nbsp;";
		if (strlen("which ps2pdf")) {
			if (in_array('udskriv_til',$vis_felt)) {
				for ($i=1;$i<=count($vis_felt);$i++) {
					if 	(isset($vis_felt[$i]) && $vis_felt[$i]=='udskriv_til') $z=$i;
				}
				if ($find[$z]=='email') {
					$confirm = findtekst(1444, $sprog_id); 
					print "<span title=\"".findtekst(1435, $sprog_id)."\"><input type=submit style=\"width:100px\"; value=\"Send mails\" name=\"submit\" onclick=\"return confirm('$confirm $valg pr mail?')\"></span><br>";
				} 
			}
			$confirm1= findtekst(1445, $sprog_id);  
			print "<span title=\"".findtekst(1436, $sprog_id)."\"><input type=submit style=\"width:100px\"; value=\"".findtekst(880,$sprog_id)."\" name=\"submit\" onclick=\"return confirm('$confirm1 $valg?')\"></span></td></tr>";
			print "<tr><td colspan='13' align='right'><span title='Udskriv følgesedler'><input type='submit' style='width:100px' value='Følgesedler' name='deliveryNote')'></span></td></tr>";
			?>
			<script>
				const button = document.querySelector("[name='deliveryNote']")
				button.addEventListener("click", () => {
					const checkedBoxes = Array.from(document.querySelectorAll(".deliveryNoteSelect")).filter(checkbox => checkbox.checked)
					// fetch with post to a php file that creates the pdf files then download them 
					
				})

			</script>
			<?php
		} else {
			print "<input type=submit value=\"".findteskt(880, $sprog_id)."\" name=\"submit\" style=\"width:100px\"; disabled=\"disabled\"></td>";
			
		}
	} else {
		$confirm2 = findtekst(1446, $sprog_id);
		print "<input class='button red medium' type=submit style=\"width:100px;\" value=\"".findtekst(1099, $sprog_id)."\" name=\"slet_valgte\" onclick=\"return confirm('$confirm2 $valg?')\">";
	}
	print "</tr>\n";
}

if ($valg=="ordrer") {
#	if ($vis_projekt) $colspan++;
	if ($vis_ret_next) {
		if ($check_all) { 
#			print "<tr><td align=right colspan=$colspan><input type=\"submit\" style=\"width:100px;\" name=\"uncheck\" value=\"".findtekst(90,$sprog_id)."\">";
		} else {
#			print "<tr><td align=right colspan=$colspan><input type=\"submit\" style=\"width:100px;\" name=\"check\" value=\"".findtekst(89,$sprog_id)."\">";
		}
		print "	</td></tr>\n";
	}
	print "<input type=hidden name=ordre_antal value='$ordreantal'>";
	print "<input type=hidden name=valg value='$valg'>";
	print "<input type=hidden name=ordrenumre value='$ordrenumre'>";
	print "<input type=hidden name=kontonumre value='$kontonumre'>";
	print "<input type=hidden name=fakturanumre value='$fakturanumre'>";
	print "<input type=hidden name=ordredatoer value='$ordredatoer'>";
	print "<input type=hidden name=lev_datoer value='$lev_datoer'>";
	print "<input type=hidden name=fakturadatoer value='$fakturadatoer'>";
	print "<input type=hidden name=genfaktdatoer value='$genfaktdatoer'>";
	print "<input type=hidden name=summer value='$summer'>";
	print "<input type=hidden name=ref value='$ref[0]'>";
	print "<input type=hidden name=firma value='$firma'>";
	print "<input type=hidden name=kontoid value='$kontoid'>";
	print "<input type=hidden name=sort value='$sort'>";
	print "<input type=hidden name=nysort value='$nysort'>";
	print "</tr><tr><td colspan=$colspan align=right>";
#	if (in_array('on',$checked)) {
		if ($vis_ret_next) print "<span title='".findtekst(1437, $sprog_id)."'><input class='button blue medium' type=\"submit\" style=\"width:100px\"; value=\"".findtekst(1206, $sprog_id)."\" name=\"submit\"></td>";
#	}
}
print "</form></tr>\n";
$qtxt = "select id from grupper where art = 'OLV' and kode = '$valg' and kodenr = '$bruger_id'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$qtxt = "update grupper set box1='$ordreliste' where id='$r[id]'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
} #else db_modify("insert into grupper (beskrivelse,kode,kodenr,art,box1) values ('Ordrelistevisning','$valg','$bruger_id','OLV','$ordreliste')",__FILE__ . " linje " . __LINE__);

if ($menu=='T') {
	print "<tr><td colspan='$colspan' class='border-hr-top'></td></tr>\n";
} else {
	print "<tr><td colspan='$colspan'><hr></td></tr>\n";
}

# Calcualte db / dg and total price
$dk_db=dkdecimal($ialt-$ialt_kostpris,2);		
if ($ialt!=0) {$dk_dg=dkdecimal(($ialt-$ialt_kostpris)*100/$ialt,2);}		
$ialt=dkdecimal($ialt,2);
$ialt_m_moms=dkdecimal($ialt_m_moms,2);

# Display the prcie and dg / db
print "<tr><td colspan='$colspan' width='100%'>";
print "<table border='0' width='100%' style='width:100%;'><tbody>";
if ($valg=="faktura") {
	print "<td width='10%'></td><td width='70%' align=right><span title= '".findtekst(1438, $sprog_id)."'><b><a href=ordreliste.php?genberegn=1&valg=$valg accesskey=G>".findtekst(878,$sprog_id)."</a></td><td width=20% align=right><b>$ialt / $dk_db / $dk_dg%</td></tr>\n";
	print "<td width=10%><br></td><td width=70% align=right><span title= ''><b>".findtekst(877,$sprog_id)."</td><td width=20% align=right><b>$ialt_m_moms</td></tr>\n";
} else {
	print "<td width=20%>";
	if ($valg=="ordrer" && !$vis_lagerstatus) {
		print "<span title='".findtekst(1443, $sprog_id)."'>";
		print "<a href=\"ordreliste.php?vis_lagerstatus=on&valg=$valg\">".findtekst(810,$sprog_id)."</a>";#20210318
		print "</span>";
	}
	print "</td><td width=70% align=right>".findtekst(811,$sprog_id)."<br>db / dg (excl. moms.)</td><td width=20% align=right><b>$ialt_m_moms ($ialt)<br>$dk_db / $dk_dg%</td></tr></tr>\n";
}
if ($genberegn==1) print "<meta http-equiv=\"refresh\" content=\"0;URL='ordreliste.php?genberegn=2&valg=$valg'\">";
#$cols++;
if ($valg=="faktura"){$cols++;}
#$cols=$cols+4;

if ($valg=="ordrer") {
	$r=db_fetch_array(db_select("select box1 from grupper where art='MFAKT' and kodenr='1'",__FILE__ . " linje " . __LINE__));
	if($r){ #20211018
		if ($r['box1'] && $ialt!="0,00") {
			$tekst="Faktur&eacute;r alt som kan leveres?";
			print "<tr><td colspan=\"2\"><span title='".findtekst(1439, $sprog_id)."'><a href=csv2ordre.php target=\"_blank\">CSV import</a></span></td><td colspan=\"".($colspan-3)."\" align=right><span title='".findtekst(1440, $sprog_id)."'><a href=massefakt.php?valg=$valg onClick=\"return MasseFakt('$tekst')\">Faktur&eacute;r&nbsp;alt</a></span></td></tr>";
		} else { 
				if ($menu=='T') {
					print "<tr><td colspan=\"3\">&nbsp;&nbsp;<span title='".findtekst(1439, $sprog_id)."'><a href=csv2ordre.php target=\"_blank\">CSV import</a></span></td></tr>";
				} else {
					print "<tr><td colspan=\"3\"><span title='".findtekst(1439, $sprog_id)."'><a href=csv2ordre.php target=\"_blank\">CSV import</a></span></td></tr>";
				}
		}
	}
}	

if ($r=db_fetch_array(db_select("select box4, box5, box6 from grupper where art='API' and box4 != ''",__FILE__ . " linje " . __LINE__))) {
	$api_fil=trim($r['box4']);
	$api_fil2=trim($r['box5']);
	$api_fil3=trim($r['box6']);
	if (file_exists("../temp/$db/shoptidspkt.txt")) {
		$fp=fopen("../temp/$db/shoptidspkt.txt","r");
		$tidspkt=fgets($fp);
		if ($hent_nu) $tidspkt-=1170; 
		fclose ($fp);
	} else $tidspkt = 0;
	if ($tidspkt < date("U")-1200 || $shop_ordre_id  || $shop_faktura) {
		$fp=fopen("../temp/$db/shoptidspkt.txt","w");
		fwrite($fp,date("U"));
		fclose ($fp);
		$header="User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
		$api_txt="$api_fil?put_new_orders=1";
//		$api_encode='utf-8';
		if ($api_encode) $api_txt.="&encode=$api_encode";
		if ($shop_ordre_id && is_numeric($shop_ordre_id)) $api_txt.="&order_id=$shop_ordre_id";
		elseif ($shop_faktura) $api_txt.="&invoice=$shop_faktura";
		exec ("nohup /usr/bin/wget  -O - -q  --no-check-certificate --header='$header' '$api_txt' > /dev/null 2>&1 &\n");
		if($api_fil2){
			$api_txt="$r[box5]?put_new_orders=1";
	//		$api_encode='utf-8';
			if ($api_encode) $api_txt.="&encode=$api_encode";
			if ($shop_ordre_id && is_numeric($shop_ordre_id)) $api_txt.="&order_id=$shop_ordre_id";
			elseif ($shop_faktura) $api_txt.="&invoice=$shop_faktura";
			exec ("nohup /usr/bin/wget  -O - -q  --no-check-certificate --header='$header' '$api_txt' > /dev/null 2>&1 &\n");
		}
		if($api_fil3){
			$api_txt="$r[box6]?put_new_orders=1";
	//		$api_encode='utf-8';
			if ($api_encode) $api_txt.="&encode=$api_encode";
			if ($shop_ordre_id && is_numeric($shop_ordre_id)) $api_txt.="&order_id=$shop_ordre_id";
			elseif ($shop_faktura) $api_txt.="&invoice=$shop_faktura";
			exec ("nohup /usr/bin/wget  -O - -q  --no-check-certificate --header='$header' '$api_txt' > /dev/null 2>&1 &\n");
		}
	} elseif ($hent_nu) alert("vent 30 sekunder");
	print "<tr><td><a href=\"$_SERVER[PHP_SELF]?sort=$sort&hent_nu=1\">".findtekst(879,$sprog_id)."</td></tr>";
}
$r=db_fetch_array(db_select("select box2 from grupper where art='DIV' and kodenr='5'",__FILE__ . " linje " . __LINE__));

if (isset($r['box2']) && $apifil=$r['box2']) { //checks if $r$r['box2'] exists before using it
	(strpos($r['box2'],'opdat_status=1'))?$opdat_status=1:$opdat_status=0;
	(strpos($r['box2'],'shop_fakt=1'))?$shop_fakt=1:$shop_fakt=0;
	(strpos($r['box2'],'betaling=kort'))?$kortbetaling=1:$kortbetaling=0;
	($kortbetaling)?$betalingsbet='betalingskort':$betalingsbet='netto+8';
	if (substr($apifil,0,4)=='http') {
		$apifil=trim(str_replace("/?","/hent_ordrer.php?",$apifil));
		$apifil=$apifil."&saldi_db=$db";
		$saldiurl="://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		if ($_SERVER['HTTPS']) $saldiurl="s".$saldiurl;
		$saldiurl="http".$saldiurl;
		if ($shop_fakt) {
			$qtxt="select max(shop_id) as shop_id from shop_ordrer";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$next_id=$r['shop_id']+1;
			$apifil.="&next_id=$next_id";
		}
		if ($shop_fakt) {
			$shop_ordre_id*=1;
			$apifil.="&shop_fakt=$shop_fakt&popup=1&shop_ordre_id=$shop_ordre_id";
		}
		$apifil.="&saldiurl=$saldiurl";
		$apifil.="&random=".rand();
		if ($shop_fakt) {
				if (file_exists("../temp/$db/shoptidspkt.txt")) {
				$fp=fopen("../temp/$db/shoptidspkt.txt","r");
				$tidspkt=fgets($fp);
			} else $tidspkt = 0;
			fclose ($fp);
			if ($tidspkt < date("U")-300 || $shop_ordre_id) {
				$fp=fopen("../temp/$db/shoptidspkt.txt","w");
				fwrite($fp,date("U"));
				fclose ($fp);
				if ($db=='bizsys_52') {
					print "<BODY onLoad=\"JavaScript:window.open('$apifil','hent:ordrer','width=10,height=10,top=1024,left=1280')\">";
				} else exec ("nohup /usr/bin/wget --spider $api_fil  > /dev/null 2>&1 &\n");
			} else {
				$tjek=$next_id-50;
				$qtxt="select shop_id from shop_ordrer where shop_id >= '$tjek'";
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					while ($r['shop_id']!=$tjek && $tjek<$next_id) {
						$tmp=$apifil."&shop_ordre_id=$tjek";
						print "<BODY onLoad=\"JavaScript:window.open('$tmp'	,'hent:ordrer','width=10,height=10,top=1024,left=1280')\">";
						$tjek++;
					} 					
					$tjek++;
				}
			}
		} else print "<tr><td colspan=\"3\"><span title='".findtekst(1441, $sprog_id)."' onclick=\"JavaScript:window.open('$apifil','hent:ordrer','width=10,height=10,top=1024,left=1280')\">SHOP import</span></td></tr>";	
	}
}

#print "<body onload=\"javascript:window.open('$url','opdat:beholdning');\">";
function genberegn($id) {
	$kostpris=0;
	$qtxt = "select id,vare_id,antal,pris,kostpris,saet,samlevare from ordrelinjer where ordre_id = '$id' ";
	$qtxt.= "and posnr > '0' and vare_id > '0' and antal IS NOT NULL and kostpris IS NOT NULL";
	$q0 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r0=db_fetch_array($q0)) {
		$qtxt = "select provisionsfri, gruppe from varer where id = '$r0[vare_id]'";
		if ($r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt = "select box9 from grupper where art = 'VG' and kodenr='$r1[gruppe]' and box9 = 'on'";
			if ( !$r1['provisionsfri'] && db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$kostpris+=$r0['kostpris']*$r0['antal'];
			}
			elseif ($r1['provisionsfri']) {
				$kostpris+=$r0['pris']*$r0['antal'];
			}
			else {	
				if ($r0['saet'] && $r0['samlevare'] && $r0['kostpris']) { 
					$r0['kostpris']=0;
					db_modify("update ordrelinjer set kostpris='0' where id = '$r0[id]'");
				}
				$kostpris+=$r0['kostpris']*$r0['antal'];
#					$r2=db_fetch_array(db_select("select kostpris from varer where id = $r0[vare_id]",__FILE__ . " linje " . __LINE__));	
#					$kostpris=$kostpris+$r2['kostpris']*$r0['antal'];
			}
		}
	} 
	db_modify("update ordrer set kostpris=$kostpris where id = $id",__FILE__ . " linje " . __LINE__);#xit;
	return $kostpris;
}

function bidrag ($feltnavn,$sum,$moms,$sum_m_moms,$kostpris,$udlignet){
	global $genberegn,$ialt,$totalkost,$sprog_id;

	$ialt=$ialt+$sum;
	$totalkost=$totalkost+$kostpris;
	$dk_db=dkdecimal($sum-$kostpris,2);		
	$sum=round($sum,2);
	$kostpris=round($kostpris,2);
	if ($sum) $dk_dg=dkdecimal(($sum-$kostpris)*100/$sum,2);		
	else $dk_dg='0,00';
	if ($feltnavn=='sum') $tmp=$sum;
	elseif ($feltnavn=='moms') $tmp=$moms;
	elseif ($feltnavn=='sum_m_moms') $tmp=$sum_m_moms;
	$tmp=dkdecimal($tmp,2);
	if ($genberegn) {print "<span title= 'db: $dk_db - dg: $dk_dg%'>$tmp/$dk_db/$dk_dg%<br></span>";}
	else {
		if ($udlignet) $span="style='color: #000000;' title='db: $dk_db - dg: $dk_dg%'";
		else $span="style='color: #FF0000;' title='".findtekst(1442, $sprog_id)."\r\ndb: $dk_db - dg: $dk_dg%'";
		print "<span $span>$tmp<br></span>";
	}
}

if ($valg=="ordrer") {
	if ($menu=='T') {
		print "</tfoot></table>";
		print "</tbody></table>";
	} else {
		print "</tbody></table>";
		print "</tbody></table>";
		print "</tbody></table>";
	}
}

if ($valg=="faktura") {
	if ($menu=='T') {
		print "</tfoot></table>";
		print "</tbody></table>";
	} else {
		print "</tfoot></table>";
		print "</tbody></table>";
	}
}

if ($valg=="tilbud"  && !$hurtigfakt) {
	if ($menu=='T') {
		print "</tfoot></table>";
		print "</tbody></table>";
	} else {
		print "</tfoot></table>";
		print "</tbody></table>";
	}
}

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
function select_valg( $valg, $box ){  #20210623
	global $bruger_id, $sprog_id, $firmanavn1;
	global $beskrivelse,$ordrenr1,$kontonr1,$fakturanr1,$fakturadate1,$nextfakt1 ;
  
  	if ($valg=="tilbud") {
		$qtxt = "select * from grupper where art = 'OLV' and kode = 'tilbud' and kodenr = '$bruger_id'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			return $r[$box];
		} else {
			switch($box){
				case "box3":
					return "ordrenr,ordredate,kontonr,firmanavn,ref,sum";
				case "box5":
					return "right,left,left,left,left,right";
				case "box4":
					return "50,100,100,150,100,100";
				case "box6":
					return "".findtekst(888,$sprog_id).".,".findtekst(888,$sprog_id).",".findtekst(804,$sprog_id).".,".findtekst(360,$sprog_id).",".findtekst(884,$sprog_id).",".findtekst(890,$sprog_id).""; #20210318
				default :
				return "choose a box";
			}
		}
	} elseif ($valg=="ordrer") {
		$qtxt = "select * from grupper where art = 'OLV' and kode = 'ordrer' and kodenr = '$bruger_id'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			return $r[$box];
		} else {
			switch ($box) {
				case "box3":
					return "ordrenr,ordredate,levdate,kontonr,firmanavn,ref,sum";
				case "box5":
					return "right,left,left,left,left,left,right";
				case "box4":
					return "50,100,100,100,150,100,100";
				case "box6":
					return "".findtekst(500,$sprog_id).".,".findtekst(881,$sprog_id).",".findtekst(886,$sprog_id).",".findtekst(804,$sprog_id).".,".findtekst(360,$sprog_id).",".findtekst(884,$sprog_id).",".findtekst(887,$sprog_id)."";
				default:
				return "choose a box";
			}
	  }
  } elseif ($valg=="faktura") {
		$qtxt = "select * from grupper where art = 'OLV' and kode = 'faktura' and kodenr = '$bruger_id'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			return $r[$box];
		} else {
			switch($box){
				case "box3":
					return "ordrenr,ordredate,fakturanr,fakturadate,nextfakt,kontonr,firmanavn,ref,sum";
				case "box5":    
					return "right,left,right,left,left,left,left,left,right";
				case "box4":    
					return "50,100,100,100,100,150,100,100,100";
				case "box6":    
					return "".findtekst(500,$sprog_id).",".findtekst(881,$sprog_id).",".findtekst(882,$sprog_id).",".findtekst(883,$sprog_id).",Genfakt.,".findtekst(804,$sprog_id).",".findtekst(360,$sprog_id).",".findtekst(884,$sprog_id).",".findtekst(885,$sprog_id)."";
				default:
				return "choose a box";
			}
		}
	}
}


?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.hover-highlight').forEach(row => {
    const cells = Array.from(row.querySelectorAll('td'))
    if (cells.length < 2) return
    const link = cells[1].querySelector('a')
    if (!link || !link.href) return

    // give pointer cursor only to all but the last 2 cells
    cells.slice(0, cells.length - 2)
         .forEach(cell => cell.style.cursor = 'pointer')

    row.addEventListener('click', e => {
      const td = e.target.closest('td')
      if (!td) return
      const idx = cells.indexOf(td)
      // ignore clicks on the last two cells
      if (idx >= cells.length - 2) return
      window.location.href = link.href
    })
  })
})
</script>