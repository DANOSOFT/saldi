<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------------- systemdata/diverse.php ------------------ ver 4.0.5 -- 2022-05-14 --
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
// Copyright (c) 2003-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 20230306 MSC - Edited screenshot message

@session_start();
$s_id=session_id();
ob_start();

$title="Beskeden ikke sendt";
$modulnr=1;
$diffkto=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");


include_once '../includes/top_header.php';
include_once '../includes/top_menu.php';
print "<div id=\"header\">"; 
print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
print "<div class=\"headerTxt\">$title</div>";     
print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
print "</div>";
print "<div class='content-noside'>";

print "
<center>
Beskeden blev desværre ikke sendt, prøv igen. Filen kan være for stor, da filer over 4mb kan ikke sendes.<br>
<a href='feedbackmail.php'>Klik her for at komme tilbage</a>
</center>";



include_once '../includes/topmenu/footer.php';

?>