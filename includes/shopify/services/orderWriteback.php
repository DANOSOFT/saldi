<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/orderWriteback.php --- ver 5.1.0 --- 2026.11.13
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
// Writing an invoiced web order's result back onto the Shopify order (implementation plan
// 2.7, WP10-1). The import (WP09, orderImport.php) enqueues order.tags when it sets the link to
// invoiced. Once the hold tags are on the order, that stage enqueues the rest:
//
//   order.tags (10) - the hold tags the intake chose, so DTAILS' rule matrix holds the order
//                     before anything else lands on it
//   order.move (15) - the lines Saldi drew from another location are moved there (orderMove.php)
//   order.references (20) - K23 order and invoice number, K45 invoice link
//   order.note (25) - K55 split-set note and its JSON metafield
//   order.processed (25) - saldi-behandlet, last of all
//
// Priorities alone do not hold that order: a stage that backs off and retries lets a later one
// overtake it. So every stage records 'done' (or, for order.move only, 'abandoned') in
// shopify_order_links.flags under writeback_v1, and every later stage checks those checkpoints
// before it calls Shopify. A stage whose prerequisites are unmet fails its attempt and is retried
// with the queue's backoff, which keeps saldi-behandlet off an order whose hold tags never landed.
// order.processed waits for order.move only once a move has been recorded: order.tags records it
// as pending when it enqueues it, so an order whose write-back began before L10b is not held up.
//
// The decision half (shopify_writeback_stage()) takes the checkpoints and the order's data as
// arguments; shopify_writeback_job() is the worker handler that reads and records them.
//
// 20261022 CL/MJ SD-759: created (WP10-1).
// 20261109 CL/MJ SD-759: wired to the merged lanes.
// 20261111 CL/MJ SD-759 (Sol): a split set's lager name comes from one LG row per lager, the newest, so rows kept from earlier years are not listed twice.
//                The worker runs the stages from the job WP09 enqueues, the order's data is read from the order and its link, and the calls go through shopify_graphql().
// 20261112 CL/MJ SD-759 (review): a waiting stage, or one of a shop whose orders flow is off, yields instead of failing; a recorded note op is not appended again.
//                Two split sets of the same bundle keep apart (saet_id), and antal is a number in saet_fordeling.
// 20261112 CL/MJ SD-759 (CodeRabbit): the invoice link's token is sent to Shopify but kept out of the call log.
// 20261113 CL/MJ SD-760: order.tags enqueues order.move too and records it pending, so saldi-behandlet waits for the move.
//                The order.move stage runs shopify_move_job() (orderMove.php).

require_once __DIR__ . '/../noteAppend.php';
require_once __DIR__ . '/orderMove.php';
require_once __DIR__ . '/../links.php';
require_once __DIR__ . '/../queue.php';
require_once __DIR__ . '/../../documents/dokumentLinks.php';

/** shopify_order_links.flags namespace holding the stage checkpoints. */
const SHOPIFY_WRITEBACK_NAMESPACE = 'writeback_v1';

/** shopify_order_links.flags namespace holding one entry per completed note op_key. */
const SHOPIFY_NOTE_OPS_NAMESPACE = 'note_ops';

/**
 * Metafield namespace. The saldi.* definitions are DTAILS' to create; until they exist the
 * values are written but have no display on the Shopify side.
 */
const SHOPIFY_METAFIELD_NAMESPACE = 'saldi';

const SHOPIFY_TAG_PROCESSED = 'saldi-behandlet';

/** The stages order.tags enqueues once it is done. */
const SHOPIFY_WRITEBACK_FOLLOWERS = array('order.move', 'order.references', 'order.note', 'order.processed');

/** The checkpoints that finish a stage; order.move is recorded as pending until then. */
const SHOPIFY_WRITEBACK_FINISHED = array('done', 'abandoned');

/** Seconds before a stage that waits (for an earlier stage, or for the orders flow) is claimed again. */
const SHOPIFY_WRITEBACK_WAIT = 300;

const SHOPIFY_METAFIELDS_SET_MUTATION = 'mutation saldiOrderMetafields($metafields: [MetafieldsSetInput!]!) {'
	. ' metafieldsSet(metafields: $metafields) { metafields { namespace key } userErrors { field message } } }';

/**
 * Which earlier checkpoints a stage needs, and the states that count as finished. order.move
 * is the only stage that may be abandoned, and only order.processed waits for it.
 *
 * @return array<string, array<int, string>> required stage => acceptable states
 */
function shopify_writeback_prerequisites(string $stage): array {
	$done = array('done');
	switch ($stage) {
		case 'order.tags':
			return array();
		case 'order.move':
		case 'order.references':
			return array('order.tags' => $done);
		case 'order.note':
			return array('order.tags' => $done, 'order.references' => $done);
		case 'order.processed':
			return array(
				'order.tags' => $done,
				'order.references' => $done,
				'order.note' => $done,
				'order.move' => array('done', 'abandoned'),
			);
	}
	throw new InvalidArgumentException("unknown writeback stage: $stage");
}

/**
 * The first prerequisite $stage is still waiting for, null when it may run. order.move counts
 * only once it is recorded: an order without a move has nothing to wait for.
 *
 * @param array<string, string> $checkpoints stage => recorded state
 */
function shopify_writeback_blocked_by(array $checkpoints, string $stage): ?string {
	foreach (shopify_writeback_prerequisites($stage) as $required => $states) {
		if ($required === 'order.move' && !isset($checkpoints[$required])) {
			continue;
		}
		if (!in_array($checkpoints[$required] ?? '', $states, true)) {
			return $required;
		}
	}
	return null;
}

/**
 * K23 and K45: Saldi's order and invoice number, and the invoice link, as metafieldsSet input.
 * A value that is not there is left out rather than written empty.
 *
 * @param array{order_gid: string, ordre_nr?: int|string|null, faktura_nr?: int|string|null} $ctx
 * @param string|null $url invoice link from dokument_link($ordreId, 'invoice'), or null
 * @return array<int, array{ownerId: string, namespace: string, key: string, type: string, value: string}>
 */
function shopify_build_order_metafields(array $ctx, ?string $url): array {
	$owner = shopify_gid((string)($ctx['order_gid'] ?? ''), 'Order');
	$fields = array(
		array('key' => 'ordre_nr', 'type' => 'single_line_text_field', 'value' => $ctx['ordre_nr'] ?? null),
		array('key' => 'faktura_nr', 'type' => 'single_line_text_field', 'value' => $ctx['faktura_nr'] ?? null),
		array('key' => 'faktura_link', 'type' => 'url', 'value' => $url),
	);
	$metafields = array();
	foreach ($fields as $field) {
		$value = trim((string)($field['value'] ?? ''));
		if ($value !== '') {
			$metafields[] = array(
				'ownerId' => $owner,
				'namespace' => SHOPIFY_METAFIELD_NAMESPACE,
				'key' => $field['key'],
				'type' => $field['type'],
				'value' => $value,
			);
		}
	}
	return $metafields;
}

/**
 * K55: the note text for the sets whose components ship from more than one lager. The wording
 * is provisional until DTAILS point 3 answers; it lives in this one function.
 *
 * @param array<int, array{saet: string, vare: string, antal: int|float|string, lager: string}> $distribution
 */
function shopify_build_split_set_note(array $distribution): string {
	if (!$distribution) {
		throw new InvalidArgumentException('shopify_build_split_set_note: distribution must not be empty');
	}
	$lines = array();
	$set = null;
	foreach ($distribution as $row) {
		if ($row['saet'] . '#' . ($row['saet_id'] ?? '') !== $set) {
			$set = $row['saet'] . '#' . ($row['saet_id'] ?? '');
			$lines[] = "Sæt {$row['saet']} delt over flere lagre:";
		}
		$antal = (float)$row['antal'];
		$antal = $antal == (int)$antal ? (string)(int)$antal : (string)$antal;
		$lines[] = '- ' . $antal . ' x ' . trim((string)$row['vare']) . ': ' . trim((string)$row['lager']);
	}
	return implode("\n", $lines);
}

/**
 * K55: the same distribution as a JSON metafield, so DTAILS' rules can read it as data rather
 * than parse the note.
 *
 * @param array<int, array{saet: string, vare: string, antal: int|float|string, lager: string}> $distribution
 * @return array{ownerId: string, namespace: string, key: string, type: string, value: string}
 */
function shopify_build_split_set_metafield(string $orderGid, array $distribution): array {
	return array(
		'ownerId' => shopify_gid($orderGid, 'Order'),
		'namespace' => SHOPIFY_METAFIELD_NAMESPACE,
		'key' => 'saet_fordeling',
		'type' => 'json',
		'value' => json_encode(array_values($distribution), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
	);
}

/** The op_key a split-set note append is recorded under, stable across replays. */
function shopify_writeback_note_op_key(int $ordreId): string {
	return 'wp10.split_set:' . $ordreId;
}

/**
 * Sends metafieldsSet, which is safe to send twice. An empty list costs no call.
 *
 * @param array<string, string|null> $shop
 * @param array<int, array<string, string>> $metafields
 * @param array{deadline?: float, graphql?: array<string, mixed>} $rt
 * @return array{status: string, errors: array<int, string>} status is 'set', 'noop' or 'failed'
 */
function shopify_set_metafields(array $shop, array $metafields, array $rt = array()): array {
	if (!$metafields) {
		return array('status' => 'noop', 'errors' => array());
	}
	$vars = array('metafields' => array_values($metafields));
	$opts = shopify_writeback_opts($rt, array('retry_mode' => 'idempotent'));
	// The invoice link's t= token opens the invoice; shopify_redact() does not know t=, so it is
	// named as a secret for the logged request. Shopify still gets the working link.
	$tokens = array();
	foreach ($metafields as $field) {
		if (preg_match('/[?&]t=([^&#]+)/', (string)($field['value'] ?? ''), $m)) {
			$tokens[] = $m[1];
		}
	}
	$inner = $opts['redact'] ?? (function_exists('shopify_redact') ? 'shopify_redact' : null);
	if ($tokens && $inner !== null) {
		$opts['redact'] = fn(mixed $body, array $secrets = array()): mixed => $inner($body, array_merge($secrets, $tokens));
	}
	$res = shopify_graphql($shop, 'metafieldsSet', SHOPIFY_METAFIELDS_SET_MUTATION, $vars, $opts);
	$written = $res['data']['metafieldsSet']['metafields'] ?? null;
	if ($res['ok'] && is_array($written) && count($written) === count($metafields)) {
		return array('status' => 'set', 'errors' => array());
	}
	$errors = shopify_graphql_messages($res);
	return array('status' => 'failed', 'errors' => $errors ?: array('metafieldsSet did not confirm every metafield'));
}

/**
 * Runs one stage against the checkpoints it is given, without reading or writing them.
 *
 * @param array<string, string|null> $shop
 * @param array<string, string> $checkpoints stage => recorded state
 * @param array{tags?: array<int, string>, references?: array<string, mixed>, invoice_url?: string|null,
 *              distribution?: array<int, array<string, mixed>>, ordre_id?: int} $context
 * @param array{deadline?: float, graphql?: array<string, mixed>} $rt
 * @return array{
 *   status: string,  'done', 'blocked' or 'failed'.
 *   blocked_by: string|null,  The prerequisite it is waiting for.
 *   checkpoint: string|null,  The state to record, null when nothing is finished yet.
 *   errors: array<int, string>,  Why it failed; empty otherwise.
 *   note_op: string|null,  The op_key whose append is now on the order, for the note_ops log.
 * }
 */
function shopify_writeback_stage(array $shop, string $orderGid, string $stage, array $context, array $checkpoints, array $rt = array()): array {
	// A stage that already has a checkpoint is finished and never runs again, whatever the
	// other checkpoints say: that is what keeps a tag HML removed from being put back.
	if (in_array($checkpoints[$stage] ?? '', SHOPIFY_WRITEBACK_FINISHED, true)) {
		return shopify_writeback_outcome('done', null, null, array());
	}
	$blockedBy = shopify_writeback_blocked_by($checkpoints, $stage);
	if ($blockedBy !== null) {
		return shopify_writeback_outcome('blocked', $blockedBy, null, array());
	}
	switch ($stage) {
		case 'order.tags':
			return shopify_writeback_finished(shopify_tags_add_to($shop, $orderGid, $context['tags'] ?? array(), $rt));
		case 'order.references':
			$ctx = (array)($context['references'] ?? array());
			$ctx['order_gid'] = $orderGid;
			return shopify_writeback_finished(shopify_set_metafields($shop, shopify_build_order_metafields($ctx, $context['invoice_url'] ?? null), $rt));
		case 'order.note':
			return shopify_writeback_stage_note($shop, $orderGid, $context, $rt);
		case 'order.processed':
			return shopify_writeback_finished(shopify_tags_add_to($shop, $orderGid, array(SHOPIFY_TAG_PROCESSED), $rt));
		case 'order.move':
			throw new LogicException('order.move runs through shopify_move_job()');
	}
	throw new InvalidArgumentException("unknown writeback stage: $stage");
}

/**
 * K55. The note append is the replay-safe part; the JSON metafield is a plain set. The
 * saldi-saet-delt tag is not written here: the intake put it on the order's hold tags, and
 * order.tags owns every tag.
 *
 * @param array<string, string|null> $shop
 * @param array<string, mixed> $context
 * @param array{deadline?: float, graphql?: array<string, mixed>} $rt
 */
function shopify_writeback_stage_note(array $shop, string $orderGid, array $context, array $rt): array {
	$distribution = (array)($context['distribution'] ?? array());
	if (!$distribution) {
		return shopify_writeback_outcome('done', null, 'done', array());
	}
	$opKey = shopify_writeback_note_op_key((int)($context['ordre_id'] ?? 0));
	// An append already in note_ops is on the order, even if the line has been deleted there since.
	if (empty($context['note_recorded'])) {
		$note = shopify_note_append_to($shop, $orderGid, $opKey, shopify_build_split_set_note($distribution), $rt);
		if ($note['status'] === 'failed') {
			return shopify_writeback_outcome('failed', null, null, $note['errors']);
		}
	}
	$metafield = shopify_set_metafields($shop, array(shopify_build_split_set_metafield($orderGid, $distribution)), $rt);
	if ($metafield['status'] === 'failed') {
		return shopify_writeback_outcome('failed', null, null, $metafield['errors'], $opKey);
	}
	return shopify_writeback_outcome('done', null, 'done', array(), $opKey);
}

/**
 * The outcome of a stage that is one call: done unless the call failed.
 *
 * @param array{status: string, errors: array<int, string>} $result
 */
function shopify_writeback_finished(array $result): array {
	if ($result['status'] === 'failed') {
		return shopify_writeback_outcome('failed', null, null, $result['errors']);
	}
	return shopify_writeback_outcome('done', null, 'done', array());
}

/**
 * @param array<int, string> $errors
 * @return array{status: string, blocked_by: string|null, checkpoint: string|null, errors: array<int, string>, note_op: string|null}
 */
function shopify_writeback_outcome(string $status, ?string $blockedBy, ?string $checkpoint, array $errors, ?string $noteOp = null): array {
	return array('status' => $status, 'blocked_by' => $blockedBy, 'checkpoint' => $checkpoint, 'errors' => $errors, 'note_op' => $noteOp);
}

/**
 * The worker handler of every write-back stage (the job's type is the stage). Reads the
 * order's link, checkpoints and data, runs the stage and records its result. A stage waiting
 * for an earlier one, or of a shop whose orders flow is off (an uninstall turns it off), yields:
 * waiting is not a failed attempt, and the stage it waits for alarms on its own. One Shopify did
 * not accept throws: the queue retries it with backoff and, from the third attempt, it is on the
 * error list. order.tags enqueues the rest in the
 * transaction that records it done, so a replay of a finished order.tags queues nothing.
 *
 * @param array{id: int, type: string, payload: array<string, mixed>} $job
 * @param array{graphql?: array<string, mixed>} $rt test seams
 * @return array{outcome: string}
 * @throws ShopifyWritebackError
 */
function shopify_writeback_job(array $job, float $deadline, callable $heartbeat, array $rt = array()): array {
	$stage = (string)$job['type'];
	$link = shopify_db_rows('SELECT * FROM shopify_order_links WHERE id = $1', array((int)($job['payload']['link_id'] ?? 0)))[0] ?? null;
	if ($link === null || (string)$link['order_gid'] === '' || (int)$link['ordre_id'] < 1) {
		throw new LogicException("job {$job['id']} $stage has no web order link with an order");
	}
	$checkpoints = shopify_writeback_flags($link, SHOPIFY_WRITEBACK_NAMESPACE);
	$shop = shopify_writeback_shop((int)$link['shop_id']);
	$open = !in_array($checkpoints[$stage] ?? '', SHOPIFY_WRITEBACK_FINISHED, true);
	if ($open && (!in_array($shop['orders_enabled'], array(true, 't', '1', 1), true) || shopify_writeback_blocked_by($checkpoints, $stage) !== null)) {
		return array('outcome' => 'yield', 'run_after' => SHOPIFY_WRITEBACK_WAIT);
	}
	if ($open && $stage === 'order.move') {
		return shopify_move_job($job, $link, $shop, $rt + array('deadline' => $deadline));
	}
	$context = $open ? shopify_writeback_context($link, $stage) : array();
	$outcome = shopify_writeback_stage($shop, (string)$link['order_gid'], $stage, $context, $checkpoints, $rt + array('deadline' => $deadline));
	shopify_db_transaction(function () use ($link, $shop, $stage, $outcome): void {
		if ($outcome['note_op'] !== null) {
			shopify_writeback_flag_set((int)$link['id'], SHOPIFY_NOTE_OPS_NAMESPACE, $outcome['note_op'], gmdate('c'));
		}
		if ($outcome['checkpoint'] !== null) {
			shopify_writeback_mark((int)$link['id'], $stage, $outcome['checkpoint']);
		}
		if ($stage === 'order.tags' && $outcome['checkpoint'] !== null) {
			shopify_writeback_enqueue_followers($link, $shop);
		}
	});
	if ($outcome['status'] === 'blocked') {
		throw new ShopifyWritebackError("$stage waits for {$outcome['blocked_by']} on order {$link['order_gid']}");
	}
	if ($outcome['status'] === 'failed') {
		throw new ShopifyWritebackError("$stage on order {$link['order_gid']} failed: " . implode('; ', $outcome['errors']));
	}
	return array('outcome' => 'done');
}

/**
 * What a stage writes, read from the order and its link: the hold tags the intake chose
 * (flags.intake.tags), the order and invoice number with the invoice link, or the split sets.
 *
 * @param array<string, string|null> $link
 * @return array<string, mixed>
 */
function shopify_writeback_context(array $link, string $stage): array {
	$ordreId = (int)$link['ordre_id'];
	switch ($stage) {
		case 'order.tags':
			$flags = json_decode((string)$link['flags'], true);
			return array('tags' => (array)($flags['intake']['tags'] ?? array()));
		case 'order.references':
			$order = shopify_db_rows('SELECT ordrenr, fakturanr FROM ordrer WHERE id = $1', array($ordreId))[0] ?? array();
			$references = array('ordre_nr' => $order['ordrenr'] ?? null, 'faktura_nr' => $order['fakturanr'] ?? null);
			return array('references' => $references, 'invoice_url' => dokument_link($ordreId, 'invoice'));
		case 'order.note':
			$recorded = shopify_writeback_flags($link, SHOPIFY_NOTE_OPS_NAMESPACE);
			$noteRecorded = isset($recorded[shopify_writeback_note_op_key($ordreId)]);
			return array('ordre_id' => $ordreId, 'note_recorded' => $noteRecorded, 'distribution' => shopify_writeback_split_sets($ordreId));
	}
	return array();
}

/**
 * The components of the order's split sets: every set (ordrelinjer.saet) whose component rows
 * are drawn from more than one lager, a row per draw, with the lager's name.
 *
 * @return array<int, array{saet: string, saet_id: int, vare: string, antal: int|float, lager: string}>
 */
function shopify_writeback_split_sets(int $ordreId): array {
	$rows = shopify_db_rows(
		"SELECT l.saet, l.varenr, l.antal, l.lager, l.samlevare, g.beskrivelse AS lager_navn
		FROM ordrelinjer l
		LEFT JOIN (SELECT DISTINCT ON (kodenr) kodenr, beskrivelse FROM grupper WHERE art = 'LG' ORDER BY kodenr, id DESC) g ON g.kodenr::text = l.lager::text
		WHERE l.ordre_id = $1 AND l.saet > 0 ORDER BY l.saet, l.posnr, l.id",
		array($ordreId)
	);
	$sets = array();
	foreach ($rows as $row) {
		$set = (int)$row['saet'];
		if ($row['samlevare'] === 'on') {
			$sets[$set]['name'] = (string)$row['varenr'];
			continue;
		}
		$lager = trim((string)$row['lager_navn']) !== '' ? trim((string)$row['lager_navn']) : 'Lager ' . (int)$row['lager'];
		$sets[$set]['lagre'][(int)$row['lager']] = true;
		$antal = (float)$row['antal'];
		$sets[$set]['rows'][] = array('vare' => (string)$row['varenr'], 'antal' => $antal == (int)$antal ? (int)$antal : $antal, 'lager' => $lager);
	}
	$distribution = array();
	foreach ($sets as $id => $set) {
		if (count($set['lagre'] ?? array()) < 2) {
			continue;
		}
		foreach ($set['rows'] as $row) {
			$distribution[] = array('saet' => (string)($set['name'] ?? $id), 'saet_id' => $id) + $row;
		}
	}
	return $distribution;
}

/**
 * Queues the stages after order.tags under the dedupe key <shop_key>:<order gid>; an equal job
 * still queued is not added twice.
 *
 * @param array<string, string|null> $link
 * @param array<string, string|null> $shop the link's shop row
 */
function shopify_writeback_enqueue_followers(array $link, array $shop): void {
	$dedupe = "{$shop['shop_key']}:{$link['order_gid']}";
	$payload = array('link_id' => (int)$link['id'], 'ordre_id' => (int)$link['ordre_id'], 'order_gid' => (string)$link['order_gid']);
	$correlation = array('ordre_id' => (int)$link['ordre_id'], 'order_gid' => (string)$link['order_gid']);
	shopify_writeback_mark((int)$link['id'], 'order.move', 'pending');
	foreach (SHOPIFY_WRITEBACK_FOLLOWERS as $stage) {
		shopify_queue_enqueue($stage, (int)$link['shop_id'], $dedupe, $payload, SHOPIFY_QUEUE_PRIORITY[$stage], null, $correlation);
	}
}

/**
 * One namespace of the link's flags, as strings.
 *
 * @param array<string, string|null> $link
 * @return array<string, string>
 */
function shopify_writeback_flags(array $link, string $namespace): array {
	$flags = json_decode((string)$link['flags'], true);
	$values = array();
	foreach ((array)($flags[$namespace] ?? array()) as $name => $value) {
		if (is_scalar($value)) {
			$values[(string)$name] = (string)$value;
		}
	}
	return $values;
}

/**
 * Records a stage's result. Only order.move may be pending (queued, not finished) or abandoned;
 * a tags, references or note stage that fails permanently records nothing, so saldi-behandlet
 * stays unset.
 */
function shopify_writeback_mark(int $linkId, string $stage, string $result): void {
	$stages = array_merge(array('order.tags', 'order.move'), SHOPIFY_WRITEBACK_FOLLOWERS);
	if (!in_array($stage, $stages, true)) {
		throw new InvalidArgumentException("unknown writeback stage: $stage");
	}
	if ($result !== 'done' && !(in_array($result, array('pending', 'abandoned'), true) && $stage === 'order.move')) {
		throw new InvalidArgumentException("stage $stage cannot be recorded as $result");
	}
	shopify_writeback_flag_set($linkId, SHOPIFY_WRITEBACK_NAMESPACE, $stage, $result);
}

/**
 * Sets one key inside one flags namespace and leaves the namespace's other keys and the other
 * namespaces alone (shopify_advance_order_link() replaces a whole namespace).
 */
function shopify_writeback_flag_set(int $linkId, string $namespace, string $key, string $value): void {
	$rows = shopify_db_rows(
		'UPDATE shopify_order_links SET flags = jsonb_set(flags, ARRAY[$1::text], coalesce(flags -> $1::text, jsonb_build_object()) || jsonb_build_object($2::text, $3::text)),'
			. ' updated_at = now() WHERE id = $4 RETURNING id',
		array($namespace, $key, $value, $linkId)
	);
	if (!isset($rows[0])) {
		throw new ShopifyLinkError("order link $linkId does not exist");
	}
}
