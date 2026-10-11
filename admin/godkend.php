<?php
// --- admin/godkend.php --- ver 5.1.0 --- 2026.10.11 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261011 CL/ASR New. Customer approval of a purchase proposal via a one-time token (no login). Shows the proposal
//                  and its price; "Godkend" applies it (plan change today or add-on) and marks it accepted; "Afvis"
//                  rejects. Token = 48 hex chars from random_bytes. Expired or decided proposals show a notice only.
include("../includes/connect.php");
if (!function_exists('db_select')) include("../includes/db_query.php");
include("../includes/std_func.php");
include("../includes/partnerScope.php");
include("../includes/entitlements.php");
include("vr_ui.php");
include("inc_forslag.php");
$brugernavn = 'kunde'; $sprog_id = 1;
fs_tables_ensure(); ent_tables_ensure(); partner_tables_ensure();
$t = preg_replace('/[^a-f0-9]/', '', (string)if_isset($_REQUEST, '', 't'));
$p = strlen($t) == 48 ? db_fetch_array(db_select("select p.*, r.regnskab from partner_proposals p join regnskab r on r.id = p.regnskab_id where p.token = '$t'", __FILE__ . " linje " . __LINE__)) : null;
$msg = '';
if ($p && $p['status'] == 'open' && $p['expires_at'] < date('Y-m-d')) { db_modify("update partner_proposals set status = 'expired', decided_at = now() where id = '".(int)$p['id']."'", __FILE__ . " linje " . __LINE__); $p['status'] = 'expired'; }
if ($p && $p['status'] == 'open' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decision'])) {
	$err = fs_decide((int)$p['id'], $_POST['decision'] == 'accept', 'kunde (link)');
	if ($err) $msg = $err; else $p['status'] = $_POST['decision'] == 'accept' ? 'accepted' : 'rejected';
}
print "<!DOCTYPE html><html lang=\"da\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><meta name=\"robots\" content=\"noindex\"><title>Saldi – forslag</title><link rel=\"stylesheet\" href=\"../css/vis_regnskaber.css?v=5.1.4\"><style>body{background:#eef3fb;margin:0;font:14px/1.5 Inter,\"Segoe UI\",system-ui,sans-serif;color:#0b1a3a}.gk{max-width:560px;margin:60px auto;padding:0 20px}.gk .vr-card{padding:26px 28px;--vr-user:#014294;--vr-user-text:#fff}.gk h1{font-size:22px;margin:0 0 6px}.gk p{color:#5b6b85;margin:0 0 14px}.gk .price{font-size:18px;font-weight:600;color:#0b1a3a;margin:14px 0}</style></head><body><div class=\"gk\"><div style=\"font-weight:700;font-size:20px;margin-bottom:16px\">Saldi</div><div class=\"vr-card\">";
if (!$p) print "<h1>Linket er ugyldigt</h1><p>Forslaget findes ikke. Kontakt den, der sendte dig linket.</p>";
elseif ($p['status'] == 'accepted') print "<h1>Tak – forslaget er godkendt</h1><p><b>".vr_h(fs_price_text($p['kind'], $p['key']))."</b> er nu aktivt for ".vr_h($p['regnskab']).". Det fremgår af næste faktura.</p>";
elseif ($p['status'] == 'rejected') print "<h1>Forslaget er afvist</h1><p>Der er ikke ændret noget for ".vr_h($p['regnskab']).".</p>";
elseif ($p['status'] == 'expired') print "<h1>Forslaget er udløbet</h1><p>Linket gjaldt til ".vr_h($p['expires_at']).". Bed om et nyt forslag, hvis det stadig er aktuelt.</p>";
else {
	print "<h1>Forslag til ".vr_h($p['regnskab'])."</h1><p>Foreslået af ".vr_h($p['proposed_by'])." den ".vr_h(substr($p['created'],0,10)).".</p><div class=\"price\">".vr_h(fs_price_text($p['kind'], $p['key']))."</div>".($p['reason'] ? "<p>".vr_h($p['reason'])."</p>" : "");
	print "<p>Intet koster noget, før du godkender. Godkender du, træder det i kraft i dag og afregnes efter jeres aftale med Saldi. Linket gælder til ".vr_h($p['expires_at']).".</p>".($msg ? "<p style=\"color:#c2410c\">".vr_h($msg)."</p>" : "");
	print "<form method=\"post\" style=\"display:flex;gap:10px\"><input type=\"hidden\" name=\"t\" value=\"".vr_h($t)."\"><button type=\"submit\" name=\"decision\" value=\"accept\" class=\"vr-btn vr-primary\">Godkend</button><button type=\"submit\" name=\"decision\" value=\"reject\" class=\"vr-btn\">Afvis</button></form>";
}
print "</div><p style=\"font-size:12px;color:#8a97ad;margin-top:14px\">Saldi · Danosoft ApS</p></div></body></html>";
?>
