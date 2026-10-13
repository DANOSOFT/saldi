<?php
// --- admin/vr_ui.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Shared view helpers for the admin-layer pages that use the new page layout
//                  (vis_regnskaber.php, regnskab.php, partnere.php): escaping that follows the database charset,
//                  number/date formatting, da/en texts without the uncached findtekst('plain text') path,
//                  and the page frame (head/tabs/foot).

if (!function_exists('vr_h')) {
function vr_cs() { global $db_encode; return ($db_encode == 'UTF8') ? 'UTF-8' : 'ISO-8859-1'; }
function vr_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, vr_cs()); }
function vr_num($n) { return number_format((int)$n, 0, ',', '.'); }
function vr_t($da, $en) {
	global $sprog_id, $db_encode;
	$t = ($sprog_id == 2) ? $en : $da;
	return ($db_encode == 'UTF8') ? $t : mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8');
}
function vr_rel($ts) { # relative time; $ts unix time or 'Y-m-d H:i:s'
	if (!$ts) return '–';
	if (!is_numeric($ts)) $ts = strtotime($ts);
	if (!$ts) return '–';
	$days = (int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $ts))) / 86400);
	if ($days <= 0) return vr_t('i dag', 'today');
	if ($days == 1) return vr_t('i går', 'yesterday');
	if ($days < 7)   return $days.' '.vr_t('dage siden', 'days ago');
	if ($days < 60)  { $w = (int) round($days/7); return $w.' '.($w==1 ? vr_t('uge siden', 'week ago') : vr_t('uger siden', 'weeks ago')); }
	if ($days < 400) { $mo = (int) round($days/30); return $mo.' '.vr_t('mdr. siden', 'months ago'); }
	$yr = (int) floor($days/365); return $yr.' '.vr_t('år siden', 'years ago');
}
function vr_url($page, $params) {
	$params = array_filter($params, fn($v) => isset($v) && $v !== '' && $v !== false);
	return $page.($params ? '?'.http_build_query($params) : '');
}
function vr_kind($kind) { return $kind == 'koncern' ? vr_t('Koncern', 'Group') : ($kind == 'revisor' ? vr_t('Revisor', 'Auditor') : vr_t('Bogholder', 'Bookkeeper')); }
// ---------- shell: sidebar in the user's colour + topbar, as in the approved prototypes ----------
// 20261012 CL/ASR The operator panel is for Saldi's own staff only (admin flag 'on'). Bookkeepers and other users keep
//                  the existing pages (index/admin_menu.php, vis_regnskaber.php, opret.php …) unchanged; every page in
//                  this shell calls vr_require_operator() and sends anyone else back to the existing menu.
function vr_require_operator() {
	partner_tables_ensure();
	if (partner_current_user()['is_operator']) return;
	while (ob_get_level()) ob_end_clean();
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/admin_menu.php\">"; exit;
}
function vr_menu() {
	global $sprog_id, $mastername;
	if (isset($GLOBALS['vr_menu_items'])) return $GLOBALS['vr_menu_items']; # 20261012 CL/ASR a panel may bring its own menu (bogholderpanelet: admin/bogholder_ui.php)
	$items = array(array(vr_t('Overblik','Overview'), '../admin/operator_overblik.php', 'home', true));
	$items[] = array(vr_t('Regnskaber','Accounts'), '../admin/operator_panel.php', 'list', true);
	$items[] = array(findtekst('339|Opret regnskab', $sprog_id), '../admin/operator_opret.php', 'plus', true);
	$items[] = array(findtekst('341|Slet regnskab', $sprog_id), '../admin/operator_slet.php', 'trash', true);
	$items[] = array(findtekst('777|Brugere', $sprog_id), '../admin/operator_brugere.php', 'user', true);
	$items[] = array(findtekst('613|Indstillinger', $sprog_id), '../admin/operator_indstillinger.php', 'gear', true);
	if (isset($mastername) && $mastername == "ROTARY") $items[] = array(findtekst('567|Kortbetalinger', $sprog_id), '../admin/bankfordeling.php', 'card', true);
	return $items;
}
function vr_icon($k) {
	$d = array(
		'home' => '<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
		'list' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 4v16"/>',
		'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14.5A5 5 0 0 1 22 19"/>',
		'plus' => '<path d="M12 5v14M5 12h14"/>', 'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
		'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
		'shield' => '<path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/>', 'card' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/>',
		'out' => '<path d="M10 17l5-5-5-5M15 12H3M21 3v18"/>', 'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .8-1 1.5M12 17h.01"/>',
	);
	return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.(isset($d[$k]) ? $d[$k] : $d['list']).'</svg>';
}
// Page frame with shell. $crumbs = [[text, href], ..., text]; $actions = html
function vr_open($crumbs, $h1, $lead, $actions = '', $pill = '') {
	global $buttonColor, $buttonTxtColor, $sprog_id, $version, $brugernavn;
	if (!isset($version)) { $version = ''; if (file_exists("../includes/version.php")) include("../includes/version.php"); }
	$u = partner_current_user();
	$self = basename($_SERVER['PHP_SELF']);
	print "<link rel=\"stylesheet\" href=\"../css/operator_panel.css?v=5.2.1\">\n";
	if (!vr_csrf_exempt()) print vr_csrf_js();
	if (isset($_GET['msg'])) print "<script>try{var vrU=new URL(location.href);[...vrU.searchParams.keys()].filter(function(k){return k.indexOf('msg')===0||k==='mk'}).forEach(function(k){vrU.searchParams.delete(k)});history.replaceState(null,'',vrU.pathname+vrU.search+vrU.hash);}catch(e){}</script>"; # a reload does not show the note again
	print "<div class=\"vr-app".(isset($GLOBALS['vr_app_class']) ? ' '.vr_h($GLOBALS['vr_app_class']) : '')."\" style=\"--vr-user:".vr_h($buttonColor).";--vr-user-text:".vr_h($buttonTxtColor).";\">\n"; # 20261013 CL/ASR a panel may add its own shell class
	// sidebar
	// 20261012 CL/ASR Danosoft logo (white, from the brand file) and a collapsible sidebar. The state is kept per browser
	//                  in localStorage and applied before paint by the inline script, so the page does not jump.
	print "<script>try{if(localStorage.getItem('vrSideCollapsed')==='1')document.documentElement.classList.add('vr-collapsed');}catch(e){}</script>";
	$vr_home = isset($GLOBALS['vr_home']) ? $GLOBALS['vr_home'] : '../admin/operator_overblik.php'; # 20261012 CL/ASR the panel's own landing page
	$vr_logo = isset($GLOBALS['vr_logo']) ? $GLOBALS['vr_logo'] : array('../img/danosoft_logo_white.png', '../img/danosoft_mark_white.png', 'Danosoft'); # 20261013 CL/ASR [full, mark, alt] – a panel may bring its own logo
	print "<aside class=\"vr-side\" id=\"vrSide\"><div class=\"vr-logo-row\"><a class=\"vr-logo\" href=\"".vr_h($vr_home)."\" title=\"".vr_t('Overblik','Overview')."\"><img class=\"vr-logo-full\" src=\"".vr_h($vr_logo[0])."\" alt=\"".vr_h($vr_logo[2])."\" height=\"26\"><img class=\"vr-logo-mark\" src=\"".vr_h($vr_logo[1])."\" alt=\"".vr_h($vr_logo[2])."\" height=\"26\"></a>";
	print "<button type=\"button\" class=\"vr-collapse\" id=\"vrCollapse\" onclick=\"vrToggleSide()\" aria-label=\"".vr_t('Fold menuen ind eller ud','Collapse or expand the menu')."\" title=\"".vr_t('Fold menuen ind eller ud','Collapse or expand the menu')."\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.4\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\"><path d=\"M15 6l-6 6 6 6\"/></svg></button></div>";
	print "<div class=\"vr-grp\">".(isset($GLOBALS['vr_panel_label']) ? vr_h($GLOBALS['vr_panel_label']) : ($u['is_operator'] ? vr_t('Operatørpanel','Operator panel') : ($u['partner'] ? vr_t('Bogholderpanel','Accountant panel') : vr_t('Administration','Administration'))))."</div><nav>";
	foreach (vr_menu() as $it) {
		$on = (basename($it[1]) == $self) || ($self == 'partnere.php' && basename($it[1]) == 'operator_panel.php') || (isset($it[4]) && in_array($self, (array)$it[4], true));
		if ($it[3]) print "<a class=\"vr-nav".($on ? " vr-on" : "")."\" href=\"".vr_h($it[1])."\" title=\"".vr_h($it[0])."\">".vr_icon($it[2])."<span class=\"vr-lbl\">".vr_h($it[0])."</span></a>";
		else print "<span class=\"vr-nav vr-dim\" title=\"".vr_h($it[0])."\">".vr_icon($it[2])."<span class=\"vr-lbl\">".vr_h($it[0])."</span></span>";
	}
	print "</nav><div class=\"vr-spacer\"></div><hr><a class=\"vr-nav\" href=\"http://saldi.dk/dok/komigang.html\" target=\"_blank\" title=\"".findtekst('92|Vejledning', $sprog_id)."\">".vr_icon('help')."<span class=\"vr-lbl\">".findtekst('92|Vejledning', $sprog_id)."</span></a><a class=\"vr-nav\" href=\"../index/logud.php\" accesskey=\"L\" title=\"".findtekst('93|Log ud', $sprog_id)."\">".vr_icon('out')."<span class=\"vr-lbl\">".findtekst('93|Log ud', $sprog_id)."</span></a><div class=\"vr-ver\">Saldi version ".vr_h($version)."</div></aside>\n";
	// column: topbar + main
	print "<div class=\"vr-side-scrim\" onclick=\"vrToggleSide()\"></div>";
	print "<div class=\"vr-col\"><div class=\"vr-top\"><button type=\"button\" class=\"vr-menu-btn\" onclick=\"vrToggleSide()\" aria-label=\"".vr_t('Menu','Menu')."\"><svg width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\" aria-hidden=\"true\"><path d=\"M4 7h16M4 12h16M4 17h16\"/></svg></button><nav class=\"vr-bc\" aria-label=\"Du er her\">";
	foreach ($crumbs as $i => $c) { if ($i) print "<i>/</i>"; if (is_array($c)) print "<a href=\"".vr_h($c[1])."\">".vr_h($c[0])."</a>"; else print "<b>".vr_h($c)."</b>"; }
	$ini = strtoupper(mb_substr((string)$brugernavn, 0, 2, vr_cs()));
	$sub = $u['is_operator'] ? vr_t('Saldi · Operatør','Saldi · Operator') : ($u['partner'] ? $u['partner']['name'].' · '.($u['partner']['partner_role'] == 'owner' ? vr_t('Ejer','Owner') : vr_t('Medarbejder','Employee')) : vr_t('Administration','Administration'));
	print "</nav><div class=\"vr-grow\"></div>".(isset($GLOBALS['vr_top_extra']) ? $GLOBALS['vr_top_extra'] : "")."<div class=\"vr-chip\"><span class=\"vr-av\">".vr_h($ini)."</span><span class=\"vr-who\"><span>".vr_h($brugernavn)."</span><small>".vr_h($sub)."</small></span></div></div>\n";
	print "<main class=\"vr\">\n<div class=\"vr-head\">\n<div class=\"vr-title\">\n<h1>".vr_h($h1).($pill ? " <span class=\"vr-pill\">".vr_h($pill)."</span>" : "")."</h1>\n";
	if ($lead) print "<p class=\"vr-lead\">".$lead."</p>\n";
	print "</div>\n<div class=\"vr-acts\">$actions</div>\n</div>\n";
}
function vr_tabs($tabs, $active) { # $tabs = [key => [label, href, count|null]]
	print "<div class=\"vr-tabs\" role=\"tablist\">";
	foreach ($tabs as $k => $t) print "<a class=\"vr-tab\" role=\"tab\" aria-selected=\"".($k == $active ? 'true' : 'false')."\" href=\"".vr_h($t[1])."\">".vr_h($t[0]).(isset($t[2]) && $t[2] !== null ? " <span class=\"vr-cnt\">".(int)$t[2]."</span>" : "")."</a>";
	print "</div>\n";
}
// 20261012 CL/ASR Post/Redirect/Get: after a POST action the page redirects (303) to itself as a GET, carrying the
//                  notes in msg[]. The page is then always drawn from fresh data, and a reload does not repeat the action.
function vr_prg($notes, $url = null, $kind = 'ok') {
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
	if ($url === null) { $q = $_GET; unset($q['msg']); $url = basename($_SERVER['PHP_SELF']).($q ? '?'.http_build_query($q) : ''); }
	$m = array(); foreach ((array)$notes as $n) { $n = trim((string)$n); if ($n !== '') $m[] = mb_substr($n, 0, 500, vr_cs()); }
	if ($kind == 'ok' && preg_match('/mangler|påkrævet|ikke gennemført|findes ikke|kan ikke|allerede|fejl|ugyldig|ukendt|missing|required|not found|cannot|already|invalid|unknown/iu', implode(' ', $m))) $kind = 'warn'; # errors in amber, not green
	if ($m) $url .= (strpos($url, '?') === false ? '?' : '&').http_build_query(array('msg' => array_slice($m, 0, 12)) + ($kind == 'warn' ? array('mk' => 'warn') : array()));
	while (ob_get_level()) ob_end_clean();
	if (php_sapi_name() == 'cli') { print "<!--VR-PRG $url-->"; exit; } # test harness follows this
	if (!headers_sent()) { header('Location: '.$url, true, 303); exit; }
	print "<meta http-equiv=\"refresh\" content=\"0;URL=".vr_h($url)."\"><script>location.replace(".json_encode($url).");</script>"; exit;
}
function vr_flash_kind() { return ($_GET['mk'] ?? '') == 'warn' ? 'warn' : 'ok'; }
function vr_flash() { # notes carried by vr_prg
	$m = $_GET['msg'] ?? null; if ($m === null || $m === '') return array();
	return array_values(array_filter(array_map('strval', (array)$m), fn($x) => $x !== ''));
}
function vr_note($lines, $kind = 'ok') {
	if (!$lines) return;
	print "<div class=\"vr-note vr-note-$kind\" role=\"status\">";
	foreach ((array)$lines as $l) print "<div>".vr_h($l)."</div>";
	print "</div>\n";
}
// One mail with Saldi headers (std_func's send_email() calls mail() twice, so it is not used here).
function vr_mail($to, $subject, $html) {
	if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
	$h = "From: Saldi <info@saldi.dk>\r\nReply-To: info@saldi.dk\r\nContent-Type: text/html; charset=UTF-8\r\n";
	return @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $html, $h);
}
// Warning before opening a live ledger from the master install on ssl3 (ported from vis_regnskaber.php, 20260717 CL/NTR):
// links get onclick="return vrWarnOpen(this)"; details come from master_warning_info.php on click.
function vr_warn_open_enabled() {
	$parts = explode('/', trim((string)if_isset($_SERVER, '', 'PHP_SELF'), '/'));
	return (if_isset($_SERVER, '', 'SERVER_NAME') == 'ssl3.saldi.dk' && if_isset($parts, '', 0) == 'master');
}
function vr_warn_attr() { return vr_warn_open_enabled() ? ' onclick="event.stopPropagation();return vrWarnOpen(this);"' : ''; }
function vr_warn_open_js() {
	if (!vr_warn_open_enabled()) return;
	print "<div class=\"vr-scrim\" id=\"vrWarnScrim\" onclick=\"vrWarnClose()\"></div><div class=\"vr-dlg\" id=\"vrDlgWarn\" role=\"dialog\" aria-modal=\"true\"><h3>Du er ved at åbne et live-regnskab fra master</h3><div class=\"vr-ls\" id=\"vrWarnList\"></div><p>Åbner du et live-regnskab fra master, kan du ændre databasestrukturen, så den bliver inkompatibel med live-versionen, eller forhindre at fremtidige opdateringer migrerer korrekt.</p><div class=\"vr-bs\"><button type=\"button\" class=\"vr-btn\" onclick=\"vrWarnClose()\">Annullér</button><a class=\"vr-btn vr-primary\" id=\"vrWarnGo\" href=\"#\">Åbn alligevel</a></div></div>";
	print "<script>function vrWarnClose(){document.getElementById('vrWarnScrim').classList.remove('vr-on');document.getElementById('vrDlgWarn').classList.remove('vr-open');}\nfunction vrWarnOpen(link){var id=new URLSearchParams(link.search).get('db_id'),list=document.getElementById('vrWarnList');function row(k,v){var d=document.createElement('div'),a=document.createElement('span'),b=document.createElement('span');a.textContent=k;b.textContent=v;d.appendChild(a);d.appendChild(b);return d;}document.getElementById('vrWarnGo').href=link.href;list.innerHTML='';fetch('master_warning_info.php?db_id='+encodeURIComponent(id),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){if(d.error){alert(d.error);return;}list.appendChild(row('Database',d.db));list.appendChild(row('Databasens version',d.dbver));list.appendChild(row('Masters version',d.master));document.getElementById('vrWarnScrim').classList.add('vr-on');document.getElementById('vrDlgWarn').classList.add('vr-open');}).catch(function(){if(confirm('Kunne ikke hente regnskabets oplysninger. Vil du fortsætte alligevel?'))window.location.href=link.href;});return false;}</script>\n";
}
function vr_close() {
	print "</main></div></div>\n";
	print "<script>function vrToggleSide(){var d=document.documentElement;if(window.matchMedia('(max-width:900px)').matches){d.classList.toggle('vr-side-open');return;}var c=d.classList.toggle('vr-collapsed');try{localStorage.setItem('vrSideCollapsed',c?'1':'0');}catch(e){}}document.addEventListener('keydown',function(e){if(e.key==='Escape')document.documentElement.classList.remove('vr-side-open');});</script>\n";
}

// 20261012 CL/ASR CSRF protection for the admin layer: one secret token per login session. It is added to every POST
//                  form by an output filter (no form needs changing), sent as the X-VR-CSRF header by fetch(), and every
//                  POST without a valid token is refused before any action runs. godkend.php (the customer's own approval
//                  page, protected by its one-time link) and the nightly job are exempt. In CLI tests the token comes
//                  from VR_CSRF_TOKEN.
function vr_csrf_token() {
	if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
	if (empty($_SESSION['vr_csrf'])) $_SESSION['vr_csrf'] = (php_sapi_name() == 'cli' && getenv('VR_CSRF_TOKEN')) ? getenv('VR_CSRF_TOKEN') : bin2hex(random_bytes(32));
	return (string)$_SESSION['vr_csrf'];
}
function vr_csrf_exempt() {
	$self = basename($_SERVER['PHP_SELF'] ?? '');
	if ($self == 'godkend.php' || $self == 'usage_snapshot_job.php') return true;
	if (php_sapi_name() == 'cli' && !getenv('VR_CSRF_TOKEN')) return true; # cron/CLI scripts
	return false;
}
function vr_csrf_filter($html) {
	if (stripos($html, '<form') === false) return $html;
	$in = '<input type="hidden" name="vr_csrf" value="'.vr_h(vr_csrf_token()).'">';
	return preg_replace_callback('/<form\b[^>]*\bmethod\s*=\s*(["\']?)post\1[^>]*>/i', fn($m) => $m[0].$in, $html);
}
function vr_csrf_js() { # fetch() sends the token on every non-GET request
	return "<script>(function(){var t=".json_encode(vr_csrf_token()).",f=window.fetch;if(!f||f.vrCsrf)return;window.fetch=function(u,o){o=o||{};if(String(o.method||'GET').toUpperCase()!=='GET'){o.headers=new Headers(o.headers||{});o.headers.set('X-VR-CSRF',t);}return f.call(this,u,o);};window.fetch.vrCsrf=1;})();</script>";
}
if (!vr_csrf_exempt()) {
	if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
		$vr_got = (string)($_POST['vr_csrf'] ?? ($_SERVER['HTTP_X_VR_CSRF'] ?? ''));
		if ($vr_got === '' || !hash_equals(vr_csrf_token(), $vr_got)) {
			while (ob_get_level()) ob_end_clean();
			http_response_code(403);
			if (isset($_SERVER['HTTP_X_VR_CSRF']) || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'json') !== false) { header('Content-Type: application/json'); print json_encode(array('error' => 'Sessionen er udløbet – genindlæs siden og prøv igen.'), JSON_UNESCAPED_UNICODE); exit; }
			print "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>Saldi</title></head><body style=\"font-family:system-ui,sans-serif;max-width:560px;margin:80px auto;color:#222\"><h1 style=\"font-size:20px\">Handlingen blev ikke udført</h1><p>Siden var for gammel eller kom fra et andet sted, så formularen blev afvist af sikkerhedshensyn. Gå tilbage, genindlæs siden og prøv igen.</p><p><a href=\"javascript:history.back()\">Tilbage</a></p></body></html>";
			exit;
		}
	}
	ob_start('vr_csrf_filter');
}
}
?>
