<?php
// tests/characterization/support/run_cli_mail.php
//
// Child-process runner for the sender of the CLI jobs' mails (tools/reminder_due.php, tools/stripe_charge_due.php).
// It starts as those jobs do: CLI with no web request (no SERVER_NAME or HTTP_HOST), includes/connect.php, a
// connection to the ledger, and then cli_server_name() with the job's --host value, as both jobs call it once
// connected. It then sends one mail through this server's own mailer (PHP mail()) with the sender the jobs use:
// 'stripe' = stripe_mail_send() (charge job, link reminders), 'classic' = send_mails() (classic reminders).
// The test gives the child a sendmail_path that keeps the message (support/fake_sendmail.php).
//
// argv: 1 = ledger db
//       2 = stripe | classic
//       3 = the job's --host value, or "" for none
//
// Prints "SERVER <name>" (what cli_server_name() set, empty for none) and "SENT <result>".
//
// History:
// 20261126 CL/ASR Created for CliMailSenderTest (review round 1, I6).

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_cli_mail.php <ledger db> <stripe|classic> [<host>]\n");
    exit(2);
}
$chartestLedger = $argv[1];
$chartestKind = $argv[2];
$chartestHost = $argv[3] ?? '';

error_reporting(E_ERROR | E_PARSE); // the legacy mail code is warning-noisy
unset($_SERVER['SERVER_NAME'], $_SERVER['HTTP_HOST']); // cron gives the job neither
$_SERVER['REQUEST_URI'] = '/cli/tools/reminder_due.php';
$_SERVER['REMOTE_ADDR'] = 'cli';
chdir(dirname(__DIR__, 3) . '/debitor'); // as tools/reminder_due.php: send_mails() reaches ../temp and ../../vendor from here
require dirname(__DIR__, 3) . '/includes/db_query.php';
require dirname(__DIR__, 3) . '/includes/connect.php';
require_once dirname(__DIR__, 3) . '/includes/std_func.php';
require_once dirname(__DIR__, 3) . '/includes/stripeIncludes/stripeInvoice.php';
require_once dirname(__DIR__, 3) . '/includes/formFuncIncludes/sendMail.php';

$connection = db_connect($sqhost, $squser, $sqpass, $chartestLedger);
if (!$connection) {
    fwrite(STDERR, "no connection to $chartestLedger\n");
    exit(1);
}
$db = $chartestLedger;
$webservice = true;
$bruger_id = 0;
$ansat_id = 0;
$brugernavn = 'run_cli_mail';
$sprog_id = 1;
$charset = 'UTF-8';
$formular = 6;
$formularsprog = 'Dansk';
$mailantal = 1;
stripe_settings_flush();

print 'SERVER ' . cli_server_name($chartestHost) . "\n";
if ($chartestKind === 'stripe') {
    $chartestResult = stripe_mail_send('kunde@example.com', 'Rykker', '<p>Rykker</p>', 'Rykker');
} else {
    @unlink("../temp/$db/mailchk.php"); // send_mails() refuses the same mail again within 30 s; the test sends it in a row
    ob_start();
    $chartestResult = send_mails(0, '', 'kunde@example.com', 'Dansk', 6, 'Rykker', 'Rykker', '', 1);
    ob_end_clean();
}
print 'SENT ' . var_export($chartestResult, true) . "\n";
