<?php
// 20261020 CDX/PHR Verify that optional missing letterheads are omitted from batch manifests.
// Run: php tests/integration/printBatchLetterhead.php
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$source = file_get_contents(__DIR__ . '/../../includes/formfunk.php');
$start = strpos($source, '$printBatchBackground = is_file(');
$end = strpos($source, '$printBatchIndex =', $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Cannot locate print manifest construction.');
}
$manifestCode = substr($source, $start, $end - $start);
$existing = tempnam(sys_get_temp_dir(), 'saldi-letterhead-');
try {
    foreach (array($existing, $existing . '-missing', '', null) as $background_pdf_path) {
        $printfilnavn = 'fakt351';
        $printBatchDocuments = array();
        eval($manifestCode);
        $expected = array(array('name' => 'fakt351', 'background' => $background_pdf_path === $existing ? $existing : '', 'pages' => 1));
        if ($printBatchDocuments !== $expected) {
            throw new RuntimeException('Unexpected print manifest.');
        }
    }
    echo "PASS: existing letterhead retained; missing, empty and null letterheads omitted\n";
} finally {
    unlink($existing);
}
