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
//                Black Week volume: a send by another path no longer restarts the item's schedule; it gets one catch-up (shopOfferCatchUp(): every shop is sent the current pair once, T_late plus 2 minutes after the send), and several sends share one, due after the latest. Price changes and the reconciler's own failures still restart the schedule; markers and untracking are unchanged.
//                Codex verification 6: no send of another path carries prices read more than 2 minutes earlier. The prices come from the claim (the shop's accepted pair, or today's prices from the claim's item read); a claim or a last check before the call on an older item read reads the item again by key (shopOfferFreshItem(), bounded, in a savepoint inside a transaction); when that fails the call is not made and an event is left (shopOfferForeignSkipped()). The test clock of the stall tests (SALDI_SHOP_OFFER_CLOCK and the release clock) is inert otherwise.
// 20261118 CL/LH CodeRabbit review of #1157: a new pair is owed at once also when a shop's last send fell in the same second (it waited 2 minutes); after a refusal, a transport failure keeps the failure's rule (failedLast).
//                The tick claims a note by renaming it, so a note touched while it records stays for the next tick; an expired in-flight marker goes only after its event is written.
//                MySQL/MariaDB: only the server's own statement-time variable is set, and a failed setting or query ends the tick with "database busy" (the re-read with no row) instead of an exception.
//                A transfer without a completion message is a transport failure, and the tick's counts survive "database busy".
//                Codex verification 8: the tick keeps at most 1 MB of an answer (SHOP_OFFER_BODY_BYTES), and an answer cut there is a transport failure on the extension and the shell transport alike: the judge needs the whole answer to see a refusal, so a cut one never proves acceptance. The shell call no longer caps the download (--max-filesize).
//                Codex verification 9: one rule for every tick transport (shopOfferAnswer()): a pair is confirmed only on positive evidence that the tick holds the complete, decoded answer; shopOfferSend() judges nothing else. The extension decodes every content encoding libcurl knows (an undecodable one fails the transfer), a body that is still gzip is not judged, and the shell call asks for the answer unencoded and checks its file against curl's own byte count (missing, unreadable, shortened or misread is a transport failure).
//                A shop whose sends of a pair failed SHOP_OFFER_ALERT_REFUSALS times since its last acceptance counts as stuck too (reporting only), so a shop that is down for long or answers what the tick cannot judge raises the same alarm as one that refuses.
//                CodeRabbit review of 63cf3d3f2: the tick's choice of transport has one owner (shopOfferTransport()), which the tests read to know which transport each leg ran.
// 20261118 CL/LH Codex verification 10: every transport asks for the answer unencoded and decodes nothing (libcurl's decoding passed the partial body of a cut gzip stream as complete); an answer with a Content-Encoding other than identity is a transport failure on all three (shopOfferAnswer()). A shop counts as stuck once per item, whatever its reasons (shopOfferStuckShop()).

if (!defined('SHOP_OFFER_ALERT_REFUSALS')) {
	define('SHOP_OFFER_ALERT_REFUSALS', 8);    // refusals, or transport failures, of one price pair after which a shop counts as stuck (still retried)
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
if (!defined('SHOP_OFFER_BODY_BYTES')) {
	define('SHOP_OFFER_BODY_BYTES', 1048576);  // the most of a shop's answer the tick keeps; a longer one is cut, and a cut answer is a transport failure
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
	 * A call's prices as shopOfferForeignClaim() gave them for the shop in $slot: the pair it last accepted when the item is
	 * tracked, else today's prices from the claim's own item read; none when the item could not be read again.
	 *
	 * @param array<string, mixed> $params With salesPrice and retailPrice.
	 * @param array|null $handle From shopOfferForeignClaim().
	 * @param int|string $slot
	 * @return array<string, mixed>
	 */
	function shopOfferForeignParams(array $params, $handle, $slot) {
		if (!is_array($handle)) {
			return $params; // an account without offer state: as before
		}
		if (!empty($handle['noPrices'])) { // the item could not be read again: no prices (the callers skip such a call)
			unset($params['salesPrice'], $params['retailPrice']);
			return $params;
		}
		// the shop's accepted pair when the item is tracked, else today's prices from the claim's item read (at most 2 minutes old)
		$pair = $handle['pairs'][$slot] ?? ($handle['today'] ?? null);
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
	 * offer is active, every 3 hours; otherwise 6, 12, 18 and 24 hours after it, and then nothing. A slot counts only after the
	 * last send, so a send in the event's own second counts as the event's send; when the event is a new pair, which no shop
	 * has been sent yet, shopOfferPlanItem() owes it at the event itself.
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

if (!function_exists('shopOfferCatchUp')) {
	/**
	 * Records a send of another path on an item's state: one catch-up, due when the send's late window has passed (T_late plus
	 * SHOP_OFFER_FOREIGN_MARGIN after it). Several sends share one pending catch-up, due after the latest of them. Not an event:
	 * the item's schedule goes on as it was.
	 *
	 * @param array $state
	 * @param int $sentAt When the send left (at the latest).
	 * @return void
	 */
	function shopOfferCatchUp(array &$state, $sentAt) {
		$state['catchup'] = max((int)($state['catchup'] ?? 0), (int)$sentAt + SHOP_OFFER_LATE_SECONDS + SHOP_OFFER_FOREIGN_MARGIN);
	}
}

if (!function_exists('shopOfferShopEntry')) {
	/**
	 * One shop's entry in an item's state, with every field present.
	 *
	 * confirmed: the pair the shop last accepted from the reconciler (bookkeeping: a request that arrives late can still
	 * change it); okAt: when; sentAt: the tick (its clock at the start) that last sent the shop the item, which the schedule
	 * counts from; leftAt: when that request actually left, which untracking counts from; pending: the pair owed, which
	 * attempts, refusals, retryAt, error and failedLast belong to; failedLast: the shop's last answer to it was a transport
	 * failure (not a refusal), so retryAt is a failure's back-off.
	 *
	 * @param array|null $state
	 * @param string $key From shopOfferShopKey().
	 * @return array{confirmed: array|null, okAt: int, sentAt: int, leftAt: int, pending: array|null, attempts: int, refusals: int, retryAt: int, error: string, failedLast: bool}
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
			'failedLast' => !empty($entry['failedLast']),
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
	 * @return array{state: array, send: array<string, bool>, next: int|null, fresh: bool, urgent: bool, refusing: list<string>, failing: list<string>, untrack: bool, catchupDue: int}
	 *         send: the shops owed a send now (a new pair at once, also when a shop's last send, of the earlier pair, fell in the
	 *         same second); next: the earliest time any shop is owed one; fresh: a shop owed a send did not
	 *         last accept the current pair (new prices, which go before repeats); urgent: within the hour after the last event;
	 *         refusing: shops that refused the current pair SHOP_OFFER_ALERT_REFUSALS times or more (still retried); failing: shops
	 *         whose sends of it failed that often since their last acceptance (a shop down for long, or an answer the tick cannot
	 *         judge); both count as stuck, for reporting only;
	 *         untrack: 24 hours have passed since the last event with no offer, every re-send is done, no request of the
	 *         reconciler can still arrive (its last dispatch is older than T_late plus SHOP_OFFER_DISPATCH_MARGIN), and every shop
	 *         last accepted the current pair, no in-flight marker of another path is open ($openUntil), and no catch-up is pending.
	 *         A shop whose last answer was not an acceptance is retried at its back-off for as long as that lasts, after the
	 *         schedule has run out too. A pending catch-up ($state['catchup'], set by shopOfferForeignDone() for sends of other
	 *         paths) sends every shop the current pair once when it is due, without restarting the schedule; catchupDue is then the
	 *         catch-up served, for the record to clear.
	 * @param int $openUntil When the item's youngest in-flight marker of another path expires (shopOfferInflightCheck()); 0: none.
	 */
	function shopOfferPlanItem(array $state, array $shops, array $desired, $offerActive, $now, $openUntil = 0) {
		$now = (int)$now;
		$changed = !isset($state['desired']) || !shopOfferPairSame($state['desired'], $desired);
		if ($changed) {
			shopOfferEvent($state, $now, isset($state['desired']) ? 'the prices changed' : 'tracked');
			$state['desired'] = $desired;
		}
		$state['offer'] = (bool)$offerActive;
		$anchor = (int)$state['anchor'];
		$catchup = (int)($state['catchup'] ?? 0);
		$out = array('state' => $state, 'send' => array(), 'next' => null, 'fresh' => false, 'urgent' => false, 'refusing' => array(), 'failing' => array(), 'untrack' => false,
			'catchupDue' => ($catchup && $catchup <= $now) ? $catchup : 0);
		$quiet = !$offerActive && $now >= $anchor + SHOP_OFFER_QUIET_SECONDS && empty($state['intent']) && (int)$openUntil <= $now && !$catchup;
		$out['state']['shops'] = array();
		foreach ($shops as $key => $endpoint) {
			$entry = shopOfferShopEntry($state, $key);
			if (!shopOfferPairSame($entry['pending'], $desired)) {
				$entry['pending'] = $desired;
				$entry['attempts'] = $entry['refusals'] = $entry['retryAt'] = 0;
				$entry['error'] = '';
				$entry['failedLast'] = false;
			}
			$out['state']['shops'][$key] = $entry;
			if ($entry['refusals'] >= SHOP_OFFER_ALERT_REFUSALS) {
				$out['refusing'][] = $key;
			}
			if ($entry['attempts'] >= SHOP_OFFER_ALERT_REFUSALS) {
				$out['failing'][] = $key;
			}
			$next = shopOfferNextSend($anchor, $entry['sentAt'], $offerActive);
			if ($changed) {
				// the pair changed now, so every shop's last send was of an earlier pair: the new one is owed at the event itself,
				// also when that send fell in this second, where the first slot after it would be 2 minutes later
				$next = $anchor;
			}
			if ($next === null && ($entry['attempts'] || $entry['refusals'])) {
				$next = $entry['retryAt']; // the schedule has run out, the shop has not accepted the pair: keep trying
			}
			$at = null;
			if ($next !== null) {
				// A failed send waits for the later of the schedule and its back-off. A refusing shop is tried at the earlier of them,
				// so at most 3 hours after its last refusal, also where the schedule's next send is 6 hours away; once its last answer
				// is a transport failure, that failure's rule applies.
				$at = ($entry['refusals'] && !$entry['failedLast']) ? min($next, $entry['retryAt']) : max($next, $entry['retryAt']);
			}
			if ($catchup) {
				$at = $at === null ? $catchup : min($at, $catchup); // the catch-up after other paths' sends: every shop once
			}
			if ($at !== null) {
				$out['next'] = $out['next'] === null ? $at : min($out['next'], $at);
				if ($at <= $now) {
					$out['send'][$key] = true;
					$out['fresh'] = $out['fresh'] || !shopOfferPairSame($entry['confirmed'], $desired);
					$out['urgent'] = $out['urgent'] || ($next !== null && $next <= $anchor + 3600) || $out['catchupDue'];
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
	 *                                             pair, or failed it, SHOP_OFFER_ALERT_REFUSALS times just now (it is still
	 *                                             retried).
	 */
	function shopOfferRecordShop(array $entry, array $pair, array $result, $now) {
		$now = (int)$now;
		if (!empty($result['ok'])) {
			$entry['confirmed'] = $pair;
			$entry['okAt'] = $now;
			$entry['attempts'] = $entry['refusals'] = $entry['retryAt'] = 0;
			$entry['error'] = '';
			$entry['failedLast'] = false;
			return array('ok', $entry, false);
		}
		$entry['error'] = (string)($result['error'] ?? '');
		if ((int)($result['httpCode'] ?? 0) > 0 && !shopSyncWorthRetrying($result)) {
			$entry['refusals']++;
			$entry['retryAt'] = $now + shopOfferBackoff($entry['refusals'], SHOP_OFFER_REFUSAL_BACKOFF);
			$entry['failedLast'] = false;
			return array('refused', $entry, $entry['refusals'] === SHOP_OFFER_ALERT_REFUSALS);
		}
		$entry['attempts']++;
		$entry['retryAt'] = $now + shopOfferBackoff($entry['attempts']);
		$entry['failedLast'] = true;
		return array('failed', $entry, $entry['attempts'] === SHOP_OFFER_ALERT_REFUSALS);
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
		$mysql = in_array(strtolower((string)$db_type), array('mysql', 'mysqli'), true);
		if (!shopOfferDbTimeouts((int)floor(($left - 0.1) * 1000))) { // no unbounded query: the tick ends here
			throw new ShopOfferDatabaseBusy('the statement and lock timeouts could not be set: '
				. trim((string)($mysql ? ($GLOBALS['shopOfferMysqlError'] ?? '') : pg_last_error($connection))));
		}
		$res = $mysql ? shopOfferMysqlQuery($connection, $sql) : @pg_query($connection, $sql);
		if ($res === false) {
			throw new ShopOfferDatabaseBusy(trim((string)($mysql ? ($GLOBALS['shopOfferMysqlError'] ?? '') : pg_last_error($connection))));
		}
		return $res;
	}
}

if (!function_exists('shopOfferMysqlQuery')) {
	/**
	 * mysqli_query() that answers false on an error instead of throwing: from PHP 8.1 mysqli reports errors as exceptions by
	 * default, and this repository does not change the report mode. The error is kept in $GLOBALS['shopOfferMysqlError'].
	 *
	 * @param mysqli $connection
	 * @param string $sql
	 * @return mysqli_result|bool
	 */
	function shopOfferMysqlQuery($connection, $sql) {
		$GLOBALS['shopOfferMysqlError'] = '';
		try {
			$res = @mysqli_query($connection, $sql);
			if ($res === false) {
				$GLOBALS['shopOfferMysqlError'] = (string)@mysqli_error($connection);
			}
			return $res;
		} catch (Throwable $e) { // mysqli_sql_exception, or an Error on a closed connection
			$GLOBALS['shopOfferMysqlError'] = $e->getMessage();
			return false;
		}
	}
}

if (!function_exists('shopOfferMysqlTimeoutSql')) {
	/**
	 * The statement-time setting of the connected server, which knows only its own: max_statement_time in seconds on
	 * MariaDB, max_execution_time in milliseconds on MySQL (it bounds SELECTs, which is all the offer push runs bounded).
	 *
	 * @param mysqli $connection
	 * @param int|null $ms Null: back to the server's default.
	 * @return string
	 */
	function shopOfferMysqlTimeoutSql($connection, $ms) {
		try {
			$mariadb = stripos((string)@mysqli_get_server_info($connection), 'mariadb') !== false;
		} catch (Throwable $e) {
			$mariadb = false;
		}
		if ($mariadb) {
			return 'SET SESSION max_statement_time = ' . ($ms === null ? 'DEFAULT' : sprintf('%.3F', (int)$ms / 1000));
		}
		return 'SET SESSION max_execution_time = ' . ($ms === null ? 'DEFAULT' : (int)$ms);
	}
}

if (!class_exists('ShopOfferDatabaseBusy')) {
	/** A query of the reconciler's tick could not finish within the tick's budget (a held lock, a slow statement). */
	class ShopOfferDatabaseBusy extends RuntimeException {}
}

if (!function_exists('shopOfferDbTimeouts')) {
	/**
	 * Sets the session's statement and lock timeouts for the tick's queries, only when they must shrink, or back to the
	 * server's defaults (null). MySQL/MariaDB: innodb_lock_wait_timeout and the server's own statement-time variable
	 * (shopOfferMysqlTimeoutSql()).
	 *
	 * @param int|null $ms Most milliseconds a statement may take.
	 * @return bool Whether the timeouts are in place; false: a setting failed, and the caller must not run a query unbounded.
	 */
	function shopOfferDbTimeouts($ms) {
		global $connection, $db_type;
		static $current = null, $touched = false;
		$mysql = in_array(strtolower((string)$db_type), array('mysql', 'mysqli'), true);
		if ($ms === null) {
			if ($touched && $connection) {
				if ($mysql) {
					shopOfferMysqlQuery($connection, 'SET SESSION innodb_lock_wait_timeout = DEFAULT');
					shopOfferMysqlQuery($connection, shopOfferMysqlTimeoutSql($connection, null));
				} else {
					@pg_query($connection, 'SET statement_timeout = DEFAULT');
					@pg_query($connection, 'SET lock_timeout = DEFAULT');
				}
			}
			$current = null;
			$touched = false;
			return true;
		}
		$ms = max(100, (int)$ms);
		if ($current !== null && $ms >= $current - 500) {
			return true;
		}
		$lock = min($ms, SHOP_OFFER_DB_LOCK_SECONDS * 1000);
		$touched = true; // a setting that went through before one failed is reset at the end too
		if ($mysql) {
			$set = shopOfferMysqlQuery($connection, 'SET SESSION innodb_lock_wait_timeout = ' . max(1, (int)ceil($lock / 1000))) !== false
				&& shopOfferMysqlQuery($connection, shopOfferMysqlTimeoutSql($connection, $ms)) !== false;
		} else {
			$set = @pg_query($connection, 'SET statement_timeout = ' . $ms) !== false
				&& @pg_query($connection, 'SET lock_timeout = ' . $lock) !== false;
		}
		$current = $set ? $ms : null;
		return $set;
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
//      {path, startedAt}, release the lock. Today's prices come from an item read at most SHOP_OFFER_FOREIGN_MARGIN old: an
//      older read is repeated by key first, bounded; if it cannot be, no prices are sent and an event is left;
//   3. shopOfferForeignReady() right before the HTTP call (a claim or an item read older than SHOP_OFFER_FOREIGN_MARGIN is
//      taken again), then the call at once. The call's prices are the claim's (shopOfferForeignParams()), never the caller's;
//   4. shopOfferForeignDone() after the send, whatever its outcome: one catch-up for the item (every shop gets the current pair
//      once, when the send's late window has passed: T_late plus SHOP_OFFER_FOREIGN_MARGIN after it), when it is tracked, has a
//      price offer running or to come, or a marker was written; several sends share one catch-up, due after the latest; the
//      item's schedule is not restarted. The marker goes.
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

if (!function_exists('shopOfferForeignNow')) {
	/**
	 * The clock of the other paths' sends: now. For the stall tests only, the test's clock: SALDI_SHOP_OFFER_CLOCK (a unix
	 * time) at the start of the process, and the clock the test gives when it releases a held path (shopOfferTestPause()).
	 *
	 * @return float
	 */
	function shopOfferForeignNow() {
		if (!isset($GLOBALS['shopOfferClockShift'])) {
			$clock = getenv('SALDI_SHOP_OFFER_CLOCK');
			$GLOBALS['shopOfferClockShift'] = ($clock !== false && is_numeric($clock)) ? (float)$clock - microtime(true) : 0.0;
		}
		return microtime(true) + (float)$GLOBALS['shopOfferClockShift'];
	}
}

if (!function_exists('shopOfferFreshItem')) {
	/**
	 * Reads an item's price columns again by its key, bounded as the reconciler's queries are: at most SHOP_OFFER_DB_LOCK_SECONDS
	 * for a lock or the statement. Inside the caller's transaction it runs in a savepoint, so a timeout leaves the transaction as
	 * it was. Never alerts, mails or exits as db_select() would.
	 *
	 * @param int $vareId
	 * @return array<string, mixed>|null salgspris, retail_price and the offer columns, or null when it could not be read.
	 */
	function shopOfferFreshItem($vareId) {
		global $connection, $db_type;
		if (!$connection) {
			return null;
		}
		$sql = "select salgspris, retail_price, " . shopOfferColumns() . " from varer where id = '" . (int)$vareId . "'";
		$ms = SHOP_OFFER_DB_LOCK_SECONDS * 1000;
		if (in_array(strtolower((string)$db_type), array('mysql', 'mysqli'), true)) { // a lock wait timeout ends the statement, not the transaction
			// bounded or not at all: a setting that fails means no read (null), never an unbounded one
			$bounded = shopOfferMysqlQuery($connection, 'SET SESSION innodb_lock_wait_timeout = ' . SHOP_OFFER_DB_LOCK_SECONDS) !== false
				&& shopOfferMysqlQuery($connection, shopOfferMysqlTimeoutSql($connection, $ms)) !== false;
			$res = $bounded ? shopOfferMysqlQuery($connection, $sql) : false;
			$row = ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : null;
			shopOfferMysqlQuery($connection, 'SET SESSION innodb_lock_wait_timeout = DEFAULT');
			shopOfferMysqlQuery($connection, shopOfferMysqlTimeoutSql($connection, null));
			return $row ?: null;
		}
		$status = pg_transaction_status($connection);
		if ($status === PGSQL_TRANSACTION_INERROR || $status === PGSQL_TRANSACTION_UNKNOWN) {
			return null;
		}
		$inTx = ($status === PGSQL_TRANSACTION_INTRANS);
		$prev = array();
		foreach (array('statement_timeout', 'lock_timeout') as $name) {
			$r = @pg_query($connection, 'SHOW ' . $name);
			$prev[$name] = $r ? (string)(pg_fetch_row($r)[0] ?? '0') : '0';
		}
		$set = $inTx ? 'SET LOCAL ' : 'SET ';
		if ($inTx) @pg_query($connection, 'SAVEPOINT shop_offer_fresh_item');
		@pg_query($connection, $set . 'statement_timeout = ' . $ms);
		@pg_query($connection, $set . 'lock_timeout = ' . $ms);
		$res = @pg_query($connection, $sql);
		$row = $res ? pg_fetch_assoc($res) : false;
		if ($res === false && $inTx) {
			@pg_query($connection, 'ROLLBACK TO SAVEPOINT shop_offer_fresh_item'); // undoes the error and the settings
			@pg_query($connection, 'RELEASE SAVEPOINT shop_offer_fresh_item');
			return null;
		}
		foreach ($prev as $name => $value) {
			@pg_query($connection, $set . $name . " = '" . pg_escape_string($connection, $value) . "'");
		}
		if ($inTx) @pg_query($connection, 'RELEASE SAVEPOINT shop_offer_fresh_item');
		return $row ?: null;
	}
}

if (!function_exists('shopOfferForeignClaim')) {
	/**
	 * Step 2 of the protocol above: the critical section of a send of an item's prices by a path other than the reconciler.
	 * Call it after the path's queries, right before the send. No query runs in its critical section.
	 *
	 * The item read it is given must be at most SHOP_OFFER_FOREIGN_MARGIN old ($item['_readAt'], shopOfferForeignNow() when
	 * the caller read it; missing counts as old). An older one is read again by key, bounded (shopOfferFreshItem()); if that
	 * fails, the claim carries no prices (noPrices: the caller skips the call) and leaves an event, so the reconciler sends the
	 * current pair. No send carries prices read more than SHOP_OFFER_FOREIGN_MARGIN earlier.
	 *
	 * Null when the account has no offer state (the reconciler has never run, or no webshop): the path is unchanged. An item
	 * the state file says is not tracked, and whose (fresh) row has no price offer running or to come, costs one file check and
	 * gets today's prices from that row, without lock or marker. Otherwise, under the item's lock: each shop's pair (the pair it
	 * last accepted when the item is tracked, else today's prices) and an in-flight marker. If the lock or the marker fails,
	 * the path sends today's prices and leaves a lock-free note (uncertain-<id>, its catch-up) before it sends.
	 *
	 * @param string $path 'card', 'stock', 'document' or 'bulk'.
	 * @param int $vareId
	 * @param array<string, mixed> $item varer row read before: salgspris, retail_price and the offer columns (shopOfferColumns()),
	 *                                   and _readAt.
	 * @param array<int|string, string> $endpoints Slot => endpoint the send goes to.
	 * @return array{path: string, vare_id: int, item: array, endpoints: array, pairs: array<int|string, array|null>, today: array{sales: string|null, retail: string|null}|null, token: string|null, tracked: bool, offer: bool, noPrices: bool, readAt: float, claimedAt: float}|null
	 *         tracked: the item was tracked at the claim (the pairs came from its state); today: null on a noPrices claim, which
	 *         has no prices to send.
	 */
	function shopOfferForeignClaim($path, $vareId, array $item, array $endpoints) {
		$vareId = (int)$vareId;
		$dir = shopOfferPushDir(false);
		if ($dir === '' || !$endpoints) {
			return null;
		}
		$now = shopOfferForeignNow();
		$readAt = isset($item['_readAt']) ? (float)$item['_readAt'] : 0.0;
		$handle = array('path' => (string)$path, 'vare_id' => $vareId, 'item' => $item, 'endpoints' => $endpoints, 'pairs' => array(),
			'today' => null, 'token' => null, 'tracked' => false, 'offer' => false, 'noPrices' => false, 'readAt' => $readAt, 'claimedAt' => $now);
		if ($now - $readAt >= SHOP_OFFER_FOREIGN_MARGIN) { // the path's read of the item is too old to send from: read it again
			$fresh = shopOfferFreshItem($vareId);
			if ($fresh === null) {
				$handle['noPrices'] = true;
				shopOfferForeignSkipped($vareId, $path);
				return $handle;
			}
			$handle['item'] = $fresh + $item;
			$handle['readAt'] = $now = shopOfferForeignNow();
		}
		$handle['today'] = array('sales' => isset($handle['item']['salgspris']) ? (string)$handle['item']['salgspris'] : null,
			'retail' => isset($handle['item']['retail_price']) ? (string)$handle['item']['retail_price'] : null);
		$handle['offer'] = shopOfferHasOfferFields($handle['item'], (int)$now);
		if (!$handle['offer'] && !is_file($dir . 'item-' . $vareId . '.json')) {
			return $handle; // not tracked, no offer: today's prices from a read at most 2 minutes old
		}
		$token = getmypid() . '-' . bin2hex(random_bytes(6));
		list($locked, $pairs) = shopOfferWithItemLock($vareId, function () use ($vareId, $endpoints, $token, $path, $now) {
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
			$markers[$token] = array('path' => (string)$path, 'startedAt' => (int)$now, 'pid' => (int)getmypid());
			return shopOfferInflightWrite($vareId, $markers) ? array($pairs, is_array($state)) : false;
		});
		if (!$locked || $pairs === false) {
			$noted = @touch($dir . 'uncertain-' . $vareId);
			shopOfferLogLoud('STATE NOT WRITABLE: a send of item vare_id ' . $vareId . ' by the ' . $path . ' could not be marked ('
				. ($locked ? 'marker not written' : 'lock busy') . '); it carries today\'s prices'
				. ($noted ? ' and left a note for the reconciler' : ', and the note for the reconciler failed too'));
			return $handle;
		}
		list($handle['pairs'], $handle['tracked']) = $pairs;
		$handle['token'] = $token;
		shopOfferTestPause($path, 'marked', $vareId);
		return $handle;
	}
}

if (!function_exists('shopOfferForeignSkipped')) {
	/**
	 * A send of other paths that was skipped because the item could not be read again: an event, so the reconciler sends the
	 * current pair (and tracks the item if it did not). If the lock is busy or the state cannot be written, a lock-free note.
	 *
	 * @param int $vareId
	 * @param string $path
	 * @return void
	 */
	function shopOfferForeignSkipped($vareId, $path) {
		$vareId = (int)$vareId;
		$dir = shopOfferPushDir(false);
		if ($dir === '') {
			return;
		}
		$now = (int)shopOfferForeignNow();
		list($locked, $written) = shopOfferWithItemLock($vareId, function () use ($vareId, $now) {
			$state = shopOfferStateRead($vareId);
			if (!is_array($state)) {
				$state = array('shops' => array(), 'reset' => true);
			}
			$state['foreign']['staleAt'] = max((int)($state['foreign']['staleAt'] ?? 0), $now);
			return shopOfferStateWrite($vareId, $state);
		});
		if (!$locked || !$written) {
			@touch($dir . 'uncertain-' . $vareId);
		}
		shopOfferLogLoud('ITEM NOT READ AGAIN: the ' . $path . ' did not send the prices of item vare_id ' . $vareId
			. ' (its read was older than ' . SHOP_OFFER_FOREIGN_MARGIN . ' s and could not be repeated); the reconciler sends them');
	}
}

if (!function_exists('shopOfferForeignReady')) {
	/**
	 * Step 3: right before the HTTP call, the last freshness check. A claim or an item read older than
	 * SHOP_OFFER_FOREIGN_MARGIN (the path stalled after it) is recorded and taken again, with the item read again; so a send
	 * leaves within the margin of its state read, its marker and its item read.
	 *
	 * @param array|null $handle From shopOfferForeignClaim().
	 * @param float|null $at shopOfferForeignNow(); for tests.
	 * @return array|null The handle to send with (check noPrices).
	 */
	function shopOfferForeignReady($handle, $at = null) {
		if (!is_array($handle) || !empty($handle['noPrices'])) {
			return $handle;
		}
		$at = $at ?? shopOfferForeignNow();
		if ($at - (float)$handle['readAt'] < SHOP_OFFER_FOREIGN_MARGIN && $at - (float)$handle['claimedAt'] < SHOP_OFFER_FOREIGN_MARGIN) {
			return $handle;
		}
		shopOfferForeignDone($handle);
		return shopOfferForeignClaim($handle['path'], $handle['vare_id'], array('_readAt' => (float)$handle['readAt']) + $handle['item'], $handle['endpoints']);
	}
}

if (!function_exists('shopOfferForeignDone')) {
	/**
	 * Step 4: after the send, whatever its outcome. When a marker was written, the item is tracked now, or it has a price offer
	 * running or to come, the item gets one catch-up: every shop is sent the current pair once, when the send's late window
	 * has passed (T_late plus SHOP_OFFER_FOREIGN_MARGIN from now, after the send left; the bulk sync's calls leave within 60 s).
	 * Several sends share one pending catch-up, due after the latest of them. The item's schedule is not restarted.
	 * An item that is not tracked any more gets state (and so the reconciler's full schedule, as when it finds an offer) only
	 * when the pairs came from its state at the claim or its offer runs now; otherwise the send carried today's prices, which
	 * is what the item is owed. The marker goes. If the lock is busy or the state cannot be written, a lock-free note
	 * (uncertain-<id>) carries the catch-up, and the marker is left to expire, which is an event.
	 *
	 * @param array|null $handle From shopOfferForeignClaim() / shopOfferForeignReady().
	 * @param int|null $now For tests.
	 * @return void
	 */
	function shopOfferForeignDone($handle, $now = null) {
		if (!is_array($handle)) {
			return;
		}
		$vareId = (int)$handle['vare_id'];
		$dir = shopOfferPushDir(false);
		if (!empty($handle['noPrices'])) {
			return; // nothing was sent; shopOfferForeignSkipped() left the event
		}
		if ($dir === '' || ($handle['token'] === null && !$handle['offer'] && !is_file($dir . 'item-' . $vareId . '.json'))) {
			return; // an item nobody tracks and without an offer: nothing to record
		}
		$now = $now === null ? (int)shopOfferForeignNow() : (int)$now;
		$token = $handle['token'];
		$adopt = !empty($handle['tracked']) || shopOfferActive($handle['item'], $now);
		list($locked, $written) = shopOfferWithItemLock($vareId, function () use ($vareId, $now, $token, $adopt) {
			$state = shopOfferStateRead($vareId);
			if ($state === null && !$adopt) {
				$state = false; // not tracked, and what went out was today's prices: no catch-up owed
			} else {
				if (!is_array($state)) {
					$state = array('shops' => array(), 'reset' => true);
				}
				shopOfferCatchUp($state, $now);
				if (!shopOfferStateWrite($vareId, $state)) {
					return false;
				}
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
	 * point for that item, it creates <prefix>.held and waits for <prefix>.go (at most 10 minutes). A unix time in <prefix>.go
	 * becomes this path's clock from then on (shopOfferForeignNow()), so the hold lasts as long as the test's clock says.
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
		$clock = trim((string)@file_get_contents($parts[3] . '.go'));
		if (is_numeric($clock)) { // the test's clock for the rest of this path (shopOfferForeignNow()): the hold lasted until then
			$GLOBALS['shopOfferClockShift'] = (float)$clock - microtime(true);
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
						shopOfferReconcileRun($now, $deadline, $shops, $dir, array('started' => $started) + $options, $result);
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
		unset($result['stuckShops']); // counted in 'stuck'
		$result['elapsed'] = round(microtime(true) - $started, 3);
		return $result;
	}
}

if (!function_exists('shopOfferStuckShop')) {
	/**
	 * Counts a shop as stuck for an item in the tick's result once, however many reasons it has (it refused the pair, its sends
	 * of it failed, or one of those crossed the threshold in this tick): 'stuck' is the number of distinct item and shop pairs.
	 *
	 * @param array $result The tick's counts; 'stuckShops' holds the pairs while the tick runs.
	 * @param int $id
	 * @param string $key Shop key.
	 * @return void
	 */
	function shopOfferStuckShop(array &$result, $id, $key) {
		$result['stuckShops'][(int)$id . '|' . $key] = true;
		$result['stuck'] = count($result['stuckShops']);
	}
}

if (!function_exists('shopOfferNoteFiles')) {
	/**
	 * An item's notes: uncertain-<id>, which a send of another path that could not be marked or recorded touches without the
	 * item's lock, and uncertain-<id>.claimed-*, a note a tick took (shopOfferNoteClaim()) and has not consumed yet.
	 *
	 * @param string $dir
	 * @param int $id
	 * @return array{files: list<string>, at: int|false} The files and the latest one's time (false: none).
	 */
	function shopOfferNoteFiles($dir, $id) {
		$note = $dir . 'uncertain-' . (int)$id;
		$out = array('files' => array(), 'at' => false);
		foreach (array_merge(array($note), (array)glob($note . '.claimed-*')) as $file) {
			$mtime = @filemtime((string)$file);
			if ($mtime !== false) {
				$out['files'][] = (string)$file;
				$out['at'] = $out['at'] === false ? $mtime : max($out['at'], $mtime);
			}
		}
		return $out;
	}
}

if (!function_exists('shopOfferNoteClaim')) {
	/**
	 * Takes an item's notes for this tick, under the item's lock. The note is renamed first, so a sender that touches it after
	 * that leaves a new note for the next tick instead of updating the one this tick consumes. Only claimed files are removed
	 * once the tick has recorded their catch-up; a claimed note left by a tick whose record failed is taken again.
	 *
	 * @param string $dir
	 * @param int $id
	 * @return array{files: list<string>, at: int|false} The claimed files, and the latest note's time (false: no note).
	 */
	function shopOfferNoteClaim($dir, $id) {
		$note = $dir . 'uncertain-' . (int)$id;
		if (is_file($note)) {
			@rename($note, $note . '.claimed-' . getmypid() . '-' . bin2hex(random_bytes(4)));
		}
		$notes = shopOfferNoteFiles($dir, $id);
		// a note that could not be renamed still counts, and stays for the next tick
		$notes['files'] = array_values(array_filter($notes['files'], function ($file) use ($note) { return $file !== $note; }));
		return $notes;
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
	 * @param array $result The tick's counts, updated as each item is done, so a later "database busy" keeps those of the items
	 *                      already sent.
	 * @return array The same counts.
	 */
	function shopOfferReconcileRun($now, $deadline, array $shops, $dir, array $options, array &$result) {
		$maxItems = isset($options['maxItems']) ? (int)$options['maxItems'] : PHP_INT_MAX;

		// What is tracked, what another path noted without its lock, and temporary files a killed writer left.
		$tracked = $notes = $inflight = array();
		foreach ((array)@scandir($dir) as $name) {
			if (preg_match('/^item-([0-9]+)\.json$/D', (string)$name, $m)) {
				$tracked[(int)$m[1]] = true;
			} elseif (preg_match('/^uncertain-([0-9]+)(\.claimed-[0-9a-f-]+)?$/D', (string)$name, $m)) {
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
					foreach (shopOfferNoteFiles($dir, $id)['files'] as $file) {
						@unlink($file);
					}
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
			if ($state === false || !empty($preview['intent']) || $markers['expired']
				|| (int)($preview['foreign']['staleAt'] ?? 0) > (int)($preview['foreign']['absorbed'] ?? 0)) {
				shopOfferEvent($preview, $now, 'pending');
			}
			if (isset($notes[$id])) {
				shopOfferCatchUp($preview, (int)shopOfferNoteFiles($dir, $id)['at']);
			}
			$plan = shopOfferPlanItem($preview, $shops, shopOfferPair($prices), $prices['offer'], $now, $markers['openUntil']);
			foreach (array_merge($plan['refusing'], $plan['failing']) as $key) {
				shopOfferStuckShop($result, $id, $key);
			}
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
	 * or killed), and a send by another path marked as stale by an earlier version (foreign.staleAt). A send another path could
	 * not mark or record (uncertain-<id>) gets its catch-up instead, as a recorded one does (shopOfferForeignDone()).
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
		if ($noteAt !== false) { // a send of another path that could not be marked or recorded: its catch-up
			shopOfferCatchUp($state, (int)$noteAt);
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

		list($locked, $intent) = shopOfferWithItemLock($id, function () use ($id, $shops, $now, $prices, $desired, $dir, $dead) {
			$read = shopOfferStateRead($id);
			shopOfferTestPause('tick', 'locked', $id);
			$notes = shopOfferNoteClaim($dir, $id); // a note touched after this stays for the next tick
			$noteAt = $notes['at'];
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
			// another path's sends: an open marker keeps the item tracked; an expired one is an event, and goes once that event is
			// written (if it cannot go, the next tick finds it again: another event, never a lost one)
			$markers = shopOfferInflightCheck($inflight, $now);
			if ($markers['expired']) {
				shopOfferEvent($state, $now, 'a send by another path was not recorded in time (' . implode(', ', $markers['expired']) . ')');
			}
			$consumed = function () use ($id, $markers, $notes) { // after the state is written: what it now records
				if ($markers['expired'] && !shopOfferInflightWrite($id, $markers['keep'])) {
					shopOfferLogLoud('STATE NOT WRITABLE: the expired in-flight markers of vare_id ' . (int)$id . ' stay; the next tick finds them again');
				}
				foreach ($notes['files'] as $file) {
					@unlink($file);
				}
			};
			$plan = shopOfferPlanItem($state, $shops, $desired, $prices['offer'], $now, $markers['openUntil']);
			$state = $plan['state'];
			unset($state['reset']);
			$newEvents = (int)($state['events'] ?? 0) - $events;
			$targets = array_diff_key($plan['send'], $dead);
			if (!$targets) {
				if ($plan['untrack']) {
					if (!shopOfferStateWrite($id, null)) return array('stateError' => true);
					$consumed();
					return array('idle' => true, 'untracked' => true, 'events' => $newEvents);
				}
				if ($state != $read && !shopOfferStateWrite($id, $state)) return array('stateError' => true);
				$consumed();
				return array('idle' => true, 'events' => $newEvents);
			}
			$was = array();
			foreach ($targets as $key => $yes) {
				$was[$key] = $state['shops'][$key]['sentAt'];
				$state['shops'][$key]['sentAt'] = $now;
			}
			// the intent: written before the send, so a send cut off or killed is found by the next tick as an event
			$state['intent'] = array('at' => $now, 'pair' => $desired, 'shops' => array_keys($targets));
			// a due catch-up is served when every shop is sent now
			$catchup = (count($targets) === count($shops)) ? (int)$plan['catchupDue'] : 0;
			if (!shopOfferStateWrite($id, $state)) {
				return array('stateError' => true);
			}
			$consumed();
			return array('send' => $targets, 'was' => $was, 'events' => $newEvents, 'catchup' => $catchup);
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

		list($locked, $outcomes) = shopOfferWithItemLock($id, function () use ($id, $now, $desired, $intent, $answers, $dir, $started) {
			$state = shopOfferStateRead($id);
			if (!is_array($state)) {
				$state = array('shops' => array());
				shopOfferEvent($state, $now, 'the state could not be read');
			}
			unset($state['intent']);
			$events = (int)($state['events'] ?? 0);
			$outcomes = array();
			$served = !empty($intent['catchup']);
			foreach ($answers as $key => $answer) {
				$entry = shopOfferShopEntry($state, $key);
				if (!empty($answer['untried'])) {
					$entry['sentAt'] = (int)$intent['was'][$key]; // nothing went out: still owed
					$state['shops'][$key] = $entry;
					$outcomes[$key] = array('untried', false, $entry);
					$served = false;
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
			// the catch-up went to every shop (a failure is an event of its own); one set by a later send stays
			if ($served && (int)($state['catchup'] ?? 0) <= (int)$intent['catchup']) {
				unset($state['catchup']);
			}
			shopOfferAbsorbEvents($state, $now, shopOfferNoteFiles($dir, $id)['at']); // a note left during the send; the next tick takes it
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
				if ($alertNow) shopOfferStuckShop($result, $id, $key); // stuck from this answer on; later ticks count it from the plan
				shopOfferLogLoud('REFUSED: item ' . $item['varenr'] . ' (vare_id ' . (int)$id . ') shop ' . $key . ' refused ' . json_encode($desired)
					. ' (' . $entry['refusals'] . ' time' . ($entry['refusals'] === 1 ? '' : 's') . '): ' . $entry['error'] . '; next try in '
					. round(($entry['retryAt'] - (int)$now) / 60) . ' min' . ($alertNow ? '; STUCK: check this shop' : ''));
			} elseif ($outcome === 'failed' && $alertNow) { // each failure is logged by the transport; the crossing is the alert
				shopOfferStuckShop($result, $id, $key);
				shopOfferLogLoud('STUCK: item ' . $item['varenr'] . ' (vare_id ' . (int)$id . ') shop ' . $key . ': ' . $entry['attempts']
					. ' sends of ' . json_encode($desired) . ' failed since its last acceptance (last: ' . $entry['error'] . '); still retried, check this shop');
			}
		}
		return true;
	}
}

if (!function_exists('shopOfferPushStuck')) {
	/**
	 * The shops that refused an item's current pair, or failed it, SHOP_OFFER_ALERT_REFUSALS times or more (still retried), for
	 * support.
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
				if ((int)($entry['refusals'] ?? 0) >= SHOP_OFFER_ALERT_REFUSALS || (int)($entry['attempts'] ?? 0) >= SHOP_OFFER_ALERT_REFUSALS) {
					$stuck[(int)$m[1]][$key] = array('pair' => $entry['pending'] ?? null, 'error' => (string)($entry['error'] ?? ''));
				}
			}
		}
		return $stuck;
	}
}

if (!function_exists('shopOfferTransport')) {
	/**
	 * The transport the tick sends with (shopOfferSend()): curl_multi, all shops at once, where the curl extension has it; curl,
	 * one call after the other, where it has only curl_init; the curl command line without the extension.
	 *
	 * @return string 'multi', 'single' or 'shell'.
	 */
	function shopOfferTransport() {
		if (function_exists('curl_init')) {
			return function_exists('curl_multi_init') ? 'multi' : 'single';
		}
		return 'shell';
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
		$transport = shopOfferTransport();
		if ($pending && $transport === 'multi') {
			$calls = shopOfferCurlMulti($pending, $deadline);
		} else {
			$calls = array();
			foreach ($pending as $key => $url) {
				$left = $deadline - microtime(true);
				if ($left < SHOP_OFFER_MIN_CALL) {
					continue; // left untried, reported below
				}
				$dispatchedAt = microtime(true);
				$calls[$key] = ($transport === 'single' ? shopOfferCurlOne($url, $left) : shopOfferShellOne($url, $left)) + array('dispatchedAt' => $dispatchedAt);
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
			// only an answer that passed shopOfferAnswer() is judged; anything else could confirm a pair the tick never saw whole
			if (!empty($call['checked'])) {
				$outcome = shopApiOutcome($call);
			} else {
				$outcome = array('state' => 'failed', 'error' => 'answer not checked by shopOfferAnswer(), so not judged');
				$results[$key]['transient'] = true;
			}
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

if (!function_exists('shopOfferAnswer')) {
	/**
	 * The one rule by which the tick turns a shop's HTTP exchange into an answer for the judge (shopApiOutcome()), on every one
	 * of its transports (shopOfferCurlOne(), shopOfferCurlMulti(), shopOfferShellOne()): a pair is confirmed only on positive
	 * evidence that the tick holds the complete, unencoded answer. Anything else is a transport failure, retried by the usual
	 * rules and never an acceptance or a refusal: the transfer failed ($failure), the answer carries a content encoding (every
	 * transport asks for it unencoded, and the tick decodes nothing: a decoder that stops short of the end yields a partial
	 * answer that would pass), more arrived than the tick keeps ($cut), what arrived could not be read back whole ($unread), or
	 * the body is still compressed (gzip without Content-Encoding). shopOfferSend() judges only answers made here ('checked').
	 *
	 * @param int $httpCode 0: no answer.
	 * @param string|false|null $body What the transport holds of the answer.
	 * @param string $failure Why the transfer failed; '' when the transport reports it complete.
	 * @param bool $cut More of the answer arrived than SHOP_OFFER_BODY_BYTES.
	 * @param string $unread Why the answer could not be read back whole; '' when it was.
	 * @param string $encoding The final response's Content-Encoding, lower case; '' when it had none.
	 * @return array{httpCode: int, body: string, error: string, transient: bool, unreachable: bool, checked: bool}
	 */
	function shopOfferAnswer($httpCode, $body, $failure = '', $cut = false, $unread = '', $encoding = '') {
		$httpCode = (int)$httpCode;
		$body = is_string($body) ? $body : '';
		$error = (string)$failure;
		if ($error === '' && $encoding !== '' && $encoding !== 'identity') {
			$error = 'answer encoded (' . $encoding . ') although it was asked unencoded, so not judged (a transport failure)';
		}
		if ($error === '' && $unread !== '') {
			$error = 'answer could not be read: ' . $unread;
		}
		if ($error === '' && $cut) {
			$error = 'answer longer than ' . SHOP_OFFER_BODY_BYTES . ' bytes: cut, so not judged (a transport failure)';
		}
		if ($error === '' && strncmp($body, "\x1f\x8b", 2) === 0) {
			$error = 'answer still compressed (gzip), so not judged (a transport failure)';
		}
		return array(
			'httpCode'    => $httpCode,
			'body'        => $body,
			'error'       => $error,
			'transient'   => ($error !== '' || $httpCode === 0 || $httpCode >= 500),
			'unreachable' => ($httpCode === 0),
			'checked'     => true,
		);
	}
}

if (!function_exists('shopOfferCurlHandle')) {
	/**
	 * A curl handle for a shop call, set up as shopApiCurlCall() sets it up but with millisecond timeouts. It asks for the
	 * answer unencoded (Accept-Encoding: identity) and decodes nothing; the final response's Content-Encoding is kept in
	 * $encoding, and shopOfferAnswer() judges an encoded answer a transport failure. The answer is kept in $body as it arrives,
	 * at most SHOP_OFFER_BODY_BYTES of it, so no answer can use the tick's memory up; the rest is read and dropped, and $cut
	 * says so (shopOfferAnswer() makes that a transport failure too).
	 *
	 * @param string $url
	 * @param float $seconds Time the call may take.
	 * @param string $body Receives the start of the answer.
	 * @param bool $cut Set when more of the answer arrived than $body keeps.
	 * @param string $encoding Receives the final response's Content-Encoding, lower case ('' when it has none).
	 * @return resource|CurlHandle|false
	 */
	function shopOfferCurlHandle($url, $seconds, &$body, &$cut, &$encoding) {
		$body = '';
		$cut = false;
		$encoding = '';
		$ch = curl_init($url);
		if ($ch === false) return false;
		$ms = max(1, (int)floor($seconds * 1000));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept-Encoding: identity'));
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($handle, $line) use (&$encoding) {
			if (strncasecmp($line, 'HTTP/', 5) === 0) {
				$encoding = ''; // a new response, after a redirect: only the final one's headers count
			} elseif (strncasecmp($line, 'Content-Encoding:', 17) === 0) {
				$value = strtolower(trim(substr($line, 17)));
				$encoding = $encoding === '' ? $value : $encoding . ', ' . $value;
			}
			return strlen($line);
		});
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($handle, $chunk) use (&$body, &$cut) {
			$room = SHOP_OFFER_BODY_BYTES - strlen($body);
			if (strlen($chunk) > $room) {
				$cut = true;
			}
			if ($room > 0) {
				$body .= substr($chunk, 0, $room);
			}
			return strlen($chunk);
		});
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

if (!function_exists('shopOfferCurlOne')) {
	/**
	 * One call, waiting at most $seconds.
	 *
	 * @param string $url
	 * @param float $seconds
	 * @return array{httpCode: int, body: string, error: string, transient: bool, unreachable: bool, checked: bool}
	 */
	function shopOfferCurlOne($url, $seconds) {
		$body = $encoding = '';
		$cut = false;
		$ch = shopOfferCurlHandle($url, $seconds, $body, $cut, $encoding);
		if ($ch === false) {
			return shopOfferAnswer(0, '', 'curl_init failed');
		}
		curl_exec($ch);
		$errno = curl_errno($ch);
		$answer = shopOfferAnswer(curl_getinfo($ch, CURLINFO_HTTP_CODE), $body, $errno ? 'curl error ' . $errno . ': ' . curl_error($ch) : '', $cut, '', $encoding);
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
	 * @return array<string, array{httpCode: int, body: string, error: string, transient: bool, unreachable: bool, checked: bool}> Missing keys were not tried.
	 */
	function shopOfferCurlMulti(array $urls, $deadline) {
		$left = $deadline - microtime(true);
		if ($left < SHOP_OFFER_MIN_CALL) {
			return array();
		}
		$multi = curl_multi_init();
		$handles = $bodies = $cuts = $encodings = $completed = array();
		$dispatchedAt = microtime(true);
		foreach ($urls as $key => $url) {
			$bodies[$key] = $encodings[$key] = '';
			$cuts[$key] = false;
			$ch = shopOfferCurlHandle($url, $left, $bodies[$key], $cuts[$key], $encodings[$key]);
			if ($ch !== false) {
				curl_multi_add_handle($multi, $ch);
				$handles[$key] = $ch;
			}
		}
		$collect = function () use ($multi, &$handles, &$completed) { // the transfers' completion messages, with their result codes
			while ($info = curl_multi_info_read($multi)) {
				foreach ($handles as $key => $ch) {
					if ($ch === $info['handle']) {
						$completed[$key] = (int)$info['result'];
						break;
					}
				}
			}
		};
		do {
			$status = curl_multi_exec($multi, $running);
			$collect();
			if ($running) {
				curl_multi_select($multi, max(0.01, min(0.1, $deadline - microtime(true))));
			}
		} while ($running && $status === CURLM_OK && microtime(true) < $deadline);
		$collect();
		$answers = array();
		foreach ($handles as $key => $ch) {
			// only a completed transfer is an answer: one cut off after its status line, or still running at the deadline, is a
			// transport failure, as in shopApiCurlMultiCall()
			if (isset($completed[$key])) {
				$errno = $completed[$key];
				$answers[$key] = shopOfferAnswer(curl_getinfo($ch, CURLINFO_HTTP_CODE), $bodies[$key], $errno ? 'curl error ' . $errno . ': ' . curl_strerror($errno) : '', $cuts[$key], '', $encodings[$key]);
			} else {
				$answers[$key] = shopOfferAnswer(0, '', 'no answer before the offer push deadline');
			}
			$answers[$key] += array('dispatchedAt' => $dispatchedAt);
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
	 * The answer goes to a temporary file, so its capture is checked before shopOfferAnswer() judges it: curl must report the
	 * transfer complete, the file must be there and readable, it must hold exactly what curl says it received (size_download,
	 * the witness that the file was not lost or shortened afterwards), and what is read back must be all of it up to
	 * SHOP_OFFER_BODY_BYTES (a larger file is cut). The call asks for the answer unencoded (Accept-Encoding: identity), because
	 * curl's witness counts the bytes on the wire: an answer curl decoded could not be checked against it. An answer that comes
	 * encoded anyway is not judged (shopOfferAnswer(), as on the extension).
	 *
	 * @param string $url
	 * @param float $seconds
	 * @return array{httpCode: int, body: string, error: string, transient: bool, unreachable: bool, checked: bool}
	 */
	function shopOfferShellOne($url, $seconds) {
		if (!function_exists('shell_exec')) {
			return shopOfferAnswer(0, '', 'no http client available (ext-curl and shell_exec both missing)');
		}
		$bodyFile = tempnam(sys_get_temp_dir(), 'shopoffer');
		if ($bodyFile === false) {
			return shopOfferAnswer(0, '', 'could not create temporary file');
		}
		$seconds = max(0.1, (float)$seconds);
		$cmd = 'curl -s -S -L --max-redirs 3 --proto =http,https'
			. ' --connect-timeout ' . sprintf('%.3F', min(2.0, $seconds))
			. ' --max-time ' . sprintf('%.3F', $seconds)
			. ' -A ' . escapeshellarg('Saldi shop sync')
			. ' -H ' . escapeshellarg('Accept-Encoding: identity')
			. ' -o ' . escapeshellarg($bodyFile)
			. ' -w ' . escapeshellarg("\nSALDI_HTTP:%{http_code}\nSALDI_SIZE:%{size_download}\nSALDI_ENC:%header{content-encoding}\n")
			. ' ' . escapeshellarg($url) . ' 2>&1; shop_curl_status=$?; printf "\nSALDI_EXIT:%s\n" "$shop_curl_status"';
		$out = trim((string)shell_exec($cmd));
		$httpCode = preg_match('/SALDI_HTTP:(\d{3})/', $out, $m) ? (int)$m[1] : 0;
		$exitCode = preg_match('/(?:^|\n)SALDI_EXIT:(\d+)$/D', $out, $m) ? (int)$m[1] : -1;
		$received = preg_match('/(?:^|\n)SALDI_SIZE:(\d+)(?:\n|$)/', $out, $m) ? (int)$m[1] : -1;
		$encoding = preg_match('/(?:^|\n)SALDI_ENC:([^\n]*)(?:\n|$)/', $out, $m) ? strtolower(trim($m[1])) : null;
		clearstatcache(true, $bodyFile);
		$size = @filesize($bodyFile);
		$data = $size === false ? false : @file_get_contents($bodyFile, false, null, 0, SHOP_OFFER_BODY_BYTES);
		@unlink($bodyFile);
		$failure = $unread = '';
		if ($exitCode !== 0 || !$httpCode) {
			$failure = 'curl command failed (exit ' . $exitCode . ')';
		} elseif ($encoding === null || strpos($encoding, '%') !== false) {
			$failure = 'curl did not report the answer\'s encoding (curl 7.84 or later is needed)';
		} elseif ($size === false) {
			$unread = 'no answer file';
		} elseif ($data === false) {
			$unread = 'the answer file could not be read';
		} elseif ($received < 0 || $size !== $received) {
			$unread = 'the answer file holds ' . $size . ' bytes, curl received ' . $received;
		} elseif (strlen($data) !== min($size, SHOP_OFFER_BODY_BYTES)) {
			$unread = 'read ' . strlen($data) . ' of ' . min($size, SHOP_OFFER_BODY_BYTES) . ' bytes';
		}
		return shopOfferAnswer($httpCode, $data === false ? '' : $data, $failure, $size !== false && $size > SHOP_OFFER_BODY_BYTES, $unread, (string)$encoding);
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
