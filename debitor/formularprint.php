<?php #topkode_start
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------debitor/formularprint-----patch 4.1.1---2026-02-19------
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
 
/**
 * Find the appropriate background PDF file for a given background, document type and department.
 *   
 *
 * @param string $background  The desired background (e.g., 'Dansk', 'Product_background', 'All')
 * @param string $file_type The document type ('bg', 'ordrer_bg', 'faktura_bg', 'tilbud_bg')
 * @param int    $department Department ID (0 = All)
 * @return string|null Full path to the PDF file, or null if not found
 */
function find_background_file($background, $file_type, $department) {
    global $db_id;
    
    $base_dir = "../logolib/$db_id/";
    $dept_dir = ($department > 0) ? "../logolib/$db_id/$department/" : $base_dir;
    
    $is_default = in_array(strtolower($background), ['dansk', 'danish', 'all', '']);
    $lang_suffix = $is_default ? '' : '_' . strtolower($background);

    // Department + language-specific
    if ($department > 0 && !$is_default) {
        $path = $dept_dir . $file_type . $lang_suffix . ".pdf";
        if (file_exists($path)) return $path;
    }

    //  Department + no suffix (covers Dansk and All)
    if ($department > 0) {
        $path = $dept_dir . $file_type . ".pdf";
        if (file_exists($path)) return $path;
    }

    //  Base + language-specific
    if (!$is_default) {
        $path = $base_dir . $file_type . $lang_suffix . ".pdf";
        if (file_exists($path)) return $path;
    }

    //  Base + no suffix (final fallback)
    $path = $base_dir . $file_type . ".pdf";
    if (file_exists($path)) return $path;

    // Generic bg.pdf fallback
    if ($file_type !== 'bg') {
        $path = $base_dir . "bg.pdf";
        if (file_exists($path)) return $path;
    }

    return null;
}
//check Post for returside
$returside = if_isset($_POST['returside']);
if (isset($_GET['id']) && $_GET['id']){
    $id = if_isset($_GET['id']);
    
    $formular = if_isset($_GET['formular']);
    $lev_nr = if_isset($_GET['lev_nr']);
    $udskriv_til = if_isset($_GET['udskriv_til']);
    $locat = if_isset($_GET['locat']);
    
    // Get order's background and department
    $r = db_fetch_array(db_select("SELECT sprog, afd FROM ordrer WHERE id='$id'", __FILE__ . " linje " . __LINE__));
    $sprog = isset($r['sprog']) ? $r['sprog'] : 'Dansk';
    $order_department = isset($r['afd']) ? (int)$r['afd'] : 0;
   
    // Allow department override via GET parameter (e.g., for testing or admin)
    $department = isset($_GET['department']) ? (int)$_GET['department'] : $order_department;
    
    // Map form type to background file type
    $file_type = 'bg'; // default for all forms
    if ($formular == 'ordre') {
        $file_type = 'ordrer_bg';
    } elseif ($formular == 'faktura') {
        $file_type = 'faktura_bg';
    } elseif ($formular == 'tilbud') { 
        $file_type = 'tilbud_bg';
    }
    
    // Find the appropriate background file using the new fallback logic
    $background_file = find_background_file($sprog, $file_type, $department);
    
    if ($udskriv_til == 'ingen') {
        $svar = 'OK';
    } else {
        // Pass the found background file path to formularprint
        $svar = formularprint($id, $formular, $lev_nr, $charset, $udskriv_til, $background_file);
    }
    
    if ($svar && $svar != 'OK') {
        print "<BODY onLoad=\"javascript:alert('$svar')\">";
        if ($returside) {
            print "<meta http-equiv=\"refresh\" content=\"1;URL=$returside\">";
            exit;
        }
        print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/ordre.php?id=$id\">";
        exit;
    }
}

// Redirect after printing
if ($returside) {
    print "<meta http-equiv=\"refresh\" content=\"1;URL=$returside&locat=$locat\">";
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

