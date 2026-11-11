<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeReminder.php --- ver 5.0.0 --- 2026.11.09 ---
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
// 20261109 CL/ASR Created. Link reminders ("Linkrykker"): a reminder (R1/R2/R3) of a customer whose newest reminded invoice was sent as "E-mail med link" or card on file is mailed like the invoice link mail, with a "Betal" link per invoice to the existing payment page (stripeReminderMail.php).
//                 This file is the data side: which reminders are link reminders (table stripe_reminders, one row per R order), the settings (var_grp stripe, reminder_*), and the reminder fee and interest, which for a link reminder are not posted as an open item but go as VAT-free lines on the customer's next invoice (bogfor()), or are waived when the customer turned on automatic payment after the reminder.
//                 Lightweight: no Stripe HTTP, and inert in ledgers without the table (classic reminders as before).
// 20261109 CL/ASR Review fixes: the fee of an earlier classic level of the chain is a prior fee, not unmapped (D1); a booked draft is never made classic again; a re-send keeps the first mailed_at (D2); the card-saved waiver compares Copenhagen days.
//                 The fee and interest charged on the next invoice are at most those of the reminder's current lines (a deleted line is not charged, nothing left = waived); never on a cash or card sale.
//                 New: stripe_reminder_refresh_amounts(), stripe_reminder_carried_rows() (payment page), stripe_reminder_waivable() and stripe_reminder_waive_request() ("Frafald rykkergebyr" in rykker.php).

include_once(__DIR__ . '/../reminderLegal.php');

/** @return void Log a line in the Stripe log when it is loaded, else in PHP's error log. Never throws. */
function stripe_reminder_log($line) {
	if (function_exists('stripe_log')) stripe_log('reminder: ' . $line);
	else error_log('Saldi reminder: ' . $line);
}

/** @return string CREATE TABLE for stripe_reminders (PostgreSQL and MySQL), used by both ensure functions. */
function stripe_reminder_table_ddl(): string {
	return "CREATE TABLE IF NOT EXISTS stripe_reminders (
		rykker_id integer PRIMARY KEY, konto_id integer NOT NULL, ordrenr integer NOT NULL, level smallint NOT NULL,
		status varchar(10) NOT NULL DEFAULT 'draft', fee_mode varchar(8) NOT NULL DEFAULT 'next', channel varchar(8),
		mailed_at timestamp NULL, mailed_to text, fee_ore integer NOT NULL DEFAULT 0, interest_ore integer NOT NULL DEFAULT 0,
		fee_ordre_id integer, waived_reason varchar(20), created_at timestamp DEFAULT CURRENT_TIMESTAMP)";
}

/** @return bool Whether stripe_reminders exists. Only a positive answer is cached, so a table created later is seen. */
function stripe_reminder_table(): bool {
	static $exists = false;
	if (!$exists) $exists = (bool)tbl_exists('stripe_reminders');
	return $exists;
}

/** @return void Create stripe_reminders when it is missing (also done by stripe_charge_schema_ensure() at login). Never inside bogfor(). */
function stripe_reminder_ensure_table(): void {
	if (stripe_reminder_table()) return;
	db_modify(stripe_reminder_table_ddl(), __FILE__ . " linje " . __LINE__);
}

/**
 * The link reminder settings (Indstillinger > Salg > Betalingsbetingelser & rykkere > Rykker med betalingslink),
 * read straight from settings (var_grp 'stripe'), so they work whether or not Stripe is set up. A missing or empty
 * row means the default.
 *
 * @return array{
 *   format: string,        'auto' (default: follow the newest reminded invoice's "Udskriv til") or 'classic'.
 *   link_email: bool,      Link reminder also when that invoice was a classic e-mail (default off).
 *   fee_next: bool,        Fee and interest go on the next invoice instead of an open item (default on).
 *   waive: bool,           Waive the carried fee when the customer turns on automatic payment (default on).
 *   close_warning: bool,   Level 3 carries the close warning (default off).
 *   close_text: string,    The warning; {frist} = the reminder's pay-by date.
 * }
 */
function stripe_reminder_settings(): array {
	$values = array();
	$q = db_select("select var_name, var_value from settings where var_grp = 'stripe' and var_name in ('reminder_format',
		'reminder_link_email', 'reminder_fee_next', 'reminder_fee_waive', 'reminder_close_warning', 'reminder_close_text') order by id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (trim((string)$r['var_value']) !== '') $values[$r['var_name']] = (string)$r['var_value'];
	}
	$flag = function ($name, $default) use ($values) {
		return isset($values[$name]) ? trim($values[$name]) === 'on' : $default;
	};
	return array(
		'format' => trim($values['reminder_format'] ?? '') === 'classic' ? 'classic' : 'auto',
		'link_email' => $flag('reminder_link_email', false),
		'fee_next' => $flag('reminder_fee_next', true),
		'waive' => $flag('reminder_fee_waive', true),
		'close_warning' => $flag('reminder_close_warning', false),
		'close_text' => trim($values['reminder_close_text'] ?? '') !== '' ? trim($values['reminder_close_text'])
			: 'Betales beløbet ikke senest {frist}, kan vi lukke for din adgang uden yderligere varsel.',
	);
}

/**
 * @param int $rykkerId ordrer.id of the reminder.
 * @return array{
 *   rykker_id: int, konto_id: int, ordrenr: int, level: int,
 *   status: string,        draft|sent|charged|waived.
 *   fee_mode: string,      next|classic.
 *   channel: string,       link|classic|'' (how it was sent).
 *   mailed_at: string, mailed_to: string, fee_ore: int, interest_ore: int,
 *   fee_ordre_id: int,     The invoice that carried the fee, 0 when none.
 *   waived_reason: string, created_at: string,
 * }|null The stripe_reminders row; null when none (classic reminder) or no table.
 */
function stripe_reminder_row(int $rykkerId): ?array {
	if ($rykkerId <= 0 || !stripe_reminder_table()) return null;
	$r = db_fetch_array(db_select("select * from stripe_reminders where rykker_id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__));
	if (!$r) return null;
	return array(
		'rykker_id' => (int)$r['rykker_id'], 'konto_id' => (int)$r['konto_id'], 'ordrenr' => (int)$r['ordrenr'],
		'level' => (int)$r['level'], 'status' => trim((string)$r['status']), 'fee_mode' => trim((string)$r['fee_mode']),
		'channel' => trim((string)$r['channel']), 'mailed_at' => (string)$r['mailed_at'], 'mailed_to' => (string)$r['mailed_to'],
		'fee_ore' => (int)$r['fee_ore'], 'interest_ore' => (int)$r['interest_ore'], 'fee_ordre_id' => (int)$r['fee_ordre_id'],
		'waived_reason' => trim((string)$r['waived_reason']), 'created_at' => (string)$r['created_at'],
	);
}

/** @return array{id: int, varenr: string, beskrivelse: string, salgspris: float, gruppe: string}|null An item, null when missing. */
function stripe_reminder_vare($vareId) {
	if ((int)$vareId <= 0) return null;
	$v = db_fetch_array(db_select("select id, varenr, beskrivelse, salgspris, gruppe from varer where id = " . (int)$vareId, __FILE__ . " linje " . __LINE__));
	if (!$v) return null;
	return array('id' => (int)$v['id'], 'varenr' => (string)$v['varenr'], 'beskrivelse' => (string)$v['beskrivelse'],
		'salgspris' => (float)$v['salgspris'], 'gruppe' => (string)$v['gruppe']);
}

/**
 * What a reminder consists of. Each comment line with an openpost id (ordrelinjer.enhed) is mapped to its invoice
 * (ordrer art DO with the same account and fakturanr = openpost.faktnr); negative open items are credits; the fee
 * of an earlier level of the same chain posted as an open item (fee_mode classic, reminder_prior_fee_level()) is a
 * prior fee; any other positive item without exactly one invoice (an opening balance, a manual entry, another
 * reminder's fee) is unmapped. Item lines are the fee and interest (reminder_charge_lines()).
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return array{
 *   invoices: array<int, array{ordre_id: int, openpost_id: int, fakturanr: string, amount: float, forfaldsdate: string}>,
 *                    Oldest due date first; amount = the open item's amount, forfaldsdate Y-m-d (transdate when none).
 *   credits: array<int, array{openpost_id: int, amount: float}>,  Negative open items (amount < 0).
 *   prior_fees: array<int, array{openpost_id: int, faktnr: string, amount: float, level: int}>,
 *                    Fee open items of earlier levels of the chain; faktnr is that reminder's number, level its level.
 *   unmapped: int,   Positive open items that are no single invoice and no prior fee (also items that no longer exist).
 *   fee: float,      Fee lines in the reminder's currency.
 *   interest: float, Interest lines in the reminder's currency.
 *   fee_vare: array{id: int, varenr: string, beskrivelse: string, salgspris: float, gruppe: string}|null,
 *   interest_vare: array{id: int, varenr: string, beskrivelse: string, salgspris: float, gruppe: string}|null,
 * }
 */
function stripe_reminder_lines(int $rykkerId): array {
	$out = array('invoices' => array(), 'credits' => array(), 'prior_fees' => array(), 'unmapped' => 0, 'fee' => 0.0, 'interest' => 0.0, 'fee_vare' => null, 'interest_vare' => null);
	$c = reminder_charge_lines($rykkerId);
	if (!$c) return $out;
	$out['fee'] = $c['fee'];
	$out['interest'] = $c['interest'];
	$out['fee_vare'] = stripe_reminder_vare($c['fee_vare_id']);
	$out['interest_vare'] = stripe_reminder_vare($c['interest_vare_id']);
	foreach (reminder_openpost_ids($rykkerId) as $opId) {
		$op = db_fetch_array(db_select("select id, konto_id, faktnr, refnr, amount, forfaldsdate, transdate from openpost where id = " . (int)$opId, __FILE__ . " linje " . __LINE__));
		if (!$op) {
			$out['unmapped']++;
			continue;
		}
		if ((float)$op['amount'] < 0) {
			$out['credits'][] = array('openpost_id' => (int)$op['id'], 'amount' => (float)$op['amount']);
			continue;
		}
		$do = array();
		$faktnr = trim((string)$op['faktnr']);
		if ($faktnr !== '') {
			$q = db_select("select id, fakturanr from ordrer where konto_id = " . (int)$op['konto_id'] . " and fakturanr = '" . db_escape_string($faktnr) . "' and art = 'DO' order by id", __FILE__ . " linje " . __LINE__);
			while ($o = db_fetch_array($q)) $do[] = $o;
		}
		if (count($do) !== 1) {
			$level = reminder_prior_fee_level($op, $c);
			if ($level > 0) {
				$out['prior_fees'][] = array('openpost_id' => (int)$op['id'], 'faktnr' => $faktnr, 'amount' => (float)$op['amount'], 'level' => $level);
			} else {
				$out['unmapped']++;
			}
			continue;
		}
		$due = substr((string)($op['forfaldsdate'] ?: $op['transdate']), 0, 10);
		$out['invoices'][] = array('ordre_id' => (int)$do[0]['id'], 'openpost_id' => (int)$op['id'], 'fakturanr' => trim((string)$do[0]['fakturanr']),
			'amount' => (float)$op['amount'], 'forfaldsdate' => $due);
	}
	usort($out['invoices'], function ($a, $b) {
		return array($a['forfaldsdate'], $a['ordre_id']) <=> array($b['forfaldsdate'], $b['ordre_id']);
	});
	return $out;
}

/**
 * Whether a reminder should be a link reminder.
 * Order of the checks: the setting, the ledger (card payment of invoices on), the previous level, currency,
 * language, the open items, each invoice's card eligibility (a customer with "Ingen kortbetaling" is allowed: the
 * mail then shows the bank details), the newest invoice's "Udskriv til", an e-mail address.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return string '' = link reminder; otherwise off (setting classic), no_link (card payment of invoices not on in
 *                this ledger), prev_classic (R2/R3 whose previous level is classic), currency (not DKK), language
 *                (not Danish), unmapped (open items that are no invoice, or none), pbs (Betalingsservice),
 *                channel (newest invoice not sent as link/card - or as e-mail with reminder_link_email on - or an
 *                invoice that can never be paid by card), no_email.
 */
function stripe_reminder_eligible(int $rykkerId): string {
	$settings = stripe_reminder_settings();
	if ($settings['format'] === 'classic') return 'off';
	if (!is_file(__DIR__ . '/stripeInvoice.php')) return 'no_link';
	include_once(__DIR__ . '/stripeInvoice.php');
	if (!function_exists('stripe_invoice_link_available') || !function_exists('stripe_invoice_uncollectable_reason') || !stripe_invoice_link_available()) return 'no_link';
	$c = reminder_charge_lines($rykkerId);
	if (!$c || $c['level'] < 1 || $c['level'] > 3) return 'unmapped';
	if ($c['level'] > 1) {
		$previous = reminder_previous_id($rykkerId);
		if (!$previous || !stripe_reminder_row($previous)) return 'prev_classic';
	}
	if ($c['valuta'] !== 'DKK') return 'currency';
	if (!in_array(strtolower($c['sprog']), array('', 'dansk'), true)) return 'language';
	$lines = stripe_reminder_lines($rykkerId);
	if ($lines['unmapped'] > 0 || !$lines['invoices']) return 'unmapped';
	$newest = null;
	foreach ($lines['invoices'] as $invoice) {
		$o = db_fetch_array(db_select("select * from ordrer where id = " . (int)$invoice['ordre_id'], __FILE__ . " linje " . __LINE__));
		if (!$o) return 'unmapped';
		$reason = stripe_invoice_uncollectable_reason(array('o' => $o));
		if ($reason === 'ledger') return 'no_link';
		if ($reason === 'currency' || $reason === 'pbs') return $reason;
		if ($reason !== '' && $reason !== 'no_card') return 'channel';
		if (!$newest || array((string)$o['fakturadate'], (int)$o['id']) > array((string)$newest['fakturadate'], (int)$newest['id'])) $newest = $o;
	}
	$via = trim((string)$newest['udskriv_til']);
	if (!in_array($via, array('Link', 'Stripe'), true) && !($via === 'email' && $settings['link_email'])) return 'channel';
	if ($c['email'] === '' && trim((string)$newest['email']) === '') return 'no_email';
	return '';
}

/**
 * Make a just created reminder (R1, or R2/R3 whose previous level is a link reminder) a link reminder when it is
 * eligible: a 'draft' row with the fee mode from the settings and the fee/interest of its lines, "Send pr. mail"
 * on, and an empty e-mail filled from the newest reminded invoice's recipients. Called by ny_rykker.php after the
 * reminder's lines are complete; never throws into it.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return bool Whether the reminder is (now) a link reminder.
 */
function stripe_reminder_register(int $rykkerId): bool {
	try {
		if ($rykkerId <= 0 || stripe_reminder_eligible($rykkerId) !== '') return false;
		stripe_reminder_ensure_table();
		if (stripe_reminder_row($rykkerId)) return true;
		$c = reminder_charge_lines($rykkerId);
		$feeMode = stripe_reminder_settings()['fee_next'] ? 'next' : 'classic';
		db_modify("insert into stripe_reminders (rykker_id, konto_id, ordrenr, level, status, fee_mode, fee_ore, interest_ore) values ("
			. (int)$rykkerId . ", " . (int)$c['konto_id'] . ", " . (int)$c['ordrenr'] . ", " . (int)$c['level'] . ", 'draft', '$feeMode', "
			. (int)round($c['fee'] * 100) . ", " . (int)round($c['interest'] * 100) . ")", __FILE__ . " linje " . __LINE__);
		$set = "mail_fakt = 'on'";
		if ($c['email'] === '') {
			$newest = reminder_newest_invoice($rykkerId);
			if ($newest && $newest['email'] !== '') $set .= ", email = '" . db_escape_string($newest['email']) . "'";
		}
		db_modify("update ordrer set $set where id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__);
		return true;
	} catch (Throwable $e) {
		stripe_reminder_log('register ' . (int)$rykkerId . ': ' . $e->getMessage());
		return false;
	}
}

/**
 * Make a reminder classic again ("Send pr. mail" unticked in rykker.php, or the reminder deleted); only while it is
 * a draft that is not booked. A booked draft stays a link reminder: bogfor_rykker() posted no open item for its fee,
 * so the fee must still go on the next invoice.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return void
 */
function stripe_reminder_unregister(int $rykkerId): void {
	if ($rykkerId <= 0 || !stripe_reminder_table()) return;
	db_modify("delete from stripe_reminders where rykker_id = " . (int)$rykkerId . " and status = 'draft'
		and not exists (select 1 from ordrer where id = " . (int)$rykkerId . " and status >= 3)", __FILE__ . " linje " . __LINE__);
}

/** @return bool Whether the reminder is a link reminder whose fee and interest go on the next invoice (bogfor_rykker posts nothing). */
function stripe_reminder_carried(int $rykkerId): bool {
	$row = stripe_reminder_row($rykkerId);
	return $row !== null && $row['fee_mode'] === 'next';
}

/** @return string Now as 'Y-m-d H:i:s', Copenhagen time, on the Stripe clock's date when it is loaded (tests pin it). */
function stripe_reminder_now(): string {
	$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen'));
	return (function_exists('stripe_today') ? stripe_today() : $now->format('Y-m-d')) . ' ' . $now->format('H:i:s');
}

/**
 * Record that a link reminder was sent: status 'sent' (a charged or waived row keeps its status), how and to whom,
 * and the fee/interest of its current lines (a staff edit in rykker.php after registration counts). mailed_at is the
 * first send: a re-send keeps it, as it is the date for the 10 days of renteloven § 9 b and for the waiver.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @param string $channel 'link' (link mail) or 'classic' (PDF mail fallback).
 * @param string $mailedTo The recipients.
 * @return void
 */
function stripe_reminder_mark_sent(int $rykkerId, string $channel, string $mailedTo): void {
	$row = stripe_reminder_row($rykkerId);
	if (!$row) return;
	$channel = $channel === 'link' ? 'link' : 'classic';
	$set = "mailed_at = coalesce(mailed_at, '" . db_escape_string(stripe_reminder_now()) . "'), channel = '$channel', mailed_to = '" . db_escape_string($mailedTo) . "'";
	if (!in_array($row['status'], array('charged', 'waived'), true)) {
		$c = reminder_charge_lines($rykkerId);
		$set .= ", status = 'sent'";
		if ($c) $set .= ", fee_ore = " . (int)round($c['fee'] * 100) . ", interest_ore = " . (int)round($c['interest'] * 100);
	}
	db_modify("update stripe_reminders set $set where rykker_id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__);
}

/**
 * The latest sent link reminder that lists this invoice (for the payment page).
 *
 * @param int $ordreId ordrer.id of the invoice (DO).
 * @return array{rykker_id: int, level: int, mailed_at: string, fee_ore: int, interest_ore: int, fee_mode: string, status: string}|null
 *               Null when none was sent, or without the table. status is sent, charged or waived.
 */
function stripe_reminder_for_invoice(int $ordreId): ?array {
	if ($ordreId <= 0 || !stripe_reminder_table()) return null;
	$o = db_fetch_array(db_select("select konto_id, fakturanr, art from ordrer where id = " . (int)$ordreId, __FILE__ . " linje " . __LINE__));
	if (!$o || $o['art'] !== 'DO' || trim((string)$o['fakturanr']) === '' || (int)$o['konto_id'] <= 0) return null;
	$kontoId = (int)$o['konto_id'];
	$idText = function_exists('db_cast') ? db_cast('op.id', 'text') : 'cast(op.id as varchar(30))';
	$r = db_fetch_array(db_select("select r.rykker_id, r.level, r.mailed_at, r.fee_ore, r.interest_ore, r.fee_mode, r.status
		from stripe_reminders r, ordrer ro where ro.id = r.rykker_id and r.konto_id = $kontoId and r.mailed_at is not null
		and r.status in ('sent', 'charged', 'waived')
		and exists (select 1 from ordrelinjer l, openpost op where l.ordre_id = r.rykker_id and coalesce(l.vare_id, 0) = 0
			and l.enhed = $idText and op.konto_id = $kontoId and op.faktnr = '" . db_escape_string(trim((string)$o['fakturanr'])) . "')
		order by r.mailed_at desc, r.rykker_id desc", __FILE__ . " linje " . __LINE__));
	if (!$r) return null;
	return array('rykker_id' => (int)$r['rykker_id'], 'level' => (int)$r['level'], 'mailed_at' => (string)$r['mailed_at'],
		'fee_ore' => (int)$r['fee_ore'], 'interest_ore' => (int)$r['interest_ore'], 'fee_mode' => trim((string)$r['fee_mode']),
		'status' => trim((string)$r['status']));
}

/**
 * Waive a link reminder's fee and interest ("Frafald rykkergebyr" in rykker.php). Only before it was charged.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @param string $reason Short reason (max 20 characters), e.g. manual.
 * @return bool Whether a draft/sent row was waived.
 */
function stripe_reminder_waive(int $rykkerId, string $reason): bool {
	$row = stripe_reminder_row($rykkerId);
	if (!$row || !in_array($row['status'], array('draft', 'sent'), true)) return false;
	db_modify("update stripe_reminders set status = 'waived', waived_reason = '" . db_escape_string(substr($reason, 0, 20)) . "'
		where rykker_id = " . (int)$rykkerId . " and status in ('draft', 'sent')", __FILE__ . " linje " . __LINE__);
	return true;
}

/**
 * Staff marker for the reminder overview and rykker.php.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return string "Linkrykker · gebyr på næste faktura" / "... som åben post" / "... frafaldet"; '' for a classic reminder.
 */
function stripe_reminder_marker(int $rykkerId): string {
	$row = stripe_reminder_row($rykkerId);
	if (!$row) return '';
	if ($row['status'] === 'waived') return reminder_txt(8102, 'Linkrykker · gebyr frafaldet');
	if ($row['fee_mode'] === 'classic') return reminder_txt(8101, 'Linkrykker · gebyr som åben post');
	return reminder_txt(8100, 'Linkrykker · gebyr på næste faktura');
}

/**
 * Why a carried fee is waived at the next invoice's posting (reminder_fee_waive on): the customer pays
 * automatically now and either enrolled on or after the day the reminder was mailed (a DATE compare: enrolling
 * the same day counts) or saved/replaced a card with a payment on or after that day.
 *
 * @param int $kontoId Customer.
 * @param array $order The invoice being posted (udskriv_til, pbs).
 * @param string $mailedAt stripe_reminders.mailed_at.
 * @return string 'enrolled', 'card_saved' or '' (no waiver).
 */
function stripe_reminder_waiver_reason($kontoId, $order, $mailedAt) {
	$day = substr((string)$mailedAt, 0, 10);
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || !stripe_manual_fee_debtor_automatic((int)$kontoId, $order)) return '';
	$d = db_fetch_array(db_select("select stripe_date from adresser where id = " . (int)$kontoId, __FILE__ . " linje " . __LINE__));
	if ($d && preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$d['stripe_date']) && substr((string)$d['stripe_date'], 0, 10) >= $day) return 'enrolled';
	if (tbl_exists('stripe_payments')) {
		// By Copenhagen day like the enrolment: mailed_at is Copenhagen time, created_at the database's clock (attempt_date is
		// the Stripe clock's day).
		$p = db_fetch_array(db_select("select p.id from stripe_payments p, ordrer o where o.id = p.ordre_id and o.konto_id = " . (int)$kontoId . "
			and o.art = 'DO' and p.status = 'succeeded' and p.save_card = true and coalesce(p.attempt_date, cast(p.created_at as date)) >= '" . db_escape_string($day) . "'", __FILE__ . " linje " . __LINE__));
		if ($p) return 'card_saved';
	}
	return '';
}

/**
 * Called by bogfor() for a DO order right after the fee for manual payment, in the same transaction: the fee and
 * interest of the customer's sent, booked link reminders with fee_mode 'next' (oldest first) become VAT-free,
 * non-repeating lines on this invoice ("Rykkergebyr, rykker 17-1 (faktura 2026-0412)", "Renter, rykker 17-1 (...)")
 * and the row 'charged' - or the row 'waived' when the waiver applies. A row is taken only while it is still
 * 'sent' (locked in this transaction), so a repeated posting never charges twice. Same order guards as the fee for
 * manual payment: no credit-note-like order, no net <= 0, DKK only, a valid delivery date; and never on a cash or card
 * sale (betalingsbet Kontant/Kreditkort is paid at once, bogfor_nu() posts it straight to the cash or card account).
 * A deleted reminder order carries nothing. The amounts are at most those of the reminder's current lines
 * (stripe_reminder_refresh_amounts()). No DDL here.
 *
 * @param int $id ordrer.id of the order being posted.
 * @param string $art Order type; only DO carries a reminder fee.
 * @param float|string $momssats The order's VAT rate (the lines are VAT-free).
 * @param bool $webservice Posting context, passed to levering().
 * @param bool $genfakt Posting context, passed to levering().
 * @return string '' on success, otherwise an error for bogfor() to return (it rolls back).
 */
function stripe_reminder_fee_bogfor($id, $art, $momssats, $webservice = false, $genfakt = false) {
	if ($art !== 'DO' || !function_exists('opret_ordrelinje') || !stripe_reminder_table()) return '';
	include_once(__DIR__ . '/stripeManualFee.php');
	$order = db_fetch_array(db_select("select konto_id, levdate, ordredate, valuta, udskriv_til, pbs, betalingsbet from ordrer where id = " . (int)$id, __FILE__ . " linje " . __LINE__));
	if (!$order || (int)$order['konto_id'] <= 0 || !$order['levdate']
		|| (function_exists('delivery_date_before_order_date') && delivery_date_before_order_date('DO', $order['levdate'], $order['ordredate']))) return '';
	if ((strtoupper(trim((string)$order['valuta'])) ?: 'DKK') !== 'DKK' || !stripe_manual_fee_order_chargeable($id)) return '';
	// A cash or card sale is paid at once (also a webshop order paid at checkout): the fee waits for an invoice on account.
	if (in_array(strtolower(trim((string)$order['betalingsbet'])), array('kontant', 'kreditkort'), true)) return '';
	$kontoId = (int)$order['konto_id'];
	$rows = array();
	$q = db_select("select r.rykker_id, r.level, r.fee_ore, r.interest_ore, r.mailed_at, o.fakturanr from stripe_reminders r, ordrer o
		where o.id = r.rykker_id and r.konto_id = $kontoId and o.konto_id = $kontoId and o.art like 'R%' and o.status >= 3
		and r.status = 'sent' and r.fee_mode = 'next' and r.fee_ore + r.interest_ore > 0
		order by r.mailed_at, r.rykker_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $rows[] = $r;
	if (!$rows) return '';
	$waive = stripe_reminder_settings()['waive'];
	foreach ($rows as $row) {
		$rid = (int)$row['rykker_id'];
		$lock = db_fetch_array(db_select("select status from stripe_reminders where rykker_id = $rid for update", __FILE__ . " linje " . __LINE__));
		if (!$lock || trim((string)$lock['status']) !== 'sent') continue;
		// The reminder's lines as they are now: a fee or interest line deleted in rykker.php is not charged.
		$amounts = stripe_reminder_refresh_amounts($rid);
		if ($amounts === null) continue;
		$reason = $waive ? stripe_reminder_waiver_reason($kontoId, $order, (string)$row['mailed_at']) : '';
		if ($reason !== '') {
			db_modify("update stripe_reminders set status = 'waived', waived_reason = '$reason' where rykker_id = $rid and status = 'sent'", __FILE__ . " linje " . __LINE__);
			continue;
		}
		$lines = stripe_reminder_lines($rid);
		$items = reminder_fee_items((int)$row['level']);
		$numbers = array_values(array_unique(array_map(function ($inv) { return $inv['fakturanr']; }, $lines['invoices'])));
		$suffix = $numbers ? ' (' . (count($numbers) === 1 ? 'faktura ' : 'fakturaer ') . implode(', ', $numbers) . ')' : '';
		$rykkerNr = trim((string)$row['fakturanr']);
		$add = array();
		// The form's item only when the line is there but its item was deleted.
		if ($amounts['fee_ore'] > 0) {
			$add[] = array($lines['fee_vare'] ?: stripe_reminder_vare($items['fee'][0] ?? 0), $amounts['fee_ore'], 'Rykkergebyr, rykker ' . $rykkerNr . $suffix);
		}
		if ($amounts['interest_ore'] > 0) {
			$add[] = array($lines['interest_vare'] ?: stripe_reminder_vare($items['interest'][0] ?? 0), $amounts['interest_ore'], 'Renter, rykker ' . $rykkerNr . $suffix);
		}
		foreach ($add as $line) {
			if (!$line[0]) {
				// no item to put the amount on (the line's item and the form's item are gone): keep it for later
				stripe_reminder_log('fee_bogfor order ' . (int)$id . ': reminder ' . $rid . ' has no fee/interest item');
				continue 2;
			}
		}
		foreach ($add as $line) {
			$error = stripe_bogfor_add_fee_line($id, $line[0], 1, $line[1] / 100, $line[2], 'on', $art, $momssats, $webservice, $genfakt);
			if ($error !== '') return 'Rykkergebyr: ' . $error;
		}
		db_modify("update stripe_reminders set status = 'charged', fee_ordre_id = " . (int)$id . " where rykker_id = $rid and status = 'sent'", __FILE__ . " linje " . __LINE__);
	}
	return '';
}

/**
 * Bring a draft or sent link reminder's fee and interest (øre) down to its current lines: a line staff deleted or
 * lowered in rykker.php is not charged, and a raise after the mail is not either (the mail named the lower amount).
 * When nothing is left the row is waived (waived_reason 'removed'). Called when the reminder is booked and when its
 * fee goes on the next invoice.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @return array{fee_ore: int, interest_ore: int}|null What may be charged; null without a draft/sent row, or when
 *   nothing is left.
 */
function stripe_reminder_refresh_amounts(int $rykkerId): ?array {
	$row = stripe_reminder_row($rykkerId);
	if (!$row || !in_array($row['status'], array('draft', 'sent'), true)) return null;
	$c = reminder_charge_lines($rykkerId);
	if (!$c) return null;
	// The lines are in the reminder's currency; a link reminder is in DKK (rate 100), a later switch uses its rate.
	$rate = $c['valuta'] === 'DKK' ? 100.0 : (float)$c['valutakurs'];
	$fee = min($row['fee_ore'], max(0, (int)round($c['fee'] * $rate)));
	$interest = min($row['interest_ore'], max(0, (int)round($c['interest'] * $rate)));
	if ($fee + $interest <= 0) {
		if ($row['fee_ore'] + $row['interest_ore'] > 0) {
			db_modify("update stripe_reminders set status = 'waived', waived_reason = 'removed' where rykker_id = " . (int)$rykkerId . "
				and status in ('draft', 'sent')", __FILE__ . " linje " . __LINE__);
		}
		return null;
	}
	if ($fee !== $row['fee_ore'] || $interest !== $row['interest_ore']) {
		db_modify("update stripe_reminders set fee_ore = $fee, interest_ore = $interest where rykker_id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__);
	}
	return array('fee_ore' => $fee, 'interest_ore' => $interest);
}

/**
 * The customer's reminder fees still to come on a next invoice (for the payment page): sent link reminders with
 * fee_mode 'next' and something to charge whose reminder order still exists. An unbooked one counts: it is charged
 * once it is booked.
 *
 * @param int $kontoId Customer.
 * @return array<int, array{rykker_id: int, fee_ore: int, interest_ore: int, mailed_at: string}> Oldest first.
 */
function stripe_reminder_carried_rows(int $kontoId): array {
	$rows = array();
	if ($kontoId <= 0 || !stripe_reminder_table()) return $rows;
	$q = db_select("select r.rykker_id, r.fee_ore, r.interest_ore, r.mailed_at from stripe_reminders r, ordrer o
		where o.id = r.rykker_id and o.konto_id = " . (int)$kontoId . " and r.konto_id = " . (int)$kontoId . " and o.art like 'R%'
		and r.status = 'sent' and r.fee_mode = 'next' and r.fee_ore + r.interest_ore > 0
		order by r.mailed_at, r.rykker_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$rows[] = array('rykker_id' => (int)$r['rykker_id'], 'fee_ore' => (int)$r['fee_ore'], 'interest_ore' => (int)$r['interest_ore'],
			'mailed_at' => (string)$r['mailed_at']);
	}
	return $rows;
}

/**
 * Whether "Frafald rykkergebyr" applies (rykker.php): a link reminder whose fee and interest go on the next invoice,
 * sent (or booked), not charged or waived yet, with something to waive.
 *
 * @param array<string, mixed>|null $row stripe_reminder_row(), or null.
 * @param int|string $ordreStatus ordrer.status of the reminder (3+ = booked).
 * @return bool
 */
function stripe_reminder_waivable($row, $ordreStatus): bool {
	return is_array($row) && $row['fee_mode'] === 'next' && in_array($row['status'], array('draft', 'sent'), true)
		&& ($row['status'] === 'sent' || (int)$ordreStatus >= 3)
		&& (int)$row['fee_ore'] + (int)$row['interest_ore'] > 0;
}

/**
 * "Frafald rykkergebyr" posted from rykker.php: the form's CSRF token first, then the waiver when it applies.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @param string $postedToken The form's csrf_token.
 * @param string $sessionToken $_SESSION['csrf_token'].
 * @return string The staff message for the alert.
 */
function stripe_reminder_waive_request(int $rykkerId, string $postedToken, string $sessionToken): string {
	if ($sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
		return reminder_txt(8133, 'Ugyldig eller udløbet formular - genindlæs siden og prøv igen.');
	}
	$o = db_fetch_array(db_select("select status from ordrer where id = " . (int)$rykkerId, __FILE__ . " linje " . __LINE__));
	$waived = $o && stripe_reminder_waivable(stripe_reminder_row($rykkerId), $o['status']) && stripe_reminder_waive($rykkerId, 'manual');
	return $waived ? reminder_txt(8132, 'Rykkergebyret er frafaldet.')
		: reminder_txt(8134, 'Rykkergebyret kan ikke frafaldes: det er allerede opkrævet eller frafaldet, eller rykkeren er ikke sendt.');
}

/**
 * Whether an invoice on a reminder is paid: the order is marked paid, its open item is settled, or a captured card
 * payment exists (the journal line may not be posted yet).
 *
 * @param array{ordre_id: int, openpost_id: int} $invoice An entry of stripe_reminder_lines()['invoices'].
 * @param bool $stripePayments Whether stripe_payments exists.
 * @return bool
 */
function stripe_reminder_invoice_paid($invoice, $stripePayments) {
	$o = db_fetch_array(db_select("select betalt from ordrer where id = " . (int)$invoice['ordre_id'], __FILE__ . " linje " . __LINE__));
	if ($o && trim((string)$o['betalt']) === 'on') return true;
	$op = db_fetch_array(db_select("select udlignet from openpost where id = " . (int)$invoice['openpost_id'], __FILE__ . " linje " . __LINE__));
	if ($op && trim((string)$op['udlignet']) === '1') return true;
	if ($stripePayments) {
		$p = db_fetch_array(db_select("select id from stripe_payments where ordre_id = " . (int)$invoice['ordre_id'] . " and status in ('succeeded', 'duplicate_paid')", __FILE__ . " linje " . __LINE__));
		if ($p) return true;
	}
	return false;
}

/**
 * Close booked link reminders whose invoices are all paid, the way "Afslut" closes a reminder (betalt = 'on'), so
 * they do not stay in "Bogførte rykkere". Run lazily by the reminder overview and "Ryk alle". A carried fee that is
 * not charged yet still goes on the next invoice.
 *
 * @param int $kontoId Only this customer; 0 = all.
 * @return int Number of reminders closed.
 */
function stripe_reminder_close_paid(int $kontoId = 0): int {
	if (!stripe_reminder_table()) return 0;
	$ids = array();
	$q = db_select("select r.rykker_id from stripe_reminders r, ordrer o where o.id = r.rykker_id and o.art like 'R%' and o.status >= 3
		and coalesce(o.betalt, '') <> 'on'" . ($kontoId > 0 ? " and r.konto_id = " . (int)$kontoId : '') . " order by r.rykker_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $ids[] = (int)$r['rykker_id'];
	if (!$ids) return 0;
	$stripePayments = (bool)tbl_exists('stripe_payments');
	$closed = 0;
	foreach ($ids as $rid) {
		$invoices = stripe_reminder_lines($rid)['invoices'];
		if (!$invoices) continue;
		foreach ($invoices as $invoice) {
			if (!stripe_reminder_invoice_paid($invoice, $stripePayments)) continue 2;
		}
		db_modify("update ordrer set betalt = 'on' where id = $rid", __FILE__ . " linje " . __LINE__);
		$closed++;
	}
	return $closed;
}
