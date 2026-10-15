<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiKindRegistry.php --- ai/mcp-assistant --- 2026.10.08
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
// Explicit registry for approval-gated base and companion kinds.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Add companion shared foundation contracts.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261008 CL/LH Add the mobile Assist identity and delegation contract.

declare(strict_types=1);

require_once __DIR__ . '/SaldiInboxKind.php';
final class SaldiKindRegistry
{
	private array $kinds = [];
	/** Build the explicit base and companion kind registry. */
	public static function default(): self
	{
		require_once __DIR__ . '/kinds/SaldiJournalDraftEntryKind.php';
		require_once __DIR__ . '/kinds/SaldiMemoryFactKind.php';
		require_once __DIR__ . '/kinds/SaldiMemoryRetractKind.php';
		require_once __DIR__ . '/kinds/SaldiFollowupTaskKind.php';
		require_once __DIR__ . '/kinds/SaldiRoutineFindingKind.php';
		require_once __DIR__ . '/kinds/SaldiPostingRuleKind.php';
		require_once __DIR__ . '/kinds/SaldiPostingRuleRetireKind.php';
		require_once __DIR__ . '/kinds/SaldiPartyCreateKind.php';
		require_once __DIR__ . '/kinds/SaldiPurchaseOrderDraftKind.php';
		require_once __DIR__ . '/kinds/SaldiReminderDraftKind.php';
		require_once __DIR__ . '/kinds/SaldiSupportReportKind.php';
		require_once __DIR__ . '/kinds/SaldiSettlementKind.php';
		require_once __DIR__ . '/kinds/SaldiPurchaseInvoiceStageKind.php';
		require_once __DIR__ . '/kinds/SaldiSalesOrderDraftKind.php';
		require_once __DIR__ . '/kinds/SaldiCreditNoteDraftKind.php';
		$r = new self();
		foreach ([new SaldiJournalDraftEntryKind(), new SaldiMemoryFactKind(), new SaldiMemoryRetractKind(), new SaldiFollowupTaskKind(), new SaldiRoutineFindingKind(), new SaldiPostingRuleKind(), new SaldiPostingRuleRetireKind(), new SaldiPartyCreateKind(), new SaldiPurchaseOrderDraftKind(), new SaldiReminderDraftKind(), new SaldiSupportReportKind(), new SaldiSettlementKind(), new SaldiPurchaseInvoiceStageKind(), new SaldiSalesOrderDraftKind(), new SaldiCreditNoteDraftKind()] as $kind) {
			$r->register($kind);
		}
		return $r;
	}
	/** Navigation module; payload-dependent debtor/creditor kinds need the shared Assist gate. */
	public static function module(string $kind): string
	{
		return match ($kind) {
			'journal.draft_entry', 'rule.posting', 'rule.retire' => 'kassekladde',
			'reminder.draft' => 'debitorkonti',
			'purchase_order.draft', 'purchase_order.invoice_stage' => 'kreditorordre',
			'sales_order.draft', 'credit_note.draft' => 'debitorordre',
			'party.create', 'settlement.draft', 'memory.fact', 'memory.retract', 'followup.task', 'routine.finding', 'support.report' => 'assistant',
			default => throw new SaldiRuntimeError('VALIDATION_ERROR', 'Unknown inbox kind'),
		};
	}
	/** Register a trusted kind implementation by its English name. */
	public function register(SaldiInboxKind $kind): void
	{
		$this->kinds[$kind->name()] = $kind;
	}
	/** Look up a kind; unknown names raise VALIDATION_ERROR. */
	public function get(string $name): SaldiInboxKind
	{
		return $this->kinds[$name] ?? throw new SaldiRuntimeError('VALIDATION_ERROR', 'Unknown inbox kind');
	}
	/** Registered English kind codes. @return list<string> */
	public function names(): array
	{
		return array_keys($this->kinds);
	}
}
