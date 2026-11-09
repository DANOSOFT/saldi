<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/semanticSend.php --- ver 5.1.0 --- 2026-11-09 ---
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
// 20261109 CL/MMR SD-815 Created (draft). semantic_send_document() sends an invoice or credit note as Peppol BIS 3 XML built by Saldi through Semantic, behind the per-regnskab switch settings provider/einvoice = 'semantic'.
//                 The guards (switch, copy of a regnskab, duplicate send) are pure functions here, so they are tested without a database.
//                 A per-document database lock (semantic_send_lock()) keeps two windows from sending the same document at the same time.
// 20261109 CL/MMR SD-815 The seller endpoint in the XML must be the one the onboarding registers (0184 + Stamdata CVR, or the Stamdata GLN), else the send stops.
// 20261109 CL/MMR SD-815 Review fixes: the seller endpoint is checked against Stamdata before the onboarding calls Semantic, and afterwards against the CVR/GLN the regnskab was registered with.

include_once(__DIR__ . '/semanticClient.php');
include_once(__DIR__ . '/semanticSendLog.php');
include_once(__DIR__ . '/semanticStatus.php');
include_once(__DIR__ . '/semanticOnboarding.php');

if (!function_exists('semantic_provider_is_semantic')) {
	/**
	 * Whether a value of the setting provider/einvoice selects Semantic. Anything else, including no row, keeps EasyUBL.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	function semantic_provider_is_semantic($value) {
		return is_string($value) && strtolower(trim($value)) === 'semantic';
	}
}

if (!function_exists('semantic_provider_enabled')) {
	/**
	 * The per-regnskab switch: settings var_grp 'einvoice', var_name 'provider'. Default 'easyubl'.
	 * There is no screen for it yet; support sets it for a test regnskab.
	 *
	 * @return bool
	 */
	function semantic_provider_enabled() {
		return semantic_provider_is_semantic(get_settings_value('provider', 'einvoice', 'easyubl'));
	}
}

if (!function_exists('semantic_document_art')) {
	/**
	 * ordrer.art for a document type.
	 *
	 * @param string $type 'invoice' or 'creditnote'.
	 * @return string|null 'DO', 'DK', or null for another type.
	 */
	function semantic_document_art($type) {
		if ($type === 'invoice') {
			return 'DO';
		}
		return ($type === 'creditnote') ? 'DK' : null;
	}
}

if (!function_exists('semantic_order_sendable')) {
	/**
	 * Whether the order can be sent as this document type at all: it exists, its art matches the type, it is invoiced
	 * (status 3 or more) and it has an invoice number (BT-1).
	 *
	 * @param array{art?: string, status?: int|string, fakturanr?: string}|null $order ordrer row.
	 * @param string $type 'invoice' or 'creditnote'.
	 * @return array{allowed: bool, reason: string, message: string}
	 */
	function semantic_order_sendable($order, $type) {
		$art = semantic_document_art($type);
		if ($art === null) {
			return array('allowed' => false, 'reason' => 'type', 'message' => 'Ukendt dokumenttype.');
		}
		if (!is_array($order)) {
			return array('allowed' => false, 'reason' => 'not_found', 'message' => 'Ordren findes ikke.');
		}
		if (trim((string)($order['art'] ?? '')) !== $art) {
			$what = ($art === 'DK') ? 'en kreditnota' : 'en faktura';
			return array('allowed' => false, 'reason' => 'art', 'message' => "Ordren er ikke $what.");
		}
		if ((int)($order['status'] ?? 0) < 3 || trim((string)($order['fakturanr'] ?? '')) === '') {
			return array('allowed' => false, 'reason' => 'not_invoiced', 'message' => 'Ordren er ikke faktureret endnu, så den kan ikke sendes digitalt.');
		}
		return array('allowed' => true, 'reason' => 'ok', 'message' => '');
	}
}

if (!function_exists('semantic_duplicate_guard')) {
	/**
	 * Whether a send may go ahead without the user confirming a resend.
	 *
	 * Refused when the newest send-log row for the order and document kind did not certainly fail
	 * (einvoice_sendlog_failed_statuses()): it was sent, is on its way, or its outcome is unknown. Without a row,
	 * ordrer.digital_status from a send through the current provider counts the same way. $resend (the user confirmed)
	 * always allows it.
	 *
	 * @param array{id?: int, status?: string, created?: string}|null $latestLog     einvoice_sendlog_latest_for_order().
	 * @param string|null                                           $digitalStatus ordrer.digital_status.
	 * @param bool                                                  $resend
	 * @return array{allowed: bool, reason: string, message: string} reason 'first', 'after_failure', 'resend' or 'duplicate'.
	 */
	function semantic_duplicate_guard($latestLog, $digitalStatus, $resend) {
		$status = '';
		$when = '';
		if (is_array($latestLog)) {
			$status = trim((string)($latestLog['status'] ?? ''));
			$when = trim((string)($latestLog['created'] ?? ''));
		} else {
			$digital = trim((string)$digitalStatus);
			if ($digital === '' || in_array($digital, array('SendFailed', 'Error', 'NoStatus'), true)) {
				return array('allowed' => true, 'reason' => 'first', 'message' => '');
			}
			$status = $digital;
		}
		if (is_array($latestLog) && in_array($status, einvoice_sendlog_failed_statuses(), true)) {
			return array('allowed' => true, 'reason' => 'after_failure', 'message' => '');
		}
		if ($resend) {
			return array('allowed' => true, 'reason' => 'resend', 'message' => '');
		}
		$text = in_array($status, array('Submitting', 'Unknown'), true)
			? 'Den sidste digitale afsendelse af dokumentet fik intet svar, så det kan være modtaget alligevel.'
			: 'Dokumentet er allerede sendt digitalt (status: ' . $status . ').';
		if ($when !== '') {
			$text .= ' Afsendt ' . substr($when, 0, 16) . '.';
		}
		return array('allowed' => false, 'reason' => 'duplicate', 'message' => $text . ' Vil du sende det igen?');
	}
}

if (!function_exists('semantic_network_decide')) {
	/**
	 * The network to send on: Peppol (3) when the receiver is there, else Nemhandel (4) when it is there.
	 * A failed lookup (null) stops the send rather than guessing.
	 * SD-815 OPEN: (Q3) what networkType 4 accepts (Peppol BIS 3 or OIOUBL), and who chooses when the receiver is in both.
	 *
	 * @param bool|null $inPeppol    isEndpointInPeppol(), null when the lookup failed.
	 * @param bool|null $inNemhandel isEndpointInNemHandel(), null when it failed or was not asked.
	 * @return array{networkType: int|null, reason: string, message: string} reason 'peppol', 'nemhandel', 'not_found' or 'lookup_failed'.
	 */
	function semantic_network_decide($inPeppol, $inNemhandel) {
		if ($inPeppol === true) {
			return array('networkType' => SemanticClient::NETWORK_PEPPOL, 'reason' => 'peppol', 'message' => '');
		}
		if ($inPeppol === null) {
			return array('networkType' => null, 'reason' => 'lookup_failed', 'message' => 'Modtageren kunne ikke slås op i Peppol lige nu. Prøv igen senere.');
		}
		if ($inNemhandel === true) {
			return array('networkType' => SemanticClient::NETWORK_NEMHANDEL, 'reason' => 'nemhandel', 'message' => '');
		}
		if ($inNemhandel === null) {
			return array('networkType' => null, 'reason' => 'lookup_failed', 'message' => 'Modtageren kunne ikke slås op i NemHandel lige nu. Prøv igen senere.');
		}
		return array('networkType' => null, 'reason' => 'not_found',
			'message' => 'Modtageren kan ikke modtage e-fakturaer: den findes hverken i Peppol eller NemHandel. Kontrollér EAN/CVR på ordren eller debitorkortet.');
	}
}

if (!function_exists('semantic_choose_network')) {
	/**
	 * Look the receiver up and pick the network (semantic_network_decide()). The only place that chooses it.
	 *
	 * @param SemanticClient $client
	 * @param array{scheme?: string, id?: string} $endpoint     The model's buyer endpoint (BT-49).
	 * @param string                              $documentType 'invoice' or 'creditnote'.
	 * @return array{networkType: int|null, reason: string, message: string}
	 */
	function semantic_choose_network(SemanticClient $client, array $endpoint, $documentType) {
		$scheme = trim((string)($endpoint['scheme'] ?? ''));
		$id = trim((string)($endpoint['id'] ?? ''));
		if ($scheme === '' || $id === '') {
			return array('networkType' => null, 'reason' => 'no_endpoint', 'message' => 'Modtageren har intet EAN- eller CVR-nummer at sende til.');
		}
		$code = ($documentType === 'creditnote') ? SemanticClient::DOCUMENT_TYPE_CREDITNOTE : SemanticClient::DOCUMENT_TYPE_INVOICE;
		$peppol = $client->isEndpointInPeppol($scheme, $id, $code);
		$inPeppol = $peppol['ok'] ? $peppol['data'] : null;
		$inNemhandel = false;
		if ($inPeppol === false) {
			$nemhandel = $client->isEndpointInNemHandel($scheme, $id, $code);
			$inNemhandel = $nemhandel['ok'] ? $nemhandel['data'] : null;
		}
		return semantic_network_decide($inPeppol, $inNemhandel);
	}
}

if (!function_exists('semantic_precheck_messages')) {
	/**
	 * peppol_precheck()'s answer as a list of messages: a list of strings, a list of arrays with 'message'/'text',
	 * or an array with 'messages'.
	 *
	 * @param mixed $result
	 * @return list<string> Empty when the document may be sent.
	 */
	function semantic_precheck_messages($result) {
		if (is_array($result) && isset($result['messages']) && is_array($result['messages'])) {
			$result = $result['messages'];
		}
		$messages = array();
		foreach ((array)$result as $item) {
			if (is_array($item)) {
				$item = $item['message'] ?? ($item['text'] ?? '');
			}
			if (is_scalar($item) && trim((string)$item) !== '') {
				$messages[] = trim((string)$item);
			}
		}
		return $messages;
	}
}

if (!function_exists('semantic_validation_report_verdict')) {
	/**
	 * Read ValidatePeppolDocument's report: true when it says there are no errors, false when it lists errors, null
	 * when it cannot be read (the send then goes on; CreateOutgoingDocument validates again).
	 * SD-815 OPEN: (validation) the report is a plain string with an undocumented format ("contains all errors found in
	 *              the document, or states that no errors were found"); this reading is a guess to replace.
	 *
	 * @param mixed $report
	 * @return bool|null
	 */
	function semantic_validation_report_verdict($report) {
		if (is_array($report)) {
			foreach (array('isValid', 'valid', 'success') as $key) {
				if (isset($report[$key]) && is_bool($report[$key])) {
					return $report[$key];
				}
			}
			foreach (array('errors', 'fatal', 'fatals') as $key) {
				if (isset($report[$key]) && is_array($report[$key])) {
					return count($report[$key]) === 0;
				}
			}
			return null;
		}
		if (!is_string($report)) {
			return null;
		}
		$text = strtolower(trim($report));
		if ($text === '') {
			return null;
		}
		if (preg_match('/\bno (validation )?errors?\b|\bnone errors\b|\bdocument is valid\b|\bis valid\b/', $text)) {
			return true;
		}
		if (preg_match('/\b(error|errors|fatal|invalid)\b/', $text)) {
			return false;
		}
		return null;
	}
}

if (!function_exists('semantic_resend_token')) {
	/**
	 * The token on the "send again" form: bound to the session, the order and the send-log row the user saw.
	 *
	 * @param string $sessionId session_id()
	 * @param int    $orderId
	 * @param int    $logId     The newest send-log row when the form was shown (0 for none).
	 * @return string
	 */
	function semantic_resend_token($sessionId, $orderId, $logId) {
		return hash_hmac('sha256', 'semantic-resend|' . (int)$orderId . '|' . (int)$logId, 'saldi|' . (string)$sessionId);
	}
}

if (!function_exists('semantic_resend_token_valid')) {
	/**
	 * @param string $sessionId
	 * @param int    $orderId
	 * @param int    $logId
	 * @param mixed  $token
	 * @return bool
	 */
	function semantic_resend_token_valid($sessionId, $orderId, $logId, $token) {
		if ((string)$sessionId === '' || !is_string($token) || !preg_match('/^[0-9a-f]{64}$/', $token)) {
			return false;
		}
		return hash_equals(semantic_resend_token($sessionId, $orderId, $logId), $token);
	}
}

if (!function_exists('semantic_require_builder')) {
	/**
	 * Load the Peppol BIS builder (written separately) and check its four functions are there.
	 *
	 * @return string '' when ready, else the error for the user.
	 */
	function semantic_require_builder() {
		foreach (array('peppolInvoiceData.php', 'peppolDocumentModel.php', 'peppolPrecheck.php', 'peppolBisWriter.php') as $file) {
			if (is_file(__DIR__ . '/' . $file)) {
				include_once(__DIR__ . '/' . $file);
			}
		}
		$missing = array();
		foreach (array('peppol_invoice_rows', 'peppol_document_model', 'peppol_precheck', 'peppol_bis_xml') as $function) {
			if (!function_exists($function)) {
				$missing[] = $function . '()';
			}
		}
		return $missing ? 'E-faktura-modulet er ikke komplet på denne server (mangler ' . implode(', ', $missing) . '). Kontakt saldi.dk support.' : '';
	}
}

if (!function_exists('semantic_seller_endpoint_mismatch')) {
	/**
	 * Whether the seller endpoint the builder put in the document is one semantic_ensure_sender() registers for this
	 * regnskab: 0184 + the 8-digit CVR of Stamdata, or 0088 + the Stamdata GLN. Both come from semantic_own_company(), so
	 * an XML that names another seller endpoint (a foreign country in Stamdata, a CVR the onboarding reads differently)
	 * is stopped before the onboarding registers anything. semantic_seller_registration_mismatch() then compares it with
	 * what was registered.
	 *
	 * @param array{scheme?: string, id?: string}|null $endpoint The model's seller endpoint.
	 * @param array{cvr?: string, gln?: string}|null    $own      semantic_own_company(); null when Stamdata is missing.
	 * @return string '' when it matches, else the Danish message for the user.
	 */
	function semantic_seller_endpoint_mismatch($endpoint, $own) {
		$scheme = trim((string)($endpoint['scheme'] ?? ''));
		$id = trim((string)($endpoint['id'] ?? ''));
		$cvr = (string)($own['cvr'] ?? '');
		$gln = (string)($own['gln'] ?? '');
		if (semantic_seller_endpoint_matches($scheme, $id, $cvr, $gln)) {
			return '';
		}
		return 'E-fakturaens afsender (' . $scheme . ':' . $id . ') er ikke det CVR-nummer fra Stamdata, som regnskabet er oprettet med til e-faktura. Ret CVR-nummer og land under Indstillinger > Stamdata, eller kontakt saldi.dk support.';
	}
}

if (!function_exists('semantic_seller_endpoint_matches')) {
	/**
	 * Whether a seller endpoint is 0184 + an 8-digit CVR or 0088 + a GLN.
	 *
	 * @param string $scheme The endpoint's scheme.
	 * @param string $id     The endpoint's id.
	 * @param string $cvr    The CVR it must equal.
	 * @param string $gln    The GLN it may equal instead ('' for none).
	 * @return bool
	 */
	function semantic_seller_endpoint_matches($scheme, $id, $cvr, $gln) {
		$id = (string)$id;
		return ($scheme === '0184' && preg_match('/^[0-9]{8}$/', (string)$cvr) && $id === (string)$cvr)
			|| ($scheme === '0088' && (string)$gln !== '' && $id === (string)$gln);
	}
}

if (!function_exists('semantic_seller_registration_mismatch')) {
	/**
	 * Whether the seller endpoint in the document is the CVR (or GLN) the regnskab's endpoints were registered with at
	 * Semantic (settings semantic/endpointIdentifier, semantic_ensure_sender()). After a change of the CVR in Stamdata the
	 * builder writes the new CVR while senderEndpointId is still the old registration, so the document is stopped.
	 * A registration from before the identifiers were stored ('' in $sender) is compared with Stamdata instead.
	 *
	 * @param array{scheme?: string, id?: string}|null $endpoint The model's seller endpoint.
	 * @param array{registeredCvr?: string, registeredGln?: string, endpointIdGln?: string|null} $sender semantic_ensure_sender().
	 * @param array{cvr?: string, gln?: string}|null    $own      semantic_own_company().
	 * @return string '' when it matches, else the Danish message for the user.
	 */
	function semantic_seller_registration_mismatch($endpoint, array $sender, $own) {
		$scheme = trim((string)($endpoint['scheme'] ?? ''));
		$id = trim((string)($endpoint['id'] ?? ''));
		$cvr = trim((string)($sender['registeredCvr'] ?? ''));
		if ($cvr === '') {
			$cvr = (string)($own['cvr'] ?? '');
		}
		$gln = '';
		if (!empty($sender['endpointIdGln'])) {
			$gln = trim((string)($sender['registeredGln'] ?? ''));
			if ($gln === '') {
				$gln = (string)($own['gln'] ?? '');
			}
		}
		if (semantic_seller_endpoint_matches($scheme, $id, $cvr, $gln)) {
			return '';
		}
		return 'E-fakturaens afsender (' . $scheme . ':' . $id . ') er ikke det CVR-nummer, regnskabet er registreret med til e-faktura (' . $cvr . '). Er CVR-nummeret i Stamdata ændret, skal registreringen flyttes af saldi.dk support, før der kan sendes.';
	}
}

if (!function_exists('semantic_send_result')) {
	/**
	 * @param bool     $ok
	 * @param string   $status
	 * @param string   $message
	 * @param int|null $sendLogId
	 * @return array{ok: bool, status: string, message: string, sendLogId: int|null}
	 */
	function semantic_send_result($ok, $status, $message, $sendLogId = null) {
		return array('ok' => (bool)$ok, 'status' => (string)$status, 'message' => (string)$message, 'sendLogId' => ($sendLogId !== null && (int)$sendLogId > 0) ? (int)$sendLogId : null);
	}
}

if (!function_exists('semantic_set_digital_status')) {
	/**
	 * Write ordrer.digital_status (varchar(25)) with a status word.
	 *
	 * @param int    $orderId
	 * @param string $word
	 * @return void
	 */
	function semantic_set_digital_status($orderId, $word) {
		if ((int)$orderId > 0 && $word !== '') {
			db_modify("UPDATE ordrer SET digital_status = '" . db_escape_string(substr((string)$word, 0, 25)) . "' WHERE id = " . (int)$orderId, __FILE__ . " linje " . __LINE__);
		}
	}
}

if (!function_exists('semantic_send_lock_sql')) {
	/**
	 * The SQL that takes, without waiting, or releases the lock for sending one document. PostgreSQL advisory locks
	 * belong to one database; MySQL's GET_LOCK names are server-wide, so the database is part of the name, as in
	 * finans/kassekladde_includes/journalSaveLock.php (and the name stays under MySQL's 64 characters).
	 *
	 * @param string|null $dbType  $db_type from includes/connect.php.
	 * @param int         $orderId ordrer.id
	 * @param string      $art     'DO' or 'DK'.
	 * @param bool        $release True for the release statement.
	 * @return string One column, locked: true/1 when the lock was taken (or released).
	 */
	function semantic_send_lock_sql($dbType, $orderId, $art, $release = false) {
		$orderId = (int)$orderId;
		$art = ($art === 'DK') ? 'DK' : 'DO';
		if (einvoice_sendlog_is_mysql($dbType)) {
			$name = "CONCAT('saldi:einv:', MD5(DATABASE()), ':$art:$orderId')";
			return $release ? "SELECT RELEASE_LOCK($name) AS locked" : "SELECT GET_LOCK($name, 0) AS locked";
		}
		$key = "hashtext('einvoice_send_$art'), $orderId";
		return $release ? "SELECT pg_advisory_unlock($key) AS locked" : "SELECT pg_try_advisory_lock($key) AS locked";
	}
}

if (!function_exists('semantic_send_lock_taken')) {
	/**
	 * Whether the lock query's answer means the lock is ours: 't' (PostgreSQL), 1 (MySQL) or true.
	 *
	 * @param mixed $value The locked column.
	 * @return bool
	 */
	function semantic_send_lock_taken($value) {
		return $value === true || $value === 't' || $value === 1 || $value === '1';
	}
}

if (!function_exists('semantic_send_lock')) {
	/**
	 * Take the send lock for one document without waiting. It is held until semantic_send_unlock() or the end of the
	 * database session, so a second click or a second window cannot send the same document while the first send runs.
	 *
	 * @param int    $orderId
	 * @param string $art
	 * @return bool False when another request holds it.
	 */
	function semantic_send_lock($orderId, $art) {
		global $db_type;
		$query = db_select(semantic_send_lock_sql($db_type, $orderId, $art), __FILE__ . " linje " . __LINE__);
		$r = $query ? db_fetch_array($query) : null;
		return is_array($r) && semantic_send_lock_taken($r['locked'] ?? null);
	}
}

if (!function_exists('semantic_send_unlock')) {
	/**
	 * Release the lock taken by semantic_send_lock().
	 *
	 * @param int    $orderId
	 * @param string $art
	 * @return void
	 */
	function semantic_send_unlock($orderId, $art) {
		global $db_type;
		db_select(semantic_send_lock_sql($db_type, $orderId, $art, true), __FILE__ . " linje " . __LINE__);
	}
}

if (!function_exists('semantic_send_document')) {
	/**
	 * Send an invoice or credit note through Semantic: the per-regnskab switch, then the per-document send lock
	 * (semantic_send_lock()), then semantic_send_document_locked(), which does the work.
	 *
	 * @param int    $orderId ordrer.id
	 * @param string $type    'invoice' or 'creditnote'.
	 * @param array<string, mixed> $context See semantic_send_document_locked().
	 * @return array{ok: bool, status: string, message: string, sendLogId: int|null}
	 *   status: 'pending' (sent), 'disabled', 'busy' (another send of the document is running), 'config', 'order', 'copy',
	 *   'duplicate', 'builder', 'precheck', 'onboarding', 'local_validation', 'network', 'validation', 'auth', 'failed'
	 *   or 'unknown' (sent, but no usable answer).
	 */
	function semantic_send_document(int $orderId, string $type, array $context = array()) {
		if (!semantic_provider_enabled()) {
			return semantic_send_result(false, 'disabled', 'Regnskabet er ikke sat op til at sende e-faktura via Semantic.');
		}
		$art = semantic_document_art($type);
		if ($art === null || $orderId <= 0) {
			return semantic_send_document_locked($orderId, $type, $context);
		}
		if (!semantic_send_lock($orderId, $art)) {
			return semantic_send_result(false, 'busy', 'Dokumentet er ved at blive sendt fra et andet vindue. Vent et øjeblik, og se status på ordren.');
		}
		try {
			return semantic_send_document_locked($orderId, $type, $context);
		} finally {
			semantic_send_unlock($orderId, $art);
		}
	}
}

if (!function_exists('semantic_send_document_locked')) {
	/**
	 * The send itself; semantic_send_document() calls it with the send lock held (without one only for an invalid order id
	 * or document type, which this function then refuses).
	 *
	 * Steps: the order (art, invoiced, invoice number); the copy guard (semantic_copy_guard());
	 * the duplicate guard (semantic_duplicate_guard()); the builder (peppol_invoice_rows(), peppol_document_model(),
	 * peppol_precheck(), peppol_bis_xml()); the document's seller endpoint against Stamdata (semantic_seller_endpoint_mismatch());
	 * the sender at Semantic (semantic_ensure_sender()) and the seller endpoint against the registration
	 * (semantic_seller_registration_mismatch()); an optional local
	 * validator; the network (semantic_choose_network()); a send-log row with the XML and its SHA-256 (Saldi's own archive);
	 * ValidatePeppolDocument; CreateOutgoingDocument; the send log and ordrer.digital_status.
	 * Nothing is printed and nothing exits: the caller shows the result.
	 *
	 * ordrer.digital_status gets 'Pending' when Semantic took the document (the webhook and the poll move it on, to 'Sent'
	 * and further), and 'SendFailed' when Semantic refused it or the outcome is unknown, so the order page asks before a
	 * new send. A precheck or local validation failure leaves it alone, as nothing left Saldi.
	 *
	 * @param int    $orderId ordrer.id
	 * @param string $type    'invoice' or 'creditnote'.
	 * @param array{
	 *   db?: string,                 The tenant database; $GLOBALS['db'] when absent.
	 *   sprog_id?: int,              Language for peppol_precheck(); 1 when absent.
	 *   user?: string,               Stored as created_by; $GLOBALS['brugernavn'] when absent.
	 *   resend?: bool,               The user confirmed sending again.
	 *   client?: SemanticClient,     Instead of semantic_client(), for tests.
	 *   validator?: callable,        fn(string $xml): array{ok: bool, messages: list<string>}; a local check before anything is sent.
	 *   networkType?: int,           3 or 4, skips the lookup (support and tests).
	 *   skipRemoteValidation?: bool, Do not call ValidatePeppolDocument.
	 *   webhookBaseUrl?: string,     For onboarding, when the server has no SALDI_SEMANTIC_WEBHOOK_BASE_URL.
	 * } $context
	 * @return array{ok: bool, status: string, message: string, sendLogId: int|null} See semantic_send_document().
	 */
	function semantic_send_document_locked($orderId, $type, array $context = array()) {
		$orderId = (int)$orderId;
		$type = (string)$type;
		$db = (string)($context['db'] ?? ($GLOBALS['db'] ?? ''));
		$sprogId = (int)($context['sprog_id'] ?? 1);
		$user = (string)($context['user'] ?? ($GLOBALS['brugernavn'] ?? ''));
		$resend = !empty($context['resend']);

		$art = semantic_document_art($type);
		$order = null;
		if ($art !== null && $orderId > 0) {
			$r = db_fetch_array(db_select("SELECT id, art, status, fakturanr, digital_status FROM ordrer WHERE id = $orderId", __FILE__ . " linje " . __LINE__));
			$order = is_array($r) ? $r : null;
		}
		$sendable = semantic_order_sendable($order, $type);
		if (!$sendable['allowed']) {
			return semantic_send_result(false, 'order', $sendable['message']);
		}

		$copy = semantic_copy_guard(semantic_settings_read(), $db);
		if (!$copy['allowed']) {
			return semantic_send_result(false, 'copy', $copy['message']);
		}

		$latest = einvoice_sendlog_latest_for_order($orderId, $art);
		$duplicate = semantic_duplicate_guard($latest, $order['digital_status'] ?? '', $resend);
		if (!$duplicate['allowed']) {
			return semantic_send_result(false, 'duplicate', $duplicate['message'], $latest['id'] ?? null);
		}

		$builderError = semantic_require_builder();
		if ($builderError !== '') {
			return semantic_send_result(false, 'builder', $builderError);
		}
		try {
			$rows = peppol_invoice_rows($orderId);
			if (!is_array($rows) || empty($rows['order'])) {
				return semantic_send_result(false, 'builder', 'Ordrens data kunne ikke læses til e-fakturaen.');
			}
			// The seller endpoint is left to the builder (0184 + own CVR), which is the endpoint semantic_ensure_sender() registers.
			$model = peppol_document_model($rows, array('documentType' => $type));
			$precheck = is_array($model) ? peppol_precheck($model, $sprogId) : array();
		} catch (Throwable $e) {
			error_log('semantic_send_document(' . $orderId . '): ' . get_class($e) . ' ' . $e->getMessage());
			return semantic_send_result(false, 'builder', 'E-fakturaen kunne ikke dannes ud fra ordren. Kontakt saldi.dk support.');
		}
		if (!is_array($model) || ($model['documentType'] ?? '') !== $type) {
			return semantic_send_result(false, 'builder', 'E-fakturaen kunne ikke dannes ud fra ordren.');
		}
		$messages = semantic_precheck_messages($precheck);
		if ($messages) {
			return semantic_send_result(false, 'precheck', implode("\n", $messages));
		}

		// The seller endpoint against Stamdata before anything is registered: a document that can never be sent must not
		// make the onboarding create a company or endpoints at Semantic
		$own = semantic_own_company();
		$senderMismatch = semantic_seller_endpoint_mismatch($model['seller']['endpoint'] ?? null, $own);
		if ($senderMismatch !== '') {
			return semantic_send_result(false, 'onboarding', $senderMismatch);
		}

		$client = (isset($context['client']) && $context['client'] instanceof SemanticClient) ? $context['client'] : semantic_client();
		if (!$client instanceof SemanticClient) {
			return semantic_send_result(false, 'config', 'E-faktura via Semantic er ikke sat op på denne server. Kontakt saldi.dk support.');
		}
		$sender = semantic_ensure_sender($client, array('db' => $db, 'webhookBaseUrl' => (string)($context['webhookBaseUrl'] ?? '')));
		if (!$sender['ok']) {
			return semantic_send_result(false, 'onboarding', $sender['message']);
		}
		$warning = ($sender['message'] !== '') ? "\n" . $sender['message'] : '';
		// ... and against what the regnskab was registered with (Stamdata may have changed since)
		$senderMismatch = semantic_seller_registration_mismatch($model['seller']['endpoint'] ?? null, $sender, $own);
		if ($senderMismatch !== '') {
			return semantic_send_result(false, 'onboarding', $senderMismatch);
		}

		try {
			$xml = peppol_bis_xml($model);
		} catch (Throwable $e) {
			error_log('semantic_send_document(' . $orderId . '): ' . get_class($e) . ' ' . $e->getMessage());
			$xml = '';
		}
		if (!is_string($xml) || strncmp(ltrim($xml), '<', 1) !== 0) {
			return semantic_send_result(false, 'builder', 'E-fakturaens XML kunne ikke dannes.');
		}
		$logBase = array(
			'ordre_id' => $orderId,
			'art' => $art,
			'fakturanr' => (string)$order['fakturanr'],
			'provider' => 'semantic',
			'xml' => einvoice_sendlog_pack_xml($xml),
			'xml_sha256' => hash('sha256', $xml),
			'db_name' => $db,
			'created_by' => $user,
		);

		if (isset($context['validator']) && is_callable($context['validator'])) {
			$local = call_user_func($context['validator'], $xml);
			if (is_array($local) && empty($local['ok'])) {
				$text = implode("\n", semantic_precheck_messages($local['messages'] ?? array()));
				$logId = einvoice_sendlog_insert($logBase + array('status' => 'ValidationFailed', 'status_text' => 'Lokal validering: ' . $text));
				return semantic_send_result(false, 'local_validation', 'E-fakturaen opfylder ikke reglerne og er ikke sendt.' . ($text !== '' ? "\n" . $text : ''), $logId);
			}
		}

		$networkType = (int)($context['networkType'] ?? 0);
		if ($networkType !== SemanticClient::NETWORK_PEPPOL && $networkType !== SemanticClient::NETWORK_NEMHANDEL) {
			$network = semantic_choose_network($client, (array)($model['buyer']['endpoint'] ?? array()), $type);
			if ($network['networkType'] === null) {
				return semantic_send_result(false, 'network', $network['message']);
			}
			$networkType = (int)$network['networkType'];
		}

		// The seller endpoint in the XML decides which of our endpoints sends.
		// SD-815 OPEN: (Q8) is senderEndpointId checked against AccountingSupplierParty/EndpointID?
		$senderEndpointId = (string)$sender['endpointId'];
		if (($model['seller']['endpoint']['scheme'] ?? '') === '0088' && !empty($sender['endpointIdGln'])) {
			$senderEndpointId = (string)$sender['endpointIdGln'];
		}

		$logId = einvoice_sendlog_insert($logBase + array('network' => $networkType, 'sender_endpoint_id' => $senderEndpointId, 'status' => 'Submitting'));
		if ($logId <= 0) {
			return semantic_send_result(false, 'failed', 'Afsendelsen kunne ikke logges, så dokumentet er ikke sendt. Kontakt saldi.dk support.');
		}

		if (empty($context['skipRemoteValidation'])) {
			$validation = $client->validatePeppolDocument($xml, $networkType);
			if ($validation['kind'] === 'auth') {
				einvoice_sendlog_update($logId, array('status' => 'SendFailed', 'status_text' => $validation['message']));
				semantic_set_digital_status($orderId, 'SendFailed');
				return semantic_send_result(false, 'auth', 'Semantic afviste adgangen. Kontakt saldi.dk support.' . "\n" . $validation['message'], $logId);
			}
			$verdict = $validation['ok'] ? semantic_validation_report_verdict($validation['data']) : null;
			if ($validation['kind'] === 'validation' || $verdict === false) {
				$report = $validation['ok'] ? (is_string($validation['data']) ? $validation['data'] : json_encode($validation['data'], JSON_UNESCAPED_UNICODE)) : $validation['message'];
				einvoice_sendlog_update($logId, array('status' => 'ValidationFailed', 'status_text' => $report));
				semantic_set_digital_status($orderId, 'SendFailed');
				return semantic_send_result(false, 'validation', 'Semantic afviste e-fakturaen ved valideringen, og den er ikke sendt.' . "\n" . semantic_short_text((string)$report), $logId);
			}
			// A transient or unreadable validation answer does not stop the send: CreateOutgoingDocument validates too.
		}

		$created = $client->createOutgoingDocument($senderEndpointId, $xml, $networkType);
		if ($created['ok']) {
			$documentId = SemanticClient::documentIdFrom($created['data']);
			if ($documentId === null) {
				einvoice_sendlog_update($logId, array('status' => 'Unknown', 'status_text' => 'Semantic svarede uden dokument-id'));
				semantic_set_digital_status($orderId, 'SendFailed');
				return semantic_send_result(false, 'unknown', 'Semantic modtog dokumentet, men svarede uden dokument-id. Kontakt saldi.dk support, før du sender igen.', $logId);
			}
			einvoice_sendlog_update($logId, array('status' => 'Pending', 'semantic_doc_id' => $documentId, 'status_text' => ''));
			semantic_set_digital_status($orderId, 'Pending');
			return semantic_send_result(true, 'pending', 'Dokumentet er afleveret til Semantic og sendes nu til modtageren.' . $warning, $logId);
		}
		if ($created['kind'] === 'validation' || $created['kind'] === 'auth') {
			einvoice_sendlog_update($logId, array('status' => ($created['kind'] === 'validation') ? 'ValidationFailed' : 'SendFailed', 'status_text' => $created['message']));
			semantic_set_digital_status($orderId, 'SendFailed');
			$text = ($created['kind'] === 'auth') ? 'Semantic afviste adgangen. Kontakt saldi.dk support.' : 'Semantic afviste e-fakturaen, og den er ikke sendt.';
			return semantic_send_result(false, $created['kind'], $text . "\n" . $created['message'], $logId);
		}
		// No answer, a timeout or a 5xx: the document may exist at Semantic. The row stays open as 'Unknown' and the
		// user is asked before a new send.
		// SD-815 OPEN: (Q5) is a duplicate (sender, number, type) rejected, and how do we find a document whose answer
		//              was lost (GetOutgoingDocuments on documentNumber + senderEndpointIds, but the KO/KK series share numbers)?
		einvoice_sendlog_update($logId, array('status' => 'Unknown', 'status_text' => $created['message']));
		semantic_set_digital_status($orderId, 'SendFailed');
		return semantic_send_result(false, 'unknown', 'Der kom intet brugbart svar fra Semantic, så dokumentet kan være modtaget alligevel. Kontakt saldi.dk support, før du sender igen.' . "\n" . $created['message'], $logId);
	}
}

if (!function_exists('semantic_short_text')) {
	/**
	 * At most 300 characters of a report, on one line, for a message to the user.
	 *
	 * @param string $text
	 * @return string
	 */
	function semantic_short_text($text) {
		$text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$text)) ?? '');
		if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 300) {
			return mb_substr($text, 0, 297, 'UTF-8') . '...';
		}
		return $text;
	}
}
