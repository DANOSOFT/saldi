<?php
// tests/test_order_unit_price_precision.php --- 2026-09-19
// Copyright (c) 2026 Danosoft ApS
// 20260919 CDX/PHR Regress order price display/save precision using production code blocks.

error_reporting(E_ALL);
require_once __DIR__ . '/../includes/std_func.php';
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$source = file_get_contents(__DIR__ . '/../debitor/ordre.php');
function priceTestBlock($source, $startMarker, $endMarker)
{
    $start = strpos($source, $startMarker);
    $end = $start === false ? false : strpos($source, $endMarker, $start + strlen($startMarker));
    if ($start === false || $end === false) throw new RuntimeException('Order code block not found.');
    return substr($source, $start, $end - $start);
}
function priceTestEqual($actual, $expected)
{
    if ($actual !== $expected) {
        throw new RuntimeException(var_export($actual, true) . ' != ' . var_export($expected, true));
    }
}
eval(priceTestBlock($source, 'function formatOrderUnitPrice(', 'function ordrelinjer('));
$readPrice = priceTestBlock($source, '		$y = "pris" . $x;', '		$y = "raba" . $x;');
// Evaluate the actual open-order renderer assignments, not a copy of their logic.
$renderer = substr($source, strpos($source, 'function ordrelinjer('));
if (!preg_match_all('/\$dkpris = (?:dkdecimal|formatOrderUnitPrice)\([^;\n]+;/', $renderer, $assignments) || count($assignments[0]) < 2) {
    throw new RuntimeException('Unit price renderer assignments not found.');
}
$renderNet = $assignments[0][0];
$renderGross = $assignments[0][1];
priceTestEqual(formatOrderUnitPrice(null), '');
priceTestEqual(formatOrderUnitPrice(''), '');
$cases = [
    [0, '0,00'], [21, '21,00'], [21.89, '21,89'], [21.888, '21,888'],
    [68.016, '68,016'], [60.608, '60,608'], [18.048, '18,048'],
    [1234.567, '1.234,567'], [-21.888, '-21,888'], [0.001, '0,001'],
];
foreach ($cases as [$original, $formatted]) {
    priceTestEqual(formatOrderUnitPrice($original), $formatted);
    foreach ([false, true] as $incl_moms) {
        foreach (['', ' (10,00)', ' (!)'] as $costSuffix) {
            $savedPrice = $original;
            for ($pass = 0; $pass < 10; $pass++) {
                $pris = $savedPrice;
                $varemomssats = 25;
                eval($incl_moms ? $renderGross : $renderNet);
                $_POST = ['pris1' => $dkpris . $costSuffix];
                $x = 1;
                $pris = $kostpris = [];
                $momsfri = $omvbet = $vare_id = [1 => 0];
                $varemomssats = [1 => 25];
                eval($readPrice);
                $savedPrice = (float)$pris[1];
                priceTestEqual($savedPrice, (float)$original);
            }
        }
    }
}
// Reconstructed net prices reproduce the reported shop totals. Original August
// API logs are unavailable; these are regression fixtures, not a historical replay.
$orders = [
    '2631619' => [[[8, 21.888]], '218,88'],
    '2631709' => [[[1, 811.2], [1, 610], [1, 18.048], [2, 316]], '2.589,06'],
    '2631714' => [[[2, 68.016], [2, 60.608], [1, 39]], '370,31'],
];
foreach ($orders as $number => [$lines, $expectedTotal]) {
    for ($pass = 0; $pass < 10; $pass++) {
        $sum = 0;
        foreach ($lines as &$line) {
            $pris = $line[1];
            eval($renderNet);
            $_POST = ['pris1' => $dkpris];
            $x = 1;
            $incl_moms = false;
            $pris = [];
            eval($readPrice);
            $line[1] = (float)$pris[1];
            $sum += afrund($line[0] * $line[1], 3);
        }
        unset($line);
        $sum = afrund($sum, 2);
        $vat = afrund($sum * 0.25, 3);
        priceTestEqual(dkdecimal($sum + $vat, 2), $expectedTotal);
    }
    echo "OK: order $number remains $expectedTotal after 10 saves.\n";
}
echo "OK: 600 price round trips, including VAT, negatives, thousands and cost-price input.\n";
