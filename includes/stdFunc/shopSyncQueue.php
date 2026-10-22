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
		// A claim keeps the item in its name, with the claiming pid appended.
		if (!preg_match('/^v(\d+)-x(\d+)-l(\d+)(?:\.[0-9]+\.claim)?$/D', $name, $m)) {
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
			// A claim whose request died takes its item with it, so one left lying around for
			// longer than any delivery could take is put back. The name it goes back to may
			// already exist, in which case that newer marker wins and the claim is dropped.
			if (substr($name, -6) === '.claim') {
				$age = time() - (int)@filemtime($dir . $name);
				if ($age > 900) {
					$live = preg_replace('/\.[0-9]+\.claim$/D', '', $dir . $name);
					if (is_file($live) || !@rename($dir . $name, $live)) {
						@unlink($dir . $name);
					}
				}
				continue;
			}
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

if (!function_exists('shopSyncQueueClaim')) {
	/**
	 * Takes an entry out of the queue for the length of one delivery.
	 *
	 * Delivering an item is seconds of http calls, and another request can change the same
	 * item's stock while that is happening. If the delivery held on to the marker and deleted
	 * it at the end, it would delete that newer change with it - an older delivery clearing a
	 * newer pending update, which is the one thing the ticket says must not happen.
	 *
	 * So the marker is renamed out of the way first. rename() is atomic, so exactly one caller
	 * can claim an entry: a second request finds it gone and leaves it alone. A change arriving
	 * mid-delivery creates a fresh marker under the original name, and that one survives.
	 *
	 * @param array $entry  Entry from shopSyncQueuePending() or shopSyncQueueRead().
	 * @return array|null  The entry with its claim path, or null when somebody else has it.
	 */
	function shopSyncQueueClaim(array $entry) {
		if (empty($entry['path']) || !is_file($entry['path'])) {
			return null;
		}
		$claim = $entry['path'] . '.' . getmypid() . '.claim';
		if (!@rename($entry['path'], $claim)) {
			return null;
		}
		$entry['claim'] = $claim;
		$entry['live']  = $entry['path'];
		$entry['path']  = $claim;
		return $entry;
	}
}

if (!function_exists('shopSyncQueueDone')) {
	/**
	 * Drops a claim once the shops have taken the figure.
	 *
	 * Only the claim. A marker that appeared under the original name while this delivery was
	 * running belongs to a change this delivery did not carry, so it is left where it is.
	 *
	 * @param array $entry  Entry from shopSyncQueueClaim().
	 * @return void
	 */
	function shopSyncQueueDone(array $entry) {
		$path = !empty($entry['claim']) ? $entry['claim'] : (isset($entry['path']) ? $entry['path'] : '');
		if ($path !== '') {
			@unlink($path);
		}
	}
}

if (!function_exists('shopSyncQueueRelease')) {
	/**
	 * Puts a claim back on the queue after a delivery that failed.
	 *
	 * The attempt is counted whether or not something re-queued the item in the meantime: a
	 * newer change does not make the shop any more willing to take the figure, and without
	 * this an item that fails during every delivery would never reach the attempt limit and
	 * would be retried in silence for ever. The newer change's own details win otherwise -
	 * the wider mode, and the earlier of the two first-seen times.
	 *
	 * @param array $entry        Entry from shopSyncQueueClaim().
	 * @param int   $maxAttempts  Attempts after which it counts as a final failure.
	 * @param bool  $countAttempt False when the delivery never happened for a reason that
	 *                            is not the item's: no webshop configured any more.
	 * @return bool  True when this was the attempt that made it final.
	 */
	function shopSyncQueueRelease(array $entry, $maxAttempts = 5, $countAttempt = true) {
		$live     = isset($entry['live']) ? $entry['live'] : $entry['path'];
		$newer    = shopSyncQueueRead($live);
		$attempts = max($newer ? (int)$newer['attempts'] : 0, (int)$entry['attempts'] + ($countAttempt ? 1 : 0));
		$mode     = ($entry['mode'] === 'all' || ($newer && $newer['mode'] === 'all')) ? 'all' : 'stock';
		$first    = $newer ? min((int)$newer['first'], (int)$entry['first']) : (int)$entry['first'];
		shopSyncQueueWrite($live, $mode, $attempts, $first);
		if (!empty($entry['claim'])) {
			@unlink($entry['claim']);
		}
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
if (!function_exists('shopSyncWorthRetrying')) {
	/**
	 * Whether a failed send is worth queueing for another request.
	 *
	 * A call that never happened - skipped because the budget ran out, or because the endpoint
	 * had already proved dead - and a call that got no answer or a 5xx are all worth repeating:
	 * nothing about the figure was wrong. A shop that answered and refused the item is not,
	 * and neither is a 4xx: the item is unknown or the parameters are wrong at that shop, and
	 * sending the same thing again tomorrow will be refused the same way. Those are reported
	 * instead.
	 *
	 * @param array $res  One result from shopApiRequest() or shopApiRequestParallel().
	 * @return bool
	 */
	function shopSyncWorthRetrying(array $res) {
		if (!empty($res['skipped'])) {
			return true;
		}
		$code = (int) (isset($res['httpCode']) ? $res['httpCode'] : 0);
		return ($code === 0 || $code >= 500);
	}
}

?>
