<?php
// ---- index/dashboardIncludes/unpaidInvoices.php --- ver 5.1.0 --- 2026.11.03 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261009 SS Ubetalte salgsfakturaer (front-page spec §5.9, addendum 3 §6): open customer items netted per invoice number in the database, split into not due / 1-15 days / over 15 days, the 8 oldest for the list, unapplied payments and credit notes as one line.
//             Foreign items with the placeholder rate 100 are converted in PHP like openpost_account_aging() does.
//             Links by refnr and ordrer.art (§5.9.4).
// 20261009 SS Items without a due date get one from the customer's payment terms with forfaldsdag(), as the account card does (§5.9.2 point 4); such items are grouped in PHP like the placeholder-rate ones.
// 20261101 CL/ASR The list holds the oldest invoices of each due group (at most $limit per group, oldest first), so a group picked on the card shows its own invoices (prototype D); the page shows the first $limit of them unfiltered.
// 20261103 CL/ASR QA: a foreign-currency invoice with the placeholder rate is converted posting by posting (each at its own date's rate, or its own rate when it has one) before the postings are netted, not the net at the first posting's rate.

include_once(__DIR__ . "/../../includes/forfaldsdag.php");

/**
 * The rate for a foreign-currency open item on its posting date (the report's rule: VK group, newest valuta row on or
 * before the date; 100 when none).
 */
function dash_unpaid_rate(string $valuta, string $date): float
{
	static $groups = array(), $rates = array();
	$key = $valuta . '|' . $date;
	if (isset($rates[$key])) {
		return $rates[$key];
	}
	if (!isset($groups[$valuta])) {
		$r = db_fetch_array(db_select("select kodenr from grupper where box1 = '" . db_escape_string($valuta) . "' and art = 'VK'", __FILE__ . " linje " . __LINE__));
		$groups[$valuta] = $r ? (string) $r['kodenr'] : '';
	}
	$rates[$key] = 100.0;
	if ($groups[$valuta] !== '') {
		$r = db_fetch_array(db_select("select kurs from valuta where gruppe = '" . db_escape_string($groups[$valuta]) . "' and valdate <= '" . db_escape_string($date) . "' order by valdate desc limit 1", __FILE__ . " linje " . __LINE__));
		if ($r && (float) $r['kurs']) {
			$rates[$key] = (float) $r['kurs'];
		}
	}
	return $rates[$key];
}

/**
 * Which page an item opens (§5.9.4): the order for invoices and credit notes, the dunning letter for fees, the journal
 * for journal items, otherwise the customer's account card.
 */
function dash_unpaid_due(array $r): string
{
	if ((string) $r['forfaldsdate'] !== '') {
		return (string) $r['forfaldsdate'];
	}
	$due = forfaldsdag((string) $r['transdate'], (string) $r['betalingsbet'], (int) $r['betalingsdage']);
	return $due ? usdate($due) : (string) $r['transdate'];
}

function dash_unpaid_link(array $it): string
{
	$refnr = (int) $it['refnr'];
	$kladde = (int) $it['kladde_id'];
	$kontonr = rawurlencode((string) $it['kontonr']);
	if ($kladde > 0) {
		return "../finans/kassekladde.php?kladde_id=$kladde&visipop=on";
	}
	if ($refnr > 0) {
		$o = db_fetch_array(db_select("select art from ordrer where id = $refnr", __FILE__ . " linje " . __LINE__));
		if ($o) {
			$art = (string) $o['art'];
			if ($art === 'DO' || $art === 'DK') {
				return "../debitor/ordre.php?id=$refnr";
			}
			if ($art !== '' && $art[0] === 'R') {
				return "../debitor/rykker.php?rykker_id=$refnr";
			}
		}
	}
	return "../debitor/rapport.php?rapportart=accountChart&kilde=openpost&konto_fra=$kontonr&konto_til=$kontonr&submit=ok";
}

/**
 * An open item with the placeholder rate in the base currency: each posting converted on its own (its own rate, else the
 * rate of its date), then netted.
 *
 * @param array<string, mixed> $r A row of dash_unpaid()'s item query (konto_id, item, valuta, transdate, net_cur).
 */
function dash_unpaid_convert(array $r, string $baseCurrency): float
{
	$item = (string) $r['item'];
	$where = ($item !== '' && $item[0] === 'r' && ctype_digit(substr($item, 1)))
		? "id = " . (int) substr($item, 1) . " and coalesce(faktnr, '') = ''"
		: "faktnr = '" . db_escape_string($item) . "'";
	$q = db_select("select amount, transdate, valuta, valutakurs from openpost where konto_id = " . (int) $r['konto_id'] . " and (udlignet is null or udlignet != '1') and $where", __FILE__ . " linje " . __LINE__);
	$net = 0.0;
	$found = false;
	while ($p = db_fetch_array($q)) {
		$found = true;
		$valuta = trim((string) $p['valuta']);
		$kurs = (float) $p['valutakurs'];
		if ($valuta === '' || $valuta === '-' || $valuta === $baseCurrency) {
			$net += (float) $p['amount'];
		} elseif ($kurs > 0 && $kurs != 100) {
			$net += (float) $p['amount'] * $kurs / 100;
		} else {
			$net += (float) $p['amount'] * dash_unpaid_rate($valuta, (string) $p['transdate']) / 100;
		}
	}
	return $found ? $net : (float) $r['net_cur'] * dash_unpaid_rate((string) $r['valuta'], (string) $r['transdate']) / 100;
}

/**
 * The card's data: groups (sum, count), the list (oldest due first, at most $limit per due group), the unapplied sum and the total.
 *
 * @return array{groups: array<string, array{sum: float, n: int}>, list: array<int, array<string, mixed>>, unapplied: float, total: float, count: int}
 */
function dash_unpaid(string $baseCurrency, int $limit = 8): array
{
	global $db_type;
	$base = db_escape_string($baseCurrency);
	$foreign = "coalesce(o.valuta, '') not in ('', '-', '$base')";
	$inBase = "case when $foreign and coalesce(o.valutakurs, 0) > 0 then o.amount * o.valutakurs / 100 else o.amount end";
	$placeholder = "case when $foreign and coalesce(o.valutakurs, 0) in (0, 100) then 1 else 0 end";
	$itemKey = ($db_type === 'mysql' || $db_type === 'mysqli') ? "case when coalesce(o.faktnr, '') = '' then concat('r', o.id) else o.faktnr end" : "case when coalesce(o.faktnr, '') = '' then 'r' || o.id else o.faktnr end";
	$items = "select o.konto_id, $itemKey as item, a.kontonr, a.firmanavn, a.betalingsbet, a.betalingsdage, sum($inBase) as net, sum(o.amount) as net_cur, max($placeholder) as placeholder, "
		. "min(o.transdate) as transdate, min(o.forfaldsdate) as forfaldsdate, max(case when o.amount > 0 then coalesce(o.refnr, 0) else 0 end) as refnr, "
		. "max(case when o.amount > 0 then coalesce(o.kladde_id, 0) else 0 end) as kladde_id, max(case when o.amount > 0 then coalesce(o.beskrivelse, '') else '' end) as beskrivelse, max(coalesce(o.valuta, '')) as valuta "
		. "from openpost o join adresser a on a.id = o.konto_id and a.art = 'D' where (o.udlignet is null or o.udlignet != '1') "
		. "group by o.konto_id, $itemKey, a.kontonr, a.firmanavn, a.betalingsbet, a.betalingsdage";
	$days = ($db_type === 'mysql' || $db_type === 'mysqli') ? "datediff(curdate(), i.forfaldsdate)" : "(current_date - i.forfaldsdate)";
	$inSql = "i.net > 0 and i.placeholder = 0 and i.forfaldsdate is not null";
	$bucket = "case when $days <= 0 then 'ok' when $days <= 15 then 'warn' else 'late' end";
	$groups = array('late' => array('sum' => 0.0, 'n' => 0), 'warn' => array('sum' => 0.0, 'n' => 0), 'ok' => array('sum' => 0.0, 'n' => 0));
	$q = db_select("select $bucket as grp, sum(i.net) as s, count(*) as n from ($items) i where $inSql group by $bucket", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (isset($groups[$r['grp']])) {
			$groups[$r['grp']] = array('sum' => (float) $r['s'], 'n' => (int) $r['n']);
		}
	}
	$r = db_fetch_array(db_select("select coalesce(sum(i.net), 0) as s from ($items) i where i.net < 0 and i.placeholder = 0", __FILE__ . " linje " . __LINE__));
	$unapplied = $r ? (float) $r['s'] : 0.0;
	$list = array();
	foreach (array_keys($groups) as $g) {
		$q = db_select("select i.*, $days as days_over from ($items) i where $inSql and $bucket = '$g' order by i.forfaldsdate, i.item limit " . $limit, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$list[] = $r;
		}
	}
	// Foreign items with the placeholder rate and items without a due date: converted and dated here, counted into the
	// groups, merged into the list.
	$today = new DateTime(date('Y-m-d'));
	$q = db_select("select i.* from ($items) i where i.placeholder = 1 or (i.forfaldsdate is null and i.net > 0)", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ((int) $r['placeholder'] === 1) {
			$r['net'] = dash_unpaid_convert($r, $baseCurrency);
		}
		if ($r['net'] < 0) {
			$unapplied += $r['net'];
			continue;
		}
		if ($r['net'] <= 0) {
			continue;
		}
		$r['forfaldsdate'] = dash_unpaid_due($r);
		$due = new DateTime(substr((string) $r['forfaldsdate'], 0, 10));
		$r['days_over'] = (int) $due->diff($today)->format('%r%a');
		$over = (int) $r['days_over'];
		$g = $over <= 0 ? 'ok' : ($over <= 15 ? 'warn' : 'late');
		$groups[$g]['sum'] += $r['net'];
		$groups[$g]['n']++;
		$list[] = $r;
	}
	usort($list, function ($a, $b) {
		$da = (string) ($a['forfaldsdate'] ?: $a['transdate']);
		$db = (string) ($b['forfaldsdate'] ?: $b['transdate']);
		return strcmp($da, $db) ?: strcmp((string) $a['item'], (string) $b['item']);
	});
	$out = array();
	$perGroup = array('late' => 0, 'warn' => 0, 'ok' => 0);
	foreach ($list as $r) {
		$over = (int) $r['days_over'];
		$g = $over <= 0 ? 'ok' : ($over <= 15 ? 'warn' : 'late');
		if (++$perGroup[$g] > $limit) {
			continue;
		}
		$out[] = array(
			'due' => (string) ($r['forfaldsdate'] ?: $r['transdate']), 'over' => $over, 'g' => $g,
			'nr' => (string) (strpos((string) $r['item'], 'r') === 0 ? ((int) $r['kladde_id'] > 0 && (int) $r['refnr'] > 0 ? $r['refnr'] : '') : $r['item']),
			'kunde' => trim((string) $r['firmanavn']) . ((string) $r['item'] !== '' && strpos((string) $r['item'], 'r') === 0 && trim((string) $r['beskrivelse']) !== '' ? ' · ' . trim((string) $r['beskrivelse']) : ''),
			'amount' => round((float) $r['net'], 2), 'href' => dash_unpaid_link($r),
		);
	}
	$total = $groups['late']['sum'] + $groups['warn']['sum'] + $groups['ok']['sum'];
	return array('groups' => $groups, 'list' => $out, 'unapplied' => round($unapplied, 2), 'total' => round($total, 2), 'count' => $groups['late']['n'] + $groups['warn']['n'] + $groups['ok']['n']);
}
