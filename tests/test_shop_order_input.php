<?php
// 20260920 CDX/LH Reject lossy identifiers and numeric-prefix monetary coercion.
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
require_once __DIR__ . '/../includes/shopOrderInput.php';
function inputCheck($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}
foreach (['1', '0001', 900190001, (string)PHP_INT_MAX] as $value) {
    inputCheck(saldiShopIntegerId($value), 'Accept exact positive integer ' . $value);
}
foreach (['0', '-1', '1.9', '9e8', '1 ', '99999999999999999999999', '', null, '1 OR 1=1'] as $value) {
    inputCheck(!saldiShopIntegerId($value), 'Reject nonrepresentable order identity ' . (string)$value);
}
inputCheck(saldiShopIntegerId('0', true), 'Optional customer mapping accepts explicit zero');
foreach ([[], ['1'], true, false, 1.0] as $value) {
    inputCheck(!saldiShopIntegerId($value), 'Reject noninteger identifier types');
}
foreach ([['100,50', 100.5], ['25,125', 25.125], ['-100.50', -100.5], ['0', 0.0], [' 12.25 ', 12.25]] as [$value, $expected]) {
    inputCheck(saldiShopDecimal($value) === $expected, 'Preserve complete decimal value ' . $value);
}
foreach (['100abc', '1,234.56', '1.234,56', '1e3', 'NaN', 'INF', '', null, str_repeat('9', 400)] as $value) {
    inputCheck(saldiShopDecimal($value) === null, 'Reject ambiguous or nonfinite amount');
}
foreach ([[], ['100'], true, false] as $value) {
    inputCheck(saldiShopDecimal($value) === null, 'Reject nonnumeric monetary input types');
}
