<?php
// --- admin/inc_betalinger.php --- ver 5.1.0 --- 2026.10.29 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Area 3 "Betalinger og fakturering" on the customer card. Everything comes from the
//                  existing systems: the billing ledger on ssl3 through the REST API (customer: contact, e-mail,
//                  terms, Betalingsservice, Stripe status; invoices: date, terms, paid, sums) and the plan
//                  interval from the entitlement layer. Status per invoice: Betalt / Afventer / Forfalden; "Fejlet"
//                  when Stripe reports past_due or unpaid. The operator can store the billing customer number,
//                  channel and payer in regnskab_billing (master) when the API cannot tell or the match by CVR
//                  fails. Nothing here writes to the billing ledger.
// 20261012 CL/ASR Aligned with the payment model decided 12/10-2026 ("Saldi invoices, Stripe collects", invoice-mail
//                  spec D1/B1): the invoice is Saldi's own from the billing ledger, Stripe is a card on file that is
//                  charged per invoice, and Betalt = the invoice's betalt flag (PBS import, card charge or manual) –
//                  so the status here works unchanged. Channel 'stripe' = card on file; new channel 'stripe_sub' =
//                  the old Stripe subscriptions (production customers until migration M1), which get no Saldi
//                  invoices meanwhile and are shown as such instead of "no invoices". The billing customer number
//                  (regnskab_billing.billing_kontonr) is the coupling the customer's own invoice overview (billing
//                  overview spec F1) needs: it is stored automatically when the API lookup matches the ledger's CVR,
//                  or with one click from the lookup result.
// 20261029 CL/ASR regnskab_billing and its columns are created by the update chain (operatorPanelSchema.php).

if (!function_exists('bill_tables_ensure')) {
function bill_tables_ensure() {
	# 20261029 CL/ASR The tables come from the update chain (includes/opdat_func/operatorPanelSchema.php via betweenUpdates.php).
	return;
}
function bill_cache_ensure() {
	# 20261029 CL/ASR The tables come from the update chain (includes/opdat_func/operatorPanelSchema.php via betweenUpdates.php).
	return;
}
// Who is invoiced for this ledger: own ledger, or another ledger (parent) via the partner's consolidated billing or a per-ledger choice.
// Returns array(payer_regnskab_id|null, source text). null = the ledger itself.
function bill_payer($rid) {
	bill_cache_ensure(); partner_billing_cols_ensure(); $rid = (int)$rid;
	$own = db_fetch_array(db_select("select payer_regnskab_id from regnskab_billing where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__));
	if ($own && $own['payer_regnskab_id']) return array((int)$own['payer_regnskab_id'], vr_t('valgt for dette regnskab','set for this account'));
	$q = db_select("select p.* from regnskab_partners rp join partners p on p.id = rp.partner_id where rp.regnskab_id = '$rid' and rp.until is null and p.status = 'active' and p.billing_mode = 'consolidated' order by (rp.access = 'owner') desc, rp.id limit 1", __FILE__ . " linje " . __LINE__);
	if ($p = db_fetch_array($q)) { $pay = $p['billing_regnskab_id'] ? (int)$p['billing_regnskab_id'] : (int)$p['home_regnskab_id']; if ($pay && $pay != $rid) return array($pay, vr_t('samlet fakturering via','consolidated billing via').' '.$p['name']); }
	return array(null, '');
}
function bill_set_payer($rid, $payer) {
	bill_cache_ensure(); $rid = (int)$rid; $payer = (int)$payer;
	if (!db_fetch_array(db_select("select 1 from regnskab_billing where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__))) db_modify("insert into regnskab_billing (regnskab_id) values ('$rid')", __FILE__ . " linje " . __LINE__);
	db_modify("update regnskab_billing set payer_regnskab_id = ".($payer && $payer != $rid ? "'$payer'" : "NULL").", updated_by = '".db_escape_string(ent_actor())."', updated_at = now() where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__);
	partner_log('billing.payer_set', $rid, null, $payer && $payer != $rid ? "faktureres via regnskab $payer" : 'faktureres selv');
}
// Summarise fetched invoices and store the summary for the overview.
function bill_cache_store($rid, $data, $cfg, $search = '') {
	bill_cache_ensure(); if (isset($data['error'])) return;
	$c = $data['customer'];
	// coupling for the customer's own invoice overview (F1): keep the found customer number when the lookup was an exact CVR match
	if (empty($cfg['billing_kontonr']) && !empty($c['kontonr']) && preg_match('/^\d{8}$/', $sd = preg_replace('/\D/', '', (string)$search)) && preg_replace('/\D/', '', (string)($c['cvrnr'] ?? '')) === $sd) bill_set_kontonr($rid, (string)$c['kontonr'], "CVR-opslag $sd"); $defaultDays = (int)($c['betaling']['betalingsdage'] ?? 0); $open = 0; $openN = 0; $overdueN = 0; $oldest = null; $last = null;
	foreach ($data['invoices'] as $i) { list($st, $due, $tot) = bill_classify($i, $defaultDays); if (!$last && !empty($i['invoiceDate'])) $last = $i['invoiceDate']; if ($st != 'paid') { $open += $tot; $openN++; if ($st == 'overdue') { $overdueN++; if (!$oldest || $due < $oldest) $oldest = $due; } } }
	$failed = !empty($c['stripe']['status']) && (in_array($c['stripe']['status'], array('past_due','unpaid')) || (!empty($c['stripe']['lastFailedAt']) && (empty($c['stripe']['lastPaidAt']) || $c['stripe']['lastFailedAt'] > $c['stripe']['lastPaidAt'])));
	$status = $failed ? 'failed' : ($overdueN ? 'overdue' : ($openN ? 'pending' : ($data['invoices'] ? 'paid' : 'none')));
	if (!db_fetch_array(db_select("select 1 from regnskab_billing where regnskab_id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__))) db_modify("insert into regnskab_billing (regnskab_id) values ('".(int)$rid."')", __FILE__ . " linje " . __LINE__);
	db_modify("update regnskab_billing set pay_status = '$status', outstanding_ore = '".(int)round($open * 100)."', overdue_n = '$overdueN', oldest_due = ".($oldest ? "'$oldest'" : "NULL").", last_invoice = ".($last ? "'".db_escape_string(substr($last,0,10))."'" : "NULL").", checked_at = now() where regnskab_id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__);
}
function bill_get($rid) { bill_tables_ensure(); $r = db_fetch_array(db_select("select * from regnskab_billing where regnskab_id = '".(int)$rid."'", __FILE__ . " linje " . __LINE__)); return $r ? $r : array('billing_kontonr' => '', 'channel' => '', 'payer_name' => '', 'payer_email' => '', 'note' => ''); }
function bill_channels() { return array('pbs' => vr_t('Betalingsservice (PBS)','Direct debit (PBS)'), 'stripe' => vr_t('Kort på fil (Stripe)','Card on file (Stripe)'), 'stripe_sub' => vr_t('Stripe-abonnement (gammel model – flyttes ved M1)','Stripe subscription (old model – moved at M1)'), 'invoice' => vr_t('Faktura / bankoverførsel','Invoice / bank transfer'), 'other' => vr_t('Anden','Other')); }
// Store the billing customer number (coupling to the billing ledger's debtor) and log it.
function bill_set_kontonr($rid, $kontonr, $why) {
	bill_tables_ensure(); $rid = (int)$rid; $k = db_escape_string($kontonr);
	if (!db_fetch_array(db_select("select 1 from regnskab_billing where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__))) db_modify("insert into regnskab_billing (regnskab_id) values ('$rid')", __FILE__ . " linje " . __LINE__);
	db_modify("update regnskab_billing set billing_kontonr = '$k', updated_by = '".db_escape_string(ent_actor())."', updated_at = now() where regnskab_id = '$rid'", __FILE__ . " linje " . __LINE__);
	partner_log('billing.kontonr_set', $rid, null, "kundenr $kontonr – $why");
}
function bill_actions($rid, &$notes) {
	if (!isset($_POST['bill_action'])) return;
	bill_tables_ensure();
	if ($_POST['bill_action'] == 'use_kontonr') { # one click on the lookup result
		$k = trim((string)if_isset($_POST, '', 'kontonr'));
		if ($k !== '' && preg_match('/^[0-9A-Za-z._-]{1,30}$/', $k)) { bill_set_kontonr($rid, $k, vr_t('valgt af operatør fra API-opslag','chosen by the operator from the API lookup')); $notes[] = vr_t('Kundenr.','Customer no.')." $k ".vr_t('er gemt','saved'); }
		return;
	}
	if ($_POST['bill_action'] != 'save') return;
	$ch = if_isset($_POST, '', 'channel'); if (!isset(bill_channels()[$ch])) $ch = '';
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
	if (!is_array($inv)) return array('error' => vr_t('Kunne ikke hente fakturaer fra faktureringsregnskabet','Could not fetch invoices from the billing ledger')); # keep the cached summary
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
	$chTxt = bill_channels();
	$chDet = ''; $chSrc = '';
	if ($cfg['channel']) { $chDet = $cfg['channel']; $chSrc = vr_t('sat manuelt','set manually'); }
	elseif ($c) {
		if (!empty($c['stripe']['status']) && in_array($c['stripe']['status'], array('active','trialing','past_due','unpaid'))) { $chDet = !empty($c['stripe']['subscriptionId']) ? 'stripe_sub' : 'stripe'; $chSrc = 'Stripe · '.$c['stripe']['status']; }
		elseif (!empty($c['pbs']['active'])) { $chDet = 'pbs'; $chSrc = vr_t('aftale','agreement').' '.($c['pbs']['number'] ?? ''); }
		elseif (($c['betaling']['betalingsbet'] ?? '') == 'Kreditkort') { $chDet = 'stripe'; $chSrc = vr_t('betalingsbetingelse Kreditkort','terms Kreditkort'); }
		else { $chDet = 'invoice'; $chSrc = vr_t('betalingsbetingelse','terms').' '.($c['betaling']['betalingsbet'] ?? '–').' '.(int)($c['betaling']['betalingsdage'] ?? 0).' '.vr_t('dage','days'); }
	}
	// invoices: status + totals
	$defaultDays = $c ? (int)($c['betaling']['betalingsdage'] ?? 0) : 0; $rows = array(); $open = 0; $openN = 0; $overdueN = 0; $oldest = null; $lastPaid = null; $lastInv = null; $overall = null;
	foreach ($inv as $i) { list($st, $due, $tot) = bill_classify($i, $defaultDays); if ($st != 'paid') { $open += $tot; $openN++; if ($st == 'overdue') { $overdueN++; if (!$oldest || $due < $oldest) $oldest = $due; } } elseif (!$lastPaid) $lastPaid = $i; if (!$lastInv) $lastInv = $i; $rows[] = array($i, $st, $due, $tot); }
	$stripeFailed = $c && !empty($c['stripe']['status']) && (in_array($c['stripe']['status'], array('past_due','unpaid')) || (!empty($c['stripe']['lastFailedAt']) && (empty($c['stripe']['lastPaidAt']) || $c['stripe']['lastFailedAt'] > $c['stripe']['lastPaidAt'])));
	if ($stripeFailed) $overall = 'failed'; elseif ($overdueN) $overall = 'overdue'; elseif ($openN) $overall = 'pending'; elseif ($inv) $overall = 'paid';
	// next billing date (expected): Stripe period end is not exposed by the API, so derive from last invoice + plan interval
	$next = null; $nextSrc = '';
	if ($c && !empty($c['stripe']['currentPeriodEnd'])) { $next = substr($c['stripe']['currentPeriodEnd'], 0, 10); $nextSrc = vr_t('periodeslut fra Stripe','period end from Stripe'); }
	elseif ($lastInv && !empty($lastInv['invoiceDate'])) { $next = date('Y-m-d', strtotime(($ent['interval'] == 'year' ? '+1 year' : '+1 month'), strtotime($lastInv['invoiceDate']))); $nextSrc = vr_t('forventet ud fra seneste faktura og','expected from latest invoice and').' '.($ent['interval'] == 'year' ? vr_t('årlig','yearly') : vr_t('månedlig','monthly')).' '.vr_t('fakturering','billing'); }
	$payerName = $cfg['payer_name'] ?: ($c ? trim(($c['kontakt'] ?? '') ?: trim(($c['fornavn'] ?? '').' '.($c['efternavn'] ?? ''))) : ''); $payerMail = $cfg['payer_email'] ?: ($c ? ($c['email'] ?? '') : '');

	print "<div class=\"vr-grid2\"><section class=\"vr-sect\"><h2>".vr_t('Betalingsstatus','Payment status')."</h2><div class=\"vr-card vr-kv\">";
	$noInv = $chDet == 'stripe_sub' ? "<span class=\"vr-st\"><span class=\"vr-dot vr-ok\"></span>".vr_t('Stripe-abonnement – fornyes af Stripe','Stripe subscription – renewed by Stripe')."</span> <span class=\"vr-mut2\">· ".vr_t('ingen Saldi-fakturaer, indtil kunden flyttes til kort på fil (M1)','no Saldi invoices until the customer moves to card on file (M1)')."</span>" : '<span class="vr-mut2">'.vr_t('ingen fakturaer','no invoices').'</span>';
	print "<div><span>".vr_t('Status','Status')."</span><b>".($overall ? "<span class=\"vr-st\"><span class=\"vr-dot ".$stDot[$overall]."\"></span>".$stTxt[$overall]."</span>" : $noInv)."</b></div>";
	print "<div><span>".vr_t('Betalingskanal','Payment channel')."</span><b>".($chDet ? vr_h($chTxt[$chDet]).' <span class="vr-mut2">· '.vr_h($chSrc).'</span>' : '<span class="vr-mut2">'.vr_t('ukendt – sæt nedenfor','unknown – set below').'</span>')."</b></div>";
	print "<div><span>".vr_t('Udestående','Outstanding')."</span><b>".($openN ? number_format($open, 2, ',', '.')." DKK <span class=\"vr-mut2\">· $openN ".vr_t('fakturaer','invoices').($overdueN ? ", $overdueN ".vr_t('forfaldne, ældste','overdue, oldest')." ".vr_h($oldest) : '')."</span>" : '0,00 DKK')."</b></div>";
	print "<div><span>".vr_t('Seneste betaling','Latest payment')."</span><b>".($lastPaid ? number_format((float)($lastPaid['economic']['sum'] ?? 0) + (float)($lastPaid['economic']['vat'] ?? 0), 2, ',', '.')." DKK <span class=\"vr-mut2\">· ".vr_t('faktura','invoice')." #".vr_h($lastPaid['invoiceNo'] ?? $lastPaid['orderNo'] ?? '-')." · ".date('d-m-Y', strtotime($lastPaid['invoiceDate']))."</span>" : '–')."</b></div>";
	print "<div><span>".vr_t('Næste fakturering','Next invoice')."</span><b>".($next ? date('d-m-Y', strtotime($next)).' <span class="vr-mut2">· '.vr_h($nextSrc).'</span>' : '–')."</b></div>";
	print "<div><span>".vr_t('Pakke','Plan')."</span><b>".($ent['plan'] ? vr_h($ent['plan']['name']).($ent['price_ore'] !== null ? ' · '.ent_kr($ent['price_ore']).'/'.($ent['interval'] == 'year' ? vr_t('år','yr') : 'md.') : '') : '–')."</b></div>";
	if ($c && !empty($c['stripe']['status'])) print "<div><span>".(!empty($c['stripe']['subscriptionId']) ? vr_t('Stripe-abonnement','Stripe subscription') : vr_t('Kort (Stripe)','Card (Stripe)'))."</span><b>".vr_h($c['stripe']['status'])." <span class=\"vr-mut2\">· ".vr_h($c['stripe']['subscriptionId'] ?? '').(!empty($c['stripe']['lastPaidAt']) ? ' · '.vr_t('sidst betalt','last paid').' '.vr_h(substr($c['stripe']['lastPaidAt'],0,10)) : '')."</span>".(!empty($c['stripe']['lastFailedAt']) ? "<br><small style=\"color:var(--bad)\">".vr_t('Betaling fejlede','Payment failed')." ".vr_h(substr($c['stripe']['lastFailedAt'],0,10)).(!empty($c['stripe']['lastFailedInvoice']) ? ' · '.vr_t('faktura','invoice').' '.vr_h($c['stripe']['lastFailedInvoice']) : '').(!empty($c['stripe']['failedCount']) ? ' · '.(int)$c['stripe']['failedCount'].' '.vr_t('forsøg','attempts') : '')."</small>" : "")."</b></div>";
	print "</div>".(isset($data['error']) ? "<p class=\"vr-sub\" style=\"margin-top:10px;color:var(--warn-ink)\">".vr_h($data['error'])."</p>" : "")."<p class=\"vr-sub\" style=\"margin-top:10px\">".vr_t('Betalt = fakturaen er markeret betalt i faktureringsregnskabet (Betalingsservice, korttræk eller manuelt). Den præcise næste faktureringsdato er genfaktureringsordrens dato i faktureringsregnskabet; API\'et viser den ikke endnu.','Paid = the invoice is marked paid in the billing ledger (direct debit, card charge or manually). The exact next invoice date is the recurring order\'s date in the billing ledger; the API does not expose it yet.')."</p></section>";
	print "<section class=\"vr-sect\"><h2>".vr_t('Betalingsansvarlig','Billing contact')."</h2><div class=\"vr-card vr-kv\">";
	print "<div><span>".vr_t('Navn','Name')."</span><b>".($payerName ? vr_h($payerName) : '–').($cfg['payer_name'] ? ' <span class="vr-pill">'.vr_t('manuelt','manual').'</span>' : '')."</b></div><div><span>E-mail</span><b>".($payerMail ? '<a href="mailto:'.vr_h($payerMail).'">'.vr_h($payerMail).'</a>' : '–')."</b></div>";
	print "<div><span>".vr_t('Faktureringskunde','Billing customer')."</span><b>".($c ? vr_h($c['firmanavn'] ?? '')." <span class=\"vr-mut2\">· ".vr_t('kundenr.','no.')." ".vr_h($c['kontonr'])." · CVR ".vr_h($c['cvrnr'] ?? '–')."</span>".(!$cfg['billing_kontonr'] && !empty($c['kontonr']) ? " <form method=\"post\" style=\"display:inline\"><input type=\"hidden\" name=\"bill_action\" value=\"use_kontonr\"><input type=\"hidden\" name=\"kontonr\" value=\"".vr_h($c['kontonr'])."\"><button type=\"submit\" class=\"vr-link\" title=\"".vr_t('Gemmer kundenr. som fast kobling – bruges også af kundens egen fakturaoversigt','Saves the customer no. as the fixed link – also used by the customer\'s own invoice overview')."\">".vr_t('Gem som kundenr.','Save as customer no.')."</button></form>" : "") : '<span class="vr-mut2">'.vr_t('ikke fundet','not found').'</span>')."</b></div>";
	print "<div><span>".vr_t('Betalingsbetingelser','Payment terms')."</span><b>".($c ? vr_h(($c['betaling']['betalingsbet'] ?? '–').' '.(int)($c['betaling']['betalingsdage'] ?? 0).' '.vr_t('dage','days')) : '–')."</b></div>";
	if ($c && !empty($c['pbs']['active'])) print "<div><span>Betalingsservice</span><b>".vr_t('Aktiv','Active')." <span class=\"vr-mut2\">· nr. ".vr_h($c['pbs']['number'] ?? '')."</span></b></div>";
	list($payerId, $payerSrc) = bill_payer($rid); $payerName = ''; if ($payerId) { $pr = db_fetch_array(db_select("select regnskab from regnskab where id = '".(int)$payerId."'", __FILE__ . " linje " . __LINE__)); $payerName = $pr ? $pr['regnskab'] : "#$payerId"; }
	print "<div><span>".vr_t('Faktureres via','Invoiced via')."</span><b>".($payerId ? "<a href=\"operator_panel.php?regnskab_id=".(int)$payerId."&tab=betalinger\">".vr_h($payerName)."</a> <span class=\"vr-mut2\">· ".vr_h($payerSrc)."</span>" : vr_t('regnskabet selv','the account itself'))."</b></div>";
	print "<div><span>".vr_t('Regnskabets e-mail','Account e-mail')."</span><b>".($reg['email'] ? vr_h($reg['email']) : '–')."</b></div></div>";
	// manual settings
	print "<h2 style=\"margin-top:18px\">".vr_t('Manuelle oplysninger','Manual details')." <small>".vr_t('bruges når API\'et ikke kan afgøre det','used when the API cannot tell')."</small></h2><div class=\"vr-card\"><form method=\"post\" class=\"vr-form\" style=\"max-width:none\"><input type=\"hidden\" name=\"bill_action\" value=\"save\">";
	print "<div class=\"vr-inline\" style=\"gap:14px;align-items:flex-end\"><label>".vr_t('Kundenr. i faktureringsregnskabet','Billing customer no.')."<input class=\"vr-inp\" name=\"billing_kontonr\" value=\"".vr_h($cfg['billing_kontonr'])."\" placeholder=\"".vr_t('ellers søges på CVR','else matched by CVR')."\" style=\"width:200px\"></label><label>".vr_t('Betalingskanal','Channel')."<select name=\"channel\" class=\"vr-sel\"><option value=\"\">".vr_t('Automatisk (fra API)','Automatic (from API)')."</option>"; foreach ($chTxt as $k => $t) print "<option value=\"$k\"".($cfg['channel'] == $k ? " selected" : "").">".vr_h($t)."</option>"; print "</select></label></div>";
	print "<div class=\"vr-inline\" style=\"gap:14px;align-items:flex-end\"><label>".vr_t('Betalingsansvarlig','Billing contact')."<input class=\"vr-inp\" name=\"payer_name\" value=\"".vr_h($cfg['payer_name'])."\" style=\"width:220px\"></label><label>E-mail<input class=\"vr-inp\" type=\"email\" name=\"payer_email\" value=\"".vr_h($cfg['payer_email'])."\" style=\"width:240px\"></label></div>";
	print "<label>".vr_t('Note','Note')."<input class=\"vr-inp\" name=\"note\" value=\"".vr_h($cfg['note'])."\"></label><div><button type=\"submit\" class=\"vr-btn vr-primary\">".findtekst('3|Gem', $sprog_id)."</button></div></form></div></section></div>";
	// history
	print "<section class=\"vr-sect\" style=\"margin-top:22px\"><h2>".vr_t('Faktura- og betalingshistorik','Invoice and payment history')." <small>".count($rows)." ".vr_t('fakturaer fra faktureringsregnskabet','invoices from the billing ledger')."</small></h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Faktura','Invoice')."</th><th>".vr_t('Dato','Date')."</th><th>".vr_t('Forfald','Due')."</th><th class=\"vr-r\">".vr_t('Beløb inkl. moms','Amount incl. VAT')."</th><th>".vr_t('Betingelser','Terms')."</th><th>".vr_t('Status','Status')."</th></tr></thead><tbody>";
	foreach ($rows as $n => $r) { list($i, $st, $due, $tot) = $r; print "<tr".($n >= 10 ? " class=\"vr-more\" hidden" : "")." onclick=\"openInvoice(".(int)$i['id'].")\" style=\"cursor:pointer\"><td class=\"vr-nm\"><a>#".vr_h($i['invoiceNo'] ?? $i['orderNo'] ?? '-')."</a></td><td class=\"vr-mut\">".(!empty($i['invoiceDate']) ? date('d-m-Y', strtotime($i['invoiceDate'])) : '–')."</td><td class=\"vr-mut\">".($due ? date('d-m-Y', strtotime($due)) : '–')."</td><td class=\"vr-r vr-num\">".number_format($tot, 2, ',', '.')."</td><td class=\"vr-mut\">".vr_h($i['paymentInfo']['paymentTerms'] ?? '')."</td><td><span class=\"vr-st\"><span class=\"vr-dot ".$stDot[$st]."\"></span>".$stTxt[$st]."</span></td></tr>"; }
	if (!$rows) print "<tr><td colspan=\"6\" class=\"vr-mut\" style=\"padding:20px\">".(isset($data['error']) ? vr_h($data['error']) : ($chDet == 'stripe_sub' ? vr_t('Ingen Saldi-fakturaer – kunden betaler via Stripe-abonnement (gammel model) og flyttes til kort på fil ved M1.','No Saldi invoices – the customer pays through a Stripe subscription (old model) and moves to card on file at M1.') : vr_t('Ingen fakturaer','No invoices')))."</td></tr>";
	print "</tbody></table>".(count($rows) > 10 ? "<div class=\"vr-foot\"><span class=\"vr-grow\"></span><button type=\"button\" class=\"vr-btn vr-quiet\" onclick=\"document.querySelectorAll('tr.vr-more').forEach(function(t){t.hidden=false});this.remove()\">".vr_t('Vis alle','Show all')." (".count($rows).")</button></div>" : "")."</div></section>";
	print "<div class=\"vr-scrim\" id=\"invScrim\" onclick=\"closeInvoice()\"></div><div class=\"vr-dlg\" id=\"invDlg\" style=\"width:720px;max-width:95vw\"><h3 id=\"invTitle\">…</h3><p id=\"invMeta\"></p><div id=\"invBody\"></div><div class=\"vr-bs\"><button type=\"button\" class=\"vr-btn\" onclick=\"closeInvoice()\">".vr_t('Luk','Close')."</button></div></div>\n";
}
}
?>
