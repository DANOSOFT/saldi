<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/semanticPdf.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261112 CL/MMR SD-815 Created. The PDF of an e-invoice sent through Semantic (GET /api/UblDocument/pdf/{ublDocumentId}), for debitor/einvoicePdf.php.
//                 semantic_pdf_response() decides everything from injected readers and the client, so its guards are tested without a database or the network.
//                 The PDF is passed straight through to the browser and never written to disk.

include_once(__DIR__ . '/semanticClient.php');
include_once(__DIR__ . '/einvoiceStatusView.php');

if (!function_exists('semantic_pdf_request')) {
	/**
	 * The request's ids, cast. Nothing else from the request is used.
	 *
	 * @param array<string, mixed> $get
	 * @return array{orderId: int, logId: int}
	 */
	function semantic_pdf_request(array $get) {
		$orderId = isset($get['id']) && is_scalar($get['id']) && ctype_digit((string)$get['id']) ? (int)$get['id'] : 0;
		$logId = isset($get['log']) && is_scalar($get['log']) && ctype_digit((string)$get['log']) ? (int)$get['log'] : 0;
		return array('orderId' => $orderId, 'logId' => $logId);
	}
}

if (!function_exists('semantic_pdf_filename')) {
	/**
	 * The download name: faktura-<nr>.pdf or kreditnota-<nr>.pdf, with only safe characters.
	 *
	 * @param string $art       'DO' or 'DK'.
	 * @param string $fakturanr
	 * @return string
	 */
	function semantic_pdf_filename($art, $fakturanr) {
		$number = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$fakturanr) ?? '';
		$number = substr($number, 0, 30);
		$name = (strtoupper((string)$art) === 'DK') ? 'kreditnota' : 'faktura';
		return $name . ($number !== '' ? '-' . $number : '') . '.pdf';
	}
}

if (!function_exists('semantic_pdf_error_page')) {
	/**
	 * A short Danish page in place of the PDF.
	 *
	 * @param int    $status  HTTP status.
	 * @param string $message
	 * @param int    $sprogId
	 * @return array{status: int, headers: list<string>, body: string}
	 */
	function semantic_pdf_error_page($status, $message, $sprogId) {
		$h = function ($text) {
			$text = (string)$text;
			if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
				$text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
			}
			return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		};
		$title = einvoice_findtekst('einvoice.pdf.title|E-faktura som PDF', $sprogId);
		$close = einvoice_findtekst('einvoice.page.close|Luk', $sprogId);
		$body = "<!DOCTYPE html>\n<html lang='da'><head><meta charset='UTF-8'><title>" . $h($title) . "</title></head><body>"
			. "<div style='max-width:640px;margin:40px auto;padding:20px 24px;border:1px solid #c33;border-radius:6px;background:#fff6f6;font-family:Arial,Helvetica,sans-serif;'>"
			. "<h2 style='margin-top:0;color:#a00;'>" . $h($title) . "</h2><p>" . $h($message) . "</p>"
			. "<p><button type='button' onclick='window.close();'>" . $h($close) . "</button></p></div></body></html>";
		return array(
			'status' => (int)$status,
			'headers' => array('Content-Type: text/html; charset=UTF-8', 'Cache-Control: no-store', 'X-Content-Type-Options: nosniff'),
			'body' => $body,
		);
	}
}

if (!function_exists('semantic_pdf_response')) {
	/**
	 * The answer to a PDF request.
	 *
	 * Guards, in order: the regnskab sends through Semantic; both ids are given; the send-log row exists, belongs to
	 * the order, and was made in this database (a copy of a regnskab does not show the customer's documents); the
	 * order exists in this regnskab. When the row has no UBL document id yet, it is read from the outgoing document
	 * and stored. A missing or unreadable PDF gives a Danish message, never the provider's answer.
	 *
	 * @param array{orderId: int, logId: int} $request From semantic_pdf_request().
	 * @param array{
	 *   enabled: bool,                       Whether the regnskab sends through Semantic.
	 *   db: string,                          The current database.
	 *   sprogId: int,
	 *   loadLog: callable,                   fn(int $logId): ?array  A send-log row (einvoice_sendlog_get()).
	 *   orderExists: callable,               fn(int $orderId): bool
	 *   storeUblId: callable,                fn(int $logId, string $ublId): void
	 *   client: SemanticClient|null,         Null when Semantic is not set up on this server.
	 * } $deps
	 * @return array{status: int, headers: list<string>, body: string}
	 */
	function semantic_pdf_response(array $request, array $deps) {
		$sprogId = (int)($deps['sprogId'] ?? 1);
		$notFound = einvoice_findtekst('einvoice.pdf.not_found|Dokumentet findes ikke i dette regnskab.', $sprogId);
		if (empty($deps['enabled'])) {
			return semantic_pdf_error_page(404, einvoice_findtekst('einvoice.pdf.not_available|PDF af e-fakturaen kan ikke vises i dette regnskab.', $sprogId), $sprogId);
		}
		$orderId = (int)($request['orderId'] ?? 0);
		$logId = (int)($request['logId'] ?? 0);
		if ($orderId <= 0 || $logId <= 0) {
			return semantic_pdf_error_page(400, $notFound, $sprogId);
		}
		$log = call_user_func($deps['loadLog'], $logId);
		$db = trim((string)($deps['db'] ?? ''));
		if (!is_array($log) || (int)($log['ordre_id'] ?? 0) !== $orderId
			|| trim((string)($log['db_name'] ?? '')) === '' || trim((string)$log['db_name']) !== $db
			|| !call_user_func($deps['orderExists'], $orderId)) {
			return semantic_pdf_error_page(404, $notFound, $sprogId);
		}
		$notReady = einvoice_findtekst('einvoice.pdf.not_ready|Der er endnu ingen PDF af e-fakturaen. Prøv igen om lidt.', $sprogId);
		$ublId = trim((string)($log['ubl_doc_id'] ?? ''));
		$documentId = trim((string)($log['semantic_doc_id'] ?? ''));
		if ($ublId === '' && $documentId === '') {
			return semantic_pdf_error_page(404, $notReady, $sprogId);
		}
		$client = $deps['client'] ?? null;
		if (!$client instanceof SemanticClient) {
			return semantic_pdf_error_page(503, einvoice_findtekst('einvoice.pdf.not_configured|E-faktura er ikke sat op på denne server. Kontakt saldi.dk support.', $sprogId), $sprogId);
		}
		$failed = einvoice_findtekst('einvoice.pdf.failed|PDF af e-fakturaen kunne ikke hentes lige nu. Prøv igen senere.', $sprogId);
		if (!SemanticClient::isUuid($ublId)) {
			$document = $client->getOutgoingDocument($documentId);
			if (!$document['ok']) {
				return semantic_pdf_error_page(502, $failed, $sprogId);
			}
			$found = (is_array($document['data']) && isset($document['data']['ublDocumentId'])) ? (string)$document['data']['ublDocumentId'] : '';
			if (!SemanticClient::isUuid($found) || $found === '00000000-0000-0000-0000-000000000000') {
				return semantic_pdf_error_page(404, $notReady, $sprogId);
			}
			$ublId = strtolower($found);
			call_user_func($deps['storeUblId'], $logId, $ublId);
		}
		$pdf = $client->getUblDocumentPdf($ublId);
		if (!$pdf['ok'] || !is_string($pdf['data']) || strncmp($pdf['data'], '%PDF-', 5) !== 0) {
			if (!$pdf['ok'] && (int)$pdf['httpStatus'] === 404) {
				return semantic_pdf_error_page(404, $notReady, $sprogId);
			}
			return semantic_pdf_error_page(502, $failed, $sprogId);
		}
		$filename = semantic_pdf_filename((string)($log['art'] ?? ''), (string)($log['fakturanr'] ?? ''));
		return array(
			'status' => 200,
			'headers' => array(
				'Content-Type: application/pdf',
				'Content-Disposition: inline; filename="' . $filename . '"',
				'Content-Length: ' . strlen($pdf['data']),
				'Cache-Control: private, no-store',
				'X-Content-Type-Options: nosniff',
			),
			'body' => $pdf['data'],
		);
	}
}
