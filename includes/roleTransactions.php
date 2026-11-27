<?php
// 20261110 CDX/ASR Transactions and content revisions shared by role administration and user assignments.
// 20261114 CL/ASR The built-in role flag is read as is_built_in (master's rename of roles.system, d7cdeb81).

/** Execute a write whose failure must abort the surrounding role transaction. */
function perm_write(string $sql): void
{
	$result = db_modify($sql, __FILE__ . ' linje ' . __LINE__);
	if (!is_string($result) || substr($result, 0, 1) !== '0') {
		throw new RuntimeException('Role write failed');
	}
}

/**
 * Run one administrative operation atomically, joining a containing operation.
 * The application's database helper exits on SQL errors in page requests; rollback
 * at shutdown also protects persistent connections when that path stops execution.
 *
 * @return mixed The callback's result.
 */
function perm_atomic(callable $operation)
{
	static $depth = 0;
	if ($depth > 0) {
		return $operation();
	}
	$nested = !empty($GLOBALS['db_transaktion_depth']);
	if (!$nested) {
		$GLOBALS['db_modify_fejl'] = false;
	}
	$begin = $nested ? 'savepoint saldi_role_change' : 'begin';
	$commit = $nested ? 'release savepoint saldi_role_change' : 'commit';
	$rollback = $nested ? 'rollback to savepoint saldi_role_change' : 'rollback';
	perm_write($begin);
	$pending = true;
	register_shutdown_function(function () use (&$pending, $rollback) {
		if ($pending) {
			transaktion($rollback);
		}
	});
	$depth++;
	try {
		$result = $operation();
		if (!empty($GLOBALS['db_modify_fejl'])) {
			throw new RuntimeException('Role transaction contains a failed write');
		}
		perm_write($commit);
		$pending = false;
		return $result;
	} catch (Throwable $error) {
		transaktion($rollback);
		$pending = false;
		throw $error;
	} finally {
		$depth--;
	}
}

/**
 * Lock existing roles in a consistent order. The Administrator row also serializes
 * new role names and assignments, so checking duplicate names and last-admin rules
 * cannot race with another administrative operation through this service.
 *
 * @return array<int, array<string, mixed>> Role rows keyed by id.
 */
function perm_lock_roles(): array
{
	$rows = array();
	$q = db_select('select id, role_key, navn, beskrivelse, is_built_in from roles order by id for update', __FILE__ . ' linje ' . __LINE__);
	if (!$q) {
		throw new RuntimeException('Could not lock roles');
	}
	while ($r = db_fetch_array($q)) {
		$rows[(int) $r['id']] = $r;
	}
	// A locking SELECT may have started before a concurrent creator committed.
	// Read again after its locks are acquired so newly committed names are included.
	$rows = array();
	$q = db_select('select id, role_key, navn, beskrivelse, is_built_in from roles order by id', __FILE__ . ' linje ' . __LINE__);
	while ($r = db_fetch_array($q)) {
		$rows[(int) $r['id']] = $r;
	}
	perm_current_levels(true);
	return $rows;
}

/** @return array<string, string> Known keys, clamped to their supported levels. */
function perm_normalize_levels(array $levels): array
{
	$out = array();
	foreach (permission_registry() as $key => $def) {
		$level = isset($levels[$key]) && is_string($levels[$key]) ? perm_level_valid($levels[$key]) : 'none';
		$out[$key] = $level === 'write' && !empty($def['readonly']) ? 'read' : $level;
	}
	return $out;
}

/** @return array<string, array{before: string, after: string}> Includes additions and removals. */
function perm_level_changes(array $before, array $after): array
{
	$before = perm_normalize_levels($before);
	$after = perm_normalize_levels($after);
	$out = array();
	foreach ($before as $key => $level) {
		if ($level !== $after[$key]) {
			$out[$key] = array('before' => $level, 'after' => $after[$key]);
		}
	}
	return $out;
}

/** Whether the proposed map removes any existing capability. */
function perm_levels_reduced(array $before, array $after): bool
{
	$rank = perm_level_rank();
	foreach (perm_level_changes($before, $after) as $change) {
		if ($rank[$change['after']] < $rank[$change['before']]) {
			return true;
		}
	}
	return false;
}

/** Content fingerprint: no schema migration, includes metadata and every capability. */
function perm_role_revision(array $role, array $levels): string
{
	$levels = perm_normalize_levels($levels);
	ksort($levels);
	return hash('sha256', serialize(array((int) $role['id'], (string) ($role['role_key'] ?? $role['key'] ?? ''),
		(string) $role['navn'], (string) $role['beskrivelse'], in_array($role['is_built_in'], array(true, 't', 1, '1'), true), $levels)));
}

/** Human-readable, language-independent details, with exact keys and before/after levels. */
function perm_change_details(string $subject, array $before, array $after): string
{
	$parts = array($subject);
	foreach (perm_level_changes($before, $after) as $key => $change) {
		$parts[] = $key . ': ' . $change['before'] . ' -> ' . $change['after'];
	}
	return implode("\n", $parts);
}
