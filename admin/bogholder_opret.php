<?php
ob_start(); # for the redirect after an action (vr_prg)
@session_start();
$s_id=session_id();
// --- admin/bogholder_opret.php --- ver 5.1.0 --- 2026.10.29 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261012 CL/ASR New. "Opret og tilknyt" in the accountant panel (bogholderpanelet), for employees with the right
//                  "Oprette kunder" in a firm Saldi allows to create accounts (partners.can_create):
//                  - Opret kunderegnskab: a new account in the customer's name with company details, the payment
//                    model (the firm pays Finans in the bookkeeper scheme, or the customer pays a chosen plan with a
//                    30-day trial), the customer's first user (create now, invite by e-mail, or later) and the
//                    responsible employee. The account is created by create_regnskab() from includes/opretfunc.php.
//                  - Tilknyt eksisterende regnskab: look up by number, name or known CVR and send an access request
//                    that the customer approves by e-mail (or Saldi, when the account has no e-mail). Nothing about
//                    the customer's plan, payer or other bookkeepers changes.
// 20261029 CL/ASR Group mode: a new company is owned by the group (access 'owner'), gets any plan without a trial and is
//                  invoiced via the parent by default or pays itself; linking an existing company makes the group its owner.
// 20261029 CL/ASR Review fixes: the chosen payer is written in both models, the creator and the responsible employee get
//                  access when they only see selected customers, the password is hashed as typed, login names are checked.

$css="../css/standard.css";
$title="Bogholderpanel";
include("../includes/connect.php");
include("../includes/std_func.php");
$modulnr=101;
$online_result=include("../includes/online.php"); // the only auth check, must run for every request regardless of $_POST content (SD-615)
if ($online_result === 'Session expired' || $db != $sqdb) { print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/logud.php\">"; exit; }
include("../includes/version.php");
include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("../includes/entitlements.php");
include("../includes/partnerPanel.php");
include("vr_ui.php");
include("inc_betalinger.php");
include("inc_proeve.php");
include("bogholder_ui.php");
include(__DIR__ . "/../includes/opretfunc.php"); // create_regnskab(), shared with admin/opret.php, operator_opret.php and the sign-up API
$me = bp_require_partner();
ent_tables_ensure(); tr_tables_ensure();
$notes = vr_flash(); $pid = bp_pid(); $p = bp_partner(); $custWord = bp_cust_word();
$tab = if_isset($_GET, 'ny', 'tab') == 'tilknyt' ? 'tilknyt' : 'ny';
$crumbs = array(bp_crumb_root(), bp_t('Opret og tilknyt','Create and link'));

if (!bp_can('create')) {
	bp_open($crumbs, bp_t('Opret og tilknyt','Create and link'), bp_t('Kræver rettigheden "Oprette kunder" – og at Saldi har givet firmaet lov til at oprette regnskaber.','Requires the right "Create customers" – and that Saldi allows the firm to create accounts.'));
	print "<section class=\"vr-sect\"><div class=\"vr-card\"><div class=\"vr-empty\"><b>".($p['can_create'] === 't' ? bp_t('Din bruger har ikke rettigheden','Your user does not hold the right') : bp_t('Firmaet må ikke oprette regnskaber endnu','The firm may not create accounts yet'))."</b><span>".($p['can_create'] === 't' ? bp_t('Bed firmaets ejer om rettigheden Oprette kunder under Mit firma › Medarbejdere.','Ask the firm\'s owner for the right Create customers under My firm › Employees.') : bp_t('Kontakt Saldi for at få oprettelse slået til for firmaet.','Contact Saldi to have creation enabled for the firm.'))."</span></div></div></section>"; bp_close(); exit;
}

// ---------- actions ----------
if (isset($_POST['bp_action'])) {
	$a = $_POST['bp_action'];
	if ($a == 'request') {
		$rid = (int)if_isset($_POST, 0, 'regnskab_id'); list($id, $note) = bp_access_request_create($rid, trim((string)if_isset($_POST, '', 'message')));
		if (!$id) $notes[] = $note; else { $reg = db_fetch_array(db_select("select regnskab from regnskab where id = '$rid'", __FILE__ . " linje " . __LINE__)); $notes[] = bp_t('Anmodningen om adgang til','The access request for').' '.$reg['regnskab'].' '.bp_t('er sendt','was sent'); if ($note) $notes[] = $note; }
		vr_prg($notes, 'bogholder_opret.php?tab=tilknyt');
	}
	if ($a == 'create') {
		$f = array(); foreach (array('regnskab','firmanavn','cvrnr','addr1','postnr','bynavn','kontakt','tlf','email','model','plan','userMode','brugernavn','kode','kode2','uname','uemail','umessage') as $k) $f[$k] = trim((string)if_isset($_POST, '', $k));
		$resp = (int)if_isset($_POST, 0, 'responsible'); $std = isset($_POST['std_kto_plan']);
		$err = array();
		if ($f['regnskab'] === '') $err[] = bp_t('Regnskabets navn mangler','The account name is missing'); elseif (is_input_too_long($f['regnskab'], 60)) $err[] = findtekst('5150|Regnskabsnavnet må højst være 60 tegn', $sprog_id);
		elseif (db_fetch_array(db_select("select 1 from regnskab where regnskab = '".db_escape_string($f['regnskab'])."'", __FILE__ . " linje " . __LINE__))) $err[] = findtekst('2682|Regnskab', $sprog_id).' '.$f['regnskab'].' '.findtekst('2683|findes allerede', $sprog_id);
		if ($f['cvrnr'] !== '' && !preg_match('/^\d{8}$/', preg_replace('/\D/', '', $f['cvrnr']))) $err[] = bp_t('CVR skal være 8 cifre','CVR must be 8 digits');
		if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $err[] = bp_t('Kundens e-mail er ikke gyldig','The customer e-mail is not valid');
		if (!in_array($f['model'], array('partner','customer'), true)) $err[] = bp_t('Vælg betalingsmodel','Choose the payment model');
		$grp = bp_is_group();
		if (($f['model'] == 'customer' || $grp) && !($grp ? isset(ent_catalog()['plans'][$f['plan']]) : in_array($f['plan'], array('finans','professionel','business'), true))) $err[] = bp_t('Vælg pakke','Choose the plan');
		if ($f['userMode'] == 'now') { if ($f['brugernavn'] === '' || is_input_too_long($f['brugernavn'])) $err[] = bp_t('Brugernavn til kundens administrator mangler','The customer administrator username is missing'); elseif (!bp_login_name_ok($f['brugernavn'])) $err[] = bp_login_name_msg(); if (strlen($f['kode']) < 6) $err[] = bp_t('Adgangskoden skal være mindst 6 tegn','The password must be at least 6 characters'); if ($f['kode'] !== $f['kode2']) $err[] = bp_t('De to adgangskoder er ikke ens','The two passwords differ'); }
		elseif ($f['userMode'] == 'invite') { if (!filter_var($f['uemail'], FILTER_VALIDATE_EMAIL)) $err[] = bp_t('Skriv en gyldig e-mail til kundens bruger','Enter a valid e-mail for the customer user'); }
		elseif ($f['userMode'] != 'none') $err[] = bp_t('Vælg, hvordan kundens bruger oprettes','Choose how the customer user is created');
		if ($resp && !bp_employee($resp)) $resp = 0;
		if ($err) { $_SESSION['bp_opret_form'] = $f + array('responsible' => $resp); vr_prg($err, 'bogholder_opret.php', 'warn'); }
		// ---- create the account with create_regnskab() (includes/opretfunc.php), as admin/opret.php and operator_opret.php do
		@set_time_limit(600);
		$cvrClean = preg_replace('/\D/', '', $f['cvrnr']);
		$adminUser = $f['userMode'] == 'now' ? $f['brugernavn'] : 'kunde'; $adminPw = $f['userMode'] == 'now' ? $f['kode'] : bin2hex(random_bytes(12));
		$company = array('kontakt' => db_escape_string($f['kontakt']), 'firmanavn' => db_escape_string($f['firmanavn'] !== '' ? $f['firmanavn'] : $f['regnskab']), 'addr1' => db_escape_string($f['addr1']), 'addr2' => '', 'postnr' => db_escape_string($f['postnr']), 'bynavn' => db_escape_string($f['bynavn']), 'tlf' => db_escape_string($f['tlf']), 'email' => db_escape_string($f['email']), 'cvrnr' => db_escape_string($cvrClean));
		$ws = $GLOBALS['webservice'] ?? null; $GLOBALS['webservice'] = 1; # like the sign-up API: no master user gets the account added to an adgang_til list because it shares the administrator's name
		ob_start(); $res = create_regnskab(db_escape_string($f['regnskab']), db_escape_string($adminUser), $adminPw, db_escape_string($f['email']), 0, 0, $std ? 'on' : NULL, $company); ob_end_clean();
		$GLOBALS['webservice'] = $ws; $db = $sqdb;
		if (!$res['ok']) vr_prg(array(bp_t('Regnskabet kunne ikke oprettes','The account could not be created').($res['error'] == 'exists' ? ': '.bp_t('navnet findes allerede','the name already exists') : '')), 'bogholder_opret.php', 'warn');
		$db_id = (int)$res['db_id']; $newdb = $res['db'];
		db_modify("update regnskab set lukket = '' where id = '$db_id' and lukket is null", __FILE__ . " linje " . __LINE__);
		// plan, payer, trial follow-up
		if ($grp) { ent_write_plan($db_id, $f['plan'], date('Y-m-d'), 'manual'); db_modify("insert into regnskab_trial (regnskab_id, started, ends, source, resolved, resolved_at, updated_by) values ('$db_id', current_date, current_date, 'opret', 'exempt', now(), '".db_escape_string(ent_actor())."')", __FILE__ . " linje " . __LINE__); } # a group company has no trial
		elseif ($f['model'] == 'partner') { ent_write_plan($db_id, 'finans', date('Y-m-d'), 'manual'); db_modify("insert into regnskab_trial (regnskab_id, started, ends, source, resolved, resolved_at, updated_by) values ('$db_id', current_date, current_date, 'opret', 'exempt', now(), '".db_escape_string(ent_actor())."')", __FILE__ . " linje " . __LINE__); }
		else { if ($f['plan'] == 'finans') ent_write_plan($db_id, 'finans', date('Y-m-d'), 'manual'); else ent_start_trial($db_id, $f['plan'], 30); tr_register($db_id); }
		db_modify("insert into regnskab_partners (regnskab_id, partner_id, access, created_by, note, payer, responsible_bruger_id) values ('$db_id', '$pid', '".($grp ? 'owner' : 'full')."', '".(int)$me['id']."', '".($grp ? 'oprettet af koncernen' : 'oprettet af bogholder')."', '".($f['model'] == 'partner' ? 'partner' : 'customer')."', ".($resp ?: 'NULL').")", __FILE__ . " linje " . __LINE__);
		partner_log('ledger.created', $db_id, $pid, $f['regnskab'].' – '.($grp ? ent_plan_name($f['plan']).', '.($f['model'] == 'partner' ? 'faktureres via hovedselskabet' : 'betaler selv') : ($f['model'] == 'partner' ? 'firmaet betaler Finans (ordning)' : 'kunden betaler: '.$f['plan'])).($cvrClean ? " – CVR $cvrClean" : ''));
		partner_log('partner.link_added', $db_id, $pid, $p['name'].' ('.($grp ? 'owner' : 'full').') – ved oprettelse');
		bp_set_payer($db_id, $pid, $f['model'] == 'partner' ? 'partner' : 'customer', bp_t('ved oprettelse','at creation'));
		foreach (array_unique(array((int)$me['id'], (int)$resp)) as $sb) { $se = $sb ? bp_employee($sb) : null; if ($se && $se['scope'] == 'selected') db_modify("insert into partner_user_access (bruger_id, regnskab_id) values ('$sb', '$db_id')", __FILE__ . " linje " . __LINE__); } # the creator and the responsible employee can open it, also with selected customers only
		// company details and work counts into the panel's cache right away
		$one = array($db_id => array('id' => $db_id, 'db' => $newdb, 'closed' => false, 'cache' => null)); bp_cache_refresh($one, true);
		$done = array(bp_t('Regnskabet','The account').' '.$f['regnskab'].' '.bp_t('er oprettet','was created').($grp ? ' – '.ent_plan_name($f['plan']).', '.($f['model'] == 'partner' ? bp_t('faktureres via hovedselskabet','invoiced via the parent') : bp_t('betaler selv','pays itself')) : ($f['model'] == 'partner' ? ' – '.bp_t('Finans i bogholderordningen','Finans in the scheme') : ' – '.bp_t('kunden betaler','the customer pays').' '.ent_plan_name($f['plan']).($f['plan'] != 'finans' ? ' ('.bp_t('30 dages prøve','30-day trial').')' : ''))));
		if ($f['userMode'] == 'invite') {
			$uid = 0; if (@db_connect($sqhost, $squser, $sqpass, $newdb, __FILE__ . " linje " . __LINE__)) { $u = db_fetch_array(db_select("select id from brugere where brugernavn = 'kunde'", __FILE__ . " linje " . __LINE__)); $uid = $u ? (int)$u['id'] : 0; include("../includes/connect.php"); $db = $sqdb; }
			list($iid, $e) = bp_invite_create('customer_user', $db_id, $f['uname'], $f['uemail'], array('role' => 'all', 'message' => $f['umessage'], 'replaces' => $uid));
			$done[] = $e ? bp_t('Invitationen kunne ikke sendes','The invitation could not be sent').': '.$e : bp_t('Invitation sendt til','Invitation sent to').' '.$f['uemail'];
		} elseif ($f['userMode'] == 'none') $done[] = bp_t('Ingen kundebruger endnu – invitér fra kortets fane Brugere (Udskift ved brugeren "kunde").','No customer user yet – invite from the card\'s Users tab (Replace on the user "kunde").');
		unset($_SESSION['bp_opret_form']);
		vr_prg($done, "bogholder_panel.php?regnskab_id=$db_id");
	}
}

$acts = $tab == 'ny' ? "<a class=\"vr-btn\" href=\"bogholder_opret.php?tab=tilknyt\">".bp_t('Tilknyt eksisterende i stedet','Link an existing one instead')."</a>" : "<a class=\"vr-btn\" href=\"bogholder_opret.php\">".bp_t('Opret nyt i stedet','Create a new one instead')."</a>";
bp_open($crumbs, $tab == 'ny' ? bp_t('Opret kunderegnskab','Create customer account') : bp_t('Tilknyt eksisterende regnskab','Link existing account'), $tab == 'ny' ? bp_t('Regnskabet oprettes i kundens navn med standardkontoplan. Kunden ejer regnskabet; firmaet får adgang med det samme. Tager normalt under et minut.','The account is created in the customer\'s name with the standard chart of accounts. The customer owns it; the firm gets access at once. Usually takes under a minute.') : bp_t('Find kundens regnskab og send en anmodning. Kunden godkender via e-mail, og intet andet ændres: ikke pakke, ikke betaler, ikke andre bogholderes adgang.','Find the customer\'s account and send a request. The customer approves by e-mail and nothing else changes: not plan, not payer, not other bookkeepers\' access.'), $acts);
vr_note($notes, vr_flash_kind());
vr_tabs(array('ny' => array(bp_t('Opret kunderegnskab','Create customer account'), 'bogholder_opret.php', null), 'tilknyt' => array(bp_t('Tilknyt eksisterende','Link existing'), 'bogholder_opret.php?tab=tilknyt', count(bp_access_requests('open')) ?: null)), $tab);

if ($tab == 'ny') {
	$f = isset($_SESSION['bp_opret_form']) ? $_SESSION['bp_opret_form'] : array(); $v = fn($k, $d = '') => bp_h((string)($f[$k] ?? $d));
	$cur = count(bp_scheme_rows()); list(, $list, $pct, $tot) = bp_price_calc($cur); list(, , $pct1, $tot1) = bp_price_calc($cur + 1); $cat = ent_catalog();
	print "<form method=\"post\" id=\"opret\"><input type=\"hidden\" name=\"bp_action\" value=\"create\">";
	print "<section class=\"vr-sect\"><h2>".(bp_is_group() ? bp_t('Selskabet','The company') : bp_t('Kunden','The customer'))."</h2><div class=\"vr-card\"><div class=\"vr-form bp-wide\"><div class=\"bp-cols\"><label>".bp_t('Regnskabets navn i Saldi','Account name in Saldi')."<input class=\"vr-inp\" name=\"regnskab\" value=\"".$v('regnskab')."\" maxlength=\"60\" required id=\"fRegnskab\" oninput=\"var x=document.getElementById('fFirma');if(!x.dataset.touched)x.value=this.value\"></label><label>".bp_t('Firmanavn (stamdata)','Company name (master data)')."<input class=\"vr-inp\" name=\"firmanavn\" value=\"".$v('firmanavn')."\" maxlength=\"60\" id=\"fFirma\" oninput=\"this.dataset.touched=1\"></label><label>CVR<input class=\"vr-inp\" name=\"cvrnr\" value=\"".$v('cvrnr')."\" maxlength=\"20\" inputmode=\"numeric\"></label><label>".bp_t('Kontaktperson','Contact person')."<input class=\"vr-inp\" name=\"kontakt\" value=\"".$v('kontakt')."\" maxlength=\"60\"></label><label>".bp_t('Adresse','Address')."<input class=\"vr-inp\" name=\"addr1\" value=\"".$v('addr1')."\" maxlength=\"60\"></label><label>".bp_t('Postnr. og by','Zip and city')."<span class=\"vr-inline\" style=\"flex-wrap:nowrap\"><input class=\"vr-inp\" name=\"postnr\" value=\"".$v('postnr')."\" style=\"width:90px\" maxlength=\"10\"><input class=\"vr-inp\" name=\"bynavn\" value=\"".$v('bynavn')."\" maxlength=\"60\"></span></label><label>".bp_t('Telefon','Phone')."<input class=\"vr-inp\" name=\"tlf\" value=\"".$v('tlf')."\" maxlength=\"20\"></label><label>".bp_t('Kundens e-mail','Customer e-mail')."<input class=\"vr-inp\" type=\"email\" name=\"email\" value=\"".$v('email')."\" maxlength=\"60\"><span class=\"bp-note-inline\">".bp_t('bruges til godkendelser af opgraderinger og betalerskift','used for approving upgrades and payer changes')."</span></label></div></div></div></section>";
	if (bp_is_group()) { # 20261029 CL/ASR group: the plan is always chosen; invoiced via the parent by default, or the company pays itself
		print "<section class=\"vr-sect\" style=\"margin-top:22px\"><h2>".bp_t('Pakke og betaling','Plan and payment')."</h2><div class=\"vr-card\"><div class=\"vr-form bp-wide\"><label>".bp_t('Pakke','Plan')."<select name=\"plan\" class=\"vr-sel\">";
		foreach ($cat['plans'] as $pk => $pl) print "<option value=\"$pk\"".(($f['plan'] ?? 'professionel') == $pk ? " selected" : "").">".bp_h($pl['name'])." · ".($pl['price_ore'] === null ? bp_t('efter aftale','by agreement') : bp_kr((int)$pl['price_ore']).'/'.bp_t('md.','mo.'))."</option>";
		print "</select><span class=\"bp-note-inline\">".bp_t('Ingen prøveperiode – selskabet starter på den valgte pakke.','No trial – the company starts on the chosen plan.')."</span></label><div class=\"bp-choice\">";
		print "<label><input type=\"radio\" name=\"model\" value=\"partner\"".(($f['model'] ?? 'partner') == 'partner' ? " checked" : "")."><b>".bp_t('Faktureres via hovedselskabet','Invoiced via the parent company')."</b>".bp_t('Selskabets abonnement kommer på koncernens samlede faktura. Det er udgangspunktet.','The company\'s subscription goes on the group\'s combined invoice. This is the default.')."</label>";
		print "<label><input type=\"radio\" name=\"model\" value=\"customer\"".(($f['model'] ?? '') == 'customer' ? " checked" : "")."><b>".bp_t('Selskabet betaler selv','The company pays itself')."</b>".bp_t('Selskabet får sin egen faktura fra Saldi. Kan ændres senere under selskabets Handlinger.','The company gets its own invoice from Saldi. Can be changed later under the company\'s Actions.')."</label></div></div></div></section>";
	} else {
	print "<section class=\"vr-sect\" style=\"margin-top:22px\"><h2>".bp_t('Betalingsmodel','Payment model')."</h2><div class=\"vr-card\"><div class=\"vr-form bp-wide\"><div class=\"bp-choice\">";
	print "<label><input type=\"radio\" name=\"model\" value=\"partner\"".(($f['model'] ?? 'partner') == 'partner' ? " checked" : "")." onchange=\"modelChange()\"><b>".bp_t('Firmaet betaler – Finans i bogholderordningen','The firm pays – Finans in the scheme')."</b>".bp_t('Finans-pakken afregnes med jer efter mængderabat. Ingen prøveperiode. Én kundebruger er med uden merpris.','The Finans plan is settled with you at the volume discount. No trial. One customer user is included at no extra cost.')."<i>".bp_t('Jeres ordning','Your scheme').": $cur → ".($cur + 1)." ".bp_t('regnskaber','accounts').", ".bp_kr($tot)." → ".bp_kr($tot1)." ".bp_t('pr. md.','per month')." (".($pct + 0)." % → ".($pct1 + 0)." % ".bp_t('rabat','discount').")".(BP_PRICING_PROVISIONAL ? ' · '.bp_t('foreløbige satser','provisional rates') : '')."</i></label>";
	print "<label><input type=\"radio\" name=\"model\" value=\"customer\"".(($f['model'] ?? '') == 'customer' ? " checked" : "")." onchange=\"modelChange()\"><b>".bp_t('Kunden betaler selv','The customer pays')."</b>".bp_t('Saldi fakturerer kunden direkte for den valgte pakke. Professionel og Business starter med 30 dages gratis prøve, derefter Finans, hvis kunden ikke vælger.','Saldi invoices the customer directly for the chosen plan. Professionel and Business start with a 30-day free trial, then Finans unless the customer chooses.')."<span id=\"planBox\" style=\"display:block;margin-top:10px\"><select name=\"plan\" class=\"vr-sel\" style=\"width:100%\">"; foreach ($cat['plans'] as $pk => $pl) if ($pl['price_ore'] !== null) print "<option value=\"$pk\"".(($f['plan'] ?? 'professionel') == $pk ? " selected" : "").">".bp_h($pl['name'])." · ".bp_kr((int)$pl['price_ore'])."/".bp_t('md.','mo.').($pk != 'finans' ? ' · '.bp_t('30 dages prøve','30-day trial') : '')."</option>"; print "</select></span></label></div></div></div></section>";
	}
	print "<section class=\"vr-sect\" style=\"margin-top:22px\"><h2>".(bp_is_group() ? bp_t('Selskabets første bruger','The company\'s first user') : bp_t('Kundens bruger','The customer\'s user'))."</h2><div class=\"vr-card\"><div class=\"vr-form bp-wide\"><div class=\"bp-choice\" style=\"grid-template-columns:1fr 1fr 1fr\">";
	$um = $f['userMode'] ?? 'invite';
	print "<label><input type=\"radio\" name=\"userMode\" value=\"invite\"".($um == 'invite' ? " checked" : "")." onchange=\"userChange()\"><b>".bp_t('Invitér pr. e-mail','Invite by e-mail')."</b>".bp_t('Kunden vælger selv brugernavn og adgangskode via et link.','The customer chooses username and password through a link.')."</label><label><input type=\"radio\" name=\"userMode\" value=\"now\"".($um == 'now' ? " checked" : "")." onchange=\"userChange()\"><b>".bp_t('Opret nu','Create now')."</b>".bp_t('I vælger brugernavn og adgangskode og giver dem til kunden.','You choose username and password and hand them to the customer.')."</label><label><input type=\"radio\" name=\"userMode\" value=\"none\"".($um == 'none' ? " checked" : "")." onchange=\"userChange()\"><b>".bp_t('Ikke endnu','Not yet')."</b>".bp_t('Kun firmaet arbejder i regnskabet indtil videre.','Only the firm works in the account for now.')."</label></div>";
	print "<div id=\"uInvite\" class=\"bp-cols\"><label>".bp_t('Navn','Name')."<input class=\"vr-inp\" name=\"uname\" value=\"".$v('uname')."\"></label><label>E-mail<input class=\"vr-inp\" type=\"email\" name=\"uemail\" value=\"".$v('uemail')."\"></label><label style=\"grid-column:1/-1\">".bp_t('Besked (valgfri)','Message (optional)')."<input class=\"vr-inp\" name=\"umessage\" value=\"".$v('umessage')."\"></label></div>";
	print "<div id=\"uNow\" class=\"bp-cols\"><label>".bp_t('Brugernavn','Username')."<input class=\"vr-inp\" name=\"brugernavn\" value=\"".$v('brugernavn')."\" maxlength=\"80\"></label><span></span><label>".bp_t('Adgangskode (mindst 6 tegn)','Password (min. 6)')."<input class=\"vr-inp\" type=\"password\" name=\"kode\"></label><label>".bp_t('Gentag adgangskode','Repeat password')."<input class=\"vr-inp\" type=\"password\" name=\"kode2\"></label></div></div></div></section>";
	print "<section class=\"vr-sect\" style=\"margin-top:22px\"><h2>".bp_t('Internt','Internal')."</h2><div class=\"vr-card\"><div class=\"vr-form bp-wide\"><div class=\"bp-cols\"><label>".bp_t('Ansvarlig medarbejder','Responsible employee')."<select name=\"responsible\" class=\"vr-sel\"><option value=\"0\">".bp_t('– ingen –','– none –')."</option>"; foreach (bp_employees() as $e) if ($e['active']) print "<option value=\"".(int)$e['bruger_id']."\"".((int)($f['responsible'] ?? $me['id']) == (int)$e['bruger_id'] ? " selected" : "").">".bp_h($e['name'] ?: $e['brugernavn'])."</option>"; print "</select></label><label class=\"vr-chk\" style=\"align-self:end\"><input type=\"checkbox\" name=\"std_kto_plan\"".(!isset($f['regnskab']) || isset($_POST['std_kto_plan']) ? " checked" : "")."> ".findtekst('2689|Opret standardkontoplan', $sprog_id)."</label></div>";
	print "<div><button type=\"submit\" class=\"vr-btn vr-primary\" onclick=\"this.disabled=true;this.textContent='".bp_t('Opretter…','Creating…')."';this.form.submit()\">".bp_t('Opret kunderegnskab','Create customer account')."</button> <span class=\"bp-note-inline\">".bp_t('Alt logges i firmaets historik.','Everything is logged in the firm\'s history.')."</span></div></div></div></section></form>";
	print "<script>function modelChange(){if(".(bp_is_group() ? 'true' : 'false').")return;var c=document.querySelector('input[name=model][value=customer]').checked;document.getElementById('planBox').style.opacity=c?1:.45;document.querySelector('select[name=plan]').disabled=!c;}function userChange(){var m=document.querySelector('input[name=userMode]:checked').value;document.getElementById('uInvite').hidden=m!=='invite';document.getElementById('uNow').hidden=m!=='now';}modelChange();userChange();</script>";
} else {
	$s = trim((string)if_isset($_GET, '', 's')); $found = null; $fnote = '';
	if ($s !== '') { list($found, $fnote) = bp_find_ledger($s); if ($found && partner_link((int)$found['id'])) { $fnote = bp_t('I har allerede adgang til','You already have access to').' '.$found['regnskab']; $found = null; } }
	print "<section class=\"vr-sect\"><h2>".bp_t('Find regnskabet','Find the account')."</h2><div class=\"vr-card\"><form method=\"get\" class=\"vr-form bp-wide\"><input type=\"hidden\" name=\"tab\" value=\"tilknyt\"><div class=\"vr-inline\"><label style=\"flex:1;min-width:260px\">".bp_t('Regnskabsnummer, regnskabsnavn eller CVR','Account number, account name or CVR')."<input class=\"vr-inp\" name=\"s\" value=\"".bp_h($s)."\" required autofocus placeholder=\"".bp_t('Fx 391, Byens Blomster eller 12345678','e.g. 391, Byens Blomster or 12345678')."\"></label><button type=\"submit\" class=\"vr-btn\" style=\"align-self:flex-end\">".bp_t('Søg','Search')."</button></div><p class=\"bp-note-inline\">".bp_t('Navnet skal skrives præcist, som kunden har det i Saldi. Regnskabsnummeret står i kundens Saldi under Indstillinger.','The name must match the customer\'s name in Saldi exactly. The account number is in the customer\'s Saldi under Settings.')."</p></form>";
	if ($s !== '') {
		if ($found) print "<div style=\"padding:0 20px 18px\"><div class=\"bp-consequence\"><b>".bp_t('Fundet','Found').": ".bp_h($found['regnskab'])."</b> <span class=\"vr-mut2\">· ".bp_t('nr.','no.')." ".(int)$found['id']."</span><br>".($found['email'] ? bp_t('Anmodningen sendes til regnskabets e-mail','The request is sent to the account\'s e-mail').' <b>'.bp_h($found['email']).'</b>. '.bp_t('Kunden godkender med ét klik.','The customer approves with one click.') : bp_t('Regnskabet har ingen e-mail registreret, så anmodningen sendes til Saldi, der godkender på kundens vegne.','The account has no e-mail, so the request goes to Saldi, who approves on the customer\'s behalf.'))."<br>".bp_t('Pakke, betaler og andre bogholderes adgang ændres ikke. Adgangen kan altid fjernes igen.','Plan, payer and other bookkeepers\' access are not changed. The access can always be removed again.')."</div><form method=\"post\" class=\"vr-form bp-wide\" style=\"padding:0\"><input type=\"hidden\" name=\"bp_action\" value=\"request\"><input type=\"hidden\" name=\"regnskab_id\" value=\"".(int)$found['id']."\"><label>".bp_t('Besked til kunden (valgfri)','Message to the customer (optional)')."<input class=\"vr-inp\" name=\"message\" placeholder=\"".bp_t('Fx: Som aftalt pr. telefon overtager vi bogføringen fra 1. november','e.g. As agreed by phone we take over the bookkeeping from 1 November')."\"></label><div><button type=\"submit\" class=\"vr-btn vr-primary\">".bp_t('Send anmodning om adgang','Send access request')."</button></div></form></div>";
		else print "<div class=\"vr-foot\"><span class=\"vr-st\"><span class=\"vr-dot vr-warn\"></span>".bp_h($fnote)."</span></div>";
	}
	print "</div></section>";
	$reqs = array_slice(bp_access_requests(), 0, 10); $rt = array('open' => array(bp_t('Afventer svar','Awaiting reply'), 'vr-warn'), 'approved' => array(bp_t('Godkendt','Approved'), 'vr-ok'), 'rejected' => array(bp_t('Afvist','Rejected'), 'vr-bad'), 'cancelled' => array(bp_t('Trukket tilbage','Cancelled'), 'vr-off'), 'expired' => array(bp_t('Udløbet','Expired'), 'vr-bad'));
	print "<section class=\"vr-sect\" style=\"margin-top:22px\"><h2>".bp_t('Seneste anmodninger','Latest requests')."</h2><div class=\"vr-card\">";
	foreach ($reqs as $r) print "<div class=\"bp-row\"><div><b>".bp_h($r['regnskab'])."</b><small>".bp_t('sendt','sent')." ".bp_date($r['sent_at'])." ".bp_t('til','to')." ".($r['sent_to'] == bp_saldi_mail() ? 'Saldi' : bp_h($r['sent_to']))." · <span class=\"vr-st\"><span class=\"vr-dot ".$rt[$r['status']][1]."\"></span>".$rt[$r['status']][0]."</span></small></div><div class=\"bp-acts\"><a href=\"bogholder_firma.php?tab=invitationer#anmodninger\">".bp_t('Håndtér','Manage')." &rsaquo;</a></div></div>";
	if (!$reqs) print "<div class=\"vr-empty\"><span>".bp_t('Ingen anmodninger endnu.','No requests yet.')."</span></div>";
	print "</div></section>";
}
bp_close();
?>
