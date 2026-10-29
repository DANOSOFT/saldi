<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/enheder.php --- lap 3.9.9 --- 2021-02-11 ---
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
// Copyright (c) 2003-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 2019.02.25 MSC - Rettet topmenu design til og isset fejl
// 2021.02.11 PHR	- Some cleanup
// 20261004 Sawaneh Security 4.0 (A12e): enh_id/mat_id from the URL cast to integers before SQL and HTML.

	
// 20261005 Sawaneh Units and materials live in Indstillinger » Varer » Enheder & materialer (settings redesign 4c, G5.2); this page only redirects.

@session_start();
$s_id = session_id();
include("../includes/connect.php");
$modulnr = 1;
$permission_key = 'system.indstillinger';
include("../includes/online.php");
header("Location: settingsSection.php?s=items.units&moved=enheder");
exit;
