<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ ^ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/projekter.php-----patch 4.0.8 ----2023-07-22-----------
//                           LICENSE
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
// 20160118 div smårettelser.
// 20210211 PHR Some cleanup
// 20210710 LOE Some texts translated 
// 20211018 LOE Some bugs fixed + 20211019
// 20230323 PBLM Fixed minor error
// 20261005 Sawaneh Projects live in Indstillinger » Organisation » Projekter (settings redesign 4c, G7.3); this page only redirects.

@session_start();
$s_id = session_id();
include("../includes/connect.php");
$modulnr = 1;
$permission_key = 'system.indstillinger';
include("../includes/online.php");
header("Location: settingsSection.php?s=organisation.projects&moved=projekter");
exit;
