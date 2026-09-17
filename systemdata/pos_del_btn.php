<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- systemdata/pos_ryk_knap.php ---------- lap 3.9.9----2023.03.15-------
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
// Copyright (c) 2012-2023 saldi.dk aps
// ----------------------------------------------------------------------
@session_start();
$s_id = session_id();

include ("../includes/connect.php");
include ("../includes/online.php");
include ("../includes/std_func.php");

$post = json_decode(file_get_contents('php://input'));

$row = $post->row;
$col = $post->col;
$menu = $post->menu;

# Set TO = FROM with location
$qtxt = "DELETE FROM pos_buttons WHERE menu_id = '$menu' AND col = '$col' AND row = '$row'";
db_modify($qtxt, __FILE__ . " linje " . __LINE__);

?>
