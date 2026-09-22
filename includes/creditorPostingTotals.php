<?php
// 20260921 CDX/LH Share creditor line/VAT cent rounding and validate header totals before posting.
require_once __DIR__ . '/std_func.php';

/** @return float Creditor line amount in source-currency cents. */
function creditorLineNet($price, $quantity, $discount)
{
    $price = $price === null || $price === '' ? 0 : $price;
    $quantity = $quantity === null || $quantity === '' ? 0 : $quantity;
    $discount = $discount === null || $discount === '' ? 0 : $discount;
    foreach (array($price, $quantity, $discount) as $value) {
        if (!is_numeric($value) || !is_finite((float)$value)) {
            throw new RuntimeException('Ugyldigt beløb på købsfaktura');
        }
    }
    $amount = ((float)$price - (float)$price * (float)$discount / 100) * (float)$quantity;
    if (!is_finite($amount)) {
        throw new RuntimeException('Ugyldigt beløb på købsfaktura');
    }
    return afrund($amount, 2);
}

/** @return float Input VAT in source-currency cents, also for signed credits. */
function creditorVatTotal($taxable, $rate)
{
    $rate = $rate === null || $rate === '' ? 0 : $rate;
    if (!is_numeric($rate) || !is_finite((float)$rate) || !is_finite((float)$taxable)) {
        throw new RuntimeException('Ugyldigt momsbeløb på købsfaktura');
    }
    $amount = (float)$taxable * (float)$rate / 100;
    if (!is_finite($amount)) {
        throw new RuntimeException('Ugyldigt momsbeløb på købsfaktura');
    }
    return afrund($amount, 2);
}

/**
 * Match the creditor entry screen's percentage discounts and taxable subtotal.
 * Negative-position currency corrections are already base-currency postings;
 * they are not part of the source invoice's displayed product subtotal.
 * @param array<int,array<string,mixed>> $lines
 * @return array{net:float,vat:float}
 */
function creditorPostingTotals(array $lines, $rate)
{
    $net = $taxable = 0.0;
    foreach ($lines as $line) {
        if ((int)$line['posnr'] < 0 || trim((string)$line['varenr']) === '') {
            continue;
        }
        $amount = creditorLineNet($line['pris'], $line['antal'], $line['rabat'] ?? 0);
        $net += $amount;
        if (($line['momsfri'] ?? '') !== 'on' && empty($line['omvbet'])) {
            $taxable += $amount;
        }
    }
    return array('net' => afrund($net, 2), 'vat' => creditorVatTotal($taxable, $rate));
}
