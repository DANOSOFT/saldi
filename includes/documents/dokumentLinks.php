<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/documents/dokumentLinks.php --- ver 5.1.0 --- 2026.10.30
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
// Document links (WP11-2, K45): a stable, unguessable link per posted invoice and credit note,
// served by api/dokument.php. The token is HMAC-SHA256(tenant secret, ordre_id|doc_type|key_version),
// base64url; dokument_links keeps only its SHA-256, so the table alone gives no working link.
// Revoking a document marks its live token revoked; the next dokument_link() then makes the next
// key version, a new token. Needs the tenant connection from shopify_bootstrap(). Never log a token
// or the secret.
//
// 20261030 CL/SZ SD-752: created (WP11-2).

require_once __DIR__ . '/renderDocument.php';
require_once __DIR__ . '/../shopify/db.php';

/** Tenant setting (settings group shopify) with the HMAC key of the document links; made on first use. */
const DOKUMENT_LINK_SECRET_SETTING = 'dokument_link_secret';

/** The endpoint below the install's base URL. */
const DOKUMENT_LINK_PATH = '/api/dokument.php';

/** doc_type => the order art it links to. */
const DOKUMENT_LINK_TYPES = array('invoice' => 'DO', 'credit' => 'DK');

/**
 * The link to a posted invoice or credit note: <base URL>/api/dokument.php?t=<token>. The same
 * order and doc type give the same link until the document is revoked. The base URL is the one
 * register-webhooks stored for the install (shopify_webhook_base_url()).
 *
 * @param int $ordreId ordrer.id
 * @param string $docType invoice (a posted DO) or credit (a posted DK)
 * @return string the absolute https URL
 * @throws InvalidArgumentException for another doc type
 * @throws DocumentRenderError (NO_DOCUMENT) when the order is no posted document of that type
 * @throws RuntimeException when the install has no base URL yet
 */
function dokument_link(int $ordreId, string $docType): string
{
	dokument_link_order($ordreId, $docType);
	require_once __DIR__ . '/../shopify/events.php'; // shopify_webhook_base_url()
	$base = shopify_webhook_base_url();
	if ($base === null) {
		throw new RuntimeException('no base URL for document links; run register-webhooks first');
	}
	$token = shopify_db_transaction(function () use ($ordreId, $docType): string {
		// one live row per document, also when two callers ask at once
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("dokument_link:$ordreId:$docType"));
		$last = shopify_db_rows('SELECT key_version, revoked_at FROM dokument_links WHERE ordre_id = $1 AND doc_type = $2 ORDER BY key_version DESC LIMIT 1',
			array($ordreId, $docType))[0] ?? null;
		$version = $last === null ? 1 : (int)$last['key_version'] + ($last['revoked_at'] === null ? 0 : 1);
		$token = dokument_link_token($ordreId, $docType, $version);
		shopify_db_rows('INSERT INTO dokument_links (ordre_id, doc_type, key_version, token_hash) VALUES ($1, $2, $3, $4) ON CONFLICT (token_hash) DO NOTHING',
			array($ordreId, $docType, $version, hash('sha256', $token)));
		return $token;
	});
	return $base . DOKUMENT_LINK_PATH . '?t=' . $token;
}

/**
 * The live document behind a token, null for anything else (malformed, unknown, revoked).
 *
 * @return array{ordre_id: int, doc_type: string, key_version: int}|null
 */
function dokument_resolve(string $token): ?array
{
	if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
		return null;
	}
	$row = shopify_db_rows('SELECT ordre_id, doc_type, key_version FROM dokument_links WHERE token_hash = $1 AND revoked_at IS NULL',
		array(hash('sha256', $token)))[0] ?? null;
	if ($row === null) {
		return null;
	}
	return array('ordre_id' => (int)$row['ordre_id'], 'doc_type' => (string)$row['doc_type'], 'key_version' => (int)$row['key_version']);
}

/**
 * Revokes the document's link: its token no longer resolves, and the next dokument_link() gives a
 * new one. Nothing happens when the document has no live link.
 *
 * @throws InvalidArgumentException for another doc type
 */
function dokument_revoke(int $ordreId, string $docType): void
{
	if (!isset(DOKUMENT_LINK_TYPES[$docType])) {
		throw new InvalidArgumentException("doc type must be invoice or credit, not $docType");
	}
	shopify_db_transaction(function () use ($ordreId, $docType): void {
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("dokument_link:$ordreId:$docType"));
		shopify_db_rows('UPDATE dokument_links SET revoked_at = now() WHERE ordre_id = $1 AND doc_type = $2 AND revoked_at IS NULL', array($ordreId, $docType));
	});
}

/**
 * Checks that the order is a posted document of the doc type.
 *
 * @throws InvalidArgumentException for another doc type
 * @throws DocumentRenderError (NO_DOCUMENT) otherwise
 */
function dokument_link_order(int $ordreId, string $docType): void
{
	if (!isset(DOKUMENT_LINK_TYPES[$docType])) {
		throw new InvalidArgumentException("doc type must be invoice or credit, not $docType");
	}
	$order = shopify_db_rows('SELECT art, status, fakturanr FROM ordrer WHERE id = $1', array($ordreId))[0] ?? null;
	if ($order === null || $order['art'] !== DOKUMENT_LINK_TYPES[$docType] || (int)$order['status'] < 3 || trim((string)$order['fakturanr']) === '') {
		throw new DocumentRenderError("order $ordreId is no posted $docType", DocumentRenderError::NO_DOCUMENT);
	}
}

/** The token of one key version: 43 characters of base64url. */
function dokument_link_token(int $ordreId, string $docType, int $version): string
{
	$mac = hash_hmac('sha256', "$ordreId|$docType|$version", dokument_link_secret(), true);
	return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
}

/** The tenant's HMAC key, made on first use (32 random bytes, hex). */
function dokument_link_secret(): string
{
	$read = function (): ?string {
		$rows = shopify_db_rows("SELECT var_value FROM settings WHERE var_grp = 'shopify' AND var_name = $1 AND var_value <> '' ORDER BY id LIMIT 1", array(DOKUMENT_LINK_SECRET_SETTING));
		return isset($rows[0]) ? (string)$rows[0]['var_value'] : null;
	};
	$secret = $read();
	if ($secret !== null) {
		return $secret;
	}
	return shopify_db_transaction(function () use ($read): string {
		// settings has no unique key: serialized like shopify_save_secret(), so one secret is made
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array('shopify_setting:' . DOKUMENT_LINK_SECRET_SETTING));
		$secret = $read();
		if ($secret === null) {
			$secret = bin2hex(random_bytes(32));
			shopify_db_rows("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ($1, 'shopify', $2, 'HMAC key of the document links (api/dokument.php)')",
				array(DOKUMENT_LINK_SECRET_SETTING, $secret));
		}
		return $secret;
	});
}
