<?php
// 20260930 CDX/PHR Test measured description wrapping and the actual HTML/PostScript renderer.
// Run: php tests/integration/printDescriptionLayout.php (requires gs).
chdir(__DIR__ . '/../../debitor');
require_once __DIR__ . '/../../includes/std_func.php';
require_once __DIR__ . '/../../includes/formfunk.php';
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function get_settings_value($name, $group, $default) { return '2'; }
function findtekst($key, $language) { return explode('|', $key, 2)[1]; }
function utf8_iso8859($text) { return iconv('UTF-8', 'ISO-8859-15', $text); }
function checkDescription($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

$description = array('x' => 42, 'align' => 'V');
$quantity = array('x' => 148, 'align' => 'H', 'text' => '1,00', 'font' => 'Helvetica',
    'size' => 10, 'bold' => false, 'italic' => false);
$available = formDescriptionAvailableWidth($description, array($quantity));
$narrow = str_repeat('i', 62);
checkDescription(formWrapDescription($narrow, 62, $available, 'Helvetica', 10) === $narrow,
    'The test_44 geometry must allow 62 narrow characters instead of the former 47-character cap');
checkDescription(explode("\n", formWrapDescription($narrow, 52, $available, 'Helvetica', 10))[0] === str_repeat('i', 52),
    'The configured character maximum must still apply');
$wide = formWrapDescription(str_repeat('W', 62), 62, $available, 'Helvetica', 10);
checkDescription(strpos($wide, "\n") !== false, 'Wide glyphs must wrap before the quantity');
foreach (explode("\n", $wide) as $line) {
    checkDescription(formDescriptionTextWidth($line, 'Helvetica', 10) <= $available, 'Wrapped lines must fit');
}
$quantity['text'] = '1.234.567,89';
checkDescription(formDescriptionAvailableWidth($description, array($quantity)) < $available,
    'Larger quantities must reserve their actual width');
$quantity['align'] = 'C';
$centerWidth = formDescriptionAvailableWidth($description, array($quantity));
$quantity['align'] = 'V';
checkDescription(formDescriptionAvailableWidth($description, array($quantity)) > $centerWidth,
    'Left and centre aligned neighbours must reserve different widths');
checkDescription(formWrapDescription('ÆØÅæøå', 3, INF, 'Helvetica', 10) === "ÆØÅ\næøå", 'UTF-8 characters must not split');
checkDescription(formWrapDescription("A      B\nC\n\nD", 62, INF, 'Helvetica', 10) === "A      B\nC\n\nD",
    'Internal spaces and explicit newlines must survive');
checkDescription(formWrapDescription('<b>ÆØÅæøå</b>', 3, INF, 'Helvetica', 10) === "<b>ÆØÅ\næøå</b>",
    'Formatting tags must remain intact and not count towards the maximum');
checkDescription(formWrapDescription('<big>WWW</big>', 62, 30, 'Helvetica', 10) === "<big>WW\nW</big>",
    'Enlarged text must reserve its printed width');
checkDescription(formWrapDescription('unbroken', 0, 0, 'Helvetica', 10) === "u\nn\nb\nr\no\nk\ne\nn",
    'An impossible layout must still advance without losing characters');
checkDescription(formWrapDescription('unbroken', 0, INF, 'Helvetica', 10) === 'unbroken', 'Zero means no character cap');
checkDescription(formDescriptionTextWidth('WWW', 'Helvetica', 10) > formDescriptionTextWidth('iii', 'Helvetica', 10),
    'Proportional glyph widths must be used');
checkDescription(abs(formDescriptionTextWidth('WWW', 'Courier', 10) - 18) < 0.01, 'Courier must remain monospaced');

$directory = sys_get_temp_dir() . '/saldi-description-' . bin2hex(random_bytes(6));
mkdir($directory);
try {
    $sprog_id = 1;
    $psfp = fopen("$directory/description.ps", 'w+');
    $htmfp = fopen('php://temp', 'w+');
    fwrite($psfp, "%!PS\n" . file_get_contents(__DIR__ . '/../../includes/faktinit.ps'));
    $text = 'Beskrivelse med ÆØÅ og seks      mellemrum samt en lang fortsættelse';
    $wrapped = formWrapDescription($text, 62, $available, 'Helvetica', 10);
    ob_start();
    $endY = ombryd(1, 10, '', '', '000000000', $text, 'ordrelinjer_20', 42, 250, 'V', 'Helvetica', 62, 2, 5, $wrapped);
    ob_end_clean();
    checkDescription($endY === 250 - (count(explode("\n", $wrapped)) - 1) * 5,
        'Preflight and actual rendering must use the same number of lines');
    $blankLines = "First\n\nLast";
    ob_start();
    $blankEndY = ombryd(1, 10, '', '', '000000000', $blankLines, 'ordrelinjer_20', 42, 180, 'V', 'Helvetica', 62, 2, 5, $blankLines);
    ob_end_clean();
    checkDescription($blankEndY === 170, 'Explicit blank lines must occupy the height reserved by preflight');
    fwrite($psfp, "showpage\n");
    fclose($psfp);
    rewind($htmfp);
    $html = stream_get_contents($htmfp);
    fclose($htmfp);
    foreach (explode("\n", $wrapped) as $line) {
        checkDescription(strpos($html, '>' . trim($line) . '</span>') !== false, 'The renderer must not wrap the measured lines again');
    }
    exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=txtwrite -sOutputFile=' . escapeshellarg("$directory/description.txt")
        . ' ' . escapeshellarg("$directory/description.ps") . ' 2>&1', $output, $status);
    checkDescription($status === 0, 'PostScript must render: ' . implode("\n", $output));
    checkDescription(strpos(file_get_contents("$directory/description.txt"), 'ÆØÅ') !== false, 'Rendered output must retain Danish letters');
    echo "PASS: measured widths, template maximum, neighbours, UTF-8, spaces, tags and actual HTML/PostScript wrapping\n";
} finally {
    foreach (glob("$directory/*") as $file) { unlink($file); }
    rmdir($directory);
}
