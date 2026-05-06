<?php
// --- includes/stdFunc/getKontaktEmail.php --- ver 4.1.0 --- 2026-03-27 ---
// Retrieves email(s) from kontakt_emails table by konto_id and optional email_type.
// Falls back to adresser.email if no matching kontakt_emails record found.

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
		$email_type = db_escape_string(trim($email_type));
		$qtxt = "SELECT email FROM kontakt_emails WHERE konto_id = '$konto_id' AND email_type = '$email_type' ORDER BY id LIMIT 1";
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
		$email_type = db_escape_string(trim($email_type));
		$qtxt = "SELECT email FROM kontakt_emails WHERE konto_id = '$konto_id' AND email_type = '$email_type' ORDER BY id";
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
?>
