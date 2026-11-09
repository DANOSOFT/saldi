<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/semanticPeppolPage.php --- ver 5.1.0 --- 2026-11-09 ---
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

include_once(__DIR__ . '/semanticSend.php');

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

if (!function_exists('semanticPeppolStatusLabel')) {
	/**
	 * A send-log status in Danish.
	 *
	 * @param string $status
	 * @return string
	 */
	function semanticPeppolStatusLabel($status) {
		$labels = array(
			'Submitting' => 'Afsendelse i gang', 'Unknown' => 'Ukendt - intet svar fra Semantic', 'ValidationFailed' => 'Afvist ved validering',
			'SendFailed' => 'Afsendelse fejlede', 'NoStatus' => 'Ingen status', 'Error' => 'Fejl', 'Pending' => 'Afleveret, venter',
			'PendingValidating' => 'Valideres', 'PendingValid' => 'Valideret', 'Sending' => 'Sendes', 'Sent' => 'Sendt',
			'Received' => 'Modtaget', 'Confirmed' => 'Bekræftet', 'Rejected' => 'Afvist af modtageren', 'Approved' => 'Godkendt',
			'Parked' => 'Parkeret',
		);
		if (isset($labels[$status])) {
			return $labels[$status];
		}
		return preg_match('/^Status([0-9]+)$/', (string)$status, $m) ? 'Status ' . $m[1] : (string)$status;
	}
}

if (!function_exists('semanticPeppolHandle')) {
	/**
	 * Controller: do what the request asks and say what to show. All database writes happen here, before any output.
	 *
	 * @param array{action: string, orderId: int, type: string, logId: int, token: string} $request From semanticPeppolRequest().
	 * @param array{db: string, sprog_id: int, user: string, sessionId: string} $env
	 * @return array{redirect: string|null, view: array{title: string, ok: bool, lines: list<string>, resend: array{orderId: int, type: string, logId: int, token: string, db: string}|null}}
	 */
	function semanticPeppolHandle(array $request, array $env) {
		$view = array('title' => '', 'ok' => false, 'lines' => array(), 'resend' => null);
		if ($request['action'] === 'unsupported') {
			$view['title'] = 'Dokumentet er ikke sendt';
			$view['lines'][] = 'Ordrebekræftelser kan endnu ikke sendes digitalt via Semantic. Fakturaer og kreditnotaer kan.';
			return array('redirect' => null, 'view' => $view);
		}
		if ($request['action'] === 'show') {
			$log = einvoice_sendlog_get($request['logId']);
			if ($log === null) {
				$view['title'] = 'Afsendelsen findes ikke';
				return array('redirect' => null, 'view' => $view);
			}
			$what = ($log['art'] === 'DK') ? 'Kreditnota' : 'Faktura';
			$view['title'] = $what . ' ' . $log['fakturanr'] . ': ' . semanticPeppolStatusLabel($log['status']);
			$view['ok'] = !in_array($log['status'], array('ValidationFailed', 'SendFailed', 'Error', 'Unknown', 'Rejected'), true);
			$view['lines'][] = 'Afsendt ' . substr($log['created'], 0, 16) . ($log['network'] === 4 ? ' via NemHandel' : ($log['network'] === 3 ? ' via Peppol' : ''));
			if ($log['status_text'] !== '') {
				$view['lines'][] = $log['status_text'];
			}
			return array('redirect' => null, 'view' => $view);
		}

		$context = array('db' => $env['db'], 'sprog_id' => $env['sprog_id'], 'user' => $env['user'], 'resend' => false);
		if ($request['action'] === 'resend') {
			$art = semantic_document_art($request['type']);
			$latest = ($art !== null) ? einvoice_sendlog_latest_for_order($request['orderId'], $art) : null;
			$currentLogId = ($latest !== null) ? (int)$latest['id'] : 0;
			if ($art === null || $currentLogId !== $request['logId']
				|| !semantic_resend_token_valid($env['sessionId'], $request['orderId'], $request['logId'], $request['token'])) {
				$view['title'] = 'Dokumentet er ikke sendt';
				$view['lines'][] = 'Siden er forældet. Luk den, og send dokumentet igen fra ordren.';
				return array('redirect' => null, 'view' => $view);
			}
			$context['resend'] = true;
		}

		$result = semantic_send_document($request['orderId'], $request['type'], $context);
		if ($request['action'] === 'resend' && $result['sendLogId'] !== null && $result['sendLogId'] !== $request['logId']) {
			// Post/Redirect/Get: a refresh shows the send instead of sending again.
			return array('redirect' => 'peppol.php?semanticLog=' . $result['sendLogId'], 'view' => $view);
		}
		$view['ok'] = $result['ok'];
		$view['lines'] = array_values(array_filter(explode("\n", $result['message']), 'strlen'));
		if ($result['ok']) {
			$view['title'] = 'E-fakturaen er afleveret';
		} elseif ($result['status'] === 'duplicate') {
			$view['title'] = 'Dokumentet er allerede sendt';
			$logId = (int)$result['sendLogId'];
			$view['resend'] = array(
				'orderId' => $request['orderId'],
				'type' => $request['type'],
				'logId' => $logId,
				'token' => semantic_resend_token($env['sessionId'], $request['orderId'], $logId),
				'db' => $env['db'],
			);
		} elseif ($result['status'] === 'unknown') {
			$view['title'] = 'Afsendelsen gav intet svar';
		} elseif ($result['status'] === 'busy') {
			$view['title'] = 'Afsendelsen er i gang';
		} else {
			$view['title'] = 'Dokumentet er ikke sendt';
		}
		return array('redirect' => null, 'view' => $view);
	}
}

if (!function_exists('semanticPeppolRender')) {
	/**
	 * View: the status page, always UTF-8.
	 *
	 * @param array{title: string, ok: bool, lines: list<string>, resend: array{orderId: int, type: string, logId: int, token: string, db: string}|null} $view
	 * @return void
	 */
	function semanticPeppolRender(array $view) {
		$h = function ($text) {
			$text = (string)$text;
			if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
				$text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
			}
			return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		};
		$colour = $view['ok'] ? '#1b6e2d' : '#a00';
		$border = $view['ok'] ? '#3a3' : '#c33';
		$background = $view['ok'] ? '#f4fbf4' : '#fff6f6';
		if (!headers_sent()) {
			header('Content-Type: text/html; charset=UTF-8');
		}
		print "<!DOCTYPE html>\n<html lang='da'><head><meta charset='UTF-8'><title>Saldi - e-faktura</title></head><body>";
		print "<div style='max-width:640px;margin:40px auto;padding:20px 24px;border:1px solid $border;border-radius:6px;background:$background;font-family:Arial,Helvetica,sans-serif;'>";
		print "<h2 style='margin-top:0;color:$colour;'>" . $h($view['title']) . "</h2>";
		foreach ($view['lines'] as $line) {
			print "<p>" . $h($line) . "</p>";
		}
		if ($view['resend'] !== null) {
			$r = $view['resend'];
			print "<form method='post' action='peppol.php'>";
			print "<input type='hidden' name='semanticResend' value='1'>";
			print "<input type='hidden' name='id' value='" . (int)$r['orderId'] . "'>";
			print "<input type='hidden' name='type' value='" . $h($r['type']) . "'>";
			print "<input type='hidden' name='logId' value='" . (int)$r['logId'] . "'>";
			print "<input type='hidden' name='token' value='" . $h($r['token']) . "'>";
			print "<input type='hidden' name='thisDb' value='" . $h($r['db']) . "'>";
			print "<p><button type='submit'>" . $h('Send igen') . "</button></p>";
			print "</form>";
		}
		print "<p><button type='button' onclick='window.close();'>" . $h('Luk') . "</button></p>";
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
		semanticPeppolRender($outcome['view']);
		exit;
	}
}
