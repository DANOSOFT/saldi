<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/easyUblWebhookKey.php --- ver 5.1.0 --- 2026-11-09 ---
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
// 20261109 CL/SZ SST-987 Created.
//                Each account gets a secret key that is part of the webhook URL sent to EasyUBL, so debitor/easyUBL.php can tell a call from EasyUBL from one made by anyone who knows a companyId.
//                Until webhookKeyRequired is set for the account, a call without the right key is only logged, so incoming invoices are not lost while accounts are registered again with the new URL.
//
// All functions work on the connection to the account's own database, as they read and write its settings table.

include_once(__DIR__ . '/../std_func.php');

if (!function_exists('easyubl_webhook_key')) {
	/**
	 * The account's webhook key, created and saved the first time it is needed.
	 *
	 * The key never changes once it is saved, because EasyUBL only learns a new one when the
	 * company is registered or updated with the URL again.
	 *
	 * @return string 64 hexadecimal characters.
	 */
	function easyubl_webhook_key() {
		$key = (string)get_settings_value('webhookKey', 'easyUBL', '');
		if ($key === '') {
			$key = bin2hex(random_bytes(32));
			update_settings_value('webhookKey', 'easyUBL', $key, 'Secret in the EasyUBL webhook URL (SST-987)');
		}
		return $key;
	}
}

if (!function_exists('easyubl_webhook_url')) {
	/**
	 * The webhook URL registered at EasyUBL for this account, with the account's key.
	 *
	 * @param string $domain      "https://" and the server name, for example "https://ssl8.saldi.dk".
	 * @param string $firstFolder The installation folder, for example "laja".
	 *
	 * @return string For example "https://ssl8.saldi.dk/laja/debitor/easyUBL.php?key=<64 hex>".
	 */
	function easyubl_webhook_url($domain, $firstFolder) {
		return "$domain/$firstFolder/debitor/easyUBL.php?key=" . easyubl_webhook_key();
	}
}

if (!function_exists('easyubl_webhook_redact')) {
	/**
	 * Text for a debug file under temp/ with the account's webhook key replaced, as temp/ is under
	 * the web root and the registration data and EasyUBL's reply can hold the keyed webhook URL.
	 *
	 * @param string $text The text about to be written.
	 *
	 * @return string The same text with every occurrence of the key replaced by "REDACTED".
	 */
	function easyubl_webhook_redact($text) {
		$key = (string)get_settings_value('webhookKey', 'easyUBL', '');
		return $key === '' ? (string)$text : str_replace($key, 'REDACTED', (string)$text);
	}
}

if (!function_exists('easyubl_webhook_key_check')) {
	/**
	 * Compares the key a webhook call came with to the account's saved key.
	 *
	 * Reading never creates a key: an account without one has not been registered with a keyed URL.
	 *
	 * @param mixed $givenKey The key from the request (?key=), or null when there is none.
	 *
	 * @return array{valid: bool, reason: string, required: bool}
	 *         valid    true only when the account has a key and the call came with exactly that key;
	 *         reason   'ok', 'no key in request', 'no key saved' or 'wrong key', for the log;
	 *         required true when webhookKeyRequired is '1', so a call that is not valid must be refused.
	 */
	function easyubl_webhook_key_check($givenKey) {
		$savedKey = (string)get_settings_value('webhookKey', 'easyUBL', '');
		$required = (string)get_settings_value('webhookKeyRequired', 'easyUBL', '') === '1';
		$givenKey = is_string($givenKey) ? $givenKey : '';

		if ($givenKey === '') {
			$reason = 'no key in request';
		} elseif ($savedKey === '') {
			$reason = 'no key saved';
		} elseif (!hash_equals($savedKey, $givenKey)) {
			$reason = 'wrong key';
		} else {
			$reason = 'ok';
		}
		return ['valid' => $reason === 'ok', 'reason' => $reason, 'required' => $required];
	}
}
