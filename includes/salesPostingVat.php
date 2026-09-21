<?php
// 20260920 CDX/LH Allocate invoice VAT cents across tax/revenue/project groups before currency conversion.
require_once __DIR__ . '/std_func.php';

/** @return string Stable identity for one posting group. */
function salesPostingVatKey($phase, $project, $revenueAccount, $vatAccount, $vatRate) {
    return json_encode([(int)$phase, trim((string)$project), (int)$revenueAccount, (int)$vatAccount, (float)$vatRate]);
}

/**
 * Round an allocation while preserving its authoritative total. Residual cents
 * go to the largest rounding remainder; input order breaks ties consistently.
 *
 * @param array<string, float> $amounts Unrounded amounts.
 * @return array<string, float> Rounded allocations, summing exactly to target cents.
 */
function salesPostingAllocateCents(array $amounts, $target) {
    $cents = [];
    foreach ($amounts as $key => $amount) {
        $cents[$key] = (int)round($amount * 100);
    }
    $difference = (int)round($target * 100) - array_sum($cents);
    if (!$cents && $difference) throw new RuntimeException('Invoice VAT has no posting groups');
    while ($difference) {
        $direction = $difference > 0 ? 1 : -1;
        $best = null;
        $bestRemainder = null;
        foreach ($amounts as $key => $amount) {
            $remainder = ($amount * 100 - $cents[$key]) * $direction;
            if ($best === null || $remainder > $bestRemainder + 0.0000001) {
                $best = $key;
                $bestRemainder = $remainder;
            }
        }
        $cents[$best] += $direction;
        $difference -= $direction;
    }
    return array_map(function ($value) { return $value / 100; }, $cents);
}

/**
 * Keep the invoice's VAT total authoritative, allowing only rounding-sized
 * reconciliation. VAT account/rate totals are shared across revenue accounts
 * and projects, so adding a revenue group cannot multiply source-currency cents.
 *
 * @param array<int, array<string, mixed>> $lines Order lines, in deterministic order.
 * @return array<string, float> Posting-group VAT amounts in base currency.
 */
function salesPostingVatAllocations(array $lines, $invoiceVat, $currencyRate) {
    $groups = $taxGroups = $taxMembers = $result = [];
    $taxableLines = 0;
    foreach ($lines as $line) {
        $phase = (int)$line['posnr'] < 0 ? 2 : 1;
        $account = (int)$line['bogf_konto'];
        if ($phase === 1 && !$account) continue;
        $rate = (float)$line['momssats'];
        $key = salesPostingVatKey($phase, $line['projekt'], $account, $line['vat_account'], $rate);
        $net = (float)$line['pris'] * (float)$line['antal'];
        $net -= $line['rabatart'] === 'amount'
            ? (float)$line['rabat'] * (float)$line['antal'] : $net * (float)$line['rabat'] / 100;
        if ($line['procent'] !== '' && $line['procent'] !== null) $net *= (float)$line['procent'] / 100;
        $vat = $rate && !$line['momsfri'] && empty($line['omvbet'])
            && !(($line['samlevare'] ?? '') === 'on' && !empty($line['saet'])) ? $net * $rate / 100 : 0;
        if ($phase === 2) {
            $result[$key] = ($result[$key] ?? 0) + $vat;
            continue;
        }
        if ($vat == 0) {
            $result[$key] = 0;
            continue;
        }
        $taxableLines++;
        $groups[$key] = ($groups[$key] ?? 0) + $vat;
        $taxKey = (int)$line['vat_account'] . ':' . $rate;
        $taxGroups[$taxKey] = ($taxGroups[$taxKey] ?? 0) + $vat;
        $taxMembers[$taxKey][$key] = true;
    }
    foreach ($result as $key => $amount) $result[$key] = round($amount, 2);
    $rawVat = array_sum($taxGroups);
    if (abs($rawVat - $invoiceVat) > max(0.01, ($taxableLines + 1) * 0.0051) + 0.0000001) {
        throw new RuntimeException('Invoice VAT differs materially from its taxable order lines');
    }
    $sourceTax = salesPostingAllocateCents($taxGroups, round($invoiceVat, 2));
    $convertedTax = [];
    foreach ($sourceTax as $taxKey => $amount) $convertedTax[$taxKey] = $amount * $currencyRate / 100;
    $baseTax = salesPostingAllocateCents($convertedTax, afrund($invoiceVat * $currencyRate / 100, 2));
    foreach ($taxMembers as $taxKey => $members) {
        $rawBase = [];
        foreach ($members as $key => $_) $rawBase[$key] = $groups[$key] * $currencyRate / 100;
        foreach (salesPostingAllocateCents($rawBase, $baseTax[$taxKey]) as $key => $amount) $result[$key] = $amount;
    }
    return $result;
}
