<?php
// 20261110 CDX/ASR Atomic, revision-checked role editing and all-or-nothing holder transfers.
// 20261114 CL/ASR The built-in role flag is read and written as is_built_in (master's rename of roles.system, d7cdeb81).

require_once __DIR__ . '/userFunctions.php';

/** @return string Empty on success, otherwise a user-facing message code. */
function perm_role_edit_error(array $role, array $before, array $after, string $revision, int $selfId): string
{
	if ($revision === '' || !hash_equals(perm_role_revision($role, $before), $revision)) {
		return 'rolestale';
	}
	if (!perm_within_own($before) || !perm_within_own($after)) {
		return 'escalation';
	}
	if ((string) $role['role_key'] === 'administrator') {
		foreach (array('settings.users.manage', 'settings.roles.manage') as $key) {
			if ($after[$key] !== 'write') {
				return 'adminlock';
			}
		}
	}
	$self = $selfId > 0 ? db_fetch_array(db_select("select role_id from brugere where id = $selfId", __FILE__ . ' linje ' . __LINE__)) : null;
	$selfRole = $self ? (int) $self['role_id'] : (!empty($GLOBALS['revisor']) && perm_enforcement_mode() === 'deny' ? perm_role_id_by_key('revisor') : 0);
	if ($selfRole === (int) $role['id'] && perm_levels_reduced($before, $after)) {
		return 'ownrolereduction';
	}
	return '';
}

/** @return array{id: int, error: string} Save/reset includes metadata, rights, users and audit in one transaction. */
function perm_edit_role(array $post, int $selfId, bool $reset = false): array
{
	return perm_atomic(function () use ($post, $selfId, $reset) {
		$id = (int) ($post['id'] ?? 0);
		$roles = perm_lock_roles();
		if (!perm_can('settings.roles.manage', 'write')) {
			return array('id' => $id, 'error' => 'noaccess');
		}
		$role = $roles[$id] ?? null;
		if (($id !== 0 && !$role) || ($reset && !$role)) {
			return array('id' => $id, 'error' => 'rolemissing');
		}
		$before = $role ? perm_levels_from_role($id) : perm_normalize_levels(array());
		$levels = perm_normalize_levels(is_array($post['level'] ?? null) ? $post['level'] : array());
		$name = trim((string) ($post['navn'] ?? ''));
		$description = mb_substr(trim((string) ($post['beskrivelse'] ?? '')), 0, 500);
		$system = $role && in_array($role['is_built_in'], array(true, 't', 1, '1'), true);
		if ($system) {
			$name = (string) $role['navn'];
			$description = (string) $role['beskrivelse'];
		}
		if ($reset) {
			$defaults = permission_default_roles();
			if (!$system || !isset($defaults[$role['role_key']])) {
				return array('id' => $id, 'error' => 'rolemissing');
			}
			$levels = perm_normalize_levels($defaults[$role['role_key']]['levels']);
		}
		if ($name === '' || mb_strlen($name) > 80) {
			return array('id' => $id, 'error' => 'name');
		}
		foreach ($roles as $otherId => $other) {
			if ((!$role || $name !== (string) $role['navn']) && $otherId !== $id && mb_strtolower(trim((string) $other['navn'])) === mb_strtolower($name)) {
				return array('id' => $id, 'error' => 'rolenameexists');
			}
		}
		$error = $role ? perm_role_edit_error($role, $before, $levels, (string) ($post['role_revision'] ?? ''), $selfId)
			: (perm_within_own($levels) ? '' : 'escalation');
		if ($error !== '') {
			return array('id' => $id, 'error' => $error);
		}
		$source = (int) ($post['role_source'] ?? 0);
		if (!$role && $source > 0 && (!isset($roles[$source]) || !perm_within_own(perm_levels_from_role($source)))) {
			return array('id' => $id, 'error' => 'escalation');
		}
		$nameSql = db_escape_string($name);
		$descriptionSql = db_escape_string($description);
		if ($id === 0) {
			perm_write("insert into roles (role_key, navn, beskrivelse, is_built_in) values (null, '$nameSql', '$descriptionSql', false)");
			// The locked role rows serialize creators using this service; the name was checked above.
			$created = db_fetch_array(db_select("select id from roles where navn = '$nameSql' order by id desc limit 1", __FILE__ . ' linje ' . __LINE__));
			if (!$created) {
				throw new RuntimeException('New role was not found');
			}
			$id = (int) $created['id'];
		} elseif (!$reset) {
			perm_write("update roles set navn = '$nameSql', beskrivelse = '$descriptionSql', role_key = case when role_key like 'migrated_%' then null else role_key end where id = $id");
		}
		perm_save_role_levels($id, $levels);
		$subject = $name;
		if ($role && ((string) $role['navn'] !== $name || (string) $role['beskrivelse'] !== $description)) {
			$subject .= "\nname: " . $role['navn'] . ' -> ' . $name . "\ndescription: " . $role['beskrivelse'] . ' -> ' . $description;
		}
		if (!$role && $source > 0) {
			$subject .= ' (source role ' . $source . ')';
		}
		audit_log($reset ? 'role.reset' : ($role ? 'role.updated' : 'role.created'), perm_change_details($subject, $before, $levels), 'rolle', (string) $id);
		return array('id' => $id, 'error' => '');
	});
}

/** @return string Message code; every holder is checked before the first transfer. */
function perm_delete_role(int $id, int $moveTo, int $selfId, string $revision): string
{
	return perm_atomic(function () use ($id, $moveTo, $selfId, $revision) {
		$roles = perm_lock_roles();
		if (!perm_can('settings.roles.manage', 'write')) {
			return 'noaccess';
		}
		$role = $roles[$id] ?? null;
		if (!$role || in_array($role['is_built_in'], array(true, 't', 1, '1'), true)) {
			return 'rolemissing';
		}
		$before = perm_levels_from_role($id);
		if ($revision === '' || !hash_equals(perm_role_revision($role, $before), $revision)) {
			return 'rolestale';
		}
		if (!perm_within_own($before)) {
			return 'escalation';
		}
		$holders = array();
		$q = db_select("select * from brugere where role_id = $id order by id for update", __FILE__ . ' linje ' . __LINE__);
		while ($r = db_fetch_array($q)) {
			$holders[] = $r;
		}
		if ($holders && ($moveTo <= 0 || $moveTo === $id || !isset($roles[$moveTo]))) {
			return 'roleinuse';
		}
		foreach ($holders as $holder) {
			$error = user_role_change_error($holder, $moveTo, $selfId, $roles);
			if ($error !== '') {
				return $error;
			}
		}
		foreach ($holders as $holder) {
			$error = user_set_role((int) $holder['id'], $moveTo, $selfId, 'role_delete');
			if ($error !== '') {
				throw new RuntimeException('A preflighted role transfer failed');
			}
		}
		perm_write("delete from role_permissions where role_id = $id");
		perm_write("delete from roles where id = $id");
		audit_log('role.deleted', perm_change_details((string) $role['navn'] . ' (moved ' . count($holders) . ' users to role ' . $moveTo . ')', $before, array()), 'rolle', (string) $id);
		return '';
	});
}
