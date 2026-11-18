<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/ebAccess.php --- ver 5.1.1 --- 2026-11-18 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20261006 CL/LH Initial version. Permissions, CSRF, flash messages, sanitized error texts and small view helpers shared by the Enable Banking pages (connect, callback, accounts, feed, settings).
// 20261007 CDX/LH Mapped Danish bank errors and preserved flash messages after releasing session locks.
// 20261007 CDX/LH Bound CSRF tokens and forms to the tenant and labelled additional review reasons.
// 20261022 CDX/LH Remove staging deferrals and explain closed ledger mappings.
// 20261028 CDX/LH Remove labels and permissions documentation for first-batch approval.
// 20261028 CDX/LH Explain period errors and normalize manual waiting and staleness status.
// 20261028 CL/LH Label pending lines "Nye", reword legacy after_review rows and add a deferral text for picked lines.
// 20261028 CDX/LH Give authorization failures their own Danish message while retaining sanitized support codes.
// 20261028 CL/LH Neutral text when a picked delivery finds the journal in use.
// 20261116 CL/LH Pull model (no deferral texts), rights through perm_can() (settings.finance, finans.kassekladde), texts through findtekst() (bank.* keys) as UTF-8 (ebT()).
// 20261116 CL/LH ebUnavailableReason() checks the company switch before the schema.
// 20261118 CL/LH ebPostButton() takes a target; ebShellRedirect() returns from the bank login (run as the whole window) into index/main.php's content frame.

require_once __DIR__ . '/ebConfig.php';
require_once __DIR__ . '/ebSchema.php';
require_once __DIR__ . '/ebClient.php';
require_once __DIR__ . '/ebConnections.php';
require_once __DIR__ . '/ebJournal.php';
require_once __DIR__ . '/ebSync.php';

/**
 * @param string $context 'authorization' for connect/reconnect, 'fetch' for fetching and delivery.
 * @return string Danish explanation of a provider or delivery error, without provider message text.
 */
function ebErrorText(?string $code, ?string $kind, string $context = 'fetch'): string
{
	$code = preg_replace('/[^A-Z0-9_]/', '', strtoupper($code ?? '')) ?? '';
	if ($context === 'authorization') {
		return ebT(findtekst('bank.error.authorization|Banken kunne ikke åbnes. Kontakt Saldi-support.', ebLang())) . ($code !== '' ? " (kode $code)" : '');
	}
	if (in_array($code, ['WRONG_TRANSACTIONS_PERIOD', 'WRONG_DATE_INTERVAL'], true) || $kind === 'period') {
		return ebT(findtekst('bank.error.period|Banken udleverer ikke posteringer så langt tilbage. Vælg en senere startdato under Indstillinger.', ebLang()));
	}
	if ($code === 'CONSENT_EXPIRED' || in_array($code, ['EXPIRED_SESSION', 'REVOKED_SESSION', 'CLOSED_SESSION', 'SESSION_DOES_NOT_EXIST',
		'ACCOUNT_DOES_NOT_EXIST', 'ASPSP_ACCOUNT_NOT_ACCESSIBLE', 'UNAUTHORIZED_ACCESS', 'WRONG_SESSION_STATUS'], true) || $kind === 'consent') {
		return ebT(findtekst('bank.error.consent|Adgangen til banken er udløbet eller tilbagekaldt. Forny adgangen.', ebLang()));
	}
	if ($code === 'ASPSP_RATE_LIMIT_EXCEEDED' || $kind === 'rate_limit' || $kind === 'rate_limited') {
		return ebT(findtekst('bank.error.rate_limit|Banken har midlertidigt begrænset antallet af opslag. Saldi prøver igen automatisk.', ebLang()));
	}
	if ($code === 'ASPSP_PSU_ACTION_REQUIRED' || $kind === 'action_required') {
		return ebT(findtekst('bank.error.action_required|Banken kræver, at du logger ind igen. Forny adgangen.', ebLang()));
	}
	if (in_array($code, ['ASPSP_ERROR', 'ASPSP_TIMEOUT', 'TRANSPORT_ERROR', 'INVALID_RESPONSE'], true) || $kind === 'transient' || $kind === 'timeout') {
		return ebT(findtekst('bank.error.transient|Banken svarede ikke. Saldi prøver igen automatisk.', ebLang()));
	}
	return ebT(findtekst('bank.error.other|Bankhentningen kunne ikke gennemføres. Prøv igen senere, eller kontakt Saldi-support.', ebLang())) . ($code !== '' ? " (kode $code)" : '');
}

/** @return string Escaped Danish message with a support code rendered in small text. */
function ebMessageHtml(string $message): string
{
	if (preg_match('/^(.*) \(kode ([A-Z0-9_]+)\)$/s', $message, $match)) {
		return ebH($match[1]) . " <small class='eb-muted'>" . ebH(sprintf(ebT(findtekst('bank.error.code|Kode: %s', ebLang())), $match[2])) . '</small>';
	}
	return ebH($message);
}

/** @return string Danish error and a small support code for a status panel. */
function ebErrorHtml(?string $code, ?string $kind = null): string
{
	$message = ebErrorText($code, $kind);
	$safeCode = preg_replace('/[^A-Z0-9_]/', '', strtoupper($code ?? '')) ?? '';
	if ($safeCode !== '' && !str_contains($message, '(kode ')) {
		$message .= " (kode $safeCode)";
	}
	return ebMessageHtml($message);
}

if (!function_exists('ebH')) {

	/**
	 * Escapes UTF-8 text for a page that is printed in the tenant's charset (the settings page, the kassekladde
	 * status icon), where the bank pages' UTF-8 would show as garbled letters on an ISO-8859-1 tenant.
	 *
	 * @param string $utf8 UTF-8 text.
	 * @return string Escaped text in the tenant's charset.
	 */
	function ebPageH(string $utf8): string {
		if (($GLOBALS['db_encode'] ?? 'UTF8') === 'UTF8') {
			return ebH($utf8);
		}
		return htmlspecialchars(mb_convert_encoding($utf8, 'ISO-8859-15', 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'ISO-8859-15');
	}

	/**
	 * Escapes a findtekst() text for HTML on the UTF-8 bank pages.
	 *
	 * @param string $text findtekst() output, in the tenant's encoding.
	 * @return string Escaped UTF-8 text.
	 */
	function ebHT(string $text): string {
		return ebH(ebT($text));
	}

	/**
	 * HTML-escapes a value for text and attribute context.
	 *
	 * @param string|int|float|null $value Value to print.
	 * @return string Escaped UTF-8 text.
	 */
	function ebH($value): string {
		return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/**
	 * Formats a decimal amount the Danish way (1.234,56) without float arithmetic.
	 * Two decimals are always shown; a third only when it is not zero.
	 *
	 * @param string|int|float|null $amount Signed decimal, e.g. '-1234.5' or '12.345'.
	 * @return string Formatted amount, e.g. '-1.234,50'. Empty input gives ''.
	 */
	function ebFormatAmount($amount): string {
		$text = trim((string)($amount ?? ''));
		if ($text === '') {
			return '';
		}
		if (!preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $text, $m)) {
			return $text;
		}
		$sign = ($m[1] === '-') ? '-' : '';
		$int = ltrim($m[2], '0');
		if ($int === '') {
			$int = '0';
		}
		$frac = substr(str_pad($m[3] ?? '', 3, '0'), 0, 3);
		if (substr($frac, 2, 1) === '0') {
			$frac = substr($frac, 0, 2);
		}
		if ($int === '0' && trim($frac, '0') === '') {
			$sign = '';
		}
		$grouped = '';
		while (strlen($int) > 3) {
			$grouped = '.' . substr($int, -3) . $grouped;
			$int = substr($int, 0, -3);
		}
		return $sign . $int . $grouped . ',' . $frac;
	}

	/**
	 * Formats a Y-m-d (or longer timestamp) value as dd-mm-yyyy.
	 *
	 * @param string|null $date Date or timestamp from the database.
	 * @return string Danish date, or '' when empty/invalid.
	 */
	function ebFormatDate(?string $date): string {
		$date = trim((string)$date);
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)) {
			return '';
		}
		return $m[3] . '-' . $m[2] . '-' . $m[1];
	}

	/**
	 * Formats a timestamp as "dd-mm-yyyy kl. HH:MM".
	 *
	 * @param string|null $timestamp Timestamp in server local time ('Y-m-d H:i:s').
	 * @return string Danish date and time, or '' when empty/invalid.
	 */
	function ebFormatDateTime(?string $timestamp): string {
		$timestamp = trim((string)$timestamp);
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $timestamp, $m)) {
			return ebFormatDate($timestamp);
		}
		return sprintf(ebT(findtekst('bank.format.datetime|%s kl. %s', ebLang())), $m[3] . '-' . $m[2] . '-' . $m[1], $m[4] . ':' . $m[5]);
	}

	/**
     * Returns a tenant-scoped CSRF token derived from a per-session secret.
	 *
	 * @return string 64 hex characters.
	 */
	function ebCsrfToken(): string {
		if (empty($_SESSION['eb_csrf_secret']) || !is_string($_SESSION['eb_csrf_secret'])) {
			$_SESSION['eb_csrf_secret'] = bin2hex(random_bytes(32));
		}
		return hash_hmac('sha256', (string)($GLOBALS['db'] ?? ''), $_SESSION['eb_csrf_secret']);
	}

	/**
	 * Hidden form field carrying the CSRF token.
	 *
	 * @return string HTML input element.
	 */
	function ebCsrfField(): string {
		return "<input type='hidden' name='eb_db' value='" . ebH($GLOBALS['db'] ?? '') . "'>"
			. "<input type='hidden' name='eb_csrf' value='" . ebH(ebCsrfToken()) . "'>";
	}

	/**
	 * Verifies the posted CSRF token; answers 403 and stops the request on mismatch.
	 *
	 * @return void
	 */
	function ebCsrfCheck(): void {
		$posted = $_POST['eb_csrf'] ?? '';
		$tenant = $_POST['eb_db'] ?? null;
		$secret = $_SESSION['eb_csrf_secret'] ?? '';
		$known = is_string($secret) && $secret !== '' ? hash_hmac('sha256', (string)($GLOBALS['db'] ?? ''), $secret) : '';
		if (!is_string($tenant) || $tenant !== ($GLOBALS['db'] ?? '')
			|| !is_string($posted) || $known === '' || !hash_equals($known, $posted)) {
			while (ob_get_level() > 0) {
				ob_end_clean();
			}
			http_response_code(403);
			header('Content-Type: text/html; charset=UTF-8');
			print "<!DOCTYPE html><html lang='da'><head><meta charset='UTF-8'><title>Saldi</title></head><body style='font-family:system-ui,sans-serif;padding:2em'>";
			print "<p>" . ebH(ebT(findtekst('bank.error.csrf|Regnskabet er skiftet, eller siden er udløbet. Handlingen blev ikke udført. Genindlæs siden i det rigtige regnskab og prøv igen.', ebLang()))) . "</p>";
			print "</body></html>";
			exit;
		}
	}

	/**
	 * Connection management (connect, map, pause, reconnect, disconnect, company toggle): write on the
	 * settings area the Bankforbindelse section belongs to (settings.finance, systemdata/settingsRegistry.php),
	 * checked like every other page through perm_can() (includes/permissions.php).
	 *
	 * @return bool
	 */
	function ebCanManage(): bool {
		return ebPermission('settings.finance', 'write');
	}

	/**
	 * Viewing the bank feed: read on Kassekladde (finans.kassekladde).
	 *
	 * @return bool
	 */
	function ebCanViewFeed(): bool {
		return ebPermission('finans.kassekladde', 'read');
	}

	/**
	 * Feed actions (Hent nu, pick, skip, restore, ...): write on Kassekladde.
	 *
	 * @return bool
	 */
	function ebCanActOnFeed(): bool {
		return ebPermission('finans.kassekladde', 'write');
	}

	/**
	 * @param string $key Permission key from includes/permissionRegistry.php.
	 * @param string $level 'read' or 'write'.
	 * @return bool Whether the logged-in user has the key at that level (role, else the rights string).
	 */
	function ebPermission(string $key, string $level): bool {
		include_once __DIR__ . '/../../includes/permissions.php';
		return perm_can($key, $level);
	}

	/**
	 * Stores a one-shot message shown after the next redirect.
	 *
	 * @param string $type 'ok', 'info', 'warn' or 'error'.
	 * @param string $text Plain Danish text (escaped when rendered).
	 * @return void
	 */
	function ebFlash(string $type, string $text): void {
		$reopen = session_status() !== PHP_SESSION_ACTIVE && session_id() !== '';
		if ($reopen) {
			session_start();
		}
		$_SESSION['eb_flash'][] = array('type' => $type, 'text' => $text);
		if ($reopen) {
			session_write_close();
		}
	}

	/**
	 * Leaves a message for the kassekladde page the feed sends the user back to (shown once, see finans/kassekladde.php).
	 *
	 * @param int $kladdeId kladdeliste.id
	 * @param string $text Plain UTF-8 text.
	 * @return void
	 */
	function ebJournalNotice(int $kladdeId, string $text): void {
		$reopen = session_status() !== PHP_SESSION_ACTIVE && session_id() !== '';
		if ($reopen) {
			session_start();
		}
		$_SESSION['eb_journal_notice'][$kladdeId] = $text;
		if ($reopen) {
			session_write_close();
		}
	}

	/**
	 * Returns and clears the pending flash messages.
	 *
	 * @return array<int, array{type: string, text: string}>
	 */
	function ebTakeFlash(): array {
		$reopen = session_status() !== PHP_SESSION_ACTIVE && session_id() !== '';
		if ($reopen) {
			session_start();
		}
		$flash = $_SESSION['eb_flash'] ?? array();
		unset($_SESSION['eb_flash']);
		if ($reopen) {
			session_write_close();
		}
		return is_array($flash) ? $flash : array();
	}

	/**
	 * Renders pending flash messages.
	 *
	 * @return string HTML.
	 */
	function ebRenderFlash(): string {
		$html = '';
		foreach (ebTakeFlash() as $f) {
			$type = in_array($f['type'] ?? '', array('ok', 'info', 'warn', 'error'), true) ? $f['type'] : 'info';
			$html .= "<div class='eb-flash eb-flash-$type'>" . ebMessageHtml($f['text'] ?? '') . "</div>\n";
		}
		return $html;
	}

	/**
	 * Back from the bank into Saldi's frame layout. The bank login and its callback run as the whole window
	 * (banks refuse to be shown in a frame), and index/main.php loads the page named after # in its content frame.
	 *
	 * @param string $page Page under bank_integration/, with its query string.
	 * @return never
	 */
	function ebShellRedirect(string $page): never {
		ebRedirect('../index/main.php#/bank_integration/' . ltrim($page, '/'));
	}

	/**
	 * Post/Redirect/Get: 303 to a relative URL and stop.
	 *
	 * @param string $url Relative URL inside the installation.
	 * @return never
	 */
	function ebRedirect(string $url): never {
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Location: ' . $url, true, 303);
		exit;
	}

	/**
	 * Removes anything that looks like a token, session id or account number from a log line.
	 *
	 * @param string $text Raw message.
	 * @return string Scrubbed and shortened message.
	 */
	function ebScrub(string $text): string {
		$text = preg_replace('/\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]){10,30}\b/', '[konto]', $text) ?? '';
		$text = preg_replace('/[A-Za-z0-9_\-\.+\/=]{24,}/', '[...]', $text) ?? '';
		return mb_substr(preg_replace('/\s+/', ' ', $text) ?? '', 0, 300);
	}

	/**
	 * Turns an exception from the backend into a calm Danish message for the page.
	 * User errors (EbUserError) carry their own Danish text; provider errors are mapped by kind;
	 * anything else gets a generic text with a reference that is also written to the PHP error log.
	 *
	 * @param Throwable $e The caught exception.
	 * @param string $context 'authorization' for connect/reconnect, 'fetch' for fetching and delivery.
	 * @return string Plain Danish text (escape when rendering).
	 */
	function ebErrorMessage(Throwable $e, string $context = 'fetch'): string {
		if ($e instanceof EbUserError) {
			return $e->getMessage();
		}
		if ($e instanceof EbApiException) {
			$code = preg_replace('/[^A-Z0-9_]/', '', strtoupper((string)$e->errorCode)) ?? '';
			$suffix = $code !== '' ? " (kode $code)" : '';
			$message = ebErrorText($code, $e->kind(), $context);
			return str_contains($message, '(kode ') ? $message : $message . $suffix;
		}
		$ref = strtoupper(bin2hex(random_bytes(3)));
		error_log('[enable-banking] ref=' . $ref . ' ' . get_class($e) . ': ' . ebScrub($e->getMessage())
			. ' at ' . basename($e->getFile()) . ':' . $e->getLine());
		return sprintf(ebT(findtekst('bank.error.unexpected|Der opstod en uventet fejl. Prøv igen, og kontakt Saldi-support hvis det fortsætter (reference %s).', ebLang())), $ref);
	}

	/**
	 * Why the bank feed cannot be used right now, or null when it can.
	 *
	 * @param bool $requireCompany Also require the company toggle (false on the settings page).
	 * @return string|null Danish explanation.
	 */
	function ebUnavailableReason(bool $requireCompany = true): ?string {
		if (!ebFeatureAvailable()) {
			return ebT(findtekst('bank.unavailable.server|Bankforbindelse er ikke sat op på denne Saldi-server. Kontakt Saldi-support, hvis du vil bruge den.', ebLang()));
		}
		if ($requireCompany && !ebCompanyEnabled()) {
			return ebT(findtekst('bank.unavailable.company|Bankforbindelse er ikke slået til for dette regnskab. Den slås til under Indstillinger > Bogføring & bank > Bankforbindelse.', ebLang()));
		}
		if (!ebSchemaReady()) {
			return ebT(findtekst('bank.unavailable.schema|Bankforbindelse er ikke klar i dette regnskab endnu. Log ud og ind igen, eller kontakt Saldi-support.', ebLang()));
		}
		return null;
	}

	/**
	 * Danish label for bank_accounts.state.
	 *
	 * @param string|null $state Account state.
	 * @return string
	 */
	function ebAccountStateLabel(?string $state): string {
		$labels = array(
			'new'          => findtekst('bank.account_state.new|Mangler opsætning', ebLang()),
			'active'       => findtekst('bank.account_state.active|Aktiv', ebLang()),
			'paused'       => findtekst('bank.account_state.paused|Sat på pause', ebLang()),
			'unsupported'  => findtekst('bank.account_state.unsupported|Ikke understøttet - kun DKK', ebLang()),
			'disconnected' => findtekst('bank.account_state.disconnected|Afbrudt', ebLang()),
		);
		return isset($labels[(string)$state]) ? ebT($labels[(string)$state]) : (string)$state;
	}

	/**
	 * Badge colour class for an account state.
	 *
	 * @param string|null $state Account state.
	 * @return string 'ok' | 'warn' | 'bad' | 'muted'
	 */
	function ebAccountStateTone(?string $state): string {
		$tones = array('new' => 'warn', 'active' => 'ok', 'paused' => 'muted', 'unsupported' => 'muted', 'disconnected' => 'muted');
		return $tones[(string)$state] ?? 'muted';
	}

	/**
	 * Danish label for bank_connections.status.
	 *
	 * @param string|null $status Connection status.
	 * @return string
	 */
	function ebConnectionStatusLabel(?string $status): string {
		$labels = array(
			'active'       => findtekst('bank.connection_status.active|Aktiv', ebLang()),
			'expired'      => findtekst('bank.connection_status.expired|Adgangen er udløbet', ebLang()),
			'revoked'      => findtekst('bank.connection_status.revoked|Adgangen er tilbagekaldt', ebLang()),
			'disconnected' => findtekst('bank.connection_status.disconnected|Afbrudt', ebLang()),
			'replaced'     => findtekst('bank.connection_status.replaced|Erstattet af ny forbindelse', ebLang()),
			'error'        => findtekst('bank.connection_status.error|Fejl', ebLang()),
		);
		return isset($labels[(string)$status]) ? ebT($labels[(string)$status]) : (string)$status;
	}

	/**
	 * Danish tab label for bank_transactions.review_state.
	 *
	 * @param string $state Review state.
	 * @return string
	 */
	function ebReviewStateLabel(string $state): string {
		$labels = array(
			'review'      => findtekst('bank.tab.review|Til gennemsyn', ebLang()),
			'pending'     => findtekst('bank.tab.pending|Nye', ebLang()),
			'imported'    => findtekst('bank.tab.imported|Importeret', ebLang()),
			'ignored'     => findtekst('bank.tab.ignored|Sprunget over', ebLang()),
			'pre_cutover' => findtekst('bank.tab.pre_cutover|Før startdato', ebLang()),
		);
		return isset($labels[(string)$state]) ? ebT($labels[(string)$state]) : (string)$state;
	}

	/**
	 * Danish explanation of bank_transactions.review_reason.
	 *
	 * @param string|null $reason Review reason.
	 * @return string
	 */
	function ebReviewReasonLabel(?string $reason): string {
		$labels = array(
			'missing_date'         => findtekst('bank.reason.missing_date|Banken har ikke sendt en bogføringsdato', ebLang()),
			'unsupported_currency' => findtekst('bank.reason.unsupported_currency|Valutaen understøttes ikke - kun DKK', ebLang()),
			'bad_amount'           => findtekst('bank.reason.bad_amount|Banken har sendt et ugyldigt beløb', ebLang()),
			'closed_period'        => findtekst('bank.reason.closed_period|Momsperioden for datoen er lukket', ebLang()),
			'outside_fiscal_year'  => findtekst('bank.reason.outside_fiscal_year|Datoen ligger uden for det regnskabsår, du arbejder i', ebLang()),
			'possible_duplicate'   => findtekst('bank.reason.possible_duplicate|Mulig dublet - en ens postering findes allerede', ebLang()),
			'changed_after_import' => findtekst('bank.reason.changed_after_import|Banken har ændret posteringen efter import', ebLang()),
			'no_ledger_account'    => findtekst('bank.reason.no_ledger_account|Bogføringskontoen findes ikke eller er lukket i kontoplanen for året', ebLang()),
			'after_review'         => findtekst('bank.reason.after_review|Kontrollér, at posteringen ikke allerede er bogført, før du lægger den i kassekladden', ebLang()),
			'base_currency'        => findtekst('bank.reason.base_currency|Regnskabets basisvaluta skal være DKK før bankimport', ebLang()),
			'not_pending'          => findtekst('bank.reason.not_pending|Posteringen ligger ikke længere under Nye', ebLang()),
		);
		return isset($labels[(string)$reason]) ? ebT($labels[(string)$reason]) : (string)$reason;
	}

	/**
	 * Danish label for a bank_sync_runs.status / ebSyncAccount() status.
	 *
	 * @param string|null $status Run status.
	 * @return string
	 */
	function ebRunStatusLabel(?string $status): string {
		$labels = array(
			'running'         => findtekst('bank.run_status.running|I gang', ebLang()),
			'ok'              => findtekst('bank.run_status.ok|Gennemført', ebLang()),
			'partial'         => findtekst('bank.run_status.partial|Delvist gennemført', ebLang()),
			'failed'          => findtekst('bank.run_status.failed|Fejlede', ebLang()),
			'rate_limited'    => findtekst('bank.run_status.rate_limited|Banken begrænser hentninger', ebLang()),
			'consent_expired' => findtekst('bank.run_status.consent_expired|Adgangen er udløbet', ebLang()),
			'skipped'         => findtekst('bank.run_status.skipped|Sprunget over - en hentning kører allerede', ebLang()),
		);
		return isset($labels[(string)$status]) ? ebT($labels[(string)$status]) : (string)$status;
	}

	/**
	 * Brings an ebAccountStatus() result into one predictable shape for the views, accepting the
	 * documented keys plus a few likely spellings, so a renamed key degrades to "unknown" instead of
	 * a warning.
	 *
	 * @param array<string, mixed> $s Raw status from ebAccountStatus().
	 * @param array<string, mixed> $account Optional bank_accounts row (ebListAccounts()) for keys the status lacks.
	 * @param array<string, mixed> $connection Optional bank_connections row (ebListConnections()).
	 * @return array{
	 *   state: string,                 bank_accounts.state.
	 *   connection_status: string,     bank_connections.status ('' when unknown).
	 *   valid_until: string,           Consent expiry timestamp ('' when unknown).
	 *   days_left: int|null,           Whole days until expiry, negative/0 when expired, null when unknown.
	 *   expired: bool,                 Consent expired or connection expired/revoked.
	 *   last_fetch_at: string,         Last fully successful fetch.
	 *   last_import_at: string,        Last time >= 1 journal line was delivered.
	 *   next_fetch_at: string,         Next scheduled fetch ('' = not scheduled).
	 *   queued: int,                   Transactions under Nye (pending).
	 *   waiting: int,                  Pending unlinked rows waiting to be picked.
	 *   waiting_oldest: string|null,   Oldest accounting date among those rows.
	 *   stale_hours: int|null,         Whole hours since the last successful fetch.
	 *   review: int,                   Transactions waiting for review.
	 *   counts: array<string, int>,    Count per review_state.
	 *   last_run_status: string,       Status of the latest sync run.
	 *   last_run_at: string,           When the latest run finished/started.
	 *   last_error_code: string,       Provider or internal error code.
	 *   last_error: string             Sanitized error text.
	 * }
	 */
	function ebNormalizeStatus(array $s, array $account = array(), array $connection = array()): array {
		$counts = array();
		$rawCounts = $s['counts'] ?? array();
		if (is_array($rawCounts)) {
			foreach ($rawCounts as $k => $v) {
				$counts[(string)$k] = (int)$v;
			}
		}
		$lastRun = (isset($s['last_run']) && is_array($s['last_run'])) ? $s['last_run'] : array();
		$validUntil = (string)($s['valid_until'] ?? $s['consent_valid_until'] ?? '');
		$daysLeft = $s['days_left'] ?? $s['consent_days_left'] ?? null;
		if ($daysLeft === null && $validUntil !== '' && ($ts = strtotime($validUntil)) !== false) {
			$daysLeft = (int)floor(($ts - time()) / 86400);
		}
		$connectionStatus = (string)($s['connection_status'] ?? $connection['status'] ?? '');
		$expiredByDate = false;
		if ($validUntil !== '' && ($ts = strtotime($validUntil)) !== false) {
			$expiredByDate = $ts <= time();
		}
		$lastErrorCode = (string)($s['last_error_code'] ?? $account['last_error_code'] ?? '');
		return array(
			'state'             => (string)($s['state'] ?? ''),
			'connection_status' => $connectionStatus,
			'valid_until'       => $validUntil,
			'days_left'         => $daysLeft === null ? null : (int)$daysLeft,
			'expired'           => $expiredByDate || in_array($connectionStatus, array('expired', 'revoked'), true)
				|| $lastErrorCode === 'consent_expired',
			'last_fetch_at'     => (string)($s['last_fetch_at'] ?? ''),
			'last_import_at'    => (string)($s['last_import_at'] ?? ''),
			'next_fetch_at'     => (string)($s['next_fetch_at'] ?? ''),
			'queued'            => (int)($s['queued'] ?? $counts['pending'] ?? 0),
			'waiting'           => max(0, (int)($s['waiting'] ?? 0)),
			'waiting_oldest'    => ebDate($s['waiting_oldest'] ?? null),
			'stale_hours'       => isset($s['stale_hours']) ? max(0, (int)$s['stale_hours']) : null,
			'review'            => (int)($s['review'] ?? $counts['review'] ?? 0),
			'counts'            => $counts,
			'last_run_status'   => (string)($s['last_run_status'] ?? $lastRun['status'] ?? ''),
			'last_run_at'       => (string)($s['last_run_at'] ?? $lastRun['finished_at'] ?? $lastRun['started_at'] ?? ''),
			'last_error_code'   => $lastErrorCode,
			'last_error'        => (string)($s['last_error'] ?? $account['last_error'] ?? ''),
		);
	}

	/**
	 * Consent expiry text, e.g. "Adgangen udløber om 12 dage (18-10-2026)".
	 *
	 * @param array{days_left: int|null, valid_until: string, expired: bool} $status Normalized status.
	 * @return string
	 */
	function ebConsentText(array $status): string {
		if ($status['expired']) {
			return ebT(findtekst('bank.consent.expired|Adgangen til banken er udløbet', ebLang()));
		}
		if ($status['days_left'] === null) {
			return ebT(findtekst('bank.consent.unknown|Ukendt udløb', ebLang()));
		}
		$date = ebFormatDate($status['valid_until']);
		$days = (int)$status['days_left'];
		if ($days <= 0) {
			$txt = ebT(findtekst('bank.consent.today|Adgangen udløber i dag', ebLang()));
		} elseif ($days === 1) {
			$txt = ebT(findtekst('bank.consent.tomorrow|Adgangen udløber i morgen', ebLang()));
		} else {
			$txt = sprintf(ebT(findtekst('bank.consent.days|Adgangen udløber om %s dage', ebLang())), $days);
		}
		return $date !== '' ? "$txt ($date)" : $txt;
	}

	/**
	 * Index of a list of rows by their 'id' column.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows with an 'id'.
	 * @return array<int, array<string, mixed>>
	 */
	function ebIndexById(array $rows): array {
		$out = array();
		foreach ($rows as $row) {
			if (is_array($row) && isset($row['id'])) {
				$out[(int)$row['id']] = $row;
			}
		}
		return $out;
	}

	/**
	 * Top line (Tilbage button + title) used by every bank page; relies on page_header.php styles.
	 *
	 * @param string $title Page title (plain text).
	 * @param string $backHref Relative URL for the Tilbage button.
	 * @return string HTML.
	 */
	function ebRenderTopline(string $title, string $backHref): string {
		global $icon_back;
		$html = "<div class='topline'>";
		$html .= "<a class='topline-btn eb-back' href='" . ebH($backHref) . "' accesskey='L'>" . ($icon_back ?? '') . ebH(ebT(findtekst('bank.feed.back|Tilbage', ebLang()))) . "</a>";
		$html .= "<div class='topline-center'><span class='center-title'>" . ebH($title) . "</span></div>";
		$html .= "</div>\n";
		return $html;
	}

	/**
	 * A one-button POST form (CSRF protected). Only ids and an action travel in the form.
	 *
	 * @param string $action Form action URL.
	 * @param array<string, string|int> $fields Hidden fields.
	 * @param string $label Button text.
	 * @param string $class Extra button class ('', 'secondary', 'danger', 'small').
	 * @param string $confirm Optional confirm() question.
	 * @return string HTML.
	 */
	function ebPostButton(string $action, array $fields, string $label, string $class = '', string $confirm = '', string $target = ''): string {
		$targetAttribute = $target !== '' ? " target='" . ebH($target) . "'" : '';
		$html = "<form method='post' action='" . ebH($action) . "' class='eb-inline'$targetAttribute>" . ebCsrfField();
		foreach ($fields as $name => $value) {
			$html .= "<input type='hidden' name='" . ebH($name) . "' value='" . ebH($value) . "'>";
		}
		$onclick = $confirm !== '' ? " onclick='return confirm(" . ebH(json_encode($confirm, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) . ");'" : '';
		$html .= "<button type='submit' class='eb-btn " . ebH($class) . "'$onclick>" . ebH($label) . "</button></form>";
		return $html;
	}
}
