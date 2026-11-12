<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/einvoiceStatusView.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261112 CL/MMR SD-815 Created. The e-invoice status of an order as the order page, the order list and the status page show it, read from einvoice_sendlog.
//                 Only for a regnskab whose setting provider/einvoice is 'semantic'; nothing here runs a query for any other regnskab except the one settings lookup.
//                 The order list reads the status of all its visible orders in one query (einvoice_status_for_orders()).
//
/*
 * The texts are findtekst() keys under einvoice.* (importfiler/tekster_keyed.tsv). Users never see the provider's
 * name: einvoice_ui_user_text() replaces it in messages that come from the send code or the provider's answers.
 *
 * The receiver's answer (a Peppol ApplicationResponse) is stored by the incoming side of SD-815 in columns that may
 * not exist yet. They are read only when information_schema lists them (einvoice_status_receiver_columns()).
 */

if (!function_exists('einvoice_findtekst')) {
	/**
	 * findtekst() when it is loaded (always on a page), else the fallback after the pipe (unit tests).
	 * Named so the guardrails tekster check sees the key and its fallback.
	 *
	 * @param string $textId  'einvoice.key|Dansk fallback'.
	 * @param int    $sprogId
	 * @return string
	 */
	function einvoice_findtekst($textId, $sprogId) {
		if (function_exists('findtekst') && function_exists('db_select')) {
			return (string)findtekst($textId, (int)$sprogId);
		}
		$parts = explode('|', (string)$textId, 2);
		return isset($parts[1]) ? $parts[1] : $parts[0];
	}
}

if (!function_exists('einvoice_ui_semantic_enabled')) {
	/**
	 * Whether this regnskab sends e-invoices through Semantic (settings provider/einvoice = 'semantic').
	 * Read once per request.
	 *
	 * @param bool $reset For tests.
	 * @return bool
	 */
	function einvoice_ui_semantic_enabled($reset = false) {
		static $enabled = null;
		if ($reset) {
			$enabled = null;
		}
		if ($enabled === null) {
			$value = function_exists('get_settings_value') ? get_settings_value('provider', 'einvoice', 'easyubl') : 'easyubl';
			$enabled = is_string($value) && strtolower(trim($value)) === 'semantic';
		}
		return $enabled;
	}
}

if (!function_exists('einvoice_ui_semantic_registered')) {
	/**
	 * Whether the regnskab is already registered for e-invoicing (settings companyId/semantic, written by the onboarding).
	 *
	 * @return bool
	 */
	function einvoice_ui_semantic_registered() {
		return function_exists('get_settings_value') && trim((string)get_settings_value('companyId', 'semantic', '')) !== '';
	}
}

if (!function_exists('einvoice_js_string')) {
	/**
	 * A text as a JavaScript string literal that works in any page charset: the text is made UTF-8 and every
	 * non-ASCII character is written as \uXXXX.
	 *
	 * @param string $text
	 * @return string
	 */
	function einvoice_js_string($text) {
		$text = (string)$text;
		if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
			$text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
		}
		$json = json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
		return ($json === false) ? '""' : $json;
	}
}

if (!function_exists('einvoice_send_open_script')) {
	/**
	 * The script the order page and bogfor.php print to send an invoice or credit note through Semantic: it opens the
	 * status page (debitor/peppol.php), which sends and asks itself before sending a document again. Before the
	 * regnskab's first e-invoice, the user is told that it will be registered for e-invoicing.
	 *
	 * @param int    $orderId
	 * @param string $type      'invoice' or 'creditnote'.
	 * @param bool   $askFirst  True before the first e-invoice (einvoice_ui_semantic_registered() is false).
	 * @param int    $sprogId
	 * @return string
	 */
	function einvoice_send_open_script($orderId, $type, $askFirst, $sprogId) {
		$url = 'peppol.php?id=' . (int)$orderId . '&type=' . (($type === 'creditnote') ? 'creditnote' : 'invoice');
		$open = 'window.open(' . einvoice_js_string($url) . ", '_blank');";
		if (!$askFirst) {
			return "<script>\n\t$open\n</script>\n";
		}
		$question = einvoice_findtekst('einvoice.send.confirm_register|Første gang du sender en e-faktura, bliver virksomheden registreret til e-faktura. Vil du fortsætte?', $sprogId);
		return "<script>\n\tif (confirm(" . einvoice_js_string($question) . ") == true) {\n\t\t$open\n\t}\n</script>\n";
	}
}

if (!function_exists('einvoice_ui_user_text')) {
	/**
	 * A message for the user without the provider's name: messages from the send code and the provider's error
	 * answers say "Semantic", which users must not see. Each line starts with a capital letter afterwards.
	 *
	 * @param string $text
	 * @return string
	 */
	function einvoice_ui_user_text($text) {
		$text = preg_replace('/\bSemantic\b/u', 'e-faktura-tjenesten', (string)$text) ?? (string)$text;
		$lines = explode("\n", $text);
		foreach ($lines as $i => $line) {
			if (strncmp($line, 'e-faktura-tjenesten', 19) === 0) {
				$lines[$i] = 'E' . substr($line, 1);
			}
		}
		return implode("\n", $lines);
	}
}

if (!function_exists('einvoice_ui_short')) {
	/**
	 * One line of at most $max characters.
	 *
	 * @param string $text
	 * @param int    $max
	 * @return string
	 */
	function einvoice_ui_short($text, $max) {
		$text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$text)) ?? '');
		$max = max(4, (int)$max);
		if (function_exists('mb_strlen') && mb_check_encoding($text, 'UTF-8')) {
			return (mb_strlen($text, 'UTF-8') > $max) ? mb_substr($text, 0, $max - 3, 'UTF-8') . '...' : $text;
		}
		return (strlen($text) > $max) ? substr($text, 0, $max - 3) . '...' : $text;
	}
}

if (!function_exists('einvoice_status_receiver_candidates')) {
	/**
	 * Column names the receiver's answer may be stored in, in order of preference.
	 *
	 * @return array{status: list<string>, reason: list<string>}
	 */
	function einvoice_status_receiver_candidates() {
		return array(
			'status' => array('receiver_status', 'response_status', 'receiver_response'),
			'reason' => array('receiver_reason', 'response_reason', 'receiver_note'),
		);
	}
}

if (!function_exists('einvoice_status_base_columns')) {
	/**
	 * The send-log columns the status needs (never the XML).
	 *
	 * @return list<string>
	 */
	function einvoice_status_base_columns() {
		return array('id', 'ordre_id', 'art', 'fakturanr', 'network', 'semantic_doc_id', 'ubl_doc_id', 'status', 'status_text', 'created', 'updated');
	}
}

if (!function_exists('einvoice_status_columns_from_schema')) {
	/**
	 * Which columns to select, from the table's column names: the base columns and the receiver columns that exist.
	 *
	 * @param list<string> $tableColumns Lower-case names from information_schema; empty when the table is missing.
	 * @return list<string> Empty when the table is missing or lacks a base column.
	 */
	function einvoice_status_columns_from_schema(array $tableColumns) {
		$tableColumns = array_map('strtolower', $tableColumns);
		$columns = einvoice_status_base_columns();
		foreach ($columns as $column) {
			if (!in_array($column, $tableColumns, true)) {
				return array();
			}
		}
		foreach (einvoice_status_receiver_candidates() as $names) {
			foreach ($names as $name) {
				if (in_array($name, $tableColumns, true)) {
					$columns[] = $name;
				}
			}
		}
		return $columns;
	}
}

if (!function_exists('einvoice_status_columns')) {
	/**
	 * The columns to select from einvoice_sendlog in this database, read once per request.
	 *
	 * @return list<string> Empty when the table is not there.
	 */
	function einvoice_status_columns() {
		static $columns = null;
		if ($columns === null) {
			global $db_type;
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'einvoice_sendlog'";
			$qtxt .= in_array((string)$db_type, array('mysql', 'mysqli'), true) ? ' AND table_schema = DATABASE()' : ' AND table_schema = current_schema()';
			$names = array();
			$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			while ($query && ($r = db_fetch_array($query))) {
				$names[] = strtolower((string)($r['column_name'] ?? $r[0] ?? ''));
			}
			$columns = einvoice_status_columns_from_schema($names);
		}
		return $columns;
	}
}

if (!function_exists('einvoice_status_latest_sql')) {
	/**
	 * The one query for the send-log rows of a set of orders, newest first. einvoice_status_pick_latest() keeps the
	 * newest row per order and document kind; a window function is avoided so it runs on MySQL 5 too.
	 *
	 * @param list<int|string> $orderIds ordrer.id values; cast to int, duplicates and non-positive ids dropped.
	 * @param list<string>     $columns  From einvoice_status_columns().
	 * @return string '' when there is nothing to ask for.
	 */
	function einvoice_status_latest_sql(array $orderIds, array $columns) {
		$ids = array();
		foreach ($orderIds as $orderId) {
			$orderId = (int)$orderId;
			if ($orderId > 0) {
				$ids[$orderId] = $orderId;
			}
		}
		$safe = array();
		foreach ($columns as $column) {
			if (preg_match('/^[a-z_]{1,40}$/', (string)$column)) {
				$safe[] = $column;
			}
		}
		if (!$ids || !$safe) {
			return '';
		}
		return 'SELECT ' . implode(', ', $safe) . ' FROM einvoice_sendlog WHERE ordre_id IN (' . implode(', ', $ids) . ')'
			. ' ORDER BY id DESC';
	}
}

if (!function_exists('einvoice_status_pick_latest')) {
	/**
	 * The newest row per order and document kind.
	 *
	 * @param iterable<array<int|string, mixed>> $rows Rows in any order.
	 * @return array<int, array<string, array<string, mixed>>> [ordre_id]['DO'|'DK'] => row (string keys only).
	 */
	function einvoice_status_pick_latest($rows) {
		$latest = array();
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$clean = array();
			foreach ($row as $key => $value) {
				if (is_string($key)) {
					$clean[$key] = ($value === null) ? '' : $value;
				}
			}
			$orderId = (int)($clean['ordre_id'] ?? 0);
			$art = strtoupper(trim((string)($clean['art'] ?? '')));
			$id = (int)($clean['id'] ?? 0);
			if ($orderId <= 0 || $art === '' || $id <= 0) {
				continue;
			}
			if (!isset($latest[$orderId][$art]) || (int)$latest[$orderId][$art]['id'] < $id) {
				$latest[$orderId][$art] = $clean;
			}
		}
		return $latest;
	}
}

if (!function_exists('einvoice_status_for_orders')) {
	/**
	 * The newest send-log row per order and document kind for a set of orders, in one query.
	 *
	 * @param list<int|string>  $orderIds
	 * @param callable|null     $fetchAll fn(string $sql): list<array<string, mixed>>; null = db_select()/db_fetch_array() (tests pass their own).
	 * @param list<string>|null $columns  Null = einvoice_status_columns().
	 * @return array<int, array<string, array<string, mixed>>> See einvoice_status_pick_latest().
	 */
	function einvoice_status_for_orders(array $orderIds, $fetchAll = null, $columns = null) {
		if (max(array_merge(array(0), array_map('intval', $orderIds))) <= 0) {
			return array();
		}
		$qtxt = einvoice_status_latest_sql($orderIds, ($columns !== null) ? (array)$columns : einvoice_status_columns());
		if ($qtxt === '') {
			return array();
		}
		if (is_callable($fetchAll)) {
			return einvoice_status_pick_latest((array)call_user_func($fetchAll, $qtxt));
		}
		$rows = array();
		$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($query && ($r = db_fetch_array($query))) {
			$rows[] = $r;
		}
		return einvoice_status_pick_latest($rows);
	}
}

if (!function_exists('einvoice_status_receiver')) {
	/**
	 * The receiver's answer in a row, when the incoming side has stored one.
	 *
	 * @param array<string, mixed> $row
	 * @return array{verdict: string, reason: string} verdict 'rejected', 'accepted', 'processing' or '' (none or unknown).
	 */
	function einvoice_status_receiver(array $row) {
		$candidates = einvoice_status_receiver_candidates();
		$code = '';
		foreach ($candidates['status'] as $name) {
			if (isset($row[$name]) && trim((string)$row[$name]) !== '') {
				$code = strtoupper(trim((string)$row[$name]));
				break;
			}
		}
		$reason = '';
		foreach ($candidates['reason'] as $name) {
			if (isset($row[$name]) && trim((string)$row[$name]) !== '') {
				$reason = trim((string)$row[$name]);
				break;
			}
		}
		// UNCL 4343 response codes (Peppol Invoice Response) and plain words
		$verdict = '';
		if (in_array($code, array('RE', 'REJECTED', 'REJECT'), true)) {
			$verdict = 'rejected';
		} elseif (in_array($code, array('AP', 'AB', 'CA', 'PD', 'ACCEPTED', 'APPROVED'), true)) {
			$verdict = 'accepted';
		} elseif (in_array($code, array('IP', 'UQ'), true)) {
			$verdict = 'processing';
		}
		return array('verdict' => $verdict, 'reason' => $reason);
	}
}

if (!function_exists('einvoice_status_view')) {
	/**
	 * What to show for one send-log row.
	 *
	 * @param array<string, mixed>|null $row     A send-log row (einvoice_status_for_orders(), einvoice_sendlog_get()).
	 * @param int                       $sprogId
	 * @param int                       $maxText Longest error or reason text in the label.
	 * @return array{
	 *   kind: string,      'pending', 'sent', 'delivered', 'error', 'rejected' or 'unknown'.
	 *   label: string,     E.g. "Sendt", "Fejl: <tekst>", "Afvist af modtager: <grund>".
	 *   detail: string,    The full stored text for a tooltip, without the provider's name ('' when none).
	 *   logId: int,        einvoice_sendlog.id
	 *   orderId: int,      ordrer.id
	 *   pdf: bool,         Whether the provider has the document, so a PDF can be asked for.
	 * }|null Null without a row.
	 */
	function einvoice_status_view($row, $sprogId, $maxText = 120) {
		if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
			return null;
		}
		$status = trim((string)($row['status'] ?? ''));
		$text = einvoice_ui_user_text(trim((string)($row['status_text'] ?? '')));
		$withText = function ($label, $extra) use ($maxText) {
			$extra = einvoice_ui_short($extra, $maxText);
			return ($extra !== '') ? $label . ': ' . $extra : $label;
		};
		$pending = array('Submitting', 'NoStatus', 'Pending', 'PendingValidating', 'PendingValid', 'Sending', 'Parked');
		if (in_array($status, $pending, true)) {
			$kind = 'pending';
			$label = einvoice_findtekst('einvoice.status.pending|Afsendes', $sprogId);
		} elseif ($status === 'Sent') {
			$kind = 'sent';
			$label = einvoice_findtekst('einvoice.status.sent|Sendt', $sprogId);
		} elseif ($status === 'Received') {
			$kind = 'delivered';
			$label = einvoice_findtekst('einvoice.status.delivered|Leveret', $sprogId);
		} elseif ($status === 'Confirmed') {
			$kind = 'delivered';
			$label = einvoice_findtekst('einvoice.status.confirmed|Bekræftet af modtager', $sprogId);
		} elseif ($status === 'Approved') {
			$kind = 'delivered';
			$label = einvoice_findtekst('einvoice.status.approved|Godkendt af modtager', $sprogId);
		} elseif ($status === 'Rejected') {
			$kind = 'rejected';
			$label = $withText(einvoice_findtekst('einvoice.status.rejected|Afvist af modtager', $sprogId), $text);
		} elseif (in_array($status, array('ValidationFailed', 'SendFailed', 'Error'), true)) {
			$kind = 'error';
			$label = $withText(einvoice_findtekst('einvoice.status.error|Fejl', $sprogId), $text);
		} elseif ($status === 'Unknown') {
			$kind = 'unknown';
			$label = einvoice_findtekst('einvoice.status.unknown|Ukendt: intet svar ved afsendelsen', $sprogId);
		} elseif (preg_match('/^Status([0-9]{1,6})$/', $status, $m)) {
			$kind = 'unknown';
			$label = einvoice_findtekst('einvoice.page.status|Status', $sprogId) . ' ' . $m[1];
		} else {
			$kind = 'unknown';
			$label = einvoice_ui_short($status, 30);
		}
		// The receiver's own answer, when stored, says more than the transport status
		$receiver = einvoice_status_receiver($row);
		if ($receiver['verdict'] === 'rejected') {
			$kind = 'rejected';
			$label = $withText(einvoice_findtekst('einvoice.status.rejected|Afvist af modtager', $sprogId), einvoice_ui_user_text($receiver['reason']));
			$text = einvoice_ui_user_text($receiver['reason']);
		} elseif ($receiver['verdict'] === 'accepted' && $kind !== 'error') {
			$kind = 'delivered';
			$label = einvoice_findtekst('einvoice.status.approved|Godkendt af modtager', $sprogId);
		} elseif ($receiver['verdict'] === 'processing' && in_array($kind, array('sent', 'delivered'), true)) {
			$label = einvoice_findtekst('einvoice.status.processing|Under behandling hos modtager', $sprogId);
		}
		$hasDocument = trim((string)($row['semantic_doc_id'] ?? '')) !== '' || trim((string)($row['ubl_doc_id'] ?? '')) !== '';
		return array(
			'kind' => $kind,
			'label' => $label,
			'detail' => einvoice_ui_short($text, 500),
			'logId' => (int)$row['id'],
			'orderId' => (int)($row['ordre_id'] ?? 0),
			'pdf' => $hasDocument && $kind !== 'error',
		);
	}
}

if (!function_exists('einvoice_status_colour')) {
	/**
	 * @param string $kind From einvoice_status_view().
	 * @return string A CSS colour.
	 */
	function einvoice_status_colour($kind) {
		$colours = array('pending' => '#a66300', 'sent' => '#1b6e2d', 'delivered' => '#1b6e2d', 'error' => '#b00000', 'rejected' => '#b00000', 'unknown' => '#a66300');
		return isset($colours[$kind]) ? $colours[$kind] : '#000000';
	}
}

if (!function_exists('einvoice_pdf_url')) {
	/**
	 * The link to the PDF endpoint (debitor/einvoicePdf.php), relative to a page in debitor/.
	 *
	 * @param int $orderId
	 * @param int $logId
	 * @return string
	 */
	function einvoice_pdf_url($orderId, $logId) {
		return 'einvoicePdf.php?id=' . (int)$orderId . '&log=' . (int)$logId;
	}
}

if (!function_exists('einvoice_status_html')) {
	/**
	 * The status as HTML for the order page: the coloured label, a link to the status page and, when there is one, to the PDF.
	 *
	 * @param array{kind: string, label: string, detail: string, logId: int, orderId: int, pdf: bool} $view From einvoice_status_view().
	 * @param int    $sprogId
	 * @param string $charset The page's charset for htmlspecialchars().
	 * @return string
	 */
	function einvoice_status_html(array $view, $sprogId, $charset = 'UTF-8') {
		$h = function ($text) use ($charset) {
			return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, (string)$charset);
		};
		$title = ($view['detail'] !== '') ? " title='" . $h($view['detail']) . "'" : '';
		$html = "<span class='einvoiceStatus' style='color:" . einvoice_status_colour($view['kind']) . ";'$title>" . $h($view['label']) . "</span>";
		$html .= " <a href='peppol.php?semanticLog=" . (int)$view['logId'] . "' target='_blank'>" . $h(einvoice_findtekst('einvoice.status.details|Detaljer', $sprogId)) . "</a>";
		if ($view['pdf']) {
			$html .= " <a href='" . $h(einvoice_pdf_url($view['orderId'], $view['logId'])) . "' target='_blank'>" . $h(einvoice_findtekst('einvoice.pdf.link|PDF', $sprogId)) . "</a>";
		}
		return $html;
	}
}

if (!function_exists('einvoice_status_badge_html')) {
	/**
	 * A small icon for the order list's action column: coloured by status, the label as its tooltip, a link to the status page.
	 *
	 * @param array{kind: string, label: string, detail: string, logId: int, orderId: int, pdf: bool} $view From einvoice_status_view().
	 * @param int    $sprogId
	 * @param string $charset
	 * @return string
	 */
	function einvoice_status_badge_html(array $view, $sprogId, $charset = 'UTF-8') {
		$title = einvoice_findtekst('einvoice.status.label|E-faktura', $sprogId) . ': ' . $view['label'];
		$title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, (string)$charset);
		$colour = einvoice_status_colour($view['kind']);
		return "<a href='peppol.php?semanticLog=" . (int)$view['logId'] . "' target='_blank' title='$title' class='einvoiceStatusBadge' onclick='event.stopPropagation();'>"
			. "<svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='$colour'>"
			. "<path d='M160-160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h640q33 0 56.5 23.5T880-720v480q0 33-23.5 56.5T800-160H160Zm320-280 320-200v-80L480-520 160-720v80l320 200Z'/></svg></a>";
	}
}
