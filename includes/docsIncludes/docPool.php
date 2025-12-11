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
	global $bruger_id,$db,$exec_path,$buttonStyle, $topStyle;
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
		
		// Handle multiple poolFiles - check for array first, then single value, then GET
		$poolFiles = array();
		if (isset($_POST['poolFile']) && is_array($_POST['poolFile'])) {
			$poolFiles = $_POST['poolFile'];
		} elseif (isset($_POST['poolFiles']) && !empty($_POST['poolFiles'])) {
			// Handle comma-separated string
			$poolFiles = explode(',', $_POST['poolFiles']);
			$poolFiles = array_map('trim', $poolFiles);
		} elseif (isset($_GET['poolFile']) && is_array($_GET['poolFile'])) {
			$poolFiles = $_GET['poolFile'];
		} elseif (isset($_GET['poolFiles']) && !empty($_GET['poolFiles'])) {
			$poolFiles = explode(',', $_GET['poolFiles']);
			$poolFiles = array_map('trim', $poolFiles);
		} elseif ($poolFile) {
			// Single file from function parameter
			$poolFiles = array($poolFile);
		} elseif (isset($_GET['poolFile']) && !empty($_GET['poolFile'])) {
			$poolFiles = array($_GET['poolFile']);
		} elseif (isset($_POST['poolFile']) && !empty($_POST['poolFile'])) {
			$poolFiles = array($_POST['poolFile']);
		}
		
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
		print "<table id='topBarHeader' width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		include("docsIncludes/topLineDocuments.php");
		print "</tbody></table>";
	} else {
		print "<table id='topBarHeader' width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" style=\"margin-bottom: 10px; margin-top: 10px;\"><tbody>";
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
			background-color: $buttonColor !important;
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
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" style=\"margin-bottom: 10px; margin-top: 10px;\"><tbody>";
	print "<tr>";
	print "<td style=\"background-color: $buttonColor; color: $buttonTxtColor; padding: 8px; border: 1px solid #ddd;\">";
	print "<font face=\"Helvetica, Arial, sans-serif\" style=\"font-weight: bold; font-size: 13px;\">" . findtekst('1408|Kassebilag', $sprog_id) . " - Nyt bilag #" . htmlspecialchars($bilag) . "</font>";
	print "</td></tr>";
	print "</tbody></table>";
}

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
            renderFiles();
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
					<th style="padding:8px; border:1px solid #ddd; text-align:center; width: 140px; color:${buttonTxtColor};">
						<span>Actions</span>
					</th>
				</tr>
			</thead>
			<tbody>
		`;


		let activeRows = '';
		let otherRows = '';

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

			const rowStyle = isMatch 
				? `border-bottom:1px solid #ddd; background-color:${lightButtonColor} !important; color:#000000 !important; font-weight:bold;`
				: "border-bottom:1px solid #ddd;";

			//let normalizedTotal = parseFloat(totalSum.replace(/\./g, '').replace(',', '.'));
			let normalizedTotal = parseFloat(totalSum?.replace(/\./g, '').replace(',', '.') || 0);
			let normalizedAmount = parseFloat(row.amount);
			let boldAmount = (normalizedAmount === normalizedTotal)
				? "<strong>" + escapeHTML(row.amount) + "</strong>"
				: escapeHTML(row.amount);

			// All cells start as non-editable (text)
			const subjectCell = "<span class='cell-content'>" + escapeHTML(row.subject) + "</span>";
			const accountCell = "<span class='cell-content'>" + escapeHTML(row.account) + "</span>";
			const amountCell = "<span class='cell-content'>" + boldAmount + "</span>";
			const dateCell = "<span class='cell-content'>" + dateFormatted + "</span>";

			// poolFileFromHref already set above
			const deleteUrl = row.href.replace(/poolFile=[^&]*/, '') + (row.href.includes('?') ? '&' : '?') + 'unlink=1&unlinkFile=' + encodeURIComponent(poolFileFromHref);
			
			const actionsCell = "<div style='display: flex; gap: 4px; justify-content: center; align-items: center; flex-wrap: wrap;'>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); enableRowEdit(this, \"" + escapeHTML(poolFileFromHref) + "\", \"" + escapeHTML(row.subject) + "\", \"" + escapeHTML(row.account) + "\", \"" + escapeHTML(row.amount) + "\", \"" + dateFormatted + "\"); return false;' style='padding: 4px 8px; background-color: " + buttonColor + "; color: " + buttonTxtColor + "; border: 1px solid " + buttonColor + "; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.opacity=\"0.9\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.opacity=\"1\"; this.style.transform=\"scale(1)\"' title='Rediger'>✏️</button>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); deletePoolFile(\"" + escapeHTML(poolFileFromHref) + "\", " + JSON.stringify(row.subject) + ", \"" + deleteUrl + "\"); return false;' style='padding: 4px 8px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#c82333\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#dc3545\"; this.style.transform=\"scale(1)\"' title='Slet'>🗑️</button>" +
				"</div>";

			// Check if this checkbox should be checked (restore from sessionStorage)
			const savedChecked = sessionStorage.getItem('docPool_checked_' + poolFileFromHref) === 'true';
			const checkedAttr = savedChecked ? ' checked' : '';
			
			const rowHTML = "<tr data-pool-file='" + escapeHTML(poolFileFromHref) + "' " + (isMatch ? "data-selected='true' " : "") + "style='" + rowStyle + " cursor: pointer;' onclick=\"if(!event.target.closest('button') && !event.target.closest('input')) { saveCheckboxState(); window.location.href='" + row.href + "'; }\">" +
				"<td style='padding:6px; border:1px solid #ddd; text-align:center; width: 40px;' onclick='event.stopPropagation();'><input type='checkbox' class='file-checkbox' value='" + escapeHTML(poolFileFromHref) + "'" + checkedAttr + " onchange='saveCheckboxState(); updateBulkButton();' onclick='event.stopPropagation();' style='cursor: pointer; width: 18px; height: 18px;'></td>" +
				"<td style='padding:6px; border:1px solid #ddd; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.subject) + "'>" + subjectCell + "</td>" +
				"<td style='padding:6px; border:1px solid #ddd; max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.account) + "'>" + accountCell + "</td>" +
				"<td style='padding:6px; border:1px solid #ddd; max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.amount) + "'>" + amountCell + "</td>" +
				"<td style='padding:6px; border:1px solid #ddd; max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" + escapeHTML(row.date) + "'>" + dateCell + "</td>" +
				"<td style='padding:4px; border:1px solid #ddd; text-align: center; width: 140px;' onclick='event.stopPropagation();'>" + actionsCell + "</td>" +
				"</tr>";


			if (isMatch) {
				activeRows += rowHTML;
			} else {
				otherRows += rowHTML;
			}
		}

		// Ensure active rows come first
		html += activeRows + otherRows;

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
			renderFiles();
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
		
		// Get form action URL
		const formAction = form.getAttribute('action');
		const url = new URL(formAction, window.location.href);
		
		// Remove existing poolFile parameters and add all selected files
		url.searchParams.delete('poolFile');
		selectedFiles.forEach((file, index) => {
			url.searchParams.append('poolFile[]', file);
		});
		
		// Also add as comma-separated for backward compatibility
		url.searchParams.set('poolFiles', selectedFiles.join(','));
		
		// Create FormData with all required fields
		const formData = new FormData();
		formData.append('insertFile', '1');
		
		// Add all selected files
		selectedFiles.forEach(file => {
			formData.append('poolFile[]', file);
		});
		formData.append('poolFiles', selectedFiles.join(','));
		
		// Add URL parameters to form data
		url.searchParams.forEach((value, key) => {
			formData.append(key, value);
		});
		
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
			"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); saveRowData(this); return false;' style='padding: 4px 8px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#218838\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#28a745\"; this.style.transform=\"scale(1)\"' title='Gem'>💾</button>" +
			"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); cancelRowEdit(this); return false;' style='padding: 4px 8px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#c82333\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#dc3545\"; this.style.transform=\"scale(1)\"' title='Annuller'>✕</button>" +
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
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); enableRowEdit(this, \"" + escapeHTML(poolFileFromRow) + "\", \"" + escapeHTML(data.newSubject) + "\", \"" + escapeHTML(data.newAccount) + "\", \"" + escapeHTML(data.newAmount) + "\", \"" + dateFormatted + "\"); return false;' style='padding: 4px 8px; background-color: " + buttonColor + "; color: " + buttonTxtColor + "; border: 1px solid " + buttonColor + "; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.opacity=\"0.9\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.opacity=\"1\"; this.style.transform=\"scale(1)\"' title='Rediger'>✏️</button>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); deletePoolFile(\"" + escapeHTML(poolFileFromRow) + "\", " + JSON.stringify(data.newSubject) + ", \"" + deleteUrl + "\"); return false;' style='padding: 4px 8px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#c82333\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#dc3545\"; this.style.transform=\"scale(1)\"' title='Slet'>🗑️</button>" +
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
	
	// Upload form (independent form, not nested)
	print "<form enctype='multipart/form-data' action='documents.php?$uploadParams' method='POST' style='margin: 0; padding: 0;'>";
	print "<input type='hidden' name='MAX_FILE_SIZE' value='100000000'>";
	print "<input type='hidden' name='insertFile' value='1'>";
	print "<div style='margin-bottom: 10px; padding: 8px; background-color: $buttonColor; border-radius: 8px; font-weight: 600; font-size: 14px; color: $buttonTxtColor; text-shadow: 0 1px 2px rgba(0,0,0,0.1);'>".findtekst(1414, $sprog_id).":</div>";
	print "<label for='fileUploadInput' style='display: block; width: 100%; margin-bottom: 12px; cursor: pointer;'>";
	print "<input id='fileUploadInput' class='inputbox' name='uploadedFile' type='file' accept='.pdf,.jpg,.png' style='width: 100%; height: auto; min-height: 40px; padding: 8px; border: 2px solid #ddd; border-radius: 8px; font-size: 12px; box-sizing: border-box; overflow: visible; background-color: #ffffff; transition: all 0.3s ease; pointer-events: auto; position: relative; z-index: 10; cursor: pointer;'>";
	print "</label>";
	print "<input type='submit' value='".findtekst(1078, $sprog_id)."' style='width: 100%; padding: 10px; margin-bottom: 12px; background-color: $buttonColor; color: $buttonTxtColor; border: 2px solid $buttonColor; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; box-sizing: border-box; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.2);'>";
	print "</form>";

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

	// Add JavaScript variables for drag and drop
	print "<script>
	var clipVariables = {
		sourceId: $sourceId,
		kladde_id: $kladde_id,
		bilag: $bilag,
		fokus: '$fokus',
		source: '$source'
	};
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
		print "<iframe style=\"width:100%;height:100%;border:none;overflow:hidden;\" src=\"$fullName\" frameborder=\"0\">";
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
