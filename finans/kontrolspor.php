<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------finans/kontrolspor.php------ patch 4.0.7 --- 2023.03.04 ---
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20160226 PHR Diverse oprydning...
// 20170424 PHR Medtager nu transaktioner selvom konto mangler i kontoplan.
// 20170509 PHR Søgning med wildcards i beskrivelse dysfunktionel
// 20170524 PHR Rettet lidt i CSV.
// 20190107 MSC Rettet isset fejl og tilpasset topmenu designet
// 20190207 MSC - Rettet topmenu design til
// 20190212 MSC - Rettet topmenu design til
// 20190319	PHR - Changed 'udvaelg' to search in '(debet or kredit)' if both filled.
// 20210708 LOE - Translated some of these texts from Danish to English and Norsk
// 20210709 LOE - Bug fixed findtekst function wasn't working here
// 20210721 LOE - Did translations on title tags


ob_start();
@session_start();
$s_id=session_id();
$title="Kontrolspor";
$modulnr=4;
$css="../css/standard.css";

print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/overlib.js\"></script>";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");
include("../includes/topline_settings.php");

$id = if_isset($_GET['id']);
$kontonr = if_isset($_GET['kontonr']);
$bilag = if_isset($_GET['bilag']);
$transdate = if_isset($_GET['transdate']);
$logdate = if_isset($_GET['logdate']);
$logtime = if_isset($_GET['logtime']);
$debet = if_isset($_GET['debet']);
$kredit = if_isset($_GET['kredit']);
$kladde_id = if_isset($_GET['kladde_id']);
$projekt_id = if_isset($_GET['projekt_id']);
$afd = if_isset($_GET['afd']);
$beskrivelse = if_isset($_GET['beskrivelse']);
$faktura = if_isset($_GET['faktura']);
$sort = if_isset($_GET['sort']);
$nysort = if_isset($_GET['nysort']);
$idnumre = if_isset($_GET['idnumre']);
$kontonumre = if_isset($_GET['kontonumre']);
$fakturanumre = if_isset($_GET['fakturanumre']);
$bilagsnumre = if_isset($_GET['bilagsnumre']);
$debetbelob = if_isset($_GET['debetbelob']);
$kreditbelob = if_isset($_GET['kreditbelob']);
$transdatoer = if_isset($_GET['transdatoer']);
$logdatoer = if_isset($_GET['logdatoer']);
$logtid = if_isset($_GET['logtid']);
$kladdenumre = if_isset($_GET['kladdenumre']);
$projektnumre = if_isset($_GET['projektnumre']);
$kassenumre = if_isset($_GET['kassenumre']);
$beskrivelse = if_isset($_GET['beskrivelse']);
$start = if_isset($_GET['start']);
$valuta =  if_isset($_GET['valuta']);
$valutakurs =  if_isset($_GET['valutakurs']);
$csv =  if_isset($_GET['csv']);

if (!isset ($_POST['submit'])) $_POST['submit'] = 0;
if (!isset ($valg)) $valg = 0;
if (!isset ($hreftext)) $hreftext = 0;
if (!isset ($kontoid)) $kontoid = 0;
if (!isset ($_POST['projektnumre'])) $_POST['projektnumre'] = 0;
if (!isset ($_POST['beskrivelse'])) $_POST['beskrivelse'] = 0;
if (!isset ($_POST['nysort'])) $_POST['nysort'] = NULL;
if (!isset ($projeknumre)) $projeknumre = 0;
if (!isset ($_COOKIE['saldi_kontrolspor'])) $_COOKIE['saldi_kontrolspor'] = NULL;

if ($submit=$_POST['submit']){
	$linjeantal = trim($_POST['linjeantal']);
	$idnumre = trim($_POST['idnumre']);
	$kontonumre = trim($_POST['kontonumre']);
	$fakturanumre = trim($_POST['fakturanumre']);
	$bilagsnumre = trim($_POST['bilagsnumre']);
	$debetbelob = trim($_POST['debetbelob']);
	$kreditbelob = trim($_POST['kreditbelob']);
	$transdatoer = trim($_POST['transdatoer']);
	$logdatoer = trim($_POST['logdatoer']);
	$logtid = trim($_POST['logtid']);
	$kladdenumre = trim($_POST['kladdenumre']);
	$projektnumre = trim($_POST['projektnumre']);
	$kassenumre = trim($_POST['kassenumre']);
	$beskrivelse = trim($_POST['beskrivelse']);
	$sort = $_POST['sort'];
	$nysort = $_POST['nysort'];

	$cookievalue="$idnumre;$kontonumre;$fakturanumre;$bilagsnumre;$debetbelob;$kreditbelob;$transdatoer;$logdatoer;$logtid;$kladdenumre;$projeknumre;$beskrivelse;$linjeantal";
	setcookie("saldi_kontrolspor", $cookievalue);
} else {
	list ($idnumre, $kontonumre, $fakturanumre, $bilagsnumre, $debetbelob, $kreditbelob, $transdatoer, $logdatoer, $logtid, $kladdenumre, $projeknumre, $beskrivelse, $linjeantal) = array_pad(explode(";", $_COOKIE['saldi_kontrolspor']), 13, null);
	$beskrivelse=str_replace("semikolon",";",$beskrivelse);
}
ob_end_flush();  //Sender det "bufferede" output afsted... 

if (!$idnumre&&!$kontonumre&&!$fakturanumre&&!$bilagsnumre&&!$debetbelob&&!$kreditbelob&&!$transdatoer&&!$logdatoer&&!$logtid&&!$kladdenumre&&!$projeknumre&&!$beskrivelse) {
	$r=db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
	$transdatoer="01".$r['box1'].substr($r['box2'],-2).":31".$r['box3'].substr($r['box4'],-2);
}

$r=db_fetch_array(db_select("select * from grupper where art = 'PRJ' and kodenr!='0'",__FILE__ . " linje " . __LINE__));
(isset($r['id']))?$vis_projekt=$r['id']:$vis_projekt=NULL;

if ($logtid) {
	list ($h,$m)=explode(":",$logtid);
	$h=$h*1;
	$m=$m*1;
	if (strlen($h)>2) $h=substr($h,-2);
	if (strlen($m)>2) $m=substr($m,-2);
	$logtid="$h:$m";
}

# $valg="idnumre=$idnumre&kontonumre=$kontonumre&fakturanumre=$fakturanumre&bilagsnumre$bilagsnumre&debetbelob=$debetbelob&kreditbelob=$kreditbelob&transdatoer=$transdatoer&logdatoer=$logdatoer&logtid=$logtid&kladdenumre=$kladdenumre&projeknumre=$projeknumre&$beskrivelse = $_GET['beskrivelse'];


$tidspkt=date("U");

$modulnr=2;


if (!$sort) {$sort = "id desc";}
elseif ($nysort==$sort){$sort=$sort." desc";}
elseif ($nysort) {$sort=$nysort;}

if (!isset ($startdato)) $startdato = 0;
if (!isset ($mf)) $mf = 0;
if (!isset ($aar_fra)) $aar_fra = 0;
if (!isset ($slutdato)) $slutdato = 0;
if (!isset ($mt)) $mt = 0;
if (!isset ($aar_til)) $aar_til = 0;
if (!isset ($konto_fra)) $konto_fra = 0;
if (!isset ($konto_til)) $konto_til = 0;
if (!isset ($ansat_fra)) $ansat_fra = 0;
if (!isset ($ansat_til)) $ansat_til = 0;
if (!isset ($projekt_fra)) $projekt_fra = 0;
if (!isset ($projekt_til)) $projekt_til = 0;

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\"><a href=rapport.php accesskey=L title='Klik for at komme tilbage til rapporter'><i class='fa fa-close'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu=='S') {
	print "<table width=100% height=100% cellpadding=\"0\" cellspacing=\"0px\" border=\"0\" valign = \"top\" align='center'> ";
	print "<tr><td height = 25 align=center valign=top>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
	print "<tr>";

	print "<td width=10%>";

	if ($popup) print "<a href=../includes/luk.php accesskey=L>
					   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
					   .findtekst(30,$sprog_id)."</button></a></td>";
	else print "<a href=rapport.php accesskey=L>
			    <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
				.findtekst(30,$sprog_id)."</button></a></td>";

	print "<td width=80% align='center' style='$topStyle'>".findtekst(905,$sprog_id)."</td>";

	print "<td width=10%><a href=kontrolspor.php?csv=1&valg=$valg $hreftext' title=\"".findtekst(505,$sprog_id)."\">
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
			CSV</button></a></td>";

	print "</tr>\n";
	print "</tbody></table></td></tr>";
	print "<tr style='height: 10px;'><td>";
} else {
	print "<table width=100% height=100% cellpadding=\"0\" cellspacing=\"0px\" border=\"0\" valign = \"top\" align='center'> ";
	print "<tr><td height = 25 align=center valign=top>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
	print "<tr>";
	print "<td width=10% $top_bund>";
	if ($popup) print "<a href=../includes/luk.php accesskey=L>".findtekst(30,$sprog_id)."</a></td>";
	else print "<a href=rapport.php accesskey=L>".findtekst(30,$sprog_id)."</a></td>";
	print "<td width=80% $top_bund>".findtekst(905,$sprog_id)."</td>";
	print "<td width=10% $top_bund><a href=kontrolspor.php?csv=1&valg=$valg $hreftext' title=\"".findtekst(505,$sprog_id)."\">CSV</a></td>";
	print "</tr>\n";
	print "</tbody></table></td></tr>";
	print "<tr style='height: 10px;'><td>";
}
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0 class='dataTable'><tbody>";

print "<form name=transaktionsliste action=kontrolspor.php method=post>";
if (!$linjeantal) $linjeantal=50;
$next=udskriv($idnumre, $bilagsnumre, $kladdenumre, $fakturanumre, $kontonumre, $transdatoer, $logdatoer, $debetbelob, $kreditbelob, $logtid, $beskrivelse, $sort, $start+50,'',$projektnumre,$kassenumre);
if ($start>=$linjeantal) {
	$tmp=$start-$linjeantal;
	print "<td width=10%><a href='kontrolspor.php?sort=$sort&start=$tmp'><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
} else print  "<td></td>";
print "<td  width=80% align=center><span title= '".findtekst(1609, $sprog_id)."'><input class=\"inputbox\" type=text style=\"text-align:center;width:100px\" name=\"linjeantal\" value=\"$linjeantal\"></td>";
$tmp=$start+$linjeantal;
if ($next>0) {
	print "<td  width=10% align=\"right\"><a href='kontrolspor.php?sort=$sort&start=$tmp'><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
} else print  "<td></td>";
print "</tr>\n";

print "</tbody></table>";
print " </td></tr><tr><td align=center valign=top>";
print "<table cellpadding=1 cellspacing=1 border=0 width=100% valign='top' class='dataTable'>";

print "<tbody>";


print "<tr>";
print "<td align=right><b><a href='kontrolspor.php?nysort=id&sort=$sort&valg=$valg$hreftext'>Id</b></td>";
print "<td align=right><b><a href='kontrolspor.php?nysort=transdate&sort=$sort&valg=$valg$hreftext'>".findtekst(635,$sprog_id)."</a></b></td>"; #20210708
print "<td align=right><b><a href='kontrolspor.php?nysort=logdate&sort=$sort&valg=$valg$hreftext'>".findtekst(1202,$sprog_id).". ".findtekst(635,$sprog_id)."</a></b></td>";
print "<td align=right><b>Tidspkt.</a></b></td>";
print "<td align=right><b><a href='kontrolspor.php?nysort=kladde_id&sort=$sort&valg=$valg$hreftext'>".findtekst(1087,$sprog_id)."</a></b></td>";
print "<td align=right><b><a href='kontrolspor.php?nysort=bilag&sort=$sort&valg=$valg$hreftext'>".findtekst(671,$sprog_id)."</a></b></td>";
print "<td align=right><b><a href='kontrolspor.php?nysort=kontonr&sort=$sort&valg=$valg$hreftext'>".findtekst(592,$sprog_id)."</b></td>";
print "<td align=right><b><a href='kontrolspor.php?nysort=faktura&sort=$sort&valg=$valg$hreftext'>".findtekst(828,$sprog_id)."</a></b></td>";
print "<td align=right><b><a href='kontrolspor.php?nysort=debet&sort=$sort&valg=$valg$hreftext'>".findtekst(1000,$sprog_id)."</a></b></td>";
print "<td align=right><b><a href='kontrolspor.php?nysort=kredit&sort=$sort&valg=$valg$hreftext'>".findtekst(1001,$sprog_id)."</a></b></td>";
if($vis_projekt) {
		print "<td align=right><b>";
		print "<a href='kontrolspor.php?nysort=projekt&sort=$sort&valg=$valg$hreftext'>".findtekst(533,$sprog_id)."";
		print "</a></b></td>";
}
print "<td align=right><b>Valuta</b></td>";
print "<td align=right><b>Valutakurs</b></td>";
print "<td align=right><b><a href='kontrolspor.php?nysort=kasse&sort=$sort&valg=$valg$hreftext'>".findtekst(931,$sprog_id)."</a></b></td>";
print "<td align=center><b><a href='kontrolspor.php?nysort=beskrivelse&sort=$sort&valg=$valg$hreftext'>".findtekst(1203,$sprog_id)."</a></b></td>";
print "</tr>\n";

print "<form name=ordreliste action=kontrolspor.php method=post>";
print "<input type=hidden name=valg value=\"$valg\">";
print "<input type=hidden name=sort value=\"$sort\">";
#print "<input type=hidden name=nysort value=\"$nysort\">";
print "<input type=hidden name=kontoid value=\"$kontoid\">";
print "<input type=hidden name=start value=\"$start\">";
print "<tr>";
print "<td align=\"right\"><span title= '".findtekst(1610, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"idnumre\" value=\"$idnumre\"></td>";
print "<td align=\"right\"><span title= '".findtekst(1611, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:80px\" name=\"transdatoer\" value=\"$transdatoer\"></td>";
print "<td align=\"right\"><span title= '".findtekst(1611, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:80px\" name=\"logdatoer\" value=\"$logdatoer\"></td>";
print "<td align=\"right\"><span title= '".findtekst(1612, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"logtid\" value=\"$logtid\"></td>";
print "<td align=\"right\"><span title= '".findtekst(1613, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"kladdenumre\" value=\"$kladdenumre\"></td>";
print "<td align=\"right\"><span title= '".findtekst(1614, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"bilagsnumre\" value=\"$bilagsnumre\"></td>";
print "<td align=\"right\"><span title= '".findtekst(1615, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"kontonumre\" value=\"$kontonumre\"></td>";
print "<td align=\"right\"><span title= '".findtekst(1616, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"fakturanumre\" value=\"$fakturanumre\"></td>";
print "<td align=\"right\"><span title= '".findtekst(1617, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:80px\" name=\"debetbelob\" value=\"$debetbelob\"></td>";
print "<td align=\"right\"><span title= '".findtekst(1617, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:80px\" name=\"kreditbelob\" value=\"$kreditbelob\"></td>";
if ($vis_projekt) print "<td align=\"right\"><span title= '".findtekst(1618, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:200px\" name=\"projektnumre\" value=\"$projektnumre\"></td>";
print "<td></td><td></td>";
print "<td align=\"right\"><span title= '".findtekst(1619, $sprog_id)."'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"kassenumre\" value=\"$kassenumre\"></td>";
print "<td><span title= '".findtekst(1620, $sprog_id)."'><input class=\"inputbox\" type=\"text\"  style=\"text-align:left;width:100%\" name=beskrivelse value=\"$beskrivelse\"></td>"; #20210721
print "<td><input class='button green small' type=submit value=\"OK\" name=\"submit\"></td>";

print "</form></tr>\n";
udskriv($idnumre,$bilagsnumre,$kladdenumre,$fakturanumre,$kontonumre,$transdatoer,$logdatoer,$debetbelob,$kreditbelob,$logtid,$beskrivelse,$sort,$start,'skriv',$projektnumre,$kassenumre);
####################################################################################
function udskriv($idnumre, $bilagsnumre, $kladdenumre, $fakturanumre,$kontonumre,$transdatoer,$logdatoer,$debetbelob,$kreditbelob,$logtid,$beskrivelse,$sort,$start,$skriv,$projektnumre,$kassenumre) {

	global $bgcolor;
	global $bgcolor5;
	global $linjeantal;
	global $regnaar;
	global $vis_projekt;
	global $rettigheder;
	global $csv;
	global $sprog_id; #20210709

	$currencyNo[0]   = 0;
	$currencyName[0] = 'DKK';
	$i = 1;
	$qtxt = "select * from grupper where art = 'VK' order by kodenr";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$currencyNo[$i]   = $r['kodenr'];
		$currencyName[$i] = $r['box1'];
		$i++;
	}

	if ($sort=='id') $sort='transaktioner.id';
	$ret_projekt=substr($rettigheder,1,1);

	if ($csv) {
		$fp=fopen("../temp/$db/kontrolspor.csv","w");
		fwrite($fp,"\"Id\";\"Dato\";\"Logdato\";\"Logtid\";\"Kladde id\";\"Bilag\";\"Kontonr\";\"Kontonavn\";\"Faktura\";\"Debet\";\"Kredit\";\"Projekt\";\"Beskrivelse\"\n");
	}
	$udvaelg='';
	if ($idnumre)			$udvaelg.=udvaelg($idnumre, 'transaktioner.id', 'NR');
	if ($bilagsnumre)	$udvaelg.=udvaelg($bilagsnumre, 'transaktioner.bilag', 'NR');
	if ($kladdenumre)	$udvaelg.=udvaelg($kladdenumre, 'transaktioner.kladde_id', 'NR');
	if ($fakturanumre)$udvaelg.=udvaelg($fakturanumre, 'transaktioner.faktura', 'TEXT');
	if ($kontonumre)	$udvaelg.=udvaelg($kontonumre, 'transaktioner.kontonr', 'NR');
	if ($transdatoer)	$udvaelg.=udvaelg($transdatoer, 'transaktioner.transdate', 'DATO');
	if ($logdatoer)		$udvaelg.=udvaelg($logdatoer, 'transaktioner.logdate', 'DATO');
	if ($debetbelob && $kreditbelob) {
		$tmp1=substr(udvaelg($debetbelob, 'transaktioner.debet', 'BELOB'),3);
		$tmp2=substr(udvaelg($kreditbelob, 'transaktioner.kredit', 'BELOB'),3);
		if ($udvaelg) $udvaelg.=" and ";
		$udvaelg.="(($tmp1) or ($tmp2))";
		#		$udvaelg.="(debet='". usdecimal($debetbelob) ."' or kredit='". usdecimal($kreditbelob) ."')";
	} else {
		if ($debetbelob) 	$udvaelg.=udvaelg($debetbelob, 'transaktioner.debet', 'BELOB');
		if ($kreditbelob)  	$udvaelg.=udvaelg($kreditbelob, 'transaktioner.kredit', 'BELOB');
	}
	if ($projektnumre) 	$udvaelg.=udvaelg($projektnumre, 'transaktioner.projekt', '');
	if ($kassenumre) 	$udvaelg.=udvaelg($kassenumre, 'transaktioner.kasse_nr', 'NR');
	if ($logtid) 		$udvaelg.=udvaelg($logtid, 'transaktioner.logtime', 'TID');
	if ($beskrivelse) $udvaelg.=udvaelg($beskrivelse, 'transaktioner.beskrivelse', 'TEXT');
	
	$udvaelg=trim($udvaelg);
	if (substr($udvaelg,0,3)=='and') $udvaelg="where".substr($udvaelg, 3);
	elseif (substr($udvaelg,0,2)=='((') $udvaelg="where ".$udvaelg;
	if ($sort=="logdate") $sort = $sort.", logtime";
	$beskrivelse=trim(strtolower($beskrivelse));
	if (substr($beskrivelse,0,1)=='*'){
		$beskrivelse=substr($beskrivelse,1);
		$startstjerne=1;
	}
	if (substr($beskrivelse,-1,1)=='*') {
		$beskrivelse=substr($beskrivelse,0,strlen($beskrivelse)-1);
		$slutstjerne=1;
	}
	if ($b_strlen=strlen($beskrivelse)) {
	}
	if (!$udvaelg) $udvaelg="where";
	else $udvaelg=$udvaelg." and";
	
	$z=0;
	
	$qtxt="SELECT kontonr,beskrivelse from kontoplan where regnskabsaar='$regnaar' and (kontotype='D' or kontotype='S') order by kontonr";
	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row =db_fetch_array($query)) {
		$kpnavn[$z]=$row['beskrivelse'];
		$kpnr[$z]=$row['kontonr'];
		$z++;
	}
	$z=0;
	if (substr($udvaelg,-3)=='and') $udvaelg=substr($udvaelg,0,strlen($udvaelg)-3);
	
	$qtxt="select * from transaktioner $udvaelg order by $sort";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r =db_fetch_array($q)) {
		$id[$z]=$r['id'];
		$transdate[$z]=$r['transdate'];
		$transtxt[$z]=$r['beskrivelse'];
		$logdate[$z]=$r['logdate'];
		$debet[$z]=$r['debet'];
		$kredit[$z]=$r['kredit'];
		$logtime[$z]=$r['logtime'];
		$kladde_id[$z]=$r['kladde_id'];
		$bilag[$z]=$r['bilag'];
		$faktura[$z]=$r['faktura'];
		$kontonr[$z]=$r['kontonr'];
		$kasse[$z]=$r['kasse_nr'];
		$valuta[$z] = (int)$r['valuta'];
		$kurs[$z]   = (float)$r['valutakurs'];
		if ($valuta[$z] == 0) {
			$valuta[$z] = $currencyName[0];
			$kurs[$z]   = 100;
		} else {
			for ($i = 1; $i < count($currencyNo); $i++) {
				if ($valuta[$z] == $currencyNo[$i]) $valuta[$z] = $currencyName[$i];
			}
		}
		$z++;
	}
	if (!isset ($id)) $id = NULL;
	$x=0;
	if(isset($id)){
		for ($z=0;$z<count($id);$z++) {
		/*
				if ($beskrivelse && in_array($kontonr[$z],$kpnr)) {
					for ($i=0;$i<count($kpnr);$i++){
						if ($kpnr[$i]==$kontonr[$z]) {
							$kontonavn[$z]=$kpnavn[$i];
							$i=count($kpnr);
						}
					}
				}
		*/		
				if ($beskrivelse && $kontonavn[$z]){
					$udskriv=0;
					if ($startstjerne){
						if ($slutstjerne) {
							if (strpos(strtolower($kontonavn[$z]), $beskrivelse)) $udskriv=1;
						} elseif (substr(strtolower($kontonavn[$z]),-$b_strlen,$b_strlen)==$beskrivelse) $udskriv=1;
					} elseif ($slutstjerne) {
						if (substr(strtolower($kontonavn[$z]),0,$b_strlen)==$beskrivelse) $udskriv=1;
					} elseif (strtolower($kontonavn[$z]) == $beskrivelse) $udskriv=1;
				} else $udskriv=1;
				if ($udskriv) $x++;
				if (!isset ($y)) $y = 0;
				if (!isset ($debetsum)) $debetsum = 0;
				if (!isset ($kontonavn[$z])) $kontonavn[$z] = 0;
				if (!isset ($kontonavn)) $kontonavn = 0;
				if (!isset ($linjebg)) $linjebg = 0;
				if (!isset ($kreditsum)) $kreditsum = 0;
				if ((($x>=$start)&&($x<$start+$linjeantal) && ($udskriv)) || $csv){
					$y++;
					if ($csv || $skriv) {
						$transdato=dkdato($transdate[$z]);
						$logdato=dkdato($logdate[$z]);
						$debetsum=afrund($debetsum+$debet[$z],2);
						$kreditsum=afrund($kreditsum+$kredit[$z],2);
						if ($skriv) {
						if (!$kontonavn[$z] && in_array($kontonr[$z],$kpnr)) {
							for ($i=0;$i<count($kpnr);$i++){
								if ($kpnr[$i]==$kontonr[$z]) {
									$kontonavn[$z]=$kpnavn[$i];
		#							echo "$kontonavn[$z]<br>";
									$i=count($kpnr);
								}
							}
						} elseif ($kontonr[$z] && !in_array($kontonr[$z],$kpnr)) {
							$alert= findtekst(1622, $sprog_id);
							#print "<BODY onLoad=\"javascript:alert('Kontroller konto $kontonr[$z]!')\">";
							print "<BODY onLoad=\"javascript:alert('$alert $kontonr[$z]!')\">"; #20210721
						}
					if (!$csv)	{
							if ($linjebg!=$bgcolor) {$linjebg=$bgcolor; $color='#000000';}
							else {$linjebg=$bgcolor5; $color='#000000';}
		#					if ($z>1 && $id[$z]+1 != $id[$z-1]) {
		#						$tmp=$id[$z]+1;
		#						$qtxt="select * from transaktioner where id='$tmp'";
		#						$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		#						print "<tr><td>$r[id]</td><td>$r[transdate]</td><td>$r[logdate]</td><td>$r[logtime]</td><td>$r[kladde_id]</td>";
		#						print "<td>$r[bilag]</td><td>$r[kontonr]</td><td>$r[faktura]</td><td>$r[debet]</td><td>$r[kredit]</td></tr>";
		#					}
							print "<tr bgcolor=\"$linjebg\">";
							print "<td align=\"right\"> $id[$z]</span><br></td>";
							print "<td align=\"right\">". dkdato($transdate[$z]) ."</td>";
							print "<td align=\"right\">". dkdato($logdate[$z]) ."</td>";
							print "<td align=\"right\">". substr($logtime[$z],0,5)."<br></td>";
							print "<td align=\"right\"> $kladde_id[$z]<br></td>";
							print "<td align=\"right\"> $bilag[$z]<br></td>";
							print "<td align=\"right\"><span title='$kontonavn[$z]'>$kontonr[$z]<br></span></td>";
							print "<td align=\"right\">$faktura[$z]<br></td>";
							if ($debet[$z]) print "<td align=\"right\"> ".dkdecimal($debet[$z],2)."<br></td>";
							else print "<td>&nbsp;</td>";
							if ($kredit[$z]) print "<td align=\"right\"> ".dkdecimal($kredit[$z],2)."<br></td>";
							else print "<td>&nbsp;</td>";
							if ($vis_projekt) {
								($row['projekt'] && $ret_projekt)?$title="".findtekst(1621, $sprog_id)."":$title=NULL;
								($ret_projekt)?$tmp="<a href=\"../includes/ret_transaktion.php?id=$row[id]&felt=projekt\">$row[projekt]</a>":$tmp=$row['projekt'];
								print "<td align=\"right\" title=\"$title\">$tmp<br></td>";
							}
							print "<td align=\"right\">$valuta[$z]<br></td>";
							print "<td align=\"right\">$kurs[$z]<br></td>";
							print "<td align=\"right\">$kasse[$z]<br></td>";
							print "<td colspan='2'> &nbsp; $transtxt[$z]<br></td>";
							print "</tr>\n";
							}
						}
						if ($csv) {
							fwrite($fp,"\"".$id[$z]."\";\"".$transdato."\";\"".$logdato."\";\"".substr($logtime[$z],0,5)."\";\"".$kladde_id[$z]."\";\"".$bilag[$x]."\";\"".$kontonr[$z]."\";\"".utf8_decode(stripslashes($kontonavn[$z]))."\";\"".$row['faktura']."\";\"".dkdecimal($debet[$z],2)."\";\"".dkdecimal($kredit[$z],2)."\";\"".$row['projekt']."\";\"".utf8_decode(stripslashes($transtxt[$z]))."\"\n");
						}
					}
				}
			}
	}
	if ($csv && $skriv){ fclose($fp);
		print "<tr></tr><td></td><tr><td colspan='12' align='center'><a href=\"../temp/$db/kontrolspor.csv\">".findtekst(1204,$sprog_id)."</a></td></tr>";
	}
	
	if (!isset ($debetsum)) $debetsum = 0;
	if (!isset ($kreditsum)) $kreditsum = 0;
	if (!isset ($y)) $y = 0;

		if (!$csv && ($debetsum || $kreditsum)) {
		($vis_projekt)?$colspan=14:$colspan=13;
		print "<tr><td colspan=\"$colspan\"><hr></td></tr>";
		print "<td colspan=8><b>".findtekst(1084,$sprog_id).":</b><br></td><td align=\"right\">".dkdecimal($debetsum,2)."<br></td><td align=\"right\">".dkdecimal($kreditsum,2)."<br></td><td><br></td></tr>";
	}

	return ($y);
} #endfunction udskriv()
print "
</tbody>
</table>
	</td></tr>
</tbody></table>
";

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>

