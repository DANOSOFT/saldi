<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/shopSyncDocument.php --- ver 5.0.0 --- 2026-10-27 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261023 CDX/MJ SST-876 Prepare document updates before sending bounded batches, with document lines before sets.
// 20261026 CDX/MJ SST-876 Recover queued sets through their original part path and retain claims when no payload was sent.
// 20261027 CDX/MJ SST-876 Retain correction markers for any successful low-level send inside a transaction, including parent sets.

/**
 * Request-local payload collection; quantities are rebuilt on every delivery.
 *
 * @return array{
 *   collecting:bool,
 *   backlog:bool,
 *   tasks:array<int,array{key:string,targets:array,params:array,context:string,userAgent:string,
 *     vare_id:int,variant_id:int,lager:int,mode:string,label:string,parent:bool}>
 * }
 */
function &shopSyncDocumentState() {
	static $state = array('collecting' => false, 'backlog' => false, 'tasks' => array());
	return $state;
}

/**
 * Collects a send while a document is being prepared; direct item-card calls still send immediately.
 *
 * @param array $targets Configured shop endpoints by slot.
 * @param array $params Adapter query parameters.
 * @param string $context Per-item log context.
 * @param int $id Item id.
 * @param int $variant Variant id, or zero.
 * @param int $warehouse Warehouse number.
 * @param string $mode Stock-only or full product synchronization.
 * @param string $label Item number for the document report.
 * @param string $userAgent Existing adapter user agent.
 * @return bool Whether the payload was collected instead of sent.
 */
function shopSyncCollect(array $targets, array $params, $context, $id, $variant, $warehouse, $mode, $label, $userAgent) {
	$state = &shopSyncDocumentState();
	if (!$state['collecting']) {
		return false;
	}
	$key = shopSyncQueueKey($id, $variant, $warehouse);
	$state['tasks'][] = array('key' => $key, 'targets' => $targets, 'params' => $params,
		'context' => $context, 'userAgent' => $userAgent, 'vare_id' => (int)$id, 'variant_id' => (int)$variant,
		'lager' => (int)$warehouse, 'mode' => $mode, 'label' => $label,
		'parent' => strpos($context, ' part vare_id ') !== false);
	return true;
}

/**
 * Whether a queued product is an assembled item, independently of its stock-managed group.
 * Existing markers carry only item ids, so check the structure rather than requiring a new marker format.
 *
 * @param array $entry Pending item/variant/warehouse entry.
 * @return bool
 */
function shopSyncIsQueuedSet(array $entry) {
	if ((int)$entry['variant_id'] !== 0) {
		return false;
	}
	$id = (int)$entry['vare_id'];
	return (bool)db_fetch_array(db_select(
		"select indgaar_i from styklister where indgaar_i = $id limit 1",
		__FILE__ . ' line ' . __LINE__));
}

/**
 * Rebuilds the original parent-set payloads with current calculated availability.
 * A set's own group need not be stock managed; its components determine its availability.
 *
 * @param int $id Assembled item id.
 * @param int $warehouse Warehouse number.
 * @return array{
 *   stock:array{update_stock:mixed,stock:string,totalStock:string,stockno:int,costPrice:mixed,
 *     itemNo:string,itemNoAlias:mixed,sku:string,skuAlias:mixed},
 *   cost:array{costPrice:mixed,sku:string,skuAlias:mixed},
 *   label:string
 * }|null Null when the product no longer supplies a payload.
 */
function shopSyncSetPayloads($id, $warehouse) {
	$id = (int)$id;
	$warehouse = (int)$warehouse;
	$product = db_fetch_array(db_select(
		"select varenr,varenr_alias,kostpris from varer where id = '$id'",
		__FILE__ . ' line ' . __LINE__));
	if (!$product) {
		return null;
	}
	$binding = db_fetch_array(db_select("select shop_id from shop_varer where saldi_id = $id",
		__FILE__ . ' line ' . __LINE__));
	list($total, $stock) = explode('|', getAvailable($id, $warehouse));
	$cost = array('costPrice' => $product['kostpris'], 'sku' => $product['varenr'],
		'skuAlias' => $product['varenr_alias']);
	return array('stock' => array('update_stock' => $binding['shop_id'] ?? 0, 'stock' => $stock,
		'totalStock' => $total, 'stockno' => $warehouse, 'costPrice' => $product['kostpris'],
		'itemNo' => $product['varenr'], 'itemNoAlias' => $product['varenr_alias'],
		'sku' => $product['varenr'], 'skuAlias' => $product['varenr_alias']),
		'cost' => $cost, 'label' => (string)$product['varenr']);
}

/**
 * Prepares a queued set through the same part payloads as its original component-triggered send.
 * Must run while the document collector is active; sends remain bounded by its normal batch budget.
 *
 * @param array $entry Queued set id, warehouse and stock/all mode.
 * @return string 'OK', 'no api' or 'no stock' when no set payload could be prepared.
 */
function shopSyncCollectQueuedSet(array $entry) {
	$id = (int)$entry['vare_id'];
	$warehouse = (int)$entry['lager'];
	$configuration = db_fetch_array(db_select("select box4, box5, box6 from grupper where art='API'",
		__FILE__ . ' line ' . __LINE__));
	$targets = array();
	foreach (array('box4', 'box5', 'box6') as $slot => $column) {
		$url = trim((string)($configuration[$column] ?? ''));
		if ($url !== '') {
			$targets[$slot] = $url;
		}
	}
	if (!$targets) {
		return 'no api';
	}
	$sent = &shopSyncSentSets();
	$key = $id . ':' . $warehouse;
	$collector = &shopSyncDocumentState();
	$queueKey = shopSyncQueueKey($id, 0, $warehouse);
	$hasStock = $hasCost = false;
	foreach ($collector['tasks'] as $task) {
		if ($task['key'] === $queueKey && $task['parent']) {
			$hasStock = $hasStock || isset($task['params']['update_stock']);
			$hasCost = $hasCost || !isset($task['params']['update_stock']);
		}
	}
	if ($hasStock && ($entry['mode'] === 'stock' || $hasCost)) {
		return 'OK';
	}
	$payloads = shopSyncSetPayloads($id, $warehouse);
	if ($payloads === null) {
		return 'no stock';
	}
	$sent[$key] = true;
	$userAgent = 'Mozilla/5.0 Gecko/20100101 Firefox/23.0';
	if (!$hasStock) {
		shopSyncCollect($targets, $payloads['stock'], "sync_shop_vare update_stock part vare_id $id",
			$id, 0, $warehouse, $entry['mode'], $payloads['label'], $userAgent);
	}
	if ($entry['mode'] !== 'stock' && !$hasCost) {
		shopSyncCollect($targets, $payloads['cost'], "sync_shop_vare costPrice part vare_id $id",
			$id, 0, $warehouse, $entry['mode'], $payloads['label'], $userAgent);
	}
	return 'OK';
}

/**
 * Prepares current figures, sends ten payloads per round, and resolves each item's own claim.
 * Parent sets and backlog failures never become document-line warnings.
 *
 * @param array $entries Items or pending queue entries.
 * @param bool $report Whether these are the current document's lines.
 * @param float $seconds Optional additional wall-clock budget (backlog only).
 * @param int $maxAttempts Actual failed deliveries before reporting a final failure.
 * @return array{failed:int,delivered:int,noApi:bool}
 */
function shopSyncRunDocument(array $entries, $report = true, $seconds = 0, $maxAttempts = 5) {
	$collector = &shopSyncDocumentState();
	$apiState = &shopApiSyncState();
	$started = microtime(true);
	$budget = $seconds > 0 ? min(30, $apiState['spent'] + $seconds) : 30;
	$groups = $own = array();
	$summary = array('failed' => 0, 'delivered' => 0, 'noApi' => false);
	$collector = array('collecting' => true, 'backlog' => !$report, 'tasks' => array());
	shopSyncResetSentSets();
	shopSyncFlushRunning(1);
	try {
		foreach ($entries as $entry) {
			if ($seconds > 0 && microtime(true) - $started >= $seconds) {
				break;
			}
			$key = shopSyncQueueKey($entry['vare_id'], $entry['variant_id'], $entry['lager']);
			$own[$key] = true;
			$dir = shopSyncQueueDir(false);
			$pending = $dir === '' ? null : shopSyncQueueRead($dir . $key);
			$claim = $pending ? shopSyncQueueClaim($pending) : null;
			if ($pending && !$claim) {
				continue;
			}
			$subject = $claim ?: $entry;
			$groups[$key] = array('claim' => $claim, 'failed' => false, 'retry' => false,
				'attempted' => false, 'sent' => false, 'label' => '', 'noApi' => false);
			$queuedSet = !$report && shopSyncIsQueuedSet($subject);
			$result = $queuedSet ? shopSyncCollectQueuedSet($subject)
				: sync_shop_vare($subject['vare_id'], $subject['variant_id'], $subject['lager'], $subject['mode']);
			if ($result === 'no api') {
				$groups[$key]['noApi'] = true;
				$summary['noApi'] = true;
				break;
			}
			if ($queuedSet && $result === 'no stock') {
				// Nothing was sent. Keep the claim pending without charging a transport attempt.
				$groups[$key]['failed'] = $groups[$key]['retry'] = true;
			}
			// Supports callers whose sender reports a failure before producing any HTTP payload.
			if (shopSyncFailed($result)) {
				$groups[$key]['failed'] = $groups[$key]['retry'] = $groups[$key]['attempted'] = true;
			}
		}
	} finally {
		$collector['collecting'] = false;
		$collector['backlog'] = false;
		shopSyncFlushRunning(-1);
	}
	$tasks = $collector['tasks'];
	$collector['tasks'] = array();
	$direct = array();
	foreach ($tasks as $task) {
		if (!$task['parent']) {
			$direct[$task['key']] = true;
		}
	}
	// Separate passes guarantee all document lines precede every parent, even when sets are disjoint.
	$ordered = array();
	foreach (array(false, true) as $parent) {
		foreach ($tasks as $task) {
			if ($task['parent'] !== $parent || ($parent && isset($direct[$task['key']]))) {
				continue;
			}
			$key = $task['key'];
			if (!isset($groups[$key])) {
				if (!shopSyncQueueAdd($task['vare_id'], $task['variant_id'], $task['lager'], $task['mode'])) {
					error_log('saldi: cannot persist shop update for ' . $key);
				}
				$dir = shopSyncQueueDir(false);
				$pending = $dir === '' ? null : shopSyncQueueRead($dir . $key);
				$claim = $pending ? shopSyncQueueClaim($pending) : null;
				if ($pending && !$claim) {
					continue;
				}
				$groups[$key] = array('claim' => $claim, 'failed' => false, 'retry' => false,
					'attempted' => false, 'sent' => false, 'label' => '', 'noApi' => false);
			}
			$groups[$key]['label'] = $task['label'];
			$ordered[] = $task;
		}
	}
	$directory = shopSyncQueueDir(false);
	$log = $ordered && $directory !== '' ? fopen(dirname(rtrim($directory, '/')) . '/rest_api.log', 'a') : null;
	try {
		foreach (array_chunk($ordered, 10) as $batch) {
			$urls = $contexts = $owners = array();
			foreach ($batch as $i => $task) {
				foreach ($task['targets'] as $slot => $endpoint) {
					$index = $i . ':' . $slot;
					$urls[$index] = shopApiUrl($endpoint, $task['params']);
					$contexts[$index] = $task['context'];
					$owners[$index] = $task['key'];
				}
			}
			$options = array('contexts' => $contexts, 'timeBudget' => $budget, 'userAgent' => $batch[0]['userAgent']);
			if ($seconds > 0) {
				// Includes preparation time, and caps both initial calls and retries at the deadline.
				$remaining = max(0, $seconds - (microtime(true) - $started));
				$options['timeBudget'] = min($budget, $apiState['spent'] + $remaining);
				if ($remaining < 1) {
					$options['timeBudget'] = max(0.001, $apiState['spent']);
				}
			}
			$results = shopApiRequestParallel($urls, array(), $log, $options);
			foreach ($results as $index => $res) {
				$key = $owners[$index];
				$groups[$key]['sent'] = $groups[$key]['sent'] || $res['ok'];
				if (!$res['ok']) {
					$groups[$key]['failed'] = true;
					if (shopSyncWorthRetrying($res)) {
						$groups[$key]['retry'] = true;
						$groups[$key]['attempted'] = $groups[$key]['attempted'] || $res['attempts'] > 0;
					}
				}
			}
		}
	} finally {
		if (is_resource($log)) {
			fclose($log);
		}
	}
	foreach ($groups as $key => $group) {
		$claim = $group['claim'];
		if ($group['noApi']) {
			if ($claim) {
				shopSyncQueueRelease($claim, $maxAttempts, false);
			}
			continue;
		}
		if ($group['retry'] && $claim) {
			$final = shopSyncQueueRelease($claim, $maxAttempts, $group['attempted']);
			if ($final && $group['attempted']) {
				error_log('saldi: shop stock sync failed repeatedly for ' . $key);
			}
		} elseif ($claim && !$group['failed'] && $group['sent'] && !empty($GLOBALS['db_transaktion_depth'])) {
			// Defensive direct callers may send before commit. Retain correction work for rollback.
			shopSyncQueueRelease($claim, $maxAttempts, false);
		} elseif ($claim) {
			// An answered refusal is reported, not retried indefinitely as if it were transport failure.
			shopSyncQueueDone($claim);
		}
		if ($group['failed']) {
			$summary['failed']++;
			if ($report && isset($own[$key])) {
				shopSyncNoteFailedItem($group['label']);
				if ($group['retry']) {
					$state = &shopSyncReportState();
					$state['queued'][$group['label']] = $group['label'];
				}
			}
		} else {
			$summary['delivered']++;
		}
	}
	return $summary;
}

/**
 * Prepares and sends small groups so set-availability queries cannot consume the entire backlog budget before sending.
 *
 * @param array $entries Snapshot of pending entries, oldest first.
 * @param float $seconds Additional wall-clock allowance.
 * @param int $maxAttempts Actual failed deliveries before reporting a final failure.
 * @return array{failed:int,delivered:int,noApi:bool}
 */
function shopSyncRunBacklog(array $entries, $seconds = 5, $maxAttempts = 5) {
	$started = microtime(true);
	$summary = array('failed' => 0, 'delivered' => 0, 'noApi' => false);
	foreach (array_chunk($entries, 5) as $batch) {
		$remaining = $seconds - (microtime(true) - $started);
		if ($remaining < 1) {
			break;
		}
		$result = shopSyncRunDocument($batch, false, $remaining, $maxAttempts);
		$summary['failed'] += $result['failed'];
		$summary['delivered'] += $result['delivered'];
		if ($result['noApi']) {
			$summary['noApi'] = true;
			break;
		}
	}
	return $summary;
}
