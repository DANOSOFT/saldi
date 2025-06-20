<?php
// -------------------lager/solarvvs.php------------patch 3.2.9 -----2013.02.10-------------
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
// Copyright (c) 2003-2013 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2013.02.10 Break ændret til break 1

@session_start();
$s_id=session_id();
$title="Vareimport";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\"><tbody>";
if ($popup) $href = "../includes/luk.php";
else $href = "diverse.php";
print "<td width=\"10%\" $top_bund><a href=$href accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\">Vareimport</td>";
print "<td width=\"10%\" $top_bund align=\"right\"><br></td>";
print "</tbody></table>";
print "</td></tr>";

if (isset($_GET['start'])) {
	$start=if_isset($_GET['start'])*1;
	if ($start<0) $start=0;
	$filnavn=if_isset($_GET['filnavn']);
	$leverandor=if_isset($_GET['leverandor']);
	$varegrp=if_isset($_GET['varegrp']);
	$vvsgrp=if_isset($_GET['vvsgrp']);
	$rabat=if_isset($_GET['rabat']);
	$handling=if_isset($_GET['handling']);
	if ($handling=="flyt") flyt_data($filnavn,$leverandor,$vvsgrp,$varegrp,$rabat,$start);
	else vis_data($filnavn,$leverandor,$vvsgrp,$varegrp,$rabat,$start);
} elseif ($_POST) {
	$submit=trim($_POST['submit']);
	$filnavn=$_POST['filnavn'];
	$leverandor=$_POST['leverandor'];
	$vvsgrp=$_POST['vvsgrp'];
	$varegrp=$_POST['varegrp'];
	$rabat=$_POST['rabat'];
	$start=if_isset($_POST['start'])*1;
	
	if (substr($submit,0,4)=="Indl") {
		flyt_data($filnavn,$leverandor,$vvsgrp,$varegrp,$rabat,$start);
	}
	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn = "../temp/" . basename( $_FILES['uploadedfile']['name']); 
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
#			echo "The file ". basename( $_FILES['uploadedfile']['name']). " has been uploaded";
			vis_data($filnavn,$leverandor,$vvsgrp,$varegrp,$rabat,$start);

#		vis_data($kladde_id, $_FILES['uploadedfile']['name'], '', '', 1);
		} else {
			echo "Der er sket en fejl ved hentningen af filen, pr&oslash;v igen!";
		}
		exit;
	}
	elseif($submit=='Vis'){
		vis_data($filnavn,$leverandor,$vvsgrp,$varegrp,$rabat,$start);
		} else upload();
}  else upload();
print "</tbody></table>";
################################################################################################################
function upload(){

	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"solarvvs.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"99999999\" />";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id />";
	print "<tr><td width=100% align=center>V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}

function vis_data($filnavn,$leverandor,$vvsgrp,$varegrp,$rabat,$start) {

list($kontonr, $tmp)=explode(" : ", $leverandor);
if (!$feltnavn) $feltnavn=array();
$slut=$start+100;

$vvsgruppe=array();
$fp=fopen("../temp/".$filnavn,"r");
if ($fp) {
	$x=0;
	while (!feof($fp)) {
		$linje=fgets($fp);
		if (substr($linje,0,1)>=5 && $x>=$start) {
			$tmp=trim(substr($linje,85,4));
				if ($tmp && !in_array($tmp,$vvsgruppe)) {
					$x++;
					$vvsgruppe[$x]=$tmp;
			}
		}
	}
	fclose($fp);
	$gruppeantal=$x;
	sort($vvsgruppe);
}	
print "<tr><td width=\"100%\" align=\"center\" valign=\"top\" height=\"40px\"><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"solarvvs.php\" method=\"POST\">";

if ($start > 0) {
	$tmp=$start-100;
	print "<tr><td width=\"5%\" title=\"$titletxt\"><a href=\"solarvvs.php?filnavn=$filnavn&leverandor=$leverandor&vvsgrp=$vvsgrp&varegrp=$varegrp&rabat=$rabat&start=$tmp&handling=vis\"><img src=\"../ikoner/left.png\" style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td>\n";
} else print "<tr><td width=\"5%\"></td>\n";
print "<td width=\"90%\" align=\"center\"> Leverand&oslash;r &nbsp;<select name=leverandor>\n";
if ($leverandor) print "<option>$leverandor</option>\n";
$q = db_select("select * from adresser where art='K' order by firmanavn",__FILE__ . " linje " . __LINE__);
print "<option></option>";
while ($r = db_fetch_array($q)) {
	if ($r[kontonr]!=$kontonr) print "<option>$r[kontonr] : $r[firmanavn]</option>";
}
print "</select>";

print "&nbsp; VVSgruppe &nbsp;<select name=vvsgrp>\n";
if ($vvsgrp) print "<option>$vvsgrp</option>\n";
print "<option></option>";
for ($x=0;$x<$gruppeantal;$x++) {
	print "<option>$vvsgruppe[$x]</option>";
}
print "</select>";

print "&nbsp; Varegrp. &nbsp;<select name=varegrp>\n";
if ($varegrp) print "<option>$varegrp</option>\n";
$q = db_select("select * from grupper where art='VG'",__FILE__ . " linje " . __LINE__);
print "<option></option>";
while ($r = db_fetch_array($q)) {
	if ($r[kodenr]!=$kodenr) print "<option>$r[kodenr] : $r[beskrivelse]</option>";
}
print "</select>";

print "&nbsp; Rabat &nbsp;<input type=text name = rabat size=2 value=$rabat>&nbsp;%\n";

print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />";
if ($leverandor && $rabat) {
	print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Indl&aelig;s\" /></td>";
}
print "<td width=\"5%\" align=\"right\" title=\"$titletxt\"><a href=\"solarvvs.php?filnavn=$filnavn&leverandor=$leverandor&vvsgrp=$vvsgrp&varegrp=$varegrp&rabat=$rabat&start=$slut&handling=vis\"><img src=\"../ikoner/right.png\" style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td></tr>\n";
print "</tbody></table>";
print "</form>";
print "</td></tr>";
print "<tr><td width=\"100%\" align=\"center\" valign=\"top\"><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\" valign=\"top\"><tbody>";

print "<tr>";
print "<td width=\"100\"><b>Varenr.</b></td>";
print "<td width=\"100\"><b>Varegruppe</b></td>";
print "<td width=\"500\"><b>Beskrivelse</b></td>\n";
print "<td width=\"100\"><b>Enhed</b></td>\n";
print "<td width=\"80\" align=\"right\"><b>Salgspris</b></td>\n";
print "<td width=\"80\" align=\"right\"><b>Kostpris</b></td>\n";
print "</tr>";

$fp=fopen("../temp/".$filnavn,"r");
if ($fp) {
	$y=0;
	$x=0;
	while (!feof($fp)) {
		$y++;
		if ($y>100000) break 1;
		$linje=fgets($fp);
#		echo substr($linje,85,4)." : $vvsgrp<br>";
		if (!$vvsgrp || substr($linje,85,4)==$vvsgrp) {
		if (substr($linje,0,1)>=5 && $x>=$start) {
			$x++;
			if (substr($linje,0,1)==5) $lukket='on';
			else $lukket=NULL;
			$ean=substr($linje,1,13);
			$ref_ean=substr($linje,14,13);
			$vvsnr=substr($linje,27,9);
			$antal_salgssenh=substr($linje,36,5);
			$salgspris=substr($linje,41,8);
			$beskrivelse=substr($linje,49,33);
			$enhed=substr($linje,82,3);
			$varegruppe=substr($linje,85,4);
			$afgift=substr($linje,89,1);
			$punktafgift=substr($linje,90,6);
			$pakningstr=substr($linje,96,5);
			$pakningsrabat=substr($linje,101,3);
			$dato=substr($linje,104,6);
#    if ($charset=="UTF-8") 
			$beskrivelse=utf8_encode($beskrivelse);
			$enhed=utf8_encode($enhed);
			$salgspris=$salgspris/100;
			$kostpris=$salgspris-($salgspris*$rabat/100);
			print "<tr>";
			print "<td>$vvsnr</td>";
			print "<td>$varegruppe</td>";
			print "<td>$beskrivelse</td>";
			print "<td>$enhed</td>";
			print "<td align=\"right\">".dkdecimal($salgspris)."</td>";
			print "<td align=\"right\">".dkdecimal($kostpris)."</td>";
			print "<td></td>";
			print "</tr>";
		}
		if ($x>$slut) break 1;
		}
	}
}
fclose($fp);
print "</tbody></table>";
print "</td></tr>";
}
function flyt_data($filnavn,$leverandor,$vvsgrp,$varegrp,$rabat,$start)
{

list($kontonr, $tmp)=explode(" : ", $leverandor);
$kontonr=trim($kontonr);
#list($lev_kontonr, $tmp)=explode(":",$leverandor);
list($varegrp, $tmp)=explode(":",$varegrp);
$varegrp=trim($varegrp);
#echo "select id from adresser where kontonr = '$kontonr'<br>";
$r = db_fetch_array(db_select("select id from adresser where kontonr = '$kontonr' and art = 'K'",__FILE__ . " linje " . __LINE__));
$lev_id=$r['id'];

$x=0;
$q = db_select("select varenr from varer",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$x++;
	$exist_vnr[$x]=$r['varenr'];
}

$fp=fopen("../temp/".$filnavn,"r");
if ($fp) {
#	transaktion('begin');
	$slut=$start+100;
	print "<td>importerer/opdaterer linje $start til $slut</td>";
	$x=0;
	while (!feof($fp)) {
		$linje=fgets($fp);
		if (!$vvsgrp || substr($linje,85,4)==$vvsgrp) {
			$x++;
			if ($x>$start+100) {
				$start=$slut;
				print "<meta http-equiv=\"refresh\" content=\"0;URL=solarvvs.php?filnavn=$filnavn&leverandor=$leverandor&vvsgrp=$vvsgrp&varegrp=$varegrp&rabat=$rabat&start=$start&handling=flyt\">";
				fclose($fp);
				exit;
			}
			if (substr($linje,0,1)>=5 && $x>=$start) {
				if (substr($linje,0,1)==5) $lukket='on';
				else $lukket=NULL;
				$ean=substr($linje,1,13);
				$ref_ean=substr($linje,14,13);
				$vvsnr=trim(substr($linje,27,9));
				$antal_salgssenh=substr($linje,36,5);
				$salgspris=substr($linje,41,8);
				$beskrivelse=trim(addslashes(substr($linje,49,33)));
				$enhed=trim(addslashes(substr($linje,82,3)));
				$gruppe=trim(substr($linje,85,4));
				$afgift=substr($linje,89,1);
				$punktafgift=substr($linje,90,6);
				$pakningstr=substr($linje,96,5);
				$pakningsrabat=substr($linje,101,3);
				$dato=substr($linje,104,6);
#    if ($charset=="UTF-8") 
				$beskrivelse=utf8_encode($beskrivelse);
				$enhed=utf8_encode($enhed);
				$salgspris=$salgspris/100;
				$kostpris=$salgspris-($salgspris*$rabat/100);
/*			
			print "<tr>";
			print "<td>$vvsnr</td>";
			print "<td>$gruppe</td>";
			print "<td>$beskrivelse</td>";
			print "<td>$enhed</td>";
			print "<td align=\"right\">".dkdecimal($salgspris)."</td>";
			print "<td align=\"right\">".dkdecimal($kostpris)."</td>";
			print "<td></td>";
			print "</tr>";
		}
*/		
			if (in_array($vvsnr,$exist_vnr)) {
#echo "opdaterer varenr $vvsnr<br>";				
db_modify("update varer set beskrivelse='$beskrivelse', salgspris='$salgspris', kostpris='$kostpris', enhed='$enhed', gruppe='$varegrp', lukket='$lukket' where varenr='$vvsnr'",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select id from varer where varenr='$vvsnr'",__FILE__ . " linje " . __LINE__));
				$r2=db_fetch_array(db_select("select id from vare_lev where vare_id='$r[id]' and lev_id='$lev_id'",__FILE__ . " linje " . __LINE__));
				if ($r2['id']) db_modify("update vare_lev set kostpris='$kostpris', lev_varenr='$vvsnr' where id='$r2[id]'",__FILE__ . " linje " . __LINE__);
				else db_modify("insert into vare_lev(vare_id, lev_id, lev_varenr, kostpris) values ('$r[id]', '$lev_id', '$vvsnr', '$kostpris')",__FILE__ . " linje " . __LINE__);
			} elseif ($vvsnr && $varegrp && $salgspris && $kostpris) {
#echo "opretter varenr $vvsnr<br>";				
				db_modify("insert into varer (varenr, beskrivelse, salgspris, kostpris, enhed, gruppe, lukket) values ('$vvsnr', '$beskrivelse', '$salgspris', '$kostpris', '$enhed', '$varegrp', '$lukket')",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select id from varer where varenr='$vvsnr'",__FILE__ . " linje " . __LINE__));
				db_modify("insert into vare_lev(vare_id, lev_id, lev_varenr, kostpris) values ('$r[id]', '$lev_id', '$vvsnr', '$kostpris')",__FILE__ . " linje " . __LINE__);
			} else {
# echo "overspringer varenr $vvsnr<br>";	
			}	
		}
#		transaktion('commit');
		}
	}
	fclose($fp);
	print "<BODY onLoad=\"javascript:alert('$x VVS varer importeret / opdateret')\">\n";
}
print "</tbody></table>";
print "</td></tr>";
}


?>
