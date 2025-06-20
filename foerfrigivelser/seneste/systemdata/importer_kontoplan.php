<?php
// ------systemdata/importer_kontoplan.php------------lap 1.9.2b-------08.04.2008-----------
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$title="Importer_kontoplan";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/dkdato.php");
include("../includes/usdate.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
include("../includes/db_query.php");

print "<div align=\"center\">";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund>$font<small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
print "<td width=\"80%\" $top_bund>$font<small>$title</small></td>";
print "<td width=\"10%\" $top_bund>$font<small><br></small></td>";
print "</tbody></table>";
print "</td></tr>";

if(($_GET)||($_POST)) {

	if ($_GET) {
		$kladde_id=$_GET['kladde_id'];
		$bilag=$_GET['bilagsnr'];
	}
	else {
		$submit=$_POST['submit'];
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
	if (!$r1=db_fetch_array(db_select("select box1, box2, beskrivelse from grupper where art='RA' order by kodenr desc"))) {
		exit;
	}else{
		$startdate=$r1[box2]."_".$r1[box1]."-01";
		if ($r2=db_fetch_array(db_select("select id from transaktioner where transdate >= '$startdate'"))) {
			print "<BODY onLoad=\"javascript:alert('Der er foretaget transaktioner i regnskabs&aring;ret: $r1[beskrivelse] - import afbrudt')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
			exit;
		}	
	}
	upload($kladde_id, $bilag);
}

print "</tbody></table>";
#####################################################################################################
function upload($kladde_id, $bilag){
global $font;

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"importer_kontoplan.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center>$font V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "</tbody></table>";
print "</td></tr>";
}

function vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal){
global $font;

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
#print "<tr><td colspan=6 width=100% align=center>$font $filnavn</td></tr>";
print "<tr><td colspan=$cols align=center>$font<span title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'><small>Sepatatortegn</small>&nbsp;<select name=splitter>\n";
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
}

if (($filnavn)&&($splitter)&&($kontonr==1)&&($beskrivelse==1)&&($kontotype==1)) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Importer\" /></td></tr>";

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
			$felt=array();
			$kontotyper=array("H","D","S","Z","X");
			$momstyper=array("S","K","E");
			$felt = split($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$felt[$y]=trim($felt[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if (($feltnavn[$y]=='Kontonr')&&(($felt[$y]!=$felt[$y]*1)||(in_array($felt[$y],$kontonumre)))) {
					$skriv_linje=2;
					print "<BODY onLoad=\"javascript:alert('Røde linjer indeholder fejl og bliver ikke importeret')\">";
#					print "<BODY onLoad=\"javascript:alert('Kontonrnummer skal v&aelig;re numerisk')\">";
				} elseif ($feltnavn[$y]=='Kontonr') $kontonumre[$x]=$felt[$y];
				if ($feltnavn[$y]=='Kontotype') {
					if (!in_array($felt[$y],$kontotyper)) {
					$skriv_linje=2;
					print "<BODY onLoad=\"javascript:alert('Røde linjer indeholder fejl og bliver ikke importeret')\">";
#					print "<BODY onLoad=\"javascript:alert('Kontotype skal v&aelig;re H,D,S eller Z')\">";
					} else if ($felt[$y]=='Z') $sumkonto=1;
					else $sumkonto=0;
				}	
				if ($feltnavn[$y]=='Moms') {
					$a=substr($felt[$y],0,1);
					$b=substr($felt[$y],1);
					if (($felt[$y])&&((!in_array($a,$momstyper))||($b!=$b*1))) {
						$skriv_linje=2;
						print "<BODY onLoad=\"javascript:alert('Røde linjer indeholder fejl og bliver ikke importeret')\">";
#						print "<BODY onLoad=\"javascript:alert('Momstype skal begynde med S eller K efterfulgt af en numerisk vaerdi')\">";
					}				
				}
				if (($feltnavn[$y]=='Fra_kto')&&($sumkonto))  {
					if ($felt[$y]!=$felt[$y]*1) {
						$skriv_linje=2;
						print "<BODY onLoad=\"javascript:alert('Røde linjer indeholder fejl og bliver ikke importeret')\">";
#						print "<BODY onLoad=\"javascript:alert('Kontonrnummer skal v&aelig;re numerisk')\">";
					}		
				} elseif ($feltnavn[$y]=='Fra_kto') $felt[$y]='';
			}
 		}
 				
		if ($skriv_linje>=1){
			print "<tr>";
#			print "<tr><td>$font<small>$bilag</td>";
			if ($skriv_linje==2) $tmpfnt="<FONT COLOR=\"#e00000\">".$font;
			else $tmpfnt=$font;
			for ($y=0; $y<=$feltantal; $y++) {
				if ($feltnavn[$y]) {print "<td>$tmpfnt<small>$felt[$y]&nbsp;</small></td>";}
				else {print "<td align=center>$tmpfnt<small style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</small></td>";}
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
global $font;

$r1=db_fetch_array(db_select("select max(kodenr) as kodenr from grupper where art='RA'"));
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
}

print "<tr><td colspan=$cols><hr></td></tr>\n";
if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
elseif ($splitter=='Komma') {$splitter=',';}
elseif ($splitter=='Tabulator') {$splitter=chr(9);}

# print "<tr><td><span title='Angiv 1. bilagsnummer'><input type=text size=4 name=bilag value=$bilag></span></td>";

transaktion('begin');
 #echo "delete from kontoplan where regnskabsaar='$regnskabsaar'<br>";
db_modify("delete from kontoplan where regnskabsaar='$regnskabsaar'");

$fp=fopen("$filnavn","r");
if ($fp) {
	$kontonumre=array();
	$x=0;
	while (!feof($fp)) {
		$skriv_linje=0;
		if ($linje=trim(fgets($fp))) {
			$x++;
			$skriv_linje=1;
			$felt=array();
			$kontotyper=array("H","D","S","Z");
			$momstyper=array("S","K");
			$felt = split($splitter, $linje);
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
					if ($felt[$y]!=$felt[$y]*1) {
						$skriv_linje=2;
					}		
				} elseif ($feltnavn[$y]=='fra_kto') $felt[$y]='0';
			}
 		}		
		if ($skriv_linje==1){
			$a='';
			$b='';
			for ($y=0; $y<=$feltantal; $y++) {
				if ($y>0) {
					$a=$a.",";
					$b=$b.",";
				}
				$a=$a.$feltnavn[$y];
				$b=$b."'".$felt[$y]."'";
			}
#			echo "insert into kontoplan($a, regnskabsaar) values ($b, '$regnskabsaar')";
			db_modify("insert into kontoplan($a, regnskabsaar) values ($b, '$regnskabsaar')");
			
		}
	echo "<br>";
	}	
}
 fclose($fp);
$q=db_modify("update kontoplan set til_kto=kontonr where kontotype='Z' and regnskabsaar='$regnskabsaar'");
transaktion('commit');
print "</tbody></table>";
print "</td></tr>";
print "<BODY onLoad=\"javascript:alert('Kontoplan importeret - husk at overf&oslash;re &aring;bningstal')\">";
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
	
