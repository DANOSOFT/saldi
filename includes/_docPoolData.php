<?php
#Begin afresh
// Prevent caching of this JSON response to ensure fresh file list
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

ob_clean();
ob_start();



    $dir = $_GET['dir'] ?? null;
    $params = isset($_GET['params']) ? urldecode($_GET['params']) : '';
    $poolParams = isset($_GET['poolParams']) ? urldecode($_GET['poolParams']) : '';
    #global $dir;

    $dir = isset($_GET['dir']) ? realpath($_GET['dir']) : null;
    $sum = isset($_GET['sum']) ? urldecode($_GET['sum']) : '';


    if (!$dir || !is_dir($dir)) {
        echo json_encode(['error' => 'Invalid directory']);
        exit;
    }
    $files = scandir($dir);
    $data = [];

    $baseNameCounts = [];
    foreach ($files as $file) {
        $base = pathinfo($file, PATHINFO_FILENAME);
        $baseNameCounts[$base] = ($baseNameCounts[$base] ?? 0) + 1;
    }


    $data = [];

    $files = scandir($dir);
    $baseNameCounts = [];

    // Count base names (used to pair .pdf and .info files)
    foreach ($files as $file) {
        $base = pathinfo($file, PATHINFO_FILENAME);
        $baseNameCounts[$base] = ($baseNameCounts[$base] ?? 0) + 1;
    }
    $fil_nr=0;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $originalFile = $file;

            // --- Normalize UTF-8 prefixes ---
            if (substr($file, 0, 7) === '__UTF-8') {
                $newFile = trim($file, '_');
                $newFile = substr($newFile, 5);
                if (substr($newFile, -4, 1) !== '.' && strtolower(substr($newFile, -3)) === 'pdf') {
                    $newFile = str_replace('pdf', '.pdf', $newFile);
                }
                rename("$dir/$file", "$dir/$newFile");
                $file = $newFile;
            }

            // --- Replace spaces or ampersands ---
            if (strpos($file, ' ') !== false || strpos($file, '&') !== false) {
                $newFile = str_replace([' ', '&'], '_', $file);
                rename("$dir/$file", "$dir/$newFile");
                $file = $newFile;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $base = pathinfo($file, PATHINFO_FILENAME);
            $fullPath = "$dir/$file";

            // --- Convert .html or .jpg to PDF ---
            if ($ext === 'html') {
                $newFile = $base . '.pdf';
                $to = "$dir/$newFile";
                system("weasyprint -e UTF-8 '$fullPath' '$to'");
                if (file_exists($to)) {
                    unlink($fullPath);
                    $file = $newFile;
                    $fullPath = $to;
                }
            } elseif ($ext === 'jpg' || $ext === 'jpeg' || $ext === 'png') {
                // Remove .pdf suffix from base if present (handles files like "document.pdf.jpg")
                $base = preg_replace('/\.pdf$/i', '', $base);
                $newFile = $base . '.pdf';
                $to = "$dir/$newFile";
                
                system("convert '$fullPath' '$to'"); 
                
                if (file_exists($to)) {
                    // Delete the original image 
                    unlink($fullPath);

                    # Update file variables
                    $file = $newFile;
                    $fullPath = $to;

                    #Create .info file
                    $infoFile = $dir . '/' . $base . '.info';
                    file_put_contents($infoFile, $base. "\n");
                   # error_log( "Converted to PDF on " . date("Y-m-d H:i:s") . "\n");
                }
            }

            // --- Only proceed if it's a PDF ---
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf') continue;

            // --- Attempt to read matching .info file ---
            $infoFile = "$dir/{$base}.info";
            $subject = $account = $amount = "";
           
            if (file_exists($infoFile)) { // Only process those having file info
                $lines          = file($infoFile, FILE_IGNORE_NEW_LINES);
                #set file creation date if non exists in the file
                $modDate1 = date("Y-m-d H:i:s", filemtime($infoFile)); 
                #
                        
                $subject = trim($lines[0] ?? '') !== '' ? trim($lines[0]) : $base; // default to filename
                $account        = $lines[1] ?? '';
                $amount         = $lines[2] ?? '';
                // Use line 3 for date, but fall back to file mod time if empty
                $insertedDate   = (isset($lines[3]) && trim($lines[3]) !== '') ? trim($lines[3]) : $modDate1;
                $invoiceNumber  = $lines[4] ?? '';  // ✓ FIXED - now reads from correct line
                $description    = $lines[5] ?? ''; 
            }

        if (!empty($subject)) {
                // Sanitize subject to make it filename-safe
                #$safeSubject = preg_replace('/[^A-Za-z0-9_\-]/', '_', $subject);
                /* 
                // DISABLED: This auto-rename logic causes issues where filenames are changed
                // simply by viewing the list, causing broken links and "file not found" errors.
                // Filename changes should only happen via explicit user action (Edit/Rename).
                
                $updaTe = false;
            if (preg_match('/^[A-Za-z0-9_\-]+$/', $subject)) {
                // Already safe, no need to sanitize
                $safeSubject = $subject;
            } else {
                // Not safe, sanitize it
                $safeSubject = preg_replace('/[^A-Za-z0-9_\-]/', '_', $subject);
                $updaTe = true;
            }

                // Get directory
                $dir = dirname($infoFile);

                // Define new filenames 
                $newInfoFile = $dir . '/' . $safeSubject . '.info';
                $newPdfFile  = $dir . '/' . $safeSubject . '.pdf';

                // Original filenames
                $originalPdfFile = preg_replace('/\.info$/i', '.pdf', $infoFile);

                // Rename .info file if needed
                if ($infoFile !== $newInfoFile && !file_exists($newInfoFile)) {
                    if (rename($infoFile, $newInfoFile)) {
                        $infoFile = $newInfoFile; // Update path
                    } else {
                        error_log("Failed to rename .info file to: $newInfoFile");
                    }
                }

                // Rename .pdf file if it exists and the target doesn't exist
                if (file_exists($originalPdfFile) && !file_exists($newPdfFile)) {
                    if (rename($originalPdfFile, $newPdfFile)) {
                        // IMPORTANT: Update $file to reflect the new filename, so the href uses the correct name
                        $file = $safeSubject . '.pdf';
                    } else {
                        
                        error_log("Failed to rename .pdf file to: $newPdfFile. PHP must be able to write to the directory.");
                    }
                } elseif (file_exists($newPdfFile)) {
                    // The file was already renamed in a previous request, update $file to match
                    $file = $safeSubject . '.pdf';
                }

                // Update the first line of the .info file to the sanitized subject
                if (file_exists($infoFile) && $updaTe) {
                    $lines = file($infoFile, FILE_IGNORE_NEW_LINES);
                    $lines[0] = $safeSubject;
                    // Preserve any other existing lines
                    if (file_put_contents($infoFile, implode(PHP_EOL, $lines) . PHP_EOL) !== false) {

                    } else {
                        error_log("Failed to update first line of .info file: $infoFile");
                    }
                }
                */
        }


            
        // }else{
        //     continue;
        // }
    
            ###############################
            $fullPath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file;

            // Retry up to 6 times with short delay
            $retries = 6;
            $delayMs = 100; // milliseconds

            while ($retries-- > 0) {
                clearstatcache(true, $fullPath); // Clear PHP's file status cache
                if (file_exists($fullPath)) {
                    $modDate = date("Y-m-d H:i:s", filemtime($fullPath));
                    break;
                }
                usleep($delayMs * 1000); // Delay in microseconds
            }

            if (!isset($modDate)) {
                error_log("File does not exist after retries: $fullPath");
            }


            ###############################
            /*
            $fullPath = "$dir/$file";
            if (file_exists($fullPath)) {
                sleep(0.1); // slight delay to ensure file system updates
                $modDate = date("Y-m-d H:i:s", filemtime($fullPath)); 
            } else {
                error_log("File does not exist (yet?): $fullPath");
            }
            */
        
          if(isset($insertedDate )){
            $modDate = $insertedDate; 
          }

        $fil_nr++;
        #$hreftxt = "../includes/documents.php?$params&docFocus=$fil_nr&poolFile=" . urlencode($file);

        // Remove any existing poolFile from poolParams to avoid duplicate parameters
        // PHP $_GET only gets one value when there are duplicates, so we need to ensure
        // only the correct poolFile (the clicked file) is in the URL
        $cleanPoolParams = preg_replace('/&?poolFile=[^&]*/', '', $poolParams);
        $cleanPoolParams = ltrim($cleanPoolParams, '&'); // Remove leading & if present
        $hreftxt = "../includes/documents.php?$params&$cleanPoolParams&docFocus=$fil_nr&poolFile=" . urlencode($file);
        $data[] = [
            'filename' => $file,
            'subject' => $subject,
            'account' => $account,
            'amount' => $amount,
            'date' => $modDate,
            'href' => $hreftxt,
            'invoiceNumber' => $invoiceNumber,
            'description' => $description,
            'fil_nr' => $fil_nr,
        ];

    }

// Clear any previous output and send proper JSON
ob_end_clean();
header('Content-Type: application/json');

// Sort by date DESC before sending
usort($data, fn($a, $b) => strcmp($b['date'], $a['date']));

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;

