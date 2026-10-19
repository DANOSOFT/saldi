<?php
// ---- includes/settings/actions.php --- lap 5.0.0 --- 2026.10.02 ---
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
// 20261005 Sawaneh settings_verify_password() for danger-zone actions; GDPR clean-up of inactive accounts (G1.5).
// 20261005 Sawaneh G1.1: company e-mail mirrored to the master database's regnskab row through db_modify(..., true).
// 20261004 Sawaneh §8.13 settings_impact_text(): the number of items an action touches.
// 20261004 Sawaneh G9.2: pickup addresses added and deleted here (the old page deleted every address missing from its form, B-D17).
// 20261004 Sawaneh G10 batch B: payment card rows (add, move, remove across the seven tab-joined lists), KDS colour
//                  compaction after the section is saved, printer cookies cleared as the old page did.
// 20261002 Sawaneh Settings redesign phase 4b: the actions a generated section can run (spec P4), each after a
//                  confirmation on the page. Called from systemdata/settingsSection.php.
// 20261004 Sawaneh G10.1: add a till, remove the last one.
// 20261004 Sawaneh G4.3: price lists - add, use (one active list), test the file (fetched only here, with a timeout), delete.
// 20261004 Sawaneh G2.6: FTP connection test for document storage.
// 20261002 Sawaneh Phase 4b batch 2 (G9): new API key, shop sync, MobilePay webhook and QR codes (includes/settings/integrations.php).

include_once(__DIR__ . '/integrations.php');

/**
 * Items created for mySale where the cost price was used as the commission percentage get a real
 * commission rate and cost price. Moved from systemdata/diverse.php (productOptions), without its sleep(10).
 *
 * @return int items changed
 */
function settings_convert_commission_items(): int
{
	$n = 0;
	$qtxt = "select id, varenr, kostpris, retail_price, provision from varer where (varenr like 'kb%' or varenr like 'kn%') ";
	$qtxt .= "and ((retail_price > 0 and retail_price < 100) or (kostpris > 0 and kostpris < 1)) order by varenr";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['provision']) {
			continue;
		}
		$id = (int) $r['id'];
		if ($r['retail_price'] && $r['retail_price'] < 100) {
			$provision = afrund($r['retail_price'], 0) * 1;
			$kostpris = 1 - $provision / 100;
			$qtxt = "update varer set provision = '$provision', kostpris = '$kostpris' where id = $id";
		} elseif ($r['kostpris'] >= 0.5 && $r['kostpris'] < 1) {
			$provision = 100 - ($r['kostpris'] * 100);
			$qtxt = "update varer set provision = '$provision' where id = $id";
		} else {
			$provision = $r['kostpris'] * 100;
			$kostpris = 1 - $r['kostpris'];
			$qtxt = "update varer set provision = '$provision', kostpris = '$kostpris' where id = $id";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$n++;
	}
	return $n;
}

function settings_pricelist_name(int $id): string
{
	$r = db_fetch_array(db_select("select beskrivelse from grupper where art = 'PL' and id = $id", __FILE__ . " linje " . __LINE__));
	return $r ? trim((string) $r['beskrivelse']) : '';
}

/**
 * Fetch a price file and report its size and columns (spec G4.3: never on render, only on request, with a timeout).
 *
 * @return array{0: string, 1: string} flash kind and text
 */
function settings_pricelist_test(int $id): array
{
	$r = db_fetch_array(db_select("select box2, box10, box11 from grupper where art = 'PL' and id = $id", __FILE__ . " linje " . __LINE__));
	$url = $r ? trim((string) $r['box2']) : '';
	if ($url === '' || !preg_match('#^https?://#i', $url)) {
		return array('err', st_txt(6247));
	}
	$context = stream_context_create(array('http' => array('timeout' => 10), 'https' => array('timeout' => 10)));
	$data = @file_get_contents($url, false, $context, 0, 20 * 1024 * 1024);
	if ($data === false || trim($data) === '') {
		return array('err', st_txt(6247));
	}
	if ($r['box11'] !== 'utf-8' && !mb_check_encoding($data, 'UTF-8')) {
		$data = mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
	}
	$lines = preg_split('/\r\n|\n|\r/', trim($data));
	$delimiter = ((string) $r['box10'] !== '') ? (string) $r['box10'] : ';';
	$columns = array_map('trim', str_getcsv($lines[0], $delimiter));
	$shown = implode(', ', array_slice($columns, 0, 8)) . (count($columns) > 8 ? ' …' : '');
	return array('ok', sprintf(st_txt(6246), count($lines) - 1, $shown));
}

/**
 * G2.6: log in to the company's own FTP server with the stored details, create the two folders and write and
 * read a test file. Same steps as testftp() on the old Bilagshåndtering page, with PHP's ftp functions.
 */
function settings_ftp_test(): bool
{
	global $db;
	$r = db_fetch_array(db_select("select box1, box2, box3, box4, box5 from grupper where art = 'bilag' order by id limit 1", __FILE__ . " linje " . __LINE__));
	if (!$r || trim((string) $r['box1']) === '' || !function_exists('ftp_connect')) {
		return false;
	}
	$target = rtrim(trim((string) $r['box1']), '/');
	$parts = parse_url((strpos($target, '://') === false ? 'ftp://' : '') . $target);
	$host = isset($parts['host']) ? $parts['host'] : '';
	$port = isset($parts['port']) ? (int) $parts['port'] : 21;
	$path = isset($parts['path']) ? trim($parts['path'], '/') : '';
	$folderVouchers = trim((string) $r['box4']) !== '' ? trim((string) $r['box4']) : 'bilag';
	$folderDocuments = trim((string) $r['box5']) !== '' ? trim((string) $r['box5']) : 'dokumenter';
	$ok = false;
	$conn = $host !== '' ? @ftp_connect($host, $port, 10) : false;
	if ($conn && @ftp_login($conn, (string) $r['box2'], urldecode((string) $r['box3']))) {
		@ftp_pasv($conn, true);
		if ($path !== '') {
			@ftp_chdir($conn, $path);
		}
		@ftp_mkdir($conn, $folderVouchers);
		@ftp_mkdir($conn, $folderDocuments);
		if (@ftp_chdir($conn, $folderVouchers)) {
			$local = tempnam(sys_get_temp_dir(), 'saldi_ftp');
			file_put_contents($local, "testfil fra saldi $db\n");
			if (@ftp_put($conn, 'saldi_testfil.txt', $local, FTP_ASCII)) {
				$ok = @ftp_get($conn, $local, 'saldi_testfil.txt', FTP_ASCII);
				@ftp_delete($conn, 'saldi_testfil.txt');
			}
			@unlink($local);
		}
	}
	if ($conn) {
		ftp_close($conn);
	}
	return $ok;
}

/**
 * Run the action named in a definition. Returns the page to go to next, with its message.
 */
function settings_run_action(array $def, string $selfUrl): string
{
	switch ($def['run']) {
		case 'update_cost_prices':
			return '../includes/opdat_kostpriser.php?metode=' . (int) SettingsService::raw('items.stock.cost_method');
		case 'convert_commission_items':
			return $selfUrl . '&converted=' . settings_convert_commission_items();
		case 'generate_api_key':
			// Shown once on the next page view, then forgotten (P8): never in the address bar or the audit log.
			$_SESSION['settings_newkey'] = settings_generate_api_key();
			return $selfUrl . '&newkey=1';
		case 'shop_sync_new':
			return 'diverse.php?sektion=api_valg&varesync=1';
		case 'shop_sync_update':
			return 'diverse.php?sektion=api_valg&varesync=2';
		case 'mobilepay_webhook':
			// The action passed the section's token check; the setup page runs only with this one-time code (review 2, PR #803).
			$_SESSION['mobilepay_webhook_nonce'] = bin2hex(random_bytes(16));
			return 'sys_div_func_includes/setup_mobilepay_webhook.php?n=' . $_SESSION['mobilepay_webhook_nonce'];
		case 'ftp_test':
			return $selfUrl . '&ftp=' . (settings_ftp_test() ? 'ok' : 'fail');
		case 'till_add':
		case 'till_remove':
			// Every fiscal year's POS/1 row (risk review R2). The per-till lists keep their entries; removing the
			// last till only stops it, adding it again brings its setup back.
			$before = settings_till_count();
			$tills = $before + ($def['run'] === 'till_add' ? 1 : -1);
			if ($tills >= 0) {
				if (!db_fetch_array(db_select("select id from grupper where art = 'POS' and kodenr = '1'", __FILE__ . " linje " . __LINE__))) {
					global $regnaar;
					db_modify("insert into grupper (beskrivelse, kode, kodenr, art, box1, fiscal_year) values ('POS_valg', '', '1', 'POS', '$tills', " . (int) $regnaar . ")", __FILE__ . " linje " . __LINE__);
				} else {
					db_modify("update grupper set box1 = '$tills' where art = 'POS' and kodenr = '1'", __FILE__ . " linje " . __LINE__);
				}
				if (function_exists('audit_log')) {
					audit_log('setting.action', json_encode(array('before' => $before, 'after' => $tills)), 'indstilling', $def['key']);
				}
			}
			return 'settingsSection.php?s=pos.tills' . ($def['run'] === 'till_add' ? '&item=till_' . $tills : '');
		case 'pricelist_create':
			$name = db_escape_string(st_txt(6222));
			db_modify("insert into grupper (beskrivelse, kodenr, art, box2, box10, box11) values ('$name', '0', 'PL', '', ';', 'utf-8')", __FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select max(id) as id from grupper where art = 'PL'", __FILE__ . " linje " . __LINE__));
			if (function_exists('audit_log')) {
				audit_log('setting.row_created', json_encode(array('before' => null, 'after' => st_txt(6222)), JSON_UNESCAPED_UNICODE), 'indstilling', 'purchase.pricelists#' . (int) $r['id']);
			}
			return 'settingsSection.php?s=purchase.pricelists&item=pl_' . (int) $r['id'];
		case 'pricelist_use':
			$id = (int) $def['scope_id'];
			// Same writes as "Use" on the old page: this list active (and offered in the order lookup), no other list active.
			db_modify("update grupper set box12 = '' where art = 'PL' and id <> $id and box12 = 'Yes'", __FILE__ . " linje " . __LINE__);
			db_modify("update grupper set box12 = 'Yes', box4 = 'on' where art = 'PL' and id = $id", __FILE__ . " linje " . __LINE__);
			$_SESSION['settings_flash'] = array('ok', st_txt(6233) . ': ' . settings_pricelist_name($id));
			return $selfUrl;
		case 'pricelist_test':
			$_SESSION['settings_flash'] = settings_pricelist_test((int) $def['scope_id']);
			return $selfUrl;
		case 'pricelist_delete':
			$id = (int) $def['scope_id'];
			$name = settings_pricelist_name($id);
			db_modify("delete from grupper where art = 'PL' and id = $id", __FILE__ . " linje " . __LINE__);
			if (function_exists('audit_log')) {
				audit_log('setting.row_deleted', json_encode(array('before' => $name, 'after' => null), JSON_UNESCAPED_UNICODE), 'indstilling', 'purchase.pricelists#' . $id);
			}
			$_SESSION['settings_flash'] = array('ok', st_txt(6249) . ': ' . $name);
			return 'settingsSection.php?s=purchase.pricelists';
		case 'ledger_reset':
			// G1.4 danger zone: the reset plan of systemdata/resetAccount.php (personal settings are kept since 2026-10-05).
			global $db_type, $db;
			require_once(__DIR__ . '/../../systemdata/resetAccount.php');
			$in = isset($GLOBALS['settings_action_inputs']) ? $GLOBALS['settings_action_inputs'] : array();
			try {
				resetAccount(!empty($in['keep_accounts']), !empty($in['keep_items']), (string) $db_type, (string) $db);
				if (function_exists('audit_log')) {
					audit_log('setting.action', json_encode(array('keep_accounts' => !empty($in['keep_accounts']), 'keep_items' => !empty($in['keep_items']))), 'indstilling', $def['key']);
				}
				$_SESSION['settings_flash'] = array('ok', st_txt(6640));
				$_SESSION['settings_reload_shell'] = 1;
			} catch (Throwable $error) {
				$_SESSION['settings_flash'] = array('err', sprintf(st_txt(6641), $error->getMessage()));
			}
			return $selfUrl;
		case 'ledger_delete':
			// G1.4 danger zone: as the old page - the ledger is closed in the master database and every session of it ends.
			global $db, $db_id, $brugernavn;
			if (function_exists('audit_log')) {
				audit_log('setting.action', 'ledger closed', 'indstilling', $def['key']);
			}
			db_modify("update regnskab set lukket = 'on', logintekst = '" . db_escape_string('slettet af ' . $brugernavn . ' den ' . date('Ymd H.i')) . "' where id = '" . (int) $db_id . "'", __FILE__ . " linje " . __LINE__, true);
			db_modify("delete from online where db = '" . db_escape_string((string) $db) . "'", __FILE__ . " linje " . __LINE__, true);
			return '../index/index.php';
		case 'employee_add':
			$sid = settings_company_account_id();
			$r = db_fetch_array(db_select("select coalesce(max(nummer), 0) + 1 as n, coalesce(max(posnr), 0) + 1 as p from ansatte where konto_id = $sid", __FILE__ . " linje " . __LINE__));
			$name = db_escape_string(st_txt(6614));
			db_modify("insert into ansatte (konto_id, navn, nummer, posnr, startdate, slutdate, lukket) values ($sid, '$name', " . (int) $r['n'] . ", " . (int) $r['p'] . ", '" . date('Y-m-d') . "', '9999-12-31', '')", __FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select max(id) as id from ansatte where konto_id = $sid", __FILE__ . " linje " . __LINE__));
			$eid = (int) $r['id'];
			if (function_exists('audit_log')) {
				audit_log('setting.row_created', json_encode(array('before' => null, 'after' => st_txt(6614)), JSON_UNESCAPED_UNICODE), 'indstilling', 'organisation.employees#' . $eid);
			}
			return 'settingsSection.php?s=organisation.employees&item=emp_' . $eid;
		case 'employee_up':
		case 'employee_down':
			$rows = array_keys(settings_employee_rows());
			$eid = (int) $def['scope_id'];
			$pos = array_search($eid, $rows, true);
			$swap = $def['run'] === 'employee_up' ? $pos - 1 : $pos + 1;
			if ($pos !== false && isset($rows[$swap])) {
				$moved = $rows[$swap];
				$rows[$swap] = $eid;
				$rows[$pos] = $moved;
				// The list order is stored as posnr 1..n, which also repairs gaps and duplicates of the old Stamdata page.
				foreach ($rows as $i => $id) {
					db_modify("update ansatte set posnr = " . ($i + 1) . " where id = " . (int) $id, __FILE__ . " linje " . __LINE__);
				}
				if (function_exists('audit_log')) {
					audit_log('setting.action', $def['run'], 'indstilling', 'organisation.employees#' . $eid);
				}
			}
			return 'settingsSection.php?s=organisation.employees&item=emp_' . $eid;
		case 'employee_delete':
			$eid = (int) $def['scope_id'];
			$usage = settings_employee_usage($eid);
			$rows = settings_employee_rows();
			$name = isset($rows[$eid]) ? $rows[$eid]['name'] : (string) $eid;
			if ($usage !== '') {
				$_SESSION['settings_flash'] = array('err', sprintf(st_txt(6618), $name, $usage));
				return 'settingsSection.php?s=organisation.employees&item=emp_' . $eid;
			}
			transaktion('begin');
			db_modify("delete from grupper where art = 'ANSAT' and kodenr = '$eid'", __FILE__ . " linje " . __LINE__);
			db_modify("delete from ansatte where id = $eid and konto_id = " . settings_company_account_id(), __FILE__ . " linje " . __LINE__);
			transaktion('commit');
			if (function_exists('audit_log')) {
				audit_log('setting.row_deleted', json_encode(array('before' => $name, 'after' => null), JSON_UNESCAPED_UNICODE), 'indstilling', 'organisation.employees#' . $eid);
			}
			$_SESSION['settings_flash'] = array('ok', sprintf(st_txt(6430), $name));
			return 'settingsSection.php?s=organisation.employees';
		case 'pickup_add':
			$r = db_fetch_array(db_select("select coalesce(max(group_id), 0) + 1 as next_id from settings where var_grp = 'DFM_Pickup'", __FILE__ . " linje " . __LINE__));
			$gid = (int) $r['next_id'];
			$name = db_escape_string(st_txt(6382));
			db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id, group_id) values ('DFM_Pickup', 'dfm_pickup_addr', '1', 'integrations.pickup', 0, $gid), ('DFM_Pickup', 'dfm_pickup_name1', '$name', 'integrations.pickup.name1', 0, $gid)", __FILE__ . " linje " . __LINE__);
			if (function_exists('audit_log')) {
				audit_log('setting.row_created', json_encode(array('before' => null, 'after' => st_txt(6382)), JSON_UNESCAPED_UNICODE), 'indstilling', 'integrations.pickup#' . $gid);
			}
			$GLOBALS['settings_pickups_changed'] = true;
			return 'settingsSection.php?s=integrations.pickup&item=pickup_' . $gid;
		case 'pickup_delete':
			$gid = (int) $def['scope_id'];
			$rows = settings_pickup_rows();
			$name = isset($rows[$gid]) ? $rows[$gid] : (string) $gid;
			db_modify("delete from settings where var_grp = 'DFM_Pickup' and group_id = $gid", __FILE__ . " linje " . __LINE__);
			if (function_exists('audit_log')) {
				audit_log('setting.row_deleted', json_encode(array('before' => $name, 'after' => null), JSON_UNESCAPED_UNICODE), 'indstilling', 'integrations.pickup#' . $gid);
			}
			$_SESSION['settings_flash'] = array('ok', st_txt(6383) . ': ' . $name);
			return 'settingsSection.php?s=integrations.pickup';
		case 'gdpr_delete_inactive':
			$n = settings_gdpr_delete_inactive();
			if (function_exists('audit_log')) {
				audit_log('setting.action', json_encode(array('customers' => $n['D'], 'suppliers' => $n['K'], 'ids' => $n['ids'])), 'indstilling', $def['key']);
			}
			$_SESSION['settings_flash'] = array('ok', sprintf(st_txt(6542), $n['D'], $n['K']));
			return $selfUrl;
		case 'card_add':
		case 'card_up':
		case 'card_down':
		case 'card_remove':
			return settings_card_action($def['run'], (int) (isset($def['scope_id']) ? $def['scope_id'] : 0), $def['key']);
		case 'mobilepay_qr':
			$res = settings_mobilepay_create_qr();
			if ($res['error'] !== '') {
				$_SESSION['settings_error'] = $res['error'];
			}
			return $selfUrl . '&qr=' . (int) $res['created'];
	}
	return $selfUrl;
}

/**
 * The payment cards as rows: every tab-joined card list of the current fiscal year, one entry per card
 * (G10.3). Lists shorter than the names are padded, so the columns stay aligned.
 *
 * @return array<int, array<string, string>>
 */
function settings_card_rows(): array
{
	$lists = array(
		'name' => array('pos', 1, 'box5'), 'account' => array('pos', 1, 'box6'), 'terminal' => array('pos', 2, 'box5'),
		'voucher' => array('pos', 3, 'box4'), 'voucher_text' => array('pos', 3, 'box5'),
		'enabled' => array('settings', 'card_enabled'), 'voucher_item' => array('settings', 'voucherItems'),
	);
	$values = array();
	foreach ($lists as $col => $src) {
		if ($src[0] === 'pos') {
			$r = settings_pos_row($src[1]);
			$values[$col] = $r ? (string) $r[$src[2]] : '';
		} else {
			// Read by name alone, as the till does (includes/posmenufunc.php).
			$r = db_fetch_array(db_select("select var_value from settings where var_name = '" . $src[1] . "' order by id desc limit 1", __FILE__ . " linje " . __LINE__));
			$values[$col] = $r ? (string) $r['var_value'] : '';
		}
	}
	$n = trim($values['name']) !== '' ? count(explode("\t", $values['name'])) : 0;
	$rows = array();
	for ($i = 0; $i < $n; $i++) {
		$row = array();
		foreach ($lists as $col => $src) {
			$parts = $values[$col] === '' ? array() : explode("\t", $values[$col]);
			$row[$col] = isset($parts[$i]) ? $parts[$i] : ($col === 'enabled' ? 'on' : '');
		}
		$rows[] = $row;
	}
	return $rows;
}

/**
 * Write the card rows back: the POS rows of every fiscal year (R2), the Paycards settings rows and the count in POS/1 box4.
 *
 * @param array<int, array<string, string>> $rows
 */
function settings_card_rows_write(array $rows): void
{
	$join = function (string $col) use ($rows): string {
		$out = array();
		foreach ($rows as $row) {
			$out[] = str_replace("\t", ' ', $row[$col]);
		}
		return db_escape_string(implode("\t", $out));
	};
	global $regnaar;
	foreach (array(1 => 'POS_valg', 2 => 'Pos valg', 3 => 'Pos valg') as $kodenr => $name) {
		if (!db_fetch_array(db_select("select id from grupper where art = 'POS' and kodenr = '$kodenr'", __FILE__ . " linje " . __LINE__))) {
			db_modify("insert into grupper (beskrivelse, kodenr, art, kode, fiscal_year) values ('$name', '$kodenr', 'POS', '', " . (int) $regnaar . ")", __FILE__ . " linje " . __LINE__);
		}
	}
	db_modify("update grupper set box4 = '" . count($rows) . "', box5 = '" . $join('name') . "', box6 = '" . $join('account') . "' where art = 'POS' and kodenr = '1'", __FILE__ . " linje " . __LINE__);
	db_modify("update grupper set box5 = '" . $join('terminal') . "' where art = 'POS' and kodenr = '2'", __FILE__ . " linje " . __LINE__);
	db_modify("update grupper set box4 = '" . $join('voucher') . "', box5 = '" . $join('voucher_text') . "' where art = 'POS' and kodenr = '3'", __FILE__ . " linje " . __LINE__);
	foreach (array('card_enabled' => 'enabled', 'voucherItems' => 'voucher_item') as $name => $col) {
		if (db_fetch_array(db_select("select id from settings where var_name = '$name'", __FILE__ . " linje " . __LINE__))) {
			db_modify("update settings set var_value = '" . $join($col) . "' where var_name = '$name'", __FILE__ . " linje " . __LINE__);
		} else {
			db_modify("insert into settings (var_name, var_grp, var_value, var_description, user_id) values ('$name', 'Paycards', '" . $join($col) . "', 'pos.cards', 0)", __FILE__ . " linje " . __LINE__);
		}
	}
	SettingsService::reset();
	$GLOBALS['settings_cards_changed'] = true;
}

/**
 * Add, move or remove a payment card (G10.3). $n is the card's number (1-based), 0 for add.
 */
function settings_card_action(string $run, int $n, string $key): string
{
	$rows = settings_card_rows();
	$i = $n - 1;
	$target = 'settingsSection.php?s=pos.cards';
	if ($run === 'card_add') {
		$rows[] = array('name' => st_txt(6317), 'account' => '', 'terminal' => '', 'voucher' => '', 'voucher_text' => '', 'enabled' => 'on', 'voucher_item' => '0');
		settings_card_rows_write($rows);
		if (function_exists('audit_log')) {
			audit_log('setting.row_created', json_encode(array('before' => null, 'after' => st_txt(6317)), JSON_UNESCAPED_UNICODE), 'indstilling', 'pos.cards#' . count($rows));
		}
		return $target . '&item=card_' . count($rows);
	}
	if (!isset($rows[$i])) {
		return $target;
	}
	if ($run === 'card_remove') {
		$name = $rows[$i]['name'];
		array_splice($rows, $i, 1);
		settings_card_rows_write($rows);
		if (function_exists('audit_log')) {
			audit_log('setting.row_deleted', json_encode(array('before' => $name, 'after' => null), JSON_UNESCAPED_UNICODE), 'indstilling', 'pos.cards#' . $n);
		}
		$_SESSION['settings_flash'] = array('ok', st_txt(6321) . ': ' . $name);
		return $target;
	}
	$j = ($run === 'card_up') ? $i - 1 : $i + 1;
	if (!isset($rows[$j])) {
		return $target . '&item=card_' . $n;
	}
	$tmp = $rows[$i];
	$rows[$i] = $rows[$j];
	$rows[$j] = $tmp;
	settings_card_rows_write($rows);
	if (function_exists('audit_log')) {
		audit_log('setting.action', json_encode(array('before' => $n, 'after' => $j + 1)), 'indstilling', $key);
	}
	return $target . '&item=card_' . ($j + 1);
}

/**
 * KDS colours: drop slots without minutes or colour and number the rest color_1.. in order of minutes
 * (debitor/kds/show_items.php casts the minutes, so a row without them would stop the kitchen screen).
 */
function settings_kds_colours_compact(): void
{
	$keep = array();
	$q = db_select("select id, var_value from settings where var_grp = 'KDS' and var_name like 'color%' order by id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$p = explode('-', (string) $r['var_value'], 2);
		if (count($p) === 2 && preg_match('/^[0-9]+$/', $p[0]) && preg_match('/^#[0-9a-f]{6}$/i', $p[1])) {
			$keep[] = array('id' => (int) $r['id'], 'min' => (int) $p[0], 'value' => $p[0] . '-' . strtolower($p[1]));
		} else {
			db_modify("delete from settings where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
		}
	}
	usort($keep, function ($a, $b) {
		return ($a['min'] <=> $b['min']) ?: ($a['id'] <=> $b['id']);
	});
	foreach ($keep as $i => $row) {
		db_modify("update settings set var_name = 'colortmp_" . ($i + 1) . "', var_value = '" . $row['value'] . "' where id = " . $row['id'], __FILE__ . " linje " . __LINE__);
	}
	foreach ($keep as $i => $row) {
		db_modify("update settings set var_name = 'color_" . ($i + 1) . "' where id = " . $row['id'], __FILE__ . " linje " . __LINE__);
	}
}

/**
 * Where an employee is referenced ('' when nowhere): a linked user, postings, cash-journal lines, commission rates.
 */
function settings_employee_usage(int $eid): string
{
	$parts = array();
	foreach (array(
		array("select count(*) as n from brugere where ansat_id = $eid", 6625),
		array("select count(*) as n from transaktioner where " . db_cast('ansat', 'text') . " = '$eid'", 6423),
		array("select count(*) as n from kassekladde where " . db_cast('ansat', 'text') . " = '$eid'", 6566),
		array("select count(*) as n from provision where ansat_id = $eid", 6626),
	) as $c) {
		$r = db_fetch_array(db_select($c[0], __FILE__ . " linje " . __LINE__));
		if ($r && (int) $r['n'] > 0) {
			$parts[] = sprintf(st_txt($c[1]), (int) $r['n']);
		}
	}
	return implode(' · ', $parts);
}

/**
 * Customers and suppliers with no activity for three years (G1.5): no change on the card, no orders, no open items,
 * no notes and no job cards since the cut-off. The same test runs when the list is shown and when it is deleted.
 *
 * @return array{D: int, K: int, ids: array<int, int>}
 */
function settings_gdpr_inactive(): array
{
	static $out = null;
	if ($out !== null) {
		return $out;
	}
	$cut = date('Y-m-d', strtotime('-3 years'));
	$out = array('D' => 0, 'K' => 0, 'ids' => array());
	$q = db_select("select a.id, a.art from adresser a where a.art in ('D', 'K') and a.modtime < '$cut' "
		. "and not exists (select 1 from ordrer o where o.konto_id = a.id) "
		. "and not exists (select 1 from openpost p where p.konto_id = a.id) "
		. "and not exists (select 1 from historik h where h.konto_id = a.id and h.notedate >= '$cut') "
		. "and not exists (select 1 from jobkort j where j.konto_id = a.id and j.initdate >= '$cut') order by a.art, a.firmanavn", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[$r['art']]++;
		$out['ids'][] = (int) $r['id'];
	}
	return $out;
}

/**
 * Delete the accounts settings_gdpr_inactive() finds right now, with their contacts, notes and job cards.
 */
function settings_gdpr_delete_inactive(): array
{
	$n = settings_gdpr_inactive();
	transaktion('begin');
	foreach ($n['ids'] as $id) {
		$q = db_select("select id from jobkort where konto_id = $id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			db_modify("delete from jobkort_felter where job_id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
		}
		db_modify("delete from jobkort where konto_id = $id", __FILE__ . " linje " . __LINE__);
		db_modify("delete from historik where konto_id = $id", __FILE__ . " linje " . __LINE__);
		db_modify("delete from ansatte where konto_id = $id", __FILE__ . " linje " . __LINE__);
		db_modify("delete from adresser where id = $id and art in ('D', 'K')", __FILE__ . " linje " . __LINE__);
	}
	transaktion('commit');
	return $n;
}

/**
 * Danger-zone actions are confirmed with the user's own password (spec §8.3). Passwords are kept as md5 in brugere.kode,
 * which is what the login compares against; a revisor session (no user row here) cannot run them.
 */
function settings_verify_password(string $typed): bool
{
	global $bruger_id;
	if ((int) $bruger_id <= 0 || $typed === '') {
		return false;
	}
	$r = db_fetch_array(db_select("select kode from brugere where id = " . (int) $bruger_id, __FILE__ . " linje " . __LINE__));
	$stored = $r ? trim((string) $r['kode']) : '';
	return $stored !== '' && (hash_equals(strtolower($stored), md5($typed)) || hash_equals($stored, saldikrypt((int) $bruger_id, $typed)));
}

/**
 * What an action touches, shown under it and in its dialog (spec §8.13 impact preview): "Påvirker 1.240 varer".
 */
function settings_impact_text(array $def): string
{
	if (empty($def['impact'])) {
		return '';
	}
	$n = null;
	if ($def['impact'] === 'cost_price_items') {
		// Same selection as includes/opdat_kostpriser.php: items in the groups flagged for it, with stock when the method is FIFO.
		$groups = array();
		$q = db_select("select kodenr from grupper where box8 = 'on'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$groups[] = "'" . db_escape_string((string) $r['kodenr']) . "'";
		}
		$where = "samlevare != 'on' and lukket != 'on'" . ($groups ? " and gruppe in (" . implode(',', $groups) . ")" : '');
		if (SettingsService::raw('items.stock.cost_method') === '1') {
			$where .= " and beholdning > 0";
		}
		$r = db_fetch_array(db_select("select count(*) as n from varer where $where", __FILE__ . " linje " . __LINE__));
		$n = $r ? (int) $r['n'] : 0;
	} elseif ($def['impact'] === 'ledger_reset') {
		$t = db_fetch_array(db_select("select count(*) as n from transaktioner", __FILE__ . " linje " . __LINE__));
		$o = db_fetch_array(db_select("select count(*) as n from ordrer", __FILE__ . " linje " . __LINE__));
		return sprintf(st_txt(6423), number_format((int) $t['n'], 0, ',', '.')) . ' · ' . sprintf(st_txt(6422), number_format((int) $o['n'], 0, ',', '.'));
	} elseif ($def['impact'] === 'employee_usage') {
		$u = settings_employee_usage((int) $def['scope_id']);
		return $u !== '' ? sprintf(st_txt(6415), $u) : '';
	} elseif ($def['impact'] === 'gdpr_inactive') {
		$g = settings_gdpr_inactive();
		return sprintf(st_txt(6465), $g['D']) . ' · ' . sprintf(st_txt(6466), $g['K']);
	} elseif ($def['impact'] === 'commission_items') {
		$r = db_fetch_array(db_select("select count(*) as n from varer where (varenr like 'kb%' or varenr like 'kn%') and ((retail_price > 0 and retail_price < 100) or (kostpris > 0 and kostpris < 1)) and coalesce(provision, 0) = 0", __FILE__ . " linje " . __LINE__));
		$n = $r ? (int) $r['n'] : 0;
	}
	return $n === null ? '' : sprintf(st_txt(6391), number_format($n, 0, ',', '.'));
}

/**
 * Follow-ups that run once after every field of a section is stored.
 */
function settings_after_section_save(): void
{
	if (!empty($GLOBALS['settings_deferred']['kds_colours'])) {
		settings_kds_colours_compact();
	}
	$GLOBALS['settings_deferred'] = array();
}

/**
 * Follow-ups after a value is stored ('on_save' in a definition).
 */
function settings_after_save(array $def, string $raw): void
{
	if ($def['on_save'] === 'company_email') {
		// The master database keeps a copy of the ledger's e-mail (regnskab.email), as the old Stamdata page did.
		global $db;
		db_modify("update regnskab set email = '" . db_escape_string($raw) . "' where db = '" . db_escape_string((string) $db) . "'", __FILE__ . " linje " . __LINE__, true);
	}
	if ($def['on_save'] === 'kds_colours') {
		$GLOBALS['settings_deferred']['kds_colours'] = true;
	}
	if ($def['on_save'] === 'pos_printer_changed') {
		// As the old page: the till re-reads its print server and terminal when the printer address changes.
		foreach (array('saldi_pfs', 'saldi_printserver', 'salditerm') as $cookie) {
			if (isset($_COOKIE[$cookie])) {
				saldi_setcookie($cookie, '', time() - 60, '/');
			}
		}
	}
	if ($def['on_save'] === 'pricelist_group_name') {
		// The old page kept the item group's name next to its number (box8); debitor/_varerInsert.php reads the name.
		$r = db_fetch_array(db_select("select beskrivelse from grupper where art = 'VG' and kodenr = '" . db_escape_string($raw) . "' order by fiscal_year desc limit 1", __FILE__ . " linje " . __LINE__));
		db_modify("update grupper set box8 = '" . db_escape_string($r ? (string) $r['beskrivelse'] : '') . "' where art = 'PL' and id = " . (int) $def['scope_id'], __FILE__ . " linje " . __LINE__);
	}
	if ($def['on_save'] === 'employee_department') {
		// The linked user's department follows the employee (as the old card and usersRoles.php do).
		$r = db_fetch_array(db_select("select id from brugere where ansat_id = " . (int) $def['scope_id'] . " order by id limit 1", __FILE__ . " linje " . __LINE__));
		if ($r) {
			update_settings_value('afd', 'brugerAfd', (int) $raw, 'Bruger afdeling', (int) $r['id']);
		}
	}
	if ($def['on_save'] === 'employee_end_date' && $raw !== '' && $raw !== '9999-12-31' && $raw <= date('Y-m-d')) {
		// An end date that has passed marks the employee as left, as the old card did on save (audit A6, now documented).
		db_modify("update ansatte set lukket = 'on' where id = " . (int) $def['scope_id'], __FILE__ . " linje " . __LINE__);
	}
	if ($def['on_save'] === 'smtp_changed' && function_exists('audit_log')) {
		// The roles spec names this event (settings redesign §11.2); the value itself is in the setting's own audit row.
		audit_log('integration.smtp_changed', $def['key']);
	}
}
