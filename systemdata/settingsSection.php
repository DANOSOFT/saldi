<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/settingsSection.php --- lap 5.0.0 --- 2026.09.29 ---
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
// 20260929 Sawaneh Settings redesign phase 4a: a settings section generated from the registry
//                  (spec §7.3, §8). Controller: POST with CSRF -> validation -> conflict check ->
//                  transaction -> audit -> redirect. View below. No hand-written form.
// 20261002 Sawaneh Phase 4b: actions run through includes/settings/actions.php, sections gated by a module, on_save follow-ups.
// 20261002 Sawaneh Hand-over 2 Oct (A2, §8.0): a heading above each card, plain tab list, history as its own column, save bar
//                  only while something is unsaved, no Back button or in-page trail (the shell's topbar has the breadcrumb).
// 20261005 Sawaneh A field's own permission key is enforced on save and on actions (st_field_access).
// 20261005 Sawaneh 4d G1.2/G2.3: row actions (set a fiscal year active, delete an old year with its data behind the
//                  password), the create card of a table, a parent filter (the currency of the rates) kept in the URL,
//                  and a save that would post amounts first shows them and needs "Bogfør og gem" (audit V9).
// 20261005 Sawaneh Danger-zone actions ('danger_zone') run only with the user's password (§8.3); a refusal is audited.
// 20261005 Sawaneh Phase 4c: sections of 'kind' rows - tables through includes/settings/rows.php (validate first,
//                  then fields, then rows), actions row_delete / row_inactive / row_copy_year, year in the address.
// 20261004 Sawaneh §8.11: 'back' (a path inside Saldi) from the gear in the sub-bar shows "Tilbage" and survives saves.
// 20261004 Sawaneh List items with a 'feature' (bank) are hidden until the feature is on.
// 20261004 Sawaneh settings_after_section_save() runs once after all fields of a save (KDS colours).
// 20261004 Sawaneh Access follows the section's permission key only, not the old Indstillinger bit (decision 16).
// 20261004 Sawaneh A stored secret shows when it was set ("sat 12/9-2026") on form sections too.
// 20261004 Sawaneh G4.3: list items from the database ('items_from'), a one-off flash kept in the session.
// 20261004 Sawaneh G2.6: FTP test result flash.
// 20261003 Sawaneh G3.4: a run of fields may carry a heading ('group_label'), a card a help line ('sub_help').
// 20261003 Sawaneh G6.3: a section may carry its own permission key (settings.email).
// 20261002 Sawaneh Phase 4b batch 2 (G9): a section of 'kind' list renders includes/settings/listView.php - rows with a drawer
//                  per integration, each drawer its own form; an empty secret leaves the stored value (P8); item kept over redirects.
// 20261008 Sawaneh The Assist menu's guide is the section's installed guide (settings decision 21).

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var int    $sprog_id
 * @var int    $bruger_id
 * @var string $db_encode
 * @var string $regnskab
 */

@session_start();
$s_id = session_id();
ob_start();

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$title = "Indstillinger";
$css = "../css/unified-components.css?v=20261016";
$modulnr = 0; // the section's own permission key is required below
$permission_key = 'any';
$permission_post_read = false;

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/settingsRegistry.php");
include_once(__DIR__ . "/../includes/settings/components.php");
include_once(__DIR__ . "/../includes/settings/actions.php");
include_once(__DIR__ . "/../includes/settings/listView.php");
include_once(__DIR__ . "/../includes/settings/rowsView.php");
include_once(__DIR__ . "/../includes/settings/rowHooks.php");

$sections = getSettingsSections();
$sectionId = isset($_GET['s']) ? (string) $_GET['s'] : '';
if (!isset($sections[$sectionId])) {
	ob_end_clean();
	header('Location: settings.php');
	exit;
}
$section = $sections[$sectionId];
if (!empty($section['module']) && !settings_has_module($section['module'])) {
	ob_end_clean();
	header('Location: settings.php?err=module');
	exit;
}
$groups = getSettingsGroups();
$permission = isset($section['permission']) ? (string) $section['permission'] : $groups[$section['group']]['permission'];
require_permission($permission, 'read');
$canWrite = perm_can($permission, 'write');

$defs = settings_section_definitions($sectionId);
SettingsService::preload(array_keys($defs));
$sectionGuide = st_section_guide($section);
if ($sectionGuide !== '' && function_exists('page_help')) {
	page_help(array('guide' => $sectionGuide));
}
// Opened from a module page through the gear in the sub-bar (spec §8.11): a path inside Saldi only, kept through saves.
$returnTo = isset($_GET['back']) ? (string) $_GET['back'] : (isset($_POST['back']) ? (string) $_POST['back'] : '');
if (!preg_match('#^/[a-z_]+/[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_\-=&%.+]*)?$#', $returnTo)) {
	$returnTo = '';
}
$section['return_to'] = $returnTo;
// The name of the page the gear came from, for the "came from" chip (topbar addendum 2026-10-05 §3.1).
$returnLabel = '';
if ($returnTo !== '') {
	$returnLabel = isset($_GET['back_label']) ? (string) $_GET['back_label'] : (isset($_POST['back_label']) ? (string) $_POST['back_label'] : '');
	$returnLabel = mb_substr(trim(preg_replace('/[\x00-\x1f<>]/u', '', strip_tags($returnLabel))), 0, 60);
	if (st_charset() !== 'UTF-8') {
		$returnLabel = mb_convert_encoding($returnLabel, st_charset(), 'UTF-8');
	}
}
$section['return_label'] = $returnLabel;
$selfUrl = 'settingsSection.php?s=' . rawurlencode($sectionId) . ($returnTo !== '' ? '&back=' . rawurlencode($returnTo) . ($returnLabel !== '' ? '&back_label=' . rawurlencode(st_charset() !== 'UTF-8' ? mb_convert_encoding($returnLabel, 'UTF-8', st_charset()) : $returnLabel) : '') : '');
$isList = (!empty($section['kind']) && $section['kind'] === 'list');
$isRows = (!empty($section['kind']) && $section['kind'] === 'rows');
$tables = $isRows ? settings_rows_tables($section) : array();
$year = null;
$years = array();
foreach ($tables as $t) {
	if ($t['fiscal']) {
		$years = settings_rows_years();
		$year = (isset($_GET['year']) && in_array((int) $_GET['year'], $years, true)) ? (int) $_GET['year'] : (int) $regnaar;
		$GLOBALS['settings_rows_year'] = $year;
		break;
	}
}
if ($isRows && $year !== null && isset($_GET['year'])) {
	$selfUrl .= '&year=' . $year;
}
foreach ($tables as $tableId => $t) {
	if (is_array($t['filter'])) {
		$opts = settings_rows_filter_options((string) $t['filter']['options']);
		$param = (string) $t['filter']['param'];
		$want = isset($_GET[$param]) ? (int) $_GET[$param] : (isset($_POST[$param]) ? (int) $_POST[$param] : 0);
		$tables[$tableId]['filter']['options'] = $opts;
		$tables[$tableId]['filter']['value'] = isset($opts[$want]) ? $want : ($opts ? (int) key($opts) : 0);
		if ($opts) {
			$selfUrl .= '&' . $param . '=' . $tables[$tableId]['filter']['value'];
		}
	}
}
if ($isList && !empty($section['items_from'])) {
	$section['items'] = settings_list_dynamic_items((string) $section['items_from']);
}
if ($isList) {
	// An item tied to a feature that is not switched on in this installation is not shown at all (spec G2.7).
	foreach ($section['items'] as $itemId => $it) {
		if (!empty($it['feature']) && !settings_feature_enabled((string) $it['feature'])) {
			unset($section['items'][$itemId]);
		}
	}
}
$item = '';
if ($isList) {
	$item = isset($_POST['item']) ? (string) $_POST['item'] : (isset($_GET['item']) ? (string) $_GET['item'] : '');
	if (!isset($section['items'][$item])) {
		$item = '';
	}
}
$backUrl = $selfUrl . ($item !== '' ? '&item=' . rawurlencode($item) : '');

$state = array('errors' => array(), 'posted' => null, 'conflict' => null, 'flash' => array());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!isset($_POST['csrf_token']) || !hash_equals((string) $csrfToken, (string) $_POST['csrf_token'])) {
		audit_log('csrf', 'settingsSection.php?s=' . $sectionId);
		ob_end_clean();
		header('Location: ' . $selfUrl . '&err=csrf');
		exit;
	}
	require_permission($permission, 'write');
	$action = isset($_POST['action']) ? (string) $_POST['action'] : 'save';

	if ($action === 'revert') {
		$entry = SettingsService::historyEntry(isset($_POST['entry']) ? (int) $_POST['entry'] : 0);
		if ($entry && $entry['section'] === $sectionId && isset($defs[$entry['setting_key']]) && $defs[$entry['setting_key']]['type'] !== 'secret' && st_field_access($defs[$entry['setting_key']]) === 'write') {
			SettingsService::saveRaw($entry['setting_key'], (string) $entry['old_value'], null, 'setting.reverted');
		}
		ob_end_clean();
		header('Location: ' . $backUrl . '&reverted=1#' . ($entry ? rawurlencode((string) $entry['setting_key']) : ''));
		exit;
	}

	if ($action === 'run') {
		$key = isset($_POST['key']) ? (string) $_POST['key'] : '';
		$target = $backUrl;
		if (isset($defs[$key]) && !empty($defs[$key]['danger_zone']) && !settings_verify_password(isset($_POST['password']) ? (string) $_POST['password'] : '')) {
			audit_log('setting.action_refused', 'password', 'indstilling', $key);
			$_SESSION['settings_error'] = st_txt(6545);
			ob_end_clean();
			header('Location: ' . $backUrl);
			exit;
		}
		if (isset($defs[$key]) && $defs[$key]['type'] === 'action' && st_visible($defs[$key], array()) && st_field_access($defs[$key]) === 'write') {
			// Till and price-list row actions write their own entry with before/after.
			if (!in_array(isset($defs[$key]['run']) ? $defs[$key]['run'] : '', array('till_add', 'till_remove', 'pricelist_create', 'pricelist_delete', 'card_add', 'card_up', 'card_down', 'card_remove', 'pickup_add', 'pickup_delete', 'gdpr_delete_inactive', 'employee_add', 'employee_up', 'employee_down', 'employee_delete', 'ledger_reset', 'ledger_delete'), true)) {
				audit_log('setting.action', '', 'indstilling', $key);
			}
			$GLOBALS['settings_action_inputs'] = array();
			foreach (isset($defs[$key]['inputs']) ? array_keys($defs[$key]['inputs']) : array() as $in) {
				$GLOBALS['settings_action_inputs'][$in] = !empty($_POST['opt'][$in]);
			}
			$target = settings_run_action($defs[$key], $backUrl);
		}
		ob_end_clean();
		header('Location: ' . $target);
		exit;
	}

	if ($isRows && $action === 'row_action') {
		$tableId = isset($_POST['table']) ? (string) $_POST['table'] : '';
		$name = isset($_POST['value']) ? (string) $_POST['value'] : '';
		$target = $backUrl;
		if (isset($tables[$tableId]['row_actions'][$name])) {
			$t = $tables[$tableId];
			$rows = settings_rows_load($t, $year);
			$rid = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
			if (!empty($t['row_actions'][$name]['danger_zone']) && !settings_verify_password(isset($_POST['password']) ? (string) $_POST['password'] : '')) {
				audit_log('setting.action_refused', 'password', 'indstilling', $sectionId . '.' . $tableId . '.' . $name);
				$_SESSION['settings_error'] = st_txt(6545);
			} elseif (isset($rows[$rid])) {
				$GLOBALS['settings_row_action_arg'] = isset($_POST['arg']) ? trim((string) $_POST['arg']) : '';
				$res = settings_rows_row_action($sectionId, $tableId, $t, $rows[$rid], $name);
				$_SESSION['settings_flash'] = $res['flash'];
				if (!empty($res['reload_shell'])) {
					$_SESSION['settings_reload_shell'] = 1;
				}
			}
		}
		ob_end_clean();
		header('Location: ' . $target);
		exit;
	}

	if ($isRows && $action === 'row_create') {
		$tableId = isset($_POST['table']) ? (string) $_POST['table'] : '';
		$target = $backUrl . '#create';
		if (isset($tables[$tableId]) && $tables[$tableId]['create'] !== null) {
			$res = settings_rows_create_submit((string) $tables[$tableId]['create'], $_POST);
			$_SESSION['settings_flash'] = $res['flash'];
			if ($res['redirect'] !== '') {
				$target = $res['redirect'];
			}
		}
		ob_end_clean();
		header('Location: ' . $target);
		exit;
	}

	if ($isRows && in_array($action, array('row_delete', 'row_inactive', 'row_copy_year'), true)) {
		$tableId = isset($_POST['table']) ? (string) $_POST['table'] : '';
		if (isset($tables[$tableId])) {
			if ($action === 'row_delete') {
				$flash = settings_rows_delete($sectionId, $tableId, $tables[$tableId], (int) $_POST['id'], $year);
			} elseif ($action === 'row_inactive') {
				$to = !empty($_POST['value']);
				$flash = settings_rows_set_inactive($sectionId, $tableId, $tables[$tableId], (int) $_POST['id'], $to, $year);
				if ($flash[0] === 'ok') {
					// "Fortryd" in the flash flips it back (spec §8.12 soft delete with undo).
					$flash[] = array('table' => $tableId, 'id' => (int) $_POST['id'], 'value' => $to ? '0' : '1');
				}
			} else {
				$flash = settings_rows_copy_year($sectionId, $tableId, $tables[$tableId], (int) $_POST['from_year'], (int) $year);
			}
			$_SESSION['settings_flash'] = $flash;
		}
		ob_end_clean();
		header('Location: ' . $backUrl);
		exit;
	}

	// Rows are checked before anything is written, so a wrong cell leaves the fields unsaved too (spec §7.3).
	$rowsState = $isRows ? settings_rows_save($sectionId, $tables, $_POST, $year, true) : array('errors' => array(), 'posted' => array(), 'flash' => array(), 'confirm' => array());
	if (!$rowsState['errors'] && !empty($rowsState['confirm']) && empty($_POST['confirmed'])) {
		// Nothing is written until the amounts the save would post have been seen and confirmed.
		$state = array('errors' => array(), 'posted' => (isset($_POST['f']) && is_array($_POST['f'])) ? $_POST['f'] : array(), 'conflict' => null, 'flash' => array(), 'confirm' => $rowsState['confirm']);
	} elseif ($rowsState['errors']) {
		$state = settings_section_save($sectionId, $defs, $_POST + array('f' => array()));
		$state['errors'] = $rowsState['errors'];
	} else {
		$state = settings_section_save($sectionId, $defs, $_POST);
		if (!$state['errors'] && !$state['conflict'] && $isRows) {
			$rowsState = settings_rows_save($sectionId, $tables, $_POST, $year, false);
		}
	}
	$state['rows_posted'] = $rowsState['posted'];
	if (!$state['errors'] && !$state['conflict'] && empty($state['confirm'])) {
		ob_end_clean();
		header('Location: ' . $backUrl . '&saved=' . date('Hi'));
		exit;
	}
}

if (!empty($_SESSION['settings_reload_shell']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
	// A new active fiscal year: the shell draws the year in the top bar, so it reloads (with this page in its hash).
	unset($_SESSION['settings_reload_shell']);
	ob_end_clean();
	echo '<!doctype html><script>if (window.top !== window) { window.top.location.reload(); } else { window.location.reload(); }</script>';
	exit;
}
$settingsRowsContext = array('tables' => $tables, 'year' => $year, 'years' => $years);
settings_section_view($sectionId, $section, $defs, $state, $canWrite, $csrfToken, $selfUrl, $item);

// ---------------------------------------------------------------- controller

/**
 * Validate everything first; save only when nothing is wrong and nobody else saved in between.
 *
 * @param array<string, array<string, mixed>> $defs
 * @return array{errors: array<string, int>, posted: array<string, string>, conflict: array<string, mixed>|null, flash: array<int, mixed>}
 */
function settings_section_save(string $sectionId, array $defs, array $post): array
{
	$posted = array();
	$originals = array();
	foreach ((isset($post['f']) && is_array($post['f'])) ? $post['f'] : array() as $key => $value) {
		if (isset($defs[$key]) && is_string($value)) {
			$posted[$key] = $value;
		}
	}
	foreach ((isset($post['o']) && is_array($post['o'])) ? $post['o'] : array() as $key => $value) {
		if (isset($defs[$key]) && is_string($value)) {
			$originals[$key] = $value;
		}
	}

	// Only fields that were rendered, may be changed and are visible take part (P6, R17).
	$errors = array();
	$toSave = array();
	foreach ($defs as $key => $def) {
		if (in_array($def['type'], array('action', 'info', 'link', 'mini'), true) || !isset($posted[$key]) || st_locked($def) || !st_visible($def, $posted) || st_field_access($def) !== 'write') {
			continue;
		}
		if ($def['type'] === 'secret' && trim($posted[$key]) === '') {
			continue;
		}
		$res = st_posted_to_raw($def, $posted[$key], $posted);
		$unchanged = (st_current_form_value($def) === trim($posted[$key]));
		if ($res['error'] !== null) {
			// A value that was already stored and is left untouched is not this user's error.
			if (!$unchanged || (is_array($def['validate']) && $def['validate'][0] === 'requires')) {
				$errors[$key] = $res['error'];
			}
			continue;
		}
		if (!$unchanged) {
			$toSave[$key] = $res['raw'];
		}
	}

	$conflict = null;
	$version = isset($post['version']) ? (int) $post['version'] : 0;
	$currentVersion = SettingsService::version($sectionId);
	if ($currentVersion > $version) {
		$latest = SettingsService::history($sectionId, 1);
		$mine = array();
		foreach ($posted as $key => $value) {
			if (isset($originals[$key]) && trim($value) !== trim($originals[$key])) {
				$mine[$key] = $value;
			}
		}
		$conflict = array('by' => $latest ? (string) $latest[0]['brugernavn'] : '', 'at' => $latest ? (string) $latest[0]['tidspunkt'] : '', 'mine' => $mine);
	}

	if (!$errors && !$conflict && $toSave) {
		transaktion('begin');
		foreach ($toSave as $key => $raw) {
			SettingsService::saveRaw($key, $raw);
		}
		transaktion('commit');
		foreach ($toSave as $key => $raw) {
			if (!empty($defs[$key]['on_save'])) {
				settings_after_save($defs[$key], $raw);
			}
		}
		settings_after_section_save();
	}
	return array('errors' => $errors, 'posted' => $posted, 'conflict' => $conflict, 'flash' => array());
}

// ---------------------------------------------------------------- view

/**
 * @param array<string, mixed>                 $section
 * @param array<string, array<string, mixed>> $defs
 * @param array<string, mixed>                 $state
 */
function settings_section_view(string $sectionId, array $section, array $defs, array $state, bool $canWrite, string $csrfToken, string $selfUrl, string $item = ''): void
{
	global $sprog_id, $regnskab, $buttonColor, $buttonTxtColor;
	$sprogId = (int) $sprog_id;
	$charset = st_charset();
	$groups = getSettingsGroups();
	$group = $groups[$section['group']];
	$accent = !empty($buttonColor) ? $buttonColor : '#114691';
	$accentTxt = !empty($buttonTxtColor) ? $buttonTxtColor : '#ffffff';

	// Form values: stored values, or after a refused save what the user typed.
	$values = array();
	$originals = array();
	foreach ($defs as $key => $def) {
		if (in_array($def['type'], array('action', 'info', 'link', 'mini'), true)) {
			continue;
		}
		$stored = st_current_form_value($def);
		$values[$key] = $stored;
		$originals[$key] = $stored;
		if ($state['conflict']) {
			if (isset($state['conflict']['mine'][$key])) {
				$values[$key] = $state['conflict']['mine'][$key];
			}
		} elseif ($state['errors'] && isset($state['posted'][$key])) {
			$values[$key] = $state['posted'][$key];
		}
	}

	$flash = array();
	if (isset($_GET['err']) && $_GET['err'] === 'csrf') {
		$flash[] = array('err', st_txt(5754));
	}
	if ($state['errors']) {
		$flash[] = array('err', st_txt(5731));
	}
	if ($state['conflict']) {
		$when = $state['conflict']['at'] !== '' ? st_local_time($state['conflict']['at'], 'H:i') : '';
		$flash[] = array('warn', $state['conflict']['by'] . ' ' . st_txt(5728) . ' ' . $when . '. ' . st_txt(5744));
	}
	if (!empty($_GET['reverted'])) {
		$flash[] = array('ok', st_txt(5752));
	}
	if (isset($_GET['converted'])) {
		$flash[] = array('ok', sprintf(st_txt(6013), (int) $_GET['converted']));
	}
	if (!empty($_GET['newkey']) && !empty($_SESSION['settings_newkey'])) {
		// The new API key is shown this once and then forgotten (P8).
		$flash[] = array('key', $_SESSION['settings_newkey']);
		unset($_SESSION['settings_newkey']);
	}
	if (isset($_GET['ftp'])) {
		$flash[] = ($_GET['ftp'] === 'ok') ? array('ok', st_txt(6212)) : array('err', st_txt(6213));
	}
	if (isset($_GET['qr'])) {
		$flash[] = array('ok', sprintf(st_txt(6133), (int) $_GET['qr']));
	}
	if (isset($_GET['webhook'])) {
		$flash[] = ($_GET['webhook'] === 'ok') ? array('ok', st_txt(6131)) : array('err', st_txt(6132));
	}
	if (!empty($_SESSION['settings_flash']) && is_array($_SESSION['settings_flash'])) {
		$flash[] = $_SESSION['settings_flash'];
		unset($_SESSION['settings_flash']);
	}
	if (!empty($_SESSION['settings_error'])) {
		$flash[] = array('err', (string) $_SESSION['settings_error']);
		unset($_SESSION['settings_error']);
	}
	if (!$canWrite) {
		$flash[] = array('info', st_txt(5755));
	}
	if (isset($_GET['saved']) && preg_match('/^[0-9]{4}$/', (string) $_GET['saved'])) {
		$flash[] = array('ok', st_txt(5709) . ' ' . substr($_GET['saved'], 0, 2) . ':' . substr($_GET['saved'], 2));
	}
	$moved = isset($_GET['moved']) ? (string) $_GET['moved'] : '';
	$movedText = '';
	if ($moved !== '' && isset($section['old'][$moved])) {
		$movedText = st_txt(5720) . ' ' . settings_legacy_text($section['old'][$moved], $sprogId) . '. ' . st_txt(5721);
	}

	$tabs = settings_section_tabs($section['group'], $sprogId);
	$history = SettingsService::history($sectionId, 20);
	$setAt = SettingsService::lastChanged($sectionId);
	$version = SettingsService::version($sectionId);
	$config = array(
		'unsavedN' => st_txt(6043), 'unsaved1' => st_txt(6044),
		'copied' => st_txt(5718), 'notFound' => st_txt(5719), 'cancel' => st_txt(5), 'lookupUrl' => 'settingsLookup.php',
		'restoreTitle' => st_txt(5747), 'restoreBody' => st_txt(5748), 'restoreVerb' => st_txt(5711),
	);
	foreach ($config as $k => $v) {
		$config[$k] = mb_convert_encoding($v, 'UTF-8', $charset);
	}
	if (!empty($section['kind']) && $section['kind'] === 'rows') {
		global $settingsRowsContext;
		settings_rows_render(array(
			'sectionId' => $sectionId, 'section' => $section, 'defs' => $defs, 'state' => $state, 'canWrite' => $canWrite,
			'csrfToken' => $csrfToken, 'selfUrl' => $selfUrl, 'values' => $values, 'originals' => $originals, 'flash' => $flash,
			'movedText' => $movedText, 'tabs' => $tabs, 'version' => $version, 'config' => $config, 'accent' => $accent,
			'accentTxt' => $accentTxt, 'group' => $group, 'sprogId' => $sprogId, 'charset' => $charset,
			'tables' => $settingsRowsContext['tables'], 'year' => $settingsRowsContext['year'], 'years' => $settingsRowsContext['years'],
			'rowsPosted' => isset($state['rows_posted']) ? $state['rows_posted'] : array(),
			'confirm' => isset($state['confirm']) ? $state['confirm'] : array(),
		));
		return;
	}
	if (!empty($section['kind']) && $section['kind'] === 'list') {
		settings_list_render(array(
			'sectionId' => $sectionId, 'section' => $section, 'defs' => $defs, 'state' => $state, 'canWrite' => $canWrite,
			'csrfToken' => $csrfToken, 'selfUrl' => $selfUrl, 'values' => $values, 'originals' => $originals, 'flash' => $flash,
			'movedText' => $movedText, 'tabs' => $tabs, 'version' => $version, 'config' => $config, 'accent' => $accent,
			'accentTxt' => $accentTxt, 'group' => $group, 'sprogId' => $sprogId, 'charset' => $charset, 'item' => $item,
		));
		return;
	}
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= json_encode(mb_convert_encoding(st_txt($section['label']), 'UTF-8', $charset)) ?>;</script>
<?= settings_breadcrumb_script(settings_breadcrumb((string) $section['group'], st_txt($section['label']), $sprogId), $charset, isset($section['return_to']) ? (string) $section['return_to'] : '', isset($section['return_label']) ? (string) $section['return_label'] : '') ?>
<div class="st-page" style="<?= st_h(st_accent_style((string) $accent, (string) $accentTxt)) ?>">
  <a class="st-skip" href="#st-form"><?= st_t(5751) ?></a>
  <section class="st-phead">
    <div>
      <h1><?= st_t($section['label']) ?><?= st_section_help($section) ?></h1>
      <p class="st-lead"><?= st_t(5706) ?> <?= st_h(st_company()) ?>.</p>
    </div>
  </section>

	<?php if ($movedText !== '' || $flash) { ?>
  <div class="st-notes">
		<?php if ($movedText !== '') { ?>
    <div class="st-toast" role="status"><i class="st-dotw st-dot-acc" aria-hidden="true"></i><span><?= st_h($movedText) ?> <a href="settingsMoved.php"><?= st_t(5722) ?></a></span><button type="button" class="st-toast-close" aria-label="<?= st_t(2172) ?>" data-dismiss><i class='bx bx-x'></i></button></div>
		<?php } ?>
		<?php foreach ($flash as $f) { ?>
		<?php if ($f[0] === 'key') { ?>
    <div class="st-flash st-flash-ok st-flash-key" role="status"><i class='bx bx-key' aria-hidden="true"></i><span><b><?= st_t(6129) ?>:</b> <code><?= st_h($f[1]) ?></code><br><?= st_t(6130) ?></span></div>
		<?php continue; } ?>
    <div class="st-flash st-flash-<?= $f[0] ?>" role="<?= $f[0] === 'err' ? 'alert' : 'status' ?>"><?php if ($f[0] === 'info') { ?><i class='bx bx-lock-alt' aria-hidden="true"></i><?php } else { ?><i class="st-dotw st-dot-<?= $f[0] ?>" aria-hidden="true"></i><?php } ?><span><?= st_h($f[1]) ?></span></div>
		<?php } ?>
  </div>
	<?php } ?>

  <div class="st-layout">
    <nav class="st-tabs" aria-label="<?= st_h(html_entity_decode(findtekst($group['label'], $sprogId), ENT_QUOTES | ENT_HTML5, $charset)) ?>">
      <select class="st-tabs-select" aria-label="<?= st_h(html_entity_decode(findtekst($group['label'], $sprogId), ENT_QUOTES | ENT_HTML5, $charset)) ?>" onchange="if (this.value) { window.location = this.value; }">
			<?php foreach ($tabs as $tab) { ?>
        <option value="<?= st_h($tab['url']) ?>"<?= $tab['id'] === $sectionId ? ' selected' : '' ?>><?= st_h($tab['label']) ?></option>
			<?php } ?>
      </select>
      <ul>
			<?php foreach ($tabs as $tab) { ?>
        <li><a href="<?= st_h($tab['url']) ?>"<?= $tab['id'] === $sectionId ? ' class="on" aria-current="page"' : '' ?>><span><?= st_h($tab['label']) ?></span><?= $tab['id'] === $sectionId ? '<i class="st-dot" id="st-dot" hidden></i>' : '' ?></a></li>
			<?php } ?>
      </ul>
    </nav>

    <form class="st-form" id="st-form" method="post" action="<?= st_h($selfUrl) ?>" autocomplete="off" novalidate>
      <input type="hidden" name="csrf_token" value="<?= st_h($csrfToken) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="version" value="<?= (int) $version ?>">

		<?php if ($state['errors']) { ?>
      <ul class="st-errorlist" aria-label="<?= st_t(5731) ?>">
			<?php foreach ($state['errors'] as $key => $textId) { ?>
        <li><a href="#<?= st_h($key) ?>"><?= st_t($defs[$key]['label']) ?>: <?= st_t($textId) ?></a></li>
			<?php } ?>
      </ul>
		<?php } ?>

		<?php
		$actions = array();
		$danger = array();
		foreach ($section['subsections'] as $sub => $subLabel) {
			$fields = array();
			foreach ($defs as $key => $def) {
				if ($def['sub'] !== $sub) {
					continue;
				}
				if ($def['type'] === 'action') {
					if (!empty($def['danger'])) {
						$danger[$key] = $def;
					} else {
						$actions[$key] = $def;
					}
					continue;
				}
				if ($def['visible_if'] && $def['visible_if'][0] === 'module' && !st_visible($def, $values)) {
					continue;
				}
				$fields[$key] = $def;
			}
			if (!$fields) {
				continue;
			}
			// A sub-section whose fields are all hidden (dependents of a switch that is off) is hidden with them.
			$anyVisible = false;
			foreach ($fields as $key => $def) {
				if (st_visible($def, $values)) {
					$anyVisible = true;
					break;
				}
			}
			?>
      <section class="st-sect" id="sub-<?= st_h($sub) ?>"<?= $anyVisible ? '' : ' hidden' ?>>
        <h2><?= st_t($subLabel) ?></h2>
			<?php if (isset($section['sub_help'][$sub])) { ?>
        <p><?= st_t($section['sub_help'][$sub]) ?></p>
			<?php } ?>
        <div class="st-card">
			<?php $lastGroup = null; foreach ($fields as $key => $def) {
				// A run of fields can carry a heading ("Rykker 1"), repeated per language when the fields are.
				$groupHeading = '';
				if (isset($def['group_label'])) {
					$g = sprintf(st_txt($def['group_label'][0]), $def['group_label'][1]) . (isset($def['label_suffix']) ? ' · ' . $def['label_suffix'] : '');
					if ($g !== $lastGroup) {
						$groupHeading = $g;
						$lastGroup = $g;
					}
				}
				st_render_field($def, array(
					'group_heading' => $groupHeading,
					'in_group'      => isset($def['group_label']),
					'set_at'        => isset($setAt[$key]) ? $setAt[$key] : '',
					'value'    => isset($values[$key]) ? $values[$key] : '',
					'original' => isset($originals[$key]) ? $originals[$key] : '',
					'error'    => isset($state['errors'][$key]) ? $state['errors'][$key] : null,
					'readonly' => !$canWrite,
					'locked'   => st_locked($def),
					'visible'  => st_visible($def, $values),
					'mine'     => ($state['conflict'] && isset($state['conflict']['mine'][$key])),
				));
			} ?>
        </div>
      </section>
		<?php } ?>

		<?php if ($actions && $canWrite) { ?>
      <section class="st-sect" id="sub-actions">
        <h2><?= st_t(3285) ?></h2>
        <p><?= st_t(6047) ?></p>
        <div class="st-card">
			<?php foreach ($actions as $def) {
				st_render_action($def, $canWrite, st_visible($def, $values));
			} ?>
        </div>
      </section>
		<?php } ?>
		<?php if ($danger && $canWrite) { ?>
      <section class="st-sect st-sect-danger" id="sub-danger">
        <h2><?= st_t(6048) ?></h2>
        <div class="st-card st-card-danger">
			<?php foreach ($danger as $def) {
				st_render_action($def, $canWrite, st_visible($def, $values));
			} ?>
        </div>
      </section>
		<?php } ?>

		<?php if ($canWrite) { ?>
      <footer class="st-savebar" id="st-savebar" hidden>
        <div class="st-savebar-in">
          <span class="st-status"><i class="st-dotw st-dot-warn" aria-hidden="true"></i><span id="st-status" role="status"></span></span>
          <span class="st-savebar-btns">
            <kbd aria-hidden="true">Ctrl S</kbd>
            <button type="button" class="st-btn st-btn-quiet" id="st-undo" disabled><?= st_t(159) ?></button>
            <button type="submit" class="st-btn st-btn-primary" id="st-save"><?= st_t(6045) ?></button>
          </span>
        </div>
      </footer>
		<?php } ?>
    </form>

    <aside class="st-hist" aria-labelledby="st-hist-title">
      <h2 id="st-hist-title"><?= st_t(5710) ?></h2>
      <div class="st-card">
			<?php
			$shown = 0;
			foreach ($history as $row) {
				$key = (string) $row['setting_key'];
				if (!isset($defs[$key])) {
					continue;
				}
				$shown++;
				$def = $defs[$key];
				$secret = ($def['type'] === 'secret' || st_field_access($def) === 'none');
				$oldText = st_display_value($def, (string) $row['old_value']);
				?>
        <div class="st-h">
          <a class="st-h-field" href="#<?= st_h($key) ?>"><?= st_t($def['label']) ?></a>
          <span class="st-h-ch"><?php if ($secret) { ?><?= st_t(5713) ?><?php } else { ?><s><?= st_h($oldText) ?></s> → <?= st_h(st_display_value($def, (string) $row['new_value'])) ?><?php } ?></span>
          <span class="st-h-m"><span><?= st_h($row['brugernavn']) ?> · <?= st_h(st_local_time((string) $row['tidspunkt'], 'j/n H:i')) ?></span><?php if (!$secret && $canWrite && st_field_access($def) === 'write' && !st_locked($def)) { ?><button type="button" class="st-tl" data-restore="<?= (int) $row['id'] ?>" data-value="<?= st_h($oldText) ?>"><?= st_t(5711) ?></button><?php } ?></span>
        </div>
			<?php } ?>
			<?php if (!$shown) { ?>
        <p class="st-h-empty"><?= st_t(5712) ?></p>
			<?php } ?>
			<?php if (function_exists('perm_can') && perm_can('settings.audit.read', 'read')) { ?>
        <div class="st-h-foot"><a class="st-tl" href="usersRoles.php?tab=log"><?= st_t(6046) ?></a></div>
			<?php } ?>
      </div>
    </aside>
  </div>

  <div class="st-backdrop" id="st-backdrop" hidden></div>
  <div class="st-dialog" id="st-dialog" role="dialog" aria-modal="true" aria-labelledby="st-dialog-title" hidden>
    <h3 id="st-dialog-title"></h3>
    <p id="st-dialog-body"></p>
    <form method="post" action="<?= st_h($selfUrl) ?>" id="st-dialog-form">
      <input type="hidden" name="csrf_token" value="<?= st_h($csrfToken) ?>">
      <input type="hidden" name="action" value="">
      <input type="hidden" name="key" value="">
      <input type="hidden" name="entry" value="">
      <div class="st-dialog-opts" id="st-dialog-opts" hidden></div>
      <label class="st-dialog-pw" id="st-dialog-pw" hidden><span><?= st_t(6546) ?></span><input class="st-input" type="password" name="password" autocomplete="current-password" aria-label="<?= st_t(6544) ?>"></label>
      <div class="st-dialog-btns">
        <button type="button" class="st-btn st-btn-quiet" id="st-dialog-cancel"><?= st_t(5) ?></button>
        <button type="submit" class="st-btn st-btn-primary" id="st-dialog-ok"></button>
      </div>
    </form>
  </div>
  <div class="st-snack" id="st-snack" role="status" hidden></div>
</div>
<script>window.SALDI_SETTINGS = <?= json_encode($config) ?>;</script>
<script src="../javascript/settingsSection.js?v=9"></script>
	<?php
}

/**
 * The sections of a group: generated ones first as registered, then the pages that have not
 * landed yet, so nothing of the group is out of reach from the tab list.
 *
 * @return array<int, array{id: string, label: string, url: string}>
 */
