<?php
// 20260914 CDX/LH SST-789: Render every selected document and atomically publish one ordered PDF.

/**
 * Render a server-created batch in selection order; never publish partial output.
 * Existing source files and destination survive conversion/merge failures.
 *
 * @param string $directory Current tenant/user print directory.
 * @param array<int, array{name: string, background: string, pages: int}> $documents Ordered source stems, letterheads and page counts.
 * @param string $destination Output filename within the print directory.
 * @param bool $html Use HTML pages rather than the primary PostScript file.
 * @param string $psCommand Trusted configured PostScript converter command.
 * @param string $pdftk Path to pdftk.
 * @param bool $withBackground Apply each document's own PDF letterhead.
 * @return void
 * @throws RuntimeException On invalid input, conversion, merge or publication failure.
 */
function renderPrintBatch($directory, array $documents, $destination, $html, $psCommand, $pdftk, $withBackground) {
    $directory = realpath($directory);
    if ($directory === false || !$documents || basename($destination) !== $destination) {
        throw new RuntimeException('Invalid print batch.');
    }
    $temporary = $directory . '/batch-' . bin2hex(random_bytes(8));
    if (!mkdir($temporary, 0700)) {
        throw new RuntimeException('Cannot create print batch workspace.');
    }
    try {
        $pdfs = array();
        foreach ($documents as $index => $document) {
            $name = $document['name'];
            if (!is_string($name) || $name === '' || basename($name) !== $name || strpos($name, "\0") !== false) {
                throw new RuntimeException('Invalid print document name.');
            }
            $primary = $directory . '/' . $name . ($html ? '.htm' : '.ps');
            $sources = array($primary);
            // skriv()/bundtekst() keep all PS pages in the primary stream; HTML uses one file per page.
            if ($html) {
                for ($page = 2; $page <= $document['pages']; $page++) {
                    $sources[] = $directory . '/' . $name . '_' . $page . '.htm';
                }
            }
            $pages = array();
            foreach ($sources as $page => $source) {
                if (!is_file($source) || filesize($source) === 0 || dirname(realpath($source)) !== $directory) {
                    throw new RuntimeException('Missing or invalid print page: ' . basename($source));
                }
                $output = "$temporary/document-$index-page-$page.pdf";
                $command = ($html || $page > 0) ? 'weasyprint -e UTF-8' : $psCommand;
                runPrintBatchCommand($command . ' ' . escapeshellarg($source) . ' ' . escapeshellarg($output), $output);
                $pages[] = $output;
            }
            $documentPdf = "$temporary/document-$index.pdf";
            runPrintBatchCommand(escapeshellarg($pdftk) . ' ' . implode(' ', array_map('escapeshellarg', $pages)) . ' cat output ' . escapeshellarg($documentPdf), $documentPdf);
            $background = $document['background'] ?? '';
            if ($withBackground && $background !== '') {
                if (!is_file($background)) {
                    throw new RuntimeException('Missing print letterhead.');
                }
                $withLetterhead = "$temporary/document-$index-letterhead.pdf";
                runPrintBatchCommand(escapeshellarg($pdftk) . ' ' . escapeshellarg($documentPdf) . ' background ' . escapeshellarg($background) . ' output ' . escapeshellarg($withLetterhead), $withLetterhead);
                $documentPdf = $withLetterhead;
            }
            $pdfs[] = $documentPdf;
        }
        $merged = "$temporary/batch.pdf";
        runPrintBatchCommand(escapeshellarg($pdftk) . ' ' . implode(' ', array_map('escapeshellarg', $pdfs)) . ' cat output ' . escapeshellarg($merged), $merged);
        if (!chmod($merged, 0666 & ~umask()) || !rename($merged, "$directory/$destination")) {
            throw new RuntimeException('Cannot publish print batch.');
        }
    } finally {
        foreach (scandir($temporary) as $file) {
            if ($file !== '.' && $file !== '..') {
                unlink($temporary . '/' . $file);
            }
        }
        rmdir($temporary);
    }
}

/**
 * Run a print command without leaking command output into the HTTP response.
 *
 * @param string $command Shell command with escaped file arguments.
 * @param string $output Expected newly generated PDF.
 * @return void
 * @throws RuntimeException When conversion fails or produces no PDF.
 */
function runPrintBatchCommand($command, $output) {
    exec($command . ' 2>&1', $messages, $status);
    clearstatcache(true, $output);
    if ($status !== 0 || !is_file($output) || filesize($output) === 0 || file_get_contents($output, false, null, 0, 5) !== '%PDF-') {
        throw new RuntimeException('Print batch command failed: ' . implode(' | ', $messages));
    }
}
