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
	global $bruger_id,$db,$exec_path;
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

	if ($insertFile && $poolFile) {
		#if(!isset($dato )&& isset($newDate)) {
			$formattedDate = date("d-m-Y", strtotime($newDate));
			$dato = $formattedDate;
			$_POST['dato']=$dato;
		#}
		
		// Ensure poolFile is available in insertDoc.php scope
		// It should already be available as a function parameter, but ensure it's set
		if (!isset($poolFile) || empty($poolFile)) {
			$poolFile = if_isset($_GET, NULL, 'poolFile');
			if (empty($poolFile)) {
				$poolFile = if_isset($_POST, NULL, 'poolFile');
			}
		}
		
		include ("docsIncludes/insertDoc.php");
#		Echo "Indsltter $poolFile<br>";
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
	#$poolFile = null;
	$latestTime = 0;
	if (!$poolFile) {
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

	// Add top banner with back button (like other pages)
	include("../includes/topline_settings.php");
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
	print "<table id='topBarHeader' width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" style=\"margin-bottom: 10px; margin-top: 10px;\"><tbody>";
	if ($menu=='S') {
		print "<tr>";
		print "<td width='10%' style='border-radius: 5px;'><a href='$backUrl' accesskey='L'><button style='$buttonStyle; width:100%; cursor: pointer;'>".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";
		print "<td width='80%' style='$topStyle' align='center'>".findtekst('1408|Kassebilag', $sprog_id)."</td>";
		print "<td width='10%' style='$topStyle' align='center'><br></td>";
		print "</tr>";
	} else {
		print "<tr>";
		print "<td width='10%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'><a href='$backUrl' accesskey='L' style='cursor: pointer;'>".findtekst('30|Tilbage', $sprog_id)."</a></td>";
		print "<td width='80%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'>".findtekst('1408|Kassebilag', $sprog_id)."</td>";
		print "<td width='10%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'><br></td>";
		print "</tr>";
	}
	print "</tbody></table>";
	
	// Add CSS to completely disable all hover effects on top bar (no visual changes at all)
	print "<style>
		/* Disable ALL hover effects on top bar - no color, opacity, or visual changes */
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
			opacity: 1 !important;
			transform: none !important;
			cursor: pointer !important;
			border-color: $buttonColor !important;
		}
		/* Disable hover effects on top bar links - no color or text changes */
		#topBarHeader tbody tr td a,
		#topBarHeader tbody tr td a:hover,
		#topBarHeader tbody tr td a:focus,
		#topBarHeader tbody tr td a:active {
			text-decoration: none !important;
			color: inherit !important;
			opacity: 1 !important;
		}
		/* Disable hover effects on top bar cells - maintain exact background color */
		#topBarHeader tbody tr,
		#topBarHeader tbody tr:hover,
		#topBarHeader tbody tr td,
		#topBarHeader tbody tr td:hover {
			background-color: $buttonColor !important;
			opacity: 1 !important;
		}
		/* Pointer cursor for back button only */
		#topBarHeader tbody tr td:first-child a {
			cursor: pointer !important;
		}
		#topBarHeader tbody tr td:first-child a button {
			cursor: pointer !important;
		}
	</style>";

	print "<form name=\"gennemse\" action=\"documents.php?$params&$poolParams\" method=\"post\">\n";
	print "<input type='hidden' id='hiddenSubject' name='newSubject' value=''>\n";
	print "<input type='hidden' id='hiddenAccount' name='newAccount' value=''>\n";
	print "<input type='hidden' id='hiddenAmount' name='newAmount' value=''>\n";
	print "<input type='hidden' id='hiddenDate' name='newDate' value=''>\n";

#####
// Modern flexbox layout instead of tables
// Adjust top position to account for header banner (approximately 50-60px)
print "<div id='docPoolContainer' style='display: flex; width: 100%; height: calc(100vh - 60px); gap: 0; margin: 0; padding: 0; position: fixed; top: 60px; left: 0;'>";
print "<div id='leftPanel' style='flex: 0 0 30%; min-width: 200px; max-width: 80%; display: flex; flex-direction: column; height: 100%; position: relative; margin: 0; padding: 0; overflow: visible;'>";
print "<div id='fileListContainer' style='flex: 1; overflow-y: auto; overflow-x: hidden; min-height: 0; width: 100%; margin: 0; padding: 0;'>Loading files...</div>";
// Fixed bottom section will be added here later via PHP (before leftPanel closes)

print "<style>
 html, body {
  margin: 0;
  padding: 0;
  height: 100%;
  overflow: hidden;
 }
 #docPoolContainer {
  display: flex;
  width: 100%;
  height: calc(100vh - 60px);
  gap: 0;
  margin: 0;
  padding: 0;
  position: fixed;
  top: 60px;
  left: 0;
  right: 0;
  bottom: 0;
 }
 #leftPanel {
  flex: 0 0 30%;
  min-width: 200px;
  max-width: 80%;
  display: flex;
  flex-direction: column;
  height: 100%;
  position: relative;
  margin: 0;
  padding: 0;
  overflow: visible;
 }
 #resizer {
  width: 5px;
  background-color: #ddd;
  cursor: col-resize;
  user-select: none;
  flex-shrink: 0;
  position: relative;
  touch-action: none;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  z-index: 100;
  pointer-events: auto;
 }
 #resizer:hover {
  background-color: #999;
 }
 #resizer::before {
  content: '';
  position: absolute;
  left: 50%;
  top: 0;
  bottom: 0;
  width: 1px;
  background-color: #999;
  transform: translateX(-50%);
 }
 #leftPanel, #rightPanel {
  will-change: flex;
 }
 #rightPanel {
  flex: 1;
  min-width: 200px;
  height: 100%;
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  overflow: hidden;
 }
 #documentViewer {
  flex: 1;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0;
  padding: 0;
  overflow: hidden;
 }
 #fileListContainer {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  min-height: 0;
  width: 100%;
  margin: 0;
  padding: 0;
  position: relative;
 }
 #fileListContainer > div {
  width: 100%;
 }
 #fixedCell {
  flex-shrink: 0;
  width: 100%;
  margin: 0;
  padding: 0;
  background: transparent;
 }
 #fixedBottom {
  width: 100%;
  box-sizing: border-box;
 }
</style>";



// $combinedParams = $params . '&' . $poolParams; 
$encodedDir = urlencode($dir);
$poolFileJs = json_encode($poolFile); // safely escapes quotes
$JsSum = json_encode($sum); // safely escapes quotes

// Get button colors for JavaScript
if (!isset($buttonColor)) {
	$qtxt = "select var_value from settings where var_name = 'buttonColor' and var_grp = 'colors' and user_id = '$bruger_id'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$buttonColor = $r["var_value"];
	} else {
		$buttonColor = '#114691'; // Default button color
	}
}
if (!isset($buttonTxtColor)) {
	$qtxt = "select var_value from settings where var_name = 'buttonTxtColor' and var_grp = 'colors' and user_id = '$bruger_id'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$buttonTxtColor = $r['var_value'];
	} else {
		$buttonTxtColor = '#ffffff'; // Default button text color
	}
}
$buttonColorJs = json_encode($buttonColor);
$buttonTxtColorJs = json_encode($buttonTxtColor);

print <<<JS
<script>
(() => {
    let docData = [];
    let currentSort = { field: 'date', asc: false };
    const containerId = 'fileListContainer';
	 const poolFile = {$poolFileJs};
	 const totalSum = {$JsSum};
	 const buttonColor = {$buttonColorJs};
	 const buttonTxtColor = {$buttonTxtColorJs};

	// Function to lighten a color
	function lightenColor(color, percent) {
		const num = parseInt(color.replace("#",""), 16);
		const amt = Math.round(2.55 * percent);
		const R = Math.min(255, (num >> 16) + amt);
		const G = Math.min(255, ((num >> 8) & 0x00FF) + amt);
		const B = Math.min(255, (num & 0x0000FF) + amt);
		return "#" + (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1);
	}

	const lightButtonColor = lightenColor(buttonColor, 60); // Lighten by 60%

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
			
		let html = `
  <div style="margin:0; padding-right:3px; width:100%; box-sizing:border-box;">
    <table style="border-collapse:collapse; width:100%; font-family:Arial, sans-serif; font-size:13px; border:1px solid #ddd; margin:0; padding:0; table-layout:fixed;">
      <thead style="background:${buttonColor}; color:${buttonTxtColor}; position:sticky; top:0; z-index:10; margin:0; padding:0;">
        <tr>
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

			const url = new URL(row.href, window.location.origin);
			const allPoolFiles = url.searchParams.getAll('poolFile');
			const lastPoolFile = allPoolFiles.length ? allPoolFiles[allPoolFiles.length - 1] : null;
			const isMatch = lastPoolFile === poolFile;

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

			// Extract poolFile from href for delete action
			const poolFileFromHref = lastPoolFile || '';
			const deleteUrl = row.href.replace(/poolFile=[^&]*/, '') + (row.href.includes('?') ? '&' : '?') + 'unlink=1&unlinkFile=' + encodeURIComponent(poolFileFromHref);
			
			const actionsCell = "<div style='display: flex; gap: 4px; justify-content: center; align-items: center; flex-wrap: wrap;'>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); chooseBilag(\"" + escapeHTML(poolFileFromHref) + "\"); return false;' style='padding: 4px 8px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#218838\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#28a745\"; this.style.transform=\"scale(1)\"' title='Choose/Insert'>✓</button>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); enableRowEdit(this, \"" + escapeHTML(poolFileFromHref) + "\", \"" + escapeHTML(row.subject) + "\", \"" + escapeHTML(row.account) + "\", \"" + escapeHTML(row.amount) + "\", \"" + dateFormatted + "\"); return false;' style='padding: 4px 8px; background-color: " + buttonColor + "; color: " + buttonTxtColor + "; border: 1px solid " + buttonColor + "; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.opacity=\"0.9\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.opacity=\"1\"; this.style.transform=\"scale(1)\"' title='Edit'>✏️</button>" +
				"<button type='button' onclick='event.preventDefault(); event.stopPropagation(); deletePoolFile(\"" + escapeHTML(poolFileFromHref) + "\", " + JSON.stringify(row.subject) + ", \"" + deleteUrl + "\"); return false;' style='padding: 4px 8px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; transition: all 0.2s;' onmouseover='this.style.backgroundColor=\"#c82333\"; this.style.transform=\"scale(1.05)\"' onmouseout='this.style.backgroundColor=\"#dc3545\"; this.style.transform=\"scale(1)\"' title='Delete'>🗑️</button>" +
				"</div>";

			const rowHTML = "<tr data-pool-file='" + escapeHTML(poolFileFromHref) + "' " + (isMatch ? "data-selected='true' " : "") + "style='" + rowStyle + " cursor: pointer;' onclick=\"if(!event.target.closest('button') && !event.target.closest('input')) { window.location.href='" + row.href + "'; }\">" +
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

			// hover style and edit input styles
			html += "<style>\
				table tbody tr:hover { background-color: " + lightButtonColor + " !important; }\
				table tbody tr[data-selected='true'] { background-color: " + lightButtonColor + " !important; color: #000000 !important; }\
				table tbody tr[data-selected='true'] td { color: #000000 !important; }\
				table tbody tr[data-selected='true']:hover { background-color: " + lightButtonColor + " !important; }\
				table tbody tr[data-selected='true']:hover td { color: #000000 !important; }\
				table tbody tr[data-editing='true'] { background-color: " + lightButtonColor + " !important; }\
				table tbody tr:hover td { background-color: transparent !important; }\
				.edit-input { width: 100%; border: 1px solid " + buttonColor + "; background: #fff; padding: 4px; font-family: inherit; font-size: inherit; box-sizing: border-box; }\
				.edit-input:focus { outline: 2px solid " + buttonColor + "; outline-offset: -1px; }\
				.cell-content { display: block; }\
			</style>";

			document.getElementById(containerId).innerHTML = html;
			
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
		const form = document.forms['gennemse'];
		if (!form) {
			alert('Form not found');
			return;
		}
		
		// Update form action URL to include the selected poolFile (like old version)
		const formAction = form.getAttribute('action');
		const url = new URL(formAction, window.location.href);
		
		// Update poolFile in URL parameters (matches old behavior where poolFile is in $poolParams)
		url.searchParams.set('poolFile', poolFile);
		
		// Update the form action with the new poolFile
		form.setAttribute('action', url.toString());
		
		// Create a hidden submit button with name="insertFile" (exactly like old version)
		// This mimics the old submit button: <input type="submit" name="insertFile" value="Indsæt">
		let insertFileButton = form.querySelector('input[name="insertFile"][type="submit"]');
		if (!insertFileButton) {
			insertFileButton = document.createElement('input');
			insertFileButton.type = 'submit';
			insertFileButton.name = 'insertFile';
			insertFileButton.style.display = 'none';
			form.appendChild(insertFileButton);
		}
		
		// Submit the form by clicking the button (matches old behavior exactly)
		// This will cause $_POST[insertFile] to be set when form is submitted
		// The poolFile in URL will be available as $_GET[poolFile] or function parameter
		insertFileButton.click();
	};
	
// Enable editing for a specific row
window.enableRowEdit = function(button, poolFile, subject, account, amount, date) {
	// Disable any other row that might be in edit mode
	const allRows = document.querySelectorAll('tr[data-editing="true"]');
	allRows.forEach(row => {
		const cells = row.querySelectorAll('td');
		if (cells.length >= 4) {
			// Restore original values
			const originalData = row.dataset.originalValues ? JSON.parse(row.dataset.originalValues) : {};
			cells[0].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.subject || '') + "</span>";
			cells[1].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.account || '') + "</span>";
			cells[2].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.amount || '') + "</span>";
			cells[3].innerHTML = "<span class='cell-content'>" + escapeHTML(originalData.date || '') + "</span>";
			row.removeAttribute('data-editing');
			delete row.dataset.originalValues;
		}
	});

	// Get the row for this button
	const row = button.closest('tr');
	if (!row) return;

	// Store original values
	row.dataset.originalValues = JSON.stringify({ subject, account, amount, date });
	row.setAttribute('data-editing', 'true');
	row.setAttribute('data-pool-file', poolFile);

	// Make cells editable
	const cells = row.querySelectorAll('td');
	if (cells.length >= 4) {
		const dateFormatted = date.split(' ')[0] || date;
		
		cells[0].innerHTML = "<input type='text' class='edit-input' value='" + escapeHTML(subject) + "' data-field='subject' onkeydown='handleEnterKey(event, this)' onclick='event.stopPropagation();'>";
		cells[1].innerHTML = "<input type='text' class='edit-input' value='" + escapeHTML(account) + "' data-field='account' onkeydown='handleEnterKey(event, this)' onclick='event.stopPropagation();'>";
		cells[2].innerHTML = "<input type='text' class='edit-input' value='" + escapeHTML(amount) + "' data-field='amount' onkeydown='handleEnterKey(event, this)' onclick='event.stopPropagation();'>";
		cells[3].innerHTML = "<input type='date' class='edit-input' value='" + dateFormatted + "' data-field='date' onkeydown='handleEnterKey(event, this)' onchange='saveRowData(this)' onclick='event.stopPropagation();'>";
		
		// Focus on first input
		setTimeout(() => cells[0].querySelector('input').focus(), 10);
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
			
			row.querySelector('td:nth-child(1)').innerHTML = "<span class='cell-content'>" + escapeHTML(data.newSubject) + "</span>";
			row.querySelector('td:nth-child(2)').innerHTML = "<span class='cell-content'>" + escapeHTML(data.newAccount) + "</span>";
			row.querySelector('td:nth-child(3)').innerHTML = "<span class='cell-content'>" + escapeHTML(data.newAmount) + "</span>";
			row.querySelector('td:nth-child(4)').innerHTML = "<span class='cell-content'>" + escapeHTML(dateFormatted) + "</span>";
			
			// Remove edit mode
			row.removeAttribute('data-editing');
			delete row.dataset.originalValues;
			
			// Update the data in docData array for future renders
			const poolFileFromRow = row.getAttribute('data-pool-file');
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

		// Add CSS for fixedBottom styling
		print "<style>
			#fixedBottom {
				max-width: 100%;
				overflow: visible;
			}
			#fixedBottom form {
				display: flex;
				flex-direction: column;
				overflow: visible;
			}
			#fixedBottom input[type='file'] {
				cursor: pointer;
				height: auto !important;
				min-height: 40px;
				overflow: visible !important;
				line-height: normal;
				pointer-events: auto !important;
				position: relative;
				z-index: 10;
			}
			#fixedBottom input[type='file']:hover {
				border-color: <?php echo $buttonColor; ?> !important;
				background-color: #ffffff !important;
				transform: translateY(-1px);
				box-shadow: 0 4px 12px rgba(0,0,0,0.15);
			}
			#fixedBottom input[type='file']::-webkit-file-upload-button {
				cursor: pointer;
				padding: 6px 12px;
				margin-right: 10px;
				background-color: <?php echo $buttonColor; ?>;
				color: <?php echo $buttonTxtColor; ?>;
				border: none;
				border-radius: 6px;
				font-weight: 600;
				transition: all 0.3s ease;
			}
			#fixedBottom input[type='file']::-webkit-file-upload-button:hover {
				transform: scale(1.05);
				box-shadow: 0 2px 8px rgba(0,0,0,0.2);
			}
			#fixedBottom input[type='submit'] {
				transition: all 0.3s ease;
			}
			#fixedBottom input[type='submit']:hover {
				transform: translateY(-2px);
				box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
				opacity: 0.9;
			}
			#fixedBottom input[type='submit']:active {
				transform: translateY(0);
			}
			#dropZone:hover {
				border-color: <?php echo $buttonColor; ?> !important;
				background-color: rgba(0,0,0,0.05) !important;
				transform: translateY(-2px);
				box-shadow: 0 4px 12px rgba(0,0,0,0.15);
			}
			#dropZone:hover #dropText {
				color: <?php echo $buttonColor; ?> !important;
			}
			.clip-image.drop-zone-container {
				display: block;
				width: 100%;
				margin: 0;
				padding: 0;
				text-align: center;
			}
			#dropZone {
				margin: 0 auto;
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
