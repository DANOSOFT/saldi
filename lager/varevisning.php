<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/varevisning.php --- lap 4.0.7 --- 2023.04.21 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2023 saldi.dk ApS
// --------------------------------------------------------------------
// 20180328 Tilføjet $vis_lev_felt
// 2018.11.23 PHR $vis_kostpriser tilføjet
// 2018.11.26 PHR href på varenr tilføjet
// 2019.01.07 MSC Rettet isset fejl og tilføjet topmenu design
// 2019.04.03 LN Ask if customer wants to set the provision field
// 2019.04.29 PHR Added ShowTrademark.
// 2019.04.30 PHR Error correction.
// 2019.09.06 PHR some desigeupdates in 'top menu view'.
// 2023.04.21 PHR PHP8

@session_start();
$s_id=session_id();

$title="Varevisning";
$modulnr=9;	

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

echo $bruger_id;

if ($popup) $returside="../includes/luk.php";
else $returside="varer.php	";

if (isset($_POST) && $_POST) {
	$vis_lukkede=if_isset($_POST['vis_lukkede']);
	$vis_lev_felt=if_isset($_POST['vis_lev_felt']);
	$vis_kostpriser=if_isset($_POST['vis_kostpriser']);
	$showProvision = if_isset($_POST['provision']);
	$showTrademark = if_isset($_POST['showTrademark']);
	$href_vnr=if_isset($_POST['href_vnr']);
	
	$VG_antal=if_isset($_POST['VG_antal']);
	$alle_VG=if_isset($_POST['VG_0']);
	if ($alle_VG=='on') $vis_VG='on';
	else $vis_VG='';
	for ($x=1; $x<=$VG_antal; $x++) {
		$tmp="VG_"."$x";
		$tmp1="VG_id"."$x";
		$tmp2=if_isset($_POST[$tmp]);
		if ($tmp2=='on') $vis_VG=$vis_VG.','.$_POST[$tmp1];
	}	
	$K_antal=if_isset($_POST['K_antal']);
	$alle_K=if_isset($_POST['K_0']);
	if ($alle_K=='on') $vis_K='on';
	else $vis_K='';
	for ($x=1; $x<=$K_antal; $x++) {
		$tmp="K_"."$x";
		$tmp1="K_id"."$x";
		$tmp2=if_isset($_POST[$tmp]);
		if ($tmp2=='on') $vis_K=$vis_K.','.$_POST[$tmp1];
	}
	$tmp2=trim($tmp2,',');

	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "select id from settings where var_name = 'showProvision' and var_grp = 'items' and user_id = '$bruger_id'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { 
		$qtxt = "update settings set var_value = '$showProvision' where id = '$r[id]'";
	} else {
		$qtxt = "insert into settings(var_name, var_grp, var_value, user_id) values ";
		$qtxt.= "('showProvision', 'items', '$showProvision', '$bruger_id')";
	}
	echo $qtxt;
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$box4 = $vis_lukkede.chr(9).$vis_lev_felt.chr(9).$vis_kostpriser.chr(9).$href_vnr.chr(9);
	$box4.= $showProvision.chr(9).$showTrademark;
	$qtxt="update grupper set box2='$vis_VG', box3='$vis_K',box4='$box4' where art = 'VV' and box1 = '$brugernavn'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($popup) print "<BODY onLoad=\"javascript=opener.location.reload();\">";
#	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
}
if (isset($_POST['gemLuk'])) print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";

print "<div align=\"center\">";
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\"> 
			<div class=\"headerbtnLft\"></div>
			<span class=\"headerTxt\">Varevisning</span>";     
	print "<div class=\"headerbtnRght\"></div>";       
	print "</div><!-- end of header -->
		<div class=\"maincontentLargeHolder\">\n";
	print  "<center><table border='0' cellspacing='1' width='75%'>";
	print "	<tr><td height = \"10\" align=\"center\"></td></tr>";
	print "	<tr><td height = \"25\" align=\"center\" valign=\"top\">
	<table width=\"33%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
} else {
print "
	<tr><td height = \"25\" align=\"center\" valign=\"top\">
		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>
			<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\"><small><a href=$returside accesskey=L>Luk</a></small></td>
			<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\"><small>Varevisning</a></small></td>
			<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\"><small><br></small></td>
			 </tr>
			</tbody></table>
	</td></tr>
 <tr><td valign=\"top\">
<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">
<tbody><td valign=top width=25%>";
print "<table border=\"0\" width=\"100%\" valign = \"top\"><tbody>";
}
$vis_VG=array();
$vis_K=array();
print "<form name='varevisning' action='varevisning.php' method='post'>";
if ($r = db_fetch_array(db_select("select * from grupper where art = 'VV' and box1 = '$brugernavn'",__FILE__ . " linje " . __LINE__))) {
	$vis_VG=explode(",",$r['box2']);
	$vis_K=explode(",",$r['box3']);
	list($vis_lukkede,$vis_lev_felt,$vis_kostpriser,$href_vnr,$showProvision,$showTrademark)=array_pad(explode(chr(9),$r['box4'],6), 6, null);
} else {
	db_modify("insert into grupper(beskrivelse, art, box1)values('varevisning', 'VV', '$brugernavn')",__FILE__ . " linje " . __LINE__);
	$vis_VG[0]='on';
	$vis_K[0]='on';
}
print "<tr><td>$font Varegrupper</td></tr>";
$x=0;
if ($vis_VG[0]) $tmp='checked';
else $tmp='';
print "<tr><td><small>$font<input name= VG_$x type=checkbox $tmp> Alle varegrupper</small></td></tr>";
$q = db_select("select * from grupper where art = 'VG' and fiscal_year = '$regnaar' order by beskrivelse",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$x++;
	print "<input type=hidden name=VG_id$x value=$r[kodenr]>";
	if (in_array($r['kodenr'],$vis_VG)) $tmp='checked';
	else $tmp='';
	$beskrivelse=stripslashes($r['beskrivelse']);
	print "<tr><td><small>$font<input name=VG_$x type=checkbox $tmp> $beskrivelse</small></td></tr>";
#	if (substr($vis_VG,$x,1)=='1') $tmp='on';
#	else $tmp='';
#	print "<tr><td><small>$font<input name= VG_$x type=checkbox $tmp> $r[beskrivelse]</small></td></tr>";
}
print "<input type=hidden name='VG_antal' value='$x'>";
print "<form name='varevisning' action='varevisning.php' method='post'>";
print "</tbody></table></td>";
print "<td width='25%'><table  border=\"0\" width=\"100%\"><tbody>";
print "<tr><td>$font Kreditorer</td></tr>";

$x=0;
if ($vis_K[0]) $tmp='checked';
else $tmp='';
print "<tr><td><small>$font<input name= K_$x type=checkbox $tmp> Alle leverand&oslash;rer</small></td></tr>";
$q = db_select("select distinct vare_lev.lev_id as lev_id, adresser.firmanavn as firmanavn from vare_lev, adresser where adresser.id=vare_lev.lev_id order by adresser.firmanavn",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$x++;
	print "<input type=hidden name=K_id$x value=$r[lev_id]>";
	if (in_array($r['lev_id'],$vis_K)) $tmp='checked';
	else $tmp='';
	$firmanavn=stripslashes($r['firmanavn']);
	print "<tr><td><small>$font<input name=K_$x type=checkbox $tmp> $firmanavn</small></td></tr>";
}
print "<input type=hidden name=K_antal value=$x>";
print "</tbody></table></td>";
print "<td valign=top width=25%><table  border=\"0\" width=\"100%\"><tbody>";
if ($vis_lukkede) $vis_lukkede="checked";
if ($vis_lev_felt) $vis_lev_felt="checked";
if ($vis_kostpriser) $vis_kostpriser="checked";
if ($showProvision) $showProvision = "checked";
if ($showTrademark) $showTrademark="checked";
if ($href_vnr) $href_vnr="checked";
print "<tr><td><small>$font<input name='vis_lukkede' type='checkbox' $vis_lukkede> Vis udg&aring;ede varer</small></td></tr>";
print "<tr><td><small>$font<input name='vis_lev_felt' type='checkbox' $vis_lev_felt> Vis søgefelt for kreditorer</small></td></tr>";
print "<tr><td><small>$font<input name='vis_kostpriser' type='checkbox' $vis_kostpriser> Vis kostpriser</small></td></tr>";
print "<tr><td><small>$font<input name='showTrademark' type='checkbox' $showTrademark> Vis Varemærke</small></td></tr>";
$title="Ved afmærkning åbnes kun kortet ved klik på varenr";
print "<tr><td title='$title'><small>$font<input name='href_vnr' title='$title' type='checkbox' $href_vnr> Href på varenr</small></td></tr>";
print "<tr><td title='$title'><small>$font<input name='provision' title='$title' type='checkbox' $showProvision> Provision</small></td></tr>";
print "<tr><td align='left' height='50' valign=bottom><br>";
print "<input type='submit' style='width:200px;' accesskey=\"g\" value=\"Gem\" name=\"gem\"><br><br>\n";
print "<input type='submit' style='width:200px;' accesskey=\"m\" value=\"Gem og Luk\" name=\"gemLuk\"></td></tr>\n";
print "</tbody></table></td>";

if ($menu=='T') {
	print "<td width=0%></td>";
} else {
	print "<td width=25%><br></td>";
}
?>
</tbody></table>

</body></html>
