<?php
// ----------finans/bankimport.php------------patch 3.6.4------2016.02.12-----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2016 DANOSOFT ApS
// ----------------------------------------------------------------------

// 2012.11.10 Indsat mulighed for valutavalg ved import - søg: valuta
// 2013.09.11 Fejl i 1. kald til "vis_data" "$vend" mangler.
// 2013.11.19	Genkendelse af posteringer fra Quickpay. Søg 20131109
// 2014.01.15 Genkendelse af FI indbetalinger fra Danske Bank. Søg Danske Bank
// 2014.01.17 Genkendelse af FI indbetalinger fra Sparekasserne. Søg Sparekasserne
// 2014.01.27 Genkendelse af FI indbetalinger fra Nordea. Søg Nordea
// 2014.02.03 Genkendelse af danske månedforkortelser i datoer. Søg 20140203
// 2014.07.08 Genkendelse af dankort betalinger 20140708
// 2014.10.05 Indsat "auto_detect_line_endings", eller kan den ikke altid genkende filer genereret på MAC 
// 2015.09.04 Genkendelse af dankort krediteringer 20150904
// 2016.02.12 Genkendelse af kortbetalinger gennem SparNord. Søg SparNord
// 2016.02.15 Ved Kortbetalinger fra SparNord registreres gortgenyrer på separat linje. Søg kortgebyr. 

ini_set("auto_detect_line_endings", true);

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="SALDI - Bankimport";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

$vend=NULL;

if(($_GET)||($_POST)) {

	if ($_GET) {
		$kladde_id=$_GET['kladde_id'];
		$bilag=$_GET['bilagsnr'];
	}
	else {
		$submit=$_POST['submit'];
		if ($_POST['vend']) $vend='checked';
		$kladde_id=$_POST['kladde_id'];
		$filnavn=$_POST['filnavn'];
		$splitter=$_POST['splitter'];
		$feltnavn=$_POST['feltnavn'];
		$feltantal=$_POST['feltantal'];
		$kontonr=$_POST['kontonr'];
		$valuta=$_POST['valuta'];
		$valutakode[0]=$_POST['valutakode']*1;
		$bilag=$_POST['bilag'];
	}
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	if ($menu=='T') {
		$leftbutton="<a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>Luk</a>";
		$rightbutton="";
		include("../includes/topmenu.php");
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		print "<td width=\"10%\" $top_bund><a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>Luk</a></td>";
		print "<td width=\"80%\" $top_bund>Bankimport (Kassekladde $kladde_id)</td>";
		print "<td width=\"10%\" $top_bund ><br></td>";
		print "</tbody></table>";
		print "</td></tr>";
	}

	if (($kontonr) && (strlen($kontonr)==1)) {
		$kontonr=strtoupper($kontonr);
		$query = db_select("select * from kontoplan where genvej='$kontonr' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $kontonr=$row[kontonr];
		else {
			$kontonr='';
			print "<BODY onLoad=\"javascript:alert('Angivet kontonummer findes ikke')\">";
		}
	}
	elseif ($kontonr)	 {
		$tmp=$kontonr*1;
		if (!$row=db_fetch_array(db_select("select id from kontoplan where kontonr=$tmp",__FILE__ . " linje " . __LINE__))) {
			print "<BODY onLoad=\"javascript:alert('Kontonummer $kontonr findes ikke i kontoplanen')\">";
			$submit='Vis';
		}
	}
	if (basename($_FILES['uploadedfile']['name'])) {
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
			} else {
				db_modify ("insert into grupper (beskrivelse,art,kode,kodenr) values ('Bankimport','KASKL','3','$bruger_id')",__FILE__ . " linje " . __LINE__);
			}
			if (!$feltantal) $feltantal=1;	
			vis_data($kladde_id,$filnavn,'',$feltnavn,$feltantal,$kontonr,$bilag,$vend,$valutakode);
		}	else {
			echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
		}
	}
	elseif($submit=='Vis'){
		vis_data($kladde_id,$filnavn,$splitter,$feltnavn,$feltantal,$kontonr,$bilag,$vend,$valutakode);
	}
	elseif($submit=='Flyt'){
		if (($kladde_id)&&($filnavn)&&($splitter)&&($kontonr))	flyt_data($kladde_id,$filnavn,$splitter,$feltnavn,$feltantal,$kontonr,$bilag,$valutakode[0]);
		else vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag,$vend,$valutakode);
	}
	else {
		upload($kladde_id, $bilag);
	}

/*	
if ($kladde_id)
	{
		hentdata($kladde_id);
	}
*/	
}
print "</tbody></table>";
################################################################################################################
function upload($kladde_id, $bilag){
global $charset;

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"bankimport.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center> V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "</tbody></table>";
print "</td></tr>";
}

function vis_data($kladde_id,$filnavn,$splitter,$feltnavn,$feltantal,$kontonr,$bilag,$vend,$valutakode){
global $charset;
global $bruger_id;

$x=0;
$q=db_select("select kodenr,box1 from grupper where art='VK' order by box1",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if (trim($r['box1'])) {
		$x++;
		$valutakode[$x]=$r['kodenr'];
		$valuta[$x]=$r['box1'];
#echo "$valutakode[$x] $valuta[$x]<br>";
		}
}


$fp=fopen("$filnavn","r");
if ($fp) {
	for ($y=1; $y<10; $y++) {
		$linje=fgets($fp); 
		$tmp=$linje;
		while ($tmp=substr(strstr($tmp,";"),1)) $semikolon++;	
		$tmp=$linje;
		while ($tmp=substr(strstr($tmp,","),1)) $komma++;
		$tmp=$linje;
		while ($tmp=substr(strstr($tmp,chr(9)),1)) $tabulator++;
		$tmp='';
	}
	if (($komma>$semikolon)&& ($komma>$tabulator)) {$tmp='Komma'; $feltantal=$komma;}
	elseif (($semikolon>$tabulator)&&($semikolon>$komma)) {$tmp='Semikolon'; $feltantal=$semikolon;}			
	elseif (($tabulator>$semikolon)&&($tabulator>$komma)) {$tmp='Tabulator'; $feltantal=$tabulator;}			

	if (!$splitter) {$splitter=$tmp;}
	if ($splitter=='Komma') $feltantal=$komma;
	elseif ($splitter=='Semikolon') $feltantal=$semikolon;
	elseif ($splitter=='Tabulator') $feltantal=$tabulator;
	$cols=$feltantal+1;
}
fclose($fp);

$fp=fopen("$filnavn","r");
if ($fp) {
	if ($splitter=='Komma') $splittegn=",";
	elseif ($splitter=='Semikolon') $splittegn=";";
	elseif ($splitter=='Tabulator') $splittegn=chr(9);
	
	$y=0;
	$feltantal=0;
#	for ($y=1; $y<20; $y++) {
	while ($linje=fgets($fp)) {
		$linje=trim($linje);
		if ($linje) {
			$y++;
			if ($charset=='UTF-8') $linje=utf8_encode($linje);
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
			for ($x=1; $x<=$z; $x++) {
				$ny_linje[$y]=$ny_linje[$y].$felt[$x].chr(9);
#				echo "$felt[$x]|".chr(9);	
			}
			$x++;
			$ny_linje[$y]=$ny_linje[$y].$felt[$x]."\n";
#			echo "$felt[$x]<br>";
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
print "<span title='Klik her for at indlæse filen nedefra'>\n";
print "Vend <input type=\"checkbox\" name=\"vend\" $vend>";
print "</span>";
print "<span title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'>Separatortegn&nbsp;<select name=splitter>\n";
if ($splitter) {print "<option>$splitter</option>\n";}
if ($splitter!='Semikolon') print "<option>Semikolon</option>\n";
if ($splitter!='Komma') print "<option>Komma</option>\n";
if ($splitter!='Tabulator') print "<option>Tabulator</option>\n";
print "</select></span>";
if ($v_ant=count($valuta)) {
	$valutakode[0]*=1;
	print "<span title='Angiv valuta'> Valuta<select name='valutakode'>\n";
	for ($x=1;$x<=$v_ant;$x++) {
		if ($valutakode[$x]==$valutakode[0]) print "<option value='$valutakode[$x]'>$valuta[$x]</option>\n";
	}
	print "<option value='0'>DKK</option>\n";
	for ($x=1;$x<=$v_ant;$x++) {
		if ($valutakode[$x]!=$valutakode[0]) print "<option value='$valutakode[$x]'>$valuta[$x]</option>\n";
	}
	print "</select></span>";
}
print "&nbsp;<span title='Angiv hvilket kontonummer der skal anvendes til posteringer'>Posteringskonto&nbsp;<input type=text size=8 name=kontonr value=$kontonr></span>&nbsp;";
print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />";
if (!in_array("dato",$feltnavn)) print "<BODY onLoad=\"javascript:alert('Kolonne for dato ikke valgt')\">";
elseif (!in_array("beskrivelse",$feltnavn)) print "<BODY onLoad=\"javascript:alert('Kolonne for beskrivelse ikke valgt')\">";
elseif (!in_array("belob",$feltnavn)) print "<BODY onLoad=\"javascript:alert('Kolonne for bel&oslash;b ikke valgt')\">";
elseif (!$splitter) print "<BODY onLoad=\"javascript:alert('Separatortegn ikke valgt')\">";
elseif (!$kontonr) print "<BODY onLoad=\"javascript:alert('Der skal angives et kontonummer til den bankkonto hvor posteringer skal f&oslash;res')\">";
elseif (($kladde_id)&&($filnavn)&&($splitter)&&($kontonr)) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Flyt\" /></td></tr>";
print "<tr><td colspan=$cols><hr></td></tr>\n";
#if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
#elseif ($splitter=='Komma') {$splitter=',';}
#elseif ($splitter=='Tabulator') {$splitter=chr(9);}
$splitter=chr(9);
print "<tr><td><span title='Angiv 1. bilagsnummer'><input type=text size=4 name=bilag value=$bilag></span></td>";
for ($y=0; $y<=$feltantal; $y++) {
	if (($feltnavn[$y]=='dato') &&($dato==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Dato')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='beskrivelse') &&($beskr==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Beskrivelse')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='belob')&&($belob==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Bel&oslash;b')\">";
		$feltnavn[$y]='';
	}
	if ($feltnavn[$y]=='belob') print "<td align=right><select name=feltnavn[$y]>\n";
	elseif ($feltnavn[$y]) print "<td><select name=feltnavn[$y]>\n";
	else  print "<td align=center><select name=feltnavn[$y]>\n";
	print "<option>$feltnavn[$y]</option>\n";
	if ($feltnavn[$y]) print "<option></option>\n";
	if ($feltnavn[$y]!='dato') print "<option value=\"dato\">Dato</option>\n";
	else $dato=1;
	if ($feltnavn[$y]!='beskrivelse') print "<option value=\"beskrivelse\">Beskrivelse</option>\n";
	else $beskr=1;
	if ($feltnavn[$y]!='belob') print "<option value=\"belob\">Bel&oslash;b</option>\n";
	else $belob=1;
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
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='dato') { # 20140203
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
					$felt[$y]=str_replace(" ","",$felt[$y]);
					if (nummertjek($felt[$y])=='US') {
						if ($felt[$y]==0) $skriv_linje=0;
						else $felt[$y]=dkdecimal($felt[$y]);
					} elseif (nummertjek($felt[$y])=='DK') {
						if (usdecimal($felt[$y])==0) $skriv_linje=0;
					}	else $skriv_linje=0;		
				}
			}
 		}		
		if ($skriv_linje==1){
			print "<tr><td>$bilag</td>";
			for ($y=0; $y<=$feltantal; $y++) {
				if ($feltnavn[$y]=='belob') {
					print "<td align=right>$felt[$y]&nbsp;</td>";
				}
				elseif ($feltnavn[$y]) {print "<td>$felt[$y]&nbsp;</td>";}
				else {print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";}
			}
			print "</tr>";
			$bilag++;
		} else {
			print "<tr><td><span style=\"color: rgb(153, 153, 153);\">-</span></td>";
			for ($y=0; $y<=$feltantal; $y++) {
				if ($feltnavn[$y]=='belob') {
					print "<td align=right><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
				} elseif ($feltnavn[$y]) print "<td><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
				else {print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";}
			}
			print "</tr>";
		}	
	}
}
 fclose($fp);
print "</tbody></table>";
print "</td></tr>";
db_modify("update grupper set box1='$kontonr', box2='$feltantal' where ART='KASKL' and kode='3' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);
for ($y=0; $y<=$feltantal; $y++) {
	$box=$y+3;
	if ($box<=10) {
		$box="box$box";
		db_modify("update grupper set $box='$feltnavn[$y]' where ART='KASKL' and kode='3' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);
	}
}
} # endfunc # vis_data

function flyt_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag,$valutakode){
	global $charset;

	transaktion('begin');
/*
	$fp=fopen($filnavn."2","r");
	if ($fp) {
		for ($y=1; $y<4; $y++) $linje=fgets($fp);
		$tmp=$linje;
		while ($tmp=substr(strstr($tmp,";"),1)) {$semikolon++;}
		$tmp=$linje;
		while ($tmp=substr(strstr($tmp,","),1)) {$komma++;}
		$tmp=$linje;
		while ($tmp=substr(strstr($tmp,chr(9)),1)) {$tabulator++;}
		$tmp='';
		if (($komma>$semikolon)&& ($komma>$tabulator)) {$tmp='Komma'; $feltantal=$komma;}
		elseif (($semikolon>$tabulator)&&($semikolon>$komma)) {$tmp='Semikolon'; $feltantal=$semikolon;}			
		elseif (($tabulator>$semikolon)&&($tabulator>$komma)) {$tmp='Tabulator'; $feltantal=$tabulator;}			
		if (!$splitter) {$splitter=$tmp;}
		$cols=$feltantal+1;
	}
	fclose($fp);
	if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
	elseif ($splitter=='Komma') {$splitter=',';}
	elseif ($splitter=='Tabulator') {$splitter=chr(9);}
*/
	$splitter=chr(9);
	$fp=fopen($filnavn."2","r");
	if ($fp) {
		$x=0;
		$kortgebyr=0;
		while (!feof($fp)) {
			$skriv_linje=0;
			if ($linje=trim(fgets($fp))) {
				$x++;
				$skriv_linje=1;
				$felt=array();
				$felt = explode($splitter, $linje);
				for ($y=0; $y<=$feltantal; $y++) {
					$felt[$y]=trim($felt[$y]);
					if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
					if ($feltnavn[$y]=='dato') { # 20140203
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
						$felt[$y]=str_replace(" ","",$felt[$y]);
						if (nummertjek($felt[$y])=='US') $felt[$y]=dkdecimal($felt[$y]);
						elseif (nummertjek($felt[$y])!='DK') $skriv_linje=0;		
					}
				}
 			}		
			if ($skriv_linje==1){
				for ($y=0; $y<=$feltantal; $y++) {
					$bilag=$bilag*1;
					if ($feltnavn[$y]=='belob') $amount=usdecimal($felt[$y]);
					elseif ($feltnavn[$y]=="dato") $transdate=usdate($felt[$y]);
					elseif ($feltnavn[$y]=="beskrivelse") $beskrivelse=db_escape_string($felt[$y]);
				}
				if ($amount>0) {
					if (strlen($beskrivelse)==22 && substr($beskrivelse,0,3)=='IK ' && is_numeric(substr($beskrivelse,3,19))) { # ?
						$kredit=substr($beskrivelse,3,13)*1;
						$faktura=substr($beskrivelse,16,5)*1;
						$k_type='D';
					} elseif (strlen($beskrivelse)==20 && substr($beskrivelse,-4)=='IK71' && is_numeric(substr($beskrivelse,0,14))) { # Sparekasserne
						$kredit=substr($beskrivelse,0,8)*1;
						$faktura=substr($beskrivelse,8,6)*1;
						$k_type='D';
					} elseif (strlen($beskrivelse)==32 && substr($beskrivelse,7,10)=='Indbet.ID=' && is_numeric(substr($beskrivelse,17,15))) { # Danske Bank
						$kredit=substr($beskrivelse,17,9)*1;
						$faktura=substr($beskrivelse,26,5)*1;
						$k_type='D';
					} elseif (strlen($beskrivelse)==35 && substr($beskrivelse,0,21)=='Indbetalingskort, nr.' && is_numeric(substr($beskrivelse,22,14))) { # Nordea
						$kredit=substr($beskrivelse,22,7)*1;
						$faktura=substr($beskrivelse,29,5)*1;
						$k_type='D';
					} elseif (strlen($beskrivelse)==40 && substr($beskrivelse,0,6)=='DK-IND' && is_numeric(substr($beskrivelse,22,14))) { # SparNord
						$felt_1="%".substr($beskrivelse,-10);
						$tmp=$amount-3;
						# fungerer med op til 2 kr i kortgebyr
						$qtxt= "select fakturanr,kontonr from ordrer where felt_1 LIKE '$felt_1' and sum <= '$amount' and sum >= '$tmp'";
						$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$faktura=$r['fakturanr'];
						$kredit=$r['kontonr']*1;
						$k_type='D';
					} elseif (c==30 && substr($beskrivelse,0,2)=='DK' && is_numeric(substr($beskrivelse,4,7)) && is_numeric(substr($beskrivelse,12,9)) && is_numeric(substr($beskrivelse,25,5))) { # Dankort betaling 20140708 
						$betalings_id="%".substr($beskrivelse,12,9);
						$r=db_fetch_array(db_select("select fakturanr,kontonr,sum,moms from ordrer where betalings_id LIKE '$betalings_id' and sum = '$amount'",__FILE__ . " linje " . __LINE__));
						$faktura=$r['fakturanr'];
						$fakturasum=$r['sum']+$r['moms'];
						$kortgebyr=$amount-$fakturasum;
						$kredit=$r['kontonr']*1;
						$k_type='D';
					} elseif (substr($beskrivelse,0,6)=='DKSSL ') { #20131119
						list($a,$b,$c)=explode(" ",$beskrivelse);
						$ordrenr=substr($c,7)*1;
						if ($ordrenr) {
							$r=db_fetch_array(db_select("select fakturanr,kontonr from ordrer where ordrenr = '$ordrenr' and sum = '$amount'",__FILE__ . " linje " . __LINE__));
							$faktura=$r['fakturanr'];
							$kredit=$r['kontonr']*1;
							$k_type='D';
						} else {
							$faktura='';
							$kredit='0';
						}
					} else {
						$kredit='0';
						$faktura='';
						$k_type='';
					}
					if ($kortgebyr) {
						$qtxt="insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,valuta)";
						$qtxt.="  values ";
						$qtxt.="('$bilag','$transdate','$beskrivelse','F','$kontonr','F','','$faktura','$amount','$kladde_id','$valutakode')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt="insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,valuta)";
						$qtxt.="  values ";
						$qtxt.="('$bilag','$transdate','$beskrivelse','F','','$k_type','$kredit','$faktura','$fakturasum','$kladde_id','$valutakode')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt="insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,valuta)";
						$qtxt.="  values ";
						$qtxt.="('$bilag','$transdate','$beskrivelse','F','','F','','$faktura','$kortgebyr','$kladde_id','$valutakode')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					} else {
						$qtxt="insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,valuta)";
						$qtxt.="  values ";
						$qtxt.="('$bilag','$transdate','$beskrivelse','F','$kontonr','$k_type','$kredit','$faktura','$amount','$kladde_id','$valutakode')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
					$bilag++;
				} elseif ($amount<0) {
					$amount=$amount*-1;
					if (strlen($beskrivelse)==30 && substr($beskrivelse,0,2)=='DK' && is_numeric(substr($beskrivelse,4,7)) && is_numeric(substr($beskrivelse,12,9)) && is_numeric(substr($beskrivelse,25,5))) { # Dankort betaling 20150904 
						$betalings_id="%".substr($beskrivelse,12,9);
						$r=db_fetch_array(db_select("select fakturanr,kontonr from ordrer where betalings_id LIKE '$betalings_id' and sum = '$amount'",__FILE__ . " linje " . __LINE__));
						$faktura=$r['fakturanr'];
						$debet=$r['kontonr']*1;
						$d_type='D';
					} else {
					$dtype='F';
					$debet=0;
#cho "insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, amount, kladde_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$kontonr', '$amount', '$kladde_id')<br>";
					db_modify("insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,valuta) values ('$bilag','$transdate','$beskrivelse','$d_type','$debet','F','$kontonr','$amount','$kladde_id','$valutakode')",__FILE__ . " linje " . __LINE__);
					}
					$bilag++;
				}
			}
		}
	}	
	fclose($fp);
	unlink($filnavn); # sletter filen.
	unlink($filnavn."2"); # sletter filen.
#xit;
	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
}
function nummertjek ($nummer){
	$nummer=trim($nummer);
	$retur=1;
	$nummerliste=array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0", ",", ".", "-");
	for ($x=0; $x<strlen($nummer); $x++) {
		if (!in_array($nummer{$x}, $nummerliste)) $retur=0;
	}
	if ($retur) {
		for ($x=0; $x<strlen($nummer); $x++) {
			if ($nummer{$x}==',') $komma++;
			elseif ($nummer{$x}=='.') $punktum++;		
		}
		if ((!$komma)&&(!$punktum)) $retur='US';
		elseif (($komma==1)&&(substr($nummer,-3,1)==',')) $retur='DK';
		elseif (($punktum==1)&&(substr($nummer,-3,1)=='.')) $retur='US';
		elseif (($komma==1)&&(!$punktum)) $retur='DK';
		elseif (($punktum==1)&&(!$komma)) $retur='US';	
	}
	return $retur;
}
	
