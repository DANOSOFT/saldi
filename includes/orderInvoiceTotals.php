<?php
// 20260920 CDX/LH Calculate read-only API preflight totals using momsupdat invoice rules.
require_once(__DIR__ . '/std_func.php');

/**
 * Calculate invoice totals without overwriting the caller's declared amounts.
 * Uses the same discount, participation, VAT exemption and rounding rules as momsupdat().
 *
 * @return array{net: float, vat: float}
 */
function orderInvoiceTotals(array $lines, $invoiceVatRate, $art) {
    $net = 0.0;
    $vat = 0.0;
    $mixedVat = false;
    foreach ($lines as $line) {
        $price = (float)($line['pris'] ?? 0);
        $discount = (float)($line['rabat'] ?? 0);
        $quantity = (float)($line['antal'] ?? 0);
        $lineNet = (($line['rabatart'] ?? '') === 'amount' ? $price - $discount : $price - $price * $discount / 100) * $quantity;
        $participation = $line['procent'] ?? '';
        if ($participation !== '') {
            $lineNet *= (float)$participation / 100;
        }
        $net += afrund($lineNet, 3);
        if (($line['samlevare'] ?? '') === 'on' && !empty($line['saet'])) {
            $mixedVat = true;
            continue;
        }
        $hasAccount = !empty($line['vare_id']) || (float)($line['bogf_konto'] ?? 0) > 0;
        if ($hasAccount && ($line['momsfri'] ?? '') !== 'on' && empty($line['omvbet'])) {
            $lineVatRate = (float)($line['momssats'] ?? 0);
            if (!($lineVatRate > 0 && $lineVatRate < $invoiceVatRate) && !empty($line['vare_id'])) {
                $lineVatRate = $invoiceVatRate;
            }
            if ($lineVatRate != $invoiceVatRate) {
                $mixedVat = true;
            }
            $vat += afrund($lineNet * $lineVatRate / 100, 2);
        } elseif ($hasAccount) {
            $mixedVat = true;
        }
    }
    if (!$mixedVat && $art !== 'PO') {
        $vat = afrund($net * $invoiceVatRate / 100, 2);
        $net = afrund($net, 2);
    }
    return array('net' => $net, 'vat' => $vat);
}
