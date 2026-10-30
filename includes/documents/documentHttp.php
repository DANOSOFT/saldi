<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/documents/documentHttp.php --- ver 5.1.0 --- 2026.10.30
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
// The public document endpoint (WP11-2, K45) behind api/dokument.php: a document link's token
// gives its PDF, rendered by render_document(). A GET only reads and renders; it never posts,
// mails or restocks. Anything that doesn't resolve to a live document gets the same 404.
//
// 20261030 CL/SZ SD-752: created (WP11-2).

require_once __DIR__ . '/dokumentLinks.php';

/** Requests per client address in one window, and the window in seconds. */
const DOKUMENT_RATE_LIMIT = 30;
const DOKUMENT_RATE_WINDOW = 60;

/**
 * Answers one request to api/dokument.php?t=<token>: 200 with the PDF (GET, or HEAD without the
 * body), 404 for anything not resolvable, 405 for another method, 429 over the rate limit and 503
 * when the rate limit can't be kept, the database fails or the document can't be rendered. Every response carries
 * Cache-Control: no-store, nosniff and no-referrer. The token is never logged.
 *
 * Needs the tenant connection and legacy globals from shopify_bootstrap().
 *
 * @param array<string, mixed> $q the query ($_GET)
 * @param array<string, mixed> $server $_SERVER
 */
function handle_dokument_request(array $q, array $server): void
{
	$method = (string)($server['REQUEST_METHOD'] ?? '');
	if ($method !== 'GET' && $method !== 'HEAD') {
		dokument_respond(405, array('Allow: GET, HEAD'));
		return;
	}
	$limit = dokument_rate_limit((string)($server['REMOTE_ADDR'] ?? ''));
	if ($limit !== null) {
		dokument_respond($limit, $limit === 429 ? array('Retry-After: ' . DOKUMENT_RATE_WINDOW) : array());
		return;
	}
	$token = $q['t'] ?? null;
	$link = null;
	try {
		$link = is_string($token) ? dokument_resolve($token) : null;
		if ($link === null) {
			dokument_respond(404);
			return;
		}
		$doc = render_document($link['ordre_id'], $link['doc_type'] === 'credit' ? 5 : 4);
	} catch (DocumentRenderError $e) {
		if ($e->getCode() === DocumentRenderError::NO_DOCUMENT) {
			dokument_respond(404);
			return;
		}
		dokument_log_failure($link, $e);
		dokument_respond(503);
		return;
	} catch (ShopifyDbError $e) {
		dokument_log_failure($link, $e);
		dokument_respond(503);
		return;
	}
	// render_document() follows the order's art; a link only ever opens its own kind of document
	if ($doc['formular'] !== ($link['doc_type'] === 'credit' ? 5 : 4)) {
		dokument_respond(404);
		return;
	}
	$size = @filesize($doc['path']);
	if ($size === false) {
		dokument_respond(503);
		return;
	}
	dokument_headers(200);
	header('Content-Type: application/pdf');
	header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $doc['filename']) . '"');
	header('Content-Length: ' . $size);
	if ($method === 'GET') {
		readfile($doc['path']);
	}
}

/** One error-log line for a document that could not be given; never the token. */
function dokument_log_failure(?array $link, Throwable $e): void
{
	error_log('dokument failed ' . json_encode(array('ordre_id' => $link['ordre_id'] ?? null, 'doc_type' => $link['doc_type'] ?? null, 'error' => get_class($e) . ': ' . $e->getMessage())));
}

/** A response without a document; the body says nothing about why. */
function dokument_respond(int $status, array $headers = array()): void
{
	dokument_headers($status);
	foreach ($headers as $header) {
		header($header);
	}
	header('Content-Type: text/plain; charset=utf-8');
	echo array(404 => 'Not found', 405 => 'Method not allowed', 429 => 'Too many requests')[$status] ?? 'Unavailable';
}

function dokument_headers(int $status): void
{
	http_response_code($status);
	header('Cache-Control: no-store');
	header('X-Content-Type-Options: nosniff');
	header('Referrer-Policy: no-referrer');
}

/**
 * Counts the request against its client address (DOKUMENT_RATE_LIMIT per DOKUMENT_RATE_WINDOW
 * seconds, a counter file per address under temp/<db>/.ht_dokument_rate/). Fails closed: when the
 * counter can't be read or written, the request is refused.
 *
 * @return int|null null to go on, 429 over the limit, 503 when the limit can't be kept
 */
function dokument_rate_limit(string $client): ?int
{
	global $db;

	$dir = dirname(__DIR__, 2) . "/temp/$db/.ht_dokument_rate";
	if (!is_string($db) || !preg_match('/^[a-z0-9_]+$/', $db) || (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir))) {
		return 503;
	}
	$file = "$dir/" . hash('sha256', $client);
	$fh = @fopen($file, 'c+');
	if ($fh === false) {
		return 503;
	}
	try {
		if (!flock($fh, LOCK_EX)) {
			return 503;
		}
		$now = time();
		[$start, $count] = array_map('intval', explode(' ', (string)stream_get_contents($fh)) + array(0, 0));
		if ($now - $start >= DOKUMENT_RATE_WINDOW) {
			[$start, $count] = array($now, 0);
		}
		$count++;
		if (!rewind($fh) || !ftruncate($fh, 0) || fwrite($fh, "$start $count") === false || !fflush($fh)) {
			return 503;
		}
	} finally {
		fclose($fh);
	}
	if (mt_rand(1, 100) === 1) {
		// counters of addresses that went quiet
		foreach (glob("$dir/*") ?: array() as $old) {
			if (@filemtime($old) < $now - DOKUMENT_RATE_WINDOW) {
				@unlink($old);
			}
		}
	}
	return $count > DOKUMENT_RATE_LIMIT ? 429 : null;
}
