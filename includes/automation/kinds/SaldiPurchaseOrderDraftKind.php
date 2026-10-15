<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiPurchaseOrderDraftKind.php --- ai/mcp-assistant --- 2026.10.05
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
// Unsent supplier order drafts with legacy VAT and product pricing, and exact-row undo.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Port supplier order headers, lines and number allocation.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261004 CL/LH Accept product_id and item_no together when they name the same product.
// 20261005 CDX/LH Format quantities on proposed and applied purchase cards in Danish notation.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiMasterDraftKind.php';

final class SaldiPurchaseOrderDraftKind extends SaldiMasterDraftKind
{
	public function name(): string
	{
		return 'purchase_order.draft';
	}
	public function actionClass(): string
	{
		return 'reversible_draft';
	}
	public function proposePermission(): string
	{
		return 'purchases.draft';
	}
	public function decidePermission(array $item): string
	{
		return 'purchases.draft';
	}
	/** Port the basis-item list and insert columns of kreditor/ordre.php::samlevare, without changing product cost. @return list<array> */
	private function assembly(SaldiDb $db, int $id, string $quantity, int $warehouse, array &$subject): array
	{
		$parts = $db->all('SELECT vare_id, antal FROM styklister WHERE indgaar_i = ? ORDER BY posnr, id', [$id]);
		for ($index = 0; $index < count($parts); $index++) {
			// fuld_stykliste's inner loop starts at one; repeated child ids are added only once.
			if ($db->value('SELECT CAST(? AS DECIMAL(30,9)) >= 1', [(string)$parts[$index]['antal']])) {
				foreach ($db->all('SELECT vare_id, antal FROM styklister WHERE indgaar_i = ? ORDER BY id', [$parts[$index]['vare_id']]) as $part) {
					if (!in_array($part['vare_id'], array_column($parts, 'vare_id'))) {
						$parts[] = $part;
					}
				}
			}
			if (count($parts) > 1000) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Assembly exceeds 1000 components');
			}
		}
		$subject['assembly'][] = $parts;
		$amounts = [];
		foreach ($parts as $part) {
			$key = (int)$part['vare_id'];
			$amounts[$key] = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) + CAST(? AS DECIMAL(30,9))', [$amounts[$key] ?? '0.000', (string)$part['antal']]);
		}
		$lines = [];
		foreach ($amounts as $partId => $amount) {
			$product = $db->one('SELECT * FROM varer WHERE id = ?' . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$partId]);
			if (!$product || ifset($product, 'lukket') === 'on') {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Assembly component missing or closed');
			}
			$subject['components'][] = $product;
			if (ifset($product, 'samlevare') === 'on') {
				continue;
			}
			$count = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) * CAST(? AS DECIMAL(30,9))', [$amount, $quantity]);
			$lines[] = ['varenr' => (string)$product['varenr'], 'vare_id' => $partId, 'beskrivelse' => (string)$product['beskrivelse'], 'antal' => $count, 'leveres' => $count, 'pris' => (string)(ifset($product, 'kostpris') ?? '0.000'), 'posnr' => 100, 'lager' => $warehouse];
		}
		if (!$lines) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Assembly has no basis items');
		}
		return $lines;
	}
	/** Snapshot source rows separately from values resolved only for approval/preview. @return array{payload:array,header:array,lines:list<array>,subject:array} */
	private function prepared(SaldiTenantContext $ctx, SaldiDb $db, array $input, bool $approval = false): array
	{
		$this->keys($input, ['supplier_account','lines','delivery_date','reference','note','order_date']);
		if ($db->inTransaction()) {
			$this->orderLock($db);
		}
		$supplier = $this->party($db, ifset($input, 'supplier_account'), 'K');
		$group = $this->group($ctx, $db, 'KG', (int)$supplier['gruppe']);
		if (!$group) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Supplier group missing');
		}
		$subject = ['supplier' => $supplier, 'groups' => [$group]];
		$p = ['supplier_account' => (string)$supplier['kontonr'], 'lines' => []];
		foreach (['reference' => 40, 'note' => 200] as $field => $max) {
			if (array_key_exists($field, $input)) {
				$p[$field] = $this->string($input[$field], 0, $max, $field);
			}
		}
		if (array_key_exists('delivery_date', $input)) {
			$p['delivery_date'] = $this->date($input['delivery_date'], 'delivery_date');
		}
		$source = ifset($input, 'lines');
		if (!is_array($source) || !array_is_list($source) || count($source) < 1 || count($source) > 50) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected 1 to 50 purchase lines');
		}
		$vat = '0.000';
		$code = trim((string)$group['box1']);
		if (str_starts_with($code, 'K')) {
			// insertAccount uses exactly the first digit after K.
			$vatGroup = $this->group($ctx, $db, 'KM', (int)substr($code, 1, 1));
			$subject['groups'][] = $vatGroup;
			$vat = trim((string)ifset($vatGroup, 'box2', '')) ?: '0.000';
		}
		$date = $this->date(ifset($input, 'order_date', $this->today()), 'order_date');
		$p['order_date'] = $date;
		$currency = trim((string)$group['box3']);
		$currency = in_array($currency, ['', '-', 'DKK'], true) ? 'DKK' : $currency;
		$rate = '100.000';
		if ($approval) {
			[$currency, $rate] = $this->currency($db, $currency, $this->today());
		}
		$employee = $approval ? $db->one('SELECT a.navn, a.afd FROM brugere b JOIN ansatte a ON a.id = b.ansat_id WHERE b.id = ?', [$ctx->userId]) : [];
		$department = (int)ifset($employee, 'afd', 0);
		$warehouse = $department ? (int)$db->value("SELECT box1 FROM grupper WHERE art = 'AFD' AND kodenr = ? ORDER BY id LIMIT 1", [$department]) : 0;
		$header = ['konto_id' => (int)$supplier['id']];
		foreach (['kontonr','firmanavn','addr1','addr2','postnr','bynavn','land','kontakt','betalingsdage','betalingsbet','cvrnr','notes','email'] as $field) {
			$header[$field] = $field === 'betalingsdage' ? (int)ifset($supplier, $field, 0) : trim((string)ifset($supplier, $field, ''));
		}
		$header += ['lev_navn' => '', 'lev_addr1' => '', 'lev_addr2' => '', 'lev_postnr' => '', 'lev_bynavn' => '', 'lev_kontakt' => '', 'art' => 'KO', 'ordredate' => $date, 'levdate' => ifset($p, 'delivery_date'), 'momssats' => $vat, 'status' => 0, 'ref' => ifset($p, 'reference') ?: (ifset($employee, 'navn') ?: $ctx->username), 'afd' => $department, 'lager' => $warehouse, 'sum' => '0.000', 'valuta' => $currency === 'DKK' ? trim((string)$group['box3']) : $currency, 'kred_ord_id' => 0, 'omvbet' => trim((string)$group['box9'])];
		if (isset($p['note'])) {
			$header['notes'] = $p['note'];
		}
		$header['ref'] = mb_substr((string)$header['ref'], 0, 40);
		$header['valutakurs'] = $rate;
		$lines = [];
		foreach ($source as $index => $line) {
			if (!is_array($line)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid purchase line');
			}
			$this->keys($line, ['product_id','item_no','description','quantity','unit_price']);
			$quantity = $this->decimal(ifset($line, 'quantity'), 'lines[' . $index . '].quantity', true);
			$productId = ifset($line, 'product_id');
			$itemNo = ifset($line, 'item_no');
			$product = null;
			if ($productId !== null || $itemNo !== null) {
				if ($productId !== null && (!is_int($productId) || $productId <= 0)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid product id');
				}
				if ($itemNo !== null) {
					$itemNo = $this->string($itemNo, 1, 100, 'item_no');
				}
				$product = $db->one('SELECT * FROM varer WHERE ' . ($productId !== null ? 'id = ?' : 'UPPER(varenr) = UPPER(?)') . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$productId ?? $itemNo]);
				if (!$product || ifset($product, 'lukket') === 'on') {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Product missing or closed', ['fields' => ['$.lines[' . $index . ']']]);
				}
				// Both references are fine when they name the same product (models often send both).
				if ($productId !== null && $itemNo !== null && strcasecmp((string)$product['varenr'], $itemNo) !== 0) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'product_id ' . $productId . ' is item ' . $product['varenr'] . ', not ' . $itemNo, ['reason' => 'product_mismatch', 'fields' => ['$.lines[' . $index . ']']]);
				}
			}
			$description = $this->string(ifset($line, 'description', (string)ifset($product, 'beskrivelse', '')), 1, 100, 'description');
			$n = ['description' => $description, 'quantity' => $quantity];
			if (!$product) {
				// The creditor page inserts free text as a comment, not an inventory product.
				if (isset($line['unit_price'])) {
					$n['unit_price'] = $this->decimal($line['unit_price'], 'unit_price');
					if (preg_match('/[1-9]/', $n['unit_price'])) {
						throw new SaldiRuntimeError('VALIDATION_ERROR', 'Priced lines require a product');
					}
				}
				$lines[] = ['posnr' => $index + 1, 'beskrivelse' => $description];
				$p['lines'][] = $n;
				continue;
			}
			$vendor = $db->one('SELECT * FROM vare_lev WHERE vare_id = ? AND lev_id = ? ORDER BY id LIMIT 1' . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$product['id'], $supplier['id']]);
			$subject['products'][] = $product;
			$subject['vendors'][] = $vendor;
			$price = ifset($vendor, 'kostpris');
			if ($price === null || !preg_match('/[1-9]/', (string)$price)) {
				$price = (ifset($product, 'kostpris') ?? '0.000');
			}
			$price = $this->decimal((string)$price, 'unit_price');
			if (array_key_exists('unit_price', $line)) {
				$price = $this->decimal($line['unit_price'], 'unit_price');
			} elseif ($currency !== 'DKK') {
				$price = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) * 100 / CAST(? AS DECIMAL(30,9))', [$price, $rate]);
			}
			$vg = $this->group($ctx, $db, 'VG', (int)$product['gruppe']);
			$subject['groups'][] = $vg;
			$account = (int)ifset($vg, 'box4', 0);
			$lineVat = $vat;
			if ($account && !ifset($vg, 'box7')) {
				$accountRow = $db->one('SELECT * FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ?' . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$account, $ctx->fiscalYear]);
				$subject['accounts'][] = $accountRow;
				$accountVat = trim((string)ifset($accountRow, 'moms', ''));
				if ($accountVat !== '') {
					$accountGroup = $this->group($ctx, $db, 'SM', (int)substr($accountVat, 1));
					$subject['groups'][] = $accountGroup;
					$lineVat = trim((string)ifset($accountGroup, 'box2', '')) ?: $vat;
				}
			}
			if (ifset($product, 'serienr')) {
				$quantity = $this->calculate($db, 'ROUND(CAST(? AS DECIMAL(30,9)), 0)', [$quantity]);
				if (!preg_match('/[1-9]/', $quantity)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Serial product quantity rounds to zero');
				}
			}
			$n['product_id'] = (int)$product['id'];
			if (array_key_exists('unit_price', $line)) {
				$n['unit_price'] = $this->decimal($line['unit_price'], 'unit_price');
			}
			$n['quantity'] = $quantity;
			$p['lines'][] = $n;
			if (ifset($product, 'samlevare')) {
				$lines = array_merge($lines, $this->assembly($db, (int)$product['id'], $quantity, $warehouse, $subject));
				continue;
			}
			$lines[] = ['posnr' => $index + 1, 'varenr' => (string)$product['varenr'], 'vare_id' => (int)$product['id'], 'beskrivelse' => $description, 'enhed' => (string)ifset($product, 'enhed', ''), 'antal' => $quantity, 'pris' => $price, 'rabat' => (string)(ifset($product, 'rabat') ?? '0.000'), 'serienr' => (string)ifset($product, 'serienr', ''), 'lev_varenr' => (string)ifset($vendor, 'lev_varenr', ''), 'momsfri' => (string)ifset($vg, 'box7', ''), 'variant_id' => '', 'samlevare' => (string)ifset($product, 'samlevare', ''), 'omvbet' => ifset($group, 'box9') && ifset($vg, 'box6') ? 'on' : '', 'momssats' => $lineVat, 'bogf_konto' => $account, 'lager' => $warehouse];
		}
		return ['payload' => $p, 'header' => $header, 'lines' => $lines, 'subject' => ['payload' => $p] + $subject];
	}
	/** @return array{payload:array,title:string,subject_type:?string,subject_id:?string,proposed_rev:?string,dedupe_key:?string,visibility:string,assignee_permission:?string,owner_user_id:?int} */
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$state = $this->prepared($ctx, $db, $input);
		$n = $this->normalized($ctx, $state['payload'], $this->text($ctx, $db, 5904) . ': ' . $state['header']['firmanavn'], null, 'permission', 'purchases.draft');
		$n['subject_type'] = 'creditor';
		$n['subject_id'] = (string)$state['header']['konto_id'];
		$n['proposed_rev'] = $this->hash($state['subject']);
		return $n;
	}
	public function currentRevision(SaldiTenantContext $ctx, SaldiDb $db, array $item): ?string
	{
		return $this->hash($this->prepared($ctx, $db, $item['payload'])['subject']);
	}
	/** @return array{order_id:int,order_number:int,rows_sha256:string,screen:string,approval_date:string,currency_rate:string,department:int,warehouse:int} */
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$this->orderLock($db);
		$state = $this->prepared($ctx, $db, $item['payload'], true);
		if (isset($item['proposed_rev']) && $item['proposed_rev'] !== $this->hash($state['subject'])) {
			throw new SaldiRuntimeError('STALE_CONTEXT', 'Supplier order inputs changed');
		}
		$number = (int)$db->value("SELECT COALESCE(MAX(ordrenr), 0) + 1 FROM ordrer WHERE art IN ('KO','KK')");
		$id = $db->insert('ordrer', ['ordrenr' => $number] + $state['header']);
		foreach ($state['lines'] as $line) {
			$db->insert('ordrelinjer', ['ordre_id' => $id] + $line);
		}
		return ['order_id' => $id, 'order_number' => $number, 'rows_sha256' => $this->hash($this->orderRows($db, $id)), 'screen' => SaldiScreens::link('purchase_order', $id), 'approval_date' => $this->today(), 'currency_rate' => $state['header']['valutakurs'], 'department' => $state['header']['afd'], 'warehouse' => $state['header']['lager']];
	}
	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool
	{
		return $this->intactOrder($db, $item, 0, 'KO');
	}
	/** @return array{columns:list<string>,rows:list<array>,fields:list<array>} */
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$p = $item['payload'];
		$supplier = $db->value("SELECT firmanavn FROM adresser WHERE art = 'K' AND kontonr = ?", [$p['supplier_account']]);
		$rows = [];
		foreach ($p['lines'] as $line) {
			$itemNo = isset($line['product_id']) ? (string)$db->value('SELECT varenr FROM varer WHERE id = ?', [$line['product_id']]) : '';
			$rows[] = [$itemNo, $line['description'], SaldiToolRuntime::quantityDa($line['quantity']), isset($line['unit_price']) ? $this->money($line['unit_price']) : ''];
		}
		if (isset($item['result']['order_id'])) {
			$inserted = $db->all('SELECT varenr, beskrivelse, antal, pris FROM ordrelinjer WHERE ordre_id = ? ORDER BY id', [$item['result']['order_id']]);
			$rows = array_map(fn(array $line): array => [(string)$line['varenr'], (string)$line['beskrivelse'], SaldiToolRuntime::quantityDa($line['antal']), $line['pris'] === null ? '' : $this->money((string)$line['pris'])], $inserted);
		} else {
			try {
				$prepared = $this->prepared($ctx, $db, $p, true);
				$rows = array_map(fn(array $line): array => [(string)ifset($line, 'varenr', ''), $line['beskrivelse'], SaldiToolRuntime::quantityDa(ifset($line, 'antal', '')), isset($line['pris']) ? $this->money($line['pris']) : ''], $prepared['lines']);
			} catch (SaldiRuntimeError $e) {
				// A stale proposal remains readable; approval revalidates before any insert.
			}
		}
		$currency = $db->value("SELECT g.box3 FROM grupper g JOIN adresser a ON a.gruppe = g.kodenr WHERE a.art = 'K' AND a.kontonr = ? AND g.art = 'KG' AND g.fiscal_year = ? ORDER BY g.id LIMIT 1", [$p['supplier_account'], $ctx->fiscalYear]);
		if (isset($item['result']['order_id'])) {
			$currency = $db->value('SELECT valuta FROM ordrer WHERE id = ?', [$item['result']['order_id']]);
		}
		return ['columns' => array_map(fn(int $id): string => $this->text($ctx, $db, $id), [5926,5743,5927,5928]), 'rows' => $rows, 'fields' => [['label' => $this->text($ctx, $db, 951, 'Leverandør'), 'value' => $p['supplier_account'] . ' ' . $supplier], ['label' => $this->text($ctx, $db, 1148, 'Levering'), 'value' => (string)ifset($p, 'delivery_date', '')], ['label' => $this->text($ctx, $db, 776, 'Valuta'), 'value' => $currency ?: 'DKK'], ['label' => $this->text($ctx, $db, 6042, 'Reference'), 'value' => (string)ifset($p, 'reference', '')], ['label' => $this->text($ctx, $db, 2881, 'Notat'), 'value' => (string)ifset($p, 'note', '')]]];
	}
}
