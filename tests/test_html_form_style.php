<?php
// 20261006 CL/LH SST-837: HTML form text style must disable ligatures in both layouts.

/**
 * Run with: php tests/test_html_form_style.php
 *
 * weasyprint renders the HTML forms with Nimbus Sans (fontconfig's Helvetica
 * substitute), whose default ligature table turns "Nr." into "№". The style
 * returned by formHtmlTextStyle() must switch ligatures off for every layout
 * version and font, and must keep the existing typography declarations.
 */

require __DIR__ . '/../includes/formFuncIncludes/htmlStyle.php';

$failed = 0;
$check = static function ($condition, $message) use (&$failed) {
    if ($condition) {
        echo "PASS $message\n";
    } else {
        $failed++;
        echo "FAIL $message\n";
    }
};

$cases = [
    'layout 2 Helvetica' => [formHtmlTextStyle('Helvetica', 10, 0, 0), 'font-family:Helvetica, Arial, sans-serif;font-size:10pt;'],
    'layout 2 Times bold italic' => [formHtmlTextStyle('Times', 12, 1, 1), 'font-weight:bold;font-style:italic;'],
    'layout 2 unknown font falls back' => [formHtmlTextStyle('Nope', 9, 0, 0), 'font-family:Helvetica, Arial, sans-serif;'],
    'layout 1' => [formHtmlTextStyle('Helvetica', 10, 0, 0, 1), 'font-family:Arial, Helvetica, sans-serif;font-size:12px;'],
];
foreach ($cases as $name => [$style, $expectedFragment]) {
    $check(str_contains($style, 'font-variant-ligatures:none;'), "$name disables ligatures");
    $check(str_contains($style, $expectedFragment), "$name keeps its typography");
    $check(str_contains($style, 'white-space:pre-wrap;'), "$name keeps pre-wrap");
}

exit($failed ? 1 : 0);
