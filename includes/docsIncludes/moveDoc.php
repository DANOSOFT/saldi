<?php
// --- includes/docsIncludes/moveDoc.php -----patch 4.1.0 ----2024-03-05---
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
// Copyright (c) 2024-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20230707 LOE Added kassekladde part
// 20230724 LOE made some modifications to include alert also
// 20240305 PHR Varioous corrections


if ($moveDoc) {
	// Decode the URL-encoded path
	$moveDoc = urldecode($moveDoc);
	
	// Normalize the path - remove double slashes
	$moveDoc = str_replace('//', '/', $moveDoc);
	$moveDoc = preg_replace('#/+#', '/', $moveDoc); // Remove multiple slashes
	$tmpA = explode("/",$moveDoc);

	$x = count($tmpA)-1;
	$h = count($tmpA)-3;

	$bilag_id = $tmpA[$h];
	$fileName = $tmpA[$x];
	$new = '';

	for ($i=0;$i<count($tmpA)-4;$i++) {
		if ($tmpA[$i]) $new.= $tmpA[$i]."/";
	}
	$new.= "pulje";
	if (!file_exists($new)) mkdir($new, 0777);
	$new.= "/$tmpA[$x]";
	$new = str_replace(' ','',$new);
	
	// Move the file
	if (file_exists($moveDoc)) {
		rename("$moveDoc", "$new");
		
		// Move corresponding .info file if it exists
		$infoDoc = preg_replace('/\.pdf$/i', '.info', $moveDoc);
		// Check if replacement happened and file exists
		if ($infoDoc !== $moveDoc && file_exists($infoDoc)) {
			$infoNew = preg_replace('/\.pdf$/i', '.info', $new);
			rename("$infoDoc", "$infoNew");
		}
	}
	
	// Delete from database
	$qtxt = "delete from documents where source = '$source' and source_id = '$sourceId' ";
	$qtxt.= "and filename = '".db_escape_string($fileName)."'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	
	// Check if there are any remaining documents for this sourceId
	$qtxt = "select id,filename,filepath from documents where source = '$source' and source_id = '$sourceId' order by id limit 1";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	
	if ($q && ($r = db_fetch_array($q))) {
		// There's another document, redirect to show it
		$nextDoc = "$docFolder/$db/$r[filepath]/$r[filename]";
		$redirectUrl = "documents.php?$params&showDoc=".urlencode($nextDoc);
	} else {
		// No more documents, redirect to docPool to choose a new bilag
		if ($source == 'kassekladde' && isset($kladde_id) && isset($fokus)) {
			// Ensure we redirect to docPool view (openPool=1) with all necessary parameters
			$poolParams = "openPool=1".
				"&kladde_id=".urlencode($kladde_id).
				"&bilag=".urlencode($bilag).
				"&fokus=".urlencode($fokus).
				"&poolFile=".urlencode($poolFile).
				"&docFolder=".urlencode($docFolder).
				"&sourceId=".urlencode($sourceId).
				"&source=".urlencode($source);
			$redirectUrl = "documents.php?$poolParams";
		} else {
			// Fallback: redirect to documents list without showDoc
			$redirectUrl = "documents.php?$params";
		}
	}
	
	echo '<script type="text/javascript">';
	echo "alert('$fileName successfully moved to pool!');";
	echo "window.location.href = '$redirectUrl';";
	echo '</script>';
	exit;
}
?>
