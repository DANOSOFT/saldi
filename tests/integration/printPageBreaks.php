<?php
// 20260925 CL/LH SST-823: Every PostScript page ends with exactly one page break in every logo mode.
// Run: php tests/integration/printPageBreaks.php (requires ps2pdf and gs).
chdir(__DIR__ . '/../../debitor');
require_once __DIR__ . '/../../includes/formfunk.php';

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// The stock logolib/logo.eps (removed from the repo in 2024) ended with its own showpage.
$stockEps = "10 dict begin\n14 14 translate\n0 0 moveto 2 2 lineto stroke\nshowpage\n%%Trailer\nend\n%%EOF\n";
$logos = [
    'PDF letterhead' => ['PDF', ''],
    'EPS with its own showpage' => ['EPS', $stockEps],
    'EPS without showpage' => ['EPS', "0 0 moveto 10 10 lineto stroke\n"],
    'EPS mentioning showpage only in a comment' => ['EPS', "% showpage intentionally omitted\n0 0 moveto 10 10 lineto stroke\n"],
    'EPS redefining showpage' => ['EPS', "/showpage {} def\n0 0 moveto 10 10 lineto stroke\n"],
    'EPS without trailing newline' => ['EPS', '0 0 moveto 10 10 lineto stroke'],
    'EPS ending in a comment' => ['EPS', '0 0 moveto 10 10 lineto stroke % end'],
    'EPS leaving a dictionary and an operand' => ['EPS', "10 dict begin 42\n"],
    'missing EPS file' => ['EPS', ''],
];

$directory = sys_get_temp_dir() . '/saldi-pages-' . bin2hex(random_bytes(6));
mkdir($directory);
try {
    foreach ($logos as $case => [$logoart, $logo]) {
        $side = 1;
        $ya = $linjeafstand = 0;
        $psfp = fopen("$directory/doc.ps", 'w');
        $htmfp = fopen('php://temp', 'w+');
        fwrite($psfp, "%!PS\n");
        for ($page = 1; $page <= 3; $page++) {
            fwrite($psfp, "/Helvetica findfont 20 scalefont setfont 100 700 moveto (Side $page) show\n");
            ob_start();
            bundtekst(0);
            ob_end_clean();
        }
        fclose($psfp);
        fclose($htmfp);
        exec('ps2pdf ' . escapeshellarg("$directory/doc.ps") . ' ' . escapeshellarg("$directory/doc.pdf") . ' 2>&1', $output, $status);
        if ($status !== 0) throw new RuntimeException("$case: ps2pdf failed: " . implode("\n", $output));
        $rendered = preg_replace('/\s+/', ' ', trim(shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=txtwrite -sOutputFile=- ' . escapeshellarg("$directory/doc.pdf"))));
        $pages = (int) shell_exec('gs -q -dNODISPLAY -dNOSAFER -c ' . escapeshellarg('(' . "$directory/doc.pdf" . ') (r) file runpdfbegin pdfpagecount = quit'));
        if ($pages !== 3 || $rendered !== 'Side 1 Side 2 Side 3') {
            throw new RuntimeException("$case: expected 3 pages 'Side 1 Side 2 Side 3', got $pages pages '$rendered'");
        }
        echo "PASS: $case\n";
    }
} finally {
    foreach (glob("$directory/*") as $file) unlink($file);
    rmdir($directory);
}
