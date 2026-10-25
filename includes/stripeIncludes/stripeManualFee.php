<?php
// 20261025 CL/ASR Fee for manual payment. An invoice that the customer paid manually - by bank transfer or with a
//                 one-off card payment on the invoice & payment page ("Betal kun denne faktura") - adds the fee
//                 product to the customer's next invoice. Automatic payment (card on file: the charge job, a saved
//                 card, or a page payment that turned on automatic payment) and PBS are exempt. A customer who pays
//                 automatically when the next invoice is made has pending fees waived. Each decision is stored in
//                 stripe_manual_fees, so an invoice is charged or waived at most once.
//                 Settings (var_grp stripe): manual_fee_varenr = the fee product (empty = off), manual_fee_from =
//                 first invoice date that counts. Only in the Stripe ledger with enrolment on, so the customer can
//                 always avoid the fee by turning on automatic payment.

require_once __DIR__ . '/stripeSettings.php';

/** @return array{vare: array, from: string}|null Fee product (id, varenr, beskrivelse, salgspris) and start date; null when off. */
function stripe_manual_fee_config() {
	global $db;
	if (!$db || stripeConfigDb() !== $db || stripe_setting('enabled', 'off') !== 'on' || !stripe_feature_on('enrolment')) return null;
	$varenr = trim((string)stripe_setting('manual_fee_varenr'));
	$from = trim((string)stripe_setting('manual_fee_from'));
	if ($varenr === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) return null;
	$vare = db_fetch_array(db_select("select id, varenr, beskrivelse, salgspris from varer where varenr = '" . db_escape_string($varenr) . "'", __FILE__ . " linje " . __LINE__));
	if (!$vare || (float)$vare['salgspris'] <= 0) return null;
	return array('vare' => $vare, 'from' => $from);
}

/** @return bool Whether stripe_manual_fees exists; created here too, so the first posting after an update cannot skip a fee. */
function stripe_manual_fee_table() {
	static $exists = null;
	if ($exists === null) {
		$query = "select table_name from information_schema.tables where table_name = 'stripe_manual_fees'";
		$exists = (bool)db_fetch_array(db_select($query, __FILE__ . " linje " . __LINE__));
		if (!$exists) {
			// Same definition as stripe_charge_schema_ensure().
			db_modify("CREATE TABLE IF NOT EXISTS stripe_manual_fees (ordre_id integer PRIMARY KEY, fee_ordre_id integer NOT NULL,
				status varchar(10) NOT NULL, created_at timestamp DEFAULT CURRENT_TIMESTAMP)", __FILE__ . " linje " . __LINE__);
			$exists = (bool)db_fetch_array(db_select($query, __FILE__ . " linje " . __LINE__));
		}
	}
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
 * @return string 'manual', 'automatic', or '' when not paid yet or not relevant (credited, cash sale, disputed, ...).
 */
function stripe_manual_fee_payment_kind($o) {
	$id = (int)$o['id'];
	if ((float)$o['sum'] + (float)$o['moms'] <= 0) return '';
	if (in_array(strtolower(trim((string)$o['betalingsbet'])), array('kontant', 'kreditkort'), true)) return '';
	if (db_fetch_array(db_select("select id from ordrer where art = 'DK' and kred_ord_id = $id", __FILE__ . " linje " . __LINE__))) return '';
	if (in_array(trim((string)$o['udskriv_til']), array('Stripe', 'PBS'), true) || strtoupper(trim((string)$o['pbs'])) === 'BS') return 'automatic';
	$stripe = db_fetch_array(db_select("select table_name from information_schema.tables where table_name = 'stripe_payments'", __FILE__ . " linje " . __LINE__));
	if ($stripe) {
		$p = db_fetch_array(db_select("select source, save_card, status from stripe_payments where ordre_id = $id
			and status in ('succeeded', 'refunded', 'disputed', 'duplicate_paid') order by id", __FILE__ . " linje " . __LINE__));
		if ($p) {
			if ($p['status'] !== 'succeeded') return '';
			$saved = in_array($p['save_card'], array(true, 't', 1, '1', 'true', 'on'), true);
			return ($p['source'] === 'page' && !$saved) ? 'manual' : 'automatic';
		}
	}
	// Paid outside Stripe: the invoice's open item is settled (bank transfer, cash at the office, ...).
	$op = db_fetch_array(db_select("select count(*) as n, coalesce(sum(case when coalesce(udlignet, '0') = '1' then 0 else 1 end), 0) as open
		from openpost where konto_id = " . (int)$o['konto_id'] . " and faktnr = '" . db_escape_string((string)$o['fakturanr']) . "'", __FILE__ . " linje " . __LINE__));
	return ($op && (int)$op['n'] > 0 && (int)$op['open'] === 0) ? 'manual' : '';
}

/**
 * Earlier invoices of the customer that were paid manually on or after the start date and have no fee decision yet.
 *
 * @return array<int, array> ordrer rows, oldest first.
 */
function stripe_manual_fee_pending($kontoId, $excludeOrderId, $from) {
	if (!stripe_manual_fee_table() || (int)$kontoId <= 0) return array();
	$q = db_select("select o.id, o.konto_id, o.fakturanr, o.fakturadate, o.betalingsbet, o.udskriv_til, o.pbs, o.sum, o.moms
		from ordrer o where o.konto_id = " . (int)$kontoId . " and o.art = 'DO' and o.status >= 3 and o.id <> " . (int)$excludeOrderId . "
		and coalesce(o.fakturanr, '') not in ('', '0') and o.fakturadate >= '" . db_escape_string($from) . "'
		and not exists (select 1 from stripe_manual_fees f where f.ordre_id = o.id)
		order by o.fakturadate, o.id", __FILE__ . " linje " . __LINE__);
	$pending = array();
	while ($o = db_fetch_array($q)) {
		if (stripe_manual_fee_payment_kind($o) === 'manual') $pending[] = $o;
	}
	return $pending;
}

/**
 * Decide the fee for an invoice about to be posted, without changing anything.
 *
 * @return array{charge: array<int, array>, waive: array<int, array>, vare: array|null}
 */
function stripe_manual_fee_decide($orderId) {
	$none = array('charge' => array(), 'waive' => array(), 'vare' => null);
	$cfg = stripe_manual_fee_config();
	if (!$cfg) return $none;
	$o = db_fetch_array(db_select("select id, art, konto_id, udskriv_til, pbs from ordrer where id = " . (int)$orderId, __FILE__ . " linje " . __LINE__));
	if (!$o || $o['art'] !== 'DO' || (int)$o['konto_id'] <= 0) return $none;
	$pending = stripe_manual_fee_pending((int)$o['konto_id'], (int)$o['id'], $cfg['from']);
	if (!$pending) return $none;
	if (stripe_manual_fee_debtor_automatic((int)$o['konto_id'], $o)) return array('charge' => array(), 'waive' => $pending, 'vare' => $cfg['vare']);
	return array('charge' => $pending, 'waive' => array(), 'vare' => $cfg['vare']);
}

/** @return void Store the decisions; runs inside the posting transaction, so a failed posting stores nothing. */
function stripe_manual_fee_record($orderId, $decision) {
	foreach (array('charged' => $decision['charge'], 'waived' => $decision['waive']) as $status => $invoices) {
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
 * paid invoices) or waive the pending fees, and record the decision. kdo = 'on' keeps the line out of the next
 * recurring copy (genfakt), so a fee is never repeated.
 *
 * @return string '' on success, otherwise an error for bogfor() to return.
 */
function stripe_manual_fee_bogfor($id, $art, $momssats) {
	if ($art !== 'DO' || !function_exists('opret_ordrelinje')) return '';
	$decision = stripe_manual_fee_decide($id);
	if (!$decision['charge'] && !$decision['waive']) return '';
	if ($decision['charge']) {
		$vare = $decision['vare'];
		$count = count($decision['charge']);
		$r = db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = " . (int)$id, __FILE__ . " linje " . __LINE__));
		$posnr = (int)$r['posnr'] + 1;
		$text = db_escape_string(stripe_manual_fee_line_text($vare, $decision['charge']));
		$res = opret_ordrelinje($id, $vare['id'], $vare['varenr'], $count, $text, (float)$vare['salgspris'], 0, 100, $art, '', $posnr, '', '', 'on', 'percent', '', '', '', '', '', __LINE__);
		if (!is_numeric($res)) return 'Gebyr for manuel betaling: ' . $res;
		$line = db_fetch_array(db_select("select id, momsfri from ordrelinjer where ordre_id = " . (int)$id . " and vare_id = " . (int)$vare['id'] . " and posnr = $posnr order by id desc", __FILE__ . " linje " . __LINE__));
		if (!$line) return 'Gebyr for manuel betaling: linjen blev ikke oprettet';
		db_modify("update ordrelinjer set leveres = '$count' where id = " . (int)$line['id'], __FILE__ . " linje " . __LINE__);
		levering($id, '', '', '');
		$net = round($count * (float)$vare['salgspris'], 2);
		$vat = trim((string)$line['momsfri']) === 'on' ? 0 : round($net * (float)$momssats / 100, 2);
		db_modify("update ordrer set sum = sum + $net, moms = moms + $vat where id = " . (int)$id, __FILE__ . " linje " . __LINE__);
	}
	stripe_manual_fee_record($id, $decision);
	return '';
}

/** @return int Fee incl. VAT in øre for one manual payment, for the payment page; 0 when the fee is off. */
function stripe_manual_fee_ore($momssats = 25) {
	$cfg = stripe_manual_fee_config();
	if (!$cfg) return 0;
	return (int)round((float)$cfg['vare']['salgspris'] * (1 + (float)$momssats / 100) * 100);
}
