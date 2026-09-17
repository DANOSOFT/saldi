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
	// Decode the URL-encoded path
	$deleteDoc = urldecode($deleteDoc);
	
	// Normalize the path - remove double slashes and resolve relative paths
	$deleteDoc = str_replace('//', '/', $deleteDoc);
	$deleteDoc = preg_replace('#/+#', '/', $deleteDoc); // Remove multiple slashes
	
	// Try to resolve the path
	$resolvedPath = realpath($deleteDoc);
	if ($resolvedPath === false && strpos($deleteDoc, '../') === 0) {
		// Try resolving from the includes directory
		$basePath = dirname(__FILE__) . '/../../';
		$resolvedPath = realpath($basePath . ltrim($deleteDoc, './'));
	}
	
	// Use resolved path if available, otherwise use normalized original
	$deleteDoc = ($resolvedPath !== false) ? $resolvedPath : $deleteDoc;
	
	error_log("deleteDoc.php: Normalized path: $deleteDoc");
	error_log("deleteDoc.php: File exists: " . (file_exists($deleteDoc) ? "yes" : "no"));
	
	$tmpA = explode("/",$deleteDoc);

	$x    = count($tmpA)-1;
	$h = count($tmpA)-3;

	$bilag_id = $tmpA[$h];
	$fileName = $tmpA[$x];

	// Security check: Block if locked and older than 24h
	$locked = 0;
	if ($source == 'creditor') {
		$qtxt = "select status from ordrer where id = '$sourceId'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
		// Handle potential schema ambiguity (art vs status)
		$statusVal = isset($r['art']) ? $r['art'] : (isset($r['status']) ? $r['status'] : 0);
		($statusVal >= '3')?$locked='1':$locked='0'; 
	} elseif ($source == 'kassekladde') {
		if ($kladde_id) {
			$qtxt = "select bogfort from kladdeliste where id = '$kladde_id'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			($r['bogfort'] == 'V')?$locked='1':$locked='0';
		}
	}
	
	$qtxt = "select timestamp from documents where source = '$source' and source_id = '$sourceId' and filename = '".db_escape_string($fileName)."'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$docTimestamp = $r ? $r['timestamp'] : 0;
	
	if ($locked == 1 && (date('U') - $docTimestamp > 86400)) {
		echo '<script type="text/javascript">';
		echo "alert('Handling afvist: Linjen er bogført/låst og bilaget er ældre end 24 timer.');";
		echo "window.history.back();";
		echo '</script>';
		exit;
	}
	
	// Get the filepath for this document to check if it's linked to other lines
	$qtxt_check = "SELECT filepath FROM documents WHERE source = '$source' AND source_id = '$sourceId' AND filename = '".db_escape_string($fileName)."' LIMIT 1";
	$doc_row = db_fetch_array(db_select($qtxt_check, __FILE__ . " linje " . __LINE__));
	$docFilepath = $doc_row ? $doc_row['filepath'] : '';
	
	// Check if this document file is linked to other lines (other source_id's)
	$qtxt_linked = "SELECT COUNT(*) as cnt FROM documents WHERE filepath = '".db_escape_string($docFilepath)."' AND filename = '".db_escape_string($fileName)."'";
	$linked_row = db_fetch_array(db_select($qtxt_linked, __FILE__ . " linje " . __LINE__));
	$isLinkedToOtherLines = ($linked_row && $linked_row['cnt'] > 1);
	
	// Delete from database first (remove this line's reference)
	$qtxt = "delete from documents where source = '$source' and source_id = '$sourceId' and filename = '".db_escape_string($fileName)."'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	
	// If the document is linked to other lines, do NOT delete the actual file
	if ($isLinkedToOtherLines) {
		// Only database record was removed, file is preserved for other lines
		$qtxt = "select id,filename,filepath from documents where source = '$source' and source_id = '$sourceId' order by id limit 1";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		
		if ($q && ($r = db_fetch_array($q))) {
			$nextDoc = "$docFolder/$db/$r[filepath]/$r[filename]";
			$redirectUrl = "documents.php?$params&showDoc=".urlencode($nextDoc);
		} else {
			// No more documents, redirect to docPool
			if ($source == 'kassekladde' && isset($kladde_id) && isset($fokus)) {
				$poolParams = "openPool=1"."&".
					"kladde_id=$kladde_id"."&".
					"bilag=$bilag"."&".
					"fokus=$fokus"."&".
					"poolFile=$poolFile"."&".
					"docFolder=$docFolder"."&".
					"sourceId=$sourceId"."&".
					"source=$source";
				$redirectUrl = "documents.php?$poolParams";
			} else {
				$redirectUrl = "documents.php?$params";
			}
		}
		
		echo '<script type="text/javascript">';
		echo "alert('Link til $fileName fjernet. Filen er bevaret da den er linket til andre linjer.');";
		echo "window.location.href = '$redirectUrl';";
		echo '</script>';
		exit;
	}
	
	// Perform the file unlink operation - only if NOT linked to other lines
	if (file_exists($deleteDoc)) {
		if (unlink($deleteDoc)) {
			// Success - check if there are any remaining documents for this sourceId
			$qtxt = "select id,filename,filepath from documents where source = '$source' and source_id = '$sourceId' order by id limit 1";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			
		if ($q && ($r = db_fetch_array($q))) {
			// There's another document, redirect to show it
			$nextDoc = "$docFolder/$db/$r[filepath]/$r[filename]";
			$redirectUrl = "documents.php?$params&showDoc=".urlencode($nextDoc);
		} else {
			// No more documents, redirect to docPool to choose a new bilag
			if ($source == 'kassekladde' && isset($kladde_id) && isset($fokus)) {
				$poolParams = "openPool=1"."&".
					"kladde_id=$kladde_id"."&".
					"bilag=$bilag"."&".
					"fokus=$fokus"."&".
					"poolFile=$poolFile"."&".
					"docFolder=$docFolder"."&".
					"sourceId=$sourceId"."&".
					"source=$source";
				$redirectUrl = "documents.php?$poolParams";
			} else {
				// Fallback: redirect to documents list without showDoc
				$redirectUrl = "documents.php?$params";
			}
		}
			
			echo '<script type="text/javascript">';
			echo "alert('$fileName successfully deleted!');";
			echo "window.location.href = '$redirectUrl';";
			echo '</script>';
			exit;
		} else {
			// Unlink failed - restore database record
			$qtxt = "insert into documents (source, source_id, filename, filepath) values ('$source', '$sourceId', '".db_escape_string($fileName)."', '".db_escape_string($tmpA[$h-1])."')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			
			echo '<script type="text/javascript">';
			echo 'alert("Failed to delete the file. Please check file permissions.");';
			echo 'window.location.href = "' . $_SERVER['HTTP_REFERER'] . '";';
			echo '</script>';
			exit;
		}
	} else {
		// File doesn't exist - database record already deleted, just redirect
		$qtxt = "select id,filename,filepath from documents where source = '$source' and source_id = '$sourceId' order by id limit 1";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		
		if ($q && ($r = db_fetch_array($q))) {
			$nextDoc = "$docFolder/$db/$r[filepath]/$r[filename]";
			$redirectUrl = "documents.php?$params&showDoc=".urlencode($nextDoc);
		} else {
			// No more documents, redirect to docPool to choose a new bilag
			if ($source == 'kassekladde' && isset($kladde_id) && isset($fokus)) {
				$poolParams = "openPool=1"."&".
					"kladde_id=$kladde_id"."&".
					"bilag=$bilag"."&".
					"fokus=$fokus"."&".
					"poolFile=$poolFile"."&".
					"docFolder=$docFolder"."&".
					"sourceId=$sourceId"."&".
					"source=$source";
				$redirectUrl = "documents.php?$poolParams";
			} else {
				$redirectUrl = "documents.php?$params";
			}
		}
		
		echo '<script type="text/javascript">';
		echo "alert('File not found, but database record removed.');";
		echo "window.location.href = '$redirectUrl';";
		echo '</script>';
		exit;
	}
}
?>
