<?php
// --- includes/stdFunc/shopSyncDocument.php --- ver 5.0.0 --- 2026-10-23 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261023 CDX/MJ SST-876 Prepare document updates before sending bounded batches, with document lines before sets.

/**
 * Request-local payload collection; quantities are rebuilt on every delivery.
 *
 * @return array{
 *   collecting:bool,
 *   tasks:array<int,array{key:string,targets:array,params:array,context:string,userAgent:string,
 *     vare_id:int,variant_id:int,lager:int,mode:string,label:string,parent:bool}>
 * }
 */
function &shopSyncDocumentState() {
	static $state = array('collecting' => false, 'tasks' => array());
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
	$collector = array('collecting' => true, 'tasks' => array());
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
				'attempted' => false, 'label' => '', 'noApi' => false);
			$result = sync_shop_vare($subject['vare_id'], $subject['variant_id'], $subject['lager'], $subject['mode']);
			if ($result === 'no api') {
				$groups[$key]['noApi'] = true;
				$summary['noApi'] = true;
				break;
			}
			// Supports callers whose sender reports a failure before producing any HTTP payload.
			if (shopSyncFailed($result)) {
				$groups[$key]['failed'] = $groups[$key]['retry'] = $groups[$key]['attempted'] = true;
			}
		}
	} finally {
		$collector['collecting'] = false;
		shopSyncFlushRunning(-1);
	}
	$tasks = $collector['tasks'];
	$collector['tasks'] = array();
	// Separate passes guarantee all document lines precede every parent, even when sets are disjoint.
	$ordered = array();
	foreach (array(false, true) as $parent) {
		foreach ($tasks as $task) {
			if ($task['parent'] !== $parent || ($parent && isset($own[$task['key']]))) {
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
					'attempted' => false, 'label' => '', 'noApi' => false);
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
