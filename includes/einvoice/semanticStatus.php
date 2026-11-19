<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/semanticStatus.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261109 CL/MMR SD-815 Created (draft). Status of documents sent through Semantic: the status words Saldi shows and semantic_status_apply() for the send log and ordrer.digital_status.
//                 Also the pull fallback semantic_status_poll() and the pure checks behind debitor/semanticWebhook.php (URL token, payload, signature).
// 20261109 CL/MMR SD-815 Review fixes: an error after 'Sent' only stores its text, so the duplicate guard still asks before a resend.
//                 A notification's own status is never applied: when Semantic's status call fails the row waits for the poll.
// 20261112 CL/MMR SD-815 semantic_webhook_parse_payload() also returns incomingDocumentId, and a notice that only names an incoming document is accepted (semanticIncoming.php).

include_once(__DIR__ . '/semanticClient.php');
include_once(__DIR__ . '/semanticSendLog.php');

/*
 * Status words. ordrer.digital_status (varchar(25)) keeps the words the order page already understands
 * (debitor/ordre.php: 'Sent', 'SendFailed'; debitor/orderIncludes/einvoiceCvr.php: 'Sent', 'Received', 'Confirmed',
 * 'Approved'), which are the words debitor/easyUBL.php writes for the same OutgoingDocumentStatus numbers.
 * The send log also uses four words of its own:
 *   Submitting        the row is written and the create call is under way (a crash leaves it, outcome unknown)
 *   Unknown           the create call got no usable answer; the document may exist at Semantic
 *   ValidationFailed  Saldi's own check or Semantic's validation refused the XML; nothing was sent
 *   SendFailed        Semantic refused the call (auth, 4xx); nothing was sent
 * An OutgoingDocumentStatus number without a known name is kept as 'Status<number>' in the send log only.
 */

if (!function_exists('semantic_status_code_words')) {
	/**
	 * OutgoingDocumentStatus numbers and their Saldi words, as debitor/easyUBL.php maps them.
	 * SD-815 OPEN: (Q7) Semantic's swagger has no names; the meanings are assumed to equal EasyUBL's. 5100, 5213, 5214 and
	 *              5215 are unknown, and which values are final is unknown.
	 *
	 * @return array<int, string>
	 */
	function semantic_status_code_words() {
		return array(
			0 => 'NoStatus',
			5101 => 'Error',
			5110 => 'Pending',
			5115 => 'PendingValidating',
			5120 => 'PendingValid',
			5130 => 'Sending',
			5135 => 'Error',
			5140 => 'Sent',
			5150 => 'Received',
			5160 => 'Confirmed',
			5170 => 'Rejected',
			5180 => 'Approved',
			5199 => 'Parked',
		);
	}
}

if (!function_exists('semantic_status_local_words')) {
	/**
	 * The send log's own words (see the comment at the top of this file).
	 *
	 * @return list<string>
	 */
	function semantic_status_local_words() {
		return array('Submitting', 'Unknown', 'ValidationFailed', 'SendFailed');
	}
}

if (!function_exists('semantic_status_word')) {
	/**
	 * The Saldi word for a Semantic status: a number is mapped (unknown numbers become 'Status<number>'), a known word is
	 * kept as it is.
	 *
	 * @param string $semanticStatus An OutgoingDocumentStatus number, or a word from this vocabulary.
	 * @return string|null Null when it is neither.
	 */
	function semantic_status_word($semanticStatus) {
		$semanticStatus = trim((string)$semanticStatus);
		if (preg_match('/^[0-9]{1,6}$/', $semanticStatus)) {
			$words = semantic_status_code_words();
			$code = (int)$semanticStatus;
			return isset($words[$code]) ? $words[$code] : 'Status' . $code;
		}
		$known = array_merge(array_values(semantic_status_code_words()), semantic_status_local_words());
		return in_array($semanticStatus, $known, true) ? $semanticStatus : null;
	}
}

if (!function_exists('semantic_status_digital')) {
	/**
	 * The ordrer.digital_status word a send-log status leads to, or null to leave the order alone.
	 * Every local failure, and an unknown outcome, becomes 'SendFailed', so the order page asks before a new send.
	 *
	 * @param string $word From semantic_status_word().
	 * @return string|null
	 */
	function semantic_status_digital($word) {
		$word = (string)$word;
		if (in_array($word, semantic_status_local_words(), true)) {
			return ($word === 'Submitting') ? null : 'SendFailed';
		}
		if ($word === 'NoStatus' || !in_array($word, semantic_status_code_words(), true)) {
			return null;
		}
		return $word;
	}
}

if (!function_exists('semantic_status_rank')) {
	/**
	 * How far a document has come; null for the error words and unknown numbers.
	 *
	 * @param string $word
	 * @return int|null
	 */
	function semantic_status_rank($word) {
		$ranks = array(
			'NoStatus' => 0, 'Submitting' => 1, 'Unknown' => 1, 'Pending' => 2, 'PendingValidating' => 3, 'PendingValid' => 4,
			'Sending' => 5, 'Parked' => 5, 'Sent' => 6, 'Received' => 7, 'Confirmed' => 8, 'Rejected' => 9, 'Approved' => 9,
		);
		return isset($ranks[$word]) ? $ranks[$word] : null;
	}
}

if (!function_exists('semantic_status_transition')) {
	/**
	 * Whether a send-log row in status $current should move to $next. Notifications can arrive twice or out of order,
	 * so a status never moves back: Sent does not become Pending again. The receiver's answers (Confirmed, Rejected,
	 * Approved) are final, and an error no longer replaces the status once the document was sent: the row keeps 'Sent'
	 * (semantic_status_plan() stores the error text), because 'Error' lets the next send go without asking
	 * (einvoice_sendlog_failed_statuses()) and the receiver may have the invoice.
	 * SD-815 OPEN: (Q7) which statuses are final, and can an error follow 'Sent'?
	 *
	 * @param string $current The row's status ('' for none).
	 * @param string $next    From semantic_status_word().
	 * @return bool
	 */
	function semantic_status_transition($current, $next) {
		$current = (string)$current;
		$next = (string)$next;
		if ($next === '' || $next === $current) {
			return false;
		}
		$currentRank = semantic_status_rank($current);
		$nextRank = semantic_status_rank($next);
		$final = in_array($current, array('Confirmed', 'Rejected', 'Approved'), true);
		if ($nextRank === null) {
			// An error word or an unnamed number: only before the document was sent (rank 6)
			return !$final && ($currentRank === null || $currentRank < 6);
		}
		if ($currentRank === null || in_array($current, array('NoStatus', 'Submitting', 'Unknown'), true)) {
			return true;
		}
		return $nextRank > $currentRank;
	}
}

if (!function_exists('semantic_status_plan')) {
	/**
	 * What semantic_status_apply() will do, without touching the database.
	 *
	 * @param array{id?: int, status?: string, status_text?: string} $logRow
	 * @param string      $semanticStatus
	 * @param string|null $text Semantic's error text or message, if any.
	 * @return array{
	 *   apply: bool,                     Whether the row changes.
	 *   status: string,                  The row's status afterwards.
	 *   fields: array<string, string>,   Send-log fields to write.
	 *   digitalStatus: string|null,      The order's new digital_status, or null to leave it (also when the status is unchanged).
	 *   reason: string,                  'changed', 'text_only', 'unchanged', 'refused_backwards' or 'unknown_status'.
	 * }
	 */
	function semantic_status_plan(array $logRow, $semanticStatus, $text = null) {
		$current = trim((string)($logRow['status'] ?? ''));
		$word = semantic_status_word($semanticStatus);
		$plan = array('apply' => false, 'status' => $current, 'fields' => array(), 'digitalStatus' => null, 'reason' => 'unchanged');
		if ($word === null) {
			$plan['reason'] = 'unknown_status';
			return $plan;
		}
		$text = ($text === null) ? '' : trim(preg_replace('/\s+/u', ' ', (string)$text) ?? '');
		$textChanged = ($text !== '' && $text !== trim((string)($logRow['status_text'] ?? '')));
		if (semantic_status_transition($current, $word)) {
			$plan['apply'] = true;
			$plan['status'] = $word;
			$plan['fields']['status'] = $word;
			if ($textChanged) {
				$plan['fields']['status_text'] = $text;
			}
			$plan['digitalStatus'] = semantic_status_digital($word);
			$plan['reason'] = 'changed';
		} elseif ($word === $current && $textChanged) {
			$plan['apply'] = true;
			$plan['fields']['status_text'] = $text;
			$plan['reason'] = 'text_only';
		} elseif (semantic_status_rank($word) === null && (int)semantic_status_rank($current) >= 6 && !in_array($current, array('Confirmed', 'Rejected', 'Approved'), true)) {
			// An error after Sent: the status stays, the error is kept as text for support (Q7)
			$errorText = $word . (($text !== '') ? ': ' . $text : '');
			if ($errorText !== trim((string)($logRow['status_text'] ?? ''))) {
				$plan['apply'] = true;
				$plan['fields']['status_text'] = $errorText;
				$plan['reason'] = 'text_only';
			}
		} elseif ($word !== $current) {
			$plan['reason'] = 'refused_backwards';
		}
		return $plan;
	}
}

if (!function_exists('semantic_status_apply')) {
	/**
	 * Move a send-log row to a Semantic status and, when that row is the order's newest send, set ordrer.digital_status.
	 * Idempotent: the same status twice changes nothing. Matched on the row's ordre_id, never on fakturanr.
	 *
	 * @param array{id: int, ordre_id: int, art: string, status: string, status_text?: string} $logRow From einvoice_sendlog_*().
	 * @param string      $semanticStatus An OutgoingDocumentStatus number or a status word.
	 * @param string|null $text
	 * @return array{changed: bool, status: string, digitalStatus: string|null, reason: string}
	 */
	function semantic_status_apply(array $logRow, $semanticStatus, $text = null) {
		$plan = semantic_status_plan($logRow, $semanticStatus, $text);
		$out = array('changed' => false, 'status' => $plan['status'], 'digitalStatus' => null, 'reason' => $plan['reason']);
		if (!$plan['apply'] || (int)($logRow['id'] ?? 0) <= 0) {
			return $out;
		}
		einvoice_sendlog_update((int)$logRow['id'], $plan['fields']);
		$out['changed'] = true;
		$orderId = (int)($logRow['ordre_id'] ?? 0);
		if ($plan['digitalStatus'] !== null && $orderId > 0) {
			// An older send's late notification must not overwrite the status of a newer send of the same document.
			$latest = einvoice_sendlog_latest_for_order($orderId, (string)($logRow['art'] ?? ''));
			if ($latest !== null && (int)$latest['id'] === (int)$logRow['id']) {
				$qtxt = "UPDATE ordrer SET digital_status = '" . db_escape_string($plan['digitalStatus']) . "' WHERE id = " . $orderId;
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$out['digitalStatus'] = $plan['digitalStatus'];
			}
		}
		return $out;
	}
}

if (!function_exists('semantic_status_refresh')) {
	/**
	 * Ask Semantic for one row's status and apply it. Only Semantic's answer is applied: the notification that triggered the
	 * call is authenticated by a static URL token only, so its own status is a hint. When the status call fails, the row is
	 * left alone and semantic_status_poll() tries again. The hint's text is used only when the hint's code is the status
	 * Semantic gave. For an error, the document's errorText is fetched, and the UBL document id is stored the first time it is seen.
	 *
	 * @param SemanticClient $client
	 * @param array<string, mixed> $row      From einvoice_sendlog_*(), with semantic_doc_id.
	 * @param int|null       $hintCode A status number from the notification.
	 * @param string|null    $hintText A text from the notification.
	 * @return array{ok: bool, changed: bool, status: string, message: string}
	 */
	function semantic_status_refresh(SemanticClient $client, array $row, $hintCode = null, $hintText = null) {
		$docId = (string)($row['semantic_doc_id'] ?? '');
		$result = $client->getOutgoingDocumentStatus($docId);
		$code = null;
		if ($result['ok'] && (is_int($result['data']) || (is_string($result['data']) && preg_match('/^[0-9]{1,6}$/', $result['data'])))) {
			$code = (int)$result['data'];
		}
		if ($code === null) {
			$message = $result['ok'] ? 'Semantic sendte en ukendt status' : $result['message'];
			return array('ok' => false, 'changed' => false, 'status' => (string)($row['status'] ?? ''), 'message' => $message);
		}
		$word = (string)semantic_status_word((string)$code);
		$text = ($hintCode !== null && (int)$hintCode === $code) ? $hintText : null;
		$needDocument = (trim((string)($row['ubl_doc_id'] ?? '')) === '')
			|| in_array($word, array('Error', 'Rejected'), true) || strncmp($word, 'Status', 6) === 0;
		if ($needDocument) {
			$document = $client->getOutgoingDocument($docId);
			if ($document['ok'] && is_array($document['data'])) {
				$data = $document['data'];
				if (isset($data['errorText']) && is_string($data['errorText']) && trim($data['errorText']) !== '') {
					$text = $data['errorText'];
				}
				$ublId = isset($data['ublDocumentId']) ? (string)$data['ublDocumentId'] : '';
				if (trim((string)($row['ubl_doc_id'] ?? '')) === '' && SemanticClient::isUuid($ublId) && $ublId !== '00000000-0000-0000-0000-000000000000') {
					einvoice_sendlog_update((int)$row['id'], array('ubl_doc_id' => $ublId));
				}
			}
		}
		$applied = semantic_status_apply($row, (string)$code, $text);
		return array('ok' => true, 'changed' => $applied['changed'], 'status' => $applied['status'], 'message' => '');
	}
}

if (!function_exists('semantic_status_poll')) {
	/**
	 * The pull fallback for missed notifications: ask Semantic for the status of the current tenant database's open
	 * send-log rows (einvoice_sendlog_find_open()) and apply it. Does nothing in a copy of a regnskab
	 * (semantic_copy_guard()), so a restored backup never calls Semantic.
	 *
	 * @param int                 $limit  Rows per run, 1..500.
	 * @param SemanticClient|null $client Null = semantic_client().
	 * @param string|null         $db     The current tenant database; null = $GLOBALS['db'].
	 * @return array{checked: int, changed: int, failed: int, messages: list<string>}
	 */
	function semantic_status_poll($limit, $client = null, $db = null) {
		include_once(__DIR__ . '/semanticOnboarding.php');
		$out = array('checked' => 0, 'changed' => 0, 'failed' => 0, 'messages' => array());
		$db = ($db !== null) ? (string)$db : (string)($GLOBALS['db'] ?? '');
		$guard = semantic_copy_guard(semantic_settings_read(), $db);
		if (!$guard['allowed']) {
			$out['messages'][] = $guard['message'];
			return $out;
		}
		$client = $client ?? semantic_client(null, array('connectTimeout' => 5, 'timeout' => 20));
		if (!$client instanceof SemanticClient) {
			$out['messages'][] = 'Semantic er ikke sat op på denne server';
			return $out;
		}
		foreach (einvoice_sendlog_find_open((int)$limit) as $row) {
			$out['checked']++;
			$refreshed = semantic_status_refresh($client, $row);
			if (!$refreshed['ok']) {
				$out['failed']++;
				$out['messages'][] = 'Send-log ' . $row['id'] . ': ' . $refreshed['message'];
			} elseif ($refreshed['changed']) {
				$out['changed']++;
			}
		}
		return $out;
	}
}

if (!function_exists('semantic_webhook_token')) {
	/**
	 * The HMAC token that authenticates the db parameter of the webhook URL.
	 *
	 * @param string $db     Tenant database name.
	 * @param string $secret semantic_webhook_secret().
	 * @return string 64 hex characters.
	 */
	function semantic_webhook_token($db, $secret) {
		return hash_hmac('sha256', 'saldi-semantic-webhook|' . (string)$db, (string)$secret);
	}
}

if (!function_exists('semantic_webhook_db_name_valid')) {
	/**
	 * Whether a db parameter looks like a tenant database name.
	 *
	 * @param mixed $db
	 * @return bool
	 */
	function semantic_webhook_db_name_valid($db) {
		return is_string($db) && (bool)preg_match('/^[A-Za-z0-9_]{1,63}$/', $db);
	}
}

if (!function_exists('semantic_webhook_token_valid')) {
	/**
	 * Whether the URL token belongs to this db name. Constant-time compare; false without a usable secret.
	 *
	 * @param mixed       $db
	 * @param mixed       $token
	 * @param string|null $secret
	 * @return bool
	 */
	function semantic_webhook_token_valid($db, $token, $secret) {
		if (!is_string($secret) || strlen($secret) < 32 || !semantic_webhook_db_name_valid($db) || !is_string($token)) {
			return false;
		}
		$token = strtolower(trim($token));
		if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
			return false;
		}
		return hash_equals(semantic_webhook_token($db, $secret), $token);
	}
}

if (!function_exists('semantic_webhook_url')) {
	/**
	 * The webhook URL given to Semantic for one regnskab (CompanyDto.webHookUrl).
	 *
	 * @param string $baseUrl The installation's public URL, e.g. https://<server>/<installation>.
	 * @param string $db
	 * @param string $secret
	 * @return string
	 */
	function semantic_webhook_url($baseUrl, $db, $secret) {
		$query = http_build_query(array('db' => (string)$db, 't' => semantic_webhook_token($db, $secret)), '', '&', PHP_QUERY_RFC3986);
		return rtrim((string)$baseUrl, '/') . '/debitor/semanticWebhook.php?' . $query;
	}
}

if (!function_exists('semantic_request_headers')) {
	/**
	 * Request headers from $_SERVER, names lower case ("x-foo" for HTTP_X_FOO), for the signature check.
	 *
	 * @param array<string, mixed> $server
	 * @return array<string, string>
	 */
	function semantic_request_headers(array $server) {
		$headers = array();
		foreach ($server as $name => $value) {
			if (is_string($name) && strncmp($name, 'HTTP_', 5) === 0 && is_scalar($value)) {
				$headers[strtolower(str_replace('_', '-', substr($name, 5)))] = (string)$value;
			}
		}
		if (isset($server['CONTENT_TYPE']) && is_scalar($server['CONTENT_TYPE'])) {
			$headers['content-type'] = (string)$server['CONTENT_TYPE'];
		}
		return $headers;
	}
}

if (!function_exists('semantic_webhook_signature_verifier')) {
	/**
	 * The check of Semantic's own webhook signature, or null while its scheme is unknown.
	 *
	 * The portal API can switch signing on (POST /api/Tenant/webhook-signing/enable), and after a rotation "every call
	 * carries a signature for each" secret for a week, but neither swagger names the header, the algorithm, what is
	 * signed or the replay window.
	 * SD-815 OPEN: (Q4) signature header, algorithm, signed content and replay window; and can an integrator key enable
	 *              signing at all (the operations are only in the portal definition)? Until then this returns null, and a
	 *              server that configures signing secrets refuses every call (fail closed) instead of guessing.
	 *
	 * @return (callable(array<string, string>, string, list<string>): bool)|null
	 */
	function semantic_webhook_signature_verifier() {
		return null;
	}
}

if (!function_exists('semantic_webhook_verify_signature')) {
	/**
	 * Check Semantic's signature when signing is configured.
	 *
	 * @param array<string, string> $headers  From semantic_request_headers().
	 * @param string                $rawBody
	 * @param list<string>          $secrets  semantic_signing_secrets(); empty = signing is off.
	 * @param callable|null         $verifier fn(array $headers, string $rawBody, list<string> $secrets): bool
	 * @return array{ok: bool, checked: bool, reason: string} reason 'signing_off', 'valid', 'invalid' or 'scheme_unknown'.
	 */
	function semantic_webhook_verify_signature(array $headers, $rawBody, array $secrets, $verifier = null) {
		if (!$secrets) {
			return array('ok' => true, 'checked' => false, 'reason' => 'signing_off');
		}
		if (!is_callable($verifier)) {
			return array('ok' => false, 'checked' => false, 'reason' => 'scheme_unknown');
		}
		$valid = (bool)call_user_func($verifier, $headers, (string)$rawBody, array_values($secrets));
		return array('ok' => $valid, 'checked' => true, 'reason' => $valid ? 'valid' : 'invalid');
	}
}

if (!function_exists('semantic_webhook_parse_payload')) {
	/**
	 * Read a notification body. Semantic documents no push schema; the field names follow its TenantNotification
	 * schema (documentId, ublDocumentId, documentNumber, message, actionCode ...), and a status number is taken from
	 * documentStatus, documentStatusCode, statusCode or status when one is there, also inside jsonMessage.
	 * SD-815 OPEN: (Q4) the push payload per event type, and the actionCode values.
	 *
	 * @param string $rawBody
	 * @return array{
	 *   ok: bool,
	 *   reason: string,                'ok', 'not_json', 'not_object' or 'no_document_id'.
	 *   kind: string,                  'outgoing', or 'incoming' when the notice names an incoming document.
	 *   documentId: string|null,       Semantic's int64 document id, as digits.
	 *   ublDocumentId: string|null,    uuid.
	 *   statusCode: int|null,
	 *   text: string|null,             errorText, message or lastErrorMessage, at most 1000 characters.
	 *   documentNumber: string|null,
	 *   incomingDocumentId: string|null, Semantic's int64 incoming document id, as digits (incoming notices only).
	 * }
	 */
	function semantic_webhook_parse_payload($rawBody) {
		$out = array('ok' => false, 'reason' => 'not_json', 'kind' => 'outgoing', 'documentId' => null, 'ublDocumentId' => null,
			'statusCode' => null, 'text' => null, 'documentNumber' => null, 'incomingDocumentId' => null);
		$data = json_decode(trim((string)$rawBody), true, 64, JSON_BIGINT_AS_STRING);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return $out;
		}
		if (!is_array($data) || !$data || array_keys($data) === range(0, count($data) - 1)) {
			$out['reason'] = 'not_object';
			return $out;
		}
		$inner = array();
		if (isset($data['jsonMessage']) && is_string($data['jsonMessage'])) {
			$decoded = json_decode($data['jsonMessage'], true, 32, JSON_BIGINT_AS_STRING);
			$inner = is_array($decoded) ? $decoded : array();
		}
		$pick = function (array $keys, callable $accept) use ($data, $inner) {
			foreach (array($data, $inner) as $source) {
				foreach ($keys as $key) {
					if (array_key_exists($key, $source)) {
						$value = call_user_func($accept, $source[$key]);
						if ($value !== null) {
							return $value;
						}
					}
				}
			}
			return null;
		};
		if (array_key_exists('incomingDocumentId', $data) || array_key_exists('incomingDocumentId', $inner)) {
			$out['kind'] = 'incoming';
		}
		$out['documentId'] = $pick(array('outgoingDocumentId', 'documentId'), function ($value) {
			return (is_int($value) || is_string($value)) ? SemanticClient::documentIdFrom(is_int($value) ? $value : trim($value)) : null;
		});
		$out['ublDocumentId'] = $pick(array('ublDocumentId'), function ($value) {
			return (SemanticClient::isUuid($value) && $value !== '00000000-0000-0000-0000-000000000000') ? strtolower($value) : null;
		});
		$out['statusCode'] = $pick(array('documentStatus', 'documentStatusCode', 'statusCode', 'status'), function ($value) {
			if (is_int($value) && $value >= 0) {
				return $value;
			}
			return (is_string($value) && preg_match('/^[0-9]{1,6}$/', trim($value))) ? (int)trim($value) : null;
		});
		$out['text'] = $pick(array('errorText', 'message', 'lastErrorMessage'), function ($value) {
			if (!is_string($value) || trim($value) === '') {
				return null;
			}
			$value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
			return function_exists('mb_substr') ? mb_substr($value, 0, 1000, 'UTF-8') : substr($value, 0, 1000);
		});
		$out['documentNumber'] = $pick(array('documentNumber'), function ($value) {
			return (is_scalar($value) && trim((string)$value) !== '') ? trim((string)$value) : null;
		});
		$out['incomingDocumentId'] = $pick(array('incomingDocumentId'), function ($value) {
			return (is_int($value) || is_string($value)) ? SemanticClient::documentIdFrom(is_int($value) ? $value : trim($value)) : null;
		});
		if ($out['documentId'] === null && $out['ublDocumentId'] === null && $out['incomingDocumentId'] === null) {
			$out['reason'] = 'no_document_id';
			return $out;
		}
		$out['ok'] = true;
		$out['reason'] = 'ok';
		return $out;
	}
}

if (!function_exists('semantic_webhook_preflight')) {
	/**
	 * Everything the webhook decides before it opens a database: method, our URL token, size, Semantic's signature
	 * and the payload. Pure, so it is tested without a server.
	 *
	 * @param string                $method        REQUEST_METHOD.
	 * @param string                $rawBody
	 * @param array<string, mixed>  $get           $_GET.
	 * @param string|null           $secret        semantic_webhook_secret().
	 * @param array<string, string> $headers       semantic_request_headers($_SERVER).
	 * @param list<string>          $signingSecrets semantic_signing_secrets().
	 * @param callable|null         $verifier      semantic_webhook_signature_verifier().
	 * @param int                   $maxBytes      Largest body accepted.
	 * @return array{httpStatus: int, reason: string, db: string|null, payload: array<string, mixed>|null}
	 */
	function semantic_webhook_preflight($method, $rawBody, array $get, $secret, array $headers, array $signingSecrets, $verifier = null, $maxBytes = 1048576) {
		$out = array('httpStatus' => 400, 'reason' => '', 'db' => null, 'payload' => null);
		if (strtoupper((string)$method) !== 'POST') {
			$out['httpStatus'] = 405;
			$out['reason'] = 'method_not_allowed';
			return $out;
		}
		if (!is_string($secret) || strlen($secret) < 32) {
			$out['httpStatus'] = 503;
			$out['reason'] = 'not_configured';
			return $out;
		}
		$db = $get['db'] ?? null;
		if (!semantic_webhook_db_name_valid($db)) {
			$out['reason'] = 'bad_request';
			return $out;
		}
		if (!semantic_webhook_token_valid($db, $get['t'] ?? null, $secret)) {
			$out['httpStatus'] = 403;
			$out['reason'] = 'forbidden';
			return $out;
		}
		if (strlen((string)$rawBody) > (int)$maxBytes) {
			$out['httpStatus'] = 413;
			$out['reason'] = 'too_large';
			return $out;
		}
		$signature = semantic_webhook_verify_signature($headers, (string)$rawBody, $signingSecrets, $verifier);
		if (!$signature['ok']) {
			// An unknown scheme is our configuration, so Semantic should retry once it is fixed.
			$out['httpStatus'] = ($signature['reason'] === 'scheme_unknown') ? 503 : 401;
			$out['reason'] = ($signature['reason'] === 'scheme_unknown') ? 'signature_not_supported' : 'bad_signature';
			return $out;
		}
		$payload = semantic_webhook_parse_payload((string)$rawBody);
		if (!$payload['ok']) {
			$out['reason'] = 'bad_payload';
			return $out;
		}
		$out['httpStatus'] = 200;
		$out['reason'] = 'ok';
		$out['db'] = $db;
		$out['payload'] = $payload;
		return $out;
	}
}
