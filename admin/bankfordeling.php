<?php
// ----------finans/bankfordeling.php------------patch 3.8.4------2019.10.22-----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2019 saldi.dk ApS
// ----------------------------------------------------------------------

// 2012.11.10 Indsat mulighed for valutavalg ved import - søg: valuta
// 2013.09.11 Fejl i 1. kald til "vis_data" "$vend" mangler.
// 2013.11.19	Genkendelse af posteringer fra Quickpay. Søg 20131109
// 2014.01.15 Genkendelse af FI indbetalinger fra Danske Bank. Søg Danske Bank
// 2014.01.17 Genkendelse af FI indbetalinger fra Sparekasserne. Søg Sparekasserne
// 2014.01.27 Genkendelse af FI indbetalinger fra Nordea. Søg Nordea
// 2014.02.03 Genkendelse af danske månedforkortelser i datoer. Søg 20140203
// 2014.07.08 Genkendelse af dankort betalinger 20140708
// 2014.10.05 Indsat "auto_detect_line_endings", eller kan den ikke altid genkende filer genereret på MAC 
// 2015.09.04 Genkendelse af dankort krediteringer 20150904
// 2016.02.12 Genkendelse af kortbetalinger gennem SparNord. Søg SparNord
// 2016.02.15 Ved Kortbetalinger fra SparNord registreres kortgebyrer på separat linje. Søg kortgebyr. 
// 2016.08.15 Genkendelse af FI indbetalinger fra Danske Bank som starter med IK71. Søg Danske Bank
// 2016.09.09 Tilføjet felt for gebyr kontonr samt ny version af SparNord. 20160909	
// 2016.11.01 Genkendelse af kortgebyr for Danske Bank. 20161101
// 2019.10.22 PHR Added 'DK3DSF' where 'DKSSL' is present as format has changed 20191022

ini_set("auto_detect_line_endings", true);

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="SALDI - bankfordeling";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

$vend=NULL;
$feltnavn=array();

if ($_POST) {
	$submit=$_POST['submit'];
	if ($_POST['vend']) $vend='checked';
	$filnavn=$_POST['filnavn'];
	$splitter=$_POST['splitter'];
	$feltnavn=$_POST['feltnavn'];
	$feltantal=$_POST['feltantal'];
	$regnskab_id=$_POST['regnskab_id'];
	$regnskab_navn=$_POST['regnskab_navn'];
	$kontonr=$_POST['kontonr'];
	$gebyrkonto=$_POST['gebyrkonto'];
	$valuta=$_POST['valuta'];
	$valutakode[0]=$_POST['valutakode']*1;
	$bilag=$_POST['bilag'];
	if (count($kontonr) || count($feltnavn)){
		$tmp='';
		for ($i=0;$i<count($kontonr);$i++){
			($tmp)?$tmp.=chr(9).$kontonr[$i]:$tmp=$kontonr[$i];
		}
		$saldi_bnkfd=$tmp.='|';
		$tmp='';
		for ($i=0;$i<count($gebyrkonto);$i++){
			($tmp)?$tmp.=chr(9).$gebyrkonto[$i]:$tmp=$gebyrkonto[$i];
		}
		$saldi_bnkfd.=$tmp.='|';
		$tmp.='';
		for ($i=0;$i<count($feltnavn);$i++){
			($tmp)?$tmp.=chr(9).$feltnavn[$i]:$tmp=$feltnavn[$i];
		}
		$saldi_bnkfd.=$tmp.='|';
		setcookie("saldi_bnkfd", $tmp);
	}	
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	if ($menu=='T') {
		$leftbutton="<a href='../index/admin_menu.php accesskey='L'>Luk</a>";
		$rightbutton="";
		include("../includes/topmenu.php");
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		print "<td width=\"10%\" $top_bund><a href='../index/admin_menu.php' accesskey='L'>Luk</a></td>";
		print "<td width=\"80%\" $top_bund>bankfordeling (Kassekladde)</td>";
		print "<td width=\"10%\" $top_bund ><br></td>";
		print "</tbody></table>";
		print "</td></tr>";
	}
/*	
	for ($i=0;$i<count($kontonr);$i++) {
		db_connect ("$sqhost", "$squser", "$sqpass", "$regnskab_navn[$i]", __FILE__ . " linje " . __LINE__);
		if (($kontonr[$i]) && (strlen($kontonr[$i])==1)) {
			$kontonr[$i]=strtoupper($kontonr[$i]);
			$query = db_select("select * from kontoplan where genvej='$kontonr[$i]' [$i]",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) $kontonr[$i]=$row['kontonr'];
			else {
				$kontonr[$i]='';
				print "<BODY onLoad=\"javascript:alert('Angivet kontonummer findes ikke')\">";
			}
		}
		elseif ($kontonr[$i])	 {
			$tmp=$kontonr[$i]*1;
			if (!$row=db_fetch_array(db_select("select id from kontoplan where kontonr=$tmp[$i]",__FILE__ . " linje " . __LINE__))) {
				print "<BODY onLoad=\"javascript:alert('Kontonummer $kontonr[$i] findes ikke i kontoplanen')\">";
				$submit='Vis';
			}
		}
	}
*/	
	if (basename($_FILES['uploadedfile']['name'])) {
	$filnavn="../temp/".$db."/".basename($_FILES['uploadedfile']['name']);
	$filnavn=str_replace(" ","_",$filnavn);
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
/*		
			if ($r=db_fetch_array(db_select("select * from grupper where ART = 'KASKL' and kode='3' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__))) {
				$kontonr=if_isset($r['box1']);
				$feltantal=if_isset($r['box2']);
				$feltnavn[0]=if_isset($r['box3']);
				$feltnavn[1]=if_isset($r['box4']);
				$feltnavn[2]=if_isset($r['box5']);
				$feltnavn[3]=if_isset($r['box6']);
				$feltnavn[4]=if_isset($r['box7']);
				$feltnavn[5]=if_isset($r['box8']);
				$feltnavn[6]=if_isset($r['box9']);
				$feltnavn[7]=if_isset($r['box10']);
				$gebyrkonto=if_isset($r['box11']);
			} else {
				db_modify ("insert into grupper (beskrivelse,art,kode,kodenr) values ('bankfordeling','KASKL','3','$bruger_id')",__FILE__ . " linje " . __LINE__);
			}
*/			
			if (!$feltantal) $feltantal=1;	
			vis_data($filnavn,'',$feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$vend,$valutakode);
		}	else {
			echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
		}
	}
	elseif($submit=='Vis'){
	#cho __line__." F $feltnavn[0]<br>";
		vis_data($filnavn,$splitter,$feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$vend,$valutakode);
	}
	elseif($submit=='Flyt'){
	#cho "flyt_data($filnavn,$regnskab_id,$regnskab_navn,$feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$valutakode[0])";
		if ($filnavn && count($kontonr)) flyt_data($filnavn,$regnskab_id,$regnskab_navn,$feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$valutakode[0]);
		else vis_data($filnavn, $splitter, $feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$vend,$valutakode);
	}
	else {
		upload($bilag);
	}
} else upload('');
print "</tbody></table>";
################################################################################################################
function upload($bilag){
global $charset;

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"bankfordeling.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center> V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "</tbody></table>";
print "</td></tr>";
}

function vis_data($filnavn,$splitter,$feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$vend,$valutakode){
#cho __line__." F $feltnavn[0]<br>";
global $charset;
global $bruger_id;
global $sqhost;
global $squser;
global $sqpass;

include("../includes/connect.php");
#cho __line__." $filnavn<br>";
if ($_COOKIE['saldi_bnkfd'] && !count($feltnavn)){
		list($a,$b,$c)=explode("|",$_COOKIE['saldi_bnkfd']);
#cho "a $a,b $b, $c<br>";
		$kontonr=explode(chr(9),$a);
		$gebyrkonto=explode(chr(9),$b);
		$feltnavn=explode(chr(9),$c);
	}


#cho __line__." F $feltnavn[0], $feltnavn[1], $feltnavn[2], $feltnavn[3], $feltnavn[4],s $feltnavn[5]<br>";

/*
$x=0;
$q=db_select("select kodenr,box1 from grupper where art='VK' order by box1",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if (trim($r['box1'])) {
		$x++;
		$valutakode[$x]=$r['kodenr'];
		$valuta[$x]=$r['box1'];
		}
}
*/
#cho __line__." F $filnavn<br>";
$semikolon=0;
$komma=0;
$tabulator=0;
$fp=fopen("$filnavn","r");
if ($fp) {
	for ($y=0; $y<10; $y++) {
		$linje[$y]=fgets($fp); 
		$semikolon+=substr_count($linje[$y],';');
		$komma+=substr_count($linje[$y],',');
		$tabulator+=substr_count($linje[$y],chr(9));
	}
	if (($komma>$semikolon)&& ($komma>$tabulator)) $tmp=',';
	elseif (($semikolon>$tabulator)&&($semikolon>$komma)) $tmp=';';
	elseif (($tabulator>$semikolon)&&($tabulator>$komma)) $tmp=chr(9);

	if (!$splitter) $splitter=$tmp;
	if ($splitter==',') $feltantal=$komma;
	elseif ($splitter==';') $feltantal=$semikolon;
	elseif ($splitter==chr(9)) $feltantal=$tabulator;
}
fclose($fp);
#cho $linje[1]." ".$splitter."<br>";
$feltantal=substr_count($linje[1],$splitter)+1;
$cols=$feltantal+1;
	$i=0;	
	$regnskab_id=array();
	if ($fp=fopen("$filnavn","r")){
	while (!feof($fp)) {
		if ($linje=trim(fgets($fp))) {
			$skriv_linje=1;
			$felt=array();
			$felt = explode($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
			$felt[$y]=trim($felt[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=="beskrivelse") {
					$beskrivelse=db_escape_string($felt[$y]);
					if (strlen($beskrivelse)==30 && (substr($beskrivelse,0,5)=='DKSSL' || substr($beskrivelse,0,6)=='DK3DSF') && is_numeric(substr($beskrivelse,6,4)) && is_numeric(substr($beskrivelse,11,7)) && is_numeric(substr($beskrivelse,19,11))) { #spar nord
						$tmp=$amount-3;
						$betalings_id="%".substr($beskrivelse,21,11);
						$tmp=substr($beskrivelse,-4)*1;
						if ($tmp && !in_array($tmp,$regnskab_id)){
							$qtxt="select * from regnskab where id='$tmp'";
							if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
								$regnskab_id[$i]=$tmp;
								$regnskab_navn[$i]=$r['db'];
								$regnskab_titel[$i]=$r['regnskab'];
#cho "R $regnskab_titel[$i]<br>";								
								$i++;
							}
						}
					}
				}
			}
		}	
	}
	fclose($fp);
	}
/*
for ($i=0;$i<count($regnskab_id);$i++){
	db_connect ("$sqhost", "$squser", "$sqpass", "$regnskab_navn[$i]", __FILE__ . " linje " . __LINE__);
	if ($r=db_fetch_array(db_select("select * from grupper where ART = 'KASKL' and kode='3' and kodenr='0'",__FILE__ . " linje " . __LINE__))) {
		$kontonr[$i]=if_isset($r['box1']);
		$gebyrkonto[$i]=if_isset($r['box11']);
		if (!$i) {
			$feltantal=if_isset($r['box2']);
			$feltnavn[0]=if_isset($r['box3']);
			$feltnavn[1]=if_isset($r['box4']);
			$feltnavn[2]=if_isset($r['box5']);
			$feltnavn[3]=if_isset($r['box6']);
			$feltnavn[4]=if_isset($r['box7']);
			$feltnavn[5]=if_isset($r['box8']);
			$feltnavn[6]=if_isset($r['box9']);
			$feltnavn[7]=if_isset($r['box10']);
		}
	} else {
		#cho "insert into grupper (beskrivelse,art,kode,kodenr) values ('bankfordeling','KASKL','3','0')";
		db_modify ("insert into grupper (beskrivelse,art,kode,kodenr) values ('bankfordeling','KASKL','3','0')",__FILE__ . " linje " . __LINE__);
	}
}
*/
#cho "RId ".count($regnskab_id)."<br>";
$fp=fopen("$filnavn","r");
if ($fp) {
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
				if ($x==0 && substr($linje,$x,1)=='"') {
					$z++; $anftegn=1; $felt[$z]='';
				} elseif ($x==0) {
					$z++; $felt[$z]=substr($linje,$x,1);
				} elseif (substr($linje,$x,1)=='"' && substr($linje,$x-1,1)==$splitter && !$anftegn) {
					$z++; $anftegn=1; $felt[$z]='';
				} elseif (substr($linje,$x,1)=='"' && (substr($linje,$x+1,1)==$splitter || $x==strlen($linje)-1)) {
					$anftegn=0;
					if (substr($linje,$x+2,1)=='"') $x++;
#					if ($x==strlen($linje)) $z--;
				}	elseif (!$anftegn && substr($linje,$x,1)==$splitter) {
					$z++; $felt[$z]='';
					if (substr($linje,$x+1,1)=='"') $x++;
				} else {
					$felt[$z]=$felt[$z].substr($linje,$x,1);
				} 
			}
			if ($z>$feltantal) $feltantal=$z-1;
			for ($x=1; $x<=$z; $x++) {
				$ny_linje[$y]=$ny_linje[$y].$felt[$x].chr(9);
			}
			$x++;
			$ny_linje[$y]=$ny_linje[$y].$felt[$x]."\n";
		}
	}
}  
$linjeantal=$y;
#$cols=$feltantal;
fclose ($fp);
#cho __line__." ".$filnavn."2<br>";
$fp=fopen($filnavn."2","w");
if ($vend) {
 for ($y=$linjeantal;$y>=1;$y--) fwrite($fp,$ny_linje[$y]);
} else { 
	for ($y=1;$y<=$linjeantal;$y++) fwrite($fp,$ny_linje[$y]);
}
fclose ($fp);
print "<tr><td width=100% align='center'>";
print "<form enctype=\"multipart/form-data\" action=\"bankfordeling.php\" method=\"POST\">";
print "<table border=\"1\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
print "<tr><td></td>";
print "<td title='Klik her for at indlæse filen nedefra'>Vend</td>";
print "<td title='Klik her for at indlæse filen nedefra'><input type=\"checkbox\" name=\"vend\" $vend>";
print "</td>";
print "<td title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'>Separatortegn&nbsp;</td>";
print "<td><select name=splitter>\n";
if ($splitter==';') {print "<option value=';'>Semikolon</option>\n";}
if ($splitter==',') {print "<option value=','>Komma</option>\n";}
if ($splitter==chr(9)) {print "<option value=chr(9)>Tabulator</option>\n";}
if ($splitter!=';') print "<option value=';'>Semikolon</option>\n";
if ($splitter!=',') print "<option value=','>Komma</option>\n";
if ($splitter!=chr(9)) print "<option value=chr(9)>Tabulator</option>\n";
print "</select></span>";
if ($v_ant=count($valuta)) {
	$valutakode[0]*=1;
	print "<span title='Angiv valuta'> Valuta<select name='valutakode'>\n";
	for ($x=1;$x<=$v_ant;$x++) {
		if ($valutakode[$x]==$valutakode[0]) print "<option value='$valutakode[$x]'>$valuta[$x]</option>\n";
	}
	print "<option value='0'>DKK</option>\n";
	for ($x=1;$x<=$v_ant;$x++) {
		if ($valutakode[$x]!=$valutakode[0]) print "<option value='$valutakode[$x]'>$valuta[$x]</option>\n";
	}
	print "</select></span>";
}
print "</td></tr>";
for ($i=0;$i<count($regnskab_id);$i++) {
#cho "I $i $regnskab_id[$i] $kontonr[$i]<br>";
	print "<tr><td>$regnskab_titel[$i]<input type='hidden' name='regnskab_id[$i]' value='$regnskab_id[$i]'><input type='hidden' name='regnskab_navn[$i]' value='$regnskab_navn[$i]'></td>\n";
	print "<td><span title='Angiv hvilket kontonummer der skal anvendes til posteringer'>Posteringskonto</td><td><input type=text size=8 name=kontonr[$i] value=$kontonr[$i]></span>&nbsp;</td>\n";
	print "<td><span title='Angiv hvilket kontonummer der skal anvendes til kortgebyr'>Gebyrkonto&nbsp;</td><td><input type=text size=8 name=gebyrkonto[$i] value=$gebyrkonto[$i]></span>&nbsp;</td></tr>\n";
	#cho "I $i $regnskab_id[$i] $kontonr[$i]<br>";
	if (!$kontonr[$i]) print "<BODY onLoad=\"javascript:alert('Der skal angives et kontonummer til den bankkonto hvor posteringer skal f&oslash;res for regnskab: $regnskab_titel[$i]')\">\n";
}
$i--;
#cho count($regnskab_id)." $i $filnavn && $splitter && ".$kontonr[$i]."<br>";
print "<tr><td colspan='5' align='center'><input type=\"hidden\" name=\"filnavn\" value=$filnavn>\n";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>\n";
print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />\n";
if (!in_array("dato",$feltnavn)) print "<BODY onLoad=\"javascript:alert('Kolonne for dato ikke valgt')\">\n";
elseif (!in_array("beskrivelse",$feltnavn)) print "<BODY onLoad=\"javascript:alert('Kolonne for beskrivelse ikke valgt')\">\n";
elseif (!in_array("belob",$feltnavn)) print "<BODY onLoad=\"javascript:alert('Kolonne for bel&oslash;b ikke valgt')\">\n";
elseif (!$splitter) print "<BODY onLoad=\"javascript:alert('Separatortegn ikke valgt')\">\n";
elseif ($filnavn && $splitter && $kontonr[$i]) {
	 print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Flyt\" />\n";
}
print "</td></tr>\n";
print "</tbody></table>\n";
print "</td></tr><tr><td>\n";
print "<table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>\n";
print "<tr><td colspan=$cols><hr></td></tr>\n";
#if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
#elseif ($splitter=='Komma') {$splitter=',';}
#elseif ($splitter=='Tabulator') {$splitter=chr(9);}
$splitter=chr(9);
print "<tr><td><span title='Angiv 1. bilagsnummer'><input type=text size=4 name=bilag value=$bilag></span></td>\n";
for ($y=0; $y<=$feltantal; $y++) {
	if (($feltnavn[$y]=='dato') &&($dato==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Dato')\">\n";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='beskrivelse') &&($beskr==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Beskrivelse')\">\n";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='belob')&&($belob==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Bel&oslash;b')\">\n";
		$feltnavn[$y]='';
	}
	if ($feltnavn[$y]=='belob') print "<td align=right><select name=feltnavn[$y]>\n";
	elseif ($feltnavn[$y]) print "<td><select name=feltnavn[$y]>\n";
	else  print "<td align=center><select name=feltnavn[$y]>\n";
	print "<option>$feltnavn[$y]</option>\n";
	if ($feltnavn[$y]) print "<option></option>\n";
	if ($feltnavn[$y]!='dato') print "<option value=\"dato\">Dato</option>\n";
	else $dato=1;
	if ($feltnavn[$y]!='beskrivelse') print "<option value=\"beskrivelse\">Beskrivelse</option>\n";
	else $beskr=1;
	if ($feltnavn[$y]!='belob') print "<option value=\"belob\">Bel&oslash;b</option>\n";
	else $belob=1;
	print "</select>";
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
				if ($feltnavn[$y]=='dato') { # 20140203
					$felt[$y]=str_replace("-jan-","-01-",$felt[$y]);
					$felt[$y]=str_replace("-feb-","-02-",$felt[$y]);
					$felt[$y]=str_replace("-mar-","-03-",$felt[$y]);
					$felt[$y]=str_replace("-apr-","-04-",$felt[$y]);
					$felt[$y]=str_replace("-maj-","-05-",$felt[$y]);
					$felt[$y]=str_replace("-jun-","-06-",$felt[$y]);
					$felt[$y]=str_replace("-jul-","-07-",$felt[$y]);
					$felt[$y]=str_replace("-aug-","-08-",$felt[$y]);
					$felt[$y]=str_replace("-sep-","-09-",$felt[$y]);
					$felt[$y]=str_replace("-okt-","-10-",$felt[$y]);
					$felt[$y]=str_replace("-nov-","-11-",$felt[$y]);
					$felt[$y]=str_replace("-dec-","-12-",$felt[$y]);
					$felt[$y]=str_replace(".","-",$felt[$y]);
				}
				if ($feltnavn[$y]=='belob') {
					$felt[$y]=str_replace(" ","",$felt[$y]);
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
			print "<tr><td>$bilag</td>";
			for ($y=0; $y<=$feltantal; $y++) {
				if ($feltnavn[$y]=='belob') {
					print "<td align=right>$felt[$y]&nbsp;</td>";
				}
				elseif ($feltnavn[$y]) {print "<td>$felt[$y]&nbsp;</td>";}
				else {print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";}
			}
			print "</tr>";
			$bilag++;
		} else {
			print "<tr><td><span style=\"color: rgb(153, 153, 153);\">-</span></td>";
			for ($y=0; $y<=$feltantal; $y++) {
				if ($feltnavn[$y]=='belob') {
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
/*
db_modify("update grupper set box1='$kontonr[$i]', box2='$feltantal',box11='$gebyrkonto' where ART='KASKL' and kode='3' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);
for ($y=0; $y<=$feltantal; $y++) {
	$box=$y+3;
	if ($box<=10) {
		$box="box$box";
		db_modify("update grupper set $box='$feltnavn[$y]' where ART='KASKL' and kode='3' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);
	}
}
*/
} # endfunc # vis_data

function flyt_data($filnavn,$regnskab_id,$regnskab_navn,$feltnavn,$feltantal,$kontonr,$gebyrkonto,$bilag,$valutakode){
	global $charset;
#	global $sqhost;
#	global $squser;
#	global $sqpass;
	global $regnaar;
	global $brugernavn;

#	transaktion('begin');
	#cho "splitter $splitter<br>";
	
	#cho "RID ".count($regnskab_id)."<br>";
	
	for ($i=0;$i<count($regnskab_id);$i++) {
	include("../includes/connect.php");
#cho "$sqhost, $squser, $sqpass, $regnskab_navn[$i]<br>";
	db_connect ("$sqhost", "$squser", "$sqpass", "$regnskab_navn[$i]", __FILE__ . " linje " . __LINE__);
	if ($r=db_fetch_array(db_select("select max(kodenr) as regnaar from grupper where art='RA'",__FILE__ . " linje " . __LINE__))) $regnaar=$r['regnaar'];
#cho __line__." $regnskab_navn[$i] $regnaar<br>";
	$qtxt="select max(id) as kladde_id from kladdeliste";
#cho $qtxt."<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$kladde_id=$r['kladde_id']+1;
	$qtxt="insert into kladdeliste(id,kladdedate,kladdenote,bogfort,oprettet_af) values ('$kladde_id','".date("Y-m-d")."','import af kortbetalinger','-','$brugernavn')";
#cho "$kladde_id<br>$qtxt<br>";
#xit;
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#	$qtxt="select max(id) as kladde_id from kladdeliste";
#	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#	$kladde_id=$r['kladde_id'];
#cho "$kladde_id<br>";
#cho $filnavn."2<br>";
	$fp=fopen($filnavn.'2','r');
#cho "$fp<br>";
	if ($fp) {
		$x=0;
		while (!feof($fp)) {
			$kortgebyr=0;
			$skriv_linje=0;
			if ($linje=trim(fgets($fp))) {
#cho __line__." $linje<br>";			
				$x++;
				$skriv_linje=1;
				$felt=array();
				$felt = explode(chr(9), $linje);
#cho __line__." felt = explode(chr(9), $linje)<br>";
				for ($y=0; $y<=$feltantal; $y++) {
					$felt[$y]=trim($felt[$y]);
					if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
					if ($feltnavn[$y]=='dato') { # 20140203
						$felt[$y]=str_replace("-jan-","-01-",$felt[$y]);
						$felt[$y]=str_replace("-feb-","-02-",$felt[$y]);
						$felt[$y]=str_replace("-mar-","-03-",$felt[$y]);
						$felt[$y]=str_replace("-apr-","-04-",$felt[$y]);
						$felt[$y]=str_replace("-maj-","-05-",$felt[$y]);
						$felt[$y]=str_replace("-jun-","-06-",$felt[$y]);
						$felt[$y]=str_replace("-jul-","-07-",$felt[$y]);
						$felt[$y]=str_replace("-aug-","-08-",$felt[$y]);
						$felt[$y]=str_replace("-sep-","-09-",$felt[$y]);
						$felt[$y]=str_replace("-okt-","-10-",$felt[$y]);
						$felt[$y]=str_replace("-nov-","-11-",$felt[$y]);
						$felt[$y]=str_replace("-dec-","-12-",$felt[$y]);
						$felt[$y]=str_replace(".","-",$felt[$y]);
					}
					if ($feltnavn[$y]=='belob') {
#cho "$felt[$y] NT ".nummertjek($felt[$y])." $skriv_linje<br>.";
						$felt[$y]=str_replace(" ","",$felt[$y]);
#cho "$felt[$y] NT ".nummertjek($felt[$y])." $skriv_linje<br>.";
						if (nummertjek($felt[$y])=='US') $felt[$y]=dkdecimal($felt[$y]);
						elseif (nummertjek($felt[$y])!='DK') $skriv_linje=0;		
#cho "$felt[$y] NT ".nummertjek($felt[$y])." $skriv_linje<br>.";
					}
				}
 			}	
#cho __line__.' '.$skriv_linje."<br>"; 			
			if ($skriv_linje==1){
#cho "feltantal	$feltantal<br>";	
				for ($y=0; $y<=$feltantal; $y++) {
					$bilag=$bilag*1;
					if ($feltnavn[$y]=='belob') $amount=usdecimal($felt[$y]);
					elseif ($feltnavn[$y]=="dato" && $felt[$y]) $transdate=usdate($felt[$y]);
					elseif ($feltnavn[$y]=="beskrivelse") $beskrivelse=db_escape_string($felt[$y]);
#cho __line__.' '.$beskrivelse."<br>";
				}
#cho __line__.' '.substr($beskrivelse,-4)*1 ."==$regnskab_id[$i]<br>";
				if (substr($beskrivelse,-4)*1==$regnskab_id[$i]) {
				$qtxt=NULL;
				if ($amount>0) {
				if (strlen($beskrivelse)==30 && (substr($beskrivelse,0,5)=='DKSSL' || substr($beskrivelse,0,6)=='DK3DSF') && is_numeric(substr($beskrivelse,6,4)) && is_numeric(substr($beskrivelse,11,7)) && is_numeric(substr($beskrivelse,19,11))) { # SparNord Ny 20160909 
						$tmp=$amount-3;
						$betalings_id="%".substr($beskrivelse,21,11);
#cho __line__.' '.$betalings_id."<br>";						
						$qtxt="select fakturanr,kontonr,sum,moms from ordrer where betalings_id LIKE '$betalings_id' and sum >= '$tmp' and sum <='$amount'";
					}	
					if ($qtxt) {
#cho __line__." $qtxt<br>";					
						if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							$faktura=$r['fakturanr'];
							$fakturasum=$r['sum']+$r['moms'];
							$kortgebyr=$amount-$fakturasum;
							$kredit=$r['kontonr']*1;
							$k_type='D';
						} else {
							$faktura='';
							$kortgebyr='0';
							$fakturasum=$amount-$kortgebyr;
							$kredit='0';
							$k_type='';
						}
#cho "$faktura | $kortgebyr | $fakturasum | $kredit | $k_type<br>";
					} else {
						$kredit='0';
						$faktura='';
						$k_type='';
					}
					
					if ($kortgebyr) {
						$qtxt="insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,valuta)";
						$qtxt.="  values ";
						$qtxt.="('$bilag','$transdate','$beskrivelse','F','$kontonr[$i]','F','0','$faktura','$amount','$kladde_id','$valutakode')";
#cho __line__.' '.$qtxt."<br>";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt="insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,valuta)";
						$qtxt.="  values ";
						$qtxt.="('$bilag','$transdate','$beskrivelse','F','0','$k_type','$kredit','$faktura','$fakturasum','$kladde_id','$valutakode')";
#cho __line__.' '.$qtxt."<br>";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt="insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,valuta)";
						$qtxt.="  values ";
						$qtxt.="('$bilag','$transdate','$beskrivelse','F','0','F','$gebyrkonto[$i]','$faktura','$kortgebyr','$kladde_id','$valutakode')";
#cho __line__.' '.$qtxt."<br>";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					} else {
						$qtxt="insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,amount,kladde_id,valuta)";
						$qtxt.="  values ";
						$qtxt.="('$bilag','$transdate','$beskrivelse','F','$kontonr[$i]','$k_type','$kredit','$faktura','$amount','$kladde_id','$valutakode')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
#cho $qtxt."<br>";
					$bilag++;
				} elseif ($amount<0) {
					$amount=$amount*-1;
					if (strlen($beskrivelse)==30 && substr($beskrivelse,0,2)=='DK' && is_numeric(substr($beskrivelse,4,7)) && is_numeric(substr($beskrivelse,12,9)) && is_numeric(substr($beskrivelse,25,5))) { # Dankort betaling 20150904 
						$betalings_id="%".substr($beskrivelse,12,9);
						$r=db_fetch_array(db_select("select fakturanr,kontonr from ordrer where betalings_id LIKE '$betalings_id' and sum = '$amount'",__FILE__ . " linje " . __LINE__));
						$faktura=$r['fakturanr'];
						$debet=$r['kontonr']*1;
						$d_type='D';
					} else {
					$dtype='F';
					$debet=0;
#cho "insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, amount, kladde_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$kontonr[$i]', '$amount', '$kladde_id')<br>";
						db_modify("insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,valuta) values ('$bilag','$transdate','$beskrivelse','$d_type','$debet','F','$kontonr[$i]','$amount','$kladde_id','$valutakode')",__FILE__ . " linje " . __LINE__);
					}
					$bilag++;
				}
			}
		}}
	}	
	fclose($fp);
#xit;
}
	unlink($filnavn); # sletter filen.
	unlink($filnavn."2"); # sletter filen.
#xit;	
#	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/admin_menu.php\">";
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
	
