<?php
/**
 * API Invoice PDF Generator and Email Sender
 * 
 * This file generates an invoice PDF and sends it via email.
 * It's designed to be called from the REST API without requiring session authentication.
 * 
 * Usage: include this file and call send_invoice_pdf($ordre_id, $db, $db_id)
 * 
 * @author PBLM
 * @date 2026-01-12
 */

// Load composer autoload (includes PHPMailer)
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    require_once("/var/www/html/vendor/autoload.php");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Prevent direct access - must be included from another script
if (!defined('API_INCLUDE') && basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)) {
    die('Direct access not allowed');
}

/**
 * Send invoice PDF to customer
 * 
 * @param int $ordre_id The order ID
 * @param string $db Database name
 * @param string $db_id Database ID (for logolib paths)
 * @return array ['success' => bool, 'message' => string]
 */
function send_invoice_pdf($ordre_id, $db, $db_id) {
    $log_file = "../temp/$db/.ht_invoice_pdf.log";
    $log = fopen($log_file, "a");
    fwrite($log, "\n" . date("Y-m-d H:i:s") . " - send_invoice_pdf called for order $ordre_id\n");
    
    // Get order details
    $qtxt = "SELECT o.*, a.firmanavn as sender_firma, a.email as sender_email, 
             a.felt_1 as smtp_host, a.felt_2 as smtp_user, a.felt_3 as smtp_pwd, a.felt_4 as smtp_enc
             FROM ordrer o, adresser a 
             WHERE o.id = '$ordre_id' AND a.art = 'S'";
    $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    
    if (!$r) {
        fwrite($log, "Order not found: $ordre_id\n");
        fclose($log);
        return ['success' => false, 'message' => 'Order not found'];
    }
    
    $ordrenr = $r['ordrenr'];
    $fakturanr = $r['fakturanr'];
    $customer_email = $r['email'];
    $customer_name = $r['firmanavn'];
    $kontakt = $r['kontakt'];
    $sprog = $r['sprog'] ? $r['sprog'] : 'Dansk';
    $status = $r['status'];
    
    $sender_firma = $r['sender_firma'];
    $sender_email = $r['sender_email'];
    $smtp_host = $r['smtp_host'] ? $r['smtp_host'] : 'localhost';
    $smtp_user = $r['smtp_user'];
    $smtp_pwd = $r['smtp_pwd'];
    $smtp_enc = $r['smtp_enc'];
    
    fwrite($log, "Order: $ordrenr, Invoice: $fakturanr, Customer: $customer_email, Status: $status\n");
    
    // Validate
    if ($status < 3) {
        fwrite($log, "Order not yet invoiced (status: $status)\n");
        fclose($log);
        return ['success' => false, 'message' => 'Order not yet invoiced'];
    }
    
    if (!$customer_email || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        fwrite($log, "Invalid customer email: $customer_email\n");
        fclose($log);
        return ['success' => false, 'message' => 'Invalid customer email'];
    }
    
    if (!$sender_email) {
        fwrite($log, "Missing sender email\n");
        fclose($log);
        return ['success' => false, 'message' => 'Missing sender email in company settings'];
    }
    
    // Get ps2pdf and pdftk paths from settings
    $r_ps2pdf = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name='ps2pdf'", __FILE__ . " linje " . __LINE__));
    $ps2pdf = $r_ps2pdf['var_value'] ? $r_ps2pdf['var_value'] : '/usr/bin/ps2pdf';
    
    $r_pdftk = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name='pdftk'", __FILE__ . " linje " . __LINE__));
    $pdftk = $r_pdftk['var_value'] ? $r_pdftk['var_value'] : '/usr/bin/pdftk';
    
    // Create temp directory for this order
    $temp_dir = "../temp/$db";
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }
    
    $ps_file = "$temp_dir/api_fakt{$fakturanr}.ps";
    $pdf_file = "$temp_dir/api_fakt{$fakturanr}.pdf";
    
    fwrite($log, "Generating PDF: $pdf_file\n");
    
    // Generate the PostScript file
    $ps_result = generate_invoice_ps($ordre_id, $ps_file, $db, $db_id, $sprog);
    if (!$ps_result['success']) {
        fwrite($log, "PS generation failed: " . $ps_result['message'] . "\n");
        fclose($log);
        return $ps_result;
    }
    
    fwrite($log, "PS file created: $ps_file\n");
    
    // Convert PS to PDF
    $cmd = "$ps2pdf $ps_file $pdf_file 2>&1";
    $output = shell_exec($cmd);
    fwrite($log, "ps2pdf command: $cmd\n");
    fwrite($log, "ps2pdf output: $output\n");
    
    if (!file_exists($pdf_file)) {
        fwrite($log, "PDF file not created\n");
        fclose($log);
        return ['success' => false, 'message' => 'PDF generation failed'];
    }
    
    // Check for background PDF and merge if exists
    $bg_file = find_background_file($db_id, $sprog, 'faktura');
    if ($bg_file && file_exists($pdftk)) {
        $merged_pdf = "$temp_dir/api_fakt{$fakturanr}_merged.pdf";
        $cmd = "$pdftk $pdf_file background $bg_file output $merged_pdf 2>&1";
        $output = shell_exec($cmd);
        fwrite($log, "pdftk command: $cmd\n");
        fwrite($log, "pdftk output: $output\n");
        
        if (file_exists($merged_pdf)) {
            unlink($pdf_file);
            rename($merged_pdf, $pdf_file);
            fwrite($log, "Merged with background: $bg_file\n");
        }
    }
    
    // Get email template from formularer
    $formularsprog = $sprog;
    $qtxt = "SELECT * FROM formularer WHERE formular = '4' AND art = '5' AND LOWER(sprog) = LOWER('$formularsprog')";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    $subjekt = '';
    $mailtext = '';
    while ($r = db_fetch_array($q)) {
        if ($r['xa'] == '1') $subjekt = $r['beskrivelse'];
        elseif ($r['xa'] == '2') $mailtext = $r['beskrivelse'];
    }
    
    // Default subject/text if not found
    if (!$subjekt) {
        $subjekt = ($sprog == 'Dansk') ? "Faktura $fakturanr" : "Invoice $fakturanr";
    }
    if (!$mailtext) {
        $mailtext = ($sprog == 'Dansk') 
            ? "Kære kunde,\n\nVedlagt finder du faktura nr. $fakturanr.\n\nMed venlig hilsen\n$sender_firma"
            : "Dear customer,\n\nPlease find attached invoice no. $fakturanr.\n\nBest regards,\n$sender_firma";
    }
    
    // Replace variables in mailtext
    $mailtext = str_replace('$firmanavn', $customer_name, $mailtext);
    $mailtext = str_replace('$kontakt', $kontakt, $mailtext);
    $mailtext = str_replace('$fakturanr', $fakturanr, $mailtext);
    $mailtext = str_replace('$ordrenr', $ordrenr, $mailtext);
    
    fwrite($log, "Subject: $subjekt\n");
    fwrite($log, "Sending to: $customer_email from $sender_email\n");
    
    // Send email with PHPMailer
    $mail_result = send_invoice_email($customer_email, $sender_email, $sender_firma, $subjekt, $mailtext, $pdf_file, $fakturanr, $smtp_host, $smtp_user, $smtp_pwd, $smtp_enc, $log);
    
    // Clean up PS file
    if (file_exists($ps_file)) {
        unlink($ps_file);
    }
    
    fwrite($log, "Result: " . ($mail_result['success'] ? 'SUCCESS' : 'FAILED') . " - " . $mail_result['message'] . "\n");
    fclose($log);
    
    return $mail_result;
}

/**
 * Find background PDF file
 */
function find_background_file($db_id, $sprog, $form_type) {
    $sprog_prefix = ($sprog != 'Dansk') ? $sprog . '_' : '';
    $backgrounds = [
        "../logolib/$db_id/{$sprog_prefix}{$form_type}_bg.pdf",
        "../logolib/$db_id/{$form_type}_bg.pdf",
        "../logolib/$db_id/{$sprog_prefix}bg.pdf",
        "../logolib/$db_id/bg.pdf"
    ];
    
    foreach ($backgrounds as $bg) {
        if (file_exists($bg)) {
            return $bg;
        }
    }
    return null;
}

/**
 * Generate invoice PostScript file
 */
function generate_invoice_ps($ordre_id, $ps_file, $db, $db_id, $sprog) {
    // Get order data
    $qtxt = "SELECT o.*, a.addr1 as konto_addr1, a.addr2 as konto_addr2, 
             a.postnr as konto_postnr, a.bynavn as konto_bynavn, a.land as konto_land,
             a.cvrnr as konto_cvrnr, a.ean as konto_ean
             FROM ordrer o 
             LEFT JOIN adresser a ON o.konto_id = a.id
             WHERE o.id = '$ordre_id'";
    $ordre = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    
    if (!$ordre) {
        return ['success' => false, 'message' => 'Order not found'];
    }
    
    // Get company info
    $qtxt = "SELECT * FROM adresser WHERE art = 'S'";
    $firma = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    
    // Get order lines
    $qtxt = "SELECT ol.*, v.varenr, v.beskrivelse as vare_beskrivelse 
             FROM ordrelinjer ol 
             LEFT JOIN varer v ON ol.vare_id = v.id 
             WHERE ol.ordre_id = '$ordre_id' 
             ORDER BY ol.id";
    $linjer = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    
    // Get bank info
    $qtxt = "SELECT * FROM grupper WHERE art = 'DIV' AND kodenr = '1'";
    $bank = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    
    // Page dimensions (A4)
    $side_x = round(210 * 2.86);
    $side_y = round(297 * 2.86);
    
    // Initialize PostScript
    $ps = "%!\n";
    $ps .= "%%Creator: Saldi API Invoice Generator\n";
    $ps .= "%%Pages: 1\n";
    $ps .= "%%BoundingBox: 0 0 $side_x $side_y\n";
    $ps .= "%%EndComments\n";
    $ps .= "<< /PageSize [$side_x $side_y] >> setpagedevice\n\n";
    
    // Define fonts
    $ps .= "/Helvetica findfont 10 scalefont setfont\n";
    $ps .= "/HelveticaBold { /Helvetica-Bold findfont exch scalefont setfont } def\n";
    $ps .= "/HelveticaNormal { /Helvetica findfont exch scalefont setfont } def\n\n";
    
    // Helper function for text positioning (PS coordinates are bottom-left origin)
    $y_start = $side_y - 100; // Start from top
    
    // Company header
    $ps .= "% Company Header\n";
    $ps .= "12 HelveticaBold\n";
    $ps .= "50 " . ($y_start) . " moveto (" . ps_escape($firma['firmanavn']) . ") show\n";
    $ps .= "10 HelveticaNormal\n";
    $ps .= "50 " . ($y_start - 15) . " moveto (" . ps_escape($firma['addr1']) . ") show\n";
    $ps .= "50 " . ($y_start - 27) . " moveto (" . ps_escape($firma['postnr'] . ' ' . $firma['bynavn']) . ") show\n";
    $ps .= "50 " . ($y_start - 39) . " moveto (CVR: " . ps_escape($firma['cvrnr']) . ") show\n";
    
    // Invoice title
    $invoice_title = ($sprog == 'Dansk') ? 'FAKTURA' : 'INVOICE';
    $ps .= "\n% Invoice Title\n";
    $ps .= "18 HelveticaBold\n";
    $ps .= "400 " . ($y_start) . " moveto ($invoice_title) show\n";
    
    // Invoice details
    $ps .= "10 HelveticaNormal\n";
    $label_fakturanr = ($sprog == 'Dansk') ? 'Fakturanr' : 'Invoice No';
    $label_dato = ($sprog == 'Dansk') ? 'Dato' : 'Date';
    $label_ordrenr = ($sprog == 'Dansk') ? 'Ordrenr' : 'Order No';
    
    $ps .= "400 " . ($y_start - 25) . " moveto ($label_fakturanr: " . ps_escape($ordre['fakturanr']) . ") show\n";
    $ps .= "400 " . ($y_start - 37) . " moveto ($label_dato: " . ps_escape($ordre['fakturadate']) . ") show\n";
    $ps .= "400 " . ($y_start - 49) . " moveto ($label_ordrenr: " . ps_escape($ordre['ordrenr']) . ") show\n";
    
    // Customer address
    $ps .= "\n% Customer Address\n";
    $ps .= "11 HelveticaBold\n";
    $ps .= "50 " . ($y_start - 100) . " moveto (" . ps_escape($ordre['firmanavn']) . ") show\n";
    $ps .= "10 HelveticaNormal\n";
    $y_addr = $y_start - 115;
    if ($ordre['kontakt']) {
        $ps .= "50 $y_addr moveto (Att: " . ps_escape($ordre['kontakt']) . ") show\n";
        $y_addr -= 12;
    }
    $ps .= "50 $y_addr moveto (" . ps_escape($ordre['addr1']) . ") show\n";
    $y_addr -= 12;
    if ($ordre['addr2']) {
        $ps .= "50 $y_addr moveto (" . ps_escape($ordre['addr2']) . ") show\n";
        $y_addr -= 12;
    }
    $ps .= "50 $y_addr moveto (" . ps_escape($ordre['postnr'] . ' ' . $ordre['bynavn']) . ") show\n";
    $y_addr -= 12;
    $ps .= "50 $y_addr moveto (" . ps_escape($ordre['land']) . ") show\n";
    
    // Order lines header
    $y_lines = $y_start - 200;
    $label_varenr = ($sprog == 'Dansk') ? 'Varenr' : 'Item No';
    $label_beskr = ($sprog == 'Dansk') ? 'Beskrivelse' : 'Description';
    $label_antal = ($sprog == 'Dansk') ? 'Antal' : 'Qty';
    $label_pris = ($sprog == 'Dansk') ? 'Pris' : 'Price';
    $label_beloeb = ($sprog == 'Dansk') ? 'Beloeb' : 'Amount';
    
    $ps .= "\n% Order Lines Header\n";
    $ps .= "0.8 setgray\n";
    $ps .= "45 " . ($y_lines + 5) . " moveto 520 " . ($y_lines + 5) . " lineto 520 " . ($y_lines - 12) . " lineto 45 " . ($y_lines - 12) . " lineto closepath fill\n";
    $ps .= "0 setgray\n";
    $ps .= "9 HelveticaBold\n";
    $ps .= "50 $y_lines moveto ($label_varenr) show\n";
    $ps .= "120 $y_lines moveto ($label_beskr) show\n";
    $ps .= "380 $y_lines moveto ($label_antal) show\n";
    $ps .= "420 $y_lines moveto ($label_pris) show\n";
    $ps .= "480 $y_lines moveto ($label_beloeb) show\n";
    
    // Order lines
    $ps .= "\n% Order Lines\n";
    $ps .= "9 HelveticaNormal\n";
    $y_line = $y_lines - 25;
    $sum = 0;
    
    while ($linje = db_fetch_array($linjer)) {
        $varenr = $linje['varenr'] ? $linje['varenr'] : '';
        $beskr = $linje['beskrivelse'] ? $linje['beskrivelse'] : $linje['vare_beskrivelse'];
        $antal = (float)$linje['antal'];
        $pris = (float)$linje['pris'];
        $rabat = (float)$linje['rabat'];
        $linje_sum = $antal * $pris * (1 - $rabat/100);
        $sum += $linje_sum;
        
        // Truncate description if too long
        if (strlen($beskr) > 40) {
            $beskr = substr($beskr, 0, 37) . '...';
        }
        
        $ps .= "50 $y_line moveto (" . ps_escape($varenr) . ") show\n";
        $ps .= "120 $y_line moveto (" . ps_escape($beskr) . ") show\n";
        $ps .= "380 $y_line moveto (" . number_format($antal, 0, ',', '.') . ") show\n";
        $ps .= "420 $y_line moveto (" . number_format($pris, 2, ',', '.') . ") show\n";
        $ps .= "480 $y_line moveto (" . number_format($linje_sum, 2, ',', '.') . ") show\n";
        
        $y_line -= 14;
        
        // Check for page break
        if ($y_line < 150) {
            $ps .= "showpage\n";
            $ps .= "10 HelveticaNormal\n";
            $y_line = $side_y - 80;
        }
    }
    
    // Totals
    $moms = (float)$ordre['moms'];
    $total = (float)$ordre['sum'] + $moms;
    $momssats = (float)$ordre['momssats'];
    $valuta = $ordre['valuta'] ? $ordre['valuta'] : 'DKK';
    
    $label_subtotal = 'Subtotal';
    $label_moms = ($sprog == 'Dansk') ? 'Moms' : 'VAT';
    $label_total = 'Total';
    
    $y_totals = $y_line - 20;
    $ps .= "\n% Totals\n";
    $ps .= "0.5 setlinewidth\n";
    $ps .= "380 " . ($y_totals + 10) . " moveto 520 " . ($y_totals + 10) . " lineto stroke\n";
    
    $ps .= "10 HelveticaNormal\n";
    $ps .= "380 $y_totals moveto ($label_subtotal:) show\n";
    $ps .= "480 $y_totals moveto (" . number_format($ordre['sum'], 2, ',', '.') . ") show\n";
    
    $y_totals -= 14;
    $ps .= "380 $y_totals moveto ($label_moms $momssats%:) show\n";
    $ps .= "480 $y_totals moveto (" . number_format($moms, 2, ',', '.') . ") show\n";
    
    $y_totals -= 14;
    $ps .= "0.5 setlinewidth\n";
    $ps .= "380 " . ($y_totals + 10) . " moveto 520 " . ($y_totals + 10) . " lineto stroke\n";
    $ps .= "12 HelveticaBold\n";
    $ps .= "380 $y_totals moveto ($label_total $valuta:) show\n";
    $ps .= "480 $y_totals moveto (" . number_format($total, 2, ',', '.') . ") show\n";
    
    // Payment info
    $label_betaling = ($sprog == 'Dansk') ? 'Betalingsbetingelser' : 'Payment Terms';
    $label_bank = ($sprog == 'Dansk') ? 'Bankoplysninger' : 'Bank Details';
    
    $y_payment = 120;
    $ps .= "\n% Payment Info\n";
    $ps .= "10 HelveticaBold\n";
    $ps .= "50 $y_payment moveto ($label_betaling:) show\n";
    $ps .= "9 HelveticaNormal\n";
    $ps .= "50 " . ($y_payment - 12) . " moveto (" . ps_escape($ordre['betalingsbet']) . ") show\n";
    
    if ($bank) {
        $ps .= "10 HelveticaBold\n";
        $ps .= "250 $y_payment moveto ($label_bank:) show\n";
        $ps .= "9 HelveticaNormal\n";
        $ps .= "250 " . ($y_payment - 12) . " moveto (" . ps_escape($bank['box1']) . ") show\n";
        $ps .= "250 " . ($y_payment - 24) . " moveto (Reg: " . ps_escape($bank['box2']) . " Konto: " . ps_escape($bank['box3']) . ") show\n";
    }
    
    // End page
    $ps .= "\nshowpage\n";
    
    // Write PS file
    if (file_put_contents($ps_file, $ps) === false) {
        return ['success' => false, 'message' => 'Could not write PS file'];
    }
    
    return ['success' => true, 'message' => 'PS file created'];
}

/**
 * Escape string for PostScript
 */
function ps_escape($str) {
    if (!$str) return '';
    $str = str_replace('\\', '\\\\', $str);
    $str = str_replace('(', '\\(', $str);
    $str = str_replace(')', '\\)', $str);
    // Remove or replace problematic characters
    $str = preg_replace('/[^\x20-\x7E]/', '', $str);
    return $str;
}

/**
 * Send invoice email using PHPMailer
 */
function send_invoice_email($to_email, $from_email, $from_name, $subject, $body, $pdf_file, $fakturanr, $smtp_host, $smtp_user, $smtp_pwd, $smtp_enc, $log) {
    fwrite($log, "SMTP Config: host='$smtp_host', user='$smtp_user', enc='$smtp_enc'\n");
    
    try {
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 0;
        
        // SSL options to allow self-signed certificates
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $smtp_host ? $smtp_host : 'localhost';
        
        if ($smtp_host && $smtp_host != 'localhost') {
            $mail->Port = ($smtp_enc == 'ssl') ? 465 : 587;
            if ($smtp_user) {
                $mail->SMTPAuth = true;
                $mail->Username = $smtp_user;
                $mail->Password = $smtp_pwd;
            }
            if ($smtp_enc) {
                $mail->SMTPSecure = $smtp_enc;
            }
            fwrite($log, "Using SMTP: $smtp_host:" . $mail->Port . " (auth: " . ($smtp_user ? 'yes' : 'no') . ")\n");
        } else {
            $mail->SMTPAuth = false;
            $mail->Port = 25;
            if (isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], 'saldi.dk') !== false) {
                global $db;
                $from_email = $db . '@' . $_SERVER['SERVER_NAME'];
            }
            fwrite($log, "Using local SMTP (localhost:25, no auth), from: $from_email\n");
        }
        
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to_email);
        $mail->addReplyTo($from_email, $from_name);
        
        $mail->Subject = $subject;
        $mail->Body = nl2br($body);
        $mail->AltBody = strip_tags($body);
        $mail->isHTML(true);
        
        // Attach PDF
        if (file_exists($pdf_file)) {
            $mail->addAttachment($pdf_file, "faktura_$fakturanr.pdf");
            fwrite($log, "Attached PDF: $pdf_file (" . filesize($pdf_file) . " bytes)\n");
        } else {
            fwrite($log, "PDF file not found: $pdf_file\n");
            return ['success' => false, 'message' => 'PDF file not found'];
        }
        
        $result = $mail->send();
        fwrite($log, "PHPMailer send() returned: " . ($result ? 'true' : 'false') . "\n");
        fwrite($log, "Email sent successfully to $to_email\n");
        return ['success' => true, 'message' => "Invoice sent to $to_email"];
        
    } catch (Exception $e) {
        $error = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
        fwrite($log, "PHPMailer error: $error\n");
        return ['success' => false, 'message' => "Email error: $error"];
    }
}
