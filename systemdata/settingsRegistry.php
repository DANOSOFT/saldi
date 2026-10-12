<?php
// ----------------systemdata/settingsRegistry.php --- Settings search Phase 1 --- 2026-10-11 ----
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260709 SZ Created: hand-maintained registry of Settings pages/keywords for search
// 20260710 SZ Expanded keywords (deep page content, DA/EN/NO, sys_div_func.php-derived terms)
// 20260721 Sawaneh Added Opgaveliste/Brug jobkort (task list) search terms to div_valg entry
// 20260930 CL/NTR Added HTML/CSS layout version search terms to div_valg entry.
//                 Hid the admin_settings entry outside the master database ('masterDb' rule).
//
// Hand-maintained index of Settings pages, used by settingsSearch.php.
// Each entry:
//   key              stable id, unique, used only for de-dup/testing
//   url              relative to /systemdata/ (e.g. 'valuta.php'), or '../module/file.php' for other modules
//   category          grouping key shown as a category tag in the search dropdown
//   textId           optional - a findtekst() id already verified (in importfiler/tekster.csv) to have
//                    real Danish + English + Norwegian text. When present, the label is resolved via
//                    findtekst(), which already picks the right column for sprog_id 1/2/3.
//   labelDa/labelEn/labelNo  required when textId is absent - hand-written labels (draft copy for
//                    entries that have no existing translation id). labelNo is optional; if a page
//                    has no Norwegian draft yet, settingsSearch.php falls back to labelEn rather than
//                    silently showing Danish.
//   requiresReseller  true => only shown when $revisorregnskab || $forhandlerregnskab is truthy
//   visibilityRule    null | 'posModule' | 'masterDb' - re-checked live in settingsSearch.php
//   visibilityRule    null | 'posModule' | 'docubizz' | 'masterDb' - re-checked live in settingsSearch.php
//                    ('masterDb' => only shown when $db == $sqdb, i.e. logged in to the master database,
//                    for pages such as admin/admin_settings.php that log everyone else out)
//   keywords         array of extra search terms describing what's actually configurable on that
//                    page (field names, synonyms, abbreviations, DA/EN/NO) - matched when the query
//                    doesn't hit the label itself, so e.g. searching "auditor" finds "Brugere"
//                    (the "Revisor"/Auditor checkbox lives there), "order layout" finds "Formularer",
//                    or "påminnelse" (Norwegian for "Rykker"/reminder) also finds "Formularer".
//                    Hand-transcribed from each page's real field labels (cross-checked against
//                    importfiler/tekster.csv, which has a Dansk/English/Norsk column for every id
//                    used here) - keep it that way; don't invent settings that aren't actually on
//                    the page, and don't invent translations that aren't already in tekster.csv.
// 20260916 Sawaneh userSettings entry now points at systemdata/personalSettings.php (text 5230).
// 20260928 Sawaneh Phase 4: every entry has a 'group' of the settings front page; groups, access and
//                  request-to-entry matching live here, so front page, menu, search and gate share them.
// 20260928 Sawaneh Groups, keys and placement aligned with Requirements_settings_redesign_EN.md §4 and Appendix A.
// 20260929 Sawaneh Phase 4a: registry v2 (settingsDefinitions.php) included; generated sections G3.2, G3.3, G4.2
//                  and G5.5 replace the ordre_valg and massefakt pages; PoS visibility from the licence flag.
// 20261001 Sawaneh Phase 4a §8.13: optional modules (on or off) and computed status badges for the front page.
// 20261002 Sawaneh Phase 4b batch 1: provision, productOptions, orediff and betalinger_settings entries point at the
//                  generated sections (commission, consignment, packaging, cash journal); mySale and print added.
// 20261002 Sawaneh Hand-over 2 Oct (A3, A4): breadcrumb trail for the shell's topbar; backup is an entry under Import & eksport
//                  with its own key, since the sidebar's System menu is gone (decision 16).
// 20261002 Sawaneh Hand-over 2 Oct (A1): the three labelled group lists, computed status per group and "Kræver opmærksomhed".
// 20261004 Sawaneh settings_require_any_access(): the settings pages are open to users with read on any settings group.
// 20261005 Sawaneh 4d: Baggrunde (G6.2) added; Import & eksport and Sikkerhedskopi (G11) open their new sections.
// 20261005 Sawaneh 4d: Valuta, Regnskabsår and Ansatte open their new sections; Abonnement & konto (G1.4) only on hosted servers.
// 20261005 Sawaneh 4d: Sprog opens Lokalisering (company.localisation); Persondata (company.gdpr) added.
// 20261005 Sawaneh 4d: Stamdata opens the generated company.data section.
// 20261005 Sawaneh 4c: Moms, Debitorgrupper, Kreditorgrupper and Varegrupper open the generated row-editor sections.
// 20261005 Sawaneh settings_breadcrumb_script() goes through page_breadcrumb() (topbar addendum 2026-10-05 §4).
// 20261005 Sawaneh 4c: Afdelinger, Projekter, Lagre and Enheder entries open the generated row-editor sections.
// 20261004 Sawaneh §8.11 settings_context_links() for the gear in the sub-bar; §8.10 banner only for users who used the old settings.
// 20261004 Sawaneh Stripe only in the operator ledger (G9.6), bank only when its credentials exist (G2.7), DFM flag in its own group.
// 20261004 Sawaneh G10 batch B: Betalingskort and Borde entries; the PoS-valg entry is gone (its keywords moved to Kasser).
// 20261004 Sawaneh Projekter opens projekter.php (syssetup.php?valg=projekter shows no projects, spec C7).
// 20261009 Sawaneh The tjekliste entry removed: checklists live in the Sager module now (settings redesign §4, 4e).
// 20261010 Sawaneh The labels entry opens the new Mærkater page (labels.php, G5.8).
// 20261011 Sawaneh settings_section_complete() (spec §8.8): 'done' from data for the onboarding checklist and the landing
//                  cards, which show 'Grundopsætning mangler' for Salg/Køb/Varer until their base rows exist.

if (!function_exists('getSettingsRegistry')) {
	function getSettingsRegistry() {
		return array(
			// -- systemdata/left_menu.php / top.php sidebar (the main Settings list) --
			array('key' => 'moms',            'group' => 'finance', 'url' => 'settingsSection.php?s=finance.vat', 'section' => 'finance.vat', 'category' => 'tax',      'textId' => 770,
				'keywords' => array('vat','vat rate','vat percentage','sales tax','purchase tax','output vat','input vat','tax code','skat','reverse charge','eu vat','vat report','tax report','vat rounding','moms','momssats','momsprocent','udgående moms','indgående moms','momskonto','moms af varekøb','moms af ydelseskøb','momsrapport','momskode','mva','merverdiavgift','mva-sats')),
			array('key' => 'debitor_grupper',  'group' => 'sales', 'url' => 'settingsSection.php?s=sales.debtor_groups', 'section' => 'sales.debtor_groups', 'category' => 'groups',  'textId' => 771,
				'keywords' => array('debtor','creditor','customer groups','supplier groups','vendor groups','vat group','collective account','summary account','samlekonto','counter account','offset account','modkonto','commission percentage','b2b price','reverse charge liability','invoice language','kreditorgrupper','debitorgrupper')),
			array('key' => 'afdelinger',       'group' => 'organisation', 'url' => 'settingsSection.php?s=organisation.departments', 'section' => 'organisation.departments', 'category' => 'org',     'textId' => 772,
				'keywords' => array('department','departments','cost center','branch','store location','afdeling','afdelinger','formularnote','avdeling','avdelinger')),
			array('key' => 'projekter',        'group' => 'organisation', 'url' => 'settingsSection.php?s=organisation.projects', 'section' => 'organisation.projects', 'category' => 'org',     'textId' => 773,
				'keywords' => array('project','projects','project number','project code','job code','projektnummer','projekter','prosjekt','prosjekter')),
			array('key' => 'lagre',            'group' => 'items', 'url' => 'settingsSection.php?s=items.warehouses', 'section' => 'items.warehouses', 'category' => 'stock',   'textId' => 608,
				'keywords' => array('warehouse','warehouses','stock location','storage location','inventory location','lager','lagre','lagerlokation')),
			array('key' => 'varegrupper',      'group' => 'items', 'url' => 'settingsSection.php?s=items.item_groups', 'section' => 'items.item_groups', 'category' => 'groups',  'textId' => 774,
				'keywords' => array('product groups','item groups','price groups','cost price','sales price','recommended price','retail price','b2b price','campaign groups','special offer price','offer price','discount groups','batch','reverse charge','vat per product group','varegrupper','prisgrupper','tilbudsgrupper','rabatgrupper','kampagnepris','kostpris','salgspris','vejledende pris')),
			array('key' => 'rabatgrupper',     'group' => 'sales', 'url' => 'settingsSection.php?s=sales.discounts',             'category' => 'groups',  'textId' => 775,
				'keywords' => array('customer discount matrix','debtor discount group','product discount group','discount matrix','percent discount','amount discount per unit','kr/stk rabat','discount by customer and product group','debitor rabatgruppe','vare rabatgruppe','rabatgrupper','rabat','rabatt')),
			array('key' => 'valuta',           'group' => 'finance', 'url' => 'settingsSection.php?s=finance.currencies', 'section' => 'finance.currencies', 'category' => 'finance', 'textId' => 776,
				'keywords' => array('currency','currencies','exchange rate','currency code','currency rate','pos currency','valuta','valutakode','kurs')),
			array('key' => 'brugere',          'group' => 'users', 'url' => 'usersRoles.php',               'category' => 'users',   'textId' => 5536,
				'keywords' => array('user','users','user permissions','access rights','user rights','password','change password','two factor authentication','2fa','sms code','auditor','accountant','revisor','revisoradgang','employee link','ip address restriction','allowed ip','user roles','delete user','add user','new user','brugernavn','rettigheder','adgangskode','brukere','brukernavn','passord','tilgangsrettigheter')),
			array('key' => 'regnskabsaar',     'group' => 'company', 'url' => 'settingsSection.php?s=company.fiscal_years', 'section' => 'company.fiscal_years', 'category' => 'finance', 'textId' => 778,
				'keywords' => array('fiscal year','financial year','accounting year','start month','end month','close year','closed year','delete fiscal year','active fiscal year','set active year','create fiscal year','regnskabsår','regnskapsår')),
			array('key' => 'stamkort',         'group' => 'company', 'url' => 'settingsSection.php?s=company.data', 'section' => 'company.data', 'category' => 'company', 'textId' => 779,
				'keywords' => array('company info','company profile','company name','company address','vat number','tax id','cvr number','bank details','bank account','gdpr agreement','data processing agreement','contact person','phone number','mobile number','employee list','firmanavn','bankoplysninger','databehandleraftale','kontaktperson')),
			array('key' => 'konto',            'group' => 'company', 'url' => 'settingsSection.php?s=company.account', 'section' => 'company.account', 'category' => 'company', 'textId' => 6630, 'visibilityRule' => 'hosted',
				'keywords' => array('abonnement', 'konto', 'regnskabets navn', 'skift navn', 'nulstil regnskab', 'slet regnskab', 'farezone', 'reset', 'delete ledger')),
			array('key' => 'gdpr',             'group' => 'company', 'url' => 'settingsSection.php?s=company.gdpr', 'section' => 'company.gdpr', 'category' => 'company', 'textId' => 6523,
				'keywords' => array('gdpr', 'persondata', 'personal data', 'inaktive kunder', 'inactive customers', 'oprydning')),
			array('key' => 'ansatte',          'group' => 'organisation', 'url' => 'settingsSection.php?s=organisation.employees', 'section' => 'organisation.employees', 'category' => 'company', 'textId' => 1262,
				'keywords' => array('employee record','staff record','new employee','edit employee','employee number','employee name','employee address','employee email','employee phone','employee mobile','salary','payroll','extra salary','cpr number','social security number','initials','pos code','employee department','employee background','employee language','employee bank account','employee notes','employee start date','employee end date','terminate employee','close employee','ansatte','løn','cprnr','initialer','startdato','slutdato','lønn')),
			array('key' => 'baggrunde',        'group' => 'documents', 'url' => 'settingsSection.php?s=documents.backgrounds', 'section' => 'documents.backgrounds', 'category' => 'documents', 'textId' => 6671,
				'keywords' => array('baggrund', 'baggrunde', 'formularsprog', 'background', 'logo', 'brevpapir', 'bilag', 'pdf')),
			array('key' => 'formularer',       'group' => 'documents', 'url' => 'formularkort.php?valg=formularer', 'category' => 'documents', 'textId' => 780,
				'keywords' => array('order layout','order confirmation layout','invoice layout','invoice template','invoice design','quote layout','offer layout','credit note layout','packing slip layout','delivery note layout','reminder letter template','dunning letter','pick list layout','picking list layout','requisition layout','purchase order layout','purchase invoice layout','account card layout','document template','form editor','form design','logo position','logo upload','print layout','template design','background name','ordrebekræftelse layout','fakturadesign','tilbud skabelon','rykker skabelon','følgeseddel layout','plukliste layout','kontokort layout','reminder fee','interest rate on reminders','mail text for invoice','email text template','move text position','text position on form','line and border design','font size on form',
					'skjemaer','ordrebekreftelse','kredittnota','påminnelse','påminnelsesmal','plukkliste','kjøpsforslag','rekvisisjon','kjøpsfaktura','bestillingslinjer','e-post tekst')),
			array('key' => 'enheder',          'group' => 'items', 'url' => 'settingsSection.php?s=items.units', 'section' => 'items.units', 'category' => 'products', 'textId' => 781,
				'keywords' => array('unit','units','unit of measure','measurement unit','material','materials','material density','weight calculation','enhed','enheder','materiale','enheter')),

			// -- systemdata/diverse.php sub-sections (each independently searchable) --
			array('key' => 'diverse_overview',      'group' => '', 'url' => 'diverse.php',                              'category' => 'diverse', 'textId' => 782,
				'keywords' => array('miscellaneous settings','other settings','diverse indstillinger')),
			array('key' => 'kontoindstillinger',    'group' => 'company', 'url' => 'diverse.php?sektion=kontoindstillinger',   'category' => 'diverse', 'textId' => 783,
				'keywords' => array('account settings','company settings','system settings','rename account','rename company','company name change','base currency','system currency','timezone','time zone','max users','user limit','number of users','reset account data','wipe all data','delete account','close account','terminate account','regnskabsnavn','tidszone','nulstil regnskab','slet regnskab','antal brugere','kontoinnstillinger','sort by phone number','postings last 12 months','keep customers and suppliers on reset','keep products on reset','reset account confirmation','backup before reset warning','5 year backup retention','bookkeeping law backup')),
			array('key' => 'provision',             'group' => 'organisation', 'url' => 'settingsSection.php?s=organisation.commission', 'section' => 'organisation.commission', 'category' => 'diverse', 'textId' => 657,
				'keywords' => array('commission report settings','commission calculation','sales commission','provisionsrapport','provision','provisjonsberegning','provisjon',
					'commission basis','invoiced or paid commission','commission source person','customer responsible person commission','reference person commission','cost price source for commission','purchase price commission','product card cost price commission','cutoff date commission calculation', 'default commission rate', 'show commission on item card', 'standard provisionssats')),
			array('key' => 'userSettings',          'group' => 'personal', 'url' => 'personalSettings.php',                     'category' => 'personal', 'textId' => 5500,
				'keywords' => array('personal settings','my settings','profile settings','appearance settings','theme color','button color','text color','button text color','ui color customization','user interface preferences','personlige valg','knapfarve',
					'expiry warning days','udløbsadvarsel','language','sprog','password','adgangskode','two factor','2fa','notifications','notifikationer','active sessions','global bar placement')),
			array('key' => 'ordre_valg',             'group' => 'sales', 'url' => 'settingsSection.php?s=sales.orders', 'section' => 'sales.orders', 'category' => 'invoicing', 'textId' => 5679,
				'keywords' => array('order settings','order options','vat on orders','show vat private customers','show vat business customers','negative stock','allow negative stock','low stock warning','out of stock warning','fifo costing','cost method','quick invoicing','immediate posting','same day posting','discount item number','delivery note text','packing slip text','shipping item number','freight item number','postage item','pick list email','send pick list by mail','gs1 barcode scanning','barcode parsing','order autocomplete','search autocomplete orders','lock invoice until paid','ipad system','ordrerelaterede valg','hurtigfaktura','negativt lager','rabatvarenummer','bestillingsrelaterte valg','bestilling',
					'automatic cost price adjustment','average cost price','replacement cost price','update cost prices button','packing slip comments','quantity only on packing slip','total price bundle discount','percentage invoicing','rental percentage invoicing','percentage surcharge','item number for surcharge','cash sale account number','credit card sale account number','internal order note','debtor ipad self email','discount decimals on orders','immediate posting purchase orders','immediate posting sales orders','item number for set bundle','mass invoicing','batch invoicing','consolidated invoicing','partial delivery','delivery deadline days','massefakturering','dellevering')),
			array('key' => 'mysale',               'group' => 'sales', 'url' => 'settingsSection.php?s=sales.mysale', 'section' => 'sales.mysale', 'category' => 'invoicing', 'textId' => 5986,
				'keywords' => array('mysale','my sales','mit salg','loppemarked','flea market','commission customers','provisionskunder','mysale labels','label maxlength','disable labels for customers','mitt salg')),
			array('key' => 'print',                'group' => 'documents', 'url' => 'settingsSection.php?s=documents.print', 'section' => 'documents.print', 'category' => 'documents', 'textId' => 5993,
				'keywords' => array('print','printer','local printer','direct print','lokal printer','direkte print','html forms','postscript','formulargenerering','html/css','utskrift','skriver')),
			array('key' => 'consignment',          'group' => 'items', 'url' => 'settingsSection.php?s=items.consignment', 'section' => 'items.consignment', 'category' => 'products', 'textId' => 5975, 'visibilityRule' => 'posModule',
				'keywords' => array('product options','vat on product card','show prices with vat','confirm description change','confirm stock change','consignment sales','commission sales','used goods commission','commission percentage','commission account','minimum stock level','reorder level','low stock threshold','stock status email','stock status report','email frequency stock','varerelaterede valg','kommissionsvarer','minimumsbeholdning','lagerstatus mail','lagerstatus rapport','varerelaterte valg','kommisjonsvarer','provisjonssalg','minimumsbeholdning av varer','lagerstatusrapporter','mva på varekort')),
			array('key' => 'packaging',            'group' => 'items', 'url' => 'settingsSection.php?s=items.packaging', 'section' => 'items.packaging', 'category' => 'products', 'textId' => 5976,
				'keywords' => array('packaging module','producer responsibility','packaging tax','emballage','emballagemodul','producentansvar','emballasje','produsentansvar')),
			array('key' => 'variant_valg',           'group' => 'items', 'url' => 'settingsSection.php?s=items.variants',         'category' => 'products', 'textId' => 788,
				'keywords' => array('product variants','variant types','variant values','color variant','size variant','import variants','import variant types','import variant values','csv import variants','variantrelaterede valg','varianter','variasjonsrelaterte valg',
					'webshop selection','internal webshop','external webshop','no webshop','webshop url','fetch products from shop','shop character encoding')),
			array('key' => 'api_valg',              'group' => 'integrations', 'url' => 'settingsSection.php?s=integrations.connections', 'section' => 'integrations.connections', 'category' => 'integrations', 'textId' => 5537,
				'keywords' => array('api settings','api key','api access','ip whitelist','allowed ip addresses','external integration','import file path','api bruger','api nøgle',
					'saldi db variable','saldi url variable','api client url','api reference user','update from shop','fetch new products from shop')),
			array('key' => 'stripe_valg',           'group' => 'integrations', 'url' => 'diverse.php?sektion=stripe_valg',          'category' => 'integrations', 'visibilityRule' => 'masterDb', 'labelDa' => 'Stripe abonnement', 'labelEn' => 'Stripe subscriptions',
				'keywords' => array('stripe','subscription','subscriptions','abonnement','recurring payment','recurring billing','checkout','webhook','webhook secret','secret key','api key stripe',
					'kortbetaling','card payment','betalingslink','payment link','tax rate','vat rate id','base url','bogholder email','bookkeeper email','stripe mode','test mode','live mode')),
			array('key' => 'labels',                'group' => 'items', 'url' => 'labels.php',                              'category' => 'documents', 'textId' => 791,
				'keywords' => array('label printer template','price tag design','price label','barcode label layout','product label template','sticker template','label size','label width and height','label columns and rows','label font size','label margins','show item number on label','show barcode on label','mærkater','label editor','vareetiket','klistremerker','skriftstørrelse',
					'dymo','dymo 11354','brother printer','brother 22606','label print html code','product card label html','a4 label sheet','simple labels','label templates')),
			array('key' => 'pricelists',            'group' => 'purchase', 'url' => 'settingsSection.php?s=purchase.pricelists', 'section' => 'purchase.pricelists', 'category' => 'pricing', 'textId' => 6219,
				'keywords' => array('price list import','supplier price list','vendor price list','csv price list','csv delimiter','csv encoding','vendor price feed','product price import','price file url','purchase price list','add pricelist url','supplier group pricelist','product group pricelist','prisliste import','prisfil',
					'price list file type','price list discount','supplier discount','vvs price list','plumbing price list','solar vvs','active price list toggle','delete price list reference')),
			array('key' => 'rykker_valg',           'group' => 'sales', 'url' => 'settingsSection.php?s=sales.reminders', 'section' => 'sales.reminders', 'category' => 'invoicing', 'textId' => 6190,
				'keywords' => array('reminder settings','dunning settings','debt collection','collection agency','debt collector','inkasso','reminder responsible user','person responsible for reminders','rykkerrelaterede valg','rykker','påminnelsesrelaterte valg','påminnelse',
					'reminder responsible email','interest rate per month reminder','reminder 1 deadline days','reminder 2 deadline days','reminder 3 deadline days','collection lawyer account number','collection attorney')),
			array('key' => 'div_valg',               'group' => 'company', 'url' => 'diverse.php?sektion=div_valg',             'category' => 'diverse', 'textId' => 794,
				'keywords' => array('payment days','default payment terms','label size mysale','vat on orders private customers','vat on orders business customers','pickup address','multiple pickup addresses','fragtintegration','betalingsdage','afhentningsadresse','mysale','customer sales portal','salesperson self service','commission self service portal','let customers see own sales','jobkort','brug jobkort','use job cards','opgaveliste','task list','oppgaveliste','bruk jobbkort','task list under debtor accounts','job card system','work order tracking','payment list toggle','show payment list debitor creditor','betalingsliste','customer phone on new order','different dates on order','extra employee on order','mandatory debtor group on debtor card','mandatory customer responsible on debtor card','extra fields on employee card','payment lists erh bank format','debtor account as order phone','activate mysale flea market','max label character length','use jobkort task descriptions','direct print to local printer','html css form generation','different dates same voucher cash journal','collection agency account number','ebconnect integration','pickup address different from main address','pickup company name','pickup zip code and city','order button name')),
			array('key' => 'bilag',                 'group' => 'finance', 'url' => 'settingsSection.php?s=finance.document_storage', 'section' => 'finance.document_storage', 'category' => 'documents', 'textId' => 6205,
				'keywords' => array('attachment storage','receipt storage','document storage settings','ftp storage for attachments','internal storage','external storage','cloud storage for receipts','scan receipts by email','bilag ftp','bilagshåndtering','dokumenthåndtering',
					'scanned receipts storage','store documents per gb per month','receipt email inbox address','own ftp server for documents','google docs viewer','ftp server name or ip','ftp username and password for documents','ftp folder for receipts','no storage option')),
			array('key' => 'cash_journal',          'group' => 'finance', 'url' => 'settingsSection.php?s=finance.cash_journal', 'section' => 'finance.cash_journal', 'category' => 'finance', 'textId' => 5983,
				'keywords' => array('rounding difference account','penny difference','cash rounding','rounding account','øredifferencer','øreforskjeller', 'payment terms','credit terms','payment due days','default payment days','invoice due date settings','betalingsfrist','betalingsdato', 'payment lists','betalingslister','different dates same voucher','forskellige datoer','bilagsnummer')),
			array('key' => 'bank_integration',      'group' => 'finance', 'url' => 'diverse.php?sektion=bank_integration',     'category' => 'integrations', 'visibilityRule' => 'bankFeature', 'labelDa' => 'Bank Integration', 'labelEn' => 'Bank integration', 'labelNo' => 'Bankintegrasjon',
				'keywords' => array('bank feed','bank transaction import','bank statement import','show bank status','show status kassekladde','default date range bank import','date method','last quarter','this quarter','bank connection status')),
			array('key' => 'barcodescan',           'group' => 'integrations', 'url' => 'settingsSection.php?s=integrations.connections&item=app', 'section' => 'integrations.connections', 'category' => 'pos', 'labelDa' => 'App Barcode', 'labelEn' => 'Barcode scanning app', 'labelNo' => 'App-strekkode',
				'keywords' => array('qr code login','app login','mobile app authentication','one time access qr','saldi app login','scan to login')),
			array('key' => 'sprog',                 'group' => 'company', 'url' => 'settingsSection.php?s=company.localisation', 'section' => 'company.localisation', 'category' => 'company', 'textId' => 6524,
				'keywords' => array('language','languages','change language','select language','preferred language','edit translation texts','ui language','current language','sprogindstillinger','sprog','språk','språkinnstillinger')),
			array('key' => 'div_io',                'group' => 'import_export', 'url' => 'settingsSection.php?s=import_export.data', 'section' => 'import_export.data', 'category' => 'data', 'textId' => 802,
				'keywords' => array('import export','chart of accounts import export','customer import export','product import export','form import export','data import','data export','solar vvs import','kontoplan import','debitor import','varer import','formular import')),
			array('key' => 'backup',                'group' => 'import_export', 'url' => 'settingsSection.php?s=import_export.backup', 'section' => 'import_export.backup', 'category' => 'data', 'textId' => 614, 'permission' => 'settings.backup',
				'keywords' => array('backup','restore','sikkerhedskopi','gendan','sikkerhetskopi','gjenopprett','database backup')),

			// -- 20260928: pages that belong to a settings group but had no entry --
			array('key' => 'kontoplan',         'group' => 'finance',    'url' => 'kontoplan.php',             'category' => 'finance',  'textId' => 612,
				'keywords' => array('chart of accounts','account list','ledger accounts','kontoplan','konti','kontooversikt')),
			array('key' => 'debtor_card',       'group' => 'sales',    'url' => 'settingsSection.php?s=sales.debtor_card', 'section' => 'sales.debtor_card', 'category' => 'groups', 'textId' => 5681,
				'keywords' => array('debtor card','customer card','mandatory debtor group','mandatory customer responsible','job cards','debtor ipad','debitorkort','kundekort','jobkort','debitoripad')),
			array('key' => 'purchase_orders',   'group' => 'purchase', 'url' => 'settingsSection.php?s=purchase.orders', 'section' => 'purchase.orders', 'category' => 'invoicing', 'textId' => 5682,
				'keywords' => array('purchase orders','immediate posting purchase orders','købsordrer','indkøbsordrer','omgående bogføring af købsordrer')),
			array('key' => 'stock_control',     'group' => 'items',    'url' => 'settingsSection.php?s=items.stock', 'section' => 'items.stock', 'category' => 'stock', 'textId' => 5680,
				'keywords' => array('stock control','cost price','fifo','negative stock','low stock warning','minimum stock','stock status email','lagerstyring','kostpris','negativt lager','minimumsbeholdning','lagerstatus mail')),
			array('key' => 'kreditorgrupper',   'group' => 'purchase', 'url' => 'settingsSection.php?s=purchase.creditor_groups', 'section' => 'purchase.creditor_groups', 'category' => 'groups',   'textId' => 2458,
				'keywords' => array('creditor groups','supplier groups','vendor groups','kreditorgrupper','leverandørgrupper')),
			array('key' => 'pos_tills',         'group' => 'pos',        'url' => 'settingsSection.php?s=pos.tills', 'section' => 'pos.tills', 'category' => 'pos', 'textId' => 6275, 'visibilityRule' => 'posModule',
				'keywords' => array('kasser', 'tills', 'kassekonti', 'pos-valg', 'posoptions', 'pos settings','cash register settings','point of sale options','number of cash registers','number of card terminals','card payment accounts','cash accounts','department per register','vat group cash customers','discount item cash sale','receipt printing','print receipt automatically','disable receipt printing','bon print','cash drawer','opening float','starting cash amount','cash count assistance','coins and banknotes','interim account','cash difference account','table selection','restaurant table','number of tables','table name','font size pos','gift card numbers','gift card text','voucher numbers','active gift card','post each trade immediately','post immediately to finance','printer ip','receipt printer ip','card terminal ip','card terminal type','flatpay','move3500','lane3000','vibrant terminal','ip baseret terminal','payment terminal type','other payment cards','kitchen printer ip','mobile pos','screen width','zoom level','flip menu','reverse primary secondary menu','cash on amount button','account lookup button','deposit button','forced user selection','clerk selection before checkout','customer display','bundle price','set price','jump to price field','show stock in pos','show inventory in pos','larger order total','print timeout','kasseantal','kortkonti','kassekonti','kortterminal','køkkenprinter','kasseprimo',
					'kassaapparat','avdeling','mva-gruppe','kredittkort','skriverens ip','kjøkken ip','terminaltype','kontantsaldo','kundedisplay','tvunget brukervalg','tabellvalg','antall bord')),
			array('key' => 'pos_cards',         'group' => 'pos',        'url' => 'settingsSection.php?s=pos.cards', 'section' => 'pos.cards', 'category' => 'pos', 'textId' => 6312, 'visibilityRule' => 'posModule',
				'keywords' => array('betalingskort', 'payment cards', 'gavekort', 'gift cards', 'terminal')),
			array('key' => 'pos_cash',          'group' => 'pos',        'url' => 'settingsSection.php?s=pos.cash', 'section' => 'pos.cash', 'category' => 'pos', 'textId' => 6303, 'visibilityRule' => 'posModule',
				'keywords' => array('kasseoptælling', 'cash count')),
			array('key' => 'pos_receipt',       'group' => 'pos',        'url' => 'settingsSection.php?s=pos.receipt', 'section' => 'pos.receipt', 'category' => 'pos', 'textId' => 6253, 'visibilityRule' => 'posModule',
				'keywords' => array('bon', 'kvittering', 'receipt')),
			array('key' => 'pos_screen',        'group' => 'pos',        'url' => 'settingsSection.php?s=pos.screen', 'section' => 'pos.screen', 'category' => 'pos', 'textId' => 6254, 'visibilityRule' => 'posModule',
				'keywords' => array('kasse knapper', 'pos buttons', 'kundedisplay')),
			array('key' => 'pos_tables',        'group' => 'pos',        'url' => 'settingsSection.php?s=pos.tables', 'section' => 'pos.tables', 'category' => 'pos', 'textId' => 6340, 'visibilityRule' => 'posModule',
				'keywords' => array('borde', 'tables', 'bordplaner', 'floor plans')),
			array('key' => 'pos_kitchen',       'group' => 'pos',        'url' => 'settingsSection.php?s=pos.kitchen', 'section' => 'pos.kitchen', 'category' => 'pos', 'textId' => 6258, 'visibilityRule' => 'posModule',
				'keywords' => array('kds', 'køkken', 'kitchen')),
			array('key' => 'posmenuer',         'group' => 'pos',        'url' => 'posmenuer.php',             'category' => 'pos',      'textId' => 1940, 'visibilityRule' => 'posModule',
				'keywords' => array('pos menus','cash register menus','buttons on register','kassemenuer','pos-menuer')),

			// -- Scattered elsewhere in the app --
			array('key' => 'admin_settings',    'group' => 'company', 'url' => '../admin/admin_settings.php',      'category' => 'system',  'textId' => 5668, 'requiresReseller' => true, 'visibilityRule' => 'masterDb',
				'keywords' => array('pdf conversion tools','weasyprint','pdftk','ps2pdf','ftp tool path','database dump tool','backup tool path','zip unzip tar path','system alert text','dashboard news snippet','system tools')),
			array('key' => 'email_settings',    'group' => 'documents', 'url' => 'settingsSection.php?s=documents.email', 'section' => 'documents.email', 'category' => 'documents', 'textId' => 6166,
				'keywords' => array('sender email','sender name','email from address','invoice email sender','background specific email settings','afsender email','afsender navn')),
			array('key' => 'rental_settings',   'group' => 'sales', 'url' => '../rental/settings.php',            'category' => 'rental', 'labelDa' => 'Udlejningsindstillinger', 'labelEn' => 'Rental settings', 'labelNo' => 'Utleieinnstillinger',
				'keywords' => array('rental booking settings','booking format','date or timeslot booking','customer search fields','move in day','move out day','delete confirmation popup','combine consecutive bookings','automatic order creation','rental invoice date','password protect settings','week helper date picker')),
		);
	}
}

include_once(__DIR__ . '/settingsDefinitions.php');

if (!function_exists('settings_has_module')) {
	/**
	 * Licence flag of an optional module (decision 9), via the settings service when it is loaded.
	 */
	function settings_has_module(string $module): bool
	{
		if (!class_exists('SettingsService')) {
			include_once(__DIR__ . '/../includes/settings/SettingsService.php');
		}
		return SettingsService::hasModule($module);
	}
}

if (!function_exists('settings_context_links')) {
	/**
	 * Gear link in the module sub-bar (settings redesign §8.11): page path => the sections whose 'context' names it,
	 * only those the user may open. Read by the shell (index/mainIncludes/topbar.php), so a new section needs no page code.
	 *
	 * @return array<string, array<int, array{url: string, label: string}>>
	 */
	function settings_context_links(int $sprogId): array
	{
		$out = array();
		$groups = getSettingsGroups();
		foreach (getSettingsSections() as $sectionId => $section) {
			if (empty($section['context'])) {
				continue;
			}
			if (!empty($section['module']) && !settings_has_module($section['module'])) {
				continue;
			}
			$permission = isset($section['permission']) ? (string) $section['permission'] : (isset($groups[$section['group']]) ? $groups[$section['group']]['permission'] : 'system.indstillinger');
			if (function_exists('perm_can') && !perm_can($permission, 'read')) {
				continue;
			}
			foreach ((array) $section['context'] as $page) {
				$out[(string) $page][] = array('url' => '/systemdata/settingsSection.php?s=' . rawurlencode($sectionId), 'label' => findtekst((string) $section['label'], $sprogId));
			}
		}
		return $out;
	}

	/**
	 * The transition banner is for people who used the old settings (§8.10): the user has audit rows from before
	 * the new settings landed in this installation (settings_ui/landed_at, written once at login). New users never see it.
	 */
	function settings_show_transition_banner(int $userId): bool
	{
		if ($userId <= 0 || !function_exists('audit_ready') || !audit_ready()) {
			return false;
		}
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'settings_ui' and var_name = 'landed_at' order by id limit 1", __FILE__ . " linje " . __LINE__));
		if (!$r || trim((string) $r['var_value']) === '') {
			return false;
		}
		return (bool) db_fetch_array(db_select("select id from audit_log where bruger_id = $userId and tidspunkt < '" . db_escape_string((string) $r['var_value']) . "' limit 1", __FILE__ . " linje " . __LINE__));
	}
}

if (!function_exists('settings_feature_enabled')) {
	/**
	 * Features that stay hidden until they are live in the installation (spec G2.7): the bank integration is on
	 * when its API credentials exist (bank_integration/includes/enabled.php).
	 */
	function settings_feature_enabled(string $feature): bool
	{
		if ($feature === 'bank') {
			$file = __DIR__ . '/../bank_integration/includes/enabled.php';
			if (!function_exists('bankIntegrationEnabled') && is_file($file)) {
				include_once($file);
			}
			return function_exists('bankIntegrationEnabled') && bankIntegrationEnabled();
		}
		return false;
	}
}

if (!function_exists('settings_require_any_access')) {
	/**
	 * The settings pages are open to anyone holding read on at least one settings group, not only to the old
	 * Indstillinger bit (decision 16: a user with only settings.backup sees the landing page with just backup).
	 * Everyone else is refused as before.
	 */
	function settings_require_any_access(): void
	{
		if (!settings_accessible_groups()) {
			require_permission('system.indstillinger', 'read');
		}
	}
}

if (!function_exists('settings_optional_modules')) {
	/**
	 * Optional modules shown on the front page whether on or off (settings redesign §8.13). 'active' is
	 * read from the flag the old page sets; 'url' is where it is switched on today (the Integrations drawer
	 * since 4b, which can also create the GLS and Danske Fragtmænd rows the old page could not - spec B-D4).
	 *
	 * @return array<int, array{key: string, group: string, label: string, active: bool, url: string, activate: bool}>
	 */
	function settings_optional_modules(): array
	{
		$flags = array();
		$q = db_select("select var_grp, var_name, var_value from settings where (var_grp = 'debitor' and var_name = 'mySale') or (var_grp = 'items' and var_name in ('packagingModuleEnabled', 'useCommission')) or (var_grp in ('GLS', 'DFM') and var_name in ('gls_user', 'dfm_user')) or (var_grp = 'mobilepay' and var_name = 'client_id')", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (trim((string) $r['var_value']) !== '') {
				$flags[$r['var_grp'] . '/' . $r['var_name']] = (string) $r['var_value'];
			}
		}
		$modules = array(
			array('key' => 'mysale', 'group' => 'sales', 'label' => 'mySale', 'active' => isset($flags['debitor/mySale']), 'url' => 'settingsSection.php?s=sales.mysale', 'activate' => true),
			array('key' => 'packaging', 'group' => 'items', 'label' => '5976|Emballage (producentansvar)', 'active' => isset($flags['items/packagingModuleEnabled']) && $flags['items/packagingModuleEnabled'] === 'on', 'url' => 'settingsSection.php?s=items.packaging', 'activate' => true),
		);
		if (settings_has_module('pos')) {
			$modules[] = array('key' => 'consignment', 'group' => 'items', 'label' => '5975|Kommissionsvarer', 'active' => isset($flags['items/useCommission']), 'url' => 'settingsSection.php?s=items.consignment', 'activate' => true);
		}
		$modules[] = array('key' => 'gls', 'group' => 'integrations', 'label' => 'GLS', 'active' => isset($flags['GLS/gls_user']), 'url' => 'settingsSection.php?s=integrations.connections&item=gls', 'activate' => true);
		$modules[] = array('key' => 'dfm', 'group' => 'integrations', 'label' => 'Danske Fragtmænd', 'active' => isset($flags['DFM/dfm_user']) || isset($flags['GLS/dfm_user']), 'url' => 'settingsSection.php?s=integrations.connections&item=dfm', 'activate' => true);
		$modules[] = array('key' => 'mobilepay', 'group' => 'integrations', 'label' => 'MobilePay', 'active' => isset($flags['mobilepay/client_id']), 'url' => 'settingsSection.php?s=integrations.connections&item=mobilepay', 'activate' => true);
		return $modules;
	}

	/**
	 * The three labelled lists of the front page (settings redesign §8.0), in display order.
	 *
	 * @return array<string, array{label: string, groups: array<int, string>}>
	 */
	function settings_group_lists(): array
	{
		return array(
			'company' => array('label' => '6017|Virksomhed & regnskab', 'groups' => array('company', 'finance', 'organisation', 'users')),
			'trade'   => array('label' => '6018|Handel', 'groups' => array('sales', 'purchase', 'items', 'pos')),
			'data'    => array('label' => '6019|Data & forbindelser', 'groups' => array('integrations', 'documents', 'import_export')),
		);
	}

	/**
	 * What needs the administrator's attention, computed on every view and never stored (§8.0, §8.13).
	 *
	 * @return array<string, array{kind: string, title: string, sub: string, button: string, url: string}> keyed by group
	 */
	function settings_attention(array $groups, int $sprogId): array
	{
		$out = array();
		if (isset($groups['integrations'])) {
			$mp = array();
			$q = db_select("select var_name, var_value from settings where var_grp = 'mobilepay' and var_name in ('client_id', 'webhook_secret')", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$mp[$r['var_name']] = trim((string) $r['var_value']);
			}
			if (!empty($mp['client_id']) && empty($mp['webhook_secret'])) {
				$out['integrations'] = array('kind' => 'err', 'title' => findtekst('6029|MobilePay: webhook er ikke forbundet', $sprogId), 'sub' => findtekst('6030|Betalinger registreres ikke automatisk', $sprogId), 'button' => findtekst('6041|Se fejl', $sprogId), 'url' => 'settingsSection.php?s=integrations.connections&item=mobilepay');
			}
		}
		if (isset($groups['documents'])) {
			// Mail falls back to the company's own address, so a sender is only missing when both are empty.
			if (!settings_section_complete('G6.3')) {
				$out['documents'] = array('kind' => 'warn', 'title' => findtekst('6025|Afsender-e-mail mangler', $sprogId), 'sub' => findtekst('5671|Dokumenter & e-mail', $sprogId), 'button' => findtekst('6026|Tilføj afsender', $sprogId), 'url' => 'settingsSection.php?s=documents.email&field=documents.email.sender_email.0');
			}
		}
		if (isset($groups['users']) && function_exists('perm_review_pending')) {
			$pending = count(perm_review_pending());
			if ($pending > 0) {
				$out['users'] = array('kind' => 'warn', 'title' => sprintf(findtekst('6028|%s brugere venter på rollegennemgang', $sprogId), $pending), 'sub' => findtekst('5536|Brugere & roller', $sprogId), 'button' => findtekst('5880|Gennemgå', $sprogId), 'url' => 'usersRoles.php?tab=users&status=review');
			}
		}
		return $out;
	}

	/**
	 * Whether a section is set up, read from the data it owns (spec §8.8): never from a manual tick. Sections not listed
	 * here have nothing to measure and count as complete.
	 */
	function settings_section_complete(string $section): bool
	{
		global $regnaar, $db_id;
		$any = function (string $qtxt): bool {
			return (bool) db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		};
		switch ($section) {
			case 'G1.1':
				return $any("select id from adresser where art = 'S' and coalesce(firmanavn, '') <> '' and coalesce(addr1, '') <> '' and coalesce(postnr, '') <> ''");
			case 'G1.2':
				return $any("select id from grupper where art = 'RA'");
			case 'G2.2':
				return (int) $regnaar > 0 && $any("select id from grupper where art = 'SM' and fiscal_year = '" . (int) $regnaar . "'")
					&& $any("select id from grupper where art = 'KM' and fiscal_year = '" . (int) $regnaar . "'");
			case 'G3.1':
			case 'G4.1':
			case 'G5.1':
				$art = array('G3.1' => 'DG', 'G4.1' => 'KG', 'G5.1' => 'VG');
				return (int) $regnaar > 0 && $any("select id from grupper where art = '" . $art[$section] . "' and fiscal_year = '" . (int) $regnaar . "' and coalesce(inaktiv, false) = false");
			case 'G6.2':
				$dir = __DIR__ . '/../logolib/' . (int) $db_id;
				return is_file($dir . '/fe_logo.png') || (bool) glob($dir . '/*.pdf');
			case 'G6.3':
				return $any("select id from settings where var_grp = 'email_settings' and var_name = 'sender_email' and coalesce(var_value, '') <> ''")
					|| $any("select id from adresser where art = 'S' and coalesce(email, '') <> ''");
			case 'G8':
				return $any("select id from brugere where status is distinct from false and id <> (select min(id) from brugere)");
		}
		return true;
	}

	/**
	 * Short status per group row: a dot plus text for a warning or error, plain text otherwise (§8.0).
	 *
	 * @param array<int, array<string, mixed>> $modules from settings_optional_modules()
	 * @return array<string, array{kind: string, text: string}> kind '', 'ok', 'warn' or 'err'
	 */
	function settings_group_status(array $groups, array $modules, array $attention, int $sprogId): array
	{
		global $regnaar;
		$st = array();
		$count = function (string $qtxt): int {
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			return $r ? (int) $r['antal'] : 0;
		};
		if (isset($groups['company']) && (int) $regnaar > 0) {
			$r = db_fetch_array(db_select("select beskrivelse from grupper where art = 'RA' and kodenr = '" . (int) $regnaar . "'", __FILE__ . " linje " . __LINE__));
			if ($r && trim((string) $r['beskrivelse']) !== '') {
				$st['company'] = array('kind' => '', 'text' => sprintf(findtekst('6037|%s aktivt', $sprogId), trim((string) $r['beskrivelse'])));
			}
		}
		if (isset($groups['finance']) && (int) $regnaar > 0) {
			$n = $count("select count(*) as antal from grupper where art in ('SM', 'KM', 'EM', 'YM') and fiscal_year = '" . (int) $regnaar . "'");
			if ($n > 0) {
				$st['finance'] = array('kind' => '', 'text' => sprintf(findtekst('6033|%s momskoder', $sprogId), $n));
			}
		}
		if (isset($groups['organisation'])) {
			$n = $count("select count(*) as antal from ansatte where konto_id in (select id from adresser where art = 'S') and (lukket is null or lukket <> 'on')");
			if ($n > 0) {
				$st['organisation'] = array('kind' => '', 'text' => sprintf(findtekst('6034|%s ansatte', $sprogId), $n));
			}
		}
		if (isset($groups['users'])) {
			$n = 0;
			$q = db_select("select status from brugere", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if (!in_array($r['status'], array('f', false, '0', 0), true)) {
					$n++;
				}
			}
			$st['users'] = array('kind' => '', 'text' => sprintf(findtekst('6036|%s brugere', $sprogId), $n));
		}
		foreach (array('sales' => 'G3.1', 'purchase' => 'G4.1', 'items' => 'G5.1') as $g => $section) {
			if (isset($groups[$g]) && !isset($attention[$g]) && !settings_section_complete($section)) {
				$st[$g] = array('kind' => 'warn', 'text' => findtekst('7096|Grundopsætning mangler', $sprogId));
			}
		}
		if (isset($groups['items']) && !isset($st['items'])) {
			$n = $count("select count(*) as antal from grupper where art = 'LG'");
			$fifo = db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '3'", __FILE__ . " linje " . __LINE__));
			$text = $n > 0 ? sprintf(findtekst('6035|%s lagre', $sprogId), $n) : '';
			if ($fifo && trim((string) $fifo['box6']) === 'on') {
				$text .= ($text !== '' ? ' · ' : '') . 'FIFO';
			}
			if ($text !== '') {
				$st['items'] = array('kind' => '', 'text' => $text);
			}
		}
		if (isset($groups['pos']) && (int) $regnaar > 0) {
			$r = db_fetch_array(db_select("select box1 from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '" . (int) $regnaar . "'", __FILE__ . " linje " . __LINE__));
			$tills = $r ? (int) $r['box1'] : 0;
			if ($tills > 0) {
				$st['pos'] = array('kind' => '', 'text' => ($tills === 1) ? findtekst('5982|1 kasse', $sprogId) : sprintf(findtekst('5978|%s kasser', $sprogId), $tills));
			}
		}
		if (isset($groups['integrations'])) {
			$active = 0;
			foreach ($modules as $m) {
				if ($m['group'] === 'integrations' && $m['active']) {
					$active++;
				}
			}
			if (isset($attention['integrations'])) {
				$st['integrations'] = array('kind' => 'err', 'text' => sprintf(findtekst('6038|%s fejl', $sprogId), 1));
			} elseif ($active > 0) {
				$st['integrations'] = array('kind' => '', 'text' => sprintf(findtekst('5980|%s aktive', $sprogId), $active));
			}
		}
		if (isset($attention['documents'])) {
			$st['documents'] = array('kind' => 'warn', 'text' => findtekst('6027|Mangler afsender', $sprogId));
		}
		return $st;
	}
}

if (!function_exists('getSettingsGroups')) {
	/**
	 * The groups of the settings front page (parent spec S1), in display order.
	 *
	 * @return array<string, array{label: string, description: string, icon: string, permission: string}>
	 */
	function getSettingsGroups(): array
	{
		return array(
			'company'       => array('label' => '5669|Virksomhed',          'description' => '5653|Stamdata, regnskabsår, kontoindstillinger og sprog',           'icon' => 'bx-buildings',  'permission' => 'settings.company'),
			'finance'       => array('label' => '600|Finans',               'description' => '5672|Kontoplan, moms, valuta, bilag og bank',                      'icon' => 'bx-coin-stack', 'permission' => 'settings.finance'),
			'sales'         => array('label' => '5544|Salg',                'description' => '5673|Debitorgrupper, ordrer, rykkere og rabatter',                 'icon' => 'bx-cart',       'permission' => 'settings.sales'),
			'purchase'      => array('label' => '1012|Køb',                 'description' => '5674|Kreditorgrupper og leverandørprislister',                     'icon' => 'bx-archive-in', 'permission' => 'settings.purchase'),
			'items'         => array('label' => '5650|Varer & lager',       'description' => '5657|Varegrupper, enheder, varianter, lagre og mærkater',           'icon' => 'bx-package',    'permission' => 'settings.items'),
			'documents'     => array('label' => '5671|Dokumenter & e-mail', 'description' => '5675|Formularer, e-mail og afsendere',                             'icon' => 'bx-file',       'permission' => 'settings.documents'),
			'organisation'  => array('label' => '5670|Organisation',        'description' => '5658|Ansatte, afdelinger, projekter og provision',                 'icon' => 'bx-id-card',    'permission' => 'settings.organisation'),
			'users'         => array('label' => '5536|Brugere & roller',    'description' => '5659|Brugere, roller, rettigheder og log',                         'icon' => 'bx-group',      'permission' => 'settings.users.manage'),
			'integrations'  => array('label' => '5537|Integrationer',       'description' => '5676|API, webshop, Stripe og app',                                 'icon' => 'bx-plug',       'permission' => 'settings.integrations'),
			'pos'           => array('label' => '2226|Kasse',               'description' => '5661|Kassevalg og kassemenuer',                                    'icon' => 'bx-store-alt',  'permission' => 'settings.pos'),
			'import_export' => array('label' => '5539|Import & eksport',    'description' => '5662|Ind- og udlæsning af kontoplan, kunder, varer og formularer', 'icon' => 'bx-transfer',   'permission' => 'settings.import_export'),
		);
	}

	function settings_group_permission(string $group): string
	{
		$groups = getSettingsGroups();
		return isset($groups[$group]) ? $groups[$group]['permission'] : '';
	}

	/**
	 * Registry entries whose url points at this script and whose query parameters all
	 * match the request (syssetup.php?valg=moms). Most specific matches first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function settings_entries_for_request(string $script, array $get): array
	{
		$matches = array();
		foreach (getSettingsRegistry() as $entry) {
			if (empty($entry['group']) || $entry['group'] === 'personal') {
				continue;
			}
			$parts = parse_url($entry['url']);
			if (!isset($parts['path']) || basename($parts['path']) !== $script) {
				continue;
			}
			$params = array();
			if (isset($parts['query'])) {
				parse_str($parts['query'], $params);
			}
			$ok = true;
			foreach ($params as $name => $value) {
				$actual = isset($get[$name]) ? (string) $get[$name] : '';
				// syssetup.php without valg shows the VAT page.
				if ($actual === '' && $script === 'syssetup.php' && $name === 'valg') {
					$actual = 'moms';
				}
				if ($actual !== (string) $value) {
					$ok = false;
				}
			}
			if ($ok) {
				$entry['_specificity'] = count($params);
				$matches[] = $entry;
			}
		}
		usort($matches, function ($a, $b) { return $b['_specificity'] - $a['_specificity']; });
		if ($matches) {
			$top = $matches[0]['_specificity'];
			$matches = array_values(array_filter($matches, function ($e) use ($top) { return $e['_specificity'] === $top; }));
		}
		return $matches;
	}

	/**
	 * Company-level visibility (reseller-only pages, PoS module, master database).
	 */
	function settings_entry_available(array $entry): bool
	{
		global $revisorregnskab, $forhandlerregnskab;
		if (!empty($entry['requiresReseller']) && !($revisorregnskab || $forhandlerregnskab)) {
			return false;
		}
		if (!empty($entry['visibilityRule'])) {
			switch ($entry['visibilityRule']) {
				case 'posModule':
					if (!settings_has_module('pos')) {
						return false;
					}
					break;
				case 'masterDb':
					// admin/admin_settings.php only opens in the master database (admin panel);
					// inside a company it logs the user out, so it is never listed there.
					global $db, $sqdb;
					if (!isset($db) || !isset($sqdb) || $db !== $sqdb) {
						return false;
					}
					break;
				case 'hosted':
					// G1.4 exists only on Saldi's own servers.
					if (!settings_has_module('hosted')) {
						return false;
					}
					break;
				case 'bankFeature':
					if (!settings_feature_enabled('bank')) {
						return false;
					}
					break;
			}
		}
		return true;
	}

	/**
	 * Groups the current user may open, each with the entries available in this company.
	 *
	 * @return array<string, array{def: array<string, string>, entries: array<int, array<string, mixed>>}>
	 */
	function settings_accessible_groups(): array
	{
		$out = array();
		foreach (getSettingsGroups() as $group => $def) {
			$groupOpen = !function_exists('perm_can') || perm_can($def['permission'], 'read');
			$entries = array();
			foreach (getSettingsRegistry() as $entry) {
				if (!isset($entry['group']) || $entry['group'] !== $group || !settings_entry_available($entry)) {
					continue;
				}
				// An entry with its own key (backup) is shown to whoever holds that key, whatever the group's.
				$open = isset($entry['permission']) ? (!function_exists('perm_can') || perm_can($entry['permission'], 'read')) : $groupOpen;
				if ($open) {
					$entries[] = $entry;
				}
			}
			if ($entries) {
				$out[$group] = array('def' => $def, 'entries' => $entries);
			}
		}
		return $out;
	}

	/**
	 * A registry url (relative to systemdata/) as the path the shell's update_iframe() takes.
	 */
	function settings_shell_path(string $url): string
	{
		if (strpos($url, '../') === 0) {
			return '/' . substr($url, 3);
		}
		return '/systemdata/' . $url;
	}

	/**
	 * The trail the shell shows in the left side of the topbar on settings pages (settings redesign §8.0):
	 * Indstillinger / group / current page. The shell puts the company name in front.
	 *
	 * @return array<int, array{label: string, url: string}> url '' for the current page
	 */
	function settings_breadcrumb(string $group, string $current, int $sprogId): array
	{
		$trail = array(array('label' => findtekst('122|Indstillinger', $sprogId), 'url' => ($group === '' && $current === '') ? '' : '/systemdata/settings.php'));
		if ($group !== '') {
			$groups = settings_accessible_groups();
			if (isset($groups[$group])) {
				$label = findtekst($groups[$group]['def']['label'], $sprogId);
				$trail[] = array('label' => $label, 'url' => $current === '' ? '' : settings_shell_path((string) $groups[$group]['entries'][0]['url']));
			}
		}
		if ($current !== '') {
			$trail[] = array('label' => $current, 'url' => '');
		}
		return $trail;
	}

	/**
	 * The settings trail through the shared page breadcrumb (includes/stdFunc/pageChrome.php, topbar addendum
	 * 2026-10-05 §4): one mechanism for every page. $back is the page a module's gear came from.
	 */
	function settings_breadcrumb_script(array $trail, string $charset = 'UTF-8', string $back = '', string $backLabel = ''): string
	{
		if (!function_exists('page_breadcrumb')) {
			include_once(__DIR__ . '/../includes/stdFunc/pageChrome.php');
		}
		$levels = array();
		foreach ($trail as $crumb) {
			$levels[] = array('label' => (string) $crumb['label'], 'href' => (string) $crumb['url']);
		}
		$backChip = null;
		if ($back !== '') {
			$backChip = array('label' => $backLabel !== '' ? $backLabel : page_label_for($back), 'href' => $back);
		}
		return page_breadcrumb($levels, null, $backChip, $charset);
	}

	/**
	 * Display label of an entry in the current language.
	 */
	function settings_entry_label(array $entry, int $sprogId): string
	{
		if (isset($entry['textId'])) {
			return findtekst((string) $entry['textId'], $sprogId);
		}
		if ($sprogId == 3 && !empty($entry['labelNo'])) {
			return $entry['labelNo'];
		}
		if ($sprogId != 1 && !empty($entry['labelEn'])) {
			return $entry['labelEn'];
		}
		return $entry['labelDa'];
	}
}
?>
