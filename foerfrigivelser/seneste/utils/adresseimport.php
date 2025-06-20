<?php
// -------------------------------------------systemdata/adresseimport.php------------lap 1.1.1------------------
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

$title="Importer_adresser";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/stdfunc.php.php");

print "<div align=\"center\">";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund>$font<small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
print "<td width=\"80%\" $top_bund>$font<small>$title</small></td>";
print "<td width=\"10%\" $top_bund>$font<small><br></small></td>";
print "</tbody></table>";
print "</td></tr>";

if(isset($_POST['submit'])) {
	$submit=$_POST['submit'];
	$filnavn=$_POST['filnavn'];
	$splitter=$_POST['splitter'];
	$feltnavn=$_POST['feltnavn'];
	$feltantal=$_POST['feltantal'];
	$importart=$_POST['importart'];

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) vis_data($filnavn, '', '', 1, $importart);
		else echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
	} elseif($submit=='Vis'){
		vis_data($filnavn, $splitter, $feltnavn, $feltantal, $importart);
	} elseif($submit=='Importer'){
		if (($filnavn)&&($splitter))	overfoer_data($filnavn, $splitter, $feltnavn, $feltantal);
		else vis_data($filnavn, $splitter, $feltnavn, $feltantal, $importart);
	}
} else upload();
print "</tbody></table>";
#####################################################################################################
function upload(){
global $font;

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"adresseimport.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
#print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
#print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center>$font V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input name=\"submit\" type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "</tbody></table>";
print "</td></tr>";
}

function vis_data($filnavn, $splitter, $feltnavn, $feltantal, $importart){
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
	$cols=$feltantal;
}
fclose($fp);

if ((!$splitter)||($splitter=='Semikolon')) {$split=';';}
elseif ($splitter=='Komma') {$split=',';}
elseif ($splitter=='Tabulator') {$split=chr(9);}

$fp=fopen("$filnavn","r");
if ($fp) {
	while ($linje=fgets($fp)) {
		$felt = split($split, $linje);
		for ($y=0; $y<=$feltantal; $y++) {
			$felt[$y]=trim($felt[$y]);
			if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
			$felt[$y]=trim($felt[$y]);
			if ($felt[$y]) $indhold[$y]=1;
		}
	}
}
fclose($fp);

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"adresseimport.php\" method=\"POST\">";
#print "<tr><td colspan=6 width=100% align=center>$font $filnavn</td></tr>";
print "<tr><td align=center>$font<span title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'><small>Importart</small>&nbsp;<select name=importart>\n";
if (!$importart) $importart='Begge';
print "<option>$importart</option>\n";
if ($importart!='Begge') print "<option>Begge</option>\n";
if ($importart!='Debitorer') print "<option>Debitorer</option>\n";
if ($importart!='Kreditorer') print "<option>Kreditorer</option>\n";
print "</select></span>";

print "<td colspan=$cols align=center>$font<span title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'><small>Separatortegn</small>&nbsp;<select name=splitter>\n";
if ($splitter) {print "<option>$splitter</option>\n";}
if ($splitter!='Semikolon') print "<option>Semikolon</option>\n";
if ($splitter!='Komma') print "<option>Komma</option>\n";
if ($splitter!='Tabulator') print "<option>Tabulator</option>\n";
print "</select></span>";
print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />";
/*
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
*/
if ($filnavn&&$splitter) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Importer\" /></td></tr>";

print "<tr><td colspan=$cols><hr></td></tr>\n";
/*
if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
elseif ($splitter=='Komma') {$splitter=',';}
elseif ($splitter=='Tabulator') {$splitter=chr(9);}
*/
# print "<tr><td><span title='Angiv 1. bilagsnummer'><input type=text size=4 name=bilag value=$bilag></span></td>";
for ($y=0; $y<=$feltantal; $y++) {
	if ($indhold[$y]){
	if ($feltnavn[$y]) print "<td><select name=feltnavn[$y]>\n";
	else  print "<td align=center><select name=feltnavn[$y]>\n";
	print "<option>$feltnavn[$y]</option>\n";
	if ($feltnavn[$y]) print "<option></option>\n";
	if ($feltnavn[$y]!='art' && $importart=='Begge') print "<option>art</option>\n";
	if ($feltnavn[$y]!='kontonr') print "<option>kontonr</option>\n";
	if ($feltnavn[$y]!='firmanavn') print "<option>firmanavn</option>\n";
	if ($feltnavn[$y]!='addr1') print "<option>addr1</option>\n";
	if ($feltnavn[$y]!='addr2') print "<option>Addr2</option>\n";
	if ($feltnavn[$y]!='postnr') print "<option>postnr</option>\n";
	if ($feltnavn[$y]!='bynavn') print "<option>bynavn</option>\n";
	if ($feltnavn[$y]!='land') print "<option>land</option>\n";
	if ($feltnavn[$y]!='tlf') print "<option>tlf</option>\n";
	if ($feltnavn[$y]!='fax') print "<option>fax</option>\n";
	if ($feltnavn[$y]!='email') print "<option>email</option>\n";
	if ($feltnavn[$y]!='web') print "<option>web</option>\n";
	if ($feltnavn[$y]!='bank_navn') print "<option>bank_navn</option>\n";
	if ($feltnavn[$y]!='bank_reg') print "<option>bank_reg</option>\n";
	if ($feltnavn[$y]!='bank_konto') print "<option>bank_konto</option>\n";
	if ($feltnavn[$y]!='rabat') print "<option>rabat</option>\n";
	if ($feltnavn[$y]!='kreditmax') print "<option>kreditmax</option>\n";
	if ($feltnavn[$y]!='betalingsbet') print "<option>betalingsbet</option>\n";
	if ($feltnavn[$y]!='betalingsdage') print "<option>betalingsdage</option>\n";
	if ($feltnavn[$y]!='cvrnr') print "<option>cvrnr</option>\n";
	if ($feltnavn[$y]!='ean') print "<option>ean</option>\n";
	if ($feltnavn[$y]!='institution') print "<option>institution</option>\n";
	if ($feltnavn[$y]!='gruppe') print "<option>gruppe</option>\n";
	if ($feltnavn[$y]!='kontoansvarlig') print "<option>kontoansvarlig</option>\n";
	if ($feltnavn[$y]!='oprettet') print "<option>oprettet</option>\n";
	if ($feltnavn[$y]!='kontaktet') print "<option>kontaktet</option>\n";
	if ($feltnavn[$y]!='kontaktes') print "<option>kontaktes</option>\n";
	if ($feltnavn[$y]!='notes') print "<option>notes</option>\n";
	if ($feltnavn[$y]!='kontakt_navn') print "<option>kontakt_navn</option>\n";
	if ($feltnavn[$y]!='kontakt_addr1') print "<option>kontakt_addr1</option>\n";
	if ($feltnavn[$y]!='kontakt_addr2') print "<option>kontakt_addr2</option>\n";
	if ($feltnavn[$y]!='kontakt_postnr') print "<option>kontakt_postnr</option>\n";
	if ($feltnavn[$y]!='kontakt_bynavn') print "<option>kontakt_bynavn</option>\n";
	if ($feltnavn[$y]!='kontakt_tlf') print "<option>kontakt_tlf</option>\n";
	if ($feltnavn[$y]!='kontakt_fax') print "<option>kontakt_fax</option>\n";
	if ($feltnavn[$y]!='kontakt_mobil') print "<option>kontakt_mobil</option>\n";
	if ($feltnavn[$y]!='kontakt_privattlf') print "<option>kontakt_privattlf</option>\n";
	if ($feltnavn[$y]!='kontakt_email') print "<option>kontakt_email</option>\n";
	if ($feltnavn[$y]!='kontakt_notes') print "<option>kontakt_notes</option>\n";
	
	}
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
			$felt = split($split, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$felt[$y]=trim($felt[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				$tmp=$felt[$y]*1;
				if ($feltnavn[$y]=='kontonr' && !$tmp) {
					$skriv_linje=2;
					$alerttext='R&oslash;de linjer indeholder fejl og bliver ikke importeret\r\n(Kontonrnummer skal v&aelig;re numerisk)';
					print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
#					print "<BODY onLoad=\"javascript:alert('Kontonrnummer skal v&aelig;re numerisk')\">";
				} elseif ($feltnavn[$y]=='kontonr') $kontonumre[$x]=$felt[$y];
			}
 		}
 				
		if ($skriv_linje>=1){
			print "<tr>";
#			print "<tr><td>$font<small>$bilag</td>";
			if ($skriv_linje==2) $tmpfnt="<FONT COLOR=\"#e00000\">".$font;
			else $tmpfnt=$font;
			for ($y=0; $y<=$feltantal; $y++) {
				if ($indhold[$y]){
				if ($feltnavn[$y]) {print "<td>$tmpfnt<small>$felt[$y]&nbsp;</small></td>";}
				else {print "<td align=center>$tmpfnt<small style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</small></td>";}
				}}
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
global $importart;
$importart=substr($importart,0,1);

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

print "<tr><td colspan=$cols><hr></td></tr>\n";
if ((!$splitter)||($split=='Semikolon')) {$splitter=';';}
elseif ($splitter=='Komma') {$split=',';}
elseif ($splitter=='Tabulator') {$split=chr(9);}

$fp=fopen("$filnavn","r");
if ($fp) {
	while ($linje=fgets($fp)) {
		$felt = split($split, $linje);
		for ($y=0; $y<=$feltantal; $y++) {
			$felt[$y]=trim($felt[$y]);
			if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
			$felt[$y]=trim($felt[$y]);
			if ($felt[$y]) $indhold[$y]=1;
		}
	}
}
fclose($fp);

# print "<tr><td><span title='Angiv 1. bilagsnummer'><input type=text size=4 name=bilag value=$bilag></span></td>";

			
if ($importart == "K" || $importart == "D") {
	$feltantal++;

}

transaktion('begin');

$fp=fopen("$filnavn","r");
if ($fp) {
	$antal=array();
	
	$x=0;
	while ($linje=fgets($fp)) {
		$skriv_linje=1;
		$felt = split($split, $linje);
		$feltnavn[$feltantal]='art';
			$felt[$feltantal]=$importart;
			$indhold[$feltantal]=1;
			$a=''; $b=''; $c=''; $d=''; $au=''; $cu='';
			$kontonummer=''; $kontakt_navn=''; $notes='';
			for ($y=0; $y<=$feltantal; $y++) {
			if (!$feltnavn[$y]) $indhold[$y]=0;
			$felt[$y]=trim($felt[$y]);
			if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
			$felt[$y]=trim($felt[$y]);
#			if ($felt[$y]) $indhold[$y]=1;
			$tmp=$felt[$y]*1;
			if ($feltnavn[$y]=='kontonr' && !$tmp) $skriv_linje=0; 
			if ($skriv_linje==1){
				$antal[$y]++;
#					$felt[$y]=trim($felt[$y]);
					if ($feltnavn[$y]=='kontonr') $kontonummer=$felt[$y];
					if ($feltnavn[$y]=='kontakt_navn' && $felt[$y]) $kontakt_navn=$felt[$y];
					if ($feltnavn[$y]=='betalingsbet' && !$felt[$y]) $felt[$y]="Netto";
					if ($feltnavn[$y]=='betalingsdage' && !$felt[$y]) $felt[$y]=14;
					if ($feltnavn[$y]=='kreditmax') $felt[$y]=$felt[$y]*1;
					if ($feltnavn[$y]=='notes' && $felt[$y]) {
						if ($notes) $notes=$notes."\n".$felt[$y];
						else $notes=$felt[$y];
						$felt[$y]='';
						$indhold[$y]=0;
					}
					if ($indhold[$y]) {
						if ($y>=0 && !strstr($feltnavn[$y],'kontakt')) {
							if ($a) $a=$a.",";
							if ($b) $b=$b.",";
							if ($au) $au=$au.",";
							$a=$a.$feltnavn[$y];
							$b=$b."'".$felt[$y]."'";
							$au=$au.$feltnavn[$y]."='".$felt[$y]."'";
						} elseif ($y>=0 && $felt[$y]) {
							if ($c) $c=$c.",";
							if ($d) $d=$d.",";
							if ($cu) $cu=$cu.",";
							list($null,$tmp)=split("_",$feltnavn[$y]);
							$c=$c.$tmp;
							$d=$d."'".$felt[$y]."'";
							$cu=$cu.$tmp."='".$felt[$y]."'";
						}
					}
				}
			} 
			if ($notes) {
				if ($a) $a=$a.",notes";
				else $a="notes";
				if ($b) $b=$b.",'$notes'";
				else $b="'notes'";
				if ($au) $au=$au.",notes='$notes'";
				else $au ="notes='$notes'";
			}
			if ($kontonummer) {
#			echo "select id from adresser where kontonr='$kontonummer'<br>";
				if ($importart == "D" || $importart == 'K') $kontoart=" and art = '$importart'";
				else $kontoart='';
				if ($r=db_fetch_array(db_select("select id from adresser where kontonr='$kontonummer' $kontoart"))) {
					$konto_id=$r['id'];
					db_modify("update adresser set $au where id = '$konto_id'");
				} else {
					db_modify("insert into adresser($a) values ($b)");
					$r=db_fetch_array(db_select("select id from adresser where kontonr='$kontonummer' $kontoart"));
					$konto_id=$r['id'];
				}
				if ($kontakt_navn && $konto_id) {
					$q=db_select("select id from ansatte where navn='$kontakt_navn' and konto_id='$r[id]'");
					if ($r=db_fetch_array($q)) {
						db_modify("update ansatte set $cu where id = '$r[id]'");
					} else {
						db_modify("insert into ansatte($c,konto_id) values ($d,'$konto_id')");	
					}
				}
		}
	}	
}

fclose($fp);
transaktion('commit');
print "</tbody></table>";
print "</td></tr>";
print "<BODY onLoad=\"javascript:alert('$antal[0] adresser importeret/opdateret')\">";
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
	
