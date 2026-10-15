<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiToolRuntime.php --- ai/mcp-assistant --- 2026.10.05
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
//
// Precise money formatting, validated dates, bounded pages and typed citations.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Use shared runtime JSON canonicalization.
// 20261002 CL/LH Ledger and account-kind words instead of F/D/K and D/S codes; input property descriptions.
// 20261002 CL/LH Shared openpost base-currency conversion with legacy placeholder-rate handling.
// 20261005 CDX/LH Format decimal quantities in Danish notation without trailing zeros.

declare(strict_types=1);

final class SaldiToolRuntime
{
	/**
	 * Display a SQL decimal quantity in Danish notation without trailing zeros or float conversion.
	 *
	 * @param mixed $value Decimal string, or an empty value for a text-only line.
	 * @return string Danish quantity; unrecognized values are left readable.
	 */
	public static function quantityDa(mixed $value): string
	{
		$value = (string)$value;
		if (!preg_match('/^(-?)([0-9]+)(?:\.([0-9]+))?$/D', $value, $parts)) {
			return $value;
		}
		$whole = ltrim($parts[2], '0') ?: '0';
		$fraction = rtrim(ifset($parts, 3, ''), '0');
		$sign = $whole === '0' && $fraction === '' ? '' : $parts[1];
		return $sign . preg_replace('/\B(?=([0-9]{3})+(?![0-9]))/', '.', $whole) . ($fraction === '' ? '' : ',' . $fraction);
	}

	/** Canonical JSON preserves empty objects and sorts every object recursively. */
	public static function canonical(mixed $value): string
	{
		return saldi_runtime_json($value);
	}
	/** Round a SQL numeric string half away from zero without binary floats. */
	public static function money(mixed $value): string
	{
		$v = (string)($value ?? '0');
		if (!preg_match('/^(-?)([0-9]+)(?:\.([0-9]+))?$/D', $v, $m)) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Invalid stored monetary amount');
		}
		$fraction = str_pad($m[3] ?? '', 3, '0');
		$digits = ltrim($m[2] . substr($fraction, 0, 2), '0') ?: '0';
		if ($fraction[2] >= '5') {
			$carry = 1;
			for ($i = strlen($digits) - 1; $i >= 0 && $carry; $i--) {
				$n = (int)$digits[$i] + $carry;
				$digits[$i] = (string)($n % 10);
				$carry = intdiv($n, 10);
			}
			if ($carry) {
				$digits = '1' . $digits;
			}
		}
		$digits = str_pad($digits, 3, '0', STR_PAD_LEFT);
		return ($m[1] === '-' && trim($digits, '0') !== '' ? '-' : '') . substr($digits, 0, -2) . '.' . substr($digits, -2);
	}
	public static function text(mixed $value): string
	{
		return mb_substr((string)($value ?? ''), 0, 500);
	}
	public static function today(): string
	{
		return (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
	}
	public static function date(string $value): string
	{
		$d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		if (!$d || $d->format('Y-m-d') !== $value) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid date', ['fields' => ['date']]);
		}
		return $value;
	}
	/** Fiscal-year bounds from the legacy month/year columns. @return array{id:int,start:string,end:string} */
	public static function year(array $r): array
	{
		$start = sprintf('%04d-%02d-01', (int)$r['box2'], (int)$r['box1']);
		$last = sprintf('%04d-%02d-01', (int)$r['box4'], (int)$r['box3']);
		self::date($start);
		self::date($last);
		return ['id' => (int)$r['kodenr'], 'start' => $start, 'end' => (new DateTimeImmutable($last))->format('Y-m-t')];
	}
	/** Validated ledger range, including defaults. @return array{0:string,1:string,2:string} */
	public static function range(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		$r = $db->one("SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA' AND kodenr = ?", [$ctx->fiscalYear]);
		if (!$r) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Fiscal year unavailable');
		}
		$year = self::year($r);
		$from = self::date($args['from'] ?? $year['start']);
		$to = self::date($args['to'] ?? min(self::today(), $year['end']));
		if ($from < $year['start'] || $to > $year['end'] || $from > $to) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Range must lie in the fiscal year', ['fields' => ['from', 'to']]);
		}
		return [$from, $to, $year['start']];
	}
	/** String cast from fixed, trusted SQL expressions. */
	public static function cast(SaldiDb $db, string $expression): string
	{
		return 'CAST(' . $expression . ' AS ' . ($db->isPostgres() ? 'TEXT' : 'CHAR') . ')';
	}
	/** Literal search pattern; % and _ supplied by callers are not wildcards. */
	public static function like(string $query, bool $prefix = false): string
	{
		return ($prefix ? '' : '%') . strtr(mb_strtolower($query), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
	}
	/** Page a fixed SELECT, counting its filtered rows cheaply. @return array{rows:array,total:int,more:bool} */
	public static function page(SaldiDb $db, string $select, string $order, array $params, array $args): array
	{
		$limit = $args['limit'] ?? 25;
		$offset = $args['offset'] ?? 0;
		$total = (int)$db->value('SELECT COUNT(*) FROM (' . $select . ') saldi_count', $params);
		$rows = $db->all($select . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?', array_merge($params, [$limit, $offset]));
		return ['rows' => $rows, 'total' => $total, 'more' => $offset + count($rows) < $total];
	}
	/** Handler response before the MCP envelope is built. @return array{data:array,citations:array,possibly_incomplete:bool,result_count:int} */
	public static function result(array $data, array $citations = [], bool $more = false, ?int $count = null): array
	{
		$trim = static function (mixed $v) use (&$trim, &$more): mixed {
			if (is_string($v) && mb_strlen($v) > 500) {
				$more = true;
				return mb_substr($v, 0, 500);
			}
			if (is_array($v)) {
				foreach ($v as $key => $item) {
					$v[$key] = $trim($item);
				}
			}
			return $v;
		};
		$data = $trim($data);
		$citations = $trim($citations);
		// Leave space for the receipt and other MCP envelope fields.
		while (strlen(self::canonical(['data' => $data, 'citations' => $citations])) > 198 * 1024) {
			$key = isset($data['rows']) ? 'rows' : (isset($data['lines']) ? 'lines' : null);
			if ($key === null || !$data[$key]) {
				throw new SaldiRuntimeError('UNAVAILABLE', 'Result exceeds the response limit');
			}
			array_pop($data[$key]);
			$citations = [];
			$more = true;
		}
		$count = isset($data['rows']) ? count($data['rows']) : (isset($data['lines']) ? count($data['lines']) : ($count ?? 1));
		return ['data' => $data, 'citations' => $citations, 'possibly_incomplete' => $more, 'result_count' => $count];
	}
	/** Typed record citation; an account/memory reference has no allowlisted screen. @return array */
	public static function citation(string $type, int|string $id, string $label, ?string $screenType = null): array
	{
		return ['id' => 'saldi:' . $type . ':' . $id, 'label' => self::text($label)] + ($screenType === null ? [] : ['screen' => SaldiScreens::link($screenType, (int)$id)]);
	}
	public static function currency(SaldiTenantContext $ctx, mixed $stored): string
	{
		// '-' is the legacy "no currency" marker, booked in base currency.
		return empty($stored) || $stored === '-' ? $ctx->baseCurrency : self::text($stored);
	}
	/**
	 * Base-currency conversion of an openpost row (alias o), shared by list_open_items, get_party and
	 * the overdue-debtors routine. Follows the legacy aging report (includes/reportFunc/showOpenPosts.php):
	 * valuta '', the base currency and '-' are base currency; a rate of 0 or NULL counts as 100; a foreign
	 * row stored at the placeholder rate 100 takes valuta.kurs of its currency on or before transdate.
	 * 'amount' is NULL and 'missing' is 1 when a foreign row's rate cannot be resolved, so SUM() leaves
	 * the row out of base totals. Each expression binds 'params' once, in order.
	 *
	 * @return array{amount: string, missing: string, params: list<string>}
	 */
	public static function openpostBaseSql(string $baseCurrency): array
	{
		$isBase = "COALESCE(NULLIF(o.valuta, ''), ?) IN (?, '-')";
		$rate = 'CASE WHEN CAST(COALESCE(NULLIF(o.valutakurs, 0), 100) AS DECIMAL(30,6)) <> 100 THEN CAST(o.valutakurs AS DECIMAL(30,6))'
			. " ELSE (SELECT CAST(v.kurs AS DECIMAL(30,6)) FROM valuta v JOIN grupper g ON g.kodenr = v.gruppe WHERE g.art = 'VK' AND g.box1 = o.valuta AND v.valdate <= o.transdate AND COALESCE(v.kurs, 0) <> 0 ORDER BY v.valdate DESC, v.id DESC LIMIT 1) END";
		$amount = 'CAST(COALESCE(o.amount, 0) AS DECIMAL(30,6))';
		return [
			'amount' => 'CASE WHEN ' . $isBase . ' THEN ' . $amount . ' ELSE ' . $amount . ' * (' . $rate . ') / 100 END',
			'missing' => 'CASE WHEN ' . $isBase . ' THEN 0 WHEN (' . $rate . ') IS NULL THEN 1 ELSE 0 END',
			'params' => [$baseCurrency, $baseCurrency],
		];
	}

	/** Kontoplan kontotype as a word a model cannot misread ('D' is drift, not debit). */
	public static function accountKind(?string $kontotype): string
	{
		return ['D' => 'pl', 'S' => 'balance', 'H' => 'heading', 'Z' => 'sum', 'R' => 'result', 'X' => 'page_break', 'L' => 'closed'][(string)$kontotype] ?? 'other';
	}
	/** Kassekladde side type F/D/K as a ledger word, and back. */
	public static function ledgerWord(string $type): string
	{
		return ['F' => 'account', 'D' => 'customer', 'K' => 'supplier'][$type] ?? 'none';
	}
	public static function ledgerCode(string $word): ?string
	{
		return ['account' => 'F', 'customer' => 'D', 'supplier' => 'K'][$word] ?? null;
	}
}
