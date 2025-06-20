<?php
// ------systemdata/importer_variantvarer.php---lap 3.1.3--2013-04-18--------
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
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Importer_variantvarer";

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
	$tegnset=$_POST['tegnset'];

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			vis_data($filnavn, '', '', 1, $bilag,$tegnset);
		} else echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
	} elseif($submit=='Vis'){
		vis_data($filnavn, $splitter, $feltnavn,$feltantal,$varenr,$bilag,$tegnset);
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

print "<form enctype=\"multipart/form-data\" action=\"importer_variantvarer.php\" method=\"POST\">\n";
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

function vis_data($filnavn, $splitter, $feltnavn, $feltantal,$tegneset){
global $charset;

$fp=fopen("$filnavn","r");
if ($fp) {
	for ($y=1; $y<4; $y++) $linje=fgets($fp);#korer frem til linje nr. 4.
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
	setcookie("saldi_variantimp",$tmp,time()+60*60*24*30);
} elseif (isset($_COOKIE['saldi_variantimp'])) {
	$tmp = $_COOKIE['saldi_variantimp'];
	$feltnavn=explode(";",$tmp);
}
print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"importer_variantvarer.php\" method=\"POST\">";
#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
print "<tr><td colspan=\"".$cols."\" align=center><span title='Angiv tegnsæt for import'>Tegnsæt<select name=tegnset>\n";
if ($tegnset) {print "<option>$tegnset</option>\n";}
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

$x=0;
$q=db_select("select varenr,id from varer",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$varer_id[$x]=$r['id'];
	$varer_nr[$x]=$r['varenr'];
	$x++;
}
$x=0;
$q=db_select("select * from varianter");
while ($r=db_fetch_array($q)) {
	$varianter_id[$x]=$r['id'];
	$varianter_beskrivelse[$x]=$r['beskrivelse'];
	$varianter_shop_id[$x]=$r['shop_id'];
	$x++;
}

$feltnavne="varenr".chr(9)."stregkode";
for ($i=0;$i<count($varianter_id);$i++) {
 $feltnavne.=chr(9)."$varianter_beskrivelse[$i]";
}
$feltnavne.=chr(9)."salgspris".chr(9)."kostpris".chr(9)."vejl.pris";
$felt_navn=explode(chr(9),$feltnavne);
$felt_antal=count($felt_navn);
for ($y=0; $y<=$feltantal; $y++) {
	for ($x=0; $x<=$felt_antal; $x++) {
		if ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x] && $felt_aktiv[$x]==1) {
			print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med $felt_navn[$x]')\">";
			$feltnavn[$y]='';
		} elseif ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x]) $felt_aktiv[$x]=1;
	}
	if ($feltnavn[$y]=='varenr')$varenr=1;
	if ($feltnavn[$y]=='stregkode')$stregkode=1;
}
if (($filnavn)&&($splitter)&&($varenr==1)&&($stregkode==1)) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Import&eacute;r\" /></td></tr>";
elseif (!$stregkode) print "<BODY onLoad=\"javascript:alert('Felt for stregkode ikke valgt')\">";
 
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
#cho "$x | $linje<br>";		
			$skriv_linje=1;
			if ($charset=='UTF-8') $linje=utf8_encode($linje);
			$felt=array();
			$felt = opdel($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
#cho "$feltnavn[$y] => $felt[$y]<br>";
				$fejl[$y]='';
				$feltfejl[$y]=0;
				$felt[$y]=trim($felt[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='varenr')	{
					$tmp=NULL;
					for($i=0;$i<count($varer_id);$i++) {
						if ($felt[$y]==$varer_nr[$i]) {
							$tmp=$varer_id[$i];
							break 1;
						}
					}
					if (!$tmp) {
						$skriv_linje=2;
						$fejl[$y].="varenr $felt[$y] ikke fundet, "; 
					}
				}
				if ($feltnavn[$y]=='kostpris')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) {
						$skriv_linje=2;
						$fejl[$y].="kostpris $tmp ikke numerisk, "; 
					}	
				}
				if ($feltnavn[$y]=='salgspris')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) {
						$skriv_linje=2;
						$fejl[$y].="salgspris $tmp ikke numerisk, "; 
					}
				}
				if ($feltnavn[$y]=='vejl.pris')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) {
						$skriv_linje=2;
						$fejl[$y].="vejl pris $tmp ikke numerisk, "; 
					}
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
				if ($feltnavn[$y]) {print "<td title=\"$fejl[$y]\"><span style=\"color: $color;\">$felt[$y]&nbsp;</span></td>";}
				else {print "<td align=\"center\" title=\"$fejl[$y]\"><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";}
			}
			print "</tr>";
		}
	}
}
fclose($fp);
print "</tbody></table>";
print "</td></tr>";
}

function overfoer_data($filnavn, $splitter, $feltnavn, $feltantal,$tegnset){
global $charset;

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

$x=0;
$q=db_select("select * from varianter",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$varianter_id[$x]=$r['id'];
	$varianter_beskrivelse[$x]=strtolower($r['beskrivelse']);
	$varianter_shop_id[$x]=$r['shop_id'];
	$x++;
}

$q=db_select("select * from variant_varer order by variant_stregkode",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($stregkode==$r['variant_stregkode']) {
		db_modify("delete from variant_varer where id='$r[id]'",__FILE__ . " linje " . __LINE__);
	} else {
		$stregkode=$r['variant_stregkode'];
	}
}

$x=0;
$q=db_select("select * from variant_typer",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$variant_type_id[$x]=$r['id'];
	$variant_type_variant_id[$x]=$r['variant_id'];
	$variant_type_beskrivelse[$x]=strtolower($r['beskrivelse']);
	$variant_type_shop_id[$x]=$r['shop_id'];
	$x++;
}

$x=0;
$q=db_select("select varenr,id,varianter from varer",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$varer_id[$x]=$r['id'];
	$varer_nr[$x]=$r['varenr'];
	$varer_varianter[$x]=$r['varianter'];
	$x++;
}

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
#db_modify("delete from de where regnskabsaar='$regnskabsaar'");attrelid    	attname   

$r=db_fetch_array(db_select("SELECT relfilenode FROM pg_class WHERE relname = 'variant_varer'",__FILE__ . " linje " . __LINE__)) ;
$relfilenode=$r['relfilenode']*1;
$r=db_fetch_array(db_select("SELECT * FROM pg_attribute WHERE attrelid= '$relfilenode' and attname = 'variant_salgspris'",__FILE__ . " linje " . __LINE__));
if ($r['attisdropped']!='f' || !$r['attname']) {
	db_modify("alter TABLE variant_varer ADD variant_salgspris numeric(15,3)",__FILE__ . " linje " . __LINE__);
}
$r=db_fetch_array(db_select("SELECT * FROM pg_attribute WHERE attrelid= '$relfilenode' and attname = 'variant_kostpris'",__FILE__ . " linje " . __LINE__));
if ($r['attisdropped']!='f' || !$r['attname']) {
	db_modify("alter TABLE variant_varer ADD variant_kostpris numeric(15,3)",__FILE__ . " linje " . __LINE__);
}
$r=db_fetch_array(db_select("SELECT * FROM pg_attribute WHERE attrelid= '$relfilenode' and attname = 'variant_vejlpris'",__FILE__ . " linje " . __LINE__));
if ($r['attisdropped']!='f' || !$r['attname']) {
	db_modify("alter TABLE variant_varer ADD variant_vejlpris numeric(15,3)",__FILE__ . " linje " . __LINE__);
}
$fp=fopen("$filnavn","r");
if ($fp) {
	$kontonumre=array();
	$x=0;
	$imp_antal=0;
	$upd_antal=0;
	$kostpris=0;
	$salgspris=0;
	$variant_type=NULL;
	$varenr="";	
	while (!feof($fp)) {
		$skriv_linje=0;
		if ($linje=fgets($fp)) {
			$x++;
			$skriv_linje=1;
			if ($charset=='UTF-8' && $tegnset!='UTF-8') $linje=utf8_encode($linje);
			elseif ($charset!='UTF-8' && $tegnset=='UTF-8') $linje=utf8_decode($linje);
			$variant_type=NULL;
			$felt=array();
 			$felt = opdel($splitter, $linje);
 			for ($y=0; $y<count($felt); $y++) {
				$medtag_felt[$y]=1;
				if ($feltnavn[$y]=='salgspris') $feltnavn[$y]="variant_salgspris";
				if ($feltnavn[$y]=='kostpris') $feltnavn[$y]="variant_kostpris";
				if ($feltnavn[$y]=='vejl.pris') $feltnavn[$y]="variant_vejlpris";
				if ($feltnavn[$y]=='varenr') $feltnavn[$y]="vare_id";
				if ($feltnavn[$y]=='stregkode') $feltnavn[$y]="variant_stregkode";
				$felt[$y]=trim($felt[$y]);
				$feltnavn[$y]=strtolower($feltnavn[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='stregkode') {
					if (!$stregkode=$felt[$y]) $skriv_linje=0;
				}
				if ($feltnavn[$y]=='vare_id')	{
					for($i=0;$i<count($varer_id);$i++) {
						if ($felt[$y]==$varer_nr[$i]) {
							$felt[$y]=$varer_id[$i];
							$vare_id=$varer_id[$i];
							break 1;
						}
					}
					$felt[$y]*=1;
				}
				if ($feltnavn[$y]=='variant_kostpris')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=0;
					elseif (!is_numeric($felt[$y])) $felt[$y]=usdecimal($felt[$y]);
					$kostpris=$felt[$y]*1;
				}
				if ($feltnavn[$y]=='variant_salgspris')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=0;
					elseif (!is_numeric($felt[$y])) $felt[$y]=usdecimal($felt[$y]);
					$salgspris=$felt[$y]*1;
				}
				if ($feltnavn[$y]=='variant_vejlpris')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=0;
					elseif (!is_numeric($felt[$y])) $felt[$y]=usdecimal($felt[$y]);
					$vejlpris=$felt[$y]*1;
				}
#cho "F $feltnavn[$y]<br>";
				if (in_array(strtolower($feltnavn[$y]),$varianter_beskrivelse)) {
				$medtag_felt[$y]=0;
				for($i=0;$i<count($varianter_id);$i++) {
					if (strtolower($feltnavn[$y])==$varianter_beskrivelse[$i]) {
					for($v=0;$v<count($varer_id);$v++) {
						if ($varer_id[$v]==$vare_id) {
							$v_var=explode(chr(9),$varer_varianter[$v]);
							if (!in_array($varianter_id[$i],$v_var)) {
								if ($varer_varianter[$v]) $varer_varianter[$v].=chr(9).$varianter_id[$i];
								else $varer_varianter[$v]=$varianter_id[$i];
							}		
						}
					}
					#echo strtolower($feltnavn[$y])."==".$varianter_beskrivelse[$i]."<br>";
#echo "Felt = $felt[$y]<br>"; 
						$tmp=NULL;
						for($t=0;$t<count($variant_type_id);$t++) {
#cho "$felt[$y]!=$variant_type_beskrivelse[$t]<br>";
							if (strtolower($felt[$y])==strtolower($variant_type_beskrivelse[$t])) 

#							$nyt_feltnavn[$y]=$feltnavn[$y];
							$tmp=$variant_type_id[$t];
#						$variant_type_id[$x]=$r['id'];
#	$variant_type_variant_id[$x]=$r['variant_id'];
#	$variant_type_beskrivelse[$x]=strtolower($r['beskrivelse']);
#	$variant_type_shop_id[$x]=$r['shop_id'];
						} 
						$felt[$y]=$tmp;	
						if ($variant_type) {
#cho "$variant_type.=chr(9).$tmp<br>";
							$variant_type.=chr(9).$tmp;
							} else {
#cho "$variant_type.=chr(9).$tmp<br>";
								$variant_type=$tmp;
							}
						}
					}
				}
				if ($feltnavn[$y]=='variant_stregkode')	$stregkode=$felt[$y];
			}
 		}
 		if ($skriv_linje==1) {
			$vare_a="variant_type";
			$vare_b="'".$variant_type."'";
			$upd="variant_type='".$variant_type."'";
			for ($y=0; $y<=$feltantal; $y++) {
				if ($feltnavn[$y] && $medtag_felt[$y]) {
					if ($nyt_feltnavn[$y]) $feltnavn[$y]=$nyt_feltnavn[$y];
					$felt[$y]=db_escape_string($felt[$y]);
					$vare_a.=",".$feltnavn[$y];
					$vare_b.=",'".$felt[$y]."'";
					$upd=$upd.",".$feltnavn[$y]."='".$felt[$y]."'";
				}
			}
#cho "select id from variant_varer where variant_stregkode='$stregkode'<br>";
				if ($r=db_fetch_array(db_select("select id from variant_varer where variant_stregkode='$stregkode'",__FILE__ . " linje " . __LINE__))) {
					$variant_id=$r['id'];
					$upd_antal++;
#cho "update variant_varer set $upd where id='$variant_id'<br>";
					db_modify("update variant_varer set $upd where id='$variant_id'",__FILE__ . " linje " . __LINE__);
				} else {
					$imp_antal++;
#cho "insert into variant_varer($vare_a) values ($vare_b)<br>";
					db_modify("insert into variant_varer($vare_a) values ($vare_b)",__FILE__ . " linje " . __LINE__);
				}
		}
	}
}
fclose($fp);

for($v=0;$v<count($varer_id);$v++) {
	if ($varer_varianter[$v]) {
		$v_var=explode(chr(9),$varer_varianter[$v]);
		$v2=array();
		$tmp=NULL;
		for ($i=0;$i<count($v_var);$i++){
			if (!in_array($v_var[$i],$v2)) {
				($tmp)?$tmp.=chr(9).$v_var[$i]:$tmp=$v_var[$i];
			}
			$v2[$i]=$v_var[$i];
		}
		db_modify("update varer set varianter = '$tmp' where id='$varer_id[$v]'",__FILE__ . " linje " . __LINE__);
	}
}
transaktion('commit');
print "</tbody></table>";
print "</td></tr>";
print "<BODY onLoad=\"javascript:alert('$imp_antal variant_varer importeret, $upd_antal variant_varer opdateret')\">";
#print "<BODY onLoad=\"javascript:alert('$imp_antal varianter importeret')\">";
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
#cho "$y B $var[$y]<br>";
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