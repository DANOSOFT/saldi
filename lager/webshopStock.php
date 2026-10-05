<?php
// --- lager/webshopStock.php --- 2026-10-05 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261005 CDX/PHR Add an authenticated, resumable total-stock update page for connected shops.
// 20261005 CDX/PHR Allow retrying an individual failed item/shop without advancing the queue.
/**
 * Injected by connect.php and online.php, included below:
 * @var string $db
 * @var string $rettigheder
 * @var int $regnaar
 */
session_start();
$s_id = session_id();
$title = 'Opdater webshopbeholdning';
$modulnr = 9;
$webservice = 'on';
$css = '../css/standard.css';
ob_start();
require_once(__DIR__ . '/../includes/std_func.php');
require_once(__DIR__ . '/../includes/connect.php');
$onlineResult = include(__DIR__ . '/../includes/online.php');
ob_end_clean();
require_once(__DIR__ . '/shopStockIncludes/sync.php');

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$isJson = $isPost || ifset($_GET, 'status') === '1';
$action = ifset($_POST, 'action', '');
$action = is_string($action) ? $action : '';
$lock = null;
try {
	if ($onlineResult === 'Session expired' || empty($db) || !preg_match('/^[a-zA-Z0-9_]+$/D', $db) || substr((string) $rettigheder, 9, 1) !== '1') {
		http_response_code(403);
		throw new ShopStockException('Du skal være logget ind med skriveret til varer.');
	}
	if (!isset($_SESSION['shop_stock_csrf'])) {
		$_SESSION['shop_stock_csrf'] = bin2hex(random_bytes(32));
	}
	$csrf = $_SESSION['shop_stock_csrf'];
	$submittedCsrf = ifset($_POST, 'csrf', '');
	if ($isPost && (!is_string($submittedCsrf) || !hash_equals($csrf, $submittedCsrf))) {
		http_response_code(403);
		throw new ShopStockException('Siden er udløbet. Genindlæs siden.');
	}
	session_write_close();
	$directory = sys_get_temp_dir() . '/saldi-shop-stock-' . substr(hash('sha256', __DIR__ . ':' . $db), 0, 24);
	if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
		throw new ShopStockException('Statusmappen kunne ikke oprettes.');
	}
	$path = $directory . '/.webshop-stock-sync.json';
	$lock = fopen($directory . '/.webshop-stock-sync.lock', 'c');
	if (!$lock || !flock($lock, ($isPost ? LOCK_EX : LOCK_SH) | LOCK_NB)) {
		http_response_code(409);
		throw new ShopStockException('En opdatering er allerede i gang. Prøv igen om et øjeblik.');
	}
	$job = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : null;
	$endpoints = shopStockEndpoints();
	if ($isPost) {
		if ($action === 'start') {
			if ($job && !in_array($job['status'], array('done', 'cancelled'), true)) {
				throw new ShopStockException('Der findes en uafsluttet opdatering. Fortsæt eller afslut den først.');
			}
			$selected = array();
			foreach ((array) ifset($_POST, 'shops', array()) as $slot) {
				$slot = (int) $slot;
				if (isset($endpoints[$slot])) {
					$selected[$slot] = $endpoints[$slot];
				}
			}
			ksort($selected);
			if (!$selected) {
				throw new ShopStockException('Vælg mindst én webshop.');
			}
			$job = shopStockNewJob(shopStockCatalog($regnaar), $selected);
			$job['year'] = (int) $regnaar;
		} else {
			$submittedJob = ifset($_POST, 'job', '');
			if (!$job || !is_string($submittedJob) || !hash_equals($job['id'], $submittedJob)) {
				http_response_code(409);
				throw new ShopStockException('Kørslen er ændret. Genindlæs siden.');
			}
			if ($action === 'cancel') {
				$job['status'] = 'cancelled';
			} elseif ($action === 'retry' && $job['status'] === 'done') {
				$job['retry'] = array();
				foreach ($job['failed'] as $failure) {
					$job['retry'][] = array($failure['id'], $failure['variant'], $failure['shop']);
				}
				if ($job['retry']) {
					$job['status'] = 'paused';
				}
			} elseif (($action === 'batch' && $job['status'] === 'paused') || $action === 'retry_one') {
				$selected = array();
				foreach ($job['shops'] as $slot) {
					if (!isset($endpoints[$slot])) {
						throw new ShopStockException('Webshopopsætningen er ændret. Afslut kørslen og start en ny.');
					}
					$selected[$slot] = $endpoints[$slot];
				}
				if ($action === 'retry_one') {
					$job = shopStockRetryOne($job, ifset($_POST, 'failure', ''), shopStockCatalog($job['year']), $selected, 'shopStockSend');
				} else {
					$job = shopStockBatch($job, shopStockCatalog($job['year']), $selected, 'shopStockSend', function ($state) use ($path) {
						shopStockSave($path, $state);
					});
				}
			} elseif ($action !== 'batch' || $job['status'] !== 'done') {
				throw new ShopStockException('Handlingen er ikke tilgængelig for denne kørsel.');
			}
		}
		shopStockSave($path, $job);
	}
	if (ifset($_GET, 'errors') === '1') {
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="webshop-fejl.csv"');
		$output = fopen('php://output', 'w');
		fputcsv($output, array('Varenummer', 'Variant', 'Webshop', 'Fejl'), ';', '"', '');
		foreach ($job ? $job['failed'] : array() as $failure) {
			$values = array($failure['item'], $failure['variant'], $failure['shop'], $failure['error']);
			foreach ($values as &$value) {
				if (preg_match('/^[=+@\-\t\r]/', (string) $value)) {
					$value = "'" . $value;
				}
			}
			unset($value);
			fputcsv($output, $values, ';', '"', '');
		}
		fclose($output);
	} elseif ($isJson) {
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode(array('job' => $job ? shopStockProgress($job) : null), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	} else {
		$progress = $job ? shopStockProgress($job) : null;
		include(__DIR__ . '/shopStockIncludes/page.php');
	}
} catch (Throwable $e) {
	if (http_response_code() < 400) {
		http_response_code(400);
	}
	// Do not expose SQL, credentials or endpoint URLs in a browser response.
	$message = $e instanceof ShopStockException ? $e->getMessage() : 'Opdateringen kunne ikke gennemføres. Genindlæs siden og prøv igen.';
	if ($isJson) {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array('error' => $message), JSON_UNESCAPED_UNICODE);
	} else {
		echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><a href="lister/vareliste.php">Tilbage til varelisten</a>';
	}
} finally {
	if (is_resource($lock)) {
		flock($lock, LOCK_UN);
		fclose($lock);
	}
}
