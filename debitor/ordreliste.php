<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/ordreliste.php -----patch 4.1.1 ----2025-11-27--------------
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
// 14082025 Sawaneh Fix invoicelist for english language
// 20251016 MS Changed "$confirm1" and "confirm('$confirm1 $valg?')" to allow complete translation
// 20251104 LOE General 0verhaul of this file to fit the new grid framework.
@session_start();
$s_id = session_id();

$css = "../css/std.css?v=24";




print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/overlib.js\"></script>";

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
$sprog_id= if_isset($sprog_id,1);
/* 
* check for popup blocker 
*/
?>
<script>
function checkPopupBlocked() {
    var popup = window.open('', 'test', 'width=1,height=1');
    
    if (!popup || popup.closed || typeof popup.closed == 'undefined') {
        // Popup blocked
        return true;
    } else {
        // Popup allowed - close test popup
        popup.close();
        return false;
    }
}

const res = checkPopupBlocked();
if (res) {
	// Alert the user about the popup blocker (Dansk translation)
	alert("<?php echo findtekst('2719|Din browser blokerer pop-up vinduer. For at kunne bruge rapportfunktionen skal du tillade pop-up vinduer for denne side.', $sprog_id)?>");
} else {
	// Proceed with the report functionality
	console.log("Pop-up allowed, proceeding with report functionality.");
}
</script>
<?php
/* 
* end check for popup blocker 
*/


# >> Date picker scripts <<
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/jquery-3.6.4.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/moment.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/daterangepicker.min.js\" defer></script>";
print '<link rel="stylesheet" type="text/css" href="../css/daterangepicker.css" />';
include("../includes/row-hover-style-with-links.js.php");
include("../includes/datepkr.php");


global $color;
 //	
#print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Ordreliste - Kunder</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";

$aa           = findtekst('360|Firmanavn', $sprog_id);
$firmanavn1   = ucfirst(str_replace(' ','_', $aa));
$bb           = findtekst('107|Ordrer', $sprog_id);
$ordrer1      = strtolower(str_replace(' ','_', $bb));
$cc           = findtekst('893|faktura', $sprog_id);
$faktura1     = strtolower(str_replace(' ','_', $cc));
$dd           = findtekst('812|Tilbud', $sprog_id);
$tilbud1      = strtolower(str_replace(' ','_', $dd));
$ee           = findtekst('892|Ordrelistevisning', $sprog_id);
$beskrivelse  = strtolower(str_replace(' ','_', $ee)); //20210527

$ff           = findtekst('500|Ordrenr.', $sprog_id);
$ordrenr1     = strtolower(str_replace(' ','_', $ff));
$gg           = findtekst('881|Ordredato', $sprog_id);
$ordredate1   = strtolower(str_replace(' ','_', $gg));
$hh           = findtekst('804|Kontonr.', $sprog_id);
$kontonr1     = strtolower(str_replace(' ','_', $hh));
$ii           = findtekst('882|Fakt. nr.', $sprog_id);
$fakturanr1   = strtolower(str_replace(' ','_', $ii));
$jj           = findtekst('883|Fakt. dato', $sprog_id);
$fakturadate1 = strtolower(str_replace(' ','_', $jj));
$kk           = findtekst('891|nextfakt', $sprog_id);
$nextfakt1    = strtolower(str_replace(' ','_', $kk));






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
if (isset($_GET['valg'])) {
	setcookie("saldi_ordreliste","$valg");
} else {
	$valg = if_isset($_COOKIE,NULL,'saldi_ordreliste');
	// If konto_id is in GET and valg is not explicitly set, default to faktura (invoices)
	if (isset($_GET['konto_id']) && $_GET['konto_id']) {
		$valg = "faktura";
	}
}

$r2=db_fetch_array(db_select("select max(id) as id from grupper",__FILE__ . " linje " . __LINE__));

if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '3' and box4='on'",__FILE__ . " linje " . __LINE__))) $hurtigfakt='on';
if ($valg=="tilbud" && $hurtigfakt) $valg="ordrer"; //20210323
if ($valg == 'invoice') $valg = 'faktura';
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
			$c = explode(",",$box6);
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

// ADD MISSING POST DATA HANDLING
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $submit = if_isset($_POST, NULL, 'submit');
    if ($submit) {
        if (strstr($submit, "Genfaktur")) $submit = "Genfakturer";
        $find = if_isset($_POST['find'], array());
        $valg = if_isset($_POST['valg'], $valg);
        $sort = if_isset($_POST['sort'], $sort);
        $nysort = if_isset($_POST['nysort'], $nysort);
        $firma = if_isset($_POST['firma'], NULL);
        $kontoid = if_isset($_POST['kontoid'], $kontoid);
        $firmanavn_ant = if_isset($_POST['firmanavn_antal'], NULL);
    } elseif (isset($_POST["clear"])) { 
        // Clear all search criteria
        $find = array();
        $konto_id = NULL;
        $udvaelg = NULL;
        $kontoid = NULL;
        $firma = NULL;
        $firmanavn_ant = NULL;
        $datagrid_id = if_isset($_POST, NULL, 'datagrid_id');

        if ($datagrid_id) {
            // Clear datagrid filters
            db_modify("delete from datatables where tabel_id = '$datagrid_id' and user_id = '$bruger_id'", __FILE__ . " linje " . __LINE__);
            
        }
        
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
    
    // Process order selections
    $ordre_id = if_isset($_POST, NULL, 'ordre_id'); 
    $checked = if_isset($_POST, NULL, 'checked');
}
if (isset($_POST['check'])||isset($_POST['uncheck'])) {
	if (isset($_POST['check'])) $check_all='on';
	else $uncheck_all='on';
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



if ($menu=='T') include_once 'ordLstIncludes/topMenu.php';
elseif ($menu=='S') include_once 'ordLstIncludes/topLine.php';
else include_once 'ordLstIncludes/oldTopLine.php';
include(get_relative() . "includes/orderFuncIncludes/grid_order.php");



////// Tutorial //////

$steps = array();
$steps[] = array(
	"selector" => "#ordrer",
	"content" => findtekst('2610|Her ser du en liste af alle dine ordrer', $sprog_id)."."
);
$steps[] = array(
    "selector" => "#ny",
    "content" => findtekst('2611|For at oprette en ny ordre, klik her', $sprog_id)."."
);

$steps[] = array(
    "selector" => "#visning",
    "content" => findtekst('2612|For at ændre, hvad der vises i oversigten, klik her', $sprog_id)."."
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




########################


// Grid-specific parameters
$grid_id = "ordrelst_$valg";

// Debug logging
$debug_file = "../temp/$db/ordreliste_debug.log";
$debug_log = array();
$debug_log[] = "=== DEBUG START " . date('Y-m-d H:i:s') . " ===";
$debug_log[] = "konto_id from GET: " . (isset($_GET['konto_id']) ? $_GET['konto_id'] : 'NOT SET');
$debug_log[] = "konto_id from settings: " . (isset($konto_id) ? $konto_id : 'NOT SET');
$debug_log[] = "valg: $valg";
$debug_log[] = "grid_id: $grid_id";

// If konto_id is in GET and search fields are not already set, pre-populate from adresser
if (isset($_GET['konto_id']) && $_GET['konto_id']) {
	$debug_log[] = "konto_id found in GET, processing...";
	
	// Initialize search array if it doesn't exist
	if (!isset($_GET['search'])) {
		$_GET['search'] = array();
		$debug_log[] = "Initialized \$_GET['search'] array";
	}
	if (!isset($_GET['search'][$grid_id])) {
		$_GET['search'][$grid_id] = array();
		$debug_log[] = "Initialized \$_GET['search'][$grid_id] array";
	}
	
	$debug_log[] = "Current search values: " . json_encode($_GET['search'][$grid_id]);
	
	// Only pre-populate if search fields are empty
	if (empty($_GET['search'][$grid_id]['firmanavn']) && empty($_GET['search'][$grid_id]['kontonr'])) {
		$konto_id_from_get = db_escape_string($_GET['konto_id']);
		$qtxt = "SELECT firmanavn, kontonr FROM adresser WHERE id = '$konto_id_from_get'";
		$debug_log[] = "Query to fetch customer: $qtxt";
		
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$debug_log[] = "Customer found: " . json_encode($r);
			
			if (!empty($r['firmanavn'])) {
				$_GET['search'][$grid_id]['firmanavn'] = $r['firmanavn'];
				$debug_log[] = "Set firmanavn search: " . $r['firmanavn'];
			}
			if (!empty($r['kontonr'])) {
				$_GET['search'][$grid_id]['kontonr'] = $r['kontonr'];
				$debug_log[] = "Set kontonr search: " . $r['kontonr'];
			}
		} else {
			$debug_log[] = "ERROR: No customer found with id = $konto_id_from_get";
		}
	} else {
		$debug_log[] = "Search fields already populated, skipping";
	}
} else {
	$debug_log[] = "konto_id NOT in GET or empty";
}

$grid_search = if_isset($_GET['search'][$grid_id], array());
$debug_log[] = "Final grid_search: " . json_encode($grid_search);
$debug_log[] = "Full \$_GET['search']: " . json_encode(if_isset($_GET['search'], array()));
$grid_offset = if_isset($_GET['offset'][$grid_id], 0);
$grid_rowcount = if_isset($_GET['rowcount'][$grid_id], 100);
$grid_sort = if_isset($_GET['sort'][$grid_id], '');

// Also check how many orders exist for this konto_id
if ($konto_id) {
	$test_query = "SELECT COUNT(*) as count FROM ordrer WHERE konto_id = '$konto_id' AND status >= 3";
	if ($test_r = db_fetch_array(db_select($test_query, __FILE__ . " linje " . __LINE__))) {
		$debug_log[] = "Total invoices for konto_id $konto_id: " . $test_r['count'];
	}
	
	// Check with firmanavn search
	if (!empty($grid_search['firmanavn'])) {
		$test_query2 = "SELECT COUNT(*) as count FROM ordrer WHERE firmanavn = '" . db_escape_string($grid_search['firmanavn']) . "' AND status >= 3";
		if ($test_r2 = db_fetch_array(db_select($test_query2, __FILE__ . " linje " . __LINE__))) {
			$debug_log[] = "Total invoices for firmanavn '{$grid_search['firmanavn']}': " . $test_r2['count'];
		}
	}
}


// Initialize totals
$ialt = 0;
$ialt_m_moms = 0;
$ialt_kostpris = 0;

// Column configuration
$columns = array();

// Checkbox column for selection
$metaColumnHeaders = ['']; //

$columns[] = array(
    "field" => "ordrenr",
    "headerName" => findtekst('500|Ordrenr.', $sprog_id),
    "width" => "0.8",
    "align" => "right",
    "type"  => "text",
    "sortable" => true,
    "defaultSort" => true,
    "defaultSortDirection" => "desc",
    "searchable" => true,
    "render" => function ($value, $row, $column) {
        global $brugernavn;
        
        $href = "ordre.php?tjek={$row['id']}&id={$row['id']}&returside=" . urlencode($_SERVER["REQUEST_URI"]);
        
        // Parse timestamp properly
        $timestamp = $row['tidspkt'];
        if (strpos($timestamp, ':')) {
            $timestamp = strtotime(date('Y/m/d') . " " . $timestamp);
        } else {
            $timestamp = strtotime($timestamp);
        }
        $current_time = time();
        $who = $row['hvem'];
        
        // Check if order is editable (same logic as original)
        $is_editable = ($row['status'] >= 3 || ($current_time - $timestamp) > 3600 || $who == $brugernavn || $who == '');
        
        if ($is_editable) {
            $style = "cursor: pointer; text-decoration: underline;";
            $title = findtekst('1522|Fortsæt med at redigere ordren', $sprog_id);
            $onclick = "onClick=\"window.location.href='$href'\"";
        } else {
            $style = "color: #FF0000; cursor: not-allowed;";
            $title = findtekst('1421|Ordre er i brug af', $sprog_id) . " $who";
            $onclick = "";
        }
        
        if ($row['art'] == 'DK') {
            $display = "(KN)&nbsp;$value";
        } else if ($row['restordre'] == '1') {
            $display = "(R)&nbsp;$value";
        } else {
            $display = $value;
        }
        
        return "<td align='$column[align]' style='$style' $onclick title='$title'>$display</td>";
    }
);

$columns[] = array(
    "field" => "ordredate",
    "headerName" => findtekst('881|Ordredato', $sprog_id),
    "width" => "1",
    "type" => "date",
    "searchable" => true,
    "render" => function ($value, $row, $column) {
        return "<td align='$column[align]'>" . dkdato($value) . "</td>";
    }
   
);

$columns[] = array(
    "field" => "levdate", 
    "headerName" => findtekst('886|Dato for levering', $sprog_id),
    "width" => "1",
    "type" => "date",
    "searchable" => true,
    "render" => function ($value, $row, $column) {
        return "<td align='$column[align]'>" . dkdato($value) . "</td>";
    }
);

$columns[] = array(
    "field" => "fakturanr",
    "headerName" => findtekst('882|Fakt. nr.', $sprog_id),
    "width" => "0.8",
    "align" => "right",
    "searchable" => true,
    "hidden" => ($valg != "faktura")
);

$columns[] = array(
    "field" => "fakturadate",
    "headerName" => findtekst('883|Fakt. dato', $sprog_id),
    "width" => "1",
    "type" => "date",
    "searchable" => true,
    "hidden" => ($valg != "faktura")
);

##firmanavn colum
$columns[] = array(
    "field" => "firmanavn",
    "headerName" => findtekst('360|Firmanavn', $sprog_id),
    "width" => "2",
    "type" => "text", 
    "searchable" => true,
    "sqlOverride" => "o.firmanavn",
    
    "generateSearch" => function($column, $term) use ($konto_id) {
        global $konto_id; // Access global konto_id if filtering by customer
        
        $field = $column['sqlOverride'] ?: $column['field'];
        $term = db_escape_string(trim($term, "'"));
        
        if (empty($term)) {
            return "1=1";
        }
        
        // Special handling if konto_id is set and search doesn't contain ":"
        if ($konto_id && $term && strpos($term, ':') === false) {
            // Find all konto_ids with this company name
            $konto_ids = array();
            $q = db_select("SELECT DISTINCT konto_id FROM ordrer WHERE firmanavn = '$term'", __FILE__ . " linje " . __LINE__);
            
            while ($r = db_fetch_array($q)) {
                $konto_ids[] = $r['konto_id'];
            }
            
            if (count($konto_ids) > 0) {
                // Build OR condition for all matching konto_ids
                $id_conditions = array();
                foreach ($konto_ids as $kid) {
                    $id_conditions[] = "o.konto_id = '$kid'";
                }
                return "(" . implode(" OR ", $id_conditions) . ")";
            } else {
                // No matches found
                return "o.konto_id = '0'"; // Returns no results
            }
        }
        
        // Standard text search
        $term = strtolower($term);
        
        if (strpos($term, '*') !== false) {
            // Wildcard search
            $term = str_replace('*', '%', $term);
            $termLower = mb_strtolower($term);
            $termUpper = mb_strtoupper($term);
            
            return "({$field} LIKE '$term' 
                     OR lower({$field}) LIKE '$termLower' 
                     OR upper({$field}) LIKE '$termUpper')";
        } else {
            // Partial match
            $termLower = mb_strtolower($term);
            $termUpper = mb_strtoupper($term);
            
            return "({$field} = '$term' 
                     OR lower({$field}) LIKE '$termLower' 
                     OR upper({$field}) LIKE '$termUpper' 
                     OR lower({$field}) LIKE '%$termLower%' 
                     OR upper({$field}) LIKE '%$termUpper%')";
        }
    },
    
    "render" => function ($value, $row, $column) {
        return "<td align='$column[align]'>$value</td>";
    }
);

#//

$columns[] = array(
    "field" => "nextfakt",
    "headerName" => "Genfakt.",
    "width" => "1",
    "type" => "date",
    "hidden" => ($valg != "faktura" && $valg != "ordrer")
);

$columns[] = array(
    "field" => "kontonr",
    "headerName" => findtekst('804|Kontonr.', $sprog_id),
    "width" => "1",
    "sqlOverride" => "o.kontonr"
);



$columns[] = array(
    "field" => "ref",
    "headerName" => findtekst('884|Sælger', $sprog_id),
     "width" => "1.5",
    "type" => "dropdown",
    "searchable" => true,
    "dropdownOptions" => function() use ($valg) {
        $options = array();
        // Get unique salesperson values from orders based on current view
        if ($valg == "tilbud") {
            $status_condition = "status < 1";
        } elseif ($valg == "faktura") {
            $status_condition = "status >= 3";
        } else {
            $status_condition = "(status = 1 OR status = 2)";
        }
        
        $qtxt = "SELECT DISTINCT ref FROM ordrer WHERE (art = 'DO' OR art = 'DK' OR (art = 'PO' AND konto_id > '0')) AND $status_condition AND ref IS NOT NULL AND ref != '' ORDER BY ref";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        while ($r = db_fetch_array($q)) {
            $options[] = $r['ref'];
        }
        return $options;
    },
    "render" => function ($value, $row, $column) {
        return "<td align='$column[align]'>$value</td>";
    }
);

$columns[] = array(
    "field" => "sum_m_moms",
    "headerName" => "Sum m. moms",
    "type" => "number",
    "decimalPrecision" => 2,
    "align" => "right",
    "searchable" => true,
    "sqlOverride" => "(o.sum + o.moms)", 
    "generateSearch" => function($column, $term) {
        // Use the sqlOverride for searching
        $field = $column['sqlOverride'];
        $term = db_escape_string(trim($term, "'"));
        
        if (strpos($term, ':') !== false) {
            list($a, $b) = explode(':', $term, 2);
            $a = usdecimal($a);
            $b = usdecimal($b);
            return "({$field} >= $a AND {$field} <= $b)";
        } else {
            $val = usdecimal($term);
            $tmp1 = $val - 0.005;
            $tmp2 = $val + 0.004;
            return "({$field} >= $tmp1 AND {$field} <= $tmp2)";
        }
    }
);


#To be revised later when needed
// $columns[] = array(
//     "field" => "betalt",
//     "headerName" => "Betalt", 
//     "width" => "0.3",
//     "type" => "dropdown",
//     "align" => "center",
//     "searchable" => true,
//     "hidden" => ($valg != "faktura"),
    
//     // Dropdown with clear labels
//     "dropdownOptions" => function() {
//         return array(
//             '' => '',      // Empty = show all
//             '0' => '0',    // Unpaid
//             '1' => '1'     // Paid
//         );
//     },
    
//     // Custom search - UPDATED to match the new query logic
//     "generateSearch" => function($column, $term) use ($valg) {
//         $term = trim($term);
        
//         // Only apply payment search for faktura views
//         if ($term === '' || $valg != "faktura") {
//             return "1=1"; // Show all for non-invoice views or empty search
//         }
        
//         // Build the same CASE logic used in the SELECT (updated version)
//         $case_sql = "
//             CASE 
//                 WHEN o.status >= 3 THEN
//                     CASE 
//                         WHEN EXISTS (
//                             SELECT 1 FROM openpost op 
//                             WHERE op.faktnr = o.fakturanr::text 
//                             AND op.konto_id = o.konto_id 
//                             AND ABS(ROUND(op.amount::numeric, 2) - ROUND((o.sum + o.moms)::numeric, 2)) < 0.01
//                             AND op.udlignet = '1'
//                         ) THEN 1
//                         WHEN o.betalt = '1' THEN 1
//                         ELSE 0
//                     END
//                 ELSE 0
//             END
//         ";
        
//         if ($term === '0') {
//             return "($case_sql = 0)";
//         } elseif ($term === '1') {
//             return "($case_sql = 1)";
//         }
        
//         return "1=1";
//     },
    
//     // Render with icons and colors
//     "render" => function ($value, $row, $column) use ($valg) {
//         if ($valg != "faktura") {
//             return "<td align='$column[align]'>-</td>";
//         }
        
//         // The 'udlignet' field from query already has the calculated value
//         $is_paid = (intval($row['udlignet']) === 1);
        
//         if ($is_paid) {
//             $icon = "✓";
//             $color = "#008000"; // Green
//             $title = "Betalt";
//         } else {
//             $icon = "✗";
//             $color = "#FF0000"; // Red
//             $title = "Ubetalt";
//         }
        
//         return "<td align='$column[align]' style='color: $color; font-weight: bold; font-size: 16px;' title='$title'>$icon</td>";
//     }
// );

$columns[] = array(
    "field" => "betalingsbet",
    "headerName" => findtekst('56|Betalingsbet.', $sprog_id),
    "width" => "1",
    "type" => "dropdown",
    "align" => "left",
    "searchable" => true,
    "hidden" => ($valg != "faktura"), // Only show for invoices
    
    "dropdownOptions" => function() use ($valg) {
        $options = array();
        
        if ($valg == "tilbud") {
            $status_condition = "status < 1";
        } elseif ($valg == "faktura") {
            $status_condition = "status >= 3";
        } else {
            $status_condition = "(status = 1 OR status = 2)";
        }
        
        $qtxt = "SELECT DISTINCT betalingsbet 
                 FROM ordrer 
                 WHERE (art = 'DO' OR art = 'DK' OR (art = 'PO' AND konto_id > '0')) 
                 AND $status_condition 
                 AND betalingsbet IS NOT NULL 
                 AND trim(betalingsbet) != '' 
                 ORDER BY betalingsbet";
        
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        while ($r = db_fetch_array($q)) {
            if (trim($r['betalingsbet']) != '') {
                $options[] = $r['betalingsbet'];
            }
        }
        return $options;
    },
    
    "generateSearch" => function($column, $term) {
        $term = db_escape_string(trim($term));
        if ($term === '') {
            return "1=1";
        }
        // Exact match for payment terms
        return "(o.betalingsbet = '$term')";
    },
    
    "render" => function ($value, $row, $column) {
        $display = htmlspecialchars($value);
        return "<td align='$column[align]'>$display</td>";
    }
);

$columns[] = array(
    "field" => "sum",
    "headerName" => ($valg == "faktura") ? findtekst('885|Fakturasum', $sprog_id) : 
                     (($valg == "tilbud") ? findtekst('890|Tilbudssum', $sprog_id) : 
                     findtekst('887|Ordresum', $sprog_id)),
    "width" => "1",
    "type" => "number",
    "align" => "right",
    "decimalPrecision" => 2, 
    "searchable" => true,
    "render" => function ($value, $row, $column) use ($valg, &$ialt, &$ialt_m_moms, &$ialt_kostpris) {
        global $genberegn; 
        
        if ($genberegn) {
            $kostpris = genberegn($row['id']); 
            $row['kostpris'] = $kostpris;
        }
        
        $formatted = dkdecimal($value, 2);
        
        // Add to totals
        $ialt += floatval($value);
        $ialt_m_moms += $row['sum_m_moms'];
        $ialt_kostpris += $row['kostpris'];
        
        if ($valg == "faktura") {
            // Use the udlignet field calculated in query
            $udlignet = intval($row['udlignet']);
            $kostpris = $row['kostpris'];
            
            // Initialize variables
            $dk_db = '0,00';
            $dk_dg = '0,00';
            
            // Convert value to numeric if it's a string with comma
            $value_numeric = is_numeric($value) ? floatval($value) : floatval(str_replace(',', '.', $value));
            
            if (!empty($kostpris) && !empty($value) && $value_numeric != 0) {
                $kostpris_numeric = is_numeric($kostpris) ? floatval($kostpris) : floatval(str_replace(',', '.', $kostpris));
                $dk_db = dkdecimal($value_numeric - $kostpris_numeric, 2);
                $dk_dg = dkdecimal(($value_numeric - $kostpris_numeric) * 100 / $value_numeric, 2);
            }

            
            $style = $udlignet ? "color: #000000;" : "color: #FF0000;";
            $title = $udlignet ? "db: $dk_db - dg: $dk_dg%" : findtekst('1442|Ikke udlignet', $sprog_id) . "\r\ndb: $dk_db - dg: $dk_dg%";
            
            // return "<td align='$column[align]' style='$style' title='$title'>$formatted/$dk_db/$dk_dg%</td>";
            return "<td align='$column[align]' style='$style' title='$title'>$formatted</td>";
        }
        return "<td align='$column[align]'>$formatted</td>";
    }
);

$columns[] = array(
    "field" => "checkbox",
    "headerName" => "",
    "width" => "0.3",
    "sortable" => false,
    "searchable" => false,
    "align" => "center",
    "render" => function ($value, $row, $column) {
        $checked = $row['selected'] ? 'checked' : '';
        return "<td align='center'><input type='checkbox' name='checked[{$row['id']}]' $checked class='deliveryNoteSelect'></td>";
    }
);


// $columns[] = array(
//     "field" => "status",
//     "headerName" => "Status",
//     "width" => "1",
//     "hidden" => true,
//     "render" => function ($value, $row, $column) {
//         $status_text = "";
//         switch($row['status']) {
//             case 0: $status_text = "Tilbud"; break;
//             case 1: $status_text = "Ordre"; break;
//             case 2: $status_text = "Ordre"; break;
//             case 3: $status_text = "Faktura"; break;
//             case 4: $status_text = "Faktura"; break;
//         }
//         return "<td align='$column[align]'>$status_text</td>";
//     }
// );

// Action buttons column
$columns[] = array(
    "field" => "actions",
    "headerName" => "Handlinger",
    "width" => "0.5",
    "sortable" => false,
    "searchable" => false,
    "align" => "center",
    "render" => function ($value, $row, $column) use ($valg, $sprog_id) {
        $actions = "<td align='center'><div style='display:flex;gap:5px;justify-content:center;'>";
        
        if ($valg == "ordrer") {
            // Plukliste
            $actions .= "<a href='formularprint.php?id={$row['id']}&formular=9&udskriv_til=PDF' target='_blank' title='".findtekst('2723|Klik for at udskrive', $sprog_id)." ".lcfirst(findtekst('574|Plukliste', $sprog_id))."'><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M216-96q-29.7 0-50.85-21.15Q144-138.3 144-168v-412q-21-8-34.5-26.5T96-648v-144q0-29.7 21.15-50.85Q138.3-864 168-864h624q29.7 0 50.85 21.15Q864-821.7 864-792v144q0 23-13.5 41.5T816-580v411.86Q816-138 794.85-117T744-96H216Zm0-480v408h528v-408H216Zm-48-72h624v-144H168v144Zm216 240h192v-72H384v72Zm96 36Z'/></svg></a>";
            
            // Ordrebekræftelse
            $actions .= "<a href='formularprint.php?id={$row['id']}&formular=2&udskriv_til=PDF' target='_blank' title='".findtekst('2723|Klik for at udskrive', $sprog_id)." ".lcfirst(findtekst('575|Ordrebekræftelse', $sprog_id))."'><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z'/></svg></a>";
            
            
            // if ($row['email']) {
            //     $actions .= "<a href='formularprint.php?id={$row['id']}&formular=2&udskriv_til=email' target='_blank' title='".findtekst('2724|Klik for at sende ordrebekræftelse via e-mail', $sprog_id)."' onclick=\"return confirm('".findtekst('2725|Er du sikker på at du vil sende ordrebekræftelsen? Kundens e-mail:', $sprog_id)." {$row['email']}')\"><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M168-192q-29.7 0-50.85-21.16Q96-234.32 96-264.04v-432.24Q96-726 117.15-747T168-768h624q29.7 0 50.85 21.16Q864-725.68 864-695.96v432.24Q864-234 842.85-213T792-192H168Zm312-240L168-611v347h624v-347L480-432Zm0-85 312-179H168l312 179Zm-312-94v-85 432-347Z'/></svg></a>";
            // }
            
        } elseif ($valg == "faktura") {
            // Følgeseddel
            $actions .= "<a href='formularprint.php?id={$row['id']}&formular=3&udskriv_til=PDF' target='_blank' title='".findtekst('2723|Klik for at udskrive', $sprog_id)." ".lcfirst(findtekst('576|Følgeseddel', $sprog_id))."'><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M216-96q-29.7 0-50.85-21.15Q144-138.3 144-168v-412q-21-8-34.5-26.5T96-648v-144q0-29.7 21.15-50.85Q138.3-864 168-864h624q29.7 0 50.85 21.15Q864-821.7 864-792v144q0 23-13.5 41.5T816-580v411.86Q816-138 794.85-117T744-96H216Zm0-480v408h528v-408H216Zm-48-72h624v-144H168v144Zm216 240h192v-72H384v72Zm96 36Z'/></svg></a>";
            
            // Faktura
            $actions .= "<a href='formularprint.php?id={$row['id']}&formular=4&udskriv_til=PDF' target='_blank' title='".findtekst('2723|Klik for at udskrive', $sprog_id)." ".lcfirst(findtekst('643|Faktura', $sprog_id))."'><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z'/></svg></a>";
            
            // Email faktura
            // if ($row['email']) {
            //     $actions .= "<a href='formularprint.php?id={$row['id']}&formular=4&udskriv_til=email' target='_blank' title='".findtekst('2726|Klik for at sende faktura via e-mail', $sprog_id)."' onclick=\"return confirm('".findtekst('2727|Er du sikker på at du vil sende fakturaen? Kundens e-mail:', $sprog_id)." {$row['email']}')\"><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M168-192q-29.7 0-50.85-21.16Q96-234.32 96-264.04v-432.24Q96-726 117.15-747T168-768h624q29.7 0 50.85 21.16Q864-725.68 864-695.96v432.24Q864-234 842.85-213T792-192H168Zm312-240L168-611v347h624v-347L480-432Zm0-85 312-179H168l312 179Zm-312-94v-85 432-347Z'/></svg></a>";
            // }
            
        } elseif ($valg == "tilbud") {
            // Tilbud
            $actions .= "<a href='formularprint.php?id={$row['id']}&formular=1&udskriv_til=PDF' target='_blank' title='".findtekst('2723|Klik for at udskrive', $sprog_id)." ".lcfirst(findtekst('812|Tilbud', $sprog_id))."'><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z'/></svg></a>";
            
            // Email tilbud
            // if ($row['email']) {
            //     $actions .= "<a href='formularprint.php?id={$row['id']}&formular=1&udskriv_til=email' target='_blank' title='".findtekst('2728|Klik for at sende tilbud via e-mail', $sprog_id)."' onclick=\"return confirm('".findtekst('2729|Er du sikker på at du vil sende tilbuddet?\nKundens e-mail:', $sprog_id)." {$row['email']}')\"><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M168-192q-29.7 0-50.85-21.16Q96-234.32 96-264.04v-432.24Q96-726 117.15-747T168-768h624q29.7 0 50.85 21.16Q864-725.68 864-695.96v432.24Q864-234 842.85-213T792-192H168Zm312-240L168-611v347h624v-347L480-432Zm0-85 312-179H168l312 179Zm-312-94v-85 432-347Z'/></svg></a>";
            // }
        }
        
        $actions .= "</div></td>";
        return $actions;
    }
);










// Filters setup
$filters = array();

// Order type filter

$filters[] = array(
    "filterName" => "Ordretype",
    "joinOperator" => "or",
    "options" => array(
        array(
            "name" => "Tilbud",
            "checked" => ($valg == "tilbud") ? "checked" : "",
            "sqlOn" => "o.status < 1",
            "sqlOff" => "",
        ),
        array(
            "name" => "Ordrer",
            "checked" => ($valg == "ordrer") ? "checked" : "",
            "sqlOn" => "(o.status = 1 OR o.status = 2)",
            "sqlOff" => "",
        ),
        array(
            "name" => "Fakturaer",
            "checked" => ($valg == "faktura") ? "checked" : "",
            "sqlOn" => "o.status >= 3",
            "sqlOff" => "",
        ),
        array(
            "name" => "PBS",
            "checked" => ($valg == "pbs") ? "checked" : "",
            "sqlOn" => "o.art = 'PO' AND o.konto_id > '0'", // PBS orders
            "sqlOff" => "",
        )
    )
);
###############################Data configuration##############*****************++++++++++++++++

// Build search conditions from legacy system
$legacy_search_conditions = "";
if (!empty($find) && is_array($find)) {
    foreach ($find as $index => $search_term) {
        if (!empty($search_term) && isset($vis_felt[$index])) {
            $field = $vis_felt[$index];
            $search_term = db_escape_string(trim($search_term));
            
            // Handle different field types like in old system
            switch($field) {
                case 'betalt':
                    if ($search_term === '0' || $search_term === '1') {
                        $legacy_search_conditions .= " AND o.betalt = '$search_term'";
                    }
                    break;
                case 'sum_m_moms':
                    if (strpos($search_term, ':') !== false) {
                        list($a, $b) = explode(':', $search_term, 2);
                        $a = usdecimal($a);
                        $b = usdecimal($b);
                        $legacy_search_conditions .= " AND (o.sum + o.moms) >= $a AND (o.sum + o.moms) <= $b";
                    } else {
                        $val = usdecimal($search_term);
                        $legacy_search_conditions .= " AND (o.sum + o.moms) = $val";
                    }
                    break;
                case 'ordrenr':
                    if (strlen($search_term) >= 11) $search_term = substr($search_term, 0, 10);
                    $search_term *= 1;
                    $legacy_search_conditions .= " AND o.ordrenr = '$search_term'";
                    break;
                case 'kontonr':
                    $search_term *= 1;
                    $legacy_search_conditions .= " AND o.kontonr = '$search_term'";
                    break;
                case 'fakturanr':
                    if (strpos($search_term, ':') !== false) {
                        list($a, $b) = explode(':', $search_term, 2);
                        $legacy_search_conditions .= " AND o.fakturanr >= '$a' AND o.fakturanr <= '$b'";
                    } else {
                        $legacy_search_conditions .= " AND o.fakturanr = '$search_term'";
                    }
                    break;
                case 'kundegruppe':
                    if (is_numeric($search_term)) {
                        $legacy_search_conditions .= " AND a.gruppe = '$search_term'";
                    }
                    break;
                default:
                    // Handle text fields and dropdowns
                    if (in_array($field, array('firmanavn', 'ref', 'email', 'kontakt'))) {
                        $legacy_search_conditions .= " AND o.$field ILIKE '%$search_term%'";
                    } else {
                        $legacy_search_conditions .= " AND o.$field = '$search_term'";
                    }
                    break;
            }
        }
    }
}

// Add konto_id filter only if konto_id is explicitly in GET (not from settings)
if (isset($_GET['konto_id']) && $_GET['konto_id']) {
    $konto_id_from_get = db_escape_string($_GET['konto_id']);
    $legacy_search_conditions .= " AND o.konto_id = '$konto_id_from_get'";
    $debug_log[] = "Added konto_id filter from GET: o.konto_id = '$konto_id_from_get'";
} else {
    $debug_log[] = "konto_id not in GET, skipping konto_id filter (konto_id from settings: " . ($konto_id ? $konto_id : 'none') . ")";
}

$debug_log[] = "legacy_search_conditions: $legacy_search_conditions";

##################
// Build the base WHERE conditions based on order type
$base_where_conditions = "";
if ($valg == "tilbud") {
    $base_where_conditions = "o.status < 1";
} elseif ($valg == "faktura") {
    $base_where_conditions = "o.status >= 3";
} else {
    $base_where_conditions = "(o.status = 1 OR o.status = 2)";
}

$debug_log[] = "base_where_conditions: $base_where_conditions";

$data = array(
    "table_name" => "ordrer",
    "query" => "SELECT 
        o.id as id,
        o.ordrenr as ordrenr,
        o.ordredate as ordredate,
        o.levdate as levdate,
        o.fakturanr as fakturanr,
        o.fakturadate as fakturadate,
        o.nextfakt as nextfakt,
        o.kontonr as kontonr,
        o.firmanavn as firmanavn,
        o.ref as ref,
        o.sum::numeric as sum,
        o.moms::numeric as moms,
        (o.sum::numeric + o.moms::numeric) as sum_m_moms,
        o.kostpris::numeric as kostpris,
        o.status as status,
        o.art as art,
        o.restordre as restordre,
        o.email as email,
        o.konto_id as konto_id,
        o.hvem as hvem,
        o.tidspkt as tidspkt,
        o.betalingsbet as betalingsbet,
        o.betalt as betalt_ordrer,
        
        -- FIXED: Better payment status calculation
        CASE 
            WHEN o.status >= 3 THEN -- Only check payment for invoices
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM openpost op 
                        WHERE op.faktnr = o.fakturanr::text 
                        AND op.konto_id = o.konto_id 
                        AND ABS(ROUND(op.amount::numeric, 2) - ROUND((o.sum + o.moms)::numeric, 2)) < 0.01
                        AND op.udlignet = '1'
                    ) THEN 1
                    WHEN o.betalt = '1' THEN 1
                    ELSE 0
                END
            ELSE 0 -- Not an invoice, so not paid
        END as udlignet,
        
        CASE 
            WHEN EXISTS (
                SELECT 1 FROM ordrelinjer ol 
                WHERE ol.ordre_id = o.id 
                AND ((ol.leveret > 0 AND ol.antal > ol.leveret) 
                     OR (ol.antal > (ol.leveres + ol.leveret)))
            ) THEN 'Mangler'
            WHEN EXISTS (
                SELECT 1 FROM ordrelinjer ol 
                WHERE ol.ordre_id = o.id 
                AND ol.leveret > 0 AND ol.antal = ol.leveret
            ) THEN 'Leveret'
            ELSE 'Intet'
        END as levstatus
    FROM ordrer o
    LEFT JOIN adresser a ON a.id = o.konto_id
    WHERE (o.art = 'DO' OR o.art = 'DK' OR (o.art = 'PO' AND o.konto_id > '0')) 
    AND $base_where_conditions
    AND {{WHERE}}
    $legacy_search_conditions
    ORDER BY {{SORT}}",
    
    "rowStyle" => function ($row) use ($valg) {
        // Additional styles
        // if ($valg == "ordrer") {
        //     if ($row['levstatus'] == "Mangler") return "background-color: #ffebee;";
        //     if ($row['levstatus'] == "Leveret") return "background-color: #e8f5e8;";
        // }
        
        // if ($valg == "faktura") {
        //     $is_paid = (intval($row['udlignet']) === 1);
            
        //     if ($is_paid) {
        //         return "background-color: #e8f5e8;";
        //     } else {
        //         if (!empty($row['fakturadate'])) {
        //             $invoice_date = strtotime($row['fakturadate']);
        //             $due_date = $invoice_date + (30 * 86400);
                    
        //             if (time() > $due_date) {
        //                 #return "background-color: #ffcccc;";
        //             }
        //         }
        //         #return "background-color: #ffebee;";
        //          return "background-color: #e8f5e8;";
        //     }
        // }
        
        return "";
    },
    "columns" => $columns,
    "filters" => $filters,
    'metaColumnHeaders' => $metaColumnHeaders,
);

// Debug: Log query structure
$debug_log[] = "Query base: " . substr($data['query'], 0, 200) . "...";
$debug_log[] = "Final WHERE will include: $base_where_conditions";
$debug_log[] = "Legacy search conditions: $legacy_search_conditions";

// Write debug log to file
$debug_log[] = "=== DEBUG END ===";
@file_put_contents($debug_file, implode("\n", $debug_log) . "\n\n", FILE_APPEND | LOCK_EX);

##############


// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $checked_orders = if_isset($_POST['checked'], array());
    
    $submit = if_isset($_POST['submit'], '');
    $slet_valgte = if_isset($_POST['slet_valgte'], '');
    
    // Handle Genfakturer and Ret actions
    if ($submit == "Genfakturer" || $submit == findtekst('1206|Ret', $sprog_id)) {
        $genfakt = "";
        $y = 0;
        
        foreach ($checked_orders as $order_id => $value) {
            if ($value == "on") {
                $y++;
                if (!$genfakt) $genfakt = $order_id;
                else $genfakt = $genfakt . "," . $order_id;
            }
        }
        
        $alert2 = findtekst('1419|Ingen fakturaer er markeret til genfakturering!', $sprog_id);
        
        if ($y > 0) {
            if ($submit == findtekst('1206|Ret', $sprog_id)) {
                print "<meta http-equiv=\"refresh\" content=\"0;URL=ret_genfakt.php?ordreliste=$genfakt\">";
            } else {
                print "<meta http-equiv=\"refresh\" content=\"0;URL=genfakturer.php?id=-1&ordre_antal=$y&genfakt=$genfakt\">";
            }
            exit;
        } else {
            print "<BODY onLoad=\"javascript:alert('$alert2')\">";
        }
    }
    
    // Handle other actions

    #Setup paid or unpaid invoice// enable when needed
    // if ($submit == "Mark as paid" || $submit == "Mark as unpaid") {
    //     $action = ($submit == "Mark as paid") ? 'paid' : 'unpaid'; 
    //     $updated_count = 0;
    
    //     foreach ($checked_orders as $order_id => $value) {
    //         if ($value == "on") {
    //             // Update ordrer.betalt
    //             $new_value = ($action == 'paid') ? '1' : '0';
                
    //             db_modify("UPDATE ordrer SET betalt = '$new_value' WHERE id = '$order_id'", __FILE__ . " linje " . __LINE__);
                
    //             // Update openpost if exists
    //             $r = db_fetch_array(db_select("SELECT fakturanr, konto_id FROM ordrer WHERE id = '$order_id'", __FILE__ . " linje " . __LINE__));
            
    //             if ($r['fakturanr']) {
    //                 db_modify("UPDATE openpost SET udlignet = '$new_value' 
    //                         WHERE faktnr = '{$r['fakturanr']}' AND konto_id = '{$r['konto_id']}'", 
    //                         __FILE__ . " linje " . __LINE__);
    //             }
                
    //             $updated_count++;
    //         }
    //     }
        
    //     $msg = ($action == 'paid') ? "markeret som betalt" : "markeret som ubetalt";
    //     echo "<script>
    //         alert('$updated_count fakturaer $msg');
    //         window.location.href = document.referrer;
    //     </script>";


    // }

    //




    ######
    $selected_ids = array();
    foreach ($checked_orders as $order_id => $value) {
        if ($value == 'on') {
            $selected_ids[] = $order_id;
        }
    }
    
    if (!empty($selected_ids)) {
        $id_list = implode(',', $selected_ids);
        
        if ($submit == findtekst('880|Udskriv', $sprog_id)) {
            print "<script>window.open('formularprint.php?id=-1&ordre_antal=".count($selected_ids)."&skriv=$id_list&formular=4&udskriv_til=PDF&returside=../includes/luk.php')</script>";
        } elseif ($submit == "Send mails") {
            print "<script>window.open('formularprint.php?id=-1&ordre_antal=".count($selected_ids)."&skriv=$id_list&formular=4&udskriv_til=email')</script>";
        } elseif ($submit == findtekst('576|Følgeseddel', $sprog_id)) {
            print "<script>window.open('formularprint.php?id=-1&ordre_antal=".count($selected_ids)."&skriv=$id_list&formular=3&udskriv_til=PDF&returside=../includes/luk.php')</script>";
        } elseif ($slet_valgte == findtekst('1099|Slet', $sprog_id)) {
            include("../includes/ordrefunc.php");
            foreach ($selected_ids as $order_id) {
                slet_ordre($order_id);
            }
        }
    } else {
        // No orders selected for other actions
        if ($submit == findtekst('880|Udskriv', $sprog_id) || $submit == "Send mails") {
            print "<BODY onLoad=\"javascript:alert('".findtekst('1418|Ingen fakturaer er markeret til udskrivning!', $sprog_id)."')\">";
        }
    }
}

print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";




// The grid will create its own form - no outer form needed
// Create the grid first (it creates its own form for pagination/search)
create_datagrid($grid_id, $data);



//data picker
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date pickers for date fields
    const dateInputs = document.querySelectorAll(
        "input[name^='search[ordrelst_'][name$='[ordredate]'], " +
        "input[name^='search[ordrelst_'][name$='[levdate]'], " +
        "input[name^='search[ordrelst_'][name$='[fakturadate]']"
    );

    dateInputs.forEach(function(input) {
        // Get existing value if any
        var existingValue = input.value.trim();
        var startDate = moment(); // Default to today
        
        // Parse existing value if it exists
        if (existingValue !== '') {
            var parsed = moment(existingValue, 'DD-MM-YYYY', true);
            if (parsed.isValid()) {
                startDate = parsed;
            }
        }
        
        // Initialize daterangepicker
        $(input).daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            autoUpdateInput: false,
            autoApply: false, // CHANGED: Show Apply/Cancel buttons
            startDate: startDate,
            minYear: 1900,
            maxYear: parseInt(moment().format('YYYY'), 10) + 10,
            locale: {
                format: 'DD-MM-YYYY',
                cancelLabel: 'Ryd',
                applyLabel: 'Søg',
                daysOfWeek: ['Sø', 'Ma', 'Ti', 'On', 'To', 'Fr', 'Lø'],
                monthNames: ['Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni', 
                             'Juli', 'August', 'September', 'Oktober', 'November', 'December'],
                firstDay: 1
            }
        });
        
        // Set initial value if exists
        if (existingValue !== '') {
            $(input).val(existingValue);
        }
        
        // When user clicks "Søg" (Apply) button
        $(input).on('apply.daterangepicker', function(ev, picker) {
            var selectedDate = picker.startDate.format('DD-MM-YYYY');
            console.log('Applied date:', selectedDate);
            $(this).val(selectedDate);
            
            // Submit the form automatically
            var form = $(this).closest('form');
            if (form.length > 0) {
                console.log('Submitting form...');
                form.submit();
            }
        });
        
        // When user clicks "Ryd" (Cancel) button
        $(input).on('cancel.daterangepicker', function(ev, picker) {
            console.log('Clearing date field');
            $(this).val('');
            
            // Submit form to clear the filter
            var form = $(this).closest('form');
            if (form.length > 0) {
                console.log('Submitting form to clear filter...');
                form.submit();
            }
        });
    });
});
</script>

<?php

$valg = is_array($valg) ? implode(',', $valg) : $valg;
$sort = is_array($sort) ? implode(',', $sort) : $sort;
// Create a SEPARATE form for bulk actions

print "<form method='post' action='ordreliste.php' id='bulkActionForm' style='margin-top: 10px;'>";
print "<input type='hidden' name='valg' value='$valg'>";
print "<input type='hidden' name='sort' value='$sort'>";
print "<input type='hidden' name='nysort' value='$nysort'>";

// Bulk action buttons
print "<div class='bulk-actions' style='padding: 10px; border-radius: 4px; text-align: right;'>";
print "<strong>Bulk handlinger:</strong> ";

// Check/Uncheck all buttons

// Determine initial state based on POST data and current checkbox states
$anyChecked = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checked'])) {
    foreach ($_POST['checked'] as $value) {
        if ($value == 'on') {
            $anyChecked = true;
            break;
        }
    }
}

// Set initial button text based on whether any checkboxes are checked
$buttonText = $anyChecked ? findtekst('90|Fravælg alle', $sprog_id) : findtekst('89|Vælg alle', $sprog_id);

########
if (preg_match('/background-color:([a-fA-F0-9#]+)/', $topStyle, $matches)) {
    $backgroundColor = $matches[1]; // Store the extracted color value
} else {
    $backgroundColor = '#114691'; // Fallback to a default color if no background-color found
}

#####
?>
<!-- Single button to toggle between check/uncheck -->
<button type="submit" 
        id="toggleButton" 
        name="toggle_checkboxes" 
        onclick="event.preventDefault(); toggleAllCheckboxes();" 
        class="button blue small">
    <?php echo $buttonText; ?>
</button>



<style>
    .button.blue.small {
        background-color: <?php echo $backgroundColor; ?> !important;
        color: white;
    }

     
</style>
<?php

if ($valg == "faktura") {
    print "<input type='submit' name='submit' value='".findtekst('880|Udskriv', $sprog_id)."' class='button blue small' onclick=\"return confirm('".findtekst('1445|Udskriv de valgte fakturaer', $sprog_id)."')\"> ";
    print "<input type='submit' name='submit' value='".findtekst('576|Følgeseddel', $sprog_id)."' class='button blue small'> ";
    print "<input type='submit' name='submit' value='Genfakturer' class='button blue small'> ";
    print "<input type='submit' name='submit' value='Send mails' class='button blue small' onclick=\"return confirm('".findtekst('1444|Er du sikker på at du vil udsende de valgte', $sprog_id)." ".findtekst('893|faktura', $sprog_id)." pr mail?')\"> ";
    //option to set whether payment has been settled or not
    // print "<input type='submit' name='submit' value='Mark as paid' class='button green small'> ";
    // print "<input type='submit' name='submit' value='Mark as unpaid' class='button red small'> ";

    //
} else {
    print "<input type='submit' name='slet_valgte' value='".findtekst('1099|Slet', $sprog_id)."' class='button red small' onclick=\"return confirm('".findtekst('1446|Er du sikker på at du vil slette de valgte', $sprog_id)." $valg?')\"> ";
    
    // Add "Ret" button for subscription orders
    if ($valg == "ordrer") {
        print "<input type='submit' name='submit' value='".findtekst('1206|Ret', $sprog_id)."' class='button blue small' title='".findtekst('1437|Klik her for at rette detaljer i abonnementsordrer', $sprog_id)."'> ";
    }
}
//add hidden datagrid id field to update data
print "<input type='hidden' name='datagrid_id' value='$grid_id'> ";
// Clear search button
print "<input type='submit' name='clear' value='".findtekst('2117|Ryd', $sprog_id)."' class='button blue small'>";

print "</div>";
print "</form>";

// JavaScript for checkbox management

?>

<script>
    
   
    // Function to toggle all checkboxes and update button text
    function toggleAllCheckboxes() {
        var checkboxes = document.querySelectorAll('input.deliveryNoteSelect');
        var button = document.getElementById('toggleButton');
        var allChecked = true;
        
        // Check if all are currently checked
        checkboxes.forEach(function(checkbox) {
            if (!checkbox.checked) {
                allChecked = false;
            }
        });
        
        // Toggle all checkboxes to the opposite state
        var newState = !allChecked;
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = newState;
        });
        
        // Update button text
        if (newState) {
            button.innerHTML = "<?php echo addslashes(findtekst('90|Fravælg alle', $sprog_id)); ?>";
        } else {
            button.innerHTML = "<?php echo addslashes(findtekst('89|Vælg alle', $sprog_id)); ?>";
        }
        
        // Also update the hidden checkboxes in the bulk form
        updateBulkFormCheckboxes();
    }
    
    // Function to sync checkboxes with bulk form
    function updateBulkFormCheckboxes() {
        var bulkForm = document.getElementById('bulkActionForm');
        var visibleCheckboxes = document.querySelectorAll('input.deliveryNoteSelect');
        var hiddenCheckboxes = bulkForm.querySelectorAll('input.deliveryNoteSelect');
        
        visibleCheckboxes.forEach(function(visibleCheckbox, index) {
            if (hiddenCheckboxes[index]) {
                hiddenCheckboxes[index].checked = visibleCheckbox.checked;
            }
        });
    }
    
    // Add event listeners to individual checkboxes to update button state
    document.addEventListener('DOMContentLoaded', function() {
        var checkboxes = document.querySelectorAll('input.deliveryNoteSelect');
        var button = document.getElementById('toggleButton');
        
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                updateBulkFormCheckboxes();
                updateToggleButtonState();
            });
        });
        
        // Initial button state update
        updateToggleButtonState();
    });
    
    // Update toggle button state based on current checkbox states
    function updateToggleButtonState() {
        var checkboxes = document.querySelectorAll('input.deliveryNoteSelect');
        var button = document.getElementById('toggleButton');
        var allChecked = true;
        var anyChecked = false;
        
        checkboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                anyChecked = true;
            } else {
                allChecked = false;
            }
        });
        
        // Update button text
        if (allChecked && anyChecked) {
            button.innerHTML = "<?php echo addslashes(findtekst('90|Fravælg alle', $sprog_id)); ?>";
        } else {
            button.innerHTML = "<?php echo addslashes(findtekst('89|Vælg alle', $sprog_id)); ?>";
        }
    }

</script>
<?php 


print "<script>

// Move checkboxes to bulk action form when clicked
document.addEventListener('DOMContentLoaded', function() {
    var bulkForm = document.getElementById('bulkActionForm');
    var checkboxes = document.querySelectorAll('input.deliveryNoteSelect');
    
    checkboxes.forEach(function(checkbox) {
        // Clone checkbox to bulk form
        var clone = checkbox.cloneNode(true);
        clone.style.display = 'none'; // Hide the duplicate
        bulkForm.appendChild(clone);
        
        // Sync changes between original and clone
        checkbox.addEventListener('change', function() {
            clone.checked = this.checked;
        });
    });
});
</script>";

########

// Calculate and display turnover summary
$dk_db = dkdecimal($ialt - $ialt_kostpris, 2);		
if ($ialt != 0) {
    $dk_dg = dkdecimal(($ialt - $ialt_kostpris) * 100 / $ialt, 2);
} else {
    $dk_dg = '0,00';
}		
$ialt_formatted = dkdecimal($ialt, 2);
$ialt_m_moms_formatted = dkdecimal($ialt_m_moms, 2);

// Display turnover summary
print "<div class='turnover-summary' style='margin-top: 10px; padding: 10px; background-color: #f0f0f0; border-radius: 4px;'>";
print "<table border='0' width='100%' style='width:100%;'><tbody>";

    if ($valg == "faktura") {
        print "<tr>";
        print "<td width='10%'></td>";
        print "<td width='70%' align='right'>";
        print "<span title='".findtekst('1438|Klik for at genberegne DB/DG', $sprog_id)."'>";
        print "<b><a href='ordreliste.php?genberegn=1&valg=$valg' accesskey='G'>".findtekst('878|Samlet omsætning / db / dg (excl. moms.)', $sprog_id)."</a></b>";
        print "</span>";
        print "</td>";
        print "<td width='20%' align='right'><b>$ialt_formatted / $dk_db / $dk_dg%</b></td>";
        print "</tr>";
        
        print "<tr>";
        print "<td width='10%'><br></td>";
        print "<td width='70%' align='right'>";
        print "<span title=''><b>".findtekst('877|Samlet omsætning inkl. moms', $sprog_id)."</b></span>";
        print "</td>";
        print "<td width='20%' align='right'><b>$ialt_m_moms_formatted</b>";
        print "</tr>";
    } else {
        
        print "<tr>";
        print "<td width='20%'>";
        if ($valg == "ordrer" && !$vis_lagerstatus) {
            print "<span title='".findtekst('1443|Hold musen over de respektive ordrenumre for at se beholdninger mm', $sprog_id)."'>";
            print "<a href=\"ordreliste.php?vis_lagerstatus=on&valg=$valg\">".findtekst('810|Vis lagerstatus', $sprog_id)."</a>";
            print "</span>";
        }
        print "</td>";
        print "<td width='70%' align='right'>".findtekst('811|Samlet omsætning incl./excl. Moms', $sprog_id)."<br>db / dg (excl. moms.)</td>";
        print "<td width='20%' align='right'><b>$ialt_m_moms_formatted ($ialt_formatted)<br>$dk_db / $dk_dg%</b></td>";
        print "</tr>";

        ##############
        print "<tr><td colspan='3'>";
        if ($valg == "ordrer") {
                $r = db_fetch_array(db_select("select box1 from grupper where art='MFAKT' and kodenr='1'", __FILE__ . " linje " . __LINE__));
                if($r) {
                    if ($r['box1'] && $ialt != "0,00") {
                        $tekst = "Faktur&eacute;r alt som kan leveres?";
                        print "<div id='massefakt_div'>";
                        print "<span title='".findtekst('1439|Klik her for at importere en csv fil', $sprog_id)."'><a href='csv2ordre.php' target=\"_blank\">CSV import</a></span>";
                        print " | ";
                        print "<span title='".findtekst('1440|Klik her for at fakturere alle ordrer på listen', $sprog_id)."'><a href='massefakt.php?valg=$valg' onClick=\"return MasseFakt('$tekst')\">Faktur&eacute;r&nbsp;alt</a></span>";
                        print "</div>";
                    } else {
                        print "<div id='massefakt_div'>";
                        if ($menu == 'T') {
                            print "&nbsp;&nbsp;<span title='".findtekst('1439|Klik her for at importere en csv fil', $sprog_id)."'><a href='csv2ordre.php' target=\"_blank\">CSV import</a></span>";
                        } else {
                            print "<span title='".findtekst('1439|Klik her for at importere en csv fil', $sprog_id)."'><a href='csv2ordre.php' target=\"_blank\">CSV import</a></span>";
                        }
                        print "</div>";
                    }
                }
            }
        
        #############
    }

    ##############
    // Shop integration features
    if ($r = db_fetch_array(db_select("select box4, box5, box6 from grupper where art='API' and box4 != ''", __FILE__ . " linje " . __LINE__))) {
        $api_fil = trim($r['box4']);
        $api_fil2 = trim($r['box5']);
        $api_fil3 = trim($r['box6']);
        
        if (file_exists("../temp/$db/shoptidspkt.txt")) {
            $fp = fopen("../temp/$db/shoptidspkt.txt", "r");
            $tidspkt = fgets($fp);
            fclose($fp);
        } else {
            $tidspkt = 0;
        }
        
        $hent_nu = if_isset($_GET, NULL, 'hent_nu');
        $shop_ordre_id = if_isset($_GET, NULL, 'shop_ordre_id');
        $shop_faktura = if_isset($_GET, NULL, 'shop_faktura');
        
        if ($tidspkt < date("U") - 1200 || $shop_ordre_id || $shop_faktura) {
            $fp = fopen("../temp/$db/shoptidspkt.txt", "w");
            fwrite($fp, date("U"));
            fclose($fp);
            
            $header = "User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
            $api_txt = "$api_fil?put_new_orders=1";
            
            if ($shop_ordre_id && is_numeric($shop_ordre_id)) {
                $api_txt .= "&order_id=$shop_ordre_id";
            } elseif ($shop_faktura) {
                $api_txt .= "&invoice=$shop_faktura";
            }
            
            exec("nohup /usr/bin/wget -O - -q --no-check-certificate --header='$header' '$api_txt' > /dev/null 2>&1 &\n");
            
            // Handle additional API files if they exist
            if ($api_fil2) {
                $api_txt2 = "$api_fil2?put_new_orders=1";
                if ($shop_ordre_id && is_numeric($shop_ordre_id)) $api_txt2 .= "&order_id=$shop_ordre_id";
                elseif ($shop_faktura) $api_txt2 .= "&invoice=$shop_faktura";
                exec("nohup /usr/bin/wget -O - -q --no-check-certificate --header='$header' '$api_txt2' > /dev/null 2>&1 &\n");
            }
            
            if ($api_fil3) {
                $api_txt3 = "$api_fil3?put_new_orders=1";
                if ($shop_ordre_id && is_numeric($shop_ordre_id)) $api_txt3 .= "&order_id=$shop_ordre_id";
                elseif ($shop_faktura) $api_txt3 .= "&invoice=$shop_faktura";
                exec("nohup /usr/bin/wget -O - -q --no-check-certificate --header='$header' '$api_txt3' > /dev/null 2>&1 &\n");
            }
        } elseif ($hent_nu) {
            print "<script>alert('vent 30 sekunder');</script>";
        }
        
        if($valg=="ordrer"){
            $sort="ordredate";
        print "<div id='shop_integration'>";
        print "<a href=\"$_SERVER[PHP_SELF]?sort=$sort&hent_nu=1\">".findtekst('879|Hent fra shop', $sprog_id)."</a>";
        print "</div>";
        print "</td></tr>";
        }else{
        print "<tr> <td colspan='3'>";
         if($valg=="faktura"){
            $sort="fakturadate";
        }
        print "<div id='shop_integration'>";
        print "<a href=\"$_SERVER[PHP_SELF]?sort=$sort&hent_nu=1\">".findtekst('879|Hent fra shop', $sprog_id)."</a>";
        print "</div>";
        print "</td></tr>";

        }

    }else{
         print "</td></tr>";
        
    }

    #############



print "</tbody></table>";
print "</div>";

    // Handle recalculate if needed
    if ($genberegn == 1) {
        print "<meta http-equiv=\"refresh\" content=\"0;URL='ordreliste.php?genberegn=2&valg=$valg'\">";
    }






// Additional API integration
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
		} else print "<tr><td colspan=\"3\"><span title='".findtekst('1441|Klik her for at hente nye ordrer fra shop', $sprog_id)."' onclick=\"JavaScript:window.open('$apifil','hent:ordrer','width=10,height=10,top=1024,left=1280')\">SHOP import</span></td></tr>";	
	}
}
######
print "</div>";

#don't show turnover summary and bulk actions if 'kolonner' menu is selected
if (isset($_GET['menu']["$grid_id"])) {
    $menu_value = $_GET['menu']["$grid_id"];
    if ($menu_value == 'kolonner') {   
       ?>
        <style>
            .turnover-summary, .bulk-actions {
                display: none;
            }

        </style>

       <?php
    }
} 

#################
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
		else $span="style='color: #FF0000;' title='".findtekst('1442|Ikke udlignet', $sprog_id)."\r\ndb: $dk_db - dg: $dk_dg%'";
		print "<span $span>$tmp<br></span>";
	}
}

################
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
					return "".findtekst('888|Tilbudsnr.', $sprog_id).".,".findtekst('888|Tilbudsnr.', $sprog_id).",".findtekst('804|Kontonr.', $sprog_id).".,".findtekst('360|Firmanavn', $sprog_id).",".findtekst('884|Sælger', $sprog_id).",".findtekst('890|Tilbudssum', $sprog_id).""; #20210318
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
					return "".findtekst('500|Ordrenr.', $sprog_id).".,".findtekst('881|Ordredato', $sprog_id).",".findtekst('886|Dato for levering', $sprog_id).",".findtekst('804|Kontonr.', $sprog_id).".,".findtekst('360|Firmanavn', $sprog_id).",".findtekst('884|Sælger', $sprog_id).",".findtekst('887|Ordresum', $sprog_id)."";
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
					return "".findtekst('500|Ordrenr.', $sprog_id).",".findtekst('881|Ordredato', $sprog_id).",".findtekst('882|Fakt. nr.', $sprog_id).",".findtekst('883|Fakt. dato', $sprog_id).",Genfakt.,".findtekst('804|Kontonr.', $sprog_id).",".findtekst('360|Firmanavn', $sprog_id).",".findtekst('884|Sælger', $sprog_id).",".findtekst('885|Fakturasum', $sprog_id)."";
				default:
				return "choose a box";
			}
		}
	}
}

################


?>

<script>
// Get the 'valg' parameter from the URL (if it exists)
let valgParam = new URLSearchParams(window.location.search).get('valg');

// If 'valg' isn't in the URL, check if PHP variable '$valg' is set (passed from PHP)
if (!valgParam) {
    <?php if (isset($valg)): ?>
        valgParam = "<?php echo addslashes($valg); ?>";  // Set from PHP
    <?php else: ?>
        valgParam = "ordrer";  // Default to 'ordrer' if neither is set
    <?php endif; ?>
}



// Add 'valg' parameter to all forms in the datagrid
document.addEventListener('DOMContentLoaded', function() {
    if (valgParam) {
        const forms = document.querySelectorAll('.datatable-wrapper form');
        
        forms.forEach(form => {
            let valgInput = form.querySelector('input[name="valg"]');
            if (!valgInput) {
                valgInput = document.createElement('input');
                valgInput.type = 'hidden';
                valgInput.name = 'valg';
                form.appendChild(valgInput);
            }
            valgInput.value = valgParam;
        });
    }
});
</script>



<script>
document.addEventListener('DOMContentLoaded', () => {
  try {
    const table = document.querySelector('.datatable');
    if (!table) {
      console.error('[ERROR] No table with class "datatable" found');
      return;
    }

    const rows = table.querySelectorAll('tr');  // Target all <tr> elements in the table

    rows.forEach((row, rowIndex) => {
      try {
        const cells = Array.from(row.querySelectorAll('td'));
        if (cells.length < 2) {
          return;  // Skip rows with less than 2 cells
        }

        let linkInCell1 = cells[0].getAttribute('onclick');
        let linkInCell2 = cells[1].getAttribute('onclick');
        let linkFound = false;

       
        if (linkInCell1) {
          linkFound = true;
          // Make the entire row clickable
          row.style.cursor = 'pointer';  
          cells.slice(0, cells.length - 2).forEach(cell => {
            cell.style.cursor = 'pointer';  
          });
        }

        
        if (linkInCell2) {
          linkFound = true;
         
          cells.slice(1).forEach(cell => {
            cell.style.cursor = 'pointer'; 
          });
        }

        if (!linkFound) {
          return;  
        }

        // Attach click event to the row to navigate
        row.addEventListener('click', (event) => {
        
          if (event.target.type === 'checkbox') {
            return;  
          }

          
          if (event.target === cells[0]) return;

          // If the link is in the first cell, navigate to it
          if (linkInCell1) {
            eval(linkInCell1); 
          } 
          
          else if (linkInCell2) {
            eval(linkInCell2);  
          }
        });
      } catch (rowErr) {
        console.error('[ERROR] Failed processing row:', rowErr);
      }
    });
  } catch (err) {
    console.error('[ERROR] DOMContentLoaded handler failed:', err);
  }
});


</script>

<style>
#massefakt_div, #shop_integration {
    display: inline-block;
    vertical-align: top;  
    margin-right: 10px;   
}

/* Force underline on all links in .turnover-summary*/
.turnover-summary a, .turnover-summary a:link, .turnover-summary a:visited, .turnover-summary a:hover, .turnover-summary a:active {
    text-decoration: underline !important;
}

</style>