<?php
// 20260907 CDX/LH Pure preliminary journal checks and invoice button predicates.
// 20260908 CDX/LH Document the separate legacy voucher and grand-total checks.

/** This predicate is also used by the existing posting preview.
 *  @param array<int,mixed> $voucherDifferences */
function saldi_assist_has_differences(float $difference, array $voucherDifferences): bool
{
    return abs($difference) >= 0.01 || count($voucherDifferences) > 0;
}

/** Mirrors the final payment gate on the Fakturér button, including its exceptions. */
function saldi_assist_invoice_payment_locked($paid, bool $paymentLink, string $lock, string $reference, string $method): bool
{
    return !$paid && $paymentLink && $lock === 'on'
        && $reference !== 'Magento' && !in_array($method, ['Konto', 'Kontant'], true);
}

/** Database decimals are parsed at SALDI's stored three-decimal precision. */
function saldi_assist_milli($value): int
{
    if (!preg_match('/^(-?)([0-9]{1,12})(?:\.([0-9]{1,3}))?$/D', (string)$value, $parts)) {
        throw new InvalidArgumentException('invalid_decimal');
    }
    $milli = (int)$parts[2] * 1000 + (int)str_pad($parts[3] ?? '', 3, '0');
    return ($parts[1] === '-') ? -$milli : $milli;
}

/** @return string Signed decimal with exactly three fractional digits. */
function saldi_assist_decimal(int $milli): string
{
    return ($milli < 0 ? '-' : '') . intdiv(abs($milli), 1000) . '.' . str_pad((string)(abs($milli) % 1000), 3, '0', STR_PAD_LEFT);
}

/** @param array<string,mixed> $header
 *  @param array<int,array<string,mixed>> $rows
 *  @param array<string,mixed> $reference
 *  @return array{status:string,issues:array,issue_count:int,issues_truncated:bool,differences:array,difference_count:int,differences_truncated:bool,coverage:array} */
function saldi_assist_validate_journal(array $header, array $rows, array $reference): array
{
    $issues = [];
    $add = static function (string $code, string $message, array $ids = [], string $severity = 'error') use (&$issues): void {
        $issues[] = ['code' => $code, 'message' => $message, 'row_ids' => $ids, 'severity' => $severity];
    };
    if (($header['bogfort'] ?? '') === 'V') {
        $add('journal_posted', 'Kladden er allerede bogført.');
    }
    if (!$rows) {
        $add('journal_empty', 'Den gemte kladde har ingen linjer.');
    }
    if (!empty($reference['has_draft'])) {
        $add('saved_draft_differs', 'SALDI har midlertidige kladdelinjer. Kontrollen gælder kun de gemte linjer.', [], 'warning');
    }
    $period = $reference['period'] ?? null;
    if (!$period) {
        $add('fiscal_year_missing', 'Regnskabsårets start og slutning kunne ikke bestemmes.');
    }
    $vouchers = [];
    $unsupported = [];
    $total = 0;
    $active = 0;
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        try {
            $amount = saldi_assist_milli($row['amount'] ?? '');
        } catch (InvalidArgumentException $error) {
            $add('invalid_amount', 'Beløbet kan ikke kontrolleres.', [$id]);
            continue;
        }
        if (!$amount) {
            continue; // Posting ignores zero-amount saved lines.
        }
        $active++;
        $date = (string)($row['transdate'] ?? '');
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date)
            || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) {
            $add('invalid_date', 'Bogføringsdato mangler eller er ugyldig.', [$id]);
        } elseif ($period && ($date < $period['start'] || $date > $period['end'])) {
            $add('outside_fiscal_year', 'Datoen ligger uden for det valgte regnskabsår.', [$id]);
        }
        if (in_array(substr($date, 0, 7), $reference['closed_months'] ?? [], true)) {
            $add('period_closed', 'Perioden ' . substr($date, 0, 7) . ' er lukket for bogføring.', [$id]);
        }
        if (empty($row['debet']) && empty($row['kredit'])) {
            $add('accounts_missing', 'Linjen har et beløb, men hverken debet- eller kreditkonto.', [$id]);
        }
        foreach (['debet' => 'd_type', 'kredit' => 'k_type'] as $side => $typeField) {
            $number = (string)($row[$side] ?? '');
            if (!$number || $number === '0') {
                continue;
            }
            $type = trim((string)($row[$typeField] ?? ''));
            if (!in_array($type, ['F', 'D', 'K'], true)) {
                $add('account_type_invalid', 'Kontotype skal være F, D eller K.', [$id]);
                continue;
            }
            $account = $reference['accounts'][$type . ':' . $number] ?? null;
            if (!$account) {
                $add('account_missing', 'Konto ' . $type . ' ' . $number . ' findes ikke i det valgte regnskabsår/kartotek.', [$id]);
                continue;
            }
            if ($type === 'F' && (!empty($account['lukket']) || in_array($account['kontotype'] ?? '', ['H', 'Z'], true))) {
                $add('account_unavailable', 'Finanskonto ' . $number . ' er lukket eller kan ikke bruges til postering.', [$id]);
            }
            if ($type !== 'F') {
                $unsupported['customer_supplier_posting'] = true;
                $group = $reference['groups'][($type === 'D' ? 'DG' : 'KG') . ':' . ($account['gruppe'] ?? '')] ?? null;
                if (!$group || empty($reference['accounts']['F:' . ($group['box2'] ?? '')])) {
                    $add('control_account_missing', 'Kontoens gruppe mangler en gyldig samlekonto.', [$id]);
                }
            }
            $override = trim((string)($row[$side . 'vat'] ?? ''));
            $otherOverride = trim((string)($row[($side === 'debet' ? 'kredit' : 'debet') . 'vat'] ?? ''));
            // A confirmed override on one side leaves the other side intentionally blank (bogfor.php).
            $vat = ($override !== '' || $otherOverride !== '') ? $override : trim((string)($account['moms'] ?? ''));
            if (empty($row['momsfri']) && $vat) {
                $unsupported['vat_calculation'] = true;
                $vatGroup = $reference['groups'][substr($vat, 0, 1) . 'M:' . substr($vat, 1)] ?? null;
                if (!$vatGroup || !is_numeric($vatGroup['box2'] ?? null)
                    || empty($reference['accounts']['F:' . ($vatGroup['box1'] ?? '')])) {
                    $add('vat_setup_missing', 'Momskode ' . $vat . ' mangler sats eller en gyldig momskonto.', [$id]);
                }
            }
        }
        $currency = (int)($row['valuta'] ?? 0);
        if ($currency) {
            $unsupported['currency_conversion'] = true;
            $rate = $reference['rates'][$currency . ':' . $date] ?? null;
            $group = $reference['groups']['VK:' . $currency] ?? null;
            if (!$rate || (float)$rate <= 0) {
                $add('exchange_rate_missing', 'Valutakurs mangler på bogføringsdatoen.', [$id]);
            }
            if (!$group || empty($reference['accounts']['F:' . ($group['box3'] ?? '')])) {
                $add('currency_account_missing', 'Valutaen mangler en gyldig differencekonto.', [$id]);
            }
        }
        $voucher = (string)($row['bilag'] ?? '');
        if (!isset($vouchers[$voucher])) {
            $vouchers[$voucher] = ['voucher' => $voucher, 'milli' => 0, 'row_ids' => [], 'foreign' => false];
        }
        $signed = (!empty($row['debet']) ? $amount : 0) - (!empty($row['kredit']) ? $amount : 0);
        $vouchers[$voucher]['milli'] += $signed;
        $vouchers[$voucher]['row_ids'][] = $id;
        $vouchers[$voucher]['foreign'] = $vouchers[$voucher]['foreign'] || $currency !== 0;
        $total += $signed;
        foreach (['afd', 'projekt', 'ansat'] as $dimension) {
            if (!empty($row[$dimension])) {
                $unsupported['dimensions'] = true;
            }
        }
    }
    if (!$active && $rows) {
        $add('no_postings', 'Kladden har ingen gemte linjer med et beløb.');
    }
    $differences = [];
    foreach ($vouchers as $voucher) {
        // Do not sum mixed currencies as if they were DKK or apply invented FX tolerances.
        // bogfor.php populates diffbilag from rounded voucher balances before the shared
        // grand-total predicate: a 0.005 voucher difference must not be ignored here.
        if (!$voucher['foreign'] && round($voucher['milli'] / 1000, 2) != 0) {
            $differences[] = ['voucher' => $voucher['voucher'], 'difference' => saldi_assist_decimal($voucher['milli']), 'currency' => 'DKK', 'row_ids' => $voucher['row_ids']];
            $add('voucher_unbalanced', 'Bilag ' . $voucher['voucher'] . ' har en difference på ' . saldi_assist_decimal($voucher['milli']) . ' DKK.', $voucher['row_ids']);
        }
    }
    if (!isset($unsupported['currency_conversion']) && saldi_assist_has_differences($total / 1000, [])) {
        $add('journal_unbalanced', 'Kladdens samlede difference er ' . saldi_assist_decimal($total) . ' DKK.');
    }
    $errors = array_filter($issues, static fn(array $issue): bool => $issue['severity'] === 'error');
    return [
        'status' => $errors ? 'blocked' : ($unsupported ? 'incomplete' : 'checks_passed'),
        'issues' => array_slice($issues, 0, 100), 'issue_count' => count($issues), 'issues_truncated' => count($issues) > 100,
        'differences' => array_slice($differences, 0, 100),
        'difference_count' => count($differences), 'differences_truncated' => count($differences) > 100,
        'coverage' => [
            'checked' => ['saved_rows', 'accounts', 'fiscal_dates', 'closed_periods', 'domestic_voucher_balance', 'vat_and_currency_setup'],
            'not_checked' => array_merge(['posting_transaction', 'open_item_matching'], array_keys($unsupported)),
            'can_post' => null,
        ],
    ];
}
