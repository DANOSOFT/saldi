<?php
// --- admin/bogholder_ui.php --- ver 5.1.0 --- 2026.10.12 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261012 CL/ASR New. Shell for the accountant panel (bogholderpanelet): the access guard (users who belong to a
//                  bookkeeper, auditor or group; operators and legacy users are sent to their own pages), the panel's
//                  own menu through vr_ui.php's menu hook, the page opener (adds css/bogholder_panel.css to the shared
//                  admin-layer shell) and small renderers used on several pages. The operator panel is not touched.

if (!function_exists('bp_require_partner')) {

function bp_shell_globals() {
	$p = bp_partner(); $k = $p ? $p['kind'] : 'bogholder';
	$GLOBALS['vr_home'] = '../admin/bogholder_overblik.php';
	$GLOBALS['vr_panel_label'] = $k == 'koncern' ? bp_t('Koncernpanel','Group panel') : ($k == 'revisor' ? bp_t('Revisorpanel','Auditor panel') : bp_t('Bogholderpanel','Accountant panel'));
	$GLOBALS['vr_menu_items'] = bp_menu();
	// 20261013 CL/ASR Own look for the accountant panel, so users can tell it from Saldi itself: light grey sidebar with the
	//                  Saldi logo in colour (css/bogholder_panel.css, .vr-app.bp-shell). The operator panel keeps its own shell.
	$GLOBALS['vr_app_class'] = 'bp-shell';
	$GLOBALS['vr_version_text'] = $k == 'koncern' ? 'Saldi Koncernpanel 2.0' : 'Saldi Bogholderpanel 2.0'; # 20261013 CL/ASR the panel's own version line at the bottom of the sidebar
	$GLOBALS['vr_help'] = '../admin/bogholder_vejledning.php'; # 20261013 CL/ASR the sidebar guide link opens the panel's own guide
	$GLOBALS['vr_logo'] = array('../img/saldi_logo_color.png', '../img/saldi_mark_color.png', 'Saldi', '../img/saldi_logo_white.png');
	// 20261013 CL/ASR Light/dark theme for the panel: the choice is kept per browser (localStorage bpTheme: light|dark), the
	//                  system preference is the default, and it is applied before the shell paints. The switch sits in the topbar.
	print "<script>(function(){try{var t=localStorage.getItem('bpTheme');if(t!=='light'&&t!=='dark')t=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t);}catch(e){}})();function bpTheme(){var d=document.documentElement,t=d.getAttribute('data-theme')==='dark'?'light':'dark';d.setAttribute('data-theme',t);try{localStorage.setItem('bpTheme',t);}catch(e){}}</script>";
	$GLOBALS['vr_top_extra'] = "<button type=\"button\" class=\"vr-menu-btn bp-theme\" onclick=\"bpTheme()\" title=\"".bp_t('Skift mellem lys og mørk','Switch between light and dark')."\" aria-label=\"".bp_t('Skift mellem lys og mørk','Switch between light and dark')."\"><svg class=\"bp-sun\" width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" aria-hidden=\"true\"><circle cx=\"12\" cy=\"12\" r=\"4\"/><path d=\"M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4\"/></svg><svg class=\"bp-moon\" width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\"><path d=\"M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z\"/></svg></button>";
}
function bp_menu() {
	$items = array(array(bp_t('Overblik','Overview'), '../admin/bogholder_overblik.php', 'home', true));
	$items[] = array(ucfirst(bp_cust_word()), '../admin/bogholder_panel.php', 'list', true);
	$items[] = array(bp_t('Opret og tilknyt','Create and link'), '../admin/bogholder_opret.php', 'plus', bp_can('create'));
	$items[] = array(bp_t('Mit firma','My firm'), '../admin/bogholder_firma.php', 'users', true);
	return $items;
}
// Guard: partner users only. Returns the current user; never returns for anyone else.
function bp_require_partner() {
	bp_tables_ensure();
	if (!bp_ready()) { # 20261029 CL/ASR the update chain creates the panel's tables at the next login to any account after a deploy
		while (ob_get_level()) ob_end_clean();
		print "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>Saldi</title></head><body style=\"font-family:system-ui,sans-serif;max-width:560px;margin:80px auto;color:#222\"><h1 style=\"font-size:20px\">".bp_t('Panelet er ved at blive klar','The panel is being prepared')."</h1><p>".bp_t('Saldi er lige blevet opdateret. Prøv igen om et øjeblik.','Saldi has just been updated. Please try again in a moment.')."</p><p><a href=\"../index/logud.php\">".bp_t('Log ud','Log out')."</a></p></body></html>"; exit;
	}
	$u = partner_current_user();
	if (!$u['partner']) {
		while (ob_get_level()) ob_end_clean();
		print "<meta http-equiv=\"refresh\" content=\"0;URL=".($u['is_operator'] ? '../admin/operator_overblik.php' : '../index/admin_menu.php?klassisk=1')."\">"; exit;
	}
	bp_shell_globals();
	if (!$u['partner']['active']) {
		while (ob_get_level()) ob_end_clean();
		vr_open(array($GLOBALS['vr_panel_label']), bp_t('Din adgang er sat på pause','Your access is paused'), bp_t('Firmaets ejer har deaktiveret din adgang til panelet og kundernes regnskaber. Kontakt din leder, hvis det er en fejl.','The firm\'s owner has deactivated your access to the panel and the customers\' accounts. Contact your manager if this is a mistake.'));
		print "<p><a class=\"vr-btn\" href=\"../index/logud.php\">".findtekst('93|Log ud', $GLOBALS['sprog_id'])."</a></p>"; vr_close(); print "</body></html>"; exit;
	}
	db_modify("update partner_users set last_seen = now() where bruger_id = '".(int)$u['id']."' and (last_seen is null or last_seen < now() - interval '5 minutes')", __FILE__ . " linje " . __LINE__);
	bp_sync_proposals();
	return $u;
}
function bp_crumb_root() { return array($GLOBALS['vr_panel_label'] ?? bp_t('Bogholderpanel','Accountant panel'), '../admin/bogholder_overblik.php'); }
function bp_open($crumbs, $h1, $lead, $actions = '', $pill = '') {
	vr_open($crumbs, $h1, $lead, $actions, $pill);
	print "<link rel=\"stylesheet\" href=\"../css/bogholder_panel.css?v=5.1.4\">\n";
}
function bp_close() { vr_close(); print "</body></html>"; }

// ---------- renderers ----------
function bp_status_html($r) { return "<span class=\"vr-st\"><span class=\"vr-dot ".$r['status'][2]."\"></span>".bp_h($r['status'][1])."</span>"; }
function bp_payer_html($r) { if ($r['is_home']) return "<span class=\"vr-mut2\">".(bp_is_group() ? bp_t('hovedselskab','parent company') : bp_t('eget regnskab','own account'))."</span>"; if (bp_is_group()) return $r['payer'] == 'partner' ? bp_t('Hovedselskabet','The parent company') : bp_t('Selskabet selv','The company itself'); return $r['payer'] == 'partner' ? bp_t('Firmaet','The firm')." <span class=\"vr-mut2\">· ".bp_t('ordning','scheme')."</span>" : bp_t('Kunden','The customer'); }
function bp_plan_html($r) { return $r['plan_name'] ? bp_h($r['plan_name']) : "<span class=\"vr-mut2\">".bp_t('ingen pakke','no plan')."</span>"; }
// Work to do in the ledger, with deep links that open the ledger at that function.
function bp_work_html($r, $compact = false) {
	$id = (int)$r['id']; $o = array();
	if ($r['open_journals'] > 0) $o[] = "<a class=\"bp-count\" href=\"".bp_open_href($id, 'kassekladde')."\" target=\"_blank\" rel=\"noopener\" onclick=\"event.stopPropagation()\"><b>".$r['open_journals']."</b> ".($r['open_journals'] == 1 ? bp_t('kladde','journal') : bp_t('kladder','journals')).($compact ? '' : " <span class=\"vr-mut2\">· ".vr_num($r['journal_lines'])." ".bp_t('linjer','lines')."</span>")."</a>";
	if ($r['pool_docs'] > 0) $o[] = "<a class=\"bp-count\" href=\"".bp_open_href($id, 'pulje')."\" target=\"_blank\" rel=\"noopener\" onclick=\"event.stopPropagation()\"><b>".$r['pool_docs']."</b> ".($r['pool_docs'] == 1 ? bp_t('bilag i puljen','document in the pool') : bp_t('bilag i puljen','documents in the pool'))."</a>";
	if (!$o) return $r['cache'] ? "<span class=\"vr-mut2\">".bp_t('intet åbent','nothing open')."</span>" : "<span class=\"vr-mut2\">–</span>";
	return implode(" <span class=\"vr-mut2\">·</span> ", $o);
}
function bp_open_href($rid, $goto = '') { return "bogholder_panel.php?open=".(int)$rid.($goto ? "&goto=$goto" : ""); }
function bp_star_html($r) { return "<form method=\"post\" class=\"bp-starform\" onclick=\"event.stopPropagation()\"><input type=\"hidden\" name=\"bp_action\" value=\"fav\"><input type=\"hidden\" name=\"regnskab_id\" value=\"".(int)$r['id']."\"><button type=\"submit\" class=\"bp-star\" aria-pressed=\"".($r['fav'] ? 'true' : 'false')."\" title=\"".($r['fav'] ? bp_t('Fjern fra favoritter','Remove from favourites') : bp_t('Tilføj til favoritter','Add to favourites'))."\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"".($r['fav'] ? 'currentColor' : 'none')."\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linejoin=\"round\" aria-hidden=\"true\"><path d=\"M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3 6.4 20.2l1.1-6.2L3 9.6l6.2-.9z\"/></svg></button></form>"; }
function bp_attention_html($r) { if (!$r['attention']) return ''; $o = array(); foreach ($r['attention'] as $a) $o[] = "<a class=\"vr-st\" href=\"".bp_h($a[2])."\" onclick=\"event.stopPropagation()\"><span class=\"vr-dot ".(in_array($a[0], array('trial_over','canceled','proposal_failed')) ? 'vr-bad' : 'vr-warn')."\"></span>".bp_h($a[1])."</a>"; return implode('<br>', $o); }
// The current employee's view of the customers: the owner may narrow to one responsible employee (?emp=)
function bp_scope_rows($includeClosed = false) {
	$rows = bp_customers($includeClosed); $emp = (int)if_isset($_GET, 0, 'emp');
	if ($emp && bp_is_owner()) $rows = array_filter($rows, fn($r) => $r['responsible_id'] == $emp);
	return $rows;
}
function bp_emp_filter_html($page) {
	if (!bp_is_owner()) return '';
	$emp = (int)if_isset($_GET, 0, 'emp'); $h = "<form method=\"get\" action=\"$page\" class=\"vr-inline\">"; foreach ($_GET as $k => $v) if ($k != 'emp' && !is_array($v)) $h .= "<input type=\"hidden\" name=\"".bp_h($k)."\" value=\"".bp_h($v)."\">";
	$h .= "<select name=\"emp\" class=\"vr-sel\" onchange=\"this.form.submit()\"><option value=\"0\">".bp_t('Alle medarbejdere','All employees')."</option>"; foreach (bp_employees() as $e) $h .= "<option value=\"".(int)$e['bruger_id']."\"".($emp == (int)$e['bruger_id'] ? " selected" : "").">".bp_t('Ansvarlig','Responsible').": ".bp_h($e['name'] ?: $e['brugernavn'])."</option>"; return $h."</select></form>";
}
function bp_updated_html($rows) { $t = bp_cache_age($rows); return "<span class=\"bp-updated\">".bp_t('Kladder og bilag læses fra kundernes regnskaber hvert 10. minut','Journals and documents are read from the customer accounts every 10 minutes').($t ? ' · '.bp_t('ældste tal','oldest figure').' '.bp_h(substr($t, 11, 5)) : '')."</span>"; }

} // function_exists
?>
