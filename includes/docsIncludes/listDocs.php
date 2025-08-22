<?php
// --- includes/docsIncludes/listDocs.php-----patch 4.1.1 ----2025-08-22--------
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
// 20220510 PHR Not attatchments from not invoiced orders can now be deleted. 
// 20230705 LOE Made some modifications 20230724+20230801
// 20240117 LOE Minor modification
// 20240305 PHR Varioous corrections
// 20240323 PHR Minor design changes
// 20250815 LOE Further improvements

$fileName = NULL;
isset($_GET['bilag_id'])? $bilag_id = $_GET['bilag_id']: $bilag_id = null;
print "<tr><td valign='top' align = 'center'>";

/*
if ($dokument) {
	echo "$docFolder/$db/bilag/kladde_$kladde_id/bilag_$bilag_id<br>";
	if (file_exists("$docFolder/$db/bilag/kladde_$kladde_id/bilag_$bilag_id")) {
		if (!file_exists("$docFolder/$db/bilag/kladde_$kladde_id/_$bilag_id")) {
			echo "mkdir (\"$docFolder/finance/$kladde_id/$bilag_id\",0777)<br>";
		}
	} else echo "Ikke fundet";
	
}
*/



//Insert files from pulje if the exist
if ($source == 'kassekladde') {
		echo error_log("#Docfolder: $docFolder/$db, Source: $source, sourceId: $sourceId");

		$sourceId = (int)$sourceId;

		$directory = $docFolder . '/' . $db . '/pulje';

		// Read all PDF files in the directory
		$pdfFiles = glob($directory . '/*.pdf');
		// Fetch existing filenames from the database
		$qtxt = "SELECT filename FROM documents WHERE source = '$source' AND source_id = $sourceId";
		$existingRecords = db_select($qtxt, __FILE__ . " linje " . __LINE__);
         
		// Normalize existing filenames
		$existingFilenames = [];
                $rowCount=0;
			if ($existingRecords !== false) {
				
				
				while ($r = db_fetch_array($existingRecords)) {
					$rowCount++;
					#error_log("Fetched row: " . print_r($r, true));
					if (!empty($r['filename'])) {
						$normalized = trim(strtolower($r['filename']));
						$existingFilenames[$normalized] = true;
					}
				}
			
			}
			if (empty($existingFilenames)) {
				error_log("No existing filenames found in DB.");
			}
			
		if (!empty($existingRecords) && is_array($existingRecords)) {
			foreach ($existingRecords as $rec) {
				$raw = $rec['filename'];
				$normalized = strtolower(trim($raw));
				$existingFilenames[] = $normalized;
				
			}
		} else {
			error_log("No existing filenames found in DB.");
		}
		$existingFilenamesMap = array_flip(
			array_filter($existingFilenames, function($val) {
				return is_string($val) || is_int($val);
			})
		);

		// Loop through the files and insert missing ones
		foreach ($pdfFiles as $filePath) {
			$filename = basename($filePath);
			$normalized = trim(strtolower($filename));
		
		  if (!isset($existingFilenames[$normalized])) {
    		error_log("-> Inserting: $filename");

				// Sanitize inputs to prevent SQL injection
				$filenameEsc = addslashes($filename);
				$filePathEsc = 'pulje';
				$insertQ = "INSERT INTO documents (filename, filepath, source, source_id)
							VALUES ('$filenameEsc', '$filePathEsc', '$source', '$sourceId')";
				$success = db_modify($insertQ, __FILE__ . " linje " . __LINE__);

				if (!$success) {
					error_log("!! Insert failed for: $filename");
				}
			}
		}
	}

//end insert files from pulje


if (!isset($sourceId) || $sourceId === '') {
		error_log("no files to list in listDocs.php");
		exit;
}

	


$qtxt = "select id,filename,filepath from documents where source = '$source' and source_id = '$sourceId' order by id";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
if($q !== false){
	while ($r=db_fetch_array($q)) {
		$docId = $r['id'];
		$href="$docFolder/$db/$r[filepath]/$r[filename]";
		if (!$showDoc) {
			$fileName = $r['filename'];
			if ($fileName != trim($fileName)) {
				$newName = trim($fileName);
				rename($showDoc,$newName);
				$qtxt = "UPDATE documents set filename = '$newName' where id = '$docId'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$fileName = $$newName; 
			}
			#else echo "Kan ikke finde den<br>";
#			$check = $r['filepath']."/$fileName";
#			if(isset($fileName) && !file_exists($check)){ //File path of email docs are different
#				$showDoc= substr($check, 3);
#			}else{
			$showDoc  = "$docFolder/$db/$r[filepath]/$fileName"; // 20230705
			if (strtolower(substr($showDoc,-4,0)) !='.') {
				if (strtolower(substr($showDoc,-3)) == 'pdf') {
					$newName = str_replace('pdf ','.pdf',$showDoc);
					rename($showDoc,$newName);
					if (file_exists($newName)) $showDoc = $newName;
				} else {
					$fileType = strtolower(file_get_contents($showDoc, FALSE, NULL, 0, 4));
					if ($fileType == '%pdf') {
						$newName = $showDoc.'.pdf';
						rename($showDoc,$newName);
						if (file_exists($newName)) {
							$showDoc = $newName;
							$newName = $fileName.'.pdf';
							$qtxt = "UPDATE documents set filename = '$newName' where id = '$docId'";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						}
					}
				}
			}			
		} else {
			$tmpA = explode("/",$showDoc);
			$x = count($tmpA)-1;
			$fileName = $tmpA[$x];
		}
		$showName = strtolower($r['filename']);
		if (strlen($showName) > 36) $showName = substr($showName,0,33).'...';
		print "<tr><td valign='top' align = 'center'>";
		if($source == 'kassekladde'){ //20230705
			print "<a href = 'documents.php?$params&showDoc=".urlencode("$href")."'>";
		}else{
			print "<a href = 'documents.php?$params&showDoc=".urlencode("$href")."'>";
		}
		print "<button style = 'width:90%;height:35px;'>". $showName ."</button></a></td></tr>";
	}
}
print "<tr><td valign='top' align = 'center'><hr width = '90%'></td></tr>";
$locked = 0;
if ($source == 'creditor') {
	$qtxt = "select status from ordrer where id = '$sourceId'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
	($r['art'] >= '3')?$locked='1':$locked='0'; 
} elseif ($source == 'kassekladde') {
	if ($kladde_id) {
		$qtxt = "select bogfort from kladdeliste where id = '$kladde_id'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		($r['bogfort'] == 'V')?$locked='1':$locked='0';
	}
}
if ($sourceId || $sourceId == 0) {
	if (!isset($sourceId) || $sourceId === '') {
		alert("no files to show");
		exit;
	}
	$qtxt = "SELECT timestamp FROM documents WHERE source = '$source' AND source_id = '$sourceId'";
	$qtxt .= " AND filename = '" . db_escape_string($fileName) . "'";

	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		if ($locked == 0 || date('U') - $r['timestamp'] < 60*60*24) {
			print "<tr><td valign='top' align='center'>";
			print "<a href='documents.php?$params&deleteDoc=" . urlencode($showDoc) . "' onclick=\"return confirm('Slet $fileName?')\">";
			print "<button style='width:90%;height:35px;'>Slet dokument</button></a>";
			print "</td></tr>";

			print "<tr><td valign='top' align='center'>";
			print "<a href='documents.php?$params&moveDoc=" . urlencode($showDoc) . "' onclick=\"return confirm('Flyt $fileName til pulje?')\">";
			print "<button style='width:90%;height:35px;'>Flyt dokument til pulje</button></a>";
			print "</td></tr>";

			// === Rename form ===
			print "<tr><td valign='top' align='center'>";
			print "<form method='POST' action='documents.php?$params&renameDoc=" . urlencode($showDoc) . "' onsubmit=\"return confirm('Omdøb dokumentet?')\">";
			print "<input type='text' name='newFileName' value='" . htmlspecialchars($fileName, ENT_QUOTES) . "' style='width:90%;height:30px;margin-top:10px;' />";
			print "<input type='hidden' name='oldFileName' value='" . htmlspecialchars($fileName, ENT_QUOTES) . "' />";
			print "<input type='submit' value='Omdøb fil' style='width:90%;height:30px;margin-top:5px;' />";
			print "</form>";
			print "</td></tr>";
		}
	}

}
?>
