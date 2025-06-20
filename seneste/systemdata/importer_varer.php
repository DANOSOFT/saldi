<?php
// ------systemdata/importer_varer.php---lap 3.4.3--2014-07-18--------
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
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// tilføjet vejl.pris til import.
// 2014.02.01 Gjort søgning på eksisterende varenumre case uafhængig 20140201
// 2014.07.18 Alle felter kan nu vælges v.import. Søg 20140718

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Importer_varer";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<div align=\"center\">\n";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
if ($popup) print "<td width=\"10%\" $top_bund><a href=../includes/luk.php accesskey=L>Luk</a></td>\n"; 
else print "<td width=\"10%\" $top_bund><a href=diverse.php?sektion=div_io accesskey=L>Luk</a></td>\n";
print "<td width=\"80%\" $top_bund>$title</td>\n";
print "<td width=\"10%\" $top_bund><br></td>\n";
print "</tbody></table>\n";
print "</td></tr>\n";

$submit=if_isset($_POST['submit']);

if($submit) {
	if (strstr($submit, "Import")) $submit="Importer";
	$filnavn=$_POST['filnavn'];
	$splitter=$_POST['splitter'];
	$feltnavn=$_POST['feltnavn'];
	$feltantal=$_POST['feltantal'];
	$varenr=$_POST['varenr'];
	$bilag=$_POST['bilag'];
	$tegnset=$_POST['tegnset'];

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			vis_data($filnavn,'','','1',$varenr,$bilag,$tegnset);
		} else echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
	} elseif($submit=='Vis'){
		vis_data($filnavn,$splitter,$feltnavn,$feltantal,$varenr,$bilag,$tegnset);
	}	elseif($submit=='Importer'){
		if (($filnavn)&&($splitter))	overfoer_data($filnavn,$splitter,$feltnavn,$feltantal,$tegnset);
		else vis_data($filnavn,$splitter,$feltnavn,$feltantal,$varenr,$bilag,$tegnset);
	}
} else {
	if (!$r1=db_fetch_array(db_select("select box1, box2, beskrivelse from grupper where art='RA' order by kodenr desc",__FILE__ . " linje " . __LINE__))) {
		exit;
	}
	upload($bilag);
}
print "</tbody></table>";
print "</body></html>";
#####################################################################################################
function upload($bilag){

print "<form enctype=\"multipart/form-data\" action=\"importer_varer.php\" method=\"POST\">\n";
print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"900000\">\n";
print "<input type=\"hidden\" name=\"bilag\" value=$bilag>\n";
print "<tr><td width=100% align=center> V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>\n";
print "<tr><td><br></td></tr>\n";
print "<tr><td align=center><input type=\"submit\" name=\"submit\" value=\"Hent\" /></td></tr>\n";
#print "</tbody></table>\n";
#print "</td></tr>\n";
print "<tr><td></form></td></tr>\n";
}
function vis_data($filnavn,$splitter,$feltnavn,$feltantal,$varenr,$bilag,$tegnset){
global $charset;

$fp=fopen("$filnavn","r");
if ($fp) {
	for ($y=1; $y<4; $y++) {
		$tmp=fgets($fp);
		if($tmp) $linje=$tmp;
	}
	if ($charset=='UTF-8' && $tegnset!='UTF-8') $linje=utf8_encode($linje);
	elseif ($charset!='UTF-8' && $tegnset=='UTF-8') $linje=utf8_decode($linje);
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
	setcookie("saldi_vareimp",$tmp,time()+60*60*24*30);
} elseif (isset($_COOKIE['saldi_vareimp'])) {
	$tmp = $_COOKIE['saldi_vareimp'];
	$feltnavn=explode(";",$tmp);
}
print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"importer_varer.php\" method=\"POST\">";
#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
print "<tr><td colspan=$cols align=center><span title='Angiv tegnsæt for import'>Tegnsæt<select name=\"tegnset\">\n";
if ($tegnset=='ISO-8859-1') print "<option>ISO-8859-1</option>\n";
if ($tegnset=='UTF-8') print "<option>UTF-8</option>\n";
if ($tegnset!='ISO-8859-1') print "<option>ISO-8859-1</option>\n";
if ($tegnset!='UTF-8') print "<option>UTF-8</option>\n";
print "</select></span>";
print "<span title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'>Separatortegn&nbsp;<select name=splitter>\n";
if ($splitter) {print "<option>$splitter</option>\n";}
if ($splitter!='Semikolon') print "<option>Semikolon</option>\n";
if ($splitter!='Komma') print "<option>Komma</option>\n";
if ($splitter!='Tabulator') print "<option>Tabulator</option>\n";
print "</select></span>";
print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />";

#20140718
$felt_navn=array("varenr","stregkode","beskrivelse","kostpris","salgspris","vejl.pris","notes","enhed","enhed2","forhold","gruppe","provisionsfri","leverandor","min_lager","max_lager","lokation","lukket","serienr","samlevare","delvare","trademark","	retail_price","special_price","	campaign_cost","tier_price","	open_colli_price","colli","	outer_colli","outer_colli_price","special_from_date","special_to_date","	komplementaer","circulate","operation","prisgruppe","tilbudgruppe","	rabatgruppe","dvrg","m_type","m_rabat","m_antal","folgevare","kategori","varianter","publiceret","indhold");


$felt_antal=count($felt_navn);
for ($y=0; $y<=$feltantal; $y++) {
	for ($x=0; $x<=$felt_antal; $x++) {
		if ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x] && $felt_aktiv[$x]==1) {
			print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med $felt_navn[$x]')\">";
			$feltnavn[$y]='';
		} elseif ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x]) $felt_aktiv[$x]=1;
	}
	if ($feltnavn[$y]=='varenr')$varenr=1;
	if ($feltnavn[$y]=='beskrivelse')$beskrivelse=1;
}		
if ($filnavn && $splitter && $varenr==1) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Import&eacute;r\" /></td></tr>";
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
			$skriv_linje=1;
			if ($charset=='UTF-8' && $tegnset!='UTF-8') $linje=utf8_encode($linje);
			elseif ($charset!='UTF-8' && $tegnset=='UTF-8') $linje=utf8_decode($linje);
			$felt=array();
			$felt = opdel($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$feltfejl[$y]=0;
				$felt[$y]=trim($felt[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='vejl_pris' || $feltnavn[$y]=='vejl.pris') $feltnavn[$y]='retail_price';
				if ($feltnavn[$y]=='kostpris')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='salgspris')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='retail_price')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='min_lager')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='max_lager')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='gruppe')	{
					if (!$tmp=find_varegrp($felt[$y])) $feltfejl[$y]=1;
				}
				if ($feltnavn[$y]=='leverandor')	{
					if ($felt[$y] && !$tmp=find_lev_id($felt[$y])) $feltfejl[$y]=1; 
				}
#				if ($feltnavn[$y]=='varenr'&&!is_numeric($felt[$y])) {
#					$skriv_linje=2;
#					print "<BODY onLoad=\"javascript:alert('R&oslash;de linjer indeholder fejl (kontonummer ikke numerisk) og bliver ikke importeret')\">";
#					print "<BODY onLoad=\"javascript:alert('varenrnummer skal v&aelig;re numerisk')\">";
#				} 
			}
 		}
		if ($skriv_linje==2) print "<BODY onLoad=\"javascript:alert('R&oslash;de linjer/felter indeholder fejl og bliver ikke importeret')\">";
		if ($skriv_linje>=1){
			print "<tr>";
#			print "<tr><td>$bilag</td>";
			for ($y=0; $y<=$feltantal; $y++) {
				if ($skriv_linje==2) $color="#e00000";
				elseif ($feltfejl[$y]) $color="#e00000";
				else $color="#000000";
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

function overfoer_data($filnavn,$splitter,$feltnavn,$feltantal,$charset){
global $charset;

$x=0;
$fp=fopen("../importfiler/postnr.csv","r");
if ($fp) {
	while (!feof($fp)) {
		$x++;
		$linje=fgets($fp);
		list($postnr[$x],$bynavn[$x])=explode(chr(9),$linje);
	}
} 
fclose($fp);
$postnr_antal=$x;

$fp=fopen("$filnavn","r");
if ($fp) {
	for ($y=1; $y<4; $y++) {
		$tmp=fgets($fp);
		if($tmp) $linje=$tmp;
	}
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
	$upd_antal=0;
	$kostpris=0;
	$salgspris=0;
	$varenr="";	
	while (!feof($fp)) {
		$skriv_linje=0;
		if ($linje=fgets($fp)) {
			$x++;
			$skriv_linje=1;
			if ($charset=='UTF-8' && $tegnset!='UTF-8') $linje=utf8_encode($linje);
			elseif ($charset!='UTF-8' && $tegnset=='UTF-8') $linje=utf8_decode($linje);
			$felt=array();
 			$felt = opdel($splitter, $linje);
 			for ($y=0; $y<count($felt); $y++) {
				$medtag_felt[$y]=1;
				if (!trim($feltnavn[$y])) $medtag_felt[$y]=0;
				$felt[$y]=trim($felt[$y]);
				$feltnavn[$y]=strtolower($feltnavn[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='varenr') {
					if (!$varenr=$felt[$y]) $skriv_linje=0;
				}
				if ($feltnavn[$y]=='vejl.pris') $feltnavn[$y]='retail_price';
				if ($feltnavn[$y]=='vejl.pris') $feltnavn[$y]='retail_price';
				if ($feltnavn[$y]=='lokation') $feltnavn[$y]='location';
				if ($feltnavn[$y]=='kostpris')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=0;
					elseif (!is_numeric($felt[$y])) $felt[$y]=usdecimal($felt[$y]);
					$kostpris=$felt[$y]*1;
				}
				if ($feltnavn[$y]=='salgspris')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=0;
					elseif (!is_numeric($felt[$y])) $felt[$y]=usdecimal($felt[$y]);
					$salgspris=$felt[$y]*1;
				}
				if ($feltnavn[$y]=='retail_price')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=0;
					elseif (!is_numeric($felt[$y])) $felt[$y]=usdecimal($felt[$y]);
					$retail_price=$felt[$y]*1;
				}
				if ($feltnavn[$y]=='min_lager')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
					else $felt[$y]=usdecimal($felt[$y]);
				}
				if ($feltnavn[$y]=='max_lager')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
					else $felt[$y]=usdecimal($felt[$y]);
				}
				if ($feltnavn[$y]=='gruppe')	{
					if ($tmp=find_varegrp($felt[$y])) $felt[$y]=$tmp;
					else $felt[$y]=1;
				}
				if ($feltnavn[$y]=='leverandor')	{
					if ($felt[$y] && !$tmp=find_lev_id($felt[$y]));
					$leverandor=$tmp;
					$levfelt=$y;
				}
			}
 		}
		if ($skriv_linje==1) {
			$vare_a='';
			$vare_b='';
			$upd='';
			for ($y=0; $y<=$feltantal; $y++) {
				if ($feltnavn[$y] && $medtag_felt[$y] && $feltnavn[$y]!='leverandor') {
					if ($nyt_feltnavn[$y]) $feltnavn[$y]=$nyt_feltnavn[$y];
					$felt[$y]=db_escape_string($felt[$y]);
					($vare_a)?$vare_a.=",".$feltnavn[$y]:$vare_a=$feltnavn[$y];
					($vare_b)?$vare_b.=",'".$felt[$y]."'":$vare_b="'".$felt[$y]."'";
					($upd)?$upd.=",".$feltnavn[$y]."='".$felt[$y]."'":$upd=$feltnavn[$y]."='".$felt[$y]."'";
				}
			}
			$vare_a=$vare_a.",lukket";
			$vare_b=$vare_b.",''";
			if ($varenr && $r=db_fetch_array(db_select("select id from varer where varenr='$varenr' or lower(varenr)='".strtolower($varenr)."' or upper(varenr)='".strtoupper($varenr)."'",__FILE__ . " linje " . __LINE__))) { #20140201
				$vare_id=$r['id'];
				$upd_antal++;
				db_modify("update varer set $upd where id='$vare_id'",__FILE__ . " linje " . __LINE__);
			} else {
				$imp_antal++;
				db_modify("insert into varer($vare_a) values ($vare_b)",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select id from varer where varenr='$varenr'",__FILE__ . " linje " . __LINE__));
				$vare_id=$r['id'];
			}
			if ($leverandor && $vare_id) {
				if ($r=db_fetch_array(db_select("select id from vare_lev where vare_id='$vare_id' and lev_id='$leverandor'",__FILE__ . " linje " . __LINE__))) {
					db_modify("update vare_lev set kostpris='$kostpris' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
				} else {
					db_modify("insert into vare_lev (vare_id,lev_id,kostpris,posnr) values ($vare_id,'$leverandor','$kostpris','1')",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
}
fclose($fp);
transaktion('commit');
print "</tbody></table>";
print "</td></tr>";
print "<BODY onLoad=\"javascript:alert('$imp_antal varer importeret, $upd_antal varer opdateret')\">";
#print "<BODY onLoad=\"javascript:alert('$imp_antal varer importeret')\">";
#print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
exit;
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

	if (substr($linje,0,1)==chr(34)) {
		$anftegn=1;
		$x++;	
 }
	for($z=$x;$z<=strlen($linje);$z++) {
		$tegn=substr($linje,$z,1);
		if (!$anftegn && substr($linje,$z-1,1)==$splitter && $tegn==chr(34)) {
			$anftegn=1;
 		}
		if ($anftegn && $tegn==chr(34) && substr($linje,$z+1,1)==$splitter) {
			$y++;
			$z++;
			$anftegn=0;
		} elseif (!$anftegn && substr($linje,$z,1)==$splitter) {
#			echo "$y B $var[$y]<br>";
			$y++;
		} elseif ($tegn!=chr(34)) {
			$var[$y]=$var[$y].substr($linje,$z,1);
		}
	}
	return $var;
}
function find_lev_id($kontonr) {
	$kontonr=trim($kontonr);
	if ($r=db_fetch_array(db_select("select id from adresser where kontonr='$kontonr'",__FILE__ . " linje " . __LINE__))) return ($r['id']);
	else return(0);
}
function find_varegrp($gruppe) {
	$gruppe=trim($gruppe);
	if (!is_numeric($gruppe)) {
		$low=strtolower($gruppe);
		$up=strtoupper($gruppe);
		if ($r=db_fetch_array(db_select("select kodenr from grupper where art='VG' and (lower(beskrivelse)='$low' or upper(beskrivelse)='$up')",__FILE__ . " linje " . __LINE__))) return ($r['kodenr']);
		else return(0);
	} elseif ($r=db_fetch_array(db_select("select id from grupper where art='VG' and kodenr = '$gruppe'",__FILE__ . " linje " . __LINE__))) return ($gruppe);
	else return(0);
}