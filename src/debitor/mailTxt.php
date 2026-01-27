<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------debitor/mailTxt.php---lap 3.9.5------2020-11-11----
//                           LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20201111 PHR rehamed to mailTxt.php and added ordinary mail til customers not using MySale

#ob_start();
@session_start();
$s_id=session_id();

$adresseantal=$check_all=$hrefslut=$javascript=$kontoid=$linjebg=$linjetext=$nextpil=$ny_sort=$prepil=$tidspkt=$understreg=$udv2=NULL;
$find=$dg_id=$dg_navn=$selectfelter=array();

$css="../css/standard.css";
$modulnr=6;
$title="Mailtekst";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");
include("../includes/topline_settings.php");
	
$id = if_isset($_GET['id']);
$valg =  if_isset($_GET['valg']);
$returside=if_isset($_GET['returside']);

isset ($_POST['subjId'])?$subjId=$_POST['subjId']:$subjId=NULL;
isset ($_POST['subject'])?$subject=$_POST['subject']:$subject=NULL;
isset ($_POST['txtId'])?$txtId=$_POST['txtId']:$txtId=NULL;
isset ($_POST['mailText'])?$mailText=$_POST['mailText']:$mailText=NULL;

if ($valg=='historik') {
	$varGrp='debitor';
	$var_description='Subject for email to customers';
} else {
	$varGrp='mySale';
	$var_description='Subject for invitation email to mySale users';
}
if ($subject && $mailText) {
	if ($subjId) $qtxt="update settings set var_value='". db_escape_string($subject) ."' where id='$subjId'";
	else {
		if ($valg=='historik') $var_description='Subject for email to customers';
		else $var_description='Subject for invitation email to mySale users';
		$qtxt = "insert into settings (var_name,var_grp,var_description,user_id,var_value) values ";
		$qtxt.= "('mailSubject','$varGrp','$var_description',0,'". db_escape_string($subject) ."')";
	}
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($txtId) $qtxt="update settings set var_value='". db_escape_string($mailText) ."' where id='$txtId'";
	else {
		if ($valg=='historik') $var_description='Text for email to customers';
		else $var_description='Text for invitation email to mySale users';
		$qtxt = "insert into settings (var_name,var_grp,var_description,user_id,var_value) values ";
		$qtxt.= "('mailText','$varGrp','$var_description',0,'". db_escape_string($mailText) ."')";
	}
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt="select * from adresser where art='S'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$firmanavn=$r['firmanavn'];

if ($valg=='historik') {
	$subject = "".findtekst('1157|Information fra', $sprog_id)." $firmanavn"; #20210702
	$mailText = "".findtekst('1158|Kære $kunde, Dette er en meddelse til dig fra os :-)', $sprog_id)."\n\n";
	$mailText.= "".findtekst('1159|Bedste hilsner', $sprog_id)."\n$firmanavn\n";
} else {
	$subject = "".findtekst('1160|Adgang til dit salg hos', $sprog_id)." $firmanavn";
	$mailText = "".findtekst('1161|Kære $"."kunde,\n\nKlik på nedestående link for at se dit salg.', $sprog_id)."\n\n";
	$mailText.= "$"."".lcfirst(findtekst('1161|Link', $sprog_id))."\n\n";
	$mailText.= "".findtekst('1159|Bedste hilsner', $sprog_id)."\n$firmanavn\n";
}

$qtxt="select id,var_value from settings where var_name = 'mailSubject' and var_grp = '$varGrp' and user_id='0'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
if ($subjId = $r['id']) $subject=$r['var_value'];
$qtxt="select id,var_value from settings where var_name = 'mailText' and var_grp = '$varGrp' and user_id='0'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
if ($txtId = $r['id']) $mailText=$r['var_value'];

$qtxt="select id, kontonr, firmanavn from adresser where firmanavn != '' and lukket != 'on' and art='D' order by id limit 1";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$kundenavn=$r['firmanavn'];
$kontonr=$r['kontonr'];

$tmp=trim($_SERVER['PHP_SELF'],'/');
list ($folder,$tmp)=explode('/',$tmp,2);
$lnk="https://". $_SERVER['HTTP_HOST'] .'/'. $folder ."/mysale/mysale.php?id=";
$lnk=str_replace('bizsys','mysale',$lnk);
$txt = $r['id'] .'|'. $r['kontonr'] .'@'. $db  .'@'. $_SERVER['HTTP_HOST'];
for ($x=0;$x<strlen($txt);$x++) {
	$lnk.=dechex(ord(substr($txt,$x,1)));
}
$myLink="<a href='$lnk'>". findtekst(1881,$sprog_id) ."</a>";

if ($valg=='historik') {
	$instruction = "<b>".findtekst(1150,$sprog_id)."</b><br><br>";
	$instruction.= findtekst(1151,$sprog_id);
} else {
	$instruction = "<b>".findtekst(1152,$sprog_id)."</b><br><br>";
	$instruction.= "".findtekst(1153,$sprog_id)."<br>";
}
$instruction.= "".findtekst(1154,$sprog_id)."<br>";
$instruction.= "".findtekst(1155,$sprog_id)."<br>";
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\"> 
		<div class=\"headerbtnLft\"></div>
		<span class=\"headerTxt\">".findtekst(606,$sprog_id)."</span>";     
	print "<div class=\"headerbtnRght\"></div>";
	print "</div><!-- end of header -->
	<div class=\"maincontentLargeHolder\">\n";
} elseif ($menu=='S') {
	print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>\n";
	print "<tr><td height = '25' align='center' valign='top'>";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>\n";

	print "<td width=10%><a href=debitor.php?valg=$valg accesskey=L>
		   <button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">".findtekst(30,$sprog_id)."</button></a></td>";
	print "<td width=80% style=$topStyle align=center></td>";
	print "<td width=10% style=$topStyle align=center></td>";

	print "</tr></tbody></table>";
	print "</td></tr>\n<tr><td align=\"center\" valign=\"middle\" width=\"100%\">";
} else {
	print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>\n";
	print "<tr><td height = '25' align='center' valign='top'>";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>\n";
	print "<td width=10% $top_bund><a href=debitor.php?valg=$valg accesskey=L>".findtekst('30|Tilbage', $sprog_id)."</a></td>";
	print "<td width=80% $top_bund></td>";
	print "<td width=10% $top_bund></td>";
	print "</tr></tbody></table>";
	print "</td></tr>\n<tr><td align=\"center\" valign=\"middle\" width=\"100%\">";
}
print "<table align='center' valign='middle' border='0'><tbody>";
print "<tr><td colspan='2' align='center' width='560px'>$instruction<hr></td></tr>";
print "<form name='mailTxt' action='mailTxt.php?valg=$valg' method='post'>";
print "<input type='hidden' name='subjId' value='$subjId'>";
print "<input type='hidden' name='txtId' value='$txtId'>";
print "<tr><td width = '40px'>Emne</td><td width = '510px'><input style= 'width:510px;' name='subject'value=\"$subject\"></td></tr>";
print "<tr><td colspan = '2'><textarea style= 'width:560px;height:220px' name='mailText'>$mailText</textarea></td></tr>";
print "<tr><td colspan = '2'><input style='width:560px' type='submit' value='Gem'></td><tr>";
print "</form>";

$txt=str_replace("\n","<br>",$mailText);
$txt=str_replace('$kunde',$kundenavn,$txt);
$txt=str_replace('$link',$myLink,$txt);

print "<tr><td colspan='2' align='center' width='560px'><br><b>".findtekst(1156,$sprog_id)."</b><hr></td></tr>";
print "<tr><td colspan='2'>$txt</td></tr>";
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
