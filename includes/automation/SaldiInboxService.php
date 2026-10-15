<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiInboxService.php --- ver 5.0.0 --- 2026.10.10
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
// Visibility, digests and human decisions coordinated in atomic transactions.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Enforce originating-rule cancellation, stable conflicts and decision metadata.
// 20261002 CL/LH request_id idempotency checked before validation; a reused request_id for another proposal fails.
//                list() accepts several statuses.
// 20261002 CDX/LH Add companion shared foundation contracts.
// 20261002 CDX/LH Enforce recipient interrupt budgets atomically on creation.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261004 CDX/LH Release bank staging reservations on terminal decisions and worker expiry.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.
// 20261008 CL/LH Add mobile inbox views, bound idempotent decisions, undo windows and interrupt delivery.
// 20261010 CDX/LH Persist failed mobile approval audit entries after the outer rollback.

declare(strict_types=1);

require_once __DIR__ . '/SaldiKindRegistry.php';
require_once __DIR__ . '/SaldiAttention.php';
require_once __DIR__ . '/SaldiInterruptNotifier.php';
require_once __DIR__ . '/approvalCard.php';
require_once __DIR__ . '/../../restapi/core/Rights.php';
final class SaldiInboxService
{
	public function __construct(private SaldiDb $db, private SaldiKindRegistry $kinds)
	{
	}
	private function visible(SaldiTenantContext $ctx, array $row): bool
	{
		[$where, $params] = $this->predicate($ctx);
		return $this->db->value('SELECT 1 FROM inbox_items WHERE id = ? AND ' . $where, array_merge([(int)$row['id']], $params)) !== null;
	}
	private function row(SaldiTenantContext $ctx, int $id, bool $lock = false): array
	{
		[$where, $params] = $this->predicate($ctx);
		$row = $this->db->one('SELECT * FROM inbox_items WHERE id = ? AND ' . $where . ($lock ? ' FOR UPDATE' : ''), array_merge([$id], $params));
		if (!$row) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Item not found');
		}
		return $row;
	}
	private function decode(array $row): array
	{
		foreach (['payload', 'evidence', 'result'] as $field) {
			$row[$field] = $row[$field] === null ? null : json_decode($row[$field], true, 512, JSON_THROW_ON_ERROR);
		}
		return $row;
	}
	private function shape(SaldiTenantContext $ctx, array $row, bool $deduplicated = false): array
	{
		$row = $this->decode($row);
		$item = array_intersect_key($row, array_flip(['kind', 'origin', 'status', 'title', 'payload', 'evidence', 'created_by', 'attention', 'result', 'decision_note']));
		$item += ['id' => (int)$row['id'], 'revision' => (int)$row['revision'], 'digest' => $row['content_digest'], 'subject' => ['type' => $row['subject_type'], 'id' => $row['subject_id']], 'owner_user_id' => $row['owner_user_id'] === null ? null : (int)$row['owner_user_id'], 'decided_by' => $row['decided_by'] === null ? null : (int)$row['decided_by']];
		$item['decided_by_name'] = $item['decided_by'] === null ? null : ($item['decided_by'] < 0 ? SaldiText::get($this->db, 6430, $ctx->languageId, 'Revisor') : $this->db->value('SELECT brugernavn FROM brugere WHERE id = ?', [$item['decided_by']]));
		foreach (['created_at', 'expires_at', 'due_at', 'decided_at'] as $field) {
			$item[$field] = $row[$field] === null ? null : str_replace(' ', 'T', $row[$field]) . 'Z';
		}
		$kind = $this->kinds->get($row['kind']);
		$item['can_undo'] = $row['status'] === 'applied' && $ctx->isHuman() && SaldiPermissionService::allows($ctx, $this->db, $kind->decidePermission($row)) && $kind->canUndo($ctx, $this->db, $row);
		$item['deduplicated'] = $deduplicated;
		$item['tenant_hash'] = $ctx->tenantHash();
		$item['screen'] = SaldiScreens::link('inbox_item', (int)$row['id']);
		$item['card'] = $kind->render($ctx, $this->db, $item);
		return $item;
	}
	private function log(SaldiTenantContext $ctx, array $row, string $event, string $suffix, array $detail = []): void
	{
		SaldiDecisionLog::append($this->db, ['event' => $event, 'inbox_item_id' => (int)$row['id'], 'kind' => $row['kind'], 'subject_type' => $row['subject_type'], 'subject_id' => $row['subject_id'], 'actor' => $ctx->principal, 'on_behalf_of' => $ctx->onBehalfOf(), 'approved_by' => $ctx->isHuman() && $event !== 'proposed' ? $ctx->userId : null, 'grant_id' => $row['grant_id'], 'idempotency_key' => 'inbox:' . $row['id'] . ':' . $suffix, 'detail' => $detail]);
	}
	/** @return array Inbox item with attachment custody reduced to its public document id. */
	public static function publicItem(array $item): array
	{
		if (is_array($item['result'] ?? null)) {
			$item['result'] = self::redactDocumentReceipt($item['result']);
		}
		return $item;
	}
	/** @return array Result with all nested attachment paths and pool snapshots removed. */
	private static function redactDocumentReceipt(array $result): array
	{
		foreach ($result as $key => $value) {
			if (in_array($key, ['from', 'to', 'pool_row'], true)) {
				unset($result[$key]);
			} elseif ($key === 'document' && is_array($value)) {
				$result[$key] = ['document_id' => (int)($value['document_id'] ?? 0)];
			} elseif (is_array($value)) {
				$result[$key] = self::redactDocumentReceipt($value);
			}
		}
		return $result;
	}
	/** Content digest over what a human approves: kind, subject and normalised payload. */
	private function digest(string $name, array $n): string
	{
		return hash('sha256', saldi_runtime_json(['kind' => $name, 'subject_type' => $n['subject_type'], 'subject_id' => $n['subject_id'], 'payload' => $n['payload']]));
	}
	private function reusedRequest(): SaldiRuntimeError
	{
		return new SaldiRuntimeError('VALIDATION_ERROR', 'request_id already used for a different proposal', ['reason' => 'request_id_reused', 'fields' => ['$.request_id']]);
	}
	/** @return array Item with translated card and visibility-enforced deduplication. */
	public function create(SaldiTenantContext $ctx, string $name, array $input, array $opts = []): array
	{
		if (($name === 'routine.finding' && !$ctx->isRule()) || ($ctx->isRule() && !in_array($name, ['routine.finding', 'rule.posting'], true)) || (!$ctx->isHuman() && !$ctx->isAgent() && !$ctx->isRule())) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Invalid proposer');
		}
		$kind = $this->kinds->get($name);
		SaldiPermissionService::require($ctx, $this->db, in_array($name, ['party.create', 'settlement.draft'], true) ? $kind->decidePermission(['payload' => $input]) : $kind->proposePermission());
		if ($name === 'journal.draft_entry') {
			// Only stored approval payloads may pin a fiscal year.
			unset($input['fiscal_year'], $input['currency']);
		}
		$request = $opts['request_id'] ?? null;
		if ($request !== null && (!is_string($request) || strlen($request) < 1 || strlen($request) > 64)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid request id');
		}
		$requestKey = $request === null ? null : ($ctx->grantId ? 'g' . $ctx->grantId : 'u' . $ctx->userId) . ':' . $request;
		// Idempotency before validation: a retry of a request whose item was already decided returns that
		// item, even when the input no longer validates (memory retracted, kladde posted, period closed).
		$existing = $requestKey === null ? null : $this->db->one('SELECT * FROM inbox_items WHERE request_key = ?', [$requestKey]);
		if ($existing !== null) {
			if (!$this->visible($ctx, $existing)) {
				throw new SaldiRuntimeError('CONFLICT', 'Duplicate item unavailable', ['reason' => 'not_open']);
			}
			if ($existing['kind'] !== $name) {
				throw $this->reusedRequest();
			}
			try {
				$n = $kind->validate($ctx, $this->db, $input);
			} catch (SaldiRuntimeError $e) {
				return $this->shape($ctx, $existing, true);
			}
			if ($name === 'sales_order.draft') {
				// Resolved promotion prices belong to the original proposal, including retries.
				$stored = json_decode($existing['payload'], true, 512, JSON_THROW_ON_ERROR);
				$identity = $n['payload'];
				$original = $stored;
				foreach ($identity['lines'] as &$line) {
					unset($line['pricing']);
				}
				unset($line);
				foreach ($original['lines'] as &$line) {
					unset($line['pricing']);
				}
				unset($line);
				if (saldi_runtime_json($identity) === saldi_runtime_json($original)) {
					$n['payload'] = $stored;
				}
			}
			if (!hash_equals($existing['content_digest'], $this->digest($name, $n))) {
				throw $this->reusedRequest();
			}
			return $this->shape($ctx, $existing, true);
		}
		$n = $kind->validate($ctx, $this->db, $input);
		if ($ctx->userId < 0 && ($ctx->isHuman() || $ctx->isAgent()) && $n['visibility'] === 'owner' && $n['owner_user_id'] === null) {
			$n['owner_user_id'] = $ctx->userId;
		}
		$evidence = $opts['evidence'] ?? [];
		if (!is_array($evidence) || (isset($evidence['explanation']) && (!is_string($evidence['explanation']) || mb_strlen($evidence['explanation']) > 500)) || (isset($evidence['tainted']) && !is_bool($evidence['tainted'])) || (isset($evidence['citations']) && (!is_array($evidence['citations']) || count($evidence['citations']) > 10))) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid evidence');
		}
		$evidence = ['explanation' => $evidence['explanation'] ?? '', 'citations' => $evidence['citations'] ?? [], 'tainted' => $evidence['tainted'] ?? false];
		$origin = $opts['origin'] ?? ($ctx->isAgent() ? 'agent' : ($ctx->isRule() ? 'rule' : 'user'));
		$attention = $opts['attention'] ?? 'ambient';
		if (!in_array($origin, ['agent', 'rule', 'ai', 'system', 'user'], true) || !in_array($attention, ['silent', 'receipt', 'ambient', 'interrupt'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid origin or attention');
		}
		$stamp = static function (mixed $v): ?string {
			if ($v === null) {
				return null;
			}
			if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}Z?$/D', $v)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid timestamp');
			}
			$plain = str_replace('T', ' ', rtrim($v, 'Z'));
			$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $plain, new DateTimeZone('UTC'));
			if (!$date || $date->format('Y-m-d H:i:s') !== $plain) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid timestamp');
			}
			return $plain;
		};
		$now = gmdate('Y-m-d H:i:s');
		$row = ['origin' => $origin, 'kind' => $name, 'revision' => 1, 'content_digest' => $this->digest($name, $n),
			'subject_type' => $n['subject_type'], 'subject_id' => $n['subject_id'], 'title' => $n['title'], 'payload' => saldi_runtime_json($n['payload']), 'evidence' => saldi_runtime_json($evidence), 'proposed_rev' => $n['proposed_rev'], 'status' => 'open', 'attention' => $attention,
			'visibility' => $n['visibility'], 'assignee_permission' => $n['assignee_permission'], 'owner_user_id' => $n['owner_user_id'], 'created_by' => $ctx->principal, 'grant_id' => $ctx->grantId,
			'request_key' => $requestKey, 'dedupe_key' => $n['dedupe_key'], 'open_key' => $n['dedupe_key'] === null ? null : $name . ':' . $n['dedupe_key'],
			'due_at' => $stamp($opts['due_at'] ?? null), 'expires_at' => $stamp($opts['expires_at'] ?? gmdate('Y-m-d H:i:s', time() + ($ctx->isRule() ? 7 : 14) * 86400)), 'created_at' => $now, 'updated_at' => $now];
		$item = $this->db->transaction(function () use ($ctx, $row): array {
			if ($row['attention'] === 'interrupt') {
				$row['attention'] = SaldiAttention::decide($this->db, $row['owner_user_id'], $row['assignee_permission'], 'interrupt');
				if ($row['attention'] !== 'interrupt') {
					$evidence = json_decode($row['evidence'], true, 512, JSON_THROW_ON_ERROR);
					$evidence['attention_downgraded'] = true;
					$row['evidence'] = saldi_runtime_json($evidence);
				}
			}
			$id = $this->db->insertIgnore('inbox_items', $row, ['request_key', 'open_key']);
			if ($id === null) {
				$existing = $this->db->one('SELECT * FROM inbox_items WHERE request_key = ? OR open_key = ? ORDER BY id LIMIT 1', [$row['request_key'], $row['open_key']]);
				if (!$existing || !$this->visible($ctx, $existing)) {
					throw new SaldiRuntimeError('CONFLICT', 'Duplicate item unavailable', ['reason' => 'not_open']);
				}
				// A concurrent insert of the same request_id must also be the same proposal.
				if ($row['request_key'] !== null && $existing['request_key'] === $row['request_key'] && ($existing['kind'] !== $row['kind'] || !hash_equals($existing['content_digest'], $row['content_digest']))) {
					throw $this->reusedRequest();
				}
				return $this->shape($ctx, $existing, true);
			}
			$stored = $this->row($ctx, $id);
			$this->log($ctx, $stored, 'proposed', 'proposed');
			SaldiInterruptNotifier::notify($this->db, $ctx, $stored);
			return $this->shape($ctx, $stored);
		});
		if (!$item['deduplicated'] && $item['attention'] === 'interrupt' && !$this->db->inTransaction()) {
			SaldiInterruptNotifier::flush($this->db, $ctx, $item['id']);
		}
		return $item;
	}
	/** Return a visible item with its ApprovalCard. @return array */
	public function get(SaldiTenantContext $ctx, int $id): array
	{
		return $this->shape($ctx, $this->row($ctx, $id));
	}
	/** Return the card a human will review before deciding. @return array */
	public function preview(SaldiTenantContext $ctx, int $id): array
	{
		return $this->get($ctx, $id);
	}
	/** Shared SQL visibility predicate for list and count. @return array{0:string,1:array} */
	private function predicate(SaldiTenantContext $ctx): array
	{
		$permissions = SaldiPermissionService::effective($ctx, $this->db);
		$clause = $permissions ? "(visibility = 'permission' AND assignee_permission IN (" . implode(',', array_fill(0, count($permissions), '?')) . '))' : '1 = 0';
		return ['((owner_user_id = ? AND ? <> 0) OR ' . $clause . ')' . (SaldiPermissionService::revenueHidden($ctx, $this->db) ? " AND kind <> 'credit_note.draft'" : ''), array_merge([$ctx->userId, $ctx->userId], $permissions)];
	}
	/** Return a filtered page using the shared visibility predicate. @return list<array> */
	public function list(SaldiTenantContext $ctx, array $filters = []): array
	{
		[$where, $params] = $this->predicate($ctx);
		// One status, or a list of statuses for a page tab that spans several (one query, one ordering).
		$statuses = is_array($filters['status'] ?? null) ? array_values($filters['status']) : [$filters['status'] ?? 'open'];
		foreach ($statuses as $status) {
			if (!in_array($status, ['open', 'applied', 'rejected', 'expired', 'cancelled', 'undone', 'all'], true)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid item status');
			}
		}
		if (!$statuses) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid item status');
		}
		if (!in_array('all', $statuses, true)) {
			$where .= ' AND status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
			$params = array_merge($params, $statuses);
		}
		if (!empty($filters['kind'])) {
			$where .= ' AND kind = ?';
			$params[] = $filters['kind'];
		}
		$limit = max(1, min(100, (int)($filters['limit'] ?? 25)));
		$offset = max(0, (int)($filters['offset'] ?? 0));
		return array_map(fn(array $r): array => $this->shape($ctx, $r), $this->db->all('SELECT * FROM inbox_items WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset, $params));
	}
	/** Count visible open items using the shared visibility predicate. */
	public function countOpen(SaldiTenantContext $ctx): int
	{
		[$where, $params] = $this->predicate($ctx);
		return (int)$this->db->value("SELECT COUNT(*) FROM inbox_items WHERE status = 'open' AND " . $where, $params);
	}
	private function humanBound(SaldiTenantContext $ctx, string $tenantHash): void
	{
		if (!$ctx->isHuman()) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human decision required');
		}
		if (!hash_equals($ctx->tenantHash(), $tenantHash)) {
			throw new SaldiRuntimeError('CONFLICT', 'Company changed', ['reason' => 'tenant_changed']);
		}
	}
	/** Bank rejection and expiry do not invoke a kind's undo; release their staging atomically. */
	private static function bankStatusChanged(SaldiDb $db, array $row, string $status): void
	{
		if ($row['kind'] === 'journal.draft_entry' && in_array($status, ['rejected', 'expired', 'cancelled'], true)) {
			require_once __DIR__ . '/SaldiBankImport.php';
			SaldiBankImport::inboxStatusChanged($db, $row, $status);
		}
	}
	private function terminal(SaldiTenantContext $ctx, array $row, string $status, ?array $result = null, ?string $note = null): void
	{
		$this->db->exec('UPDATE inbox_items SET status = ?, result = ?, open_key = NULL, decided_by = ?, decided_at = ?, decision_note = ?, updated_at = ? WHERE id = ?', [$status, $result === null ? $row['result'] : saldi_runtime_json($result), $ctx->isHuman() ? $ctx->userId : null, gmdate('Y-m-d H:i:s'), $note, gmdate('Y-m-d H:i:s'), $row['id']]);
		self::bankStatusChanged($this->db, $row, $status);
		$this->log($ctx, $row, $status, match ($status) {
			'applied' => 'apply', 'rejected' => 'reject', 'undone' => 'undo', default => $status
		});
	}
	/** Human approval/rejection bound to the reviewed digest and tenant. @return array */
	public function decide(SaldiTenantContext $ctx, int $id, string $decision, string $digest, string $tenantHash, ?string $note = null): array
	{
		$applying = false;
		return $this->decideAttempt($ctx, $id, $decision, $digest, $tenantHash, $note, $applying);
	}
	/** @return array Shared decision path; mobile defers failure audit until its outer rollback. */
	private function decideAttempt(SaldiTenantContext $ctx, int $id, string $decision, string $digest, string $tenantHash, ?string $note, bool &$applying, bool $logFailure = true): array
	{
		$this->humanBound($ctx, $tenantHash);
		if (!in_array($decision, ['approve', 'reject'], true) || ($note !== null && mb_strlen($note) > 255)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid decision');
		}
		$expired = $this->db->transaction(function () use ($ctx, $id): bool {
			$row = $this->row($ctx, $id, true);
			if ($row['status'] === 'open' && $row['expires_at'] !== null && $row['expires_at'] <= gmdate('Y-m-d H:i:s')) {
				$this->terminal($ctx, $row, 'expired');
				return true;
			}
			return $row['status'] === 'expired';
		});
		if ($expired) {
			throw new SaldiRuntimeError('CONFLICT', 'Item expired', ['reason' => 'expired']);
		}
		$applying = false;
		try {
			return $this->db->transaction(function () use ($ctx, $id, $decision, $digest, $note, &$applying): array {
				$row = $this->row($ctx, $id, true);
				if ($row['status'] !== 'open') {
					throw new SaldiRuntimeError('CONFLICT', 'Item not open', ['reason' => 'not_open']);
				}
				if (!hash_equals($row['content_digest'], $digest)) {
					throw new SaldiRuntimeError('CONFLICT', 'Card changed', ['reason' => 'digest_mismatch']);
				}
				$kind = $this->kinds->get($row['kind']);
				$item = $this->decode($row);
				SaldiPermissionService::require($ctx, $this->db, $kind->decidePermission($item));
				$result = null;
				if ($decision === 'approve') {
					$applying = true;
					// Re-check the pinned payload; private-item visibility already enforces its owner.
					$kind->validate($ctx, $this->db, $item['payload']);
					if ($row['proposed_rev'] !== null && $kind->currentRevision($ctx, $this->db, $item) !== $row['proposed_rev']) {
						throw new SaldiRuntimeError('STALE_CONTEXT', 'Subject changed');
					}
					$result = $kind->apply($ctx, $this->db, $item);
				}
				$this->terminal($ctx, $row, $decision === 'approve' ? 'applied' : 'rejected', $result, $note);
				return $this->get($ctx, $id);
			});
		} catch (Throwable $e) {
			if ($applying && $logFailure) {
				$this->logApplyFailure($ctx, $id, $e);
			}
			if ($e instanceof SaldiRuntimeError) {
				throw $e;
			}
			throw new SaldiRuntimeError('UNAVAILABLE', 'Apply unavailable', [], $e);
		}
	}
	/** Persist an approval failure after the decision transaction has rolled back. */
	private function logApplyFailure(SaldiTenantContext $ctx, int $id, Throwable $error): void
	{
		$this->db->transaction(function () use ($ctx, $id, $error): void {
			$row = $this->row($ctx, $id, true);
			$n = (int)$this->db->value("SELECT COUNT(*) FROM decision_log WHERE inbox_item_id = ? AND event = 'apply_failed'", [$id]) + 1;
			$this->log($ctx, $row, 'apply_failed', 'apply_failed:' . $n, ['error_code' => $error instanceof SaldiRuntimeError ? $error->errorCode : 'UNAVAILABLE']);
		});
	}
	/** Human reversal of the intact applied change. @return array */
	public function undo(SaldiTenantContext $ctx, int $id, string $tenantHash): array
	{
		$this->humanBound($ctx, $tenantHash);
		return $this->db->transaction(function () use ($ctx, $id): array {
			$row = $this->row($ctx, $id, true);
			$kind = $this->kinds->get($row['kind']);
			$item = $this->decode($row);
			SaldiPermissionService::require($ctx, $this->db, $kind->decidePermission($item));
			if ($row['status'] !== 'applied' || !$kind->canUndo($ctx, $this->db, $item)) {
				throw new SaldiRuntimeError('CONFLICT', 'Cannot undo changed item', ['reason' => 'undo_changed']);
			}
			$result = $kind->undo($ctx, $this->db, $item);
			$this->terminal($ctx, $row, 'undone', $result);
			return $this->get($ctx, $id);
		});
	}
	public const MOBILE_UNDO_SECONDS = 300;

	/** @return bool Navigation visibility never substitutes for the kind's action permission. */
	private function mobileCanDecide(SaldiTenantContext $ctx, array $item): bool
	{
		return $ctx->isHuman()
			&& Rights::allows($ctx->rights, SaldiKindRegistry::module($item['kind']), 'read', null, $ctx->userId < 0)
			&& SaldiPermissionService::allows($ctx, $this->db, $this->kinds->get($item['kind'])->decidePermission($item));
	}
	/**
	 * Mobile projection uses the web card's translated labels and display formatting.
	 * @return array{
	 *   id:int,kind:string,title:string,summary:?string,attention:string,status:string,created_at:string,
	 *   subject_type:?string,subject_id:?string,digest:string,can_decide:bool,decided_by_label:?string,
	 *   payload_view?:list<array{label:string,value:string}>,rows?:list<array<string,string>>,
	 *   ops?:list<string>,undo_until?:?string,tenant_hash?:string
	 * }
	 */
	private function mobileShape(SaldiTenantContext $ctx, array $item, bool $detail = true): array
	{
		$card = $item['card'];
		$canDecide = $this->mobileCanDecide($ctx, $item);
		$out = ['id' => $item['id'], 'kind' => $item['kind'], 'title' => $item['title'],
			'summary' => ifset($card, 'summary'), 'attention' => in_array($item['attention'], ['silent', 'interrupt'], true) ? $item['attention'] : 'normal',
			'status' => $item['status'], 'created_at' => $item['created_at'],
			'subject_type' => $item['subject']['type'], 'subject_id' => $item['subject']['id'],
			'digest' => $item['digest'], 'can_decide' => $canDecide, 'decided_by_label' => $item['decided_by_name']];
		if (!$detail) {
			return $out;
		}
		$out['payload_view'] = array_map(static fn(array $field): array => ['label' => (string)$field['label'], 'value' => saldi_ui_cell($field['value'])['text']], ifset($card, 'fields', []));
		$out['rows'] = [];
		foreach (ifset($card, 'rows', []) as $row) {
			$mapped = [];
			foreach (ifset($card, 'columns', []) as $i => $label) {
				$mapped[(string)$label] = saldi_ui_cell(ifset($row, $i, ''))['text'];
			}
			$out['rows'][] = $mapped;
		}
		$out['ops'] = $canDecide && $item['status'] === 'open' ? ['approve', 'reject', 'snooze', 'dismiss'] : [];
		$until = $item['decided_at'] === null ? null : strtotime($item['decided_at']) + self::MOBILE_UNDO_SECONDS;
		$out['undo_until'] = $canDecide && $item['can_undo'] && $until !== null && $until > time() ? gmdate('Y-m-d\TH:i:s\Z', $until) : null;
		$out['tenant_hash'] = $ctx->tenantHash();
		return $out;
	}
	/** @return array Mobile detail with display rows and the bounded undo deadline. */
	public function mobileItem(SaldiTenantContext $ctx, int $id): array
	{
		return $this->mobileShape($ctx, $this->get($ctx, $id));
	}
	/** @return array{open:int,silent:int,decided:int,interrupt:int} Cheap counts share item visibility. */
	public function mobileCounts(SaldiTenantContext $ctx, ?string $kind = null): array
	{
		[$where, $params] = $this->predicate($ctx);
		if ($kind !== null) {
			$this->kinds->get($kind);
			$where .= ' AND kind = ?';
			$params[] = $kind;
		}
		$row = $this->db->one("SELECT
			COALESCE(SUM(CASE WHEN status = 'open' AND attention <> 'silent' THEN 1 ELSE 0 END),0) AS open,
			COALESCE(SUM(CASE WHEN status = 'open' AND attention = 'silent' THEN 1 ELSE 0 END),0) AS silent,
			COALESCE(SUM(CASE WHEN status <> 'open' THEN 1 ELSE 0 END),0) AS decided,
			COALESCE(SUM(CASE WHEN status = 'open' AND attention = 'interrupt' THEN 1 ELSE 0 END),0) AS interrupt
			FROM inbox_items WHERE " . $where, $params);
		return array_map('intval', $row);
	}
	/** @return array{items:list<array>,total:int,counts:array{open:int,silent:int,decided:int}} */
	public function mobilePage(SaldiTenantContext $ctx, string $tab = 'open', ?string $kind = null, int $limit = 25, int $offset = 0): array
	{
		if (!in_array($tab, ['open', 'silent', 'decided'], true) || $limit < 1 || $limit > 100 || $offset < 0) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid inbox filter');
		}
		$counts = $this->mobileCounts($ctx, $kind);
		unset($counts['interrupt']);
		[$where, $params] = $this->predicate($ctx);
		$where .= match ($tab) {
			'open' => " AND status = 'open' AND attention <> 'silent'",
			'silent' => " AND status = 'open' AND attention = 'silent'",
			'decided' => " AND status <> 'open'",
		};
		if ($kind !== null) {
			$where .= ' AND kind = ?';
			$params[] = $kind;
		}
		$rows = $this->db->all('SELECT * FROM inbox_items WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset, $params);
		return ['items' => array_map(fn(array $row): array => $this->mobileShape($ctx, $this->shape($ctx, $row), false), $rows), 'total' => $counts[$tab], 'counts' => $counts];
	}
	private function mobileDigest(array $row, string $digest): void
	{
		if (!hash_equals($row['content_digest'], $digest)) {
			throw new SaldiRuntimeError('CONFLICT', 'Card changed', ['reason' => 'digest']);
		}
	}
	/** @return array{item:array,result?:array} A client id stores its first outcome in the same transaction as the decision. */
	public function mobileDecide(SaldiTenantContext $ctx, int $id, string $op, string $digest, string $tenantHash, string $clientId, ?string $note = null): array
	{
		$this->humanBound($ctx, $tenantHash);
		if (!in_array($op, ['approve', 'reject', 'snooze', 'dismiss'], true) || strlen($clientId) < 1 || strlen($clientId) > 64 || ($note !== null && mb_strlen($note) > 255)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid decision');
		}
		$key = 'mobile:decision:' . hash('sha256', $ctx->principal . ':' . $clientId);
		$applying = false;
		try {
			$outcome = $this->db->transaction(function () use ($ctx, $id, $op, $digest, $tenantHash, $note, $key, &$applying): array {
				$this->db->lock($key);
				$row = $this->row($ctx, $id, true);
				$item = $this->decode($row);
				if (!$this->mobileCanDecide($ctx, $item)) {
					throw new SaldiRuntimeError('PERMISSION_DENIED', 'Permission denied');
				}
				$replay = $this->db->one('SELECT inbox_item_id, detail FROM decision_log WHERE idempotency_key = ?', [$key]);
				if ($replay !== null) {
					if ((int)$replay['inbox_item_id'] !== $id) {
						throw new SaldiRuntimeError('VALIDATION_ERROR', 'Client id belongs to another item');
					}
					return json_decode($replay['detail'], true, 512, JSON_THROW_ON_ERROR)['outcome'];
				}
				$this->mobileDigest($row, $digest);
				if ($row['status'] !== 'open') {
					throw new SaldiRuntimeError('CONFLICT', 'Item already decided', ['reason' => 'already_decided']);
				}
				if ($row['expires_at'] !== null && $row['expires_at'] <= gmdate('Y-m-d H:i:s')) {
					$this->terminal($ctx, $row, 'expired');
					return ['conflict' => 'already_decided'];
				}
				if ($op === 'snooze') {
					$this->db->exec("UPDATE inbox_items SET attention = 'silent', decision_note = ?, updated_at = ? WHERE id = ?", [$note, gmdate('Y-m-d H:i:s'), $id]);
				} elseif ($op === 'dismiss') {
					$this->terminal($ctx, $row, 'cancelled', null, $note);
				} else {
					$this->decideAttempt($ctx, $id, $op, $digest, $tenantHash, $note, $applying, false);
				}
				$item = $this->get($ctx, $id);
				$outcome = ['item' => $this->mobileShape($ctx, $item)];
				if (is_array($item['result'])) {
					$outcome['result'] = self::publicItem($item)['result'];
				}
				$outcome = json_decode(saldi_runtime_json($outcome), true, 512, JSON_THROW_ON_ERROR);
				SaldiDecisionLog::append($this->db, ['event' => 'revised',
					'inbox_item_id' => $id, 'kind' => $row['kind'], 'actor' => $ctx->principal, 'approved_by' => $ctx->userId,
					'idempotency_key' => $key, 'detail' => ['reason' => 'mobile_outcome', 'op' => $op, 'outcome' => $outcome]]);
				return $outcome;
			});
		} catch (Throwable $error) {
			if ($applying) {
				$this->logApplyFailure($ctx, $id, $error);
			}
			if ($error instanceof SaldiRuntimeError) {
				if ($error->errorCode === 'STALE_CONTEXT') {
					throw new SaldiRuntimeError('CONFLICT', 'Subject changed', ['reason' => 'digest'], $error);
				}
				if ($error->errorCode === 'CONFLICT' && in_array(ifset($error->detail, 'reason'), ['not_open', 'expired'], true)) {
					throw new SaldiRuntimeError('CONFLICT', 'Item already decided', ['reason' => 'already_decided'], $error);
				}
			}
			throw $error;
		}
		if (isset($outcome['conflict'])) {
			throw new SaldiRuntimeError('CONFLICT', 'Item already decided', ['reason' => $outcome['conflict']]);
		}
		return $outcome;
	}
	/** @return array{item:array} Both the digest and undo deadline are checked under the item lock. */
	public function mobileUndo(SaldiTenantContext $ctx, int $id, string $digest, string $tenantHash): array
	{
		$this->humanBound($ctx, $tenantHash);
		return $this->db->transaction(function () use ($ctx, $id, $digest, $tenantHash): array {
			$row = $this->row($ctx, $id, true);
			$this->mobileDigest($row, $digest);
			if (!$this->mobileCanDecide($ctx, $this->decode($row))) {
				throw new SaldiRuntimeError('PERMISSION_DENIED', 'Permission denied');
			}
			if ($row['status'] !== 'applied' || $row['decided_at'] === null || strtotime($row['decided_at'] . ' UTC') + self::MOBILE_UNDO_SECONDS <= time()) {
				throw new SaldiRuntimeError('CONFLICT', 'Undo unavailable', ['reason' => 'not_undoable']);
			}
			try {
				$this->undo($ctx, $id, $tenantHash);
			} catch (SaldiRuntimeError $error) {
				if ($error->errorCode === 'CONFLICT') {
					throw new SaldiRuntimeError('CONFLICT', 'Undo unavailable', ['reason' => 'not_undoable'], $error);
				}
				throw $error;
			}
			return ['item' => $this->mobileItem($ctx, $id)];
		});
	}
	/** Cancel an open item for its human owner, originating grant or originating rule. @return array */
	public function cancel(SaldiTenantContext $ctx, int $id): array
	{
		return $this->db->transaction(function () use ($ctx, $id): array {
			$row = $this->row($ctx, $id, true);
			if (!(($ctx->isHuman() && $ctx->userId !== 0 && (int)$row['owner_user_id'] === $ctx->userId)
				|| ($ctx->isAgent() && $ctx->grantId !== null && (int)$row['grant_id'] === $ctx->grantId)
				|| ($ctx->isRule() && $row['kind'] === 'routine.finding' && $row['created_by'] === $ctx->principal))) {
				throw new SaldiRuntimeError('PERMISSION_DENIED', 'Owner decision required');
			}
			if ($row['status'] !== 'open') {
				throw new SaldiRuntimeError('CONFLICT', 'Item not open', ['reason' => 'not_open']);
			}
			$this->terminal($ctx, $row, 'cancelled');
			return $this->get($ctx, $id);
		});
	}
	/** Expire overdue open items atomically; return the number changed. */
	public function expireDue(): int
	{
		return $this->db->transaction(function (): int {
			$rows = $this->db->all("SELECT * FROM inbox_items WHERE status = 'open' AND expires_at <= ? ORDER BY id FOR UPDATE", [gmdate('Y-m-d H:i:s')]);
			foreach ($rows as $row) {
				$this->db->exec("UPDATE inbox_items SET status = 'expired', open_key = NULL, updated_at = ? WHERE id = ?", [gmdate('Y-m-d H:i:s'), $row['id']]);
				self::bankStatusChanged($this->db, $row, 'expired');
				SaldiDecisionLog::append($this->db, ['event' => 'expired', 'actor' => 'system:worker', 'inbox_item_id' => $row['id'], 'kind' => $row['kind'], 'idempotency_key' => 'inbox:' . $row['id'] . ':expired']);
			}
			return count($rows);
		});
	}
}
