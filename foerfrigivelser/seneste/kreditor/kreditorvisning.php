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

$title="kreditorvisning";
$modulnr=9;	

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/std_func.php");

if (isset($_POST) && $_POST) {
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
echo "update grupper set box2='$vis_K' where art = 'KV' and box1 = '$brugernavn'<br>";	
	db_modify("update grupper set box2='$vis_K' where art = 'KV' and box1 = '$brugernavn'");
	print "<BODY onLoad=\"javascript=opener.location.reload();\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
}

?>

<div align="center">

<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
	<tr><td height = "25" align="center" valign="top">
		<table width="100%" align="center" border="0" cellspacing="2" cellpadding="0"><tbody>
			<td width="10%"<?php echo $top_bund ?>><font face="Helvetica, Arial, sans-serif"><small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>
			<td width="80%"<?php echo $top_bund ?>><font face="Helvetica, Arial, sans-serif"><small>Kreditorvisning</a></small></td>
			<td width="10%"<?php echo $top_bund ?>><font face="Helvetica, Arial, sans-serif"><small><br></small></td>
			 </tr>
			</tbody></table>
	</td></tr>
 <tr><td valign="top">
<table cellpadding="1" cellspacing="1" border="0" width="100%" valign = "top">
<tbody><td valign=top width=25%>
<?php 
print "<table border=\"0\" width=\"100%\" valign = \"top\"><tbody>";

$vis_VG=array();
$vis_K=array();
print "<form name=kreditorvisning action=kreditorvisning.php?sort=$sort method=post>";
if ($r = db_fetch_array(db_select("select * from grupper where art = 'KV' and box1 = '$brugernavn'"))) {
	$vis_K=split(",",$r['box2']);
} else {
	db_modify("insert into grupper(beskrivelse, art, box1)values('kreditorvisning', 'KV', '$brugernavn')");
	$vis_K[0]='on';
}

$x=0;
if ($vis_K[0]) $tmp='checked';
else $tmp='';
print "<tr><td><small>$font<input name= K_$x type=checkbox $tmp> Alle leverand&oslash;rer</small></td></tr>";
$q = db_select("select distinct vare_lev.lev_id as lev_id, adresser.firmanavn as firmanavn from vare_lev, adresser where adresser.id=vare_lev.lev_id order by adresser.firmanavn");
while ($r = db_fetch_array($q)) {
	$x++;
	print "<input type=hidden name=K_id$x value=$r[lev_id]>";
	if (in_array($r[lev_id],$vis_K)) $tmp='checked';
	else $tmp='';
	$firmanavn=stripslashes($r['firmanavn']);
	print "<tr><td><small>$font<input name=K_$x type=checkbox $tmp> $firmanavn</small></td></tr>";
}
print "<tr><td height=200 valign=bottom><input type=submit accesskey=\"a\" value=\"OK\" name=\"submit\"></td></tr>\n";
print "</tbody></table></td>";
print "<td width=25%><br></td>";
?>
</tbody></table>

</body></html>
