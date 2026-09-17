<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------debitor/mySaleMailTxt.php.php---lap 3.9.5------2020-10-25----
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2020 saldi.dk aps
// ----------------------------------------------------------------------

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
	
$id = if_isset($_GET['id']);
$returside=if_isset($_GET['returside']);

isset ($_POST['subjId'])?$subjId=$_POST['subjId']:$subjId=NULL;
isset ($_POST['subject'])?$subject=$_POST['subject']:$subject=NULL;
isset ($_POST['txtId'])?$txtId=$_POST['txtId']:$txtId=NULL;
isset ($_POST['mailText'])?$mailText=$_POST['mailText']:$mailText=NULL;

if ($subject && $mailText) {
	if ($subjId) $qtxt="update settings set var_value='". db_escape_string($subject) ."' where id='$subjId'";
	else {
		$qtxt = "insert into settings (var_name,var_grp,var_description,var_value,user_id) values ";
		$qtxt.= "('mailSubject','mySale','Subject for invitation email to mySale users','". db_escape_string($subject) ."','0')";
	}
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($txtId) $qtxt="update settings set var_value='". db_escape_string($mailText) ."' where id='$txtId'";
	else {
		$qtxt = "insert into settings (var_name,var_grp,var_description,var_value,user_id) values ";
		$qtxt.= "('mailText','mySale','Text for invitation email to mySale users','". db_escape_string($mailText) ."','0')";
	}
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
$qtxt="select * from adresser where art='S'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$firmanavn=$r['firmanavn'];

$subject = "Adgang til dit salg hos $firmanavn";
$mailText = "Kære $"."kunde,\n\nKlik på nedestående link for at se dit salg.\n\n";
$mailText.= "$"."link\n\n";
$mailText.= "Bedste hilsner\n$firmanavn\n";


$qtxt="select id,var_value from settings where var_name = 'mailSubject' and var_grp = 'mySale'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
if ($subjId = $r['id']) $subject=$r['var_value'];
$qtxt="select id,var_value from settings where var_name = 'mailText' and var_grp = 'mySale'";
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
$myLink="<a href='$lnk'>MitSalg</a>";

$instruction = "<b>Skriv emne og tekst til den mail der skal bruges som invitation</b><br><br>";
$instruction.= "Teksterne '$"."kunde'  og '$"."link' bliver erstattet af hhv kundens navn og ink til  kundens salg<br>";
$instruction.= "Der kan anvendes HTML koder i teksten.<br>";
$instruction.= "F.eks bliver &lt;b&gt;fed&lt;/b&gt; vist som <b>fed</b>.<br>";

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\"> 
		<div class=\"headerbtnLft\"></div>
		<span class=\"headerTxt\">Konti</span>";     
	print "<div class=\"headerbtnRght\"></div>";
	print "</div><!-- end of header -->
	<div class=\"maincontentLargeHolder\">\n";
} else {
	print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>\n";
	print "<tr><td height = '25' align='center' valign='top'>";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>\n";
	print "<td width=10% $top_bund><a href=debitor.php?valg=kommission accesskey=L>Luk</a></td>";
	print "<td width=80% $top_bund></td>";
	print "<td width=10% $top_bund></td>";
	print "</tr></tbody></table>";
	print "</td></tr>\n<tr><td align=\"center\" valign=\"middle\" width=\"100%\">";
}
print "<table align='center' valign='middle' border='0'><tbody>";
print "<tr><td colspan='2' align='center' width='560px'>$instruction<hr></td></tr>";
print "<form name='mySaleMailTxt' action='mySaleMailTxt.php' method='post'>";
print "<input type='hidden' name='subjId' value='$subjId'>";
print "<input type='hidden' name='txtId' value='$txtId'>";
print "<tr><td width = '40px'>Emne</td><td width = '510px'><input style= 'width:510px;' name='subject'value=\"$subject\"></td></tr>";
print "<tr><td colspan = '2'><textarea style= 'width:560px;height:220px' name='mailText'>$mailText</textarea></td></tr>";
print "<tr><td colspan = '2'><input style='width:560px' type='submit' value='Gem'></td><tr>";
print "</form>";

$txt=str_replace("\n","<br>",$mailText);
$txt=str_replace('$kunde',$kundenavn,$txt);
$txt=str_replace('$link',$myLink,$txt);

print "<tr><td colspan='2' align='center' width='560px'><br><b>Eksempel</b><hr></td></tr>";
print "<tr><td colspan='2'>$txt</td></tr>";
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
