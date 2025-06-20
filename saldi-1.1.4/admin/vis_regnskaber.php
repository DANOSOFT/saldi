<?php
@session_start();
$s_id=session_id();

// --------------------------------/admin/vis_regnskaber.php-----patch 1.1.4------21.02.2008--------
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


include("../includes/connect.php");
include("../includes/db_query.php");
include("../includes/online.php");

if (!$font) $font="Helvetica, Arial, sans-serif";
if (!$top_bund) $top_bund="style=\"border: 1px solid rgb(0, 0, 0); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/knap_bg.gif\";";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>Saldi - Opret regnskab</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
	<tr><td align="center" valign="top" height="25">
		<table width="100%" align="center" border="0" cellspacing="2" cellpadding="0"><tbody>
			<td width="10%" <?php echo $top_bund ?>><font face="Helvetica, Arial, sans-serif" color="#000066"><small><a href=../index/admin_menu.php accesskey=L>Luk</a></small></td>
			<td width="80%" <?php echo $top_bund ?> align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Vis regnskaber</small></td>
			<td width="10%" <?php echo $top_bund ?> align = "right"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
		</tbody></table>
	</td></tr>
<td align = center valign = center>
<table cellpadding="1" cellspacing="1" border="1"><tbody>
<?php

$id=array(); $regnskab=array(); $db_navn=array();
$q= db_select("select id, regnskab, db from regnskab where db != '$sqdb' order by regnskab");
	print "<tr><td>$font<b>Regnskab</b></td><td align=right width=30>$font<b>id</b></td></tr>";
while ($r=db_fetch_array($q)) {
#	$x++;
	$id[$x]=$r['id'];
	$regnskab[$x]=$r['regnskab'];
	$db_navn[$x]=$r['db'];
	print "<tr><td>$font $regnskab[$x]</td><td align=right>$font $id[$x]</td></tr>";
}
?>
</tbody></table>
</body></html>
