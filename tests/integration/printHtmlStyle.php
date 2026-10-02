<?php
// 20260929 CDX/PHR Cover HTML typography and configurable rules through the form renderer.
chdir(__DIR__ . '/../../debitor');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/../../includes/formfunk.php';
// Isolate the tenant setting from the database-backed application bootstrap.
function get_settings_value($name, $group, $default) { return '2'; }
function findtekst($key, $language) { return explode('|', $key, 2)[1]; }
function utf8_iso8859($text) { return iconv('UTF-8', 'ISO-8859-15', $text); }
function db_select($sql, $context) { return new ArrayIterator($GLOBALS['rules']); }
function db_fetch_array($result) {
    if (!$result->valid()) { return false; }
    $row = $result->current();
    $result->next();
    return $row;
}
function checkHtmlStyle($condition, $label) {
    if (!$condition) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
$rules = array(
    array('xa'=>20,'ya'=>260,'xb'=>100,'yb'=>260,'str'=>1,'color'=>0),
    array('xa'=>110,'ya'=>260,'xb'=>110,'yb'=>230,'str'=>3,'color'=>0),
    array('xa'=>100,'ya'=>220,'xb'=>20,'yb'=>220,'str'=>5,'color'=>100000000),
    array('xa'=>20,'ya'=>210,'xb'=>100,'yb'=>210,'str'=>0,'color'=>0),
);
$psfp = fopen('php://temp', 'w+');
$htmfp = fopen('php://temp', 'w+');
$sprog_id = 1;
ob_start();
formulartekst(0, 4, 'dansk');
skriv(0, 11, '', '', '0', 'Regular Helvetica 11pt', 'header', 20, 280, 'V', 'Helvetica', 4, __LINE__);
skriv(0, 11, 'on', '', '0', 'Bold Helvetica 11pt', 'header', 20, 272, 'V', 'Helvetica', 4, __LINE__);
skriv(0, 12, 'on', 'on', '0', 'Bold italic Times 12pt', 'header', 20, 200, 'V', 'Times', 4, __LINE__);
ob_end_clean();
rewind($htmfp);
$html = stream_get_contents($htmfp);
rewind($psfp);
$ps = stream_get_contents($psfp);
fclose($htmfp);
fclose($psfp);
checkHtmlStyle(strpos($html, 'border-top:1pt solid rgb(0%,0%,0%)') !== false, 'Horizontal rule uses the configured one-point width');
checkHtmlStyle(strpos($html, 'border-left:3pt solid rgb(0%,0%,0%)') !== false, 'Vertical rule uses the configured three-point width');
checkHtmlStyle(strpos($html, 'border-top:5pt solid rgb(100%,0%,0%)') !== false && strpos($html, 'width:80mm') !== false, 'Reverse-direction rule retains its width, length and color');
checkHtmlStyle(strpos($html, 'border-top:0.2pt') !== false, 'A zero-width PostScript hairline remains visible in HTML');
checkHtmlStyle(strpos($html, 'font-size:11pt;font-weight:normal;') !== false, 'Eleven-point normal text is not reduced to 9.9 points');
checkHtmlStyle(strpos($html, 'font-size:11pt;font-weight:bold;') !== false, 'Bold text retains its weight');
checkHtmlStyle(strpos($html, 'font-family:Times, Times New Roman, serif;font-size:12pt;font-weight:bold;font-style:italic;') !== false, 'Selected Times font and bold italic styling are retained');
checkHtmlStyle(strpos($ps, '1 setlinewidth') !== false && strpos($ps, '5 setlinewidth') !== false && strpos($ps, '11 scalefont') !== false, 'PostScript still uses the existing line widths and text sizes');
checkHtmlStyle(formHtmlTextStyle('Times', 11, true, true, 1) === 'font-family:Arial, Helvetica, sans-serif;font-size:13.2px;white-space:pre-wrap;', 'Legacy typography preserves the original fixed family and pixel conversion');
$legacyLine = '<hr style="position:absolute;top:37.37mm;left:20mm;border:0.2px solid black; width:80mm;">' . "\n";
checkHtmlStyle(formHtmlLine($rules[0], 1) === $legacyLine, 'Legacy rules preserve the original element, width, margins and placement');
foreach (array(1, 2) as $version) {
    checkHtmlStyle(strpos(formHtmlTextStyle('Helvetica', 11, false, false, $version), 'white-space:pre-wrap;') !== false, "Layout $version preserves repeated spaces while allowing wrapping");
}
// Optional output for a real WeasyPrint smoke test, without any tenant data or writes.
if (isset($argv[1])) {
    file_put_contents($argv[1], '<!doctype html><html><head><meta charset="UTF-8"><style>@page{size:A4;margin:0}body{margin:0}</style></head><body>' . $html . '</body></html>');
}
