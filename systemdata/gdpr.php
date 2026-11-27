<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//
// --- systemdata/gdprInc/gdprLogic.php --- ver 5.1.1 --- 2026-11-13 --
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------------
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
@session_start();
$s_id=session_id();

include("../includes/connect.php");
$modulnr = 1; // 20260928 Sawaneh Security 4.0 (A8)
$permission_key = 'system.indstillinger';
include("../includes/online.php");
include("../includes/std_func.php");

// 20261005 Sawaneh The clean-up lives in Indstillinger » Virksomhed » Persondata (settings redesign G1.5); this page only redirects.
header("Location: settingsSection.php?s=company.gdpr&moved=gdpr");
exit;

?>
