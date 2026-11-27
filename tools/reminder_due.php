<?php
// tools/reminder_due.php — ver 5.1.1 — 2026.11.27
// Copyright (c) 2026 Danosoft ApS. GNU GPL version 2 or later.
// 20261117 CDX/ASR Run only an explicitly named ledger's enabled reminder flow; dry-run is read-only.
// 20261126 CL/ASR Review round 1 (I6): the mails get the sender a web request gives them (<ledger>@<server> on a saldi.dk server): the server name comes from --host, or else from the host of the ledger's Stripe public_base_url (cli_server_name()).
// 20261127 ASR With $allowedLedgers in includes/connect.php the ledger named for one run must be listed, the master database too (saldi_ledger_guard()); the master stays connected for the registry.
// Usage: php tools/reminder_due.php --db=ledger_name [--host=server.name] [--dry-run]
// Run as the web-server user so PDF files have the same owner. Missing/off/copied configuration does nothing.
// Example cron (every 15 minutes): */15 * * * * php /path/to/saldi/tools/reminder_due.php --db=ledger_name

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
date_default_timezone_set('Europe/Copenhagen');
require_once __DIR__ . '/../includes/stdFunc/serverMailSender.php';
$options = getopt('', array('db:', 'host:', 'dry-run'));
$ledger = (string)($options['db'] ?? '');
$host = (string)($options['host'] ?? '');
if (!preg_match('/^[a-z0-9_]{1,40}$/', $ledger) || (isset($options['host']) && server_mail_host($host) === '')) {
    fwrite(STDERR, "Usage: php tools/reminder_due.php --db=ledger_name [--host=server.name] [--dry-run]\n");
    exit(1);
}
chdir(__DIR__ . '/../debitor');
$_SERVER['REQUEST_URI'] = '/cli/tools/reminder_due.php';
$_SERVER['REMOTE_ADDR'] = 'cli';
$_GET = $_POST = $_SESSION = array();
require __DIR__ . '/../includes/db_query.php';
require __DIR__ . '/../includes/connect.php';
saldi_ledger_guard($ledger);
require_once __DIR__ . '/../includes/std_func.php';
require_once __DIR__ . '/../includes/stripeIncludes/stripePdf.php';
require_once __DIR__ . '/../includes/reminderAutomation.php';
if (in_array($db_type, array('mysql', 'mysqli'), true)) {
    fwrite(STDERR, "Automatic reminders require PostgreSQL.\n");
    exit(1);
}
// The print engine gets these installation paths from the master database in online.php too.
$printTools = array('ps2pdf' => '/usr/bin/ps2pdf', 'weasyprint' => '/usr/bin/weasyprint');
foreach ($printTools as $name => $fallback) {
    $row = db_fetch_array(db_select("select var_value from settings where var_name='$name' order by id limit 1", __FILE__));
    $printTools[$name] = $row && trim((string)$row['var_value']) !== '' ? trim((string)$row['var_value']) : $fallback;
}
$ps2pdf = $printTools['ps2pdf'];
$weasyprint = $printTools['weasyprint'];
$pdftk = stripe_invoice_pdf_pdftk();
$row = db_fetch_array(db_select("select id,db from regnskab where db='" . db_escape_string($ledger) . "' and coalesce(lukket,'')<>'on'", __FILE__, true));
if (!$row) {
    fwrite(STDERR, "The ledger is unknown or closed.\n");
    exit(1);
}
$connection = db_connect($sqhost, $squser, $sqpass, $row['db']);
if (!$connection) exit(1);
$db = $row['db'];
$db_id = (int)$row['id'];
$bruger_id = 0;
$brugernavn = 'reminder_due';
$sprog_id = 1;
$s_id = 'cli';
$charset = 'UTF-8';
$exec_path = '';
$returside = '';
$popup = 0;
$webservice = true;
$regnaar = (string)newest_active_fiscal_year();
stripe_settings_flush();
cli_server_name($host); // the sender of the mails, as on the web
$dryRun = isset($options['dry-run']);
if ($dryRun) pg_query($connection, 'SET default_transaction_read_only=on');
try {
    $service = reminder_automation_service();
    $config = $service->config();
    if ($dryRun) {
        $result = array('dry_run' => true, 'mode' => $config['mode'], 'armed' => $config['armed'], 'candidates' => $service->preview());
    } elseif (!$config['armed'] || $config['mode'] === 'off') {
        $result = array('skipped' => 'off');
    } else {
        $result = $service->run('reminder_automation_render_send', $brugernavn);
    }
    print date('c') . ' ' . $db . ' ' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
    exit(empty($result['errors']) ? 0 : 2);
} catch (Throwable $error) {
    fwrite(STDERR, date('c') . ' ' . $db . ': ' . str_replace(array("\r", "\n"), ' ', $error->getMessage()) . "\n");
    exit(2);
}
