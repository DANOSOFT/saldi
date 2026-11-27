<?php
// Child runner for PosPaymentContactGuardTest: lifts afslut() and delbetal() exactly as they ship,
// with the K61 contact check stubbed, and reports whether a payment step was reached.
//
// usage: php run_pos_payment_guard.php <afslut|delbetal> <godkendt> <indbetaling> <ok|refuse>
// prints JSON: {result, guardCalls, paymentReached}

require_once __DIR__ . '/pos_lift.php';

[$self, $function, $godkendt, $indbetaling, $verdict] = $argv + [null, '', '', '', 'ok'];
if (!in_array($function, ['afslut', 'delbetal'], true)) {
    fwrite(STDERR, "usage: php run_pos_payment_guard.php <afslut|delbetal> <godkendt> <indbetaling> <ok|refuse>\n");
    exit(2);
}

final class PaymentReached extends Exception
{
}

$GLOBALS['guardCalls'] = [];
$GLOBALS['guardVerdict'] = $verdict;

/** The K61 check, stubbed: records the sale it was asked about and answers as the test says. */
function pos_validate_checkout_sale($orderId)
{
    $GLOBALS['guardCalls'][] = (int)$orderId;
    return $GLOBALS['guardVerdict'] === 'ok'
        ? ['ok' => true, 'message' => '']
        : ['ok' => false, 'message' => 'contact missing'];
}

// The first payment step of each function: reaching one means money could have moved.
function posvaluta($modtaget)
{
    throw new PaymentReached('posvaluta');
}

function voucherPay($id, $betaling, $modtaget)
{
    throw new PaymentReached('voucherPay');
}

$root = dirname(__DIR__, 4);
pos_lift_into([
    $root . '/debitor/pos_ordre_includes/exitFunc/exit.php' => ['afslut'],
    $root . '/debitor/pos_ordre_includes/paymentFunc/partPayment.php' => ['delbetal'],
]);

// delbetal() includes voucherPay.php by a relative path; from an empty directory that include
// finds nothing, and the stub above stands in for it.
$cwd = sys_get_temp_dir() . '/sd844guard' . getmypid();
@mkdir($cwd . '/sub', 0777, true);
chdir($cwd . '/sub');
set_error_handler(static function (int $no, string $msg): bool {
    return str_contains($msg, 'voucherPay.php');
});

$result = null;
$reached = null;
ob_start();
try {
    $result = $function === 'afslut'
        ? afslut(42, 'Kontant', '', '100', '', $indbetaling, $godkendt, '')
        : delbetal(42, 'Kontant', '', '100', '', $indbetaling, $godkendt, '', '', '');
} catch (PaymentReached $e) {
    $reached = $e->getMessage();
}
ob_end_clean();
restore_error_handler();
@rmdir($cwd . '/sub');
@rmdir($cwd);

echo json_encode(['result' => $result, 'guardCalls' => $GLOBALS['guardCalls'], 'paymentReached' => $reached]);
