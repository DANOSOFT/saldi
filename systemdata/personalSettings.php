<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/personalSettings.php --- ver 5.1.0 --- 2026.11.03 ---
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
// 20260916 Sawaneh Consolidated personal settings page (language, colours, popup/expiry
//                  choices, contact details, 2FA, password). Replaces the "Personlige valg"
//                  section in diverse.php and the self-service page brugerdata.php.
//                  Controller (POST -> save -> redirect) above, view below.
// 20260927 Sawaneh Topbar spec 2026-09-17 step 1b: three tabs (Profil / Sikkerhed / Min adgang),
//                  cluster placement, active sessions with "log out other devices", current
//                  password required for email/phone/2FA/password changes (spec 4.5), audit log.
// 20260928 Sawaneh Back button top-left in the theme colour, as elsewhere in the system (was Close top-right).
// 20260928 Sawaneh Popup windows, background colour and highlight removed (settings redesign spec, Personal settings).
// 20260929 Sawaneh Order autocomplete moved here from Ordrerelaterede valg, saved through SettingsService.
// 20260930 Sawaneh Notifications tab: daily e-mail summary on/off (Adam: summary only).
// 20261004 Sawaneh Bank status in the cash journal is a personal setting here when the bank integration is live (G2.7).
// 20261005 Sawaneh Bar placement option removed: the cluster is always in the top bar (topbar addendum 2026-10-05 §2).
// 20261008 Sawaneh Tema (light / dark / system) under Udseende, stored as personal.profile.theme (settings ui/theme).
// 20261009 Sawaneh The theme field waits for round 2 (settings decision 23): shown and saved only with SALDI_THEME_CHOICE.
// 20261009 Sawaneh Tekststørrelse Normal / Større (personal.profile.text_size, front-page addendum 3 §3.3); the shell reloads on change.
// 20261013 Sawaneh The back link carries accesskey L, so the shell hides it like on every migrated page (breadcrumb instead).
// 20261014 Sawaneh Review: CSRF token on every form and checked before any POST is handled.
// 20261031 CL/ASR The confirm-password flash's findtekst fallback is id 5625's full Danish text, as the tekster guardrail requires.
// 20261031 CL/ASR Fallback texts 7031 and 5639 match importfiler/tekster.csv.
// 20261101 CL/ASR The personal colour choice (Knapfarve / Tekstfarve på knapper under Udseende) and its save path are removed.
//                 Everyone gets the standard colours (includes/online.php); stored colour rows are left as they are.
// 20261101 CL/ASR Tilbage is the shared settings button (settings_back_button(): small kit button, arrow, above the title) instead of the filled one in the user's colour.
//                 It goes where the user came from, or Oversigt, also inside the shell (it went to Oversigt there). Alt+L kept.
// 20261102 CL/ASR Tilbage comes from settings_back_link() alone: the newest page outside the settings system, skipping old addresses that redirect here, else Oversigt.
//                 settings_back_button() adds Alt+L itself; the kit is linked as ?v=20261102.
// 20261103 CL/ASR QA: two-factor login needs an e-mail or phone stored on the login itself (login.php reads brugere): switching it
//                 on with only the employee's e-mail stores that e-mail on the login, and the last channel cannot be cleared while
//                 two-factor is on. An expired form (err=csrf) now says so.

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var string $brugernavn
 * @var int    $bruger_id
 * @var string $db
 * @var string $db_encode
 * @var int    $sprog_id
 * @var mixed  $revisor
 * @var string $rettigheder
 * @var string $regnskab
 */

@session_start();
$s_id = session_id();
ob_start();

$title = "Personlige indstillinger";
$css = "../css/personalSettings.css?v=20261101";
$permission_key = 'any';

include(__DIR__ . "/../includes/connect.php");
// The `online` table lives in the master database: read this user's sessions now.
$sessionRows = personal_settings_sessions($s_id);
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/notifications.php");
include_once(__DIR__ . "/../includes/settings/SettingsService.php");
include_once(__DIR__ . "/../includes/settings/components.php"); // settings_back_button(), the Tilbage of every settings page

$contextQuery = personal_settings_context_query($_GET, $_POST);
$selfUrl = 'personalSettings.php' . ($contextQuery !== '' ? '?' . $contextQuery : '');
$tab = isset($_GET['tab']) && in_array($_GET['tab'], array('profile', 'security', 'access', 'notifications'), true) ? $_GET['tab'] : 'profile';

$isRevisor = (bool) $revisor;
$canEdit = ((int) $bruger_id !== 0);

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit && (!isset($_POST['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token']))) {
	audit_log('csrf', 'personalSettings.php');
	ob_end_clean();
	header('Location: ' . $selfUrl . ($contextQuery !== '' ? '&' : '?') . 'err=csrf');
	exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
	$action = isset($_POST['action']) ? (string) $_POST['action'] : 'save';
	$result = array();
	if ($action === 'logout_others') {
		include(__DIR__ . "/../includes/connect.php");
		db_modify("delete from online where brugernavn = '" . db_escape_string((string) $brugernavn) . "' and db = '" . db_escape_string((string) $db) . "' and session_id != '" . db_escape_string($s_id) . "'", __FILE__ . " linje " . __LINE__);
		$result = array('tab' => 'security', 'sessions' => 'out');
	} else {
		$result = personal_settings_save($_POST, (int) $bruger_id, (string) $brugernavn, (string) $db, $isRevisor);
		if (!empty($result['language'])) {
			// The session row lives in the master database: back to it for the last write.
			include(__DIR__ . "/../includes/connect.php");
			db_modify("update online set language_id = '" . (int) $result['language'] . "' where session_id = '" . db_escape_string($s_id) . "'", __FILE__ . " linje " . __LINE__);
			unset($result['language']);
		}
	}
	ob_end_clean();
	header('Location: ' . $selfUrl . ($contextQuery !== '' ? '&' : '?') . http_build_query($result));
	exit;
}

$flash = personal_settings_flash($_GET, (int) $sprog_id);
$data = $canEdit ? personal_settings_load((int) $bruger_id, $isRevisor, (string) $brugernavn, (string) $rettigheder, $sessionRows, $s_id, (int) $sprog_id) : null;
personal_settings_view($data, $flash, $selfUrl, $tab, (int) $sprog_id, (string) $db_encode);

// ---------------------------------------------------------------- controller helpers

/**
 * This user's sessions in the master `online` table (same login name, same company).
 * Must run before includes/online.php switches the connection.
 *
 * @return array<int, array{session_id: string, logtime: int}>
 */
function personal_settings_sessions(string $sessionId): array
{
	$sessionId = db_escape_string($sessionId);
	$me = db_fetch_array(db_select("select brugernavn, db from online where session_id = '$sessionId' order by logtime desc limit 1", __FILE__ . " linje " . __LINE__));
	if (!$me) {
		return array();
	}
	$rows = array();
	$q = db_select("select session_id, logtime from online where brugernavn = '" . db_escape_string((string) $me['brugernavn']) . "' and db = '" . db_escape_string((string) $me['db']) . "' order by logtime desc", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$rows[] = array('session_id' => (string) $r['session_id'], 'logtime' => (int) $r['logtime']);
	}
	return $rows;
}

/**
 * Carry the shell/popup context flags across the redirect so the page keeps
 * behaving as an in-frame or popup page after saving.
 */
function personal_settings_context_query(array $get, array $post): string
{
	$parts = array();
	if (!empty($get['inframe']) || !empty($post['inframe'])) {
		$parts[] = 'inframe=1';
	}
	if (!empty($get['popup']) || !empty($post['popup_window'])) {
		$parts[] = 'popup=1';
	}
	return implode('&', $parts);
}

/**
 * True when $password is the user's current password (md5 legacy or saldikrypt).
 */
function personal_settings_password_ok(int $brugerId, string $password): bool
{
	if ($password === '') {
		return false;
	}
	$r = db_fetch_array(db_select("select kode from brugere where id = $brugerId", __FILE__ . " linje " . __LINE__));
	$stored = isset($r['kode']) ? (string) $r['kode'] : '';
	return $stored !== '' && ($stored === md5($password) || $stored === saldikrypt($brugerId, $password));
}

/**
 * Persist everything the form posted. Writes go to the same places the old pages
 * wrote (grupper USET, settings lager/globals, brugere), so nothing else changes.
 *
 * @return array<string, mixed> Query parameters for the redirect.
 */
function personal_settings_save(array $post, int $brugerId, string $brugernavn, string $db, bool $isRevisor): array
{
	$tab = isset($post['tab']) && in_array($post['tab'], array('profile', 'security', 'notifications'), true) ? $post['tab'] : 'profile';
	$out = array('tab' => $tab, 'saved' => 1);
	$reloadShell = false;
	$current = personal_settings_load($brugerId, $isRevisor, $brugernavn, '', array(), '', 1);

	if ($tab === 'profile') {
		$lang = isset($post['language_id']) ? (int) $post['language_id'] : 0;
		if ($lang >= 1 && $lang <= 3 && $lang !== $current['language']) {
			if (!$isRevisor) {
				db_modify("update brugere set language_id = $lang where id = $brugerId", __FILE__ . " linje " . __LINE__);
			}
			saldi_setcookie('languageId', (string) $lang, time() + (10 * 365 * 24 * 60 * 60), '/');
			$out['language'] = $lang;
			$reloadShell = true;
		}

		// Popup windows are removed (settings redesign spec, Personal settings): saving switches
		// them off, as the old Personlige valg page did. Background colour/highlight are no
		// longer edited here; their stored values are left as they are.
		if ($current['popup']) {
			db_modify("update grupper set box2 = '' where art = 'USET' and kodenr = '$brugerId'", __FILE__ . " linje " . __LINE__);
			$reloadShell = true;
		}

		// Moved here from Ordrerelaterede valg: it was always a per-user choice (settings redesign spec §4).
		$theme = isset($post['theme']) && in_array($post['theme'], array('light', 'dark', 'system'), true) ? (string) $post['theme'] : 'light';
		if (defined('SALDI_THEME_CHOICE') && SALDI_THEME_CHOICE && $theme !== $current['theme']) {
			SettingsService::save('personal.profile.theme', $theme, $brugerId);
			$reloadShell = true;
		}
		$textSize = isset($post['text_size']) && $post['text_size'] === 'large' ? 'large' : 'normal';
		if ($textSize !== $current['textSize']) {
			SettingsService::save('personal.profile.text_size', $textSize, $brugerId);
			$reloadShell = true;
		}
		SettingsService::save('personal.orders.autocomplete', !empty($post['order_autocomplete']), $brugerId);
		if (SettingsService::hasModule('bank')) {
			// Moved here from the old bank page (settings redesign G2.7): the status icon in the cash journal is per user.
			SettingsService::save('personal.profile.bank_status', !empty($post['bank_status']), $brugerId);
		}

		if (isset($post['due_date_warning_days']) && $post['due_date_warning_days'] !== '') {
			update_settings_value('due_date_warning_days', 'lager', max(1, intval($post['due_date_warning_days'])), 'Days before expiry to warn', $brugerId);
		}

		// Contact details are the 2FA / password-reset channel: current password required (spec 4.5).
		if (!$isRevisor) {
			$email = trim(isset($post['email']) ? (string) $post['email'] : '');
			$tlf = trim(isset($post['tlf']) ? (string) $post['tlf'] : '');
			if ($email !== $current['email'] || $tlf !== $current['tlf']) {
				if ($current['twofactor'] && $email === '' && $tlf === '') {
					// login.php sends the second factor to brugere.email/tlf: without both it would skip it.
					$out['tfa'] = 'missing';
				} elseif (personal_settings_password_ok($brugerId, isset($post['confirm_kode']) ? (string) $post['confirm_kode'] : '')) {
					db_modify("update brugere set email = '" . db_escape_string($email) . "', tlf = '" . db_escape_string(mb_substr($tlf, 0, 16)) . "' where id = $brugerId", __FILE__ . " linje " . __LINE__);
					audit_log('user.contact', $brugernavn . ': e-mail/telefon ændret');
				} else {
					$out['confirm'] = 'missing';
				}
			}
		}
		audit_log('user.settings', $brugernavn . ': personlige indstillinger gemt');
	}

	if ($tab === 'security' && !$isRevisor) {
		personal_settings_save_security($post, $brugerId, $brugernavn, $db, $current, $out);
	}

	if ($tab === 'notifications' && !$isRevisor && function_exists('notif_types')) {
		$on = isset($post['types']) && is_array($post['types']) ? $post['types'] : array();
		foreach (notif_types() as $type => $label) {
			update_settings_value('type_' . $type, 'notifications', in_array($type, $on, true) ? '1' : '0', 'Notification type on/off', $brugerId);
		}
		update_settings_value('digest', 'notifications', !empty($post['digest']) ? '1' : '0', 'Daily summary by e-mail', $brugerId);
	}

	if ($reloadShell) {
		// index/main.php polls this cookie and reloads itself, so sidebar colours and texts follow.
		saldi_setcookie('refresh_opener', 'true', time() + 30, '/');
	}
	return $out;
}

/**
 * Two-factor and password: only with the current password (spec 4.5), every change audited.
 *
 * @param array<string, mixed> $current From personal_settings_load().
 * @param array<string, mixed> $out     Redirect parameters; 'tfa' / 'pw' / 'confirm' are added here.
 */
function personal_settings_save_security(array $post, int $brugerId, string $brugernavn, string $db, array $current, array &$out): void
{
	$twofactor = !empty($post['twofactor']);
	$new1 = isset($post['nykode1']) ? trim((string) $post['nykode1']) : '';
	$new2 = isset($post['nykode2']) ? trim((string) $post['nykode2']) : '';
	$wantsPassword = ($new1 !== '' || $new2 !== '');
	if ($twofactor === $current['twofactor'] && !$wantsPassword) {
		return;
	}
	if (!personal_settings_password_ok($brugerId, isset($post['glkode']) ? (string) $post['glkode'] : '')) {
		$out['confirm'] = 'missing';
		return;
	}
	if ($twofactor !== $current['twofactor']) {
		if ($twofactor && $current['email'] === '' && $current['tlf'] === '') {
			$out['tfa'] = 'missing';
		} else {
			if ($twofactor && $current['storedEmail'] === '' && $current['tlf'] === '') {
				// The e-mail shown is the employee's: login.php reads the login's own e-mail, so it is stored there first.
				db_modify("update brugere set email = '" . db_escape_string($current['email']) . "' where id = $brugerId", __FILE__ . " linje " . __LINE__);
				audit_log('user.contact', $brugernavn . ': e-mail sat til tofaktor');
			}
			db_modify("update brugere set twofactor = '" . ($twofactor ? 't' : 'f') . "' where id = $brugerId", __FILE__ . " linje " . __LINE__);
			audit_log('user.twofactor', $brugernavn . ': ' . ($twofactor ? 'slået til' : 'slået fra'));
		}
	}
	if ($wantsPassword) {
		if ($brugernavn === 'test' && $db === 'test') {
			$out['pw'] = 'demo';
		} elseif ($new1 === '' || $new1 !== $new2) {
			$out['pw'] = 'mismatch';
		} else {
			$hash = db_escape_string(saldikrypt($brugerId, $new1));
			db_modify("update brugere set kode = '$hash' where id = $brugerId", __FILE__ . " linje " . __LINE__);
			audit_log('user.password', $brugernavn . ': adgangskode ændret');
			$out['pw'] = 'changed';
		}
	}
}

/**
 * Current values for the form.
 *
 * @param array<int, array{session_id: string, logtime: int}> $sessionRows
 * @return array<string, mixed>
 */
function personal_settings_load(int $brugerId, bool $isRevisor, string $brugernavn, string $rettigheder, array $sessionRows, string $sessionId, int $sprogId): array
{
	global $sprog_id;

	$u = array();
	if (!$isRevisor) {
		// select * : keep working on company databases that predate the email/tlf/twofactor columns.
		$u = db_fetch_array(db_select("select * from brugere where id = $brugerId", __FILE__ . " linje " . __LINE__));
	}
	$u = ($u ?: array()) + array('brugernavn' => $brugernavn, 'email' => '', 'tlf' => '', 'twofactor' => 'f', 'language_id' => 1, 'ansat_id' => 0, 'role_id' => 0);
	$name = trim((string) $u['brugernavn']);
	$email = trim((string) $u['email']);
	if ((int) $u['ansat_id'] > 0) {
		$a = db_fetch_array(db_select("select navn, email from ansatte where id = " . (int) $u['ansat_id'], __FILE__ . " linje " . __LINE__));
		if ($a && trim((string) $a['navn']) !== '') {
			$name = trim((string) $a['navn']);
		}
		if ($a && $email === '') {
			$email = trim((string) $a['email']);
		}
	}
	$twofactor = in_array($u['twofactor'], array('t', true, '1', 1), true);

	$uset = db_fetch_array(db_select("select box2 from grupper where art = 'USET' and kodenr = '$brugerId'", __FILE__ . " linje " . __LINE__));

	$languages = array();
	$fp = @fopen(__DIR__ . "/../importfiler/tekster.csv", "r");
	if ($fp) {
		$header = explode("\t", trim((string) fgets($fp)));
		fclose($fp);
		for ($i = 1; $i < count($header) && $i <= 3; $i++) {
			$languages[$i] = trim($header[$i]);
		}
	}
	if (!$languages) {
		$languages = array(1 => 'Dansk', 2 => 'English', 3 => 'Norsk');
	}

	// My access (spec 4.3): the role when one is assigned, else the legacy string.
	$roleName = '';
	$levels = array();
	$admins = array();
	if (function_exists('perm_user_levels')) {
		$levels = perm_user_levels($brugerId, $rettigheder);
		if (!$isRevisor && (int) $u['role_id'] > 0) {
			foreach (perm_roles() as $role) {
				if ($role['id'] === (int) $u['role_id']) {
					$roleName = perm_role_name($role, $sprogId);
				}
			}
		}
		if ($roleName === '') {
			$roleName = $isRevisor ? findtekst('2562|Revisor', $sprogId) : ((substr($rettigheder, 1, 1) === '1') ? findtekst('330|Administrator', $sprogId) : findtekst('990|Bruger', $sprogId));
		}
		$adminRole = function_exists('perm_role_id_by_key') ? perm_role_id_by_key('administrator') : 0;
		$q = db_select("select b.brugernavn, b.role_id, b.rettigheder, a.navn from brugere b left join ansatte a on a.id = b.ansat_id order by b.brugernavn", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$isAdmin = ($adminRole > 0 && (int) $r['role_id'] === $adminRole) || ((int) $r['role_id'] <= 0 && substr((string) $r['rettigheder'], 1, 1) === '1');
			if ($isAdmin) {
				$admins[] = trim((string) $r['navn']) !== '' ? trim((string) $r['navn']) : (string) $r['brugernavn'];
			}
		}
	}

	return array(
		'name'      => $name,
		'username'  => trim((string) $u['brugernavn']),
		'email'     => $email,
		'storedEmail' => trim((string) $u['email']),
		'tlf'       => trim((string) $u['tlf']),
		'twofactor' => $twofactor,
		'language'  => max(1, (int) $sprog_id ?: (int) $u['language_id']),
		'languages' => $languages,
		'revisor'   => $isRevisor,
		'popup'     => ($uset && trim((string) $uset['box2']) !== ''),
		'theme'     => in_array(SettingsService::get('personal.profile.theme', $brugerId), array('light', 'dark', 'system'), true) ? SettingsService::get('personal.profile.theme', $brugerId) : 'light',
		'textSize'  => SettingsService::get('personal.profile.text_size', $brugerId) === 'large' ? 'large' : 'normal',
		'warnDays'  => get_due_date_warning_days($brugerId),
		'autocomplete' => (bool) SettingsService::get('personal.orders.autocomplete', $brugerId),
		'bankFeature' => SettingsService::hasModule('bank'),
		'bankStatus' => (bool) SettingsService::get('personal.profile.bank_status', $brugerId),
		'sessions'  => $sessionRows,
		'sessionId' => $sessionId,
		'roleName'  => $roleName,
		'levels'    => $levels,
		'admins'    => $admins,
		'notifOff'  => function_exists('notif_disabled_types') ? notif_disabled_types($brugerId) : array(),
		'digest'    => ((string) get_settings_value('digest', 'notifications', '0', $brugerId) === '1'),
	);
}

/**
 * Messages for the banner row, from the redirect's query parameters.
 *
 * @return array<int, array{type: string, text: string}>
 */
function personal_settings_flash(array $get, int $sprogId): array
{
	$flash = array();
	$pw = isset($get['pw']) ? (string) $get['pw'] : '';
	if ($pw === 'changed') {
		$flash[] = array('type' => 'ok', 'text' => findtekst('5518|Adgangskoden er ændret', $sprogId));
	} elseif ($pw === 'mismatch') {
		$flash[] = array('type' => 'err', 'text' => findtekst('5520|De to nye adgangskoder er ikke ens', $sprogId));
	} elseif ($pw === 'demo') {
		$flash[] = array('type' => 'warn', 'text' => findtekst('5528|Adgangskoden kan ikke ændres i demoversionen', $sprogId));
	}
	if (isset($get['confirm']) && $get['confirm'] === 'missing') {
		$flash[] = array('type' => 'err', 'text' => findtekst('5625|Den nuværende adgangskode mangler eller er forkert – ændringer af e-mail, telefon, tofaktor og adgangskode blev ikke gemt', $sprogId));
	}
	if (isset($get['tfa']) && $get['tfa'] === 'missing') {
		$flash[] = array('type' => 'warn', 'text' => findtekst('5516|Tofaktor-login kræver et telefonnummer eller en e-mail', $sprogId));
	}
	if (isset($get['err']) && $get['err'] === 'csrf') {
		$flash[] = array('type' => 'err', 'text' => findtekst('7804|Siden var udløbet, så intet blev gemt. Prøv igen.', $sprogId));
	}
	if (isset($get['sessions']) && $get['sessions'] === 'out') {
		$flash[] = array('type' => 'ok', 'text' => findtekst('5623|Andre enheder er logget ud', $sprogId));
	}
	if (!empty($get['saved'])) {
		$flash[] = array('type' => 'ok', 'text' => findtekst('5517|Dine indstillinger er gemt', $sprogId));
	}
	return $flash;
}

// ---------------------------------------------------------------- view

function personal_settings_view(?array $d, array $flash, string $selfUrl, string $tab, int $sprogId, string $dbEncode): void
{
	$charset = ($dbEncode === 'UTF8') ? 'UTF-8' : 'ISO-8859-1';
	$h = function ($s) use ($charset): string {
		return htmlspecialchars((string) $s, ENT_QUOTES, $charset);
	};
	$t = function (string $text) use ($sprogId, $h): string {
		return $h(findtekst($text, $sprogId));
	};
	$tabUrl = function (string $name) use ($selfUrl, $h): string {
		return $h($selfUrl . (strpos($selfUrl, '?') === false ? '?' : '&') . 'tab=' . $name);
	};
	$flashIcons = array('ok' => 'bx-check-circle', 'warn' => 'bx-error', 'err' => 'bx-x-circle');
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel='stylesheet' href='../css/saldi-kit.css?v=20261102'>
<script>document.title = <?= json_encode(mb_convert_encoding(findtekst('5500|Personlige indstillinger', $sprogId), 'UTF-8', $charset)) ?>;</script>
<div class="ps-page">
  <div class="sk ps-backbar"><?= settings_back_button() /* opened from anywhere in Saldi, like the hub: the last page outside the settings, or Oversigt */ ?></div>
  <header class="ps-head">
    <div>
      <h1><i class='bx bx-cog'></i><?= $t('5500|Personlige indstillinger') ?></h1>
      <p class="ps-sub"><?= $t('5522|Gælder kun for dig, ikke for andre brugere i regnskabet') ?></p>
    </div>
  </header>

  <?php foreach ($flash as $f) { ?>
  <div class="ps-flash ps-flash-<?= $h($f['type']) ?>"><i class='bx <?= $flashIcons[$f['type']] ?>'></i><span><?= $h($f['text']) ?></span></div>
  <?php } ?>

  <?php if ($d === null) { ?>
  <div class="ps-notice"><i class='bx bx-info-circle'></i> <?= $t('5533|Personlige indstillinger findes kun for regnskabets egne brugere. Du er logget ind som revisor/administrator udefra.') ?></div>
</div>
	<?php return; } ?>

  <nav class="ps-tabs">
    <a class="<?= $tab === 'profile' ? 'on' : '' ?>" href="<?= $tabUrl('profile') ?>"><i class='bx bx-user'></i><?= $t('5613|Profil') ?></a>
    <?php if (!$d['revisor']) { ?><a class="<?= $tab === 'security' ? 'on' : '' ?>" href="<?= $tabUrl('security') ?>"><i class='bx bx-shield-quarter'></i><?= $t('5511|Sikkerhed') ?></a><?php } ?>
    <a class="<?= $tab === 'access' ? 'on' : '' ?>" href="<?= $tabUrl('access') ?>"><i class='bx bx-key'></i><?= $t('5614|Min adgang') ?></a>
    <?php if (!$d['revisor']) { ?><a class="<?= $tab === 'notifications' ? 'on' : '' ?>" href="<?= $tabUrl('notifications') ?>"><i class='bx bx-bell'></i><?= $t('5639|Notifikationer') ?></a><?php } ?>
  </nav>

  <?php
	if ($tab === 'security' && !$d['revisor']) {
		personal_settings_view_security($d, $h, $t, $selfUrl);
	} elseif ($tab === 'access') {
		personal_settings_view_access($d, $h, $t, $sprogId);
	} elseif ($tab === 'notifications' && !$d['revisor']) {
		personal_settings_view_notifications($d, $h, $t, $selfUrl);
	} else {
		personal_settings_view_profile($d, $h, $t, $selfUrl);
	}
	?>
</div>
<script>
(function () {
	var form = document.getElementById('ps-form');
	if (!form) { return; }
	var dirty = document.getElementById('ps-dirty');

	var initial = snapshot();
	function snapshot() {
		return Array.prototype.map.call(form.elements, function (el) {
			if (!el.name) { return ''; }
			return el.name + '=' + (el.type === 'checkbox' || el.type === 'radio' ? (el.checked ? '1' : '0') : el.value);
		}).join('&');
	}
	function markDirty() {
		var changed = snapshot() !== initial;
		window.docChange = changed;
		if (dirty) { dirty.classList.toggle('on', changed); }
	}

	form.addEventListener('input', markDirty);
	form.addEventListener('change', markDirty);
	form.addEventListener('submit', function () {
		window.docChange = false;
		var b = document.getElementById('ps-submit');
		if (b) { b.disabled = true; }
	});
})();
</script>
	<?php
}

function personal_settings_view_profile(array $d, callable $h, callable $t, string $selfUrl): void
{
	?>
  <form method="post" action="<?= $h($selfUrl) ?>" id="ps-form" autocomplete="off"><input type="hidden" name="csrf_token" value="<?= $h($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="tab" value="profile">
    <?php if (strpos($selfUrl, 'popup=1') !== false) { ?><input type="hidden" name="popup_window" value="1"><?php } ?>

    <section class="ps-card">
      <h2><i class='bx bx-user'></i><?= $t('5521|Konto') ?></h2>
      <div class="ps-grid">
        <div class="ps-field">
          <label><?= $t('5531|Navn') ?></label>
          <input class="ps-input" type="text" value="<?= $h($d['name']) ?>" readonly>
        </div>
        <div class="ps-field">
          <label><?= $t('5530|Brugernavn') ?></label>
          <input class="ps-input" type="text" value="<?= $h($d['username']) ?>" readonly>
        </div>
        <?php if (!$d['revisor']) { ?>
        <div class="ps-field">
          <label for="ps-email"><?= $t('52|E-mail') ?></label>
          <input class="ps-input" type="email" id="ps-email" name="email" value="<?= $h($d['email']) ?>">
          <span class="ps-help"><?= $t('5523|Bruges til tofaktor-koder') ?></span>
        </div>
        <div class="ps-field">
          <label for="ps-tlf"><?= $t('37|Telefon') ?></label>
          <input class="ps-input" type="tel" id="ps-tlf" name="tlf" value="<?= $h($d['tlf']) ?>">
          <span class="ps-help"><?= $t('5523|Bruges til tofaktor-koder') ?></span>
        </div>
        <div class="ps-field ps-field-full">
          <label for="ps-confirm"><?= $t('5619|Bekræft med din nuværende adgangskode') ?></label>
          <input class="ps-input ps-input-half" type="password" id="ps-confirm" name="confirm_kode" autocomplete="current-password">
          <span class="ps-help"><?= $t('5618|Indtast din nuværende adgangskode for at ændre e-mail, telefon, tofaktor eller adgangskode') ?></span>
        </div>
        <?php } else { ?>
        <div class="ps-field ps-field-full">
          <span class="ps-help"><?= $t('5535|Kontaktoplysninger, tofaktor-login og adgangskode hører til regnskabets egne brugere og kan ikke ændres her, da du er logget ind som revisor/administrator udefra.') ?></span>
        </div>
        <?php } ?>
			<?php if (defined('SALDI_THEME_CHOICE') && SALDI_THEME_CHOICE) { // round 2 (settings decision 23) ?>
        <div class="ps-field">
          <label for="ps-theme"><?= $t('1075|Tema') ?></label>
          <select class="ps-select" id="ps-theme" name="theme">
            <?php foreach (array('light' => '6917|Lys', 'dark' => '6918|Mørk', 'system' => '2377|System') as $tv => $tl) { ?>
            <option value="<?= $tv ?>"<?= $d['theme'] === $tv ? ' selected' : '' ?>><?= $t($tl) ?></option>
            <?php } ?>
          </select>
          <small class="ps-help"><?= $t('6919|Lys eller mørk visning. System følger din enheds indstilling.') ?></small>
        </div>
			<?php } ?>
        <div class="ps-field" id="personal.profile.text_size">
          <label for="ps-text-size"><?= $t('2283|Tekststørrelse') ?></label>
          <select class="ps-select" id="ps-text-size" name="text_size">
            <option value="normal"<?= $d['textSize'] === 'normal' ? ' selected' : '' ?>><?= $t('7029|Normal') ?></option>
            <option value="large"<?= $d['textSize'] === 'large' ? ' selected' : '' ?>><?= $t('7030|Større (+11 %)') ?></option>
          </select>
          <small class="ps-help"><?= $t('7031|Gør teksten i topbaren, på Oversigt og i Indstillinger ca. 11 % større. Browserens egen zoom virker oveni.') ?></small>
        </div>
        <div class="ps-field">
          <label for="ps-language"><?= $t('801|Sprog') ?></label>
          <select class="ps-select" id="ps-language" name="language_id">
            <?php foreach ($d['languages'] as $id => $label) { ?>
            <option value="<?= (int) $id ?>"<?= ($id === $d['language']) ? ' selected' : '' ?>><?= $h($label) ?></option>
            <?php } ?>
          </select>
        </div>
      </div>
    </section>

    <section class="ps-card">
      <h2><i class='bx bx-error-circle'></i><?= $t('5641|Advarsler') ?></h2>
      <div class="ps-grid">
        <div class="ps-field ps-field-full" id="personal.orders.autocomplete">
          <label class="ps-check">
            <input type="checkbox" name="order_autocomplete" value="on"<?= $d['autocomplete'] ? ' checked' : '' ?>>
            <span class="ps-check-txt"><b><?= $t('5704|Anvend autosøgning på ordrer') ?></b><span><?= $t('5705|Slår autosøgning til på ordresider. Gælder kun for dig.') ?></span></span>
          </label>
        </div>
			<?php if ($d['bankFeature']) { ?>
        <div class="ps-field ps-field-full" id="personal.profile.bank_status">
          <label class="ps-check">
            <input type="checkbox" name="bank_status" value="on"<?= $d['bankStatus'] ? ' checked' : '' ?>>
            <span class="ps-check-txt"><b><?= $t('6377|Vis bankstatus i kassekladden') ?></b><span><?= $t('6378|Et ikon i kassekladden viser, om bankforbindelsen er aktiv') ?></span></span>
          </label>
        </div>
			<?php } ?>
        <div class="ps-field">
          <label for="ps-warn"><?= $t('5006|Advar om udløb (dage før)') ?></label>
          <input class="ps-input ps-input-short" type="number" min="1" id="ps-warn" name="due_date_warning_days" value="<?= (int) $d['warnDays'] ?>">
        </div>
      </div>
    </section>

    <footer class="ps-save">
      <span class="ps-dirty" id="ps-dirty"><i class='bx bx-edit-alt'></i> <?= $t('5532|Du har ændringer, der ikke er gemt') ?></span>
      <button class="ps-btn ps-btn-primary" type="submit" id="ps-submit"><i class='bx bx-save'></i><?= $t('3|Gem') ?></button>
    </footer>
  </form>
	<?php
}

function personal_settings_view_security(array $d, callable $h, callable $t, string $selfUrl): void
{
	?>
  <form method="post" action="<?= $h($selfUrl) ?>" id="ps-form" autocomplete="off"><input type="hidden" name="csrf_token" value="<?= $h($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="tab" value="security">

    <section class="ps-card">
      <h2><i class='bx bx-shield-quarter'></i><?= $t('5511|Sikkerhed') ?></h2>
      <p class="ps-card-help"><?= $t('5618|Indtast din nuværende adgangskode for at ændre e-mail, telefon, tofaktor eller adgangskode') ?></p>
      <div class="ps-grid">
        <div class="ps-field ps-field-full">
          <label class="ps-check">
            <input type="checkbox" name="twofactor" value="on" id="ps-twofactor"<?= $d['twofactor'] ? ' checked' : '' ?>>
            <span class="ps-check-txt"><b><?= $t('5515|Tofaktor-login (kode via SMS eller e-mail)') ?></b><span><?= $t('5516|Tofaktor-login kræver et telefonnummer eller en e-mail') ?> · <?= $t('5624|Tofaktor-metode: SMS når et telefonnummer er angivet, ellers e-mail') ?></span></span>
          </label>
        </div>
        <div class="ps-field">
          <label for="ps-glkode"><?= $t('5512|Nuværende adgangskode') ?></label>
          <input class="ps-input" type="password" id="ps-glkode" name="glkode" autocomplete="current-password">
          <span class="ps-help"><?= $t('5619|Bekræft med din nuværende adgangskode') ?></span>
        </div>
        <div class="ps-field"></div>
        <div class="ps-field">
          <label for="ps-nykode1"><?= $t('5513|Ny adgangskode') ?></label>
          <input class="ps-input" type="password" id="ps-nykode1" name="nykode1" autocomplete="new-password">
          <span class="ps-help"><?= $t('5525|Lad felterne stå tomme for at beholde din adgangskode') ?></span>
        </div>
        <div class="ps-field">
          <label for="ps-nykode2"><?= $t('5514|Bekræft ny adgangskode') ?></label>
          <input class="ps-input" type="password" id="ps-nykode2" name="nykode2" autocomplete="new-password">
        </div>
      </div>
    </section>

    <footer class="ps-save">
      <span class="ps-dirty" id="ps-dirty"><i class='bx bx-edit-alt'></i> <?= $t('5532|Du har ændringer, der ikke er gemt') ?></span>
      <button class="ps-btn ps-btn-primary" type="submit" id="ps-submit"><i class='bx bx-save'></i><?= $t('3|Gem') ?></button>
    </footer>
  </form>

  <section class="ps-card">
    <h2><i class='bx bx-devices'></i><?= $t('5615|Aktive sessioner') ?></h2>
    <table class="ps-table">
      <thead><tr><th><?= $t('5556|Sidst aktiv') ?></th><th></th></tr></thead>
      <tbody>
        <?php foreach ($d['sessions'] as $s) { ?>
        <tr>
          <td><?= $h(date('Y-m-d H:i', $s['logtime'])) ?></td>
          <td><?php if ($s['session_id'] === $d['sessionId']) { ?><span class="ps-tag"><?= $t('5617|Denne enhed') ?></span><?php } ?></td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
    <?php if (count($d['sessions']) > 1) { ?>
    <form method="post" action="<?= $h($selfUrl) ?>" class="ps-inline-form"><input type="hidden" name="csrf_token" value="<?= $h($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="logout_others">
      <button class="ps-btn ps-btn-ghost" type="submit"><i class='bx bx-log-out-circle'></i><?= $t('5616|Log andre enheder ud') ?></button>
    </form>
    <?php } ?>
  </section>
	<?php
}

function personal_settings_view_access(array $d, callable $h, callable $t, int $sprogId): void
{
	$registry = function_exists('permission_registry') ? permission_registry() : array();
	$groups = function_exists('permission_groups') ? permission_groups() : array();
	?>
  <section class="ps-card">
    <h2><i class='bx bx-key'></i><?= $t('5614|Min adgang') ?></h2>
    <div class="ps-grid">
      <div class="ps-field">
        <label><?= $t('5553|Rolle') ?></label>
        <span class="ps-role"><?= $h($d['roleName']) ?></span>
      </div>
      <div class="ps-field">
        <label><?= $t('5620|Regnskabets administratorer') ?></label>
        <span><?= $d['admins'] ? $h(implode(', ', $d['admins'])) : '–' ?></span>
        <span class="ps-help"><?= $t('5621|Kontakt en administrator for at få ændret din adgang') ?></span>
      </div>
    </div>
    <div class="ps-access">
      <?php foreach ($groups as $group => $groupLabel) { ?>
      <div class="ps-access-group">
        <h4><?= $t($groupLabel) ?></h4>
        <?php foreach ($registry as $key => $def) {
        	if ($def['group'] !== $group) {
        		continue;
        	}
        	$level = isset($d['levels'][$key]) ? $d['levels'][$key] : 'none';
        ?>
        <div class="ps-access-row"><span><?= $t($def['label']) ?></span><span class="ps-level ps-level-<?= $h($level) ?>"><?= $h(perm_level_label($level, $sprogId)) ?></span></div>
        <?php } ?>
      </div>
      <?php } ?>
    </div>
  </section>
	<?php
}

function personal_settings_view_notifications(array $d, callable $h, callable $t, string $selfUrl): void
{
	$types = function_exists('notif_types') ? notif_types() : array();
	?>
  <form method="post" action="<?= $h($selfUrl) ?>" id="ps-form" autocomplete="off"><input type="hidden" name="csrf_token" value="<?= $h($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="tab" value="notifications">
    <section class="ps-card">
      <h2><i class='bx bx-bell'></i><?= $t('5639|Notifikationer') ?></h2>
      <p class="ps-card-help"><?= $t('5644|Vælg hvilke notifikationer du vil se i klokken') ?></p>
      <div class="ps-checks">
        <?php foreach ($types as $type => $label) { ?>
        <label class="ps-check">
          <input type="checkbox" name="types[]" value="<?= $h($type) ?>"<?= in_array($type, $d['notifOff'], true) ? '' : ' checked' ?>>
          <span class="ps-check-txt"><b><?= $t($label) ?></b></span>
        </label>
        <?php } ?>
      </div>
    </section>
    <section class="ps-card">
      <h2><i class='bx bx-envelope'></i><?= $t('52|E-mail') ?></h2>
      <div class="ps-checks">
        <label class="ps-check">
          <input type="checkbox" name="digest" value="1"<?= $d['digest'] ? ' checked' : '' ?>>
          <span class="ps-check-txt"><b><?= $t('5961|Send mig en daglig opsummering af ulæste notifikationer') ?></b>
          <span><?= $d['email'] !== '' ? $h(sprintf(findtekst('5962|Sendes én gang om dagen til %s, kun når der er noget nyt.', $d['language']), $d['email'])) : $t('5963|Tilføj en e-mail under Profil for at få opsummeringen.') ?></span></span>
        </label>
      </div>
    </section>
    <footer class="ps-save">
      <span class="ps-dirty" id="ps-dirty"><i class='bx bx-edit-alt'></i> <?= $t('5532|Du har ændringer, der ikke er gemt') ?></span>
      <button class="ps-btn ps-btn-primary" type="submit" id="ps-submit"><i class='bx bx-save'></i><?= $t('3|Gem') ?></button>
    </footer>
  </form>
	<?php
}
