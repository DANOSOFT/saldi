<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/noteAppend.php --- ver 5.1.0 --- 2026.11.11
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
// Replay-safe writes on a Shopify order's note and tags (implementation plan 2.7, WP10-1).
// Shared with WP14's credit publication, so the signature of shopify_append_note() is fixed.
//
// A note append is an uncertain mutation: orderUpdate replaces the whole note, so a blind
// resend after a lost response would append the text twice. Every append therefore reads the
// note first and skips when the op_key's marker is already there. The mutation goes through
// shopify_graphql() in reconcile mode: after an attempt that may have reached Shopify the note
// is read again, and the marker decides whether it was applied or may be sent once more.
//
// Tags go through tagsAdd only, never orderUpdate.tags, so tags HML or DTAILS set on the
// order survive. tagsAdd is additive, so re-running it would put back a tag HML removed -
// what prevents that is the stage checkpoint in includes/shopify/services/orderWriteback.php,
// not this file.
//
// 20261022 CL/MJ SD-759: created (WP10-1).
// 20261109 CL/MJ SD-759: calls go through shopify_graphql() (WP03) with the shop's row, and a lost note update is reconciled by the client's read-first callback.
// 20261111 CL/MJ SD-759 (Sol): a note changed since the first read is not resent; the next attempt reads it again and appends to it.

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/client.php';

/** Marker prefix that makes a Saldi note append recognisable on a later read. */
const SHOPIFY_NOTE_MARKER_PREFIX = 'saldi:';

/**
 * Date stamp an appended note line carries. DTAILS point 3 (note appends) has not answered
 * yet, so the format lives in one place and changes here when it does.
 */
const SHOPIFY_NOTE_DATE_FORMAT = 'Y-m-d';

const SHOPIFY_NOTE_READ_QUERY = 'query saldiOrderNote($id: ID!) { order(id: $id) { id note } }';

const SHOPIFY_NOTE_UPDATE_MUTATION = 'mutation saldiOrderNoteAppend($input: OrderInput!) {'
	. ' orderUpdate(input: $input) { order { id note } userErrors { field message } } }';

const SHOPIFY_TAGS_ADD_MUTATION = 'mutation saldiOrderTagsAdd($id: ID!, $tags: [String!]!) {'
	. ' tagsAdd(id: $id, tags: $tags) { node { id } userErrors { field message } } }';

/** A write-back call that could not be made or did not succeed; the job retries it. */
class ShopifyWritebackError extends RuntimeException
{
}

/**
 * The shop row shopify_graphql() needs.
 *
 * @return array<string, string|null>
 * @throws ShopifyWritebackError when the shop does not exist
 */
function shopify_writeback_shop(int $shopId): array {
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1', array($shopId))[0] ?? null;
	if ($shop === null) {
		throw new ShopifyWritebackError("unknown Shopify shop: $shopId");
	}
	return $shop;
}

/**
 * The marker an append for $opKey leaves in the note. Hashed so an op_key that names a
 * document or a customer never ends up readable in the shop's note.
 */
function shopify_note_marker(string $opKey): string {
	if (trim($opKey) === '') {
		throw new InvalidArgumentException('shopify_note_marker: op_key must not be empty');
	}
	return '[' . SHOPIFY_NOTE_MARKER_PREFIX . hash('sha256', $opKey) . ']';
}

function shopify_note_has_marker(string $note, string $opKey): bool {
	return strpos($note, shopify_note_marker($opKey)) !== false;
}

/**
 * The note as it should look after the append: $note byte for byte, then one dated line
 * carrying $text and the marker. $date is for tests and for replays that must stay
 * reproducible.
 */
function shopify_note_compose(string $note, string $text, string $opKey, ?string $date = null): string {
	$text = rtrim($text);
	if ($text === '') {
		throw new InvalidArgumentException('shopify_note_compose: text must not be empty');
	}
	$line = ($date ?? date(SHOPIFY_NOTE_DATE_FORMAT)) . ' ' . $text . ' ' . shopify_note_marker($opKey);
	return $note === '' ? $line : $note . "\n" . $line;
}

/**
 * Appends $text to the Shopify order's note once, whatever happens to the response.
 *
 * @param array{deadline?: float, graphql?: array<string, mixed>} $rt the job's deadline; graphql
 *   is merged into shopify_graphql()'s opts (the tests' transport)
 * @return array{
 *   status: string,  'appended', 'already_present', 'reconciled' or 'failed'.
 *   appended: bool,  True when this call, or its lost response, added the line.
 *   note: string|null,  The note as Shopify last reported it, null when it could not be read.
 *   marker: string,  The marker this op_key writes.
 *   errors: array<int, string>,  Why it failed; empty otherwise.
 * }
 */
function shopify_append_note(int $shopId, string $orderGid, string $opKey, string $text, array $rt = array()): array {
	return shopify_note_append_to(shopify_writeback_shop($shopId), $orderGid, $opKey, $text, $rt);
}

/**
 * shopify_append_note() for a shop row. Reads the note first and does nothing when $opKey's
 * marker is already there. A mutation attempt that may have reached Shopify is followed by a
 * read (the reconcile callback): the marker tells whether it was applied, and only a note
 * without it is sent again.
 *
 * @param array<string, string|null> $shop
 * @param array{deadline?: float, graphql?: array<string, mixed>} $rt
 * @return array{status: string, appended: bool, note: string|null, marker: string, errors: array<int, string>}
 */
function shopify_note_append_to(array $shop, string $orderGid, string $opKey, string $text, array $rt = array()): array {
	$gid = shopify_gid($orderGid, 'Order');
	$marker = shopify_note_marker($opKey);

	$read = shopify_note_read($shop, $gid, $rt);
	if ($read['note'] === null) {
		return shopify_note_result('failed', false, null, $marker, $read['errors']);
	}
	if (strpos($read['note'], $marker) !== false) {
		return shopify_note_result('already_present', false, $read['note'], $marker, array());
	}

	$before = $read['note'];
	$reconcile = function () use ($shop, $gid, $marker, $rt, $before): array {
		$after = shopify_note_read($shop, $gid, $rt);
		if ($after['note'] === null) {
			return array('state' => 'unknown');
		}
		if (strpos($after['note'], $marker) === false) {
			// a resend carries the note as it was first read, so it would undo an edit made since
			return array('state' => $after['note'] === $before ? 'absent' : 'unknown');
		}
		$data = array('orderUpdate' => array('order' => array('id' => $gid, 'note' => $after['note']), 'userErrors' => array()));
		return array('state' => 'applied', 'data' => $data);
	};
	$input = array('id' => $gid, 'note' => shopify_note_compose($read['note'], $text, $opKey));
	$opts = shopify_writeback_opts($rt, array('retry_mode' => 'reconcile', 'reconcile' => $reconcile, 'correlation' => array('order_gid' => $gid)));
	$res = shopify_graphql($shop, 'orderUpdate', SHOPIFY_NOTE_UPDATE_MUTATION, array('input' => $input), $opts);
	$written = $res['data']['orderUpdate']['order']['note'] ?? null;
	if ($res['ok'] && is_string($written) && strpos($written, $marker) !== false) {
		return shopify_note_result($res['outcome'] === 'reconciled' ? 'reconciled' : 'appended', true, $written, $marker, array());
	}
	$errors = shopify_graphql_messages($res) ?: array('orderUpdate returned no note carrying the marker (' . ($res['error'] ?? $res['outcome']) . ')');
	return shopify_note_result('failed', false, is_string($written) ? $written : null, $marker, $errors);
}

/**
 * Adds $tags with tagsAdd, which leaves every other tag on the order untouched. Duplicates
 * and blanks are dropped; an empty list is a no-op that costs no API call.
 *
 * tagsAdd is additive, so calling it again would put back a tag HML has removed. Callers
 * must gate the retry on the stage checkpoint rather than on this function.
 *
 * @param array<int, string> $tags
 * @param array{deadline?: float, graphql?: array<string, mixed>} $rt
 * @return array{
 *   status: string,  'added', 'noop' or 'failed'.
 *   tags: array<int, string>,  The tags actually sent.
 *   errors: array<int, string>,  Why it failed; empty otherwise.
 * }
 */
function shopify_add_tags(int $shopId, string $orderGid, array $tags, array $rt = array()): array {
	$clean = shopify_clean_tags($tags);
	if (!$clean) {
		return array('status' => 'noop', 'tags' => array(), 'errors' => array());
	}
	return shopify_tags_add_to(shopify_writeback_shop($shopId), $orderGid, $clean, $rt);
}

/**
 * shopify_add_tags() for a shop row. tagsAdd is sent in idempotent mode: adding the same
 * tags twice leaves the order as adding them once.
 *
 * @param array<string, string|null> $shop
 * @param array<int, string> $tags
 * @param array{deadline?: float, graphql?: array<string, mixed>} $rt
 * @return array{status: string, tags: array<int, string>, errors: array<int, string>}
 */
function shopify_tags_add_to(array $shop, string $orderGid, array $tags, array $rt = array()): array {
	$gid = shopify_gid($orderGid, 'Order');
	$clean = shopify_clean_tags($tags);
	if (!$clean) {
		return array('status' => 'noop', 'tags' => array(), 'errors' => array());
	}
	$opts = shopify_writeback_opts($rt, array('retry_mode' => 'idempotent', 'correlation' => array('order_gid' => $gid)));
	$res = shopify_graphql($shop, 'tagsAdd', SHOPIFY_TAGS_ADD_MUTATION, array('id' => $gid, 'tags' => $clean), $opts);
	if ($res['ok'] && isset($res['data']['tagsAdd']['node']['id'])) {
		return array('status' => 'added', 'tags' => $clean, 'errors' => array());
	}
	$errors = shopify_graphql_messages($res);
	return array('status' => 'failed', 'tags' => $clean, 'errors' => $errors ?: array('tagsAdd returned no node (' . ($res['error'] ?? $res['outcome']) . ')'));
}

/**
 * @param array<int, mixed> $tags
 * @return array<int, string> trimmed, without blanks and duplicates, in the order given
 */
function shopify_clean_tags(array $tags): array {
	$clean = array();
	foreach ($tags as $tag) {
		$tag = trim((string)$tag);
		if ($tag !== '' && !in_array($tag, $clean, true)) {
			$clean[] = $tag;
		}
	}
	return $clean;
}

/**
 * The order's note as Shopify has it right now: '' when the order carries none, null when the
 * read itself did not succeed - which is a different answer, and not one to append to.
 *
 * @param array<string, string|null> $shop
 * @param array{deadline?: float, graphql?: array<string, mixed>} $rt
 * @return array{note: string|null, errors: array<int, string>}
 */
function shopify_note_read(array $shop, string $orderGid, array $rt = array()): array {
	$opts = shopify_writeback_opts($rt, array('correlation' => array('order_gid' => $orderGid)));
	$res = shopify_graphql($shop, 'orderNoteRead', SHOPIFY_NOTE_READ_QUERY, array('id' => $orderGid), $opts);
	if (!$res['ok'] || !isset($res['data']['order']['id'])) {
		$errors = shopify_graphql_messages($res);
		return array('note' => null, 'errors' => $errors ?: array("order $orderGid could not be read (" . ($res['error'] ?? 'not found') . ')'));
	}
	return array('note' => (string)($res['data']['order']['note'] ?? ''), 'errors' => array());
}

/**
 * The opts of one write-back call: the test's graphql seams, then the call's own, then the
 * job's deadline.
 *
 * @param array{deadline?: float, graphql?: array<string, mixed>} $rt
 * @param array<string, mixed> $opts
 * @return array<string, mixed>
 */
function shopify_writeback_opts(array $rt, array $opts): array {
	return ($rt['graphql'] ?? array()) + $opts + array('deadline' => (float)($rt['deadline'] ?? microtime(true) + 50));
}

/**
 * The top-level errors and every userError of a shopify_graphql() result, as messages.
 *
 * @param array<string, mixed> $res
 * @return array<int, string>
 */
function shopify_graphql_messages(array $res): array {
	$messages = array();
	foreach (array_merge($res['errors'] ?? array(), $res['user_errors'] ?? array()) as $error) {
		$messages[] = is_array($error) ? (string)($error['message'] ?? 'error') : (string)$error;
	}
	return $messages;
}

/**
 * @param array<int, string> $errors
 * @return array{status: string, appended: bool, note: string|null, marker: string, errors: array<int, string>}
 */
function shopify_note_result(string $status, bool $appended, ?string $note, string $marker, array $errors): array {
	return array('status' => $status, 'appended' => $appended, 'note' => $note, 'marker' => $marker, 'errors' => $errors);
}
