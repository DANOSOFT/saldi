<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/saldi_help_panel.php --- ver 5.1.1 --- 2026-11-09 ---
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
// 20261107 CL/ASR Created: the pilot and kill switch of the help panel (Hjælp variant B) and the recipient of "Skriv til os".
//                 SALDI_HELP_PANEL unset or pilot shows the panel only to tenants with the settings row help/panel = 'on'; on shows it to every tenant unless its row says 'off'; off or any other value keeps the old Assist menu.
//                 The recipient is SALDI_HELP_SUPPORT_TO, else $helpSupportTo from includes/connect.php, else none (the panel then shows a mail link instead of the form).
// 20261107 CL/ASR Review round 2: saldi_help_chrome_prefetch() reads the panel's chrome texts (topbar_help_host()) in one query into findtekst()'s session cache, so the first shell load after a login no longer runs two queries per text id.
// 20261108 CL/ASR Chrome texts 8711 and 8788-8792 for the ask block, the problem card and "Lær siden at kende" (spec §18).
// 20261108 CL/ASR Chrome text 8793 "Hjælp til siden" for the page help inside Saldi Assist (spec §19).
// 20261108 CL/ASR Chrome text 8794 "Tilbage til samtalen" for the page help inside Saldi Assist (spec §19.9).
// 20261108 ASR Chrome text 8795 "Ok" for the button of the Vis mig callout (spec §21).
// 20261109 CL/ASR Chrome texts 8825-8827 for the page's questions in Saldi Assist (spec §25).

/**
 * SALDI_HELP_PANEL: '' (unset) or 'pilot' = only tenants with help/panel = 'on' (the phase-1 default);
 * 'on' = every tenant unless its row says 'off'; 'off' or anything else = the old Assist menu.
 *
 * @param string      $mode           SALDI_HELP_PANEL, '' when it is not set
 * @param string|null $tenantSetting  the tenant's settings row help/panel, null when there is none
 */
function saldi_help_panel_enabled(string $mode, ?string $tenantSetting): bool
{
	if ($mode === '' || $mode === 'pilot') return $tenantSetting === 'on';
	if ($mode === 'on') return $tenantSetting !== 'off';
	return false;
}

/** Whether saldi_help_panel_enabled() needs the tenant's own row for this mode (no query when the panel is off anyway). */
function saldi_help_panel_needs_tenant(string $mode): bool
{
	return $mode === '' || $mode === 'on' || $mode === 'pilot';
}

/**
 * The tenant's own row (not a user's or a till's), as saldi_assist_guide_tenant_setting(): get_settings_value()
 * without a user also matches user rows. Runs on the tenant connection (after includes/online.php).
 *
 * @return string|null var_value, or null when the row does not exist
 */
function saldi_help_panel_tenant_setting(): ?string
{
	$row = db_fetch_array(db_select(
		"SELECT var_value FROM settings WHERE var_grp = 'help' AND var_name = 'panel'"
		. " AND COALESCE(user_id, 0) = 0 AND COALESCE(pos_id, 0) = 0 ORDER BY id DESC LIMIT 1",
		__FILE__ . ' linje ' . __LINE__));
	return $row ? (string) $row['var_value'] : null;
}

/**
 * Recipient of "Skriv til os": SALDI_HELP_SUPPORT_TO, else $helpSupportTo from includes/connect.php, else ''
 * (the form is off and the panel shows a mail link). Never $errorReportTo: a test server must not mail by accident.
 */
function saldi_help_support_to(): string
{
	$to = trim((string) getenv('SALDI_HELP_SUPPORT_TO'));
	if ($to === '' && isset($GLOBALS['helpSupportTo'])) $to = trim((string) $GLOBALS['helpSupportTo']);
	return filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : '';
}

/**
 * The numeric chrome text ids that topbar_help_host() reads (doc/hjaelp_saldi_ai.md §2.2, §8.1, §18, §19, §25): 8700–8760,
 * 8763, 8771–8777, 8788–8795, 8825–8827 and the reused 2564, 6024, 6455, 92, 5148, 636 and 641. tests/includes/saldiHelpPanel.test.php checks the
 * list against the findtekst() lines of topbar_help_host().
 *
 * @return array<int, int>
 */
function saldi_help_chrome_ids(): array
{
	return array_merge(range(8700, 8760), array(8763), range(8771, 8777), range(8788, 8795), range(8825, 8827), array(2564, 6024, 6455, 92, 5148, 636, 641));
}

/**
 * The usable rows of a tekster query, tekst_id => text: per id only its first row (the query orders by id) and only when that
 * text is not '' and not '-'. Everything else is left to findtekst(), which seeds a missing or empty row from tekster.csv and
 * shows '-' as an empty text. Pure.
 *
 * @param array<int, array<string, mixed>> $rows rows with tekst_id and tekst
 * @return array<string, string>
 */
function saldi_help_chrome_pick(array $rows): array
{
	$seen = array();
	$out = array();
	foreach ($rows as $r) {
		$id = isset($r['tekst_id']) ? trim((string) $r['tekst_id']) : '';
		if ($id === '' || !ctype_digit($id) || isset($seen[$id])) continue;
		$seen[$id] = true;
		$text = isset($r['tekst']) ? $r['tekst'] : null;
		if ($text === null || $text === '' || $text === '-') continue;
		$out[$id] = (string) $text;
	}
	return $out;
}

/**
 * Puts the chrome texts that the tenant's tekster table already holds into findtekst()'s session cache, in one query.
 * login.php starts a new session on every login, so without it the first shell load after a login runs two queries per
 * uncached id (the information_schema check and the tekster select), about 150 for topbar_help_host(). Ids that are
 * cached already, missing, empty or '-' are left to findtekst(). Does nothing on the master database (findtekst() reads
 * no tekster table there) or without the db functions.
 *
 * @param array<int, int|string> $ids     numeric text ids (saldi_help_chrome_ids(), never from the client)
 * @param int                    $sprogId the user's language: 1 Danish, 2 English, 3 Norwegian
 */
function saldi_help_chrome_prefetch(array $ids, int $sprogId): void
{
	global $db, $sqdb;
	if (!function_exists('findtekst_session_var') || !function_exists('db_select') || !function_exists('db_fetch_array')) return;
	if (isset($sqdb) && $db == $sqdb) return;
	$missing = array();
	foreach ($ids as $id) {
		$id = (int) $id;
		if ($id > 0 && !isset($_SESSION[findtekst_session_var((string) $id, $sprogId)])) $missing[] = "'" . $id . "'";
	}
	if (!$missing) return;
	$lang = ($sprogId < 1 || $sprogId > 3) ? 1 : $sprogId;   // as findtekst()
	$q = db_select("SELECT tekst_id, tekst FROM tekster WHERE sprog_id = '$lang' AND tekst_id IN (" . implode(',', $missing) . ") ORDER BY id",
		__FILE__ . ' linje ' . __LINE__);
	$rows = array();
	while ($q && ($r = db_fetch_array($q))) $rows[] = $r;
	foreach (saldi_help_chrome_pick($rows) as $id => $text) {
		$_SESSION[findtekst_session_var((string) $id, $sprogId)] = $text;   // the database encoding, as findtekst() caches it
	}
}
