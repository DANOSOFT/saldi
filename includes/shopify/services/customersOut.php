<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/services/customersOut.php --- ver 5.1.0 --- 2026.11.09
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
// Outbound customers (WP13-1, K31, K61): a Saldi debtor becomes a customer in a shop, so a store
// order (WP13-2) can carry it. The export is reserved in shopify_customer_exports with a
// correlation tag the customer carries, so the inbound side (customers.php) adopts the webhook of
// our own create instead of making a second debtor. customerCreate is an uncertain mutation: a
// create that may have reached Shopify is looked up (tag, then email and phone) before anything is
// sent again, and never sooner than SHOPIFY_CUSTOMER_EXPORT_SETTLE after it was sent. A phone
// another customer has is left out (info on the error list); an email another customer has parks
// the export on the error list. A debtor is never associated with an existing customer.
//
// 20261108 CL/SS SD-803: created (WP13-1).
// 20261108 CL/SS SD-803 review: an inbound link resolves the export's error; the phone's info row is written before the create without it.
//                A linked export whose link is gone is looked up before any create. A queue while the job runs queues another behind it.
//                A debtor corrected while Shopify answers is not parked on the refusal of its old values.
// 20261109 CL/SS SD-803 review: linking a customer that has a phone resolves the phone's info row.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../client.php';
require_once __DIR__ . '/../links.php';
require_once __DIR__ . '/../errors.php';
require_once __DIR__ . '/../queue.php';
require_once __DIR__ . '/customers.php';

/** Seconds a create that may have reached Shopify is left before it may be sent again: Shopify's customer search can lag the create. */
const SHOPIFY_CUSTOMER_EXPORT_SETTLE = 600;

const SHOPIFY_CUSTOMER_EXPORT_FIELDS = 'id tags firstName lastName updatedAt defaultEmailAddress { emailAddress } defaultPhoneNumber { phoneNumber }';
const SHOPIFY_CUSTOMER_EXPORT_CREATE = 'mutation customerCreate($input: CustomerInput!) {
	customerCreate(input: $input) { customer { ' . SHOPIFY_CUSTOMER_EXPORT_FIELDS . ' } userErrors { field message } }
}';
const SHOPIFY_CUSTOMER_EXPORT_BY_TAG = 'query customerExportByTag($query: String!) {
	customers(first: 5, query: $query) { nodes { ' . SHOPIFY_CUSTOMER_EXPORT_FIELDS . ' } }
}';
const SHOPIFY_CUSTOMER_EXPORT_BY_IDENTIFIER = 'query customerExportByIdentifier($identifier: CustomerIdentifierInput!) {
	customerByIdentifier(identifier: $identifier) { ' . SHOPIFY_CUSTOMER_EXPORT_FIELDS . ' }
}';

/** A failure that is not the debtor's: the queue retries the job and alarms after the third attempt. */
final class ShopifyCustomerExportError extends RuntimeException
{
}

/** The tag a debtor's customer carries in Shopify: saldi-deb-<db>-<adresser_id>. */
function shopify_customer_export_tag(int $adresserId): string {
	$db = (string)shopify_db_rows('SELECT current_database() AS db')[0]['db'];
	return "saldi-deb-$db-$adresserId";
}

/**
 * Queues the debtor for a shop whose customers flow is on, unless it has a customer there already:
 * reserves the export (pending, with its tag) and queues job customer.export. A parked or linked
 * export reopens as create_started, so a debtor whose email was corrected goes with the next order,
 * and one whose customer exists (a link that is gone, or a customer linked otherwise) is looked up
 * before any create. A parked export's last create was refused, so its lookup does not wait for the
 * search; a linked one keeps the time of its create and waits it out like any create.
 *
 * @return array{id: int, created: bool, state: string}|null the job; null when nothing is to be sent
 */
function shopify_customer_export_enqueue(int $shopId, int $adresserId, array $correlation = array()): ?array {
	$shop = shopify_db_rows('SELECT id, shop_key FROM shopify_shops WHERE id = $1 AND customers_enabled', array($shopId))[0] ?? null;
	if ($shop === null || $adresserId <= 0 || shopify_customer_link_for_adresser($shopId, $adresserId) !== null) {
		return null;
	}
	return shopify_db_transaction(function () use ($shop, $shopId, $adresserId, $correlation): array {
		shopify_db_rows(
			"INSERT INTO shopify_customer_exports (shop_id, adresser_id, state, correlation_tag) VALUES ($1, $2, 'pending', $3)
			 ON CONFLICT (shop_id, adresser_id) DO UPDATE SET state = 'create_started', updated_at = now(),
				attempt_at = CASE shopify_customer_exports.state WHEN 'parked' THEN NULL ELSE shopify_customer_exports.attempt_at END
			 WHERE shopify_customer_exports.state IN ('parked', 'linked')",
			array($shopId, $adresserId, shopify_customer_export_tag($adresserId))
		);
		// coalesced: a job already running may have read the export before it was reopened, so another one follows it
		return shopify_queue_enqueue_coalesced('customer.export', $shopId, "{$shop['shop_key']}:customer:$adresserId", array('adresser_id' => $adresserId),
			SHOPIFY_QUEUE_PRIORITY['customer.export'], null, $correlation);
	});
}

/**
 * Exports one reserved debtor to the shop.
 *
 * @param array{deadline?: float, graphql?: array} $rt
 * @return array{result: string, reason?: string, customer_gid?: string, run_after?: int}
 *   result linked (now or already), parked, skipped, or wait (a create may have reached Shopify; run again after run_after seconds)
 */
function shopify_customer_export(array $rt, int $shopId, int $adresserId): array {
	$shop = shopify_db_rows('SELECT * FROM shopify_shops WHERE id = $1 AND customers_enabled', array($shopId))[0] ?? null;
	if ($shop === null) {
		return array('result' => 'skipped', 'reason' => 'the shop\'s customers flow is off');
	}
	$start = shopify_db_transaction(function () use ($shopId, $adresserId): array {
		$export = shopify_db_rows('SELECT state, correlation_tag, extract(epoch FROM now() - attempt_at) AS age FROM shopify_customer_exports
			WHERE shop_id = $1 AND adresser_id = $2 FOR UPDATE', array($shopId, $adresserId))[0] ?? null;
		$link = shopify_customer_link_for_adresser($shopId, $adresserId);
		if ($link !== null) {
			if ($export !== null) {
				shopify_db_rows("UPDATE shopify_customer_exports SET state = 'linked', updated_at = now() WHERE shop_id = $1 AND adresser_id = $2", array($shopId, $adresserId));
			}
			// also when the inbound side linked it first (adopted by the tag)
			shopify_error_resolve_key($shopId, 'customer.export', "adresser:$adresserId");
			return array('result' => 'linked', 'customer_gid' => $link['customer_gid']);
		}
		if ($export === null) {
			return array('result' => 'skipped', 'reason' => 'no export is reserved');
		}
		$debtor = shopify_db_rows("SELECT id, firmanavn, kontakt, email, tlf, mobile, land FROM adresser WHERE id = $1 AND art = 'D'", array($adresserId))[0] ?? null;
		if ($debtor === null) {
			shopify_db_rows('DELETE FROM shopify_customer_exports WHERE shop_id = $1 AND adresser_id = $2', array($shopId, $adresserId));
			return array('result' => 'skipped', 'reason' => 'the debtor was deleted');
		}
		if ($export['state'] === 'parked') {
			return array('result' => 'parked');
		}
		$started = $export['state'] === 'create_started';
		if (!$started) {
			shopify_db_rows("UPDATE shopify_customer_exports SET state = 'create_started', attempt_at = now(), updated_at = now() WHERE shop_id = $1 AND adresser_id = $2",
				array($shopId, $adresserId));
		}
		return array('debtor' => $debtor, 'tag' => (string)$export['correlation_tag'], 'started' => $started, 'age' => $export['age'] === null ? null : (float)$export['age']);
	});
	if (!isset($start['debtor'])) {
		return $start;
	}
	$debtor = $start['debtor'];
	$tag = $start['tag'];
	$input = shopify_customer_export_input($debtor, $tag);
	$sent = $input;

	if ($start['started']) {
		// an earlier attempt may have created the customer: read before anything is sent again
		$found = shopify_customer_export_find($rt, $shop, $tag, $input);
		if ($found !== null) {
			return shopify_customer_export_adopt($shopId, $adresserId, $found);
		}
		if ($start['age'] !== null && $start['age'] < SHOPIFY_CUSTOMER_EXPORT_SETTLE) {
			return array('result' => 'wait', 'run_after' => (int)ceil(SHOPIFY_CUSTOMER_EXPORT_SETTLE - $start['age']));
		}
		shopify_db_rows('UPDATE shopify_customer_exports SET attempt_at = now(), updated_at = now() WHERE shop_id = $1 AND adresser_id = $2', array($shopId, $adresserId));
	}

	$res = shopify_customer_export_create($rt, $shop, $tag, $input);
	$fields = array_map(fn(array $e): string => (string)(($e['field'] ?? array())[0] ?? ''), $res['user_errors']);
	if (!$res['ok'] && $res['error'] === 'user_errors' && in_array('phone', $fields, true) && !in_array('email', $fields, true) && isset($input['phone'])) {
		$phone = $input['phone'];
		$refused = implode('; ', array_column(array_filter($res['user_errors'], fn(array $e): bool => (($e['field'] ?? array())[0] ?? '') === 'phone'), 'message'));
		unset($input['phone']);
		// before the send: a create whose answer is lost is found later, and this must not be lost with it
		shopify_error_open($shopId, 'customer.phone', "adresser:$adresserId", "debtor $adresserId goes to Shopify without its phone $phone, which Shopify refused ($refused)", 'info');
		$res = shopify_customer_export_create($rt, $shop, $tag, $input);
		$fields = array_map(fn(array $e): string => (string)(($e['field'] ?? array())[0] ?? ''), $res['user_errors']);
	}
	if ($res['ok']) {
		$customer = $res['data']['customerCreate']['customer'] ?? null;
		if (!is_array($customer) || (string)($customer['id'] ?? '') === '') {
			throw new ShopifyCustomerExportError("customerCreate for debtor $adresserId answered without the customer");
		}
		return shopify_customer_export_adopt($shopId, $adresserId, $customer);
	}
	if ($res['error'] === 'user_errors') {
		$why = implode('; ', array_map(fn(array $e): string => ($e['field'] ? implode('.', (array)$e['field']) . ': ' : '') . $e['message'], $res['user_errors']));
		$message = in_array('email', $fields, true)
			? "debtor $adresserId was not created in Shopify: its email " . ($input['email'] ?? '') . " belongs to another customer there or is refused ($why); correct the debtor's email, the next order sends it again"
			: "debtor $adresserId was not created in Shopify: $why";
		$parked = shopify_db_transaction(function () use ($shopId, $adresserId, $message, $tag, $sent, $input): bool {
			shopify_db_rows('SELECT 1 FROM shopify_customer_exports WHERE shop_id = $1 AND adresser_id = $2 FOR UPDATE', array($shopId, $adresserId));
			$now = shopify_db_rows("SELECT id, firmanavn, kontakt, email, tlf, mobile, land FROM adresser WHERE id = $1 AND art = 'D'", array($adresserId))[0] ?? null;
			if ($now !== null && shopify_customer_export_input($now, $tag) !== $sent) {
				// the debtor was corrected while Shopify answered: what was refused is not what would be sent now
				shopify_db_rows("UPDATE shopify_customer_exports SET state = 'pending', updated_at = now() WHERE shop_id = $1 AND adresser_id = $2", array($shopId, $adresserId));
				return false;
			}
			shopify_db_rows("UPDATE shopify_customer_exports SET state = 'parked', updated_at = now() WHERE shop_id = $1 AND adresser_id = $2", array($shopId, $adresserId));
			shopify_error_open($shopId, 'customer.export', "adresser:$adresserId", $message);
			if (isset($sent['phone']) && !isset($input['phone'])) {
				// no customer went without the phone after all
				shopify_error_resolve_key($shopId, 'customer.phone', "adresser:$adresserId");
			}
			return true;
		});
		return $parked ? array('result' => 'parked', 'reason' => $why) : array('result' => 'wait', 'run_after' => 1);
	}
	if ($res['outcome'] === 'uncertain') {
		// it may have been created: the next run reads first, after the search has caught up
		return array('result' => 'wait', 'run_after' => SHOPIFY_CUSTOMER_EXPORT_SETTLE);
	}
	// nothing was created: the next attempt may send at once
	shopify_db_rows("UPDATE shopify_customer_exports SET state = 'pending', updated_at = now() WHERE shop_id = $1 AND adresser_id = $2 AND state = 'create_started'",
		array($shopId, $adresserId));
	if ($res['error'] === 'deadline') {
		return array('result' => 'wait', 'run_after' => 1);
	}
	$why = implode('; ', array_column($res['errors'], 'message'));
	throw new ShopifyCustomerExportError("customerCreate for debtor $adresserId failed ({$res['error']})" . ($why !== '' ? ": $why" : ''));
}

/**
 * Sends customerCreate in reconcile mode: an attempt that may have reached Shopify is looked up
 * (shopify_customer_export_find()) before it is sent again. Without an email or phone only the
 * tag search can find it, and that search can lag, so it is not resent then.
 */
function shopify_customer_export_create(array $rt, array $shop, string $tag, array $input): array {
	$reconcile = function () use ($rt, $shop, $tag, $input): array {
		$found = shopify_customer_export_find($rt, $shop, $tag, $input);
		if ($found !== null) {
			return array('state' => 'applied', 'data' => array('customerCreate' => array('customer' => $found, 'userErrors' => array())));
		}
		return array('state' => isset($input['email']) || isset($input['phone']) ? 'absent' : 'unknown');
	};
	$res = shopify_graphql($shop, 'customerCreate', SHOPIFY_CUSTOMER_EXPORT_CREATE, array('input' => $input),
		($rt['graphql'] ?? array()) + array('deadline' => (float)($rt['deadline'] ?? microtime(true) + 50), 'retry_mode' => 'reconcile', 'reconcile' => $reconcile,
			'correlation' => array('ref' => $tag)));
	return $res;
}

/**
 * The customer an earlier create made for this tag: by the tag, then by the debtor's email and
 * phone (exact lookups, which don't lag), where the customer found must carry the tag. A customer
 * with the email or phone but not the tag is someone else's and is not adopted. More than one
 * customer with the tag is not guessed between.
 *
 * @throws ShopifyCustomerExportError
 */
function shopify_customer_export_find(array $rt, array $shop, string $tag, array $input): ?array {
	$read = function (string $op, string $query, array $vars) use ($rt, $shop, $tag): array {
		$res = shopify_graphql($shop, $op, $query, $vars, ($rt['graphql'] ?? array()) + array('deadline' => (float)($rt['deadline'] ?? microtime(true) + 50),
			'correlation' => array('ref' => $tag)));
		if (!$res['ok']) {
			throw new ShopifyCustomerExportError("$op failed ({$res['error']})");
		}
		return $res['data'] ?? array();
	};
	$tagged = array_values(array_filter($read('customerExportByTag', SHOPIFY_CUSTOMER_EXPORT_BY_TAG, array('query' => 'tag:"' . addcslashes($tag, '"\\') . '"'))['customers']['nodes'] ?? array(),
		fn(array $c): bool => in_array($tag, (array)($c['tags'] ?? array()), true)));
	if (count($tagged) > 1) {
		throw new ShopifyCustomerExportError(count($tagged) . " customers in shop {$shop['shop_key']} carry the tag $tag; link the debtor by hand");
	}
	if ($tagged !== array()) {
		return $tagged[0];
	}
	foreach (array('email' => 'emailAddress', 'phone' => 'phoneNumber') as $field => $key) {
		if (!isset($input[$field])) {
			continue;
		}
		$c = $read('customerExportByIdentifier', SHOPIFY_CUSTOMER_EXPORT_BY_IDENTIFIER, array('identifier' => array($key => $input[$field])))['customerByIdentifier'] ?? null;
		if (is_array($c) && in_array($tag, (array)($c['tags'] ?? array()), true)) {
			return $c;
		}
	}
	return null;
}

/**
 * Links the debtor to its new customer and marks the export linked. The link's hash and time are
 * the customer's as created, so the webhook of our own create changes nothing on the debtor.
 * When the inbound side linked it first (adopted by the tag), that link stays.
 */
function shopify_customer_export_adopt(int $shopId, int $adresserId, array $customer): array {
	$gid = shopify_gid((string)$customer['id'], 'Customer');
	return shopify_db_transaction(function () use ($shopId, $adresserId, $customer, $gid): array {
		shopify_db_rows('SELECT pg_advisory_xact_lock(hashtext($1))', array("shopify_customer:$shopId:$gid"));
		$byCustomer = shopify_customer_link($shopId, $gid);
		$byDebtor = shopify_customer_link_for_adresser($shopId, $adresserId);
		if (($byCustomer !== null && (int)$byCustomer['adresser_id'] !== $adresserId) || ($byDebtor !== null && $byDebtor['customer_gid'] !== $gid)) {
			$message = "debtor $adresserId's customer $gid in Shopify is linked otherwise (customer to debtor " . ($byCustomer['adresser_id'] ?? '-')
				. ', debtor to customer ' . ($byDebtor['customer_gid'] ?? '-') . '); nothing was linked';
			shopify_db_rows("UPDATE shopify_customer_exports SET state = 'parked', updated_at = now() WHERE shop_id = $1 AND adresser_id = $2", array($shopId, $adresserId));
			shopify_error_open($shopId, 'customer.export', "adresser:$adresserId", $message);
			return array('result' => 'parked', 'reason' => $message);
		}
		if ($byCustomer === null) {
			$rest = array('first_name' => $customer['firstName'] ?? null, 'last_name' => $customer['lastName'] ?? null,
				'email' => $customer['defaultEmailAddress']['emailAddress'] ?? null, 'phone' => $customer['defaultPhoneNumber']['phoneNumber'] ?? null);
			$updatedAt = shopify_customer_time($customer['updatedAt'] ?? null);
			shopify_upsert_customer_link($shopId, $gid, $adresserId, array('source_hash' => shopify_customer_hash(shopify_customer_fields($rest)))
				+ ($updatedAt === null ? array() : array('source_updated_at' => $updatedAt)));
		}
		shopify_db_rows("UPDATE shopify_customer_exports SET state = 'linked', updated_at = now() WHERE shop_id = $1 AND adresser_id = $2", array($shopId, $adresserId));
		shopify_error_resolve_key($shopId, 'customer.export', "adresser:$adresserId");
		if ((string)($customer['defaultPhoneNumber']['phoneNumber'] ?? '') !== '') {
			// a customer with a phone did not go without it
			shopify_error_resolve_key($shopId, 'customer.phone', "adresser:$adresserId");
		}
		return array('result' => 'linked', 'customer_gid' => $gid);
	});
}

/**
 * The customerCreate input of a debtor: the contact person's name for a company, else the
 * debtor's name, split at its last space; the first of its emails; the phone in E.164; and the tag.
 *
 * @param array<string, string|null> $debtor firmanavn, kontakt, email, tlf, mobile, land
 */
function shopify_customer_export_input(array $debtor, string $tag): array {
	$text = fn($v): string => trim(preg_replace('/\s+/', ' ', (string)$v));
	$name = $text($debtor['kontakt'] ?? '') !== '' ? $text($debtor['kontakt']) : $text($debtor['firmanavn'] ?? '');
	$cut = strrpos($name, ' ');
	$input = $cut === false ? array('firstName' => $name) : array('firstName' => substr($name, 0, $cut), 'lastName' => substr($name, $cut + 1));
	$input = array_filter($input, fn(string $v): bool => $v !== '');
	// the field may hold several addresses (; or ,), as reminders and invoices split it: the first goes
	$email = $text(shopify_customer_emails((string)($debtor['email'] ?? ''))[0] ?? '');
	if ($email !== '') {
		$input['email'] = $email;
	}
	foreach (array('mobile', 'tlf') as $column) {
		$phone = shopify_customer_export_phone((string)($debtor[$column] ?? ''), (string)($debtor['land'] ?? ''));
		if ($phone !== '') {
			$input['phone'] = $phone;
			break;
		}
	}
	$input['tags'] = array($tag);
	return $input;
}

/**
 * A debtor's phone in E.164: +<digits> or 00<digits> as given, a Danish 8-digit number (no
 * country, or Denmark) with +45. '' when it is neither, so no number is guessed.
 */
function shopify_customer_export_phone(string $phone, string $land): string {
	$phone = preg_replace('/[\s().\/-]/', '', $phone);
	if (str_starts_with($phone, '00')) {
		$phone = '+' . substr($phone, 2);
	}
	if (preg_match('/^[0-9]{8}$/', $phone) && in_array(mb_strtolower(trim($land)), array('', 'dk', 'danmark', 'denmark'), true)) {
		$phone = "+45$phone";
	}
	return preg_match('/^\+[1-9][0-9]{6,14}$/', $phone) ? $phone : '';
}

/** Job customer.export (prio 20). */
function shopify_customer_export_job(array $job, float $deadline, callable $heartbeat, array $rt = array()): array {
	$adresserId = (int)($job['payload']['adresser_id'] ?? 0);
	if ($adresserId <= 0) {
		throw new LogicException("job {$job['id']} customer.export has no adresser_id");
	}
	$result = shopify_customer_export($rt + array('deadline' => $deadline), (int)$job['shop_id'], $adresserId);
	if ($result['result'] === 'wait') {
		return array('outcome' => 'yield', 'checkpoint' => array(), 'run_after' => $result['run_after']);
	}
	return array('outcome' => 'done');
}
