<?php
// Child runner for PosPaymentContactGuardTest: lifts voucherPay() exactly as it ships, with the
// K61 contact check and its two settings queries stubbed, and reports whether the gift card
// number form was shown and which alerts the cashier got.
//
// usage: php run_pos_voucher_guard.php <betaling> <indbetaling> <ok|refuse> [giftcardNumber]
// prints JSON: {result, guardCalls, formShown, alerts}

require_once __DIR__ . '/pos_lift.php';

[$self, $betaling, $indbetaling, $verdict, $giftcardNumber] = $argv + [null, '', '', 'ok', null];
$GLOBALS['alerts'] = [];

/** includes/std_func.php's alert(), stubbed: records the text instead of printing a script. */
function alert($text)
{
    $GLOBALS['alerts'][] = (string)$text;
}

$GLOBALS['guardCalls'] = [];
$GLOBALS['guardVerdict'] = $verdict;
$GLOBALS['done'] = false;
$GLOBALS['result'] = null;
$regnaar = 2026;

/** The K61 check, stubbed: records the sale it was asked about and answers as the test says. */
function pos_validate_checkout_sale($orderId)
{
    $GLOBALS['guardCalls'][] = (int)$orderId;
    return $GLOBALS['guardVerdict'] === 'ok'
        ? ['ok' => true, 'message' => '']
        : ['ok' => false, 'message' => 'contact missing'];
}

/** Pay cards Kontant and Gavekort; only the second is a voucher. */
function db_select($qtxt, $where)
{
    return $qtxt;
}

function db_fetch_array($qtxt)
{
    if (str_contains($qtxt, "from grupper")) {
        return ['box5' => "Kontant\tGavekort"];
    }
    if (str_contains($qtxt, "voucherItems")) {
        return ['var_value' => "\ton"];
    }
    return false;
}

// voucherPay() exits after printing the card number form; the report is written either way.
register_shutdown_function(static function (): void {
    $printed = (string)ob_get_clean();
    echo json_encode(['result' => $GLOBALS['result'], 'guardCalls' => $GLOBALS['guardCalls'],
        'formShown' => str_contains($printed, 'giftcardNumber'), 'alerts' => $GLOBALS['alerts']]);
});

pos_lift_into([dirname(__DIR__, 4) . '/debitor/pos_ordre_includes/voucherFunc/voucherPay.php' => ['voucherPay']]);

$_POST = ['betaling' => $betaling, 'modtaget' => '100', 'sum' => '100', 'fokus' => '', 'pre_bordnr' => '', 'momssats' => '25',
    'leveret' => '', 'varenr_ny' => '', 'betvaluta' => 'DKK', 'indbetaling' => $indbetaling];
if ($giftcardNumber !== null) {
    $_POST['giftcardNumber'] = $giftcardNumber;
}
ob_start();
$GLOBALS['result'] = voucherPay(42, $betaling, '100');
