<?php
// --- admin/inc_forslag.php --- ver 5.1.1 --- 2026.11.09 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261011 CL/ASR New. Purchase proposals (tilkøb): the operator (or later a partner) proposes a plan or an add-on; the customer's billing contact gets a one-time link (admin/godkend.php?t=…) and nothing becomes payable until they approve.
//                 The operator sees open proposals on the customer card and may approve on the customer's behalf (e.g. agreed by phone) or reject.
//                 Proposals expire after 30 days. Table partner_proposals from the panels spec §4, plus token / sent_to / expires_at.
// 20261029 CL/ASR partner_proposals is created by the update chain (includes/opdat_func/operatorPanelSchema.php).
// 20261109 CL/ASR fs_decide() claims the proposal with one update that only matches an open, unexpired proposal and applies it in the same transaction, so two clicks cannot both apply it (review #785).
//                 An accepted add-on goes through ent_accept_addon(): one more unit of a counted add-on, an on/off add-on the account has is left as it is, and a proposal for an on/off add-on it already has is refused.
//                 fs_create() reports a failed e-mail to the operator as a warning and clears sent_to, instead of saying the proposal was sent.
//                 An address PHP does not accept as e-mail (e.g. 'kunde@firma', which the browser's type=email lets through) is refused before the proposal is stored, where it was quietly dropped.
//                 An on/off add-on the account has only for a limited time (e.g. a trial) can still be proposed for purchase.
//                 fs_create() refuses an add-on proposal that ent_addon_purchase_error() rejects, i.e. an add-on not on sale for the account's plan or a counted add-on allocated for a limited time, and tells the operator why (review #785).
// 20261109 CL/ASR The approval link in the mail is built from partner_mail_host() (includes/partnerScope.php), not from SERVER_NAME, which can come from the request's Host header (review #960).

if (!function_exists('fs_tables_ensure')) {
function fs_tables_ensure() {
	# 20261029 CL/ASR The tables come from the update chain (includes/opdat_func/operatorPanelSchema.php via betweenUpdates.php).
	return;
}
function fs_price_text($kind, $key) {
	$c = ent_catalog();
	if ($kind == 'plan' && isset($c['plans'][$key])) { $p = $c['plans'][$key]; return $p['name'].' · '.($p['price_ore'] === null ? vr_t('pris efter aftale','price by agreement') : ent_kr((int)$p['price_ore']).'/md.'); }
	if ($kind == 'addon' && isset($c['addons'][$key])) { $a = $c['addons'][$key]; return $a['name'].(ent_addon_counted($a) ? ' (+1)' : '').' · '.($a['price_ore'] === null ? vr_t('pris efter aftale','price by agreement') : ent_kr((int)$a['price_ore']).'/md.'); }
	return $key;
}
// Create a proposal and mail the link. $to = billing contact e-mail (empty → the operator decides on the card).
function fs_create($rid, $kind, $key, $reason, $to, $partner_id = null) {
	fs_tables_ensure(); $c = ent_catalog();
	if (!in_array($kind, array('plan','addon'), true) || ($kind == 'plan' && !isset($c['plans'][$key])) || ($kind == 'addon' && !isset($c['addons'][$key]))) return vr_t('Ukendt pakke eller tillæg','Unknown plan or add-on');
	if (db_fetch_array(db_select("select 1 from partner_proposals where regnskab_id = '".(int)$rid."' and kind = '$kind' and key = '".db_escape_string($key)."' and status = 'open'", __FILE__ . " linje " . __LINE__))) return vr_t('Der er allerede et åbent forslag om det','There is already an open proposal for that');
	if ($kind == 'addon' && !ent_addon_counted($c['addons'][$key]) && db_fetch_array(db_select("select 1 from regnskab_addons where regnskab_id = '".(int)$rid."' and addon_key = '".db_escape_string($key)."' and expires_at is null", __FILE__ . " linje " . __LINE__))) return vr_t('Kunden har allerede tillægget','The customer already has this add-on'); # a time-limited one (trial) may be offered for purchase
	if ($kind == 'addon' && ($why = ent_addon_purchase_error($rid, $key)) !== '') return $why.' '.vr_t('Forslaget er ikke oprettet.','The proposal was not created.'); # not on sale for the plan, or a counted add-on allocated for a limited time
	$token = bin2hex(random_bytes(24)); $exp = date('Y-m-d', strtotime('+30 days'));
	if ((string)$to !== '' && !filter_var($to, FILTER_VALIDATE_EMAIL)) return vr_t('Ugyldig e-mailadresse, forslaget er ikke oprettet','Invalid e-mail address, the proposal was not created').': '.$to; # refused, not blanked: the card would otherwise say "sent"
	db_modify("insert into partner_proposals (regnskab_id, partner_id, proposed_by, kind, key, reason, token, sent_to, expires_at) values ('".(int)$rid."', ".($partner_id ? "'".(int)$partner_id."'" : 'NULL').", '".db_escape_string(ent_actor())."', '$kind', '".db_escape_string($key)."', '".db_escape_string($reason)."', '$token', '".db_escape_string((string)$to)."', '$exp')", __FILE__ . " linje " . __LINE__);
	partner_log('proposal.created', (int)$rid, $partner_id, "$kind $key – $reason".($to ? " → $to" : ' (ingen e-mail)'));
	if ($to) {
		$reg = db_fetch_array(db_select("select regnskab from regnskab where id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__));
		$url = 'https://'.partner_mail_host().rtrim(dirname($_SERVER['PHP_SELF']), '/').'/godkend.php?t='.$token; # a trusted host, not the Host header
		$html = "<p>Hej</p><p>Der er et forslag til <b>".vr_h($reg['regnskab'])."</b> i Saldi:</p><p><b>".vr_h(fs_price_text($kind, $key))."</b></p>".($reason ? "<p>".vr_h($reason)."</p>" : "")."<p>Intet ændres, og intet koster noget, før du godkender. Linket virker til ".date('d-m-Y', strtotime($exp)).".</p><p><a href=\"".vr_h($url)."\">Se og godkend forslaget</a></p><p>Venlig hilsen<br>Saldi</p>";
		if (!vr_mail($to, 'Forslag til '.$reg['regnskab'].': '.fs_price_text($kind, $key), $html)) { # the proposal stays open; the card then shows it without e-mail
			db_modify("update partner_proposals set sent_to = '' where token = '$token'", __FILE__ . " linje " . __LINE__);
			partner_log('proposal.mail_failed', (int)$rid, $partner_id, "$kind $key → $to");
			return vr_t('Forslaget er oprettet, men e-mailen til','The proposal was created, but the e-mail to').' '.$to.' '.vr_t('kunne ikke sendes, så kunden kan ikke se linket. Godkend på kundens vegne, eller afvis forslaget og send det igen.','could not be sent, so the customer cannot see the link. Approve on the customer\'s behalf, or reject the proposal and send it again.');
		}
	}
	return '';
}
function fs_open($rid) {
	fs_tables_ensure(); $out = array();
	db_modify("update partner_proposals set status = 'expired', decided_at = now() where status = 'open' and expires_at < current_date", __FILE__ . " linje " . __LINE__);
	$q = db_select("select p.*, pa.name as pname from partner_proposals p left join partners pa on pa.id = p.partner_id where p.regnskab_id = '".(int)$rid."' and p.status = 'open' order by p.id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $out[] = $r; return $out;
}
// Apply or reject. $by = 'kunde (link)' or the operator's username.
function fs_decide($id, $accept, $by, $rid = 0) { # $rid: the card the decision comes from (0 = the customer's own link, matched by token)
	fs_tables_ensure();
	# 20261109 CL/ASR Single use: the update that moves the proposal out of 'open' claims it, and the change is applied in the same
	#                  transaction. A second click waits for the first, then finds nothing open; an error rolls everything back.
	transaktion('begin');
	$p = db_fetch_array(db_select("update partner_proposals set status = '".($accept ? 'accepted' : 'rejected')."', decided_by = '".db_escape_string($by)."', decided_at = now() where id = '".(int)$id."' and status = 'open' and (expires_at is null or expires_at >= current_date)".($rid ? " and regnskab_id = '".(int)$rid."'" : "")." returning *", __FILE__ . " linje " . __LINE__));
	if (!$p) { transaktion('rollback'); return vr_t('Forslaget er ikke længere åbent – det er allerede afgjort eller udløbet','The proposal is no longer open – it was already decided or has expired'); }
	$err = '';
	if ($accept) $err = ($p['kind'] == 'plan') ? ent_set_plan((int)$p['regnskab_id'], $p['key'], date('Y-m-d'), 'Godkendt forslag af '.$by) : ent_accept_addon((int)$p['regnskab_id'], $p['key']);
	if ($err) { transaktion('rollback'); ent_resolve_flush((int)$p['regnskab_id']); return $err; }
	partner_log($accept ? 'proposal.accepted' : 'proposal.rejected', (int)$p['regnskab_id'], $p['partner_id'] ? (int)$p['partner_id'] : null, "$p[kind] $p[key] – af $by");
	transaktion('commit');
	return '';
}
function fs_actions($rid, &$notes, $defaultTo) {
	if (!isset($_POST['fs_action'])) return;
	$a = $_POST['fs_action'];
	if ($a == 'create') {
		$kind = if_isset($_POST, '', 'kind'); $key = trim((string)if_isset($_POST, '', $kind == 'plan' ? 'plan_key' : 'addon_key')); $to = trim((string)if_isset($_POST, '', 'to'));
		$err = fs_create($rid, $kind, $key, trim((string)if_isset($_POST, '', 'reason')), $to);
		$notes[] = $err ? $err : ($to ? vr_t('Forslaget er sendt til','Proposal sent to').' '.$to : vr_t('Forslag oprettet uden e-mail – afgør det her','Proposal created without e-mail – decide here'));
	} elseif ($a == 'accept' || $a == 'reject') {
		$err = fs_decide((int)if_isset($_POST, 0, 'proposal_id'), $a == 'accept', ent_actor(), $rid);
		$notes[] = $err ? $err : ($a == 'accept' ? vr_t('Forslaget er godkendt og gennemført','Proposal approved and applied') : vr_t('Forslaget er afvist','Proposal rejected'));
	}
}
function fs_render($rid, $ent, $cat, $defaultTo) {
	$open = fs_open($rid);
	print "<section class=\"vr-sect\"><h2>".vr_t('Forslag om tilkøb','Purchase proposals')." <small>".count($open)." ".vr_t('åbne','open')." · ".vr_t('kunden godkender via link, før noget koster penge','the customer approves via link before anything is charged')."</small></h2><div class=\"vr-card\">";
	foreach ($open as $o) {
		print "<div class=\"vr-frow\"><div><b>".vr_h(fs_price_text($o['kind'], $o['key']))."</b><small>".vr_t('foreslået af','proposed by')." ".vr_h($o['pname'] ?: $o['proposed_by'])." ".vr_h(substr($o['created'],0,10)).($o['sent_to'] ? ' · '.vr_t('sendt til','sent to').' '.vr_h($o['sent_to']) : ' · '.vr_t('ingen e-mail','no e-mail')).' · '.vr_t('udløber','expires').' '.vr_h($o['expires_at']).($o['reason'] ? '<br>'.vr_h($o['reason']) : '')."</small></div><span class=\"vr-lvl\">".vr_t('Afventer kunden','Awaiting customer')."</span>";
		print "<span class=\"vr-inline\"><form method=\"post\"><input type=\"hidden\" name=\"fs_action\" value=\"accept\"><input type=\"hidden\" name=\"proposal_id\" value=\"".(int)$o['id']."\"><button type=\"submit\" class=\"vr-btn\" onclick=\"return confirm('".vr_t('Godkend på kundens vegne? Det bliver betalingspligtigt nu.','Approve on behalf of the customer? It becomes payable now.')."')\">".vr_t('Godkend på kundens vegne','Approve for customer')."</button></form><form method=\"post\"><input type=\"hidden\" name=\"fs_action\" value=\"reject\"><input type=\"hidden\" name=\"proposal_id\" value=\"".(int)$o['id']."\"><button type=\"submit\" class=\"vr-link\">".vr_t('Afvis','Reject')."</button></form></span></div>";
	}
	if (!$open) print "<div class=\"vr-empty\" style=\"padding:18px\"><span>".vr_t('Ingen åbne forslag.','No open proposals.')."</span></div>";
	print "<div class=\"vr-foot\" style=\"display:block\"><form method=\"post\" class=\"vr-inline\"><input type=\"hidden\" name=\"fs_action\" value=\"create\">";
	print "<select name=\"kind\" class=\"vr-sel\" onchange=\"this.form.addon_key.hidden=this.value!='addon';this.form.plan_key.hidden=this.value!='plan'\"><option value=\"addon\">".vr_t('Tillæg','Add-on')."</option><option value=\"plan\">".vr_t('Pakke','Plan')."</option></select>";
	print "<select name=\"addon_key\" class=\"vr-sel\">"; foreach ($cat['addons'] as $k => $a) print "<option value=\"$k\">".vr_h($a['name']).($a['price_ore'] !== null ? ' · '.ent_kr((int)$a['price_ore']).'/md.' : '')."</option>"; print "</select>";
	print "<select name=\"plan_key\" class=\"vr-sel\" hidden>"; foreach ($cat['plans'] as $k => $pl) print "<option value=\"$k\">".vr_h($pl['name']).($pl['price_ore'] !== null ? ' · '.ent_kr((int)$pl['price_ore']).'/md.' : '')."</option>"; print "</select>";
	print "<input class=\"vr-inp\" name=\"to\" type=\"email\" value=\"".vr_h($defaultTo)."\" placeholder=\"".vr_t('Kundens e-mail','Customer e-mail')."\" style=\"width:220px\"><input class=\"vr-inp\" name=\"reason\" placeholder=\"".vr_t('Begrundelse til kunden','Reason for the customer')."\" style=\"flex:1;min-width:200px\"><button type=\"submit\" class=\"vr-btn\">".vr_t('Send forslag','Send proposal')."</button></form></div></div></section>";
}
}
?>
