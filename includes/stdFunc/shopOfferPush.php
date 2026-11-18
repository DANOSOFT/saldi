<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/shopOfferPush.php --- ver 5.1.1 --- 2026-11-18 ---
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
// 20261117 CL/LH Created (Havemøbelland, Black Week): keeps the webshops (grupper API box4-box6) on an item's effective prices while it has an offer, and on the normal prices afterwards.
//                One sender only: shopOfferReconcileTick(), run by an authorised api/rest_api.php?action=shop_offer_tick call from a scheduler, one tick at a time (a non-blocking lock), within 25 seconds; new prices before repeats, the longest overdue first.
//                The shops' price call has no version and the last write wins, so delivery is never taken as proven: after every event (the prices change, an item gets an offer, a send fails, is refused, is cut off or is overtaken) the item's current pair is sent to every shop again after about 2, 5, 15 and 60 minutes, every 3 hours while an offer is active, and at 6, 12, 18 and 24 hours afterwards.
//                An item stays tracked until 24 hours after its last event with no offer active, so a request that arrives late (assumed within 10 minutes) is always overwritten; per shop the JSON state keeps the pair the shop last accepted (bookkeeping) and the retry and refusal counters of the pair owed.
//                The intent is written before a send, and nothing is sent that cannot be recorded; the tick's database reads are bounded in time (Postgres statement_timeout and lock_timeout), so a held table lock ends the tick with "database busy" within its budget.
//                The item card, the stock sync and the bulk sync send a tracked item the pair each shop last accepted; when that is not the item's current pair they mark an event, so the reconciler re-sends the current pair after them.
//                Codex verification 4: every path looks the pair up at the moment it sends (shopOfferForeignAtSend() for document sends, the bulk sync re-reads the item), so no send carries a pair read earlier; a bulk sync's sends are events for the items they touch.
//                A refusing shop is never given up: it gets the current pair again on the schedule and at its back-off, at most 3 hours after each refusal, logged each time, for as long as it refuses; a refusal applies nothing, so it is no event for the other shops.
//                leftAt is when a request actually left, on the tick's clock; an item is untracked only once its last request left more than T_late plus a minute (more than a tick's budget) ago.
// 20261118 CL/LH Codex verification 5: one protocol for every send of an item's prices by another path (item card, stock sync, document sync, bulk sync): its queries first, then a short critical section under the item's lock (shopOfferForeignClaim(): read the state, choose each shop's pair, write an in-flight marker), the call at once (shopOfferForeignReady() takes a claim older than 2 minutes again), then an event (shopOfferForeignDone()).
//                The reconciler never untracks an item while one of its markers is younger than T_late plus 2 minutes; an expired marker is an event. No query runs between a path's state read and its call (the VAT lookup no longer runs there at all).
//                shopOfferTestPause() is inert outside the stall tests, which hold each path at every point between its first read and its call.

if (!defined('SHOP_OFFER_ALERT_REFUSALS')) {
	define('SHOP_OFFER_ALERT_REFUSALS', 8);    // refusals of one price pair after which a shop counts as stuck (still retried)
	define('SHOP_OFFER_REFUSAL_BACKOFF', 10800); // most a refusing shop waits for its next try: 3 hours
	define('SHOP_OFFER_DISPATCH_MARGIN', 60);  // added to T_late before untracking: more than a tick's budget
	define('SHOP_OFFER_MIN_CALL', 0.2);        // seconds a call or a query needs at least; with less left the tick stops
	define('SHOP_OFFER_CALL_SECONDS', 10);     // most one call of the reconciler may take
	define('SHOP_OFFER_TICK_SECONDS', 25);     // most one tick may take
	define('SHOP_OFFER_LATE_SECONDS', 600);    // T_late: a request is assumed to be applied within 10 minutes of being sent, or never
	define('SHOP_OFFER_ACTIVE_EVERY', 10800);  // while an offer is active, its pair is sent again every 3 hours
	define('SHOP_OFFER_QUIET_SECONDS', 86400); // an item is tracked until 24 hours after its last event
	define('SHOP_OFFER_DB_LOCK_SECONDS', 5);   // most a tick waits for a database lock
	define('SHOP_OFFER_LOCK_WAIT', 0.2);       // most a sender waits for an item's state lock
	define('SHOP_OFFER_LOCK_STRIPES', 64);     // item locks: one file per stripe, not per item
}
if (!defined('SHOP_OFFER_FOREIGN_MARGIN')) {
	define('SHOP_OFFER_FOREIGN_MARGIN', 120);  // another path sends within this of its claim (else it claims again); a marker lives T_late plus this
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

if (!function_exists('shopOfferBackoff')) {
	/**
	 * Seconds to wait after the n-th failure or refusal of one pair: 1, 2, 4 ... minutes, at most $cap.
	 *
	 * @param int $n
	 * @param int $cap Half an hour for failures, SHOP_OFFER_REFUSAL_BACKOFF (3 hours) for refusals.
	 * @return int
	 */
	function shopOfferBackoff($n, $cap = 1800) {
		return (int)min((int)$cap, 60 * pow(2, max(0, min(20, (int)$n) - 1)));
	}
}

if (!function_exists('shopOfferForeignParams')) {
	/**
	 * A call's parameters with the pair shopOfferForeignClaim() gave the shop in $slot, if any.
	 *
	 * @param array<string, mixed> $params With salesPrice and retailPrice.
	 * @param array|null $handle From shopOfferForeignClaim().
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
	 * @param array|null $handle From shopOfferForeignClaim().
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


if (!function_exists('shopOfferSchedule')) {
	/**
	 * The re-send times after an event, in seconds: soon after it, and in the tail when no offer is active.
	 *
	 * @return array{soon: list<int>, tail: list<int>}
	 */
	function shopOfferSchedule() {
		return array('soon' => array(0, 120, 300, 900, 3600), 'tail' => array(21600, 43200, 64800, 86400));
	}
}

if (!function_exists('shopOfferNextSend')) {
	/**
	 * The next time a shop is owed the item's current pair: the first scheduled time after its last send.
	 *
	 * The schedule runs from the item's last event (the anchor): at it and 2, 5, 15 and 60 minutes after it; then, while an
	 * offer is active, every 3 hours; otherwise 6, 12, 18 and 24 hours after it, and then nothing.
	 *
	 * @param int $anchor The item's last event.
	 * @param int $lastSent When the reconciler last sent this shop the item (0: never).
	 * @param bool $offerActive
	 * @return int|null Null when nothing more is scheduled.
	 */
	function shopOfferNextSend($anchor, $lastSent, $offerActive) {
		$anchor = (int)$anchor;
		$lastSent = (int)$lastSent;
		$schedule = shopOfferSchedule();
		foreach ($schedule['soon'] as $offset) {
			if ($anchor + $offset > $lastSent) return $anchor + $offset;
		}
		if ($offerActive) {
			$base = $anchor + end($schedule['soon']);
			return $base + (intdiv(max(0, $lastSent - $base), SHOP_OFFER_ACTIVE_EVERY) + 1) * SHOP_OFFER_ACTIVE_EVERY;
		}
		foreach ($schedule['tail'] as $offset) {
			if ($anchor + $offset > $lastSent) return $anchor + $offset;
		}
		return null;
	}
}

if (!function_exists('shopOfferEvent')) {
	/**
	 * Records an event on an item's state: its schedule starts again from the event.
	 *
	 * @param array $state
	 * @param int $at When the event happened.
	 * @param string $why
	 * @return void
	 */
	function shopOfferEvent(array &$state, $at, $why) {
		if ((int)$at >= (int)($state['anchor'] ?? 0)) {
			$state['anchor'] = (int)$at;
			$state['event'] = (string)$why;
		}
		$state['events'] = (int)($state['events'] ?? 0) + 1;
	}
}

if (!function_exists('shopOfferShopEntry')) {
	/**
	 * One shop's entry in an item's state, with every field present.
	 *
	 * confirmed: the pair the shop last accepted from the reconciler (bookkeeping: a request that arrives late can still
	 * change it); okAt: when; sentAt: the tick (its clock at the start) that last sent the shop the item, which the schedule
	 * counts from; leftAt: when that request actually left, which untracking counts from; pending: the pair owed, which
	 * attempts, refusals, retryAt and error belong to.
	 *
	 * @param array|null $state
	 * @param string $key From shopOfferShopKey().
	 * @return array{confirmed: array|null, okAt: int, sentAt: int, leftAt: int, pending: array|null, attempts: int, refusals: int, retryAt: int, error: string}
	 */
	function shopOfferShopEntry($state, $key) {
		$entry = (is_array($state) && isset($state['shops'][$key]) && is_array($state['shops'][$key])) ? $state['shops'][$key] : array();
		return array(
			'confirmed' => (isset($entry['confirmed']) && is_array($entry['confirmed'])) ? $entry['confirmed'] : null,
			'okAt'      => (int)($entry['okAt'] ?? 0),
			'sentAt'    => (int)($entry['sentAt'] ?? 0),
			'leftAt'    => (int)max((int)($entry['leftAt'] ?? 0), (int)($entry['sentAt'] ?? 0)),
			'pending'   => (isset($entry['pending']) && is_array($entry['pending'])) ? $entry['pending'] : null,
			'attempts'  => (int)($entry['attempts'] ?? 0),
			'refusals'  => (int)($entry['refusals'] ?? 0),
			'retryAt'   => (int)($entry['retryAt'] ?? 0),
			'error'     => (string)($entry['error'] ?? ''),
		);
	}
}

if (!function_exists('shopOfferPlanItem')) {
	/**
	 * What the reconciler owes an item's shops now. A desired pair other than the one the state planned for is an event; the
	 * counters belong to the pair owed, so a new pair starts with none. Entries of shops no longer configured are dropped.
	 *
	 * @param array $state
	 * @param array<string, string> $shops Shop key => endpoint (shopOfferPushEndpoints()).
	 * @param array{sales: string|null, retail: string|null} $desired
	 * @param bool $offerActive
	 * @param int $now
	 * @return array{state: array, send: array<string, bool>, next: int|null, fresh: bool, urgent: bool, refusing: list<string>, untrack: bool}
	 *         send: the shops owed a send now; next: the earliest time any shop is owed one; fresh: a shop owed a send did not
	 *         last accept the current pair (new prices, which go before repeats); urgent: within the hour after the last event;
	 *         refusing: shops that refused the current pair SHOP_OFFER_ALERT_REFUSALS times or more (still retried);
	 *         untrack: 24 hours have passed since the last event with no offer, every re-send is done, no request of the
	 *         reconciler can still arrive (its last dispatch is older than T_late plus SHOP_OFFER_DISPATCH_MARGIN), and every shop
	 *         last accepted the current pair, and no in-flight marker of another path is open ($openUntil). A shop whose last
	 *         answer was not an acceptance is retried at its back-off for as long as that lasts, after the schedule has run out too.
	 * @param int $openUntil When the item's youngest in-flight marker of another path expires (shopOfferInflightCheck()); 0: none.
	 */
	function shopOfferPlanItem(array $state, array $shops, array $desired, $offerActive, $now, $openUntil = 0) {
		$now = (int)$now;
		if (!isset($state['desired']) || !shopOfferPairSame($state['desired'], $desired)) {
			shopOfferEvent($state, $now, isset($state['desired']) ? 'the prices changed' : 'tracked');
			$state['desired'] = $desired;
		}
		$state['offer'] = (bool)$offerActive;
		$anchor = (int)$state['anchor'];
		$out = array('state' => $state, 'send' => array(), 'next' => null, 'fresh' => false, 'urgent' => false, 'refusing' => array(), 'untrack' => false);
		$quiet = !$offerActive && $now >= $anchor + SHOP_OFFER_QUIET_SECONDS && empty($state['intent']) && (int)$openUntil <= $now;
		$out['state']['shops'] = array();
		foreach ($shops as $key => $endpoint) {
			$entry = shopOfferShopEntry($state, $key);
			if (!shopOfferPairSame($entry['pending'], $desired)) {
				$entry['pending'] = $desired;
				$entry['attempts'] = $entry['refusals'] = $entry['retryAt'] = 0;
				$entry['error'] = '';
			}
			$out['state']['shops'][$key] = $entry;
			if ($entry['refusals'] >= SHOP_OFFER_ALERT_REFUSALS) {
				$out['refusing'][] = $key;
			}
			$next = shopOfferNextSend($anchor, $entry['sentAt'], $offerActive);
			if ($next === null && ($entry['attempts'] || $entry['refusals'])) {
				$next = $entry['retryAt']; // the schedule has run out, the shop has not accepted the pair: keep trying
			}
			if ($next !== null) {
				// A failed send waits for the later of the schedule and its back-off. A refusing shop is tried at the earlier of them,
				// so at most 3 hours after its last refusal, also where the schedule's next send is 6 hours away.
				$at = $entry['refusals'] ? min($next, $entry['retryAt']) : max($next, $entry['retryAt']);
				$out['next'] = $out['next'] === null ? $at : min($out['next'], $at);
				if ($at <= $now) {
					$out['send'][$key] = true;
					$out['fresh'] = $out['fresh'] || !shopOfferPairSame($entry['confirmed'], $desired);
					$out['urgent'] = $out['urgent'] || $next <= $anchor + 3600;
				}
			}
			if ($next !== null || $entry['attempts'] || $entry['refusals'] || $entry['leftAt'] + SHOP_OFFER_LATE_SECONDS + SHOP_OFFER_DISPATCH_MARGIN > $now
				|| !shopOfferPairSame($entry['confirmed'], $desired)) {
				$quiet = false;
			}
		}
		$out['untrack'] = $quiet;
		return $out;
	}
}

if (!function_exists('shopOfferRecordShop')) {
	/**
	 * Records one shop's answer to the reconciler's send of $pair. Accepted: the shop's bookkeeping says it has the pair.
	 * Refused (an answer that is not a transport failure): nothing was applied; retried at the latest after its back-off (up
	 * to 3 hours), for as long as the shop refuses. A transport failure or no answer: retried with back-off up to half an hour, and an event for
	 * the caller (the request may still be applied).
	 *
	 * @param array $entry
	 * @param array{sales: string|null, retail: string|null} $pair
	 * @param array $result From shopOfferSend().
	 * @param int $now
	 * @return array{0: string, 1: array, 2: bool} 'ok', 'refused' or 'failed'; the entry; whether the shop has refused this
	 *                                             pair SHOP_OFFER_ALERT_REFUSALS times just now (it is still retried).
	 */
	function shopOfferRecordShop(array $entry, array $pair, array $result, $now) {
		$now = (int)$now;
		if (!empty($result['ok'])) {
			$entry['confirmed'] = $pair;
			$entry['okAt'] = $now;
			$entry['attempts'] = $entry['refusals'] = $entry['retryAt'] = 0;
			$entry['error'] = '';
			return array('ok', $entry, false);
		}
		$entry['error'] = (string)($result['error'] ?? '');
		if ((int)($result['httpCode'] ?? 0) > 0 && !shopSyncWorthRetrying($result)) {
			$entry['refusals']++;
			$entry['retryAt'] = $now + shopOfferBackoff($entry['refusals'], SHOP_OFFER_REFUSAL_BACKOFF);
			return array('refused', $entry, $entry['refusals'] === SHOP_OFFER_ALERT_REFUSALS);
		}
		$entry['attempts']++;
		$entry['retryAt'] = $now + shopOfferBackoff($entry['attempts']);
		return array('failed', $entry, false);
	}
}

if (!function_exists('shopOfferBoundedQuery')) {
	/**
	 * A query of the reconciler's tick, bounded in time: it waits at most SHOP_OFFER_DB_LOCK_SECONDS for a lock and never
	 * runs past the tick's deadline ($GLOBALS['shopOfferBoundedUntil']). Unlike db_select() it neither alerts nor mails on
	 * an error: it throws ShopOfferDatabaseBusy, and the tick ends with "database busy".
	 * Postgres: statement_timeout and lock_timeout. MySQL/MariaDB: innodb_lock_wait_timeout and max_execution_time
	 * (MySQL) or max_statement_time (MariaDB), each only where the server knows it (not tested here).
	 *
	 * @param string $sql
	 * @return resource|object Query result for db_fetch_array().
	 */
	function shopOfferBoundedQuery($sql) {
		global $connection, $db_type;
		$left = (float)($GLOBALS['shopOfferBoundedUntil'] ?? 0) - microtime(true);
		if ($left < SHOP_OFFER_MIN_CALL) {
			throw new ShopOfferDatabaseBusy('the tick\'s time budget is used up');
		}
		shopOfferDbTimeouts((int)floor(($left - 0.1) * 1000));
		$mysql = in_array(strtolower((string)$db_type), array('mysql', 'mysqli'), true);
		$res = $mysql ? @mysqli_query($connection, $sql) : @pg_query($connection, $sql);
		if ($res === false) {
			throw new ShopOfferDatabaseBusy(trim((string)($mysql ? mysqli_error($connection) : pg_last_error($connection))));
		}
		return $res;
	}
}

if (!class_exists('ShopOfferDatabaseBusy')) {
	/** A query of the reconciler's tick could not finish within the tick's budget (a held lock, a slow statement). */
	class ShopOfferDatabaseBusy extends RuntimeException {}
}

if (!function_exists('shopOfferDbTimeouts')) {
	/**
	 * Sets the session's statement and lock timeouts for the tick's queries, only when they must shrink, or back to the
	 * server's defaults (null).
	 *
	 * @param int|null $ms Most milliseconds a statement may take.
	 * @return void
	 */
	function shopOfferDbTimeouts($ms) {
		global $connection, $db_type;
		static $current = null;
		$mysql = in_array(strtolower((string)$db_type), array('mysql', 'mysqli'), true);
		if ($ms === null) {
			if ($current !== null && $connection) {
				if ($mysql) {
					@mysqli_query($connection, 'SET SESSION innodb_lock_wait_timeout = DEFAULT');
					@mysqli_query($connection, 'SET SESSION max_execution_time = DEFAULT');
					@mysqli_query($connection, 'SET SESSION max_statement_time = DEFAULT');
				} else {
					@pg_query($connection, 'SET statement_timeout = DEFAULT');
					@pg_query($connection, 'SET lock_timeout = DEFAULT');
				}
			}
			$current = null;
			return;
		}
		$ms = max(100, (int)$ms);
		if ($current !== null && $ms >= $current - 500) {
			return;
		}
		$lock = min($ms, SHOP_OFFER_DB_LOCK_SECONDS * 1000);
		if ($mysql) {
			@mysqli_query($connection, 'SET SESSION innodb_lock_wait_timeout = ' . max(1, (int)ceil($lock / 1000)));
			@mysqli_query($connection, 'SET SESSION max_execution_time = ' . $ms);
			@mysqli_query($connection, 'SET SESSION max_statement_time = ' . sprintf('%.3F', $ms / 1000));
		} else {
			@pg_query($connection, 'SET statement_timeout = ' . $ms);
			@pg_query($connection, 'SET lock_timeout = ' . $lock);
		}
		$current = $ms;
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
			$r = shopOfferFetchRow("select box4, box5, box6 from grupper where art='API'");
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

// ------------------------------------------------------------------ the other paths: item card, stock sync, document sync, bulk sync
//
// Every send of an item's prices by a path other than the reconciler follows one protocol:
//   1. slow work first: the path's queries and its payload, before anything below (no query from here to the HTTP call);
//   2. shopOfferForeignClaim(), a short critical section under the item's lock: read the state, choose each shop's pair (the
//      pair the shop last accepted when the item is tracked, today's prices when not), write an in-flight marker
//      {path, startedAt}, release the lock;
//   3. shopOfferForeignReady() right before the HTTP call (a claim older than SHOP_OFFER_FOREIGN_MARGIN is taken again), then
//      the call at once;
//   4. shopOfferForeignDone() after the send, whatever its outcome: an event for the item when it is tracked, has a price offer
//      running or to come, or a marker was written; the marker goes.
// The reconciler never untracks an item while one of its markers is younger than T_late plus SHOP_OFFER_FOREIGN_MARGIN; an
// older marker has expired, which is an event (the item is sent again before it can be untracked), so a path that dies
// between its claim and its record leaves a re-send behind, not a gap.

if (!function_exists('shopOfferHasOfferFields')) {
	/**
	 * Whether an item has a price offer that runs or is still to come, by the columns the reconciler selects items by.
	 *
	 * @param array<string, mixed> $item varer row with specialtype, special_price and special_to_date.
	 * @param int $now
	 * @return bool
	 */
	function shopOfferHasOfferFields(array $item, $now) {
		return (string)($item['specialtype'] ?? '') === 'price' && (float)($item['special_price'] ?? 0) != 0
			&& (string)($item['special_to_date'] ?? '') >= date('Y-m-d', strtotime('yesterday', (int)$now));
	}
}

if (!function_exists('shopOfferInflightRead')) {
	/**
	 * An item's in-flight markers: sends of other paths between their claim and their record.
	 *
	 * @param int $vareId
	 * @return array<string, array{path: string, startedAt: int, pid: int}>|false Empty when there are none; false when the file
	 *                                                                             cannot be read as markers.
	 */
	function shopOfferInflightRead($vareId) {
		$dir = shopOfferPushDir(false);
		$path = $dir === '' ? '' : $dir . 'inflight-' . (int)$vareId . '.json';
		if ($path === '' || !is_file($path)) {
			return array();
		}
		$raw = @file_get_contents($path);
		$data = is_string($raw) ? json_decode($raw, true) : null;
		return is_array($data) ? $data : false;
	}
}

if (!function_exists('shopOfferInflightWrite')) {
	/**
	 * Writes an item's in-flight markers (the file goes when there are none). Under the item's lock only.
	 *
	 * @param int $vareId
	 * @param array<string, array> $markers
	 * @return bool
	 */
	function shopOfferInflightWrite($vareId, array $markers) {
		$dir = shopOfferPushDir(false);
		if ($dir === '') {
			return false;
		}
		$path = $dir . 'inflight-' . (int)$vareId . '.json';
		if (!$markers) {
			return !is_file($path) || @unlink($path);
		}
		$json = json_encode($markers, JSON_UNESCAPED_SLASHES);
		return $json !== false && shopOfferWriteFile($path, $json);
	}
}

if (!function_exists('shopOfferInflightCheck')) {
	/**
	 * Splits an item's markers into open ones (younger than T_late plus SHOP_OFFER_FOREIGN_MARGIN: the item is not untracked
	 * until openUntil) and expired ones (an event; they are dropped).
	 *
	 * @param array|false $markers From shopOfferInflightRead().
	 * @param int $now
	 * @return array{openUntil: int, expired: list<string>, keep: array<string, array>} expired: the paths of the expired markers.
	 */
	function shopOfferInflightCheck($markers, $now) {
		$out = array('openUntil' => 0, 'expired' => array(), 'keep' => array());
		if (!is_array($markers)) {
			$out['expired'][] = 'unreadable markers';
			return $out;
		}
		foreach ($markers as $token => $marker) {
			$until = (int)($marker['startedAt'] ?? 0) + SHOP_OFFER_LATE_SECONDS + SHOP_OFFER_FOREIGN_MARGIN;
			if ($until > (int)$now) {
				$out['keep'][$token] = $marker;
				$out['openUntil'] = max($out['openUntil'], $until);
			} else {
				$out['expired'][] = (string)($marker['path'] ?? '?');
			}
		}
		return $out;
	}
}

if (!function_exists('shopOfferForeignClaim')) {
	/**
	 * Step 2 of the protocol above: the critical section of a send of an item's prices by a path other than the reconciler.
	 * Call it after the path's queries, right before the send. No query runs in it.
	 *
	 * Null when the account has no offer state (the reconciler has never run, or no webshop): the path sends today's prices
	 * and there is nothing to record. An item that is not tracked and has no price offer running or to come costs one file
	 * check and keeps today's prices, without lock or marker. Otherwise, under the item's lock: each shop's pair (the pair it
	 * last accepted when the item is tracked, else today's prices) and an in-flight marker. If the lock or the marker fails,
	 * the path sends today's prices and leaves a lock-free note (uncertain-<id>), which is an event, before it sends.
	 *
	 * @param string $path 'card', 'stock', 'document' or 'bulk'.
	 * @param int $vareId
	 * @param array<string, mixed> $item varer row read before: salgspris, retail_price and the offer columns (shopOfferColumns()).
	 * @param array<int|string, string> $endpoints Slot => endpoint the send goes to.
	 * @return array{path: string, vare_id: int, item: array, endpoints: array, pairs: array<int|string, array|null>, token: string|null, offer: bool, claimedAt: float}|null
	 */
	function shopOfferForeignClaim($path, $vareId, array $item, array $endpoints) {
		$vareId = (int)$vareId;
		$dir = shopOfferPushDir(false);
		if ($dir === '' || !$endpoints) {
			return null;
		}
		$handle = array('path' => (string)$path, 'vare_id' => $vareId, 'item' => $item, 'endpoints' => $endpoints, 'pairs' => array(),
			'token' => null, 'offer' => shopOfferHasOfferFields($item, time()), 'claimedAt' => microtime(true));
		if (!$handle['offer'] && !is_file($dir . 'item-' . $vareId . '.json')) {
			return $handle; // today's prices; after the send only a look whether the item got tracked meanwhile
		}
		$token = getmypid() . '-' . bin2hex(random_bytes(6));
		list($locked, $pairs) = shopOfferWithItemLock($vareId, function () use ($vareId, $endpoints, $token, $path) {
			$state = shopOfferStateRead($vareId);
			shopOfferTestPause($path, 'locked', $vareId);
			$pairs = array();
			foreach ($endpoints as $slot => $endpoint) {
				$pairs[$slot] = is_array($state) ? shopOfferShopEntry($state, shopOfferShopKey($endpoint))['confirmed'] : null;
			}
			$markers = shopOfferInflightRead($vareId);
			if (!is_array($markers)) {
				return false;
			}
			$markers[$token] = array('path' => (string)$path, 'startedAt' => time(), 'pid' => (int)getmypid());
			return shopOfferInflightWrite($vareId, $markers) ? $pairs : false;
		});
		if (!$locked || $pairs === false) {
			$noted = @touch($dir . 'uncertain-' . $vareId);
			shopOfferLogLoud('STATE NOT WRITABLE: a send of item vare_id ' . $vareId . ' by the ' . $path . ' could not be marked ('
				. ($locked ? 'marker not written' : 'lock busy') . '); it carries today\'s prices'
				. ($noted ? ' and left a note for the reconciler' : ', and the note for the reconciler failed too'));
			return $handle;
		}
		$handle['pairs'] = $pairs;
		$handle['token'] = $token;
		shopOfferTestPause($path, 'marked', $vareId);
		return $handle;
	}
}

if (!function_exists('shopOfferForeignReady')) {
	/**
	 * Step 3: right before the HTTP call. A claim older than SHOP_OFFER_FOREIGN_MARGIN (the path stalled after it) is
	 * recorded and taken again, so a send leaves within the margin of its claim's state read and marker.
	 *
	 * @param array|null $handle From shopOfferForeignClaim().
	 * @param float|null $at microtime(true); for tests.
	 * @return array|null The handle to send with.
	 */
	function shopOfferForeignReady($handle, $at = null) {
		if (!is_array($handle) || ($at ?? microtime(true)) - $handle['claimedAt'] < SHOP_OFFER_FOREIGN_MARGIN) {
			return $handle;
		}
		shopOfferForeignDone($handle);
		return shopOfferForeignClaim($handle['path'], $handle['vare_id'], $handle['item'], $handle['endpoints']);
	}
}

if (!function_exists('shopOfferForeignDone')) {
	/**
	 * Step 4: after the send, whatever its outcome. An event for the item (foreign.staleAt; the reconciler sends the current
	 * pair after it and keeps the item tracked for 24 hours) when a marker was written, the item is tracked now, or it has a
	 * price offer running or to come; the marker goes. If the lock is busy or the state cannot be written, a lock-free note
	 * (uncertain-<id>) is the event, and the marker is left to expire, which is an event too.
	 *
	 * @param array|null $handle From shopOfferForeignClaim() / shopOfferForeignReady().
	 * @return void
	 */
	function shopOfferForeignDone($handle) {
		if (!is_array($handle)) {
			return;
		}
		$vareId = (int)$handle['vare_id'];
		$dir = shopOfferPushDir(false);
		if ($dir === '' || ($handle['token'] === null && !$handle['offer'] && !is_file($dir . 'item-' . $vareId . '.json'))) {
			return; // an item nobody tracks and without an offer: nothing to record
		}
		$now = time();
		$token = $handle['token'];
		list($locked, $written) = shopOfferWithItemLock($vareId, function () use ($vareId, $now, $token) {
			$state = shopOfferStateRead($vareId);
			if (!is_array($state)) {
				$state = array('shops' => array(), 'reset' => true);
			}
			$state['foreign']['staleAt'] = max((int)($state['foreign']['staleAt'] ?? 0), $now);
			if (!shopOfferStateWrite($vareId, $state)) {
				return false;
			}
			$markers = $token === null ? array() : shopOfferInflightRead($vareId);
			if (is_array($markers) && isset($markers[$token])) {
				unset($markers[$token]);
				shopOfferInflightWrite($vareId, $markers);
			}
			return true;
		});
		if (!$locked || !$written) {
			$noted = @touch($dir . 'uncertain-' . $vareId);
			shopOfferLogLoud('STATE NOT WRITABLE: the send of item vare_id ' . $vareId . ' by the ' . $handle['path'] . ' could not be recorded ('
				. ($locked ? 'write failed' : 'lock busy') . '); ' . ($noted ? 'a note for the reconciler was left' : 'the note failed too; its marker expires and is an event then'));
		}
	}
}

if (!function_exists('shopOfferTestPause')) {
	/**
	 * A pause for the stall tests only (ShopOfferPushTest::testStallMatrix()); inert unless the environment variable
	 * SALDI_SHOP_OFFER_STALL is "<path>:<point>:<vare_id, 0 for any>:<file prefix>". Then, the first time that path reaches that
	 * point for that item, it creates <prefix>.held and waits for <prefix>.go (at most 10 minutes).
	 *
	 * @param string $path 'card', 'stock', 'document', 'bulk' or 'tick'.
	 * @param string $point 'queried', 'locked', 'marked' or 'send'.
	 * @param int $vareId
	 * @return void
	 */
	function shopOfferTestPause($path, $point, $vareId = 0) {
		static $fired = false;
		$spec = getenv('SALDI_SHOP_OFFER_STALL');
		if ($fired || $spec === false || $spec === '') {
			return;
		}
		$parts = explode(':', $spec, 4);
		if (count($parts) !== 4 || $parts[0] !== (string)$path || $parts[1] !== (string)$point
			|| ((int)$parts[2] && (int)$vareId && (int)$parts[2] !== (int)$vareId)) {
			return;
		}
		$fired = true;
		@touch($parts[3] . '.held');
		for ($i = 0; $i < 12000 && !is_file($parts[3] . '.go'); $i++) {
			usleep(50000);
		}
	}
}

// ------------------------------------------------------------------ the reconciler

if (!function_exists('shopOfferReconcileTick')) {
	/**
	 * One tick of the only sender of offer-derived prices: api/rest_api.php?action=shop_offer_tick, called by a scheduler.
	 *
	 * Returns 'busy' at once when another tick runs, 'no api' without webshops, 'state not writable' when nothing could be
	 * recorded (and then sends nothing), 'database busy' when a query could not finish within the budget. Otherwise it records
	 * the events it finds and, within the budget, sends what is owed: first new prices (a shop that did not last accept the
	 * current pair), then the re-sends within the hour after an event, then the rest, the longest overdue first.
	 *
	 * @param array{now?: int, budget?: float, maxItems?: int} $options now and maxItems for tests; budget at most 25 seconds.
	 * @return array<string, int|float|string>
	 */
	function shopOfferReconcileTick(array $options = array()) {
		$started = microtime(true);
		$budget = min((float)SHOP_OFFER_TICK_SECONDS, max(0.5, (float)($options['budget'] ?? SHOP_OFFER_TICK_SECONDS)));
		$deadline = $started + $budget;
		$now = isset($options['now']) ? (int)$options['now'] : time();
		$result = array('result' => 'ok', 'tracked' => 0, 'due' => 0, 'sent' => 0, 'calls' => 0, 'accepted' => 0, 'refused' => 0,
			'failed' => 0, 'events' => 0, 'left' => 0, 'stuck' => 0, 'untracked' => 0, 'stateErrors' => 0, 'elapsed' => 0);
		$GLOBALS['shopOfferBoundedUntil'] = $deadline;
		try {
			$shops = shopOfferPushEndpoints();
			if (!$shops) {
				$result['result'] = 'no api';
			} else {
				$dir = shopOfferPushDir(true);
				$lock = ($dir !== '' && is_writable($dir)) ? @fopen($dir . 'tick.lock', 'c') : false;
				if (!$lock) {
					shopOfferLogLoud('STATE NOT WRITABLE: the tick sent nothing (' . ($dir === '' ? 'no state directory' : $dir) . ')');
					$result['result'] = 'state not writable';
					$result['stateErrors'] = 1;
				} elseif (!flock($lock, LOCK_EX | LOCK_NB)) {
					fclose($lock);
					$result['result'] = 'busy';
				} else {
					try {
						$result = shopOfferReconcileRun($now, $deadline, $shops, $dir, array('started' => $started) + $options, $result);
					} finally {
						flock($lock, LOCK_UN);
						fclose($lock);
					}
				}
			}
		} catch (ShopOfferDatabaseBusy $e) {
			$result['result'] = 'database busy';
			$result['error'] = substr($e->getMessage(), 0, 200);
			shopOfferLogLoud('DATABASE BUSY: the tick stopped after ' . round(microtime(true) - $started, 3) . ' s: ' . $result['error']);
		} finally {
			unset($GLOBALS['shopOfferBoundedUntil']);
			shopOfferDbTimeouts(null);
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
		$maxItems = isset($options['maxItems']) ? (int)$options['maxItems'] : PHP_INT_MAX;

		// What is tracked, what another path noted without its lock, and temporary files a killed writer left.
		$tracked = $notes = $inflight = array();
		foreach ((array)@scandir($dir) as $name) {
			if (preg_match('/^item-([0-9]+)\.json$/D', (string)$name, $m)) {
				$tracked[(int)$m[1]] = true;
			} elseif (preg_match('/^uncertain-([0-9]+)$/D', (string)$name, $m)) {
				$notes[(int)$m[1]] = true;
			} elseif (preg_match('/^inflight-([0-9]+)\.json$/D', (string)$name, $m)) {
				$inflight[(int)$m[1]] = true;
			} elseif (substr((string)$name, -4) === '.new' && (int)@filemtime($dir . $name) < $now - 3600) {
				@unlink($dir . $name);
			}
		}
		$result['tracked'] = count($tracked);
		$since = date('Y-m-d', strtotime('yesterday', $now));
		$known = array_keys($tracked + $notes + $inflight);
		$qtxt = "select id, varenr, varenr_alias, kostpris, salgspris, retail_price, m_type, m_rabat, colli_webfragt, stregkode, " . shopOfferColumns();
		$qtxt .= " from varer where (specialtype = 'price' and special_price <> 0 and special_to_date >= '$since')";
		if ($known) $qtxt .= " or id in (" . implode(',', array_map('intval', $known)) . ")";
		$qtxt .= " order by id";
		$items = array();
		$q = shopOfferBoundedQuery($qtxt);
		while ($row = db_fetch_array($q)) {
			$items[(int)$row['id']] = $row;
		}
		shopOfferTestPause('tick', 'queried', 0);
		foreach ($known as $id) {
			if (!isset($items[$id])) { // the item is gone
				shopOfferWithItemLock($id, function () use ($id, $dir) {
					shopOfferStateWrite($id, null);
					shopOfferInflightWrite($id, array());
					@unlink($dir . 'uncertain-' . $id);
				});
			}
		}

		// What is owed, from a read of the state; the decisions are taken again under each item's lock. New prices go first
		// (a shop that did not last accept the current pair), then the re-sends within the hour after an event, then the rest;
		// within each, the longest overdue first.
		$tidy = $fresh = $urgent = $routine = array();
		foreach ($items as $id => $item) {
			if (microtime(true) > $deadline - SHOP_OFFER_MIN_CALL) {
				$result['left']++;
				continue;
			}
			$state = isset($tracked[$id]) ? shopOfferStateRead($id) : null;
			$prices = shopPricesForItem($item, $now);
			if ($state === null && !$prices['offer'] && !isset($notes[$id]) && !isset($inflight[$id])) {
				continue; // not tracked and no offer now: today's prices, nothing owed
			}
			$preview = is_array($state) ? $state : array('shops' => array());
			$markers = isset($inflight[$id]) ? shopOfferInflightCheck(shopOfferInflightRead($id), $now) : array('openUntil' => 0, 'expired' => array());
			if ($state === false || isset($notes[$id]) || !empty($preview['intent']) || $markers['expired']
				|| (int)($preview['foreign']['staleAt'] ?? 0) > (int)($preview['foreign']['absorbed'] ?? 0)) {
				shopOfferEvent($preview, $now, 'pending');
			}
			$plan = shopOfferPlanItem($preview, $shops, shopOfferPair($prices), $prices['offer'], $now, $markers['openUntil']);
			$result['stuck'] += count($plan['refusing']);
			if ($plan['send']) {
				if ($plan['fresh']) $fresh[$id] = $plan['next'];
				elseif ($plan['urgent']) $urgent[$id] = $plan['next'];
				else $routine[$id] = $plan['next'];
			} elseif ($plan['untrack'] || $state === false || isset($notes[$id]) || $markers['expired'] || (is_array($state) && $plan['state']['anchor'] !== ($state['anchor'] ?? null))) {
				$tidy[$id] = $id; // record an event or stop tracking: no call
			}
		}
		asort($fresh);
		asort($urgent);
		asort($routine);
		$queue = array();
		foreach (array_keys($tidy) as $id) $queue[] = $id;
		foreach (array_keys($fresh) as $id) $queue[] = $id;
		foreach (array_keys($urgent) as $id) $queue[] = $id;
		foreach (array_keys($routine) as $id) $queue[] = $id;
		$result['due'] = count($fresh) + count($urgent) + count($routine);

		$log = @fopen(dirname(rtrim($dir, '/')) . '/rest_api.log', 'a') ?: null;
		$dead = array();
		$done = 0;
		foreach ($queue as $id) {
			if ($done >= $maxItems || $deadline - microtime(true) < SHOP_OFFER_MIN_CALL) {
				$result['left']++;
				continue;
			}
			if (shopOfferReconcileItem($id, $items[$id], $shops, $now, $deadline, $dir, $dead, $log, $result, (float)$options['started'])) {
				$done++;
			}
		}
		if ($log) fclose($log);
		if ($result['stateErrors']) {
			$result['result'] = 'state not writable';
		}
		return $result;
	}
}

if (!function_exists('shopOfferAbsorbEvents')) {
	/**
	 * Turns what happened to an item since the state was written into events: an unfinished send of an earlier tick (cut off
	 * or killed), a send by another path of a pair that was not the current one, a send another path could not mark.
	 *
	 * @param array $state
	 * @param int $now
	 * @param int|false $noteAt mtime of uncertain-<id>, or false.
	 * @return void
	 */
	function shopOfferAbsorbEvents(array &$state, $now, $noteAt) {
		if (!empty($state['intent'])) {
			shopOfferEvent($state, $now, 'a send was not finished');
			unset($state['intent']);
		}
		// each mark once; an event is never later than the tick's clock, or it would put the next send off
		$staleAt = (int)($state['foreign']['staleAt'] ?? 0);
		if ($staleAt > (int)($state['foreign']['absorbed'] ?? 0)) {
			shopOfferEvent($state, min($staleAt, (int)$now), 'another path sent an earlier pair');
			$state['foreign']['absorbed'] = $staleAt;
		}
		if ($noteAt !== false) {
			shopOfferEvent($state, min((int)$noteAt, (int)$now), 'another path sent without a mark');
		}
	}
}

if (!function_exists('shopOfferReconcileItem')) {
	/**
	 * One item in a tick: events and intent under the item's lock, the send without it, the answers under it again.
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
	 * @param float $started microtime(true) at the start of the tick, the moment $now stands for: a request's dispatch time
	 *                       on the tick's clock is $now plus the time since.
	 * @return bool Whether a call went out.
	 */
	function shopOfferReconcileItem($id, array $item, array $shops, $now, $deadline, $dir, array &$dead, $log, array &$result, $started) {
		$prices = shopPricesForItem($item, $now);
		$desired = shopOfferPair($prices);
		$note = $dir . 'uncertain-' . (int)$id;

		list($locked, $intent) = shopOfferWithItemLock($id, function () use ($id, $shops, $now, $prices, $desired, $note, $dead) {
			$read = shopOfferStateRead($id);
			shopOfferTestPause('tick', 'locked', $id);
			$noteAt = @filemtime($note);
			$inflight = shopOfferInflightRead($id);
			if ($read === null && !$prices['offer'] && $noteAt === false && $inflight === array()) {
				return array('idle' => true);
			}
			$state = is_array($read) ? $read : array('shops' => array());
			$events = (int)($state['events'] ?? 0);
			if ($read === false) {
				shopOfferEvent($state, $now, 'the state could not be read');
			}
			shopOfferAbsorbEvents($state, $now, $noteAt);
			// another path's sends: an open marker keeps the item tracked; an expired one is an event and goes
			$markers = shopOfferInflightCheck($inflight, $now);
			if ($markers['expired']) {
				shopOfferEvent($state, $now, 'a send by another path was not recorded in time (' . implode(', ', $markers['expired']) . ')');
				if (!shopOfferInflightWrite($id, $markers['keep'])) return array('stateError' => true);
			}
			$plan = shopOfferPlanItem($state, $shops, $desired, $prices['offer'], $now, $markers['openUntil']);
			$state = $plan['state'];
			unset($state['reset']);
			$newEvents = (int)($state['events'] ?? 0) - $events;
			$targets = array_diff_key($plan['send'], $dead);
			if (!$targets) {
				if ($plan['untrack']) {
					if (!shopOfferStateWrite($id, null)) return array('stateError' => true);
					if ($noteAt !== false) @unlink($note);
					return array('idle' => true, 'untracked' => true, 'events' => $newEvents);
				}
				if ($state != $read && !shopOfferStateWrite($id, $state)) return array('stateError' => true);
				if ($noteAt !== false) @unlink($note);
				return array('idle' => true, 'events' => $newEvents);
			}
			$was = array();
			foreach ($targets as $key => $yes) {
				$was[$key] = $state['shops'][$key]['sentAt'];
				$state['shops'][$key]['sentAt'] = $now;
			}
			// the intent: written before the send, so a send cut off or killed is found by the next tick as an event
			$state['intent'] = array('at' => $now, 'pair' => $desired, 'shops' => array_keys($targets));
			if (!shopOfferStateWrite($id, $state)) {
				return array('stateError' => true);
			}
			if ($noteAt !== false) @unlink($note);
			return array('send' => $targets, 'was' => $was, 'events' => $newEvents);
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
		$result['events'] += (int)($intent['events'] ?? 0);
		if (!empty($intent['idle'])) {
			if (!empty($intent['untracked'])) $result['untracked']++;
			return false;
		}
		shopOfferTestPause('tick', 'marked', $id);
		$shopId = 0;
		if ($b = db_fetch_array(shopOfferBoundedQuery("select shop_id from shop_varer where saldi_id = '" . (int)$id . "'"))) {
			$shopId = $b['shop_id'];
		}
		$targets = array();
		foreach ($intent['send'] as $key => $yes) {
			$targets[$key] = $shops[$key];
		}
		shopOfferTestPause('tick', 'send', $id);
		$answers = shopOfferSend($targets, shopPriceParams($item, $prices, $shopId), min($deadline, microtime(true) + SHOP_OFFER_CALL_SECONDS),
			$log, "shop offer tick vare_id " . (int)$id);
		foreach ($answers as $key => $answer) {
			if (empty($answer['ok']) && empty($answer['untried']) && (int)($answer['httpCode'] ?? 0) === 0) {
				$dead[$key] = true; // no answer: the rest of the tick does not wait for this shop again
			}
		}

		list($locked, $outcomes) = shopOfferWithItemLock($id, function () use ($id, $now, $desired, $intent, $answers, $note, $started) {
			$state = shopOfferStateRead($id);
			if (!is_array($state)) {
				$state = array('shops' => array());
				shopOfferEvent($state, $now, 'the state could not be read');
			}
			unset($state['intent']);
			$events = (int)($state['events'] ?? 0);
			$outcomes = array();
			foreach ($answers as $key => $answer) {
				$entry = shopOfferShopEntry($state, $key);
				if (!empty($answer['untried'])) {
					$entry['sentAt'] = (int)$intent['was'][$key]; // nothing went out: still owed
					$state['shops'][$key] = $entry;
					$outcomes[$key] = array('untried', false, $entry);
					continue;
				}
				// the schedule counts from the tick that read the pair; untracking from when the request actually left, on the tick's clock
				$entry['sentAt'] = (int)$now;
				$entry['leftAt'] = (int)$now + (int)ceil(max(0.0, (float)($answer['dispatchedAt'] ?? $started) - (float)$started));
				list($outcome, $entry, $alertNow) = shopOfferRecordShop($entry, $desired, $answer, $now);
				$state['shops'][$key] = $entry;
				$outcomes[$key] = array($outcome, $alertNow, $entry);
				if ($outcome === 'failed') {
					shopOfferEvent($state, $now, 'a send failed or got no answer'); // it may still be applied: every shop again
				}
			}
			shopOfferAbsorbEvents($state, $now, @filemtime($note));
			return shopOfferStateWrite($id, $state) ? array($outcomes, (int)$state['events'] - $events) : false;
		});
		$result['sent']++;
		if (!$locked || $outcomes === false) {
			// the intent stays on disk: the next tick takes the send as unfinished, an event
			$result['stateErrors']++;
			shopOfferLogLoud('STATE NOT WRITABLE: the answers for item ' . $item['varenr'] . ' (vare_id ' . (int)$id . ') were not recorded; it is sent again');
			return true;
		}
		list($perShop, $newEvents) = $outcomes;
		$result['events'] += $newEvents;
		foreach ($perShop as $key => $o) {
			list($outcome, $alertNow, $entry) = $o;
			if ($outcome === 'untried') continue;
			$result['calls']++;
			$result[$outcome === 'ok' ? 'accepted' : $outcome]++;
			if ($outcome === 'refused') {
				if ($alertNow) $result['stuck']++; // stuck from this answer on; later ticks count it from the plan
				shopOfferLogLoud('REFUSED: item ' . $item['varenr'] . ' (vare_id ' . (int)$id . ') shop ' . $key . ' refused ' . json_encode($desired)
					. ' (' . $entry['refusals'] . ' time' . ($entry['refusals'] === 1 ? '' : 's') . '): ' . $entry['error'] . '; next try in '
					. round(($entry['retryAt'] - (int)$now) / 60) . ' min' . ($alertNow ? '; STUCK: check this shop' : ''));
			}
		}
		return true;
	}
}

if (!function_exists('shopOfferPushStuck')) {
	/**
	 * The shops that refused an item's current pair SHOP_OFFER_ALERT_REFUSALS times or more (still retried), for support.
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
				if ((int)($entry['refusals'] ?? 0) >= SHOP_OFFER_ALERT_REFUSALS) {
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
				$dispatchedAt = microtime(true);
				$calls[$key] = (function_exists('curl_init') ? shopOfferCurlOne($url, $left) : shopOfferShellOne($url, $left)) + array('dispatchedAt' => $dispatchedAt);
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
			$results[$key]['dispatchedAt'] = (float)($call['dispatchedAt'] ?? microtime(true));
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
		$dispatchedAt = microtime(true);
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
			$answers[$key] = ($done
				? shopOfferCurlAnswer($ch, curl_multi_getcontent($ch), $errno, curl_error($ch))
				: array('httpCode' => 0, 'body' => '', 'error' => 'no answer before the offer push deadline', 'transient' => true, 'unreachable' => true))
				+ array('dispatchedAt' => $dispatchedAt);
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
