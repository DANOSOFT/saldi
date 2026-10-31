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
// 20261005 Sawaneh G7.1: employees live in Indstillinger » Organisation » Ansatte (list + card per employee); this page only
//                  redirects, to the employee's card when an id is given. ansatte_load/_body/_save.php are no longer used.

@session_start();
$s_id = session_id();
include("../includes/connect.php");
$modulnr = 1;
include("../includes/online.php");
$eid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
header("Location: settingsSection.php?s=organisation.employees&moved=ansatte" . ($eid > 0 ? "&item=emp_$eid" : ""));
exit;
