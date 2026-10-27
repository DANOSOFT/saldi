<?php
// 20260914 CDX/LH SST-789: Exercise ordered batch rendering and atomic failure handling.
// Run: php tests/integration/printBatch.php (requires ps2pdf, pdftk and gs).
// Set SALDI_TEST_REAL_WEASYPRINT=1 to exercise real HTML conversion too.
require_once __DIR__ . '/../../includes/stdFunc/renderPrintBatch.php';
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function checkBatch($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function batchPs(array $labels) {
    $ps = "%!PS\n/Helvetica findfont 20 scalefont setfont\n";
    foreach ($labels as $label) $ps .= "72 720 moveto ($label) show\nshowpage\n";
    return $ps;
}
function batchText($path) {
    return shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=txtwrite -sOutputFile=- ' . escapeshellarg($path));
}

$directory = sys_get_temp_dir() . '/saldi batch ' . bin2hex(random_bytes(6));
mkdir($directory);
mkdir("$directory/bin");
$originalPath = getenv('PATH');
$realHtml = getenv('SALDI_TEST_REAL_WEASYPRINT') === '1';
if (!$realHtml) {
    // Only the HTML renderer is substituted; all conversion/merging uses real PDFs.
    file_put_contents("$directory/bin/weasyprint", "#!/bin/sh\nexec ps2pdf \"\$3\" \"\$4\"\n");
    chmod("$directory/bin/weasyprint", 0700);
    putenv("PATH=$directory/bin:$originalPath");
}
try {
    $documents = array();
    // Selection order intentionally differs from lexical filename order; include page 10.
    foreach (array('fakt30', 'fakt2', 'fakt100', 'fakt 4') as $index => $name) {
        $pages = $index === 1 ? 10 : 1;
        $labels = array();
        for ($page = 1; $page <= $pages; $page++) {
            $label = "Invoice$index-page$page-END";
            $labels[] = $label;
            $htmlPath = "$directory/$name" . ($page === 1 ? '' : "_$page") . '.htm';
            file_put_contents($htmlPath, $realHtml ? "<!doctype html><html><body><p>$label</p></body></html>" : batchPs(array($label)));
        }
        file_put_contents("$directory/$name.ps", batchPs($labels));
        $documents[] = array('name' => $name, 'background' => '', 'pages' => $pages);
    }
    file_put_contents("$directory/unrelated.htm", 'Do not print this file');
    foreach (array(false, true) as $html) {
        foreach (array(1, 2, 4) as $count) {
            $selection = array_slice($documents, 0, $count);
            renderPrintBatch($directory, $selection, 'combined.pdf', $html, 'ps2pdf', 'pdftk', false);
            $text = batchText("$directory/combined.pdf");
            $offset = -1;
            $expectedPages = 0;
            foreach ($selection as $index => $document) {
                for ($page = 1; $page <= $document['pages']; $page++) {
                    $label = "Invoice$index-page$page-END";
                    checkBatch(substr_count($text, $label) === 1, "Missing/duplicated $label");
                    $position = strpos($text, $label);
                    checkBatch($position > $offset, "Wrong order for $label");
                    $offset = $position;
                    $expectedPages++;
                }
            }
            $metadata = shell_exec('pdftk ' . escapeshellarg("$directory/combined.pdf") . ' dump_data');
            checkBatch(strpos($metadata, "NumberOfPages: $expectedPages\n") !== false, 'Wrong PDF page count');
            checkBatch(strpos($text, 'Do not print') === false, 'Unrelated document included');
            echo 'PASS: ' . ($html ? 'HTML' : 'PostScript') . " $count invoices, $expectedPages pages in selection order\n";
        }
    }

    // Different letterheads must stay with their respective invoices.
    $letterhead = batchPs(array('LETTERHEAD'));
    $letterhead = str_replace('72 720', '72 100', $letterhead);
    file_put_contents("$directory/background.ps", $letterhead);
    runPrintBatchCommand('ps2pdf ' . escapeshellarg("$directory/background.ps") . ' ' . escapeshellarg("$directory/background.pdf"), "$directory/background.pdf");
    $selection = array($documents[0], $documents[2]);
    $selection[0]['background'] = "$directory/background.pdf";
    renderPrintBatch($directory, $selection, 'combined.pdf', false, 'ps2pdf', 'pdftk', true);
    checkBatch(substr_count(batchText("$directory/combined.pdf"), 'LETTERHEAD') === 1, 'Letterhead applied to another invoice');
    echo "PASS: per-invoice letterhead\n";

    $before = hash_file('sha256', "$directory/combined.pdf");
    foreach (array('missing-primary', 'missing-extra-page', 'converter-failure', 'merge-failure', 'traversal', 'missing-letterhead', 'corrupt-input') as $scenario) {
        $selection = array_slice($documents, 0, 2);
        $converter = 'ps2pdf';
        $merger = 'pdftk';
        $html = false;
        if ($scenario === 'missing-primary') $selection[1]['name'] = 'missing';
        if ($scenario === 'missing-extra-page') { $selection[1]['pages'] = 11; $html = true; }
        if ($scenario === 'converter-failure') $converter = 'false';
        if ($scenario === 'merge-failure') $merger = 'false';
        if ($scenario === 'traversal') $selection[1]['name'] = '../outside';
        if ($scenario === 'missing-letterhead') $selection[1]['background'] = "$directory/missing.pdf";
        if ($scenario === 'corrupt-input') {
            file_put_contents("$directory/corrupt.ps", 'Invalid PostScript');
            $selection[1]['name'] = 'corrupt';
        }
        $failed = false;
        ob_start();
        try {
            renderPrintBatch($directory, $selection, 'combined.pdf', $html, $converter, $merger, true);
        } catch (RuntimeException $error) {
            $failed = true;
        } finally {
            $response = ob_get_clean();
        }
        checkBatch($failed, "$scenario must fail");
        checkBatch($response === '', 'Converter output leaked into HTTP response');
        checkBatch(hash_file('sha256', "$directory/combined.pdf") === $before, 'Existing output changed on failure');
        checkBatch(glob("$directory/batch-*") === array(), 'Temporary workspace leaked');
        checkBatch(is_file("$directory/fakt30.ps"), 'Source removed on failure');
        echo "PASS: $scenario preserves output and sources\n";
    }
} finally {
    putenv("PATH=$originalPath");
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) rmdir($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir($directory);
}
