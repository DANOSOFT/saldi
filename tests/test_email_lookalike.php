<?php
/**
 * Email lookalike analyser - unit test (no database needed)
 *
 * CLI: php tests/test_email_lookalike.php
 */

include(__DIR__ . "/../includes/formFuncIncludes/emailLookalike.php");

$passed = 0;
$failed = 0;

function check($condition, $msg) {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  PASS  $msg\n";
    } else {
        $failed++;
        echo "  FAIL  $msg\n";
    }
}

echo "SST-759 customer case: U+2212 in domain\n";
$a = emailLookalikeAnalyse("oh@agf\u{2212}as.dk");
check(count($a['issues']) === 1, 'exactly one issue found');
check($a['issues'][0]['codepoint'] === 'U+2212', 'code point is U+2212');
check($a['issues'][0]['name'] === 'MINUS SIGN', 'name is MINUS SIGN');
check($a['issues'][0]['position'] === 7, 'position is 7 (1-based, character count)');
check($a['issues'][0]['replacement'] === '-', 'replacement is ASCII hyphen');
check($a['suggested'] === 'oh@agf-as.dk', 'suggested address is oh@agf-as.dk');
check($a['fixable'] === true, 'marked fixable');

echo "Invisible characters\n";
$a = emailLookalikeAnalyse("info\u{200B}@saldi.dk\u{00A0}");
check(count($a['issues']) === 2, 'zero-width space and NBSP both reported');
check($a['issues'][0]['name'] === 'ZERO WIDTH SPACE' && $a['issues'][0]['replacement'] === '', 'ZWSP is removed');
check($a['issues'][1]['name'] === 'NO-BREAK SPACE' && $a['issues'][1]['position'] === 15, 'NBSP reported at its position');
check($a['suggested'] === 'info@saldi.dk' && $a['fixable'], 'suggestion strips both');

echo "Dashes, fullwidth and homoglyphs\n";
$a = emailLookalikeAnalyse("a\u{2013}b@x\u{FF0E}dk");
check($a['suggested'] === 'a-b@x.dk' && $a['fixable'], 'en dash and fullwidth full stop mapped');
$a = emailLookalikeAnalyse("\u{FF41}bc@x.dk");
check($a['issues'][0]['name'] === 'FULLWIDTH A' && $a['suggested'] === 'abc@x.dk', 'fullwidth letters mapped generically');
$a = emailLookalikeAnalyse("\u{0441}ontact@x.dk");
check($a['issues'][0]['name'] === 'CYRILLIC SMALL LETTER ES' && $a['suggested'] === 'contact@x.dk', 'Cyrillic es -> c');

echo "Unknown non-ASCII stays unfixable\n";
$a = emailLookalikeAnalyse("bl\u{00E5}b\u{00E6}r@x.dk");
check(count($a['issues']) === 2 && $a['issues'][0]['known'] === false, 'æ/å reported as unknown');
check($a['issues'][0]['name'] === '' && $a['issues'][0]['codepoint'] === 'U+00E5', 'unknown char still has code point');
check($a['fixable'] === false, 'not fixable');

echo "Valid and plainly invalid addresses\n";
$a = emailLookalikeAnalyse('normal.user+tag@example.co.uk');
check($a['issues'] === [] && $a['fixable'] === false, 'valid ASCII address has no issues and no suggestion');
$a = emailLookalikeAnalyse('foo@bar');
check($a['issues'] === [] && $a['fixable'] === false, 'ASCII-only invalid address gets no suggestion');
$a = emailLookalikeAnalyse("@\u{2212}x.dk");
check($a['fixable'] === false, 'suggestion that is still invalid is not offered');

echo "Latin-1 input is analysed as text, not as garbage bytes\n";
$a = emailLookalikeAnalyse("o\xF8@x.dk");
check($a['issues'][0]['codepoint'] === 'U+00F8', 'ISO-8859-1 ø converted before analysis');

echo "\nPassed: $passed  Failed: $failed\n";
exit($failed ? 1 : 0);
