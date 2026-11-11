<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/reminderLegal.php --- ver 5.0.0 --- 2026.11.09 ---
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
 * When a reminder was sent: the day it was mailed (stripe_reminders.mailed_at) when Saldi knows it, otherwise its
 * date (fakturadate, fallback ordredate).
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return string Y-m-d, or '' when the reminder does not exist.
 */
function reminder_sent_date($rykkerId) {
	if (reminder_link_table()) {
		$s = db_fetch_array(db_select("select mailed_at from stripe_reminders where rykker_id = " . (int)$rykkerId . " and mailed_at is not null", __FILE__ . " linje " . __LINE__));
		if ($s && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$s['mailed_at'])) return substr((string)$s['mailed_at'], 0, 10);
	}
	$o = db_fetch_array(db_select("select fakturadate, ordredate from ordrer where id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__));
	if (!$o) return '';
	$date = trim((string)$o['fakturadate']) ?: trim((string)$o['ordredate']);
	return preg_match('/^\d{4}-\d{2}-\d{2}/', $date) ? substr($date, 0, 10) : '';
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
 * Renteloven § 9 b for an existing reminder, checked before it is sent (classic and link).
 * L1: the fee lines (not interest - interest is not a fee) at most 100,00 kr.; L2: no fee on a level above 3;
 * L3: a 2nd/3rd reminder WITH a fee at least 10 days after the previous level was sent. A reminder without a
 * fee is never blocked by L3.
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
	if ($hasFee && $c['level'] >= 2) {
		return reminder_l3_message($c['level'], reminder_previous_sent_date($rykkerId), $today);
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
 * Why "Ryk alle" or "Ny rykker" leaves a reminder as it is instead of booking it (only "Ryk alle") and making its next
 * level: every invoice on it is paid (Afslut closes it), its date is outside the active fiscal year (only when it
 * would be booked, as "Bogfør" refuses it) or renteloven § 9 b (L3) holds the next level back.
 *
 * @param int $rykkerId ordrer.id of the reminder that would be escalated.
 * @param bool $book Whether it would be booked first ("Ryk alle").
 * @param string $today Y-m-d; '' = today.
 * @return string '' = go on; 'paid', 'year' or the L3 message (worded by reminder_skip_notice()).
 */
function reminder_escalation_skip($rykkerId, $book, $today = '') {
	$payment = reminder_invoice_payment((int)$rykkerId);
	if ($payment['invoices'] > 0 && $payment['unpaid'] === 0) return 'paid';
	if ($book && !reminder_in_active_year((int)$rykkerId)) return 'year';
	return reminder_escalation_check((int)$rykkerId, $today);
}

/**
 * The staff notice when "Ryk alle" or "Ny rykker" leaves a reminder: "Ingen ny rykker for 17-1: <reason>".
 *
 * @param string $rykkerNr The reminder's number (ordrer.fakturanr).
 * @param string $reason paid (every invoice on it is paid), year (its date is outside the active fiscal year) or a
 *                       ready text (the L3 message).
 * @return string
 */
function reminder_skip_notice($rykkerNr, $reason) {
	if ($reason === 'paid') {
		$reason = reminder_txt(8109, 'alle fakturaer på rykkeren er betalt. Luk den med Afslut.');
	} elseif ($reason === 'year') {
		$reason = reminder_txt(8111, 'rykkerdatoen ligger uden for det aktive regnskabsår.');
	}
	return reminder_txt(8107, 'Ingen ny rykker for {rykker}: {tekst}', array('rykker' => $rykkerNr, 'tekst' => $reason));
}
