<?php
// ---- includes/stdFunc/cookie.php --- lap 5.0.0 --- 2026.10.09 ---
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
// 20261009 Sawaneh saldi_setcookie(): every cookie gets SameSite=Lax, Secure on https and HttpOnly unless JavaScript reads
//                  it (settings redesign §7.4 item 5, §7.6 R11, C25). Loaded by online.php and std_func.php so it exists before login too.

if (!function_exists('saldi_setcookie')) {
	/**
	 * setcookie() with the flags the settings redesign asks for (§7.4 item 5, §7.6 R11, C25): SameSite=Lax, Secure on https,
	 * HttpOnly unless JavaScript reads the cookie. Same positional arguments as setcookie(), so calls swap 1:1.
	 */
	function saldi_setcookie($name, $value = '', $expire = 0, $path = '', $domain = '', $secure = null, $httponly = null) {
		static $jsRead = array('pos_menu_location', 'isSidebarOpen', 'saldi_theme', 'refresh_opener', 'autoExtract');
		$opts = array(
			'expires'  => (int) $expire,
			'path'     => (string) $path,
			'domain'   => (string) $domain,
			'secure'   => $secure === null ? !empty($_SERVER['HTTPS']) : (bool) $secure,
			'httponly' => $httponly === null ? !in_array((string) $name, $jsRead, true) : (bool) $httponly,
			'samesite' => 'Lax',
		);
		return setcookie((string) $name, (string) $value, $opts);
	}
}
