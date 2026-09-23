<?php
// --- debitor/cashCountHistoryPrint.php --- 2026-09-17 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20260917 CDX/PHR Format reconstructed counts for the existing POS receipt printer.
// 20260917 CDX/PHR Print saved totals without decimal-repair annotations.
// 20260917 CL/LH Convert receipt text from the account's own charset, and reuse one copy file per register.

/**
 * Convert and sanitize one line before it reaches the receipt printer.
 * Same source charset as createXreport(): the account's own encoding, not always UTF-8.
 * Control characters are stripped after the conversion, so the regex works on single-byte
 * CP865 and never has to interpret non-UTF-8 input.
 */
function cashCountReceiptText($text)
{
    global $db_encode;

    if ($db_encode == "UTF8") {
        $fromCharset = "UTF-8";
    } else {
        $fromCharset = "iso-8859-15";
    }
    $text = iconv($fromCharset, 'CP865//TRANSLIT', (string)$text);
    return preg_replace('/[\x00-\x1f\x7f]/', ' ', (string)$text);
}

/** Return the same 40-column, CP865 text format as a normal cash count. */
function cashCountHistoryReceipt(array $header, array $data, array $company, $printedBy)
{
    $lines = ['', '', 'KASSEOPGØRELSE', 'GENUDSKRIFT', '',
        $company['firmanavn'] ?? '', 'CVR: ' . ($company['cvrnr'] ?? ''), '',
        'Optællingsdato: ' . $header['date'], 'Kasse nr: ' . (int)$header['register'],
        'Rapport: ' . (int)$header['report_number'], 'Genudskrevet af: ' . $printedBy, ''];
    $receipt = '';
    foreach ($lines as $line) {
        $receipt .= wordwrap(cashCountReceiptText($line), 40, "\n", true) . "\n";
    }
    foreach ($data['rows'] as $row) {
        if (trim($row['description']) === '' && (float)$row['total'] == 0) {
            continue;
        }
        $label = cashCountReceiptText($row['description']);
        $value = number_format((float)$row['total'], 2, ',', '.');
        if (strlen($label) + strlen($value) >= 40) {
            $receipt .= wordwrap($label, 40, "\n", true) . "\n";
            $label = '';
        }
        $receipt .= $label . str_pad($value, 40 - strlen($label), ' ', STR_PAD_LEFT) . "\n";
    }

    return $receipt . "\n\n\n";
}

/**
 * Write the copy; never replace kasseopgN.txt or update accounting data.
 * One file per register, like Xreport$kasse.txt, so reprints do not pile up in temp/.
 */
function cashCountHistoryPrintFile($database, $receipt, $register)
{
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $database)) {
        throw new RuntimeException('Ugyldigt regnskab til udskrivning.');
    }
    $directory = __DIR__ . '/../temp/' . $database;
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('Regnskabets temp-mappe er ikke skrivbar.');
    }
    $name = 'cash-count-copy-' . (int)$register . '.txt';
    if (file_put_contents($directory . '/' . $name, $receipt, LOCK_EX) !== strlen($receipt)) {
        throw new RuntimeException('Kunne ikke oprette filen til bonprinteren.');
    }
    return '../temp/' . $database . '/' . $name;
}
