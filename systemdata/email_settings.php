<?php
// ------------systemdata/email_settings.php-----patch 4.0.8 ----2025-01-26--
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// Language-specific sender email settings
// 20260710 SZ Added Settings search box to standalone/topmenu layouts (settingsSearch.php/.js/.css)
// 20260916 Sawaneh Declared $permission_key (roles & permissions, phase 3)

// 20261003 Sawaneh G6.3: the page has landed in Indstillinger » Dokumenter & e-mail » E-mail; this file only redirects there.
@session_start();
$s_id=session_id();
include("../includes/connect.php");
$modulnr = 1;
$permission_key = 'settings.email';
include("../includes/online.php");
header("Location: settingsSection.php?s=documents.email&moved=email_settings#sub-sender");
exit;
