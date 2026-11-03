<?php
ob_start(); # for the redirect after an action (vr_prg)
@session_start();
$s_id=session_id();
// --- admin/bogholder_overblik.php --- ver 5.1.0 --- 2026.10.12 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261012 CL/ASR New. Landing page of the accountant panel (bogholderpanelet): which customers need attention and
//                  the quickest way on. Counts (active, in trial, needing a decision), customers with work waiting
//                  (open journals, documents in the pool – read from the customer databases through the work cache),
//                  pending actions (invitations, access requests, trials ending, upgrades awaiting the customer,
//                  requests to Saldi), recently opened and favourites, shortcuts by right, and a small price
//                  overview for employees who may see prices. Scoped to the employee's customers; the owner can
//                  narrow to one responsible employee.

$css="../css/standard.css";
$title="Bogholderpanel";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("../includes/entitlements.php");
include("../includes/partnerPanel.php");
include("vr_ui.php");
include("inc_betalinger.php");
include("bogholder_ui.php");
if ($db != $sqdb) { print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/logud.php\">"; exit; }
$me = bp_require_partner();
$notes = vr_flash();

if (isset($_POST['bp_action'])) {
	if ($_POST['bp_action'] == 'fav') { $on = bp_fav_toggle((int)if_isset($_POST, 0, 'regnskab_id')); $notes[] = $on ? bp_t('Tilføjet til favoritter','Added to favourites') : bp_t('Fjernet fra favoritter','Removed from favourites'); }
	elseif ($_POST['bp_action'] == 'refresh') { @set_time_limit(300); $all = bp_customers(false); $n = bp_cache_refresh($all, true, 500); $notes[] = bp_t('Kladder og bilag er læst igen for','Journals and documents re-read for').' '.$n.' '.bp_cust_word(); }
	vr_prg($notes);
}

$rows = bp_scope_rows(false);
bp_cache_refresh($rows);
$p = bp_partner(); $custWord = bp_cust_word();
$nTrial = 0; $nTrialSoon = 0; $nAtt = 0; $nWork = 0; $nScheme = 0; $work = array(); $att = array();
foreach ($rows as $r) {
	if ($r['status'][0] == 'trial') { $nTrial++; if ($r['trial_left'] <= 7) $nTrialSoon++; }
	if ($r['attention']) { $nAtt++; $att[] = $r; }
	if ($r['work']) { $nWork++; $work[] = $r; }
	if ($r['payer'] == 'partner' && !$r['is_home']) $nScheme++;
}
usort($work, fn($a, $b) => ($b['journal_lines'] + $b['pool_docs'] * 10) <=> ($a['journal_lines'] + $a['pool_docs'] * 10));

// ---------- pending actions: everything that waits for someone ----------
$pending = array();
if (bp_can('users')) foreach (bp_invites('sent') as $i) $pending[] = array('t' => ($i['kind'] == 'employee' ? bp_t('Invitation til medarbejder','Employee invitation') : bp_t('Invitation til kundebruger','Customer user invitation')).': '.($i['name'] ?: $i['email']), 's' => ($i['regnskab'] ? bp_h($i['regnskab']).' · ' : '').bp_t('sendt','sent').' '.bp_date($i['sent_at']).' · '.bp_t('ubesvaret – gensend eller tilbagekald','unanswered – resend or revoke'), 'h' => 'bogholder_firma.php?tab=invitationer', 'a' => bp_t('Invitationer','Invitations'), 'w' => strtotime($i['sent_at']));
if (bp_can('create')) foreach (bp_access_requests('open') as $a) $pending[] = array('t' => bp_t('Adgang til','Access to').' '.$a['regnskab'], 's' => bp_t('afventer','awaiting').' '.($a['sent_to'] == bp_saldi_mail() ? 'Saldi' : bp_t('kundens godkendelse','the customer\'s approval')).' · '.bp_t('sendt','sent').' '.bp_date($a['sent_at']), 'h' => 'bogholder_opret.php?tab=tilknyt', 'a' => bp_t('Anmodninger','Requests'), 'w' => strtotime($a['sent_at']));
foreach ($att as $r) foreach ($r['attention'] as $x) $pending[] = array('t' => $r['regnskab'], 's' => $x[1], 'h' => $x[2], 'a' => in_array($x[0], array('trial_soon','trial_over')) ? bp_t('Vælg pakke','Choose plan') : (in_array($x[0], array('proposal_open','proposal_failed')) ? bp_t('Se forslag','View proposal') : bp_t('Se status','View status')), 'w' => in_array($x[0], array('trial_over','proposal_failed','canceled')) ? 0 : 1);
usort($pending, fn($a, $b) => $a['w'] <=> $b['w']);

$h = (int)date('G'); $greet = $h < 10 ? bp_t('Godmorgen','Good morning') : ($h < 17 ? bp_t('Goddag','Good day') : bp_t('Godaften','Good evening'));
$acts = '';
if (bp_can('create')) $acts .= "<a class=\"vr-btn\" href=\"bogholder_opret.php?tab=tilknyt\">".bp_t('Tilknyt regnskab','Link account')."</a><a class=\"vr-btn vr-primary\" href=\"bogholder_opret.php\">+ ".bp_t('Opret kunderegnskab','Create customer account')."</a>";
elseif (bp_can('users')) $acts .= "<a class=\"vr-btn vr-primary\" href=\"bogholder_firma.php?tab=medarbejdere#inviter\">".bp_t('Invitér medarbejder','Invite employee')."</a>";
bp_open(array(bp_t('Overblik','Overview')), $greet.', '.bp_display_name(), bp_h($p['name']).' · '.count($rows).' '.bp_cust_word(count($rows) != 1).' · '.date('j. ').vr_t(array('januar','februar','marts','april','maj','juni','juli','august','september','oktober','november','december')[(int)date('n')-1], date('F')).date(' Y'), $acts);
vr_note($notes, vr_flash_kind());

// ---------- counts ----------
print "<section class=\"vr-sect\"><h2 style=\"display:flex;align-items:center;gap:12px\">".bp_t('Dine','Your')." ".$custWord."<span class=\"vr-grow\"></span>".bp_emp_filter_html('bogholder_overblik.php')."</h2><div class=\"vr-card\"><div class=\"vr-stats bp-stats3\">";
$emp = (int)if_isset($_GET, 0, 'emp'); $lq = $emp ? "&emp=$emp" : '';
print "<a class=\"vr-stat\" href=\"bogholder_panel.php?f=open$lq\"><span>".bp_t('Aktive regnskaber','Active accounts')."</span><b>".count($rows)."</b><small>".($nScheme ? $nScheme.' '.(bp_is_group() ? bp_t('faktureres via hovedselskabet','invoiced via the parent') : bp_t('i bogholderordningen','in the scheme')).' · ' : '').$nWork.' '.bp_t('med arbejde der venter','with work waiting')."</small></a>";
print "<a class=\"vr-stat".($nTrialSoon ? ' bp-warn' : '')."\" href=\"bogholder_panel.php?f=trial$lq\"><span>".bp_t('I prøveperiode','In trial')."</span><b>$nTrial</b><small>".($nTrialSoon ? $nTrialSoon.' '.bp_t('udløber inden for 7 dage','end within 7 days') : bp_t('ingen udløber inden for 7 dage','none end within 7 days'))."</small></a>";
print "<a class=\"vr-stat".($nAtt ? ' bp-bad' : '')."\" href=\"bogholder_panel.php?f=attention$lq\"><span>".bp_t('Kræver handling','Need a decision')."</span><b>$nAtt</b><small>".($nAtt ? bp_t('prøveperioder, opgraderinger og anmodninger','trials, upgrades and requests') : bp_t('intet venter på en beslutning','nothing waits for a decision'))."</small></a>";
print "</div></div></section>";

// ---------- work + pending ----------
print "<div class=\"bp-grid\">";
print "<section class=\"vr-sect\"><h2>".bp_t('Regnskaber der kræver arbejde','Accounts with work waiting')." <small>".count($work)."</small></h2><div class=\"vr-card\">";
foreach (array_slice($work, 0, 8) as $r) { $id = (int)$r['id'];
	print "<div class=\"bp-row\"><div><b><a href=\"bogholder_panel.php?regnskab_id=$id\">".bp_h($r['regnskab'])."</a></b><small>".bp_work_html($r).($r['responsible'] ? " <span class=\"vr-mut2\">· ".bp_h($r['responsible'])."</span>" : "")."</small></div><div class=\"bp-acts\"><a href=\"".bp_open_href($id, $r['open_journals'] ? 'kassekladde' : 'pulje')."\" target=\"_blank\" rel=\"noopener\">".bp_t('Åbn','Open')." &rsaquo;</a></div></div>";
}
if (!$work) print "<div class=\"vr-empty\"><b>".bp_t('Alt er bogført','Everything is posted')."</b><span>".bp_t('Ingen af dine kunder har åbne kassekladder eller bilag i puljen lige nu.','None of your customers have open journals or documents in the pool right now.')."</span></div>";
print "<div class=\"vr-foot\">".bp_updated_html($rows)."<span class=\"vr-grow\"></span>".(count($work) > 8 ? "<a class=\"vr-link\" href=\"bogholder_panel.php?f=work$lq\">".bp_t('Se alle','See all')." (".count($work).")</a> · " : "")."<form method=\"post\" style=\"display:inline\"><input type=\"hidden\" name=\"bp_action\" value=\"refresh\"><button type=\"submit\" class=\"vr-link\">".bp_t('Opdatér nu','Refresh now')."</button></form></div></div></section>";
print "<section class=\"vr-sect\"><h2>".bp_t('Afventende handlinger','Pending actions')." <small>".count($pending)."</small></h2><div class=\"vr-card\">";
foreach (array_slice($pending, 0, 10) as $x) print "<div class=\"bp-row\"><div><b>".bp_h($x['t'])."</b><small>".$x['s']."</small></div><div class=\"bp-acts\"><a href=\"".bp_h($x['h'])."\">".bp_h($x['a'])." &rsaquo;</a></div></div>";
if (!$pending) print "<div class=\"vr-empty\"><b>".bp_t('Intet afventer','Nothing pending')."</b><span>".bp_t('Invitationer, adgangsanmodninger, prøveperioder tæt på udløb og opgraderinger vises her, når de kræver et næste skridt.','Invitations, access requests, trials near their end and upgrades appear here when they need a next step.')."</span></div>";
if (count($pending) > 10) print "<div class=\"vr-foot\"><span class=\"vr-grow\"></span><a class=\"vr-link\" href=\"bogholder_panel.php?f=attention$lq\">".bp_t('Se alle','See all')." (".count($pending).")</a></div>";
print "</div></section></div>";

// ---------- recent + favourites | shortcuts + prices ----------
print "<div class=\"bp-grid bp-even\"><div class=\"bp-stack\">";
$recent = bp_recent(6); $all = bp_customers(true);
print "<section class=\"vr-sect\"><h2>".bp_t('Senest åbnede','Recently opened')."</h2><div class=\"vr-card\">";
$n = 0; foreach ($recent as $rid => $when) { if (!isset($all[$rid])) continue; $r = $all[$rid]; $n++;
	print "<div class=\"bp-row\"><div><b><a href=\"bogholder_panel.php?regnskab_id=$rid\">".bp_h($r['regnskab'])."</a></b><small>".bp_t('åbnet','opened')." ".vr_rel($when)." · ".bp_status_html($r)."</small></div><div class=\"bp-acts\">".bp_star_html($r)."<a href=\"".bp_open_href($rid)."\" target=\"_blank\" rel=\"noopener\">".bp_t('Åbn','Open')." &rsaquo;</a></div></div>"; }
if (!$n) print "<div class=\"vr-empty\"><span>".bp_t('De regnskaber, du åbner fra panelet, vises her.','Accounts you open from the panel appear here.')."</span></div>";
print "</div></section>";
$favs = array_filter($all, fn($r) => $r['fav']);
print "<section class=\"vr-sect\"><h2>".bp_t('Favoritter','Favourites')." <small>".count($favs)."</small></h2><div class=\"vr-card\">";
foreach ($favs as $r) { $rid = (int)$r['id']; print "<div class=\"bp-row\"><div><b><a href=\"bogholder_panel.php?regnskab_id=$rid\">".bp_h($r['regnskab'])."</a></b><small>".bp_work_html($r, true)."</small></div><div class=\"bp-acts\">".bp_star_html($r)."<a href=\"".bp_open_href($rid)."\" target=\"_blank\" rel=\"noopener\">".bp_t('Åbn','Open')." &rsaquo;</a></div></div>"; }
if (!$favs) print "<div class=\"vr-empty\"><span>".bp_t('Markér en kunde med stjernen i kundelisten for at få den herop.','Star a customer in the list to keep it here.')."</span></div>";
print "</div></section></div><div class=\"bp-stack\">";
// shortcuts by right
print "<section class=\"vr-sect\"><h2>".bp_t('Genveje','Shortcuts')."</h2><div class=\"vr-card vr-menu\">";
$sc = array(
	array(bp_t('Opret kunderegnskab','Create customer account'), (bp_is_group() ? bp_t('Nyt selskab i koncernen – via hovedselskabet eller selv betalt','New company in the group – via the parent or self-paid') : bp_t('Nyt regnskab i kundens navn – firmaet eller kunden betaler','New account in the customer\'s name – the firm or the customer pays')), 'bogholder_opret.php', bp_can('create'), 'plus'),
	array(bp_t('Tilknyt eksisterende regnskab','Link existing account'), bp_t('Bed kunden om adgang til et regnskab, der allerede findes','Ask the customer for access to an existing account'), 'bogholder_opret.php?tab=tilknyt', bp_can('create'), 'list'),
	array(bp_t('Invitér medarbejder','Invite employee'), bp_t('Kollega med adgang til udvalgte eller alle kunder','Colleague with access to selected or all customers'), 'bogholder_firma.php?tab=medarbejdere#inviter', bp_can('users'), 'user'),
	array(bp_t('Invitér kundebruger','Invite customer user'), bp_t('Vælg kunden i listen og brug fanen Brugere','Pick the customer in the list and use the Users tab'), 'bogholder_panel.php', bp_can('users'), 'users'),
	array(bp_t('Mit firma','My firm'), bp_t('Medarbejdere, rettigheder, firmaoplysninger og log','Employees, rights, firm details and log'), 'bogholder_firma.php', true, 'gear'),
);
foreach ($sc as $s) { if ($s[3]) print "<a class=\"vr-frow\" href=\"".bp_h($s[2])."\">".vr_icon($s[4])."<div><b>".bp_h($s[0])."</b><small>".bp_h($s[1])."</small></div><span class=\"vr-chev\">&rsaquo;</span></a>"; else print "<div class=\"vr-frow\" style=\"opacity:.5;cursor:default\">".vr_icon($s[4])."<div><b>".bp_h($s[0])."</b><small>".bp_t('Kræver rettighed','Requires a right')."</small></div><span></span></div>"; }
print "</div></section>";
if (bp_can('prices') && bp_is_group()) { # 20261029 CL/ASR group: monthly price of the combined invoice
	list($rows, $plans, $unpriced, $users, $up, $usersOre, $total) = bp_group_price();
	print "<section class=\"vr-sect\"><h2>".bp_t('Prisoverblik','Price overview')." <small>".bp_t('koncernens samlede faktura','the group\'s combined invoice')."</small></h2><div class=\"vr-card\"><div class=\"bp-price-row\"><div class=\"bp-price\"><span>".bp_t('Selskaber på samlet faktura','Companies on the combined invoice')."</span><b>".count($rows)."</b></div><div class=\"bp-price\"><span>".bp_t('Koncernbrugere','Group users')."</span><b>$users<small>".bp_t('ERP-brugere','ERP users')."</small></b></div><div class=\"bp-price\"><span>".bp_t('Samlet pr. måned','Total per month')."</span><b>".bp_kr($total)."<small>".bp_t('ekskl. moms','excl. VAT')."</small></b></div></div><div class=\"vr-foot\"><span>".bp_t('Pakker','Plans')." ".bp_kr($plans)." + ".bp_t('brugere','users')." ".bp_kr($usersOre)."</span><span class=\"vr-grow\"></span><a class=\"vr-link\" href=\"bogholder_firma.php?tab=priser\">".bp_t('Se specifikation','See breakdown')." &rsaquo;</a></div></div></section>";
} elseif (bp_can('prices')) {
	list($n, $list, $pct, $total, $avg) = bp_price_calc(count(bp_scheme_rows()));
	print "<section class=\"vr-sect\"><h2>".bp_t('Prisoverblik','Price overview')." <small>".bp_t('bogholderordningen','the bookkeeper scheme')."</small>".(BP_PRICING_PROVISIONAL ? " <span class=\"bp-prov\">".bp_t('foreløbige satser','provisional rates')."</span>" : "")."</h2><div class=\"vr-card\"><div class=\"bp-price-row\">";
	print "<div class=\"bp-price\"><span>".bp_t('Finans-regnskaber i ordningen','Finans accounts in the scheme')."</span><b>$n</b></div><div class=\"bp-price\"><span>".bp_t('Samlet pr. måned','Total per month')."</span><b>".bp_kr($total)."<small>".bp_t('ekskl. moms','excl. VAT')."</small></b></div><div class=\"bp-price\"><span>".bp_t('Gennemsnit pr. regnskab','Average per account')."</span><b>".($n ? bp_kr($avg) : '–').($pct ? "<small>".($pct + 0)." % ".bp_t('rabat','discount')."</small>" : "")."</b></div>";
	print "</div><div class=\"vr-foot\"><span>".bp_t('Listepris','List price')." ".bp_kr($list)."/".bp_t('md.','mo.')." ".bp_t('pr. Finans-regnskab','per Finans account')."</span><span class=\"vr-grow\"></span><a class=\"vr-link\" href=\"bogholder_firma.php?tab=priser\">".bp_t('Åbn prisberegneren','Open the price calculator')." &rsaquo;</a></div></div></section>";
}
print "</div></div>";
bp_close();
?>
