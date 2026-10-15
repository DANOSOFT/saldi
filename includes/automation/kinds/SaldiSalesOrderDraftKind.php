<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiSalesOrderDraftKind.php --- ai/mcp-assistant --- 2026.10.05
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
// Unsent customer offers and orders, with legacy headers, decimal prices and guarded undo.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Port debtor drafts, pricing, VAT and shared DO/DK allocation.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.
// 20261005 CDX/LH Format sales and credit card quantities in Danish notation.
// 20261007 CL/LH Discount cells use the Danish quantity format (no "0.00000").

declare(strict_types=1);

require_once __DIR__ . '/../SaldiMasterDraftKind.php';

/** Shared sales insert and preview primitives; kept in the B9-owned sales file. */
abstract class SaldiSalesDraftKind extends SaldiMasterDraftKind
{
	public function actionClass(): string
	{
		return 'reversible_draft';
	}
	public function proposePermission(): string
	{
		return 'sales.draft';
	}
	public function decidePermission(array $item): string
	{
		return 'sales.draft';
	}
	protected const HEADER_FIELDS = ['id','ordrenr','art','status','konto_id','kontonr','kundeordnr','firmanavn','addr1','addr2','postnr','bynavn','land','kontakt','lev_navn','lev_addr1','lev_addr2','lev_postnr','lev_bynavn','lev_kontakt','lev_email','lev_land','betalingsdage','betalingsbet','cvrnr','ean','institution','email','mail_fakt','phone','notes','momssats','ref','performed_by','lev_adr','valuta','valutakurs','projekt','sprog','pbs','afd','lager','procenttillag','sag_id','sagsnr','tilbudnr','nr','returside','omvbet','felt_1','felt_2','felt_3','felt_4','felt_5','kred_ord_id','ordredate','restordre','vis_lev_addr','levdate','sum','moms','kostpris'];
	protected const LINE_FIELDS = ['id','ordre_id','vare_id','varenr','enhed','beskrivelse','antal','rabat','rabatart','procent','m_rabat','pris','vat_price','kostpris','momsfri','momssats','posnr','projekt','folgevare','rabatgruppe','bogf_konto','vat_account','kred_linje_id','kdo','serienr','variant_id','leveres','leveret','samlevare','omvbet','saet','fast_db','lev_varenr','tilfravalg','lager','barcode'];
	/** @return array{header:?array,lines:list<array>} Stable written fields and delivery/status guards. */
	protected function orderRows(SaldiDb $db, int $id): array
	{
		$rows = parent::orderRows($db, $id);
		$rows['header'] = $rows['header'] === null ? null : array_intersect_key($rows['header'], array_flip(self::HEADER_FIELDS));
		$rows['lines'] = array_map(static fn(array $line): array => array_intersect_key($line, array_flip(self::LINE_FIELDS)), $rows['lines']);
		return $rows;
	}
	/** Port ordrelinjer()'s three-decimal line rounding and ordre.php:6123,6255-6266 header totals.
	 * @return array{sum:string,moms:string,kostpris:string}
	 */
	private function totals(SaldiDb $db, array $header, array $lines): array
	{
		$sum = $vatTotal = $cost = '0.000';
		$exact = static fn(string $expression, array $params): string => (string)$db->value('SELECT ' . $expression, $params);
		$mixed = false;
		$customerType = $db->value('SELECT kontotype FROM adresser WHERE id = ?', [$header['konto_id']]);
		$inclVat = (bool)SaldiSettings::get($db, 'ordre', $customerType === 'erhverv' ? 'vatBusinessCustomers' : 'vatPrivateCustomers', '');
		$percent = $db->value("SELECT box12 FROM grupper WHERE art = 'DIV' AND kodenr = 3 ORDER BY id LIMIT 1");
		foreach ($lines as $line) {
			if (!isset($line['pris'], $line['antal']) || (int)($line['posnr'] ?? 0) <= 0 || is_numeric($line['samlevare'] ?? '') || (!empty($line['saet']) && ($line['samlevare'] ?? '') === 'on')) {
				continue;
			}
			$price = $this->number($line['pris']);
			$discount = $this->number($line['rabat'] ?? '0');
			$net = $this->calculate($db, 'ROUND((CAST(? AS DECIMAL(30,9)) - ' . (($line['rabatart'] ?? '') === 'amount' ? 'CAST(? AS DECIMAL(30,9))' : 'CAST(? AS DECIMAL(30,9)) * CAST(? AS DECIMAL(30,9)) / 100') . ') * CAST(? AS DECIMAL(30,9)) * CAST(? AS DECIMAL(30,9)) / 100, 3)', array_merge([$price], ($line['rabatart'] ?? '') === 'amount' ? [$discount] : [$price, $discount], [$this->number($line['antal']), $percent ? $this->number($line['procent'] ?? '100') : '100']));
			$sum = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) + CAST(? AS DECIMAL(30,9))', [$sum, $net]);
			$vat = $this->number($line['momssats'] ?? '0');
			if (empty($line['momsfri']) && !$this->nonzero($vat)) {
				$vat = $this->number($header['momssats']);
			} else {
				$vat = $this->calculate($db, 'LEAST(CAST(? AS DECIMAL(30,9)),CAST(? AS DECIMAL(30,9)))', [$vat, $this->number($header['momssats'])]);
			}
			if (!empty($line['momsfri']) || !empty($line['omvbet'])) {
				$vat = '0.000';
			}
			$mixed = $mixed || !empty($line['momsfri']) || $db->value('SELECT CAST(? AS DECIMAL(30,9)) <> CAST(? AS DECIMAL(30,9))', [$vat, $this->number($header['momssats'])]);
			$vatTotal = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) + ROUND(CAST(? AS DECIMAL(30,9)) * CAST(? AS DECIMAL(30,9)) / 100, 3)', [$vatTotal, $net, $vat]);
			// ordre.php negates saved m_rabat before rendering its separate quantity-discount row.
			if (!$this->nonzero($discount) && $this->nonzero($this->number($line['m_rabat'] ?? '0')) && empty($line['rabatgruppe'])) {
				$quantityDiscount = $this->number($line['m_rabat']);
				$quantity = $this->number($line['antal']);
				$sum = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) + ROUND(-CAST(? AS DECIMAL(30,9)) * CAST(? AS DECIMAL(30,9)), 2)', [$sum, $quantityDiscount, $quantity]);
				$vatTotal = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) + ROUND(-CAST(? AS DECIMAL(30,9)) * CAST(? AS DECIMAL(30,9)) * CAST(? AS DECIMAL(30,9)) / 100, 2)', [$vatTotal, $quantityDiscount, $quantity, $vat]);
			}

			if (!empty($line['vare_id'])) {
				$unitCost = !empty($line['fast_db']) && $this->nonzero($this->number($line['fast_db'])) ? $exact('CAST(? AS DECIMAL(30,9)) * CAST(? AS DECIMAL(30,9))', [$price, $this->number($line['fast_db'])]) : $this->number($line['kostpris'] ?? '0');
				$cost = $exact('CAST(? AS DECIMAL(30,9)) + CAST(? AS DECIMAL(30,9)) * CAST(? AS DECIMAL(30,9))', [$cost, $unitCost, $this->number($line['antal'])]);
			}
		}
		$sum = $this->calculate($db, 'ROUND(CAST(? AS DECIMAL(30,9)), 2)', [$sum]);
		$vatTotal = $this->calculate($db, 'ROUND(CAST(? AS DECIMAL(30,9)), 2)', [$vatTotal]);
		$surcharge = $exact('CAST(? AS DECIMAL(30,9)) * CAST(? AS DECIMAL(30,9)) / 100', [$sum, $this->number($header['procenttillag'] ?? '0')]);
		$sum = $exact('CAST(? AS DECIMAL(30,9)) + CAST(? AS DECIMAL(30,9))', [$sum, $surcharge]);
		$vatTotal = ($mixed || $inclVat) ? $exact('CAST(? AS DECIMAL(30,9)) + CAST(? AS DECIMAL(30,9)) * CAST(? AS DECIMAL(30,9)) / 100', [$vatTotal, $surcharge, $this->number($header['momssats'])]) : $exact('CAST(? AS DECIMAL(30,9)) * CAST(? AS DECIMAL(30,9)) / 100', [$sum, $this->number($header['momssats'])]);
		return ['sum' => $this->calculate($db, 'CAST(? AS DECIMAL(30,9))', [$sum]), 'moms' => $this->calculate($db, 'ROUND(CAST(? AS DECIMAL(30,9)), 3)', [$vatTotal]), 'kostpris' => $this->calculate($db, 'CAST(? AS DECIMAL(30,9))', [$cost])];
	}
	/** Decimal database value without a binary float conversion. */
	protected function number(mixed $value): string
	{
		$value = (string)($value ?? '0');
		if (!preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', $value)) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Invalid stored decimal');
		}
		return $value;
	}
	protected function nonzero(string $value): bool
	{
		return (bool)preg_match('/[1-9]/', $value);
	}
	/** @return array{order_id:int,order_number:int,rows_sha256:string,screen:string,approval_date:string,currency_rate:string,department:int,warehouse:int} */
	protected function insertDraft(SaldiDb $db, array $header, array $lines): array
	{
		// get_next_order_number('DO'): same table lock and the combined DO/DK maximum.
		$this->orderLock($db);
		$number = (int)$db->value("SELECT COALESCE(MAX(ordrenr), 0) + 1 FROM ordrer WHERE art IN ('DO','DK')");
		$header = array_replace($header, $this->totals($db, $header, $lines));
		$id = $db->insert('ordrer', ['ordrenr' => $number] + $header);
		foreach ($lines as $line) {
			$db->insert('ordrelinjer', ['ordre_id' => $id] + $line);
		}
		return ['order_id' => $id, 'order_number' => $number, 'rows_sha256' => $this->hash($this->orderRows($db, $id)), 'screen' => SaldiScreens::link('sales_order', $id), 'approval_date' => $this->today(), 'currency_rate' => $this->number(ifset($header, 'valutakurs', '100')), 'department' => (int)ifset($header, 'afd', 0), 'warehouse' => (int)ifset($header, 'lager', 0)];
	}
	/** @return array{columns:list<string>,rows:list<array>,fields:list<array>} */
	protected function salesCard(SaldiTenantContext $ctx, SaldiDb $db, array $header, array $lines, array $fields): array
	{
		return ['columns' => array_map(fn(int $id): string => $this->text($ctx, $db, $id), [5926,5743,5927,5928,428]), 'rows' => array_map(fn(array $line): array => [(string)ifset($line, 'varenr', ''), (string)$line['beskrivelse'], SaldiToolRuntime::quantityDa(ifset($line, 'antal', '')), isset($line['pris']) ? $this->money((string)$line['pris']) : '', SaldiToolRuntime::quantityDa(ifset($line, 'rabat', ''))], $lines), 'fields' => array_merge([['label' => $this->text($ctx, $db, 35, 'Kunde'), 'value' => (string)ifset($header, 'kontonr', '') . ' ' . (string)ifset($header, 'firmanavn', '')], ['label' => $this->text($ctx, $db, 776, 'Valuta'), 'value' => ifset($header, 'valuta') ?: 'DKK']], $fields)];
	}
}

final class SaldiSalesOrderDraftKind extends SaldiSalesDraftKind
{
	public function name(): string
	{
		return 'sales_order.draft';
	}
	/** Build the exact new debtor header from debitor/ordre.php:836-851.
	 * @return array<string,mixed>
	 */
	private function header(SaldiTenantContext $ctx, SaldiDb $db, array $party, array $dg, array $p, array &$subject, bool $approval): array
	{
		$header = ['konto_id' => (int)$party['id']];
		foreach (['kontonr','firmanavn','addr1','addr2','postnr','bynavn','land','betalingsbet','cvrnr','ean','institution','email','notes','kontakt','pbs'] as $field) {
			$header[$field] = trim((string)ifset($party, $field, ''));
		}
		$header['betalingsdage'] = (int)ifset($party, 'betalingsdage', 0);
		$emails = $db->all('SELECT * FROM kontakt_emails WHERE konto_id = ? ORDER BY id' . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$party['id']]);
		$subject['emails'] = $emails;
		$preferred = array_values(array_filter($emails, fn(array $r): bool => strtolower(trim((string)$r['email_type'])) === 'faktura'));
		$email = $preferred[0] ?? null;
		if (!trim((string)ifset($email, 'email', ''))) {
			$email = $emails[0] ?? null;
		}
		$header['email'] = trim((string)ifset($email, 'email', '')) ?: $header['email'];
		$header['mail_fakt'] = (string)ifset($party, 'mailfakt', '');
		$div2 = $db->one("SELECT * FROM grupper WHERE art = 'DIV' AND kodenr = 2 ORDER BY id LIMIT 1");
		$div3 = $db->one("SELECT * FROM grupper WHERE art = 'DIV' AND kodenr = 3 ORDER BY id LIMIT 1");
		$subject['defaults'] = [$div2, $div3];
		$phone = (string)ifset($party, 'tlf', '');
		if ($phone === '' && ifset($div2, 'box5') === 'on') {
			$phone = $header['kontonr'];
		}
		$header['phone'] = mb_substr($phone, 0, 15);
		if ((int)ifset($party, 'pbs_nr', 0) > 0) {
			$header['pbs'] = 'BS';
		}
		foreach (['navn' => 'firmanavn','addr1' => 'addr1','addr2' => 'addr2','postnr' => 'postnr','bynavn' => 'bynavn','land' => 'land','email' => 'email','kontakt' => 'kontakt'] as $target => $source) {
			$header['lev_' . $target] = trim((string)ifset($party, 'lev_' . $source, ''));
		}
		foreach (range(1, 5) as $n) {
			$header['felt_' . $n] = $this->text($ctx, $db, 243 + $n) === $this->text($ctx, $db, 254 + $n) ? (string)ifset($party, 'felt_' . $n, '') : '';
		}
		$sm = $this->group($ctx, $db, 'SM', (int)substr((string)ifset($dg, 'box1', ''), 1, 1));
		$subject['header_vat'] = $sm;
		$employee = $approval ? $db->one('SELECT a.navn, a.afd FROM brugere b JOIN ansatte a ON a.id = b.ansat_id WHERE b.id = ?', [$ctx->userId]) : [];
		$language = (string)ifset($dg, 'box4', '');
		if ($approval && $employee) {
			$background = $db->value("SELECT var_value FROM settings WHERE var_name = 'sprog' AND var_grp = 'brugerSprog' AND user_id = ? ORDER BY id LIMIT 1", [$ctx->userId]);
			$language = (string)($background ?? 'Dansk') ?: $language;
		}
		$responsible = (int)ifset($party, 'kontoansvarlig', 0) ? $db->one('SELECT * FROM ansatte WHERE id = ?', [$party['kontoansvarlig']]) : null;
		$subject['responsible'] = $responsible;
		$department = (int)(ifset($employee, 'afd') ?: ifset($responsible, 'afd', 0));
		$warehouse = $department ? (int)$db->value("SELECT box1 FROM grupper WHERE art = 'AFD' AND kodenr = ? ORDER BY id LIMIT 1", [$department]) : 0;
		if ($department && !$warehouse) {
			$warehouse = (int)$db->value("SELECT kodenr FROM grupper WHERE art = 'LG' AND box1 = ? ORDER BY id LIMIT 1", [(string)$department]);
		}
		$currency = trim((string)ifset($dg, 'box3', ''));
		$rate = '100.000';
		if ($approval) {
			[$currency, $rate] = $this->currency($db, $currency, $this->today());
		}
		$surcharge = str_replace(',', '.', explode(chr(9), (string)ifset($div3, 'box13', '0'))[0]);
		$surcharge = preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', $surcharge) ? $surcharge : '0.000';
		return array_replace($header, ['notes' => ifset($p, 'note', $header['notes']), 'art' => 'DO', 'ordredate' => $p['order_date'], 'momssats' => $this->number(ifset($sm, 'box2') ?: '0'), 'tidspkt' => gmdate('Y-m-d H:i:s'), 'ref' => ifset($p, 'reference', mb_substr((string)(ifset($responsible, 'navn') ?: (ifset($employee, 'navn') ?: $ctx->username)), 0, 40)), 'performed_by' => '', 'valuta' => $currency, 'valutakurs' => $rate, 'sprog' => $language, 'afd' => $department, 'lager' => max(1, $warehouse), 'status' => $p['type'] === 'order' ? 1 : 0, 'restordre' => '0.000', 'vis_lev_addr' => '', 'procenttillag' => $surcharge, 'omvbet' => ifset($dg, 'box9') ? 'on' : '', 'levdate' => ifset($p, 'delivery_date'), 'kred_ord_id' => 0]);
	}
	/** opret_ordrelinje(): VG -> kontoplan -> SM, discounts and price precedence.
	 * @return array<string,mixed>
	 */
	private function productLine(SaldiTenantContext $ctx, SaldiDb $db, array $product, array $party, array $dg, array $header, array $line, array $p, int $position, array &$subject): array
	{
		$vg = $this->group($ctx, $db, 'VG', (int)$product['gruppe']);
		$subject['groups'][] = $vg;
		$account = (int)ifset($vg, 'box4', 0);
		if (!$vg || !$account) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Product requires a sales account and product group');
		}
		$free = (string)ifset($vg, 'box7', '');
		$reverse = ifset($dg, 'box9') && ifset($vg, 'box6') ? 'on' : '';
		$vat = '0.000';
		$vatAccount = 0;
		if (!$free) {
			$setting = $db->one('SELECT * FROM settings WHERE var_name = ? ORDER BY id LIMIT 1', ['maxDepVatRate_' . $header['afd']]);
			$subject['vat_settings'][] = $setting;
			$accountRow = $db->one('SELECT * FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ?' . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$account, $ctx->fiscalYear]);
			$subject['accounts'][] = $accountRow === null ? null : array_intersect_key($accountRow, array_flip(['kontonr','moms','kontotype','lukket','regnskabsaar']));
			$code = (int)substr((string)ifset($accountRow, 'moms', ''), 1);
			$sm = $this->group($ctx, $db, 'SM', $setting ? (int)$header['afd'] : $code);
			// Approval-specific department VAT is recorded in the inserted row, not the subject hash.
			if (!$setting) {
				$subject['groups'][] = $sm;
			}
			$vatAccount = (int)ifset($sm, 'box1', 0);
			$vat = $this->number(ifset($sm, 'box2') ?: $header['momssats']);
			if (!$vatAccount) {
				$vat = '0.000';
			}
		}
		$price = isset($line['unit_price']) ? $line['unit_price'] : '0';
		$cost = $this->number(ifset($product, 'kostpris'));
		if (!isset($line['unit_price']) && ifset($dg, 'box8') && $this->nonzero($this->number(ifset($product, 'tier_price')))) {
			$price = $this->number($product['tier_price']);
		}
		$clock = new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen'));
		$start = (string)ifset($product, 'special_from_date', '') . ' ' . (string)ifset($product, 'special_from_time', '');
		$end = (string)ifset($product, 'special_to_date', '') . ' ' . (string)ifset($product, 'special_to_time', '');
		$window = $start <= $clock->format('Y-m-d H:i:s') && $end >= $clock->format('Y-m-d H:i:s');
		$special = $this->number(ifset($product, 'special_price'));
		$discount = ifset($line, 'discount_pct', '0.000');
		$discountArt = '';
		// Keep discount inputs in the source revision independent of promotion activation.
		$drg = array_filter($subject['discount_groups'], static fn(array $group): bool => $group['art'] === 'DRG');
		$dvrg = array_filter($subject['discount_groups'], static fn(array $group): bool => $group['art'] === 'DVRG');
		$customerGroup = (int)ifset($party, 'rabatgruppe', 0) ?: ($drg ? 0 : (int)$party['gruppe']);
		$productGroup = (int)ifset($product, 'dvrg', 0) ?: ($dvrg ? 0 : (int)$product['gruppe']);
		$discountRow = $db->one('SELECT * FROM rabat WHERE vare = ? AND debitor = ? ORDER BY id LIMIT 1' . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$productGroup, $customerGroup]);
		$subject['discounts'][] = $discountRow;

		if ($window && ifset($product, 'specialtype') === 'percent' && !array_key_exists('discount_pct', $line)) {
			$discount = $special;
		}
		if (!isset($line['unit_price']) && !$this->nonzero($price)) {
			$offer = $db->one('SELECT * FROM varetilbud WHERE vare_id = ? AND ugedag = ? AND startdag <= ? AND slutdag >= ? AND starttid <= ? AND sluttid >= ? ORDER BY id LIMIT 1' . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$product['id'], (int)$clock->format('N'), $clock->getTimestamp(), $clock->getTimestamp(), $clock->format('H:i:s'), $clock->format('H:i:s')]);
			if ($offer) {
				$price = $this->number($offer['salgspris']);
				$cost = $this->number($offer['kostpris']);
			} elseif ($window && $this->nonzero($special) && ifset($product, 'specialtype') !== 'percent') {
				$price = $special;
				$cost = $this->number(ifset($product, 'campaign_cost'));
			} else {
				$price = $this->number($product['salgspris']);
			}
		}
		if (!isset($line['discount_pct']) && !$this->nonzero($discount)) {
			$discount = $this->number(ifset($discountRow, 'rabat'));
			$discountArt = (string)ifset($discountRow, 'rabatart', '');
		}
		if ($free || $reverse) {
			$vat = '0.000';
			$vatAccount = 0;
		} elseif (isset($line['unit_price']) || $this->nonzero($this->number(ifset($product, 'tier_price'))) && ifset($dg, 'box8')) {
			$vat = $this->calculate($db, 'LEAST(CAST(? AS DECIMAL(30,9)), CAST(? AS DECIMAL(30,9)))', [$vat, $header['momssats']]);
		}
		$fast = '0.000';
		if (!$this->nonzero($this->number($product['salgspris'])) && $this->nonzero($price) && $db->value('SELECT CAST(? AS DECIMAL(30,9)) > 0 AND CAST(? AS DECIMAL(30,9)) < 1', [$cost, $cost])) {
			$fast = $cost;
			$cost = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) * (1 - CAST(? AS DECIMAL(30,9)) / 100) * CAST(? AS DECIMAL(30,9))', [$price, $discount, $cost]);
		}
		if ($this->nonzero($header['valutakurs']) && $header['valutakurs'] !== '100.000') {
			$price = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) * 100 / CAST(? AS DECIMAL(30,9))', [$price, $header['valutakurs']]);
			$cost = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) * 100 / CAST(? AS DECIMAL(30,9))', [$cost, $header['valutakurs']]);
		}
		return ['vare_id' => (int)$product['id'], 'varenr' => (string)$product['varenr'], 'enhed' => (string)ifset($product, 'enhed', ''), 'beskrivelse' => $line['description'], 'antal' => $line['quantity'], 'rabat' => $discount, 'rabatart' => $discountArt, 'procent' => '100.000', 'm_rabat' => '0.000', 'pris' => $price, 'vat_price' => $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) * (1 + CAST(? AS DECIMAL(30,9)) / 100)', [$price, $vat]), 'kostpris' => $cost, 'momsfri' => $free, 'momssats' => $vat, 'posnr' => $position, 'projekt' => '', 'folgevare' => (int)ifset($product, 'folgevare', 0), 'rabatgruppe' => (int)ifset($product, 'rabatgruppe', 0), 'bogf_konto' => $account, 'vat_account' => $vatAccount, 'kred_linje_id' => 0, 'kdo' => '', 'serienr' => (string)ifset($product, 'serienr', ''), 'variant_id' => '0', 'leveres' => '0.000', 'samlevare' => (string)ifset($product, 'samlevare', ''), 'omvbet' => $reverse, 'saet' => 0, 'fast_db' => $fast, 'lev_varenr' => '', 'tilfravalg' => '', 'lager' => $header['lager'], 'barcode' => ''];
	}
	/** @return array{payload:array,header:array,lines:list<array>,subject:array} */
	private function prepared(SaldiTenantContext $ctx, SaldiDb $db, array $input, bool $approval = false): array
	{
		$this->keys($input, ['customer_account','type','lines','reference','note','delivery_date','order_date']);
		if ($db->inTransaction()) {
			$this->orderLock($db);
		}
		$party = $this->party($db, ifset($input, 'customer_account'), 'D');
		$dg = $this->group($ctx, $db, 'DG', (int)$party['gruppe']);
		if (!$dg || !in_array(ifset($input, 'type'), ['offer','order'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Customer group or order type unavailable');
		}
		$p = ['customer_account' => (string)$party['kontonr'], 'type' => $input['type'], 'order_date' => $this->date(ifset($input, 'order_date', $this->today()), 'order_date'), 'lines' => []];
		foreach (['reference' => 40,'note' => 200] as $field => $max) {
			if (array_key_exists($field, $input)) {
				$p[$field] = $this->string($input[$field], 0, $max, $field);
			}
		}
		if (isset($input['delivery_date'])) {
			$p['delivery_date'] = $this->date($input['delivery_date'], 'delivery_date');
		}
		$source = ifset($input, 'lines');
		if (!is_array($source) || !array_is_list($source) || count($source) < 1 || count($source) > 50) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected 1 to 50 sales lines');
		}
		$subject = ['party' => $party, 'customer_group' => $dg];
		$header = $this->header($ctx, $db, $party, $dg, $p, $subject, $approval);
		[$header['valuta'], $header['valutakurs']] = $this->currency($db, $header['valuta'], $this->today());
		$subject['discount_groups'] = $db->all("SELECT * FROM grupper WHERE art IN ('DRG','DVRG') ORDER BY id");
		$subject['discounts'] = [];
		$lines = [];
		foreach ($source as $index => $line) {
			if (!is_array($line)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid sales line');
			}
			$this->keys($line, ['product_id','item_no','description','quantity','unit_price','discount_pct','pricing']);
			$n = ['quantity' => $this->decimal(ifset($line, 'quantity'), 'quantity', true)];
			if ($db->value('SELECT CAST(? AS DECIMAL(30,9)) >= 100000000000', [$n['quantity']])) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Quantity too large');
			}
			$product = null;
			if (isset($line['product_id']) || isset($line['item_no'])) {
				$id = ifset($line, 'product_id');
				if ($id !== null && (!is_int($id) || $id <= 0)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid product id');
				}
				$itemNo = isset($line['item_no']) ? $this->string($line['item_no'], 1, 100, 'item_no') : null;
				$product = $db->one('SELECT * FROM varer WHERE ' . ($id !== null ? 'id = ?' : 'UPPER(varenr) = UPPER(?)') . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$id ?? $itemNo]);
				if (!$product || ifset($product, 'lukket') === 'on' || ($id !== null && $itemNo !== null && strcasecmp((string)$product['varenr'], $itemNo) !== 0)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Product missing, closed or mismatched');
				}
				if ($db->value('SELECT 1 FROM variant_varer WHERE vare_id = ? LIMIT 1', [$product['id']]) !== null) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Variant product requires an explicit variant');
				}
				$n['product_id'] = (int)$product['id'];
				$subject['products'][] = array_intersect_key($product, array_flip(['id','varenr','beskrivelse','gruppe','enhed','salgspris','tier_price','kostpris','lukket','rabatgruppe','dvrg','special_price','specialtype','special_from_date','special_to_date','special_from_time','special_to_time','campaign_cost','folgevare','serienr','samlevare']));
				$subject['offers'][] = $db->all('SELECT * FROM varetilbud WHERE vare_id = ? ORDER BY id' . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$product['id']]);
			}
			$n['description'] = $this->string(ifset($line, 'description', (string)ifset($product, 'beskrivelse', '')), 1, 100, 'description');
			foreach (['unit_price','discount_pct'] as $field) {
				if (array_key_exists($field, $line)) {
					$n[$field] = $this->decimal($line[$field], $field);
					$max = $field === 'unit_price' ? '99999999' : '100';
					if ($db->value('SELECT CAST(? AS DECIMAL(30,9)) > CAST(? AS DECIMAL(30,9))', [$n[$field], $max])) {
						throw new SaldiRuntimeError('VALIDATION_ERROR', 'Price or discount too large');
					}
				}
			}
			if (!$product) {
				if (isset($n['unit_price']) && $this->nonzero($n['unit_price']) || isset($n['discount_pct']) && $this->nonzero($n['discount_pct'])) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Priced or discounted lines require a product');
				}
				// debitor/ordre.php:2706; bypass PHP 8-broken free-text opret_ordrelinje().
				$lines[] = ['posnr' => $index + 1, 'beskrivelse' => $n['description']];
			} else {
				$resolved = $this->productLine($ctx, $db, $product, $party, $dg, $header, $n, $p, $index + 1, $subject);
				$pricing = $line['pricing'] ?? array_intersect_key($resolved, array_flip(['pris','rabat','rabatart','kostpris','fast_db']));
				if (!is_array($pricing) || count($pricing) !== 5 || array_diff(array_keys($pricing), ['rabat','rabatart','pris','kostpris','fast_db'])) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid pinned sales pricing');
				}
				foreach (['pris','rabat','kostpris','fast_db'] as $field) {
					$pricing[$field] = $this->number($pricing[$field]);
				}
				$n['pricing'] = $pricing;
				$resolved = array_replace($resolved, $pricing);
				$resolved['vat_price'] = $this->calculate($db, 'CAST(? AS DECIMAL(30,9)) * (1 + CAST(? AS DECIMAL(30,9)) / 100)', [$resolved['pris'], $resolved['momssats']]);
				$lines[] = $resolved;
			}
			$p['lines'][] = $n;
		}
		return ['payload' => $p, 'header' => $header, 'lines' => $lines, 'subject' => ['payload' => $p] + $subject];
	}
	/** @return array{payload:array,title:string,subject_type:?string,subject_id:?string,proposed_rev:?string,dedupe_key:?string,visibility:string,assignee_permission:?string,owner_user_id:?int} */
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$state = $this->prepared($ctx, $db, $input);
		$n = $this->normalized($ctx, $state['payload'], $this->text($ctx, $db, $state['payload']['type'] === 'order' ? 6103 : 6102) . ': ' . $state['header']['firmanavn'], null, 'permission', 'sales.draft');
		$n['subject_type'] = 'debtor';
		$n['subject_id'] = (string)$state['header']['konto_id'];
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
		// Hash source inputs with proposal settings, before resolving approver values.
		$source = $this->prepared($ctx, $db, $item['payload']);
		if (isset($item['proposed_rev']) && $item['proposed_rev'] !== $this->hash($source['subject'])) {
			throw new SaldiRuntimeError('STALE_CONTEXT', 'Sales inputs changed');
		}
		$state = $this->prepared($ctx, $db, $item['payload'], true);
		return $this->insertDraft($db, $state['header'], $state['lines']);
	}
	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool
	{
		return $this->intactOrder($db, $item, $item['payload']['type'] === 'order' ? 1 : 0, 'DO');
	}
	/** @return array{columns:list<string>,rows:list<array>,fields:list<array>} */
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$p = $item['payload'];
		$header = $db->one("SELECT kontonr, firmanavn FROM adresser WHERE art = 'D' AND kontonr = ?", [ifset($p, 'customer_account', '')]) ?? [];
		$lines = array_map(fn(array $r): array => ['beskrivelse' => (string)ifset($r, 'description', ''), 'antal' => (string)ifset($r, 'quantity', ''), 'pris' => ifset($r, 'unit_price'), 'rabat' => ifset($r, 'discount_pct', '')], $p['lines']);
		if (isset($item['result']['order_id'])) {
			$header = $db->one('SELECT * FROM ordrer WHERE id = ?', [$item['result']['order_id']]) ?? $header;
			$lines = $db->all('SELECT * FROM ordrelinjer WHERE ordre_id = ? ORDER BY posnr, id', [$item['result']['order_id']]);
		} else {
			try {
				$state = $this->prepared($ctx, $db, $p, true);
				$header = $state['header'];
				$lines = $state['lines'];
			} catch (SaldiRuntimeError $e) {
				// Keep stale proposals readable; approval still revalidates.
			}
		}
		return $this->salesCard($ctx, $db, $header, $lines, [['label' => $this->text($ctx, $db, 1148, 'Levering'), 'value' => (string)ifset($p, 'delivery_date', '')], ['label' => $this->text($ctx, $db, 6042, 'Reference'), 'value' => (string)ifset($p, 'reference', '')], ['label' => $this->text($ctx, $db, 2881, 'Notat'), 'value' => (string)ifset($p, 'note', '')]]);
	}
}
