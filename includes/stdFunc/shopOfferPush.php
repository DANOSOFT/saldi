<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/shopOfferPush.php --- ver 5.1.1 --- 2026-11-17 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------------
// 20261117 CL/LH Created (Havemøbelland, Black Week): sends an item's prices to the webshops (grupper API box4-box6) when its offer starts or ends.
//                There is no cron, so ordinary requests drive it, as the stock queue in shopSyncQueue.php: shopSyncDrainQueue() after a sale, an invoice or a receipt, api/rest_api.php after a shop's own call, the item import after a campaign import, and a save on the item card.
//                The state lives beside the stock queue in the account's temp directory: a file with the time the next look is due (the cheap check every driver makes), and one file per item that a shop shows an offer price for, with the prices last delivered and the retry state.
//                Only changes are sent; a delivery that fails in transit is retried with back-off; a look stops at its item and time budget.
//                Until a campaign import or an item card save asks for a look, and again once no offer is left, the push is dormant and the check makes no query.

if (!function_exists('shopOfferPushDir')) {
	/**
	 * The directory holding this account's offer push state.
	 *
	 * @param bool $create Create it when missing.
	 * @return string Absolute path with a trailing slash, or '' when the account has no usable temp directory.
	 */
	function shopOfferPushDir($create = true) {
		global $db;
		$name = (string)$db;
		if ($name === '' || !preg_match('/^[a-zA-Z0-9_]+$/D', $name)) {
			return '';
		}
		$dir = dirname(dirname(__DIR__)) . '/temp/' . $name . '/shopoffer/';
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

if (!function_exists('shopOfferPushDueAt')) {
	/**
	 * When the next look is due. Without the file the push is dormant: an account that has had no offer since a campaign
	 * import or an item card save asked for a look costs no query at all.
	 *
	 * @return int Unix time, PHP_INT_MAX when dormant.
	 */
	function shopOfferPushDueAt() {
		$dir = shopOfferPushDir(false);
		if ($dir === '' || !is_file($dir . 'next')) {
			return PHP_INT_MAX;
		}
		return (int)@file_get_contents($dir . 'next');
	}
}

if (!function_exists('shopOfferPushSchedule')) {
	/**
	 * Sets when the next look is due; 0 asks for a look on the next request (an offer was imported or saved).
	 *
	 * @param int $when Unix time.
	 * @return void
	 */
	function shopOfferPushSchedule($when = 0) {
		$dir = shopOfferPushDir();
		if ($dir === '') {
			return;
		}
		$tmp = $dir . 'next.' . getmypid() . '.new';
		if (@file_put_contents($tmp, (string)(int)$when) !== false) {
			@rename($tmp, $dir . 'next');
		}
	}
}

if (!function_exists('shopOfferPushRecord')) {
	/**
	 * Reads the state of one item: the prices last delivered, and the retry state of a delivery not yet made.
	 *
	 * @param int $vareId varer.id
	 * @return array{sales: string, retail: string, attempts: int, retryAt: int}|null Null when no shop shows an offer for the item.
	 */
	function shopOfferPushRecord($vareId) {
		$dir = shopOfferPushDir(false);
		$path = $dir . 'p' . (int)$vareId;
		if ($dir === '' || !is_file($path)) {
			return null;
		}
		$f = explode(' ', trim((string)@file_get_contents($path)));
		return array('sales' => $f[0] ?? '', 'retail' => $f[1] ?? '', 'attempts' => (int)($f[2] ?? 0), 'retryAt' => (int)($f[3] ?? 0));
	}
}

if (!function_exists('shopOfferPushStore')) {
	/**
	 * Writes or removes the state of one item.
	 *
	 * @param int $vareId varer.id
	 * @param array{sales: string, retail: string, attempts: int, retryAt: int}|null $record Null removes it.
	 * @return void
	 */
	function shopOfferPushStore($vareId, $record) {
		$dir = shopOfferPushDir();
		if ($dir === '') {
			return;
		}
		$path = $dir . 'p' . (int)$vareId;
		if ($record === null) {
			@unlink($path);
			return;
		}
		$clean = function ($v) {
			return preg_replace('/[^0-9.\-]/', '', (string)$v);
		};
		$line = $clean($record['sales']) . ' ' . $clean($record['retail']) . ' ' . (int)$record['attempts'] . ' ' . (int)$record['retryAt'] . "\n";
		$tmp = $path . '.' . getmypid() . '.new';
		if (@file_put_contents($tmp, $line) !== false) {
			@rename($tmp, $path);
		}
	}
}

if (!function_exists('shopOfferPushSame')) {
	/**
	 * Whether two prices are the same to the øre.
	 *
	 * @param string|float $a
	 * @param string|float $b
	 * @return bool
	 */
	function shopOfferPushSame($a, $b) {
		return round((float)$a, 2) == round((float)$b, 2);
	}
}

if (!function_exists('shopOfferPushDecide')) {
	/**
	 * What to do for one item, from its prices now and its state.
	 *
	 * @param array{salesPrice: string, retailPrice: string, offer: bool} $prices From shopPricesForItem().
	 * @param array{sales: string, retail: string, attempts: int, retryAt: int}|null $record From shopOfferPushRecord().
	 * @param int $now Unix time.
	 * @return string 'push' (the shops show something else), 'wait' (a failed delivery waits for its retry time),
	 *                'forget' (the shops show the normal prices again: drop the state) or 'none'.
	 */
	function shopOfferPushDecide(array $prices, $record, $now) {
		if ($record === null) {
			return $prices['offer'] ? 'push' : 'none';
		}
		if ($record['sales'] === '') {
			// Only a failed delivery of an offer is on record: the shops still show the normal prices.
			if (!$prices['offer']) {
				return 'forget';
			}
		} elseif (shopOfferPushSame($record['sales'], $prices['salesPrice']) && shopOfferPushSame($record['retail'], $prices['retailPrice'])) {
			// The shops show these prices already; a failed delivery of other prices since is moot.
			return $prices['offer'] ? 'none' : 'forget';
		}
		if ($record['retryAt'] > $now) {
			return 'wait';
		}
		return 'push';
	}
}

if (!function_exists('shopOfferPushTick')) {
	/**
	 * Looks for offers that started or ended and sends the items' prices to the webshops, within a budget.
	 *
	 * The cheap part runs on every call: a check of the due time in a small file. Only when it is due does it read the
	 * items with an offer, and only one request at a time does (a lock file; others leave at once).
	 *
	 * @param array{
	 *   maxItems: int,        Most items sent in this call, default 10.
	 *   maxSeconds: float,    Wall time after which no further item is started, default 3.
	 *   now: int,             Unix time to evaluate at, default time() (tests).
	 *   force: bool,          Look even when the due time has not come (after an import).
	 *   timeout: int,         Seconds per shop call, default 5.
	 *   connectTimeout: int,  Seconds to connect, default 2.
	 * } $options All keys optional.
	 * @return array{looked: bool, pushed: int, failed: int, pending: int, next: int} pending counts the items still to send.
	 */
	function shopOfferPushTick(array $options = array()) {
		global $db;
		$result = array('looked' => false, 'pushed' => 0, 'failed' => 0, 'pending' => 0, 'next' => 0);
		$now = isset($options['now']) ? (int)$options['now'] : time();
		if (empty($options['force']) && shopOfferPushDueAt() > $now) {
			return $result;
		}
		if (!empty($GLOBALS['db_transaktion_depth']) || !function_exists('shopSyncApiConfigured') || !shopSyncApiConfigured()) {
			return $result;
		}
		$dir = shopOfferPushDir();
		if ($dir === '') {
			return $result;
		}
		$lock = @fopen($dir . 'lock', 'c');
		if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
			if ($lock) fclose($lock);
			return $result;
		}
		try {
			$result = shopOfferPushRun($dir, $now, $options);
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		return $result;
	}
}

if (!function_exists('shopOfferPushRun')) {
	/**
	 * The look itself, holding the lock. See shopOfferPushTick().
	 *
	 * @param string $dir From shopOfferPushDir().
	 * @param int $now Unix time.
	 * @param array<string, mixed> $options As shopOfferPushTick().
	 * @return array{looked: bool, pushed: int, failed: int, pending: int, next: int}
	 */
	function shopOfferPushRun($dir, $now, array $options) {
		global $db;
		$maxItems = isset($options['maxItems']) ? (int)$options['maxItems'] : 10;
		$maxSeconds = isset($options['maxSeconds']) ? (float)$options['maxSeconds'] : 3.0;
		$started = microtime(true);
		$result = array('looked' => true, 'pushed' => 0, 'failed' => 0, 'pending' => 0, 'next' => 0);

		// The items with an offer that has not ended before yesterday, and the items a shop may still show an offer for.
		$ids = array();
		foreach ((array)@scandir($dir) as $name) {
			if (preg_match('/^p([0-9]+)$/D', (string)$name, $m)) $ids[(int)$m[1]] = true;
		}
		$since = date('Y-m-d', strtotime('yesterday', $now));
		$qtxt = "select id, varenr, varenr_alias, kostpris, salgspris, retail_price, m_type, m_rabat, colli_webfragt, stregkode, " . shopOfferColumns();
		$qtxt .= " from varer where (specialtype = 'price' and special_price <> 0 and special_to_date >= '$since')";
		if ($ids) $qtxt .= " or id in (" . implode(',', array_map('intval', array_keys($ids))) . ")";
		$qtxt .= " order by id";
		$items = array();
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$items[(int)$r['id']] = $r;
		}
		foreach (array_keys($ids) as $id) {
			if (!isset($items[$id])) shopOfferPushStore($id, null); // the item is gone
		}

		$next = $now + 1800; // look at least every half hour, for offers changed elsewhere than the card or the import
		$work = array();
		foreach ($items as $id => $item) {
			$check = shopOfferNextCheck($item, $now);
			if ($check !== null && $check < $next) $next = $check;
			$prices = shopPricesForItem($item, $now);
			$record = shopOfferPushRecord($id);
			$decision = shopOfferPushDecide($prices, $record, $now);
			if ($decision === 'forget') {
				shopOfferPushStore($id, null);
			} elseif ($decision === 'wait') {
				if ($record['retryAt'] < $next) $next = $record['retryAt'];
			} elseif ($decision === 'push') {
				$work[$id] = array($item, $prices, $record);
			}
		}

		$endpoints = shopOfferPushEndpoints();
		if (!$endpoints) {
			$work = array();
		}
		$log = @fopen(dirname(dirname(__DIR__)) . '/temp/' . $db . '/rest_api.log', 'a');
		$log = $log ?: null;
		foreach ($work as $id => $job) {
			if ($result['pushed'] + $result['failed'] >= $maxItems || microtime(true) - $started >= $maxSeconds) {
				$result['pending']++;
				continue;
			}
			list($item, $prices, $record) = $job;
			$shopId = 0;
			if ($b = db_fetch_array(db_select("select shop_id from shop_varer where saldi_id = '" . (int)$id . "'", __FILE__ . " linje " . __LINE__))) {
				$shopId = $b['shop_id'];
			}
			$params = shopPriceParams($item, $prices, $shopId);
			$responses = shopApiRequestParallel($endpoints, $params, $log, array(
				'context' => "shop offer push vare_id $id",
				'timeout' => isset($options['timeout']) ? (int)$options['timeout'] : 5,
				'connectTimeout' => isset($options['connectTimeout']) ? (int)$options['connectTimeout'] : 2,
				'retries' => 0,
			));
			$retry = false;
			foreach ($responses as $response) {
				if (!$response['ok'] && shopSyncWorthRetrying($response)) $retry = true;
			}
			if ($retry) {
				$attempts = ($record ? (int)$record['attempts'] : 0) + 1;
				$retryAt = $now + min(1800, 60 * (1 << min($attempts - 1, 5)));
				shopOfferPushStore($id, array('sales' => $record ? $record['sales'] : '', 'retail' => $record ? $record['retail'] : '',
					'attempts' => $attempts, 'retryAt' => $retryAt));
				if ($retryAt < $next) $next = $retryAt;
				$result['failed']++;
			} else {
				// Delivered, or refused by a shop that answered: the refusal is about the item, not the figure, so it is not sent again.
				shopOfferPushStore($id, $prices['offer']
					? array('sales' => $prices['salesPrice'], 'retail' => $prices['retailPrice'], 'attempts' => 0, 'retryAt' => 0)
					: null);
				$result['pushed']++;
			}
		}
		if ($log) fclose($log);
		if ($result['pending']) $next = $now + 30; // the rest on a request at least half a minute later
		if (!$items && !$result['pending']) {
			@unlink($dir . 'next'); // no offer and nothing on record: dormant until an import or a card save asks again
			$result['next'] = 0;
			return $result;
		}
		$result['next'] = $next;
		shopOfferPushSchedule($next);
		return $result;
	}
}

if (!function_exists('shopOfferPushEndpoints')) {
	/**
	 * The webshop endpoints the push sends to: grupper API box4-box6, or, where the Shopify integration's coexistence switches
	 * exist (includes/shopify/coexistence.php on hml/shopify), only the slots still open for the old integration's outbound
	 * sync, as every other legacy sender takes them, so a slot handed over to Shopify (which prices it itself) gets nothing.
	 *
	 * @return array<int|string, string> Slot => endpoint.
	 */
	function shopOfferPushEndpoints() {
		global $db;
		if (function_exists('shopify_legacy_endpoints')) {
			return shopify_legacy_endpoints(array('db' => $db), 'outbound');
		}
		$endpoints = array();
		$r = db_fetch_array(db_select("select box4, box5, box6 from grupper where art='API'", __FILE__ . " linje " . __LINE__));
		foreach (array('box4', 'box5', 'box6') as $slot => $column) {
			if ($r && trim((string)$r[$column]) !== '') $endpoints[$slot] = trim((string)$r[$column]);
		}
		return $endpoints;
	}
}

if (!function_exists('shopPriceParams')) {
	/**
	 * The update_price call for an item, as sync_shop_vare() sends it from the item card.
	 *
	 * @param array<string, mixed> $item varer row: varenr, varenr_alias, kostpris, m_type, m_rabat, colli_webfragt, stregkode.
	 * @param array{salesPrice: string, retailPrice: string, offer: bool} $prices From shopPricesForItem().
	 * @param int|string $shopId shop_varer.shop_id, or 0.
	 * @return array<string, mixed>
	 */
	function shopPriceParams(array $item, array $prices, $shopId) {
		return array(
			'update_price' => $shopId,
			'salesPrice'   => $prices['salesPrice'],
			'discountType' => $item['m_type'] ?? '',
			'discount'     => $item['m_rabat'] ?? '',
			'itemNo'       => $item['varenr'] ?? '',
			'itemNoAlias'  => $item['varenr_alias'] ?? '',
			'rand'         => '',
			'costPrice'    => $item['kostpris'] ?? '',
			'retailPrice'  => $prices['retailPrice'],
			'webFragt'     => $item['colli_webfragt'] ?? '',
			'barcode'      => $item['stregkode'] ?? '',
		);
	}
}
?>
