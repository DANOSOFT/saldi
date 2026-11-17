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
//                One sender only: shopOfferReconcileTick(), run by an authorised api/rest_api.php?action=shop_offer_tick call from a scheduler, one tick at a time (a non-blocking lock), within 25 seconds, continuing where the last tick stopped.
//                Per item and shop the state holds, as JSON, the price pair the shop last confirmed to the reconciler and whether that is still certain; the reconciler writes its intent before it sends, and nothing at all when it cannot.
//                The item card, the stock sync and the bulk sync send an item under offer management only the pair each shop confirmed (shopOfferForeignBegin()), so they never change a shop's price; each such send leaves an in-flight note, and the reconciler certifies a send only when no other send of the item overlapped it.
//                Refusal and retry counters belong to the price pair being sent and start again when the desired pair changes; after 8 refusals of one pair that shop is given up for it, visibly.
//                Every hour each tracked item's prices are sent again, so drift heals; an item is no longer tracked once every shop has confirmed the normal prices after its offer.

if (!defined('SHOP_OFFER_MAX_REFUSALS')) {
	define('SHOP_OFFER_MAX_REFUSALS', 8);      // refusals of one price pair before a shop is given up for that pair
	define('SHOP_OFFER_MIN_CALL', 0.2);        // seconds a call needs at least; with less left the tick stops
	define('SHOP_OFFER_CALL_SECONDS', 10);     // most one call of the reconciler may take
	define('SHOP_OFFER_TICK_SECONDS', 25);     // most one tick may take
	define('SHOP_OFFER_FOREIGN_SECONDS', 120); // how long a send by another path that did not end cleanly counts as in flight
	define('SHOP_OFFER_HEAL_SECONDS', 3600);   // a certified price is sent again after this long
	define('SHOP_OFFER_LOCK_WAIT', 0.2);       // most a sender waits for an item's lock
	define('SHOP_OFFER_LOCK_STRIPES', 64);     // item locks: one file per stripe, not per item
}

if (!function_exists('shopOfferPushDir')) {
	/**
	 * The directory holding this account's offer state. It exists only where the reconciler has run.
	 *
	 * @param bool $create Create it when missing (the reconciler only).
	 * @return string Absolute path with a trailing slash, or '' when it does not exist and is not created.
	 */
	function shopOfferPushDir($create = false) {
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
	 * @return bool Whether the file now holds $content.
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

if (!function_exists('shopOfferLogLoud')) {
	/**
	 * A line both in the account's rest_api.log and in the PHP error log, for what support has to see.
	 *
	 * @param string $message
	 * @return void
	 */
	function shopOfferLogLoud($message) {
		global $db;
		error_log('saldi: ' . $db . ' SHOP OFFER ' . $message);
		if (preg_match('/^[a-zA-Z0-9_]+$/D', (string)$db)) {
			$log = @fopen(dirname(dirname(__DIR__)) . '/temp/' . $db . '/rest_api.log', 'a');
			if ($log) {
				fwrite($log, date('Y-m-d H:i:s') . ' SHOP OFFER ' . $message . "\n");
				fclose($log);
			}
		}
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
	 * @return string '' when the account has no offer state directory.
	 */
	function shopOfferItemPath($vareId) {
		$dir = shopOfferPushDir(false);
		return $dir === '' ? '' : $dir . 'item-' . (int)$vareId . '.json';
	}
}

if (!function_exists('shopOfferStateRead')) {
	/**
	 * One item's state.
	 *
	 * @param int $vareId
	 * @return array|null|false Null when the item is not tracked, false when its file cannot be read as state.
	 */
	function shopOfferStateRead($vareId) {
		$path = shopOfferItemPath($vareId);
		if ($path === '' || !is_file($path)) {
			return null;
		}
		$raw = @file_get_contents($path);
		$data = is_string($raw) ? json_decode($raw, true) : null;
		if (!is_array($data) || !isset($data['shops']) || !is_array($data['shops'])) {
			return false;
		}
		return $data;
	}
}

if (!function_exists('shopOfferStateWrite')) {
	/**
	 * Writes one item's state, or stops tracking it (null).
	 *
	 * @param int $vareId
	 * @param array|null $state
	 * @return bool Whether the state on disk is now $state.
	 */
	function shopOfferStateWrite($vareId, $state) {
		$path = shopOfferItemPath($vareId);
		if ($path === '') {
			return false;
		}
		if ($state === null) {
			return !is_file($path) || @unlink($path);
		}
		$state['vare_id'] = (int)$vareId;
		$json = json_encode($state, JSON_UNESCAPED_SLASHES);
		return $json !== false && shopOfferWriteFile($path, $json);
	}
}

if (!function_exists('shopOfferWithItemLock')) {
	/**
	 * Runs $fn holding the item's lock, which every read-modify-write of its state takes. Nobody holds it across a send.
	 *
	 * @param int $vareId
	 * @param callable $fn
	 * @param float $wait Most seconds to wait for it.
	 * @return array{0: bool, 1: mixed} Whether the lock was taken, and what $fn returned.
	 */
	function shopOfferWithItemLock($vareId, callable $fn, $wait = SHOP_OFFER_LOCK_WAIT) {
		$dir = shopOfferPushDir(false);
		$lock = $dir === '' ? false : @fopen($dir . 'lock-' . ((int)$vareId % SHOP_OFFER_LOCK_STRIPES), 'c');
		if (!$lock) {
			return array(false, null);
		}
		$until = microtime(true) + $wait;
		while (!flock($lock, LOCK_EX | LOCK_NB)) {
			if (microtime(true) >= $until) {
				fclose($lock);
				return array(false, null);
			}
			usleep(5000);
		}
		try {
			return array(true, $fn());
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}
}

if (!function_exists('shopOfferShopEntry')) {
	/**
	 * One shop's entry in an item's state, with every field present.
	 *
	 * confirmed: the pair the shop last accepted from the reconciler with no other send overlapping (null: never);
	 * certain: confirmed is what the shop has now; pending: the pair owed, which attempts, refusals, retryAt, error and gaveUp
	 * belong to; verifiedAt: when confirmed was last certified.
	 *
	 * @param array|null $state
	 * @param string $key From shopOfferShopKey().
	 * @return array{confirmed: array|null, certain: bool, verifiedAt: int, pending: array|null, attempts: int, refusals: int, retryAt: int, error: string, gaveUp: bool}
	 */
	function shopOfferShopEntry($state, $key) {
		$entry = (is_array($state) && isset($state['shops'][$key]) && is_array($state['shops'][$key])) ? $state['shops'][$key] : array();
		return array(
			'confirmed'  => (isset($entry['confirmed']) && is_array($entry['confirmed'])) ? $entry['confirmed'] : null,
			'certain'    => !empty($entry['certain']),
			'verifiedAt' => (int)($entry['verifiedAt'] ?? 0),
			'pending'    => (isset($entry['pending']) && is_array($entry['pending'])) ? $entry['pending'] : null,
			'attempts'   => (int)($entry['attempts'] ?? 0),
			'refusals'   => (int)($entry['refusals'] ?? 0),
			'retryAt'    => (int)($entry['retryAt'] ?? 0),
			'error'      => (string)($entry['error'] ?? ''),
			'gaveUp'     => !empty($entry['gaveUp']),
		);
	}
}

if (!function_exists('shopOfferBackoff')) {
	/**
	 * Seconds to wait after the n-th failure or refusal of one pair: 1, 2, 4 ... minutes, at most half an hour.
	 *
	 * @param int $n
	 * @return int
	 */
	function shopOfferBackoff($n) {
		return (int)min(1800, 60 * pow(2, max(0, (int)$n - 1)));
	}
}

if (!function_exists('shopOfferPlanShop')) {
	/**
	 * What the reconciler owes one shop for the desired pair. The counters belong to the pending pair, so a new desired pair
	 * starts with none: no inherited refusals, no inherited back-off.
	 *
	 * @param array $entry From shopOfferShopEntry().
	 * @param array{sales: string|null, retail: string|null} $desired
	 * @param int $now
	 * @return array{0: string, 1: array} 'ok', 'send' (the shop may not have the pair), 'heal' (certified over an hour ago),
	 *                                    'wait' (back-off) or 'gaveUp'; and the entry with its pending pair brought up to date.
	 */
	function shopOfferPlanShop(array $entry, array $desired, $now) {
		$mismatch = !$entry['certain'] || !shopOfferPairSame($entry['confirmed'], $desired);
		$heal = !$mismatch && (int)$now - $entry['verifiedAt'] >= SHOP_OFFER_HEAL_SECONDS;
		if (!$mismatch && !$heal) {
			$entry['pending'] = null;
			$entry['attempts'] = $entry['refusals'] = $entry['retryAt'] = 0;
			$entry['error'] = '';
			$entry['gaveUp'] = false;
			return array('ok', $entry);
		}
		if (!shopOfferPairSame($entry['pending'], $desired)) {
			$entry['pending'] = $desired;
			$entry['attempts'] = $entry['refusals'] = $entry['retryAt'] = 0;
			$entry['error'] = '';
			$entry['gaveUp'] = false;
		}
		if ($entry['gaveUp']) {
			return array('gaveUp', $entry);
		}
		if ($entry['retryAt'] > (int)$now) {
			return array('wait', $entry);
		}
		return array($mismatch ? 'send' : 'heal', $entry);
	}
}

if (!function_exists('shopOfferPlanItem')) {
	/**
	 * What the reconciler owes an item's shops. Entries of shops no longer configured are dropped.
	 *
	 * @param array $state
	 * @param array<string, string> $shops Shop key => endpoint (shopOfferPushEndpoints()).
	 * @param array{sales: string|null, retail: string|null} $desired
	 * @param int $now
	 * @return array{state: array, send: array<string, string>, waitUntil: int|null, gaveUp: list<string>, settled: bool}
	 *         send: shop key => 'send' or 'heal'; settled: every shop certainly has the desired pair.
	 */
	function shopOfferPlanItem(array $state, array $shops, array $desired, $now) {
		$out = array('state' => $state, 'send' => array(), 'waitUntil' => null, 'gaveUp' => array(), 'settled' => true);
		$out['state']['shops'] = array();
		foreach ($shops as $key => $endpoint) {
			list($action, $entry) = shopOfferPlanShop(shopOfferShopEntry($state, $key), $desired, $now);
			$out['state']['shops'][$key] = $entry;
			if (!$entry['certain'] || !shopOfferPairSame($entry['confirmed'], $desired)) {
				$out['settled'] = false;
			}
			if ($action === 'send' || $action === 'heal') {
				$out['send'][$key] = $action;
			} elseif ($action === 'wait') {
				$out['waitUntil'] = $out['waitUntil'] === null ? $entry['retryAt'] : min($out['waitUntil'], $entry['retryAt']);
			} elseif ($action === 'gaveUp') {
				$out['gaveUp'][] = $key;
			}
		}
		return $out;
	}
}

if (!function_exists('shopOfferRecordShop')) {
	/**
	 * Records one shop's answer to the reconciler's send of $pair.
	 *
	 * Accepted with no other send of the item overlapping: certified. Accepted but overlapped: the shop may have either pair,
	 * so it stays uncertain and is sent again on the next tick. Refused (an answer that is not a transport failure): not
	 * applied, so the shop keeps what it had; counts towards giving up on this pair. A transport failure or no answer: the
	 * effect is unknown, so the shop is uncertain; retried with back-off. Not sent (deadline, or a shop already dead this tick):
	 * nothing changes.
	 *
	 * @param array $entry The entry after the intent (certain false, pending $pair).
	 * @param bool $wasCertain Its certainty before the intent.
	 * @param array{sales: string|null, retail: string|null} $pair
	 * @param array $result From shopOfferSend(), or array('untried' => true).
	 * @param bool $overlapped Whether another send of the item started while this one was under way.
	 * @param int $now
	 * @return array{0: string, 1: array, 2: bool} Outcome ('certified', 'overlapped', 'refused', 'failed', 'untried'), the entry,
	 *                                             and whether the shop was given up for this pair just now.
	 */
	function shopOfferRecordShop(array $entry, $wasCertain, array $pair, array $result, $overlapped, $now) {
		$now = (int)$now;
		if (!empty($result['untried'])) {
			$entry['certain'] = $wasCertain && !$overlapped;
			return array('untried', $entry, false);
		}
		if (!empty($result['ok'])) {
			if ($overlapped) {
				$entry['certain'] = false;
				$entry['error'] = 'another send of this item overlapped; sent again on the next tick';
				return array('overlapped', $entry, false);
			}
			$entry['confirmed'] = $pair;
			$entry['certain'] = true;
			$entry['verifiedAt'] = $now;
			$entry['pending'] = null;
			$entry['attempts'] = $entry['refusals'] = $entry['retryAt'] = 0;
			$entry['error'] = '';
			$entry['gaveUp'] = false;
			return array('certified', $entry, false);
		}
		$entry['error'] = (string)($result['error'] ?? '');
		$answered = (int)($result['httpCode'] ?? 0) > 0 && !shopSyncWorthRetrying($result);
		if ($answered) {
			$entry['certain'] = $wasCertain && !$overlapped;
			$entry['refusals']++;
			$entry['retryAt'] = $now + shopOfferBackoff($entry['refusals']);
			$gaveUpNow = false;
			if ($entry['refusals'] >= SHOP_OFFER_MAX_REFUSALS && !$entry['gaveUp']) {
				$entry['gaveUp'] = $gaveUpNow = true;
			}
			return array('refused', $entry, $gaveUpNow);
		}
		$entry['certain'] = false;
		$entry['attempts']++;
		$entry['retryAt'] = $now + shopOfferBackoff($entry['attempts']);
		return array('failed', $entry, false);
	}
}

if (!function_exists('shopOfferForeignOpen')) {
	/**
	 * The in-flight notes of other paths' sends that have not ended and not expired.
	 *
	 * @param array|null $state
	 * @param int $now
	 * @return array<string, int> Token => expiry.
	 */
	function shopOfferForeignOpen($state, $now) {
		$open = array();
		foreach ((array)($state['foreign']['open'] ?? array()) as $token => $until) {
			if ((int)$until > (int)$now) {
				$open[(string)$token] = (int)$until;
			}
		}
		return $open;
	}
}

if (!function_exists('shopOfferHasOfferFields')) {
	/**
	 * Whether an item has a 'price' offer that has not ended before yesterday: the items the reconciler reads.
	 *
	 * @param array<string, mixed> $item varer row with the offer columns.
	 * @param int $now
	 * @return bool
	 */
	function shopOfferHasOfferFields(array $item, $now) {
		return (string)($item['specialtype'] ?? '') === 'price' && (float)($item['special_price'] ?? 0) != 0
			&& (string)($item['special_to_date'] ?? '') >= date('Y-m-d', strtotime('yesterday', (int)$now));
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

// ------------------------------------------------------------------ the other paths: item card, stock sync, bulk sync

if (!function_exists('shopOfferForeignBegin')) {
	/**
	 * Called by a path other than the reconciler just before it sends an item's prices (update_price, or update_stock, which
	 * carries them too) to some shops.
	 *
	 * Null, at no cost, when the item is not under offer management (not tracked and no 'price' offer that has not ended
	 * before yesterday) or when the reconciler has never run on this account: the path sends today's prices. Otherwise the
	 * path is to send each shop the pair that shop confirmed to the reconciler (or today's prices to a shop that never
	 * confirmed one), so it does not change any shop's price; the new prices go out with the reconciler's next tick, which this
	 * marks the item for. The send is noted as in flight, under the item's lock and before the pairs are read, so the
	 * reconciler does not certify a send of its own that this one could overtake. If the note cannot be written (lock, disk),
	 * the path falls back to today's prices, leaves a lock-free note so the reconciler checks every shop again, and logs it.
	 *
	 * @param int $vareId
	 * @param array<string, mixed> $item varer row with the offer columns.
	 * @param array<int|string, string> $endpoints Slot => endpoint the path sends to.
	 * @param int|null $now
	 * @return array{vare_id: int, token: string|null, pairs: array<int|string, array|null>, fallback: bool}|null
	 */
	function shopOfferForeignBegin($vareId, array $item, array $endpoints, $now = null) {
		$now = $now === null ? time() : (int)$now;
		$vareId = (int)$vareId;
		$dir = shopOfferPushDir(false);
		if ($dir === '' || !$endpoints) {
			return null;
		}
		if (!is_file($dir . 'item-' . $vareId . '.json') && !shopOfferHasOfferFields($item, $now)) {
			return null;
		}
		$token = bin2hex(random_bytes(8));
		list($locked, $pairs) = shopOfferWithItemLock($vareId, function () use ($vareId, $endpoints, $token, $now) {
			$state = shopOfferStateRead($vareId);
			if ($state === false) {
				$state = array('shops' => array(), 'reset' => true); // unreadable: no shop's price is known
			} elseif ($state === null) {
				$state = array('shops' => array());
			}
			$state['foreign']['seq'] = (int)($state['foreign']['seq'] ?? 0) + 1;
			$open = shopOfferForeignOpen($state, $now);
			$open[$token] = $now + SHOP_OFFER_FOREIGN_SECONDS;
			$state['foreign']['open'] = $open;
			if (empty($state['dirty'])) {
				$state['dirty'] = $now;
			}
			$pairs = array();
			foreach ($endpoints as $slot => $endpoint) {
				$pairs[$slot] = shopOfferShopEntry($state, shopOfferShopKey($endpoint))['confirmed'];
			}
			return shopOfferStateWrite($vareId, $state) ? $pairs : false;
		});
		if (!$locked || $pairs === false) {
			$noted = @touch($dir . 'uncertain-' . $vareId);
			shopOfferLogLoud('STATE NOT WRITABLE: a send of item vare_id ' . $vareId . ' could not be noted (' . ($locked ? 'write failed' : 'lock busy')
				. '); it carries the normal prices' . ($noted ? '' : ', and the note for the reconciler failed too'));
			return array('vare_id' => $vareId, 'token' => null, 'pairs' => array(), 'fallback' => true);
		}
		return array('vare_id' => $vareId, 'token' => $token, 'pairs' => $pairs, 'fallback' => false);
	}
}

if (!function_exists('shopOfferForeignParams')) {
	/**
	 * A call's parameters with the pair shopOfferForeignBegin() gave the shop in $slot, if any.
	 *
	 * @param array<string, mixed> $params With salesPrice and retailPrice.
	 * @param array|null $handle From shopOfferForeignBegin().
	 * @param int|string $slot
	 * @return array<string, mixed>
	 */
	function shopOfferForeignParams(array $params, $handle, $slot) {
		$pair = is_array($handle) ? ($handle['pairs'][$slot] ?? null) : null;
		if (is_array($pair)) {
			$params['salesPrice'] = $pair['sales'] ?? null;
			$params['retailPrice'] = $pair['retail'] ?? null;
		}
		return $params;
	}
}

if (!function_exists('shopOfferForeignGroups')) {
	/**
	 * Splits one send to several shops into sends with the same parameters, as shopOfferForeignParams() gives each shop.
	 *
	 * @param array<int|string, string> $targets Slot => endpoint.
	 * @param array|null $handle From shopOfferForeignBegin().
	 * @param array<string, mixed> $params
	 * @return list<array{targets: array<int|string, string>, params: array<string, mixed>}>
	 */
	function shopOfferForeignGroups(array $targets, $handle, array $params) {
		$groups = array();
		foreach ($targets as $slot => $endpoint) {
			$withPair = shopOfferForeignParams($params, $handle, $slot);
			$key = json_encode(array($withPair['salesPrice'] ?? null, $withPair['retailPrice'] ?? null));
			$groups[$key]['targets'][$slot] = $endpoint;
			$groups[$key]['params'] = $withPair;
		}
		return array_values($groups);
	}
}

if (!function_exists('shopOfferAnswered')) {
	/**
	 * Whether a call is over for certain: the shop answered, or it was never sent.
	 *
	 * @param array $result shopApiRequest()-style result.
	 * @return bool
	 */
	function shopOfferAnswered(array $result) {
		return (int)($result['httpCode'] ?? 0) > 0 || (!empty($result['skipped']) && empty($result['attempts']));
	}
}

if (!function_exists('shopOfferForeignEnd')) {
	/**
	 * Ends the in-flight note of a send once every shop has answered. A call without an answer (a timeout) may still reach
	 * its shop, so its note is left to expire after SHOP_OFFER_FOREIGN_SECONDS.
	 *
	 * @param int $vareId
	 * @param array|null $handle From shopOfferForeignBegin().
	 * @param array<int|string, array> $results The calls' results.
	 * @return void
	 */
	function shopOfferForeignEnd($vareId, $handle, array $results) {
		if (!is_array($handle) || empty($handle['token'])) {
			return;
		}
		foreach ($results as $result) {
			if (!is_array($result) || !shopOfferAnswered($result)) {
				return;
			}
		}
		$token = (string)$handle['token'];
		shopOfferWithItemLock((int)$vareId, function () use ($vareId, $token) {
			$state = shopOfferStateRead((int)$vareId);
			if (is_array($state) && isset($state['foreign']['open'][$token])) {
				unset($state['foreign']['open'][$token]);
				shopOfferStateWrite((int)$vareId, $state);
			}
		});
	}
}

// ------------------------------------------------------------------ the reconciler

if (!function_exists('shopOfferReconcileTick')) {
	/**
	 * One tick of the only sender of offer-driven prices: api/rest_api.php?action=shop_offer_tick, called by a scheduler.
	 *
	 * Returns 'busy' at once when another tick runs. Sends nothing when the state cannot be written. Within the budget it
	 * sends, per item, each shop whose certified pair differs from the item's effective prices now (items another path marked
	 * first, then from where the last tick stopped), then the hourly re-sends, oldest first.
	 *
	 * @param array{now?: int, budget?: float, maxItems?: int} $options now and maxItems for tests; budget at most 25 seconds.
	 * @return array<string, int|float|string> result: 'ok', 'busy', 'no api' or 'state not writable'; counts.
	 */
	function shopOfferReconcileTick(array $options = array()) {
		$started = microtime(true);
		$budget = min((float)SHOP_OFFER_TICK_SECONDS, max(0.5, (float)($options['budget'] ?? SHOP_OFFER_TICK_SECONDS)));
		$now = isset($options['now']) ? (int)$options['now'] : time();
		$result = array('result' => 'ok', 'checked' => 0, 'sent' => 0, 'certified' => 0, 'refused' => 0, 'failed' => 0,
			'overlapped' => 0, 'deferred' => 0, 'left' => 0, 'healed' => 0, 'gaveUp' => 0, 'stuck' => 0, 'untracked' => 0,
			'stateErrors' => 0, 'elapsed' => 0);
		$shops = shopOfferPushEndpoints();
		if (!$shops) {
			$result['result'] = 'no api';
			return $result;
		}
		$dir = shopOfferPushDir(true);
		$lock = ($dir !== '' && is_writable($dir)) ? @fopen($dir . 'tick.lock', 'c') : false;
		if (!$lock) {
			shopOfferLogLoud('STATE NOT WRITABLE: the tick sent nothing (' . ($dir === '' ? 'no state directory' : $dir) . ')');
			$result['result'] = 'state not writable';
			$result['stateErrors'] = 1;
			return $result;
		}
		if (!flock($lock, LOCK_EX | LOCK_NB)) {
			fclose($lock);
			$result['result'] = 'busy';
			return $result;
		}
		try {
			$result = shopOfferReconcileRun($now, $started + $budget, $shops, $dir, $options, $result);
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		$result['elapsed'] = round(microtime(true) - $started, 3);
		return $result;
	}
}

if (!function_exists('shopOfferReconcileRun')) {
	/**
	 * The tick itself, holding the tick lock. See shopOfferReconcileTick().
	 *
	 * @param int $now
	 * @param float $deadline microtime(true).
	 * @param array<string, string> $shops
	 * @param string $dir
	 * @param array $options
	 * @param array $result
	 * @return array
	 */
	function shopOfferReconcileRun($now, $deadline, array $shops, $dir, array $options, array $result) {
		global $db;
		$maxItems = isset($options['maxItems']) ? (int)$options['maxItems'] : PHP_INT_MAX;

		// What is tracked, what another path noted without its lock, and temporary files a killed writer left.
		$tracked = $notes = array();
		foreach ((array)@scandir($dir) as $name) {
			if (preg_match('/^item-([0-9]+)\.json$/D', (string)$name, $m)) {
				$tracked[(int)$m[1]] = true;
			} elseif (preg_match('/^uncertain-([0-9]+)$/D', (string)$name, $m)) {
				$notes[(int)$m[1]] = true;
			} elseif (substr((string)$name, -4) === '.new' && (int)@filemtime($dir . $name) < $now - 3600) {
				@unlink($dir . $name);
			}
		}
		$since = date('Y-m-d', strtotime('yesterday', $now));
		$known = array_keys($tracked + $notes);
		$qtxt = "select id, varenr, varenr_alias, kostpris, salgspris, retail_price, m_type, m_rabat, colli_webfragt, stregkode, " . shopOfferColumns();
		$qtxt .= " from varer where (specialtype = 'price' and special_price <> 0 and special_to_date >= '$since')";
		if ($known) $qtxt .= " or id in (" . implode(',', array_map('intval', $known)) . ")";
		$qtxt .= " order by id";
		$items = array();
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($q)) {
			$items[(int)$row['id']] = $row;
		}
		foreach ($known as $id) {
			if (!isset($items[$id])) { // the item is gone
				shopOfferWithItemLock($id, function () use ($id, $dir) {
					shopOfferStateWrite($id, null);
					@unlink($dir . 'uncertain-' . $id);
				});
			}
		}

		// The order: items whose state only needs tidying (no call); then items owing a shop a new pair, those another path
		// marked first (oldest mark first), then by id from where the last tick stopped; then the hourly re-sends, the longest
		// unverified first. An untracked item without an offer now costs nothing.
		$cursor = (int)(json_decode((string)@file_get_contents($dir . 'cursor.json'), true)['after'] ?? 0);
		$tidy = $marked = $after = $before = $heal = array();
		foreach ($items as $id => $item) {
			$state = isset($tracked[$id]) ? shopOfferStateRead($id) : null;
			$prices = shopPricesForItem($item, $now);
			if ($state === null && !$prices['offer'] && !isset($notes[$id])) {
				continue;
			}
			$result['checked']++;
			$plan = shopOfferPlanItem(is_array($state) ? $state : array('shops' => array()), $shops, shopOfferPair($prices), $now);
			$owes = !is_array($state) || $state['shops'] || !empty($state['reset']) || $prices['offer'];
			if ($owes) {
				$result['stuck'] += count($plan['gaveUp']);
			}
			if ($state === false || isset($notes[$id]) || !empty($state['reset']) || ($owes && in_array('send', $plan['send'], true))) {
				if (is_array($state) && !empty($state['dirty'])) {
					$marked[$id] = (int)$state['dirty'];
				} elseif ($id > $cursor) {
					$after[$id] = $id;
				} else {
					$before[$id] = $id;
				}
			} elseif ($owes && $plan['send']) {
				$oldest = PHP_INT_MAX;
				foreach ($plan['send'] as $key => $why) {
					$oldest = min($oldest, $plan['state']['shops'][$key]['verifiedAt']);
				}
				$heal[$id] = $oldest;
			} elseif (is_array($state) && (!$owes || ($plan['settled'] && !$prices['offer']))) {
				$tidy[$id] = $id;
			}
		}
		asort($marked);
		asort($heal);
		$queue = array();
		foreach (array_keys($tidy) as $id) $queue[] = array($id, 'tidy');
		foreach (array_keys($marked) as $id) $queue[] = array($id, 'urgent');
		foreach (array_keys($after) as $id) $queue[] = array($id, 'urgent');
		foreach (array_keys($before) as $id) $queue[] = array($id, 'urgent');
		foreach (array_keys($heal) as $id) $queue[] = array($id, 'heal');

		$log = @fopen(dirname(rtrim($dir, '/')) . '/rest_api.log', 'a') ?: null;
		$dead = array();
		$done = 0;
		$reached = 0;
		$urgentLeft = false;
		foreach ($queue as $job) {
			list($id, $kind) = $job;
			if ($done >= $maxItems || $deadline - microtime(true) < SHOP_OFFER_MIN_CALL) {
				$result['left']++;
				$urgentLeft = $urgentLeft || $kind === 'urgent';
				continue;
			}
			if (shopOfferReconcileItem($id, $items[$id], $shops, $now, $deadline, $dir, $dead, $log, $result)) {
				$done++;
				if ($kind === 'heal') $result['healed']++;
			}
			if ($kind === 'urgent' && !isset($marked[$id])) {
				$reached = $id;
			}
		}
		if ($log) fclose($log);
		// the next tick goes on after the last item this one reached, when it could not reach them all
		$next = $urgentLeft ? $reached : 0;
		if ($next !== $cursor) {
			shopOfferWriteFile($dir . 'cursor.json', json_encode(array('after' => $next)));
		}
		if ($result['stateErrors']) {
			$result['result'] = 'state not writable';
		}
		return $result;
	}
}

if (!function_exists('shopOfferReconcileItem')) {
	/**
	 * One item in a tick: intent under the item's lock, the send without it, the answers under it again.
	 *
	 * @param int $id
	 * @param array<string, mixed> $item varer row.
	 * @param array<string, string> $shops
	 * @param int $now
	 * @param float $deadline
	 * @param string $dir
	 * @param array<string, bool> $dead Shops that did not answer in this tick; not called again in it.
	 * @param resource|null $log
	 * @param array $result Counts, updated.
	 * @return bool Whether a call went out.
	 */
	function shopOfferReconcileItem($id, array $item, array $shops, $now, $deadline, $dir, array &$dead, $log, array &$result) {
		$prices = shopPricesForItem($item, $now);
		$desired = shopOfferPair($prices);
		$note = $dir . 'uncertain-' . (int)$id;

		list($locked, $intent) = shopOfferWithItemLock($id, function () use ($id, $shops, $now, $prices, $desired, $note) {
			$read = shopOfferStateRead($id);
			$state = $read === false ? array('shops' => array(), 'reset' => true) : $read; // unreadable: no shop's price is known
			$noteAt = @filemtime($note);
			if ($noteAt !== false) {
				if ($noteAt > $now - SHOP_OFFER_FOREIGN_SECONDS) {
					return array('deferred' => true); // a send another path could not note may still be under way
				}
				$state = is_array($state) ? $state : array('shops' => array());
				$state['reset'] = true; // it has had time to arrive, with the normal prices: no shop's price is known
			}
			if ($state === null && !$prices['offer']) {
				return array('idle' => true);
			}
			$state = is_array($state) ? $state : array('shops' => array());
			$open = shopOfferForeignOpen($state, $now);
			$state['foreign'] = array('seq' => (int)($state['foreign']['seq'] ?? 0), 'open' => $open);
			if (!$state['shops'] && empty($state['reset']) && !$prices['offer']) {
				// noted by another path only, and no offer now: nothing was ever sent for it by the reconciler
				if ($open) return array('deferred' => true);
				if (!shopOfferStateWrite($id, null)) return array('stateError' => true);
				return array('idle' => true, 'untracked' => true);
			}
			if ($open) {
				return array('deferred' => true); // another path's send of the item is under way
			}
			if (!empty($state['reset'])) {
				foreach ($state['shops'] as $key => $entry) {
					$state['shops'][$key]['certain'] = false;
				}
			}
			$plan = shopOfferPlanItem($state, $shops, $desired, $now);
			$state = $plan['state'];
			unset($state['reset']);
			if (!$plan['send']) {
				$untrack = $plan['settled'] && !$prices['offer'];
				if (($untrack || $state != $read) && !shopOfferStateWrite($id, $untrack ? null : $state)) {
					return array('stateError' => true);
				}
				if ($noteAt !== false) @unlink($note);
				return array('idle' => true, 'untracked' => $untrack);
			}
			$was = array();
			foreach ($plan['send'] as $key => $why) {
				$was[$key] = $state['shops'][$key]['certain'];
				$state['shops'][$key]['certain'] = false; // written before the send: if the answers cannot be recorded, the shop is checked again
			}
			if (!shopOfferStateWrite($id, $state)) {
				return array('stateError' => true);
			}
			if ($noteAt !== false) @unlink($note);
			return array('send' => $plan['send'], 'was' => $was, 'seq' => (int)$state['foreign']['seq']);
		});
		if (!$locked) {
			$result['left']++;
			return false;
		}
		if (!empty($intent['stateError'])) {
			$result['stateErrors']++;
			shopOfferLogLoud('STATE NOT WRITABLE: nothing sent for item ' . $item['varenr'] . ' (vare_id ' . (int)$id . ')');
			return false;
		}
		if (!empty($intent['deferred'])) {
			$result['deferred']++;
			return false;
		}
		if (!empty($intent['idle'])) {
			if (!empty($intent['untracked'])) $result['untracked']++;
			return false;
		}

		$targets = array();
		foreach ($intent['send'] as $key => $why) {
			if (!isset($dead[$key])) $targets[$key] = $shops[$key];
		}
		$answers = array();
		if ($targets) {
			$shopId = 0;
			if ($b = db_fetch_array(db_select("select shop_id from shop_varer where saldi_id = '" . (int)$id . "'", __FILE__ . " linje " . __LINE__))) {
				$shopId = $b['shop_id'];
			}
			$answers = shopOfferSend($targets, shopPriceParams($item, $prices, $shopId), min($deadline, microtime(true) + SHOP_OFFER_CALL_SECONDS),
				$log, "shop offer tick vare_id " . (int)$id);
		}
		foreach ($intent['send'] as $key => $why) {
			if (!isset($answers[$key])) {
				$answers[$key] = array('ok' => false, 'untried' => true, 'error' => 'shop did not answer earlier in this tick');
			} elseif (empty($answers[$key]['ok']) && empty($answers[$key]['untried']) && (int)$answers[$key]['httpCode'] === 0) {
				$dead[$key] = true; // a timeout or no connection: the rest of the tick does not wait for this shop again
			}
		}

		list($locked, $outcomes) = shopOfferWithItemLock($id, function () use ($id, $now, $prices, $desired, $intent, $answers, $note) {
			$state = shopOfferStateRead($id);
			if (!is_array($state)) {
				$state = array('shops' => array(), 'reset' => true);
			}
			$overlapped = (int)($state['foreign']['seq'] ?? 0) !== $intent['seq'] || is_file($note);
			$outcomes = array();
			foreach ($answers as $key => $answer) {
				list($outcome, $entry, $gaveUpNow) = shopOfferRecordShop(shopOfferShopEntry($state, $key), $intent['was'][$key], $desired, $answer, $overlapped, $now);
				$state['shops'][$key] = $entry;
				$outcomes[$key] = array($outcome, $gaveUpNow, $entry);
			}
			if (!empty($state['dirty']) && (int)$state['dirty'] <= $now) {
				unset($state['dirty']);
			}
			$settled = true;
			foreach ($state['shops'] as $entry) {
				if (empty($entry['certain']) || !shopOfferPairSame($entry['confirmed'] ?? null, $desired)) $settled = false;
			}
			$untrack = $settled && !$prices['offer'] && !shopOfferForeignOpen($state, $now) && empty($state['reset']);
			return shopOfferStateWrite($id, $untrack ? null : $state) ? array($outcomes, $untrack) : false;
		});
		$result['sent']++;
		if (!$locked || $outcomes === false) {
			// the intent left every shop sent to uncertain, so the next tick sends again
			$result['stateErrors']++;
			shopOfferLogLoud('STATE NOT WRITABLE: the answers for item ' . $item['varenr'] . ' (vare_id ' . (int)$id . ') were not recorded; it is sent again');
			return true;
		}
		list($perShop, $untracked) = $outcomes;
		if ($untracked) $result['untracked']++;
		foreach ($perShop as $key => $o) {
			list($outcome, $gaveUpNow, $entry) = $o;
			if (isset($result[$outcome])) $result[$outcome]++;
			if ($gaveUpNow) {
				$result['gaveUp']++;
				shopOfferLogLoud('GAVE UP: item ' . $item['varenr'] . ' (vare_id ' . (int)$id . ') shop ' . $key . ' refused '
					. json_encode($desired) . ' ' . SHOP_OFFER_MAX_REFUSALS . ' times: ' . $entry['error'] . '; it is sent again when the item\'s prices change');
			}
		}
		return (bool)$targets;
	}
}

if (!function_exists('shopOfferPushStuck')) {
	/**
	 * The shops given up for an item's current pair (SHOP_OFFER_MAX_REFUSALS refusals of it), for a status page or support.
	 *
	 * @return array<int, array<string, array{pair: array|null, error: string}>> vare_id => shop key => the pair and last error.
	 */
	function shopOfferPushStuck() {
		$stuck = array();
		$dir = shopOfferPushDir(false);
		if ($dir === '') return $stuck;
		foreach ((array)@scandir($dir) as $name) {
			if (!preg_match('/^item-([0-9]+)\.json$/D', (string)$name, $m)) continue;
			$state = shopOfferStateRead((int)$m[1]);
			foreach ((array)(is_array($state) ? $state['shops'] : array()) as $key => $entry) {
				if (!empty($entry['gaveUp'])) {
					$stuck[(int)$m[1]][$key] = array('pair' => $entry['pending'] ?? null, 'error' => (string)($entry['error'] ?? ''));
				}
			}
		}
		return $stuck;
	}
}

if (!function_exists('shopOfferSend')) {
	/**
	 * Sends one call to some shops and returns when they have answered or the deadline has passed, whichever is first.
	 *
	 * The shared transport (shopApiRequest.php) takes whole seconds and adds up its timeouts one shop after the other when
	 * curl_multi is missing, so it cannot keep the tick's deadline. This one gives every call what is left of the deadline in
	 * milliseconds, in parallel with curl_multi, one after the other with curl, or with the curl command line.
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
				$result['untried'] = true;
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
				$results[$key]['error'] = 'the tick\'s time budget was used up';
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
