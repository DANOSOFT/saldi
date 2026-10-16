<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------debitor/mailTxt.php---lap 3.9.5------2020-11-11----
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
//
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20201111 PHR rehamed to mailTxt.php and added ordinary mail til customers not using MySale
// 20261001 Sawaneh Subject and text saved by key (update_settings_value) instead of the posted row id; read with NULL = 0.

// 20261003 Sawaneh G6.3: the mail texts live under Indstillinger » Dokumenter & e-mail » E-mail; this file only redirects there.
@session_start();
$s_id=session_id();
$modulnr=6;
include("../includes/connect.php");
include("../includes/online.php");
$valg = isset($_GET['valg']) ? (string) $_GET['valg'] : '';
$field = ($valg == 'historik') ? 'documents.email.customer_text' : 'documents.email.mysale_text';
header("Location: ../systemdata/settingsSection.php?s=documents.email&moved=mailTxt&field=$field");
exit;
