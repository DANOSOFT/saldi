<?php
// ---- includes/onboarding.php --- lap 5.0.0 --- 2026.10.11 ---
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
// 20261006 Sawaneh Onboarding part 1 (Requirements_onboarding_welcome_EN.md §4, §8): the guide's state per company in the
//                  settings table - onboarding_state (new/started/completed/hidden), onboarding_steps (JSON step => done/
//                  skipped), onboarding_source and onboarding_role. A company without a state counts as completed, so
//                  existing customers never see the pop-up without an upgrade step. The dashboard card is rendered here.
// 20261008 Sawaneh Step 3 VAT is the settings vat.registered / vat.period, period month|quarter|halfyear (decision 19).
// 20261008 Sawaneh Spec 2026-10-07 §7a hand-over to Sally: onb_handover_context() (profile, situation, source system, skipped
//                  steps, VAT, invited users); the card offers Få hjælp af Sally when the shell reports her not live.
// 20261011 Sawaneh Steps company/fiscal/users count as done when their data exists (settings spec §8.8,
//                  settings_section_complete(); fiscal also needs the VAT answer); onb_steps_stored() keeps what the
//                  guide recorded; onb_steps() answers once per request.
// 20261014 Sawaneh Test invoice: formularprint() answers 'OK' when it printed, which was read as a refusal; refusals are logged.
//                  The PDF converter and pdftk fall back to the server's own binaries when the master settings are empty.

if (!function_exists('onb_get')):

function onb_get(string $name): string
{
	$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'onboarding' and var_name = '" . db_escape_string($name) . "' and coalesce(user_id, 0) = 0 order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	return $r ? (string) $r['var_value'] : '';
}

function onb_set(string $name, string $value): void
{
	$n = db_escape_string($name);
	$v = db_escape_string($value);
	if (db_fetch_array(db_select("select id from settings where var_grp = 'onboarding' and var_name = '$n' and coalesce(user_id, 0) = 0", __FILE__ . " linje " . __LINE__))) {
		db_modify("update settings set var_value = '$v' where var_grp = 'onboarding' and var_name = '$n' and coalesce(user_id, 0) = 0", __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('onboarding', '$n', '$v', 'Onboarding guide', 0)", __FILE__ . " linje " . __LINE__);
	}
}

/**
 * The guide's steps in order: key => [title text, description text, important, available]. A step that is not
 * available (inviting without settings.users.manage) is left out of the guide and not counted as next.
 *
 * @return array<string, array{0: string, 1: string, 2: bool, 3: bool}>
 */
function onb_steps_def(): array
{
	return array(
		'welcome' => array('6685|Velkommen', '6686|Kort intro', false, true),
		'profile' => array('6687|Hvem er du?', '6688|Ny virksomhed eller skifter du system?', false, true),
		'company' => array('6689|Virksomhed og udtryk', '6690|CVR, firmaoplysninger, logo og farve', false, true),
		'fiscal'  => array('6691|Regnskabsår og moms', '6692|Startdato og momsperiode', true, true),
		'invoice' => array('6693|Din faktura', '6694|Bank, betalingsbetingelser og forhåndsvisning', false, true),
		'users'   => array('6695|Inviter kolleger', '6696|Bogholder, ejer eller medarbejdere', false, onb_can_invite()),
		'done'    => array('6697|Færdig', '6698|Opsummering', false, true),
	);
}

function onb_state(): string
{
	$s = onb_get('onboarding_state');
	return $s === '' ? 'completed' : $s;
}

/**
 * @return array<string, string> step => done|skipped
 */
function onb_steps(bool $fresh = false): array
{
	static $cache = null;
	if ($cache !== null && !$fresh) {
		return $cache;
	}
	$steps = onb_steps_stored();
	if (!function_exists('settings_section_complete')) {
		include_once(__DIR__ . "/../systemdata/settingsRegistry.php");
	}
	// The fiscal step also asks the VAT question (decision 19), so a year alone does not finish it.
	$vatAnswered = (bool) db_fetch_array(db_select("select id from settings where var_grp = 'vat' and var_name = 'vat_registered' and (user_id is null or user_id = 0)", __FILE__ . " linje " . __LINE__));
	foreach (array('company' => 'G1.1', 'fiscal' => 'G1.2', 'users' => 'G8') as $step => $section) {
		if ((!isset($steps[$step]) || $steps[$step] !== 'done') && settings_section_complete($section) && ($step !== 'fiscal' || $vatAnswered)) {
			$steps[$step] = 'done';
		}
	}
	return $cache = $steps;
}

/**
 * @return array<string, string> what the guide itself recorded
 */
function onb_steps_stored(): array
{
	$j = json_decode(onb_get('onboarding_steps'), true);
	return is_array($j) ? $j : array();
}

function onb_mark(string $step, string $status): void
{
	onb_set('onboarding_touched', (string) time());
	$steps = onb_steps_stored();
	if ($status === 'skipped' && isset($steps[$step]) && $steps[$step] === 'done') {
		return;
	}
	$steps[$step] = $status;
	onb_set('onboarding_steps', json_encode($steps));
	$steps = onb_steps(true);
	$all = true;
	foreach (array_keys(onb_steps_def()) as $k) {
		if (!isset($steps[$k])) {
			$all = false;
		}
	}
	if ($all && onb_state() !== 'hidden') {
		onb_set('onboarding_state', 'completed');
	}
}

/**
 * The guide is started (first opening, spec §4): who started it, when, and the login address for the reminder e-mail,
 * which is sent from the command line where no address is known.
 */
function onb_start(int $userId): void
{
	onb_set('onboarding_state', 'started');
	onb_set('onboarding_user', (string) $userId);
	onb_set('onboarding_started_at', (string) time());
	$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
	$host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string) $_SERVER['HTTP_HOST']) : '';
	if ($host !== '') {
		$root = rtrim(str_replace('\\', '/', dirname(dirname(isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/index/x'))), '/');
		onb_set('onboarding_url', ($https ? 'https' : 'http') . '://' . $host . $root . '/index/login.php');
	}
}

/**
 * Step 5 needs the right to manage users (spec §5 step 5, R5).
 */
function onb_can_invite(): bool
{
	return function_exists('perm_can') ? perm_can('settings.users.manage', 'write') : false;
}

/**
 * The invoice number the next posted invoice gets (a read-only look, no number is taken).
 */
function onb_next_invoice_peek(): int
{
	return function_exists('get_next_invoice_number') ? max(1, (int) get_next_invoice_number('DO')) : 1;
}

/**
 * A number typed in step 4 for a company switching from another system: whole, and not below the next number Saldi
 * would use anyway. Returns the number, or null when nothing was typed or it is refused ($errors['next_invoice']).
 */
function onb_next_invoice_check(array $posted, array &$errors): ?int
{
	$v = isset($posted['next_invoice']) ? trim($posted['next_invoice']) : '';
	if ($v === '') {
		return null;
	}
	$peek = onb_next_invoice_peek();
	if (!preg_match('/^\d{1,9}$/', $v) || (int) $v < $peek) {
		$errors['next_invoice'] = sprintf(findtekst('6796|Fakturanummeret skal være et helt tal og mindst %s.', isset($GLOBALS['sprog_id']) ? (int) $GLOBALS['sprog_id'] : 1), $peek);
		return null;
	}
	return (int) $v;
}

/**
 * The first invoice number of the number series (grupper RB 1 box1, edited on the first fiscal year's card); the
 * next invoice gets at least this number.
 */
function onb_next_invoice_set(int $n): void
{
	$r = db_fetch_array(db_select("select id, box1 from grupper where art = 'RB' and kodenr = '1'", __FILE__ . " linje " . __LINE__));
	if ($r) {
		db_modify("update grupper set box1 = '$n' where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("insert into grupper (beskrivelse, kodenr, kode, art, box1, box2, box3, box4, box5) values ('Regnskabsbilag', '1', '1', 'RB', '$n', '1', '', 'on', 'on')", __FILE__ . " linje " . __LINE__);
	}
	if (function_exists('audit_log')) {
		audit_log('setting.changed', 'Første fakturanummer ' . ($r ? $r['box1'] : '') . ' -> ' . $n, 'grupper', 'RB', 'onboarding');
	}
}

/**
 * A test invoice with the real form (spec §6.4): a customer, an order and a line are made in a transaction, the PDF is
 * printed from them through the print engine, and everything is rolled back, so nothing is posted, saved or numbered.
 *
 * @return array{ok: bool, file: string, url: string, error: string}
 */
function onb_test_invoice(): array
{
	global $db, $db_id, $bruger_id, $brugernavn, $charset, $ps2pdf, $pdftk, $sprog_id, $exec_path;
	$fail = function (string $e) {
		return array('ok' => false, 'file' => '', 'url' => '', 'error' => $e);
	};
	if (!db_fetch_array(db_select("select id from formularer where formular = 4 and art = 2 limit 1", __FILE__ . " linje " . __LINE__))) {
		return $fail('noform');
	}
	include_once(__DIR__ . '/formfunk.php');
	require_once(__DIR__ . '/stdFunc/renderPrintBatch.php');
	$co = db_fetch_array(db_select("select * from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__));
	$bet = ($co && trim((string) $co['betalingsbet']) !== '') ? trim((string) $co['betalingsbet']) : 'Netto';
	$dage = ($co && in_array($bet, array('Netto', 'Lb. md.'), true)) ? (int) $co['betalingsdage'] : 0;
	$no = onb_next_invoice_peek();
	$today = date('Y-m-d');
	$e = function ($s) {
		return db_escape_string((string) $s);
	};
	$kunde = findtekst('6793|Kunde A/S', $sprog_id);

	transaktion('begin');
	db_modify("insert into adresser (firmanavn, addr1, postnr, bynavn, land, kontonr, art, betalingsbet, betalingsdage) values ('" . $e($kunde) . "', 'Testvej 1', '1000', 'København K', 'Danmark', '0', 'D', '" . $e($bet) . "', $dage)", __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("select max(id) as id from adresser where art = 'D'", __FILE__ . " linje " . __LINE__));
	$kontoId = (int) $r['id'];
	$r = db_fetch_array(db_select("select coalesce(max(ordrenr), 0) + 1 as n from ordrer where art = 'DO'", __FILE__ . " linje " . __LINE__));
	$ordrenr = (int) $r['n'];
	db_modify("insert into ordrer (konto_id, firmanavn, addr1, postnr, bynavn, land, kontonr, art, valuta, valutakurs, sprog, ordredate, levdate, fakturadate, ordrenr, sum, momssats, moms, status, ref, fakturanr, betalingsbet, betalingsdage, udskriv_til, mail_fakt) values ($kontoId, '" . $e($kunde) . "', 'Testvej 1', '1000', 'København K', 'Danmark', '0', 'DO', 'DKK', 100, 'Dansk', '$today', '$today', '$today', $ordrenr, 10000, 25, 2500, 3, '" . $e($brugernavn) . "', '$no', '" . $e($bet) . "', $dage, 'PDF', '')", __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("select max(id) as id from ordrer where art = 'DO'", __FILE__ . " linje " . __LINE__));
	$ordreId = (int) $r['id'];
	db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse, antal, pris, rabat, vare_id, momsfri, momssats, samlevare) values ($ordreId, 1, '" . $e(findtekst('6794|Konsulentydelse', $sprog_id)) . "', 10, 1000, 0, 0, '', 25, '')", __FILE__ . " linje " . __LINE__);

	ob_start();
	$res = formularprint($ordreId, 4, 0, $charset, 'PDF');
	ob_end_clean();
	$name = isset($GLOBALS['printfilnavn']) ? (string) $GLOBALS['printfilnavn'] : '';
	transaktion('rollback');
	// formularprint() answers 'OK' or a PDF path when it printed; any other text is its refusal.
	$answer = is_string($res) ? trim($res) : '';
	if (($answer !== '' && $answer !== 'OK' && !preg_match('/\.pdf$/i', $answer)) || $name === '') {
		error_log("onboarding test invoice: print refused ('$answer', file '$name')");
		return $fail('print');
	}

	$dir = __DIR__ . "/../temp/$db/" . abs((int) $bruger_id);
	$pv = db_fetch_array(db_select("select box2, box3 from grupper where art = 'PV'", __FILE__ . " linje " . __LINE__));
	$html = empty($pv['box2']) && !empty($pv['box3']);
	$pages = 1;
	while ($html && is_file("$dir/{$name}_" . ($pages + 1) . ".htm")) {
		$pages++;
	}
	$bg = '';
	foreach (array("faktura_bg.pdf", "bg.pdf") as $f) {
		if (is_file(__DIR__ . "/../logolib/" . (int) $db_id . "/$f")) {
			$bg = __DIR__ . "/../logolib/" . (int) $db_id . "/$f";
			break;
		}
	}
	$out = 'testfaktura.pdf';
	try {
		// The converter as includes/udskriv.php picks it: the PV override, the master setting, else the server's own ps2pdf.
		$ps = !empty($pv['box2']) ? (string) $pv['box2'] : (string) $ps2pdf;
		if (trim($ps) === '') {
			$ps = rtrim(!empty($exec_path) ? (string) $exec_path : '/usr/bin', '/') . '/ps2pdf';
		}
		$merge = trim((string) $pdftk);
		foreach (array($merge, rtrim(!empty($exec_path) ? (string) $exec_path : '/usr/bin', '/') . '/pdftk', '/usr/bin/pdftk') as $candidate) {
			if ($candidate !== '' && is_file($candidate)) {
				$merge = $candidate;
				break;
			}
		}
		if ($merge === '' || !is_file($merge)) {
			error_log('onboarding test invoice: pdftk not found (master setting empty, no /usr/bin/pdftk)');
			return $fail('render');
		}
		renderPrintBatch($dir, array(array('name' => $name, 'background' => $bg, 'pages' => $pages)), $out, $html, $ps, $merge, true);
	} catch (\Throwable $ex) {
		error_log('onboarding test invoice: render failed: ' . $ex->getMessage());
		return $fail('render');
	}
	return array('ok' => true, 'file' => "$dir/$out", 'url' => "../temp/$db/" . abs((int) $bruger_id) . "/$out?t=" . time(), 'error' => '');
}

/**
 * Who may run the guide: the Indstillinger permission (§8).
 */
/**
 * What the guide hands Sally (SALDI Assist) when it ends (spec §7a): who the user is, where they come from, what was
 * skipped, VAT and invited users - so Sally continues instead of asking again.
 *
 * @return array<string, mixed>
 */
function onb_handover_context(): array
{
	$steps = onb_steps();
	$skipped = array();
	foreach (array('company', 'fiscal', 'invoice', 'users') as $k) {
		if (isset($steps[$k]) && $steps[$k] === 'skipped') {
			$skipped[] = $k;
		}
	}
	$source = onb_get('onboarding_source');
	$vat = onb_vat_get();
	return array(
		'profile' => onb_get('onboarding_role') === 'accountant' ? 'bookkeeper' : 'owner',
		'situation' => ($source === '' || $source === 'new') ? 'new' : 'switching',
		'source_system' => ($source === '' || $source === 'new' || $source === 'switch') ? 'unknown' : $source,
		'skipped_steps' => $skipped,
		'vat_registered' => $vat['registered'],
		'vat_period' => $vat['period'],
		'invited_users' => (int) onb_get('onboarding_invited'),
	);
}

function onb_can_run(): bool
{
	return function_exists('perm_can') ? perm_can('settings.company', 'write') : true;
}

/**
 * Steps counted on the card ("4 af 7"): done ones.
 */
function onb_done_count(): int
{
	$n = 0;
	foreach (onb_steps() as $status) {
		if ($status === 'done') {
			$n++;
		}
	}
	return $n;
}

/**
 * The first built step that is not done (the one "Fortsæt" opens), or '' when there is none.
 */
function onb_next_step(): string
{
	$steps = onb_steps();
	foreach (onb_steps_def() as $k => $d) {
		if ($d[3] && (!isset($steps[$k]) || $steps[$k] !== 'done')) {
			return $k;
		}
	}
	return '';
}

/**
 * The step before and after $step among the built ones ('' at the ends).
 *
 * @return array{0: string, 1: string}
 */
function onb_neighbours(string $step): array
{
	$keys = array();
	foreach (onb_steps_def() as $k => $d) {
		if ($d[3]) {
			$keys[] = $k;
		}
	}
	$i = array_search($step, $keys, true);
	if ($i === false) {
		return array('', '');
	}
	return array($i > 0 ? $keys[$i - 1] : '', isset($keys[$i + 1]) ? $keys[$i + 1] : '');
}

/**
 * VAT registration and period chosen in step 3: the settings vat.registered and vat.period (G2.2, decision 19).
 *
 * @return array{registered: bool, period: string}
 */
function onb_vat_get(): array
{
	return vat_registration();
}

function onb_vat_set(bool $registered, string $period): void
{
	update_settings_value('vat_registered', 'vat', $registered ? 'on' : 'off', 'VAT registered', 0);
	if ($registered && in_array($period, array('month', 'quarter', 'halfyear'), true)) {
		update_settings_value('vat_period', 'vat', $period, 'VAT period', 0);
	}
}

/**
 * The colour themes offered in step 2 (prototype), the first one being Saldi's default.
 *
 * @return array<int, string>
 */
function onb_colours(): array
{
	return array('114691', '1d9e57', '7c3aed', 'e0442c', '0e7c86', 'b45309', '1c2431', 'd63384');
}

/**
 * The user's menu colour (personal settings, var_grp 'colors'), without '#'.
 */
function onb_colour_get(int $userId): string
{
	$v = strtolower(ltrim((string) get_settings_value('buttonColor', 'colors', '114691', $userId), '#'));
	return preg_match('/^[0-9a-f]{6}$/', $v) ? $v : '114691';
}

/**
 * Store the colour personally, as the personal settings page does (spec A2: no ledger colour yet).
 */
function onb_colour_set(int $userId, string $hex): bool
{
	$hex = strtolower(ltrim(trim($hex), '#'));
	if ($userId <= 0 || !preg_match('/^[0-9a-f]{6}$/', $hex)) {
		return false;
	}
	update_settings_value('buttonColor', 'colors', $hex, 'Background color for user settings', $userId);
	return true;
}

function onb_logo_url($dbId): string
{
	$f = "../logolib/" . (int) $dbId . "/fe_logo.png";
	return file_exists($f) ? $f . "?t=" . filemtime($f) : '';
}

/**
 * Put the uploaded logo on the printed invoice (acceptance 5): the invoice form gets a LOGO element at the top right
 * when it has none with a size, and the form editor's compositor stamps the logo onto the invoice background
 * (faktura_bg.pdf, which the print engine uses for invoices, delivery notes and credit notes, when there is none).
 */
function onb_invoice_logo($dbId): void
{
	$png = "../logolib/" . (int) $dbId . "/fe_logo.png";
	if (!file_exists($png) || !function_exists('fe_composite_logo')) {
		return;
	}
	$info = @getimagesize($png);
	$w = 40.0;
	$h = ($info && $info[0] > 0) ? round($w * $info[1] / $info[0], 1) : 20.0;
	if ($h > 25) {
		$w = round($w * 25 / $h, 1);
		$h = 25.0;
	}
	$r = db_fetch_array(db_select("select id, xb, yb from formularer where formular = 4 and art = 1 and beskrivelse = 'LOGO' and sprog = 'Dansk' order by id limit 1", __FILE__ . " linje " . __LINE__));
	if (!$r) {
		db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, sprog) values (4, 1, 'LOGO', " . (190 - $w) . ", 290, $w, $h, 'Dansk')", __FILE__ . " linje " . __LINE__);
	} elseif ((float) $r['xb'] <= 0 || (float) $r['yb'] <= 0) {
		db_modify("update formularer set xa = " . (190 - $w) . ", ya = 290, xb = $w, yb = $h where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
	}
	fe_composite_logo($dbId, 4, 'Dansk', 'faktura_bg.pdf');
}

/**
 * The invoice's footer text (step 4): one centred text line at the bottom of the standard invoice form, remembered by
 * its row id. '$' is removed because the print engine reads $names as fields.
 */
function onb_footer_get(): string
{
	$id = (int) onb_get('invoice_footer_id');
	if (!$id) {
		return '';
	}
	$r = db_fetch_array(db_select("select beskrivelse from formularer where id = $id and formular = 4 and art = 2", __FILE__ . " linje " . __LINE__));
	return $r ? (string) $r['beskrivelse'] : '';
}

function onb_footer_set(string $text): void
{
	$text = trim(str_replace('$', '', $text));
	$id = (int) onb_get('invoice_footer_id');
	$exists = $id && db_fetch_array(db_select("select id from formularer where id = $id and formular = 4 and art = 2", __FILE__ . " linje " . __LINE__));
	if ($text === '') {
		if ($exists) {
			db_modify("delete from formularer where id = $id", __FILE__ . " linje " . __LINE__);
		}
		onb_set('invoice_footer_id', '');
		return;
	}
	$v = db_escape_string($text);
	if ($exists) {
		db_modify("update formularer set beskrivelse = '$v' where id = $id", __FILE__ . " linje " . __LINE__);
		return;
	}
	db_modify("insert into formularer (formular, art, beskrivelse, justering, xa, ya, xb, yb, str, color, font, fed, kursiv, side, sprog) values (4, 2, '$v', 'C', 105, 10, 0, 0, 9, 0, 'Helvetica', '', '', 'A', 'Dansk')", __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("select max(id) as id from formularer where formular = 4 and art = 2 and beskrivelse = '$v'", __FILE__ . " linje " . __LINE__));
	onb_set('invoice_footer_id', $r ? (string) $r['id'] : '');
}

/**
 * The "Kom godt i gang" card at the top of the dashboard (spec §7): shown while the guide is new or started, and as
 * "Opsætning færdig" once every step is set, until it is hidden. A ledger without a state (existing customers) gets no card.
 */
function onb_render_card(int $userId, int $sprogId): void
{
	$state = onb_get('onboarding_state');
	if ($state === '' || $state === 'hidden') {
		return;
	}
	$steps = onb_steps();
	if ($state === 'completed' && !$steps) {
		return;
	}
	$canRun = onb_can_run();
	$h = function ($s) {
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	};
	$tx = function ($t) use ($sprogId) {
		return findtekst($t, $sprogId);
	};
	$def = onb_steps_def();
	$total = count($def);
	$done = onb_done_count();
	$next = onb_next_step();
	if (!isset($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}

	print "<link rel='stylesheet' href='../css/onboarding.css?v=1'>";
	print "<div class='onb-card' style='--brand:#" . $h(onb_colour_get($userId)) . "'>";
	print "<div class='onb-card-hero'><div><h3>" . $h($state === 'completed' ? $tx('6775|Opsætning færdig') : $tx('6748|Kom godt i gang')) . "</h3>";
	if (!$canRun) {
		print "<p>" . $h($tx('6756|Opsætningen er ikke færdig – bed administrator om at gøre den færdig.')) . "</p>";
	} elseif ($state === 'completed') {
		print "<p>" . $h($tx('6757|Alt er sat op. Godt gået!')) . "</p>";
	} else {
		print "<p>" . $h($tx('6749|Et par korte trin, så Saldi ser ud som dit firma og er klar til din første faktura.')) . "</p>";
	}
	$dash = round($done / max(1, $total) * 239);
	print "<div class='onb-ring'><svg width='92' height='92'><circle class='bg' cx='46' cy='46' r='38'/><circle class='fg' cx='46' cy='46' r='38' stroke-dasharray='$dash 239'/></svg><b>$done/$total</b></div></div>";
	if ($canRun) {
		print "<div class='cta'>";
		if ($next !== '') {
			$label = $done === 0 ? $tx('6751|Start opsætning') : sprintf($tx('6752|Fortsæt: %s'), $tx($def[$next][0]));
			print "<button type='button' class='light' onclick=\"onbOpen('" . $h($next) . "')\">" . $h($label) . " &rarr;</button>";
		} else {
			print "<button type='button' class='light' onclick=\"onbOpen('profile')\">" . $h($tx('6758|Gennemse opsætning')) . "</button>";
		}
		print "</div>";
	}
	print "</div><div class='onb-card-body'><div class='onb-card-head'><span>" . $h(sprintf($tx('6750|%s af %s trin færdige'), $done, $total)) . "</span>";
	if ($canRun) {
		print "<form method='post' action='onboarding.php' style='margin:0'><input type='hidden' name='csrf_token' value='" . $h($_SESSION['csrf_token']) . "'>"
			. "<input type='hidden' name='action' value='hide'><button type='submit' class='ghost'>" . $h($state === 'completed' ? $tx('6774|Skjul') : $tx('6753|Skjul tjekliste')) . "</button></form>";
	}
	print "</div><div class='onb-grid'>";
	$check = "<svg width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><path d='M5 13l4 4L19 7'/></svg>";
	foreach ($def as $k => $d) {
		$st = isset($steps[$k]) ? $steps[$k] : '';
		$cls = $st === 'done' ? 'done' : (($st === 'skipped' && $d[2]) ? 'imp' : '');
		$icon = $cls === 'done' ? $check : ($cls === 'imp' ? '!' : '');
		$tag = $cls === 'imp' ? "<span class='onb-tag imp'>" . $h($tx('6754|Vigtigt')) . "</span>" : '';
		$desc = ($k === 'users' && !$d[3]) ? '6797|Bed din administrator om at invitere kolleger' : $d[1];
		$open = ($canRun && $d[3]) ? " onclick=\"onbOpen('" . $h($k) . "')\"" : '';
		print "<button type='button' class='onb-step" . (($canRun && $d[3]) ? '' : ' soon') . "'$open><span class='onb-st $cls'>$icon</span><span class='t'><b>" . $h($tx($d[0])) . "</b><span>" . $h($tx($desc)) . "</span></span>$tag</button>";
	}
	$part2 = array(array('6760|Kontoplan', '6768|Behold standard, vælg skabelon eller importér'), array('6761|Åbningsbalance', '6769|Saldobalance og åbne poster'),
		array('6762|Debitorer og kreditorer', '6770|Importér kunder og leverandører'), array('6763|Varer og lager', '6771|Importér varer og beholdning'));
	foreach ($part2 as $p) {
		print "<div class='onb-step soon'><span class='onb-st'></span><span class='t'><b>" . $h($tx($p[0])) . "</b><span>" . $h($tx($p[1])) . "</span></span><span class='onb-tag'>" . $h($tx('6759|Del 2')) . "</span></div>";
	}
	// Spec §7a: while Sally cannot open by herself, the list offers her instead.
	print "<button type='button' class='onb-step' id='onb-sally' hidden onclick='onbSally()'><span class='onb-st'>?</span><span class='t'><b>" . $h($tx('6915|Få hjælp af Sally')) . "</b><span>" . $h($tx('6916|Sally hjælper med at hente dine data og viser dig rundt i Saldi.')) . "</span></span></button>";
	print "</div></div></div>";
	$ctx = json_encode(onb_handover_context(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	print "<script>var onbSallyCtx = $ctx; function onbSally(){ if (window.parent && window.parent !== window && window.parent.saldiAssistOpen) { window.parent.saldiAssistOpen(onbSallyCtx); } }"
		. " (function(){ var p = window.parent; if (p && p !== window && typeof p.saldiAssistLive === 'function' && !p.saldiAssistLive()) { var r = document.getElementById('onb-sally'); if (r) { r.hidden = false; } } })();</script>";
	print "<script>function onbOpen(s){ if (window.parent && window.parent !== window && window.parent.saldiOnboardingOpen) { window.parent.saldiOnboardingOpen(s); } else { location.href = 'onboarding.php?step=' + encodeURIComponent(s); } }</script>";
}

endif;
