<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/runtime/SaldiRuntimeError.php --- ai/mcp-assistant --- 2026.10.10
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
// Stable public error codes with translated messages.
//
// 20261001 CDX/LH Created.
// 20261010 CDX/LH Expose the mobile grant revocation error code.

declare(strict_types=1);

final class SaldiRuntimeError extends RuntimeException
{
	public const CODES = [
		'UNAUTHENTICATED' => ['PERMANENT', 401, 5730], 'PERMISSION_DENIED' => ['NEEDS_HUMAN', 403, 5731],
		'VALIDATION_ERROR' => ['PERMANENT', 400, 5732], 'NOT_FOUND' => ['PERMANENT', 404, 5733],
		'STALE_CONTEXT' => ['NEEDS_HUMAN', 409, 5734], 'CLOSED_PERIOD' => ['NEEDS_HUMAN', 409, 5735],
		'MEMORY_FULL' => ['NEEDS_HUMAN', 409, 5736], 'CONFLICT' => ['NEEDS_HUMAN', 409, 5737],
		'RATE_LIMITED' => ['RETRYABLE', 429, 5738], 'DISABLED' => ['PERMANENT', 403, 5739],
		'UNAVAILABLE' => ['RETRYABLE', 503, 5740], 'GRANT_REVOKED' => ['PERMANENT', 401, 6400],
	];
	public function __construct(public readonly string $errorCode, string $messageEn, public readonly array $detail = [], ?Throwable $previous = null)
	{
		if (!isset(self::CODES[$errorCode])) {
			throw new InvalidArgumentException('Unknown runtime error');
		}
		parent::__construct($messageEn, 0, $previous);
	}
	/** Stable retry/human-action classification. */
	public function errorClass(): string
	{
		return self::CODES[$this->errorCode][0];
	}
	/** HTTP status for this public error code. */
	public function httpStatus(): int
	{
		return self::CODES[$this->errorCode][1];
	}
	/** Translate the public error in the viewer language. */
	public function messageDa(?SaldiDb $tenant = null, int $languageId = 1): string
	{
		return SaldiText::get($tenant, self::CODES[$this->errorCode][2], $languageId, $this->getMessage());
	}
}
