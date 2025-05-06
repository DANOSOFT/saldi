<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/varer.php ---patch 4.1.1 ----2025-05-03--------------
//                           LICENSE
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

// 2013.01.15 Wildcard forsvinder efter søgning - tak til Henrik Thomsen fra Basslab for rettelse - søg 20130115
// 2014.07.28 Timeout ændret fra 30 til 60 - Søg timeout.
// 2014.12.17	Omskrevet funktionen "find_beholdning" se evt. find_beholdning_xx 
// 2015.02.10	Flerlager ændret så lagre kun vises hvis der er mere end 1 lager oprettet under lagre og 'Hovedlager' ikke vises 
// 2015.03.03 Søger nu på %text% hvis tekst ikke starter eller slutter med "*" #20150303
// 2016.01.04 Lagerstatus opdateres hvis der er differ 20161004
// 2016.12.06 PHR Optimeret søgefunktion. Der kan søges på exakt streng og på streng hvor ord indgår afskilt af +
// 2016.12.17	PHR Fjernet funktion find_beholdning (Flyttet til includes/std_func.php)
// 2017.05.02	PHR Hvis der er flere lagre og lagernavn <= 2 tegn vises lagernavn i stedet for bogstav
// 2017.09.20	PHR Hele varelinjen er nu klikbar. Søg 20170920
// 2018.01.12 PHR Tilføjet søgning på variant stregkode 20180112
// 2018.02.12 PHR Liste kan nu gemmes som csv. Sæg scv eller csvfil
// 2018.03.20 PHR Tilføjet leverandørsøgefelt. Søg $lev_kto_navn
// 2018.04.11 PHR Sætter backslash foran " ved leverandørsøgning.
// 2018.08.22 PHR db_escape string på 'insert into ordrer' v. indkøbsforslag. 218180822
// 2018.11.23 PHR $vis_kostpriser tilføjet
// 2018.11.26 PHR href på varenr tilføjet
// 2019.02.19 PHR Incl_moms changed according to'$vatOnItemCard' to fullfill demands for different Vat Rates
// 2019.02.20 MSC - Rettet isset fejl
// 2019.02.20 MSC - Rettet topmenu design
// 2019.03.06 PHR - Corrected error in 'forslag' Search $makeSuggestion & $stock
// 2019.03.12 PHR - Corrected another error in 'forslag' Search 20190312
// 2019.03.13 PHR - Deleted apostrophe around 'varer.gruppe' 20190313
// 2019.03.22 PHR - Optimized speed by moving $vatPrice calculation. 
// 2019.04.23 PHR - Will now search in both varenr(item number) beskrivelse(description) for text in varenr field 20190423  
// 2019.05.01 PHR - Added showTrademark & tradeMark. 
// 2019.06.12 PHR - Added 'if (!strstr($varenummer,'%'))' 20190612
// 2019.08.28 PHR - Number of item lines per page was not saved. #20190828
// 2019.91.02 PHR - Increased timeout. #20190901
// 2021.02.11 PHR - Search now looks in 'trademark'
// 2021.04.01 LOE - Translated these texts to English 20210401
// 2023.04.14 LOE - Minor modifications
// 2023.06.03 PHR - php8
// 2023.09.05 PHR - cookie for saldiProductListStart & saldiProductListLines 
// 20250130 	  -	migrate utf8_en-/decode() to mb_convert_encoding
// 2025.05.03 LOE - reordered mix-up text_id from tekster.csv in findtekst()

@session_start();
$s_id=session_id();

$vis_lev_felt = NULL;

if (isset($_GET['sort'])) {
	$sort=$_GET['sort'];
	setcookie("saldi_vare_sort", $sort, time()+3600*24*12);
} elseif(isset($_COOKIE['saldi_vare_sort'])) $sort=$_COOKIE['saldi_vare_sort'];
else $sort = "varenr";


if (isset($_GET['lev_kto_navn']) && $_GET['lev_kto_navn']) {
	$lev_kto_navn=$_GET['lev_kto_navn'];
#	setcookie("saldi_lkn", $lev_kto_navn, time()+3600*24*12);
} #elseif(isset($_COOKIE['saldi_lkn'])) $lev_kto_navn=$_COOKIE['saldi_lkn'];
else $lev_kto_navn = "";

$title="Varer";
$modulnr=9;
$css="../css/std.css";

$beskrivelse = $fokus = $linjeantal = $slut = $start = $udvalg = $vis_lev = $vis_lev_felt = NULL;
#$linjeantal=100;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

// Navigate to new lagersystem
if ($menu == "S") {
	header('Location: lister/vareliste.php');
	exit;
}
	
if ($popup) $returside="../includes/luk.php";
else $returside=(if_isset($_GET['returside']));
if (!$returside) $returside="../index/menu.php";
$lev_id = array();
/*
$qtxt="select * from grupper where art='VV' and box1='$brugernavn'";
if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$vis_VG=explode(",",$r['box2']);
	if ($r['box3']) $vis_K=explode(",",$r['box3']);
	else $vis_VG[0]=1;
	list($vis_lukkede,$vis_lev_felt,$vis_kostpriser,$href_vnr,$tmp,$showTrademark) = array_pad(explode(chr(9),$r['box4'],6), 6, null);
	if ($r['box5']) $linjeantal=$r['box5'];
	if (!$linjeantal) $linjeantal=100;
} else {
	$qtxt="insert into grupper (beskrivelse, art, box1, box2, box3, box4) values ('varevisning', 'VV', '$brugernavn', 'on', 'on', 'on')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
}
if ($slut <= $start) $slut=$start+$linjeantal;
*/
if ($vis_lev_felt) {
	$x=0;
	$qtxt="select distinct(lev_id) from vare_lev";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$lev_id[$x]=$r['lev_id'];
		$x++;
	}
	$lev='';
	$qtxt="select id,kontonr,firmanavn from adresser where art='K' order by firmanavn";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (in_array($r['id'],$lev_id)) {
			$lev.='"'.$r['kontonr']." : ".str_replace('"','\"',$r['firmanavn']).'",';
		}
	}
	$lev=trim($lev,',');
	print "<link rel=\"stylesheet\" href=\"//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css\">
		<script src=\"//code.jquery.com/jquery-1.10.2.js\"></script>
		<script src=\"//code.jquery.com/ui/1.11.4/jquery-ui.js\"></script>
		<link rel=\"stylesheet\" href=\"/resources/demos/style.css\">
	<script>
		$(function() {
			var availableTags = [ $lev ];
			$( \"#tags\" ).autocomplete({
				source: availableTags
			});
		});
	</script>";
}
print "<script>
function lagerflyt(vare_id, lager){
	window.open(\"lagerflyt.php?input=\"+ lager +space + vare_id,\"\",\"left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no\")
}
//-->
</script>";

if (isset($_GET)) {
	$csv=if_isset($_GET['csv']);
	$vis_lev=if_isset($_GET['vis_lev']);
	$alle_varer=if_isset($_GET['alle_varer']);
	
	if (isset($_GET['forslag']) && $_GET['forslag']) {
		$stock=1;
		$makeSuggestion=1;
	} else $makeSuggestion=0;
	if (isset($_GET['beholdning']) && $_GET['beholdning']) {
		$stock=1;
		$i_tilbud=array();
		$i_ordre=array();
		$i_forslag=array();
		$bestilt=array();
	}
	$linjeantal = $start = NULL;
	if (isset($_GET['start'])) $start = $_GET['start'];
	elseif (isset($_POST['start'])) $start = $_POST['start'];
	if ($start)	setcookie("saldiProductListStart", $start);
	elseif(isset($_COOKIE['saldiProductListStart'])) $start=$_COOKIE['saldiProductListStart'];
	else $start=1;
	if (isset($_GET['linjeantal'])) $linjeantal = $_GET['linjeantal'];
	elseif (isset($_POST['linjeantal'])) $linjeantal = $_POST['linjeantal'];
	if ($linjeantal)	setcookie("saldiProductListLines", $linjeantal);
	elseif(isset($_COOKIE['saldiProductListLines'])) $linjeantal=$_COOKIE['saldiProductListLines'];
	else $linjeantal=100;
	$slut = if_isset($_GET['slut']);
#	else $slut=$start+$linjeantal;
	$varenummer= db_escape_string(if_isset($_GET['varenummer']));
	$beskrivelse=if_isset($_GET['beskrivelse']);
}
	
if (isset($_POST)) {
	if (isset($_POST['lev_kto_navn'])) $lev_kto_navn=db_escape_string($_POST['lev_kto_navn']);
	else $lev_kto_navn=":";
	if (strstr(":",$lev_kto_navn)) { 
		list($lev_kto,$lev_navn)=explode(":",$lev_kto_navn,2);
	} elseif (is_numeric($lev_kto_navn)) {
		$lev_kto=trim($lev_kto_navn);
		$lev_navn='';
	} else {
		$lev_navn=trim($lev_kto_navn);
		$lev_kto=NULL;
	}
	$lev_kto=trim($lev_kto);
	$lev_navn=trim($lev_navn);

	if (isset($_POST['genbestil_ant'])) {
		transaktion('begin');
		for ($x=1; $x<=$_POST['genbestil_ant']; $x++) {
			$tmp1="gb_id_$x";
			$tmp1=$_POST[$tmp1];
			$tmp2="gb_antal_$x";
			$tmp2=$_POST[$tmp2];
			if ($tmp2) genbestil($tmp1,$tmp2); 
		}
		transaktion('commit');
		print "<BODY onLoad=\"javascript:alert('Der er oprettet nye indk&oslash;bsforslag')\">";
	}
	if (isset($_POST['start'])) $start = $_POST['start'];
	if (isset($_POST['linjeantal'])) $linjeantal = $_POST['linjeantal'];
#	if (isset($_POST['varenummer'])) $varenummer= db_escape_string($_POST['varenummer']);
#	if (isset($_POST['beskrivelse'])) $beskrivelse= db_escape_string($_POST['beskrivelse']);
	if (isset($_POST['varenummer'])){ # 20130115 
		$varenummer=db_escape_string($_POST['varenummer']);		
		$_SESSION['varenummer']=$varenummer;
	} elseif (isset($_SESSION['varenummer']) && $_SESSION['varenummer']) {
		$varenummer=$_SESSION['varenummer'];
	}
	if (isset($_POST['beskrivelse'])){
		$beskrivelse=$_POST['beskrivelse'];
		$_SESSION['beskrivelse']=$beskrivelse;
	} elseif (isset($_SESSION['beskrivelse']) && $_SESSION['beskrivelse']) {
		$beskrivelse=$_SESSION['beskrivelse'];
	}
	$slut=$start+$linjeantal;
}

if ($linjeantal) { #20190828
	$qtxt="update grupper set box5='$linjeantal' where art='VV' and box1='$brugernavn'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt="select * from grupper where art='VV' and box1='$brugernavn'";
if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$vis_VG=explode(",",$r['box2']);
	if ($r['box3']) $vis_K=explode(",",$r['box3']);
	else $vis_VG[0]=1;
	list($vis_lukkede,$vis_lev_felt,$vis_kostpriser,$href_vnr,$tmp,$showTrademark) = array_pad(explode(chr(9),$r['box4'],6), 6, null);
	if ($r['box5']) $linjeantal=$r['box5'];
	if (!$linjeantal) $linjeantal=100;
} else {
	$qtxt="insert into grupper (beskrivelse, art, box1, box2, box3, box4) values ('varevisning', 'VV', '$brugernavn', 'on', 'on', 'on')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
}
if ($slut <= $start) $slut=$start+$linjeantal;

$qtxt="select var_value from settings where var_name = 'vatOnItemCard' and var_grp = 'items'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
if (isset($r['var_value']) && $vatOnItemCard=$r['var_value']) {
	$itemGroup=array();
	$x=0;
	$qtxt = "select kodenr,box4 from grupper where art='VG' and box7!='on' and fiscal_year = '$regnaar' order by box4";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$itemGroup[$x]       = $r['kodenr'];
		$itemSaleAccount[$x] = $r['box4'];
		$x++;
	}
	for ($x=0;$x<count($itemGroup);$x++) {
		$qtxt = "select moms from kontoplan where kontonr='$itemSaleAccount[$x]'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$vatType[$x]=substr($r['moms'],0,1);
			($vatType[$x] == 'S')?$vatNo[$x]=substr($r['moms'],1):$vatNo[$x]=0;
		} else {
			$vatType[$x]='';
			$vatNo[$x]=0;
		}
		$qtxt="select box2 from grupper where art='SM' and kodenr = '". (int)$vatNo[$x] ."' and fiscal_year = '$regnaar'";
		($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$vatRate[$x]=$r['box2']:$vatRate[$x]='0';
	}
}
/*	
$qtxt="select * from grupper where art='VV' and box1='$brugernavn'";
if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$vis_VG=explode(",",$r['box2']);
	if ($r['box3']) $vis_K=explode(",",$r['box3']);
	else $vis_VG[0]=1;
	list($vis_lukkede,$vis_lev_felt,$vis_kostpriser,$href_vnr,$tmp,$showTrademark) = array_pad(explode(chr(9),$r['box4'],6), 6, null);
	if ($r['box5']) $linjeantal=$r['box5'];
	if (!$linjeantal) $linjeantal=100;
} else {
	$qtxt="insert into grupper (beskrivelse, art, box1, box2, box3, box4) values ('varevisning', 'VV', '$brugernavn', 'on', 'on', 'on')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
}
if ($slut <= $start) $slut=$start+$linjeantal;
*/
#if (!$sort) $sort = "varenr";

if ($beskrivelse) { #20161206
	$find=strtolower(db_escape_string($beskrivelse));
	if (strpos($find,'+')) { #20161110
		$find=str_replace("*","",$find);
		$ord=array();
		$ord=explode("+",$find);
		$find=NULL;
		for($b=0;$b<count($ord);$b++){
			$udvalg.="and lower(beskrivelse) like '%$ord[$b]%'";
		}
	} elseif (strstr($find, "*")) $udvalg.="and lower(beskrivelse) like '".str_replace("*","%",$find)."'";
	elseif (substr($find,0,1)=='"' && substr($find,-1)=='"') $udvalg.="and lower(beskrivelse) = '".str_replace('"','',$find)."'"; 
	else $udvalg.="and lower(beskrivelse) like '%".$find."%'"; #20190423
	/*
	if ($beskrivelse == str_replace("*","",$beskrivelse) && !strpos($beskrivelse," ")) $beskrivelse="%".$beskrivelse."%"; #20150303
	if (strstr($beskrivelse, "*")) {
		if (substr($beskrivelse,0,1)=='*'){
			$beskrivelse="%".substr($beskrivelse,1);
#			$b_startstjerne=1;
		}
		if (substr($beskrivelse,-1,1)=='*') {
			$beskrivelse=substr($beskrivelse,0,strlen($beskrivelse)-1)."%";
#			$b_slutstjerne=1;
		}
		$b_strlen=strlen($beskrivelse);
#		if ($db_type=="mysql") 
#		else $udvalg=$udvalg." and beskrivelse ~ '$beskrivelse'"; 
	} # else $udvalg=$udvalg." and beskrivelse='$beskrivelse'";
	$low=strtolower($beskrivelse);
	$upp=strtoupper($beskrivelse);
	$udvalg.=" and (beskrivelse LIKE '$beskrivelse' or lower(beskrivelse) LIKE '$low' or upper(beskrivelse) LIKE '$upp')";
*/
}

$next=udskriv($start, $slut, $sort, '', '');

include("../includes/topline_settings.php");

$txt30   = findtekst(30,$sprog_id);   # Tilbage
$txt39   = findtekst(39,$sprog_id);   # Ny
$txt159  = findtekst(159,$sprog_id);  # Fortryd
$txt813  = findtekst(813,$sprog_id);  # Visning
$txt953  = findtekst(953,$sprog_id);  # Ordrebeholdning
$txt955  = findtekst(955,$sprog_id);  # Alle varer fra lev.
$txt957  = findtekst(957,$sprog_id);  # Vareliste
$txt954  = findtekst(954,$sprog_id);  # Indkøbsforslag
$txt2110 = findtekst(2110,$sprog_id); # Viser status for tilbud.....
$txt2113 = findtekst(2113,$sprog_id); # Tilbage til varelisten uden at bestille
$txt3102 = findtekst(2403,$sprog_id); # Opret en ny vare
$txt3103 = findtekst(2404,$sprog_id); # Luk varelisten....
$txt3105 = findtekst(2406,$sprog_id); # Opret indkøbsforslag /....
$txt3101 = findtekst(2402,$sprog_id); # Vælg hvilke varegrupper.....

if ($menu=='T') {
#	print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\"></div>\n";
#	print "<span class=\"headerTxt\">Systemsetup</span>\n";     
#	print "<div class=\"headerbtnRght\"><!--<a href=\"index.php?page=../debitor/debitorkort.php;title=debitor\" class=\"button green small right\">Ny debitor</a>--></div>";       
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontent\">\n";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\"><tbody>"; # -> 1

} elseif ($menu=='S') {
	print "<table style='width:100%;height:100%;' border='0' cellspacing='0' cellpadding='0'><tbody>\n";
	print "<tr><td height = '25' align='center' valign='top'>\n";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>\n";
	print "<tr><td width='10%'><a href='$returside' accesskey=L>
		   <button style='$buttonStyle; width:100%' title='$backTitle' onMouseOver=\"this.style.cursor='pointer'\">$txt30</button></a></td>\n";
	if ($start<$linjeantal) {
		if ($makeSuggestion) {
			print "<td width='10%'><a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal'>";
			print "<button style='$buttonStyle; width:100%' title='$txt2113' onMouseOver=\"this.style.cursor='pointer'\">";
			print "$txt159</button></a></td>\n";
		} else {
			print "<td width='15%'>
				   <a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;forslag=ja&amp;beskrivelse=$beskrivelse&find=$find'>
				   <button style='$buttonStyle; width:100%' title='$txt3105' onMouseOver=\"this.style.cursor='pointer'\">
				   $txt954</button></a></td>\n";
		}
	}
	print "<td style='$topStyle' align='center'> $txt957</td>\n";

	if ($start<$linjeantal) {
		if ($stock && !$makeSuggestion) {
			print "<td width='10%'><a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal'>
				   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">$txt30</button></a></td>\n";
		} elseif ($stock && $makeSuggestion && !$alle_varer) {
			print "<td width='14%'>
				   <a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;forslag=ja&amp;beskrivelse=$beskrivelse&amp;alle_varer=ja'>
				   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\" title='".findtekst('2207|Medtager alle varer fra valgte leverandører, uanset ordrestatus', $sprog_id)."'>$txt955</button></a></td>\n";
		} elseif ($stock && $makeSuggestion && $alle_varer) {
			print "<td width='10%'>
				   <a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;forslag=ja&amp;beskrivelse=$beskrivelse'>
				   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\" title='".findtekst('2208|Medtager kun varer fra valgte leverandører, som vil komme under minimum ud fra ordrer & tilbud', $sprog_id)."'>".findtekst('956|Kun mangler', $sprog_id)."</button></a></td>\n";
		} else {
			print "<td width='10%'><a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;beholdning=ja'>
				   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\" title='".findtekst('2110|Viser status for tilbud, salgsordrer og indkøbsordrer', $sprog_id)."'>$txt953</button></a></td>\n";
		}
	} #else print "<td width='80%' $top_bund> Visning</td>\n";

	print "<td width='5%'><a href='varevisning.php'>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\" title='$txt3101'>$txt813</button></a></td>";
	print "<td width='5%'><a href='varekort.php?returside=varer.php'>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\" title='".findtekst('2403|Opret en ny vare', $sprog_id)."'>".findtekst('39|Ny', $sprog_id)."</button></a></td>";

	print "</tr>\n";
	print "</tbody></table>\n";

} else {
	print "<table style=\"width:100%;height:100%;\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
	print "<tr><td width=\"10%\" $top_bund><a href=\"$returside\" accesskey=L><span title='$backTitle'>$txt30</span></a></td>\n";
	if ($start<$linjeantal) {
		if ($makeSuggestion) {
			print "<td width=\"10%\" $top_bund><a href=\"varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal\"><span title='$txt2113'>$txt159</span></a></td>\n";
		} else {
			print "<td width=\"10%\" $top_bund><a href=\"varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;forslag=ja&amp;beskrivelse=$beskrivelse&find=$find\"><span title='$txt3105'>$txt954</span></a></td>\n";
		}
	}	
	print "<td width=\"50%\" $top_bund>$txt957</td>\n";
	if ($start<$linjeantal) {
		if ($stock && !$makeSuggestion) {
			print "<td width=\"10%\" $top_bund><a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal'>$txt30</a></td>\n";
		} elseif ($stock && $makeSuggestion && !$alle_varer) {
			$titletxt = 'Medtager alle varer fra valgte leverand&amp;oslash;rer, uanset ordrestatus';
			print "<td width=\"15%\" $top_bund><a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;forslag=ja&amp;beskrivelse=$beskrivelse&amp;alle_varer=ja'><span title=$titletxt>$txt955</span></a></td>\n";
		} elseif ($stock && $makeSuggestion && $alle_varer) {
			$titletxt = 'Medtager kun varer fra valgte leverand&amp;oslash;rer, som vil komme under minimum udfra ordrer & tilbud';
			print "<td width=\"10%\" $top_bund><a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;forslag=ja&amp;beskrivelse=$beskrivelse'><span title='$titletxt'>$txt</span></a></td>\n";
		} else {
			print "<td width=\"10%\" $top_bund><a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;beholdning=ja'><span title='$txt2110'>$txt953</span></a></td>\n";
		}
	} #else print "<td width=\"80%\" $top_bund> Visning</td>\n";
	if ($popup) {
		print "<td width=\"5%\" $top_bund onClick=\"javascript:vare_vis=window.open('varevisning.php','vare_vis','scrollbars=1,resizable=1');vare_vis.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"> <span title='$txt3101'><u>$txt813</u></span></td>";
		print "<td width=\"5%\" $top_bund onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:window.open('varekort.php?opener=varer.php&amp;returside=../includes/luk.php','varekort','scrollbars=1,resizable=1');ordre.focus();\"><span style=\"text-decoration: underline;\" title='Opret en ny vare'>Ny</a></span></td>";
	} else {
		print "<td width=\"5%\" $top_bund><a href=\"varevisning.php\"> <span title='$txt3101'><u>$txt813</u></span></a></td>";
		print "<td width=\"5%\" $top_bund><a href=\"varekort.php?returside=varer.php\"><span title='$txt813</span></a></td>";
	}
	print "</tr>\n";
	print "</tbody></table>\n";
}
print "<tr><td valign=\"top\">\n";
if (!$makeSuggestion) {
	print "<form name=\"vareliste\" action=\"varer.php?sort=$sort&amp;beholdning=$stock&amp;forslag=$makeSuggestion&lev_kto_navn=$lev_kto_navn\" method=\"post\">";
	print "<input type=\"hidden\" name=\"valg\">";
	print "<input type=\"hidden\" name=\"start\" value=\"$start\">";
} else 	print "<form name=\"vareliste\" action=\"varer.php?sort=$sort\" method=\"post\">";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\"><tbody>\n";
$x=0;
$query = db_select("select beskrivelse, kodenr from grupper where art='LG' order by kodenr",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	$x++;
	$lagernavn[$x]=$row['beskrivelse'];
}
$lagerantal=$x;
if (!$lagerantal) {
	$lagernavn[1]='Lager';
	$lagerantal=1;
}

if (!$makeSuggestion) {
	if ($csv) {
		if (file_exists("../temp/$db/vareliste.csv")) unlink("../temp/$db/vareliste.csv"); 
		$csvfil=fopen("../temp/$db/vareliste.csv","w");
	} else {
	print "<tr><td colspan='2' width='15%'>";
	if ($start>=$linjeantal) {
		$tmp=$start-$linjeantal;
		print "<a href='varer.php?sort=$sort&amp;start=$tmp&amp;linjeantal=$linjeantal&amp;varenummer=$varenummer&amp;beskrivelse=$beskrivelse&amp;beholdning=$stock'><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a>&nbsp;";
	}
	if ($vis_lev_felt) {
#		print "<div class=\"ui-widget\">";
		(strlen($lev_kto_navn)>125)?$width=strlen($lev_kto_navn)*7:$width=125;
		$width.='px';
		print "<input class=\"inputbox\" style=\"width:$width\" id=\"tags\" placeholder=\"Leverandør\" name=\"lev_kto_navn\" value='$lev_kto_navn'>";
#		print "</div>";
		#		print "<input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:125px\" name=\"lev\" placeholder=\"Leverandør\" title=\"Vælg leverandør\" value=\"$lev\">";
	}
	print "</td>";
	print "<td align=center colspan='2'>";
	print "<input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:40px\" name=\"start\" title= \"1 linje\" value=\"$start\"> - ";
	print "<input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:40px\" name=\"linjeantal\" title= \"Antal linjer pr side\" value=\"$linjeantal\"></td>";
	$tmp=$start+$linjeantal;
	$colspan=$lagerantal;
	print "<td colspan='$colspan'></td>";
	if ($next>=$slut) {
		print "<td align=right><a href='varer.php?sort=$sort&amp;start=$tmp&amp;linjeantal=$linjeantal&amp;beholdning=$stock'><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
	}
	else print "<td colspan=2></td>";
	print "</tr>\n";
	}
}
if ($csv) fwrite($csvfil,"\"Varenr\";\"Enhed\";\"Varemrk.\";\"Beskrivelse\";");
else {
	print "<tr>";
	print "<td><b><a href=\"varer.php?sort=varenr&amp;vis_lev=$vis_lev&amp;start=$start&amp;linjeantal=$linjeantal\">".findtekst('917|Varenr.', $sprog_id)."</a></b></td>\n"; #20210401
	print "<td><b><a href=\"varer.php?sort=enhed&amp;vis_lev=$vis_lev&amp;start=$start&amp;linjeantal=$linjeantal\">".findtekst('945|Enhed', $sprog_id)."</a></b></td>\n";
	print "<td><b><a href=\"varer.php?sort=beskrivelse&amp;vis_lev=$vis_lev&amp;start=$start&amp;linjeantal=$linjeantal\">".findtekst('914|Beskrivelse', $sprog_id)."</a></b></td>\n";
if ($showTrademark) print "<td><b><a href=\"varer.php?sort=trademark&amp;vis_lev=$vis_lev&amp;start=$start&amp;linjeantal=$linjeantal\">".findtekst('946|Varemrk.', $sprog_id)."</a></b></td>\n";
}
if (!$vis_lev){
	if ($lagerantal>1 && !$makeSuggestion) {
		for ($x=1;$x<=$lagerantal; $x++) {
			if ($csv) {
				fwrite($csvfil,"\"$lagernavn[$x]\";");
				fwrite($csvfil,"\"Lok ($lagernavn[$x])\";");
			} else {
				print "<td align='center'><b>";
				if (strlen($lagernavn[$x])<=2) print "<span title='Lager $x'>$lagernavn[$x]";
				else print "<span title='$lagernavn[$x]'>L $x";
				print "</b></td>\n";
			}
		}
		if ($csv) fwrite($csvfil,"\"Ialt\";");
		else print "<td align=right><b><a href=\"varer.php?sort=beholdning&amp;vis_lev=$vis_lev&amp;linjeantal=$linjeantal\">".findtekst(947,$sprog_id)."</a></b></td>\n";
	} else {
		if ($stock) {
			print "<td align=right><b>".findtekst('1428|I tilbud', $sprog_id)."</b></td>\n";
			print "<td align=right><b>".findtekst('1429|I ordre', $sprog_id)."</b></td>\n";
			print "<td align=right><b>".findtekst('976|Bestilt', $sprog_id)."</b></td>\n";
		}
		print "<td align=right><b><a href=\"varer.php?sort=beholdning&amp;vis_lev=$vis_lev&amp;linjeantal=$linjeantal\">".findtekst(948,$sprog_id)."</a></b></td></td></td>\n";
	}
}
if ($makeSuggestion) {
	print "<td align=right><span title='Klik her for at oprette indk&oslash;bsordrer med nedenst&aring;ende antal'>";
	print "<input class='button gray small' type=\"submit\" value=\"".findtekst('2218|Order', $sprog_id)."\" name=\"submit\"></span></td>\n";
}	else {
	if ($csv) fwrite($csvfil,"\"Kostpris\";\"Salgspris\"\n");
	else {
		($vatOnItemCard)?$tekst="<br>(incl.moms)":$tekst="";
		print "<td align=\"right\" valign=\"top\" rowspan=\"2\"><b><a href=\"varer.php?sort=salgspris&amp;vis_lev=$vis_lev&amp;linjeantal=$linjeantal\">".findtekst(949,$sprog_id)."</a></b>$tekst</td>\n";
		if ($vis_kostpriser) print "<td align=\"right\" valign=\"top\" rowspan=\"2\"><b>".findtekst('950|Kostpris', $sprog_id)."</b></td>\n";
	}
}
if ($vis_lev) {
	print "<td align=right><b>".findtekst('950|Kostpris', $sprog_id)."</b></td>\n";
	print "<td align=right><b>".findtekst('948|Beholdn.', $sprog_id)."</b></td>\n";	
	print "<td></td>\n";
	print "<td><b> ".findtekst('951|Leverandør', $sprog_id)."</b></td>\n";
	print "<td><b> ".findtekst('952|Lev. varenr', $sprog_id)."</td>\n";
}
print "</tr><tr>\n";

if (!$makeSuggestion && !$csv) {
	$tmp=stripslashes($varenummer);
	$spantitle="<span title=";
	$spantitle.="'Skriv&nbsp;en&nbsp;søgetekst.&nbsp;Der&nbsp;søges&nbsp;efter&nbsp;varer hvor&nbsp;teksten&nbsp;indgår.&#13;";
	$spantitle.="Skrives&nbsp;2&nbsp;eller&nbsp;flere&nbsp;ord&nbsp;adskilt&nbsp;af&nbsp;\"+\",&nbsp;søges&nbsp;varer hvor&nbsp;alle&nbsp;ord&nbsp;indgår.&#13;";
	$spantitle.="Skrives&nbsp;\"*\"&nbsp;før&nbsp;eller&nbsp;efter&nbsp;søgeteksten,&nbsp;søges&nbsp;varer som&nbsp;starter&nbsp;eller&nbsp;slutter&nbsp;med&nbsp;ordet.&#13;";
	$spantitle.="Skrives&nbsp;\"&nbsp;før&nbsp;og&nbsp;efter&nbsp;søgeteksten,&nbsp;søges&nbsp;varer hvor&nbsp;beskrivelsen&nbsp;matcher&nbsp;præcis.";
	$spantitle.="'>";
	
	print "<td>$spantitle<input class=\"inputbox\" type=\"text\" style=\"width:125px\" name=\"varenummer\" value=\"$tmp\"></span></td>";
	print "<td></td>";
	print "<td>$spantitle<input class=\"inputbox\" type=\"text\" style=\"width:600px\" name=\"beskrivelse\" value="; 
	if (strstr($beskrivelse,"'")) print "\"$beskrivelse\"";
	else print "'$beskrivelse'";
	print ">&nbsp;<input class='button gray small' type=\"submit\" value=\"".findtekst('913|Søg', $sprog_id)."\" name=\"submit\"></span></td>";
	if ($showTrademark) print "<td></td>";
	print "<td colspan=5 align=right></td></tr>\n";
}
#$udvalg="";
if ($varenummer) {
	if (strstr($varenummer, "*")) {
		if (substr($varenummer,0,1)=='*'){
			$varenummer="%".substr($varenummer,1);
#			$v_startstjerne=1;
		}
		if (substr($varenummer,-1,1)=='*') {
			$varenummer=substr($varenummer,0,strlen($varenummer)-1)."%";
#			$v_slutstjerne=1;
		}
	$v_strlen=strlen($varenummer);
#	$udvalg=$udvalg." and varenr LIKE '$varenummer'";
#	else $udvalg=$udvalg." and (varenr ~ '$varenummer' or stregkode ~ '$varenummer')"; 
	} else { # 20180112
		$qtxt="select vare_id from variant_varer where upper(variant_stregkode)='".strtoupper($varenummer)."'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt="select varenr from varer where id='$r[vare_id]'";
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $varenummer=$r['varenr']; 
		}
	}
	$low=strtolower($varenummer);
	$upp=strtoupper($varenummer);
	$udvalg.=" and ((varenr LIKE '$varenummer' or lower(varenr) LIKE '$low' or upper(varenr) LIKE '$upp' or stregkode = '$varenummer')";
	if (!strstr($varenummer,'%')) {
		$udvalg.=" or lower(beskrivelse) like '%".strtolower($varenummer)."%'"; #20190612
		$udvalg.=" or lower(trademark) like '%".strtolower($varenummer)."%')"; #20210211	
	}
	else $udvalg.=")";
} 
if ($csv) {
	udskriv(0, 1000000, $sort, '1', $udvalg);
		fclose($csvfil);
		print "<a href=\"../temp/$db/vareliste.csv\">csvfil</a>";
} else {
	$next = udskriv($start, $slut, $sort, '1', $udvalg);
# if ($next<$slut) lukkede_varer();
	if ($next > 25 && $linjeantal > 25) {
		if ($start>=$linjeantal){
			$tmp=$start-$linjeantal;
			print "<tr><td><a href='varer.php?sort=$sort&amp;start=$tmp&amp;linjeantal=$linjeantal&amp;varenummer=$varenummer&amp;beskrivelse=$beskrivelse&amp;beholdning=$stock'><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
		}
		else print  "<td></td>";
		print "<td colspan=3></td>";
		$tmp=$start+$linjeantal;
		if ($showTrademark) $colspan++;
		if ($next>=$slut && !$makeSuggestion) {
			print "<td colspan='$colspan' align=right><a href='varer.php?sort=$sort&amp;start=$tmp&amp;linjeantal=$linjeantal&amp;beholdning=$stock'><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
		}
		else print  "<td></td>"; 
		print "</tr>\n";
	}
	print "<tr><td colspan='3'>";
	print "<a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;varenummer=$varenummer&amp;beskrivelse=$beskrivelse&amp;beholdning=$stock&amp;csv=1'>csv</a>";
}
print "</td></tr>";
print "</tbody></table>";
print "</form>";
#print "</td></tr>";

	
function udskriv($start, $slut, $sort, $udskriv, $udvalg) {

global $alle_varer;
global $b_startstjerne,$b_slutstjerne,$b_strlen,$beholdning,$beskrivelse,$bestilt,$bgcolor,$bgcolor5,$brugernavn;
global $charset,$csv,$csvfil;
global $lagerantal,$lev_kto,$lev_navn;
global $forslag;
global $href_vnr;
global $i_forslag,$i_ordre,$i_tilbud,$itemGroup;
global $jsvars;
global $makeSuggestion;
global $popup,$regnaar;
global $showTrademark,$stock;
global $v_startstjerne,$v_slutstjerne,$v_strlen,$varenummer,$vatOnItemCard,$vatRate,$vis_kostpriser,$vis_lukkede,$vis_K,$vis_lev,$vis_VG;

$color=$gb=NULL;
if (!isset ($tmp)) $tmp = NULL;
if (!isset ($varenr)) $varenr = NULL;
if (!isset ($lukket)) $lukket = NULL;
if (!isset ($id)) $id = NULL;
if (!isset ($enhed)) $enhed = NULL;
if (!isset ($notes)) $notes = NULL;
if (!isset ($description)) $description = NULL;
if (!isset ($gruppe)) $gruppe = NULL;
if (!isset ($vatPrice)) $vatPrice = NULL;

$tidspkt=time();

$z=0;$z1=0;
$varer_i_ordre=array();
$linjebg=NULL;
/*
$qtxt="select * from grupper where art='VV' and box1='$brugernavn'";
if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$vis_VG=explode(",",$r['box2']);
	if ($r['box3']) $vis_K=explode(",",$r['box3']);
	else $vis_VG[0]=1;
	list($vis_lukkede,$vis_lev_felt,$vis_kostpriser,$href_vnr,$tmp,$showTrademark) = array_pad(explode(chr(9),$r['box4'],6), 6, null);
} else {
	$qtxt="insert into grupper (beskrivelse, art, box1, box2, box3, box4) values ('varevisning', 'VV', '$brugernavn', 'on', 'on', 'on')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
}
*/
if ($vis_lukkede!='on') {
	$udvalg=$udvalg. " and lukket != '1'"; 
}

if (!$vis_VG[0]) {
	if ($vis_VG[1]) {
		$udvalg=$udvalg. " and (gruppe = '$vis_VG[1]'";
		$x=2; 
		if (!isset ($vis_VG[$x])) $vis_VG[$x] = NULL;
		while ($vis_VG[$x]) {
			$udvalg=$udvalg. " or gruppe = '$vis_VG[$x]'";
			$x++;
		}
		$udvalg=$udvalg. ")";
	} # else $udvalg=$udvalg. " and gruppe = ''";
}

if ($lev_kto || $lev_navn) {
	$x=1;
	if ($lev_kto) $qtxt="select id from adresser where art='K' and kontonr = '$lev_kto'";
	else {
		$tmp=str_replace("*","%",$lev_navn);
		$qtxt="select id from adresser where art='K' and ";
		$qtxt.="(firmanavn like '$tmp' or lower(firmanavn) like '".strtolower($tmp)."' or ";
		$qtxt.="upper(firmanavn) like '".strtoupper($tmp)."')";
	}
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$vis_K[$x]=$r['id'];
		$x++;
	}
	if (isset($vis_K[1]) && $vis_K[1]) $vis_K[0]=NULL;
}

if (!$vis_K[0]) {	
	$lev_vare_liste=array();	
	$x=1; 
	if (isset($vis_K[1])) {
		$tmp="where lev_id = '$vis_K[1]'";
		$x=2;
		while (isset($vis_K[$x])) {
			$tmp=$tmp." or lev_id = '$vis_K[$x]'"; 
			$x++;
		}	
	}  
	$y=0;
	$qtxt="select distinct vare_id from vare_lev $tmp";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$y++;
		$lev_vare_liste[$y]=$r['vare_id'];
	}
}
	$x=0;
	$lagergrupper=array();
	$q=db_select("select * from grupper where art='VG' and box8='on' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){ 
		$x++;
		$lagergrupper[$x]=$r['kodenr'];
	}

if (($stock||$makeSuggestion)&&!$udskriv) $varer_i_ordre=find_varer_i_ordre(); 
if (!$slut) $slut=$start+50; 
if ($beskrivelse||$varenummer||$makeSuggestion) $slut=999999;
$v = 0;
$varenr = array();
$qtxt = "select * from varer ";
if ($udvalg) $qtxt.= "where id > 0 $udvalg ";
if ($sort) $qtxt.= "order by $sort";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$id[$v]=$r['id'];
	$varenr[$v]=$r['varenr'];
	$enhed[$v]=$r['enhed'];
	$description[$v]=$r['beskrivelse'];
	$tradeMark[$v]=$r['trademark'];
	$salgspris[$v]=$r['salgspris'];
	$kostpris[$v]=$r['kostpris'];
	$beholdning[$v]=$r['beholdning'];
	$min_lager[$v]=$r['min_lager'];
	$max_lager[$v]=$r['max_lager'];
	$gruppe[$v]=$r['gruppe'];
	$notes[$v]=$r['notes'];
	$lukket[$v]=$r['lukket'];
	$vatPrice[$v]=$salgspris[$v];
	$v++;
}
if(isset($varenr)){// 20230414
for ($v=0;$v<count($varenr);$v++) {
	$z++;	# $z bruges som taeller til at kontrollere hvor mange linjer der indgaar i listen.
	$vis1=1;
	$vis2=1;
	if ($udskriv && $makeSuggestion && !$alle_varer) {
		if (isset($forslag[$v])) {
			$vis1=1; $vis2=1;
		} else $vis1=0;
	}
	// Her frasorteres varer som ikke kommer fra den valgte lev.	
	if ((isset($vis_K[1]) && $vis1==1 && isset($lev_vare_liste) && in_array($id[$v],$lev_vare_liste)) || $vis_K[0]); #gor intet
	elseif (!isset($vis_K[1]) && $vis1==1 && isset($lev_vare_liste) && !in_array($id[$v],$lev_vare_liste)); #gor intet
	elseif(!$makeSuggestion) {$vis1=0; $z--;}
	if ((isset($vis_K[1]) && $vis2==1 && isset($lev_vare_liste) && in_array($id[$v],$lev_vare_liste)) || $vis_K[0]); #gor intet
	elseif (!isset($vis_K[1]) && $vis2==1 && isset($lev_vare_liste) && !in_array($id[$v],$lev_vare_liste)); #gor intet
	else $vis2=0;
	// Her frasorteres varer i bestillingsforslag som ikke lagerfoerte - skal staa nederst i frasortering.	
	if ($makeSuggestion && !in_array($gruppe[$v],$lagergrupper)) {$vis1=0;$vis2=0;}	
// frasortering slut	
	if ((($z>=$start&&$z<$slut)||$makeSuggestion)&&$vis1==1&&$vis2==1){
	$z1++;
	if ($udskriv) {
		$y=0;
			($linjebg!=$bgcolor)?$linjebg=$bgcolor:$linjebg=$bgcolor5;
			($lukket[$v]=='1')?$color='red':$color='black';
			print "<tr bgcolor=\"$linjebg\">";
			if ($popup) { #20170920
				$kort="kort".$id[$v];
				$js="onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kort=window.open('varekort.php?opener=varer.php&amp;id=$id[$v]&amp;returside=../includes/luk.php','".$jsvars."');$kort.focus();\"";
			} elseif ($href_vnr) $js=NULL; 
			else $js="onMouseOver=\"this.style.cursor = 'pointer'\"; onclick=\"javascript:location.href='varekort.php?id=$id[$v]'\"";
#			if ($popup) print "<td </td>";
#			else print "<td><a href=\"varekort.php?id=$id[$v]&amp;returside=varer.php\"><FONT style=\"COLOR:$color;\">".htmlentities(stripslashes($varenr),ENT_COMPAT,$charset)."</font></a></td>";	
			if ($csv) {
				fwrite($csvfil,"\"".mb_convert_encoding($varenr[$v], 'ISO-8859-1', 'UTF-8')."\";\"".mb_convert_encoding($enhed[$v], 'ISO-8859-1', 'UTF-8')."\";\"".mb_convert_encoding($description[$v], 'ISO-8859-1', 'UTF-8')."\";");
			} else {
				print "<td $js><FONT style=\"COLOR:$color;\">";
				if ($href_vnr) print "<a href = 'varekort.php?id=$id[$v]'>";
				print htmlentities(stripslashes($varenr[$v]),ENT_COMPAT,$charset);
				if ($href_vnr) print "</a>";
				print "</font></td>";
				print "<td $js><FONT style=\"color:$color\">".htmlentities(stripslashes($enhed[$v]),ENT_COMPAT,$charset)."</font><br></td>";
				print "<td $js title='".$notes[$v]."'><FONT style=\"color:$color\">";
				print htmlentities(stripslashes($description[$v]),ENT_COMPAT,$charset);
				print "</font><br></td>";
				if ($showTrademark) print "<td>".htmlentities(stripslashes($tradeMark[$v]),ENT_COMPAT,$charset)."</td>";
			}
			if (!$vis_lev){
				if ($lagerantal>1 && !$makeSuggestion) {
					$r2=db_fetch_array(db_select("select sum(beholdning) as lagersum from lagerstatus where vare_id = $id[$v]",__FILE__ . " linje " . __LINE__));
					$diff=$beholdning[$v]-$r2['lagersum'];
					for ($x=1;$x<=$lagerantal; $x++) {
						$qtxt = "select id, lager,lok1,beholdning from lagerstatus where vare_id = '$id[$v]' and lager = '$x'";
						if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							$y = (float)$r2['beholdning'];
							$lok=trim(mb_convert_encoding($r2['lok1'], 'ISO-8859-1', 'UTF-8'));
						} else {
							$y=0;
							$lok='';
						}
						if ($csv) fwrite($csvfil,"\"".dkdecimal($y,2)."\";\"$lok\";");
						else {
							print "<td align=center>";
							if (in_array($gruppe[$v],$lagergrupper)) {
								if ($y >= 1) print "<span title= 'Flyt til andet lager'><a href='lagerflyt.php?lager=$x&vare_id=$id[$v]'>".dkdecimal($y,2)."</a>";
								else print dkdecimal($y,2);
							}
							print "</td>";
						}
					}
				} elseif ($csv) { 
					for ($x=1;$x<=$lagerantal; $x++) fwrite($csvfil,"\"0\";");
				}
				if (in_array($gruppe[$v],$lagergrupper)) {
				if ($makeSuggestion || $stock)	{
					$tmp=find_beholdning($id[$v],$udskriv);
					(isset($tmp[1]))?$i_tilbud[$z]   = $tmp[1]:$i_tilbud[$z]   = 0;
					(isset($tmp[5]))?$it_ordrenr[$z] = $tmp[5]:$it_ordrenr[$z] = 0;
					(isset($tmp[2]))?$i_ordre[$z]    = $tmp[2]:$i_ordre[$z]    = 0;
					(isset($tmp[6]))?$io_ordrenr[$z] = $tmp[6]:$io_ordrenr[$z] = 0;
					(isset($tmp[3]))?$i_forslag[$z]  = $tmp[3]:$i_forslag[$z]  = 0;
					(isset($tmp[7]))?$if_ordrenr[$z] = $tmp[7]:$if_ordrenr[$z] = 0;
					(isset($tmp[4]))?$bestilt[$z]    = $tmp[4]:$bestilt[$z]    = 0;
					(isset($tmp[8]))?$b_ordrenr[$z]  = $tmp[8]:$b_ordrenr[$z]  = 0;
				}

				if ($csv) fwrite($csvfil,"\"".dkdecimal($beholdning[$v],2)."\";");
				else {
					if ($stock) {
						($it_ordrenr[$z])?$title="title=\"Tilbud: $it_ordrenr[$z]\"":$title="title=\"\"";
						print "<td align=\"right\" $title>$i_tilbud[$z]</td>";
						($io_ordrenr[$z])?$title="title=\"Ordre: $io_ordrenr[$z]\"":$title="title=\"\"";
						print "<td align=\"right\" $title>$i_ordre[$z]</td>";
						($b_ordrenr[$z])?$title="title=\"Ordre: $b_ordrenr[$z]\"":$title="title=\"\"";
						print "<td align=\"right\" $title>$bestilt[$z]</td>";
					}
					print "<td align=right>".dkdecimal($beholdning[$v],2)."</td>";
				}
				if ($makeSuggestion){
					$tmp=$beholdning[$v]-$i_ordre[$z];
					if ($min_lager[$v]*1>$tmp || $alle_varer) {
						$gb=$gb+1;
						$genbestil[$z]=$max_lager[$v]-$beholdning[$v]+$i_ordre[$z];
						if ($genbestil[$z] < 0) $genbestil[$z]=0;	
						print "<td align=right><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:60px\" name=\"gb_antal_$gb\" value=\"$genbestil[$z]\"></td>";
						print "<input type=\"hidden\" name=\"gb_id_$gb\" value=\"$id[$v]\">";
						print "<input type=\"hidden\" name=\"genbestil_ant\" value=\"$gb\">";
					} else print "<td></td>";
				}
			} else print "<td></td>";
			} else print "<td></td>";
			if ($vatOnItemCard) {
				for($x=0;$x<count($itemGroup);$x++){
						if ($gruppe[$v]==$itemGroup[$x]) $vatPrice[$v]=$salgspris[$v]+=$salgspris[$v]/100*$vatRate[$x];
#if ($varenr[$v] == '2219') echo "$salgspris[$v]<br>";
				}
			}
			if (!$makeSuggestion) {
#				$salgspris[$v]=dkdecimal($salgspris[$v]*(100+$incl_moms)/100,2);
				if ($csv) fwrite($csvfil,"\"".dkdecimal($kostpris[$v],2)."\";\"".dkdecimal($vatPrice[$v],2)."\"\n");
				else {
					print "<td align=right>".dkdecimal($vatPrice[$v],2)."<br></td>";
					if ($vis_kostpriser) print "<td align=right>".dkdecimal($kostpris[$v],2)."<br></td>";
				}
			}
			if ($vis_lev=='on') {
				$qtxt="select kostpris, lev_id, lev_varenr from vare_lev where vare_id = $id[$v] order by posnr";
				$query2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$row2 = db_fetch_array($query2);
				if ($row2['lev_id']) {
					$lev_varenr=$row2['lev_varenr'];
					$levquery = db_select("select kontonr, firmanavn from adresser where id=$row2[lev_id]",__FILE__ . " linje " . __LINE__);
					$levrow = db_fetch_array($levquery);
					$kostpris=dkdecimal($row2['kostpris'],2);
				}
				elseif ($row['samlevare']=='on') {$kostpris=dkdecimal($row['kostpris'],2);}
				if (!$csv) print "<td align=right>$kostpris</td>";
				$qtxt = "select box8 from grupper where art='VG' and kodenr='$row[gruppe]' and fiscal_year = '$regnaar'";
				$query2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$row2 =db_fetch_array($query2);
				if (($row2['box8']=='on')||($row['samlevare']=='on')){
					$ordre_id=array();
					$x=0;
					$query2 = db_select("select id from ordrer where status >= 1 and status < 3 and art = 'DO'",__FILE__ . " linje " . __LINE__);
					while ($row2 =db_fetch_array($query2)){
						$x++;
						$ordre_id[$x]=$row2['id'];
					}
					$x=0;
					$query2 = db_select("select id, ordre_id, antal from ordrelinjer where vare_id = $id[$v]",__FILE__ . " linje " . __LINE__);
					while ($row2 =db_fetch_array($query2)) {
						if (in_array($row2['ordre_id'],$ordre_id)) {
							$x=$x+$row2['antal'];	 
							$query3 = db_select("select antal from batch_salg where linje_id = $row2[id]",__FILE__ . " linje " . __LINE__);
							while ($row3=db_fetch_array($query3)) {$x=$x-$row3['antal'];}
						}
					}	
					$linjetext="<span title= 'Der er $x i ordre'>";
					if (!$csv) {
						print "<td align=right>$linjetext $beholdning[$v]</span></td>";		
						print "<td></td>";		
						print "<td>$levrow[kontonr] - ".htmlentities(stripslashes($levrow['firmanavn']),ENT_COMPAT,$charset)."</td>";
						print "<td>".htmlentities(stripslashes($lev_varenr),ENT_COMPAT,$charset)."</td>";
					}
				}
				elseif (!$csv) print "<td></td>";	 
			}
			if (!$csv) print "</tr>\n";
		} elseif ($makeSuggestion||$stock) {
			if (in_array($id[$v],$varer_i_ordre)) {
				$tmp=find_beholdning($id[$v],$udskriv);
					(isset($tmp[1]))?$i_tilbud[$z]   = $tmp[1]:$i_tilbud[$z]   = 0;
					(isset($tmp[5]))?$it_ordrenr[$z] = $tmp[5]:$it_ordrenr[$z] = 0;
					(isset($tmp[2]))?$i_ordre[$z]    = $tmp[2]:$i_ordre[$z]    = 0;
					(isset($tmp[6]))?$io_ordrenr[$z] = $tmp[6]:$io_ordrenr[$z] = 0;
					(isset($tmp[3]))?$i_forslag[$z]  = $tmp[3]:$i_forslag[$z]  = 0;
					(isset($tmp[7]))?$if_ordrenr[$z] = $tmp[7]:$if_ordrenr[$z] = 0;
					(isset($tmp[4]))?$bestilt[$z]    = $tmp[4]:$bestilt[$z]    = 0;
					(isset($tmp[8]))?$b_ordrenr[$z]  = $tmp[8]:$b_ordrenr[$z]  = 0;
			} else {
				$i_tilbud[$z]=0;
				$i_ordre[$z]=0;
				$i_forslag[$z]=0;
				$bestilt[$z]=0;
			}
			if (!$min_lager[$v])  $min_lager[$v]  = 0;
			if (!$beholdning[$v]) $beholdning[$v] = 0;
			if ($min_lager[$v]*1>($beholdning[$v]-$i_ordre[$z]+$i_forslag[$z]+$bestilt[$z])) {
			
				$genbestil[$z]=$max_lager[$v]-$beholdning[$v]+$i_ordre[$z]-($i_forslag[$z]+$bestilt[$z]);
				if ($makeSuggestion) {
					$forslag[$v]=$id[$v];
				}
			}
		}
	} elseif ($udskriv && $z>=$slut && !$makeSuggestion) break;
	if ($z>=$slut) {
		break;
	}
	if (time()-$tidspkt>120) { #20190901
		print "<BODY onLoad=\"javascript:alert('Timeout - reducer linjeantal')\">";
		break;
	}
}
return($z);
}
}# endfunc udskriv
##############################################

function genbestil($vare_id, $antal) {
	global $brugernavn,$db,$regnaar;
	
	$r = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
	if ($r['ansat_id']) {
		$r = db_fetch_array(db_select("select navn from ansatte where id = $r[ansat_id]",__FILE__ . " linje " . __LINE__));
		($r['navn'])?$ref=$r['navn']:$ref=NULL;
	}
	$qtxt="select * from vare_lev where vare_id = '$vare_id' order by posnr";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$lev_id=$r['lev_id'];
		$lev_varenr=$r['lev_varenr'];
		$pris=$r['kostpris']*1;
		$ordredate=date("Y-m-d");
		$qtxt="select id, sum from ordrer where konto_id = $lev_id and art = 'KO' and status < 1 and ordredate = '$ordredate'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$sum=$r['sum']*1;
			$ordre_id=$r['id'];
		} else {
			$qtxt="select ordrenr from ordrer where art='KO' or art='KK' order by ordrenr desc";
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $ordrenr=$r['ordrenr']+1;
			else $ordrenr=1;
			$qtxt="select * from adresser where id = $lev_id";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['gruppe']) {
				$qtxt = "select box1 from grupper ";
				$qtxt.= "where kode = 'K' and art = 'KG' and kodenr = '$r[gruppe]' and fiscal_year = '$regnaar'";
				$r1 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$kode=substr($r1['box1'],0,1); $kodenr=substr($r1['box1'],1);
			}	else {
				$qtxt="select varenr from varer where id = '$vare_id'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				print "<BODY onLoad=\"javascript:alert('Leverand&oslash;rgruppe ikke korrekt opsat for varenr $r[varenr]')\">";
			}
			$qtxt = "select box2 from grupper where art = 'KM' and kode = '$kode' and kodenr = '$kodenr' and fiscal_year = '$regnaar'";
			$r1 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$momssats=$r1['box2']*1;
			$qtxt="insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,"; #218180822
			$qtxt.="betalingsdage, betalingsbet, cvrnr, notes, art, ordredate, momssats, status, ref)";
			$qtxt.=" values ";
			$qtxt.="('$ordrenr','$r[id]','$r[kontonr]','".db_escape_string($r['firmanavn'])."','".db_escape_string($r['addr1'])."',";
			$qtxt.= "'".db_escape_string($r['addr2'])."','".db_escape_string($r['postnr'])."','".db_escape_string($r['bynavn'])."',";
			$qtxt.= "'".db_escape_string($r['land]'])."','$r[betalingsdage]','$r[betalingsbet]','$r[cvrnr]','".db_escape_string($r['notes'])."',";
			$qtxt.= "'KO','$ordredate','$momssats','0','".db_escape_string($ref)."')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="select id from ordrer where ordrenr='$ordrenr' and art = 'KO'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$ordre_id=$r['id'];
		}
		$qtxt="select varer.varenr as varenr,varer.beskrivelse as beskrivelse,varer.enhed as enhed,";
		$qtxt.="vare_lev.lev_varenr as lev_varenr,grupper.box7 as momsfri ";
		$qtxt.="from varer,vare_lev,grupper where ";
		$qtxt.="varer.id='$vare_id' and vare_lev.vare_id='$vare_id' and grupper.art='VG' and grupper.kodenr=varer.gruppe"; #20190313
	
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$varenr=db_escape_string($r['varenr']);
		$lev_varenr=db_escape_string($r['lev_varenr']);
		$enhed=db_escape_string($r['enhed']);
		$beskrivelse=db_escape_string($r['beskrivelse']);
		$momsfri=$r['momsfri'];
		
		$qtxt="insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, pris, lev_varenr, antal, momsfri)";
		$qtxt.=" values ";
		$qtxt.="('$ordre_id', '1000', '$varenr', '$vare_id', '$beskrivelse', '$enhed', '$pris', '$lev_varenr', '$antal', '$momsfri')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$sum=$sum+$pris*$antal;	
		db_modify("update ordrer set sum = '$sum' where id = $ordre_id",__FILE__ . " linje " . __LINE__);	
	} else { 
		$r = db_fetch_array(db_select("select varenr from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
		print "".findtekst(951,$sprog_id)." findes ikke (Varenr: $r[varenr])<br>";
	}
}

#####################################################
function find_varer_i_ordre() { #tilfoejet 2008.01.28 for hastighedsoptimering af genbestilling
	$ordreliste=NULL;
	$varer_i_ordre=array();
	$qtxt="select id from ordrer where status < 3 and art = 'DO'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!$ordreliste) $ordreliste="where ordre_id='".$r['id']."'";
		else $ordreliste=$ordreliste." or ordre_id='".$r['id']."'";
	} 
	$x=0;
	if ($ordreliste) {
		$qtxt="select distinct(vare_id) from ordrelinjer $ordreliste";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if (!in_array($r['vare_id'],$varer_i_ordre)) {
				$varer_i_ordre[$x]=$r['vare_id'];
				$x++;
			}
		}
	}
	#$x must not be set to 0 as array must grow. 20190312
	$ordreliste=NULL;
	$qtxt="select id from ordrer where (status = 1 or status = 2) and art = 'KO'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!$ordreliste) $ordreliste="where ordre_id='".$r['id']."'";
		else $ordreliste=$ordreliste." or ordre_id='".$r['id']."'";
	} 
	if ($ordreliste) {
		$qtxt="select distinct(vare_id) from ordrelinjer $ordreliste";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if (!in_array($r['vare_id'],$varer_i_ordre)) {
				$varer_i_ordre[$x]=$r['vare_id'];
				$x++;
			}
		}
	}
	sort ($varer_i_ordre);
	return $varer_i_ordre;
}
if ($fokus) { # This does not work - must make it focus on last opened product.
	print "<script language=\"javascript\">
	document.vareliste.$fokus.focus();
	</script>";
}
print "</tbody></table>";

?>
</td></tr>
</tbody></table>
</center></body></html>
