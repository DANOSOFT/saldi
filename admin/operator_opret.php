<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- admin/operator_opret.php --- ver 5.1.0 --- 2026-10-29 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// History of the account creation itself: includes/opretfunc.php and admin/opret.php.
// 20261012 CL/ASR Operator copy of admin/opret.php for Saldi staff only (vr_require_operator). The original page is
//                  unchanged for bookkeepers and everyone else; this copy carries the operator panel's changes.
// 20261029 CL/ASR The account is now created by create_regnskab() from includes/opretfunc.php instead of a copy of
//                  opret(), so the page keeps only the form, the account type, plan/trial and partner choices.

if (defined('VR_OPRET_FUNCTIONS_ONLY')) return; # 20261012 CL/ASR bogholder_opret.php includes this file for opret()/forside() only (declared at compile time; the page code below is skipped)
@session_start();
$s_id=session_id();

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/std_func.php");
include(__DIR__ . "/../includes/opretfunc.php"); // create_regnskab(), shared with admin/opret.php and the sign-up API

$modulnr=101;

$online_result=include("../includes/online.php"); // the only auth check, must run for every request regardless of $_POST content (SD-615)
if ($online_result === 'Session expired' || $db != $sqdb) {
	print "<BODY onLoad=\"javascript:alert('".findtekst('1905|Hmm du har vist ikke noget at gøre her! Dit IP nummer, brugernavn og regnskab er registreret!', $sprog_id)."')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
	exit;
}

# if (!$top_bund) $top_bund="style=\"border: 1px solid rgb(0, 0, 0); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/knap_bg.gif\";";
$fra_formular=NULL;

include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("../includes/entitlements.php");
include("vr_ui.php");
vr_require_operator(); # Saldi staff only; everyone else keeps the existing pages
include("inc_proeve.php");
$vr_type = (string)if_isset($_POST, if_isset($_GET, 'normal', 'type'), 'type'); if (!in_array($vr_type, array_merge(array('normal'), partner_kinds()), true)) $vr_type = 'normal';
partner_tables_ensure();
vr_open(array(array('Administrationspanel', 'operator_panel.php'), findtekst('339|Opret regnskab', $sprog_id)), findtekst('339|Opret regnskab', $sprog_id), vr_t('Opretter et nyt regnskab med administrator og standardkontoplan. Vælg typen øverst: et normalt kunderegnskab, eller en bogholders, revisors eller koncerns eget regnskab. Tager normalt under et minut.', 'Creates a new account with an administrator and the standard chart of accounts. Choose the type at the top. Usually takes less than a minute.'));
print "<section class=\"vr-sect\"><div class=\"vr-card\"><div class=\"vr-legacy\">";
if ($db_type=="mysql" or $db_type=="mysqli") {

	echo '<p class="vr-sub">This installation may take up to 15 minutes. Thank you for your patience.</p>';
}

if ($_POST){
	if (isset($_POST['fra_formular'])) {
		$fra_formular = $_POST['fra_formular'];
		$kontakt=db_escape_string(trim($_POST['kontakt']));
		$firmanavn=db_escape_string(trim($_POST['firmanavn']));
		$addr1=db_escape_string(trim($_POST['addr1']));
		$addr2=db_escape_string(trim($_POST['addr2']));
		$postnr=db_escape_string(trim($_POST['postnr']));
		$bynavn=db_escape_string(trim($_POST['bynavn']));
		$tlf=db_escape_string(trim($_POST['tlf']));
		$email=db_escape_string(trim($_POST['email']));
		$cvrnr=db_escape_string(trim($_POST['cvrnr']));
	}	else {
		$kontakt=$firmanavn=$addr1=$addr2=$postnr=$bynavn=$tlf=$email=$cvrnr=NULL;

	}
	$regnskab=db_escape_string(trim($_POST['regnskab']));
	$brugernavn=db_escape_string(trim($_POST['brugernavn']));
	$passwd=db_escape_string(trim($_POST['passwd']));
	$passwd2=db_escape_string(trim($_POST['passwd2']));
	(isset($_POST['posteringer']))?$posteringer=(int)$_POST['posteringer']:$posteringer=0;
	(isset($_POST['brugerantal']))?$brugerantal=(int)$_POST['brugerantal']:$brugerantal=0;
	(isset($_POST['std_kto_plan']))?$std_kto_plan=$_POST['std_kto_plan']:$std_kto_plan=NULL;
	if (is_input_too_long(trim($_POST['regnskab']), 60)) {
		print "<BODY onLoad=\"javascript:alert('".findtekst('5150|Regnskabsnavnet må højst være 60 tegn', $sprog_id)."')\">";
		forside($regnskab,$brugernavn);
		exit;
	}
	if (is_input_too_long(trim($_POST['brugernavn']))) {
		print "<BODY onLoad=\"javascript:alert('".findtekst('5149|Brugernavnet må højst være 80 tegn', $sprog_id)."')\">";
		forside($regnskab,$brugernavn);
		exit;
	}
	if ((($revisorregnskab && $passwd) || !$revisorregnskab)  && $passwd!=$passwd2 ) {
		print "<BODY onLoad=\"javascript:alert('Adgangskoder er ikke ens')\">";
		forside($regnskab,$brugernavn);
		exit;
	}
/*
	if ($db_encode=="UTF8") {
		$firmanavn=mb_convert_encoding($firmanavn, 'UTF-8', 'ISO-8859-1');
		$addr1=mb_convert_encoding($addr1, 'UTF-8', 'ISO-8859-1');
		$addr2=mb_convert_encoding($addr2, 'UTF-8', 'ISO-8859-1');
		$bynavn=mb_convert_encoding($bynavn, 'UTF-8', 'ISO-8859-1');
		$kontakt=mb_convert_encoding($kontakt, 'UTF-8', 'ISO-8859-1');
		$regnskab=mb_convert_encoding($regnskab, 'UTF-8', 'ISO-8859-1');
		$brugernavn=mb_convert_encoding($brugernavn, 'UTF-8', 'ISO-8859-1');
		$passwd=mb_convert_encoding($passwd, 'UTF-8', 'ISO-8859-1');
	}
*/


	$x=0;
#	$tmp=db_escape_string($regnskab);
	$query = db_select("select * from regnskab where regnskab='$regnskab'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		print "<BODY onLoad=\"javascript:alert('".findtekst('2682|Regnskab', $sprog_id)." $regnskab ".findtekst('2683|findes allerede', $sprog_id)."')\">";
		forside($regnskab,$brugernavn);
		exit;
	} elseif ($vr_type != 'normal' && isset($_POST['partner_login']) && !partner_login_free(trim($_POST['brugernavn']))) {
		print "<BODY onLoad=\"javascript:alert('".vr_t('Brugernavnet findes allerede som login i administrationslaget. Vælg et andet administratornavn, eller fravælg login til panelet.','The username already exists as an administration login. Choose another administrator name, or untick the panel login.')."')\">";
		forside($regnskab,$brugernavn);
		exit;
	} elseif ((!$revisorregnskab && (!$brugernavn||!$passwd)) || (!$regnskab))  {
		print "<BODY onLoad=\"javascript:alert('".findtekst('2684|Alle felter SKAL udfyldes', $sprog_id)."')\">";
		forside($regnskab,$brugernavn);
		exit();
	} else {
		// 20261029 CL/ASR The account is created by create_regnskab() in includes/opretfunc.php, shared with admin/opret.php
		//                  and the sign-up API (it rolls back on failure and leaves the connection on the master database).
		$res = create_regnskab($regnskab, $brugernavn, $passwd, $email, (int)$posteringer, (int)$brugerantal, $std_kto_plan);
		if (!$res['ok']) {
			print "<BODY onLoad=\"javascript:alert('".vr_t('Regnskabet','The account')." $regnskab ".vr_t('kunne ikke oprettes','could not be created')."')\">";
			forside($regnskab,$brugernavn);
			print "</div></div></section>"; vr_close();
			exit();
		}
		$db_id = (int)$res['db_id'];
		db_modify("update regnskab set lukket = '' where id = '$db_id' and lukket is null", __FILE__ . " linje " . __LINE__); # NULL hid new accounts from "lukket != 'on'" lists and the nightly job
		// 20261011 CL/ASR plan/trial and partner link chosen on the form (entitlements spec §6.5, panels spec D6); trial follow-up (inc_proeve.php)
		$vr_trial = (string)if_isset($_POST, '', 'trial'); $vr_partner = (int)if_isset($_POST, 0, 'partner_id');
		if (in_array($vr_trial, array('professionel','business'), true)) ent_start_trial($db_id, $vr_trial, 30);
		elseif ($vr_trial === 'finans') ent_write_plan($db_id, 'finans', date('Y-m-d'), 'manual');
		tr_register($db_id);
		if ($vr_type != 'normal') { $vr_partner = 0; list($vr_pid, $vr_perr) = partner_create_for_ledger($db_id, $vr_type, trim((string)$_POST['regnskab']), trim((string)if_isset($_POST, '', 'cvr')), isset($_POST['can_create']), isset($_POST['partner_login']) ? array(trim($_POST['brugernavn']), trim($_POST['passwd'])) : null); }
		if ($vr_partner && ($vr_pp = db_fetch_array(db_select("select * from partners where id = '$vr_partner' and status = 'active'", __FILE__ . " linje " . __LINE__)))) {
			db_modify("insert into regnskab_partners (regnskab_id, partner_id, access, created_by, note) values ('$db_id', '$vr_partner', '".($vr_pp['kind'] == 'koncern' ? 'owner' : 'full')."', '".(int)partner_current_user()['id']."', 'oprettet med regnskabet')", __FILE__ . " linje " . __LINE__);
			partner_log('partner.link_added', $db_id, $vr_partner, $vr_pp['name'].' (ved oprettelse)');
		}
		// the operator lands on the new account's card with a note
		$vr_url = 'operator_panel.php?'.http_build_query(array('regnskab_id' => $db_id, 'msg' => array(vr_t('Regnskabet','Account').' '.$res['regnskab'].' '.vr_t('er oprettet og aktiveret','was created and activated'))));
		print "<meta http-equiv=\"refresh\" content=\"0;URL=".vr_h($vr_url)."\">";
		exit();
	}
} else {
	if (!isset($regnskab)) $regnskab='';
	if (!isset($brugernavn)) $brugernavn='';
	forside($regnskab,$brugernavn);
	print "</div></div></section>"; vr_close();
}

function forside($regnskab,$brugernavn) {
	global $charset;
	global $sprog_id;
	global $vr_type;
	$types = array('normal' => vr_t('Normalt regnskab','Standard account'), 'bogholder' => vr_t('Bogholder','Bookkeeper'), 'revisor' => vr_t('Revisor','Auditor'), 'koncern' => vr_t('Koncern (moderselskab)','Group (parent company)'));
	print "<form name=\"debitorkort\" action=\"operator_opret.php\" method=\"post\" class=\"vr-form\" id=\"vrOpret\">";
	print "<label>".vr_t('Regnskabstype','Account type')."<select name=\"type\" class=\"vr-sel\" id=\"vrType\" onchange=\"vrTypeChange()\">"; foreach ($types as $k => $t) print "<option value=\"$k\"".($vr_type == $k ? " selected" : "").">".vr_h($t)."</option>"; print "</select><span class=\"vr-mut2\" id=\"vrTypeHelp\" style=\"font-size:12.5px\"></span></label>";
	print "<label>".findtekst('2685|Navn på regnskab', $sprog_id)."<input class=\"vr-inp\" type=\"text\" maxlength=\"60\" name=\"regnskab\" value=\"".vr_h($regnskab)."\" required></label>";
	print "<label>".findtekst('2686|Administrators navn', $sprog_id)."<input class=\"vr-inp\" type=\"text\" maxlength=\"80\" name=\"brugernavn\" value=\"".vr_h($brugernavn)."\" required></label>";
	print "<label>".findtekst('2687|Administrators adgangskode', $sprog_id)."<input class=\"vr-inp\" type=\"password\" name=\"passwd\" required></label>";
	print "<label>".findtekst('2688|Gentag adgangskode', $sprog_id)."<input class=\"vr-inp\" type=\"password\" name=\"passwd2\" required></label>";
	print "<label class=\"vr-chk\"><input type=\"checkbox\" name=\"std_kto_plan\" checked> ".findtekst('2689|Opret standardkontoplan', $sprog_id)."</label>";
	print "<label>".vr_t('Pakke og prøveperiode','Plan and trial')."<select name=\"trial\" class=\"vr-sel\"><option value=\"\">".vr_t('Ingen pakke endnu','No plan yet')."</option><option value=\"finans\">Finans</option><option value=\"professionel\" selected>".vr_t('30 dages prøve på Professionel, derefter Finans','30-day trial of Professionel, then Finans')."</option><option value=\"business\">".vr_t('30 dages prøve på Business, derefter Finans','30-day trial of Business, then Finans')."</option></select></label>";
	print "<label id=\"vrLinkPartner\">".vr_t('Tilknyt bogholder, revisor eller koncern (valgfrit)','Link bookkeeper, auditor or group (optional)')."<select name=\"partner_id\" class=\"vr-sel\"><option value=\"0\">–</option>"; foreach (partner_all() as $vp) print "<option value=\"".(int)$vp['id']."\">".vr_h($vp['name'])." · ".vr_h(vr_kind($vp['kind']))."</option>"; print "</select></label>";
	print "<div id=\"vrPartnerFields\" class=\"vr-form\" style=\"padding:14px 16px;margin:2px 0;background:var(--surface-2);border-radius:10px;max-width:none\">";
	print "<label>CVR<input class=\"vr-inp\" name=\"cvr\" value=\"".vr_h((string)if_isset($_POST, '', 'cvr'))."\" maxlength=\"20\"></label>";
	print "<label class=\"vr-chk\"><input type=\"checkbox\" name=\"partner_login\" value=\"1\" checked> ".vr_t('Opret login til bogholderpanelet med samme navn og adgangskode','Create a panel login with the same name and password')."</label>";
	print "<label class=\"vr-chk\"><input type=\"checkbox\" name=\"can_create\" value=\"1\"> <span id=\"vrCanCreate\">".vr_t('Må selv oprette kunderegnskaber','May create customer accounts')."</span></label>";
	print "</div>";
	print "<div><button type=\"submit\" class=\"vr-btn vr-primary\" accesskey=\"g\" name=\"submit\" value=\"1\" id=\"vrSubmit\">".findtekst('339|Opret regnskab', $sprog_id)."</button></div>";
	$help = array('normal' => vr_t('Et almindeligt kunderegnskab.','A standard customer account.'), 'bogholder' => vr_t('Bogholderens eget regnskab. Bogholderen får et panel med sine kunder, som tilknyttes bagefter.','The bookkeeper\'s own account. They get a panel with their customers, linked afterwards.'), 'revisor' => vr_t('Revisorens eget regnskab. Virker som bogholder, men vises som revisor.','The auditor\'s own account. Works like a bookkeeper, shown as auditor.'), 'koncern' => vr_t('Moderselskabets regnskab. Datterselskaber tilknyttes bagefter og ejes af koncernen.','The parent company\'s account. Subsidiaries are linked afterwards and owned by the group.'));
	$btn = array('normal' => findtekst('339|Opret regnskab', $sprog_id), 'bogholder' => vr_t('Opret bogholder','Create bookkeeper'), 'revisor' => vr_t('Opret revisor','Create auditor'), 'koncern' => vr_t('Opret koncern','Create group'));
	print "<script>var VR_HELP=".json_encode($help).",VR_BTN=".json_encode($btn).";function vrTypeChange(){var t=document.getElementById('vrType').value,p=t!=='normal';document.getElementById('vrPartnerFields').hidden=!p;document.getElementById('vrLinkPartner').hidden=p;document.getElementById('vrTypeHelp').textContent=VR_HELP[t];document.getElementById('vrSubmit').textContent=VR_BTN[t];document.getElementById('vrCanCreate').textContent=t==='koncern'?'".vr_t('Må selv oprette datterselskaber','May create subsidiaries')."':'".vr_t('Må selv oprette kunderegnskaber','May create customer accounts')."';}vrTypeChange();</script>";
	print "</form>";
}


?>
</tbody></table>
</body></html>
