<?php
// --- admin/inc_special.php --- ver 5.1.0 --- 2026.10.29 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261012 CL/ASR New. Specialtilpasninger: separate notes on custom development made for one customer (a ledger or a
//                  self-hosted installation), so support can see at a glance what is special about their system.
//                  Each item: title, type, description, reference (ticket/PR/branch), delivered date, "vigtigt for
//                  support" flag, status active/retired. Shown on the Abonnement tab (full), on Oversigt and in the card
//                  header (counts / important items) and as a marker in the ledger list. Changes are logged.
// 20261029 CL/ASR customizations is created by the update chain (includes/opdat_func/operatorPanelSchema.php).

if (!function_exists('sp_tables_ensure')) {
function sp_tables_ensure() {
	# 20261029 CL/ASR The tables come from the update chain (includes/opdat_func/operatorPanelSchema.php via betweenUpdates.php).
	return;
}
function sp_categories() { return array('funktion' => vr_t('Funktion','Feature'), 'integration' => vr_t('Integration','Integration'), 'rapport' => vr_t('Rapport/udskrift','Report/print'), 'opsaetning' => vr_t('Opsætning/data','Setup/data'), 'andet' => vr_t('Andet','Other')); }
function sp_owner_sql($rid, $iid) { return $iid ? "self_hosted_id = '".(int)$iid."'" : "regnskab_id = '".(int)$rid."'"; }
function sp_list($rid, $iid = 0, $only_active = false) {
	sp_tables_ensure(); $o = array();
	$q = db_select("select * from customizations where ".sp_owner_sql($rid, $iid).($only_active ? " and status = 'active'" : "")." order by (status = 'active') desc, important desc, coalesce(delivered, created::date) desc, id desc", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $o[] = $r; return $o;
}
function sp_counts_by_ledger() { # regnskab_id => [active, important]
	sp_tables_ensure(); $o = array();
	$q = db_select("select regnskab_id, count(*) as n, sum(case when important then 1 else 0 end) as imp from customizations where regnskab_id is not null and status = 'active' group by regnskab_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $o[(int)$r['regnskab_id']] = array((int)$r['n'], (int)$r['imp']); return $o;
}
function sp_log($rid, $iid, $action, $details) { partner_log($action, $rid ? (int)$rid : null, null, ($iid ? "[installation $iid] " : '').$details); }
function sp_actions($rid, $iid, &$notes) {
	if (!isset($_POST['sp_action'])) return;
	sp_tables_ensure(); $a = $_POST['sp_action']; $id = (int)if_isset($_POST, 0, 'sp_id');
	$own = sp_owner_sql($rid, $iid);
	if ($a == 'save') {
		$title = trim((string)if_isset($_POST, '', 'title')); if ($title === '') { $notes[] = vr_t('Titel mangler','Title missing'); return; }
		$cat = (string)if_isset($_POST, '', 'category'); if (!isset(sp_categories()[$cat])) $cat = 'andet';
		$del = trim((string)if_isset($_POST, '', 'delivered')); $del = preg_match('/^\d{4}-\d{2}-\d{2}$/', $del) ? "'$del'" : 'NULL';
		$f = array('title' => "'".db_escape_string($title)."'", 'category' => "'$cat'", 'description' => "'".db_escape_string(trim((string)if_isset($_POST, '', 'description')))."'", 'reference' => "'".db_escape_string(trim((string)if_isset($_POST, '', 'reference')))."'", 'delivered' => $del, 'important' => isset($_POST['important']) ? 'true' : 'false');
		if ($id) {
			if (!db_fetch_array(db_select("select 1 from customizations where id = '$id' and $own", __FILE__ . " linje " . __LINE__))) return;
			$set = array(); foreach ($f as $k => $v) $set[] = "$k = $v";
			db_modify("update customizations set ".implode(', ', $set).", updated = now(), updated_by = '".db_escape_string(ent_actor())."' where id = '$id'", __FILE__ . " linje " . __LINE__);
			sp_log($rid, $iid, 'customization.updated', $title); $notes[] = vr_t('Specialtilpasningen er gemt','Customization saved');
		} else {
			$f['regnskab_id'] = $iid ? 'NULL' : "'".(int)$rid."'"; $f['self_hosted_id'] = $iid ? "'".(int)$iid."'" : 'NULL'; $f['created_by'] = "'".db_escape_string(ent_actor())."'";
			db_modify("insert into customizations (".implode(', ', array_keys($f)).") values (".implode(', ', $f).")", __FILE__ . " linje " . __LINE__);
			sp_log($rid, $iid, 'customization.created', $title); $notes[] = vr_t('Specialtilpasningen er tilføjet','Customization added');
		}
	} elseif (($a == 'retire' || $a == 'activate' || $a == 'delete') && $id) {
		$r = db_fetch_array(db_select("select * from customizations where id = '$id' and $own", __FILE__ . " linje " . __LINE__)); if (!$r) return;
		if ($a == 'delete') { db_modify("delete from customizations where id = '$id'", __FILE__ . " linje " . __LINE__); sp_log($rid, $iid, 'customization.deleted', $r['title']); $notes[] = vr_t('Slettet','Deleted'); }
		else { db_modify("update customizations set status = '".($a == 'retire' ? 'retired' : 'active')."', updated = now(), updated_by = '".db_escape_string(ent_actor())."' where id = '$id'", __FILE__ . " linje " . __LINE__); sp_log($rid, $iid, 'customization.'.($a == 'retire' ? 'retired' : 'activated'), $r['title']); $notes[] = $a == 'retire' ? vr_t('Markeret som udfaset','Marked as retired') : vr_t('Aktiv igen','Active again'); }
	}
}
function sp_form($r) {
	global $sprog_id; $v = fn($k) => vr_h($r[$k] ?? '');
	print "<form method=\"post\" class=\"vr-form\" style=\"max-width:none;padding:14px 0 4px\"><input type=\"hidden\" name=\"sp_action\" value=\"save\"><input type=\"hidden\" name=\"sp_id\" value=\"".(int)($r['id'] ?? 0)."\">";
	print "<div class=\"vr-fgrid\"><label>".vr_t('Titel','Title')."<input class=\"vr-inp\" name=\"title\" value=\"".$v('title')."\" maxlength=\"160\" required placeholder=\"".vr_t('Fx Eksport af ordrer til lagerets WMS','e.g. Order export to the warehouse WMS')."\"></label><label>".vr_t('Type','Type')."<select name=\"category\" class=\"vr-sel\">"; foreach (sp_categories() as $k => $t) print "<option value=\"$k\"".(($r['category'] ?? 'funktion') == $k ? " selected" : "").">".vr_h($t)."</option>"; print "</select></label></div>";
	print "<label>".vr_t('Beskrivelse – hvad er lavet, hvor i systemet, og hvad skal support vide','Description – what was built, where, and what support should know')."<textarea class=\"vr-inp\" name=\"description\" style=\"height:110px;padding:8px 12px\">".$v('description')."</textarea></label>";
	print "<div class=\"vr-fgrid\"><label>".vr_t('Reference (ticket, PR, gren)','Reference (ticket, PR, branch)')."<input class=\"vr-inp\" name=\"reference\" value=\"".$v('reference')."\" placeholder=\"SD-712 / https://github.com/…\"></label><label>".vr_t('Leveret','Delivered')."<input class=\"vr-inp\" type=\"date\" name=\"delivered\" value=\"".$v('delivered')."\"></label></div>";
	print "<label class=\"vr-chk\"><input type=\"checkbox\" name=\"important\" value=\"1\"".(!empty($r['important']) && $r['important'] !== 'f' ? " checked" : "")."> ".vr_t('Vigtigt for support – vises øverst på regnskabets kort','Important for support – shown at the top of the account card')."</label>";
	print "<div><button type=\"submit\" class=\"vr-btn vr-primary\">".(!empty($r['id']) ? findtekst('3|Gem', $sprog_id) : vr_t('Tilføj specialtilpasning','Add customization'))."</button></div></form>";
}
function sp_ref_html($ref) { $ref = trim((string)$ref); if ($ref === '') return ''; return preg_match('#^https?://#i', $ref) ? "<a href=\"".vr_h($ref)."\" target=\"_blank\" rel=\"noopener\">".vr_h(strlen($ref) > 60 ? substr($ref, 0, 57).'…' : $ref)."</a>" : vr_h($ref); }
function sp_render($rid, $iid = 0) {
	$all = sp_list($rid, $iid); $act = count(array_filter($all, fn($x) => $x['status'] == 'active')); $cats = sp_categories();
	print "<section class=\"vr-sect\" id=\"special\" style=\"margin-top:22px\"><h2>".vr_t('Specialtilpasninger','Customizations')." <small>$act ".vr_t('aktive','active')." · ".vr_t('udvikling og opsætning lavet specielt til denne kunde','development and setup made specially for this customer')."</small></h2><div class=\"vr-card\">";
	foreach ($all as $r) {
		$imp = ($r['important'] === 't' || $r['important'] === true); $ret = ($r['status'] != 'active');
		print "<div class=\"vr-sp".($ret ? " vr-sp-ret" : "")."\"><div class=\"vr-sp-head\"><div><b>".vr_h($r['title'])."</b> <span class=\"vr-pill\">".vr_h($cats[$r['category']] ?? $r['category'])."</span>".($imp && !$ret ? " <span class=\"vr-st\" style=\"color:var(--warn-ink);margin-left:6px\"><span class=\"vr-dot vr-warn\"></span>".vr_t('Vigtigt for support','Important for support')."</span>" : "").($ret ? " <span class=\"vr-pill\">".vr_t('udfaset','retired')."</span>" : "")."</div>";
		print "<div class=\"vr-sp-acts\"><details><summary class=\"vr-link\">".vr_t('Ret','Edit')."</summary>"; sp_form($r); print "</details>";
		print "<form method=\"post\"><input type=\"hidden\" name=\"sp_action\" value=\"".($ret ? 'activate' : 'retire')."\"><input type=\"hidden\" name=\"sp_id\" value=\"".(int)$r['id']."\"><button type=\"submit\" class=\"vr-link\">".($ret ? vr_t('Aktivér','Activate') : vr_t('Udfas','Retire'))."</button></form>";
		print "<form method=\"post\" onsubmit=\"return confirm('".vr_t('Slet specialtilpasningen? Overvej i stedet at udfase den, så historikken bevares.','Delete the customization? Consider retiring it to keep the history.')."')\"><input type=\"hidden\" name=\"sp_action\" value=\"delete\"><input type=\"hidden\" name=\"sp_id\" value=\"".(int)$r['id']."\"><button type=\"submit\" class=\"vr-link\" style=\"color:var(--bad)\">".vr_t('Slet','Delete')."</button></form></div></div>";
		$meta = array(); if ($r['delivered']) $meta[] = vr_t('Leveret','Delivered').' '.date('d-m-Y', strtotime($r['delivered'])); if ($r['reference']) $meta[] = sp_ref_html($r['reference']); $meta[] = vr_t('oprettet af','added by').' '.vr_h($r['created_by']).' '.date('d-m-Y', strtotime($r['created'])); if ($r['updated']) $meta[] = vr_t('rettet','edited').' '.date('d-m-Y', strtotime($r['updated'])).' '.vr_t('af','by').' '.vr_h($r['updated_by']);
		print "<div class=\"vr-sp-meta\">".implode(' · ', $meta)."</div>".($r['description'] ? "<div class=\"vr-sp-desc\">".nl2br(vr_h($r['description']))."</div>" : "")."</div>";
	}
	if (!$all) print "<div class=\"vr-empty\" style=\"padding:24px\"><b>".vr_t('Ingen specialtilpasninger','No customizations')."</b><span>".vr_t('Beskriv her, hvad der er udviklet eller sat op specielt til kunden, så support ved, hvad de skal være opmærksomme på.','Describe what was built or set up specially for the customer, so support knows what to watch for.')."</span></div>";
	print "<div class=\"vr-foot\" style=\"display:block\"><details".($all ? "" : " open")."><summary class=\"vr-btn\" style=\"display:inline-flex\">+ ".vr_t('Tilføj specialtilpasning','Add customization')."</summary>"; sp_form(array()); print "</details></div></div></section>";
}
// Compact block for the overview: important active items (or a count), linking to the full list.
function sp_render_compact($rid, $iid, $href) {
	$act = sp_list($rid, $iid, true); if (!$act) return;
	$imp = array_values(array_filter($act, fn($x) => $x['important'] === 't' || $x['important'] === true)); $show = $imp ?: array_slice($act, 0, 3);
	print "<div class=\"vr-note vr-note-warn\" style=\"margin-bottom:20px\"><div><b>".count($act)." ".(count($act) == 1 ? vr_t('specialtilpasning','customization') : vr_t('specialtilpasninger','customizations'))."</b> – ".vr_t('vær opmærksom ved support','keep in mind for support').": ";
	print implode(' · ', array_map(fn($x) => vr_h($x['title']), $show)).(count($act) > count($show) ? ' …' : '')." <a href=\"".vr_h($href)."#special\">".vr_t('Se alle','See all')."</a></div></div>";
}
}
?>
