<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiJobRunner.php --- ai/mcp-assistant --- 2026.10.03
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
// Lease claiming, transaction fencing, retries and daily scheduling.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Identify lost job leases through structured conflict details.
// 20261002 CL/LH Expired leases on the last attempt fail instead of being reclaimed.
// 20261002 CDX/LH Add companion shared foundation contracts.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.

declare(strict_types=1);

require_once __DIR__ . '/SaldiJobRegistry.php';

final class SaldiJobRunner
{
	public function __construct(private SaldiDb $db, private SaldiJobRegistry $registry)
	{
	}
	/** @return array|null Claimed row with a fresh five-minute lease. */
	public function claim(): ?array
	{
		$now = gmdate('Y-m-d H:i:s');
		$token = bin2hex(random_bytes(16));
		$until = gmdate('Y-m-d H:i:s', time() + 300);
		// A lease that expired on its last attempt (worker killed, fatal error, timeout) never reaches
		// complete(); fail it here instead of retrying a poison job forever.
		$this->db->exec("UPDATE automation_jobs SET status = 'failed', last_error = 'lease expired', finished_at = ?, lease_token = NULL, locked_until = NULL WHERE status = 'running' AND locked_until < ? AND attempts >= max_attempts", [$now, $now]);
		$select = "SELECT id FROM automation_jobs WHERE (status = 'pending' AND run_after <= ?) OR (status = 'running' AND locked_until < ? AND attempts < max_attempts) ORDER BY run_after, id LIMIT 1 FOR UPDATE SKIP LOCKED";
		if ($this->db->isPostgres()) {
			return $this->db->one("UPDATE automation_jobs SET status = 'running', lease_token = ?, locked_until = ?, attempts = attempts + 1 WHERE id = (" . $select . ') RETURNING *', [$token, $until, $now, $now]);
		}
		return $this->db->transaction(function () use ($select, $now, $token, $until): ?array {
			$id = $this->db->value($select, [$now, $now]);
			if ($id === null) {
				return null;
			}
			$this->db->exec("UPDATE automation_jobs SET status = 'running', lease_token = ?, locked_until = ?, attempts = attempts + 1 WHERE id = ?", [$token, $until, $id]);
			return $this->db->one('SELECT * FROM automation_jobs WHERE id = ?', [$id]);
		});
	}
	/** @return array{status:string,result?:array,error?:string} Lost leases discard all handler writes. */
	public function run(SaldiTenantContext $ctx, array $job): array
	{
		$handler = $this->registry->get($job['job_type']);
		if ($handler === null) {
			return $this->complete($job, ['status' => 'failed', 'error' => 'unknown job type'], true);
		}
		$failedOutcome = null;
		try {
			return $this->db->transaction(function () use ($ctx, $job, $handler, &$failedOutcome): array {
				$outcome = $handler($ctx, $this->db, $job);
				if (!is_array($outcome) || !in_array($outcome['status'] ?? '', ['done', 'skipped', 'failed'], true)) {
					throw new RuntimeException('Invalid job outcome');
				}
				if ($outcome['status'] === 'failed') {
					$failedOutcome = $outcome;
					throw new RuntimeException('Job handler failed');
				}
				// Lock and fence the lease inside the same transaction as finding writes.
				$n = $this->db->exec("UPDATE automation_jobs SET locked_until = ? WHERE id = ? AND status = 'running' AND lease_token = ?", [gmdate('Y-m-d H:i:s', time() + 300), $job['id'], $job['lease_token']]);
				if ($n !== 1) {
					throw new SaldiRuntimeError('CONFLICT', 'Job lease lost', ['reason' => 'not_open', 'lease_lost' => true]);
				}
				return $this->complete($job, $outcome);
			});
		} catch (Throwable $e) {
			if ($e instanceof SaldiRuntimeError && $e->errorCode === 'CONFLICT' && ($e->detail['lease_lost'] ?? false) === true) {
				return ['status' => 'skipped', 'result' => ['value' => 'lease_lost']];
			}
			// Store a stable diagnostic, never a PDO message containing tenant data.
			return $this->complete($job, $failedOutcome ?? ['status' => 'failed', 'error' => $e instanceof SaldiRuntimeError ? $e->errorCode : 'job execution failed']);
		}
	}
	/** @return array{status:string,result?:array,error?:string} */
	public function complete(array $job, array $outcome, bool $permanent = false): array
	{
		$failed = $outcome['status'] === 'failed';
		$status = $failed && !$permanent && (int)$job['attempts'] < (int)$job['max_attempts'] ? 'pending' : $outcome['status'];
		$delay = 60 * (2 ** min(20, (int)$job['attempts']));
		return $this->db->transaction(function () use ($job, $outcome, $status, $delay): array {
			$n = $this->db->exec("UPDATE automation_jobs SET status = ?, run_after = ?, locked_until = NULL, lease_token = NULL, last_error = ?, result = ?, finished_at = ? WHERE id = ? AND status = 'running' AND lease_token = ?", [$status, $status === 'pending' ? gmdate('Y-m-d H:i:s', time() + $delay) : $job['run_after'], $outcome['error'] ?? null, isset($outcome['result']) ? saldi_runtime_json($outcome['result']) : null, $status === 'pending' ? null : gmdate('Y-m-d H:i:s'), $job['id'], $job['lease_token']]);
			if ($n === 1 && $status === 'failed' && $job['job_type'] === 'support.deliver') {
				SaldiSupportDeliverJob::markFailed($this->db, $job);
			}
			return $n === 1 ? $outcome : ['status' => 'skipped', 'result' => ['value' => 'lease_lost']];
		});
	}
	/** Enqueue each enabled routine at its Copenhagen cadence. @return int Inserted jobs. */
	public function enqueue(?string $date = null, ?DateTimeImmutable $nowLocal = null): int
	{
		$zone = new DateTimeZone('Europe/Copenhagen');
		$nowLocal = ($nowLocal ?? new DateTimeImmutable('now', $zone))->setTimezone($zone);
		$date ??= $nowLocal->format('Y-m-d');
		$d = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $zone);
		if (!$d || $d->format('Y-m-d') !== $date) {
			throw new InvalidArgumentException('Invalid scheduled date');
		}
		$count = 0;
		foreach ($this->registry->routines() as $name => $routine) {
			if (SaldiSettings::get($this->db, 'automation', 'routine.' . $name) !== 'on') {
				continue;
			}
			$slot = match ($routine->schedule()) {
				'daily' => $date,
				'hourly' => $date . 'T' . $nowLocal->format('H'),
				'weekly' => $d->format('N') === '1' ? $d->format('o-\WW') : null,
				default => throw new InvalidArgumentException('Unknown routine schedule'),
			};
			if ($slot !== null) {
				$count += $this->enqueueJob('routine.' . $name, ['scheduled_date' => $date], 'routine:' . $name . ':' . $slot);
			}
		}
		if (SaldiDigestJob::shouldEnqueue($this->db, $nowLocal)) {
			$count += $this->enqueueJob('digest.daily', ['scheduled_date' => $date], 'digest:' . $date);
		}
		return $count;
	}
	/** @return int One when inserted, zero when this schedule slot already exists. */
	private function enqueueJob(string $type, array $payload, string $dedupe): int
	{
		$id = $this->db->insertIgnore('automation_jobs', ['job_type' => $type, 'payload' => saldi_runtime_json($payload), 'status' => 'pending', 'run_after' => gmdate('Y-m-d H:i:s'), 'dedupe_key' => $dedupe, 'attempts' => 0, 'max_attempts' => 3, 'created_at' => gmdate('Y-m-d H:i:s')], ['dedupe_key']);
		return $id === null ? 0 : 1;
	}

	public function hasWork(): bool
	{
		if (SaldiDigestJob::shouldEnqueue($this->db, new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))) {
			return true;
		}
		foreach ($this->registry->routines() as $name => $routine) {
			if (SaldiSettings::get($this->db, 'automation', 'routine.' . $name) === 'on') {
				return true;
			}
		}
		return $this->db->value("SELECT id FROM automation_jobs WHERE status IN ('pending', 'running') LIMIT 1") !== null || $this->db->value("SELECT id FROM inbox_items WHERE status = 'open' AND expires_at <= ? LIMIT 1", [gmdate('Y-m-d H:i:s')]) !== null;
	}
}
