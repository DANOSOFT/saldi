<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/mainIncludes/topbar.php --- ver 5.1.1 --- 2026.11.14 ---
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
// 20260916 SS Topbar (variant A): user chip, fiscal-year switcher, who-is-online, help (guides) and notification bell shell.
//             Data + view for index/main.php.
// 20260922 SS Global cluster per the 2026-09-17 topbar spec (step 1a): language selector, SALDI Assist button, PoS shortcut, dashboard hide/edit + Print page in the chip, no hamburger/breadcrumb on desktop, mobile fallbacks in the dropdown.
// 20260927 SS Step 1b: cluster wrapper + move button (top/sidebar placement, spec 2.3); Who is online counts every session in the company, current user marked.
// 20260928 SS PoS shortcut shown only with the pos.kasse permission.
// 20260930 SS Dashboard items in the user menu are hidden, not greyed out, away from the dashboard (Adam 2026-09-30).
//             Who is online shown to users who may manage users (settings.users.manage).
// 20261005 SS Greeting, date and "opdateret" on the left on Oversigt only (topbar addendum 2026-09-30 §3).
// 20261005 SS Assist button opens a menu (topbar addendum 2026-10-05 §7): ask about the page, page tour, shortcuts, guide.
// 20261005 SS "+ Opret nyt regnskabsår" under Regnskabsår through the named route fiscal_year_create (addendum 2026-09-30 §4.1).
// 20261005 SS Global search (topbar addendum 2026-10-05 §6): magnifier first in the cluster, field and results panel.
// 20261005 SS Topbar addendum 2026-10-05: no sidebar placement (move button and cluster_placement gone).
// 20261004 SS Gear link next to the breadcrumb: the settings section that governs the page in the frame (settings redesign §8.11).
// 20261002 SS Breadcrumb element in the left side, filled by the shell on settings pages (settings redesign §8.0).
// 20261008 SS Sagsstyring in the chip menu, only for companies with the module (prototype_dashboard_tema v5).
// 20261031 CL/ASR MB-94: the fiscal-year switch offers only active_fiscal_years() (a year with box10 set is deleted).
// 20261103 CL/ASR QA: the gear's map is converted to UTF-8 and written as ASCII JSON, so json_encode() does not fail on latin-1 installations.
//                 "Hvem er online" lists only the company's own logins: revisor/support sessions, mySale customers and the anonymous booking session (all in the same online table) are left out, as master's count left out revisor sessions.
// 20261107 CL/ASR Hjælp variant B: the Saldi AI symbol in a 28 px disc with "Hjælp" replaces the Assist button and its menu where the help panel is on (topbar_help_symbol(), topbar_help_host()); elsewhere the Assist button stays.
// 20261107 CL/ASR Review round 2: topbar_help_host() reads the help panel's chrome texts in one query first (saldi_help_chrome_prefetch()), instead of two queries per text on the first shell load after a login.
// 20261108 CL/ASR topbar_help_host(): texts 8711 and 8788-8792 for the help panel's ask block, problem card and "Lær siden at kende" (spec §18).
// 20261108 CL/ASR topbar_help_host(): text 8793 "Hjælp til siden", the page help's button in Saldi Assist (spec §19).
// 20261108 CL/ASR topbar_help_host(): text 8794 "Tilbage til samtalen", the label back from the page help to the conversation in Saldi Assist (spec §19.9).
// 20261108 ASR topbar_help_host(): text 8795 "Ok", the button of the Vis mig callout instead of 5148 "Forstået" (spec §21).
// 20261108 ASR The Hjælp button's symbol carries data-saldi-assist-symbol: Saldi Assist's loader flies it into the chat and shows its states there.
// 20261108 ASR Saldi Assist in the shell (spec §23): with the help panel on, the header carries data-saldi-assist-topbar.
//              "+ Ny chat" (texts 8796, 8797) follows the Hjælp button, rendered hidden for the loader to bind and show.
// 20261108 ASR "+ Ny chat" shows a chat-bubble-with-plus icon below 1240 px, where its label is hidden (a bare "+" read as "create new").
// 20261109 CL/ASR topbar_help_host(): texts 8825-8827, the labels of the page's questions in Saldi Assist (spec §25).
// 20261113 CL/ASR The version and database number at the bottom of the user menu ($ctx appVersion/dbNo from index/main.php; was the sidebar's bottom line).
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
// 20261114 CL/ASR No "+ Ny chat": closing the chat ends its conversation and Hjælp opens a new one, and Hjælp says "Fortsæt samtalen" (9502) while one is minimised.
// 20261114 CL/ASR The PoS shortcut says Kassesystem (5606) next to its icon, as Hjælp does, so customers can find it (support's wish).
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
	$qtxt = "select brugernavn, logtime, revisor from online where db = '$db' and logtime > '$since' order by brugernavn";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$byName = array();
	while ($r = db_fetch_array($q)) {
		if (in_array($r['revisor'], array('t', true, '1', 1, 'on'), true)) {
			continue; // a revisor or support session, not one of the company's users
		}
		$name = trim((string) $r['brugernavn']);
		$byName[$name] = max(isset($byName[$name]) ? $byName[$name] : 0, (int) $r['logtime']);
	}
	$rows = array();
	foreach ($byName as $name => $logtime) {
		$rows[] = array('brugernavn' => $name, 'logtime' => $logtime);
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

/**
 * topbar_utf8() on every string of an array, for json_encode().
 *
 * @param mixed $v
 * @return mixed
 */
function topbar_utf8_deep($v)
{
	if (is_array($v)) {
		return array_map('topbar_utf8_deep', $v);
	}
	return is_string($v) ? topbar_utf8($v) : $v;
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
	// The years that may be switched to: open and not deleted (MB-94: a year with any box10 value is deleted).
	$openYears = array();
	foreach (active_fiscal_years() as $r) {
		$openYears[(string) $r['kodenr']] = true;
	}
	// Newest first: the active year is normally the latest, so it sits at the top of the list.
	$q = db_select("select kodenr, beskrivelse from grupper where art = 'RA' order by box2 desc, box1 desc", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$active = ((string) $r['kodenr'] === (string) $regnaar);
		if ($active) {
			$fiscalYear = (string) $r['beskrivelse'];
		}
		if (isset($openYears[(string) $r['kodenr']]) || $active) {
			$fiscalYears[] = array('kodenr' => (string) $r['kodenr'], 'label' => (string) $r['beskrivelse'], 'active' => $active);
		}
	}

	$isAdmin = !$revisor && (function_exists('perm_can') ? perm_can('settings.users.manage', 'read') : substr($rettigheder, 1, 1) === '1');
	$onlineUsers = array();
	if ($isAdmin || $revisor) {
		foreach ($onlineRows as $row) {
			// Only the company's own logins (mySale customers and the booking page also have sessions here).
			if ($row['brugernavn'] !== '' && isset($ansatByUser[$row['brugernavn']])) {
				$label = topbar_utf8($resolveName($row['brugernavn']));
				if ($row['brugernavn'] === $brugernavn) {
					$label .= ' (' . topbar_utf8(findtekst('5612|dig', $sprogId)) . ')';
				}
				$onlineUsers[] = $label;
			}
		}
		$onlineUsers = array_values(array_unique($onlineUsers));
	}

	// PoS shortcut (cluster icon): the company runs a cash register this
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
 * The Saldi AI symbol, byte-identical to the landing page's (landing-chat-wt/index.html:1122) except the gradient id,
 * which must be unique in the document. javascript/saldi-help.js clones this copy for the panel and gives each clone its own id.
 */
function topbar_help_symbol(string $gradientId): string
{
	$id = topbar_h($gradientId);
	return sprintf('<svg viewBox="6 14 34 42" aria-hidden="true" focusable="false"><defs><linearGradient id="sa-g-%s" gradientUnits="userSpaceOnUse" x1="10" y1="16" x2="38" y2="54"><stop offset="0" stop-color="#1A66B8"></stop><stop offset="1" stop-color="#7A2CBC"></stop></linearGradient></defs><circle class="sa-glow" cx="13.5" cy="28.8" r="11.4"></circle><circle class="sa-halo" cx="13.5" cy="28.8" r="8.6"></circle><circle class="sa-ring sa-ring--outer" cx="13.5" cy="28.8" r="11.2"></circle><circle class="sa-ring sa-ring--inner" cx="13.5" cy="28.8" r="8.4"></circle><path class="sa-track" d="M35.6 18.9 L17.9 37.8 C16.4 39.5 15.9 40.9 15.9 43.2 L15.9 44.6 C15.9 49 19.4 51 23.4 51 C27.6 51 30.9 49 30.9 46.4"></path><path class="sa-stroke" pathLength="100" stroke="url(#sa-g-%s)" d="M35.6 18.9 L17.9 37.8 C16.4 39.5 15.9 40.9 15.9 43.2 L15.9 44.6 C15.9 49 19.4 51 23.4 51 C27.6 51 30.9 49 30.9 46.4"></path><circle class="sa-dot" cx="13.5" cy="28.8" r="5.808" fill="url(#sa-g-%s)"></circle></svg>', $id, $id, $id);
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
  <header class="topbar" id="topbar"<?= empty($ctx['helpPanel']) ? '' : ' data-saldi-assist-topbar' ?>>
    <button type="button" class="topbar-menu-btn" aria-label="Menu" title="Menu" onclick="topbarMenu()"><i class='bx bx-menu'></i></button>
    <div class="topbar-hello" id="topbar-hello" data-name="<?= topbar_h((string) strtok((string) $ctx['name'], ' ')) ?>" data-lang="<?= topbar_h(strtolower((string) $lang['code']) === 'no' ? 'nb' : strtolower((string) $lang['code'])) ?>" data-txt="<?= topbar_h(json_encode(array(
		'night' => topbar_utf8(findtekst('6456|God nat', $sprogId)), 'morning' => topbar_utf8(findtekst('6457|Godmorgen', $sprogId)),
		'forenoon' => topbar_utf8(findtekst('6458|God formiddag', $sprogId)), 'afternoon' => topbar_utf8(findtekst('6459|God eftermiddag', $sprogId)),
		'evening' => topbar_utf8(findtekst('6460|God aften', $sprogId)), 'now' => topbar_utf8(findtekst('6461|opdateret for et øjeblik siden', $sprogId)),
		'min' => topbar_utf8(findtekst('6462|opdateret for %s min. siden', $sprogId)),
	), JSON_UNESCAPED_UNICODE)) ?>" hidden><b id="topbar-hello-greet"></b><span class="topbar-hello-line"><i class="topbar-hello-dot" aria-hidden="true"></i><span id="topbar-hello-date"></span> · <span id="topbar-hello-upd"></span></span></div>
    <nav class="topbar-crumb" id="topbar-crumb" aria-label="<?= $t('6015|Du er her') ?>" data-company="<?= topbar_h($ctx['company']) ?>" hidden></nav>
    <a class="topbar-gear" id="topbar-gear" href="#" title="<?= $t('6392|Indstillinger for denne side') ?>" aria-label="<?= $t('6392|Indstillinger for denne side') ?>" data-map="<?= topbar_h(json_encode(topbar_utf8_deep(function_exists('settings_context_links') ? settings_context_links((int) $ctx['langId']) : array()))) ?>" hidden><i class='bx bx-cog' aria-hidden="true"></i></a>
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
          <input type="hidden" name="return_hash" value=""><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES) ?>">
          <?php foreach ($ctx['languages'] as $id => $l) { ?>
          <button type="submit" name="language_id" value="<?= (int) $id ?>" class="topbar-pop-item<?= $id === $ctx['langId'] ? ' active' : '' ?>" role="menuitem"><span class="topbar-lang-code"><?= topbar_h($l['code']) ?></span><?= topbar_h($l['label']) ?><?php if ($id === $ctx['langId']) { ?><i class='bx bx-check topbar-pop-trail'></i><?php } ?></button>
          <?php } ?>
        </form>
      </div>
    </div>

    <?php if (empty($ctx['helpPanel'])) { ?>
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
    <?php } else { ?>
    <div class="topbar-item topbar-help-item">
      <button type="button" class="topbar-help" id="topbar-help-btn" data-txt-resume="<?= $t('9502|Fortsæt samtalen') ?>"
        aria-label="<?= $t('8700|Hjælp og Saldi AI') ?>" title="<?= $t('2564|Hjælp') ?> (?)"
        aria-controls="saldi-help" aria-expanded="false"
        onclick="if (window.SaldiHelp) { SaldiHelp.toggle({ source: 'topbar', trigger: this, keyboard: event.detail === 0 }); } else { topbarOpenAssist(); }">
        <span class="ai-avatar ai-avatar--28 ai-avatar--seat" data-here="true" data-ai-state="ready" data-saldi-assist-symbol aria-hidden="true"><?= topbar_help_symbol('top') ?></span>
        <span class="topbar-help-lbl"><?= $t('2564|Hjælp') ?></span>
      </button>
    </div>
    <?php } ?>

    <?php if ($ctx['posUrl'] !== '') { ?>
    <a class="topbar-icbtn topbar-pos topbar-desktop" href="<?= topbar_h($ctx['posUrl']) ?>" target="_top" title="<?= $t('5606|Kassesystem') ?>"><i class='bx bx-store-alt'></i><span class="topbar-pos-lbl"><?= $t('5606|Kassesystem') ?></span></a>
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
              <input type="hidden" name="return_hash" value=""><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES) ?>">
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
                <input type="hidden" name="return_hash" value=""><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES) ?>">
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
          <?php if (($ctx['appVersion'] ?? '') !== '') { ?>
          <div class="topbar-pop-sep"></div>
          <div class="topbar-pop-ver">Saldi <?= topbar_h($ctx['appVersion']) ?><?= ($ctx['dbNo'] ?? '') !== '' ? ' · DB ' . topbar_h($ctx['dbNo']) : '' ?></div>
          <?php } ?>
        </div>
      </div>
    </div>

    </div>
  </header>
	<?php
}

/**
 * The empty, hidden host of the help panel (inside .home-content, after the iframe); javascript/saldi-help.js builds
 * its content. data-txt holds every chrome text the panel uses, each a literal findtekst() call with the Danish of
 * importfiler/tekster.csv, so the tekster guardrail can compare them. data-signal stays 0 in phase 1: the
 * "har noget til dig" state of the symbol is off.
 */
function topbar_help_host(array $ctx, int $sprogId): void
{
	$lang = array(1 => 'da', 2 => 'en', 3 => 'no');
	$f = function (string $s): string { return topbar_utf8($s); };
	// One query for the texts the tenant already has, into findtekst()'s session cache (a login starts an empty session);
	// findtekst() below still reads, and seeds, the rest.
	if (function_exists('saldi_help_chrome_prefetch')) saldi_help_chrome_prefetch(saldi_help_chrome_ids(), $sprogId);
	$txt = array(
		'8700' => $f(findtekst('8700|Hjælp og Saldi AI', $sprogId)),
		'8701' => $f(findtekst('8701|Hjælp og Saldi AI, har et forslag til siden', $sprogId)),
		'8702' => $f(findtekst('8702|Søg i hjælpen, eller spørg Saldi AI', $sprogId)),
		'8703' => $f(findtekst('8703|Om siden', $sprogId)),
		'8704' => $f(findtekst('8704|Feltet du stod i: %s', $sprogId)),
		'8705' => $f(findtekst('8705|Spørg Saldi AI om feltet', $sprogId)),
		'8706' => $f(findtekst('8706|Sådan gør du', $sprogId)),
		'8707' => $f(findtekst('8707|Vis mig', $sprogId)),
		'8708' => $f(findtekst('8708|Vis mig: %s', $sprogId)),
		'8709' => $f(findtekst('8709|Rundvisning af siden (%s trin)', $sprogId)),
		'8710' => $f(findtekst('8710|Vis mig rundt, ét trin ad gangen', $sprogId)),
		'8712' => $f(findtekst('8712|Spørg Saldi AI: »%s«', $sprogId)),
		'8713' => $f(findtekst('8713|Spørg Saldi AI om %s', $sprogId)),
		'8714' => $f(findtekst('8714|Kontakt support', $sprogId)),
		'8715' => $f(findtekst('8715|Ring %s', $sprogId)),
		'8716' => $f(findtekst('8716|hverdage 9–16', $sprogId)),
		'8717' => $f(findtekst('8717|Åbent nu, til kl. %s', $sprogId)),
		'8718' => $f(findtekst('8718|Lukket, åbner kl. %s', $sprogId)),
		'8719' => $f(findtekst('8719|Lukket, åbner i morgen kl. %s', $sprogId)),
		'8720' => $f(findtekst('8720|Lukket, åbner mandag kl. %s', $sprogId)),
		'8721' => $f(findtekst('8721|Skriv til os', $sprogId)),
		'8722' => $f(findtekst('8722|Din besked', $sprogId)),
		'8723' => $f(findtekst('8723|Send med', $sprogId)),
		'8724' => $f(findtekst('8724|Sidens adresse', $sprogId)),
		'8725' => $f(findtekst('8725|Send', $sprogId)),
		'8726' => $f(findtekst('8726|Tak. Vi har fået din besked og svarer på mail, normalt samme dag.', $sprogId)),
		'8727' => $f(findtekst('8727|Beskeden blev ikke sendt. Prøv igen, eller ring til os.', $sprogId)),
		'8728' => $f(findtekst('8728|Tilbage til hjælpen', $sprogId)),
		'8729' => $f(findtekst('8729|Viser: %s', $sprogId)),
		'8730' => $f(findtekst('8730|Fastgør', $sprogId)),
		'8731' => $f(findtekst('8731|Løsn', $sprogId)),
		'8732' => $f(findtekst('8732|Hold hjælpen åben i højre side, også når du skifter side', $sprogId)),
		'8733' => $f(findtekst('8733|Løsn: hjælpen lukker, når du skifter side', $sprogId)),
		'8734' => $f(findtekst('8734|Vinduet er for smalt til at vise hjælpen ved siden af. Den ligger over siden.', $sprogId)),
		'8735' => $f(findtekst('8735|Luk hjælpen', $sprogId)),
		'8736' => $f(findtekst('8736|Hvad får Saldi AI at vide?', $sprogId)),
		'8737' => $f(findtekst('8737|Det spørgsmål, du sender, og hvilken side du står på. Saldi AI kan ikke se dine posteringer eller beløb.', $sprogId)),
		'8738' => $f(findtekst('8738|Saldi AI åbner i sit eget vindue med dit spørgsmål. Det er ikke sendt endnu. Send det dér, når du er klar.', $sprogId)),
		'8739' => $f(findtekst('8739|Saldi AI-vinduet er åbent. Skriv dit spørgsmål dér.', $sprogId)),
		'8740' => $f(findtekst('8740|Saldi AI kan ikke svare lige nu. Du kan skrive spørgsmålet til support i stedet.', $sprogId)),
		'8741' => $f(findtekst('8741|Skriv til support med spørgsmålet', $sprogId)),
		'8742' => $f(findtekst('8742|Her er knappen. Der sker ikke noget, før du selv klikker.', $sprogId)),
		'8743' => $f(findtekst('8743|Her er feltet.', $sprogId)),
		'8744' => $f(findtekst('8744|Her står det.', $sprogId)),
		'8745' => $f(findtekst('8745|Knappen ligger bag hjælpen.', $sprogId)),
		'8746' => $f(findtekst('8746|Feltet ligger bag hjælpen.', $sprogId)),
		'8747' => $f(findtekst('8747|Luk (Esc)', $sprogId)),
		'8748' => $f(findtekst('8748|Til denne side', $sprogId)),
		'8749' => $f(findtekst('8749|Åbn hjælpen (uden for felter)', $sprogId)),
		'8750' => $f(findtekst('8750|Åbn hjælpen, også i et felt', $sprogId)),
		'8751' => $f(findtekst('8751|Luk hjælpen og gå tilbage til feltet', $sprogId)),
		'8752' => $f(findtekst('8752|Søg i Saldi', $sprogId)),
		'8753' => $f(findtekst('8753|Gå et niveau tilbage', $sprogId)),
		'8754' => $f(findtekst('8754|Sidens egne genveje', $sprogId)),
		'8755' => $f(findtekst('8755|Hjælp, fastgjort', $sprogId)),
		'8756' => $f(findtekst('8756|Forklar mere om: %s', $sprogId)),
		'8757' => $f(findtekst('8757|Skriv til %s', $sprogId)),
		'8758' => $f(findtekst('8758|Det ligger bag hjælpen.', $sprogId)),
		'8759' => $f(findtekst('8759|Her er linjen.', $sprogId)),
		'8760' => $f(findtekst('8760|Rundvisning', $sprogId)),
		'8763' => $f(findtekst('8763|Spørg Saldi AI om dette', $sprogId)),
		'8771' => $f(findtekst('8771|Det spørgsmål, du sender, hvilken side du står på, og den gemte kladde eller faktura, du har åben. Saldi AI kan læse den, men ikke ændre den.', $sprogId)),
		'8772' => $f(findtekst('8772|Spørgsmålet sendes til den server, Saldi AI kører på.', $sprogId)),
		'8773' => $f(findtekst('8773|Luk hjælpen (træk ned)', $sprogId)),
		'8774' => $f(findtekst('8774|Taster', $sprogId)),
		'8775' => $f(findtekst('8775|Koder i felterne', $sprogId)),
		'8776' => $f(findtekst('8776|Overalt i Saldi', $sprogId)),
		'8777' => $f(findtekst('8777|Spørg Saldi AI', $sprogId)),
		'8711' => $f(findtekst('8711|Hvad kan jeg hjælpe med?', $sprogId)),
		'8788' => $f(findtekst('8788|Skriv dit spørgsmål med dine egne ord', $sprogId)),
		'8789' => $f(findtekst('8789|Du kan spørge om alt, også det, der ikke står herunder.', $sprogId)),
		'8790' => $f(findtekst('8790|Lær siden at kende', $sprogId)),
		'8791' => $f(findtekst('8791|Se her først', $sprogId)),
		'8792' => $f(findtekst('8792|Send spørgsmålet', $sprogId)),
		'8793' => $f(findtekst('8793|Hjælp til siden', $sprogId)),
		'8794' => $f(findtekst('8794|Tilbage til samtalen', $sprogId)),
		'8795' => $f(findtekst('8795|Ok', $sprogId)),
		'8825' => $f(findtekst('8825|Spørgsmål om %s', $sprogId)),
		'8826' => $f(findtekst('8826|Spørg Saldi AI om mere', $sprogId)),
		'8827' => $f(findtekst('8827|Flere spørgsmål om siden', $sprogId)),
		'2564' => $f(findtekst('2564|Hjælp', $sprogId)),
		'6024' => $f(findtekst('6024|Tastaturgenveje', $sprogId)),
		'6455' => $f(findtekst('6455|Rundvisning af siden', $sprogId)),
		'92' => $f(findtekst('92|Vejledning', $sprogId)),
		'5148' => $f(findtekst('5148|Forstået', $sprogId)),
		'636' => $f(findtekst('636|Vis alle', $sprogId)),
		'641' => $f(findtekst('641|Vis egne', $sprogId)),
	);
	print '<aside id="saldi-help" class="saldi-help hs-b hc-root" hidden tabindex="-1" aria-labelledby="saldi-help-title"'
		. ' data-lang="' . topbar_h($lang[(int) $ctx['langId']] ?? 'da') . '"'
		. ' data-support-send="' . (!empty($ctx['helpSupportSend']) ? '1' : '0') . '"'
		. ' data-records="' . (!empty($ctx['helpRecords']) ? '1' : '0') . '"'
		. ' data-signal="0"'   // the "har noget til dig" state stays off in phase 1
		. ' data-version="1"'
		. ' data-txt="' . topbar_h((string) json_encode($txt, JSON_UNESCAPED_UNICODE)) . '"></aside>' . "\n";
}
