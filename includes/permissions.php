<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/permissions.php --- lap 5.0.0 --- 2026.09.16 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260916 Sawaneh Roles & permissions layer (spec part 2): role -> permission levels,
//                  compatibility with the legacy rettigheder string (a role is rendered
//                  into brugere.rettigheder so old checks keep working), require_permission(),
//                  nearest-role suggestion for migration, and the audit log.
// 20260928 Sawaneh Derived keys (pos.kasse from Debitorordre) and one-time seeding of new keys into roles.
// 20260928 Sawaneh Phase 4: settings pages resolve to their group key; derived keys open their source position.
// 20260928 Sawaneh Registry keys may carry 'renamed_from'; role rows move to the new name once.
// 20260929 Sawaneh Roles stage 2: audit_log() records object and source; event names follow the spec (§7.1).
// 20260930 Sawaneh Roles stage 2 (§6): migration of users without a role, custom roles per rights pattern,
//                  covering-role suggestion and the review list.
// 20261001 Sawaneh Review fixes: the migration leaves users without a rights string alone and never rewrites a
//                  rights string; booleans written as true/false (MySQL); custom roles get no audit log;
//                  notification check per database; one review flag per user (unique index on settings).
// 20261009 Sawaneh Oversigt keys (addendum 4): 'derive_keys' resolution for users without a role and at seeding (built-in roles too), read-only clamp.

include_once(__DIR__ . '/permissionRegistry.php');

// ------------------------------------------------------------------ levels

/**
 * @return array<string, int> Level name -> rank, for comparisons.
 */
function perm_level_rank(): array
{
	return array('none' => 0, 'read' => 1, 'write' => 2);
}

function perm_level_valid(string $level): string
{
	return isset(perm_level_rank()[$level]) ? $level : 'none';
}

function perm_level_label(string $level, int $sprogId): string
{
	if ($level === 'write') {
		return findtekst('5547|Fuld adgang', $sprogId);
	}
	if ($level === 'read') {
		return findtekst('2475|Kun visning', $sprogId);
	}
	return findtekst('5546|Ingen adgang', $sprogId);
}

// ------------------------------------------------------------------ schema

/**
 * True once includes/betweenUpdates.php has created the role tables in this company.
 */
function perm_tables_ready(): bool
{
	static $ready = null;
	if ($ready === null) {
		$ready = function_exists('tbl_exists') && tbl_exists('roles') && tbl_exists('role_permissions');
	}
	return $ready;
}

function audit_ready(): bool
{
	static $ready = null;
	if ($ready === null) {
		$ready = function_exists('tbl_exists') && tbl_exists('audit_log');
	}
	return $ready;
}

// ------------------------------------------------------------------ legacy <-> levels

/**
 * Levels implied by a legacy rettigheder string. Position values: '1' write, '2' read,
 * else none. Keys without a legacy position follow the Indstillinger bit (1) - that is
 * exactly what those users can do today, so nothing changes until a role is assigned.
 *
 * @return array<string, string>
 */
function perm_levels_from_legacy(string $rettigheder): array
{
	$levels = array();
	$settingsBit = substr($rettigheder, 1, 1);
	$settingsLevel = ($settingsBit === '1') ? 'write' : (($settingsBit === '2') ? 'read' : 'none');
	$derived = array();
	foreach (permission_registry() as $key => $def) {
		if (!$def['legacy']) {
			if (isset($def['derive_keys'])) {
				$derived[$key] = $def['derive_keys'];
				$levels[$key] = 'none';
			} elseif (isset($def['derive'])) {
				$c = substr($rettigheder, (int) $def['derive'], 1);
				$levels[$key] = ($c === '1') ? 'write' : (($c === '2') ? 'read' : 'none');
			} else {
				$levels[$key] = $settingsLevel;
			}
			continue;
		}
		$best = 'none';
		foreach ($def['legacy'] as $pos) {
			$c = substr($rettigheder, $pos, 1);
			if ($c === '1') {
				$best = 'write';
			} elseif ($c === '2' && $best === 'none') {
				$best = 'read';
			}
		}
		$levels[$key] = $best;
	}
	// Keys derived from other keys (Oversigt blocks): read when any of them is open; '*' = always (everyone sees Oversigt today).
	foreach ($derived as $key => $from) {
		$levels[$key] = perm_derive_level($from, $levels);
	}
	return $levels;
}

/**
 * The level a derived key gets from the keys it follows: 'read' when any of them is open, '*' meaning always.
 *
 * @param array<int, string> $from
 * @param array<string, string> $levels
 */
function perm_derive_level(array $from, array $levels): string
{
	foreach ($from as $k) {
		if ($k === '*' || (isset($levels[$k]) && $levels[$k] !== 'none')) {
			return 'read';
		}
	}
	return 'none';
}

/**
 * The 16-position legacy string a set of levels renders to.
 */
function perm_legacy_string(array $levels): string
{
	$positions = array_fill(0, 16, '0');
	foreach (permission_registry() as $key => $def) {
		$level = isset($levels[$key]) ? $levels[$key] : 'none';
		foreach ($def['legacy'] as $pos) {
			$c = ($level === 'write') ? '1' : (($level === 'read') ? '2' : '0');
			if ($c === '1' || ($c === '2' && $positions[$pos] === '0')) {
				$positions[$pos] = $c;
			}
		}
	}
	// A derived key (pos.kasse, settings.finance, ...) needs its source position open so the
	// legacy page gate lets the user in; read is enough, the key itself decides the rest.
	foreach (permission_registry() as $key => $def) {
		if (isset($def['derive']) && isset($levels[$key]) && $levels[$key] !== 'none' && $positions[(int) $def['derive']] === '0') {
			$positions[(int) $def['derive']] = '2';
		}
	}
	return implode('', $positions);
}

/**
 * @return array<string, string> Every registry key -> level for the role (none when unset).
 */
function perm_levels_from_role(int $roleId): array
{
	$levels = array();
	foreach (permission_registry() as $key => $def) {
		$levels[$key] = 'none';
	}
	if (!perm_tables_ready() || $roleId <= 0) {
		return $levels;
	}
	$q = db_select("select permission_key, level from role_permissions where role_id = $roleId", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (isset($levels[$r['permission_key']])) {
			$levels[$r['permission_key']] = perm_level_valid((string) $r['level']);
		}
	}
	return $levels;
}

/**
 * Effective levels for a user: the role when one is assigned, else the legacy string.
 *
 * @return array<string, string>
 */
function perm_user_levels(int $brugerId, string $rettigheder): array
{
	if (perm_tables_ready() && $brugerId > 0) {
		$r = db_fetch_array(db_select("select role_id from brugere where id = $brugerId", __FILE__ . " linje " . __LINE__));
		if ($r && (int) $r['role_id'] > 0) {
			return perm_levels_from_role((int) $r['role_id']);
		}
	}
	return perm_levels_from_legacy($rettigheder);
}

// ------------------------------------------------------------------ enforcement mode

/**
 * Company-wide switch for phase 3 (spec 3.3 step 2):
 *  'log'  - pages without a declaration and requests that would be refused are only
 *           written to audit_log ("would have been denied"); nothing is blocked.
 *  'deny' - default-deny is on: undeclared pages are refused, read-only is real, and
 *           auditor/master-admin sessions get the read-only Revisor role.
 * Dangerous keys (registry 'dangerous') are enforced in both modes.
 */
function perm_enforcement_mode(): string
{
	static $mode = null;
	if ($mode === null) {
		$mode = 'log';
		if (function_exists('tbl_exists') && tbl_exists('settings')) {
			$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'permissions' and var_name = 'enforce'", __FILE__ . " linje " . __LINE__));
			if ($r && in_array($r['var_value'], array('log', 'deny'), true)) {
				$mode = $r['var_value'];
			}
		}
	}
	return $mode;
}

function perm_set_enforcement_mode(string $mode): void
{
	$mode = ($mode === 'deny') ? 'deny' : 'log';
	$r = db_fetch_array(db_select("select id from settings where var_grp = 'permissions' and var_name = 'enforce'", __FILE__ . " linje " . __LINE__));
	if ($r) {
		db_modify("update settings set var_value = '$mode' where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("insert into settings (var_grp, var_name, var_value, var_description) values ('permissions', 'enforce', '$mode', 'Permission enforcement: log or deny')", __FILE__ . " linje " . __LINE__);
	}
	audit_log('permissions.mode', $mode);
}

/**
 * Registry key a legacy $modulnr position maps to (the first key owning that position).
 */
function perm_key_for_modulnr(int $modulnr): string
{
	foreach (permission_registry() as $key => $def) {
		if (in_array($modulnr, $def['legacy'], true)) {
			return $key;
		}
	}
	return '';
}

/**
 * Central request gate, called from includes/online.php once the user is known.
 *
 * @param string|null $declaredKey   $permission_key set by the page ('any' = every logged-in user), or null
 * @param string      $declaredLevel $permission_level set by the page (read/write), default read
 * @param bool        $postIsRead    $permission_post_read: POST is a filter/search, not a write
 */
function perm_enforce_request(?string $declaredKey, string $declaredLevel, bool $postIsRead, ?int $modulnr): void
{
	$mode = perm_enforcement_mode();
	$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
	$page = (string) parse_url($uri, PHP_URL_PATH);

	$key = $declaredKey;
	if ($key === null && $modulnr !== null && $modulnr >= 0 && $modulnr < 16) {
		$key = perm_key_for_modulnr($modulnr);
	}
	if ($key === 'system.indstillinger') {
		$key = perm_settings_key_for_request($page, $_GET);
	}
	if ($key === null || $key === '') {
		if ($mode === 'deny') {
			perm_refuse('unguarded', $page);
		}
		perm_log_once('unguarded', $page);
		return;
	}
	if ($key === 'any') {
		return;
	}
	$registry = permission_registry();
	$dangerous = isset($registry[$key]) && $registry[$key]['dangerous'];
	$isPost = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST');
	$need = ($declaredLevel === 'write' || ($isPost && !$postIsRead)) ? 'write' : 'read';
	if (perm_can($key, $need)) {
		return;
	}
	if ($mode === 'deny' || $dangerous) {
		perm_refuse($key . ' (' . $need . ')', $page);
	}
	perm_log_once('permission.would_deny', $key . ' (' . $need . ') ' . $page);
}

/**
 * A settings page is guarded by the key of its group on the settings front page
 * (systemdata/settingsRegistry.php). A page listed in two groups (e.g. debtor/creditor
 * groups) is open to whoever holds either; unlisted pages keep system.indstillinger.
 */
function perm_settings_key_for_request(string $page, array $get): string
{
	$registryFile = __DIR__ . '/../systemdata/settingsRegistry.php';
	if (strpos($page, '/systemdata/') === false || !file_exists($registryFile)) {
		return 'system.indstillinger';
	}
	include_once($registryFile);
	if (!function_exists('settings_entries_for_request')) {
		return 'system.indstillinger';
	}
	$best = '';
	$rank = perm_level_rank();
	foreach (settings_entries_for_request(basename($page), $get) as $entry) {
		$key = settings_group_permission($entry['group']);
		if ($key !== '' && ($best === '' || $rank[perm_level($key)] > $rank[perm_level($best)])) {
			$best = $key;
		}
	}
	return $best !== '' ? $best : 'system.indstillinger';
}

/**
 * One audit row per page per session, so the logging period doesn't flood the table.
 */
function perm_log_once(string $handling, string $detaljer): void
{
	$slot = 'perm_' . md5($handling . '|' . $detaljer);
	if (isset($_SESSION[$slot])) {
		return;
	}
	$_SESSION[$slot] = 1;
	audit_log($handling, $detaljer);
}

function perm_refuse(string $what, string $page): void
{
	global $sprog_id;
	audit_log('denied', $what . ' ' . $page);
	// std_func.php (findtekst/tekstboks) may not be loaded yet when called from online.php.
	$txt = function_exists('findtekst')
		? findtekst('5548|Du har ikke adgang til denne funktion. Kontakt din administrator.', isset($sprog_id) ? (int) $sprog_id : 1)
		: 'Du har ikke adgang til denne funktion. Kontakt din administrator.';
	if (function_exists('tekstboks')) {
		print tekstboks($txt);
	} else {
		print "<p>" . htmlspecialchars($txt) . "</p>";
	}
	exit;
}

// ------------------------------------------------------------------ current user

/**
 * Level of the current session's user for a key. Auditor/master-admin sessions
 * (bruger_id -1) keep their rettigheder string in 'log' mode; in 'deny' mode they
 * get the read-only Revisor role (spec decision 2).
 */
function perm_level(string $key): string
{
	global $bruger_id, $rettigheder, $revisor;
	static $cache = null;
	if ($cache === null) {
		if (!empty($revisor) && perm_enforcement_mode() === 'deny') {
			$cache = perm_levels_from_role(perm_role_id_by_key('revisor'));
		} else {
			$cache = perm_user_levels((int) $bruger_id, (string) $rettigheder);
		}
	}
	return isset($cache[$key]) ? $cache[$key] : 'none';
}

function perm_role_id_by_key(string $roleKey): int
{
	foreach (perm_roles() as $role) {
		if ($role['key'] === $roleKey) {
			return $role['id'];
		}
	}
	return 0;
}

function perm_can(string $key, string $level = 'read'): bool
{
	$rank = perm_level_rank();
	return $rank[perm_level($key)] >= $rank[perm_level_valid($level)];
}

/**
 * Central gate (spec R4). Denied requests are logged, as the message has always claimed.
 */
function require_permission(string $key, string $level = 'read'): void
{
	global $sprog_id;
	if (perm_can($key, $level)) {
		return;
	}
	perm_refuse($key . ' (' . $level . ')', (string) parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH));
}

// ------------------------------------------------------------------ roles

/**
 * @return array<int, array{id: int, key: string, navn: string, beskrivelse: string, system: bool}>
 */
function perm_roles(): array
{
	$roles = array();
	if (!perm_tables_ready()) {
		return $roles;
	}
	$q = db_select("select id, role_key, navn, beskrivelse, system from roles order by system desc, navn", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$roles[] = array(
			'id'          => (int) $r['id'],
			'key'         => (string) $r['role_key'],
			'navn'        => (string) $r['navn'],
			'beskrivelse' => (string) $r['beskrivelse'],
			'system'      => ($r['system'] === 't' || $r['system'] === true || $r['system'] === '1' || $r['system'] === 1),
		);
	}
	return $roles;
}

/**
 * Display name of a role: built-in roles are translated via the registry, custom ones as typed.
 */
function perm_role_name(array $role, int $sprogId): string
{
	if ($role['key'] !== '') {
		$defaults = permission_default_roles();
		if (isset($defaults[$role['key']])) {
			return findtekst($defaults[$role['key']]['label'], $sprogId);
		}
	}
	return $role['navn'];
}

/**
 * Description in the user's language for built-in roles; custom roles show what was typed.
 */
function perm_role_description(array $role, int $sprogId): string
{
	if ($role['key'] !== '') {
		$defaults = permission_default_roles();
		if (isset($defaults[$role['key']])) {
			return findtekst($defaults[$role['key']]['beskrivelse'], $sprogId);
		}
	}
	return $role['beskrivelse'];
}

/**
 * Replace a role's permissions and re-render the legacy string of every user holding it.
 *
 * @param array<string, string> $levels
 */
function perm_save_role_levels(int $roleId, array $levels): void
{
	db_modify("delete from role_permissions where role_id = $roleId", __FILE__ . " linje " . __LINE__);
	foreach (permission_registry() as $key => $def) {
		$level = isset($levels[$key]) ? perm_level_valid($levels[$key]) : 'none';
		if ($level === 'write' && !empty($def['readonly'])) {
			$level = 'read'; // Oversigt keys are read-only (addendum 4 §2.4)
		}
		if ($level !== 'none') {
			db_modify("insert into role_permissions (role_id, permission_key, level) values ($roleId, '" . db_escape_string($key) . "', '$level')", __FILE__ . " linje " . __LINE__);
		}
	}
	perm_sync_role($roleId);
}

/**
 * Keep brugere.rettigheder equal to the role, so every legacy substr() check follows the role.
 */
function perm_sync_user(int $brugerId): void
{
	if (!perm_tables_ready()) {
		return;
	}
	$r = db_fetch_array(db_select("select role_id from brugere where id = $brugerId", __FILE__ . " linje " . __LINE__));
	if (!$r || (int) $r['role_id'] <= 0) {
		return;
	}
	$legacy = perm_legacy_string(perm_levels_from_role((int) $r['role_id']));
	db_modify("update brugere set rettigheder = '$legacy' where id = $brugerId", __FILE__ . " linje " . __LINE__);
}

function perm_sync_role(int $roleId): void
{
	$legacy = perm_legacy_string(perm_levels_from_role($roleId));
	db_modify("update brugere set rettigheder = '$legacy' where role_id = $roleId", __FILE__ . " linje " . __LINE__);
}

/**
 * True when every level in $levels is within what the current user holds (spec R5:
 * nobody can hand out more than they have themselves).
 *
 * @param array<string, string> $levels
 */
function perm_within_own(array $levels): bool
{
	$rank = perm_level_rank();
	foreach (permission_registry() as $key => $def) {
		$wanted = isset($levels[$key]) ? perm_level_valid($levels[$key]) : 'none';
		if ($rank[$wanted] > $rank[perm_level($key)]) {
			return false;
		}
	}
	return true;
}

/**
 * Nearest built-in role for a legacy string (migration suggestion, spec 3.3 step 3).
 * Distance = number of legacy positions whose level differs; ties go to the first role.
 *
 * @return array{id: int, key: string, distance: int}|null
 */
function perm_suggest_role(string $rettigheder): ?array
{
	if (!perm_tables_ready()) {
		return null;
	}
	$target = perm_levels_from_legacy($rettigheder);
	$best = null;
	foreach (perm_roles() as $role) {
		if (!$role['system']) {
			continue;
		}
		$levels = perm_levels_from_role($role['id']);
		$distance = 0;
		foreach (permission_registry() as $key => $def) {
			if ($def['legacy'] && $levels[$key] !== $target[$key]) {
				$distance++;
			}
		}
		if ($best === null || $distance < $best['distance']) {
			$best = array('id' => $role['id'], 'key' => $role['key'], 'distance' => $distance);
		}
	}
	return $best;
}

/**
 * Seed the built-in roles once, and give users who already hold every legacy right the
 * Administrator role (an exact match; everyone else is suggested, not moved).
 * Idempotent: called from includes/betweenUpdates.php.
 */
function perm_ensure_default_roles(): void
{
	if (!perm_tables_ready()) {
		return;
	}
	$existing = array();
	$fresh = array();
	foreach (perm_roles() as $role) {
		if ($role['key'] !== '') {
			$existing[$role['key']] = $role['id'];
		}
	}
	foreach (permission_default_roles() as $key => $def) {
		if (isset($existing[$key])) {
			continue;
		}
		$fresh[$key] = true;
		$navn = db_escape_string(explode('|', $def['label'], 2)[1]);
		$beskrivelse = db_escape_string(explode('|', $def['beskrivelse'], 2)[1]);
		db_modify("insert into roles (role_key, navn, beskrivelse, system) values ('$key', '$navn', '$beskrivelse', true)", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select id from roles where role_key = '$key'", __FILE__ . " linje " . __LINE__));
		if ($r) {
			$roleId = (int) $r['id'];
			foreach ($def['levels'] as $permKey => $level) {
				db_modify("insert into role_permissions (role_id, permission_key, level) values ($roleId, '" . db_escape_string($permKey) . "', '" . perm_level_valid($level) . "')", __FILE__ . " linje " . __LINE__);
			}
			$existing[$key] = $roleId;
		}
	}
	perm_seed_new_keys($existing, $fresh);
	if (isset($existing['administrator'])) {
		$adminId = (int) $existing['administrator'];
		db_modify("update brugere set role_id = $adminId where (role_id is null or role_id = 0) and rettigheder = '1111111111111111'", __FILE__ . " linje " . __LINE__);
	}
}

/**
 * Keys added to the registry after roles were created get a level once: built-in roles
 * their default, custom roles the level of the key they derive from (e.g. pos.kasse from
 * Debitorordre), so nobody loses what they could do before. Remembered in settings so a
 * level an admin later removes is not re-added.
 *
 * @param array<string, int> $existing role_key => id of the built-in roles
 */
function perm_seed_new_keys(array $existing, array $fresh = array()): void
{
	$r = db_fetch_array(db_select("select id, var_value from settings where var_grp = 'permissions' and var_name = 'known_keys'", __FILE__ . " linje " . __LINE__));
	$known = $r ? array_filter(explode(',', (string) $r['var_value'])) : array();
	$registry = permission_registry();
	if (!$r) {
		// First run with this mechanism: every key without a 'since' marker was seeded with the roles.
		foreach ($registry as $key => $def) {
			if (!isset($def['since'])) {
				$known[] = $key;
			}
		}
	}
	// A renamed key keeps what roles had under its old name. Without a known_keys row the old
	// name may still be in role_permissions, so the (harmless) update runs in that case too.
	foreach ($registry as $key => $def) {
		if (!empty($def['renamed_from']) && (!$r || (in_array($def['renamed_from'], $known, true) && !in_array($key, $known, true)))) {
			db_modify("update role_permissions set permission_key = '" . db_escape_string($key) . "' where permission_key = '" . db_escape_string($def['renamed_from']) . "'", __FILE__ . " linje " . __LINE__);
			if (!in_array($key, $known, true)) {
				$known[] = $key;
			}
		}
	}
	$new = array_diff(array_keys($registry), $known);
	if ($new) {
		$defaults = permission_default_roles();
		$builtIn = array_flip($existing);
		$legacyKeyOf = array();
		foreach ($registry as $key => $def) {
			foreach ($def['legacy'] as $pos) {
				if (!isset($legacyKeyOf[$pos])) {
					$legacyKeyOf[$pos] = $key;
				}
			}
		}
		foreach (perm_roles() as $role) {
			$levels = perm_levels_from_role($role['id']);
			foreach ($new as $key) {
				// A role that already holds the key (a company created with it in the defaults) keeps what it has.
				if (db_fetch_array(db_select("select permission_key from role_permissions where role_id = " . (int) $role['id'] . " and permission_key = '" . db_escape_string($key) . "'", __FILE__ . " linje " . __LINE__))) {
					continue;
				}
				$level = 'none';
				$freshBuiltIn = isset($builtIn[$role['id']]) && isset($fresh[$builtIn[$role['id']]]);
				if (isset($registry[$key]['derive_keys']) && !$freshBuiltIn) {
					// Oversigt blocks at upgrade (addendum 4 §5): derived from the role's module keys, built-in roles too, so nobody
					// loses a block. A built-in role created in this run (a new company) takes the §4 table below instead.
					$level = perm_derive_level($registry[$key]['derive_keys'], $levels);
				} elseif (isset($builtIn[$role['id']]) && isset($defaults[$builtIn[$role['id']]]['levels'][$key])) {
					$level = $defaults[$builtIn[$role['id']]]['levels'][$key];
				} elseif (!isset($builtIn[$role['id']]) && isset($registry[$key]['derive']) && isset($legacyKeyOf[$registry[$key]['derive']])) {
					$level = $levels[$legacyKeyOf[$registry[$key]['derive']]];
				}
				if ($level !== 'none') {
					db_modify("delete from role_permissions where role_id = " . (int) $role['id'] . " and permission_key = '" . db_escape_string($key) . "'", __FILE__ . " linje " . __LINE__);
					db_modify("insert into role_permissions (role_id, permission_key, level) values (" . (int) $role['id'] . ", '" . db_escape_string($key) . "', '" . perm_level_valid($level) . "')", __FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	$value = db_escape_string(implode(',', array_keys($registry)));
	if ($r) {
		db_modify("update settings set var_value = '$value' where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("insert into settings (var_grp, var_name, var_value, var_description) values ('permissions', 'known_keys', '$value', 'Permission keys already seeded into roles')", __FILE__ . " linje " . __LINE__);
	}
}

// ------------------------------------------------------------------ migration to roles (spec §6)

/**
 * The old rights string as 16 positions; '' stays '' (old databases without rights).
 */
function perm_normalize_legacy(string $rettigheder): string
{
	$rettigheder = trim($rettigheder);
	if ($rettigheder === '') {
		return '';
	}
	return substr(str_pad(preg_replace('/[^012]/', '0', $rettigheder), 16, '0'), 0, 16);
}

/**
 * The standard role with the fewest extra rights that still covers everything the user had
 * (spec §6.3). Administrator covers everything, so there is always an answer.
 */
function perm_covering_role(string $rettigheder): ?array
{
	$target = perm_levels_from_legacy(perm_normalize_legacy($rettigheder));
	$rank = perm_level_rank();
	$best = null;
	foreach (perm_roles() as $role) {
		if (!$role['system']) {
			continue;
		}
		$levels = perm_levels_from_role($role['id']);
		$extra = 0;
		$covers = true;
		foreach (permission_registry() as $key => $def) {
			if (!$def['legacy']) {
				continue;
			}
			$diff = $rank[$levels[$key]] - $rank[$target[$key]];
			if ($diff < 0) {
				$covers = false;
				break;
			}
			$extra += $diff;
		}
		if ($covers && ($best === null || $extra < $best['extra'])) {
			$best = array('id' => $role['id'], 'key' => $role['key'], 'extra' => $extra);
		}
	}
	return $best;
}

/**
 * Give every user without a role one (spec §6.2): all rights → Administrator, a string equal
 * to a standard role → that role, anything else → one "Custom role <n>" per pattern with
 * exactly the rights the user had; custom cases are kept for review (§6.3). Idempotent: only
 * users without a role are touched, and what a user can do never changes: users with no
 * rights string at all (e.g. Sager workers, who live on their sag rights) are left without a
 * role, and the rights string is only cut to 16 positions, never rewritten.
 */
function perm_migrate_users(): void
{
	global $sprog_id;
	if (!perm_tables_ready()) {
		return;
	}
	$q = db_select("select id, brugernavn, rettigheder from brugere where role_id is null or role_id = 0", __FILE__ . " linje " . __LINE__);
	$pending = array();
	while ($r = db_fetch_array($q)) {
		$pending[] = $r;
	}
	if (!$pending) {
		return;
	}
	$standard = array();
	foreach (perm_roles() as $role) {
		if ($role['system']) {
			$standard[perm_legacy_string(perm_levels_from_role($role['id']))] = $role['id'];
		}
	}
	$adminId = perm_role_id_by_key('administrator');
	$sprog = isset($sprog_id) ? (int) $sprog_id : 1;
	$counts = perm_migration_summary();
	foreach ($pending as $r) {
		$id = (int) $r['id'];
		$string = perm_normalize_legacy((string) $r['rettigheder']);
		$review = false;
		if ($string === '') {
			continue;
		} elseif ($string === str_repeat('1', 16)) {
			$roleId = $adminId;
		} elseif (isset($standard[$string])) {
			$roleId = $standard[$string];
		} else {
			$roleId = perm_custom_role_for($string, $sprog);
			$review = true;
		}
		if ($roleId <= 0) {
			continue;
		}
		$keep = (strlen(trim((string) $r['rettigheder'])) > 16) ? ", rettigheder = '$string'" : '';
		db_modify("update brugere set role_id = $roleId$keep where id = $id", __FILE__ . " linje " . __LINE__);
		if ($review) {
			db_modify("delete from settings where var_grp = 'permissions' and var_name = 'review' and user_id = $id", __FILE__ . " linje " . __LINE__);
			db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('permissions', 'review', '" . db_escape_string((string) $r['rettigheder']) . "', 'Role set by the migration, not confirmed yet', $id)", __FILE__ . " linje " . __LINE__);
		}
		audit_log('user.role_changed', (string) $r['brugernavn'] . ': - -> ' . perm_role_label_plain($roleId), 'bruger', (string) $id, 'migrering');
		$counts['total']++;
		if ($roleId === $adminId) {
			$counts['admin']++;
		} elseif ($review) {
			$counts['custom']++;
		}
	}
	perm_save_migration_summary($counts);
}

/**
 * The custom role for one rights pattern, created the first time the pattern is seen.
 */
function perm_custom_role_for(string $string, int $sprog): int
{
	$key = 'migrated_' . $string;
	$existing = perm_role_id_by_key($key);
	if ($existing > 0) {
		return $existing;
	}
	$n = 1;
	$r = db_fetch_array(db_select("select count(*) as antal from roles where role_key like 'migrated_%'", __FILE__ . " linje " . __LINE__));
	if ($r) {
		$n = (int) $r['antal'] + 1;
	}
	$levels = perm_levels_from_legacy($string);
	$levels['settings.audit.read'] = 'none';
	$grants = array();
	foreach (permission_registry() as $permKey => $def) {
		if ($def['legacy'] && $levels[$permKey] !== 'none') {
			$grants[] = findtekst($def['label'], $sprog) . ($levels[$permKey] === 'read' ? ' (' . findtekst('5893|læs', $sprog) . ')' : '');
		}
	}
	$navn = sprintf(findtekst('5877|Tilpasset rolle %s', $sprog), $n);
	$beskrivelse = sprintf(findtekst('5878|Oprettet ved overgangen til roller ud fra de gamle rettigheder: %s', $sprog), implode(', ', $grants));
	db_modify("insert into roles (role_key, navn, beskrivelse, system) values ('" . db_escape_string($key) . "', '" . db_escape_string($navn) . "', '" . db_escape_string(mb_substr($beskrivelse, 0, 1000)) . "', false)", __FILE__ . " linje " . __LINE__);
	$roleId = perm_role_id_by_key($key);
	if ($roleId > 0) {
		perm_save_role_levels($roleId, $levels);
		audit_log('role.created', $navn . ': ' . $string, 'rolle', (string) $roleId, 'migrering');
	}
	return $roleId;
}

function perm_role_label_plain(int $roleId): string
{
	foreach (perm_roles() as $role) {
		if ($role['id'] === $roleId) {
			return $role['navn'];
		}
	}
	return (string) $roleId;
}

/**
 * @return array{total: int, admin: int, custom: int}
 */
function perm_migration_summary(): array
{
	$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'permissions' and var_name = 'migration_summary'", __FILE__ . " linje " . __LINE__));
	$parts = $r ? array_map('intval', explode(';', (string) $r['var_value'])) : array();
	return array('total' => isset($parts[0]) ? $parts[0] : 0, 'admin' => isset($parts[1]) ? $parts[1] : 0, 'custom' => isset($parts[2]) ? $parts[2] : 0);
}

function perm_save_migration_summary(array $c): void
{
	$value = (int) $c['total'] . ';' . (int) $c['admin'] . ';' . (int) $c['custom'];
	$r = db_fetch_array(db_select("select id from settings where var_grp = 'permissions' and var_name = 'migration_summary'", __FILE__ . " linje " . __LINE__));
	if ($r) {
		db_modify("update settings set var_value = '$value' where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('permissions', 'migration_summary', '$value', 'Users moved to roles: total;administrators;custom', 0)", __FILE__ . " linje " . __LINE__);
	}
}

/**
 * Users whose migrated role is not confirmed yet: id => the rights string they had.
 *
 * @return array<int, string>
 */
function perm_review_pending(): array
{
	$out = array();
	if (!perm_tables_ready()) {
		return $out;
	}
	$q = db_select("select user_id, var_value from settings where var_grp = 'permissions' and var_name = 'review'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['user_id']] = (string) $r['var_value'];
	}
	return $out;
}

/**
 * The administrator has decided on this user's role. Custom roles from the migration that
 * no one holds any more are removed (spec §6.3).
 */
function perm_review_done(int $brugerId): void
{
	db_modify("delete from settings where var_grp = 'permissions' and var_name = 'review' and user_id = $brugerId", __FILE__ . " linje " . __LINE__);
	$q = db_select("select r.id, r.navn from roles r where r.role_key like 'migrated_%' and not exists (select 1 from brugere b where b.role_id = r.id)", __FILE__ . " linje " . __LINE__);
	$empty = array();
	while ($r = db_fetch_array($q)) {
		$empty[] = $r;
	}
	foreach ($empty as $r) {
		db_modify("delete from role_permissions where role_id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
		db_modify("delete from roles where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
		audit_log('role.deleted', (string) $r['navn'], 'rolle', (string) $r['id'], 'migrering');
	}
}

// ------------------------------------------------------------------ audit log
// 20261004 Sawaneh The user name in audit rows is read raw (audit_user_name()); the escaped global was escaped twice.
// 20261003 Sawaneh Roles spec §3 alignment for SD-724: handling up to 60 chars, detaljer no longer cut at 2000 chars,
//                  audit_log_write() with the document-pool argument order.

/**
 * The current user's name unescaped. online.php keeps $brugernavn SQL-escaped, so escaping that global again for an
 * insert stored O'Brien as O''Brien; online.php now also keeps the raw name.
 */
function audit_user_name(): string
{
	global $brugernavn, $brugernavn_raw;
	if (isset($brugernavn_raw)) {
		return (string) $brugernavn_raw;
	}
	// No online.php in this request (login form, API, cron): the global, if any, is not escaped.
	return isset($brugernavn) ? (string) $brugernavn : '';
}

/**
 * Record who did what (spec R7). Never throws: an install without the table just skips.
 */
function audit_log(string $handling, string $detaljer = '', string $objektType = '', string $objektId = '', string $kilde = 'ui'): void
{
	global $bruger_id, $brugernavn;
	static $extended = null;
	if (!audit_ready()) {
		return;
	}
	if ($extended === null) {
		$extended = (bool) db_fetch_array(db_select("select column_name from information_schema.columns where table_name = 'audit_log' and column_name = 'objekt_type'", __FILE__ . " linje " . __LINE__));
	}
	$id = isset($bruger_id) ? (int) $bruger_id : 0;
	$navn = db_escape_string(audit_user_name());
	$ip = db_escape_string(isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : '');
	$handling = db_escape_string(substr($handling, 0, 60));
	$detaljer = db_escape_string($detaljer);
	if ($extended) {
		$qtxt = "insert into audit_log (bruger_id, brugernavn, handling, detaljer, ip, objekt_type, objekt_id, kilde) values ($id, '$navn', '$handling', '$detaljer', '$ip', ";
		$qtxt .= "'" . db_escape_string(substr($objektType, 0, 30)) . "', '" . db_escape_string(substr($objektId, 0, 60)) . "', '" . db_escape_string(substr($kilde, 0, 30)) . "')";
	} else {
		$qtxt = "insert into audit_log (bruger_id, brugernavn, handling, detaljer, ip) values ($id, '$navn', '$handling', '$detaljer', '$ip')";
	}
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

/**
 * The same, in the argument order the document-pool work uses (SD-724). One table, one implementation.
 */
if (!function_exists('audit_log_write')) {
	// SD-724 ships the same function on master (includes/auditLog.php) until this branch lands; one of them wins.
	function audit_log_write(string $handling, string $objektType = '', string $objektId = '', string $detaljer = '', string $kilde = 'ui'): void
	{
		audit_log($handling, $detaljer, $objektType, $objektId, $kilde);
	}
}
