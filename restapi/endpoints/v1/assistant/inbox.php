<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/assistant/inbox.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Created for the mobile Assist contract.

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../../../../includes/automation/SaldiInboxService.php';

abstract class SaldiAssistantInboxEndpoint extends SaldiAssistantEndpoint
{
	/** @return array{module:string,action:string} */
	protected function requiredRight(): ?array
	{
		return ['module' => 'assistant', 'action' => 'read'];
	}
	/** @return mixed Result using the live principal, with the runtime connection always closed. */
	protected function inboxCall(callable $call): mixed
	{
		$open = $this->assistantContext();
		try {
			return $call(new SaldiInboxService($open['db'], SaldiKindRegistry::default()), $open['ctx']);
		} finally {
			$open['db']->close();
		}
	}
	protected function integer(mixed $value, string $field, int $min = 1, ?int $max = null): int
	{
		if ((!is_int($value) && (!is_string($value) || !preg_match('/^[0-9]+$/D', $value)))
			|| filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < $min || ($max !== null && (int)$value > $max)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid ' . $field, ['fields' => [$field]]);
		}
		return (int)$value;
	}
	/** @return array{id:int,digest:string,tenant_hash:string} */
	protected function binding(mixed $data): array
	{
		if (!is_object($data)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'An object is required');
		}
		$id = $this->integer(ifset($data, 'id'), 'id');
		foreach (['digest' => 64, 'tenant_hash' => 32] as $field => $length) {
			$value = ifset($data, $field);
			if (!is_string($value) || !preg_match('/^[a-f0-9]{' . $length . '}$/D', $value)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid ' . $field, ['fields' => [$field]]);
			}
		}
		return ['id' => $id, 'digest' => $data->digest, 'tenant_hash' => $data->tenant_hash];
	}
}

class AssistantInboxEndpoint extends SaldiAssistantInboxEndpoint
{
	protected function handleGet($id = null)
	{
		$tab = ifset($_GET, 'tab', 'open');
		$kind = ifset($_GET, 'kind');
		if (!is_string($tab) || ($kind !== null && !is_string($kind))) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid inbox filter');
		}
		$limit = $this->integer(ifset($_GET, 'limit', 25), 'limit', 1, 100);
		$offset = $this->integer(ifset($_GET, 'offset', 0), 'offset', 0);
		$data = $this->inboxCall(static fn(SaldiInboxService $service, SaldiTenantContext $ctx): array => $service->mobilePage($ctx, $tab, $kind === '' ? null : $kind, $limit, $offset));
		$this->sendResponse(true, $data);
	}
}
if (realpath((string)ifset($_SERVER, 'SCRIPT_FILENAME', '')) === __FILE__) {
	(new AssistantInboxEndpoint())->handleRequestMethod();
}
