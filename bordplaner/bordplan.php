<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- bordplaner/bordplan.php ---------- lap 3.9.9----2021.02.13-------
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
// Copyright (c) 2012-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 2014.11.11 Bordplan bruges nu også selvom der ikke er individuel bordplan
// 2015.01.10 Bord sætttes kun som optaget hvis der er et bordnr.
// 2016.10.14 Bord sætttes kun som optaget hvis der er ordrelinjer.
// 2018.05.14 Tilføjet mulighed for "underborde". Søg "$_GET['bordplan']".
// 2019.07.10 Rettet $flyt til $bord[$flyt] så bordnavn og ikke bord ID vises i overskrift ved del / ftyt

@session_start();
$s_id=session_id();
ob_start();

$modulnr=5;
$z=NULL;
$title="POS_ordre";
$css="../css/pos.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr='2'",__FILE__ . " linje " . __LINE__)); 
($r['box7'])?$bord=explode(chr(9),$r['box7']):$bord=NULL; #20140508
(isset($_GET['flyt']))?$flyt=$_GET['flyt']:$flyt=NULL;
$id=if_isset($_GET['id']);
$delflyt=if_isset($_GET['delflyt']);
$optaget=array();
$bnr=array();
$dubletter=array();
$x=0;
$y=0;
$q=db_select("select id,nr,hvem from ordrer where art = 'PO' and status < 3",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$bordnr=$r['nr'];
	if (in_array($bordnr,$bnr)) {
		$dubletter[$z]=$bordnr;
		$z++;
	}
	$bnr[$y]=$bordnr;
	if (is_numeric($r['nr'])) {
		if ($r2=db_fetch_array(db_select("select id from ordrelinjer where ordre_id='$r[id]' limit 1",__FILE__ . " linje " . __LINE__))) {
			$optaget[$x]=$r['nr'];
			$x++;
		}
	}
	$y++;
}
if ($delflyt) print "<b>Vælg det bord $bord[$flyt] skal deles til</b><br>"; 
elseif ($flyt || $flyt=='0') print "<b>Vælg det bord $bord[$flyt] skal flyttes til</b><br>"; 
if (count($dubletter)) {
	for ($z=0;$z<count($dubletter);$z++) {
		for ($y=0;$y<count($bord);$y++) {
			
		}
	}
}



$w="17px";
$h="17px";
$bg1="ff0000";
$bg2="00ff00";
$bg3="0000ff";
if (isset($_GET['bordplan']) && file_exists($_GET['bordplan'])) include($_GET['bordplan']);
elseif (file_exists("bordplan_$db_id.php")) include("bordplan_$db_id.php");
else {
	$stil="STYLE=\"
		display: table-cell;
		moz-border-radius:10px;
		-webkit-border-radius:10px;
		width:150px;
		height:50px;
		text-align:center;
		vertical-align:middle;
		font-size:30px; 
		border: 1px solid ##BEBCCE;
		background-color:";
		
	print "<table><tbody><tr>";
	$y=0;
		for ($x=0;$x<count($bord);$x++) {
		if ($y==4) {
			print "</tr><tr>";
			$y=0;
		}
		(in_array($x,$optaget))?$bgcolor=$bg1:$bgcolor=$bg2;
		$style=$stil."$bgcolor\"";
		if ($flyt || $flyt=='0') {
			if ($x!=$flyt && $delflyt) $href="../debitor/pos_ordre.php?id=$id&flyt_til=$x&delflyt=$delflyt";
			else $href="../debitor/pos_ordre.php?id=$id&flyt_til=$x";
		} else $href="../debitor/pos_ordre.php?bordnr=$x";
		print "<td><input type=\"button\" $style onclick=\"window.location.href='$href'\" value=\"$bord[$x]\"></td>\n";
		$y++;
	}
	print "</tr></tbody></table>";
}

function vis_bord($bordnr,$cs,$rs) {
global $h;
global $w;
global $th;
global $bg1;
global $bg2;
global $bg3;
global $optaget;
global $flyt;
global $id;
global $bord;
global $delflyt;

$rw=$w*$cs.'px';
$rh=$h*$rs.'px';
if (!$th) $th=$h;

if (!isset($bord[$bordnr])) $bord[$bordnr]=NULL;
if (strpos($bord[$bordnr]," ") && strpos($bord[$bordnr]," ")) list($tmp,$bordnavn)=explode(" ",$bord[$bordnr]);
else $bordnavn=$bord[$bordnr];
(in_array($bordnr,$optaget))?$bgcolor=$bg1:$bgcolor=$bg2;
	if ($flyt || $flyt=='0') {
	if ($bordnr!=$flyt && $delflyt) print "<td align=\"center\" colspan=\"$cs\" rowspan=\"$rs\"><a style=\"text-decoration:none\" href=\"../debitor/pos_ordre.php?id=$id&flyt_til=$bordnr&delflyt=$delflyt\"><input type=\"button\" style=\"width:$rw;height:$rh;text-align:center;font-size:$th; background-color:$bgcolor;\" value= \"$bordnavn\"></a></td>";
		elseif ($bgcolor==$bg1 && $bordnr!=$flyt) print "<td align=\"center\" colspan=\"$cs\" rowspan=\"$rs\"><style=\"text-decoration:none\"><input type=\"button\" style=\"width:$rw;height:$rh;text-align:center;font-size:$th; background-color:$bgcolor;\" value= \"$bordnavn\"></td>";
		elseif ($bordnr==$flyt) print "<td align=\"center\" colspan=\"$cs\" rowspan=\"$rs\"><a style=\"text-decoration:none\" href=\"../debitor/pos_ordre.php?id=$id&flyt_til=$bordnr\"><input type=\"button\" style=\"width:$rw;height:$rh;text-align:center;font-size:$th; background-color:$bg3;\" value= \"$bordnavn\"></a></td>";
		else print "<td align=\"center\" colspan=\"$cs\" rowspan=\"$rs\"><a style=\"text-decoration:none\" href=\"../debitor/pos_ordre.php?id=$id&flyt_til=$bordnr\"><input type=\"button\" style=\"width:$rw;height:$rh;text-align:center;font-size:$th; background-color:$bgcolor;\" value= \"$bordnavn\"></a></td>";
	} else print "<td align=\"center\" colspan=\"$cs\" rowspan=\"$rs\"><a style=\"text-decoration:none\" href=\"../debitor/pos_ordre.php?bordnr=$bordnr\"><input type=\"button\" style=\"width:$rw;height:$rh;text-align:center;font-size:$th; background-color:$bgcolor;\" value= \"$bordnavn\"></a></td>";
}
?>
