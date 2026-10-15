<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiBankMatcher.php --- ai/mcp-assistant --- 2026.10.05
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
// Deterministic bank matches from open invoices, payment identifiers, posting rules and history.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.
// 20261007 CL/LH An open item matched with high confidence is claimed once per batch; repeats become medium.
// 20261007 CL/LH Claims are a pure pass over the whole batch (claimBatch), so cached and filtered previews agree.

declare(strict_types=1);

require_once __DIR__ . '/SaldiBankImport.php';
require_once __DIR__ . '/SaldiPostingSuggester.php';

final class SaldiBankMatcher
{
	private ?array $openItems = null;
	private ?array $paymentIds = null;
	private ?SaldiPostingSuggester $suggester = null;
	public function __construct(private SaldiTenantContext $ctx, private SaldiDb $db)
	{
	}
	/** @return list<array> Outstanding totals per party, invoice and original currency. */
	private function openItems(): array
	{
		return $this->openItems ??= $this->db->all("SELECT a.id AS party_id, a.kontonr, a.art, a.firmanavn, o.faktnr, o.currency, SUM(o.amount) AS amount FROM (SELECT konto_id, faktnr, amount, CASE WHEN COALESCE(valuta, '') IN ('', '-') THEN ? ELSE valuta END AS currency FROM openpost WHERE COALESCE(udlignet, '') <> '1') o JOIN adresser a ON a.id = o.konto_id WHERE a.art IN ('D','K') AND COALESCE(a.lukket, '') <> 'on' GROUP BY a.id, a.kontonr, a.art, a.firmanavn, o.faktnr, o.currency HAVING SUM(o.amount) <> 0 ORDER BY a.id, o.faktnr", [$this->ctx->baseCurrency]);
	}
	/** Match complete identifiers, so invoice 12 never matches invoice 123. */
	private static function contains(string $text, string $id): bool
	{
		return $id !== '' && preg_match('/(?<![\p{L}\p{N}_])' . preg_quote($id, '/') . '(?![\p{L}\p{N}_])/iu', $text) === 1;
	}
	/** @return list<string> FIK/IK71/Nordea identifiers, excluding the card-type code. */
	private static function paymentReferences(string $text): array
	{
		$ids = [];
		foreach (['/\+71\s*<\s*([0-9]{12,19})/i', '/IK71\s*[: ,]*([0-9]{12,19})/i', '/([0-9]{12,19})\s*IK71/i', '/Indbetalingskort\s*,?\s*nr\.?\s*[: ,]*([0-9]{12,19})/iu'] as $pattern) {
			preg_match_all($pattern, $text, $matches);
			$ids = array_merge($ids, $matches[1]);
		}
		return array_values(array_unique($ids));
	}
	/** Saldi's legacy card references encode the account, invoice and a trailing check digit. */
	private static function encodedInvoice(string $digits, string $account, string $invoice): bool
	{
		if (!ctype_digit($invoice)) {
			return false;
		}
		foreach ([[9, 5], [8, 6], [13, 5], [7, 5]] as [$partyLength, $invoiceLength]) {
			if (in_array(strlen($digits), [$partyLength + $invoiceLength, $partyLength + $invoiceLength + 1, $partyLength + $invoiceLength + 2], true)
				&& (ltrim(substr($digits, 0, $partyLength), '0') ?: '0') === (ltrim($account, '0') ?: '0')
				&& (ltrim(substr($digits, $partyLength, $invoiceLength), '0') ?: '0') === (ltrim($invoice, '0') ?: '0')) {
				return true;
			}
		}
		return false;
	}
	/**
	 * One payment settles one open item. Applied to the whole batch in statement order, every time
	 * matches are listed or proposed: an open item held by a proposed line, or by an earlier high
	 * match, is high at most once; later lines that hit it become 'medium' (opt-in). Matches are
	 * stored unclaimed, so the result never depends on which rows were cached or filtered.
	 * @param list<array{status:string,match:?array}> $entries Batch rows in statement order.
	 * @return list<?array> The matches, in the same order, with repeats demoted.
	 */
	public static function claimBatch(array $entries): array
	{
		$key = static fn(?array $match): ?string => isset($match['party']['ledger'], $match['party']['account'], $match['invoice'])
			? $match['party']['ledger'] . ':' . $match['party']['account'] . ':' . $match['invoice'] : null;
		$claimed = [];
		foreach ($entries as $entry) {
			if (($entry['status'] ?? '') === 'proposed' && ($k = $key($entry['match'] ?? null)) !== null) {
				$claimed[$k] = true;
			}
		}
		$out = [];
		foreach ($entries as $entry) {
			$match = $entry['match'] ?? null;
			$k = $key($match);
			if ($k !== null && ($entry['status'] ?? '') !== 'proposed') {
				if (isset($claimed[$k])) {
					$match['confidence'] = $match['confidence'] === 'high' ? 'medium' : $match['confidence'];
				} elseif ($match['confidence'] === 'high') {
					$claimed[$k] = true;
				}
			}
			$out[] = $match;
		}
		return $out;
	}
	/** @return array{party:?array{ledger:string,account:string,name:string},account:?array{account:string,name:string},invoice:?string,confidence:string} */
	private static function partyMatch(array $row, string $confidence): array
	{
		return ['party' => ['ledger' => $row['art'] === 'D' ? 'customer' : 'supplier', 'account' => (string)$row['kontonr'], 'name' => (string)$row['firmanavn']], 'account' => null, 'invoice' => $row['faktnr'] === null || (string)$row['faktnr'] === '' ? null : (string)$row['faktnr'], 'confidence' => $confidence];
	}
	/**
	 * First safe hit: invoice/payment identifier, unique named party, then approved rule/history.
	 * @return array{party:?array{ledger:string,account:string,name:string},account:?array{account:string,name:string},invoice:?string,confidence:string}|null
	 */
	public function match(array $line): ?array
	{
		SaldiPermissionService::require($this->ctx, $this->db, 'journal.read');
		if (in_array($line['status'] ?? 'new', ['duplicate', 'ignored'], true)) {
			return null;
		}
		$amount = SaldiBankStatementParser::milli((string)$line['amount']);
		if ($amount === 0 || ($line['currency'] ?? $this->ctx->baseCurrency) !== $this->ctx->baseCurrency) {
			return null;
		}
		$incoming = $amount > 0;
		if ($incoming && SaldiPermissionService::revenueHidden($this->ctx, $this->db)) {
			return null;
		}
		$text = trim((string)$line['description'] . ' ' . ($line['reference'] ?? ''));
		$items = array_values(array_filter($this->openItems(), fn(array $item): bool => $item['art'] === ($incoming ? 'D' : 'K') && ($incoming ? SaldiBankStatementParser::milli($item['amount']) > 0 : SaldiBankStatementParser::milli($item['amount']) < 0) && in_array($item['currency'], [$line['currency'] ?? $this->ctx->baseCurrency, '-'], true)));
		if ($this->paymentIds === null) {
			$this->paymentIds = [];
			foreach ($this->db->all("SELECT konto_id, fakturanr, betalings_id FROM ordrer WHERE art IN ('DO','KO') AND COALESCE(fakturanr, '') <> '' AND COALESCE(betalings_id, '') <> '' ORDER BY id") as $order) {
				$this->paymentIds[(int)$order['konto_id']][(string)$order['fakturanr']][] = trim((string)$order['betalings_id']);
			}
		}
		$references = self::paymentReferences($text);
		$identifierText = preg_replace('/\+71\s*<|IK71|Indbetalingskort\s*,?\s*nr\.?/iu', ' ', $text);
		$hits = [];
		foreach ($items as $item) {
			$invoice = (string)($item['faktnr'] ?? '');
			$hit = self::contains($identifierText, $invoice);
			foreach ($references as $digits) {
				$hit = $hit || self::encodedInvoice($digits, (string)$item['kontonr'], $invoice);
			}
			foreach ($this->paymentIds[(int)$item['party_id']][$invoice] ?? [] as $paymentId) {
				$hit = $hit || self::contains($identifierText, $paymentId);
				if (ctype_digit($paymentId)) {
					foreach ($references as $digits) {
						$hit = $hit || (ltrim($digits, '0') ?: '0') === (ltrim($paymentId, '0') ?: '0');
					}
				}
			}
			if ($hit) {
				$hits[] = $item;
			}
		}
		if ($hits) {
			// A reference shared by parties is not safe enough to choose automatically.
			$parties = array_unique(array_column($hits, 'party_id'));
			if (count($parties) !== 1 || count($hits) !== 1) {
				return null;
			}
			$item = $hits[0];
			return self::partyMatch($item, abs(abs(SaldiBankStatementParser::milli($item['amount'])) - abs($amount)) <= 5 ? 'high' : 'medium');
		}
		$nameText = ' ' . SaldiPostingSuggester::normalise($text) . ' ';
		$named = [];
		foreach ($items as $item) {
			$name = SaldiPostingSuggester::normalise((string)$item['firmanavn']);
			if (mb_strlen($name) >= 4 && str_contains($nameText, ' ' . $name . ' ') && abs(abs(SaldiBankStatementParser::milli($item['amount'])) - abs($amount)) <= 5) {
				$named[(int)$item['party_id']][] = $item;
			}
		}
		if (count($named) === 1) {
			$rows = reset($named);
			$match = self::partyMatch($rows[0], 'high');
			if (count($rows) > 1) {
				$match['invoice'] = null;
			}
			return $match;
		}
		if (!SaldiPermissionService::allows($this->ctx, $this->db, 'accounts.read')) {
			return null;
		}
		$hint = ($this->suggester ??= new SaldiPostingSuggester($this->ctx, $this->db))->suggest(['description' => mb_substr($text, 0, 100) ?: SaldiText::get($this->db, 6183, $this->ctx->languageId, 'Bankpostering'), 'amount' => SaldiToolRuntime::money(ltrim((string)$line['amount'], '-')), 'date' => $line['transdate'] ?? $line['date']]);
		$suggestion = $hint['suggestions'][0] ?? null;
		if ($suggestion === null || !in_array($suggestion['confidence'], ['high', 'medium'], true)) {
			return null;
		}
		$bank = (string)$line['bank_account'];
		$counter = null;
		foreach (['debit' => 'credit', 'credit' => 'debit'] as $side => $other) {
			if (($suggestion[$side]['ledger'] ?? '') === 'account' && $suggestion[$side]['account'] === $bank) {
				$counter = $suggestion[$other];
			}
		}
		// A text rule without history has a single finance side.
		if ($counter === null && $suggestion['source'] === 'rule' && ($suggestion['debit'] === null || $suggestion['credit'] === null)) {
			$counter = $suggestion['debit'] ?? $suggestion['credit'];
		}
		if ($counter === null || ($counter['ledger'] === 'account' && $counter['account'] === $bank)) {
			return null;
		}
		$vat = ['vat_free' => $suggestion['vat_free'], 'vat_code' => $suggestion['vat_free'] ? '' : $counter['vat_code']];
		if ($counter['ledger'] === 'account') {
			return ['party' => null, 'account' => ['account' => $counter['account'], 'name' => $counter['name']], 'invoice' => null, 'confidence' => $suggestion['confidence']] + $vat;
		}
		if ($counter['ledger'] !== ($incoming ? 'customer' : 'supplier')) {
			return null;
		}
		return ['party' => array_intersect_key($counter, array_flip(['ledger', 'account', 'name'])), 'account' => null, 'invoice' => null, 'confidence' => $suggestion['confidence']] + $vat;
	}
}
