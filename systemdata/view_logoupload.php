<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------systemdata/view_logoupload.php------------patch 4.1.1-----2026-01-21------
//                               LICENSE      
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
// 20260102 LOE Updated to use department format for viewing uploaded background.

session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

global $db_id;
$current_sprog = isset($_GET['sprog']) ? $_GET['sprog'] : 'Dansk';
#echo "$db_id";
$url  = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
$url .= $_SERVER['SERVER_NAME'];
$url .= htmlspecialchars($_SERVER['REQUEST_URI']);
$urlstr = dirname(dirname($url));
$dataurl = isset($_SERVER['HTTPS']) ? 'https' : 'http'; 

$baggrund=if_isset($_GET['vis']);

function find_background_file($db_id, $baggrund, $current_sprog, $department = null) {
    // First check if it's a full path (from formularprint)
    if (file_exists($baggrund)) {
        return $baggrund;
    }
    
    // Normalize language
    $lang_lower = strtolower($current_sprog);
    $is_default_lang = ($lang_lower == 'dansk' || $lang_lower == 'danish');
    
    // Check db_id directory with department if specified
    if ($department && $department > 0) {
        // 1. Check Department + Language Specific (if not Default)
        if (!$is_default_lang) {
             $lang_suffix = "_" . $lang_lower;
             $dept_path_lang = "../logolib/$db_id/$department/{$baggrund}{$lang_suffix}.pdf";
             if (file_exists($dept_path_lang)) {
                 return $dept_path_lang;
             }
        }
        
        // 2. Check Department + Default
        $dept_path = "../logolib/$db_id/$department/{$baggrund}.pdf";
        if (file_exists($dept_path)) {
            return $dept_path;
        }
    }

    // Check db_id directory (default/main)
    // 3. Check Global + Language Specific (if not Default)
    if (!$is_default_lang) {
         $lang_suffix = "_" . $lang_lower;
         $db_path_lang = "../logolib/$db_id/{$baggrund}{$lang_suffix}.pdf";
         if (file_exists($db_path_lang)) {
             return $db_path_lang;
         }
    }
    
    // 4. Check Global + Default
    $db_path = "../logolib/$db_id/{$baggrund}.pdf";
    if (file_exists($db_path)) {
        return $db_path;
    }
    
    return false;
}

// Get department from URL if available
$department = isset($_GET['department']) ? $_GET['department'] : null;

$actual_file = find_background_file($db_id, $baggrund, $current_sprog, $department);

if ($actual_file && file_exists($actual_file)) {
    $usefile = $actual_file;
} else {
    $usefile = false;
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund height=\"1%\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=\"logoupload.php?sprog=$current_sprog" . ($department ? "&department=$department" : "") . "\">Close</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Print</td>";
print "<td width=\"10%\" $top_bund align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">&nbsp;</td>";
print "<tr><td width=\"100%\" height=\"100%\" align=\"center\" valign=\"top\" colspan=\"3\">";

if ($usefile && file_exists($usefile)) {
    print "<div style=\"height:100%;\">
    <iframe style=\"width:100%;height:100%;\" src=\"$usefile#toolbar=0&navpanes=0&scrollbar=0\">
        <p>Your browser cannot display this file. <a href=\"$usefile\">Download PDF</a></p>
    </iframe>
    </div>";
} else {
    $dept_info = $department ? " (department $department)" : "";
    print "<div style=\"height:100%; text-align:center; padding-top:50px;\">
        <h2>File not found</h2>
        <p>The file '$baggrund.pdf' could not be found for language '$current_sprog'$dept_info.</p>
        <p><a href=\"logoupload.php?sprog=$current_sprog" . ($department ? "&department=$department" : "") . "\">Back to upload</a></p> 
    </div>";
}

print "</td></tr>";
print "</tbody></table>";

?>