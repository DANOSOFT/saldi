<?php
// ----------finans/importer.php------------patch 3.4.8-----2015.01.05-----------
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
//
// 20130415 - Fejl hvis linje starter med separatortegn.
// 20150105 - Transdate sættes til dd hvis den ikke er sat20150105

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Import til kassekladde";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

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
#		$kontonr=$_POST['kontonr'];
		$bilag=$_POST['bilag'];
		$datoformat=$_POST['datoformat'];
	}
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Importer til kassekladde (Kassekladde $kladde_id)</td>";
	print "<td width=\"10%\" $top_bund ><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"></td>";
	print "</tbody></table>";
	print "</td></tr>";

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
		$feltnavn=array();
		if ($r=db_fetch_array(db_select("select * from grupper where ART = 'KASKL' and kode='2' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__))) {
			$feltantal=if_isset($r['box1']);
			$feltnavn=explode(",",$r['box2']);
			$datoformat=$r['box3'];
			#box14 bruges at pbswebimport.php
		} else {
			db_modify ("insert into grupper (beskrivelse,art,kode,kodenr) values ('Kassekladdeimport','KASKL','2','$bruger_id')",__FILE__ . " linje " . __LINE__);
		}
		if (!$feltantal) $feltantal=1;	
			if (in_array('bilag',$feltnavn)) vis_data($kladde_id, $filnavn, '', $feltnavn, $feltantal, 0,$datoformat);
			else vis_data($kladde_id, $filnavn, '', $feltnavn, $feltantal, $bilag,$datoformat);
		}
		else{
			echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
		}
	}
	elseif($submit=='Vis'){
		if (in_array('bilag',$feltnavn)) vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, 0,$datoformat);
		else vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $bilag,$datoformat);
	}
	elseif($submit=='Flyt'){
		if (($kladde_id)&&($filnavn)&&($splitter))	{
			if (in_array('bilag',$feltnavn)) flyt_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, 0,$datoformat);
			else flyt_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $bilag,$datoformat);
		}
		else {
			if (in_array('bilag',$feltnavn)) vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, 0,$datoformat);
			else vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $bilag,$datoformat);
		}
	}
	else {
		if (in_array('bilag',$feltnavn)) upload($kladde_id, 0);
		else upload($kladde_id, $bilag);
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
print "<tr><td width=100% align=center><b>Import af bankposteringer</b></td></tr>";
print "<tr><td width=100% align=center><br></td></tr>";
print "<form enctype=\"multipart/form-data\" action=\"bankimport.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
if ($bilag) print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center> V&aelig;lg datafil: <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td><hr width=30%></td></tr>";
print "<tr><td width=100% align=center><br></td></tr>";

$title='"';
print "<tr><td width=100% align=center title='$title'><b>Import fra Danl&oslash;n</b></td></tr>";
print "<tr><td width=100% align=center><br></td></tr>";
print "<form enctype=\"multipart/form-data\" action=\"danlonimport.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
if ($bilag) print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center title='$title'> V&aelig;lg datafil: <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td><hr width=30%></td></tr>";
print "<tr><td width=100% align=center><br></td></tr>";

$title='Import at betalingsoplysninger leverance 0602"';
print "<tr><td width=100% align=center title='$title'><b>Import af pbs betalinger M602</b></td></tr>";
print "<tr><td width=100% align=center><br></td></tr>";
print "<form enctype=\"multipart/form-data\" action=\"pbsm602import.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
if ($bilag) print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center title='$title'> V&aelig;lg datafil: <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td><hr width=30%></td></tr>";
print "<tr><td width=100% align=center><br></td></tr>";

$title='Filen skal v&aelig;re af typen "udenopkrævningsarter.csv"';
print "<tr><td width=100% align=center title='$title'><b>Import af pbs betalinger fra web</b></td></tr>";
print "<tr><td width=100% align=center><br></td></tr>";
print "<form enctype=\"multipart/form-data\" action=\"pbswebimport.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
if ($bilag) print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center title='$title'> V&aelig;lg datafil: <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td><hr width=30%></td></tr>";
print "<tr><td width=100% align=center><br></td></tr>";

print "<tr><td width=100% align=center><b>Import af andre data</b></td></tr>";
print "<tr><td width=100% align=center><br></td></tr>";
print "<form enctype=\"multipart/form-data\" action=\"importer.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
if ($bilag) print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center> V&aelig;lg datafil: <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "</tbody></table>";
print "</td></tr>";
}

function vis_data($kladde_id,$filnavn,$splitter,$feltnavn,$feltantal,$bilag,$datoformat){
global $charset;
global $bruger_id;

if (!$datoformat) $datoformat="mm-dd-yyyy";

$fp=fopen("$filnavn","r");
if ($fp) {
	for ($y=1; $y<10; $y++) {
		if ($linje=fgets($fp)) { 
			$tmp=$linje;
			while ($tmp=substr(strstr($tmp,";"),1)) $semikolon++;	
			$tmp=$linje;
			while ($tmp=substr(strstr($tmp,","),1)) $komma++;
			$tmp=$linje;
			while ($tmp=substr(strstr($tmp,chr(9)),1)) $tabulator++;
			$tmp='';
		}
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
#echo substr($linje,$x,1);

#if ($x+1==strlen($linje)) echo "<br>";
				if ($x==0 && substr($linje,$x,1)=='"') {
					$z++; $anftegn=1; $felt[$z]='';
				} elseif ($x==0 && substr($linje,$x,1)==$splittegn) { #20130415
					$felt[$z]='';
					$z++; 
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
		}
	}
}  
$linjeantal=$y;
#$cols=$feltantal;
fclose ($fp);
$fp=fopen($filnavn."2","w");
for ($y=1; $y<=$linjeantal;$y++) {
	fwrite($fp,$ny_linje[$y]);
}
fclose ($fp);
print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"importer.php\" method=\"POST\">";
#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
print "<tr><td colspan=$cols align=center>Datofotmat <input style=\"width:80px\" type=\"text\" name=\"datoformat\" value=\"$datoformat\"> ";
print "<span title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'>Separatortegn&nbsp;<select name=\"splitter\">\n";
if ($splitter) {print "<option>$splitter</option>\n";}
if ($splitter!='Semikolon') print "<option>Semikolon</option>\n";
if ($splitter!='Komma') print "<option>Komma</option>\n";
if ($splitter!='Tabulator') print "<option>Tabulator</option>\n";
print "</select></span>";
print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />";
if (($kladde_id)&&($filnavn)&&($splitter)) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Flyt\" /></td></tr>";
print "<tr><td colspan=$cols><hr></td></tr>\n";
$fil_splitter=$splitter;
#if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
#elseif ($splitter=='Komma') {$splitter=',';}
#elseif ($splitter=='Tabulator') {$splitter=chr(9);}
$splitter=chr(9);
print "<tr>";
if (!in_array('bilag',$feltnavn)) print "<td><span title='Angiv 1. bilagsnummer'><input class=\"inputbox\" type=text size=4 name=bilag value=$bilag></span></td>";
for ($y=0; $y<=$feltantal; $y++) {
	if (in_array('bilag',$feltnavn) && $feltnavn[$y]=='bilag' && $bilag==1) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Bilag')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='dato') &&($dato==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Dato')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='beskrivelse') &&($beskr==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Beskrivelse')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='debitor') &&($debitor==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Debitor')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='kreditor') &&($kreditor==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Kreditor')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='debet') &&($debet==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Debet')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='kredit') &&($kredit==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Kredit')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='fakturanr') &&($fakturanr==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Fakturanr')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='belob')&&($belob==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Bel&oslash;b')\">";
		$feltnavn[$y]='';
	}

	if ($feltnavn[$y]=='bilag' || $feltnavn[$y]=='belob') print "<td align=right><select name=\"feltnavn[$y]\">\n";
#	elseif (!$feltnavn[$y]) print "<td><select>\n";
	else  print "<td align=center><select name=\"feltnavn[$y]\">\n";
	if (!$feltnavn[$y]) print "<option></option>\n";
	elseif ($feltnavn[$y]=='bilag') print "<option \"align=right\" value='bilag'>Bilag</option>\n";
	elseif ($feltnavn[$y]=='dato') print "<option value='dato'>Dato</option>\n";
	elseif ($feltnavn[$y]=='beskrivelse') print "<option value='beskrivelse'>Beskrivelse</option>\n";
	elseif ($feltnavn[$y]=='debet') print "<option value='debet'>Debet</option>\n";
	elseif ($feltnavn[$y]=='kredit') print "<option value='kredit'>Kredit</option>\n";
	elseif ($feltnavn[$y]=='debitor') print "<option value='debitor'>Debitor</option>\n";
	elseif ($feltnavn[$y]=='kreditor') print "<option value='kreditor'>Kreditor</option>\n";
	elseif ($feltnavn[$y]=='fakturanr') print "<option value='fakturanr'>Fakturanr</option>\n";
	elseif ($feltnavn[$y]=='belob') print "<option \"align=right\" value='belob'>Bel&oslash;b</option>\n";

 	if ($feltnavn[$y]!='bilag') print "<option value='bilag'>Bilag</option>\n";
	elseif (in_array('bilag',$feltnavn)) $bilag=1;
	if ($feltnavn[$y]!='dato') print "<option value='dato'>Dato</option>\n";
	else $dato=1;
	if ($feltnavn[$y]!='beskrivelse') print "<option value='beskrivelse'>Beskrivelse</option>\n";
	else $beskr=1;
	if ($feltnavn[$y]!='debet') print "<option value='debet'>Debet</option>\n";
	else $debet=1;
	if ($feltnavn[$y]!='kredit') print "<option value='kredit'>Kredit</option>\n";
	else $kredit=1;
	if ($feltnavn[$y]!='debitor') print "<option value='debitor'>Debitor</option>\n";
	else $debitor=1;
	if ($feltnavn[$y]!='kreditor') print "<option value='kreditor'>Kreditor</option>\n";
	else $kreditor=1;
	if ($feltnavn[$y]!='fakturanr') print "<option value='fakturanr'>Fakturanr</option>\n";
	else $fakturanr=1;
	if ($feltnavn[$y]!='belob') print "<option value='belob'>Bel&oslash;b</option>\n";
	else $belob=1;
	if ($feltnavn[$y]) print "<option></option>\n";
	print "</select></td>";
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
				if ($feltnavn[$y]=='dato') $felt[$y]=str_replace(".","-",$felt[$y]);
				if ($feltnavn[$y]=='belob') {
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
			print "<tr>";
			if (!in_array('bilag',$feltnavn)) print "<td>$bilag</td>";
			for ($y=0; $y<=$feltantal; $y++) {
				if ($feltnavn[$y]=='dato') $felt[$y]=datotjek($datoformat,$felt[$y]);
				if ($feltnavn[$y]=='bilag' || $feltnavn[$y]=='belob') {
					print "<td align=right>$felt[$y]&nbsp;</td>";
				}
				elseif ($feltnavn[$y]) {print "<td>$felt[$y]&nbsp;</td>";}
				else {print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";}
			}
			print "</tr>";
			if (!in_array('bilag',$feltnavn))	$bilag++;
		} else {
			print "<tr>";
			if (!in_array('bilag',$feltnavn)) print "<td><span style=\"color: rgb(153, 153, 153);\">-</span></td>";
			for ($y=0; $y<=$feltantal; $y++) {
#				if ($feltnavn[$y]=='dato') $felt[$y]=datotjek($datoformat,$felt[$y]);
				if ($feltnavn[$y]=='bilag' || $feltnavn[$y]=='belob') {
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
$box2=$feltnavn[0];
for ($y=1; $y<=$feltantal; $y++) $box2.=",".$feltnavn[$y];
db_modify("update grupper set box1='$feltantal',box2='$box2',box3='$datoformat' where ART='KASKL' and kode='2' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);
} # function vis_data slut;

function flyt_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $bilag,$datoformat){
	global $charset;

	transaktion('begin');
	$splitter=chr(9);
	$fp=fopen($filnavn."2","r");
	if ($fp) {
		$x=0;
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
					if ($feltnavn[$y]=='dato') $felt[$y]=datotjek($datoformat,$felt[$y]);
#					if ($feltnavn[$y]=='dato') $felt[$y]=str_replace(".","-",$felt[$y]);
					if ($feltnavn[$y]=='belob') {
						if (nummertjek($felt[$y])=='US') $felt[$y]=dkdecimal($felt[$y]);
						elseif (nummertjek($felt[$y])!='DK') $skriv_linje=0;		
					}
				}
 			}		
			if ($skriv_linje==1){
				for ($y=0; $y<=$feltantal; $y++) {
					$bilag=$bilag*1;
					if ($feltnavn[$y]=='bilag') $bilag=$felt[$y]*1;
					if ($feltnavn[$y]=='belob') $amount=usdecimal($felt[$y]);
					elseif ($feltnavn[$y]=="dato") $transdate=usdate($felt[$y]);
					elseif ($feltnavn[$y]=="beskrivelse") $beskrivelse=addslashes($felt[$y]);
					elseif ($feltnavn[$y]=="debet") {
						$d_type="F";
						$debet=$felt[$y];
					} elseif ($feltnavn[$y]=="kredit") {
						$d_type="F";
						$kredit=$felt[$y];
					} elseif ($feltnavn[$y]=="debitor") {
						$d_type="D";
						$debet=$felt[$y];
					} elseif ($feltnavn[$y]=="kreditor") {
						$k_type="K";
						$kredit=$felt[$y];
					} elseif ($feltnavn[$y]=="fakturanr") $fakturanr=addslashes($felt[$y]);
				}
				if (!$transdate) $transdate=date('Y-m-d'); #20150105
				if ($amount*1!=0) {
#					$debet=$debet*1;$kredit=$kredit*1;
					$felttext1=NULL;$felttext2=NULL;
					if (is_numeric($debet)) {
						$felttext1 = "d_type,debet,";
						$felttext2 = "'$d_type','$debet',";
					}
					if (is_numeric($kredit)) {
						$felttext1 = $felttext1."k_type,kredit,";
						$felttext2 = $felttext2."'$k_type','$kredit',";
					}
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse,$felttext1 faktura, amount, kladde_id) values ('$bilag', '$transdate', '$beskrivelse',$felttext2 '$fakturanr','$amount', '$kladde_id')",__FILE__ . " linje " . __LINE__);
					if (!in_array('bilag',$feltnavn)) $bilag++;
				}
			}
		}
	}	
	fclose($fp);
	unlink($filnavn); # sletter filen.
	unlink($filnavn."2"); # sletter filen.
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
function datotjek ($datoformat,$dato){
	$dato=trim($dato);
	$datoformat=strtolower($datoformat);
	
	$tal="01234567890";
	$tegn_a=substr($datoformat,0,1);
	$a=substr($dato,0,1);
	$b=NULL;
	$c=NULL;
	for ($i=1;$i<strlen($datoformat);$i++) {
		$tegn=substr($datoformat,$i,1);
		$datotegn=substr($dato,$i,1);
		if ($b == NULL) {
			if ($tegn==$tegn_a) $a.=$datotegn;
			elseif (strstr($tal,$datotegn)) {
				$tegn_b=$tegn;
				$b=$datotegn;
			} else $splitter=$tegn;
		} elseif ($c == NULL) {
			if ($tegn==$tegn_b) $b.=$datotegn;
				elseif (strstr($tal,$datotegn)) {
				$tegn_c=$tegn;
				$c=$datotegn;
			} else $splitter=$tegn;
		} else {
			if ($tegn==$tegn_c) $c.=$datotegn;
			else $splitter=$tegn;
		}
	}
	if ($tegn_a=='d') $dag=$a;
	elseif ($tegn_a=='m') $maaned=$a;
	else $aar=$a;
	if ($tegn_b=='d') $dag=$b;
	elseif ($tegn_b=='m') $maaned=$b;
	else $aar=$b;
	if ($tegn_c=='d') $dag=$c;
	elseif ($tegn_c=='m') $maaned=$c;
	else $aar=$c;

	return("$dag-$maaned-$aar");
}