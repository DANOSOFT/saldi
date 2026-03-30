<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|
//
// --- systemdata/ansatte.php --- patch 4.0.8 --- 2023-079-25 ---
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20160303 PHR indsat manglende '</form>'
// 20210711 LOE - Translated some texts to Norsk and English from Dansk
// 20220614 MSC - Implementing new design
// 20230925 PHR - PHP8

@session_start();
$s_id=session_id();

$css="../css/standard.css";
$title="Personalekort";
$modulnr=1;

$afd_nr=array();
	
include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include_once('settings_layout.php');

 if ($_GET) {
	$id = $_GET['id'];
	$returside= $_GET['returside'];
	$fokus = $_GET['fokus'];
	$konto_id=$_GET['konto_id'];
 }
if ($_POST) {
	include("ansatte_save.php");
}
if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n<div class=\"headerbtnLft\"></div>\n</div>";
	print "<div class=\"maincontentLargeHolder\">\n";
} elseif ($menu == 'S') {
	include("top.php");
}

settings_layout_start($menu, 'ansatte');

print "<table border=\"0\" cellspacing=\"0\" class=\"dataTableSys\"><tbody>";

include("ansatte_load.php");

print "<form name=\"ansatte\" action=\"ansatte.php?konto_id=$konto_id\" method=\"post\">";
include("ansatte_body.php");

print "<tr><td><br></td></tr>";
print "<tr><td><br></td></tr>";
print "<td><br></td><td><br></td><td><br></td>";
PRINT "<td align=center><input type=\"submit\" class='green medium button' style='width:150px;' accesskey=\"g\" value=\"".findtekst(471, $sprog_id)."\" name=\"submit\"></td>";
print "</form>";

print "</tbody></table>";

settings_layout_end($menu);
?>
