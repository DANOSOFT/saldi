<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiToolSchemas.php --- ai/mcp-assistant --- 2026.10.05
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
// Exhaustive input, response and inbox-card contracts for the base and companion catalogue.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Export draft taint, decision metadata and money row fields.
// 20261002 CL/LH Ledger and account-kind words instead of F/D/K and D/S codes; input property descriptions.
// 20261002 CL/LH Open items and parties report unconvertible foreign rows; journal lines must balance.
// 20261002 CDX/LH Add companion catalogue and verified evidence references.
// 20261002 CDX/LH Expose invoice, current-line exclusion and party side for suggestions.
// 20261003 CL/LH list_open_items rows carry item_id (openpost id) for reminder proposals.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261003 CL/LH propose_reminder open_item_ids optional.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261004 CDX/LH Expose customer-statement base-currency conversion metadata.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.

declare(strict_types=1);

final class SaldiToolSchemas
{
	/** Strict object schema; omitted required means every listed property. @return array */
	public static function object(array $properties, ?array $required = null): array
	{
		return ['type' => 'object', 'properties' => $properties ?: new stdClass(), 'required' => $required ?? array_keys($properties), 'additionalProperties' => false];
	}
	/** Array schema. @return array */
	public static function list(array $items, ?int $max = null): array
	{
		return ['type' => 'array', 'items' => $items] + ($max === null ? [] : ['maxItems' => $max]);
	}
	/** Compact field declarations for exhaustive response allowlists. @return array */
	public static function fields(string $strings = '', string $integers = '', string $booleans = '', string $money = ''): array
	{
		$out = [];
		foreach (['string' => $strings, 'integer' => $integers, 'boolean' => $booleans] as $type => $names) {
			foreach (array_filter(explode(' ', $names)) as $name) {
				$out[$name] = ['type' => $type];
			}
		}
		foreach (array_filter(explode(' ', $money)) as $name) {
			$out[$name] = ['type' => 'string', 'pattern' => '^-?[0-9]+\.[0-9]{2}$'];
		}
		return $out;
	}
	/** Shared inbox item and ApprovalCard schemas. @return array */
	public static function item(bool $payload = true): array
	{
		$p = self::fields('kind origin status digest title created_by attention created_at tenant_hash screen', 'id revision', 'can_undo deduplicated');
		$p['subject'] = self::object(['type' => ['type' => ['string', 'null']], 'id' => ['type' => ['string', 'null']]]);
		foreach (['owner_user_id', 'decided_by'] as $key) {
			$p[$key] = ['type' => ['integer', 'null']];
		}
		foreach (['expires_at', 'due_at', 'decided_at', 'decision_note', 'decided_by_name'] as $key) {
			$p[$key] = ['type' => ['string', 'null']];
		}
		if ($payload) {
			$p['payload'] = ['type' => 'object'];
		}
		$p['evidence'] = ['type' => ['object', 'null']];
		$p['result'] = ['type' => ['object', 'null']];
		$card = self::fields('digest tenant_hash kind kind_label provenance provenance_label title summary why next_step action_class status approve_label reject_label screen', 'item_id revision', 'can_undo');
		$card['expires_at'] = ['type' => ['string', 'null']];
		$card['columns'] = self::list(['type' => 'string']);
		$card['rows'] = self::list(self::list(['type' => 'string']));
		$card['fields'] = self::list(self::object(self::fields('label value')));
		$card['evidence'] = self::list(self::object(self::fields('label screen')));
		$card['notices'] = self::list(self::object(self::fields('kind text')));
		$p['card'] = self::object($card);
		return self::object($p);
	}
	/** Catalogue input and data schemas. @return array<string,array{input:array,data:array}> */
	public static function catalogue(): array
	{
		$s = ['type' => 'string', 'maxLength' => 500];
		$id = ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647];
		$b = ['type' => 'boolean'];
		$date = ['type' => 'string', 'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'];
		$account = ['type' => 'string', 'pattern' => '^[0-9]{1,15}$'];
		$enum = static fn(array $values): array => ['type' => 'string', 'enum' => $values];
		$page = ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647]];
		$range = ['from' => $date, 'to' => $date];
		$out = [];
		$add = static function (string $name, array $input, array $required, array $data) use (&$out): void {
			$out[$name] = ['input' => self::object($input, $required), 'data' => $data];
		};
		$rows = static fn(array $fields, array $extra = []): array => self::object(['rows' => self::list(self::object($fields)), 'total' => ['type' => 'integer']] + $extra);
		$fy = self::object(self::fields('start end', 'id'));
		$add('get_company_context', [], [], self::object(self::fields('company_name cvr base_currency tenant_hash user_hash install_id today', '', 'revenue_hidden') + ['fiscal_year' => $fy, 'fiscal_years' => self::list(self::object(self::fields('start end', 'id', 'active'))), 'user' => self::object(self::fields('name', 'language')), 'permissions' => self::list(['type' => 'string'])]));
		$add('get_memory', [], [], self::object(['company' => self::list(self::object(self::fields('fact created_at', 'id'))), 'user' => self::list(self::object(self::fields('fact created_at', 'id'))), 'revision' => $s, 'caps' => self::object(self::fields('', 'company_used company_cap user_used user_cap'))]));
		$add('search_accounts', $page + ['query' => $s, 'kind' => $enum(['pl', 'balance', 'all'])], [], $rows(self::fields('account name kind vat_code', '', 'closed'), ['revenue_hidden' => $b]));
		$add('get_account_balance', ['account' => $account] + $range, ['account'], self::object(self::fields('account name kind from to currency', '', 'revenue_hidden', 'opening debit credit movement closing')));
		$add('list_ledger_entries', $page + ['account' => $account] + $range, ['account'], $rows(self::fields('date text currency', 'bilag', '', 'debit credit') + ['journal_id' => ['type' => ['integer', 'null']]], ['currency' => $s, 'revenue_hidden' => $b]));
		$add('get_trial_balance', $page + $range + ['include_zero' => $b], [], $rows(self::fields('account name kind currency', '', '', 'opening debit credit closing'), ['currency' => $s, 'revenue_hidden' => $b]));
		$journal = self::fields('date note status created_by', 'journal_id lines');
		$add('list_journals', $page + ['status' => $enum(['open', 'posted', 'all']), 'query' => $s], [], $rows($journal));
		$side = self::object(self::fields('ledger account name'));
		$add('get_journal', ['journal_id' => $id], ['journal_id'], self::object(self::fields('date note status revision', 'journal_id') + ['lines' => self::list(self::object(self::fields('date text currency', 'line_id bilag', '', 'amount') + ['debit' => $side, 'credit' => $side]), 200)]));
		// amount_base is null (with rate_unavailable true) when a foreign row's exchange rate cannot be resolved.
		$open = self::fields('party_account party_name invoice date currency', 'party_id item_id days_overdue', 'converted rate_unavailable', 'amount') + ['amount_base' => ['type' => ['string', 'null'], 'pattern' => '^-?[0-9]+\\.[0-9]{2}$'], 'due_date' => ['type' => ['string', 'null']]];
		$group = self::fields('party_account party_name currency', 'party_id items rate_unavailable_items', 'converted', 'open_amount overdue_amount') + ['oldest_due_date' => ['type' => ['string', 'null']]];
		// Conditional rows share a closed union allowlist; required identity fields are common.
		$openSchema = self::object($open + $group, ['party_id', 'party_account', 'party_name', 'currency', 'converted']);
		$add('list_open_items', $page + ['party_type' => $enum(['debtor', 'creditor']), 'overdue_only' => $b, 'party_account' => $account, 'group_by_party' => $b], ['party_type'], self::object(['rows' => self::list($openSchema), 'total' => ['type' => 'integer'], 'totals' => self::object(self::fields('currency', 'parties rate_unavailable_items', '', 'open_amount overdue_amount')), 'converted' => $b]));
		$add('search_parties', $page + ['type' => $enum(['debtor', 'creditor']), 'query' => $s + ['minLength' => 1], 'include_closed' => $b], ['type', 'query'], $rows(self::fields('account name cvr city', 'party_id', 'closed')));
		$recent = self::object(self::fields('art number date currency', 'order_id', '', 'total'));
		$add('get_party', ['party_id' => $id], ['party_id'], self::object(self::fields('type account name cvr ean address zip city country email phone currency', 'party_id payment_terms_days rate_unavailable_items', 'closed', 'credit_limit open_amount overdue_amount') + ['recent_documents' => self::list($recent, 5), 'converted' => $b]));
		$invoice = self::fields('art number invoice_number date party_account party_name currency status', 'order_id', 'paid', 'net vat total');
		$add('search_invoices', $page + $range + ['direction' => $enum(['sales', 'purchases']), 'status' => $enum(['offer', 'order', 'invoiced', 'all']), 'party_account' => $account, 'query' => $s], ['direction'], $rows($invoice));
		$line = self::fields('item_no text quantity discount_pct vat_rate account currency', 'line', '', 'unit_price');
		$add('get_invoice', ['order_id' => $id], ['order_id'], self::object($invoice + ['due_date' => ['type' => ['string', 'null']], 'party_id' => ['type' => 'integer'], 'reference' => $s, 'lines' => self::list(self::object($line))]));
		$add('search_products', $page + ['query' => $s, 'low_stock_only' => $b], [], $rows(self::fields('item_no text unit stock min_stock currency', 'product_id', 'low_stock', 'price cost')));
		$add('list_inbox_items', $page + ['status' => $enum(['open', 'applied', 'rejected', 'expired', 'undone', 'all']), 'kind' => $s], [], self::object(['rows' => self::list(self::item(false)), 'total' => ['type' => 'integer']], ['rows']));
		$add('get_inbox_item', ['item_id' => $id], ['item_id'], self::item());
		$request = ['tainted' => $b, 'request_id' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64]];
		$postingSide = self::object(['ledger' => $enum(['account', 'customer', 'supplier']), 'account' => $account]);
		$journalLine = self::object(['description' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100], 'debit' => $postingSide, 'credit' => $postingSide, 'amount' => ['type' => 'string', 'pattern' => '^[0-9]{1,12}(\.[0-9]{1,2})?$']], ['description', 'amount']);
		$add('propose_journal_entry', $request + ['kladde_id' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 2147483647], 'note' => ['type' => 'string', 'maxLength' => 60], 'date' => $date, 'lines' => self::list($journalLine, 20) + ['minItems' => 1], 'explanation' => $s, 'citations' => self::list(['type' => 'string', 'maxLength' => 100], 10), 'tainted' => $b], ['request_id', 'date', 'lines', 'explanation'], self::item());
		$reason = ['type' => 'string', 'maxLength' => 255];
		$add('propose_memory_fact', $request + ['scope' => $enum(['company', 'user']), 'fact' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 300], 'reason' => $reason], ['request_id', 'scope', 'fact'], self::item());
		$add('propose_memory_retraction', $request + ['memory_id' => $id, 'reason' => $reason], ['request_id', 'memory_id'], self::item());
		$add('create_followup_task', $request + ['title' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 120], 'details' => ['type' => 'string', 'maxLength' => 1000], 'due_date' => $date, 'screen' => $s], ['request_id', 'title'], self::item());

		// Companion schemas are exhaustive allowlists, including nested data objects.
		$short = static fn(int $max, int $min = 0): array => ['type' => 'string', 'minLength' => $min, 'maxLength' => $max];
		$nullable = static fn(array $schema): array => array_replace($schema, ['type' => array_merge((array)$schema['type'], ['null'])]);
		$moneyInput = ['type' => 'string', 'pattern' => '^[0-9]{1,12}(\.[0-9]{1,2})?$'];
		$money = ['type' => 'string', 'pattern' => '^-?[0-9]+\.[0-9]{2}$'];
		$vatCode = ['type' => 'string', 'pattern' => '^([A-Z][0-9]+)?$', 'maxLength' => 8];
		$ruleVatCode = ['type' => 'string', 'pattern' => '^(none|[A-Z][0-9]+)?$', 'maxLength' => 8];
		$partyInput = self::object(['ledger' => $enum(['customer', 'supplier']), 'account' => $account]);
		$common = $request + ['explanation' => $s, 'citations' => self::list($short(100), 10)];
		$commonRequired = ['request_id', 'explanation'];
		$documentRef = ['type' => 'string', 'pattern' => '^pool:[0-9]{1,15}$'];
		$invoiceNumber = ['type' => 'string', 'minLength' => 1, 'maxLength' => 30, 'pattern' => '^[A-Za-z0-9 ._;/-]+$'];
		$description = $short(100, 1);
		$kladde = ['kladde_id' => $id];
		$out['propose_journal_entry']['input']['properties']['document_ref'] = $documentRef;
		$lineProperties =& $out['propose_journal_entry']['input']['properties']['lines']['items']['properties'];
		$lineProperties += ['invoice' => $invoiceNumber, 'due_date' => $date, 'vat_free' => $b];
		foreach (['debit', 'credit'] as $sideName) {
			$lineProperties[$sideName]['properties']['vat_code'] = $vatCode;
		}
		unset($lineProperties);
		$match = self::object(['type' => $enum(['supplier', 'customer', 'text']), 'value' => $short(100, 1)]);
		$ruleFields = self::fields('account account_name vat_code created_at', 'rule_id uses_90d');
		$ruleFields += ['match' => self::object(self::fields('type value label')), 'description' => $nullable($s), 'note' => $nullable($s), 'approved_by_name' => $nullable($s)];
		$add('list_posting_rules', $page + ['status' => $enum(['active', 'retired', 'all'])], [], $rows($ruleFields));
		$suggestSide = $nullable(self::object(['ledger' => $enum(['account', 'customer', 'supplier'])] + self::fields('account name vat_code')));
		$suggestion = self::object([
			'source' => $enum(['rule', 'history']), 'rule_id' => $nullable($id), 'confidence' => $enum(['high', 'medium', 'low']),
			'debit' => $suggestSide, 'credit' => $suggestSide, 'vat_free' => $b, 'description' => $description,
			'basis' => self::object(['count' => ['type' => 'integer'], 'last_date' => $nullable($date), 'line_ids' => self::list($id)]),
		]);
		$duplicate = self::object(self::fields('date description screen', 'line_id kladde_id bilag', 'posted', 'amount'));
		$add('suggest_posting', ['description' => $description, 'amount' => $moneyInput, 'date' => $date, 'party' => self::object($partyInput['properties'] + ['side' => $enum(['debit', 'credit'])], ['ledger', 'account']), 'invoice' => $short(30), 'exclude_line_id' => $id] + $kladde, ['description'], self::object(['suggestions' => self::list($suggestion, 3), 'duplicates' => self::list($duplicate, 5)]));
		$add('propose_posting_rule', $common + ['match' => $match, 'account' => $account, 'vat_code' => $ruleVatCode, 'description' => $short(100), 'replaces_rule_id' => $id], array_merge($commonRequired, ['match', 'account']), self::item());
		$add('propose_rule_retirement', $common + ['rule_id' => $id, 'reason' => $short(255)], array_merge($commonRequired, ['rule_id']), self::item());
		$documentFields = self::fields('ref filename currency invoice_number description created_at', '', 'extracted manually_edited');
		$documentFields += [
			'source' => $enum(['pool', 'journal', 'purchase_order', 'sales_order']),
			'attached_to' => $nullable(self::object(self::fields('type label screen', 'id'))),
			'date' => $nullable($date), 'amount' => $nullable($money),
			'vendor' => $nullable(self::object(['name' => $s, 'cvr' => $s, 'match' => $nullable($s), 'supplier_account' => $nullable($account), 'supplier_name' => $nullable($s)])),
		];
		$add('list_documents', $page + $range + ['status' => $enum(['pool', 'attached', 'all']), 'query' => $s], [], $rows($documentFields));
		$add('get_document', ['ref' => ['type' => 'string', 'pattern' => '^(pool|doc):[0-9]{1,15}$']], ['ref'], self::object($documentFields + ['suggestion' => $nullable($suggestion)]));
		$supplierLine = self::object(['description' => $description, 'account' => $account, 'amount' => $moneyInput, 'vat_code' => $vatCode], ['description', 'account', 'amount']);
		$add('propose_supplier_invoice', $common + $kladde + ['supplier_account' => $account, 'invoice_number' => $invoiceNumber, 'date' => $date, 'due_date' => $date, 'document_ref' => $documentRef, 'lines' => self::list($supplierLine, 10) + ['minItems' => 1]], array_merge($commonRequired, ['supplier_account', 'invoice_number', 'date', 'lines']), self::item());
		$add('propose_payment_registration', $common + $kladde + ['party' => $partyInput, 'amount' => $moneyInput, 'date' => $date, 'bank_account' => $account, 'invoice_numbers' => self::list($invoiceNumber, 10) + ['description' => 'up to 10 invoice numbers, 200 characters joined']], array_merge($commonRequired, ['party', 'amount', 'date', 'bank_account']), self::item());
		$add('propose_party', $common + [
			'ledger' => $enum(['customer', 'supplier']), 'name' => $short(100, 2),
			'cvr' => ['type' => 'string', 'pattern' => '^[0-9]{8}$'], 'ean' => ['type' => 'string', 'pattern' => '^[0-9]{13}$'],
			'address' => $short(60), 'zip' => $short(10), 'city' => $short(40), 'country' => $short(40),
			'email' => ['type' => 'string', 'maxLength' => 100, 'pattern' => '^[^\s@]+@[^\s@]+\.[^\s@]+$'], 'phone' => $short(20), 'contact' => $short(60),
			'payment_terms' => self::object(['type' => $enum(['netto', 'lobende_maaned', 'kontant']), 'days' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 120]]), 'group' => $id,
		], array_merge($commonRequired, ['ledger', 'name']), self::item());
		$purchaseLine = self::object(['product_id' => $id, 'item_no' => $short(100, 1), 'description' => $description, 'quantity' => ['type' => 'string', 'pattern' => '^(?!0+(\.0+)?$)[0-9]{1,12}(\.[0-9]{1,3})?$'], 'unit_price' => $moneyInput], ['quantity']);
		$add('propose_purchase_order', $common + ['supplier_account' => $account, 'lines' => self::list($purchaseLine, 50) + ['minItems' => 1], 'delivery_date' => $date, 'reference' => $short(40), 'note' => $short(200)], array_merge($commonRequired, ['supplier_account', 'lines']), self::item());
		$add('propose_reminder', $common + ['customer_account' => $account, 'open_item_ids' => self::list($id, 30) + ['minItems' => 1], 'level' => ['type' => 'integer', 'const' => 1], 'note' => $short(200)], array_merge($commonRequired, ['customer_account']), self::item());
		$overview = [
			'date' => $date,
			'open_items' => self::object(['count' => ['type' => 'integer'], 'interrupts' => self::list(self::object(self::fields('title screen', 'item_id'))), 'by_kind' => self::list(self::object(self::fields('kind label', 'count')))]),
			'overdue_debtors' => self::object(self::fields('currency', 'count', 'converted', 'amount')),
			'open_journals' => self::object(['count' => ['type' => 'integer'], 'oldest_date' => $nullable($date)]),
			'vat' => self::object(['period' => self::object($range), 'deadline' => $date, 'days_left' => ['type' => 'integer'], 'missing_documents' => ['type' => 'integer'], 'note' => $s]),
			'pool_documents' => self::object(self::fields('', 'count')), 'low_stock' => self::object(self::fields('', 'count')), 'nothing_to_do' => $b,
		];
		$add('get_daily_overview', [], [], self::object($overview, ['date', 'nothing_to_do']));
		$vatFields = self::fields('from to currency note', '', 'period_closed revenue_hidden', 'sales_vat purchase_vat payable');
		$vatFields['codes'] = self::list(self::object(['code' => $s, 'kind' => $enum(['sales', 'purchase', 'eu_purchase', 'foreign_service']), 'rate' => $s, 'vat_account' => $account, 'vat_amount' => $money, 'currency' => $s]));
		$add('get_vat_summary', $range, ['from', 'to'], self::object($vatFields));
		$periodFields = self::fields('from to', 'unposted_lines lines_without_document');
		$periodFields += ['open_journals' => self::list(self::object(self::fields('note date screen', 'journal_id lines')), 20), 'vat_months' => self::list(self::object(['month' => ['type' => 'string', 'pattern' => '^[0-9]{4}-[0-9]{2}$'], 'closed' => $b])), 'last_posted_date' => $nullable($date)];
		$add('get_period_status', $range, ['from', 'to'], self::object($periodFields));
		// Companion part 2: closed allowlists derived from E2-E7, also for nested data.
		$decimal = ['type' => 'string', 'pattern' => '^-?[0-9]+(\.[0-9]{1,3})?$'];
		$quantity = ['type' => 'string', 'pattern' => '^[0-9]{1,12}(\.[0-9]{1,3})?$'];
		$positiveQuantity = $purchaseLine['properties']['quantity'];
		$batchId = ['type' => 'string', 'pattern' => '^[a-f0-9]{32}$'];
		$bankStatus = $enum(['new', 'proposed', 'duplicate', 'ignored']);
		$bankMatch = self::object([
			'party' => $nullable(self::object(['ledger' => $enum(['customer', 'supplier']), 'account' => $account, 'name' => $s])),
			'account' => $nullable(self::object(['account' => $account, 'name' => $s])),
			'invoice' => $nullable($short(100)), 'confidence' => $enum(['high', 'medium']),
			'vat_free' => $b, 'vat_code' => $short(8),
		], ['party','account','invoice','confidence']);
		$bankLine = self::fields('date description currency', 'line_id') + ['amount' => $decimal, 'status' => $bankStatus];
		$add('import_bank_statement', ['bank_account' => $account, 'content' => $short(200000, 1), 'filename' => $short(255)], ['bank_account', 'content'], self::object([
			'batch_id' => $batchId, 'lines' => self::list(self::object($bankLine), 5000), 'duplicates' => ['type' => 'integer'], 'warnings' => self::list($s),
		]));
		$add('list_bank_lines', ['batch_id' => $batchId, 'status' => $bankStatus], ['batch_id'], $rows($bankLine + ['match' => $nullable($bankMatch)]));
		$add('propose_bank_entries', $common + $kladde + ['batch_id' => $batchId, 'line_ids' => self::list($id, 200) + ['minItems' => 1]], array_merge($commonRequired, ['batch_id']), self::item());

		$checkDetail = self::object([
			'difference' => $money, 'currency' => $s, 'date' => $date, 'account' => $account, 'ledger' => $enum(['account', 'customer', 'supplier']),
			'vat_code' => $s, 'expected_vat_code' => $s, 'median' => $money, 'invoice' => $s, 'journal_id' => $id, 'line_id' => $id,
		], []);
		$finding = self::object([
			'code' => $s, 'severity' => $enum(['error', 'warning']), 'line_ids' => self::list($id),
			'bilag' => $nullable(['type' => 'integer']), 'message_da' => $s, 'detail' => $checkDetail,
		]);
		$add('check_journal', ['journal_id' => $id], ['journal_id'], self::object([
			'journal_id' => $id, 'status' => $s, 'ready' => $b, 'errors' => self::list($finding), 'warnings' => self::list($finding),
		]));
		$partyType = $enum(['debtor', 'creditor']);
		$settlementParty = self::object(['party_type' => $partyType, 'account' => $account, 'name' => $s, 'party_id' => $id]);
		$add('suggest_settlements', ['party_type' => $partyType, 'party_account' => $account, 'limit' => $page['limit']], ['party_type'], self::object([
			'groups' => self::list(self::object(['party' => $settlementParty, 'item_ids' => self::list($id, 10) + ['minItems' => 2], 'amounts' => self::list($money, 10) + ['minItems' => 2], 'total' => $money, 'currency' => $s, 'reason' => $s])),
		]));
		$add('propose_settlement', $common + ['party_type' => $partyType, 'party_account' => $account, 'item_ids' => self::list($id, 10) + ['minItems' => 2]], array_merge($commonRequired, ['party_type', 'party_account', 'item_ids']), self::item());

		$matchInputLine = self::object(['item_no' => $short(100), 'description' => $short(255), 'quantity' => $quantity, 'unit_price' => $moneyInput], []);
		$matchLine = self::object(self::fields('item_no description currency', 'line_id') + [
			'ordered' => $decimal, 'received' => $decimal, 'remaining' => $decimal, 'order_price' => $money,
			'invoice_price' => $nullable($money), 'quantity_difference' => $nullable($decimal),
		]);
		$purchaseDifference = self::object(['line_id' => $nullable($id), 'field' => $enum(['quantity', 'unit_price', 'total']), 'order_value' => $decimal, 'invoice_value' => $decimal, 'difference' => $decimal, 'currency' => $s]);
		$add('match_purchase_order', ['supplier_account' => $account, 'document_ref' => $documentRef, 'invoice_number' => $invoiceNumber, 'order_number' => $short(30, 1), 'lines' => self::list($matchInputLine)], [], self::object([
			'orders' => self::list(self::object(['order_id' => $id, 'order_number' => $s, 'score' => ['type' => 'number'], 'lines' => self::list($matchLine), 'differences' => self::list($purchaseDifference)])),
		]));
		$stageLine = self::object(['line_id' => $id, 'receive_quantity' => $quantity, 'unit_price' => $moneyInput], ['line_id']);
		$add('propose_purchase_invoice_stage', $common + ['order_id' => $id, 'invoice_number' => $invoiceNumber, 'invoice_date' => $date, 'lines' => self::list($stageLine) + ['minItems' => 1]], array_merge($commonRequired, ['order_id', 'invoice_number', 'invoice_date', 'lines']), self::item());
		$reorder = self::object(self::fields('item_no text', 'product_id') + [
			'per_day' => $decimal, 'on_hand' => $decimal, 'on_order' => $decimal, 'cover_days' => $nullable($decimal), 'suggest_qty' => $decimal,
			'supplier' => $nullable(self::object(['account' => $account, 'name' => $s])),
		]);
		$add('suggest_reorders', ['days' => ['type' => 'integer', 'minimum' => 30, 'maximum' => 365], 'lead_days' => ['type' => 'integer', 'minimum' => 0]], [], $rows($reorder['properties']));
		$salesLine = self::object($purchaseLine['properties'] + ['discount_pct' => ['type' => 'string', 'pattern' => '^(100(\.0{1,3})?|[0-9]{1,2}(\.[0-9]{1,3})?)$']], ['quantity']);
		$add('propose_sales_order', $common + ['customer_account' => $account, 'type' => $enum(['offer', 'order']), 'lines' => self::list($salesLine, 50) + ['minItems' => 1], 'reference' => $short(40), 'note' => $short(200), 'delivery_date' => $date], array_merge($commonRequired, ['customer_account', 'type', 'lines']), self::item());
		$add('propose_credit_note', $common + ['invoice_order_id' => $id, 'lines' => self::list(self::object(['line_id' => $id, 'quantity' => $positiveQuantity])) + ['minItems' => 1], 'reason' => $short(200, 1)], array_merge($commonRequired, ['invoice_order_id', 'reason']), self::item());
		$statementRow = self::object(self::fields('invoice date currency', 'item_id', '', 'amount') + ['due_date' => $nullable($date), 'classification_uncertain' => $b], ['invoice','date','currency','item_id','amount','due_date']);
		$add('get_customer_statement', ['party_id' => $id, 'account' => $account] + $range, [], self::object([
			'party' => self::object(self::fields('account name', 'party_id')), 'from' => $date, 'to' => $date, 'currency' => $s,
			'open_items' => self::list($statementRow), 'payments' => self::list($statementRow), 'invoices' => self::list($statementRow),
			'opening_balance' => $money, 'closing_balance' => $money, 'overdue_sum' => $money, 'converted' => $b,
		], ['party', 'from', 'to', 'currency', 'open_items', 'payments', 'invoices', 'opening_balance', 'closing_balance', 'overdue_sum']));

		$comparisonRow = self::object(self::fields('key name currency', '', '', 'a b change') + ['change_pct' => $nullable($decimal)]);
		$add('compare_periods', ['a_from' => $date, 'a_to' => $date, 'b_from' => $date, 'b_to' => $date, 'level' => $enum(['account', 'group']), 'only_changed' => $b, 'limit' => $page['limit']], ['a_from', 'a_to', 'b_from', 'b_to'], $rows($comparisonRow['properties'], ['currency' => $s, 'revenue_hidden' => $b]));
		$packOpen = self::object(self::fields('party_type party_account party_name invoice date currency', 'item_id', '', 'amount') + ['due_date' => $nullable($date)]);
		$packTrialRow = self::object(self::fields('account name kind currency', '', '', 'opening debit credit closing'));
		$packTrial = self::object(['rows' => self::list($packTrialRow), 'totals' => self::object(self::fields('currency', '', '', 'opening debit credit closing')), 'revenue_hidden' => $b]);
		$packVat = self::object(['quarter' => ['type' => 'integer', 'minimum' => 1]] + $vatFields);
		$packLinks = self::list(self::object(self::fields('label screen')));
		$add('get_year_end_pack', ['fiscal_year' => $id], [], self::object([
			'fiscal_year' => $fy, 'currency' => $s, 'revenue_hidden' => $b, 'trial_balance' => $packTrial,
			'open_items' => self::list($packOpen), 'vat' => self::list($packVat),
			'unposted_journals' => self::list(self::object($journal + ['screen' => $s])),
			'periods_closed' => $periodFields['vat_months'], 'notes' => self::list($s), 'links' => $packLinks,
		]));
		$tillRow = self::object(self::fields('currency', 'till receipts', '', 'turnover_net turnover_gross refunds') + [
			'payments' => self::list(self::object(self::fields('method currency', '', '', 'amount'))),
			'expected' => $nullable($money), 'counted' => $nullable($money), 'difference' => $nullable($money),
			'cash_difference_postings' => self::list(self::object(self::fields('date account currency', 'bilag', '', 'amount'))),
		]);
		$add('get_till_summary', ['date' => $date, 'till' => $id], ['date'], self::object(['date' => $date, 'currency' => $s, 'rows' => self::list($tillRow), 'revenue_hidden' => $b]));
		$countRow = self::object(self::fields('item_no text currency', 'product_id warehouse variant_id', 'posted', 'value_difference') + ['expected' => $decimal, 'counted' => $decimal, 'difference' => $decimal]);
		$fefoRow = self::object(self::fields('item_no text expiry_date screen', 'product_id batch_id days_left') + ['quantity' => $decimal]);
		$add('get_stock_count', ['date' => $date], [], self::object(['date' => $nullable($date), 'rows' => self::list($countRow), 'fefo' => self::list($fefoRow)]));
		$add('check_master_data', [], [], $rows(self::fields('kind label screen')));

		$slug = ['type' => 'string', 'pattern' => '^[a-z0-9-]{3,40}$'];
		$add('list_procedures', [], [], $rows(['slug' => $slug, 'title' => $s, 'description' => $s, 'version' => ['type' => 'integer']]));
		// Check arguments use the closed union of read-tool input fields; placeholders may be strings.
		$checkProperties = [];
		foreach ($out as $name => $schemas) {
			if (str_starts_with($name, 'propose_') || $name === 'create_followup_task') {
				continue;
			}
			foreach ((array)$schemas['input']['properties'] as $key => $property) {
				$checkProperties[$key] = ['type' => array_values(array_unique(array_merge((array)($property['type'] ?? 'string'), ['string'])))] + (isset($property['properties']) ? ['properties' => $property['properties'], 'additionalProperties' => false] : []);
			}
		}
		$checkProperties['slug'] = $s;
		$step = self::object(['id' => $s, 'title' => $s, 'check_tool' => $s, 'check_args' => self::object($checkProperties, []), 'screen' => $s, 'doc' => $s], ['id', 'title']);
		$add('get_procedure', ['slug' => $slug], ['slug'], self::object(['slug' => $slug, 'title' => $s, 'version' => ['type' => 'integer'], 'body' => ['type' => 'string'], 'steps' => self::list($step)]));
		$add('report_problem', $common + ['summary' => $short(200, 10), 'details' => $short(2000), 'screen' => $short(200), 'category' => $enum(['bug', 'question'])], array_merge($commonRequired, ['summary', 'details', 'category']), self::item());
		return self::describe($out);
	}
	/** Property descriptions, by property name with per-tool overrides (20261002 CL/LH). @return array */
	private static function describe(array $out): array
	{
		$common = [
			'limit' => 'Maximum rows to return, 1-100 (default 25).',
			'offset' => 'Rows to skip, for paging.',
			'from' => 'Start date YYYY-MM-DD inside the active fiscal year (see get_company_context).',
			'to' => 'End date YYYY-MM-DD inside the active fiscal year.',
			'account' => 'Finance account number from search_accounts (digits only).',
			'query' => 'Free text: words, or the start of a number.',
			'party_account' => 'Customer or supplier account number from search_parties.',
			'party_id' => 'Party id from search_parties (not the account number).',
			'journal_id' => 'Journal (kassekladde) id from list_journals.',
			'order_id' => 'Order id from search_invoices (not the invoice number).',
			'item_id' => 'Inbox item id from list_inbox_items.',
			'memory_id' => 'Memory fact id from get_memory.',
			'request_id' => 'Unique id for this proposal. Reusing it returns the same inbox item instead of a duplicate.',
			'explanation' => 'Why this is proposed, in the user\'s language. Shown to the person who approves it.',
			'citations' => 'Citation ids from tool results in this conversation, e.g. saldi:journal:12.',
			'tainted' => 'true when the proposal builds on text read from the user\'s data.',
			'reason' => 'Short reason, shown to the approver.',
			'include_zero' => 'Also list accounts without movements.',
			'include_closed' => 'Also return closed (inactive) parties.',
			'overdue_only' => 'Only items past their due date.',
			'group_by_party' => 'One row per customer or supplier with totals instead of one row per item.',
			'low_stock_only' => 'Only products below their minimum stock.',
		];
		$specific = [
			'search_accounts' => ['kind' => "'pl' = profit and loss accounts, 'balance' = balance sheet accounts, 'all' = both."],
			'list_journals' => ['status' => "'open' = not posted yet, 'posted' = posted, 'all'."],
			'list_open_items' => ['party_type' => "'debtor' = customers who owe us, 'creditor' = suppliers we owe."],
			'search_parties' => ['type' => "'debtor' = customer, 'creditor' = supplier.", 'query' => 'Name, account number or CVR.'],
			'search_invoices' => ['direction' => "'sales' = customer orders and invoices, 'purchases' = supplier orders and invoices.", 'status' => "'offer', 'order', 'invoiced' or 'all'."],
			'list_inbox_items' => ['status' => 'Item status; default open.', 'kind' => 'Item kind, e.g. journal.draft_entry.'],
			'propose_journal_entry' => [
				'kladde_id' => 'Existing open journal id from list_journals. Omit to create a new journal.',
				'note' => 'Note for a new journal.',
				'date' => 'Posting date YYYY-MM-DD inside the active fiscal year and not in a closed VAT period.',
				'lines' => 'One to twenty lines. Each line has a debit side, a credit side, or both. A line with both sides balances itself; lines with only one side must balance within the draft (one-sided debits equal one-sided credits), because the whole draft becomes one voucher (bilag).',
			],
			'propose_memory_fact' => ['scope' => "'user' = private to this user; 'company' = shared, approved by an administrator.", 'fact' => 'A short fact the user stated, max 300 characters. Never ERP data, secrets or passwords.'],
			'create_followup_task' => ['title' => 'Task title.', 'details' => 'Optional details.', 'due_date' => 'Optional due date YYYY-MM-DD.', 'screen' => 'Optional Saldi screen link taken from a citation, e.g. finans/kassekladde.php?kladde_id=12.'],
		];
		$line = [
			'description' => 'Line text shown in the journal.',
			'amount' => "Positive amount with '.' as decimal separator, e.g. '1250.00'. VAT follows the account's VAT code when the journal is posted.",
			'debit' => 'Debit side.',
			'credit' => 'Credit side.',
		];
		$side = [
			'ledger' => "'account' = finance account (from search_accounts), 'customer' = customer account (search_parties type debtor), 'supplier' = supplier account (search_parties type creditor).",
			'account' => 'Account number in that ledger (digits only).',
		];
		foreach ($out as $tool => &$schemas) {
			foreach ($schemas['input']['properties'] ?? [] as $name => $property) {
				$text = $specific[$tool][$name] ?? $common[$name] ?? null;
				if ($text !== null && is_array($property)) {
					$schemas['input']['properties'][$name]['description'] = $text;
				}
			}
			if ($tool === 'propose_journal_entry') {
				foreach ($line as $name => $text) {
					$schemas['input']['properties']['lines']['items']['properties'][$name]['description'] = $text;
				}
				foreach (['debit', 'credit'] as $s) {
					foreach ($side as $name => $text) {
						$schemas['input']['properties']['lines']['items']['properties'][$s]['properties'][$name]['description'] = $text;
					}
				}
			}
		}
		unset($schemas);
		return $out;
	}
}
