<?php
@session_start();
$s_id=session_id();
// ---------lager/varer.php-------------lap 3.5.1-----2015-02-10--------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------

// 2013.01.15 Wildcard forsvinder efter søgning - tak til Henrik Thomsen fra Basslab for rettelse - søg 20130115
// 2014.07.28 Timeout ændret fra 30 til 60 - Søg timeout.
// 2014.12.17	Omskrevet funktionen "find_beholdning" se evt. find_beholdning_xx 
// 2015.02.10	Flerlager ændret så lagre kun vises hvis der er mere end 1 lager oprettet under lagre og 'Hovedlager' ikke vises 
// 2015.03.03 Søger nu på %text% hvis tekst ikke starter eller slutter med "*" #20150303

$title="Varer";
$modulnr=9;
$css="../css/standard.css";

$org_beskrivelse=NULL;$udvalg=NULL;$vis_lev=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
	
if ($popup) $returside="../includes/luk.php";
else $returside=(if_isset($_GET['returside']));
if (!$returside) $returside="../index/menu.php";

?>
<script language="javascript" type="text/javascript">
<!--
var vare_id=0;
var lager=0;
var space=":";

function lagerflyt(vare_id, lager){
	window.open("lagerflyt.php?input="+ lager +space + vare_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
}
//-->
</script>
<?php


	
if (isset($_GET)) {
	$vis_lev=if_isset($_GET['vis_lev']);
	$alle_varer=if_isset($_GET['alle_varer']);
	
	if (isset($_GET['forslag']) && $_GET['forslag']) {
		$beholdning=1;
		$forslag[0]=1;
	}
	if (isset($_GET['beholdning']) && $_GET['beholdning']) {
		$beholdning=1;
		$i_tilbud=array();
		$i_ordre=array();
		$i_forslag=array();
		$bestilt=array();
	}
	$sort = if_isset($_GET['sort']);
	if (isset($_GET['start'])) $start = $_GET['start'];
	else $start=1;
	if (isset($_GET['linjeantal'])) $linjeantal = $_GET['linjeantal'];
#	else $linjeantal=500;
	$slut = if_isset($_GET['slut']);
#	else $slut=$start+$linjeantal;
	$varenummer= db_escape_string(if_isset($_GET['varenummer']));
	$beskrivelse= db_escape_string(if_isset($_GET['beskrivelse']));
}
	
if (isset($_POST)) {
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
		$varenummer= addslashes($_POST['varenummer']);
		$_SESSION['varenummer']=$varenummer;
	}elseif ($_SESSION['varenummer']) {
		$varenummer=$_SESSION['varenummer'];
	}
	if (isset($_POST['beskrivelse'])){
		$beskrivelse= addslashes($_POST['beskrivelse']);
		$_SESSION['beskrivelse']=$beskrivelse;
	}elseif ($_SESSION['beskrivelse']) {
		$beskrivelse=$_SESSION['beskrivelse'];
	}
#	$slut=$start+$linjeantal;
}

	if($r=db_fetch_array(db_select("select box1 from grupper where art='DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__))) {
		$incl_moms=$r['box1'];
		$r=db_fetch_array(db_select("select box2 from grupper where art='SM' and kodenr = '$incl_moms'",__FILE__ . " linje " . __LINE__));
		$incl_moms=$r['box2']*1;
	} else $incl_moms=0;

if (!isset($linjeantal)) {
	$r = db_fetch_array(db_select("select box5 from grupper where art='VV' and box1='$brugernavn'",__FILE__ . " linje " . __LINE__));
	if ($r['box5']) $linjeantal=$r['box5'];
	else $linjeantal=100;
} else db_modify("update grupper set box5='$linjeantal' where art='VV' and box1='$brugernavn'",__FILE__ . " linje " . __LINE__);
	if (!$slut) 0;
if ($slut <= $start) $slut=$start+$linjeantal;
	

if (!isset($linjeantal)) $linjeantal=100;
if ($slut <= $start) $slut=$start+$linjeantal;
	
if (!$sort) $sort = "varenr";

if ($beskrivelse) {
	$org_beskrivelse=stripslashes($beskrivelse);
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
}
 
$next=udskriv($start, $slut, $sort, '', '');

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
} else {
	print "<table style=\"width:100%;height:100%;\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
	print "<tr><td width=\"10%\" $top_bund><a href=\"$returside\" accesskey=L><span title='Luk varelisten og g&aring; tilbage til hovedmenuen'>Luk</span></a></td>\n";
	if ($start<$linjeantal) {
		if ($forslag) print "<td width=\"10%\" $top_bund><a href=\"varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal\"><span title='Tilbage til varelisten uden at bestille'>Fortryd</span></a></td>\n";
		else print "<td width=\"10%\" $top_bund><a href=\"varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;forslag=ja&amp;beskrivelse=$org_beskrivelse\"><span title='Opret indk&oslash;bsforslag udfra igangv&aelig;rende tilbud og ordrebeholdning'>Indk&oslash;bsforslag</span></a></td>\n";
	}	
	print "<td width=\"60%\" $top_bund> Vareliste</td>\n";
	if ($start<$linjeantal) {
		if ($beholdning && !$forslag) print "<td width=\"10%\" $top_bund><a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal'>Tilbage</a></td>\n";
		elseif ($beholdning && $forslag && !$alle_varer) print "<td width=\"10%\" $top_bund><a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;forslag=ja&amp;beskrivelse=$org_beskrivelse&amp;alle_varer=ja'><span title='Medtager alle varer fra valgte leverand&amp;oslash;rer, uanset ordrestatus'>Alle varer fra lev.</span></a></td>\n"; 
		elseif ($beholdning && $forslag && $alle_varer) print "<td width=\"10%\" $top_bund><a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;forslag=ja&amp;beskrivelse=$org_beskrivelse'><span title='Medtager kun varer fra valgte leverand&amp;oslash;rer, som vil komme under minimum udfra ordrer & tilbud'>Kun mangler</span></a></td>\n"; 
		else print "<td width=\"10%\" $top_bund><a href='varer.php?sort=$sort&amp;start=$start&amp;linjeantal=$linjeantal&amp;beholdning=ja'><span title='Viser status for tilbud, salgsordrer og indk&oslash;bsordrer'>Ordrebeholdning</span></a></td>\n";
	} #else print "<td width=\"80%\" $top_bund> Visning</td>\n";
	if ($popup) {
		print "<td width=\"5%\"$top_bund onClick=\"javascript:vare_vis=window.open('varevisning.php','vare_vis','scrollbars=1,resizable=1');vare_vis.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"> <span title='V&aelig;lg hvilke varegrupper og kreditorer som som vises i varelisten'><u>Visning</u></span></td>";
		print "<td width=\"5%\" $top_bund onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:window.open('varekort.php?opener=varer.php&amp;returside=../includes/luk.php','varekort','scrollbars=1,resizable=1');ordre.focus();\"><span style=\"text-decoration: underline;\" title='Opret en ny vare'>Ny</a></span></td>";
	} else {
		print "<td width=\"5%\" $top_bund><a href=\"varevisning.php\"> <span title='V&aelig;lg hvilke varegrupper og kreditorer som som vises i varelisten'><u>Visning</u></span></a></td>";
		print "<td width=\"5%\" $top_bund><a href=\"varekort.php?returside=varer.php\"><span title='Opret en ny vare'>Ny</span></a></td>";
	}
	print "</tr>\n";
	print "</tbody></table>\n";
}
print "<tr><td valign=\"top\">\n";
if (!$forslag) {
	print "<form name=\"vareliste\" action=\"varer.php?sort=$sort&amp;beholdning=$beholdning&amp;forslag=$forslag\" method=\"post\">";
	print "<input type=\"hidden\" name=\"valg\">";
	print "<input type=\"hidden\" name=\"start\" value=\"$start\">";
} else 	print "<form name=\"vareliste\" action=\"varer.php?sort=$sort\" method=\"post\">";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\"><tbody>\n";

if (!$forslag) {
	print "<tr>";
	if ($start>=$linjeantal) {
		$tmp=$start-$linjeantal;
		print "<tr><td><a href='varer.php?sort=$sort&amp;start=$tmp&amp;linjeantal=$linjeantal&amp;varenummer=$varenummer&amp;beskrivelse=$org_beskrivelse&amp;beholdning=$beholdning'><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
	}
	else print  "<td></td>";
	print "<td></td>";
	print "<td align=center>";
	print "<input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:40px\" name=\"start\" title= \"1 linje\" value=\"$start\"> - ";
	print "<input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:40px\" name=\"linjeantal\" title= \"Antal linjer pr side\" value=\"$linjeantal\"></td>";
	$tmp=$start+$linjeantal;
	print "<td colspan=4></td>";
	if ($next>=$slut) {
		print "<td align=right><a href='varer.php?sort=$sort&amp;start=$tmp&amp;linjeantal=$linjeantal&amp;beholdning=$beholdning'><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
	}
	else print  "<td colspan=2></td>";
	print "</tr>\n";
}
print "<tr>";
print "<td><b><a href=\"varer.php?sort=varenr&amp;vis_lev=$vis_lev&amp;start=$start&amp;linjeantal=$linjeantal\">Varenr.</a></b></td>\n";
print "<td><b><a href=\"varer.php?sort=enhed&amp;vis_lev=$vis_lev&amp;start=$start&amp;linjeantal=$linjeantal\">Enhed</a></b></td>\n";
print "<td><b><a href=\"varer.php?sort=beskrivelse&amp;vis_lev=$vis_lev&amp;start=$start&amp;linjeantal=$linjeantal\">Beskrivelse</a></b></td>\n";
if (!$vis_lev){
	$x=0;
	$query = db_select("select beskrivelse, kodenr from grupper where art='LG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$lagernavn[$x]=$row['beskrivelse'];
	}
	$lagerantal=$x;
	if ($lagerantal>1 && !$forslag) {
		for ($x=1;$x<=$lagerantal; $x++) {
			print "<td align=right><b><span title='$lagernavn[$x]'>L $x</b></td>\n";
		}
	print "<td align=right><b><a href=\"varer.php?sort=beholdning&amp;vis_lev=$vis_lev&amp;linjeantal=$linjeantal\">Ialt</a></b></td>\n";
	}
	else {
		if ($beholdning) {	
			print "<td align=right><b> I tilbud</b></td>\n";
			print "<td align=right><b> I ordre</b></td>\n";
			print "<td align=right><b> Bestilt</b></td>\n";
		}
		print "<td align=right><b><a href=\"varer.php?sort=beholdning&amp;vis_lev=$vis_lev&amp;linjeantal=$linjeantal\">Beholdn.</a></b></td>\n";
	}
}
if ($forslag) {
	print "<td align=right><span title='Klik her for at oprette indk&oslash;bsordrer med nedenst&aring;ende antal'>";
	print "<input type=\"submit\" value=\"Bestil\" name=\"submit\"></span></td>\n";
}	
else {
	($incl_moms)?$tekst="<br>(incl.moms)":$tekst="";
	print "<td align=\"right\" valign=\"top\" rowspan=\"2\"><b><a href=\"varer.php?sort=salgspris&amp;vis_lev=$vis_lev&amp;linjeantal=$linjeantal\">Salgspris</a></b>$tekst</td>\n";
}
if ($vis_lev) {
	print "<td align=right><b> Kostpris</b></td>\n";
	print "<td align=right><b> Beholdn.</b></td>\n";	
	print "<td>&nbsp;</td>\n";
	print "<td><b> Leverand&oslash;r</b></td>\n";
	print "<td><b> Lev. varenr</td>\n";
}
print "</tr><tr>\n";
if (!$forslag) {
	$tmp=stripslashes($varenummer);
	$spantitle="<span title= 'Angiv en s&oslash;getekst. Der kan anvendes * f&oslash;r og efter teksten'>";
	print "<td>$spantitle<input class=\"inputbox\" type=\"text\" style=\"width:125px\" name=\"varenummer\" value=\"$tmp\"></span></td>";
	print "<td></td>";
	print "<td>$spantitle<input class=\"inputbox\" type=\"text\" style=\"width:600px\" name=\"beskrivelse\" value=\"$org_beskrivelse\">&nbsp;<input type=\"submit\" value=\"S&oslash;g\" name=\"submit\"></span></td>";
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
	} 
#	else 
	$low=strtolower($varenummer);
	$upp=strtoupper($varenummer);
	$udvalg.=" and (varenr LIKE '$varenummer' or lower(varenr) LIKE '$low' or upper(varenr) LIKE '$upp' or stregkode = '$varenummer')";
}
$next = udskriv($start, $slut, $sort, '1', $udvalg);
# if ($next<$slut) lukkede_varer();
if ($next > 25 && $linjeantal > 25) {
	if ($start>=$linjeantal){
		$tmp=$start-$linjeantal;
		print "<tr><td><a href='varer.php?sort=$sort&amp;start=$tmp&amp;linjeantal=$linjeantal&amp;varenummer=$varenummer&amp;beskrivelse=$beskrivelse&amp;beholdning=$beholdning'><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
	}
	else print  "<td></td>";
	print "<td colspan=3></td>";
	$tmp=$start+$linjeantal;
	if ($next>=$slut && !$forslag) {
		print "<td colspan=4 align=right><a href='varer.php?sort=$sort&amp;start=$tmp&amp;linjeantal=$linjeantal&amp;beholdning=$beholdning'><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
	}
	else print  "<td></td>"; 
	print "</tr>\n";
}
print "</tbody></table>";
print "</form>";
#print "</td></tr>";

	
function udskriv($start, $slut, $sort, $udskriv, $udvalg) {
global $lagerantal;
global $varenummer;
global $v_startstjerne;
global $v_slutstjerne;
global $v_strlen;
global $beskrivelse;
global $b_startstjerne;
global $b_slutstjerne;
global $b_strlen;
global $forslag;
global $beholdning;
global $i_tilbud;
global $i_ordre;
global $i_forslag;
global $bestilt;
global $brugernavn;
global $bgcolor;
global $bgcolor5;
global $alle_varer;
global $charset;
global $popup;
global $vis_lev;
global $incl_moms;

$tidspkt=time("u");

$z=0;$z1=0;
$linjebg=NULL;
$varer_i_ordre=array();

$vis_VG=array();
$vis_K=array();
if ($r = db_fetch_array(db_select("select * from grupper where art='VV' and box1='$brugernavn'",__FILE__ . " linje " . __LINE__))) {
	$vis_VG=explode(",",$r['box2']);
	if ($r['box3']) $vis_K=explode(",",$r['box3']);
	else $vis_VG[0]=1;
	$vis_lukkede=$r['box4'];
} else db_modify("insert into grupper (beskrivelse, art, box1, box2, box3, box4) values ('varevisning', 'VV', '$brugernavn', 'on', 'on', 'on')",__FILE__ . " linje " . __LINE__); 

if ($vis_lukkede!='on') {
	$udvalg=$udvalg. " and lukket != '1'"; 
}
if (!$vis_VG[0]) {
	if ($vis_VG[1]) {
		$udvalg=$udvalg. " and (gruppe = '$vis_VG[1]'";
		$x=2; 
		while ($vis_VG[$x]) {
			$udvalg=$udvalg. " or gruppe = '$vis_VG[$x]'";
			$x++;
		}
		$udvalg=$udvalg. ")";
	} else $udvalg=$udvalg. " and gruppe = ''";
}
if (!$vis_K[0]) {	
	$lev_vare_liste=array();	
	$x=1; 
	if ($vis_K[1]) {
		$tmp="where lev_id = '$vis_K[1]'";
		$x=2;
		while ($vis_K[$x]) {
			$tmp=$tmp." or lev_id = '$vis_K[$x]'"; 
			$x++;
		}	
	}  
	$y=0;
	$q = db_select("select distinct vare_id from vare_lev $tmp",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$y++;
		$lev_vare_liste[$y]=$r['vare_id'];
	}
}
if ($forslag) { 
	$x=0;
	$lagergrupper=array();
	$q=db_select("select * from grupper where art='VG' and box8='on'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){ 
		$x++;
		$lagergrupper[$x]=$r['kodenr'];
	}
}		
if (($beholdning||$forslag)&&!$udskriv) $varer_i_ordre=find_varer_i_ordre(); 
if (!$slut) $slut=$start+50; 
if ($beskrivelse||$varenummer||$forslag) $slut=999999;
$query = db_select("select * from varer where id > 0 $udvalg order by $sort",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
#	if ($row['stregkode'] && $varenummer == $row['stregkode']) { #remmet phr 2011-04-11 grundet probl. med 2 varer hvor ene vares nummer = anden vares stregkode.
#		$varenummer=$row['varenr'];
#	} 
	$z++;	# $z bruges som taeller til at kontrollere hvor mange linjer der indgaar i listen.
$vis1=1;
$vis2=1;
if ($udskriv && $forslag && !$alle_varer) {
		if (isset($forslag[$z])) {
			$vis1=1; $vis2=1;
		} else $vis1=0;
	}
// Her frasorteres varer som ikke kommer fra den valgte lev.	
	if ((isset($vis_K[1]) && $vis1==1 && isset($lev_vare_liste) && in_array($row['id'],$lev_vare_liste)) || $vis_K[0]); #gor intet
	elseif (!$vis_K[1] && $vis1==1 && isset($lev_vare_liste) && !in_array($row['id'],$lev_vare_liste)); #gor intet
	elseif(!$forslag) {$vis1=0; $z--;}
	if ((isset($vis_K[1]) && $vis2==1 && isset($lev_vare_liste) && in_array($row['id'],$lev_vare_liste)) || $vis_K[0]); #gor intet
	elseif (!$vis_K[1] && $vis2==1 && isset($lev_vare_liste) && !in_array($row['id'],$lev_vare_liste)); #gor intet
	else $vis2=0;
	// Her frasorteres varer i bestillingsforslag som ikke lagerfoerte - skal staa nederst i frasortering.	
	if ($forslag && !in_array($row['gruppe'],$lagergrupper)) {$vis1=0;$vis2=0;}	
// frasortering slut	
	if ((($z>=$start&&$z<$slut)||$forslag)&&$vis1==1&&$vis2==1){
	$z1++;
	if ($udskriv) {
			$y=0;
			($linjebg!=$bgcolor)?$linjebg=$bgcolor:$linjebg=$bgcolor5;
			($row['lukket']=='1')?$color='red':$color='black';
			print "<tr bgcolor=\"$linjebg\">";
			$kort="kort".$row['id'];
			if ($popup) print "<td onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kort=window.open('varekort.php?opener=varer.php&amp;id=$row[id]&amp;returside=../includes/luk.php','$kort','scrollbars=1,resizable=1');$kort.focus();\"><FONT style=\"color:$color;\"><span style=\"text-decoration: underline;\">".htmlentities(stripslashes($row['varenr']),ENT_COMPAT,$charset)."</span></td>";
			else print "<td> <a href=\"varekort.php?id=$row[id]&amp;returside=varer.php\"><FONT style=\"COLOR:$color;\">".htmlentities(stripslashes($row['varenr']),ENT_COMPAT,$charset)."</font></a></td>";	
			print "<td><FONT style=\"color:$color\">".htmlentities(stripslashes($row['enhed']),ENT_COMPAT,$charset)."</font><br></td>";
			print "<td><FONT style=\"color:$color\">".htmlentities(stripslashes($row['beskrivelse']),ENT_COMPAT,$charset)."</font><br></td>";
			if (!$vis_lev){
				if ($lagerantal>1  && !$forslag) {
					$r2=db_fetch_array(db_select("select sum(beholdning) as lagersum from lagerstatus where vare_id = $row[id]",__FILE__ . " linje " . __LINE__));
					$diff=$row['beholdning']-$r2['lagersum'];
					for ($x=1;$x<=$lagerantal; $x++) {
						$qtxt="select lager, beholdning from lagerstatus where vare_id = $row[id] and lager = $x";
						$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$y=$r2['beholdning'];
						if ($x==1)$y+=$diff;
						if ($y >= 1) print "<td align=center onClick=\"lagerflyt($row[id], $x)\" onMouseOver=\"this.style.cursor = 'pointer'\"><span title= 'Flyt til andet lager'><u>".dkdecimal($y)."</u></td>";
						else print "<td align=center>".dkdecimal($y)."</td></td>";	
					}
				}
#				if (($beholdning||$forslag)&&!$udskriv) {
#				if (in_array($row['id'],$varer_i_ordre))	{
				if ($forslag || $beholdning)	{
					$tmp=find_beholdning($row['id'],$udskriv);
					$i_tilbud[$z]=$tmp[1];
					$it_ordrenr[$z]=$tmp[5];
					$i_ordre[$z]=$tmp[2];
					$io_ordrenr[$z]=$tmp[6];
					$i_forslag[$z]=$tmp[3];
					$if_ordrenr[$z]=$tmp[7];
					$bestilt[$z]=$tmp[4];
					$b_ordrenr[$z]=$tmp[8];
				}
				if ($beholdning) {
					($it_ordrenr[$z])?$title="title=\"Tilbud: $it_ordrenr[$z]\"":$title="title=\"\"";
					print "<td align=\"right\" $title>$i_tilbud[$z]</td>";
					($io_ordrenr[$z])?$title="title=\"Ordre: $io_ordrenr[$z]\"":$title="title=\"\"";
					print "<td align=\"right\" $title>$i_ordre[$z]</td>";
					($b_ordrenr[$z])?$title="title=\"Ordre: $b_ordrenr[$z]\"":$title="title=\"\"";
					print "<td align=\"right\" $title>$bestilt[$z]</td>";
				}
				print "<td align=right>".dkdecimal($row['beholdning'])."</td>";
				if ($forslag){
					$tmp=$row['beholdning']-$i_ordre[$z];
					if ($row['min_lager']*1>$tmp || $alle_varer) {
						$gb=$gb+1;
						$genbestil[$z]=$row['max_lager']-$row['beholdning']+$i_ordre[$z];
						if ($genbestil[$z] < 0) $genbestil[$z]=0;	
						print "<td align=right><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:60px\" name=\"gb_antal_$gb\" value=\"$genbestil[$z]\"></td>";
						print "<input type=\"hidden\" name=\"gb_id_$gb\" value=\"$row[id]\">";
						print "<input type=\"hidden\" name=\"genbestil_ant\" value=\"$gb\">";
					} else print "<td></td>";
				}
			}
			if (!$forslag) {
				$salgspris=dkdecimal($row['salgspris']*(100+$incl_moms)/100);
				print "<td align=right>$salgspris<br></td>";
			}
			if ($vis_lev=='on') {
				$query2 = db_select("select kostpris, lev_id, lev_varenr from vare_lev where vare_id = $row[id] order by posnr",__FILE__ . " linje " . __LINE__);
				$row2 = db_fetch_array($query2);
				if ($row2['lev_id']) {
					$lev_varenr=$row2['lev_varenr'];
					$levquery = db_select("select kontonr, firmanavn from adresser where id=$row2[lev_id]",__FILE__ . " linje " . __LINE__);
					$levrow = db_fetch_array($levquery);
					$kostpris=dkdecimal($row2['kostpris']);
				}
				elseif ($row['samlevare']=='on') {$kostpris=dkdecimal($row['kostpris']);}
				print "<td align=right>$kostpris</td>";
				$query2 = db_select("select box8 from grupper where art='VG' and kodenr='$row[gruppe]'",__FILE__ . " linje " . __LINE__);
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
					$query2 = db_select("select id, ordre_id, antal from ordrelinjer where vare_id = $row[id]",__FILE__ . " linje " . __LINE__);
					while ($row2 =db_fetch_array($query2)) {
						if (in_array($row2['ordre_id'],$ordre_id)) {
							$x=$x+$row2['antal'];	 
							$query3 = db_select("select antal from batch_salg where linje_id = $row2[id]",__FILE__ . " linje " . __LINE__);
							while ($row3=db_fetch_array($query3)) {$x=$x-$row3['antal'];}
						}
					}	
					$linjetext="<span title= 'Der er $x i ordre'>";
					print "<td align=right>$linjetext$row[beholdning]</span></td>";		
					print "<td></td>";		
					print "<td>$levrow[kontonr] - ".htmlentities(stripslashes($levrow['firmanavn']),ENT_COMPAT,$charset)."</td>";
					print "<td>".htmlentities(stripslashes($lev_varenr),ENT_COMPAT,$charset)."</td>";
				}
				else {print "<td></td>";}	 
			}
			print "</tr>\n";
		} elseif ($forslag||$beholdning) {
			if (in_array($row['id'],$varer_i_ordre)) {
				$tmp=find_beholdning($row['id'],$udskriv);
				$i_tilbud[$z]=$tmp[1];
				$it_ordrenr[$z]=$tmp[5];
				$i_ordre[$z]=$tmp[2];
				$io_ordrenr[$z]=$tmp[6];
				$i_forslag[$z]=$tmp[3];
				$if_ordrenr[$z]=$tmp[7];
				$bestilt[$z]=$tmp[4];
				$b_ordrenr[$z]=$tmp[8];
			} else {
				$i_tilbud[$z]=0;
				$i_ordre[$z]=0;
				$i_forslag[$z]=0;
				$bestilt[$z]=0;
			}
			if ($row['min_lager']*1>($row['beholdning']-$i_ordre[$z]+$i_forslag[$z]+$bestilt[$z])) {
				$genbestil[$z]=$row['max_lager']-$row['beholdning']+$i_ordre[$z]-($i_forslag[$z]+$bestilt[$z]);
				if ($forslag) {
						$forslag[$z]=$row['id'];
				}
			}
		}
	} elseif ($udskriv && $z>=$slut && !$forslag) break;
	if ($z>=$slut) break;
	if (time("u")-$tidspkt>60) {
		print "<BODY onLoad=\"javascript:alert('Timeout - reducer linjeantal')\">";
		break;
	}
}
return($z);
}# endfunc udskriv
	
##############################################
function find_beholdning($vare_id, $udskriv) {

	$x=0;
	$ordre_id=array();
	$query2 = db_select("select id from ordrer where status < 1 and art = 'DO'",__FILE__ . " linje " . __LINE__);
	while ($row2 =db_fetch_array($query2)){
		$x++;
		$ordre_id[$x]=$row2['id'];
	}
	$x=0;
	$y='';
	$beholdning[1]=0;
	$beholdning[2]=0;
	$beholdning[3]=0;
	$beholdning[4]=0;
	$beholdning[5]='';
	$beholdning[6]='';
	$beholdning[7]='';
	$beholdning[8]='';
#if ($vare_id==81) echo "select ordrelinjer.id as linje_id, ordrelinjer.ordre_id as ordre_id, ordrelinjer.antal as antal,ordrer.ordrenr as ordrenr,ordrer.status as status,ordrer.art as art from ordrelinjer,ordrer where ordrelinjer.vare_id = $vare_id and ordrer.id=ordrelinjer.ordre_id<br>";
	$query2 = db_select("select ordrelinjer.id as linje_id, ordrelinjer.ordre_id as ordre_id, ordrelinjer.antal as antal,ordrer.ordrenr as ordrenr,ordrer.status as status,ordrer.art as art from ordrelinjer,ordrer where ordrelinjer.vare_id = $vare_id and ordrer.id=ordrelinjer.ordre_id",__FILE__ . " linje " . __LINE__);
	while ($row2 =db_fetch_array($query2)) {
		if ($row2['status']<1 && $row2['art']=='DO') {
			$beholdning[1]+=$row2['antal'];
			($beholdning[5])?$beholdning[5].=",".$row2['ordrenr']:$beholdning[5].=$row2['ordrenr'];
		}
		elseif ($row2['status']<3 && $row2['art']=='DO') {
			$beholdning[2]+=$row2['antal'];
			($beholdning[6])?$beholdning[6].=",".$row2['ordrenr']:$beholdning[6].=$row2['ordrenr'];
			$query3 = db_select("select antal from batch_salg where linje_id = '$row2[linje_id]'",__FILE__ . " linje " . __LINE__);
			while ($row3=db_fetch_array($query3)) {$beholdning[2]-=$row3['antal'];}
		}	elseif ($row2['status']<1 && $row2['art']=='KO') {
			$beholdning[3]+=$row2['antal'];
			($beholdning[7])?$beholdning[7].=",".$row2['ordrenr']:$beholdning[7].=$row2['ordrenr'];
		}	elseif ($row2['status']<3 && $row2['art']=='KO') {
			$beholdning[4]+=$row2['antal'];
			($beholdning[8])?$beholdning[8].=",".$row2['ordrenr']:$beholdning[8].=$row2['ordrenr'];
			$query3 = db_select("select antal from batch_kob where linje_id = '$row2[linje_id]'",__FILE__ . " linje " . __LINE__);
			while ($row3=db_fetch_array($query3)) {$beholdning[4]-=$row3['antal'];}
		}
	}	
	return $beholdning;
} #endfunc find_beholdning()
##############################################
function find_beholdning_xx($vare_id, $udskriv) {

$x=0;
$ordre_id=array();
$query2 = db_select("select id from ordrer where status < 1 and art = 'DO'",__FILE__ . " linje " . __LINE__);
while ($row2 =db_fetch_array($query2)){
	$x++;
	$ordre_id[$x]=$row2['id'];
}
$x=0;
$y='';
$query2 = db_select("select ordrelinjer.id, ordrelinjer.ordre_id, ordrelinjer.antal,ordrer.ordrenr from ordrelinjer,ordrer where ordrelinjer.vare_id = $vare_id and ordrer.id=ordrelinjer.ordre_id",__FILE__ . " linje " . __LINE__);
while ($row2 =db_fetch_array($query2)) {
	if (in_array($row2['ordre_id'],$ordre_id)) {
		$x=$x+$row2['antal'];	 
		($y)?$y.=",".$row2['ordrenr']:$y.=$row2['ordrenr'];
	}
}	
#print "<td align=right> $x</span></td>";
$beholdning[1]=$x;
$beholdning[5]=$y;
$x=0;
$ordre_id=array();
$query2 = db_select("select id from ordrer where (status = 1 or status = 2) and art = 'DO'",__FILE__ . " linje " . __LINE__);
while ($row2 =db_fetch_array($query2)){
	$x++;
	$ordre_id[$x]=$row2['id'];
}
$x=0;
$y='';
$query2 = db_select("select ordrelinjer.id, ordrelinjer.ordre_id, ordrelinjer.antal,ordrer.ordrenr from ordrelinjer,ordrer where ordrelinjer.vare_id = $vare_id and ordrer.id=ordrelinjer.ordre_id",__FILE__ . " linje " . __LINE__);
while ($row2 =db_fetch_array($query2)) {
	if (in_array($row2['ordre_id'],$ordre_id)) {
		$x=$x+$row2['antal'];	 
		($y)?$y.=",".$row2['ordrenr']:$y.=$row2['ordrenr'];
		$query3 = db_select("select antal from batch_salg where linje_id = $row2[id]",__FILE__ . " linje " . __LINE__);
		while ($row3=db_fetch_array($query3)) {$x=$x-$row3['antal'];}
	}
}	
#print "<td align=right>  $x</span></td>";
$beholdning[2]=$x;
$beholdning[6]=$y;
$x=0;
$ordre_id=array();
$query2 = db_select("select id from ordrer where status < 1 and art = 'KO'",__FILE__ . " linje " . __LINE__);
while ($row2 =db_fetch_array($query2)){
	$x++;
	$ordre_id[$x]=$row2['id'];
}

$x=0;
$y='';
$query2 = db_select("select ordrelinjer.id, ordrelinjer.ordre_id, ordrelinjer.antal,ordrer.ordrenr from ordrelinjer,ordrer where ordrelinjer.vare_id = $vare_id and ordrer.id=ordrelinjer.ordre_id",__FILE__ . " linje " . __LINE__);
while ($row2 =db_fetch_array($query2)) {
	if (in_array($row2[ordre_id],$ordre_id)) {
		$x=$x+$row2['antal'];	 
		($y)?$y.=",".$row2['ordrenr']:$y.=$row2['ordrenr'];
		$query3 = db_select("select antal from batch_kob where linje_id = $row2[id]",__FILE__ . " linje " . __LINE__); #_salg rettet til _kob 20090215
		while ($row3=db_fetch_array($query3)) {$x=$x-$row3['antal'];}
	}
}	
$beholdning[3]=$x;
$beholdning[7]=$y;
$x=0;
$ordre_id=array();
$query2 = db_select("select id from ordrer where status >= 1 and status <= 2 and art = 'KO'",__FILE__ . " linje " . __LINE__);
while ($row2 =db_fetch_array($query2)){
	$x++;
	$ordre_id[$x]=$row2['id'];
}
$x=0;
$y='';
$query2 = db_select("select ordrelinjer.id, ordrelinjer.ordre_id, ordrelinjer.antal,ordrer.ordrenr from ordrelinjer,ordrer where ordrelinjer.vare_id = $vare_id and ordrer.id=ordrelinjer.ordre_id",__FILE__ . " linje " . __LINE__);
while ($row2 =db_fetch_array($query2)) {
	if (in_array($row2['ordre_id'],$ordre_id)) {
		$x=$x+$row2['antal'];	 
		($y)?$y.=",".$row2['ordrenr']:$y.=$row2['ordrenr'];
		$query3 = db_select("select antal from batch_kob where linje_id = $row2[id]",__FILE__ . " linje " . __LINE__);  #_salg rettet til _kob 20090215 
		while ($row3=db_fetch_array($query3)) {$x=$x-$row3['antal'];}
	}
}	
$beholdning[4]=$x;
$beholdning[8]=$y;
#print "<td align=right> $x</span></td>";
return $beholdning;
} #endfunc find_beholdning.xx()
################################################################


function genbestil($vare_id, $antal) {
	$r = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
	if ($r[ansat_id]) {
		$r = db_fetch_array(db_select("select navn from ansatte where id = $r[ansat_id]",__FILE__ . " linje " . __LINE__));
		if ($r[navn]) $ref=$r['navn'];
	}
	if ($r = db_fetch_array(db_select("select * from vare_lev where vare_id = $vare_id order by posnr",__FILE__ . " linje " . __LINE__))) {
		$lev_id=$r['lev_id'];
		$lev_varenr=$r['lev_varenr'];
		$pris=$r['kostpris']*1;
		$ordredate=date("Y-m-d");
		if ($r = db_fetch_array(db_select("select id, sum from ordrer where konto_id = $lev_id and status < 1 and ordredate = '$ordredate'",__FILE__ . " linje " . __LINE__))) {
			$sum=$r['sum']*1;
			$ordre_id=$r[id];
		} else {
			if ($r = db_fetch_array(db_select("select ordrenr from ordrer where art='KO' or art='KK' order by ordrenr desc",__FILE__ . " linje " . __LINE__))) $ordrenr=$r[ordrenr]+1;
			else $ordrenr=1;
			$r = db_fetch_array(db_select("select * from adresser where id = $lev_id",__FILE__ . " linje " . __LINE__));
			if ($r['gruppe']) {
				$r1 = db_fetch_array(db_select("select box1 from grupper where kode = 'K' and art = 'KG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
				$kode=substr($r1[box1],0,1); $kodenr=substr($r1[box1],1);
			}	else {
				$r = db_fetch_array(db_select("select varenr from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
				print "<BODY onLoad=\"javascript:alert('Leverand&oslash;rgruppe ikke korrekt opsat for varenr $r[varenr]')\">";
			}
			$r1 = db_fetch_array(db_select("select box2 from grupper where art = 'KM' and kode = '$kode' and kodenr = '$kodenr'",__FILE__ . " linje " . __LINE__));
			$momssats=$r1['box2']*1;
			db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, betalingsdage, betalingsbet, cvrnr, notes, art, ordredate, momssats, status, ref) values ($ordrenr, $r[id], '$r[kontonr]', '$r[firmanavn]', '$r[addr1]', '$r[addr2]', '$r[postnr]', '$r[bynavn]', '$r[land]', '$r[betalingsdage]', '$r[betalingsbet]', '$r[cvrnr]', '$r[notes]', 'KO', '$ordredate', '$momssats', '0', '$ref')",__FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select id from ordrer where ordrenr='$ordrenr' and art = 'KO'",__FILE__ . " linje " . __LINE__));
			$ordre_id=$r[id];
		}
		$r = db_fetch_array(db_select("select varer.varenr as varenr,varer.beskrivelse as beskrivelse,vare_lev.lev_varenr as lev_varenr from varer,vare_lev where varer.id='$vare_id' and vare_lev.vare_id='$vare_id'",__FILE__ . " linje " . __LINE__));
		$varenr=db_escape_string($r['varenr']);
		$lev_varenr=db_escape_string($r['lev_varenr']);
		$beskrivelse=db_escape_string($r['beskrivelse']);
		db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, pris, lev_varenr, antal, momsfri) values ('$ordre_id', '1000', '$varenr', '$vare_id', '$beskrivelse', '$r[enhed]', '$pris', '$lev_varenr', '$antal', '$r[momsfri]')",__FILE__ . " linje " . __LINE__);
		$sum=$sum+$pris*$antal;	
		db_modify("update ordrer set sum = '$sum' where id = $ordre_id",__FILE__ . " linje " . __LINE__);	
	} else { 
		print "Leverand&oslash;r findes ikke<br>";
	}
}

#####################################################
function find_varer_i_ordre() { #tilfoejet 2008.01.28 for hastighedsoptimering af genbestilling
	$ordreliste=NULL;
	$q2=db_select("select id from ordrer where status < 3 and art = 'DO'",__FILE__ . " linje " . __LINE__);
	while ($r2=db_fetch_array($q2)) {
		if (!$ordreliste) $ordreliste="where ordre_id='".$r2['id']."'";
		else $ordreliste=$ordreliste." or ordre_id='".$r2['id']."'";
	} 
	if ($ordreliste) {
		$x=0;
		$varer_i_ordre=array();
		$q2=db_select("select distinct(vare_id) from ordrelinjer $ordreliste",__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)) {
			$x++;
			$varer_i_ordre[$x]=$r2['vare_id'];
		}
	}
	$ordreliste=NULL;
	$q2=db_select("select id from ordrer where (status = 1 or status = 2) and art = 'KO'",__FILE__ . " linje " . __LINE__);
	while ($r2=db_fetch_array($q2)) {
		if (!$ordreliste) $ordreliste="where ordre_id='".$r2['id']."'";
		else $ordreliste=$ordreliste." or ordre_id='".$r2['id']."'";
	} 
	if ($ordreliste) {
#		$varer_i_ordre=array();
		$q2=db_select("select distinct(vare_id) from ordrelinjer $ordreliste",__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)) {
			if (!in_array($r2['vare_id'],$varer_i_ordre)) {
				$x++;
				$varer_i_ordre[$x]=$r2['vare_id'];
			}
		}
	}
	return $varer_i_ordre;
}

?>
</td></tr>
</tbody></table>
</center></body></html>
