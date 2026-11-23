<?php
// --- systemdata/shopify/adminValidation.php --- ver 5.1.0 --- 2026-11-23 ---
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
// 20261103 CL/MJ SD-761 review: one shop per legacy slot; a display lager belongs to a sellable lager.
// 20261123 CL/MJ SD-865: the lager-to-location mapping of a shop.

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
		$errors['shop_key'] = 'shopify.admin.error.shop_key|Kortnavnet skal være 1-40 tegn: a-z, 0-9, _ eller -.';
	}
	if (!preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', strtolower($input['domain']))) {
		$errors['domain'] = 'shopify.admin.error.domain|Domænet skal være en *.myshopify.com-adresse.';
	}
	if ($input['afd'] !== '' && !preg_match('/^[1-9][0-9]{0,8}$/', $input['afd'])) {
		$errors['afd'] = 'shopify.admin.error.afd|Vælg en afdeling.';
	}
	if (!in_array($input['legacy_slot'], array('', '4', '5', '6'), true)) {
		$errors['legacy_slot'] = 'shopify.admin.error.legacy_slot|Vælg 1., 2. eller 3. API-klient.';
	}
	foreach ($shops as $shop) {
		if ($shop['shop_key'] === $input['shop_key']) {
			if ($isNew) {
				$errors['shop_key'] = 'shopify.admin.error.shop_key_taken|En anden shop har allerede dette kortnavn.';
			}
			continue;
		}
		if (strtolower((string)$shop['domain']) === strtolower($input['domain'])) {
			$errors['domain'] = 'shopify.admin.error.domain_taken|En anden shop har allerede dette domæne.';
		}
		if ($input['afd'] !== '' && (string)$shop['afd'] === $input['afd']) {
			$errors['afd'] = 'shopify.admin.error.afd_taken|En anden shop bruger allerede denne afdeling.';
		}
		if ($input['legacy_slot'] !== '' && (string)($shop['legacy_slot'] ?? '') === $input['legacy_slot']) {
			$errors['legacy_slot'] = 'shopify.admin.error.legacy_slot_taken|En anden shop bruger allerede denne API-klient.';
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
 * the sellable lager it belongs to and takes no priority (K13), so displays never chain or loop.
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
				$errors["$lager.prioritet"] = 'shopify.admin.error.prioritet|Et salgbart lager skal have en prioritet på 1 eller mere.';
			} elseif (isset($priorities[$role['prioritet']])) {
				$errors["$lager.prioritet"] = 'shopify.admin.error.prioritet_taken|To salgbare lagre kan ikke have samme prioritet.';
			} else {
				$priorities[$role['prioritet']] = $lager;
			}
		}
		if ($role['rolle'] === 'udstilling') {
			$shows = $role['udstilling_af'];
			if (!preg_match('/^[0-9]{1,9}$/', $shows) || ($roles[(int)$shows]['rolle'] ?? '') !== 'saelgbar') {
				$errors["$lager.udstilling_af"] = 'shopify.admin.error.udstilling_af|Vælg det salgbare lager, udstillingen hører til.';
			}
		}
	}
	return $errors;
}

/**
 * Problems with the posted lager-to-location choices of one shop, keyed by lager; empty when they can
 * be saved. Only a lager without a location gets one here: moving or removing a mapping has to zero
 * the old location's stock first (shopify_stock_remap()), which the worker's remap command does. A
 * location has to be one of the shop's fetched locations and serves one lager only (K04).
 *
 * @param array<int, string> $chosen lager => posted location GID, '' for none
 * @param array<int, string> $mapped lager => the location GID it maps to today
 * @param list<string> $fetched the GIDs of the shop's fetched locations
 * @return array<int, string> lager => findtekst token
 */
function shopify_admin_validate_locations(array $chosen, array $mapped, array $fetched): array {
	$errors = array();
	$taken = array_flip($mapped);
	foreach ($chosen as $lager => $gid) {
		if ($gid === '' || ($mapped[$lager] ?? null) === $gid) {
			continue;
		}
		if (isset($mapped[$lager])) {
			$errors[$lager] = 'shopify.admin.mapping.error.remap|En lokation skiftes med worker-kommandoen remap, så lageret i den gamle lokation nulstilles først.';
		} elseif (!in_array($gid, $fetched, true)) {
			$errors[$lager] = 'shopify.admin.mapping.error.unknown|Lokationen findes ikke i shoppen. Hent lokationer igen.';
		} elseif (isset($taken[$gid])) {
			$errors[$lager] = 'shopify.admin.mapping.error.taken|Lokationen bruges allerede af et andet lager.';
		} else {
			$taken[$gid] = $lager;
		}
	}
	return $errors;
}
