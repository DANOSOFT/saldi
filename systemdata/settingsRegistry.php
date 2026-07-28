<?php
// ----------------systemdata/settingsRegistry.php --- Settings search Phase 1 --- 2026-07-09 ----
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
// 20260709 SZ Created: hand-maintained registry of Settings pages/keywords for search
// 20260710 SZ Expanded keywords (deep page content, DA/EN/NO, sys_div_func.php-derived terms)
// 20260721 Sawaneh Added Opgaveliste/Brug jobkort (task list) search terms to div_valg entry
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
//   visibilityRule    null | 'posModule' | 'docubizz' - re-checked live in settingsSearch.php
//   keywords         array of extra search terms describing what's actually configurable on that
//                    page (field names, synonyms, abbreviations, DA/EN/NO) - matched when the query
//                    doesn't hit the label itself, so e.g. searching "auditor" finds "Brugere"
//                    (the "Revisor"/Auditor checkbox lives there), "order layout" finds "Formularer",
//                    or "påminnelse" (Norwegian for "Rykker"/reminder) also finds "Formularer".
//                    Hand-transcribed from each page's real field labels (cross-checked against
//                    importfiler/tekster.csv, which has a Dansk/English/Norsk column for every id
//                    used here) - keep it that way; don't invent settings that aren't actually on
//                    the page, and don't invent translations that aren't already in tekster.csv.

if (!function_exists('getSettingsRegistry')) {
	function getSettingsRegistry() {
		return array(
			// -- systemdata/left_menu.php / top.php sidebar (the main Settings list) --
			array('key' => 'moms',            'url' => 'syssetup.php?valg=moms',       'category' => 'tax',      'textId' => 770,
				'keywords' => array('vat','vat rate','vat percentage','sales tax','purchase tax','output vat','input vat','tax code','skat','reverse charge','eu vat','vat report','tax report','vat rounding','moms','momssats','momsprocent','udgående moms','indgående moms','momskonto','moms af varekøb','moms af ydelseskøb','momsrapport','momskode','mva','merverdiavgift','mva-sats')),
			array('key' => 'debitor_grupper',  'url' => 'syssetup.php?valg=debitor',    'category' => 'groups',  'textId' => 771,
				'keywords' => array('debtor','creditor','customer groups','supplier groups','vendor groups','vat group','collective account','summary account','samlekonto','counter account','offset account','modkonto','commission percentage','b2b price','reverse charge liability','invoice language','kreditorgrupper','debitorgrupper')),
			array('key' => 'afdelinger',       'url' => 'syssetup.php?valg=afdelinger', 'category' => 'org',     'textId' => 772,
				'keywords' => array('department','departments','cost center','branch','store location','afdeling','afdelinger','formularnote','avdeling','avdelinger')),
			array('key' => 'projekter',        'url' => 'syssetup.php?valg=projekter',  'category' => 'org',     'textId' => 773,
				'keywords' => array('project','projects','project number','project code','job code','projektnummer','projekter','prosjekt','prosjekter')),
			array('key' => 'lagre',            'url' => 'syssetup.php?valg=lagre',      'category' => 'stock',   'textId' => 608,
				'keywords' => array('warehouse','warehouses','stock location','storage location','inventory location','lager','lagre','lagerlokation')),
			array('key' => 'varegrupper',      'url' => 'syssetup.php?valg=varer',      'category' => 'groups',  'textId' => 774,
				'keywords' => array('product groups','item groups','price groups','cost price','sales price','recommended price','retail price','b2b price','campaign groups','special offer price','offer price','discount groups','batch','reverse charge','vat per product group','varegrupper','prisgrupper','tilbudsgrupper','rabatgrupper','kampagnepris','kostpris','salgspris','vejledende pris')),
			array('key' => 'rabatgrupper',     'url' => 'rabatgrupper.php',             'category' => 'groups',  'textId' => 775,
				'keywords' => array('customer discount matrix','debtor discount group','product discount group','discount matrix','percent discount','amount discount per unit','kr/stk rabat','discount by customer and product group','debitor rabatgruppe','vare rabatgruppe','rabatgrupper','rabat','rabatt')),
			array('key' => 'valuta',           'url' => 'valuta.php',                   'category' => 'finance', 'textId' => 776,
				'keywords' => array('currency','currencies','exchange rate','currency code','currency rate','pos currency','valuta','valutakode','kurs')),
			array('key' => 'brugere',          'url' => 'brugere.php',                  'category' => 'users',   'textId' => 777,
				'keywords' => array('user','users','user permissions','access rights','user rights','password','change password','two factor authentication','2fa','sms code','auditor','accountant','revisor','revisoradgang','employee link','ip address restriction','allowed ip','user roles','delete user','add user','new user','brugernavn','rettigheder','adgangskode','brukere','brukernavn','passord','tilgangsrettigheter')),
			array('key' => 'regnskabsaar',     'url' => 'regnskabsaar.php',             'category' => 'finance', 'textId' => 778,
				'keywords' => array('fiscal year','financial year','accounting year','start month','end month','close year','closed year','delete fiscal year','active fiscal year','set active year','create fiscal year','regnskabsår','regnskapsår')),
			array('key' => 'stamkort',         'url' => 'stamkort.php',                 'category' => 'company', 'textId' => 779,
				'keywords' => array('company info','company profile','company name','company address','vat number','tax id','cvr number','bank details','bank account','gdpr agreement','data processing agreement','contact person','phone number','mobile number','employee list','firmanavn','bankoplysninger','databehandleraftale','kontaktperson')),
			array('key' => 'ansatte',          'url' => 'ansatte.php',                  'category' => 'company', 'textId' => 1262,
				'keywords' => array('employee record','staff record','new employee','edit employee','employee number','employee name','employee address','employee email','employee phone','employee mobile','salary','payroll','extra salary','cpr number','social security number','initials','pos code','employee department','employee background','employee language','employee bank account','employee notes','employee start date','employee end date','terminate employee','close employee','ansatte','løn','cprnr','initialer','startdato','slutdato','lønn')),
			array('key' => 'formularer',       'url' => 'formularkort.php?valg=formularer', 'category' => 'documents', 'textId' => 780,
				'keywords' => array('order layout','order confirmation layout','invoice layout','invoice template','invoice design','quote layout','offer layout','credit note layout','packing slip layout','delivery note layout','reminder letter template','dunning letter','pick list layout','picking list layout','requisition layout','purchase order layout','purchase invoice layout','account card layout','document template','form editor','form design','logo position','logo upload','print layout','template design','background name','ordrebekræftelse layout','fakturadesign','tilbud skabelon','rykker skabelon','følgeseddel layout','plukliste layout','kontokort layout','reminder fee','interest rate on reminders','mail text for invoice','email text template','move text position','text position on form','line and border design','font size on form',
					'skjemaer','ordrebekreftelse','kredittnota','påminnelse','påminnelsesmal','plukkliste','kjøpsforslag','rekvisisjon','kjøpsfaktura','bestillingslinjer','e-post tekst')),
			array('key' => 'enheder',          'url' => 'enheder.php',                  'category' => 'products', 'textId' => 781,
				'keywords' => array('unit','units','unit of measure','measurement unit','material','materials','material density','weight calculation','enhed','enheder','materiale','enheter')),

			// -- systemdata/diverse.php sub-sections (each independently searchable) --
			array('key' => 'diverse_overview',      'url' => 'diverse.php',                              'category' => 'diverse', 'textId' => 782,
				'keywords' => array('miscellaneous settings','other settings','diverse indstillinger')),
			array('key' => 'kontoindstillinger',    'url' => 'diverse.php?sektion=kontoindstillinger',   'category' => 'diverse', 'textId' => 783,
				'keywords' => array('account settings','company settings','system settings','rename account','rename company','company name change','base currency','system currency','timezone','time zone','max users','user limit','number of users','reset account data','wipe all data','delete account','close account','terminate account','smtp settings','mail server settings','email server settings','regnskabsnavn','tidszone','nulstil regnskab','slet regnskab','antal brugere','kontoinnstillinger',
					'sort by phone number','postings last 12 months','alternative smtp server','smtp port','smtp username','smtp password','smtp encryption','keep customers and suppliers on reset','keep products on reset','reset account confirmation','backup before reset warning','5 year backup retention','bookkeeping law backup')),
			array('key' => 'provision',             'url' => 'diverse.php?sektion=provision',            'category' => 'diverse', 'textId' => 784,
				'keywords' => array('commission report settings','commission calculation','sales commission','provisionsrapport','provision','provisjonsberegning','provisjon',
					'commission basis','invoiced or paid commission','commission source person','customer responsible person commission','reference person commission','cost price source for commission','purchase price commission','product card cost price commission','cutoff date commission calculation')),
			array('key' => 'userSettings',          'url' => 'diverse.php?sektion=userSettings',         'category' => 'personal', 'textId' => 785,
				'keywords' => array('personal settings','my settings','profile settings','appearance settings','theme color','button color','background color','text color','menu design','sidebar design','popup window mode','ui color customization','user interface preferences','personlige valg','baggrundsfarve','knapfarve','skift menu design',
					'highlight color','order highlighting','use popup windows','use top menu','classic layout','classic look','full page workspace','no side menu','background color hex code','browser popup settings','highlight color nuance','red green blue yellow magenta cyan')),
			array('key' => 'ordre_valg',             'url' => 'diverse.php?sektion=ordre_valg',           'category' => 'diverse', 'textId' => 786,
				'keywords' => array('order settings','order options','vat on orders','show vat private customers','show vat business customers','negative stock','allow negative stock','low stock warning','out of stock warning','fifo costing','cost method','quick invoicing','immediate posting','same day posting','discount item number','delivery note text','packing slip text','shipping item number','freight item number','postage item','pick list email','send pick list by mail','gs1 barcode scanning','barcode parsing','order autocomplete','search autocomplete orders','lock invoice until paid','ipad system','ordrerelaterede valg','hurtigfaktura','negativt lager','rabatvarenummer','bestillingsrelaterte valg','bestilling',
					'automatic cost price adjustment','average cost price','replacement cost price','update cost prices button','packing slip comments','quantity only on packing slip','total price bundle discount','percentage invoicing','rental percentage invoicing','percentage surcharge','item number for surcharge','cash sale account number','credit card sale account number','internal order note','debtor ipad self email','discount decimals on orders','immediate posting purchase orders','immediate posting sales orders','item number for set bundle')),
			array('key' => 'productOptions',        'url' => 'diverse.php?sektion=productOptions',       'category' => 'products', 'textId' => 787,
				'keywords' => array('product options','vat on product card','show prices with vat','confirm description change','confirm stock change','consignment sales','commission sales','used goods commission','commission percentage','commission account','minimum stock level','reorder level','low stock threshold','stock status email','stock status report','email frequency stock','varerelaterede valg','kommissionsvarer','minimumsbeholdning','lagerstatus mail','lagerstatus rapport','varerelaterte valg','kommisjonsvarer','provisjonssalg','minimumsbeholdning av varer','lagerstatusrapporter','mva på varekort')),
			array('key' => 'variant_valg',           'url' => 'diverse.php?sektion=variant_valg',         'category' => 'products', 'textId' => 788,
				'keywords' => array('product variants','variant types','variant values','color variant','size variant','import variants','import variant types','import variant values','csv import variants','variantrelaterede valg','varianter','variasjonsrelaterte valg',
					'webshop selection','internal webshop','external webshop','no webshop','webshop url','fetch products from shop','shop character encoding','quickpay merchant number','quickpay agreement id','quickpay md5 secret')),
			array('key' => 'api_valg',              'url' => 'diverse.php?sektion=api_valg',             'category' => 'integrations', 'textId' => 790,
				'keywords' => array('api settings','api key','api access','ip whitelist','allowed ip addresses','external integration','import file path','api bruger','api nøgle',
					'saldi db variable','saldi url variable','api client url','api reference user','update from shop','fetch new products from shop')),
			array('key' => 'labels',                'url' => 'diverse.php?sektion=labels',               'category' => 'documents', 'textId' => 791,
				'keywords' => array('label printer template','price tag design','price label','barcode label layout','product label template','sticker template','label size','label width and height','label columns and rows','label font size','label margins','show item number on label','show barcode on label','mærkater','label editor','vareetiket','klistremerker','skriftstørrelse',
					'dymo','dymo 11354','brother printer','brother 22606','label print html code','product card label html','a4 label sheet','simple labels','label templates')),
			array('key' => 'pricelists',            'url' => 'diverse.php?sektion=pricelists',           'category' => 'pricing', 'textId' => 792,
				'keywords' => array('price list import','supplier price list','vendor price list','csv price list','csv delimiter','csv encoding','vendor price feed','product price import','price file url','purchase price list','add pricelist url','supplier group pricelist','product group pricelist','prisliste import','prisfil',
					'price list file type','price list discount','supplier discount','vvs price list','plumbing price list','solar vvs','active price list toggle','delete price list reference')),
			array('key' => 'rykker_valg',           'url' => 'diverse.php?sektion=rykker_valg',          'category' => 'invoicing', 'textId' => 793,
				'keywords' => array('reminder settings','dunning settings','debt collection','collection agency','debt collector','inkasso','reminder responsible user','person responsible for reminders','rykkerrelaterede valg','rykker','påminnelsesrelaterte valg','påminnelse',
					'reminder responsible email','interest rate per month reminder','reminder 1 deadline days','reminder 2 deadline days','reminder 3 deadline days','collection lawyer account number','collection attorney')),
			array('key' => 'div_valg',               'url' => 'diverse.php?sektion=div_valg',             'category' => 'diverse', 'textId' => 794,
				'keywords' => array('shipping integration','carrier integration','freight integration','gls','bring','dfm','mobilepay','mobilepay webhook','copayone','quickpay','nemhandel','e-invoicing','electronic invoicing','vibrant','paperflow','scan invoices ocr','payment gateway','payment days','default payment terms','label size mysale','vat on orders private customers','vat on orders business customers','pickup address','multiple pickup addresses','fragtintegration','betalingsdage','afhentningsadresse',
					'mysale','customer sales portal','salesperson self service','commission self service portal','let customers see own sales','jobkort','brug jobkort','use job cards','opgaveliste','task list','oppgaveliste','bruk jobbkort','task list under debtor accounts','job card system','work order tracking','payment list toggle','show payment list debitor creditor','betalingsliste','customer phone on new order','different dates on order','extra employee on order','enable docubizz','docubizz toggle',
					'mandatory debtor group on debtor card','mandatory customer responsible on debtor card','extra fields on employee card','payment lists erh bank format','debtor account as order phone','docubizz scanned documents application','activate mysale flea market','max label character length','use jobkort task descriptions','direct print to local printer','html css form generation','different dates same voucher cash journal','collection agency account number','use paperflow ocr','paperflow id','paperflow bearer token','ebconnect integration','oioubl e-invoice','gls id','gls username','gls contact id','gls password','danske fragtmænd','dfm agreement number','dfm hub code','dfm api url','dfm clientid','dfm api username','dfm api password','default shipping type danske fragtmænd','default goods type danske fragtmænd','default payment method danske fragtmænd','default delivery method danske fragtmænd','pickup address different from main address','pickup company name','pickup zip code and city','order button name')),
			array('key' => 'tjekliste',             'url' => 'diverse.php?sektion=tjekliste',            'category' => 'diverse', 'textId' => 796,
				'keywords' => array('checklist','checklists','case checklist','task list','workflow phases','case phases','sagsstyring tjekliste','tjekpunkt','sjekkliste','sjekklister','new check group','new checklist')),
			array('key' => 'docubizz',              'url' => 'diverse.php?sektion=docubizz',             'category' => 'integrations', 'labelDa' => 'Docubizz integration', 'labelEn' => 'Docubizz integration', 'labelNo' => 'Docubizz-integrasjon', 'visibilityRule' => 'docubizz',
				'keywords' => array('docubizz','bookkeeping export','accounting bureau data exchange','ftp export accounting','outsourced bookkeeping')),
			array('key' => 'bilag',                 'url' => 'diverse.php?sektion=bilag',                'category' => 'documents', 'textId' => 797,
				'keywords' => array('attachment storage','receipt storage','document storage settings','ftp storage for attachments','internal storage','external storage','cloud storage for receipts','scan receipts by email','bilag ftp','bilagshåndtering','dokumenthåndtering',
					'scanned receipts storage','store documents per gb per month','receipt email inbox address','own ftp server for documents','google docs viewer','ftp server name or ip','ftp username and password for documents','ftp folder for receipts','no storage option')),
			array('key' => 'orediff',               'url' => 'diverse.php?sektion=orediff',              'category' => 'finance', 'textId' => 170,
				'keywords' => array('rounding difference account','penny difference','cash rounding','rounding account','øredifferencer','øreforskjeller')),
			array('key' => 'massefakt',             'url' => 'diverse.php?sektion=massefakt',            'category' => 'invoicing', 'textId' => 200,
				'keywords' => array('mass invoicing','batch invoicing','consolidated invoicing','partial delivery','delivery deadline days','massefakturering','dellevering')),
			array('key' => 'posOptions',            'url' => 'diverse.php?sektion=posOptions',           'category' => 'pos', 'textId' => 271, 'visibilityRule' => 'posModule',
				'keywords' => array('pos settings','cash register settings','point of sale options','number of cash registers','number of card terminals','card payment accounts','cash accounts','department per register','vat group cash customers','discount item cash sale','receipt printing','print receipt automatically','disable receipt printing','bon print','cash drawer','opening float','starting cash amount','cash count assistance','coins and banknotes','interim account','cash difference account','table selection','restaurant table','number of tables','table name','font size pos','gift card numbers','gift card text','voucher numbers','active gift card','post each trade immediately','post immediately to finance','printer ip','receipt printer ip','card terminal ip','card terminal type','flatpay','move3500','lane3000','vibrant terminal','ip baseret terminal','payment terminal type','other payment cards','kitchen printer ip','mobile pos','screen width','zoom level','flip menu','reverse primary secondary menu','cash on amount button','account lookup button','deposit button','forced user selection','clerk selection before checkout','customer display','bundle price','set price','jump to price field','show stock in pos','show inventory in pos','larger order total','print timeout','kasseantal','kortkonti','kassekonti','kortterminal','køkkenprinter','kasseprimo',
					'kassaapparat','avdeling','mva-gruppe','kredittkort','skriverens ip','kjøkken ip','terminaltype','kontantsaldo','kundedisplay','tvunget brukervalg','tabellvalg','antall bord')),
			array('key' => 'bank_integration',      'url' => 'diverse.php?sektion=bank_integration',     'category' => 'integrations', 'labelDa' => 'Bank Integration', 'labelEn' => 'Bank integration', 'labelNo' => 'Bankintegrasjon',
				'keywords' => array('bank feed','bank transaction import','bank statement import','show bank status','show status kassekladde','default date range bank import','date method','last quarter','this quarter','bank connection status')),
			array('key' => 'barcodescan',           'url' => 'barcodescan.php',                          'category' => 'pos', 'labelDa' => 'App Barcode', 'labelEn' => 'Barcode scanning app', 'labelNo' => 'App-strekkode',
				'keywords' => array('qr code login','app login','mobile app authentication','one time access qr','saldi app login','scan to login')),
			array('key' => 'sprog',                 'url' => 'diverse.php?sektion=sprog',                'category' => 'company', 'textId' => 801,
				'keywords' => array('language','languages','change language','select language','preferred language','edit translation texts','ui language','current language','sprogindstillinger','sprog','språk','språkinnstillinger')),
			array('key' => 'div_io',                'url' => 'diverse.php?sektion=div_io',               'category' => 'data', 'textId' => 802,
				'keywords' => array('import export','chart of accounts import export','customer import export','product import export','form import export','sql query tool','data import','data export','solar vvs import','kontoplan import','debitor import','varer import','formular import')),

			// -- Scattered elsewhere in the app --
			array('key' => 'admin_settings',    'url' => '../admin/admin_settings.php',      'category' => 'system',  'textId' => 613, 'requiresReseller' => true,
				'keywords' => array('pdf conversion tools','weasyprint','pdftk','ps2pdf','ftp tool path','database dump tool','backup tool path','zip unzip tar path','system alert text','dashboard news snippet','system tools')),
			array('key' => 'email_settings',    'url' => 'email_settings.php',                'category' => 'documents', 'labelDa' => 'Email Indstillinger', 'labelEn' => 'Email settings', 'labelNo' => 'E-postinnstillinger',
				'keywords' => array('sender email','sender name','email from address','invoice email sender','background specific email settings','afsender email','afsender navn')),
			array('key' => 'betalinger_settings', 'url' => '../debitor/betalinger_settings.php', 'category' => 'finance', 'labelDa' => 'Betalingsindstillinger', 'labelEn' => 'Payment settings', 'labelNo' => 'Betalingsinnstillinger',
				'keywords' => array('payment terms','credit terms','payment due days','default payment days','invoice due date settings','betalingsfrist','betalingsdato')),
			array('key' => 'rental_settings',   'url' => '../rental/settings.php',            'category' => 'rental', 'labelDa' => 'Udlejningsindstillinger', 'labelEn' => 'Rental settings', 'labelNo' => 'Utleieinnstillinger',
				'keywords' => array('rental booking settings','booking format','date or timeslot booking','customer search fields','move in day','move out day','delete confirmation popup','combine consecutive bookings','automatic order creation','rental invoice date','password protect settings','week helper date picker')),
		);
	}
}
?>
