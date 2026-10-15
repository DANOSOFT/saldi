<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiDraftTools.php --- ai/mcp-assistant --- 2026.10.04
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
// Approval-only proposals and server-verified evidence references.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Keep private document attachment receipts out of proposal replay output.
// 20261002 CL/LH Ledger and account-kind words instead of F/D/K and D/S codes; input property descriptions.
// 20261002 CDX/LH Add companion catalogue and verified evidence references.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261004 CDX/LH Add companion part 2 foundation contracts.

declare(strict_types=1);

final class SaldiDraftTools
{
	/** Drafts create only inbox proposals. @return array */
	public static function propose(string $kind, SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		$definition = SaldiKindRegistry::default()->get($kind);
		SaldiPermissionService::require($ctx, $db, in_array($kind, ['party.create', 'settlement.draft'], true) ? $definition->decidePermission(['payload' => $args]) : $definition->proposePermission());
		$evidence = ['explanation' => $args['explanation'] ?? '', 'citations' => [], 'tainted' => $args['tainted'] ?? false];
		foreach ($args['citations'] ?? [] as $id) {
			$citation = self::resolve($ctx, $db, $id);
			if ($citation !== null) {
				$evidence['citations'][] = $citation;
			}
		}
		$opts = ['request_id' => $args['request_id'], 'evidence' => $evidence];
		unset($args['request_id'], $args['explanation'], $args['citations'], $args['tainted']);
		if ($kind === 'journal.draft_entry') {
			// The tool speaks ledger words; the kind and kassekladde use F/D/K.
			foreach ($args['lines'] ?? [] as $i => $line) {
				foreach (['debit', 'credit'] as $side) {
					if (isset($line[$side]['ledger'])) {
						$args['lines'][$i][$side] = ['type' => SaldiToolRuntime::ledgerCode((string)$line[$side]['ledger']) ?? '', 'account' => $line[$side]['account'] ?? ''] + (array_key_exists('vat_code', $line[$side]) ? ['vat_code' => $line[$side]['vat_code']] : []);
					}
				}
			}
		}
		$item = (new SaldiInboxService($db, SaldiKindRegistry::default()))->create($ctx, $kind, $args, $opts);
		$item = SaldiInboxService::publicItem($item);
		return SaldiToolRuntime::result($item, [SaldiToolRuntime::citation('inbox', $item['id'], $item['title'], 'inbox_item')]);
	}
	/** Resolve only typed, existing references visible to this principal. @return array|null */
	public static function resolve(SaldiTenantContext $ctx, SaldiDb $db, string $id): ?array
	{
		if (!preg_match('/^saldi:(journal|journal_line|account|party|invoice|product|inbox|memory|rule|document|pool_file|order|bank_line):([0-9]{1,15})(?:@([0-9]{1,10}))?$/D', $id, $m)) {
			return null;
		}
		$type = $m[1];
		$number = (int)$m[2];
		if (($type === 'account') !== isset($m[3]) || ($type === 'account' && (int)$m[3] !== $ctx->fiscalYear)) {
			return null;
		}
		try {
			switch ($type) {
				case 'bank_line':
					SaldiPermissionService::require($ctx, $db, 'journal.read');
					$r = $db->one('SELECT description FROM bank_import_lines WHERE id = ?', [$number]);
					return $r ? SaldiToolRuntime::citation($type, $number, $r['description'], 'bank_import') : null;
				case 'rule':
					SaldiPermissionService::require($ctx, $db, 'rules.read');
					$r = $db->one('SELECT match_type, match_value, account FROM posting_rules WHERE id = ?', [$number]);
					return $r ? SaldiToolRuntime::citation($type, $number, 'Regel ' . $number . ': ' . $r['match_value'] . ' → ' . $r['account'], 'assist_settings') : null;
				case 'pool_file':
					SaldiPermissionService::require($ctx, $db, 'documents.read');
					$r = $db->one('SELECT filename FROM pool_files WHERE id = ?', [$number]);
					return $r ? SaldiToolRuntime::citation($type, $number, $r['filename'], 'document_pool') : null;
				case 'document':
					SaldiPermissionService::require($ctx, $db, 'documents.read');
					$r = $db->one('SELECT filename, source, source_id FROM documents WHERE id = ?', [$number]);
					if (!$r) {
						return null;
					}
					if ($r['source'] === 'kassekladde') {
						SaldiPermissionService::require($ctx, $db, 'journal.read');
						$parent = $db->one('SELECT k.kladde_id FROM kassekladde k JOIN kladdeliste l ON l.id = k.kladde_id WHERE k.id = ?', [$r['source_id']]);
						$screen = $parent ? SaldiScreens::link('journal', (int)$parent['kladde_id']) : null;
					} elseif (in_array($r['source'], ['creditorOrder', 'debitorOrdrer'], true)) {
						$purchase = $r['source'] === 'creditorOrder';
						SaldiPermissionService::require($ctx, $db, $purchase ? 'purchases.read' : 'sales.read');
						if (!$purchase && SaldiPermissionService::revenueHidden($ctx, $db)) {
							return null;
						}
						$parent = $db->one('SELECT art FROM ordrer WHERE id = ?', [$r['source_id']]);
						$screen = $parent && in_array($parent['art'], $purchase ? ['KO', 'KK'] : ['DO', 'DK'], true) ? SaldiScreens::link($purchase ? 'purchase_order' : 'sales_order', (int)$r['source_id']) : null;
					} else {
						return null;
					}
					return $screen === null ? null : SaldiToolRuntime::citation($type, $number, $r['filename']) + ['screen' => $screen];
				case 'order':
					$r = $db->one('SELECT art, ordrenr FROM ordrer WHERE id = ?', [$number]);
					if (!$r || !in_array($r['art'], ['KO', 'KK', 'R1', 'R2', 'R3', 'DO', 'DK', 'PO'], true)) {
						return null;
					}
					if (in_array($r['art'], ['DO', 'DK', 'PO'], true)) {
						SaldiPermissionService::require($ctx, $db, 'sales.read');
						if (SaldiPermissionService::revenueHidden($ctx, $db)) {
							return null;
						}
						return SaldiToolRuntime::citation($type, $number, 'Ordre ' . $r['ordrenr'], 'sales_order');
					}
					$purchase = in_array($r['art'], ['KO', 'KK'], true);
					SaldiPermissionService::require($ctx, $db, $purchase ? 'purchases.read' : 'debtors.read');
					return SaldiToolRuntime::citation($type, $number, ($purchase ? 'Ordre ' : 'Rykker ') . $r['ordrenr'], $purchase ? 'purchase_order' : 'reminder');
				case 'account':
					if (!SaldiPermissionService::allows($ctx, $db, 'accounts.read') && !SaldiPermissionService::allows($ctx, $db, 'ledger.read')) {
						return null;
					}
					$r = $db->one('SELECT beskrivelse, kontotype FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ?', [$m[2], $ctx->fiscalYear]);
					return !$r || ($r['kontotype'] === 'D' && SaldiPermissionService::revenueHidden($ctx, $db)) ? null : SaldiToolRuntime::citation('account', $m[2] . '@' . $ctx->fiscalYear, $m[2] . ' ' . $r['beskrivelse']);
				case 'journal':
					SaldiPermissionService::require($ctx, $db, 'journal.read');
					return $db->value('SELECT id FROM kladdeliste WHERE id = ?', [$number]) === null ? null : SaldiToolRuntime::citation($type, $number, 'Kassekladde ' . $number, 'journal');
				case 'journal_line':
					SaldiPermissionService::require($ctx, $db, 'journal.read');
					$r = $db->one('SELECT l.beskrivelse, l.kladde_id FROM kassekladde l JOIN kladdeliste k ON k.id = l.kladde_id WHERE l.id = ?', [$number]);
					return $r ? SaldiToolRuntime::citation($type, $number, $r['beskrivelse']) + ['screen' => SaldiScreens::link('journal', (int)$r['kladde_id'])] : null;
				case 'party':
					$result = SaldiReadTools::party($ctx, $db, ['party_id' => $number]);
					return $result['citations'][0];
				case 'invoice':
					$result = SaldiReadTools::invoice($ctx, $db, ['order_id' => $number]);
					return $result['citations'][0];
				case 'product':
					SaldiPermissionService::require($ctx, $db, 'products.read');
					$r = $db->one("SELECT varenr, beskrivelse FROM varer WHERE id = ? AND COALESCE(lukket,'') <> 'on'", [$number]);
					return $r ? SaldiToolRuntime::citation($type, $number, $r['varenr'] . ' ' . $r['beskrivelse'], 'product') : null;
				case 'inbox':
					$result = SaldiReadTools::inboxItem($ctx, $db, ['item_id' => $number]);
					return $result['citations'][0];
				case 'memory':
					SaldiPermissionService::require($ctx, $db, 'memory.read');
					$r = $db->one("SELECT fact FROM assistant_memory WHERE id = ? AND retracted_at IS NULL AND (scope = 'company' OR (scope = 'user' AND user_id = ?))", [$number, $ctx->userId]);
					return $r ? SaldiToolRuntime::citation($type, $number, $r['fact']) : null;
			}
		} catch (SaldiRuntimeError $e) {
			if (!in_array($e->errorCode, ['NOT_FOUND', 'PERMISSION_DENIED'], true)) {
				throw $e;
			}
		}
		return null;
	}
}
