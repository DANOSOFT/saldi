<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiFollowupTaskKind.php --- ai/mcp-assistant --- 2026.10.01
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
// Owner-only reminders with allowlisted navigation.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiInboxKind.php';
final class SaldiFollowupTaskKind extends SaldiBaseInboxKind
{
	public function name(): string
	{
		return 'followup.task';
	}
	public function actionClass(): string
	{
		return 'read_only';
	}
	public function proposePermission(): string
	{
		return 'followup.draft';
	}
	public function decidePermission(array $item): string
	{
		return 'inbox.read';
	}
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$title = $this->string($input['title'] ?? null, 3, 120, 'title');
		$p = ['title' => $title, 'details' => $this->string($input['details'] ?? '', 0, 1000, 'details')];
		if (isset($input['due_date'])) {
			$p['due_date'] = $this->date($input['due_date'], 'due_date');
		}
		if (isset($input['screen'])) {
			if (!is_string($input['screen']) || !SaldiScreens::isAllowed($input['screen'])) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid screen');
			}
			$p['screen'] = $input['screen'];
		}
		return $this->normalized($ctx, $p, $title);
	}
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		return ['done' => true];
	}
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		return ['columns' => [], 'rows' => [], 'fields' => [['label' => $this->text($ctx, $db, 5743), 'value' => $item['payload']['details']], ['label' => $this->text($ctx, $db, 5741), 'value' => $item['payload']['due_date'] ?? '']]];
	}
}
