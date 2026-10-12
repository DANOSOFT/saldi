<?php
// --- admin/inc_abonnement.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. The subscription tab (area 1 "Pakker og abonnementer") as two functions shared by the
//                  operator's customer card (operator_panel.php) and, for now, regnskab.php. Uses includes/entitlements.php.
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
	elseif ($ea == 'feature_toggle') { # on/off within the plan: kind='feature' override; 'on' for a locked feature needs reason+expiry
		$fk = trim(if_isset($_POST, '', 'feature_key')); $to = if_isset($_POST, 'off', 'to') == 'on' ? 'on' : 'off';
		db_modify("update regnskab_overrides set expires_at = current_date - 1 where regnskab_id = '".(int)$rid."' and kind = 'feature' and key = '".db_escape_string($fk)."' and (expires_at is null or expires_at >= current_date)", __FILE__ . " linje " . __LINE__);
		if (if_isset($_POST, '', 'mode') == 'restore') { ent_log('entitlement.override_expired', (int)$rid, "feature $fk – tilbage til pakken"); $notes[] = vr_t('Funktionen følger igen pakken','Feature follows the plan again'); }
		else { $reason = trim(if_isset($_POST, '', 'reason')) ?: ($to == 'off' ? 'Slået fra af operatør' : ''); $exp = trim(if_isset($_POST, '', 'expires_at')); $err = ent_add_override($rid, 'feature', $fk, $to, $reason, $to == 'off' && !$exp ? date('Y-m-d', strtotime('+10 years')) : $exp); $notes[] = $err ? $err : ($to == 'on' ? vr_t('Funktionen er tildelt','Feature granted') : vr_t('Funktionen er slået fra for kunden','Feature switched off for the customer')); }
	}
	if ($err) $notes[] = $err;
}
function ent_tab_render($rid, $ent, $cat) {
	global $sprog_id;
	// 20261012 CL/ASR Layout: plan + change side by side, then what the plan includes, then add-ons and individual
	//                  terms; empty states explain what to do instead of showing "–".
	$P = $ent['plan']; $today = date('Y-m-d');
	$lim = fn($v) => $v === null ? vr_t('ubegrænset','unlimited') : vr_num($v);
	print "<div class=\"vr-grid2\">\n";
	// ---- current plan
	print "<section class=\"vr-sect\"><h2>".vr_t('Nuværende pakke','Current plan')."</h2><div class=\"vr-card\">";
	if ($P) {
		$price = $ent['price_ore'] === null ? vr_t('efter aftale','by agreement') : ent_kr($ent['price_ore']).' / '.($ent['interval'] == 'year' ? vr_t('år','year') : vr_t('md.','month'));
		$src = array('manual' => vr_t('sat af operatør','set by operator'), 'stripe' => 'Stripe', 'override' => vr_t('tilsidesættelse','override'), 'migration' => vr_t('overgangsordning','grandfathered'), 'scheduled' => vr_t('planlagt skift','scheduled change'));
		$viaOv = count(array_filter($ent['overrides'], fn($o) => $o['kind']=='plan'));
		print "<div class=\"vr-planhead\"><div><span class=\"vr-planname\">".vr_h($P['name'])."</span>".($viaOv ? " <span class=\"vr-pill\">".vr_t('via tilsidesættelse','via override')."</span>" : "")."</div><div class=\"vr-planprice\">".$price." <small>".vr_t('ekskl. moms','excl. VAT')."</small></div></div><div class=\"vr-kv\">";
		foreach (array(
			array(vr_t('Faktureringsinterval','Billing interval'), $ent['interval'] == 'year' ? vr_t('Årligt','Yearly') : vr_t('Månedligt','Monthly')),
			array(vr_t('Gældende fra','Valid from'), $ent['row']['valid_from'] ? date('d-m-Y', strtotime($ent['row']['valid_from'])) : '–'),
			array(vr_t('Kilde','Source'), isset($src[$ent['row']['source']]) ? $src[$ent['row']['source']] : vr_h($ent['row']['source'])),
			array('Stripe', $ent['row']['subscription_status'] && $ent['row']['subscription_status'] != 'none' ? vr_h($ent['row']['subscription_status']) : '<span class="vr-mut2">'.vr_t('ikke koblet','not linked').'</span>'),
			array(vr_t('Håndhævelse','Enforcement'), $ent['row']['enforcement'] == 'enforce' ? vr_t('Håndhæves','Enforced') : '<span class="vr-mut2">'.vr_t('Observerer – begrænser ikke endnu','Observe – no gating yet').'</span>'),
		) as $row) print "<div><span>$row[0]</span><b>$row[1]</b></div>";
		print "</div>";
	} else {
		print "<div class=\"vr-empty\" style=\"padding:30px 24px\"><b>".vr_t('Ingen pakke endnu','No plan yet')."</b><span>".vr_t('Regnskabet har ikke fået tildelt en pakke. Vælg en til højre – tillæg og grænser afhænger af pakken.','The account has no plan yet. Choose one on the right – add-ons and limits depend on it.')."</span></div>";
	}
	print "</div></section>\n";
	// ---- change / choose plan
	print "<section class=\"vr-sect\"><h2>".($P ? vr_t('Skift pakke','Change plan') : vr_t('Vælg pakke','Choose plan'))."</h2><div class=\"vr-card\"><form method=\"post\" class=\"vr-form\" style=\"max-width:none\"><input type=\"hidden\" name=\"ent_action\" value=\"set_plan\">";
	print "<div class=\"vr-fgrid\"><label>".vr_t('Pakke','Plan')."<select name=\"plan_key\" class=\"vr-sel\">";
	foreach ($cat['plans'] as $k => $pl) print "<option value=\"$k\"".($P && $P['plan_key'] == $k ? " selected" : "").">".vr_h($pl['name'])." · ".($pl['price_ore'] === null ? vr_t('efter aftale','by agreement') : ent_kr((int)$pl['price_ore']).'/md.')."</option>";
	print "</select></label><label>".vr_t('Gælder fra','Effective from')."<input class=\"vr-inp\" type=\"date\" name=\"effective_from\" value=\"$today\" min=\"$today\"></label></div>";
	print "<label>".vr_t('Begrundelse','Reason')."<input class=\"vr-inp\" name=\"reason\" placeholder=\"".vr_t('Fx aftalt pr. mail','e.g. agreed by mail')."\"></label>";
	if ($P) print "<label class=\"vr-chk\"><input type=\"checkbox\" name=\"force\"> ".vr_t('Gennemfør, selv om forbruget overstiger den nye pakkes grænser','Apply even if usage exceeds the new plan limits')."</label>";
	print "<div><button type=\"submit\" class=\"vr-btn vr-primary\">".($P ? vr_t('Skift pakke','Change plan') : vr_t('Tildel pakke','Assign plan'))."</button></div>";
	print "<p class=\"vr-hint-line\">".vr_t('En dato i fremtiden planlægger skiftet. En nedgradering tjekkes mod regnskabets aktuelle forbrug.','A future date schedules the change. A downgrade is checked against current usage.')."</p></form>";
	if ($ent['pending']) { print "<div class=\"vr-foot\" style=\"display:block\"><b style=\"font-weight:600\">".vr_t('Planlagte ændringer','Scheduled changes')."</b>"; foreach ($ent['pending'] as $pc) print "<div class=\"vr-frow\" style=\"padding:8px 0\"><div><b>".vr_h(ent_plan_name($pc['plan_key']))." ".vr_t('fra','from')." ".date('d-m-Y', strtotime($pc['effective_from']))."</b><small>".vr_h($pc['reason'])." · ".vr_h($pc['created_by'])."</small></div><span></span><form method=\"post\"><input type=\"hidden\" name=\"ent_action\" value=\"cancel_change\"><input type=\"hidden\" name=\"change_id\" value=\"".(int)$pc['id']."\"><button class=\"vr-link\" type=\"submit\">".vr_t('Annullér','Cancel')."</button></form></div>"; print "</div>"; }
	print "</div></section>\n</div>\n";
	// ---- included in the plan
	if ($P) {
		$ovKeys = array(); foreach ($ent['overrides'] as $o) if ($o['kind'] == 'limit') $ovKeys[$o['key']] = $o;
		$tile = function($label, $val, $sub, $key) use ($ovKeys) { return "<div class=\"vr-stat\"><span>$label</span><b>$val</b><small>".$sub.(isset($ovKeys[$key]) ? ' <span class="vr-pill">'.vr_t('individuel','individual').'</span>' : '')."</small></div>"; };
		print "<section class=\"vr-sect\" style=\"margin-top:22px\"><h2>".vr_t('Inkluderet i pakken','Included in the plan')."</h2><div class=\"vr-card\"><div class=\"vr-stats\">";
		print $tile(vr_t('Brugere','Users'), $lim($ent['limits']['max_users']), $ent['limits']['included_users'].' '.vr_t('inkluderet','included').($ent['extra_user_price_ore'] ? ' · '.vr_t('ekstra','extra').' '.ent_kr($ent['extra_user_price_ore']).'/md.' : ''), 'max_users');
		print $tile(vr_t('Posteringer','Entries'), $lim($ent['limits']['included_postings']), vr_t('rullende 12 mdr.','rolling 12 m'), 'included_postings');
		print $tile(vr_t('Varer','Items'), $lim($ent['limits']['max_items']), vr_t('i varekartoteket','in the item register'), 'max_items');
		print $tile(vr_t('Webshops','Webshops'), $lim($ent['limits']['max_webshops']), vr_t('tilkoblede shops','connected shops'), 'max_webshops');
		print "</div></div></section>";
		$areas = array(); foreach ($cat['feature_names'] as $fk => $fn) { $lv = isset($ent['features'][$fk]) ? $ent['features'][$fk] : 'none'; $areas[$fn[1]][] = array($fk, $fn[0], $lv); }
		print "<section class=\"vr-sect\" style=\"margin-top:22px\"><h2>".vr_t('Moduler og funktioner','Modules and features')." <small>".vr_t('slå inkluderede funktioner fra for kunden, eller tildel en funktion uden for pakken','switch included features off for the customer, or grant one outside the plan')."</small></h2><div class=\"vr-card vr-mods\">";
		$fov = array(); foreach ($ent['overrides'] as $o) if ($o['kind'] == 'feature') $fov[$o['key']] = $o;
		$planLv = $cat['features'][$P['plan_key']];
		foreach ($areas as $an => $fs) { print "<div class=\"vr-frow\" style=\"background:var(--surface-2);padding:7px 20px\"><b style=\"font-size:12.5px;color:var(--text-2)\">".vr_h($an)."</b><span></span><span></span></div>"; foreach ($fs as $f) { $lvTxt = array('included' => vr_t('Inkluderet','Included'), 'basic' => vr_t('Basis','Basic'), 'addon' => vr_t('Tillæg','Add-on'), 'none' => vr_t('Ikke med','Not included')); $k = $f[0]; $inPlan = in_array($planLv[$k] ?? 'none', array('included','basic')); $on = in_array($f[2], array('included','basic')); $ov = isset($fov[$k]) ? $fov[$k] : null; $core = ($k == 'finans.core');
			print "<div class=\"vr-frow".(!$on ? " vr-off" : "")."\" style=\"padding:8px 20px\"><div><b style=\"font-weight:400\">".vr_h($f[1])."</b>".($ov ? "<small>".($ov['value'] == 'on' ? vr_t('tildelt','granted') : vr_t('slået fra','switched off'))." · ".vr_h($ov['reason']).($ov['expires_at'] && $ov['expires_at'] < date('Y-m-d', strtotime('+5 years')) ? ' · '.vr_t('til','until').' '.vr_h($ov['expires_at']) : '')."</small>" : "")."</div><span class=\"vr-lvl\">".$lvTxt[$planLv[$k] ?? 'none'].($ov ? ' <span class="vr-pill">'.vr_t('tilsidesat','overridden').'</span>' : '')."</span>";
			if ($core) print "<span></span>";
			elseif ($ov) print "<form method=\"post\" style=\"display:inline\"><input type=\"hidden\" name=\"ent_action\" value=\"feature_toggle\"><input type=\"hidden\" name=\"feature_key\" value=\"$k\"><input type=\"hidden\" name=\"mode\" value=\"restore\"><button class=\"vr-link\" type=\"submit\">".vr_t('Følg pakken','Follow plan')."</button></form>";
			elseif ($inPlan) print "<form method=\"post\" style=\"display:inline\"><input type=\"hidden\" name=\"ent_action\" value=\"feature_toggle\"><input type=\"hidden\" name=\"feature_key\" value=\"$k\"><input type=\"hidden\" name=\"to\" value=\"off\"><button class=\"vr-sw sm\" type=\"submit\" role=\"switch\" aria-checked=\"true\" title=\"".vr_t('Slå fra for kunden','Switch off for the customer')."\" style=\"border:0;cursor:pointer\"></button></form>";
			else print "<a class=\"vr-link\" href=\"#\" onclick=\"var e=document.getElementById('ftg_".str_replace('.', '_', $k)."');e.hidden=!e.hidden;return false\">".vr_t('Tildel','Grant')."</a>";
			print "</div>";
			if (!$core && !$ov && !$inPlan) print "<div id=\"ftg_".str_replace('.', '_', $k)."\" hidden style=\"padding:6px 20px 10px;background:var(--surface-2)\"><form method=\"post\" class=\"vr-inline\"><input type=\"hidden\" name=\"ent_action\" value=\"feature_toggle\"><input type=\"hidden\" name=\"feature_key\" value=\"$k\"><input type=\"hidden\" name=\"to\" value=\"on\"><input class=\"vr-inp\" name=\"reason\" placeholder=\"".vr_t('Begrundelse (påkrævet)','Reason (required)')."\" style=\"flex:1;min-width:220px\" required><input class=\"vr-inp\" type=\"date\" name=\"expires_at\" required title=\"".vr_t('Udløber','Expires')."\"><button type=\"submit\" class=\"vr-btn\">".vr_t('Tildel uden for pakken','Grant outside the plan')."</button></form></div>";
		} }
		print "</div>"; print "</section>";
	}
	// ---- add-ons and individual terms
	print "<div class=\"vr-grid2\" style=\"margin-top:22px\">\n<section class=\"vr-sect\"><h2>".vr_t('Tillæg','Add-ons')." <small>".count($ent['addons'])."</small></h2><div class=\"vr-card\">";
	foreach ($ent['addons'] as $ak => $ar) { $ad = isset($cat['addons'][$ak]) ? $cat['addons'][$ak] : null; print "<div class=\"vr-frow\"><div><b>".vr_h($ad ? $ad['name'] : $ak).($ad && $ad['quantity_based'] ? " × ".(int)$ar['quantity'] : "")."</b><small>".($ad && $ad['price_ore'] !== null ? ent_kr((int)$ad['price_ore'] * max(1,(int)$ar['quantity'])).'/md. · ' : '').vr_h($ar['source']).($ar['expires_at'] ? ' · '.vr_t('udløber','expires').' '.vr_h($ar['expires_at']) : '')."</small></div><span></span>".($ar['source'] != 'override' ? "<form method=\"post\"><input type=\"hidden\" name=\"ent_action\" value=\"remove_addon\"><input type=\"hidden\" name=\"addon_key\" value=\"".vr_h($ak)."\"><button class=\"vr-link\" type=\"submit\" onclick=\"return confirm('".vr_t('Fjern tillægget?','Remove the add-on?')."')\">".vr_t('Fjern','Remove')."</button></form>" : "<span></span>")."</div>"; }
	if (!$ent['addons']) print "<div class=\"vr-empty\" style=\"padding:24px\"><b>".vr_t('Ingen tillæg','No add-ons')."</b><span>".($P ? vr_t('Tilføj fx booking, kasse eller webshop nedenfor.','Add e.g. booking, POS or webshop below.') : vr_t('Vælg en pakke først – hvilke tillæg der kan købes, afhænger af pakken.','Choose a plan first – available add-ons depend on it.'))."</span></div>";
	if ($P) { print "<div class=\"vr-foot\" style=\"display:block\"><form method=\"post\" class=\"vr-form\" style=\"padding:0;max-width:none\"><input type=\"hidden\" name=\"ent_action\" value=\"set_addon\"><label>".vr_t('Tillæg','Add-on')."<select name=\"addon_key\" class=\"vr-sel\">"; foreach ($cat['addons'] as $ak => $ad) { $avail = in_array($P['plan_key'], explode(',', $ad['available_on'])); print "<option value=\"$ak\"".($avail ? "" : " disabled").">".vr_h($ad['name']).($ad['price_ore'] !== null ? ' · '.ent_kr((int)$ad['price_ore']).'/md.' : '').($avail ? '' : ' · '.vr_t('ikke på denne pakke','not on this plan'))."</option>"; } print "</select></label><div class=\"vr-fgrid\"><label>".vr_t('Antal','Quantity')."<input class=\"vr-inp\" type=\"number\" name=\"quantity\" value=\"1\" min=\"1\"></label><label>".vr_t('Udløber (valgfrit)','Expires (optional)')."<input class=\"vr-inp\" type=\"date\" name=\"expires_at\"></label></div><div><button type=\"submit\" class=\"vr-btn\">+ ".vr_t('Tilføj tillæg','Add add-on')."</button></div></form></div>"; }
	print "</div></section>\n";
	print "<section class=\"vr-sect\"><h2>".vr_t('Individuelle aftaler','Individual terms')." <small>".count($ent['overrides'])."</small></h2><div class=\"vr-card\">";
	$kinds = array('plan' => vr_t('Pakke','Plan'), 'feature' => vr_t('Funktion','Feature'), 'limit' => vr_t('Grænse','Limit'), 'addon' => vr_t('Tillæg','Add-on'));
	foreach ($ent['overrides'] as $o) print "<div class=\"vr-frow\"><div><b>".vr_h($kinds[$o['kind']])." · ".vr_h($o['key'])." = ".vr_h($o['value'])."</b><small>".vr_h($o['reason'])." · ".vr_h($o['created_by'])." ".vr_h(substr($o['created_at'],0,10)).($o['expires_at'] ? ' · '.vr_t('udløber','expires').' '.vr_h($o['expires_at']) : ' · '.vr_t('uden udløb','no expiry'))."</small></div><span></span><form method=\"post\"><input type=\"hidden\" name=\"ent_action\" value=\"expire_override\"><input type=\"hidden\" name=\"override_id\" value=\"".(int)$o['id']."\"><button class=\"vr-link\" type=\"submit\" onclick=\"return confirm('".vr_t('Lad tilsidesættelsen udløbe nu?','Expire the override now?')."')\">".vr_t('Udløb nu','Expire now')."</button></form></div>";
	if (!$ent['overrides']) print "<div class=\"vr-empty\" style=\"padding:24px\"><b>".vr_t('Ingen individuelle aftaler','No individual terms')."</b><span>".vr_t('Til prøveperioder, goodwill-grænser og pilotfunktioner. Begrundelse og udløb er påkrævet.','For trials, goodwill limits and pilot features. Reason and expiry are required.')."</span></div>";
	print "<div class=\"vr-foot\" style=\"display:block\"><form method=\"post\" class=\"vr-form\" style=\"padding:0;max-width:none\"><input type=\"hidden\" name=\"ent_action\" value=\"add_override\">";
	print "<div class=\"vr-fgrid\"><label>".vr_t('Type','Type')."<select name=\"kind\" class=\"vr-sel\">"; foreach ($kinds as $k => $n) print "<option value=\"$k\"".($k == 'limit' ? " selected" : "").">$n</option>"; print "</select></label>";
	print "<label>".vr_t('Hvad','What')."<input class=\"vr-inp\" name=\"key\" list=\"ovKeys\" placeholder=\"".vr_t('fx max_users','e.g. max_users')."\"></label>";
	print "<label>".vr_t('Værdi','Value')."<input class=\"vr-inp\" name=\"value\" placeholder=\"".vr_t('tal, on/off eller pakke','number, on/off or plan')."\"></label>";
	print "<label>".vr_t('Udløber','Expires')."<input class=\"vr-inp\" type=\"date\" name=\"expires_at\"></label></div>";
	print "<datalist id=\"ovKeys\">"; foreach (array('max_users','included_postings','max_items','max_webshops') as $k) print "<option value=\"$k\">"; foreach ($cat['feature_names'] as $fk => $fn) print "<option value=\"$fk\">".vr_h($fn[0])."</option>"; foreach ($cat['addons'] as $ak => $ad) print "<option value=\"$ak\">".vr_h($ad['name'])."</option>"; foreach ($cat['plans'] as $pk => $pl) print "<option value=\"$pk\">".vr_h($pl['name'])."</option>"; print "</datalist>";
	print "<label>".vr_t('Begrundelse','Reason')."<input class=\"vr-inp\" name=\"reason\" placeholder=\"".vr_t('Påkrævet','Required')."\" required></label>";
	print "<div><button type=\"submit\" class=\"vr-btn\">+ ".vr_t('Opret aftale','Create term')."</button></div></form></div>";
	print "</div></section>\n</div>\n";
}
}
?>
