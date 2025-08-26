<?php
@session_start();
$s_id=session_id();
// --- debitor/pbs_liste.php --- patch 3.4.1 --- 2014.04.22 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 2014.04.22 Max ID øges med en hvis alle eksisterende er afsendt. # 20140422

$modulnr=5;
$title="PBS Liste";
$css="../css/standard.css";

include("../includes/std_func.php");
$title="PBS Liste";
include("../includes/connect.php");
include("../includes/online.php");

print "<table width=100%><tbody>";
print "<tr><td>Id</td><td>Liste dato</td></tr>";
print "<tr><td colspan=3><hr></td></tr>";
$r=db_fetch_array(db_select("select max(id) as id from pbs_liste",__FILE__ . " linje " . __LINE__));
$max_id=$r['id']*1;
$r=db_fetch_array(db_select("select afsendt from pbs_liste where id='$max_id'",__FILE__ . " linje " . __LINE__)); #20140422
if ($r['afsendt']) $max_id++;

$kan_afsluttes=0;
#echo "A $kan_afsluttes<br>";
if ($r=db_fetch_array(db_select("select * from adresser where pbs_nr='' and pbs = 'on' order by id",__FILE__ . " linje " . __LINE__))) {
#	echo "$r[kontonr]<br>";
	$kan_afsluttes=1;
#echo "B $kan_afsluttes<br>";
}
if ($r=db_fetch_array(db_select("select adresser.kontonr as ny_kontonr, adresser.pbs_nr as pbs_nr, pbs_kunder.kontonr as kontonr from adresser,pbs_kunder where adresser.id=pbs_kunder.konto_id and adresser.kontonr!=pbs_kunder.kontonr order by adresser.id",__FILE__ . " linje " . __LINE__))) {
	$kan_afsluttes=1;
#echo "C $kan_afsluttes<br>";
}
if ($r=db_fetch_array(db_select("select pbs_ordrer.ordre_id,ordrer.konto_id from pbs_ordrer,ordrer where pbs_ordrer.liste_id = $max_id and ordrer.id=pbs_ordrer.ordre_id order by pbs_ordrer.id",__FILE__ . " linje " . __LINE__))) {
	$kan_afsluttes=1;
#echo "D $kan_afsluttes<br>";
}


$x=0;
$q=db_select("select * from pbs_liste order by id desc",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$x++;
	$id[$x]=$r['id'];
	if ($x==1 && $r['afsendt']) {
		$liste_dato=date('d-m-Y');
		$tmp=$id[$x]+1;
    if ($popup) print "<tr><td onClick=\"javascript:pbsfile=window.open('pbsfile.php?id=$tmp','pbsfile','".$jsvars."');pbsfile.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\"><u>$tmp</u></td>\n";
		else print "<tr><td><a href=pbsfile.php?id=$tmp>$tmp</a></td>";
		print "<td>$liste_dato</td>";
		if ($kan_afsluttes) {
			if ($popup) print "<td onClick=\"javascript:pbsfile=window.open('pbsfile.php?id=$tmp&afslut=ok','pbsfile','".$jsvars."');pbsfile.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\" align=right><u>afslut</u></td>\n";
			else print "<td align=right><a href=pbsfile.php?id=$tmp&afslut=ok>afslut</a></td>";
			print "</tr>";
		}
	}
 	if ($popup) print "<tr><td onClick=\"javascript:pbsfile=window.open('pbsfile.php?id=$id[$x]','pbsfile','".$jsvars."');pbsfile.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\"><u>$r[id]</u></td>\n";
	else print "<tr><td><a href=pbsfile.php?id=$id[$x]>$id[$x]</a></td>";
	$liste_dato=dkdato($r['liste_date']);
	print "<td>$liste_dato</td>";
	if (!$r['afsendt'])	{
		if ($popup) print "<td onClick=\"javascript:pbsfile=window.open('pbsfile.php?id=$id[$x]&afslut=ok','pbsfile','".$jsvars."');pbsfile.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\" align=right><u>afslut</u></td>\n";
		else	print "<td align=right><a href=pbsfile.php?id=$id[$x]&afslut=ok>afslut</a></td>";
#		$vis_ny=1;
	}
	print "</tr>";
/* 	# udelade 20103105 - Har tilsyneladende ingen anden funktion en at dobbeltudskrive ???
 if ($vis_ny) {
	$liste_dato=date('d-m-Y');
	$tmp=$r['id']+1;
	if ($popup) print "<tr><td onClick=\"javascript:pbsfile=window.open('pbsfile.php?id=$r[id]','pbsfile','".$jsvars."');pbsfile.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\"><u>C $r[id]</u></td>\n";
	else print "<tr><td><a href=pbsfile.php?id=1>C 1</a></td>";
	print "<td>CC $liste_dato</td>";
	if ($popup) print "<td onClick=\"javascript:pbsfile=window.open('pbsfile.php?id=$r[id]&afslut=ok','pbsfile','".$jsvars."');pbsfile.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\" align=right>afslut</td>\n";
	else	print "<td align=right><a href=pbsfile.php?id=$tmp&afslut=ok><u>afslut</u></a></td>";
	print "</tr>";
	}
*/
}
print "</tbody></table>";
######################################################################################################################################
?>
