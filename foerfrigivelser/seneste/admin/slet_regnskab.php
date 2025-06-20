<?php
@session_start();
$s_id=session_id();

// --------------------------------/admin/slet_regnskab.php-----patch 1.1.4------21.02.2008--------
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

?>
<script LANGUAGE="JavaScript">
<!--
function Slet_Regnskab()
{
 var agree=confirm("Bekræft sletning af de valgte regnskaber");
	if (agree)
		return true ;
	else
    return false ;
}
// -->
</script>
<?php
		
if (!$font) $font="Helvetica, Arial, sans-serif";
if (!$top_bund) $top_bund="style=\"border: 1px solid rgb(0, 0, 0); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/knap_bg.gif\";";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>Saldi - Opret regnskab</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
	<tr><td align="center" valign="top" height="25">
		<table width="100%" align="center" border="0" cellspacing="2" cellpadding="0"><tbody>
			<td width="10%" <?php echo $top_bund ?>><font face="Helvetica, Arial, sans-serif" color="#000066"><small><a href=../index/admin_menu.php accesskey=L>Luk</a></small></td>
			<td width="80%" <?php echo $top_bund ?> align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Slet regnskab</small></td>
			<td width="10%" <?php echo $top_bund ?> align = "right"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
		</tbody></table>
	</td></tr>
<td align = center valign = center>
<table cellpadding="1" cellspacing="1" border="0"><tbody>
<?php
$id=array();$db_navn=array();$regnskab=array();$slet=array();
if ($_POST['regnskabsantal']) {
	$regnskabsantal=$_POST['regnskabsantal'];
	$id=$_POST['id'];
	$db_navn=$_POST['db_navn'];
	$regnskab=$_POST['regnskab'];
	$slet=$_POST['slet'];


	if ($regnskabsantal) {
		$slet_antal=0;
		for ($x=1; $x<=$regnskabsantal; $x++) {
			if ($slet[$x]=='on'){
			 	$slet_antal++;
				$mappe='../temp/'.$db_navn[$x];
				if (!file_exists($mappe)) mkdir("$mappe", 0777);
				if (file_exists($mappe)) {
					$dump_filnavn=$mappe."/regnskab_".date("Y-m-d");
					$gz_filnavn=$mappe."/regnskab_".date("Y-m-d").".gz";
					$dat_filnavn=$mappe."/regnskab_".date("Y-m-d").".sdat";
					$info_filnavn=$mappe."/regnskabsinfo.txt";
					$tidspkt= date("d-m-Y H:i");
					$infotekst="$regnskab[$x] slettet $tidspkt";
					system("export PGPASSWORD=$sqpass\npg_dump -h $sqhost -U $squser -f $dump_filnavn $db_navn[$x]");
					system ("gzip $dump_filnavn");
					system ("mv $gz_filnavn $dat_filnavn");
					system ("echo $infotekst > $info_filnavn");
					if (file_exists($dat_filnavn)) {
# 						print "Sletter regnskab: $regnskab[$x]<br>";
						db_modify("DROP DATABASE $db_navn[$x]");
						db_modify("delete from regnskab where id = $id[$x]");
						$slettet_regnskab=$regnskab[$x];
					} else "print Backupfejl - $regnskab[$x] ikke slettet";
				}
			}	
		}
		if ($slet_antal==1)	print "<BODY onLoad=\"javascript:alert('$slettet_regnskab slettet')\">";
		else print "<BODY onLoad=\"javascript:alert('$slet_antal regnskaber slettet')\">";
#	print "<meta http-equiv=\"refresh\" content=\"1;URL=slet_regnskab.php\">";
		}
}
	$x=0;
	$q1= db_select("select id, regnskab, db from regnskab where db != '$sqdb' order by id");
	while ($r1=db_fetch_array($q1)) {
		$x++;
		$id[$x]=$r1['id'];
		$regnskab[$x]=$r1['regnskab'];
		$db_navn[$x]=$r1['db'];
	}
	$regnskabsantal=$x;

print "<form name=slet_regnskab action=slet_regnskab.php method=post>";
for ($x=1; $x<=$regnskabsantal; $x++) {
	print "<tr>";
	print "<td>X $x</td>";
	print "<input type=\"hidden\" name=id[$x] value=\"$id[$x]\">";
	print "<input type=\"hidden\" name=db_navn[$x] value=\"$db_navn[$x]\">";
	print "<input type=\"hidden\" name=regnskab[$x] value=\"$regnskab[$x]\">";
	print "<td>$id[$x] : $regnskab[$x]</td>";
	print "<td><input type=checkbox name=slet[$x]</td>";
	print "</tr>";
}
print "<input type=\"hidden\" name=\"regnskabsantal\" value=\"$regnskabsantal\">";
print "<tr><td colspan=2 align=center><hr></td></tr>\n";
print "<tr><td colspan=2 align=center><input type=submit accesskey=\"a\" value=\"OK\" name=\"submit\" OnClick=\"return Slet_Regnskab()\"></td></tr>\n";
print "</form>";
?>
</tbody></table>
</body></html>
