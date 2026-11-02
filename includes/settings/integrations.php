<?php
// ---- includes/settings/integrations.php --- ver 5.1.0 --- 2026.11.02 ---
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
// 20261004 Sawaneh G9.2 pickup address items, G9.4 NemHandel status.
// 20261004 Sawaneh G10.3: card list items from the POS card lists, their status, the payment providers' state.
// 20261002 Sawaneh Settings redesign phase 4b batch 2 (G9, hand-over 2 Oct): what the Integrations list needs beyond
//                  the registry - the computed status of each integration (never stored, §8.13), the read-only info
//                  rows, the two small login forms, a new API key, MobilePay QR codes per till (moved from the old
//                  page's render, spec B-D12) and the Vibrant terminal login created on the server (B-D10).
// 20261101 CL/ASR Ansatte lists one more item, Ekstra felter ('general'), with the field that moved there from Diverse valg.
// 20261102 CL/ASR Personer (owner, 2 Nov 2026: one place for people, the employee list as the overview): for a user with read on
//                 settings.users.manage each employee row shows the login's role and status (or "Ingen login"), and the logins without an
//                 employee of the company ("Kun login") or a second login of an employee ("Ekstra login") are rows of their own that open the
//                 login in usersRoles.php. The card's "Login og rolle" lists the employee's logins with a link on to their role settings, or,
//                 with write, invites or creates one (usersRoles.php's own drawer, which saves and checks as before; ret= brings the user back).
//                 Without read on the logins nothing about them is shown, and the old link to usersRoles.php (which would refuse) is gone.
// 20261102 CL/ASR Review: "Login og rolle" is laid out as the card's other lists (st-info-list: the login left, its buttons right), and
//                 "Se log" carries ret= too, so Tilbage on the log returns to the card.

/**
 * Status of one integration from what is stored: kind 'ok', 'off', 'err' or 'soon', the short text and the
 * button label. Nothing is called externally here (P5).
 *
 * @return array{kind: string, text: string, button: int}
 */
function settings_integration_status(string $item, array $def, array $setAt = array()): array
{
	if (!empty($def['soon'])) {
		return array('kind' => 'soon', 'text' => st_txt(6140), 'button' => 0);
	}
	if (strpos($item, 'till_') === 0) {
		return array('kind' => 'plain', 'text' => (string) $def['status_text'], 'button' => 6122);
	}
	if ($item === 'general') {
		return array('kind' => 'plain', 'text' => '', 'button' => 6122);
	}
	if (strpos($item, 'pickup_') === 0) {
		return array('kind' => 'plain', 'text' => (string) $def['status_text'], 'button' => 6122);
	}
	if (strpos($item, 'card_') === 0) {
		return !empty($def['active']) ? array('kind' => 'ok', 'text' => st_txt(860), 'button' => 6122) : array('kind' => 'off', 'text' => st_txt(6360), 'button' => 6122);
	}
	if (strpos($item, 'emp_') === 0 || strpos($item, 'usr_') === 0) {
		// Personer: the login's role and status when the list set them ('status_kind'), otherwise as before (Fratrådt or nothing).
		if (isset($def['status_kind'])) {
			return array('kind' => (string) $def['status_kind'], 'text' => isset($def['status_text']) ? (string) $def['status_text'] : '', 'button' => 6122);
		}
		return !empty($def['active']) ? array('kind' => 'plain', 'text' => '', 'button' => 6122) : array('kind' => 'off', 'text' => st_txt(660), 'button' => 6122);
	}
	if (strpos($item, 'pl_') === 0) {
		return !empty($def['active']) ? array('kind' => 'ok', 'text' => st_txt(6233), 'button' => 6122) : array('kind' => 'off', 'text' => st_txt(6234), 'button' => 6122);
	}
	$set = function (string $key): bool {
		return trim(SettingsService::raw($key)) !== '';
	};
	$on = false;
	$text = '';
	switch ($item) {
		case 'rest_api':
			$on = $set('integrations.rest_api.key');
			$when = isset($setAt['integrations.rest_api.key']) ? ' ' . st_local_time($setAt['integrations.rest_api.key'], 'j/n') : '';
			$text = $on ? st_txt(6120) . ' · ' . st_txt(6127) . $when : '';
			break;
		case 'webshop':
			$on = $set('integrations.webshop.url');
			break;
		case 'quickpay':
			$on = $set('integrations.quickpay.agreement_id') && $set('integrations.quickpay.merchant');
			break;
		case 'gls':
			$on = $set('integrations.gls.user');
			break;
		case 'dfm':
			$on = $set('integrations.dfm.user');
			break;
		case 'easyubl':
			$on = $set('integrations.easyubl.api_key');
			break;
		case 'app':
			$on = $set('integrations.app.api_key');
			break;
		case 'mobilepay':
			$on = $set('integrations.mobilepay.client_id');
			if ($on && !settings_mobilepay_webhook_secret()) {
				return array('kind' => 'err', 'text' => st_txt(6102), 'button' => 6124);
			}
			$text = $on ? st_txt(6103) : '';
			break;
		case 'flatpay':
			$on = settings_setting_value('flatpay_auth', 'globals') !== '';
			break;
		case 'vibrant':
			$on = $set('integrations.vibrant.api_key');
			break;
	}
	if ($on) {
		return array('kind' => 'ok', 'text' => $text !== '' ? $text : st_txt(6120), 'button' => 6122);
	}
	return array('kind' => 'off', 'text' => st_txt(6121), 'button' => 6123);
}

/**
 * The rows of a list section whose items come from the database ('items_from').
 *
 * @return array<string, array<string, mixed>>
 */
function settings_list_dynamic_items(string $from): array
{
	$items = array();
	if ($from === 'tills') {
		$tills = settings_till_count();
		$departments = st_options(array('options_from' => 'departments'));
		for ($n = 1; $n <= $tills; $n++) {
			$department = SettingsService::raw('pos.tills.department.' . $n);
			$terminal = SettingsService::raw('pos.tills.terminal_type.' . $n);
			$items['till_' . $n] = array(
				'sub' => 'tills', 'abbr' => (string) $n, 'literal' => true,
				'label' => sprintf(st_txt(6278), $n) . (isset($departments[$department]) && $departments[$department] !== '' ? ' · ' . $departments[$department] : ''),
				'desc' => SettingsService::raw('pos.tills.cash_account.' . $n) !== '' ? st_txt(6279) . ' ' . SettingsService::raw('pos.tills.cash_account.' . $n) : '',
				'status_text' => $terminal,
			);
		}
	}
	if ($from === 'cards') {
		$items['general'] = array('sub' => 'general', 'abbr' => '*', 'literal' => true, 'label' => st_txt(6324), 'desc' => st_txt(6339));
		foreach (settings_card_rows() as $i => $row) {
			$n = $i + 1;
			$items['card_' . $n] = array(
				'sub' => 'cards', 'abbr' => (string) $n, 'literal' => true,
				'label' => trim($row['name']) !== '' ? trim($row['name']) : '—',
				'desc' => trim($row['account']) !== '' ? st_txt(284) . ' ' . trim($row['account']) . (trim($row['voucher']) !== '' ? ' · ' . st_txt(2272) : '') : (trim($row['voucher']) !== '' ? st_txt(2272) : ''),
				'active' => trim($row['enabled']) !== '',
			);
		}
	}
	if ($from === 'pickups') {
		$q = db_select("select group_id, var_name, var_value from settings where var_grp = 'DFM_Pickup' order by group_id", __FILE__ . " linje " . __LINE__);
		$rows = array();
		while ($r = db_fetch_array($q)) {
			$rows[(int) $r['group_id']][str_replace('dfm_pickup_', '', (string) $r['var_name'])] = trim((string) $r['var_value']);
		}
		foreach ($rows as $gid => $a) {
			$a += array('name1' => '', 'buttonname' => '', 'street1' => '', 'zipcode' => '', 'town' => '', 'dfm_user' => '');
			$items['pickup_' . $gid] = array(
				'sub' => 'addresses', 'abbr' => (string) $gid, 'literal' => true,
				'label' => $a['name1'] !== '' ? $a['name1'] : ($a['buttonname'] !== '' ? $a['buttonname'] : '—'),
				'desc' => trim($a['street1'] . ($a['street1'] !== '' && ($a['zipcode'] . $a['town']) !== '' ? ', ' : '') . trim($a['zipcode'] . ' ' . $a['town'])),
				'status_text' => $a['dfm_user'] !== '' ? st_txt(6387) : '',
			);
		}
	}
	if ($from === 'employees') {
		// Diverse valg's last field (settings hub, 1 Nov 2026): one row under Ekstra felter, its drawer holds the switch.
		$items['general'] = array('sub' => 'general', 'abbr' => '+', 'literal' => true, 'label' => st_txt(616), 'desc' => st_txt(615));
		$departments = st_options(array('options_from' => 'departments'));
		$sid = settings_company_account_id();
		// Personer (owner, 2 Nov 2026): who can log in as what. Only for a user with read on the logins; otherwise the rows are as before.
		$canUsers = !function_exists('perm_can') || perm_can('settings.users.manage', 'read');
		$usersWrite = !function_exists('perm_can') || perm_can('settings.users.manage', 'write');
		$logins = $canUsers ? settings_people_logins() : array();
		$byEmployee = array();
		foreach ($logins as $uid => $l) {
			if ($l['ansat_id'] > 0) {
				$byEmployee[$l['ansat_id']][$uid] = $l;
			}
		}
		$loginText = function (array $l): string {
			return $l['role'] . ' · ' . $l['state_text'];
		};
		$loginItem = function (array $l, string $label, bool $extra) use ($loginText, $usersWrite): array {
			$desc = array($extra ? st_txt('7756|Ekstra login') : st_txt('7755|Kun login'));
			foreach (array($label !== $l['brugernavn'] ? $l['brugernavn'] : '', $l['email'], $l['revisor'] ? st_txt(2562) : '') as $part) {
				if ($part !== '') {
					$desc[] = $part;
				}
			}
			return array(
				'sub' => 'employees', 'abbr' => '–', 'literal' => true, 'label' => $label !== '' ? $label : '—', 'desc' => implode(' · ', $desc),
				'active' => $l['state'] !== 'closed', 'status_kind' => $l['state'] === 'closed' ? 'off' : 'plain', 'status_text' => $loginText($l),
				'href' => 'usersRoles.php?tab=users&bruger=' . (int) $l['id'] . '&ret=people', 'href_write' => $usersWrite,
			);
		};
		$shown = array();
		$q = db_select("select id, nummer, navn, initialer, email, tlf, mobil, afd, lukket from ansatte where konto_id = $sid order by coalesce(posnr, 999999), nummer, id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$desc = array();
			foreach (array('initialer', 'email', 'mobil', 'tlf') as $c) {
				if (trim((string) $r[$c]) !== '') {
					$desc[] = trim((string) $r[$c]);
				}
			}
			if ((int) $r['afd'] && isset($departments[(string) (int) $r['afd']]) && $departments[(string) (int) $r['afd']] !== '') {
				$desc[] = $departments[(string) (int) $r['afd']];
			}
			$left = trim((string) $r['lukket']) !== '';
			$eid = (int) $r['id'];
			$item = array(
				'sub' => 'employees', 'abbr' => trim((string) $r['nummer']) !== '' ? trim((string) $r['nummer']) : '–', 'literal' => true,
				'label' => trim((string) $r['navn']) !== '' ? trim((string) $r['navn']) : '—',
				'desc' => implode(' · ', array_slice($desc, 0, 3)),
				'active' => !$left,
				'status_text' => $left ? st_txt(660) : '',
			);
			$mine = array();
			if ($canUsers) {
				// The employee's first login (as settings_employee_rows()) is the row's; a further login gets a row of its own below it.
				$mine = isset($byEmployee[$eid]) ? $byEmployee[$eid] : array();
				ksort($mine);
				$primary = $mine ? reset($mine) : null;
				if ($left) {
					$item['status_kind'] = 'off';
					$item['status_text'] = st_txt(660) . ($primary ? ' · ' . $loginText($primary) : '');
				} elseif ($primary) {
					$item['status_kind'] = $primary['state'] === 'closed' ? 'off' : 'plain';
					$item['status_text'] = $loginText($primary);
				} else {
					$item['status_kind'] = 'off';
					$item['status_text'] = st_txt('7754|Ingen login');
				}
				if ($primary) {
					$shown[$primary['id']] = true;
					unset($mine[$primary['id']]);
				}
			}
			$items['emp_' . $eid] = $item;
			foreach ($mine as $uid => $l) {
				$shown[$uid] = true;
				$items['usr_' . $uid] = $loginItem($l, $item['label'], true);
			}
		}
		// Logins without an employee of the company (the administrator, revisor, bogholder, a login linked to a customer's contact person):
		// after the employees, by username.
		foreach ($logins as $uid => $l) {
			if (!isset($shown[$uid])) {
				$items['usr_' . $uid] = $loginItem($l, $l['brugernavn'], false);
			}
		}
	}
	if ($from === 'pricelists') {
		$q = db_select("select id, beskrivelse, box2, box12 from grupper where art = 'PL' order by beskrivelse, id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$items['pl_' . (int) $r['id']] = array(
				'sub' => 'lists', 'abbr' => 'CSV', 'literal' => true,
				'label' => trim((string) $r['beskrivelse']) !== '' ? trim((string) $r['beskrivelse']) : '—',
				'desc' => trim((string) $r['box2']),
				'active' => ($r['box12'] === 'Yes'),
			);
		}
	}
	return $items;
}

/**
 * The logins for Personer (owner, 2 Nov 2026), loaded once: id => id, brugernavn, email, ansat_id, role (its name, plain text), state
 * (active | invited | closed), state_text, twofactor, revisor. Empty without read on settings.users.manage. Read only: every change
 * to a login goes through usersRoles.php.
 *
 * @return array<int, array<string, mixed>>
 */
function settings_people_logins(): array
{
	static $out = null;
	if ($out !== null) {
		return $out;
	}
	$out = array();
	if (function_exists('perm_can') && !perm_can('settings.users.manage', 'read')) {
		return $out;
	}
	include_once(__DIR__ . '/../userFunctions.php'); // user_row_active(), user_row_invited()
	global $sprog_id;
	$roles = array();
	foreach (function_exists('perm_roles') ? perm_roles() : array() as $role) {
		// perm_role_name() is findtekst() text or the name as typed: decoded here, escaped once where it is printed.
		$roles[(int) $role['id']] = html_entity_decode(perm_role_name($role, (int) $sprog_id), ENT_QUOTES | ENT_HTML5, st_charset());
	}
	$revisor = 0;
	if ($r = db_fetch_array(db_select("select user_id from settings where var_name = 'revisor' and var_grp = 'system'", __FILE__ . " linje " . __LINE__))) {
		$revisor = (int) $r['user_id'];
	}
	// brugere.* as usersRoles.php reads it: older databases lack some of the newer columns (email, status, tmp_kode).
	$q = db_select("select * from brugere order by lower(brugernavn), id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$closed = !user_row_active($r);
		$invited = !$closed && user_row_invited($r);
		$roleId = isset($r['role_id']) ? (int) $r['role_id'] : 0;
		$out[(int) $r['id']] = array(
			'id' => (int) $r['id'],
			'brugernavn' => trim((string) $r['brugernavn']),
			'email' => isset($r['email']) ? trim((string) $r['email']) : '',
			'ansat_id' => (int) $r['ansat_id'],
			'role' => isset($roles[$roleId]) ? $roles[$roleId] : st_txt(5554),
			'state' => $closed ? 'closed' : ($invited ? 'invited' : 'active'),
			'state_text' => st_txt($closed ? 5559 : ($invited ? 5778 : 5558)),
			'twofactor' => isset($r['twofactor']) && in_array($r['twofactor'], array('t', true, '1', 1), true),
			'revisor' => ((int) $r['id'] === $revisor),
		);
	}
	return $out;
}

/**
 * A settings row that has no registry key of its own ('' when unset).
 */
function settings_setting_value(string $name, string $grp, int $posId = 0): string
{
	$qtxt = "select var_value from settings where var_name = '" . db_escape_string($name) . "' and var_grp = '" . db_escape_string($grp) . "'";
	if ($posId > 0) {
		$qtxt .= " and pos_id = $posId";
	}
	$r = db_fetch_array(db_select($qtxt . " order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	return $r ? trim((string) $r['var_value']) : '';
}

function settings_mobilepay_webhook_secret(): string
{
	return settings_setting_value('webhook_secret', 'mobilepay');
}

/**
 * The content of a read-only 'info' row, escaped HTML.
 */
function settings_integration_info(array $def): string
{
	global $db;
	switch ($def['info']) {
		case 'backup_latest':
			// G11.3: the copy admin/backup.php keeps for this ledger (temp/backup/<db>/, the latest only).
			global $db;
			$files = glob(__DIR__ . '/../../temp/backup/' . basename((string) $db) . '/*.sdat') ?: array();
			if (!$files) {
				return st_h(st_txt(6667));
			}
			usort($files, function ($a, $b) {
				return filemtime($b) - filemtime($a);
			});
			$f = $files[0];
			return st_h(date('d-m-Y H:i', filemtime($f)) . ' · ' . number_format(filesize($f) / 1048576, 1, ',', '.') . ' MB') . ' · <a class="st-tl" href="../temp/backup/' . rawurlencode(basename((string) $db)) . '/' . rawurlencode(basename($f)) . '" download>' . st_t(6668) . '</a>';
		case 'ledger_activity':
			$r = db_fetch_array(db_select("select count(id) as n from transaktioner where logdate >= '" . date('Y-m-d', strtotime('-1 year')) . "'", __FILE__ . " linje " . __LINE__));
			return st_h(sprintf(st_txt(6639), number_format($r ? (int) $r['n'] : 0, 0, ',', '.')));
		case 'employee_user':
			$rows = settings_employee_rows();
			$eid = (int) $def['scope_id'];
			if (function_exists('perm_can') && !perm_can('settings.users.manage', 'read')) {
				// No read on the logins: the name only, no link to usersRoles.php (it would refuse the user).
				if (empty($rows[$eid]['user'])) {
					return st_h(st_txt(6622));
				}
				$r = db_fetch_array(db_select("select brugernavn from brugere where id = " . (int) $rows[$eid]['user'], __FILE__ . " linje " . __LINE__));
				return st_h(sprintf(st_txt(6621), $r ? (string) $r['brugernavn'] : ''));
			}
			// Personer: every login of the employee with its role and status, and the way on to its role settings (usersRoles.php,
			// which saves and checks; ret= brings the user back to this card). data-guard: unsaved edits here are asked about first.
			$write = !function_exists('perm_can') || perm_can('settings.users.manage', 'write');
			$audit = !function_exists('perm_can') || perm_can('settings.audit.read', 'read');
			$lines = array();
			foreach (settings_people_logins() as $uid => $l) {
				if ($l['ansat_id'] !== $eid) {
					continue;
				}
				$parts = array($l['brugernavn'], $l['role'], $l['state_text']);
				if ($l['twofactor']) {
					$parts[] = st_txt('7763|2FA til');
				}
				if ($l['revisor']) {
					$parts[] = st_txt(2562);
				}
				$acts = '<a class="st-btn" data-guard href="usersRoles.php?tab=users&amp;bruger=' . (int) $uid . '&amp;ret=emp_' . $eid . '">'
					. st_t($write ? '7757|Rediger login og rolle' : '7758|Se login og rolle') . '</a>';
				if ($audit) {
					$acts .= ' <a class="st-tl" data-guard href="usersRoles.php?tab=log&amp;bruger=' . rawurlencode($l['brugernavn']) . '&amp;ret=emp_' . $eid . '">' . st_t('7762|Se log') . '</a>';
				}
				$lines[] = '<li><span>' . st_h(implode(' · ', $parts)) . '</span><span>' . $acts . '</span></li>';
			}
			if ($lines) {
				return '<ul class="st-info-list">' . implode('', $lines) . '</ul>';
			}
			$acts = '';
			if ($write && isset($rows[$eid]) && empty($rows[$eid]['left'])) {
				// Opens usersRoles.php's new-user drawer with this employee, its e-mail and a username filled in; nothing is sent before Send invitation.
				$new = 'usersRoles.php?tab=users&amp;bruger=0&amp;ret=emp_' . $eid;
				$acts = '<a class="st-btn" data-guard href="' . $new . '">' . st_t('7759|Inviter som bruger') . '</a> <a class="st-tl" data-guard href="' . $new . '&amp;mode=classic">' . st_t('7760|Opret login med adgangskode') . '</a>';
			}
			return '<ul class="st-info-list"><li><span>' . st_h(st_txt('7754|Ingen login')) . '</span>' . ($acts !== '' ? '<span>' . $acts . '</span>' : '') . '</li></ul>';
		case 'gdpr_inactive':
			$n = settings_gdpr_inactive();
			if (!$n['D'] && !$n['K']) {
				return st_h(st_txt(6543));
			}
			return st_h(sprintf(st_txt(6465), $n['D']) . ' · ' . sprintf(st_txt(6466), $n['K']));
		case 'nemhandel':
			// G9.4: the company is created in NemHandel the first time an invoice goes out digitally (debitor/api.php stores the companyID).
			return st_h(trim(SettingsService::raw('integrations.easyubl.company_id')) !== '' ? st_txt(6375) : st_txt(6376));
		case 'pos_providers':
			// G10.3 (decision 4): the payment providers' state, the keys themselves live under Integrations.
			$sections = getSettingsSections();
			$out = array();
			foreach (array('mobilepay', 'vibrant', 'flatpay', 'quickpay') as $provider) {
				if (isset($sections['integrations.connections']['items'][$provider])) {
					$it = $sections['integrations.connections']['items'][$provider];
					$status = settings_integration_status($provider, $it);
					$out[] = st_h(is_int($it['label']) ? st_txt($it['label']) : $it['label']) . ': ' . st_h($status['text']);
				}
			}
			return implode(' · ', $out);
		case 'saldi_db':
			return st_h($db);
		case 'saldi_url':
			$parts = explode('/', isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '', 3);
			$folder = isset($parts[1]) ? $parts[1] : '';
			return st_h((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . '/' . $folder . '/api');
		case 'document_mail':
			$address = 'bilag_' . $db . '@' . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
			return '<a href="mailto:' . st_h($address) . '">' . st_h($address) . '</a>';
		case 'mobilepay_qr':
			$codes = settings_mobilepay_qr_codes();
			if (!$codes) {
				return st_t(6158);
			}
			$out = '<ul class="st-info-list">';
			foreach ($codes as $till => $url) {
				$out .= '<li><span>' . sprintf(st_t(6159), (int) $till) . '</span>';
				$out .= ($url !== '') ? '<a href="' . st_h($url) . '" target="_blank" rel="noopener">' . st_t(6107) . ' <i class=\'bx bx-link-external\' aria-hidden="true"></i></a>' : '<span class="st-muted">' . st_t(6138) . '</span>';
				$out .= '</li>';
			}
			return $out . '</ul>';
	}
	return '';
}

/**
 * Till number => stored QR image url ('' for a till without one).
 *
 * @return array<int, string>
 */
function settings_mobilepay_qr_codes(): array
{
	global $regnaar;
	$r = db_fetch_array(db_select("select box1 from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '" . (int) $regnaar . "'", __FILE__ . " linje " . __LINE__));
	$tills = $r ? (int) $r['box1'] : 0;
	$out = array();
	for ($i = 1; $i <= $tills; $i++) {
		$out[$i] = settings_setting_value('qrkodeuri', 'mobilepay', $i);
	}
	return $out;
}

/**
 * The small forms that talk to a provider through our own server: the Flatpay login that is exchanged for
 * the Flatpay ID, and the Vibrant terminal login. Nothing typed here is part of the drawer's save.
 */
function settings_integration_mini(array $def, bool $disabled): void
{
	$id = 'm-' . str_replace('.', '-', $def['key']);
	if ($def['mini'] === 'flatpay') {
		$set = settings_setting_value('flatpay_auth', 'globals') !== '';
		?>
    <div class="st-mini" data-mini="flatpay" data-url="diverseIncludes/save_flatpay_id.php" data-done="<?= st_t(6134) ?>" data-fail="<?= st_t(6135) ?>">
		<?php if ($set) { ?>
      <span class="st-secret-state"><span class="st-secret-mask" aria-hidden="true">••••••••</span><span class="st-secret-when"><?= st_t(2314) ?> <?= st_t(6139) ?></span></span>
		<?php } ?>
		<?php if (!$disabled) { ?>
      <label class="st-mini-f"><span><?= st_t(225) ?></span><input class="st-input" type="text" id="<?= $id ?>-u" data-mini-field="username" autocomplete="off"></label>
      <label class="st-mini-f"><span><?= st_t(6115) ?></span><input class="st-input" type="password" id="<?= $id ?>-p" data-mini-field="password" autocomplete="new-password"></label>
      <button type="button" class="st-btn" data-mini-send><?= st_t(6116) ?></button>
		<?php } ?>
    </div>
		<?php
		return;
	}
	if ($def['mini'] === 'vibrant') {
		$r = db_fetch_array(db_select("select var_name from settings where var_grp = 'vibrant_account' order by id desc limit 1", __FILE__ . " linje " . __LINE__));
		$hasKey = (SettingsService::raw('integrations.vibrant.api_key') !== '');
		?>
    <div class="st-mini" data-mini="vibrant" data-url="diverseIncludes/create_vibrant_login.php" data-done="<?= st_t(6136) ?>" data-fail="<?= st_t(6156) ?>">
		<?php if ($r) { ?>
      <span class="st-secret-state"><span><?= st_h($r['var_name']) ?></span><span class="st-secret-when"><?= st_t(5677) ?></span></span>
		<?php } elseif (!$hasKey) { ?>
      <span class="st-info"><?= st_t(6152) ?></span>
		<?php } elseif (!$disabled) { ?>
      <label class="st-mini-f"><span><?= st_t(6118) ?></span><input class="st-input" type="text" id="<?= $id ?>-n" data-mini-field="name" autocomplete="off"></label>
      <label class="st-mini-f"><span><?= st_t(6119) ?></span><input class="st-input" type="email" id="<?= $id ?>-e" data-mini-field="email" autocomplete="off"></label>
      <label class="st-mini-f"><span><?= st_t(6115) ?></span><input class="st-input" type="password" id="<?= $id ?>-p" data-mini-field="passwd" autocomplete="new-password"></label>
      <button type="button" class="st-btn" data-mini-send><?= st_t(2324) ?></button>
		<?php } ?>
    </div>
		<?php
	}
}

/**
 * A new REST API key (spec G9.1: random_bytes, never made on render). Stored through the registry, so the
 * change is audited as a secret, and named as the roles spec's integration.api_key_changed event.
 */
function settings_generate_api_key(): string
{
	$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$key = '';
	$bytes = random_bytes(36);
	for ($i = 0; $i < 36; $i++) {
		$key .= $chars[ord($bytes[$i]) % strlen($chars)];
	}
	SettingsService::saveRaw('integrations.rest_api.key', $key);
	if (function_exists('audit_log')) {
		audit_log('integration.api_key_changed', 'integrations.rest_api.key');
	}
	return $key;
}

/**
 * The Vipps/MobilePay request headers the old page and the webhook script send.
 *
 * @return array<int, string>
 */
function settings_mobilepay_headers(array $cfg, string $token = ''): array
{
	global $version, $db;
	$h = array(
		'Content-Type: application/json',
		"Client_id: $cfg[client_id]",
		"Client_secret: $cfg[client_secret]",
		"Ocp-Apim-Subscription-Key: $cfg[subscriptionKey]",
		"Merchant-Serial-Number: $cfg[MSN]",
		'Vipps-System-Name: Saldi',
		"Vipps-System-Version: $version",
		"Vipps-System-Plugin-Name: Saldi $db",
		"Vipps-System-Plugin-Version: $version",
	);
	if ($token !== '') {
		$h[] = "Authorization: Bearer $token";
	}
	return $h;
}

/**
 * @return array<string, string> client_id, client_secret, subscriptionKey, MSN
 */
function settings_mobilepay_config(): array
{
	$cfg = array('client_id' => '', 'client_secret' => '', 'subscriptionKey' => '', 'MSN' => '');
	$q = db_select("select var_name, var_value from settings where var_grp = 'mobilepay' and var_name in ('client_id', 'client_secret', 'subscriptionKey', 'MSN')", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$cfg[$r['var_name']] = trim((string) $r['var_value']);
	}
	return $cfg;
}

/**
 * @return array{status: int, body: string, error: string}
 */
function settings_curl(string $url, array $headers, ?string $body, string $method = 'POST'): array
{
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_CUSTOMREQUEST  => $method,
		CURLOPT_HTTPHEADER     => $headers,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 20,
	));
	if ($body !== null) {
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	}
	$out = curl_exec($ch);
	$res = array('status' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'body' => $out === false ? '' : (string) $out, 'error' => $out === false ? (string) curl_error($ch) : '');
	curl_close($ch);
	return $res;
}

function settings_mobilepay_token(array $cfg): string
{
	$headers = settings_mobilepay_headers($cfg);
	$headers[] = 'Content-Length: 0';
	$res = settings_curl('https://api.vipps.no/accesstoken/get', $headers, '');
	$data = json_decode($res['body'], true);
	return (is_array($data) && isset($data['access_token'])) ? (string) $data['access_token'] : '';
}

/**
 * One QR code per till that has none yet (moved from systemdata/sys_div_func.php, where it ran on every render).
 *
 * @return array{created: int, error: string}
 */
function settings_mobilepay_create_qr(): array
{
	$cfg = settings_mobilepay_config();
	if ($cfg['client_id'] === '') {
		return array('created' => 0, 'error' => 'client_id');
	}
	$token = settings_mobilepay_token($cfg);
	if ($token === '') {
		return array('created' => 0, 'error' => 'accesstoken');
	}
	$created = 0;
	foreach (settings_mobilepay_qr_codes() as $till => $url) {
		if ($url !== '') {
			continue;
		}
		$endpoint = 'https://api.vipps.no/qr/v1/merchant-callback/kasse' . $till;
		$res = settings_curl($endpoint, settings_mobilepay_headers($cfg, $token), json_encode(array('locationDescription' => "Kasse $till", 'Qrimageformat' => 'SVG')), 'PUT');
		if ($res['status'] !== 200 && $res['status'] !== 201) {
			return array('created' => $created, 'error' => 'PUT ' . $res['status'] . ' ' . $res['error']);
		}
		$headers = settings_mobilepay_headers($cfg, $token);
		$headers[] = 'Accept: text/targetUrl';
		$res = settings_curl($endpoint, $headers, null, 'GET');
		$data = json_decode($res['body'], true);
		if ($res['status'] !== 200 || !is_array($data) || empty($data['qrImageUrl'])) {
			return array('created' => $created, 'error' => 'GET ' . $res['status'] . ' ' . $res['error']);
		}
		$qtxt = "insert into settings (var_name, var_grp, var_value, var_description, pos_id) values ('qrkodeuri', 'mobilepay', '" . db_escape_string((string) $data['qrImageUrl']) . "', 'A QR code URI to access the QR image on vipps server', $till)";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$created++;
	}
	return array('created' => $created, 'error' => '');
}

/**
 * Create the terminal user at Vibrant with the stored API key (was done in the browser, B-D10).
 *
 * @return array{ok: bool, message: string}
 */
function settings_vibrant_create_user(string $name, string $email, string $password): array
{
	$apiKey = SettingsService::raw('integrations.vibrant.api_key');
	if ($apiKey === '') {
		return array('ok' => false, 'message' => 'apikey');
	}
	$body = json_encode(array(
		'name'     => $name,
		'email'    => $email,
		'roleIds'  => array('ro_1xBHy6kquVWMne9caAaXps', 'ro_bzDKsUpAeFsFm8kUUXkXTy'),
		'password' => $password,
	));
	$res = settings_curl('https://pos.api.vibrant.app/pos/v1/users', array('Content-Type: application/json', 'apikey: ' . $apiKey), $body);
	if ($res['status'] >= 200 && $res['status'] < 300) {
		return array('ok' => true, 'message' => '');
	}
	$data = json_decode($res['body'], true);
	$message = is_array($data) ? trim((isset($data['error']) ? $data['error'] : '') . ' ' . (isset($data['message']) ? $data['message'] : '')) : '';
	return array('ok' => false, 'message' => $message !== '' ? $message : ($res['error'] !== '' ? $res['error'] : 'HTTP ' . $res['status']));
}
