<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/coexistence.php --- ver 5.0.0 --- 2026-10-26 ---
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
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// Legacy coexistence switches (WP15a, K06): close the old webshop integration's order intake
// and outbound sync for one slot (grupper art='API' box4/box5/box6 = slot 4/5/6) at a time, so
// the old integration and Shopify never run at the same time for the same shop.
//
// Settings var_grp='shopify_legacy:<slot>': intake_enabled / outbound_enabled ('0', 'off',
// 'false' or 'nej' close the flow; a missing row means on), and for the go-live drain
// drain_until (unix time) and drain_manifest (comma list of shop order ids still admitted
// while intake is closed).
//
// This runs inside the legacy pages on every install, MySQL ones and ones without the Shopify
// schema included, so it reads through db_select() and never sends a statement that can fail:
// db_select() has no quiet error path (it mails, alerts and may exit). Anything it can't read
// counts as on.
//
// 20261026 CL/SZ Created (SD-750).

if (!function_exists('shopify_legacy_enabled')) {
	/** Whether a legacy flow ('intake' or 'outbound') is open for a slot (4, 5 or 6). */
	function shopify_legacy_enabled(array $ctx, int $slot, string $flow): bool
	{
		$value = shopify_legacy_settings($slot)[$flow . '_enabled'] ?? null;
		return $value === null || !in_array(strtolower(trim($value)), array('0', 'off', 'false', 'nej'), true);
	}
}

if (!function_exists('shopify_legacy_endpoints')) {
	/**
	 * The legacy endpoints of every slot that has one and is open for the flow, keyed by the
	 * original slot (4/5/6), never renumbered. An empty box4 no longer hides box5/box6.
	 *
	 * Like an admitted intake request, the caller holds each slot's shared lock until the
	 * request ends, so a cutover waits for a sync that is still sending. A slot whose cutover is
	 * running right now is left out of this call.
	 *
	 * @return array<int, string>
	 */
	function shopify_legacy_endpoints(array $ctx, string $flow): array
	{
		$r = db_fetch_array(db_select("select box4, box5, box6 from grupper where art='API'", __FILE__ . " linje " . __LINE__));
		$endpoints = array();
		foreach (array(4, 5, 6) as $slot) {
			$url = trim((string)($r["box$slot"] ?? ''));
			if ($url !== '' && shopify_legacy_shared($ctx, $slot) !== false && shopify_legacy_enabled($ctx, $slot, $flow)) {
				$endpoints[$slot] = $url;
			}
		}
		return $endpoints;
	}
}

if (!function_exists('shopify_legacy_admission')) {
	/**
	 * Admits one legacy intake request for a slot. While admitted, the request holds a shared
	 * lock on the slot, so a cutover (shopify_legacy_cutover(), exclusive) waits for it and the
	 * setting is read only once no cutover is running. With intake closed, an order in the
	 * drain manifest is still admitted until drain_until.
	 *
	 * The lock is kept until the request ends. Slot 0 (no shop mapped) is always admitted.
	 *
	 * @return array{admitted: bool, reason: 'open'|'drain'|'closed'|'busy'|'no_slot'|'no_lock', slot: int, afd: int, action: string}
	 */
	function shopify_legacy_admission(array $ctx, int $slot, int $afd, string $action, ?string $extId = null): array
	{
		$result = array('admitted' => true, 'reason' => 'no_slot', 'slot' => $slot, 'afd' => $afd, 'action' => $action);
		if (!in_array($slot, array(4, 5, 6), true)) {
			return $result;
		}
		$fh = shopify_legacy_shared($ctx, $slot);
		if ($fh === null) {
			// The lock file can't be opened: never close a live flow over that.
			$result['reason'] = 'no_lock';
		} elseif ($fh === false) {
			// A cutover is running: the caller answers "try again", never "closed".
			return array('admitted' => false, 'reason' => 'busy') + $result;
		}
		if (shopify_legacy_enabled($ctx, $slot, 'intake')) {
			$result['reason'] = $fh ? 'open' : $result['reason'];
		} else {
			$settings = shopify_legacy_settings($slot);
			$manifest = array_map('trim', explode(',', (string)($settings['drain_manifest'] ?? '')));
			$now = (int)($ctx['now'] ?? time());
			if ($extId === null || trim($extId) === '' || !in_array(trim($extId), $manifest, true) || $now >= (int)($settings['drain_until'] ?? 0)) {
				return array('admitted' => false, 'reason' => 'closed') + $result;
			}
			$result['reason'] = 'drain';
		}
		return $result;
	}
}

if (!function_exists('shopify_legacy_cutover')) {
	/**
	 * Runs $op (the settings write that closes or reopens a slot) under the slot's exclusive
	 * lock, after every admitted request has finished. $op must commit its own work: the lock
	 * is only released once the connection is back outside a transaction, so no request is
	 * admitted on the old setting. Only the settings write belongs in $op; a call back into
	 * this tenant from inside it would wait for the lock it holds.
	 *
	 * @throws RuntimeException when the lock can't be taken or $op left a transaction open
	 */
	function shopify_legacy_cutover(array $ctx, int $slot, callable $op)
	{
		if (!in_array($slot, array(4, 5, 6), true)) {
			throw new InvalidArgumentException("legacy slot $slot is not 4, 5 or 6");
		}
		if (!shopify_legacy_tx_idle()) {
			throw new RuntimeException('cutover must start outside a transaction');
		}
		$fh = shopify_legacy_lock($ctx, $slot, LOCK_EX, (float)($ctx['lock_wait'] ?? 30));
		if (!$fh) {
			throw new RuntimeException("slot $slot: " . ($fh === null ? 'lock file not usable' : 'admitted requests still running'));
		}
		try {
			$value = $op();
			if (!shopify_legacy_tx_idle()) {
				throw new RuntimeException('cutover left a transaction open; it was rolled back');
			}
			return $value;
		} finally {
			// Whatever $op left uncommitted is rolled back while the lock is still held, so no
			// request admitted after the release can read a setting that may still change.
			if (!shopify_legacy_tx_idle()) {
				global $connection;
				pg_query($connection, 'ROLLBACK');
			}
			flock($fh, LOCK_UN);
			fclose($fh);
		}
	}
}

if (!function_exists('shopify_legacy_effective_afd')) {
	/** The department a legacy REST order lands in: orders from 172.105.246.144 always go to afd 4. */
	function shopify_legacy_effective_afd(int $afd, string $ip): int
	{
		return $ip === '172.105.246.144' ? 4 : $afd;
	}
}

if (!function_exists('shopify_legacy_slot_for_afd')) {
	/**
	 * The legacy slot of the Shopify shop that owns an afd, 0 when there is none. shopify_shops
	 * is only read on a PostgreSQL tenant whose Shopify schema marker is set, so nothing is
	 * ever sent to a table that may not exist.
	 */
	function shopify_legacy_slot_for_afd(int $afd): int
	{
		if (!shopify_legacy_readable()) {
			return 0;
		}
		$marker = db_fetch_array(db_select("select var_value from settings where var_grp = 'shopify' and var_name = 'schema_version'", __FILE__ . " linje " . __LINE__));
		if (!$marker || (int)$marker['var_value'] < 1) {
			return 0;
		}
		$r = db_fetch_array(db_select("select legacy_slot from shopify_shops where afd = '" . (int)$afd . "'", __FILE__ . " linje " . __LINE__));
		return $r ? (int)$r['legacy_slot'] : 0;
	}
}

if (!function_exists('shopify_legacy_settings')) {
	/** @return array<string, string> the slot's shopify_legacy settings, empty when unreadable */
	function shopify_legacy_settings(int $slot): array
	{
		$settings = array();
		if (!in_array($slot, array(4, 5, 6), true) || !shopify_legacy_readable(true)) {
			return $settings;
		}
		$q = db_select("select var_name, var_value from settings where var_grp = 'shopify_legacy:$slot'", __FILE__ . " linje " . __LINE__);
		while ($q && $r = db_fetch_array($q)) {
			$settings[$r['var_name']] = (string)$r['var_value'];
		}
		return $settings;
	}
}

if (!function_exists('shopify_legacy_readable')) {
	/**
	 * Whether a read can be sent without risking db_select()'s error path: never inside a
	 * failed PostgreSQL transaction, and shopify_shops only on PostgreSQL (the Shopify module
	 * is PostgreSQL only). settings exists on every tenant.
	 */
	function shopify_legacy_readable(bool $anyEngine = false): bool
	{
		global $db_type, $connection;
		if (in_array(strtolower((string)$db_type), array('mysql', 'mysqli'), true)) {
			return $anyEngine;
		}
		return !($connection && function_exists('pg_transaction_status') && pg_transaction_status($connection) === PGSQL_TRANSACTION_INERROR);
	}
}

if (!function_exists('shopify_legacy_tx_idle')) {
	function shopify_legacy_tx_idle(): bool
	{
		global $db_type, $connection;
		if (in_array(strtolower((string)$db_type), array('mysql', 'mysqli'), true) || !$connection || !function_exists('pg_transaction_status')) {
			return true;
		}
		return pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE;
	}
}

if (!function_exists('shopify_legacy_lock')) {
	/**
	 * Takes the slot's lock without blocking for longer than $wait seconds (a blocked flock
	 * doesn't count against max_execution_time).
	 *
	 * @return resource|false|null the handle; false when another holder kept it; null when the file can't be used
	 */
	function shopify_legacy_lock(array $ctx, int $slot, int $mode, float $wait)
	{
		$dir = __DIR__ . '/../../temp/' . (string)($ctx['db'] ?? '');
		$path = "$dir/.ht_shopify_legacy_$slot.lock";
		$usable = ($ctx['db'] ?? '') !== '' && is_dir($dir) && (is_file($path) ? is_writable($path) : is_writable($dir));
		$fh = $usable ? fopen($path, 'c') : false;
		if (!$fh) {
			return null;
		}
		$until = microtime(true) + $wait;
		while (!flock($fh, $mode | LOCK_NB)) {
			if (microtime(true) >= $until) {
				fclose($fh);
				return false;
			}
			usleep(100000);
		}
		return $fh;
	}
}

if (!function_exists('shopify_legacy_shared')) {
	/**
	 * The slot's shared lock for this request: taken once, then kept until the request ends
	 * (a PHP handle that goes out of scope would release it).
	 *
	 * @return resource|false|null see shopify_legacy_lock(); false (cutover running) is not kept
	 */
	function shopify_legacy_shared(array $ctx, int $slot)
	{
		static $held = array();
		$key = (string)($ctx['db'] ?? '') . ':' . $slot;
		if (!isset($held[$key])) {
			$fh = shopify_legacy_lock($ctx, $slot, LOCK_SH, (float)($ctx['lock_wait'] ?? 3));
			if ($fh === false) {
				return false;
			}
			$held[$key] = $fh;
		}
		return $held[$key];
	}
}
