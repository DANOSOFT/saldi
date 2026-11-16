<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/errorReport.php --- ver 5.1.1 --- 2026.11.13 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261104 CL/ASR Created: the error box that replaces the plain alert "Uforudset hændelse, kontakt salditeamet på telefon 4690 2208" after a failed query (db_modify(), db_select()) and after a fatal PHP error.
//                 It is a calm card in the new look with a reference code (F-<date>-<6 chars>), an optional message and Send fejlrapport, which mails the stored details to Saldi support through index/fejlrapport.php.
//                 The browser never gets SQL, file paths or error texts: they stay in the session ($_SESSION['saldi_error_reports'], the 10 newest for 24 hours) until the user sends the report.
//                 Requests that want JSON get {ok:false,error:'db',ref,message}; CLI, cron, API and webservice callers keep the old alert. A page that closed its session gets the box without the form (the reference and the support address).
//                 A box on a page that moves on at once (a meta refresh right after the error) comes again on the next page until it has been seen. Texts 7830, 7833-7836, 7838-7840, 7842 and 7844-7848.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
if (!defined('SALDI_ERROR_KEEP')) define('SALDI_ERROR_KEEP', 10);        // stored reports per session
if (!defined('SALDI_ERROR_TTL')) define('SALDI_ERROR_TTL', 86400);       // a stored report can be sent for 24 hours
if (!defined('SALDI_ERROR_SUPPORT')) define('SALDI_ERROR_SUPPORT', 'support@danosoft.dk');

if (!function_exists('saldi_error_ref')) {
	/**
	 * The reference of this request's error, F-<yyyymmdd>-<6 chars>. One per request: later errors in the same request
	 * are added to the first one's record, so the box, the automatic mail and the report all carry the same code.
	 */
	function saldi_error_ref(): string
	{
		static $ref = null;
		if ($ref === null) {
			$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O, 1/I: read aloud on the phone without doubt
			$code = '';
			for ($i = 0; $i < 6; $i++) {
				$code .= $chars[random_int(0, strlen($chars) - 1)];
			}
			$ref = 'F-' . date('Ymd') . '-' . $code;
		}
		return $ref;
	}
}

if (!function_exists('saldi_error_utf8')) {
	/** A string as valid UTF-8: database texts of a LATIN9 ledger are converted, anything else is kept. */
	function saldi_error_utf8($s): string
	{
		$s = (string) $s;
		return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-15');
	}
}

if (!function_exists('saldi_error_h')) {
	/** HTML-escaped and pure ASCII (numeric entities), so the box reads the same on a UTF-8 and on an ISO-8859-1 page. */
	function saldi_error_h($s): string
	{
		$s = htmlspecialchars(saldi_error_utf8($s), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		return mb_encode_numericentity($s, array(0x80, 0x10FFFF, 0, 0x1FFFFF), 'UTF-8');
	}
}

if (!function_exists('saldi_error_session')) {
	/**
	 * Whether this request has a browser session to keep the details in. A page that closed its session early is
	 * opened again when nothing has been sent yet (returns 'reopened', and the caller closes it again).
	 * @return string 'active' | 'reopened' | ''
	 */
	function saldi_error_session(): string
	{
		if (PHP_SAPI === 'cli' || !function_exists('session_status')) {
			return '';
		}
		if (session_status() === PHP_SESSION_ACTIVE) {
			return 'active';
		}
		if (session_status() === PHP_SESSION_NONE && !headers_sent() && isset($_COOKIE[session_name()]) && @session_start()) {
			return 'reopened';
		}
		return '';
	}
}

if (!function_exists('saldi_error_box_allowed')) {
	/** False for CLI, cron, webservice and API requests: they keep their old answer (the alert text or a return value). */
	function saldi_error_box_allowed(): bool
	{
		if (PHP_SAPI === 'cli' || !empty($GLOBALS['webservice'])) {
			return false;
		}
		$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ($uri === '' || preg_match('#/(api|restapi|soapserver|soap|webservice)/#i', $uri)) {
			return false;
		}
		return true;
	}
}

if (!function_exists('saldi_error_header')) {
	/** A request header, lower-case ('' when absent). */
	function saldi_error_header(string $key): string
	{
		return isset($_SERVER[$key]) ? strtolower((string) $_SERVER[$key]) : '';
	}
}

if (!function_exists('saldi_error_wants_json')) {
	/**
	 * True when the caller reads the answer as JSON: it asks for JSON (Accept, Content-Type), the page has already declared a
	 * JSON answer, or the script only ever answers JSON. A plain XHR or fetch (X-Requested-With, Sec-Fetch-Dest: empty) gets
	 * the box script, as it got the old alert script: jQuery runs it when it inserts an HTML fragment.
	 */
	function saldi_error_wants_json(): bool
	{
		if (!empty($GLOBALS['saldi_error_json'])) {
			return true; // set by JSON endpoints (index/fejlrapport.php)
		}
		if (strpos(saldi_error_header('HTTP_ACCEPT'), 'application/json') !== false || strpos(saldi_error_header('CONTENT_TYPE'), 'application/json') !== false) {
			return true;
		}
		foreach (headers_list() as $header) {
			if (stripos($header, 'content-type:') === 0 && stripos($header, 'json') !== false) {
				return true; // the page has declared a JSON answer (e.g. debitor/accountLookupData.php)
			}
		}
		// Scripts that only ever answer JSON (the list of perm_request_wants_json() in includes/permissions.php).
		$script = basename(isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '');
		return in_array($script, array('rental.php', 'orderapi.php', 'save_date_settings.php'), true)
			|| ($script === 'formeditor.php' && !empty($_GET['fe_action']));
	}
}

if (!function_exists('saldi_error_script_request')) {
	/** True for a request made by a script (XHR, fetch) rather than a page load: nothing is added to its answer afterwards. */
	function saldi_error_script_request(): bool
	{
		return saldi_error_wants_json() || saldi_error_header('HTTP_X_REQUESTED_WITH') === 'xmlhttprequest' || saldi_error_header('HTTP_SEC_FETCH_DEST') === 'empty';
	}
}

if (!function_exists('saldi_error_navigation')) {
	/** True for a page load in a tab or a frame (not a script's request, an image or a download): it may show a box carried over. */
	function saldi_error_navigation(): bool
	{
		$mode = saldi_error_header('HTTP_SEC_FETCH_MODE');
		if ($mode !== '') {
			return $mode === 'navigate' && in_array(saldi_error_header('HTTP_SEC_FETCH_DEST'), array('', 'document', 'iframe', 'frame'), true);
		}
		$accept = saldi_error_header('HTTP_ACCEPT');
		return !saldi_error_script_request() && ($accept === '' || strpos($accept, 'text/html') !== false);
	}
}

if (!function_exists('saldi_error_html_answer')) {
	/** False when the answer is a redirect, a download or not HTML (PDF, CSV, JSON, an image): a box added at the end is not seen there. */
	function saldi_error_html_answer(): bool
	{
		$code = http_response_code();
		if (is_int($code) && $code >= 300 && $code < 400) {
			return false;
		}
		foreach (headers_list() as $header) {
			if (stripos($header, 'location:') === 0
				|| (stripos($header, 'content-disposition:') === 0 && stripos($header, 'attachment') !== false)
				|| (stripos($header, 'content-type:') === 0 && stripos($header, 'text/html') === false)) {
				return false;
			}
		}
		return true;
	}
}

if (!function_exists('saldi_error_output_started')) {
	/** Whether anything has been sent or buffered for the page yet. */
	function saldi_error_output_started(): bool
	{
		if (headers_sent()) {
			return true;
		}
		foreach (ob_get_status(true) as $buffer) {
			if (!empty($buffer['buffer_used'])) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('saldi_error_db_usable')) {
	/** False while the Postgres connection sits in an aborted transaction: every query (also findtekst()) would fail. */
	function saldi_error_db_usable(): bool
	{
		global $connection, $db_type;
		if (!function_exists('db_select')) {
			return false;
		}
		if (in_array((string) $db_type, array('mysql', 'mysqli'), true)) {
			return true;
		}
		if (!function_exists('pg_transaction_status') || !($connection instanceof \PgSql\Connection)) {
			return false;
		}
		$status = @pg_transaction_status($connection);
		return $status !== PGSQL_TRANSACTION_INERROR && $status !== PGSQL_TRANSACTION_UNKNOWN;
	}
}

if (!function_exists('saldi_error_seed_text')) {
	/**
	 * A text of the box from importfiler/tekster.csv (id, Dansk, English, Norsk), for when the database cannot be asked:
	 * a user who never saw the box has no translation cached yet. Only the box's own rows are kept.
	 */
	function saldi_error_seed_text(string $id, int $lang): string
	{
		static $rows = null;
		if ($rows === null) {
			$rows = array();
			$fh = @fopen(__DIR__ . '/../importfiler/tekster.csv', 'r');
			if ($fh) {
				while (($line = fgets($fh)) !== false) {
					$tab = strpos($line, "\t");
					$key = $tab === false ? '' : substr($line, 0, $tab);
					if ($key !== '' && ctype_digit($key) && ((int) $key === 30 || ((int) $key >= 7830 && (int) $key <= 7849))) {
						$rows[(int) $key] = explode("\t", rtrim($line, "\r\n"));
					}
				}
				fclose($fh);
			}
		}
		$col = ($lang >= 1 && $lang <= 3) ? $lang : 1;
		return ctype_digit($id) && isset($rows[(int) $id][$col]) ? (string) $rows[(int) $id][$col] : '';
	}
}

if (!function_exists('saldi_error_text')) {
	/**
	 * A text of the box in the user's language, as UTF-8. findtekst() only when the database answers; otherwise the
	 * session's cached translation, the seed file's or the Danish fallback.
	 */
	function saldi_error_text(string $idAndFallback, int $lang): string
	{
		list($id, $fallback) = array_pad(explode('|', $idAndFallback, 2), 2, '');
		$text = '';
		if (saldi_error_db_usable()) {
			if (!function_exists('findtekst')) {
				include_once(__DIR__ . '/stdFunc/findTxt.php');
			}
			try {
				$text = (string) findtekst($idAndFallback, $lang);
			} catch (\Throwable $e) {
				$text = ''; // e.g. a seed file it cannot open: the box must still come
			}
		} elseif (function_exists('findtekst_session_var') && isset($_SESSION[findtekst_session_var($id, $lang)])) {
			$text = (string) $_SESSION[findtekst_session_var($id, $lang)];
		}
		if ((trim($text) === '' || $text === $id) && $lang > 1) {
			$text = saldi_error_seed_text($id, $lang); // e.g. an aborted transaction: the translation from the seed file
		}
		if (trim($text) === '' || $text === $id) {
			$text = $fallback;
		}
		return saldi_error_utf8($text);
	}
}

if (!function_exists('saldi_error_custom_contact')) {
	/**
	 * The installation's own contact text ($customAlertText, settings alertText), or '' when it is unset or still the old
	 * standard text (text 534 "Uforudset hændelse, kontakt ... 4690 2208" in any language), which Indstillinger saves as the
	 * default and which would bring the phone number back.
	 */
	function saldi_error_custom_contact(): string
	{
		$text = isset($GLOBALS['customAlertText']) ? trim(saldi_error_utf8($GLOBALS['customAlertText'])) : '';
		if ($text === '') {
			return '';
		}
		$plain = mb_strtolower(preg_replace('/\s+/u', ' ', $text), 'UTF-8');
		$standard = preg_match('/^(uforudset hændelse|unforeseen event|uforutsett hendelse)\b/u', $plain)
			&& preg_match('/4690 ?2208/', $plain);
		return $standard ? '' : mb_substr($text, 0, 300, 'UTF-8');
	}
}

if (!function_exists('saldi_error_root_url')) {
	/** The URL path of the Saldi folder (e.g. /asr_dash) from wherever the running script lives. */
	function saldi_error_root_url(): string
	{
		$root = realpath(__DIR__ . '/..');
		$file = isset($_SERVER['SCRIPT_FILENAME']) ? realpath((string) $_SERVER['SCRIPT_FILENAME']) : false;
		$name = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
		if ($root !== false && $file !== false && strpos($file, $root . '/') === 0) {
			$rel = substr($file, strlen($root));
			if ($name !== '' && substr($name, -strlen($rel)) === $rel) {
				return substr($name, 0, -strlen($rel));
			}
		}
		return '..';
	}
}

if (!function_exists('saldi_error_store')) {
	/**
	 * Keep the details of an error in the session under this request's reference and return the reference.
	 * A second error in the same request is added to the first record (at most 5), not stored as a new one.
	 */
	function saldi_error_store(string $spor, string $errtxt, string $qtext, string $kind = 'db'): string
	{
		$ref = saldi_error_ref();
		if (!isset($_SESSION['saldi_error_reports']) || !is_array($_SESSION['saldi_error_reports'])) {
			$_SESSION['saldi_error_reports'] = array();
		}
		$reports = &$_SESSION['saldi_error_reports'];
		$now = time();
		foreach ($reports as $key => $report) {
			if (!is_array($report) || (int) ($report['time'] ?? 0) < $now - SALDI_ERROR_TTL) {
				unset($reports[$key]);
			}
		}
		$cut = function (string $s, int $max): string {
			$s = saldi_error_utf8($s);
			return mb_strlen($s, 'UTF-8') > $max ? mb_substr($s, 0, $max, 'UTF-8') . ' [...]' : $s;
		};
		if (isset($reports[$ref])) {
			if (count($reports[$ref]['more']) < 5) {
				$reports[$ref]['more'][] = array('spor' => $cut($spor, 300), 'error' => $cut($errtxt, 1000), 'query' => $cut($qtext, 1000));
			}
			return $ref;
		}
		$g = $GLOBALS;
		$reports[$ref] = array(
			'ref'         => $ref,
			'kind'        => $kind,
			'time'        => $now,
			'db'          => isset($g['db']) ? (string) $g['db'] : '',
			'company'     => isset($g['regnskab']) ? saldi_error_utf8($g['regnskab']) : '',
			'brugernavn'  => saldi_error_utf8(isset($g['brugernavn_raw']) ? $g['brugernavn_raw'] : (isset($g['brugernavn']) ? stripslashes((string) $g['brugernavn']) : '')),
			'bruger_id'   => isset($g['bruger_id']) ? (int) $g['bruger_id'] : 0,
			'uri'         => $cut(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '', 1000),
			'method'      => isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '',
			'spor'        => $cut($spor, 300),
			'error'       => $cut($errtxt, 4000),
			'query'       => $cut($qtext, 4000),
			'version'     => isset($g['version']) ? (string) $g['version'] : '',
			'php'         => PHP_VERSION,
			'user_agent'  => $cut(isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '', 400),
			'more'        => array(),
			'reported'    => 0,
		);
		// The newest SALDI_ERROR_KEEP only (insertion order = age).
		while (count($reports) > SALDI_ERROR_KEEP) {
			reset($reports);
			unset($reports[key($reports)]);
		}
		return $ref;
	}
}

if (!function_exists('saldi_error_box')) {
	/**
	 * Show the error box for a failed query (or a fatal error) and keep its details for a report.
	 * The caller keeps its own logging, mail and exit/continue; $exit says whether it stops right after.
	 * Without a session to keep the details in (a page that closed it after printing) the box has no form: it shows the
	 * reference and the support address, and the automatic mail to fejl@saldi.dk carries the same reference.
	 *
	 * @return string 'shown'    the box is on the page (or was already, earlier in this request)
	 *                'json'     the caller wants JSON: answered (when $exit) or left alone with a header (when not)
	 *                'nested'   called from inside the box itself (findtekst() failing too): the caller must neither
	 *                           print nor exit
	 *                'nosession' CLI, cron, API or webservice: the caller keeps its old alert
	 */
	function saldi_error_box(string $spor, string $errtxt, string $qtext, bool $exit = false, string $kind = 'db'): string
	{
		static $busy = false, $done = false, $bufferedPrints = 0;
		if ($busy) {
			return 'nested';
		}
		if (!saldi_error_box_allowed()) {
			return 'nosession';
		}
		$busy = true;
		try {
			$session = saldi_error_session();
			$ref = $session !== '' ? saldi_error_store($spor, $errtxt, $qtext, $kind) : saldi_error_ref();
			$lang = isset($GLOBALS['sprog_id']) && (int) $GLOBALS['sprog_id'] > 0 ? (int) $GLOBALS['sprog_id'] : 1;
			if (saldi_error_wants_json()) {
				if ($session === 'reopened') {
					session_write_close();
				}
				if (!$exit) {
					// The page goes on and prints its own answer; a JSON object here would break it.
					if (!headers_sent()) {
						header('X-Saldi-Error-Ref: ' . $ref);
					}
					return 'json';
				}
				$message = saldi_error_text('7844|Handlingen blev ikke gennemført, og fejlen er registreret.', $lang);
				while (ob_get_level() > 0 && @ob_end_clean()) {
				}
				if (!headers_sent()) {
					http_response_code(500);
					header('Content-Type: application/json; charset=utf-8');
					header('X-Saldi-Error-Ref: ' . $ref);
				}
				print json_encode(array('ok' => false, 'succes' => false, 'success' => false, 'error' => 'db', 'ref' => $ref, 'message' => $message, 'msg' => $message), JSON_INVALID_UTF8_SUBSTITUTE);
				$done = true;
				return 'json';
			}
			if ($done) {
				if ($session === 'reopened') {
					session_write_close();
				}
				return 'shown';
			}
			$csrf = '';
			if ($session !== '') {
				if (empty($_SESSION['csrf_token'])) {
					$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
				}
				$csrf = (string) $_SESSION['csrf_token'];
				if (!$exit) {
					// The page goes on and may move on at once (a meta refresh or location.href right after, which the old
					// blocking alert() survived): the next page loads show the box again until it has been seen there or
					// here (saldi_error_page_start(), sessionStorage in the script). 5 minutes, at most 3 page loads.
					$_SESSION['saldi_error_pending'] = array('ref' => $ref, 'time' => time(), 'left' => 3);
				}
				if ($session === 'reopened') {
					session_write_close();
				}
			}
			$html = saldi_error_box_html($ref, $lang, $csrf, saldi_error_root_url() . '/index/fejlrapport.php', saldi_error_custom_contact(), $exit);
			if ($exit && !saldi_error_output_started()) {
				// Nothing on the page yet and nothing will follow: a small page of its own.
				global $charset;
				$cs = (isset($charset) && $charset) ? (string) $charset : 'UTF-8';
				if (!headers_sent()) {
					header('Content-Type: text/html; charset=' . $cs);
				}
				print "<!DOCTYPE html>\n<html><head><meta charset='" . saldi_error_h($cs) . "'><meta name='viewport' content='width=device-width, initial-scale=1'>"
					. '<title>' . saldi_error_h(saldi_error_text('7830|Noget gik galt', $lang)) . "</title></head>\n<body style='margin:0;background:#f4f5f7'>\n"
					. $html . "\n</body></html>\n";
			} else {
				// Inside whatever the page has printed (or, when the page goes on, before it): the card is fixed on top.
				print "\n" . $html . "\n";
			}
			if (!$exit && isset($GLOBALS['saldi_error_ob_base']) && ob_get_level() > (int) $GLOBALS['saldi_error_ob_base']) {
				// Printed into an output buffer the page may throw away (ob_start(); ...; ob_end_clean()): not counted as shown,
				// so a later error prints it again and the shutdown function once more at the end (a second copy does nothing).
				$GLOBALS['saldi_error_box_buffered'] = $html;
				if (++$bufferedPrints >= 3) {
					$done = true; // not more copies than that in one request
				}
			} else {
				$done = true;
				$GLOBALS['saldi_error_box_shown'] = $ref;
			}
			return 'shown';
		} finally {
			$busy = false;
		}
	}
}

if (!function_exists('saldi_error_box_html')) {
	/**
	 * The box as one script that adds its markup and scoped style. Everything is ASCII (entities, \u escapes), so the page's charset does not matter.
	 * Without a CSRF token (no session) there is no form: the reference and the support address only.
	 * $exit: the request stopped, so the secondary button goes back to the previous page when there is one.
	 */
	function saldi_error_box_html(string $ref, int $lang, string $csrf, string $url, string $contact, bool $exit = false): string
	{
		$t = function (string $idAndFallback) use ($lang): string {
			return saldi_error_text($idAndFallback, $lang);
		};
		$form    = $csrf !== '';
		$title   = $t('7830|Noget gik galt');
		$lead    = $t('7844|Handlingen blev ikke gennemført, og fejlen er registreret.');
		$more    = $form ? $t('7845|Send en fejlrapport, hvis du vil have hjælp. Den indeholder de tekniske detaljer.')
			: $t('7848|Skriv til support@danosoft.dk og oplys fejlreferencen, hvis du vil have hjælp.');
		$refLbl  = $t('7833|Fejlreference');
		$msgLbl  = $t('7834|Hvad var du i gang med? (valgfrit)');
		$sendLbl = $t('7835|Send fejlrapport');
		$closeLbl = $t('7836|Luk');
		$support = SALDI_ERROR_SUPPORT;
		$mailto = function (string $text) use ($support, $ref): string {
			$link = "<a class='sfb-a' href='mailto:" . $support . '?subject=' . str_replace('-', '%2D', rawurlencode('Saldi fejlrapport ' . $ref)) . "'>" . $support . '</a>';
			return str_replace(saldi_error_h($support), $link, saldi_error_h($text));
		};
		$nw = function (string $html) use ($ref): string {
			return str_replace($ref, "<span class='sfb-nw'>" . $ref . '</span>', $html); // the reference never breaks over two lines
		};
		$texts = array(
			'sending' => saldi_error_h($t('7839|Sender ...')),
			'title'   => saldi_error_h($t('7842|Fejlrapporten er sendt')),
			'ok'      => saldi_error_h($t('7846|Tak. Rapporten er sendt til Saldis support. Vi kontakter dig, hvis vi har brug for flere oplysninger.')),
			'already' => $nw(saldi_error_h(sprintf($t('7840|Rapporten om denne fejl er allerede sendt (%s).'), $ref))),
			'fail'    => $nw($mailto(sprintf($t('7838|Rapporten kunne ikke sendes. Skriv til support@danosoft.dk og oplys %s.'), $ref))),
			'rate'    => $nw($mailto(sprintf($t('7847|Du har sendt mange rapporter den seneste time. Skriv til support@danosoft.dk og oplys %s.'), $ref))),
			'back'    => saldi_error_h($t('30|Tilbage')),
		);
		$warn = "<svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true' focusable='false'><path d='M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z'/><path d='M12 9v4'/><path d='M12 17h.01'/></svg>";
		$check = "<svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true' focusable='false'><path d='M20 6 9 17l-5-5'/></svg>";

		$css = "#sfb-root,#sfb-root *{box-sizing:border-box}"
			. "#sfb-root{position:fixed;top:0;right:0;bottom:0;left:0;z-index:2147483000;margin:0;padding:0;font:400 14px/1.45 Inter,system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#0b1a3a;text-align:left;letter-spacing:normal;text-transform:none;-webkit-font-smoothing:antialiased}"
			. "#sfb-root [hidden]{display:none!important}"
			. "#sfb-root .sfb-scrim{position:absolute;top:0;right:0;bottom:0;left:0;background:rgba(11,26,58,.42)}"
			. "#sfb-root .sfb-wrap{position:absolute;top:0;right:0;bottom:0;left:0;overflow-y:auto;display:flex;align-items:flex-start;justify-content:center;padding:12vh 16px 24px}"
			. "#sfb-root .sfb-card{position:relative;width:100%;max-width:440px;margin:0;padding:20px 22px 16px;background:#fff;border:1px solid #d8dbe0;border-radius:8px;box-shadow:0 12px 32px rgba(11,26,58,.18),0 2px 6px rgba(11,26,58,.08);outline:0}"
			. "#sfb-root .sfb-head{display:flex;align-items:center;gap:12px;margin:0 0 10px}"
			. "#sfb-root .sfb-ic{flex:0 0 32px;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fdf3e7;color:#a15c07}"
			. "#sfb-root .sfb-ic-ok{background:#e8f6ee;color:#15803d}"
			. "#sfb-root .sfb-ic svg{display:block}"
			. "#sfb-root .sfb-title{margin:0;padding:0;border:0;background:none;font-family:inherit;font-size:16px;font-weight:600;line-height:1.3;color:#0b1a3a;text-align:left}"
			. "#sfb-root .sfb-p{margin:0 0 6px;padding:0;font-size:14px;line-height:1.45;color:#22324f}"
			. "#sfb-root .sfb-p2{color:#5b6b85}"
			. "#sfb-root .sfb-contact{margin:6px 0 0;font-size:12.5px;line-height:1.4;color:#5b6b85}"
			. "#sfb-root .sfb-lbl{display:block;margin:14px 0 6px;padding:0;font-size:13px;font-weight:500;line-height:1.3;color:#33415c}"
			. "#sfb-root .sfb-ta{display:block;width:100%;min-height:72px;max-height:40vh;margin:0;padding:8px 10px;border:1px solid #c6d2e4;border-radius:6px;background:#fff;color:#0b1a3a;font-family:inherit;font-size:14px;font-weight:400;line-height:1.4;resize:vertical;box-shadow:none;outline:0}"
			. "#sfb-root .sfb-ta:hover{border-color:#a9b8cf}"
			. "#sfb-root .sfb-ta:focus{border-color:#4FA0EE;box-shadow:0 0 0 2px rgba(79,160,238,.45)}"
			. "#sfb-root .sfb-note{margin:12px 0 0;padding:9px 11px;border-radius:6px;font-size:13px;line-height:1.4}"
			. "#sfb-root .sfb-note-info{background:#f7f9fc;color:#5b6b85;border:1px solid #e4e8ef}"
			. "#sfb-root .sfb-note-ok{background:#e8f6ee;color:#14532d;border:1px solid #c9e9d5}"
			. "#sfb-root .sfb-note-bad{background:#fdecec;color:#9a3412;border:1px solid #f6d0c4}"
			. "#sfb-root .sfb-a{color:inherit;text-decoration:underline}"
			. "#sfb-root .sfb-nw{white-space:nowrap}"
			. "#sfb-root .sfb-foot{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin:16px 0 0;padding:14px 0 0;border-top:1px solid #eef1f6}"
			. "#sfb-root .sfb-ref{margin:10px 0 0;font-size:12px;line-height:1.5;color:#8a97ad}"
			. "#sfb-root .sfb-code{display:inline-block;margin-left:4px;padding:1px 6px;border:1px solid #e4e8ef;border-radius:4px;background:#f7f9fc;color:#33415c;font:500 12px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;-webkit-user-select:all;user-select:all}"
			. "#sfb-root .sfb-btn{display:inline-flex;align-items:center;justify-content:center;height:32px;min-width:0;margin:0;padding:0 14px;border:1px solid #c6d2e4;border-radius:6px;background:#fff;color:#0b1a3a;font-family:inherit;font-size:13.5px;font-weight:500;line-height:1;text-decoration:none;white-space:nowrap;cursor:pointer;box-shadow:none;text-shadow:none;width:auto}"
			. "#sfb-root .sfb-btn:hover{background:#eef2f8;border-color:#a9b8cf}"
			. "#sfb-root .sfb-btn:focus{outline:0}"
			. "#sfb-root .sfb-btn:focus-visible{box-shadow:0 0 0 2px rgba(79,160,238,.45)}"
			. "#sfb-root .sfb-primary{background:#114691;border-color:#114691;color:#fff}"
			. "#sfb-root .sfb-primary:hover{background:#0f3f83;border-color:#0f3f83;color:#fff}"
			. "#sfb-root .sfb-btn[disabled]{opacity:.6;cursor:default}"
			// 16px in the textarea on small screens: iOS Safari zooms the page into a smaller field when it gets focus.
			. "@media (max-width:420px){#sfb-root .sfb-wrap{padding:16px 12px}#sfb-root .sfb-card{padding:18px 16px 14px}#sfb-root .sfb-btn{flex:1 1 0}#sfb-root .sfb-ta{font-size:16px}}";

		$h = "<style id='sfb-style'>" . $css . "</style>"
			. "<div id='sfb-root'><div class='sfb-scrim'></div><div class='sfb-wrap'>"
			. "<div class='sfb-card' id='sfb-card' role='dialog' aria-modal='true' aria-labelledby='sfb-title' aria-describedby='sfb-desc' tabindex='-1'>"
			. "<div class='sfb-head'><span class='sfb-ic' id='sfb-ic-warn'>" . $warn . "</span><span class='sfb-ic sfb-ic-ok' id='sfb-ic-ok' hidden>" . $check . "</span>"
			. "<h2 class='sfb-title' id='sfb-title'>" . saldi_error_h($title) . "</h2></div>"
			. "<div id='sfb-desc'><p class='sfb-p'>" . saldi_error_h($lead) . "</p><p class='sfb-p sfb-p2'>" . ($form ? saldi_error_h($more) : $mailto($more)) . "</p></div>"
			. "<p class='sfb-ref'>" . saldi_error_h($refLbl) . "<span class='sfb-code'>" . saldi_error_h($ref) . "</span></p>"
			. ($contact !== '' ? "<p class='sfb-contact'>" . saldi_error_h($contact) . "</p>" : '')
			. ($form ? "<div id='sfb-form'><label class='sfb-lbl' for='sfb-msg'>" . saldi_error_h($msgLbl) . "</label>"
				. "<textarea class='sfb-ta' id='sfb-msg' maxlength='2000' rows='3'></textarea></div>" : '')
			. "<div class='sfb-note' id='sfb-status' role='status' aria-live='polite' hidden></div>"
			. "<div class='sfb-foot'>"
			. "<button type='button' class='sfb-btn" . ($form ? '' : ' sfb-primary') . "' id='sfb-close'>" . saldi_error_h($closeLbl) . "</button>"
			. ($form ? "<button type='button' class='sfb-btn sfb-primary' id='sfb-send'>" . saldi_error_h($sendLbl) . "</button>" : '')
			. "</div></div></div></div>";

		// sessionStorage 'sfb-seen-<ref>': set on Luk, Esc, Send and after 1.5 s on screen; a box carried over to the next
		// page (saldi_error_page_start()) stays away once it has been seen. Only one box per tab: not when the shell or the
		// page in its frame already shows one.
		$js = <<<'JS'
(function(){
var cfg=__CFG__,seenKey='sfb-seen-'+cfg.ref;
function seen(){try{return !!window.sessionStorage.getItem(seenKey);}catch(e){return false;}}
function mark(){try{window.sessionStorage.setItem(seenKey,'1');}catch(e){}}
function elsewhere(){try{if(window.top!==window&&window.top.document.getElementById('sfb-root'))return true;}catch(e){}
var f=document.getElementsByTagName('iframe');for(var i=0;i<f.length;i++){try{var d=f[i].contentDocument;if(d&&d.getElementById('sfb-root'))return true;}catch(e){}}return false;}
function mount(){if(document.getElementById('sfb-root')||seen()||elsewhere())return;var host=document.body||document.documentElement,w=document.createElement('div');
w.innerHTML=cfg.html;while(w.firstChild)host.appendChild(w.firstChild);init();}
function init(){
var root=document.getElementById('sfb-root'),card=document.getElementById('sfb-card'),send=document.getElementById('sfb-send'),close=document.getElementById('sfb-close'),
msg=document.getElementById('sfb-msg'),form=document.getElementById('sfb-form'),note=document.getElementById('sfb-status'),busy=false,
prev=document.activeElement,back=!!cfg.back&&!!window.history&&history.length>1&&document.referrer!=='';
if(back)close.innerHTML=cfg.t.back;
function open(){return !!root.parentNode;}
function main(){return !send||send.hidden||send.disabled?close:send;}
function items(){var l=card.querySelectorAll('button,textarea,a[href]'),r=[];for(var i=0;i<l.length;i++){if(!l[i].disabled&&l[i].offsetParent!==null)r.push(l[i]);}return r;}
function shut(){if(!open())return;mark();root.parentNode.removeChild(root);document.removeEventListener('keydown',key,true);document.removeEventListener('focusin',fin,true);
try{if(prev&&prev.focus&&prev!==document.body)prev.focus();}catch(e){}}
function key(e){if(!open())return;var k=e.key||'',c=e.keyCode;
if(k==='Escape'||k==='Esc'||c===27){e.preventDefault();e.stopPropagation();shut();return;}
if(k==='Tab'||c===9){var f=items();e.stopPropagation();if(!f.length){e.preventDefault();return;}var i=f.indexOf(document.activeElement);
if(e.shiftKey){if(i<=0){e.preventDefault();f[f.length-1].focus();}}else if(i<0||i===f.length-1){e.preventDefault();f[0].focus();}return;}
if(card.contains(e.target))e.stopPropagation();}
function fin(e){if(open()&&!card.contains(e.target)){main().focus();}}
function show(tone,html){note.className='sfb-note sfb-note-'+tone;note.innerHTML=html;note.hidden=false;}
function done(html){form.hidden=true;send.hidden=true;document.getElementById('sfb-desc').hidden=true;document.getElementById('sfb-title').innerHTML=cfg.t.title;document.getElementById('sfb-ic-warn').hidden=true;document.getElementById('sfb-ic-ok').hidden=false;
close.className='sfb-btn sfb-primary';show('ok',html);close.focus();}
function fail(html,stay){busy=false;send.disabled=!!stay;show('bad',html);main().focus();}
if(send)send.onclick=function(){if(busy)return;busy=true;mark();send.disabled=true;show('info',cfg.t.sending);
var x=new XMLHttpRequest();x.open('POST',cfg.url,true);x.setRequestHeader('Content-Type','application/x-www-form-urlencoded; charset=UTF-8');
x.setRequestHeader('Accept','application/json');x.setRequestHeader('X-CSRF-Token',cfg.csrf);
x.onreadystatechange=function(){if(x.readyState!==4)return;var r=null;try{r=JSON.parse(x.responseText);}catch(e){}
if(r&&r.ok)done(cfg.t.ok);else if(r&&r.error==='already')done(cfg.t.already);else if(r&&r.error==='rate')fail(cfg.t.rate,true);else fail(cfg.t.fail);};
x.send('ref='+encodeURIComponent(cfg.ref)+'&csrf_token='+encodeURIComponent(cfg.csrf)+'&message='+encodeURIComponent(msg.value));};
close.onclick=back?function(){mark();history.back();}:shut;
document.addEventListener('keydown',key,true);document.addEventListener('focusin',fin,true);
main().focus();
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',function(){if(open()&&!card.contains(document.activeElement))main().focus();});}
function watch(){if(document.hidden){document.addEventListener('visibilitychange',function v(){if(!document.hidden){document.removeEventListener('visibilitychange',v);watch();}});return;}
var raf=window.requestAnimationFrame?function(f){window.requestAnimationFrame(f);}:function(f){setTimeout(f,16);};
raf(function(){setTimeout(function(){if(open())mark();},1500);});}
watch();
}
if(document.getElementById('sfb-root'))return;
if(document.body)mount();else document.addEventListener('DOMContentLoaded',mount);
})();
JS;
		// The script adds the box to the end of <body>: printed as markup it would break inside a <select>, a <table> row or the <head>
		// the page happens to be printing when the query fails; a <script> runs in all of those places.
		$cfg = json_encode(array('ref' => $ref, 'csrf' => $csrf, 'url' => $url, 'back' => $exit, 't' => $texts, 'html' => $h), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
		return '<script>' . str_replace('__CFG__', $cfg, $js) . '</script>';
	}
}

if (!function_exists('saldi_error_header_value')) {
	/** A mail header value without CR, LF or other control characters (no header injection). */
	function saldi_error_header_value($s): string
	{
		return trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', saldi_error_utf8($s)));
	}
}

if (!function_exists('saldi_error_report_recipient')) {
	/** $errorReportTo from includes/connect.php when set (an installation override, e.g. a test folder), else Saldi support. */
	function saldi_error_report_recipient(): string
	{
		$to = isset($GLOBALS['errorReportTo']) ? saldi_error_header_value($GLOBALS['errorReportTo']) : '';
		return ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) ? $to : SALDI_ERROR_SUPPORT;
	}
}

if (!function_exists('saldi_error_report_compose')) {
	/**
	 * The report mail for a stored error, as plain UTF-8 text with the user's message first. Pure apart from the
	 * recipient default: it sends nothing.
	 * $details may carry 'reply_to' (the user's or the company's e-mail), used when it is a valid address.
	 *
	 * @return array{to: string, subject: string, body: string, headers: array<string, string>}
	 */
	function saldi_error_report_compose(array $details, string $message, ?string $to = null): array
	{
		$v = function (string $k) use ($details): string {
			return isset($details[$k]) ? saldi_error_utf8($details[$k]) : '';
		};
		$to = saldi_error_header_value($to === null ? saldi_error_report_recipient() : $to);
		$ref = saldi_error_header_value($v('ref'));
		$company = saldi_error_header_value($v('company'));
		$db = saldi_error_header_value($v('db'));
		$subject = 'Saldi fejlrapport ' . $ref . ($company !== '' ? ' - ' . $company : '') . ($db !== '' ? ' (' . $db . ')' : '');
		$subject = mb_substr(saldi_error_header_value($subject), 0, 200, 'UTF-8');
		$subjectHeader = preg_match('/[^\x20-\x7E]/', $subject) ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n") : $subject;

		$replyTo = saldi_error_header_value($v('reply_to'));
		if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
			$replyTo = '';
		}
		$message = trim(str_replace(array("\r\n", "\r"), "\n", saldi_error_utf8($message)));
		$time = (int) ($details['time'] ?? 0);
		$lines = array();
		$lines[] = 'Fejlrapport fra Saldi - reference ' . $ref;
		$lines[] = '';
		$lines[] = 'Brugerens besked:';
		$lines[] = $message !== '' ? $message : '(ingen besked)';
		$lines[] = '';
		$lines[] = '--- Detaljer ---';
		$lines[] = 'Tidspunkt:      ' . ($time ? date('Y-m-d H:i:s', $time) : '');
		$lines[] = 'Firma:          ' . $v('company');
		$lines[] = 'Database:       ' . $v('db');
		$lines[] = 'Bruger:         ' . $v('brugernavn') . ' (bruger_id ' . (int) ($details['bruger_id'] ?? 0) . ')';
		$lines[] = 'Svar til:       ' . ($replyTo !== '' ? $replyTo : '(ingen e-mail)');
		$lines[] = 'Side:           ' . trim($v('method') . ' ' . $v('uri'));
		$lines[] = 'Spor:           ' . $v('spor');
		$lines[] = 'Type:           ' . ($v('kind') === 'fatal' ? 'PHP-fejl' : ($v('kind') === 'db' ? 'databasefejl' : 'programfejl'));
		$lines[] = 'Saldi-version:  ' . $v('version');
		$lines[] = 'PHP-version:    ' . $v('php');
		$lines[] = 'Browser:        ' . $v('user_agent');
		$lines[] = '';
		$lines[] = 'Fejltekst:';
		$lines[] = $v('error');
		if ($v('query') !== '') {
			$lines[] = '';
			$lines[] = 'Forespørgsel:';
			$lines[] = $v('query');
		}
		foreach ((isset($details['more']) && is_array($details['more'])) ? $details['more'] : array() as $n => $more) {
			$lines[] = '';
			$lines[] = '--- Fejl ' . ($n + 2) . ' i samme sidevisning ---';
			$lines[] = 'Spor: ' . saldi_error_utf8($more['spor'] ?? '');
			$lines[] = 'Fejltekst: ' . saldi_error_utf8($more['error'] ?? '');
			$lines[] = 'Forespørgsel: ' . saldi_error_utf8($more['query'] ?? '');
		}
		// No line over 998 bytes (SMTP): long queries are wrapped at 200 characters.
		$out = array();
		foreach (explode("\n", implode("\n", $lines)) as $line) {
			$line = rtrim($line);
			while (strlen($line) > 900) {
				$out[] = mb_substr($line, 0, 200, 'UTF-8');
				$line = mb_substr($line, 200, null, 'UTF-8');
			}
			$out[] = $line;
		}
		$body = implode("\n", $out) . "\n";

		$headers = array(
			'From'                      => 'kan_ikke_besvares@saldi.dk',
			'MIME-Version'              => '1.0',
			'Content-Type'              => 'text/plain; charset=UTF-8',
			'Content-Transfer-Encoding' => '8bit',
			'X-Mailer'                  => 'Saldi',
			'X-Saldi-Error-Ref'         => $ref,
		);
		if ($replyTo !== '') {
			$headers['Reply-To'] = $replyTo;
		}
		return array('to' => $to, 'subject' => $subjectHeader, 'body' => $body, 'headers' => $headers);
	}
}

if (!function_exists('saldi_error_register_fatal')) {
	/** Register the shutdown function (a fatal PHP error, a box printed into a thrown-away buffer, a box carried over). Once. */
	function saldi_error_register_fatal(): void
	{
		static $registered = false;
		if ($registered || !saldi_error_box_allowed()) {
			return;
		}
		$registered = true;
		register_shutdown_function('saldi_error_on_shutdown');
	}
}

if (!function_exists('saldi_error_page_start')) {
	/**
	 * Called once from includes/online.php on pages (not API, JSON, webservice or CLI requests): notes the output-buffer
	 * level, registers the shutdown function and, on a page load, takes over the box of an earlier page that moved on
	 * before the user could see it (a meta refresh or location.href right after a db_select() error). The box comes at
	 * the end of this page with the same reference; the script leaves it out when it was already seen.
	 */
	function saldi_error_page_start(): void
	{
		static $started = false;
		if ($started || !saldi_error_box_allowed()) {
			return;
		}
		$started = true;
		$GLOBALS['saldi_error_ob_base'] = ob_get_level();
		saldi_error_register_fatal();
		if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['saldi_error_pending']) || !is_array($_SESSION['saldi_error_pending'])
			|| !saldi_error_navigation()) {
			return;
		}
		$pending = $_SESSION['saldi_error_pending'];
		$ref = isset($pending['ref']) ? (string) $pending['ref'] : '';
		$report = ($ref !== '' && isset($_SESSION['saldi_error_reports'][$ref]) && is_array($_SESSION['saldi_error_reports'][$ref]))
			? $_SESSION['saldi_error_reports'][$ref] : null;
		$valid = $report !== null && empty($report['reported']) && (int) ($pending['time'] ?? 0) >= time() - 300;
		$left = (int) ($pending['left'] ?? 1) - 1;
		if ($valid && $left > 0) {
			$_SESSION['saldi_error_pending']['left'] = $left;
		} else {
			unset($_SESSION['saldi_error_pending']);
		}
		if (!$valid) {
			return;
		}
		if (empty($_SESSION['csrf_token'])) {
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		}
		$GLOBALS['saldi_error_again'] = array('ref' => $ref, 'csrf' => (string) $_SESSION['csrf_token']);
	}
}

if (!function_exists('saldi_error_on_shutdown')) {
	/**
	 * At the end of a page: the box after a fatal PHP error; else the box printed into an output buffer the page may
	 * have thrown away; else a box carried over from an earlier page (saldi_error_page_start()). Never on a script's
	 * request, a redirect, a download or a non-HTML answer, and not when this page already showed a box.
	 */
	function saldi_error_on_shutdown(): void
	{
		$e = error_get_last();
		$fatal = $e && in_array((int) $e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true);
		$buffered = isset($GLOBALS['saldi_error_box_buffered']) ? (string) $GLOBALS['saldi_error_box_buffered'] : '';
		$again = (isset($GLOBALS['saldi_error_again']) && is_array($GLOBALS['saldi_error_again'])) ? $GLOBALS['saldi_error_again'] : null;
		if (!$fatal && $buffered === '' && $again === null) {
			return;
		}
		if (!empty($GLOBALS['saldi_error_box_shown']) || !saldi_error_box_allowed() || saldi_error_script_request()
			|| isset($_GET['api']) || isset($_POST['api']) || !saldi_error_html_answer()) {
			return;
		}
		// Apache may run shutdown functions in another working directory; findtekst() opens its files relatively.
		if (!empty($_SERVER['SCRIPT_FILENAME']) && is_dir(dirname((string) $_SERVER['SCRIPT_FILENAME']))) {
			@chdir(dirname((string) $_SERVER['SCRIPT_FILENAME']));
		}
		if ($fatal) {
			$root = realpath(__DIR__ . '/..');
			$file = (string) $e['file'];
			if ($root !== false && strpos($file, $root . '/') === 0) {
				$file = substr($file, strlen($root) + 1);
			}
			saldi_error_box($file . ' linje ' . (int) $e['line'], (string) $e['message'], '', true, 'fatal');
			return;
		}
		if ($buffered !== '') {
			print "\n" . $buffered . "\n";
			return;
		}
		$lang = isset($GLOBALS['sprog_id']) && (int) $GLOBALS['sprog_id'] > 0 ? (int) $GLOBALS['sprog_id'] : 1;
		print "\n" . saldi_error_box_html((string) $again['ref'], $lang, (string) $again['csrf'], saldi_error_root_url() . '/index/fejlrapport.php', saldi_error_custom_contact(), false) . "\n";
	}
}
