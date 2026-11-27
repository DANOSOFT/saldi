<?php
// tests/characterization/support/run_stripe_capture.php
//
// Child-process runner: a card payment of an invoiced order that Stripe has captured, booked by the production payment
// path (stripe_payment_apply_succeeded() -> stripe_payment_complete() in includes/stripeIncludes/) into the
// "Stripe-betalinger" journal, which the test then posts with run_bogfor_page.php.
//
// Nothing leaves the process: the Stripe transport, the customer mail and the bookkeeper alert are replaced here
// before the Stripe includes, which define their own only when missing. A Stripe request fails the run.
//
// argv: 1 = ordre_id of an invoiced order
//       2 = tenant db
//       3 = optional 'check': only load the invoice and print fully_open and open_item, nothing is recorded or booked
//
// Prints a single JSON object on the LAST line of stdout: whether the invoice was fully open (collectable by card) before
// the capture, result, the stripe_payments row's journal_line_id and last_error, the journal line (kladde_id, faktura,
// projekt, amount, debet, kredit) and the alert subjects.
//
// History:
// 20261126 ASR Created for the payment review (GPT-P-3): the Stripe journal line is posted through finans/bogfor.php.
// 20261126 ASR Payment review (GPT-G-2): prints fully_open and open_item as loaded before the capture; argv 3 'check' stops there.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_stripe_capture.php <ordre_id> <tenant_db>\n");
    exit(2);
}
$ordreId = (int)$argv[1];
$tenantDb = $argv[2];
$GLOBALS['chartest_stripe_alerts'] = array();

function stripeHttpRequest($method, $path, $params = array(), $opts = array())
{
    throw new RuntimeException("run_stripe_capture.php makes no Stripe request ($method $path)");
}

function stripe_mail_send($to, $subject, $html, $text, $attachments = array())
{
    return true;
}

function stripeAlertMail($subject, $body, $throttleKey = '', $throttleSec = 3600)
{
    $GLOBALS['chartest_stripe_alerts'][] = $subject;
    return true;
}

function stripe_log($line)
{
    fwrite(STDERR, "stripe_log: $line\n");
}

require __DIR__ . '/bootstrap_ordrefunc.php';
require_once __DIR__ . '/../../../includes/stripeIncludes/stripeInvoice.php';

$inv = stripe_invoice_load($ordreId);
if (!$inv) {
    ob_end_clean();
    fwrite(STDERR, "order $ordreId is not an invoice\n");
    exit(2);
}
$out = array('fully_open' => !empty($inv['fully_open']), 'open_item' => $inv['open_item']);
if (($argv[3] ?? '') === 'check') {
    ob_end_clean();
    fwrite(STDOUT, json_encode($out) . "\n");
    exit(0);
}
$pi = array(
    'id' => 'pi_chartest_' . $ordreId, 'customer' => 'cus_chartest', 'payment_method' => 'pm_chartest',
    'amount' => $inv['total_ore'], 'amount_received' => $inv['total_ore'], 'currency' => 'dkk', 'status' => 'succeeded',
    'metadata' => array('saldi_db' => $db, 'saldi_ordre_id' => $ordreId, 'saldi_fakturanr' => $inv['nr']),
    'latest_charge' => array('payment_method_details' => array('card' => array('brand' => 'visa', 'last4' => '4242'))),
);
stripe_payment_record($inv, $pi, 'page', false, 1);
$out['result'] = stripe_payment_apply_succeeded($pi, 'evt_chartest_' . $ordreId);
$payment = db_fetch_array(db_select("select journal_line_id, last_error from stripe_payments where payment_intent_id = '" . db_escape_string($pi['id']) . "'", __FILE__ . " linje " . __LINE__));
$out['journal_line_id'] = (int)$payment['journal_line_id'];
$out['last_error'] = $payment['last_error'];
$line = $payment['journal_line_id'] ? db_fetch_array(db_select("select kladde_id, faktura, projekt, amount, debet, kredit from kassekladde where id = " . (int)$payment['journal_line_id'], __FILE__ . " linje " . __LINE__)) : null;
$out['line'] = $line ? array_intersect_key($line, array_flip(array('kladde_id', 'faktura', 'projekt', 'amount', 'debet', 'kredit'))) : null;
$out['alerts'] = $GLOBALS['chartest_stripe_alerts'];

ob_end_clean();
fwrite(STDOUT, json_encode($out) . "\n");
