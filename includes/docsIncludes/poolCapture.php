<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolCapture.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ SD-722 Created: what the extraction read from a pool document, and what became of it.
//                The snapshot (pool_files.capture_raw and capture_values) keeps the service's raw answer and the values after Saldi's normalisation.
//                At attach time every field that ends up different is written to pool_capture_log as a correction; the snapshot is kept there too.
//                "Rapportér fejl i aflæsning" stores a report in pool_capture_log and e-mails it to support (PHPMailer), retried by the pool's folder sync.
//                The pure functions (snapshot, diff, mail text) have no database access and are unit tested.
// 20261005 CL/SZ SD-727 The row poolCaptureStore() creates gets its content hash (poolContentHashStore()), so the same bilag arriving again is recognised.

require_once __DIR__ . '/poolAmountNormalizer.php';
require_once __DIR__ . '/poolDateNormalizer.php';
include_once __DIR__ . '/poolVendorMatcher.php';

if (!defined('POOL_CAPTURE_MAX_ATTEMPTS')) define('POOL_CAPTURE_MAX_ATTEMPTS', 5);

if (!function_exists('poolCaptureFields')) {
	/**
	 * The fields the snapshot and the correction records cover, in display order.
	 *
	 * @return string[]
	 */
	function poolCaptureFields() {
		return array('date', 'amount', 'invoiceNumber', 'currency', 'kreditor');
	}
}

if (!function_exists('poolCaptureNorm')) {
	/**
	 * One field's value in the form it is compared in: date Y-m-d, amount without sign with two decimals,
	 * invoice number trimmed, currency as an ISO code, kreditor as its account number. '' when empty.
	 *
	 * @param string $field One of poolCaptureFields().
	 * @param mixed  $value
	 * @return string
	 */
	function poolCaptureNorm($field, $value) {
		if ($value === null || !is_scalar($value)) return '';
		$value = trim((string)$value);
		if ($value === '') return '';
		switch ($field) {
			case 'date':
				$date = normalizeDateFormat($value);
				return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : $value;
			case 'amount':
				$amount = normalizePoolAmount($value);
				return $amount === null ? $value : number_format(abs($amount), 2, '.', '');
			case 'invoiceNumber':
				return preg_replace('/\s+/', ' ', $value);
			case 'currency':
				return (string)normalizePoolCurrency($value);
			case 'kreditor':
				// "K1234" as the pool saves it, or the plain number
				return preg_match('/^K?(\d+)$/i', $value, $m) ? ltrim($m[1], '0') : $value;
		}
		return $value;
	}
}

if (!function_exists('poolCaptureRawFields')) {
	/**
	 * The service's own value per field, before Saldi touched it.
	 *
	 * @param array|null $raw The decoded answer of the extraction service ('extracted_data'), or for UBL XML
	 *                        array('source' => 'ubl', 'values' => <extractUblInvoiceData() result>).
	 * @return array<string, string> field => raw value ('' when the service gave none)
	 */
	function poolCaptureRawFields($raw) {
		$out = array_fill_keys(poolCaptureFields(), '');
		if (!is_array($raw)) return $out;
		$text = function ($value) {
			return ($value === null || is_array($value)) ? '' : trim((string)$value);
		};
		if (($raw['source'] ?? '') === 'ubl') {
			$v = is_array($raw['values'] ?? null) ? $raw['values'] : array();
			$keys = array('date' => 'date', 'amount' => 'amount', 'invoiceNumber' => 'invoiceNumber', 'currency' => 'currency');
			$vendor = $text($v['vendor'] ?? null);
			$cvr = $text($v['vendorCvr'] ?? null);
		} else {
			$v = is_array($raw['extracted_data'] ?? null) ? $raw['extracted_data'] : array();
			$keys = array('date' => 'invoice_date', 'amount' => 'total_amount', 'invoiceNumber' => 'invoice_number', 'currency' => 'currency');
			$vendor = $text($v['vendor'] ?? null);
			$cvr = $text($v['vendor_vat_number'] ?? null);
		}
		foreach ($keys as $field => $key) $out[$field] = $text($v[$key] ?? null);
		// The kreditor is not read as such: the service reads the seller's name and CVR, Saldi finds the kreditor
		$out['kreditor'] = trim($vendor . ($cvr !== '' ? " (CVR $cvr)" : ''));
		return $out;
	}
}

if (!function_exists('poolCaptureSnapshot')) {
	/**
	 * What the extraction read, in two layers per field: raw from the service and after Saldi's normalisation.
	 *
	 * @param array      $result      extractInvoiceData()'s result (the values the pool shows and saves).
	 * @param array|null $raw         See poolCaptureRawFields().
	 * @param array|null $vendorMatch poolVendorMatch()'s result, or null.
	 * @return array{fields: array<string, array{raw: string, norm: string}>, extractionId: string, vendorCvr: string, vendorMatch: string}
	 */
	function poolCaptureSnapshot(array $result, $raw, $vendorMatch) {
		$rawFields = poolCaptureRawFields($raw);
		$kreditor = '';
		$match = is_array($vendorMatch) ? (string)($vendorMatch['match'] ?? '') : '';
		if (in_array($match, array('cvr', 'bank', 'name'), true) && !empty($vendorMatch['kontonr'])) {
			$kreditor = poolCaptureNorm('kreditor', $vendorMatch['kontonr']);
		}
		$fields = array(
			'date' => array('raw' => $rawFields['date'], 'norm' => poolCaptureNorm('date', $result['date'] ?? '')),
			'amount' => array('raw' => $rawFields['amount'], 'norm' => poolCaptureNorm('amount', $result['amount'] ?? '')),
			'invoiceNumber' => array('raw' => $rawFields['invoiceNumber'], 'norm' => poolCaptureNorm('invoiceNumber', $result['invoiceNumber'] ?? '')),
			'currency' => array('raw' => $rawFields['currency'], 'norm' => poolCaptureNorm('currency', $result['currency'] ?? '')),
			'kreditor' => array('raw' => $rawFields['kreditor'], 'norm' => $kreditor),
		);
		$extractionId = '';
		if (is_array($raw)) {
			foreach (array('extraction_id', 'request_id', 'id') as $key) {
				if (isset($raw[$key]) && is_scalar($raw[$key]) && trim((string)$raw[$key]) !== '') {
					$extractionId = trim((string)$raw[$key]);
					break;
				}
			}
		}
		$cvr = normalizePoolVendorCvr($result['vendorCvr'] ?? null);
		return array(
			'fields' => $fields,
			'extractionId' => $extractionId,
			'vendorCvr' => $cvr === null ? '' : $cvr,
			'vendorMatch' => $match,
		);
	}
}

if (!function_exists('poolCaptureDisplay')) {
	/**
	 * A normalised value as a Danish user reads it: date dd-mm-yyyy, amount 1.234,56. Other fields as they are.
	 *
	 * @return string
	 */
	function poolCaptureDisplay($field, $norm) {
		$norm = (string)$norm;
		if ($field === 'date' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $norm, $m)) return "$m[3]-$m[2]-$m[1]";
		if ($field === 'amount' && is_numeric($norm)) return number_format((float)$norm, 2, ',', '.');
		return $norm;
	}
}

if (!function_exists('poolCaptureSame')) {
	/**
	 * Whether a normalised captured value and a normalised final value are the same.
	 * An empty currency is the company's own currency, so '' and DKK are the same.
	 *
	 * @return bool
	 */
	function poolCaptureSame($field, $captured, $final) {
		if ($field === 'currency') {
			$captured = $captured === 'DKK' ? '' : $captured;
			$final = $final === 'DKK' ? '' : $final;
		}
		return (string)$captured === (string)$final;
	}
}

if (!function_exists('poolCaptureDiff')) {
	/**
	 * The fields whose saved value differs from what was captured. A field the service missed and the user filled in counts.
	 *
	 * @param array $snapshot poolCaptureSnapshot()'s result.
	 * @param array $final    field => saved value, in any form poolCaptureNorm() reads.
	 * @return array<int, array{field: string, raw: string, norm: string, final: string}>
	 */
	function poolCaptureDiff(array $snapshot, array $final) {
		$out = array();
		foreach (poolCaptureFields() as $field) {
			if (!array_key_exists($field, $final)) continue;
			$captured = (string)($snapshot['fields'][$field]['norm'] ?? '');
			$saved = poolCaptureNorm($field, $final[$field]);
			if (poolCaptureSame($field, $captured, $saved)) continue;
			$out[] = array(
				'field' => $field,
				'raw' => (string)($snapshot['fields'][$field]['raw'] ?? ''),
				'norm' => $captured,
				'final' => $saved,
			);
		}
		return $out;
	}
}

if (!function_exists('poolCaptureReportMailContent')) {
	/**
	 * Subject and body of the e-mail to support for one report.
	 *
	 * @param array $report  company, db, user, time, filename, hash, extractionId, kreditor, fields (as poolCaptureDiff() rows), comment
	 * @param array $labels  field => label, plus 'field', 'raw', 'norm', 'final', 'comment', 'company', 'db', 'user', 'time', 'file', 'hash', 'extractionId'
	 * @return array{subject: string, html: string, text: string}
	 */
	function poolCaptureReportMailContent(array $report, array $labels) {
		$h = function ($value) {
			return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
		};
		$oneLine = function ($value) {
			return trim(preg_replace('/[\r\n\t]+/', ' ', (string)$value));
		};
		$about = $oneLine($report['kreditor'] ?? '') !== '' ? $oneLine($report['kreditor']) : $oneLine($report['filename'] ?? '');
		$subject = 'Aflæsningsfejl: ' . $oneLine($report['company'] ?? '') . ' – ' . $about;
		$facts = array(
			'company' => $report['company'] ?? '',
			'db' => $report['db'] ?? '',
			'user' => $report['user'] ?? '',
			'time' => $report['time'] ?? '',
			'file' => $report['filename'] ?? '',
			'hash' => $report['hash'] ?? '',
			'extractionId' => $report['extractionId'] ?? '',
		);
		$html = "<table cellpadding='4' style='border-collapse:collapse;font-family:sans-serif;font-size:13px'>";
		$text = '';
		foreach ($facts as $key => $value) {
			if ($key === 'extractionId' && (string)$value === '') continue;
			$html .= "<tr><td><b>" . $h($labels[$key] ?? $key) . "</b></td><td>" . $h($value) . "</td></tr>";
			$text .= ($labels[$key] ?? $key) . ': ' . $oneLine($value) . "\n";
		}
		$html .= "</table><br>";
		$html .= "<table cellpadding='4' border='1' style='border-collapse:collapse;font-family:sans-serif;font-size:13px'>";
		$html .= "<tr><th>" . $h($labels['field'] ?? 'Felt') . "</th><th>" . $h($labels['raw'] ?? 'Fra tjenesten') . "</th><th>" . $h($labels['norm'] ?? 'Efter Saldi') . "</th><th>" . $h($labels['final'] ?? 'Korrekt') . "</th></tr>";
		$text .= "\n" . ($labels['field'] ?? 'Felt') . ': ' . ($labels['raw'] ?? 'Fra tjenesten') . ' | ' . ($labels['norm'] ?? 'Efter Saldi') . ' | ' . ($labels['final'] ?? 'Korrekt') . "\n";
		foreach ((array)($report['fields'] ?? array()) as $row) {
			$label = $labels[$row['field']] ?? $row['field'];
			$html .= "<tr><td>" . $h($label) . "</td><td>" . $h($row['raw'] ?? '') . "</td><td>" . $h($row['norm'] ?? '') . "</td><td>" . $h($row['final'] ?? '') . "</td></tr>";
			$text .= $label . ': ' . $oneLine($row['raw'] ?? '') . ' | ' . $oneLine($row['norm'] ?? '') . ' | ' . $oneLine($row['final'] ?? '') . "\n";
		}
		$html .= "</table><br>";
		$comment = (string)($report['comment'] ?? '');
		$html .= "<b>" . $h($labels['comment'] ?? 'Kommentar') . ":</b><br>" . nl2br($h($comment));
		$text .= "\n" . ($labels['comment'] ?? 'Kommentar') . ":\n" . $comment . "\n";
		return array('subject' => $subject, 'html' => $html, 'text' => $text);
	}
}

if (!function_exists('poolCaptureNextAttempt')) {
	/**
	 * When a report that failed is tried again: 2, 4, 8, 16 minutes after the attempt.
	 *
	 * @param int $attempts Attempts made so far, including the one that just failed.
	 * @param int $now      Unix time of the attempt.
	 * @return string Y-m-d H:i:s
	 */
	function poolCaptureNextAttempt($attempts, $now) {
		return date('Y-m-d H:i:s', $now + 60 * (2 ** max(1, min(10, (int)$attempts))));
	}
}

// ---------------------------------------------------------------- Database

if (!function_exists('poolCaptureIsMysql')) {
	function poolCaptureIsMysql() {
		global $db_type;
		return $db_type == 'mysql' || $db_type == 'mysqli';
	}
}

if (!function_exists('poolCaptureReady')) {
	/**
	 * Whether the snapshot columns and pool_capture_log exist. includes/betweenUpdates.php adds them at login.
	 *
	 * @return bool
	 */
	function poolCaptureReady() {
		static $ready = null;
		if ($ready !== null) return $ready;
		$schema = poolCaptureIsMysql() ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
		$ready = (bool)db_fetch_array(db_select("SELECT table_name FROM information_schema.tables WHERE table_name = 'pool_capture_log'$schema", __FILE__ . " linje " . __LINE__))
			&& (bool)db_fetch_array(db_select("SELECT column_name FROM information_schema.columns WHERE table_name = 'pool_files' AND column_name = 'captured'$schema", __FILE__ . " linje " . __LINE__));
		return $ready;
	}
}

if (!function_exists('poolCaptureLogLine')) {
	/** One line in the pool log, temp/<db>/docPool.log (the same file as docPool.php's docPoolLog()). */
	function poolCaptureLogLine($message) {
		global $db;
		$dir = __DIR__ . '/../../temp/' . preg_replace('/[^A-Za-z0-9_]/', '', (string)($db ?? 'unknown_db'));
		if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
			error_log("poolCapture: $message");
			return;
		}
		$message = trim(preg_replace('/\s+/', ' ', (string)$message));
		@file_put_contents("$dir/docPool.log", date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND | LOCK_EX);
	}
}

if (!function_exists('poolCaptureSql')) {
	/** A value as an SQL literal: NULL, a number or an escaped string. */
	function poolCaptureSql($value) {
		if ($value === null) return 'NULL';
		if (is_int($value)) return (string)$value;
		return "'" . db_escape_string((string)$value) . "'";
	}
}

if (!function_exists('poolCaptureStore')) {
	/**
	 * Stores the snapshot on the pool row, once: a later extraction of the same document does not replace it.
	 * A document uploaded a moment ago may not have its row yet; it gets the same minimal row the folder sync would give it.
	 *
	 * @param string     $filename Pool file name.
	 * @param string     $filePath Full path of the file, for the minimal row's date.
	 * @param array      $snapshot poolCaptureSnapshot()'s result.
	 * @param array|null $raw      The service's answer, stored as it came.
	 * @return bool Whether a snapshot was stored.
	 */
	function poolCaptureStore($filename, $filePath, array $snapshot, $raw) {
		if (!poolCaptureReady() || $filename === '' || basename($filename) !== $filename) return false;
		$nameSql = db_escape_string($filename);
		if (!db_fetch_array(db_select("SELECT id FROM pool_files WHERE filename = '$nameSql'", __FILE__ . " linje " . __LINE__))) {
			if (!is_file($filePath)) return false;
			$conflict = poolCaptureIsMysql() ? ' ON DUPLICATE KEY UPDATE id = id' : ' ON CONFLICT (filename) DO NOTHING';
			db_modify("INSERT INTO pool_files (filename, subject, file_date) VALUES ('$nameSql', '" . db_escape_string(pathinfo($filename, PATHINFO_FILENAME)) . "', '"
				. date('Y-m-d H:i:s', filemtime($filePath)) . "')$conflict", __FILE__ . " linje " . __LINE__);
		}
		// The folder sync never inserts this row, so the content hash it would have set is set here (MB-42, SD-727)
		include_once(__DIR__ . '/poolContentHash.php');
		poolContentHashStore($filename, $filePath);
		$rawJson = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		// The service's answer is small; a cap keeps a misbehaving answer out of the row
		if ($rawJson === false || strlen($rawJson) > 65535) $rawJson = null;
		$valuesJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		db_modify("UPDATE pool_files SET capture_raw = " . poolCaptureSql($rawJson) . ", capture_values = " . poolCaptureSql($valuesJson)
			. ", captured = '" . date('Y-m-d H:i:s') . "' WHERE filename = '$nameSql' AND capture_values IS NULL", __FILE__ . " linje " . __LINE__);
		return true;
	}
}

if (!function_exists('poolCaptureStoreExtraction')) {
	/**
	 * Stores the snapshot of an extraction that ran where no kreditor match was made (the upload in includes/documents.php).
	 * The match is made the way extractInvoiceHandler.php makes it. Never throws: the snapshot must not fail an upload.
	 *
	 * @param string     $filename Pool file name.
	 * @param string     $filePath Full path of the file.
	 * @param array|null $result   extractInvoiceData()'s result.
	 * @return void
	 */
	function poolCaptureStoreExtraction($filename, $filePath, $result) {
		if (!is_array($result)) return;
		try {
			$vendorMatch = null;
			if (function_exists('poolVendorMatch') && function_exists('poolVendorLoadIndex')) {
				$ownCvr = poolVendorLoadOwnCvr();
				$customerCvr = normalizePoolVendorCvr($result['customerCvr'] ?? null);
				if ($customerCvr !== null && $ownCvr === null) $ownCvr = $customerCvr;
				$vendorCvr = normalizePoolVendorCvr($result['vendorCvr'] ?? null);
				$vendorMatch = poolVendorMatch(array(
					'name' => $result['vendor'] ?? null,
					'cvr' => ($vendorCvr !== null && $vendorCvr === $customerCvr) ? null : ($result['vendorCvr'] ?? null),
					'iban' => $result['vendorIban'] ?? null,
					'bank_reg' => $result['vendorBankReg'] ?? null,
					'bank_konto' => $result['vendorBankKonto'] ?? null,
				), poolVendorLoadIndex(), array('ownCvr' => $ownCvr, 'nameScan' => 'full'));
			}
			$raw = $GLOBALS['invoiceExtractionLastRaw'] ?? null;
			poolCaptureStore($filename, $filePath, poolCaptureSnapshot($result, $raw, $vendorMatch), $raw);
		} catch (Throwable $e) {
			error_log("poolCapture snapshot for $filename failed: " . $e->getMessage());
		}
	}
}

if (!function_exists('poolCaptureForFile')) {
	/**
	 * The snapshot of a document in the pool.
	 *
	 * @return array|null poolCaptureSnapshot()'s shape, or null when the document has none.
	 */
	function poolCaptureForFile($filename) {
		if (!poolCaptureReady()) return null;
		$r = db_fetch_array(db_select("SELECT capture_values FROM pool_files WHERE filename = '" . db_escape_string($filename) . "'", __FILE__ . " linje " . __LINE__));
		$snapshot = $r ? json_decode((string)$r['capture_values'], true) : null;
		return is_array($snapshot) && isset($snapshot['fields']) ? $snapshot : null;
	}
}

if (!function_exists('poolCaptureFieldsShown')) {
	/**
	 * The fields of a pool document whose value is still what the extraction read, for the "Aflæst" highlight.
	 * Without a snapshot (a document read before snapshots were kept) a value counts as read until someone corrected the document.
	 *
	 * @param array      $current  field => the pool row's value now (kreditor as account number).
	 * @param array|null $snapshot poolCaptureSnapshot()'s result, or null.
	 * @param bool       $manual   The pool row was corrected or confirmed by a user (pool_files.manually_edited).
	 * @return string[]
	 */
	function poolCaptureFieldsShown(array $current, $snapshot, $manual) {
		$out = array();
		foreach (poolCaptureFields() as $field) {
			$now = poolCaptureNorm($field, $current[$field] ?? '');
			if ($now === '') continue;
			if (is_array($snapshot)) {
				if (poolCaptureSame($field, (string)($snapshot['fields'][$field]['norm'] ?? ''), $now)) $out[] = $field;
			} elseif (!$manual) {
				$out[] = $field;
			}
		}
		return $out;
	}
}

if (!function_exists('poolCaptureClientState')) {
	/**
	 * What javascript/poolCapture.js needs about the pool document that is open.
	 *
	 * @return array{fields: string[], vendorMatch: string, hasSnapshot: bool}
	 */
	function poolCaptureClientState($filename) {
		$state = array('fields' => array(), 'vendorMatch' => '', 'hasSnapshot' => false);
		if ($filename === '' || !poolCaptureReady()) return $state;
		$vendorCols = function_exists('poolVendorColumnsExist') && poolVendorColumnsExist() ? ', vendor_konto_id, vendor_match' : '';
		$r = db_fetch_array(db_select("SELECT file_date, amount, invoice_number, currency, manually_edited, capture_values$vendorCols FROM pool_files WHERE filename = '" . db_escape_string($filename) . "'", __FILE__ . " linje " . __LINE__));
		if (!$r) return $state;
		$snapshot = json_decode((string)$r['capture_values'], true);
		if (!is_array($snapshot) || !isset($snapshot['fields'])) $snapshot = null;
		$match = (string)($r['vendor_match'] ?? '');
		$kreditor = '';
		if (in_array($match, array('cvr', 'bank', 'name'), true) && (int)($r['vendor_konto_id'] ?? 0)) {
			$k = db_fetch_array(db_select("SELECT kontonr FROM adresser WHERE id = '" . (int)$r['vendor_konto_id'] . "' AND art = 'K'", __FILE__ . " linje " . __LINE__));
			$kreditor = $k ? (string)$k['kontonr'] : '';
		}
		$state['fields'] = poolCaptureFieldsShown(array(
			'date' => $r['file_date'],
			'amount' => $r['amount'],
			'invoiceNumber' => $r['invoice_number'],
			'currency' => $r['currency'],
			'kreditor' => $kreditor,
		), $snapshot, in_array($r['manually_edited'], array('t', true, 1, '1'), true));
		$state['vendorMatch'] = $kreditor !== '' ? $match : '';
		$state['hasSnapshot'] = $snapshot !== null;
		return $state;
	}
}

if (!function_exists('poolCaptureLogInsert')) {
	/**
	 * One row in pool_capture_log.
	 *
	 * @param array $row Column => value; kind is required.
	 * @return void
	 */
	function poolCaptureLogInsert(array $row) {
		$allowed = array('kind', 'filename', 'content_hash', 'source_id', 'field', 'raw_value', 'norm_value', 'final_value', 'kreditor_cvr', 'data', 'comment', 'user_id', 'user_name');
		$cols = array();
		$vals = array();
		foreach ($allowed as $col) {
			if (!array_key_exists($col, $row)) continue;
			$cols[] = $col;
			$vals[] = poolCaptureSql($row[$col]);
		}
		$cols[] = 'created';
		$vals[] = "'" . date('Y-m-d H:i:s') . "'";
		db_modify("INSERT INTO pool_capture_log (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")", __FILE__ . " linje " . __LINE__);
	}
}

if (!function_exists('poolCaptureFinalValues')) {
	/**
	 * What was saved for a document attached to a journal line: date, invoice number, currency and kreditor of that line,
	 * and the amount of all the bilag's lines the document is attached to ("Fordeling" spreads one invoice over several).
	 *
	 * @param int    $sourceId kassekladde.id of the line the document was attached to.
	 * @param string $filename Document file name, as in documents.filename.
	 * @return array|null field => value, or null when the line is gone.
	 */
	function poolCaptureFinalValues($sourceId, $filename) {
		$sourceId = (int)$sourceId;
		$line = db_fetch_array(db_select("SELECT kladde_id, bilag, transdate, amount, faktura, valuta, d_type, debet, k_type, kredit FROM kassekladde WHERE id = '$sourceId'", __FILE__ . " linje " . __LINE__));
		if (!$line) return null;
		$ids = array($sourceId => true);
		$q = db_select("SELECT source_id FROM documents WHERE source = 'kassekladde' AND filename = '" . db_escape_string($filename) . "'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) $ids[(int)$r['source_id']] = true;
		$amount = 0.0;
		$q = db_select("SELECT amount FROM kassekladde WHERE id IN (" . implode(',', array_keys($ids)) . ") AND kladde_id = '" . (int)$line['kladde_id'] . "' AND bilag = '" . (int)$line['bilag'] . "'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) $amount += abs((float)$r['amount']);
		$currency = '';
		if ((int)$line['valuta']) {
			$v = db_fetch_array(db_select("SELECT box1 FROM grupper WHERE art = 'VK' AND kodenr = '" . (int)$line['valuta'] . "'", __FILE__ . " linje " . __LINE__));
			$currency = $v ? (string)$v['box1'] : '';
		}
		$kreditor = '';
		if (strtoupper((string)$line['k_type']) === 'K' && (int)$line['kredit']) $kreditor = (string)(int)$line['kredit'];
		elseif (strtoupper((string)$line['d_type']) === 'K' && (int)$line['debet']) $kreditor = (string)(int)$line['debet'];
		return array(
			'date' => (string)$line['transdate'],
			'amount' => number_format($amount, 2, '.', ''),
			'invoiceNumber' => (string)$line['faktura'],
			'currency' => $currency,
			'kreditor' => $kreditor,
		);
	}
}

if (!function_exists('poolCaptureKreditorCvr')) {
	/** The CVR of kreditor $kontonr, normalised, or the CVR the document showed when the kreditor has none. */
	function poolCaptureKreditorCvr($kontonr, array $snapshot) {
		if ($kontonr !== '') {
			$r = db_fetch_array(db_select("SELECT cvrnr FROM adresser WHERE art = 'K' AND kontonr = '" . db_escape_string($kontonr) . "' ORDER BY id LIMIT 1", __FILE__ . " linje " . __LINE__));
			$cvr = $r ? normalizePoolVendorCvr($r['cvrnr']) : null;
			if ($cvr !== null) return $cvr;
		}
		return (string)($snapshot['vendorCvr'] ?? '');
	}
}

if (!function_exists('poolCaptureRecordAttach')) {
	/**
	 * At attach time, before the pool row is deleted: one correction record per field that was saved differently from what was
	 * captured, and the snapshot itself, so the document can still be reported from the journal line.
	 *
	 * @param string      $filename Pool file name.
	 * @param int         $sourceId kassekladde.id the document was attached to.
	 * @param string      $filePath Where the file is now, for its content hash when the pool row has none.
	 * @param int|null    $userId
	 * @param string      $userName
	 * @return int Correction records written.
	 */
	function poolCaptureRecordAttach($filename, $sourceId, $filePath, $userId, $userName) {
		if (!poolCaptureReady() || !(int)$sourceId) return 0;
		$pool = db_fetch_array(db_select("SELECT capture_values, capture_raw, content_sha256 FROM pool_files WHERE filename = '" . db_escape_string($filename) . "'", __FILE__ . " linje " . __LINE__));
		$snapshot = $pool ? json_decode((string)$pool['capture_values'], true) : null;
		if (!is_array($snapshot) || !isset($snapshot['fields'])) return 0;
		$final = poolCaptureFinalValues($sourceId, $filename);
		if ($final === null) return 0;
		$hash = trim((string)($pool['content_sha256'] ?? ''));
		if ($hash === '' && is_file($filePath)) $hash = (string)hash_file('sha256', $filePath);
		$cvr = poolCaptureKreditorCvr($final['kreditor'], $snapshot);
		$common = array(
			'filename' => $filename,
			'content_hash' => $hash,
			'source_id' => (int)$sourceId,
			'kreditor_cvr' => $cvr,
			'user_id' => $userId === null ? null : (int)$userId,
			'user_name' => mb_substr((string)$userName, 0, 80),
		);
		poolCaptureLogInsert($common + array(
			'kind' => 'snapshot',
			'data' => json_encode(array('snapshot' => $snapshot, 'raw' => json_decode((string)$pool['capture_raw'], true)), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
		));
		$diff = poolCaptureDiff($snapshot, $final);
		foreach ($diff as $row) {
			poolCaptureLogInsert($common + array(
				'kind' => 'correction',
				'field' => $row['field'],
				'raw_value' => $row['raw'],
				'norm_value' => $row['norm'],
				'final_value' => $row['final'],
			));
		}
		return count($diff);
	}
}

if (!function_exists('poolCaptureAttachedSnapshot')) {
	/**
	 * The snapshot kept for a document attached to a journal line: the line's own, else the latest for that file name.
	 *
	 * @return array|null array('snapshot' => ..., 'content_hash' => ..., 'source_id' => ...) or null.
	 */
	function poolCaptureAttachedSnapshot($filename, $sourceId) {
		if (!poolCaptureReady()) return null;
		$nameSql = db_escape_string($filename);
		$r = db_fetch_array(db_select("SELECT data, content_hash, source_id FROM pool_capture_log WHERE kind = 'snapshot' AND filename = '$nameSql' AND source_id = '" . (int)$sourceId . "' ORDER BY id DESC LIMIT 1", __FILE__ . " linje " . __LINE__));
		if (!$r) $r = db_fetch_array(db_select("SELECT data, content_hash, source_id FROM pool_capture_log WHERE kind = 'snapshot' AND filename = '$nameSql' ORDER BY id DESC LIMIT 1", __FILE__ . " linje " . __LINE__));
		$data = $r ? json_decode((string)$r['data'], true) : null;
		if (!is_array($data) || !is_array($data['snapshot'] ?? null)) return null;
		return array('snapshot' => $data['snapshot'], 'content_hash' => (string)$r['content_hash'], 'source_id' => (int)$r['source_id']);
	}
}

if (!function_exists('poolCaptureDocPath')) {
	/**
	 * Where a document's file is: in the pool folder, or where it was attached to journal line $sourceId.
	 * The folder is found the way includes/docsIncludes/insertDoc.php finds it.
	 *
	 * @return string Full path, or '' when the file is not found.
	 */
	function poolCaptureDocPath($filename, $sourceId = 0) {
		global $db;
		$filename = basename((string)$filename);
		$company = preg_replace('/[^A-Za-z0-9_]/', '', (string)$db);
		if ($filename === '' || $company === '') return '';
		$root = dirname(__DIR__, 2);
		foreach (array('owncloud', 'bilag', 'documents') as $folder) {
			if (!is_dir("$root/$folder")) continue;
			$base = "$root/$folder/$company";
			if (is_file("$base/pulje/$filename")) return "$base/pulje/$filename";
			$where = "source = 'kassekladde' AND filename = '" . db_escape_string($filename) . "'";
			if ((int)$sourceId) $where .= " AND source_id = '" . (int)$sourceId . "'";
			$r = db_fetch_array(db_select("SELECT filepath FROM documents WHERE $where ORDER BY id DESC LIMIT 1", __FILE__ . " linje " . __LINE__));
			if ($r) {
				$path = $base . '/' . trim((string)$r['filepath'], '/') . '/' . $filename;
				if (strpos($path, '..') === false && is_file($path)) return $path;
			}
			return '';
		}
		return '';
	}
}

if (!function_exists('poolCaptureReject')) {
	/**
	 * A suggestion the user rejected with "×", kept with the correction records.
	 *
	 * @param string $field  'debet' (contra account) or 'kreditor'.
	 * @param string $value  The rejected value.
	 * @param string $reason The reason the suggestion was shown with.
	 */
	function poolCaptureReject($filename, $field, $value, $reason, $userId, $userName) {
		if (!poolCaptureReady()) return;
		poolCaptureLogInsert(array(
			'kind' => 'rejection',
			'filename' => mb_substr((string)$filename, 0, 255),
			'field' => $field,
			'raw_value' => mb_substr((string)$value, 0, 100),
			'comment' => mb_substr((string)$reason, 0, 500),
			'user_id' => $userId === null ? null : (int)$userId,
			'user_name' => mb_substr((string)$userName, 0, 80),
		));
	}
}

if (!function_exists('poolCaptureReported')) {
	/** Whether this user has reported this document already (by content, so a renamed or attached copy counts). */
	function poolCaptureReported($filename, $hash, $userId, $userName) {
		if (!poolCaptureReady()) return false;
		$who = $userId !== null && (int)$userId > 0 ? "user_id = '" . (int)$userId . "'" : "user_name = '" . db_escape_string((string)$userName) . "'";
		$what = $hash !== '' ? "content_hash = '" . db_escape_string($hash) . "'" : "filename = '" . db_escape_string($filename) . "'";
		return (bool)db_fetch_array(db_select("SELECT id FROM pool_capture_log WHERE kind = 'report' AND $what AND $who LIMIT 1", __FILE__ . " linje " . __LINE__));
	}
}

if (!function_exists('poolCaptureReportSettings')) {
	/**
	 * Whether the report button is on (settings pool/capture_report = 'on'), and where reports go (pool/capture_report_email).
	 * The button stays off until the customer terms for sending documents to support are approved.
	 *
	 * @return array{enabled: bool, email: string}
	 */
	function poolCaptureReportSettings() {
		$enabled = false;
		$email = 'support@danosoft.dk';
		$q = db_select("SELECT var_name, var_value FROM settings WHERE var_grp = 'pool' AND var_name IN ('capture_report', 'capture_report_email')", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($r['var_name'] === 'capture_report') $enabled = trim((string)$r['var_value']) === 'on';
			elseif (filter_var(trim((string)$r['var_value']), FILTER_VALIDATE_EMAIL)) $email = trim((string)$r['var_value']);
		}
		return array('enabled' => $enabled, 'email' => $email);
	}
}

if (!function_exists('poolCaptureReportCreate')) {
	/**
	 * Stores a report. It is e-mailed by poolCaptureReportDeliver().
	 *
	 * @param array $report filename, hash, sourceId, extractionId, kreditor, kreditorCvr, fields, comment, userId, userName, docPath
	 * @return void
	 */
	function poolCaptureReportCreate(array $report) {
		poolCaptureLogInsert(array(
			'kind' => 'report',
			'filename' => mb_substr((string)$report['filename'], 0, 255),
			'content_hash' => (string)$report['hash'],
			'source_id' => $report['sourceId'] ? (int)$report['sourceId'] : null,
			'kreditor_cvr' => (string)($report['kreditorCvr'] ?? ''),
			'data' => json_encode(array(
				'fields' => $report['fields'],
				'extractionId' => (string)($report['extractionId'] ?? ''),
				'kreditor' => (string)($report['kreditor'] ?? ''),
				'docPath' => (string)($report['docPath'] ?? ''),
			), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
			'comment' => mb_substr((string)$report['comment'], 0, 4000),
			'user_id' => $report['userId'] === null ? null : (int)$report['userId'],
			'user_name' => mb_substr((string)$report['userName'], 0, 80),
		));
	}
}

if (!function_exists('poolCaptureMailLabels')) {
	function poolCaptureMailLabels($sprog_id) {
		return array(
			'date' => findtekst('438|Dato', $sprog_id),
			'amount' => findtekst('934|Beløb', $sprog_id),
			'invoiceNumber' => findtekst('828|Fakturanr.', $sprog_id),
			'currency' => findtekst('776|Valuta', $sprog_id),
			'kreditor' => findtekst('1169|Kreditor', $sprog_id),
			'field' => findtekst('543|Felt', $sprog_id),
			'raw' => findtekst('5377|Fra aflæsningen', $sprog_id),
			'norm' => findtekst('5378|Efter Saldi', $sprog_id),
			'final' => findtekst('5379|Korrekt værdi', $sprog_id),
			'comment' => findtekst('5380|Kommentar', $sprog_id),
			'company' => findtekst('5381|Firma', $sprog_id),
			'db' => findtekst('5382|Database', $sprog_id),
			'user' => findtekst('990|Bruger', $sprog_id),
			'time' => findtekst('5383|Tidspunkt', $sprog_id),
			'file' => findtekst('3284|Fil', $sprog_id),
			'hash' => findtekst('5384|Indholds-hash', $sprog_id),
			'extractionId' => findtekst('5385|Aflæsnings-id', $sprog_id),
		);
	}
}

if (!function_exists('poolCaptureClientTexts')) {
	/** The texts javascript/poolCapture.js shows. */
	function poolCaptureClientTexts($sprog_id) {
		return array(
			'read' => findtekst('5371|Aflæst', $sprog_id),
			'suggested' => findtekst('5255|Forslag', $sprog_id),
			'use' => findtekst('5242|Brug forslag', $sprog_id),
			'reject' => findtekst('5391|Afvis forslaget', $sprog_id),
			'viaCvr' => findtekst('5374|Fundet via CVR-nr.', $sprog_id),
			'viaBank' => findtekst('5375|Fundet via bankkonto', $sprog_id),
			'viaName' => findtekst('5376|Fundet via navn', $sprog_id),
			'report' => findtekst('5386|Rapportér fejl i aflæsning', $sprog_id),
			'notice' => findtekst('5387|Bilaget og de viste oplysninger sendes til Saldi support, så aflæsningen kan forbedres.', $sprog_id),
			'send' => findtekst('2310|Send', $sprog_id),
			'cancel' => findtekst('5|Annullér', $sprog_id),
			'thanks' => findtekst('5388|Tak — fejlen er rapporteret', $sprog_id),
			'already' => findtekst('5389|Du har allerede rapporteret dette bilag', $sprog_id),
			'failed' => findtekst('5393|Fejlen kunne ikke rapporteres. Prøv igen.', $sprog_id),
			'noCapture' => findtekst('5394|Ingen aflæste værdier er gemt for dette bilag', $sprog_id),
			'field' => findtekst('543|Felt', $sprog_id),
			'now' => findtekst('5392|Nu', $sprog_id),
			'comment' => findtekst('5380|Kommentar', $sprog_id),
			'labels' => array(
				'date' => findtekst('438|Dato', $sprog_id),
				'amount' => findtekst('934|Beløb', $sprog_id),
				'invoiceNumber' => findtekst('828|Fakturanr.', $sprog_id),
				'currency' => findtekst('776|Valuta', $sprog_id),
				'kreditor' => findtekst('1169|Kreditor', $sprog_id),
			),
		);
	}
}

if (!function_exists('poolCaptureMailSend')) {
	/**
	 * Sends one report through PHPMailer, the way includes/formFuncIncludes/sendMail.php does: the company's own SMTP
	 * settings (adresser art 'S', felt_1..4), else PHP's mail(). A test can replace the transport through
	 * $GLOBALS['poolCaptureMailTransport'] (callable taking the message array, returning true or an error text).
	 *
	 * @param array $message to, from, fromName, replyTo, subject, html, text, attachment (path), attachmentName
	 * @return true|string true when the mail was accepted, otherwise the error.
	 */
	function poolCaptureMailSend(array $message) {
		if (isset($GLOBALS['poolCaptureMailTransport']) && is_callable($GLOBALS['poolCaptureMailTransport'])) {
			return call_user_func($GLOBALS['poolCaptureMailTransport'], $message);
		}
		if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
			// The installs keep PHPMailer next to the program folder (sendMail.php: "../../vendor/autoload.php")
			foreach (array(dirname(__DIR__, 3) . '/vendor/autoload.php', dirname(__DIR__, 2) . '/vendor/autoload.php') as $autoload) {
				if (is_file($autoload)) {
					require_once $autoload;
					break;
				}
			}
		}
		if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) return 'PHPMailer is not installed';
		$smtp = db_fetch_array(db_select("SELECT felt_1, felt_2, felt_3, felt_4 FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1", __FILE__ . " linje " . __LINE__));
		try {
			$mail = new PHPMailer\PHPMailer\PHPMailer(true);
			$mail->CharSet = 'UTF-8';
			$host = trim((string)($smtp['felt_1'] ?? ''));
			if ($host !== '' && $host !== 'localhost') {
				$mail->isSMTP();
				$mail->Host = $host;
				$mail->Timeout = 10;
				if (trim((string)($smtp['felt_2'] ?? '')) !== '') {
					$mail->SMTPAuth = true;
					$mail->Username = $smtp['felt_2'];
					$mail->Password = $smtp['felt_3'];
					if (trim((string)($smtp['felt_4'] ?? '')) !== '') $mail->SMTPSecure = $smtp['felt_4'];
				}
				$mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
			} else {
				$mail->isMail();
			}
			$mail->setFrom($message['from'], $message['fromName'], false);
			$mail->addAddress($message['to']);
			if (!empty($message['replyTo'])) $mail->addReplyTo($message['replyTo']);
			$mail->Subject = $message['subject'];
			$mail->isHTML(true);
			$mail->Body = $message['html'];
			$mail->AltBody = $message['text'];
			if (!empty($message['attachment']) && is_file($message['attachment'])) {
				$mail->addAttachment($message['attachment'], $message['attachmentName'] ?: basename($message['attachment']));
			}
			$mail->send();
			return true;
		} catch (Throwable $e) {
			return $e->getMessage() !== '' ? $e->getMessage() : 'send failed';
		}
	}
}

if (!function_exists('poolCaptureReportDeliver')) {
	/**
	 * E-mails the reports that are due, each at most once: a report is claimed before it is sent, so two requests running
	 * at the same time cannot both send it. A failed report is tried again later; after POOL_CAPTURE_MAX_ATTEMPTS it is written
	 * to the pool log and left.
	 *
	 * @param int      $limit    Reports per call.
	 * @param int|null $onlyId   Only this report (the send right after "Send").
	 * @return array{sent: int, failed: int}
	 */
	function poolCaptureReportDeliver($limit = 5, $onlyId = null) {
		global $db, $sprog_id;
		$result = array('sent' => 0, 'failed' => 0);
		if (!poolCaptureReady()) return $result;
		$now = time();
		$nowSql = date('Y-m-d H:i:s', $now);
		$staleSql = date('Y-m-d H:i:s', $now - 600);
		$due = "kind = 'report' AND sent IS NULL AND attempts < " . POOL_CAPTURE_MAX_ATTEMPTS
			. " AND (next_attempt IS NULL OR next_attempt <= '$nowSql') AND (claimed IS NULL OR claimed < '$staleSql')";
		if ($onlyId !== null) $due .= " AND id = '" . (int)$onlyId . "'";
		$ids = array();
		$q = db_select("SELECT id FROM pool_capture_log WHERE $due ORDER BY id LIMIT " . max(1, (int)$limit), __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) $ids[] = (int)$r['id'];
		if (!$ids) return $result;

		$settings = poolCaptureReportSettings();
		$company = db_fetch_array(db_select("SELECT firmanavn, email FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1", __FILE__ . " linje " . __LINE__));
		$companyName = trim((string)($company['firmanavn'] ?? '')) !== '' ? trim((string)$company['firmanavn']) : (string)$db;
		$companyMail = filter_var(trim((string)($company['email'] ?? '')), FILTER_VALIDATE_EMAIL) ? trim((string)$company['email']) : '';
		$labels = poolCaptureMailLabels($sprog_id ?? 1);

		foreach ($ids as $id) {
			$token = bin2hex(random_bytes(8));
			db_modify("UPDATE pool_capture_log SET claim = '$token', claimed = '$nowSql' WHERE id = '$id' AND $due", __FILE__ . " linje " . __LINE__);
			$row = db_fetch_array(db_select("SELECT * FROM pool_capture_log WHERE id = '$id'", __FILE__ . " linje " . __LINE__));
			if (!$row || $row['claim'] !== $token) continue;
			$data = json_decode((string)$row['data'], true);
			if (!is_array($data)) $data = array();
			$replyTo = '';
			if ((int)$row['user_id'] > 0) {
				$u = db_fetch_array(db_select("SELECT email FROM brugere WHERE id = '" . (int)$row['user_id'] . "'", __FILE__ . " linje " . __LINE__));
				if ($u && filter_var(trim((string)$u['email']), FILTER_VALIDATE_EMAIL)) $replyTo = trim((string)$u['email']);
			}
			$content = poolCaptureReportMailContent(array(
				'company' => $companyName,
				'db' => $db,
				'user' => $row['user_name'],
				'time' => $row['created'],
				'filename' => $row['filename'],
				'hash' => $row['content_hash'],
				'extractionId' => $data['extractionId'] ?? '',
				'kreditor' => $data['kreditor'] ?? '',
				'fields' => $data['fields'] ?? array(),
				'comment' => $row['comment'],
			), $labels);
			// The document may have been attached (moved) since it was reported
			$docPath = (string)($data['docPath'] ?? '');
			if ($docPath === '' || !is_file($docPath)) $docPath = poolCaptureDocPath($row['filename'], (int)$row['source_id']);
			$host = isset($_SERVER['SERVER_NAME']) ? preg_replace('/[^A-Za-z0-9.-]/', '', $_SERVER['SERVER_NAME']) : '';
			$sent = poolCaptureMailSend(array(
				'to' => $settings['email'],
				'from' => $companyMail !== '' ? $companyMail : 'noreply@' . ($host !== '' && strpos($host, '.') !== false ? $host : 'saldi.dk'),
				'fromName' => $companyName,
				'replyTo' => $replyTo,
				'subject' => $content['subject'],
				'html' => $content['html'],
				'text' => $content['text'],
				'attachment' => $docPath,
				'attachmentName' => $row['filename'],
			));
			$attempts = (int)$row['attempts'] + 1;
			if ($sent === true) {
				db_modify("UPDATE pool_capture_log SET sent = '" . date('Y-m-d H:i:s') . "', attempts = '$attempts', claim = NULL, claimed = NULL, last_error = NULL WHERE id = '$id' AND claim = '$token'", __FILE__ . " linje " . __LINE__);
				$result['sent']++;
			} else {
				db_modify("UPDATE pool_capture_log SET attempts = '$attempts', next_attempt = '" . poolCaptureNextAttempt($attempts, time()) . "', claim = NULL, claimed = NULL, last_error = "
					. poolCaptureSql(mb_substr((string)$sent, 0, 500)) . " WHERE id = '$id' AND claim = '$token'", __FILE__ . " linje " . __LINE__);
				$result['failed']++;
				if ($attempts >= POOL_CAPTURE_MAX_ATTEMPTS) {
					poolCaptureLogLine("Aflæsningsfejl-rapport $id ({$row['filename']}) kunne ikke sendes til {$settings['email']} efter $attempts forsøg: $sent");
				}
			}
		}
		return $result;
	}
}
