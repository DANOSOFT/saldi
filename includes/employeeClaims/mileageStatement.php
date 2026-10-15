<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/employeeClaims/mileageStatement.php --- ver 5.0.0 --- 2026.10.06
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Keep wide and transliterated statement text within the page margins.
// 20261001 CDX/LH Withdraw unpublished statements whose owned upload reservation has expired.

require_once __DIR__ . '/mileageRates.php';
require_once __DIR__ . '/../docsIncludes/attachmentPdfRenderer.php';

/** @return string Renderable Latin-1 bytes, with the statement's arrow and dash substitutions. */
function mileage_statement_latin1(string $text): string
{
    $text = str_replace(['—', '→'], ['-', '->'], $text);
    $latin = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    if ($latin === false) {
        throw new RuntimeException('Unable to encode statement text');
    }
    return $latin;
}

/** @return string A PostScript literal, Latin-1 bytes escaped without executable user input. */
function mileage_statement_ps_text(string $text): string
{
    $literal = '';
    foreach (str_split(mileage_statement_latin1($text)) as $byte) {
        $code = ord($byte);
        $literal .= $code < 32 || $code > 126 || str_contains('()\\', $byte) ? sprintf('\\%03o', $code) : $byte;
    }
    return '(' . $literal . ')';
}

/**
 * Render the stored issue snapshot; complete trip text wraps across lines and pages.
 * @param array{period_from:string,period_to:string,km_total:string,amount:string,rates_json:string,lines_json:string} $statement
 * @return string PostScript with at most the upload renderer's twenty pages.
 */
function mileage_statement_postscript(array $statement): string
{
    $snapshot = json_decode($statement['rates_json'], true, 512, JSON_THROW_ON_ERROR);
    $lines = json_decode($statement['lines_json'], true, 512, JSON_THROW_ON_ERROR);
    $text = ['Kørselsgodtgørelse', 'Medarbejder: ' . $snapshot['employee_name'],
        'Periode: ' . $statement['period_from'] . ' - ' . $statement['period_to'], ''];
    foreach ($lines as $line) {
        $text[] = 'Tur ' . $line['trip_id'] . ' | ' . $line['date'];
        $text[] = 'Fra: ' . $line['from'];
        $text[] = 'Til: ' . $line['to'];
        $text[] = 'Formål: ' . $line['purpose'];
        $text[] = $line['km'] . ' km | ' . $line['km_high'] . ' km x ' . $line['rate'] . ' kr./km + '
            . $line['km_low'] . ' km x ' . $line['rate_low'] . ' kr./km | ' . $line['amount'] . ' DKK';
        $text[] = '';
    }
    $text[] = 'Kilometer i alt: ' . $statement['km_total'] . ' km';
    $text[] = 'Godtgørelse i alt: ' . $statement['amount'] . ' DKK';
    $wrapped = [];
    foreach ($text as $row) {
        // Helvetica at 10 pt has a widest Latin-1 advance of 10.15 pt (@).
        // Fifty rendered bytes fit the 515 pt text area even when transliteration expands a character.
        foreach (str_split(mileage_statement_latin1($row), 50) ?: [''] as $part) {
            $wrapped[] = mb_convert_encoding($part, 'UTF-8', 'ISO-8859-1');
        }
    }
    $pages = array_chunk($wrapped, 51);
    if (count($pages) > 20) {
        throw new MobileT2Error('validation_failed', 422, ['reason'=>'statement_too_large']);
    }
    $ps = "%!PS-Adobe-3.0\n%%BoundingBox: 0 0 595 842\n"
        . "/Helvetica-Latin1 /Helvetica findfont dup length dict begin\n"
        . "{1 index /FID ne {def} {pop pop} ifelse} forall /Encoding ISOLatin1Encoding def\n"
        . "currentdict end definefont pop\n/Helvetica-Latin1 findfont 10 scalefont setfont\n";
    foreach ($pages as $index=>$page) {
        $ps .= '%%Page: ' . ($index + 1) . ' ' . ($index + 1) . "\n";
        foreach ($page as $rowIndex=>$row) {
            $ps .= '40 ' . (795 - $rowIndex * 14) . ' moveto ' . mileage_statement_ps_text($row) . " show\n";
        }
        $ps .= '40 35 moveto ' . mileage_statement_ps_text('Side ' . ($index + 1) . ' / ' . count($pages)) . " show\nshowpage\n";
    }
    return $ps . "%%EOF\n";
}

/** @return string PDF bytes produced by the shared bounded Ghostscript runner. */
function mileage_statement_pdf(array $statement): string
{
    if (!mobileAttachmentRendererAvailable()) {
        throw new MobileT2Error('internal', 503);
    }
    $source = tempnam(sys_get_temp_dir(), 'mileage_ps_');
    $destination = tempnam(sys_get_temp_dir(), 'mileage_pdf_');
    try {
        if ($source === false || $destination === false) {
            throw new RuntimeException('Unable to create statement render files');
        }
        $ps = mileage_statement_postscript($statement);
        if (file_put_contents($source, $ps) !== strlen($ps)) {
            throw new RuntimeException('Unable to write statement source');
        }
        mobileAttachmentGs(['-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4', '-sPAPERSIZE=a4',
            '-sOutputFile=' . $destination, '-f', $source]);
        $pdf = file_get_contents($destination);
        if ($pdf === false || !str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('Statement renderer did not produce a PDF');
        }
        return $pdf;
    } finally {
        foreach ([$source, $destination] as $path) {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }
}

/**
 * Called inside the authorized deletion or expired-reservation recovery transaction with the pool row locked.
 * Lock the stored employee/year keys; return trips and allowance without repricing later statements.
 * @return void
 */
function mileage_statement_withdraw($connection, int $statementId): void
{
    if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Statement withdrawal requires the deletion transaction');
    }
    $store = new MobileSchema($connection);
    $id = (int)$statementId;
    foreach ($store->rows("SELECT scope,year FROM mobile_mileage_allocations WHERE statement_id=$id ORDER BY year,scope") as $allocation) {
        mileage_lock($allocation['scope'], (int)$allocation['year'], $connection);
    }
    $rows = $store->rows("SELECT s.*,p.status AS attachment_status FROM mobile_mileage_statements s
        LEFT JOIN pool_files p ON p.id=s.pool_file_id OR (s.state='generating' AND s.pool_file_id IS NULL
            AND p.client_id=s.client_id AND p.uploaded_by=s.subject AND p.sha256=s.pdf_sha256)
        WHERE s.id=$id FOR UPDATE OF s");
    if (!$rows) {
        throw new MobileT2Error('not_found', 404);
    }
    if ($rows[0]['state'] === 'withdrawn') {
        return;
    }
    if ($rows[0]['attachment_status'] !== 'deleted') {
        throw new MobileT2Error('conflict', 409, ['reason'=>'attachment_processed']);
    }
    $store->execute("UPDATE mobile_mileage_statements SET state='withdrawn',withdrawn_at=now() WHERE id=$id");
    $store->execute("UPDATE mobile_mileage_trips SET statement_id=NULL,version=version+1,updated_at=now() WHERE statement_id=$id");
}
