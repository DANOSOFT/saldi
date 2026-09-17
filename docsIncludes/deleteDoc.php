<?php
// --- includes/docsIncludes/deleteDoc.php -----patch 4.0.8 ----2023-07-24---
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20230707 LOE Added kassekladde part
// 20230724 LOE made some modifications to include alert also


if ($deleteDoc) {
	$tmpA = explode("/",$deleteDoc);

	$x    = count($tmpA)-1;
	$h = count($tmpA)-3;

	$bilag_id = $tmpA[$h];
	$fileName = $tmpA[$x];

	
	$qtxt = "delete from documents where source = '$source' and source_id = '$sourceId' and filename = '".db_escape_string($fileName)."'";
	
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	
    // Perform the file unlink operation.
    if (unlink($deleteDoc)) {
        // Unlink operation was successful.
        // Generate JavaScript code for displaying an alert and redirecting the user.
        echo '<script type="text/javascript">';
        echo "alert('$fileName successfully deleted!');";
#        echo 'window.location.href = "' . $_SERVER['HTTP_REFERER'] . '";';
        echo '</script>';
        $deleteDoc = $fileName = $showDoc = NULL;
				print "<meta http-equiv=\"refresh\" content=\"0;URL=documents.php?$params&showDoc=\">";
        exit; // Exit to prevent further PHP code execution.
    } else {
        // Unlink operation failed.
        // Optionally, you can also add an error alert or take other actions if needed.
        echo '<script type="text/javascript">';
        echo 'alert("Failed to delete the file.");';
        echo 'window.location.href = "' . $_SERVER['HTTP_REFERER'] . '";';
        echo '</script>';
        exit; // Exit to prevent further PHP code execution.
    }

}
?>
