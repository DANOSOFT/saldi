<?php #topkode_start
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------debitor/formularprint-----patch 4.1.1---2026-01-15------
// 							LICENSE
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
// Copyright (c) 2003-2026 saldi.dk aps
// ----------------------------------------------------------------------
// 
// 17012013 Oprydning i forb. med fejlsøgning i ret_genfakt.php
// 08042014 Ændret returside til ordre.php
// 20150106 Indsat "returside"
// 20170505 Ved $udskriv_til=='ingen' returneres uden udskrift.
// 20260102 LOE Added department support for background files

session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/formfunk.php");
include("../includes/var2str.php");

if (isset($_GET['id']) && $_GET['id']){
    $id=if_isset($_GET['id']);
    $returside=if_isset($_GET['returside']);
    $formular=if_isset($_GET['formular']);
    $lev_nr=if_isset($_GET['lev_nr']);
    $udskriv_til=if_isset($_GET['udskriv_til']);
    $bg="nix";
    
    $r = db_fetch_array(db_select("SELECT sprog, afd FROM ordrer WHERE id='$id'", __FILE__ . " linje " . __LINE__));
    $sprog = isset($r['sprog']) ? $r['sprog'] : 'Dansk';
    $order_department = isset($r['afd']) ? $r['afd'] : 0;

    // Check if there's a department parameter in the URL
    $department = isset($_GET['department']) ? (int)$_GET['department'] : $order_department;

    $form_type = '';
    switch ($formular) {
        case 1: $form_type = 'tilbud'; break;
        case 2: $form_type = 'ordrer'; break;
        case 4: $form_type = 'faktura'; break;
        default: $form_type = 'bg'; // fallback
    }

    // Convert language to directory format: lowercase and replace spaces with underscores
    $lang_dir_name = strtolower(str_replace(' ', '_', $sprog));

    // Build the background file paths to check
    // Check new structure first (language_department), then fall back to old structures if needed
    $backgrounds = [];

    //  language_department directory
    if ($department > 0) {
        // Form-specific background in language_department directory
        $backgrounds[] = "../logolib/{$lang_dir_name}_{$department}/{$form_type}_bg.pdf";
        // Generic background in language_department directory
        $backgrounds[] = "../logolib/{$lang_dir_name}_{$department}/bg.pdf";
    }

    // Old structure: afd$department directory (for backward compatibility)
    if ($department > 0) {
        $backgrounds[] = "../logolib/afd{$department}/{$form_type}_bg.pdf";
        $backgrounds[] = "../logolib/afd{$department}/bg.pdf";
    }

    // Old global directory structure (for backward compatibility)
    global $db_id;
    if (!isset($db_id)) {
        $db_id = isset($_SESSION['db_id']) ? $_SESSION['db_id'] : 'default';
    }
    $backgrounds[] = "../logolib/{$db_id}/{$form_type}_bg.pdf";
    $backgrounds[] = "../logolib/{$db_id}/bg.pdf";

    $background_file = null;
    foreach ($backgrounds as $bg) {
        if (file_exists($bg)) {
            $background_file = $bg;
            break;
        }
    }

    if ($udskriv_til=='ingen') $svar='OK';
    else $svar=formularprint($id, $formular, $lev_nr, $charset, $udskriv_til, $background_file);
    
    if ($svar && $svar!='OK') {
        print "<BODY onLoad=\"javascript:alert('$svar')\">";
        if ($returside) {
            print "<meta http-equiv=\"refresh\" content=\"1;URL=$returside\">";
            exit;
        }
        print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/ordre.php?id=$id\">";
        exit;
    }
}

if ($returside) {
    print "<meta http-equiv=\"refresh\" content=\"1;URL=$returside\">";
    exit;
} elseif ($popup) {
    print "<meta http-equiv=\"refresh\" content=\"1;URL=../includes/luk.php\">";
    exit;
} elseif (is_numeric($id) && $id > 1) {
    print "<meta http-equiv=\"refresh\" content=\"1;URL=ordre.php?id=$id\">";
    exit;
} else {
    print "<meta http-equiv=\"refresh\" content=\"1;URL=ordreliste.php\">";
}
?>

