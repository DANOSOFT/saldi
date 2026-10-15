<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/attachmentPdfRenderer.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Accept repairable supplier PDFs while retaining bounded safe rendering.

require_once __DIR__ . '/../../restapi/models/attachment/AttachmentException.php';

/** @return bool Whether the bounded production renderer is installed. */
function mobileAttachmentRendererAvailable(): bool
{
    return is_executable('/usr/bin/gs') && is_executable('/usr/bin/prlimit') && function_exists('proc_open');
}

/**
 * Run Ghostscript with a wall deadline and kernel limits, capturing only bounded diagnostics.
 * @return string Standard output, never PDF bytes or diagnostics in client errors.
 */
function mobileAttachmentGs(array $arguments): string
{
    if (!mobileAttachmentRendererAvailable()) {
        throw new RuntimeException('Bounded PDF renderer unavailable');
    }
    $command = ['/usr/bin/prlimit', '--as=536870912', '--cpu=10', '--fsize=16777216', '--nofile=64',
        '--', '/usr/bin/gs', '-q', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dNumRenderingThreads=1', '-dMaxBitmap=8388608', '-dBufferSpace=8388608'];
    // Every argument is escaped, and exec removes the shell so timeout kills the renderer itself.
    $process = proc_open('exec ' . implode(' ', array_map('escapeshellarg', array_merge($command, $arguments))),
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
        null, ['PATH' => '/usr/bin:/bin', 'TMPDIR' => sys_get_temp_dir()]);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start PDF renderer');
    }
    foreach ($pipes as $pipe) {
        stream_set_blocking($pipe, false);
    }
    $started = microtime(true);
    $output = '';
    $diagnostics = '';
    $exit = -1;
    try {
        while (true) {
            $output .= stream_get_contents($pipes[1]);
            $diagnostics .= stream_get_contents($pipes[2]);
            if (strlen($output) + strlen($diagnostics) > 65536 || microtime(true) - $started > 12) {
                proc_terminate($process, 9);
                throw new AttachmentException('validation_failed', 'PDF exceeds rendering resource limits', 422);
            }
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exit = $status['exitcode'];
                $output .= stream_get_contents($pipes[1]);
                break;
            }
            usleep(10000);
        }
    } finally {
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
    }
    if ($exit !== 0) {
        throw new AttachmentException('validation_failed', 'PDF is malformed, encrypted or exceeds rendering limits', 422);
    }
    return $output;
}

/**
 * B4 calls this before finalizing uploads. Pixels are the sum of page raster areas at 144 dpi.
 * Page metadata is read by Ghostscript; nullpage interprets content after the caps are checked.
 * @return array{page_count:int, decoded_pixels:int}
 */
function mobileAttachmentInspectPdf(string $path): array
{
    $path = realpath($path);
    if ($path === false || !is_file($path) || filesize($path) > 15 * 1024 * 1024
        || file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
        throw new AttachmentException('validation_failed', 'Invalid PDF', 422);
    }
    $program = 'PDFname (r) file runpdfbegin pdfpagecount dup (PAGES ) print = '
        . 'dup 1 lt exch 20 gt or { /limitcheck signalerror } if '
        . '1 1 pdfpagecount { pdfgetpage dup /UserUnit known { dup /UserUnit get } { 1 } ifelse '
        . '/unit exch def /MediaBox get aload pop '
        . '3 -1 roll sub abs exch 3 -1 roll sub abs unit mul exch unit mul '
        . '(SIZE ) print exch =only ( ) print = } for quit';
    $output = mobileAttachmentGs(['-dNODISPLAY', '--permit-file-read=' . $path, '-sPDFname=' . $path, '-c', $program]);
    if (!preg_match('/PAGES ([0-9]+)/', $output, $count)) {
        throw new AttachmentException('validation_failed', 'Unable to inspect PDF', 422);
    }
    preg_match_all('/SIZE ([0-9.eE+-]+) ([0-9.eE+-]+)/', $output, $sizes, PREG_SET_ORDER);
    $pages = (int)$count[1];
    $pixels = 0;
    if (count($sizes) !== $pages) {
        throw new AttachmentException('validation_failed', 'Invalid PDF page dimensions', 422);
    }
    foreach ($sizes as $size) {
        $width = (float)$size[1] * 2;
        $height = (float)$size[2] * 2;
        if (!is_finite($width) || !is_finite($height) || $width < 1 || $height < 1
            || $width > 16000 || $height > 16000 || $width * $height > 60000000) {
            throw new AttachmentException('validation_failed', 'PDF exceeds pixel limits', 422);
        }
        $pixels += (int)ceil($width) * (int)ceil($height);
        if ($pixels > 60000000) {
            throw new AttachmentException('validation_failed', 'PDF exceeds 60 million decoded pixels', 422);
        }
    }
    mobileAttachmentGs(['-sDEVICE=nullpage', '-f', $path]);
    return ['page_count' => $pages, 'decoded_pixels' => $pixels];
}

/** @return string JPEG bytes for a 1-based page, fit inside a square of 200 or 1600 pixels. */
function mobileAttachmentRenderPdf(string $path, int $page, int $edge): string
{
    $inspection = mobileAttachmentInspectPdf($path);
    if ($page < 1 || $page > $inspection['page_count'] || !in_array($edge, [200, 1600], true)) {
        throw new AttachmentException('validation_failed', 'Page number is outside this PDF', 422);
    }
    $output = tempnam(sys_get_temp_dir(), 'mobile_jpeg_');
    if ($output === false) {
        throw new RuntimeException('Unable to create render file');
    }
    try {
        mobileAttachmentGs(['-sDEVICE=jpeg', '-dJPEGQ=85', '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4',
            '-dFirstPage=' . $page, '-dLastPage=' . $page, '-dFIXEDMEDIA', '-dPDFFitPage',
            '-g' . $edge . 'x' . $edge, '-r144', '-sOutputFile=' . $output, '-f', realpath($path)]);
        $dimensions = getimagesize($output);
        if (!$dimensions || $dimensions[2] !== IMAGETYPE_JPEG || $dimensions[0] !== $edge || $dimensions[1] !== $edge) {
            throw new AttachmentException('validation_failed', 'PDF could not be rendered', 422);
        }
        return file_get_contents($output);
    } finally {
        unlink($output);
    }
}
