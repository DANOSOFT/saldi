<?php
// ---- includes/settings/hub.php --- ver 5.1.0 --- 2026.11.02 ---
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
// 20261101 CL/ASR Created: the settings hub "C - Fold ud" (the owner's choice of 1 Nov 2026, _prototypes/settings_hub/?v=c).
//                 Four groups (Virksomhed, Finans, Salg, Køb & lager), each with exactly four sub-groups of rows; one row per settings page.
//                 A row that covers two pages opens the first, and the left menu shows the second under it.
//                 This file is the one source for the hub, the left menu of the settings pages, the settings breadcrumbs and the "moved" map.
//                 Section ids, URLs, permission keys and stored data are unchanged: a row only points at sections and registry entries.
//                 Included by systemdata/settingsRegistry.php.
// 20261102 CL/ASR Tilbage is fixed, so it never loops: a section or other settings page goes to the hub (or the module page its gear came from);
//                 the hub and the personal settings go to the newest page outside the settings system in the navigation stack, else Oversigt.
//                 Old addresses that redirect (diverse.php, syssetup.php, anything with moved=) count as settings pages; Finans' kontoplan.php does not.
// 20261102 CL/ASR Review: Tilbage's tooltip names a section by the section itself (backup.php's showed "Regnskabsår", the last section seen).
// 20261102 CL/ASR The settings marker (owner, 2 Nov 2026: every page reached from Indstillinger keeps the settings menu): a hub row, a menu link or
//                 a moved-map link to a page of another module (Kontoplan, Booking) carries settings=1 (settings_hub_marked_url()).
//                 settings_opened_from_settings() tells such a page to show the settings layout (includes/settings/layout.php); without the
//                 marker it stays as on master. An address with the marker counts as a settings page for Tilbage.

if (!function_exists('getSettingsHub')):

/**
 * The hub (mock C): group id => label, description and four sub-groups; sub-group id => label, icon, description, rows.
 * Texts are 'id|Dansk' (tekster.csv) or array('da', 'en', 'no') written here (the row hints: the 47 of them did not fit the
 * text-id range). A row covers 'maps': section ids and registry keys, the first is its own id and the page it opens.
 * A registry key that points at a section of the same row is an alias (another name for that page, found by search);
 * any other key is a page of its own (shown under the row in the left menu).
 * Sub-group: 'status' = the rows whose badges make up its status; 'lean' = name/description when a row is hidden because
 * the company lacks a module; 'locked' = the row shown instead when every row needs a module the company lacks.
 *
 * @return array<string, array<string, mixed>>
 */
function getSettingsHub(): array
{
	$h = function (string $da, string $en, string $no): array {
		return array('da' => $da, 'en' => $en, 'no' => $no);
	};
	return array(
		'virksomhed' => array('label' => '5669|Virksomhed', 'description' => '7704|Firma, brugere og ansatte, fakturaformularer og e-mail, integrationer', 'subs' => array(
			'firma' => array('label' => '5649|Firma', 'icon' => 'bx-buildings', 'description' => '7718|Stamdata, sprog, abonnement og persondata', 'rows' => array(
				array('label' => '779|Stamdata', 'maps' => array('company.data', 'stamkort'),
					'hint' => $h('Navn, adresse, CVR, kontaktperson og bank', 'Name, address, VAT number, contact person and bank', 'Navn, adresse, org.nr., kontaktperson og bank')),
				array('label' => '7736|Sprog & lokalisering', 'maps' => array('company.localisation', 'sprog'),
					'hint' => $h('Sprog, tidszone, talformat og oversættelser', 'Language, time zone, number format and translations', 'Språk, tidssone, tallformat og oversettelser')),
				array('label' => '6630|Abonnement & konto', 'maps' => array('company.account', 'konto', 'kontoindstillinger'),
					'hint' => $h('Regnskabets navn, antal brugere, nulstil eller slet', 'Name of the accounts, number of users, reset or delete', 'Regnskapets navn, antall brukere, nullstill eller slett')),
				array('label' => '7737|Persondata (GDPR)', 'maps' => array('company.gdpr', 'gdpr'),
					'hint' => $h('Slet kunder og leverandører uden aktivitet i 3 år', 'Delete customers and suppliers with no activity for 3 years', 'Slett kunder og leverandører uten aktivitet på 3 år')),
			)),
			'brugere' => array('label' => '7708|Brugere & ansatte', 'icon' => 'bx-group', 'description' => '7719|Hvem kan logge ind, roller og medarbejdere',
				'status' => array('brugere', 'organisation.employees'), 'rows' => array(
				array('label' => '5536|Brugere & roller', 'maps' => array('brugere'),
					'hint' => $h('Hvem kan logge ind (også revisor og bogholder), roller og log', 'Who can log in (also auditor and bookkeeper), roles and log', 'Hvem kan logge inn (også revisor og regnskapsfører), roller og logg')),
				array('label' => '1262|Ansatte', 'maps' => array('organisation.employees', 'ansatte', 'div_valg'),
					'hint' => $h('Medarbejdere, initialer, kundeansvarlige og ekstra felter', 'Employees, initials, account managers and extra fields', 'Medarbeidere, initialer, kundeansvarlige og ekstra felt')),
			)),
			'formularer' => array('label' => '7709|Fakturaformularer & e-mail', 'icon' => 'bx-file', 'description' => '7720|Formularer, logo, udskrift og e-mail',
				'status' => array('documents.email'), 'rows' => array(
				array('label' => '780|Formularer', 'maps' => array('formularer'),
					'hint' => $h('Layout af faktura, tilbud, ordrebekræftelse, følgeseddel og rykker', 'Layout of invoice, quote, order confirmation, delivery note and reminder', 'Oppsett av faktura, tilbud, ordrebekreftelse, følgeseddel og purring')),
				array('label' => '7738|Logo & baggrunde', 'maps' => array('documents.backgrounds', 'baggrunde'),
					'hint' => $h('Brevpapir og logo bag fakturaen', 'Letterhead and logo behind the invoice', 'Brevpapir og logo bak fakturaen')),
				array('label' => '5993|Udskrift', 'maps' => array('documents.print', 'print'),
					'hint' => $h("Direkte print, og hvordan PDF'en dannes", 'Direct printing, and how the PDF is made', 'Direkte utskrift, og hvordan PDF-en lages')),
				array('label' => '6166|E-mail', 'maps' => array('documents.email', 'email_settings'),
					'hint' => $h('Afsender, mailtekster til fakturaer og mailserver', 'Sender, e-mail texts for invoices and mail server', 'Avsender, e-posttekster til fakturaer og e-postserver')),
			)),
			'integrationer' => array('label' => '7710|Integrationer & fragt', 'icon' => 'bx-plug', 'description' => '7721|Webshop, API, fragt, betaling og afhentning',
				'status' => array('integrations.connections'), 'rows' => array(
				array('label' => '5537|Integrationer', 'maps' => array('integrations.connections', 'api_valg', 'barcodescan', 'stripe_valg'),
					'hint' => $h('Webshop, API, fragt (GLS, DFM), e-faktura, MobilePay m.fl. og app', 'Webshop, API, shipping (GLS, DFM), e-invoicing, MobilePay and more, and the app', 'Nettbutikk, API, frakt (GLS, DFM), e-faktura, MobilePay m.fl. og app')),
				array('label' => '6379|Afhentningsadresser', 'maps' => array('integrations.pickup'),
					'hint' => $h('Hvor fragtfirmaet henter dine pakker', 'Where the carrier collects your parcels', 'Hvor fraktselskapet henter pakkene dine')),
			)),
		)),
		'finans' => array('label' => '600|Finans', 'description' => '7705|Kontoplan, regnskabsår, moms, kassekladde, bilag og sikkerhedskopi', 'subs' => array(
			'regnskab' => array('label' => '7711|Regnskab & moms', 'icon' => 'bx-coin-stack', 'description' => '7722|Kontoplan, regnskabsår, moms og valuta',
				'status' => array('company.fiscal_years'), 'rows' => array(
				array('label' => '612|Kontoplan', 'maps' => array('kontoplan'),
					'hint' => $h('Konti, sumkonti og standardkontoplan', 'Accounts, total accounts and the standard chart of accounts', 'Konti, sumkonti og standard kontoplan')),
				array('label' => '894|Regnskabsår', 'maps' => array('company.fiscal_years', 'regnskabsaar'),
					'hint' => $h('Opret, aktivér og luk regnskabsår', 'Create, activate and close financial years', 'Opprett, aktiver og lukk regnskapsår')),
				array('label' => '770|Moms', 'maps' => array('finance.vat', 'moms'),
					'hint' => $h('Momsregistrering, momskoder og momsrapport', 'VAT registration, VAT codes and VAT report', 'Mva-registrering, mva-koder og mva-rapport')),
				array('label' => '776|Valuta', 'maps' => array('finance.currencies', 'valuta'),
					'hint' => $h('Fremmed valuta og kurser', 'Foreign currencies and exchange rates', 'Fremmed valuta og kurser')),
			)),
			'bogfoering' => array('label' => '7712|Bogføring & bank', 'icon' => 'bx-book', 'description' => '7723|Kassekladde, betalinger, bilag og bank',
				'lean' => array('description' => '7734|Kassekladde, betalinger og bilag'), 'rows' => array(
				array('label' => '5983|Kassekladde & betalinger', 'maps' => array('finance.cash_journal', 'cash_journal'),
					'hint' => $h('Betalingslister, betalingsdato og øredifferencer', 'Payment lists, payment date and rounding differences', 'Betalingslister, betalingsdato og øreavrunding')),
				array('label' => '6205|Bilagsopbevaring', 'maps' => array('finance.document_storage', 'bilag'),
					'hint' => $h('Hvor bilag gemmes, egen FTP og visning', 'Where vouchers are stored, own FTP and viewing', 'Hvor bilag lagres, egen FTP og visning')),
				// The mock's "(vises når en bank er klar)" is left out: the row is only shown when a bank is ready.
				array('label' => '6141|Bankforbindelse', 'maps' => array('bank_integration'),
					'hint' => $h('Bankposter i kassekladden', 'Bank entries in the cash journal', 'Bankposter i kassekladden')),
			)),
			'afdelinger' => array('label' => '7713|Afdelinger & projekter', 'icon' => 'bx-sitemap', 'description' => '7724|Afdelinger og projektnumre til bogføring og rapporter', 'rows' => array(
				array('label' => '772|Afdelinger', 'maps' => array('organisation.departments', 'afdelinger'),
					'hint' => $h('Afdelinger til afdelingsregnskab og rapporter', 'Departments for departmental accounts and reports', 'Avdelinger til avdelingsregnskap og rapporter')),
				array('label' => '773|Projekter', 'maps' => array('organisation.projects', 'projekter'),
					'hint' => $h('Projektnumre til bogføring og rapporter', 'Project numbers for bookkeeping and reports', 'Prosjektnumre til bokføring og rapporter')),
			)),
			'data' => array('label' => '7714|Data & sikkerhedskopi', 'icon' => 'bx-transfer', 'description' => '7725|Import, eksport og sikkerhedskopi af regnskabet', 'rows' => array(
				array('label' => '5539|Import & eksport', 'maps' => array('import_export.data', 'div_io'),
					'hint' => $h('Kontoplan, kunder, leverandører, varer og formularer ind og ud', 'Chart of accounts, customers, suppliers, items and forms in and out', 'Kontoplan, kunder, leverandører, varer og skjemaer inn og ut')),
				array('label' => '6665|Sikkerhedskopi', 'maps' => array('import_export.backup', 'backup'),
					'hint' => $h('Tag eller indlæs en kopi af hele regnskabet', 'Take or restore a copy of all the accounts', 'Ta eller les inn en kopi av hele regnskapet')),
			)),
		)),
		'salg' => array('label' => '5544|Salg', 'description' => '7706|Debitorer, ordrer og priser, kasse, booking og Mit salg', 'subs' => array(
			'debitorer' => array('label' => '5985|Debitorer', 'icon' => 'bx-user', 'description' => '7726|Debitorkort, debitorgrupper, rykkere og inkasso', 'rows' => array(
				array('label' => '5681|Debitorkort', 'maps' => array('sales.debtor_card', 'debtor_card'),
					'hint' => $h('Felter og krav på kundekortet, jobkort', 'Fields and requirements on the customer card, job cards', 'Felt og krav på kundekortet, jobbkort')),
				array('label' => '1008|Debitorgrupper', 'maps' => array('sales.debtor_groups', 'debitor_grupper'),
					'hint' => $h('Samlekonto, moms, valuta og sprog pr. kundegruppe', 'Control account, VAT, currency and language per customer group', 'Samlekonto, mva, valuta og språk per kundegruppe')),
				array('label' => '7739|Rykkere & inkasso', 'maps' => array('sales.reminders', 'rykker_valg'),
					'hint' => $h('Rykkerfrister, gebyr, rente og inkasso', 'Reminder deadlines, fee, interest and debt collection', 'Purrefrister, gebyr, rente og inkasso')),
			)),
			'ordrer' => array('label' => '7715|Ordrer & priser', 'icon' => 'bx-receipt', 'description' => '7727|Fakturering, rabatter, prisgrupper og provision', 'rows' => array(
				array('label' => '5679|Ordrer & fakturering', 'maps' => array('sales.orders', 'ordre_valg'),
					'hint' => $h('Priser og moms, fakturering, massefakturering og følgeseddel', 'Prices and VAT, invoicing, batch invoicing and delivery note', 'Priser og mva, fakturering, massefakturering og følgeseddel')),
				array('label' => '6851|Rabatter & prisgrupper', 'maps' => array('sales.discounts', 'rabatgrupper'),
					'hint' => $h('Prisgrupper, tilbud, mængderabat og rabatmatrix', 'Price groups, offers, quantity discount and discount matrix', 'Prisgrupper, tilbud, mengderabatt og rabattmatrise')),
				array('label' => '657|Provision', 'maps' => array('organisation.commission', 'provision'),
					'hint' => $h('Grundlag for sælgerprovision', 'Basis for sales commission', 'Grunnlag for selgerprovisjon')),
			)),
			'kasse' => array('label' => '2226|Kasse', 'icon' => 'bx-store-alt', 'description' => '7728|Kasser, kort, kvittering, skærm og borde',
				'status' => array('pos.tills'), 'locked' => '5740|Kræver kasselicens — kontakt Saldi', 'rows' => array(
				array('label' => '7740|Kasser & kasseoptælling', 'maps' => array('pos.tills', 'pos_tills', 'pos.cash', 'pos_cash'),
					'hint' => $h('Kasser, kassekonti, optælling og rabatvare', 'Tills, till accounts, cash count and discount item', 'Kasser, kassekonti, opptelling og rabattvare')),
				array('label' => '6312|Betalingskort & terminaler', 'maps' => array('pos.cards', 'pos_cards'),
					'hint' => $h('Kortbetaling, gavekort og terminaler', 'Card payment, gift cards and terminals', 'Kortbetaling, gavekort og terminaler')),
				array('label' => '6253|Kvittering & print', 'maps' => array('pos.receipt', 'pos_receipt'),
					'hint' => $h('Bonens indhold og kvitteringsprinteren', 'What the receipt shows, and the receipt printer', 'Kvitteringens innhold og kvitteringsskriveren')),
				array('label' => '7741|Skærm & kassemenuer', 'maps' => array('pos.screen', 'pos_screen', 'posmenuer'),
					'hint' => $h('Knapper, ekspedition, visning og menuer', 'Buttons, checkout, display and menus', 'Knapper, ekspedisjon, visning og menyer')),
				array('label' => '7742|Borde & køkken', 'maps' => array('pos.tables', 'pos_tables', 'pos.kitchen', 'pos_kitchen'),
					'hint' => $h('Bordplaner, køkkenskærm og køkkenprint', 'Floor plans, kitchen screen and kitchen printing', 'Bordplaner, kjøkkenskjerm og kjøkkenutskrift')),
			)),
			'booking' => array('label' => '7716|Booking & Mit salg', 'icon' => 'bx-calendar', 'description' => '7729|Udlejning og portalen til kommissionskunder',
				'lean' => array('label' => '5986|Mit salg', 'description' => '7735|Portalen hvor kommissionskunder ser deres salg'), 'rows' => array(
				array('label' => '1116|Booking', 'maps' => array('rental_settings'),
					'hint' => $h('Bookingformat, ind- og udflytning og automatisk ordre', 'Booking format, move-in and move-out, and automatic order', 'Bookingformat, inn- og utflytting og automatisk ordre')),
				array('label' => '5986|Mit salg', 'maps' => array('sales.mysale', 'mysale'),
					'hint' => $h('Portal hvor kommissionskunder ser deres eget salg', 'Portal where consignment customers see their own sales', 'Portal der kommisjonskunder ser sitt eget salg')),
			)),
		)),
		'koeb_lager' => array('label' => '7703|Køb & lager', 'description' => '7707|Kreditorer, varer, lager og særlige varer', 'subs' => array(
			'kreditorer' => array('label' => '607|Kreditorer', 'icon' => 'bx-archive-in', 'description' => '7730|Kreditorgrupper, købsordrer og leverandørprislister', 'rows' => array(
				array('label' => '2458|Kreditorgrupper', 'maps' => array('purchase.creditor_groups', 'kreditorgrupper'),
					'hint' => $h('Samlekonto, moms og valuta pr. leverandørgruppe', 'Control account, VAT and currency per supplier group', 'Samlekonto, mva og valuta per leverandørgruppe')),
				array('label' => '5682|Købsordrer', 'maps' => array('purchase.orders', 'purchase_orders'),
					'hint' => $h('Bogføring af køb', 'Posting of purchases', 'Bokføring av innkjøp')),
				array('label' => '6219|Leverandørprislister', 'maps' => array('purchase.pricelists', 'pricelists'),
					'hint' => $h('Indlæs prislister fra leverandører', 'Import price lists from suppliers', 'Les inn prislister fra leverandører')),
			)),
			'varer' => array('label' => '609|Varer', 'icon' => 'bx-purchase-tag', 'description' => '7731|Varegrupper, varianter, enheder og mærkater', 'rows' => array(
				array('label' => '774|Varegrupper', 'maps' => array('items.item_groups', 'varegrupper'),
					'hint' => $h('Konti for køb, salg og lager, moms og prisgrupper', 'Accounts for purchases, sales and stock, VAT and price groups', 'Konti for innkjøp, salg og lager, mva og prisgrupper')),
				array('label' => '472|Varianter', 'maps' => array('items.variants', 'variant_valg'),
					'hint' => $h('Størrelser, farver og import af varianter', 'Sizes, colours and import of variants', 'Størrelser, farger og import av varianter')),
				array('label' => '6407|Enheder & materialer', 'maps' => array('items.units', 'enheder'),
					'hint' => $h('Stk., timer, kg og materialer', 'Pcs., hours, kg and materials', 'Stk., timer, kg og materialer')),
				array('label' => '791|Mærkater', 'maps' => array('labels'),
					'hint' => $h('Prisskilte og varelabels', 'Price tags and product labels', 'Prisskilt og vareetiketter')),
			)),
			'lager' => array('label' => '608|Lager', 'icon' => 'bx-package', 'description' => '7732|Lagerstyring, kostpris og lagre',
				'status' => array('items.warehouses', 'items.stock'), 'rows' => array(
				array('label' => '5680|Lagerstyring & kostpris', 'maps' => array('items.stock', 'stock_control'),
					'hint' => $h('Lagerføring, kostpris, lagerstatus-mail og varekort', 'Stock keeping, cost price, stock status mail and item card', 'Lagerføring, kostpris, lagerstatus-e-post og varekort')),
				array('label' => '6399|Lagre', 'maps' => array('items.warehouses', 'lagre'),
					'hint' => $h('Flere lagre og lagerlokationer', 'Several warehouses and stock locations', 'Flere lagre og lagerlokasjoner')),
			)),
			'saerlige' => array('label' => '7717|Særlige varer', 'icon' => 'bx-box', 'description' => '7733|Kommissionsvarer og emballage (producentansvar)',
				'lean' => array('description' => '5976|Emballage (producentansvar)'), 'rows' => array(
				array('label' => '5975|Kommissionsvarer', 'maps' => array('items.consignment', 'consignment'),
					'hint' => $h('Varer solgt i kommission og afregning', 'Items sold on consignment, and settlement', 'Varer solgt i kommisjon og oppgjør')),
				array('label' => '5976|Emballage (producentansvar)', 'maps' => array('items.packaging', 'packaging'),
					'hint' => $h('Emballagedata til producentansvar', 'Packaging data for producer responsibility', 'Emballasjedata for produsentansvar')),
			)),
		)),
	);
}

/**
 * Registry entries deliberately not on the hub (they are still found by search and listed in the "moved" map):
 * key => the reason shown in the moved map.
 *
 * @return array<string, string>
 */
function getSettingsHubLeftOut(): array
{
	return array(
		'userSettings'     => '5742|Personlig',
		'admin_settings'   => '7747|Ikke på forsiden – findes med søgning',
		'diverse_overview' => '7747|Ikke på forsiden – findes med søgning',
		'tjekliste'        => '7747|Ikke på forsiden – findes med søgning',
	);
}

/**
 * A hub text in the user's language and the page's charset, entities decoded (as st_txt): 'id|Dansk' through findtekst(),
 * or an inline array('da', 'en', 'no').
 *
 * @param string|int|array<string, string> $text
 */
function settings_hub_text($text, int $sprogId): string
{
	global $db_encode;
	$charset = (isset($db_encode) && $db_encode !== 'UTF8') ? 'ISO-8859-1' : 'UTF-8';
	if (is_array($text)) {
		$s = ($sprogId == 3 && isset($text['no'])) ? $text['no'] : (($sprogId != 1 && isset($text['en'])) ? $text['en'] : $text['da']);
		return $charset === 'UTF-8' ? $s : mb_convert_encoding($s, $charset, 'UTF-8');
	}
	return html_entity_decode(findtekst((string) $text, $sprogId), ENT_QUOTES | ENT_HTML5, $charset);
}

/**
 * The same text in every language Saldi ships, lower case and in the page's charset, for searching.
 *
 * @param string|int|array<string, string> $text
 */
function settings_hub_text_all($text): string
{
	global $db_encode;
	if (is_array($text)) {
		$s = mb_strtolower(implode(' | ', $text), 'UTF-8');
	} else {
		$s = function_exists('settings_text_all_languages') ? settings_text_all_languages((int) $text) : '';
		if ($s === '' && strpos((string) $text, '|') !== false) {
			$s = mb_strtolower(substr((string) $text, strpos((string) $text, '|') + 1), 'UTF-8');
		}
	}
	return (isset($db_encode) && $db_encode !== 'UTF8') ? mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8') : $s;
}

/**
 * A registry entry by its key.
 *
 * @return array<string, mixed>|null
 */
function settings_registry_entry(string $key): ?array
{
	static $byKey = null;
	if ($byKey === null) {
		$byKey = array();
		foreach (getSettingsRegistry() as $entry) {
			$byKey[(string) $entry['key']] = $entry;
		}
	}
	return isset($byKey[$key]) ? $byKey[$key] : null;
}

/**
 * The query parameter that marks a page of another module as opened from Indstillinger: settings=1 (owner, 2 Nov 2026).
 * Kontoplan is also Finans > Kontoplan and Booking's settings page belongs to Booking; opened from their own module they look
 * as on master, opened from the hub or the settings menu (whose links carry the marker) they show the settings layout.
 */
function settings_marker_param(): string
{
	return 'settings';
}

/**
 * Whether a url (relative to systemdata/, '../dir/file.php', or a path from the Saldi root) opens a page the breadcrumb map
 * (includes/stdFunc/pageRoutes.php) gives to a module other than the settings: systemdata/kontoplan.php and kontokort.php
 * (Finans), rental/settings.php (Booking), sager/tjeklister.php (Sager).
 */
function settings_hub_module_owned(string $url): bool
{
	$path = (string) parse_url($url, PHP_URL_PATH);
	if ($path === '' || preg_match('#^[a-z]+:#i', $url)) {
		return false;
	}
	if (strpos($path, '/') === 0) {
		$key = basename(dirname($path)) . '/' . basename($path);
	} elseif (strpos($path, '../') === 0) {
		$key = substr($path, 3);
	} else {
		$key = 'systemdata/' . $path;
	}
	$module = settings_hub_route_module($key);
	return !in_array($module, array('', 'system', 'settings'), true);
}

/**
 * A hub or menu url with the settings marker added when it opens another module's page (settings_hub_module_owned()).
 */
function settings_hub_marked_url(string $url): string
{
	if ($url === '' || !settings_hub_module_owned($url)) {
		return $url;
	}
	$query = array();
	parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
	if (isset($query[settings_marker_param()])) {
		return $url;
	}
	$hash = strpos($url, '#') !== false ? substr($url, strpos($url, '#')) : '';
	$base = $hash !== '' ? substr($url, 0, -strlen($hash)) : $url;
	return $base . (strpos($base, '?') !== false ? '&' : '?') . settings_marker_param() . '=1' . $hash;
}

/**
 * Whether the running page was opened from Indstillinger (the hub row, the settings menu, the moved map): the marker in
 * the request (GET or POST), or - for a form or link of the page itself that did not carry it on - in the address of the
 * same page the request came from. A module's own page (Kontoplan, Booking) asks this before it shows the settings layout;
 * without the marker it stays exactly as on master. Keep it on with settings_marker_query() / settings_marker_input().
 */
function settings_opened_from_settings(): bool
{
	$p = settings_marker_param();
	if ((isset($_GET[$p]) && $_GET[$p] === '1') || (isset($_POST[$p]) && $_POST[$p] === '1')) {
		return true;
	}
	$referer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
	$script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
	if ($referer === '' || $script === '') {
		return false;
	}
	$host = (string) parse_url($referer, PHP_URL_HOST);
	if ($host !== '' && isset($_SERVER['HTTP_HOST']) && strcasecmp($host, (string) preg_replace('/:\d+$/', '', (string) $_SERVER['HTTP_HOST'])) !== 0) {
		return false;
	}
	if ((string) parse_url($referer, PHP_URL_PATH) !== $script) {
		return false;
	}
	$query = array();
	parse_str((string) parse_url($referer, PHP_URL_QUERY), $query);
	return isset($query[$p]) && $query[$p] === '1';
}

/**
 * 'settings=1' when the running page was opened from Indstillinger, else '': for the page's own links (kontoplan.php?sort=x&...).
 */
function settings_marker_query(): string
{
	return settings_opened_from_settings() ? settings_marker_param() . '=1' : '';
}

/**
 * A hidden input with the marker when the running page was opened from Indstillinger, else '': for the page's own forms.
 */
function settings_marker_input(): string
{
	return settings_opened_from_settings() ? '<input type="hidden" name="' . settings_marker_param() . '" value="1">' : '';
}

/**
 * What a key of a row's 'maps' points at, without company or user checks: a section, or a registry entry. An entry whose
 * url opens a section ('section', or settingsSection.php?s=) is an alias of that section ('alias_of'). The url of a page
 * that belongs to another module carries the settings marker (settings_hub_marked_url()).
 *
 * @return array{key: string, kind: string, url: string, label: mixed, permission: string, module: string, entry: array<string, mixed>|null, alias_of: string}|null
 */
function settings_hub_page_def(string $key): ?array
{
	static $cache = array();
	if (array_key_exists($key, $cache)) {
		return $cache[$key];
	}
	$sections = getSettingsSections();
	$groups = getSettingsGroups();
	if (isset($sections[$key])) {
		$s = $sections[$key];
		return $cache[$key] = array(
			'key' => $key, 'kind' => 'section', 'url' => settings_section_url($key), 'label' => (string) $s['label'],
			'permission' => isset($s['permission']) ? (string) $s['permission'] : (isset($groups[$s['group']]) ? $groups[$s['group']]['permission'] : 'system.indstillinger'),
			'module' => !empty($s['module']) ? (string) $s['module'] : '', 'entry' => null, 'alias_of' => '',
		);
	}
	$entry = settings_registry_entry($key);
	if ($entry === null) {
		return $cache[$key] = null;
	}
	$alias = isset($entry['section']) ? (string) $entry['section'] : '';
	if ($alias === '' && basename((string) parse_url((string) $entry['url'], PHP_URL_PATH)) === 'settingsSection.php' && preg_match('/[?&]s=([A-Za-z0-9_.]+)/', (string) $entry['url'], $m)) {
		$alias = $m[1];
	}
	$group = isset($entry['group']) ? (string) $entry['group'] : '';
	return $cache[$key] = array(
		'key' => $key, 'kind' => 'entry', 'url' => settings_hub_marked_url((string) $entry['url']),
		'label' => isset($entry['textId']) ? (string) $entry['textId'] : array('da' => (string) $entry['labelDa'], 'en' => isset($entry['labelEn']) ? (string) $entry['labelEn'] : (string) $entry['labelDa'], 'no' => isset($entry['labelNo']) ? (string) $entry['labelNo'] : (isset($entry['labelEn']) ? (string) $entry['labelEn'] : (string) $entry['labelDa'])),
		'permission' => isset($entry['permission']) ? (string) $entry['permission'] : (isset($groups[$group]) ? $groups[$group]['permission'] : 'system.indstillinger'),
		'module' => '', 'entry' => $entry, 'alias_of' => $alias,
	);
}

/**
 * Whether a page exists in this company: the section's licence module, or the entry's visibility rule.
 */
function settings_hub_page_available(array $page): bool
{
	if ($page['kind'] === 'section') {
		return $page['module'] === '' || settings_has_module($page['module']);
	}
	return settings_entry_available($page['entry']);
}

/**
 * Whether the user may open a page: its own permission key (a section's or entry's own key, otherwise the key of the
 * permission group it belongs to - never the hub group it is shown in).
 */
function settings_hub_page_permitted(array $page): bool
{
	return !function_exists('perm_can') || perm_can($page['permission'], 'read');
}

/**
 * The pages of a row (aliases left out), its own first.
 *
 * @return array<string, array<string, mixed>>
 */
function settings_hub_row_pages(array $row): array
{
	$pages = array();
	$urls = array();
	foreach ($row['maps'] as $key) {
		$def = settings_hub_page_def((string) $key);
		if ($def === null || $def['alias_of'] !== '' || isset($urls[$def['url']])) {
			continue;
		}
		$urls[$def['url']] = true;
		$pages[(string) $key] = $def;
	}
	return $pages;
}

/**
 * A row in this company for this user: 'pages' that exist here, 'open' those the user may open, the 'url' and 'target'
 * (key) of the first open page, and whether any page exists here at all ('available').
 *
 * @return array{pages: array<string, array<string, mixed>>, open: array<string, array<string, mixed>>, url: string, target: string, available: bool}
 */
function settings_hub_row_state(array $row): array
{
	$pages = array();
	$open = array();
	foreach (settings_hub_row_pages($row) as $key => $page) {
		if (!settings_hub_page_available($page)) {
			continue;
		}
		$pages[$key] = $page;
		if (settings_hub_page_permitted($page)) {
			$open[$key] = $page;
		}
	}
	$first = $open ? reset($open) : null;
	return array('pages' => $pages, 'open' => $open, 'url' => $first ? $first['url'] : '', 'target' => $first ? $first['key'] : '', 'available' => (bool) $pages);
}

/**
 * Every key of every row => where it sits: group, sub, row (the row's id = its first key) and page (the page it opens:
 * the key itself, or for an alias the section it names).
 *
 * @return array<string, array{group: string, sub: string, row: string, page: string}>
 */
function settings_hub_index(): array
{
	static $index = null;
	if ($index !== null) {
		return $index;
	}
	$index = array();
	foreach (getSettingsHub() as $groupId => $group) {
		foreach ($group['subs'] as $subId => $sub) {
			foreach ($sub['rows'] as $row) {
				$rowId = (string) $row['maps'][0];
				foreach ($row['maps'] as $key) {
					$def = settings_hub_page_def((string) $key);
					$page = ($def !== null && $def['alias_of'] !== '' && in_array($def['alias_of'], $row['maps'], true)) ? $def['alias_of'] : (string) $key;
					if (!isset($index[(string) $key])) {
						$index[(string) $key] = array('group' => $groupId, 'sub' => $subId, 'row' => $rowId, 'page' => $page);
					}
				}
			}
		}
	}
	return $index;
}

/**
 * Where a section id or registry key sits on the hub, or null (a left-out entry, an unknown key).
 *
 * @return array{group: string, sub: string, row: string, page: string}|null
 */
function settings_hub_locate(string $key): ?array
{
	$index = settings_hub_index();
	return isset($index[$key]) ? $index[$key] : null;
}

/**
 * The row definition at a location.
 *
 * @param array{group: string, sub: string, row: string} $loc
 * @return array<string, mixed>|null
 */
function settings_hub_row(array $loc): ?array
{
	$hub = getSettingsHub();
	if (!isset($hub[$loc['group']]['subs'][$loc['sub']])) {
		return null;
	}
	foreach ($hub[$loc['group']]['subs'][$loc['sub']]['rows'] as $row) {
		if ((string) $row['maps'][0] === $loc['row']) {
			return $row;
		}
	}
	return null;
}

/**
 * Where the running page sits on the hub: settingsSection.php by its s=, any other page by the registry entry whose url
 * names this folder, file and query (most specific first). Null on the hub itself and on pages that are not on it.
 *
 * @return array{group: string, sub: string, row: string, page: string}|null
 */
function settings_hub_locate_request(): ?array
{
	$script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
	$file = basename($script);
	$dir = basename(dirname($script));
	if ($file === 'settingsSection.php') {
		return (isset($_GET['s']) && is_string($_GET['s'])) ? settings_hub_locate((string) $_GET['s']) : null;
	}
	$get = array_filter($_GET, 'is_string');
	if (!isset($get['valg']) && isset($_POST['valg']) && is_string($_POST['valg'])) {
		$get['valg'] = $_POST['valg'];
	}
	$best = null;
	$bestSpec = -1;
	foreach (getSettingsRegistry() as $entry) {
		$parts = parse_url((string) $entry['url']);
		$path = isset($parts['path']) ? (string) $parts['path'] : '';
		$entryDir = (strpos($path, '../') === 0) ? basename(dirname(substr($path, 3))) : 'systemdata';
		if (basename($path) !== $file || $entryDir !== $dir) {
			continue;
		}
		$params = array();
		if (isset($parts['query'])) {
			parse_str($parts['query'], $params);
		}
		$ok = true;
		foreach ($params as $name => $value) {
			if (!isset($get[$name]) || $get[$name] !== (string) $value) {
				$ok = false;
				break;
			}
		}
		if ($ok && count($params) > $bestSpec) {
			$loc = settings_hub_locate((string) $entry['key']);
			if ($loc !== null) {
				$best = $loc;
				$bestSpec = count($params);
			}
		}
	}
	return $best;
}

/**
 * The label of a page (section or entry) in the user's language.
 */
function settings_hub_page_label(array $page, int $sprogId): string
{
	if ($page['kind'] === 'entry') {
		return settings_hub_text($page['label'], $sprogId);
	}
	return settings_hub_text((string) $page['label'], $sprogId);
}

/**
 * The name a page had before the settings hub: the entry's 'renamedFrom', or the 'renamed_from' of a renamed section
 * (also for an entry that opens it); null when it kept its name.
 *
 * @return string|array<string, string>|null
 */
function settings_hub_renamed_from(array $page)
{
	$sections = getSettingsSections();
	if ($page['kind'] === 'entry') {
		if (isset($page['entry']['renamedFrom'])) {
			return $page['entry']['renamedFrom'];
		}
		$sec = $page['alias_of'];
		return ($sec !== '' && isset($sections[$sec]['renamed_from']) && (string) $page['label'] === (string) $sections[$sec]['label']) ? (string) $sections[$sec]['renamed_from'] : null;
	}
	return isset($sections[$page['key']]['renamed_from']) ? (string) $sections[$page['key']]['renamed_from'] : null;
}

/**
 * The old names a row is found by (current language, for "tidligere ..."), and every language's version of its texts
 * for searching ('terms'). Only names of pages this company has.
 *
 * @return array{old: array<int, string>, terms: string}
 */
function settings_hub_row_names(array $row, int $sprogId): array
{
	$sections = getSettingsSections();
	$rowLabel = settings_hub_text($row['label'], $sprogId);
	$old = array();
	$terms = array(settings_hub_text_all($row['label']), settings_hub_text_all($row['hint']));
	$add = function (string $name, string $all) use (&$old, &$terms, $rowLabel) {
		$name = trim((string) preg_replace('/\s+/u', ' ', $name));
		if ($name !== '' && mb_strtolower($name) !== mb_strtolower($rowLabel) && !in_array($name, $old, true)) {
			$old[] = $name;
		}
		if ($all !== '') {
			$terms[] = $all;
		}
	};
	foreach ($row['maps'] as $key) {
		$def = settings_hub_page_def((string) $key);
		if ($def === null || !settings_hub_page_available($def)) {
			continue;
		}
		if ($def['kind'] === 'section') {
			$s = $sections[(string) $key];
			$add(settings_hub_text((string) $s['label'], $sprogId), settings_hub_text_all((string) $s['label']));
			if (!empty($s['renamed_from'])) {
				$add(settings_hub_text((string) $s['renamed_from'], $sprogId), settings_hub_text_all((string) $s['renamed_from']));
			}
			foreach (isset($s['legacy']) ? $s['legacy'] : array() as $path) {
				$add(settings_legacy_text($path, $sprogId), trim(settings_legacy_all_languages($path)));
			}
		} else {
			$add(settings_hub_page_label($def, $sprogId), settings_hub_text_all($def['label']));
			if (isset($def['entry']['renamedFrom'])) {
				$add(settings_hub_text($def['entry']['renamedFrom'], $sprogId), settings_hub_text_all($def['entry']['renamedFrom']));
			}
		}
	}
	// "Diverse → Kontoindstillinger" and "Kontoindstillinger" both: the short name is enough.
	$old = array_values(array_filter($old, function ($name) use ($old) {
		$leaf = trim((string) preg_replace('/^.*→/u', '', $name));
		return $leaf === $name || !in_array($leaf, $old, true);
	}));
	return array('old' => $old, 'terms' => implode(' | ', array_filter($terms, 'strlen')));
}

/**
 * Count and status per row (settings redesign §8.0, moved from the old group rows to the row they are about):
 * row id => ['kind' => ''|'ok'|'warn'|'err', 'text' => ...]. Only rows in $rowIds are measured.
 *
 * @param array<int, string> $rowIds
 * @return array<string, array{kind: string, text: string}>
 */
function settings_hub_badges(array $rowIds, int $sprogId): array
{
	global $regnaar;
	$want = array_flip($rowIds);
	$out = array();
	$count = function (string $qtxt): int {
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		return $r ? (int) $r['antal'] : 0;
	};
	$one = function (int $n, array $singular, string $plural) use ($sprogId): string {
		return $n === 1 ? settings_hub_text($singular, $sprogId) : sprintf(settings_hub_text($plural, $sprogId), $n);
	};
	if (isset($want['company.fiscal_years']) && (int) $regnaar > 0) {
		$r = db_fetch_array(db_select("select beskrivelse from grupper where art = 'RA' and kodenr = '" . (int) $regnaar . "'", __FILE__ . " linje " . __LINE__));
		if ($r && trim((string) $r['beskrivelse']) !== '') {
			$out['company.fiscal_years'] = array('kind' => '', 'text' => sprintf(settings_hub_text('6037|%s aktivt', $sprogId), trim((string) $r['beskrivelse'])));
		}
	}
	if (isset($want['finance.vat']) && (int) $regnaar > 0) {
		$n = $count("select count(*) as antal from grupper where art in ('SM', 'KM', 'EM', 'YM') and fiscal_year = '" . (int) $regnaar . "'");
		if ($n > 0) {
			$out['finance.vat'] = array('kind' => '', 'text' => sprintf(settings_hub_text('6033|%s momskoder', $sprogId), $n));
		}
	}
	if (isset($want['organisation.employees'])) {
		$n = $count("select count(*) as antal from ansatte where konto_id in (select id from adresser where art = 'S') and (lukket is null or lukket <> 'on')");
		if ($n > 0) {
			$out['organisation.employees'] = array('kind' => '', 'text' => $one($n, array('da' => '1 ansat', 'en' => '1 employee', 'no' => '1 ansatt'), '6034|%s ansatte'));
		}
	}
	if (isset($want['brugere'])) {
		$n = 0;
		$q = db_select("select status from brugere", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['status'], array('f', false, '0', 0), true)) {
				$n++;
			}
		}
		$out['brugere'] = array('kind' => '', 'text' => $one($n, array('da' => '1 bruger', 'en' => '1 user', 'no' => '1 bruker'), '6036|%s brugere'));
	}
	foreach (array('sales.debtor_groups' => 'G3.1', 'purchase.creditor_groups' => 'G4.1', 'items.item_groups' => 'G5.1') as $rowId => $section) {
		if (isset($want[$rowId]) && !settings_section_complete($section)) {
			$out[$rowId] = array('kind' => 'warn', 'text' => settings_hub_text('7096|Grundopsætning mangler', $sprogId));
		}
	}
	if (isset($want['items.warehouses'])) {
		$n = $count("select count(*) as antal from grupper where art = 'LG'");
		if ($n > 0) {
			$out['items.warehouses'] = array('kind' => '', 'text' => $one($n, array('da' => '1 lager', 'en' => '1 warehouse', 'no' => '1 lager'), '6035|%s lagre'));
		}
	}
	if (isset($want['items.stock'])) {
		$fifo = db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '3'", __FILE__ . " linje " . __LINE__));
		if ($fifo && trim((string) $fifo['box6']) === 'on') {
			$out['items.stock'] = array('kind' => '', 'text' => 'FIFO');
		}
	}
	if (isset($want['pos.tills']) && (int) $regnaar > 0 && settings_has_module('pos')) {
		$r = db_fetch_array(db_select("select box1 from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '" . (int) $regnaar . "'", __FILE__ . " linje " . __LINE__));
		$tills = $r ? (int) $r['box1'] : 0;
		if ($tills > 0) {
			$out['pos.tills'] = array('kind' => '', 'text' => ($tills === 1) ? settings_hub_text('5982|1 kasse', $sprogId) : sprintf(settings_hub_text('5978|%s kasser', $sprogId), $tills));
		}
	}
	if (isset($want['integrations.connections'])) {
		$mp = array();
		$q = db_select("select var_name, var_value from settings where var_grp = 'mobilepay' and var_name in ('client_id', 'webhook_secret')", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$mp[$r['var_name']] = trim((string) $r['var_value']);
		}
		if (!empty($mp['client_id']) && empty($mp['webhook_secret'])) {
			$out['integrations.connections'] = array('kind' => 'err', 'text' => sprintf(settings_hub_text('6038|%s fejl', $sprogId), 1));
		} else {
			$active = 0;
			foreach (settings_optional_modules() as $m) {
				if ($m['group'] === 'integrations' && $m['active']) {
					$active++;
				}
			}
			if ($active > 0) {
				$out['integrations.connections'] = array('kind' => '', 'text' => $active === 1 ? settings_hub_text(array('da' => '1 aktiv', 'en' => '1 active', 'no' => '1 aktiv'), $sprogId) : sprintf(settings_hub_text('5980|%s aktive', $sprogId), $active));
			}
		}
	}
	if (isset($want['documents.email']) && !settings_section_complete('G6.3')) {
		$out['documents.email'] = array('kind' => 'warn', 'text' => settings_hub_text('6027|Mangler afsender', $sprogId));
	}
	return $out;
}

/**
 * A sub-group's status: a warning or error on any of its rows first, otherwise the badges of its 'status' rows joined.
 *
 * @param array<string, array{kind: string, text: string}> $badges of the sub-group's visible rows
 * @return array{kind: string, text: string}|null
 */
function settings_hub_sub_status(array $sub, array $badges): ?array
{
	foreach (array('err', 'warn') as $kind) {
		foreach ($badges as $badge) {
			if ($badge['kind'] === $kind) {
				return $badge;
			}
		}
	}
	$texts = array();
	foreach (isset($sub['status']) ? $sub['status'] : array() as $rowId) {
		if (isset($badges[$rowId]) && $badges[$rowId]['text'] !== '') {
			$texts[] = $badges[$rowId]['text'];
		}
	}
	return $texts ? array('kind' => '', 'text' => implode(' · ', $texts)) : null;
}

/**
 * Everything the hub page shows (mock C), for the current company and user, texts in the user's language and the page's
 * charset (not HTML-escaped). Groups with nothing to show are left out; a sub-group whose rows all need a module the
 * company lacks is 'locked' (when the user holds a permission for it), otherwise left out.
 *
 * group: id, label, description, subs[]
 * sub:   id, label, description, icon, locked ('' or the text of the locked row), status (null or kind/text), terms, rows[]
 * row:   id, label, hint, url (first page the user may open, relative to systemdata/), target (its key),
 *        pages[] (id, label, url: every page of the row the user may open, the target first), badge (null or kind/text),
 *        attention (null or kind/title/sub/button/url), old[] (names it was known by), terms (all languages, lower case)
 *
 * @return array{groups: array<int, array<string, mixed>>, leftOut: array<int, array<string, string>>}
 */
function settings_hub_model(int $sprogId, bool $withStatus = true): array
{
	$groups = array();
	$visibleRows = array();
	foreach (getSettingsHub() as $groupId => $group) {
		$subs = array();
		foreach ($group['subs'] as $subId => $sub) {
			$rows = array();
			$missing = false;
			$lockedPerm = false;
			foreach ($sub['rows'] as $row) {
				$state = settings_hub_row_state($row);
				if (!$state['available']) {
					$missing = true;
					foreach (settings_hub_row_pages($row) as $page) {
						$lockedPerm = $lockedPerm || settings_hub_page_permitted($page);
					}
					continue;
				}
				if (!$state['open']) {
					continue;
				}
				$names = settings_hub_row_names($row, $sprogId);
				$pages = array();
				foreach ($state['open'] as $key => $page) {
					$pages[] = array('id' => $key, 'label' => settings_hub_page_label($page, $sprogId), 'url' => $page['url']);
				}
				$rowId = (string) $row['maps'][0];
				$rows[] = array(
					'id' => $rowId, 'label' => settings_hub_text($row['label'], $sprogId), 'hint' => settings_hub_text($row['hint'], $sprogId),
					'url' => $state['url'], 'target' => $state['target'], 'pages' => $pages, 'badge' => null, 'attention' => null,
					'old' => $names['old'], 'terms' => $names['terms'],
				);
				$visibleRows[] = $rowId;
			}
			$lean = ($missing && !empty($sub['lean'])) ? $sub['lean'] : array();
			$label = settings_hub_text(isset($lean['label']) ? $lean['label'] : $sub['label'], $sprogId);
			$description = settings_hub_text(isset($lean['description']) ? $lean['description'] : $sub['description'], $sprogId);
			$locked = '';
			if (!$rows) {
				if (empty($sub['locked']) || !$lockedPerm) {
					continue;
				}
				$locked = settings_hub_text($sub['locked'], $sprogId);
			}
			$subs[] = array(
				'id' => $subId, 'label' => $label, 'description' => $description, 'icon' => (string) $sub['icon'], 'locked' => $locked, 'status' => null,
				'terms' => settings_hub_text_all(isset($lean['label']) ? $lean['label'] : $sub['label']) . ' | ' . settings_hub_text_all(isset($lean['description']) ? $lean['description'] : $sub['description']),
				'rows' => $rows, '_def' => $sub,
			);
		}
		if ($subs) {
			$groups[] = array('id' => $groupId, 'label' => settings_hub_text($group['label'], $sprogId), 'description' => settings_hub_text($group['description'], $sprogId), 'subs' => $subs);
		}
	}
	if ($withStatus) {
		$badges = settings_hub_badges($visibleRows, $sprogId);
		$attention = settings_attention(settings_accessible_groups(), $sprogId);
		$attentionRow = array('integrations' => 'integrations.connections', 'documents' => 'documents.email', 'users' => 'brugere');
		foreach ($groups as $g => $group) {
			foreach ($group['subs'] as $s => $sub) {
				$subBadges = array();
				foreach ($sub['rows'] as $r => $row) {
					if (isset($badges[$row['id']])) {
						$groups[$g]['subs'][$s]['rows'][$r]['badge'] = $badges[$row['id']];
						$subBadges[$row['id']] = $badges[$row['id']];
					}
					foreach ($attentionRow as $attGroup => $attRow) {
						if ($attRow === $row['id'] && isset($attention[$attGroup])) {
							$groups[$g]['subs'][$s]['rows'][$r]['attention'] = $attention[$attGroup];
						}
					}
				}
				$groups[$g]['subs'][$s]['status'] = settings_hub_sub_status($sub['_def'], $subBadges);
			}
		}
	}
	foreach ($groups as $g => $group) {
		foreach ($group['subs'] as $s => $sub) {
			unset($groups[$g]['subs'][$s]['_def']);
		}
	}
	return array('groups' => $groups, 'leftOut' => settings_hub_left_out($sprogId));
}

/**
 * The left-out entries this user may open, for search on the hub: key, label, url, reason, terms.
 *
 * @return array<int, array<string, string>>
 */
function settings_hub_left_out(int $sprogId): array
{
	$out = array();
	foreach (getSettingsHubLeftOut() as $key => $reason) {
		$def = settings_hub_page_def($key);
		if ($def === null || !settings_hub_page_available($def)) {
			continue;
		}
		$personal = isset($def['entry']['group']) && $def['entry']['group'] === 'personal';
		if (!$personal && !settings_hub_page_permitted($def)) {
			continue;
		}
		$terms = settings_hub_text_all($def['label']);
		foreach (isset($def['entry']['labelDa']) ? array('labelDa', 'labelEn', 'labelNo') : array() as $l) {
			if (!empty($def['entry'][$l])) {
				$terms .= ' | ' . mb_strtolower((string) $def['entry'][$l]);
			}
		}
		$out[] = array('key' => $key, 'label' => settings_hub_page_label($def, $sprogId), 'url' => $def['url'], 'reason' => settings_hub_text($reason, $sprogId), 'terms' => $terms);
	}
	return $out;
}

/**
 * The left menu of a settings page (mock C): the page's hub group as its four sub-groups with their rows, the current
 * row marked. $current is a section id or registry key; '' finds the running page (settingsSection.php?s=, or the
 * registry entry whose url is this page). Rows the user cannot open are left out, except the current one. A row with
 * more than one page lists them in 'pages' (the current page marked), so the left menu can show the second page under it.
 * Empty array when the page is not on the hub.
 *
 * group:   id, label, description, url (settings.php?g=<id>)
 * current: group, sub, row, page
 * subs[]:  id, label, icon, locked ('' or text), current (bool), rows[]
 * rows[]:  id, label, hint, url, current (bool), pages[] (id, label, url, current) - only when the row has two or more pages
 *
 * @return array<string, mixed>
 */
function settings_hub_menu(int $sprogId, string $current = ''): array
{
	$loc = $current !== '' ? settings_hub_locate($current) : settings_hub_locate_request();
	if ($loc === null) {
		return array();
	}
	$hub = getSettingsHub();
	$group = $hub[$loc['group']];
	$subs = array();
	foreach ($group['subs'] as $subId => $sub) {
		$rows = array();
		$missing = false;
		$lockedPerm = false;
		foreach ($sub['rows'] as $row) {
			$rowId = (string) $row['maps'][0];
			$isCurrent = ($rowId === $loc['row']);
			$state = settings_hub_row_state($row);
			if (!$state['available'] && !$isCurrent) {
				$missing = true;
				foreach (settings_hub_row_pages($row) as $page) {
					$lockedPerm = $lockedPerm || settings_hub_page_permitted($page);
				}
				continue;
			}
			$open = $state['open'];
			if ($isCurrent && !isset($open[$loc['page']]) && isset($state['pages'][$loc['page']])) {
				$open[$loc['page']] = $state['pages'][$loc['page']];
			}
			if (!$open) {
				continue;
			}
			$pages = array();
			if (count($open) > 1) {
				foreach ($open as $key => $page) {
					$pages[] = array('id' => $key, 'label' => settings_hub_page_label($page, $sprogId), 'url' => $page['url'], 'current' => $isCurrent && $key === $loc['page']);
				}
			}
			$first = reset($open);
			$rows[] = array(
				'id' => $rowId, 'label' => settings_hub_text($row['label'], $sprogId), 'hint' => settings_hub_text($row['hint'], $sprogId),
				'url' => $first['url'], 'current' => $isCurrent, 'pages' => $pages,
			);
		}
		$lean = ($missing && !empty($sub['lean'])) ? $sub['lean'] : array();
		$locked = '';
		if (!$rows) {
			if (empty($sub['locked']) || !$lockedPerm) {
				continue;
			}
			$locked = settings_hub_text($sub['locked'], $sprogId);
		}
		$subs[] = array(
			'id' => $subId, 'label' => settings_hub_text(isset($lean['label']) ? $lean['label'] : $sub['label'], $sprogId), 'icon' => (string) $sub['icon'],
			'locked' => $locked, 'current' => ($subId === $loc['sub']), 'rows' => $rows,
		);
	}
	return array(
		'group' => array('id' => $loc['group'], 'label' => settings_hub_text($group['label'], $sprogId), 'description' => settings_hub_text($group['description'], $sprogId), 'url' => 'settings.php?g=' . rawurlencode($loc['group'])),
		'current' => $loc,
		'subs' => $subs,
	);
}

/**
 * The hint of the row a section belongs to (the section's one-line description where it has no lead of its own).
 */
function settings_hub_hint_for(string $key, int $sprogId): string
{
	$loc = settings_hub_locate($key);
	$row = $loc !== null ? settings_hub_row($loc) : null;
	return $row !== null ? settings_hub_text($row['hint'], $sprogId) : '';
}

/**
 * A hub location as text, "Finans › Afdelinger & projekter › Afdelinger".
 */
function settings_hub_path(array $loc, int $sprogId): string
{
	$hub = getSettingsHub();
	$row = settings_hub_row($loc);
	if ($row === null) {
		return '';
	}
	return settings_hub_text($hub[$loc['group']]['label'], $sprogId) . ' › ' . settings_hub_text($hub[$loc['group']]['subs'][$loc['sub']]['label'], $sprogId) . ' › ' . settings_hub_text($row['label'], $sprogId);
}

/**
 * Where a section id or registry key sits, as the group path search results show ("Finans › Afdelinger & projekter");
 * '' when it is not on the hub.
 */
function settings_hub_group_path(string $key, int $sprogId): string
{
	$loc = settings_hub_locate($key);
	if ($loc === null) {
		return '';
	}
	$hub = getSettingsHub();
	return settings_hub_text($hub[$loc['group']]['label'], $sprogId) . ' › ' . settings_hub_text($hub[$loc['group']]['subs'][$loc['sub']]['label'], $sprogId);
}

/**
 * "Hvor er de gamle menupunkter?": every old menu item - the classic menu (getSettingsMovedMap) and the entries of the
 * first new settings (old group → name) that moved or were renamed - with where it is now.
 * Row: old (text), search (all languages, lower case), to[] (path, url - '' when the user cannot open it or the company
 * lacks it -, tag: '' | personal | out).
 *
 * @return array<int, array<string, mixed>>
 */
function settings_hub_moved_rows(int $sprogId): array
{
	$groups = getSettingsGroups();
	$rows = array();
	$target = function (string $key) use ($sprogId): ?array {
		$loc = settings_hub_locate($key);
		if ($loc === null) {
			return null;
		}
		$def = settings_hub_page_def($loc['page']);
		$open = $def !== null && settings_hub_page_available($def) && settings_hub_page_permitted($def);
		return array('path' => settings_hub_path($loc, $sprogId), 'url' => $open ? $def['url'] : '', 'tag' => '', 'all' => '');
	};
	$byUrl = function (string $url): string {
		foreach (getSettingsRegistry() as $entry) {
			if ((string) $entry['url'] === $url) {
				return (string) $entry['key'];
			}
		}
		return '';
	};
	$leftOut = function (string $key) use ($sprogId): ?array {
		$out = getSettingsHubLeftOut();
		$def = settings_hub_page_def($key);
		if (!isset($out[$key]) || $def === null) {
			return null;
		}
		$personal = isset($def['entry']['group']) && $def['entry']['group'] === 'personal';
		$open = settings_hub_page_available($def) && ($personal || settings_hub_page_permitted($def));
		return array('path' => settings_hub_page_label($def, $sprogId), 'url' => $open ? $def['url'] : '', 'tag' => $personal ? 'personal' : 'out', 'all' => settings_hub_text_all($out[$key]));
	};
	foreach (getSettingsMovedMap() as $moved) {
		$to = array();
		foreach ($moved['to'] as $t) {
			list($group, $sectionId, $url) = $t;
			$key = $sectionId !== null ? (string) $sectionId : ($group === 'personal' ? 'userSettings' : $byUrl((string) $url));
			$found = ($key !== '') ? ($target($key) ?: $leftOut($key)) : null;
			if ($found !== null && !in_array($found['path'], array_column($to, 'path'), true)) {
				$to[] = $found;
			}
		}
		$rows[] = array('old' => trim((string) preg_replace('/\s+/u', ' ', settings_legacy_text($moved['old'], $sprogId))), 'search' => settings_legacy_all_languages($moved['old']), 'to' => $to);
	}
	foreach (getSettingsRegistry() as $entry) {
		$key = (string) $entry['key'];
		$group = isset($entry['group']) ? (string) $entry['group'] : '';
		if (!isset($groups[$group])) {
			continue;
		}
		$def = settings_hub_page_def($key);
		$found = $target($key) ?: $leftOut($key);
		if ($def === null || $found === null || !settings_hub_page_available($def)) {
			continue;
		}
		$oldGroup = settings_hub_text($groups[$group]['label'], $sprogId);
		$renamed = settings_hub_renamed_from($def);
		$oldLabel = $renamed !== null ? settings_hub_text($renamed, $sprogId) : settings_hub_page_label($def, $sprogId);
		$loc = settings_hub_locate($key);
		if ($loc !== null) {
			$hub = getSettingsHub();
			$row = settings_hub_row($loc);
			// Not moved: same group name and the row carries the entry's own name.
			if (settings_hub_text($hub[$loc['group']]['label'], $sprogId) === $oldGroup && $row !== null && settings_hub_text($row['label'], $sprogId) === $oldLabel) {
				continue;
			}
		}
		$rows[] = array('old' => $oldGroup . ' → ' . $oldLabel, 'search' => settings_hub_text_all($groups[$group]['label']) . ' ' . settings_hub_text_all($def['label']) . ($renamed !== null ? ' ' . settings_hub_text_all($renamed) : ''), 'to' => array($found));
	}
	foreach ($rows as $i => $row) {
		foreach ($row['to'] as $t) {
			$rows[$i]['search'] .= ' ' . mb_strtolower($t['path']) . ' ' . $t['all'];
		}
		$rows[$i]['search'] = mb_strtolower($row['old']) . ' ' . $rows[$i]['search'];
	}
	return $rows;
}

/**
 * The module the breadcrumb map (includes/stdFunc/pageRoutes.php) gives a page 'dir/file.php': its own entry, else its first
 * variant ('dir/file.php?x=y'), else its folder's module; '' when the map knows neither. 'settings' = a page the map hangs
 * under the hub (its trail starts at Indstillinger itself, see pageRoutes.php).
 */
function settings_hub_route_module(string $key): string
{
	if (!function_exists('page_routes')) {
		include_once(__DIR__ . '/../stdFunc/pageChrome.php');
	}
	$routes = page_routes();
	if (isset($routes['pages'][$key]['m'])) {
		return (string) $routes['pages'][$key]['m'];
	}
	foreach ($routes['pages'] as $k => $entry) {
		if (strpos((string) $k, $key . '?') === 0 && isset($entry['m'])) {
			return (string) $entry['m'];
		}
	}
	$dir = (strpos($key, '/') !== false) ? substr($key, 0, strpos($key, '/')) : '';
	return isset($routes['dirs'][$dir]) ? (string) $routes['dirs'][$dir] : '';
}

/**
 * Whether a url (a request uri from the navigation stack, or a registry url) belongs to the settings system: the hub, a
 * section, the moved map, every page in systemdata/ (the old addresses that only redirect - diverse.php, syssetup.php,
 * ansatte.php ... - included), any address carrying moved=, a page the breadcrumb map hangs under the hub (admin/backup.php)
 * and a page a hub row opens. Not: a page the map gives to another module (kontoplan.php and kontokort.php are Finans,
 * rental/settings.php is Booking, sager/tjeklister.php is Sager), even when a hub row opens it - unless the address carries
 * the settings marker (settings=1: the page was opened from Indstillinger and shows the settings layout).
 */
function settings_hub_is_settings_url(string $url): bool
{
	$path = (string) parse_url($url, PHP_URL_PATH);
	$file = basename($path);
	$dir = basename(dirname($path));
	if ($file === '') {
		return false;
	}
	$query = array();
	parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
	if (isset($query['moved']) || (isset($query[settings_marker_param()]) && $query[settings_marker_param()] === '1')) {
		return true;
	}
	$module = settings_hub_route_module($dir . '/' . $file);
	if ($module === 'settings' || ($module === 'system' && $dir === 'systemdata')) {
		return true;
	}
	if ($module !== '' && $module !== 'system') {
		return false;
	}
	foreach (settings_hub_index() as $key => $loc) {
		if ($key !== $loc['page']) {
			continue;
		}
		$def = settings_hub_page_def($key);
		if ($def === null || $def['kind'] !== 'entry') {
			continue;
		}
		$p = (string) parse_url($def['url'], PHP_URL_PATH);
		$pDir = (strpos($p, '../') === 0) ? basename(dirname(substr($p, 3))) : 'systemdata';
		if (basename($p) === $file && $pDir === $dir) {
			return true;
		}
	}
	return false;
}

/**
 * The target of the Tilbage button every settings page shows above its title (owner, 1 Nov 2026). Fixed, so two pages never
 * send the user back and forth:
 * - $returnTo wins (a path inside Saldi: the module page a gear link came from, or a page's own parent such as a user card's list);
 * - the hub and the personal settings (both opened from anywhere in Saldi): the newest page in the navigation stack outside the
 *   settings system (settings_hub_is_settings_url(): no settings page and no old address that redirects), else Oversigt;
 * - any other settings page (a section, a framed old page, an import page ...): the hub.
 * url: relative to the running page's folder, or a request uri from the navigation stack; title: the target's name.
 *
 * @return array{url: string, label: string, title: string}
 */
function settings_back_link(int $sprogId, string $returnTo = ''): array
{
	$script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
	$inSystemdata = basename(dirname($script)) === 'systemdata';
	$label = settings_hub_text('5647|Tilbage', $sprogId);
	$overview = settings_hub_text('2224|Oversigt', $sprogId);
	$titleOf = function (string $url) use ($sprogId, $overview): string {
		$path = (string) parse_url($url, PHP_URL_PATH);
		if (basename($path) === 'dashboard.php' && basename(dirname($path)) === 'index') {
			return $overview;
		}
		// A section by its own name: page_label_for() keys on the path only, so it would give the last section shown, whichever.
		$query = array();
		parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
		if (basename($path) === 'settingsSection.php' && isset($query['s']) && is_string($query['s'])) {
			$def = settings_hub_page_def($query['s']);
			if ($def !== null) {
				return settings_hub_page_label($def, $sprogId);
			}
		}
		return function_exists('page_label_for') ? (string) page_label_for($url) : settings_hub_text('5647|Tilbage', $sprogId);
	};
	if ($returnTo !== '') {
		return array('url' => $returnTo, 'label' => $label, 'title' => $titleOf($returnTo));
	}
	if ($inSystemdata && in_array(basename($script), array('settings.php', 'personalSettings.php'), true)) {
		$stack = function_exists('_nav_read') ? _nav_read() : array();
		for ($i = count($stack) - 1; $i >= 0; $i--) {
			$url = (string) $stack[$i];
			if ($url !== '' && !settings_hub_is_settings_url($url)) {
				return array('url' => $url, 'label' => $label, 'title' => $titleOf($url));
			}
		}
		return array('url' => '../index/dashboard.php', 'label' => $label, 'title' => $overview);
	}
	return array('url' => ($inSystemdata ? '' : '../systemdata/') . 'settings.php', 'label' => $label, 'title' => settings_hub_text('122|Indstillinger', $sprogId));
}

/**
 * Consistency of the hub (no database): every section and registry entry in exactly one row, except the left-out ones;
 * four groups of four sub-groups; aliases inside their own row; every text id in tekster.csv. Used by the check script.
 *
 * @return array<int, string>
 */
function settings_hub_coverage_problems(): array
{
	$problems = array();
	$seen = array();
	$hub = getSettingsHub();
	if (count($hub) !== 4) {
		$problems[] = 'the hub has ' . count($hub) . ' groups, not 4';
	}
	foreach ($hub as $groupId => $group) {
		if (count($group['subs']) !== 4) {
			$problems[] = "group $groupId has " . count($group['subs']) . ' sub-groups, not 4';
		}
		foreach ($group['subs'] as $subId => $sub) {
			if (!$sub['rows']) {
				$problems[] = "sub-group $subId has no rows";
			}
			foreach ($sub['rows'] as $row) {
				$rowId = (string) $row['maps'][0];
				if (settings_hub_page_def($rowId) === null || settings_hub_page_def($rowId)['alias_of'] !== '') {
					$problems[] = "row $rowId: its first key is not a page";
				}
				foreach ($row['maps'] as $key) {
					$def = settings_hub_page_def((string) $key);
					if ($def === null) {
						$problems[] = "row $rowId: unknown key $key";
						continue;
					}
					if ($def['alias_of'] !== '' && !in_array($def['alias_of'], $row['maps'], true)) {
						$problems[] = "row $rowId: $key opens " . $def['alias_of'] . ', which is not in the row';
					}
					$seen[(string) $key][] = "$groupId/$subId/$rowId";
				}
			}
		}
	}
	$leftOut = getSettingsHubLeftOut();
	foreach (array_keys(getSettingsSections()) as $id) {
		if (!isset($seen[$id])) {
			$problems[] = "section $id is in no row";
		}
	}
	foreach (getSettingsRegistry() as $entry) {
		$key = (string) $entry['key'];
		if (isset($leftOut[$key])) {
			if (isset($seen[$key])) {
				$problems[] = "entry $key is left out but in a row";
			}
		} elseif (!isset($seen[$key])) {
			$problems[] = "entry $key is in no row";
		}
	}
	foreach ($seen as $key => $where) {
		if (count($where) > 1) {
			$problems[] = "$key is in " . count($where) . ' rows: ' . implode(', ', $where);
		}
	}
	return $problems;
}

endif;
