<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiToolRegistry.php --- ai/mcp-assistant --- 2026.10.04
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
// Explicit catalogue discovery; handlers retain execution-time authorization.
//
// 20261002 CL/LH Semantic notes for open items and balances in tool descriptions.
// 20261001 CDX/LH Created.
// 20261002 CL/LH Notes on creditor signs, unconvertible rates and balanced journal drafts.
// 20261002 CDX/LH Add companion catalogue and verified evidence references.
// 20261003 CL/LH list_open_items rows carry item_id (openpost id) for reminder proposals.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261003 CL/LH search_invoices note: query matches invoice and order numbers.
// 20261003 CL/LH propose_reminder works from the customer alone.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261004 CDX/LH Report successful customer-statement conversion at the shared tool boundary.
// 20261005 CL/LH get_year_end_pack note: calendar year input and trimmed result.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/../automation/SaldiInboxService.php';
require_once __DIR__ . '/../automation/SaldiMemoryStore.php';
require_once __DIR__ . '/SaldiSchemaValidator.php';
require_once __DIR__ . '/SaldiToolSchemas.php';
require_once __DIR__ . '/SaldiToolDefinition.php';
require_once __DIR__ . '/SaldiToolRuntime.php';
require_once __DIR__ . '/SaldiReadTools.php';
require_once __DIR__ . '/SaldiDraftTools.php';

require_once __DIR__ . '/SaldiRuleTools.php';
require_once __DIR__ . '/SaldiDocumentTools.php';
require_once __DIR__ . '/SaldiWatchTools.php';
require_once __DIR__ . '/SaldiSkillTools.php';
require_once __DIR__ . '/SaldiBankTools.php';
require_once __DIR__ . '/SaldiJournalCheckTools.php';
require_once __DIR__ . '/SaldiSettlementTools.php';
require_once __DIR__ . '/SaldiPurchaseTools.php';
require_once __DIR__ . '/SaldiSalesTools.php';
require_once __DIR__ . '/SaldiInsightTools.php';

final class SaldiToolRegistry
{
	private array $tools = [];
	/** Construct the complete base and companion catalogue. */
	public static function default(): self
	{
		// Semantics a model must not misread (20261002 CL/LH).
		$notes = [
			'list_open_items' => ' Amounts are in the base currency. overdue_amount counts only items past their due date; credit notes that are not yet due reduce open_amount but not overdue_amount, so overdue_amount can exceed open_amount. totals sums every matching item, not just the returned page. For creditors, amounts we owe are negative; positive amounts are prepayments or credit notes. Rows are sorted largest balance first. item_id identifies the open item (optional input for propose_reminder). A foreign-currency item whose exchange rate cannot be resolved has rate_unavailable true and amount_base null, and is left out of the base-currency sums (counted in rate_unavailable_items).',
			'propose_journal_entry' => ' The whole draft becomes one voucher (bilag), so it must balance: one-sided debit lines must equal one-sided credit lines.',
			'get_account_balance' => ' opening = primo plus net movements from the fiscal-year start to the day before from; movement = debit minus credit in the range; closing = opening plus movement.',
			'get_trial_balance' => ' Same opening/movement/closing formulas as get_account_balance, per account.',
			'search_invoices' => ' query matches the party name, the invoice number or the order number, so an invoice can be found by its number alone.',
			'get_daily_overview' => ' Use it only when the user asks what to do today or for an overview of the day; never as a preamble to other questions.',
			'get_year_end_pack' => ' fiscal_year may be the fiscal-year number or the calendar year it starts in (e.g. 2026). Returns the non-zero accounts with the largest balances, the largest open items and VAT per quarter; the complete pack with CSV files is the Revisorpakke page (links).',
			'search_parties' => ' An exact account number or CVR match is returned first, so "debitor 7" or "kreditor 7" means account number 7: search with query "7" and take the exact match.',
			'suggest_posting' => ' Suggestions come from approved posting rules first, then from how similar lines were booked before. They are hints for the user, never bookings. duplicates lists earlier lines with the same party and amount, or the same invoice number.',
			'propose_payment_registration' => ' Records a payment that has already happened (seen on the bank statement) as a journal line. It does not pay anything. Accepts up to 10 invoice numbers, 200 characters joined.',
			'propose_supplier_invoice' => ' Builds journal lines from a supplier invoice. Use get_document first when a document ref is known. The document is attached to the first line when approved.',
			'propose_purchase_order' => ' Creates an order proposal (status forslag). Nothing is sent to the supplier.',
			'propose_reminder' => ' Creates a reminder (rykker) document for a customer\'s overdue invoices. open_item_ids is optional: without it every eligible overdue item of the customer is included (past the grace days, not already on a reminder, max 30). Nothing is sent or posted; the user prints or sends it in Saldi. One customer per call.',
			'get_procedure' => ' Step-by-step procedures written by Danosoft. Run each step\'s check_tool with check_args to see the user\'s actual state, then say which steps are done and which remain.',

		];
		$notes += [
			'import_bank_statement' => ' stages lines for matching; changes nothing in the books. Requires journal.draft at execution.',
			'propose_bank_entries' => ' Each bank line becomes a separate voucher in an unposted journal. Medium-confidence matches are included only when explicitly selected. Nothing is posted.',
			'propose_settlement' => ' Exact balanced sets only, for one party and currency. Nothing is paid or posted; a human approves settlement in Saldi.',
			'propose_purchase_invoice_stage' => ' Stages invoice number, date, receive quantities and prices on the purchase order. A human presses Modtag and Bogfør; no goods are received or invoice posted by this proposal.',
			'propose_sales_order' => ' Creates an offer or order draft after human approval. Nothing is sent or invoiced.',
			'propose_credit_note' => ' Creates a credit-note draft after human approval. Nothing is posted or sent.',
			'get_customer_statement' => ' Read a statement for a message the user sends themselves. Balances are in the tenant base currency; converted reports successful base-currency conversion. Individual entries retain their original currency. Never sends a message.',
			'get_till_summary' => ' Read-only; never calls cashCountHistoryLoadAndRepair. Requires revenue visibility.',
			'compare_periods' => ' Debit minus credit, revenue negative, excluding primo. Profit and loss rows are omitted when revenue is hidden.',
		];
		$metadata = [
			'get_company_context' => ['Company context', ['company.read'], 'company', 'adresser (own company), grupper, brugere and settings'],
			'get_memory' => ['Approved memory', ['memory.read'], 'memory', 'assistant_memory'],
			'search_accounts' => ['Search accounts', ['accounts.read'], 'accounts', 'kontoplan'],
			'get_account_balance' => ['Account balance', ['ledger.read'], 'balance', 'kontoplan and transaktioner'],
			'list_ledger_entries' => ['Ledger entries', ['ledger.read'], 'ledger', 'kontoplan and transaktioner'],
			'get_trial_balance' => ['Trial balance', ['ledger.read'], 'trial', 'kontoplan and transaktioner'],
			'list_journals' => ['List journals', ['journal.read'], 'journals', 'kladdeliste and kassekladde'],
			'get_journal' => ['Read journal', ['journal.read'], 'journal', 'kladdeliste, kassekladde, kontoplan and adresser'],
			'list_open_items' => ['Open items', ['debtors.read', 'creditors.read'], 'openItems', 'openpost and adresser'],
			'search_parties' => ['Search parties', ['debtors.read', 'creditors.read'], 'parties', 'adresser'],
			'get_party' => ['Read party', ['debtors.read', 'creditors.read'], 'party', 'adresser, openpost and permitted ordrer'],
			'search_invoices' => ['Search invoices', ['sales.read', 'purchases.read'], 'invoices', 'ordrer'],
			'get_invoice' => ['Read invoice', ['sales.read', 'purchases.read'], 'invoice', 'ordrer and ordrelinjer'],
			'search_products' => ['Search products', ['products.read'], 'products', 'varer'],
			'list_inbox_items' => ['List inbox items', ['inbox.read'], 'inboxList', 'visible inbox_items and kind cards'],
			'get_inbox_item' => ['Read inbox item', ['inbox.read'], 'inboxItem', 'visible inbox_items and kind cards'],
			'propose_journal_entry' => ['Propose journal entry', ['journal.draft'], 'journal.draft_entry', 'kontoplan, adresser, grupper, kladdeliste, kassekladde and period locks'],
			'propose_memory_fact' => ['Propose memory fact', ['memory.propose'], 'memory.fact', 'assistant_memory'],
			'propose_memory_retraction' => ['Propose memory retraction', ['memory.propose'], 'memory.retract', 'visible assistant_memory'],
			'create_followup_task' => ['Create follow-up task', ['followup.draft'], 'followup.task', 'inbox_items'],
			'list_posting_rules' => ['List posting rules', ['rules.read'], 'SaldiRuleTools::listRules', 'posting_rules, kontoplan and assist_events'],
			'suggest_posting' => ['Suggest posting', ['journal.read'], 'SaldiRuleTools::suggest', 'posting_rules, kassekladde, kontoplan and adresser'],
			'propose_posting_rule' => ['Propose posting rule', ['journal.draft'], 'rule.posting', 'posting_rules, kontoplan and adresser'],
			'propose_rule_retirement' => ['Propose rule retirement', ['journal.draft'], 'rule.retire', 'posting_rules'],
			'list_documents' => ['List documents', ['documents.read'], 'SaldiDocumentTools::listDocuments', 'pool_files and visible documents'],
			'get_document' => ['Read document', ['documents.read'], 'SaldiDocumentTools::getDocument', 'pool_files and visible documents'],
			'propose_supplier_invoice' => ['Propose supplier invoice', ['journal.draft'], 'SaldiDocumentTools::proposeSupplierInvoice', 'adresser, kontoplan, pool_files and journal period locks'],
			'propose_payment_registration' => ['Propose payment registration', ['journal.draft'], 'SaldiDocumentTools::proposePaymentRegistration', 'adresser, openpost, kontoplan and journal period locks'],
			'propose_party' => ['Propose party', ['debtors.draft', 'creditors.draft'], 'party.create', 'adresser and groups'],
			'propose_purchase_order' => ['Propose purchase order', ['purchases.draft'], 'purchase_order.draft', 'adresser, varer, vare_lev and groups'],
			'propose_reminder' => ['Propose reminder', ['debtors.draft'], 'reminder.draft', 'adresser, openpost, ordrer and formularer'],
			'get_daily_overview' => ['Daily overview', ['company.read'], 'SaldiWatchTools::overview', 'visible inbox_items, openpost, kladdeliste, pool_files and varer'],
			'get_vat_summary' => ['VAT summary', ['ledger.read'], 'SaldiWatchTools::vatSummary', 'transaktioner, grupper and moms_periode_luk'],
			'get_period_status' => ['Period status', ['journal.read'], 'SaldiWatchTools::periodStatus', 'kladdeliste, kassekladde, documents, transaktioner and moms_periode_luk'],
			'list_procedures' => ['List procedures', ['company.read'], 'SaldiSkillTools::listSkills', 'Danosoft procedure packs'],
			'get_procedure' => ['Read procedure', ['company.read'], 'SaldiSkillTools::getSkill', 'Danosoft procedure packs'],
			'report_problem' => ['Report problem', ['support.draft'], 'support.report', 'only the description supplied by the user'],

			'import_bank_statement' => ['Import bank statement', ['journal.draft'], 'SaldiBankTools::import', 'bank_import_lines and kontoplan'],
			'list_bank_lines' => ['List bank lines', ['journal.read'], 'SaldiBankTools::listLines', 'bank_import_lines'],
			'propose_bank_entries' => ['Propose bank entries', ['journal.draft'], 'SaldiBankTools::propose', 'bank_import_lines, openpost and journal draft checks'],
			'check_journal' => ['Check journal', ['journal.read'], 'SaldiJournalCheckTools::check', 'kladdeliste, kassekladde, kontoplan, adresser and period locks'],
			'suggest_settlements' => ['Suggest settlements', ['debtors.read', 'creditors.read'], 'SaldiSettlementTools::suggest', 'openpost and adresser'],
			'propose_settlement' => ['Propose settlement', ['debtors.draft', 'creditors.draft'], 'settlement.draft', 'openpost and adresser'],
			'match_purchase_order' => ['Match purchase order', ['purchases.read'], 'SaldiPurchaseTools::match', 'ordrer, ordrelinjer, batch_kob and pool_files'],
			'propose_purchase_invoice_stage' => ['Propose purchase invoice stage', ['purchases.draft'], 'purchase_order.invoice_stage', 'ordrer, ordrelinjer and batch_kob'],
			'suggest_reorders' => ['Suggest reorders', ['products.read'], 'SaldiPurchaseTools::reorders', 'varer, batch_salg, batch_kob, ordrer, ordrelinjer and vare_lev'],
			'propose_sales_order' => ['Propose sales order', ['sales.draft'], 'sales_order.draft', 'adresser, varer, groups and pricing'],
			'propose_credit_note' => ['Propose credit note', ['sales.draft'], 'credit_note.draft', 'ordrer, ordrelinjer and VAT settings'],
			'get_customer_statement' => ['Customer statement', ['debtors.read'], 'SaldiSalesTools::statement', 'openpost and adresser'],
			'compare_periods' => ['Compare periods', ['ledger.read'], 'SaldiInsightTools::comparePeriods', 'kontoplan and transaktioner'],
			'get_year_end_pack' => ['Year-end pack', ['ledger.read'], 'SaldiInsightTools::yearEndPack', 'kontoplan, transaktioner, openpost, journals and VAT periods'],
			'get_till_summary' => ['Till summary', ['sales.read'], 'SaldiInsightTools::tillSummary', 'ordrer, pos_betalinger and report cashCount rows'],
			'get_stock_count' => ['Stock count', ['products.read'], 'SaldiInsightTools::stockCount', 'regulering, varer and expiring batches'],
			'check_master_data' => ['Check master data', ['company.read'], 'SaldiInsightTools::masterData', 'adresser, groups, varer and open journals and orders'],
		];
		$schemas = SaldiToolSchemas::catalogue();
		$registry = new self();
		foreach ($metadata as $name => [$title, $permissions, $method, $reads]) {
			$draft = str_contains($method, '.') || str_starts_with($name, 'propose_');
			$preconditions = implode(' or ', $permissions) . '; the requested branch and referenced records must be authorized and exist';
			if (in_array($name, ['search_invoices', 'get_invoice'], true)) {
				$preconditions .= '; sales documents require revenue visibility';
			}
			if (in_array($name, ['get_account_balance', 'list_ledger_entries', 'get_trial_balance'], true)) {
				$preconditions .= '; dates must lie in the active fiscal year';
			}
			$writes = $name === 'import_bank_statement' ? 'stages lines in bank_import_lines only; changes nothing in the books' : ($draft ? 'creates one inbox item for human approval' : 'nothing');
			$description = 'WHAT: ' . ($draft ? 'Propose ' : 'Read ') . strtolower($title) . '.' . ($notes[$name] ?? '') . "\nREADS: " . $reads . '. WRITES: ' . $writes . ".\nIRREVERSIBILITY: " . ($draft ? 'nothing changes until a person approves it in Saldi' : 'none (read)') . ".\nPRECONDITIONS: " . $preconditions . ".\nSAFE FALLBACK: explain the failure and ask a person to inspect the relevant Saldi screen.\nNEXT STEP: " . ($draft ? 'open Opgaver and have a person review the exact proposal' : 'use the returned citations to inspect records or prepare an approval proposal') . '.';
			$handler = str_contains($method, '::') ? Closure::fromCallable(explode('::', $method, 2)) : ($draft ? static fn(SaldiTenantContext $ctx, SaldiDb $db, array $a): array => SaldiDraftTools::propose($method, $ctx, $db, $a) : Closure::fromCallable([SaldiReadTools::class, $method]));
			if ($name === 'get_customer_statement') {
				$handler = static function (SaldiTenantContext $ctx, SaldiDb $db, array $args): array {
					$result = SaldiSalesTools::statement($ctx, $db, $args);
					// The statement rejects missing rates before returning any balances.
					$result['data']['converted'] = true;
					return $result;
				};
			}
			$registry->register(new SaldiToolDefinition($name, $title, $description, $schemas[$name]['input'], $schemas[$name]['data'], $permissions, $draft ? 'draft' : 'read', $draft ? 'gated' : 'auto', $handler));
		}
		return $registry;
	}
	public function register(SaldiToolDefinition $tool): void
	{
		if (isset($this->tools[$tool->name])) {
			throw new InvalidArgumentException('Duplicate tool');
		}
		$this->tools[$tool->name] = $tool;
	}
	public function get(string $name): ?SaldiToolDefinition
	{
		return $this->tools[$name] ?? null;
	}
	/** All definitions in stable name order. @return list<SaldiToolDefinition> */
	public function all(): array
	{
		$tools = $this->tools;
		ksort($tools);
		return array_values($tools);
	}
	/** Discoverable definitions after permission/scope intersection. @return list<SaldiToolDefinition> */
	public function visible(SaldiTenantContext $ctx, SaldiDb $db): array
	{
		$effective = SaldiPermissionService::effective($ctx, $db);
		return array_values(array_filter($this->all(), static fn(SaldiToolDefinition $t): bool => (bool)array_intersect($t->discoveryPermissions, $effective)));
	}
}
