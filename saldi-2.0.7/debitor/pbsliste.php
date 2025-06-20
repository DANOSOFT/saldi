<?php
@session_start();
$s_id=session_id();
// ------------debitor/pbs_liste.php------- patch 2.0.7 ---2009.05.11------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

$modulnr=5;
$title="PBS Liste";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<table width=100%><tbody>";
print "<tr><td>Id</td><td>Liste dato</td></tr>";
print "<tr><td colspan=3><hr></td></tr>";
$x=0;
$q=db_select("select * from pbs_liste order by id desc",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$x++;
	if ($x==1 && $r['afsendt']) {
		$liste_dato=date('d-m-Y');
		$tmp=$r['id']+1;
    if ($popup) print "<tr><td onClick=\"javascript:pbsfile=window.open('pbsfile.php?id=$tmp','pbsfile','".$jsvars."');pbsfile.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">$tmp</td>\n";
		else print "<tr><td><a href=pbsfile.php?id=$r[id]>$r[id]</a></td>";
		print "<td>$liste_dato</td>";
		if ($popup) print "<td onClick=\"javascript:pbsfile=window.open('pbsfile.php?id=$r[id]&afslut=ok','pbsfile','".$jsvars."');pbsfile.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\" align=right>afslut</td>\n";
		else	print "<td align=right><a href=pbsfile.php?id=$tmp&afslut=ok>afslut</a></td>";
		print "</tr>";
	}	
  if ($popup) print "<tr><td onClick=\"javascript:pbsfile=window.open('pbsfile.php?id=$r[id]','pbsfile','".$jsvars."');pbsfile.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">$r[id]</td>\n";
	else print "<tr><td><a href=pbsfile.php?id=$r[id]>$r[id]</a></td>";
	$liste_dato=dkdato($r['liste_date']);
	print "<td>$liste_dato</td>";
	if (!$r['afsendt'])	{
		if ($popup) print "<td onClick=\"javascript:pbsfile=window.open('pbsfile.php?id=$r[id]&afslut=ok','pbsfile','".$jsvars."');pbsfile.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\" align=right>afslut</td>\n";
		else	print "<td align=right><a href=pbsfile.php?id=$id&afslut=ok>afslut</a></td>";
	}
	print "</tr>";
} 
if (!$x) {
	$liste_dato=date('d-m-Y');
	$tmp=$r['id']+1;
	if ($popup) print "<tr><td onClick=\"javascript:pbsfile=window.open('pbsfile.php?id=$r[id]','pbsfile','".$jsvars."');pbsfile.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">$r[id]</td>\n";
	print "<tr><td><a href=pbsfile.php?id=1>1</a></td><td>$liste_dato</td>";
	if ($popup) print "<td onClick=\"javascript:pbsfile=window.open('pbsfile.php?id=$r[id]&afslut=ok','pbsfile','".$jsvars."');pbsfile.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\" align=right>afslut</td>\n";
	else	print "<td align=right><a href=pbsfile.php?id=$tmp&afslut=ok>afslut</a></td>";
	print "</tr>";
}	
print "</tbody></table>";	
######################################################################################################################################
?>
