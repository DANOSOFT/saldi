<?php
// --- systemdata/shopify/shops.php --- ver 5.1.0 --- 2026-11-03 ---
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
// Shopify shop registry (WP04, K01): one row per shop with its domain, department, primary
// flag, legacy slot, default freight item, web register and flow flags (all off by default),
// saved through WP01's shopify_save_shop(). The access token and webhook secret are write-only:
// stored through shopify_save_secret(), never shown, never put back into the form. Only a session
// opened from the master admin (Danosoft) creates a shop.
//
// 20261102 CL/MJ SD-761: created.
// 20261103 CL/MJ SD-761 review: creating a shop needs Danosoft's login; a new secret value wins over "Slet";
//                the legacy slot reads 1./2./3. API-klient; the flows have texts.

$title = 'Shopify shops';
require __DIR__ . '/adminBootstrap.php';
require_once __DIR__ . '/../../includes/shopify/events.php';

/** Secrets a shop needs (config.php, events.php), with their labels. */
const SHOPIFY_ADMIN_SECRETS = array(
	'access_token' => 'shopify.admin.shop.access_token|Adgangstoken (Admin API)',
	SHOPIFY_WEBHOOK_SECRET => 'shopify.admin.shop.webhook_secret|Webhook-hemmelighed',
);

/** The texts of the flow flags (SHOPIFY_FLOWS). */
const SHOPIFY_ADMIN_FLOWS = array(
	'products' => 'shopify.admin.flow.products|Varer',
	'prices' => 'shopify.admin.flow.prices|Priser',
	'stock_capture' => 'shopify.admin.flow.stock_capture|Opsaml lagerændringer',
	'stock_publish' => 'shopify.admin.flow.stock_publish|Send lagertal',
	'orders' => 'shopify.admin.flow.orders|Ordrer',
	'customers' => 'shopify.admin.flow.customers|Kunder',
	'store_orders' => 'shopify.admin.flow.store_orders|Butiksordrer',
);

/** Legacy slot (grupper API box4/box5/box6) => its place among the API Klient fields. */
const SHOPIFY_ADMIN_LEGACY_SLOTS = array('4' => '1', '5' => '2', '6' => '3');

/** A readable message for a refused save; the database error names only the constraint. */
function shopify_admin_shop_save_error(Throwable $e): string {
	$constraints = array(
		'shopify_shops_shop_key_key' => 'shopify.admin.error.shop_key_taken|En anden shop har allerede denne nøgle.',
		'shopify_shops_domain_key' => 'shopify.admin.error.domain_taken|En anden shop har allerede dette domæne.',
		'shopify_shops_afd_key' => 'shopify.admin.error.afd_taken|En anden shop bruger allerede denne afdeling.',
		'shopify_shops_is_primary_uidx' => 'shopify.admin.error.primary_taken|En anden shop er allerede primær.',
	);
	if ($e instanceof ShopifyDbError && isset($constraints[$e->constraint])) {
		return $constraints[$e->constraint];
	}
	return 'shopify.admin.error.save|Ændringen kunne ikke gemmes. Prøv igen.';
}

$editKey = (string)($_POST['shop'] ?? $_GET['shop'] ?? '');
$current = $editKey === '' ? null : shopify_shop($editKey);
if (!$current) {
	$editKey = '';
}
$isNew = $editKey === '';

$form = array(
	'shop_key' => $editKey,
	'domain' => (string)($current['domain'] ?? ''),
	'navn' => (string)($current['navn'] ?? ''),
	'afd' => (string)($current['afd'] ?? ''),
	'is_primary' => ($current['is_primary'] ?? 'f') === 't',
	'legacy_slot' => (string)($current['legacy_slot'] ?? ''),
	'freight_varenr' => shopify_admin_varenr($current['default_freight_vare_id'] ?? null),
	'web_kasse' => (string)($current['web_kasse'] ?? ''),
);
foreach (SHOPIFY_FLOWS as $flow) {
	$form["{$flow}_enabled"] = ($current["{$flow}_enabled"] ?? 'f') === 't';
}
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isNew && !$shopifyCanCreate) {
	http_response_code(403);
	print "<p style='margin:20px'>" . shopify_admin_t('shopify.admin.shop.create_danosoft|En ny shop oprettes af Danosoft.') . "</p>";
	exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	foreach (array('domain', 'navn', 'afd', 'legacy_slot', 'freight_varenr', 'web_kasse') as $field) {
		$form[$field] = trim((string)($_POST[$field] ?? ''));
	}
	if ($isNew) {
		$form['shop_key'] = trim((string)($_POST['shop_key'] ?? ''));
	}
	$form['is_primary'] = isset($_POST['is_primary']);
	foreach (SHOPIFY_FLOWS as $flow) {
		$form["{$flow}_enabled"] = isset($_POST["{$flow}_enabled"]);
	}
	$errors = shopify_admin_validate_shop($form, shopify_shops(), $isNew);
	$freightId = null;
	if ($form['freight_varenr'] !== '') {
		$freightId = shopify_admin_vare_id($form['freight_varenr']);
		if ($freightId === null) {
			$errors['freight_varenr'] = 'shopify.admin.error.freight|Varenummeret findes ikke.';
		}
	}
	if (!$errors) {
		$shop = array(
			'shop_key' => $form['shop_key'],
			'domain' => strtolower($form['domain']),
			'navn' => $form['navn'] === '' ? null : $form['navn'],
			'afd' => $form['afd'] === '' ? null : (int)$form['afd'],
			'is_primary' => $form['is_primary'],
			'legacy_slot' => $form['legacy_slot'] === '' ? null : (int)$form['legacy_slot'],
			'default_freight_vare_id' => $freightId,
			'web_kasse' => $form['web_kasse'] === '' ? null : $form['web_kasse'],
		);
		foreach (SHOPIFY_FLOWS as $flow) {
			$shop["{$flow}_enabled"] = $form["{$flow}_enabled"];
		}
		$secrets = is_array($_POST['secret'] ?? null) ? $_POST['secret'] : array();
		$removed = is_array($_POST['secret_remove'] ?? null) ? $_POST['secret_remove'] : array();
		try {
			shopify_db_transaction(function () use ($shop, $secrets, $removed) {
				shopify_save_shop($shop);
				foreach (array_keys(SHOPIFY_ADMIN_SECRETS) as $name) {
					$value = trim((string)($secrets[$name] ?? ''));
					// a new value wins: "Slet" deletes only when the field is left empty
					if ($value !== '') {
						shopify_save_secret($shop['shop_key'], $name, $value);
					} elseif (isset($removed[$name])) {
						shopify_save_secret($shop['shop_key'], $name, '');
					}
				}
			});
			shopify_admin_redirect('shops.php?shop=' . rawurlencode($shop['shop_key']), 'shopify.admin.saved|Gemt.');
		} catch (ShopifyConfigError | ShopifyDbError | ShopifySchemaError $e) {
			$errors['form'] = shopify_admin_shop_save_error($e);
		}
	}
}

$canWrite = $shopifyAccess === 'write';
$disabled = $canWrite ? '' : ' disabled';
$fieldError = function (string $field) use ($errors): string {
	return isset($errors[$field]) ? '<br>' . shopify_admin_error($errors[$field]) : '';
};

shopify_admin_header('shopify.admin.shops.title|Shopify-shops', 'shops.php');

print "<table cellpadding='4' cellspacing='0' border='1' style='margin:8px;border-collapse:collapse'>\n<tr>";
foreach (array('shopify.admin.shop.key|Kortnavn', 'shopify.admin.shop.domain|Domæne', 'shopify.admin.shop.navn|Navn', 'shopify.admin.shop.afd|Afdeling', 'shopify.admin.shop.flows|Synkronisering') as $token) {
	print '<th>' . shopify_admin_t($token) . '</th>';
}
print "</tr>\n";
foreach (shopify_shops() as $shop) {
	$flows = array();
	foreach (SHOPIFY_FLOWS as $flow) {
		if ($shop["{$flow}_enabled"] === 't') {
			$flows[] = shopify_admin_t(SHOPIFY_ADMIN_FLOWS[$flow]);
		}
	}
	$primary = $shop['is_primary'] === 't' ? ' (' . shopify_admin_t('shopify.admin.shop.primary|Primær') . ')' : '';
	print "<tr><td><a href='shops.php?shop=" . shopify_admin_h(rawurlencode($shop['shop_key'])) . "'>" . shopify_admin_h($shop['shop_key']) . "</a>$primary</td>";
	print '<td>' . shopify_admin_h($shop['domain']) . '</td><td>' . shopify_admin_h($shop['navn']) . '</td><td>' . shopify_admin_h($shop['afd']) . '</td>';
	print '<td>' . ($flows ? implode(', ', $flows) : '-') . "</td></tr>\n";
}
print "</table>\n";
if ($shopifyCanCreate && !$isNew) {
	print "<p style='margin:8px'><a href='shops.php'>" . shopify_admin_t('shopify.admin.shop.new|Ny shop') . "</a></p>\n";
}

if ($isNew && !$shopifyCanCreate) {
	// Without Danosoft's login there is no form for a new shop, only the note below the list.
	print "<p style='margin:8px'>" . shopify_admin_t('shopify.admin.shop.create_danosoft|En ny shop oprettes af Danosoft.') . "</p>\n";
	exit;
}
print "<form method='post' action='shops.php' autocomplete='off' style='margin:8px'>\n";
print shopify_admin_form_fields();
print "<input type='hidden' name='shop' value='" . shopify_admin_h($editKey) . "'>\n";
print '<h3>' . shopify_admin_t($isNew ? 'shopify.admin.shop.new|Ny shop' : 'shopify.admin.shop.edit|Ret shop') . "</h3>\n";
if (isset($errors['form'])) {
	print '<p>' . shopify_admin_error($errors['form']) . "</p>\n";
}
print "<table cellpadding='4' cellspacing='0'>\n";
$keyField = $isNew
	? "<input name='shop_key' size='20' maxlength='40' value='" . shopify_admin_h($form['shop_key']) . "'$disabled>"
	: shopify_admin_h($form['shop_key']);
print '<tr><td>' . shopify_admin_t('shopify.admin.shop.key|Kortnavn') . "</td><td>$keyField" . $fieldError('shop_key') . "</td></tr>\n";
print '<tr><td>' . shopify_admin_t('shopify.admin.shop.domain|Domæne') . "</td><td><input name='domain' size='40' value='" . shopify_admin_h($form['domain']) . "'$disabled>" . $fieldError('domain') . "</td></tr>\n";
print '<tr><td>' . shopify_admin_t('shopify.admin.shop.navn|Navn') . "</td><td><input name='navn' size='40' value='" . shopify_admin_h($form['navn']) . "'$disabled></td></tr>\n";
print '<tr><td>' . shopify_admin_t('shopify.admin.shop.afd|Afdeling') . "</td><td><select name='afd'$disabled><option value=''></option>";
foreach (shopify_admin_departments() as $afd) {
	$selected = $afd['kodenr'] === $form['afd'] ? ' selected' : '';
	print "<option value='" . shopify_admin_h($afd['kodenr']) . "'$selected>" . shopify_admin_h($afd['kodenr'] . ' ' . $afd['beskrivelse']) . '</option>';
}
print '</select>' . $fieldError('afd') . "</td></tr>\n";
print '<tr><td>' . shopify_admin_t('shopify.admin.shop.primary|Primær') . "</td><td><input type='checkbox' name='is_primary'" . ($form['is_primary'] ? ' checked' : '') . "$disabled>" . $fieldError('is_primary') . "</td></tr>\n";
print '<tr><td>' . shopify_admin_t('shopify.admin.shop.legacy_slot|Gammel API-klient') . "</td><td><select name='legacy_slot'$disabled><option value=''></option>";
foreach (SHOPIFY_ADMIN_LEGACY_SLOTS as $slot => $place) {
	print "<option value='$slot'" . ((string)$slot === $form['legacy_slot'] ? ' selected' : '') . ">$place.</option>";
}
print '</select>' . $fieldError('legacy_slot') . "</td></tr>\n";
print '<tr><td>' . shopify_admin_t('shopify.admin.shop.freight|Standard fragtvare (varenr.)') . "</td><td><input name='freight_varenr' size='20' value='" . shopify_admin_h($form['freight_varenr']) . "'$disabled>" . $fieldError('freight_varenr') . "</td></tr>\n";
print '<tr><td>' . shopify_admin_t('shopify.admin.shop.web_kasse|Webkasse') . "</td><td><input name='web_kasse' size='20' value='" . shopify_admin_h($form['web_kasse']) . "'$disabled></td></tr>\n";
print '<tr><td valign="top">' . shopify_admin_t('shopify.admin.shop.flows|Synkronisering') . '</td><td>';
foreach (SHOPIFY_FLOWS as $flow) {
	print "<label><input type='checkbox' name='{$flow}_enabled'" . ($form["{$flow}_enabled"] ? ' checked' : '') . "$disabled> " . shopify_admin_t(SHOPIFY_ADMIN_FLOWS[$flow]) . '</label><br>';
}
print "</td></tr>\n";
foreach (SHOPIFY_ADMIN_SECRETS as $name => $token) {
	$set = !$isNew && shopify_admin_secret_set($editKey, $name);
	$state = shopify_admin_t($set ? 'shopify.admin.secret.set|Gemt. Lad feltet stå tomt for at beholde værdien.' : 'shopify.admin.secret.unset|Ikke gemt');
	print '<tr><td>' . shopify_admin_t($token) . "</td><td><input type='password' name='secret[$name]' size='40' value='' autocomplete='new-password'$disabled> <small>$state</small>";
	if ($set) {
		print " <label><input type='checkbox' name='secret_remove[$name]'$disabled> " . shopify_admin_t('shopify.admin.secret.remove|Slet') . '</label>';
	}
	print "</td></tr>\n";
}
print "</table>\n";
if ($canWrite) {
	print "<p><input type='submit' value='" . shopify_admin_t('shopify.admin.save|Gem') . "'></p>\n";
}
print "</form>\n";
