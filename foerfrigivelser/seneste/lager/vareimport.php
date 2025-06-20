<?php
// -------------------------------------------lager/vareimport.pgp------------patch 1.1.2------------------
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
$title="Vareimport";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/dkdecimal.php");
include("../includes/db_query.php");

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund>$font<small><a href=varer.php? accesskey=L>Luk</a></small></td>";
print "<td width=\"80%\" $top_bund align=\"center\">$font<small>Vareimport</small></td>";
print "<td width=\"10%\" $top_bund align=\"right\">$font</td>";
print "</tbody></table>";
print "</td></tr>";

if($_POST) {
	$submit=trim($_POST[submit]);
	$filnavn=$_POST['filnavn'];
	$splitter=$_POST['splitter'];
	$feltnavn=$_POST['feltnavn'];
	$feltantal=$_POST['feltantal'];
	$leverandor=$_POST['leverandor'];
	$varegrp=$_POST['varegrp'];
	$rabat=$_POST['rabat'];

echo "Submit $submit<br>";

	if ($submit=="Flyt") {
		flyt_data($filnavn, $splitter, $feltnavn, $feltantal, $leverandor,$varegrp, $rabat);
	}
	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn = "../temp/" . basename( $_FILES['uploadedfile']['name']); 
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
#			echo "The file ". basename( $_FILES['uploadedfile']['name']). " has been uploaded";
		vis_data($filnavn, $splitter, $feltnavn, $feltantal, $leverandor,$varegrp, $rabat);

#		vis_data($kladde_id, $_FILES['uploadedfile']['name'], '', '', 1);
		}else{
			echo "Der er sket en fejl ved hentningen af filen, pr&oslash;v igen!";
		}
		exit;
	}
	elseif($splitter){
		vis_data($filnavn, $splitter, $feltnavn, $feltantal, $leverandor,$varegrp, $rabat);
		} else upload();
}  else upload();
print "</tbody></table>";
################################################################################################################
function upload(){
global $font;

	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"vareimport.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"9999999\" />";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id />";
	print "<tr><td width=100% align=center>$font V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}

function vis_data($filnavn, $splitter, $feltnavn, $feltantal, $leverandor,$varegrp, $rabat){
global $font;
list($kontonr, $tmp)=split(" : ", $leverandor);
if (!$feltnavn) $feltnavn=array();

$fp=fopen("../temp/".$filnavn,"r");
if ($fp) {
	$linje=fgets($fp);
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
	elseif ($splitter=="Brdr. Dahl") $feltantal=7;
}
fclose($fp);

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"vareimport.php\" method=\"POST\">";
#print "<tr><td colspan=6 width=100% align=center>$font $filnavn</td></tr>";
print "<tr><td colspan=$feltantal align=center>$font Separatortegn &nbsp;<select name=splitter>\n";

if ($splitter) print "<option>$splitter</option>\n";
if ($splitter!='Semikolon') print "<option>Semikolon</option>\n";
if ($splitter!='Komma') print "<option>Komma</option>\n";
if ($splitter!='Tabulator') print "<option>Tabulator</option>\n";
if ($splitter!='Brdr. Dahl') print "<option>Brdr. Dahl</option>\n";
print "</select>";

print "&nbsp; Leverand&oslash;r &nbsp;<select name=leverandor>\n";
if ($leverandor) print "<option>$leverandor</option>\n";
$q = db_select("select * from adresser where art='K'");
print "<option></option>";
while ($r = db_fetch_array($q)) {
	if ($r[kontonr]!=$kontonr) print "<option>$r[kontonr] : $r[firmanavn]</option>";
}
print "</select>";

if (!in_array('Varegrp.', $feltnavn)) {
	print "&nbsp; Varegrp. &nbsp;<select name=varegrp>\n";
	if ($varegrp) print "<option>$varegrp</option>\n";
	$q = db_select("select * from grupper where art='VG'");
	print "<option></option>";
	while ($r = db_fetch_array($q)) {
		if ($r[kodenr]!=$kodenr) print "<option>$r[kodenr] : $r[beskrivelse]</option>";
	}
print "</select>";
}

if (!in_array('Kostpris', $feltnavn) && in_array('Salgspris', $feltnavn)) {
	print "&nbsp; Rabat &nbsp;<input type=text name = rabat size=2 value=$rabat>&nbsp;%\n";
}

print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
print "&nbsp; <input type=\"submit\" name=submit value=\"Vis\" />";
if (in_array('Beskrivelse',$feltnavn) && in_array('Salgspris',$feltnavn) && (in_array('Kostpris',$feltnavn) || $rabat) && in_array('Enhed',$feltnavn) && (in_array('Varegrp.',$feltnavn) || $varegrp)) {
	print "&nbsp; <input type=\"submit\" name=submit value=\"Flyt\" />";
}
print "</td></tr>";

if ((!$splitter)||($splitter=='Semikolon')) $splitter=';';
elseif ($splitter=='Komma') $splitter=',';
elseif ($splitter=='Tabulator') $splitter=chr(9);
elseif ($splitter=='Brdr. Dahl') $feltantal=7;

for ($y=0; $y<$feltantal; $y++) {
	print "<td><select name=feltnavn[$y]>\n";
	if ($feltnavn[$y]=='Varegrp.' && $varegrp) $feltnavn[$y] ==''; 
	print "<option>$feltnavn[$y]</option>\n";
	if (!in_array('Eget varenr.',$feltnavn) && !in_array('Begge varenr.',$feltnavn)) print "<option>Eget varenr.</option>\n";
	if (!in_array('Lev. varenr.',$feltnavn) && !in_array('Begge varenr.',$feltnavn)) print "<option>Lev. varenr.</option>\n";
	if (!in_array('Eget varenr.',$feltnavn) && !in_array('Lev. varenr.',$feltnavn) && !in_array('Begge varenr.',$feltnavn)) print "<option>Begge varenr.</option>\n";
	elseif ($feltnavn[$y]=='Eget varenr.' && !in_array('Lev. varenr.',$feltnavn) && !in_array('Begge varenr.',$feltnavn)) print "<option>Begge varenr.</option>\n";
	elseif ($feltnavn[$y]=='Begge varenr.') {
		print "<option>Eget varenr.</option>\n";
		print "<option>Lev. varenr.</option>\n";
	}
	if (!in_array('Beskrivelse',$feltnavn)) print "<option>Beskrivelse</option>\n";
	if (!in_array('Salgspris',$feltnavn)) print "<option>Salgspris</option>\n";
	if (!in_array('Kostpris',$feltnavn) && !$rabat) print "<option>Kostpris</option>\n";
	if (!in_array('Enhed',$feltnavn)) print "<option>Enhed</option>\n";
	if (!in_array('Varegrp.',$feltnavn) && !$varegrp) print "<option>Varegrp.</option>\n";
	if ($feltnavn[$y]) print "<option></option>\n";
	print "</select>";
	if ($feltnavn[$y]=='Salgspris' && $rabat) print "</td><td>$font Kostpris\n";
	print "</td>";
}
print "</form>";
$fp=fopen("../temp/".$filnavn,"r");
if ($fp) {
	$x=0;
	while (!feof($fp)) {
		$x++;
		$linje=fgets($fp);
		$felt=array();
		if ($splitter=='Brdr. Dahl') {
			print "<tr>";
			$felt[0]=substr($linje,1,10);
			$felt[1]=substr($linje,12,35);
			$felt[2]=substr($linje,47,10);
			$felt[3]=substr($linje,58,3);
			$felt[4]=substr($linje,61,3);
			$felt[5]=substr($linje,64,1);
			$felt[6]=substr($linje,65,3);
			$felt[1]=str_replace(chr(145),"æ",$felt[1]);
			$felt[1]=str_replace(chr(155),"ø",$felt[1]);
			$felt[1]=str_replace(chr(134),"å",$felt[1]);
			$felt[1]=str_replace(chr(146),"Æ",$felt[1]);
			$felt[1]=str_replace(chr(157),"Ø",$felt[1]);
			$felt[1]=str_replace(chr(143),"Å",$felt[1]);
		} else $felt = split($splitter, $linje);
		for ($y=0; $y<$feltantal; $y++) {


			if ($feltnavn[$y] == 'Varegrp.' && !in_array($felt[$y], $feltnavn)) $linjefarve[$x]='red';
		}
		for ($y=0; $y<$feltantal; $y++) {
			if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
			if ($linjefarve[$x]=='red' && $feltnavn[$y]) print "<td><FONT COLOR=#FF0000><small>$felt[$y]&nbsp;</small></td>";
			elseif ($feltnavn[$y]) {
				if ($feltnavn[$y]=='Salgspris') {
						$tmp=dkdecimal($felt[$y]/100);
						print "<td><FONT COLOR=#000000><small>$tmp&nbsp;</small></td>";
						if  ($rabat) {
							$tmp=dkdecimal(($felt[$y]/100)-($felt[$y]/10000*$rabat));
							print "<td><FONT COLOR=#000000><small>$tmp&nbsp;</small></td>";
						}
					}
					else print "<td><FONT COLOR=#000000><small>$felt[$y]&nbsp;</small></td>";
			}
			else print "<td><FONT COLOR=#999999><small>$felt[$y]&nbsp;</small></td>";
		}
		print "</tr>";
	}
	if ($linjefarve) print "<BODY onLoad=\"javascript:alert('Røde linjer vil ikke blive importeret - varegruppe findes ikke')\">";
}
 fclose($fp);
print "</tbody></table>";
print "</td></tr>";
}
function flyt_data($filnavn, $splitter, $feltnavn, $feltantal, $leverandor,$varegrp, $rabat)
{
global $font;
list($kontonr, $tmp)=split(" : ", $leverandor);
if (!$feltnavn) $feltnavn=array();
list($lev_id, $tmp)=split(":",$leverandor);
list($gruppe, $tmp)=split(":",$varegrp);

$x=0;
$q = db_select("select varenr from varer");
while ($r = db_fetch_array($q)) {
	$x++;
	$exist_vnr[$x]=$r['varenr'];
}

if ((!$splitter)||($splitter=='Semikolon')) $splitter=';';
elseif ($splitter=='Komma') $splitter=',';
elseif ($splitter=='Tabulator') $splitter=chr(9);
elseif ($splitter=='Brdr. Dahl') $feltantal=7;

#for ($y=0; $y<$feltantal; $y++) {

$fp=fopen("../temp/".$filnavn,"r");
if ($fp) {
	transaktion('begin');
	$x=0;
	while (!feof($fp)) {
		$x++;
		$linje=fgets($fp);
		$felt=array();
		if ($splitter=='Brdr. Dahl') {
			$felt[0]=substr($linje,1,10);
			$felt[1]=substr($linje,12,35);
			$felt[2]=substr($linje,47,10);
			$felt[3]=substr($linje,58,3);
			$felt[4]=substr($linje,61,3);
			$felt[5]=substr($linje,64,1);
			$felt[6]=substr($linje,65,3);
			$felt[1]=str_replace(chr(145),"æ",$felt[1]);
			$felt[1]=str_replace(chr(155),"ø",$felt[1]);
			$felt[1]=str_replace(chr(134),"å",$felt[1]);
			$felt[1]=str_replace(chr(146),"Æ",$felt[1]);
			$felt[1]=str_replace(chr(157),"Ø",$felt[1]);
			$felt[1]=str_replace(chr(143),"Å",$felt[1]);
		} else $felt = split($splitter, $linje);
		for ($y=0; $y<$feltantal; $y++) 	if ($feltnavn[$y] == 'Varegrp.' && !in_array($felt[$y], $feltnavn)) $linjefarve[$x]='red';
		for ($y=0; $y<$feltantal; $y++) {
			if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
			if ($linjefarve[$x]!='red' && $feltnavn[$y]) {
				if ($feltnavn[$y]=='Eget varenr.') $varenr=trim($felt[$y]);
				elseif ($feltnavn[$y]=='Lev. varenr.') $lev_varenr=trim($felt[$y]);
				elseif ($feltnavn[$y]=='Begge varenr.') {$varenr=trim($felt[$y]); $lev_varenr=trim($felt[$y]);}
				elseif ($feltnavn[$y]=='Beskrivelse') $beskrivelse=addslashes($felt[$y]);
				elseif ($feltnavn[$y]=='Enhed') $enhed=$felt[$y];
				elseif ($feltnavn[$y]=='Salgspris') {
					$salgspris=$felt[$y]/100;
					if  ($rabat) {
						$kostpris=($felt[$y]/100)-($felt[$y]/10000*$rabat);
					}
				}
				elseif ($feltnavn[$y]=='Kostpris') $kostpris=$felt[$y];
			}
		}
		if (in_array($varenr[$y],$exist_vnr)) {
			db_modify("update varer set beskrivelse='$beskrivelse', salgspris='$salgspris', kostpris=$kostpris, enhed=$enhed, gruppe=$gruppe where varenr='$varenr'");
			$r=db_fetch_array(db_select("select id from varer where varenr='$varenr'"));
			$r2=db_fetch_array(db_select("select id from vare_lev where vare_id='$r[id]' and lev_id='$lev_id'"));
			if ($r2[id]) db_modify("update vare_lev set kostpris=$kostpris[$y], lev_varenr=$lev_varenr[$y] where id='$r2[id]'");
			else db_modify("insert into vare_lev(vare_id, lev_id, lev_varenr, kostpris) values ('$r[id]', '$lev_id', '$lev_varenr', '$kostpris')");
		} else {
			db_modify("insert into varer (varenr, beskrivelse, salgspris, kostpris, enhed, gruppe) values ('$varenr', '$beskrivelse', '$salgspris', '$kostpris', '$enhed', '$gruppe')");
			$r=db_fetch_array(db_select("select id from varer where varenr='$varenr'"));
			db_modify("insert into vare_lev(vare_id, lev_id, lev_varenr, kostpris) values ('$r[id]', '$lev_id', '$lev_varenr', '$kostpris')");
		}	
	}
	transaktion('commit');
}
 fclose($fp);
print "</tbody></table>";
print "</td></tr>";
}


?>
