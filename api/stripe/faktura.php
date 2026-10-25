<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/faktura.php --- 2026.10.22
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261019 CL/ASR Created: the public invoice & payment page (Requirements par. 5; design
//                 design_betalingsside_desktop.png; prototype prototype_betalingsside.html).
//                 Left: the invoice rendered from order data. Right: the payment panel per
//                 state (open / open_recurring / enrolled / pending / paid / paidbank /
//                 overdue / failed / nocard / credited) with Stripe's Payment Element
//                 (deferred intent: the PaymentIntent is created on submit by faktura_pay.php).
//                 No card data touches Saldi. Public, signed link, no session.
// 20261022 CDX/LH Gate save-card controls and defaults with the enrolment switch.
// 20261022 CDX/LH Render manual amount, bank and contact panel without card controls.
// 20261022 CDX/LH Resume authentication on the existing PaymentIntent and keep replacement-card usage in sync with Elements.
// 20261022 CDX/LH Offer enrolled saved-card payment only on or after the due date.
// 20261022 CL/LH S5 Danish decline sentences, accurate consent text (removal via contact), charge date, Saldi hint only for Danosoft's ledger.
// 20261022 CDX/LH Wait for Payment Element readiness and usage updates; prevent concurrent submits and keep consent date accurate.
// 20261022 CDX/LH Enforce card payment methods in Elements and remove the unavailable Saldi menu hint.
// 20261022 CDX/LH Place whole-row, keyboard-accessible consent above the resizing Payment Element.
// 20261025 CL/ASR Left: the invoice exactly as its PDF (page images via faktura_side.php); the HTML
//                 summary is only shown if the images cannot be made. Stripe Link is hidden in the
//                 Payment Element (wallets.link = never): card only, shorter panel.

include(__DIR__ . '/faktura_common.php');

$state = $inv['state'];
$o = $inv['o']; $f = $inv['firma']; $d = $inv['debtor'];
$brand = $inv['brand'];
$pk = (string)stripe_setting('publishable_key');
$returned = isset($_GET['redirect_status']) || isset($_GET['payment_intent']);
if ($returned && in_array($state, array('open', 'open_recurring', 'overdue', 'failed', 'enrolled', 'requires_action', 'pending')) && !empty($_GET['payment_intent'])) {
	// browser came back from Stripe before the webhook: sync once (only an intent of THIS invoice), then render
	$piq = db_fetch_array(db_select("select payment_intent_id from stripe_payments where ordre_id = " . (int)$faktura_o . " and payment_intent_id = '" . db_escape_string(substr((string)$_GET['payment_intent'], 0, 255)) . "'", __FILE__ . " linje " . __LINE__));
	if ($piq) stripe_payment_sync((string)$piq['payment_intent_id']);
	$inv = stripe_invoice_load($faktura_o); $state = $inv['state'];
}
$totalKr = stripe_fmt_kr($inv['total_ore']) . ($inv['currency'] === 'DKK' ? ' kr.' : ' ' . $inv['currency']);
$dueFmt = stripe_fmt_date($inv['due']);
$nr = $inv['nr'];
$docName = $inv['is_credit'] ? stripe_txt('creditnote') : stripe_txt('invoice');
$self = stripe_invoice_url($inv['id']);
$pdfUrl = str_replace('/faktura.php?', '/faktura_pdf.php?', $self);
$pageUrl = str_replace('/faktura.php?', '/faktura_side.php?', $self);
$pageImgs = stripe_invoice_page_images($inv['id'], true);
$contactMail = trim((string)($f['email'] ?? '')); $contactPhone = trim((string)($f['tlf'] ?? ''));
$bankReg = trim((string)($f['bank_reg'] ?? '')); $bankKonto = trim((string)($f['bank_konto'] ?? ''));
$hasBank = ($bankReg !== '' && $bankKonto !== '');
$vatPct = rtrim(rtrim(number_format((float)$o['momssats'], 2, ',', ''), '0'), ',');
$h = 'stripe_h';
$contact = stripe_contact_phrase($inv);

function fak_pill($inv, $state) {
	if (in_array($state, array('paid', 'paidbank'))) { $when = $inv['paid'] ? substr((string)$inv['paid']['succeeded_at'], 0, 10) : ''; return '<span class="pill good">' . stripe_h(stripe_txt('paid_pill', array('date' => $when ? stripe_fmt_date($when) : ''))) . '</span>'; }
	if ($state === 'credited') return '<span class="pill grey">' . stripe_h(stripe_txt('credited_pill', array('date' => stripe_fmt_date($inv['o']['fakturadate'])))) . '</span>';
	if (in_array($state, array('overdue', 'failed')) && $inv['due'] < date('Y-m-d')) return '<span class="pill bad">' . stripe_h(stripe_txt('overdue_pill', array('date' => stripe_fmt_date($inv['due'])))) . '</span>';
	return '<span class="pill">' . stripe_h(stripe_txt('due_pill', array('date' => stripe_fmt_date($inv['due'])))) . '</span>';
}
function fak_status($kind, $icon, $title, $sub) {
	$icons = array('ok' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>',
		'x' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
		'clock' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
		'spin' => '<span class="spin"></span>');
	return '<div class="status ' . $kind . '"><div class="ic">' . $icons[$icon] . '</div><div><b>' . stripe_h($title) . '</b>' . ($sub !== '' ? '<span>' . stripe_h($sub) . '</span>' : '') . '</div></div>';
}
$amountBox = '<div class="amt"><div class="l"><span>' . $h(stripe_txt('to_pay')) . '</span><span>' . $h(stripe_txt('due_pill', array('date' => $dueFmt))) . '</span></div><b class="num">' . $h($totalKr) . '</b></div>';
$trust = '<div class="trust"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg><div>' . $h(stripe_txt('trust', array('brand' => $brand, 'email' => $inv['email']))) . '</div></div>';
$foot = '';
if ($contactMail !== '' || $contactPhone !== '') $foot = '<div class="foot">' . $h(stripe_txt('questions', array('email' => '', 'phone' => ''))) . '</div>';
if ($contactMail !== '') $foot = '<div class="foot">' . str_replace(array('%email', '%phone'), array('<a href="mailto:' . $h($contactMail) . '">' . $h($contactMail) . '</a>', $contactPhone !== '' ? ' eller ' . $h($contactPhone) : ''), $h(stripe_txt('questions'))) . '</div>';
$bankBox = $hasBank ? '<div class="bank"><b>' . $h(stripe_txt('bank_box_title')) . '</b>Reg. ' . $h($bankReg) . ' · konto ' . $h($bankKonto) . '<br>' . $h(stripe_txt('bank_box_ref', array('nr' => $nr))) . '<br><span style="color:var(--ink-3)">' . $h(stripe_txt('bank_box_note')) . '</span></div>' : '';
$elementBlock = '<div id="payment-element" class="pe"></div><div id="pay-error" class="perr" role="alert"></div>';

$panel = '';
switch ($state) {
	case 'open': case 'overdue':
		$panel .= '<div class="lab">' . $h(stripe_txt('lab_payment')) . '</div><h2>' . $h(stripe_txt($state === 'overdue' ? 'h_overdue' : 'h_open')) . '</h2><p class="lead">' . $h($state === 'overdue' ? stripe_txt('lead_overdue', array('date' => $dueFmt)) : stripe_txt('lead_open')) . '</p>';
		if ($state === 'overdue') $panel .= fak_status('bad', 'clock', stripe_txt('overdue_pill', array('date' => $dueFmt)), stripe_txt('amount_lbl') . ' ' . $totalKr);
		else $panel .= $amountBox;
		$panel .= '<form id="pay-form">' . $elementBlock . '<button class="btn primary" id="pay" type="submit" disabled>' . $h(stripe_txt('pay_btn', array('amount' => $totalKr))) . '</button></form>' . $trust . ($hasBank ? '<div style="margin-top:18px">' . $bankBox . '</div>' : '') . $foot;
		break;
	case 'open_recurring':
		$panel .= '<div class="lab">' . $h(stripe_txt('lab_payment')) . '</div><h2>' . $h(stripe_txt('h_recurring')) . '</h2><p class="lead">' . $h(stripe_txt('lead_recurring', array('brand' => $brand))) . '</p>' . $amountBox;
		$panel .= '<form id="pay-form"><div class="check" role="checkbox" aria-checked="false" tabindex="0" id="chk" aria-labelledby="consent-title" aria-describedby="consent-text"><div class="box" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg></div><div><b id="consent-title">' . $h(stripe_txt('chk_title')) . '</b><span id="consent-text">' . $h(stripe_invoice_consent($inv)) . '</span></div></div>' . $elementBlock . '<button class="btn primary" id="pay" type="submit" disabled>' . $h(stripe_txt('pay_btn', array('amount' => $totalKr))) . '</button></form>' . $trust . ($hasBank ? '<div style="margin-top:18px">' . $bankBox . '</div>' : '') . $foot;
		break;
	case 'failed':
		$fp = $inv['failed']; $card = stripe_card_label($fp['pm_brand'], $fp['pm_last4']);
		$savedCardFailed = in_array($fp['source'], array('auto', 'backup', 'manual'), true);
		$panel .= '<div class="lab">' . $h(stripe_txt('lab_payment')) . '</div><h2>' . $h(stripe_txt('h_failed')) . '</h2><p class="lead">' . $h(stripe_txt($savedCardFailed ? 'lead_failed' : 'lead_failed_page')) . '</p>';
		// Never show Stripe's raw code to the customer; stripe_decline_text() maps it to a Danish sentence.
		$panel .= fak_status('bad', 'x', stripe_txt('failed_box', array('card' => $card, 'date' => stripe_fmt_date(stripe_attempt_date($fp)))), stripe_decline_text($fp['last_error']));
		$panel .= $amountBox . '<form id="pay-form">';
		if (stripe_feature_on('enrolment') && ($inv['enrolled'] || $inv['recurring'])) $panel .= '<div class="check" role="checkbox" aria-checked="true" tabindex="0" id="chk" aria-labelledby="consent-title" aria-describedby="consent-text"><div class="box" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg></div><div><b id="consent-title">' . $h($inv['enrolled'] ? stripe_txt('chk_replace_title') : stripe_txt('chk_title')) . '</b><span id="consent-text">' . $h($inv['enrolled'] ? stripe_txt('chk_replace_sub', array('card' => $card)) : stripe_invoice_consent($inv)) . '</span></div></div>';
		$panel .= $elementBlock . '<button class="btn primary" id="pay" type="submit" disabled>' . $h(stripe_txt('pay_btn', array('amount' => $totalKr))) . '</button></form>' . $trust . $foot;
		break;
	case 'enrolled':
		$card = stripe_card_label('', '');
		if (!empty($d['stripe_pm_id'])) { $r = stripeHttpRequest('GET', '/v1/payment_methods/' . rawurlencode($d['stripe_pm_id'])); if ($r['ok'] && !empty($r['body']['card'])) $card = stripe_card_label($r['body']['card']['brand'], $r['body']['card']['last4']) . ' <span class="grow"></span><span style="color:var(--ink-3)">Udløber ' . (int)$r['body']['card']['exp_month'] . '/' . (int)$r['body']['card']['exp_year'] . '</span>'; }
		$panel .= '<div class="lab">' . $h(stripe_txt('lab_payment')) . '</div><h2>' . $h(stripe_txt('h_enrolled')) . '</h2><p class="lead">' . $h(stripe_txt('lead_enrolled')) . '</p>';
		$chargeDate = stripe_invoice_charge_date($inv);
		$panel .= fak_status('grey', 'clock', stripe_txt('enrolled_on', array('date' => stripe_fmt_date($chargeDate !== '' ? $chargeDate : $inv['due']))), stripe_txt('amount_lbl') . ' ' . $totalKr);
		$panel .= '<div class="card-row"><span class="brand">' . $h(strtoupper(substr((string)($r['body']['card']['brand'] ?? 'kort'), 0, 4))) . '</span><span>' . $card . '</span></div>';
		if (stripe_invoice_pay_now_allowed($inv)) {
			$panel .= '<button class="btn primary" id="paynow" type="button">' . $h(stripe_txt('pay_now_btn', array('amount' => $totalKr))) . '</button><div id="pay-error" class="perr" role="alert"></div>';
		}
		$panel .= '<p style="margin-top:14px;color:var(--ink-2);font-size:14px">' . $h(stripe_txt('manage_hint', array('kontakt' => $contact))) . '</p>' . $trust . $foot;
		break;
	case 'requires_action':
		$panel .= '<div class="lab">' . $h(stripe_txt('lab_payment')) . '</div><h2>' . $h(stripe_txt('h_action')) . '</h2><p class="lead">' . $h(stripe_txt('lead_action')) . '</p>' . $amountBox;
		$panel .= '<button class="btn primary" id="resume" type="button">' . $h(stripe_txt('action_btn')) . '</button><div id="pay-error" class="perr" role="alert"></div>' . $foot;
		break;
	case 'pending':
		$panel .= '<div class="lab">' . $h(stripe_txt('lab_payment')) . '</div><h2>' . $h(stripe_txt('h_pending')) . '</h2><p class="lead">' . $h(stripe_txt('lead_pending')) . '</p>';
		$panel .= fak_status('warn', 'spin', stripe_txt('pending_box'), stripe_txt('pending_sub'));
		$panel .= '<div class="amt"><div class="l"><span>' . $h(stripe_txt('amount_lbl')) . '</span></div><b class="num">' . $h($totalKr) . '</b></div>' . $foot;
		break;
	case 'paid':
		$p = $inv['paid']; $card = stripe_card_label($p['pm_brand'], $p['pm_last4']);
		$panel .= '<div class="lab">' . $h(stripe_txt('lab_payment')) . '</div><h2>' . $h(stripe_txt('h_paid')) . '</h2><p class="lead">' . $h(stripe_txt('lead_paid', array('email' => $inv['email']))) . '</p>';
		$panel .= fak_status('good', 'ok', stripe_txt('paid_box', array('date' => stripe_fmt_date_long(substr((string)$p['succeeded_at'], 0, 10)))), $totalKr . ' · ' . $card);
		if (stripe_bool($p['save_card'])) $panel .= '<div class="card-row"><span class="brand">' . $h(strtoupper(substr((string)$p['pm_brand'], 0, 4))) . '</span><span>' . $h(stripe_txt('card_saved_row')) . '</span></div>';
		$panel .= $foot;
		break;
	case 'paidbank':
		$panel .= '<div class="lab">' . $h(stripe_txt('lab_payment')) . '</div><h2>' . $h(stripe_txt('h_paidbank')) . '</h2><p class="lead">' . $h(stripe_txt('lead_paidbank')) . '</p>';
		$panel .= fak_status('good', 'ok', stripe_txt('paid_pill', array('date' => '')), $totalKr . ' · ' . stripe_txt('bank_transfer')) . $foot;
		break;
	case 'manual':
		$panel .= '<div class="lab">' . $h(stripe_txt('lab_payment')) . '</div><h2>' . $h(stripe_txt('h_manual')) . '</h2><p class="lead">' . $h(stripe_txt('lead_manual')) . '</p>' . $amountBox . $bankBox . $foot;
		break;
	case 'nocard':
		$panel .= '<div class="lab">' . $h(stripe_txt('lab_payment')) . '</div><h2>' . $h(stripe_txt('h_nocard')) . '</h2><p class="lead">' . $h(stripe_txt('lead_nocard')) . '</p>' . $amountBox . $bankBox . $foot;
		break;
	case 'credited':
		$panel .= '<div class="lab">' . $h(stripe_txt('creditnote')) . '</div><h2>' . $h(stripe_txt('h_credited')) . '</h2><p class="lead">' . $h(stripe_txt('lead_credited')) . '</p>';
		$panel .= fak_status('grey', 'ok', stripe_txt('credited_pill', array('date' => stripe_fmt_date_long(substr((string)$o['fakturadate'], 0, 10)))), $totalKr) . $foot;
		break;
}

// ---- invoice (left) ----
$linesHtml = '';
foreach ($inv['lines'] as $l) {
	if ($l['is_text']) { $linesHtml .= '<tr><td colspan="4" style="color:var(--ink-2)">' . $h($l['tekst']) . '</td></tr>'; continue; }
	$linesHtml .= '<tr><td>' . $h($l['tekst']) . ($l['varenr'] !== '' ? '<small>' . $h($l['varenr']) . ($l['rabat'] > 0 ? ' · rabat ' . $h(rtrim(rtrim(number_format($l['rabat'], 2, ',', ''), '0'), ',')) . ' %' : '') . '</small>' : '') . '</td>'
		. '<td class="r num">' . $h(rtrim(rtrim(number_format($l['antal'], 2, ',', '.'), '0'), ',')) . '</td><td class="r num">' . $h(number_format($l['pris'], 2, ',', '.')) . '</td><td class="r num">' . $h(number_format($l['sum'], 2, ',', '.')) . '</td></tr>';
}
$cfg = array('pk' => $pk, 'o' => $inv['id'], 's' => $faktura_s, 'state' => $state, 'amount' => $inv['total_ore'], 'currency' => strtolower($inv['currency']),
	'payUrl' => str_replace('/faktura.php?', '/faktura_pay.php?', $self), 'stateUrl' => str_replace('/faktura.php?', '/faktura_state.php?', $self), 'returnUrl' => $self,
	'actionError' => stripe_txt('action_unavailable'), 'payError' => stripe_txt('pay_failed'), 'startError' => stripe_txt('pay_start_failed'), 'netError' => stripe_txt('network_error'),
	'btn' => stripe_txt('pay_btn', array('amount' => $totalKr)), 'btnSave' => stripe_txt('pay_save_btn', array('amount' => $totalKr)), 'saveDefault' => ($state === 'failed' && stripe_invoice_save_allowed($inv)));

while (ob_get_level()) ob_end_clean();
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="da">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $h(stripe_txt('page_title', array('nr' => $nr, 'firma' => $f['firmanavn'] ?? ''))) ?></title>
<style>
:root{--bg:#f7f5f0;--surface:#fff;--ink:#111318;--ink-2:#4b5563;--ink-3:#6b7280;--line:#e6e2da;--line-2:#efece6;--label:#9a5b1a;--soft:#f3eee6;--tint:#f1eefb;--tint-line:#e2dbf6;--blue:#1b68bd;--purple:#8b33d4;--purple-pill:#efe6fb;--purple-ink:#5b21a6;--good:#15803d;--good-bg:#e9f7ee;--bad:#c2410c;--bad-bg:#fdeee8;--warn:#a15c07;--warn-bg:#fdf3e2;--sans:Inter,"Segoe UI",system-ui,sans-serif;--r:16px;--shadow:0 1px 2px rgba(17,19,24,.04),0 8px 24px rgba(17,19,24,.06)}
*{box-sizing:border-box;margin:0;padding:0}html{background:var(--bg)}body{font:15px/1.55 var(--sans);color:var(--ink);-webkit-font-smoothing:antialiased;min-height:100vh}
h1,h2{letter-spacing:-.01em}a{color:var(--blue)}.num{font-variant-numeric:tabular-nums}
.top{display:flex;align-items:center;gap:16px;height:72px;padding:0 32px;background:var(--surface);border-bottom:1px solid var(--line)}.top .logo{font-weight:700;font-size:26px;letter-spacing:-.02em}.top .sep{width:1px;height:28px;background:var(--line)}.top .ttl{font-size:17px;font-weight:500}.top .ttl span{color:var(--ink-3);font-size:14px;margin-left:10px;font-weight:400}.top .grow{flex:1}
.btn{height:44px;display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:0 18px;border-radius:10px;border:1px solid var(--line);background:var(--surface);font:500 15px var(--sans);color:var(--ink);cursor:pointer;text-decoration:none;white-space:nowrap}.btn:hover{background:var(--soft)}
.btn.primary{border:0;background:linear-gradient(90deg,var(--blue),var(--purple));color:#fff;font-weight:600;height:52px;width:100%;font-size:16px;box-shadow:0 8px 20px rgba(139,51,212,.18);margin-top:8px}.btn.primary:hover{filter:brightness(1.06)}.btn.primary[disabled]{opacity:.55;cursor:default;filter:none}
.wrap{display:grid;grid-template-columns:minmax(0,1fr) 440px;min-height:calc(100vh - 72px)}.left{padding:48px 56px 64px;display:flex;justify-content:center}.right{background:var(--surface);border-left:1px solid var(--line);padding:48px 40px;min-width:0}
@media(max-width:980px){.wrap{grid-template-columns:1fr}.right{border-left:0;border-top:1px solid var(--line);order:-1;padding:28px 20px}.left{padding:24px 16px 48px}.top{padding:0 16px}.top .ttl span{display:none}}
.pages{width:100%;max-width:820px;display:flex;flex-direction:column;gap:24px}.pages img{display:block;width:100%;height:auto;background:#fff;border:1px solid var(--line);border-radius:4px;box-shadow:var(--shadow)}
.inv{width:100%;max-width:820px;background:var(--surface);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--shadow);padding:56px 60px}@media(max-width:980px){.inv{padding:28px 22px}}
.inv .head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:40px}.inv .from b{display:block;font-size:20px;font-weight:600;margin-bottom:6px}.inv .from span{display:block;color:var(--ink-2);font-size:14.5px}.inv .title{text-align:right}.inv .title h1{font-size:44px;font-weight:700;line-height:1;margin-bottom:12px}
.pill{display:inline-block;padding:6px 14px;border-radius:99px;font-size:13.5px;font-weight:600;background:var(--purple-pill);color:var(--purple-ink)}.pill.bad{background:var(--bad-bg);color:var(--bad)}.pill.good{background:var(--good-bg);color:var(--good)}.pill.grey{background:var(--soft);color:var(--ink-2)}
.inv .meta{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-bottom:40px}@media(max-width:600px){.inv .meta{grid-template-columns:1fr;gap:20px}}
.lab{font-size:11.5px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3)}.inv .to .lab{margin-bottom:10px}.inv .to b{display:block;font-weight:600;margin-bottom:2px}.inv .to span{display:block;color:var(--ink-2);font-size:14.5px}
.kv{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--line-2);font-size:14.5px}.kv span{color:var(--ink-2)}.kv b{font-weight:500}
table.lines{width:100%;border-collapse:collapse;margin-bottom:28px}table.lines th{font-size:11.5px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);text-align:left;padding:0 0 12px;border-bottom:1px solid var(--ink)}table.lines td{padding:16px 0;border-bottom:1px solid var(--line-2);font-size:15px;vertical-align:top}table.lines th.r,table.lines td.r{text-align:right;white-space:nowrap;padding-left:12px}table.lines td small{display:block;color:var(--ink-3);font-size:13px;margin-top:2px}
.totals{margin-left:auto;width:min(100%,360px)}.totals .kv{border:0;padding:6px 0}.totals .sum{border-top:2px solid var(--ink);margin-top:8px;padding-top:14px;display:flex;justify-content:space-between;align-items:baseline}.totals .sum b{font-size:30px;font-weight:700}
.inv .terms{margin-top:40px;padding-top:24px;border-top:1px solid var(--line-2);color:var(--ink-2);font-size:14.5px}.inv .terms b{color:var(--ink);font-weight:600}.inv .terms p+p{margin-top:8px}
.right .lab{color:var(--label);margin-bottom:14px}.right h2{font-size:30px;line-height:1.15;font-weight:700;margin-bottom:12px}.right .lead{color:var(--ink-2);font-size:15px;margin-bottom:24px}
.amt{display:flex;justify-content:space-between;align-items:center;gap:16px;background:var(--soft);border-radius:12px;padding:20px 22px;margin-bottom:22px}.amt .l span{display:block;color:var(--ink-2);font-size:14px}.amt .l span+span{margin-top:6px}.amt b{font-size:34px;font-weight:700;white-space:nowrap}
.pe{margin:4px 0 8px;min-height:40px}.perr{color:var(--bad);font-size:14px;min-height:0;margin:6px 0}
.check{display:flex;gap:14px;background:var(--tint);border:1px solid var(--tint-line);border-radius:12px;padding:16px 18px;margin:12px 0 20px;cursor:pointer}.check:focus-visible{outline:3px solid var(--purple);outline-offset:3px}.check .box{width:24px;height:24px;border-radius:6px;border:2px solid var(--purple);display:grid;place-items:center;flex:none;background:var(--surface);cursor:pointer;margin-top:1px}.check[aria-checked="true"] .box{background:var(--purple);border-color:var(--purple)}.check .box svg{display:none;color:#fff}.check[aria-checked="true"] .box svg{display:block}.check b{display:block;font-weight:600;margin-bottom:4px}.check span{display:block;color:var(--ink-2);font-size:14px}
.trust{display:flex;gap:12px;margin-top:18px;color:var(--ink-2);font-size:14px;line-height:1.5}.trust svg{flex:none;margin-top:3px;color:var(--ink-3)}
.foot{margin-top:40px;padding-top:24px;border-top:1px solid var(--line);color:var(--ink-2);font-size:14px}
.status{display:flex;gap:14px;border-radius:12px;padding:18px 20px;margin-bottom:22px;align-items:flex-start}.status.good{background:var(--good-bg)}.status.bad{background:var(--bad-bg)}.status.warn{background:var(--warn-bg)}.status.grey{background:var(--soft)}.status .ic{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;flex:none;background:var(--surface)}.status.good .ic{color:var(--good)}.status.bad .ic{color:var(--bad)}.status.warn .ic{color:var(--warn)}.status.grey .ic{color:var(--ink-3)}.status b{display:block;font-weight:600;margin-bottom:3px}.status span{display:block;color:var(--ink-2);font-size:14px}
.card-row{display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:14.5px}.card-row .brand{font:700 11px var(--sans);letter-spacing:.06em;background:var(--soft);border:1px solid var(--line);border-radius:6px;padding:4px 7px}.card-row .grow{flex:1}
.bank{background:var(--soft);border-radius:12px;padding:18px 20px;font-size:14.5px;line-height:1.6}.bank b{display:block;font-weight:600;margin-bottom:6px}
.spin{width:18px;height:18px;border:2px solid var(--line);border-top-color:var(--ink-3);border-radius:50%;animation:sp 1s linear infinite;display:inline-block}@keyframes sp{to{transform:rotate(360deg)}}
</style>
<?php if ($pk !== '' && in_array($state, array('open', 'open_recurring', 'overdue', 'failed', 'enrolled', 'requires_action'))): ?><script src="https://js.stripe.com/v3/"></script><?php endif; ?>
</head>
<body>
<header class="top">
  <div class="logo"><?= $h($brand) ?></div>
  <div class="sep"></div>
  <div class="ttl"><?= $h($docName . ' ' . $nr) ?> <span>fra <?= $h($f['firmanavn'] ?? '') ?></span></div>
  <div class="grow"></div>
  <a class="btn" href="<?= $h($pdfUrl) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M4 19h16"/></svg><?= $h(stripe_txt('download_pdf')) ?></a>
</header>
<div class="wrap">
  <main class="left">
    <?php if ($pageImgs): ?>
    <div class="pages">
      <?php foreach ($pageImgs as $i => $png): $dim = @getimagesize($png); ?>
      <img src="<?= $h($pageUrl . '&p=' . ($i + 1)) ?>" alt="<?= $h($docName . ' ' . $nr . ', side ' . ($i + 1) . ' af ' . count($pageImgs)) ?>"<?= $dim ? ' width="' . (int)$dim[0] . '" height="' . (int)$dim[1] . '"' : '' ?><?= $i > 0 ? ' loading="lazy"' : '' ?>>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <article class="inv">
      <div class="head">
        <div class="from"><b><?= $h($f['firmanavn'] ?? '') ?></b><span><?= $h($f['addr1'] ?? '') ?></span><?php if (trim((string)($f['addr2'] ?? '')) !== ''): ?><span><?= $h($f['addr2']) ?></span><?php endif; ?><span><?= $h(trim(($f['postnr'] ?? '') . ' ' . ($f['bynavn'] ?? ''))) ?></span><?php if (trim((string)($f['cvrnr'] ?? '')) !== ''): ?><span>CVR <?= $h($f['cvrnr']) ?></span><?php endif; ?></div>
        <div class="title"><h1><?= $h($docName) ?></h1><?= fak_pill($inv, $state) ?></div>
      </div>
      <div class="meta">
        <div class="to"><div class="lab"><?= $h(stripe_txt($inv['is_credit'] ? 'credit_to' : 'bill_to')) ?></div><b><?= $h($o['firmanavn']) ?></b><?php if (trim((string)$o['kontakt']) !== ''): ?><span>Att.: <?= $h($o['kontakt']) ?></span><?php endif; ?><span><?= $h($o['addr1']) ?></span><?php if (trim((string)$o['addr2']) !== ''): ?><span><?= $h($o['addr2']) ?></span><?php endif; ?><span><?= $h(trim($o['postnr'] . ' ' . $o['bynavn'])) ?></span><?php if (trim((string)$o['cvrnr']) !== ''): ?><span>CVR <?= $h($o['cvrnr']) ?></span><?php endif; ?></div>
        <div>
          <div class="kv"><span><?= $h(stripe_txt($inv['is_credit'] ? 'creditnote_no' : 'invoice_no')) ?></span><b class="num"><?= $h($nr) ?></b></div>
          <div class="kv"><span><?= $h(stripe_txt('customer_no')) ?></span><b class="num"><?= $h($o['kontonr']) ?></b></div>
          <div class="kv"><span><?= $h(stripe_txt('invoice_date')) ?></span><b class="num"><?= $h(stripe_fmt_date($o['fakturadate'])) ?></b></div>
          <?php if (!$inv['is_credit']): ?><div class="kv"><span><?= $h(stripe_txt('due_date')) ?></span><b class="num"><?= $h($dueFmt) ?></b></div><?php endif; ?>
        </div>
      </div>
      <table class="lines"><thead><tr><th><?= $h(stripe_txt('description')) ?></th><th class="r"><?= $h(stripe_txt('qty')) ?></th><th class="r"><?= $h(stripe_txt('price')) ?></th><th class="r"><?= $h(stripe_txt('amount')) ?></th></tr></thead><tbody><?= $linesHtml ?></tbody></table>
      <div class="totals">
        <div class="kv"><span><?= $h(stripe_txt('subtotal')) ?></span><b class="num"><?= $h(number_format((float)$o['sum'], 2, ',', '.')) ?></b></div>
        <div class="kv"><span><?= $h(stripe_txt('vat', array('pct' => $vatPct))) ?></span><b class="num"><?= $h(number_format((float)$o['moms'], 2, ',', '.')) ?></b></div>
        <div class="sum"><span><?= $h(stripe_txt('total', array('cur' => $inv['currency']))) ?></span><b class="num"><?= $h(stripe_fmt_kr($inv['total_ore'])) ?></b></div>
      </div>
      <div class="terms">
        <?php if ($inv['is_credit']): ?><p><?= $h(stripe_txt('lead_credited')) ?></p><?php else: ?>
        <p><b><?= $h(stripe_txt('terms')) ?></b> <?= $h(trim((string)$o['betalingsbet'] . ' ' . (int)$o['betalingsdage'] . ' dage')) ?>.</p>
        <?php if ($hasBank): ?><p><?= $h(stripe_txt($inv['no_card'] ? 'bank_line_nocard' : 'bank_line', array('reg' => $bankReg, 'konto' => $bankKonto, 'nr' => $nr))) ?></p><?php endif; ?>
        <?php endif; ?>
      </div>
    </article>
    <?php endif; ?>
  </main>
  <aside class="right" id="panel"><?= $panel ?></aside>
</div>
<script>
(function(){
  var cfg = <?= json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var errBox = document.getElementById('pay-error');
  function showErr(m){ if (errBox) errBox.textContent = m || ''; }
  function poll(times){
    fetch(cfg.stateUrl, {credentials:'omit'}).then(function(r){return r.json()}).then(function(j){
      if (j.state !== 'pending' && j.state !== cfg.state) { location.replace(cfg.returnUrl); return; }
      if (times > 0) setTimeout(function(){ poll(times-1) }, 2000);
      else location.replace(cfg.returnUrl);
    }).catch(function(){ if (times > 0) setTimeout(function(){ poll(times-1) }, 3000); });
  }
  if (cfg.state === 'pending') { poll(15); return; }
  function authenticate(j, button){
    if (j.status !== 'requires_action') { location.replace(cfg.returnUrl + '&payment_intent=' + encodeURIComponent(j.pi)); return; }
    if (!cfg.pk || !window.Stripe || !j.client_secret) { showErr(cfg.actionError); button.disabled = false; return; }
    Stripe(cfg.pk, {locale:'da'}).handleNextAction({clientSecret:j.client_secret}).then(function(result){
      if (result.error) { showErr(result.error.message); button.disabled = false; return; }
      location.replace(cfg.returnUrl + '&payment_intent=' + encodeURIComponent(j.pi));
    }).catch(function(){ showErr(cfg.actionError); button.disabled = false; });
  }
  var resume = document.getElementById('resume');
  if (resume) {
    resume.addEventListener('click', function(){
      resume.disabled = true; showErr('');
      fetch(cfg.payUrl, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({resume:1})})
        .then(function(r){ return r.json(); }).then(function(j){
          if (!j.ok) { showErr(j.message || cfg.actionError); resume.disabled = false; return; }
          authenticate(j, resume);
        }).catch(function(){ showErr(cfg.actionError); resume.disabled = false; });
    });
    return;
  }
  var form = document.getElementById('pay-form'), payBtn = document.getElementById('pay'), chk = document.getElementById('chk'), payNow = document.getElementById('paynow');
  if (payNow) {
    payNow.addEventListener('click', function(){
      payNow.disabled = true; showErr('');
      fetch(cfg.payUrl, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({pay_now:1})}).then(function(r){return r.json()}).then(function(j){
        if (j.error === 'declined') { location.replace(cfg.returnUrl); return; }
        if (!j.ok) { showErr(j.message || cfg.payError); payNow.disabled = false; return; }
        authenticate(j, payNow);
      }).catch(function(){ showErr(cfg.netError); payNow.disabled = false; });
    });
    return;
  }
  if (!form) return;
  if (!cfg.pk || !window.Stripe) { showErr(cfg.startError); return; }
  var saveCard = !!cfg.saveDefault, elementReady = false, submitting = false;
  var usageReady = Promise.resolve(), usageResolve = null;
  function updBtn(){ if (payBtn) payBtn.textContent = saveCard ? cfg.btnSave : cfg.btn; }
  function release(){ submitting = false; payBtn.disabled = !elementReady; }
  function updateUsage(usage){
    return new Promise(function(resolve){
      usageResolve = resolve;
      elements.update({setupFutureUsage:usage});
    });
  }
  if (chk) { chk.setAttribute('aria-checked', saveCard ? 'true' : 'false'); chk.addEventListener('click', toggle); chk.addEventListener('keydown', function(e){ if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); toggle(); } }); }
  function toggle(){
    if (submitting) return;
    saveCard = !saveCard;
    chk.setAttribute('aria-checked', saveCard ? 'true' : 'false');
    updBtn();
    var usage = saveCard ? 'off_session' : null;
    usageReady = usageReady.then(function(){ return updateUsage(usage); });
  }
  updBtn();
  var stripe = Stripe(cfg.pk, {locale:'da'});
  var elements = stripe.elements({mode:'payment', amount: cfg.amount, currency: cfg.currency, paymentMethodTypes:['card'], setupFutureUsage: saveCard ? 'off_session' : null, paymentMethodCreation:'manual',
    appearance:{theme:'stripe', variables:{colorPrimary:'#1b68bd', colorText:'#111318', colorDanger:'#c2410c', fontFamily:'Inter, "Segoe UI", system-ui, sans-serif', borderRadius:'10px', fontSizeBase:'16px'}}});
  elements.on('update-end', function(){
    if (usageResolve) { var resolve = usageResolve; usageResolve = null; resolve(); }
  });
  var pe = elements.create('payment', {layout:'tabs', wallets:{link:'never'}});
  pe.on('ready', function(){ elementReady = true; payBtn.disabled = submitting; });
  pe.on('loaderror', function(){ elementReady = false; payBtn.disabled = true; showErr(cfg.startError); });
  pe.mount('#payment-element');
  form.addEventListener('submit', function(e){
    e.preventDefault();
    if (!elementReady || submitting) return;
    submitting = true; showErr(''); payBtn.disabled = true;
    usageReady.then(function(){ return elements.submit(); }).then(function(res){
      if (res.error) { showErr(res.error.message); release(); return; }
      return fetch(cfg.payUrl, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({save_card: saveCard})}).then(function(r){return r.json()}).then(function(j){
        if (!j.ok) { showErr(j.message || cfg.startError); release(); return; }
        var effectiveUsageReady = Promise.resolve();
        if ((saveCard ? 'off_session' : null) !== j.setup_future_usage) {
          effectiveUsageReady = updateUsage(j.setup_future_usage);
        }
        return effectiveUsageReady.then(function(){
          return stripe.confirmPayment({elements: elements, clientSecret: j.client_secret, confirmParams:{return_url: cfg.returnUrl}});
        }).then(function(r2){
          if (r2 && r2.error) { showErr(r2.error.message); release(); }
        });
      });
    }).catch(function(){ showErr(cfg.netError); release(); });
  });
})();
</script>
</body>
</html>
