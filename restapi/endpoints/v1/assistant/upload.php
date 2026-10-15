<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/assistant/upload.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Add chat attachments and bank-line record context.

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../../../../includes/automation/SaldiAttachmentDescriptor.php';

class AssistantUploadEndpoint extends SaldiAssistantEndpoint
{
	/** @return array{module:string,action:string} */
	protected function requiredRight(): ?array
	{
		return ['module' => 'assistant', 'action' => 'read'];
	}
	protected function decodeJsonBody($rawInput)
	{
		if (!str_starts_with(strtolower((string)ifset($_SERVER, 'CONTENT_TYPE', '')), 'multipart/form-data')) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected multipart upload', ['reason' => 'invalid_files']);
		}
		return (object)$_POST;
	}
	protected function handlePost($data)
	{
		$open = $this->assistantContext();
		try {
			$purpose = ifset($data, 'purpose');
			if ($purpose !== null && !is_string($purpose)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid purpose', ['reason' => 'invalid_purpose']);
			}
			$result = SaldiAttachmentDescriptor::upload($open['ctx'], $open['db'], $_FILES, $purpose);
		} finally {
			if (PHP_SAPI === 'cli') {
				$open['db']->close();
			}
		}
		$this->sendResponse(true, $result);
	}
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
	(new AssistantUploadEndpoint())->handleRequestMethod();
}
