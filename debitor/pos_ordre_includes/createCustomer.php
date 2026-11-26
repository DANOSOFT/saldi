<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/createCustomer.php --- ver 5.1.1 --- 2026.11.26 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261120 CL/MJ SD-844 K31: a new customer created at the till, with no Shopify call.
//                The number comes from the shared Shopify customer allocator, so it never collides with a customer Shopify creates.
//                A resent form (a refresh, a double click) gives back the customer it created the first time.
// 20261123 CL/MJ SD-844 K31: a form token is kept in the session that was given the form, so a form from another site creates nothing.
// 20261124 CL/MJ SD-844 K31: a form resent after its customer was deleted creates a new one instead of failing on the old token row.
//                The payment terms are cut to the column's 15 characters.
// 20261126 CL/MJ SD-844 K31: pos_customer_create_shopify_mode() answers false when the Shopify tables can't be read, so the customer lookup still opens.
// 20261126 CL/MJ SD-844 K31: pos_customer_create_alert_script(), the cashier's alert through jsString().
// 20261126 CL/MJ SD-844 K31: a phone the Shopify export would drop (not 8 digits and without a country code) is refused, so it can't pass the pickup and delivery contact check and reach Shopify with no contact.
// 20261126 CL/MJ SD-844 K31: the phone is checked both as stored and as the sale holds it, cut to ordrer.phone's 50 characters (Sol).
// 20261126 CL/MJ SD-844 K31: only the form's payment terms are stored (opdater_konto() puts them into SQL unescaped), the group must be an active debtor group of the year, and text is cut in the tenant's encoding (Hugin, Saul).
// 20261126 CL/MJ SD-844 K31: a debtor group stored as zero-padded text ('01') is found (Sol).
// 20261126 CL/MJ SD-844 K31: text fields lose a trailing backslash (injecttjek() in opdater_konto() could log the cashier out), and betalingsdage is kept within 0-999 (Hugin).

include_once(__DIR__ . "/../../includes/shopify/schema.php");

/** settings.var_grp of the form tokens that make a resent create form harmless. */
const POS_CUSTOMER_CREATE_GROUP = 'pos_customer_create';

/** Days a form token is kept; a form older than that is long gone from any till. */
const POS_CUSTOMER_CREATE_TOKEN_DAYS = 30;

/** The payment terms the till's create form offers (accountLookup.php); nothing else is stored. */
const POS_CUSTOMER_CREATE_TERMS = array('Kontant', 'Netto', 'Lb. md.', 'Forud');

/** InvalidArgumentException code for a phone the Shopify export would drop (pos_create_customer()). */
const POS_CUSTOMER_CREATE_BAD_PHONE = 2;

/** adresser column widths on a fresh install; a longer value is cut to fit rather than refused. */
const POS_CUSTOMER_CREATE_WIDTHS = array('firmanavn' => 90, 'addr1' => 60, 'addr2' => 60, 'postnr' => 10, 'bynavn' => 60,
	'email' => 60, 'tlf' => 60, 'kontakt' => 60, 'cvrnr' => 20, 'ean' => 20);

/**
 * True when customers created at the till belong to Shopify: the Shopify schema is installed and
 * at least one shop has the customer flow switched on. Everywhere else (MySQL, an install without
 * Shopify, a shop without the customer flow) the till keeps the create form it always had.
 *
 * @return bool
 */
function pos_customer_create_shopify_mode() {
	// a database error here falls back to the old form, so the customer lookup itself keeps working
	try {
		if (!shopify_schema_ready()) {
			return false;
		}
		require_once __DIR__ . '/../../includes/shopify/config.php';
		return shopify_shops('customers') !== array();
	} catch (Throwable $e) {
		error_log('pos_customer_create_shopify_mode: ' . $e->getMessage());
		return false;
	}
}

/**
 * The script that tells the cashier $message and returns to the sale. The text comes from findtekst() in
 * the database's encoding; jsString() makes it a valid string literal on a LATIN9 tenant too, where
 * json_encode() of the raw text fails and the alert would be empty.
 */
function pos_customer_create_alert_script(string $message, string $back): string {
	return "<script>alert(" . jsString(array($message)) . "); window.location.href = " . json_encode($back, JSON_HEX_TAG) . ";</script>\n";
}

/** How many create-form tokens a session keeps; the newest ones, as a till has few lookups open at once. */
const POS_CUSTOMER_CREATE_SESSION_TOKENS = 20;

/** A fresh one-time token for a create form, recorded in the session the form is shown in. */
function pos_customer_create_token() {
	$token = bin2hex(random_bytes(16));
	$tokens = isset($_SESSION['pos_create_tokens']) && is_array($_SESSION['pos_create_tokens']) ? $_SESSION['pos_create_tokens'] : array();
	$tokens[] = $token;
	$_SESSION['pos_create_tokens'] = array_slice($tokens, -POS_CUSTOMER_CREATE_SESSION_TOKENS);
	return $token;
}

/**
 * Whether this session was given the token with a create form. A form posted from another site
 * carries a token it made up, so it creates nothing; the same form sent again still matches.
 *
 * @param mixed $token
 * @return bool
 */
function pos_customer_create_token_issued($token) {
	return is_string($token) && isset($_SESSION['pos_create_tokens']) && is_array($_SESSION['pos_create_tokens'])
		&& in_array($token, $_SESSION['pos_create_tokens'], true);
}

/**
 * Creates a debtor from the till's create form (K31), in one transaction under the debtor-number
 * lock: the number from shopify_customer_number(), the debtor through create_debtor() in headless
 * mode, and the form's token, so that the same form sent again finds the debtor it already made
 * instead of creating a second one. No Shopify call: the store-order export creates the customer
 * in Shopify when it sends the sale.
 *
 * @param array<string, mixed> $c Form fields: firmanavn (required), addr1, addr2, postnr, bynavn,
 *                                 email, phone, kontakt, cvrnr, ean, grp, betalingsbet, betalingsdage.
 * @param string $token The form's token from pos_customer_create_token().
 * @return array{konto_id: int, kontonr: string, created: bool}
 * @throws InvalidArgumentException for a missing name or group, or (code POS_CUSTOMER_CREATE_BAD_PHONE) a
 *                                  phone the Shopify export would drop
 * @throws UnexpectedValueException for a malformed token, or payment terms the form doesn't offer
 */
function pos_create_customer(array $c, $token) {
	require_once __DIR__ . '/../../includes/shopify/config.php';
	$token = (string) $token;
	if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
		throw new UnexpectedValueException('the create form has no valid token');
	}
	// The form's text comes in the tenant's encoding (ISO-8859-15 on a LATIN9 tenant), so it is cut in that
	// encoding to fit the columns.
	global $db_encode, $regnaar;
	$encoding = ($db_encode ?? 'UTF8') === 'UTF8' ? 'UTF-8' : 'ISO-8859-15';
	$fields = array();
	foreach (POS_CUSTOMER_CREATE_WIDTHS as $column => $width) {
		$source = $column === 'tlf' ? 'phone' : $column;
		// No trailing backslash: opdater_konto() copies these fields through db_modify(), whose injection check
		// (injecttjek()) reads a quote after a backslash as escaped and can then log the cashier out.
		$fields[$column] = rtrim(mb_substr(rtrim(trim((string) ($c[$source] ?? '')), '\\'), 0, $width, $encoding), '\\');
	}
	if ($fields['firmanavn'] === '') {
		throw new InvalidArgumentException('a customer needs a name');
	}
	if ($fields['bynavn'] === '' && $fields['postnr'] !== '') {
		$fields['bynavn'] = mb_substr((string) bynavn($fields['postnr']), 0, POS_CUSTOMER_CREATE_WIDTHS['bynavn'], $encoding);
	}
	if ($fields['tlf'] !== '') {
		// The sale goes to Shopify with this phone only when the export can make it E.164 (8 digits, taken as
		// Danish, or a number with its country code). A phone it would drop would pass the till's pickup and
		// delivery contact check (K61) and still reach Shopify with no contact, so it is refused here.
		// Both copies must be usable: the customer export reads the phone as stored, and a sale holds at most
		// its first 50 characters (ordrer.phone, opdater_konto() in saleHeaderFunctions.php, which cuts in UTF-8,
		// so the same cut is checked here; a phone is digits and signs, the same in both encodings).
		require_once __DIR__ . '/../../includes/shopify/services/customersOut.php';
		if (shopify_customer_export_phone($fields['tlf'], '') === ''
			|| shopify_customer_export_phone(mb_substr($fields['tlf'], 0, 50, 'UTF-8'), '') === '') {
			throw new InvalidArgumentException('the phone cannot be sent to Shopify', POS_CUSTOMER_CREATE_BAD_PHONE);
		}
	}
	$fields['oprettet'] = date('Y-m-d');
	$group = (int) ($c['grp'] ?? 0);
	if ($group <= 0) {
		throw new InvalidArgumentException('a customer needs a debtor group');
	}
	// The group must be one the form offers: an active debtor group of this fiscal year (accountLookup.php).
	// kodenr is an integer on newer tenants and text on older ones (possibly zero-padded, '01'), so it is
	// compared without leading zeros; inaktiv may not exist yet.
	$known = shopify_db_rows("SELECT 1 FROM grupper g WHERE g.art = 'DG' AND ltrim(btrim(g.kodenr::text), '0') = $1"
		. ((int) $regnaar > 0 ? ' AND g.fiscal_year = ' . (int) $regnaar : '')
		. " AND coalesce((to_jsonb(g) ->> 'inaktiv')::boolean, false) = false LIMIT 1", array((string) $group));
	if (!$known) {
		throw new InvalidArgumentException('a customer needs an active debtor group');
	}
	// Only the form's own terms: opdater_konto() copies adresser.betalingsbet into the sale's SQL unescaped
	// (saleHeaderFunctions.php), so a posted value of any other text is refused, not stored.
	$terms = trim((string) ($c['betalingsbet'] ?? '')) ?: 'Kontant';
	if (!in_array($terms, POS_CUSTOMER_CREATE_TERMS, true)) {
		throw new UnexpectedValueException('the create form sent payment terms it does not offer');
	}
	$days = max(0, min(999, (int) ($c['betalingsdage'] ?? 0)));   // days of credit; nothing outside a real range reaches the column

	return shopify_db_transaction(function () use ($fields, $group, $terms, $days, $token) {
		// the lock first: a second copy of the form waits here until the first has committed its token
		debtor_number_lock();
		$seen = shopify_db_rows("SELECT a.id, a.kontonr FROM adresser a WHERE a.id = (SELECT CASE WHEN s.var_value ~ '^[0-9]{1,9}$' THEN CAST(s.var_value AS integer) END
			FROM settings s WHERE s.var_grp = $1 AND s.var_name = $2 LIMIT 1)", array(POS_CUSTOMER_CREATE_GROUP, $token));
		if ($seen) {
			return array('konto_id' => (int) $seen[0]['id'], 'kontonr' => (string) $seen[0]['kontonr'], 'created' => false);
		}
		shopify_db_rows('DELETE FROM settings WHERE var_grp = $1 AND var_description < $2',
			array(POS_CUSTOMER_CREATE_GROUP, date('Y-m-d H:i:s', time() - POS_CUSTOMER_CREATE_TOKEN_DAYS * 86400)));

		$kontonr = shopify_customer_number();
		// the seller's text is bound below, never passed through db_modify(), whose injection check
		// misreads a value that ends in a backslash (as includes/shopify/services/customers.php)
		$id = (int) create_debtor($kontonr, '', '', '', '', '', '', '', '', $group, '', $terms, $days, '', true);
		$set = array();
		$params = array();
		foreach ($fields as $column => $value) {
			$params[] = $value;
			$set[] = "$column = \$" . count($params);
		}
		$params[] = $id;
		shopify_db_rows('UPDATE adresser SET ' . implode(', ', $set) . ' WHERE id = $' . count($params), $params);
		// a token whose customer was deleted since is replaced, not inserted twice (settings is unique per group and name)
		shopify_db_rows('DELETE FROM settings WHERE var_grp = $1 AND var_name = $2', array(POS_CUSTOMER_CREATE_GROUP, $token));
		shopify_db_rows('INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ($1, $2, $3, $4)',
			array($token, POS_CUSTOMER_CREATE_GROUP, (string) $id, date('Y-m-d H:i:s')));
		return array('konto_id' => $id, 'kontonr' => (string) $kontonr, 'created' => true);
	});
}
