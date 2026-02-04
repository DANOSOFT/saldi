<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/docPool.php --- ver 4.1.1 --- 2025-10-09 --- 
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
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20250510 PHR Added 'w' to $legalChars
// 20250519 PHR '&' replaced by '_' in filenames 
// 20250823 LOE Applied if_isset properly to prevent excessive error logging plus other improvements
// 20250824 LOE _docPoolData.php added to this file for improved data handling, also checks that file is .pdf before setting default. Update .info subject 
// 20250827 LOE fixed error of rm: cannot remove '*': No such file or directory  cp: cannot stat '../..error. Also User can now add subject and amount to shown poolfiles
// 20251007 LOE Refactored the fixed bottom table, added background color and various enhancement.
// 20260202 Added syncPuljeFilesToDatabase to sync files once on page load.

/**
 * Sync files from pulje directory to pool_files database table.
 * This runs once on page load and adds any missing PDF files to the database.
 */
function syncPuljeFilesToDatabase($docFolder, $db) {
	$puljePath = "$docFolder/$db/pulje";
	
/* 	$skip = get_settings_value("skip_sync", "docs", 0);

	
	if ($skip) {
		return;
	} */
	// Always clean up .info files if directory exists (do this before any early returns)
	if (is_dir($puljePath)) {
		$infoFiles = glob("$puljePath/*.info");
		if ($infoFiles) {
			foreach ($infoFiles as $infoFile) {
				if (is_file($infoFile)) {
					unlink($infoFile);
					error_log("syncPuljeFilesToDatabase: Deleted info file: $infoFile");
				}
			}
		}
	}
	
	// Check if directory exists
	if (!is_dir($puljePath)) {
		return;
	}
	
	// Ensure pool_files table exists
	$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'pool_files'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " line " . __LINE__))) {
		// Create table (use IF NOT EXISTS to prevent race condition errors)
		$qtxt = "CREATE TABLE IF NOT EXISTS pool_files (
			id serial NOT NULL,
			filename varchar(255) NOT NULL,
			subject text,
			account varchar(50),
			amount varchar(50),
			file_date varchar(50),
			invoice_number varchar(100),
			description text,
			updated timestamp DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE(filename)
		)";
		db_modify($qtxt, __FILE__ . " line " . __LINE__);
	} else {
		// Table exists, check for missing columns and add them
		$qtxt = "SELECT column_name FROM information_schema.columns 
				 WHERE table_schema = 'public' AND table_name = 'pool_files' AND column_name = 'invoice_number'";
		if (!db_fetch_array(db_select($qtxt, __FILE__ . " line " . __LINE__))) {
			@db_modify("ALTER TABLE pool_files ADD COLUMN invoice_number varchar(100)", __FILE__ . " line " . __LINE__);
		}
		
		$qtxt = "SELECT column_name FROM information_schema.columns 
				 WHERE table_schema = 'public' AND table_name = 'pool_files' AND column_name = 'description'";
		if (!db_fetch_array(db_select($qtxt, __FILE__ . " line " . __LINE__))) {
			@db_modify("ALTER TABLE pool_files ADD COLUMN description text", __FILE__ . " line " . __LINE__);
		}
	}
	
	// Get all PDF files from the pulje directory
	$pdfFiles = [];
	$files = scandir($puljePath);
	foreach ($files as $file) {
		if ($file === '.' || $file === '..') continue;
		if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf') {
			$pdfFiles[] = $file;
		}
	}
	
	if (empty($pdfFiles)) {
		return;
	}
	
	// Get existing filenames from database in one query
	$escapedFiles = array_map(function($f) { return "'" . db_escape_string($f) . "'"; }, $pdfFiles);
	$inClause = implode(',', $escapedFiles);
	$qtxt = "SELECT filename FROM pool_files WHERE filename IN ($inClause)";
	$result = db_select($qtxt, __FILE__ . " line " . __LINE__);
	
	$existingFiles = [];
	while ($row = db_fetch_array($result)) {
		$existingFiles[$row['filename']] = true;
	}
	
	// Insert missing files
	foreach ($pdfFiles as $file) {
		if (!isset($existingFiles[$file])) {
			$fullPath = "$puljePath/$file";
			$baseName = pathinfo($file, PATHINFO_FILENAME);
			$fileDate = date("Y-m-d H:i:s", filemtime($fullPath));
			
			// Check for .info file for additional data
			$infoFile = "$puljePath/$baseName.info";
			$subject = $baseName;
			$account = '';
			$amount = '';
			$invoiceNumber = '';
			$description = '';
			
			if (file_exists($infoFile)) {
				$lines = file($infoFile, FILE_IGNORE_NEW_LINES);
				$subject = (isset($lines[0]) && trim($lines[0]) !== '') ? trim($lines[0]) : $baseName;
				$account = isset($lines[1]) ? trim($lines[1]) : '';
				$amount = isset($lines[2]) ? trim($lines[2]) : '';
				if (isset($lines[3]) && trim($lines[3]) !== '') {
					$fileDate = trim($lines[3]);
				}
				$invoiceNumber = isset($lines[4]) ? trim($lines[4]) : '';
				$description = isset($lines[5]) ? trim($lines[5]) : '';
			}
			
			// Insert into database
			$qtxt = "INSERT INTO pool_files (filename, subject, account, amount, file_date, invoice_number, description) VALUES (
				'" . db_escape_string($file) . "',
				'" . db_escape_string($subject) . "',
				'" . db_escape_string($account) . "',
				'" . db_escape_string($amount) . "',
				'" . db_escape_string($fileDate) . "',
				'" . db_escape_string($invoiceNumber) . "',
				'" . db_escape_string($description) . "'
			)";
			db_modify($qtxt, __FILE__ . " line " . __LINE__);
		}
	}
	/* update_settings_value("skip_sync", "docs", 1, "Skip pool sync after initial run"); */
}

function docPool($sourceId,$source,$kladde_id,$bilag,$fokus,$poolFile,$docFolder,$docFocus){

	global $bruger_id,$db,$exec_path,$buttonStyle, $topStyle;
	global $params,$regnaar,$sprog_id,$userId,$bgcolor, $bgcolor5, $buttonColor, $buttonTxtColor;
	
	$afd =  $beskrivelse = $debet = $dato = $fakturanr = $kredit = $projekt = $readOnly = $sag = $sum = NULL;

	// Sync missing files from pulje directory to database once on page load
	syncPuljeFilesToDatabase($docFolder, $db);

	((isset($_POST['unlink']) && $_POST['unlink']) || (isset($_GET['unlink']) && $_GET['unlink']))?$unlink=1:$unlink=0;
	$cleanupOrphans = if_isset($_GET, NULL, 'cleanupOrphans');
	$cleanup = get_settings_value("cleanup", "docs", 0);

	if ($cleanupOrphans && !$cleanup) {
		$puljePath = "$docFolder/$db/pulje";
		if (is_dir($puljePath)) {
			$files = scandir($puljePath);
			$count = 0;
			foreach ($files as $file) {
				if ($file == '.' || $file == '..') continue;
				
				$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				
				// Look for .info files
				if ($ext === 'info') {
					$baseName = pathinfo($file, PATHINFO_FILENAME);
					$pdfFile = "$puljePath/$baseName.pdf";
					
					// If corresponding PDF does not exist
					if (!file_exists($pdfFile)) {
						$infoFile = "$puljePath/$file";
						if (unlink($infoFile)) {
							$count++;
							error_log("Cleanup: Removed orphaned info file: $file");
							
							// Cleanup database
							$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'pool_files'";
							if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
								$dbFilename = $baseName . '.pdf';
								$qtxt = "DELETE FROM pool_files WHERE filename = '". db_escape_string($dbFilename) ."'";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							}
						}
					}
				}
			}
			if ($count > 0) {
				echo "<script>alert('Oprydning færdig. Fjernede $count forældreløse info-filer.');</script>";
			} else {
				echo "<script>alert('Ingen forældreløse info-filer fundet.');</script>";
			}
		}
		update_settings_value("cleanup", "docs", 1);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/documents.php?$params&openPool=1\">";
		exit;
	}

	(isset($_POST['rename']) && $_POST['rename'])?$rename=1:$rename=0;
	(isset($_POST['unlinkFile']) && $_POST['unlinkFile'])?$unlinkFile=$_POST['unlinkFile']:((isset($_GET['unlinkFile']) && $_GET['unlinkFile'])?$unlinkFile=$_GET['unlinkFile']:$unlinkFile=NULL);
	
	$insertFile   = if_isset($_POST,NULL,'insertFile');
	$newFileName  = if_isset($_POST,NULL,'newFileName');
	$descFile     = if_isset($_POST,NULL,'descFile');
	$newSubject   = if_isset($_POST,NULL,'newSubject');
	$newAccount	= if_isset($_POST,NULL,'newAccount');
	$newAmount	= if_isset($_POST,NULL,'newAmount');
	$newDate	   = if_isset($_POST,NULL,'newDate');
	$newInvoiceNumber = if_isset($_POST,NULL,'newInvoiceNumber');
	$newInvoiceDescription = if_isset($_POST,NULL,'newInvoiceDescription');
	
	// Override $poolFile from POST if it's set (for AJAX edit operations)
	if (isset($_POST['poolFile']) && $_POST['poolFile']) {
		$poolFile = $_POST['poolFile'];
	}

	$afd         = if_isset($_POST,NULL,'afd');
	$bilag       = if_isset($_POST,NULL,'bilag');
	$beskrivelse = if_isset($_POST,NULL,'beskrivelse');
	$dato        = if_isset($_POST,NULL,'dato');
	$debet       = if_isset($_POST,NULL,'debet');
	$fakturanr   = if_isset($_POST,NULL,'fakturanr');
	$kredit      = if_isset($_POST,NULL,'kredit');
	$projekt     = if_isset($_POST,NULL,'projekt');
	$sag         = if_isset($_POST,NULL,'sag');
	$sum         = if_isset($_POST,NULL,'sum');

	if ($insertFile) {
		// Debug: Log all POST values we receive
		error_log("docPool INSERT - sourceId: " . ($sourceId ?? 'NOT SET'));
		error_log("docPool INSERT - newDate: " . ($newDate ?? 'NOT SET'));
		error_log("docPool INSERT - newAmount: " . ($newAmount ?? 'NOT SET'));
		
		// Only set date from pool file if sourceId is empty (new entry) and newDate is valid
		if (!$sourceId && $newDate && strtotime($newDate) !== false && strtotime($newDate) > 0) {
			$formattedDate = date("d-m-Y", strtotime($newDate));
			$dato = $formattedDate;
			$_POST['dato'] = $dato;
			error_log("docPool INSERT - Setting date from pool file: newDate=$newDate, formatted=$formattedDate");
		} else {
			error_log("docPool INSERT - NOT setting date. sourceId=$sourceId, newDate=$newDate, strtotime result=" . (strtotime($newDate ?? '') ?: 'false'));
		}
		
		// Only set amount from pool file if sourceId is empty (new entry) and newAmount is set
		if (!$sourceId && $newAmount) {
			$sum = $newAmount;
			$_POST['sum'] = $sum;
			error_log("docPool INSERT - Setting amount from pool file: newAmount=$newAmount");
		} else {
			error_log("docPool INSERT - NOT setting amount. sourceId=$sourceId, newAmount=$newAmount");
		}
		
		// Set invoice number from pool file if sourceId is empty (new entry) and newInvoiceNumber is set
		if (!$sourceId && $newInvoiceNumber) {
			$_POST['fakturanr'] = $newInvoiceNumber;
			error_log("docPool INSERT - Setting fakturanr from pool file: newInvoiceNumber=$newInvoiceNumber");
		}
		
		// Set description from pool file if sourceId is empty (new entry) and newInvoiceDescription is set
		if (!$sourceId && $newInvoiceDescription) {
			$_POST['beskrivelse'] = $newInvoiceDescription;
			error_log("docPool INSERT - Setting beskrivelse from pool file: newInvoiceDescription=$newInvoiceDescription");
		}
		
		// Debug logging - log what we receive
		error_log("docPool INSERT - POST poolFiles: " . (isset($_POST['poolFiles']) ? $_POST['poolFiles'] : 'NOT SET'));
		error_log("docPool INSERT - POST poolFile: " . (isset($_POST['poolFile']) ? (is_array($_POST['poolFile']) ? implode(',', $_POST['poolFile']) : $_POST['poolFile']) : 'NOT SET'));
		error_log("docPool INSERT - Function param poolFile: " . ($poolFile ?? 'NOT SET'));
		
		// Handle multiple poolFiles - prioritize POST data for insert operations
		// IMPORTANT: Do NOT use $poolFile function parameter here - it contains the 
		// file being VIEWED, not the file the user wants to INSERT
		$poolFiles = array();
		
		// First priority: poolFiles as comma-separated string (most reliable from JavaScript)
		if (isset($_POST['poolFiles']) && !empty($_POST['poolFiles'])) {
			$poolFiles = explode(',', $_POST['poolFiles']);
			$poolFiles = array_map('trim', $poolFiles);
			error_log("docPool INSERT - Using POST poolFiles (comma-separated): " . implode(',', $poolFiles));
		// Second priority: poolFile[] as array from POST
		} elseif (isset($_POST['poolFile']) && is_array($_POST['poolFile'])) {
			$poolFiles = $_POST['poolFile'];
			error_log("docPool INSERT - Using POST poolFile[] array: " . implode(',', $poolFiles));
		// Third priority: Single poolFile from POST (string)
		} elseif (isset($_POST['poolFile']) && !empty($_POST['poolFile']) && is_string($_POST['poolFile'])) {
			$poolFiles = array($_POST['poolFile']);
			error_log("docPool INSERT - Using POST poolFile string: " . $_POST['poolFile']);
		// Fourth priority: GET array (URL params from JavaScript)
		} elseif (isset($_GET['poolFile']) && is_array($_GET['poolFile'])) {
			$poolFiles = $_GET['poolFile'];
			error_log("docPool INSERT - Using GET poolFile[] array: " . implode(',', $poolFiles));
		// Fifth priority: GET comma-separated
		} elseif (isset($_GET['poolFiles']) && !empty($_GET['poolFiles'])) {
			$poolFiles = explode(',', $_GET['poolFiles']);
			$poolFiles = array_map('trim', $poolFiles);
			error_log("docPool INSERT - Using GET poolFiles: " . implode(',', $poolFiles));
		// Sixth priority: Single GET poolFile
		} elseif (isset($_GET['poolFile']) && !empty($_GET['poolFile']) && is_string($_GET['poolFile'])) {
			$poolFiles = array($_GET['poolFile']);
			error_log("docPool INSERT - Using GET poolFile string: " . $_GET['poolFile']);
		} else {
			error_log("docPool INSERT - NO poolFiles found from any source!");
		}
		// NOTE: We intentionally do NOT fall back to $poolFile (function parameter)
		// because that contains the currently viewed file, not the file to insert
		
		error_log("docPool INSERT - Final poolFiles to process: " . implode(',', $poolFiles));
		
		// Remove empty values
		$poolFiles = array_filter($poolFiles);
		
		if (!empty($poolFiles)) {
			// If date/amount wasn't passed from JavaScript, try to read from .info file of first selected file
			// Check database first for file information
			$filename = reset($poolFiles);
			$qtxt = "SELECT * FROM pool_files WHERE filename = '" . db_escape_string($filename) . "'";
			$poolData = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

			if ($poolData) {
				if (!$sourceId && empty($newDate) && $poolData['file_date']) {
					// format date from Y-m-d H:i:s to d-m-Y
					$ts = strtotime($poolData['file_date']);
					if ($ts !== false && $ts > 0) {
						$_POST['dato'] = date("d-m-Y", $ts);
						error_log("docPool INSERT - Got date from DB: " . $poolData['file_date']);
					}
				}
				if (!$sourceId && empty($newAmount) && $poolData['amount']) {
					$_POST['sum'] = $poolData['amount'];
					error_log("docPool INSERT - Got amount from DB: " . $poolData['amount']);
				}
				if (!$sourceId && empty($newInvoiceNumber) && $poolData['invoice_number']) {
					$_POST['fakturanr'] = $poolData['invoice_number'];
					error_log("docPool INSERT - Got invoice_number from DB: " . $poolData['invoice_number']);
				}
				if (!$sourceId && empty($newInvoiceDescription) && $poolData['description']) {
					$_POST['beskrivelse'] = $poolData['description'];
					error_log("docPool INSERT - Got description from DB: " . $poolData['description']);
				}
			} elseif (!$sourceId && empty($newDate) && !empty($poolFiles)) {
				// Fallback to .info file if not in DB
				$firstPoolFile = reset($poolFiles);
				$baseName = pathinfo($firstPoolFile, PATHINFO_FILENAME);
				$infoFile = "$docFolder/$db/pulje/$baseName.info";
				error_log("docPool INSERT - Trying to read .info file: $infoFile");
				
				if (file_exists($infoFile)) {
					$infoLines = file($infoFile, FILE_IGNORE_NEW_LINES);
					// Line 0: subject, Line 1: account, Line 2: amount, Line 3: date, Line 4: invoiceNumber, Line 5: invoiceDescription
					if (isset($infoLines[3]) && !empty(trim($infoLines[3]))) {
						$infoDate = trim($infoLines[3]);
						// Try to parse the date
						$timestamp = strtotime($infoDate);
						if ($timestamp !== false && $timestamp > 0) {
							$formattedDate = date("d-m-Y", $timestamp);
							$_POST['dato'] = $formattedDate;
							error_log("docPool INSERT - Got date from .info file: $infoDate -> $formattedDate");
						}
					}
					if (isset($infoLines[2]) && !empty(trim($infoLines[2])) && empty($newAmount)) {
						$_POST['sum'] = trim($infoLines[2]);
						error_log("docPool INSERT - Got amount from .info file: " . $_POST['sum']);
					}
					// Get invoice_number from line 4
					if (isset($infoLines[4]) && !empty(trim($infoLines[4]))) {
						$_POST['fakturanr'] = trim($infoLines[4]);
						error_log("docPool INSERT - Got invoice_number from .info file: " . $_POST['fakturanr']);
					}
					// Get invoice_description from line 5
					if (isset($infoLines[5]) && !empty(trim($infoLines[5]))) {
						$_POST['beskrivelse'] = trim($infoLines[5]);
						error_log("docPool INSERT - Got invoice_description from .info file: " . $_POST['beskrivelse']);
					}
				} else {
					error_log("docPool INSERT - .info file not found: $infoFile");
				}
			}
			
			// Process multiple files
			$isMultiple = count($poolFiles) > 1;
			$processedCount = 0;
			$failedFiles = array();
			
			// Store original sourceId - for multiple files, all should attach to the same source
			$originalSourceId = $sourceId;
			
			foreach ($poolFiles as $currentPoolFile) {
				$poolFile = $currentPoolFile; // Set for insertDoc.php scope
				$fileName = $poolFile; // Set fileName for insertDoc.php
				
				// For multiple files, keep the same sourceId so all files attach to the same entry
				// Only reset temporary variables, but preserve sourceId
				if ($isMultiple) {
					// Reset sourceId to original for each file (so all attach to same entry)
					$sourceId = $originalSourceId;
					// Reset showDoc and other temporary variables
					$showDoc = null;
					unset($showDoc);
					// Keep bilag if it was set, otherwise let insertDoc.php calculate it
					// (only for the first file if sourceId is empty)
				}
				
				// Set flag to prevent redirect in insertDoc.php when processing multiple files
				$processingMultiple = $isMultiple;
				
				// Store the fileName before including insertDoc.php (it might modify it)
				$expectedFileName = $fileName;
				
				// Preserve original docFolder - insertDoc.php modifies it by appending /$db
				$originalDocFolder = $docFolder;
				
				// Include insertDoc.php for each file
				// Capture any output but don't stop on redirects for multiple files
				if ($isMultiple) {
					ob_start();
				}
				
				include ("docsIncludes/insertDoc.php");
				
				// Restore original docFolder for next iteration
				if ($isMultiple) {
					$docFolder = $originalDocFolder;
				}
				
				if ($isMultiple) {
					$output = ob_get_clean();
					
					// If sourceId was empty initially and insertDoc.php created one, update originalSourceId
					// so subsequent files use the same sourceId
					if (!$originalSourceId && isset($sourceId) && $sourceId) {
						$originalSourceId = $sourceId;
					}
					
					// Store the sourceId and fileName that were set by insertDoc.php for this file
					$fileSourceId = isset($sourceId) ? $sourceId : null;
					$fileFileName = isset($fileName) ? $fileName : $expectedFileName;
					
					// Check if file was processed successfully
					// Verify database entry was created (this is the source of truth)
					$fileProcessed = false;
					
					// Check if sourceId exists (either original or newly created)
					if ($fileSourceId) {
						// For kassekladde, verify the entry exists
						if ($source == 'kassekladde') {
							$qtxt = "select id from kassekladde where id = '$fileSourceId'";
							$kladdeEntry = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
							if (!$kladdeEntry) {
								error_log("Multiple insert: kassekladde entry not found for sourceId=$fileSourceId, poolFile=$currentPoolFile");
								$fileProcessed = false;
							}
						}
						
						// Check if database entry exists for this file
						if ($fileProcessed !== false || $source != 'kassekladde') {
							$qtxt = "select id from documents where source = '$source' and source_id = '$fileSourceId' and filename = '". db_escape_string($fileFileName) ."'";
							$docEntry = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
							
							if ($docEntry) {
								$fileProcessed = true;
								$processedCount++;
							} else {
								// Try with the expected filename if different
								if ($fileFileName != $expectedFileName) {
									$qtxt = "select id from documents where source = '$source' and source_id = '$fileSourceId' and filename = '". db_escape_string($expectedFileName) ."'";
									if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
										$fileProcessed = true;
										$processedCount++;
									}
								}
								if (!$fileProcessed) {
									error_log("Multiple insert: No document entry found for source=$source, sourceId=$fileSourceId, filename=$fileFileName (expected: $expectedFileName), poolFile=$currentPoolFile");
								}
							}
						}
					} else {
						error_log("Multiple insert: sourceId not set after processing file: $currentPoolFile, source=$source");
					}
					
					if (!$fileProcessed) {
						$failedFiles[] = $currentPoolFile;
					}
				} else {
					// Single file - restore original sourceId and let insertDoc.php handle redirect
					$sourceId = $originalSourceId;
					exit;
				}
			}
			
			// If processing multiple files, redirect after all are processed
			if ($isMultiple && $processedCount > 0) {
				// Use the sourceId that was actually used (might have been created)
				$finalSourceId = isset($sourceId) && $sourceId ? $sourceId : $originalSourceId;
				
				// Determine redirect URL
				if($source == 'kassekladde'){
					// Always redirect back to kassekladde after insert
					$redirectUrl = "../finans/kassekladde.php?kladde_id=$kladde_id&fokus=$fokus";
				} else {
					$redirectUrl = "documents.php?source=$source&sourceId=$finalSourceId";
				}
				
				// Clear any existing output buffers
				while (ob_get_level()) {
					ob_end_clean();
				}
				
				// Output complete HTML page with immediate JavaScript redirect
				// (Don't use header() as headers may already be sent)
				echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title></head><body>';
				echo '<script type="text/javascript">';
				
				// Show alert for failed files if any
				if (!empty($failedFiles)) {
					$failedList = implode(', ', array_map(function($f) {
						return addslashes(htmlspecialchars($f, ENT_QUOTES));
					}, $failedFiles));
					echo "alert('Nogle filer kunne ikke indsættes: " . $failedList . "');";
				}
				
				// Perform redirect immediately
				echo "window.location.replace('" . addslashes($redirectUrl) . "');";
				echo '</script>';
				echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl) . '"></noscript>';
				echo '</body></html>';
				exit;
			}
		} else {
			alert("Ingen filer valgt");
		}
		exit;
	}
	if ($sourceId) {
		$qtxt = "select * from kassekladde where id = '$sourceId'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			if (!$afd)         $afd         = $r['afd']; 
			if (!$bilag)       $bilag       = $r['bilag']; 
			if (!$beskrivelse) $beskrivelse = $r['beskrivelse']; 
			if (!$dato)        $dato        = dkdato($r['transdate']);
			if (!$debet)       $debet       = $r['debet']; 
			if (!$fakturanr)   $fakturanr   = $r['faktura']; 
			if (!$kredit)      $kredit      = $r['kredit']; 
			if (!$projekt)     $projekt     = if_isset($r,NULL,'projekt'); 
			if (!$sag)         $sag         = if_isset($r,NULL,'sag'); 
			if (!$sum)         $sum         = dkdecimal(if_isset($r,0,'amount')); 
		}
	}

	// Debug logging for edit operations
	if ($rename) {
		$logFile = "../temp/docpool_edit_debug.log";
		$logMsg = date('Y-m-d H:i:s') . " - docPool EDIT - rename=$rename, poolFile=$poolFile, newFileName=$newFileName\n";
		$logMsg .= date('Y-m-d H:i:s') . " - docPool EDIT - newSubject=$newSubject, newAccount=$newAccount, newAmount=$newAmount\n";
		$logMsg .= date('Y-m-d H:i:s') . " - docPool EDIT - newDate=$newDate, newInvoiceNumber=$newInvoiceNumber, newInvoiceDescription=$newInvoiceDescription\n";
		file_put_contents($logFile, $logMsg, FILE_APPEND);
	}

	$conditionPart1 = ($rename && $newFileName && $newFileName != $poolFile);
	$conditionPart2 = ($rename && ($newAccount||$newAmount||$newSubject||$newDate||$newInvoiceNumber||$newInvoiceDescription));
	$logFile = "../temp/docpool_edit_debug.log";
	file_put_contents($logFile, date('Y-m-d H:i:s') . " - Condition check: part1=$conditionPart1, part2=$conditionPart2\n", FILE_APPEND);
	
	if ($rename && $newFileName && $newFileName != $poolFile || ($rename && ($newAccount||$newAmount||$newSubject||$newDate||$newInvoiceNumber||$newInvoiceDescription))) {
		file_put_contents($logFile, date('Y-m-d H:i:s') . " - ENTERED rename block\n", FILE_APPEND);
	$legalChars = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
		array_push($legalChars,'0','1','2','3','4','5','6','7','8','9','_','-','.','(',')');
		$nfn = trim($newFileName);
		$nfn = str_replace('æ','ae',$nfn);
		$nfn = str_replace('Æ','AE',$nfn);
		$nfn = str_replace('ø','oe',$nfn);
		$nfn = str_replace('Ø','OE',$nfn);
		$nfn = str_replace('å','aa',$nfn);
		$nfn = str_replace('Å','AA',$nfn);
		$newFileName = '';
		for ($x=0;$x<strlen($nfn);$x++) {

			$c1=substr($nfn,$x,1);
			$c2=strtolower($c1);
			if (!in_array($c2,$legalChars)) $c1 = '_';
			$newFileName.= $c1;
		}
		
		// $tmpA = explode('.',$poolFile);
		// if (count($tmpA) > 1) $ext = end($tmpA);
		// else $ext = NULL;
		// $newFileName= trim($newFileName,' ._');
		// $tmpA = explode('.',$newFileName);
		// if (count($tmpA) > 1) $newExt = end($tmpA);
		// else $newExt = NULL;
		// if (strtolower($ext) != strtolower($newExt)) $newFileName.= ".$ext";
		// $newFileName= trim($newFileName,' ._');
		// rename($docFolder."/$db/pulje/$poolFile",$docFolder."/$db/pulje/$newFileName");
		// $poolFile = $newFileName;
		##########also rename other file with the same base names
		//  Get extension of the original pool file
			// Ensure the new filename is clean and has the correct extension
			#below rename the filename for both the pdf and the subject of .info file
			$ext = pathinfo($poolFile, PATHINFO_EXTENSION);
				$origBase = pathinfo($poolFile, PATHINFO_FILENAME);

				// Clean the new file name
				$newFileName = trim($newFileName, ' ._');
				
				$newExt = pathinfo($newFileName, PATHINFO_EXTENSION);

				// If extension missing or mismatched, add original
				if (strtolower($ext) !== strtolower($newExt)) {
					$newFileName .= ".$ext";
					$newExt = $ext;
				}
				
				$newBase = pathinfo($newFileName, PATHINFO_FILENAME);

				// Define the pulje directory path
				$puljePath = "$docFolder/$db/pulje";

				if (!is_dir($puljePath)) {
					error_log("Directory does not exist: $puljePath");
				} else {
					$allFiles = scandir($puljePath);
					$renamedPdf = false;
					
					// First pass: perform file renames
					foreach ($allFiles as $file) {
						if (in_array($file, ['.', '..'])) continue;
						$fileBase = pathinfo($file, PATHINFO_FILENAME);
						$fileExt  = pathinfo($file, PATHINFO_EXTENSION);
						
						// Rename all files with the same base name (e.g., PDF and .info)
						if ($fileBase === $origBase) {
							$oldPath = "$puljePath/$file";
							$newPath = "$puljePath/$newBase.$fileExt";

							// Skip if file doesn't exist
							if (!file_exists($oldPath)) continue;

							// Check if destination exists
							if (file_exists($newPath) && $oldPath !== $newPath) {
								error_log("Target file exists, skipping: $newPath");
								continue;
							}

							if (rename($oldPath, $newPath)) {
								error_log("Renamed: $oldPath -> $newPath");
								if (strtolower($fileExt) === 'pdf') {
									$renamedPdf = true;
									// Update poolFile variable to new name
									if ($poolFile === $file) {
										$poolFile = "$newBase.$fileExt";
									}
								}
							} else {
								error_log("Failed to rename: $oldPath -> $newPath");
							}
						}
					}
					
					// Update Database - Do this cleanly after renames
					if ($renamedPdf) {
						$oldFilename = $origBase . '.pdf';
						$newFilename = $newBase . '.pdf';
						
						// Ensure table exists
						$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'pool_files'";
						if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							// Create logic if needed, but assuming it exists or created by _docPoolData
							$qtxt = "CREATE TABLE IF NOT EXISTS pool_files (
								id serial NOT NULL,
								filename varchar(255) NOT NULL,
								subject text,
								account varchar(50),
								amount varchar(50),
								file_date varchar(50),
								invoice_number varchar(100),
								description text,
								updated timestamp DEFAULT CURRENT_TIMESTAMP,
								PRIMARY KEY (id),
								UNIQUE(filename)
							)";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						}
						
						// Check if old record exists
						$qtxt = "SELECT id FROM pool_files WHERE filename = '" . db_escape_string($oldFilename) . "'";
						$existing = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
						
						if ($existing) {
							// Update filename and metadata of existing record
							$qtxt = "UPDATE pool_files SET 
								filename = '" . db_escape_string($newFilename) . "',
								subject = '" . db_escape_string($newSubject ?: $newBase) . "',
								updated = CURRENT_TIMESTAMP";
								
							if ($newAccount) $qtxt .= ", account = '" . db_escape_string($newAccount) . "'";
							if ($newAmount) $qtxt .= ", amount = '" . db_escape_string($newAmount) . "'";
							if ($newDate) $qtxt .= ", file_date = '" . db_escape_string($newDate) . "'";
							if ($newInvoiceNumber) $qtxt .= ", invoice_number = '" . db_escape_string($newInvoiceNumber) . "'";
							if ($newInvoiceDescription) $qtxt .= ", description = '" . db_escape_string($newInvoiceDescription) . "'";
							
							$qtxt .= " WHERE id = '" . $existing['id'] . "'";
							db_modify($qtxt, __FILE__ . " linje " . __LINE__);
						} else {
							// Insert new record if old didn't exist
							$qtxt = "INSERT INTO pool_files (filename, subject, account, amount, file_date, invoice_number, description) VALUES (
								'" . db_escape_string($newFilename) . "',
								'" . db_escape_string($newSubject ?: $newBase) . "',
								'" . db_escape_string($newAccount ?: '') . "',
								'" . db_escape_string($newAmount ?: '') . "',
								'" . db_escape_string($newDate ?: '') . "',
								'" . db_escape_string($newInvoiceNumber ?: '') . "',
								'" . db_escape_string($newInvoiceDescription ?: '') . "'
							)";
							db_modify($qtxt, __FILE__ . " linje " . __LINE__);
						}
						
				// Update documents table if exists
						$qtxt = "UPDATE documents SET filename = '" . db_escape_string($newFilename) . "', 
								filepath = '" . db_escape_string("$puljePath/$newFilename") . "' 
								WHERE source = 'pulje' AND filename = '" . db_escape_string($oldFilename) . "'";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
						}
				
				// Note: We are no longer writing to .info files, but we leave existing ones (renamed)
				// Future cleanup can remove them.
			}
	}
	
	// Handle case where we're updating metadata without renaming
	$logFile = "../temp/docpool_edit_debug.log";
	file_put_contents($logFile, date('Y-m-d H:i:s') . " - Before metadata update check: rename=$rename, poolFile=$poolFile\n", FILE_APPEND);
	file_put_contents($logFile, date('Y-m-d H:i:s') . " - Fields: newAccount='$newAccount', newAmount='$newAmount', newSubject='$newSubject'\n", FILE_APPEND);
	file_put_contents($logFile, date('Y-m-d H:i:s') . " - Fields: newDate='$newDate', newInvoiceNumber='$newInvoiceNumber', newInvoiceDescription='$newInvoiceDescription'\n", FILE_APPEND);
		
	if ($rename && ($newAccount || $newAmount || $newSubject || $newDate || $newInvoiceNumber || $newInvoiceDescription) && $poolFile) {
		// Direct Database Update
		$qtxt = "SELECT id FROM pool_files WHERE filename = '" . db_escape_string($poolFile) . "'";
		$existing = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		
		if ($existing) {
			// Update existing
			$logFile = "../temp/docpool_edit_debug.log";
			file_put_contents($logFile, date('Y-m-d H:i:s') . " - docPool EDIT - Updating database for poolFile=$poolFile, id=" . $existing['id'] . "\n", FILE_APPEND);
			$qtxt = "UPDATE pool_files SET updated = CURRENT_TIMESTAMP";
			if ($newSubject) $qtxt .= ", subject = '" . db_escape_string($newSubject) . "'";
			if ($newAccount) $qtxt .= ", account = '" . db_escape_string($newAccount) . "'";
			if ($newAmount) $qtxt .= ", amount = '" . db_escape_string($newAmount) . "'";
			if ($newDate) $qtxt .= ", file_date = '" . db_escape_string($newDate) . "'";
			if ($newInvoiceNumber) $qtxt .= ", invoice_number = '" . db_escape_string($newInvoiceNumber) . "'";
			if ($newInvoiceDescription) $qtxt .= ", description = '" . db_escape_string($newInvoiceDescription) . "'";
			
			$qtxt .= " WHERE id = '" . $existing['id'] . "'";
			$logFile = "../temp/docpool_edit_debug.log";
			file_put_contents($logFile, date('Y-m-d H:i:s') . " - docPool EDIT - SQL: $qtxt\n", FILE_APPEND);
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			// Insert if missing (shouldn't happen usually)
			$baseName = pathinfo($poolFile, PATHINFO_FILENAME);
			$subject = $newSubject ?: $baseName;
			
			$qtxt = "INSERT INTO pool_files (filename, subject, account, amount, file_date, invoice_number, description) VALUES (
				'" . db_escape_string($poolFile) . "',
				'" . db_escape_string($subject) . "',
				'" . db_escape_string($newAccount ?: '') . "',
				'" . db_escape_string($newAmount ?: '') . "',
				'" . db_escape_string($newDate ?: '') . "',
				'" . db_escape_string($newInvoiceNumber ?: '') . "',
				'" . db_escape_string($newInvoiceDescription ?: '') . "'
			)";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	}

	// ✅ Prevent undefined variable warnings
	$modDate = $modDate ?? '';



	###############
	if ($unlink && $unlinkFile) {
		
		#if ($descFile) unlink("../".$docFolder."/$db/pulje/$descFile");
		if ($unlinkFile) {
			
				$puljePath = "$docFolder/$db/pulje";
				$origBase = pathinfo($unlinkFile, PATHINFO_FILENAME); 

				
				// Define the extensions you want to delete
				$extensionsToDelete = ['pdf', 'info'];

				foreach ($extensionsToDelete as $ext) {
					$fileToDelete = "$puljePath/$origBase.$ext";
					if (is_file($fileToDelete)) {
						if (unlink($fileToDelete)) {
							#error_log("Deleted: $fileToDelete");
						} else {
							error_log("Failed to delete: $fileToDelete");
						}
					} else {
						error_log("File not found: $fileToDelete");
					}
				}
				
				// Remove from database
				// Remove from database
				$filename = $unlinkFile;
				$qtxt = "DELETE FROM pool_files WHERE filename = '". db_escape_string($filename) ."'";
				// We execute directly because the table should exist (synced on load) and schema checks might be unreliable
				@db_modify($qtxt,__FILE__ . " linje " . __LINE__);


		}elseif (isset($_POST['poolFile'])) {
			$poolFile=if_isset($_POST['poolFile']);

			if ($poolFile) {
				unlink("../".$docFolder."/$db/pulje/$poolFile");
				
				// Remove from database
				$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = '$db' AND table_name = 'pool_files'";
				if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$qtxt = "DELETE FROM pool_files WHERE filename = '". db_escape_string($poolFile) ."'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
			
		}
#exit;
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/documents.php?$params&openPool=1\">";
		exit;
	}
	if ($insertFile) {
				include("docsIncludes/uploadDoc.php");
		exit;
	}
	$r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
	$google_docs=$r['box7'];

#	if ($sourceId && $source == 'kassekladde') {
	if ($source == 'kassekladde' && $kladde_id) {
		$qtxt = "select bogfort from kladdeliste where id='$kladde_id'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		($r['bogfort'] != '-')?$readOnly=1:$readOnly=0;
	} elseif ($sourceId && $source == 'creditorOrder') {
		$qtxt = "select status from ordrer where id='$sourceId'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		($r['status'] >= '3')?$readOnly=1:$readOnly=0;
	}
	
	$dir=$docFolder."/".$db."/pulje";
	$url="://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
	$url=str_replace("/includes/documents.php","/temp/$db/pulje/",$url);
	$httpS = if_isset($_SERVER,NULL,'HTTPS');
	if ($httpS) $url="s".$url;
	$url="http".$url;
	
	// Read poolFile from URL if present (user clicked on a row), otherwise use function parameter
	$urlPoolFile = if_isset($_GET, NULL, 'poolFile');
	if ($urlPoolFile) {
		$poolFile = $urlPoolFile; // Use the one from URL (user clicked)
	}
	
	$latestTime = 0;
	// Don't auto-select latest file when coming from kassekladde - let user choose
	if (!$poolFile && $source != 'kassekladde') {
		// Optimization: Try DB first to find latest file
		// Check table existence first to avoid errors during migration
		$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = '$db' AND table_name = 'pool_files'";
		$hasTable = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		
		$foundInDb = false;
		if ($hasTable) {
			$qtxt = "SELECT filename FROM pool_files ORDER BY file_date DESC, updated DESC LIMIT 1";
			$latestRow = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			
			if ($latestRow) {
				$poolFile = $latestRow['filename'];
				$foundInDb = true;
			}
		}
		
		if (!$foundInDb && is_dir($dir)) {
			if ($dh = opendir($dir)) {
				while (($file = readdir($dh)) !== false) {
					// Check for .pdf file (case-insensitive), skip hidden files
					if (substr($file, 0, 1) != '.' && preg_match('/\.pdf$/i', $file)) {
						$filePath = rtrim($dir, '/') . '/' . $file;
						#clearstatcache(); 
						$modTime = filemtime($filePath);

						if ($modTime > $latestTime) {
							$latestTime = $modTime;
							$poolFile = $file;
						}
					}
				}
				closedir($dh);
			}
		}
	}

	// $poolParams =
	// "openPool=1&".
	// "docFolder=$docFolder&".
	// "poolFile=$poolFile&".
	// "fokus=$fokus&".
	// "bilag=$bilag";

	$poolParams =
	"openPool=1"."&".
	"kladde_id=$kladde_id"."&".
	"bilag=$bilag"."&".
	"fokus=$fokus"."&".
	"poolFile=$poolFile"."&".
	"docFolder=$docFolder"."&".
	"sourceId=$sourceId"."&".
	"source=$source";

	global $menu;
	if (!isset($top_bund)) $top_bund = "";
	
	// Determine back URL based on source (same logic as header.php)
	if ($source=="kassekladde") {
		$backUrl = "../finans/kassekladde.php?kladde_id=$kladde_id&id=$sourceId&fokus=$fokus";
	} elseif ($source=="debitorOrdrer") {
		$backUrl = "../debitor/ordre.php?id=$sourceId&fokus=$fokus";
	} elseif ($source=="creditorOrder") {
		$backUrl = "../kreditor/ordre.php?id=$sourceId&fokus=$fokus";
	} else {
		$backUrl = "../debitor/historikkort.php?id=$sourceId&fokus=$fokus";
	}
	// Print header banner
	if ($menu == 'S') {
		print "<table id='topBarHeader' width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		include("docsIncludes/topLineDocuments.php");
		print "</tbody></table>";
	}
	
	// Include DocPool CSS files
	$cssPath = "../css";
	if (!file_exists($cssPath)) {
		if (file_exists("../../css")) $cssPath = "../../css";
		elseif (file_exists("../../../css")) $cssPath = "../../../css";
	}
	print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$cssPath/docpool-variables.css\">\n";
	print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$cssPath/docpool.css\">\n";
	// SVG icon definitions (inline SVGs from iconsvg.xyz style)
	print "<style>
		.icon-svg { display: inline-block; width: 1em; height: 1em; vertical-align: -0.125em; fill: none; stroke: currentColor; }
		.icon-svg-sm { width: 14px; height: 14px; }
		.icon-svg-md { width: 16px; height: 16px; }
		.icon-svg-lg { width: 20px; height: 20px; }
		.icon-spin { animation: icon-spin 1s linear infinite; }
		@keyframes icon-spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}
	</style>\n";
	
	// Add dynamic CSS variables for button colors
	$lightButtonColor = brightenColor($buttonColor, 0.6);
	print "<style>
		:root {
			--docpool-primary: $buttonColor;
			--docpool-primary-text: $buttonTxtColor;
			--docpool-primary-light: $lightButtonColor;
		}
		/* Dynamic button color overrides for top bar */
		#topBarHeader tbody tr td a button,
		#topBarHeader tbody tr td a button:hover,
		#topBarHeader tbody tr td a:hover button,
		#topBarHeader tbody tr td a:focus button,
		#topBarHeader tbody tr td a:active button,
		#topBarHeader tbody tr td button,
		#topBarHeader tbody tr td button:hover,
		#topBarHeader tbody tr td button:focus,
		#topBarHeader tbody tr td button:active {
			// background-color: $buttonColor !important;
			color: $buttonTxtColor !important;
			border-color: $buttonColor !important;
		}
		#topBarHeader tbody tr,
		#topBarHeader tbody tr:hover,
		#topBarHeader tbody tr td,
		#topBarHeader tbody tr td:hover {
			background-color: $buttonColor !important;
		}
	</style>";

	$perfLog = "../temp/docpool_perf.log";
	if (!isset($startTime)) $startTime = microtime(true);
	file_put_contents($perfLog, sprintf("Time: %.4f - Before HTML output\n", microtime(true) - $startTime), FILE_APPEND);
	
	print "<form name=\"gennemse\" action=\"documents.php?$params&$poolParams\" method=\"post\">\n";
	print "<input type='hidden' id='hiddenSubject' name='newSubject' value=''>\n";
	print "<input type='hidden' id='hiddenAccount' name='newAccount' value=''>\n";
	print "<input type='hidden' id='hiddenAmount' name='newAmount' value=''>\n";
	print "<input type='hidden' id='hiddenDate' name='newDate' value=''>\n";

#####
// Modern flexbox layout instead of tables
// Styles are now in docpool.css
// Define SVG icons as PHP variables for use in HTML
$svgStar = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
$svgCheck = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';
$svgCalendar = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><path d="M9 16l2 2 4-4"></path></svg>';
$svgPlus = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>';
$svgPointer = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 14a8 8 0 0 1-8 8"></path><path d="M18 11v-1a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v0"></path><path d="M14 10V9a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v1"></path><path d="M10 9.5V4a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v10"></path><path d="M18 11a2 2 0 1 1 4 0v3a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"></path></svg>';
$svgPencil = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>';
$svgTrash = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>';
$svgSave = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>';
$svgX = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
$svgTable = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line></svg>';
$svgGrid = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>';
$svgUpload = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>';
$svgChevronDown = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
$svgSpinner = '<svg class="icon-svg icon-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>';
$svgLink = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>';
$svgFile = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>';
$svgScan = '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><line x1="7" y1="12" x2="17" y2="12"></line></svg>';

print "<div id='docPoolContainer'>";
print "<script>console.time('docPoolRender');</script>";
print "<div id='leftPanel'>";

// Display kassekladde information if inserting to existing entry (just above the list)
if ($source == 'kassekladde' && $sourceId) {
	$qtxt = "select bilag, beskrivelse, transdate, debet, kredit, faktura, amount from kassekladde where id = '$sourceId'";
	$kladdeInfo = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if ($kladdeInfo) {
		$displayBilag = $kladdeInfo['bilag'];
		$displayBeskrivelse = $kladdeInfo['beskrivelse'] ? htmlspecialchars($kladdeInfo['beskrivelse']) : '';
		$displayDato = dkdato($kladdeInfo['transdate']);
		$displayDebet = $kladdeInfo['debet'] ? $kladdeInfo['debet'] : '';
		$displayKredit = $kladdeInfo['kredit'] ? $kladdeInfo['kredit'] : '';
		$displayFaktura = $kladdeInfo['faktura'] ? htmlspecialchars($kladdeInfo['faktura']) : '';
		$displayAmount = $kladdeInfo['amount'] ? dkdecimal($kladdeInfo['amount']) : '';
		
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" style=\"margin-bottom: 10px; margin-top: 10px;\"><tbody>";
		print "<tr>";
		print "<td style=\"background-color: $buttonColor; color: $buttonTxtColor; padding: 8px; border: 1px solid #ddd;\">";
		print "<font face=\"Helvetica, Arial, sans-serif\" style=\"font-weight: bold; font-size: 13px;\">" . findtekst('1408|Kassebilag', $sprog_id) . " - Bilag #" . htmlspecialchars($displayBilag) . "</font>";
		print "</td></tr>";
		print "<tr><td style=\"background-color: " . (isset($bgcolor5) ? $bgcolor5 : '#ffffff') . "; padding: 8px; border: 1px solid #ddd; border-top: none;\">";
		print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"4\" style=\"font-family: Arial, sans-serif; font-size: 12px;\">";
		if ($displayDato) print "<tr><td width=\"20%\" style=\"font-weight: bold;\">Dato:</td><td>" . htmlspecialchars($displayDato) . "</td></tr>";
		if ($displayBeskrivelse) print "<tr><td style=\"font-weight: bold;\">Beskrivelse:</td><td>" . $displayBeskrivelse . "</td></tr>";
		if ($displayDebet) print "<tr><td style=\"font-weight: bold;\">Debet:</td><td>" . htmlspecialchars($displayDebet) . "</td></tr>";
		if ($displayKredit) print "<tr><td style=\"font-weight: bold;\">Kredit:</td><td>" . htmlspecialchars($displayKredit) . "</td></tr>";
		if ($displayFaktura) print "<tr><td style=\"font-weight: bold;\">Fakturanr:</td><td>" . $displayFaktura . "</td></tr>";
		if ($displayAmount) print "<tr><td style=\"font-weight: bold;\">Beløb:</td><td>" . htmlspecialchars($displayAmount) . "</td></tr>";
		print "</table>";
		print "</td></tr>";
		print "</tbody></table>";
	}
} elseif ($source == 'kassekladde' && !$sourceId && $bilag) {
	// Show bilag number if creating new entry
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" style=\"margin-bottom: 10px; margin-top: 10px;\"><tbody>";
	print "<tr>";
	print "<td style=\"background-color: $buttonColor; color: $buttonTxtColor; padding: 8px; border: 1px solid #ddd;\">";
	print "<font face=\"Helvetica, Arial, sans-serif\" style=\"font-weight: bold; font-size: 13px;\">" . findtekst('1408|Kassebilag', $sprog_id) . " - Nyt bilag #" . htmlspecialchars($bilag) . "</font>";
	print "</td></tr>";
	print "</tbody></table>";
}

// View mode toggle and search box
print "<div id='docPoolToolbar' style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding: 8px; background-color: #f8f9fa; border-radius: 6px;'>";
// Search box
print "<div style='flex: 1; margin-right: 10px;'>";
print "<input type='text' id='poolSearchBox' placeholder='Søg...' oninput='filterPoolFiles()' style='width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; box-sizing: border-box;'>";
print "</div>";
// View mode toggle and extract all button
print "<div style='display: flex; gap: 8px;'>";
// Extract all button
print "<button type='button' id='extractAllBtn' onclick='extractAllPoolFiles()' title='Opdater alle filer med fakturadata' style='padding: 8px 12px; background-color: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 4px;'>$svgScan <span style='font-size: 12px;'>Opdater alle</span></button>";
// Delete selected button
print "<button type='button' id='deleteSelectedBtn' onclick='deleteSelectedFiles()' title='Slet valgte filer' style='padding: 8px 12px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 4px;'>$svgTrash <span style='font-size: 12px;'>Slet valgte</span></button>";
print "<div style='display: flex; gap: 0;'>";
print "<button type='button' id='tableViewBtn' onclick='setViewMode(\"table\")' title='Tabelvisning' style='padding: 8px 12px; background-color: $buttonColor; color: $buttonTxtColor; border: none; border-radius: 4px 0 0 4px; cursor: pointer; font-size: 14px;'>$svgTable</button>";
print "<button type='button' id='cardViewBtn' onclick='setViewMode(\"card\")' title='Kortvisning' style='padding: 8px 12px; background-color: #e9ecef; color: #495057; border: none; border-radius: 0 4px 4px 0; cursor: pointer; font-size: 14px;'>$svgGrid</button>";
print "</div>";
print "</div>";
print "</div>";

// Preview popup container (for card view hover)
print "<div id='previewPopup' style='display: none; position: fixed; z-index: 99999; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); padding: 10px; max-width: 500px; max-height: 600px; overflow: hidden;'>";
print "<div id='previewTitle' style='padding: 8px; background: $buttonColor; color: $buttonTxtColor; border-radius: 4px 4px 0 0; margin: -10px -10px 10px -10px; font-size: 12px; font-weight: bold;'>Forhåndsvisning</div>";
print "<div id='previewContent'><div style='display: flex; align-items: center; justify-content: center; width: 480px; height: 550px; background: #f5f5f5; color: #666; font-size: 14px;'>Indlæser...</div></div>";
print "</div>";

print "<div id='fileListContainer'>Loading files...</div>";
// Fixed bottom section will be added here later via PHP (before leftPanel closes)


// $combinedParams = $params . '&' . $poolParams; 
$encodedDir = urlencode($dir);
$poolFileJs = json_encode($poolFile); // safely escapes quotes
$JsSum = json_encode($sum); // safely escapes quotes
$JsDato = json_encode($dato); // kassekladde date for matching


// Calculate lightened button color using PHP function from topline_settings.php
$lightButtonColor = brightenColor($buttonColor, 0.6); // Lighten by 60% (0.6 = 60%)

$buttonColorJs = json_encode($buttonColor);
$buttonTxtColorJs = json_encode($buttonTxtColor);
$lightButtonColorJs = json_encode($lightButtonColor);

print <<<JS
<script>
(() => {
    let docData = [];
    let currentSort = { field: 'date', asc: false };
    const containerId = 'fileListContainer';
		// Get poolFile from URL if present (user clicked on a row), otherwise null (no auto-selection)
		const urlParams = new URLSearchParams(window.location.search);
		const poolFile = urlParams.get('poolFile') || null;
		const totalSum = {$JsSum};
		const targetDate = {$JsDato}; // kassekladde date for matching
		const buttonColor = {$buttonColorJs};
		const buttonTxtColor = {$buttonTxtColorJs};
		const lightButtonColor = {$lightButtonColorJs};
		
		// View mode state (table or card) - default to table, save preference in localStorage
		let viewMode = localStorage.getItem('docPoolViewMode') || 'table';
		let searchFilter = '';
		let previewTimeout = null;
		let currentPreviewPath = null;
		const docFolder = '{$docFolder}';
		const db = '{$db}';
		
		// SVG icons for JavaScript use
		const svgIcons = {
			star: '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>',
			check: '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
			calendar: '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><path d="M9 16l2 2 4-4"></path></svg>',
			plus: '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>',
			pointer: '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 14a8 8 0 0 1-8 8"></path><path d="M18 11v-1a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v0"></path><path d="M14 10V9a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v1"></path><path d="M10 9.5V4a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v10"></path><path d="M18 11a2 2 0 1 1 4 0v3a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"></path></svg>',
			pencil: '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>',
			trash: '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>',
			save: '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>',
			x: '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
			file: '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
			scan: '<svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><line x1="7" y1="12" x2="17" y2="12"></line></svg>'
		};
		
		// Initialize view mode toggle buttons on page load
		document.addEventListener('DOMContentLoaded', function() {
			updateViewModeButtons();
			updatePanelLayout();
		});
		
		// Set view mode and re-render
		window.setViewMode = function(mode) {
			viewMode = mode;
			localStorage.setItem('docPoolViewMode', mode);
			updateViewModeButtons();
			updatePanelLayout();
			renderCurrentView();
		};
		
		// Update toggle button styles
		function updateViewModeButtons() {
			const tableBtn = document.getElementById('tableViewBtn');
			const cardBtn = document.getElementById('cardViewBtn');
			if (tableBtn && cardBtn) {
				if (viewMode === 'table') {
					tableBtn.style.backgroundColor = buttonColor;
					tableBtn.style.color = buttonTxtColor;
					cardBtn.style.backgroundColor = '#e9ecef';
					cardBtn.style.color = '#495057';
				} else {
					cardBtn.style.backgroundColor = buttonColor;
					cardBtn.style.color = buttonTxtColor;
					tableBtn.style.backgroundColor = '#e9ecef';
					tableBtn.style.color = '#495057';
				}
			}
		}
		
		// Update panel layout based on view mode (hide right panel in card mode)
		function updatePanelLayout() {
			const leftPanel = document.getElementById('leftPanel');
			const rightPanel = document.getElementById('rightPanel');
			const resizer = document.getElementById('resizer');
			
			if (viewMode === 'card') {
				// Card mode: hide right panel and resizer, make left panel full width
				if (rightPanel) rightPanel.style.display = 'none';
				if (resizer) resizer.style.display = 'none';
				if (leftPanel) {
					leftPanel.style.flex = '1 1 100%';
					leftPanel.style.maxWidth = '100%';
				}
			} else {
				// Table mode: show right panel and resizer, restore split layout
				if (rightPanel) rightPanel.style.display = 'flex';
				if (resizer) resizer.style.display = 'block';
				if (leftPanel) {
					leftPanel.style.flex = '0 0 35%';
					leftPanel.style.width = '50%';
				}
			}
			
			// Update fixed div after layout change
			if (typeof updateFixedDiv === 'function') {
				setTimeout(updateFixedDiv, 100);
			}
		}
		
		// Render based on current view mode
		function renderCurrentView() {
			if (viewMode === 'card') {
				renderFilesCard();
			} else {
				renderFiles();
			}
		}
		
		// Filter pool files based on search input
		window.filterPoolFiles = function() {
			const searchBox = document.getElementById('poolSearchBox');
			searchFilter = searchBox ? searchBox.value.toLowerCase() : '';
			renderCurrentView();
		};
		
		// Preview popup functions for card view
		window.showPreview = function(element, event) {
			const filepath = element.getAttribute('data-filepath');
			const filename = element.getAttribute('data-filename');
			
			if (!filepath) return;
			
			// Clear any existing timeout
			if (previewTimeout) clearTimeout(previewTimeout);
			
			// Delay showing preview slightly to avoid flickering
			previewTimeout = setTimeout(function() {
				const popup = document.getElementById('previewPopup');
				const content = document.getElementById('previewContent');
				const title = document.getElementById('previewTitle');
				
				// Only reload if different file
				if (currentPreviewPath !== filepath) {
					currentPreviewPath = filepath;
					if (title) title.textContent = filename || 'Forhåndsvisning';
					
					// Check file extension
					const ext = filepath.split('.').pop().toLowerCase();
					
					if (ext === 'pdf') {
						content.innerHTML = '<embed src=\"' + filepath + '#pagemode=none\" type=\"application/pdf\" style=\"width:480px;height:550px;\">';
					} else if (['jpg', 'jpeg', 'png', 'gif'].indexOf(ext) !== -1) {
						content.innerHTML = '<img src=\"' + filepath + '\" style=\"max-width:480px;max-height:550px;display:block;margin:0 auto;\">';
					} else {
						content.innerHTML = '<div style=\"display:flex;align-items:center;justify-content:center;width:480px;height:550px;background:#f5f5f5;color:#666;font-size:14px;\">Forhåndsvisning ikke tilgængelig</div>';
					}
				}
				
				if (popup) {
					popup.style.display = 'block';
					movePreview(event);
				}
			}, 300);
		};
		
		window.hidePreview = function() {
			if (previewTimeout) {
				clearTimeout(previewTimeout);
				previewTimeout = null;
			}
			const popup = document.getElementById('previewPopup');
			if (popup) popup.style.display = 'none';
		};
		
		window.movePreview = function(event) {
			const popup = document.getElementById('previewPopup');
			if (!popup || popup.style.display === 'none') return;
			
			let x = event.clientX + 20;
			let y = event.clientY - 100;
			
			// Keep within viewport
			const viewportWidth = window.innerWidth;
			const viewportHeight = window.innerHeight;
			
			// If would go off right edge, show on left side of cursor
			if (x + 520 > viewportWidth) {
				x = event.clientX - 520;
			}
			
			// If would go off bottom, adjust y
			if (y + 620 > viewportHeight) {
				y = viewportHeight - 630;
			}
			
			// Don't go above viewport
			if (y < 10) y = 10;
			
			popup.style.left = x + 'px';
			popup.style.top = y + 'px';
		};

    async function fetchFiles() {
        const dir = '{$encodedDir}'; 
      

        try {
            const response = await fetch('_docPoolData.php?dir=' + dir + '&poolParams=' + encodeURIComponent('{$poolParams}'));
            const data = await response.json();

            if (data.error) {
                document.getElementById(containerId).innerHTML = '<div style="color:red;">' + escapeHTML(data.error) + '</div>';
				console.error(dir + ': ' + data.error);
                return;
            }

            docData = data;
            renderCurrentView();
        } catch (error) {
            document.getElementById(containerId).innerHTML = '<div style="color:red;">Error loading files</div>';
            console.error(error);
        }
    }

    function renderFiles() {
			if (!docData.length) {
				document.getElementById(containerId).innerHTML = '<em>No files found.</em>';
				return;
			}
			
			// Read poolFile from current URL (in case it changed after page load)
			// Get all poolFile parameters and use the last non-empty one
			const currentUrlParams = new URLSearchParams(window.location.search);
			const allCurrentPoolFiles = currentUrlParams.getAll('poolFile');
			let currentPoolFile = null;
			for (let i = allCurrentPoolFiles.length - 1; i >= 0; i--) {
				if (allCurrentPoolFiles[i] && allCurrentPoolFiles[i].trim() !== '') {
					currentPoolFile = allCurrentPoolFiles[i];
					break;
				}
			}
			
		let html = `
  <div style="margin:0; padding-right:3px; width:100%; box-sizing:border-box; position: relative; padding-bottom: 70px;">
    <table style="border-collapse:collapse; width:100%; font-family:Arial, sans-serif; font-size:13px; border:1px solid #ddd; margin:0; padding:0; table-layout:fixed;">
      <thead style="background:${buttonColor}; color:${buttonTxtColor}; position:sticky; top:0; z-index:10; margin:0; padding:0;">
        <tr>
					<th style="padding:8px; border:1px solid #ddd; text-align:center; width: 40px; color:${buttonTxtColor};" onclick="event.stopPropagation();">
						<input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)" title="Vælg alle" style="cursor: pointer; width: 18px; height: 18px;">
					</th>
					<th onclick="sortFiles('subject')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left; color:${buttonTxtColor};">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Fil</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th onclick="sortFiles('account')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left; color:${buttonTxtColor};">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Kontonr</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th onclick="sortFiles('amount')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left; color:${buttonTxtColor};">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Beløb</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th onclick="sortFiles('invoiceNumber')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left; color:${buttonTxtColor};">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Fakturanr</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th onclick="sortFiles('description')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left; color:${buttonTxtColor};">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Beskrivelse</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th onclick="sortFiles('date')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left; color:${buttonTxtColor};">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Dato</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th style="padding:8px; border:1px solid #ddd; text-align:center; width: 90px; color:${buttonTxtColor};">
						<span>Handlinger</span>
					</th>
				</tr>
			</thead>
			<tbody>
		`;


		let activeRows = '';
		let perfectMatchRows = '';
		let matchingAmountRows = '';
		let dateMatchRows = '';
		let combinationRows = '';
		let otherRows = '';
		
		// Normalize the total sum for comparison
		const normalizedTotal = parseFloat(totalSum?.replace(/\./g, '').replace(',', '.') || 0);
		const hasAmountToMatch = normalizedTotal !== 0 && !isNaN(normalizedTotal);
		
		// Normalize target date for comparison (convert dd-mm-yyyy to yyyy-mm-dd for comparison)
		let normalizedTargetDate = null;
		if (targetDate) {
			// Handle Danish date format (dd-mm-yyyy)
			const dateParts = targetDate.split('-');
			if (dateParts.length === 3) {
				if (dateParts[0].length === 4) {
					// Already yyyy-mm-dd format
					normalizedTargetDate = targetDate;
				} else {
					// Convert dd-mm-yyyy to yyyy-mm-dd
					normalizedTargetDate = dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
				}
			}
		}
		const hasDateToMatch = normalizedTargetDate !== null;
		
		// First pass: count matching documents and find combinations
		let matchingCount = 0;
		let exactMatches = []; // Store filenames that are exact amount matches
		let perfectMatches = []; // Store filenames that match BOTH amount AND date
		let dateOnlyMatches = []; // Store filenames that match date but not amount
		let combinationMatches = new Set(); // Store filenames that are part of a combination
		let combinationGroups = []; // Store the actual combinations found
		
		// Helper function to normalize date for comparison
		const normalizeDate = function(dateStr) {
			if (!dateStr) return null;
			// Remove time portion if present
			dateStr = dateStr.split(' ')[0];
			const parts = dateStr.split('-');
			if (parts.length === 3) {
				if (parts[0].length === 4) {
					// Already yyyy-mm-dd
					return dateStr;
				} else {
					// dd-mm-yyyy to yyyy-mm-dd
					return parts[2] + '-' + parts[1] + '-' + parts[0];
				}
			}
			return null;
		};
		
		if (hasAmountToMatch || hasDateToMatch) {
			// Build list of documents with valid amounts
			const docsWithAmounts = [];
			for (let i = 0; i < docData.length; i++) {
				const row = docData[i];
				const normalizedAmount = parseFloat(row.amount);
				const rowDate = normalizeDate(row.date);
				const filename = row.filename || '';
				
				// Check date match
				const isDateMatch = hasDateToMatch && rowDate === normalizedTargetDate;
				
				// Check amount match
				const isAmountMatch = hasAmountToMatch && !isNaN(normalizedAmount) && Math.abs(normalizedAmount - normalizedTotal) < 0.01;
				
				if (!isNaN(normalizedAmount) && normalizedAmount > 0) {
					docsWithAmounts.push({
						index: i,
						filename: filename,
						amount: normalizedAmount,
						date: rowDate,
						row: row
					});
				}
				
				// Categorize matches
				if (isAmountMatch && isDateMatch) {
					// Perfect match - both amount AND date
					perfectMatches.push(filename);
					matchingCount++;
				} else if (isAmountMatch) {
					// Amount only match
					exactMatches.push(filename);
					matchingCount++;
				} else if (isDateMatch) {
					// Date only match
					dateOnlyMatches.push(filename);
				}
			}
			
			// Only look for combinations if no exact matches found
			if (matchingCount === 0 && docsWithAmounts.length >= 2) {
				// Find pairs that sum to target
				for (let i = 0; i < docsWithAmounts.length; i++) {
					for (let j = i + 1; j < docsWithAmounts.length; j++) {
						const sum = docsWithAmounts[i].amount + docsWithAmounts[j].amount;
						if (Math.abs(sum - normalizedTotal) < 0.01) {
							combinationMatches.add(docsWithAmounts[i].filename);
							combinationMatches.add(docsWithAmounts[j].filename);
							combinationGroups.push({
								files: [docsWithAmounts[i].filename, docsWithAmounts[j].filename],
								amounts: [docsWithAmounts[i].amount, docsWithAmounts[j].amount],
								sum: sum
							});
						}
					}
				}
				
				// Find triplets that sum to target (only if no pairs found)
				if (combinationGroups.length === 0 && docsWithAmounts.length >= 3) {
					for (let i = 0; i < docsWithAmounts.length; i++) {
						for (let j = i + 1; j < docsWithAmounts.length; j++) {
							for (let k = j + 1; k < docsWithAmounts.length; k++) {
								const sum = docsWithAmounts[i].amount + docsWithAmounts[j].amount + docsWithAmounts[k].amount;
								if (Math.abs(sum - normalizedTotal) < 0.01) {
									combinationMatches.add(docsWithAmounts[i].filename);
									combinationMatches.add(docsWithAmounts[j].filename);
									combinationMatches.add(docsWithAmounts[k].filename);
									combinationGroups.push({
										files: [docsWithAmounts[i].filename, docsWithAmounts[j].filename, docsWithAmounts[k].filename],
										amounts: [docsWithAmounts[i].amount, docsWithAmounts[j].amount, docsWithAmounts[k].amount],
										sum: sum
									});
								}
							}
						}
					}
				}
				
				// Find quads that sum to target (only if no pairs or triplets found)
				if (combinationGroups.length === 0 && docsWithAmounts.length >= 4) {
					for (let i = 0; i < docsWithAmounts.length; i++) {
						for (let j = i + 1; j < docsWithAmounts.length; j++) {
							for (let k = j + 1; k < docsWithAmounts.length; k++) {
								for (let l = k + 1; l < docsWithAmounts.length; l++) {
									const sum = docsWithAmounts[i].amount + docsWithAmounts[j].amount + 
										docsWithAmounts[k].amount + docsWithAmounts[l].amount;
									if (Math.abs(sum - normalizedTotal) < 0.01) {
										combinationMatches.add(docsWithAmounts[i].filename);
										combinationMatches.add(docsWithAmounts[j].filename);
										combinationMatches.add(docsWithAmounts[k].filename);
										combinationMatches.add(docsWithAmounts[l].filename);
										combinationGroups.push({
											files: [docsWithAmounts[i].filename, docsWithAmounts[j].filename, 
												docsWithAmounts[k].filename, docsWithAmounts[l].filename],
											amounts: [docsWithAmounts[i].amount, docsWithAmounts[j].amount,
												docsWithAmounts[k].amount, docsWithAmounts[l].amount],
											sum: sum
										});
									}
								}
							}
						}
					}
				}
			}
		}
		
		// Store combination info for display
		const hasCombinationMatches = combinationMatches.size > 0;
		const hasPerfectMatches = perfectMatches.length > 0;
		const hasDateOnlyMatches = dateOnlyMatches.length > 0;

		for (const row of docData) {
			// Apply search filter
			if (searchFilter) {
				const searchText = ((row.filename || '') + ' ' + (row.subject || '') + ' ' + (row.account || '') + ' ' + (row.amount || '') + ' ' + (row.date || '') + ' ' + (row.invoiceNumber || '') + ' ' + (row.description || '')).toLowerCase();
				if (searchText.indexOf(searchFilter) === -1) {
					continue;
				}
			}
						
			const dateFormatted = escapeHTML(row.date.split(' ')[0]);

			// Use filename directly from row data - more reliable than parsing from href
			const rowFilename = row.filename || '';
			
			// Also try to get it from href as fallback
			const url = new URL(row.href, window.location.origin);
			const allPoolFiles = url.searchParams.getAll('poolFile');
			let lastPoolFile = null;
			for (let i = allPoolFiles.length - 1; i >= 0; i--) {
				if (allPoolFiles[i] && allPoolFiles[i].trim() !== '') {
					lastPoolFile = allPoolFiles[i];
					break;
				}
			}
			// Prefer filename from row data, fallback to href parameter
			const poolFileFromHref = rowFilename || lastPoolFile || '';
			
			// Normalize both values for comparison (decode, trim, and handle case)
			const normalizeFile = (file) => {
				if (!file) return null;
				try {
					// Decode URL encoding, then trim whitespace
					return decodeURIComponent(String(file)).trim();
				} catch (e) {
					// If decode fails, just trim
					return String(file).trim();
				}
			};
			const normalizedCurrent = normalizeFile(currentPoolFile);
			const normalizedRow = normalizeFile(poolFileFromHref);
			// Compare normalized values (case-sensitive filename comparison)
			const isMatch = normalizedCurrent && normalizedRow && normalizedCurrent === normalizedRow;
			
			// Debug logging (remove in production if not needed)
			if (normalizedCurrent && normalizedRow && normalizedCurrent !== normalizedRow) {
				console.log('PoolFile mismatch:', {
					current: normalizedCurrent,
					row: normalizedRow,
					rawCurrent: currentPoolFile,
					rawRow: poolFileFromHref
				});
			}

			// Check if this row's amount matches the target amount
			const normalizedAmount = parseFloat(row.amount);
			const isAmountMatch = hasAmountToMatch && !isNaN(normalizedAmount) && Math.abs(normalizedAmount - normalizedTotal) < 0.01;
			
			// Check if this row's date matches the target date
			const rowDate = normalizeDate(row.date);
			const isDateMatch = hasDateToMatch && rowDate === normalizedTargetDate;
			
			// Check for perfect match (both amount AND date)
			const isPerfectMatch = isAmountMatch && isDateMatch;
			
			// Check if this row is part of a combination match
			const isCombinationMatch = hasCombinationMatches && combinationMatches.has(poolFileFromHref);

			// Determine row style based on selection and match type
			let rowStyle = "border-bottom:1px solid #ddd;";
			if (isMatch) {
				rowStyle = `border-bottom:1px solid #ddd; background-color:${lightButtonColor} !important; color:#000000 !important; font-weight:bold;`;
			} else if (isPerfectMatch) {
				// Blue/purple background for perfect match (amount + date)
				rowStyle = "border-bottom:1px solid #ddd; background-color:#cce5ff !important; border-left: 4px solid #004085 !important;";
			} else if (isAmountMatch) {
				// Green-tinted background for exact amount matches
				rowStyle = "border-bottom:1px solid #ddd; background-color:#d4edda !important;";
			} else if (isDateMatch && !isAmountMatch) {
				// Light blue background for date-only matches
				rowStyle = "border-bottom:1px solid #ddd; background-color:#e7f3ff !important;";
			} else if (isCombinationMatch) {
				// Amber/orange-tinted background for combination matches
				rowStyle = "border-bottom:1px solid #ddd; background-color:#fff3cd !important;";
			}

			// Format amount with match indicator
			let amountDisplay = escapeHTML(row.amount);
			if (isPerfectMatch) {
				amountDisplay = "<span style='color: #004085; font-weight: bold;'><span style='margin-right: 4px; color: #007bff;'>" + svgIcons.star + "</span>" + escapeHTML(row.amount) + "</span>";
			} else if (isAmountMatch) {
				amountDisplay = "<span style='color: #155724; font-weight: bold;'><span style='margin-right: 4px; color: #28a745;'>" + svgIcons.check + "</span>" + escapeHTML(row.amount) + "</span>";
			} else if (isCombinationMatch) {
				amountDisplay = "<span style='color: #856404; font-weight: bold;'><span style='margin-right: 4px; color: #ffc107;'>" + svgIcons.plus + "</span>" + escapeHTML(row.amount) + "</span>";
			}
			
			// Format date with match indicator
			let dateDisplay = dateFormatted;
			if (isDateMatch) {
				dateDisplay = "<span style='color: #004085; font-weight: bold;'><span style='margin-right: 4px; color: #007bff;'>" + svgIcons.calendar + "</span>" + dateFormatted + "</span>";
			}

			// All cells start as non-editable (text)
			const subjectCell = "<span class='cell-content'>" + escapeHTML(row.subject) + "</span>";
			const accountCell = "<span class='cell-content'>" + escapeHTML(row.account) + "</span>";
			const amountCell = "<span class='cell-content'>" + amountDisplay + "</span>";
			const dateCell = "<span class='cell-content'>" + dateDisplay + "</span>";
			const invoiceNumberCell = "<span class='cell-content'>" + escapeHTML(row.invoiceNumber) + "</span>";
			const descriptionCell = "<span class='cell-content'>" + escapeHTML(row.description) + "</span>";

			// poolFileFromHref already set above
			const deleteUrl = row.href.replace(/poolFile=[^&]*/, '') + (row.href.includes('?') ? '&' : '?') + 'unlink=1&unlinkFile=' + encodeURIComponent(poolFileFromHref);
			
			const actionsCell = "<div style='display: flex; gap: 4px; justify-content: center; align-items: center; flex-wrap: wrap;'>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); enableRowEdit(this, \"" + escapeHTML(poolFileFromHref) + "\", \"" + escapeHTML(row.subject) + "\", \"" + escapeHTML(row.account) + "\", \"" + escapeHTML(row.amount) + "\", \"" + dateFormatted + "\", \"" + escapeHTML(row.invoiceNumber || '') + "\", \"" + escapeHTML(row.description || '') + "\"); return false;' style='padding: 4px 8px; background-color: " + buttonColor + "; color: " + buttonTxtColor + "; border: 1px solid " + buttonColor + "; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.opacity=\"0.9\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.opacity=\"1\"; this.style.transform=\"scale(1)\"' title='Rediger'>" + svgIcons.pencil + "</button>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); deletePoolFile(\"" + escapeHTML(poolFileFromHref) + "\", " + JSON.stringify(row.subject) + ", \"" + deleteUrl + "\"); return false;' style='padding: 4px 8px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#c82333\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#dc3545\"; this.style.transform=\"scale(1)\"' title='Slet'>" + svgIcons.trash + "</button>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); extractPoolFile(\"" + escapeHTML(poolFileFromHref) + "\"); return false;' style='padding: 4px 8px; background-color: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#138496\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#17a2b8\"; this.style.transform=\"scale(1)\"' title='Udtræk fakturadata'>" + svgIcons.scan + "</button>" +
				"</div>";

			// Check if this checkbox should be checked (restore from sessionStorage)
			const savedChecked = sessionStorage.getItem('docPool_checked_' + poolFileFromHref) === 'true';
			const checkedAttr = savedChecked ? ' checked' : '';
			
			const dataAttrs = "data-pool-file='" + escapeHTML(poolFileFromHref) + "' " + 
				(isMatch ? "data-selected='true' " : "") + 
				(isPerfectMatch ? "data-perfect-match='true' " : "") +
				(isAmountMatch && !isPerfectMatch ? "data-amount-match='true' " : "") + 
				(isDateMatch && !isAmountMatch ? "data-date-match='true' " : "") +
				(isCombinationMatch ? "data-combination-match='true' " : "");
				const rowHTML = "<tr " + dataAttrs + "style='" + rowStyle + " cursor: pointer;' onclick=\"if(!event.target.closest('button') && !event.target.closest('input') && !this.hasAttribute('data-editing')) { saveCheckboxState(); window.location.href='" + row.href + "'; }\">" +
					"<td style='padding:6px; border:1px solid #ddd; text-align:center; width: 40px;' onclick='event.stopPropagation();'><input type='checkbox' class='file-checkbox' value='" + escapeHTML(poolFileFromHref) + "'" + checkedAttr + " onchange='saveCheckboxState(); updateBulkButton();' onclick='event.stopPropagation();' style='cursor: pointer; width: 18px; height: 18px;'></td>" +
					"<td style='padding:6px; border:1px solid #ddd; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.subject) + "'>" + subjectCell + "</td>" +
					"<td style='padding:6px; border:1px solid #ddd; max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.account) + "'>" + accountCell + "</td>" +
					"<td style='padding:6px; border:1px solid #ddd; max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.amount) + "'>" + amountCell + "</td>" +
					"<td style='padding:6px; border:1px solid #ddd; max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.invoiceNumber) + "'>" + invoiceNumberCell + "</td>" +
					"<td style='padding:6px; border:1px solid #ddd; max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.description) + "'>" + descriptionCell + "</td>" +
					"<td style='padding:6px; border:1px solid #ddd; max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.date) + "'>" + dateCell + "</td>" +
					"<td style='padding:4px; border:1px solid #ddd; text-align: center; width: 140px;' onclick='event.stopPropagation();'>" + actionsCell + "</td>" +
					"</tr>";
			
			// Categorize rows by match type (priority order)
			if (isMatch) {
				activeRows += rowHTML;
			} else if (isPerfectMatch) {
				perfectMatchRows += rowHTML;
			} else if (isAmountMatch) {
				matchingAmountRows += rowHTML;
			} else if (isDateMatch) {
				dateMatchRows += rowHTML;
			} else if (isCombinationMatch) {
				combinationRows += rowHTML;
			} else {
				otherRows += rowHTML;
			}
		}

		// Add section header for perfect matches (amount + date)
		let perfectMatchHeader = '';
		if (hasPerfectMatches && perfectMatchRows) {
			const perfectFilesJson = JSON.stringify(perfectMatches).replace(/'/g, "&#39;");
			perfectMatchHeader = "<tr style='background-color: #007bff; color: white; cursor: pointer;' onclick='selectCombinationFiles(" + perfectFilesJson + ")' title='Klik for at vælge alle bilag med perfekt match'>" +
				"<td colspan='8' style='padding: 8px 12px; font-weight: bold; font-size: 12px; border: 1px solid #007bff;'>" +
				"<span style='margin-right: 6px;'>" + svgIcons.star + "</span>" +
				"Perfekt match (beløb: " + escapeHTML(totalSum) + " + dato: " + escapeHTML(targetDate) + ") - " + perfectMatches.length + " fundet" +
				" <span style='font-weight: normal; font-size: 11px; float: right;'>" + svgIcons.pointer + " Klik for at vælge</span>" +
				"</td></tr>";
		}
		
		// Add section header for exact matching amounts (without date match)
		let matchingHeader = '';
		if (hasAmountToMatch && exactMatches.length > 0 && matchingAmountRows) {
			const exactFilesJson = JSON.stringify(exactMatches).replace(/'/g, "&#39;");
			matchingHeader = "<tr style='background-color: #28a745; color: white; cursor: pointer;' onclick='selectCombinationFiles(" + exactFilesJson + ")' title='Klik for at vælge alle bilag med beløb match'>" +
				"<td colspan='8' style='padding: 8px 12px; font-weight: bold; font-size: 12px; border: 1px solid #28a745;'>" +
				"<span style='margin-right: 6px;'>" + svgIcons.check + "</span>" +
				"Beløb match (beløb: " + escapeHTML(totalSum) + ") - " + exactMatches.length + " fundet" +
				" <span style='font-weight: normal; font-size: 11px; float: right;'>" + svgIcons.pointer + " Klik for at vælge</span>" +
				"</td></tr>";
		}
		
		// Add section header for date-only matches
		let dateMatchHeader = '';
		if (hasDateOnlyMatches && dateMatchRows) {
			const dateFilesJson = JSON.stringify(dateOnlyMatches).replace(/'/g, "&#39;");
			dateMatchHeader = "<tr style='background-color: #17a2b8; color: white; cursor: pointer;' onclick='selectCombinationFiles(" + dateFilesJson + ")' title='Klik for at vælge alle bilag med dato match'>" +
				"<td colspan='8' style='padding: 8px 12px; font-weight: bold; font-size: 12px; border: 1px solid #17a2b8;'>" +
				"<span style='margin-right: 6px;'>" + svgIcons.calendar + "</span>" +
				"Dato match (" + escapeHTML(targetDate) + ") - " + dateOnlyMatches.length + " fundet" +
				" <span style='font-weight: normal; font-size: 11px; float: right;'>" + svgIcons.pointer + " Klik for at vælge</span>" +
				"</td></tr>";
		}
		
		// Add section header for combination matches
		let combinationHeader = '';
		if (hasCombinationMatches && combinationRows) {
			// Build description of the combinations found
			let comboDesc = '';
			let comboFilesJson = '[]';
			if (combinationGroups.length > 0) {
				const firstCombo = combinationGroups[0];
				const amountStrs = firstCombo.amounts.map(a => a.toLocaleString('da-DK', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
				comboDesc = amountStrs.join(' + ') + ' = ' + escapeHTML(totalSum);
			comboFilesJson = JSON.stringify(firstCombo.files).replace(/'/g, "&#39;");
		}
		
		combinationHeader = "<tr style='background-color: #ffc107; color: #212529; cursor: pointer;' onclick='selectCombinationFiles(" + comboFilesJson + ")' title='Klik for at vælge alle bilag i denne kombination'>" +
				"<td colspan='6' style='padding: 8px 12px; font-weight: bold; font-size: 12px; border: 1px solid #ffc107;'>" +
				"<span style='margin-right: 6px;'>" + svgIcons.plus + "</span>" +
				"Kombination fundet (" + combinationMatches.size + " bilag giver: " + escapeHTML(totalSum) + ")" +
				(comboDesc ? " <span style='font-weight: normal; font-size: 11px;'>(" + comboDesc + ")</span>" : "") +
				" <span style='font-weight: normal; font-size: 11px; float: right;'>" + svgIcons.pointer + " Klik for at vælge</span>" +
				"</td></tr>";
		}

		// Ensure rows are ordered by priority: active, perfect match, amount match, date match, combination, others
		html += activeRows + perfectMatchHeader + perfectMatchRows + matchingHeader + matchingAmountRows + dateMatchHeader + dateMatchRows + combinationHeader + combinationRows + otherRows;

			html += "</tbody></table>";
			
			// Add bulk action button container at the bottom of the list (sticky so it's always visible)
			html += "<div id='bulkActionsContainer' style='margin-top: 12px; padding: 8px; background-color: " + lightButtonColor + "; border-radius: 6px; display: none; position: sticky; bottom: 0; z-index: 5;'>";
			html += "<button type='button' id='bulkInsertButton' onclick='chooseMultipleBilag()' style='padding: 8px 16px; background-color: " + buttonColor + "; color: " + buttonTxtColor + "; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.opacity=\"0.9\"; this.style.transform=\"scale(1.02)\"' onmouseout='this.style.opacity=\"1\"; this.style.transform=\"scale(1)\"'>";
			html += "Indsæt valgte (<span id='selectedCount'>0</span>)";
			html += "</button>";
			html += "</div>";

			// Dynamic styles for selected/editing rows (using CSS variables from docpool.css)
			html += "<style>\
				table tbody tr:hover { background-color: " + lightButtonColor + " !important; }\
				table tbody tr[data-selected='true'] { background-color: " + lightButtonColor + " !important; color: #000000 !important; }\
				table tbody tr[data-selected='true'] td { color: #000000 !important; }\
				table tbody tr[data-selected='true']:hover { background-color: " + lightButtonColor + " !important; }\
				table tbody tr[data-selected='true']:hover td { color: #000000 !important; }\
				table tbody tr[data-editing='true'] { background-color: " + lightButtonColor + " !important; }\
				table tbody tr[data-perfect-match='true'] { background-color: #cce5ff !important; border-left: 4px solid #004085 !important; }\
				table tbody tr[data-perfect-match='true']:hover { background-color: #b8daff !important; }\
				table tbody tr[data-perfect-match='true'] td { color: #004085 !important; }\
				table tbody tr[data-amount-match='true'] { background-color: #d4edda !important; }\
				table tbody tr[data-amount-match='true']:hover { background-color: #c3e6cb !important; }\
				table tbody tr[data-amount-match='true'] td { color: #155724 !important; }\
				table tbody tr[data-date-match='true'] { background-color: #e7f3ff !important; }\
				table tbody tr[data-date-match='true']:hover { background-color: #d1e7ff !important; }\
				table tbody tr[data-date-match='true'] td { color: #0c5460 !important; }\
				table tbody tr[data-combination-match='true'] { background-color: #fff3cd !important; }\
				table tbody tr[data-combination-match='true']:hover { background-color: #ffe69c !important; }\
				table tbody tr[data-combination-match='true'] td { color: #856404 !important; }\
				table tbody tr:hover td { background-color:  }\
				.edit-input { border-color: " + buttonColor + "; }\
				.edit-input:focus { outline-color: " + buttonColor + "; }\
			</style>";

			document.getElementById(containerId).innerHTML = html;
			
			// Restore checkbox states from sessionStorage
			const checkboxes = document.querySelectorAll('.file-checkbox');
			checkboxes.forEach(cb => {
				const savedChecked = sessionStorage.getItem('docPool_checked_' + cb.value) === 'true';
				if (savedChecked) {
					cb.checked = true;
				}
			});
			
			// Update select all checkbox state
			const selectAllCheckbox = document.getElementById('selectAllCheckbox');
			if (selectAllCheckbox && checkboxes.length > 0) {
				const allChecked = Array.from(checkboxes).every(cb => cb.checked);
				selectAllCheckbox.checked = allChecked;
			}
			
			// Update bulk button state after rendering
			if (typeof updateBulkButton === 'function') {
				updateBulkButton();
			}
			
			// Update padding after rendering
			if (typeof updateFixedDiv === 'function') {
				setTimeout(updateFixedDiv, 100);
			}
		}

		// Card layout render function (similar to linkBilag style)
		function renderFilesCard() {
			if (!docData.length) {
				document.getElementById(containerId).innerHTML = '<em>No files found.</em>';
				return;
			}
			
			// Get current poolFile from URL for highlighting
			const currentUrlParams = new URLSearchParams(window.location.search);
			const allCurrentPoolFiles = currentUrlParams.getAll('poolFile');
			let currentPoolFile = null;
			for (let i = allCurrentPoolFiles.length - 1; i >= 0; i--) {
				if (allCurrentPoolFiles[i] && allCurrentPoolFiles[i].trim() !== '') {
					currentPoolFile = allCurrentPoolFiles[i];
					break;
				}
			}
			
			// Amount and date matching logic (reuse from renderFiles)
			const normalizedTotal = parseFloat(totalSum?.replace(/\\./g, '').replace(',', '.') || 0);
			const hasAmountToMatch = normalizedTotal !== 0 && !isNaN(normalizedTotal);
			
			// Normalize target date for card view
			let cardNormalizedTargetDate = null;
			if (targetDate) {
				const dateParts = targetDate.split('-');
				if (dateParts.length === 3) {
					if (dateParts[0].length === 4) {
						cardNormalizedTargetDate = targetDate;
					} else {
						cardNormalizedTargetDate = dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
					}
				}
			}
			const cardHasDateToMatch = cardNormalizedTargetDate !== null;
			
			let exactMatches = [];
			let perfectMatches = [];
			let dateOnlyMatches = [];
			let combinationMatches = new Set();
			let combinationGroups = [];
			
			if (hasAmountToMatch || cardHasDateToMatch) {
				const docsWithAmounts = [];
				for (let i = 0; i < docData.length; i++) {
					const row = docData[i];
					const normalizedAmount = parseFloat(row.amount);
					const filename = row.filename || '';
					
					// Normalize row date
					let rowDate = null;
					if (row.date) {
						const dateStr = row.date.split(' ')[0];
						const parts = dateStr.split('-');
						if (parts.length === 3) {
							rowDate = parts[0].length === 4 ? dateStr : parts[2] + '-' + parts[1] + '-' + parts[0];
						}
					}
					
					const isDateMatch = cardHasDateToMatch && rowDate === cardNormalizedTargetDate;
					const isAmountMatch = hasAmountToMatch && !isNaN(normalizedAmount) && Math.abs(normalizedAmount - normalizedTotal) < 0.01;
					
					if (!isNaN(normalizedAmount) && normalizedAmount > 0) {
						docsWithAmounts.push({
							index: i,
							filename: filename,
							amount: normalizedAmount,
							row: row
						});
					}
					
					if (isAmountMatch && isDateMatch) {
						perfectMatches.push(filename);
					} else if (isAmountMatch) {
						exactMatches.push(filename);
					} else if (isDateMatch) {
						dateOnlyMatches.push(filename);
					}
				}
				
				// Find combinations if no exact or perfect matches
				if (exactMatches.length === 0 && perfectMatches.length === 0 && docsWithAmounts.length >= 2) {
					for (let i = 0; i < docsWithAmounts.length; i++) {
						for (let j = i + 1; j < docsWithAmounts.length; j++) {
							const sum = docsWithAmounts[i].amount + docsWithAmounts[j].amount;
							if (Math.abs(sum - normalizedTotal) < 0.01) {
								combinationMatches.add(docsWithAmounts[i].filename);
								combinationMatches.add(docsWithAmounts[j].filename);
								combinationGroups.push({
									files: [docsWithAmounts[i].filename, docsWithAmounts[j].filename],
									sum: sum
								});
							}
						}
					}
				}
			}
			
			let html = '<div class="doc-card-list" style="display: flex; flex-direction: column; gap: 8px; padding: 0 4px 80px 4px;">';
			
			// Add select all and bulk area
			html += '<div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: ' + buttonColor + '; border-radius: 6px; margin-bottom: 4px;">';
			html += '<label style="display: flex; align-items: center; gap: 8px; color: ' + buttonTxtColor + '; font-size: 13px; cursor: pointer;">';
			html += '<input type="checkbox" id="selectAllCheckboxCard" onclick="toggleSelectAll(this)" style="width: 18px; height: 18px; cursor: pointer;">';
			html += '<span>Vælg alle</span>';
			html += '</label>';
			html += '<span style="color: ' + buttonTxtColor + '; font-size: 12px;">' + docData.length + ' filer</span>';
			html += '</div>';
			
			// Perfect match header (amount + date) if applicable
			if (perfectMatches.length > 0) {
				const perfectFilesJson = JSON.stringify(perfectMatches).replace(/'/g, "&#39;");
				html += '<div onclick="selectCombinationFiles(' + perfectFilesJson + ')" style="cursor: pointer; padding: 10px; background: #007bff; color: white; border-radius: 6px; margin-bottom: 8px;">';
				html += '<span style="margin-right: 6px;">' + svgIcons.star + '</span>';
				html += '<strong>Perfekt match</strong> - ' + perfectMatches.length + ' bilag matcher beløb ' + escapeHTML(totalSum) + ' og dato ' + escapeHTML(targetDate);
				html += ' <span style="float: right; font-size: 11px;">' + svgIcons.pointer + ' Klik for at vælge</span>';
				html += '</div>';
			}
			
			// Amount-only match header if applicable
			if (hasAmountToMatch && exactMatches.length > 0) {
				const exactFilesJson = JSON.stringify(exactMatches).replace(/'/g, "&#39;");
				html += '<div onclick="selectCombinationFiles(' + exactFilesJson + ')" style="cursor: pointer; padding: 10px; background: #28a745; color: white; border-radius: 6px; margin-bottom: 8px;">';
				html += '<span style="margin-right: 6px;">' + svgIcons.check + '</span>';
				html += '<strong>Beløb match</strong> - ' + exactMatches.length + ' bilag matcher beløbet ' + escapeHTML(totalSum);
				html += ' <span style="float: right; font-size: 11px;">' + svgIcons.pointer + ' Klik for at vælge</span>';
				html += '</div>';
			}
			
			// Date-only match header if applicable
			if (dateOnlyMatches.length > 0) {
				const dateFilesJson = JSON.stringify(dateOnlyMatches).replace(/'/g, "&#39;");
				html += '<div onclick="selectCombinationFiles(' + dateFilesJson + ')" style="cursor: pointer; padding: 10px; background: #17a2b8; color: white; border-radius: 6px; margin-bottom: 8px;">';
				html += '<span style="margin-right: 6px;">' + svgIcons.calendar + '</span>';
				html += '<strong>Dato match</strong> - ' + dateOnlyMatches.length + ' bilag matcher datoen ' + escapeHTML(targetDate);
				html += ' <span style="float: right; font-size: 11px;">' + svgIcons.pointer + ' Klik for at vælge</span>';
				html += '</div>';
			}
			
			// Combination match header if applicable
			if (combinationMatches.size > 0 && combinationGroups.length > 0) {
				const comboFilesJson = JSON.stringify(combinationGroups[0].files).replace(/'/g, "&#39;");
				html += '<div onclick="selectCombinationFiles(' + comboFilesJson + ')" style="cursor: pointer; padding: 10px; background: #ffc107; color: #212529; border-radius: 6px; margin-bottom: 8px;">';
				html += '<span style="margin-right: 6px;">' + svgIcons.plus + '</span>';
				html += '<strong>Kombination fundet</strong> - ' + combinationMatches.size + ' bilag giver tilsammen ' + escapeHTML(totalSum);
				html += ' <span style="float: right; font-size: 11px;">' + svgIcons.pointer + ' Klik for at vælge</span>';
				html += '</div>';
			}
			
			// Render each file as a card
			for (const row of docData) {
				const filename = row.filename || '';
				const subject = row.subject || filename;
				const account = row.account || '';
				const amount = row.amount || '';
				const dateFormatted = (row.date || '').split(' ')[0];
				
				// Apply search filter
				if (searchFilter) {
					const searchText = (filename + ' ' + subject + ' ' + account + ' ' + amount).toLowerCase();
					if (searchText.indexOf(searchFilter) === -1) {
						continue;
					}
				}
				
				// Build file path for preview
				const filePath = docFolder + '/' + db + '/pulje/' + filename;
				
				// Check matches
				const isSelected = currentPoolFile && filename === currentPoolFile;
				const isPerfectMatch = perfectMatches.includes(filename);
				const isAmountMatch = exactMatches.includes(filename);
				const isDateMatch = dateOnlyMatches.includes(filename);
				const isCombinationMatch = combinationMatches.has(filename);
				
				// Card styling based on state (priority order)
				let cardStyle = 'display: flex; align-items: flex-start; gap: 12px; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.2s;';
				if (isSelected) {
					cardStyle += ' background: ' + lightButtonColor + '; border-color: ' + buttonColor + ';';
				} else if (isPerfectMatch) {
					cardStyle += ' background: #cce5ff; border-color: #007bff; border-left: 4px solid #004085;';
				} else if (isAmountMatch) {
					cardStyle += ' background: #d4edda; border-color: #28a745;';
				} else if (isDateMatch) {
					cardStyle += ' background: #e7f3ff; border-color: #17a2b8;';
				} else if (isCombinationMatch) {
					cardStyle += ' background: #fff3cd; border-color: #ffc107;';
				}
				
				// Check if checkbox was previously checked
				const savedChecked = sessionStorage.getItem('docPool_checked_' + filename) === 'true';
				const checkedAttr = savedChecked ? ' checked' : '';
				
				// Delete URL
				const deleteUrl = row.href.replace(/poolFile=[^&]*/, '') + (row.href.includes('?') ? '&' : '?') + 'unlink=1&unlinkFile=' + encodeURIComponent(filename);
				
				html += '<div class="doc-card-item" data-pool-file="' + escapeHTML(filename) + '" data-filepath="' + escapeHTML(filePath) + '" data-filename="' + escapeHTML(filename) + '" style="' + cardStyle + '" onmouseenter="showPreview(this, event)" onmouseleave="hidePreview()" onmousemove="movePreview(event)" onclick="if(!event.target.closest(\\'button\\') && !event.target.closest(\\'input\\') && !event.target.closest(\\'.card-actions\\')) { toggleCardCheckbox(this); }">';
				
				// Checkbox
				html += '<div style="flex-shrink: 0;" onclick="event.stopPropagation();">';
				html += '<input type="checkbox" class="file-checkbox" value="' + escapeHTML(filename) + '"' + checkedAttr + ' onchange="saveCheckboxState(); updateBulkButton();" onclick="event.stopPropagation();" style="width: 20px; height: 20px; cursor: pointer;">';
				html += '</div>';
				
				// Icon
				html += '<div style="flex-shrink: 0; color: ' + buttonColor + ';">';
				html += '<svg class="icon-svg" style="width: 32px; height: 32px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>';
				html += '</div>';
				
				// Content
				html += '<div style="flex: 1; min-width: 0;">';
				html += '<div style="font-weight: bold; font-size: 14px; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="' + escapeHTML(subject) + '">' + escapeHTML(subject) + '</div>';
				html += '<div style="font-size: 12px; color: #666; display: flex; flex-wrap: wrap; gap: 8px;">';
				if (account) html += '<span><strong>Konto:</strong> ' + escapeHTML(account) + '</span>';
				if (amount) {
					let amountHtml = '<span><strong>Beløb:</strong> ';
					if (isPerfectMatch) {
						amountHtml += '<span style="color: #007bff;">' + svgIcons.star + ' ' + escapeHTML(amount) + '</span>';
					} else if (isAmountMatch) {
						amountHtml += '<span style="color: #28a745;">' + svgIcons.check + ' ' + escapeHTML(amount) + '</span>';
					} else if (isCombinationMatch) {
						amountHtml += '<span style="color: #ffc107;">' + svgIcons.plus + ' ' + escapeHTML(amount) + '</span>';
					} else {
						amountHtml += escapeHTML(amount);
					}
					amountHtml += '</span>';
					html += amountHtml;
				}
				if (dateFormatted) {
					let dateHtml = '<span><strong>Dato:</strong> ';
					if (isPerfectMatch || isDateMatch) {
						dateHtml += '<span style="color: #007bff;">' + svgIcons.calendar + ' ' + escapeHTML(dateFormatted) + '</span>';
					} else {
						dateHtml += escapeHTML(dateFormatted);
					}
					dateHtml += '</span>';
					html += dateHtml;
				}
				html += '</div>';
				html += '</div>';
				
				// Actions
				html += '<div class="card-actions" style="flex-shrink: 0; display: flex; gap: 4px;" onclick="event.stopPropagation();">';
				html += '<button type="button" onclick="event.preventDefault(); event.stopPropagation(); enableCardEdit(\\'' + escapeHTML(filename) + '\\', \\'' + escapeHTML(subject) + '\\', \\'' + escapeHTML(account) + '\\', \\'' + escapeHTML(amount) + '\\', \\'' + escapeHTML(dateFormatted) + '\\'); return false;" style="padding: 6px 10px; background: ' + buttonColor + '; color: ' + buttonTxtColor + '; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;" title="Rediger">' + svgIcons.pencil + '</button>';
				html += '<button type="button" onclick="event.preventDefault(); event.stopPropagation(); deletePoolFile(\\'' + escapeHTML(filename) + '\\', ' + JSON.stringify(subject) + ', \\'' + escapeHTML(deleteUrl) + '\\'); return false;" style="padding: 6px 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;" title="Slet">' + svgIcons.trash + '</button>';
				html += '<button type="button" onclick="event.preventDefault(); event.stopPropagation(); extractPoolFile(\\'' + escapeHTML(filename) + '\\'); return false;" style="padding: 6px 10px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;" title="Udtræk fakturadata">' + svgIcons.scan + '</button>';
				html += '</div>';
				
				html += '</div>';
			}
			
			html += '</div>';
			
			// Bulk actions
			html += '<div id="bulkActionsContainer" style="margin-top: 12px; padding: 8px; background-color: ' + lightButtonColor + '; border-radius: 6px; display: none; position: sticky; bottom: 0; z-index: 5;">';
			html += '<button type="button" id="bulkInsertButton" onclick="chooseMultipleBilag()" style="padding: 8px 16px; background-color: ' + buttonColor + '; color: ' + buttonTxtColor + '; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold;">';
			html += 'Indsæt valgte (<span id="selectedCount">0</span>)';
			html += '</button>';
			html += '</div>';
			
			// Dynamic styles for cards
			html += '<style>';
			html += '.doc-card-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.15); transform: translateY(-1px); }';
			html += '</style>';
			
			document.getElementById(containerId).innerHTML = html;
			
			// Restore checkboxes and update UI
			const checkboxes = document.querySelectorAll('.file-checkbox');
			checkboxes.forEach(cb => {
				const saved = sessionStorage.getItem('docPool_checked_' + cb.value) === 'true';
				if (saved) cb.checked = true;
			});
			
			const selectAllCheckbox = document.getElementById('selectAllCheckboxCard');
			if (selectAllCheckbox && checkboxes.length > 0) {
				const allChecked = Array.from(checkboxes).every(cb => cb.checked);
				selectAllCheckbox.checked = allChecked;
			}
			
			if (typeof updateBulkButton === 'function') updateBulkButton();
			if (typeof updateFixedDiv === 'function') setTimeout(updateFixedDiv, 100);
		}
		
		
		// Toggle checkbox when clicking a card in card view (kortvisning)
		window.toggleCardCheckbox = function(cardElement) {
			const checkbox = cardElement.querySelector('.file-checkbox');
			if (checkbox) {
				checkbox.checked = !checkbox.checked;
				saveCheckboxState();
				updateBulkButton();
				
				// Update card styling based on checkbox state
				const filename = cardElement.dataset.poolFile;
				if (checkbox.checked) {
					cardElement.style.background = lightButtonColor;
					cardElement.style.borderColor = buttonColor;
				} else {
					// Reset to default or match state
					cardElement.style.background = '#fff';
					cardElement.style.borderColor = '#ddd';
				}
			}
		};
		
		// Enable editing for a card item - opens a modal/inline form
		window.enableCardEdit = function(poolFile, subject, account, amount, date) {
			// For simplicity, reuse the table row edit via renderFiles mode temporarily
			// Switch to table mode, enable edit, then user can save and switch back
			// Or we can show a simple prompt/modal
			
			const newSubject = prompt('Emne:', subject);
			if (newSubject === null) return; // Cancelled
			
			const newAccount = prompt('Konto:', account);
			if (newAccount === null) return;
			
			const newAmount = prompt('Beløb:', amount);
			if (newAmount === null) return;
			
			const newDate = prompt('Dato (ÅÅÅÅ-MM-DD):', date);
			if (newDate === null) return;
			
			const newDescription = prompt('Beskrivelse:', description);
			if (newDescription === null) return;
			
			const newInvoiceNumber = prompt('Fakturanummer:', invoiceNumber);
			if (newInvoiceNumber === null) return;
			
			// Save via AJAX (reuse existing save logic)
			const form = document.forms['gennemse'];
			if (!form) return;
			
			const formAction = form.getAttribute('action');
			const url = new URL(formAction, window.location.href);
			url.searchParams.set('poolFile', poolFile);
			
			const formData = new FormData();
			formData.append('rename', 'Ret filnavn');
			formData.append('poolFile', poolFile);
			formData.append('newFileName', poolFile);
			formData.append('newSubject', newSubject);
			formData.append('newAccount', newAccount);
			formData.append('newAmount', newAmount);
			formData.append('newDescription', newDescription);
			formData.append('newInvoiceNumber', newInvoiceNumber);
			formData.append('newDate', newDate);
			
			url.searchParams.forEach((value, key) => {
				formData.append(key, value);
			});
			
			fetch(url.toString(), {
				method: 'POST',
				body: formData,
				redirect: 'follow'
			})
			.then(response => {
				if (response.ok) {
					// Refresh the file list
					fetchFiles();
				} else {
					alert('Fejl ved gemning. Prøv igen.');
				}
			})
			.catch(error => {
				console.error('Error saving:', error);
				alert('Fejl ved gemning: ' + error.message);
			});
		};


	
		function sortFiles(field) {
			const asc = currentSort.field === field ? !currentSort.asc : true;

			docData.sort((a, b) => {
				let valA = a[field];
				let valB = b[field];

				if (field === 'amount') {
						valA = parseFloat(valA) || 0;
						valB = parseFloat(valB) || 0;
				} else if (field === 'date') {
						valA = new Date(valA).getTime() || 0;
						valB = new Date(valB).getTime() || 0;
				} else {
						if (typeof valA === 'string') valA = valA.toLowerCase();
						if (typeof valB === 'string') valB = valB.toLowerCase();
				}

				if (valA === valB) return 0;
				return asc ? (valA > valB ? 1 : -1) : (valA < valB ? 1 : -1);
			});

			currentSort = { field, asc };
			renderCurrentView();
		}


    function escapeHTML(str) {
        if (typeof str !== 'string') return str;
        return str.replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[m]);
    }
	
	// Make escapeHTML globally available
	window.escapeHTML = escapeHTML;
	
	// Function to insert/choose a bilag - matches old "Indsæt" button behavior exactly
	window.chooseBilag = function(poolFile) {
		chooseMultipleBilag([poolFile]);
	};
	
	// Function to insert/choose multiple bilag
	window.chooseMultipleBilag = function(selectedFiles) {
		const form = document.forms['gennemse'];
		if (!form) {
			alert('Form not found');
			return;
		}
		
		// If no files provided, get selected checkboxes
		if (!selectedFiles) {
			const checkboxes = document.querySelectorAll('.file-checkbox:checked');
			selectedFiles = Array.from(checkboxes).map(cb => cb.value);
		}
		
		if (!selectedFiles || selectedFiles.length === 0) {
			alert('Vælg mindst ét bilag');
			return;
		}
		
		// Debug: log selected files
		console.log('Selected files to insert:', selectedFiles);
		
		// Get form action URL
		const formAction = form.getAttribute('action');
		const url = new URL(formAction, window.location.href);
		
		// Remove ALL existing poolFile parameters (with and without brackets)
		url.searchParams.delete('poolFile');
		url.searchParams.delete('poolFile[]');
		url.searchParams.delete('poolFiles');
		
		// Create FormData with all required fields
		const formData = new FormData();
		formData.append('insertFile', '1');
		
		// Add selected files - ONLY use poolFiles (comma-separated) as it's most reliable
		formData.append('poolFiles', selectedFiles.join(','));
		
		// Also add as array for compatibility
		selectedFiles.forEach(file => {
			formData.append('poolFile[]', file);
		});
		
		// Add other URL parameters to form data (but NOT poolFile params - we already set those)
		url.searchParams.forEach((value, key) => {
			// Skip poolFile parameters - we've already set the correct ones above
			if (key === 'poolFile' || key === 'poolFile[]' || key === 'poolFiles') {
				return;
			}
			formData.append(key, value);
		});
		
		// If sourceId is empty (0 or not set), transfer date and amount from the first selected file
		const sourceId = url.searchParams.get('sourceId') || '';
		console.log('sourceId from URL:', sourceId, 'Is empty:', !sourceId || sourceId === '0' || sourceId === '');
		
		if (!sourceId || sourceId === '0' || sourceId === '') {
			// Look up data from the first selected file
			const firstFile = selectedFiles[0];
			console.log('Looking for file in docData:', firstFile);
			console.log('docData has', docData.length, 'entries');
			
			// Try multiple ways to find the file data
			let fileData = docData.find(d => d.filename === firstFile);
			
			// If not found, try with URL decoding
			if (!fileData) {
				try {
					const decodedFirstFile = decodeURIComponent(firstFile);
					fileData = docData.find(d => d.filename === decodedFirstFile);
					if (fileData) console.log('Found via URL decoding');
				} catch (e) {
					console.log('URL decode failed:', e);
				}
			}
			
			// If still not found, try case-insensitive match
			if (!fileData) {
				const firstFileLower = firstFile.toLowerCase();
				fileData = docData.find(d => d.filename && d.filename.toLowerCase() === firstFileLower);
				if (fileData) console.log('Found via case-insensitive match');
			}
			
			console.log('File data found:', fileData);
			
			if (fileData) {
				// Transfer date if available
				if (fileData.date) {
					formData.append('newDate', fileData.date);
					console.log('Transferring date from pool file:', fileData.date);
				} else {
					console.log('No date in fileData');
				}
				// Transfer amount if available
				if (fileData.amount) {
					formData.append('newAmount', fileData.amount);
					console.log('Transferring amount from pool file:', fileData.amount);
				} else {
					console.log('No amount in fileData');
				}
				// Transfer invoice number if available
				if (fileData.invoiceNumber) {
					formData.append('newInvoiceNumber', fileData.invoiceNumber);
					console.log('Transferring invoice number from pool file:', fileData.invoiceNumber);
				} else {
					console.log('No invoiceNumber in fileData');
				}
				// Transfer invoice description if available
				if (fileData.description) {
					formData.append('newInvoiceDescription', fileData.description);
					console.log('Transferring invoice description from pool file:', fileData.description);
				} else {
					console.log('No description in fileData');
				}
			} else {
				console.log('Could not find file in docData. Available filenames:', docData.map(d => d.filename));
			}
		}
		
		// Debug: log what we're sending
		console.log('FormData poolFiles:', formData.get('poolFiles'));
		console.log('FormData poolFile[]:', formData.getAll('poolFile[]'));
		
		// Show loading indicator
		const loadingMsg = selectedFiles.length > 1 ? 'Indsætter ' + selectedFiles.length + ' filer...' : 'Indsætter fil...';
		console.log(loadingMsg);
		
		// Send AJAX request (same approach as saveRowData)
		fetch(url.toString(), {
			method: 'POST',
			body: formData,
			redirect: 'follow'
		})
		.then(response => {
			console.log('Insert response status:', response.status, response.ok, response.redirected);
			
			// Check if response contains redirect or is successful
			if (response.ok || (response.status >= 200 && response.status < 300)) {
				// Get the response text to check for redirect URL
				return response.text().then(text => {
					// Check if response contains a redirect script
					const redirectMatch = text.match(/window\.location\.(replace|href)\s*=\s*['"]([^'"]+)['"]/);
					if (redirectMatch) {
						// Clear sessionStorage for inserted files
						selectedFiles.forEach(file => {
							sessionStorage.removeItem('docPool_checked_' + file);
						});
						// Extract redirect URL from response
						const redirectUrl = redirectMatch[2];
						window.location.replace(redirectUrl);
					} else {
						// Clear sessionStorage for inserted files
						selectedFiles.forEach(file => {
							sessionStorage.removeItem('docPool_checked_' + file);
						});
						// Fallback: construct redirect URL from current context
						const kladdeId = url.searchParams.get('kladde_id') || '';
						const fokus = url.searchParams.get('fokus') || '';
						const source = url.searchParams.get('source') || '';
						
						if (source === 'kassekladde' && kladdeId) {
							const redirectUrl = '../finans/kassekladde.php?kladde_id=' + kladdeId + '&fokus=' + fokus;
							window.location.replace(redirectUrl);
						} else {
							// Reload current page without poolFile params
							url.searchParams.delete('poolFile[]');
							url.searchParams.delete('poolFiles');
							url.searchParams.delete('insertFile');
							window.location.replace(url.toString());
						}
					}
				});
			} else {
				// Error handling
				response.text().then(text => {
					console.error('Insert failed. Response:', response.status, text);
					alert('Fejl ved indsætning (Status: ' + response.status + '). Prøv igen.');
				}).catch(() => {
					alert('Fejl ved indsætning. Prøv igen.');
				});
			}
		})
		.catch(error => {
			console.error('Insert error:', error);
			alert('Fejl ved indsætning: ' + error.message);
		});
	};
	
	// Save checkbox state to sessionStorage
	window.saveCheckboxState = function() {
		const checkboxes = document.querySelectorAll('.file-checkbox');
		checkboxes.forEach(cb => {
			if (cb.checked) {
				sessionStorage.setItem('docPool_checked_' + cb.value, 'true');
			} else {
				sessionStorage.removeItem('docPool_checked_' + cb.value);
			}
		});
	};
	
	// Toggle select all checkboxes
	window.toggleSelectAll = function(checkbox) {
		const checkboxes = document.querySelectorAll('.file-checkbox');
		checkboxes.forEach(cb => {
			cb.checked = checkbox.checked;
		});
		saveCheckboxState();
		updateBulkButton();
	};
	
	// Select all files in a combination match
	window.selectCombinationFiles = function(files) {
		if (!files || !Array.isArray(files)) return;
		
		// First, uncheck all checkboxes
		const allCheckboxes = document.querySelectorAll('.file-checkbox');
		allCheckboxes.forEach(cb => {
			cb.checked = false;
		});
		
		// Then check only the combination files
		files.forEach(filename => {
			const checkbox = document.querySelector('.file-checkbox[value="' + CSS.escape(filename) + '"]');
			if (checkbox) {
				checkbox.checked = true;
			}
		});
		
		saveCheckboxState();
		updateBulkButton();
		
		// Show a brief confirmation
		const count = files.length;
		console.log('Selected ' + count + ' combination files: ' + files.join(', '));
	};
	
	// Update bulk action button visibility and count
	window.updateBulkButton = function() {
		const checkboxes = document.querySelectorAll('.file-checkbox:checked');
		const count = checkboxes.length;
		const bulkContainer = document.getElementById('bulkActionsContainer');
		const selectedCount = document.getElementById('selectedCount');
		const selectAllCheckbox = document.getElementById('selectAllCheckbox');
		
		if (bulkContainer) {
			if (count > 0) {
				bulkContainer.style.display = 'block';
			} else {
				bulkContainer.style.display = 'none';
			}
		}
		
		if (selectedCount) {
			selectedCount.textContent = count;
		}
		
		// Update select all checkbox state
		if (selectAllCheckbox) {
			const allCheckboxes = document.querySelectorAll('.file-checkbox');
			selectAllCheckbox.checked = allCheckboxes.length > 0 && allCheckboxes.length === count;
			selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
		}
	};
	
// Enable editing for a specific row
window.enableRowEdit = function(button, poolFile, subject, account, amount, date, invoiceNumber, description) {
	// Disable any other row that might be in edit mode
	const allRows = document.querySelectorAll('tr[data-editing="true"]');
	allRows.forEach(row => {
		const cells = row.querySelectorAll('td');
		if (cells.length >= 6) {
			// Restore original values (skip checkbox column which is cells[0])
			const originalData = row.dataset.originalValues ? JSON.parse(row.dataset.originalValues) : {};
			cells[1].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.subject || '') + "</span>";
			cells[2].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.account || '') + "</span>";
			cells[3].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.amount || '') + "</span>";
			cells[4].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.invoiceNumber || '') + "</span>";
			cells[5].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.description || '') + "</span>";
			cells[6].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.date || '') + "</span>";
			// Restore original actions
			if (row.dataset.originalActions) {
				cells[7].innerHTML = row.dataset.originalActions;
			}
			row.removeAttribute('data-editing');
			delete row.dataset.originalValues;
			delete row.dataset.originalActions;
		}
	});

	// Get the row for this button
	const row = button.closest('tr');
	if (!row) return;

	// Store original values and actions
	const cells = row.querySelectorAll('td');
	const originalActions = cells.length >= 8 ? cells[7].innerHTML : '';
	 // Store original values
	 console.log(subject, account, amount, date, invoiceNumber, description);
    row.dataset.originalValues = JSON.stringify({ 
        subject, 
        account, 
        amount, 
        date,
        invoiceNumber,
        description
    });

	row.dataset.originalActions = originalActions;
	row.setAttribute('data-editing', 'true');
	row.setAttribute('data-pool-file', poolFile);

	// Make cells editable (update to handle all 8 columns)
    if (cells.length >= 8) {
        const dateFormatted = date.split(' ')[0] || date;
        const stopPropagation = "event.stopPropagation();";
        const inputEvents = "onclick='" + stopPropagation + "' onmousedown='" + stopPropagation + "' onmouseup='" + stopPropagation + "' onmousemove='" + stopPropagation + "'";
        
        cells[1].innerHTML = "<input type='text' class='edit-input' value='" + escapeHTML(subject) + "' data-field='subject' onkeydown='handleEnterKey(event, this)' " + inputEvents + ">";
        cells[2].innerHTML = "<input type='text' class='edit-input' value='" + escapeHTML(account) + "' data-field='account' onkeydown='handleEnterKey(event, this)' " + inputEvents + ">";
        cells[3].innerHTML = "<input type='text' class='edit-input' value='" + escapeHTML(amount) + "' data-field='amount' onkeydown='handleEnterKey(event, this)' " + inputEvents + ">";
        cells[4].innerHTML = "<input type='text' class='edit-input' value='" + escapeHTML(invoiceNumber || '') + "' data-field='invoiceNumber' onkeydown='handleEnterKey(event, this)' " + inputEvents + ">";
        cells[5].innerHTML = "<input type='text' class='edit-input' value='" + escapeHTML(description || '') + "' data-field='description' onkeydown='handleEnterKey(event, this)' " + inputEvents + ">";
        cells[6].innerHTML = "<input type='date' class='edit-input' value='" + dateFormatted + "' data-field='date' onkeydown='handleEnterKey(event, this)' onchange='saveRowData(this)' " + inputEvents + ">";
        
        // Update actions column
        cells[7].innerHTML = "<div style='display: flex; gap: 4px; justify-content: center; align-items: center; flex-wrap: wrap;'>" +
            "<button type='button' onclick='event.preventDefault(); event.stopPropagation(); saveRowData(this); return false;' style='padding: 4px 8px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold;' title='Gem'>" + svgIcons.save + "</button>" +
            "<button type='button' onclick='event.preventDefault(); event.stopPropagation(); cancelRowEdit(this); return false;' style='padding: 4px 8px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold;' title='Annuller'>" + svgIcons.x + "</button>" +
            "</div>";
        
        // Focus on first input
        setTimeout(() => cells[1].querySelector('input').focus(), 10);
    }
};

// Cancel editing and restore original values
window.cancelRowEdit = function(button) {
const row = button.closest('tr[data-editing="true"]');
    if (!row) return;
    
    const cells = row.querySelectorAll('td');
    if (cells.length >= 8) {
        const originalData = row.dataset.originalValues ? JSON.parse(row.dataset.originalValues) : {};
        cells[1].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.subject || '') + "</span>";
        cells[2].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.account || '') + "</span>";
        cells[3].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.amount || '') + "</span>";
        cells[4].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.invoiceNumber || '') + "</span>";
        cells[5].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.description || '') + "</span>";
        cells[6].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.date || '') + "</span>";
        
        if (row.dataset.originalActions) {
            cells[7].innerHTML = row.dataset.originalActions;
        }
        row.removeAttribute('data-editing');
        delete row.dataset.originalValues;
        delete row.dataset.originalActions;
    }
    
    if (typeof updateBulkButton === 'function') {
        updateBulkButton();
    }
};

// Delete pool file with confirmation
window.deletePoolFile = function(poolFile, subject, deleteUrl) {
	const confirmMsg = "Er du sikker på at du vil slette \"" + subject + "\"?";
	if (confirm(confirmMsg)) {
		window.location.href = deleteUrl;
	}
};

// Extract invoice data from pool file via API
window.extractPoolFile = function(poolFile) {
	// Show loading state
	const btn = event.target.closest('button');
	const originalContent = btn.innerHTML;
	btn.innerHTML = '<svg class="icon-svg icon-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>';
	btn.disabled = true;
	
	// Make AJAX call to extract invoice data
	const formData = new FormData();
	formData.append('action', 'extract');
	formData.append('poolFile', poolFile);
	formData.append('db', db);
	formData.append('docFolder', docFolder);
	
	fetch('docsIncludes/extractInvoiceHandler.php', {
		method: 'POST',
		body: formData
	})
	.then(response => response.json())
	.then(result => {
		btn.innerHTML = originalContent;
		btn.disabled = false;

		if (result.success) {
			// Update the row or card with extracted data
			const extracted = result.data;
			let message = 'Udtrækket data: \\n';
			if (extracted.amount) message += 'Beløb: ' + extracted.amount + "\\n";
			if (extracted.date) message += 'Dato: ' + extracted.date + "\\n";
			if (extracted.invoiceNumber) message += 'Fakturanummer: ' + extracted.invoiceNumber + "\\n";
			if (extracted.description) message += 'Beskrivelse: ' + extracted.description + "\\n";
			if (extracted.vendor) message += 'Leverandør: ' + extracted.vendor + "\\n";
			
			if (confirm(message + 'Vil du opdatere filen med disse data?')) {
				// Save the extracted data to the .info file
				const saveData = new FormData();
				saveData.append('action', 'save');
				saveData.append('poolFile', poolFile);
				saveData.append('db', db);
				saveData.append('docFolder', docFolder);
				if (extracted.amount) saveData.append('newAmount', extracted.amount);
				if (extracted.date) saveData.append('newDate', extracted.date);
				if (extracted.invoiceNumber) saveData.append('newInvoiceNumber', extracted.invoiceNumber);
				if (extracted.description) saveData.append('newDescription', extracted.description);
				if (extracted.vendor) saveData.append('newSubject', extracted.vendor);
				
				fetch('docsIncludes/extractInvoiceHandler.php', {
					method: 'POST',
					body: saveData
				})
				.then(response => response.json())
				.then(saveResult => {
					if (saveResult.success) {
						// Reload the page while preserving the current URL (keeps poolFile selection)
						window.location.href = window.location.href;
					} else {
						alert('Fejl ved gemning: ' + (saveResult.error || 'Ukendt fejl'));
					}
				})
				.catch(error => {
					alert('Fejl ved gemning: ' + error.message);
				});
			}
		} else {
			alert('Fejl ved udtræk: ' + (result.error || 'Ingen data kunne udtrækkes'));
		}
	})
	.catch(error => {
		btn.innerHTML = originalContent;
		btn.disabled = false;
		alert('Fejl ved udtræk: ' + error.message);
	});
};

// Extract invoice data from ALL pool files
window.extractAllPoolFiles = async function() {
	// Get all pool files from the docData array
	if (!docData || docData.length === 0) {
		alert('Ingen filer i puljen at opdatere');
		return;
	}
	
	const btn = document.getElementById('extractAllBtn');
	const originalContent = btn.innerHTML;
	let processed = 0;
	let successful = 0;
	let failed = 0;
	const total = docData.length;
	
	// Disable button and show progress
	btn.disabled = true;
	btn.style.opacity = '0.7';
	
	const updateProgress = () => {
		btn.innerHTML = '<svg class="icon-svg icon-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg> <span style="font-size: 12px;">' + processed + '/' + total + '</span>';
	};
	
	updateProgress();
	
	// Process each file sequentially
	for (const file of docData) {
		const poolFile = file.filename;
		
		try {
			// Extract data
			const extractFormData = new FormData();
			extractFormData.append('action', 'extract');
			extractFormData.append('poolFile', poolFile);
			extractFormData.append('db', db);
			
			const extractResponse = await fetch('docsIncludes/extractInvoiceHandler.php', {
				method: 'POST',
				body: extractFormData
			});
			
			const extractResult = await extractResponse.json();
			
			if (extractResult.success && extractResult.data) {
				const extracted = extractResult.data;
				
				// Only save if we got some data
				if (extracted.amount || extracted.date || extracted.vendor) {
					// Save the extracted data to the .info file
					const saveData = new FormData();
					saveData.append('action', 'save');
					saveData.append('poolFile', poolFile);
					saveData.append('db', db);
					if (extracted.amount) saveData.append('newAmount', extracted.amount);
					if (extracted.date) saveData.append('newDate', extracted.date);
					if (extracted.vendor) saveData.append('newSubject', extracted.vendor);
					if (extracted.invoiceNumber) saveData.append('newInvoiceNumber', extracted.invoiceNumber);
					if (extracted.description) saveData.append('newDescription', extracted.description);
					
					const saveResponse = await fetch('docsIncludes/extractInvoiceHandler.php', {
						method: 'POST',
						body: saveData
					});
					
					const saveResult = await saveResponse.json();
					if (saveResult.success) {
						successful++;
					} else {
						failed++;
						console.error('Failed to save data for ' + poolFile + ': ' + (saveResult.error || 'Unknown error'));
					}
				} else {
					// No data extracted
					failed++;
				}
			} else {
				failed++;
				console.error('Failed to extract data from ' + poolFile + ': ' + (extractResult.error || 'Unknown error'));
			}
		} catch (error) {
			failed++;
			console.error('Error processing ' + poolFile + ': ' + error.message);
		}
		
		processed++;
		updateProgress();
	}
	
	// Restore button
	btn.innerHTML = originalContent;
	btn.disabled = false;
	btn.style.opacity = '1';
	
	// Show summary
	let message = 'Opdatering afsluttet!';
	message += 'Behandlet: ' + total + ' filer';
	message += 'Succesfulde: ' + successful;
	if (failed > 0) {
		message += 'Fejlede: ' + failed;
	}
	
	alert(message);
	
	// Reload the page to show updated data
	if (successful > 0) {
		window.location.reload();
	}
};

// Delete selected files
window.deleteSelectedFiles = async function() {
	// Get all selected checkboxes
	const checkboxes = document.querySelectorAll('.file-checkbox:checked');
	if (checkboxes.length === 0) {
		alert('Ingen filer valgt. Vælg venligst de filer du vil slette.');
		return;
	}
	
	// Confirm deletion
	const confirmMsg = 'Er du sikker på at du vil slette ' + checkboxes.length + ' fil(er)?';
	if (!confirm(confirmMsg)) {
		return;
	}
	
	const btn = document.getElementById('deleteSelectedBtn');
	const originalContent = btn.innerHTML;
	let deleted = 0;
	let failed = 0;
	const total = checkboxes.length;
	const filesToDelete = Array.from(checkboxes).map(cb => cb.value);
	
	// Disable button and show progress
	btn.disabled = true;
	btn.style.opacity = '0.7';
	
	const updateProgress = () => {
		btn.innerHTML = '<svg class="icon-svg icon-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg> <span style="font-size: 12px;">' + deleted + '/' + total + '</span>';
	};
	
	updateProgress();
	
	// Process each file
	for (const poolFile of filesToDelete) {
		try {
			// Delete via form submission (using the existing unlink mechanism)
			const formData = new FormData();
			formData.append('action', 'delete');
			formData.append('poolFile', poolFile);
			formData.append('db', db);
			
			const response = await fetch('docsIncludes/extractInvoiceHandler.php', {
				method: 'POST',
				body: formData
			});
			
			const result = await response.json();
			
			if (result.success) {
				deleted++;
				// Remove from sessionStorage
				sessionStorage.removeItem('poolFileChecked_' + poolFile);
			} else {
				failed++;
				console.error('Failed to delete ' + poolFile + ': ' + (result.error || 'Unknown error'));
			}
		} catch (error) {
			failed++;
			console.error('Error deleting ' + poolFile + ': ' + error.message);
		}
		
		updateProgress();
	}
	
	// Restore button
	btn.innerHTML = originalContent;
	btn.disabled = false;
	btn.style.opacity = '1';
	
	// Show summary
	let message = 'Sletning afsluttet!';
	message += 'Slettet: ' + deleted + ' filer';
	if (failed > 0) {
		message += 'Fejlede: ' + failed;
	}
	
	alert(message);
	
	// Reload the page to show updated list
	if (deleted > 0) {
		window.location.reload();
	}
};

// Handle Enter key to save
window.handleEnterKey = function(event, input) {
	if (event.key === "Enter") {
		event.preventDefault();
		saveRowData(input);
	}
};

// Save row data via AJAX
window.saveRowData = function(input) {
    const row = input.closest('tr[data-editing="true"]');
    if (!row) return;

    const poolFile = row.getAttribute('data-pool-file');
    const inputs = row.querySelectorAll('.edit-input');
    
    const data = {
        rename: 'Ret filnavn',
        poolFile: poolFile,
        newSubject: '',
        newAccount: '',
        newAmount: '',
        newDate: '',
        newInvoiceNumber: '',
        newInvoiceDescription: ''
    };

    inputs.forEach(input => {
        const field = input.getAttribute('data-field');
        if (field === 'subject') data.newSubject = input.value;
        else if (field === 'account') data.newAccount = input.value;
        else if (field === 'amount') data.newAmount = input.value;
        else if (field === 'date') data.newDate = input.value;
        else if (field === 'invoiceNumber') data.newInvoiceNumber = input.value;
        else if (field === 'description') data.newInvoiceDescription = input.value;
    });

	// Get the form action URL
	const form = document.forms['gennemse'];
	if (!form) return;

	const formAction = form.getAttribute('action');
	
	// Construct the full URL - resolve relative path from current page
	// Use window.location.href as base to properly resolve relative paths
	const url = new URL(formAction, window.location.href);
	
	// Ensure poolFile is in the URL query string
	url.searchParams.set('poolFile', data.poolFile);
	
	// Create FormData with all required fields
	const formData = new FormData();
	formData.append('rename', data.rename);
	formData.append('poolFile', data.poolFile);
	formData.append('newFileName', data.poolFile); // Required by backend, use same filename since we're only updating .info
	formData.append('newSubject', data.newSubject);
	formData.append('newAccount', data.newAccount);
	formData.append('newAmount', data.newAmount);
	formData.append('newDate', data.newDate);
	formData.append('newInvoiceNumber', data.newInvoiceNumber);
	formData.append('newInvoiceDescription', data.newInvoiceDescription);
	
	// Add URL parameters to form data
	url.searchParams.forEach((value, key) => {
		formData.append(key, value);
	});

	// Show loading state
	row.style.opacity = '0.6';
	row.style.pointerEvents = 'none';

	// Debug: Log what we're sending
	console.log('Saving data:', {
		poolFile: data.poolFile,
		newSubject: data.newSubject,
		newAccount: data.newAccount,
		newAmount: data.newAmount,
		newDate: data.newDate,
		newInvoiceNumber: data.newInvoiceNumber,
		newInvoiceDescription: data.newInvoiceDescription,
		url: url.toString()
	});

	// Send AJAX request
	console.log('Sending AJAX request to:', url.toString());
	console.log('Form data:', formData);
	fetch(url.toString(), {
		method: 'POST',
		body: formData,
		redirect: 'follow' // Follow redirects
	})
	.then(response => {
		console.log('Save response status:', response.status, response.ok, response.redirected, response.statusText);
		// Check if response is ok (status 200-299) - backend returns HTML page which is fine
		// Any 2xx status means success
		if (response.ok || (response.status >= 200 && response.status < 300)) {
			// Update the row with new values (convert back to display format)
			let dateFormatted = data.newDate;
			if (dateFormatted && dateFormatted.includes(' ')) {
				dateFormatted = dateFormatted.split(' ')[0];
			}
			
			// Update cells (skip checkbox column which is nth-child(1))
			// Update cells (skip checkbox column which is nth-child(1))
			row.querySelector('td:nth-child(2)').innerHTML = "<span class='cell-content'>" + escapeHTML(data.newSubject) + "</span>";
			row.querySelector('td:nth-child(3)').innerHTML = "<span class='cell-content'>" + escapeHTML(data.newAccount) + "</span>";
			row.querySelector('td:nth-child(4)').innerHTML = "<span class='cell-content'>" + escapeHTML(data.newAmount) + "</span>";
			row.querySelector('td:nth-child(5)').innerHTML = "<span class='cell-content'>" + escapeHTML(data.newInvoiceNumber || '') + "</span>";
			row.querySelector('td:nth-child(6)').innerHTML = "<span class='cell-content'>" + escapeHTML(data.newInvoiceDescription || '') + "</span>";
			row.querySelector('td:nth-child(7)').innerHTML = "<span class='cell-content'>" + dateFormatted + "</span>";
			
			// Restore actions column with updated values
			const poolFileFromRow = row.getAttribute('data-pool-file');
			const currentUrl = window.location.href;
			const deleteUrl = currentUrl.replace(/poolFile=[^&]*/, '') + (currentUrl.includes('?') ? '&' : '?') + 'unlink=1&unlinkFile=' + encodeURIComponent(poolFileFromRow);
			
			const actionsCell = "<div style='display: flex; gap: 4px; justify-content: center; align-items: center; flex-wrap: wrap;'>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); enableRowEdit(this, \"" + escapeHTML(poolFileFromRow) + "\", \"" + escapeHTML(data.newSubject) + "\", \"" + escapeHTML(data.newAccount) + "\", \"" + escapeHTML(data.newAmount) + "\", \"" + dateFormatted + "\", \"" + escapeHTML(data.newInvoiceNumber || '') + "\", \"" + escapeHTML(data.newInvoiceDescription || '') + "\"); return false;' style='padding: 4px 8px; background-color: " + buttonColor + "; color: " + buttonTxtColor + "; border: 1px solid " + buttonColor + "; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.opacity=\"0.9\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.opacity=\"1\"; this.style.transform=\"scale(1)\"' title='Rediger'>" + svgIcons.pencil + "</button>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); deletePoolFile(\"" + escapeHTML(poolFileFromRow) + "\", " + JSON.stringify(data.newSubject) + ", \"" + deleteUrl + "\"); return false;' style='padding: 4px 8px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#c82333\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#dc3545\"; this.style.transform=\"scale(1)\"' title='Slet'>" + svgIcons.trash + "</button>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); extractPoolFile(\"" + escapeHTML(poolFileFromRow) + "\"); return false;' style='padding: 4px 8px; background-color: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#138496\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#17a2b8\"; this.style.transform=\"scale(1)\"' title='Udtræk fakturadata'>" + svgIcons.scan + "</button>" +
				"</div>";
			
			row.querySelector('td:nth-child(8)').innerHTML = actionsCell;
			
			// Remove edit mode
			row.removeAttribute('data-editing');
			delete row.dataset.originalValues;
			delete row.dataset.originalActions;
			
			// Update the data in docData array for future renders
			const dataIndex = docData.findIndex(d => {
				const url = new URL(d.href, window.location.origin);
				const allPoolFiles = url.searchParams.getAll('poolFile');
				return allPoolFiles.length > 0 && allPoolFiles[allPoolFiles.length - 1] === poolFileFromRow;
			});
			
			if (dataIndex !== -1) {
				docData[dataIndex].subject = data.newSubject;
				docData[dataIndex].account = data.newAccount;
				docData[dataIndex].amount = data.newAmount;
				docData[dataIndex].date = dateFormatted;
			}
			
			// Update bulk button state
			if (typeof updateBulkButton === 'function') {
				updateBulkButton();
			}
		} else {
			// Try to get error message from response
			response.text().then(text => {
				console.error('Save failed. Response:', response.status, text);
				alert('Fejl ved gemning (Status: ' + response.status + '). Prøv igen.');
			}).catch(() => {
				alert('Fejl ved gemning. Prøv igen.');
			});
		}
	})
	.catch(error => {
		console.error('Error saving:', error);
		alert('Fejl ved gemning: ' + error.message);
	})
	.finally(() => {
		row.style.opacity = '1';
		row.style.pointerEvents = 'auto';
	});
};

// Old form submission handlers removed - now using AJAX



    fetchFiles();
    window.sortFiles = sortFiles;



})();



</script>
JS;

#####
	// if ($poolFile) { //#*rm: cannot remove '*': No such file or directory
	//# cp: cannot stat '../..error .... when documents.php loads*//
	// 	$tmp="../".$docFolder."/$db/pulje/$poolFile";
	// 	if (!is_dir("../temp/$db/pulje")) mkdir("../temp/$db/pulje");
	// 	system("cd ../temp/$db/pulje\nrm *\ncp $tmp .\n");
	// } else {
	// 	$ccalert= __line__." ".findtekst('1416|Ingen bilag i puljen', $sprog_id);
	// 	print "<BODY onLoad=\"javascript:alert('$ccalert')\">\n";
	// 	$tmp="documents.php?$params";
	// 	print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
	// 	exit;
	// }
	if ($poolFile) {
		$tmp = "../" . $docFolder . "/$db/pulje/$poolFile";

		if (!is_dir("../temp/$db/pulje")) {
			mkdir("../temp/$db/pulje", 0777, true);
		}

		// Remove all files safely (ignore error if empty)
		system("cd ../temp/$db/pulje && rm -f -- *");

		// Copy file only if it exists
		if (file_exists($tmp)) {
			system("cp " . escapeshellarg($tmp) . " .");
		} 
	}

	// Fixed bottom section in left panel (must be inside leftPanel, before rightPanel)
	global $params, $showDoc, $sprog_id;
	if (!isset($showDoc)) $showDoc = '';
	$uploadParams = $params . "&openPool=1&poolFile=$poolFile&docFolder=" . urlencode($docFolder);
	
	print "<div id='fixedCell' style='width: 100%; flex-shrink: 0;'>";
	print "<div id='contentWrapper'>";
	
	// Get button colors for fixedBottom
	if (!isset($buttonColor)) {
		$qtxt = "select var_value from settings where var_name = 'buttonColor' and var_grp = 'colors' and user_id = '$bruger_id'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$buttonColor = $r['var_value'];
		} else {
			$buttonColor = '#114691';
		}
	}
	if (!isset($buttonTxtColor)) {
		$qtxt = "select var_value from settings where var_name = 'buttonTxtColor' and var_grp = 'colors' and user_id = '$bruger_id'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$buttonTxtColor = $r['var_value'];
		} else {
			$buttonTxtColor = '#ffffff';
		}
	}
	
	// Close the gennemse form before the upload form (forms cannot be nested)
	print "</form>";
	
	print "<div id='fixedBottom' style='position: relative; width: 100%; padding: 16px; box-sizing: border-box; z-index: 1000;'>";
	
	// Toggle header for upload section
	print "<div id='uploadToggleHeader' onclick='toggleUploadSection()' style='display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background-color: $buttonColor; color: $buttonTxtColor; border-radius: 8px; cursor: pointer; margin-bottom: 10px; user-select: none;'>";
	print "<span style='font-weight: 600; font-size: 13px;'><span style='margin-right: 6px;'>$svgUpload</span>".findtekst(1414, $sprog_id)."</span>";
	print "<span id='uploadToggleIcon' style='transition: transform 0.3s;'>$svgChevronDown</span>";
	print "</div>";
	
	// Collapsible upload content
	print "<div id='uploadContent' style='overflow: hidden; transition: max-height 0.3s ease, opacity 0.3s ease;'>";
	
	// Upload form (independent form, not nested) - uses AJAX like drag and drop
	print "<form id='fileUploadForm' enctype='multipart/form-data' action='documents.php?$uploadParams' method='POST' style='margin: 0; padding: 0;'>";
	print "<input type='hidden' name='MAX_FILE_SIZE' value='100000000'>";
	print "<input type='hidden' name='openPool' value='1'>";
	print "<label for='fileUploadInput' style='display: block; width: 100%; margin-bottom: 12px; cursor: pointer;'>";
	print "<input id='fileUploadInput' class='inputbox' name='uploadedFile[]' type='file' accept='.pdf,.jpg,.jpeg,.png' multiple style='width: 100%; height: auto; min-height: 40px; padding: 8px; border: 2px solid #ddd; border-radius: 8px; font-size: 12px; box-sizing: border-box; overflow: visible; background-color: #ffffff; transition: all 0.3s ease; pointer-events: auto; position: relative; z-index: 10; cursor: pointer;'>";
	print "</label>";
	print "<button type='submit' id='fileUploadSubmit' style='width: 100%; padding: 10px; margin-bottom: 12px; background-color: $buttonColor; color: $buttonTxtColor; border: 2px solid $buttonColor; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; box-sizing: border-box; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.2);'>".findtekst(1078, $sprog_id)."</button>";
	print "</form>";
	
	// JavaScript to handle form submission via AJAX (same as drag and drop)
	print "<script>
	document.addEventListener('DOMContentLoaded', function() {
		var uploadForm = document.getElementById('fileUploadForm');
		var fileInput = document.getElementById('fileUploadInput');
		var submitBtn = document.getElementById('fileUploadSubmit');
		
		if (uploadForm) {
			uploadForm.addEventListener('submit', function(e) {
				e.preventDefault();
				
				if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
					alert('Please select at least one file.');
					return;
				}
				
				var files = Array.from(fileInput.files);
				var allowedExtensions = ['.pdf', '.jpg', '.jpeg', '.png'];
				
				// Validate all files first
				for (var i = 0; i < files.length; i++) {
					var fileName = files[i].name.toLowerCase();
					var isAllowed = allowedExtensions.some(function(ext) {
						return fileName.endsWith(ext);
					});
					if (!isAllowed) {
						alert('File ' + files[i].name + ' is not allowed. Please select only PDF or image files (jpg, png).');
						return;
					}
				}
				
				// Show loading state
				var originalBtnText = submitBtn.innerHTML;
				var totalFiles = files.length;
				var uploadedCount = 0;
				var failedCount = 0;
				var lastUploadedFilename = null;
				
				function updateProgress() {
					submitBtn.innerHTML = '<svg class=\"icon-svg icon-spin\" style=\"margin-right: 6px;\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><line x1=\"12\" y1=\"2\" x2=\"12\" y2=\"6\"></line><line x1=\"12\" y1=\"18\" x2=\"12\" y2=\"22\"></line><line x1=\"4.93\" y1=\"4.93\" x2=\"7.76\" y2=\"7.76\"></line><line x1=\"16.24\" y1=\"16.24\" x2=\"19.07\" y2=\"19.07\"></line><line x1=\"2\" y1=\"12\" x2=\"6\" y2=\"12\"></line><line x1=\"18\" y1=\"12\" x2=\"22\" y2=\"12\"></line><line x1=\"4.93\" y1=\"19.07\" x2=\"7.76\" y2=\"16.24\"></line><line x1=\"16.24\" y1=\"7.76\" x2=\"19.07\" y2=\"4.93\"></line></svg> Uploader ' + (uploadedCount + failedCount + 1) + ' af ' + totalFiles + '...';
				}
				
				submitBtn.disabled = true;
				submitBtn.style.opacity = '0.7';
				fileInput.disabled = true;
				updateProgress();
				
				// Determine URL
				var currentPath = window.location.pathname;
				var uploadUrl = currentPath.indexOf('/includes/') !== -1 ? 'documents.php' : '../includes/documents.php';
				
				// Upload files sequentially
				function uploadFile(index) {
					if (index >= files.length) {
						// All files processed
						submitBtn.innerHTML = originalBtnText;
						submitBtn.disabled = false;
						submitBtn.style.opacity = '1';
						fileInput.disabled = false;
						fileInput.value = '';
						
						var message = '✓ Upload complete!\\n';
						message += uploadedCount + ' file(s) uploaded successfully';
						if (failedCount > 0) {
							message += '\\n' + failedCount + ' file(s) failed';
						}
						alert(message);
						
						// Redirect to focus on the last uploaded file
						if (lastUploadedFilename) {
							var currentUrl = new URL(window.location.href);
							currentUrl.searchParams.set('poolFile', lastUploadedFilename);
							currentUrl.searchParams.set('openPool', '1');
							window.location.href = currentUrl.toString();
						} else {
							window.location.reload();
						}
						return;
					}
					
					var file = files[index];
					updateProgress();
					
					// Create FormData for this file
					var formData = new FormData();
					formData.append('uploadedFile', file);
					formData.append('openPool', '1');
					
					// Add clipVariables if available
					if (typeof clipVariables !== 'undefined') {
						for (var key in clipVariables) {
							if (clipVariables.hasOwnProperty(key)) {
								formData.append(key, clipVariables[key]);
							}
						}
					}
					
					fetch(uploadUrl, {
						method: 'POST',
						body: formData
					})
					.then(function(response) {
						return response.text().then(function(text) {
							try {
								return JSON.parse(text);
							} catch(e) {
								if (text.indexOf('\"success\":true') !== -1) {
									var filenameMatch = text.match(/\"filename\"\\s*:\\s*\"([^\"]+)\"/);
									return {
										success: true,
										filename: filenameMatch ? filenameMatch[1] : file.name,
										message: 'File uploaded successfully'
									};
								}
								throw new Error('Invalid response from server');
							}
						});
					})
					.then(async function(data) {
						if (data && data.success) {
							uploadedCount++;
							lastUploadedFilename = data.filename;

							// Auto-extract and save information from API
							try {
								const extractFormData = new FormData();
								extractFormData.append('action', 'extract');
								extractFormData.append('poolFile', data.filename);
								extractFormData.append('db', '$db');
								extractFormData.append('docFolder', '$docFolder');
								
								const extRes = await fetch('docsIncludes/extractInvoiceHandler.php', { method: 'POST', body: extractFormData });
								const extData = await extRes.json();
								
								if (extData.success && extData.data) {
									const svData = new FormData();
									svData.append('action', 'save');
									svData.append('poolFile', data.filename);
									svData.append('db', '$db');
									
									const d = extData.data;
									if(d.amount) svData.append('newAmount', d.amount);
									if(d.date) svData.append('newDate', d.date);
									if(d.vendor) svData.append('newSubject', d.vendor);
									if(d.invoiceNumber) svData.append('newInvoiceNumber', d.invoiceNumber);
									if(d.description) svData.append('newDescription', d.description);
									
									await fetch('docsIncludes/extractInvoiceHandler.php', { method: 'POST', body: svData });
								}
							} catch(e) { 
								console.error('Auto-extract failed', e); 
							}
						} else {
							failedCount++;
							console.error('Upload failed for ' + file.name + ':', data && data.message ? data.message : 'Unknown error');
						}
						// Continue to next file
						uploadFile(index + 1);
					})
					.catch(function(error) {
						failedCount++;
						console.error('Upload error for ' + file.name + ':', error);
						// Continue to next file
						uploadFile(index + 1);
					});
				}
				
				// Start uploading from first file
				uploadFile(0);
			});
		}
	});
	</script>";

	// JavaScript for drag and drop functionality - define handleDrop and handleDragOver functions
	print "<script>
	// Global functions for drag and drop (must be global for inline event handlers)
	function handleDragOver(e) {
		e.preventDefault();
		e.stopPropagation();
		var dropZone = document.getElementById('dropZone');
		if (dropZone) {
			dropZone.style.borderColor = '$buttonColor';
			dropZone.style.backgroundColor = 'rgba(0,0,0,0.08)';
			dropZone.style.transform = 'scale(1.02)';
		}
	}
	
	function handleDrop(e) {
		e.preventDefault();
		e.stopPropagation();
		
		var dropZone = document.getElementById('dropZone');
		if (dropZone) {
			dropZone.style.borderColor = '$buttonColor';
			dropZone.style.backgroundColor = 'rgba(0,0,0,0.02)';
			dropZone.style.transform = 'scale(1)';
		}
		
		var files = e.dataTransfer.files;
		if (!files || files.length === 0) {
			console.log('No files dropped');
			return;
		}
		
		// Validate file types
		var allowedExtensions = ['.pdf', '.jpg', '.jpeg', '.png'];
		var validFiles = [];
		for (var i = 0; i < files.length; i++) {
			var fileName = files[i].name.toLowerCase();
			var isAllowed = allowedExtensions.some(function(ext) {
				return fileName.endsWith(ext);
			});
			if (isAllowed) {
				validFiles.push(files[i]);
			} else {
				alert('File ' + files[i].name + ' is not allowed. Please select only PDF or image files (jpg, png).');
			}
		}
		
		if (validFiles.length === 0) {
			return;
		}
		
		// Show loading state on drop zone
		var dropText = document.getElementById('dropText');
		var originalDropText = dropText ? dropText.innerHTML : '';
		var totalFiles = validFiles.length;
		var uploadedCount = 0;
		var failedCount = 0;
		var lastUploadedFilename = null;
		
		function updateDropProgress() {
			if (dropText) {
				dropText.innerHTML = '<svg class=\"icon-svg icon-spin\" style=\"margin-right: 6px;\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><line x1=\"12\" y1=\"2\" x2=\"12\" y2=\"6\"></line><line x1=\"12\" y1=\"18\" x2=\"12\" y2=\"22\"></line><line x1=\"4.93\" y1=\"4.93\" x2=\"7.76\" y2=\"7.76\"></line><line x1=\"16.24\" y1=\"16.24\" x2=\"19.07\" y2=\"19.07\"></line><line x1=\"2\" y1=\"12\" x2=\"6\" y2=\"12\"></line><line x1=\"18\" y1=\"12\" x2=\"22\" y2=\"12\"></line><line x1=\"4.93\" y1=\"19.07\" x2=\"7.76\" y2=\"16.24\"></line><line x1=\"16.24\" y1=\"7.76\" x2=\"19.07\" y2=\"4.93\"></line></svg> Uploader ' + (uploadedCount + failedCount + 1) + ' af ' + totalFiles + '...';
			}
		}
		
		updateDropProgress();
		
		// Determine URL
		var currentPath = window.location.pathname;
		var uploadUrl = currentPath.indexOf('/includes/') !== -1 ? 'documents.php' : '../includes/documents.php';
		
		// Upload files sequentially
		function uploadFile(index) {
			if (index >= validFiles.length) {
				// All files processed
				if (dropText) {
					dropText.innerHTML = originalDropText;
				}
				
				var message = '✓ Upload complete!\\n';
				message += uploadedCount + ' file(s) uploaded successfully';
				if (failedCount > 0) {
					message += '\\n' + failedCount + ' file(s) failed';
				}
				alert(message);
				
				// Redirect to focus on the last uploaded file
				if (lastUploadedFilename) {
					var currentUrl = new URL(window.location.href);
					currentUrl.searchParams.set('poolFile', lastUploadedFilename);
					currentUrl.searchParams.set('openPool', '1');
					window.location.href = currentUrl.toString();
				} else {
					window.location.reload();
				}
				return;
			}
			
			var file = validFiles[index];
			updateDropProgress();
			
			// Create FormData for this file
			var formData = new FormData();
			formData.append('uploadedFile', file);
			formData.append('openPool', '1');
			
			// Add clipVariables if available
			if (typeof clipVariables !== 'undefined') {
				for (var key in clipVariables) {
					if (clipVariables.hasOwnProperty(key)) {
						formData.append(key, clipVariables[key]);
					}
				}
			}
			
			fetch(uploadUrl, {
				method: 'POST',
				body: formData
			})
			.then(function(response) {
				return response.text().then(function(text) {
					try {
						return JSON.parse(text);
					} catch(e) {
						if (text.indexOf('\"success\":true') !== -1) {
							var filenameMatch = text.match(/\"filename\"\\s*:\\s*\"([^\"]+)\"/);
							return {
								success: true,
								filename: filenameMatch ? filenameMatch[1] : file.name,
								message: 'File uploaded successfully'
							};
						}
						throw new Error('Invalid response from server');
					}
				});
			})
			.then(function(data) {
				if (data && data.success) {
					uploadedCount++;
					lastUploadedFilename = data.filename;
				} else {
					failedCount++;
					console.error('Upload failed for ' + file.name + ':', data && data.message ? data.message : 'Unknown error');
				}
				// Continue to next file
				uploadFile(index + 1);
			})
			.catch(function(error) {
				failedCount++;
				console.error('Upload error for ' + file.name + ':', error);
				// Continue to next file
				uploadFile(index + 1);
			});
		}
		
		// Start uploading from first file
		uploadFile(0);
	}
	</script>";

	// Add drag and drop zone - use buttonColor with opacity for background
	$dropZone = "<div id='dropZone' ondrop='handleDrop(event)' ondragover='handleDragOver(event)' style='width: 100%; height: 70px; border: 2px dashed $buttonColor; border-radius: 8px; padding: 12px; background-color: rgba(0,0,0,0.02); cursor: pointer; transition: all 0.3s ease; box-sizing: border-box; display: flex; align-items: center; justify-content: center; margin: 0 auto;'>";
	$dropZone .= "<span id='dropText' style='font-size: 12px; color: $buttonColor; text-align: center; font-weight: 500;'>".findtekst('2593|Træk og slip PDF-fil her', $sprog_id)."</span>";
	$dropZone .= "</div>";
	print "<div class='clip-image drop-zone-container' title='Drag and Drop the file here' style='display: block; width: 100%; margin: 0; padding: 0;'>";
	print $dropZone;
	print "</div>";

	// Add email text for sending bilag
	print "<div style='margin-top: 14px; padding: 12px; background-color: $buttonColor; border-radius: 8px; text-align: center;'>";
	print "<div style='font-size: 11px; color: $buttonTxtColor; margin-bottom: 6px; font-weight: 500;'>".findtekst('2591|Bilag kan sendes til', $sprog_id)."</div>";
	print "<a href='mailto:bilag_".$db."@".$_SERVER['SERVER_NAME']."' style='font-size: 11px; color: $buttonTxtColor; text-decoration: none; word-break: break-all; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.2); transition: color 0.2s;' onmouseover='this.style.color=\"#f0f0f0\"' onmouseout='this.style.color=\"$buttonTxtColor\"'>";
	print "bilag_".$db."@".$_SERVER['SERVER_NAME']."</a>";
	print "</div>";

	// Add "Link bilag fra anden linje" button for kassekladde
	if ($source == 'kassekladde') {
		$linkUrl = "../includes/documents.php?linkBilag=1&kladde_id=" . urlencode($kladde_id) . "&bilag=" . urlencode($bilag) . "&fokus=" . urlencode($fokus) . "&sourceId=" . urlencode($sourceId) . "&source=" . urlencode($source);
		print "<div style='margin-top: 14px;'>";
		print "<a href='$linkUrl' style='display: block; width: 100%; padding: 10px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 8px; font-size: 12px; font-weight: 600; text-align: center; box-sizing: border-box; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#5a6268\"' onmouseout='this.style.backgroundColor=\"#6c757d\"'>";
		print "<span style='margin-right: 4px;'>$svgLink</span> Link bilag fra anden linje";
		print "</a>";
		print "</div>";
	}
	
	// Close uploadContent div
	print "</div>"; // uploadContent

	// Add JavaScript variables for drag and drop and toggle function
	print "<script>
	var clipVariables = {
		sourceId: " . (int)$sourceId . ",
		kladde_id: " . (int)$kladde_id . ",
		bilag: " . (int)$bilag . ",
		fokus: '$fokus',
		source: '$source',
		openPool: 1
	};
	
	// Toggle upload section visibility
	window.toggleUploadSection = function() {
		const content = document.getElementById('uploadContent');
		const icon = document.getElementById('uploadToggleIcon');
		const isHidden = localStorage.getItem('docPoolUploadHidden') === 'true';
		
		if (isHidden) {
			// Show
			content.style.maxHeight = content.scrollHeight + 'px';
			content.style.opacity = '1';
			if (icon) icon.style.transform = 'rotate(0deg)';
			localStorage.setItem('docPoolUploadHidden', 'false');
			// After animation, set to auto for dynamic content
			setTimeout(function() {
				content.style.maxHeight = 'none';
			}, 300);
		} else {
			// Hide
			content.style.maxHeight = content.scrollHeight + 'px';
			setTimeout(function() {
				content.style.maxHeight = '0';
			}, 10);
			content.style.opacity = '0';
			if (icon) icon.style.transform = 'rotate(-90deg)';
			localStorage.setItem('docPoolUploadHidden', 'true');
		}
	};
	
	// Initialize upload section state on page load
	document.addEventListener('DOMContentLoaded', function() {
		const content = document.getElementById('uploadContent');
		const icon = document.getElementById('uploadToggleIcon');
		const isHidden = localStorage.getItem('docPoolUploadHidden') === 'true';
		
		if (isHidden && content && icon) {
			content.style.maxHeight = '0';
			content.style.opacity = '0';
			icon.style.transform = 'rotate(-90deg)';
		} else if (content) {
			content.style.maxHeight = 'none';
			content.style.opacity = '1';
			if (icon) icon.style.transform = 'rotate(0deg)';
		}
	});
	</script>";
	
	print "</div>"; // fixedBottom
	print "</div>"; // contentWrapper
	print "</div>"; // fixedCell
	print "</div>"; // Close leftPanel
	
	print "<div id='resizer'></div>"; // Resizer divider

	// Right panel for document viewer
	print "<div id='rightPanel' style='flex: 1; min-width: 200px; height: 100%; display: flex; flex-direction: column;'>";
	print "<div id='documentViewer' style='flex: 1; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;'>";
	$corrected = 0;
	$ext = pathinfo($poolFile, PATHINFO_EXTENSION);
	$fullName = "$docFolder/$db/pulje/$poolFile";
	
	// Skip processing if poolFile is empty or points to a directory
	if (empty($poolFile) || is_dir($fullName)) {
		// Log and skip - this is not a valid file
		if (is_dir($fullName)) {
			error_log("Skipping directory in poolFile: $fullName");
		}
		$poolFile = '';
	}
	
#	cho __line__." $fullName<br>";
	$descFile = $newName = str_replace($ext,'.desc',$fullName);
	if (strpos($fullName,'æ')) {
		$newName = str_replace('æ','ae',$fullName);
		$poolFile = str_replace('æ','ae',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
	if (strpos($fullName,'ø')) {
		$newName = str_replace('ø','oe',$fullName);
		$poolFile = str_replace('ø','oe',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
	if (strpos($fullName,'å')) {
		$newName = str_replace('å','aa',$fullName);
		$poolFile = str_replace('å','aa',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}

	if (strpos($fullName,'Æ')) {
		$newName = str_replace('Æ','AE',$fullName);
		$poolFile = str_replace('Æ','AE',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
	if (strpos($fullName,'Ø')) {
		$newName = str_replace('Ø','OE',$fullName);
		$poolFile = str_replace('Ø','OE',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
	if (strpos($fullName,'Å')) {
		$newName = str_replace('Å','AA',$fullName);
		$poolFile = str_replace('Å','AA',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
/*
	if (strpos($fullName,'(')) {
		$newName = str_replace('(','_',$fullName);
		$poolFile = str_replace('(','_',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
	if (strpos($fullName,')')) {
		$newName = str_replace(')','_',$fullName);
		$poolFile = str_replace(')','_',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
*/
	if (strpos($fullName,'?')) {
		$newName = str_replace('?','_',$fullName);
		$poolFile = str_replace('ø','oe',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		$fullName = $newName ;
		$corrected = 1;
	}
	if (!$ext && $poolFile && file_exists($fullName) && is_file($fullName)) {
		$fileType = strtolower(file_get_contents($fullName, FALSE, NULL, 0, 4));
		if ($fileType == '%pdf') $newName = $fullName.'.pdf';
		else $newName = $fullName;
		$newName = str_replace('ø','oe',$newName);
		$poolFile = str_replace('ø','oe',$poolFile);
		exec("mv \"$fullName\" \"$newName\"\n");
		if (file_exists($newName) && $newName != $fullName) {
			$fullName = $newName ;
			if ($fileType == '%pdf') $poolFile = $poolFile.'.pdf';
			$corrected = 1;
		} else $corrected = 0;
	}
	if (strtolower($ext) != 'pdf') {
		$choices = array('bmp','jpg','jpeg','png','tif','tiff');
#		$tmp=str_replace($fullName);
		if (in_array(strtolower($ext),$choices)) {
			$fs = filesize($fullName);
			if ($fs > 500000) {
				$reduce = round (50000000 / $fs, 0);
				exec("$exec_path/mogrify -resize $reduce% $fullName");
			}
			$newName =  str_replace($ext,'pdf',$fullName);
			$tmp = str_replace($ext,'pdf',$poolFile);
			if (file_exists("$docFolder/$db/pulje/$tmp")) $poolFile = $tmp;
			exec("$exec_path/convert $fullName $newName");
			if (file_exists($newName))  {
				if (filesize($newName) > 10) {
					unlink($fullName);
					$fullName = $newName;
					$corrected = 1;
				} else {
					unlink($newName);
					$corrected = 0;
				}
			}
		}
	}
	if (!file_exists($fullName) && file_exists("$docFolder/$db/pulje/$poolFile")) {
		$fullName = "$docFolder/$db/pulje/$poolFile";
		$corrected = '0';
	}
#	if ($bruger_id == '-1') {
#		echo $corrected;
#		exit;
#	}
	if ($corrected == '1') {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/documents.php?$params&openPool=1&poolFile=$poolFile\">";
	}
			if ($poolFile) {
		if ($google_docs) $src="http://docs.google.com/viewer?url=$fullName&embedded=true";
		else $src=$tmp;
		
		print "<iframe style=\"width:100%;height:100%;border:none;overflow:hidden;\" src=\"$fullName#pagemode=none\" frameborder=\"0\">";
		print "</iframe>";
	}
	print "</div>"; // documentViewer
	print "</div>"; // rightPanel
	
	// Close docPoolContainer div
	print "</div>";

		// Additional dynamic styles for button colors (styles are in docpool.css)
		print "<style>
			#fixedBottom input[type='file']::-webkit-file-upload-button {
				background-color: $buttonColor;
				color: $buttonTxtColor;
			}
			#fixedBottom input[type='submit'] {
				background-color: $buttonColor;
				color: $buttonTxtColor;
				border-color: $buttonColor;
			}
			#fixedBottom .upload-label {
				background-color: $buttonColor;
				color: $buttonTxtColor;
			}
			#fixedBottom .email-section {
				background-color: $buttonColor;
			}
			#fixedBottom .email-section .email-label,
			#fixedBottom .email-section .email-link {
				color: $buttonTxtColor;
			}
			#dropZone {
				border-color: $buttonColor;
			}
			#dropText {
				color: $buttonColor;
			}
		</style>";

		// Script to adjust fixed div width and position
		print "<script>
			const buttonColor = ".json_encode($buttonColor).";
			const buttonTxtColor = ".json_encode($buttonTxtColor).";
			
			function updateFixedDiv() {
				const leftPanel = document.getElementById('leftPanel');
				const fixedCell = document.getElementById('fixedCell');
				const fixedDiv = document.getElementById('fixedBottom');
				const fileListContainer = document.getElementById('fileListContainer');

				if (!fixedDiv || !fileListContainer || !leftPanel || !fixedCell) {
					return;
				}

				setTimeout(function() {
					const leftPanelWidth = leftPanel.offsetWidth;
					
					// Set fixedBottom width to match the left panel width
					fixedDiv.style.width = leftPanelWidth + 'px';
					
					// No need for padding-bottom since fixedCell is now a normal flex item
				}, 100);
			}

			document.addEventListener('DOMContentLoaded', function() {
				updateFixedDiv();
				
				// Ensure file input is clickable
				const fileInput = document.getElementById('fileUploadInput');
				if (fileInput) {
					fileInput.style.pointerEvents = 'auto';
					fileInput.style.cursor = 'pointer';
				}
				
				// Add hover effect for drop zone
				const dropZone = document.getElementById('dropZone');
				if (dropZone) {
					dropZone.addEventListener('dragover', function(e) {
						e.preventDefault();
						this.style.borderColor = buttonColor;
						this.style.backgroundColor = 'rgba(0,0,0,0.05)';
						const dropText = this.querySelector('#dropText');
						if (dropText) dropText.style.color = buttonColor;
					});
					dropZone.addEventListener('dragleave', function(e) {
						this.style.borderColor = buttonColor;
						this.style.backgroundColor = 'rgba(0,0,0,0.02)';
						const dropText = this.querySelector('#dropText');
						if (dropText) dropText.style.color = buttonColor;
					});
				}
				
				// Resizer functionality - optimized for maximum responsiveness
				function setupResizer() {
					const resizer = document.getElementById('resizer');
					const leftPanel = document.getElementById('leftPanel');
					const rightPanel = document.getElementById('rightPanel');
					const container = document.getElementById('docPoolContainer');
					
					if (!resizer || !leftPanel || !rightPanel || !container) {
						return false;
					}
					
					let isResizing = false;
					let startX = 0;
					let startLeftWidth = 0;
					let containerWidth = 0;
					let currentMouseX = 0;
					let rafId = null;
					let moveHandlers = [];
					let endHandlers = [];
					let pointerId = null;
					let pointerTarget = null;
					
					// Mouse move handler - must be defined before handleStart
					const handleMove = function(e) {
						if (!isResizing) return;
						
						e.preventDefault();
						e.stopPropagation();
						
						const clientX = e.clientX !== undefined ? e.clientX : (e.touches?.[0]?.clientX);
						if (clientX !== undefined && clientX !== null && !isNaN(clientX)) {
							currentMouseX = clientX;
						}
					};
					
					// Mouse end handler
					const handleEnd = function(e) {
						if (!isResizing) return;
						
						// Release pointer capture if it was set
						if (pointerTarget && pointerTarget.releasePointerCapture && pointerId !== undefined) {
							try {
								pointerTarget.releasePointerCapture(pointerId);
							} catch(err) {
								// Ignore errors
							}
						}
						
						// Clear pointer tracking
						pointerTarget = null;
						pointerId = null;
						
						isResizing = false;
						document.body.style.cursor = '';
						document.body.style.userSelect = '';
						
						// Cancel animation frame
						if (rafId !== null) {
							cancelAnimationFrame(rafId);
							rafId = null;
						}
						
						// Remove all move and end listeners
						moveHandlers.forEach(({element, event, handler, options}) => {
							element.removeEventListener(event, handler, options);
						});
						endHandlers.forEach(({element, event, handler, options}) => {
							element.removeEventListener(event, handler, options);
						});
						moveHandlers = [];
						endHandlers = [];
						
						// Update fixedBottom after resize
						setTimeout(function() {
							if (typeof updateFixedDiv === 'function') {
								updateFixedDiv();
							}
						}, 50);
					};
					
					// Use both mousedown and pointerdown for maximum compatibility
					const handleStart = function(e) {
						if (isResizing) return; // Prevent double-start
						
						isResizing = true;
						startX = e.clientX || e.touches?.[0]?.clientX;
						currentMouseX = startX;
						
						// Cache initial values
						const rect = container.getBoundingClientRect();
						containerWidth = rect.width;
						startLeftWidth = leftPanel.offsetWidth;
						
						document.body.style.cursor = 'col-resize';
						document.body.style.userSelect = 'none';
						
						e.preventDefault();
						e.stopPropagation();
						
						// Store pointer info for capture/release
						pointerTarget = e.target;
						pointerId = e.pointerId;
						
						// Use pointer capture if available (for better tracking when moving quickly)
						if (pointerTarget && pointerTarget.setPointerCapture && pointerId !== undefined) {
							try {
								pointerTarget.setPointerCapture(pointerId);
							} catch(err) {
								// Pointer capture not supported or failed, continue without it
							}
						}
						
						// Add move listeners dynamically when resizing starts
						const moveOptions = { capture: true, passive: false };
						const moveEvents = [
							{ element: document, event: 'mousemove' },
							{ element: document, event: 'pointermove' },
							{ element: window, event: 'mousemove' },
							{ element: window, event: 'pointermove' },
							{ element: document.body, event: 'mousemove' },
							{ element: document.body, event: 'pointermove' }
						];
						
						moveEvents.forEach(({element, event}) => {
							element.addEventListener(event, handleMove, moveOptions);
							moveHandlers.push({element, event, handler: handleMove, options: moveOptions});
						});
						
						// Handle mouse leaving window - continue tracking last known position
						const handleMouseLeave = function(e) {
							if (!isResizing) return;
							// Don't stop resizing, just continue with last known position
							// The animation frame will continue using currentMouseX
						};
						window.addEventListener('mouseleave', handleMouseLeave, { capture: true });
						moveHandlers.push({element: window, event: 'mouseleave', handler: handleMouseLeave, options: { capture: true }});
						
						// Add end listeners dynamically
						const endOptions = { capture: true };
						const endEvents = [
							{ element: document, event: 'mouseup' },
							{ element: document, event: 'pointerup' },
							{ element: document, event: 'touchend' },
							{ element: window, event: 'mouseup' },
							{ element: window, event: 'pointerup' },
							{ element: window, event: 'touchend' },
							{ element: document.body, event: 'mouseup' },
							{ element: document.body, event: 'pointerup' }
						];
						
						endEvents.forEach(({element, event}) => {
							element.addEventListener(event, handleEnd, endOptions);
							endHandlers.push({element, event, handler: handleEnd, options: endOptions});
						});
						
						// Start animation frame loop
						const updateLoop = function() {
							if (!isResizing) {
								rafId = null;
								return;
							}
							
							const deltaX = currentMouseX - startX;
							const newLeftWidth = startLeftWidth + deltaX;
							
							// Apply min/max constraints
							const minLeftWidth = 200;
							const minRightWidth = 200;
							const maxLeftWidth = containerWidth * 0.8;
							const resizerWidth = 5;
							
							// Constrain left panel width
							const constrainedLeftWidth = Math.max(minLeftWidth, Math.min(maxLeftWidth, newLeftWidth));
							
							// Calculate right width - ensure it meets minimum
							const calculatedRightWidth = containerWidth - constrainedLeftWidth - resizerWidth;
							const constrainedRightWidth = Math.max(minRightWidth, calculatedRightWidth);
							
							// Recalculate left width if right panel hit minimum
							const finalLeftWidth = calculatedRightWidth < minRightWidth 
								? containerWidth - minRightWidth - resizerWidth 
								: constrainedLeftWidth;
							
							// Update panels - use flex-basis for better control
							leftPanel.style.flex = '0 0 ' + finalLeftWidth + 'px';
							rightPanel.style.flex = '1 1 ' + constrainedRightWidth + 'px';
							
							// Continue loop
							rafId = requestAnimationFrame(updateLoop);
						};
						
						rafId = requestAnimationFrame(updateLoop);
					};
					
					resizer.addEventListener('mousedown', handleStart);
					resizer.addEventListener('pointerdown', handleStart);
					
					// Update resizer hover color - buttonColor is available in outer scope
					resizer.addEventListener('mouseenter', function() {
						if (!isResizing) {
							this.style.backgroundColor = buttonColor;
						}
					});
					resizer.addEventListener('mouseleave', function() {
						if (!isResizing) {
							this.style.backgroundColor = '#ddd';
						}
					});
					
					return true;
				}
				
				// Setup resizer - try immediately and retry if needed
				if (!setupResizer()) {
					setTimeout(function() {
						setupResizer();
					}, 100);
				}
			});

			window.addEventListener('load', function () {
				updateFixedDiv(); 
			});

			window.addEventListener('resize', function() {
				updateFixedDiv();  
			});
		</script>";

		##################



	$tmp="../".$dir."/".$descFile.".desc";
	if (file_exists($tmp)) {
		system("cd ../temp/$db/pulje\ncp $tmp .\n");
		$fp=fopen("../temp/$db/pulje/$descFile.desc","r");
		while($linje=trim(fgets($fp))) {
			if (strtolower(substr($linje,0,6))=='bilag:') $bilag=trim(substr($linje,6));
			if (strtolower(substr($linje,0,5))=='dato:') $dato=trim(substr($linje,5));
			if (strtolower(substr($linje,0,12))=='beskrivelse:') $beskrivelse=trim(substr($linje,12));
			if (strtolower(substr($linje,0,6))=='debet:') $debet=trim(substr($linje,6));
			if (strtolower(substr($linje,0,7))=='kredit:') $kredit=trim(substr($linje,7));
			if (strtolower(substr($linje,0,10))=='fakturanr:') $fakturanr=trim(substr($linje,10));
			if (strtolower(substr($linje,0,4))=='sum:') $sum=trim(substr($linje,4));
			if (strtolower(substr($linje,0,4))=='sag:') $sag=trim(substr($linje,4));
			if (strtolower(substr($linje,0,4))=='afd:') $afd=trim(substr($linje,4));
			if (strtolower(substr($linje,0,8))==='projekt:') $projekt=trim(substr($linje,8));
		}
	}
	if ($source == 'kassekladde' && !$bilag && $bilag!='0') {
		$r=db_fetch_array(db_select("select max(bilag) as bilag from kassekladde where kladde_id='$sourceId'",__FILE__ . " linje " . __LINE__));
		$bilag=$r['bilag']+1;
	}
	#####get the corresponding .info content of the pdf file
	if ($fullName) {
		//Check if the fullname exists or already deleted:

			if (!file_exists($fullName)) {
			// Log the issue but DON'T auto-select another file - this was causing wrong .info file operations
			error_log("docPool: Selected file not found: $fullName. Clearing selection to prevent wrong file operations.");
			
			// Clear the selection to prevent operating on wrong file
			// User will need to select a file manually
			$poolFile = '';
			$fullName = null;
			
			// Note: Previously this code would auto-select ANY PDF which caused issues
			// with .info files being deleted for the wrong document
		}

		// Ensure it's a .pdf file
		if (strtolower(pathinfo($fullName, PATHINFO_EXTENSION)) === 'pdf') {
			$baseName = pathinfo($fullName, PATHINFO_FILENAME);
			$directory = dirname($fullName);
			$infoFile = "$directory/$baseName.info";

			if (file_exists($infoFile)) {
				// Check that it's not empty
				if (filesize($infoFile) > 0) {
					$lines = file($infoFile, FILE_IGNORE_NEW_LINES);

					// Default values
					$Subject = $lines[0] ?? '';
					$Account = $lines[1] ?? '';
					$Amount  = $lines[2] ?? '';
					$Date	= $lines[3] ?? '';
					$InvoiceNumber = $lines[4] ?? '';
					$InvoiceDescription = $lines[5] ?? '';
					
				} else {
					$Subject = if_isset($newSubject,NULL);
					$Account = if_isset($newAccount,NULL);
					$Amount  = if_isset($newAmount,NULL);
					$Date	= if_isset($newDate,NULL);
					$InvoiceNumber = if_isset($newInvoiceNumber,NULL);
					$InvoiceDescription = if_isset($newInvoiceDescription,NULL);	

				}
			} 
		} else {
			error_log("Invalid file extension (expected .pdf): $fullName");
		}
	}

	#####################################end .info part
	
	if (!$dato) $dato=date("d-m-Y");
     //#################
print <<<HTML

	<script>

	function updateSubjectFromFilename() {
		const fileInput = document.querySelector('input[name="newFileName"]');
		const subjectInput = document.querySelector('input[name="newSubject"]');

		if (fileInput) {
			// Always strip .pdf from what the user types
			let val = fileInput.value.replace(/\.pdf$/i, '');
			fileInput.value = val;

			if (subjectInput) {
				subjectInput.value = val.trim();
			}
		}
	}

	// On page load: remove .pdf from value (if set by server)
	document.addEventListener('DOMContentLoaded', function () {
		const fileInput = document.querySelector('input[name="newFileName"]');
		const subjectInput = document.querySelector('input[name="newSubject"]');

		if (fileInput) {
			fileInput.value = fileInput.value.replace(/\.pdf$/i, '');
			if (subjectInput) {
				// Don't auto-fill subject on load if it's already set (preserves existing subject)
				if (!subjectInput.value) {
					subjectInput.value = fileInput.value.trim();
				}
			}
		}
	});

	// Before form submit, add .pdf back
	document.querySelector('form')?.addEventListener('submit', function () {
		const fileInput = document.querySelector('input[name="newFileName"]');
		if (fileInput) {
			let val = fileInput.value.trim();
			if (!val.toLowerCase().endsWith('.pdf')) {
				fileInput.value = val + '.pdf';
			}
		}
	});


	function updateFilenameFromSubject() {
		const fileInput = document.querySelector('input[name="newFileName"]');
		const subjectInput = document.querySelector('input[name="newSubject"]');
		if (fileInput && subjectInput) {
			let filename = fileInput.value;
			let extension = "";

			// Extract extension if any
			const lastDot = filename.lastIndexOf(".");
			if (lastDot !== -1) {
				extension = filename.substring(lastDot);
			}
			
			// If extension is missing or not .pdf, ensure it's .pdf (since we work with PDFs)
			if (!extension || extension.toLowerCase() !== '.pdf') {
				extension = '.pdf';
			}

			// Always update filename's basename with the subject's value when subject changes
			// TRIM the subject value to avoid trailing spaces becoming underscores
			fileInput.value = subjectInput.value.trim() + extension;
		}
	}
	</script>



HTML;

			
			$useAlt = false;

			//##################
			print "<div id='activeRowContainer'></div>";
	
	if(!is_numeric($docFocus)) {
	print "<script language=\"javascript\">";
	if($docFocus != "" && $docFocus != "."){
		print "document.gennemse.$docFocus.focus();";
	}
	print "</script>";
	}

	file_put_contents($perfLog, sprintf("Time: %.4f - End of PHP execution\n", microtime(true) - $startTime), FILE_APPEND);
	exit;


	

} # endfunc gennemse
?>