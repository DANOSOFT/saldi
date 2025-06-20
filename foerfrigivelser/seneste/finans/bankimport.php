<?php
// -------------------------------------------finans/bankimport.php------------patch 1.1.2------20070801-----------
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
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$title="SALDI - Bankimport";
if ((!$sqhost)||(!$dbuser)||(!$db)){
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/settings.php");
	include("../includes/dkdato.php");
	include("../includes/usdate.php");
	include("../includes/dkdecimal.php");
	include("../includes/usdecimal.php");
	include("../includes/db_query.php");
}
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
		$kontonr=$_POST['kontonr'];
		$bilag=$_POST['bilag'];
	}
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>Luk</a></small></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Bankimport (Kassekladde $kladde_id)</small></td>";
	print "<td width=\"10%\" $top_bund ><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"></td>";
	print "</tbody></table>";
	print "</td></tr>";

	if (($kontonr) && (strlen($kontonr)==1)) {
		$kontonr=strtoupper($kontonr);
		$query = db_select("select * from kontoplan where genvej='$kontonr' and regnskabsaar='$regnaar'");
		if ($row = db_fetch_array($query)) $kontonr=$row[kontonr];
		else {
			$kontonr='';
			print "<BODY onLoad=\"javascript:alert('Angivet kontonrnummer findes ikke')\">";
		}
	}
	elseif ($kontonr)	 {
		$tmp=$kontonr*1;
		if (!$row=db_fetch_array(db_select("select id from kontoplan where kontonr=$tmp"))) {
			print "<BODY onLoad=\"javascript:alert('Kontonummer $kontonr findes ikke i kontoplanen')\">";
			$submit='Vis';
		}
	}
	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
		vis_data($kladde_id, $filnavn, '', '', 1, $kontonr, $bilag);
		}
		else{
			echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
		}
	}
	elseif($submit=='Vis'){
		vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag);
	}
	elseif($submit=='Flyt'){
		if (($kladde_id)&&($filnavn)&&($splitter)&&($kontonr))	flyt_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag);
		else vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag);
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
global $font;

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"bankimport.php\" method=\"POST\">";
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

function vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag){
global $font;

$fp=fopen("$filnavn","r");
if ($fp) {
	for ($y=1; $y<4; $y++) $linje=fgets($fp);
	$tmp=$linje;
	while ($tmp=substr(strstr($tmp,";"),1)) {
$semikolon++;
}
	$tmp=$linje;
	while ($tmp=substr(strstr($tmp,","),1)) {$komma++;}
	$tmp=$linje;
	while ($tmp=substr(strstr($tmp,chr(9)),1)) {$tabulator++;}
	$tmp='';
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
print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"bankimport.php\" method=\"POST\">";
#print "<tr><td colspan=6 width=100% align=center>$font $filnavn</td></tr>";
print "<tr><td colspan=$cols align=center>$font<span title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'><small>Separatortegn</small>&nbsp;<select name=splitter>\n";
if ($splitter) {print "<option>$splitter</option>\n";}
if ($splitter!='Semikolon') print "<option>Semikolon</option>\n";
if ($splitter!='Komma') print "<option>Komma</option>\n";
if ($splitter!='Tabulator') print "<option>Tabulator</option>\n";
print "</select></span>";
print "&nbsp;<small><span title='Angiv hvilket kontonummer der skal anvendes til posteringer'>Posteringskonto&nbsp;<input type=text size=8 name=kontonr value=$kontonr></span></small>&nbsp;";
print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />";
if (($kladde_id)&&($filnavn)&&($splitter)&&($kontonr)) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Flyt\" /></td></tr>";
print "<tr><td colspan=$cols><hr></td></tr>\n";
if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
elseif ($splitter=='Komma') {$splitter=',';}
elseif ($splitter=='Tabulator') {$splitter=chr(9);}

print "<tr><td><span title='Angiv 1. bilagsnummer'><input type=text size=4 name=bilag value=$bilag></span></td>";
for ($y=0; $y<=$feltantal; $y++) {
	if (($feltnavn[$y]=='Dato') &&($dato==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Dato')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='Beskrivelse') &&($beskr==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Beskrivelse')\">";
		$feltnavn[$y]='';
	}
	if ((strstr($feltnavn[$y],'Bel'))&&($belob==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Bel&oslash;b')\">";
		$feltnavn[$y]='';
	}
	if (strstr($feltnavn[$y],'Bel')) print "<td align=right><select name=feltnavn[$y]>\n";
	elseif ($feltnavn[$y]) print "<td><select name=feltnavn[$y]>\n";
	else  print "<td align=center><select name=feltnavn[$y]>\n";
	print "<option>$feltnavn[$y]</option>\n";
	if ($feltnavn[$y]) print "<option></option>\n";
	if ($feltnavn[$y]!='Dato') print "<option>Dato</option>\n";
	else $dato=1;
	if ($feltnavn[$y]!='Beskrivelse') print "<option>Beskrivelse</option>\n";
	else $beskr=1;
	if (!strstr($feltnavn[$y],'Bel')) print "<option>Bel&oslash;b</option>\n";
	else $belob=1;
	print "</select>";
}
print "</form>";
$fp=fopen("$filnavn","r");
if ($fp) {
	$x=0;
	while (!feof($fp)) {
		$skriv_linje=0;
		if ($linje=trim(fgets($fp))) {
			$x++;
			$skriv_linje=1;
			$felt=array();
			$felt = split($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$felt[$y]=trim($felt[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='Dato') $felt[$y]=str_replace(".","-",$felt[$y]);
				if (strstr($feltnavn[$y],'Bel')) {
					if (nummertjek($felt[$y])=='US') $felt[$y]=dkdecimal($felt[$y]);
					elseif (nummertjek($felt[$y])!='DK') $skriv_linje=0;		
				}
			}
 		}		
		if ($skriv_linje==1){
			print "<tr><td>$font<small>$bilag</td>";
			for ($y=0; $y<=$feltantal; $y++) {
				if (strstr($feltnavn[$y],'Bel')) {
					print "<td align=right>$font<small>$felt[$y]&nbsp;</small></td>";
				}
				elseif ($feltnavn[$y]) {print "<td>$font<small>$felt[$y]&nbsp;</small></td>";}
				else {print "<td align=center>$font<small style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</small></td>";}
			}
			print "</tr>";
			$bilag++;
		}
	}	
}
 fclose($fp);
print "</tbody></table>";
print "</td></tr>";
}

function flyt_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $kontonr, $bilag){
	global $font;

	transaktion('begin');

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

	if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
	elseif ($splitter=='Komma') {$splitter=',';}
	elseif ($splitter=='Tabulator') {$splitter=chr(9);}

	$fp=fopen("$filnavn","r");
	if ($fp) {
		$x=0;
		while (!feof($fp)) {
			$skriv_linje=0;
			if ($linje=trim(fgets($fp))) {
				$x++;
				$skriv_linje=1;
				$felt=array();
				$felt = split($splitter, $linje);
				for ($y=0; $y<=$feltantal; $y++) {
					$felt[$y]=trim($felt[$y]);
					if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
					if ($feltnavn[$y]=='Dato') $felt[$y]=str_replace(".","-",$felt[$y]);
					if (strstr($feltnavn[$y],'Bel')) {
						if (nummertjek($felt[$y])=='US') $felt[$y]=dkdecimal($felt[$y]);
						elseif (nummertjek($felt[$y])!='DK') $skriv_linje=0;		
					}
				}
 			}		
			if ($skriv_linje==1){
				for ($y=0; $y<=$feltantal; $y++) {
					$bilag=$bilag*1;
					if (strstr($feltnavn[$y],'Bel')) $amount=usdecimal($felt[$y]);
					elseif ($feltnavn[$y]=="Dato") $transdate=usdate($felt[$y]);
					elseif ($feltnavn[$y]=="Beskrivelse") 	$beskrivelse=addslashes($felt[$y]);
				}
				if ($amount>=0) db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, amount, kladde_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$kontonr', '$amount', '$kladde_id')");
				elseif ($amount<0) {
					$amount=$amount*-1;
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, amount, kladde_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$kontonr', '$amount', '$kladde_id')");
				}
				$bilag++;
			}
		}
	}	
	fclose($fp);
	unlink($filnavn); # sletter filen.
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
	
