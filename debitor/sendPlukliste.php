<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/sendPlukliste.php --- 2026-01-21 ---
// Handles sending plukliste (picking list) via email
// 
// This script generates a PDF plukliste and sends it to the configured email address
// from the settings table (pluklisteEmail in groupe 'ordre')

@session_start();
$s_id = session_id();

$modulnr = 5;
$css = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/formfunk.php");

if (!isset($exec_path)) $exec_path = "/usr/bin";

$id = if_isset($_GET, null, 'id');

if (!$id || !is_numeric($id)) {
    print "<script>alert('Ordre ID mangler');window.history.back();</script>";
    exit;
}

// Get the plukliste email from settings
$pluklisteEmail = get_settings_value("pluklisteEmail", "ordre", "");

if (!$pluklisteEmail) {
    print "<script>alert('Plukliste email er ikke konfigureret. Gå til System -> Indstillinger -> Ordrerelaterede valg.');window.history.back();</script>";
    exit;
}

// Validate email format
if (!filter_var($pluklisteEmail, FILTER_VALIDATE_EMAIL)) {
    print "<script>alert('Ugyldig email adresse konfigureret: " . htmlspecialchars($pluklisteEmail) . "');window.history.back();</script>";
    exit;
}

// Get order information
$qtxt = "select ordrenr, kontonr, firmanavn from ordrer where id = '$id'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if (!$r) {
    print "<script>alert('Ordre ikke fundet');window.history.back();</script>";
    exit;
}
$ordrenr = $r['ordrenr'];
$kontonr = $r['kontonr'];
$firmanavn = $r['firmanavn'];

// Generate the plukliste using formularprint
$formular = 9; // Plukliste form number
$lev_nr = -1;
$charset = 'UTF-8';
$udskriv_alle_til = 'PDF';

// Call formularprint to generate the PS/HTM files
// formularprint will create the temp directory and files
ob_start(); // Capture any output from formularprint
$result = formularprint($id, $formular, $lev_nr, $charset, $udskriv_alle_til);
ob_end_clean();

// The mappe path used by formularprint
$mappe = "../temp/$db/" . abs($bruger_id);

// Check if mappe exists
if (!file_exists($mappe)) {
    print "<script>alert('Temp mappe blev ikke oprettet: $mappe');window.history.back();</script>";
    exit;
}

// Now we need to convert the PS/HTM files to PDF (like udskriv.php does)
// Get the print settings
$q_pv = db_select("select box2,box3 from grupper where art='PV'", __FILE__ . " linje " . __LINE__);
$r_pv = db_fetch_array($q_pv);

$r_ps2pdf = db_fetch_array(db_select("select var_value from settings where var_name='ps2pdf'", __FILE__ . " linje " . __LINE__));
if ($r_ps2pdf && $r_ps2pdf['var_value']) {
    $ps2pdf = $r_ps2pdf['var_value'];
} else {
    $ps2pdf = "$exec_path/ps2pdf";
}

// The printfilnavn for plukliste is "plukliste" + ordrenr (based on formfunk.php)
$printfilnavn = "plukliste" . $ordrenr;

// Check if we should use HTML or PostScript
$use_html = isset($r_pv['box3']) && $r_pv['box3'];
$use_ps2pdf_cmd = isset($r_pv['box2']) && $r_pv['box2'];

$pdf_path = null;

if ($use_ps2pdf_cmd) {
    // Use custom ps2pdf command
    $ps_file = "$mappe/$printfilnavn.ps";
    $pdf_file = "$mappe/$printfilnavn.pdf";
    if (file_exists($ps_file)) {
        system("{$r_pv['box2']} $ps_file $pdf_file");
        if (file_exists($pdf_file)) {
            $pdf_path = $pdf_file;
        }
    }
} elseif ($use_html) {
    // Use weasyprint for HTML
    $htmfil = glob("$mappe/*.htm");
    if ($htmfil && count($htmfil) > 0) {
        $indfil = '';
        $pdffil = [];
        
        for ($i = 0; $i < count($htmfil); $i++) {
            if (filesize($htmfil[$i])) {
                $pdffil[$i] = str_replace(".htm", ".pdf", $htmfil[$i]);
                system("weasyprint -e UTF-8 {$htmfil[$i]} {$pdffil[$i]}");
                ($indfil) ? $indfil .= " " . $pdffil[$i] : $indfil = $pdffil[$i];
            }
        }
        
        if (count($htmfil) > 1) {
            $udfil = "$mappe/udskrift.pdf";
            system("pdftk $indfil output $udfil");
            $pdf_path = $udfil;
        } elseif (count($pdffil) > 0 && isset($pdffil[0]) && file_exists($pdffil[0])) {
            $pdf_path = $pdffil[0];
        }
    }
} else {
    // Use PostScript
    $psfil = glob("$mappe/*.ps");
    if ($psfil && count($psfil) > 0) {
        $indfil = '';
        $pdffil = [];
        
        for ($i = 0; $i < count($psfil); $i++) {
            if (filesize($psfil[$i])) {
                $pdffil[$i] = str_replace(".ps", ".pdf", $psfil[$i]);
                system("$ps2pdf {$psfil[$i]} {$pdffil[$i]}");
                ($indfil) ? $indfil .= " " . $pdffil[$i] : $indfil = $pdffil[$i];
            }
        }
        
        if (count($psfil) > 1) {
            $udfil = "$mappe/udskrift.pdf";
            system("pdftk $indfil output $udfil");
            $pdf_path = $udfil;
        } elseif (count($pdffil) > 0 && isset($pdffil[0]) && file_exists($pdffil[0])) {
            $pdf_path = $pdffil[0];
        }
    }
}

// Try to apply background if it exists
if ($pdf_path && file_exists($pdf_path)) {
    $bg_fil = null;
    if (file_exists("../logolib/$db_id/bg.pdf")) {
        $bg_fil = "../logolib/$db_id/bg.pdf";
    }
    
    if ($bg_fil && system("which pdftk")) {
        $out = $pdf_path . "x.pdf";
        system("$exec_path/pdftk $pdf_path background $bg_fil output $out");
        if (file_exists($out)) {
            unlink($pdf_path);
            rename($out, $pdf_path);
        }
    }
}

// Check if PDF was generated
if (!$pdf_path || !file_exists($pdf_path)) {
    // Debug: list files in mappe
    $files = @scandir($mappe);
    if ($files === false) {
        $file_list = "(kunne ikke læse mappe)";
    } else {
        $file_list = implode(", ", $files);
    }
    print "<script>alert('Kunne ikke generere plukliste PDF.\\nMappe: $mappe\\nFiler: $file_list');window.history.back();</script>";
    exit;
}

// Send the email with the PDF attached
$mail_subj = "Plukliste for ordre $ordrenr - $firmanavn";
$mail_text = "Hermed plukliste for ordre $ordrenr.\n\nKunde: $firmanavn (Kontonr: $kontonr)";
$mailsprog = 'dansk';

// Use the send_mails function
$svar = send_mails($id, $pdf_path, $pluklisteEmail, $mailsprog, $formular, $mail_subj, $mail_text, '', 0);

if ($svar && strpos($svar, 'Fejl') === false && strpos($svar, 'Error') === false) {
    print "<script>alert('Plukliste sendt til: " . htmlspecialchars($pluklisteEmail) . "');window.history.back();</script>";
} else {
    print "<script>alert('Fejl ved afsendelse af plukliste: " . htmlspecialchars($svar) . "');window.history.back();</script>";
}
