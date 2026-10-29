<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/usersRoles.php --- lap 5.0.0 --- 2026.09.16 ---
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
// 20260916 Sawaneh "Brugere & roller" (spec R8): user list with search, user card with role,
//                  role list + matrix editor, bulk role assignment, migration suggestions and
//                  the audit log. Replaces brugere.php (and brugereRevisor.php). Controller here,
//                  view in usersRolesIncludes/view.php. Post/Redirect/Get throughout.

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var string $brugernavn
 * @var int    $bruger_id
 * @var string $db
 * @var string $db_encode
 * @var int    $sprog_id
 * @var mixed  $revisor
 * @var mixed  $regnaar
 */

@session_start();
$s_id = session_id();
ob_start();

$title = "Brugere & roller";
$css = "../css/usersRoles.css?v=20260930b";
$modulnr = 1; // legacy gate (Indstillinger)
$permission_key = 'settings.users.manage';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/userFunctions.php");
include_once(__DIR__ . "/settingsRegistry.php");
include(__DIR__ . "/usersRolesIncludes/view.php");

// Rows per page of the audit log. Declared before any code that reads it: a const statement is
// not hoisted like the functions further down.
const UR_LOG_PAGE = 100;
const UR_ADMIN_LOCKED = array('settings.users.manage', 'settings.roles.manage');

require_permission('settings.users.manage', 'read');

$contextQuery = (!empty($_GET['inframe']) ? 'inframe=1' : '');

// Every posted form carries the session token (added to the page below), as in diverse.php.
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	require_permission('settings.users.manage', 'write');
	if (!isset($_POST['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
		audit_log('csrf', 'usersRoles.php');
		ob_end_clean();
		header('Location: usersRoles.php?' . ($contextQuery !== '' ? $contextQuery . '&' : '') . 'tab=users&msg=csrf');
		exit;
	}
	$redirect = ur_handle_post($_POST, (int) $bruger_id, (int) $regnaar);
	if (!empty($GLOBALS['user_sessions_to_end'])) {
		// Sessions live in the master database: back to it for this last write.
		$endSessions = $GLOBALS['user_sessions_to_end'];
		include(__DIR__ . "/../includes/connect.php");
		foreach ($endSessions as $endName) {
			db_modify("delete from online where brugernavn = '" . db_escape_string($endName) . "' and db = '" . db_escape_string((string) $db) . "'", __FILE__ . " linje " . __LINE__);
		}
	}
	ob_end_clean();
	header('Location: usersRoles.php?' . ($contextQuery !== '' ? $contextQuery . '&' : '') . $redirect);
	exit;
}

if (isset($_GET['tab'], $_GET['export']) && $_GET['tab'] === 'log' && $_GET['export'] === 'csv') {
	if (!perm_can('settings.audit.read', 'read') || !perm_can('settings.import_export', 'read')) {
		ob_end_clean();
		header('Location: usersRoles.php?' . ($contextQuery !== '' ? $contextQuery . '&' : '') . 'tab=log&msg=noaccess');
		exit;
	}
	ur_export_log($_GET, (string) $db_encode);
	exit;
}

$urToken = "<input type=\"hidden\" name=\"csrf_token\" value=\"" . htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES) . "\">";
ob_start(function ($buffer) use ($urToken) {
	return preg_replace('/(<form\b[^>]*\bmethod="post"[^>]*>)/i', '$1' . $urToken, $buffer);
});
$vm = ur_view_model($_GET, (int) $bruger_id, (int) $sprog_id, (string) $db_encode, $contextQuery);
ur_view($vm);
ob_end_flush();

// 20260929 Sawaneh Roles stage 2: close/reopen instead of delete, last-administrator and own-role rules, bulk
//                  close/reopen with one audit entry per user, reset of built-in roles; user operations
//                  moved to includes/userFunctions.php.
// 20260929 Sawaneh Roles stage 2 (§8.4): invite a user instead of choosing the password, resend, status Invited.
// 20260930 Sawaneh Roles stage 2 (§8.1, §8.2): reset password by mail, last active from live sessions.
// 20261014 Sawaneh Review: a role above the editor's own levels cannot be saved down, copied or deleted; an invitation
//                  is only resent for users the editor may manage.
// 20261016 Sawaneh Review (PR #803): bulk resend checks user_may_manage() like the single resend.
// 20261019 Sawaneh Review 2 (PR #803): audit-log search escapes backslash, % and _ so it is literal; the audit_log column check is schema-filtered.
// 20260930 Sawaneh Roles stage 2 (§6.3): review of migrated roles - confirm, use the suggestion, bulk; a role is required.
// 20261001 Sawaneh Review fixes: CSRF token on every posted form; users above the actor's own access cannot be
//                  changed (msg=above).
// 20261001 Sawaneh The Administrator role keeps write on users and roles (msg=adminlock); the department is
//                  stored for the user, not for the employee id.
// 20260930 Sawaneh Layout after prototype_brugere_roller.html: user drawer, role list + matrix, move users when deleting
//                  a role, IP list one per line (validated), username locked after creation.
// 20260930 Sawaneh Roles stage 2 (§7.2): audit log with filters, search, pages and CSV export; Roles tab behind
//                  settings.roles.manage, Log tab behind settings.audit.read.
// 20261008 Sawaneh SD-657 (from master's brugere.php): losing the Indstillinger right on save switches hideRevenue on.
// 20261009 Sawaneh Read-only Oversigt keys (addendum 4) are clamped to read when the matrix is saved.

// ================================================================== controller

/**
 * Dispatch a POST and return the query string to redirect to.
 */
function ur_handle_post(array $post, int $selfId, int $regnaar): string
{
	$action = isset($post['action']) ? (string) $post['action'] : '';
	$roleActions = array('reset_role', 'save_role', 'delete_role', 'copy_role', 'set_enforce');
	if (in_array($action, $roleActions, true) && !perm_can('settings.roles.manage', 'write')) {
		return 'tab=users&msg=noaccess';
	}
	switch ($action) {
		case 'save_user':
			return ur_save_user($post, $selfId, $regnaar);
		case 'delete_user':
			return ur_user_result(user_delete((int) ifset($post, 'id', 0), $selfId), (int) ifset($post, 'id', 0), 'userdeleted', false);
		case 'close_user':
			return ur_user_result(user_close((int) ifset($post, 'id', 0), $selfId), (int) ifset($post, 'id', 0), 'userclosed', true);
		case 'reopen_user':
			return ur_user_result(user_reopen((int) ifset($post, 'id', 0)), (int) ifset($post, 'id', 0), 'userreopened', true);
		case 'confirm_role':
			$id = (int) ifset($post, 'id', 0);
			$error = user_confirm_role($id, !empty($post['use']), $selfId);
			return 'tab=users&bruger=' . $id . '&msg=' . ($error === '' ? 'confirmed' : $error);
		case 'reset_password':
			$id = (int) ifset($post, 'id', 0);
			$error = user_reset_password($id, (int) $GLOBALS['sprog_id']);
			return 'tab=users&bruger=' . $id . '&msg=' . ($error === '' ? 'resetsent' : ($error === 'missing' ? 'usersaved' : $error));
		case 'resend_invite':
			return ur_resend_invite((int) ifset($post, 'id', 0));
		case 'bulk_role':
			return ur_bulk($post, $selfId);
		case 'reset_role':
			return ur_reset_role((int) ifset($post, 'id', 0));
		case 'apply_suggestions':
			return ur_apply_suggestions();
		case 'save_role':
			return ur_save_role($post);
		case 'delete_role':
			return ur_delete_role((int) ifset($post, 'id', 0), (int) ifset($post, 'move_to', 0), $selfId);
		case 'copy_role':
			return ur_copy_role((int) ifset($post, 'id', 0));
		case 'set_enforce':
			perm_set_enforcement_mode((string) ifset($post, 'mode', 'log'));
			return 'tab=log&msg=enforce';
	}
	return 'tab=users';
}

/**
 * True when the current user may hand out this role (spec R5: no self-escalation).
 */
function ur_role_assignable(int $roleId): bool
{
	if ($roleId <= 0) {
		return true;
	}
	return perm_within_own(perm_levels_from_role($roleId));
}

function ur_save_user(array $post, int $selfId, int $regnaar): string
{
	$id = (int) ifset($post, 'id', 0);
	$navn = trim((string) ifset($post, 'brugernavn', ''));
	$kode = trim((string) ifset($post, 'kode', ''));
	$kode2 = trim((string) ifset($post, 'kode2', ''));
	$roleId = (int) ifset($post, 'role_id', 0);
	$ansatId = (int) ifset($post, 'ansat_id', 0);
	$ipList = array_values(array_filter(preg_split('/[\s,;]+/', trim((string) ifset($post, 'ip_address', '')))));
	$ip = implode(',', $ipList);
	$tlf = trim((string) ifset($post, 'tlf', ''));
	$email = trim((string) ifset($post, 'email', ''));
	$twofactor = !empty($post['twofactor']) ? 't' : 'f';
	$isRevisor = !empty($post['revisor']);
	if ($id > 0) {
		// The username is locked after creation (spec §8.2).
		$current = db_fetch_array(db_select("select brugernavn from brugere where id = $id", __FILE__ . " linje " . __LINE__));
		if ($current) {
			$navn = (string) $current['brugernavn'];
		}
	}
	$invite = ($id === 0 && ifset($post, 'mode', 'invite') !== 'classic');
	$back = 'tab=users&bruger=' . $id . ($id === 0 && !$invite ? '&mode=classic' : '');

	if ($navn === '' || mb_strlen($navn) > 80) {
		return $back . '&msg=name';
	}
	if ($roleId <= 0) {
		return $back . '&msg=norole';
	}
	foreach ($ipList as $one) {
		if (!filter_var($one, FILTER_VALIDATE_IP)) {
			return $back . '&msg=badip';
		}
	}
	if (strlen($ip) > 45) {
		return $back . '&msg=iptoolong';
	}
	if ($kode !== '' && $kode !== $kode2) {
		return $back . '&msg=pwmismatch';
	}
	if ($invite) {
		$kode = '';
		if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return $back . '&msg=emailrequired';
		}
	} elseif ($id === 0 && $kode === '') {
		return $back . '&msg=pwrequired';
	}
	$navnSql = db_escape_string($navn);
	$dup = db_fetch_array(db_select("select id from brugere where brugernavn = '$navnSql' and id != $id", __FILE__ . " linje " . __LINE__));
	if ($dup) {
		return $back . '&msg=duplicate';
	}

	$current = ($id > 0) ? db_fetch_array(db_select("select role_id, rettigheder from brugere where id = $id", __FILE__ . " linje " . __LINE__)) : null;
	if ($current && $id !== $selfId && !user_may_manage($current)) {
		return $back . '&msg=above';
	}
	$currentRole = $current ? (int) $current['role_id'] : 0;
	if ($roleId !== $currentRole) {
		if (!ur_role_assignable($roleId)) {
			return $back . '&msg=escalation';
		}
		if ($id > 0 && $id === $selfId) {
			return $back . '&msg=ownrole';
		}
		if ($id > 0 && user_is_last_admin($id)) {
			return $back . '&msg=lastadmin';
		}
	}

	$ipSql = db_escape_string(mb_substr($ip, 0, 45));
	$tlfSql = db_escape_string(mb_substr($tlf, 0, 16));
	$emailSql = db_escape_string($email);
	$ansatSql = $ansatId > 0 ? (string) $ansatId : '0';

	$inviteToken = '';
	if ($id === 0) {
		$data = array(
			'brugernavn' => $navn, 'kode' => $kode, 'role_id' => $roleId, 'ansat_id' => $ansatId, 'ip_address' => $ip,
			'tlf' => $tlf, 'email' => $email, 'twofactor' => ($twofactor === 't'), 'regnskabsaar' => $regnaar,
		);
		if ($invite) {
			$invited = user_invite($data);
			$id = $invited['id'];
			$inviteToken = $invited['token'];
		} else {
			$id = user_create($data);
		}
	} else {
		$qtxt = "update brugere set brugernavn = '$navnSql', ansat_id = $ansatSql, ip_address = '$ipSql', tlf = '$tlfSql', twofactor = '$twofactor', email = '$emailSql'";
		if ($kode !== '') {
			$qtxt .= ", kode = '" . db_escape_string(saldikrypt($id, $kode)) . "'";
		}
		$qtxt .= " where id = $id";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		audit_log('user.updated', $navn . ($kode !== '' ? ' (ny adgangskode)' : ''), 'bruger', (string) $id);
		if ($roleId !== $currentRole) {
			user_set_role($id, $roleId, $selfId);
		}
	}
	perm_sync_user($id);
	// SD-657: taking the Indstillinger right away switches hideRevenue on, once, so that user stops seeing turnover.
	$after = $current ? db_fetch_array(db_select("select rettigheder from brugere where id = $id", __FILE__ . " linje " . __LINE__)) : null;
	if ($after && substr((string) $current['rettigheder'], 1, 1) == '1' && substr((string) $after['rettigheder'], 1, 1) != '1'
		&& get_settings_value('hideRevenue', 'finans', 'off') !== 'on') {
		update_settings_value('hideRevenue', 'finans', 'on', 'Set when a user lost access to Settings');
	}

	if ($ansatId > 0) {
		$r = db_fetch_array(db_select("select afd from ansatte where id = $ansatId", __FILE__ . " linje " . __LINE__));
		if ($r) {
			update_settings_value('afd', 'brugerAfd', (int) $r['afd'], 'Department of employee', $id);
		}
	}
	ur_set_revisor_user($id, $isRevisor);
	if ($inviteToken !== '') {
		return 'tab=users&bruger=' . $id . '&msg=' . ur_send_invite($id, $inviteToken);
	}
	return 'tab=users&bruger=' . $id . '&msg=usersaved';
}

/**
 * Mail the invitation. Where the server cannot send mail the link is handed to the
 * administrator instead (spec §8.4), once, through the session.
 *
 * @return string the message key for the redirect
 */
function ur_send_invite(int $id, string $token): string
{
	global $sprog_id;
	if (user_invite_mail($id, $token, (int) $sprog_id)) {
		return 'invited';
	}
	$_SESSION['ur_invite_link'] = array('id' => $id, 'link' => user_invite_link($token));
	return 'invitelink';
}

function ur_resend_invite(int $id): string
{
	$row = $id > 0 ? db_fetch_array(db_select("select * from brugere where id = $id", __FILE__ . " linje " . __LINE__)) : null;
	if (!$row || !user_may_manage($row)) {
		return 'tab=users&bruger=' . $id . '&msg=escalation';
	}
	$token = user_invite_resend($id);
	if ($token === '') {
		return 'tab=users&bruger=' . $id . '&msg=notinvited';
	}
	return 'tab=users&bruger=' . $id . '&msg=' . ur_send_invite($id, $token);
}

/**
 * The company's designated auditor account (settings var 'revisor'): at most one user.
 */
function ur_set_revisor_user(int $id, bool $isRevisor): void
{
	$r = db_fetch_array(db_select("select id, user_id from settings where var_name = 'revisor' and var_grp = 'system'", __FILE__ . " linje " . __LINE__));
	$currentId = $r ? (int) $r['user_id'] : 0;
	if ($isRevisor && $currentId !== $id) {
		if ($r) {
			db_modify("update settings set user_id = $id where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
		} else {
			db_modify("insert into settings (var_name, var_grp, var_value, var_description, user_id) values ('revisor', 'system', '', 'Designated auditor user', $id)", __FILE__ . " linje " . __LINE__);
		}
		audit_log('revisor.set', 'bruger ' . $id);
	} elseif (!$isRevisor && $currentId === $id && $r) {
		db_modify("update settings set user_id = null where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
		audit_log('revisor.unset', 'bruger ' . $id);
	}
}

/**
 * Redirect target after a user operation: the success message, or the reason it was refused.
 */
function ur_user_result(string $error, int $id, string $okMsg, bool $stay): string
{
	if ($error === 'missing') {
		return 'tab=users';
	}
	if ($error !== '') {
		return 'tab=users&bruger=' . $id . '&msg=' . $error;
	}
	return 'tab=users' . ($stay ? '&bruger=' . $id : '') . '&msg=' . $okMsg;
}

/**
 * Bulk operations on the selected users (spec §8.1): assign role, close, reopen.
 * Every user gets its own audit entry; users the rules protect are skipped and counted.
 */
function ur_bulk(array $post, int $selfId): string
{
	$ids = isset($post['ids']) && is_array($post['ids']) ? array_filter(array_map('intval', $post['ids'])) : array();
	$what = isset($post['bulk']) ? (string) $post['bulk'] : 'role';
	if (!in_array($what, array('role', 'close', 'reopen', 'resend', 'suggest'), true)) {
		$what = 'role';
	}
	if (!$ids) {
		return 'tab=users';
	}
	$roleId = (int) ifset($post, 'role_id', 0);
	if ($what === 'role' && ($roleId <= 0 || !ur_role_assignable($roleId))) {
		return 'tab=users&msg=' . ($roleId <= 0 ? 'norole' : 'escalation');
	}
	// Only real changes count as done; everything else is reported with its reason.
	$done = 0;
	$why = array();
	foreach ($ids as $id) {
		if ($what === 'close') {
			$error = user_close($id, $selfId);
		} elseif ($what === 'reopen') {
			$error = user_reopen($id);
		} elseif ($what === 'suggest') {
			$error = user_confirm_role($id, true, $selfId);
		} elseif ($what === 'resend') {
			$row = db_fetch_array(db_select("select * from brugere where id = $id", __FILE__ . " linje " . __LINE__));
			$token = ($row && user_may_manage($row)) ? user_invite_resend($id) : '';
			$error = ($token === '') ? ($row && !user_may_manage($row) ? 'escalation' : 'notinvited') : (user_invite_mail($id, $token, (int) $GLOBALS['sprog_id']) ? '' : 'mailfailed');
		} else {
			$error = user_set_role($id, $roleId, $selfId);
		}
		if ($error === '') {
			$done++;
		} elseif ($error !== 'missing') {
			$why[$error] = isset($why[$error]) ? $why[$error] + 1 : 1;
		}
	}
	$reasons = array();
	foreach ($why as $reason => $count) {
		$reasons[] = $reason . ':' . $count;
	}
	return 'tab=users&msg=bulk&act=' . $what . '&done=' . $done . ($reasons ? '&why=' . implode(',', $reasons) : '');
}

/**
 * "reason:count,reason:count" from the redirect, validated.
 *
 * @return array<string, int>
 */
function ur_parse_why(string $raw): array
{
	$out = array();
	foreach (explode(',', $raw) as $part) {
		if (preg_match('/^([a-z]{1,20}):(\d{1,6})$/', $part, $m)) {
			$out[$m[1]] = (int) $m[2];
		}
	}
	return $out;
}

/**
 * Suggest/confirm (spec 3.3 step 3): the admin has confirmed, so every user without a
 * role gets the nearest built-in role - as long as the admin may hand it out.
 */
function ur_apply_suggestions(): string
{
	$q = db_select("select id, rettigheder from brugere where role_id is null or role_id = 0", __FILE__ . " linje " . __LINE__);
	$done = array();
	while ($r = db_fetch_array($q)) {
		$suggestion = perm_suggest_role((string) $r['rettigheder']);
		if (!$suggestion || !ur_role_assignable($suggestion['id'])) {
			continue;
		}
		db_modify("update brugere set role_id = " . $suggestion['id'] . " where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
		perm_sync_user((int) $r['id']);
		audit_log('user.role_changed', '- -> ' . user_role_label((int) $suggestion['id']), 'bruger', (string) $r['id'], 'migrering');
		$done[] = $r['id'];
	}
	return 'tab=users&msg=assigned';
}

/**
 * @return array<string, string> levels[key] from the matrix radios, unknown keys dropped.
 */
function ur_levels_from_post(array $post): array
{
	$levels = array();
	$posted = isset($post['level']) && is_array($post['level']) ? $post['level'] : array();
	foreach (permission_registry() as $key => $def) {
		$levels[$key] = isset($posted[$key]) ? perm_level_valid((string) $posted[$key]) : 'none';
		if ($levels[$key] === 'write' && !empty($def['readonly'])) {
			$levels[$key] = 'read';
		}
	}
	return $levels;
}

function ur_save_role(array $post): string
{
	$id = (int) ifset($post, 'id', 0);
	$navn = trim((string) ifset($post, 'navn', ''));
	$beskrivelse = trim((string) ifset($post, 'beskrivelse', ''));
	$levels = ur_levels_from_post($post);
	if ($navn === '' || mb_strlen($navn) > 80) {
		return 'tab=roles&rolle=' . $id . '&msg=name';
	}
	if (!perm_within_own($levels) || ($id > 0 && !perm_within_own(perm_levels_from_role($id)))) {
		return 'tab=roles&rolle=' . $id . '&msg=escalation';
	}
	$navnSql = db_escape_string($navn);
	$beskSql = db_escape_string(mb_substr($beskrivelse, 0, 500));
	$existing = ($id > 0) ? db_fetch_array(db_select("select role_key, navn, beskrivelse, system from roles where id = $id", __FILE__ . " linje " . __LINE__)) : null;
	// The Administrator role always keeps users and roles, so nobody can lock the company out (Adam 2026-10-01).
	if ($existing && $existing['role_key'] === 'administrator') {
		foreach (UR_ADMIN_LOCKED as $lockedKey) {
			if ($levels[$lockedKey] !== 'write') {
				return 'tab=roles&rolle=' . $id . '&msg=adminlock';
			}
		}
	}
	if ($existing && in_array($existing['system'], array('t', true, '1', 1), true)) {
		// Built-in roles keep their name and description; only the matrix can be edited (spec §3.2).
		$navn = (string) $existing['navn'];
		$navnSql = db_escape_string($navn);
		$beskSql = db_escape_string((string) $existing['beskrivelse']);
	}
	if ($id === 0) {
		db_modify("insert into roles (role_key, navn, beskrivelse, system) values (null, '$navnSql', '$beskSql', false)", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select id from roles where navn = '$navnSql' order by id desc limit 1", __FILE__ . " linje " . __LINE__));
		$id = (int) $r['id'];
		audit_log('role.created', $navn, 'rolle', (string) $id);
	} else {
		db_modify("update roles set navn = '$navnSql', beskrivelse = '$beskSql' where id = $id", __FILE__ . " linje " . __LINE__);
		audit_log('role.updated', $navn, 'rolle', (string) $id);
	}
	perm_save_role_levels($id, $levels);
	return 'tab=roles&rolle=' . $id . '&msg=rolesaved';
}

function ur_delete_role(int $id, int $moveTo = 0, int $selfId = 0): string
{
	if ($id <= 0) {
		return 'tab=roles';
	}
	$r = db_fetch_array(db_select("select navn, system from roles where id = $id", __FILE__ . " linje " . __LINE__));
	if (!$r || $r['system'] === 't' || $r['system'] === true || $r['system'] === '1' || $r['system'] === 1 || !perm_within_own(perm_levels_from_role($id))) {
		return 'tab=roles';
	}
	if ($moveTo > 0 && $moveTo !== $id) {
		// Move the users first, one by one with the usual rules and one log entry each.
		$q = db_select("select id from brugere where role_id = $id", __FILE__ . " linje " . __LINE__);
		$holders = array();
		while ($u = db_fetch_array($q)) {
			$holders[] = (int) $u['id'];
		}
		foreach ($holders as $uid) {
			user_set_role($uid, $moveTo, $selfId);
		}
	}
	$inUse = db_fetch_array(db_select("select id from brugere where role_id = $id limit 1", __FILE__ . " linje " . __LINE__));
	if ($inUse) {
		return 'tab=roles&rolle=' . $id . '&msg=roleinuse';
	}
	db_modify("delete from role_permissions where role_id = $id", __FILE__ . " linje " . __LINE__);
	db_modify("delete from roles where id = $id", __FILE__ . " linje " . __LINE__);
	audit_log('role.deleted', (string) $r['navn'], 'rolle', (string) $id);
	return 'tab=roles&msg=roledeleted';
}

/**
 * "Nulstil til standard" (spec §3.2): a built-in role gets its built-in matrix back.
 */
function ur_reset_role(int $id): string
{
	$defaults = permission_default_roles();
	foreach (perm_roles() as $role) {
		if ($role['id'] !== $id || !$role['system'] || !isset($defaults[$role['key']])) {
			continue;
		}
		$levels = array();
		foreach (permission_registry() as $key => $def) {
			$levels[$key] = isset($defaults[$role['key']]['levels'][$key]) ? $defaults[$role['key']]['levels'][$key] : 'none';
		}
		if (!perm_within_own($levels)) {
			return 'tab=roles&rolle=' . $id . '&msg=escalation';
		}
		perm_save_role_levels($id, $levels);
		audit_log('role.reset', $role['navn'], 'rolle', (string) $id);
		return 'tab=roles&rolle=' . $id . '&msg=rolereset';
	}
	return 'tab=roles';
}

function ur_copy_role(int $id): string
{
	global $sprog_id;
	$source = null;
	foreach (perm_roles() as $role) {
		if ($role['id'] === $id) {
			$source = $role;
		}
	}
	if (!$source || !perm_within_own(perm_levels_from_role($id))) {
		return 'tab=roles';
	}
	$navn = findtekst('5587|Kopi af', (int) $sprog_id) . ' ' . perm_role_name($source, (int) $sprog_id);
	$navnSql = db_escape_string(mb_substr($navn, 0, 80));
	$beskSql = db_escape_string($source['beskrivelse']);
	db_modify("insert into roles (role_key, navn, beskrivelse, system) values (null, '$navnSql', '$beskSql', false)", __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("select id from roles where navn = '$navnSql' order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	$newId = (int) $r['id'];
	perm_save_role_levels($newId, perm_levels_from_role($id));
	audit_log('role.created', $navn . ' (kopi af ' . $id . ')', 'rolle', (string) $newId);
	return 'tab=roles&rolle=' . $newId;
}

// ================================================================== view model

/**
 * Everything the view needs, loaded in one place.
 *
 * @return array<string, mixed>
 */
function ur_view_model(array $get, int $selfId, int $sprogId, string $dbEncode, string $contextQuery): array
{
	$canRoles = perm_can('settings.roles.manage', 'read');
	$canAudit = perm_can('settings.audit.read', 'read');
	$tab = isset($get['tab']) ? (string) $get['tab'] : 'users';
	if (!in_array($tab, array('users', 'roles', 'log'), true) || ($tab === 'roles' && !$canRoles) || ($tab === 'log' && !$canAudit)) {
		$tab = 'users';
	}
	$roles = perm_roles();
	$rolesById = array();
	foreach ($roles as $role) {
		$rolesById[$role['id']] = $role;
	}
	$counts = array();
	$q = db_select("select role_id, count(*) as antal from brugere group by role_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$counts[(int) $r['role_id']] = (int) $r['antal'];
	}

	$lastActive = user_last_active();
	$lastLogin = array();
	if (audit_ready()) {
		$q = db_select("select bruger_id, max(tidspunkt) as sidst from audit_log where handling in ('login', 'login.success') group by bruger_id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$lastLogin[(int) $r['bruger_id']] = (string) $r['sidst'];
		}
	}
	$revisorUser = 0;
	if ($r = db_fetch_array(db_select("select user_id from settings where var_name = 'revisor' and var_grp = 'system'", __FILE__ . " linje " . __LINE__))) {
		$revisorUser = (int) $r['user_id'];
	}

	$users = array();
	$withoutRole = 0;
	$review = perm_review_pending();
	$q = db_select("select b.*, a.navn as ansat_navn, a.initialer, a.lukket as ansat_lukket from brugere b left join ansatte a on a.id = b.ansat_id order by lower(b.brugernavn)", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$roleId = (int) $r['role_id'];
		$suggestion = null;
		if (isset($review[(int) $r['id']])) {
			$s = perm_covering_role($review[(int) $r['id']]);
			if ($s && isset($rolesById[$s['id']])) {
				$suggestion = $rolesById[$s['id']];
			}
		} elseif ($roleId <= 0) {
			$withoutRole++;
			$s = perm_suggest_role((string) $r['rettigheder']);
			if ($s && isset($rolesById[$s['id']])) {
				$suggestion = $rolesById[$s['id']];
			}
		}
		$users[] = array(
			'id'         => (int) $r['id'],
			'brugernavn' => (string) $r['brugernavn'],
			'navn'       => trim((string) $r['ansat_navn']) !== '' ? trim((string) $r['ansat_navn']) : (string) $r['brugernavn'],
			'initialer'  => (string) $r['initialer'],
			'email'      => (string) ifset($r, 'email', ''),
			'tlf'        => (string) ifset($r, 'tlf', ''),
			'ip'         => (string) ifset($r, 'ip_address', ''),
			'twofactor'  => in_array($r['twofactor'], array('t', true, '1', 1), true),
			'ansat_id'   => (int) $r['ansat_id'],
			'role_id'    => $roleId,
			'role'       => isset($rolesById[$roleId]) ? $rolesById[$roleId] : null,
			'suggestion' => $suggestion,
			'rettigheder'=> (string) $r['rettigheder'],
			'lastLogin'  => isset($lastActive[mb_strtolower((string) $r['brugernavn'])]) ? date('Y-m-d H:i', $lastActive[mb_strtolower((string) $r['brugernavn'])]) : '',
			'closed'     => !user_row_active($r),
			'invited'    => user_row_invited($r),
			'review'     => isset($review[(int) $r['id']]),
			'hasLoggedIn'=> isset($lastLogin[(int) $r['id']]),
			'isRevisor'  => ((int) $r['id'] === $revisorUser),
		);
	}

	$employees = array();
	$q = db_select("select id, navn, initialer from ansatte where lukket is null or lukket != 'on' order by initialer, navn", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$employees[] = array('id' => (int) $r['id'], 'navn' => (string) $r['navn'], 'initialer' => (string) $r['initialer']);
	}

	$editUser = null;
	if (isset($get['bruger'])) {
		$wanted = (int) $get['bruger'];
		foreach ($users as $u) {
			if ($u['id'] === $wanted) {
				$editUser = $u;
			}
		}
		if ($wanted === 0) {
			$editUser = array('id' => 0, 'brugernavn' => '', 'navn' => '', 'initialer' => '', 'email' => '', 'tlf' => '', 'ip' => '',
				'twofactor' => false, 'ansat_id' => 0, 'role_id' => 0, 'role' => null, 'suggestion' => null, 'rettigheder' => '',
				'lastLogin' => '', 'closed' => false, 'invited' => false, 'review' => false, 'hasLoggedIn' => false, 'isRevisor' => false);
		}
	}

	$inviteLink = '';
	if (isset($_SESSION['ur_invite_link'])) {
		if ($editUser && (int) $_SESSION['ur_invite_link']['id'] === $editUser['id']) {
			$inviteLink = (string) $_SESSION['ur_invite_link']['link'];
		}
		unset($_SESSION['ur_invite_link']);
	}

	$editRole = null;
	if (isset($get['rolle'])) {
		$wanted = (int) $get['rolle'];
		if ($wanted === 0) {
			$editRole = array('id' => 0, 'key' => '', 'navn' => '', 'beskrivelse' => '', 'system' => false, 'levels' => perm_levels_from_role(0));
		} elseif (isset($rolesById[$wanted])) {
			$editRole = $rolesById[$wanted];
			$editRole['levels'] = perm_levels_from_role($wanted);
		}
	}

	if ($editRole === null && $tab === 'roles' && $roles) {
		$editRole = $roles[0];
		$editRole['levels'] = perm_levels_from_role($roles[0]['id']);
	}
	$roleChanged = false;
	if ($editRole !== null && $editRole['system']) {
		$defaults = permission_default_roles();
		if (isset($defaults[$editRole['key']])) {
			foreach ($editRole['levels'] as $key => $level) {
				$want = isset($defaults[$editRole['key']]['levels'][$key]) ? $defaults[$editRole['key']]['levels'][$key] : 'none';
				if ($level !== $want) {
					$roleChanged = true;
					break;
				}
			}
		}
	}
	// The user drawer shows what the chosen role grants, per module, and follows the role picker.
	$roleLevels = array();
	if ($editUser !== null) {
		foreach ($roles as $role) {
			$roleLevels[$role['id']] = perm_levels_from_role($role['id']);
		}
	}

	$log = array();
	$logMore = false;
	$logFilter = ur_log_filter($get);
	$logOptions = array('users' => array(), 'objects' => array());
	$unguarded = array();
	$wouldDeny = array();
	if ($tab === 'log' && audit_ready()) {
		$cols = ur_audit_columns();
		$q = db_select("select * from audit_log where " . ur_log_where($logFilter, $cols) . " order by id desc limit " . (UR_LOG_PAGE + 1) . " offset " . ($logFilter['side'] * UR_LOG_PAGE), __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$log[] = $r;
		}
		if (count($log) > UR_LOG_PAGE) {
			array_pop($log);
			$logMore = true;
		}
		$q = db_select("select distinct brugernavn from audit_log where brugernavn != '' order by brugernavn limit 300", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$logOptions['users'][] = (string) $r['brugernavn'];
		}
		if (isset($cols['objekt_type'])) {
			$q = db_select("select distinct objekt_type from audit_log where objekt_type is not null and objekt_type != '' order by objekt_type", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$logOptions['objects'][] = (string) $r['objekt_type'];
			}
		}
		// Logging-period overview (spec 3.3 step 2): which pages still lack a key, and
		// what would have been refused, grouped so the admin can judge before switching.
		$q = db_select("select detaljer, count(*) as antal, max(tidspunkt) as sidst from audit_log where handling = 'unguarded' group by detaljer order by antal desc limit 100", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$unguarded[] = $r;
		}
		$q = db_select("select detaljer, count(*) as antal, max(tidspunkt) as sidst from audit_log where handling in ('would-deny', 'permission.would_deny') group by detaljer order by antal desc limit 100", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$wouldDeny[] = $r;
		}
	}

	return array(
		'tab'          => $tab,
		'sprogId'      => $sprogId,
		'charset'      => ($dbEncode === 'UTF8') ? 'UTF-8' : 'ISO-8859-1',
		'selfUrl'      => 'usersRoles.php' . ($contextQuery !== '' ? '?' . $contextQuery : ''),
		'linkPrefix'   => 'usersRoles.php?' . ($contextQuery !== '' ? $contextQuery . '&' : ''),
		'canWrite'     => perm_can('settings.users.manage', 'write'),
		'ownLevels'    => perm_user_levels($selfId, (string) $GLOBALS['rettigheder']),
		'selfId'       => $selfId,
		'msg'          => isset($get['msg']) ? (string) $get['msg'] : '',
		'skipped'      => isset($get['skipped']) ? (int) $get['skipped'] : 0,
		'done'         => isset($get['done']) ? (int) $get['done'] : 0,
		'act'          => isset($get['act']) ? (string) $get['act'] : '',
		'why'          => ur_parse_why(isset($get['why']) ? (string) $get['why'] : ''),
		'adminRoleId'  => user_admin_role_id(),
		'users'        => $users,
		'withoutRole'  => $withoutRole,
		'reviewCount'  => count($review),
		'statusFilter' => (isset($get['status']) && in_array($get['status'], array('active', 'invited', 'closed', 'review'), true)) ? (string) $get['status'] : '',
		'roles'        => $roles,
		'roleCounts'   => $counts,
		'employees'    => $employees,
		'editUser'     => $editUser,
		'roleChanged'  => $roleChanged,
		'roleLevels'   => $roleLevels,
		'newMode'      => (isset($get['mode']) && $get['mode'] === 'classic') ? 'classic' : 'invite',
		'inviteLink'   => $inviteLink,
		'editRole'     => $editRole,
		'log'          => $log,
		'logMore'      => $logMore,
		'logFilter'    => $logFilter,
		'logOptions'   => $logOptions,
		'canRoles'     => $canRoles,
		'canRolesWrite'=> perm_can('settings.roles.manage', 'write'),
		'canAudit'     => $canAudit,
		'canExport'    => perm_can('settings.import_export', 'read'),
		'userNames'    => array_column($users, 'brugernavn', 'id'),
		'unguarded'    => $unguarded,
		'wouldDeny'    => $wouldDeny,
		'enforceMode'  => perm_enforcement_mode(),
		'tablesReady'  => perm_tables_ready(),
	);
}

// ================================================================== audit log (spec §7.2)


/**
 * Action types of the filter and the actions (handling, LIKE patterns) each covers.
 *
 * @return array<string, array<int, string>>
 */
function ur_log_types(): array
{
	return array(
		'login'      => array('login%', 'logout'),
		'session'    => array('session.%'),
		'user'       => array('user.%', 'revisor.%'),
		'role'       => array('role.%', 'permissions.mode'),
		'permission' => array('permission.%', 'would-deny', 'unguarded', 'denied', 'csrf'),
		'setting'    => array('setting.%', 'integration.%'),
	);
}

/**
 * The filter from the query string, validated.
 *
 * @return array{fra: string, til: string, bruger: string, type: string, objekt: string, q: string, wd: bool, side: int}
 */
function ur_log_filter(array $get): array
{
	$date = function ($v) {
		$v = trim((string) $v);
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : '';
	};
	$type = isset($get['type']) ? (string) $get['type'] : '';
	if ($type === 'wd') {
		$get['wd'] = 1;
		$type = '';
	}
	return array(
		'fra'    => $date(ifset($get, 'fra', '')),
		'til'    => $date(ifset($get, 'til', '')),
		'bruger' => mb_substr(trim((string) ifset($get, 'bruger', '')), 0, 80),
		'type'   => isset(ur_log_types()[$type]) ? $type : '',
		'objekt' => preg_match('/^[a-z_]{1,30}$/', (string) ifset($get, 'objekt', '')) ? (string) $get['objekt'] : '',
		'q'      => mb_substr(trim((string) ifset($get, 'q', '')), 0, 80),
		'wd'     => !empty($get['wd']),
		'side'   => max(0, min(10000, (int) ifset($get, 'side', 0))),
	);
}

/**
 * The query string of a filter, for links that keep it.
 */
function ur_log_query(array $f, array $override = array()): string
{
	$f = array_merge($f, $override);
	$parts = array('tab' => 'log');
	foreach (array('fra', 'til', 'bruger', 'type', 'objekt', 'q') as $k) {
		if ($f[$k] !== '') {
			$parts[$k] = $f[$k];
		}
	}
	if ($f['wd']) {
		$parts['wd'] = 1;
	}
	if ($f['side'] > 0) {
		$parts['side'] = $f['side'];
	}
	return http_build_query($parts);
}

/**
 * Columns of audit_log that exist on this installation.
 *
 * @return array<string, bool>
 */
function ur_audit_columns(): array
{
	static $cols = null;
	if ($cols === null) {
		$cols = array();
		$q = db_select("select column_name as column_name from information_schema.columns where table_name = 'audit_log'" . saldi_schema_filter(), __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$cols[strtolower((string) $r['column_name'])] = true;
		}
	}
	return $cols;
}

function ur_log_where(array $f, array $cols): string
{
	$w = array('1 = 1');
	if ($f['fra'] !== '') {
		$w[] = "tidspunkt >= '" . $f['fra'] . "'";
	}
	if ($f['til'] !== '') {
		$w[] = "tidspunkt < '" . date('Y-m-d', strtotime($f['til'] . ' +1 day')) . "'";
	}
	if ($f['bruger'] !== '') {
		$w[] = "brugernavn = '" . db_escape_string($f['bruger']) . "'";
	}
	if ($f['wd']) {
		$w[] = "handling in ('permission.would_deny', 'would-deny')";
	} elseif ($f['type'] !== '') {
		$or = array();
		foreach (ur_log_types()[$f['type']] as $pattern) {
			$or[] = "handling like '" . db_escape_string($pattern) . "'";
		}
		$w[] = '(' . implode(' or ', $or) . ')';
	}
	if ($f['objekt'] !== '' && isset($cols['objekt_type'])) {
		$w[] = "objekt_type = '" . db_escape_string($f['objekt']) . "'";
	}
	if ($f['q'] !== '') {
		$like = "'%" . db_escape_string(db_escape_like_pattern(mb_strtolower($f['q']))) . "%'";
		$or = array("lower(brugernavn) like $like", "lower(detaljer) like $like");
		if (isset($cols['objekt_id'])) {
			$or[] = "lower(objekt_id) like $like";
			// A user as object is stored by id: a search for the name finds it too.
			$ids = array();
			$q = db_select("select id from brugere where lower(brugernavn) like $like limit 50", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$ids[] = "'" . (int) $r['id'] . "'";
			}
			if ($ids) {
				$or[] = "(objekt_type = 'bruger' and objekt_id in (" . implode(',', $ids) . "))";
			}
		}
		if (isset($cols['setting_key'])) {
			$or[] = "lower(setting_key) like $like";
		}
		$w[] = '(' . implode(' or ', $or) . ')';
	}
	return implode(' and ', $w);
}

/**
 * The filtered log as CSV (semicolon, UTF-8). Cells that a spreadsheet would read as a
 * formula are prefixed with an apostrophe.
 */
function ur_export_log(array $get, string $dbEncode): void
{
	$filter = ur_log_filter($get);
	$filter['side'] = 0;
	$cols = ur_audit_columns();
	$cell = function ($v) use ($dbEncode) {
		$v = (string) $v;
		if ($dbEncode !== 'UTF8') {
			$v = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
		}
		return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
	};
	audit_log('audit.exported', ur_log_query($filter), 'audit_log', '');
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="audit-log-' . date('Ymd-Hi') . '.csv"');
	header('Cache-Control: no-store');
	$out = fopen('php://output', 'w');
	fwrite($out, "\xEF\xBB\xBF");
	fputcsv($out, array('tidspunkt', 'brugernavn', 'handling', 'objekt_type', 'objekt_id', 'detaljer', 'ip', 'kilde'), ';');
	$q = db_select("select * from audit_log where " . ur_log_where($filter, $cols) . " order by id desc limit 100000", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		fputcsv($out, array_map($cell, array(
			substr((string) $r['tidspunkt'], 0, 19), $r['brugernavn'], $r['handling'],
			ifset($r, 'objekt_type', ''), ifset($r, 'objekt_id', ''), $r['detaljer'], $r['ip'], ifset($r, 'kilde', ''),
		)), ';');
	}
	fclose($out);
}
