<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/assistant/consent.php --- ver 5.0.0 --- 2026.10.08
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

class AssistantConsentEndpoint extends SaldiAssistantEndpoint
{
	/** @return array{module:string,action:string} */
	protected function requiredRight(): ?array
	{
		return ['module' => 'assistant', 'action' => 'read'];
	}
	protected function handleGet($id = null)
	{
		$this->consent(null);
	}
	protected function handlePost($data)
	{
		$value = ifset($data, 'consent');
		if (!is_string($value) || !in_array($value, ['yes', 'no'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid consent');
		}
		$this->consent($value);
	}
	private function consent(?string $value): void
	{
		$open = $this->assistantContext();
		$db = $open['db'];
		try {
			if ($value !== null) {
				SaldiSettings::setUser($db, $open['ctx'], 'assistant', 'improvement_consent', $value);
			}
			$consent = SaldiSettings::getUser($db, $open['ctx'], 'assistant', 'improvement_consent', '');
		} finally {
			if (PHP_SAPI === 'cli') {
				$db->close();
			}
		}
		$this->sendResponse(true, ['consent' => in_array($consent, ['yes', 'no'], true) ? $consent : null]);
	}
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
	(new AssistantConsentEndpoint())->handleRequestMethod();
}
