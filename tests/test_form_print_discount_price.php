<?php
// 20260915 CDX/PHR Preserve real discount amounts when printing without a stored set price.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
$source = file_get_contents(__DIR__ . '/../includes/formfunk.php');
if (!preg_match('/^\h*if \(\$rvnr\) \{/m', $source, $match, PREG_OFFSET_CAPTURE)) {
    throw new RuntimeException('Print-price block was not found');
}
$start = $match[0][1];
// Extract the complete production block, including its nested conditionals.
$depth = 0;
$opened = false;
foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
    $text = is_array($token) ? $token[1] : $token;
    if (is_array($token) && $token[0] === T_OPEN_TAG) continue;
    $block = ($block ?? '') . $text;
    if ($token === '{') { $depth++; $opened = true; }
    if ($token === '}') $depth--;
    if ($opened && $depth === 0) break;
}
$cases = array(
    array('', -512.8, 1, 'R', -512.8),
    array(null, -512.8, 1, 'R', -512.8),
    array('supplier-reference', -512.8, 1, 'R', -512.8),
    array('125.50|10', -12, 2, 'R', 251.0),
    array('0|10', -12, 2, 'R', 0.0),
    array('-15.25|0', -12, 2, 'R', -30.5),
    array('125.50|10', 3.2, 4, 'AE10P121Q', 12.8),
);
foreach ($cases as $case) {
    list($stored, $price, $qty, $item, $expected) = $case;
    $rvnr = 1; $x = 1; $rabatvarenr = 'R';
    $row = array('lev_varenr'=>$stored);
    $pris = array(1=>$price); $antal = array(1=>$qty);
    $varenr = array(1=>$item); $rabat = array(1=>0); $linjesum = array();
    eval($block);
    if (abs($linjesum[1] - $expected) > 0.000001) {
        throw new RuntimeException('Unexpected printed line amount for ' . json_encode($case));
    }
}
echo 'PASS 7 discount/set-price printing scenarios, including test_37 order 2607' . PHP_EOL;
