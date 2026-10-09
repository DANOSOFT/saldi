<?php
// --- lager/shopStockIncludes/sync.php --- 2026-10-05 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261005 CDX/PHR Add resumable total-stock synchronization without price or warehouse updates.
// 20261005 CDX/PHR Allow retrying an individual failed item/shop without advancing the queue.

class ShopStockException extends RuntimeException {}

/** @return array<int, array> Database rows, fetched without modifying inventory. */
function shopStockRows($sql)
{
	$rows = array();
	$result = db_select($sql, __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($result)) {
		$rows[] = $row;
	}
	return $rows;
}

/** @return array<int, string> Configured, deduplicated shop endpoints keyed by API slot. */
function shopStockEndpoints()
{
	$rows = shopStockRows("SELECT box4,box5,box6 FROM grupper WHERE art='API' ORDER BY id");
	$endpoints = array();
	foreach ($rows as $row) {
		foreach (array(1 => 'box4', 2 => 'box5', 3 => 'box6') as $slot => $column) {
			$url = trim((string) ifset($row, $column, ''));
			if ($url !== '' && !in_array($url, $endpoints, true) && !isset($endpoints[$slot])) {
				if (shopApiUrl($url) === '') {
					throw new ShopStockException('Webshop ' . $slot . ' har en ugyldig API-adresse.');
				}
				$endpoints[$slot] = $url;
			}
		}
	}
	return $endpoints;
}

/** @return array{products: array, parts: array, quantities: array, variants: array, bindings: array} */
function shopStockCatalog($year)
{
	$year = (int) $year;
	$products = $parts = $quantities = $variants = $bindings = array();
	$groups = array();
	foreach (shopStockRows("SELECT kodenr FROM grupper WHERE art='VG' AND fiscal_year=$year AND box8='on'") as $row) {
		$groups[(int) $row['kodenr']] = true;
	}
	foreach (shopStockRows('SELECT id,varenr,varenr_alias,gruppe,lukket FROM varer ORDER BY id') as $row) {
		$row['tracked'] = isset($groups[(int) $row['gruppe']]);
		$products[(int) $row['id']] = $row;
	}
	foreach (shopStockRows('SELECT indgaar_i,vare_id,antal FROM styklister ORDER BY indgaar_i,vare_id') as $row) {
		$parts[(int) $row['indgaar_i']][] = array((int) $row['vare_id'], (float) $row['antal']);
	}
	// Refresh these totals for every batch, rather than freezing stock at job creation.
	foreach (shopStockRows('SELECT vare_id,coalesce(variant_id,0) AS variant_id,sum(beholdning) AS quantity FROM lagerstatus GROUP BY vare_id,coalesce(variant_id,0)') as $row) {
		$quantities[(int) $row['vare_id']][(int) $row['variant_id']] = (float) $row['quantity'];
	}
	foreach (shopStockRows('SELECT id,vare_id,variant_stregkode FROM variant_varer ORDER BY id') as $row) {
		$variants[(int) $row['vare_id']][(int) $row['id']] = $row;
	}
	foreach (shopStockRows('SELECT saldi_id,shop_id,saldi_variant,shop_variant FROM shop_varer ORDER BY id') as $row) {
		$id = (int) $row['saldi_id'];
		$variant = (int) $row['saldi_variant'];
		$value = $variant ? $row['shop_variant'] : $row['shop_id'];
		if ($value !== null && $value !== '' && $value !== '0') {
			$bindings[$id][$variant][(string) $value] = (string) $value;
		}
	}
	return compact('products', 'parts', 'quantities', 'variants', 'bindings');
}

/** @return bool Whether a product is open and eligible for stock synchronization. */
function shopStockEligible(array $product, array $parts)
{
	return !in_array(strtolower(trim((string) $product['lukket'])), array('1', 'on'), true)
		&& ($product['tracked'] || isset($parts[(int) $product['id']]));
}

/** @return array<int, float> Required stock-tracked leaf quantities, including shared nested components. */
function shopStockRequirements($id, array $catalog, array $path = array())
{
	if (isset($path[$id])) {
		throw new ShopStockException('Cirkulær stykliste.');
	}
	if (!isset($catalog['products'][$id])) {
		throw new ShopStockException('En delvare findes ikke.');
	}
	$path[$id] = true;
	if (!isset($catalog['parts'][$id])) {
		return $catalog['products'][$id]['tracked'] ? array($id => 1.0) : array();
	}
	$required = array();
	foreach ($catalog['parts'][$id] as $part) {
		$leaves = shopStockRequirements($part[0], $catalog, $path);
		if (!$leaves) {
			continue;
		}
		if ($part[1] <= 0) {
			throw new ShopStockException('Stykliste med manglende eller ugyldigt antal.');
		}
		foreach ($leaves as $leaf => $quantity) {
			$required[$leaf] = ifset($required, $leaf, 0) + $quantity * $part[1];
		}
	}
	return $required;
}

/** @return array Query parameters for a total-stock update, never prices or a warehouse number. */
function shopStockPayload($id, $variant, array $catalog)
{
	if (!isset($catalog['products'][$id]) || !shopStockEligible($catalog['products'][$id], $catalog['parts'])) {
		throw new ShopStockException('Varen er lukket, slettet eller ikke lagerført.');
	}
	$product = $catalog['products'][$id];
	$quantities = ifset($catalog['quantities'], $id, array());
	$quantity = array_sum($quantities);
	$binding = array_values(ifset($catalog['bindings'], array($id, $variant), array()));
	if (count($binding) > 1) {
		throw new ShopStockException('Varen har flere forskellige webshopbindinger.');
	}
	$shopId = $binding ? $binding[0] : 0;
	if ($variant) {
		if (!isset($catalog['variants'][$id][$variant]) || !$shopId) {
			throw new ShopStockException('Varianten mangler en entydig webshopbinding.');
		}
		$quantity = ifset($quantities, $variant, 0);
	} elseif (isset($catalog['parts'][$id])) {
		$required = shopStockRequirements($id, $catalog);
		if (!$required) {
			throw new ShopStockException('Styklisten har ingen lagerførte dele.');
		}
		$available = array();
		foreach ($required as $leaf => $amount) {
			$available[] = floor(array_sum(ifset($catalog['quantities'], $leaf, array())) / $amount);
		}
		$quantity = max(0, min($available));
	}
	if (!is_finite((float) $quantity) || (!$shopId && trim((string) $product['varenr']) === '')) {
		throw new ShopStockException('Varen mangler et varenummer eller har en ugyldig beholdning.');
	}
	$params = array('update_stock' => $shopId, 'stock' => $quantity, 'totalStock' => $quantity);
	if (!$variant) {
		$params['itemNo'] = $product['varenr'];
		$params['itemNoAlias'] = $product['varenr_alias'];
	}
	return $params;
}

/** @return string Empty for a successful HTTP response, otherwise a safe error without credentials. */
function shopStockResponseError($code, $curlError, $body)
{
	if ($curlError) {
		return 'Forbindelsesfejl (' . (int) $curlError . ').';
	}
	if ($code < 200 || $code >= 300) {
		return 'HTTP ' . (int) $code . '.';
	}
	$data = json_decode($body, true);
	if (is_array($data) && (ifset($data, 'success', true) === false || ifset($data, 'ok', true) === false || !empty($data['error']) || !empty($data['errors']) || (int) ifset($data, array('data', 'status'), 0) >= 400 || in_array(ifset($data, 'status', ''), array('error', 'failed', 'failure'), true))) {
		return 'Webshoppen returnerede en fejl.';
	}
	if (preg_match('/^\s*(?:error\b|fatal error\b|<br\s*\/?>\s*<b>(?:warning|fatal error))/i', $body)) {
		return 'Webshoppen returnerede en fejl.';
	}
	return '';
}

/** @return array<int, string> Per-shop result: an empty string on HTTP success, otherwise an error. */
function shopStockSend(array $endpoints, array $params)
{
	$multi = curl_multi_init();
	$handles = $results = $bodies = array();
	try {
		foreach ($endpoints as $slot => $endpoint) {
			$handle = curl_init(shopApiUrl($endpoint, $params));
			$bodies[$slot] = '';
			curl_setopt_array($handle, array(
				CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 12,
				CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
				CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
				CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
				CURLOPT_USERAGENT => 'Saldi total-stock synchronization',
				CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$bodies, $slot) {
					// Bound response memory even when an adapter returns a full HTML page.
					if (strlen($bodies[$slot]) < 16384) {
						$bodies[$slot] .= substr($chunk, 0, 16384 - strlen($bodies[$slot]));
					}
					return strlen($chunk);
				}
			));
			$handles[$slot] = $handle;
			curl_multi_add_handle($multi, $handle);
		}
		do {
			$status = curl_multi_exec($multi, $running);
			if ($status !== CURLM_OK) {
				throw new ShopStockException('Webshopforbindelsen kunne ikke startes.');
			}
			if ($running && curl_multi_select($multi, 0.2) === -1) {
				usleep(10000);
			}
		} while ($running);
		foreach ($handles as $slot => $handle) {
			$results[$slot] = shopStockResponseError(curl_getinfo($handle, CURLINFO_HTTP_CODE), curl_errno($handle), $bodies[$slot]);
		}
	} finally {
		foreach ($handles as $handle) {
			curl_multi_remove_handle($multi, $handle);
			curl_close($handle);
		}
		curl_multi_close($multi);
	}
	return $results;
}

/** @return array Persistent job state. No endpoint credentials are stored in it. */
function shopStockNewJob(array $catalog, array $endpoints)
{
	$items = array();
	foreach ($catalog['products'] as $id => $product) {
		if (!shopStockEligible($product, $catalog['parts'])) {
			continue;
		}
		$items[] = array($id, 0);
		foreach (ifset($catalog['variants'], $id, array()) as $variant => $row) {
			$items[] = array($id, (int) $variant);
		}
	}
	return array('id' => bin2hex(random_bytes(16)), 'created' => date('c'), 'items' => $items,
		'cursor' => 0, 'status' => 'paused', 'shops' => array_keys($endpoints),
		'config' => hash('sha256', json_encode($endpoints)), 'ok' => 0,
		'failed' => array(), 'retry' => array(), 'last' => '', 'updated' => date('c'));
}

/** @return array Public progress; internal queue and configuration fingerprints stay on the server. */
function shopStockProgress(array $job)
{
	$errors = array_values($job['failed']);
	return array('id' => $job['id'], 'created' => $job['created'], 'status' => $job['status'],
		'processed' => $job['cursor'], 'total' => count($job['items']), 'ok' => $job['ok'],
		'failed' => count($errors), 'errors' => array_slice($errors, 0, 100),
		'retrying' => count($job['retry']), 'last' => $job['last'], 'updated' => $job['updated']);
}

/** Persist after each item so reloads and browser failures resume the same job. */
function shopStockSave($path, array $job)
{
	$tmp = $path . '.new';
	$json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	if (file_put_contents($tmp, $json, LOCK_EX) !== strlen($json)) {
		throw new ShopStockException('Status kunne ikke gemmes.');
	}
	chmod($tmp, 0600);
	if (!rename($tmp, $path)) {
		throw new ShopStockException('Status kunne ikke gemmes.');
	}
}

/** @return array Job state after a bounded batch. Sender injection supports offline regression tests. */
function shopStockBatch(array $job, array $catalog, array $endpoints, callable $sender, callable $save)
{
	if (!hash_equals($job['config'], hash('sha256', json_encode($endpoints)))) {
		throw new ShopStockException('Webshopopsætningen er ændret. Afslut kørslen og start en ny opdatering.');
	}
	$start = microtime(true);
	for ($n = 0; $n < 5 && microtime(true) - $start < 8; $n++) {
		$retry = !empty($job['retry']);
		if ($retry) {
			$task = $job['retry'][0];
			list($id, $variant, $slot) = $task;
			$targets = array($slot => $endpoints[$slot]);
		} elseif ($job['cursor'] < count($job['items'])) {
			list($id, $variant) = $job['items'][$job['cursor']];
			$targets = $endpoints;
		} else {
			$job['status'] = 'done';
			break;
		}
		$name = ifset($catalog['products'], array($id, 'varenr'), '#' . $id) . ($variant ? ' (variant ' . $variant . ')' : '');
		try {
			$params = shopStockPayload($id, $variant, $catalog);
			$results = $sender($targets, $params);
		} catch (ShopStockException $e) {
			$results = array_fill_keys(array_keys($targets), $e->getMessage());
		}
		foreach ($targets as $slot => $unused) {
			$key = $id . ':' . $variant . ':' . $slot;
			$error = ifset($results, $slot, 'Intet svar fra webshoppen.');
			if ($error === '') {
				$job['ok']++;
				unset($job['failed'][$key]);
			} else {
				$job['failed'][$key] = array('id' => $id, 'variant' => $variant, 'shop' => $slot, 'item' => $name, 'error' => $error);
			}
		}
		if ($retry) {
			array_shift($job['retry']);
		} else {
			$job['cursor']++;
		}
		$job['last'] = $name;
		$job['updated'] = date('c');
		$job['status'] = ($job['cursor'] === count($job['items']) && !$job['retry']) ? 'done' : 'paused';
		$save($job);
	}
	return $job;
}

/**
 * Retry one recorded failure without advancing the main queue or other retries.
 *
 * @return array Updated persistent job state.
 */
function shopStockRetryOne(array $job, $key, array $catalog, array $endpoints, callable $sender)
{
	if (!is_string($key) || !isset($job['failed'][$key]) || !in_array($job['status'], array('paused', 'done'), true)) {
		throw new ShopStockException('Fejlen er ikke længere tilgængelig. Genindlæs siden.');
	}
	$failure = $job['failed'][$key];
	$single = $job;
	$single['items'] = array();
	$single['cursor'] = 0;
	$single['retry'] = array(array($failure['id'], $failure['variant'], $failure['shop']));
	$single = shopStockBatch($single, $catalog, $endpoints, $sender, function ($state) {});
	foreach (array('ok', 'failed', 'last', 'updated') as $field) {
		$job[$field] = $single[$field];
	}
	if (!isset($job['failed'][$key])) {
		$job['retry'] = array_values(array_filter($job['retry'], function ($task) use ($failure) {
			return $task !== array($failure['id'], $failure['variant'], $failure['shop']);
		}));
	}
	return $job;
}
