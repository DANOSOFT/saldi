<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/importer_kontoplan.php --- lap 4.0.6 --- 2022-04-03 ---
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
// Copyright (c) 2003 - 2022 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20181112 Håndtering af tegnsæt og MAC linjeskift.
// 20181204 Valg gemmes i cookie
// 20210713 LOE - Translated some texts
// 20220404	PHR function vis_data & overfoer_data: Inserted trim($felt[$y],'"');	
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding

@session_start();
$s_id=session_id();

ini_set("auto_detect_line_endings", true);

$css="../css/standard.css";

$title="Importer_kontoplan";
	
$komma=$semikolon=$tabulator=NULL;
$feltnavn=array();
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
#if ($popup) print "<td width=\"10%\" $top_bund><a href=../includes/luk.php accesskey=L>Luk</a></td>"; 
# else 
print "<td width=\"10%\" $top_bund><a href=diverse.php?sektion=div_io accesskey=L>".findtekst(30, $sprog_id)."</a></td>"; #20210713
print "<td width=\"80%\" $top_bund>$title</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";

if (isset($_POST['hent']) || isset($_POST['vis']) || isset($_POST['import'])) {
#	if (strstr($submit, "Import")) $submit="Importer";
	$vis=if_isset($_POST['vis']);
	$import=if_isset($_POST['import']);
	$filnavn=if_isset($_POST['filnavn']);
	$file_charset=if_isset($_POST['file_charset']);
	$splitter=if_isset($_POST['splitter']);
	$feltantal=if_isset($_POST['feltantal']);
	$kontonr=if_isset($_POST['kontonr']);
	$bilag=if_isset($_POST['bilag']);

	if ($feltnavn=if_isset($_POST['feltnavn'])) {		
		$cookie=$file_charset."|".$splitter."|";
		if (!$feltnavn) $feltnavn=array();
/*
		for ($i=count($feltnavn);$i>0;$i--) {
			if (!$x && trim($feltnavn[$i])) $x=$i;
		}
		$feltnavn=array_slice($feltnavn, 0, $x);
*/
		for ($i=0;$i<count($feltnavn);$i++) {
			if (!isset($feltnavn[$i])) $feltnavn[$i]=NULL;
			($i)?$cookie.=";".$feltnavn[$i]:$cookie.=$feltnavn[$i];
		}
		setcookie('saldi_kto_imp',$cookie);
	} elseif (isset($_COOKIE['saldi_kto_imp'])) {
		list($file_charset,$splitter,$fn)=explode("|",$_COOKIE['saldi_kto_imp']);
		$feltnavn=explode(";",$fn);
	}
	if (isset ($_FILES['uploadedfile']['name']) && basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
		vis_data($file_charset, $filnavn, $splitter, $feltnavn, 1, $kontonr, $bilag);
		} else echo "Der er sket en fejl under hentningen, prøv venligst igen";
	} elseif($vis){
		vis_data($file_charset, $filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag);
	} elseif($import){
		if (($filnavn)&&($splitter))	overfoer_data($filnavn, $splitter, $feltnavn, $feltantal);
		else vis_data($file_charset, $filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag);
	}
} else {
	$qtxt="select box1, box2, beskrivelse from grupper where art='RA' order by box2 desc,box1 desc";
	if (!$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		exit;
	}else{
		$startdate=$r1['box2']."-".$r1['box1']."-01";
		$qtxt="select id from transaktioner where transdate >= '$startdate'";
		if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$txt1= findtekst(1365, $sprog_id);  #20210713
			$txt2= findtekst(1366, $sprog_id);
			alert("$txt1:"." $r1[beskrivelse]-" ."$txt2");
			if ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=diverse.php?sektion=div_io\">";
			else print "<meta http-equiv=\"refresh\" content=\"0;URL=diverse.php?sektion=div_io\">";
			exit;
		}	
	}
	upload($kladde_id, $bilag);
}

print "</tbody></table>";
#####################################################################################################
function upload($kladde_id, $bilag){

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"importer_kontoplan.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
print "<tr><td width=100% align=center> ".findtekst(1364, $sprog_id).": <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" name=\"hent\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "</tbody></table>";
print "</td></tr>";
}

function vis_data($file_charset, $filnavn, $splitter, $feltnavn, $feltantal){
global $charset;
global $regnaar;

$komma=$kontonr=$semikolon=$tabulator=0;
$beskrivelse=$fra_kto=$kontonr=$kontotype=NULL;

$fp=fopen("$filnavn","r");
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
print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"importer_kontoplan.php\" method=\"POST\">";
#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
print "<tr><td colspan='$cols' align=center><span title='Angiv hvilket tegnsæt der anvendes'>Tegnsæt<select name='file_charset'>\n";
if ($file_charset=='UTF-8') print "<option value='UTF-8'>UTF-8</option>\n";
if ($file_charset=='ISO-8859-15') print "<option value='ISO-8859-15'>ISO-8859-15 (Windows)</option>\n";
if ($file_charset=='cp865') print "<option value='cp865'>cp865 (DOS)</option>\n";
if ($file_charset=='macintosh') print "<option value='macintosh'>MAC</option>\n";
if ($file_charset!='UTF-8') print "<option value='UTF-8'>UTF-8</option>\n";
if ($file_charset!='ISO-8859-15') print "<option value='ISO-8859-15'>ISO-8859-15 (Windows)</option>\n";
if ($file_charset!='cp865') print "<option value='cp865'>cp865 (DOS)</option>\n";
if ($file_charset!='macintosh') print "<option value='macintosh'>MAC</option>\n";
print "</select></span>&nbsp";
print "<span title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'>Sepatatortegn&nbsp;<select name=splitter>\n";
if ($splitter) {print "<option>$splitter</option>\n";}
if ($splitter!='Semikolon') print "<option>Semikolon</option>\n";
if ($splitter!='Komma') print "<option>Komma</option>\n";
if ($splitter!='Tabulator') print "<option>Tabulator</option>\n";
print "</select></span>";
print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
print "&nbsp; <input type=\"submit\" name=\"vis\" value=\"Vis\" />";
#exit;
for ($y=0; $y<=$feltantal; $y++) {
	if (($feltnavn[$y]=='Kontonr') &&($kontonr==1)) {
		alert('Der kan kun være 1 kolonne med Kontonr');
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Kontonr') $kontonr=1;
	if ($feltnavn[$y]=='Beskrivelse' && $beskrivelse==1) {
		alert('Der kan kun være 1 kolonne med Beskrivelse');
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Beskrivelse') $beskrivelse=1;
	if (strstr($feltnavn[$y],'Kontotype') &&$kontotype==1) {
		alert('Der kan kun være 1 kolonne med Kontotype');
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Kontotype') $kontotype=1; 
	if (strstr($feltnavn[$y],'Moms' )&& $moms==1) {
		alert('Der kan kun være 1 kolonne med Moms');
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Moms') $moms=1;
	if (strstr($feltnavn[$y],'Fra_kto') && $fra_kto==1) {
		alert('Der kan kun være 1 kolonne med fra_kto');
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Fra_kto') $fra_kto=1;
	if (strstr($feltnavn[$y],'primo') && $primo==1) {
		alert('Der kan kun være 1 kolonne med primo');
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='primo') $fra_kto=1;
}

if (($filnavn)&&($splitter)&&($kontonr==1)&&($beskrivelse==1)&&($kontotype==1)) {
	print "&nbsp; <input type=\"submit\" name=\"import\" value=\"Import&eacute;r\" />";
}
print "</td></tr><tr><td colspan=$cols><hr></td></tr>\n";
if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
elseif ($splitter=='Komma') {$splitter=',';}
elseif ($splitter=='Tabulator') {$splitter=chr(9);}

# print "<tr><td><span title='Angiv 1. bilagsnummer'><input type=text size=4 name=bilag value=$bilag></span></td>";
for ($y=0; $y<=$feltantal; $y++) {
	if ($feltnavn[$y]) print "<td><select name='feltnavn[$y]'>\n";
	else  print "<td align=center><select name='feltnavn[$y]'>\n";
	print "<option>$feltnavn[$y]</option>\n";
	if ($feltnavn[$y]) print "<option></option>\n";
	if ($feltnavn[$y]!='Kontonr') print "<option>Kontonr</option>\n";
	if ($feltnavn[$y]!='Beskrivelse') print "<option>Beskrivelse</option>\n";
	if ($feltnavn[$y]!='Kontotype') print "<option>Kontotype</option>\n";
	if ($feltnavn[$y]!='Moms') print "<option>Moms</option>\n";
	if ($feltnavn[$y]!='Fra_kto') print "<option>Fra_kto</option>\n";
	if ($feltnavn[$y]!='map_to') print "<option value='map_to'>Map til</option>\n";
	if ($regnaar==1 && $feltnavn[$y]!='primo') print "<option>primo</option>\n";
}

print "</form></td></tr>";
$fp=fopen("$filnavn","r");
if ($fp) {
	$x=0;
	$kontonumre=array();
	$alert=null;
	while (!feof($fp)) {
		$skriv_linje=0;
		if ($linje=trim(fgets($fp))) {
			$x++;
			$skriv_linje=1;
#			if ($file_charset!=$charset) $linje=mb_convert_encoding($linje ,$charset,$file_charset);
			if ($file_charset!=$charset) $linje=iconv($file_charset, $charset, $linje);
			$felt=array();
			$kontotyper=array("H","D","S","Z","X","R");
			$momstyper=array("S","K","E","Y");
			$felt = explode($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$felt[$y]=trim($felt[$y]);
				$felt[$y]=trim($felt[$y],'"');
				# if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='Kontonr' && ($felt[$y]!=(int)$felt[$y] || in_array($felt[$y],$kontonumre))) {
					if (!$alert) alert('Røde linjer indeholder fejl i kontonummer og bliver ikke importeret');
					$alert=1;
					$skriv_linje=2;
#					alert('Kontonrnummer skal være numerisk');
				} elseif ($feltnavn[$y]=='Kontonr') $kontonumre[$x]=$felt[$y];
				if ($feltnavn[$y]=='Kontotype') {
					if (!in_array($felt[$y],$kontotyper)) {
					if (!$alert) alert('Røde linjer indeholder fejl i kontotype og bliver ikke importeret');
					$alert=1;
					$skriv_linje=2;
#					alert('Kontotype skal være H,D,S eller Z');
					} else if ($felt[$y]=='Z') $sumkonto=1;
					else $sumkonto=0;
				}	
				if ($feltnavn[$y]=='Moms') {
					$a=substr($felt[$y],0,1);
					$b=substr($felt[$y],1);
					if (($felt[$y])&&((!in_array($a,$momstyper))||($b != (int)$b))) {
						if (!$alert) alert('Røde linjer indeholder fejl (Moms) og bliver ikke importeret');
						$alert=1;
						$skriv_linje=2;
#						alert('Momstype skal begynde med S eller K efterfulgt af en numerisk vaerdi');
					}				
				}
				if ($feltnavn[$y]=='Fra_kto' && $sumkonto)  {
					if (!$felt[$y]) $felt[$y]='0';
					if ($felt[$y] != (int)$felt[$y]) {
						if (!$alert) alert('Røde linjer indeholder fejl (Fra kto) og bliver ikke importeret');
						$alert=1;
						$skriv_linje=2;
#						alert('Kontonrnummer skal være numerisk');
					}		
				} elseif ($feltnavn[$y]=='Fra_kto') $felt[$y]='';
#				if ($feltnavn[$y]=='primo')  {
#					if (!is_numeric($feltnavn[$y])) {
#						$feltnavn[$y]=usdecimal($feltnavn[$y]);
#					}		
#				}
			}
 		}
 				
		if ($skriv_linje>=1){
			print "<tr>";
#			print "<tr><td>$bilag</td>";
			if ($skriv_linje==2) $color="#e00000";
			else $color="#000000";
			for ($y=0; $y<=$feltantal; $y++) {
				if ($feltnavn[$y]) print "<td><span style=\"color: $color;\">$felt[$y]&nbsp;</span></td>";
				else print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
			}
			print "</tr>";
		}
	}	
}
 fclose($fp);
print "</tbody></table>";
print "</td></tr>";
}

function overfoer_data($filnavn, $splitter, $feltnavn, $feltantal){
global $charset;
global $file_charset;
global $regnaar;

$r1=db_fetch_array(db_select("select kodenr as kodenr from grupper where art='RA' order by box2 desc,box1 desc limit 1",__FILE__ . " linje " . __LINE__));
$regnskabsaar=$r1['kodenr'];

$fp=fopen("$filnavn","r");
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
	if (!$splitter) $splitter=$tmp;
	$cols=$feltantal+1;
}

fclose($fp);

for ($y=0; $y<=$feltantal; $y++) {
	if (($feltnavn[$y]=='Kontonr') &&($kontonr==1)) {
		alert('Der kan kun være 1 kolonne med Kontonr');
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Kontonr') $kontonr=1;
	if (($feltnavn[$y]=='Beskrivelse') &&($beskrivelse==1)) {
		alert('Der kan kun være 1 kolonne med Beskrivelse');
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Beskrivelse') $beskrivelse=1;
	if ((strstr($feltnavn[$y],'Kontotype'))&&($kontotype==1)) {
		alert('Der kan kun være 1 kolonne med Kontotype');
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Kontotype') $kontotype=1; 
	if ((strstr($feltnavn[$y],'Moms'))&&($moms==1)) {
		alert('Der kan kun være 1 kolonne med Moms');
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Moms') $moms=1;
	if ((strstr($feltnavn[$y],'Fra_kto'))&&($fra_kto==1)) {
		alert('Der kan kun være 1 kolonne med fra_kto');
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Fra_kto') $fra_kto=1;
	if ((strstr($feltnavn[$y],'primo'))&&($primo==1)) {
		alert('Der kan kun være 1 kolonne med primo');
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='primo') $primo=1;
}

print "<tr><td colspan=$cols><hr></td></tr>\n";
if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
elseif ($splitter=='Komma') {$splitter=',';}
elseif ($splitter=='Tabulator') {$splitter=chr(9);}

# print "<tr><td><span title='Angiv 1. bilagsnummer'><input type=text size=4 name=bilag value=$bilag></span></td>";

transaktion('begin');
 #echo "delete from kontoplan where regnskabsaar='$regnskabsaar'<br>";
db_modify("delete from kontoplan where regnskabsaar='$regnskabsaar'",__FILE__ . " linje " . __LINE__);

$fp=fopen("$filnavn","r");
if ($fp) {
	$kontonumre=array();
	$x=0;
	while (!feof($fp)) {
		$skriv_linje=0;
		if ($linje=trim(fgets($fp))) {
			$x++;
			$skriv_linje=1;
#			if ($charset=='UTF-8') $linje=mb_convert_encoding($linje, 'UTF-8', 'ISO-8859-1');
			if ($file_charset!=$charset) $linje=iconv($file_charset, $charset, $linje);

			$felt=array();
			$kontotyper=array("H","D","S","Z","R");
			$momstyper=array("S","K","E","Y");
			$felt = explode($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$felt[$y]=trim($felt[$y]);
				$felt[$y]=trim($felt[$y],'"');
				$feltnavn[$y]=strtolower($feltnavn[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ((substr($felt[$y],0,1) == "'")&&(substr($felt[$y],-1) == "'")) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if (($feltnavn[$y]=='Kontonr')&&(($felt[$y] != (int)$felt[$y])||(in_array($felt[$y],$kontonumre)))) {
					$skriv_linje=2;
				} elseif ($feltnavn[$y]=='Kontonr') $kontonumre[$x]=$felt[$y];
				if (($feltnavn[$y]=='kontonr') && ($felt[$y] != (int)$felt[$y])) {
					$skriv_linje=2;
				}
				if ($feltnavn[$y]=='beskrivelse') {
					$felt[$y]=addslashes($felt[$y]);
				}
				if ($feltnavn[$y]=='kontotype') {
					if ((strlen($felt[$y])>1)||(!in_array($felt[$y],$kontotyper))) {
					$skriv_linje=2;
					} else if ($felt[$y]=='Z') $sumkonto=1;
					else $sumkonto=0;
				}	
				if ($feltnavn[$y]=='moms') {
					$a=substr($felt[$y],0,1);
					$b=substr($felt[$y],1);
					if (($felt[$y])&&((!in_array($a,$momstyper))||($b != (int)$b))) {
						$skriv_linje=2;
					}				
				}
				if (($feltnavn[$y]=='fra_kto')&&($sumkonto))  {
					if (!$felt[$y]) $felt[$y]='0';
					if ($felt[$y] != (int)$felt[$y]) {
						$skriv_linje=2;
					}
				}
				if (($feltnavn[$y]=='fra_kto')&&($sumkonto))  {
					if (!$felt[$y]) $felt[$y]='0';
					if ($felt[$y] != (int)$felt[$y]) {
						$skriv_linje=2;
					}
				} elseif ($feltnavn[$y]=='fra_kto') $felt[$y]='0';
				if ($feltnavn[$y]=='map_to') $felt[$y] = (int)$felt[$y];
				if ($feltnavn[$y]=='primo')  {
					if (!is_numeric($felt[$y])) {
						$felt[$y]=usdecimal($felt[$y]);
					}		
					$balance=$balance+$felt[$y];
				}
			}
 		}		
		if ($skriv_linje==1){
			$a='';
			$b='';
			for ($y=0; $y<=$feltantal; $y++) {
				if ($y>0 && $feltnavn[$y]) {
					if ($a) {
						$a=$a.",";
						$b=$b.",";
					}
				}
				if ($feltnavn[$y]) {
					$a=$a.$feltnavn[$y];
					if ($feltnavn[$y] == 'kontonr') $felt[$y]*=1;
					$b=$b."'".db_escape_string(trim($felt[$y]))."'";
				}
			}
			$qtxt = "insert into kontoplan($a, regnskabsaar) values ($b, '$regnskabsaar')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			
		}
	}	
}
 fclose($fp);
$q=db_modify("update kontoplan set til_kto=kontonr where kontotype='Z' and regnskabsaar='$regnskabsaar'",__FILE__ . " linje " . __LINE__);
transaktion('commit');
print "</tbody></table>";
print "</td></tr>";
if ($regnaar==1 && $balance) alert('Åbningsbalance stemmer ikke - kontroller sum');
else alert('Kontoplan importeret - husk at overføre åbningstal');
exit;
} # endfunc overfoer_data

function nummertjek ($nummer){
	$nummer=trim($nummer);
	$retur=1;
	$nummerliste=array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0", ",", ".", "-");
	for ($x=0; $x<strlen($nummer); $x++) {
		if (!in_array($nummer[$x], $nummerliste)) $retur=0;
	}
	if ($retur) {
		for ($x=0; $x<strlen($nummer); $x++) {
			if ($nummer[$x]==',') $komma++;
			elseif ($nummer[$x]=='.') $punktum++;
		}
		if ((!$komma)&&(!$punktum)) $retur='US';
		elseif (($komma==1)&&(substr($nummer,-3,1)==',')) $retur='DK';
		elseif (($punktum==1)&&(substr($nummer,-3,1)=='.')) $retur='US';
		elseif (($komma==1)&&(!$punktum)) $retur='DK';
		elseif (($punktum==1)&&(!$komma)) $retur='US';	
	}
	return $retur;
}
	
