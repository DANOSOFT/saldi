<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/customers.php --- ver 5.1.0 --- 2026.11.09
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
// Inbound customers (WP08-1, K28-K30, K32): one debtor per Shopify customer and shop, matched on
// the customer GID only, never on phone or email. A new debtor gets its number from
// shopify_customer_number() and is created by create_debtor() in headless mode. Shopify's E.164
// phone is kept in mobile; tlf gets it once, at creation, so a phone search finds the debtor,
// and is never overwritten after that. An older or unchanged event changes nothing.
//
// 20261101 CL/SS SD-792: created (WP08-1).
// 20261108 CL/SS SD-803: an update of an exported debtor's customer leaves its name and contact, unless the address has a company.
// 20261109 CL/SS SD-803 review: and leaves an email field with several addresses when the customer has the first of them.
//                Adopting an export whose customer has a phone resolves the export's phone info row.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../links.php';
require_once __DIR__ . '/../errors.php';
require_once __DIR__ . '/../events.php';

/** Tenant settings (group shopify) for a new debtor: group, payment terms and days. */
const SHOPIFY_CUSTOMER_GROUP = 'customer_group';
const SHOPIFY_CUSTOMER_TERMS = 'customer_payment_terms';
const SHOPIFY_CUSTOMER_DAYS = 'customer_payment_days';

/** Topics that carry a whole customer; the others under CUSTOMERS_ (delete, enable, ...) do not. */
const SHOPIFY_CUSTOMER_TOPICS = array('CUSTOMERS_CREATE', 'CUSTOMERS_UPDATE');

/** Widths of the adresser columns on a fresh install (includes/opretfunc.php). */
const SHOPIFY_CUSTOMER_WIDTHS = array('firmanavn' => 90, 'kontakt' => 60, 'addr1' => 60, 'addr2' => 60, 'postnr' => 10,
	'bynavn' => 60, 'land' => 60, 'mobile' => 15);

/** adresser.email on a fresh install. A longer email is not stored (a cut one is another address) and is listed. */
const SHOPIFY_CUSTOMER_EMAIL_WIDTH = 60;

/**
 * The debtor fields of a Shopify customer (webhook body, REST shape): a company is the name with
 * the person as contact, else the person's name ('' when it has neither, see ensure_customer()).
 * Names and addresses are cut to the column widths.
 *
 * @param array<string, mixed> $customer
 * @return array<string, string> firmanavn, kontakt, addr1, addr2, postnr, bynavn, land, email, mobile
 */
function shopify_customer_fields(array $customer): array {
	$address = shopify_customer_address($customer);
	$text = fn($v): string => trim(is_scalar($v) ? (string)$v : '');
	$person = $text($text($customer['first_name'] ?? '') . ' ' . $text($customer['last_name'] ?? ''));
	if ($person === '') {
		$person = $text($address['name'] ?? '');
	}
	$company = $text($address['company'] ?? '');
	$email = $text($customer['email'] ?? '');
	$mobile = shopify_customer_phone($text($customer['phone'] ?? ''));
	$fields = array(
		'firmanavn' => $company !== '' ? $company : $person,
		'kontakt' => $company !== '' ? $person : '',
		'addr1' => $text($address['address1'] ?? ''),
		'addr2' => $text($address['address2'] ?? ''),
		'postnr' => $text($address['zip'] ?? ''),
		'bynavn' => $text($address['city'] ?? ''),
		'land' => $text($address['country'] ?? ''),
		'email' => $email,
		'mobile' => $mobile,
	);
	foreach (SHOPIFY_CUSTOMER_WIDTHS as $column => $width) {
		$fields[$column] = mb_substr($fields[$column], 0, $width);
	}
	return $fields;
}

/** The customer's default address, else its first; empty when it has none. */
function shopify_customer_address(array $customer): array {
	$address = $customer['default_address'] ?? ($customer['addresses'][0] ?? array());
	return is_array($address) ? $address : array();
}

/** The addresses of a debtor's email field, which may hold several separated by ; or , (as reminders and invoices split it). */
function shopify_customer_emails(string $field): array {
	return array_values(array_filter(array_map('trim', preg_split('/[;,]/', $field)), fn(string $e): bool => $e !== ''));
}

/** An E.164 phone ('+4512345678') as stored in mobile (15 wide: a 15-digit number loses its +); '' when not E.164. */
function shopify_customer_phone(string $phone): string {
	$phone = preg_replace('/[\s().-]/', '', $phone);
	if (!preg_match('/^\+[1-9][0-9]{6,14}$/', $phone)) {
		return '';
	}
	return strlen($phone) > 15 ? substr($phone, 1) : $phone;
}

/** Hash of the debtor fields, so an event that changes none of them writes nothing. */
function shopify_customer_hash(array $fields): string {
	return sha1(json_encode($fields, JSON_UNESCAPED_UNICODE));
}

/**
 * The debtor of a Shopify customer in a shop, created when it has none, its fields updated when
 * they changed. Runs in the caller's transaction and serializes per customer, so two events of
 * one new customer create one debtor. An event older than the last one applied changes nothing
 * on the debtor; a debtor deleted in Saldi is created again, also from an older event, since the
 * caller always gets one.
 * Before creating, an outbound create of a Saldi debtor (shopify_customer_exports, still open)
 * whose correlation tag the customer carries is adopted: the debtor is linked, not created.
 *
 * @param array<string, mixed> $customer webhook body (REST shape)
 * @return int adresser.id
 */
function ensure_customer(int $shopId, array $customer): int {
	if (pg_transaction_status(shopify_db_connection()) !== PGSQL_TRANSACTION_INTRANS) {
		throw new ShopifyDbMisuse('ensure_customer() runs in the caller\'s transaction: call it inside shopify_db_transaction()');
	}
	if (!function_exists('create_debtor')) {
		require_once __DIR__ . '/../../std_func.php';
	}
	$gid = shopify_gid((string)($customer['admin_graphql_api_id'] ?? $customer['id'] ?? ''), 'Customer');
	shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_customer:$shopId:$gid"));
	$fields = shopify_customer_fields($customer);
	if ($fields['bynavn'] === '' && $fields['postnr'] !== '' && strtoupper((string)(shopify_customer_address($customer)['country_code'] ?? '')) === 'DK'
		&& is_file('../importfiler/postnr.csv')) {
		// bynavn() reads Danish postcodes from that path, relative to tools/ in the worker
		$fields['bynavn'] = mb_substr((string)bynavn($fields['postnr']), 0, SHOPIFY_CUSTOMER_WIDTHS['bynavn']);
	}
	$hash = shopify_customer_hash($fields);
	$updatedAt = shopify_customer_time($customer['updated_at'] ?? null);
	$source = array('source_hash' => $hash) + ($updatedAt === null ? array() : array('source_updated_at' => $updatedAt));

	$link = shopify_customer_link($shopId, $gid);
	$older = $link !== null && $updatedAt !== null && $link['source_updated_at'] !== null && $updatedAt < $link['source_updated_at'];
	$stored = $link === null ? array() : shopify_db_rows("SELECT a.kontakt, a.email, EXISTS (SELECT 1 FROM shopify_customer_exports e WHERE e.shop_id = $2 AND e.adresser_id = a.id AND e.state = 'linked') AS exported
		FROM adresser a WHERE a.id = $1 AND a.art = 'D'", array((int)$link['adresser_id'], $shopId));
	if ($link !== null && $stored === array()) {
		// the debtor was deleted in Saldi: the customer gets a new one, and the stored time never goes back
		if ($older) {
			$source['source_updated_at'] = $link['source_updated_at'];
		}
		$link = null;
	}
	if ($link !== null) {
		$id = (int)$link['adresser_id'];
		if ($older) {
			return $id;
		}
		if ($link['source_hash'] !== $hash) {
			// an empty value leaves the stored one: a body without protected customer data has no name, email, phone or
			// address. A contact is Saldi's once set: Shopify fills an empty one, and one that is now the name itself
			// goes (a company that became a person). A debtor Saldi sent out (shopify_customer_exports) carries only
			// its contact's name in Shopify (the export is linked), so without a company on the address that name is not the debtor's.
			$write = array_filter($fields, fn(string $v): bool => $v !== '');
			$contact = (string)$stored[0]['kontakt'];
			unset($write['kontakt']);
			$exported = $stored[0]['exported'] === 't';
			$emails = shopify_customer_emails((string)$stored[0]['email']);
			if ($exported && isset($write['email']) && count($emails) > 1 && strcasecmp($emails[0], $write['email']) === 0) {
				// the export sent the first of the debtor's addresses; the others stay
				unset($write['email']);
			}
			if ($exported && trim((string)(shopify_customer_address($customer)['company'] ?? '')) === '') {
				unset($write['firmanavn']);
			} elseif ($contact === '' && $fields['kontakt'] !== '') {
				$write['kontakt'] = $fields['kontakt'];
			} elseif (isset($write['firmanavn']) && $fields['kontakt'] === '' && $contact === $write['firmanavn']) {
				$write['kontakt'] = '';
			}
			shopify_customer_write($shopId, $gid, $id, $write);
		}
		// also when unchanged: its time counts, so an older event arriving later does not win
		shopify_upsert_customer_link($shopId, $gid, $id, $source);
		return $id;
	}

	$adopted = shopify_customer_adopt_export($shopId, $customer);
	if ($adopted !== null) {
		shopify_upsert_customer_link($shopId, $gid, $adopted, $source);
		return $adopted;
	}

	$defaults = shopify_customer_defaults();
	if ($fields['firmanavn'] === '') {
		$fields['firmanavn'] = $fields['email'] !== '' ? mb_substr($fields['email'], 0, SHOPIFY_CUSTOMER_WIDTHS['firmanavn'])
			: ($fields['mobile'] !== '' ? $fields['mobile'] : 'Shopify ' . preg_replace('#^gid://shopify/Customer/#', '', $gid));
	}
	// the customer's text is bound below, never passed through db_modify(), whose injection check
	// misreads a value that ends in a backslash
	$id = (int)create_debtor(shopify_customer_number(), '', '', '', '', '', '', '', '', $defaults['gruppe'], '', $defaults['betalingsbet'], $defaults['betalingsdage'], '', true);
	shopify_customer_write($shopId, $gid, $id, $fields + array('tlf' => $fields['mobile'], 'oprettet' => date('Y-m-d')));
	shopify_upsert_customer_link($shopId, $gid, $id, $source);
	return $id;
}

/** Writes debtor fields, bound; an email too long for the column is not written (the stored one stays) and is listed. */
function shopify_customer_write(int $shopId, string $gid, int $id, array $fields): void {
	if (mb_strlen($fields['email'] ?? '') > SHOPIFY_CUSTOMER_EMAIL_WIDTH) {
		shopify_error_open($shopId, 'customer.email', $gid, "the email of customer $gid is longer than " . SHOPIFY_CUSTOMER_EMAIL_WIDTH . " characters and was not stored on debtor $id", 'warning');
		unset($fields['email']);
	} elseif (isset($fields['email'])) {
		shopify_error_resolve_key($shopId, 'customer.email', $gid);
	}
	if ($fields === array()) {
		return;
	}
	$set = array();
	$params = array($id);
	foreach ($fields as $column => $value) {
		$params[] = $value;
		$set[] = "$column = $" . count($params);
	}
	shopify_db_rows('UPDATE adresser SET ' . implode(', ', $set) . ' WHERE id = $1', $params);
}

/**
 * The Saldi debtor of an open outbound create (pending or create_started) whose correlation tag
 * the customer carries, that still exists and that no other customer of the shop is linked to;
 * the export becomes linked, and a customer with a phone resolves the export's phone info row.
 * Null when there is none.
 */
function shopify_customer_adopt_export(int $shopId, array $customer): ?int {
	$tags = array_filter(array_map('trim', explode(',', is_string($customer['tags'] ?? null) ? $customer['tags'] : implode(',', (array)($customer['tags'] ?? array())))));
	if ($tags === array()) {
		return null;
	}
	$rows = shopify_db_rows(
		"SELECT e.adresser_id FROM shopify_customer_exports e
		 WHERE e.shop_id = $1 AND e.state IN ('pending', 'create_started') AND e.correlation_tag = ANY($2::text[])
		 AND EXISTS (SELECT 1 FROM adresser a WHERE a.id = e.adresser_id AND a.art = 'D')
		 AND NOT EXISTS (SELECT 1 FROM shopify_customer_links l WHERE l.shop_id = e.shop_id AND l.adresser_id = e.adresser_id) FOR UPDATE OF e",
		array($shopId, '{' . implode(',', array_map(fn(string $t): string => '"' . addcslashes($t, '"\\') . '"', $tags)) . '}')
	);
	if (count($rows) > 1) {
		shopify_error_open($shopId, 'customer.export', shopify_gid((string)($customer['admin_graphql_api_id'] ?? $customer['id']), 'Customer'),
			'the customer carries the tags of ' . count($rows) . ' open exports (debtors ' . implode(', ', array_column($rows, 'adresser_id')) . '); none was adopted, a new debtor was created', 'warning');
	}
	if (count($rows) !== 1) {
		return null;
	}
	shopify_db_rows("UPDATE shopify_customer_exports SET state = 'linked', updated_at = now() WHERE shop_id = $1 AND adresser_id = $2", array($shopId, (int)$rows[0]['adresser_id']));
	if (trim(is_scalar($customer['phone'] ?? null) ? (string)$customer['phone'] : '') !== '') {
		// as the export's own link does: a customer with a phone did not go without it
		shopify_error_resolve_key($shopId, 'customer.phone', 'adresser:' . (int)$rows[0]['adresser_id']);
	}
	return (int)$rows[0]['adresser_id'];
}

/**
 * Group and payment terms of a new debtor: the shopify settings, else as the legacy web intake
 * (api/rest_api.php): the first debtor group, Netto, and 8 days as the order page's create.
 *
 * @return array{gruppe: int, betalingsbet: string, betalingsdage: int}
 */
function shopify_customer_defaults(): array {
	$settings = array();
	foreach (shopify_db_rows('SELECT var_name, var_value FROM settings WHERE var_grp = $1 AND var_name IN ($2, $3, $4)',
		array('shopify', SHOPIFY_CUSTOMER_GROUP, SHOPIFY_CUSTOMER_TERMS, SHOPIFY_CUSTOMER_DAYS)) as $row) {
		$settings[$row['var_name']] = trim((string)$row['var_value']);
	}
	$group = (int)($settings[SHOPIFY_CUSTOMER_GROUP] ?? 0);
	if ($group <= 0) {
		$group = (int)(shopify_db_rows("SELECT kodenr FROM grupper WHERE art = 'DG' ORDER BY fiscal_year DESC, kodenr LIMIT 1")[0]['kodenr'] ?? 0);
	}
	if ($group <= 0) {
		throw new ShopifyConfigError('no debtor group for Shopify customers: set settings shopify/' . SHOPIFY_CUSTOMER_GROUP . ' or create a debtor group');
	}
	if (shopify_db_rows("SELECT 1 FROM grupper WHERE art = 'DG' AND kodenr = $1 LIMIT 1", array($group)) === array()) {
		throw new ShopifyConfigError("settings shopify/" . SHOPIFY_CUSTOMER_GROUP . " is $group, which is not a debtor group");
	}
	$terms = ($settings[SHOPIFY_CUSTOMER_TERMS] ?? '') !== '' ? $settings[SHOPIFY_CUSTOMER_TERMS] : 'Netto';
	if (mb_strlen($terms) > 15) {
		throw new ShopifyConfigError("settings shopify/" . SHOPIFY_CUSTOMER_TERMS . " is longer than adresser.betalingsbet (15 characters)");
	}
	return array(
		'gruppe' => $group,
		'betalingsbet' => $terms,
		'betalingsdage' => ($settings[SHOPIFY_CUSTOMER_DAYS] ?? '') !== '' ? (int)$settings[SHOPIFY_CUSTOMER_DAYS] : 8,
	);
}

/**
 * Shopify's updated_at as UTC ('Y-m-d H:i:s'), the form shopify_customer_links.source_updated_at
 * keeps: unlike the manifest's other times (local wall time, includes/shopify/db.php) it is only
 * compared with itself, and local time would misorder the hour that repeats when summer time ends.
 * Null when absent or not ISO 8601.
 */
function shopify_customer_time($value): ?string {
	$time = shopify_event_time(is_string($value) ? trim($value) : null);
	return $time === null ? null : $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

/**
 * Applies one stored customer event: its body (shopify_events) to the debtor, in one
 * transaction. Topics without a whole customer, and shops whose customers flow is off, are skipped.
 *
 * @param array{shop_id: int, event_row_id: int, topic: string} $event
 */
function shopify_customer_upsert(array $event): void {
	if (!in_array($event['topic'], SHOPIFY_CUSTOMER_TOPICS, true)) {
		return;
	}
	if (shopify_db_rows('SELECT 1 FROM shopify_shops WHERE id = $1 AND customers_enabled', array($event['shop_id'])) === array()) {
		return; // switched off after the event was queued
	}
	$rows = shopify_db_rows('SELECT payload_b64 FROM shopify_events WHERE id = $1 AND shop_id = $2', array($event['event_row_id'], $event['shop_id']));
	$customer = isset($rows[0]) ? json_decode((string)base64_decode((string)$rows[0]['payload_b64'], true), true) : null;
	if (!is_array($customer) || !isset($customer['id'])) {
		throw new LogicException("event {$event['event_row_id']}: not a customer body");
	}
	shopify_db_transaction(fn() => ensure_customer((int)$event['shop_id'], $customer));
}

/** Job customer.upsert (prio 25). */
function shopify_customer_upsert_job(array $job, float $deadline, callable $heartbeat): array {
	shopify_customer_upsert(array('shop_id' => (int)$job['shop_id'], 'event_row_id' => (int)($job['payload']['event_row_id'] ?? 0), 'topic' => (string)($job['payload']['topic'] ?? '')));
	return array('outcome' => 'done');
}
