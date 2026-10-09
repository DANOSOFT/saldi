<?php
// ---- index/dashboardIncludes/unpaidInvoices.php --- lap 5.0.0 --- 2026.10.09 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20261009 Sawaneh Ubetalte salgsfakturaer (front-page spec §5.9, addendum 3 §6): open customer items netted per invoice
//                  number in the database, split into not due / 1-15 days / over 15 days, the 8 oldest for the list,
//                  unapplied payments and credit notes as one line. Foreign items with the placeholder rate 100 are
//                  converted in PHP like openpost_account_aging() does. Links by refnr and ordrer.art (§5.9.4).

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
 * The card's data: groups (sum, count), the list (oldest due first, at most $limit), the unapplied sum and the total.
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
	$days = ($db_type === 'mysql' || $db_type === 'mysqli') ? "datediff(curdate(), coalesce(i.forfaldsdate, i.transdate))" : "(current_date - coalesce(i.forfaldsdate, i.transdate))";
	$bucket = "case when $days <= 0 then 'ok' when $days <= 15 then 'warn' else 'late' end";
	$groups = array('late' => array('sum' => 0.0, 'n' => 0), 'warn' => array('sum' => 0.0, 'n' => 0), 'ok' => array('sum' => 0.0, 'n' => 0));
	$q = db_select("select $bucket as grp, sum(i.net) as s, count(*) as n from ($items) i where i.net > 0 and i.placeholder = 0 group by $bucket", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (isset($groups[$r['grp']])) {
			$groups[$r['grp']] = array('sum' => (float) $r['s'], 'n' => (int) $r['n']);
		}
	}
	$r = db_fetch_array(db_select("select coalesce(sum(i.net), 0) as s from ($items) i where i.net < 0 and i.placeholder = 0", __FILE__ . " linje " . __LINE__));
	$unapplied = $r ? (float) $r['s'] : 0.0;
	$list = array();
	$q = db_select("select i.*, $days as days_over from ($items) i where i.net > 0 and i.placeholder = 0 order by coalesce(i.forfaldsdate, i.transdate), i.item limit " . ($limit * 2), __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$list[] = $r;
	}
	// Foreign items with the placeholder rate: converted here, counted into the groups, merged into the list.
	$q = db_select("select i.*, $days as days_over from ($items) i where i.placeholder = 1", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$r['net'] = (float) $r['net_cur'] * dash_unpaid_rate((string) $r['valuta'], (string) $r['transdate']) / 100;
		if ($r['net'] < 0) {
			$unapplied += $r['net'];
			continue;
		}
		if ($r['net'] <= 0) {
			continue;
		}
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
	$list = array_slice($list, 0, $limit);
	$out = array();
	foreach ($list as $r) {
		$over = (int) $r['days_over'];
		$out[] = array(
			'due' => (string) ($r['forfaldsdate'] ?: $r['transdate']), 'over' => $over, 'g' => $over <= 0 ? 'ok' : ($over <= 15 ? 'warn' : 'late'),
			'nr' => (string) (strpos((string) $r['item'], 'r') === 0 && (string) ($r['refnr'] ?? '') === '0' ? '' : $r['item']),
			'kunde' => trim((string) $r['firmanavn']) . ((string) $r['item'] !== '' && strpos((string) $r['item'], 'r') === 0 && trim((string) $r['beskrivelse']) !== '' ? ' · ' . trim((string) $r['beskrivelse']) : ''),
			'amount' => round((float) $r['net'], 2), 'href' => dash_unpaid_link($r),
		);
	}
	$total = $groups['late']['sum'] + $groups['warn']['sum'] + $groups['ok']['sum'];
	return array('groups' => $groups, 'list' => $out, 'unapplied' => round($unapplied, 2), 'total' => round($total, 2), 'count' => $groups['late']['n'] + $groups['warn']['n'] + $groups['ok']['n']);
}
