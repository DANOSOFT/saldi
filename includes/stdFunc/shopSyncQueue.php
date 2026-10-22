<?php
// --- includes/stdFunc/shopSyncQueue.php --- ver 5.0.0 --- 2026-10-22 ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// Keeps the stock updates a request could not deliver, so the next one delivers them.
//
// A webshop call that is skipped because the 30 second budget ran out, or that fails for
// transport reasons, used to be lost: the figure in Saldi was right and the shop kept the
// old one, with nothing but a log line to say so. At Havemoebelland that was most of an
// invoicing run - 1,179 skipped calls on the worst day checked.
//
// One file per item, named after the item, so the file name is the deduplication: two POS
// terminals and an invoicing run can all mark the same item in the same second and the
// result is one marker, with no lock and no read-modify-write.
//
// No stock figure is stored. The figure is read from the database when the marker is
// drained, so a queued value can never overwrite a newer one - the same reason
// lager/shopStockIncludes/sync.php re-reads its totals for every batch instead of freezing
// them when the job is created.
//
// The markers live beside rest_api.log in the account's own temp directory rather than in
// the system temp directory, because work that must not be lost cannot live somewhere the
// operating system sweeps.
// 20261022 CL/MJ SST-876 Created.

if (!function_exists('shopSyncQueueDir')) {
	/**
	 * The directory holding this account's undelivered stock updates.
	 *
	 * @param bool $create  Create the directory when it does not exist yet.
	 * @return string  Absolute path with a trailing slash, or an empty string when the
	 *                 account has no usable temp directory.
	 */
	function shopSyncQueueDir($create = true) {
		global $db;
		$name = (string)$db;
		// The database name becomes part of a path, so it is checked rather than trusted,
		// exactly as lager/webshopStock.php:32 checks it before building its own path.
		if ($name === '' || !preg_match('/^[a-zA-Z0-9_]+$/D', $name)) {
			return '';
		}
		$dir = dirname(dirname(__DIR__)) . '/temp/' . $name . '/shopsync/';
		if (!is_dir($dir)) {
			if (!$create) {
				return '';
			}
			if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
				return '';
			}
		}
		return $dir;
	}
}

if (!function_exists('shopSyncQueueKey')) {
	/**
	 * The file name for one item, variant and warehouse.
	 *
	 * Everything in it is cast to an integer, so the name cannot carry a path or anything
	 * else from a request.
	 *
	 * @param int $vare_id     varer.id.
	 * @param int $variant_id  variant_varer.id, 0 for a product without variants.
	 * @param int $lager       Warehouse number the figure belongs to.
	 * @return string
	 */
	function shopSyncQueueKey($vare_id, $variant_id, $lager) {
		return 'v' . (int)$vare_id . '-x' . (int)$variant_id . '-l' . (int)$lager;
	}
}

if (!function_exists('shopSyncQueueAdd')) {
	/**
	 * Records that an item still owes a webshop update.
	 *
	 * Marking the same item twice is one marker, and the attempt count and the time it was
	 * first seen survive, so a marker that keeps failing can be told from a new one.
	 *
	 * @param int    $vare_id     varer.id.
	 * @param int    $variant_id  variant_varer.id, or 0.
	 * @param int    $lager       Warehouse number.
	 * @param string $mode        'stock' or 'all', as sync_shop_vare() takes it.
	 * @return bool  True when the marker is on disk.
	 */
	function shopSyncQueueAdd($vare_id, $variant_id, $lager, $mode = 'stock') {
		$dir = shopSyncQueueDir();
		if ($dir === '') {
			return false;
		}
		$path    = $dir . shopSyncQueueKey($vare_id, $variant_id, $lager);
		$entry   = shopSyncQueueRead($path);
		$attempts = $entry ? (int)$entry['attempts'] : 0;
		$first    = $entry ? (int)$entry['first'] : time();
		// 'all' sends the price and sku calls as well as the stock one, so it covers 'stock'.
		// A marker asked for both must not be narrowed by a later stock-only change.
		$wanted = ($mode === 'all' || ($entry && $entry['mode'] === 'all')) ? 'all' : 'stock';
		return shopSyncQueueWrite($path, $wanted, $attempts, $first);
	}
}

if (!function_exists('shopSyncQueueWrite')) {
	/**
	 * Writes one marker atomically.
	 *
	 * @param string $path      Full path of the marker.
	 * @param string $mode      'stock' or 'all'.
	 * @param int    $attempts  Deliveries tried so far.
	 * @param int    $first     Unix time the item was first queued.
	 * @return bool
	 */
	function shopSyncQueueWrite($path, $mode, $attempts, $first) {
		$line = ($mode === 'all' ? 'all' : 'stock') . ' ' . (int)$attempts . ' ' . (int)$first . "\n";
		$tmp  = $path . '.' . getmypid() . '.new';
		if (@file_put_contents($tmp, $line, LOCK_EX) !== strlen($line)) {
			@unlink($tmp);
			return false;
		}
		@chmod($tmp, 0600);
		if (!@rename($tmp, $path)) {
			@unlink($tmp);
			return false;
		}
		return true;
	}
}

if (!function_exists('shopSyncQueueRead')) {
	/**
	 * Reads one marker.
	 *
	 * @param string $path  Full path of the marker.
	 * @return array{vare_id:int,variant_id:int,lager:int,mode:string,attempts:int,first:int}|null
	 *         Null when the file is gone or unreadable - a marker another request has just
	 *         delivered is a normal race, not an error.
	 */
	function shopSyncQueueRead($path) {
		if (!is_file($path)) {
			return null;
		}
		$raw = @file_get_contents($path);
		if ($raw === false) {
			return null;
		}
		$name = basename($path);
		if (!preg_match('/^v(\d+)-x(\d+)-l(\d+)$/D', $name, $m)) {
			return null;
		}
		$f = explode(' ', trim($raw));
		return array(
			'vare_id'    => (int)$m[1],
			'variant_id' => (int)$m[2],
			'lager'      => (int)$m[3],
			'mode'       => (isset($f[0]) && $f[0] === 'all') ? 'all' : 'stock',
			'attempts'   => isset($f[1]) ? (int)$f[1] : 0,
			'first'      => isset($f[2]) ? (int)$f[2] : 0,
			'path'       => $path,
		);
	}
}

if (!function_exists('shopSyncQueuePending')) {
	/**
	 * The undelivered updates, oldest first.
	 *
	 * Oldest first so an item that has been waiting since yesterday is not starved by the
	 * ones this request just queued.
	 *
	 * @param int $limit  Most entries to return. 0 means every one of them.
	 * @return array<int, array>  Entries as shopSyncQueueRead() returns them.
	 */
	function shopSyncQueuePending($limit = 0) {
		$dir = shopSyncQueueDir(false);
		if ($dir === '') {
			return array();
		}
		$names = @scandir($dir);
		if ($names === false) {
			return array();
		}
		$entries = array();
		foreach ($names as $name) {
			if (!preg_match('/^v\d+-x\d+-l\d+$/D', $name)) {
				continue;
			}
			$entry = shopSyncQueueRead($dir . $name);
			if ($entry) {
				$entry['mtime'] = (int)@filemtime($dir . $name);
				$entries[] = $entry;
			}
		}
		usort($entries, 'shopSyncQueueOldestFirst');
		if ($limit > 0 && count($entries) > $limit) {
			$entries = array_slice($entries, 0, (int)$limit);
		}
		return $entries;
	}
}

if (!function_exists('shopSyncQueueOldestFirst')) {
	/**
	 * Orders two entries by the time they were first queued.
	 *
	 * @param array $a  One entry.
	 * @param array $b  The other.
	 * @return int
	 */
	function shopSyncQueueOldestFirst(array $a, array $b) {
		if ($a['first'] === $b['first']) {
			return 0;
		}
		return ($a['first'] < $b['first']) ? -1 : 1;
	}
}

if (!function_exists('shopSyncQueueDone')) {
	/**
	 * Drops a marker once the shops have taken the figure.
	 *
	 * @param array $entry  Entry from shopSyncQueuePending() or shopSyncQueueRead().
	 * @return void
	 */
	function shopSyncQueueDone(array $entry) {
		if (!empty($entry['path'])) {
			@unlink($entry['path']);
		}
	}
}

if (!function_exists('shopSyncQueueRetryLater')) {
	/**
	 * Counts a failed delivery and keeps the marker.
	 *
	 * The marker is kept however often it fails. Deleting it would be dropping a stock update
	 * again, which is the whole point of this file; what changes is that past the attempt
	 * limit the item is reported as a failure the user can act on instead of being retried
	 * quietly for ever.
	 *
	 * @param array $entry        Entry being retried.
	 * @param int   $maxAttempts  Attempts after which it counts as a final failure.
	 * @return bool  True when this was the attempt that made it final.
	 */
	function shopSyncQueueRetryLater(array $entry, $maxAttempts = 5) {
		$attempts = (int)$entry['attempts'] + 1;
		shopSyncQueueWrite($entry['path'], $entry['mode'], $attempts, (int)$entry['first']);
		return ($attempts >= (int)$maxAttempts);
	}
}

if (!function_exists('shopSyncQueueCount')) {
	/**
	 * How many updates are waiting.
	 *
	 * @return int
	 */
	function shopSyncQueueCount() {
		return count(shopSyncQueuePending(0));
	}
}
?>
