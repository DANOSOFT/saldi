<?php
//                         ___   _   _   __  _
//                        / __| / \ | | |  \| |
//                        \__ \/ _ \| |_| | | |
//                        |___/_/ \_|___|__/|_|
//
// ----------lager/slet_shopbinding.php----------lap 4.1.1-----2024-08-05-------
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
// Copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------
//

@session_start();
$s_id=session_id();

$title="Slet shopbinding";
$modulnr=9;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (isset($_GET['id'])) db_modify("delete from shop_varer where saldi_id='$_GET[id]'",__FILE__ . " linje " . __LINE__);
print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$_GET[id]\">";
?>
