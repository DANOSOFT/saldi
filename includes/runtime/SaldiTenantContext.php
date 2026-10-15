<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/runtime/SaldiTenantContext.php --- ai/mcp-assistant --- 2026.10.08
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
// Immutable live tenant identity; no SQL-escaped usernames.
//
// 20261001 CDX/LH Created.
// 20261008 CL/LH Add the mobile Assist identity and delegation contract.

declare(strict_types=1);

final class SaldiTenantContext
{
	public function __construct(
		public readonly string $installId,
		public readonly int $regnskabId,
		public readonly string $db,
		public readonly string $dbType,
		public readonly int $userId,
		public readonly string $username,
		public readonly string $rights,
		public readonly int $fiscalYear,
		public readonly int $languageId,
		public readonly string $principal,
		public readonly ?int $grantId = null,
		public readonly array $scopes = [],
		public readonly string $baseCurrency = 'DKK',
	) {
	}
	/** Trusted CLI construction only; HTTP authentication never calls this method. */
	public static function forSystem(string $installId, int $regnskabId, string $db, string $dbType, string $principal, int $fiscalYear, string $baseCurrency): self
	{
		if (PHP_SAPI !== 'cli' || ($principal !== 'system:worker' && !preg_match('/^rule:[a-z][a-z0-9_]*$/D', $principal))) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Invalid system principal');
		}
		return new self($installId, $regnskabId, $db, $dbType, 0, '', '', $fiscalYear, 1, $principal, null, [], $baseCurrency);
	}
	/** Whether the principal matches its account user or negative revisor id. */
	public function isHuman(): bool
	{
		return ($this->userId > 0 && $this->principal === 'user:' . $this->userId)
			|| ($this->userId < 0 && $this->principal === 'revisor:' . (-$this->userId));
	}
	/** Whether this is a delegated agent principal. */
	public function isAgent(): bool
	{
		return str_starts_with($this->principal, 'agent:');
	}
	/** Whether this is a trusted rule principal. */
	public function isRule(): bool
	{
		return str_starts_with($this->principal, 'rule:');
	}
	/** The represented user for agents; otherwise null. */
	public function onBehalfOf(): ?int
	{
		return $this->isAgent() ? $this->userId : null;
	}
	/** Stable privacy-preserving tenant binding. */
	public function tenantHash(): string
	{
		return substr(hash('sha256', 'saldi-tenant:' . $this->db), 0, 32);
	}
	/** Stable privacy-preserving binding to the raw username. */
	public function userHash(): string
	{
		return substr(hash('sha256', 'saldi-user:' . $this->db . ':' . $this->username), 0, 32);
	}
}
