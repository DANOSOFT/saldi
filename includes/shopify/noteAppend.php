<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/noteAppend.php --- ver 5.0.0 --- 2026.10.22
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
// note first, skips when the op_key's marker is already there, and on an unclear outcome
// reconciles by reading again instead of sending the mutation a second time.
//
// Tags go through tagsAdd only, never orderUpdate.tags, so tags HML or DTAILS set on the
// order survive. tagsAdd is additive, so re-running it would put back a tag HML removed -
// what prevents that is the stage checkpoint in includes/shopify/services/orderWriteback.php,
// not this file.
//
// 20261022 CL/MJ SD-759: created (WP10-1).

require_once __DIR__ . '/schema.php';

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

class ShopifyWritebackError extends RuntimeException
{
}

/**
 * The one call WP10 needs from the Shopify transport. S09b (WP02) owns the real client and
 * registers it with shopify_set_graphql_client() when it lands; until then only the test
 * doubles implement this.
 */
interface ShopifyGraphqlClient {
	/**
	 * Runs one GraphQL document against $shopId's shop. Throws on a transport failure, which
	 * the caller must read as "the mutation may or may not have been applied".
	 *
	 * @param array<string, mixed> $variables
	 * @return array{data?: array<string, mixed>, errors?: array<int, mixed>}
	 */
	public function call(int $shopId, string $document, array $variables): array;
}

/** Registers the transport, or clears it with null. */
function shopify_set_graphql_client(?ShopifyGraphqlClient $client): void {
	$GLOBALS['shopify_graphql_client'] = $client;
}

function shopify_graphql_client(): ShopifyGraphqlClient {
	$client = $GLOBALS['shopify_graphql_client'] ?? null;
	if (!$client instanceof ShopifyGraphqlClient) {
		throw new ShopifyWritebackError('no Shopify GraphQL client registered (shopify_set_graphql_client)');
	}
	return $client;
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
 * Reads the note first and does nothing when $opKey's marker is already there. After an
 * unclear mutation outcome it reads once more: the marker tells whether the lost response
 * had been applied. The mutation is never sent twice.
 *
 * @return array{
 *   status: string,  'appended', 'already_present', 'reconciled' or 'failed'.
 *   appended: bool,  True when this call, or its lost response, added the line.
 *   note: string|null,  The note as Shopify last reported it, null when it could not be read.
 *   marker: string,  The marker this op_key writes.
 *   errors: array<int, string>,  Why it failed; empty otherwise.
 * }
 */
function shopify_append_note(int $shopId, string $orderGid, string $opKey, string $text): array {
	$gid = shopify_gid($orderGid, 'Order');
	$marker = shopify_note_marker($opKey);
	$client = shopify_graphql_client();

	$note = shopify_note_read($client, $shopId, $gid);
	if ($note === null) {
		return shopify_note_result('failed', false, null, $marker, array("order $gid could not be read"));
	}
	if (strpos($note, $marker) !== false) {
		return shopify_note_result('already_present', false, $note, $marker, array());
	}

	$input = array('id' => $gid, 'note' => shopify_note_compose($note, $text, $opKey));
	$errors = array();
	try {
		$response = $client->call($shopId, SHOPIFY_NOTE_UPDATE_MUTATION, array('input' => $input));
		$errors = shopify_user_errors($response, 'orderUpdate');
		$written = $response['data']['orderUpdate']['order']['note'] ?? null;
		if (!$errors && is_string($written) && strpos($written, $marker) !== false) {
			return shopify_note_result('appended', true, $written, $marker, array());
		}
		if (!$errors) {
			$errors = array('orderUpdate returned no usable order note');
		}
	} catch (Throwable $e) {
		$errors = array($e->getMessage());
	}

	// Uncertain outcome. The note may already carry the line, so read instead of resending.
	$after = shopify_note_read($client, $shopId, $gid);
	if ($after !== null && strpos($after, $marker) !== false) {
		return shopify_note_result('reconciled', true, $after, $marker, $errors);
	}
	return shopify_note_result('failed', false, $after, $marker, $errors);
}

/**
 * Adds $tags with tagsAdd, which leaves every other tag on the order untouched. Duplicates
 * and blanks are dropped; an empty list is a no-op that costs no API call.
 *
 * tagsAdd is additive, so calling it again would put back a tag HML has removed. Callers
 * must gate the retry on the stage checkpoint rather than on this function.
 *
 * @param array<int, string> $tags
 * @return array{
 *   status: string,  'added', 'noop' or 'failed'.
 *   tags: array<int, string>,  The tags actually sent.
 *   errors: array<int, string>,  Why it failed; empty otherwise.
 * }
 */
function shopify_add_tags(int $shopId, string $orderGid, array $tags): array {
	$gid = shopify_gid($orderGid, 'Order');
	$clean = array();
	foreach ($tags as $tag) {
		$tag = trim((string)$tag);
		if ($tag !== '' && !in_array($tag, $clean, true)) {
			$clean[] = $tag;
		}
	}
	if (!$clean) {
		return array('status' => 'noop', 'tags' => array(), 'errors' => array());
	}
	$errors = array();
	try {
		$variables = array('id' => $gid, 'tags' => $clean);
		$response = shopify_graphql_client()->call($shopId, SHOPIFY_TAGS_ADD_MUTATION, $variables);
		$errors = shopify_user_errors($response, 'tagsAdd');
		if (!$errors && isset($response['data']['tagsAdd']['node']['id'])) {
			return array('status' => 'added', 'tags' => $clean, 'errors' => array());
		}
		if (!$errors) {
			$errors = array('tagsAdd returned no node');
		}
	} catch (Throwable $e) {
		$errors = array($e->getMessage());
	}
	return array('status' => 'failed', 'tags' => $clean, 'errors' => $errors);
}

/**
 * The order's note as Shopify has it right now, '' when the order carries none, null when the
 * read itself did not succeed - which is a different answer, and not one to append to.
 */
function shopify_note_read(ShopifyGraphqlClient $client, int $shopId, string $orderGid): ?string {
	try {
		$response = $client->call($shopId, SHOPIFY_NOTE_READ_QUERY, array('id' => $orderGid));
	} catch (Throwable $e) {
		return null;
	}
	if (!empty($response['errors']) || !isset($response['data']['order']['id'])) {
		return null;
	}
	return (string)($response['data']['order']['note'] ?? '');
}

/**
 * The userErrors of one mutation field, flattened to messages, with any top-level GraphQL
 * errors in front of them.
 *
 * @param array{data?: array<string, mixed>, errors?: array<int, mixed>} $response
 * @return array<int, string>
 */
function shopify_user_errors(array $response, string $field): array {
	$messages = array();
	foreach (($response['errors'] ?? array()) as $error) {
		$messages[] = is_array($error) ? (string)($error['message'] ?? 'GraphQL error') : (string)$error;
	}
	foreach (($response['data'][$field]['userErrors'] ?? array()) as $error) {
		$messages[] = is_array($error) ? (string)($error['message'] ?? 'userError') : (string)$error;
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
