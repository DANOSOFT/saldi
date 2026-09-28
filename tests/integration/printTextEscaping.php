<?php
// 20260914 CDX/LH SST-784: Test actual skriv() output in HTML and real PostScript rendering.
// Run: php tests/integration/printTextEscaping.php (requires ps2pdf and gs).
chdir(__DIR__ . '/../../debitor');
require_once __DIR__ . '/../../includes/formfunk.php';

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Isolate translation and character conversion from the database-backed application bootstrap.
function findtekst($key, $language) { return explode('|', $key, 2)[1]; }
function utf8_iso8859($text) { return iconv('UTF-8', 'ISO-8859-15', $text); }

$directory = sys_get_temp_dir() . '/saldi-text-' . bin2hex(random_bytes(6));
mkdir($directory);
try {
    $sprog_id = 1;
    foreach (['(VW Polo 1,0 tsi -- DB74015)', 'Nested (outer (inner))', 'Unbalanced (text', 'C:\\new\\test (part)', 'Plain text'] as $index => $text) {
        foreach (['V', 'H', 'C'] as $alignment) {
            $psfp = fopen("$directory/text.ps", 'w+');
            $htmfp = fopen('php://temp', 'w+');
            fwrite($psfp, "%!PS\n" . file_get_contents(__DIR__ . '/../../includes/faktinit.ps'));
            ob_start();
            skriv(1, 12, '', '', '000000000', $text, 'header', 20, 250, $alignment, 'Helvetica', 2, __LINE__);
            ob_end_clean();
            fwrite($psfp, "showpage\n");
            fclose($psfp);
            rewind($htmfp);
            $html = stream_get_contents($htmfp);
            fclose($htmfp);
            if (strpos($html, '>' . $text . '</span>') === false) {
                throw new RuntimeException("HTML text changed: $text");
            }
            exec('ps2pdf ' . escapeshellarg("$directory/text.ps") . ' ' . escapeshellarg("$directory/text.pdf") . ' 2>&1', $output, $status);
            if ($status !== 0) throw new RuntimeException(implode("\n", $output));
            $rendered = shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=txtwrite -sOutputFile=- ' . escapeshellarg("$directory/text.pdf"));
            // The existing ISO font maps ASCII hyphen to the Unicode minus glyph.
            if (strpos(str_replace('−', '-', $rendered), $text) === false) {
                throw new RuntimeException("PostScript text changed: $text; got $rendered");
            }
        }
        echo "PASS: fixture $index, left/right/center HTML and PostScript\n";
    }
} finally {
    foreach (glob("$directory/*") as $file) unlink($file);
    rmdir($directory);
}
