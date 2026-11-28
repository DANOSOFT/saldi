<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/settings/SettingsService.php --- ver 5.1.1 --- 2026.11.20 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260929 SS Settings redesign phase 4a (spec §7.2): one service that reads and writes a setting through its registry definition.
//             The registry points at the EXISTING storage (grupper box / settings row), so every current reader keeps working.
// 20261005 SS G1.1: the company address row is created by the first saved field when a ledger has none.
// 20261005 SS 4c: auditRow() for master-data rows; the history includes row events.
// 20261005 SS G7.1: dbrow storage takes a row filter ('where'), grupper storage an optional 'kode' (ANSAT extra fields keep two rows per employee with the same kodenr).
// 20261004 SS G10.5: storage 'dbrow' = a column of any table's row (table_pages), scope 'row' = its id.
// 20261004 SS G10.1: a joined list ('join' + 'index') may also live in a settings row (postEachSale); scope 'pos' takes its id from the definition like 'group' and 'row'.
// 20261004 SS G10 (risk review R2): grupper rows kept per fiscal year ('fiscal') are read from the current year and written to every year, as before; a missing row is created for the current year.
// 20261004 SS G4.3: storage 'grupper_row' (one column of one grupper row, the row id is the scope; price lists).
// 20261004 SS G2.6: storage 'virtual' (a value derived from several fields, includes/settings/virtualStorage.php).
// 20261004 SS Audit rows store the raw user name (the global from online.php is already escaped).
// 20261003 SS G3.4 Reminders: storage 'formularer' (the GEBYR row of a reminder form per language: xb fee item, yb interest item, str rate), kept where debitor/ny_rykker.php and includes/formfunk.php read it (spec R20).
// 20261003 SS G6.3 E-mail: storage 'adresser' (a felt_ column of the company's own address row) and scope 'group' (settings.group_id, the form language of a sender address).
// 20261002 SS Phase 4b batch 2: lastChanged() for the 'sat <date>' note next to a write-only secret.
// 20261002 SS Settings changes in audit_log carry objekt_type 'indstilling' and the key as objekt_id (settings redesign §11.2).
// 20261018 SS Review 2 (PR #803): sqlValue()/columnIsNumeric(): an empty value goes into a number column as NULL.
// 20261019 SS Review 2 (PR #803): a lone value in a tab-joined box is its first part only (DIV/3 box13); kode '0' also matches NULL/empty kode rows.
// 20261103 CL/ASR QA: historyMasked() is the one rule for settings whose history keeps no values (secrets and the employees' CPR, bank and salary): the audit row, the history views and Gendan all use it, so a masked row can never be restored as ''.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
// 20261119 CL/ASR An empty straksbogfør box (DIV/3 box5 '', NULL or no row) splits into two empty parts, so switching on only sales stores 'on;' and not a lone 'on' that every reader takes as purchases on too.
// 20261119 CL/ASR A save of one part of a composite ('join') reads the stored row again with a row lock (lockForSave(), select ... for update) and merges its part into that, so a save of the other part at the same time no longer puts it back to what this request read when it started (p1-settings-posting-F1).
// 20261120 CL/ASR A stored value without a row is created under a named lock for that row (PostgreSQL pg_advisory_lock before the save's transaction, or pg_advisory_xact_lock inside it; MySQL and MariaDB GET_LOCK through saldiNamedLockWait()), which the save that creates the row holds until it has committed (lockCreationBeforeSave(), lockForSave(), releaseLocks()), so two first saves at the same time create one row and both parts are kept ('on;on', not ';on' and 'on;' in two rows) (p1-settings-posting-F1r3). A setting of its own in a grupper row (DIV/3 box4 Hurtigfakturering, box9 negative stock) whose row the request did not find is saved the same way, so its first save at the same time as a save of Omgående bogføring writes into the row the other save created and inserts no second DIV/3 row (fix round 7, review round 1). The lock of a row the request read as missing is taken before the transaction begins, so the transaction sees the other save's row also under repeatable read, and a save that waits for it holds no row lock. A locked read or a named lock that fails is reported by lockForSave() and lockFailed(), no composite or grupper setting is written until releaseLocks() and saveRaw() rolls back its own transaction, so a failed read on MySQL or MariaDB, where the transaction goes on, is never taken for a missing row and a second row inserted.
include_once(__DIR__ . '/../../systemdata/settingsRegistry.php');

class SettingsService
{
	/** @var array<string, array<string, mixed>|false> grupper rows, keyed "art|kodenr" */
	private static $grupper = array();
	/** @var array<string, array<int, array<string, mixed>>> settings rows, keyed "var_grp|var_name" */
	private static $settings = array();
	/** @var array<string, mixed>|false|null the company's own address row (art S), for storage 'adresser' */
	private static $company = null;
	/** @var array<int, array<string, mixed>>|null the GEBYR rows of the reminder forms, for storage 'formularer' */
	private static $fees = null;
	/** @var array<string, array<int, array<string, mixed>>> grupper rows by art and id, for storage 'grupper_row' */
	private static $rows = array();
	private static $dbrows = array();
	/** @var bool|null */
	private static $auditColumns = null;
	private static $auditObjekt = false;
	/** @var array<string, true> named locks of the session (MySQL/MariaDB GET_LOCK, PostgreSQL pg_advisory_lock) this request holds for creating a missing row, until releaseLocks() */
	private static $namedLocks = array();
	/** @var bool a locked read or a named lock failed since the last releaseLocks(): no composite or grupper setting is written */
	private static $lockFailed = false;

	// ------------------------------------------------------------ definitions

	public static function definition(string $key): ?array
	{
		$all = getSettingDefinitions();
		if (!isset($all[$key])) {
			return null;
		}
		return $all[$key] + array('key' => $key);
	}

	/**
	 * Storage of a definition as named parts. Registry form (spec §7.1):
	 *   ['grupper', art, kodenr, box, encoding]   or   ['settings', var_grp, var_name, encoding]
	 * plus optional 'join' (separator of a composite box, R7) and 'index' (part of it).
	 *
	 * @return array<string, mixed>
	 */
	public static function storage(array $def): array
	{
		$s = $def['storage'];
		$out = array('join' => isset($s['join']) ? $s['join'] : null, 'index' => isset($s['index']) ? (int) $s['index'] : 0, 'list' => !empty($s['list']));
		if ($s[0] === 'grupper') {
			return $out + array('table' => 'grupper', 'art' => (string) $s[1], 'kodenr' => (string) $s[2], 'box' => (string) $s[3], 'encoding' => isset($s[4]) ? $s[4] : 'raw', 'fiscal' => !empty($s['fiscal']),
				'kode' => isset($s['kode']) ? (string) $s['kode'] : null);
		}
		if ($s[0] === 'adresser') {
			return $out + array('table' => 'adresser', 'column' => (string) $s[1], 'encoding' => isset($s[2]) ? $s[2] : 'raw');
		}
		if ($s[0] === 'grupper_row') {
			return $out + array('table' => 'grupper_row', 'art' => (string) $s[1], 'column' => (string) $s[2], 'encoding' => isset($s[3]) ? $s[3] : 'raw');
		}
		if ($s[0] === 'virtual') {
			return $out + array('table' => 'virtual', 'name' => (string) $s[1], 'encoding' => 'raw');
		}
		if ($s[0] === 'dbrow') {
			return $out + array('table' => 'dbrow', 'dbtable' => (string) $s[1], 'column' => (string) $s[2], 'encoding' => isset($s[3]) ? $s[3] : 'raw', 'where' => isset($s['where']) ? (string) $s['where'] : '');
		}
		if ($s[0] === 'formularer') {
			return $out + array('table' => 'formularer', 'formular' => (int) $s[1], 'column' => (string) $s[2], 'encoding' => isset($s[3]) ? $s[3] : 'raw');
		}
		return $out + array('table' => 'settings', 'var_grp' => $s[1], 'var_name' => (string) $s[2], 'encoding' => isset($s[3]) ? $s[3] : 'raw');
	}

	/**
	 * Cache key of a grupper storage: art|kodenr, plus the kode when the storage names one (two rows share art and kodenr).
	 */
	private static function grKey(array $st): string
	{
		return $st['art'] . '|' . $st['kodenr'] . ($st['kode'] !== null ? '|k' . $st['kode'] : '');
	}

	/**
	 * Row filter on the kode a grupper storage names; an older row with a NULL or empty kode counts as kode '0'.
	 */
	private static function kodeWhere(array $st): string
	{
		if ($st['kode'] === null) {
			return '';
		}
		if ($st['kode'] === '0') {
			return " and (kode = '0' or kode is null or kode = '')";
		}
		return " and kode = '" . db_escape_string($st['kode']) . "'";
	}

	/**
	 * Cache key of a dbrow storage: the table, plus its row filter when it has one.
	 */
	private static function dbKey(array $st): string
	{
		return $st['dbtable'] . ($st['where'] !== '' ? '|' . md5($st['where']) : '');
	}

	// ------------------------------------------------------------ reading

	/**
	 * Load the storage rows of several keys with one query per storage table.
	 *
	 * @param array<int, string> $keys
	 */
	public static function preload(array $keys): void
	{
		$gr = array();
		$names = array();
		foreach ($keys as $key) {
			$def = self::definition($key);
			if (!$def || empty($def['storage'])) {
				continue;
			}
			$st = self::storage($def);
			if ($st['table'] === 'grupper') {
				$gr[self::grKey($st)] = "(art = '" . db_escape_string($st['art']) . "' and kodenr = '" . db_escape_string($st['kodenr']) . "'" . self::kodeWhere($st) . ")";
			} elseif ($st['table'] === 'adresser') {
				if (self::$company === null) {
					$r = db_fetch_array(db_select("select * from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__));
					self::$company = $r ? $r : false;
				}
			} elseif ($st['table'] === 'formularer') {
				if (self::$fees === null) {
					self::$fees = array();
					$q = db_select("select id, formular, sprog, xb, yb, str from formularer where beskrivelse = 'GEBYR' and art = 2 order by id", __FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						self::$fees[] = $r;
					}
				}
			} elseif ($st['table'] === 'grupper_row') {
				if (!isset(self::$rows[$st['art']])) {
					self::$rows[$st['art']] = array();
					$q = db_select("select * from grupper where art = '" . db_escape_string($st['art']) . "' order by id", __FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						self::$rows[$st['art']][(int) $r['id']] = $r;
					}
				}
			} elseif ($st['table'] === 'dbrow') {
				$dk = self::dbKey($st);
				if (!isset(self::$dbrows[$dk])) {
					self::$dbrows[$dk] = array();
					$q = db_select("select * from " . $st['dbtable'] . ($st['where'] !== '' ? " where " . $st['where'] : '') . " order by id", __FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						self::$dbrows[$dk][(int) $r['id']] = $r;
					}
				}
			} elseif ($st['table'] === 'settings') {
				$names[$st['var_name']] = "'" . db_escape_string($st['var_name']) . "'";
			}
		}
		$gr = array_diff_key($gr, self::$grupper);
		if ($gr) {
			foreach (array_keys($gr) as $k) {
				self::$grupper[$k] = false;
			}
			// The current fiscal year's row first, for arts that keep a row per year (POS, OreDif).
			global $regnaar;
			$q = db_select("select * from grupper where " . implode(' or ', $gr) . " order by (coalesce(fiscal_year, 0) = " . (int) $regnaar . ") desc, id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$k = $r['art'] . '|' . $r['kodenr'];
				if (isset(self::$grupper[$k]) && self::$grupper[$k] === false) {
					self::$grupper[$k] = $r;
				}
				$kk = $k . '|k' . ((string) $r['kode'] === '' ? '0' : $r['kode']);
				if (isset(self::$grupper[$kk]) && self::$grupper[$kk] === false) {
					self::$grupper[$kk] = $r;
				}
			}
		}
		if ($names) {
			$q = db_select("select id, var_grp, var_name, var_value, user_id, pos_id, group_id from settings where var_name in (" . implode(',', $names) . ") order by id", __FILE__ . " linje " . __LINE__);
			$loaded = array();
			while ($r = db_fetch_array($q)) {
				$loaded[(string) $r['var_name']][] = $r;
			}
			foreach (array_keys($names) as $name) {
				self::$settings[$name] = isset($loaded[$name]) ? $loaded[$name] : array();
			}
		}
	}

	/**
	 * The stored string of a setting, exactly as the current writer left it ('' when unset).
	 */
	public static function raw(string $key, $scopeId = null): string
	{
		$def = self::definition($key);
		if (!$def || empty($def['storage'])) {
			return '';
		}
		$st = self::storage($def);
		$scopeId = self::scopeId($def, $scopeId);
		if ($st['table'] === 'grupper') {
			$ck = self::grKey($st);
			if (!array_key_exists($ck, self::$grupper)) {
				self::preload(array($key));
			}
			$row = self::$grupper[$ck];
			$value = ($row && isset($row[$st['box']])) ? (string) $row[$st['box']] : '';
		} elseif ($st['table'] === 'adresser') {
			if (self::$company === null) {
				self::preload(array($key));
			}
			$value = (self::$company && isset(self::$company[$st['column']])) ? (string) self::$company[$st['column']] : '';
		} elseif ($st['table'] === 'formularer') {
			$row = self::feeRow($key, $st, $scopeId);
			$value = $row ? (string) $row[$st['column']] : '';
		} elseif ($st['table'] === 'virtual') {
			include_once(__DIR__ . '/virtualStorage.php');
			$value = settings_virtual_get($st['name']);
		} elseif ($st['table'] === 'grupper_row') {
			if (!isset(self::$rows[$st['art']])) {
				self::preload(array($key));
			}
			$row = isset(self::$rows[$st['art']][(int) $scopeId]) ? self::$rows[$st['art']][(int) $scopeId] : null;
			$value = ($row && isset($row[$st['column']])) ? (string) $row[$st['column']] : '';
		} elseif ($st['table'] === 'dbrow') {
			if (!isset(self::$dbrows[self::dbKey($st)])) {
				self::preload(array($key));
			}
			$row = isset(self::$dbrows[self::dbKey($st)][(int) $scopeId]) ? self::$dbrows[self::dbKey($st)][(int) $scopeId] : null;
			$value = ($row && isset($row[$st['column']])) ? (string) $row[$st['column']] : '';
		} else {
			if (!array_key_exists($st['var_name'], self::$settings)) {
				self::preload(array($key));
			}
			$value = '';
			foreach (self::$settings[$st['var_name']] as $r) {
				if (self::rowInScope($r, $def, $st, $scopeId)) {
					$value = (string) $r['var_value'];
					break;
				}
			}
		}
		if ($st['join'] !== null) {
			$parts = self::splitComposite($value, $st['join'], $st['list']);
			$value = isset($parts[$st['index']]) ? $parts[$st['index']] : '';
		}
		return $value;
	}

	/**
	 * Value of a setting as a PHP type (bool / int / string); the registry default when unset.
	 *
	 * @return mixed
	 */
	public static function get(string $key, $scopeId = null)
	{
		$def = self::definition($key);
		if (!$def) {
			return null;
		}
		$raw = self::raw($key, $scopeId);
		if ($raw === '' && !self::exists($key, $scopeId)) {
			return isset($def['default']) ? $def['default'] : null;
		}
		return self::decode($def, $raw);
	}

	/**
	 * True when a storage row for the key exists (an empty stored value still counts).
	 */
	public static function exists(string $key, $scopeId = null): bool
	{
		$def = self::definition($key);
		if (!$def || empty($def['storage'])) {
			return false;
		}
		$st = self::storage($def);
		$scopeId = self::scopeId($def, $scopeId);
		self::raw($key, $scopeId);
		if ($st['table'] === 'grupper') {
			return (bool) self::$grupper[self::grKey($st)];
		}
		if ($st['table'] === 'adresser') {
			return (bool) self::$company;
		}
		if ($st['table'] === 'formularer') {
			return (bool) self::feeRow($key, $st, $scopeId);
		}
		if ($st['table'] === 'virtual') {
			return true;
		}
		if ($st['table'] === 'grupper_row') {
			return isset(self::$rows[$st['art']][(int) $scopeId]);
		}
		if ($st['table'] === 'dbrow') {
			return isset(self::$dbrows[self::dbKey($st)][(int) $scopeId]);
		}
		foreach (self::$settings[$st['var_name']] as $r) {
			if (self::rowInScope($r, $def, $st, $scopeId)) {
				return true;
			}
		}
		return false;
	}

	/** @return mixed */
	public static function decode(array $def, string $raw)
	{
		$st = self::storage($def);
		switch ($def['type']) {
			case 'bool':
				if ($st['encoding'] === 'oneZero') {
					return $raw === '1';
				}
				return trim($raw) === 'on';
			case 'int':
				return (int) $raw;
			default:
				return $raw;
		}
	}

	public static function encode(array $def, $value): string
	{
		$st = self::storage($def);
		switch ($def['type']) {
			case 'bool':
				$on = ($value === true || $value === 1 || $value === '1' || $value === 'on');
				if ($st['encoding'] === 'oneZero') {
					return $on ? '1' : '0';
				}
				if ($st['encoding'] === 'onOff') {
					return $on ? 'on' : 'off';
				}
				return $on ? 'on' : '';
			case 'int':
				return (string) (int) $value;
			default:
				return (string) $value;
		}
	}

	// ------------------------------------------------------------ writing

	/**
	 * Save a typed value. Returns true when the stored value changed.
	 */
	/**
	 * A value for SQL: quoted text, or NULL when it is empty and the column is a number column (review 2, PR #803).
	 * PostgreSQL refuses '' in an integer or numeric column; text columns keep '' because old readers compare with ''.
	 */
	public static function sqlValue(string $table, string $column, string $raw): string
	{
		if ($raw === '' && self::columnIsNumeric($table, $column)) {
			return 'NULL';
		}
		return "'" . db_escape_string($raw) . "'";
	}

	/**
	 * Whether a column is a number column in this ledger's database. Looked up once per column and request; the type
	 * can differ between older and newer ledgers (grupper.kodenr).
	 */
	public static function columnIsNumeric(string $table, string $column): bool
	{
		static $cache = array();
		$k = $table . '.' . $column;
		if (!isset($cache[$k])) {
			$r = db_fetch_array(db_select("select data_type as data_type from information_schema.columns where table_name = '" . db_escape_string($table) . "' and column_name = '" . db_escape_string($column) . "'" . self::schemaFilter(), __FILE__ . " linje " . __LINE__));
			$type = $r ? strtolower((string) $r['data_type']) : '';
			$cache[$k] = in_array($type, array('integer', 'smallint', 'bigint', 'numeric', 'decimal', 'real', 'double precision', 'int', 'tinyint', 'mediumint', 'float', 'double'), true);
		}
		return $cache[$k];
	}

	/**
	 * information_schema spans every database the user can see on MySQL: filter on this one (review, PR #803).
	 */
	public static function schemaFilter(): string
	{
		return function_exists('saldi_schema_filter') ? saldi_schema_filter() : '';
	}

	public static function save(string $key, $value, $scopeId = null): bool
	{
		$def = self::definition($key);
		if (!$def || empty($def['storage'])) {
			return false;
		}
		return self::saveRaw($key, self::encode($def, $value), $scopeId);
	}

	/**
	 * Read the stored rows of composite settings again with a row lock (select ... for update), in place of what preload()
	 * read when the request started. A composite ('join') is several settings in one stored value, such as DIV/3 box5
	 * "<sales>;<purchases>", a till list or a KDS colour "<minutes>-<colour>".
	 *
	 * Called inside the save's transaction, so the lock holds until it is committed: a save of another part of the same value
	 * waits for it, and a part another save committed since this request read the row is kept, because saveRaw() merges only
	 * the part it changes into what is read here (p1-settings-posting-F1). A caller can apply its lock rules to these values.
	 * The rows are locked in a fixed order (grupper by art and kodenr, then settings by name).
	 *
	 * A stored value without a row (an older ledger without grupper DIV/3) has no row to lock, so two first saves would each
	 * insert one ('on;' and ';on') and the readers take one of them (p1-settings-posting-F1r3). The row is created under its
	 * named lock (lockCreation()), which the save that creates it holds until it has committed, so the other save finds that
	 * row when it reads it here and writes its part into it. A grupper row holds other settings too (DIV/3 box4
	 * Hurtigfakturering, box9 negative stock), and the first save of any of them creates the row, so a setting of its own in
	 * a grupper row is read here as well when the request has not found the row; with the row there it is not read again.
	 * The caller takes the named locks of the rows it read as missing before its transaction (lockCreationBeforeSave());
	 * those this request does not hold yet are taken here, before any row is locked, and a row that is gone when it is read
	 * here is read again under its named lock. The caller ends the transaction and then calls releaseLocks().
	 *
	 * @param array<int, string> $keys
	 * @return bool false when a locked read or a named lock failed (lock wait timeout, deadlock): write() then refuses every
	 *   setting read here until releaseLocks(), so a failed read is never taken for a missing row, and the caller rolls back.
	 */
	public static function lockForSave(array $keys): bool
	{
		global $regnaar;
		list($grupper, $settings) = self::lockTargets($keys);
		$created = self::missingRowLocks($grupper, $settings);
		foreach (array_keys($created) as $lockName) {
			if (!self::lockCreation($lockName)) {
				self::$lockFailed = true;
				return false;
			}
		}
		foreach ($grupper as $ck => $where) {
			// The row preload() takes (this fiscal year's first, then the oldest). Every row of the pair is locked, as a save
			// writes them all (a ledger may hold duplicates, risk review R9).
			$qtxt = "select * from grupper where $where order by (coalesce(fiscal_year, 0) = " . (int) $regnaar . ") desc, id for update";
			$rows = self::lockedRows($qtxt);
			if ($rows === array() && !isset($created['grupper:' . $ck])) {
				$rows = self::lockCreation('grupper:' . $ck) ? self::lockedRows($qtxt) : null;
			}
			if ($rows === null) {
				self::$lockFailed = true;
				return false;
			}
			self::$grupper[$ck] = $rows ? $rows[0] : false;
		}
		foreach ($settings as $name => list($def, $st)) {
			$qtxt = "select id, var_grp, var_name, var_value, user_id, pos_id, group_id from settings where var_name = '" . db_escape_string($name) . "' order by id for update";
			$rows = self::lockedRows($qtxt);
			if ($rows !== null && !isset($created['settings:' . $name]) && !self::hasRowInScope($rows, $def, $st)) {
				$rows = self::lockCreation('settings:' . $name) ? self::lockedRows($qtxt) : null;
			}
			if ($rows === null) {
				self::$lockFailed = true;
				return false;
			}
			self::$settings[$name] = $rows;
		}
		return true;
	}

	/**
	 * Takes, before the save's transaction begins, the named locks of the rows lockForSave() will read that this request
	 * read as missing (what it has not read yet is read first, outside a transaction). A save that waits for one holds no
	 * row lock and has no snapshot yet: once it has the lock, the save that created the row has committed, and the
	 * transaction sees that row also under repeatable read, where a snapshot taken before the wait would not. On MySQL and
	 * MariaDB the wait for GET_LOCK() is then never part of a deadlock InnoDB cannot see. The caller calls releaseLocks()
	 * after its commit or rollback, also when this returns false (the database refused a lock, or another save held it for
	 * 2 minutes on MySQL or MariaDB). Inside a transaction it does nothing: lockForSave() takes the locks there.
	 *
	 * @param array<int, string> $keys
	 */
	public static function lockCreationBeforeSave(array $keys): bool
	{
		if (!empty($GLOBALS['db_transaktion_depth'])) {
			return true;
		}
		$load = array();
		foreach ($keys as $key) {
			$def = self::definition($key);
			if (!$def || empty($def['storage'])) {
				continue;
			}
			$st = self::storage($def);
			if (($st['table'] === 'grupper' && !array_key_exists(self::grKey($st), self::$grupper))
				|| ($st['table'] === 'settings' && $st['join'] !== null && !array_key_exists($st['var_name'], self::$settings))) {
				$load[] = $key;
			}
		}
		if ($load) {
			self::preload($load);
		}
		list($grupper, $settings) = self::lockTargets($keys);
		foreach (array_keys(self::missingRowLocks($grupper, $settings)) as $lockName) {
			if (!self::lockCreation($lockName, true)) {
				self::$lockFailed = true;
				return false;
			}
		}
		return true;
	}

	/**
	 * The rows lockForSave() reads under a lock for these keys, in lock order: grupper rows by cache key (with their row
	 * filter) and settings composites by name. A setting of its own in a grupper row counts only while this request has not
	 * found its row (grupperRowKnown()): its save updates a row that is there, and creates one that is not.
	 *
	 * @param array<int, string> $keys
	 * @return array{0: array<string, string>, 1: array<string, array{0: array<string, mixed>, 1: array<string, mixed>}>}
	 */
	private static function lockTargets(array $keys): array
	{
		$grupper = array();
		$settings = array();
		foreach ($keys as $key) {
			$def = self::definition($key);
			if (!$def || empty($def['storage'])) {
				continue;
			}
			$st = self::storage($def);
			if ($st['table'] === 'grupper' && ($st['join'] !== null || !self::grupperRowKnown($st))) {
				$grupper[self::grKey($st)] = "art = '" . db_escape_string($st['art']) . "' and kodenr = '" . db_escape_string($st['kodenr']) . "'" . self::kodeWhere($st);
			} elseif ($st['table'] === 'settings' && $st['join'] !== null) {
				$settings[$st['var_name']] = array($def, $st);
			}
		}
		ksort($grupper);
		ksort($settings);
		return array($grupper, $settings);
	}

	/**
	 * The named locks, in name order, of the rows among lockTargets() that this request read and found missing.
	 *
	 * @param array<string, string> $grupper
	 * @param array<string, array{0: array<string, mixed>, 1: array<string, mixed>}> $settings
	 * @return array<string, true>
	 */
	private static function missingRowLocks(array $grupper, array $settings): array
	{
		$names = array();
		foreach (array_keys($grupper) as $ck) {
			if (array_key_exists($ck, self::$grupper) && self::$grupper[$ck] === false) {
				$names['grupper:' . $ck] = true;
			}
		}
		foreach ($settings as $name => list($def, $st)) {
			if (array_key_exists($name, self::$settings) && !self::hasRowInScope(self::$settings[$name], $def, $st)) {
				$names['settings:' . $name] = true;
			}
		}
		return $names;
	}

	/**
	 * Whether this request has read a grupper storage's row and found it (preload()).
	 */
	private static function grupperRowKnown(array $st): bool
	{
		$ck = self::grKey($st);
		return array_key_exists($ck, self::$grupper) && self::$grupper[$ck];
	}

	/**
	 * The rows a locking read returns, or null when the read failed. On PostgreSQL a failed statement aborts the transaction;
	 * on MySQL and MariaDB the transaction goes on (a lock wait timeout, 1205), so a failed read must not look like no rows.
	 *
	 * @return array<int, array<string, mixed>>|null
	 */
	private static function lockedRows(string $qtxt): ?array
	{
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		if ($q === false) {
			return null;
		}
		$rows = array();
		while ($r = db_fetch_array($q)) {
			$rows[] = $r;
		}
		return $rows;
	}

	/**
	 * Whether the settings rows of a composite include the row its writer updates (write() inserts one otherwise).
	 *
	 * @param array<int, array<string, mixed>> $rows
	 */
	private static function hasRowInScope(array $rows, array $def, array $st): bool
	{
		foreach ($rows as $r) {
			if (self::rowInScope($r, $def, $st, self::scopeId($def, null))) {
				return true;
			}
		}
		return false;
	}

	/**
	 * SQL of a named lock's name on MySQL and MariaDB. GET_LOCK() names are server-wide and at most 64 characters, so the
	 * database is part of the name, hashed: "saldi:settings:" and 32 hex digits.
	 */
	private static function mysqlLockName(string $name): string
	{
		return "concat('saldi:settings:', md5(concat(database(), ':', '" . db_escape_string($name) . "')))";
	}

	/**
	 * Takes the named lock under which a missing row of a stored value is created. The name is "grupper:<art>|<kodenr>" (with
	 * "|k<kode>" when the storage names a kode) or "settings:<var_name>".
	 *
	 * PostgreSQL: with $session, before the transaction, pg_advisory_lock(), which the session holds until releaseLocks();
	 * otherwise pg_advisory_xact_lock(), which the transaction holds until it is committed or rolled back. MySQL and
	 * MariaDB: GET_LOCK(), asked for in rounds of 30 seconds for at most 2 minutes (saldiNamedLockWait()); it belongs to the
	 * session, so the caller releases it after the commit (releaseLocks()), and at the latest the end of the request does.
	 * Locks the session holds are not asked for again.
	 *
	 * @return bool false when the lock was not taken (the database refused it, or another save held it for 2 minutes)
	 */
	private static function lockCreation(string $name, bool $session = false): bool
	{
		global $db_type;
		static $shutdown = false;
		if (isset(self::$namedLocks[$name])) {
			return true;
		}
		if ($db_type !== 'mysql' && $db_type !== 'mysqli') {
			$fn = $session ? 'pg_advisory_lock' : 'pg_advisory_xact_lock';
			if (db_select("select $fn(" . self::pgLockKey($name) . ")", __FILE__ . " linje " . __LINE__) === false) {
				return false;
			}
			if (!$session) {
				return true;
			}
		} else {
			include_once(__DIR__ . '/../stdFunc/namedLockWait.php');
			$outcome = saldiNamedLockWait(function ($qtxt) {
				$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
				$r = $q ? db_fetch_array($q) : null;
				return $r ? $r['locked'] : null;
			}, self::mysqlLockName($name));
			if ($outcome !== 'locked') {
				error_log("Saldi settings: the lock for creating $name was not taken ($outcome).");
				return false;
			}
		}
		if (!$shutdown) {
			// A persistent connection would keep a lock the request did not release.
			register_shutdown_function(array(self::class, 'releaseLocks'));
			$shutdown = true;
		}
		self::$namedLocks[$name] = true;
		return true;
	}

	/**
	 * The two keys of a named lock on PostgreSQL: one for the settings' locks, one for the row.
	 */
	private static function pgLockKey(string $name): string
	{
		return "hashtext('saldi:settings'), hashtext('" . db_escape_string($name) . "')";
	}

	/**
	 * Ends what lockCreationBeforeSave() and lockForSave() hold once the save's transaction is committed or rolled back:
	 * releases the session's named locks (pg_advisory_unlock(), MySQL and MariaDB release_lock(); a PostgreSQL lock taken
	 * inside the transaction ended with it) and forgets failed reads.
	 */
	public static function releaseLocks(): void
	{
		global $db_type;
		self::$lockFailed = false;
		$names = array_keys(self::$namedLocks);
		self::$namedLocks = array();
		foreach ($names as $name) {
			if ($db_type === 'mysql' || $db_type === 'mysqli') {
				db_select("select release_lock(" . self::mysqlLockName($name) . ")", __FILE__ . " linje " . __LINE__);
			} else {
				db_select("select pg_advisory_unlock(" . self::pgLockKey($name) . ")", __FILE__ . " linje " . __LINE__);
			}
		}
	}

	/**
	 * True when a locked read or a named lock of lockForSave() failed since the last releaseLocks(): the caller rolls back.
	 */
	public static function lockFailed(): bool
	{
		return self::$lockFailed;
	}

	/**
	 * Write a stored string as is (also used by "Gendan" in the change history).
	 *
	 * One part of a composite is merged into the value read again under a row lock (lockForSave()), not into the one this
	 * request read when it started. The lock holds until the caller's transaction ends; a caller without a transaction gets
	 * one around the read and the write, and takes the named locks of rows it reads as missing before it begins
	 * (lockCreationBeforeSave()). A setting of its own in a grupper row whose row this request did not find is written the
	 * same way, as its save creates the row. A caller with a transaction rolls back when lockFailed() says a read failed,
	 * and calls releaseLocks() after its commit or rollback.
	 */
	public static function saveRaw(string $key, string $raw, $scopeId = null, string $handling = 'setting.changed'): bool
	{
		$def = self::definition($key);
		if (!$def || empty($def['storage'])) {
			return false;
		}
		$st = self::storage($def);
		if ($st['join'] === null && ($st['table'] !== 'grupper' || self::exists($key))) {
			return self::write($def, $key, $raw, $scopeId, $handling);
		}
		if (!empty($GLOBALS['db_transaktion_depth'])) {
			// write() refuses a setting whose read failed here or before.
			self::lockForSave(array($key));
			return self::write($def, $key, $raw, $scopeId, $handling);
		}
		if (!self::lockCreationBeforeSave(array($key))) {
			self::releaseLocks();
			return false;
		}
		transaktion('begin');
		if (!self::lockForSave(array($key))) {
			transaktion('rollback');
			self::releaseLocks();
			return false;
		}
		$changed = self::write($def, $key, $raw, $scopeId, $handling);
		transaktion('commit');
		self::releaseLocks();
		return $changed;
	}

	/**
	 * saveRaw() after any composite, and a grupper row this request did not find, has been read again under its lock.
	 */
	private static function write(array $def, string $key, string $raw, $scopeId, string $handling): bool
	{
		$st = self::storage($def);
		if (self::$lockFailed && ($st['join'] !== null || $st['table'] === 'grupper')) {
			// A stored row that was not read under its lock: the cache may be stale or say there is no row, and an insert would
			// add a second row. The caller rolls back.
			return false;
		}
		$scope = isset($def['scope']) ? $def['scope'] : 'company';
		$scopeId = self::scopeId($def, $scopeId);
		if ($scope === 'user' && (int) $scopeId <= 0) {
			return false;
		}
		if ($scope === 'company') {
			$scopeId = null;
		}
		$old = self::raw($key, $scopeId);
		$had = self::exists($key, $scopeId);
		if ($had && $old === $raw) {
			return false;
		}
		if (!$had) {
			// No row yet: the readers use their default, so only a value that differs from it is stored.
			$default = array_key_exists('default', $def) ? ($def['type'] === 'bool' ? self::encode($def, (bool) $def['default']) : (string) $def['default']) : '';
			if ($raw === $default || ($raw === '0' && $default === '')) {
				return false;
			}
		}
		$esc = db_escape_string($raw);
		if ($st['table'] === 'grupper') {
			$ck = self::grKey($st);
			$row = self::$grupper[$ck];
			$stored = $esc;
			if ($st['join'] !== null) {
				$parts = self::splitComposite($row ? (string) $row[$st['box']] : '', $st['join'], $st['list']);
				$parts[$st['index']] = $raw;
				for ($i = 0; $i <= max(array_keys($parts)); $i++) {
					if (!isset($parts[$i])) {
						$parts[$i] = '';
					}
				}
				ksort($parts);
				$stored = db_escape_string(implode($st['join'], $parts));
			}
			$where = "art = '" . db_escape_string($st['art']) . "' and kodenr = '" . db_escape_string($st['kodenr']) . "'" . self::kodeWhere($st);
			if ($st['fiscal'] && $row && (int) $row['fiscal_year'] > 0) {
				// An art with a row per year (POS, OreDif) writes this year's row only (review, PR #803).
				$where .= " and fiscal_year = " . (int) $row['fiscal_year'];
			}
			if ($row) {
				// Every row of the art/kodenr pair: a ledger may hold duplicates (risk review R9).
				db_modify("update grupper set " . $st['box'] . " = '$stored' where $where", __FILE__ . " linje " . __LINE__);
			} else {
				$name = db_escape_string(isset($def['storage']['row_name']) ? (string) $def['storage']['row_name'] : 'Indstillinger');
				if ($st['fiscal']) {
					global $regnaar;
					db_modify("insert into grupper (beskrivelse, kodenr, art, kode, fiscal_year, " . $st['box'] . ") values ('$name', '" . db_escape_string($st['kodenr']) . "', '" . db_escape_string($st['art']) . "', '', " . (int) $regnaar . ", '$stored')", __FILE__ . " linje " . __LINE__);
				} else {
					db_modify("insert into grupper (beskrivelse, kodenr, art, " . ($st['kode'] !== null ? 'kode, ' : '') . $st['box'] . ") values ('$name', '" . db_escape_string($st['kodenr']) . "', '" . db_escape_string($st['art']) . "', " . ($st['kode'] !== null ? "'" . db_escape_string($st['kode']) . "', " : '') . "'$stored')", __FILE__ . " linje " . __LINE__);
				}
			}
			unset(self::$grupper[$ck]);
		} elseif ($st['table'] === 'adresser') {
			if (!self::$company) {
				// A new ledger has no company row yet (G1.1): the first saved field creates it.
				db_modify("insert into adresser (art, kontonr, " . $st['column'] . ") values ('S', '0', " . self::sqlValue('adresser', $st['column'], $raw) . ")", __FILE__ . " linje " . __LINE__);
			} else {
				db_modify("update adresser set " . $st['column'] . " = " . self::sqlValue('adresser', $st['column'], $raw) . " where id = " . (int) self::$company['id'], __FILE__ . " linje " . __LINE__);
			}
			self::$company = null;
		} elseif ($st['table'] === 'formularer') {
			$row = self::feeRow($key, $st, $scopeId);
			if ($row) {
				db_modify("update formularer set " . $st['column'] . " = " . self::sqlValue('formularer', $st['column'], $raw) . " where id = " . (int) $row['id'], __FILE__ . " linje " . __LINE__);
			} else {
				$lang = db_escape_string(self::formLanguageName($scopeId));
				db_modify("insert into formularer (beskrivelse, formular, art, " . $st['column'] . ", sprog) values ('GEBYR', " . (int) $st['formular'] . ", 2, " . self::sqlValue('formularer', $st['column'], $raw) . ", '$lang')", __FILE__ . " linje " . __LINE__);
			}
			self::$fees = null;
		} elseif ($st['table'] === 'virtual') {
			include_once(__DIR__ . '/virtualStorage.php');
			settings_virtual_set($st['name'], $raw);
			self::$grupper = array();
		} elseif ($st['table'] === 'grupper_row') {
			if ((int) $scopeId <= 0 || !isset(self::$rows[$st['art']][(int) $scopeId])) {
				return false;
			}
			db_modify("update grupper set " . $st['column'] . " = " . self::sqlValue('grupper', $st['column'], $raw) . " where id = " . (int) $scopeId . " and art = '" . db_escape_string($st['art']) . "'", __FILE__ . " linje " . __LINE__);
			unset(self::$rows[$st['art']]);
		} elseif ($st['table'] === 'dbrow') {
			if ((int) $scopeId <= 0 || !isset(self::$dbrows[self::dbKey($st)][(int) $scopeId])) {
				return false;
			}
			db_modify("update " . $st['dbtable'] . " set " . $st['column'] . " = " . self::sqlValue((string) $st['dbtable'], $st['column'], $raw) . " where id = " . (int) $scopeId, __FILE__ . " linje " . __LINE__);
			unset(self::$dbrows[self::dbKey($st)]);
		} else {
			$where = "var_name = '" . db_escape_string($st['var_name']) . "'";
			if ($st['var_grp'] !== null) {
				$where .= " and var_grp = '" . db_escape_string((string) $st['var_grp']) . "'";
			}
			if ($scope === 'user') {
				$where .= " and user_id = " . (int) $scopeId;
			} elseif ($scope === 'pos') {
				$where .= " and pos_id = " . (int) $scopeId;
			} elseif ($scope === 'group') {
				$where .= " and (user_id is null or user_id = 0) and coalesce(group_id, 0) = " . (int) $scopeId;
			} else {
				$where .= " and (user_id is null or user_id = 0)";
			}
			if ($st['join'] !== null) {
				// One part of a joined list: the other parts stay as they are.
				$whole = '';
				foreach (self::$settings[$st['var_name']] as $r) {
					if (self::rowInScope($r, $def, $st, $scopeId)) {
						$whole = (string) $r['var_value'];
						break;
					}
				}
				if ($whole === '' && !empty($def['storage']['seed'])) {
					// A list that does not exist yet starts from what the readers fall back to, so the other parts keep their meaning.
					include_once(__DIR__ . '/virtualStorage.php');
					$whole = settings_virtual_get('seed_' . $def['storage']['seed']);
				}
				$parts = self::splitComposite($whole, $st['join'], $st['list']);
				$parts[$st['index']] = $raw;
				for ($i = 0; $i <= max(array_keys($parts)); $i++) {
					if (!isset($parts[$i])) {
						$parts[$i] = '';
					}
				}
				ksort($parts);
				$esc = db_escape_string(implode($st['join'], $parts));
				$had = false;
				foreach (self::$settings[$st['var_name']] as $r) {
					if (self::rowInScope($r, $def, $st, $scopeId)) {
						$had = true;
						break;
					}
				}
			}
			if ($had) {
				db_modify("update settings set var_value = '$esc' where $where", __FILE__ . " linje " . __LINE__);
			} else {
				// Company scope is stored with user_id 0 (risk review R10).
				$userId = ($scope === 'user') ? (int) $scopeId : 0;
				$cols = "var_name, var_grp, var_value, var_description, user_id";
				$vals = "'" . db_escape_string($st['var_name']) . "', '" . db_escape_string((string) $st['var_grp']) . "', '$esc', '" . db_escape_string($key) . "', $userId";
				if ($scope === 'pos') {
					$cols .= ", pos_id";
					$vals .= ", " . (int) $scopeId;
				} elseif ($scope === 'group') {
					$cols .= ", group_id";
					$vals .= ", " . (int) $scopeId;
				}
				db_modify("insert into settings ($cols) values ($vals)", __FILE__ . " linje " . __LINE__);
			}
			unset(self::$settings[$st['var_name']]);
		}
		if (!isset($def['audit']) || $def['audit']) {
			self::audit($def, $old, $raw, $handling);
		}
		return true;
	}

	// ------------------------------------------------------------ modules

	/**
	 * Licence flag of an optional module (decision 9): settings system/<module>_licence,
	 * and while that key is unset, what the installation did before.
	 */
	public static function hasModule(string $module): bool
	{
		static $cache = array();
		if (isset($cache[$module])) {
			return $cache[$module];
		}
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'system' and var_name = '" . db_escape_string($module) . "_licence'", __FILE__ . " linje " . __LINE__));
		if ($r) {
			return $cache[$module] = ((string) $r['var_value'] === 'on');
		}
		if ($module === 'pos') {
			return $cache[$module] = file_exists(__DIR__ . '/../../debitor/pos_ordre.php');
		}
		if ($module === 'hosted') {
			// G1.4: Saldi's own servers keep the keys file next to the installation (read by includes/betweenUpdates.php).
			return $cache[$module] = file_exists(__DIR__ . '/../../../.ht_keys.txt');
		}
		if ($module === 'bank') {
			return $cache[$module] = function_exists('settings_feature_enabled') && settings_feature_enabled('bank');
		}
		return $cache[$module] = false;
	}

	// ------------------------------------------------------------ change history

	private static function auditColumns(): bool
	{
		if (self::$auditColumns === null) {
			self::$auditColumns = (bool) db_fetch_array(db_select("select column_name from information_schema.columns where table_name = 'audit_log' and column_name = 'setting_key'" . self::schemaFilter(), __FILE__ . " linje " . __LINE__));
			self::$auditObjekt = self::$auditColumns && (bool) db_fetch_array(db_select("select column_name from information_schema.columns where table_name = 'audit_log' and column_name = 'objekt_type'" . self::schemaFilter(), __FILE__ . " linje " . __LINE__));
		}
		return self::$auditColumns;
	}

	/** Settings history rows; 'setting.change' is the name used before the roles spec's event names. */
	const HISTORY_HANDLINGS = "'setting.changed', 'setting.reverted', 'setting.change', 'setting.row_created', 'setting.row_updated', 'setting.row_deactivated', 'setting.row_deleted'";

	/**
	 * True when the change history keeps no values for this setting (only "ændret"): secrets and the sensitive
	 * employee fields. Such rows are shown without values and can never be restored.
	 */
	public static function historyMasked(array $def): bool
	{
		return $def['type'] === 'secret' || (isset($def['permission']) && $def['permission'] === 'settings.organisation.sensitive');
	}

	private static function audit(array $def, string $old, string $new, string $handling): void
	{
		global $bruger_id, $brugernavn;
		$secret = self::historyMasked($def);
		$section = $def['group'] . '.' . $def['section'];
		// detaljer as the roles spec defines it: {before, after}, a secret only as "ændret" (settings redesign §8.12).
		$detaljer = json_encode($secret ? array('before' => 'ændret', 'after' => 'ændret') : array('before' => $old, 'after' => $new), JSON_UNESCAPED_UNICODE);
		if ($detaljer === false) {
			$detaljer = json_encode($secret ? array('before' => 'ændret', 'after' => 'ændret') : array('before' => mb_convert_encoding($old, 'UTF-8', 'ISO-8859-1'), 'after' => mb_convert_encoding($new, 'UTF-8', 'ISO-8859-1')), JSON_UNESCAPED_UNICODE);
		}
		if (!self::auditColumns()) {
			if (function_exists('audit_log')) {
				audit_log($handling, (string) $detaljer, 'indstilling', $def['key']);
			}
			return;
		}
		$ip = db_escape_string(isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : '');
		// objekt_type 'indstilling' is what the roles spec's audit log groups settings changes by (settings redesign §11.2).
		$objekt = self::$auditObjekt ? ", objekt_type, objekt_id, kilde" : "";
		$objektValues = self::$auditObjekt ? ", 'indstilling', '" . db_escape_string(substr($def['key'], 0, 60)) . "', 'ui'" : "";
		$qtxt = "insert into audit_log (bruger_id, brugernavn, handling, detaljer, ip, setting_key, section, old_value, new_value$objekt) values (";
		$qtxt .= (int) $bruger_id . ", '" . db_escape_string(isset($GLOBALS['brugernavn_raw']) ? (string) $GLOBALS['brugernavn_raw'] : (string) $brugernavn) . "', '" . db_escape_string($handling) . "', '" . db_escape_string((string) $detaljer) . "', '$ip', ";
		$qtxt .= "'" . db_escape_string($def['key']) . "', '" . db_escape_string($section) . "', ";
		$qtxt .= "'" . db_escape_string($secret ? '' : $old) . "', '" . db_escape_string($secret ? '' : $new) . "'$objektValues)";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}

	/**
	 * An audit row for a master-data row (settings redesign §8.12): the key names the table and column, the objekt the
	 * row ("section.table#code"); old/new as stored, detaljer {before, after}.
	 */
	public static function auditRow(string $section, string $key, string $objektId, string $old, string $new, string $handling): void
	{
		global $bruger_id, $brugernavn;
		$detaljer = json_encode(array('before' => $old, 'after' => $new), JSON_UNESCAPED_UNICODE);
		if ($detaljer === false) {
			$detaljer = json_encode(array('before' => mb_convert_encoding($old, 'UTF-8', 'ISO-8859-1'), 'after' => mb_convert_encoding($new, 'UTF-8', 'ISO-8859-1')), JSON_UNESCAPED_UNICODE);
		}
		if (!self::auditColumns()) {
			if (function_exists('audit_log')) {
				audit_log($handling, (string) $detaljer, 'indstilling', $objektId);
			}
			return;
		}
		$ip = db_escape_string(isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : '');
		$objekt = self::$auditObjekt ? ", objekt_type, objekt_id, kilde" : "";
		$objektValues = self::$auditObjekt ? ", 'indstilling', '" . db_escape_string(substr($objektId, 0, 60)) . "', 'ui'" : "";
		$qtxt = "insert into audit_log (bruger_id, brugernavn, handling, detaljer, ip, setting_key, section, old_value, new_value$objekt) values (";
		$qtxt .= (int) $bruger_id . ", '" . db_escape_string(isset($GLOBALS['brugernavn_raw']) ? (string) $GLOBALS['brugernavn_raw'] : (string) $brugernavn) . "', '" . db_escape_string($handling) . "', '" . db_escape_string((string) $detaljer) . "', '$ip', ";
		$qtxt .= "'" . db_escape_string($key) . "', '" . db_escape_string($section) . "', '" . db_escape_string($old) . "', '" . db_escape_string($new) . "'$objektValues)";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}

	/**
	 * Audit version of a section: the id of its newest change (0 when none). The form carries
	 * the version it was rendered from, so a save on top of someone else's save is detected.
	 */
	public static function version(string $section): int
	{
		if (!self::auditColumns()) {
			return 0;
		}
		$r = db_fetch_array(db_select("select max(id) as id from audit_log where section = '" . db_escape_string($section) . "'", __FILE__ . " linje " . __LINE__));
		return $r ? (int) $r['id'] : 0;
	}

	/**
	 * @return array<int, array<string, mixed>> newest first
	 */
	public static function history(string $section, int $limit = 20, int $afterId = 0): array
	{
		$rows = array();
		if (!self::auditColumns()) {
			return $rows;
		}
		$qtxt = "select id, bruger_id, brugernavn, tidspunkt, setting_key, old_value, new_value, handling from audit_log ";
		$qtxt .= "where section = '" . db_escape_string($section) . "' and handling in (" . self::HISTORY_HANDLINGS . ") and id > " . (int) $afterId . " order by id desc limit " . (int) $limit;
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$rows[] = $r;
		}
		return $rows;
	}

	/**
	 * When each setting of a section was last changed through the registry (key => timestamp), for the
	 * "sat 12/9" note next to a secret.
	 *
	 * @return array<string, string>
	 */
	public static function lastChanged(string $section): array
	{
		if (!self::auditColumns()) {
			return array();
		}
		$out = array();
		$q = db_select("select setting_key, max(tidspunkt) as t from audit_log where section = '" . db_escape_string($section) . "' group by setting_key", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$out[(string) $r['setting_key']] = (string) $r['t'];
		}
		return $out;
	}

	public static function historyEntry(int $id): ?array
	{
		if (!self::auditColumns()) {
			return null;
		}
		$r = db_fetch_array(db_select("select id, brugernavn, tidspunkt, setting_key, section, old_value, new_value from audit_log where id = " . (int) $id . " and handling in (" . self::HISTORY_HANDLINGS . ")", __FILE__ . " linje " . __LINE__));
		return $r ?: null;
	}

	// ------------------------------------------------------------ helpers

	/**
	 * @return array<int, string>
	 */
	private static function splitComposite(string $value, string $sep, bool $list = false): array
	{
		if ($value === '') {
			// An empty straksbogfør box (';') is both parts off. Writing one part must give 'on;' or ';on', never a lone 'on'
			// that the readers take as sales and purchases both on.
			return $sep === ';' ? array(0 => '', 1 => '') : array();
		}
		if ($list && strpos($value, $sep) === false) {
			// A per-till list with one entry belongs to till 1 only.
			return array(0 => $value);
		}
		if (strpos($value, $sep) === false) {
			// A lone value is the first part (legacy explode), except the straksbogfør box (';') that sales and purchase both read whole.
			return $sep === ';' ? array(0 => $value, 1 => $value) : array(0 => $value);
		}
		return explode($sep, $value);
	}

	private static function rowInScope(array $row, array $def, array $st, $scopeId): bool
	{
		if ($st['var_grp'] !== null && (string) $row['var_grp'] !== (string) $st['var_grp']) {
			return false;
		}
		$scope = isset($def['scope']) ? $def['scope'] : 'company';
		if ($scope === 'user') {
			return (int) $row['user_id'] === (int) $scopeId && (int) $scopeId > 0;
		}
		if ($scope === 'pos') {
			return (int) $row['pos_id'] === (int) $scopeId;
		}
		if ($scope === 'group') {
			return (int) $row['group_id'] === (int) $scopeId && (int) $row['user_id'] === 0;
		}
		return (int) $row['user_id'] === 0;
	}

	public static function reset(): void
	{
		self::$dbrows = array();
		self::$grupper = array();
		self::$settings = array();
		self::$company = null;
		self::$fees = null;
		self::$rows = array();
	}

	/**
	 * The GEBYR row of a reminder form in one language (null when none). The forms page writes the language name as
	 * typed, the readers compare lower-case, so this does too.
	 */
	private static function feeRow(string $key, array $st, $scopeId): ?array
	{
		if (self::$fees === null) {
			self::preload(array($key));
		}
		$lang = mb_strtolower(self::formLanguageName($scopeId));
		foreach (self::$fees as $r) {
			if ((int) $r['formular'] === (int) $st['formular'] && mb_strtolower(trim((string) $r['sprog'])) === $lang) {
				return $r;
			}
		}
		return null;
	}

	private static function formLanguageName($langId): string
	{
		return function_exists('settings_form_language_name') ? settings_form_language_name((int) $langId) : 'Dansk';
	}

	/**
	 * The scope id to use: the one given, or the definition's own for a 'group' scoped key (a sender address
	 * carries its form language in the definition).
	 */
	private static function scopeId(array $def, $scopeId)
	{
		// A user-scoped field normally gets the current user from its caller; one that names its user (the employee's
		// linked user, G7.1) carries it as scope_id.
		if ($scopeId === null && isset($def['scope']) && in_array($def['scope'], array('group', 'row', 'pos', 'user'), true) && isset($def['scope_id'])) {
			return (int) $def['scope_id'];
		}
		return $scopeId;
	}
}

if (!function_exists('settings_registry_note')) {
	/**
	 * Compatibility layer (spec §7.2): get_settings_value() reports every (var_grp, var_name) it is
	 * asked for; pairs without a registry definition are written once per session to
	 * temp/<db>/settings_not_in_registry.log, so the inventory becomes complete over time.
	 */
	function settings_registry_note($varGrp, $varName): void
	{
		global $db;
		static $known = null;
		if ($known === null) {
			$known = array();
			foreach (getSettingDefinitions() as $def) {
				if (!empty($def['storage']) && $def['storage'][0] === 'settings') {
					$known[(string) $def['storage'][1] . '|' . (string) $def['storage'][2]] = true;
					$known['|' . (string) $def['storage'][2]] = true;
				}
			}
		}
		$pair = (string) $varGrp . '|' . (string) $varName;
		if (isset($known[$pair]) || isset($_SESSION['settings_noted'][$pair]) || empty($db)) {
			return;
		}
		$_SESSION['settings_noted'][$pair] = 1;
		$dir = __DIR__ . '/../../temp/' . $db;
		if (is_dir($dir)) {
			@file_put_contents($dir . '/settings_not_in_registry.log', date('Y-m-d H:i:s') . "\t" . $pair . "\n", FILE_APPEND);
		}
	}
}

if (!function_exists('setting')) {
	/** @return mixed */
	function setting(string $key, $scopeId = null)
	{
		return SettingsService::get($key, $scopeId);
	}
	function setting_save(string $key, $value, $scopeId = null): bool
	{
		return SettingsService::save($key, $value, $scopeId);
	}
	function has_module(string $module): bool
	{
		return SettingsService::hasModule($module);
	}
}
