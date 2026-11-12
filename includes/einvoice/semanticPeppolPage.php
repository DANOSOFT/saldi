<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/semanticPeppolPage.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261109 CL/MMR SD-815 Created (draft). The Semantic branch of debitor/peppol.php: request handling (send, confirmed resend, show a send) and a small Danish status page.
//                 An order confirmation (type=order) is refused here instead of falling through to EasyUBL.
//                 TODO SD-815: the Danish texts here are plain strings; they need findtekst() keys before the draft is released.
// 20261112 CL/MMR SD-815 The status page says clearly what happened: sent (with the status and a link to the PDF), not sent (the precheck messages as a list), already sent (with Send igen) or no answer.
//                 Its texts are findtekst() keys under einvoice.page.*, and the provider's name is never shown: its answers go under "Tekniske detaljer til support".
//                 The views are pure functions (semanticPeppolSendView(), semanticPeppolShowView()), so every outcome is tested without a database.

include_once(__DIR__ . '/semanticSend.php');
include_once(__DIR__ . '/einvoiceStatusView.php');

if (!function_exists('semanticPeppolRequest')) {
	/**
	 * Read the request once. Null when it is not one for the Semantic branch (the EasyUBL page code then runs as before,
	 * e.g. for viewing an older document with ?name=).
	 *
	 * @param array<string, mixed> $get
	 * @param array<string, mixed> $post
	 * @param string               $method
	 * @return array{action: string, orderId: int, type: string, logId: int, token: string}|null action 'send', 'resend', 'show'
	 *   or 'unsupported' (an order confirmation, which must not fall through to EasyUBL's sendOrder() in a Semantic regnskab).
	 */
	function semanticPeppolRequest(array $get, array $post, $method) {
		$types = array('invoice', 'creditnote');
		if (strtoupper((string)$method) === 'POST' && (string)($post['semanticResend'] ?? '') === '1') {
			$type = (string)($post['type'] ?? '');
			return array(
				'action' => 'resend',
				'orderId' => (int)($post['id'] ?? 0),
				'type' => in_array($type, $types, true) ? $type : '',
				'logId' => (int)($post['logId'] ?? 0),
				'token' => is_string($post['token'] ?? null) ? $post['token'] : '',
			);
		}
		if (isset($get['semanticLog'])) {
			return array('action' => 'show', 'orderId' => 0, 'type' => '', 'logId' => (int)$get['semanticLog'], 'token' => '');
		}
		$type = (string)($get['type'] ?? '');
		if (isset($get['id']) && in_array($type, $types, true)) {
			return array('action' => 'send', 'orderId' => (int)$get['id'], 'type' => $type, 'logId' => 0, 'token' => '');
		}
		if (isset($get['id']) && $type === 'order') {
			return array('action' => 'unsupported', 'orderId' => (int)$get['id'], 'type' => $type, 'logId' => 0, 'token' => '');
		}
		return null;
	}
}

if (!function_exists('semanticPeppolEmptyView')) {
	/**
	 * A view with nothing in it yet.
	 *
	 * @return array{title: string, tone: string, lines: list<string>, items: list<string>, details: list<string>, status: array{label: string, kind: string}|null, pdfUrl: string|null, resend: array{orderId: int, type: string, logId: int, token: string, db: string}|null}
	 */
	function semanticPeppolEmptyView() {
		return array('title' => '', 'tone' => 'error', 'lines' => array(), 'items' => array(), 'details' => array(), 'status' => null, 'pdfUrl' => null, 'resend' => null);
	}
}

if (!function_exists('semanticPeppolMessageLines')) {
	/**
	 * A send result's message as lines for the user, without the provider's name.
	 *
	 * @param string $message
	 * @return list<string>
	 */
	function semanticPeppolMessageLines($message) {
		$lines = array();
		foreach (explode("\n", einvoice_ui_user_text((string)$message)) as $line) {
			if (trim($line) !== '') {
				$lines[] = trim($line);
			}
		}
		return $lines;
	}
}

if (!function_exists('semanticPeppolLogFacts')) {
	/**
	 * The status, the send time and network and the PDF link of a send-log row, into a view.
	 *
	 * @param array<string, mixed> $view
	 * @param array<string, mixed> $log     A send-log row.
	 * @param int                  $sprogId
	 * @return array<string, mixed> The view.
	 */
	function semanticPeppolLogFacts(array $view, array $log, $sprogId) {
		$status = einvoice_status_view($log, $sprogId, 300);
		if ($status === null) {
			return $view;
		}
		$view['status'] = array('label' => $status['label'], 'kind' => $status['kind']);
		$network = (int)($log['network'] ?? 0);
		$via = '';
		if ($network === 3) {
			$via = ' ' . einvoice_findtekst('einvoice.page.via_peppol|via Peppol', $sprogId);
		} elseif ($network === 4) {
			$via = ' ' . einvoice_findtekst('einvoice.page.via_nemhandel|via NemHandel', $sprogId);
		}
		$created = substr(trim((string)($log['created'] ?? '')), 0, 16);
		if ($created !== '') {
			$view['lines'][] = einvoice_findtekst('einvoice.page.sent_at|Afsendt', $sprogId) . ' ' . $created . $via;
		}
		if ($status['pdf']) {
			$view['pdfUrl'] = einvoice_pdf_url($status['orderId'], $status['logId']);
		}
		return $view;
	}
}

if (!function_exists('semanticPeppolSendView')) {
	/**
	 * View: the page after a send. Pure, so every outcome is tested without a database.
	 *
	 * @param array{ok: bool, status: string, message: string, sendLogId: int|null} $result From semantic_send_document().
	 * @param array<string, mixed>|null $log     The send-log row the result names, if any (einvoice_sendlog_get()).
	 * @param array{action: string, orderId: int, type: string, logId: int, token: string} $request
	 * @param array{db: string, sprog_id: int, user: string, sessionId: string} $env
	 * @return array{title: string, tone: string, lines: list<string>, items: list<string>, details: list<string>, status: array{label: string, kind: string}|null, pdfUrl: string|null, resend: array{orderId: int, type: string, logId: int, token: string, db: string}|null}
	 *   tone 'ok', 'warning' or 'error'.
	 */
	function semanticPeppolSendView(array $result, $log, array $request, array $env) {
		$sprogId = (int)$env['sprog_id'];
		$view = semanticPeppolEmptyView();
		$lines = semanticPeppolMessageLines($result['message']);
		$notSent = einvoice_findtekst('einvoice.page.not_sent_title|E-fakturaen er ikke sendt', $sprogId);
		$status = (string)$result['status'];
		if ($result['ok']) {
			$view['title'] = einvoice_findtekst('einvoice.page.sent_title|E-fakturaen er sendt af sted', $sprogId);
			$view['tone'] = 'ok';
			$view['lines'][] = einvoice_findtekst('einvoice.page.sent_text|Den er afleveret til afsendelse og går nu videre til modtageren. Status kan ses på ordren og i fakturalisten.', $sprogId);
			// The first line is the send code's own "delivered" text; what follows is a note from the registration
			foreach (array_slice($lines, 1) as $line) {
				$view['lines'][] = $line;
			}
		} elseif ($status === 'duplicate') {
			$view['title'] = einvoice_findtekst('einvoice.page.duplicate_title|Dokumentet er allerede sendt', $sprogId);
			$view['tone'] = 'warning';
			$logStatus = is_array($log) ? trim((string)($log['status'] ?? '')) : '';
			if (in_array($logStatus, array('Submitting', 'Unknown'), true)) {
				$view['lines'][] = einvoice_findtekst('einvoice.page.duplicate_unknown|Den sidste afsendelse fik intet svar, så modtageren kan have fået dokumentet alligevel.', $sprogId);
			} else {
				$view['lines'][] = einvoice_findtekst('einvoice.page.duplicate_text|Dokumentet er allerede sendt digitalt.', $sprogId);
			}
		} elseif ($status === 'precheck' || $status === 'local_validation') {
			$view['title'] = $notSent;
			if ($status === 'precheck') {
				$view['lines'][] = einvoice_findtekst('einvoice.page.precheck_intro|Ret følgende på ordren eller debitorkortet, og send igen:', $sprogId);
				$view['items'] = $lines;
			} else {
				$view['lines'] = array_slice($lines, 0, 1);
				$view['items'] = array_slice($lines, 1);
			}
		} elseif (in_array($status, array('validation', 'auth', 'unknown', 'failed', 'config'), true)) {
			// The first line says what happened; the rest is the provider's answer, for support
			$view['title'] = ($status === 'unknown') ? einvoice_findtekst('einvoice.page.unknown_title|Afsendelsen fik intet svar', $sprogId) : $notSent;
			$view['tone'] = ($status === 'unknown') ? 'warning' : 'error';
			$view['lines'] = array_slice($lines, 0, 1);
			$view['details'] = array_slice($lines, 1);
		} elseif ($status === 'busy') {
			$view['title'] = einvoice_findtekst('einvoice.page.busy_title|Afsendelsen er allerede i gang', $sprogId);
			$view['tone'] = 'warning';
			$view['lines'] = $lines;
		} else {
			$view['title'] = $notSent;
			$view['lines'] = $lines;
		}
		if (is_array($log) && (int)($log['id'] ?? 0) > 0) {
			$view = semanticPeppolLogFacts($view, $log, $sprogId);
		}
		if ($status === 'duplicate' && (int)$result['sendLogId'] > 0) {
			$view['lines'][] = einvoice_findtekst('einvoice.page.resend_question|Vil du sende det igen?', $sprogId);
			$logId = (int)$result['sendLogId'];
			$view['resend'] = array(
				'orderId' => (int)$request['orderId'],
				'type' => (string)$request['type'],
				'logId' => $logId,
				'token' => semantic_resend_token($env['sessionId'], $request['orderId'], $logId),
				'db' => (string)$env['db'],
			);
		}
		return $view;
	}
}

if (!function_exists('semanticPeppolShowView')) {
	/**
	 * View: one earlier send (peppol.php?semanticLog=<id>).
	 *
	 * @param array<string, mixed>|null $log
	 * @param int                       $sprogId
	 * @return array{title: string, tone: string, lines: list<string>, items: list<string>, details: list<string>, status: array{label: string, kind: string}|null, pdfUrl: string|null, resend: array{orderId: int, type: string, logId: int, token: string, db: string}|null}
	 */
	function semanticPeppolShowView($log, $sprogId) {
		$view = semanticPeppolEmptyView();
		$status = is_array($log) ? einvoice_status_view($log, $sprogId, 300) : null;
		if ($status === null) {
			$view['title'] = einvoice_findtekst('einvoice.page.log_not_found|Afsendelsen findes ikke', $sprogId);
			return $view;
		}
		$what = ((string)($log['art'] ?? '') === 'DK')
			? einvoice_findtekst('einvoice.page.creditnote|Kreditnota', $sprogId)
			: einvoice_findtekst('einvoice.page.invoice|Faktura', $sprogId);
		$view['title'] = $what . ' ' . trim((string)($log['fakturanr'] ?? ''));
		$view['tone'] = in_array($status['kind'], array('error', 'rejected'), true) ? 'error' : (($status['kind'] === 'pending' || $status['kind'] === 'unknown') ? 'warning' : 'ok');
		$view = semanticPeppolLogFacts($view, $log, $sprogId);
		if ($status['detail'] !== '' && in_array($status['kind'], array('sent', 'delivered', 'unknown'), true)) {
			// Error and rejection texts are already in the status label; any other stored text is for support
			$view['details'][] = $status['detail'];
		}
		return $view;
	}
}

if (!function_exists('semanticPeppolHandle')) {
	/**
	 * Controller: do what the request asks and say what to show. All database writes happen here, before any output.
	 *
	 * @param array{action: string, orderId: int, type: string, logId: int, token: string} $request From semanticPeppolRequest().
	 * @param array{db: string, sprog_id: int, user: string, sessionId: string} $env
	 * @return array{redirect: string|null, view: array<string, mixed>} The view as semanticPeppolSendView() describes it.
	 */
	function semanticPeppolHandle(array $request, array $env) {
		$sprogId = (int)$env['sprog_id'];
		$view = semanticPeppolEmptyView();
		if ($request['action'] === 'unsupported') {
			$view['title'] = einvoice_findtekst('einvoice.page.not_sent_title|E-fakturaen er ikke sendt', $sprogId);
			$view['lines'][] = einvoice_findtekst('einvoice.page.order_unsupported|Ordrebekræftelser kan endnu ikke sendes som e-faktura. Fakturaer og kreditnotaer kan.', $sprogId);
			return array('redirect' => null, 'view' => $view);
		}
		if ($request['action'] === 'show') {
			return array('redirect' => null, 'view' => semanticPeppolShowView(einvoice_sendlog_get($request['logId']), $sprogId));
		}

		$context = array('db' => $env['db'], 'sprog_id' => $sprogId, 'user' => $env['user'], 'resend' => false);
		if ($request['action'] === 'resend') {
			$art = semantic_document_art($request['type']);
			$latest = ($art !== null) ? einvoice_sendlog_latest_for_order($request['orderId'], $art) : null;
			$currentLogId = ($latest !== null) ? (int)$latest['id'] : 0;
			if ($art === null || $currentLogId !== $request['logId']
				|| !semantic_resend_token_valid($env['sessionId'], $request['orderId'], $request['logId'], $request['token'])) {
				$view['title'] = einvoice_findtekst('einvoice.page.not_sent_title|E-fakturaen er ikke sendt', $sprogId);
				$view['lines'][] = einvoice_findtekst('einvoice.page.stale|Siden er forældet. Luk den, og send dokumentet igen fra ordren.', $sprogId);
				return array('redirect' => null, 'view' => $view);
			}
			$context['resend'] = true;
		}

		$result = semantic_send_document($request['orderId'], $request['type'], $context);
		if ($request['action'] === 'resend' && $result['sendLogId'] !== null && $result['sendLogId'] !== $request['logId']) {
			// Post/Redirect/Get: a refresh shows the send instead of sending again.
			return array('redirect' => 'peppol.php?semanticLog=' . $result['sendLogId'], 'view' => $view);
		}
		$log = ($result['sendLogId'] !== null) ? einvoice_sendlog_get((int)$result['sendLogId']) : null;
		return array('redirect' => null, 'view' => semanticPeppolSendView($result, $log, $request, $env));
	}
}

if (!function_exists('semanticPeppolRender')) {
	/**
	 * View: the status page, always UTF-8.
	 *
	 * @param array<string, mixed> $view From semanticPeppolSendView()/semanticPeppolShowView(); 'lines' and 'title' at least.
	 * @param int                  $sprogId
	 * @return void
	 */
	function semanticPeppolRender(array $view, $sprogId = 1) {
		$h = function ($text) {
			$text = (string)$text;
			if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
				$text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
			}
			return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		};
		$tone = (string)($view['tone'] ?? (!empty($view['ok']) ? 'ok' : 'error'));
		$styles = array(
			'ok' => array('#1b6e2d', '#3a3', '#f4fbf4'),
			'warning' => array('#8a5300', '#d90', '#fffaf0'),
			'error' => array('#a00', '#c33', '#fff6f6'),
		);
		list($colour, $border, $background) = isset($styles[$tone]) ? $styles[$tone] : $styles['error'];
		if (!headers_sent()) {
			header('Content-Type: text/html; charset=UTF-8');
		}
		print "<!DOCTYPE html>\n<html lang='da'><head><meta charset='UTF-8'><title>" . $h('Saldi - ' . einvoice_findtekst('einvoice.status.label|E-faktura', $sprogId)) . "</title></head><body>";
		print "<div style='max-width:640px;margin:40px auto;padding:20px 24px;border:1px solid $border;border-radius:6px;background:$background;font-family:Arial,Helvetica,sans-serif;'>";
		print "<h2 style='margin-top:0;color:$colour;'>" . $h($view['title'] ?? '') . "</h2>";
		if (!empty($view['status'])) {
			print "<p><b>" . $h(einvoice_findtekst('einvoice.page.status|Status', $sprogId)) . ":</b> ";
			print "<span style='color:" . einvoice_status_colour((string)$view['status']['kind']) . ";'>" . $h($view['status']['label']) . "</span></p>";
		}
		foreach ($view['lines'] ?? array() as $line) {
			print "<p>" . $h($line) . "</p>";
		}
		if (!empty($view['items'])) {
			print "<ul>";
			foreach ($view['items'] as $item) {
				print "<li>" . $h($item) . "</li>";
			}
			print "</ul>";
		}
		if (!empty($view['pdfUrl'])) {
			print "<p><a href='" . $h($view['pdfUrl']) . "' target='_blank'>" . $h(einvoice_findtekst('einvoice.page.pdf_link|Vis e-fakturaen som PDF', $sprogId)) . "</a></p>";
		}
		if (!empty($view['details'])) {
			print "<details><summary>" . $h(einvoice_findtekst('einvoice.page.details|Tekniske detaljer til support', $sprogId)) . "</summary>";
			foreach ($view['details'] as $line) {
				print "<p style='font-size:90%;color:#444;'>" . $h($line) . "</p>";
			}
			print "</details>";
		}
		if (!empty($view['resend'])) {
			$r = $view['resend'];
			print "<form method='post' action='peppol.php'>";
			print "<input type='hidden' name='semanticResend' value='1'>";
			print "<input type='hidden' name='id' value='" . (int)$r['orderId'] . "'>";
			print "<input type='hidden' name='type' value='" . $h($r['type']) . "'>";
			print "<input type='hidden' name='logId' value='" . (int)$r['logId'] . "'>";
			print "<input type='hidden' name='token' value='" . $h($r['token']) . "'>";
			print "<input type='hidden' name='thisDb' value='" . $h($r['db']) . "'>";
			print "<p><button type='submit'>" . $h(einvoice_findtekst('einvoice.page.resend|Send igen', $sprogId)) . "</button></p>";
			print "</form>";
		}
		print "<p><button type='button' onclick='window.close();'>" . $h(einvoice_findtekst('einvoice.page.close|Luk', $sprogId)) . "</button></p>";
		print "</div></body></html>";
	}
}

if (!function_exists('semanticPeppolPage')) {
	/**
	 * Entry point from debitor/peppol.php when the regnskab sends through Semantic. Returns false when the request
	 * is not one for this branch; otherwise answers it and exits.
	 *
	 * @param array<string, mixed> $get
	 * @param array<string, mixed> $post
	 * @param string               $method
	 * @param array{db: string, sprog_id: int, user: string, sessionId: string} $env
	 * @return bool
	 */
	function semanticPeppolPage(array $get, array $post, $method, array $env) {
		$request = semanticPeppolRequest($get, $post, $method);
		if ($request === null) {
			return false;
		}
		$outcome = semanticPeppolHandle($request, $env);
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		if ($outcome['redirect'] !== null) {
			header('Location: ' . $outcome['redirect']);
			exit;
		}
		semanticPeppolRender($outcome['view'], (int)$env['sprog_id']);
		exit;
	}
}
