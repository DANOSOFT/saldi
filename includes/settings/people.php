<?php
// ---- includes/settings/people.php --- ver 5.1.0 --- 2026.11.02 ---
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
// 20261102 CL/ASR Created: Brugere & ansatte, one place for people (owner, 2 Nov 2026: "Ét punkt, 'Brugere & ansatte', og én liste med alle", one card with the tabs Stamdata and Login og rolle, one button "Tilføj person").
//                 The one list is the users list of systemdata/usersRoles.php: its logins, plus the company's employees without a login ("Ingen login"), employees first in their list order (posnr: POS and "vores ref." use it), then the other logins by name.
//                 Search, filters (also "Ingen login"), sorting, pages and the bulk bar are usersRoles.php's own; a row without a login has no tick box, so bulk only ever works on logins.
//                 The card is usersRoles.php's user drawer with two tabs.
//                 Login og rolle is the drawer as it was (it saves and checks there: CSRF, permissions, own role, last administrator, R5, revisor).
//                 Stamdata is the employee drawer of Indstillinger (systemdata/settingsSection.php?s=organisation.employees&embed=person, in an iframe: it saves there, with its own checks).
//                 A person without a login gets "Giv adgang" (Inviter / Opret login med adgangskode: the new-login form, linked to the employee and filled in; nothing is sent before Send invitation).
//                 A login without an employee opens on Login og rolle.
//                 "Tilføj person" posts the employee section's own add action (with name, initials and e-mail), or opens the new-login form.
//                 The usersRoles.php side (ur_people_*) only reads; the settings side (settings_people_*) only routes and checks input.
//                 Javascript in javascript/usersPeople.js, look in css/usersPeople.css.
// 20261102 CL/ASR Review: when the list is too narrow for its E-mail column (the app shell at 1366 px), a row's sub-line shows the initials and the e-mail (ur_people_sub).
//                 A search hit for Tilføj person (organisation.employees.add) opens the list with its dialog (add=1).
//                 Giv adgang names both buttons in its note (7792 replaces 7775).

/**
 * Where a plain visit of the employee list goes for a user who may open usersRoles.php: the one list, or the person's card there
 * ('' = it stays: no access, a popup, a message is waiting, or an embedded/extra-fields address). The shell's inframe=1 (every
 * page opened from the top bar carries it) goes along, as usersRoles.php keeps it on its own links.
 */
function settings_people_list_target(array $get): string
{
	if (function_exists('perm_can') && !perm_can('settings.users.manage', 'read')) {
		return '';
	}
	foreach (array('popup', 'embed', 'err', 'reverted', 'saved', 'newkey', 'done', 'converted') as $k) {
		if (isset($get[$k])) {
			return '';
		}
	}
	if (!empty($_SESSION['settings_flash']) || !empty($_SESSION['settings_error'])) {
		return '';
	}
	$item = (isset($get['item']) && is_string($get['item'])) ? $get['item'] : '';
	$field = (isset($get['field']) && is_string($get['field'])) ? $get['field'] : '';
	$to = 'usersRoles.php?' . (!empty($get['inframe']) ? 'inframe=1&' : '') . 'tab=users';
	if (preg_match('/^organisation\.employees\.[a-z_0-9]+\.([1-9][0-9]{0,9})\z/', $field, $m)) {
		return $to . '&ansat=' . (int) $m[1] . '&fane=stamdata&field=' . rawurlencode($field);
	}
	if (preg_match('/^emp_([1-9][0-9]{0,9})\z/', $item, $m)) {
		return $to . '&ansat=' . (int) $m[1] . '&fane=stamdata';
	}
	if ($field === 'organisation.employees.add') {
		return $to . '&add=1'; // the search hit "Tilføj person": the list opens its dialog (javascript/usersPeople.js), when the user may add
	}
	return $to;
}

/**
 * The employee list's address leaves the navigation stack when it sends the visit on (Tilbage must not lead back to it).
 */
function settings_people_nav_drop(): void
{
	if (!function_exists('_nav_read') || !function_exists('_nav_write') || !function_exists('_nav_page')) {
		return;
	}
	$stack = _nav_read();
	$n = count($stack);
	if ($n >= 1 && _nav_page((string) $stack[$n - 1]) === _nav_page((string) $_SERVER['REQUEST_URI'])) {
		array_pop($stack);
		_nav_write($stack);
	}
}

/**
 * Name, initials and e-mail typed in "Tilføj person", for the employee section's add action: control characters out, capped at the
 * lengths the old employee card allowed, an e-mail only when it is one. Written by employee_add (includes/settings/actions.php).
 *
 * @return array{navn: string, initialer: string, email: string}
 */
function settings_people_person_input(array $in): array
{
	$charset = function_exists('st_charset') ? st_charset() : 'UTF-8';
	$clean = function ($v, int $max) use ($charset): string {
		$v = is_string($v) ? trim((string) preg_replace('/[\x00-\x1f\x7f]+/', ' ', $v)) : '';
		return mb_substr($v, 0, $max, $charset);
	};
	$email = $clean(isset($in['email']) ? $in['email'] : '', 200);
	if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$email = '';
	}
	return array('navn' => $clean(isset($in['navn']) ? $in['navn'] : '', 100), 'initialer' => $clean(isset($in['initialer']) ? $in['initialer'] : '', 10), 'email' => $email);
}

/**
 * After "Tilføj person" added the employee: its card in usersRoles.php, or with a login wanted (then=invite|classic) the new-login form
 * linked to it. The add action's own address when the user may not open usersRoles.php.
 */
function settings_people_after_add(string $target, string $then): string
{
	if (!preg_match('/[?&]item=emp_([1-9][0-9]{0,9})\z/', $target, $m) || (function_exists('perm_can') && !perm_can('settings.users.manage', 'read'))) {
		return $target;
	}
	$eid = (int) $m[1];
	if (in_array($then, array('invite', 'classic'), true) && (!function_exists('perm_can') || perm_can('settings.users.manage', 'write'))) {
		return 'usersRoles.php?tab=users&bruger=0&ansat=' . $eid . ($then === 'classic' ? '&mode=classic' : '');
	}
	return 'usersRoles.php?tab=users&ansat=' . $eid . '&fane=stamdata';
}

// ================================================================== usersRoles.php: the one list and the card

/**
 * The card's own parameters, checked: '_ansat' (a company employee, only for a user who may read employees; with no 'bruger' its
 * first login is opened), '_fane' (stamdata|login) and '_field' (an employee field to show in Stamdata).
 */
function ur_people_request(array $get): array
{
	$get['_ansat'] = 0;
	$get['_fane'] = '';
	$get['_field'] = '';
	unset($get['ansat_given']);
	if (isset($get['ansat']) && is_string($get['ansat']) && preg_match('/^[1-9][0-9]{0,9}\z/', $get['ansat']) && perm_can('settings.organisation', 'read')) {
		$sid = settings_company_account_id();
		if ($sid > 0 && db_fetch_array(db_select("select id from ansatte where id = " . (int) $get['ansat'] . " and konto_id = $sid", __FILE__ . " linje " . __LINE__))) {
			$get['_ansat'] = (int) $get['ansat'];
		}
	}
	if ($get['_ansat'] > 0 && !isset($get['bruger'])) {
		$r = db_fetch_array(db_select("select min(id) as id from brugere where ansat_id = " . $get['_ansat'], __FILE__ . " linje " . __LINE__));
		if ($r && (int) $r['id'] > 0) {
			$get['bruger'] = (string) (int) $r['id'];
		}
	}
	if (isset($get['fane']) && in_array($get['fane'], array('stamdata', 'login'), true)) {
		$get['_fane'] = (string) $get['fane'];
	}
	if (isset($get['field']) && is_string($get['field']) && preg_match('/^organisation\.employees\.[a-z_0-9]+\.[0-9]+\z/', $get['field'])) {
		$get['_field'] = $get['field'];
	}
	return $get;
}

/**
 * The tab a login's card opens on when none is asked for: Login og rolle for a login without a stamkort, one waiting for its role to be
 * confirmed, the revisor, and a bookkeeper or auditor role (owner: "Revisor og bogholder åbner direkte på 'Login og rolle'"); Stamdata otherwise.
 */
function ur_people_default_fane(array $u, bool $hasStam): string
{
	$key = ($u['role'] && isset($u['role']['key'])) ? (string) $u['role']['key'] : '';
	return (!$hasStam || $u['review'] || $u['isRevisor'] || in_array($key, array('bogholder', 'revisor'), true)) ? 'login' : 'stamdata';
}

/**
 * What the list and the card need beyond ur_view_model(): the company's employees (only with read on settings.organisation), the order
 * of the rows, the card's tabs and the "Tilføj person" choices.
 *
 * @param array<string, mixed> $vm  from ur_view_model()
 * @param array<string, mixed> $get from ur_people_request()
 * @return array<string, mixed>
 */
function ur_people_model(array $vm, array $get): array
{
	$canOrg = perm_can('settings.organisation', 'read');
	$vm['canOrg'] = $canOrg;
	$vm['canOrgWrite'] = perm_can('settings.organisation', 'write');
	$company = array();
	if ($canOrg) {
		$sid = settings_company_account_id();
		$q = db_select("select id, navn, initialer, email, lukket from ansatte where konto_id = $sid order by coalesce(posnr, 999999), nummer, id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$company[(int) $r['id']] = array('id' => (int) $r['id'], 'navn' => trim((string) $r['navn']) !== '' ? trim((string) $r['navn']) : '—',
				'initialer' => trim((string) $r['initialer']), 'email' => trim((string) $r['email']), 'left' => trim((string) $r['lukket']) !== '');
		}
	}
	$vm['company'] = $company;

	// The rows: each employee in the list order with its logins (or as "Ingen login"), then the other logins by name.
	$byEmployee = array();
	foreach ($vm['users'] as $i => $u) {
		if ($u['ansat_id'] > 0 && isset($company[$u['ansat_id']])) {
			$byEmployee[$u['ansat_id']][] = $i;
		}
	}
	$users = array();
	$before = array();
	$pending = array();
	$placed = array();
	foreach ($company as $eid => $e) {
		if (!isset($byEmployee[$eid])) {
			$pending[] = $eid;
			continue;
		}
		foreach ($byEmployee[$eid] as $i) {
			$u = $vm['users'][$i];
			$before[$u['id']] = $pending;
			$pending = array();
			$users[] = $u;
			$placed[$i] = true;
		}
	}
	$rest = array();
	foreach ($vm['users'] as $i => $u) {
		if (!isset($placed[$i])) {
			$rest[] = $u;
		}
	}
	usort($rest, function ($a, $b) {
		return strcmp(mb_strtolower($a['navn']) . "\0" . mb_strtolower($a['brugernavn']), mb_strtolower($b['navn']) . "\0" . mb_strtolower($b['brugernavn']));
	});
	foreach ($rest as $u) {
		if ($pending) {
			$before[$u['id']] = $pending;
			$pending = array();
		}
		$users[] = $u;
	}
	$vm['users'] = $users;
	$vm['peopleBefore'] = $before;
	$vm['peopleRest'] = $pending;
	$vm['noLogin'] = array();
	foreach ($company as $eid => $e) {
		if (!isset($byEmployee[$eid])) {
			$vm['noLogin'][$eid] = true;
		}
	}

	// The card: a person without a login (ansat=), or a login (bruger=), with the employee its Stamdata tab shows.
	$vm['person'] = null;
	if ($vm['tab'] === 'users' && $vm['editUser'] === null && $get['_ansat'] > 0 && isset($company[$get['_ansat']]) && isset($vm['noLogin'][$get['_ansat']])) {
		$vm['person'] = $company[$get['_ansat']];
	}
	$u = $vm['editUser'];
	$cardEid = $vm['person'] ? $vm['person']['id'] : (($u !== null && $u['ansat_id'] > 0 && isset($company[$u['ansat_id']])) ? $u['ansat_id'] : 0);
	$vm['cardEid'] = $cardEid;
	$vm['stamUrl'] = $cardEid > 0 ? 'settingsSection.php?s=organisation.employees&item=emp_' . $cardEid . '&embed=person&popup=1' . ($get['_field'] !== '' ? '&field=' . rawurlencode($get['_field']) : '') : '';
	$isNew = ($u !== null && $u['id'] === 0);
	$vm['cardTabs'] = $canOrg && $vm['tab'] === 'users' && ($vm['person'] !== null || ($u !== null && (!$isNew || $cardEid > 0)));
	if (!$vm['cardTabs']) {
		$fane = 'login';
	} elseif ($vm['person'] !== null) {
		$fane = $get['_fane'] !== '' ? $get['_fane'] : 'stamdata';
	} elseif ($isNew || $vm['msg'] !== '') {
		$fane = $get['_fane'] !== '' ? $get['_fane'] : 'login'; // a new login, or back from saving the login
	} elseif ($get['_fane'] !== '') {
		$fane = $get['_fane'];
	} else {
		$fane = isset($get['ansat']) ? ur_people_default_fane($u, $cardEid > 0) : 'login'; // bruger=N alone: the old address of the login
	}
	$vm['fane'] = $fane;
	// The person's other logins (one employee, two logins): each is reachable from the other's card.
	$vm['otherLogins'] = array();
	if ($u !== null && !$isNew && $u['ansat_id'] > 0) {
		foreach ($users as $o) {
			if ($o['ansat_id'] === $u['ansat_id'] && $o['id'] !== $u['id']) {
				$vm['otherLogins'][] = $o;
			}
		}
	}
	// A new login linked to an employee keeps the link when the form comes back refused (a username already taken).
	$vm['userFormUrl'] = $vm['selfUrl'];
	if ($isNew && $u['ansat_id'] > 0 && $u['ansat_id'] === $get['_ansat']) {
		$vm['userFormUrl'] .= (strpos($vm['selfUrl'], '?') === false ? '?' : '&') . 'ansat=' . (int) $get['_ansat'];
	}
	// "Se log" from the card: Tilbage on the log returns to the card (ret=user_<id>, a whitelist built from an int).
	$vm['logBack'] = '';
	if ($vm['tab'] === 'log' && isset($get['ret']) && is_string($get['ret']) && preg_match('/^user_([1-9][0-9]{0,9})\z/', $get['ret'], $m)) {
		$vm['logBack'] = 'tab=users&bruger=' . (int) $m[1] . '&fane=login';
	}
	return $vm;
}

/**
 * The list's lead: "5 ansatte · 1 bruger · 1 administrator" (+ invited and closed when there are any), each count with its own singular.
 */
function ur_people_lead(array $vm, array $counts): string
{
	$sprog = (int) $vm['sprogId'];
	$n = function (int $count, string $one, string $many) use ($sprog): string {
		return $count === 1 ? findtekst($one, $sprog) : sprintf(findtekst($many, $sprog), $count);
	};
	$parts = array();
	if (!empty($vm['canOrg'])) {
		$active = 0;
		foreach ($vm['company'] as $e) {
			$active += $e['left'] ? 0 : 1;
		}
		$parts[] = $n($active, '7782|1 ansat', '6034|%s ansatte');
	}
	$parts[] = $n($counts['all'], '7783|1 bruger', '6036|%s brugere');
	$parts[] = $n($counts['admins'], '7784|1 administrator', '7785|%s administratorer');
	if ($counts['invited'] > 0) {
		$parts[] = $n($counts['invited'], '7786|1 inviteret', '7787|%s inviterede');
	}
	if ($counts['closed'] > 0) {
		$parts[] = $n($counts['closed'], '7788|1 lukket', '7789|%s lukkede');
	}
	return implode(' · ', $parts);
}

/**
 * The one button of the list's head: "Tilføj person" (a dialog asks whether the person gets a login), for a user who may add
 * employees or logins.
 */
function ur_people_add_button(array $vm, callable $t): string
{
	if (!$vm['canWrite'] && empty($vm['canOrgWrite'])) {
		return '';
	}
	return '<button type="button" class="sk-btn sk-btn-primary" data-padd aria-haspopup="dialog"><i class="bx bx-user-plus"></i>' . $t('7765|Tilføj person') . '</button>';
}

/**
 * The status filter's "Ingen login" choice (employees without a login), for a user who sees the employees.
 */
function ur_people_status_option(array $vm, callable $t): string
{
	return !empty($vm['canOrg']) ? '<option value="nologin">' . $t('7754|Ingen login') . '</option>' : '';
}

/**
 * The address of a login's card from the list (relative query): Login og rolle or Stamdata, as ur_people_default_fane() says.
 */
function ur_people_row_link(array $vm, array $u): string
{
	$q = 'tab=users&bruger=' . (int) $u['id'];
	if (!empty($vm['canOrg'])) {
		$q .= '&fane=' . ur_people_default_fane($u, $u['ansat_id'] > 0 && isset($vm['company'][$u['ansat_id']]));
	}
	return $q;
}

/**
 * A row's sub-line: the initials and the username (an employee without a login: the initials). A second one with the e-mail in place
 * of the username is shown instead when the list is too narrow for its E-mail column (css/usersPeople.css), so the e-mail stays in view.
 */
function ur_people_sub(callable $h, string $initials, string $username, string $email): string
{
	$wide = implode(' · ', array_filter(array($initials, $username), 'strlen'));
	$narrow = implode(' · ', array_filter(array($initials, $email !== '' ? $email : $username), 'strlen'));
	if ($narrow === $wide) {
		return $wide === '' ? '' : '<span class="sk-sub">' . $h($wide) . '</span>';
	}
	return ($wide !== '' ? '<span class="sk-sub ur-sub-w">' . $h($wide) . '</span>' : '') . '<span class="sk-sub ur-sub-n" title="' . $h($email) . '">' . $h($narrow) . '</span>';
}

/**
 * The line under a login's name: the employee's initials and the username (the e-mail on a narrow list), and "Fratrådt" for an
 * employee who has left (the initials and Fratrådt only for a user who may read the employees).
 */
function ur_people_user_sub(array $vm, array $u, callable $h, callable $t): string
{
	$initials = (!empty($vm['canOrg']) && trim((string) $u['initialer']) !== '') ? trim((string) $u['initialer']) : ''; // only for a user who may read the employees
	$out = ur_people_sub($h, $initials, (string) $u['brugernavn'], trim((string) $u['email']));
	if ($u['ansat_id'] > 0 && !empty($vm['company'][$u['ansat_id']]['left'])) {
		$out .= '<span class="sk-tag">' . $t('660|Fratrådt') . '</span>';
	}
	return $out;
}

/**
 * The rows of the employees without a login that come before a login in the list's order.
 */
function ur_people_rows_before(array $vm, array $u, callable $h, callable $t, callable $link): void
{
	foreach (isset($vm['peopleBefore'][$u['id']]) ? $vm['peopleBefore'][$u['id']] : array() as $eid) {
		ur_people_row($vm, $vm['company'][$eid], $h, $t, $link);
	}
}

/**
 * The rows of the employees without a login after the last login.
 */
function ur_people_rows_rest(array $vm, callable $h, callable $t, callable $link): void
{
	foreach (isset($vm['peopleRest']) ? $vm['peopleRest'] : array() as $eid) {
		ur_people_row($vm, $vm['company'][$eid], $h, $t, $link);
	}
}

/**
 * One employee without a login, in the columns of the users table: no tick box (bulk works on logins), no role, 2FA or last active.
 */
function ur_people_row(array $vm, array $e, callable $h, callable $t, callable $link): void
{
	$cardLink = $link('tab=users&ansat=' . (int) $e['id'] . '&fane=stamdata');
	$search = mb_strtolower($e['navn'] . ' ' . $e['initialer'] . ' ' . $e['email']);
	$on = ($vm['person'] !== null && $vm['person']['id'] === $e['id']);
	?>
          <tr data-search="<?= $h($search) ?>" data-status="nologin" data-role="" data-tfa="" data-href="<?= $cardLink ?>" class="ur-nologin<?= $on ? ' on' : '' ?>">
            <?php if ($vm['canWrite']) { ?><td class="sk-c ur-cb"></td><?php } ?>
            <td class="ur-name" data-v="<?= $h(mb_strtolower($e['navn'])) ?>">
              <a class="ur-name-a" href="<?= $cardLink ?>"><?= $h($e['navn']) ?></a><?= ur_people_sub($h, $e['initialer'], '', $e['email']) ?><?php if ($e['left']) { ?><span class="sk-tag"><?= $t('660|Fratrådt') ?></span><?php } ?>
            </td>
            <td class="sk-muted" title="<?= $h($e['email']) ?>"><?= $h($e['email']) ?></td>
            <td data-v=""><span class="sk-muted">–</span></td>
            <td class="sk-c" data-v=""></td>
            <td class="sk-muted" data-v=""></td>
            <td data-v="nologin"><span class="sk-muted"><?= $t('7754|Ingen login') ?></span></td>
            <td class="sk-act"><a class="sk-btn sk-btn-ghost sk-btn-icon sk-btn-sm" href="<?= $cardLink ?>" aria-label="<?= $t('6157|Åbn') ?>" title="<?= $t('6157|Åbn') ?>"><i class='bx bx-chevron-right'></i></a></td>
          </tr>
	<?php
}

/**
 * The drawer's attributes for the card: its tab (data-fane) and that it is a person card (wider, two tabs).
 */
function ur_people_drawer_attrs(array $vm): string
{
	return ' data-fane="' . ($vm['fane'] === 'stamdata' ? 'stamdata' : 'login') . '"' . (!empty($vm['cardTabs']) ? ' data-person="1"' : '');
}

/**
 * Under the card's head: the tabs Stamdata | Login og rolle, the Stamdata tab (the employee drawer in an iframe, loaded when it is first
 * shown, or "har intet stamkort"), and on Login og rolle the person's other logins and "Se log".
 */
function ur_people_card_head(array $vm, callable $h, callable $t, callable $link): void
{
	$u = $vm['editUser'];
	$name = $vm['person'] !== null ? $vm['person']['navn'] : ($u !== null ? $u['navn'] : '');
	// "Se log": the log of this login (Tilbage there returns to the card); right of the tabs on Login og rolle, or alone above the form
	$log = ($vm['canAudit'] && $u !== null && $u['id'] > 0) ? '<a class="sk-link ur-plog" data-pane="login" href="' . $link('tab=log&bruger=' . rawurlencode($u['brugernavn']) . '&ret=user_' . (int) $u['id']) . '">' . $t('7762|Se log') . '</a>' : '';
	if (!empty($vm['cardTabs'])) {
		?>
    <nav class="sk-tabs ur-ptabs" role="tablist" aria-label="<?= $h($name) ?>">
      <button type="button" class="sk-tab" role="tab" id="ur-tab-stamdata" data-fane="stamdata" aria-controls="ur-pane-stamdata" aria-selected="<?= $vm['fane'] === 'stamdata' ? 'true' : 'false' ?>"><?= $t('779|Stamdata') ?></button>
      <button type="button" class="sk-tab" role="tab" id="ur-tab-login" data-fane="login" aria-selected="<?= $vm['fane'] === 'login' ? 'true' : 'false' ?>"><?= $t('7753|Login og rolle') ?></button>
      <?= $log ?>
    </nav>
    <div class="sk-drawer-b ur-stam" id="ur-pane-stamdata" role="tabpanel" aria-labelledby="ur-tab-stamdata">
		<?php if ($vm['stamUrl'] !== '') { ?>
      <iframe class="ur-stam-frame" id="ur-stam-frame" data-src="<?= $h($vm['stamUrl']) ?>" title="<?= $t('779|Stamdata') ?>"></iframe>
		<?php } else { ?>
      <div class="ur-stam-none">
        <p><b><?= $h(sprintf(findtekst('7776|%s har intet stamkort.', $vm['sprogId']), $name)) ?></b></p>
        <p class="sk-muted"><?= $t('7777|Et stamkort bruges til ansatte: initialer, kontakt og ansættelse, fx som "Vores ref." på ordrer. Revisor, bogholder og andre udefra har normalt ikke brug for et.') ?></p>
			<?php if ($vm['canWrite'] && $u !== null && $u['id'] > 0 && $vm['company']) { ?>
        <button type="button" class="sk-btn sk-btn-sm" data-plink><?= $t('7778|Kobl til en ansat') ?></button>
			<?php } ?>
      </div>
		<?php } ?>
    </div>
		<?php
	}
	if ($u === null || $u['id'] === 0) {
		return;
	}
	$others = array();
	foreach ($vm['otherLogins'] as $o) {
		$state = $o['closed'] ? '5559|Lukket' : ($o['invited'] ? '5778|Inviteret' : '5558|Aktiv');
		$role = $o['role'] ? htmlspecialchars(perm_role_name($o['role'], $vm['sprogId']), ENT_QUOTES, 'UTF-8') : $t('5554|Ingen rolle');
		$others[] = '<a href="' . $link('tab=users&bruger=' . (int) $o['id'] . '&fane=login') . '">' . $h($o['brugernavn']) . ' · ' . $role . ' · ' . $t($state) . '</a>';
	}
	if (!empty($vm['cardTabs'])) {
		$log = ''; // in the tab row
	}
	if ($others || $log !== '') {
		?>
    <div class="ur-pbar" data-pane="login">
      <span class="sk-grow"><?php if ($others) { ?><?= $t('7779|Har også login:') ?> <?= implode(', ', $others) ?><?php } ?></span><?= $log ?>
    </div>
		<?php
	}
}

/**
 * The card of an employee without a login: Stamdata, and on Login og rolle "Giv adgang" - the new-login form linked to the employee
 * (usersRoles.php's own, filled in; nothing is sent before Send invitation). Not for an employee who has left.
 */
function ur_people_card_nologin(array $vm, callable $h, callable $t, callable $link): void
{
	$p = $vm['person'];
	$closeUrl = $link('tab=users');
	$new = 'tab=users&bruger=0&ansat=' . (int) $p['id'] . '&fane=login';
	?>
  <a class="sk-scrim" href="<?= $closeUrl ?>" aria-label="<?= $t('2172|Luk') ?>"></a>
  <aside class="sk-drawer ur-drawer" id="ur-drawer" data-close="<?= $closeUrl ?>"<?= ur_people_drawer_attrs($vm) ?> aria-labelledby="ur-drawer-title">
    <div class="sk-drawer-h">
      <h2 class="sk-drawer-t" id="ur-drawer-title"><?= $h($p['navn']) ?></h2>
      <a class="sk-btn sk-btn-ghost sk-btn-icon" href="<?= $closeUrl ?>" aria-label="<?= $t('2172|Luk') ?>" title="<?= $t('2172|Luk') ?>"><i class='bx bx-x'></i></a>
    </div>
	<?php ur_people_card_head($vm, $h, $t, $link); ?>
    <div class="sk-drawer-b ur-give" data-pane="login" role="tabpanel" aria-labelledby="ur-tab-login">
      <h3 class="ur-give-t"><?= $t('7773|Giv adgang') ?></h3>
      <p><?= $h(sprintf(findtekst('7774|%s har ikke noget login.', $vm['sprogId']), $p['navn'])) ?></p>
		<?php if ($p['left']) { ?>
      <p class="sk-muted"><?= $t('7790|Fratrådte kan ikke få login. Fjern Fratrådt under Stamdata først.') ?></p>
		<?php } elseif ($vm['canWrite']) { ?>
      <div class="ur-give-acts">
        <a class="sk-btn sk-btn-primary" href="<?= $link($new) ?>"><i class='bx bx-envelope'></i><?= $t('7759|Inviter som bruger') ?></a>
        <a class="sk-btn" href="<?= $link($new . '&mode=classic') ?>"><i class='bx bx-user-plus'></i><?= $t('7760|Opret login med adgangskode') ?></a>
      </div>
      <p class="sk-muted"><?= $t('7792|Der sendes og oprettes intet, før du trykker Send invitation eller Opret.') ?></p>
		<?php } ?>
    </div>
  </aside>
	<?php
}

/**
 * "Tilføj person": does the person get a login, and are they employed here? An employee is added with the employee section's own add
 * action (its permission and token checks); a login only opens usersRoles.php's new-login form. Only the choices the user may make.
 */
function ur_people_add_dialog(array $vm, callable $h, callable $t, callable $link): void
{
	if ($vm['tab'] !== 'users' || (!$vm['canWrite'] && empty($vm['canOrgWrite']))) {
		return;
	}
	$org = !empty($vm['canOrgWrite']);
	$users = $vm['canWrite'];
	?>
<div class="sk-modal" id="ur-padd" hidden>
  <div class="sk-dialog ur-padd" role="dialog" aria-modal="true" aria-labelledby="ur-padd-t">
    <h3 class="sk-dialog-t" id="ur-padd-t"><?= $t('7765|Tilføj person') ?></h3>
    <form method="post" action="settingsSection.php?s=organisation.employees" id="ur-padd-form" class="sk-dialog-b" autocomplete="off" data-invite="<?= $link('tab=users&bruger=0') ?>" data-classic="<?= $link('tab=users&bruger=0&mode=classic') ?>">
      <input type="hidden" name="action" value="run">
      <input type="hidden" name="key" value="organisation.employees.add">
      <input type="hidden" name="people" value="1">
      <input type="hidden" name="then" value="">
      <fieldset class="ur-padd-q">
        <legend><?= $t('7766|Skal personen kunne logge ind?') ?></legend>
		<?php if ($org) { ?>
        <label class="sk-check-lbl"><input class="sk-radio" type="radio" name="p_login" value="no" checked><?= $t('7767|Nej – kun stamkort (navn, initialer, ansættelse)') ?></label>
		<?php } ?>
		<?php if ($users) { ?>
        <label class="sk-check-lbl"><input class="sk-radio" type="radio" name="p_login" value="invite"<?= $org ? '' : ' checked' ?>><?= $t('7768|Ja – send en invitation på e-mail') ?></label>
        <label class="sk-check-lbl"><input class="sk-radio" type="radio" name="p_login" value="classic"><?= $t('7769|Ja – opret login med adgangskode') ?></label>
		<?php } ?>
      </fieldset>
		<?php if ($org && $users) { ?>
      <fieldset class="ur-padd-q" data-when="login" hidden>
        <legend><?= $t('7770|Er personen ansat i virksomheden?') ?></legend>
        <label class="sk-check-lbl"><input class="sk-radio" type="radio" name="p_emp" value="yes" checked><?= $t('7771|Ja – opret også et stamkort') ?></label>
        <label class="sk-check-lbl"><input class="sk-radio" type="radio" name="p_emp" value="no"><?= $t('7772|Nej – kun login, fx revisor, bogholder eller ekstern') ?></label>
      </fieldset>
		<?php } ?>
		<?php if ($org) { ?>
      <div class="ur-padd-fields" data-when="stamkort">
        <div class="sk-field"><label class="sk-field-lbl" for="ur-padd-navn"><?= $t('5531|Navn') ?></label><input class="sk-input" type="text" name="person[navn]" id="ur-padd-navn" maxlength="100" required></div>
        <div class="sk-fields-2">
          <div class="sk-field"><label class="sk-field-lbl" for="ur-padd-init"><?= $t('647|Initialer') ?></label><input class="sk-input" type="text" name="person[initialer]" id="ur-padd-init" maxlength="10"></div>
          <div class="sk-field"><label class="sk-field-lbl" for="ur-padd-mail"><?= $t('52|E-mail') ?></label><input class="sk-input" type="email" name="person[email]" id="ur-padd-mail" maxlength="200"></div>
        </div>
      </div>
		<?php } ?>
    </form>
    <div class="sk-dialog-f">
      <button type="button" class="sk-btn" data-padd-close><?= $t('5940|Annullér') ?></button>
      <button type="submit" class="sk-btn sk-btn-primary" form="ur-padd-form"><?= $t('1991|Fortsæt') ?></button>
    </div>
  </div>
</div>
	<?php
}

/**
 * The guard dialog of the card (unsaved changes in Stamdata or Login og rolle), the texts of javascript/usersPeople.js and its files.
 */
function ur_people_assets(array $vm, callable $t): void
{
	$cfg = array(
		'discardTitle' => findtekst(6144, $vm['sprogId']), 'discardBody' => findtekst(6145, $vm['sprogId']), 'discardVerb' => findtekst(6146, $vm['sprogId']),
	);
	if ($vm['charset'] !== 'UTF-8') {
		foreach ($cfg as $k => $v) {
			$cfg[$k] = mb_convert_encoding($v, 'UTF-8', $vm['charset']);
		}
	}
	?>
<div class="sk ur-pguard-root">
  <div class="sk-modal" id="ur-pguard" hidden>
    <div class="sk-dialog" role="dialog" aria-modal="true" aria-labelledby="ur-pguard-t">
      <h3 class="sk-dialog-t" id="ur-pguard-t"></h3>
      <p class="sk-dialog-b" id="ur-pguard-b"></p>
      <div class="sk-dialog-f"><button type="button" class="sk-btn" data-pguard-no><?= $t('5940|Annullér') ?></button><button type="button" class="sk-btn sk-btn-danger" data-pguard-yes></button></div>
    </div>
  </div>
</div>
<link rel="stylesheet" href="../css/usersPeople.css?v=20261102b">
<script>window.UR_PEOPLE = <?= json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="../javascript/usersPeople.js?v=20261102b"></script>
	<?php
}

/**
 * A message from the Stamdata tab handed to the list (an employee deleted there, or a refused form): shown once, then forgotten.
 *
 * @return array{type: string, text: string}|null
 */
function ur_people_flash(): ?array
{
	if (empty($_SESSION['people_flash']) || !is_array($_SESSION['people_flash'])) {
		return null;
	}
	$f = $_SESSION['people_flash'];
	unset($_SESSION['people_flash']);
	return array('type' => $f[0] === 'ok' ? 'ok' : 'err', 'text' => (string) $f[1]);
}
