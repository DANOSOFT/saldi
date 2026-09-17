<?php
// ----------finans/importer.php---- patch 5.0.0 --- 2026-02-12 ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ---------------------------------------------------------------------- 
// 20140701 Mange ændring i forbindelse med indførelse af owncloud bilagsopbevaring
// 20260212 LOE Refactored to handle multiple files, added upload functionality, and improved delete operations.

@session_start();
$s_id=session_id();
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<center><middle>";

$bilag_id=$_GET['bilag_id'];
$kilde_id=$_GET['kilde_id'];
$kilde=$_GET['kilde'];
$db=$_GET['db'];

// Handle delete operations
if (isset($_GET['slet'])) {
    if ($_GET['slet'] == 'ok' && isset($_GET['filnavn'])) {
        // Delete single file
        $filnavn = $_GET['filnavn'];
        slet_bilag($bilag_id, $filnavn, $kilde_id, $kilde, false);
        
        // Redirect to clean URL
        $clean_url = "vis_bilag.php?bilag_id=$bilag_id&kilde_id=$kilde_id&kilde=$kilde&db=$db";
        print "<BODY onLoad=\"javascript:alert('Bilaget er slettet'); window.location.href='$clean_url';\">";
        exit;
    } elseif ($_GET['slet'] == 'all') {
        // Delete all files
        slet_bilag($bilag_id, '', $kilde_id, $kilde, true);
        
        // Redirect to clean URL
        $clean_url = "vis_bilag.php?bilag_id=$bilag_id&kilde_id=$kilde_id&kilde=$kilde&db=$db";
        print "<BODY onLoad=\"javascript:alert('Alle bilag er slettet'); window.location.href='$clean_url';\">";
        exit;
    }
}

// Handle file upload
if (isset($_FILES['uploadedfile']) && isset($_FILES['uploadedfile']['name'][0]) && $_FILES['uploadedfile']['name'][0]) {
    $fileCount = count($_FILES['uploadedfile']['name']);
    $uploadedFiles = 0;
    
    // Get storage settings
    $r = db_fetch_array(db_select("select * from grupper where art='bilag'", __FILE__ . " linje " . __LINE__));
    $box6 = $r['box6'];
    
    // Determine storage paths
    if (file_exists("../documents")) $nfs_mappe = 'documents';
    elseif (file_exists("../owncloud")) $nfs_mappe = 'owncloud';
    elseif (file_exists("../bilag")) $nfs_mappe = 'bilag';
    
    if ($kilde == "kassekladde" || $kilde == "ordrer") {
        $mappe = 'bilag';
        $undermappe = ($kilde == "kassekladde") ? "kladde_$kilde_id" : "ordrer";
    } else {
        $mappe = 'dokumenter';
        $undermappe = "debitor_$kilde_id";
    }
    
    // Create directories if they don't exist
    if (!file_exists("../".$nfs_mappe."/".$db."/".$mappe)) {
        mkdir("../".$nfs_mappe."/".$db."/".$mappe, 0777, true);
    }
    if (!file_exists("../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe)) {
        mkdir("../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe, 0777, true);
    }
    
    // Get existing documents
    $r_existing = db_fetch_array(db_select("select dokument from $kilde where id='$bilag_id'", __FILE__ . " linje " . __LINE__));
    $existing_docs = $r_existing['dokument'];
    $new_filenames = array();
    
    for($i = 0; $i < $fileCount; $i++) {
        $filnavn = basename($_FILES['uploadedfile']['name'][$i]);
        $tmp = "../temp/".$db."/".$filnavn;
        
        if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'][$i], "$tmp")) {
            // Create unique filename with timestamp
            $timestamp = time();
            $random = rand(1000, 9999);
            
            if ($kilde == "kassekladde" || $kilde == "ordrer") {
                $bilagfilnavn = "bilag_" . $bilag_id . "_" . $timestamp . "_" . $random;
            } else {
                $bilagfilnavn = "doc_" . $bilag_id . "_" . $timestamp . "_" . $random;
            }
            
            // copy to permanent storage
            $fra = $tmp;
            $til = "../".$nfs_mappe."/".$db."/".$mappe."/".$undermappe."/".$bilagfilnavn;
            
            if (copy($fra, $til)) {
				$new_filenames[] = $filnavn;
				$uploadedFiles++;
			
			} else { error_log("Failed to move uploaded file from $fra to $til"); }
        }
    }
    
    // Update database with all filenames (existing + new)
    if (count($new_filenames) > 0) {
        if ($existing_docs && trim($existing_docs) != '') {
            $updated_docs = $existing_docs . '|' . implode('|', $new_filenames);
        } else {
            $updated_docs = implode('|', $new_filenames);
        }
        
        db_modify("update $kilde set dokument='".db_escape_string($updated_docs)."' where id='$bilag_id'", __FILE__ . " linje " . __LINE__);
        
        // Redirect to clean URL
        $clean_url = "vis_bilag.php?bilag_id=$bilag_id&kilde_id=$kilde_id&kilde=$kilde&db=$db";
        print "<BODY onLoad=\"javascript:alert('$uploadedFiles file(s) uploaded'); window.location.href='$clean_url';\">";
        exit;
    }
}

// Get the document(s) from database
$r = db_fetch_array(db_select("select dokument from $kilde where id='$bilag_id'", __FILE__ . " linje " . __LINE__));
$dokumenter = $r['dokument'];

error_log("select dokument from $kilde where id='$bilag_id';");
// Split multiple documents (assuming pipe-separated format)
$filnavn_array = array();
if ($dokumenter) {
    if (strpos($dokumenter, '|') !== false) {
        $filnavn_array = explode('|', $dokumenter);
    } else {
        $filnavn_array = array($dokumenter);
    }
}

print "<br><br><table width=\"700px\" style=\"border: 3px solid rgb(180, 180, 255); padding: 10px;\">";
print "<tbody>";

// UPLOAD SECTION
print "<tr><td width=100% align=center>";
print "<h3>Upload New Files</h3>";
print "<form enctype=\"multipart/form-data\" action=\"vis_bilag.php?bilag_id=$bilag_id&kilde_id=$kilde_id&kilde=$kilde&db=$db\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000000\">";
print "Select file(s): <input class=\"inputbox\" name=\"uploadedfile[]\" type=\"file\" multiple /><br /><br />";
print "<input type=\"submit\" value=\"Upload\" />";
print "</form>";
print "<hr><br>";
print "</td></tr>";

// FILE LIST SECTION
print "<tr><td width=100% align=center>";

if (count($filnavn_array) > 0 && $filnavn_array[0] != '') {
    print "<h3>Attached Files</h3>";
    print "Click on filename to open, right-click to save<br><br>";
    
    print "<table width=\"100%\" border=\"1\" cellpadding=\"5\" cellspacing=\"0\">";
    print "<tr><th>Filename</th><th>Action</th></tr>";
    
    foreach ($filnavn_array as $filnavn) {
        $filnavn = trim($filnavn);
        if ($filnavn != '') {
            print "<tr>";
            print "<td><a href=\"../temp/$db/$filnavn\" target=\"_blank\">$filnavn</a></td>";
            print "<td><a onclick=\"return confirm('Delete this file?');\" ";
            print "href=\"vis_bilag.php?slet=ok&kilde=$kilde&kilde_id=$kilde_id&bilag_id=$bilag_id&db=$db&filnavn=$filnavn\">";
            print "Delete</a></td>";
            print "</tr>";
        }
    }
    print "</table>";
    
    // Add "Delete All" button if there are multiple files
    if (count($filnavn_array) > 1) {
        print "<br><br><hr><br>";
        print "<a onclick=\"return confirm('Delete ALL files?');\" ";
        print "href=\"vis_bilag.php?slet=all&kilde=$kilde&kilde_id=$kilde_id&bilag_id=$bilag_id&db=$db\" ";
        print "style=\"color: red; font-weight: bold;\">";
        print "Click here to delete ALL files</a>";
    }
} else {
    print "<p>No attached files</p>";
}

print "</td></tr></tbody></table>";


function slet_bilag($bilag_id, $filnavn, $kilde_id, $kilde, $slet_alle = false){
    global $db, $bruger_id, $exec_path;
    
    if (!isset($exec_path)) $exec_path = "/usr/bin";
    
    // Get current documents
    $r = db_fetch_array(db_select("select dokument from $kilde where id='$bilag_id'", __FILE__ . " linje " . __LINE__));
    $dokumenter = $r['dokument'];
    
    // Get storage settings
    $r = db_fetch_array(db_select("select * from grupper where art='bilag'", __FILE__ . " linje " . __LINE__));
    $box1 = $r['box1'];
    $box2 = $r['box2'];
    $box3 = $r['box3'];
    $box6 = $r['box6'];
    
    // Determine storage paths
    if (file_exists("../documents")) $nfs_mappe = 'documents';
    elseif (file_exists("../owncloud")) $nfs_mappe = 'owncloud';
    elseif (file_exists("../bilag")) $nfs_mappe = 'bilag';
    
    if ($kilde == "kassekladde" || $kilde == "ordrer") {
        $mappe = $box6 ? 'bilag' : $r['box4'];
        $undermappe = ($kilde == "kassekladde") ? "kladde_$kilde_id" : "ordrer";
    } else {
        $mappe = $box6 ? 'dokumenter' : $r['box5'];
        $undermappe = "debitor_$kilde_id";
    }
    
    if ($slet_alle) {
        // Delete all files from filesystem
        if ($box6) {
            // Local storage - delete all files matching pattern
            $dir = "../" . $nfs_mappe . "/" . $db . "/" . $mappe . "/" . $undermappe . "/";
            if (is_dir($dir)) {
                $files = scandir($dir);
                foreach ($files as $file) {
                    if ($kilde == "kassekladde" || $kilde == "ordrer") {
                        if (strpos($file, "bilag_" . $bilag_id) === 0) {
                            unlink($dir . $file);
                        }
                    } else {
                        if (strpos($file, "doc_" . $bilag_id) === 0) {
                            unlink($dir . $file);
                        }
                    }
                }
            }
            
            // Also delete from temp
            $temp_files = explode('|', $dokumenter);
            foreach ($temp_files as $tf) {
                $tf = trim($tf);
                if ($tf && file_exists("../temp/$db/$tf")) {
                    unlink("../temp/$db/$tf");
                }
            }
        } else {
           //Ftp etc.
        }
        
        // Clear all documents from database
        db_modify("update $kilde set dokument='' where id='$bilag_id'", __FILE__ . " linje " . __LINE__);
        
    } else {
        // Delete single file
        $filnavn_array = explode('|', $dokumenter);
        $nye_dokumenter = array();
        
        foreach ($filnavn_array as $fil) {
            $fil = trim($fil);
            if ($fil != '' && $fil != $filnavn) {
                $nye_dokumenter[] = $fil;
            }
        }
        
        // Update database with remaining files
        $nye_dok_string = implode('|', $nye_dokumenter);
        db_modify("update $kilde set dokument='" . db_escape_string($nye_dok_string) . "' where id='$bilag_id'", __FILE__ . " linje " . __LINE__);
        
        // Delete physical file
        if ($box6) {
            // Delete from temp folder
            $temp_fil = "../temp/$db/$filnavn";
            if (file_exists($temp_fil)) {
                unlink($temp_fil);
            }
            
            //$dir = "../" . $nfs_mappe . "/" . $db . "/" . $mappe . "/" . $undermappe . "/";
           
        }
    }
}
?>