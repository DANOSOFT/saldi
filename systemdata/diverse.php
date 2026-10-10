<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverse.php -----patch 4.1.1 ----2026-09-29------------
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 2012.09.20 Tilføjet integration med ebconnect
// 2013.01.19 funktioner lagt i selvstændig fil (../includes/sys_div_func.php)
// 2013.05.23 varelaterede rettet til varerelaterede valg.
// 2013.12.10	Tilføjet valg om kort er betalingskort som aktiver betalingsterminal. Søg 20131210
// 2013.12.13	Tilføjet "intern" bilagsopbevaring (box6 under ftp)
// 2014.01.29	Tilføjet valg til automatisk genkendelse af betalingskort (kun ved integreret betalingsterminal) Søg 20140129
// 2014.04.29	Ændret teksten så siden er mere overskuelig. Claus Agerskov ca@saldi.dk
// 2014.05.08	Tilføjet valg til bordhåndtering under pos_valg Søg 20140508
// 2014.06.16 Tilføjet mellemkonto til pos kasser. Søg mellemkonto.
// 2014.07.01	FTP ændret til bilag og intern bilagsopbevaring flyttet til owncloud
// 2015.04.11	Tilføjet labelprint.Søg label
// 20150424 CA  Ændret link til funktionsfilen sys_div_func.php    Søg 20150424a
// 20150424 CA  Ændret link til funktionsfilen konv_lager.php      Søg 20150424b
// 20150424 CA  Benytter funktionen skriv_formtabel til formularer Søg 20150424c
// 20150612 CA  Databasehåndtering af prislister (ej afsluttet)    Søg 20150612
// 20150907 PHR Sætpriser tilføjet under ordre_valg, Søg 20150907 & $saetvareid
// 20151006 PHR Labelprint ændret fra php til html og kontrol for php indsat.
// 20160116 PHR Indsat kontrol for ftp adgang v ebconnect integration
// 20160412 PHR Opdelt vare_valg i vare_valg, labels & shop_valg
// 20160601	PHR SMTP kan nu anvendes med brugernavn, adgangskode og kryptering.
// 20161118	PHR	Tilføjet default bord som option for kasse i funktion pos_valg. Søg bordvalg
// 20161125 PHR Indført html som formulargenerator som alternativ til postscript i funktion div_. Søg pv_box3
// 20170123 PHR Tilføjet API_valg
// 20170314 PHR POS Valg - tilføjet mulighed for at sætte 'udtages fra kasse' til 0 som default.
// 20170404 PHR ordre_valg - Straksbogfør skelner nu mellem debitor og kreditorordrer. Dvs debitor;kreditor - Søg # 20170404
// 20170731 PHR Tilføjet 'Nulstil regnskab - 20170731
// 20171009 PHR Tilføjet pos_font_size under pos_valg.
// 20181029 CA  Tilføjet voucher og tilgodehavende tilknyttet id  søg 20181029
// 20181126 PHR	Variant_valg lagt i egen funktion.
// 20181126 PHR	Tilvalg - Marker vare som udgået når beholdning går i minus (vare_valg). Søg DisItemIfNeg
// 20181129 PHR	Tilføjet mulighed for at sætte tidszone i regnskabet.
// 20181216 PHR	Tilføjet 'card_enabled' på betalingskort (Pos_valg) og mulighed for ændring af rækkefølge. Søg '$card_enabled'
// 20190107 PHR	Tilføjet 'change_cardvalue' på betalingskort (Pos_valg) og mulighed for ændring af rækkefølge. Søg '$change_cardvalue'
// 20190129 PHR	(vare_valg) Changed 'Momskode for salgspriser på varekort' to 'Vis priser med moms på varekort'. Search '$vatOnItemCard'
// 20190225 MSC Rettet topmenu design og isset fejl
// 20190322 LN Added tables to be deleted when pressing the "Nulstil" button
// 20190411 LN Call funtion in chooseProvision.php to save default provision value
// 20190421 PHR Added confirmDescriptionChange, in 'vare_valg'
// 20190614 LN Added argument to the function saveProvisionForItemGroup -> $defaultProvision
// 20200827 PHR Added shop_varer & shop_addresser to truncate in 'Nulstil'
// 20190921 PHR Added confirmStockChange, in 'vare_valg'
// 20201128 PHR Added labelType in.  'Label'
// 20210110 PHR Section Vare_valg. Added commission. 
// 20210213 PHR Some cleanup
// 20210303 CA  Added reservation of consignment for Danske Fragtmænd - search dfm_
// 20210305 CA  Added the selection to use debtor number as phone number in orders - search debtor2orderphone
// 20210312 PHR Changed intern_ftp til internFTP
// 20210410 PHR Correction of minor error in alertcondition in 'vare_valg'.
// 20210513 LOE	These texts were translated but not entered here previously
// 20210801 CA  Added the selection to use order notes in ordre_valg - search orderNoteEnabled
// 20210802 LOE Translated the remaining alert texts
// 20211123 PHR added paperflow
// 20211123 PHR added paperflowId & paperflowBearer
// 20220514 PHR mailText is now removed when account is reset.  
// 20221231 PHR	sektion 'bilag' box3 (ftp passwd) is now urlencoded as it failed with special characters in password. 
// 20231228 PBLM Added mobilePay (diverse valg)
// 20240126 PBLM Added nemhandel (diverse valg)
// 20240827 LOE 'personlige_valg' readujsted to use userSettings.
// 20241220 LOE Initialized tenantID and $key to null if not set
// 20250105 PBLM Added a second file to api_valg
// 20250414 LOE Updated barcodescan location and updated some variables
// 20250513 Sawaneh add max user update in kontoindstillinger()
// 20250526 PHR 'nyt_navn' changed to 'newName'
// 20251124 PHR	modified 'betalingslister' to choose between none / debitor / kreditor / both
// 20260223 Sawaneh SD-335 added buttonname field to DFM pickup addresses
// 20260304 Sawaneh SD-369 fixed- API URL instead of duplicate Danske Fragtmænd agreement number
// 20260306 Sawaneh - Added Simple guides feature: sidebar overlay with hardcoded Finance + Scaffolding PDF links
// 20260326 Sawaneh -Added ourRefStockSwitch setting
// 20260708 NTR - Changed how we convert id1 to a int, to avoid a fatal error.
// 20260709 Sawaneh Save "Show both delivery address and Extra fields on open orders" setting (showBothAddrExtra)
// 20260710 SZ Added Settings search box (settingsSearch.php/.js/.css)
// 20260818 CL/LH Use stable Stripe settings include paths.
// 20260819 CL/NTR Label saving routed through saveLabelText() so 'Standard' also reaches the labels
//                 table that lager/labelprint.php prints from, and new labels get account_id 0.
// 20260824 CL/NTR Label deletion only removes global rows (account_id 0 or null), matching what the
//                 label editor shows.
// 20260811 Sawaneh Save 'batchExpiryEnabled' setting (batch/expiry date section on the item card)
// 20260826 CL/SZ  saveLabel now refuses to save when the label's current template isn't reproducible
//                 by the visual editor's field model - it was silently discarding formatting it
//                 doesn't understand on every save (MB-18).
// 20260915 CL/NTR Bank Integration settings button only shown when the API credentials
//                 are configured (bankIntegrationEnabled()).
// 20260916 CDX/PHR Reset additional account data and skip tables absent from the installed schema.
// 20260916 CDX/PHR Set users and active sessions to financial year 1 after reset.
// 20260917 CDX/PHR Keep settings usable when the optional bank integration helper is absent.
// 20260917 CL/LH Report a failed account reset as a message instead of an uncaught error page.
// 20260916 Sawaneh Personal settings moved to systemdata/personalSettings.php; sektion=userSettings
//                  now redirects there and the Diverse menu entry links to the new page.
// 20260916 Sawaneh Phase 3: SQL tool, API, SMTP/e-mail, integrations and import/export sections
//                  gated by their own permission keys via require_permission().
// 20261006 Sawaneh Varianter (G5.3) moved to settingsSection.php?s=items.variants: variant_valg redirects (its GET deletes and
//                  unescaped POST saves are no longer reachable); the CSV imports return there with the result.
// 20260924 LOE SD-657 Save the setting that keeps turnover from users without the Indstillinger right.
// 20260929 CDX/PHR Save explicit HTML layout version choices without resetting older settings forms.

@session_start();
$s_id = session_id();

// Generate CSRF token if not already created
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
// 20260928 Sawaneh Security 4.0 (A12): every posted form in the output gets the token, also on early exits.
// 20261009 Sawaneh sektion=tjekliste redirects to sager/tjeklister.php: checklists left Indstillinger (settings redesign §4, 4e).
$diverseToken = "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf_token, ENT_QUOTES) . "'>";
ob_start(function ($buffer) use ($diverseToken) {
	return preg_replace_callback('/<form\b[^>]*>/i', function ($m) use ($diverseToken) {
		return (stripos($m[0], 'method') !== false && stripos($m[0], 'post') !== false) ? $m[0] . $diverseToken : $m[0];
	}, $buffer);
});
$title      = "Diverse Indstillinger";
$modulnr    = 1;
$css        = "../css/standard.css";
$diffkto    = NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
if (is_file(__DIR__ . '/../bank_integration/includes/enabled.php')) {
	include_once(__DIR__ . '/../bank_integration/includes/enabled.php');
}
include("sys_div_func.php"); # 20150424a
include("skriv_formtabel.inc.php"); # 20150424c

$defaultProvision = $sqlstreng = NULL;
if (!isset($_SESSION['UserName']) && isset($brugernavn)) {
	$_SESSION['UserName'] = $brugernavn;
}

include("top.php");

if (!isset($exec_path)) $exec_path = "/usr/bin";
$sektion    = if_isset($_GET, null, 'sektion');
$pricelists = if_isset($_POST, null, 'pricelists');
if ($sektion == 'personlige_valg') $sektion = 'userSettings';
if ($sektion == 'userSettings' && $_SERVER['REQUEST_METHOD'] != 'POST') {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=personalSettings.php\">";
	exit;
}
// 20260929 Sawaneh Phase 4a (R6): sections that have landed in the generated settings redirect there;
// the toast on the new page says where the old page went (spec §8.10).
$landedSections = array(
	'ordre_valg'     => 'settingsSection.php?s=sales.orders&moved=ordre_valg',
	'massefakt'      => 'settingsSection.php?s=sales.orders&moved=massefakt#sub-mass',
	'provision'      => 'settingsSection.php?s=organisation.commission&moved=provision',
	'productOptions' => 'settingsSection.php?s=items.stock&moved=productOptions',
	'orediff'        => 'settingsSection.php?s=finance.cash_journal&moved=orediff#sub-rounding',
	'api_valg'       => 'settingsSection.php?s=integrations.connections&moved=api_valg',
	'smtp'           => 'settingsSection.php?s=documents.email&moved=smtp#sub-server',
	'rykker_valg'    => 'settingsSection.php?s=sales.reminders&moved=rykker_valg',
	'bilag'          => 'settingsSection.php?s=finance.document_storage&moved=bilag',
	'pricelists'     => 'settingsSection.php?s=purchase.pricelists&moved=pricelists',
	'barcodescan'    => 'settingsSection.php?s=integrations.connections&item=app',
	'posOptions'     => 'settingsSection.php?s=pos.tills&moved=posOptions',
	'div_io'         => 'settingsSection.php?s=import_export.data&moved=div_io',
	'kontoplan_io'   => 'settingsSection.php?s=import_export.data&moved=div_io#sub-accounts',
	'adresser_io'    => 'settingsSection.php?s=import_export.data&moved=div_io#sub-addresses',
	'varer_io'       => 'settingsSection.php?s=import_export.data&moved=div_io#sub-items',
	'variantvarer_io'=> 'settingsSection.php?s=import_export.data&moved=div_io#sub-items',
	'formular_io'    => 'settingsSection.php?s=import_export.data&moved=div_io#sub-forms',
	'solar_io'       => 'settingsSection.php?s=import_export.data&moved=div_io',
	'variant_valg'   => 'settingsSection.php?s=items.variants&moved=variant_valg',
	'labels'         => 'labels.php?moved=labels',
);
if (isset($landedSections[$sektion]) && !($sektion == 'api_valg' && !empty($_GET['varesync']))) {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=" . $landedSections[$sektion] . "\">";
	exit;
}
// The old "Diverse" landing list is replaced by the settings front page (phase 4).
if (!$sektion && $_SERVER['REQUEST_METHOD'] != 'POST') {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=settings.php\">";
	exit;
}
// 20260916 Phase 3 (spec R6): dangerous sections are gated by their own permission key.
// 20260928 Sawaneh Phase 4: frame switch reduced to top.php, the Diverse sub-menu column and landing list
//                  replaced by the registry-driven frame/front page, dead userSettings/personlige_valg code removed.
// 20260928 Sawaneh Key names follow the settings redesign spec (settings.integrations.keys, settings.import_export).
// 20261002 Sawaneh Keys per settings redesign §11.1: settings.integrations and settings.email; reset/delete need settings.company.danger.
// 20260928 Sawaneh Security 4.0: CSRF check on POST (token injected into every posted form), pickup debug log removed,
//                  SQL tool (Dataudtræk), DocuBizz and Paperflow removed, MobilePay/QuickPay secrets write-only.
// 20261001 Sawaneh One row per setting (unique index on settings): KDS colours saved as color_1, color_2...;
//                  item options look their row up when the form has no id.
// 20261002 Sawaneh Phase 4b batch 1: provision, productOptions and orediff moved to the generated sections (their
//                  save code is gone); div_valg no longer saves mySale, print, payment lists, payment days or voucher dates.
// 20261002 Sawaneh Phase 4b batch 2: api_valg landed in Integrationer (only the shop sync still runs here); div_valg no longer
//                  saves GLS, Danske Fragtmænd, QuickPay, MobilePay, Flatpay, Vibrant or Copayone.
// 20261003 Sawaneh G6.3: the SMTP save and the dead 'email' (MAIL/1) save are gone; smtp redirects to Dokumenter & e-mail » E-mail.
// 20261005 Sawaneh G11: div_io and the *_io sections redirect to Indstillinger » Import & eksport (the B-IO1 handlers removed).
// 20261004 Sawaneh G5.8: labels - valg limited to box1/box2, new labels only from the six shipped templates and with an allowed name
//                  (a forged template name could copy any readable file into a label).
// 20261004 Sawaneh G4.3: pricelists landed in Køb » Leverandørprislister (diverseIncludes/pricelists.php is no longer reached).
// 20261010 Sawaneh G5.8: labels redirects to the new Mærkater page (systemdata/labels.php); its save code below is no longer reached.
// 20261004 Sawaneh Pick-up addresses landed in Integrationer » Afhentningsadresser; their save code is gone (it deleted every
//                  address missing from the form, B-D17). Stripe only opens in the operator ledger (G9.6).
// 20261004 Sawaneh G10: PoS-valg landed in Kasse » Kasser, Betalingskort, Kasseoptælling, Kvittering, Køkken, Skærm og Borde; its save code is gone.
// 20261004 Sawaneh The old App Barcode link opens the App row in Integrationer instead of barcodescan.php.
// 20261004 Sawaneh div_valg save keeps DIV/2 box6 (old DocuBizz data, removed in 4f) and escapes the values kept from the stored row.
// 20261004 Sawaneh G2.6: bilag landed in Finans » Bilagsopbevaring; its save code is gone.
// 20261003 Sawaneh G3.4: rykker_valg landed in Salg » Betalingsbetingelser & rykkere; its save code is gone.
// Users without a role inherit these from the Indstillinger bit, so nothing changes for
// them; a role only gets them when an administrator grants them explicitly.
$dangerousSections = array(
	'api_valg' => 'settings.integrations',
	'email' => 'settings.email',
	'smtp' => 'settings.email',
	'stripe_valg' => 'settings.integrations',
	'shop_valg' => 'settings.integrations',
	'adresser_io' => 'settings.import_export',
	'formular_io' => 'settings.import_export',
	'kontoplan_io' => 'settings.import_export',
	'solar_io' => 'settings.import_export',
	'varer_io' => 'settings.import_export',
	'variant_valg_import_types' => 'settings.import_export',
	'variant_valg_import_values' => 'settings.import_export',
);
if (isset($dangerousSections[$sektion]) && function_exists('require_permission')) {
	require_permission($dangerousSections[$sektion], ($_SERVER['REQUEST_METHOD'] === 'POST') ? 'write' : 'read');
}
// Resetting or deleting the company is the danger zone of G1.4 (settings redesign §11.1): Administrator only.
if ($sektion == 'kontoindstillinger' && $_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_POST['nulstil']) || isset($_POST['slet'])) && function_exists('require_permission')) {
	require_permission('settings.company.danger', 'write');
}
$skiftnavn  = if_isset($_GET['skiftnavn']);
// 20260928 Sawaneh Security 4.0 (A12): every POST to this shared entry must carry the session's
// CSRF token; the token is injected into every posted form by the output filter at the end of the file.
if ($_SERVER['REQUEST_METHOD'] == "POST" && (!isset($_POST['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token']))) {
	audit_log('csrf', 'diverse.php?sektion=' . $sektion);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=diverse.php?sektion=" . urlencode((string) $sektion) . "\">";
	exit;
}
if ($_POST && $_SERVER['REQUEST_METHOD'] == "POST") {
	if ($sektion == 'div_valg') {
		$id          = (int) $_POST['id'];
		$box1        = '';                #kept from the stored row below (Salg → Debitorkort)
		$box2        = '';                #kept from the stored row below
		$box3        = $_POST['box3'];    #extra_ansat
		$box4        = '';                #kept from the stored row below (Finans → Kassekladde & betalinger)
		$box5        = '';                #kept from the stored row below
		$box6        = '';                #was DocuBizz - kept from the stored row below until the 4f cleanup
		$box7        = '';                #kept from the stored row below
//		$box8        = $_POST['box8'];    #ebconnect
		$box8        = '';                #kept from the stored row below
		$box9        = $_POST['box9'];    #ledig
		$box10       = '';                #kept from the stored row below
		$box12       = $_POST['box12'];
		// GLS, Danske Fragtmænd, QuickPay, MobilePay, Flatpay, Vibrant and the pick-up addresses are saved by Indstillinger » Integrationer (phase 4b).
		// if ($box8) {
		// 	ftptest($_POST['oiourl'], $_POST['oiobruger'], $_POST['oiokode']);
		// 	$box8 = $_POST['oiourl'] . chr(9) . $_POST['oiobruger'] . chr(9) . $_POST['oiokode'];
		// }
		if (($id == 0) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='2'", __FILE__ . " linje " . __LINE__))))
			$id = $r['id'];
		if ($keep = db_fetch_array(db_select("select box1, box2, box4, box5, box6, box7, box8, box10 from grupper where art = 'DIV' and kodenr = '2'", __FILE__ . " linje " . __LINE__))) {
			foreach (array('box1', 'box2', 'box4', 'box5', 'box6', 'box7', 'box8', 'box10') as $kept) {
				$$kept = db_escape_string((string) $keep[$kept]);
			}
		}
		if ($id == 0) {
			// db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12) values ('Div_valg','2','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10','$box11','$box12')", __FILE__ . " linje " . __LINE__);
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12) values ('Div_valg','2','DIV','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10','$box11','$box12')", __FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			// db_modify("update grupper set  box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10',box11='$box11',box12='$box12' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
			$qtxt = "update grupper set  ";
			$qtxt.= "box1='$box1',box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$box7',box8='$box8',box9='$box9',box10='$box10',box11='$box11',box12='$box12' ";
			$qtxt.= "WHERE id = '$id'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		
		#######################################################################################
	} elseif ($sektion == 'ordre_valg') {
		$vatPrivateCustomers  = if_isset($_POST['vatPrivateCustomers']);
		$vatBusinessCustomers = if_isset($_POST['vatBusinessCustomers']);
		$hideRevenue          = (ifset($_POST, 'hideRevenue') === 'on') ? 'on' : 'off'; #SD-657
		$box2                 = if_isset($_POST['box2']); #Rabatvarenr
		$box3                 = if_isset($_POST['box3']); #folge_s_tekst
		$box4                 = if_isset($_POST['box4']); #hurtigfakt
		$box5                 = if_isset($_POST['straks_deb']) . ";" . if_isset($_POST['straks_kred']); #straks_bogf
		$box6                 = if_isset($_POST['box6']); #fifo
		$box7                 = if_isset($_POST['box7']); #
		$box8                 = if_isset($_POST['box8']); #vis_nul_lev
		$box9                 = if_isset($_POST['box9']); #negativt_lager
		$box10                = if_isset($_POST['box10']); #
		$box11                = if_isset($_POST['box11']); #advar_lav_beh
		$box12                = if_isset($_POST['box12']); #$procentfakt
		$box13                = if_isset($_POST['procenttillag']) . chr(9) . if_isset($_POST['procentvare']);
		$box14                = if_isset($_POST['box14']);
		$rabatvarenr          = if_isset($_POST['rabatvarenr']);
		$kostmetode           = if_isset($_POST['kostmetode']);
		$saetvarenr           = if_isset($_POST['saetvarenr']); #20150907
		$orderNoteEnabled     = if_isset($_POST, null, 'orderNoteEnabled');
		$debitoripad          = if_isset($_POST, null, 'debitoripad');
		$portovarenr          = if_isset($_POST, null, 'portovarenr');
		$showDB               = if_isset($_POST, null, 'showDB');
		$showDG               = if_isset($_POST, null, 'showDG');
		$pluklisteEmail       = if_isset($_POST, null, 'pluklisteEmail');
		$lockPayment          = if_isset($_POST["lockPayment"]);
		$ordreAutocomplete    = if_isset($_POST, null, 'ordreAutocomplete');
		$gs1parsing           = if_isset($_POST, null, 'gs1_parsing');
		$ourRefStockSwitch    = if_isset($_POST, null, 'ourRefStockSwitch');
		$stockWarningEnabled  = if_isset($_POST, null, 'stockWarningEnabled');
		
		$showBothAddrExtra    = if_isset($_POST, null, 'showBothAddrExtra');


		update_settings_value("debitoripad", "ordre", $debitoripad, "Weather or not to include the debitor ipad system");
		update_settings_value("pluklisteEmail", "ordre", $pluklisteEmail, "Email address to send plukliste to");
		update_settings_value("porto_varnr", "ordre", $portovarenr, "Varenr to autmatically include on new orders");
		update_settings_value("showDB", "ordre", $showDB, "Weather or not to show the DB on the order page");
		update_settings_value("showDG", "ordre", $showDG, "Weather or not to show the DG on the order page");
		update_settings_value("lockedInvoiceButton", "debitor", $lockPayment, "Locks the invoice button until payment has occured");
		update_settings_value("ordreAutocomplete", "ordre", $ordreAutocomplete, "Enable or disable autocomplete search on order pages", $bruger_id);
		update_settings_value("gs1_parsing", "ordre", $gs1parsing, "Enable GS1 barcode parsing on order line item entry");
		update_settings_value("ourRefStockSwitch", "ordre", $ourRefStockSwitch, "Update order stock/warehouse from Our ref when the reference changes"); // Removed single quotes from description to avoid SQL syntax error
		update_settings_value("stockWarningEnabled", "ordre", $stockWarningEnabled, "Show popup and require approval note when selling out-of-stock items (POS + Debtor/Order)");
		update_settings_value("showBothAddrExtra", "ordre", $showBothAddrExtra, "Show both delivery address and extra fields simultaneously on open orders");
		if ($box2 && $r = db_fetch_array(db_select("select id from varer WHERE varenr = '$box2'", __FILE__ . " linje " . __LINE__))) {
			$box2 = $r['id'];
		} elseif ($box2) {
			$txt = str_replace('XXXXX', $box2, findtekst('289|Varenr. XXXXX eksisterer ikke.', $sprog_id));
			print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
		if ($box14 && !$box2) {
			$txt = findtekst('1875|Samlet pris forudsætter at der er et varenr. til rabat', $sprog_id); #20210820
			print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
			$box14 = '';
		}
		#20150907 ->
		$saetvareid = 0;
		$qtxt = "select id from varer WHERE varenr = '$saetvarenr'";
		if ($saetvarenr && $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
			$saetvareid = $r['id'];
		if ($saetvarenr && !$saetvareid) {
			$txt = findtekst('1876|Varenummer for sæt eksisterer ikke', $sprog_id);
			print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
		}
/*
		if ($kostmetode) {
			if ($r=db_fetch_array(db_select("select id from grupper WHERE art = 'VG' and box1 != box2",__FILE__ . " linje " . __LINE__))) {
				$txt = findtekst(733, $sprog_id);
				print "<BODY onLoad=\"JavaScript:alert('$txt')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../systemdata/konv_lager.php\">\n"; # 20140424b
				exit;
			}
		}
*/
		# <- 20150907
		if ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='3'", __FILE__ . " linje " . __LINE__))) {
			$id   = $r['id'];
			$qtxt = "update grupper set  box2='$box2',box3='$box3',box4='$box4',box5='$box5',box6='$box6',";
			$qtxt.= "box7='$box7',box8='$box8',box9='$box9',box10='$box10',box11='$box11',box12='$box12',box13='$box13',";
			$qtxt.= "box14='$box14' WHERE id = '$id'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into grupper (beskrivelse,kodenr,art,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,";
			$qtxt.= "box12,box13,box14) values ('Div_valg (Ordrer)','3','DIV','$box2','$box3','$box4','$box5','$box6',";
			$qtxt.= "'$box7','$box8','$box9','$box10','$box11','$box12','$box13','$box14')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		
		// Save VAT options to settings table
		update_settings_value("vatPrivateCustomers", "ordre", $vatPrivateCustomers, "Show VAT on orders for private customers");
		update_settings_value("vatBusinessCustomers", "ordre", $vatBusinessCustomers, "Show VAT on orders for business customers");
		update_settings_value("hideRevenue", "finans", $hideRevenue, "Keep turnover from users without access to Settings");
		
		if ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='5'", __FILE__ . " linje " . __LINE__))) {
			$id = $r['id'];
			db_modify("update grupper set box6='$kostmetode',box8='$saetvareid' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13) ";
			$qtxt.= "values ('Div_valg','5','DIV','','','','','','','$kostmetode','','','','','','')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}

		if ($r = db_fetch_array(db_select("select id from settings where var_name='orderNoteEnabled'", __FILE__ . " linje " . __LINE__))) { #20210729
			$id   = $r['id'];
			$qtxt = "update settings set var_value='$orderNoteEnabled' WHERE id='$id'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into settings (var_name, var_value) values ('orderNoteEnabled','$orderNoteEnabled')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}

		update_settings_value("debitoripad", "ordre", $debitoripad, "Weather or not to include the debitor ipad system");

		#######################################################################################
	} elseif ($sektion == 'variant_valg') {
		$id                   = if_isset($_POST['id']);
		$variant_beskrivelse  = if_isset($_POST['variant_beskrivelse']);
		$variant_id           = if_isset($_POST['variant_id']);
		$var_type_beskrivelse = if_isset($_POST['var_type_beskrivelse']);
		$variant_antal        = if_isset($_POST['variant_antal']);
		$rename_varianter     = if_isset($_POST['rename_varianter']);
		$rename_var_type      = if_isset($_POST['rename_var_type']);
		if ($rename_var_type) {
			db_modify("update variant_typer set  beskrivelse='$var_type_beskrivelse' WHERE id = '$rename_var_type'", __FILE__ . " linje " . __LINE__);
		} elseif ($rename_varianter) {
			db_modify("update varianter set  beskrivelse='$variant_beskrivelse' WHERE id = '$rename_varianter'", __FILE__ . " linje " . __LINE__);
		} elseif ($variant_beskrivelse)
			db_modify("insert into varianter (beskrivelse) values ('$variant_beskrivelse')", __FILE__ . " linje " . __LINE__);
		for ($x = 1; $x <= $variant_antal; $x++) {
			if ($var_type_beskrivelse[$x] && $variant_id[$x])
				db_modify("insert into variant_typer (beskrivelse,variant_id) values ('$var_type_beskrivelse[$x]','$variant_id[$x]')", __FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion == 'variant_valg_import_types') {
		// Import variant types (main categories like Color, Size)
		$imported = 0;
		$skipped  = 0;
		if (isset($_FILES['variant_types_file']) && $_FILES['variant_types_file']['error'] == 0) {
			$filnavn = $_FILES['variant_types_file']['tmp_name'];
			if (($handle = fopen($filnavn, "r")) !== FALSE) {
				while (($line = fgets($handle)) !== FALSE) {
					$line = trim($line);
					// Handle both semicolon and comma separated, but expect single column
					$parts = preg_split('/[;,\t]/', $line);
					$variant_name = trim($parts[0]);
					
					// Convert encoding if needed
					if ($db_encode == "UTF8" && !mb_check_encoding($variant_name, 'UTF-8')) {
						$variant_name = mb_convert_encoding($variant_name, 'UTF-8', 'ISO-8859-1');
					}
					
					if (!empty($variant_name)) {
						// Check if variant already exists
						$escaped_name = db_escape_string($variant_name);
						$existing = db_fetch_array(db_select("SELECT id FROM varianter WHERE LOWER(beskrivelse) = LOWER('$escaped_name')", __FILE__ . " linje " . __LINE__));
						if (!$existing) {
							db_modify("INSERT INTO varianter (beskrivelse) VALUES ('$escaped_name')", __FILE__ . " linje " . __LINE__);
							$imported++;
						} else {
							$skipped++;
						}
					}
				}
				fclose($handle);
			}
		}
		$_SESSION['settings_flash'] = array('ok', sprintf(findtekst('6843|Importeret: %s varianttyper. Sprunget over, da de findes allerede: %s.', $sprog_id), $imported, $skipped));
		header("Location: settingsSection.php?s=items.variants");
		exit;
	#######################################################################################
	} elseif ($sektion == 'variant_valg_import_values') {
		// Import variant values (values for existing types like Red, Blue for Color)
		$imported  = 0;
		$skipped   = 0;
		$not_found = 0;
		if (isset($_FILES['variant_values_file']) && $_FILES['variant_values_file']['error'] == 0) {
			$filnavn = $_FILES['variant_values_file']['tmp_name'];
			if (($handle = fopen($filnavn, "r")) !== FALSE) {
				while (($line = fgets($handle)) !== FALSE) {
					$line = trim($line);
					// Expect format: variant_name;value or variant_name,value
					$parts = preg_split('/[;,\t]/', $line, 2);
					if (count($parts) >= 2) {
						$variant_name = trim($parts[0]);
						$value_name = trim($parts[1]);
						
						// Convert encoding if needed
						if ($db_encode == "UTF8") {
							if (!mb_check_encoding($variant_name, 'UTF-8')) {
								$variant_name = mb_convert_encoding($variant_name, 'UTF-8', 'ISO-8859-1');
							}
							if (!mb_check_encoding($value_name, 'UTF-8')) {
								$value_name = mb_convert_encoding($value_name, 'UTF-8', 'ISO-8859-1');
							}
						}
						
						if (!empty($variant_name) && !empty($value_name)) {
							// Find the variant by name
							$escaped_variant = db_escape_string($variant_name);
							$variant = db_fetch_array(db_select("SELECT id FROM varianter WHERE LOWER(beskrivelse) = LOWER('$escaped_variant')", __FILE__ . " linje " . __LINE__));
							
							if ($variant) {
								$variant_id = $variant['id'];
								$escaped_value = db_escape_string($value_name);
								
								// Check if value already exists for this variant
								$existing = db_fetch_array(db_select("SELECT id FROM variant_typer WHERE variant_id = '$variant_id' AND LOWER(beskrivelse) = LOWER('$escaped_value')", __FILE__ . " linje " . __LINE__));
								if (!$existing) {
									db_modify("INSERT INTO variant_typer (beskrivelse, variant_id) VALUES ('$escaped_value', '$variant_id')", __FILE__ . " linje " . __LINE__);
									$imported++;
								} else {
									$skipped++;
								}
							} else {
								$not_found++;
							}
						}
					}
				}
				fclose($handle);
			}
		}
		$_SESSION['settings_flash'] = array('ok', sprintf(findtekst('6844|Importeret: %s værdier. Sprunget over, da de findes allerede: %s. Variant ikke fundet: %s.', $sprog_id), $imported, $skipped, $not_found));
		header("Location: settingsSection.php?s=items.variants#sub-values");
		exit;
	#######################################################################################
	} 
	
// 	elseif ($sektion == 'shop_valg') {
// 		$id = if_isset($_POST['id']);
// #		$box1 = if_isset($_POST['box1']);   #incl_moms (legacy - not used for VAT anymore)
// 		$box2 = if_isset($_POST['box2']);   #Shop url
// 		$box3 = if_isset($_POST['box3']);   #shop valg
// 		$box4 = if_isset($_POST['box4']);   #merchant id
// 		$box5 = if_isset($_POST['box5']);   #md5 secret
// #		$box6 = if_isset($_POST['box6']);   #Bruges ved productOptions
// 		$box7 = if_isset($_POST['box7']);   #Tegnsæt for webshop
// #		$box8 = if_isset($_POST['box8']);   #Bruges ved ordre_valg
// 		$box9 = if_isset($_POST['box9']);   #Agreement ID
// 		$box10 = if_isset($_POST['box10']); #ledig

// 		if ($box3 == '1')
// 			$box2 = '!';
// 		$qtxt = NULL;
// 		if ((!$id) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='5'", __FILE__ . " linje " . __LINE__))))
// 			$id = $r['id'];
// 		if (!$id) {
// 			$qtxt = "insert into grupper (beskrivelse,kodenr,art,box2,box3,box4,box5,box7,box9) values ('Div_valg (Varer)','5','DIV','$box2','$box3','$box4','$box5','$box7','$box9')";
// 		} elseif ($id > 0) {
// 			$qtxt = "update grupper set box2='$box2',box3='$box3',box4='$box4',box5='$box5',box7='$box7',box9='$box9' WHERE id = '$id'";
// 		}
// 		if ($qtxt)
// 			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
// 		#######################################################################################
// 	} 
	elseif ($sektion == 'stripe_valg' && $db == $sqdb) {
		include_once(__DIR__ . '/diverseIncludes/stripeValg.php');
		stripeValgSave();
		#######################################################################################
	} elseif ($sektion == 'labels') {
		// Generate template from form data
		$valg           = if_isset($_GET['valg']);
		if ($valg !== 'box1' && $valg !== 'box2') $valg = '';
    	$labelName      = if_isset($_POST['labelName']);
    	$newLabelName   = if_isset($_POST['newLabelName']);
    	$labelTemplate  = if_isset($_POST['labelTemplate']);
    	$saveLabel      = if_isset($_POST['saveLabel']);
    	$saveRawHTML    = if_isset($_POST['saveRawHTML']);
    	$deleteLabel    = if_isset($_POST['deleteLabel']);
    	$createNewLabel = if_isset($_POST['createNewLabel']);
    	$switchToVisual = if_isset($_POST['switchToVisual']);
        // Ensure labelName is preserved when switching between editors
    if ($switchToVisual && !$labelName) {
        $labelName = if_isset($_GET['labelName'], 'Standard');
    }
	
    // Only the shipped templates, and only names the create form allows (the form's pattern is not a guard).
    $labelTemplates = array('A4Label38x21_ens.txt', 'A4Label38x21.txt', 'BrotherLabel22606.txt', 'BrotherLabel22606MS.txt', 'DymoLabelArt11354.txt', 'DymoLabelArt11354MS.txt');
    if ($createNewLabel && (!in_array($labelTemplate, $labelTemplates, true) || !preg_match('/^[a-zA-Z0-9+.-]+$/', (string) $newLabelName))) {
        $createNewLabel = null;
    }
    if ($createNewLabel && $newLabelName && $labelTemplate) {
        // Create new label from template
        $templateFile = "../importfiler/$labelTemplate";
        if (file_exists($templateFile)) {
            $templateContent = file_get_contents($templateFile);
            saveLabelText($valg, $newLabelName, $templateContent, 'sheet');
            $labelName = $newLabelName;
        }
    } elseif ($saveRawHTML) {
        // Save raw HTML
        $rawHTML   = if_isset($_POST['rawHTML'], '');
        $labelType = if_isset($_POST, 'sheet', ['labelType']);
        saveLabelText($valg, $labelName, $rawHTML, $labelType);
    } elseif ($switchToVisual) {
		// When switching from raw HTML to visual editor, we need to save the raw HTML first
		$rawHTML   = if_isset($_POST['rawHTML'], '');
		$labelType = if_isset($_POST['labelType'], 'sheet');
		saveLabelText($valg, $labelName, $rawHTML, $labelType);
	} elseif ($saveLabel) {
		// saveVisualLabelEdit() (sys_div_func.php) refuses when the label's CURRENT template
		// has formatting the visual editor's field model can't reproduce (imported Brother/Dymo
		// templates, hand-written raw HTML, ...) - regenerating from that narrow model would
		// silently discard whatever it doesn't understand, which is exactly MB-18 ("changing
		// any setting destroys the whole configuration"). The UI already hides the visual
		// editor for such labels (see labels() in sys_div_func.php); this is the authoritative
		// check a form submit cannot bypass.
		if (!saveVisualLabelEdit($valg, $labelName, $_POST)) {
			// Refused - the template changed underneath this submit (e.g. another tab/admin, or
			// a back-button re-post) into something the visual editor can no longer safely
			// regenerate. Nothing is saved; labels() below re-renders the current stored
			// template in raw-HTML mode with an explicit "not saved" message instead of silently
			// discarding the user's submitted values (MB-18 review).
			$saveLabelRefused = true;
		}
		} elseif ($deleteLabel && $labelName != 'Standard') {
			$qtxt = "DELETE FROM labels WHERE labelname = '" . db_escape_string($labelName) . "'";
			$qtxt.= " and (account_id = '0' or account_id is null)";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			$labelName = 'Standard';
		}
		#######################################################################################
	} elseif ($pricelists) {
		$id          = $_POST['id'];
		$beskrivelse = $_POST['beskrivelse'];
		$box1        = $_POST['lev_id'];
		$box2        = $_POST['prisfil'];
		$box3        = $_POST['opdateret'];
		$box4        = $_POST['aktiv'];
		$box5        = $_POST['rabatter'];
		$box6        = $_POST['rabat'];
		$box7        = $_POST['grupper'];
		$box8        = $_POST['gruppe'];
		$box9        = $_POST['filtype'];
		$slet        = $_POST['slet'];
		$antal       = $_POST['antal'];

		for ($x = 0; $x < count($id); $x++) {
#			if (!$box4[$x]) $box1[$x]=''; # 20160225

			$id[$x] *= 1;
			$qtxt = NULL;
			$q_txt = "select id from grupper WHERE art='PL' and beskrivelse='$beskrivelse[$x]'";
			if ($id[$x] == 0 && $box4[$x] && $r = db_fetch_array(db_select($q_txt, __FILE__ . " linje " . __LINE__))) {
				$id[$x] = $r['id'];
			} elseif ($id[$x] == 0 && $box4[$x] && $beskrivelse[$x]) {
				$box4[$x] = 0; # 20150612
				$qtxt = "insert into grupper (beskrivelse,kodenr,art,box2,box4,box6,box8,box9) values ";
				$qtxt.= "('$beskrivelse[$x]','0','PL','$box2[$x]','$box4[$x]','$box6[$x]','$box8[$x]','$box9[$x]')";
			} elseif ($id[$x] && $slet[$x] == "Slet") {
				$slet[$x] = $slet[$x];
			} elseif ($id[$x] > 0) {
				$qtxt = "update grupper set beskrivelse='$beskrivelse[$x]',box1='$box1[$x]',box2='$box2[$x]',box4='$box4[$x]',";
				$qtxt.= "box6='$box6[$x]',box8='$box8[$x]',box9='$box9[$x]' WHERE id='$id[$x]'";
			}
			if ($qtxt)
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	#######################################################################################
	} elseif ($sektion == 'massefakt') {
		$id         = if_isset($_POST['id']);
		$brug_mfakt = if_isset($_POST['brug_mfakt']);
		if ($brug_mfakt) {
			$brug_dellev = if_isset($_POST['brug_dellev']);
			$levfrist    = if_isset($_POST['levfrist']);
		} else {
			$brug_dellev = NULL;
			$levfrist    = 0;
		}
		if ((!$id) && ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'MFAKT'", __FILE__ . " linje " . __LINE__))))
			$id = $r['id'];
		elseif (!$id) {
			db_modify("insert into grupper (beskrivelse,kodenr,art,box1,box2,box3) values ('Massefakturering','1','MFAKT','$brug_mfakt','$brug_dellev','$levfrist')", __FILE__ . " linje " . __LINE__);
		} elseif ($id > 0) {
			db_modify("update grupper set  box1='$brug_mfakt',box2='$brug_dellev',box3='$levfrist' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
		}


######################################################################################


######################################################################################
	// 20261005 Sawaneh G11: the *_io handlers (fatal strstr() with one argument, audit B-IO1) are gone; the sections redirect.
	} elseif ($sektion == 'kontoindstillinger') {

		if (isset($_POST['update_max_users'])) {
		
			$new_max_users = isset($_POST['max_users']) ? (int)$_POST['max_users'] : 0;
			if ($new_max_users <= 0 || $new_max_users > 1000) {
				echo "<p style='color: red;'>Invalid number of users. Must be between 1 and 1000.</p>";
				exit;
			}
			
			$ch       = curl_init();
			$curl_url = "https://saldi.dk/locator/locator.php?action=insertUserCount&userCount=$new_max_users&dbName=$db";
			curl_setopt($ch, CURLOPT_URL, $curl_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Accept: application/json'
			));
			$response  = curl_exec($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($http_code != 200) {
				echo "<p style='color: red;'>Failed to update max users. Please try again later.</p>";
				error_log("Failed to update max users via API. HTTP code: $http_code");
				exit;
			}

			$current_regnskab_name = $regnskab;
			$current_username      = $brugernavn;
			$update_successful     = false;
			$old_max_users_value   = null;
		
			$masterDb = $sqdb;
			include("../includes/connect.php");
				$query_select  = "SELECT brugerantal FROM regnskab WHERE db = '$db'";
				$result_select = db_select($query_select, __FILE__ . " linje " . __LINE__);
		
				if ($result_select && db_num_rows($result_select) > 0) {
					$row = db_fetch_array($result_select);
					$old_max_users_value = (int) $row['brugerantal'];
		
					if ($new_max_users !== $old_max_users_value) {
						$query_update = "UPDATE regnskab SET brugerantal = $new_max_users WHERE db = '$db'";
						db_modify($query_update, __FILE__ . " linje " . __LINE__);
		
							$update_successful = true;
							echo "<p style='color: blue;'>Max users updated successfully to $new_max_users.</p>";
						
							$subject = "Max Users Updated on Account: $current_regnskab_name";
							$message = "User '$current_username' has updated the max users from $old_max_users_value to $new_max_users on account '$current_regnskab_name'.";
							$headers = "From: noreply@saldi.dk\r\nReply-To: noreply@saldi.dk\r\n";
						
							mail("info@saldi.dk", $subject, $message, $headers);
						
					} else {
						echo "<p style='color: gray;'>No change in max users value.</p>";
					}
				} else {
					echo "<p style='color: red;'>An unexpected error occurred.</p>";
				}
				include("../includes/online.php");
		}
		
		
		// 20261005 Sawaneh G1.3/G1.4: rename, base currency, time zone, reset and delete are saved by the new sections.
	} elseif ($sektion == 'tjekliste') {
		// 20261009 Sawaneh Checklists moved to the Sager module (settings redesign §4, phase 4e).
		header('Location: ../sager/tjeklister.php');
		exit;
	}
} else {
	$valg    = if_isset($_GET['valg']);
	// $sektion = if_isset($_GET['sektion']);
	$sektion = if_isset($_GET, null, 'sektion');
	#	if ($sektion == 'personlige_valg') $sektion = 'userSettings';


}

print "<table class='dataTable2' cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" height=\"100%\"><tbody>";

// 20260928 Phase 4: no separate "Diverse" column; the frame (top.php) lists the group's pages.
print "<td valign=\"top\" align=\"left\"><table align=\"left\" valign=\"top\" border=\"0\" width=\"90%\"><tbody>\n";
if (!$sektion)
	print "<td><br></td>";
if ($sektion == "kontoindstillinger")
	kontoindstillinger($regnskab, $skiftnavn);
if ($sektion == "ordre_valg")
	ordre_valg();
if ($sektion == "variant_valg") variant_valg();
// if ($sektion == "shop_valg") shop_valg();
if ($sektion == "api_valg") api_valg();
if ($sektion == "stripe_valg") {
	// Subscriptions (Stripe) belong to the operator ledger only (settings redesign G9.6).
	if ($db != $sqdb) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=settings.php\">";
		exit;
	}
	include_once(__DIR__ . '/diverseIncludes/stripeValg.php');
	stripeValg();
}
if ($sektion == "labels") labels($valg);
if ($sektion == "pricelists") {
	include("diverseIncludes/pricelists.php");
	pricelists();
}
if ($sektion == "div_valg") div_valg(); # Kalder sys_div_valg.php
if ($sektion == "bank_integration") include('diverseIncludes/bank_integration.php');
//if ($sektion=="barcodescan") barcodescan();
if ($sektion == "massefakt") massefakt();

if ($sektion == "sprog") {
	include("diverseIncludes/language.php");
	language();
}
if ($sektion == "tjekliste") {
	header('Location: ../sager/tjeklister.php'); // 20261009 Sawaneh moved to the Sager module (4e)
	exit;
}

print "</tbody></table></td></tr>";
#print "</form>";
#print "</tbody></table></td></tr>";





?>
</tbody>
</table>
</body>

</html>
