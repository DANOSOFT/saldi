<?php
// Copyright (c) 2026 Danosoft ApS
// 20261028 CDX/MJ SST-876 Model a concurrent sale between HTTP batches and verify exhausted budgets do no preparation.
putenv('SALDI_SHOP_FIXTURE_ONLY=1');
putenv('SALDI_SHOP_REAL_TRANSPORT=1');
function shopApiCurlMultiCall(array $urls, $timeout, $connect, $userAgent) {
    $GLOBALS['shopRounds'][] = array_values($urls);
    $result = [];
    foreach ($urls as $slot => $url) {
        $result[$slot] = ['httpCode' => 200, 'body' => '67', 'error' => '', 'transient' => false, 'unreachable' => false];
    }
    // A concurrent POS transaction commits after the first ten document lines are sent.
    $GLOBALS['fixtureStock'] = '66';
    foreach ($GLOBALS['setAvailability'] as $id => $value) {
        $GLOBALS['setAvailability'][$id] = '44|44';
    }
    return $result;
}
require __DIR__ . '/shopSync/test_sst876_item_fanout.php';
ini_set('error_log', '/dev/stderr');
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    reset_fanout();
    $GLOBALS['fixtureStock'] = '67';
    for ($i = 20000; $i < 20011; $i++) {
        $parent = $i + 10000;
        $ITEMS[$i] = ['varenr' => 'LINE' . $i, 'delvare' => 'on', 'gruppe' => 1, 'parents' => [$parent]];
        $ITEMS[$parent] = ['varenr' => 'SET' . $parent, 'delvare' => '', 'gruppe' => 12, 'parents' => []];
        $GLOBALS['setAvailability'][$parent] = '67|67';
        shopSyncMark($i, 0, 4, 'stock');
    }
    check(shopSyncFlush(0) === 'OK', 'A concurrent sale does not interrupt document delivery');
    $laterLine = $setCount = 0;
    foreach ($GLOBALS['shopRounds'] as $round) {
        foreach ($round as $url) {
            parse_str(parse_url($url, PHP_URL_QUERY), $params);
            if (($params['itemNo'] ?? '') === 'LINE20010') {
                check($params['stock'] === '66' && $params['totalStock'] === '66', 'A later document batch re-reads the stock committed by the concurrent sale');
                $laterLine++;
            }
            if (str_starts_with($params['itemNo'] ?? '', 'SET3')) {
                check($params['stock'] === '44' && $params['totalStock'] === '44', 'A parent set uses availability refreshed after the concurrent sale');
                $setCount++;
            }
        }
    }
    check($laterLine === 3 && $setCount === 33, 'All three shops receive the refreshed line and all eleven refreshed sets');
    check(shopSyncQueueCount() === 0, 'The refreshed document and sets leave no pending markers');

    reset_fanout();
    shopSyncQueueAdd(3580, 0, 4, 'stock');
    $state = &shopApiSyncState();
    $state['spent'] = 29.5;
    $before = $GLOBALS['shopQueries'];
    check(shopSyncDrainQueue() === 0, 'An exhausted HTTP budget sends no backlog');
    check($GLOBALS['shopQueries'] === $before && $GLOBALS['shopRounds'] === [], 'An exhausted budget performs no item or set preparation queries');
    $pending = shopSyncQueuePending();
    check(count($pending) === 1 && $pending[0]['attempts'] === 0, 'Untried backlog stays pending without consuming an attempt');

    reset_fanout();
    shopSyncMark(20000, 0, 4, 'all');
    check(shopSyncFlush(0) === 'OK', 'Receipt compatibility mode sends successfully');
    $skuCalls = $priceCalls = $setCostCalls = 0;
    foreach ($GLOBALS['shopRounds'] as $round) {
        foreach ($round as $url) {
            parse_str(parse_url($url, PHP_URL_QUERY), $params);
            $skuCalls += isset($params['sku']) && !isset($params['update_stock']) && $params['sku'] === 'LINE20000' ? 1 : 0;
            $priceCalls += isset($params['update_price']) ? 1 : 0;
            $setCostCalls += ($params['sku'] ?? '') === 'SET30000' && !isset($params['update_stock']) ? 1 : 0;
        }
    }
    check($skuCalls === 2 && $priceCalls === 3 && $setCostCalls === 3, 'Receipt compatibility retains SKU, price and parent cost calls');
    $receipt = file_get_contents(dirname(__DIR__, 4) . '/kreditor/modtag.php');
    check(str_contains($receipt, "shopSyncMark($" . "vare_id[$" . "x],$" . "variant_id[$" . "x],$" . "lager[$" . "x],'all')"), 'Goods receipt requests the full compatibility payload');

    $previousDb = $db;
    $db = 'invalid database name';
    $log = tempnam(sys_get_temp_dir(), 'sst876-persist-');
    $previousLog = ini_set('error_log', $log);
    try {
        shopSyncMark(3580, 0, 4, 'stock');
        check(str_contains(file_get_contents($log), 'cannot persist shop update for v3580-x0-l4'), 'A marker write failure is visible in the error log');
    } finally {
        $db = $previousDb;
        ini_set('error_log', $previousLog);
        unlink($log);
    }
} finally {
    reset_fanout();
    $dir = shopSyncQueueDir(false);
    if ($dir !== '') {
        $parent = dirname(rtrim($dir, '/'));
        if (is_file($parent . '/rest_api.log')) {
            unlink($parent . '/rest_api.log');
        }
        rmdir($dir);
        rmdir($parent);
    }
}
