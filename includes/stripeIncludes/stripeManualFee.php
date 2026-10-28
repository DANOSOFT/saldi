<?php
// 20261025 CL/ASR Fee for manual payment. An invoice that the customer paid manually - by bank transfer or with a
//                 one-off card payment on the invoice & payment page ("Betal kun denne faktura") - adds the fee
//                 product to the customer's next invoice. Automatic payment (card on file: the charge job, a saved
//                 card, or a page payment that turned on automatic payment) and PBS are exempt. A customer who pays
//                 automatically when the next invoice is made has pending fees waived. Each decision is stored in
//                 stripe_manual_fees (charged | waived | exempt), so an invoice is decided at most once.
//                 Settings (var_grp stripe): manual_fee_varenr = the fee product (empty = off), manual_fee_from =
//                 first invoice date that counts. Only in the Stripe ledger with invoice payments and enrolment on,
//                 so the customer can always avoid the fee by turning on automatic payment.
// 20261026 CL/NTR Multi-ledger: applies in every ledger with Stripe, invoice payments and enrolment on (not in a copy).

require_once __DIR__ . '/stripeSettings.php';

/** @return bool Whether a YYYY-MM-DD string is a real calendar date. */
function stripe_manual_fee_valid_date($date) {
	return is_string($date) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) === 1 && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

/** @return array{vare: array, from: string}|null Fee product (id, varenr, beskrivelse, salgspris, gruppe) and start date; null when off. */
function stripe_manual_fee_config() {
	global $db;
	if (!$db || !stripe_ledger_armed() || stripe_setting('enabled', 'off') !== 'on'
		|| !stripe_feature_on('invoice_payments') || !stripe_feature_on('enrolment')) return null;
	$varenr = trim((string)stripe_setting('manual_fee_varenr'));
	$from = trim((string)stripe_setting('manual_fee_from'));
	if ($varenr === '' || !stripe_manual_fee_valid_date($from)) return null;
	$vare = db_fetch_array(db_select("select id, varenr, beskrivelse, salgspris, gruppe from varer where varenr = '" . db_escape_string($varenr) . "'", __FILE__ . " linje " . __LINE__));
	if (!$vare || (float)$vare['salgspris'] <= 0) return null;
	return array('vare' => $vare, 'from' => $from);
}

/** @return bool Whether stripe_manual_fees exists (created by stripe_charge_schema_ensure() from betweenUpdates). */
function stripe_manual_fee_table() {
	static $exists = false;
	if (!$exists) $exists = (bool)tbl_exists('stripe_manual_fees');
	return $exists;
}

/** @return bool Whether the customer pays automatically now: card on file at Stripe, or PBS. */
function stripe_manual_fee_debtor_automatic($kontoId, $order = array()) {
	if (in_array(trim((string)($order['udskriv_til'] ?? '')), array('Stripe', 'PBS'), true)
		|| strtoupper(trim((string)($order['pbs'] ?? ''))) === 'BS') return true;
	$d = db_fetch_array(db_select("select stripe, stripe_pm_id, pbs_nr from adresser where id = " . (int)$kontoId, __FILE__ . " linje " . __LINE__));
	if (!$d) return false;
	if (trim((string)$d['stripe']) === 'on' && trim((string)$d['stripe_pm_id']) !== '') return true;
	return (float)$d['pbs_nr'] > 0;
}

/**
 * How an issued invoice was paid, as far as the fee is concerned.
 *
 * @param array $o ordrer row (id, konto_id, fakturanr, betalingsbet, udskriv_til, pbs, sum, moms).
 * @return string 'manual'; 'automatic'; 'exempt' when it can never give a fee (credited, cash sale, zero, refunded,
 *                disputed); '' when not paid yet.
 */
function stripe_manual_fee_payment_kind($o) {
	include_once __DIR__ . '/stripeInvoice.php';     // stripe_bool()
	$id = (int)$o['id'];
	if ((float)$o['sum'] + (float)$o['moms'] <= 0) return 'exempt';
	if (in_array(strtolower(trim((string)$o['betalingsbet'])), array('kontant', 'kreditkort'), true)) return 'exempt';
	if (db_fetch_array(db_select("select id from ordrer where art = 'DK' and kred_ord_id = $id", __FILE__ . " linje " . __LINE__))) return 'exempt';
	if (in_array(trim((string)$o['udskriv_til']), array('Stripe', 'PBS'), true) || strtoupper(trim((string)$o['pbs'])) === 'BS') return 'automatic';
	static $stripeTable = null;
	if ($stripeTable === null) $stripeTable = (bool)tbl_exists('stripe_payments');
	if ($stripeTable) {
		$p = db_fetch_array(db_select("select source, save_card, status from stripe_payments where ordre_id = $id
			and status in ('succeeded', 'refunded', 'disputed', 'duplicate_paid') order by id", __FILE__ . " linje " . __LINE__));
		if ($p) {
			if ($p['status'] !== 'succeeded') return 'exempt';
			return ($p['source'] === 'page' && !stripe_bool($p['save_card'])) ? 'manual' : 'automatic';
		}
	}
	// Paid outside Stripe: the invoice's open item is settled (bank transfer, cash at the office, ...).
	$op = db_fetch_array(db_select("select count(*) as n, coalesce(sum(case when coalesce(udlignet, '0') = '1' then 0 else 1 end), 0) as open
		from openpost where konto_id = " . (int)$o['konto_id'] . " and faktnr = '" . db_escape_string((string)$o['fakturanr']) . "'", __FILE__ . " linje " . __LINE__));
	return ($op && (int)$op['n'] > 0 && (int)$op['open'] === 0) ? 'manual' : '';
}

/**
 * Earlier invoices of the customer, on or after the start date, without a decision yet.
 *
 * @return array{manual: array<int, array>, exempt: array<int, array>} Paid manually / can never give a fee
 *               (automatic or permanently excluded). Unpaid invoices are in neither and are looked at again later.
 */
function stripe_manual_fee_pending($kontoId, $excludeOrderId, $from) {
	$found = array('manual' => array(), 'exempt' => array());
	if (!stripe_manual_fee_table() || (int)$kontoId <= 0) return $found;
	$q = db_select("select o.id, o.konto_id, o.fakturanr, o.fakturadate, o.betalingsbet, o.udskriv_til, o.pbs, o.sum, o.moms
		from ordrer o where o.konto_id = " . (int)$kontoId . " and o.art = 'DO' and o.status >= 3 and o.id <> " . (int)$excludeOrderId . "
		and coalesce(o.fakturanr, '') not in ('', '0') and o.fakturadate >= '" . db_escape_string($from) . "'
		and not exists (select 1 from stripe_manual_fees f where f.ordre_id = o.id)
		order by o.fakturadate, o.id", __FILE__ . " linje " . __LINE__);
	while ($o = db_fetch_array($q)) {
		$kind = stripe_manual_fee_payment_kind($o);
		if ($kind === 'manual') $found['manual'][] = $o;
		elseif ($kind !== '') $found['exempt'][] = $o;
	}
	return $found;
}

/**
 * Decide the fee for an invoice about to be posted, without changing anything.
 *
 * @return array{charge: array<int, array>, waive: array<int, array>, exempt: array<int, array>, vare: array|null}
 */
function stripe_manual_fee_decide($orderId) {
	$none = array('charge' => array(), 'waive' => array(), 'exempt' => array(), 'vare' => null);
	$cfg = stripe_manual_fee_config();
	if (!$cfg) return $none;
	$o = db_fetch_array(db_select("select id, art, konto_id, udskriv_til, pbs from ordrer where id = " . (int)$orderId, __FILE__ . " linje " . __LINE__));
	if (!$o || $o['art'] !== 'DO' || (int)$o['konto_id'] <= 0) return $none;
	// bogfor() posts a DO with negative lines and no item line with a positive quantity as a credit note (dan_kn);
	// never add a fee to that, nor to an order with a zero or negative total.
	$lines = db_fetch_array(db_select("select coalesce(sum(antal * pris * (100 - coalesce(rabat, 0)) / 100), 0) as net,
		coalesce(sum(case when antal < 0 then 1 else 0 end), 0) as negative,
		coalesce(sum(case when vare_id > 0 and antal > 0 then 1 else 0 end), 0) as items
		from ordrelinjer where ordre_id = " . (int)$orderId . " and antal <> 0", __FILE__ . " linje " . __LINE__));
	if (!$lines || (float)$lines['net'] <= 0 || ((int)$lines['negative'] > 0 && (int)$lines['items'] === 0)) return $none;
	$pending = stripe_manual_fee_pending((int)$o['konto_id'], (int)$o['id'], $cfg['from']);
	$decision = array('charge' => array(), 'waive' => array(), 'exempt' => $pending['exempt'], 'vare' => $cfg['vare']);
	if ($pending['manual']) {
		$decision[stripe_manual_fee_debtor_automatic((int)$o['konto_id'], $o) ? 'waive' : 'charge'] = $pending['manual'];
	}
	return $decision;
}

/** @return void Store the decisions; runs inside the posting transaction, so a failed posting stores nothing. */
function stripe_manual_fee_record($orderId, $decision) {
	foreach (array('charged' => $decision['charge'], 'waived' => $decision['waive'], 'exempt' => $decision['exempt'] ?? array()) as $status => $invoices) {
		foreach ($invoices as $inv) {
			db_modify("insert into stripe_manual_fees (ordre_id, fee_ordre_id, status) values (" . (int)$inv['id'] . ", " . (int)$orderId . ", '$status')", __FILE__ . " linje " . __LINE__);
		}
	}
}

/** @return string Line text: the fee product's description and the invoice numbers it covers. */
function stripe_manual_fee_line_text($vare, $invoices) {
	$numbers = array_map(function ($inv) { return (string)$inv['fakturanr']; }, $invoices);
	return trim((string)$vare['beskrivelse']) . ' (' . (count($numbers) === 1 ? 'faktura ' : 'fakturaer ') . implode(', ', $numbers) . ')';
}

/**
 * Called by bogfor() for a DO order before its lines are posted: add one fee line (quantity = number of manually
 * paid invoices) or waive the pending fees, and record the decisions. kdo = 'on' keeps the line out of the next
 * recurring copy (genfakt), so a fee is never repeated. An order without a valid delivery date is left to bogfor's
 * own checks. The line never takes a customer discount; the order totals follow the stored line.
 *
 * @return string '' on success, otherwise an error for bogfor() to return.
 */
function stripe_manual_fee_bogfor($id, $art, $momssats, $webservice = false, $genfakt = false) {
	if ($art !== 'DO' || !function_exists('opret_ordrelinje')) return '';
	$order = db_fetch_array(db_select("select levdate, ordredate from ordrer where id = " . (int)$id, __FILE__ . " linje " . __LINE__));
	if (!$order || !$order['levdate'] || (function_exists('delivery_date_before_order_date') && delivery_date_before_order_date('DO', $order['levdate'], $order['ordredate']))) return '';
	$decision = stripe_manual_fee_decide($id);
	if (!$decision['charge'] && !$decision['waive'] && !$decision['exempt']) return '';
	if ($decision['charge']) {
		$vare = $decision['vare'];
		$count = count($decision['charge']);
		$r = db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = " . (int)$id, __FILE__ . " linje " . __LINE__));
		$posnr = (int)$r['posnr'] + 1;
		$text = db_escape_string(stripe_manual_fee_line_text($vare, $decision['charge']));
		$res = opret_ordrelinje($id, $vare['id'], $vare['varenr'], $count, $text, (float)$vare['salgspris'], 0, 100, $art, '', $posnr, '', '', 'on', 'percent', '', '', '', '', '', __LINE__);
		if (!is_numeric($res)) return 'Gebyr for manuel betaling: ' . $res;
		$line = db_fetch_array(db_select("select id from ordrelinjer where ordre_id = " . (int)$id . " and vare_id = " . (int)$vare['id'] . " and posnr = $posnr order by id desc", __FILE__ . " linje " . __LINE__));
		if (!$line) return 'Gebyr for manuel betaling: linjen blev ikke oprettet';
		db_modify("update ordrelinjer set rabat = 0, m_rabat = 0, leveres = '$count' where id = " . (int)$line['id'], __FILE__ . " linje " . __LINE__);
		$delivered = levering($id, '', $genfakt, $webservice);
		if ($delivered !== 'OK') return 'Gebyr for manuel betaling: ' . $delivered;
		$line = db_fetch_array(db_select("select antal, pris, momsfri, momssats from ordrelinjer where id = " . (int)$line['id'], __FILE__ . " linje " . __LINE__));
		$net = round((float)$line['antal'] * (float)$line['pris'], 2);
		$rate = (float)$line['momssats'] > 0 ? (float)$line['momssats'] : (float)$momssats;
		$vat = trim((string)$line['momsfri']) === 'on' ? 0 : round($net * $rate / 100, 2);
		db_modify("update ordrer set sum = sum + $net, moms = moms + $vat where id = " . (int)$id, __FILE__ . " linje " . __LINE__);
	}
	stripe_manual_fee_record($id, $decision);
	return '';
}

/** @return bool Whether the fee product's item group is VAT-exempt. */
function stripe_manual_fee_vat_free($vare) {
	$r = db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '" . db_escape_string((string)$vare['gruppe']) . "' order by fiscal_year desc", __FILE__ . " linje " . __LINE__));
	return $r && trim((string)$r['box7']) === 'on';
}

/**
 * Fee incl. VAT in øre for the payment page, or 0 when the note does not apply to this invoice (fee off, or the
 * invoice is dated before the start date and can never give a fee).
 */
function stripe_manual_fee_ore($momssats = 25, $invoiceDate = '') {
	$cfg = stripe_manual_fee_config();
	if (!$cfg) return 0;
	if ($invoiceDate !== '' && substr((string)$invoiceDate, 0, 10) < $cfg['from']) return 0;
	$vat = stripe_manual_fee_vat_free($cfg['vare']) ? 0 : (float)$momssats;
	return (int)round((float)$cfg['vare']['salgspris'] * (1 + $vat / 100) * 100);
}
