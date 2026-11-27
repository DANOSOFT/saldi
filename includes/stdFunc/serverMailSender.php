<?php
// includes/stdFunc/serverMailSender.php
//
// History:
// 20261126 CL/ASR Created (review round 1, I6).
//                 send_mails() (includes/formFuncIncludes/sendMail.php) and stripe_mail_send() (includes/stripeIncludes/stripeInvoice.php) take the sender for this server's own mailer from server_mail_from().
//                 The CLI jobs tools/reminder_due.php and tools/stripe_charge_due.php give it the server name with cli_server_name(), so their mails get the same sender as a web request's.

if (!function_exists('server_mail_host')) {
/**
 * A host name as it may stand after the @ of a mail address, in lower case.
 *
 * @param mixed $candidate SERVER_NAME, a --host option or the host of a URL.
 * @return string The host, or '' when the candidate is not a host name with a dot.
 */
function server_mail_host($candidate): string {
	$host = strtolower(trim((string)$candidate));
	if (strlen($host) > 253 || !preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', $host)) return '';
	return $host;
}
}

if (!function_exists('server_mail_from')) {
/**
 * The sender of a mail handed to this server's own mailer (no mail server set under Indstillinger > E-mail).
 * On a saldi.dk server it is <ledger>@<server name>, an address the server may send for (SPF), instead of the
 * company's own address, whose domain does not let the server send for it. Elsewhere, or while the server
 * name is unknown, the company's address stays.
 *
 * @param string $from The company's own address.
 * @return string The sender to use.
 */
function server_mail_from($from) {
	$server = server_mail_host($_SERVER['SERVER_NAME'] ?? '');
	$ledger = (string)($GLOBALS['db'] ?? '');
	if ($server === '' || $ledger === '' || strpos($server, 'saldi.dk') === false) return $from;
	return $ledger . '@' . $server;
}
}

if (!function_exists('cli_server_name')) {
/**
 * A CLI job has no web request, so the mail code cannot tell which server it runs on and would send from the
 * company's own address. This gives the job the server name a web request has: its --host=<name>, or else the
 * host of the ledger's Stripe public_base_url (the address its payment links use). Call it once the job is
 * connected to the ledger.
 *
 * @param string $hostOption The job's --host value ('' = none).
 * @return string The server name now in $_SERVER['SERVER_NAME'] and HTTP_HOST, or '' when none is known.
 */
function cli_server_name($hostOption = '') {
	if (PHP_SAPI !== 'cli') return '';
	$host = server_mail_host($hostOption);
	if ($host === '') {
		if (!function_exists('stripe_setting')) include_once(__DIR__ . '/../stripeIncludes/stripeSettings.php');
		$host = server_mail_host(parse_url((string)stripe_setting('public_base_url'), PHP_URL_HOST));
	}
	if ($host === '') return '';
	$_SERVER['SERVER_NAME'] = $host;
	$_SERVER['HTTP_HOST'] = $host;
	return $host;
}
}
