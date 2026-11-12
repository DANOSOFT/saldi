<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/semanticIncoming.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261112 CL/MMR SD-815 Created. Incoming e-documents through Semantic (tasks 19 and 20), behind the per-regnskab switch provider/einvoice = 'semantic'.
//                 An invoice or credit note lands in the Bilagspulje through poolUploadIngest(), as an uploaded XML does, and the company gets the mail "Ny faktura modtaget" that debitor/easyUBL.php sends.
//                 An ApplicationResponse (the receiver's answer to an invoice we sent) never goes to the pool: it is matched strictly to its einvoice_sendlog row and stored there as the receiver status.
//                 Every incoming document is handled once (table einvoice_incoming), and only what Semantic's API answers is used, never the notification body.

include_once(__DIR__ . '/semanticClient.php');
include_once(__DIR__ . '/semanticSendLog.php');
include_once(__DIR__ . '/semanticOnboarding.php');
include_once(__DIR__ . '/semanticSend.php');

/*
 * Flow (semantic_incoming_process()):
 *   1. The regnskab must be on the Semantic provider and registered at Semantic in this very database
 *      (semantic_copy_guard() reason 'same_db'); a copy of a regnskab never receives anything.
 *   2. The document is read from Semantic's API (GET /api/IncomingDocument filtered on its id), and its
 *      receiverEndpointId must be one of this regnskab's own endpoint ids (settings semantic/endpointId, endpointIdGln).
 *      All regnskaber on a server share one Semantic tenant, so this is what keeps another company's invoice out.
 *   3. The id is claimed in einvoice_incoming (unique semantic_incoming_id). A second delivery of a handled document
 *      changes nothing; a failed one is tried again (at most SEMANTIC_INCOMING_MAX_ATTEMPTS times), and a claim
 *      left by a crashed run is taken over after 15 minutes.
 *   4. The XML is read (GET /api/UblDocument/{ublDocumentId}) and its root element decides:
 *        Invoice, CreditNote  -> the Bilagspulje (poolUploadIngest(), poolCaptureStoreExtraction()) and the mail
 *        ApplicationResponse  -> einvoice_sendlog.receiver_* of the one row it answers, or nothing (logged)
 *        anything else        -> ignored (recorded, nothing else happens)
 *
 * Table einvoice_incoming (includes/betweenUpdates.php, includes/opretfunc.php), tenant database:
 *   id, semantic_incoming_id varchar(40) unique, ubl_doc_id varchar(64), document_type varchar(30), document_number
 *   varchar(100), sender_endpoint varchar(100), sender_name varchar(255), status varchar(30), status_text text,
 *   pool_filename varchar(255), sendlog_id int, claim_token varchar(64), claimed_at timestamp, attempts int,
 *   received timestamp, xml text (an ApplicationResponse only, packed by einvoice_sendlog_pack_xml()), db_name
 *   varchar(64), created timestamp, updated timestamp.
 *
 * status: 'processing' (claimed), 'failed' (tried again), and the final ones in semantic_incoming_final_statuses().
 */

if (!defined('SEMANTIC_INCOMING_MAX_ATTEMPTS')) {
	/** How often a failing incoming document is tried before it is left for support (status stays 'failed'). */
	define('SEMANTIC_INCOMING_MAX_ATTEMPTS', 10);
}

if (!function_exists('semantic_incoming_final_statuses')) {
	/**
	 * einvoice_incoming statuses after which the document is never handled again.
	 *
	 * @return list<string>
	 */
	function semantic_incoming_final_statuses() {
		return array('pooled', 'duplicate', 'response_applied', 'response_older', 'response_unmatched', 'ignored');
	}
}

if (!function_exists('semantic_incoming_columns')) {
	/**
	 * The einvoice_incoming columns callers may write, in einvoice_sendlog_columns()'s format.
	 *
	 * @return array<string, array{type: string, max: int}>
	 */
	function semantic_incoming_columns() {
		return array(
			'ubl_doc_id' => array('type' => 'string', 'max' => 64),
			'document_type' => array('type' => 'string', 'max' => 30),
			'document_number' => array('type' => 'string', 'max' => 100),
			'sender_endpoint' => array('type' => 'string', 'max' => 100),
			'sender_name' => array('type' => 'string', 'max' => 255),
			'status' => array('type' => 'string', 'max' => 30),
			'status_text' => array('type' => 'string', 'max' => 2000),
			'pool_filename' => array('type' => 'string', 'max' => 255),
			'sendlog_id' => array('type' => 'int', 'max' => 0),
			'received' => array('type' => 'string', 'max' => 19),
			'xml' => array('type' => 'text', 'max' => 0),
			'db_name' => array('type' => 'string', 'max' => 64),
		);
	}
}

if (!function_exists('semantic_incoming_text')) {
	/**
	 * A text through findtekst() when it is there (a page or the webhook), else the fallback after the pipe.
	 *
	 * @param string     $key     'key|Dansk tekst'.
	 * @param int|string $sprogId
	 * @return string
	 */
	function semantic_incoming_text($key, $sprogId = 1) {
		if (function_exists('findtekst')) {
			return (string)findtekst($key, $sprogId);
		}
		$parts = explode('|', (string)$key, 2);
		return (string)($parts[1] ?? $parts[0]);
	}
}

if (!function_exists('semantic_incoming_receiver_codes')) {
	/**
	 * The UNCL4343 response codes a Peppol ApplicationResponse carries, with Saldi's text for each.
	 *
	 * @param int|string $sprogId
	 * @return array<string, string>
	 */
	function semantic_incoming_receiver_codes($sprogId = 1) {
		return array(
			'AB' => semantic_incoming_text('einvoice.receiver.ab|Modtaget af modtager', $sprogId),
			'IP' => semantic_incoming_text('einvoice.receiver.ip|Under behandling hos modtager', $sprogId),
			'UQ' => semantic_incoming_text('einvoice.receiver.uq|Spørgsmål fra modtager', $sprogId),
			'CA' => semantic_incoming_text('einvoice.receiver.ca|Godkendt af modtager med forbehold', $sprogId),
			'RE' => semantic_incoming_text('einvoice.receiver.re|Afvist af modtager', $sprogId),
			'AP' => semantic_incoming_text('einvoice.receiver.ap|Godkendt af modtager', $sprogId),
			'PD' => semantic_incoming_text('einvoice.receiver.pd|Betalt af modtager', $sprogId),
		);
	}
}

if (!function_exists('semantic_incoming_receiver_display')) {
	/**
	 * The receiver's answer as one line for the order page, e.g. "Afvist af modtager: Forkert ordrenummer".
	 *
	 * @param array{status?: string, text?: string}|null $receiver From einvoice_sendlog_receiver().
	 * @param int|string $sprogId
	 * @return string '' when the receiver has not answered.
	 */
	function semantic_incoming_receiver_display($receiver, $sprogId = 1) {
		if (!is_array($receiver)) {
			return '';
		}
		$code = strtoupper(trim((string)($receiver['status'] ?? '')));
		if ($code === '') {
			return '';
		}
		$codes = semantic_incoming_receiver_codes($sprogId);
		$label = $codes[$code] ?? $code;
		$text = trim((string)($receiver['text'] ?? ''));
		return ($text === '') ? $label : $label . ': ' . $text;
	}
}

if (!function_exists('semantic_incoming_receiver_rank')) {
	/**
	 * Which answer wins when two carry the same time: the later step of the invoice's handling.
	 *
	 * @param string $code
	 * @return int 0 for an unknown code.
	 */
	function semantic_incoming_receiver_rank($code) {
		$ranks = array('AB' => 1, 'IP' => 2, 'UQ' => 3, 'CA' => 4, 'RE' => 5, 'AP' => 5, 'PD' => 6);
		return $ranks[strtoupper((string)$code)] ?? 0;
	}
}

if (!function_exists('semantic_incoming_one_line')) {
	/**
	 * A text as one trimmed line without tags, at most $max characters.
	 *
	 * @param mixed $value
	 * @param int   $max
	 * @return string
	 */
	function semantic_incoming_one_line($value, $max = 1000) {
		if (!is_scalar($value)) {
			return '';
		}
		$text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$value)) ?? '');
		if (function_exists('mb_substr')) {
			return mb_substr($text, 0, (int)$max, 'UTF-8');
		}
		return substr($text, 0, (int)$max);
	}
}

if (!function_exists('semantic_incoming_datetime')) {
	/**
	 * An API or XML time as 'Y-m-d H:i:s' in the server's time zone, or null.
	 *
	 * @param mixed $value e.g. "2026-11-12T08:15:00Z", "2026-11-12T08:15:00.123+01:00", "2026-11-12".
	 * @return string|null
	 */
	function semantic_incoming_datetime($value) {
		if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}([T ][0-9:.]+(Z|[+-]\d{2}:?\d{2})?)?$/', trim($value))) {
			return null;
		}
		try {
			$date = new DateTime(trim($value));
		} catch (Exception $e) {
			return null;
		}
		$date->setTimezone(new DateTimeZone(date_default_timezone_get()));
		return $date->format('Y-m-d H:i:s');
	}
}

if (!function_exists('semantic_incoming_document')) {
	/**
	 * One IncomingDocument from Semantic's answer, normalized.
	 *
	 * @param mixed $doc
	 * @return array{
	 *   incomingId: string|null,               incomingDocumentId as digits.
	 *   ublDocumentId: string|null,            Lower case uuid.
	 *   receiverEndpointId: string,            Lower case uuid of the receiving endpoint at Semantic, '' when missing.
	 *   documentNumber: string,
	 *   documentType: int|null,
	 *   senderEndpoint: string,                "<scheme>:<identifier>" when both are there, else the identifier.
	 *   senderName: string,
	 *   received: string|null,                 'Y-m-d H:i:s'.
	 *   previousOutgoingDocumentId: string|null, Our outgoing document this one answers, when Semantic knows it.
	 * }|null Null when it is not an object.
	 */
	function semantic_incoming_document($doc) {
		if (!is_array($doc) || !$doc || array_keys($doc) === range(0, count($doc) - 1)) {
			return null;
		}
		$id = function ($value) {
			return (is_int($value) || is_string($value)) ? SemanticClient::documentIdFrom(is_int($value) ? $value : trim($value)) : null;
		};
		$uuid = function ($value) {
			return (SemanticClient::isUuid($value) && $value !== '00000000-0000-0000-0000-000000000000') ? strtolower($value) : null;
		};
		$scheme = semantic_incoming_one_line($doc['senderEndpointType'] ?? '', 20);
		$identifier = semantic_incoming_one_line($doc['senderEndpointIdentifier'] ?? '', 70);
		$type = $doc['documentType'] ?? null;
		return array(
			'incomingId' => $id($doc['incomingDocumentId'] ?? null),
			'ublDocumentId' => $uuid($doc['ublDocumentId'] ?? null),
			'receiverEndpointId' => (string)$uuid($doc['receiverEndpointId'] ?? null),
			'documentNumber' => semantic_incoming_one_line($doc['documentNumber'] ?? '', 100),
			'documentType' => (is_int($type) || (is_string($type) && preg_match('/^[0-9]{1,6}$/', $type))) ? (int)$type : null,
			'senderEndpoint' => ($scheme !== '' && $identifier !== '') ? $scheme . ':' . $identifier : $identifier,
			'senderName' => semantic_incoming_one_line($doc['senderCompanyName'] ?? '', 255),
			'received' => semantic_incoming_datetime($doc['receivedDateTime'] ?? null),
			'previousOutgoingDocumentId' => $id($doc['previousOutgoingDocumentId'] ?? null),
		);
	}
}

if (!function_exists('semantic_incoming_list')) {
	/**
	 * The documents in an IncomingDocument search answer.
	 *
	 * SD-815 ASSUMPTION: the swagger answers a plain array; a paged wrapper ({items|data|documents: [...]}) is accepted too.
	 *
	 * @param mixed $data
	 * @return list<array<string, mixed>>
	 */
	function semantic_incoming_list($data) {
		if (!is_array($data)) {
			return array();
		}
		if ($data && array_keys($data) !== range(0, count($data) - 1)) {
			foreach (array('items', 'data', 'documents') as $key) {
				if (isset($data[$key]) && is_array($data[$key])) {
					return semantic_incoming_list($data[$key]);
				}
			}
			return array();
		}
		$list = array();
		foreach ($data as $doc) {
			if (is_array($doc)) {
				$list[] = $doc;
			}
		}
		return $list;
	}
}

if (!function_exists('semantic_incoming_pick')) {
	/**
	 * The one document in a search answer with this incoming id (or, without one, this UBL id). The filter is not trusted:
	 * the id is compared here, and two documents with the same id find nothing.
	 *
	 * @param mixed       $data          The search answer.
	 * @param string|null $incomingId
	 * @param string|null $ublDocumentId
	 * @return array<string, mixed>|null From semantic_incoming_document().
	 */
	function semantic_incoming_pick($data, $incomingId, $ublDocumentId = null) {
		$found = array();
		$ublDocumentId = ($ublDocumentId === null) ? null : strtolower((string)$ublDocumentId);
		foreach (semantic_incoming_list($data) as $raw) {
			$doc = semantic_incoming_document($raw);
			if ($doc === null) {
				continue;
			}
			if ($incomingId !== null && $incomingId !== '') {
				if ($doc['incomingId'] === (string)$incomingId) {
					$found[] = $doc;
				}
			} elseif ($ublDocumentId !== null && $doc['ublDocumentId'] === $ublDocumentId) {
				$found[] = $doc;
			}
		}
		return (count($found) === 1) ? $found[0] : null;
	}
}

if (!function_exists('semantic_incoming_route')) {
	/**
	 * Whether a document Semantic holds belongs to this regnskab: the regnskab must be registered at Semantic in this
	 * database, and the document's receiving endpoint must be one of its own.
	 *
	 * @param array{receiverEndpointId: string} $doc      From semantic_incoming_document().
	 * @param array<string, string>             $settings From semantic_settings_read().
	 * @param string                            $currentDb
	 * @return array{allowed: bool, reason: string} reason 'ok', 'copy', 'not_registered' or 'wrong_endpoint'.
	 */
	function semantic_incoming_route(array $doc, array $settings, $currentDb) {
		$guard = semantic_incoming_guard($settings, $currentDb);
		if (!$guard['allowed']) {
			return $guard;
		}
		$receiver = strtolower(trim((string)($doc['receiverEndpointId'] ?? '')));
		$own = semantic_incoming_own_endpoint_ids($settings);
		if ($receiver === '' || !in_array($receiver, $own, true)) {
			return array('allowed' => false, 'reason' => 'wrong_endpoint');
		}
		return array('allowed' => true, 'reason' => 'ok');
	}
}

if (!function_exists('semantic_incoming_own_endpoint_ids')) {
	/**
	 * This regnskab's endpoint ids at Semantic, lower case.
	 *
	 * @param array<string, string> $settings From semantic_settings_read().
	 * @return list<string>
	 */
	function semantic_incoming_own_endpoint_ids(array $settings) {
		$own = array();
		foreach (array('endpointId', 'endpointIdGln') as $name) {
			$value = strtolower(trim((string)($settings[$name] ?? '')));
			if (SemanticClient::isUuid($value)) {
				$own[] = $value;
			}
		}
		return $own;
	}
}

if (!function_exists('semantic_incoming_guard')) {
	/**
	 * Whether this database may receive as the Semantic company in its settings at all.
	 *
	 * @param array<string, string> $settings
	 * @param string                $currentDb
	 * @return array{allowed: bool, reason: string} reason 'ok', 'copy' (also a registration without a database name) or
	 *   'not_registered'.
	 */
	function semantic_incoming_guard(array $settings, $currentDb) {
		$copy = semantic_copy_guard($settings, (string)$currentDb);
		if (!$copy['allowed']) {
			return array('allowed' => false, 'reason' => 'copy');
		}
		if ($copy['reason'] !== 'same_db' || trim((string)($settings['companyId'] ?? '')) === '' || !semantic_incoming_own_endpoint_ids($settings)) {
			return array('allowed' => false, 'reason' => 'not_registered');
		}
		return array('allowed' => true, 'reason' => 'ok');
	}
}

if (!function_exists('semantic_incoming_load_xml')) {
	/**
	 * Parse an XML document without DTDs or entities (as extractUblInvoiceData() refuses them).
	 *
	 * @param string $xml
	 * @return DOMDocument|null
	 */
	function semantic_incoming_load_xml($xml) {
		$xml = (string)$xml;
		if (trim($xml) === '' || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false || !class_exists('DOMDocument')) {
			return null;
		}
		$previous = libxml_use_internal_errors(true);
		$document = new DOMDocument();
		$loaded = $document->loadXML($xml, LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		return ($loaded && $document->documentElement) ? $document : null;
	}
}

if (!function_exists('semantic_incoming_children')) {
	/**
	 * The elements under $element along a path of local names (namespace prefixes do not matter).
	 *
	 * @param DOMElement   $element
	 * @param list<string> $path
	 * @return list<DOMElement>
	 */
	function semantic_incoming_children(DOMElement $element, array $path) {
		$current = array($element);
		foreach ($path as $name) {
			$next = array();
			foreach ($current as $parent) {
				foreach ($parent->childNodes as $child) {
					if ($child instanceof DOMElement && $child->localName === $name) {
						$next[] = $child;
					}
				}
			}
			$current = $next;
		}
		return $current;
	}
}

if (!function_exists('semantic_incoming_value')) {
	/**
	 * The trimmed text of the first element along a path, '' when there is none.
	 *
	 * @param DOMElement   $element
	 * @param list<string> $path
	 * @return string
	 */
	function semantic_incoming_value(DOMElement $element, array $path) {
		$found = semantic_incoming_children($element, $path);
		return $found ? trim($found[0]->textContent) : '';
	}
}

if (!function_exists('semantic_incoming_endpoint')) {
	/**
	 * The EndpointID along a path as array{scheme, id}, with the identifier in upper case without white space.
	 *
	 * @param DOMElement   $element
	 * @param list<string> $path Ending in 'EndpointID'.
	 * @return array{scheme: string, id: string}|null
	 */
	function semantic_incoming_endpoint(DOMElement $element, array $path) {
		$found = semantic_incoming_children($element, $path);
		if (!$found) {
			return null;
		}
		$id = strtoupper(preg_replace('/\s+/', '', $found[0]->textContent) ?? '');
		$scheme = trim($found[0]->getAttribute('schemeID'));
		return ($id === '' || $scheme === '') ? null : array('scheme' => $scheme, 'id' => $id);
	}
}

if (!function_exists('semantic_incoming_sent_document')) {
	/**
	 * What the response matching needs from an invoice or credit note we sent (the archived XML of a send-log row).
	 *
	 * @param string|null $xml
	 * @return array{root: string, id: string, issueDate: string, supplier: array{scheme: string, id: string}|null, customer: array{scheme: string, id: string}|null}|null
	 */
	function semantic_incoming_sent_document($xml) {
		$document = ($xml === null) ? null : semantic_incoming_load_xml($xml);
		if ($document === null) {
			return null;
		}
		$root = $document->documentElement;
		if (!in_array($root->localName, array('Invoice', 'CreditNote'), true)) {
			return null;
		}
		return array(
			'root' => $root->localName,
			'id' => semantic_incoming_value($root, array('ID')),
			'issueDate' => semantic_incoming_value($root, array('IssueDate')),
			'supplier' => semantic_incoming_endpoint($root, array('AccountingSupplierParty', 'Party', 'EndpointID')),
			'customer' => semantic_incoming_endpoint($root, array('AccountingCustomerParty', 'Party', 'EndpointID')),
		);
	}
}

if (!function_exists('semantic_incoming_parse_response')) {
	/**
	 * Read a Peppol ApplicationResponse (Invoice Response, or a Message Level Response).
	 *
	 * @param DOMDocument $document Its root is ApplicationResponse.
	 * @return array{
	 *   kind: string,                 'invoice_response', 'mlr' or 'other' (from CustomizationID).
	 *   responseId: string,
	 *   date: string|null,            IssueDate + IssueTime as 'Y-m-d H:i:s'.
	 *   code: string,                 UNCL4343 response code, upper case, '' when missing or unknown.
	 *   text: string,                 The reasons, at most 2000 characters.
	 *   sender: array{scheme: string, id: string}|null,   Who answers (the customer we invoiced).
	 *   receiver: array{scheme: string, id: string}|null, Who the answer is for (us).
	 *   refId: string,                The referenced document's ID (our invoice number for an Invoice Response).
	 *   refTypeCode: string,          UNCL1001, e.g. 380 invoice, 381 credit note.
	 *   refIssueDate: string,
	 * }
	 */
	function semantic_incoming_parse_response(DOMDocument $document) {
		$root = $document->documentElement;
		$customization = semantic_incoming_value($root, array('CustomizationID'));
		$kind = 'other';
		if (stripos($customization, 'invoice_response') !== false) {
			$kind = 'invoice_response';
		} elseif (stripos($customization, 'mlr') !== false) {
			$kind = 'mlr';
		}
		$issueDate = semantic_incoming_value($root, array('IssueDate'));
		$issueTime = semantic_incoming_value($root, array('IssueTime'));
		$date = null;
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate)) {
			$date = semantic_incoming_datetime(preg_match('/^\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})?$/', $issueTime) ? $issueDate . 'T' . $issueTime : $issueDate);
		}
		$code = '';
		$texts = array();
		$responses = semantic_incoming_children($root, array('DocumentResponse', 'Response'));
		if ($responses) {
			$response = $responses[0];
			$code = strtoupper(semantic_incoming_value($response, array('ResponseCode')));
			if (semantic_incoming_receiver_rank($code) === 0) {
				$code = '';
			}
			foreach (semantic_incoming_children($response, array('Description')) as $description) {
				$texts[] = semantic_incoming_one_line($description->textContent, 1000);
			}
			foreach (semantic_incoming_children($response, array('Status')) as $status) {
				$reason = semantic_incoming_value($status, array('StatusReason'));
				$reasonCode = semantic_incoming_children($status, array('StatusReasonCode'));
				if ($reason !== '') {
					$texts[] = semantic_incoming_one_line($reason, 1000);
				} elseif ($reasonCode && strcasecmp($reasonCode[0]->getAttribute('listID'), 'OPStatusAction') !== 0) {
					$texts[] = semantic_incoming_one_line($reasonCode[0]->textContent, 20);
				}
			}
		}
		$texts = array_values(array_unique(array_filter($texts, 'strlen')));
		$reference = semantic_incoming_children($root, array('DocumentResponse', 'DocumentReference'));
		return array(
			'kind' => $kind,
			'responseId' => semantic_incoming_value($root, array('ID')),
			'date' => $date,
			'code' => $code,
			'text' => semantic_incoming_one_line(implode('; ', $texts), 2000),
			'sender' => semantic_incoming_endpoint($root, array('SenderParty', 'EndpointID')),
			'receiver' => semantic_incoming_endpoint($root, array('ReceiverParty', 'EndpointID')),
			'refId' => $reference ? semantic_incoming_value($reference[0], array('ID')) : '',
			'refTypeCode' => $reference ? semantic_incoming_value($reference[0], array('DocumentTypeCode')) : '',
			'refIssueDate' => $reference ? semantic_incoming_value($reference[0], array('IssueDate')) : '',
		);
	}
}

if (!function_exists('semantic_incoming_same_endpoint')) {
	/**
	 * @param array{scheme: string, id: string}|null $a
	 * @param array{scheme: string, id: string}|null $b
	 * @return bool False when either is missing.
	 */
	function semantic_incoming_same_endpoint($a, $b) {
		return is_array($a) && is_array($b) && $a['scheme'] === $b['scheme'] && $a['id'] === $b['id'];
	}
}

if (!function_exists('semantic_incoming_match_response')) {
	/**
	 * Find the one send-log row an ApplicationResponse answers. Strict: every fact the response carries must agree with
	 * the document we archived, and a match that is not unique matches nothing.
	 *
	 * A candidate matches when its fakturanr and the archived XML's ID equal the referenced ID, the responder is the
	 * customer endpoint we sent to, the response is addressed to the supplier endpoint we sent from, and - when the
	 * response states them - the document type (380 invoice, 381 credit note) and the issue date agree. When Semantic
	 * links the response to our outgoing document (previousOutgoingDocumentId), only that row may match. Several matching
	 * rows are only accepted when they are sends of the same order and document kind (a resend); the newest is used.
	 *
	 * @param array<string, mixed> $response   From semantic_incoming_parse_response().
	 * @param list<array{row: array<string, mixed>, sent: array<string, mixed>|null}> $candidates Rows with the referenced
	 *                                          fakturanr and their parsed archive (semantic_incoming_sent_document()).
	 * @param string|null          $linkedDocId IncomingDocument.previousOutgoingDocumentId.
	 * @return array{matched: bool, reason: string, row: array<string, mixed>|null} reason 'matched', 'no_reference',
	 *   'no_parties', 'no_candidates', 'no_match', 'link_mismatch' or 'ambiguous'.
	 */
	function semantic_incoming_match_response(array $response, array $candidates, $linkedDocId = null) {
		$out = array('matched' => false, 'reason' => 'no_match', 'row' => null);
		$refId = trim((string)($response['refId'] ?? ''));
		if ($refId === '') {
			$out['reason'] = 'no_reference';
			return $out;
		}
		if (!is_array($response['sender'] ?? null) || !is_array($response['receiver'] ?? null)) {
			$out['reason'] = 'no_parties';
			return $out;
		}
		if (!$candidates) {
			$out['reason'] = 'no_candidates';
			return $out;
		}
		$typeCode = trim((string)($response['refTypeCode'] ?? ''));
		$root = ($typeCode === '380') ? 'Invoice' : (($typeCode === '381') ? 'CreditNote' : '');
		$refIssueDate = trim((string)($response['refIssueDate'] ?? ''));
		$matches = array();
		foreach ($candidates as $candidate) {
			$row = $candidate['row'] ?? null;
			$sent = $candidate['sent'] ?? null;
			if (!is_array($row) || !is_array($sent)) {
				continue;
			}
			if ((string)($row['fakturanr'] ?? '') !== $refId || (string)$sent['id'] !== $refId) {
				continue;
			}
			if (!semantic_incoming_same_endpoint($sent['customer'], $response['sender']) || !semantic_incoming_same_endpoint($sent['supplier'], $response['receiver'])) {
				continue;
			}
			if ($root !== '' && $sent['root'] !== $root) {
				continue;
			}
			if ($refIssueDate !== '' && $sent['issueDate'] !== $refIssueDate) {
				continue;
			}
			$matches[] = $row;
		}
		if ($linkedDocId !== null && $linkedDocId !== '') {
			$linked = array();
			foreach ($matches as $row) {
				if ((string)($row['semantic_doc_id'] ?? '') === (string)$linkedDocId) {
					$linked[] = $row;
				}
			}
			if ($matches && !$linked) {
				$out['reason'] = 'link_mismatch';
				return $out;
			}
			$matches = $linked;
		}
		if (!$matches) {
			return $out;
		}
		$documents = array();
		$newest = null;
		$orderless = false;
		foreach ($matches as $row) {
			$orderless = $orderless || (int)($row['ordre_id'] ?? 0) <= 0;
			$documents[(int)($row['ordre_id'] ?? 0) . '/' . (string)($row['art'] ?? '')] = true;
			if ($newest === null || (int)$row['id'] > (int)$newest['id']) {
				$newest = $row;
			}
		}
		if (count($documents) > 1 || ($orderless && count($matches) > 1)) {
			$out['reason'] = 'ambiguous';
			return $out;
		}
		return array('matched' => true, 'reason' => 'matched', 'row' => $newest);
	}
}

if (!function_exists('semantic_incoming_receiver_should_apply')) {
	/**
	 * Whether a receiver's answer replaces the one stored: answers can arrive out of order, so an older one never
	 * replaces a newer one, and at the same time the later step wins (semantic_incoming_receiver_rank()).
	 *
	 * @param array{status?: string, date?: string}|null $current From einvoice_sendlog_receiver().
	 * @param string $code
	 * @param string $date 'Y-m-d H:i:s'
	 * @return bool
	 */
	function semantic_incoming_receiver_should_apply($current, $code, $date) {
		$currentCode = is_array($current) ? trim((string)($current['status'] ?? '')) : '';
		$currentDate = is_array($current) ? trim((string)($current['date'] ?? '')) : '';
		if ($currentCode === '' || $currentDate === '') {
			return true;
		}
		if ($date !== $currentDate) {
			return strcmp($date, $currentDate) > 0;
		}
		return semantic_incoming_receiver_rank($code) >= semantic_incoming_receiver_rank($currentCode);
	}
}

if (!function_exists('semantic_incoming_pool_filename')) {
	/**
	 * The name an incoming document is offered to the pool under (poolUploadIngest() renames it from the invoice's
	 * vendor and date when it can read them).
	 *
	 * @param string $root           'Invoice' or 'CreditNote'.
	 * @param string $documentNumber
	 * @return string e.g. "efaktura_1001.xml", "ekreditnota_77.xml".
	 */
	function semantic_incoming_pool_filename($root, $documentNumber) {
		$base = ($root === 'CreditNote') ? 'ekreditnota' : 'efaktura';
		$number = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$documentNumber) ?? '';
		$number = trim(substr($number, 0, 60), '-');
		return $base . (($number !== '') ? '_' . $number : '') . '.xml';
	}
}

if (!function_exists('semantic_incoming_mail_content')) {
	/**
	 * The mail the company gets for a new incoming invoice: the subject and text debitor/easyUBL.php sends.
	 *
	 * @param string     $time 'Y-m-d H:i:s'
	 * @param int|string $sprogId
	 * @return array{subject: string, html: string}
	 */
	function semantic_incoming_mail_content($time, $sprogId = 1) {
		$h = function ($text) {
			return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
		};
		$html = "<html>\n<head>\n\t<title>" . $h(semantic_incoming_text('einvoice.incoming.mail_title|Faktura notification', $sprogId)) . "</title>\n</head>\n<body>\n";
		$html .= "\t<h2>" . $h(semantic_incoming_text('einvoice.incoming.mail_heading|Du har modtaget en ny elektronisk faktura', $sprogId)) . "</h2>\n";
		$html .= "\t<p>" . $h(semantic_incoming_text('einvoice.incoming.mail_where|Du kan se den i din dokument pulje', $sprogId)) . "</p>\n";
		$html .= "\t<p>" . $h(semantic_incoming_text('einvoice.incoming.mail_how|Du finder dokument puljen under kassekladder -> gå ind på en kladde -> papirklips ikonet', $sprogId)) . "</p>\n";
		$html .= "\t<p>" . $h(semantic_incoming_text('einvoice.incoming.mail_time|Tidspunkt', $sprogId)) . ': ' . $h($time) . "</p>\n";
		$html .= "</body>\n</html>\n";
		return array('subject' => semantic_incoming_text('einvoice.incoming.mail_subject|Ny faktura modtaget', $sprogId), 'html' => $html);
	}
}

if (!class_exists('SemanticIncomingStore', false)) {
	/**
	 * Everything semantic_incoming_process() reads and writes outside Semantic: the tenant database, the pool and the
	 * mail. Tests replace it with an in-memory subclass.
	 */
	class SemanticIncomingStore {
		/**
		 * Whether einvoice_incoming and the send log's receiver columns exist (includes/betweenUpdates.php has run).
		 *
		 * @return bool
		 */
		public function ready() {
			global $db_type;
			$schema = einvoice_sendlog_is_mysql($db_type) ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
			$table = db_fetch_array(db_select("SELECT table_name FROM information_schema.tables WHERE table_name = 'einvoice_incoming'$schema", __FILE__ . " linje " . __LINE__));
			if (!$table) {
				return false;
			}
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'einvoice_sendlog' AND column_name = 'receiver_status'$schema";
			return (bool)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		}

		/**
		 * @return array<string, string> semantic_settings_read()
		 */
		public function settings() {
			return semantic_settings_read();
		}

		/**
		 * @return bool The per-regnskab switch selects Semantic.
		 */
		public function providerIsSemantic() {
			return semantic_provider_enabled();
		}

		/**
		 * The einvoice_incoming row for an incoming id.
		 *
		 * @param string $incomingId
		 * @return array{status: string, attempts: int, pool_filename: string, sendlog_id: int}|null
		 */
		public function get($incomingId) {
			$qtxt = "SELECT status, attempts, pool_filename, sendlog_id FROM einvoice_incoming WHERE semantic_incoming_id = '" . db_escape_string((string)$incomingId) . "'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			if (!is_array($r)) {
				return null;
			}
			return array('status' => trim((string)$r['status']), 'attempts' => (int)$r['attempts'], 'pool_filename' => trim((string)$r['pool_filename']), 'sendlog_id' => (int)$r['sendlog_id']);
		}

		/**
		 * Claim an incoming id for this run: a new row, or a failed one, or one a crashed run left over 15 minutes ago.
		 *
		 * @param string               $incomingId
		 * @param array<string, mixed> $fields Columns from semantic_incoming_columns() to store with a new row.
		 * @return string|null The claim token, null when another run has it or it is done.
		 */
		public function claim($incomingId, array $fields) {
			global $db_type;
			$token = bin2hex(random_bytes(16));
			$now = date('Y-m-d H:i:s');
			$stale = date('Y-m-d H:i:s', time() - 900);
			$id = db_escape_string((string)$incomingId);
			$columns = semantic_incoming_columns();
			$names = array('semantic_incoming_id', 'status', 'claim_token', 'claimed_at', 'attempts', 'created', 'updated');
			$values = array("'$id'", "'processing'", "'$token'", "'$now'", '1', 'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP');
			foreach ($fields as $name => $value) {
				if (isset($columns[$name]) && !in_array($name, $names, true) && $name !== 'status') {
					$names[] = $name;
					$values[] = einvoice_sendlog_sql_value($value, $columns[$name], 'db_escape_string', $db_type);
				}
			}
			$conflict = einvoice_sendlog_is_mysql($db_type) ? ' ON DUPLICATE KEY UPDATE id = id' : ' ON CONFLICT (semantic_incoming_id) DO NOTHING';
			db_modify('INSERT INTO einvoice_incoming (' . implode(', ', $names) . ') VALUES (' . implode(', ', $values) . ')' . $conflict, __FILE__ . " linje " . __LINE__);
			$qtxt = "UPDATE einvoice_incoming SET status = 'processing', claim_token = '$token', claimed_at = '$now', attempts = attempts + 1, updated = CURRENT_TIMESTAMP";
			$qtxt .= " WHERE semantic_incoming_id = '$id' AND claim_token <> '$token' AND attempts < " . (int)SEMANTIC_INCOMING_MAX_ATTEMPTS;
			$qtxt .= " AND (status = 'failed' OR (status = 'processing' AND claimed_at < '$stale'))";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("SELECT claim_token FROM einvoice_incoming WHERE semantic_incoming_id = '$id'", __FILE__ . " linje " . __LINE__));
			return (is_array($r) && (string)$r['claim_token'] === $token) ? $token : null;
		}

		/**
		 * Store the outcome of a claimed id (only while this run still holds the claim).
		 *
		 * @param string               $incomingId
		 * @param string               $token  From claim().
		 * @param array<string, mixed> $fields Columns from semantic_incoming_columns(), with status.
		 * @return void
		 */
		public function finish($incomingId, $token, array $fields) {
			global $db_type;
			$columns = semantic_incoming_columns();
			$sets = array();
			foreach ($fields as $name => $value) {
				if (isset($columns[$name])) {
					$sets[] = $name . ' = ' . einvoice_sendlog_sql_value($value, $columns[$name], 'db_escape_string', $db_type);
				}
			}
			if (!$sets) {
				return;
			}
			$sets[] = 'updated = CURRENT_TIMESTAMP';
			$qtxt = 'UPDATE einvoice_incoming SET ' . implode(', ', $sets) . " WHERE semantic_incoming_id = '" . db_escape_string((string)$incomingId) . "'";
			$qtxt .= " AND claim_token = '" . db_escape_string((string)$token) . "'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}

		/**
		 * Put an incoming invoice or credit note in the tenant's Bilagspulje, the way an uploaded XML goes there
		 * (poolUploadIngest(): one copy per content, vendor and date read from the UBL, poolCaptureStoreExtraction()).
		 *
		 * @param string $xml
		 * @param string $filename Offered name, ending in .xml.
		 * @param string $db       Tenant database.
		 * @return array{success: bool, duplicate?: bool, existing?: string, filename?: string, message: string}
		 */
		public function poolIngest($xml, $filename, $db) {
			require_once __DIR__ . '/../docsIncludes/poolPaths.php';
			require_once __DIR__ . '/../docsIncludes/poolUpload.php';
			require_once __DIR__ . '/../docsIncludes/invoiceExtractionApi.php';
			$captureFile = __DIR__ . '/../docsIncludes/poolCapture.php';
			if (is_file($captureFile)) {
				require_once $captureFile;
			}
			if (!isset($GLOBALS['sprog_id'])) {
				$GLOBALS['sprog_id'] = 1; // no login here; poolUploadIngest()'s messages are only logged
			}
			$stageDir = sys_get_temp_dir() . '/saldi_einvoice_' . bin2hex(random_bytes(12));
			if (!mkdir($stageDir, 0700)) {
				return array('success' => false, 'message' => 'Kunne ikke klargøre det modtagne dokument');
			}
			$staged = $stageDir . '/incoming.xml';
			try {
				if (file_put_contents($staged, (string)$xml) === false) {
					return array('success' => false, 'message' => 'Kunne ikke gemme det modtagne dokument');
				}
				$poolDir = poolPuljePath((string)$db);
				$result = poolUploadIngest($staged, $filename, $poolDir, true, true);
				if (!empty($result['success']) && !empty($result['extracted']) && function_exists('poolCaptureStoreExtraction')) {
					poolCaptureStoreExtraction($result['filename'], $poolDir . '/' . $result['filename'], $result['extracted']);
				}
				return $result;
			} finally {
				if (is_file($staged)) {
					unlink($staged);
				}
				rmdir($stageDir);
			}
		}

		/**
		 * The company's own mail address from Stamdata, as debitor/easyUBL.php reads it.
		 *
		 * @return string '' when none is valid.
		 */
		public function companyEmail() {
			$r = db_fetch_array(db_select("SELECT email FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1", __FILE__ . " linje " . __LINE__));
			$email = is_array($r) ? trim((string)$r['email']) : '';
			return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
		}

		/**
		 * Send the notification mail the way debitor/easyUBL.php's sendSimpleMail() does (mail(), HTML, From noreply@<host>).
		 *
		 * @param string $to
		 * @param string $subject
		 * @param string $html
		 * @return bool
		 */
		public function sendMail($to, $subject, $html) {
			$host = (string)($_SERVER['HTTP_HOST'] ?? '');
			if ($host === '') {
				$host = (string)parse_url((string)semantic_webhook_base_url(), PHP_URL_HOST);
			}
			$host = preg_replace('/:\d+$/', '', $host) ?? '';
			$headers = array();
			if (preg_match('/^[A-Za-z0-9.-]+$/', $host)) {
				$headers[] = 'From: noreply@' . $host;
				$headers[] = 'Reply-To: noreply@' . $host;
			}
			$headers[] = 'X-Mailer: PHP/' . phpversion();
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			$subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
			return mail($to, $subject, $html, implode("\r\n", $headers) . "\r\n");
		}

		/**
		 * @param string $fakturanr
		 * @return list<array<string, mixed>> einvoice_sendlog_find_by_fakturanr()
		 */
		public function sendlogByFakturanr($fakturanr) {
			return einvoice_sendlog_find_by_fakturanr($fakturanr);
		}

		/**
		 * @param int $id
		 * @return string|null einvoice_sendlog_xml()
		 */
		public function sendlogXml($id) {
			return einvoice_sendlog_xml($id);
		}

		/**
		 * @param int $id
		 * @return array{status: string, text: string, date: string, responseId: string}|null einvoice_sendlog_receiver()
		 */
		public function sendlogReceiver($id) {
			return einvoice_sendlog_receiver($id);
		}

		/**
		 * @param int                   $id
		 * @param array<string, string> $fields receiver_* columns.
		 * @return void
		 */
		public function sendlogSetReceiver($id, array $fields) {
			einvoice_sendlog_update((int)$id, $fields);
		}

		/**
		 * @param string $message Never the document or a key.
		 * @return void
		 */
		public function log($message) {
			error_log('semanticIncoming: ' . $message);
		}
	}
}

if (!function_exists('semantic_incoming_process')) {
	/**
	 * Handle one incoming document of the current tenant database: an invoice or credit note goes to the Bilagspulje
	 * (with the mail "Ny faktura modtaget"), an ApplicationResponse is stored on the send-log row it answers.
	 *
	 * Called by debitor/semanticWebhook.php for a notification (with the ids from the notice: the document itself is
	 * read from Semantic, never from the notice) and by semantic_incoming_poll() (with the document from Semantic's list).
	 * Idempotent: a document already handled is not read again.
	 *
	 * @param SemanticClient $client
	 * @param array{incomingId?: string|null, ublDocumentId?: string|null, document?: array<string, mixed>} $ref
	 *        The ids from a notification, or 'document' = one IncomingDocument from Semantic's own answer.
	 * @param array{db?: string, store?: SemanticIncomingStore, sprogId?: int, now?: string} $options
	 *        db: the current tenant database (default $GLOBALS['db']); now: 'Y-m-d H:i:s' for the mail (default now).
	 * @return array{
	 *   ok: bool,          False when it should be tried again (Semantic or the pool failed) or must not run here.
	 *   outcome: string,   'pooled', 'duplicate', 'response_applied', 'response_older', 'response_unmatched', 'ignored',
	 *                      'already', 'busy', 'failed', 'fetch_failed', 'not_found', 'wrong_endpoint', 'copy',
	 *                      'not_registered', 'provider_off', 'not_ready' or 'bad_reference'.
	 *   message: string,   A short reason for the log.
	 *   incomingId: string|null,
	 * }
	 */
	function semantic_incoming_process(SemanticClient $client, array $ref, array $options = array()) {
		$store = (isset($options['store']) && $options['store'] instanceof SemanticIncomingStore) ? $options['store'] : new SemanticIncomingStore();
		$db = isset($options['db']) ? (string)$options['db'] : (string)($GLOBALS['db'] ?? '');
		$sprogId = (int)($options['sprogId'] ?? 1);
		$out = function ($ok, $outcome, $message = '', $incomingId = null) {
			return array('ok' => (bool)$ok, 'outcome' => $outcome, 'message' => (string)$message, 'incomingId' => $incomingId);
		};
		if (!$store->ready()) {
			return $out(false, 'not_ready', 'einvoice_incoming mangler: regnskabet er ikke opdateret endnu');
		}
		if (!$store->providerIsSemantic()) {
			return $out(false, 'provider_off', 'Regnskabet bruger ikke Semantic');
		}
		$settings = $store->settings();
		$guard = semantic_incoming_guard($settings, $db);
		if (!$guard['allowed']) {
			$store->log($guard['reason'] . " in $db");
			return $out(false, $guard['reason'], ($guard['reason'] === 'copy') ? 'Kopi af et regnskab: intet modtages' : 'Regnskabet er ikke registreret hos Semantic');
		}

		// The document as Semantic's API has it
		if (isset($ref['document'])) {
			$doc = semantic_incoming_document($ref['document']);
			if ($doc === null || $doc['incomingId'] === null) {
				return $out(false, 'bad_reference', 'Dokumentet fra Semantic har intet id');
			}
		} else {
			$incomingId = isset($ref['incomingId']) ? SemanticClient::documentIdFrom(is_string($ref['incomingId']) ? trim($ref['incomingId']) : $ref['incomingId']) : null;
			$ublId = (isset($ref['ublDocumentId']) && SemanticClient::isUuid($ref['ublDocumentId'])) ? strtolower($ref['ublDocumentId']) : null;
			if ($incomingId === null && $ublId === null) {
				return $out(false, 'bad_reference', 'Ingen dokument-id');
			}
			if ($incomingId !== null) {
				$known = $store->get($incomingId);
				if ($known !== null && in_array($known['status'], semantic_incoming_final_statuses(), true)) {
					return $out(true, 'already', '', $incomingId);
				}
			}
			$filter = array('companyId' => $settings['companyId'], 'pageNumber' => 1, 'pageSize' => 10);
			if ($incomingId !== null && strlen($incomingId) <= 18) {
				$filter['id'] = (int)$incomingId;
			} elseif ($ublId !== null) {
				$filter['documentId'] = $ublId;
			}
			$answer = $client->getIncomingDocuments($filter);
			if (!$answer['ok']) {
				$store->log("search for incoming document failed in $db: " . $answer['message']);
				return $out(false, 'fetch_failed', $answer['message'], $incomingId);
			}
			$doc = semantic_incoming_pick($answer['data'], $incomingId, $ublId);
			if ($doc === null || $doc['incomingId'] === null) {
				$store->log('incoming document ' . ($incomingId ?? $ublId) . " not found at Semantic for $db");
				return $out(false, 'not_found', 'Semantic kender ikke dokumentet', $incomingId);
			}
		}
		$incomingId = $doc['incomingId'];
		$route = semantic_incoming_route($doc, $settings, $db);
		if (!$route['allowed']) {
			$store->log("incoming document $incomingId is not for $db (" . $route['reason'] . ')');
			return $out(false, $route['reason'], 'Dokumentet er ikke til dette regnskab', $incomingId);
		}
		$known = $store->get($incomingId);
		if ($known !== null && in_array($known['status'], semantic_incoming_final_statuses(), true)) {
			return $out(true, 'already', '', $incomingId);
		}
		$token = $store->claim($incomingId, array(
			'ubl_doc_id' => (string)$doc['ublDocumentId'],
			'document_number' => $doc['documentNumber'],
			'sender_endpoint' => $doc['senderEndpoint'],
			'sender_name' => $doc['senderName'],
			'received' => $doc['received'],
			'db_name' => $db,
		));
		if ($token === null) {
			$known = $store->get($incomingId);
			$done = ($known !== null && in_array($known['status'], semantic_incoming_final_statuses(), true));
			return $out($done, $done ? 'already' : 'busy', $done ? '' : 'Dokumentet behandles allerede, eller er forsøgt for mange gange', $incomingId);
		}
		$fail = function ($message) use ($store, $incomingId, $token, $out, $db) {
			$store->finish($incomingId, $token, array('status' => 'failed', 'status_text' => $message));
			$store->log("incoming document $incomingId in $db failed: $message");
			return $out(false, 'failed', $message, $incomingId);
		};

		// The document itself
		if ($doc['ublDocumentId'] === null) {
			return $fail('Semantic oplyste ikke dokumentets UBL-id');
		}
		$xmlAnswer = $client->getUblDocumentXml($doc['ublDocumentId']);
		if (!$xmlAnswer['ok']) {
			return $fail($xmlAnswer['message']);
		}
		$xml = (string)$xmlAnswer['data'];
		$document = semantic_incoming_load_xml($xml);
		if ($document === null) {
			return $fail('Dokumentet kunne ikke læses som XML');
		}
		$root = $document->documentElement->localName;

		if ($root === 'Invoice' || $root === 'CreditNote') {
			$number = ($doc['documentNumber'] !== '') ? $doc['documentNumber'] : semantic_incoming_value($document->documentElement, array('ID'));
			$pooled = $store->poolIngest($xml, semantic_incoming_pool_filename($root, $number), $db);
			if (!empty($pooled['duplicate'])) {
				$store->finish($incomingId, $token, array('status' => 'duplicate', 'document_type' => $root, 'pool_filename' => (string)($pooled['existing'] ?? ''),
					'status_text' => 'Ligger allerede i puljen'));
				return $out(true, 'duplicate', '', $incomingId);
			}
			if (empty($pooled['success']) || (string)($pooled['filename'] ?? '') === '') {
				return $fail('Puljen: ' . semantic_incoming_one_line($pooled['message'] ?? '', 200));
			}
			$store->finish($incomingId, $token, array('status' => 'pooled', 'document_type' => $root, 'pool_filename' => (string)$pooled['filename'], 'status_text' => ''));
			$to = $store->companyEmail();
			if ($to === '') {
				$store->log("no company mail address for the incoming document notice in $db");
			} else {
				$mail = semantic_incoming_mail_content((string)($options['now'] ?? date('Y-m-d H:i:s')), $sprogId);
				if (!$store->sendMail($to, $mail['subject'], $mail['html'])) {
					$store->log("the incoming document notice could not be mailed in $db");
				}
			}
			return $out(true, 'pooled', '', $incomingId);
		}

		if ($root === 'ApplicationResponse') {
			return semantic_incoming_apply_response($store, $document, $xml, $doc, $token, $db, $out);
		}

		$store->finish($incomingId, $token, array('status' => 'ignored', 'document_type' => substr($root, 0, 30), 'status_text' => 'Dokumenttypen modtages ikke i Saldi'));
		$store->log("incoming document $incomingId in $db is a $root: ignored");
		return $out(true, 'ignored', '', $incomingId);
	}
}

if (!function_exists('semantic_incoming_apply_response')) {
	/**
	 * The ApplicationResponse part of semantic_incoming_process(): match and store, never the pool.
	 *
	 * @param SemanticIncomingStore $store
	 * @param DOMDocument           $document
	 * @param string                $xml
	 * @param array<string, mixed>  $doc   From semantic_incoming_document().
	 * @param string                $token The claim.
	 * @param string                $db
	 * @param callable              $out   The result builder of semantic_incoming_process().
	 * @return array{ok: bool, outcome: string, message: string, incomingId: string|null}
	 */
	function semantic_incoming_apply_response(SemanticIncomingStore $store, DOMDocument $document, $xml, array $doc, $token, $db, callable $out) {
		$incomingId = (string)$doc['incomingId'];
		$response = semantic_incoming_parse_response($document);
		$record = array('document_type' => 'ApplicationResponse', 'xml' => einvoice_sendlog_pack_xml($xml));
		$unmatched = function ($reason) use ($store, $incomingId, $token, $record, $response, $db, $out) {
			$text = $reason . ' (' . $response['kind'] . ', ' . ($response['code'] !== '' ? $response['code'] : '-') . ', ref ' . ($response['refId'] !== '' ? $response['refId'] : '-') . ')';
			$store->finish($incomingId, $token, $record + array('status' => 'response_unmatched', 'status_text' => $text));
			$store->log("application response $incomingId in $db matched no sent document: $text");
			return $out(true, 'response_unmatched', $text, $incomingId);
		};
		if ($response['code'] === '') {
			return $unmatched('no_code');
		}
		$candidates = array();
		foreach ($store->sendlogByFakturanr($response['refId']) as $row) {
			$candidates[] = array('row' => $row, 'sent' => semantic_incoming_sent_document($store->sendlogXml((int)$row['id'])));
		}
		$match = semantic_incoming_match_response($response, $candidates, $doc['previousOutgoingDocumentId']);
		if (!$match['matched']) {
			return $unmatched($match['reason']);
		}
		$row = $match['row'];
		$date = $response['date'] ?? $doc['received'] ?? date('Y-m-d H:i:s');
		$record['sendlog_id'] = (int)$row['id'];
		if (!semantic_incoming_receiver_should_apply($store->sendlogReceiver((int)$row['id']), $response['code'], $date)) {
			$store->finish($incomingId, $token, $record + array('status' => 'response_older', 'status_text' => $response['code'] . ' er ældre end det gemte svar'));
			return $out(true, 'response_older', '', $incomingId);
		}
		$store->sendlogSetReceiver((int)$row['id'], array(
			'receiver_status' => $response['code'],
			'receiver_status_text' => $response['text'],
			'receiver_status_date' => $date,
			'receiver_response_id' => $incomingId,
		));
		$store->finish($incomingId, $token, $record + array('status' => 'response_applied', 'status_text' => $response['code']));
		return $out(true, 'response_applied', '', $incomingId);
	}
}

if (!function_exists('semantic_incoming_poll')) {
	/**
	 * The pull fallback for missed incoming notifications, for a cron job: list the current tenant database's incoming
	 * documents received since $since and handle each one (semantic_incoming_process()). Already handled documents are
	 * skipped without reading them again, so overlapping windows are harmless: call it with the 'newest' of the previous
	 * run minus a margin (e.g. one hour), or a day back the first time. Does nothing in a copy of a regnskab, in one that
	 * is not on the Semantic provider, or before includes/betweenUpdates.php created einvoice_incoming.
	 * The caller connects to the tenant database first (includes/connect.php, db_connect(), $db) and includes
	 * includes/std_func.php (get_settings_value(), findtekst()), as debitor/semanticWebhook.php does.
	 *
	 * SD-815 ASSUMPTION: DocumentFilter.pageNumber starts at 1 and receivedFrom filters on receivedDateTime.
	 *
	 * @param string                     $since  'Y-m-d H:i:s' (server time) or ISO 8601.
	 * @param int                        $limit  Documents per run, 1..500.
	 * @param SemanticClient|null        $client Null = semantic_client().
	 * @param string|null                $db     The current tenant database; null = $GLOBALS['db'].
	 * @param SemanticIncomingStore|null $store
	 * @return array{
	 *   listed: int,       Documents Semantic listed for this regnskab.
	 *   handled: int,      Newly handled now (pooled, duplicate or a response stored/recorded, or ignored).
	 *   already: int,      Handled before.
	 *   failed: int,       To be tried again by the next run.
	 *   skipped: int,      Not this regnskab's (another endpoint), or busy in another run.
	 *   newest: string|null, The newest receivedDateTime seen ('Y-m-d H:i:s'), for the next run's $since.
	 *   messages: list<string>,
	 * }
	 */
	function semantic_incoming_poll($since, $limit = 100, $client = null, $db = null, $store = null) {
		$out = array('listed' => 0, 'handled' => 0, 'already' => 0, 'failed' => 0, 'skipped' => 0, 'newest' => null, 'messages' => array());
		$db = ($db !== null) ? (string)$db : (string)($GLOBALS['db'] ?? '');
		$store = ($store instanceof SemanticIncomingStore) ? $store : new SemanticIncomingStore();
		if (!$store->ready()) {
			$out['messages'][] = 'einvoice_incoming mangler: regnskabet er ikke opdateret endnu';
			return $out;
		}
		if (!$store->providerIsSemantic()) {
			$out['messages'][] = 'Regnskabet bruger ikke Semantic';
			return $out;
		}
		$settings = $store->settings();
		$guard = semantic_incoming_guard($settings, $db);
		if (!$guard['allowed']) {
			$out['messages'][] = ($guard['reason'] === 'copy') ? 'Kopi af et regnskab: intet modtages' : 'Regnskabet er ikke registreret hos Semantic';
			return $out;
		}
		$sinceTime = strtotime((string)$since);
		if ($sinceTime === false) {
			$out['messages'][] = 'Ugyldigt tidspunkt';
			return $out;
		}
		$client = $client ?? semantic_client(null, array('connectTimeout' => 5, 'timeout' => 30));
		if (!$client instanceof SemanticClient) {
			$out['messages'][] = 'Semantic er ikke sat op på denne server';
			return $out;
		}
		$limit = max(1, min(500, (int)$limit));
		$pageSize = min(100, $limit);
		$seen = 0;
		for ($page = 1; $seen < $limit && $page <= (int)ceil($limit / $pageSize) + 1; $page++) {
			$answer = $client->getIncomingDocuments(array(
				'companyId' => $settings['companyId'],
				'receivedFrom' => date('c', $sinceTime),
				'pageNumber' => $page,
				'pageSize' => $pageSize,
			));
			if (!$answer['ok']) {
				$out['messages'][] = 'Semantic: ' . $answer['message'];
				$out['failed']++;
				break;
			}
			$list = semantic_incoming_list($answer['data']);
			foreach ($list as $raw) {
				if ($seen >= $limit) {
					break;
				}
				$seen++;
				$doc = semantic_incoming_document($raw);
				if ($doc === null || !semantic_incoming_route($doc, $settings, $db)['allowed']) {
					$out['skipped']++;
					continue;
				}
				$out['listed']++;
				if ($doc['received'] !== null && ($out['newest'] === null || strcmp($doc['received'], $out['newest']) > 0)) {
					$out['newest'] = $doc['received'];
				}
				$result = semantic_incoming_process($client, array('document' => $raw), array('db' => $db, 'store' => $store));
				if ($result['outcome'] === 'already') {
					$out['already']++;
				} elseif ($result['outcome'] === 'busy') {
					$out['skipped']++;
				} elseif ($result['ok']) {
					$out['handled']++;
				} else {
					$out['failed']++;
					$out['messages'][] = 'Dokument ' . ($doc['incomingId'] ?? '?') . ': ' . $result['message'];
				}
			}
			if (count($list) < $pageSize) {
				break;
			}
		}
		return $out;
	}
}

if (!function_exists('semantic_incoming_handle_notification')) {
	/**
	 * The webhook's part: the ids an incoming notification names, handed to semantic_incoming_process(). Only the ids are
	 * taken from the notice; what the document is, and whom it is for, is read from Semantic.
	 *
	 * SD-815 ASSUMPTION: an incoming notice carries incomingDocumentId (semantic_webhook_parse_payload() 'kind' incoming),
	 *                    and its documentId is then the same incoming id. A notice in another shape is answered and left
	 *                    to semantic_incoming_poll().
	 *
	 * @param SemanticClient       $client
	 * @param array<string, mixed> $payload From semantic_webhook_parse_payload().
	 * @param string               $db      The webhook's tenant database.
	 * @param array{store?: SemanticIncomingStore, sprogId?: int, now?: string} $options See semantic_incoming_process().
	 * @return array{ok: bool, outcome: string, message: string, incomingId: string|null}
	 */
	function semantic_incoming_handle_notification(SemanticClient $client, array $payload, $db, array $options = array()) {
		$incomingId = $payload['incomingDocumentId'] ?? null;
		if ($incomingId === null && ($payload['kind'] ?? '') === 'incoming') {
			$incomingId = $payload['documentId'] ?? null;
		}
		return semantic_incoming_process($client, array('incomingId' => $incomingId, 'ublDocumentId' => $payload['ublDocumentId'] ?? null), array('db' => (string)$db) + $options);
	}
}
