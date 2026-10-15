<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiSupportReportKind.php --- ai/mcp-assistant --- 2026.10.03
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
// Owner-approved support descriptions enqueue delivery without including ERP rows.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Validate support text and queue the exact approved description.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiInboxKind.php';

final class SaldiSupportReportKind extends SaldiBaseInboxKind
{
	public function name(): string
	{
		return 'support.report';
	}
	public function actionClass(): string
	{
		return 'read_only';
	}
	public function proposePermission(): string
	{
		return 'support.draft';
	}
	public function decidePermission(array $item): string
	{
		return 'inbox.read';
	}
	/** @return array{payload:array,title:string,subject_type:?string,subject_id:?string,proposed_rev:?string,dedupe_key:?string,visibility:string,assignee_permission:?string,owner_user_id:?int} */
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		if (!in_array($input['category'] ?? null, ['bug', 'question'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid support category');
		}
		$payload = ['category' => $input['category']];
		foreach (['summary' => [10, 200], 'details' => [0, 2000]] as $field => [$minimum, $maximum]) {
			$value = $input[$field] ?? null;
			if (!is_string($value)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid ' . $field, ['fields' => [$field]]);
			}
			$value = preg_replace('/[\x00-\x1f\x7f]/u', '', $value);
			$payload[$field] = $this->string($value, $minimum, $maximum, $field);
			if (preg_match('/(?:password|kodeord|adgangskode|api[_ -]?key|token|cpr)\s*(?:[:=]|[0-9])|\b(?=[A-Za-z0-9_-]{16,}\b)(?=[A-Za-z0-9_-]*[A-Za-z])(?=[A-Za-z0-9_-]*[0-9])[A-Za-z0-9_-]+/iu', $payload[$field])) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Secret-like support text is not allowed');
			}
		}
		if (array_key_exists('screen', $input)) {
			$screen = $this->string($input['screen'], 1, 200, 'screen');
			if (!SaldiScreens::isAllowed($screen)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid support screen');
			}
			$payload['screen'] = $screen;
		}
		return $this->normalized($ctx, $payload, $payload['summary']);
	}
	/** @return array{queued:bool,job_id:int} */
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		if (!$ctx->isHuman() || (int)($item['owner_user_id'] ?? 0) !== $ctx->userId) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Support approval requires its human owner');
		}
		$payload = $this->validate($ctx, $db, $item['payload'])['payload'];
		$payload += ['install_id' => $ctx->installId, 'tenant_hash' => $ctx->tenantHash(), 'item_id' => (int)$item['id'],
			'company_name' => (string)($db->value("SELECT firmanavn FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1") ?? ''),
			'saldi_version' => saldi_runtime_config()['version'], 'submitted_by_hash' => $ctx->userHash(), 'approved_at' => gmdate('Y-m-d\TH:i:s\Z')];
		$payload['screen'] ??= null;
		$dedupe = 'support:' . (int)$item['id'];
		$id = $db->insertIgnore('automation_jobs', ['job_type' => 'support.deliver', 'subject_type' => 'inbox_item', 'subject_id' => (string)$item['id'],
			'payload' => saldi_runtime_json($payload), 'status' => 'pending', 'run_after' => gmdate('Y-m-d H:i:s'), 'dedupe_key' => $dedupe,
			'attempts' => 0, 'max_attempts' => 3, 'created_at' => gmdate('Y-m-d H:i:s')], ['dedupe_key']);
		$id ??= (int)$db->value('SELECT id FROM automation_jobs WHERE dedupe_key = ?', [$dedupe]);
		return ['queued' => true, 'job_id' => $id];
	}
	/** @return array{columns:array,rows:array,fields:list<array{label:string,value:string}>} */
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$fields = [];
		foreach (['category' => [2837, 'Kategori'], 'summary' => [216, 'Emne'], 'details' => [914, 'Beskrivelse'], 'screen' => [6066, 'Skærm']] as $key => [$id, $fallback]) {
			$value = (string)($item['payload'][$key] ?? '');
			if ($key === 'category') {
				$value = $value === 'bug' ? $this->text($ctx, $db, 6067, 'Fejl') : $this->text($ctx, $db, 6068, 'Spørgsmål');
			}
			$fields[] = ['label' => $this->text($ctx, $db, $id, $fallback), 'value' => $value];
		}
		return ['columns' => [], 'rows' => [], 'fields' => $fields];
	}
	/** @return array */
	public function render(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$card = parent::render($ctx, $db, $item);
		if (($item['result']['support_case'] ?? null) === 'not_configured') {
			$card['notices'][] = ['kind' => 'info', 'text' => $this->text($ctx, $db, 6065, 'Ikke sendt: support er ikke sat op')];
		}
		if (($item['result']['support_case'] ?? null) === 'failed') {
			$card['notices'][] = ['kind' => 'info', 'text' => $this->text($ctx, $db, 6069, 'Ikke sendt: kunne ikke nå Saldi-support. Prøv igen senere eller kontakt support.')];
		}
		return $card;
	}
}
