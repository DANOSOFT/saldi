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
// 20261117 CL/LH Created (Havemøbelland, Black Week): keeps the webshops (grupper API box4-box6) on an item's effective prices while it has an offer, and restores the normal prices when the offer ends.
//                There is no cron, so ordinary requests drive it, as the stock queue in shopSyncQueue.php: an authorised api/rest_api.php call, the item import, an item card save and a sale, invoice or receipt (shopSyncDrainQueue()), each with a hard time budget.
//                Codex verification: the state is reconciliation, per item and per shop: the price pair each shop last confirmed (JSON), so a partial delivery, a refusal or a send from another path can no longer lose an obligation.
//                Every path that sends prices reports to shopOfferObserve(); an item is tracked from the first offer sent on any path until every shop has confirmed the normal prices again.
//                The next look can only be moved earlier by a request, atomically (flock); a look that ends moves it later only when no earlier look was asked for meanwhile.
//                Sends use their own deadline: millisecond timeouts within the budget, also in the one-at-a-time fallback without curl_multi.
//                Without a webshop API a look makes the push dormant again, so the check on later requests costs no query.

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

if (!function_exists('shopOfferWriteFile')) {
	/**
	 * Writes a small file atomically (temporary file and rename).
	 *
	 * @param string $path
	 * @param string $content
	 * @return bool
	 */
	function shopOfferWriteFile($path, $content) {
		$tmp = $path . '.' . getmypid() . '.' . mt_rand() . '.new';
		if (@file_put_contents($tmp, $content) !== strlen($content)) {
			@unlink($tmp);
			return false;
		}
		if (!@rename($tmp, $path)) {
			@unlink($tmp);
			return false;
		}
		return true;
	}
}

if (!function_exists('shopOfferScheduleRead')) {
	/**
	 * The schedule: when the next look is due and how often a request has asked for an earlier one.
	 *
	 * @return array{due: int, gen: int}|null Null when dormant (no file): an account with no offer costs no query.
	 */
	function shopOfferScheduleRead() {
		$dir = shopOfferPushDir(false);
		if ($dir === '' || !is_file($dir . 'next.json')) {
			return null;
		}
		$data = json_decode((string)@file_get_contents($dir . 'next.json'), true);
		if (!is_array($data) || !isset($data['due'])) {
			return array('due' => 0, 'gen' => 0); // unreadable: look now rather than never
		}
		return array('due' => (int)$data['due'], 'gen' => (int)($data['gen'] ?? 0));
	}
}

if (!function_exists('shopOfferScheduleUpdate')) {
	/**
	 * Changes the schedule under an exclusive lock, so two requests cannot overwrite each other.
	 *
	 * @param callable $change function (array{due:int,gen:int}|null $current): array{due:int,gen:int}|null|false
	 *                         Returns the new schedule, null to make the push dormant, or false to leave it as it is.
	 * @return array{due:int,gen:int}|null The schedule afterwards.
	 */
	function shopOfferScheduleUpdate(callable $change) {
		$dir = shopOfferPushDir();
		if ($dir === '') {
			return null;
		}
		$lock = @fopen($dir . 'schedule.lock', 'c');
		if (!$lock || !flock($lock, LOCK_EX)) {
			if ($lock) fclose($lock);
			return null;
		}
		try {
			$current = shopOfferScheduleRead();
			$next = $change($current);
			if ($next === false) {
				return $current;
			}
			if ($next === null) {
				@unlink($dir . 'next.json');
				return null;
			}
			shopOfferWriteFile($dir . 'next.json', json_encode(array('due' => (int)$next['due'], 'gen' => (int)$next['gen'])));
			return $next;
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}
}

if (!function_exists('shopOfferPushSchedule')) {
	/**
	 * Asks for a look no later than $when. It can only move the next look earlier, never later.
	 *
	 * @param int $when Unix time; 0 asks for a look on the next request (an offer was imported or saved).
	 * @return void
	 */
	function shopOfferPushSchedule($when = 0) {
		$when = (int)$when;
		shopOfferScheduleUpdate(function ($current) use ($when) {
			if ($current !== null && $current['due'] <= $when) {
				return false;
			}
			return array('due' => $when, 'gen' => ($current ? $current['gen'] : 0) + 1);
		});
	}
}

if (!function_exists('shopOfferPushDueAt')) {
	/**
	 * When the next look is due.
	 *
	 * @return int Unix time, PHP_INT_MAX when dormant.
	 */
	function shopOfferPushDueAt() {
		$schedule = shopOfferScheduleRead();
		return $schedule === null ? PHP_INT_MAX : $schedule['due'];
	}
}

if (!function_exists('shopOfferShopKey')) {
	/**
	 * The name a shop has in the state: a hash of its endpoint, so a changed endpoint is a new shop and the url is not stored.
	 *
	 * @param string $endpoint grupper API box4/box5/box6.
	 * @return string
	 */
	function shopOfferShopKey($endpoint) {
		return substr(sha1(trim((string)$endpoint)), 0, 16);
	}
}

if (!function_exists('shopOfferPair')) {
	/**
	 * The price pair a send carries, from shopPricesForItem().
	 *
	 * @param array{salesPrice: string|null, retailPrice: string|null} $prices
	 * @return array{sales: string|null, retail: string|null}
	 */
	function shopOfferPair(array $prices) {
		return array(
			'sales'  => isset($prices['salesPrice']) ? (string)$prices['salesPrice'] : null,
			'retail' => isset($prices['retailPrice']) ? (string)$prices['retailPrice'] : null,
		);
	}
}

if (!function_exists('shopOfferPairSame')) {
	/**
	 * Whether two price pairs are the same to the øre (an empty price only equals an empty price).
	 *
	 * @param array{sales: string|null, retail: string|null}|null $a
	 * @param array{sales: string|null, retail: string|null}|null $b
	 * @return bool
	 */
	function shopOfferPairSame($a, $b) {
		if (!is_array($a) || !is_array($b)) {
			return false;
		}
		foreach (array('sales', 'retail') as $field) {
			$x = $a[$field] ?? null;
			$y = $b[$field] ?? null;
			$xEmpty = ($x === null || $x === '');
			$yEmpty = ($y === null || $y === '');
			if ($xEmpty || $yEmpty) {
				if ($xEmpty !== $yEmpty) return false;
				continue;
			}
			if (round((float)$x, 2) != round((float)$y, 2)) return false;
		}
		return true;
	}
}

if (!function_exists('shopOfferItemPath')) {
	/**
	 * @param int $vareId
	 * @param bool $create Create the directory when missing.
	 * @return string '' when the account has no usable temp directory.
	 */
	function shopOfferItemPath($vareId, $create = false) {
		$dir = shopOfferPushDir($create);
		return $dir === '' ? '' : $dir . 'item-' . (int)$vareId . '.json';
	}
}

if (!function_exists('shopOfferItemLoad')) {
	/**
	 * The state of one tracked item: per shop key, what it confirmed and its retry state.
	 *
	 * @param int $vareId
	 * @return array{shops: array<string, array>}|null Null when the item is not tracked.
	 */
	function shopOfferItemLoad($vareId) {
		$path = shopOfferItemPath($vareId);
		if ($path === '' || !is_file($path)) {
			return null;
		}
		$data = json_decode((string)@file_get_contents($path), true);
		if (!is_array($data) || !isset($data['shops']) || !is_array($data['shops'])) {
			return array('shops' => array()); // unreadable: every shop counts as unconfirmed
		}
		return $data;
	}
}

if (!function_exists('shopOfferItemSave')) {
	/**
	 * Writes the state of one item, or stops tracking it (null).
	 *
	 * @param int $vareId
	 * @param array|null $state
	 * @return void
	 */
	function shopOfferItemSave($vareId, $state) {
		$path = shopOfferItemPath($vareId, $state !== null);
		if ($path === '') {
			return;
		}
		if ($state === null) {
			@unlink($path);
			return;
		}
		$state['vare_id'] = (int)$vareId;
		shopOfferWriteFile($path, json_encode($state, JSON_UNESCAPED_SLASHES));
	}
}

if (!function_exists('shopOfferShopEntry')) {
	/**
	 * One shop's entry in an item's state, with every field present.
	 *
	 * @param array|null $state
	 * @param string $key From shopOfferShopKey().
	 * @return array{confirmed: array|null, attempts: int, refusals: int, retryAt: int, error: string, gaveUp: array|null}
	 */
	function shopOfferShopEntry($state, $key) {
		$entry = (is_array($state) && isset($state['shops'][$key]) && is_array($state['shops'][$key])) ? $state['shops'][$key] : array();
		return array(
			'confirmed' => (isset($entry['confirmed']) && is_array($entry['confirmed'])) ? $entry['confirmed'] : null,
			'attempts'  => (int)($entry['attempts'] ?? 0),
			'refusals'  => (int)($entry['refusals'] ?? 0),
			'retryAt'   => (int)($entry['retryAt'] ?? 0),
			'error'     => (string)($entry['error'] ?? ''),
			'gaveUp'    => (isset($entry['gaveUp']) && is_array($entry['gaveUp'])) ? $entry['gaveUp'] : null,
		);
	}
}

if (!function_exists('shopOfferPlan')) {
	/**
	 * Which shops an item has to be sent to now (pure).
	 *
	 * @param array|null $state From shopOfferItemLoad().
	 * @param array<string, string> $shops Shop key => endpoint, the configured shops.
	 * @param array{sales: string|null, retail: string|null} $desired The effective prices now.
	 * @param int $now Unix time.
	 * @return array{
	 *   send: array<string, string>,  Shop key => endpoint: a mismatch whose retry time has come.
	 *   waitUntil: int|null,          The earliest retry time of a mismatch that waits.
	 *   settled: bool,                Every configured shop has confirmed $desired.
	 *   gaveUp: string[],             Shop keys that refused $desired too often and are no longer sent it.
	 * }
	 */
	function shopOfferPlan($state, array $shops, array $desired, $now) {
		$plan = array('send' => array(), 'waitUntil' => null, 'settled' => true, 'gaveUp' => array());
		foreach ($shops as $key => $endpoint) {
			$entry = shopOfferShopEntry($state, $key);
			if (shopOfferPairSame($entry['confirmed'], $desired)) {
				continue;
			}
			$plan['settled'] = false;
			if (shopOfferPairSame($entry['gaveUp'], $desired)) {
				$plan['gaveUp'][] = $key;
			} elseif ($entry['retryAt'] > $now) {
				if ($plan['waitUntil'] === null || $entry['retryAt'] < $plan['waitUntil']) $plan['waitUntil'] = $entry['retryAt'];
			} else {
				$plan['send'][$key] = $endpoint;
			}
		}
		return $plan;
	}
}

if (!function_exists('shopOfferBackoff')) {
	/**
	 * Seconds to wait after the n-th failure in a row: 1, 2, 4, 8, 16 minutes, then every 30 minutes.
	 *
	 * @param int $failures
	 * @return int
	 */
	function shopOfferBackoff($failures) {
		return (int)min(1800, 60 * (1 << max(0, min((int)$failures, 6) - 1)));
	}
}

if (!defined('SHOP_OFFER_MAX_REFUSALS')) {
	/** Refusals of the same prices after which a shop is no longer sent them (reported; new prices start again). */
	define('SHOP_OFFER_MAX_REFUSALS', 8);
}

if (!defined('SHOP_OFFER_MIN_CALL')) {
	/** Seconds a shop call needs at least; with less left before the deadline it is left for a later look. */
	define('SHOP_OFFER_MIN_CALL', 0.2);
}

if (!function_exists('shopOfferRecord')) {
	/**
	 * Records what each shop answered to a send of $desired (pure on the state array).
	 *
	 * A shop that accepted has confirmed $desired. A transport failure (no answer, 5xx, skipped) keeps the mismatch and retries
	 * with back-off, without limit. A refusal (an answer carrying a rejection) also keeps the mismatch and retries; after
	 * SHOP_OFFER_MAX_REFUSALS refusals of the same prices the shop gives up on them, which the caller reports, and starts again
	 * when the prices change.
	 *
	 * @param array|null $state
	 * @param array<string, array> $results Shop key => result shaped like shopApiRequest()'s (ok, httpCode, error, skipped, transient),
	 *                                      plus untried (left for a later look by the deadline).
	 * @param array{sales: string|null, retail: string|null} $desired
	 * @param int $now
	 * @return array{state: array, delivered: string[], failed: string[], gaveUpNow: string[]}
	 */
	function shopOfferRecord($state, array $results, array $desired, $now) {
		if (!is_array($state)) $state = array('shops' => array());
		$out = array('delivered' => array(), 'failed' => array(), 'gaveUpNow' => array());
		foreach ($results as $key => $result) {
			if (!empty($result['untried'])) {
				continue; // left for a later look by the deadline: not a failure, no back-off
			}
			$entry = shopOfferShopEntry($state, $key);
			if (!empty($result['ok'])) {
				$entry = array('confirmed' => $desired, 'attempts' => 0, 'refusals' => 0, 'retryAt' => 0, 'error' => '', 'gaveUp' => null);
				$out['delivered'][] = $key;
			} elseif (shopSyncWorthRetrying($result)) {
				$entry['attempts']++;
				$entry['retryAt'] = $now + shopOfferBackoff($entry['attempts']);
				$entry['error'] = (string)($result['error'] ?? '');
				$out['failed'][] = $key;
			} else {
				$entry['refusals']++;
				$entry['retryAt'] = $now + shopOfferBackoff($entry['refusals']);
				$entry['error'] = (string)($result['error'] ?? '');
				if ($entry['refusals'] >= SHOP_OFFER_MAX_REFUSALS && !shopOfferPairSame($entry['gaveUp'], $desired)) {
					$entry['gaveUp'] = $desired;
					$out['gaveUpNow'][] = $key;
				}
				$out['failed'][] = $key;
			}
			$state['shops'][$key] = $entry;
		}
		return array('state' => $state) + $out;
	}
}

if (!function_exists('shopOfferPushEndpoints')) {
	/**
	 * The configured webshops, keyed by shopOfferShopKey(): grupper API box4-box6, or, where the Shopify integration's
	 * coexistence switches exist (includes/shopify/coexistence.php on hml/shopify), only the slots still open for the old
	 * integration's outbound sync, as every other legacy sender takes them.
	 *
	 * @return array<string, string> Shop key => endpoint.
	 */
	function shopOfferPushEndpoints() {
		global $db;
		static $cache = array();
		if (isset($cache[(string)$db])) return $cache[(string)$db];
		$list = array();
		if (function_exists('shopify_legacy_endpoints')) {
			$list = shopify_legacy_endpoints(array('db' => $db), 'outbound');
		} else {
			$r = db_fetch_array(db_select("select box4, box5, box6 from grupper where art='API'", __FILE__ . " linje " . __LINE__));
			foreach (array('box4', 'box5', 'box6') as $column) {
				if ($r && trim((string)$r[$column]) !== '') $list[] = trim((string)$r[$column]);
			}
		}
		$endpoints = array();
		foreach ($list as $endpoint) {
			$endpoints[shopOfferShopKey($endpoint)] = $endpoint;
		}
		return $cache[(string)$db] = $endpoints;
	}
}

if (!function_exists('shopOfferObserve')) {
	/**
	 * What another path (item card save, sale, stock sync, bulk sync) sent an item's shops, so the push's state stays true.
	 *
	 * Costs nothing for an item that has no offer and is not tracked (one file check, no query). An item whose offer was sent
	 * is tracked from then on, so the offer's end is restored even if nothing else looks at it. With $confirms (update_price
	 * calls whose answers are known) each shop that accepted has confirmed the prices; a call whose effect on the prices is not
	 * known (update_stock, a fire-and-forget send) confirms nothing and only makes sure the obligation is there.
	 *
	 * @param int $vareId
	 * @param array<string, mixed> $item varer row with the offer columns (shopOfferColumns()).
	 * @param array{salesPrice: string|null, retailPrice: string|null, offer: bool} $prices What was sent (shopPricesForItem()).
	 * @param array<int|string, string> $endpoints Slot => endpoint the call went to.
	 * @param array<int|string, array>|null $results Slot => result, or null when the answers are not known.
	 * @param bool $confirms Whether an accepted call confirms the prices (update_price).
	 * @return void
	 */
	function shopOfferObserve($vareId, array $item, array $prices, array $endpoints, $results, $confirms) {
		$path = shopOfferItemPath($vareId);
		$tracked = ($path !== '' && is_file($path));
		if (!$tracked && empty($prices['offer'])) {
			return;
		}
		$now = time();
		$state = shopOfferItemLoad($vareId) ?? array('shops' => array());
		$desired = shopOfferPair($prices);
		if ($confirms && is_array($results)) {
			$accepted = array();
			foreach ($results as $slot => $result) {
				if (!empty($result['ok']) && isset($endpoints[$slot])) $accepted[shopOfferShopKey($endpoints[$slot])] = $result;
			}
			$state = shopOfferRecord($state, $accepted, $desired, $now)['state'];
		}
		$plan = shopOfferPlan($state, shopOfferPushEndpoints(), $desired, $now);
		if ($plan['settled'] && empty($prices['offer'])) {
			shopOfferItemSave($vareId, null); // every shop confirmed the normal prices: nothing left to restore
			return;
		}
		shopOfferItemSave($vareId, $state);
		if ($plan['send']) {
			shopOfferPushSchedule($now);
		} else {
			$check = shopOfferNextCheck($item, $now);
			shopOfferPushSchedule(min($check ?? ($now + 1800), $plan['waitUntil'] ?? PHP_INT_MAX, $now + 1800));
		}
	}
}

if (!function_exists('shopOfferPushTick')) {
	/**
	 * Looks for items whose shops disagree with their effective prices and sends them, within a hard time budget.
	 *
	 * The cheap part runs on every call: one read of a small file. Only when a look is due does it read the items, and only
	 * one request at a time does (a lock; others leave at once).
	 *
	 * @param array{
	 *   maxItems: int,        Most items sent in this call, default 10.
	 *   maxSeconds: float,    Hard limit on the time this call takes, sends included, default 3.
	 *   now: int,             Unix time to evaluate at, default time() (tests).
	 *   force: bool,          Look even when the due time has not come (after an import).
	 *   prefer: int[],        varer.id values sent before the others (the items an import has just written).
	 * } $options All keys optional.
	 * @return array{looked: bool, pushed: int, failed: int, pending: int, next: int, gaveUp: int}
	 *         pushed counts items every shop they were sent to accepted; pending counts items left for a later look.
	 */
	function shopOfferPushTick(array $options = array()) {
		$started = microtime(true);
		$result = array('looked' => false, 'pushed' => 0, 'failed' => 0, 'pending' => 0, 'next' => 0, 'gaveUp' => 0);
		$now = isset($options['now']) ? (int)$options['now'] : time();
		if (empty($options['force']) && shopOfferPushDueAt() > $now) {
			return $result;
		}
		if (!empty($GLOBALS['db_transaktion_depth']) || !function_exists('shopSyncApiConfigured')) {
			return $result;
		}
		if (!shopSyncApiConfigured()) {
			if (shopOfferScheduleRead() !== null) {
				shopOfferScheduleUpdate(function () { return null; }); // no webshop (any more): dormant, so later requests cost no query
			}
			return $result;
		}
		$dir = shopOfferPushDir();
		if ($dir === '') {
			return $result;
		}
		$lock = @fopen($dir . 'look.lock', 'c');
		if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
			if ($lock) fclose($lock);
			return $result;
		}
		try {
			$result = shopOfferPushRun($now, $started + (isset($options['maxSeconds']) ? (float)$options['maxSeconds'] : 3.0), $options);
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		return $result;
	}
}

if (!function_exists('shopOfferPushRun')) {
	/**
	 * The look itself, holding the look lock. See shopOfferPushTick().
	 *
	 * @param int $now Unix time.
	 * @param float $deadline microtime(true) by which the look has to be done.
	 * @param array<string, mixed> $options As shopOfferPushTick().
	 * @return array{looked: bool, pushed: int, failed: int, pending: int, next: int, gaveUp: int}
	 */
	function shopOfferPushRun($now, $deadline, array $options) {
		global $db;
		$maxItems = isset($options['maxItems']) ? (int)$options['maxItems'] : 10;
		$result = array('looked' => true, 'pushed' => 0, 'failed' => 0, 'pending' => 0, 'next' => 0, 'gaveUp' => 0);
		$schedule = shopOfferScheduleRead();
		$generation = $schedule ? $schedule['gen'] : 0;
		$dir = shopOfferPushDir();

		// The items with an offer that has not ended before yesterday, and the tracked items.
		$tracked = array();
		foreach ((array)@scandir($dir) as $name) {
			if (preg_match('/^item-([0-9]+)\.json$/D', (string)$name, $m)) $tracked[(int)$m[1]] = true;
		}
		$since = date('Y-m-d', strtotime('yesterday', $now));
		$qtxt = "select id, varenr, varenr_alias, kostpris, salgspris, retail_price, m_type, m_rabat, colli_webfragt, stregkode, " . shopOfferColumns();
		$qtxt .= " from varer where (specialtype = 'price' and special_price <> 0 and special_to_date >= '$since')";
		if ($tracked) $qtxt .= " or id in (" . implode(',', array_map('intval', array_keys($tracked))) . ")";
		$qtxt .= " order by id";
		$items = array();
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$items[(int)$r['id']] = $r;
		}
		foreach (array_keys($tracked) as $id) {
			if (!isset($items[$id])) shopOfferItemSave($id, null); // the item is gone
		}

		$shops = shopOfferPushEndpoints();
		$next = $now + 1800; // look at least every half hour, for offers changed elsewhere than the card or the import
		$work = array();
		foreach ($items as $id => $item) {
			$check = shopOfferNextCheck($item, $now);
			if ($check !== null && $check < $next) $next = $check;
			$prices = shopPricesForItem($item, $now);
			$state = isset($tracked[$id]) ? shopOfferItemLoad($id) : null;
			if ($state === null && !$prices['offer']) {
				continue; // not tracked and no offer now: the shops have the normal prices from the ordinary sync
			}
			$plan = shopOfferPlan($state, $shops, shopOfferPair($prices), $now);
			if ($plan['settled'] && !$prices['offer']) {
				shopOfferItemSave($id, null); // every shop confirmed the normal prices
				continue;
			}
			if ($plan['waitUntil'] !== null && $plan['waitUntil'] < $next) $next = $plan['waitUntil'];
			if ($plan['send']) {
				$work[$id] = array($item, $prices, $state, $plan['send']);
			}
		}
		if (!empty($options['prefer'])) {
			$preferred = array_intersect_key($work, array_flip(array_map('intval', (array)$options['prefer'])));
			$work = $preferred + array_diff_key($work, $preferred);
		}

		$log = @fopen(dirname(dirname(__DIR__)) . '/temp/' . $db . '/rest_api.log', 'a') ?: null;
		$sent = 0;
		foreach ($work as $id => $job) {
			list($item, $prices, $state, $targets) = $job;
			if ($sent >= $maxItems || $deadline - microtime(true) < SHOP_OFFER_MIN_CALL) {
				$result['pending']++;
				continue;
			}
			$sent++;
			$shopId = 0;
			if ($b = db_fetch_array(db_select("select shop_id from shop_varer where saldi_id = '" . (int)$id . "'", __FILE__ . " linje " . __LINE__))) {
				$shopId = $b['shop_id'];
			}
			$desired = shopOfferPair($prices);
			$answers = shopOfferSend($targets, shopPriceParams($item, $prices, $shopId), $deadline, $log, "shop offer push vare_id $id");
			$recorded = shopOfferRecord($state, $answers, $desired, $now);
			foreach ($recorded['gaveUpNow'] as $key) {
				$line = 'SHOP OFFER PUSH GAVE UP: item ' . $item['varenr'] . " (vare_id $id) shop $key refused " . SHOP_OFFER_MAX_REFUSALS . ' times: '
					. $recorded['state']['shops'][$key]['error'] . "; it is sent again when the item's prices change";
				if ($log) fwrite($log, date('Y-m-d H:i:s') . ' ' . $line . "\n");
				error_log('saldi: ' . $db . ' ' . $line);
				$result['gaveUp']++;
			}
			$plan = shopOfferPlan($recorded['state'], $shops, $desired, $now);
			shopOfferItemSave($id, ($plan['settled'] && !$prices['offer']) ? null : $recorded['state']);
			if ($recorded['failed']) {
				$result['failed']++;
			} elseif ($recorded['delivered']) {
				$result['pushed']++;
			}
			if ($plan['waitUntil'] !== null && $plan['waitUntil'] < $next) $next = $plan['waitUntil'];
			if ($plan['send']) $result['pending']++; // a shop left untried (the deadline)
		}
		if ($log) fclose($log);
		if ($result['pending']) $next = min($next, $now + 30); // the rest on a request at least half a minute later
		$dormant = !$items && !$result['pending'];
		$result['next'] = $dormant ? 0 : $next;
		// Later than now only if nobody asked for an earlier look while this one ran (the generation is unchanged).
		shopOfferScheduleUpdate(function ($current) use ($next, $generation, $dormant) {
			if ($current !== null && $current['gen'] !== $generation) {
				return array('due' => $dormant ? $current['due'] : min($current['due'], $next), 'gen' => $current['gen']);
			}
			return $dormant ? null : array('due' => $next, 'gen' => $generation);
		});
		return $result;
	}
}

if (!function_exists('shopOfferSend')) {
	/**
	 * Sends one call to some shops and returns when they have answered or the deadline has passed, whichever is first.
	 *
	 * The shared transport (shopApiRequest.php) takes whole seconds and adds up its timeouts one shop after the other when
	 * curl_multi is missing, so it cannot keep a deadline of a second and a half. This one gives every call what is left of the
	 * deadline in milliseconds, in parallel with curl_multi, one after the other with curl, or with the curl command line.
	 * Answers are judged and logged by the shared helpers (shopApiOutcome(), shopApiLogFailure()), and an endpoint this request
	 * already found dead (shopApiSyncState() degraded) is skipped.
	 *
	 * @param array<string, string> $targets Shop key => endpoint.
	 * @param array<string, mixed> $params
	 * @param float $deadline microtime(true).
	 * @param resource|null $log
	 * @param string $context For the log.
	 * @return array<string, array{ok: bool, url: string, httpCode: int, error: string, body: string, attempts: int, skipped: bool, transient: bool}>
	 */
	function shopOfferSend(array $targets, array $params, $deadline, $log, $context) {
		$sync = &shopApiSyncState();
		$results = array();
		$pending = array();
		foreach ($targets as $key => $endpoint) {
			$result = array('ok' => false, 'url' => '', 'httpCode' => 0, 'error' => '', 'body' => '', 'attempts' => 0, 'skipped' => false, 'transient' => true);
			$url = shopApiUrl($endpoint, $params);
			if ($url === '') {
				$result['error'] = 'invalid api endpoint';
				$result['transient'] = false;
			} elseif (isset($sync['degraded'][shopApiEndpointKey($url)])) {
				$result['url'] = $url;
				$result['skipped'] = true;
				$result['error'] = 'endpoint degraded earlier in this request';
			} else {
				$result['url'] = $url;
				$pending[$key] = $url;
			}
			$results[$key] = $result;
		}
		if ($pending && function_exists('curl_multi_init') && function_exists('curl_init')) {
			$calls = shopOfferCurlMulti($pending, $deadline);
		} else {
			$calls = array();
			foreach ($pending as $key => $url) {
				$left = $deadline - microtime(true);
				if ($left < SHOP_OFFER_MIN_CALL) {
					continue; // left untried, reported below
				}
				$calls[$key] = function_exists('curl_init') ? shopOfferCurlOne($url, $left) : shopOfferShellOne($url, $left);
			}
		}
		foreach ($pending as $key => $url) {
			if (!isset($calls[$key])) {
				$results[$key]['skipped'] = true;
				$results[$key]['untried'] = true;
				$results[$key]['error'] = 'offer push time budget used up in this request';
				shopApiLogFailure($log, $context, $url, $results[$key]);
				continue;
			}
			$call = $calls[$key];
			$results[$key]['attempts'] = 1;
			$results[$key]['httpCode'] = $call['httpCode'];
			$results[$key]['body'] = $call['body'];
			$results[$key]['transient'] = !empty($call['transient']);
			$outcome = shopApiOutcome($call);
			if ($outcome['state'] === 'ok') {
				$results[$key]['ok'] = true;
				if (is_resource($log)) fwrite($log, date('Y-m-d H:i:s') . ' shop sync ok ' . $context . ' http ' . (int)$call['httpCode'] . ' ' . $url . "\n");
			} else {
				$results[$key]['error'] = $outcome['error'];
				if ($outcome['state'] === 'refused') $results[$key]['transient'] = false;
				shopApiLogFailure($log, $context, $url, $results[$key]);
			}
		}
		return $results;
	}
}

if (!function_exists('shopOfferCurlHandle')) {
	/**
	 * A curl handle for a shop call, set up as shopApiCurlCall() sets it up but with millisecond timeouts.
	 *
	 * @param string $url
	 * @param float $seconds Time the call may take.
	 * @return resource|CurlHandle|false
	 */
	function shopOfferCurlHandle($url, $seconds) {
		$ch = curl_init($url);
		if ($ch === false) return false;
		$ms = max(1, (int)floor($seconds * 1000));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, min($ms, 2000));
		curl_setopt($ch, CURLOPT_TIMEOUT_MS, $ms);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Saldi shop sync');
		if (defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
			curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		}
		return $ch;
	}
}

if (!function_exists('shopOfferCurlAnswer')) {
	/**
	 * A finished curl handle as the shared outcome judge takes it.
	 *
	 * @param resource|CurlHandle $ch
	 * @param string|false|null $body
	 * @param int $errno
	 * @param string $error
	 * @return array{httpCode: int, body: string, error: string, transient: bool, unreachable: bool}
	 */
	function shopOfferCurlAnswer($ch, $body, $errno, $error) {
		$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		return array(
			'httpCode'    => $httpCode,
			'body'        => is_string($body) ? $body : '',
			'error'       => $errno ? 'curl error ' . $errno . ': ' . $error : '',
			'transient'   => ($errno !== 0 || $httpCode === 0 || $httpCode >= 500),
			'unreachable' => ($httpCode === 0),
		);
	}
}

if (!function_exists('shopOfferCurlOne')) {
	/**
	 * One call, waiting at most $seconds.
	 *
	 * @param string $url
	 * @param float $seconds
	 * @return array{httpCode: int, body: string, error: string, transient: bool, unreachable: bool}
	 */
	function shopOfferCurlOne($url, $seconds) {
		$ch = shopOfferCurlHandle($url, $seconds);
		if ($ch === false) {
			return array('httpCode' => 0, 'body' => '', 'error' => 'curl_init failed', 'transient' => true, 'unreachable' => true);
		}
		$body = curl_exec($ch);
		$answer = shopOfferCurlAnswer($ch, $body, curl_errno($ch), curl_error($ch));
		curl_close($ch);
		return $answer;
	}
}

if (!function_exists('shopOfferCurlMulti')) {
	/**
	 * Calls several shops at once and stops at the deadline.
	 *
	 * @param array<string, string> $urls Shop key => url.
	 * @param float $deadline microtime(true).
	 * @return array<string, array{httpCode: int, body: string, error: string, transient: bool, unreachable: bool}> Missing keys were not tried.
	 */
	function shopOfferCurlMulti(array $urls, $deadline) {
		$left = $deadline - microtime(true);
		if ($left < SHOP_OFFER_MIN_CALL) {
			return array();
		}
		$multi = curl_multi_init();
		$handles = array();
		foreach ($urls as $key => $url) {
			$ch = shopOfferCurlHandle($url, $left);
			if ($ch !== false) {
				curl_multi_add_handle($multi, $ch);
				$handles[$key] = $ch;
			}
		}
		do {
			$status = curl_multi_exec($multi, $running);
			if ($running) {
				curl_multi_select($multi, max(0.01, min(0.1, $deadline - microtime(true))));
			}
		} while ($running && $status === CURLM_OK && microtime(true) < $deadline);
		$answers = array();
		foreach ($handles as $key => $ch) {
			$errno = curl_errno($ch);
			$done = curl_getinfo($ch, CURLINFO_HTTP_CODE) || $errno;
			$answers[$key] = $done
				? shopOfferCurlAnswer($ch, curl_multi_getcontent($ch), $errno, curl_error($ch))
				: array('httpCode' => 0, 'body' => '', 'error' => 'no answer before the offer push deadline', 'transient' => true, 'unreachable' => true);
			curl_multi_remove_handle($multi, $ch);
			curl_close($ch);
		}
		curl_multi_close($multi);
		return $answers;
	}
}

if (!function_exists('shopOfferShellOne')) {
	/**
	 * One call with the curl command line, used only when ext-curl is missing; --max-time takes fractions of a second.
	 *
	 * @param string $url
	 * @param float $seconds
	 * @return array{httpCode: int, body: string, error: string, transient: bool, unreachable: bool}
	 */
	function shopOfferShellOne($url, $seconds) {
		if (!function_exists('shell_exec')) {
			return array('httpCode' => 0, 'body' => '', 'error' => 'no http client available (ext-curl and shell_exec both missing)', 'transient' => true, 'unreachable' => true);
		}
		$bodyFile = tempnam(sys_get_temp_dir(), 'shopoffer');
		if ($bodyFile === false) {
			return array('httpCode' => 0, 'body' => '', 'error' => 'could not create temporary file', 'transient' => true, 'unreachable' => true);
		}
		$seconds = max(0.1, (float)$seconds);
		$cmd = 'curl -s -S -L --max-redirs 3 --proto =http,https'
			. ' --connect-timeout ' . sprintf('%.3F', min(2.0, $seconds))
			. ' --max-time ' . sprintf('%.3F', $seconds)
			. ' -A ' . escapeshellarg('Saldi shop sync')
			. ' -o ' . escapeshellarg($bodyFile)
			. ' -w ' . escapeshellarg("\nSALDI_HTTP:%{http_code}\n")
			. ' ' . escapeshellarg($url) . ' 2>&1; shop_curl_status=$?; printf "\nSALDI_EXIT:%s\n" "$shop_curl_status"';
		$out = trim((string)shell_exec($cmd));
		$body = (string)@file_get_contents($bodyFile);
		@unlink($bodyFile);
		$httpCode = preg_match('/SALDI_HTTP:(\d{3})/', $out, $m) ? (int)$m[1] : 0;
		$exitCode = preg_match('/(?:^|\n)SALDI_EXIT:(\d+)$/D', $out, $m) ? (int)$m[1] : -1;
		return array(
			'httpCode'    => $httpCode,
			'body'        => $body,
			'error'       => ($exitCode !== 0 || !$httpCode) ? 'curl command failed (exit ' . $exitCode . ')' : '',
			'transient'   => ($exitCode !== 0 || $httpCode === 0 || $httpCode >= 500),
			'unreachable' => ($httpCode === 0),
		);
	}
}

if (!function_exists('shopOfferPushStuck')) {
	/**
	 * The items a shop gave up on (refused the same prices SHOP_OFFER_MAX_REFUSALS times), for a status page or a support check.
	 *
	 * @return array<int, array<string, string>> vare_id => shop key => last error.
	 */
	function shopOfferPushStuck() {
		$stuck = array();
		$dir = shopOfferPushDir(false);
		if ($dir === '') return $stuck;
		foreach ((array)@scandir($dir) as $name) {
			if (!preg_match('/^item-([0-9]+)\.json$/D', (string)$name, $m)) continue;
			$state = shopOfferItemLoad((int)$m[1]);
			foreach ((array)($state['shops'] ?? array()) as $key => $entry) {
				if (!empty($entry['gaveUp'])) $stuck[(int)$m[1]][$key] = (string)($entry['error'] ?? '');
			}
		}
		return $stuck;
	}
}

if (!function_exists('shopPriceParams')) {
	/**
	 * The update_price call for an item, as sync_shop_vare() sends it from the item card.
	 *
	 * @param array<string, mixed> $item varer row: varenr, varenr_alias, kostpris, m_type, m_rabat, colli_webfragt, stregkode.
	 * @param array{salesPrice: string|null, retailPrice: string|null, offer: bool} $prices From shopPricesForItem().
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
