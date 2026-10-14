<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/settings/SettingsService.php --- lap 5.0.0 --- 2026.09.29 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260929 Sawaneh Settings redesign phase 4a (spec §7.2): one service that reads and writes a
//                  setting through its registry definition. The registry points at the EXISTING
//                  storage (grupper box / settings row), so every current reader keeps working.
// 20261005 Sawaneh G1.1: the company address row is created by the first saved field when a ledger has none.
// 20261005 Sawaneh 4c: auditRow() for master-data rows; the history includes row events.
// 20261005 Sawaneh G7.1: dbrow storage takes a row filter ('where'), grupper storage an optional 'kode' (ANSAT extra
//                  fields keep two rows per employee with the same kodenr).
// 20261004 Sawaneh G10.5: storage 'dbrow' = a column of any table's row (table_pages), scope 'row' = its id.
// 20261004 Sawaneh G10.1: a joined list ('join' + 'index') may also live in a settings row (postEachSale); scope 'pos' takes its
//                  id from the definition like 'group' and 'row'.
// 20261004 Sawaneh G10 (risk review R2): grupper rows kept per fiscal year ('fiscal') are read from the current year and
//                  written to every year, as before; a missing row is created for the current year.
// 20261004 Sawaneh G4.3: storage 'grupper_row' (one column of one grupper row, the row id is the scope; price lists).
// 20261004 Sawaneh G2.6: storage 'virtual' (a value derived from several fields, includes/settings/virtualStorage.php).
// 20261004 Sawaneh Audit rows store the raw user name (the global from online.php is already escaped).
// 20261003 Sawaneh G3.4 Reminders: storage 'formularer' (the GEBYR row of a reminder form per language: xb fee item, yb interest
//                  item, str rate), kept where debitor/ny_rykker.php and includes/formfunk.php read it (spec R20).
// 20261003 Sawaneh G6.3 E-mail: storage 'adresser' (a felt_ column of the company's own address row) and scope 'group'
//                  (settings.group_id, the form language of a sender address).
// 20261002 Sawaneh Phase 4b batch 2: lastChanged() for the 'sat <date>' note next to a write-only secret.
// 20261002 Sawaneh Settings changes in audit_log carry objekt_type 'indstilling' and the key as objekt_id (settings redesign §11.2).

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
				$gr[self::grKey($st)] = "(art = '" . db_escape_string($st['art']) . "' and kodenr = '" . db_escape_string($st['kodenr']) . "'" . ($st['kode'] !== null ? " and kode = '" . db_escape_string($st['kode']) . "'" : '') . ")";
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
				$kk = $k . '|k' . $r['kode'];
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
	public static function save(string $key, $value, $scopeId = null): bool
	{
		$def = self::definition($key);
		if (!$def || empty($def['storage'])) {
			return false;
		}
		return self::saveRaw($key, self::encode($def, $value), $scopeId);
	}

	/**
	 * Write a stored string as is (also used by "Gendan" in the change history).
	 */
	public static function saveRaw(string $key, string $raw, $scopeId = null, string $handling = 'setting.changed'): bool
	{
		$def = self::definition($key);
		if (!$def || empty($def['storage'])) {
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
		$st = self::storage($def);
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
			$where = "art = '" . db_escape_string($st['art']) . "' and kodenr = '" . db_escape_string($st['kodenr']) . "'" . ($st['kode'] !== null ? " and kode = '" . db_escape_string($st['kode']) . "'" : '');
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
				db_modify("insert into adresser (art, kontonr, " . $st['column'] . ") values ('S', '0', '$esc')", __FILE__ . " linje " . __LINE__);
			} else {
				db_modify("update adresser set " . $st['column'] . " = '$esc' where id = " . (int) self::$company['id'], __FILE__ . " linje " . __LINE__);
			}
			self::$company = null;
		} elseif ($st['table'] === 'formularer') {
			$row = self::feeRow($key, $st, $scopeId);
			if ($row) {
				db_modify("update formularer set " . $st['column'] . " = '$esc' where id = " . (int) $row['id'], __FILE__ . " linje " . __LINE__);
			} else {
				$lang = db_escape_string(self::formLanguageName($scopeId));
				db_modify("insert into formularer (beskrivelse, formular, art, " . $st['column'] . ", sprog) values ('GEBYR', " . (int) $st['formular'] . ", 2, '$esc', '$lang')", __FILE__ . " linje " . __LINE__);
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
			db_modify("update grupper set " . $st['column'] . " = '$esc' where id = " . (int) $scopeId . " and art = '" . db_escape_string($st['art']) . "'", __FILE__ . " linje " . __LINE__);
			unset(self::$rows[$st['art']]);
		} elseif ($st['table'] === 'dbrow') {
			if ((int) $scopeId <= 0 || !isset(self::$dbrows[self::dbKey($st)][(int) $scopeId])) {
				return false;
			}
			db_modify("update " . $st['dbtable'] . " set " . $st['column'] . " = '$esc' where id = " . (int) $scopeId, __FILE__ . " linje " . __LINE__);
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
			self::$auditColumns = (bool) db_fetch_array(db_select("select column_name from information_schema.columns where table_name = 'audit_log' and column_name = 'setting_key'", __FILE__ . " linje " . __LINE__));
			self::$auditObjekt = self::$auditColumns && (bool) db_fetch_array(db_select("select column_name from information_schema.columns where table_name = 'audit_log' and column_name = 'objekt_type'", __FILE__ . " linje " . __LINE__));
		}
		return self::$auditColumns;
	}

	/** Settings history rows; 'setting.change' is the name used before the roles spec's event names. */
	const HISTORY_HANDLINGS = "'setting.changed', 'setting.reverted', 'setting.change', 'setting.row_created', 'setting.row_updated', 'setting.row_deactivated', 'setting.row_deleted'";

	private static function audit(array $def, string $old, string $new, string $handling): void
	{
		global $bruger_id, $brugernavn;
		$secret = ($def['type'] === 'secret' || (isset($def['permission']) && $def['permission'] === 'settings.organisation.sensitive'));
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
			return array();
		}
		if ($list && strpos($value, $sep) === false) {
			// A per-till list with one entry belongs to till 1 only.
			return array(0 => $value);
		}
		if (strpos($value, $sep) === false) {
			// A box written before it became composite holds one value meant for every part.
			return array(0 => $value, 1 => $value);
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
