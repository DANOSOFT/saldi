<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------debitor/mySaleMailTxt.php.php---lap 3.9.5------2020-10-25----
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
// Copyright (c) 2020 saldi.dk aps
// ----------------------------------------------------------------------
// 20261001 Sawaneh Subject and text saved by key (update_settings_value) instead of the posted row id.

#ob_start();
// 20261004 Sawaneh The mySale mail text lives in Indstillinger » Dokumenter » E-mail (settings redesign G6.3); this page only redirects.

@session_start();
$s_id = session_id();
include("../includes/connect.php");
$modulnr = 1;
$permission_key = 'settings.email';
include("../includes/online.php");
header("Location: ../systemdata/settingsSection.php?s=documents.email#documents.email.mysale_text");
exit;
