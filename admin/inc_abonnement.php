<?php
// --- admin/inc_abonnement.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. The subscription tab (area 1 "Pakker og abonnementer") as two functions shared by the
//                  operator's customer card (admin_panel.php) and, for now, regnskab.php. Uses includes/entitlements.php.
//                  ent_tab_actions() handles the POSTs (ent_action=...), ent_tab_render() prints the tab.

if (!function_exists('ent_tab_actions')) {
function ent_live_usage_quick($reg) { # users/items/postings from the customer db for the downgrade guard
	global $sqhost, $squser, $sqpass; $u = array();
	if (!$reg['db'] || !db_exists($reg['db'])) return $u;
	if (!@db_connect($sqhost, $squser, $sqpass, $reg['db'], __FILE__ . " linje " . __LINE__)) return $u;
	if (tbl_exists('brugere')) { $cols = array(); $q = db_select("select column_name from information_schema.columns where table_name = 'brugere'", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $cols[] = $r['column_name']; $r = db_fetch_array(db_select("select count(*) as n from brugere".(in_array('revisor', $cols) ? " where revisor is not true" : ""), __FILE__ . " linje " . __LINE__)); $u['users_active'] = (int)$r['n']; }
	if (tbl_exists('transaktioner')) { $r = db_fetch_array(db_select("select count(id) as n from transaktioner where logdate >= '".date('Y-m-d', strtotime('-1 year'))."'", __FILE__ . " linje " . __LINE__)); $u['postings_12m'] = (int)$r['n']; }
	if (tbl_exists('varer')) { $r = db_fetch_array(db_select("select count(id) as n from varer", __FILE__ . " linje " . __LINE__)); $u['items'] = (int)$r['n']; }
	include("../includes/connect.php");
	return $u;
}
function ent_tab_actions($rid, $reg, &$notes) {
	if (!isset($_POST['ent_action'])) return;
	$ea = $_POST['ent_action']; $err = '';
	if ($ea == 'set_plan') {
		$target = if_isset($_POST, '', 'plan_key'); $ef = trim(if_isset($_POST, '', 'effective_from')); $reason = trim(if_isset($_POST, '', 'reason'));
		$conf = ent_conflicts($target, ent_live_usage_quick($reg));
		if ($conf && !isset($_POST['force'])) { $notes[] = vr_t('Nedgraderingen er ikke gennemført. Løs konflikterne, eller markér "Gennemfør alligevel":', 'Downgrade not applied. Resolve the conflicts or tick "Apply anyway":'); foreach ($conf as $cf) $notes[] = '• '.$cf; }
		else { $err = ent_set_plan($rid, $target, $ef, $reason.($conf ? ' (gennemført trods konflikter)' : '')); if (!$err) $notes[] = ($ef && $ef > date('Y-m-d')) ? vr_t('Pakkeskiftet er planlagt','Plan change scheduled') : vr_t('Pakken er ændret','Plan changed'); }
	} elseif ($ea == 'cancel_change') { ent_cancel_change($rid, (int)if_isset($_POST, 0, 'change_id')); $notes[] = vr_t('Planlagt ændring annulleret','Scheduled change cancelled'); }
	elseif ($ea == 'set_addon') { $err = ent_set_addon($rid, if_isset($_POST, '', 'addon_key'), if_isset($_POST, 1, 'quantity'), trim(if_isset($_POST, '', 'expires_at'))); if (!$err) $notes[] = vr_t('Tillæg gemt','Add-on saved'); }
	elseif ($ea == 'remove_addon') { ent_remove_addon($rid, if_isset($_POST, '', 'addon_key')); $notes[] = vr_t('Tillæg fjernet','Add-on removed'); }
	elseif ($ea == 'add_override') { $err = ent_add_override($rid, if_isset($_POST, '', 'kind'), trim(if_isset($_POST, '', 'key')), trim(if_isset($_POST, '', 'value')), trim(if_isset($_POST, '', 'reason')), trim(if_isset($_POST, '', 'expires_at'))); if (!$err) $notes[] = vr_t('Tilsidesættelse oprettet','Override created'); }
	elseif ($ea == 'expire_override') { ent_expire_override($rid, (int)if_isset($_POST, 0, 'override_id')); $notes[] = vr_t('Tilsidesættelsen er udløbet','Override expired'); }
	if ($err) $notes[] = $err;
}
function ent_tab_render($rid, $ent, $cat) {
	global $sprog_id;

	$P = $ent['plan']; $today = date('Y-m-d');
	$lim = fn($v) => $v === null ? vr_t('ubegrænset','unlimited') : vr_num($v);
	print "<div class=\"vr-grid2\">\n<section class=\"vr-sect\"><h2>".vr_t('Pakke','Plan')."</h2><div class=\"vr-card vr-kv\">";
	if ($P) {
		$price = $ent['price_ore'] === null ? vr_t('efter aftale','by agreement') : ent_kr($ent['price_ore']).' / '.($ent['interval'] == 'year' ? vr_t('år','year') : vr_t('md.','month'));
		$src = array('manual' => vr_t('manuel (sat af operatør)','manual (set by operator)'), 'stripe' => 'Stripe', 'override' => vr_t('tilsidesættelse','override'), 'migration' => vr_t('overgangsordning','grandfathered'), 'scheduled' => vr_t('planlagt skift','scheduled change'));
		$rowsKv = array(
			array(vr_t('Pakke','Plan'), '<b>'.vr_h($P['name']).'</b>'.(count(array_filter($ent['overrides'], fn($o) => $o['kind']=='plan')) ? ' <span class="vr-pill">'.vr_t('via tilsidesættelse','via override').'</span>' : '')),
			array(vr_t('Pris','Price'), $price.' <span class="vr-mut2">'.vr_t('ekskl. moms','excl. VAT').'</span>'),
			array(vr_t('Faktureringsinterval','Billing interval'), $ent['interval'] == 'year' ? vr_t('Årligt','Yearly') : vr_t('Månedligt','Monthly')),
			array(vr_t('Kilde','Source'), isset($src[$ent['row']['source']]) ? $src[$ent['row']['source']] : vr_h($ent['row']['source'])),
			array(vr_t('Gældende fra','Valid from'), $ent['row']['valid_from'] ? vr_h($ent['row']['valid_from']) : '–'),
			array(vr_t('Stripe-status','Stripe status'), $ent['row']['subscription_status'] && $ent['row']['subscription_status'] != 'none' ? vr_h($ent['row']['subscription_status']) : '<span class="vr-mut2">'.vr_t('ikke koblet','not linked').'</span>'),
			array(vr_t('Håndhævelse','Enforcement'), $ent['row']['enforcement'] == 'enforce' ? vr_t('Håndhæves','Enforced') : vr_t('Observerer (ingen begrænsning endnu)','Observe (no gating yet)')),
			array(vr_t('Katalog','Catalogue'), $cat['version'] ? vr_h($cat['version']['name']) : '–'),
		);
	} else $rowsKv = array(array(vr_t('Pakke','Plan'), '<span class="vr-mut2">'.vr_t('Ingen pakke registreret. Vælg en nedenfor.','No plan registered. Choose one below.').'</span>'));
	foreach ($rowsKv as $row) print "<div><span>$row[0]</span><b>$row[1]</b></div>";
	print "</div>";
	// change plan
	print "<h2 style=\"margin-top:18px\">".vr_t('Skift pakke','Change plan')."</h2><div class=\"vr-card\"><form method=\"post\" class=\"vr-form\"><input type=\"hidden\" name=\"ent_action\" value=\"set_plan\">";
	print "<label>".vr_t('Ny pakke','New plan')."<select name=\"plan_key\" class=\"vr-sel\">";
	foreach ($cat['plans'] as $k => $pl) print "<option value=\"$k\"".($P && $P['plan_key'] == $k ? " selected" : "").">".vr_h($pl['name'])." · ".($pl['price_ore'] === null ? vr_t('efter aftale','by agreement') : ent_kr((int)$pl['price_ore']).'/md.')."</option>";
	print "</select></label><label>".vr_t('Ikrafttrædelse','Effective from')."<input class=\"vr-inp\" type=\"date\" name=\"effective_from\" value=\"$today\" min=\"$today\"></label><label>".vr_t('Begrundelse','Reason')."<input class=\"vr-inp\" name=\"reason\" placeholder=\"".vr_t('Fx aftale pr. mail 10/10','e.g. agreed by mail 10/10')."\"></label>";
	print "<label class=\"vr-chk\"><input type=\"checkbox\" name=\"force\"> ".vr_t('Gennemfør alligevel, selv om forbruget overstiger den nye pakkes grænser','Apply anyway, even if usage exceeds the new plan limits')."</label>";
	print "<div><button type=\"submit\" class=\"vr-btn vr-primary\">".vr_t('Skift pakke','Change plan')."</button> <span class=\"vr-mut2\" style=\"font-size:12.5px\">".vr_t('En dato i fremtiden planlægger skiftet; nedgradering tjekkes mod aktuelt forbrug.','A future date schedules the change; downgrades are checked against current usage.')."</span></div></form>";
	if ($ent['pending']) { print "<div class=\"vr-foot\" style=\"display:block\"><b style=\"font-weight:600\">".vr_t('Planlagte ændringer','Scheduled changes')."</b>"; foreach ($ent['pending'] as $pc) print "<div class=\"vr-frow\" style=\"padding:8px 0\"><div><b>".vr_h(ent_plan_name($pc['plan_key']))." ".vr_t('fra','from')." ".vr_h($pc['effective_from'])."</b><small>".vr_h($pc['reason'])." · ".vr_h($pc['created_by'])."</small></div><span></span><form method=\"post\"><input type=\"hidden\" name=\"ent_action\" value=\"cancel_change\"><input type=\"hidden\" name=\"change_id\" value=\"".(int)$pc['id']."\"><button class=\"vr-link\" type=\"submit\">".vr_t('Annullér','Cancel')."</button></form></div>"; print "</div>"; }
	print "</div></section>\n";
	// included
	print "<section class=\"vr-sect\"><h2>".vr_t('Inkluderet i pakken','Included in the plan')."</h2><div class=\"vr-card vr-kv\">";
	if ($P) {
		$ovKeys = array(); foreach ($ent['overrides'] as $o) if ($o['kind'] == 'limit') $ovKeys[$o['key']] = $o;
		$mark = fn($k) => isset($ovKeys[$k]) ? ' <span class="vr-pill">'.vr_t('tilsidesat','overridden').'</span>' : '';
		print "<div><span>".vr_t('Brugere','Users')."</span><b>".$ent['limits']['included_users']." ".vr_t('inkl., maks.','incl., max')." ".$lim($ent['limits']['max_users']).$mark('max_users').($ent['extra_user_price_ore'] ? " <span class=\"vr-mut2\">· ".vr_t('ekstra bruger','extra user')." ".ent_kr($ent['extra_user_price_ore'])."/md.</span>" : "")."</b></div>";
		print "<div><span>".vr_t('Posteringer (12 mdr.)','Entries (12 m)')."</span><b>".$lim($ent['limits']['included_postings']).$mark('included_postings')."</b></div>";
		print "<div><span>".vr_t('Varer','Items')."</span><b>".$lim($ent['limits']['max_items']).$mark('max_items')."</b></div>";
		print "<div><span>".vr_t('Webshops','Webshops')."</span><b>".$lim($ent['limits']['max_webshops']).$mark('max_webshops')."</b></div>";
		print "</div>";
		$areas = array(); foreach ($cat['feature_names'] as $fk => $fn) { $lv = isset($ent['features'][$fk]) ? $ent['features'][$fk] : 'none'; $areas[$fn[1]][] = array($fk, $fn[0], $lv); }
		print "<h2 style=\"margin-top:18px\">".vr_t('Moduler og funktioner','Modules and features')."</h2><div class=\"vr-card\">";
		foreach ($areas as $an => $fs) { print "<div class=\"vr-frow\" style=\"background:var(--surface-2);padding:7px 20px\"><b style=\"font-size:12.5px;color:var(--text-2)\">".vr_h($an)."</b><span></span><span></span></div>"; foreach ($fs as $f) { $lvTxt = array('included' => vr_t('Inkluderet','Included'), 'basic' => vr_t('Basis','Basic'), 'addon' => vr_t('Tillæg','Add-on'), 'none' => vr_t('Ikke med','Not included')); print "<div class=\"vr-frow".($f[2]=='none' ? " vr-off" : "")."\" style=\"padding:8px 20px\"><div><b style=\"font-weight:400\">".vr_h($f[1])."</b></div><span class=\"vr-lvl\">".$lvTxt[$f[2]]."</span><span class=\"vr-st\"><span class=\"vr-dot ".($f[2]=='included'||$f[2]=='basic' ? 'vr-ok' : 'vr-off')."\"></span></span></div>"; } }
		print "</div>";
	} else print "<div><span class=\"vr-mut2\">–</span></div></div>";
	print "</section>\n</div>\n";
	// add-ons
	print "<div class=\"vr-grid2\" style=\"margin-top:22px\">\n<section class=\"vr-sect\"><h2>".vr_t('Tillæg','Add-ons')." <small>".count($ent['addons'])."</small></h2><div class=\"vr-card\">";
	foreach ($ent['addons'] as $ak => $ar) { $ad = isset($cat['addons'][$ak]) ? $cat['addons'][$ak] : null; print "<div class=\"vr-frow\"><div><b>".vr_h($ad ? $ad['name'] : $ak).($ad && $ad['quantity_based'] ? " × ".(int)$ar['quantity'] : "")."</b><small>".($ad && $ad['price_ore'] !== null ? ent_kr((int)$ad['price_ore'] * max(1,(int)$ar['quantity'])).'/md. · ' : '').vr_h($ar['source']).($ar['expires_at'] ? ' · '.vr_t('udløber','expires').' '.vr_h($ar['expires_at']) : '')."</small></div><span></span>".($ar['source'] != 'override' ? "<form method=\"post\"><input type=\"hidden\" name=\"ent_action\" value=\"remove_addon\"><input type=\"hidden\" name=\"addon_key\" value=\"".vr_h($ak)."\"><button class=\"vr-link\" type=\"submit\" onclick=\"return confirm('".vr_t('Fjern tillægget?','Remove the add-on?')."')\">".vr_t('Fjern','Remove')."</button></form>" : "<span></span>")."</div>"; }
	if (!$ent['addons']) print "<div class=\"vr-empty\"><b>".vr_t('Ingen tillæg','No add-ons')."</b></div>";
	if ($P) { print "<div class=\"vr-foot\"><form method=\"post\" class=\"vr-inline\"><input type=\"hidden\" name=\"ent_action\" value=\"set_addon\"><select name=\"addon_key\" class=\"vr-sel\">"; foreach ($cat['addons'] as $ak => $ad) { $avail = in_array($P['plan_key'], explode(',', $ad['available_on'])); print "<option value=\"$ak\"".($avail ? "" : " disabled").">".vr_h($ad['name']).($ad['price_ore'] !== null ? ' · '.ent_kr((int)$ad['price_ore']).'/md.' : '').($avail ? '' : ' · '.vr_t('ikke på denne pakke','not on this plan'))."</option>"; } print "</select><input class=\"vr-inp\" type=\"number\" name=\"quantity\" value=\"1\" min=\"1\" style=\"width:70px\" title=\"".vr_t('Antal','Quantity')."\"><input class=\"vr-inp\" type=\"date\" name=\"expires_at\" title=\"".vr_t('Udløber (valgfrit)','Expires (optional)')."\"><button type=\"submit\" class=\"vr-btn\">+ ".vr_t('Tilføj','Add')."</button></form></div>"; }
	print "</div></section>\n";
	// overrides
	print "<section class=\"vr-sect\"><h2>".vr_t('Individuelle aftaler (tilsidesættelser)','Individual terms (overrides)')." <small>".count($ent['overrides'])."</small></h2><div class=\"vr-card\">";
	$kinds = array('plan' => vr_t('Pakke','Plan'), 'feature' => vr_t('Funktion','Feature'), 'limit' => vr_t('Grænse','Limit'), 'addon' => vr_t('Tillæg','Add-on'));
	foreach ($ent['overrides'] as $o) print "<div class=\"vr-frow\"><div><b>".vr_h($kinds[$o['kind']])." · ".vr_h($o['key'])." = ".vr_h($o['value'])."</b><small>".vr_h($o['reason'])." · ".vr_h($o['created_by'])." ".vr_h(substr($o['created_at'],0,10)).($o['expires_at'] ? ' · '.vr_t('udløber','expires').' '.vr_h($o['expires_at']) : ' · '.vr_t('uden udløb','no expiry'))."</small></div><span></span><form method=\"post\"><input type=\"hidden\" name=\"ent_action\" value=\"expire_override\"><input type=\"hidden\" name=\"override_id\" value=\"".(int)$o['id']."\"><button class=\"vr-link\" type=\"submit\" onclick=\"return confirm('".vr_t('Lad tilsidesættelsen udløbe nu?','Expire the override now?')."')\">".vr_t('Udløb nu','Expire now')."</button></form></div>";
	if (!$ent['overrides']) print "<div class=\"vr-empty\"><b>".vr_t('Ingen individuelle aftaler','No individual terms')."</b><span>".vr_t('Brug dem til prøveperioder, goodwill-grænser og pilotfunktioner. Begrundelse og udløb er påkrævet.','Use for trials, goodwill limits and pilot features. Reason and expiry are required.')."</span></div>";
	print "<div class=\"vr-foot\" style=\"display:block\"><form method=\"post\" class=\"vr-form\" style=\"padding:0;max-width:none\"><input type=\"hidden\" name=\"ent_action\" value=\"add_override\"><div class=\"vr-inline\"><select name=\"kind\" class=\"vr-sel\" id=\"ovKind\">"; foreach ($kinds as $k => $n) print "<option value=\"$k\">$n</option>"; print "</select><input class=\"vr-inp\" name=\"key\" list=\"ovKeys\" placeholder=\"".vr_t('nøgle (fx max_users, lager.styring, booking)','key (e.g. max_users, lager.styring, booking)')."\" style=\"min-width:260px\"><datalist id=\"ovKeys\">"; foreach (array('max_users','included_postings','max_items','max_webshops') as $k) print "<option value=\"$k\">"; foreach ($cat['feature_names'] as $fk => $fn) print "<option value=\"$fk\">"; foreach ($cat['addons'] as $ak => $ad) print "<option value=\"$ak\">"; foreach ($cat['plans'] as $pk => $pl) print "<option value=\"$pk\">"; print "</datalist><input class=\"vr-inp\" name=\"value\" placeholder=\"".vr_t('værdi (on/off, tal, pakke)','value (on/off, number, plan)')."\" style=\"width:160px\"><input class=\"vr-inp\" type=\"date\" name=\"expires_at\" title=\"".vr_t('Udløber','Expires')."\"></div><div class=\"vr-inline\" style=\"margin-top:8px\"><input class=\"vr-inp\" name=\"reason\" placeholder=\"".vr_t('Begrundelse (påkrævet)','Reason (required)')."\" style=\"flex:1;min-width:260px\" required><button type=\"submit\" class=\"vr-btn\">+ ".vr_t('Opret','Create')."</button></div></form></div>";
	print "</div></section>\n</div>\n";
}
}
?>
