<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/mainIncludes/topbar.php --- lap 5.0.0 --- 2026.09.16 ---
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
// 20260916 Sawaneh Topbar (variant A): user chip, fiscal-year switcher, who-is-online,
//                  help (guides) and notification bell shell. Data + view for index/main.php.
// 20260922 Sawaneh Global cluster per the 2026-09-17 topbar spec (step 1a): language selector,
//                  SALDI Assist button, PoS shortcut, dashboard hide/edit + Print page in the chip,
//                  no hamburger/breadcrumb on desktop, mobile fallbacks in the dropdown.
// 20260927 Sawaneh Step 1b: cluster wrapper + move button (top/sidebar placement, spec 2.3);
//                  Who is online counts every session in the company, current user marked.
// 20260928 Sawaneh PoS shortcut shown only with the pos.kasse permission.
// 20260930 Sawaneh Dashboard items in the user menu are hidden, not greyed out, away from the dashboard (Adam 2026-09-30).
//                  Who is online shown to users who may manage users (settings.users.manage).
// 20261005 Sawaneh Greeting, date and "opdateret" on the left on Oversigt only (topbar addendum 2026-09-30 §3).
// 20261005 Sawaneh Assist button opens a menu (topbar addendum 2026-10-05 §7): ask about the page, page tour, shortcuts, guide.
// 20261005 Sawaneh "+ Opret nyt regnskabsår" under Regnskabsår through the named route fiscal_year_create (addendum 2026-09-30 §4.1).
// 20261005 Sawaneh Global search (topbar addendum 2026-10-05 §6): magnifier first in the cluster, field and results panel.
// 20261005 Sawaneh Topbar addendum 2026-10-05: no sidebar placement (move button and cluster_placement gone).
// 20261004 Sawaneh Gear link next to the breadcrumb: the settings section that governs the page in the frame (settings redesign §8.11).
// 20261002 Sawaneh Breadcrumb element in the left side, filled by the shell on settings pages (settings redesign §8.0).
// 20261008 Sawaneh Tema (Lys / Mørk / System) as a segment in the chip menu, prototype_dashboard_tema v5.
// 20261008 Sawaneh Sagsstyring in the chip menu, only for companies with the module (prototype_dashboard_tema v5).

/**
 * Sessions in the master `online` table that belong to the caller's company and
 * were active within the last hour. Must run BEFORE includes/online.php switches
 * the connection to the company database (the `online` table lives in the master db).
 *
 * @return array<int, array{brugernavn: string, logtime: int}>
 */
function topbar_online_rows(string $sessionId): array
{
	$sessionId = db_escape_string($sessionId);
	$r = db_fetch_array(db_select("select db from online where session_id = '$sessionId' order by logtime desc limit 1", __FILE__ . " linje " . __LINE__));
	if (!$r || empty($r['db'])) {
		return array();
	}
	$db = db_escape_string($r['db']);
	$since = (int) date('U') - 3600;
	$qtxt = "select brugernavn, max(logtime) as logtime from online where db = '$db' and logtime > '$since' group by brugernavn order by brugernavn";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$rows = array();
	while ($r = db_fetch_array($q)) {
		$rows[] = array('brugernavn' => trim((string) $r['brugernavn']), 'logtime' => (int) $r['logtime']);
	}
	return $rows;
}

/**
 * Company databases may still be ISO-8859-1; the shell is a UTF-8 document.
 */
function topbar_utf8(?string $s): string
{
	global $db_encode;
	$s = (string) $s;
	if (isset($db_encode) && $db_encode != 'UTF8' && $s !== '') {
		$s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
	}
	return $s;
}

function topbar_initials(string $displayName, string $initialer, string $username): string
{
	$initialer = trim($initialer);
	if ($initialer !== '') {
		return mb_strtoupper(mb_substr($initialer, 0, 3));
	}
	$words = preg_split('/\s+/', trim($displayName));
	$words = array_values(array_filter($words, function ($w) { return $w !== ''; }));
	if (count($words) >= 2) {
		return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
	}
	$base = $displayName !== '' ? $displayName : $username;
	return mb_strtoupper(mb_substr($base, 0, 2));
}

/**
 * Avatar colour derived from the user id so a user keeps the same colour everywhere.
 *
 * @return array{bg: string, fg: string}
 */
function topbar_avatar_color(int $userId): array
{
	$palette = array(
		array('bg' => '#d9e7ff', 'fg' => '#114691'),
		array('bg' => '#dcf3e6', 'fg' => '#137a45'),
		array('bg' => '#fde9d0', 'fg' => '#a35a00'),
		array('bg' => '#ecdff8', 'fg' => '#5b2d91'),
		array('bg' => '#fde0e0', 'fg' => '#a92727'),
		array('bg' => '#d8f1f5', 'fg' => '#0e6b7c'),
		array('bg' => '#f5e6d3', 'fg' => '#7a4a12'),
		array('bg' => '#e4e8f0', 'fg' => '#2f3b52'),
	);
	return $palette[abs($userId) % count($palette)];
}

/**
 * Human-readable role: the assigned role when one exists, else derived from the
 * legacy string (Indstillinger bit = administrator), auditor sessions as Revisor.
 */
function topbar_role_title(string $rettigheder, $revisor, int $brugerId, int $sprogId): string
{
	if ($revisor) {
		return findtekst('2562|Revisor', $sprogId);
	}
	if (function_exists('perm_tables_ready') && perm_tables_ready() && $brugerId > 0) {
		$r = db_fetch_array(db_select("select role_id from brugere where id = $brugerId", __FILE__ . " linje " . __LINE__));
		if ($r && (int) $r['role_id'] > 0) {
			foreach (perm_roles() as $role) {
				if ($role['id'] === (int) $r['role_id']) {
					return perm_role_name($role, $sprogId);
				}
			}
		}
	}
	if (substr($rettigheder, 1, 1) === '1') {
		return findtekst('330|Administrator', $sprogId);
	}
	return findtekst('990|Bruger', $sprogId);
}

/**
 * Languages as tekster.csv defines them (header row: id, Dansk, English, Norsk).
 *
 * @return array<int, array{code: string, label: string}>
 */
function topbar_languages(): array
{
	$codes = array(1 => 'DA', 2 => 'EN', 3 => 'NO');
	$labels = array(1 => 'Dansk', 2 => 'English', 3 => 'Norsk');
	$fp = @fopen(__DIR__ . "/../../importfiler/tekster.csv", "r");
	if ($fp) {
		$header = explode("\t", trim((string) fgets($fp)));
		fclose($fp);
		for ($i = 1; $i <= 3; $i++) {
			if (isset($header[$i]) && trim($header[$i]) !== '') {
				$labels[$i] = trim($header[$i]);
			}
		}
	}
	$out = array();
	foreach ($codes as $id => $code) {
		$out[$id] = array('code' => $code, 'label' => $labels[$id]);
	}
	return $out;
}

/**
 * Everything the topbar view needs, gathered in one place (company connection active).
 *
 * @param array<int, array{brugernavn: string, logtime: int}> $onlineRows From topbar_online_rows().
 * @return array<string, mixed>
 */
function topbar_context(array $onlineRows, string $brugernavn, int $brugerId, string $rettigheder, $revisor, $regnaar, int $sprogId, string $regnskab): array
{
	$name = $brugernavn;
	$email = '';
	$initialer = '';
	$ansatByUser = array();

	// select * : older company databases may lack email/initialer, and a missing
	// column must not take the whole shell down.
	$q = db_select("select * from brugere", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ansatByUser[trim((string) $r['brugernavn'])] = array(
			'ansat_id' => isset($r['ansat_id']) ? (int) $r['ansat_id'] : 0,
			'email'    => isset($r['email']) ? trim((string) $r['email']) : '',
		);
	}
	$ansatte = array();
	$q = db_select("select * from ansatte", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ansatte[(int) $r['id']] = $r + array('navn' => '', 'email' => '', 'initialer' => '');
	}

	$resolveName = function (string $user) use ($ansatByUser, $ansatte): string {
		$ansatId = isset($ansatByUser[$user]) ? $ansatByUser[$user]['ansat_id'] : 0;
		if ($ansatId && isset($ansatte[$ansatId]) && trim((string) $ansatte[$ansatId]['navn']) !== '') {
			return trim((string) $ansatte[$ansatId]['navn']);
		}
		return $user;
	};

	if (!$revisor) {
		$name = $resolveName($brugernavn);
		$ansatId = isset($ansatByUser[$brugernavn]) ? $ansatByUser[$brugernavn]['ansat_id'] : 0;
		$email = isset($ansatByUser[$brugernavn]) ? $ansatByUser[$brugernavn]['email'] : '';
		if ($ansatId && isset($ansatte[$ansatId])) {
			$initialer = (string) $ansatte[$ansatId]['initialer'];
			if ($email === '') {
				$email = trim((string) $ansatte[$ansatId]['email']);
			}
		}
	}

	$company = $regnskab;
	if ($r = db_fetch_array(db_select("select firmanavn from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__))) {
		if (trim((string) $r['firmanavn']) !== '') {
			$company = trim((string) $r['firmanavn']);
		}
	}

	$fiscalYears = array();
	$fiscalYear = '';
	// Newest first: the active year is normally the latest, so it sits at the top of the list.
	$q = db_select("select kodenr, beskrivelse, box5 from grupper where art = 'RA' order by box2 desc, box1 desc", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$active = ((string) $r['kodenr'] === (string) $regnaar);
		if ($active) {
			$fiscalYear = (string) $r['beskrivelse'];
		}
		if ($r['box5'] === 'on' || $active) {
			$fiscalYears[] = array('kodenr' => (string) $r['kodenr'], 'label' => (string) $r['beskrivelse'], 'active' => $active);
		}
	}

	$isAdmin = !$revisor && (function_exists('perm_can') ? perm_can('settings.users.manage', 'read') : substr($rettigheder, 1, 1) === '1');
	$onlineUsers = array();
	if ($isAdmin || $revisor) {
		foreach ($onlineRows as $row) {
			if ($row['brugernavn'] !== '') {
				$label = topbar_utf8($resolveName($row['brugernavn']));
				if ($row['brugernavn'] === $brugernavn) {
					$label .= ' (' . topbar_utf8(findtekst('5612|dig', $sprogId)) . ')';
				}
				$onlineUsers[] = $label;
			}
		}
		$onlineUsers = array_values(array_unique($onlineUsers));
	}

	// PoS shortcut (cluster icon + sidebar entry): the company runs a cash register this
	// fiscal year and the user's role grants Kassesystem.
	$posUrl = '';
	$sagerUrl = '';
	$regnaarSql = db_escape_string((string) $regnaar);
	if (db_fetch_array(db_select("select id from grupper where art = 'POS' and box1 >= '1' and fiscal_year = '$regnaarSql'", __FILE__ . " linje " . __LINE__))) {
		$posAllowed = function_exists('perm_can') ? perm_can('pos.kasse', 'read') : (substr($rettigheder, 5, 1) >= '1');
		if ($posAllowed) {
			$posUrl = '../debitor/pos_ordre.php';
		}
	} elseif (db_fetch_array(db_select("select id from settings where var_name = 'orderXpress' and var_value = 'on'", __FILE__ . " linje " . __LINE__))) {
		$sagerUrl = '../sager/sager.php';
	}

	$dashHidden = false;
	if (function_exists('get_settings_value')) {
		$dashHidden = ((string) get_settings_value('hide_dash', 'dashboard', '0', $brugerId) === '1');
	}

	$languages = topbar_languages();
	$langId = isset($languages[$sprogId]) ? $sprogId : 1;

	return array(
		'name'        => topbar_utf8($name),
		'username'    => topbar_utf8($brugernavn),
		'email'       => topbar_utf8($email),
		'initials'    => topbar_initials(topbar_utf8($name), topbar_utf8($initialer), topbar_utf8($brugernavn)),
		'avatar'      => topbar_avatar_color($brugerId),
		'company'     => topbar_utf8($company),
		'role'        => topbar_utf8(topbar_role_title($rettigheder, $revisor, $brugerId, $sprogId)),
		'isAdmin'     => $isAdmin || (bool) $revisor,
		'fiscalYear'  => topbar_utf8($fiscalYear),
		'fiscalYears' => array_map(function ($y) { $y['label'] = topbar_utf8($y['label']); return $y; }, $fiscalYears),
		'onlineUsers' => $onlineUsers,
		'languages'   => $languages,
		'langId'      => $langId,
		'posUrl'      => $posUrl,
		'sagerUrl'    => $sagerUrl,
		'canCreateYear' => !$revisor && (function_exists('perm_can') ? perm_can('settings.company', 'write') : substr($rettigheder, 1, 1) === '1'),
		'dashHidden'  => $dashHidden,
		'unread'      => 0,
	);
}

/**
 * Named routes of the top bar (topbar addendum 2026-09-30 §4.1): one place to change when a page moves -
 * "create fiscal year" points at the old fiscal-year card until settings G1.2 is live.
 */
function topbar_route(string $name): string
{
	$routes = array('fiscal_year_create' => '/systemdata/settingsSection.php?s=company.fiscal_years#create');
	return isset($routes[$name]) ? $routes[$name] : '/index/dashboard.php';
}

function topbar_h(?string $s): string
{
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * SALDI Assist icon: chat bubble with two sparkles (spec 2.1).
 */
function topbar_assist_icon(): string
{
	return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
		. '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.2 3.4c-.5.4-1.3.05-1.3-.6V16A2.5 2.5 0 0 1 4 13.5z"/>'
		. '<path d="M11.5 6.8l.7 1.9 1.9.7-1.9.7-.7 1.9-.7-1.9-1.9-.7 1.9-.7z" fill="currentColor" stroke="none"/>'
		. '<path d="M15.6 10.3l.4 1.1 1.1.4-1.1.4-.4 1.1-.4-1.1-1.1-.4 1.1-.4z" fill="currentColor" stroke="none"/>'
		. '</svg>';
}

/**
 * The global cluster in topbar mode: [mobile hamburger] · empty middle · language ·
 * Assist · PoS · bell · user chip. Dropdown panels are siblings of their buttons so
 * they overlay the content iframe.
 */
function topbar_render(array $ctx, int $sprogId): void
{
	$t = function (string $text) use ($sprogId): string {
		return topbar_h(topbar_utf8(findtekst($text, $sprogId)));
	};
	$avatarStyle = 'background:' . topbar_h($ctx['avatar']['bg']) . ';color:' . topbar_h($ctx['avatar']['fg']);
	$lang = $ctx['languages'][$ctx['langId']];
	?>
  <header class="topbar" id="topbar">
    <button type="button" class="topbar-menu-btn" aria-label="Menu" title="Menu" onclick="topbarMenu()"><i class='bx bx-menu'></i></button>
    <div class="topbar-hello" id="topbar-hello" data-name="<?= topbar_h((string) strtok((string) $ctx['name'], ' ')) ?>" data-lang="<?= topbar_h(strtolower((string) $lang['code']) === 'no' ? 'nb' : strtolower((string) $lang['code'])) ?>" data-txt="<?= topbar_h(json_encode(array(
		'night' => topbar_utf8(findtekst('6456|God nat', $sprogId)), 'morning' => topbar_utf8(findtekst('6457|Godmorgen', $sprogId)),
		'forenoon' => topbar_utf8(findtekst('6458|God formiddag', $sprogId)), 'afternoon' => topbar_utf8(findtekst('6459|God eftermiddag', $sprogId)),
		'evening' => topbar_utf8(findtekst('6460|God aften', $sprogId)), 'now' => topbar_utf8(findtekst('6461|opdateret for et øjeblik siden', $sprogId)),
		'min' => topbar_utf8(findtekst('6462|opdateret for %s min. siden', $sprogId)),
	), JSON_UNESCAPED_UNICODE)) ?>" hidden><b id="topbar-hello-greet"></b><span class="topbar-hello-line"><i class="topbar-hello-dot" aria-hidden="true"></i><span id="topbar-hello-date"></span> · <span id="topbar-hello-upd"></span></span></div>
    <nav class="topbar-crumb" id="topbar-crumb" aria-label="<?= $t('6015|Du er her') ?>" data-company="<?= topbar_h($ctx['company']) ?>" hidden></nav>
    <a class="topbar-gear" id="topbar-gear" href="#" title="<?= $t('6392|Indstillinger for denne side') ?>" aria-label="<?= $t('6392|Indstillinger for denne side') ?>" data-map="<?= topbar_h(json_encode(function_exists('settings_context_links') ? settings_context_links((int) $ctx['langId']) : array(), JSON_UNESCAPED_UNICODE)) ?>" hidden><i class='bx bx-cog' aria-hidden="true"></i></a>
    <span class="topbar-spacer"></span>
    <div class="topbar-cluster" id="topbar-cluster">

    <div class="topbar-search" id="topbar-search" data-txt="<?= topbar_h(json_encode(array(
		'recent' => topbar_utf8(findtekst('6445|Senest besøgt', $sprogId)), 'pages' => topbar_utf8(findtekst('6446|Sider', $sprogId)),
		'settings' => topbar_utf8(findtekst('122|Indstillinger', $sprogId)), 'none' => topbar_utf8(findtekst('6451|Ingen resultater', $sprogId)),
	), JSON_UNESCAPED_UNICODE)) ?>">
      <button type="button" class="topbar-icbtn" id="topbar-search-btn" title="<?= $t('6443|Søg i Saldi') ?> (Ctrl+K)" aria-label="<?= $t('6443|Søg i Saldi') ?>" aria-expanded="false" aria-controls="topbar-search-res" onclick="topbarSearchToggle(event)"><i class='bx bx-search'></i></button>
      <input type="search" class="topbar-search-in" id="topbar-search-in" placeholder="<?= $t('6444|Søg efter sider, kunder, bilag, varer …') ?>" aria-label="<?= $t('6443|Søg i Saldi') ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="topbar-search-res" aria-autocomplete="list" tabindex="-1">
      <div class="topbar-pop topbar-search-res" id="topbar-search-res" role="listbox" aria-label="<?= $t('6443|Søg i Saldi') ?>"></div>
    </div>

    <div class="topbar-item topbar-desktop">
      <button type="button" class="topbar-lang" id="topbar-lang-btn" title="<?= $t('801|Sprog') ?>" aria-haspopup="true" aria-expanded="false" aria-controls="topbar-lang-pop" onclick="topbarToggle(event, 'topbar-lang-pop')"><i class='bx bx-globe'></i><span><?= topbar_h($lang['code']) ?></span><i class='bx bx-chevron-down topbar-chev'></i></button>
      <div class="topbar-pop topbar-pop-lang" id="topbar-lang-pop" role="menu">
        <form method="post" action="topbarAction.php" onsubmit="return topbarSubmitReturn(this)">
          <input type="hidden" name="action" value="language">
          <input type="hidden" name="return_hash" value="">
          <?php foreach ($ctx['languages'] as $id => $l) { ?>
          <button type="submit" name="language_id" value="<?= (int) $id ?>" class="topbar-pop-item<?= $id === $ctx['langId'] ? ' active' : '' ?>" role="menuitem"><span class="topbar-lang-code"><?= topbar_h($l['code']) ?></span><?= topbar_h($l['label']) ?><?php if ($id === $ctx['langId']) { ?><i class='bx bx-check topbar-pop-trail'></i><?php } ?></button>
          <?php } ?>
        </form>
      </div>
    </div>

    <div class="topbar-item">
      <button type="button" class="topbar-icbtn topbar-assist" id="topbar-assist-btn" title="SALDI Assist" aria-haspopup="true" aria-expanded="false" aria-controls="topbar-assist-pop" onclick="topbarAssistMenu(event)"><?= topbar_assist_icon() ?></button>
      <div class="topbar-pop topbar-pop-assist" id="topbar-assist-pop" role="menu" data-ask="<?= $t('6453|Spørg Assist om %s') ?>" data-ask-plain="<?= $t('6454|Spørg Assist') ?>">
        <div class="topbar-pop-body">
          <button type="button" class="topbar-pop-item" role="menuitem" id="topbar-assist-ask" onclick="topbarAssistAsk()"><?= topbar_assist_icon() ?><span><?= $t('6454|Spørg Assist') ?></span></button>
          <button type="button" class="topbar-pop-item" role="menuitem" id="topbar-assist-tour" onclick="topbarAssistRun('tour')" hidden><i class='bx bx-map-alt'></i><?= $t('6455|Rundvisning af siden') ?></button>
          <button type="button" class="topbar-pop-item" role="menuitem" id="topbar-assist-shortcuts" onclick="topbarAssistRun('shortcuts')" hidden><i class='bx bx-command'></i><?= $t('6024|Tastaturgenveje') ?><small>?</small></button>
          <button type="button" class="topbar-pop-item" role="menuitem" id="topbar-assist-guide" onclick="topbarAssistRun('guide')" hidden><i class='bx bx-book-open'></i><?= $t('92|Vejledning') ?></button>
        </div>
      </div>
    </div>

    <?php if ($ctx['posUrl'] !== '') { ?>
    <a class="topbar-icbtn topbar-desktop" href="<?= topbar_h($ctx['posUrl']) ?>" target="_top" title="<?= $t('5606|Kassesystem') ?>"><i class='bx bx-store-alt'></i></a>
    <?php } ?>

    <div class="topbar-item">
      <button type="button" class="topbar-icbtn" id="topbar-bell-btn" title="<?= $t('5502|Notifikationer') ?>" aria-haspopup="true" aria-expanded="false" aria-controls="topbar-bell-pop" onclick="topbarToggle(event, 'topbar-bell-pop')"><i class='bx bx-bell'></i><?php if ($ctx['unread'] > 0) { ?><span class="topbar-badge"><?= (int) $ctx['unread'] ?></span><?php } ?></button>
      <div class="topbar-pop topbar-pop-bell" id="topbar-bell-pop" role="dialog" aria-label="<?= $t('5502|Notifikationer') ?>" data-empty="<?= $t('5503|Ingen notifikationer endnu') ?>">
        <div class="topbar-notif-head"><b><?= $t('5502|Notifikationer') ?></b><button type="button" id="topbar-notif-all" onclick="topbarNotifRead('all', '')"><?= $t('5634|Markér alle som læst') ?></button></div>
        <div class="topbar-notif-list" id="topbar-notif-list"><div class="topbar-empty"><i class='bx bx-bell-off'></i><span><?= $t('5503|Ingen notifikationer endnu') ?></span></div></div>
      </div>
    </div>

    <span class="topbar-sep" aria-hidden="true"></span>
    <div class="topbar-item">
      <button type="button" class="topbar-chip" id="topbar-user-btn" aria-haspopup="true" aria-expanded="false" aria-controls="topbar-user-pop" onclick="topbarToggle(event, 'topbar-user-pop')" title="<?= topbar_h($ctx['name']) ?> · <?= topbar_h($ctx['company']) ?>">
        <span class="topbar-avatar" style="<?= $avatarStyle ?>"><?= topbar_h($ctx['initials']) ?></span>
        <span class="topbar-who"><span class="topbar-name"><?= topbar_h($ctx['name']) ?></span><span class="topbar-company"><?= topbar_h($ctx['company']) ?></span></span>
        <i class='bx bx-chevron-down topbar-chev'></i>
      </button>
      <div class="topbar-pop topbar-pop-user" id="topbar-user-pop" role="menu">
        <div class="topbar-pop-head">
          <span class="topbar-avatar topbar-avatar-lg" style="<?= $avatarStyle ?>"><?= topbar_h($ctx['initials']) ?></span>
          <div class="topbar-pop-headtxt">
            <div class="topbar-pop-name"><?= topbar_h($ctx['name']) ?></div>
            <div class="topbar-pop-mail"><?= $ctx['email'] !== '' ? topbar_h($ctx['email']) : topbar_h($ctx['username']) ?></div>
            <span class="topbar-role"><?= topbar_h($ctx['role']) ?></span>
          </div>
        </div>
        <div class="topbar-pop-body">
          <a class="topbar-pop-item" role="menuitem" href="#" onclick="topbarCloseAll(); update_iframe('/systemdata/personalSettings.php'); return false;"><i class='bx bx-cog'></i><?= $t('5500|Personlige indstillinger') ?></a>

          <?php if (count($ctx['fiscalYears']) > 0) { ?>
          <button type="button" class="topbar-pop-item" role="menuitem" aria-expanded="false" onclick="topbarToggleSub(event, 'topbar-years')"><i class='bx bx-calendar'></i><?= $t('778|Regnskabsår') ?><small><?= topbar_h($ctx['fiscalYear']) ?> <i class='bx bx-chevron-down'></i></small></button>
          <div class="topbar-pop-sub" id="topbar-years">
            <form method="post" action="topbarAction.php" id="topbar-year-form" onsubmit="return topbarSubmitReturn(this)">
              <input type="hidden" name="action" value="fiscal_year">
              <input type="hidden" name="return_hash" value="">
              <?php foreach ($ctx['fiscalYears'] as $y) { ?>
              <button type="submit" name="year" value="<?= topbar_h($y['kodenr']) ?>" class="topbar-year<?= $y['active'] ? ' active' : '' ?>"<?= $y['active'] ? ' disabled aria-current="true"' : '' ?>><i class='bx <?= $y['active'] ? 'bx-check-circle' : 'bx-calendar-event' ?>'></i><span><?= topbar_h($y['label']) ?></span><?php if ($y['active']) { ?><small><?= $t('5534|Aktivt') ?></small><?php } ?></button>
              <?php } ?>
            </form>
            <?php if (!empty($ctx['canCreateYear'])) { ?>
            <div class="topbar-pop-sep"></div>
            <button type="button" class="topbar-year topbar-year-new" onclick="topbarCloseAll(); update_iframe('<?= topbar_h(topbar_route('fiscal_year_create')) ?>')"><i class='bx bx-plus'></i><?= $t('508|Opret nyt regnskabsår') ?></button>
            <?php } ?>
          </div>
          <?php } ?>

          <?php $uiThemeNow = isset($GLOBALS['uiTheme']) ? (string) $GLOBALS['uiTheme'] : 'light'; ?>
          <div class="topbar-pop-row topbar-theme">
            <span class="topbar-pop-lab"><i class='bx bx-palette'></i><?= $t('1075|Tema') ?></span>
            <div class="topbar-seg" role="group" aria-label="<?= $t('1075|Tema') ?>">
              <button type="button" data-theme-opt="light" aria-pressed="<?= $uiThemeNow === 'light' ? 'true' : 'false' ?>" onclick="topbarThemeSet('light')"><i class='bx bx-sun'></i><?= $t('6917|Lys') ?></button>
              <button type="button" data-theme-opt="dark" aria-pressed="<?= $uiThemeNow === 'dark' ? 'true' : 'false' ?>" onclick="topbarThemeSet('dark')"><i class='bx bx-moon'></i><?= $t('6918|Mørk') ?></button>
              <button type="button" data-theme-opt="system" aria-pressed="<?= $uiThemeNow === 'system' ? 'true' : 'false' ?>" onclick="topbarThemeSet('system')"><i class='bx bx-desktop'></i><?= $t('2377|System') ?></button>
            </div>
          </div>

          <div class="topbar-pop-section topbar-dash" hidden><?= $t('2224|Oversigt') ?></div>
          <button type="button" class="topbar-pop-item topbar-dash" role="menuitem" data-dash-hide="1" onclick="topbarDashHide()" hidden><i class='bx <?= $ctx['dashHidden'] ? 'bx-show' : 'bx-hide' ?>'></i><span class="topbar-dash-hide-label"><?= $ctx['dashHidden'] ? $t('5604|Vis oversigt') : $t('5603|Skjul oversigt') ?></span></button>
          <?php if (!$ctx['dashHidden']) { ?>
          <button type="button" class="topbar-pop-item topbar-dash" role="menuitem" onclick="topbarDashEdit()" hidden><i class='bx bx-edit-alt'></i><?= $t('5605|Rediger oversigt') ?></button>
          <?php } ?>

          <?php if ($ctx['isAdmin']) { ?>
          <button type="button" class="topbar-pop-item" role="menuitem" aria-expanded="false" onclick="topbarToggleSub(event, 'topbar-online')"><i class='bx bx-group'></i><?= $t('5501|Hvem er online') ?><small><?= count($ctx['onlineUsers']) ?> <i class='bx bx-chevron-down'></i></small></button>
          <div class="topbar-pop-sub" id="topbar-online">
            <?php foreach ($ctx['onlineUsers'] as $u) { ?>
            <div class="topbar-online-user"><span class="topbar-dot"></span><?= topbar_h($u) ?></div>
            <?php } ?>
          </div>
          <?php } ?>

          <button type="button" class="topbar-pop-item" role="menuitem" onclick="topbarPrint()"><i class='bx bx-printer'></i><?= $t('5602|Print side') ?></button>
          <?php if ($ctx['sagerUrl'] !== '') { ?>
          <div class="topbar-pop-sep"></div>
          <a class="topbar-pop-item" role="menuitem" href="<?= topbar_h($ctx['sagerUrl']) ?>" target="_top"><i class='bx bx-briefcase'></i><?= $t('5607|Sagsstyring') ?></a>
          <?php } ?>

          <div class="topbar-mobile">
            <div class="topbar-pop-sep"></div>
            <button type="button" class="topbar-pop-item" role="menuitem" aria-expanded="false" onclick="topbarToggleSub(event, 'topbar-lang-sub')"><i class='bx bx-globe'></i><?= $t('801|Sprog') ?><small><?= topbar_h($lang['code']) ?> <i class='bx bx-chevron-down'></i></small></button>
            <div class="topbar-pop-sub" id="topbar-lang-sub">
              <form method="post" action="topbarAction.php" onsubmit="return topbarSubmitReturn(this)">
                <input type="hidden" name="action" value="language">
                <input type="hidden" name="return_hash" value="">
                <?php foreach ($ctx['languages'] as $id => $l) { ?>
                <button type="submit" name="language_id" value="<?= (int) $id ?>" class="topbar-year<?= $id === $ctx['langId'] ? ' active' : '' ?>"<?= $id === $ctx['langId'] ? ' disabled' : '' ?>><i class='bx <?= $id === $ctx['langId'] ? 'bx-check-circle' : 'bx-globe' ?>'></i><span><?= topbar_h($l['label']) ?></span></button>
                <?php } ?>
              </form>
            </div>
            <?php if ($ctx['posUrl'] !== '') { ?>
            <a class="topbar-pop-item" role="menuitem" href="<?= topbar_h($ctx['posUrl']) ?>" target="_top"><i class='bx bx-store-alt'></i><?= $t('5606|Kassesystem') ?></a>
            <?php } ?>
          </div>

          <div class="topbar-pop-sep"></div>
          <a class="topbar-pop-item topbar-pop-out" role="menuitem" href="#" onclick="redirect_uri('/index/logud.php'); return false;"><i class='bx bx-log-out'></i><?= $t('93|Log ud') ?></a>
        </div>
      </div>
    </div>

    </div>
  </header>
	<?php
}
