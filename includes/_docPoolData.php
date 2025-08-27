<?php
#Begin afresh
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
        } elseif ($ext === 'jpg' || $ext === 'ejpg') {
            $newFile = $base . '.pdf';
            $to = "$dir/$newFile";
            system("convert '$fullPath' '$to'");
            if (file_exists($to)) {
                unlink($fullPath);
                $file = $newFile;
                $fullPath = $to;
            }
        }

        // --- Only proceed if it's a PDF ---
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf') continue;

        // --- Attempt to read matching .info file ---
        $infoFile = "$dir/{$base}.info";
        $subject = $account = $amount = "";
        
    if (file_exists($infoFile)) { // Only process those having file info
        $lines = file($infoFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $subject = $lines[0] ?? '';
        $account = $lines[1] ?? '';
        $amount  = $lines[2] ?? '';
    }

    if (!empty($subject)) {
        // Sanitize subject to make it filename-safe
        $safeSubject = preg_replace('/[^A-Za-z0-9_\-]/', '_', $subject); 

        // Get directory and extensions
        $dir = dirname($infoFile);
        $newInfoFile = $dir . '/' . $safeSubject . '.info';
        $newPdfFile = $dir . '/' . $safeSubject . '.pdf';

        // Original PDF file assumed to have same name as .info but with .pdf extension
        $originalPdfFile = preg_replace('/\.info$/i', '.pdf', $infoFile);

        // Rename .info file 
        if ($infoFile !== $newInfoFile && !file_exists($newInfoFile)) {
            rename($infoFile, $newInfoFile);
            $infoFile = $newInfoFile; // Update path for future use
            
        }

        // Rename .pdf file if it exists and target doesn't already exist
        if (file_exists($originalPdfFile) && !file_exists($newPdfFile)) {
            rename($originalPdfFile, $newPdfFile);
        }

        // ✅ Update the subject in the .info file to match the sanitized filename
        $lines[0] = $safeSubject;
        file_put_contents($infoFile, implode(PHP_EOL, $lines)); 
    }

         
    // }else{
    //     continue;
    // }
  

        
        $fullPath = "$dir/$file";
        if (file_exists($fullPath)) {
            sleep(0.1); // slight delay to ensure file system updates
            $modDate = date("Y-m-d H:i:s", filemtime($fullPath)); 
        } else {
            error_log("⚠️ File does not exist (yet?): $fullPath");
        }



    $fil_nr++;
    #$hreftxt = "../includes/documents.php?$params&docFocus=$fil_nr&poolFile=" . urlencode($file);

    $hreftxt = "../includes/documents.php?$params&$poolParams&docFocus=$fil_nr&poolFile=" . urlencode($file);
    $data[] = [
        'filename' => $file,
        'subject' => $subject,
        'account' => $account,
        'amount' => $amount,
        'date' => $modDate,
        'href' => $hreftxt,
        'fil_nr' => $fil_nr,
    ];

}

header('Content-Type: application/json');
echo json_encode($data);

// clear output
ob_end_clean();
header('Content-Type: application/json');
usort($data, fn($a, $b) => strcmp($b['date'], $a['date'])); // Default sort by date DESC

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;

