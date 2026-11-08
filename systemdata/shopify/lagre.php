<?php
// --- systemdata/shopify/lagre.php --- ver 5.1.0 --- 2026-11-03 ---
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
// Shopify role of each Saldi lager (WP04, K04b, K13, K16): sellable with a priority (unique among
// the sellable lagre; Saldi draws an order from them in that order), display of another lager
// (shown, never sold) or none. Saved as one table through WP01's shopify_save_lager(), so a
// priority change needs no code change.
//
// 20261102 CL/MJ SD-761: created.
// 20261103 CL/MJ SD-761 review: the help, the empty-list note and the display column read as Lui's text review.

$title = 'Shopify lagre';
require __DIR__ . '/adminBootstrap.php';

$lagre = shopify_admin_lagre();
$roles = array();
foreach ($lagre as $row) {
	$roles[$row['lager']] = array('rolle' => $row['rolle'], 'prioritet' => $row['prioritet'], 'udstilling_af' => $row['udstilling_af']);
}
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$posted = is_array($_POST['lager'] ?? null) ? $_POST['lager'] : array();
	foreach (array_keys($roles) as $lager) {
		$row = is_array($posted[$lager] ?? null) ? $posted[$lager] : array();
		foreach (array('rolle', 'prioritet', 'udstilling_af') as $field) {
			$roles[$lager][$field] = trim((string)($row[$field] ?? ''));
		}
	}
	$errors = shopify_admin_validate_lagre($roles);
	if (!$errors) {
		try {
			shopify_db_transaction(function () use ($roles) {
				// The priority index is unique and not deferrable: clear the roles first, so two
				// lagre can swap priorities in one save.
				foreach (array_keys($roles) as $lager) {
					shopify_save_lager($lager, 'ingen');
				}
				foreach ($roles as $lager => $role) {
					$prioritet = $role['rolle'] === 'saelgbar' ? (int)$role['prioritet'] : null;
					$udstillingAf = $role['rolle'] === 'udstilling' ? (int)$role['udstilling_af'] : null;
					shopify_save_lager($lager, $role['rolle'], $prioritet, $udstillingAf);
				}
			});
			shopify_admin_redirect('lagre.php', 'shopify.admin.saved|Gemt.');
		} catch (ShopifyConfigError | ShopifyDbError | ShopifySchemaError $e) {
			$errors['form'] = 'shopify.admin.error.save|Ændringen kunne ikke gemmes. Prøv igen.';
		}
	}
}

$canWrite = $shopifyAccess === 'write';
$disabled = $canWrite ? '' : ' disabled';
$rolleLabels = array(
	'saelgbar' => 'shopify.admin.lager.saelgbar|Salgbart',
	'udstilling' => 'shopify.admin.lager.udstilling|Udstilling',
	'ingen' => 'shopify.admin.lager.ingen|Ingen',
);

shopify_admin_header('shopify.admin.lagre.title|Shopify-lagre', 'lagre.php');
print "<p style='margin:8px'>" . shopify_admin_t('shopify.admin.lagre.help|Har en webordre intet lager fra Shopify, trækkes den fra de salgbare lagre i prioriteret rækkefølge (1 først). Der sælges aldrig fra et udstillingslager.') . "</p>\n";
if (!$lagre) {
	print "<p style='margin:8px'>" . shopify_admin_t('shopify.admin.lagre.none|Der er ingen lagre. Opret dem under Indstillinger → Lager.') . "</p>\n";
	exit;
}
print "<form method='post' action='lagre.php' style='margin:8px'>\n";
print shopify_admin_form_fields();
if (isset($errors['form'])) {
	print '<p>' . shopify_admin_error($errors['form']) . "</p>\n";
}
print "<table cellpadding='4' cellspacing='0' border='1' style='border-collapse:collapse'>\n<tr>";
foreach (array('shopify.admin.lager.nr|Nr.', 'shopify.admin.lager.navn|Lager', 'shopify.admin.lager.rolle|Rolle', 'shopify.admin.lager.prioritet|Prioritet', 'shopify.admin.lager.udstilling_af|Hører til lager') as $token) {
	print '<th>' . shopify_admin_t($token) . '</th>';
}
print "</tr>\n";
foreach ($lagre as $row) {
	$lager = $row['lager'];
	$role = $roles[$lager];
	print '<tr><td>' . $lager . '</td><td>' . shopify_admin_h($row['beskrivelse']) . '</td>';
	print "<td><select name='lager[$lager][rolle]'$disabled>";
	foreach ($rolleLabels as $rolle => $token) {
		print "<option value='$rolle'" . ($role['rolle'] === $rolle ? ' selected' : '') . '>' . shopify_admin_t($token) . '</option>';
	}
	print '</select>' . (isset($errors["$lager.rolle"]) ? '<br>' . shopify_admin_error($errors["$lager.rolle"]) : '') . '</td>';
	print "<td><input name='lager[$lager][prioritet]' size='4' value='" . shopify_admin_h($role['prioritet']) . "'$disabled>";
	print (isset($errors["$lager.prioritet"]) ? '<br>' . shopify_admin_error($errors["$lager.prioritet"]) : '') . '</td>';
	print "<td><select name='lager[$lager][udstilling_af]'$disabled><option value=''></option>";
	foreach ($lagre as $other) {
		if ($other['lager'] !== $lager) {
			$selected = (string)$other['lager'] === $role['udstilling_af'] ? ' selected' : '';
			print "<option value='{$other['lager']}'$selected>" . shopify_admin_h($other['lager'] . ' ' . $other['beskrivelse']) . '</option>';
		}
	}
	print '</select>' . (isset($errors["$lager.udstilling_af"]) ? '<br>' . shopify_admin_error($errors["$lager.udstilling_af"]) : '') . "</td></tr>\n";
}
print "</table>\n";
if ($canWrite) {
	print "<p><input type='submit' value='" . shopify_admin_t('shopify.admin.save|Gem') . "'></p>\n";
}
print "</form>\n";
