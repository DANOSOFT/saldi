<?php
// -------------------debitor/prislister.php------------patch 3.2.9----2013-02-10--------------
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
// ---------------------------------------------------------------------------

// 2013.02.10 break ændret til break 1

@session_start();
$s_id=session_id();
$title="Prislister";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$indset=if_isset($_GET['indset']);
$fokus=if_isset($_GET['fokus']);
$prisliste=if_isset($_GET['prisliste']);
$ordre_id=if_isset($_GET['ordre_id']);
$start=if_isset($_GET['start'])*1;
if ($indset && $prisliste && $ordre_id) {
	$vare_id=indset($indset,$prisliste,$ordre_id,$fokus);
	if ($vare_id) {
		$r=db_fetch_array(db_select("select art from ordrer where id=$ordre_id",__FILE__ . " linje " . __LINE__));
		if ($r['art']=='DO') print "<meta http-equiv=\"refresh\" content=\"1;URL=../debitor/ordre.php?id=$ordre_id&vare_id=$vare_id&fokus=$fokus\">";
		elseif ($r['art']=='KO') print "<meta http-equiv=\"refresh\" content=\"1;URL=../kreditor/ordre.php?id=$ordre_id&vare_id=$vare_id&fokus=$fokus\">";
	}
}
if ($start<0)$start=0;
$prev=$start-500;
if ($prev<0) $prev=0;
$slut=$start+500;

if ($_POST['prisliste']) $prisliste=if_isset($_POST['prisliste']);
$varenr[0]=trim($_POST['varenr0']);
$ean[0]=trim($_POST['ean0']);
$prisgruppe[0]=trim($_POST['varegruppe0']);
$beskrivelse[0]=trim($_POST['beskrivelse0']);
$enhed[0]=trim($_POST['enhed0']);
$salgspris[0]=trim($_POST['salgspris0']);
$kostpris[0]=trim($_POST['kostpris0']);

$r=db_fetch_array(db_select("select * from grupper where id=$prisliste",__FILE__ . " linje " . __LINE__));
$listenavn=$r['beskrivelse'];
$prisfil=$r['box2'];
$rabatter=$r['box5'];
$std_rabat=$r['box6'];

$prisgrupper=array();
$fp=fopen("$prisfil","r");
if ($fp) {
	$x=0;
	while (!feof($fp)) {
		$linje=fgets($fp);
		if (substr($linje,0,1)>=5) {
			$tmp=substr($linje,86,4);
			if ($tmp && !in_array($tmp,$prisgrupper)) {
				$x++;
				$prisgrupper[$x]=$tmp;
			}
		}
	}
}
fclose($fp);
$gruppeantal=$x;
sort($prisgrupper);

$fp=fopen("$prisfil","r");
if ($fp) {
	$x=0;
	while (!feof($fp)) {
		$linje=fgets($fp);
#		if (substr($linje,0,1)>=5) {
			$x++;
			if (substr($linje,0,1)==5) $lukket[$x]='on';
			else $lukket[$x]=NULL;
			$ean[$x]=substr($linje,1,13);
#			$ref_ean[$x]=substr($linje,14,13);
			$varenr[$x]=substr($linje,27,10); #27,9
#			$antal_salgssenh[$x]=substr($linje,36,5);
			$salgspris[$x]=substr($linje,42,8); #41,8
			$beskrivelse[$x]=substr($linje,50,32); #49,33
			$enhed[$x]=substr($linje,83,3); # 82,3
			$prisgruppe[$x]=substr($linje,86,4); #85,4
#			$afgift[$x]=substr($linje,89,1);
#			$punktafgift[$x]=substr($linje,90,6);
#			$pakningstr[$x]=substr($linje,96,5);
#			$pakningsrabat[$x]=substr($linje,101,3);
#			$dato[$x]=substr($linje,104,6);
#    if ($charset=="UTF-8")
			$beskrivelse[$x]=utf8_encode($beskrivelse[$x]);
			$enhed[$x]=utf8_encode($enhed[$x]);
			$salgspris[$x]=$salgspris[$x]/100;
			if (strstr($rabatter,$prisgruppe[$x])) {
				$rabat[$x]=-1;
#if ($prisgruppe[$x]=='0004') echo "A1 rabat $rabat[$x]<br>";
				$rabatarray=array();
#if ($prisgruppe[$x]=='0004') echo "A2 rabat $rabat[$x]<br>";
				$rabatarray=explode(chr(9),$rabatter);
#if ($prisgruppe[$x]=='0004') echo "A3 rabat $rabat[$x]<br>";
				for ($y=0;$y<=count($rabatarray);$y++) {
#if ($prisgruppe[$x]=='0004') echo "A4 rabat $rabat[$x]<br>";
					list($a,$b)=explode("|",$rabatarray[$y]);	
#if ($prisgruppe[$x]=='0004') echo "A5 rabat $rabat[$x]<br>";
					if ($prisgruppe[$x]==$a) $rabat[$x]=$b*1;
#if ($prisgruppe[$x]=='0004') echo "A6 rabat $rabat[$x]<br>";
				}
				if ($rabat[$x]==-1) $rabat[$x]=$std_rabat;
			} else $rabat[$x]=$std_rabat;
#if ($prisgruppe[$x]=='0004') echo "A7 rabat $rabat[$x]<br>";
			$kostpris[$x]=$salgspris[$x]-($salgspris[$x]*$rabat[$x]/100);
			if ($varenr[0]) {
				$x=udvaelg($x,$varenr[0],$varenr[$x]); #|| $ean[0] || $prisgruppe[0] || $beskrivelse[0] || $enhed[0] || $salgspris[0] || $kostpris[0]) {	
			}
			if ($prisgruppe[0]) {
				$x=udvaelg($x,$prisgruppe[0],$prisgruppe[$x]); #|| $ean[0] || $prisgruppe[0] || $beskrivelse[0] || $enhed[0] || $salgspris[0] || $kostpris[0]) {
			}
			if ($ean[0]) {
				$x=udvaelg($x,$ean[0],$ean[$x]); #|| $ean[0] || $prisgruppe[0] || $beskrivelse[0] || $enhed[0] || $salgspris[0] || $kostpris[0]) {
			}
			if ($beskrivelse[0]) {
				$x=udvaelg($x,$beskrivelse[0],$beskrivelse[$x]); #|| $ean[0] || $prisgruppe[0] || $beskrivelse[0] || $enhed[0] || $salgspris[0] || $kostpris[0]) {
			}
			if ($enhed[0]) {
				$x=udvaelg($x,$enhed[0],$enhed[$x]); #|| $ean[0] || $prisgruppe[0] || $beskrivelse[0] || $enhed[0] || $salgspris[0] || $kostpris[0]) {
			}
			if ($salgspris[0]) {
				$a=usdecimal($salgspris[0]);
				$x=udvaelg($x,$a,$salgspris[$x]); #|| $ean[0] || $prisgruppe[0] || $beskrivelse[0] || $enhed[0] || $salgspris[0] || $kostpris[0]) {
			}
			if ($kostpris[0]) {
				$x=udvaelg($x,$kostpris[0],$kostpris[$x]); #|| $ean[0] || $prisgruppe[0] || $beskrivelse[0] || $enhed[0] || $salgspris[0] || $kostpris[0]) {
			}
			if ($x>$slut) break 1;
		}
#	}
	fclose($fp);
}
$vareantal=$x;

while ($vareantal<$start) {
	$slut=$start;
	$start=$start-500;
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\"><tbody>";
if ($popup) $href = "../includes/luk.php?returside=debitor/ordre.php&id=$ordre_id&fokus=$fokus";
else $href = "../debitor/ordre.php?id=$ordre_id&fokus=$fokus";
print "<td width=\"10%\" $top_bund><a href=$href accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\">Vareimport</td>";
print "<td width=\"10%\" $top_bund align=\"right\"><br></td><tr>";
print "<tr><td height = \"25\">";
if ($start>0) {
	print "<a href=prislister.php?start=$prev&ordre_id=$ordre_id&prisliste=$prisliste&fokus=$fokus><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a>";
} 
print "</td><td></td>";
print "<td align=right>";
if ($slut<$vareantal) {
	print "<a href=prislister.php?start=$slut&ordre_id=$ordre_id&prisliste=$prisliste&fokus=$fokus><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a>";
}
print "</td></tr>\n";
print "</tbody></table>";
print "</td></tr>";

print "<input type=\"hidden\" name=\"vareantal\" value=\"$vareantal\">";
for($x=1;$x<$gruppeantal;$x++) print "<input type=\"hidden\" name=\"kostpris[$x]\" value=\"$kostpris[$x]\">";

print "<tr><td width=\"100%\" align=\"center\" valign=\"top\"><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";

print "<tr>";
print "<td width=\"100\"><b>Varenr.</b></td>";
print "<td width=\"100\"><b>EANnr.</b></td>";
print "<td width=\"100\"><b>Varegruppe</b></td>";
print "<td width=\"500\"><b>Beskrivelse</b></td>\n";
print "<td width=\"100\"><b>Enhed</b></td>\n";
print "<td width=\"80\" align=\"right\"><b>Salgspris</b></td>\n";
print "<td width=\"80\" align=\"right\"><b>Kostpris</b></td>\n";
print "</tr>";

print "<form name=\"solar\" action=\"prislister.php?start=$start&ordre_id=$ordre_id&prisliste=$prisliste&fokus=$fokus\" method=\"post\">";
print "<tr><td><input class=\"inputbox\" style=\"width:100px\" type=\"text\" name=\"varenr0\" value=\"$varenr[0]\"></td>";
print "<td><input class=\"inputbox\" style=\"width:100px\" type=\"text\" name=\"ean0\" value=\"$ean[0]\"></td>";
print "<td><select class=\"inputbox\" name=\"varegruppe0\">\n";
if ($prisgruppe[0]) print "<option>$prisgruppe[0]</option>\n";
print "<option></option>";
for ($y=0;$y<$gruppeantal;$y++) {
	print "<option value=\"$prisgrupper[$y]\">$prisgrupper[$y]</option>";
}
print "</select></td>";
print "<td><input class=\"inputbox\" style=\"width:300px\" type=\"text\" name=\"beskrivelse0\" value=\"$beskrivelse[0]\"></td>";
print "<td><input class=\"inputbox\" style=\"width:50px\" type=\"text\" name=\"enhed0\" value=\"$enhed[0]\"></td>";
print "<td align=\"right\"><input class=\"inputbox\" style=\"text-align:right;width:100px\" type=\"text\" name=\"salgspris0\" value=\"$salgspris[0]\"></td>";
print "<td align=\"right\"><input class=\"inputbox\" style=\"text-align:right;width:100px\" type=\"text\" name=\"kostpris0\" value=\"$kostpris[0]\"></td>";
print "<td><input type=\"submit\" name=\"soeg\" value=\"S&oslash;g\"</td></tr>";
print "</form>";

if (!$start) $start=1;
if ($slut>$vareantal) $slut=$vareantal;

for ($x=$start;$x<=$slut;$x++) {
	$href="prislister.php?ordre_id=$ordre_id&prisliste=$prisliste&indset=$varenr[$x]&fokus=$fokus";
	print "<tr>";
	print "<td><a href=\"$href\">$varenr[$x]</a></td>";	
	print "<td>$ean[$x]</td>";	
	print "<td>$prisgruppe[$x]</td>";
	print "<td>$beskrivelse[$x]</td>";
	print "<td>$enhed[$x]</td>";
	print "<td align=\"right\">".dkdecimal($salgspris[$x])."</td>";
	print "<td align=\"right\">".dkdecimal($kostpris[$x])."</td>";
	print "<td></td>";
	print "</tr>";
}
print "</tbody></table>";
print "</td></tr>";

function udvaelg($x,$a,$b) {
	$a=trim(strtolower($a));
	$b=trim(strtolower($b));
#	if (substr($b,0,1)=='f') #echo "$b - ";
	if (substr($a,0,1)=='*' && substr($a,-1)=='*') {
		$a=substr($a,1,strlen($a)-2);
		if (!strstr($b,$a)) $x--;
	}	elseif (substr($a,0,1)=='*' && substr($a,-1)!='*') {
		$tmp=strlen($a)-1;
		if (substr($b,-$tmp)!=substr($a,-$tmp)) $x--;
	} elseif (substr($a,0,1)!='*' && substr($a,-1)=='*') {
		$tmp=strlen($a)-1;
		if (substr($b,0,$tmp)!=substr($a,0,$tmp)) $x--;
	} else {
		if ($b!=$a) $x--;
	}
	return($x);
}

function indset($indset,$prisliste,$ordre_id,$fokus) {

$indset=trim($indset);
	
$r=db_fetch_array(db_select("select * from grupper where id='$prisliste'",__FILE__ . " linje " . __LINE__));
$listenavn=$r['beskrivelse'];
$lev_id=$r['box1']*1;
$prisfil=$r['box2'];
$rabatter=$r['box5'];
$std_rabat=$r['box6'];
$varegupper=$r['box7'];
$std_varegruppe=$r['box8'];

#if (!$vareantal) {
	$fp=fopen("$prisfil","r");
	if ($fp) {
		while (!feof($fp)) {
			$linje=fgets($fp);
			$tmp=trim(substr($linje,27,10));
			if ($tmp==$indset) {
				if (substr($linje,0,1)==5) $lukket='on';
				else $lukket=NULL;
				$varenr=trim(substr($linje,27,10));
				$ean=substr($linje,1,13);
				$salgspris=substr($linje,38,12);
				$beskrivelse=substr($linje,50,32);
				$enhed=substr($linje,83,3);
				$prisgruppe=substr($linje,86,4);
				$beskrivelse=utf8_encode($beskrivelse);
				$enhed=utf8_encode($enhed);
				$salgspris=$salgspris/100;
				if (strstr($prisgruppe,$rabatter)) {
					$rabat=-1;
					$rabatarray=array();
					$rabatarray=explode(chr(9),$rabatter);
					for ($x=0;$x<=count($rabatarray);$x++) {
						list($a,$b)=explode("|",$rabatarray[$x]);	
						if ($prisgruppe==$a) $rabat=$b;
					}
					if ($rabat==-1) $rabat=$std_rabat;
				} else $rabat=$std_rabat;
				$kostpris=$salgspris-($salgspris*$rabat/100);
				if (strstr($prisgruppe,$varegrupper)) {
					$varegruppe=0;
					$vgarray=array();
					$vgarray=explode(chr(9),$varegrupper);
					for ($x=0;$x<=count($vgarray);$x++) {
						list($a,$b)=explode("|",$vgarray[$x]);	
						if ($prisgruppe==$a) $varegruppe=$b;
					}
					if (!$varegruppe) $varegruppe=$std_varegruppe;
				} else $varegruppe=$std_varegruppe;
			}
			if ($varenr) break 1;
		}
		fclose($fp);
	}
	if ($varenr) {
		if ($r=db_fetch_array(db_select("select id from varer where varenr='$indset'",__FILE__ . " linje " . __LINE__))) {
			$vare_id=$r['id'];
		} else {
			db_modify("insert into varer (varenr,stregkode,beskrivelse,salgspris,kostpris,enhed,gruppe) values ('$indset','$ean','$beskrivelse','$salgspris','$kostpris','$enhed','$varegruppe')",__FILE__ . " linje " . __LINE__);	
			$r=db_fetch_array(db_select("select id from varer where varenr='$indset'",__FILE__ . " linje " . __LINE__));
			$vare_id=$r['id'];
		}	
		if (!$r=db_fetch_array(db_select("select id from vare_lev where vare_id='$vare_id' and lev_id='$lev_id'",__FILE__ . " linje " . __LINE__))) {
			db_modify("insert into vare_lev (posnr,lev_id,vare_id,lev_varenr,kostpris) values ('1','$lev_id','$vare_id','$varenr','$kostpris')",__FILE__ . " linje " . __LINE__);
		}
	}	
	return($vare_id);
}
?>
