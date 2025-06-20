<?php
// ------systemdata/importer_adresser.php---lap 3.4.3---2014-08-11--------
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
// -----------------------------------------------------------------------

// 2013.02.10 Break ændret til break 1
// 2014.07.04 Ansatte og primær kontakt opdateres nu også ved opdatering 
// 2014.08.11 se $find_kontakt... 


@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Importer_adresser";

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
	$art=$_POST['art'];
	$opdat=$_POST['opdat'];
	if (strstr($submit, "Import")) $submit="Importer";
	$filnavn=$_POST['filnavn'];
	$splitter=$_POST['splitter'];
	$feltnavn=$_POST['feltnavn'];
	$feltantal=$_POST['feltantal'];
	$kontonr=$_POST['kontonr'];
	$kontotype=$_POST['kontotype'];
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
#	if (!$r1=db_fetch_array(db_select("select box1, box2, beskrivelse from grupper where art='RA' order by kodenr desc",__FILE__ . " linje " . __LINE__))) {
#		exit;
#	}
	upload($bilag);
}

print "</tbody></table>";
#####################################################################################################
function upload($bilag){

	print "<tr><td width=100% align=center><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"importer_adresser.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"900000\">";
	print "<tr><td width=\"150px\">Import art</td><td align=\"right\"><select name=\"art\" style=\"width:150px\">\n";
	if ($art!='D') print "<option value=\"D\">Debitorimport</option>\n";
	if ($art!='K') print "<option value=\"K\">Kreditorimport</option>\n";
	print "</select></span></td></tr>";
	print "<tr><td width=\"150px\">Opdater eksisterende</td><td align=\"right\"><input type=\"checkbox\" name=\"opdat\"></td></tr>";
	print "<tr><td colspan=\"2\"><hr></td></tr>";
	print "<tr><td> V&aelig;lg datafil:</td><td><input name=\"uploadedfile\" type=\"file\"></td></tr>";
	print "<tr><td colspan=\"2\"><hr></td></tr>";
	print "<tr><td colspan=\"2\" align=center><input type=\"submit\" value=\"Hent\"></td></tr>";
	print "</form>";
	print "</tbody></table>";
	print "</td></tr>";
}

function vis_data($filnavn, $splitter, $feltnavn, $feltantal){
global $charset;
global $sprog_id;
global $art;
global $opdat;

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
($art=='D')?$box='box1':$box='box2';
if ($feltnavn) {
	for ($y=0; $y<=$feltantal; $y++) {
 		if ($y) $tmp=$tmp.";".$feltnavn[$y];
		else $tmp=$feltnavn[$y];
	}
	if ($r=db_fetch_array(db_select("select id from grupper where art='IMP'",__FILE__ . " linje " . __LINE__))) {
		db_modify("update grupper set $box='$tmp' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
	} else {
		db_modify("insert into grupper(beskrivelse,kodenr,art,$box) values ('Importsettings','1','IMP','$tmp')",__FILE__ . " linje " . __LINE__);
	}
#	setcookie("saldi_debimp",$tmp,time()+60*60*24*30);
} elseif ($r=db_fetch_array(db_select("select $box from grupper where art='IMP'",__FILE__ . " linje " . __LINE__))) {
	$feltnavn=explode(";",$r[$box]);
} elseif (isset($_COOKIE['saldi_debimp'])) {
	$tmp = $_COOKIE['saldi_debimp'];
	$feltnavn=explode(";",$tmp);
}

print "<tr><td width=100% align=center><table width=\"100%\" border=\"1\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"importer_adresser.php\" method=\"POST\">";
#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
print "<tr><td colspan=$cols><table><tbody>";
print "<tr><td>Konto art:<b>$art</b></td>\n";
print "<input type=\"hidden\" name=\"art\" value=\"$art\">\n";
print "<input type=\"hidden\" name=\"opdat\" value=\"$opdat\">\n";
print "</select></span>";
print "<td colspan=\"$cols\" align=\"right\"><span title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'>Separatortegn&nbsp;<select name=splitter>\n";
if ($splitter) print "<option>$splitter</option>\n";
if ($splitter!='Semikolon') print "<option>Semikolon</option>\n";
if ($splitter!='Komma') print "<option>Komma</option>\n";
if ($splitter!='Tabulator') print "<option>Tabulator</option>\n";
print "</select></span>";
print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />";
# if (!$art) $felt_navn=array("art","kontonr","firmanavn","fornavn","efternavn","addr1","husnr","etage","addr2","postnr","bynavn","land","kontakt","tlf","fax","email","web","notes","kreditmax","betalingsbet","betalingsdage","cvrnr","ean","institution","gruppe","kontoansvarlig","oprettet","felt_1","felt_2","felt_3","felt_4","felt_5","kategori","kontakt_navn","kontakt_addr1","kontakt_addr2","kontakt_postnr","kontakt_bynavn","kontakt_tlf","kontakt_fax","kontakt_email","kontakt_notes");
#else
if ($art!='D') {
	$felt_navn=array("kontonr","firmanavn","addr1","addr2","postnr","bynavn","land","kontakt","tlf","fax","email","web","notes","kreditmax","betalingsbet","betalingsdage","cvrnr","bank_navn","bank_reg","bank_konto","bank_fi","swift","erh","gruppe","oprettet","felt_1","felt_2","felt_3","felt_4","felt_5","kategori","kontakt_navn","kontakt_addr1","kontakt_addr2","kontakt_postnr","kontakt_bynavn","kontakt_tlf","kontakt_fax","kontakt_email","kontakt_notes");
	$felt_antal=count($felt_navn);
	for ($x=0; $x<$felt_antal; $x++) {
		$felt_betegn[$x]=$felt_navn[$x];
	}
} else {
	$felt_navn=array("kontonr","firmanavn","fornavn","efternavn","addr1","husnr","etage","addr2","postnr","bynavn","land","kontakt","tlf","fax","email","mailfakt","web","notes","kreditmax","betalingsbet","betalingsdage","cvrnr","ean","institution","pbs_nr","gruppe","kontoansvarlig","oprettet","felt_1","felt_2","felt_3","felt_4","felt_5","kategori","status","kontakt_navn","kontakt_addr1","kontakt_addr2","kontakt_postnr","kontakt_bynavn","kontakt_tlf","kontakt_fax","kontakt_email","kontakt_notes");
	$felt_tekst_id=array("357","360","358","359","361","412","413","362","144","146","364","370","377","378","402","677","367","391","381","368","57","376","379","380","414","374","386","65","255","256","257","258","259","388","494","403","404","405","406","407","408","409","410","411");
	$felt_antal=count($felt_navn);
	for ($x=0; $x<$felt_antal; $x++) {
		$felt_betegn[$x]=findtekst($felt_tekst_id[$x],$sprog_id);
	}
}

$kontotype=NULL;
for ($y=0; $y<=$feltantal; $y++) {
	for ($x=0; $x<$felt_antal; $x++) {
		if ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x] && $felt_aktiv[$x]==1) {
			print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med $felt_navn[$x]')\">";
			$feltnavn[$y]='';
		} elseif ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x]) $felt_aktiv[$x]=1;
	}
	if ($feltnavn[$y]=='kontonr')$kontonr=1;
	if ($feltnavn[$y]=='firmanavn') $kontotype='erhverv';
	if ($feltnavn[$y]=='fornavn') $kontotype='privat';

}
print "<input type=\"hidden\" name=\"kontotype\" value=\"$kontotype\">";
if ($filnavn && $splitter && $kontotype) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Import&eacute;r\"></td></tr>";
print "</td></tbody></table></td></tr>";
print "<tr><td colspan=$cols><hr></td></tr>\n";
if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
elseif ($splitter=='Komma') {$splitter=',';}
elseif ($splitter=='Tabulator') {$splitter=chr(9);}

for ($y=0; $y<=$feltantal; $y++) {
	$tmp='';
	for ($x=0; $x<=$felt_antal; $x++) {
		if ($feltnavn[$y]==$felt_navn[$x])$tmp=$felt_betegn[$x];
	}
	if ($feltnavn[$y]) {
		print "<td><select name=feltnavn[$y]>\n";
	}
	else  print "<td align=center><select name=feltnavn[$y]>\n";
	print "<option value=\"$feltnavn[$y]\">$tmp</option>\n";
	if ($feltnavn[$y]) print "<option></option>\n";
	for ($x=0; $x<=$felt_antal; $x++) {
		if ($feltnavn[$y]!=$felt_navn[$x]) print "<option value=\"$felt_navn[$x]\">$felt_betegn[$x]</option>\n";
	}
	print "</select>";
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
			if ($charset=='UTF-8') $linje=utf8_encode($linje);
			$felt=array();
			$felt = opdel($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$felt[$y]=trim($felt[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='kontonr'&&!is_numeric($felt[$y])) {
					$skriv_linje=2;
					print "<BODY onLoad=\"javascript:alert('R&oslash;de linjer indeholder fejl (kontonummer ikke numerisk) og bliver ikke importeret')\">";
				}
			}
 		}
		if ($skriv_linje>=1){
			print "<tr>";
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
global $kontotype;
global $art;
global $opdat;

$x=0;
$fp=fopen("../importfiler/postnr.csv","r");
if ($fp) {
	while (!feof($fp)) {
		$x++;
		$linje=trim(fgets($fp));
		list($postnr[$x],$bynavn[$x])=explode(chr(9),$linje);
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


transaktion('begin');

#$felt_antal=$feltantal;
if ($r=db_fetch_array(db_select("select * from grupper where art='DebInfo'",__FILE__ . " linje " . __LINE__))) {
	$status_id=explode(chr(9),$r['box3']);
	$status_beskrivelse=explode(chr(9),$r['box4']);
	$status_antal=count($status_id);
#	for ($x=0;$x<$status_antal;$x++)$status_beskrivelse[$x]=addslashes($status_beskrivelse[$x]);
}	else {
	db_modify("insert into grupper(beskrivelse,art) values ('Div DebitorInfo','DebInfo')",__FILE__ . " linje " . __LINE__);
	db_fetch_array(db_select("select box3 from grupper where art='DebInfo'",__FILE__ . " linje " . __LINE__));
	$status_id=array();
	$status_beskrivelse=array();
		$status_antal=0;
}
$ryd_firmanavn=0;
$fp=fopen("$filnavn","r");
if ($fp) {
	$kontonumre=array();
	$x=0;
	$imp_antal=0;
	while (!feof($fp)) {
#			$feltantal=$felt_antal;
			$skriv_linje=0;
		if ($linje=fgets($fp)) {
			$x++;
			$skriv_linje=1;
			if ($charset=='UTF-8') $linje=utf8_encode($linje);
			$felt=array();
			$felt = opdel($splitter, $linje);
#			if ($ryd_firmanavn) $felt[$ryd_firmanavn]='';
			if (in_array('pbs_nr',$feltnavn) && !in_array('pbs',$feltnavn)) {
				$feltantal++;
				$pbs=$feltantal;
				$feltnavn[$feltantal]='pbs';
				$felt[$feltantal]='';
			}
			if (in_array('husnr',$feltnavn)) {
				$felt=add2felt($feltantal,'husnr','addr1',$feltnavn,$felt);
			}
			if (in_array('etage',$feltnavn)) {
				$felt=add2felt($feltantal,'etage','addr1',$feltnavn,$felt);
			}
			if (in_array('fornavn',$feltnavn) && !in_array('firmanavn',$feltnavn)) {
				$feltantal++;
				$ryd_firmanavn=$feltantal;
				$feltnavn[$feltantal]='firmanavn';
				$felt[$feltantal]='';
			}
			if (in_array('efternavn',$feltnavn) && !in_array('firmanavn',$feltnavn)) {
				$feltantal++;
				$ryd_firmanavn=$feltantal;
				$feltnavn[$feltantal]='firmanavn';
				$felt[$feltantal]='';
			}
			if (in_array('fornavn',$feltnavn)) {
				$felt=add2felt($feltantal,'fornavn','firmanavn',$feltnavn,$felt);
			}
			if (in_array('efternavn',$feltnavn)) {
				$felt=add2felt($feltantal,'efternavn','firmanavn',$feltnavn,$felt);
			}
			for ($y=0; $y<=$feltantal; $y++) {
#				$felt[$y]=addslashes(trim($felt[$y]));
				$feltnavn[$y]=strtolower($feltnavn[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='kontonr'&&!is_numeric($felt[$y])) {
					$kontonr=0;
					$skriv_linje=2;
				} elseif ($feltnavn[$y]=='kontonr') $kontonr=$felt[$y];
				if ($feltnavn[$y]=='pbs_nr' && $felt[$y]) $felt[$pbs]='on';
				if ($feltnavn[$y]=="postnr") list($felt[$y],$bynavn[$y]) = explode(" ",$felt[$y],2);
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
				if ($feltnavn[$y]=='betalingsbet') {
					$tmp=strtolower($felt[$y]);
					if ($tmp=='lb.md.') $felt[$y]='Lb.Md.';
					elseif ($tmp=='forud') $felt[$y]='Forud';
					elseif ($tmp=='kontant') $felt[$y]='Kontant';
					elseif ($tmp=='efterkrav') $felt[$y]='Efterkrav';
					else $felt[$y]='Netto';
				}
			}
		}
		if ($skriv_linje==1){
			$addr_a='';
			$addr_b='';
			$upd='';
			$kontakt_a='';
			$kontakt_b='';
			$find_kontakt='';
			for ($y=0; $y<=$feltantal; $y++) {
				if ($felt[$y] && $feltnavn[$y]=='status'&& (!in_array($felt[$y],$status_beskrivelse))) {
					$x=1;
					while(in_array($x,$status_id)) $x++; #finder laveste ledige vaerdi
					$status=$x;
					$status_id[$status_antal]=$x;
					$status_beskrivelse[$status_antal]=$felt[$y];
					$felt[$y]=$x;
					$status_antal++;
					$box3=NULL;$box4=NULL;
					for ($x=0;$x<$status_antal;$x++) {
#							if ($status_id[$x]==$rename_status) $status_beskrivelse[$x]=$ny_status;
						($box3)?$box3.=chr(9).$status_id[$x]:$box3=$status_id[$x];
						($box4)?$box4.=chr(9).$status_beskrivelse[$x]:$box4=$status_beskrivelse[$x];
					}
					db_modify("update grupper set box3='$box3',box4='$box4' where art = 'DebInfo'",__FILE__ . " linje " . __LINE__);  
				} else {
					for ($x=0;$x<$status_antal;$x++) {
						if ($felt[$y]==$status_beskrivelse[$x]) { 
							$felt[$y]=$status_id[$x];
							break 1;
						}
					}
				}
#cho "$feltnavn[$y]<br>";
				if ($feltnavn[$y] && $feltnavn[$y]!='husnr'  && $feltnavn[$y]!='etage' ) {
					$felt[$y]=trim(addslashes($felt[$y]));
					if ($feltnavn[$y]=='betalingsdage') $felt[$y]*=1;
					if (!strstr($feltnavn[$y],"kontakt_")) {
						if ($addr_a) {
							$addr_a=$addr_a.",";
							$addr_b=$addr_b.",";
							$upd=$upd.",";
						}
						$addr_a=$addr_a.$feltnavn[$y];
						$addr_b=$addr_b."'".$felt[$y]."'";
						$upd=$upd.$feltnavn[$y]."='".$felt[$y]."'";
					} else {
						if ($kontakt_a) {
							$kontakt_a=$kontakt_a.",";
							$kontakt_b=$kontakt_b.",";
							$find_kontakt.=" and ";
						}
						$tmp=substr($feltnavn[$y],8);
						$kontakt_a=$kontakt_a.$tmp;
						$kontakt_b=$kontakt_b."'".$felt[$y]."'";
						($find_kontakt)?$find_kontakt.="$tmp='".$felt[$y]."'":$find_kontakt="$kontakt_a=$kontakt_b";
					}
				}
			}
			if (!strstr($addr_a,'lukket')) {
				$addr_a=$addr_a.",lukket";
				$addr_b=$addr_b.",''";
			}
			if (!strstr($addr_a,'gruppe')) {
				$addr_a=$addr_a.",gruppe";
				$addr_b=$addr_b.",'1'";
				$upd=$upd.",gruppe='1'";
			}
#			if (!strpos($addr_a,'art')) {
#				$addr_a=$addr_a.",art";
#				$addr_b=$addr_b.",'$art'";
#				$upd=$upd.",art='$art'";
#			} else {
#				for ($y=0; $y<=$feltantal; $y++) {
#				if ($feltnavn[$y]=='art') $art=$felt[$y];
#				}
#			}
			if ($r=db_fetch_array(db_select("select id from adresser where kontonr='$kontonr' and art='$art'",__FILE__ . " linje " . __LINE__))) {
				if ($opdat) {
					$konto_id=$r['id'];
					$imp_antal++;
				 db_modify("update adresser set $upd where id='$konto_id'",__FILE__ . " linje " . __LINE__);
#cho "kontonr=$kontonr opdateret<br>";
				} else {
#cho "kontonr=$kontonr ikke opdateret<br>";
					$konto_id=0;
				}
			} else {
				$imp_antal++;
				db_modify("insert into adresser($addr_a,kontotype,art) values ($addr_b,'$kontotype','$art')",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select id from adresser where kontonr='$kontonr' and art = '$art'",__FILE__ . " linje " . __LINE__));
				$konto_id=$r['id'];
			}
			if (in_array('kontakt',$feltnavn)) {
				for ($y=0; $y<=$feltantal; $y++) {
					if ($feltnavn[$y]=='kontakt') $kontakt=$felt[$y];
				}
			}
			if ($kontakt && !$r=db_fetch_array(db_select("select id from ansatte where konto_id='$konto_id' and navn = '$kontakt'",__FILE__ . " linje " . __LINE__))){
				db_modify("update ansatte set posnr=posnr+1 where konto_id= '$konto_id'",__FILE__ . " linje " . __LINE__);
				db_modify("insert into ansatte(navn,posnr,konto_id) values ('$kontakt',1,'$konto_id')",__FILE__ . " linje " . __LINE__);
			}
			if ($kontakt_a && $kontakt_b) {
				if (!$r=db_fetch_array(db_select("select id from ansatte where konto_id='$konto_id' and $find_kontakt",__FILE__ . " linje " . __LINE__))){
					db_modify("update ansatte set posnr=posnr+1 where konto_id= '$konto_id'",__FILE__ . " linje " . __LINE__);
					db_modify("insert into ansatte($kontakt_a,posnr,konto_id) values ($kontakt_b,1,'$konto_id')",__FILE__ . " linje " . __LINE__);
				}
				if ($kontakt_b) {
					list($tmp,$null)=explode(",",$kontakt_b,2);
					db_modify("update adresser set kontakt=$tmp where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
}
fclose($fp);
transaktion('commit');
print "</tbody></table>";
print "</td></tr>";
print "<BODY onLoad=\"javascript:alert('$imp_antal adresser importeret')\">";
if ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
else print "<meta http-equiv=\"refresh\" content=\"0;URL=../systemdata/diverse.php\">";
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
	if (substr($linje,0,1)==$splitter) {
		$linje=" ".$linje; 
	}
	$anftegn=0;
	$ny_linje="";
	for($z=$x;$z<=strlen($linje);$z++) {
		$tegn=substr($linje,$z,1);
		if (!$anftegn && $tegn==chr(34)) $anftegn=1;
		elseif ($tegn==chr(34)) $anftegn=0;
		if (!$anftegn && $tegn==$splitter) $tegn=chr(9);
		$ny_linje.=$tegn;
	}
	$var=explode(chr(9),$ny_linje);
	for($i=0;$i<$feltantal;$i++) {
#cho "$var[$i] - ";
		$var[$i]=trim($var[$i]);
#cho substr($var[$i],0,1);
#cho " - ";
#cho substr($var[$i],-1);

		if (substr($var[$i],0,1)==chr(34) && substr($var[$i],-1)==chr(34)) {
			$var[$i]=substr($var[$i],1,strlen($var[$i])-2);
		}
#cho " - $var[$i]<br>";
	}
	return $var;
}

function opdel2 ($splitter,$linje){
	global $feltantal;
	$anftegn=0;
	$x=0;
	$y=0;
	$linje=trim($linje);
	if (substr($linje,0,1)==chr(34)) {
		$anftegn=1;
		$x++;
 }
	for($z=$x;$z<=strlen($linje);$z++) {
		if (!$anftegn && substr($linje,$z-1,1)==$splitter && substr($linje,$z,1)==chr(34)) {
			$anftegn=1;
			$z++;
		}
		if ($anftegn && substr($linje,$z,1)==chr(34) && substr($linje,$z+1,1)==$splitter) {
			$y++;
			$z++;
			$anftegn=0;
		} elseif ($anftegn && substr($linje,$z,1)==chr(34) && $z>=strlen($linje)-1) {
			$z++;
			$anftegn=0;
		} elseif (!$anftegn && substr($linje,$z,1)==$splitter) {
			$y++;
		} else {
			$var[$y]=$var[$y].substr($linje,$z,1);
		}
	}
	return $var;
}


function add2felt($feltantal,$tmpnavn1,$tmpnavn2,$feltnavn,$felt){
#	global $feltantal;

	$tmp=NULL;
	for ($y=0; $y<=$feltantal; $y++) {
		if ($feltnavn[$y]==$tmpnavn1){
		$tmp=" ".$felt[$y];
		}
		if ($feltnavn[$y]==$tmpnavn2)	$feltnr=$y;
	}
	$felt[$feltnr]=$felt[$feltnr].$tmp;
	return ($felt);
}

