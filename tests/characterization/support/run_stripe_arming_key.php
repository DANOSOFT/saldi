<?php
// tests/characterization/support/run_stripe_arming_key.php
//
// Child-process runner: prints stripe_arming_key() for a ledger as a job on this installation computes it, with the
// master database of includes/connect.php. Saving the Stripe settings on this server stores the same value as armed_for.
//
// argv: 1 = ledger db
//
// History:
// 20261126 CL/ASR Created for StripeChargeAllArmingTest (review round 1, I15).

if ($argc < 2) {
    fwrite(STDERR, "usage: php run_stripe_arming_key.php <ledger db>\n");
    exit(2);
}
$_SERVER['REQUEST_URI'] = '/cli/tests/run_stripe_arming_key.php';
$_SERVER['REMOTE_ADDR'] = 'cli';
chdir(dirname(__DIR__, 3) . '/tools'); // includes/connect.php includes ../includes/... from the working directory, as for the tools
include dirname(__DIR__, 3) . '/includes/db_query.php';
include dirname(__DIR__, 3) . '/includes/connect.php';
include_once dirname(__DIR__, 3) . '/includes/stripeIncludes/stripeSettings.php';
print stripe_arming_key($argv[1]);
