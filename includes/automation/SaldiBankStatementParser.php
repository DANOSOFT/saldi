<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiBankStatementParser.php --- ai/mcp-assistant --- 2026.10.05
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
// Bounded CSV and camt.053 parsing with exact decimals and disabled XML entities.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeError.php';
require_once __DIR__ . '/../runtime/SaldiText.php';

final class SaldiBankStatementParser
{
	/**
	 * Parse a bounded statement without resolving XML entities or contacting the bank.
	 * @return array{format:string,rows:list<array{date:string,description:string,amount:string,currency:string,balance?:string,reference?:string,statement_account?:string}>,warnings:list<string>,statement_accounts?:list<string>,statement_count?:int}
	 */
	public static function parse(string $content, ?string $filename = null): array
	{
		if (strlen($content) > 2 * 1024 * 1024 || trim($content) === '') {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Statement must contain at most 2 MB');
		}
		$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
		if (str_starts_with(ltrim($content), '<')) {
			return self::xml($content);
		}
		$warnings = [];
		if (!mb_check_encoding($content, 'UTF-8')) {
			$content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
			$warnings[] = SaldiText::get(null, 6180, 1, 'Filen er konverteret fra Windows-1252 til UTF-8.');
		}
		return self::csv($content, $warnings);
	}
	/** Canonical signed decimal, retaining a third decimal until the API boundary. */
	public static function amount(string $value, ?string $decimalSeparator = null): ?string
	{
		$value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
		if (!preg_match('/^[+-]?[0-9][0-9.,]*$/D', $value)) {
			return null;
		}
		$negative = str_starts_with($value, '-');
		$value = ltrim($value, '+-');
		if (str_contains($value, ',') && str_contains($value, '.')) {
			if (strrpos($value, '.') > strrpos($value, ',')) {
				if (!preg_match('/^[0-9]{1,3}(?:,[0-9]{3})+\.[0-9]{1,3}$/D', $value)) {
					return null;
				}
				$value = str_replace(',', '', $value);
			} else {
				if (!preg_match('/^[0-9]{1,3}(?:\.[0-9]{3})+,[0-9]{1,3}$/D', $value)) {
					return null;
				}
				$value = str_replace(['.', ','], ['', '.'], $value);
			}
		} elseif ($decimalSeparator === ',' && preg_match('/^[0-9]{1,3}(?:\.[0-9]{3})+$/D', $value)) {
			$value = str_replace('.', '', $value);
		} elseif ($decimalSeparator === '.' && preg_match('/^[0-9]{1,3}(?:,[0-9]{3})+$/D', $value)) {
			$value = str_replace(',', '', $value);
		} elseif (str_contains($value, ',')) {
			$value = str_replace(',', '.', $value);
		}
		if (!preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,3}))?$/D', $value, $m)) {
			return null;
		}
		$amount = (ltrim($m[1], '0') ?: '0') . '.' . str_pad($m[2] ?? '', 2, '0');
		return ($negative && self::milli($amount) !== 0 ? '-' : '') . $amount;
	}
	/** Signed integer milli-units from an already classified decimal string. */
	public static function milli(string $amount): int
	{
		if (!preg_match('/^(-?)([0-9]{1,12})(?:\.([0-9]{1,3}))?$/D', $amount, $m)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid decimal amount');
		}
		return ($m[1] === '-' ? -1 : 1) * ((int)$m[2] * 1000 + (int)str_pad($m[3] ?? '', 3, '0'));
	}
	/** Recognised bank date, or null rather than PHP's permissive date rollover. */
	public static function date(string $value): ?string
	{
		$value = trim($value);
		if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $m)) {
			[$year, $month, $day] = [(int)$m[1], (int)$m[2], (int)$m[3]];
		} elseif (preg_match('/^([0-9]{2})([-.\/])([0-9]{2})\2([0-9]{4}|[0-9]{2})$/D', $value, $m)) {
			[$day, $month, $year] = [(int)$m[1], (int)$m[3], (int)$m[4]];
			if (strlen($m[4]) === 2) {
				$year += $year < 70 ? 2000 : 1900;
			}
		} else {
			return null;
		}
		return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
	}
	/** @return array{format:string,rows:list<array>,warnings:list<string>,statement_accounts:list<string>,statement_count:int} */
	private static function xml(string $content): array
	{
		if (preg_match('/<!\s*(DOCTYPE|ENTITY)\b/i', $content)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'XML entities and document types are disabled');
		}
		$prior = libxml_use_internal_errors(true);
		try {
			$doc = new DOMDocument();
			$doc->resolveExternals = false;
			$doc->substituteEntities = false;
			if (!$doc->loadXML($content, LIBXML_NONET) || $doc->doctype !== null) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid camt.053 XML');
			}
			$xpath = new DOMXPath($doc);
			if ($xpath->query('//*[local-name()="BkToCstmrStmt"]')->length !== 1) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected camt.053 bank statement');
			}
			$entries = $xpath->query('//*[local-name()="BkToCstmrStmt"]/*[local-name()="Stmt"]/*[local-name()="Ntry"]');
			if ($entries->length > 5000) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Statement exceeds 5000 rows');
			}
			$rows = [];
			$accounts = [];
			foreach ($xpath->query('//*[local-name()="BkToCstmrStmt"]/*[local-name()="Stmt"]') as $statement) {
				$accounts[] = trim($xpath->evaluate('string(./*[local-name()="Acct"]/*[local-name()="Id"]/*[local-name()="IBAN"] | ./*[local-name()="Acct"]/*[local-name()="Id"]/*[local-name()="Othr"]/*[local-name()="Id"])', $statement));
			}
			foreach ($entries as $entry) {
				$get = static function (string $path) use ($xpath, $entry): string {
					return trim($xpath->evaluate('string(' . $path . ')', $entry));
				};
				$status = $get('./*[local-name()="Sts"]/*[local-name()="Cd"]') ?: $get('./*[local-name()="Sts"]');
				if ($status !== '' && $status !== 'BOOK') {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Only booked camt.053 entries are accepted', ['warning_da' => SaldiText::get(null, 6309, 1, 'Filen indeholder bankposter, der endnu ikke er bogført i banken.'), 'warning_id' => 6309]);
				}
				$statementAccount = trim($xpath->evaluate('string(../*[local-name()="Acct"]/*[local-name()="Id"]/*[local-name()="IBAN"] | ../*[local-name()="Acct"]/*[local-name()="Id"]/*[local-name()="Othr"]/*[local-name()="Id"])', $entry));
				$date = $get('./*[local-name()="BookgDt"]/*[local-name()="Dt"]') ?: $get('./*[local-name()="ValDt"]/*[local-name()="Dt"]');
				$amount = self::amount($get('./*[local-name()="Amt"]'));
				$sign = $get('./*[local-name()="CdtDbtInd"]');
				if (self::date($date) === null || $amount === null || self::milli($amount) < 0 || !in_array($sign, ['CRDT', 'DBIT'], true)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid camt.053 entry');
				}
				$text = [];
				foreach ($xpath->query('./*[local-name()="NtryDtls"]/*[local-name()="TxDtls"]/*[local-name()="RmtInf"]/*[local-name()="Ustrd"]', $entry) as $node) {
					$text[] = trim($node->textContent);
				}
				$reference = $get('.//*[local-name()="Refs"]/*[local-name()="EndToEndId" and normalize-space(.) != "NOTPROVIDED"]') ?: $get('.//*[local-name()="AcctSvcrRef"]');
				$currency = strtoupper($get('./*[local-name()="Amt"]/@Ccy'));
				if (!preg_match('/^[A-Z]{3}$/D', $currency)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid statement currency');
				}
				$rows[] = ['statement_account' => $statementAccount, 'date' => $date, 'description' => implode(' ', $text) ?: $get('./*[local-name()="AddtlNtryInf"]'), 'amount' => ($sign === 'DBIT' && self::milli($amount) !== 0 ? '-' : '') . $amount, 'currency' => $currency]
					+ ($reference !== '' ? ['reference' => $reference] : []);
			}
			return ['format' => 'camt.053', 'rows' => $rows, 'warnings' => [], 'statement_accounts' => $accounts, 'statement_count' => count($accounts)];
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($prior);
		}
	}
	/** Infer the delimiter using its modal field count over the first twenty lines. */
	private static function delimiter(string $content): string
	{
		$sample = array_slice(preg_split('/\r\n|\n|\r/', $content), 0, 20);
		$best = ';';
		$bestScore = -1;
		$bestWidth = 0;
		foreach ([';', ',', "\t"] as $delimiter) {
			$counts = [];
			foreach ($sample as $line) {
				if (trim($line) !== '') {
					$count = count(str_getcsv($line, $delimiter, '"', ''));
					if ($count > 1) {
						$counts[$count] = ($counts[$count] ?? 0) + 1;
					}
				}
			}
			$score = $counts ? max($counts) / max(1, count($sample)) : 0;
			$width = $counts ? max(array_keys(array_filter($counts, static fn(int $count): bool => $count === max($counts)))) : 0;
			if ($score > $bestScore || ($score === $bestScore && $width > $bestWidth)) {
				$best = $delimiter;
				$bestScore = $score;
				$bestWidth = $width;
			}
		}
		return $best;
	}
	/** @return array<string,int> Recognised column positions. */
	private static function header(array $fields): array
	{
		$map = [];
		foreach ($fields as $i => $name) {
			$name = mb_strtolower(trim((string)$name));
			foreach (['date' => '/^(dato|bogført|bogføringsdato|bogfoert|date|booking date|booked date)$/u',
				'description' => '/^(tekst|beskrivelse|text|description|posteringstekst|postering|meddelelse)$/u',
				'amount' => '/^(beløb|belob|amount|beløb \(dkk\))$/u', 'balance' => '/^(saldo|balance|løbende saldo)$/u',
				'currency' => '/^(valuta|currency)$/u', 'reference' => '/^(reference|ref|betalingsreference)$/u'] as $key => $pattern) {
				if (!isset($map[$key]) && preg_match($pattern, $name)) {
					$map[$key] = $i;
				}
			}
		}
		return $map;
	}
	/** @return array<string,int> Headerless columns, resolving amount against a running balance. */
	private static function columns(array $rows, ?string $decimalSeparator): array
	{
		$dates = [];
		$amounts = [];
		foreach (array_keys($rows[0]) as $i) {
			if (count(array_filter($rows, static fn(array $r): bool => isset($r[$i]) && self::date((string)$r[$i]) !== null)) === count($rows)) {
				$dates[] = $i;
			} elseif (count(array_filter($rows, static fn(array $r): bool => isset($r[$i]) && self::amount((string)$r[$i], $decimalSeparator) !== null)) === count($rows)) {
				$amounts[] = $i;
			}
		}
		if (!$dates || !$amounts) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Cannot identify date and amount columns');
		}
		$amount = $amounts[0];
		$balance = count($amounts) > 1 ? $amounts[count($amounts) - 1] : null;
		if ($balance !== null && count($rows) > 1) {
			$matched = [];
			foreach (array_slice($amounts, 0, -1) as $candidate) {
				foreach ([false, true] as $descending) {
					$ordered = $descending ? array_reverse($rows) : $rows;
					$valid = true;
					for ($i = 1; $i < count($ordered); $i++) {
						$delta = self::milli(self::amount((string)$ordered[$i][$balance], $decimalSeparator)) - self::milli(self::amount((string)$ordered[$i - 1][$balance], $decimalSeparator));
						if ($delta !== self::milli(self::amount((string)$ordered[$i][$candidate], $decimalSeparator))) {
							$valid = false;
							break;
						}
					}
					if ($valid) {
						$matched[] = $candidate;
					}
				}
			}
			$matched = array_values(array_unique($matched));
			if (count($matched) === 1) {
				$amount = $matched[0];
			} else {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Ambiguous amount columns; include a header', ['warning_da' => SaldiText::get(null, 6308, 1, 'Beløbskolonnen kan ikke bestemmes sikkert. Tilføj en overskriftsrække med Dato, Tekst og Beløb.'), 'warning_id' => 6308]);
			}
		}
		if ($balance !== null && count($rows) < 2) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Ambiguous amount columns; include a header', ['warning_da' => SaldiText::get(null, 6308, 1, 'Beløbskolonnen kan ikke bestemmes sikkert. Tilføj en overskriftsrække med Dato, Tekst og Beløb.'), 'warning_id' => 6308]);
		}
		$map = ['date' => $dates[0], 'amount' => $amount];
		if ($balance !== null) {
			$map['balance'] = $balance;
		}
		foreach (array_keys($rows[0]) as $i) {
			if (!in_array($i, array_merge($dates, $amounts), true)) {
				if (preg_match('/^[A-Z]{3}$/D', trim((string)$rows[0][$i]))) {
					$map['currency'] = $i;
				} elseif (!isset($map['description'])) {
					$map['description'] = $i;
				}
			}
		}
		return $map;
	}
	/** @return array{format:string,rows:list<array>,warnings:list<string>} */
	private static function csv(string $content, array $warnings): array
	{
		$delimiter = self::delimiter($content);
		$stream = fopen('php://temp', 'w+');
		fwrite($stream, $content);
		rewind($stream);
		$records = [];
		try {
			while (($fields = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
				if (count($fields) === 1 && trim((string)$fields[0]) === '') {
					continue;
				}
				$records[] = $fields;
				if (count($records) > 5021) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Statement exceeds 5000 rows');
				}
			}
		} finally {
			fclose($stream);
		}
		$map = [];
		foreach (array_slice($records, 0, 20) as $i => $fields) {
			$candidate = self::header($fields);
			if (isset($candidate['date'], $candidate['amount'])) {
				$map = $candidate;
				$records = array_slice($records, $i + 1);
				break;
			}
		}
		if (!$records) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Statement has no data rows');
		}
		$decimalSeparator = null;
		$amountColumns = isset($map['amount']) ? array_filter([$map['amount'], $map['balance'] ?? null], static fn(?int $i): bool => $i !== null) : array_keys($records[0]);
		foreach ($records as $record) {
			foreach ($amountColumns as $column) {
				if (preg_match('/^[+-]?[0-9.]+,[0-9]{1,2}$/D', trim((string)($record[$column] ?? '')))) {
					$decimalSeparator = ',';
				}
			}
		}
		$map = $map ?: self::columns($records, $decimalSeparator);
		if (count($records) > 5000) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Statement exceeds 5000 rows');
		}
		$rows = [];
		foreach ($records as $i => $fields) {
			$get = static fn(string $key): string => isset($map[$key]) ? trim((string)($fields[$map[$key]] ?? '')) : '';
			$date = self::date($get('date'));
			$amount = self::amount($get('amount'), $decimalSeparator);
			if ($date === null || $amount === null) {
				$warnings[] = sprintf(SaldiText::get(null, 6181, 1, 'Linje %s er sprunget over: dato eller beløb kunne ikke læses.'), (string)($i + 1));
				continue;
			}
			$currency = strtoupper($get('currency') ?: 'DKK');
			if (!preg_match('/^[A-Z]{3}$/D', $currency)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid statement currency');
			}
			$row = ['date' => $date, 'description' => $get('description'), 'amount' => $amount, 'currency' => $currency];
			$balance = self::amount($get('balance'), $decimalSeparator);
			if ($balance !== null) {
				$row['balance'] = $balance;
			}
			if ($get('reference') !== '') {
				$row['reference'] = $get('reference');
			}
			$rows[] = $row;
		}
		return ['format' => 'csv', 'rows' => $rows, 'warnings' => $warnings];
	}
}
