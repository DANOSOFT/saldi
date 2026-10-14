<?php
// Copyright (c) 2026 Danosoft ApS
// 20261005 CDX/PHR Verify total quantities, nested sets, endpoint outcomes and resumable retries.
require_once(__DIR__ . '/../includes/std_func.php');
require_once(__DIR__ . '/../lager/shopStockIncludes/sync.php');
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
function checkShopStock($ok, $message)
{
	if (!$ok) {
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}
$catalog = array('products' => array(), 'parts' => array(), 'quantities' => array(), 'variants' => array(), 'bindings' => array());
foreach (array(1,2,3,4,5,6) as $id) {
	$catalog['products'][$id] = array('id' => $id, 'varenr' => 'Item & ' . $id, 'varenr_alias' => '', 'tracked' => true, 'lukket' => '0');
}
$catalog['quantities'][1] = array(0 => 14.5);
$catalog['quantities'][2] = array(0 => -2);
$catalog['quantities'][3] = array(0 => 9);
$catalog['products'][6]['lukket'] = '1';
$params = shopStockPayload(1,0,$catalog);
checkShopStock($params['stock'] === 14.5 && $params['totalStock'] === 14.5, 'Decimal total stock is sent consistently');
checkShopStock(!isset($params['stockno']) && !isset($params['salesPrice']) && !isset($params['costPrice']), 'No warehouse or price mutation is requested');
checkShopStock(strpos(shopApiUrl('https://example.invalid/api', $params), 'itemNo=Item+%26+1') !== false, 'Item numbers are URL encoded');
checkShopStock(shopStockPayload(2,0,$catalog)['stock'] === -2, 'Negative stock is preserved for ordinary products');
checkShopStock(shopStockPayload(4,0,$catalog)['stock'] === 0, 'Products without stock rows explicitly clear obsolete shop stock');
$catalog['parts'][4] = array(array(1,2),array(3,1));
$catalog['parts'][5] = array(array(4,1),array(1,1));
checkShopStock(shopStockPayload(5,0,$catalog)['stock'] === 4.0, 'Nested sets aggregate shared leaf requirements before calculating availability');
$catalog['parts'][3] = array(array(5,1));
try { shopStockPayload(5,0,$catalog); throw new LogicException('Expected a cycle error'); }
catch (ShopStockException $e) { checkShopStock(true, 'Cyclic sets fail instead of publishing an arbitrary stock value'); }
unset($catalog['parts'][3]);
$catalog['variants'][1][21] = array('id'=>21);
$catalog['quantities'][1] = array(21=>2.5,22=>7);
$catalog['bindings'][1][21] = array('201'=>'201');
checkShopStock(shopStockPayload(1,21,$catalog)['stock'] === 2.5, 'Variant quantities are isolated from other variants');
checkShopStock(!isset(shopStockPayload(1,21,$catalog)['itemNo']), 'Variant updates cannot fall back to the parent SKU');
$catalog['bindings'][1][21]['202']='202';
try { shopStockPayload(1,21,$catalog); throw new LogicException('Expected an ambiguous binding error'); }
catch (ShopStockException $e) { checkShopStock(true, 'Ambiguous variant bindings fail visibly'); }
$catalog['bindings'][1][21] = array('201'=>'201');
$endpoints = array(1=>'https://one.invalid/api',2=>'https://two.invalid/api');
$job = shopStockNewJob($catalog,$endpoints);
checkShopStock(count($job['items']) === 6 && !in_array(array(6,0),$job['items'],true), 'Closed products are excluded and variants are queued separately');
$calls = $saved = array();
$sender = function ($targets,$payload) use (&$calls) {
	$calls[] = array($targets,$payload);
	return array(1=>'',2=>'HTTP 503.');
};
$save = function ($state) use (&$saved) { $saved[]=$state; };
$job = shopStockBatch($job,$catalog,$endpoints,$sender,$save);
checkShopStock($job['cursor'] === 5 && count($saved) === 5, 'Batches are bounded and persist progress after every item');
$job = shopStockBatch($job,$catalog,$endpoints,$sender,$save);
checkShopStock($job['status'] === 'done' && $job['ok'] === 6 && count($job['failed']) === 6, 'Endpoint failures are counted separately from successes');
$paused = $job;
$paused['status'] = 'paused';
$paused['cursor'] = 3;
$failureKey = array_key_first($paused['failed']);
$failure = $paused['failed'][$failureKey];
$paused['retry'] = array(array($failure['id'], $failure['variant'], $failure['shop']), array(2,0,2));
$singleCalls = array();
$singleSender = function ($targets, $payload) use (&$singleCalls) { $singleCalls[] = array_keys($targets); return array(2=>''); };
$retried = shopStockRetryOne($paused, $failureKey, $catalog, $endpoints, $singleSender);
checkShopStock($singleCalls === array(array(2)) && !isset($retried['failed'][$failureKey]) && count($retried['failed']) === 5, 'Single retry sends only the selected failure and preserves other failures');
checkShopStock($retried['cursor'] === 3 && $retried['status'] === 'paused' && $retried['items'] === $paused['items'] && $retried['retry'] === array(array(2,0,2)), 'Single retry preserves the main queue and removes only its successful pending retry');
$stillFailed = shopStockRetryOne($paused, $failureKey, $catalog, $endpoints, function ($targets, $payload) { return array(2=>'HTTP 503.'); });
checkShopStock($stillFailed['failed'] === $paused['failed'] && $stillFailed['ok'] === $paused['ok'] && $stillFailed['retry'] === $paused['retry'], 'An unsuccessful single retry remains available without changing progress');
try { shopStockRetryOne($retried, $failureKey, $catalog, $endpoints, $singleSender); throw new LogicException('Expected stale failure rejection'); }
catch (ShopStockException $e) { checkShopStock(count($singleCalls) === 1, 'A stale retry cannot resend an already successful update'); }
$job['retry'] = array_map(function ($r) { return array($r['id'],$r['variant'],$r['shop']); },array_values($job['failed']));
$job['status']='paused';
$calls=array();
$sender=function ($targets,$payload) use (&$calls) { $calls[]=array_keys($targets);return array(2=>''); };
while ($job['status'] !== 'done') { $job=shopStockBatch($job,$catalog,$endpoints,$sender,$save); }
checkShopStock(count($job['failed']) === 0 && $job['ok'] === 12 && count($calls) === 6 && $calls[0] === array(2), 'Retry sends only failed shop/item pairs');
try { shopStockBatch($job,$catalog,array(1=>'https://changed.invalid/api'),$sender,$save); throw new LogicException('Expected config mismatch'); }
catch (ShopStockException $e) { checkShopStock(true, 'A changed endpoint configuration cannot silently redirect a saved job'); }
checkShopStock(shopStockResponseError(200,0,'{"error":"bad"}') !== '' && shopStockResponseError(200,0,'{"success":false}') !== '' && shopStockResponseError(404,0,'') !== '' && shopStockResponseError(0,28,'') !== '', 'Transport, HTTP and explicit application errors are rejected');
checkShopStock(shopStockResponseError(200,0,'14') === '' && shopStockResponseError(204,0,'') === '', 'Legacy numeric and empty successful responses are supported');

if (!getenv('SALDI_CHAR_DSN')) {
	echo "SKIP: database cases require SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS.\n";
	exit;
}
$pdo=new PDO(getenv('SALDI_CHAR_DSN'),getenv('SALDI_CHAR_PGUSER'),getenv('SALDI_CHAR_PGPASS'),array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
function db_select($sql,$context) { return $GLOBALS['pdo']->query($sql); }
function db_fetch_array($result) { return $result->fetch(PDO::FETCH_ASSOC); }
$pdo->beginTransaction();
try {
	$pdo->exec('CREATE TEMP TABLE grupper(id integer,kodenr integer,art text,fiscal_year integer,box8 text,box4 text,box5 text,box6 text)');
	$pdo->exec('CREATE TEMP TABLE varer(id integer,varenr text,varenr_alias text,gruppe integer,lukket text)');
	$pdo->exec('CREATE TEMP TABLE lagerstatus(vare_id integer,variant_id integer,lager integer,beholdning numeric)');
	$pdo->exec('CREATE TEMP TABLE styklister(indgaar_i integer,vare_id integer,antal numeric)');
	$pdo->exec('CREATE TEMP TABLE variant_varer(id integer,vare_id integer,variant_stregkode text)');
	$pdo->exec('CREATE TEMP TABLE shop_varer(id integer,saldi_id integer,shop_id text,saldi_variant integer,shop_variant text)');
	$pdo->exec("INSERT INTO grupper(id,kodenr,art,fiscal_year,box8) VALUES (1,2,'VG',9,'on'),(2,2,'VG',10,'on'),(3,3,'VG',9,'on'),(4,3,'VG',10,'')");
	$pdo->exec("INSERT INTO grupper(id,art,box4,box5,box6) VALUES (5,'API','https://one.invalid/api','https://two.invalid/api','https://one.invalid/api')");
	$pdo->exec("INSERT INTO varer VALUES (5871,'YQTC0311B','',2,'0'),(5872,'Missing','',2,'0'),(5873,'Service','',3,'0')");
	$pdo->exec('INSERT INTO lagerstatus VALUES (5871,0,2,2),(5871,0,3,4),(5871,0,4,7),(5871,0,7,1),(5871,0,10,0)');
	$catalog=shopStockCatalog(10);
	checkShopStock(shopStockPayload(5871,0,$catalog)['stock'] === 14.0, 'YQTC0311B uses all warehouses and fiscal-year duplicates do not multiply quantities');
	checkShopStock(count(shopStockNewJob($catalog,shopStockEndpoints())['items']) === 2, 'Only the selected fiscal year determines stock-tracked product groups');
	checkShopStock(count(shopStockEndpoints()) === 2, 'Duplicate endpoint URLs are not called twice');
	$pdo->exec('UPDATE lagerstatus SET beholdning=6 WHERE vare_id=5871 AND lager=4');
	checkShopStock(shopStockPayload(5871,0,shopStockCatalog(10))['stock'] === 13.0, 'A resumed batch observes stock movements after job creation');
} finally { $pdo->rollBack(); }
