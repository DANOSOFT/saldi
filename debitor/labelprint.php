<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|
//
// ----------/debitor/labelprint.php----------------lap 3.6.6---2016-04-12---
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
// Copyright (c) 2004-2016 DANOSOFT ApS
// ----------------------------------------------------------------------
// 

@session_start();
$s_id=session_id();
$title="Labelprint";
$modulnr=9;
$css="../css/standard.css";

$diffkto=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$id=if_isset($_GET['id'])*1;
$img=if_isset($_GET['src']);


$fy_ord=array('<?','<?php','?>');
$r=db_fetch_array(db_select("select box2 from grupper where art='LABEL'",__FILE__ . " linje " . __LINE__));
$txt=$r['box2'];
if (!$txt) {
	$txt="<table style='width:300px;height:100px'><tbody>";
	$txt.="<tr><td><i><p style='font-size:20px'>"."$"."eget_firmanavn</i></p></td><td><p style='font-size:4px'>Afs.<br>"."$"."eget_firmanavn<br>"."$"."egen_addr1<br>"."$"."eget_postnr "."$"."eget_bynavn</p></td></tr>";
	$txt.="<tr><td colspan=\"2\"><hr></td></tr>";
	$txt.="<tr><td colspan=\"2\"><p style='font-size:8px'>"."$"."firmanavn<br>"."$"."addr1<br>"."$"."postnr "."$"."bynavn</p></td></tr>";
	$txt.="</tbody></table>";
}
for ($x=0;$x<count($fy_ord);$x++) {
	if (strpos($txt,$fy_ord[$x])) {
		print "<BODY onLoad=\"JavaScript:alert('Illegal værdi i labeltekst')\">";
		exit;
	}
}
#echo "TXT $txt<br>";
#exit;
$r=db_fetch_array(db_select("select * from adresser where id='$id'",__FILE__ . " linje " . __LINE__));
$txt=str_replace('$firmanavn',$r['firmanavn'],$txt);
$txt=str_replace('$addr1',$r['addr1'],$txt);
$txt=str_replace('$addr2',$r['addr2'],$txt);
$txt=str_replace('$postnr',$r['postnr'],$txt);
$txt=str_replace('$bynavn',$r['bynavn'],$txt);
$txt=str_replace('$land',$r['land'],$txt);
$txt=str_replace('$cvrnr',$r['cvrnr'],$txt);
$txt=str_replace('$tlf',$r['tlf'],$txt);
$txt=str_replace('$email',$r['email'],$txt);

$r=db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
$txt=str_replace('$eget_firmanavn',$r['firmanavn'],$txt);
$txt=str_replace('$eget_addr1',$r['addr1'],$txt);
$txt=str_replace('$eget_addr2',$r['addr2'],$txt);
$txt=str_replace('$eget_postnr',$r['postnr'],$txt);
$txt=str_replace('$eget_bynavn',$r['bynavn'],$txt);
$txt=str_replace('$eget_land',$r['land'],$txt);
$txt=str_replace('$eget_cvrnr',$r['cvrnr'],$txt);
$txt=str_replace('$eget_tlf',$r['tlf'],$txt);
$txt=str_replace('$eget_email',$r['email'],$txt);

$txt=str_replace('$egen_firmanavn',$r['firmanavn'],$txt);
$txt=str_replace('$egen_addr1',$r['addr1'],$txt);
$txt=str_replace('$egen_addr1',$r['addr1'],$txt);
$txt=str_replace('$egen_postnr',$r['postnr'],$txt);
$txt=str_replace('$egen_bynavn',$r['bynavn'],$txt);
$txt=str_replace('$egen_land',$r['land'],$txt);
$txt=str_replace('$egen_cvrnr',$r['cvrnr'],$txt);
$txt=str_replace('$egen_tlf',$r['tlf'],$txt);
$txt=str_replace('$egen_email',$r['email'],$txt);

$fp=fopen("../temp/$db/label.html",'w');
fwrite($fp,$txt);
fclose($fp);
include ("../temp/$db/label.html");
print "<body onLoad=\"javascript:window.print()\">\n";

print " <br>\n";
?>
