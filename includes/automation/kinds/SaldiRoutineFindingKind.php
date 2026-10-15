<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiRoutineFindingKind.php --- ai/mcp-assistant --- 2026.10.02
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
// Rule-only findings assign visibility from the trusted routine registry.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Preserve conversion flags and expose row evidence links.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiInboxKind.php';
final class SaldiRoutineFindingKind extends SaldiBaseInboxKind
{
	public function name(): string
	{
		return 'routine.finding';
	}
	public function actionClass(): string
	{
		return 'read_only';
	}
	public function proposePermission(): string
	{
		return 'company.read';
	}
	public function decidePermission(array $item): string
	{
		return SaldiPermissionService::routine($item['payload']['routine'])['permission'];
	}
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$name = $input['routine'] ?? '';
		if (!is_string($name) || ($ctx->isRule() && $ctx->principal !== 'rule:' . $name)) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Routine identity mismatch');
		}
		$routine = SaldiPermissionService::routine($name);
		if (!is_int($input['count'] ?? null) || $input['count'] < 0 || !is_array($input['rows'] ?? null) || count($input['rows']) > 20) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid finding');
		}
		$p = ['routine' => $name, 'scheduled_date' => $this->date($input['scheduled_date'] ?? null, 'scheduled_date'), 'count' => $input['count'], 'rows' => []];
		$money = static function (mixed $v): string {
			if (!is_string($v) || !preg_match('/^-?[0-9]{1,12}\.[0-9]{2}$/D', $v)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid finding amount');
			}
			return $v;
		};
		$currency = fn(mixed $v): string => $this->string($v, 3, 3, 'currency');
		if (isset($input['total'])) {
			if (!is_array($input['total']) || !is_bool($input['total']['converted'] ?? null)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid total conversion flag');
			}
			$p['total'] = ['amount' => $money($input['total']['amount'] ?? null), 'currency' => $currency($input['total']['currency'] ?? null), 'converted' => $input['total']['converted']];
		}
		foreach ($input['rows'] as $row) {
			if (!is_array($row)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid finding row');
			}
			$n = ['label' => $this->string($row['label'] ?? null, 1, 500, 'label')];
			if (isset($row['amount'])) {
				$n['amount'] = $money($row['amount']);
				$n['currency'] = $currency($row['currency'] ?? $ctx->baseCurrency);
			}
			if (isset($row['date'])) {
				$n['date'] = $this->date($row['date'], 'date');
			}
			if (isset($row['screen'])) {
				if (!is_string($row['screen']) || !SaldiScreens::isAllowed($row['screen'])) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid finding screen');
				}
				$n['screen'] = $row['screen'];
			}
			$p['rows'][] = $n;
		}
		$n = $this->normalized($ctx, $p, $this->text($ctx, $db, $routine['title_text_id']), $name, 'permission', $routine['permission']);
		$n['owner_user_id'] = null;
		return $n;
	}
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		return ['acknowledged' => true];
	}
	public function render(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$card = parent::render($ctx, $db, $item);
		foreach ($item['payload']['rows'] as $row) {
			if (isset($row['screen']) && SaldiScreens::isAllowed($row['screen'])) {
				$card['evidence'][] = ['label' => $row['label'], 'screen' => $row['screen']];
			}
		}
		return $card;
	}
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		return ['columns' => [$this->text($ctx, $db, 5743), $this->text($ctx, $db, 5746), $this->text($ctx, $db, 5741)], 'rows' => array_map(static fn(array $r): array => [$r['label'], isset($r['amount']) ? $r['amount'] . ' ' . $r['currency'] : '', $r['date'] ?? ''], $item['payload']['rows']), 'fields' => isset($item['payload']['total']) ? [['label' => $this->text($ctx, $db, 5746), 'value' => $item['payload']['total']['amount'] . ' ' . $item['payload']['total']['currency']]] : []];
	}
}
