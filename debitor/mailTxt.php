<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------debitor/mailTxt.php---ver 5.1.0------2026-11-03----
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20201111 PHR rehamed to mailTxt.php and added ordinary mail til customers not using MySale
// 20261001 SS Subject and text saved by key (update_settings_value) instead of the posted row id; read with NULL = 0.

// 20261003 SS G6.3: the mail texts live under Indstillinger » Dokumenter & e-mail » E-mail; this file only redirects there.
// 20261103 CL/ASR QA: output is buffered, so the redirect header still goes out after online.php has printed the page head (it only worked where PHP's output_buffering is on).
ob_start();
@session_start();
$s_id=session_id();
$modulnr=6;
include("../includes/connect.php");
include("../includes/online.php");
$valg = isset($_GET['valg']) ? (string) $_GET['valg'] : '';
$field = ($valg == 'historik') ? 'documents.email.customer_text' : 'documents.email.mysale_text';
header("Location: ../systemdata/settingsSection.php?s=documents.email&moved=mailTxt&field=$field");
exit;
