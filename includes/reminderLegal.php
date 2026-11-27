<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/reminderLegal.php --- ver 5.1.1 --- 2026.11.27 ---
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
// 20261109 CL/ASR Created. Renteloven § 9 b for every reminder (rykker), classic and link, in every ledger: L1 a reminder fee is at most 100,00 kr., L2 fees on at most 3 reminders, L3 at least 10 days between reminders when the new one carries a fee.
//                 Used by debitor/ny_rykker.php when reminders are created and by the send path (rykkerprint) when they are sent.
//                 No Stripe dependency.
//                 Also the shared reading of a reminder's lines: its fee/interest lines (GEBYR items of formular level+5) and the newest invoice it reminds of.
// 20261109 CL/ASR Review fixes: a fee item that is also the interest item counts only its last line as fee (the others are interest); the fee of an earlier level of the same chain is recognised (reminder_prior_fee_level()); which reminded invoices are paid (reminder_invoice_payment()); the active fiscal year (reminder_in_active_year()).
//                 reminder_txt() returns its Danish fallback in the database's encoding, like findtekst().
//
// 20261116 CDX/ASR Bind reminder creation to the report's ledger, including popup requests.
// 20261117 CDX/ASR Use the automation queue's confirmed sending date when checking the next reminder.
// 20261126 ASR Review I3: an automatic reminder that was not sent (a reminder_automation_jobs row whose state is not 'sent') is not escalated: reminder_escalation_skip() returns 'unsent'.
//              reminder_sent_date() returns '' for such a reminder instead of its date, and reminder_automation_unsent()/reminder_automation_unsent_sql() find it.
// 20261126 ASR Review GPT-R-1: reminder_posting_year() and reminder_group_year_order() let both bogfor_rykker() copies read the item and debtor groups of the year the reminder is posted in, as invoice posting does.
// 20261126 ASR Review GPT-R-3: L2 and L3 also count the reminders with a fee made earlier for the same invoice in another chain (reminder_claim_fee_history()), as closing a chain does not end the claim.
//              reminder_legal_check() refuses a 4th fee for an invoice (8215) and a fee less than 10 days after the newest reminder with a fee for it was sent (8216).
//              reminder_claim_fee_refusal() lets creation leave the fee off such a reminder.
// 20261126 ASR Review GPT-R-4: reminder_sent_date() takes the day a reminder first went out by mail or on paper (reminder_dispatch, reminder_dispatch_record()) before its date.
//              A reminder from before the update has no such row and keeps its date, as before.
// 20261126 ASR Review GPT-F-1: reminder_booking_posted() and reminder_unposted_sql() tell a booked reminder from one left at status 3 by a booking that stopped before it posted.
//              Both bogfor_rykker() copies, the reminder overview and "Ryk alle" book such a reminder again; one with a fee open item or a posting stays booked.
// 20261126 ASR Review GPT-G-4: reminder_posting_groups() replaces reminder_group_year_order(): a reminder is posted only with the item and debtor groups of its posting year.
//              When one of them has no row in that year, nothing is posted and the message names the groups and the year (8227-8229); another year's row is never used.
// 20261126 ASR Review GPT-G-7: the fees counted per invoice leave out a reminder made while Saldi records the day reminders go out (reminder_dispatch_first_id()) that was never mailed or printed.
//              reminder_claim_sent_date() counts such a reminder only by its recorded day (reminder_recorded_sent_date()); a reminder made before that still counts from its date.
// 20261126 ASR Review R3-G7-1: such a reminder also counts when its fee was booked to the customer (reminder_booking_posted()), dated as reminder_sent_date().
//              Escalating with "Ryk alle" books the level first, and the next level lists that fee, also when the level itself was never mailed or printed.
// 20261126 ASR Review AR2-2: the refusal for an automatic reminder that was not sent follows its job's state (reminder_automation_state()).
//              A draft (approval, queued) keeps 8242, approve it or delete it; one that is paused or whose sending failed or is uncertain (held, failed, sending) points to Automatiske rykkere (8231) and is not offered to be deleted.
// 20261126 ASR Review AR2-1: reminder_went_out() tells a reminder that was mailed, printed or sent by the automatic flow (or whose sending is unresolved) from a draft.
//              reminder_delete_draft() deletes a draft with its lines, its draft link reminder row and its automatic flow's job (logged as the event 'deleted'), for "Ryk alle".
// 20261126 ASR Review R3E2E-1: reminder_sending_tracked() replaces reminder_went_out() and looks only at the automatic flow's job and the link reminder's row, whose deletion would leave them without their reminder.
//              A classic reminder that was only printed or mailed (a reminder_dispatch row and nothing else) is deleted by "Ryk alle" as before the update, as whether a delivered reminder is kept is owner question Q26.
//              reminder_delete_draft() also deletes the reminder's reminder_dispatch row.
// 20261127 ASR Review GPT-H-1: when a reminder goes out for the first time (reminder_claim_first_dispatch()), the fees counted per invoice include every other reminder for it that went out or whose fee was booked, also one made after it.
//              A reminder that already counts (sent, booked, or made before Saldi recorded the day) is checked as before, against the reminders made before it, so sending it again is not held back by its later levels.
//              reminder_dispatch_locked() lets the final check and the record of the day it went out run under a lock per customer, so two first dispatches at once cannot both use the last fee allowed.
// 20261127 ASR Review AR3-1: the refusal for a paused automatic reminder follows whether an attempt to send it is unresolved (reminder_automation_attempt_unresolved(), the predicate of ReminderAutomation::uncertainSql()).
//              A paused draft that was never attempted, or whose attempt was checked and approved again, gets 8234: approve it under Automatiske rykkere or delete it. One with an unresolved attempt keeps 8231.
//              reminder_sending_tracked() uses the same function.

/**
 * Whether the report and the current session refer to the same ledger.
 * Missing context is refused so a stale report cannot create reminders in another company.
 *
 * @param mixed $requested Ledger carried by the report form or popup URL.
 * @param string $current Authenticated session ledger.
 * @return bool
 */
function reminder_request_ledger_matches($requested, string $current): bool {
	return is_string($requested) && $requested !== '' && $current !== '' && hash_equals($current, $requested);
}

/**
 * Staff text through findtekst() with the Danish wording as fallback, so a text that is not in tekster.csv
 * yet (or a context without findtekst) still shows Danish. {name} placeholders are replaced from $vars.
 *
 * @param int $textId Id in importfiler/tekster.csv.
 * @param string $danish The Danish text, used when findtekst() has no translation.
 * @param array<string, string> $vars Placeholder values.
 * @return string The text.
 */
function reminder_txt($textId, $danish, $vars = array()) {
	global $sprog_id, $db_encode;
	$text = '';
	if (function_exists('findtekst')) {
		$text = (string)findtekst((int)$textId, $sprog_id ?? 1);
	}
	if ($text === '' || $text === (string)$textId || strpos($text, 'Tekst nr') === 0) {
		// In the database's encoding like findtekst(): ISO-8859-1 on a legacy (non-UTF8) database, so the page's
		// escaping ($charset) and jsString() treat both the same way.
		$text = (isset($db_encode) && $db_encode !== '' && $db_encode != 'UTF8') ? mb_convert_encoding($danish, 'ISO-8859-1', 'UTF-8') : $danish;
	}
	foreach ($vars as $name => $value) {
		$text = str_replace('{' . $name . '}', (string)$value, $text);
	}
	return $text;
}

/** @return float The highest reminder fee per reminder in kroner (renteloven § 9 b, stk. 1). */
function reminder_fee_cap_kr(): float {
	return 100.0;
}

/** @return string An amount as Danish kroner text, e.g. 1.430,00. */
function reminder_fmt_kr($amount) {
	return number_format((float)$amount, 2, ',', '.');
}

/** @return string A Y-m-d date as dd.mm.yyyy (unchanged when it is not a date). */
function reminder_fmt_date($ymd) {
	return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$ymd, $m) ? "$m[3].$m[2].$m[1]" : (string)$ymd;
}

/** @return string Today as Y-m-d: the Stripe clock when it is loaded (tests pin it), else Copenhagen's calendar date. */
function reminder_today() {
	if (function_exists('stripe_today')) return stripe_today();
	return (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
}

/** @return string Y-m-d plus a number of calendar days. */
function reminder_date_add($ymd, $days) {
	return (new DateTimeImmutable(substr((string)$ymd, 0, 10), new DateTimeZone('Europe/Copenhagen')))
		->modify(sprintf('%+d days', (int)$days))->format('Y-m-d');
}

/** @return bool Whether the table exists; only a positive answer is cached (it can be created later in the request). */
function reminder_link_table() {
	static $exists = false;
	if (!$exists && function_exists('tbl_exists')) $exists = (bool)tbl_exists('stripe_reminders');
	return $exists;
}

/**
 * The GEBYR items of a reminder level (formular level+5; one row per form language).
 *
 * @param int $level Reminder level 1..3.
 * @return array{fee: array<int, int>, interest: array<int, int>} Item ids (varer.id) of the fee (xb) and interest (yb) items.
 */
function reminder_fee_items($level) {
	$items = array('fee' => array(), 'interest' => array());
	$q = db_select("select xb, yb from formularer where beskrivelse = 'GEBYR' and formular = " . ((int)$level + 5), __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ((int)$r['xb'] > 0 && !in_array((int)$r['xb'], $items['fee'], true)) $items['fee'][] = (int)$r['xb'];
		if ((int)$r['yb'] > 0 && !in_array((int)$r['yb'], $items['interest'], true)) $items['interest'][] = (int)$r['yb'];
	}
	return $items;
}

/**
 * The fee a new reminder of this level gets, as ny_rykker.php puts it on (the fee item's price, in kroner).
 *
 * @param int $level Reminder level 1..3.
 * @return float The fee item's salgspris, 0 when the level has no fee item.
 */
function reminder_level_fee($level) {
	$r = db_fetch_array(db_select("select salgspris from varer where id IN (select xb from formularer where beskrivelse = 'GEBYR' and formular = " . ((int)$level + 5) . ")", __FILE__ . " linje " . __LINE__));
	return $r ? (float)$r['salgspris'] : 0.0;
}

/**
 * A reminder order and the amounts on its item lines. Item lines are the fee and interest lines that
 * ny_rykker.php adds; an item line whose item is the level's interest item (yb) is interest, every other
 * item line counts as fee. When the same item is both the fee and the interest item, ny_rykker.php adds the
 * interest lines first and the fee line last: the last line of that item is the fee, the earlier ones interest.
 *
 * @param int $rykkerId ordrer.id of an R1/R2/R3 order.
 * @return array{
 *   id: int, level: int, art: string, konto_id: int, ordrenr: int, fakturanr: string,
 *   valuta: string,         'DKK' when empty.
 *   valutakurs: float,      The order's rate (100 for DKK).
 *   sprog: string, email: string, fakturadate: string, ordredate: string, status: int,
 *   fee: float,             Sum of the fee lines in the order's currency.
 *   interest: float,        Sum of the interest lines in the order's currency.
 *   fee_vare_id: int,       Item of the first fee line, 0 when none.
 *   interest_vare_id: int,  Item of the first interest line, 0 when none.
 * }|null Null when the order does not exist or is not a reminder.
 */
function reminder_charge_lines($rykkerId) {
	$o = db_fetch_array(db_select("select id, art, konto_id, ordrenr, fakturanr, valuta, valutakurs, sprog, email, fakturadate, ordredate, status from ordrer where id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__));
	if (!$o || !preg_match('/^R(\d+)$/', trim((string)$o['art']), $m)) return null;
	$level = (int)$m[1];
	$valuta = strtoupper(trim((string)$o['valuta'])) ?: 'DKK';
	$out = array(
		'id' => (int)$o['id'], 'level' => $level, 'art' => trim((string)$o['art']), 'konto_id' => (int)$o['konto_id'],
		'ordrenr' => (int)$o['ordrenr'], 'fakturanr' => trim((string)$o['fakturanr']), 'valuta' => $valuta,
		'valutakurs' => (float)$o['valutakurs'] ?: ($valuta === 'DKK' ? 100.0 : 0.0),
		'sprog' => trim((string)$o['sprog']), 'email' => trim((string)$o['email']),
		'fakturadate' => (string)$o['fakturadate'], 'ordredate' => (string)$o['ordredate'], 'status' => (int)$o['status'],
		'fee' => 0.0, 'interest' => 0.0, 'fee_vare_id' => 0, 'interest_vare_id' => 0,
	);
	$items = reminder_fee_items($level);
	$lines = array();
	$lastShared = array();   // item that is both fee and interest item => id of its last line (the fee line)
	$q = db_select("select id, vare_id, antal, pris, rabat from ordrelinjer where ordre_id = " . (int)$rykkerId . " and vare_id > 0 order by posnr, id", __FILE__ . " linje " . __LINE__);
	while ($l = db_fetch_array($q)) {
		$lines[] = $l;
		$vareId = (int)$l['vare_id'];
		if (in_array($vareId, $items['interest'], true) && in_array($vareId, $items['fee'], true)) {
			// highest id, not the last by posnr: the fee line of a 2nd/3rd reminder has no posnr
			$lastShared[$vareId] = max((int)($lastShared[$vareId] ?? 0), (int)$l['id']);
		}
	}
	foreach ($lines as $l) {
		$vareId = (int)$l['vare_id'];
		// the same line total as bogfor_rykker()
		$amount = (float)$l['antal'] * (float)$l['pris'] - (float)$l['antal'] * (float)$l['pris'] / 100 * (float)$l['rabat'];
		$interest = in_array($vareId, $items['interest'], true)
			&& (!in_array($vareId, $items['fee'], true) || (int)$l['id'] !== $lastShared[$vareId]);
		if ($interest) {
			$out['interest'] += $amount;
			if (!$out['interest_vare_id']) $out['interest_vare_id'] = $vareId;
		} else {
			$out['fee'] += $amount;
			if (!$out['fee_vare_id']) $out['fee_vare_id'] = $vareId;
		}
	}
	$out['fee'] = round($out['fee'], 2);
	$out['interest'] = round($out['interest'], 2);
	return $out;
}

/**
 * The openposts a reminder lists: its comment lines whose ordrelinjer.enhed holds an openpost id.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return array<int, int> openpost ids, in line order, without duplicates.
 */
function reminder_openpost_ids($rykkerId) {
	$ids = array();
	$q = db_select("select enhed from ordrelinjer where ordre_id = " . (int)$rykkerId . " and coalesce(vare_id, 0) = 0 order by posnr, id", __FILE__ . " linje " . __LINE__);
	while ($l = db_fetch_array($q)) {
		$enhed = trim((string)$l['enhed']);
		if ($enhed !== '' && ctype_digit($enhed) && (int)$enhed > 0 && !in_array((int)$enhed, $ids, true)) $ids[] = (int)$enhed;
	}
	return $ids;
}

/**
 * Whether an open item is the fee of an earlier level of the same reminder chain. bogfor_nu() posts a classic
 * reminder's fee and interest as an open item with refnr = that reminder's id and faktnr = its fakturanr, and
 * ny_rykker.php lists it on the next level.
 *
 * @param array{konto_id: int|string, faktnr: string, refnr: int|string} $op The open item.
 * @param array{konto_id: int, ordrenr: int, level: int} $c reminder_charge_lines() of the reminder that lists it.
 * @return int The level (1 or 2) of the earlier reminder whose fee it is; 0 when it is none.
 */
function reminder_prior_fee_level($op, $c) {
	$faktnr = trim((string)($op['faktnr'] ?? ''));
	if ((int)($op['refnr'] ?? 0) <= 0 || $faktnr === '' || (int)$op['konto_id'] !== (int)$c['konto_id']) return 0;
	$r = db_fetch_array(db_select("select art from ordrer where id = " . (int)$op['refnr'] . " and konto_id = " . (int)$c['konto_id'] . "
		and ordrenr = " . (int)$c['ordrenr'] . " and fakturanr = '" . db_escape_string($faktnr) . "' and art like 'R%'", __FILE__ . " linje " . __LINE__));
	if (!$r || !preg_match('/^R(\d+)$/', trim((string)$r['art']), $m) || (int)$m[1] >= (int)$c['level']) return 0;
	return (int)$m[1];
}

/**
 * Whether an open item on a reminder is paid: it is settled (udlignet = '1'), or the invoice (DO) it belongs to is
 * marked paid (betalt = 'on').
 *
 * @param array{konto_id: int|string, faktnr: string, udlignet: string} $op The open item.
 * @return bool
 */
function reminder_openpost_paid($op) {
	if (trim((string)($op['udlignet'] ?? '')) === '1') return true;
	$faktnr = trim((string)($op['faktnr'] ?? ''));
	if ($faktnr === '') return false;
	return (bool)db_fetch_array(db_select("select id from ordrer where konto_id = " . (int)$op['konto_id'] . " and fakturanr = '" . db_escape_string($faktnr) . "'
		and art = 'DO' and betalt = 'on'", __FILE__ . " linje " . __LINE__));
}

/**
 * How many of the invoices a reminder lists are still unpaid ("Ryk alle" and "Ny rykker" do not escalate a reminder
 * whose invoices are all paid). Invoices are its positive open items, except the fee of an earlier level of the chain;
 * paid as reminder_openpost_paid().
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return array{invoices: int, unpaid: int} Number of invoice open items, and how many of them are unpaid.
 */
function reminder_invoice_payment($rykkerId) {
	$out = array('invoices' => 0, 'unpaid' => 0);
	$c = reminder_charge_lines($rykkerId);
	if (!$c) return $out;
	foreach (reminder_openpost_ids($rykkerId) as $opId) {
		$op = db_fetch_array(db_select("select konto_id, faktnr, refnr, amount, udlignet from openpost where id = " . (int)$opId, __FILE__ . " linje " . __LINE__));
		if (!$op || (float)$op['amount'] <= 0 || reminder_prior_fee_level($op, $c)) continue;
		$out['invoices']++;
		if (!reminder_openpost_paid($op)) $out['unpaid']++;
	}
	return $out;
}

/**
 * The newest invoice (DO) among the positive open items on a reminder: the one whose "Udskriv til" decides
 * how the reminder is sent.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return array{id: int, fakturanr: string, fakturadate: string, udskriv_til: string, email: string}|null Null when no line maps to an invoice.
 */
function reminder_newest_invoice($rykkerId) {
	$ids = reminder_openpost_ids($rykkerId);
	if (!$ids) return null;
	$r = db_fetch_array(db_select("select o.id, o.fakturanr, o.fakturadate, o.udskriv_til, o.email from ordrer o, openpost op
		where op.id in (" . implode(',', $ids) . ") and op.amount > 0 and o.konto_id = op.konto_id and o.fakturanr = op.faktnr
		and o.art = 'DO' order by o.fakturadate desc, o.id desc", __FILE__ . " linje " . __LINE__));
	if (!$r) return null;
	return array('id' => (int)$r['id'], 'fakturanr' => trim((string)$r['fakturanr']), 'fakturadate' => (string)$r['fakturadate'],
		'udskriv_til' => trim((string)$r['udskriv_til']), 'email' => trim((string)$r['email']));
}

/**
 * When a reminder was sent: the day it was mailed (stripe_reminders.mailed_at) or first mailed or printed
 * (reminder_dispatch) when Saldi knows it, otherwise its date (fakturadate, fallback ordredate): a reminder made
 * before Saldi recorded the day has no row.
 * An automatic reminder (reminder_automation_jobs) was sent on its sent_date, and not at all without one.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return string Y-m-d, or '' when the reminder does not exist or is an automatic reminder that was not sent.
 */
function reminder_sent_date($rykkerId) {
	$recorded = reminder_recorded_sent_date((int)$rykkerId);
	if ($recorded !== null) return $recorded;
	$o = db_fetch_array(db_select("select fakturadate, ordredate from ordrer where id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__));
	if (!$o) return '';
	$date = trim((string)$o['fakturadate']) ?: trim((string)$o['ordredate']);
	return preg_match('/^\d{4}-\d{2}-\d{2}/', $date) ? substr($date, 0, 10) : '';
}

/**
 * The day Saldi recorded that a reminder went out: an automatic reminder's sending date, the link mail's mailed_at, or the
 * first day it was mailed or printed (reminder_dispatch). reminder_sent_date() falls back to the reminder's date without one.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return string|null Y-m-d; '' for an automatic reminder that was not sent; null when nothing is recorded.
 */
function reminder_recorded_sent_date(int $rykkerId): ?string {
    if (tbl_exists('reminder_automation_jobs')) {
        $tracked = db_fetch_array(db_select('select sent_date from reminder_automation_jobs where order_id=' . (int)$rykkerId, __FILE__ . ' line ' . __LINE__));
        // 20261126 ASR Review I3: a tracked reminder without a sending date was not sent; its date is when it was drafted
        if ($tracked) return !empty($tracked['sent_date']) ? substr((string)$tracked['sent_date'], 0, 10) : '';
    }
	if (reminder_link_table()) {
		$s = db_fetch_array(db_select("select mailed_at from stripe_reminders where rykker_id = " . (int)$rykkerId . " and mailed_at is not null", __FILE__ . " linje " . __LINE__));
		if ($s && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$s['mailed_at'])) return substr((string)$s['mailed_at'], 0, 10);
	}
	// 20261126 ASR Review GPT-R-4: the day a classic reminder first went out, not the day it was made
	$dispatched = reminder_dispatch_date((int)$rykkerId);
	return $dispatched !== '' ? $dispatched : null;
}

/**
 * The first reminder (ordrer.id) made while Saldi records the day reminders go out (reminder_dispatch_since, written once
 * by includes/opdat_func/reminderDispatch.php when reminder_dispatch is there).
 *
 * @return int ordrer.id; 0 when it is not recorded, and every reminder counts as made before.
 */
function reminder_dispatch_first_id(): int {
	if (!reminder_dispatch_table() || !tbl_exists('reminder_dispatch_since')) return 0;
	$r = db_fetch_array(db_select("select first_order_id from reminder_dispatch_since where id = 1", __FILE__ . " linje " . __LINE__));
	return $r ? max(0, (int)$r['first_order_id']) : 0;
}

/**
 * When a reminder with a fee went out or its fee was claimed, for the fees counted per invoice (reminder_claim_fee_history()).
 * A reminder made while Saldi records the day reminders go out (from reminder_dispatch_first_id() on) counts by that recorded
 * day (reminder_recorded_sent_date()). Without one it counts only when its fee was booked to the customer: its booking
 * posted the fee open item or postings (reminder_booking_posted(); "Ryk alle" books a level before it escalates it, and the
 * next level lists that fee). It is then dated as reminder_sent_date(). A link reminder whose fee goes on the next invoice
 * posts nothing when it is booked (all three booking paths), so it counts only by its link mail (mailed_at), after which
 * that fee is charged. One that was made and closed without being mailed, printed or booked was never sent, and its fee was
 * never claimed. An older reminder (or any reminder, when the line is not recorded) may have gone out before Saldi recorded
 * it, so it counts from its date (reminder_sent_date()), as before.
 *
 * @param int $rykkerId ordrer.id of the earlier reminder.
 * @param int $firstTrackedId reminder_dispatch_first_id(); 0 = not recorded.
 * @return string|null Y-m-d it was sent ('' = counted without a date); null when it was neither sent nor its fee booked.
 */
function reminder_claim_sent_date(int $rykkerId, int $firstTrackedId): ?string {
	if ($firstTrackedId > 0 && $rykkerId >= $firstTrackedId) {
		$recorded = reminder_recorded_sent_date($rykkerId);
		if ($recorded !== null && $recorded !== '') return $recorded;
		// 20261126 ASR Review R3-G7-1: a fee booked to the customer was claimed, also when this level itself did not go out
		return reminder_booking_posted($rykkerId) ? reminder_sent_date($rykkerId) : null;
	}
	return reminder_sent_date($rykkerId);
}

/** @return bool Whether the ledger records the day reminders go out (reminder_dispatch, includes/opdat_func/reminderDispatch.php). */
function reminder_dispatch_table(): bool {
	return function_exists('tbl_exists') && (bool)tbl_exists('reminder_dispatch');
}

/**
 * The day a reminder first went out by mail or on paper (reminder_dispatch_record()).
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return string Y-m-d, '' when it is not recorded (not sent through Saldi yet, or sent before Saldi recorded it).
 */
function reminder_dispatch_date(int $rykkerId): string {
	if (!reminder_dispatch_table()) return '';
	$r = db_fetch_array(db_select("select sent_date from reminder_dispatch where order_id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__));
	return ($r && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$r['sent_date'])) ? substr((string)$r['sent_date'], 0, 10) : '';
}

/**
 * Record the day a reminder went out (rykkerprint(): mailed, or printed on paper), for the 10 days to the next reminder
 * with a fee (reminder_sent_date()). Only the first day is kept: sending the same reminder again does not move it.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @param string $channel How it went out: classic, link or print.
 * @param string $date Y-m-d; '' = today.
 * @return void
 */
function reminder_dispatch_record(int $rykkerId, string $channel, string $date = ''): void {
	if ($rykkerId <= 0 || !reminder_dispatch_table() || reminder_dispatch_date($rykkerId) !== '') return;
	$date = $date !== '' ? $date : reminder_today();
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return;
	$channel = substr(preg_replace('/[^a-z]/', '', strtolower($channel)), 0, 12);
	$mysql = in_array($GLOBALS['db_type'] ?? '', array('mysql', 'mysqli'), true);
	db_modify("insert " . ($mysql ? "ignore " : "") . "into reminder_dispatch (order_id, sent_date, channel) values (" . (int)$rykkerId . ", '$date', '$channel')"
		. ($mysql ? "" : " on conflict (order_id) do nothing"), __FILE__ . " linje " . __LINE__);
}

/**
 * Run the final renteloven § 9 b check before a reminder goes out and the record of its dispatch under a lock per customer
 * (the reminder's konto_id), so two first dispatches for the same invoice at once cannot both find the last fee still
 * allowed (reminder_claim_fee_history() counts only the customer's own reminders). PostgreSQL session advisory lock: it
 * holds outside and across transactions, the same session may take it again (the automatic sending takes it around the
 * mail, whose send path takes it too), and it is let go when $work ends or throws, or at the latest when the connection
 * closes. MySQL ledgers take no lock, as reminder_create_lock().
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @param callable $work The check, the dispatch and its record.
 * @return mixed What $work returns.
 */
function reminder_dispatch_locked(int $rykkerId, callable $work) {
	$kontoId = reminder_dispatch_lock($rykkerId);
	try {
		return $work();
	} finally {
		reminder_dispatch_unlock($kontoId);
	}
}

/**
 * Take the dispatch lock of the reminder's customer (reminder_dispatch_locked()); it waits while another session holds it.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return int konto_id locked; 0 when no lock was taken (MySQL, or no such reminder).
 */
function reminder_dispatch_lock(int $rykkerId): int {
	if ($rykkerId <= 0 || in_array($GLOBALS['db_type'] ?? '', array('mysql', 'mysqli'), true)) return 0;
	$o = db_fetch_array(db_select("select konto_id from ordrer where id = " . $rykkerId, __FILE__ . " linje " . __LINE__));
	$kontoId = $o ? (int)$o['konto_id'] : 0;
	if ($kontoId <= 0) return 0;
	db_select("select pg_advisory_lock(hashtext('saldi:reminder_dispatch'), " . $kontoId . ")", __FILE__ . " linje " . __LINE__);
	return $kontoId;
}

/**
 * Let go of a lock reminder_dispatch_lock() took. In a transaction that already failed no statement runs; the lock is then
 * let go when the connection closes.
 *
 * @param int $kontoId reminder_dispatch_lock()'s result; 0 = nothing to let go.
 * @return void
 */
function reminder_dispatch_unlock(int $kontoId): void {
	if ($kontoId <= 0) return;
	$connection = $GLOBALS['connection'] ?? null;
	if ($connection && function_exists('pg_transaction_status') && pg_transaction_status($connection) === PGSQL_TRANSACTION_INERROR) return;
	db_select("select pg_advisory_unlock(hashtext('saldi:reminder_dispatch'), " . $kontoId . ")", __FILE__ . " linje " . __LINE__);
}

/**
 * Whether a reminder is an automatic reminder that was not sent yet (a draft awaiting approval, queued, held, being sent
 * or failed). Its fee is not owed, so it is neither booked nor escalated by hand.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return bool
 */
function reminder_automation_unsent($rykkerId): bool {
	if (!tbl_exists('reminder_automation_jobs')) return false;
	return (bool)db_fetch_array(db_select("select order_id from reminder_automation_jobs where order_id = " . (int)$rykkerId . " and state <> 'sent'", __FILE__ . " linje " . __LINE__));
}

/**
 * SQL predicate that leaves out automatic reminders that were not sent (reminder_automation_unsent()), for "Ryk alle".
 *
 * @param string $alias Trusted SQL alias for the reminder order.
 * @return string '1=1' when the ledger has no automatic reminders.
 */
function reminder_automation_unsent_sql($alias = 'ordrer'): string {
	if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $alias)) throw new InvalidArgumentException('Invalid reminder alias');
	if (!tbl_exists('reminder_automation_jobs')) return '1=1';
	return "not exists (select 1 from reminder_automation_jobs s_aj where s_aj.order_id = $alias.id and s_aj.state <> 'sent')";
}

/**
 * Whether a booking of the reminder posted anything: its fee open item (openpost.refnr = the reminder on its account) or a
 * posting (transaktioner.ordre_id = the reminder). Both bogfor_nu() copies have written both since 2013, and a complete
 * booking ends at status 4. A reminder at status 3 without either was left by a booking that stopped before bogfor_nu()
 * ran (earlier code committed the status the bookable lines had set when another line was refused).
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return bool True when anything was posted for it.
 */
function reminder_booking_posted(int $rykkerId): bool {
	if (db_fetch_array(db_select("select id from transaktioner where ordre_id = " . (int)$rykkerId . " limit 1", __FILE__ . " linje " . __LINE__))) return true;
	return (bool)db_fetch_array(db_select("select openpost.id from openpost, ordrer where ordrer.id = " . (int)$rykkerId . "
		and openpost.refnr = ordrer.id and openpost.konto_id = ordrer.konto_id limit 1", __FILE__ . " linje " . __LINE__));
}

/**
 * SQL predicate for a reminder left at status 3 without anything posted (reminder_booking_posted()): it is not booked, so
 * the overview lists it under Åbne rykkere and Bogfør or "Ryk alle" can book it.
 *
 * @param string $alias Trusted SQL alias for the reminder order.
 * @return string
 */
function reminder_unposted_sql($alias = 'ordrer'): string {
	if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $alias)) throw new InvalidArgumentException('Invalid reminder alias');
	return "($alias.status = 3 and not exists (select 1 from transaktioner s_ut where s_ut.ordre_id = $alias.id)
		and not exists (select 1 from openpost s_uo where s_uo.refnr = $alias.id and s_uo.konto_id = $alias.konto_id))";
}

/**
 * The state of the automatic flow's job for a reminder (reminder_automation_jobs.state).
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return string approval, queued, sending, sent, held or failed; '' when the flow does not track it.
 */
function reminder_automation_state(int $rykkerId): string {
	if (!tbl_exists('reminder_automation_jobs')) return '';
	$r = db_fetch_array(db_select("select state from reminder_automation_jobs where order_id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__));
	return $r ? trim((string)$r['state']) : '';
}

/**
 * Whether an attempt to send the automatic reminder is unresolved: the flow logged a 'sending' event for it and no 'approve'
 * after it (the operator approves again only after checking the mail server's log), also when the job was paused since.
 * The predicate of ReminderAutomation::uncertainSql() for a job that is not sent.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return bool False when the ledger has no audit events for the flow.
 */
function reminder_automation_attempt_unresolved(int $rykkerId): bool {
	if (!tbl_exists('reminder_automation_events')) return false;
	return (bool)db_fetch_array(db_select("select s.id from reminder_automation_events s
		where s.order_id = " . (int)$rykkerId . " and s.action = 'sending' and not exists (select 1 from reminder_automation_events a
		where a.order_id = s.order_id and a.action = 'approve' and a.id > s.id) limit 1", __FILE__ . " linje " . __LINE__));
}

/**
 * Why an automatic reminder that was not sent is neither booked nor escalated by hand. A draft that waits for approval or
 * for the next run (approval, queued) is approved in the flow or deleted (8242). A paused draft without an unresolved
 * sending attempt (held, reminder_automation_attempt_unresolved() false) cannot have reached the customer: it is approved
 * under Automatiske rykkere or deleted (8234). One that is being sent, whose sending failed, or that is paused with an
 * unresolved attempt (sending, failed, held) may have reached the customer, so it is handled under Automatiske rykkere
 * (8231): Registrér som sendt when the mail server's log shows that it went out, otherwise Godkend. It is not offered to be
 * deleted, as the flow's own refusal of a manual Send says too (8214).
 *
 * @param int $rykkerId ordrer.id of the reminder; 0 = the wording for a draft.
 * @return string A clause for reminder_txt(8108) and reminder_skip_notice().
 */
function reminder_automation_unsent_text(int $rykkerId = 0): string {
	$state = $rykkerId > 0 ? reminder_automation_state($rykkerId) : '';
	if ($state === 'held' && !reminder_automation_attempt_unresolved($rykkerId)) { // 20261127 ASR Review AR3-1: a paused draft that was never attempted
		return reminder_txt(8234, 'den automatiske rykker er sat på pause og ikke sendt. Godkend den under Automatiske rykkere, eller slet den.');
	}
	if ($state !== '' && !in_array($state, array('approval', 'queued'), true)) {
		return reminder_txt(8231, 'den automatiske rykker er ikke registreret som sendt. Den er sat på pause, eller dens afsendelse fejlede eller er uafklaret. Se den under Automatiske rykkere: vælg Registrér som sendt, hvis mailserverens log viser, at den blev sendt, ellers Godkend den.');
	}
	return reminder_txt(8242, 'den automatiske rykker er ikke sendt endnu. Godkend den i rykkerflowet, eller slet den.');
}

/**
 * Whether the automatic flow or a link reminder tracks the reminder as sent to the customer, or its sending as unresolved:
 * the automatic flow sent it, is sending it, or its sending failed or is unresolved (a sending attempt that was not approved
 * again after the mail server's log was checked, also when the job was paused since); or it was mailed as a link reminder
 * (stripe_reminders: mailed_at, or status sent or charged). Deleting such a reminder would leave the flow's job or the link
 * reminder's row without its reminder, so "Ryk alle" does not delete it when its customer has no more open items, and says so.
 * A classic reminder that was only printed or mailed (a reminder_dispatch row and nothing else) is not tracked here: "Ryk
 * alle" deletes it as before the update, as whether a delivered reminder is kept is owner question Q26.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return bool
 */
function reminder_sending_tracked(int $rykkerId): bool {
	if (tbl_exists('reminder_automation_jobs')) {
		$job = db_fetch_array(db_select("select state from reminder_automation_jobs where order_id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__));
		if ($job && in_array(trim((string)$job['state']), array('sent', 'sending', 'failed'), true)) return true;
		if ($job && reminder_automation_attempt_unresolved($rykkerId)) return true; // 20261127 ASR Review AR3-1: the shared predicate
	}
	if (reminder_link_table() && db_fetch_array(db_select("select rykker_id from stripe_reminders where rykker_id = " . (int)$rykkerId . "
		and (mailed_at is not null or status in ('sent', 'charged'))", __FILE__ . " linje " . __LINE__))) return true;
	return false;
}

/**
 * Delete a reminder that the automatic flow and the link reminders do not track as sent (reminder_sending_tracked()) with its
 * lines, the day it was printed or mailed (reminder_dispatch), its draft link reminder row (stripe_reminder_unregister()) and
 * the automatic flow's job for it. The job's removal is logged as the event 'deleted', so the flow's page lists no job for a
 * reminder that is gone, and the previous level is not held by a job that has no reminder.
 * The caller owns the transaction.
 *
 * @param int $rykkerId ordrer.id of the reminder (art R1-R3).
 * @param string $actor Who deleted it, for the flow's event.
 * @param string $why The event's detail.
 * @return void
 */
function reminder_delete_draft(int $rykkerId, string $actor, string $why): void {
	db_modify("delete from ordrelinjer where ordre_id in (select id from ordrer where id = " . (int)$rykkerId . " and art like 'R%')", __FILE__ . " linje " . __LINE__);
	db_modify("delete from ordrer where id = " . (int)$rykkerId . " and art like 'R%'", __FILE__ . " linje " . __LINE__);
	if (reminder_dispatch_table()) db_modify("delete from reminder_dispatch where order_id = " . (int)$rykkerId . " and not exists (select 1 from ordrer where id = " . (int)$rykkerId . ")", __FILE__ . " linje " . __LINE__);
	if (function_exists('stripe_reminder_unregister')) stripe_reminder_unregister($rykkerId);
	if (!tbl_exists('reminder_automation_jobs')) return;
	if (!db_fetch_array(db_select("select order_id from reminder_automation_jobs where order_id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__))) return;
	db_modify("delete from reminder_automation_jobs where order_id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__);
	if (tbl_exists('reminder_automation_events')) {
		db_modify("insert into reminder_automation_events (order_id, action, actor, detail) values (" . (int)$rykkerId . ", 'deleted', '"
			. db_escape_string($actor) . "', '" . db_escape_string($why) . "')", __FILE__ . " linje " . __LINE__);
	}
}

/**
 * The previous level of the same reminder chain (same ordrenr and account): the newest one created before this
 * reminder, so a level escalated again later never counts as this reminder's previous one.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return int ordrer.id of the previous level, 0 for level 1 or none.
 */
function reminder_previous_id($rykkerId) {
	$c = reminder_charge_lines($rykkerId);
	if (!$c || $c['level'] <= 1) return 0;
	$r = db_fetch_array(db_select("select id from ordrer where ordrenr = " . (int)$c['ordrenr'] . " and konto_id = " . (int)$c['konto_id'] . "
		and art = 'R" . ($c['level'] - 1) . "' and id < " . (int)$rykkerId . " order by id desc", __FILE__ . " linje " . __LINE__));
	return $r ? (int)$r['id'] : 0;
}

/**
 * When the previous level of a reminder was sent (L3).
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return string Y-m-d, or '' for a 1st reminder or when the previous level is gone.
 */
function reminder_previous_sent_date(int $rykkerId): string {
	$previous = reminder_previous_id($rykkerId);
	return $previous ? reminder_sent_date($previous) : '';
}

/**
 * L1 at creation: a fee above 100,00 kr. is set down to it. Other currencies are converted with the reminder's
 * rate; without a rate L1 is skipped.
 *
 * @param float $fee Fee in the reminder's currency.
 * @param string $valuta Reminder currency ('' = DKK).
 * @param float $valutakurs Reminder rate (DKK per 100 units).
 * @return array{fee: float, capped: bool} The fee to use and whether it was set down.
 */
function reminder_legal_cap_fee(float $fee, string $valuta, float $valutakurs): array {
	$valuta = strtoupper(trim($valuta)) ?: 'DKK';
	if ($valuta === 'DKK') {
		$cap = reminder_fee_cap_kr();
	} elseif ($valutakurs > 0) {
		$cap = floor(reminder_fee_cap_kr() * 10000 / $valutakurs) / 100; // never above 100 kr. after conversion
	} else {
		return array('fee' => $fee, 'capped' => false);
	}
	if (round($fee, 2) > round($cap, 2)) return array('fee' => round($cap, 2), 'capped' => true);
	return array('fee' => $fee, 'capped' => false);
}

/**
 * Staff notice when ny_rykker.php set a fee down to the cap (L1).
 *
 * @param float $fee The capped fee in the reminder's currency.
 * @param string $valuta Reminder currency ('' = DKK).
 * @return string "Rykkergebyret er sat ned til 100,00 kr. (renteloven § 9 b)."
 */
function reminder_cap_notice($fee, $valuta) {
	$valuta = strtoupper(trim((string)$valuta)) ?: 'DKK';
	return reminder_txt(8103, 'Rykkergebyret er sat ned til {gebyr} (renteloven § 9 b).',
		array('gebyr' => reminder_fmt_kr($fee) . ($valuta === 'DKK' ? ' kr.' : ' ' . $valuta)));
}

/**
 * L3 message for a reminder of $level when the previous one was sent on $sent, or '' when it may go now.
 *
 * @param int $level Level of the new reminder (2 or 3).
 * @param string $sent Y-m-d the previous level was sent; '' = unknown (no check).
 * @param string $today Y-m-d.
 * @return string Danish staff message or ''.
 */
function reminder_l3_message($level, $sent, $today) {
	if ($sent === '') return '';
	$earliest = reminder_date_add($sent, 10);
	if ($today >= $earliest) return '';
	return reminder_txt(8106, '{niveau}. rykker kan tidligst sendes den {dato} (mindst 10 dage efter forrige rykker, renteloven § 9 b).',
		array('niveau' => (int)$level, 'dato' => reminder_fmt_date($earliest)));
}

/**
 * Renteloven § 9 b across reminder chains: the reminders with a fee made before this one for the invoices it lists.
 * Closing a chain (Afslut, or "Ryk alle" after the next level) does not pay the invoice, and a new chain can be made for
 * it, so the fees on at most 3 reminders (L2) and the 10 days (L3) are counted per invoice over every chain. An invoice
 * is its positive open items with the same number on the account; the fee of an earlier reminder (an open item whose
 * refnr is a reminder) is not an invoice. An automatic reminder that was not sent does not count, nor does a reminder made
 * while Saldi records the day reminders go out that was never mailed, printed or booked (reminder_claim_sent_date()); a
 * reminder made before that counts from its date.
 * When a reminder is made, and when one that already counts is sent again, only the reminders made before it count. When
 * it goes out for the first time ($firstDispatch, reminder_claim_first_dispatch()), every other reminder for its invoices
 * that went out or whose fee was booked counts, also one made after it: an older draft sent after a later chain would
 * otherwise claim a fee the invoice no longer allows, or come less than 10 days after the newest one.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @param bool $firstDispatch The reminder is about to go out for the first time: the other reminders count whatever their id.
 *   false: only reminders made before it (a lower id) count.
 * @return array{count: int, invoice: string, last_sent: string, last_invoice: string}
 *   count: the most of those reminders with a fee for one of its invoices; invoice: that invoice's number;
 *   last_sent: Y-m-d the newest of those reminders was sent ('' = none); last_invoice: its invoice's number.
 */
function reminder_claim_fee_history(int $rykkerId, bool $firstDispatch = false): array {
	$out = array('count' => 0, 'invoice' => '', 'last_sent' => '', 'last_invoice' => '');
	$c = reminder_charge_lines($rykkerId);
	if (!$c) return $out;
	$kontoId = (int)$c['konto_id'];
	$firstTracked = reminder_dispatch_first_id();   // 20261126 ASR Review GPT-G-7
	$sentWithFee = array();   // reminder id => its sending date (with a fee) or null (no fee, or not sent)
	foreach (reminder_openpost_ids($rykkerId) as $opId) {
		$op = db_fetch_array(db_select("select konto_id, faktnr, refnr, amount from openpost where id = " . (int)$opId, __FILE__ . " linje " . __LINE__));
		if (!$op || (float)$op['amount'] <= 0 || (int)$op['konto_id'] !== $kontoId) continue;
		if ((int)$op['refnr'] > 0 && db_fetch_array(db_select("select id from ordrer where id = " . (int)$op['refnr'] . " and art like 'R%'", __FILE__ . " linje " . __LINE__))) continue;
		$faktnr = trim((string)$op['faktnr']);
		$ids = array((string)(int)$opId);
		if ($faktnr !== '') {
			$q = db_select("select id from openpost where konto_id = $kontoId and faktnr = '" . db_escape_string($faktnr) . "' and amount > 0", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) $ids[] = (string)(int)$r['id'];
		}
		$q = db_select("select distinct o.id from ordrer o, ordrelinjer l where l.ordre_id = o.id and coalesce(l.vare_id, 0) = 0
			and l.enhed in ('" . implode("','", array_unique($ids)) . "') and o.konto_id = $kontoId and o.art like 'R%' and o.id " . ($firstDispatch ? '<>' : '<') . " " . (int)$rykkerId . " order by o.id", __FILE__ . " linje " . __LINE__);   // 20261127 ASR Review GPT-H-1
		$count = 0;
		while ($r = db_fetch_array($q)) {
			$id = (int)$r['id'];
			if (!array_key_exists($id, $sentWithFee)) {
				$prior = reminder_charge_lines($id);
				$sentWithFee[$id] = ($prior && $prior['fee'] > 0.005 && !reminder_automation_unsent($id)) ? reminder_claim_sent_date($id, $firstTracked) : null;
			}
			if ($sentWithFee[$id] === null) continue;
			$count++;
			if ($sentWithFee[$id] > $out['last_sent']) {
				$out['last_sent'] = $sentWithFee[$id];
				$out['last_invoice'] = $faktnr;
			}
		}
		if ($count > $out['count']) {
			$out['count'] = $count;
			$out['invoice'] = $faktnr;
		}
	}
	return $out;
}

/**
 * Whether sending a reminder now is its first dispatch, for renteloven § 9 b at sending (reminder_legal_check()): it does not
 * count towards the fees per invoice yet. That is an automatic reminder that was not sent, and a reminder made while Saldi
 * records the day reminders go out that was neither mailed, printed nor booked (reminder_claim_sent_date() is null).
 * A reminder that already counts is sent again (a reprint, or a level booked before it went out), and keeps the check
 * against the reminders made before it. A reminder made before Saldi recorded the day always counts from its date.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return bool
 */
function reminder_claim_first_dispatch(int $rykkerId): bool {
	if (reminder_automation_unsent($rykkerId)) return true;
	return reminder_claim_sent_date($rykkerId, reminder_dispatch_first_id()) === null;
}

/**
 * L2 across chains (reminder_claim_fee_history()).
 *
 * @param array{count: int, invoice: string} $history reminder_claim_fee_history().
 * @return string The staff message when an invoice on the reminder already had fees on 3 reminders, else ''.
 */
function reminder_claim_limit_message(array $history): string {
	if ($history['count'] < 3) return '';
	return reminder_txt(8215, 'Der er allerede opkrævet rykkergebyr for 3 rykkere for faktura {faktura} (renteloven § 9 b).',
		array('faktura' => $history['invoice']));
}

/**
 * L3 across chains (reminder_claim_fee_history()).
 *
 * @param array{last_sent: string, last_invoice: string} $history reminder_claim_fee_history().
 * @param string $today Y-m-d.
 * @return string The staff message when the newest reminder with a fee for one of its invoices was sent less than 10 days before $today, else ''.
 */
function reminder_claim_spacing_message(array $history, string $today): string {
	if ($history['last_sent'] === '') return '';
	$earliest = reminder_date_add($history['last_sent'], 10);
	if ($today >= $earliest) return '';
	return reminder_txt(8216, 'Faktura {faktura} fik en rykker med gebyr den {sendt}. En ny rykker med gebyr kan tidligst sendes den {dato} (renteloven § 9 b).',
		array('faktura' => $history['last_invoice'], 'sendt' => reminder_fmt_date($history['last_sent']), 'dato' => reminder_fmt_date($earliest)));
}

/**
 * Whether a reminder that is being made may get a fee, by the reminders made before it for its invoices in any chain
 * (L2 and L3 across chains). reminder_create_items() leaves the fee off when it may not.
 *
 * @param int $rykkerId ordrer.id of the reminder being made, with its invoice lines.
 * @param string $today Y-m-d of the reminder.
 * @return string '' when it may, else the staff message.
 */
function reminder_claim_fee_refusal(int $rykkerId, string $today): string {
	$history = reminder_claim_fee_history($rykkerId);
	$limit = reminder_claim_limit_message($history);
	return $limit !== '' ? $limit : reminder_claim_spacing_message($history, $today);
}

/**
 * Renteloven § 9 b for an existing reminder, checked before it is sent (classic and link).
 * L1: the fee lines (not interest - interest is not a fee) at most 100,00 kr.; L2: no fee on a level above 3, nor
 * when an invoice on it already had fees on 3 reminders in any chain; L3: a 2nd/3rd reminder WITH a fee at least
 * 10 days after the previous level was sent, and any reminder WITH a fee at least 10 days after the newest reminder
 * with a fee for one of its invoices was sent. A reminder without a fee is never blocked by L2 or L3.
 * At a reminder's first dispatch (reminder_claim_first_dispatch()) the invoices' reminders made after it count too, when
 * they went out or their fee was booked (reminder_claim_fee_history()). The caller that sends it runs this check and the
 * record of its dispatch under reminder_dispatch_locked().
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @param string $today Y-m-d; '' = today.
 * @return string '' when it may be sent, else the Danish staff message (without "Rykker x er ikke sendt:").
 */
function reminder_legal_check(int $rykkerId, string $today = ''): string {
	$c = reminder_charge_lines($rykkerId);
	if (!$c) return '';
	$today = $today !== '' ? $today : reminder_today();
	$hasFee = $c['fee'] > 0.005;
	if ($hasFee) {
		$feeKr = null;
		if ($c['valuta'] === 'DKK') $feeKr = $c['fee'];
		elseif ($c['valutakurs'] > 0) $feeKr = $c['fee'] * $c['valutakurs'] / 100;
		if ($feeKr !== null && (int)round($feeKr * 100) > (int)round(reminder_fee_cap_kr() * 100)) {
			return reminder_txt(8104, 'Rykkergebyret er {gebyr} kr., men må højst være 100,00 kr. pr. rykker (renteloven § 9 b).',
				array('gebyr' => reminder_fmt_kr($feeKr)));
		}
	}
	if ($hasFee && $c['level'] > 3) {
		return reminder_txt(8105, 'Der må højst opkræves rykkergebyr for 3 rykkere (renteloven § 9 b).');
	}
	if ($hasFee) {
		// 20261126 ASR Review GPT-R-3: the invoices' earlier reminders with a fee in any chain count too
		// 20261127 ASR Review GPT-H-1: at its first dispatch, also the ones made after it that went out or were booked
		$history = reminder_claim_fee_history($rykkerId, reminder_claim_first_dispatch($rykkerId));
		$limit = reminder_claim_limit_message($history);
		if ($limit !== '') return $limit;
		$l3 = ($c['level'] >= 2) ? reminder_l3_message($c['level'], reminder_previous_sent_date($rykkerId), $today) : '';
		return $l3 !== '' ? $l3 : reminder_claim_spacing_message($history, $today);
	}
	return '';
}

/**
 * L3 when ny_rykker.php escalates a reminder: may the next level be created now? Only when the next level gets
 * a fee (its fee item has a price); a reminder without a fee is never blocked.
 *
 * @param int $prevRykkerId ordrer.id of the reminder that is escalated (the current level).
 * @param string $today Y-m-d; '' = today.
 * @return string '' when the next level may be created, else the Danish staff message.
 */
function reminder_escalation_check($prevRykkerId, $today = '') {
	$c = reminder_charge_lines((int)$prevRykkerId);
	if (!$c) return '';
	$next = $c['level'] + 1;
	if ($next > 3 || reminder_level_fee($next) <= 0) return '';
	return reminder_l3_message($next, reminder_sent_date((int)$prevRykkerId), $today !== '' ? $today : reminder_today());
}

/**
 * Whether a reminder's date (fakturadate, fallback ordredate) lies in the active fiscal year ($regnaar, grupper RA
 * box2/box1 to box4/box3): the check "Bogfør" makes before it posts a reminder (includes/rapportfunc.php
 * bogfor_rykker(), 20121106), for "Ryk alle", which posts through includes/openpost.php. Not checked without an
 * active fiscal year.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return bool
 */
function reminder_in_active_year($rykkerId) {
	global $regnaar;
	if (!isset($regnaar) || trim((string)$regnaar) === '') return true;
	$ra = db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art = 'RA' and kodenr = '" . db_escape_string(trim((string)$regnaar)) . "'", __FILE__ . " linje " . __LINE__));
	if (!$ra) return true;
	$o = db_fetch_array(db_select("select fakturadate, ordredate from ordrer where id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__));
	$date = $o ? (trim((string)$o['fakturadate']) ?: trim((string)$o['ordredate'])) : '';
	if (!preg_match('/^(\d{4})-(\d{2})/', $date, $m)) return true;
	$ym = $m[1] . $m[2];
	$start = str_replace(' ', '', trim((string)$ra['box2']) . $ra['box1']);
	$end = str_replace(' ', '', trim((string)$ra['box4']) . $ra['box3']);
	return $ym >= $start && $ym <= $end;
}

/**
 * The fiscal year a reminder is posted in (grupper RA kodenr). Its item and debtor groups (grupper.fiscal_year) hold the
 * posting accounts, as invoice posting reads them for its year (includes/ordrefunc.php). It is the active year ($regnaar),
 * as both bogfor_rykker() copies post only a reminder dated in it (the automatic run sets it to the reminder's year);
 * without an active year, the year whose period holds the reminder's date.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return int RA kodenr, 0 when none is known.
 */
function reminder_posting_year($rykkerId): int {
	global $regnaar;
	if (isset($regnaar) && (int)$regnaar > 0) return (int)$regnaar;
	$o = db_fetch_array(db_select("select fakturadate, ordredate from ordrer where id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__));
	$date = $o ? (trim((string)$o['fakturadate']) ?: trim((string)$o['ordredate'])) : '';
	if (!preg_match('/^(\d{4})-(\d{2})/', $date, $m)) return 0;
	$ym = $m[1] . $m[2];
	$q = db_select("select kodenr, box1, box2, box3, box4 from grupper where art = 'RA' order by kodenr", __FILE__ . " linje " . __LINE__);
	while ($ra = db_fetch_array($q)) {
		$start = str_replace(' ', '', trim((string)$ra['box2']) . $ra['box1']);
		$end = str_replace(' ', '', trim((string)$ra['box4']) . $ra['box3']);
		if ($ym >= $start && $ym <= $end) return (int)$ra['kodenr'];
	}
	return 0;
}

/**
 * An item or debtor group's row of one fiscal year (grupper has one row per group and year), as invoice posting reads it
 * (includes/ordrefunc.php: fiscal_year = $regnaar). Another year's row is never used.
 *
 * @param string $art 'VG' (item group) or 'DG' (debtor group).
 * @param string $kodenr The group number (varer.gruppe or adresser.gruppe).
 * @param int $year RA kodenr of the fiscal year.
 * @return array|null The grupper row, or null when the group has no row in that year.
 */
function reminder_posting_group(string $art, string $kodenr, int $year): ?array {
	$kodenr = trim($kodenr);
	if (!in_array($art, array('VG', 'DG'), true) || $year <= 0 || !ctype_digit($kodenr)) return null;
	$r = db_fetch_array(db_select("select * from grupper where art = '$art' and kodenr = '" . (int)$kodenr . "' and fiscal_year = " . (int)$year . " order by id", __FILE__ . " linje " . __LINE__));
	return $r ?: null;
}

/** @return string A fiscal year's name for staff (grupper RA beskrivelse, e.g. 2026 or 2025/2026), else its number. */
function reminder_fiscal_year_name(int $year): string {
	$r = db_fetch_array(db_select("select beskrivelse from grupper where art = 'RA' and kodenr = '" . (int)$year . "'", __FILE__ . " linje " . __LINE__));
	return ($r && trim((string)$r['beskrivelse']) !== '') ? trim((string)$r['beskrivelse']) : (string)(int)$year;
}

/**
 * The groups a reminder's fee is posted with, all of its posting year: the item group of every fee or interest line
 * (ordrelinjer with an item) and the customer's debtor group. Both bogfor_rykker() copies (Bogfør, "Ryk alle") and the
 * automatic run (ReminderAutomation::bookPrevious()) check them before anything is written, and post with these rows.
 * When the year is unknown or one of the groups has no row in it, the reminder is not posted at all: no other year's
 * accounts are used.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @param int $year RA kodenr; 0 = reminder_posting_year().
 * @return array{year: int, items: array<int, array>, debtor: array|null, refusal: string}
 *   items: ordrelinjer.id => the line item's grupper VG row; debtor: the customer's grupper DG row;
 *   refusal: '' when every row is there, else why the fee cannot be posted, as a clause for reminder_txt(8108) and reminder_skip_notice().
 */
function reminder_posting_groups(int $rykkerId, int $year = 0): array {
	$year = $year > 0 ? $year : reminder_posting_year($rykkerId);
	$out = array('year' => $year, 'items' => array(), 'debtor' => null, 'refusal' => '');
	if ($year <= 0) {
		$out['refusal'] = reminder_txt(8111, 'rykkerdatoen ligger uden for det aktive regnskabsår.');
		return $out;
	}
	$missing = array();
	$q = db_select("select l.id, v.gruppe from ordrelinjer l left join varer v on v.id = l.vare_id
		where l.ordre_id = " . (int)$rykkerId . " and l.vare_id > 0 order by l.id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$gruppe = trim((string)$r['gruppe']);
		$row = reminder_posting_group('VG', $gruppe, $year);
		if ($row) $out['items'][(int)$r['id']] = $row;
		else $missing['VG' . $gruppe] = reminder_txt(8228, 'varegruppe {nr}', array('nr' => $gruppe !== '' ? $gruppe : '?'));
	}
	$a = db_fetch_array(db_select("select a.gruppe from ordrer o left join adresser a on a.id = o.konto_id where o.id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__));
	$gruppe = trim((string)($a['gruppe'] ?? ''));
	$out['debtor'] = reminder_posting_group('DG', $gruppe, $year);
	if (!$out['debtor']) $missing['DG' . $gruppe] = reminder_txt(8229, 'debitorgruppe {nr}', array('nr' => $gruppe !== '' ? $gruppe : '?'));
	if ($missing) {
		$out['refusal'] = reminder_txt(8227, 'rykkergebyret kan ikke bogføres, da regnskabsår {aar} mangler {grupper}. Opret {grupper} i regnskabsår {aar} under Indstillinger, og bogfør rykkeren igen.',
			array('aar' => reminder_fiscal_year_name($year), 'grupper' => implode(', ', $missing)));
	}
	return $out;
}

/**
 * Why "Ryk alle" or "Ny rykker" does not make a reminder's next level: its date is outside the active fiscal year
 * (only when "Ryk alle" would book it, as "Bogfør" refuses it; a link reminder whose fee goes on the next invoice posts
 * nothing and is not held), every invoice on it is paid ("Ryk alle" still books it, as the fee of a sent reminder is
 * owed; Afslut closes it), or renteloven § 9 b (L3) holds the next level back.
 *
 * @param int $rykkerId ordrer.id of the reminder that would be escalated.
 * @param bool $book Whether it would be booked first ("Ryk alle").
 * @param string $today Y-m-d; '' = today.
 * @return string '' = go on; 'unsent' (an automatic reminder that was not sent), 'year' (not booked), 'paid' (booked by
 *                "Ryk alle", not escalated) or the L3 message (worded by reminder_skip_notice()).
 */
function reminder_escalation_skip($rykkerId, $book, $today = '') {
	if (reminder_automation_unsent((int)$rykkerId)) return 'unsent'; // 20261126 ASR Review I3
	$carried = function_exists('stripe_reminder_carried') && stripe_reminder_carried((int)$rykkerId);
	if ($book && !$carried && !reminder_in_active_year((int)$rykkerId)) return 'year';
	$payment = reminder_invoice_payment((int)$rykkerId);
	if ($payment['invoices'] > 0 && $payment['unpaid'] === 0) return 'paid';
	return reminder_escalation_check((int)$rykkerId, $today);
}

/**
 * The staff notice when "Ryk alle" or "Ny rykker" leaves a reminder: "Ingen ny rykker for 17-1: <reason>".
 *
 * @param string $rykkerNr The reminder's number (ordrer.fakturanr).
 * @param string $reason paid (every invoice on it is paid), year (its date is outside the active fiscal year), unsent (an
 *                       automatic reminder that was not sent) or a ready text (the L3 message).
 * @param int $rykkerId ordrer.id of the reminder, for the wording of 'unsent', which follows its job's state; 0 = a draft's.
 * @return string
 */
function reminder_skip_notice($rykkerNr, $reason, int $rykkerId = 0) {
	if ($reason === 'unsent') {
		$reason = reminder_automation_unsent_text($rykkerId);
	} elseif ($reason === 'paid') {
		$reason = reminder_txt(8109, 'alle fakturaer på rykkeren er betalt. Luk den med Afslut.');
	} elseif ($reason === 'year') {
		$reason = reminder_txt(8111, 'rykkerdatoen ligger uden for det aktive regnskabsår.');
	}
	return reminder_txt(8107, 'Ingen ny rykker for {rykker}: {tekst}', array('rykker' => $rykkerNr, 'tekst' => $reason));
}
