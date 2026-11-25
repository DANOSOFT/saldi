<?php
// --- includes/entitlementRoutes.php --- ver 5.1.1 --- 2026.11.25 ---
// LICENSE: GNU GPL v2 or later. Copyright (c) 2026 Danosoft ApS
// 20261125 CDX/ASR Map phase-one entry points to catalogue features; shared order actions remain phase two.

/**
 * Only maps functions to features; no package names, prices or limits belong here.
 * Specialized pages are checked before their broad role key (budget shares finans.regnskab).
 * Reports, exports and ordinary invoicing have no package gate.
 *
 * @return array<string, string> Installation-relative script path => catalogue feature.
 */
function ent_page_features(): array
{
	return array(
		'finans/budget.php' => 'finans.budget',
		'debitor/genfakturer.php' => 'debitor.abonnement',
		'debitor/ret_genfakt.php' => 'debitor.abonnement',
		'debitor/rykker.php' => 'debitor.rykker',
		'debitor/ny_rykker.php' => 'debitor.rykker',
		'debitor/rykkertjek.php' => 'debitor.rykker',
		'debitor/afslut_rykker.php' => 'debitor.rykker',
		'debitor/pbsfakt.php' => 'debitor.betalingsservice',
		'debitor/pbs_import.php' => 'debitor.betalingsservice',
		'debitor/pbs_gensend.php' => 'debitor.betalingsservice',
		'debitor/pbsliste.php' => 'debitor.betalingsservice',
		'debitor/pbsfile.php' => 'debitor.betalingsservice',
		'debitor/peppol.php' => 'debitor.peppol',
		'debitor/api.php' => 'debitor.peppol',
		'systemdata/prisliste.php' => 'salg.prisgrupper',
		'systemdata/rabatgrupper.php' => 'salg.prisgrupper',
		'systemdata/lev_rabat.php' => 'salg.prisgrupper',
		'lager/optalling.php' => 'lager.optaelling',
		'lager/lagerflyt.php' => 'lager.multilocation',
		'lager/minmaxstock.php' => 'lager.minmax',
		'lager/modtageliste.php' => 'lager.barcode',
		'debitor/sendPlukliste.php' => 'lager.plukliste',
		'debitor/debitor_kommission.php' => 'kommission',
		'lager/webshopStock.php' => 'shop.advanced',
	);
}

/** @return string|null Feature of an authenticated phase-one entry point, or null for an ungated/shared page. */
function ent_feature_for_page(string $script): ?string
{
	foreach (ent_page_features() as $path => $feature) {
		if (substr($script, -strlen('/' . $path)) === '/' . $path) return $feature;
	}
	if (strpos($script, '/rental/') !== false || strpos($script, '/booking/') !== false) return 'booking';
	return null;
}

/** @return string|null Package ceiling for a role permission; the core keys deliberately have no ceiling. */
function ent_feature_for_permission(string $key): ?string
{
	$features = array('lager.varer' => 'lager.varer', 'kreditor.ordre' => 'kreditor.ordre',
		'lager.varemodtagelse' => 'lager.varemodtagelse', 'pos.kasse' => 'pos.kasse');
	return $features[$key] ?? null;
}

/** @return void Check a specialized entry point before the permission gate's early returns. */
function ent_guard_page(string $script): void
{
	$feature = ent_feature_for_page($script);
	if ($feature !== null && !ent_check($feature)) ent_upgrade($feature);
}

/** @return bool Package allowance for the same permission candidate the role gate is checking. */
function ent_permission_allowed(string $key, string $level = 'write', string $script = ''): bool
{
	// Existing records and exports remain accessible after a downgrade. POS pages
	// create sales on GET, so their entry point cannot use this read exception.
	if ($level === 'read' && in_array($key, array('lager.varer', 'kreditor.ordre', 'lager.varemodtagelse'), true)) return true;
	// Posting an existing supplier invoice is bookkeeping, including after downgrade.
	if ($key === 'kreditor.ordre' && substr($script, -strlen('/kreditor/bogfor.php')) === '/kreditor/bogfor.php') return true;
	$feature = ent_feature_for_permission($key);
	return $feature === null || ent_check($feature);
}

/** @return string|null Feature owning a dedicated settings section; shared core sections stay open. */
function ent_feature_for_settings(string $section): ?string
{
	if (strpos($section, 'pos.') === 0) return 'pos.kasse';
	$features = array('purchase.pricelists' => 'salg.prisgrupper', 'sales.discounts' => 'salg.prisgrupper',
		'sales.reminders' => 'debitor.rykker', 'sales.debtor_groups' => 'salg.prisgrupper',
		'items.consignment' => 'kommission');
	return $features[$section] ?? null;
}
