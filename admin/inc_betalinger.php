<?php
// --- admin/inc_betalinger.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Area 3 "Betalinger og fakturering" on the customer card. Everything comes from the
//                  existing systems: the billing ledger on ssl3 through the REST API (customer: contact, e-mail,
//                  terms, Betalingsservice, Stripe status; invoices: date, terms, paid, sums) and the plan
//                  interval from the entitlement layer. Status per invoice: Betalt / Afventer / Forfalden; "Fejlet"
//                  when Stripe reports past_due or unpaid. The operator can store the billing customer number,
//                  channel and payer in regnskab_billing (master) when the API cannot tell or the match by CVR
//                  fails. Nothing here writes to the billing ledger.

if (!function_exists('bill_tables_ensure')) {
function bill_tables_ensure() {
	static $d = false; if ($d) return; $d = true;
	if (db_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = 'regnskab_billing'", __FILE__ . " linje " . __LINE__))) return;
	db_modify("CREATE TABLE IF NOT EXISTS regnskab_billing (regnskab_id int PRIMARY KEY, billing_kontonr varchar(30), channel varchar(20), payer_name varchar(120), payer_email varchar(120), note text, updated_by text, updated_at timestamp DEFAULT now())", __FILE__ . " linje " . __LINE__);
}
function bill_get($rid) { bill_tables_ensure(); $r = db_fetch_array(db_select("select * from regnskab_billing where regnskab_id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__)); return $r ? $r : array('billing_kontonr' => '', 'channel' => '', 'payer_name' => '', 'payer_email' => '', 'note' => ''); }
function bill_actions($rid, &$notes) {
	if (!isset($_POST['bill_action']) || $_POST['bill_action'] != 'save') return;
	bill_tables_ensure();
	$ch = if_isset($_POST, '', 'channel'); if (!in_array($ch, array('', 'pbs', 'stripe', 'invoice', 'other'), true)) $ch = '';
	$v = array('billing_kontonr' => trim(if_isset($_POST, '', 'billing_kontonr')), 'channel' => $ch, 'payer_name' => trim(if_isset($_POST, '', 'payer_name')), 'payer_email' => trim(if_isset($_POST, '', 'payer_email')), 'note' => trim(if_isset($_POST, '', 'note')));
	$set = array(); foreach ($v as $k => $val) $set[] = "$k = '".db_escape_string($val)."'";
	if (db_fetch_array(db_select("select 1 from regnskab_billing where regnskab_id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__))) db_modify("update regnskab_billing set ".implode(', ', $set).", updated_by = '".db_escape_string(ent_actor())."', updated_at = now() where regnskab_id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__);
	else db_modify("insert into regnskab_billing (regnskab_id, billing_kontonr, channel, payer_name, payer_email, note, updated_by) values ('".(int)$rid."', '".db_escape_string($v['billing_kontonr'])."', '".db_escape_string($v['channel'])."', '".db_escape_string($v['payer_name'])."', '".db_escape_string($v['payer_email'])."', '".db_escape_string($v['note'])."', '".db_escape_string(ent_actor())."')", __FILE__ . " linje " . __LINE__);
	partner_log('billing.updated', (int)$rid, null, "kanal=".($ch ?: 'auto').", kontonr=".$v['billing_kontonr'].", ansvarlig=".$v['payer_name']);
	$notes[] = vr_t('Faktureringsoplysninger gemt','Billing details saved');
}
// Fetch customer + invoices from the billing ledger. $search: billing kontonr if set, else CVR, else name.
function bill_fetch($search) {
	if (!function_exists('get_saldi_api_token')) return array('error' => 'API-funktioner mangler');
	$token = get_saldi_api_token(); if (!$token) return array('error' => vr_t('Kunne ikke logge ind på faktureringsregnskabets API','Could not log in to the billing ledger API'));
	$customers = fetch_saldi_api('/debitor/customers/index.php', $token, array('search' => $search, 'limit' => 1));
	if (!is_array($customers) || !count($customers) || !isset($customers[0]['kontonr'])) return array('error' => vr_t('Ingen kunde fundet i faktureringsregnskabet for','No billing customer found for').' "'.$search.'"');
	$c = $customers[0];
	$inv = fetch_saldi_api('/debitor/invoices/index.php', $token, array('customer' => $c['kontonr'], 'limit' => 100, 'page' => 1));
	if (!is_array($inv)) $inv = array();
	usort($inv, fn($a, $b) => strcmp($b['invoiceDate'] ?? '', $a['invoiceDate'] ?? ''));
	return array('customer' => $c, 'invoices' => $inv);
}
// Classify one invoice: paid | pending | overdue  (+ due date)
function bill_classify($inv, $defaultDays) {
	$paid = ($inv['paid'] == '1' || $inv['paid'] === true || $inv['paid'] === 't');
	$days = isset($inv['paymentInfo']['paymentDays']) && $inv['paymentInfo']['paymentDays'] !== null ? (int)$inv['paymentInfo']['paymentDays'] : (int)$defaultDays;
	$terms = $inv['paymentInfo']['paymentTerms'] ?? '';
	$due = null;
	if (!empty($inv['invoiceDate'])) { $base = strtotime($inv['invoiceDate']); if ($terms == 'Lb. md.' || $terms == 'Lb.md') $base = strtotime(date('Y-m-t', $base)); $due = date('Y-m-d', strtotime("+$days days", $base)); }
	$st = $paid ? 'paid' : (($due && $due < date('Y-m-d')) ? 'overdue' : 'pending');
	return array($st, $due, (float)($inv['economic']['sum'] ?? 0) + (float)($inv['economic']['vat'] ?? 0));
}
function bill_render($rid, $reg, $ent, $live, $data, $cfg) {
	global $sprog_id;
	$stTxt = array('paid' => vr_t('Betalt','Paid'), 'pending' => vr_t('Afventer','Pending'), 'overdue' => vr_t('Forfalden','Overdue'), 'failed' => vr_t('Fejlet','Failed'));
	$stDot = array('paid' => 'vr-ok', 'pending' => 'vr-warn', 'overdue' => 'vr-bad', 'failed' => 'vr-bad');
	$c = isset($data['customer']) ? $data['customer'] : null; $inv = isset($data['invoices']) ? $data['invoices'] : array();
	// channel
	$chTxt = array('pbs' => vr_t('Betalingsservice (PBS)','Direct debit (PBS)'), 'stripe' => vr_t('Kort / Stripe','Card / Stripe'), 'invoice' => vr_t('Faktura / bankoverførsel','Invoice / bank transfer'), 'other' => vr_t('Anden','Other'));
	$chDet = ''; $chSrc = '';
	if ($cfg['channel']) { $chDet = $cfg['channel']; $chSrc = vr_t('sat manuelt','set manually'); }
	elseif ($c) {
		if (!empty($c['stripe']['status']) && in_array($c['stripe']['status'], array('active','trialing','past_due','unpaid'))) { $chDet = 'stripe'; $chSrc = 'Stripe · '.$c['stripe']['status']; }
		elseif (!empty($c['pbs']['active'])) { $chDet = 'pbs'; $chSrc = vr_t('aftale','agreement').' '.($c['pbs']['number'] ?? ''); }
		elseif (($c['betaling']['betalingsbet'] ?? '') == 'Kreditkort') { $chDet = 'stripe'; $chSrc = vr_t('betalingsbetingelse Kreditkort','terms Kreditkort'); }
		else { $chDet = 'invoice'; $chSrc = vr_t('betalingsbetingelse','terms').' '.($c['betaling']['betalingsbet'] ?? '–').' '.(int)($c['betaling']['betalingsdage'] ?? 0).' '.vr_t('dage','days'); }
	}
	// invoices: status + totals
	$defaultDays = $c ? (int)($c['betaling']['betalingsdage'] ?? 0) : 0; $rows = array(); $open = 0; $openN = 0; $overdueN = 0; $oldest = null; $lastPaid = null; $lastInv = null; $overall = null;
	foreach ($inv as $i) { list($st, $due, $tot) = bill_classify($i, $defaultDays); if ($st != 'paid') { $open += $tot; $openN++; if ($st == 'overdue') { $overdueN++; if (!$oldest || $due < $oldest) $oldest = $due; } } elseif (!$lastPaid) $lastPaid = $i; if (!$lastInv) $lastInv = $i; $rows[] = array($i, $st, $due, $tot); }
	$stripeFailed = $c && !empty($c['stripe']['status']) && in_array($c['stripe']['status'], array('past_due','unpaid'));
	if ($stripeFailed) $overall = 'failed'; elseif ($overdueN) $overall = 'overdue'; elseif ($openN) $overall = 'pending'; elseif ($inv) $overall = 'paid';
	// next billing date (expected): Stripe period end is not exposed by the API, so derive from last invoice + plan interval
	$next = null; if ($lastInv && !empty($lastInv['invoiceDate'])) { $next = date('Y-m-d', strtotime(($ent['interval'] == 'year' ? '+1 year' : '+1 month'), strtotime($lastInv['invoiceDate']))); }
	$payerName = $cfg['payer_name'] ?: ($c ? trim(($c['kontakt'] ?? '') ?: trim(($c['fornavn'] ?? '').' '.($c['efternavn'] ?? ''))) : ''); $payerMail = $cfg['payer_email'] ?: ($c ? ($c['email'] ?? '') : '');

	print "<div class=\"vr-grid2\"><section class=\"vr-sect\"><h2>".vr_t('Betalingsstatus','Payment status')."</h2><div class=\"vr-card vr-kv\">";
	print "<div><span>".vr_t('Status','Status')."</span><b>".($overall ? "<span class=\"vr-st\"><span class=\"vr-dot ".$stDot[$overall]."\"></span>".$stTxt[$overall]."</span>" : '<span class="vr-mut2">'.vr_t('ingen fakturaer','no invoices').'</span>')."</b></div>";
	print "<div><span>".vr_t('Betalingskanal','Payment channel')."</span><b>".($chDet ? vr_h($chTxt[$chDet]).' <span class="vr-mut2">· '.vr_h($chSrc).'</span>' : '<span class="vr-mut2">'.vr_t('ukendt – sæt nedenfor','unknown – set below').'</span>')."</b></div>";
	print "<div><span>".vr_t('Udestående','Outstanding')."</span><b>".($openN ? number_format($open, 2, ',', '.')." DKK <span class=\"vr-mut2\">· $openN ".vr_t('fakturaer','invoices').($overdueN ? ", $overdueN ".vr_t('forfaldne, ældste','overdue, oldest')." ".vr_h($oldest) : '')."</span>" : '0,00 DKK')."</b></div>";
	print "<div><span>".vr_t('Seneste betaling','Latest payment')."</span><b>".($lastPaid ? number_format((float)($lastPaid['economic']['sum'] ?? 0) + (float)($lastPaid['economic']['vat'] ?? 0), 2, ',', '.')." DKK <span class=\"vr-mut2\">· ".vr_t('faktura','invoice')." #".vr_h($lastPaid['invoiceNo'] ?? $lastPaid['orderNo'] ?? '-')." · ".date('d-m-Y', strtotime($lastPaid['invoiceDate']))."</span>" : '–')."</b></div>";
	print "<div><span>".vr_t('Næste fakturering','Next invoice')."</span><b>".($next ? date('d-m-Y', strtotime($next)).' <span class="vr-mut2">· '.vr_t('forventet ud fra seneste faktura og','expected from latest invoice and').' '.($ent['interval'] == 'year' ? vr_t('årlig','yearly') : vr_t('månedlig','monthly')).' '.vr_t('fakturering','billing').'</span>' : '–')."</b></div>";
	print "<div><span>".vr_t('Pakke','Plan')."</span><b>".($ent['plan'] ? vr_h($ent['plan']['name']).($ent['price_ore'] !== null ? ' · '.ent_kr($ent['price_ore']).'/'.($ent['interval'] == 'year' ? vr_t('år','yr') : 'md.') : '') : '–')."</b></div>";
	if ($c && !empty($c['stripe']['status'])) print "<div><span>Stripe</span><b>".vr_h($c['stripe']['status'])." <span class=\"vr-mut2\">· ".vr_h($c['stripe']['subscriptionId'] ?? '')."</span></b></div>";
	print "</div>".(isset($data['error']) ? "<p class=\"vr-sub\" style=\"margin-top:10px;color:var(--warn-ink)\">".vr_h($data['error'])."</p>" : "")."</section>";
	print "<section class=\"vr-sect\"><h2>".vr_t('Betalingsansvarlig','Billing contact')."</h2><div class=\"vr-card vr-kv\">";
	print "<div><span>".vr_t('Navn','Name')."</span><b>".($payerName ? vr_h($payerName) : '–').($cfg['payer_name'] ? ' <span class="vr-pill">'.vr_t('manuelt','manual').'</span>' : '')."</b></div><div><span>E-mail</span><b>".($payerMail ? '<a href="mailto:'.vr_h($payerMail).'">'.vr_h($payerMail).'</a>' : '–')."</b></div>";
	print "<div><span>".vr_t('Faktureringskunde','Billing customer')."</span><b>".($c ? vr_h($c['firmanavn'] ?? '')." <span class=\"vr-mut2\">· ".vr_t('kundenr.','no.')." ".vr_h($c['kontonr'])." · CVR ".vr_h($c['cvrnr'] ?? '–')."</span>" : '<span class="vr-mut2">'.vr_t('ikke fundet','not found').'</span>')."</b></div>";
	print "<div><span>".vr_t('Betalingsbetingelser','Payment terms')."</span><b>".($c ? vr_h(($c['betaling']['betalingsbet'] ?? '–').' '.(int)($c['betaling']['betalingsdage'] ?? 0).' '.vr_t('dage','days')) : '–')."</b></div>";
	if ($c && !empty($c['pbs']['active'])) print "<div><span>Betalingsservice</span><b>".vr_t('Aktiv','Active')." <span class=\"vr-mut2\">· nr. ".vr_h($c['pbs']['number'] ?? '')."</span></b></div>";
	print "<div><span>".vr_t('Regnskabets e-mail','Account e-mail')."</span><b>".($reg['email'] ? vr_h($reg['email']) : '–')."</b></div></div>";
	// manual settings
	print "<h2 style=\"margin-top:18px\">".vr_t('Manuelle oplysninger','Manual details')." <small>".vr_t('bruges når API\'et ikke kan afgøre det','used when the API cannot tell')."</small></h2><div class=\"vr-card\"><form method=\"post\" class=\"vr-form\" style=\"max-width:none\"><input type=\"hidden\" name=\"bill_action\" value=\"save\">";
	print "<div class=\"vr-inline\" style=\"gap:14px;align-items:flex-end\"><label>".vr_t('Kundenr. i faktureringsregnskabet','Billing customer no.')."<input class=\"vr-inp\" name=\"billing_kontonr\" value=\"".vr_h($cfg['billing_kontonr'])."\" placeholder=\"".vr_t('ellers søges på CVR','else matched by CVR')."\" style=\"width:200px\"></label><label>".vr_t('Betalingskanal','Channel')."<select name=\"channel\" class=\"vr-sel\"><option value=\"\">".vr_t('Automatisk (fra API)','Automatic (from API)')."</option>"; foreach ($chTxt as $k => $t) print "<option value=\"$k\"".($cfg['channel'] == $k ? " selected" : "").">".vr_h($t)."</option>"; print "</select></label></div>";
	print "<div class=\"vr-inline\" style=\"gap:14px;align-items:flex-end\"><label>".vr_t('Betalingsansvarlig','Billing contact')."<input class=\"vr-inp\" name=\"payer_name\" value=\"".vr_h($cfg['payer_name'])."\" style=\"width:220px\"></label><label>E-mail<input class=\"vr-inp\" type=\"email\" name=\"payer_email\" value=\"".vr_h($cfg['payer_email'])."\" style=\"width:240px\"></label></div>";
	print "<label>".vr_t('Note','Note')."<input class=\"vr-inp\" name=\"note\" value=\"".vr_h($cfg['note'])."\"></label><div><button type=\"submit\" class=\"vr-btn vr-primary\">".findtekst('3|Gem', $sprog_id)."</button></div></form></div></section></div>";
	// history
	print "<section class=\"vr-sect\" style=\"margin-top:22px\"><h2>".vr_t('Faktura- og betalingshistorik','Invoice and payment history')." <small>".count($rows)." ".vr_t('fakturaer fra faktureringsregnskabet','invoices from the billing ledger')."</small></h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Faktura','Invoice')."</th><th>".vr_t('Dato','Date')."</th><th>".vr_t('Forfald','Due')."</th><th class=\"vr-r\">".vr_t('Beløb inkl. moms','Amount incl. VAT')."</th><th>".vr_t('Betingelser','Terms')."</th><th>".vr_t('Status','Status')."</th></tr></thead><tbody>";
	foreach ($rows as $n => $r) { list($i, $st, $due, $tot) = $r; print "<tr".($n >= 10 ? " class=\"vr-more\" hidden" : "")." onclick=\"openInvoice(".(int)$i['id'].")\" style=\"cursor:pointer\"><td class=\"vr-nm\"><a>#".vr_h($i['invoiceNo'] ?? $i['orderNo'] ?? '-')."</a></td><td class=\"vr-mut\">".(!empty($i['invoiceDate']) ? date('d-m-Y', strtotime($i['invoiceDate'])) : '–')."</td><td class=\"vr-mut\">".($due ? date('d-m-Y', strtotime($due)) : '–')."</td><td class=\"vr-r vr-num\">".number_format($tot, 2, ',', '.')."</td><td class=\"vr-mut\">".vr_h($i['paymentInfo']['paymentTerms'] ?? '')."</td><td><span class=\"vr-st\"><span class=\"vr-dot ".$stDot[$st]."\"></span>".$stTxt[$st]."</span></td></tr>"; }
	if (!$rows) print "<tr><td colspan=\"6\" class=\"vr-mut\" style=\"padding:20px\">".(isset($data['error']) ? vr_h($data['error']) : vr_t('Ingen fakturaer','No invoices'))."</td></tr>";
	print "</tbody></table>".(count($rows) > 10 ? "<div class=\"vr-foot\"><span class=\"vr-grow\"></span><button type=\"button\" class=\"vr-btn vr-quiet\" onclick=\"document.querySelectorAll('tr.vr-more').forEach(function(t){t.hidden=false});this.remove()\">".vr_t('Vis alle','Show all')." (".count($rows).")</button></div>" : "")."</div></section>";
	print "<div class=\"vr-scrim\" id=\"invScrim\" onclick=\"closeInvoice()\"></div><div class=\"vr-dlg\" id=\"invDlg\" style=\"width:720px;max-width:95vw\"><h3 id=\"invTitle\">…</h3><p id=\"invMeta\"></p><div id=\"invBody\"></div><div class=\"vr-bs\"><button type=\"button\" class=\"vr-btn\" onclick=\"closeInvoice()\">".vr_t('Luk','Close')."</button></div></div>\n";
}
}
?>
