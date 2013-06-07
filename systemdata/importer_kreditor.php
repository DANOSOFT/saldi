<?php
// ------systemdata/importer_kreditor.php---lap 2.1.9--2010-04-28--------
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Importer_kreditorer";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
if ($popup) print "<td width=\"10%\" $top_bund><a href=../includes/luk.php accesskey=L>Luk</a></td>"; 
else print "<td width=\"10%\" $top_bund><a href=diverse.php?sektion=div_io accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>$title</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";

if($_POST) {
	$submit=$_POST['submit'];
	if (strstr($submit, "Import")) $submit="Importer";
	$filnavn=$_POST['filnavn'];
	$splitter=$_POST['splitter'];
	$feltnavn=$_POST['feltnavn'];
	$feltantal=$_POST['feltantal'];
	$kontonr=$_POST['kontonr'];
	$bilag=$_POST['bilag'];

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			vis_data($filnavn, '', '', 1, $kontonr, $bilag);
		} else echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
	} elseif($submit=='Vis'){
		vis_data($filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag);
	}	elseif($submit=='Importer'){
		if (($filnavn)&&($splitter))	overfoer_data($filnavn, $splitter, $feltnavn, $feltantal);
		else vis_data($filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag);
	}
} else {
	if (!$r1=db_fetch_array(db_select("select box1, box2, beskrivelse from grupper where art='RA' order by kodenr desc",__FILE__ . " linje " . __LINE__))) {
		exit;
	}
	upload($bilag);
}

print "</tbody></table>";
#####################################################################################################
function upload($bilag){

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"importer_kreditor.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"900000\">";
print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center> V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "</tbody></table>";
print "</td></tr>";
}

function vis_data($filnavn, $splitter, $feltnavn, $feltantal){
global $charset;

$fp=fopen("$filnavn","r");
if ($fp) {
	for ($y=1; $y<4; $y++) $linje=fgets($fp);#korer frem til linje nr. 4.
	if ($charset=='UTF-8') $linje=utf8_encode($linje);
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
$tmp='';
if ($feltnavn) {
	for ($y=0; $y<=$feltantal; $y++) {
 		if ($tmp) $tmp=$tmp.";".$feltnavn[$y];
		else $tmp=$feltnavn[$y];
	}
	setcookie("saldi_debimp",$tmp,time()+60*60*24*30);
} elseif (isset($_COOKIE['saldi_debimp'])) {
	$tmp = $_COOKIE['saldi_debimp'];
	$feltnavn=explode(";",$tmp);
}
print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"importer_kreditor.php\" method=\"POST\">";
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

$felt_navn=array("kontonr","firmanavn","addr1","addr2","postnr","bynavn","land","kontakt","tlf","fax","email","web","notes","kreditmax","betalingsbet","betalingsdage","cvrnr","ean","institution","gruppe","kontoansvarlig","oprettet","felt_1","felt_2","felt_3","felt_4","felt_5","bank_navn","bank_reg","bank_konto","bank_fi","swift","ean","institution","kontakt_navn","kontakt_addr1","kontakt_addr2","kontakt_postnr","kontakt_bynavn","kontakt_tlf","kontakt_fax","kontakt_email","kontakt_notes");
$felt_antal=30;
for ($y=0; $y<=$feltantal; $y++) {
	for ($x=0; $x<=$felt_antal; $x++) {
		if ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x] && $felt_aktiv[$x]==1) {
			print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med $felt_navn[$x]')\">";
			$feltnavn[$y]='';
		} elseif ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x]) $felt_aktiv[$x]=1;
	}
	if ($feltnavn[$y]=='kontonr')$kontonr=1;
	if ($feltnavn[$y]=='firmanavn')$firmanavn=1;
}		
if (($filnavn)&&($splitter)&&($kontonr==1)&&($firmanavn==1)) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Import&eacute;r\" /></td></tr>";
print "<tr><td colspan=$cols><hr></td></tr>\n";
if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
elseif ($splitter=='Komma') {$splitter=',';}
elseif ($splitter=='Tabulator') {$splitter=chr(9);}
for ($y=0; $y<=$feltantal; $y++) {
	if ($feltnavn[$y]) print "<td><select name=feltnavn[$y]>\n";
	else  print "<td align=center><select name=feltnavn[$y]>\n";
	print "<option>$feltnavn[$y]</option>\n";
	if ($feltnavn[$y]) print "<option></option>\n";
	for ($x=0; $x<=$felt_antal; $x++) {
		if ($feltnavn[$y]!=$felt_navn[$x]) print "<option>$felt_navn[$x]</option>\n";
	}
	print "</td>";
}
print "</form></td></tr>";
$fp=fopen("$filnavn","r");
if ($fp) {
	$x=0;
	$kontonumre=array();
	while (!feof($fp)) {
		$skriv_linje=0;
		if ($linje=fgets($fp)) {
			$x++;
#echo "$x | $linje<br>";		
			$skriv_linje=1;
			if ($charset=='UTF-8') $linje=utf8_encode($linje);
			$felt=array();
			$felt = opdel($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$felt[$y]=trim($felt[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='kontonr'&&!is_numeric($felt[$y])) {
					$skriv_linje=2;
					print "<BODY onLoad=\"javascript:alert('R&oslash;de linjer indeholder fejl (kontonummer ikke numerisk) og bliver ikke importeret')\">";
#					print "<BODY onLoad=\"javascript:alert('Kontonrnummer skal v&aelig;re numerisk')\">";
				} 
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

$x=0;
$fp=fopen("../importfiler/postnr.csv","r");
if ($fp) {
	while (!feof($fp)) {
		$x++;
		$linje=trim(fgets($fp));
		list($postnr[$x],$bynavn[$x])=split(chr(9),$linje);
	}
} 
fclose($fp);
$postnr_antal=$x;

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
	for ($x=0; $x<=$felt_antal; $x++) {
		if ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x]&& $felt_aktiv[$x]==1) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med $felt_navn[$x]')\">";
		$feltnavn[$y]='';
		} elseif ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x]) $felt_aktiv[$x]=1;
	}
}

print "<tr><td colspan=$cols><hr></td></tr>\n";
if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
elseif ($splitter=='Komma') {$splitter=',';}
elseif ($splitter=='Tabulator') {$splitter=chr(9);}

# print "<tr><td><span title='Angiv 1. bilagsnummer'><input type=text size=4 name=bilag value=$bilag></span></td>";

transaktion('begin');
 #echo "delete from kontoplan where regnskabsaar='$regnskabsaar'<br>";
#db_modify("delete from de where regnskabsaar='$regnskabsaar'");

$fp=fopen("$filnavn","r");
if ($fp) {
	$kontonumre=array();
	$x=0;
	$imp_antal=0;
	while (!feof($fp)) {
		$skriv_linje=0;
		if ($linje=trim(fgets($fp))) {
			$x++;
			$skriv_linje=1;
			if ($charset=='UTF-8') $linje=utf8_encode($linje);
			$felt=array();
			$felt = opdel($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$felt[$y]=trim($felt[$y]);
				$feltnavn[$y]=strtolower($feltnavn[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='kontonr'&&!is_numeric($felt[$y])) {
					$kontonr=0;
					$skriv_linje=2;
#					print "<BODY onLoad=\"javascript:alert('R&oslash;de linjer indeholder fejl (kontonummer ikke numerisk) og bliver ikke importeret')\">";
#					print "<BODY onLoad=\"javascript:alert('Kontonrnummer skal v&aelig;re numerisk')\">";
				} elseif ($feltnavn[$y]=='kontonr') $kontonr=$felt[$y];
				if ($feltnavn[$y]=="postnr") list($felt[$y],$bynavn[$y]) = split(" ",$felt[$y],2);
				if ($feltnavn[$y]=='kontoansvarlig'&&$felt[$y]&&$kontonr){
					$r=db_fetch_array(db_select("select id from adresser where kontonr='$kontonr'",__FILE__ . " linje " . __LINE__));
					$konto_id=$r['id']*1;
					$r=db_fetch_array(db_select("select id from ansatte where initialer='$felt[$y]' and konto_id=$konto_id",__FILE__ . " linje " . __LINE__));
					$felt[$y]=$r['id']*1;
				} elseif ($feltnavn[$y]=='kontoansvarlig') $felt[$y]='0';
				if ($feltnavn[$y]=='oprettet'&&$felt[$y]&&$kontonr){
					$felt[$y]=usdate($felt[$y]);
				} elseif ($feltnavn[$y]=='oprettet') $felt[$y]=date("Y-m-d");
				if ($feltnavn[$y]=='kreditmax')	$felt[$y]=usdecimal($felt[$y]);
				if ($feltnavn[$y]=='betalingsdage')	$felt[$y]=$felt[$y]*1;
			}
 		}		
		if ($skriv_linje==1){
			$addr_a='';
			$addr_b='';
			$kontakt_a='';
			$kontakt_b='';
			for ($y=0; $y<=$feltantal; $y++) {
				if ($feltnavn[$y]) {
				$felt[$y]=addslashes($felt[$y]);
				if (!strstr($feltnavn[$y],"kontakt_")) {
					if ($y>0) {
						$addr_a=$addr_a.",";
						$addr_b=$addr_b.",";
					}
					$addr_a=$addr_a.$feltnavn[$y];
					$addr_b=$addr_b."'".$felt[$y]."'";
				} else {
					if ($kontakt_a) {
						$kontakt_a=$kontakt_a.",";
						$kontakt_b=$kontakt_b.",";
					}
					$tmp=substr($feltnavn[$y],8);
					$kontakt_a=$kontakt_a.$tmp;
					$kontakt_b=$kontakt_b."'".$felt[$y]."'";
				}
				}}
			if ($r=db_fetch_array(db_select("select id from adresser where kontonr='$kontonr'",__FILE__ . " linje " . __LINE__))) {
				$imp_antal++;
				db_modify("delete from adresser where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
				db_modify("insert into adresser(id,$addr_a,art) values ($r[id],$addr_b,'K')",__FILE__ . " linje " . __LINE__);
			} else {
				$imp_antal++;
				db_modify("insert into adresser($addr_a, art) values ($addr_b, 'K')",__FILE__ . " linje " . __LINE__);
			}	
			$r=db_fetch_array(db_select("select id from adresser where kontonr='$kontonr' and art = 'K'",__FILE__ . " linje " . __LINE__));
			$konto_id=$r['id'];
			if ($kontakt_a && !$r=db_fetch_array(db_select("select id from ansatte where konto_id='$konto_id'",__FILE__ . " linje " . __LINE__))){
				db_modify("insert into ansatte($kontakt_a, konto_id) values ($kontakt_b, '$konto_id')",__FILE__ . " linje " . __LINE__);
			}
		}
	}	
}
 fclose($fp);
transaktion('commit');
print "</tbody></table>";
print "</td></tr>";
print "<BODY onLoad=\"javascript:alert('$imp_antal adresser importeret')\">";
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
	return $retur=chr(32);
}
function opdel ($splitter,$linje){
	global $feltantal;
	$anftegn=0;	
	$x=0;
	$y=0;

	if (substr($linje,0,1)==chr(32)) {
		$anftegn=1;
		$x++;	
 }
	for($z=$x;$z<=strlen($linje);$z++) {
		if (!$anftegn && substr($linje,$z-1,1)==$splitter && substr($linje,$z,1)==chr(32)) {
			$anftegn=1;
 		}
		if ($anftegn && substr($linje,$z,1)==chr(32) && substr($linje,$z+1,1)==$splitter) {
#			echo "$y A $var[$y]<br>";
			$y++;
			$z++;
			$anftegn=0;
		} elseif (!$anftegn && substr($linje,$z,1)==$splitter) {
#			echo "$y B $var[$y]<br>";
			$y++;
		} else {
			$var[$y]=$var[$y].substr($linje,$z,1);
		}
	}
	return $var;
}
