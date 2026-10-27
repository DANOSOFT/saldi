<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/shortcutProfile.php --- ver 5.0.0 --- 2026-10-04 ---
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
// 20261004 CL/SZ SD-726 Created: the user's shortcut profile ("e-conomic-genvejsprofil", release plan 2.4), shared by the journal and the document pool.
//                The first choice is what Ctrl+↓ / Ctrl+↑ do: "lines" moves to the line below / above, "save" saves and goes to the next / previous.
//                A choice is stored in the journal's gear box options (grupper art 'KASKL', box3) as "sc.<choice>=<value>", only when it is not the default.
//                A further shortcut choice is one more entry in kkShortcutChoices().

if (!function_exists('kkShortcutChoices')) {
	/**
	 * The shortcut choices and their values; the first value is the default.
	 *
	 * @return array<string, string[]>
	 */
	function kkShortcutChoices() {
		return array(
			'ctrl_arrow' => array('lines', 'save'),
		);
	}
}

if (!function_exists('kkShortcutToken')) {
	/**
	 * Whether a gear box option is a valid stored shortcut choice ("sc.ctrl_arrow=save"). A default value is not stored.
	 *
	 * @param string $token
	 * @return bool
	 */
	function kkShortcutToken($token) {
		if (!preg_match('/^sc\.([a-z_]+)=([a-z_]+)$/', (string)$token, $m)) return false;
		$choices = kkShortcutChoices();
		return isset($choices[$m[1]]) && $m[2] !== $choices[$m[1]][0] && in_array($m[2], $choices[$m[1]], true);
	}
}

if (!function_exists('kkShortcutProfile')) {
	/**
	 * The profile from the gear box options: every choice, with its default where nothing valid is stored.
	 *
	 * @param string|null $box3 The comma separated options.
	 * @return array<string, string>
	 */
	function kkShortcutProfile($box3) {
		$profile = array();
		foreach (kkShortcutChoices() as $name => $values) $profile[$name] = $values[0];
		foreach (explode(',', (string)$box3) as $token) {
			$token = trim($token);
			if (!kkShortcutToken($token)) continue;
			list($name, $value) = explode('=', substr($token, 3), 2);
			$profile[$name] = $value;
		}
		return $profile;
	}
}

if (!function_exists('kkShortcutUserProfile')) {
	/**
	 * The user's profile. Users without stored options, and sessions without a user, get the defaults.
	 *
	 * @param int|null $userId
	 * @return array<string, string>
	 */
	function kkShortcutUserProfile($userId) {
		if ($userId === null || (int)$userId === 0) return kkShortcutProfile('');
		$r = db_fetch_array(db_select("select box3 from grupper where art = 'KASKL' and kode = '1' and kodenr = '" . (int)$userId . "'", __FILE__ . " linje " . __LINE__));
		return kkShortcutProfile($r ? $r['box3'] : '');
	}
}
