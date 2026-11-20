<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/createCustomer.php --- ver 5.1.0 --- 2026.11.20 ---
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

include_once(__DIR__ . "/../../includes/shopify/schema.php");

/** settings.var_grp of the form tokens that make a resent create form harmless. */
const POS_CUSTOMER_CREATE_GROUP = 'pos_customer_create';

/** Days a form token is kept; a form older than that is long gone from any till. */
const POS_CUSTOMER_CREATE_TOKEN_DAYS = 30;

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
	if (!shopify_schema_ready()) {
		return false;
	}
	require_once __DIR__ . '/../../includes/shopify/config.php';
	return shopify_shops('customers') !== array();
}

/** A fresh one-time token for a create form. */
function pos_customer_create_token() {
	return bin2hex(random_bytes(16));
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
 * @throws InvalidArgumentException for a missing name or group
 * @throws UnexpectedValueException for a malformed token
 */
function pos_create_customer(array $c, $token) {
	require_once __DIR__ . '/../../includes/shopify/config.php';
	$token = (string) $token;
	if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
		throw new UnexpectedValueException('the create form has no valid token');
	}
	$fields = array();
	foreach (POS_CUSTOMER_CREATE_WIDTHS as $column => $width) {
		$source = $column === 'tlf' ? 'phone' : $column;
		$fields[$column] = mb_substr(trim((string) ($c[$source] ?? '')), 0, $width, 'UTF-8');
	}
	if ($fields['firmanavn'] === '') {
		throw new InvalidArgumentException('a customer needs a name');
	}
	if ($fields['bynavn'] === '' && $fields['postnr'] !== '') {
		$fields['bynavn'] = mb_substr((string) bynavn($fields['postnr']), 0, POS_CUSTOMER_CREATE_WIDTHS['bynavn'], 'UTF-8');
	}
	$fields['oprettet'] = date('Y-m-d');
	$group = (int) ($c['grp'] ?? 0);
	if ($group <= 0) {
		throw new InvalidArgumentException('a customer needs a debtor group');
	}
	$terms = trim((string) ($c['betalingsbet'] ?? '')) ?: 'Kontant';
	$days = (int) ($c['betalingsdage'] ?? 0);

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
		shopify_db_rows('INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ($1, $2, $3, $4)',
			array($token, POS_CUSTOMER_CREATE_GROUP, (string) $id, date('Y-m-d H:i:s')));
		return array('konto_id' => $id, 'kontonr' => (string) $kontonr, 'created' => true);
	});
}
