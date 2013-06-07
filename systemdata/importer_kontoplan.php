<?php
// ------systemdata/importer_kontoplan.php--lap 3.2.9---2012-06-20-------
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
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Importer_kontoplan";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
#if ($popup) print "<td width=\"10%\" $top_bund><a href=../includes/luk.php accesskey=L>Luk</a></td>"; 
# else 
print "<td width=\"10%\" $top_bund><a href=diverse.php?sektion=div_io accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>$title</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";

if(($_GET)||($_POST)) {

	if ($_GET) {
		$kladde_id=$_GET['kladde_id'];
		$bilag=$_GET['bilagsnr'];
	}
	else {
		$submit=$_POST['submit'];
		if (strstr($submit, "Import")) $submit="Importer";
		$kladde_id=$_POST['kladde_id'];
		$filnavn=$_POST['filnavn'];
		$splitter=$_POST['splitter'];
		$feltnavn=$_POST['feltnavn'];
		$feltantal=$_POST['feltantal'];
		$kontonr=$_POST['kontonr'];
		$bilag=$_POST['bilag'];
	}

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
		vis_data($kladde_id, $filnavn, '', '', 1, $kontonr, $bilag);
		} else echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
	} elseif($submit=='Vis'){
		vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag);
	} elseif($submit=='Importer'){
		if (($filnavn)&&($splitter))	overfoer_data($filnavn, $splitter, $feltnavn, $feltantal);
		else vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag);
	}
} else {
	if (!$r1=db_fetch_array(db_select("select box1, box2, beskrivelse from grupper where art='RA' order by kodenr desc",__FILE__ . " linje " . __LINE__))) {
		exit;
	}else{
		$startdate=$r1[box2]."_".$r1[box1]."-01";
		if ($r2=db_fetch_array(db_select("select id from transaktioner where transdate >= '$startdate'",__FILE__ . " linje " . __LINE__))) {
			print "<BODY onLoad=\"javascript:alert('Der er foretaget transaktioner i regnskabs&aring;ret: $r1[beskrivelse] - import afbrudt')\">";
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
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center> V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "</tbody></table>";
print "</td></tr>";
}

function vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal){
global $charset;
global $regnaar;

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
print "<tr><td colspan=$cols align=center><span title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'>Sepatatortegn&nbsp;<select name=splitter>\n";
if ($splitter) {print "<option>$splitter</option>\n";}
if ($splitter!='Semikolon') print "<option>Semikolon</option>\n";
if ($splitter!='Komma') print "<option>Komma</option>\n";
if ($splitter!='Tabulator') print "<option>Tabulator</option>\n";
print "</select></span>";
print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />";

for ($y=0; $y<=$feltantal; $y++) {
	if (($feltnavn[$y]=='Kontonr') &&($kontonr==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Dato')\">";
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Kontonr') $kontonr=1;
	if (($feltnavn[$y]=='Beskrivelse')&&($beskrivelse==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Beskrivelse')\">";
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Beskrivelse') $beskrivelse=1;
	if ((strstr($feltnavn[$y],'Kontotype'))&&($kontotype==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Kontotype')\">";
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Kontotype') $kontotype=1; 
	if ((strstr($feltnavn[$y],'Moms'))&&($moms==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Moms')\">";
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Moms') $moms=1;
	if ((strstr($feltnavn[$y],'Fra_kto'))&&($fra_kto==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med fra_kto')\">";
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Fra_kto') $fra_kto=1;
	if ((strstr($feltnavn[$y],'primo'))&&($primo==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med primo')\">";
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='primo') $fra_kto=1;
}

if (($filnavn)&&($splitter)&&($kontonr==1)&&($beskrivelse==1)&&($kontotype==1)) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Import&eacute;r\" /></td></tr>";

print "<tr><td colspan=$cols><hr></td></tr>\n";
if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
elseif ($splitter=='Komma') {$splitter=',';}
elseif ($splitter=='Tabulator') {$splitter=chr(9);}

# print "<tr><td><span title='Angiv 1. bilagsnummer'><input type=text size=4 name=bilag value=$bilag></span></td>";
for ($y=0; $y<=$feltantal; $y++) {
	if ($feltnavn[$y]) print "<td><select name=feltnavn[$y]>\n";
	else  print "<td align=center><select name=feltnavn[$y]>\n";
	print "<option>$feltnavn[$y]</option>\n";
	if ($feltnavn[$y]) print "<option></option>\n";
	if ($feltnavn[$y]!='Kontonr') print "<option>Kontonr</option>\n";
	if ($feltnavn[$y]!='Beskrivelse') print "<option>Beskrivelse</option>\n";
	if ($feltnavn[$y]!='Kontotype') print "<option>Kontotype</option>\n";
	if ($feltnavn[$y]!='Moms') print "<option>Moms</option>\n";
	if ($feltnavn[$y]!='Fra_kto') print "<option>Fra_kto</option>\n";
	if ($regnaar==1 && $feltnavn[$y]!='primo') print "<option>primo</option>\n";
}

print "</form></td></tr>";
$fp=fopen("$filnavn","r");
if ($fp) {
	$x=0;
	$kontonumre=array();
	while (!feof($fp)) {
		$skriv_linje=0;
		if ($linje=trim(fgets($fp))) {
			$x++;
			$skriv_linje=1;
			if ($charset=='UTF-8') $linje=utf8_encode($linje);
			$felt=array();
			$kontotyper=array("H","D","S","Z","X","R");
			$momstyper=array("S","K","E","Y");
			$felt = explode($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$felt[$y]=trim($felt[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if (($feltnavn[$y]=='Kontonr')&&(($felt[$y]!=$felt[$y]*1)||(in_array($felt[$y],$kontonumre)))) {
					$skriv_linje=2;
					print "<BODY onLoad=\"javascript:alert('R&oslash;de linjer indeholder fejl og bliver ikke importeret')\">";
#					print "<BODY onLoad=\"javascript:alert('Kontonrnummer skal v&aelig;re numerisk')\">";
				} elseif ($feltnavn[$y]=='Kontonr') $kontonumre[$x]=$felt[$y];
				if ($feltnavn[$y]=='Kontotype') {
					if (!in_array($felt[$y],$kontotyper)) {
					$skriv_linje=2;
					print "<BODY onLoad=\"javascript:alert('R&oslash;de linjer indeholder fejl og bliver ikke importeret')\">";
#					print "<BODY onLoad=\"javascript:alert('Kontotype skal v&aelig;re H,D,S eller Z')\">";
					} else if ($felt[$y]=='Z') $sumkonto=1;
					else $sumkonto=0;
				}	
				if ($feltnavn[$y]=='Moms') {
					$a=substr($felt[$y],0,1);
					$b=substr($felt[$y],1);
					if (($felt[$y])&&((!in_array($a,$momstyper))||($b!=$b*1))) {
						$skriv_linje=2;
						print "<BODY onLoad=\"javascript:alert('R&oslash;de linjer indeholder fejl og bliver ikke importeret')\">";
#						print "<BODY onLoad=\"javascript:alert('Momstype skal begynde med S eller K efterfulgt af en numerisk vaerdi')\">";
					}				
				}
				if (($feltnavn[$y]=='Fra_kto')&&($sumkonto))  {
					if (!$felt[$y]) $felt[$y]='0';
					if ($felt[$y]!=$felt[$y]*1) {
						$skriv_linje=2;
						print "<BODY onLoad=\"javascript:alert('R&oslash;de linjer indeholder fejl og bliver ikke importeret')\">";
#						print "<BODY onLoad=\"javascript:alert('Kontonrnummer skal v&aelig;re numerisk')\">";
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
				if ($feltnavn[$y]) {print "<td><span style=\"color: $color;\">$felt[$y]&nbsp;</span></td>";}
				else {print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";}
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
global $regnaar;

$r1=db_fetch_array(db_select("select max(kodenr) as kodenr from grupper where art='RA'",__FILE__ . " linje " . __LINE__));
$regnskabsaar=$r1[kodenr];

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

for ($y=0; $y<=$feltantal; $y++) {
	if (($feltnavn[$y]=='Kontonr') &&($kontonr==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Dato')\">";
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Kontonr') $kontonr=1;
	if (($feltnavn[$y]=='Beskrivelse') &&($beskrivelse==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Beskrivelse')\">";
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Beskrivelse') $beskrivelse=1;
	if ((strstr($feltnavn[$y],'Kontotype'))&&($kontotype==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Kontotype')\">";
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Kontotype') $kontotype=1; 
	if ((strstr($feltnavn[$y],'Moms'))&&($moms==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Moms')\">";
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Moms') $moms=1;
	if ((strstr($feltnavn[$y],'Fra_kto'))&&($fra_kto==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med fra_kto')\">";
		$feltnavn[$y]='';
	} elseif ($feltnavn[$y]=='Fra_kto') $fra_kto=1;
	if ((strstr($feltnavn[$y],'primo'))&&($primo==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med primo')\">";
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
			if ($charset=='UTF-8') $linje=utf8_encode($linje);
			$felt=array();
			$kontotyper=array("H","D","S","Z","R");
			$momstyper=array("S","K","E","Y");
			$felt = explode($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$felt[$y]=trim($felt[$y]);
				$feltnavn[$y]=strtolower($feltnavn[$y]);

				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				
				if (($feltnavn[$y]=='Kontonr')&&(($felt[$y]!=$felt[$y]*1)||(in_array($felt[$y],$kontonumre)))) {
					$skriv_linje=2;
				} elseif ($feltnavn[$y]=='Kontonr') $kontonumre[$x]=$felt[$y];
				if (($feltnavn[$y]=='kontonr')&&($felt[$y]!=$felt[$y]*1)) {
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
					if (($felt[$y])&&((!in_array($a,$momstyper))||($b!=$b*1))) {
						$skriv_linje=2;
					}				
				} 
				if (($feltnavn[$y]=='fra_kto')&&($sumkonto))  {
					if (!$felt[$y]) $felt[$y]='0';
					if ($felt[$y]!=$felt[$y]*1) {
						$skriv_linje=2;
					}		
				} elseif ($feltnavn[$y]=='fra_kto') $felt[$y]='0';
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
					$b=$b."'".$felt[$y]."'";
				}
			}
			db_modify("insert into kontoplan($a, regnskabsaar) values ($b, '$regnskabsaar')",__FILE__ . " linje " . __LINE__);
			
		}
	}	
}
 fclose($fp);
$q=db_modify("update kontoplan set til_kto=kontonr where kontotype='Z' and regnskabsaar='$regnskabsaar'",__FILE__ . " linje " . __LINE__);
transaktion('commit');
print "</tbody></table>";
print "</td></tr>";
if ($regnaar==1 && $balance) print "<BODY onLoad=\"javascript:alert('&Aring;bningsbalance stemmer ikke - kontroller sum')\">";
else print "<BODY onLoad=\"javascript:alert('Kontoplan importeret - husk at overf&oslash;re &aring;bningstal')\">";
print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
} # endfunc overfoer_data

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
	
