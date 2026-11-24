<?php
// --- systemdata/shopify/mapping.php --- ver 5.1.1 --- 2026-11-23 ---
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
// The Shopify location of each Saldi lager, per shop (WP04, K04). "Hent lokationer" queues a
// locations.fetch job (includes/shopify/services/locations.php) and the page offers the fetched
// locations. A lager without a location gets one here through WP01's shopify_save_map(). Moving
// or removing a mapping is the worker's remap command, which zeroes the old location's stock
// first, so the page shows a mapped lager without a choice. A sellable lager without a location
// is marked, because no stock reaches the shop from it. On a shop that already sends stock, a new
// mapping marks the listed items, so the new location gets its stock on the next push.
//
// 20261123 CL/MJ SD-865: created, with the location mapping.

$title = 'Shopify mapping';
require __DIR__ . '/adminBootstrap.php';
require_once __DIR__ . '/../../includes/shopify/queue.php';
require_once __DIR__ . '/../../includes/shopify/services/locations.php';
require_once __DIR__ . '/../../includes/shopify/services/stock.php';

$shops = shopify_shops();
if (!$shops) {
	shopify_admin_header('shopify.admin.mapping.title|Shopify-mapping', 'mapping.php');
	print "<p style='margin:8px'>" . shopify_admin_t('shopify.admin.mapping.no_shops|Der er ingen shops endnu.') . "</p>\n";
	exit;
}
$shopId = (int)($_POST['shop'] ?? $_GET['shop'] ?? 0);
$shop = $shops[0];
$shopFound = false;
foreach ($shops as $candidate) {
	if ((int)$candidate['id'] === $shopId) {
		$shop = $candidate;
		$shopFound = true;
	}
}
$shopId = (int)$shop['id'];
$self = 'mapping.php?shop=' . $shopId;
$lagre = shopify_admin_lagre();
$mapped = shopify_admin_location_map($shopId);
$fetched = shopify_locations_fetched($shopId);
$names = array_column($fetched['locations'], 'name', 'gid');
$errors = array();
$chosen = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$shopFound) {
	// A form posted for a shop that is gone acts on no shop, never on the first one.
	$errors['form'] = 'shopify.admin.mapping.error.shop|Shoppen findes ikke længere. Vælg shoppen igen.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['fetch'])) {
		try {
			shopify_locations_request($shop);
			shopify_admin_redirect($self, 'shopify.admin.mapping.fetch_queued|Lokationerne hentes fra Shopify. Genindlæs siden om et øjeblik.');
		} catch (ShopifyConfigError | ShopifyDbError | ShopifySchemaError $e) {
			$errors['form'] = 'shopify.admin.error.save|Ændringen kunne ikke gemmes. Prøv igen.';
		}
	}
	$posted = is_array($_POST['location'] ?? null) ? $_POST['location'] : array();
	foreach ($lagre as $row) {
		$value = $posted[$row['lager']] ?? '';
		$chosen[$row['lager']] = is_string($value) ? trim($value) : '';
	}
	$errors = shopify_admin_validate_locations($chosen, $mapped, array_keys($names));
	if (!$errors) {
		try {
			shopify_db_transaction(function () use ($chosen, $mapped, $shopId, $shop) {
				$saved = 0;
				foreach ($chosen as $lager => $gid) {
					if ($gid !== '' && !isset($mapped[$lager])) {
						shopify_save_map($shopId, $lager, $gid);
						$saved++;
					}
				}
				// as shopify_stock_remap_finish(): on a shop that already sends stock, the listed items are marked,
				// so the new location gets its stock on the next push instead of at the next stock change
				if ($saved && $shop['stock_capture_enabled'] === 't') {
					$rows = shopify_db_rows('SELECT vare_id FROM shopify_product_links WHERE shop_id = $1 AND variant_id = 0 AND listed AND inventory_item_gid IS NOT NULL', array($shopId));
					shopify_stock_mark($shop, array_map('intval', array_column($rows, 'vare_id')), (int)shopify_db_rows('SELECT txid_current() AS x')[0]['x']);
				}
			});
			shopify_admin_redirect($self, 'shopify.admin.saved|Gemt.');
		} catch (ShopifyConfigError | ShopifyDbError | ShopifySchemaError $e) {
			$errors['form'] = 'shopify.admin.error.save|Ændringen kunne ikke gemmes. Prøv igen.';
		}
	}
}

shopify_admin_header('shopify.admin.mapping.title|Shopify-mapping', 'mapping.php');
$canWrite = $shopifyAccess === 'write';
$disabled = $canWrite ? '' : ' disabled';
print "<p style='margin:8px'>";
foreach ($shops as $candidate) {
	$label = shopify_admin_h($candidate['navn'] !== null && $candidate['navn'] !== '' ? $candidate['navn'] : $candidate['shop_key']);
	print (int)$candidate['id'] === $shopId ? "<b>$label</b> &nbsp; " : "<a href='mapping.php?shop=" . (int)$candidate['id'] . "'>$label</a> &nbsp; ";
}
print "</p>\n";
print "<p style='margin:8px'>" . shopify_admin_t('shopify.admin.mapping.help|Vælg den Shopify-lokation, hvert lager svarer til. En lokation kan kun bruges af ét lager.') . "</p>\n";
print "<form method='post' action='" . shopify_admin_h($self) . "' style='margin:8px'>\n" . shopify_admin_form_fields();
print "<input type='hidden' name='shop' value='$shopId'>";
print $fetched['fetched_at'] === null
	? shopify_admin_t('shopify.admin.mapping.never_fetched|Lokationerne er ikke hentet endnu.')
	: shopify_admin_t('shopify.admin.mapping.fetched_at|Lokationer hentet') . ' ' . shopify_admin_h(date('d-m-Y H:i', (int)strtotime($fetched['fetched_at'])));
if ($canWrite) {
	print " <input type='submit' name='fetch' value='" . shopify_admin_t('shopify.admin.mapping.fetch|Hent lokationer') . "'>";
}
print "</form>\n";

print "<form method='post' action='" . shopify_admin_h($self) . "' style='margin:8px'>\n" . shopify_admin_form_fields();
print "<input type='hidden' name='shop' value='$shopId'>";
if (isset($errors['form'])) {
	print '<p>' . shopify_admin_error($errors['form']) . "</p>\n";
}
print "<table cellpadding='4' cellspacing='0' border='1' style='border-collapse:collapse'>\n<tr>";
foreach (array('shopify.admin.lager.nr|Nr.', 'shopify.admin.lager.navn|Lager', 'shopify.admin.lager.rolle|Rolle', 'shopify.admin.mapping.location|Lokation i Shopify') as $token) {
	print '<th>' . shopify_admin_t($token) . '</th>';
}
print "</tr>\n";
$rolleLabels = array('saelgbar' => 'shopify.admin.lager.saelgbar|Salgbart', 'udstilling' => 'shopify.admin.lager.udstilling|Udstilling', 'ingen' => 'shopify.admin.lager.ingen|Ingen');
foreach ($lagre as $row) {
	$lager = $row['lager'];
	print '<tr><td>' . $lager . '</td><td>' . shopify_admin_h($row['beskrivelse']) . '</td><td>' . shopify_admin_t($rolleLabels[$row['rolle']] ?? $rolleLabels['ingen']) . '</td><td>';
	if (isset($mapped[$lager])) {
		print shopify_admin_h($names[$mapped[$lager]] ?? $mapped[$lager]);
	} else {
		print "<select name='location[$lager]'$disabled><option value=''></option>";
		foreach ($names as $gid => $name) {
			if (!in_array($gid, $mapped, true)) {
				$selected = ($chosen[$lager] ?? '') === $gid ? ' selected' : '';
				print "<option value='" . shopify_admin_h($gid) . "'$selected>" . shopify_admin_h($name) . '</option>';
			}
		}
		print '</select>';
		if ($row['rolle'] === 'saelgbar') {
			print '<br>' . shopify_admin_error('shopify.admin.mapping.missing|Salgbart lager uden lokation: lagertallet sendes ikke til shoppen.');
		}
	}
	print (isset($errors[$lager]) ? '<br>' . shopify_admin_error($errors[$lager]) : '') . "</td></tr>\n";
}
print "</table>\n";
print "<p>" . shopify_admin_t('shopify.admin.mapping.remap_note|En lokation, der er valgt, skiftes eller fjernes af Danosoft med worker-kommandoen remap, så lageret i den gamle lokation nulstilles først.') . "</p>\n";
if ($canWrite) {
	print "<p><input type='submit' value='" . shopify_admin_t('shopify.admin.save|Gem') . "'></p>\n";
}
print "</form>\n";
