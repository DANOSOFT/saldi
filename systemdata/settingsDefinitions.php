<?php
// ----------------systemdata/settingsDefinitions.php --- Settings registry v2 --- 2026-10-10 ----
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260929 Sawaneh Registry v2 (Requirements_settings_redesign_EN.md §7.1): the definition of each
// 20260930 Sawaneh Field links use ?field= so they work through the shell (spec §8.11).
// 20261002 Sawaneh Phase 4b batch 1: G2.5 cash journal & payments, G3.6 mySale, G5.6 consignment, G5.7 packaging,
//                  G6.4 print, G7.4 commission; 'module' gates a section, 'on_save' names a follow-up, type 'date'.
// 20261006 Sawaneh 4c G3.5 part A: price, campaign and item quantity-discount groups (VPG/VTG/VRG) as rows in Salg » Rabatter
//                  & prisgrupper; saving no longer rewrites item prices - "Anvend på varer" per group does, after a confirmation.
//                  Part B: customer and item discount groups (DRG/DVRG) and the discount matrix (virtual discount_matrix, by
//                  group number); rabatgrupper.php redirects.
// 20261006 Sawaneh 4c B-D06: "Flyt samlekonto" on debtor/creditor groups as a confirmed row action (amount shown first).
// 20261006 Sawaneh 4c G5.3 Varianter: variant types and their values as rows (values filtered by type), usage from
//                  variant items, CSV import through importer_varianter.php; diverse.php?sektion=variant_valg redirects.
// 20261006 Sawaneh §8.13: 'standard' on the VAT, VAT report, debtor/creditor/item group and unit tables ("Opret dansk standardsæt").
// 20261006 Sawaneh Onboarding step 4 (acceptance 6): payment terms and days on Firmaoplysninger (company row betalingsbet/
//                  betalingsdage), used by a new customer card while there are no customers yet.
// 20261005 Sawaneh 4d G6.2 Baggrunde: form backgrounds (VSPR) as a row list - new ones copied from a template background,
//                  deleted with their form lines only when no customer, supplier or order uses them - and the PDF files.
// 20261005 Sawaneh 4d G11: Import & eksport (export/import per data type, chart of accounts per fiscal year) and
//                  Sikkerhedskopi (latest copy, take a copy, restore) framing the existing pages.
// 20261005 Sawaneh 4d G1.4 Abonnement & konto (Saldi-hosted only): ledger name (master database), activity, and the
//                  danger zone - reset (keep customers/suppliers, keep items) and delete, both with password.
// 20261005 Sawaneh 4d G7.1 Ansatte: list of the company's employees with a card each (person, contact, employment, CPR
//                  and salary behind settings.organisation.sensitive, extra fields, linked user and its form language),
//                  add, move up/down (posnr) and delete with a usage check.
// 20261005 Sawaneh 4d G1.2 Regnskabsår (row list, create card shared with onboarding, set active, delete empty or old
//                  year) and G2.3 Valuta (currencies and rates per currency, rate changes confirmed before posting).
// 20261005 Sawaneh 4d G1.3 Lokalisering (five settings that had no or scattered UI, translations link) and G1.5 Persondata
//                  (inactive customers/suppliers clean-up as a danger-zone action with password).
// 20261005 Sawaneh 4d G1.1 Stamdata as a generated section on the company address row (no notes/kontonr overwrite,
//                  no double escaping, country always shown but changed only with settings.company.danger); employees
//                  stay on the old page until G7.1.
// 20261005 Sawaneh 4c stage 2: VAT codes (SM/KM/YM/EM per year, code 1-9, usage check, inactive) and VAT report accounts,
//                  debtor and creditor groups (control account locked while in use, discount shown again, EU zone to all
//                  years), item groups (box12/box14 left out, box10 kept for Sager/payroll); price groups stay on the old page.
// 20261005 Sawaneh Phase 4c: sections of 'kind' rows (tables with columns, usage checks, 'exclude', 'code_col',
//                  'on_save') for departments, projects, warehouses and units & materials; the project number split
//                  as a field on the PRJ kodenr 0 row.
// 20261004 Sawaneh 4b gaps: discount decimals (G3.3), payment link per till, link to confirm-stock-change from G4.2,
//                  PDF command (G6.4), NemHandel status (G9.4), DFM settings in their own group (B-D5), pickup
//                  addresses as a list section ('per' => 'pickup', settings group_id), bank item behind its feature,
//                  bank status in the cash journal as a personal setting (G2.7).
// 20261004 Sawaneh G10 batch B: Cards (one drawer per payment card, 'per' => 'card'), Tables & floor plans ('per' => 'table',
//                  'per' => 'floor_plan' on table_pages rows), currencies per till, Move3500 login per till, KDS colours by
//                  waiting time, Flatpay receipt print; the old PoS-valg page is retired.
// 20261004 Sawaneh G10.1 Tills: list section with one drawer per till ('per' => 'till'), lists joined by tab with 'list',
//                  Cash counting form section; 'decimal_comma', 'seed' for a joined settings row.
// 20261004 Sawaneh G10 POS batch A: G10.2 receipt, G10.6 kitchen, G10.7 screen (shop-wide); 'fiscal' grupper storage.
// 20261004 Sawaneh G4.3 Price lists: a list section whose items come from the database ('items_from'), 'per' => 'pricelist'
//                  fields on storage 'grupper_row', 'value_map', rules 'required' and 'csv_url', a page-level 'add_action'.
// 20261004 Sawaneh G2.6 Document storage: storage 'virtual', encoding 'urlencode' for a secret, 'ensure_suffix'.
// 20261003 Sawaneh G3.4 Reminders: storage 'formularer', type 'creditor', 'options_from' users, 'group_label' and 'sub_help'.
// 20261003 Sawaneh G6.3 E-mail: storage 'adresser', 'per' => 'language' fields expanded per form language (scope 'group'),
//                  type 'textarea', a section-level 'permission'.
// 20261002 Sawaneh Phase 4b batch 2: G9 Integrations as a list section ('kind' list, 'items', 'sub_module'); types secret,
//                  info, link and mini; 'options_from', 'options_literal', 'locked_if' ht_keys:, rule 'setting_set'.
// 20261002 Sawaneh Merge of master: its new batchExpiryEnabled setting (was on the removed Varerelaterede valg page) is
//                  items.stock.batch_expiry.
//                  setting - key, type, scope, group/section, label and help text ids, default,
//                  storage (the EXISTING location and encoding), permission, dependencies.
//                  Section pages, save logic, search, change history and the "moved" page are
//                  generated from this file. Included by settingsRegistry.php.
// 20261008 Sawaneh Settings decisions 19-20: vat.registered and vat.period at the top of G2.2 (settings var_grp 'vat',
//                  company scope); the discount matrix keeps only rows marked 'NR' (by group number), older rows are
//                  listed as inactive in G3.5 with a per-row Overfør.
// 20261008 Sawaneh Merge of master: hideRevenue (SD-657) in G3.3, the HTML/CSS layout version (forms/htmlLayoutVersion) in
//                  G6.4, and "Gem kurs uden bogføring" (SST-769) as 'confirm_skip' on the currency rates.
// 20261008 Sawaneh personal.profile.theme: light, dark or system (prototype_dashboard_tema v5), settings ui/theme per user.
// 20261009 Sawaneh 4e: personal.documents.auto_extract and personal.ui.sidebar_closed replace the autoExtract/isSidebarOpen cookies.
// 20261012 Sawaneh personal.ui.sidebar_closed removed again: the sidebar is a per-device cookie (Adam, settings spec §7.4 (5)).
// 20261009 Sawaneh pos.tills.currency_account_<n>: the per-currency till account as its own per-till key (decision 22), VK box4 stays the flag.
// 20261009 Sawaneh personal.profile.text_size (Normal / Større, front-page addendum 3 §3.3).
// 20261010 Sawaneh G5.8: Diverse → Mærkater moved to labels.php.
// 20261019 Sawaneh Review 2 (PR #803): settings_floor_plans() limits its information_schema check to this database (saldi_schema_filter, with a fallback like db_cast).
// 20261023 ASR Merge of master (#815 removed lager/vareliste.php): the warehouses gear link names lager/lister/vareliste.php, the list that is left.
//
// Storage encodings mirror the current writer (risk review R23):
//   onEmpty  'on' / ''        onOff  'on' / 'off'        oneZero  '1' / '0'        raw  as typed
// 'join' + 'index' address one part of a composite box (R7).
// 'legacy' is the old menu path as text ids; it feeds the "Tidligere: ..." search tag (§8.10).

// The registry is also read without a database connection (tools/check_settings_registry.php): the same cast
// as includes/db_query.php's db_cast(), so the definitions load either way.
if (!function_exists('db_cast')) {
	function db_cast($expr, $type) {
		global $db_type;
		if (in_array($db_type ?? 'postgres', array('mysql', 'mysqli'), true)) {
			return "cast($expr as " . ($type === 'text' ? 'char' : 'signed') . ")";
		}
		return "cast($expr as $type)";
	}
}
if (!function_exists('saldi_schema_filter')) {
	function saldi_schema_filter() {
		global $db_type;
		return in_array($db_type ?? 'postgres', array('mysql', 'mysqli'), true) ? " and table_schema = DATABASE()" : " and table_schema = current_schema()";
	}
}

if (!function_exists('getSettingsSections')) {
	/**
	 * Sections that are generated from the registry, in display order within their group.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function getSettingsSections(): array
	{
		return array(
			'sales.debtor_card' => array(
				'group' => 'sales', 'section' => 'debtor_card', 'number' => 'G3.2', 'label' => 5681, 'icon' => 'bx-id-card',
				'subsections' => array('card' => 5681),
				'legacy' => array(array(782, 794), array(782, 786)),
				'context' => array('debitor/debitorkort.php'),
				'keywords' => array('debitorkort', 'kundekort', 'customer card', 'debtor card', 'jobkort', 'job cards', 'debitoripad', 'kundeansvarlig'),
			),
			'sales.orders' => array(
				'group' => 'sales', 'section' => 'orders', 'number' => 'G3.3', 'label' => 5679, 'icon' => 'bx-receipt',
				'subsections' => array('prices' => 5683, 'invoicing' => 5684, 'mass' => 200, 'items' => 5685, 'window' => 5686, 'packing' => 5687),
				'legacy' => array(array(782, 786), array(782, 200)),
				'old' => array('ordre_valg' => array(782, 786), 'massefakt' => array(782, 200)),
				'context' => array('debitor/ordre.php', 'debitor/ordreliste.php'),
				'keywords' => array('ordre', 'order', 'faktura', 'invoice', 'invoicing', 'kunde', 'kunder', 'hurtigfakturering', 'massefakturering', 'følgeseddel', 'plukliste'),
			),
			'purchase.orders' => array(
				'group' => 'purchase', 'section' => 'orders', 'number' => 'G4.2', 'label' => 5682, 'icon' => 'bx-archive-in',
				'subsections' => array('posting' => 5692),
				'legacy' => array(array(782, 786)),
				'context' => array('kreditor/ordre.php'),
				'keywords' => array('købsordre', 'indkøb', 'purchase order', 'leverandør', 'supplier'),
			),
			'items.stock' => array(
				'group' => 'items', 'section' => 'stock', 'number' => 'G5.5', 'label' => 5680, 'icon' => 'bx-package',
				'subsections' => array('stock' => 5688, 'cost' => 5689, 'mail' => 5690, 'card' => 5691),
				'legacy' => array(array(782, 786), array(782, 787)),
				'old' => array('productOptions' => array(782, 787)),
				'context' => array('lager/varekort.php', 'lager/varer.php'),
				'keywords' => array('lager', 'stock', 'inventory', 'kostpris', 'cost price', 'fifo', 'beholdning', 'minimumsbeholdning'),
			),
			// ---- phase 4b
			'finance.cash_journal' => array(
				'group' => 'finance', 'section' => 'cash_journal', 'number' => 'G2.5', 'label' => 5983, 'icon' => 'bx-book',
				'subsections' => array('journal' => 601, 'payments' => 532, 'due' => 2732, 'rounding' => 170),
				'legacy' => array(array(782, 794), array(782, 170), array(2732)),
				'old' => array('div_valg' => array(782, 794), 'orediff' => array(782, 170), 'betalinger' => array(2732)),
				'context' => array('finans/kassekladde.php', 'debitor/betalingsliste.php', 'kreditor/betalingsliste.php'),
				'keywords' => array('kassekladde', 'cash journal', 'betalingsliste', 'payment list', 'betalingsfrist', 'payment days', 'øredifferencer', 'rounding', 'bilagsnummer', 'voucher'),
			),
			// ---- 4d standalone pages
			'company.data' => array(
				'group' => 'company', 'section' => 'data', 'number' => 'G1.1', 'label' => 779, 'icon' => 'bx-buildings',
				'subsections' => array('company' => 6520, 'contact' => 6522, 'bank' => 6521, 'gdpr' => 6523),
				'legacy' => array(array(779)), 'old' => array('stamkort' => array(779)),
				'context' => array('debitor/ordre.php'),
				'keywords' => array('stamdata', 'firmanavn', 'company name', 'adresse', 'cvr', 'bank', 'iban', 'swift', 'betalingsservice', 'bs', 'fi', 'gdpr', 'databehandleraftale', 'landekonfiguration'),
			),
			'company.localisation' => array(
				'group' => 'company', 'section' => 'localisation', 'number' => 'G1.3', 'label' => 6524, 'icon' => 'bx-globe',
				'subsections' => array('locale' => 6524, 'texts' => 6534), 'sub_help' => array('locale' => 6525),
				'legacy' => array(array(782, 801)), 'old' => array('sprog' => array(782, 801)),
				'keywords' => array('lokalisering', 'localisation', 'basisvaluta', 'base currency', 'tidszone', 'timezone', 'talformat', 'number format', 'systemsprog', 'sprog', 'language', 'oversættelser', 'translations', 'tekster'),
			),
			'import_export.data' => array(
				'group' => 'import_export', 'section' => 'data', 'number' => 'G11.1', 'label' => 5539, 'icon' => 'bx-transfer',
				'lead' => 6646, 'subsections' => array('accounts' => 1352, 'addresses' => 6648, 'items' => 609, 'forms' => 780),
				'legacy' => array(array(782, 802)), 'old' => array('div_io' => array(782, 802)),
				'keywords' => array('import', 'eksport', 'export', 'indlæs', 'udlæs', 'csv', 'kontoplan', 'debitorer', 'kreditorer', 'kunder', 'leverandører', 'varer', 'variantvarer', 'varelokationer', 'formularer', 'standardkontoplan', 'mapping'),
			),
			'import_export.backup' => array(
				'group' => 'import_export', 'section' => 'backup', 'number' => 'G11.3', 'label' => 6665, 'icon' => 'bx-data', 'permission' => 'settings.backup',
				'lead' => 6650, 'subsections' => array('backup' => 6665, 'restore' => 1247), 'sub_help' => array('restore' => 6651),
				'keywords' => array('backup', 'sikkerhedskopi', 'gendan', 'restore', 'indlæs sikkerhedskopi', 'tag backup'),
			),
			'company.account' => array(
				'group' => 'company', 'section' => 'account', 'number' => 'G1.4', 'label' => 6630, 'icon' => 'bx-key', 'module' => 'hosted',
				'lead' => 6631, 'subsections' => array('account' => 6632, 'danger' => 6633), 'sub_help' => array('danger' => 6634),
				'legacy' => array(array(782, 783)), 'old' => array('kontoindstillinger' => array(782, 783)),
				'keywords' => array('abonnement', 'konto', 'regnskabets navn', 'skift navn', 'omdøb', 'nulstil regnskab', 'reset', 'slet regnskab', 'delete ledger', 'farezone', 'danger zone'),
			),
			'company.gdpr' => array(
				'group' => 'company', 'section' => 'gdpr', 'number' => 'G1.5', 'label' => 6523, 'icon' => 'bx-shield-quarter',
				'subsections' => array('cleanup' => 6537), 'sub_help' => array('cleanup' => 6538),
				'legacy' => array(array(779)),
				'keywords' => array('gdpr', 'persondata', 'personal data', 'inaktive kunder', 'slet kunder', 'oprydning', 'cleanup'),
			),
			'company.fiscal_years' => array(
				'group' => 'company', 'section' => 'fiscal_years', 'number' => 'G1.2', 'label' => 894, 'icon' => 'bx-calendar', 'kind' => 'rows',
				'lead' => 6547, 'subsections' => array('years' => 894),
				'tables' => array(
					'years' => array('sub' => 'years', 'label' => 894, 'add' => 508, 'empty' => 6565, 'storage' => array('grupper', 'RA'), 'no_add' => true,
						'usage' => 'fiscal_year', 'on_delete' => 'fiscal_year_empty', 'row_locked' => 'fiscal_year_deleted', 'create' => 'fiscal_year',
						'order' => db_cast('kodenr', 'integer'),
						'columns' => array(
							'kodenr' => array('label' => 6548, 'type' => 'code', 'readonly' => true),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'period' => array('label' => 6549, 'type' => 'derived', 'derive' => 'fy_period'),
							'box5' => array('label' => 6550, 'type' => 'bool'),
							'status' => array('label' => 6551, 'type' => 'derived', 'derive' => 'fy_status'),
						),
						'row_actions' => array(
							'fy_activate' => array('label' => 1213),
							'fy_activate_all' => array('label' => 6555, 'confirm_title' => 6555, 'confirm' => 6605),
							'fy_opening' => array('label' => 6556, 'href' => 'regnskabskort.php?id=%d'),
							'fy_archive' => array('label' => 6557, 'confirm_title' => 6571, 'confirm' => 6572, 'danger' => true, 'danger_zone' => true),
						)),
				),
				'legacy' => array(array(778)), 'old' => array('regnskabsaar' => array(778), 'regnskabskort' => array(778)),
				'keywords' => array('regnskabsår', 'fiscal year', 'financial year', 'regnskabsperiode', 'periode', 'nyt regnskabsår', 'opret regnskabsår', 'åbningsbalance', 'primo', 'sæt aktivt', 'slet regnskabsår', 'bogføring tilladt'),
			),
			'finance.currencies' => array(
				'group' => 'finance', 'section' => 'currencies', 'number' => 'G2.3', 'label' => 776, 'icon' => 'bx-dollar-circle', 'kind' => 'rows',
				'lead' => 6580, 'subsections' => array('currencies' => 776, 'rates' => 6587),
				'tables' => array(
					'currencies' => array('sub' => 'currencies', 'label' => 776, 'add' => 6585, 'empty' => 6586, 'storage' => array('grupper', 'VK'), 'code_col' => 'box1',
						'auto_code' => true, 'usage' => 'currency', 'order' => 'box1',
						'columns' => array(
							'box1' => array('label' => 6581, 'type' => 'select', 'options_from' => 'iso_currencies', 'required' => true, 'locked_if_used' => true, 'forbid' => array('base_currency', 6593)),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box3' => array('label' => 6582, 'type' => 'account', 'kontotype' => 'D', 'required' => true, 'help' => 1705),
							'box4' => array('label' => 6583, 'type' => 'bool', 'true_value' => '1', 'false_value' => '0'),
							'rate' => array('label' => 6584, 'type' => 'derived', 'derive' => 'currency_rate_now'),
						)),
					'rates' => array('sub' => 'rates', 'label' => 6587, 'help' => 6588, 'help_args' => 'base_currency', 'add' => 6590, 'empty' => 6591, 'storage' => array('table', 'valuta'),
						'code_col' => 'valdate', 'filter' => array('param' => 'cur', 'column' => 'gruppe', 'label' => 776, 'options' => 'currencies'),
						'usage' => 'currency_rate', 'confirm' => 'currency_rate', 'confirm_skip' => array(5153, 5154), 'row_check' => 'currency_rate', 'before_row' => 'currency_rate', 'on_save' => 'currency_rates',
						'order' => 'valdate desc, id desc',
						'columns' => array(
							'valdate' => array('label' => 635, 'type' => 'date', 'required' => true, 'help' => 1703),
							'kurs' => array('label' => 6589, 'type' => 'decimal', 'required' => true, 'locked_if_used' => true),
						)),
				),
				'legacy' => array(array(776)), 'old' => array('valuta' => array(776), 'valutakort' => array(776)),
				'context' => array('finans/kassekladde.php'),
				'keywords' => array('valuta', 'valutaer', 'currency', 'currencies', 'valutakode', 'kurs', 'valutakurs', 'exchange rate', 'kursdifference', 'kursændring', 'pos valuta', 'vis i kassen'),
			),
			// ---- 4c master data (row editor, spec §8.2)
			'organisation.departments' => array(
				'group' => 'organisation', 'section' => 'departments', 'number' => 'G7.2', 'label' => 772, 'icon' => 'bx-sitemap', 'kind' => 'rows',
				'subsections' => array('departments' => 772),
				'tables' => array(
					'departments' => array('sub' => 'departments', 'label' => 772, 'help' => 6395, 'add' => 6396, 'empty' => 6397,
						'storage' => array('grupper', 'AFD'), 'usage' => 'department',
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code'),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 6398, 'type' => 'derived', 'derive' => 'department_warehouse', 'help' => 6400),
							'box2' => array('label' => 2552, 'type' => 'text'),
						)),
				),
				'legacy' => array(array(772)), 'old' => array('afdelinger' => array(772)),
				'context' => array('sager/ansatte.php'),
				'keywords' => array('afdelinger', 'departments', 'afdeling', 'department'),
			),
			'organisation.employees' => array(
				'group' => 'organisation', 'section' => 'employees', 'number' => 'G7.1', 'label' => 1262, 'icon' => 'bx-id-card', 'kind' => 'list',
				'lead' => 6606, 'items_from' => 'employees', 'add_action' => 'organisation.employees.add', 'empty_text' => 6607,
				'subsections' => array('employees' => 1262),
				'legacy' => array(array(779)), 'old' => array('ansatte' => array(779), 'stamkort' => array(779)),
				'keywords' => array('ansatte', 'medarbejdere', 'employees', 'personale', 'personalekort', 'medarbejdernummer', 'initialer', 'løn', 'cpr', 'fratrådt', 'tiltrådt', 'ekstra felter'),
			),
			'organisation.projects' => array(
				'group' => 'organisation', 'section' => 'projects', 'number' => 'G7.3', 'label' => 773, 'icon' => 'bx-briefcase', 'kind' => 'rows',
				'subsections' => array('setup' => 1249, 'projects' => 773),
				'tables' => array(
					'projects' => array('sub' => 'projects', 'label' => 773, 'help' => 6403, 'add' => 6404, 'empty' => 6405,
						'storage' => array('grupper', 'PRJ'), 'usage' => 'project', 'exclude' => db_cast('kodenr', 'text') . " <> '0'", 'row_name' => 'projekt',
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code', 'numeric' => false),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
						)),
				),
				'legacy' => array(array(773)), 'old' => array('projekter' => array(773)),
				'context' => array('finans/kassekladde.php'),
				'keywords' => array('projekter', 'projects', 'projekt', 'projektnummer', 'projektopdeling'),
			),
			'items.warehouses' => array(
				'group' => 'items', 'section' => 'warehouses', 'number' => 'G5.4', 'label' => 6399, 'icon' => 'bx-building-house', 'kind' => 'rows',
				'subsections' => array('warehouses' => 6399),
				'tables' => array(
					'warehouses' => array('sub' => 'warehouses', 'label' => 6399, 'help' => 6400, 'add' => 6401, 'empty' => 6402,
						'storage' => array('grupper', 'LG'), 'usage' => 'warehouse', 'on_save' => 'warehouses_to_departments',
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code'),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 2464, 'type' => 'select', 'options_from' => 'departments', 'options_literal' => true),
						)),
				),
				'legacy' => array(array(608)), 'old' => array('lagre' => array(608)),
				'context' => array('lager/lister/vareliste.php', 'lager/varekort.php'),
				'keywords' => array('lagre', 'lager', 'warehouses', 'warehouse', 'lagersted'),
			),
			'items.units' => array(
				'group' => 'items', 'section' => 'units', 'number' => 'G5.2', 'label' => 6407, 'icon' => 'bx-ruler', 'kind' => 'rows',
				'lead' => 6408, 'subsections' => array('units' => 1259, 'materials' => 6411),
				'tables' => array(
					'units' => array('sub' => 'units', 'label' => 1259, 'add' => 6409, 'empty' => 6410,
						'storage' => array('table', 'enheder'), 'usage' => 'unit', 'code_col' => 'betegnelse', 'standard' => array(array('betegnelse' => 'stk', 'beskrivelse' => 'styk')),
						'columns' => array(
							'betegnelse' => array('label' => 6442, 'type' => 'code', 'numeric' => false, 'required' => true),
							'beskrivelse' => array('label' => 914, 'type' => 'text'),
						)),
					'materials' => array('sub' => 'materials', 'label' => 6411, 'add' => 6412, 'empty' => 6413,
						'storage' => array('table', 'materialer'), 'code_col' => 'beskrivelse',
						'columns' => array(
							'beskrivelse' => array('label' => 570, 'type' => 'code', 'numeric' => false, 'required' => true),
							'densitet' => array('label' => 569, 'type' => 'decimal'),
						)),
				),
				'legacy' => array(array(781)), 'old' => array('enheder' => array(781)),
				'context' => array('lager/varekort.php'),
				'keywords' => array('enheder', 'units', 'enhed', 'unit', 'materialer', 'materials', 'densitet', 'density'),
			),
			'items.variants' => array(
				'group' => 'items', 'section' => 'variants', 'number' => 'G5.3', 'label' => 472, 'icon' => 'bx-palette', 'kind' => 'rows',
				'lead' => 6827, 'subsections' => array('types' => 6828, 'values' => 6829, 'import' => 1356),
				'tables' => array(
					'types' => array('sub' => 'types', 'label' => 6828, 'help' => 6830, 'add' => 6831, 'empty' => 6832,
						'storage' => array('table', 'varianter'), 'code_col' => 'beskrivelse', 'usage' => 'variant_type', 'on_delete' => 'variant_type_values', 'order' => 'beskrivelse, id',
						'columns' => array(
							'beskrivelse' => array('label' => 6833, 'type' => 'text', 'required' => true, 'unique_text' => 6834),
							'values' => array('label' => 6829, 'type' => 'derived', 'derive' => 'variant_values'),
						)),
					'values' => array('sub' => 'values', 'label' => 6829, 'help' => 6835, 'add' => 6836, 'empty' => 6837,
						'storage' => array('table', 'variant_typer'), 'code_col' => 'beskrivelse', 'usage' => 'variant_value', 'order' => 'beskrivelse, id',
						'filter' => array('param' => 'type', 'column' => 'variant_id', 'label' => 6833, 'options' => 'variant_types'),
						'columns' => array(
							'beskrivelse' => array('label' => 6838, 'type' => 'text', 'required' => true, 'unique_text' => 6834),
						)),
				),
				'legacy' => array(array(788)), 'old' => array('variant_valg' => array(788)),
				'context' => array('lager/varekort.php'),
				'keywords' => array('varianter', 'variants', 'variant', 'farve', 'color', 'størrelse', 'size', 'varianttyper', 'variantværdier', 'variantrelaterede valg', 'import varianter'),
			),
			'finance.vat' => array(
				'group' => 'finance', 'section' => 'vat', 'number' => 'G2.2', 'label' => 770, 'icon' => 'bx-receipt', 'kind' => 'rows',
				'lead' => 6468, 'subsections' => array('registration' => 6898, 'sales' => 994, 'purchase' => 996, 'services' => 997, 'goods' => 998, 'report' => 1009),
				'tables' => array(
					'sales' => array('sub' => 'sales', 'label' => 994, 'help' => 2247, 'add' => 6469, 'empty' => 6470, 'storage' => array('grupper', 'SM'), 'standard' => 'grupper', 'kode' => 'S',
						'fiscal' => true, 'usage' => 'vat', 'inactive' => true,
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code', 'range' => array(1, 9, 6472)),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 440, 'type' => 'account', 'required' => true, 'help' => 2245),
							'box2' => array('label' => 995, 'type' => 'decimal', 'required' => true),
							'box6' => array('label' => 6471, 'type' => 'text'),
							'box7' => array('label' => 2995, 'type' => 'select', 'options' => array('' => '–', 'varer' => 110, 'ydelser' => 6501), 'options_mixed' => true),
						)),
					'purchase' => array('sub' => 'purchase', 'label' => 996, 'help' => 2431, 'add' => 6469, 'empty' => 6470, 'storage' => array('grupper', 'KM'), 'standard' => 'grupper', 'kode' => 'K',
						'fiscal' => true, 'usage' => 'vat', 'inactive' => true,
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code', 'range' => array(1, 9, 6472)),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 440, 'type' => 'account', 'required' => true),
							'box2' => array('label' => 995, 'type' => 'decimal', 'required' => true),
						)),
					'services' => array('sub' => 'services', 'label' => 997, 'help' => 2444, 'add' => 6469, 'empty' => 6470, 'storage' => array('grupper', 'YM'), 'standard' => 'grupper', 'kode' => 'Y',
						'fiscal' => true, 'usage' => 'vat', 'inactive' => true,
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code', 'range' => array(1, 9, 6472)),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 440, 'type' => 'account', 'required' => true, 'help' => 2432),
							'box2' => array('label' => 995, 'type' => 'decimal', 'required' => true),
							'box3' => array('label' => 1013, 'type' => 'account', 'help' => 2433),
						)),
					'goods' => array('sub' => 'goods', 'label' => 998, 'help' => 2445, 'add' => 6469, 'empty' => 6470, 'storage' => array('grupper', 'EM'), 'standard' => 'grupper', 'kode' => 'E',
						'fiscal' => true, 'usage' => 'vat', 'inactive' => true,
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code', 'range' => array(1, 9, 6472)),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 440, 'type' => 'account', 'required' => true, 'help' => 2434),
							'box2' => array('label' => 995, 'type' => 'decimal', 'required' => true),
							'box3' => array('label' => 1013, 'type' => 'account', 'help' => 2435),
						)),
					'report' => array('sub' => 'report', 'label' => 1009, 'add' => 6469, 'empty' => 6470, 'storage' => array('grupper', 'MR'), 'standard' => 'grupper', 'kode' => 'R', 'fiscal' => true,
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code'),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 903, 'type' => 'account', 'help' => 2436),
							'box2' => array('label' => 904, 'type' => 'account', 'help' => 2437),
							'box3' => array('label' => 6473, 'type' => 'account', 'help' => 2438),
							'box4' => array('label' => 6474, 'type' => 'account', 'help' => 2439),
						)),
				),
				'legacy' => array(array(770)), 'old' => array('moms' => array(770)),
				'context' => array('finans/moms_periode.php'),
				'keywords' => array('moms', 'vat', 'momskode', 'momskoder', 'salgsmoms', 'købsmoms', 'momsrapport', 'oss', 'moms-satser', 'momssats', 'rubrik'),
			),
			'sales.debtor_groups' => array(
				'group' => 'sales', 'section' => 'debtor_groups', 'number' => 'G3.1', 'label' => 1008, 'icon' => 'bx-group', 'kind' => 'rows',
				'lead' => 6505, 'subsections' => array('groups' => 1008),
				'tables' => array(
					'groups' => array('sub' => 'groups', 'label' => 1008, 'add' => 6475, 'empty' => 6478, 'storage' => array('grupper', 'DG'), 'standard' => 'grupper', 'row_actions' => array('move_control' => array('label' => 6503, 'confirm_title' => 6503, 'confirm' => 6885, 'confirm_args' => 'move_control', 'input' => 6886, 'danger' => true)),  'kode' => 'D',
						'fiscal' => true, 'usage' => 'debtor_group', 'inactive' => true, 'propagate' => array('box10'), 'defaults' => array('box3' => 'DKK'),
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code'),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 1011, 'type' => 'select', 'options_from' => 'vat_codes_sales', 'options_literal' => true, 'help' => 2447),
							'box2' => array('label' => 2448, 'type' => 'account', 'required' => true, 'help' => 2449, 'locked_if_used' => 6887),
							'box3' => array('label' => 776, 'type' => 'select', 'options_from' => 'currencies', 'options_literal' => true, 'empty_value' => 'DKK'),
							'box4' => array('label' => 801, 'type' => 'select', 'options_from' => 'form_language_names', 'options_literal' => true, 'help' => 1010),
							'box6' => array('label' => 6481, 'type' => 'decimal', 'help' => 6482),
							'box8' => array('label' => 6483, 'type' => 'bool', 'help' => 2454),
							'box9' => array('label' => 2457, 'type' => 'bool', 'help' => 2456),
							'box10' => array('label' => 6484, 'type' => 'select', 'options' => array('' => '–', 'B2C-EU' => 'B2C EU', 'B2C-UDL' => 'B2C uden for EU', 'B2B-EU' => 'B2B EU', 'B2B-UDL' => 'B2B uden for EU'), 'options_literal' => true),
						)),
				),
				'legacy' => array(array(771)), 'old' => array('debitor' => array(771)),
				'context' => array('debitor/debitorkort.php', 'debitor/debitor.php'),
				'keywords' => array('debitorgrupper', 'debtor groups', 'kundegrupper', 'customer groups', 'samlekonto', 'rabat', 'b2b', 'eu-zone', 'oss'),
			),
			'purchase.creditor_groups' => array(
				'group' => 'purchase', 'section' => 'creditor_groups', 'number' => 'G4.1', 'label' => 2458, 'icon' => 'bx-group', 'kind' => 'rows',
				'lead' => 6506, 'subsections' => array('groups' => 2458),
				'tables' => array(
					'groups' => array('sub' => 'groups', 'label' => 2458, 'add' => 6476, 'empty' => 6479, 'storage' => array('grupper', 'KG'), 'standard' => 'grupper', 'row_actions' => array('move_control' => array('label' => 6503, 'confirm_title' => 6503, 'confirm' => 6885, 'confirm_args' => 'move_control', 'input' => 6886, 'danger' => true)),  'kode' => 'K',
						'fiscal' => true, 'usage' => 'creditor_group', 'inactive' => true, 'propagate' => array('box10'), 'defaults' => array('box3' => 'DKK'),
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code'),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 1011, 'type' => 'select', 'options_from' => 'vat_codes_purchase', 'options_literal' => true, 'help' => 2459),
							'box2' => array('label' => 2448, 'type' => 'account', 'required' => true, 'help' => 2460, 'locked_if_used' => 6887),
							'box3' => array('label' => 776, 'type' => 'select', 'options_from' => 'currencies', 'options_literal' => true, 'empty_value' => 'DKK'),
							'box6' => array('label' => 2463, 'type' => 'select', 'options_from' => 'vat_codes_sales', 'options_literal' => true, 'help' => 2462),
							'box9' => array('label' => 2457, 'type' => 'bool', 'help' => 2465, 'requires' => array('box6', 6500)),
							'box10' => array('label' => 6484, 'type' => 'select', 'options' => array('' => '–', 'B2B-EU' => 'B2B EU', 'B2B-UDL' => 'B2B uden for EU'), 'options_literal' => true),
						)),
				),
				'legacy' => array(array(771)), 'old' => array('debitor' => array(771)),
				'context' => array('kreditor/kreditorkort.php', 'kreditor/kreditor.php'),
				'keywords' => array('kreditorgrupper', 'creditor groups', 'leverandørgrupper', 'supplier groups', 'samlekonto', 'omvendt betalingspligt', 'reverse charge'),
			),
			'items.item_groups' => array(
				'group' => 'items', 'section' => 'item_groups', 'number' => 'G5.1', 'label' => 774, 'icon' => 'bx-category', 'kind' => 'rows',
				'lead' => 6507, 'subsections' => array('groups' => 774, 'prices' => 6508),
				'tables' => array(
					'groups' => array('sub' => 'groups', 'label' => 774, 'add' => 6477, 'empty' => 6480, 'storage' => array('grupper', 'VG'), 'standard' => 'grupper',
						'fiscal' => true, 'usage' => 'item_group', 'propagate' => array('box5'),
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code'),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 6485, 'type' => 'account', 'requires' => array('box2', 6499)),
							'box2' => array('label' => 6486, 'type' => 'account', 'requires' => array('box1', 6499)),
							'box3' => array('label' => 6487, 'type' => 'account', 'required' => true),
							'box4' => array('label' => 6488, 'type' => 'account', 'required' => true),
							'box5' => array('label' => 6489, 'type' => 'select', 'options' => array('' => '–', 'varer' => 110, 'ydelser' => 6501), 'options_mixed' => true),
							'box6' => array('label' => 2455, 'type' => 'bool', 'help' => 2466),
							'box7' => array('label' => 6490, 'type' => 'bool'),
							'box8' => array('label' => 6491, 'type' => 'bool'),
							'box9' => array('label' => 6492, 'type' => 'bool', 'requires' => array('box8', 6498)),
							'box10' => array('label' => 6493, 'type' => 'bool'),
							'box11' => array('label' => 6494, 'type' => 'account', 'help' => 6496),
							'box13' => array('label' => 6495, 'type' => 'account', 'help' => 6497),
						)),
				),
				'legacy' => array(array(774)), 'old' => array('varer' => array(774)),
				'context' => array('lager/varekort.php', 'lager/varer.php'),
				'keywords' => array('varegrupper', 'item groups', 'product groups', 'varekøb', 'varesalg', 'lagerført', 'batch', 'momsfri', 'omvendt betalingspligt'),
			),
			'integrations.pickup' => array(
				'group' => 'integrations', 'section' => 'pickup', 'number' => 'G9.2', 'label' => 6379, 'icon' => 'bx-map-pin', 'kind' => 'list',
				'lead' => 6380, 'items_from' => 'pickups', 'add_action' => 'integrations.pickup.add', 'empty_text' => 6386,
				'subsections' => array('addresses' => 6379),
				'legacy' => array(array(782, 794)), 'old' => array('div_valg' => array(782, 794)),
				'context' => array('debitor/ordre.php'),
				'keywords' => array('afhentningsadresse', 'afhentningsadresser', 'pickup address', 'pick-up', 'danske fragtmænd', 'dfm'),
			),
			// ---- G10 POS: Tills, Cards, Cash, Receipt, Kitchen, Screen, Tables (the old PoS-valg page is retired)
			'pos.tills' => array(
				'group' => 'pos', 'section' => 'tills', 'number' => 'G10.1', 'label' => 6275, 'icon' => 'bx-store-alt', 'module' => 'pos', 'kind' => 'list',
				'lead' => 6276, 'items_from' => 'tills', 'add_action' => 'pos.tills.add', 'empty_text' => 6307,
				'subsections' => array('tills' => 6275),
				'legacy' => array(array(782, 271)), 'old' => array('posOptions' => array(782, 271)),
				'context' => array('debitor/pos_ordre.php'),
				'keywords' => array('kasse', 'kasser', 'till', 'tills', 'afdeling', 'momsgruppe', 'kontantkonto', 'mellemkonto', 'differencekonto', 'printer', 'terminal', 'køkkenprinter', 'mobil kasse'),
			),
			'pos.cash' => array(
				'group' => 'pos', 'section' => 'cash', 'number' => 'G10.1', 'label' => 6303, 'icon' => 'bx-calculator', 'module' => 'pos',
				'subsections' => array('cash' => 6303, 'discount' => 287),
				'legacy' => array(array(782, 271)), 'old' => array('posOptions' => array(782, 271)),
				'context' => array('debitor/kasseoptaelling.php'),
				'keywords' => array('kasseoptælling', 'cash count', 'kassebeholdning', 'byttepenge', 'float', 'rabatvare', 'discount item'),
			),
			'pos.cards' => array(
				'group' => 'pos', 'section' => 'cards', 'number' => 'G10.3', 'label' => 6312, 'icon' => 'bx-credit-card', 'module' => 'pos', 'kind' => 'list',
				'lead' => 6313, 'items_from' => 'cards', 'add_action' => 'pos.cards.add', 'empty_text' => 6332,
				'subsections' => array('general' => 6324, 'cards' => 567),
				'legacy' => array(array(782, 271)), 'old' => array('posOptions' => array(782, 271)),
				'context' => array('debitor/pos_ordre.php'),
				'keywords' => array('betalingskort', 'kort', 'cards', 'payment cards', 'dankort', 'mobilepay', 'gavekort', 'gift card', 'tilgodebevis', 'voucher', 'terminal', 'kortkonto'),
			),
			'pos.receipt' => array(
				'group' => 'pos', 'section' => 'receipt', 'number' => 'G10.2', 'label' => 6253, 'icon' => 'bx-receipt', 'module' => 'pos',
				'subsections' => array('receipt' => 6253), 'sub_help' => array('receipt' => 6272),
				'legacy' => array(array(782, 271)), 'old' => array('posOptions' => array(782, 271)),
				'context' => array('debitor/pos_ordre.php'),
				'keywords' => array('bon', 'kvittering', 'receipt', 'bonprint', 'udskrift', 'print', 'kasse', 'pos'),
			),
			'pos.kitchen' => array(
				'group' => 'pos', 'section' => 'kitchen', 'number' => 'G10.6', 'label' => 6258, 'icon' => 'bx-dish', 'module' => 'pos',
				'subsections' => array('kds' => 6259, 'colours' => 6351, 'print' => 6260),
				'legacy' => array(array(782, 271)), 'old' => array('posOptions' => array(782, 271)),
				'context' => array('debitor/kds/show_items.php'),
				'keywords' => array('kds', 'køkken', 'kitchen', 'køkkenskærm', 'køkkenprint', 'kitchen print'),
			),
			'pos.tables' => array(
				'group' => 'pos', 'section' => 'tables', 'number' => 'G10.5', 'label' => 6340, 'icon' => 'bx-grid-alt', 'module' => 'pos',
				'subsections' => array('tables' => 674, 'plans' => 6344), 'sub_help' => array('tables' => 6341),
				'legacy' => array(array(782, 271)), 'old' => array('posOptions' => array(782, 271)),
				'context' => array('debitor/pos_ordre.php', 'bordplaner/planner/index.php'),
				'keywords' => array('borde', 'tables', 'bordplan', 'bordplaner', 'floor plan', 'bordplanlægger', 'restaurant'),
			),
			'pos.screen' => array(
				'group' => 'pos', 'section' => 'screen', 'number' => 'G10.7', 'label' => 6254, 'icon' => 'bx-desktop', 'module' => 'pos',
				'subsections' => array('buttons' => 6255, 'sale' => 6256, 'display' => 6257), 'sub_help' => array('buttons' => 6272),
				'legacy' => array(array(782, 271)), 'old' => array('posOptions' => array(782, 271)),
				'context' => array('debitor/pos_ordre.php'),
				'keywords' => array('kasse', 'pos', 'knapper', 'buttons', 'kontoopslag', 'indbetaling', 'sæt', 'kundedisplay', 'lagerbeholdning', 'stor sum'),
			),
			'purchase.pricelists' => array(
				'group' => 'purchase', 'section' => 'pricelists', 'number' => 'G4.3', 'label' => 6219, 'icon' => 'bx-spreadsheet', 'kind' => 'list',
				'lead' => 6220, 'items_from' => 'pricelists', 'add_action' => 'purchase.pricelists.create', 'empty_text' => 6250,
				'subsections' => array('lists' => 6219),
				'legacy' => array(array(782, 792)),
				'old' => array('pricelists' => array(782, 792)),
				'context' => array('debitor/ordre.php', 'debitor/_varerInsert.php'),
				'keywords' => array('prisliste', 'prislister', 'price list', 'price lists', 'leverandør', 'supplier', 'csv', 'prisfil', 'price file'),
			),
			'finance.document_storage' => array(
				'group' => 'finance', 'section' => 'document_storage', 'number' => 'G2.6', 'label' => 6205, 'icon' => 'bx-folder',
				'subsections' => array('storage' => 6206, 'ftp' => 1343, 'viewer' => 6207),
				'sub_help' => array('ftp' => 1340),
				'legacy' => array(array(782, 797)),
				'old' => array('bilag' => array(782, 797)),
				'context' => array('includes/bilag.php', 'includes/vis_bilag.php', 'finans/kassekladde.php'),
				'keywords' => array('bilag', 'bilagsopbevaring', 'bilagshåndtering', 'document storage', 'documents', 'ftp', 'scanning', 'scannede bilag', 'bilagspulje', 'google docs'),
			),
			'sales.discounts' => array(
				'group' => 'sales', 'section' => 'discounts', 'number' => 'G3.5', 'label' => 6851, 'icon' => 'bx-purchase-tag-alt', 'kind' => 'rows',
				'lead' => 6854, 'subsections' => array('prices' => 2471, 'campaigns' => 2472, 'quantity' => 6855, 'debtor_groups' => 6878, 'item_groups' => 6879, 'matrix' => 6875, 'old_discounts' => 6900),
				'tables' => array(
					'prices' => array('sub' => 'prices', 'label' => 2471, 'help' => 6856, 'add' => 6857, 'empty' => 6858, 'storage' => array('grupper', 'VPG'),
						'usage' => 'price_group', 'row_actions' => array('group_apply' => array('label' => 6852, 'confirm_title' => 6852, 'confirm' => 6853)),
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code'),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 6859, 'type' => 'decimal'),
							'box2' => array('label' => 6860, 'type' => 'decimal'),
							'box3' => array('label' => 6861, 'type' => 'decimal'),
							'box4' => array('label' => 6862, 'type' => 'decimal'),
							'items' => array('label' => 6863, 'type' => 'derived', 'derive' => 'group_items'),
						)),
					'campaigns' => array('sub' => 'campaigns', 'label' => 2472, 'help' => 6864, 'add' => 6857, 'empty' => 6858, 'storage' => array('grupper', 'VTG'),
						'usage' => 'price_group', 'row_actions' => array('group_apply' => array('label' => 6852, 'confirm_title' => 6852, 'confirm' => 6853)),
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code'),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 6859, 'type' => 'decimal'),
							'box2' => array('label' => 6865, 'type' => 'decimal'),
							'box3' => array('label' => 6866, 'type' => 'date'),
							'box4' => array('label' => 6867, 'type' => 'date'),
							'items' => array('label' => 6863, 'type' => 'derived', 'derive' => 'group_items'),
						)),
					'quantity' => array('sub' => 'quantity', 'label' => 6855, 'help' => 6868, 'add' => 6857, 'empty' => 6858, 'storage' => array('grupper', 'VRG'),
						'usage' => 'price_group', 'row_actions' => array('group_apply' => array('label' => 6852, 'confirm_title' => 6852, 'confirm' => 6853)),
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code'),
							'beskrivelse' => array('label' => 914, 'type' => 'text', 'required' => true),
							'box1' => array('label' => 6869, 'type' => 'select', 'options' => array('percent' => 6870, 'amount' => 6871), 'required' => true),
							'box2' => array('label' => 2473, 'type' => 'text', 'help' => 6872),
							'box3' => array('label' => 2474, 'type' => 'text', 'help' => 6872),
							'items' => array('label' => 6863, 'type' => 'derived', 'derive' => 'group_items'),
						)),
					'debtor_groups' => array('sub' => 'debtor_groups', 'label' => 6878, 'help' => 6881, 'add' => 6857, 'empty' => 6858, 'storage' => array('grupper', 'DRG'),
						'fiscal' => true, 'propagate' => array('box1'), 'defaults' => array('beskrivelse' => 'Debitorrabatgrupper'), 'usage' => 'discount_debtor_group',
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code'),
							'box1' => array('label' => 646, 'type' => 'text', 'required' => true),
						)),
					'item_groups' => array('sub' => 'item_groups', 'label' => 6879, 'help' => 6882, 'add' => 6857, 'empty' => 6858, 'storage' => array('grupper', 'DVRG'),
						'defaults' => array('beskrivelse' => 'DebitorVareRabatGrupper'), 'usage' => 'discount_item_group',
						'columns' => array(
							'kodenr' => array('label' => 2248, 'type' => 'code'),
							'box1' => array('label' => 646, 'type' => 'text', 'required' => true),
						)),
					'old_discounts' => array('sub' => 'old_discounts', 'label' => 6900, 'help' => 6901, 'empty' => 6858, 'storage' => array('table', 'rabat'),
						'exclude' => "coalesce(debitorart, '') <> 'NR' or coalesce(vareart, '') <> 'NR'", 'order' => 'debitor, vare, id',
						'no_add' => true, 'hide_empty' => true, 'row_locked' => 'always', 'row_actions' => array('discount_transfer' => array('label' => 6902)),
						'columns' => array(
							'debitor' => array('label' => 374, 'type' => 'derived', 'derive' => 'legacy_discount_debtor'),
							'vare' => array('label' => 6230, 'type' => 'derived', 'derive' => 'legacy_discount_item'),
							'rabat' => array('label' => 428, 'type' => 'derived', 'derive' => 'legacy_discount_value'),
						)),
				),
				'legacy' => array(array(2471), array(2472), array(1006), array(775)),
				'context' => array('lager/varekort.php', 'debitor/debitorkort.php'),
				'keywords' => array('prisgrupper', 'price groups', 'tilbudsgrupper', 'kampagne', 'campaign', 'kampagnepris', 'rabatgrupper', 'mængderabat', 'quantity discount', 'stk. rabat', 'b2b-pris', 'vejledende pris', 'anvend priser'),
			),
			'sales.mysale' => array(
				'group' => 'sales', 'section' => 'mysale', 'number' => 'G3.6', 'label' => 5986, 'icon' => 'bx-store',
				'subsections' => array('mysale' => 5986, 'labels' => 5992),
				'legacy' => array(array(782, 794)),
				'old' => array('div_valg' => array(782, 794)),
				'context' => array('mysale/showMySale.php', 'debitor/debitor_kommission.php'),
				'keywords' => array('mit salg', 'mysale', 'my sales', 'loppemarked', 'kommission', 'provisionskunder', 'labels'),
			),
			'items.consignment' => array(
				'group' => 'items', 'section' => 'consignment', 'number' => 'G5.6', 'label' => 5975, 'icon' => 'bx-purchase-tag', 'module' => 'pos',
				'subsections' => array('consignment' => 5975, 'accounts' => 117, 'settlement' => 2051),
				'legacy' => array(array(782, 787)),
				'old' => array('productOptions' => array(782, 787)),
				'context' => array('lager/varekort.php', 'debitor/kasseoptaelling.php'),
				'keywords' => array('kommission', 'kommissionsvarer', 'consignment', 'commission', 'brugte varer', 'used items', 'afregning', 'settlement'),
			),
			'items.packaging' => array(
				'group' => 'items', 'section' => 'packaging', 'number' => 'G5.7', 'label' => 5976, 'icon' => 'bx-box',
				'subsections' => array('packaging' => 5976),
				'legacy' => array(array(782, 787)),
				'old' => array('productOptions' => array(782, 787)),
				'context' => array('lager/emballage.php', 'lager/varekort.php'),
				'keywords' => array('emballage', 'packaging', 'producentansvar', 'producer responsibility', 'emballageafgift'),
			),
			'documents.backgrounds' => array(
				'group' => 'documents', 'section' => 'backgrounds', 'number' => 'G6.2', 'label' => 6671, 'icon' => 'bx-layer', 'kind' => 'rows',
				'lead' => 6672, 'subsections' => array('backgrounds' => 6671, 'files' => 6673),
				'tables' => array(
					'backgrounds' => array('sub' => 'backgrounds', 'label' => 6671, 'help' => 6674, 'add' => 6675, 'empty' => 6676, 'storage' => array('grupper', 'VSPR'),
						'code_col' => 'box1', 'auto_code' => true, 'usage' => 'background', 'before_row' => 'background_create', 'on_delete' => 'background_delete',
						'defaults' => array('beskrivelse' => 'Formular og varesprog'),
						'columns' => array(
							'box1' => array('label' => 646, 'type' => 'text', 'required' => true, 'create_only' => true, 'help' => 6677, 'forbid' => array('default_background', 6683), 'unique_text' => 6684),
							'template' => array('label' => 6678, 'type' => 'select', 'options_from' => 'form_backgrounds', 'options_literal' => true, 'transient' => true, 'help' => 6679),
						)),
				),
				'legacy' => array(array(780)), 'old' => array('formularsprog' => array(780), 'logoslet' => array(780)),
				'keywords' => array('baggrund', 'baggrunde', 'formularsprog', 'sprog', 'background', 'form language', 'logo', 'brevpapir', 'bilag', 'pdf'),
			),
			'documents.print' => array(
				'group' => 'documents', 'section' => 'print', 'number' => 'G6.4', 'label' => 5993, 'icon' => 'bx-printer',
				'subsections' => array('print' => 5993),
				'legacy' => array(array(782, 794)),
				'old' => array('div_valg' => array(782, 794)),
				'context' => array('includes/udskriv.php'),
				'keywords' => array('udskrift', 'print', 'printer', 'lokal printer', 'local printer', 'html', 'postscript', 'formulargenerering', 'form generation'),
			),
			'organisation.commission' => array(
				'group' => 'organisation', 'section' => 'commission', 'number' => 'G7.4', 'label' => 657, 'icon' => 'bx-line-chart',
				'subsections' => array('basis' => 1263, 'card' => 566),
				'legacy' => array(array(782, 784), array(782, 787)),
				'old' => array('provision' => array(782, 784)),
				'context' => array('finans/provisionsrapport.php', 'lager/varekort.php'),
				'keywords' => array('provision', 'commission', 'provisionsrapport', 'commission report', 'kundeansvarlig', 'referenceperson', 'skæringsdato', 'cut-off'),
			),
			'sales.reminders' => array(
				'group' => 'sales', 'section' => 'reminders', 'number' => 'G3.4', 'label' => 6190, 'icon' => 'bx-bell',
				'subsections' => array('responsible' => 6186, 'deadlines' => 6187, 'collection' => 6188, 'fees' => 6189),
				'sub_help' => array('deadlines' => 6202),
				'legacy' => array(array(782, 793), array(780)),
				'old' => array('rykker_valg' => array(782, 793)),
				'context' => array('debitor/rykker.php', 'debitor/ny_rykker.php'),
				'keywords' => array('rykker', 'rykkere', 'reminder', 'reminders', 'påmindelse', 'inkasso', 'collection', 'rykkergebyr', 'rente', 'interest', 'betalingsbetingelser', 'payment terms'),
			),
			'documents.email' => array(
				'group' => 'documents', 'section' => 'email', 'number' => 'G6.3', 'label' => 6166, 'icon' => 'bx-envelope', 'permission' => 'settings.email',
				'subsections' => array('server' => 6167, 'sender' => 6168, 'format' => 7100, 'texts' => 6169),
				'legacy' => array(array(782, 783)),
				'old' => array('smtp' => array(782, 783), 'email_settings' => array(573, 6166), 'mailTxt' => array(606, 6169)),
				'context' => array('includes/formFuncIncludes/sendMail.php', 'debitor/mail_modtagere.php'),
				'keywords' => array('e-mail', 'email', 'mail', 'smtp', 'afsender', 'sender', 'mailtekst', 'mail text', 'emne', 'subject', 'mit salg', 'mysale', 'afregning', 'kryptering', 'ssl', 'tls'),
			),
			// ---- phase 4b batch 2: a list of integrations, each with a drawer (hand-over 2 Oct, mock-ups 07/08)
			'integrations.connections' => array(
				'group' => 'integrations', 'section' => 'connections', 'number' => 'G9', 'label' => 5537, 'icon' => 'bx-plug', 'kind' => 'list',
				'lead' => 6126,
				'subsections' => array('api' => 6055, 'shipping' => 6056, 'einvoice' => 6057, 'payments' => 6058),
				'sub_module' => array('payments' => 'pos'),
				'items' => array(
					'rest_api'  => array('sub' => 'api',      'abbr' => 'API', 'label' => 'REST API',        'desc' => 6059),
					'webshop'   => array('sub' => 'api',      'abbr' => 'WS',  'label' => 6068,              'desc' => 6069),
					'quickpay'  => array('sub' => 'api',      'abbr' => 'QP',  'label' => 'QuickPay',        'desc' => 6073),
					'gls'       => array('sub' => 'shipping', 'abbr' => 'GLS', 'label' => 'GLS',             'desc' => 6079),
					'dfm'       => array('sub' => 'shipping', 'abbr' => 'DFM', 'label' => 'Danske Fragtmænd', 'desc' => 6080),
					'easyubl'   => array('sub' => 'einvoice', 'abbr' => 'EAN', 'label' => 6084,              'desc' => 6085),
					'app'       => array('sub' => 'einvoice', 'abbr' => 'APP', 'label' => 6091,              'desc' => 6092),
					'mobilepay' => array('sub' => 'payments', 'abbr' => 'MP',  'label' => 'MobilePay',       'desc' => 6096),
					'flatpay'   => array('sub' => 'payments', 'abbr' => 'FP',  'label' => 'Flatpay',         'desc' => 6112),
					'vibrant'   => array('sub' => 'payments', 'abbr' => 'VB',  'label' => 'Vibrant',         'desc' => 6117),
					'bank'      => array('sub' => 'payments', 'abbr' => 'BK',  'label' => 6141,              'desc' => 6142, 'soon' => true, 'feature' => 'bank'),
				),
				'legacy' => array(array(782, 790), array(782, 794)),
				'old' => array('api_valg' => array(782, 790), 'div_valg' => array(782, 794)),
				'context' => array('debitor/ordre.php', 'api/rest_api.php'),
				'keywords' => array('integration', 'integrationer', 'api', 'rest api', 'api-nøgle', 'api key', 'webshop', 'shop', 'quickpay', 'gls', 'dfm', 'danske fragtmænd', 'fragt', 'shipping', 'nemhandel', 'easyubl', 'e-faktura', 'oioubl', 'app', 'qr', 'barcode', 'mobilepay', 'vipps', 'webhook', 'flatpay', 'vibrant', 'kortterminal', 'betalingsudbyder', 'payment provider'),
			),
		);
	}

	/**
	 * Every setting that is defined in the registry (key => definition).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function getSettingDefinitions(): array
	{
		static $defs = null;
		if ($defs !== null) {
			return $defs;
		}
		$ordre = array(782, 786);
		$vare = array(782, 787);
		$divvalg = array(782, 794);
		$mass = array(782, 200);
		// G7.1: only the company's own employees (ansatte also holds the contact persons of customers and suppliers).
		$empWhere = 'konto_id = ' . settings_company_account_id();
		$prov = array(782, 784);
		$ore = array(782, 170);
		$api = array(782, 790);
		$konto = array(782, 783);
		$rykker = array(782, 793);
		$bilag = array(782, 797);
		$prisliste = array(782, 792);
		$pos = array(782, 271);

		$defs = array(
			// ---------------------------------------------------------------- G3.2 Debtor card
			'sales.debtor_card.mandatory_group' => array('sub' => 'card', 'type' => 'bool', 'label' => 162, 'help' => 186, 'default' => false,
				'storage' => array('grupper', 'DIV', 2, 'box1', 'onEmpty', 'row_name' => 'Div_valg'), 'legacy' => $divvalg),
			'sales.debtor_card.mandatory_responsible' => array('sub' => 'card', 'type' => 'bool', 'label' => 163, 'help' => 187, 'default' => false,
				'storage' => array('grupper', 'DIV', 2, 'box2', 'onEmpty', 'row_name' => 'Div_valg'), 'legacy' => $divvalg),
			'sales.debtor_card.job_cards' => array('sub' => 'card', 'type' => 'bool', 'label' => 168, 'help' => 194, 'default' => false,
				'storage' => array('grupper', 'DIV', 2, 'box7', 'onEmpty', 'row_name' => 'Div_valg'), 'legacy' => $divvalg,
				'keywords' => array('jobkort', 'opgaveliste', 'task list')),
			'sales.debtor_card.account_as_phone' => array('sub' => 'card', 'type' => 'bool', 'label' => 1060, 'help' => 1061, 'default' => false,
				'storage' => array('grupper', 'DIV', 2, 'box5', 'onEmpty', 'row_name' => 'Div_valg'), 'legacy' => $divvalg),
			'sales.debtor_card.debtor_ipad' => array('sub' => 'card', 'type' => 'bool', 'label' => 2369, 'help' => 2370, 'default' => false,
				'storage' => array('settings', 'ordre', 'debitoripad', 'onEmpty'), 'legacy' => $ordre),
			'sales.debtor_card.show_both_addresses' => array('sub' => 'card', 'type' => 'bool', 'label' => 5038, 'help' => 5039, 'default' => false,
				'storage' => array('settings', 'ordre', 'showBothAddrExtra', 'onEmpty'), 'legacy' => $ordre),

			// ---------------------------------------------------------------- G3.3 Orders & invoicing
			'sales.orders.vat_private' => array('sub' => 'prices', 'type' => 'bool', 'label' => 5693, 'help' => 5695, 'default' => false,
				'storage' => array('settings', 'ordre', 'vatPrivateCustomers', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('moms', 'vat', 'mva', 'private kunder')),
			'sales.orders.vat_business' => array('sub' => 'prices', 'type' => 'bool', 'label' => 5694, 'help' => 5695, 'default' => false,
				'storage' => array('settings', 'ordre', 'vatBusinessCustomers', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('moms', 'vat', 'mva', 'erhvervskunder', 'b2b')),

			'sales.orders.discount_decimals' => array('sub' => 'prices', 'type' => 'int', 'label' => 6366, 'help' => 6367, 'default' => 2,
				'storage' => array('settings', 'ordre', 'rabatdecimal', 'raw'), 'legacy' => $ordre, 'validate' => array('range', 0, 4),
				'keywords' => array('rabat', 'decimaler', 'discount decimals')),
			'sales.orders.hide_revenue' => array('sub' => 'invoicing', 'type' => 'bool', 'label' => 5246, 'help' => 5247, 'default' => false,
				'storage' => array('settings', 'finans', 'hideRevenue', 'onOff'), 'legacy' => $ordre,
				'keywords' => array('omsætning', 'skjul omsætning', 'turnover', 'hide revenue', 'kasseoptælling')),
			'sales.orders.quick_invoice' => array('sub' => 'invoicing', 'type' => 'bool', 'label' => 165, 'help' => 190, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box4', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'locked_if' => 'batch_control', 'locked_text' => 7290,
				'keywords' => array('hurtigfakturering', 'quick invoicing', 'fast invoicing')),
			'sales.orders.post_sales_immediately' => array('sub' => 'invoicing', 'type' => 'bool', 'label' => 166, 'help' => 191, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box5', 'onEmpty', 'join' => ';', 'index' => 0, 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('straksbogføring', 'immediate posting', 'bogføring')),
			'sales.orders.lock_invoice_until_paid' => array('sub' => 'invoicing', 'type' => 'bool', 'label' => 3357, 'help' => 3356, 'default' => false,
				'storage' => array('settings', 'debitor', 'lockedInvoiceButton', 'onEmpty'), 'legacy' => $ordre,
				'visible_if' => array('module', 'pos')),
			'sales.orders.percentage_invoicing' => array('sub' => 'invoicing', 'type' => 'bool', 'label' => 681, 'help' => 682, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box12', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('procentfakturering', 'udlejning', 'rental')),
			'sales.orders.percentage_surcharge' => array('sub' => 'invoicing', 'type' => 'decimal', 'label' => 683, 'help' => 684, 'default' => '', 'unit' => '%',
				'storage' => array('grupper', 'DIV', 3, 'box13', 'raw', 'join' => "\t", 'index' => 0, 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'visible_if' => array('setting', 'sales.orders.percentage_invoicing', true)),
			'sales.orders.percentage_item' => array('sub' => 'invoicing', 'type' => 'item', 'item_as' => 'varenr', 'label' => 685, 'help' => 686, 'default' => '',
				'storage' => array('grupper', 'DIV', 3, 'box13', 'raw', 'join' => "\t", 'index' => 1, 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'visible_if' => array('setting', 'sales.orders.percentage_invoicing', true), 'validate' => array('item_exists')),
			'sales.orders.bundle_price' => array('sub' => 'invoicing', 'type' => 'bool', 'label' => 742, 'help' => 743, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box14', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'validate' => array('requires', 'sales.orders.discount_item', 1875),
				'keywords' => array('samlet pris', 'bundle', 'sæt')),
			'sales.orders.bundle_item' => array('sub' => 'invoicing', 'type' => 'item', 'item_as' => 'id', 'label' => 744, 'help' => 745, 'default' => '',
				'storage' => array('grupper', 'DIV', 5, 'box8', 'raw', 'row_name' => 'Div_valg'), 'legacy' => $ordre,
				'visible_if' => array('setting', 'sales.orders.bundle_price', true), 'validate' => array('item_exists')),

			'sales.orders.mass_invoicing' => array('sub' => 'mass', 'type' => 'bool', 'label' => 201, 'help' => 202, 'default' => false,
				'storage' => array('grupper', 'MFAKT', 1, 'box1', 'onEmpty', 'row_name' => 'Massefakturering'), 'legacy' => $mass,
				'keywords' => array('massefakturering', 'mass invoicing', 'batch invoicing')),
			'sales.orders.mass_partial_deliveries' => array('sub' => 'mass', 'type' => 'bool', 'label' => 203, 'help' => 204, 'default' => false,
				'storage' => array('grupper', 'MFAKT', 1, 'box2', 'onEmpty', 'row_name' => 'Massefakturering'), 'legacy' => $mass,
				'visible_if' => array('setting', 'sales.orders.mass_invoicing', true)),
			'sales.orders.mass_delivery_deadline' => array('sub' => 'mass', 'type' => 'int', 'label' => 205, 'help' => 206, 'default' => 0, 'unit' => 5025,
				'storage' => array('grupper', 'MFAKT', 1, 'box3', 'raw', 'row_name' => 'Massefakturering'), 'legacy' => $mass,
				'visible_if' => array('setting', 'sales.orders.mass_invoicing', true)),

			'sales.orders.discount_item' => array('sub' => 'items', 'type' => 'item', 'item_as' => 'id', 'label' => 287, 'help' => 288, 'default' => '',
				'storage' => array('grupper', 'DIV', 3, 'box2', 'raw', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre, 'validate' => array('item_exists'),
				'keywords' => array('rabatvare', 'discount item')),
			'sales.orders.postage_item' => array('sub' => 'items', 'type' => 'item', 'item_as' => 'varenr', 'label' => 5696, 'help' => 5697, 'default' => '',
				'storage' => array('settings', 'ordre', 'porto_varnr', 'raw'), 'legacy' => $ordre, 'validate' => array('item_exists'),
				'keywords' => array('porto', 'fragt', 'postage', 'shipping item')),
			'sales.orders.cash_account' => array('sub' => 'items', 'type' => 'account', 'label' => 687, 'help' => 688, 'default' => '',
				'storage' => array('grupper', 'DIV', 3, 'box7', 'raw', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre, 'validate' => array('account_exists'),
				'keywords' => array('kontantsalg', 'cash sale')),
			'sales.orders.card_account' => array('sub' => 'items', 'type' => 'account', 'label' => 689, 'help' => 690, 'default' => '',
				'storage' => array('grupper', 'DIV', 3, 'box10', 'raw', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre, 'validate' => array('account_exists'),
				'keywords' => array('kreditkort', 'kortsalg', 'card sale')),

			'sales.orders.internal_note' => array('sub' => 'window', 'type' => 'bool', 'label' => 1714, 'help' => 1711, 'default' => false,
				'storage' => array('settings', null, 'orderNoteEnabled', 'onEmpty'), 'legacy' => $ordre),
			'sales.orders.hide_margin' => array('sub' => 'window', 'type' => 'bool', 'label' => 5698, 'help' => 5699, 'default' => false,
				'storage' => array('settings', 'ordre', 'showDB', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('dækningsbidrag', 'db', 'contribution margin')),
			'sales.orders.hide_ratio' => array('sub' => 'window', 'type' => 'bool', 'label' => 5700, 'help' => 5701, 'default' => false,
				'storage' => array('settings', 'ordre', 'showDG', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('dækningsgrad', 'dg', 'contribution ratio')),
			'sales.orders.gs1_parsing' => array('sub' => 'window', 'type' => 'bool', 'label' => 5032, 'help' => 5033, 'default' => false,
				'storage' => array('settings', 'ordre', 'gs1_parsing', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('gs1', 'stregkode', 'barcode')),
			'sales.orders.our_ref_stock_switch' => array('sub' => 'window', 'type' => 'bool', 'label' => 5034, 'help' => 5035, 'default' => false,
				'storage' => array('settings', 'ordre', 'ourRefStockSwitch', 'onEmpty'), 'legacy' => $ordre),
			'sales.orders.out_of_stock_warning' => array('sub' => 'window', 'type' => 'bool', 'label' => 5036, 'help' => 5037, 'default' => false,
				'storage' => array('settings', 'ordre', 'stockWarningEnabled', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('udsolgt', 'out of stock')),

			'sales.orders.packing_comments' => array('sub' => 'packing', 'type' => 'bool', 'label' => 164, 'help' => 188, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box3', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('følgeseddel', 'packing slip', 'delivery note')),
			'sales.orders.packing_only_quantity' => array('sub' => 'packing', 'type' => 'bool', 'label' => 169, 'help' => 189, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box8', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre),
			'sales.orders.picklist_email' => array('sub' => 'packing', 'type' => 'email', 'label' => 5702, 'help' => 5703, 'default' => '',
				'storage' => array('settings', 'ordre', 'pluklisteEmail', 'raw'), 'legacy' => $ordre,
				'keywords' => array('plukliste', 'pick list')),

			// ---------------------------------------------------------------- G4.2 Purchase orders
			'purchase.orders.post_immediately' => array('sub' => 'posting', 'type' => 'bool', 'label' => 213, 'help' => 214, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box5', 'onEmpty', 'join' => ';', 'index' => 1, 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('straksbogføring', 'immediate posting', 'købsordrer')),
			'purchase.orders.confirm_stock_change' => array('sub' => 'posting', 'type' => 'link', 'label' => 1277, 'help' => 6370, 'href' => 'settingsSection.php?s=items.stock#items.stock.confirm_stock_change', 'button' => 6371, 'audit' => false,
				'keywords' => array('bekræft lagerændring', 'confirm stock change', 'varemodtagelse', 'goods receipt')),

			// ---------------------------------------------------------------- G5.5 Stock control & cost price
			'items.stock.fifo' => array('sub' => 'stock', 'type' => 'bool', 'label' => 314, 'help' => 313, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box6', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('fifo', 'first in first out')),
			'items.stock.allow_negative' => array('sub' => 'stock', 'type' => 'bool', 'label' => 183, 'help' => 192, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box9', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('negativt lager', 'negative stock')),
			'items.stock.low_stock_warning' => array('sub' => 'stock', 'type' => 'bool', 'label' => 714, 'help' => 680, 'default' => false,
				'storage' => array('grupper', 'DIV', 3, 'box11', 'onEmpty', 'row_name' => 'Div_valg (Ordrer)'), 'legacy' => $ordre,
				'keywords' => array('lav beholdning', 'low stock')),
			'items.stock.discontinue_when_negative' => array('sub' => 'stock', 'type' => 'bool', 'label' => 1279, 'help' => 1280, 'default' => false,
				'storage' => array('settings', 'items', 'DisItemIfNeg', 'onEmpty'), 'legacy' => $vare),
			'items.stock.default_minimum' => array('sub' => 'stock', 'type' => 'int', 'label' => 2420, 'help' => 2419, 'default' => 0,
				'storage' => array('settings', 'productOptions', 'min_beholdning', 'raw'), 'legacy' => $vare,
				'keywords' => array('minimumsbeholdning', 'minimum stock', 'reorder level')),

			'items.stock.cost_method' => array('sub' => 'cost', 'type' => 'select', 'label' => 731, 'help' => 732, 'default' => '0',
				'options' => array('0' => 2527, '1' => 2528, '2' => 2529),
				'storage' => array('grupper', 'DIV', 5, 'box6', 'raw', 'row_name' => 'Div_valg'), 'legacy' => $ordre,
				'keywords' => array('kostpris', 'gennemsnitspris', 'genanskaffelsespris', 'cost price', 'average cost')),
			'items.stock.update_cost_prices' => array('sub' => 'cost', 'type' => 'action', 'label' => 739, 'help' => 738,
				'confirm_title' => 5737, 'confirm' => 5738, 'run' => 'update_cost_prices', 'impact' => 'cost_price_items', 'legacy' => $ordre,
				'visible_if' => array('setting_in', 'items.stock.cost_method', array('1', '2'))),

			'items.stock.status_mail' => array('sub' => 'mail', 'type' => 'email', 'label' => 2553, 'help' => 2554, 'default' => '',
				'storage' => array('settings', 'lagerstatus', 'mail', 'raw'), 'legacy' => $vare,
				'keywords' => array('lagerstatus', 'stock status')),
			'items.stock.status_frequency' => array('sub' => 'mail', 'type' => 'int', 'label' => 2555, 'help' => 2556, 'default' => '', 'unit' => 5745, 'empty_ok' => true,
				'storage' => array('settings', 'lagerstatus', 'time', 'raw'), 'legacy' => $vare),
			'items.stock.status_threshold' => array('sub' => 'mail', 'type' => 'int', 'label' => 2557, 'help' => 2558, 'default' => '', 'empty_ok' => true,
				'storage' => array('settings', 'lagerstatus', 'trigger', 'raw'), 'legacy' => $vare),

			'items.stock.vat_on_item_card' => array('sub' => 'card', 'type' => 'bool', 'label' => 1273, 'help' => 1274, 'default' => false,
				'storage' => array('settings', 'items', 'vatOnItemCard', 'onEmpty'), 'legacy' => $vare),
			'items.stock.confirm_description_change' => array('sub' => 'card', 'type' => 'bool', 'label' => 1275, 'help' => 1276, 'default' => false,
				'storage' => array('settings', 'items', 'confirmDescriptionChange', 'onEmpty'), 'legacy' => $vare),
			'items.stock.confirm_stock_change' => array('sub' => 'card', 'type' => 'bool', 'label' => 1277, 'help' => 1278, 'default' => false,
				'storage' => array('settings', 'items', 'confirmStockChange', 'onEmpty'), 'legacy' => $vare),
			// From master (batch expiry, #457): was a checkbox on the old Varerelaterede valg page.
			'items.stock.batch_expiry' => array('sub' => 'card', 'type' => 'bool', 'label' => 6051, 'help' => 6052, 'default' => false,
				'storage' => array('settings', 'items', 'batchExpiryEnabled', 'onOff'), 'legacy' => $vare,
				'keywords' => array('batch', 'batch management', 'batch control', 'expiry date', 'due date', 'shelf life', 'fefo', 'batchstyring', 'udløbsdato', 'holdbarhed', 'batchkontrol')),

			// ---------------------------------------------------------------- G2.2 VAT registration
			'vat.registered' => array('group' => 'finance', 'section' => 'vat', 'sub' => 'registration', 'type' => 'bool', 'label' => 6895, 'help' => 6896, 'default' => true,
				'storage' => array('settings', 'vat', 'vat_registered', 'onOff'),
				'keywords' => array('momsregistreret', 'momspligtig', 'vat registered', 'mva-registrert')),
			'vat.period' => array('group' => 'finance', 'section' => 'vat', 'sub' => 'registration', 'type' => 'select', 'label' => 6739, 'help' => 6897, 'default' => 'quarter',
				'options' => array('month' => 6740, 'quarter' => 6741, 'halfyear' => 6742),
				'storage' => array('settings', 'vat', 'vat_period', 'raw'), 'visible_if' => array('setting', 'vat.registered', true),
				'keywords' => array('momsperiode', 'vat period', 'mva-periode', 'kvartal', 'halvår', 'måned')),

			// ---------------------------------------------------------------- G2.5 Cash journal & payments
			'finance.cash_journal.different_dates_same_voucher' => array('sub' => 'journal', 'type' => 'bool', 'label' => 708, 'help' => 709, 'default' => false,
				'storage' => array('grupper', 'DIV', 2, 'box4', 'onEmpty', 'row_name' => 'Div_valg'), 'legacy' => $divvalg,
				'keywords' => array('bilagsnummer', 'voucher number', 'dato', 'date')),
			'finance.cash_journal.payment_lists' => array('sub' => 'payments', 'type' => 'select', 'label' => 184, 'help' => 185, 'default' => '',
				'options' => array('' => 2541, 'B' => 1266, 'D' => 5985, 'K' => 607),
				'storage' => array('grupper', 'DIV', 2, 'box10', 'raw', 'row_name' => 'Div_valg'), 'legacy' => $divvalg,
				'keywords' => array('betalingslister', 'payment lists', 'erh', 'bank')),
			'finance.cash_journal.payment_days' => array('sub' => 'due', 'type' => 'int', 'label' => 2733, 'help' => 2734, 'default' => '', 'empty_ok' => true, 'unit' => 5025,
				'storage' => array('settings', 'payment_list', 'paymentDays', 'raw'), 'legacy' => array(2732),
				'keywords' => array('betalingsfrist', 'betalingsdage', 'payment days', 'forfald', 'due date')),
			'finance.cash_journal.rounding_max' => array('sub' => 'rounding', 'type' => 'decimal', 'label' => 172, 'help' => 171, 'default' => '',
				'storage' => array('grupper', 'OreDif', 1, 'box1', 'raw', 'row_name' => 'Oredifferencer'), 'legacy' => $ore,
				'keywords' => array('øredifference', 'rounding', 'afrunding')),
			'finance.cash_journal.rounding_account' => array('sub' => 'rounding', 'type' => 'account', 'label' => 174, 'help' => 173, 'default' => '',
				'storage' => array('grupper', 'OreDif', 1, 'box2', 'raw', 'row_name' => 'Oredifferencer'), 'legacy' => $ore, 'validate' => array('account_exists'),
				'keywords' => array('øredifferencekonto', 'rounding account')),

			// ---------------------------------------------------------------- G3.6 mySale
			'sales.mysale.enabled' => array('sub' => 'mysale', 'type' => 'bool', 'label' => 768, 'help' => 767, 'default' => false,
				'storage' => array('settings', 'debitor', 'mySale', 'onEmpty'), 'legacy' => $divvalg,
				'keywords' => array('mit salg', 'mysale', 'my sales')),
			'sales.mysale.show_times' => array('sub' => 'mysale', 'type' => 'bool', 'label' => 5990, 'help' => 5991, 'default' => false,
				'storage' => array('settings', 'mysale', 'showMysaleTimes', 'oneZero'), 'legacy' => $divvalg,
				'visible_if' => array('setting', 'sales.mysale.enabled', true)),
			'sales.mysale.disable_customer_labels' => array('sub' => 'labels', 'type' => 'bool', 'label' => 5987, 'help' => 5988, 'default' => false,
				'storage' => array('settings', 'debitor', 'mySaleLabel', 'onEmpty'), 'legacy' => $divvalg,
				'visible_if' => array('setting', 'sales.mysale.enabled', true)),
			'sales.mysale.label_max_length' => array('sub' => 'labels', 'type' => 'int', 'label' => 5989, 'help' => 2450, 'default' => 22,
				'storage' => array('settings', 'mysale', 'labelsize', 'raw'), 'legacy' => $divvalg,
				'visible_if' => array('setting', 'sales.mysale.enabled', true)),

			// ---------------------------------------------------------------- G5.6 Consignment items (PoS)
			'items.consignment.enabled' => array('sub' => 'consignment', 'type' => 'bool', 'label' => 1281, 'help' => 1282, 'default' => false,
				'storage' => array('settings', 'items', 'useCommission', 'onEmpty'), 'legacy' => $vare,
				'keywords' => array('kommission', 'consignment', 'commission')),
			'items.consignment.default_rate' => array('sub' => 'consignment', 'type' => 'decimal', 'label' => 1283, 'help' => 1284, 'default' => '', 'unit' => '%',
				'storage' => array('settings', 'items', 'defaultCommission', 'raw'), 'legacy' => $vare,
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.include_vat' => array('sub' => 'consignment', 'type' => 'bool', 'label' => 2544, 'help' => 2545, 'default' => false,
				'storage' => array('settings', 'items', 'commissionInclVat', 'onEmpty'), 'legacy' => $vare,
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.income_account_new' => array('sub' => 'accounts', 'type' => 'account', 'label' => 1286, 'help' => 1287, 'default' => '',
				'storage' => array('settings', 'items', 'commissionAccountNew', 'raw'), 'legacy' => $vare, 'validate' => array('account_exists'),
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.settlement_account_new' => array('sub' => 'accounts', 'type' => 'account', 'label' => 1289, 'help' => 1290, 'default' => '',
				'storage' => array('settings', 'items', 'customerCommissionAccountNew', 'raw'), 'legacy' => $vare, 'validate' => array('account_exists'),
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.own_account_new' => array('sub' => 'accounts', 'type' => 'account', 'label' => 1291, 'help' => 1292, 'default' => '',
				'storage' => array('settings', 'items', 'ownCommissionAccountNew', 'raw'), 'legacy' => $vare, 'validate' => array('account_exists'),
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.income_account_used' => array('sub' => 'accounts', 'type' => 'account', 'label' => 1293, 'help' => 1294, 'default' => '',
				'storage' => array('settings', 'items', 'commissionAccountUsed', 'raw'), 'legacy' => $vare, 'validate' => array('account_exists'),
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.settlement_account_used' => array('sub' => 'accounts', 'type' => 'account', 'label' => 1295, 'help' => 1296, 'default' => '',
				'storage' => array('settings', 'items', 'customerCommissionAccountUsed', 'raw'), 'legacy' => $vare, 'validate' => array('account_exists'),
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.own_account_used' => array('sub' => 'accounts', 'type' => 'account', 'label' => 1297, 'help' => 1298, 'default' => '',
				'storage' => array('settings', 'items', 'ownCommissionAccountUsed', 'raw'), 'legacy' => $vare, 'validate' => array('account_exists'),
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.settlement_from' => array('sub' => 'settlement', 'type' => 'date', 'label' => 1306, 'help' => 1307, 'default' => '2021-01-01',
				'storage' => array('settings', 'items', 'commissionFromDate', 'raw'), 'legacy' => $vare,
				'visible_if' => array('setting', 'items.consignment.enabled', true)),
			'items.consignment.convert_existing' => array('sub' => 'settlement', 'type' => 'action', 'label' => 1299, 'help' => 6005,
				'confirm_title' => 1299, 'confirm' => 6006, 'run' => 'convert_commission_items', 'impact' => 'commission_items', 'legacy' => $vare,
				'visible_if' => array('setting', 'items.consignment.enabled', true)),

			// ---------------------------------------------------------------- G5.7 Packaging
			'items.packaging.enabled' => array('sub' => 'packaging', 'type' => 'bool', 'label' => 5995, 'help' => 5996, 'default' => false,
				'storage' => array('settings', 'items', 'packagingModuleEnabled', 'onEmpty'), 'legacy' => $vare,
				'keywords' => array('emballage', 'packaging', 'producentansvar')),

			// ---------------------------------------------------------------- G6.4 Print
			'documents.print.local_printer' => array('sub' => 'print', 'type' => 'bool', 'label' => 763, 'help' => 5994, 'default' => false,
				'storage' => array('grupper', 'PV', 1, 'box1', 'onEmpty', 'row_name' => 'Udskrift'), 'legacy' => $divvalg,
				'keywords' => array('lokal printer', 'local printer', 'port 9100')),
			'documents.print.html_forms' => array('sub' => 'print', 'type' => 'bool', 'label' => 818, 'help' => 817, 'default' => false,
				'storage' => array('grupper', 'PV', 1, 'box3', 'onEmpty', 'row_name' => 'Udskrift'), 'legacy' => $divvalg,
				'keywords' => array('html', 'css', 'postscript', 'formulargenerering')),
			'documents.print.html_layout_version' => array('sub' => 'print', 'type' => 'select', 'label' => 6905, 'help' => 6906, 'default' => '1',
				'options' => array('1' => 6907, '2' => 6908),
				'storage' => array('settings', 'forms', 'htmlLayoutVersion', 'raw'), 'legacy' => $divvalg,
				'visible_if' => array('setting', 'documents.print.html_forms', true),
				'keywords' => array('html layout', 'html/css layout', 'layout version', 'formulargenerator html', 'keep existing look', 'form fonts and line thickness', 'skrifter', 'stregtykkelser')),
			'documents.print.pdf_command' => array('sub' => 'print', 'type' => 'text', 'label' => 6372, 'help' => 6373,
				'storage' => array('grupper', 'PV', 1, 'box2', 'raw', 'row_name' => 'Udskrift'), 'legacy' => $divvalg,
				'keywords' => array('ps2pdf', 'pdf', 'printkommando', 'print command')),

			// ---------------------------------------------------------------- G7.4 Commission
			'organisation.commission.basis' => array('sub' => 'basis', 'type' => 'select', 'label' => 1269, 'help' => 6002, 'default' => 'fak',
				'options' => array('fak' => 1264, 'bet' => 1265),
				'storage' => array('grupper', 'DIV', 1, 'box4', 'raw', 'row_name' => 'Provisionsrapport'), 'legacy' => $prov),
			'organisation.commission.person_source' => array('sub' => 'basis', 'type' => 'select', 'label' => 1268, 'help' => 6003, 'default' => 'smart',
				'options' => array('smart' => 1266, 'ref' => 6001, 'kua' => 386),
				'storage' => array('grupper', 'DIV', 1, 'box1', 'raw', 'row_name' => 'Provisionsrapport'), 'legacy' => $prov),
			'organisation.commission.cost_source' => array('sub' => 'basis', 'type' => 'select', 'label' => 1270, 'help' => 6004, 'default' => 'batch',
				'options' => array('batch' => 1271, 'kort' => 566),
				'storage' => array('grupper', 'DIV', 1, 'box2', 'raw', 'row_name' => 'Provisionsrapport'), 'legacy' => $prov),
			'organisation.commission.cutoff_day' => array('sub' => 'basis', 'type' => 'int', 'label' => 1272, 'help' => 1724, 'default' => '', 'empty_ok' => true,
				'storage' => array('grupper', 'DIV', 1, 'box3', 'raw', 'row_name' => 'Provisionsrapport'), 'legacy' => $prov,
				'validate' => array('range', 1, 28)),
			'organisation.commission.default_rate' => array('sub' => 'card', 'type' => 'decimal', 'label' => 5997, 'help' => 5998, 'default' => '', 'unit' => '%',
				'storage' => array('settings', 'items', 'defaultProvision', 'raw'), 'legacy' => $vare),
			'organisation.commission.show_on_item_card' => array('sub' => 'card', 'type' => 'bool', 'label' => 5999, 'help' => 6000, 'default' => false,
				'storage' => array('settings', 'items', 'showProvision', 'onEmpty'), 'legacy' => $vare),

			// ---------------------------------------------------------------- G10.1 Tills (one drawer per till; the lists are tab-joined per fiscal year, R7)
			'pos.tills.add' => array('sub' => 'tills', 'type' => 'action', 'label' => 6277, 'help' => 6276, 'confirm_title' => 6277, 'confirm' => 6276, 'run' => 'till_add'),
			'pos.tills.department' => array('sub' => 'tills', 'type' => 'select', 'label' => 274, 'help' => 273, 'per' => 'till', 'group_label' => array(6302, ''), 'default' => '0',
				'options_from' => 'departments', 'options_literal' => true, 'storage' => array('grupper', 'POS', 1, 'box3', 'raw', 'row_name' => 'POS_valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.tills.vat_group' => array('sub' => 'tills', 'type' => 'select', 'label' => 286, 'help' => 285, 'per' => 'till', 'group_label' => array(6302, ''), 'default' => '0',
				'options_from' => 'vat_sales', 'options_literal' => true, 'storage' => array('grupper', 'POS', 1, 'box7', 'raw', 'row_name' => 'POS_valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.tills.post_each_sale' => array('sub' => 'tills', 'type' => 'bool', 'label' => 6292, 'help' => 1728, 'per' => 'till', 'group_label' => array(6302, ''), 'default' => false,
				'storage' => array('settings', 'POS', 'postEachSale', 'onEmpty', 'join' => "\t", 'list' => true, 'seed' => 'post_each_sale'), 'legacy' => $pos),
			'pos.tills.cash_account' => array('sub' => 'tills', 'type' => 'account', 'label' => 6279, 'help' => 275, 'per' => 'till', 'group_label' => array(6300, ''),
				'storage' => array('grupper', 'POS', 1, 'box2', 'raw', 'row_name' => 'POS_valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.tills.interim_account' => array('sub' => 'tills', 'type' => 'account', 'label' => 6280, 'help' => 6281, 'per' => 'till', 'group_label' => array(6300, ''),
				'storage' => array('grupper', 'POS', 2, 'box8', 'raw', 'row_name' => 'Pos valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.tills.difference_account' => array('sub' => 'tills', 'type' => 'account', 'label' => 6282, 'help' => 6283, 'per' => 'till', 'group_label' => array(6300, ''),
				'storage' => array('grupper', 'POS', 2, 'box9', 'raw', 'row_name' => 'Pos valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.tills.printer_ip' => array('sub' => 'tills', 'type' => 'text', 'label' => 704, 'help' => 6284, 'per' => 'till', 'group_label' => array(6301, ''), 'default' => 'localhost',
				'storage' => array('grupper', 'POS', 2, 'box3', 'raw', 'row_name' => 'Pos valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos, 'on_save' => 'pos_printer_changed'),
			'pos.tills.terminal_type' => array('sub' => 'tills', 'type' => 'select', 'label' => 2312, 'help' => 2313, 'per' => 'till', 'group_label' => array(6301, ''), 'default' => '', 'scope' => 'pos',
				'options' => array('' => 6171, 'Ip baseret' => 'Ip baseret', 'Flatpay' => 'Flatpay', 'Move3500' => 'Move3500', 'Lane3000' => 'Lane3000', 'Vibrant' => 'Vibrant'), 'options_mixed' => true,
				'storage' => array('settings', 'POS', 'terminal_type', 'raw'), 'legacy' => $pos),
			'pos.tills.move3500_user' => array('sub' => 'tills', 'type' => 'text', 'label' => 6333, 'help' => 6335, 'per' => 'till', 'group_label' => array(6301, ''), 'scope' => 'pos',
				'storage' => array('settings', 'move3500', 'username', 'raw'), 'legacy' => $pos, 'visible_if' => array('setting_in', 'pos.tills.terminal_type', array('Move3500'))),
			'pos.tills.move3500_password' => array('sub' => 'tills', 'type' => 'secret', 'label' => 6334, 'help' => 6335, 'per' => 'till', 'group_label' => array(6301, ''), 'scope' => 'pos',
				'storage' => array('settings', 'move3500', 'password', 'raw'), 'legacy' => $pos, 'visible_if' => array('setting_in', 'pos.tills.terminal_type', array('Move3500'))),
			'pos.tills.terminal_ip' => array('sub' => 'tills', 'type' => 'text', 'label' => 6285, 'help' => 6286, 'per' => 'till', 'group_label' => array(6301, ''),
				'storage' => array('grupper', 'POS', 2, 'box4', 'raw', 'row_name' => 'Pos valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.tills.payment_link' => array('sub' => 'tills', 'type' => 'bool', 'label' => 6368, 'help' => 6369, 'per' => 'till', 'group_label' => array(6301, ''), 'default' => false, 'scope' => 'pos',
				'storage' => array('settings', 'deb_ordre', 'showPaymentLink', 'onEmpty'), 'legacy' => $pos, 'keywords' => array('betalingslink', 'payment link')),
			'pos.tills.kitchen_ip' => array('sub' => 'tills', 'type' => 'text', 'label' => 6287, 'help' => 6288, 'per' => 'till', 'group_label' => array(6301, ''),
				'storage' => array('grupper', 'POS', 2, 'box10', 'raw', 'row_name' => 'Pos valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.tills.default_table' => array('sub' => 'tills', 'type' => 'select', 'label' => 6289, 'help' => 6290, 'per' => 'till', 'group_label' => array(6301, ''), 'default' => '',
				'options_from' => 'tables', 'options_literal' => true, 'storage' => array('grupper', 'POS', 2, 'box13', 'raw', 'row_name' => 'Pos valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.tills.font_size' => array('sub' => 'tills', 'type' => 'int', 'label' => 6291, 'help' => 766, 'unit' => 'px', 'per' => 'till', 'group_label' => array(6293, ''), 'default' => 10,
				'storage' => array('grupper', 'POS', 3, 'box2', 'raw', 'row_name' => 'Pos valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos, 'validate' => array('range', 6, 60)),
			'pos.tills.mobile' => array('sub' => 'tills', 'type' => 'bool', 'label' => 6293, 'help' => 2410, 'per' => 'till', 'group_label' => array(6293, ''), 'default' => false, 'scope' => 'pos',
				'storage' => array('settings', 'POS', 'mobilepos', 'onOff'), 'legacy' => $pos),
			'pos.tills.mobile_width' => array('sub' => 'tills', 'type' => 'int', 'label' => 6294, 'help' => 2412, 'unit' => 'px', 'per' => 'till', 'group_label' => array(6293, ''), 'default' => 510, 'scope' => 'pos',
				'storage' => array('settings', 'POS', 'mobilwidth', 'raw'), 'legacy' => $pos, 'visible_if' => array('setting', 'pos.tills.mobile', true)),
			'pos.tills.mobile_zoom' => array('sub' => 'tills', 'type' => 'decimal', 'decimal_comma' => true, 'label' => 2413, 'help' => 2414, 'per' => 'till', 'group_label' => array(6293, ''), 'default' => '1,0', 'scope' => 'pos',
				'storage' => array('settings', 'POS', 'mobilzoom', 'raw'), 'legacy' => $pos, 'visible_if' => array('setting', 'pos.tills.mobile', true)),
			'pos.tills.swap_menus' => array('sub' => 'tills', 'type' => 'bool', 'label' => 6295, 'help' => 2416, 'per' => 'till', 'group_label' => array(6293, ''), 'default' => false, 'scope' => 'pos',
				'storage' => array('settings', 'POS', 'omv_menu', 'onOff'), 'legacy' => $pos),
			'pos.tills.remove' => array('sub' => 'tills', 'type' => 'action', 'label' => 6296, 'help' => 6297, 'per' => 'till_last', 'danger' => true,
				'confirm_title' => 6298, 'confirm' => 6299, 'run' => 'till_remove'),

			// ---------------------------------------------------------------- G6.2 Form backgrounds: the PDF files
			'documents.backgrounds.files' => array('sub' => 'files', 'type' => 'link', 'label' => 6673, 'help' => 6680, 'href' => 'logoupload.php', 'button' => 6681, 'audit' => false),

			// ---------------------------------------------------------------- G11 Import, export & backup (the existing pages, framed)
			'import_export.data.accounts_import' => array('sub' => 'accounts', 'type' => 'link', 'label' => 6652, 'help' => 6666, 'href' => 'importer_kontoplan.php', 'button' => 1356, 'audit' => false),
			'import_export.data.accounts_map' => array('sub' => 'accounts', 'type' => 'link', 'label' => 2336, 'href' => 'importAccountMap.php', 'button' => 1356, 'audit' => false),
			'import_export.data.addresses_export' => array('sub' => 'addresses', 'type' => 'link', 'label' => 6653, 'href' => 'exporter_adresser.php', 'button' => 1355, 'audit' => false),
			'import_export.data.addresses_import' => array('sub' => 'addresses', 'type' => 'link', 'label' => 6654, 'href' => 'importer_adresser.php', 'button' => 1356, 'audit' => false),
			'import_export.data.items_export' => array('sub' => 'items', 'type' => 'link', 'label' => 6655, 'href' => 'exporter_varer.php', 'button' => 1355, 'audit' => false),
			'import_export.data.items_import' => array('sub' => 'items', 'type' => 'link', 'label' => 6656, 'href' => 'importer_varer.php', 'button' => 1356, 'audit' => false),
			'sales.discounts.matrix' => array('sub' => 'matrix', 'type' => 'matrix', 'label' => 6875, 'help' => 6883, 'storage' => array('virtual', 'discount_matrix'),
				'keywords' => array('rabatmatrix', 'discount matrix', 'debitorrabatgrupper', 'kunderabat', 'customer discount', 'rabat pr. varegruppe')),
			'items.variants.import' => array('sub' => 'import', 'type' => 'link', 'label' => 6839, 'help' => 6840, 'href' => 'importer_varianter.php', 'button' => 1356, 'audit' => false, 'permission' => 'settings.import_export'),
			'import_export.data.variants_export' => array('sub' => 'items', 'type' => 'link', 'label' => 6657, 'href' => 'exporter_variantvarer.php', 'button' => 1355, 'audit' => false),
			'import_export.data.variants_import' => array('sub' => 'items', 'type' => 'link', 'label' => 6658, 'href' => 'importer_variantvarer.php', 'button' => 1356, 'audit' => false),
			'import_export.data.forms_export' => array('sub' => 'forms', 'type' => 'link', 'label' => 6660, 'href' => 'exporter_formular.php', 'button' => 1355, 'audit' => false),
			'import_export.data.forms_import' => array('sub' => 'forms', 'type' => 'link', 'label' => 6661, 'href' => 'importer_formular.php', 'button' => 1356, 'audit' => false),
			'import_export.backup.latest' => array('sub' => 'backup', 'type' => 'info', 'label' => 6662, 'info' => 'backup_latest', 'audit' => false),
			'import_export.backup.take' => array('sub' => 'backup', 'type' => 'link', 'label' => 1245, 'help' => 6663, 'href' => '../admin/backup.php?backup=1', 'button' => 1245, 'audit' => false),
			'import_export.backup.restore' => array('sub' => 'restore', 'type' => 'link', 'label' => 1247, 'help' => 6664, 'href' => '../admin/restore.php', 'button' => 1247, 'audit' => false,
				'permission' => 'system.backup.restore'),

			// ---------------------------------------------------------------- G1.4 Subscription & account (Saldi-hosted only)
			'company.account.name' => array('sub' => 'account', 'type' => 'text', 'label' => 6635, 'help' => 6636, 'maxlength' => 80, 'validate' => array('ledger_name'),
				'storage' => array('virtual', 'ledger_name')),
			'company.account.activity' => array('sub' => 'account', 'type' => 'info', 'label' => 6644, 'info' => 'ledger_activity', 'audit' => false),
			'company.account.reset' => array('sub' => 'danger', 'type' => 'action', 'label' => 756, 'help' => 6642, 'confirm_title' => 756, 'confirm' => 6638,
				'run' => 'ledger_reset', 'impact' => 'ledger_reset', 'danger' => true, 'danger_zone' => true, 'permission' => 'settings.company.danger',
				'inputs' => array('keep_accounts' => 758, 'keep_items' => 760)),
			'company.account.delete' => array('sub' => 'danger', 'type' => 'action', 'label' => 852, 'help' => 6643, 'confirm_title' => 852, 'confirm' => 851,
				'run' => 'ledger_delete', 'danger' => true, 'danger_zone' => true, 'permission' => 'settings.company.danger'),

			// ---------------------------------------------------------------- G1.3 Localisation (settings rows without a group, as the readers look them up)
			'company.localisation.base_currency' => array('sub' => 'locale', 'type' => 'select', 'label' => 6526, 'help' => 6527, 'default' => 'DKK',
				'options_from' => 'currencies', 'options_literal' => true, 'storage' => array('settings', null, 'baseCurrency', 'raw'), 'permission' => 'settings.company.danger'),
			'company.localisation.base_country' => array('sub' => 'locale', 'type' => 'select', 'label' => 47, 'help' => 6528, 'default' => 'dk',
				'options' => array('dk' => 'Danmark', 'no' => 'Norge', 'ch' => 'Schweiz'), 'options_literal' => true, 'storage' => array('settings', null, 'baseCountry', 'raw')),
			'company.localisation.timezone' => array('sub' => 'locale', 'type' => 'select', 'label' => 1236, 'help' => 6529, 'default' => 'Europe/Copenhagen',
				'options' => array('Europe/Copenhagen' => 'Europe/Copenhagen', 'Europe/Oslo' => 'Europe/Oslo', 'Europe/Stockholm' => 'Europe/Stockholm', 'Europe/Zurich' => 'Europe/Zurich', 'Europe/Berlin' => 'Europe/Berlin', 'Europe/London' => 'Europe/London', 'Atlantic/Reykjavik' => 'Atlantic/Reykjavik', 'UTC' => 'UTC'),
				'options_literal' => true, 'storage' => array('settings', null, 'timezone', 'raw')),
			'company.localisation.number_format' => array('sub' => 'locale', 'type' => 'select', 'label' => 6530, 'help' => 6531, 'default' => '.|,',
				'options' => array('.|,' => '1.234,56', ',|.' => '1,234.56', ' |,' => '1 234,56'), 'options_literal' => true, 'storage' => array('settings', 'localization', 'numberFormat', 'raw')),
			'company.localisation.system_language' => array('sub' => 'locale', 'type' => 'select', 'label' => 6532, 'help' => 6533, 'default' => 'Dansk',
				'options' => array('Dansk' => 'Dansk', 'English' => 'English', 'Norsk' => 'Norsk'), 'options_literal' => true, 'storage' => array('settings', null, 'systemLanguage', 'raw')),
			'company.localisation.translations' => array('sub' => 'texts', 'type' => 'link', 'label' => 6534, 'help' => 6535, 'href' => 'tekster.php', 'button' => 6536, 'audit' => false),

			// ---------------------------------------------------------------- G1.5 Personal data: inactive customers and suppliers
			'company.gdpr.inactive' => array('sub' => 'cleanup', 'type' => 'info', 'label' => 6537, 'info' => 'gdpr_inactive', 'audit' => false),
			'company.gdpr.delete_inactive' => array('sub' => 'cleanup', 'type' => 'action', 'label' => 6539, 'help' => 6538, 'confirm_title' => 6540, 'confirm' => 6541,
				'run' => 'gdpr_delete_inactive', 'impact' => 'gdpr_inactive', 'danger' => true, 'danger_zone' => true, 'permission' => 'settings.company.danger'),

			// ---------------------------------------------------------------- G7.1 Employees (ansatte rows of the company's own address row)
			'organisation.employees.add' => array('sub' => 'employees', 'type' => 'action', 'label' => 6613, 'help' => 6606, 'confirm_title' => 6613, 'confirm' => 6606, 'run' => 'employee_add'),
			'organisation.employees.nummer' => array('sub' => 'employees', 'type' => 'int', 'label' => 645, 'per' => 'employee', 'group_label' => array(6608, ''), 'validate' => array('employee_number'),
				'storage' => array('dbrow', 'ansatte', 'nummer', 'raw', 'where' => $empWhere)),
			'organisation.employees.navn' => array('sub' => 'employees', 'type' => 'text', 'label' => 646, 'per' => 'employee', 'group_label' => array(6608, ''), 'validate' => array('required'),
				'storage' => array('dbrow', 'ansatte', 'navn', 'raw', 'where' => $empWhere)),
			'organisation.employees.initialer' => array('sub' => 'employees', 'type' => 'text', 'label' => 647, 'per' => 'employee', 'group_label' => array(6608, ''),
				'storage' => array('dbrow', 'ansatte', 'initialer', 'raw', 'where' => $empWhere)),
			'organisation.employees.addr1' => array('sub' => 'employees', 'type' => 'text', 'label' => 648, 'per' => 'employee', 'group_label' => array(6608, ''),
				'storage' => array('dbrow', 'ansatte', 'addr1', 'raw', 'where' => $empWhere)),
			'organisation.employees.addr2' => array('sub' => 'employees', 'type' => 'text', 'label' => 649, 'per' => 'employee', 'group_label' => array(6608, ''),
				'storage' => array('dbrow', 'ansatte', 'addr2', 'raw', 'where' => $empWhere)),
			'organisation.employees.postnr' => array('sub' => 'employees', 'type' => 'text', 'label' => 650, 'per' => 'employee', 'group_label' => array(6608, ''),
				'storage' => array('dbrow', 'ansatte', 'postnr', 'raw', 'where' => $empWhere)),
			'organisation.employees.bynavn' => array('sub' => 'employees', 'type' => 'text', 'label' => 651, 'per' => 'employee', 'group_label' => array(6608, ''),
				'storage' => array('dbrow', 'ansatte', 'bynavn', 'raw', 'where' => $empWhere)),
			'organisation.employees.email' => array('sub' => 'employees', 'type' => 'email', 'label' => 652, 'per' => 'employee', 'group_label' => array(6522, ''),
				'storage' => array('dbrow', 'ansatte', 'email', 'raw', 'where' => $empWhere)),
			'organisation.employees.tlf' => array('sub' => 'employees', 'type' => 'text', 'label' => 654, 'per' => 'employee', 'group_label' => array(6522, ''),
				'storage' => array('dbrow', 'ansatte', 'tlf', 'raw', 'where' => $empWhere)),
			'organisation.employees.mobil' => array('sub' => 'employees', 'type' => 'text', 'label' => 653, 'per' => 'employee', 'group_label' => array(6522, ''),
				'storage' => array('dbrow', 'ansatte', 'mobil', 'raw', 'where' => $empWhere)),
			'organisation.employees.mobile' => array('sub' => 'employees', 'type' => 'text', 'label' => 655, 'per' => 'employee', 'group_label' => array(6522, ''),
				'storage' => array('dbrow', 'ansatte', 'mobile', 'raw', 'where' => $empWhere)),
			'organisation.employees.privattlf' => array('sub' => 'employees', 'type' => 'text', 'label' => 656, 'per' => 'employee', 'group_label' => array(6522, ''),
				'storage' => array('dbrow', 'ansatte', 'privattlf', 'raw', 'where' => $empWhere)),
			'organisation.employees.afd' => array('sub' => 'employees', 'type' => 'select', 'label' => 658, 'per' => 'employee', 'group_label' => array(6609, ''), 'options_from' => 'departments',
				'storage' => array('dbrow', 'ansatte', 'afd', 'raw', 'where' => $empWhere), 'on_save' => 'employee_department'),
			'organisation.employees.startdate' => array('sub' => 'employees', 'type' => 'date', 'label' => 663, 'per' => 'employee', 'group_label' => array(6609, ''), 'value_map' => array('' => '1900-01-01'),
				'storage' => array('dbrow', 'ansatte', 'startdate', 'raw', 'where' => $empWhere)),
			'organisation.employees.slutdate' => array('sub' => 'employees', 'type' => 'date', 'label' => 1216, 'help' => 6627, 'per' => 'employee', 'group_label' => array(6609, ''), 'value_map' => array('' => '9999-12-31'),
				'storage' => array('dbrow', 'ansatte', 'slutdate', 'raw', 'where' => $empWhere), 'on_save' => 'employee_end_date'),
			'organisation.employees.lukket' => array('sub' => 'employees', 'type' => 'bool', 'label' => 660, 'help' => 6627, 'per' => 'employee', 'group_label' => array(6609, ''),
				'storage' => array('dbrow', 'ansatte', 'lukket', 'raw', 'where' => $empWhere)),
			'organisation.employees.notes' => array('sub' => 'employees', 'type' => 'textarea', 'label' => 659, 'per' => 'employee', 'group_label' => array(6609, ''),
				'storage' => array('dbrow', 'ansatte', 'notes', 'raw', 'where' => $empWhere)),
			'organisation.employees.cprnr' => array('hide_without' => true, 'sub' => 'employees', 'type' => 'text', 'label' => 661, 'per' => 'employee', 'group_label' => array(6610, ''), 'permission' => 'settings.organisation.sensitive',
				'storage' => array('dbrow', 'ansatte', 'cprnr', 'raw', 'where' => $empWhere)),
			'organisation.employees.bank' => array('hide_without' => true, 'sub' => 'employees', 'type' => 'text', 'label' => 662, 'per' => 'employee', 'group_label' => array(6610, ''), 'permission' => 'settings.organisation.sensitive',
				'storage' => array('dbrow', 'ansatte', 'bank', 'raw', 'where' => $empWhere)),
			'organisation.employees.loen' => array('hide_without' => true, 'sub' => 'employees', 'type' => 'decimal', 'label' => 664, 'per' => 'employee', 'group_label' => array(6610, ''), 'permission' => 'settings.organisation.sensitive',
				'decimals' => 2, 'value_map' => array('' => '0'), 'storage' => array('dbrow', 'ansatte', 'loen', 'raw', 'where' => $empWhere)),
			'organisation.employees.extraloen' => array('hide_without' => true, 'sub' => 'employees', 'type' => 'decimal', 'label' => 665, 'per' => 'employee', 'group_label' => array(6610, ''), 'permission' => 'settings.organisation.sensitive',
				'decimals' => 2, 'value_map' => array('' => '0'), 'storage' => array('dbrow', 'ansatte', 'extraloen', 'raw', 'where' => $empWhere)),
			'organisation.employees.user' => array('sub' => 'employees', 'type' => 'info', 'label' => 6612, 'info' => 'employee_user', 'per' => 'employee', 'group_label' => array(6612, ''), 'audit' => false),
			'organisation.employees.background' => array('sub' => 'employees', 'type' => 'select', 'label' => 571, 'help' => 6628, 'per' => 'employee_user', 'group_label' => array(6612, ''),
				'options_from' => 'form_backgrounds', 'options_literal' => true, 'scope' => 'user', 'storage' => array('settings', 'brugerSprog', 'sprog', 'raw'), 'default' => 'Dansk'),
			'organisation.employees.up' => array('sub' => 'employees', 'type' => 'action', 'label' => 6619, 'help' => 6629, 'per' => 'employee', 'run' => 'employee_up', 'confirm_title' => 6619, 'confirm' => 6629),
			'organisation.employees.down' => array('sub' => 'employees', 'type' => 'action', 'label' => 6620, 'help' => 6629, 'per' => 'employee', 'run' => 'employee_down', 'confirm_title' => 6620, 'confirm' => 6629),
			'organisation.employees.delete' => array('sub' => 'employees', 'type' => 'action', 'label' => 6615, 'help' => 6617, 'per' => 'employee', 'danger' => true,
				'confirm_title' => 6615, 'confirm' => 6617, 'run' => 'employee_delete', 'impact' => 'employee_usage'),

			// ---------------------------------------------------------------- G1.1 Company data (the company's own address row, art S)
			'company.data.name' => array('sub' => 'company', 'type' => 'text', 'label' => 28, 'storage' => array('adresser', 'firmanavn'), 'validate' => array('required'), 'maxlength' => 90, 'legacy' => array(779)),
			'company.data.address1' => array('sub' => 'company', 'type' => 'text', 'label' => 648, 'storage' => array('adresser', 'addr1'), 'maxlength' => 60, 'legacy' => array(779)),
			'company.data.address2' => array('sub' => 'company', 'type' => 'text', 'label' => 649, 'storage' => array('adresser', 'addr2'), 'maxlength' => 60, 'legacy' => array(779)),
			'company.data.zip' => array('sub' => 'company', 'type' => 'text', 'label' => 36, 'storage' => array('adresser', 'postnr'), 'maxlength' => 10, 'legacy' => array(779)),
			'company.data.city' => array('sub' => 'company', 'type' => 'text', 'label' => 46, 'storage' => array('adresser', 'bynavn'), 'maxlength' => 60, 'legacy' => array(779)),
			'company.data.cvr' => array('sub' => 'company', 'type' => 'text', 'label' => 376, 'storage' => array('adresser', 'cvrnr'), 'maxlength' => 15, 'legacy' => array(779)),
			'company.data.country' => array('sub' => 'company', 'type' => 'select', 'label' => 6513, 'help' => 6514, 'default' => 'Denmark', 'permission' => 'settings.company.danger',
				'options' => array('Denmark' => 'Danmark', 'Norway' => 'Norge', 'Switzerland' => 'Schweiz'), 'options_literal' => true, 'storage' => array('adresser', 'land'), 'legacy' => array(779)),
			'company.data.phone' => array('sub' => 'contact', 'type' => 'text', 'label' => 37, 'storage' => array('adresser', 'tlf'), 'maxlength' => 60, 'legacy' => array(779)),
			'company.data.mobile' => array('sub' => 'contact', 'type' => 'text', 'label' => 378, 'storage' => array('adresser', 'mobile'), 'maxlength' => 15, 'legacy' => array(779)),
			'company.data.email' => array('sub' => 'contact', 'type' => 'email', 'label' => 52, 'storage' => array('adresser', 'email'), 'maxlength' => 60, 'on_save' => 'company_email', 'legacy' => array(779)),
			'company.data.copy_to_ref' => array('sub' => 'contact', 'type' => 'bool', 'label' => 6512, 'help' => 1880, 'default' => false, 'storage' => array('adresser', 'mailfakt', 'onEmpty'), 'legacy' => array(779)),
			'company.data.bank_name' => array('sub' => 'bank', 'type' => 'text', 'label' => 662, 'storage' => array('adresser', 'bank_navn'), 'maxlength' => 60, 'legacy' => array(779)),
			'company.data.bank_reg' => array('sub' => 'bank', 'type' => 'text', 'label' => 2227, 'storage' => array('adresser', 'bank_reg'), 'maxlength' => 15, 'legacy' => array(779)),
			'company.data.bank_account' => array('sub' => 'bank', 'type' => 'text', 'label' => 592, 'storage' => array('adresser', 'bank_konto'), 'maxlength' => 15, 'legacy' => array(779)),
			'company.data.iban' => array('sub' => 'bank', 'type' => 'text', 'label' => 'IBAN', 'help' => 3367, 'storage' => array('adresser', 'iban'), 'maxlength' => 40, 'legacy' => array(779)),
			'company.data.swift' => array('sub' => 'bank', 'type' => 'text', 'label' => 2228, 'help' => 3367, 'storage' => array('adresser', 'swift'), 'maxlength' => 15, 'legacy' => array(779)),
			'company.data.payment_terms' => array('sub' => 'bank', 'type' => 'select', 'label' => 368, 'help' => 6779, 'default' => 'Netto',
				'options' => array('Netto' => 372, 'Lb. md.' => 373, 'Kontant' => 370, 'Forud' => 369, 'Efterkrav' => 371), 'storage' => array('adresser', 'betalingsbet'), 'legacy' => array(779),
				'keywords' => array('betalingsbetingelser', 'payment terms', 'netto', 'løbende måned')),
			'company.data.payment_days' => array('sub' => 'bank', 'type' => 'int', 'label' => 6778, 'help' => 6779, 'default' => '8', 'unit' => 5025, 'storage' => array('adresser', 'betalingsdage'), 'legacy' => array(779),
				'visible_if' => array('setting_in', 'company.data.payment_terms', array('Netto', 'Lb. md.')), 'keywords' => array('betalingsfrist', 'betalingsdage', 'payment days')),
			'company.data.bs_number' => array('sub' => 'bank', 'type' => 'text', 'label' => 385, 'storage' => array('adresser', 'pbs_nr'), 'maxlength' => 15, 'legacy' => array(779), 'keywords' => array('betalingsservice', 'pbs', 'kreditornummer')),
			'company.data.bs_type' => array('sub' => 'bank', 'type' => 'select', 'label' => 6517, 'default' => '', 'options' => array('' => 2486, 'B' => 2485, 'L' => 2487),
				'storage' => array('adresser', 'pbs'), 'legacy' => array(779), 'visible_if' => array('setting_set', 'company.data.bs_number')),
			'company.data.bs_group' => array('sub' => 'bank', 'type' => 'select', 'label' => 6518, 'default' => '', 'options_from' => 'debtor_groups', 'options_literal' => true,
				'storage' => array('adresser', 'gruppe'), 'legacy' => array(779), 'visible_if' => array('setting_set', 'company.data.bs_number')),
			'company.data.fi_number' => array('sub' => 'bank', 'type' => 'text', 'label' => 'FI', 'storage' => array('adresser', 'bank_fi'), 'maxlength' => 15, 'legacy' => array(779), 'keywords' => array('fi kreditornummer', 'fi-kort')),
			'company.data.gdpr_contact' => array('sub' => 'gdpr', 'type' => 'email', 'label' => 6519, 'storage' => array('adresser', 'kontakt'), 'maxlength' => 60, 'legacy' => array(779)),
			'company.data.dpa' => array('sub' => 'gdpr', 'type' => 'link', 'label' => 2484, 'href' => 'https://saldi.dk/dok/saldi_gdpr_20180525.pdf', 'button' => 6515, 'blank' => true, 'audit' => false),
			'company.data.employees' => array('sub' => 'gdpr', 'type' => 'link', 'label' => 1262, 'help' => 6516, 'href' => 'settingsSection.php?s=organisation.employees', 'button' => 6504, 'audit' => false),

			// ---------------------------------------------------------------- 4c: links for what stays on the old pages for now
			'items.item_groups.price_groups' => array('sub' => 'prices', 'type' => 'link', 'label' => 6508, 'help' => 6874, 'href' => 'settingsSection.php?s=sales.discounts', 'button' => 6504, 'audit' => false,
				'keywords' => array('prisgrupper', 'price groups', 'tilbudsgrupper', 'rabatgrupper')),

			// ---------------------------------------------------------------- G7.3 Projects: the number split that was the kodenr 0 row of projekter.php
			'organisation.projects.number_split' => array('sub' => 'setup', 'type' => 'text', 'label' => 1251, 'help' => 6406,
				'storage' => array('grupper', 'PRJ', 0, 'box1', 'raw', 'row_name' => 'projekt'), 'legacy' => array(773),
				'keywords' => array('projektopdeling', 'project split', 'projektnummer')),

			// ---------------------------------------------------------------- G9.2 Pickup addresses (settings DFM_Pickup, one group_id per address)
			'integrations.pickup.add' => array('sub' => 'addresses', 'type' => 'action', 'label' => 6381, 'help' => 6380, 'confirm_title' => 6381, 'confirm' => 6380, 'run' => 'pickup_add'),
			'integrations.pickup.name1' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1045, 'help' => 1046, 'per' => 'pickup', 'group_label' => array(6389, ''), 'validate' => array('required'),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_pickup_name1', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.name2' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1047, 'help' => 1048, 'per' => 'pickup', 'group_label' => array(6389, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_pickup_name2', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.street1' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1049, 'help' => 1050, 'per' => 'pickup', 'group_label' => array(6389, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_pickup_street1', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.street2' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1051, 'help' => 1052, 'per' => 'pickup', 'group_label' => array(6389, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_pickup_street2', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.zipcode' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1053, 'help' => 1054, 'per' => 'pickup', 'group_label' => array(6389, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_pickup_zipcode', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.town' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1055, 'help' => 1056, 'per' => 'pickup', 'group_label' => array(6389, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_pickup_town', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.button' => array('sub' => 'addresses', 'type' => 'text', 'label' => 3127, 'help' => 3128, 'per' => 'pickup', 'group_label' => array(6389, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_pickup_buttonname', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.client_id' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1014, 'help' => 6388, 'per' => 'pickup', 'group_label' => array(6387, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_id', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.user' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1016, 'help' => 1017, 'per' => 'pickup', 'group_label' => array(6387, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_user', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.password' => array('sub' => 'addresses', 'type' => 'secret', 'label' => 1018, 'help' => 1019, 'per' => 'pickup', 'group_label' => array(6387, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_pass', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.agreement' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1020, 'help' => 1021, 'per' => 'pickup', 'group_label' => array(6387, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_agree', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.api_url' => array('sub' => 'addresses', 'type' => 'text', 'label' => 3129, 'help' => 3130, 'per' => 'pickup', 'group_label' => array(6387, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_url', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.hub' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1022, 'help' => 1023, 'per' => 'pickup', 'group_label' => array(6387, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_hub', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.shipping_type' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1024, 'help' => 1025, 'per' => 'pickup', 'group_label' => array(6387, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_ship', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.goods_type' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1026, 'help' => 1027, 'per' => 'pickup', 'group_label' => array(6387, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_good', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.goods_description' => array('sub' => 'addresses', 'type' => 'text', 'label' => 6081, 'help' => 1039, 'per' => 'pickup', 'group_label' => array(6387, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_gooddes', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.payment' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1028, 'help' => 1029, 'per' => 'pickup', 'group_label' => array(6387, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_pay', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.delivery' => array('sub' => 'addresses', 'type' => 'text', 'label' => 1058, 'help' => 1059, 'per' => 'pickup', 'group_label' => array(6387, ''),
				'storage' => array('settings', 'DFM_Pickup', 'dfm_sercode', 'raw'), 'legacy' => $divvalg),
			'integrations.pickup.delete' => array('sub' => 'addresses', 'type' => 'action', 'label' => 6383, 'help' => 6384, 'per' => 'pickup', 'danger' => true,
				'confirm_title' => 6385, 'confirm' => 6384, 'run' => 'pickup_delete'),

			// ---------------------------------------------------------------- G10.3 / G10.4 Payment cards (one drawer per card; the lists are tab-joined per fiscal year, R7)
			'pos.cards.providers' => array('sub' => 'general', 'item' => 'general', 'type' => 'info', 'label' => 6329, 'info' => 'pos_providers', 'audit' => false),
			'pos.cards.integrations' => array('sub' => 'general', 'item' => 'general', 'type' => 'link', 'label' => 6330, 'href' => 'settingsSection.php?s=integrations.connections', 'button' => 6331, 'audit' => false),
			'pos.cards.other_cards_account' => array('sub' => 'general', 'item' => 'general', 'type' => 'account', 'label' => 712, 'help' => 6359,
				'storage' => array('grupper', 'POS', 2, 'box6', 'raw', 'row_name' => 'Pos valg', 'fiscal' => true), 'legacy' => $pos),
			'pos.cards.change_card_value' => array('sub' => 'general', 'item' => 'general', 'type' => 'bool', 'label' => 6325, 'help' => 6326, 'default' => false,
				'storage' => array('settings', 'Paycards', 'change_cardvalue', 'onEmpty'), 'legacy' => $pos),
			'pos.cards.add' => array('sub' => 'cards', 'type' => 'action', 'label' => 6318, 'help' => 6315, 'confirm_title' => 6318, 'confirm' => 6315, 'run' => 'card_add'),
			'pos.cards.name' => array('sub' => 'cards', 'type' => 'text', 'label' => 6314, 'help' => 6315, 'per' => 'card', 'validate' => array('required'),
				'storage' => array('grupper', 'POS', 1, 'box5', 'raw', 'row_name' => 'POS_valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.cards.account' => array('sub' => 'cards', 'type' => 'account', 'label' => 284, 'help' => 282, 'per' => 'card',
				'storage' => array('grupper', 'POS', 1, 'box6', 'raw', 'row_name' => 'POS_valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.cards.terminal_card' => array('sub' => 'cards', 'type' => 'bool', 'label' => 710, 'help' => 6357, 'per' => 'card', 'default' => false,
				'storage' => array('grupper', 'POS', 2, 'box5', 'onEmpty', 'row_name' => 'Pos valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.cards.active' => array('sub' => 'cards', 'type' => 'bool', 'label' => 860, 'help' => 6358, 'per' => 'card', 'default' => true,
				'storage' => array('settings', 'Paycards', 'card_enabled', 'onEmpty', 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.cards.voucher' => array('sub' => 'cards', 'type' => 'bool', 'label' => 2272, 'help' => 855, 'per' => 'card', 'default' => false,
				'storage' => array('grupper', 'POS', 3, 'box4', 'onEmpty', 'row_name' => 'Pos valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.cards.voucher_item' => array('sub' => 'cards', 'type' => 'item', 'item_as' => 'id', 'label' => 6327, 'help' => 6328, 'per' => 'card',
				'storage' => array('settings', 'Paycards', 'voucherItems', 'raw', 'join' => "\t", 'list' => true), 'legacy' => $pos, 'visible_if' => array('setting', 'pos.cards.voucher', true)),
			'pos.cards.up' => array('sub' => 'cards', 'type' => 'action', 'label' => 6319, 'help' => 6363, 'per' => 'card', 'confirm_title' => 6319, 'confirm' => 6363, 'run' => 'card_up'),
			'pos.cards.down' => array('sub' => 'cards', 'type' => 'action', 'label' => 6320, 'help' => 6364, 'per' => 'card', 'confirm_title' => 6320, 'confirm' => 6364, 'run' => 'card_down'),
			'pos.cards.remove' => array('sub' => 'cards', 'type' => 'action', 'label' => 6321, 'help' => 6322, 'per' => 'card', 'danger' => true,
				'confirm_title' => 6323, 'confirm' => 6322, 'run' => 'card_remove'),

			// ---------------------------------------------------------------- G10.5 Tables & floor plans
			'pos.tables.count' => array('sub' => 'tables', 'type' => 'int', 'label' => 674, 'help' => 6342, 'default' => 0,
				'storage' => array('virtual', 'table_count'), 'legacy' => $pos, 'validate' => array('range', 0, 200), 'keywords' => array('borde', 'tables', 'antal borde')),
			'pos.tables.name' => array('sub' => 'tables', 'type' => 'text', 'label' => 676, 'per' => 'table', 'validate' => array('required'),
				'storage' => array('grupper', 'POS', 2, 'box7', 'raw', 'row_name' => 'Pos valg', 'fiscal' => true, 'join' => "\t", 'list' => true), 'legacy' => $pos),
			'pos.tables.plan_count' => array('sub' => 'plans', 'type' => 'int', 'label' => 6345, 'help' => 6346, 'default' => 0,
				'storage' => array('virtual', 'floor_plan_count'), 'legacy' => $pos, 'validate' => array('range', 0, 50), 'keywords' => array('bordplaner', 'floor plans')),
			'pos.tables.plan_name' => array('sub' => 'plans', 'type' => 'text', 'label' => 138, 'per' => 'floor_plan',
				'storage' => array('dbrow', 'table_pages', 'name'), 'legacy' => $pos),
			'pos.tables.open_planner' => array('sub' => 'plans', 'type' => 'link', 'label' => 6348, 'help' => 6350, 'href' => '../bordplaner/planner/', 'button' => 6349, 'blank' => true, 'audit' => false,
				'keywords' => array('bordplanlægger', 'floor planner')),

			'pos.cash.opening_float' => array('sub' => 'cash', 'type' => 'int', 'label' => 6304, 'help' => 701, 'unit' => 'kr', 'default' => 0,
				'storage' => array('grupper', 'POS', 2, 'box1', 'raw', 'row_name' => 'Pos valg', 'fiscal' => true), 'legacy' => $pos),
			'pos.cash.count_assist' => array('sub' => 'cash', 'type' => 'bool', 'label' => 6305, 'help' => 6306, 'default' => false,
				'storage' => array('grupper', 'POS', 2, 'box2', 'onEmpty', 'row_name' => 'Pos valg', 'fiscal' => true), 'legacy' => $pos),
			'pos.cash.withdraw_zero' => array('sub' => 'cash', 'type' => 'bool', 'label' => 838, 'help' => 837, 'default' => false,
				'storage' => array('grupper', 'POS', 2, 'box14', 'onEmpty', 'row_name' => 'Pos valg', 'fiscal' => true), 'legacy' => $pos),
			'pos.cash.discount_item' => array('sub' => 'discount', 'type' => 'item', 'item_as' => 'id', 'label' => 287, 'help' => 288,
				'storage' => array('grupper', 'POS', 1, 'box8', 'raw', 'row_name' => 'POS_valg', 'fiscal' => true), 'legacy' => $pos),

			// ---------------------------------------------------------------- G10.2 / G10.6 / G10.7 POS shop-wide options
			// POS/1-3 rows exist per fiscal year (spec R2): read from the current year, written to every year.
			'pos.receipt.print_receipt' => array('sub' => 'receipt', 'type' => 'bool', 'label' => 6267, 'help' => 6273, 'default' => false,
				'storage' => array('grupper', 'POS', 1, 'box10', 'onEmpty', 'row_name' => 'POS_valg', 'fiscal' => true), 'legacy' => $pos,
				'keywords' => array('udskriv bon', 'print receipt')),
			'pos.receipt.disable_print' => array('sub' => 'receipt', 'type' => 'bool', 'label' => 1730, 'help' => 1731, 'default' => false,
				'storage' => array('settings', 'globals', 'deactivateBonprint', 'onEmpty'), 'legacy' => $pos,
				'keywords' => array('deaktiver bonprint', 'disable receipt')),
			'pos.receipt.timeout' => array('sub' => 'receipt', 'type' => 'int', 'label' => 463, 'help' => 462, 'unit' => 6270, 'default' => 0,
				'storage' => array('grupper', 'POS', 1, 'box13', 'raw', 'row_name' => 'POS_valg', 'fiscal' => true), 'legacy' => $pos, 'validate' => array('range', 0, 3600),
				'keywords' => array('tidsfrist', 'timeout', 'ny ordre')),
			'pos.receipt.flatpay_print' => array('sub' => 'receipt', 'type' => 'bool', 'label' => 6355, 'help' => 6356, 'default' => false,
				'storage' => array('settings', 'POS', 'flatpay_print', 'oneZero'), 'legacy' => $pos, 'keywords' => array('flatpay', 'terminalkvittering', 'terminal receipt')),

			'pos.kitchen.kds_active' => array('sub' => 'kds', 'type' => 'bool', 'label' => 6261, 'help' => 6262, 'default' => false,
				'storage' => array('settings', 'KDS', 'activated', 'onOff'), 'legacy' => $pos, 'keywords' => array('kds', 'køkkenskærm')),
			'pos.kitchen.kds_columns' => array('sub' => 'kds', 'type' => 'int', 'label' => 6265, 'default' => 5,
				'storage' => array('settings', 'KDS', 'columns', 'raw'), 'legacy' => $pos, 'validate' => array('range', 1, 20),
				'visible_if' => array('setting', 'pos.kitchen.kds_active', true), 'keywords' => array('kds', 'kolonner', 'columns')),
			'pos.kitchen.kds_height' => array('sub' => 'kds', 'type' => 'int', 'label' => 6266, 'unit' => 'px', 'default' => 20,
				'storage' => array('settings', 'KDS', 'height', 'raw'), 'legacy' => $pos, 'validate' => array('range', 8, 200),
				'visible_if' => array('setting', 'pos.kitchen.kds_active', true), 'keywords' => array('kds', 'linjehøjde', 'line height')),
			'pos.kitchen.print_active' => array('sub' => 'print', 'type' => 'bool', 'label' => 6263, 'help' => 6264, 'default' => true,
				'storage' => array('settings', 'kitchen-print', 'activated', 'onOff'), 'legacy' => $pos, 'keywords' => array('køkkenprint', 'kitchen print')),

			'pos.screen.cash_button' => array('sub' => 'buttons', 'type' => 'bool', 'label' => 459, 'help' => 458, 'default' => false,
				'storage' => array('grupper', 'POS', 1, 'box12', 'onEmpty', 'row_name' => 'POS_valg', 'fiscal' => true), 'legacy' => $pos),
			'pos.screen.account_lookup' => array('sub' => 'buttons', 'type' => 'bool', 'label' => 461, 'help' => 460, 'default' => false,
				'storage' => array('grupper', 'POS', 1, 'box11', 'onEmpty', 'row_name' => 'POS_valg', 'fiscal' => true), 'legacy' => $pos),
			'pos.screen.deposit_button' => array('sub' => 'buttons', 'type' => 'bool', 'label' => 465, 'help' => 464, 'default' => false,
				'storage' => array('grupper', 'POS', 1, 'box14', 'onEmpty', 'row_name' => 'POS_valg', 'fiscal' => true), 'legacy' => $pos),
			'pos.screen.set_button' => array('sub' => 'buttons', 'type' => 'bool', 'label' => 735, 'help' => 6274, 'default' => false,
				'storage' => array('grupper', 'POS', 2, 'box12', 'onEmpty', 'row_name' => 'Pos valg', 'fiscal' => true), 'legacy' => $pos),
			'pos.screen.set_item' => array('sub' => 'buttons', 'type' => 'item', 'item_as' => 'id', 'label' => 6268, 'help' => 6269,
				'storage' => array('grupper', 'POS', 2, 'box11', 'raw', 'row_name' => 'Pos valg', 'fiscal' => true), 'legacy' => $pos,
				'visible_if' => array('setting', 'pos.screen.set_button', true)),
			'pos.screen.forced_user' => array('sub' => 'sale', 'type' => 'bool', 'label' => 840, 'help' => 839, 'default' => false,
				'storage' => array('grupper', 'POS', 3, 'box1', 'onEmpty', 'row_name' => 'Pos valg', 'fiscal' => true), 'legacy' => $pos),
			'pos.screen.jump_to_price' => array('sub' => 'sale', 'type' => 'bool', 'label' => 1962, 'help' => 1961, 'default' => false,
				'storage' => array('settings', 'globals', 'jump2price', 'onEmpty'), 'legacy' => $pos),
			'pos.screen.customer_display' => array('sub' => 'display', 'type' => 'bool', 'label' => 847, 'help' => 848, 'default' => false,
				'storage' => array('grupper', 'POS', 3, 'box3', 'onEmpty', 'row_name' => 'Pos valg', 'fiscal' => true), 'legacy' => $pos),
			'pos.screen.show_stock' => array('sub' => 'display', 'type' => 'bool', 'label' => 2367, 'help' => 2368, 'default' => false,
				'storage' => array('settings', 'POS', 'show_stock', 'onOff'), 'legacy' => $pos),
			'pos.screen.big_total' => array('sub' => 'display', 'type' => 'bool', 'label' => 2407, 'help' => 2408, 'default' => false,
				'storage' => array('settings', 'POS', 'show_big_sum', 'onOff'), 'legacy' => $pos),

			// ---------------------------------------------------------------- G4.3 Supplier price lists (one drawer per grupper PL row)
			'purchase.pricelists.create' => array('sub' => 'lists', 'type' => 'action', 'label' => 6221, 'help' => 6220,
				'confirm_title' => 6221, 'confirm' => 6220, 'run' => 'pricelist_create', 'audit' => false),
			'purchase.pricelists.description' => array('sub' => 'lists', 'type' => 'text', 'label' => 914, 'per' => 'pricelist', 'validate' => array('required'),
				'storage' => array('grupper_row', 'PL', 'beskrivelse'), 'legacy' => $prisliste, 'keywords' => array('prisliste', 'beskrivelse', 'description')),
			'purchase.pricelists.url' => array('sub' => 'lists', 'type' => 'text', 'label' => 6223, 'help' => 6224, 'per' => 'pricelist', 'validate' => array('csv_url'),
				'storage' => array('grupper_row', 'PL', 'box2'), 'legacy' => $prisliste, 'keywords' => array('prisfil', 'url', 'csv', 'price file')),
			'purchase.pricelists.delimiter' => array('sub' => 'lists', 'type' => 'select', 'label' => 6225, 'per' => 'pricelist', 'default' => ';',
				'options' => array(';' => 6227, ',' => 6226, 'tab' => 6228), 'value_map' => array('tab' => "\t"),
				'storage' => array('grupper_row', 'PL', 'box10'), 'legacy' => $prisliste, 'keywords' => array('skilletegn', 'delimiter', 'separator')),
			'purchase.pricelists.encoding' => array('sub' => 'lists', 'type' => 'select', 'label' => 6229, 'per' => 'pricelist', 'default' => 'utf-8',
				'options' => array('utf-8' => 'UTF-8', 'iso-8859' => 'ISO-8859-1'), 'options_literal' => true,
				'storage' => array('grupper_row', 'PL', 'box11'), 'legacy' => $prisliste, 'keywords' => array('tegnsæt', 'encoding', 'charset')),
			'purchase.pricelists.item_group' => array('sub' => 'lists', 'type' => 'select', 'label' => 6230, 'help' => 6231, 'per' => 'pricelist', 'default' => '',
				'options_from' => 'item_groups', 'options_literal' => true, 'on_save' => 'pricelist_group_name',
				'storage' => array('grupper_row', 'PL', 'kodenr'), 'legacy' => $prisliste, 'keywords' => array('varegruppe', 'item group')),
			'purchase.pricelists.supplier' => array('sub' => 'lists', 'type' => 'select', 'label' => 6232, 'per' => 'pricelist', 'default' => '',
				'options_from' => 'creditor_names', 'options_literal' => true,
				'storage' => array('grupper_row', 'PL', 'box9'), 'legacy' => $prisliste, 'keywords' => array('leverandør', 'supplier', 'kreditor')),
			'purchase.pricelists.use' => array('sub' => 'lists', 'type' => 'action', 'label' => 6235, 'help' => 6236, 'per' => 'pricelist',
				'confirm_title' => 6237, 'confirm' => 6238, 'run' => 'pricelist_use', 'legacy' => $prisliste),
			'purchase.pricelists.test' => array('sub' => 'lists', 'type' => 'action', 'label' => 6251, 'help' => 6239, 'per' => 'pricelist',
				'confirm_title' => 6240, 'confirm' => 6241, 'run' => 'pricelist_test', 'legacy' => $prisliste),
			'purchase.pricelists.delete' => array('sub' => 'lists', 'type' => 'action', 'label' => 6242, 'help' => 6243, 'per' => 'pricelist', 'danger' => true,
				'confirm_title' => 6244, 'confirm' => 6245, 'run' => 'pricelist_delete', 'legacy' => $prisliste),

			// ---------------------------------------------------------------- G2.6 Document storage
			'finance.document_storage.type' => array('sub' => 'storage', 'type' => 'select', 'label' => 1341, 'help' => 6214, 'default' => '',
				'options' => array('internFTP' => 1342, 'externFTP' => 1343, '' => 1344),
				'storage' => array('virtual', 'document_storage'), 'legacy' => $bilag,
				'keywords' => array('opbevaring', 'storage', 'intern opbevaring', 'internal storage', 'ftp', 'ingen opbevaring')),
			'finance.document_storage.mail_address' => array('sub' => 'storage', 'type' => 'info', 'label' => 6216, 'help' => 6217, 'info' => 'document_mail', 'audit' => false,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('internFTP')),
				'keywords' => array('bilag mail', 'scan to mail', 'bilag_')),
			'finance.document_storage.ftp_server' => array('sub' => 'ftp', 'type' => 'text', 'label' => 1346, 'help' => 6215, 'ensure_suffix' => '/',
				'storage' => array('grupper', 'bilag', 1, 'box1', 'raw', 'row_name' => 'Bilag og dokumenter'), 'legacy' => $bilag,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('externFTP')),
				'keywords' => array('ftp server', 'ftp-server', 'host')),
			'finance.document_storage.ftp_user' => array('sub' => 'ftp', 'type' => 'text', 'label' => 1347,
				'storage' => array('grupper', 'bilag', 1, 'box2', 'raw', 'row_name' => 'Bilag og dokumenter'), 'legacy' => $bilag,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('externFTP')),
				'keywords' => array('ftp', 'brugernavn', 'username')),
			'finance.document_storage.ftp_password' => array('sub' => 'ftp', 'type' => 'secret', 'label' => 1348,
				'storage' => array('grupper', 'bilag', 1, 'box3', 'urlencode', 'row_name' => 'Bilag og dokumenter'), 'legacy' => $bilag,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('externFTP')),
				'keywords' => array('ftp', 'adgangskode', 'password')),
			'finance.document_storage.ftp_folder_vouchers' => array('sub' => 'ftp', 'type' => 'text', 'label' => 1350, 'help' => 6218, 'default' => 'bilag',
				'storage' => array('grupper', 'bilag', 1, 'box4', 'raw', 'row_name' => 'Bilag og dokumenter'), 'legacy' => $bilag,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('externFTP')),
				'keywords' => array('ftp', 'mappe', 'folder', 'bilag')),
			'finance.document_storage.ftp_folder_documents' => array('sub' => 'ftp', 'type' => 'text', 'label' => 1351, 'help' => 6218, 'default' => 'dokumenter',
				'storage' => array('grupper', 'bilag', 1, 'box5', 'raw', 'row_name' => 'Bilag og dokumenter'), 'legacy' => $bilag,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('externFTP')),
				'keywords' => array('ftp', 'mappe', 'folder', 'dokumenter')),
			'finance.document_storage.ftp_test' => array('sub' => 'ftp', 'type' => 'action', 'label' => 6208, 'help' => 6209,
				'confirm_title' => 6210, 'confirm' => 6211, 'run' => 'ftp_test', 'legacy' => $bilag,
				'visible_if' => array('setting_in', 'finance.document_storage.type', array('externFTP')),
				'keywords' => array('ftp test', 'test forbindelse', 'test connection')),
			'finance.document_storage.google_docs' => array('sub' => 'viewer', 'type' => 'bool', 'label' => 719, 'help' => 720, 'default' => false,
				'storage' => array('grupper', 'bilag', 1, 'box7', 'onEmpty', 'row_name' => 'Bilag og dokumenter'), 'legacy' => $bilag,
				'keywords' => array('google docs', 'viewer', 'visning')),

			// ---------------------------------------------------------------- G3.4 Payment terms & reminders
			'sales.reminders.responsible_user' => array('sub' => 'responsible', 'type' => 'select', 'label' => 225, 'help' => 6191, 'default' => '',
				'options_from' => 'users', 'options_mixed' => true,
				'storage' => array('grupper', 'DIV', 4, 'box1', 'raw', 'row_name' => 'Div_valg (Rykker)'), 'legacy' => $rykker,
				'keywords' => array('rykkeransvarlig', 'reminder manager', 'bruger', 'user')),
			'sales.reminders.responsible_mail' => array('sub' => 'responsible', 'type' => 'email', 'label' => 227, 'help' => 6192,
				'storage' => array('grupper', 'DIV', 4, 'box2', 'raw', 'row_name' => 'Div_valg (Rykker)'), 'legacy' => $rykker,
				'keywords' => array('rykkeransvarlig', 'mailadresse', 'e-mail')),
			'sales.reminders.days_1' => array('sub' => 'deadlines', 'type' => 'int', 'label' => 233, 'help' => 232, 'unit' => 1332, 'empty_ok' => true,
				'storage' => array('grupper', 'DIV', 4, 'box5', 'raw', 'row_name' => 'Div_valg (Rykker)'), 'legacy' => $rykker, 'validate' => array('range', 0, 365),
				'keywords' => array('rykker 1', 'reminder 1', 'frist', 'dage', 'days')),
			'sales.reminders.days_2' => array('sub' => 'deadlines', 'type' => 'int', 'label' => 235, 'help' => 234, 'unit' => 1332, 'empty_ok' => true,
				'storage' => array('grupper', 'DIV', 4, 'box6', 'raw', 'row_name' => 'Div_valg (Rykker)'), 'legacy' => $rykker, 'validate' => array('range', 0, 365),
				'keywords' => array('rykker 2', 'reminder 2', 'frist', 'dage', 'days')),
			'sales.reminders.days_3' => array('sub' => 'deadlines', 'type' => 'int', 'label' => 237, 'help' => 236, 'unit' => 1332, 'empty_ok' => true,
				'storage' => array('grupper', 'DIV', 4, 'box7', 'raw', 'row_name' => 'Div_valg (Rykker)'), 'legacy' => $rykker, 'validate' => array('range', 0, 365),
				'keywords' => array('rykker 3', 'reminder 3', 'frist', 'dage', 'days')),
			'sales.reminders.collection_lawyer' => array('sub' => 'collection', 'type' => 'creditor', 'creditor_as' => 'id', 'label' => 6193, 'help' => 6194,
				'storage' => array('grupper', 'DIV', 4, 'box9', 'raw', 'row_name' => 'Div_valg (Rykker)'), 'legacy' => $rykker,
				'keywords' => array('inkasso', 'inkassoadvokat', 'debt collection', 'kreditor', 'creditor')),
			'sales.reminders.fee_item_1' => array('sub' => 'fees', 'type' => 'item', 'item_as' => 'id', 'label' => 6196, 'help' => 6197, 'per' => 'language', 'group_label' => array(6195, 1),
				'storage' => array('formularer', 6, 'xb', 'raw'), 'legacy' => array(780), 'keywords' => array('rykkergebyr', 'reminder fee', 'gebyr', 'rykker 1')),
			'sales.reminders.interest_item_1' => array('sub' => 'fees', 'type' => 'item', 'item_as' => 'id', 'label' => 6198, 'help' => 6199, 'per' => 'language', 'group_label' => array(6195, 1),
				'storage' => array('formularer', 6, 'yb', 'raw'), 'legacy' => array(780), 'keywords' => array('rentevare', 'interest item', 'rykker 1')),
			'sales.reminders.interest_rate_1' => array('sub' => 'fees', 'type' => 'decimal', 'label' => 6200, 'help' => 6201, 'unit' => '%', 'per' => 'language', 'group_label' => array(6195, 1),
				'storage' => array('formularer', 6, 'str', 'raw'), 'legacy' => array(780), 'keywords' => array('rentesats', 'interest rate', 'rykker 1')),
			'sales.reminders.fee_item_2' => array('sub' => 'fees', 'type' => 'item', 'item_as' => 'id', 'label' => 6196, 'help' => 6197, 'per' => 'language', 'group_label' => array(6195, 2),
				'storage' => array('formularer', 7, 'xb', 'raw'), 'legacy' => array(780), 'keywords' => array('rykkergebyr', 'reminder fee', 'gebyr', 'rykker 2')),
			'sales.reminders.interest_item_2' => array('sub' => 'fees', 'type' => 'item', 'item_as' => 'id', 'label' => 6198, 'help' => 6199, 'per' => 'language', 'group_label' => array(6195, 2),
				'storage' => array('formularer', 7, 'yb', 'raw'), 'legacy' => array(780), 'keywords' => array('rentevare', 'interest item', 'rykker 2')),
			'sales.reminders.interest_rate_2' => array('sub' => 'fees', 'type' => 'decimal', 'label' => 6200, 'help' => 6201, 'unit' => '%', 'per' => 'language', 'group_label' => array(6195, 2),
				'storage' => array('formularer', 7, 'str', 'raw'), 'legacy' => array(780), 'keywords' => array('rentesats', 'interest rate', 'rykker 2')),
			'sales.reminders.fee_item_3' => array('sub' => 'fees', 'type' => 'item', 'item_as' => 'id', 'label' => 6196, 'help' => 6197, 'per' => 'language', 'group_label' => array(6195, 3),
				'storage' => array('formularer', 8, 'xb', 'raw'), 'legacy' => array(780), 'keywords' => array('rykkergebyr', 'reminder fee', 'gebyr', 'rykker 3')),
			'sales.reminders.interest_item_3' => array('sub' => 'fees', 'type' => 'item', 'item_as' => 'id', 'label' => 6198, 'help' => 6199, 'per' => 'language', 'group_label' => array(6195, 3),
				'storage' => array('formularer', 8, 'yb', 'raw'), 'legacy' => array(780), 'keywords' => array('rentevare', 'interest item', 'rykker 3')),
			'sales.reminders.interest_rate_3' => array('sub' => 'fees', 'type' => 'decimal', 'label' => 6200, 'help' => 6201, 'unit' => '%', 'per' => 'language', 'group_label' => array(6195, 3),
				'storage' => array('formularer', 8, 'str', 'raw'), 'legacy' => array(780), 'keywords' => array('rentesats', 'interest rate', 'rykker 3')),

			// ---------------------------------------------------------------- G6.3 E-mail
			'documents.email.smtp_host' => array('sub' => 'server', 'type' => 'text', 'label' => 6170, 'help' => 6184,
				'storage' => array('adresser', 'felt_1', 'raw'), 'legacy' => $konto, 'on_save' => 'smtp_changed',
				'keywords' => array('smtp', 'mailserver', 'mail server', 'port')),
			'documents.email.smtp_user' => array('sub' => 'server', 'type' => 'text', 'label' => 225, 'help' => 749,
				'storage' => array('adresser', 'felt_2', 'raw'), 'legacy' => $konto, 'on_save' => 'smtp_changed',
				'keywords' => array('smtp', 'brugernavn', 'username')),
			'documents.email.smtp_password' => array('sub' => 'server', 'type' => 'secret', 'label' => 324, 'help' => 750,
				'storage' => array('adresser', 'felt_3', 'raw'), 'legacy' => $konto, 'on_save' => 'smtp_changed',
				'keywords' => array('smtp', 'adgangskode', 'password')),
			'documents.email.smtp_encryption' => array('sub' => 'server', 'type' => 'select', 'label' => 748, 'help' => 751, 'default' => '',
				'options' => array('' => 6171, 'ssl' => 'SSL', 'tls' => 'TLS'), 'options_mixed' => true,
				'storage' => array('adresser', 'felt_4', 'raw'), 'legacy' => $konto, 'on_save' => 'smtp_changed',
				'keywords' => array('smtp', 'kryptering', 'encryption', 'ssl', 'tls')),
			'documents.email.sender_email' => array('sub' => 'sender', 'type' => 'email', 'label' => 6172, 'help' => 6173, 'per' => 'language', 'per_default_label' => 6183,
				'storage' => array('settings', 'email_settings', 'sender_email', 'raw'), 'legacy' => array(573, 6166),
				'keywords' => array('afsender', 'sender', 'afsender e-mail', 'sender e-mail', 'from')),
			'documents.email.sender_name' => array('sub' => 'sender', 'type' => 'text', 'label' => 6174, 'help' => 6175, 'per' => 'language', 'per_default_label' => 6183,
				'storage' => array('settings', 'email_settings', 'sender_name', 'raw'), 'legacy' => array(573, 6166),
				'keywords' => array('afsender', 'sender', 'afsendernavn', 'sender name')),
			// 20261019 CL/ASR invoice format: classic PDF mail or link to the invoice & payment page
			'documents.email.invoice_format' => array('sub' => 'format', 'type' => 'select', 'label' => 7101, 'help' => 7102, 'default' => 'pdf',
				'options' => array('pdf' => 7103, 'link' => 7104),
				'storage' => array('settings', 'email_settings', 'invoice_format', 'raw'),
				'keywords' => array('fakturaformat', 'invoice format', 'betalingslink', 'payment link', 'stripe', 'kortbetaling', 'card payment')),
			'documents.email.invoice_link_text' => array('sub' => 'format', 'type' => 'textarea', 'label' => 7105, 'help' => 7106,
				'storage' => array('settings', 'email_settings', 'invoice_link_text', 'raw'),
				'keywords' => array('fakturamail', 'invoice mail', 'link', 'tekst', 'text')),
			'documents.email.customer_subject' => array('sub' => 'texts', 'type' => 'text', 'label' => 6176, 'help' => 1150,
				'storage' => array('settings', 'debitor', 'mailSubject', 'raw'), 'legacy' => array(606, 6169),
				'keywords' => array('mailtekst', 'mail text', 'emne', 'subject', 'kunder', 'customers')),
			'documents.email.customer_text' => array('sub' => 'texts', 'type' => 'textarea', 'label' => 6177, 'help' => 1151,
				'storage' => array('settings', 'debitor', 'mailText', 'raw'), 'legacy' => array(606, 6169),
				'keywords' => array('mailtekst', 'mail text', 'kunder', 'customers', '$kunde')),
			'documents.email.mysale_subject' => array('sub' => 'texts', 'type' => 'text', 'label' => 6178, 'help' => 1152,
				'storage' => array('settings', 'mySale', 'mailSubject', 'raw'), 'legacy' => array(606, 6169),
				'keywords' => array('mit salg', 'mysale', 'invitation', 'emne', 'subject')),
			'documents.email.mysale_text' => array('sub' => 'texts', 'type' => 'textarea', 'label' => 6179, 'help' => 6185,
				'storage' => array('settings', 'mySale', 'mailText', 'raw'), 'legacy' => array(606, 6169),
				'keywords' => array('mit salg', 'mysale', 'invitation', '$link')),
			'documents.email.paylist_text' => array('sub' => 'texts', 'type' => 'textarea', 'label' => 6180, 'help' => 6181,
				'storage' => array('settings', 'paylist', 'mailText', 'raw'),
				'keywords' => array('afregning', 'settlement', 'betalingsliste', 'payment list', 'kommission', 'commission')),

			// ---------------------------------------------------------------- G9 Integrations (list + drawer)
			'integrations.rest_api.key' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'secret', 'label' => 819, 'help' => 820,
				'storage' => array('grupper', 'API', 1, 'box1', 'raw', 'row_name' => 'API valg'), 'legacy' => $api,
				'keywords' => array('api nøgle', 'api key', 'nøgle', 'token')),
			'integrations.rest_api.ip_list' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'text', 'label' => 821, 'help' => 822,
				'storage' => array('grupper', 'API', 1, 'box2', 'raw', 'row_name' => 'API valg'), 'legacy' => $api, 'validate' => array('ip_list'),
				'keywords' => array('ip', 'ip-adresse', 'ip address', 'whitelist')),
			'integrations.rest_api.client_url' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'text', 'label' => 6061, 'help' => 830,
				'storage' => array('grupper', 'API', 1, 'box4', 'raw', 'row_name' => 'API valg'), 'legacy' => $api,
				'keywords' => array('api klient', 'api client', 'varesync')),
			'integrations.rest_api.client_url2' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'text', 'label' => 6153, 'help' => 6155,
				'storage' => array('grupper', 'API', 1, 'box5', 'raw', 'row_name' => 'API valg'), 'legacy' => $api),
			'integrations.rest_api.client_url3' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'text', 'label' => 6154, 'help' => 6155,
				'storage' => array('grupper', 'API', 1, 'box6', 'raw', 'row_name' => 'API valg'), 'legacy' => $api),
			'integrations.rest_api.invoice_email' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'bool', 'label' => 6062, 'help' => 6063, 'default' => false,
				'storage' => array('settings', 'api', 'api_invoice_email_enabled', 'onEmpty'), 'legacy' => $api,
				'keywords' => array('faktura e-mail', 'invoice e-mail')),
			'integrations.rest_api.db' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'info', 'label' => 6147, 'help' => 832, 'info' => 'saldi_db', 'audit' => false),
			'integrations.rest_api.url' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'info', 'label' => 6148, 'help' => 836, 'info' => 'saldi_url', 'audit' => false),
			'integrations.rest_api.swagger' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'link', 'label' => 6149, 'href' => '../restapi/swagger-ui.html#/', 'button' => 6157, 'blank' => true, 'audit' => false,
				'keywords' => array('swagger', 'dokumentation', 'documentation')),
			'integrations.rest_api.generate_key' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'rest_api', 'type' => 'action', 'label' => 6064, 'help' => 6065,
				'confirm_title' => 6066, 'confirm' => 6067, 'run' => 'generate_api_key', 'legacy' => $api,
				'keywords' => array('ny api nøgle', 'new api key', 'generér', 'generate')),

			'integrations.webshop.url' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'webshop', 'type' => 'text', 'label' => 6070, 'help' => 6071,
				'storage' => array('grupper', 'DIV', 5, 'box2', 'raw', 'row_name' => 'Div_valg'), 'legacy' => $api,
				'keywords' => array('shop url', 'webshop url', 'shopurl')),
			'integrations.webshop.charset' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'webshop', 'type' => 'select', 'label' => 6072, 'default' => 'UTF-8',
				'options' => array('UTF-8' => 'UTF-8', 'ISO-8859-1' => 'ISO-8859-1'), 'options_literal' => true,
				'storage' => array('grupper', 'DIV', 5, 'box7', 'raw', 'row_name' => 'Div_valg'), 'legacy' => $api,
				'keywords' => array('tegnsæt', 'charset', 'encoding', 'utf-8', 'iso-8859-1')),
			'integrations.webshop.sync_new' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'webshop', 'type' => 'action', 'label' => 741, 'help' => 740,
				'confirm_title' => 741, 'confirm' => 740, 'run' => 'shop_sync_new', 'blank' => true, 'legacy' => $api,
				'keywords' => array('hent varer', 'fetch items', 'varesync')),
			'integrations.webshop.sync_update' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'webshop', 'type' => 'action', 'label' => 2546, 'help' => 1726,
				'confirm_title' => 2546, 'confirm' => 1726, 'run' => 'shop_sync_update', 'blank' => true, 'legacy' => $api,
				'keywords' => array('opdater fra shop', 'update from shop', 'varesync')),

			'integrations.quickpay.agreement_id' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'quickpay', 'type' => 'text', 'label' => 6074, 'help' => 6075,
				'storage' => array('settings', 'quickpay', 'qp_agreement_id', 'raw'), 'legacy' => $divvalg,
				'keywords' => array('quickpay', 'aftale id', 'agreement id')),
			'integrations.quickpay.merchant' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'quickpay', 'type' => 'text', 'label' => 6076, 'help' => 691,
				'storage' => array('settings', 'quickpay', 'qp_merchant', 'raw'), 'legacy' => $divvalg,
				'keywords' => array('quickpay', 'merchant')),
			'integrations.quickpay.md5secret' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'quickpay', 'type' => 'secret', 'label' => 6077, 'help' => 6078,
				'storage' => array('settings', 'quickpay', 'qp_md5secret', 'raw'), 'legacy' => $divvalg,
				'keywords' => array('quickpay', 'md5', 'secret')),
			'integrations.quickpay.item_group' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'api', 'item' => 'quickpay', 'type' => 'select', 'label' => 6161, 'help' => 6162, 'default' => '',
				'options_from' => 'item_groups', 'options_literal' => true,
				'storage' => array('settings', 'quickpay', 'qp_itemGrp', 'raw'), 'legacy' => $divvalg,
				'keywords' => array('quickpay', 'varegruppe', 'item group')),

			'integrations.gls.id' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'gls', 'type' => 'text', 'label' => 865, 'help' => 866,
				'storage' => array('settings', 'GLS', 'gls_id', 'raw'), 'legacy' => $divvalg, 'keywords' => array('gls', 'gls id', 'pakkelabel', 'parcel label')),
			'integrations.gls.user' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'gls', 'type' => 'text', 'label' => 867, 'help' => 868,
				'storage' => array('settings', 'GLS', 'gls_user', 'raw'), 'legacy' => $divvalg, 'keywords' => array('gls', 'brugernavn', 'username')),
			'integrations.gls.contact_id' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'gls', 'type' => 'text', 'label' => 873, 'help' => 874,
				'storage' => array('settings', 'GLS', 'gls_ctId', 'raw'), 'legacy' => $divvalg, 'keywords' => array('gls', 'kontakt id', 'contact id')),
			'integrations.gls.password' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'gls', 'type' => 'secret', 'label' => 869, 'help' => 870,
				'storage' => array('settings', 'GLS', 'gls_pass', 'raw'), 'legacy' => $divvalg, 'keywords' => array('gls', 'adgangskode', 'password')),

			'integrations.dfm.agreement' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1020, 'help' => 1021,
				'storage' => array('settings', 'DFM', 'dfm_agree', 'raw'), 'legacy' => $divvalg, 'keywords' => array('danske fragtmænd', 'dfm', 'aftalenummer', 'agreement number')),
			'integrations.dfm.hub' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1022, 'help' => 1023,
				'storage' => array('settings', 'DFM', 'dfm_hub', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'hub')),
			'integrations.dfm.api_url' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 3129, 'help' => 3130,
				'storage' => array('settings', 'DFM', 'dfm_url', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'api url')),
			'integrations.dfm.client_id' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1014, 'help' => 1015,
				'storage' => array('settings', 'DFM', 'dfm_id', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'clientid', 'client id')),
			'integrations.dfm.user' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1016, 'help' => 1017,
				'storage' => array('settings', 'DFM', 'dfm_user', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'brugernavn', 'username')),
			'integrations.dfm.password' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'secret', 'label' => 1018, 'help' => 1019,
				'storage' => array('settings', 'DFM', 'dfm_pass', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'password', 'adgangskode')),
			'integrations.dfm.shipping_type' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1024, 'help' => 1025,
				'storage' => array('settings', 'DFM', 'dfm_ship', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'shippingtype', 'shipping type')),
			'integrations.dfm.goods_type' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1026, 'help' => 1027,
				'storage' => array('settings', 'DFM', 'dfm_good', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'godstype', 'goods type')),
			'integrations.dfm.payment' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1028, 'help' => 1029,
				'storage' => array('settings', 'DFM', 'dfm_pay', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'betalingsmetode', 'payment method')),
			'integrations.dfm.goods_description' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 6081, 'help' => 1039,
				'storage' => array('settings', 'DFM', 'dfm_gooddes', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'godsbeskrivelse', 'goods description')),
			'integrations.dfm.delivery' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'text', 'label' => 1058, 'help' => 1059,
				'storage' => array('settings', 'DFM', 'dfm_sercode', 'raw'), 'legacy' => $divvalg, 'keywords' => array('dfm', 'afleveringsmetode', 'delivery method')),
			'integrations.dfm.pickup' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'shipping', 'item' => 'dfm', 'type' => 'link', 'label' => 6082, 'help' => 6380, 'href' => 'settingsSection.php?s=integrations.pickup', 'button' => 6390, 'audit' => false,
				'legacy' => $divvalg, 'keywords' => array('afhentningsadresse', 'afhentningsadresser', 'pickup address', 'pick-up address')),

			'integrations.easyubl.api_key' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'einvoice', 'item' => 'easyubl', 'type' => 'secret', 'label' => 6086, 'help' => 6087,
				'storage' => array('settings', 'easyUBL', 'apiKey', 'raw'), 'locked_if' => 'ht_keys:easyUBLApiKey', 'locked_text' => 6088,
				'keywords' => array('easyubl', 'nemhandel', 'e-faktura', 'oioubl', 'api nøgle', 'api key')),
			'integrations.easyubl.company_id' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'einvoice', 'item' => 'easyubl', 'type' => 'text', 'label' => 6089, 'help' => 6090,
				'storage' => array('settings', 'easyUBL', 'companyID', 'raw'),
				'keywords' => array('easyubl', 'nemhandel', 'virksomheds id', 'company id')),
			'integrations.easyubl.nemhandel' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'einvoice', 'item' => 'easyubl', 'type' => 'info', 'label' => 6374, 'info' => 'nemhandel', 'audit' => false,
				'keywords' => array('nemhandel', 'nemhandelsregistret', 'peppol')),
			'integrations.app.api_key' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'einvoice', 'item' => 'app', 'type' => 'secret', 'label' => 6093, 'help' => 6094,
				'storage' => array('settings', 'app_api', 'apikey', 'raw'), 'locked_if' => 'ht_keys:aiApiKey', 'locked_text' => 6088,
				'keywords' => array('app', 'api nøgle', 'api key', 'bilagsgenkendelse')),
			'integrations.app.qr' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'einvoice', 'item' => 'app', 'type' => 'link', 'label' => 6095, 'help' => 6092, 'href' => 'barcodescan.php', 'button' => 6157, 'audit' => false,
				'keywords' => array('app', 'qr', 'qr-kode', 'barcode', 'app login', 'app barcode')),

			'integrations.mobilepay.client_id' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'text', 'label' => 6097, 'help' => 6098,
				'storage' => array('settings', 'mobilepay', 'client_id', 'raw'), 'legacy' => $divvalg, 'keywords' => array('mobilepay', 'vipps', 'client id')),
			'integrations.mobilepay.client_secret' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'secret', 'label' => 6099, 'help' => 6098,
				'storage' => array('settings', 'mobilepay', 'client_secret', 'raw'), 'legacy' => $divvalg, 'keywords' => array('mobilepay', 'vipps', 'client secret')),
			'integrations.mobilepay.subscription_key' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'secret', 'label' => 6100, 'help' => 6098,
				'storage' => array('settings', 'mobilepay', 'subscriptionKey', 'raw'), 'legacy' => $divvalg, 'keywords' => array('mobilepay', 'vipps', 'subscription key')),
			'integrations.mobilepay.msn' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'text', 'label' => 6101, 'help' => 6098,
				'storage' => array('settings', 'mobilepay', 'MSN', 'raw'), 'legacy' => $divvalg, 'keywords' => array('mobilepay', 'vipps', 'msn', 'merchant serial number')),
			'integrations.mobilepay.connect_webhook' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'action', 'label' => 6104, 'help' => 6030,
				'confirm_title' => 6105, 'confirm' => 6106, 'run' => 'mobilepay_webhook', 'blank' => true, 'visible_if' => array('setting_set', 'integrations.mobilepay.client_id'), 'legacy' => $divvalg,
				'keywords' => array('mobilepay', 'webhook')),
			'integrations.mobilepay.qr_codes' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'info', 'label' => 6163, 'help' => 6109, 'info' => 'mobilepay_qr', 'audit' => false,
				'visible_if' => array('setting_set', 'integrations.mobilepay.client_id'), 'keywords' => array('mobilepay', 'qr', 'qr-kode', 'kasse')),
			'integrations.mobilepay.create_qr' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'mobilepay', 'type' => 'action', 'label' => 6108, 'help' => 6111,
				'confirm_title' => 6110, 'confirm' => 6111, 'run' => 'mobilepay_qr', 'visible_if' => array('setting_set', 'integrations.mobilepay.client_id'), 'legacy' => $divvalg,
				'keywords' => array('mobilepay', 'qr', 'qr-koder')),

			'integrations.flatpay.login' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'flatpay', 'type' => 'mini', 'label' => 6113, 'help' => 6114, 'mini' => 'flatpay', 'audit' => false,
				'legacy' => $divvalg, 'keywords' => array('flatpay', 'flatpay id', 'kortterminal', 'card terminal')),
			'integrations.vibrant.api_key' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'vibrant', 'type' => 'secret', 'label' => 2317, 'help' => 2318,
				'storage' => array('settings', 'globals', 'vibrant_auth', 'raw'), 'legacy' => $divvalg, 'keywords' => array('vibrant', 'api nøgle', 'api key')),
			'integrations.vibrant.terminal_login' => array('group' => 'integrations', 'section' => 'connections', 'sub' => 'payments', 'item' => 'vibrant', 'type' => 'mini', 'label' => 6150, 'help' => 6151, 'mini' => 'vibrant', 'audit' => false,
				'legacy' => $divvalg, 'keywords' => array('vibrant', 'login', 'terminal', 'kortterminal')),

			// ---------------------------------------------------------------- Personal (topbar spec §4)
			'personal.orders.autocomplete' => array('group' => 'personal', 'section' => 'profile', 'sub' => 'profile', 'scope' => 'user',
				'type' => 'bool', 'label' => 5704, 'help' => 5705, 'default' => true, 'permission' => 'any',
				'storage' => array('settings', 'ordre', 'ordreAutocomplete', 'onEmpty'), 'legacy' => $ordre,
				'keywords' => array('autosøgning', 'autocomplete')),
			'personal.profile.bank_status' => array('group' => 'personal', 'section' => 'profile', 'sub' => 'profile', 'scope' => 'user',
				'type' => 'bool', 'label' => 6377, 'help' => 6378, 'default' => false, 'permission' => 'any',
				'storage' => array('settings', 'bank_integration', 'show_status', 'oneZero'), 'visible_if' => array('module', 'bank'),
				'keywords' => array('bank', 'bankstatus', 'kassekladde')),
			'personal.profile.text_size' => array('group' => 'personal', 'section' => 'profile', 'sub' => 'profile', 'scope' => 'user',
				'type' => 'select', 'label' => 2283, 'help' => 7031, 'default' => 'normal', 'permission' => 'any',
				'options' => array('normal' => 7029, 'large' => 7030),
				'storage' => array('settings', 'ui', 'text_size', 'raw'),
				'keywords' => array('tekststørrelse', 'text size', 'større tekst', 'skriftstørrelse')),
			'personal.profile.theme' => array('group' => 'personal', 'section' => 'profile', 'sub' => 'profile', 'scope' => 'user',
				'type' => 'select', 'label' => 1075, 'help' => 6919, 'default' => 'light', 'permission' => 'any',
				'options' => array('light' => 6917, 'dark' => 6918, 'system' => 2377),
				'storage' => array('settings', 'ui', 'theme', 'raw'),
				'keywords' => array('tema', 'theme', 'mørk tilstand', 'dark mode', 'lys', 'mørk', 'udseende')),
			'personal.print.local_print' => array('group' => 'personal', 'section' => 'print', 'sub' => 'print', 'scope' => 'user',
				'type' => 'bool', 'label' => 6007, 'help' => 6008, 'default' => false, 'permission' => 'any',
				'storage' => array('settings', 'print', 'localPrint', 'onEmpty'), 'legacy' => $divvalg,
				'keywords' => array('lokal printer', 'local printer')),
			// 4e: the autoExtract cookie becomes a user setting (settings redesign §7.4 item 5); the sidebar stays a cookie per device.
			'personal.documents.auto_extract' => array('group' => 'personal', 'section' => 'print', 'sub' => 'print', 'scope' => 'user',
				'type' => 'bool', 'label' => 3271, 'help' => 7018, 'default' => true, 'permission' => 'any',
				'storage' => array('settings', 'documents', 'auto_extract', 'oneZero'),
				'keywords' => array('auto-udtræk', 'fakturadata', 'ai scan', 'auto extract', 'dokumentpulje')),
		);

		$sprog = isset($GLOBALS['sprog_id']) ? (int) $GLOBALS['sprog_id'] : 1;
		// G10.1: interim and difference account per till for each currency the till accepts (VK box5/box6; box4 is the
		// "used in the till" flag of valuta.php, so the till account column of the old page is not offered).
		// G11.1: the chart of accounts is exported per fiscal year (deleted years have none); item locations only with warehouses.
		$q = db_select("select kodenr, beskrivelse from grupper where art = 'RA' and coalesce(box10, '') = '' order by " . db_cast('kodenr', 'integer') . " desc", __FILE__ . " linje " . __LINE__);
		$ioDefs = array();
		while ($r = db_fetch_array($q)) {
			$ioDefs['import_export.data.accounts_export_' . (int) $r['kodenr']] = array('sub' => 'accounts', 'type' => 'link', 'label' => 6649, 'label_suffix' => trim((string) $r['beskrivelse']),
				'href' => 'exporter_kontoplan.php?aar=' . (int) $r['kodenr'], 'button' => 1355, 'audit' => false);
		}
		$defs = $ioDefs + $defs;
		if (db_fetch_array(db_select("select id from grupper where art = 'LG' limit 1", __FILE__ . " linje " . __LINE__))) {
			$defs['import_export.data.locations_import'] = array('sub' => 'items', 'type' => 'link', 'label' => 6659, 'href' => 'importer_varelokationer.php', 'button' => 1356, 'audit' => false);
		}
		// G7.1: extra employee fields (defined in Sager › Ansatte; label = text 616+n, definition "type|option|option" in
		// grupper ANSAT kodenr 0, values per employee in ANSAT kodenr <employee id>, kode 0 for fields 1-14 and 1 for 15-28).
		foreach (settings_employee_extra_fields() as $n => $f) {
			$d = array('sub' => 'employees', 'type' => $f['type'], 'label' => 616 + $n, 'per' => 'employee', 'group_label' => array(6611, ''),
				'storage' => array('grupper', 'ANSAT', '0', 'box' . ($n <= 14 ? $n : $n - 14), 'raw', 'kode' => $n <= 14 ? '0' : '1', 'row_name' => 'Ekstra felter på ansatte stamkort'));
			if ($f['type'] === 'select') {
				$d['options'] = array('' => '') + array_combine($f['options'], $f['options']);
				$d['options_literal'] = true;
			}
			$defs['organisation.employees.extra_' . $n] = $d;
		}
		foreach (settings_pos_currencies() as $kodenr => $code) {
			$defs['pos.tills.currency_interim_' . $kodenr] = array('sub' => 'tills', 'type' => 'account', 'label' => 6280, 'help' => 6337, 'per' => 'till', 'group_label' => array(6336, $code),
				'storage' => array('grupper', 'VK', (string) $kodenr, 'box5', 'raw', 'row_name' => $code, 'join' => "\t", 'list' => true), 'legacy' => $pos);
			$defs['pos.tills.currency_difference_' . $kodenr] = array('sub' => 'tills', 'type' => 'account', 'label' => 6282, 'help' => 6338, 'per' => 'till', 'group_label' => array(6336, $code),
				'storage' => array('grupper', 'VK', (string) $kodenr, 'box6', 'raw', 'row_name' => $code, 'join' => "\t", 'list' => true), 'legacy' => $pos);
			// Decision 22 (2026-10-09): the till account per currency has its own key per till; VK box4 stays the "used in till" flag.
			$defs['pos.tills.currency_account_' . $kodenr] = array('sub' => 'tills', 'type' => 'account', 'label' => 7027, 'help' => 7028, 'per' => 'till', 'group_label' => array(6336, $code), 'scope' => 'pos',
				'storage' => array('settings', 'VK', 'till_account_' . $code, 'raw'), 'legacy' => $pos);
		}
		// G10.6: KDS colours by waiting time, one settings row color_<n> = "<minutes>-<#rrggbb>" per colour, plus one empty slot.
		foreach (settings_kds_colour_slots() as $i) {
			$defs['pos.kitchen.colour_after_' . $i] = array('sub' => 'colours', 'type' => 'int', 'label' => 6352, 'help' => 6353, 'group_label' => array(6354, $i), 'default' => '', 'empty_ok' => true,
				'storage' => array('settings', 'KDS', 'color_' . $i, 'raw', 'join' => '-', 'index' => 0, 'list' => true), 'legacy' => $pos, 'on_save' => 'kds_colours',
				'validate' => array('range', 0, 1440), 'visible_if' => array('setting', 'pos.kitchen.kds_active', true));
			$defs['pos.kitchen.colour_' . $i] = array('sub' => 'colours', 'type' => 'color', 'label' => 1786, 'group_label' => array(6354, $i), 'default' => '',
				'storage' => array('settings', 'KDS', 'color_' . $i, 'raw', 'join' => '-', 'index' => 1, 'list' => true), 'legacy' => $pos, 'on_save' => 'kds_colours',
				'visible_if' => array('setting', 'pos.kitchen.kds_active', true));
		}

		$groups = getSettingsGroups();
		$sections = getSettingsSections();
		$expanded = array();
		$order = array_keys($defs);
		foreach ($defs as $key => $def) {
			if (!isset($def['group'])) {
				$parts = explode('.', $key);
				$def['group'] = $parts[0];
				$def['section'] = $parts[1];
			}
			$def += array('scope' => 'company', 'audit' => true, 'visible_if' => null, 'validate' => null, 'keywords' => array(), 'on_save' => null);
			if (!isset($def['permission'])) {
				$sectionId = $def['group'] . '.' . $def['section'];
				if (isset($sections[$sectionId]['permission'])) {
					$def['permission'] = $sections[$sectionId]['permission'];
				} else {
					$def['permission'] = isset($groups[$def['group']]) ? $groups[$def['group']]['permission'] : 'system.indstillinger';
				}
			}
			if (isset($def['per']) && $def['per'] === 'language') {
				// One field per form language (settings.group_id): the languages are the company's own rows.
				foreach (settings_form_languages() as $langId => $langName) {
					if ($langId === 0 && isset($def['per_default_label'])) {
						$langName = findtekst((string) $def['per_default_label'], isset($GLOBALS['sprog_id']) ? (int) $GLOBALS['sprog_id'] : 1);
					}
					$expanded[$key . '.' . $langId] = array('scope' => 'group', 'scope_id' => (int) $langId, 'label_suffix' => $langName, 'per_key' => $key) + $def;
				}
				unset($defs[$key]);
				continue;
			}
			if (isset($def['per']) && ($def['per'] === 'till' || $def['per'] === 'till_last')) {
				// One field per till: the till's place in a tab-joined list, or its pos_id for per-till settings rows.
				$tills = settings_till_count();
				$from = ($def['per'] === 'till_last') ? $tills : 1;
				for ($n = $from; $n <= $tills; $n++) {
					$d = array('label_suffix' => sprintf(findtekst('6278|Kasse %s', isset($GLOBALS['sprog_id']) ? (int) $GLOBALS['sprog_id'] : 1), $n), 'per_key' => $key, 'item' => 'till_' . $n, 'scope_id' => $n) + $def;
					if (isset($d['storage']['join'])) {
						$d['storage']['index'] = $n - 1;
					}
					if ($key === 'pos.tills.post_each_sale') {
						$d['default'] = settings_post_each_sale_default();
					}
					$expanded[$key . '.' . $n] = settings_expand_visible_if($d, $def, $n);
				}
				unset($defs[$key]);
				continue;
			}
			if (isset($def['per']) && $def['per'] === 'card') {
				// One field per payment card: the card's place in the tab-joined lists (POS/1-3 and settings Paycards).
				$cards = settings_card_count();
				for ($n = 1; $n <= $cards; $n++) {
					$d = array('label_suffix' => sprintf(findtekst('6316|Kort %s', $sprog), $n), 'per_key' => $key, 'item' => 'card_' . $n, 'scope_id' => $n) + $def;
					if (isset($d['storage']['join'])) {
						$d['storage']['index'] = $n - 1;
					}
					$expanded[$key . '.' . $n] = settings_expand_visible_if($d, $def, $n);
				}
				unset($defs[$key]);
				continue;
			}
			if (isset($def['per']) && $def['per'] === 'table') {
				// One field per table name in POS/2 box7.
				$tables = settings_table_count();
				for ($n = 1; $n <= $tables; $n++) {
					$d = array('label_suffix' => sprintf(findtekst('6343|Bord %s', $sprog), $n), 'per_key' => $key, 'scope_id' => $n) + $def;
					if (isset($d['storage']['join'])) {
						$d['storage']['index'] = $n - 1;
					}
					$expanded[$key . '.' . $n] = $d;
				}
				unset($defs[$key]);
				continue;
			}
			if (isset($def['per']) && $def['per'] === 'pickup') {
				// One field per pickup address (settings DFM_Pickup, group_id = the address); the drawer is item pickup_<id>.
				foreach (settings_pickup_rows() as $gid => $name) {
					$d = array('scope' => 'group', 'scope_id' => (int) $gid, 'label_suffix' => $name, 'per_key' => $key, 'item' => 'pickup_' . $gid) + $def;
					if (isset($d['group_label']) && $d['group_label'][0] === 6389) {
						$d['group_label'] = array(6389, (string) $gid);
					}
					$expanded[$key . '.' . $gid] = $d;
				}
				unset($defs[$key]);
				continue;
			}
			if (isset($def['per']) && $def['per'] === 'floor_plan') {
				// One field per floor plan (table_pages row).
				foreach (settings_floor_plans() as $rowId => $rowName) {
					$expanded[$key . '.' . $rowId] = array('scope' => 'row', 'scope_id' => (int) $rowId, 'label_suffix' => sprintf(findtekst('6347|Bordplan %s', $sprog), $rowId), 'per_key' => $key) + $def;
				}
				unset($defs[$key]);
				continue;
			}
			if (isset($def['per']) && ($def['per'] === 'employee' || $def['per'] === 'employee_user')) {
				// One field per employee (ansatte row; extra fields in ANSAT rows keyed by the employee id); the drawer is item
				// emp_<id>. 'employee_user' fields belong to the employee's linked user and exist only when there is one.
				foreach (settings_employee_rows() as $eid => $emp) {
					$d = array('scope' => 'row', 'scope_id' => (int) $eid, 'label_suffix' => $emp['name'], 'per_key' => $key, 'item' => 'emp_' . $eid) + $def;
					if ($def['per'] === 'employee_user') {
						if ($emp['user'] <= 0) {
							continue;
						}
						$d['scope'] = 'user';
						$d['scope_id'] = (int) $emp['user'];
					}
					if (isset($d['storage'][0]) && $d['storage'][0] === 'grupper') {
						$d['storage'][2] = (string) (int) $eid;
					}
					$expanded[$key . '.' . $eid] = $d;
				}
				unset($defs[$key]);
				continue;
			}
			if (isset($def['per']) && $def['per'] === 'pricelist') {
				// One field per price list (grupper art PL); the drawer of the list is item pl_<id>.
				foreach (settings_pricelist_rows() as $rowId => $rowName) {
					$expanded[$key . '.' . $rowId] = array('scope' => 'row', 'scope_id' => (int) $rowId, 'label_suffix' => $rowName, 'per_key' => $key, 'item' => 'pl_' . $rowId) + $def;
				}
				unset($defs[$key]);
				continue;
			}
			$defs[$key] = $def;
		}
		if ($expanded) {
			// The expanded fields go where the first 'per' field of their card was, grouped by language, so the form
			// shows every field of one language together.
			$slots = array();
			foreach ($expanded as $key => $def) {
				$slot = $def['group'] . '.' . $def['section'] . '.' . $def['sub'];
				$slots[$slot][$def['scope_id']][$key] = $def;
			}
			$out = array();
			$placed = array();
			foreach ($order as $key) {
				if (isset($defs[$key])) {
					$out[$key] = $defs[$key];
					continue;
				}
				$def = null;
				foreach ($expanded as $k => $d) {
					if ($d['per_key'] === $key) {
						$def = $d;
						break;
					}
				}
				if ($def === null) {
					continue;
				}
				$slot = $def['group'] . '.' . $def['section'] . '.' . $def['sub'];
				if (isset($placed[$slot])) {
					continue;
				}
				$placed[$slot] = true;
				foreach ($slots[$slot] as $langDefs) {
					foreach ($langDefs as $k => $d) {
						$out[$k] = $d;
					}
				}
			}
			$defs = $out;
		}
		return $defs;
	}

	/**
	 * Appendix A of the settings redesign spec: where each old menu item lives now.
	 * 'old' is the old menu path (text ids), 'to' the new home: group, generated section
	 * (null until that section has landed) and the page that holds it today.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function getSettingsMovedMap(): array
	{
		$d = 782; // "Diverse"
		return array(
			array('old' => array(770), 'to' => array(array('finance', 'finance.vat', null))),
			array('old' => array(771), 'to' => array(array('sales', 'sales.debtor_groups', null), array('purchase', 'purchase.creditor_groups', null))),
			array('old' => array(772), 'to' => array(array('organisation', 'organisation.departments', null))),
			array('old' => array(773), 'to' => array(array('organisation', 'organisation.projects', null))),
			array('old' => array(608), 'to' => array(array('items', 'items.warehouses', null))),
			array('old' => array(774), 'to' => array(array('items', 'items.item_groups', null), array('sales', 'sales.discounts', null))),
			array('old' => array(775), 'to' => array(array('sales', 'sales.discounts', null))),
			array('old' => array(776), 'to' => array(array('finance', 'finance.currencies', null))),
			array('old' => array(778), 'to' => array(array('company', 'company.fiscal_years', null))),
			array('old' => array(779), 'to' => array(array('company', 'company.data', null), array('organisation', 'organisation.employees', null))),
			array('old' => array(780), 'to' => array(array('documents', null, 'formularkort.php?valg=formularer'))),
			array('old' => array(781), 'to' => array(array('items', 'items.units', null))),
			array('old' => array($d, 783), 'to' => array(array('company', 'company.account', null), array('company', 'company.localisation', null), array('company', null, 'diverse.php?sektion=kontoindstillinger'), array('documents', 'documents.email', null))),
			array('old' => array($d, 784), 'to' => array(array('organisation', 'organisation.commission', null))),
			array('old' => array($d, 786), 'to' => array(array('sales', 'sales.orders', null), array('sales', 'sales.debtor_card', null), array('purchase', 'purchase.orders', null), array('items', 'items.stock', null), array('personal', null, 'personalSettings.php'))),
			array('old' => array($d, 787), 'to' => array(array('items', 'items.stock', null), array('items', 'items.consignment', null), array('items', 'items.packaging', null))),
			array('old' => array($d, 788), 'to' => array(array('items', 'items.variants', null))),
			array('old' => array($d, 790), 'to' => array(array('integrations', 'integrations.connections', null))),
			array('old' => array($d, 791), 'to' => array(array('items', null, 'labels.php'))),
			array('old' => array($d, 792), 'to' => array(array('purchase', 'purchase.pricelists', null))),
			array('old' => array($d, 793), 'to' => array(array('sales', 'sales.reminders', null))),
			array('old' => array($d, 794), 'to' => array(array('sales', 'sales.debtor_card', null), array('finance', 'finance.cash_journal', null), array('sales', 'sales.mysale', null), array('documents', 'documents.print', null), array('company', null, 'diverse.php?sektion=div_valg'), array('integrations', 'integrations.connections', null))),
			array('old' => array($d, 796), 'to' => array(array('organisation', null, '../sager/tjeklister.php'))),
			array('old' => array($d, 797), 'to' => array(array('finance', 'finance.document_storage', null))),
			array('old' => array($d, 170), 'to' => array(array('finance', 'finance.cash_journal', null))),
			array('old' => array(2732), 'to' => array(array('finance', 'finance.cash_journal', null))),
			array('old' => array($d, 200), 'to' => array(array('sales', 'sales.orders', null))),
			array('old' => array($d, 271), 'to' => array(array('pos', 'pos.tills', null), array('pos', 'pos.cards', null), array('pos', 'pos.tables', null))),
			array('old' => array($d, 801), 'to' => array(array('personal', null, 'personalSettings.php'), array('company', null, 'diverse.php?sektion=sprog'))),
			array('old' => array($d, 802), 'to' => array(array('import_export', 'import_export.data', null))),
		);
	}

	/**
	 * A text in every language Saldi ships (the columns of importfiler/tekster.csv), lower case,
	 * for searching: people type the name they remember, whatever language the screen is in.
	 * Read from the file, so a search never writes rows for languages nobody uses.
	 */
	function settings_text_all_languages($id): string
	{
		static $texts = null;
		if ($texts === null) {
			$texts = array();
			$fp = @fopen(__DIR__ . '/../importfiler/tekster.csv', 'r');
			if ($fp) {
				fgets($fp);
				while (($line = fgets($fp)) !== false) {
					$cols = explode("\t", rtrim($line, "\r\n"));
					if (count($cols) > 1) {
						$texts[$cols[0]] = mb_strtolower(html_entity_decode(implode(' | ', array_slice($cols, 1)), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'UTF-8');
					}
				}
				fclose($fp);
			}
		}
		$id = (string) (int) $id;
		return isset($texts[$id]) ? $texts[$id] : '';
	}

	/**
	 * @param array<int, int> $path text ids
	 */
	function settings_legacy_all_languages(array $path): string
	{
		$out = '';
		foreach ($path as $id) {
			$out .= ' ' . settings_text_all_languages($id);
		}
		return $out;
	}

	function settings_section_url(string $sectionId, string $key = ''): string
	{
		// A query parameter, not #key: the shell keeps the page address in its own #.
		return 'settingsSection.php?s=' . rawurlencode($sectionId) . ($key !== '' ? '&field=' . rawurlencode($key) : '');
	}

	/**
	 * Old menu path as text, e.g. "Diverse → Ordrerelaterede valg".
	 *
	 * @param array<int, int> $path text ids
	 */
	function settings_legacy_text(array $path, int $sprogId): string
	{
		$parts = array();
		foreach ($path as $id) {
			$parts[] = findtekst((string) $id, $sprogId);
		}
		return implode(' → ', $parts);
	}

	/**
	 * The form languages of the company: 0 is the default (Danish and languages without their own row), then the
	 * VSPR rows the forms editor keeps. This is what includes/formFuncIncludes/sendMail.php looks the sender up by.
	 *
	 * @return array<int, string>
	 */
	/**
	 * The supplier price lists (grupper art PL): id => description.
	 *
	 * @return array<int, string>
	 */
	function settings_pricelist_rows(): array
	{
		static $rows = null;
		if ($rows === null || !empty($GLOBALS['settings_pricelists_changed'])) {
			$rows = array();
			unset($GLOBALS['settings_pricelists_changed']);
			$q = db_select("select id, beskrivelse from grupper where art = 'PL' order by beskrivelse, id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$rows[(int) $r['id']] = trim((string) $r['beskrivelse']);
			}
		}
		return $rows;
	}

	/**
	 * A 'visible_if' rule inside an expanded field follows the expanded parent of the same card / till.
	 */
	function settings_expand_visible_if(array $d, array $def, int $n): array
	{
		$prefix = $def['group'] . '.' . $def['section'] . '.';
		if (is_array($d['visible_if']) && isset($d['visible_if'][1]) && strpos((string) $d['visible_if'][1], $prefix) === 0) {
			$d['visible_if'][1] .= '.' . $n;
		}
		return $d;
	}

	/**
	 * The current fiscal year's POS row (kodenr 1-3), or null.
	 */
	function settings_pos_row(int $kodenr): ?array
	{
		global $regnaar;
		$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '" . (int) $kodenr . "' order by (coalesce(fiscal_year, 0) = " . (int) $regnaar . ") desc, id limit 1", __FILE__ . " linje " . __LINE__));
		return $r ? $r : null;
	}

	/**
	 * Number of payment cards: the names in POS/1 box5 of the current fiscal year.
	 */
	function settings_card_count(): int
	{
		static $n = null;
		if ($n === null || !empty($GLOBALS['settings_cards_changed'])) {
			unset($GLOBALS['settings_cards_changed']);
			$r = settings_pos_row(1);
			$n = ($r && trim((string) $r['box5']) !== '') ? count(explode("\t", (string) $r['box5'])) : 0;
		}
		return $n;
	}

	/**
	 * Number of tables: the names in POS/2 box7 of the current fiscal year.
	 */
	function settings_table_count(): int
	{
		$r = settings_pos_row(2);
		return ($r && trim((string) $r['box7']) !== '') ? count(explode("\t", (string) $r['box7'])) : 0;
	}

	/**
	 * Floor plans of the floor planner (table_pages): id => name.
	 */
	function settings_floor_plans(): array
	{
		$out = array();
		if (!db_fetch_array(db_select("select table_name from information_schema.tables where table_name = 'table_pages'" . saldi_schema_filter(), __FILE__ . " linje " . __LINE__))) {
			return $out;
		}
		$q = db_select("select id, name from table_pages order by id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$out[(int) $r['id']] = trim((string) $r['name']);
		}
		return $out;
	}

	/**
	 * Currencies the till accepts (grupper VK rows flagged in valuta.php): kodenr => code.
	 */
	function settings_pos_currencies(): array
	{
		static $out = null;
		if ($out === null) {
			$out = array();
			$q = db_select("select kodenr, box1 from grupper where art = 'VK' and box4 = '1' order by box1", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$out[(int) $r['kodenr']] = trim((string) $r['box1']);
			}
		}
		return $out;
	}

	/**
	 * Slot numbers of the KDS colours: one per stored colour plus an empty one to fill in.
	 */
	function settings_kds_colour_slots(): array
	{
		static $slots = null;
		if ($slots === null) {
			$r = db_fetch_array(db_select("select count(*) as n from settings where var_grp = 'KDS' and var_name like 'color%'", __FILE__ . " linje " . __LINE__));
			$slots = range(1, ($r ? (int) $r['n'] : 0) + 1);
		}
		return $slots;
	}

	/**
	 * Pickup addresses (settings DFM_Pickup): group_id => the name shown in the list.
	 */
	function settings_company_account_id(): int
	{
		static $id = null;
		if ($id === null) {
			$r = db_fetch_array(db_select("select id from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__));
			$id = $r ? (int) $r['id'] : 0;
		}
		return $id;
	}

	/**
	 * The company's employees in their list order (posnr, then number): id => array(name, number, user = linked brugere id).
	 *
	 * @return array<int, array{name: string, number: string, user: int}>
	 */
	function settings_employee_rows(bool $fresh = false): array
	{
		static $rows = null;
		if ($rows === null || $fresh) {
			$rows = array();
			$sid = settings_company_account_id();
			$q = db_select("select a.id, a.navn, a.nummer, (select min(b.id) from brugere b where b.ansat_id = a.id) as uid from ansatte a where a.konto_id = $sid order by coalesce(a.posnr, 999999), a.nummer, a.id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$rows[(int) $r['id']] = array('name' => trim((string) $r['navn']), 'number' => trim((string) $r['nummer']), 'user' => (int) $r['uid']);
			}
		}
		return $rows;
	}

	/**
	 * The extra employee fields in use: n => array(type, options). Only when switched on (Diverse valg, DIV/2 box3) and
	 * with a type - the label text ids are shared with other texts, so a label alone does not make a field.
	 *
	 * @return array<int, array{type: string, options: array<int, string>}>
	 */
	function settings_employee_extra_fields(): array
	{
		static $out = null;
		if ($out !== null) {
			return $out;
		}
		$out = array();
		if (!db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box3 = 'on'", __FILE__ . " linje " . __LINE__))) {
			return $out;
		}
		$defsRaw = array();
		$q = db_select("select kode, box1, box2, box3, box4, box5, box6, box7, box8, box9, box10, box11, box12, box13, box14 from grupper where art = 'ANSAT' and kodenr = '0'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			for ($b = 1; $b <= 14; $b++) {
				$defsRaw[(trim((string) $r['kode']) === '1' ? 14 : 0) + $b] = (string) $r['box' . $b];
			}
		}
		$types = array('text' => 'text', 'select' => 'select', 'checkbox' => 'bool', 'textarea' => 'textarea');
		for ($n = 1; $n <= 28; $n++) {
			if (!isset($defsRaw[$n]) || trim($defsRaw[$n]) === '') {
				continue;
			}
			$parts = explode('|', $defsRaw[$n]);
			$type = trim((string) array_shift($parts));
			$label = function_exists('findtekst') ? trim((string) findtekst((string) (616 + $n), isset($GLOBALS['sprog_id']) ? (int) $GLOBALS['sprog_id'] : 1)) : '';
			if (!isset($types[$type]) || $label === '' || $label === '-') {
				continue;
			}
			$options = array_values(array_filter(array_map('trim', $parts), 'strlen'));
			$out[$n] = array('type' => $types[$type], 'options' => $options);
		}
		return $out;
	}

	function settings_pickup_rows(): array
	{
		static $rows = null;
		if ($rows === null || !empty($GLOBALS['settings_pickups_changed'])) {
			unset($GLOBALS['settings_pickups_changed']);
			$rows = array();
			$names = array();
			$q = db_select("select group_id, var_name, var_value from settings where var_grp = 'DFM_Pickup' and var_name in ('dfm_pickup_name1', 'dfm_pickup_buttonname') order by group_id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$names[(int) $r['group_id']][(string) $r['var_name']] = trim((string) $r['var_value']);
			}
			foreach ($names as $gid => $n) {
				$rows[$gid] = !empty($n['dfm_pickup_name1']) ? $n['dfm_pickup_name1'] : (isset($n['dfm_pickup_buttonname']) ? $n['dfm_pickup_buttonname'] : '');
			}
		}
		return $rows;
	}

	/**
	 * Number of tills (POS/1 box1 of the current fiscal year).
	 */
	function settings_till_count(): int
	{
		static $n = null;
		if ($n === null || !empty($GLOBALS['settings_tills_changed'])) {
			unset($GLOBALS['settings_tills_changed']);
			global $regnaar;
			$r = db_fetch_array(db_select("select box1 from grupper where art = 'POS' and kodenr = '1' order by (coalesce(fiscal_year, 0) = " . (int) $regnaar . ") desc, id limit 1", __FILE__ . " linje " . __LINE__));
			$n = $r ? max(0, (int) $r['box1']) : 0;
		}
		return $n;
	}

	/**
	 * Until the postEachSale list exists the till uses the old 'post each sale' flag (POS/1 box9) for every till
	 * (includes/ordrefunc.php), so that flag is what the form shows.
	 */
	function settings_post_each_sale_default(): bool
	{
		static $on = null;
		if ($on === null) {
			global $regnaar;
			$r = db_fetch_array(db_select("select box9 from grupper where art = 'POS' and kodenr = '1' order by (coalesce(fiscal_year, 0) = " . (int) $regnaar . ") desc, id limit 1", __FILE__ . " linje " . __LINE__));
			$on = ($r && trim((string) $r['box9']) !== '');
		}
		return $on;
	}

	function settings_form_language_name(int $langId): string
	{
		$languages = settings_form_languages();
		return ($langId > 0 && isset($languages[$langId])) ? $languages[$langId] : 'Dansk';
	}

	function settings_form_languages(): array
	{
		static $languages = null;
		if ($languages === null) {
			$languages = array(0 => 'Dansk');
			$q = db_select("select kodenr, box1 from grupper where art = 'VSPR' order by kodenr", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if ((int) $r['kodenr'] > 0) {
					$languages[(int) $r['kodenr']] = trim((string) $r['box1']);
				}
			}
		}
		return $languages;
	}

	/**
	 * Definitions of one section, in registry order.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function settings_section_definitions(string $sectionId): array
	{
		$out = array();
		foreach (getSettingDefinitions() as $key => $def) {
			if ($def['group'] . '.' . $def['section'] === $sectionId) {
				$out[$key] = $def + array('key' => $key);
			}
		}
		return $out;
	}
}
