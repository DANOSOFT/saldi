<?php
// --- includes/stdFunc/getKontaktEmail.php --- ver 5.1.1 --- 2026-11-26 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2008-2026 Danosoft ApS
// ----------------------------------------------------------------------
// Retrieves email(s) from kontakt_emails table by konto_id and optional email_type.
// Falls back to adresser.email if no matching kontakt_emails record found.
// 20260611 MJ Normalize email_type matching so purpose-specific emails are not skipped.
// 20261126 ASR Review GPT-R-9: getReminderEmails(), a reminder's recipients with the customer's reminder contacts as the reminder editor (debitor/rykker.php) merged them, shared by the editor, reminder creation and the automatic reminders' check.

/**
 * Get customer email by type from kontakt_emails table.
 * @param int $konto_id  Customer ID (adresser.id)
 * @param string $email_type  Email type: 'tilbud', 'ordre', 'faktura', 'kontoudtog', 'rykker', 'andet' (optional)
 * @return string  Email address or empty string
 */
function getKontaktEmail($konto_id, $email_type = '') {
	$konto_id = intval($konto_id);
	if (!$konto_id) return '';

	// If specific type requested, try that first
	if ($email_type) {
		$email_type = db_escape_string(strtolower(trim($email_type)));
		$qtxt = "SELECT email FROM kontakt_emails WHERE konto_id = '$konto_id' AND lower(trim(email_type)) = '$email_type' ORDER BY id LIMIT 1";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r && trim($r['email'])) return trim($r['email']);
	}

	// Fallback: first email of any type
	$qtxt = "SELECT email FROM kontakt_emails WHERE konto_id = '$konto_id' ORDER BY id LIMIT 1";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if ($r && trim($r['email'])) return trim($r['email']);

	// Final fallback: adresser.email
	$qtxt = "SELECT email FROM adresser WHERE id = '$konto_id'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if ($r && trim($r['email'])) return trim($r['email']);

	return '';
}

/**
 * Get all customer emails of a specific type from kontakt_emails table.
 * Returns semicolon-separated string of emails for the given type.
 * @param int $konto_id  Customer ID (adresser.id)
 * @param string $email_type  Email type filter (optional - returns all if empty)
 * @return string  Semicolon-separated email addresses
 */
function getAllKontaktEmails($konto_id, $email_type = '') {
	$konto_id = intval($konto_id);
	if (!$konto_id) return '';

	$emails = array();
	if ($email_type) {
		$email_type = db_escape_string(strtolower(trim($email_type)));
		$qtxt = "SELECT email FROM kontakt_emails WHERE konto_id = '$konto_id' AND lower(trim(email_type)) = '$email_type' ORDER BY id";
	} else {
		$qtxt = "SELECT email FROM kontakt_emails WHERE konto_id = '$konto_id' ORDER BY id";
	}
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (trim($r['email'])) $emails[] = trim($r['email']);
	}

	if (count($emails)) return implode(';', $emails);

	// Fallback to adresser.email
	$qtxt = "SELECT email FROM adresser WHERE id = '$konto_id'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if ($r && trim($r['email'])) return trim($r['email']);

	return '';
}

/**
 * The recipients of a reminder: its own address(es) and the customer's reminder contacts (kontakt_emails of type 'rykker',
 * or the customer's own address when there is none, as getAllKontaktEmails() falls back), each once, ';'-separated.
 * The reminder editor (debitor/rykker.php) shows and saves this list, and reminder creation (includes/reminderCreate.php)
 * stores it on a new reminder, so a reminder sent without being opened (the automatic reminders, Udskriv under Åbne
 * rykkere) goes to the same recipients. Without contacts, or without the table, the address is returned as it is.
 *
 * @param int $konto_id Customer ID (adresser.id)
 * @param string $email The reminder's address(es), ';' or ',' separated
 * @return string  Semicolon-separated email addresses
 */
function getReminderEmails($konto_id, $email) {
	$email = (string)$email;
	if (function_exists('tbl_exists') && !tbl_exists('kontakt_emails')) return $email;
	$contacts = getAllKontaktEmails($konto_id, 'rykker');
	if (!$contacts) return $email;
	$all = array_filter(array_map('trim', preg_split('/[;,]/', $email . ';' . $contacts)));
	return implode(';', array_unique($all));
}
?>
