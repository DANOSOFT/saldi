<?php
// --- systemdata/shopify/adminValidation.php --- ver 5.1.0 --- 2026-11-02 ---
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
// Form validation of the Shopify admin pages (WP04). Pure functions without database access: the
// page passes the posted values and the rows it read, and gets back one text per field in error,
// as a findtekst() 'key|fallback' token. The unique keys of the schema still refuse what slips
// past a concurrent save; these checks give the user a readable message first.
//
// 20261102 CL/MJ SD-761: created.

/**
 * Problems with a posted shop, keyed by form field; empty when it can be saved.
 *
 * @param array{shop_key: string, domain: string, afd: string, is_primary: bool, legacy_slot: string, web_kasse: string} $input
 *   trimmed form values; shop_key is the edited shop's key when $isNew is false
 * @param list<array<string, string|null>> $shops every saved shop (shopify_shops rows)
 * @return array<string, string> field => findtekst token
 */
function shopify_admin_validate_shop(array $input, array $shops, bool $isNew): array {
	$errors = array();
	if ($isNew && !preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $input['shop_key'])) {
		$errors['shop_key'] = 'shopify.admin.error.shop_key|Nøglen skal være 1-40 tegn: a-z, 0-9, _ eller -.';
	}
	if (!preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', strtolower($input['domain']))) {
		$errors['domain'] = 'shopify.admin.error.domain|Domænet skal være en *.myshopify.com-adresse.';
	}
	if ($input['afd'] !== '' && !preg_match('/^[1-9][0-9]{0,8}$/', $input['afd'])) {
		$errors['afd'] = 'shopify.admin.error.afd|Vælg en afdeling.';
	}
	if (!in_array($input['legacy_slot'], array('', '4', '5', '6'), true)) {
		$errors['legacy_slot'] = 'shopify.admin.error.legacy_slot|Den gamle shopplads skal være 4, 5 eller 6.';
	}
	foreach ($shops as $shop) {
		if ($shop['shop_key'] === $input['shop_key']) {
			if ($isNew) {
				$errors['shop_key'] = 'shopify.admin.error.shop_key_taken|En anden shop har allerede denne nøgle.';
			}
			continue;
		}
		if (strtolower((string)$shop['domain']) === strtolower($input['domain'])) {
			$errors['domain'] = 'shopify.admin.error.domain_taken|En anden shop har allerede dette domæne.';
		}
		if ($input['afd'] !== '' && (string)$shop['afd'] === $input['afd']) {
			$errors['afd'] = 'shopify.admin.error.afd_taken|En anden shop bruger allerede denne afdeling.';
		}
		if ($input['is_primary'] && $shop['is_primary'] === 't') {
			$errors['is_primary'] = 'shopify.admin.error.primary_taken|En anden shop er allerede primær.';
		}
	}
	return $errors;
}

/**
 * Problems with the posted lager roles, keyed '<lager>.<field>'; empty when they can be saved.
 * A sellable lager needs a priority, unique among the sellable ones (K16); a display lager names
 * another known lager it displays and takes no priority (K13).
 *
 * @param array<int, array{rolle: string, prioritet: string, udstilling_af: string}> $roles lager => trimmed form values
 * @return array<string, string> '<lager>.<field>' => findtekst token
 */
function shopify_admin_validate_lagre(array $roles): array {
	$errors = array();
	$priorities = array();
	foreach ($roles as $lager => $role) {
		if (!in_array($role['rolle'], array('saelgbar', 'udstilling', 'ingen'), true)) {
			$errors["$lager.rolle"] = 'shopify.admin.error.rolle|Vælg en rolle.';
			continue;
		}
		if ($role['rolle'] === 'saelgbar') {
			if (!preg_match('/^[1-9][0-9]{0,5}$/', $role['prioritet'])) {
				$errors["$lager.prioritet"] = 'shopify.admin.error.prioritet|Et salgbart lager skal have en prioritet (et helt tal fra 1).';
			} elseif (isset($priorities[$role['prioritet']])) {
				$errors["$lager.prioritet"] = 'shopify.admin.error.prioritet_taken|To salgbare lagre kan ikke have samme prioritet.';
			} else {
				$priorities[$role['prioritet']] = $lager;
			}
		}
		if ($role['rolle'] === 'udstilling') {
			$shows = $role['udstilling_af'];
			if (!preg_match('/^[0-9]{1,9}$/', $shows) || !isset($roles[(int)$shows]) || (int)$shows === (int)$lager) {
				$errors["$lager.udstilling_af"] = 'shopify.admin.error.udstilling_af|Vælg det lager, som udstillingslageret viser.';
			}
		}
	}
	return $errors;
}
