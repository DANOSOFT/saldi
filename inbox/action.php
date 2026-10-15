<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- inbox/action.php --- ai/mcp-assistant --- 2026.10.03
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// JSON endpoint behind the shell's approval modal and inbox badge (SPEC 7.1, 7.2).
// POST only, same origin, logged-in tenant user (bruger_id > 0), CSRF header
// X-Saldi-Csrf, and the tenant hash the shell was loaded with. Body:
//   {op: "preview"|"approve"|"reject"|"undo"|"cancel"|"count"|"banner"|"banner_seen", item_id, digest?, tenant_hash, note?}
// "banner" returns the newest open interrupt item this user has not been shown yet (or null) for the
// shell's quiet banner (SPEC-companion C5.1); "banner_seen" records assist_events interrupt_shown for it.
// The decision itself is enforced again by SaldiInboxService (human, digest, tenant,
// permission); this file only translates HTTP to service calls and errors to Danish.
// Also used as a function library by inbox/index.php (define SALDI_INBOX_ACTION_LIBRARY
// before requiring it), so the page and the modal share one decision path.
//
// 20261002 CL/LH Created.
// 20261002 CDX/LH Accept cancellation and return structured conflict and CSRF details.
// 20261002 CL/LH Shared request-origin helper; inbox page view state and paging helpers.
// 20261002 CL/LH Companion C5.1: 'banner'/'banner_seen' ops for the shell banner; the Åbne tab hides silent
//                items (new 'silent' tab) and lists interrupt items first.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261006 CL/LH "Afgjort" tab spanning every decided status, with its own count.
// 20261007 CL/LH The page's visibility predicate leaves credit-note drafts out for users with revenue hidden,
//                as SaldiInboxService does, so the Åbne/Stille/Afgjort counters match the lists.

require_once __DIR__ . '/../includes/runtime/bootstrap.php';
require_once __DIR__ . '/../includes/automation/SaldiInboxService.php';
require_once __DIR__ . '/../includes/automation/inboxCsrf.php';
require_once __DIR__ . '/../includes/automation/approvalCard.php';

/**
 * Run one inbox operation for a human session context. Throws SaldiRuntimeError.
 *
 * @return array{item?: array, count?: int}
 */
function saldi_inbox_perform(SaldiInboxService $inbox, SaldiTenantContext $ctx, string $op, int $itemId, string $digest, string $tenantHash, ?string $note = null): array
{
	if (!hash_equals($ctx->tenantHash(), $tenantHash)) {
		// Checked for every op, previews included: an item id from another company means nothing here.
		throw new SaldiRuntimeError('CONFLICT', 'Company changed', ['reason' => 'tenant_changed']);
	}
	switch ($op) {
		case 'count':
			return ['count' => $inbox->countOpen($ctx)];
		case 'preview':
			return ['item' => $inbox->preview($ctx, $itemId)];
		case 'approve':
		case 'reject':
			return ['item' => $inbox->decide($ctx, $itemId, $op, $digest, $tenantHash, $note)];
		case 'undo':
			return ['item' => $inbox->undo($ctx, $itemId, $tenantHash)];
		case 'cancel':
			return ['item' => $inbox->cancel($ctx, $itemId)];
	}
	throw new SaldiRuntimeError('VALIDATION_ERROR', 'Unknown operation');
}

/** Same-origin check on Origin, falling back to the Referer's origin; absent both -> refuse. */
function saldi_inbox_same_origin(array $server): bool
{
	$own = saldi_runtime_request_origin($server);
	$source = (string)($server['HTTP_ORIGIN'] ?? '');
	if ($source === '') {
		$source = (string)($server['HTTP_REFERER'] ?? '');
	}
	$origin = $source === '' ? null : saldi_runtime_origin($source);
	return $own !== null && $origin !== null && hash_equals($own, $origin);
}

/**
 * The whole JSON controller, free of superglobal reads so tests can drive it.
 *
 * @param array $server     $_SERVER-like: REQUEST_METHOD, HTTP_HOST, HTTP_ORIGIN, HTTP_X_SALDI_CSRF, ...
 * @param string $rawBody   request body
 * @param int $brugerId     tenant user id from online.php (<= 0 for revisor/admin sessions)
 * @param callable $session fn(): array{ctx: SaldiTenantContext, db: SaldiDb}; called only after the checks
 * @param int $languageId   viewer language for messages before a context exists
 * @return array{status:int, body:array}
 */
function saldi_inbox_action_handle(array $server, string $rawBody, int $brugerId, callable $session, int $languageId = 1): array
{
	$fail = static function (int $status, string $code, string $message, array $detail = []): array {
		return ['status' => $status, 'body' => ['ok' => false, 'error' => ['code' => $code, 'message_da' => $message, 'detail' => $detail]]];
	};
	if (($server['REQUEST_METHOD'] ?? '') !== 'POST') {
		return $fail(405, 'VALIDATION_ERROR', 'POST required');
	}
	if (!saldi_inbox_same_origin($server)) {
		return $fail(403, 'PERMISSION_DENIED', saldi_ui_text(null, $languageId, 5731, 'Du har ikke adgang til denne handling.'));
	}
	if ($brugerId <= 0) {
		return $fail(403, 'PERMISSION_DENIED', saldi_ui_text(null, $languageId, 5811, 'Opgaver kræver et brugerlogin.'));
	}
	if (!saldi_inbox_csrf_check(isset($server['HTTP_X_SALDI_CSRF']) ? (string)$server['HTTP_X_SALDI_CSRF'] : null)) {
		return $fail(403, 'PERMISSION_DENIED', saldi_ui_text(null, $languageId, 5888, 'Siden er udløbet. Genindlæs den, og prøv igen.'), ['reason' => 'csrf']);
	}
	$body = json_decode($rawBody, true);
	$op = is_array($body) && is_string($body['op'] ?? null) ? $body['op'] : '';
	$itemId = is_array($body) && is_int($body['item_id'] ?? null) ? $body['item_id'] : 0;
	$tenantHash = is_array($body) && is_string($body['tenant_hash'] ?? null) ? $body['tenant_hash'] : '';
	$digest = is_array($body) && is_string($body['digest'] ?? null) ? $body['digest'] : '';
	$note = is_array($body) && is_string($body['note'] ?? null) ? trim($body['note']) : '';
	$invalid = !in_array($op, ['preview', 'approve', 'reject', 'undo', 'cancel', 'count', 'banner', 'banner_seen'], true)
		|| !preg_match('/^[a-f0-9]{32}$/D', $tenantHash)
		|| (!in_array($op, ['count', 'banner'], true) && $itemId <= 0)
		|| (in_array($op, ['approve', 'reject'], true) && !preg_match('/^[a-f0-9]{64}$/D', $digest))
		|| mb_strlen($note) > 255;
	if ($invalid) {
		return $fail(400, 'VALIDATION_ERROR', saldi_ui_text(null, $languageId, 5732, 'Kontrollér de indtastede oplysninger.'));
	}
	$db = null;
	$lang = $languageId;
	try {
		$open = $session();
		$ctx = $open['ctx'];
		$db = $open['db'];
		$lang = $ctx->languageId;
		$inbox = new SaldiInboxService($db, SaldiKindRegistry::default());
		if ($op === 'banner' || $op === 'banner_seen') {
			return ['status' => 200, 'body' => ['ok' => true] + saldi_inbox_banner_op($db, $inbox, $ctx, $op, $itemId, $tenantHash)];
		}
		$result = saldi_inbox_perform($inbox, $ctx, $op, $itemId, $digest, $tenantHash, $note === '' ? null : $note);
		if (isset($result['item'])) {
			$result['item'] = SaldiInboxService::publicItem($result['item']);
		}
		$response = ['ok' => true] + $result;
		if (isset($result['item'])) {
			$item = $result['item'];
			// The modal shows the server's own card markup before and after the decision.
			$response['html'] = saldi_approval_card_html($item, ['db' => $db, 'lang' => $lang, 'mode' => 'modal', 'heading_level' => 3]);
			if ($op !== 'preview') {
				$response['receipt'] = saldi_approval_receipt($item, $db, $lang);
			}
		}
		return ['status' => 200, 'body' => $response];
	} catch (Throwable $e) {
		if (!$e instanceof SaldiRuntimeError) {
			error_log('inbox/action.php: ' . get_class($e) . ' ' . $e->getMessage());
		}
		$error = saldi_approval_error($e, $db, $lang);
		return ['status' => $e instanceof SaldiRuntimeError ? $e->httpStatus() : 503, 'body' => ['ok' => false, 'error' => $error]];
	}
}

/**
 * The shell banner ops. 'banner': the newest open interrupt item visible to this user without an
 * interrupt_shown event of theirs, as {banner: {item_id, title, texts} | null}. 'banner_seen': records
 * interrupt_shown once per user and item, as {recorded: bool}; only for a visible open interrupt item.
 *
 * @return array{banner?: ?array, recorded?: bool}
 */
function saldi_inbox_banner_op(SaldiDb $db, SaldiInboxService $inbox, SaldiTenantContext $ctx, string $op, int $itemId, string $tenantHash): array
{
	if (!hash_equals($ctx->tenantHash(), $tenantHash)) {
		throw new SaldiRuntimeError('CONFLICT', 'Company changed', ['reason' => 'tenant_changed']);
	}
	if (!$db->hasTable('assist_events')) {
		return $op === 'banner' ? ['banner' => null] : ['recorded' => false];
	}
	if ($op === 'banner_seen') {
		$item = $inbox->get($ctx, $itemId);
		if ($item['status'] !== 'open' || ($item['attention'] ?? '') !== 'interrupt') {
			return ['recorded' => false];
		}
		return ['recorded' => $db->transaction(static function () use ($db, $ctx, $itemId): bool {
			$db->lock('saldi_interrupt_shown:' . $ctx->userId . ':' . $itemId);
			if ($db->value("SELECT 1 FROM assist_events WHERE event = 'interrupt_shown' AND user_id = ? AND subject = ?", [$ctx->userId, 'item:' . $itemId]) !== null) {
				return false;
			}
			$db->insert('assist_events', ['created_at' => gmdate('Y-m-d H:i:s'), 'user_id' => $ctx->userId, 'event' => 'interrupt_shown', 'subject' => 'item:' . $itemId]);
			return true;
		})];
	}
	[$where, $params] = saldi_inbox_visible_where($db, $ctx);
	$ids = array_map(static fn(array $r): int => (int)$r['id'], $db->all("SELECT id FROM inbox_items WHERE status = 'open' AND attention = 'interrupt' AND " . $where . ' ORDER BY id DESC LIMIT 20', $params));
	if (!$ids) {
		return ['banner' => null];
	}
	$seen = array_map(static fn(array $r): string => (string)$r['subject'], $db->all("SELECT subject FROM assist_events WHERE event = 'interrupt_shown' AND user_id = ? AND subject IN (" . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([$ctx->userId], array_map(static fn(int $id): string => 'item:' . $id, $ids))));
	foreach ($ids as $id) {
		if (in_array('item:' . $id, $seen, true)) {
			continue;
		}
		try {
			$item = $inbox->get($ctx, $id);
		} catch (SaldiRuntimeError $e) {
			if ($e->errorCode === 'NOT_FOUND') {
				continue;
			}
			throw $e;
		}
		$lang = $ctx->languageId;
		return ['banner' => [
			'item_id' => $id,
			'title' => mb_substr((string)($item['card']['title'] ?? $item['title']), 0, 160),
			'texts' => [
				'important' => saldi_ui_text($db, $lang, 5958, 'Vigtigt'),
				'open' => saldi_ui_text($db, $lang, 6090, 'Åbn'),
				'close' => saldi_ui_text($db, $lang, 2172, 'Luk'),
			],
		]];
	}
	return ['banner' => null];
}

/**
 * The visibility predicate of SaldiInboxService (owner, or an assignee permission the user holds; no
 * credit-note drafts while revenue is hidden), for the attention-aware queries and tab counters below
 * that the service's list() cannot express. Keep it in step with SaldiInboxService::predicate(): a
 * counter that counts more than the list shows tells the viewer that hidden items exist. Every id found
 * with it is loaded again through SaldiInboxService::get(), which applies the service's own predicate.
 *
 * @return array{0:string, 1:array}
 */
function saldi_inbox_visible_where(SaldiDb $db, SaldiTenantContext $ctx): array
{
	$permissions = SaldiPermissionService::effective($ctx, $db);
	$clause = $permissions ? "(visibility = 'permission' AND assignee_permission IN (" . implode(',', array_fill(0, count($permissions), '?')) . '))' : '1 = 0';
	$revenue = SaldiPermissionService::revenueHidden($ctx, $db) ? " AND kind <> 'credit_note.draft'" : '';
	return ['((owner_user_id = ? AND ? > 0) OR ' . $clause . ')' . $revenue, array_merge([$ctx->userId, $ctx->userId], $permissions)];
}

/**
 * Inbox page tabs (inbox/index.php) and the item statuses each one lists. 'open' (Åbne) and 'silent'
 * (Stille) split the open items by attention: silent items only show under Stille.
 */
const SALDI_INBOX_TABS = ['open' => ['open'], 'silent' => ['open'], 'decided' => ['applied', 'undone', 'rejected', 'cancelled', 'expired'], 'done' => ['applied', 'undone'], 'rejected' => ['rejected', 'cancelled'], 'expired' => ['expired']];

/** Decided items visible to the viewer (the "Afgjort" tab counter), one query. */
function saldi_inbox_decided_count(SaldiDb $db, SaldiTenantContext $ctx): int
{
	[$where, $params] = saldi_inbox_visible_where($db, $ctx);
	return (int)$db->value("SELECT COUNT(*) FROM inbox_items WHERE status <> 'open' AND " . $where, $params);
}

/**
 * Open items per attention tab, as the page's tab counters: Åbne without silent items, Stille only them.
 *
 * @return array{open:int, silent:int}
 */
function saldi_inbox_tab_counts(SaldiDb $db, SaldiTenantContext $ctx): array
{
	[$where, $params] = saldi_inbox_visible_where($db, $ctx);
	$counts = ['open' => 0, 'silent' => 0];
	foreach ($db->all("SELECT CASE WHEN attention = 'silent' THEN 'silent' ELSE 'open' END AS tab, COUNT(*) AS n FROM inbox_items WHERE status = 'open' AND " . $where . " GROUP BY CASE WHEN attention = 'silent' THEN 'silent' ELSE 'open' END", $params) as $row) {
		$counts[$row['tab']] = (int)$row['n'];
	}
	return $counts;
}

/**
 * Whitelisted inbox page view state from a query array (GET or the posted 'return' field).
 * limit is the page size (25..100 in steps of 25); offset skips that many newer items.
 *
 * @return array{tab:string, kind:string, item:int, limit:int, offset:int}
 */
function saldi_inbox_view_state(array $query, array $kinds): array
{
	$tab = is_string($query['tab'] ?? null) && isset(SALDI_INBOX_TABS[$query['tab']]) ? $query['tab'] : 'open';
	$kind = is_string($query['kind'] ?? null) && in_array($query['kind'], $kinds, true) ? $query['kind'] : '';
	$item = isset($query['item']) && is_string($query['item']) && preg_match('/^[1-9][0-9]{0,9}$/D', $query['item']) ? (int)$query['item'] : 0;
	$limit = isset($query['limit']) && is_string($query['limit']) && preg_match('/^[0-9]{1,3}$/D', $query['limit']) ? (int)$query['limit'] : 25;
	$offset = isset($query['offset']) && is_string($query['offset']) && preg_match('/^[0-9]{1,6}$/D', $query['offset']) ? (int)$query['offset'] : 0;
	return ['tab' => $tab, 'kind' => $kind, 'item' => $item, 'limit' => max(25, min(100, $limit - $limit % 25)), 'offset' => $offset];
}

/** Query string for an inbox page view state (defaults left out). */
function saldi_inbox_view_query(array $state, array $override = []): string
{
	$state = array_merge($state, $override);
	$query = [];
	if ($state['item'] > 0) {
		$query['item'] = $state['item'];
	}
	if ($state['tab'] !== 'open') {
		$query['tab'] = $state['tab'];
	}
	if ($state['kind'] !== '') {
		$query['kind'] = $state['kind'];
	}
	if ($state['limit'] !== 25) {
		$query['limit'] = $state['limit'];
	}
	if (($state['offset'] ?? 0) > 0) {
		$query['offset'] = $state['offset'];
	}
	return http_build_query($query);
}

/**
 * State override behind 'Vis flere': the list first grows by 25 up to 100 items, then moves on to the
 * next 100 older items.
 *
 * @return array{limit:int, offset:int}
 */
function saldi_inbox_next_page(array $state): array
{
	return $state['limit'] < 100 ? ['limit' => $state['limit'] + 25, 'offset' => $state['offset']] : ['limit' => 100, 'offset' => $state['offset'] + 100];
}

/**
 * One page of a tab: a single newest-first query over all of the tab's statuses, plus a one-row probe
 * past the page to tell whether older items exist. With $db, the Åbne tab leaves silent items out and
 * lists interrupt items first, and the Stille tab lists only silent open items (SPEC-companion C5.1).
 *
 * @return array{items: list<array>, has_more: bool}
 */
function saldi_inbox_page_items(SaldiInboxService $inbox, SaldiTenantContext $ctx, array $state, ?SaldiDb $db = null): array
{
	if ($db !== null && in_array($state['tab'], ['open', 'silent'], true)) {
		[$where, $params] = saldi_inbox_visible_where($db, $ctx);
		$where .= " AND status = 'open' AND attention " . ($state['tab'] === 'silent' ? '=' : '<>') . " 'silent'";
		if ($state['kind'] !== '') {
			$where .= ' AND kind = ?';
			$params[] = $state['kind'];
		}
		$order = $state['tab'] === 'open' ? "CASE WHEN attention = 'interrupt' THEN 0 ELSE 1 END, id DESC" : 'id DESC';
		$limit = max(1, min(100, (int)$state['limit']));
		$rows = $db->all('SELECT id FROM inbox_items WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . ($limit + 1) . ' OFFSET ' . max(0, (int)$state['offset']), $params);
		$items = [];
		foreach (array_slice($rows, 0, $limit) as $row) {
			try {
				$items[] = $inbox->get($ctx, (int)$row['id']);
			} catch (SaldiRuntimeError $e) {
				if ($e->errorCode !== 'NOT_FOUND') {
					throw $e;
				}
			}
		}
		return ['items' => $items, 'has_more' => count($rows) > $limit];
	}
	$filters = ['status' => SALDI_INBOX_TABS[$state['tab']], 'kind' => $state['kind']];
	$items = $inbox->list($ctx, $filters + ['limit' => $state['limit'], 'offset' => $state['offset']]);
	$hasMore = count($items) === $state['limit'] && $inbox->list($ctx, $filters + ['limit' => 1, 'offset' => $state['offset'] + $state['limit']]) !== [];
	return ['items' => $items, 'has_more' => $hasMore];
}

if (!defined('SALDI_INBOX_ACTION_LIBRARY')) {
	// Entry point. The legacy includes run at file scope (they rely on globals).
	ob_start();
	@session_start();
	$s_id = session_id();
	$modulnr = 0;
	$title = 'Opgaver';
	include __DIR__ . '/../includes/connect.php';
	$inboxSession = db_fetch_array(db_select("select brugernavn from online where session_id = '" . db_escape_string($s_id) . "' order by logtime desc limit 1", __FILE__ . ' linje ' . __LINE__));
	$inboxStatus = 401;
	$inboxBody = ['ok' => false, 'error' => ['code' => 'UNAUTHENTICATED', 'message_da' => SaldiText::get(null, 5730, 1, 'Adgangen er udløbet eller ugyldig.')]];
	if ($inboxSession && !empty($inboxSession['brugernavn'])) {
		include __DIR__ . '/../includes/online.php';
		/**
		 * Injected by ../includes/online.php, included above:
		 * @var int|string $bruger_id
		 * @var int|string $sprog_id
		 */
		$inboxResult = saldi_inbox_action_handle($_SERVER, (string)file_get_contents('php://input'), (int)($bruger_id ?? 0), static function (): array {
			$open = saldi_runtime_context_for_session();
			saldi_runtime_ensure_schema($open['db']);
			return $open;
		}, (int)($sprog_id ?? 1));
		$inboxStatus = $inboxResult['status'];
		$inboxBody = $inboxResult['body'];
	}
	ob_end_clean();
	http_response_code($inboxStatus);
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	header('X-Content-Type-Options: nosniff');
	echo json_encode($inboxBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}
