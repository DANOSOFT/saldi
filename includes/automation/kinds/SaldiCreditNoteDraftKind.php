<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiCreditNoteDraftKind.php --- ai/mcp-assistant --- 2026.10.05
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
// Unposted credit-note drafts linked to exactly one invoiced sales order and its source lines.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Port invoice copying, negative quantities, duplicate checks and exact undo.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.

declare(strict_types=1);

require_once __DIR__ . '/SaldiSalesOrderDraftKind.php';

final class SaldiCreditNoteDraftKind extends SaldiSalesDraftKind
{
	public function name(): string
	{
		return 'credit_note.draft';
	}
	/** Invoice amounts remain unavailable under the tenant's revenue restriction. */
	private function requireRevenue(SaldiTenantContext $ctx, SaldiDb $db): void
	{
		if (SaldiPermissionService::revenueHidden($ctx, $db)) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Sales documents are hidden');
		}
	}
	/** Copy only columns written by debitor/ordre.php:2617-2630, excluding posting state.
	 * @return array{payload:array,header:array,lines:list<array>,subject:array}
	 */
	private function prepared(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$this->requireRevenue($ctx, $db);
		$this->keys($input, ['invoice_order_id','lines','reason','order_date']);
		$id = ifset($input, 'invoice_order_id');
		if (!is_int($id) || $id < 1) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid invoice order id');
		}
		if ($db->inTransaction()) {
			$this->orderLock($db);
		}
		$lock = $db->inTransaction() ? ' FOR UPDATE' : '';
		$source = $db->one('SELECT * FROM ordrer WHERE id = ?' . $lock, [$id]);
		if (!$source || $source['art'] !== 'DO' || (int)$source['status'] < 3) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Source must be an invoiced sales order');
		}
		$existing = $db->one("SELECT id, ordrenr FROM ordrer WHERE art = 'DK' AND kred_ord_id = ? ORDER BY id LIMIT 1" . $lock, [$id]);
		if ($existing) {
			throw new SaldiRuntimeError('CONFLICT', 'Credit note ' . $existing['ordrenr'] . ' already exists (order ' . $existing['id'] . ')', ['reason' => 'not_open', 'order_id' => (int)$existing['id'], 'order_number' => (int)$existing['ordrenr']]);
		}
		$rows = $db->all('SELECT * FROM ordrelinjer WHERE ordre_id = ? ORDER BY posnr, id' . $lock, [$id]);
		$p = ['invoice_order_id' => $id, 'reason' => $this->string(ifset($input, 'reason'), 1, 200, 'reason'), 'order_date' => $this->date(ifset($input, 'order_date', $this->today()), 'order_date')];
		$selected = null;
		if (array_key_exists('lines', $input)) {
			if (!is_array($input['lines']) || !array_is_list($input['lines']) || !$input['lines'] || count($input['lines']) > 50) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected 1 to 50 credit lines');
			}
			$selected = [];
			foreach ($input['lines'] as $line) {
				if (!is_array($line)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid credit line');
				}
				$this->keys($line, ['line_id','quantity']);
				$lineId = ifset($line, 'line_id');
				if (!is_int($lineId) || $lineId < 1 || isset($selected[$lineId])) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid or duplicate source line');
				}
				$selected[$lineId] = $this->decimal(ifset($line, 'quantity'), 'quantity', true);
			}
			$p['lines'] = [];
		}
		$header = [];
		foreach (['konto_id','kontonr','kundeordnr','firmanavn','addr1','addr2','postnr','bynavn','land','kontakt','lev_navn','lev_addr1','lev_addr2','lev_postnr','lev_bynavn','lev_kontakt','lev_email','lev_land','betalingsdage','betalingsbet','cvrnr','ean','institution','email','mail_fakt','phone','notes','momssats','ref','performed_by','lev_adr','valuta','projekt','sprog','pbs','afd','procenttillag','sag_id','sagsnr','tilbudnr','nr','returside','omvbet','felt_1','felt_2','felt_3','felt_4','felt_5'] as $field) {
			$header[$field] = ifset($source, $field);
		}
		// Legacy copies do not carry invoice number/date, paid marks, ownership or delivery state.
		$header = array_replace($header, ['art' => 'DK', 'kred_ord_id' => $id, 'status' => 0, 'ordredate' => $p['order_date'], 'restordre' => '0.000', 'datotid' => gmdate('Y-m-d H:i:s'), 'valutakurs' => $this->number(ifset($source, 'valutakurs') ?: '100'), 'lager' => (int)ifset($source, 'lager', 0)]);
		if (!$header['valuta'] || $header['valuta'] === '-') {
			$header['valuta'] = 'DKK';
			$header['valutakurs'] = '100.000';
		}
		$lines = [];
		$found = [];
		foreach ($rows as $row) {
			$lineId = (int)$row['id'];
			if ($selected !== null && !isset($selected[$lineId])) {
				continue;
			}
			if (!(int)ifset($row, 'vare_id', 0) && !$this->nonzero($this->number(ifset($row, 'pris'))) && !(int)ifset($row, 'bogf_konto', 0)) {
				if ($selected !== null) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Text lines cannot have credit quantities');
				}
				$lines[] = ['posnr' => (int)$row['posnr'], 'beskrivelse' => (string)$row['beskrivelse']];
				continue;
			}
			$original = $this->number($row['antal']);
			if (!$db->value('SELECT CAST(? AS DECIMAL(30,9)) > 0', [$original])) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Source quantity must be positive');
			}
			$quantity = $selected[$lineId] ?? $original;
			if ($db->value('SELECT CAST(? AS DECIMAL(30,9)) > CAST(? AS DECIMAL(30,9))', [$quantity, $original])) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Credit quantity exceeds invoice quantity');
			}
			$found[] = $lineId;
			if ($selected !== null) {
				$p['lines'][] = ['line_id' => $lineId, 'quantity' => $quantity];
			}
			$line = [];
			foreach (['vare_id','varenr','enhed','beskrivelse','rabat','rabatart','procent','m_rabat','pris','kostpris','momsfri','posnr','projekt','folgevare','rabatgruppe','bogf_konto','vat_account','serienr','variant_id','samlevare','omvbet','saet','fast_db','lev_varenr','lager'] as $field) {
				$line[$field] = ifset($row, $field);
			}
			$vat = $this->number(ifset($row, 'momssats'));
			if (!$this->nonzero($vat) && !$line['momsfri']) {
				$vat = $this->number($source['momssats']);
			}
			$vat = $this->calculate($db, 'LEAST(CAST(? AS DECIMAL(30,9)), CAST(? AS DECIMAL(30,9)))', [$vat, $this->number($source['momssats'])]);
			if ($line['momsfri'] || $line['omvbet']) {
				$vat = '0.000';
			}
			$line += ['antal' => '-' . $quantity, 'kred_linje_id' => $lineId, 'leveres' => '0.000', 'kdo' => '', 'tilfravalg' => '', 'barcode' => '', 'momssats' => $vat, 'vat_price' => $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) * (1 + CAST(? AS DECIMAL(30,9)) / 100)', [$this->number($row['pris']), $vat])];
			$lines[] = $line;
		}
		if (!$found || $selected !== null && count($found) !== count($selected)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Credit lines must belong to the invoice and have positive quantities');
		}
		return ['payload' => $p, 'header' => $header, 'lines' => $lines, 'subject' => ['payload' => $p, 'invoice' => array_intersect_key($source, array_flip(self::HEADER_FIELDS)), 'lines' => array_map(static fn(array $row): array => array_intersect_key($row, array_flip(self::LINE_FIELDS)), $rows)]];
	}
	/** @return array{payload:array,title:string,subject_type:?string,subject_id:?string,proposed_rev:?string,dedupe_key:?string,visibility:string,assignee_permission:?string,owner_user_id:?int} */
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$state = $this->prepared($ctx, $db, $input);
		$n = $this->normalized($ctx, $state['payload'], $this->text($ctx, $db, 6104) . ': ' . (string)$state['header']['firmanavn'], null, 'permission', 'sales.draft');
		$n['subject_type'] = 'order';
		$n['subject_id'] = (string)$state['payload']['invoice_order_id'];
		$n['proposed_rev'] = $this->hash($state['subject']);
		return $n;
	}
	public function currentRevision(SaldiTenantContext $ctx, SaldiDb $db, array $item): ?string
	{
		return $this->hash($this->prepared($ctx, $db, $item['payload'])['subject']);
	}
	/** @return array Draft insertion receipt. */
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$this->orderLock($db);
		$state = $this->prepared($ctx, $db, $item['payload']);
		if (isset($item['proposed_rev']) && $item['proposed_rev'] !== $this->hash($state['subject'])) {
			throw new SaldiRuntimeError('STALE_CONTEXT', 'Invoice changed');
		}
		return $this->insertDraft($db, $state['header'], $state['lines']);
	}
	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool
	{
		return $this->intactOrder($db, $item, 0, 'DK');
	}
	/** @return array{columns:list<string>,rows:list<array>,fields:list<array>} */
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$this->requireRevenue($ctx, $db);
		$p = $item['payload'];
		$header = $db->one('SELECT * FROM ordrer WHERE id = ?', [ifset($p, 'invoice_order_id', 0)]) ?? [];
		$lines = [];
		if (isset($item['result']['order_id'])) {
			$header = $db->one('SELECT * FROM ordrer WHERE id = ?', [$item['result']['order_id']]) ?? $header;
			$lines = $db->all('SELECT * FROM ordrelinjer WHERE ordre_id = ? ORDER BY posnr, id', [$item['result']['order_id']]);
		} else {
			try {
				$state = $this->prepared($ctx, $db, $p);
				$header = $state['header'];
				$lines = $state['lines'];
			} catch (SaldiRuntimeError $e) {
				// Stale or duplicate proposals remain readable.
			}
		}
		return $this->salesCard($ctx, $db, $header, $lines, [['label' => $this->text($ctx, $db, 5929, 'Faktura'), 'value' => (string)$p['invoice_order_id']], ['label' => $this->text($ctx, $db, 2881, 'Notat'), 'value' => (string)$p['reason']]]);
	}
}
