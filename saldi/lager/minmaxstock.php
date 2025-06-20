<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------lager/minmaxstock.php------------patch 3.9.0-------2020.03.13----
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
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$css="../css/standard.css";
 
$title="Beholdningsrapport";
$modulnr=15;

$vk_kost=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$afd=if_isset($_GET['afd']);
$vgrp=if_isset($_GET['vgrp']);
$vnr=if_isset($_GET['vnr']);
$vname=if_isset($_GET['vname']);

if ($popup) $returside="../includes/luk.php";
else $returside="rapport.php?varenr=$vnr&afd=$afd&varegruppe=$vrgp&varenavn=$vname";

$lokMinMax=if_isset($_POST['lokMinMax']);

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\"> 
			<div class=\"headerbtnLft\"></div>
			<span class=\"headerTxt\"></span>";     
	print "<div class=\"headerbtnRght\"></div>";       
	print "</div><!-- end of header -->
		<div class=\"maincontentLargeHolder\">\n";
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" align=\"center\"><tbody>"; #A
	print "<tr><td width=100%>";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	print "<td width=\"10%\" $top_bund><a href=$returside accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund>Varerapport - forside</td>";
	print "<td width=\"10%\" $top_bund></td></tr>";
	print "</tbody></table></td></tr>"; #B slut
	print "</tr><tr><td height=\"99%\" \"width=100%\" align=\"center\" valign=\"middle\">";
}
$x=0;
$qtxt = "select kodenr,beskrivelse from grupper where art = 'VG' and box8 = 'on' ";
if ($vgrp) $qtxt.= " and kodenr='$vgrp' ";
$qtxt.= "order by kodenr";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$vGr[$x]=$r['kodenr'];
	$vGrDescription[$x]=$r['beskrivelse'];
	$x++;
}
$x=0;
$qtxt = "select kodenr,beskrivelse from grupper where art = 'LG' ";
if ($afd) $qtxt.= "and kodenr='$afd' ";
$qtxt.= "order by kodenr";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$stk[$x]=$r['kodenr'];
	$stkDescription[$x]=$r['beskrivelse'];
	$x++;
}
$x=0;
$qtxt = "select id,varenr,beskrivelse,min_lager,max_lager,gruppe from varer where lukket != 'on' and min_lager > '0' ";
if ($vnr && $vnr != '*') {
	$vnr=str_replace('*','%',$vnr);
	$qtxt.= "and (varenr like '$vnr' or lower(varenr) like '". strtolower($vnr) ."' ";
	$qtxt.= "or upper(varenr) like '". strtoupper($vnr) ."') ";
}
if ($vname && $vname != '*') {
	$vname=str_replace('*','%',$vname);
	$qtxt.= "and (beskrivelse like '$vname' or lower(beskrivelse) like '". strtolower($vname) ."' ";
	$qtxt.= "or upper(beskrivelse) like '". strtoupper($vname) ."') ";
}
$qtxt.= "order by gruppe, varenr";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if (in_array($r['gruppe'],$vGr)) {
		$itemId[$x]=$r['id'];
		$itemNo[$x]=$r['varenr'];
		$itemGrp[$x]=$r['gruppe'];
		$itemMin[$x]=$r['min_lager']*1;
		$itemMax[$x]=$r['max_lager']*1;
		$itemDescription[$x]=$r['beskrivelse'];
		$x++;
	}
}
$x=0;
$qtxt = "select * from lagerstatus ";
if ($afd) $qtxt.= "where lager = '$afd' ";
$qtxt.= "order by vare_id,variant_id,lager";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if (in_array($r['vare_id'],$itemId)) {
		$location[$x]=$r['lok1'];
		$stockItemId[$x]=$r['vare_id'];
		$variant_id[$x]=$r['variant_id'];
		$stockNo[$x]=$r['lager'];
		$stock[$x]=$r['beholdning'];
#if ($stockItemId[$x]==809) echo __line__." 809 $stockNo[$x] -> $stock[$x]<br>";
#if ($stockItemId[$x]==1746) echo __line__." 1746 $stockNo[$x] -> $stock[$x]<br>";
		$x++;
	}
}
$bgc=$bgcolor2;
print "<table border=\"0\" cellspacing=\"2\" cellpadding=\"0\" align=\"center\"><tbody>";
print "<tr bgcolor='$bgc'><td>Afd</td><td>Varenr</td><td>Beskrivelse</td><td width='80px'>Beholdning</td>";
print "<td width='80px' align='center'>Min</td><td width='80px' align='center'>Max</td>";
print "<td width='80px' align='center'>Køb</td><tr>";
for ($a=0;$a<count($vGr);$a++) {
	if (in_array($vGr[$a],$itemGrp)) {
		for ($b=0;$b<count($itemId);$b++) {
#			$stocksum=0;
			for ($c=0;$c<count($stk);$c++) {
				for ($d=0;$d<count($stock);$d++) {
#if ($ItemId[$b]==809 && $stockNo[$d]=='3') echo __line__." 809 $stockNo[$d] -> $stock[$d]<br>";
#if ($ItemId[$b]=1746 && $stockNo[$d]=='3') echo __line__." 1746 $stockNo[$d] -> $stock[$d] ($b)<br>";
				if ($stk[$c]==$stockNo[$d] && $itemId[$b]==$stockItemId[$d]) $stocksum+=$stock[$d];
#if ($ItemId[$b]=1746) echo "$itemId[$b]==$stockItemId[$d] && $stockNo[$d]==$stk[$c] && $itemMin[$b]>0 && $itemMax[$b]>0 && $itemGrp[$b]==$vGr[$a] && $itemMin[$b]+1<$stock[$d]<br>";
				if ($itemId[$b]==$stockItemId[$d] && $stockNo[$d]==$stk[$c] && $itemMin[$b]>0 && $itemMax[$b]>0 && $itemGrp[$b]==$vGr[$a] && $itemMin[$b]+1 >= $stock[$d]) {
					if (!isset($stock[$d])) $stock[$d]=0;
					($bgc==$bgcolor)?$bgc=$bgcolor2:$bgc=$bgcolor;
					print "<tr bgcolor='$bgc'>";
					print "<td>$stkDescription[$c]</td><td>$itemNo[$b]</td><td>$itemDescription[$b]</td><td align='right'>". dkdecimal($stock[$d]) ."</td>";
					print "<td align='right'>". dkdecimal($itemMin[$b]) ."</td><td align='right'>". dkdecimal($itemMax[$b]) ."</td>";
					print "<td align='right'>". dkdecimal($itemMax[$b]-$stock[$d]) ." </td></tr>";
				}
				}
			}
		}
	}
}
print "</tbody></table></td></tr>";
?>
</html>

