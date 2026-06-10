<?php

// ---------------------------------------------debitor/historik.php-----lap 1.1.4-------12.12.2007-----------
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

$title="Kunde & emne histotik";
$modulnr=6;	

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/std_func.php");

$sort=if_isset($_GET['sort']);

if (isset($_POST) && $_POST) {
	$dg_antal=if_isset($_POST['dg_antal']);
	$vis_liste='';
	for ($x=0; $x<=$dg_antal; $x++) {
		$tmp="box"."$x";
		$tmp2=if_isset($_POST[$tmp]);
		if ($tmp2=='on') $vis_liste=$vis_liste.'1';
		else $vis_liste=$vis_liste.'0';
	}
	db_modify("update grupper set box2='$vis_liste' where art = 'HV' and box1 = '$brugernavn'");
	print "<BODY onLoad=\"javascript=opener.location.reload();\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
}

?>

<div align="center">

<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
	<tr><td height = "25" align="center" valign="top">
		<table width="100%" align="center" border="0" cellspacing="2" cellpadding="0"><tbody>
			<td width="10%"<?php echo $top_bund ?>><font face="Helvetica, Arial, sans-serif"><small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>
			<td width="80%"<?php echo $top_bund ?>><font face="Helvetica, Arial, sans-serif"><small>Historikvisning</a></small></td>
			<td width="10%"<?php echo $top_bund ?>><font face="Helvetica, Arial, sans-serif"><small><br></small></td>
			 </tr>
			</tbody></table>
	</td></tr>
 <tr><td valign="top">
<table cellpadding="1" cellspacing="1" border="0" width="100%" valign = "top">
<tbody>
<?php 

print "<form name=historikvisning action=historikvisning.php?sort=$sort method=post>";
if ($r = db_fetch_array(db_select("select * from grupper where art = 'HV' and box1 = '$brugernavn'"))) {
	$vis_liste=$r['box2'];
} else {
	db_modify("insert into grupper(beskrivelse, art, box1, box2)values('historikvisning', 'HV', '$brugernavn', '0')");
	$vis_liste='0';
}

$q = db_select("select * from grupper where art = 'DG' order by beskrivelse");
$x=-1;
while ($r = db_fetch_array($q)) {
	$x++;
	if (substr($vis_liste,$x,1)=='1') $tmp='checked';
	else $tmp='';
	print "<tr><td><small>$font<input name= box$x type=checkbox $tmp> $r[beskrivelse]</small></td></tr>";
}
print "<input type=hidden name=dg_antal value=$x>";
print "<tr><td colspan=6 align = center><input type=submit accesskey=\"a\" value=\"OK\" name=\"submit\"></td></tr>\n";

?>
</tbody></table>

</body></html>
