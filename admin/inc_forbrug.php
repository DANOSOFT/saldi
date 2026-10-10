<?php
// --- admin/inc_forbrug.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Area 2 "Forbrug og begrænsninger" on the customer card: live usage from the customer
//                  database (ent_usage_collect), today's snapshot stored, limits shown as plan standard vs.
//                  individual override vs. effective, utilisation bars, entries per month, modules in use vs.
//                  plan, and limit adjustment (writes regnskab_overrides kind='limit', logged as entitlement.*).

if (!function_exists('ent_forbrug_actions')) {
function ent_forbrug_actions($rid, &$notes) {
	if (!isset($_POST['fb_action'])) return;
	if ($_POST['fb_action'] == 'limit_set') {
		$key = if_isset($_POST, '', 'key'); $val = trim(if_isset($_POST, '', 'value')); $reason = trim(if_isset($_POST, '', 'reason')); $exp = trim(if_isset($_POST, '', 'expires_at'));
		if (!in_array($key, array('max_users','included_postings','max_items','max_webshops','pos_tills','warehouses'), true)) { $notes[] = vr_t('Ukendt grænse','Unknown limit'); return; }
		if ($val === '' ) { $notes[] = vr_t('Angiv en værdi (tal eller "ubegrænset")','Enter a value (number or "unlimited")'); return; }
		$val = (strtolower($val) == 'ubegrænset' || strtolower($val) == 'unlimited' || $val == '*') ? '*' : (string)(int)preg_replace('/\D/', '', $val);
		// one active limit override per key: expire the old one first
		db_modify("update regnskab_overrides set expires_at = current_date - 1 where regnskab_id = '".(int)$rid."' and kind = 'limit' and key = '".db_escape_string($key)."' and (expires_at is null or expires_at >= current_date)", __FILE__ . " linje " . __LINE__);
		$err = ent_add_override($rid, 'limit', $key, $val, $reason, $exp);
		$notes[] = $err ? $err : vr_t('Grænsen er justeret','Limit adjusted');
	} elseif ($_POST['fb_action'] == 'limit_clear') {
		$key = if_isset($_POST, '', 'key');
		db_modify("update regnskab_overrides set expires_at = current_date - 1 where regnskab_id = '".(int)$rid."' and kind = 'limit' and key = '".db_escape_string($key)."' and (expires_at is null or expires_at >= current_date)", __FILE__ . " linje " . __LINE__);
		ent_log('entitlement.override_expired', (int)$rid, "limit $key – tilbage til pakkens standard");
		$notes[] = vr_t('Tilbage til pakkens standard','Back to the plan default');
	}
}
function ent_forbrug_render($rid, $reg, $ent, $cat, $u) {
	global $sprog_id;
	$P = $ent['plan']; $today = date('Y-m-d');
	$inf = vr_t('ubegrænset','unlimited'); $fmt = fn($v) => $v === null ? $inf : vr_num($v);
	$std = $P ? array('max_users' => $P['max_users'] === null ? null : (int)$P['max_users'], 'included_postings' => $P['included_postings'] === null ? null : (int)$P['included_postings'], 'max_items' => $P['max_items'] === null ? null : (int)$P['max_items'], 'max_webshops' => (int)$P['included_webshops'], 'pos_tills' => null, 'warehouses' => null) : array();
	$ov = array(); foreach ($ent['overrides'] as $o) if ($o['kind'] == 'limit') $ov[$o['key']] = $o;
	$eff = array(); foreach (array('max_users','included_postings','max_items','max_webshops','pos_tills','warehouses') as $k) { $eff[$k] = isset($ent['limits'][$k]) ? $ent['limits'][$k] : (isset($std[$k]) ? $std[$k] : null); if (isset($ov[$k])) $eff[$k] = ($ov[$k]['value'] === '*' || $ov[$k]['value'] === '') ? null : (int)$ov[$k]['value']; }
	$use = array('max_users' => $u['users_active'], 'included_postings' => $u['postings_12m'], 'max_items' => $u['items'], 'max_webshops' => $u['webshops'], 'pos_tills' => $u['pos_tills'], 'warehouses' => $u['warehouses']);
	if (!$u['ok']) vr_note(array(vr_t('Kunne ikke læse fra regnskabets database – forbruget er fra sidste øjebliksbillede.','Could not read the account database – usage is from the last snapshot.')), 'warn');
	if (!$P) vr_note(array(vr_t('Regnskabet har ingen pakke. Grænserne nedenfor er derfor kun individuelle aftaler.','The account has no plan; the limits below are individual terms only.')), 'warn');
	// ---- utilisation table
	print "<section class=\"vr-sect\"><h2>".vr_t('Udnyttelse af pakkens grænser','Utilisation of plan limits')." <small>".($P ? vr_h($P['name']) : '–')." · ".vr_t('målt nu','measured now')."</small></h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Grænse','Limit')."</th><th class=\"vr-r\">".vr_t('Forbrug','Usage')."</th><th class=\"vr-r\">".vr_t('Pakkens standard','Plan default')."</th><th class=\"vr-r\">".vr_t('Individuel','Individual')."</th><th class=\"vr-r\">".vr_t('Gældende','Effective')."</th><th style=\"width:160px\">".vr_t('Udnyttelse','Utilisation')."</th><th></th></tr></thead><tbody>";
	foreach (array('max_users','included_postings','max_items','max_webshops','pos_tills','warehouses') as $k) {
		$used = $use[$k]; $lim = $eff[$k]; $pct = ($lim && $used !== null) ? min(100, (int)round($used / $lim * 100)) : ($lim === 0 && $used ? 100 : 0);
		$cls = $pct >= 100 ? 'vr-hi' : ($pct >= 80 ? 'vr-mid' : '');
		print "<tr><td><b style=\"font-weight:500\">".ent_limit_label($k)."</b>".($k == 'included_postings' ? "<small style=\"display:block;color:var(--text-3)\">".vr_t('rullende 12 mdr. · over grænsen afregnes, blokerer ikke','rolling 12 m · overage is billed, never blocks')."</small>" : "")."</td>";
		print "<td class=\"vr-r vr-num\">".($used === null ? '–' : vr_num($used))."</td><td class=\"vr-r vr-mut\">".(isset($std[$k]) ? $fmt($std[$k]) : '–')."</td><td class=\"vr-r\">".(isset($ov[$k]) ? "<b>".$fmt($eff[$k])."</b> <span class=\"vr-pill\" title=\"".vr_h($ov[$k]['reason'])."\">".($ov[$k]['expires_at'] ? vr_t('til','until').' '.vr_h($ov[$k]['expires_at']) : vr_t('uden udløb','no expiry'))."</span>" : '<span class="vr-mut2">–</span>')."</td><td class=\"vr-r\"><b>".$fmt($lim)."</b></td>";
		print "<td><span class=\"vr-b\" style=\"width:120px\"><i class=\"$cls\" style=\"width:$pct%\"></i></span> <span class=\"vr-mut2\" style=\"font-size:12px\">".($lim === null ? '' : "$pct %")."</span></td>";
		print "<td class=\"vr-r\"><a class=\"vr-link\" href=\"#\" onclick=\"document.getElementById('fbf_$k').hidden=!document.getElementById('fbf_$k').hidden;return false\">".vr_t('Justér','Adjust')."</a></td></tr>";
		print "<tr id=\"fbf_$k\" hidden><td colspan=\"7\" style=\"background:var(--surface-2)\"><form method=\"post\" class=\"vr-inline\" style=\"padding:4px 0 8px\"><input type=\"hidden\" name=\"fb_action\" value=\"limit_set\"><input type=\"hidden\" name=\"key\" value=\"$k\"><input class=\"vr-inp\" name=\"value\" value=\"".($lim === null ? '' : (int)$lim)."\" placeholder=\"".vr_t('tal eller ubegrænset','number or unlimited')."\" style=\"width:160px\"><input class=\"vr-inp\" name=\"reason\" placeholder=\"".vr_t('Begrundelse (påkrævet)','Reason (required)')."\" style=\"flex:1;min-width:220px\" required><input class=\"vr-inp\" type=\"date\" name=\"expires_at\" title=\"".vr_t('Udløber (påkrævet)','Expires (required)')."\" required><button type=\"submit\" class=\"vr-btn vr-primary\">".vr_t('Gem individuel grænse','Save individual limit')."</button>".(isset($ov[$k]) ? "</form><form method=\"post\" style=\"display:inline\"><input type=\"hidden\" name=\"fb_action\" value=\"limit_clear\"><input type=\"hidden\" name=\"key\" value=\"$k\"><button type=\"submit\" class=\"vr-btn vr-quiet\">".vr_t('Tilbage til standard','Back to default')."</button>" : "")."</form></td></tr>";
	}
	print "</tbody></table><div class=\"vr-foot\"><span>".vr_t('Individuelle grænser gælder frem for pakkens standard, kræver begrundelse og udløb, og logges.','Individual limits override the plan default, need a reason and an expiry, and are logged.')."</span></div></div></section>\n";
	// ---- postings per month
	$months = array(); for ($i = 11; $i >= 0; $i--) { $m = date('Y-m', strtotime("-$i months", strtotime(date('Y-m-01')))); $months[$m] = isset($u['postings_by_month'][$m]) ? $u['postings_by_month'][$m] : 0; }
	$max = max(1, max($months));
	print "<div class=\"vr-grid2\" style=\"margin-top:22px\"><section class=\"vr-sect\"><h2>".vr_t('Posteringer','Entries')." <small>".vr_t('denne måned','this month').": ".($u['postings_month'] === null ? '–' : vr_num($u['postings_month']))." · 12 mdr.: ".($u['postings_12m'] === null ? '–' : vr_num($u['postings_12m']))."</small></h2><div class=\"vr-card\"><div class=\"vr-bars\">";
	foreach ($months as $m => $n) { $h = (int)round($n / $max * 100); $cur = ($m == date('Y-m')); print "<div class=\"vr-barcol\" title=\"".vr_h($m).": ".vr_num($n)."\"><i style=\"height:$h%\"".($cur ? " class=\"vr-cur\"" : "")."></i><span>".vr_h(substr($m, 5))."</span></div>"; }
	print "</div><div class=\"vr-foot\"><span>".vr_t('Talt live i kundens database pr. måned (logdate). Seneste 12 måneder.','Counted live in the customer database per month (logdate). Last 12 months.')."</span></div></div></section>";
	// ---- history (snapshots)
	$hist = ent_usage_history($rid, 12);
	print "<section class=\"vr-sect\"><h2>".vr_t('Historik','History')." <small>".vr_t('natlige øjebliksbilleder, seneste pr. måned','nightly snapshots, latest per month')."</small></h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Måned','Month')."</th><th class=\"vr-r\">".vr_t('Brugere','Users')."</th><th class=\"vr-r\">".vr_t('Post. måned','Entries month')."</th><th class=\"vr-r\">".vr_t('Post. 12 mdr.','Entries 12 m')."</th><th class=\"vr-r\">".vr_t('Varer','Items')."</th><th class=\"vr-r\">".vr_t('Kasser','Tills')."</th></tr></thead><tbody>";
	foreach ($hist as $h) print "<tr><td class=\"vr-mut vr-num\">".vr_h($h['m'])." <span class=\"vr-mut2\">".vr_h(substr($h['taken_at'],8,2))."</span></td><td class=\"vr-r vr-num\">".vr_num($h['users_active'])."</td><td class=\"vr-r vr-num\">".vr_num($h['postings_month'])."</td><td class=\"vr-r vr-num\">".vr_num($h['postings_12m'])."</td><td class=\"vr-r vr-num\">".vr_num($h['items'])."</td><td class=\"vr-r vr-num\">".vr_num($h['pos_tills'])."</td></tr>";
	if (!$hist) print "<tr><td colspan=\"6\" class=\"vr-mut\">".vr_t('Ingen øjebliksbilleder endnu. Det natlige job (admin/usage_snapshot_job.php) fylder historikken.','No snapshots yet. The nightly job (admin/usage_snapshot_job.php) fills the history.')."</td></tr>";
	print "</tbody></table></div></section></div>\n";
	// ---- modules in use
	$names = $cat['feature_names']; $lvTxt = array('included' => vr_t('inkluderet','included'), 'basic' => vr_t('basis','basic'), 'addon' => vr_t('tillæg','add-on'), 'none' => vr_t('ikke i pakken','not in plan'));
	$mods = array('pos' => vr_t('Kassesystem','POS'), 'shop.integration' => vr_t('Webshop','Webshop'), 'kommission' => vr_t('Kommissionssalg','Commission sales'), 'booking' => vr_t('Booking / udlejning','Booking / rental'), 'sager' => vr_t('Sager og jobkort','Jobs'), 'finans.dimensioner' => vr_t('Dimensioner','Dimensions'), 'finans.budget' => vr_t('Budget','Budget'), 'debitor.tilbud' => vr_t('Tilbud','Quotes'), 'debitor.abonnement' => vr_t('Abonnementsfakturering','Subscription invoicing'), 'debitor.rykker' => vr_t('Rykkersystem','Reminders'), 'kreditor.register' => vr_t('Kreditorer','Creditors'), 'kreditor.indkob' => vr_t('Indkøbsordrer','Purchase orders'), 'lager.styring' => vr_t('Lagerstyring','Inventory'), 'lager.multilocation' => vr_t('Flere lagre','Multiple warehouses'), 'lager.serienumre' => vr_t('Serienumre','Serial numbers'), 'lager.minmax' => vr_t('Min/max genbestilling','Min/max'));
	print "<section class=\"vr-sect\" style=\"margin-top:22px\"><h2>".vr_t('Moduler kunden benytter','Modules the customer uses')." <small>".vr_t('aflæst i kundens opsætning og data','read from the customer setup and data')."</small></h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Modul','Module')."</th><th>".vr_t('I brug','In use')."</th><th>".vr_t('I pakken','In plan')."</th><th></th></tr></thead><tbody>";
	foreach ($mods as $k => $n) {
		$used = isset($u['modules'][$k]) ? $u['modules'][$k] : null; $lv = isset($ent['features'][$k]) ? $ent['features'][$k] : 'none';
		$warn = ($P && $used && ($lv == 'none' || $lv == 'addon') && !isset($ent['addons'][$k]));
		print "<tr".($warn ? " style=\"background:color-mix(in srgb,var(--amber) 8%,var(--surface))\"" : "")."><td>".vr_h($n)."<small style=\"display:block;color:var(--text-3)\">$k</small></td><td>".($used === null ? '<span class="vr-mut2">'.vr_t('ukendt','unknown').'</span>' : ($used ? '<span class="vr-st"><span class="vr-dot vr-ok"></span>'.vr_t('Ja','Yes').'</span>' : '<span class="vr-mut2">'.vr_t('Nej','No').'</span>'))."</td><td class=\"vr-mut\">".($P ? $lvTxt[$lv] : '–')."</td><td class=\"vr-r\">".($warn ? "<span class=\"vr-st\" style=\"color:var(--warn-ink)\"><span class=\"vr-dot vr-warn\"></span>".vr_t('bruges, men er ikke med i pakken','used, but not in the plan')."</span>" : "")."</td></tr>";
	}
	print "</tbody></table><div class=\"vr-foot\"><span>".vr_t('Rækker med advarsel er kandidater til tilkøb eller opgradering – indtil pakkelaget håndhæver, begrænser det ikke kunden.','Rows with a warning are candidates for an add-on or upgrade – until enforcement, nothing is blocked.')."</span></div></div></section>\n";
}
}
?>
