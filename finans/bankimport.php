<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/bankimport.php --- patch 4.0.8 --- 2023.10.30 ---
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
// Copyright (c) 2003-2023 saldi.dk aps
// ----------------------------------------------------------------------

// 20121110 Indsat mulighed for valutavalg ved import - søg: valuta
// 20130911 Fejl i 1. kald til "vis_data" "$vend" mangler.
// 20131119	Genkendelse af posteringer fra Quickpay. Søg 20131109
// 20140115 Genkendelse af FI indbetalinger fra Danske Bank. Søg Danske Bank
// 20140117 Genkendelse af FI indbetalinger fra Sparekasserne. Søg Sparekasserne
// 20140127 Genkendelse af FI indbetalinger fra Nordea. Søg Nordea
// 20140203 Genkendelse af danske månedforkortelser i datoer. Søg 20140203
// 20140708 Genkendelse af dankort betalinger 20140708
// 20141005 Indsat "auto_detect_line_endings", eller kan den ikke altid genkende filer genereret på MAC 
// 20150904 Genkendelse af dankort krediteringer 20150904
// 20160212 Genkendelse af kortbetalinger gennem SparNord. Søg SparNord
// 20160215 Ved Kortbetalinger fra SparNord registreres kortgebyrer på separat linje. Søg kortgebyr. 
// 20160815 Genkendelse af FI indbetalinger fra Danske Bank som starter med IK71. Søg Danske Bank
// 20160909 Tilføjet felt for gebyr kontonr samt ny version af SparNord. 20160909	
// 20161101 Genkendelse af kortgebyr for Danske Bank. 20161101	
// 20170111 Tilføjet afdeling. Søg $afd
// 20170119 Tilføjet $qtxt= ... Søg 20170119 
// 20170608 Tilføjet genkendelse af loppeafreningssbilag. 20170608
// 20170630 Flyttet $bilag++ fra over db_modify da der var huller og dubletter i bilagsnr.rækken. 20170630
// 20170816 Tilføjet genkendelse af UTF-8 i filindhold og fjerner ukendt tegn i starte og slut af linje . Søg $tegnsaet;
// 20170914 mb_detect_encoding fejlfortolker så jeg har skrevet min egen. 21070914
// 20180314 den udlignede de nyeste istedet for de ældste 20180318
// 20190911 PHR Added 'DK3DSF' at SparNord Ny 20190911
// 20190917 PHR Added 'DK3DSF' at DanskeBank Ny 20190917
// 20200820 PHR Added recognition of outgoing payments from Cultura Sparebank, Norway 20200820
// 20211107 PHR Added recognition of customer account # when importing bank 20201107
// 20210328 PHR Added recognition of payment ID from Jyske Bank 21210328
// 20210714 LOE Translated some texts 
// 20210916 PHR date recognition of yyyymmdd
// 20211022 PHR url decoding 'beskrivelse' and some improvements for Norway 
// 20220120	PHR Translation of weired charset from Sparebanken Vest,Norway
// 20220421	PHR recognition of payments from Sparebanken Vest,Norway
// 20220531	PHR Added kundenr as select option (Sparebanken Vest,Norway)
// 20220617 PHR Better recognition of date formats
// 20220531	PHR Added 'Modtager konto'
// 20230615	PHR php8
// 20230822 MSC - Copy pasted new design into code
// 20231030	PHR Added 'Saldo'
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding

ini_set("auto_detect_line_endings", true);

@session_start();
$s_id=session_id();
$css="../css/standard.css";
$bankName = NULL;

$title="Bankimport";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");

global $menu;

$vend    = NULL;
$feltnavn = array();
if(($_GET)||($_POST)) {

	if ($_GET) {
		$kladde_id=$_GET['kladde_id'];
		$bilag=$_GET['bilagsnr'];
	}
	else {
		$import      = if_isset($_POST['import'],NULL);
		$show        = if_isset($_POST['show'],NULL);
		$kladde_id   = if_isset($_POST['kladde_id'],0);
		$filnavn     = if_isset($_POST['filnavn'],NULL);
		$splitter    = if_isset($_POST['splitter'],NULL);
		$feltnavn    = if_isset($_POST['feltnavn'],NULL);
		$feltantal   = if_isset($_POST['feltantal'],0);
		$kontonr     = if_isset($_POST['kontonr'],0);
		$gebyrkonto  = (int)if_isset($_POST['gebyrkonto'],NULL);
		$valuta      = if_isset($_POST['valuta'],NULL);
		$valuta_kode = if_isset($_POST['valuta_kode'],NULL);
		$bilag       = if_isset($_POST['bilag'],NULL);
		$afd         = if_isset($_POST['afd'],NULL);
		$vend        = if_isset($_POST['vend'],NULL);
		if ($vend) $vend='checked';
	}

	if ($menu=='T') {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">"; 
		print "<div class=\"headerbtnLft headLink\"><a href=importer.php?kladde_id=$kladde_id accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
		print "<div class=\"headerTxt\">$title</div>";     
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
		print "</div>";
		print "<div class='content-noside'><center>";
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
		print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		print "<td width=\"10%\" $top_bund><a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>".findtekst(30, $sprog_id)."</a></td>";
		print "<td width=\"80%\" $top_bund>".findtekst(1391, $sprog_id)." (".findtekst(1072, $sprog_id)." $kladde_id)</td>";
		print "<td width=\"10%\" $top_bund ><br></td>";
		print "</tbody></table>";
		print "</td></tr>";
	}

	if (($kontonr) && (strlen($kontonr)==1)) {
		$kontonr=strtoupper($kontonr);
		$query = db_select("select * from kontoplan where genvej='$kontonr' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $kontonr=$row['kontonr'];
		else {
			alert("Genvejen $kontonr findes ikke i kontoplanen");
			$kontonr='';
		}
	}
	elseif ($kontonr)	 {
		$tmp=(int)$kontonr;
		if (!$row=db_fetch_array(db_select("select id from kontoplan where kontonr=$tmp",__FILE__ . " linje " . __LINE__))) {
			alert("Kontonummer $kontonr findes ikke i kontoplanen");
			$show='Vis';
			$import=NULL;
		}
	}
	if (basename($_FILES['uploadedfile']['name'])) {
//echo __line__." ".basename($_FILES['uploadedfile']['name'])."<br>";
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
		
			if ($r=db_fetch_array(db_select("select * from grupper where ART = 'KASKL' and kode='3' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__))) {
				$kontonr=if_isset($r['box1']);
				$feltantal=if_isset($r['box2']);
				$feltnavn[0]=if_isset($r['box3']);
				$feltnavn[1]=if_isset($r['box4']);
				$feltnavn[2]=if_isset($r['box5']);
				$feltnavn[3]=if_isset($r['box6']);
				$feltnavn[4]=if_isset($r['box7']);
				$feltnavn[5]=if_isset($r['box8']);
				$feltnavn[6]=if_isset($r['box9']);
				$feltnavn[7]=if_isset($r['box10']);
				$gebyrkonto=if_isset($r['box11'],0);
			} else {
				db_modify ("insert into grupper (beskrivelse,art,kode,kodenr) values ('Bankimport','KASKL','3','$bruger_id')",__FILE__ . " linje " . __LINE__);
			}
			if (!$feltantal) $feltantal=1;	
			vis_data($kladde_id,$filnavn,'',$feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$vend,$valuta_kode,$afd);
		}	else {
			echo findtekst(1370, $sprog_id);
		}
	}
	elseif($show){
		vis_data($kladde_id,$filnavn,$splitter,$feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$vend,$valuta_kode,$afd);
	}
	elseif($import){
		if (($kladde_id)&&($filnavn)&&($splitter)&&($kontonr))	{
			flyt_data($kladde_id,$filnavn,$splitter,$feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$valuta_kode,$afd[0]);
		} else vis_data($kladde_id, $filnavn, $splitter, $feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$vend,$valuta_kode,$afd);
	}
	else {
		upload($kladde_id, $bilag);
	}
}
print "</tbody></table>";

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

################################################################################################################
function upload($kladde_id, $bilag){
global $charset;
global $sprog_id;
global $menu;

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"bankimport.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center> ".findtekst(1364, $sprog_id).": <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"".findtekst(1078, $sprog_id)."\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "</tbody></table>";
print "</td></tr>";
}

function vis_data($kladde_id,$filnavn,$splitter,$feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$vend,$valuta_kode,$afd){
global $bruger_id,$charset,$sprog_id;

$bankName = '';
$komma = $punktum = $semikolon = $tabulator = $x = 0;
$valuta = array();
$q=db_select("select kodenr,box1 from grupper where art='VK' order by box1",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if (trim($r['box1'])) {
		$x++;
		$valutakode[$x]=$r['kodenr'];
		$valuta[$x]=$r['box1'];
		}
}

$x=0;
$afd_nr=array();
$q=db_select("select kodenr,box1 from grupper where art='AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$afd_nr[$x]=$r['kodenr'];
	$afd_navn[$x]=$r['box1'];
	$x++;
}

$fp=fopen("$filnavn","r");
$tegnsaet="iso";
if ($fp) {
	$z=0;
	while ($linje=fgets($fp)){
#	exit;
		if ($z == 0 && substr($linje,0,61) == "Planlagt;Type;Fil;Fra konto;Kontonavn;Til konto;Mottakernavn;") {
			$bankName = 'Sparebanken Vest';
		}  
		if ($z<=10) {
			$tmp=$linje;
			while ($tmp=substr(strstr($tmp,";"),1)) $semikolon++;	
			$tmp=$linje;
			while ($tmp=substr(strstr($tmp,","),1)) $komma++;
			$tmp=$linje;
			while ($tmp=substr(strstr($tmp,chr(9)),1)) $tabulator++;
			$tmp='';
		}
		$z++;
		if ($tegnsaet=='iso') { #20170914
			if (strpos($linje,'ø') || strpos($linje,'Ø')) $tegnsaet='UTF-8';
		}	
	}
	fclose($fp);
	if (($komma>$semikolon)&& ($komma>$tabulator)) {$tmp='Komma'; $feltantal=$komma;}
	elseif (($semikolon>$tabulator)&&($semikolon>$komma)) {$tmp='Semikolon'; $feltantal=$semikolon;}			
	elseif (($tabulator>$semikolon)&&($tabulator>$komma)) {$tmp='Tabulator'; $feltantal=$tabulator;}			
		if (!$splitter) {$splitter=$tmp;}
	if ($splitter=='Komma') $feltantal=$komma;
	elseif ($splitter=='Semikolon') $feltantal=$semikolon;
	elseif ($splitter=='Tabulator') $feltantal=$tabulator;
	$cols=$feltantal+1;
}


$fp=fopen("$filnavn","r");
if ($fp) {
	if ($splitter=='Komma') $splittegn=",";
	elseif ($splitter=='Semikolon') $splittegn=";";
	elseif ($splitter=='Tabulator') $splittegn=chr(9);
	
	$y=0;
	$feltantal=0;
#	for ($y=1; $y<20; $y++) {
	while ($linje=fgets($fp)) {
		if ($linje) {
			$y++;
			$ny_linje[$y]='';
			if ($tegnsaet=='UTF-8') $linje=mb_convert_encoding($linje, 'ISO-8859-1', 'UTF-8');
			$linje=trim($linje);
			$linje=trim($linje,"?");
			if ($charset=='UTF-8') $linje=mb_convert_encoding($linje, 'UTF-8', 'ISO-8859-1');
			$anftegn=0;
				$felt=array();
				$z=0;
				for ($x=0; $x<strlen($linje);$x++) {
				if ($x==0 && substr($linje,$x,1)=='"') {
					$z++; $anftegn=1; $felt[$z]='';
				} elseif ($x==0) {
					$z++; $felt[$z]=substr($linje,$x,1);
				} elseif (substr($linje,$x,1)=='"' && substr($linje,$x-1,1)==$splittegn && !$anftegn) {
					$z++; $anftegn=1; $felt[$z]='';
				} elseif (substr($linje,$x,1)=='"' && (substr($linje,$x+1,1)==$splittegn || $x==strlen($linje)-1)) {
					$anftegn=0;
					if (substr($linje,$x+2,1)=='"') $x++;
#					if ($x==strlen($linje)) $z--;
				}	elseif (!$anftegn && substr($linje,$x,1)==$splittegn) {
					$z++; $felt[$z]='';
					if (substr($linje,$x+1,1)=='"') $x++;
				} else {
					$felt[$z]=$felt[$z].substr($linje,$x,1);
				} 
			}
			if ($z>$feltantal) $feltantal=$z-1;
			if (!isset($felt[$x])) $felt[$x] = NULL;
			for ($x=1; $x<=$z; $x++) {
				$ny_linje[$y]=$ny_linje[$y].$felt[$x].chr(9);
			}
			$x++;
			if (!isset($felt[$x])) $felt[$x] = NULL;
			$ny_linje[$y]=$ny_linje[$y].$felt[$x]."\n";
		}
	}
}  
$linjeantal=$y;
#$cols=$feltantal;
fclose ($fp);
$fp=fopen($filnavn."2","w");
if ($vend) {
 for ($y=$linjeantal;$y>=1;$y--) fwrite($fp,$ny_linje[$y]);
} else { 
	for ($y=1;$y<=$linjeantal;$y++) fwrite($fp,$ny_linje[$y]);
}
fclose ($fp);
print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"bankimport.php\" method=\"POST\">";
#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
print "<tr><td colspan=$cols align=center>";
print "<span title='".findtekst(1392, $sprog_id)."'>\n";
print "Vend <input type=\"checkbox\" name=\"vend\" $vend>";
print "</span>";
print "<span title='".findtekst(1389, $sprog_id)."'>".findtekst(1377, $sprog_id)."<select name=splitter>\n";
if ($splitter) {print "<option>$splitter</option>\n";}
if ($splitter!='Semikolon') print "<option>".findtekst(1378, $sprog_id)."</option>\n";
if ($splitter!='Komma') print "<option>".findtekst(1379, $sprog_id)."</option>\n";
if ($splitter!='Tabulator') print "<option>Tabulator</option>\n";
print "</select></span>";
if (count($afd_nr)) {
	print "<span title='".findtekst(1393, $sprog_id)."'> ".findtekst(658, $sprog_id)."<select name='afd'>\n";
	if (!$afd) print "<option value='0'></option>\n";
	for ($x=0;$x<count($afd_nr);$x++) {
		if ($afd_nr[$x]==$afd) print "<option value='$afd_nr[$x]'>$afd_nr[$x]</option>\n";
	}
	for ($x=0;$x<count($afd_nr);$x++) {
		if ($afd_nr[$x]!=$afd) print "<option value='$afd_nr[$x]'>$afd_nr[$x]</option>\n";
	}
	if ($afd) print "<option value='0'></option>\n";
	print "</select>";
}	
if ($v_ant=count($valuta)) {
	$valuta_kode*=1;
	print "<span title='".findtekst(1394, $sprog_id)."'> ".findtekst(1069, $sprog_id)."<select name='valuta_kode'>\n";
	for ($x=1;$x<=$v_ant;$x++) {
		if ($valutakode[$x]==$valuta_kode) print "<option value='$valutakode[$x]'>$valuta[$x]</option>\n";
	}
	print "<option value='0'>DKK</option>\n";
	for ($x=1;$x<=$v_ant;$x++) {
		if ($valutakode[$x]!=$valuta_kode) print "<option value='$valutakode[$x]'>$valuta[$x]</option>\n";
	}
	print "</select></span>";
}
print "&nbsp;<span title='".findtekst(1395, $sprog_id)."'>".findtekst(1396, $sprog_id)."<input type=text size=8 name=kontonr value=$kontonr></span>&nbsp;";
print "&nbsp;<span title='".findtekst(1398, $sprog_id)."'>".findtekst(1397, $sprog_id)."<input type=text size=8 name=gebyrkonto value=$gebyrkonto></span>&nbsp;";
print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
print "&nbsp; <input type=\"submit\" name=\"show\" value=\"".findtekst(1133, $sprog_id)."\" />";
$alertj=findtekst(1399, $sprog_id); $alertk=findtekst(1400, $sprog_id); $alertl=findtekst(1401, $sprog_id);
$alertm=findtekst(1402, $sprog_id); $alertn=findtekst(1403, $sprog_id);
$import = findtekst(1356, $sprog_id); 
if (!in_array("dato",$feltnavn)) alert("$alertj");
elseif (!in_array("beskrivelse",$feltnavn)) alert("$alertk");
elseif (!in_array("belob",$feltnavn)) alert("$alertl");
elseif (!$splitter) alert("$alertm");
elseif (!$kontonr)  alert("$alertn");

elseif (($kladde_id)&&($filnavn)&&($splitter)&&($kontonr)) print "&nbsp; <input type=\"submit\" name=\"import\" value=\"$import\" /></td></tr>";
print "<tr><td colspan=$cols><hr></td></tr>\n";
#if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
#elseif ($splitter=='Komma') {$splitter=',';}
#elseif ($splitter=='Tabulator') {$splitter=chr(9);}
$splitter=chr(9);
print "<tr><td><span title='".findtekst(1404, $sprog_id)."'><input type=text size=4 name=bilag value=$bilag></span></td>";
$belob = $beskr = $dato = $kundenr = 0;
for ($y=0; $y<=$feltantal; $y++) {
	if ($feltnavn[$y]=='dato' && $dato==1) {
		$aalert = findtekst(1405, $sprog_id);
		alert("$aalert");
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='beskrivelse') && ($beskr==1)) {
		$abalert = findtekst(1406, $sprog_id);
		alert("$abalert");
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='belob')&&($belob==1)) {
		$acalert = findtekst(1407, $sprog_id);
		alert("$acalert");
		$feltnavn[$y]='';
	}
	if ( $feltnavn[$y]=='kundenr' && $kundenr==1 ) {
		$adalert = findtekst(2053, $sprog_id);
		alert("$adalert");
		$feltnavn[$y]='';
	}
	if ($feltnavn[$y]=='belob' || $feltnavn[$y]=='saldo') print "<td align=right><select name=feltnavn[$y]>\n";
	elseif ($feltnavn[$y]) print "<td><select name=feltnavn[$y]>\n";
	else  print "<td align=center><select name=feltnavn[$y]>\n";
	print "<option>$feltnavn[$y]</option>\n";
	if ($feltnavn[$y]) print "<option></option>\n";
	if ($feltnavn[$y]!='dato') print "<option value=\"dato\">".findtekst(635, $sprog_id)."</option>\n";
	else $dato=1;
	if ($feltnavn[$y]!='beskrivelse') print "<option value=\"beskrivelse\">".findtekst(914, $sprog_id)."</option>\n";
	else $beskr=1;
	if ($feltnavn[$y]!='belob') print "<option value=\"belob\">".findtekst(934, $sprog_id)."</option>\n";
	else $belob=1;
	if ($feltnavn[$y]!='saldo') print "<option value=\"saldo\">Saldo</option>\n";
	else $saldo=1;
	if ($feltnavn[$y]!='kundenr') print "<option value=\"kundenr\">".findtekst(357, $sprog_id)."</option>\n";
	else $kundenr=1;
	if ($feltnavn[$y]!='recieverAccount') print "<option value=\"recieverAccount\">recieverAccount</option>\n";
	else $recieverAccount=1;
	print "</select>";
}
print "</form>";
$fp=fopen($filnavn."2","r");
if ($fp) {
	$x=0;
	while($linje=fgets($fp)) {
#	while (!feof($fp)) {
		$skriv_linje=0;
		if ($linje=trim($linje)) {
			$x++;
			$skriv_linje=1;
			$felt=array();
			$felt = explode($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$felt[$y]=trim($felt[$y]);
				$felt[$y]=trim($felt[$y],'"');
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='dato') { # 20140203
					if (is_numeric($felt[$y]) && strlen($felt[$y])=='8') { #20210916
						$thisYear=date('Y');
						if (substr($felt[$y],0,4) >= $thisYear-2 && 
						substr($felt[$y],0,4) <= $thisYear+2 &&
						substr($felt[$y],4,2) <= 12) {
							$year=substr($felt[$y],0,4);
							$month=substr($felt[$y],4,2);
							$day=substr($felt[$y],6,2);
							$felt[$y] = $day ."-". $month."-". $year; 
						}
					}	 
					$felt[$y]=str_replace("-jan-","-01-",$felt[$y]);
					$felt[$y]=str_replace("-feb-","-02-",$felt[$y]);
					$felt[$y]=str_replace("-mar-","-03-",$felt[$y]);
					$felt[$y]=str_replace("-apr-","-04-",$felt[$y]);
					$felt[$y]=str_replace("-maj-","-05-",$felt[$y]);
					$felt[$y]=str_replace("-jun-","-06-",$felt[$y]);
					$felt[$y]=str_replace("-jul-","-07-",$felt[$y]);
					$felt[$y]=str_replace("-aug-","-08-",$felt[$y]);
					$felt[$y]=str_replace("-sep-","-09-",$felt[$y]);
					$felt[$y]=str_replace("-okt-","-10-",$felt[$y]);
					$felt[$y]=str_replace("-nov-","-11-",$felt[$y]);
					$felt[$y]=str_replace("-dec-","-12-",$felt[$y]);
					$felt[$y]=str_replace(".","-",$felt[$y]);
				}
				if ($feltnavn[$y]=='belob') {
					if ($bankName == 'Sparebanken Vest' && $felt[1] == 'Betaling') $felt[$y] = '-'.$felt[$y]; 
					$felt[$y]=str_replace(chr(194).chr(160),"",$felt[$y]); // 20220120 Weired dot?
					$felt[$y]=str_replace(" ","",$felt[$y]);
					if (nummertjek($felt[$y])=='US') {
						if ($felt[$y]==0) $skriv_linje=0;
						else $felt[$y]=dkdecimal($felt[$y]);
					} elseif (nummertjek($felt[$y])=='DK') {
						if (usdecimal($felt[$y])==0) $skriv_linje=0;
					}	else {
						$skriv_linje=0;
					}
				}
				if ($feltnavn[$y]=='beskrivelse') { // 20220120
					$felt[$y]=str_replace("%c3%a6","æ",$felt[$y]);
					$felt[$y]=str_replace("%c3%b8","ø",$felt[$y]);
					$felt[$y]=str_replace("%c3%a5","å",$felt[$y]);
					$felt[$y]=str_replace("%c3%86","Æ",$felt[$y]);
					$felt[$y]=str_replace("%c3%98","Ø",$felt[$y]);
					$felt[$y]=str_replace("%c3%85","Å",$felt[$y]);
					$felt[$y]=str_replace("%C3%A6","æ",$felt[$y]);
					$felt[$y]=str_replace("%C3%B8","ø",$felt[$y]);
					$felt[$y]=str_replace("%C3%A5","å",$felt[$y]);
					$felt[$y]=str_replace("%C3%86","Æ",$felt[$y]);
					$felt[$y]=str_replace("%C3%98","Ø",$felt[$y]);
					$felt[$y]=str_replace("%C3%85","Å",$felt[$y]);
				}
			}
			if ($skriv_linje==1){
				print "<tr><td>$bilag</td>";
				for ($y=0; $y<=$feltantal; $y++) {
					if ($feltnavn[$y]=='belob' || $feltnavn[$y]=='saldo') {
						print "<td align=right>$felt[$y]&nbsp;</td>";
					}
					elseif ($feltnavn[$y]) {print "<td>$felt[$y]&nbsp;</td>";}
				else print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
				}
				print "</tr>";
				$bilag++;
			} else {
				print "<tr><td><span style=\"color: rgb(153, 153, 153);\">-</span></td>";
				for ($y=0; $y<=$feltantal; $y++) {
					if ($feltnavn[$y]=='belob' || $feltnavn[$y]=='saldo') {
						print "<td align=right><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
					} elseif ($feltnavn[$y]) print "<td><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
					else print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
				}
				print "</tr>";
			}
		}
	}
}

fclose($fp);
print "</tbody></table>";
print "</td></tr>";
db_modify("update grupper set box1='$kontonr', box2='$feltantal',box11='$gebyrkonto' where ART='KASKL' and kode='3' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);
for ($y=0; $y<=$feltantal; $y++) {
	$box=$y+3;
	if ($box<=10) {
		$box="box$box";
		db_modify("update grupper set $box='$feltnavn[$y]' where ART='KASKL' and kode='3' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);
	}
}
} # endfunc # vis_data

function flyt_data($kladde_id,$filnavn,$splitter,$feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$valuta_kode,$afd) {
	global $charset;

	$afd*=1;
	$valuta_kode*=1;
	
	transaktion('begin');
	$splitter=chr(9);
	$fp=fopen($filnavn."2","r");
	if ($fp) {
		$x=0;
		while (!feof($fp)) {
			$kortgebyr=0;
			$skriv_linje=0;
			if ($linje=trim(fgets($fp))) {
				if ($x == 0 && strpos($linje,"\tType\tFil\tFra konto\tKontonavn\tTil konto\tMottakernavn")) {
					$bankName = 'Sparebanken Vest';
				}  
				$x++;
				$skriv_linje=1;
				$felt=array();
				$felt = explode($splitter, $linje);
				for ($y=0; $y<=$feltantal; $y++) {
					$felt[$y]=trim($felt[$y]);
					if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
					if ($feltnavn[$y]=='dato') { # 20140203 + 20220617
						$felt[$y] = str_replace('-','',$felt[$y]);
						$felt[$y] = str_replace('/','',$felt[$y]);
						$felt[$y] = str_replace('.','',$felt[$y]);
						if (is_numeric($felt[$y]) && strlen($felt[$y])=='8') { #20210916
							$thisYear=date('Y');
							if (substr($felt[$y],0,4) >= $thisYear-2 && 
							substr($felt[$y],0,4) <= $thisYear+2 &&
							substr($felt[$y],4,2) <= 12) {
								$year=substr($felt[$y],0,4);
								$month=substr($felt[$y],4,2);
								$day=substr($felt[$y],6,2);
								$felt[$y] = $day ."-". $month."-". $year; 
							}
						}	 
						$felt[$y]=str_replace("-jan-","-01-",$felt[$y]);
						$felt[$y]=str_replace("-feb-","-02-",$felt[$y]);
						$felt[$y]=str_replace("-mar-","-03-",$felt[$y]);
						$felt[$y]=str_replace("-apr-","-04-",$felt[$y]);
						$felt[$y]=str_replace("-maj-","-05-",$felt[$y]);
						$felt[$y]=str_replace("-jun-","-06-",$felt[$y]);
						$felt[$y]=str_replace("-jul-","-07-",$felt[$y]);
						$felt[$y]=str_replace("-aug-","-08-",$felt[$y]);
						$felt[$y]=str_replace("-sep-","-09-",$felt[$y]);
						$felt[$y]=str_replace("-okt-","-10-",$felt[$y]);
						$felt[$y]=str_replace("-nov-","-11-",$felt[$y]);
						$felt[$y]=str_replace("-dec-","-12-",$felt[$y]);
						$felt[$y]=str_replace(".","-",$felt[$y]);
					}
					if ($feltnavn[$y]=='belob') {
						if ($bankName == 'Sparebanken Vest' && $felt[1] == 'Betaling') $felt[$y] = '-'.$felt[$y]; 
						$felt[$y]=str_replace(chr(194).chr(160),"",$felt[$y]); // 20220120 Weired dot?
						$felt[$y]=str_replace(" ","",$felt[$y]);
						if (nummertjek($felt[$y])=='US') $felt[$y]=dkdecimal($felt[$y]);
						elseif (nummertjek($felt[$y])!='DK') $skriv_linje=0;		
					}
					if ($feltnavn[$y]=='beskrivelse') { // 20220120
						$felt[$y]=str_replace("%c3%a6","æ",$felt[$y]);
						$felt[$y]=str_replace("%c3%b8","ø",$felt[$y]);
						$felt[$y]=str_replace("%c3%a5","å",$felt[$y]);
						$felt[$y]=str_replace("%c3%86","Æ",$felt[$y]);
						$felt[$y]=str_replace("%c3%98","Ø",$felt[$y]);
						$felt[$y]=str_replace("%c3%85","Å",$felt[$y]);
						$felt[$y]=str_replace("%C3%A6","æ",$felt[$y]);
						$felt[$y]=str_replace("%C3%B8","ø",$felt[$y]);
						$felt[$y]=str_replace("%C3%A5","å",$felt[$y]);
						$felt[$y]=str_replace("%C3%86","Æ",$felt[$y]);
						$felt[$y]=str_replace("%C3%98","Ø",$felt[$y]);
						$felt[$y]=str_replace("%C3%85","Å",$felt[$y]);
						$felt[$y]=str_replace("+"," ",$felt[$y]);
					}
					}
 			}		
 			if ($skriv_linje==1){
				for ($y=0; $y<=$feltantal; $y++) {
					$bilag=(int)$bilag;
					if ($feltnavn[$y]=='belob') $amount=usdecimal($felt[$y]);
					elseif ($feltnavn[$y]=="dato") $transdate=usdate($felt[$y]);
					elseif ($feltnavn[$y]=="beskrivelse") $beskrivelse=db_escape_string($felt[$y]);
					elseif ($feltnavn[$y]=="recieverAccount") $recieverAccount=db_escape_string($felt[$y]);
					elseif ($feltnavn[$y]=="kundenr") $kundenr=db_escape_string($felt[$y]);
					elseif ($feltnavn[$y]=='saldo') $saldo = usdecimal($felt[$y]);

				}
				$qtxt=NULL;
				$saldo = (float)$saldo;
				if ($amount>0) {
					if (strlen($beskrivelse)==22 && substr($beskrivelse,0,3)=='IK ' && is_numeric(substr($beskrivelse,3,19))) { # ?
						$kredit=(int)substr($beskrivelse,3,13);
						$faktura=(int)substr($beskrivelse,16,5);
						$k_type='D';
					} elseif (strlen($beskrivelse)==20 && substr($beskrivelse,-4)=='IK71' && is_numeric(substr($beskrivelse,0,14))) { # Sparekasserne
						$kredit=(int)substr($beskrivelse,0,8);
						$faktura=(int)substr($beskrivelse,8,6);
						$k_type='D';
					} elseif (strlen($beskrivelse)==24 && substr($beskrivelse,0,4)=='IK71' && is_numeric(substr($beskrivelse,5))) { # Danske Bank 20160815
						$kredit=(int)substr($beskrivelse,5,13);
						$faktura=(int)substr($beskrivelse,18,5);
						$k_type='D';
						if ($kredit && $faktura) $qtxt="select * from ordrer where fakturanr = '$faktura' and kontonr = '$kredit'"; # 20170119
					} elseif (strlen($beskrivelse)==32 && substr($beskrivelse,7,10)=='Indbet.ID=' && is_numeric(substr($beskrivelse,17,15))) { # Danske Bank
						$kredit=(int)substr($beskrivelse,17,9);
						$faktura=(int)substr($beskrivelse,26,5);
						$k_type='D';
					} elseif (strlen($beskrivelse)==35 && substr($beskrivelse,0,21)=='Indbetalingskort, nr.' && is_numeric(substr($beskrivelse,22,14))) { # Nordea
						$kredit=(int)substr($beskrivelse,22,7);
						$faktura=(int)substr($beskrivelse,29,5);
						$k_type='D';
					} elseif (strlen($beskrivelse)==40 && substr($beskrivelse,0,6)=='DK-IND' && is_numeric(substr($beskrivelse,22,14))) { # SparNord
						$felt_1="%".substr($beskrivelse,-10);
						$tmp=$amount-3;
						$qtxt= "select fakturanr,kontonr from ordrer where felt_1 LIKE '$felt_1' and sum <= '$amount' and sum >= '$tmp'";
					} elseif (strlen($beskrivelse)==30 && substr($beskrivelse,0,2)=='DK' && is_numeric(substr($beskrivelse,4,7)) && is_numeric(substr($beskrivelse,12,9)) && is_numeric(substr($beskrivelse,25,5))) { # Dankort betaling 20140708 
						$betalings_id="%".substr($beskrivelse,12,9);
						$qtxt="select fakturanr,kontonr,sum,moms from ordrer where betalings_id LIKE '$betalings_id' and sum = '$amount'";
					} elseif (strlen($beskrivelse)==30 && (substr($beskrivelse,0,5)=='DKSSL' || substr($beskrivelse,0,6)=='DK3DSF') && is_numeric(substr($beskrivelse,6,4)) && is_numeric(substr($beskrivelse,11,7)) && is_numeric(substr($beskrivelse,19,11))) { # SparNord Ny 20160909 
						$tmp=$amount-3;
						$betalings_id="%".substr($beskrivelse,21,11);
						$qtxt = "select fakturanr,kontonr,sum,moms from ordrer where betalings_id LIKE '$betalings_id' ";
						$qtxt.= "and sum+moms >= '$tmp' and sum+moms <='$amount'";
					} elseif (substr($beskrivelse,12,3)=='ha_' && is_numeric(substr($beskrivelse,15,5))) { # BetalingsID Jyske Bank 21210328
						$tmp=$amount-3;
						$betalings_id=substr($beskrivelse,12,8);
						$qtxt = "select fakturanr,kontonr,sum,moms from ordrer where betalings_id = '$betalings_id' ";
						$qtxt.= "and sum+moms >= '$tmp' and sum+moms <='$amount'";
					} elseif (strlen($beskrivelse)==22 && (substr($beskrivelse,0,5)=='DKSSL' || substr($beskrivelse,0,6)=='DK3DSF') && is_numeric(substr($beskrivelse,6,4)) && is_numeric(substr($beskrivelse,11,7)) && is_numeric(substr($beskrivelse,19,11))) { # DanskeBank Ny 20161101 
						$tmp=$amount-3;
						$ordrenr=substr($beskrivelse,-4);
						$qtxt="select fakturanr,kontonr,sum,moms from ordrer where ordrenr = '$ordrenr' and sum >= '$tmp' and sum <='$amount' and art='DO'";
					} elseif (substr($beskrivelse,0,6)=='DKSSL ') { #20131119
						list($a,$b,$c)=explode(" ",$beskrivelse);
						$ordrenr=(int)substr($c,7);
						if ($ordrenr) $qtxt="select fakturanr,kontonr from ordrer where ordrenr = '$ordrenr' and sum = '$amount'";
					}
					if ($qtxt) {
						 $qtxt.=" and (betalt = '' or betalt is NULL) order by fakturadate limit 1"; #20180314
						if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							$faktura=$r['fakturanr'];
							$fakturasum=$r['sum']+$r['moms'];
							$kortgebyr=$amount-$fakturasum;
							$kredit=(int)$r['kontonr'];
							$k_type='D';
						} else {
							$faktura='';
							$kortgebyr='0';
							$fakturasum=$amount-$kortgebyr;
							$kredit='0';
							$k_type='';
						}
					} elseif ($kundenr) { #20220531
						$k_type='D';
						$kredit=$kundenr;
					} elseif ($recieverAccount) { #20220531
						$qtxt = "select kontonr from adresser where bank_konto = '$recieverAccount'";
						if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							$k_type='D';
							$kredit=$r['kontonr'];
							$amount*=-1;
						}
					} else { #20220120 Finds debitor paid by paylist.
						$kredit='0';
						$faktura='';
						$k_type='';
					}
					$kortgebyr = afrund($kortgebyr,2);
					if ($kortgebyr) {
						$qtxt = "insert into kassekladde ";
						$qtxt.= "(bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,valuta,afd,saldo)";
						$qtxt.= "  values ";
						$qtxt.= "('$bilag','$transdate','$beskrivelse','F','$kontonr','F','0','$faktura','$amount',";
						$qtxt.= "'$kladde_id','$valuta_kode','$afd','$saldo')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt = "insert into kassekladde ";
						$qtxt.= "(bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,valuta,afd,saldo)";
						$qtxt.= "  values ";
						$qtxt.= "('$bilag','$transdate','$beskrivelse','F','0','$k_type','$kredit','$faktura','$fakturasum',";
						$qtxt.= "'$kladde_id','$valuta_kode','$afd','$saldo')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt= "insert into kassekladde ";
						$qtxt.= "(bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,valuta,afd,saldo)";
						$qtxt.= "  values ";
						$qtxt.= "('$bilag','$transdate','$beskrivelse','F','0','F','$gebyrkonto','$faktura','$kortgebyr','$kladde_id',";
						$qtxt.= "'$valuta_kode','$afd','$saldo')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					} else {
						$qtxt = " insert into kassekladde ";
						$qtxt.= "(bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,valuta,afd,saldo)";
						$qtxt.= " values ";
						$qtxt.= "('$bilag','$transdate','$beskrivelse','F','$kontonr','$k_type','$kredit','$faktura','$amount','$kladde_id',";
						$qtxt.= "'$valuta_kode','$afd','$saldo')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
					$bilag++;
				} elseif ($amount < 0) {
						$dtype=$ktype='F';
						$debet=0;
						$amount=(float)$amount;
					if (strlen($beskrivelse)==30 && substr($beskrivelse,0,2)=='DK' && is_numeric(substr($beskrivelse,4,7)) && is_numeric(substr($beskrivelse,12,9)) && is_numeric(substr($beskrivelse,25,5))) { # Dankort betaling 20150904 
						$betalings_id="%".substr($beskrivelse,12,9);
						$r=db_fetch_array(db_select("select fakturanr,kontonr from ordrer where betalings_id LIKE '$betalings_id' and sum = '$amount'",__FILE__ . " linje " . __LINE__));
						$faktura=$r['fakturanr'];
						$debet=(int)$r['kontonr'];
						$d_type='D';
					} elseif (substr($beskrivelse,0,4)=='Afr:' && is_numeric(substr($beskrivelse,5,4))) { #20201107
						$d_type='D';
						$debet=substr($beskrivelse,5,4);
					} elseif (substr($beskrivelse,0,4)=='Afr:' && substr($beskrivelse,-8,1)=='-' && substr($beskrivelse,-5,1)=='-') { #20170608
						list($tmp,$debet,$tmp)=explode(" ",$beskrivelse);
						$d_type='D';
					} elseif (substr($beskrivelse,0,12)=='Girobetaling' && is_numeric(substr($beskrivelse,13,11))) { #20200820
						$bank=substr($beskrivelse,13,11);
						$d_type='D';
						$qtxt="select kontonr from adresser where bank_konto = '$bank'";
						if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							$debet=$r['kontonr'];
						}
					} else { #20211022 | if (strpos($beskrivelse,'+')) { 
						$beskrivelse=urldecode($beskrivelse);
#						$beskrivelse=str_replace('%C3%A6','æ',$beskrivelse);
#						$beskrivelse=str_replace('%C3%B8','ø',$beskrivelse);
#						$beskrivelse=str_replace('%C3%A5','å',$beskrivelse);
#						$beskrivelse=str_replace('+',' ',$beskrivelse);
						if (strlen($beskrivelse) < 25) $qtxt = "select kontonr from adresser where firmanavn='$beskrivelse' ";
						else $qtxt = "select kontonr from adresser where firmanavn like '$beskrivelse%' ";
						$qtxt.= "and art = 'D' and bank_konto != ''";
						$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
						$c=0;
						while ($r=db_fetch_array($q)) {
							$debet=$r['kontonr'];
							$c++;
						}
						($c == 1)?$d_type='D':$debet = 0;	
					} if ($kundenr) { #20220531
						$d_type='D';
						$debet=$kundenr;
					}
					$qtxt = "insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,valuta,afd";
					if ($saldo) $qtxt.=',saldo';
					$qtxt.= " ) values ";
					$qtxt.= "('$bilag','$transdate','$beskrivelse','$d_type','$debet','$ktype',";
					$qtxt.= "'$kontonr','".abs($amount)."','$kladde_id','$valuta_kode','$afd'";
					if ($saldo) $qtxt.=",'$saldo'";
					$qtxt.= ")";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$bilag++; #20170630 Flyttet fra over db_mod...
				}
			}
		}
	}
#xit;
	fclose($fp);
	unlink($filnavn); # sletter filen.
	unlink($filnavn."2"); # sletter filen.
	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
}
function nummertjek ($nummer){
	$nummer=(float)$nummer;
	$komma = $punktum = 0;
	$retur=1;
	$nummerliste=array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0", ",", ".", "-");
	for ($x=0; $x<strlen($nummer); $x++) {
		if (!in_array(substr($nummer,$x,1), $nummerliste)) {
			$retur=0;
		}
	}
	if ($retur) {
		for ($x=0; $x<strlen($nummer); $x++) {
			if (substr($nummer,$x,1)==',') $komma++;
			elseif (substr($nummer,$x,1)=='.') $punktum++;
		}
		if ((!$komma)&&(!$punktum)) $retur='US';
		elseif (($komma==1)&&(substr($nummer,-3,1)==',')) $retur='DK';
		elseif (($punktum==1)&&(substr($nummer,-3,1)=='.')) $retur='US';
		elseif (($komma==1)&&(!$punktum)) $retur='DK';
		elseif (($punktum==1)&&(!$komma)) $retur='US';	
	}
	return $retur;
}
	
