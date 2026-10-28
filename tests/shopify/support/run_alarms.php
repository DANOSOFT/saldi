<?php
// tests/shopify/support/run_alarms.php
//
// Child-process runner for the call log, the error list, job alarms and the login watchdog
// (SD-738). Bootstraps the tenant like run_worker.php, saves the shop 'hml' and runs one mode:
//   calllog   shopify_redact() cases, a call-log row from an entry full of secrets, one written
//             by the real client (shopify_graphql() with FakeShopify) through its default log, and a
//             failing insert inside the caller's transaction
//   purge     rows at 89 days, exactly 90 days and 90 days plus a second before a fixed now
//   errors    open, count, resolve and reopen; the hourly alarm throttle; info never alarms;
//             an error opened in a transaction that rolls back is gone with it
//   jobs      a job failing 5 times, a dead job, a failing mail.alert, the queued alarm mails, then
//             the failing job succeeding and a crashed job recovered with its lease expired
//   watchdog  fresh, stale and missing heartbeats with recorded spawns; arg 'noshop' first deletes
//             the shop; arg 'spawn' uses the real background spawn and waits for the worker's heartbeat;
//             arg 'mailfail' has the first alarm mail refused
// Mails go to a recording transport. Prints JSON on stdout.
//
// History:
// 20261028 Sawaneh SD-738: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_alarms.php <calllog|purge|errors|jobs|watchdog> <test_db> [arg]\n");
    exit(2);
}
$mode = $argv[1];
$testDb = $argv[2];
$arg = $argv[3] ?? '';

require_once __DIR__ . '/ShopifyEnv.php';
require_once __DIR__ . '/FakeShopify.php';
ShopifyEnv::assertSafeTestDb($testDb);

function alert($msg)
{
    fwrite(STDERR, "alert: $msg\n");
    exit(3);
}

function out(array $data, int $exit = 0): void
{
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit($exit);
}

/** @return array<string, int> */
function counts(): array
{
    $r = shopify_db_rows("SELECT (SELECT count(*) FROM shopify_errors) AS errors, (SELECT count(*) FROM shopify_jobs WHERE type = 'mail.alert') AS mails");
    return ['errors' => (int)$r[0]['errors'], 'mails' => (int)$r[0]['mails']];
}

$db_type = 'postgresql';
$db_encode = 'UTF8';
$sqhost = ShopifyEnv::pgHost();
$squser = ShopifyEnv::pgUser();
$sqpass = ShopifyEnv::pgPass();
$sqdb = ShopifyEnv::masterDb();
$db = $sqdb;
$db_skriv_id = null;
$brugernavn = '';
$webservice = false;

require_once ShopifyEnv::repoRoot() . '/includes/db_query.php';
$connection = db_connect($sqhost, $squser, $sqpass, $sqdb);
require_once ShopifyEnv::repoRoot() . '/includes/shopify/bootstrap.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/worker.php';

$mails = [];
$mailOk = true;
shopify_mail_transport(function (string $to, string $subject, string $body) use (&$mails, &$mailOk): bool {
    $mails[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
    return $mailOk;
});

try {
    shopify_bootstrap($testDb);
    $shopId = shopify_db_transaction(fn() => shopify_save_shop(['shop_key' => 'hml', 'domain' => 'hml-test.myshopify.com', 'afd' => 1]));
    switch ($mode) {
        case 'calllog':
            require_once ShopifyEnv::repoRoot() . '/includes/shopify/client.php';
            $token = 'tok-' . bin2hex(random_bytes(8));
            $redact = [
                'string' => shopify_redact("header X-Shopify-Access-Token: shpat_abc123 and $token and Bearer eyJ.x-y", [$token, 'ab', null]),
                'nested' => shopify_redact(['a' => ['accessToken' => 'x', 'Password' => 'y', 'webhook_hmac' => 'z', 'lock_token' => '', 'note' => "n $token"], 'list' => ['shpss_9']], [$token]),
                'object' => json_encode(shopify_redact(json_decode('{"secret": "s", "data": {}, "n": 5}'))),
                'scalars' => [shopify_redact(5), shopify_redact(null), shopify_redact(true)],
                'assigned' => shopify_redact('GET /x?access_token=abc123&y=1 X-Shopify-Access-Token: legacy-9 client_secret=s3, lock_token is fine'),
            ];
            $b64 = fn($v) => base64_encode(json_encode($v));
            $id = shopify_call_log([
                'op' => 'orderCreate', 'shop' => 'hml', 'status' => 200, 'cost' => 12.4, 'available' => 1988, 'ms' => 81,
                'direction' => 'out', 'request_id' => 'req-1', 'class' => 'ok', 'error' => null,
                'correlation' => ['job_id' => 41, 'ordre_id' => 77, 'order_gid' => 'gid://shopify/Order/9', 'event_row_id' => 5, 'session' => $token],
                'request_b64' => $b64(['query' => 'mutation', 'variables' => ['input' => ['note' => "token $token", 'accessToken' => $token]]]),
                'response_b64' => base64_encode("raw shpat_zz99 body $token"),
            ], [$token]);
            $row = shopify_db_rows('SELECT * FROM shopify_call_log WHERE id = $1', [$id])[0];
            $decoded = base64_decode((string)$row['request_b64']) . ' ' . base64_decode((string)$row['response_b64']);

            $fake = new FakeShopify([FakeShopify::ok(['shop' => ['name' => 'HML', 'accessToken' => 'shpat_resp1']])]);
            $shop = shopify_shop('hml');
            $graphql = shopify_graphql($shop, 'shop', 'query shop($n: String) { shop { name } }', ['n' => FakeShopify::TOKEN],
                $fake->opts(['log' => null, 'correlation' => ['job_id' => 42, 'ordre_id' => 78]]));
            $clientRow = shopify_db_rows("SELECT * FROM shopify_call_log WHERE job_id = 42")[0] ?? null;
            $fake = new FakeShopify([FakeShopify::throttled(0, 50), FakeShopify::ok(['shop' => ['name' => 'HML']])]);
            shopify_graphql($shop, 'shop', 'query { shop { name } }', [], $fake->opts(['log' => null, 'correlation' => ['job_id' => 43]]));
            $throttled = array_map(fn($row) => [$row['http_status'], $row['cost_requested'], $row['cost_actual']],
                shopify_db_rows("SELECT http_status, cost_requested, cost_actual FROM shopify_call_log WHERE job_id = 43 ORDER BY id"));
            $insideTx = shopify_db_transaction(function (): array {
                shopify_db_rows("INSERT INTO settings (var_name, var_grp, var_value) VALUES ('kept', 'shopify_test_effect', '1')");
                try {
                    shopify_call_log(['op' => 'broken', 'shop_id' => 99999999999]);
                    $failed = false;
                } catch (ShopifyDbError $e) {
                    $failed = true;
                }
                return ['insert_failed' => $failed, 'caller_goes_on' => shopify_db_rows("SELECT count(*) AS n FROM settings WHERE var_grp = 'shopify_test_effect'")[0]['n']];
            });
            shopify_db_rows("DELETE FROM settings WHERE var_grp = 'shopify_test_effect'");
            out(['redact' => $redact, 'token' => $token, 'id' => $id, 'row' => $row, 'decoded' => $decoded, 'shop_id' => $shopId, 'inside_tx' => $insideTx, 'throttled' => $throttled,
                'client' => ['ok' => $graphql['ok'], 'row' => $clientRow, 'decoded' => $clientRow ? base64_decode((string)$clientRow['request_b64']) . ' ' . base64_decode((string)$clientRow['response_b64']) : null]]);
            // no break: out() exits
        case 'purge':
            $now = new DateTimeImmutable('2026-10-28 07:15:00');
            foreach (['-89 days' => 'young', '-90 days' => 'exact', '-90 days -1 second' => 'old'] as $offset => $name) {
                shopify_db_rows("INSERT INTO shopify_call_log (direction, operation, created_at) VALUES ('out', $1, $2)", [$name, $now->modify($offset)]);
            }
            $deleted = shopify_call_log_purge($now);
            $left = array_column(shopify_db_rows("SELECT operation FROM shopify_call_log WHERE operation IN ('young', 'exact', 'old') ORDER BY created_at"), 'operation');
            $job = shopify_runtime_purge_job(['id' => 0], microtime(true) + 30, fn() => true);
            out(['deleted' => $deleted, 'left' => $left, 'job' => $job]);
            // no break: out() exits
        case 'errors':
            $a = shopify_error_open($shopId, 'order.sku', 'gid://shopify/Order/1', 'unknown SKU shpat_err1', 'error', ['ordre_id' => 7, 'api_key' => 'k1']);
            $after1 = counts();
            $row1 = shopify_db_rows('SELECT message, correlation FROM shopify_errors WHERE id = $1', [$a])[0];
            $a2 = shopify_error_open($shopId, 'order.sku', 'gid://shopify/Order/1', 'unknown SKU again');
            $row = shopify_db_rows('SELECT occurrences, message, correlation, alarmed_at IS NOT NULL AS alarmed FROM shopify_errors WHERE id = $1', [$a])[0];
            $after2 = counts();
            shopify_db_rows("UPDATE shopify_errors SET alarmed_at = now() - interval '61 minutes' WHERE id = $1", [$a]);
            shopify_error_open($shopId, 'order.sku', 'gid://shopify/Order/1', 'unknown SKU a third time');
            $after3 = counts();
            $other = shopify_error_open(null, 'order.sku', 'gid://shopify/Order/1', 'same kind and ref, no shop');
            $info = shopify_error_open($shopId, 'customer.phone', 'gid://shopify/Customer/3', 'phone taken', 'info');
            $afterInfo = counts();
            $resolved = shopify_error_resolve($a);
            $resolvedAgain = shopify_error_resolve($a);
            $fresh = shopify_error_open($shopId, 'order.sku', 'gid://shopify/Order/1', 'unknown SKU after the fix');
            $freshRow = shopify_db_rows('SELECT occurrences FROM shopify_errors WHERE id = $1', [$fresh])[0];
            $afterFresh = counts();
            try {
                shopify_db_transaction(function () use ($shopId) {
                    shopify_error_open($shopId, 'webhook.anomaly', 'rolled-back', 'anomaly');
                    throw new RuntimeException('the caller fails');
                });
            } catch (RuntimeException $e) {
            }
            $rolledBack = shopify_db_rows("SELECT count(*) AS n FROM shopify_errors WHERE ref = 'rolled-back'")[0]['n'];
            $mailJobs = shopify_db_rows("SELECT priority, payload FROM shopify_jobs WHERE type = 'mail.alert' ORDER BY id");
            out(['ids' => [$a, $a2, $other, $info, $fresh], 'row1' => $row1, 'row' => $row, 'counts' => [$after1, $after2, $after3, $afterInfo, $afterFresh],
                'resolved' => [$resolved, $resolvedAgain], 'fresh_occurrences' => (int)$freshRow['occurrences'], 'rolled_back' => (int)$rolledBack,
                'mail_jobs' => array_map(fn($r) => ['priority' => (int)$r['priority'], 'payload' => json_decode($r['payload'], true)], $mailJobs),
                'key' => shopify_error_key($shopId, 'order.sku', 'gid://shopify/Order/1') === hash('sha256', "$shopId\x1forder.sku\x1fgid://shopify/Order/1")]);
            // no break: out() exits
        case 'jobs':
            shopify_db_rows("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('support_email', 'shopify', 'alarm@example.test', 'test')");
            $run = function (string $type, callable $handler): string {
                shopify_db_rows("UPDATE shopify_jobs SET next_run_at = now() WHERE type = $1 AND state = 'queued'", [$type]);
                $job = shopify_queue_claim([$type]);
                return $job === null ? 'none' : shopify_worker_execute($job, $handler, microtime(true) + 30);
            };
            $failing = function () {
                throw new RuntimeException('fail shpat_jobsecret1');
            };
            $import = shopify_queue_enqueue('order.import', $shopId, 'hml:order-1', [], 10, null, ['ordre_id' => 77, 'order_gid' => 'gid://shopify/Order/1']);
            $steps = [];
            for ($i = 1; $i <= 5; $i++) {
                if ($i === 5) {
                    shopify_db_rows("UPDATE shopify_errors SET alarmed_at = now() - interval '61 minutes'");
                }
                $steps[] = ['result' => $run('order.import', $failing)] + counts();
            }
            $importError = shopify_db_rows("SELECT kind, ref, occurrences, severity, message, correlation FROM shopify_errors WHERE kind = 'job.failed'")[0];
            $lastError = shopify_db_rows('SELECT last_error FROM shopify_jobs WHERE id = $1', [$import['id']])[0]['last_error'];
            shopify_queue_enqueue('order.move', $shopId, 'hml:move-1', [], 15);
            $dead = ['result' => $run('order.move', fn() => throw new LogicException('programming error'))] + counts();
            $mailOk = false;
            $mailSteps = [];
            for ($i = 1; $i <= 3; $i++) {
                $mailSteps[] = ['result' => $run('mail.alert', 'shopify_mail_alert_job')] + counts();
            }
            $mailError = shopify_db_rows("SELECT severity FROM shopify_errors WHERE ref LIKE 'mail.alert:%'")[0] ?? null;
            $mailOk = true;
            $mails = [];
            $sent = [];
            while (($r = $run('mail.alert', 'shopify_mail_alert_job')) !== 'none') {
                $sent[] = $r;
            }
            $succeeds = $run('order.import', fn() => ['outcome' => 'done']);
            $resolved = shopify_db_rows("SELECT resolved_at IS NOT NULL AS r FROM shopify_errors WHERE kind = 'job.failed' AND ref = 'order.import:hml:order-1'")[0]['r'];
            $crash = shopify_queue_enqueue('order.tags', $shopId, 'hml:tags-1', [], 10);
            shopify_queue_claim(['order.tags']);
            shopify_db_rows("UPDATE shopify_jobs SET attempts = 3, locked_until = now() - interval '1 second' WHERE id = $1", [$crash['id']]);
            $pass = shopify_worker_run(['db' => $testDb, 'shops' => [], 'heartbeat_file' => null], [], [], 5);
            $crashError = shopify_db_rows("SELECT message FROM shopify_errors WHERE ref = 'order.tags:hml:tags-1'")[0]['message'] ?? null;
            out(['steps' => $steps, 'import_error' => $importError, 'last_error' => $lastError, 'dead' => $dead,
                'mail_steps' => $mailSteps, 'mail_error' => $mailError, 'sent' => $sent, 'mails' => $mails,
                'succeeds' => [$succeeds, $resolved], 'crash' => ['recovered' => $pass['recovered'], 'error' => $crashError]]);
            // no break: out() exits
        case 'watchdog':
            if ($arg === 'noshop') {
                shopify_db_rows('DELETE FROM shopify_shops');
            }
            $dir = ShopifyEnv::repoRoot() . "/temp/$testDb";
            @mkdir($dir, 0777, true);
            $file = "$dir/.ht_shopify_worker.heartbeat";
            $now = time();
            $beat = fn(int $at) => file_put_contents($file, json_encode(['at' => date('c', $at), 'unix' => $at]) . "\n");
            if ($arg === 'mailfail') {
                $beat($now - 601);
                $mailOk = false;
                $first = shopify_watchdog(['db' => $testDb, 'now' => $now, 'heartbeat_file' => $file, 'spawn' => fn() => null]);
                $mailOk = true;
                $second = shopify_watchdog(['db' => $testDb, 'now' => $now + 60, 'heartbeat_file' => $file, 'spawn' => fn() => null]);
                $third = shopify_watchdog(['db' => $testDb, 'now' => $now + 120, 'heartbeat_file' => $file, 'spawn' => fn() => null]);
                @unlink($file);
                out(['mailed' => [$first['mailed'], $second['mailed'], $third['mailed']], 'spawned' => [$first['spawned'], $second['spawned']]]);
            }
            if ($arg === 'spawn') {
                // the spawned worker sends queued mail.alert jobs with PHP mail(): there must be none
                shopify_db_rows('DELETE FROM shopify_jobs');
                $beat($now - 601);
                $w = shopify_watchdog(['db' => $testDb]);
                $fresh = null;
                for ($i = 0; $i < 150 && $fresh === null; $i++) {
                    usleep(100000);
                    $u = (int)(json_decode((string)@file_get_contents($file), true)['unix'] ?? 0);
                    $fresh = $u >= $now ? $u - $now : null;
                }
                out(['watchdog' => $w, 'heartbeat_after' => $fresh, 'mails' => count($mails)]);
            }
            $spawns = [];
            $ctx = function (int $at) use ($testDb, $file, &$spawns): array {
                return ['db' => $testDb, 'now' => $at, 'heartbeat_file' => $file, 'spawn' => function (string $db) use (&$spawns) {
                    $spawns[] = $db;
                }];
            };
            $check = function (string $name, int $at) use ($ctx, &$spawns, &$mails): array {
                [$s0, $m0] = [count($spawns), count($mails)];
                $r = shopify_watchdog($ctx($at));
                return [$name => ['stale' => $r['stale'], 'spawned' => count($spawns) - $s0, 'mailed' => count($mails) - $m0] + (isset($r['error']) ? ['error' => $r['error']] : [])];
            };
            @unlink($file);
            $res = $check('missing', $now);
            $beat($now - 60);
            $res += $check('fresh', $now);
            $beat($now - 600);
            $res += $check('ten_minutes', $now);
            $beat($now - 601);
            $res += $check('stale', $now);
            $res += $check('stale_again', $now + 60);
            $res += $check('after_ten_minutes', $now + 601);
            $res += $check('after_an_hour', $now + 3601);
            @unlink($file);
            out(['checks' => $res, 'spawned_for' => array_values(array_unique($spawns)), 'mail' => $mails[0] ?? null]);
            // no break: out() exits
        default:
            fwrite(STDERR, "unknown mode $mode\n");
            exit(2);
    }
} catch (ShopifyBootstrapError | ShopifyDbError | ShopifyQueueError $e) {
    out(['error' => $e->getMessage(), 'class' => get_class($e)], 1);
}
