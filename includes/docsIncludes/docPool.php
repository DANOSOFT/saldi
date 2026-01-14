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
function docPool($sourceId,$source,$kladde_id,$bilag,$fokus,$poolFile,$docFolder,$docFocus){
	global $bruger_id,$db,$exec_path,$buttonStyle, $topStyle, $butDownStyle;
	global $params,$regnaar,$sprog_id,$userId,$bgcolor, $bgcolor5, $buttonColor, $buttonTxtColor;
	
	$afd =  $beskrivelse = $debet = $dato = $fakturanr = $kredit = $projekt = $readOnly = $sag = $sum = NULL;

	((isset($_POST['unlink']) && $_POST['unlink']) || (isset($_GET['unlink']) && $_GET['unlink']))?$unlink=1:$unlink=0;
	(isset($_POST['rename']) && $_POST['rename'])?$rename=1:$rename=0;
	(isset($_POST['unlinkFile']) && $_POST['unlinkFile'])?$unlinkFile=$_POST['unlinkFile']:((isset($_GET['unlinkFile']) && $_GET['unlinkFile'])?$unlinkFile=$_GET['unlinkFile']:$unlinkFile=NULL);
	
	$insertFile   = if_isset($_POST,NULL,'insertFile');
	$newFileName  = if_isset($_POST,NULL,'newFileName');
	$descFile     = if_isset($_POST,NULL,'descFile');
	$newSubject   = if_isset($_POST,NULL,'newSubject');
	$newAccount	= if_isset($_POST,NULL,'newAccount');
	$newAmount	= if_isset($_POST,NULL,'newAmount');
	$newDate	   = if_isset($_POST,NULL,'newDate');

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
		#if(!isset($dato )&& isset($newDate)) {
			$formattedDate = date("d-m-Y", strtotime($newDate));
			$dato = $formattedDate;
			$_POST['dato']=$dato;
		#}
		
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

	if ($rename && $newFileName && $newFileName != $poolFile || ($rename &&($newAccount||$newAmount||$newSubject))) {
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
				

				$renamedPoolFile = $poolFile;

				if (!is_dir($puljePath)) {
					error_log("Directory does not exist: $puljePath");
				} else {
					$allFiles = scandir($puljePath);
					foreach ($allFiles as $file) {
						if (in_array($file, ['.', '..'])) continue;
						if(!in_array($newExt, ['pdf', 'info'])) continue;
						$fileBase = pathinfo($file, PATHINFO_FILENAME);
						$fileExt  = pathinfo($file, PATHINFO_EXTENSION);
						
						// Rename all files with the same base name (e.g., PDF and .info)
						if ($fileBase === $origBase) {
							$oldPath = "$puljePath/$file";
							$newPath = "$puljePath/$newBase.$fileExt";

							if (!file_exists($oldPath)) {
								error_log(" Skipped missing file: $oldPath");
								continue;
							}

							if (!is_writable(dirname($newPath))) {
								error_log(" Cannot write to: " . dirname($newPath)); 
								continue;
							}

							// Rename the file
							#if($oldPath != $newPath){
								if (rename($oldPath, $newPath)) {
									error_log("++*++ Renamed: $oldPath → $newPath");

									// Update the poolFile variable
									if ($file === $poolFile) {
										$renamedPoolFile = "$newBase.$fileExt";
									}
									// ✅ If this is the .info file, update subject, account, and amount
										if (strtolower($fileExt) === 'info') {
										// Set subject to newBase if it's empty
														if (empty($newSubject)) {
															$newSubject = $newBase; 
														}
		
											// Build new contents 
											$infoLines = [
												$newSubject ?? '',
												$newAccount ?? '',
												$newAmount ?? '',
												$newDate ?? ''
											]; 

											// Write to the file
											if (file_put_contents($newPath, implode(PHP_EOL, $infoLines) . PHP_EOL) !== false) {
												// Save to database
												// Check if pool_files table exists, create if not
												$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = '$db' AND table_name = 'pool_files'";
												if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
													$qtxt = "CREATE TABLE pool_files (
														id serial NOT NULL,
														filename varchar(255) NOT NULL,
														subject text,
														account varchar(50),
														amount varchar(50),
														file_date varchar(50),
														updated timestamp DEFAULT CURRENT_TIMESTAMP,
														PRIMARY KEY (id),
														UNIQUE(filename)
													)";
													db_modify($qtxt,__FILE__ . " linje " . __LINE__);
												}
												
												// Save or update in database
												$filename = $newBase . '.pdf';
												$qtxt = "SELECT id FROM pool_files WHERE filename = '". db_escape_string($filename) ."'";
												$existing = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
												
												if ($existing) {
													// Update existing record
													$qtxt = "UPDATE pool_files SET 
														subject = '". db_escape_string($newSubject ?? '') ."',
														account = '". db_escape_string($newAccount ?? '') ."',
														amount = '". db_escape_string($newAmount ?? '') ."',
														file_date = '". db_escape_string($newDate ?? '') ."',
														updated = CURRENT_TIMESTAMP
														WHERE filename = '". db_escape_string($filename) ."'";
													db_modify($qtxt,__FILE__ . " linje " . __LINE__);
												} else {
													// Insert new record
													$qtxt = "INSERT INTO pool_files (filename, subject, account, amount, file_date) VALUES (
														'". db_escape_string($filename) ."',
														'". db_escape_string($newSubject ?? '') ."',
														'". db_escape_string($newAccount ?? '') ."',
														'". db_escape_string($newAmount ?? '') ."',
														'". db_escape_string($newDate ?? '') ."'
													)";
													db_modify($qtxt,__FILE__ . " linje " . __LINE__);
												}
											} else {
												error_log("Failed to update .info file: $newPath");
											}
										}
									//
								} else {
									error_log("Rename failed: $oldPath → $newPath");
								}
						    #}
						}
					}

					// ✅ Example: Check if PDF exists before further use 
					$pdfPath = "$puljePath/$newBase.pdf";
					if (!file_exists($pdfPath)) {
						#error_log("⚠️ PDF file does not exist after renaming: $pdfPath");
					} else {
						// e.g. copy to saldibilag if needed
						$targetPath = str_replace('/bilag/', '/saldibilag/', $pdfPath); 
						
						//Remove only the first occurrence of '..'
						$targetPath = preg_replace('/\.\./', '', $targetPath, 1); 
						
						if (!file_exists($targetPath)) {
							if (copy($pdfPath, $targetPath)) {
								#error_log("✅ Copied PDF to: $targetPath");
							} else {
								error_log("Failed to copy PDF to: $targetPath");
							}
						}else{
							#error_log("File already exists: $targetPath"); 
						}
					}

					// ✅ Safe file deletion (instead of `rm '*'`)
					/*
					$cleanupFiles = glob("$puljePath/tmp_*"); // example pattern
					foreach ($cleanupFiles as $fileToRemove) {
						if (is_file($fileToRemove)) {
							unlink($fileToRemove);
							error_log("🗑️ Deleted: $fileToRemove");
						}
					}
					*/

					// Update poolFile to reflect renamed main file
					$poolFile = $renamedPoolFile;
				}
				
				// Handle case where we're updating metadata without renaming
				// If rename is set but filename hasn't changed, still update .info and database
				if ($rename && ($newAccount || $newAmount || $newSubject || $newDate) && $poolFile) {
					$puljePath = "$docFolder/$db/pulje";
					$baseName = pathinfo($poolFile, PATHINFO_FILENAME);
					$infoFile = "$puljePath/$baseName.info";
					
					if (file_exists($infoFile)) {
						// Update .info file
						$infoSubject = $newSubject ?? '';
						if (empty($infoSubject)) {
							$infoSubject = $baseName;
						}
						$infoLines = [
							$infoSubject,
							$newAccount ?? '',
							$newAmount ?? '',
							$newDate ?? ''
						];
						
						if (file_put_contents($infoFile, implode(PHP_EOL, $infoLines) . PHP_EOL) !== false) {
							// Save to database
							$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = '$db' AND table_name = 'pool_files'";
							if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
								$qtxt = "CREATE TABLE pool_files (
									id serial NOT NULL,
									filename varchar(255) NOT NULL,
									subject text,
									account varchar(50),
									amount varchar(50),
									file_date varchar(50),
									updated timestamp DEFAULT CURRENT_TIMESTAMP,
									PRIMARY KEY (id),
									UNIQUE(filename)
								)";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							}
							
							// Save or update in database
							$qtxt = "SELECT id FROM pool_files WHERE filename = '". db_escape_string($poolFile) ."'";
							$existing = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
							
							if ($existing) {
								// Update existing record
								$qtxt = "UPDATE pool_files SET 
									subject = '". db_escape_string($infoSubject) ."',
									account = '". db_escape_string($newAccount ?? '') ."',
									amount = '". db_escape_string($newAmount ?? '') ."',
									file_date = '". db_escape_string($newDate ?? '') ."',
									updated = CURRENT_TIMESTAMP
									WHERE filename = '". db_escape_string($poolFile) ."'";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							} else {
								// Insert new record
								$qtxt = "INSERT INTO pool_files (filename, subject, account, amount, file_date) VALUES (
									'". db_escape_string($poolFile) ."',
									'". db_escape_string($infoSubject) ."',
									'". db_escape_string($newAccount ?? '') ."',
									'". db_escape_string($newAmount ?? '') ."',
									'". db_escape_string($newDate ?? '') ."'
								)";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							}
						}
					}
				}

				// ✅ Prevent undefined variable warnings
				$modDate = $modDate ?? '';



		###############
	}
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
				$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = '$db' AND table_name = 'pool_files'";
				if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$filename = $unlinkFile;
					$qtxt = "DELETE FROM pool_files WHERE filename = '". db_escape_string($filename) ."'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}


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
		if (is_dir($dir)) {
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
	
	if ($menu=='S') {
		// Modern header - wrapped in table like other topLine files
		print "<table id='topBarHeader' width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" style=\"margin-top: 10px;\"><tbody>";
		include("docsIncludes/topLineDocuments.php");
		print "</tbody></table>";
	} elseif ($menu != 'T') {
		print "<table id='topBarHeader' width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" style=\"margin-bottom: 8px;\"><tbody>";
		print "<tr>";
		print "<td width='10%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'><a href='$backUrl' accesskey='L' style='cursor: pointer;'>".findtekst('30|Tilbage', $sprog_id)."</a></td>";
		print "<td width='80%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'>".findtekst('1408|Kassebilag', $sprog_id)."</td>";
		print "<td width='10%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'><br></td>";
		print "</tr>";
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
	// Include Font Awesome for icons
	print "<link href='https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css' rel='stylesheet'>\n";
	
	// Add dynamic CSS variables for button colors
	$lightButtonColor = brightenColor($buttonColor, 0.6);
	$butDownColor = brightenColor($buttonColor, 0.2);
	print "<style>
		:root {
			--docpool-primary: $buttonColor;
			--docpool-primary-text: $buttonTxtColor;
			--docpool-primary-light: $lightButtonColor;
		}
		/* Dynamic button color overrides for top bar - except the active middle button */
		#topBarHeader tbody tr td a button,
		#topBarHeader tbody tr td a button:hover,
		#topBarHeader tbody tr td a:hover button,
		#topBarHeader tbody tr td a:focus button,
		#topBarHeader tbody tr td a:active button,
		#topBarHeader tbody tr td button.center-btn,
		#topBarHeader tbody tr td button.center-btn:hover,
		#topBarHeader tbody tr td button.center-btn:focus,
		#topBarHeader tbody tr td button.center-btn:active {
			background-color: $buttonColor !important;
			color: $buttonTxtColor !important;
			border-color: $buttonColor !important;
		}
		/* Middle button (active page indicator) should be brighter */
		#topBarHeader tbody tr td button.headerbtn.navbtn-top,
		#topBarHeader tbody tr td button.headerbtn.navbtn-top:hover,
		#topBarHeader tbody tr td button.headerbtn.navbtn-top:focus,
		#topBarHeader tbody tr td button.headerbtn.navbtn-top:active {
			background-color: $butDownColor !important;
			color: $buttonTxtColor !important;
			border-color: $butDownColor !important;
		}
		#topBarHeader tbody tr,
		#topBarHeader tbody tr:hover,
		#topBarHeader tbody tr td,
		#topBarHeader tbody tr td:hover {
			background-color: $buttonColor !important;
		}
	</style>";

	print "<form name=\"gennemse\" action=\"documents.php?$params&$poolParams\" method=\"post\">\n";
	print "<input type='hidden' id='hiddenSubject' name='newSubject' value=''>\n";
	print "<input type='hidden' id='hiddenAccount' name='newAccount' value=''>\n";
	print "<input type='hidden' id='hiddenAmount' name='newAmount' value=''>\n";
	print "<input type='hidden' id='hiddenDate' name='newDate' value=''>\n";

#####
// Modern flexbox layout instead of tables
// Styles are now in docpool.css
print "<div id='docPoolContainer'>";
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
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" style=\"margin-bottom: 10px; margin-top: 10px; margin-top: 8px;\"><tbody>";
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
// View mode toggle
print "<div style='display: flex; gap: 4px;'>";
print "<button type='button' id='tableViewBtn' onclick='setViewMode(\"table\")' title='Tabelvisning' style='padding: 8px 12px; background-color: $buttonColor; color: $buttonTxtColor; border: none; border-radius: 4px 0 0 4px; cursor: pointer; font-size: 14px;'><i class='fa fa-table'></i></button>";
print "<button type='button' id='cardViewBtn' onclick='setViewMode(\"card\")' title='Kortvisning' style='padding: 8px 12px; background-color: #e9ecef; color: #495057; border: none; border-radius: 0 4px 4px 0; cursor: pointer; font-size: 14px;'><i class='fa fa-th-large'></i></button>";
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
					leftPanel.style.maxWidth = '50%';
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
							<span>Subject</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th onclick="sortFiles('account')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left; color:${buttonTxtColor};">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Account</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th onclick="sortFiles('amount')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left; color:${buttonTxtColor};">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Amount</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th onclick="sortFiles('date')" style="cursor:pointer; padding:8px; border:1px solid #ddd; text-align:left; color:${buttonTxtColor};">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>Date</span>
							<span>&#9660;</span>
						</div>
					</th>
					<th style="padding:8px; border:1px solid #ddd; text-align:center; width: 90px; color:${buttonTxtColor};">
						<span>Actions</span>
					</th>
				</tr>
			</thead>
			<tbody>
		`;


		let activeRows = '';
		let matchingAmountRows = '';
		let combinationRows = '';
		let otherRows = '';
		
		// Normalize the total sum for comparison
		const normalizedTotal = parseFloat(totalSum?.replace(/\./g, '').replace(',', '.') || 0);
		const hasAmountToMatch = normalizedTotal !== 0 && !isNaN(normalizedTotal);
		
		// First pass: count matching documents and find combinations
		let matchingCount = 0;
		let exactMatches = []; // Store filenames that are exact matches
		let combinationMatches = new Set(); // Store filenames that are part of a combination
		let combinationGroups = []; // Store the actual combinations found
		
		if (hasAmountToMatch) {
			// Build list of documents with valid amounts
			const docsWithAmounts = [];
			for (let i = 0; i < docData.length; i++) {
				const row = docData[i];
				const normalizedAmount = parseFloat(row.amount);
				if (!isNaN(normalizedAmount) && normalizedAmount > 0) {
					const filename = row.filename || '';
					docsWithAmounts.push({
						index: i,
						filename: filename,
						amount: normalizedAmount,
						row: row
					});
					// Count exact matches and store filenames
					if (Math.abs(normalizedAmount - normalizedTotal) < 0.01) {
						matchingCount++;
						exactMatches.push(filename);
					}
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

		for (const row of docData) {
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
			
			// Check if this row is part of a combination match
			const isCombinationMatch = hasCombinationMatches && combinationMatches.has(poolFileFromHref);

			// Determine row style based on selection and amount match
			let rowStyle = "border-bottom:1px solid #ddd;";
			if (isMatch) {
				rowStyle = `border-bottom:1px solid #ddd; background-color:${lightButtonColor} !important; color:#000000 !important; font-weight:bold;`;
			} else if (isAmountMatch) {
				// Green-tinted background for exact amount matches
				rowStyle = "border-bottom:1px solid #ddd; background-color:#d4edda !important;";
			} else if (isCombinationMatch) {
				// Amber/orange-tinted background for combination matches
				rowStyle = "border-bottom:1px solid #ddd; background-color:#fff3cd !important;";
			}

			// Format amount with match indicator
			let amountDisplay = escapeHTML(row.amount);
			if (isAmountMatch) {
				amountDisplay = "<span style='color: #155724; font-weight: bold;'><i class='fa fa-check-circle' style='margin-right: 4px; color: #28a745;'></i>" + escapeHTML(row.amount) + "</span>";
			} else if (isCombinationMatch) {
				amountDisplay = "<span style='color: #856404; font-weight: bold;'><i class='fa fa-plus-circle' style='margin-right: 4px; color: #ffc107;'></i>" + escapeHTML(row.amount) + "</span>";
			}

			// All cells start as non-editable (text)
			const subjectCell = "<span class='cell-content'>" + escapeHTML(row.subject) + "</span>";
			const accountCell = "<span class='cell-content'>" + escapeHTML(row.account) + "</span>";
			const amountCell = "<span class='cell-content'>" + amountDisplay + "</span>";
			const dateCell = "<span class='cell-content'>" + dateFormatted + "</span>";

			// poolFileFromHref already set above
			const deleteUrl = row.href.replace(/poolFile=[^&]*/, '') + (row.href.includes('?') ? '&' : '?') + 'unlink=1&unlinkFile=' + encodeURIComponent(poolFileFromHref);
			
			const actionsCell = "<div style='display: flex; gap: 4px; justify-content: center; align-items: center; flex-wrap: wrap;'>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); enableRowEdit(this, \"" + escapeHTML(poolFileFromHref) + "\", \"" + escapeHTML(row.subject) + "\", \"" + escapeHTML(row.account) + "\", \"" + escapeHTML(row.amount) + "\", \"" + dateFormatted + "\"); return false;' style='padding: 4px 8px; background-color: " + buttonColor + "; color: " + buttonTxtColor + "; border: 1px solid " + buttonColor + "; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.opacity=\"0.9\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.opacity=\"1\"; this.style.transform=\"scale(1)\"' title='Rediger'><i class='fa fa-pencil' style='font-size: 12px;'></i></button>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); deletePoolFile(\"" + escapeHTML(poolFileFromHref) + "\", " + JSON.stringify(row.subject) + ", \"" + deleteUrl + "\"); return false;' style='padding: 4px 8px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#c82333\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#dc3545\"; this.style.transform=\"scale(1)\"' title='Slet'><i class='fa fa-trash-o' style='font-size: 12px;'></i></button>" +
				"</div>";

			// Check if this checkbox should be checked (restore from sessionStorage)
			const savedChecked = sessionStorage.getItem('docPool_checked_' + poolFileFromHref) === 'true';
			const checkedAttr = savedChecked ? ' checked' : '';
			
			const dataAttrs = "data-pool-file='" + escapeHTML(poolFileFromHref) + "' " + (isMatch ? "data-selected='true' " : "") + (isAmountMatch ? "data-amount-match='true' " : "") + (isCombinationMatch ? "data-combination-match='true' " : "");
			const rowHTML = "<tr " + dataAttrs + "style='" + rowStyle + " cursor: pointer;' onclick=\"if(!event.target.closest('button') && !event.target.closest('input')) { saveCheckboxState(); window.location.href='" + row.href + "'; }\">" +
				"<td style='padding:6px; border:1px solid #ddd; text-align:center; width: 40px;' onclick='event.stopPropagation();'><input type='checkbox' class='file-checkbox' value='" + escapeHTML(poolFileFromHref) + "'" + checkedAttr + " onchange='saveCheckboxState(); updateBulkButton();' onclick='event.stopPropagation();' style='cursor: pointer; width: 18px; height: 18px;'></td>" +
				"<td style='padding:6px; border:1px solid #ddd; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.subject) + "'>" + subjectCell + "</td>" +
				"<td style='padding:6px; border:1px solid #ddd; max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.account) + "'>" + accountCell + "</td>" +
				"<td style='padding:6px; border:1px solid #ddd; max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.amount) + "'>" + amountCell + "</td>" +
				"<td style='padding:6px; border:1px solid #ddd; max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.date) + "'>" + dateCell + "</td>" +
				"<td style='padding:4px; border:1px solid #ddd; text-align: center; width: 140px;' onclick='event.stopPropagation();'>" + actionsCell + "</td>" +
				"</tr>";
			if (isMatch) {
				activeRows += rowHTML;
			} else if (isAmountMatch) {
				matchingAmountRows += rowHTML;
			} else if (isCombinationMatch) {
				combinationRows += rowHTML;
			} else {
				otherRows += rowHTML;
			}
		}

		// Add section header for exact matching amounts
		let matchingHeader = '';
		if (hasAmountToMatch && matchingCount > 0 && matchingAmountRows) {
			const exactFilesJson = JSON.stringify(exactMatches).replace(/'/g, "&#39;");
			matchingHeader = "<tr style='background-color: #28a745; color: white; cursor: pointer;' onclick='selectCombinationFiles(" + exactFilesJson + ")' title='Klik for at vælge alle bilag med eksakt match'>" +
				"<td colspan='6' style='padding: 8px 12px; font-weight: bold; font-size: 12px; border: 1px solid #28a745;'>" +
				"<i class='fa fa-check-circle' style='margin-right: 6px;'></i>" +
				"Eksakt match (beløb: " + escapeHTML(totalSum) + ") - " + matchingCount + " fundet" +
				" <span style='font-weight: normal; font-size: 11px; float: right;'><i class='fa fa-hand-pointer-o'></i> Klik for at vælge</span>" +
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
				"<i class='fa fa-plus-circle' style='margin-right: 6px;'></i>" +
				"Kombination fundet (" + combinationMatches.size + " bilag giver: " + escapeHTML(totalSum) + ")" +
				(comboDesc ? " <span style='font-weight: normal; font-size: 11px;'>(" + comboDesc + ")</span>" : "") +
				" <span style='font-weight: normal; font-size: 11px; float: right;'><i class='fa fa-hand-pointer-o'></i> Klik for at vælge</span>" +
				"</td></tr>";
		}

		// Ensure active rows come first, then exact matches, then combination matches, then others
		html += activeRows + matchingHeader + matchingAmountRows + combinationHeader + combinationRows + otherRows;

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
				table tbody tr[data-amount-match='true'] { background-color: #d4edda !important; }\
				table tbody tr[data-amount-match='true']:hover { background-color: #c3e6cb !important; }\
				table tbody tr[data-amount-match='true'] td { color: #155724 !important; }\
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
			
			// Amount matching logic (reuse from renderFiles)
			const normalizedTotal = parseFloat(totalSum?.replace(/\\./g, '').replace(',', '.') || 0);
			const hasAmountToMatch = normalizedTotal !== 0 && !isNaN(normalizedTotal);
			let exactMatches = [];
			let combinationMatches = new Set();
			let combinationGroups = [];
			
			if (hasAmountToMatch) {
				const docsWithAmounts = [];
				for (let i = 0; i < docData.length; i++) {
					const row = docData[i];
					const normalizedAmount = parseFloat(row.amount);
					if (!isNaN(normalizedAmount) && normalizedAmount > 0) {
						const filename = row.filename || '';
						docsWithAmounts.push({
							index: i,
							filename: filename,
							amount: normalizedAmount,
							row: row
						});
						if (Math.abs(normalizedAmount - normalizedTotal) < 0.01) {
							exactMatches.push(filename);
						}
					}
				}
				
				// Find combinations if no exact matches
				if (exactMatches.length === 0 && docsWithAmounts.length >= 2) {
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
			
			// Exact match header if applicable
			if (hasAmountToMatch && exactMatches.length > 0) {
				const exactFilesJson = JSON.stringify(exactMatches).replace(/'/g, "&#39;");
				html += '<div onclick="selectCombinationFiles(' + exactFilesJson + ')" style="cursor: pointer; padding: 10px; background: #28a745; color: white; border-radius: 6px; margin-bottom: 8px;">';
				html += '<i class="fa fa-check-circle" style="margin-right: 6px;"></i>';
				html += '<strong>Eksakt match</strong> - ' + exactMatches.length + ' bilag matcher beløbet ' + escapeHTML(totalSum);
				html += ' <span style="float: right; font-size: 11px;"><i class="fa fa-hand-pointer-o"></i> Klik for at vælge</span>';
				html += '</div>';
			}
			
			// Combination match header if applicable
			if (combinationMatches.size > 0 && combinationGroups.length > 0) {
				const comboFilesJson = JSON.stringify(combinationGroups[0].files).replace(/'/g, "&#39;");
				html += '<div onclick="selectCombinationFiles(' + comboFilesJson + ')" style="cursor: pointer; padding: 10px; background: #ffc107; color: #212529; border-radius: 6px; margin-bottom: 8px;">';
				html += '<i class="fa fa-plus-circle" style="margin-right: 6px;"></i>';
				html += '<strong>Kombination fundet</strong> - ' + combinationMatches.size + ' bilag giver tilsammen ' + escapeHTML(totalSum);
				html += ' <span style="float: right; font-size: 11px;"><i class="fa fa-hand-pointer-o"></i> Klik for at vælge</span>';
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
				const isAmountMatch = exactMatches.includes(filename);
				const isCombinationMatch = combinationMatches.has(filename);
				
				// Card styling based on state
				let cardStyle = 'display: flex; align-items: flex-start; gap: 12px; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.2s;';
				if (isSelected) {
					cardStyle += ' background: ' + lightButtonColor + '; border-color: ' + buttonColor + ';';
				} else if (isAmountMatch) {
					cardStyle += ' background: #d4edda; border-color: #28a745;';
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
				html += '<div style="flex-shrink: 0; font-size: 32px; color: ' + buttonColor + ';">';
				html += '<i class="fa fa-file-pdf-o"></i>';
				html += '</div>';
				
				// Content
				html += '<div style="flex: 1; min-width: 0;">';
				html += '<div style="font-weight: bold; font-size: 14px; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="' + escapeHTML(subject) + '">' + escapeHTML(subject) + '</div>';
				html += '<div style="font-size: 12px; color: #666; display: flex; flex-wrap: wrap; gap: 8px;">';
				if (account) html += '<span><strong>Konto:</strong> ' + escapeHTML(account) + '</span>';
				if (amount) {
					let amountHtml = '<span><strong>Beløb:</strong> ';
					if (isAmountMatch) {
						amountHtml += '<span style="color: #28a745;"><i class="fa fa-check-circle"></i> ' + escapeHTML(amount) + '</span>';
					} else if (isCombinationMatch) {
						amountHtml += '<span style="color: #ffc107;"><i class="fa fa-plus-circle"></i> ' + escapeHTML(amount) + '</span>';
					} else {
						amountHtml += escapeHTML(amount);
					}
					amountHtml += '</span>';
					html += amountHtml;
				}
				if (dateFormatted) html += '<span><strong>Dato:</strong> ' + escapeHTML(dateFormatted) + '</span>';
				html += '</div>';
				html += '</div>';
				
				// Actions
				html += '<div class="card-actions" style="flex-shrink: 0; display: flex; gap: 4px;" onclick="event.stopPropagation();">';
				html += '<button type="button" onclick="event.preventDefault(); event.stopPropagation(); enableCardEdit(\\'' + escapeHTML(filename) + '\\', \\'' + escapeHTML(subject) + '\\', \\'' + escapeHTML(account) + '\\', \\'' + escapeHTML(amount) + '\\', \\'' + escapeHTML(dateFormatted) + '\\'); return false;" style="padding: 6px 10px; background: ' + buttonColor + '; color: ' + buttonTxtColor + '; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;" title="Rediger"><i class="fa fa-pencil"></i></button>';
				html += '<button type="button" onclick="event.preventDefault(); event.stopPropagation(); deletePoolFile(\\'' + escapeHTML(filename) + '\\', ' + JSON.stringify(subject) + ', \\'' + escapeHTML(deleteUrl) + '\\'); return false;" style="padding: 6px 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;" title="Slet"><i class="fa fa-trash-o"></i></button>';
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
window.enableRowEdit = function(button, poolFile, subject, account, amount, date) {
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
			cells[4].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.date || '') + "</span>";
			// Restore original actions
			if (row.dataset.originalActions) {
				cells[5].innerHTML = row.dataset.originalActions;
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
	const originalActions = cells.length >= 6 ? cells[5].innerHTML : '';
	row.dataset.originalValues = JSON.stringify({ subject, account, amount, date });
	row.dataset.originalActions = originalActions;
	row.setAttribute('data-editing', 'true');
	row.setAttribute('data-pool-file', poolFile);

	// Make cells editable (skip checkbox column which is cells[0])
	if (cells.length >= 6) {
		const dateFormatted = date.split(' ')[0] || date;
		
		// cells[0] is checkbox, cells[1-4] are data columns, cells[5] is actions
		cells[1].innerHTML = "<input type='text' class='edit-input' value='" + escapeHTML(subject) + "' data-field='subject' onkeydown='handleEnterKey(event, this)' onclick='event.stopPropagation();'>";
		cells[2].innerHTML = "<input type='text' class='edit-input' value='" + escapeHTML(account) + "' data-field='account' onkeydown='handleEnterKey(event, this)' onclick='event.stopPropagation();'>";
		cells[3].innerHTML = "<input type='text' class='edit-input' value='" + escapeHTML(amount) + "' data-field='amount' onkeydown='handleEnterKey(event, this)' onclick='event.stopPropagation();'>";
		cells[4].innerHTML = "<input type='date' class='edit-input' value='" + dateFormatted + "' data-field='date' onkeydown='handleEnterKey(event, this)' onchange='saveRowData(this)' onclick='event.stopPropagation();'>";
		
		// Update actions column with only save (green) and cancel (red) buttons when editing
		cells[5].innerHTML = "<div style='display: flex; gap: 4px; justify-content: center; align-items: center; flex-wrap: wrap;'>" +
			"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); saveRowData(this); return false;' style='padding: 4px 8px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#218838\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#28a745\"; this.style.transform=\"scale(1)\"' title='Gem'><i class='fa fa-save' style='font-size: 12px;'></i></button>" +
			"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); cancelRowEdit(this); return false;' style='padding: 4px 8px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#c82333\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#dc3545\"; this.style.transform=\"scale(1)\"' title='Annuller'><i class='fa fa-times' style='font-size: 12px;'></i></button>" +
			"</div>";
		
		// Focus on first input (subject)
		setTimeout(() => cells[1].querySelector('input').focus(), 10);
	}
};

// Cancel editing and restore original values
window.cancelRowEdit = function(button) {
	const row = button.closest('tr[data-editing="true"]');
	if (!row) return;
	
	const cells = row.querySelectorAll('td');
	if (cells.length >= 6) {
		// Restore original values (skip checkbox column which is cells[0])
		const originalData = row.dataset.originalValues ? JSON.parse(row.dataset.originalValues) : {};
		cells[1].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.subject || '') + "</span>";
		cells[2].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.account || '') + "</span>";
		cells[3].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.amount || '') + "</span>";
		cells[4].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.date || '') + "</span>";
		// Restore original actions
		if (row.dataset.originalActions) {
			cells[5].innerHTML = row.dataset.originalActions;
		}
		row.removeAttribute('data-editing');
		delete row.dataset.originalValues;
		delete row.dataset.originalActions;
	}
	
	// Update bulk button state
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
		newDate: ''
	};

	inputs.forEach(input => {
		const field = input.getAttribute('data-field');
		if (field === 'subject') data.newSubject = input.value;
		else if (field === 'account') data.newAccount = input.value;
		else if (field === 'amount') data.newAmount = input.value;
		else if (field === 'date') data.newDate = input.value;
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
		url: url.toString()
	});

	// Send AJAX request
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
			row.querySelector('td:nth-child(2)').innerHTML = "<span class='cell-content'>" + escapeHTML(data.newSubject) + "</span>";
			row.querySelector('td:nth-child(3)').innerHTML = "<span class='cell-content'>" + escapeHTML(data.newAccount) + "</span>";
			row.querySelector('td:nth-child(4)').innerHTML = "<span class='cell-content'>" + escapeHTML(data.newAmount) + "</span>";
			row.querySelector('td:nth-child(5)').innerHTML = "<span class='cell-content'>" + escapeHTML(dateFormatted) + "</span>";
			
			// Restore actions column with updated values
			const poolFileFromRow = row.getAttribute('data-pool-file');
			const currentUrl = window.location.href;
			const deleteUrl = currentUrl.replace(/poolFile=[^&]*/, '') + (currentUrl.includes('?') ? '&' : '?') + 'unlink=1&unlinkFile=' + encodeURIComponent(poolFileFromRow);
			
			const actionsCell = "<div style='display: flex; gap: 4px; justify-content: center; align-items: center; flex-wrap: wrap;'>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); enableRowEdit(this, \"" + escapeHTML(poolFileFromRow) + "\", \"" + escapeHTML(data.newSubject) + "\", \"" + escapeHTML(data.newAccount) + "\", \"" + escapeHTML(data.newAmount) + "\", \"" + dateFormatted + "\"); return false;' style='padding: 4px 8px; background-color: " + buttonColor + "; color: " + buttonTxtColor + "; border: 1px solid " + buttonColor + "; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.opacity=\"0.9\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.opacity=\"1\"; this.style.transform=\"scale(1)\"' title='Rediger'><i class='fa fa-pencil' style='font-size: 12px;'></i></button>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); deletePoolFile(\"" + escapeHTML(poolFileFromRow) + "\", " + JSON.stringify(data.newSubject) + ", \"" + deleteUrl + "\"); return false;' style='padding: 4px 8px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#c82333\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#dc3545\"; this.style.transform=\"scale(1)\"' title='Slet'><i class='fa fa-trash-o' style='font-size: 12px;'></i></button>" +
				"</div>";
			
			row.querySelector('td:nth-child(6)').innerHTML = actionsCell;
			
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
	print "<span style='font-weight: 600; font-size: 13px;'><i class='fa fa-upload' style='margin-right: 6px;'></i>".findtekst(1414, $sprog_id)."</span>";
	print "<i id='uploadToggleIcon' class='fa fa-chevron-down' style='font-size: 12px; transition: transform 0.3s;'></i>";
	print "</div>";
	
	// Collapsible upload content
	print "<div id='uploadContent' style='overflow: hidden; transition: max-height 0.3s ease, opacity 0.3s ease;'>";
	
	// Upload form (independent form, not nested) - uses AJAX like drag and drop
	print "<form id='fileUploadForm' enctype='multipart/form-data' action='documents.php?$uploadParams' method='POST' style='margin: 0; padding: 0;'>";
	print "<input type='hidden' name='MAX_FILE_SIZE' value='100000000'>";
	print "<input type='hidden' name='openPool' value='1'>";
	print "<label for='fileUploadInput' style='display: block; width: 100%; margin-bottom: 12px; cursor: pointer;'>";
	print "<input id='fileUploadInput' class='inputbox' name='uploadedFile' type='file' accept='.pdf,.jpg,.jpeg,.png' style='width: 100%; height: auto; min-height: 40px; padding: 8px; border: 2px solid #ddd; border-radius: 8px; font-size: 12px; box-sizing: border-box; overflow: visible; background-color: #ffffff; transition: all 0.3s ease; pointer-events: auto; position: relative; z-index: 10; cursor: pointer;'>";
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
					alert('Please select a file first.');
					return;
				}
				
				var file = fileInput.files[0];
				
				// Check file type
				var allowedExtensions = ['.pdf', '.jpg', '.jpeg', '.png'];
				var fileName = file.name.toLowerCase();
				var isAllowed = allowedExtensions.some(function(ext) {
					return fileName.endsWith(ext);
				});
				
				if (!isAllowed) {
					alert('Please select a PDF or image file (jpg, png).');
					return;
				}
				
				// Show loading state
				var originalBtnText = submitBtn.innerHTML;
				submitBtn.innerHTML = '<i class=\"fa fa-spinner fa-spin\" style=\"margin-right: 6px;\"></i> Uploader og analyserer...';
				submitBtn.disabled = true;
				submitBtn.style.opacity = '0.7';
				fileInput.disabled = true;
				
				// Create FormData
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
				
				// Determine URL
				var currentPath = window.location.pathname;
				var uploadUrl = currentPath.indexOf('/includes/') !== -1 ? 'documents.php' : '../includes/documents.php';
				
				// Send via fetch
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
					// Reset button
					submitBtn.innerHTML = originalBtnText;
					submitBtn.disabled = false;
					submitBtn.style.opacity = '1';
					fileInput.disabled = false;
					fileInput.value = '';
					
					if (data && data.success) {
						var message = '✓ Upload successful: ' + data.filename;
						if (data.extracted) {
							if (data.extracted.amount) message += '\\nAmount: ' + data.extracted.amount;
							if (data.extracted.date) message += '\\nDate: ' + data.extracted.date;
						}
						alert(message);
						
						// Redirect to focus on the uploaded file
						var currentUrl = new URL(window.location.href);
						currentUrl.searchParams.set('poolFile', data.filename);
						currentUrl.searchParams.set('openPool', '1');
						window.location.href = currentUrl.toString();
					} else {
						alert('Error: ' + (data && data.message ? data.message : 'Upload failed'));
					}
				})
				.catch(function(error) {
					// Reset button on error
					submitBtn.innerHTML = originalBtnText;
					submitBtn.disabled = false;
					submitBtn.style.opacity = '1';
					fileInput.disabled = false;
					
					console.error('Upload error:', error);
					alert('Error uploading file: ' + error.message);
				});
			});
		}
	});
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
		print "<i class='fa fa-link' style='margin-right: 4px;'></i> Link bilag fra anden linje";
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
			icon.style.transform = 'rotate(0deg)';
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
			icon.style.transform = 'rotate(-90deg)';
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
	if (!$ext) {
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
			
				
				// Attempt to get the first .pdf file in the same directory
				$directory = dirname($fullName);
				$pdfFiles = glob($directory . '/*.pdf');

				if (!empty($pdfFiles)) {
					$fullName = $pdfFiles[0]; // First PDF found 

					$poolFile = basename($pdfFiles[0]);
					
				} else {
					error_log("No PDF files found in directory: $directory");
					$fullName = null; // Optional: unset it if nothing is found
				}
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
					
				} else {
					$Subject = if_isset($newSubject,NULL);
					$Account = if_isset($newAccount,NULL);
					$Amount  = if_isset($newAmount,NULL);
					$Date	= if_isset($newDate,NULL);	

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
			fileInput.value = fileInput.value.replace(/\.pdf$/i, '');

			if (subjectInput) {
				subjectInput.value = fileInput.value.trim();
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
				subjectInput.value = fileInput.value.trim();
			}
		}
	});

	// Before form submit, add .pdf back
	document.querySelector('form')?.addEventListener('submit', function () {
		const fileInput = document.querySelector('input[name="newFileName"]');
		if (fileInput && !fileInput.value.endsWith('.pdf')) {
			fileInput.value = fileInput.value.trim() + '.pdf';
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

			// Always update filename's basename with the subject's value when subject changes
			fileInput.value = subjectInput.value + extension;
		}
	}
	</script>



HTML;

			
			$useAlt = false;

			//##################
			print "<div id='activeRowContainer'></div>";
	
	if(!is_numeric($docFocus)) {
	print "<script language=\"javascript\">";
	print "document.gennemse.$docFocus.focus();";
	print "</script>";
	}

	exit;


	

} # endfunc gennemse
?>
