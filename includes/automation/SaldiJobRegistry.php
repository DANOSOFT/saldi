<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiJobRegistry.php --- ai/mcp-assistant --- 2026.10.04
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
// Trusted job handlers and atomic replace-or-keep routine findings.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Cancel replaced routine findings through the inbox service.
// 20261002 CDX/LH Add companion shared foundation contracts.
// 20261002 CDX/LH Honor finding attention and revise accumulated supplier findings in place.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261004 CDX/LH Add companion part 2 foundation contracts.

declare(strict_types=1);

require_once __DIR__ . '/SaldiInboxService.php';
require_once __DIR__ . '/routines/SaldiOverdueDebtorsRoutine.php';
require_once __DIR__ . '/routines/SaldiStaleOpenJournalsRoutine.php';
require_once __DIR__ . '/routines/SaldiLowStockRoutine.php';

require_once __DIR__ . '/routines/SaldiVatMissingDocumentsRoutine.php';
require_once __DIR__ . '/routines/SaldiSupplierBankChangeRoutine.php';
require_once __DIR__ . '/routines/SaldiExpiringBatchesRoutine.php';
require_once __DIR__ . '/routines/SaldiTillDifferenceRoutine.php';
require_once __DIR__ . '/routines/SaldiPostingRuleCandidatesRoutine.php';
require_once __DIR__ . '/routines/SaldiReorderSuggestionsRoutine.php';
require_once __DIR__ . '/routines/SaldiMasterDataHygieneRoutine.php';
require_once __DIR__ . '/SaldiProposingRoutine.php';
require_once __DIR__ . '/jobs/SaldiDigestJob.php';
require_once __DIR__ . '/jobs/SaldiSupportDeliverJob.php';

final class SaldiJobRegistry
{
	private array $handlers = [];
	private array $routines = [];
	public function __construct(private ?SaldiKindRegistry $kinds = null)
	{
	}
	public static function default(): self
	{
		$r = new self();
		foreach ([new SaldiOverdueDebtorsRoutine(), new SaldiStaleOpenJournalsRoutine(), new SaldiLowStockRoutine(), new SaldiVatMissingDocumentsRoutine(), new SaldiSupplierBankChangeRoutine(), new SaldiExpiringBatchesRoutine(), new SaldiTillDifferenceRoutine(), new SaldiPostingRuleCandidatesRoutine(), new SaldiReorderSuggestionsRoutine(), new SaldiMasterDataHygieneRoutine()] as $routine) {
			$r->registerRoutine($routine);
		}
		$r->register('digest.daily', static fn(SaldiTenantContext $ctx, SaldiDb $db, array $job): array => SaldiDigestJob::run($db, $job));
		$r->register('support.deliver', static fn(SaldiTenantContext $ctx, SaldiDb $db, array $job): array => SaldiSupportDeliverJob::run($db, $job));
		return $r;
	}
	public function register(string $type, callable $handler): void
	{
		$this->handlers[$type] = Closure::fromCallable($handler);
	}
	public function get(string $type): ?Closure
	{
		return $this->handlers[$type] ?? null;
	}
	/** @return array<string,SaldiRoutine> */
	public function routines(): array
	{
		return $this->routines;
	}
	public function bindPermissions(): void
	{
		SaldiPermissionService::setRoutineLookup(fn(string $name): ?SaldiRoutine => $this->routines[$name] ?? null);
	}
	public function registerRoutine(SaldiRoutine $routine): void
	{
		$this->routines[$routine->name()] = $routine;
		$this->register('routine.' . $routine->name(), function (SaldiTenantContext $worker, SaldiDb $db, array $job) use ($routine): array {
			if (SaldiSettings::get($db, 'automation', 'routine.' . $routine->name()) !== 'on') {
				return ['status' => 'skipped', 'result' => ['value' => 'disabled']];
			}
			$this->bindPermissions();
			$ctx = SaldiTenantContext::forSystem($worker->installId, $worker->regnskabId, $worker->db, $worker->dbType, 'rule:' . $routine->name(), $worker->fiscalYear, $worker->baseCurrency);
			$payload = json_decode($job['payload'], true, 512, JSON_THROW_ON_ERROR);
			$db->lock('saldi_routine:' . $routine->name());
			if ($routine instanceof SaldiProposingRoutine) {
				$ids = [];
				$inbox = new SaldiInboxService($db, $this->kinds ?? SaldiKindRegistry::default());
				foreach ($routine->proposals($ctx, $db, (string)($payload['scheduled_date'] ?? '')) as $proposal) {
					$dedupe = $proposal['dedupe_key'] ?? ('rule:' . $proposal['match']['type'] . ':' . sha1($proposal['match']['value']));
					$explanation = $proposal['explanation'] ?? '';
					unset($proposal['dedupe_key'], $proposal['explanation']);
					$item = $inbox->create($ctx, 'rule.posting', $proposal, [
						'origin' => 'rule', 'attention' => 'ambient',
						'expires_at' => gmdate('Y-m-d H:i:s', time() + 14 * 86400),
						'request_id' => hash('sha256', $routine->name() . ':' . $job['dedupe_key'] . ':' . $dedupe),
						'evidence' => ['explanation' => $explanation],
					]);
					$ids[] = $item['id'];
				}
				return ['status' => 'done', 'result' => ['value' => $ids ? 'proposals' : 'none', 'item_ids' => $ids]];
			}
			$input = $routine->run($ctx, $db, (string)($payload['scheduled_date'] ?? ''));
			$old = $db->one("SELECT * FROM inbox_items WHERE open_key = ? AND status = 'open' FOR UPDATE", ['routine.finding:' . $routine->name()]);
			$kinds = $this->kinds ?? SaldiKindRegistry::default();
			if ($input !== null) {
				$attention = $input['attention'] ?? 'ambient';
				if (!in_array($attention, ['silent', 'receipt', 'ambient', 'interrupt'], true)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid finding attention');
				}
				$validated = $kinds->get('routine.finding')->validate($ctx, $db, $input);
				$normal = $validated['payload'];
				if ($old && saldi_runtime_json(json_decode($old['payload'], true, 512, JSON_THROW_ON_ERROR)) === saldi_runtime_json($normal)) {
					return ['status' => 'done', 'result' => ['value' => 'unchanged', 'item_id' => (int)$old['id']]];
				}
				if ($old && $routine->name() === 'supplier_bank_change') {
					$revision = (int)$old['revision'] + 1;
					$digest = hash('sha256', saldi_runtime_json(['kind' => 'routine.finding', 'subject_type' => $validated['subject_type'], 'subject_id' => $validated['subject_id'], 'payload' => $normal]));
					$evidence = json_decode($old['evidence'], true, 512, JSON_THROW_ON_ERROR);
					if ($attention === 'interrupt' && $old['attention'] !== 'interrupt') {
						$attention = SaldiAttention::decide($db, null, $validated['assignee_permission'], $attention);
						if ($attention !== 'interrupt') {
							$evidence['attention_downgraded'] = true;
						}
					}
					$db->exec('UPDATE inbox_items SET payload = ?, title = ?, content_digest = ?, revision = ?, updated_at = ?, attention = ?, evidence = ? WHERE id = ?', [saldi_runtime_json($normal), $validated['title'], $digest, $revision, gmdate('Y-m-d H:i:s'), $attention, saldi_runtime_json($evidence), $old['id']]);
					SaldiDecisionLog::append($db, ['event' => 'revised', 'inbox_item_id' => (int)$old['id'], 'kind' => 'routine.finding', 'subject_type' => $validated['subject_type'], 'subject_id' => $validated['subject_id'], 'actor' => $ctx->principal, 'idempotency_key' => 'inbox:' . $old['id'] . ':revised:' . $revision, 'detail' => ['revision' => $revision]]);
					return ['status' => 'done', 'result' => ['value' => 'finding', 'item_id' => (int)$old['id']]];
				}
			}
			if ($old) {
				(new SaldiInboxService($db, $kinds))->cancel($ctx, (int)$old['id']);
			}
			if ($input === null) {
				return ['status' => 'done', 'result' => ['value' => 'none']];
			}
			// Evidence is proposer data. The kind owns translated UI labels.
			$explanation = isset($input['total']) ? 'Totaler omregnet til regnskabets basisvaluta (' . $ctx->baseCurrency . ').' : '';
			$item = (new SaldiInboxService($db, $kinds))->create($ctx, 'routine.finding', $input, ['origin' => 'rule', 'attention' => $attention, 'evidence' => ['explanation' => $explanation], 'expires_at' => gmdate('Y-m-d H:i:s', time() + 7 * 86400)]);
			return ['status' => 'done', 'result' => ['value' => 'finding', 'item_id' => $item['id']]];
		});
	}
}
