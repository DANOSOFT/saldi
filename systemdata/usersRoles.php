<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/usersRoles.php --- ver 5.1.1 --- 2026.11.14 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260916 SS "Brugere & roller" (spec R8): user list with search, user card with role, role list + matrix editor, bulk role assignment, migration suggestions and the audit log.
//             Replaces brugere.php (and brugereRevisor.php).
//             Controller here, view in usersRolesIncludes/view.php.
//             Post/Redirect/Get throughout.
// 20261101 CL/ASR New design (prototype D): the head links css/saldi-theme.css; the view links css/saldi-kit.css and then css/usersRoles.css, so the page's own rules come after the kit.
// 20261101 CL/ASR Loads includes/settings/components.php for the Tilbage button the view shows above the title.
// 20261102 CL/ASR Loads includes/settings/layout.php (which loads components.php): the view uses the standard settings layout with the left menu.
// 20261102 CL/ASR Brugere & ansatte (owner, 2 Nov 2026: "Ét punkt, 'Brugere & ansatte', og én liste med alle", one card with Stamdata and Login og rolle, one button "Tilføj person"): the users list is the one list of logins and employees, and the user drawer the person card (includes/settings/people.php, loaded here: ur_people_request() checks ansat=/fane=/field=, ur_people_model() adds the employees after ur_view_model()).
//                 Saving, the checks and the redirects of this page are unchanged; the only new input is ansat=<id>, an employee of the company, only read for a user who may read the employees.
//                 With ansat=<id> a new user starts linked to that employee, with its e-mail and a username from it, when the employee is the company's, has not left and has no login yet, and only for a user who may create logins and read the employees; the new-user form keeps ansat= on its POST, so a refused form (a username already taken) comes back linked.
//                 "Kobling til ansat" lists only the company's employees (contact persons of customers and suppliers are in the same table), keeps a closed or other linked employee as the selected choice (Gem no longer unlinks it), and marks employees that have a login.
//                 The status filter may start on "Ingen login" (status=nologin).
// 20261103 CL/ASR QA: no legacy $modulnr gate (it read the Indstillinger bit, so an Administrator matrix without Indstillinger, or a role with only Brugere & roller, was refused before the page's own checks).
//                 Each tab has its own key: Brugere settings.users.manage, Roller settings.roles.manage, Audit-log settings.audit.read; the page opens with any of the three, and a POST needs write on the key of what it changes (role actions roles.manage, the rest users.manage).
//                 Nulstil til standard is refused (and disabled) for a role whose present rights lie above the editor's own, as Gem is.
//                 The employee choices on the user card are loaded only for a user who may read Organisation (else only the linked one).
//                 Once a revisor is designated it can no longer be moved or removed here (master's rule; text 2563 says support does it).
//                 "Slet helt" is offered only to users the log saw being created (as user_delete() decides), not to every older user.
//                 A migrated custom role that an administrator saves loses its migrated_ key, so it is not deleted with its last holder.
//                 Audit-log times (list and CSV) on PHP's clock (st_local_time()).
// 20261108 CL/ASR css/saldi-theme.css linked as ?v=8: the page ground is Saldi's classic page colour #eeeef0, the panels and fields stay white (owner, 8 Nov 2026).
// 20261110 CDX/ASR Atomic revision-checked role actions, unsaved template drafts, and tab-specific batched models.
// 20261113 CDX/NTR Use is_built_in when saving, deleting, copying and resetting built-in roles.
// 20261114 CL/ASR The activity log's type filter has Data (data.%: changes to varer, adresser and kontoplan, includes/dataAudit.php).
// 20261114 CL/ASR The type filter Fakturering og kreditering (data.invoiced, data.credited).
// 20261114 CL/ASR The card's Tofaktor is Fra, Kode på SMS or Kode på e-mail (includes/twoFactor.php). SMS without a phone number and e-mail without an e-mail are refused (msg=tfa_phone, tfa_email, tfa_contact).

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
$css = "../css/saldi-theme.css?v=11"; // the light/dark tokens; usersRolesIncludes/view.php links the kit and css/usersRoles.css after it
$modulnr = 0; // no legacy gate: the page checks its own keys below
$permission_key = 'settings.users.manage|settings.roles.manage|settings.audit.read';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/userFunctions.php");
include_once(__DIR__ . "/../includes/twoFactor.php");
include_once(__DIR__ . "/../includes/roleManagement.php");
include_once(__DIR__ . "/settingsRegistry.php");
include_once(__DIR__ . "/../includes/settings/layout.php"); // settings_layout_open(): the standard settings layout (Tilbage, left menu) the view uses
include(__DIR__ . "/usersRolesIncludes/view.php");
include_once(__DIR__ . "/../includes/settings/people.php"); // Brugere & ansatte: the employees in the list, the person card, Tilføj person

// Rows per page of the audit log. Declared before any code that reads it: a const statement is
// not hoisted like the functions further down.
const UR_LOG_PAGE = 100;
const UR_ADMIN_LOCKED = array('settings.users.manage', 'settings.roles.manage');

// Each tab has its own key (Brugere, Roller, Audit-log); any of them opens the page.
if (!perm_can('settings.users.manage', 'read') && !perm_can('settings.roles.manage', 'read') && !perm_can('settings.audit.read', 'read')) {
	require_permission('settings.users.manage', 'read');
}

$contextQuery = (!empty($_GET['inframe']) ? 'inframe=1' : '');
// A new login started from a person's card (ansat=<id>, digits only): kept through the POST's redirect, so a refused form comes back linked.
$postQuery = implode('&', array_filter(array($contextQuery, (isset($_GET['ansat']) && is_string($_GET['ansat']) && preg_match('/^[1-9][0-9]{0,9}\z/', $_GET['ansat'])) ? 'ansat=' . $_GET['ansat'] : ''), 'strlen'));

// Every posted form carries the session token (added to the page below), as in diverse.php.
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Role actions need the roles key, everything else the users key.
	require_permission(in_array((string) ($_POST['action'] ?? ''), array('reset_role', 'save_role', 'delete_role', 'copy_role', 'set_enforce'), true) ? 'settings.roles.manage' : 'settings.users.manage', 'write');
	if (!isset($_POST['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
		audit_log('csrf', 'usersRoles.php');
		ob_end_clean();
		header('Location: usersRoles.php?' . ($postQuery !== '' ? $postQuery . '&' : '') . 'tab=users&msg=csrf');
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
	header('Location: usersRoles.php?' . ($postQuery !== '' ? $postQuery . '&' : '') . $redirect);
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
$get = ur_people_request($_GET);
$vm = ur_people_model(ur_view_model($get, (int) $bruger_id, (int) $sprog_id, (string) $db_encode, $contextQuery), $get);
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
			return ur_reset_role((int) ifset($post, 'id', 0), (string) ifset($post, 'role_revision', ''), $selfId);
		case 'apply_suggestions':
			return ur_apply_suggestions();
		case 'save_role':
			return ur_save_role($post, $selfId);
		case 'delete_role':
			return ur_delete_role((int) ifset($post, 'id', 0), (int) ifset($post, 'move_to', 0), $selfId, (string) ifset($post, 'role_revision', ''));
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
	$exists = db_fetch_array(db_select("select id from roles where id = $roleId", __FILE__ . ' linje ' . __LINE__));
	return $exists && perm_within_own(perm_levels_from_role($roleId));
}

/** Save account details, its role and related settings together; send mail after commit. */
function ur_save_user(array $post, int $selfId, int $regnaar): string
{
	$invitation = array();
	try {
		$redirect = perm_atomic(function () use ($post, $selfId, $regnaar, &$invitation) {
			perm_lock_roles();
			if (!perm_can('settings.users.manage', 'write')) {
				return 'tab=users&msg=noaccess';
			}
			return ur_save_user_locked($post, $selfId, $regnaar, $invitation);
		});
	} catch (Throwable $error) {
		error_log('User save failed: ' . $error->getMessage());
		return 'tab=users&bruger=' . (int) ($post['id'] ?? 0) . '&msg=userfailed';
	}
	if ($invitation) {
		return 'tab=users&bruger=' . $invitation['id'] . '&msg=' . ur_send_invite($invitation['id'], $invitation['token']);
	}
	return $redirect;
}

/**
 * Internal account save, called with role locks and an open transaction.
 *
 * @param array{id?: int, token?: string} $invitation Mail to deliver only after a successful commit.
 */
function ur_save_user_locked(array $post, int $selfId, int $regnaar, array &$invitation): string
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
	// The card sends '' (off), 'sms' or 'email'. Anything else that is not empty is the old "Til": on, and the code
	// goes by SMS when there is a number and else by e-mail (includes/twoFactor.php).
	$tfaChoice = (string) ifset($post, 'twofactor', '');
	if ($tfaChoice !== '' && !in_array($tfaChoice, array('sms', 'email'), true)) {
		$tfaChoice = 'auto';
	}
	$twofactor = ($tfaChoice !== '') ? 't' : 'f';
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
	// Two-factor needs somewhere to send the code: index/login.php does not let the user in without one.
	if ($tfaChoice === 'sms' && !twofactor_phone_ok($tlf)) {
		return $back . '&msg=tfa_phone';
	}
	if ($tfaChoice === 'email' && !twofactor_email_ok($email)) {
		return $back . '&msg=tfa_email';
	}
	if ($tfaChoice === 'auto' && !twofactor_methods($tlf, $email)) {
		return $back . '&msg=tfa_contact';
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
		if ($roleId !== $currentRole) {
			$roleError = user_set_role($id, $roleId, $selfId);
			if ($roleError !== '' && $roleError !== 'unchanged') {
				return $back . '&msg=' . $roleError;
			}
		}
		$qtxt = "update brugere set brugernavn = '$navnSql', ansat_id = $ansatSql, ip_address = '$ipSql', tlf = '$tlfSql', twofactor = '$twofactor', email = '$emailSql'";
		if ($kode !== '') {
			$qtxt .= ", kode = '" . db_escape_string(saldikrypt($id, $kode)) . "'";
		}
		$qtxt .= " where id = $id";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		audit_log('user.updated', $navn . ($kode !== '' ? ' (ny adgangskode)' : ''), 'bruger', (string) $id);
	}
	if ($id > 0 && in_array($tfaChoice, array('sms', 'email'), true)) {
		twofactor_set_preferred($id, $tfaChoice);
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
		$invitation = array('id' => $id, 'token' => $inviteToken);
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
	// As on master's brugere.php: once a revisor is designated, only Saldi support moves or removes it (text 2563).
	if ($currentId > 0) {
		return;
	}
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
	while ($r = db_fetch_array($q)) {
		$suggestion = perm_suggest_role((string) $r['rettigheder']);
		if ($suggestion) {
			user_set_role((int) $r['id'], (int) $suggestion['id'], (int) $GLOBALS['bruger_id'], 'migrering');
		}
	}
	return 'tab=users&msg=assigned';
}

/** Save through the shared atomic role service. */
function ur_save_role(array $post, int $selfId): string
{
	try {
		$r = perm_edit_role($post, $selfId);
	} catch (Throwable $error) {
		error_log('Role save failed: ' . $error->getMessage());
		return 'tab=roles&rolle=' . (int) ($post['id'] ?? 0) . '&msg=rolefailed';
	}
	return 'tab=roles&rolle=' . $r['id'] . '&msg=' . ($r['error'] !== '' ? $r['error'] : 'rolesaved');
}

/** Transfer holders and delete as one operation, with no partial moves. */
function ur_delete_role(int $id, int $moveTo, int $selfId, string $revision): string
{
	try {
		$error = perm_delete_role($id, $moveTo, $selfId, $revision);
	} catch (Throwable $error) {
		error_log('Role delete failed: ' . $error->getMessage());
		return 'tab=roles&rolle=' . $id . '&msg=rolefailed';
	}
	return 'tab=roles' . ($error !== '' ? '&rolle=' . $id . '&msg=' . $error : '&msg=roledeleted');
}

/** Reset uses the same revision and own-role checks as saving. */
function ur_reset_role(int $id, string $revision, int $selfId): string
{
	try {
		$r = perm_edit_role(array('id' => $id, 'role_revision' => $revision), $selfId, true);
	} catch (Throwable $error) {
		error_log('Role reset failed: ' . $error->getMessage());
		return 'tab=roles&rolle=' . $id . '&msg=rolefailed';
	}
	return 'tab=roles&rolle=' . $id . '&msg=' . ($r['error'] !== '' ? $r['error'] : 'rolereset');
}

/** Older copy buttons also open an unsaved draft; only Save creates a role. */
function ur_copy_role(int $id): string
{
	return 'tab=roles&rolle=0&copy_role=' . $id;
}

/**
 * Everything the view needs, loaded in one place.
 *
 * @return array<string, mixed>
 */
function ur_view_model(array $get, int $selfId, int $sprogId, string $dbEncode, string $contextQuery): array
{
	$canRoles = perm_can('settings.roles.manage', 'read');
	$canAudit = perm_can('settings.audit.read', 'read');
	$canUsers = perm_can('settings.users.manage', 'read');
	$tab = isset($get['tab']) ? (string) $get['tab'] : 'users';
	if (!in_array($tab, array('users', 'roles', 'log'), true) || ($tab === 'roles' && !$canRoles) || ($tab === 'log' && !$canAudit)) {
		$tab = 'users';
	}
	// Without the users key the page opens on a tab the user may see.
	if ($tab === 'users' && !$canUsers) {
		$tab = $canRoles ? 'roles' : 'log';
	}
	$roles = $tab !== 'log' ? perm_roles() : array();
	$roleLevels = perm_levels_from_roles(array_column($roles, 'id'));
	$roleDefaults = array();
	$defaults = permission_default_roles();
	$rolesById = array();
	foreach ($roles as &$role) {
		$roleDefaults[$role['id']] = isset($defaults[$role['key']]) ? perm_normalize_levels($defaults[$role['key']]['levels']) : array();
		$role['changed'] = $role['is_built_in'] && $roleDefaults[$role['id']] && perm_level_changes($roleDefaults[$role['id']], $roleLevels[$role['id']]) !== array();
		$rolesById[$role['id']] = $role;
	}
	unset($role);
	$counts = array();
	$roleStatusCounts = array();
	$roleHolders = array();
	if ($tab !== 'log') {
		foreach ($roles as $role) {
			$roleStatusCounts[$role['id']] = array('active' => 0, 'invited' => 0, 'closed' => 0, 'total' => 0);
			$roleHolders[$role['id']] = array();
		}
		$columns = $canUsers ? 'id, brugernavn, ' : '';
		$q = db_select("select " . $columns . "role_id, status, case when kode = '' and tmp_kode like 'invite|%' then 1 else 0 end as invited from brugere", __FILE__ . ' linje ' . __LINE__);
		while ($r = db_fetch_array($q)) {
			$rid = (int) $r['role_id'];
			if (!isset($roleStatusCounts[$rid])) {
				$roleStatusCounts[$rid] = array('active' => 0, 'invited' => 0, 'closed' => 0, 'total' => 0);
			}
			$status = !user_row_active($r) ? 'closed' : ((int) $r['invited'] === 1 ? 'invited' : 'active');
			$roleStatusCounts[$rid][$status]++;
			$roleStatusCounts[$rid]['total']++;
			if ($canUsers) {
				$roleHolders[$rid][] = array('id' => (int) $r['id'], 'brugernavn' => (string) $r['brugernavn'], 'status' => $status);
			}
		}
		foreach ($roleStatusCounts as $rid => $statusCounts) {
			$counts[$rid] = $statusCounts['total'];
		}
	}
	$users = array();
	$withoutRole = 0;
	$review = array();
	$employees = array();
	$editUser = null;
	$inviteLink = '';
	$revisorUser = 0;
	if ($tab === 'users') {
		$lastActive = user_last_active();
		$lastLogin = array();
		$createdInLog = array();
		$auditReady = audit_ready();
		if ($auditReady) {
			$q = db_select("select bruger_id, max(tidspunkt) as sidst from audit_log where handling in ('login', 'login.success') group by bruger_id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$lastLogin[(int) $r['bruger_id']] = (string) $r['sidst'];
			}
			// Users the log saw being created: only they can be new. Older users may have logged in before the log existed
			// (the same rule as user_has_logged_in(), which decides whether Slet helt is allowed).
			$q = db_select("select distinct objekt_id from audit_log where objekt_type = 'bruger' and handling in ('user.created', 'user.invited')", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$createdInLog[(int) $r['objekt_id']] = true;
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
				$s = perm_covering_role($review[(int) $r['id']], $roles, $roleLevels);
				if ($s && isset($rolesById[$s['id']])) {
					$suggestion = $rolesById[$s['id']];
				}
			} elseif ($roleId <= 0) {
				$withoutRole++;
				$s = perm_suggest_role((string) $r['rettigheder'], $roles, $roleLevels);
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
				'hasLoggedIn'=> !$auditReady || isset($lastLogin[(int) $r['id']]) || !isset($createdInLog[(int) $r['id']]),
				'isRevisor'  => ((int) $r['id'] === $revisorUser),
			);
		}

		// The company's own employees only: the table also holds the contact persons of customers and suppliers. 'logins' = how many
		// logins are linked to each (shown as "har login"; a second link stays possible).
		$companyId = function_exists('settings_company_account_id') ? settings_company_account_id() : 0;
		// Only for a user who may read the employees (Organisation); others see just the employee a login is linked to (added below).
		$employees = array();
		$q = perm_can('settings.organisation', 'read') ? db_select("select a.id, a.navn, a.initialer, (select count(*) from brugere b where b.ansat_id = a.id) as logins from ansatte a where (a.lukket is null or a.lukket != 'on')" . ($companyId > 0 ? " and a.konto_id = $companyId" : "") . " order by a.initialer, a.navn", __FILE__ . " linje " . __LINE__) : false;
		while ($q && $r = db_fetch_array($q)) {
			$employees[] = array('id' => (int) $r['id'], 'navn' => (string) $r['navn'], 'initialer' => (string) $r['initialer'], 'logins' => (int) $r['logins'], 'left' => false);
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
				// From a person's card (ansat=<id>, checked by ur_people_request()): the new user starts linked to that employee, with its
				// e-mail and a username (the e-mail before @, else the initials) - only an employee of the company that has not left and has
				// no login yet, and only for a user who may create logins and read the employees (the e-mail is the employee's data).
				$fromEmployee = isset($get['_ansat']) ? (int) $get['_ansat'] : 0;
				if ($fromEmployee > 0 && $companyId > 0 && perm_can('settings.users.manage', 'write') && perm_can('settings.organisation', 'read')) {
					$e = db_fetch_array(db_select("select id, navn, initialer, email from ansatte where id = " . $fromEmployee . " and konto_id = $companyId and (lukket is null or lukket != 'on') and not exists (select 1 from brugere b where b.ansat_id = ansatte.id)", __FILE__ . " linje " . __LINE__));
					if ($e) {
						$mail = trim((string) $e['email']);
						$name = ($mail !== '' && strpos($mail, '@') > 0) ? substr($mail, 0, strpos($mail, '@')) : trim((string) $e['initialer']);
						$editUser['ansat_id'] = (int) $e['id'];
						$editUser['email'] = $mail;
						$editUser['brugernavn'] = substr(strtolower($name), 0, 80);
						$editUser['navn'] = trim((string) $e['navn']);
					}
				}
			}
		}
		// How the two-factor code reaches the user on the card: the user's own choice, else SMS when there is a number.
		if ($editUser !== null) {
			$editUser['tfaMethod'] = ($editUser['id'] > 0 && $editUser['twofactor'])
				? twofactor_pick(twofactor_methods($editUser['tlf'], $editUser['email']), twofactor_preferred($editUser['id'])) : '';
		}
		// A login linked to an employee that is not offered (left, or not the company's) keeps that link as its selected choice,
		// so Gem does not unlink it.
		if ($editUser !== null && $editUser['ansat_id'] > 0 && !in_array($editUser['ansat_id'], array_column($employees, 'id'), true)) {
			$e = db_fetch_array(db_select("select id, navn, initialer, lukket from ansatte where id = " . (int) $editUser['ansat_id'], __FILE__ . " linje " . __LINE__));
			if ($e) {
				$employees[] = array('id' => (int) $e['id'], 'navn' => (string) $e['navn'], 'initialer' => (string) $e['initialer'], 'logins' => 1, 'left' => trim((string) $e['lukket']) !== '');
			}
		}

		$inviteLink = '';
		if (isset($_SESSION['ur_invite_link'])) {
			if ($editUser && (int) $_SESSION['ur_invite_link']['id'] === $editUser['id']) {
				$inviteLink = (string) $_SESSION['ur_invite_link']['link'];
			}
			unset($_SESSION['ur_invite_link']);
		}

	}

	$editRole = null;
	$sourceId = max(0, (int) ($get['copy_role'] ?? 0));
	if ($tab === 'roles') {
		$wanted = isset($get['rolle']) ? (int) $get['rolle'] : ($roles[0]['id'] ?? 0);
		if ($sourceId > 0) {
			$wanted = 0;
		}
		if ($wanted === 0) {
			$editRole = array('id' => 0, 'key' => '', 'navn' => '', 'beskrivelse' => '', 'is_built_in' => false, 'levels' => perm_normalize_levels(array()), 'changed' => false);
			if ($sourceId > 0 && isset($rolesById[$sourceId]) && perm_within_own($roleLevels[$sourceId])) {
				$editRole['levels'] = $roleLevels[$sourceId];
				$editRole['beskrivelse'] = $rolesById[$sourceId]['beskrivelse'];
			} else {
				$sourceId = 0;
			}
		} elseif (isset($rolesById[$wanted])) {
			$editRole = $rolesById[$wanted];
			$editRole['levels'] = $roleLevels[$wanted];
		}
	}
	$roleChanged = $editRole !== null && $editRole['changed'];
	$revision = $editRole !== null && $editRole['id'] > 0 ? perm_role_revision($editRole, $editRole['levels']) : '';
	if ($editRole !== null) {
		$editRole['role_revision'] = $revision;
		$editRole['role_source'] = $sourceId;
	}
	$self = $selfId > 0 && $tab !== 'log' ? db_fetch_array(db_select("select role_id from brugere where id = $selfId", __FILE__ . ' linje ' . __LINE__)) : null;

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
		$q = db_select("select detaljer, count(*) as antal, max(tidspunkt) as sidst from audit_log where handling in ('would-deny', 'would_deny', 'permission.would_deny') group by detaljer order by antal desc limit 100", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$wouldDeny[] = $r;
		}
	}

	$userNames = array_column($users, 'brugernavn', 'id');
	if ($tab === 'log' && $canUsers && $log) {
		$objectIds = array();
		foreach ($log as $row) {
			if (($row['objekt_type'] ?? '') === 'bruger' && (int) ($row['objekt_id'] ?? 0) > 0) {
				$objectIds[] = (int) $row['objekt_id'];
			}
		}
		if ($objectIds) {
			$q = db_select('select id, brugernavn from brugere where id in (' . implode(',', array_unique($objectIds)) . ')', __FILE__ . ' linje ' . __LINE__);
			while ($r = db_fetch_array($q)) {
				$userNames[(int) $r['id']] = (string) $r['brugernavn'];
			}
		}
	}

	return array(
		'tab'          => $tab,
		'sprogId'      => $sprogId,
		'charset'      => ($dbEncode === 'UTF8') ? 'UTF-8' : 'ISO-8859-1',
		'selfUrl'      => 'usersRoles.php' . ($contextQuery !== '' ? '?' . $contextQuery : ''),
		'linkPrefix'   => 'usersRoles.php?' . ($contextQuery !== '' ? $contextQuery . '&' : ''),
		'canWrite'     => perm_can('settings.users.manage', 'write'),
		'ownLevels'    => perm_current_levels(),
		'ownRoleId'    => $self ? (int) $self['role_id'] : (!empty($GLOBALS['revisor']) && perm_enforcement_mode() === 'deny' ? perm_role_id_by_key('revisor') : 0),
		'stateScope'   => hash('sha256', (string) $GLOBALS['db'] . '|' . $selfId),
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
		'statusFilter' => (isset($get['status']) && in_array($get['status'], array('active', 'invited', 'closed', 'review', 'nologin'), true)) ? (string) $get['status'] : '',
		'roles'        => $roles,
		'roleCounts'   => $counts,
		'roleStatusCounts' => $roleStatusCounts,
		'roleHolders'  => $canUsers ? $roleHolders : array(),
		'roleDefaults' => $roleDefaults,
		'role_revision' => $revision,
		'role_source'  => $sourceId,
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
		'canUsers'     => $canUsers,
		'revisorSet'   => $revisorUser > 0,
		'canRolesWrite'=> perm_can('settings.roles.manage', 'write'),
		'canAudit'     => $canAudit,
		'canExport'    => perm_can('settings.import_export', 'read'),
		'userNames'    => $userNames,
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
		'permission' => array('permission.%', 'would-deny', 'would_deny', 'unguarded', 'denied', 'csrf'),
		'setting'    => array('setting.%', 'integration.%'),
		'invoicing'  => array('data.invoiced', 'data.credited'),
		'data'       => array('data.%'),
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
		'objekt_id' => preg_match('/^[0-9]{1,12}$/', (string) ($get['objekt_id'] ?? '')) ? (string) $get['objekt_id'] : '',
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
	foreach (array('fra', 'til', 'bruger', 'type', 'objekt', 'objekt_id', 'q') as $k) {
		if (($f[$k] ?? '') !== '') {
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
		$w[] = "handling in ('permission.would_deny', 'would-deny', 'would_deny')";
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
	if (($f['objekt_id'] ?? '') !== '' && $f['objekt'] !== '' && isset($cols['objekt_id'])) {
		$w[] = "objekt_id = '" . db_escape_string($f['objekt_id']) . "'";
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
			function_exists('st_local_time') ? st_local_time((string) $r['tidspunkt'], 'Y-m-d H:i:s') : substr((string) $r['tidspunkt'], 0, 19), $r['brugernavn'], $r['handling'],
			ifset($r, 'objekt_type', ''), ifset($r, 'objekt_id', ''), $r['detaljer'], $r['ip'], ifset($r, 'kilde', ''),
		)), ';');
	}
	fclose($out);
}
