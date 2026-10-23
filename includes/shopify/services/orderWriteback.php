<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/orderWriteback.php --- ver 5.0.0 --- 2026.10.22
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
// Writing an invoiced web order's result back onto the Shopify order
// (implementation plan 2.7, WP10-1). WP09 step 9 calls shopify_enqueue_writeback(),
// which queues five stages in a fixed order:
//
//   order.tags (10) - the hold tags, so DTAILS' rule matrix holds the order before
//                     anything else lands on it
//   order.move (15) - built in L10b
//   order.references (20) - K23 order and invoice number, K45 invoice link
//   order.note (25) - K55 split-set note and its JSON metafield
//   order.processed (25) - saldi-behandlet, last of all
//
// Priorities alone do not hold that order: a stage that backs off and retries lets a
// later one overtake it. So every stage records 'done' (or, for order.move only,
// 'abandoned') in shopify_order_links.flags under the writeback_v1 namespace, and every
// later stage checks those checkpoints before it calls Shopify. A stage whose
// prerequisites are unmet re-queues itself instead of running, which is what keeps
// saldi-behandlet off an order whose hold tags never landed.
//
// The decision half of each stage (shopify_writeback_stage_*) takes the checkpoints as an
// argument and returns what to do, so the ordering rules are testable without a database.
// shopify_writeback_run_stage() is the half that reads and writes them; S09b's worker
// handler is a call to it.
//
// 20261022 CL/MJ SD-759: created (WP10-1).

require_once __DIR__ . '/../noteAppend.php';

/** shopify_order_links.flags namespace holding the stage checkpoints. */
const SHOPIFY_WRITEBACK_NAMESPACE = 'writeback_v1';

/** shopify_order_links.flags namespace holding one entry per completed note op_key. */
const SHOPIFY_NOTE_OPS_NAMESPACE = 'note_ops';

/**
 * Metafield namespace. The saldi.* definitions are DTAILS' to create (needed 28 Oct);
 * until they exist the values are written but have no display on the Shopify side.
 */
const SHOPIFY_METAFIELD_NAMESPACE = 'saldi';

const SHOPIFY_TAG_SHORTAGE = 'saldi-mangler-lager';
const SHOPIFY_TAG_SPLIT_SET = 'saldi-saet-delt';
const SHOPIFY_TAG_PROCESSED = 'saldi-behandlet';

const SHOPIFY_METAFIELDS_SET_MUTATION = 'mutation saldiOrderMetafields($metafields: [MetafieldsSetInput!]!) {'
	. ' metafieldsSet(metafields: $metafields) { metafields { namespace key } userErrors { field message } } }';

/**
 * The stages in the order they run, with the queue priority each is enqueued at.
 *
 * @return array<string, int> stage => priority
 */
function shopify_writeback_stages(): array {
	return array(
		'order.tags' => 10,
		'order.move' => 15,
		'order.references' => 20,
		'order.note' => 25,
		'order.processed' => 25,
	);
}

/**
 * Which earlier checkpoints a stage needs, and the states that count as finished.
 *
 * order.move is the only stage that may be abandoned, and only order.processed waits for
 * it - references and the note do not, so an order whose move keeps backing off still gets
 * its numbers and its note. saldi-behandlet waits for all four.
 *
 * @return array<string, array<int, string>> required stage => acceptable states
 */
function shopify_writeback_prerequisites(string $stage): array {
	$done = array('done');
	switch ($stage) {
		case 'order.tags':
			return array();
		case 'order.move':
			return array('order.tags' => $done);
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
 * The first prerequisite $stage is still waiting for, null when it may run.
 *
 * @param array<string, string> $checkpoints stage => recorded state
 */
function shopify_writeback_blocked_by(array $checkpoints, string $stage): ?string {
	foreach (shopify_writeback_prerequisites($stage) as $required => $states) {
		if (!in_array($checkpoints[$required] ?? '', $states, true)) {
			return $required;
		}
	}
	return null;
}

/** @param array<string, string> $checkpoints stage => recorded state */
function shopify_writeback_may_run(array $checkpoints, string $stage): bool {
	return shopify_writeback_blocked_by($checkpoints, $stage) === null;
}

/**
 * The hold tags an allocation calls for, in the order they are written.
 *
 * @param array{shortage?: bool, split_set?: bool} $allocation as stored by WP09
 * @return array<int, string>
 */
function shopify_build_hold_tags(array $allocation): array {
	$tags = array();
	if (!empty($allocation['shortage'])) {
		$tags[] = SHOPIFY_TAG_SHORTAGE;
	}
	if (!empty($allocation['split_set'])) {
		$tags[] = SHOPIFY_TAG_SPLIT_SET;
	}
	return $tags;
}

/**
 * K23 and K45: Saldi's order and invoice number, and the invoice link, as metafieldsSet
 * input. A value that is not there yet is left out rather than written empty - an order
 * invoiced before L11b's dokument_link() exists gets its numbers and no link.
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
		if ($value === '') {
			continue;
		}
		$metafields[] = array(
			'ownerId' => $owner,
			'namespace' => SHOPIFY_METAFIELD_NAMESPACE,
			'key' => $field['key'],
			'type' => $field['type'],
			'value' => $value,
		);
	}
	return $metafields;
}

/**
 * K55: the note text for a set whose components ship from more than one location. The
 * wording is provisional until DTAILS point 3 answers; it lives in this one function.
 *
 * @param array<int, array{vare: string, antal: int|float, lager: string}> $distribution
 */
function shopify_build_split_set_note(array $distribution): string {
	if (!$distribution) {
		throw new InvalidArgumentException('shopify_build_split_set_note: distribution must not be empty');
	}
	$lines = array('Sæt delt over flere lagre:');
	foreach ($distribution as $row) {
		$antal = $row['antal'] ?? 0;
		$antal = ((float)$antal == (int)$antal) ? (string)(int)$antal : (string)(float)$antal;
		$lines[] = '- ' . $antal . ' x ' . trim((string)($row['vare'] ?? '')) . ': ' . trim((string)($row['lager'] ?? ''));
	}
	return implode("\n", $lines);
}

/**
 * K55: the same distribution as a JSON metafield, so DTAILS' rules can read it as data
 * rather than parse the note.
 *
 * @param array<int, array{vare: string, antal: int|float, lager: string}> $distribution
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
 * Sends metafieldsSet. An empty list is a no-op that costs no API call.
 *
 * @param array<int, array<string, string>> $metafields
 * @return array{status: string, errors: array<int, string>} status is 'set', 'noop' or 'failed'
 */
function shopify_set_metafields(int $shopId, array $metafields): array {
	if (!$metafields) {
		return array('status' => 'noop', 'errors' => array());
	}
	$errors = array();
	try {
		$variables = array('metafields' => array_values($metafields));
		$response = shopify_graphql_client()->call($shopId, SHOPIFY_METAFIELDS_SET_MUTATION, $variables);
		$errors = shopify_user_errors($response, 'metafieldsSet');
		$written = $response['data']['metafieldsSet']['metafields'] ?? null;
		if (!$errors && is_array($written) && count($written) === count($metafields)) {
			return array('status' => 'set', 'errors' => array());
		}
		if (!$errors) {
			$errors = array('metafieldsSet did not confirm every metafield');
		}
	} catch (Throwable $e) {
		$errors = array($e->getMessage());
	}
	return array('status' => 'failed', 'errors' => $errors);
}

/**
 * Runs one stage against the checkpoints it is given, without reading or writing them.
 *
 * @param array<string, string> $checkpoints stage => recorded state
 * @param array{allocation?: array<string, mixed>, references?: array<string, mixed>,
 *              invoice_url?: string|null, distribution?: array<int, array<string, mixed>>,
 *              ordre_id?: int} $context
 * @return array{
 *   status: string,  'done', 'requeue' or 'failed'.
 *   blocked_by: string|null,  The prerequisite that sent it back to the queue.
 *   checkpoint: string|null,  The state to record, null when nothing is finished yet.
 *   errors: array<int, string>,  Why it failed; empty otherwise.
 *   note_op: string|null,  The op_key whose append is now on the order, for the note_ops log.
 * }
 */
function shopify_writeback_stage(int $shopId, string $orderGid, string $stage, array $context, array $checkpoints): array {
	// A stage that already has a checkpoint is finished and is never run or re-queued again,
	// whatever the other checkpoints say.
	if (($checkpoints[$stage] ?? '') !== '') {
		return shopify_writeback_outcome('done', null, null, array());
	}
	$blockedBy = shopify_writeback_blocked_by($checkpoints, $stage);
	if ($blockedBy !== null) {
		return shopify_writeback_outcome('requeue', $blockedBy, null, array());
	}
	switch ($stage) {
		case 'order.tags':
			return shopify_writeback_stage_tags($shopId, $orderGid, $context);
		case 'order.references':
			return shopify_writeback_stage_references($shopId, $orderGid, $context);
		case 'order.note':
			return shopify_writeback_stage_note($shopId, $orderGid, $context);
		case 'order.processed':
			return shopify_writeback_stage_processed($shopId, $orderGid);
		case 'order.move':
			throw new ShopifyWritebackError('order.move is built in L10b');
	}
	throw new InvalidArgumentException("unknown writeback stage: $stage");
}

/** @param array<string, mixed> $context */
function shopify_writeback_stage_tags(int $shopId, string $orderGid, array $context): array {
	$tags = shopify_build_hold_tags(is_array($context['allocation'] ?? null) ? $context['allocation'] : array());
	$result = shopify_add_tags($shopId, $orderGid, $tags);
	if ($result['status'] === 'failed') {
		return shopify_writeback_outcome('failed', null, null, $result['errors']);
	}
	return shopify_writeback_outcome('done', null, 'done', array());
}

/** @param array<string, mixed> $context */
function shopify_writeback_stage_references(int $shopId, string $orderGid, array $context): array {
	$ctx = is_array($context['references'] ?? null) ? $context['references'] : array();
	$ctx['order_gid'] = $orderGid;
	$url = $context['invoice_url'] ?? null;
	$result = shopify_set_metafields($shopId, shopify_build_order_metafields($ctx, $url === null ? null : (string)$url));
	if ($result['status'] === 'failed') {
		return shopify_writeback_outcome('failed', null, null, $result['errors']);
	}
	return shopify_writeback_outcome('done', null, 'done', array());
}

/**
 * K55. The note append is the replay-safe part; the JSON metafield is a plain set. The
 * saldi-saet-delt tag is not written here - order.tags owns every tag and this stage
 * cannot run until it is done.
 *
 * @param array<string, mixed> $context
 */
function shopify_writeback_stage_note(int $shopId, string $orderGid, array $context): array {
	$distribution = is_array($context['distribution'] ?? null) ? $context['distribution'] : array();
	if (!$distribution) {
		return shopify_writeback_outcome('done', null, 'done', array());
	}
	$opKey = shopify_writeback_note_op_key((int)($context['ordre_id'] ?? 0));
	$note = shopify_append_note($shopId, $orderGid, $opKey, shopify_build_split_set_note($distribution));
	if ($note['status'] === 'failed') {
		return shopify_writeback_outcome('failed', null, null, $note['errors']);
	}
	$metafield = shopify_set_metafields($shopId, array(shopify_build_split_set_metafield($orderGid, $distribution)));
	if ($metafield['status'] === 'failed') {
		return shopify_writeback_outcome('failed', null, null, $metafield['errors'], $opKey);
	}
	return shopify_writeback_outcome('done', null, 'done', array(), $opKey);
}

function shopify_writeback_stage_processed(int $shopId, string $orderGid): array {
	$result = shopify_add_tags($shopId, $orderGid, array(SHOPIFY_TAG_PROCESSED));
	if ($result['status'] === 'failed') {
		return shopify_writeback_outcome('failed', null, null, $result['errors']);
	}
	return shopify_writeback_outcome('done', null, 'done', array());
}

/**
 * @param array<int, string> $errors
 * @return array{status: string, blocked_by: string|null, checkpoint: string|null,
 *               errors: array<int, string>, note_op: string|null}
 */
function shopify_writeback_outcome(string $status, ?string $blockedBy, ?string $checkpoint, array $errors, ?string $noteOp = null): array {
	return array(
		'status' => $status,
		'blocked_by' => $blockedBy,
		'checkpoint' => $checkpoint,
		'errors' => $errors,
		'note_op' => $noteOp,
	);
}

/**
 * Reads the checkpoints, runs the stage and records its result. This is the body S09b's
 * worker handler calls; everything it decides is in shopify_writeback_stage().
 *
 * @param array<string, mixed> $context
 * @return array{status: string, blocked_by: string|null, checkpoint: string|null,
 *               errors: array<int, string>, note_op: string|null}
 */
function shopify_writeback_run_stage(int $shopId, string $orderGid, string $stage, array $context): array {
	$checkpoints = shopify_writeback_checkpoints($shopId, $orderGid);
	$outcome = shopify_writeback_stage($shopId, $orderGid, $stage, $context, $checkpoints);
	if ($outcome['note_op'] !== null) {
		shopify_note_op_record($shopId, $orderGid, $outcome['note_op']);
	}
	if ($outcome['checkpoint'] !== null) {
		shopify_writeback_mark($shopId, $orderGid, $stage, $outcome['checkpoint']);
	}
	return $outcome;
}

/**
 * The writeback_v1 checkpoints recorded on the order link.
 *
 * @return array<string, string> stage => recorded state
 */
function shopify_writeback_checkpoints(int $shopId, string $orderGid): array {
	return shopify_order_link_flags($shopId, $orderGid, SHOPIFY_WRITEBACK_NAMESPACE);
}

/** The note op_keys already applied to this order, op_key => ISO timestamp. */
function shopify_note_ops(int $shopId, string $orderGid): array {
	return shopify_order_link_flags($shopId, $orderGid, SHOPIFY_NOTE_OPS_NAMESPACE);
}

/**
 * One namespace of shopify_order_links.flags, empty when the link row or the namespace is
 * not there.
 *
 * @return array<string, string>
 */
function shopify_order_link_flags(int $shopId, string $orderGid, string $namespace): array {
	$gid = db_escape_string(shopify_gid($orderGid, 'Order'));
	$shopId = intval($shopId);
	$qtxt = "SELECT flags FROM shopify_order_links WHERE shop_id = $shopId AND order_gid = '$gid'";
	$row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$row) {
		return array();
	}
	$flags = json_decode((string)$row['flags'], true);
	$section = is_array($flags) ? if_array($flags, $namespace) : array();
	$values = array();
	foreach ((is_array($section) ? $section : array()) as $name => $value) {
		if (is_scalar($value)) {
			$values[(string)$name] = (string)$value;
		}
	}
	return $values;
}

/**
 * Records a stage's terminal result. Only order.move may be abandoned; a tags, references
 * or note stage that fails permanently records nothing, so saldi-behandlet stays unset and
 * the order stays on the error list.
 *
 * @return bool false when the order has no link row to record against
 */
function shopify_writeback_mark(int $shopId, string $orderGid, string $stage, string $result): bool {
	if (!array_key_exists($stage, shopify_writeback_stages())) {
		throw new InvalidArgumentException("unknown writeback stage: $stage");
	}
	if ($result !== 'done' && !($result === 'abandoned' && $stage === 'order.move')) {
		throw new InvalidArgumentException("stage $stage cannot be recorded as $result");
	}
	return shopify_order_link_flag_set($shopId, $orderGid, SHOPIFY_WRITEBACK_NAMESPACE, $stage, $result);
}

/** Records that $opKey's note append has been applied to this order. */
function shopify_note_op_record(int $shopId, string $orderGid, string $opKey): bool {
	if (trim($opKey) === '') {
		throw new InvalidArgumentException('shopify_note_op_record: op_key must not be empty');
	}
	return shopify_order_link_flag_set($shopId, $orderGid, SHOPIFY_NOTE_OPS_NAMESPACE, $opKey, gmdate('c'));
}

/**
 * Writes one key inside one flags namespace, leaving the rest of the document alone.
 *
 * @return bool false when the order has no link row
 */
function shopify_order_link_flag_set(int $shopId, string $orderGid, string $namespace, string $key, string $value): bool {
	$gid = db_escape_string(shopify_gid($orderGid, 'Order'));
	$shopId = intval($shopId);
	$where = "shop_id = $shopId AND order_gid = '$gid'";
	if (!db_fetch_array(db_select("SELECT id FROM shopify_order_links WHERE $where", __FILE__ . " linje " . __LINE__))) {
		return false;
	}
	$ns = db_escape_string($namespace);
	$json = db_escape_string(json_encode(array($key => $value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	// jsonb_set creates only the last element of its path, so a two-element path would be
	// dropped without a sound on the first checkpoint, while flags is still '{}'. Merging the
	// one key into the namespace object instead works whether or not the namespace is there,
	// and leaves the other namespace and the namespace's other keys as they were.
	$qtxt = "UPDATE shopify_order_links"
		. " SET flags = jsonb_set(coalesce(flags, '{}'::jsonb), ARRAY['$ns'],"
		. " coalesce(flags -> '$ns', '{}'::jsonb) || '$json'::jsonb, true), updated_at = now()"
		. " WHERE $where";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	return true;
}

/**
 * Overrides how stages reach the queue, or restores the default with null. S09b (WP02)
 * owns shopify_queue_enqueue(); until it lands only the tests supply an enqueuer.
 */
function shopify_set_writeback_enqueuer(?callable $enqueuer): void {
	$GLOBALS['shopify_writeback_enqueuer'] = $enqueuer;
}

function shopify_writeback_enqueuer(): callable {
	$enqueuer = $GLOBALS['shopify_writeback_enqueuer'] ?? null;
	if (is_callable($enqueuer)) {
		return $enqueuer;
	}
	if (function_exists('shopify_queue_enqueue')) {
		return 'shopify_queue_enqueue';
	}
	throw new ShopifyWritebackError('no Shopify queue available (shopify_queue_enqueue)');
}

/**
 * WP09 step 9: queue the whole writeback for an invoiced web order. Every stage is enqueued
 * with the dedupe key <shop_key>:<order gid>, so enqueuing the same order twice adds
 * nothing. The checkpoints, not the priorities, are what hold the stages in order.
 */
function shopify_enqueue_writeback(int $shopId, int $ordreId): void {
	$link = shopify_order_link_for_ordre($shopId, $ordreId);
	if (!$link) {
		throw new ShopifyWritebackError("order $ordreId has no Shopify order link on shop $shopId");
	}
	$dedupe = shopify_shop_key($shopId) . ':' . $link['order_gid'];
	$enqueue = shopify_writeback_enqueuer();
	foreach (shopify_writeback_jobs($dedupe, $ordreId, $link['order_gid']) as $job) {
		$enqueue($job['type'], $shopId, $job['dedupe_key'], $job['priority'], $job['payload']);
	}
}

/**
 * The jobs shopify_enqueue_writeback() hands the queue, in the order they are enqueued.
 *
 * @return array<int, array{
 *   type: string,  The stage name, which is also the job type.
 *   dedupe_key: string,  <shop_key>:<order gid>, the same for every stage of one order.
 *   priority: int,
 *   payload: array{ordre_id: int, order_gid: string},
 * }>
 */
function shopify_writeback_jobs(string $dedupeKey, int $ordreId, string $orderGid): array {
	$payload = array('ordre_id' => $ordreId, 'order_gid' => $orderGid);
	$jobs = array();
	foreach (shopify_writeback_stages() as $stage => $priority) {
		$jobs[] = array('type' => $stage, 'dedupe_key' => $dedupeKey, 'priority' => $priority, 'payload' => $payload);
	}
	return $jobs;
}

/**
 * The web order link for a Saldi order.
 *
 * @return array{id: int, order_gid: string}|null null when the order has no linked web order
 */
function shopify_order_link_for_ordre(int $shopId, int $ordreId): ?array {
	$shopId = intval($shopId);
	$ordreId = intval($ordreId);
	$qtxt = "SELECT id, order_gid FROM shopify_order_links"
		. " WHERE shop_id = $shopId AND ordre_id = $ordreId AND direction = 'web' AND order_gid IS NOT NULL";
	$row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$row) {
		return null;
	}
	return array('id' => (int)$row['id'], 'order_gid' => (string)$row['order_gid']);
}

function shopify_shop_key(int $shopId): string {
	$shopId = intval($shopId);
	$qtxt = "SELECT shop_key FROM shopify_shops WHERE id = $shopId";
	$row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$row) {
		throw new ShopifyWritebackError("unknown Shopify shop: $shopId");
	}
	return (string)$row['shop_key'];
}
