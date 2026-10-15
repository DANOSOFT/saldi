<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiPartyCreateKind.php --- ai/mcp-assistant --- 2026.10.03
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
// Approval-gated customer and supplier creation with legacy defaults and exact-row undo.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Port party card inserts, duplicate guards and reversible creation.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiMasterDraftKind.php';

final class SaldiPartyCreateKind extends SaldiMasterDraftKind
{
	public function name(): string
	{
		return 'party.create';
	}
	public function actionClass(): string
	{
		return 'persistent';
	}
	public function proposePermission(): string
	{
		return 'debtors.draft';
	}
	public function decidePermission(array $item): string
	{
		return ifset($item, ['payload', 'ledger']) === 'supplier' ? 'creditors.draft' : 'debtors.draft';
	}
	private function normalizedName(string $name): string
	{
		$name = mb_strtolower($name, 'UTF-8');
		$name = preg_replace('~(?<![\pL\pN])(?:aps|a/s|i/s|ivs)(?![\pL\pN])~u', '', $name);
		return trim(preg_replace('/\s+/u', ' ', $name));
	}
	/** Mirror the customer card's payment defaults; supplier cards default to Netto/8. @return array{type:string,days:int} */
	private function defaultTerms(SaldiDb $db, string $art): array
	{
		$terms = 'Netto';
		$days = 8;
		if ($art === 'D') {
			$previous = 0;
			foreach ($db->all('SELECT DISTINCT betalingsbet FROM adresser') as $index => $row) {
				$value = (string)$row['betalingsbet'];
				$count = (int)$db->value("SELECT COUNT(*) FROM adresser WHERE art = 'D' AND betalingsbet = ?", [$value]);
				if ($index === 0 || $count > $previous) {
					$terms = $value;
				}
				$previous = $count;
			}
			$previous = 0;
			foreach ($db->all('SELECT DISTINCT betalingsdage FROM adresser WHERE betalingsdage <> 0') as $index => $row) {
				$count = (int)$db->value("SELECT COUNT(*) FROM adresser WHERE art = 'D' AND betalingsdage = ?", [$row['betalingsdage']]);
				if ($index > 0 && $count > $previous) {
					$days = (int)$row['betalingsdage'];
				}
				$previous = $count;
			}
		}
		$type = ['Netto' => 'netto', 'Lb. md.' => 'lobende_maaned', 'Kontant' => 'kontant'][$terms] ?? 'netto';
		return ['type' => $type, 'days' => $type === 'kontant' ? 0 : max(0, min(120, $days))];
	}
	/** @return array{payload:array,title:string,subject_type:?string,subject_id:?string,proposed_rev:?string,dedupe_key:?string,visibility:string,assignee_permission:?string,owner_user_id:?int} */
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$this->keys($input, ['ledger','name','cvr','ean','address','zip','city','country','email','phone','contact','payment_terms','group']);
		$ledger = ifset($input, 'ledger');
		if (!in_array($ledger, ['customer', 'supplier'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid ledger', ['fields' => ['$.ledger']]);
		}
		$art = $ledger === 'customer' ? 'D' : 'K';
		if ($db->inTransaction()) {
			if ($db->isPostgres()) {
				// Shared allocation keys: 5130513 with debkort_save.php, 5130514 with kreditorkort.php.
				$db->value('SELECT pg_advisory_xact_lock(?)', [$art === 'D' ? 5130513 : 5130514]);
			}
			$db->lock('saldi_adresser_' . $art);
		}
		$p = ['ledger' => $ledger, 'name' => $this->string(ifset($input, 'name'), 2, 100, 'name')];
		foreach (['cvr' => 8, 'ean' => 13, 'address' => 60, 'zip' => 10, 'city' => 40, 'country' => 40, 'email' => 100, 'phone' => 20, 'contact' => 60] as $field => $max) {
			if (array_key_exists($field, $input)) {
				$p[$field] = $this->string($input[$field], 0, $max, $field);
			}
		}
		foreach (['name' => 'firmanavn', 'email' => 'email', 'phone' => 'tlf'] as $field => $column) {
			$limit = $db->value('SELECT character_maximum_length FROM information_schema.columns WHERE table_schema = ' . ($db->isPostgres() ? 'current_schema()' : 'DATABASE()') . ' AND table_name = ? AND column_name = ?', ['adresser', $column]);
			if (isset($p[$field]) && $limit !== null && mb_strlen($p[$field]) > (int)$limit) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Field exceeds the installed party schema', ['reason' => 'schema_limit', 'fields' => ['$.' . $field], 'max_length' => (int)$limit]);
			}
		}
		foreach (['cvr' => 8, 'ean' => 13] as $field => $length) {
			if (isset($p[$field]) && !preg_match('/^[0-9]{' . $length . '}$/D', $p[$field])) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid ' . $field, ['fields' => ['$.' . $field]]);
			}
		}
		if (isset($p['email']) && !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid email', ['fields' => ['$.email']]);
		}
		$name = $this->normalizedName($p['name']);
		foreach ($db->all("SELECT id, kontonr, firmanavn, cvrnr FROM adresser WHERE art = ? AND COALESCE(lukket, '') <> 'on'", [$art]) as $row) {
			if ((!empty($p['cvr']) && trim((string)$row['cvrnr']) === $p['cvr']) || $this->normalizedName((string)$row['firmanavn']) === $name) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Party already exists', ['reason' => 'duplicate_party', 'existing' => ['party_id' => (int)$row['id'], 'account' => (string)$row['kontonr']]]);
			}
		}
		$groupArt = $art === 'D' ? 'DG' : 'KG';
		$group = ifset($input, 'group');
		if ($group !== null && (!is_int($group) || $group <= 0)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid group', ['fields' => ['$.group']]);
		}
		if ($group === null) {
			$group = (int)$db->value('SELECT MIN(kodenr) FROM grupper WHERE art = ?', [$groupArt]);
		}
		if ($group < 1 || $db->value('SELECT 1 FROM grupper WHERE art = ? AND kodenr = ? LIMIT 1', [$groupArt, $group]) === null) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Group does not exist', ['fields' => ['$.group']]);
		}
		$p['group'] = $group;
		$terms = ifset($input, 'payment_terms', fn(): array => $this->defaultTerms($db, $art));
		if (!is_array($terms)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid payment terms');
		}
		$this->keys($terms, ['type', 'days']);
		$type = ifset($terms, 'type', 'netto');
		$days = ifset($terms, 'days', 0);
		if (!in_array($type, ['netto','lobende_maaned','kontant'], true) || !is_int($days) || $days < 0 || $days > 120) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid payment terms', ['fields' => ['$.payment_terms']]);
		}
		$p['payment_terms'] = ['type' => $type, 'days' => $type === 'kontant' ? 0 : $days];
		$permission = $art === 'D' ? 'debtors.draft' : 'creditors.draft';
		return $this->normalized($ctx, $p, $this->text($ctx, $db, $art === 'D' ? 5902 : 5903) . ': ' . $p['name'], 'party:' . $ledger . ':' . (ifset($p, 'cvr') ?: sha1($name)), 'permission', $permission);
	}
	/** Mirror the legacy account-allocation lock, with guaranteed release on errors. */
	private function legacyLock(SaldiDb $db, bool $customer): void
	{
		if ($db->isPostgres()) {
			$db->value('SELECT pg_advisory_lock(?)', [$customer ? 5130513 : 5130514]);
		}
	}
	/** @return array{party_id:int,account:string,row_sha256:string,contact_id:?int,contact_sha256:?string} */
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$customer = $item['payload']['ledger'] === 'customer';
		$this->legacyLock($db, $customer);
		try {
			return $db->transaction(function () use ($ctx, $db, $item, $customer): array {
				$art = $customer ? 'D' : 'K';
				$db->lock('saldi_adresser_' . $art);
				$p = $this->validate($ctx, $db, $item['payload'])['payload'];
				$numbers = array_column($db->all('SELECT kontonr FROM adresser WHERE art = ?', [$art]), 'kontonr');
				$account = 1000;
				while (in_array($account, $numbers)) {
					$account++;
				}
				$row = ['kontonr' => (string)$account, 'firmanavn' => $p['name'], 'addr1' => ifset($p, 'address', ''), 'addr2' => '', 'postnr' => ifset($p, 'zip', ''), 'bynavn' => ifset($p, 'city', ''), 'land' => ifset($p, 'country', ''), 'kontakt' => ifset($p, 'contact', ''), 'tlf' => ifset($p, 'phone', ''), 'mobile' => '', 'email' => ifset($p, 'email', ''), 'web' => '', 'betalingsdage' => $p['payment_terms']['days'], 'kreditmax' => '0.000', 'betalingsbet' => ['netto' => 'Netto', 'lobende_maaned' => 'Lb. md.', 'kontant' => 'Kontant'][$p['payment_terms']['type']], 'cvrnr' => ifset($p, 'cvr', ''), 'notes' => '', 'art' => $art, 'gruppe' => $p['group'], 'felt_1' => '', 'felt_2' => '', 'felt_3' => '', 'felt_4' => '', 'felt_5' => '', 'lukket' => ''];
				if ($customer) {
					$private = (int)$db->value("SELECT COUNT(*) FROM adresser WHERE kontotype = 'privat'");
					$business = (int)$db->value("SELECT COUNT(*) FROM adresser WHERE kontotype = 'erhverv'");
					$row += ['mailfakt' => '', 'ean' => ifset($p, 'ean', ''), 'institution' => '', 'kontoansvarlig' => 0, 'oprettet' => $this->today(), 'kontotype' => $private > $business ? 'privat' : 'erhverv', 'fornavn' => '', 'efternavn' => '', 'lev_firmanavn' => '', 'lev_fornavn' => '', 'lev_efternavn' => '', 'lev_addr1' => '', 'lev_addr2' => '', 'lev_postnr' => '', 'lev_bynavn' => '', 'lev_land' => '', 'lev_kontakt' => '', 'lev_tlf' => '', 'lev_email' => '', 'vis_lev_addr' => 'on', 'stripe_fravalg' => '', 'kategori' => '', 'rabatgruppe' => 0, 'status' => ''];
				} else {
					$row += ['ean' => ifset($p, 'ean', '')];
				}
				if ($customer && !$db->hasColumn('adresser', 'stripe_fravalg')) {
					unset($row['stripe_fravalg']);
				}
				$id = $db->insert('adresser', $row);
				$contactId = $customer && $row['kontakt'] !== '' ? $db->insert('ansatte', ['konto_id' => $id, 'navn' => $row['kontakt']]) : null;
				return ['party_id' => $id, 'account' => (string)$account, 'row_sha256' => $this->hash($db->one('SELECT * FROM adresser WHERE id = ?', [$id])), 'contact_id' => $contactId, 'contact_sha256' => $contactId === null ? null : $this->hash($db->one('SELECT * FROM ansatte WHERE id = ?', [$contactId]))];
			});
		} finally {
			if ($db->isPostgres()) {
				$db->value('SELECT pg_advisory_unlock(?)', [$customer ? 5130513 : 5130514]);
			}
		}
	}
	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool
	{
		if (ifset($item, 'status') !== 'applied' || !isset($item['result']['party_id'], $item['result']['row_sha256'])) {
			return false;
		}
		$r = $item['result'];
		// undo() freezes the reference tables and rechecks; the preview must not acquire
		// a row lock before that table-lock order and deadlock another undo.
		$row = $db->one('SELECT * FROM adresser WHERE id = ?' . $this->undoReadLock($db), [$r['party_id']]);
		if (!$row || !hash_equals($r['row_sha256'], $this->hash($row))) {
			return false;
		}
		foreach (['openpost', 'ordrer'] as $table) {
			if ($db->value('SELECT 1 FROM ' . $table . ' WHERE konto_id = ? LIMIT 1' . $this->undoReadLock($db), [$r['party_id']]) !== null) {
				return false;
			}
		}
		if ($db->value('SELECT 1 FROM kassekladde WHERE (d_type = ? AND debet = ?) OR (k_type = ? AND kredit = ?) LIMIT 1' . $this->undoReadLock($db), [$row['art'], $row['kontonr'], $row['art'], $row['kontonr']]) !== null
			|| $db->value('SELECT 1 FROM transaktioner WHERE adresser_id = ? LIMIT 1' . $this->undoReadLock($db), [$r['party_id']]) !== null) {
			return false;
		}
		foreach (['delivery_addresses' => 'account_id', 'kontakt_emails' => 'konto_id'] as $table => $column) {
			if ($db->hasTable($table) && $db->value('SELECT 1 FROM ' . $table . ' WHERE ' . $column . ' = ? LIMIT 1' . $this->undoReadLock($db), [$r['party_id']]) !== null) {
				return false;
			}
		}
		if ($db->value('SELECT 1 FROM vare_lev WHERE lev_id = ? LIMIT 1' . $this->undoReadLock($db), [$r['party_id']]) !== null) {
			return false;
		}
		if (ifset($r, 'contact_id') !== null) {
			foreach (['brugere' => 'ansat_id', 'transaktioner' => 'ansat', 'kassekladde' => 'ansat'] as $table => $column) {
				if ($db->value('SELECT 1 FROM ' . $table . ' WHERE ' . $column . ' = ? LIMIT 1' . $this->undoReadLock($db), [(string)$r['contact_id']]) !== null) {
					return false;
				}
			}
		}
		$contacts = $db->all('SELECT * FROM ansatte WHERE konto_id = ? ORDER BY id' . $this->undoReadLock($db), [$r['party_id']]);
		return ifset($r, 'contact_id') === null ? !$contacts : count($contacts) === 1 && (int)$contacts[0]['id'] === (int)$r['contact_id'] && hash_equals($r['contact_sha256'], $this->hash($contacts[0]));
	}
	/** @return array{removed_party:bool} */
	public function undo(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$db->lock('saldi_adresser_' . ($item['payload']['ledger'] === 'customer' ? 'D' : 'K'));
		$this->freezeReferences($db, ['ordrer','adresser','ansatte','openpost','kassekladde','transaktioner','vare_lev','brugere','delivery_addresses','kontakt_emails']);
		$this->undoCheck = true;
		try {
			if (!$this->canUndo($ctx, $db, $item)) {
				throw new SaldiRuntimeError('CONFLICT', 'Party changed or referenced', ['reason' => 'undo_changed']);
			}
			if (ifset($item, ['result', 'contact_id']) !== null) {
				$db->exec('DELETE FROM ansatte WHERE id = ? AND konto_id = ?', [$item['result']['contact_id'], $item['result']['party_id']]);
			}
			return ['removed_party' => $db->exec('DELETE FROM adresser WHERE id = ?', [$item['result']['party_id']]) === 1];
		} finally {
			$this->undoCheck = false;
		}
	}
	/** @return array{columns:list<string>,rows:list<array>,fields:array} */
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$labels = ['ledger' => [6045, 'Kartotek'], 'name' => [138, 'Navn'], 'cvr' => [48, 'CVR-nr.'], 'ean' => [379, 'EAN-nr.'], 'address' => [140, 'Adresse'], 'zip' => [36, 'Postnr.'], 'city' => [46, 'By'], 'country' => [47, 'Land'], 'email' => [52, 'E-mail'], 'phone' => [37, 'Telefon'], 'contact' => [398, 'Kontakt'], 'payment_terms' => [368, 'Betalingsbetingelse'], 'group' => [63, 'Gruppe']];
		$rows = [];
		foreach ($item['payload'] as $field => $value) {
			[$id, $fallback] = $labels[$field];
			if ($field === 'payment_terms') {
				$value = ['netto' => 'Netto', 'lobende_maaned' => 'Lb. md.', 'kontant' => 'Kontant'][$value['type']] . ' ' . $value['days'];
			} elseif ($field === 'ledger') {
				$value = $this->text($ctx, $db, $value === 'customer' ? 5902 : 5903);
			}
			$rows[] = [$this->text($ctx, $db, $id, $fallback), (string)$value];
		}
		return ['columns' => [$this->text($ctx, $db, 5924), $this->text($ctx, $db, 5925)], 'rows' => $rows, 'fields' => []];
	}
}
