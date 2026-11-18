<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/ebNames.php --- ver 5.1.1 --- 2026-11-18 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20261118 CL/LH Initial version. How a bank account's holder names and number are shown, with no other dependencies, so the bank pages (ebAccess.php) and the CSV import notice (ebCoverage.php) share them.

if (!function_exists('ebHolderName')) {

	/**
	 * Account holder names as the bank sends them, joined for reading: "Anders Madsen;Susan Madsen" becomes
	 * "Anders Madsen, Susan Madsen". Use it wherever bank_accounts.display_name is shown.
	 *
	 * @param string|null $displayName bank_accounts.display_name, in UTF-8 or the tenant's charset (only ';' is split on).
	 * @return string Plain text in the same charset ('' when the bank sent no name); escape when printing.
	 */
	function ebHolderName(?string $displayName): string
	{
		$names = array();
		foreach (explode(';', (string)$displayName) as $name) {
			$name = trim($name);
			if ($name !== '' && !in_array($name, $names, true)) {
				$names[] = $name;
			}
		}
		return implode(', ', $names);
	}

	/**
	 * The last four digits of a masked account identity, e.g. "DK61 **** **** 8028" gives "…8028".
	 *
	 * @param string|null $identity bank_accounts.display_identity.
	 * @return string Plain UTF-8 text; the identity itself when it does not end in four digits.
	 */
	function ebAccountLast4(?string $identity): string
	{
		$identity = trim((string)$identity);
		return preg_match('/(\d{4})$/', $identity, $m) ? "\u{2026}" . $m[1] : $identity;
	}
}
