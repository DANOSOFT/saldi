<?php
// 20261126 CL/ASR New. Reads the plan off the recurring invoicing orders in the billing ledger (payment model B1, 12/10-2026: the recurring order is the truth for the plan).
//                 The operator panel fetches the orders through the REST API and stores the result in regnskab_entitlements with source 'billing' (admin/inc_betalinger.php).
//                 Only product numbers that name a catalogue plan count. Every other line (add-ons, fees, discounts, older products) is passed back to be shown, never guessed.

/**
 * Plan keys for the product numbers on the recurring orders.
 * F/FG, PRO/PG and BUS followed by the interval in months are the billing ledger's package products (FG alone is the invoicing fee).
 * FINANS, PROFESSIONEL and BUSINESS are the sign-up's products (includes/signupIncludes/signupFunc.php).
 * @param string $sku Product number from an order line.
 * @param array<string, string> $extra More product numbers (upper case) => plan key, e.g. the sign-up's billing_sku_<pakke> settings.
 * @return string|null The plan key, or null when the product is not a package.
 */
function billingPlanForSku(string $sku, array $extra = []): ?string
{
    $sku = strtoupper(trim($sku));
    if ($sku === '') {
        return null;
    }
    $fixed = ['FINANS' => 'finans', 'PROFESSIONEL' => 'professionel', 'BUSINESS' => 'business'];
    if (isset($extra[$sku])) {
        return $extra[$sku];
    }
    if (isset($fixed[$sku])) {
        return $fixed[$sku];
    }
    if (preg_match('/^(F|FG)(1|3|6|12)$/', $sku)) {
        return 'finans';
    }
    if (preg_match('/^(PRO|PG)(1|3|6|12)$/', $sku)) {
        return 'professionel';
    }
    if (preg_match('/^BUS(1|3|6|12)$/', $sku)) {
        return 'business';
    }
    return null;
}

/**
 * Whether a recurring order is still running: its next invoice date is at most 60 days ago.
 * An order that has not been invoiced for longer is left behind, not running.
 * @param string|null $nextInvoiceDate The order's next invoice date (nextfakt).
 * @param string $today Y-m-d.
 * @return bool
 */
function billingOrderIsRunning(?string $nextInvoiceDate, string $today): bool
{
    $next = trim((string)$nextInvoiceDate);
    $time = $next === '' ? false : strtotime($next);
    return $time !== false && date('Y-m-d', $time) >= date('Y-m-d', strtotime($today . ' -60 days'));
}

/**
 * The plan a billing customer pays for, from its recurring orders.
 * Only running orders count (billingOrderIsRunning()).
 * The plan is set only when the counting orders name exactly one plan. Several plans (e.g. one customer paying for several accounts) give no plan, only the list, in the catalogue's order.
 * @param array<int, array<string, mixed>> $orders Orders as the REST API returns them (orderNo, nextInvoiceDate), each with 'lines' (sku, description, quantity, price).
 * @param string $today Y-m-d.
 * @param array<string, string> $extra More product numbers => plan key, see billingPlanForSku().
 * @return array{plan: ?string, plans: string[], order: ?array{orderNo: string, nextInvoiceDate: string}, other: array<int, array{sku: string, description: string, quantity: float}>, active_orders: int, main_other: ?string}
 */
function billingPlanFromOrders(array $orders, string $today, array $extra = []): array
{
    $plans = [];
    $order = null;
    $other = [];
    $active = 0;
    $mainOther = null;
    $mainValue = 0.0;
    foreach ($orders as $o) {
        if (!billingOrderIsRunning($o['nextInvoiceDate'] ?? null, $today)) {
            continue;
        }
        $time = strtotime((string)$o['nextInvoiceDate']);
        $active++;
        foreach ((array)($o['lines'] ?? []) as $l) {
            $sku = trim((string)($l['sku'] ?? ''));
            $qty = (float)($l['quantity'] ?? 0);
            $desc = trim((string)($l['description'] ?? ''));
            $plan = $qty > 0 ? billingPlanForSku($sku, $extra) : null;
            if ($plan !== null) {
                if (!in_array($plan, $plans, true)) {
                    $plans[] = $plan;
                }
                if ($order === null) {
                    $order = ['orderNo' => (string)($o['orderNo'] ?? ''), 'nextInvoiceDate' => date('Y-m-d', $time)];
                }
                continue;
            }
            if ($sku === '' && $desc === '') {
                continue;
            }
            $other[] = ['sku' => $sku, 'description' => $desc, 'quantity' => $qty];
            // the dearest other line names the agreement when no plan is found (an older product rather than a fee or a discount)
            $value = $qty * (float)($l['price'] ?? 0);
            if ($value > $mainValue) {
                $mainValue = $value;
                $mainOther = $desc !== '' ? $desc : $sku;
            }
        }
    }
    // in the catalogue's order, so the same orders always give the same list whatever order the API returns them in
    $rank = array_flip(['finans', 'professionel', 'business', 'erp']);
    usort($plans, fn($x, $y) => ($rank[$x] ?? 9) <=> ($rank[$y] ?? 9));
    return [
        'plan' => count($plans) == 1 ? $plans[0] : null,
        'plans' => $plans,
        'order' => $order,
        'other' => $other,
        'active_orders' => $active,
        'main_other' => $mainOther,
    ];
}
