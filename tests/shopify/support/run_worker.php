<?php
// tests/shopify/support/run_worker.php
//
// Child-process runner for the job queue and the worker run (SD-737). Bootstraps the tenant
// like run_config.php and runs one mode:
//   queue      enqueue and dedupe, claim and lease fencing, yield, fail with backoff, dead,
//              recover of an expired lease, durable state
//   hold       claims nothing; locks the given job row FOR UPDATE, writes the marker file and
//              holds the lock for a few seconds (the other claimer must skip it)
//   claim      claims one test.job and reports it
//   run        one worker pass with test handlers: order by priority, yield then done, fail,
//              misuse, bad outcome, a slot insert
//   backlog    one worker pass with a 6 second budget over slow jobs: stops on the budget
//   misuse_replay  a handler that commits and leaves a transaction open, and one that only
//              leaves one open; their terminal state must be committed ('enqueue' adds the jobs)
//   busy_yield a handler that always yields with no delay must not be reclaimed in the same pass
// Prints JSON on stdout.
//
// History:
// 20261026 Sawaneh SD-737: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_worker.php <queue|hold|claim|run|backlog|misuse_replay|busy_yield> <test_db> [arg]\n");
    exit(2);
}
$mode = $argv[1];
$testDb = $argv[2];
$arg = $argv[3] ?? '';

require_once __DIR__ . '/ShopifyEnv.php';
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

/** @return array<string, mixed>|null */
function job_row(int $id): ?array
{
    $rows = shopify_db_rows('SELECT id, state, attempts, lock_token, last_error, checkpoint, next_run_at > now() + interval \'50 seconds\' AS later, extract(epoch FROM next_run_at - now()) AS wait FROM shopify_jobs WHERE id = $1', [$id]);
    return $rows[0] ?? null;
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

try {
    shopify_bootstrap($testDb);
    switch ($mode) {
        case 'queue':
            $out = [];
            $a = shopify_queue_enqueue('order.import', null, 'hml:gid://shopify/Order/1', ['n' => 1], 10, null, ['ordre_id' => 77, 'order_gid' => 'gid://shopify/Order/1']);
            $b = shopify_queue_enqueue('order.import', null, 'hml:gid://shopify/Order/1', ['n' => 2], 10);
            $c = shopify_queue_enqueue('order.status', null, 'hml:gid://shopify/Order/1', [], 25);
            $out['dedupe'] = ['a' => $a, 'b' => $b, 'other_type' => $c['created'],
                'columns' => shopify_db_rows('SELECT ordre_id, order_gid, payload FROM shopify_jobs WHERE id = $1', [$a['id']])[0]];
            $later = shopify_queue_enqueue('order.tags', null, 'hml:later', [], 10, new DateTimeImmutable('+1 hour'));
            $out['not_due'] = shopify_queue_claim(['order.tags']);

            $job = shopify_queue_claim(['order.import', 'order.status']);
            $out['claim'] = ['id' => $job['id'], 'type' => $job['type'], 'attempts' => $job['attempts'], 'payload' => $job['payload'], 'token_len' => strlen($job['lock_token']), 'state' => job_row($job['id'])['state']];
            $out['other_claimer_while_running'] = shopify_queue_claim(['order.import'])['id'] ?? null;
            $out['dedupe_while_running'] = shopify_queue_enqueue('order.import', null, 'hml:gid://shopify/Order/1', [], 10);
            $out['fence'] = [
                'stale_complete' => shopify_queue_complete($job['id'], 'stale'),
                'stale_fail' => shopify_queue_fail($job['id'], 'stale', 'x'),
                'stale_yield' => shopify_queue_yield($job['id'], 'stale', ['x' => 1]),
                'stale_dead' => shopify_queue_dead($job['id'], 'stale', 'x'),
                'stale_extend' => shopify_queue_extend($job['id'], 'stale'),
                'extend' => shopify_queue_extend($job['id'], $job['lock_token']),
                'complete' => shopify_queue_complete($job['id'], $job['lock_token']),
                'complete_again' => shopify_queue_complete($job['id'], $job['lock_token']),
                'state' => job_row($job['id'])['state'],
            ];
            $out['after_done_enqueue'] = shopify_queue_enqueue('order.import', null, 'hml:gid://shopify/Order/1', [], 10);

            $y = shopify_queue_enqueue('catalogue.seed', null, 'tenant:seed', [], 60);
            $j = shopify_queue_claim(['catalogue.seed']);
            shopify_queue_yield($j['id'], $j['lock_token'], ['offset' => 250]);
            $j2 = shopify_queue_claim(['catalogue.seed']);
            $out['yield'] = ['checkpoint' => $j2['checkpoint'], 'attempts' => $j2['attempts'], 'same' => $j2['id'] === $y['id']];
            shopify_queue_yield($j2['id'], $j2['lock_token'], [], 600);
            $out['yield_run_after_later'] = job_row($j2['id'])['later'];

            $f = shopify_queue_enqueue('stock.push', null, 'hml:1', [], 30);
            $waits = [];
            for ($i = 1; $i <= 5; $i++) {
                $fj = shopify_queue_claim(['stock.push']);
                shopify_queue_fail($fj['id'], $fj['lock_token'], "boom $i shpat_secret");
                $row = job_row($f['id']);
                $waits[] = (int)round((float)$row['wait']);
                shopify_db_rows('UPDATE shopify_jobs SET next_run_at = now() WHERE id = $1', [$f['id']]);
            }
            $out['fail'] = ['waits' => $waits, 'state' => job_row($f['id'])['state'], 'attempts' => (int)job_row($f['id'])['attempts'], 'last_error' => job_row($f['id'])['last_error']];
            $fj = shopify_queue_claim(['stock.push']);
            shopify_queue_fail($fj['id'], $fj['lock_token'], 'throttled', 7);
            $out['retry_after'] = (int)round((float)job_row($f['id'])['wait']);

            $d = shopify_queue_enqueue('stock.display', null, 'hml:2', [], 35);
            shopify_db_rows('UPDATE shopify_jobs SET max_attempts = 2 WHERE id = $1', [$d['id']]);
            for ($i = 0; $i < 2; $i++) {
                $dj = shopify_queue_claim(['stock.display']);
                shopify_queue_fail($dj['id'], $dj['lock_token'], 'again');
                shopify_db_rows('UPDATE shopify_jobs SET next_run_at = now() WHERE id = $1', [$d['id']]);
            }
            $out['dead_after_max'] = job_row($d['id'])['state'];

            $r = shopify_queue_enqueue('price.window', null, 'hml:crash', [], 50);
            $rj = shopify_queue_claim(['price.window']);
            shopify_db_rows("UPDATE shopify_jobs SET locked_until = now() - interval '1 second' WHERE id = $1", [$r['id']]);
            $out['recover'] = ['recovered' => shopify_queue_recover_expired(), 'state' => job_row($r['id'])['state'], 'error' => job_row($r['id'])['last_error'],
                'old_token_complete' => shopify_queue_complete($r['id'], $rj['lock_token'])];
            $rj2 = shopify_queue_claim(['price.window']);
            $out['recover']['new_token_complete'] = shopify_queue_complete($r['id'], $rj2['lock_token']);
            $out['recover']['attempts_after'] = (int)$rj2['attempts'];

            foreach ([
                'bad_type' => fn() => shopify_queue_enqueue('Order Import', null, 'hml:x', [], 10),
                'bad_key' => fn() => shopify_queue_enqueue('order.import', null, 'nocolon', [], 10),
                'bad_priority' => fn() => shopify_queue_enqueue('order.import', null, 'hml:x', [], 101),
            ] as $name => $attempt) {
                try {
                    $attempt();
                    $out[$name] = 'accepted';
                } catch (Throwable $e) {
                    $out[$name] = get_class($e);
                }
            }
            shopify_queue_state_set('monitor.counterpart:hml', ['cursor' => 'gid://shopify/Order/9', 'n' => 3]);
            shopify_queue_state_set('monitor.counterpart:hml', ['cursor' => 'gid://shopify/Order/10', 'n' => 4]);
            $out['state'] = ['value' => shopify_queue_state_get('monitor.counterpart:hml'), 'missing' => shopify_queue_state_get('nope'),
                'rows' => (int)shopify_db_rows("SELECT count(*) AS n FROM settings WHERE var_grp = 'shopify_state'")[0]['n']];
            $out['later_id'] = $later['id'];
            out($out);
            // no break: out() exits
        case 'hold':
            pg_query($connection, 'BEGIN');
            pg_query_params($connection, 'SELECT id FROM shopify_jobs WHERE id = $1 FOR UPDATE', [(int)$arg]);
            file_put_contents(ShopifyEnv::repoRoot() . "/temp/$testDb/.hold", 'locked');
            sleep(4);
            pg_query($connection, 'COMMIT');
            out(['held' => (int)$arg]);
            // no break: out() exits
        case 'claim':
            $started = microtime(true);
            $job = shopify_queue_claim(['test.job']);
            out(['id' => $job['id'] ?? null, 'ms' => (int)round((microtime(true) - $started) * 1000)]);
            // no break: out() exits
        case 'run':
            $order = [];
            $registry = [];
            $record = function (string $name, array $result) use (&$order) {
                return function (array $job, float $deadline, callable $heartbeat) use (&$order, $name, $result) {
                    $order[] = $name . ($job['checkpoint'] !== null ? '+' . json_encode($job['checkpoint']) : '');
                    if ($name === 'beat') {
                        $result = ['outcome' => $heartbeat() ? 'done' : 'yield'];
                    }
                    if ($name === 'yield' && $job['checkpoint'] === null) {
                        return ['outcome' => 'yield', 'checkpoint' => ['page' => 2]];
                    }
                    if ($name === 'fail') {
                        throw new RuntimeException('fail shpat_secret');
                    }
                    if ($name === 'misuse') {
                        throw new ShopifyDbMisuse('ended the transaction');
                    }
                    return $result;
                };
            };
            shopify_worker_register($registry, 'catalogue.seed', $record('seed', ['outcome' => 'done']));
            shopify_worker_register($registry, 'order.import', $record('import', ['outcome' => 'done']));
            shopify_worker_register($registry, 'order.tags', $record('yield', ['outcome' => 'done']));
            shopify_worker_register($registry, 'stock.push', $record('fail', []));
            shopify_worker_register($registry, 'order.move', $record('misuse', []));
            shopify_worker_register($registry, 'order.note', $record('bad', ['nope' => 1]));
            shopify_worker_register($registry, 'order.status', $record('beat', []));
            shopify_worker_register($registry, 'runtime.purge', $record('purge', ['outcome' => 'done']));
            try {
                shopify_worker_register($registry, 'order.import', $record('dup', []));
                $dupRegister = 'accepted';
            } catch (LogicException $e) {
                $dupRegister = 'refused';
            }
            shopify_queue_enqueue('catalogue.seed', null, 'tenant:seed', [], 60);
            shopify_queue_enqueue('order.import', null, 'hml:o1', [], 10);
            shopify_queue_enqueue('order.tags', null, 'hml:o1', [], 10);
            shopify_queue_enqueue('stock.push', null, 'hml:5', [], 30);
            shopify_queue_enqueue('order.move', null, 'hml:o2', [], 15);
            shopify_queue_enqueue('order.note', null, 'hml:o3', [], 25);
            shopify_queue_enqueue('order.status', null, 'hml:o4', [], 25);
            shopify_queue_enqueue('webhook.health', null, 'hml:unregistered', [], 40);
            $producerCalls = [];
            $producers = [
                'events' => function (array $ctx, float $deadline) use (&$producerCalls) {
                    $producerCalls[] = 'events';
                    return ['promoted' => 0];
                },
                'broken' => function () {
                    throw new RuntimeException('producer down');
                },
                'stock_dirty' => function () use (&$producerCalls) {
                    $producerCalls[] = 'stock_dirty';
                    return ['drained' => 0];
                },
            ];
            $heartbeat = ShopifyEnv::repoRoot() . "/temp/$testDb/.ht_shopify_worker.heartbeat";
            @mkdir(dirname($heartbeat), 0777, true);
            $now = new DateTimeImmutable('2026-10-26 07:20:00', new DateTimeZone('Europe/Copenhagen'));
            $summary = shopify_worker_run(['db' => $testDb, 'now' => fn() => $now, 'shops' => [], 'heartbeat_file' => $heartbeat], $registry, $producers, 30);
            $states = [];
            foreach (shopify_db_rows('SELECT type, state, attempts, last_error FROM shopify_jobs ORDER BY id') as $row) {
                $states[$row['type']] = ['state' => $row['state'], 'attempts' => (int)$row['attempts'], 'error' => $row['last_error']];
            }
            $firstPassCalls = $producerCalls;
            $second = shopify_worker_run(['db' => $testDb, 'now' => fn() => $now, 'shops' => [], 'heartbeat_file' => $heartbeat], $registry, $producers, 30);
            out(['summary' => $summary, 'order' => $order, 'states' => $states, 'producer_calls' => $firstPassCalls, 'dup_register' => $dupRegister,
                'second_scheduled' => $second['scheduled'], 'heartbeat' => json_decode((string)file_get_contents($heartbeat), true)]);
            // no break: out() exits
        case 'misuse_replay':
            $registry = [];
            shopify_worker_register($registry, 'order.import', function () use ($connection) {
                return shopify_db_transaction(function () use ($connection) {
                    shopify_db_rows("INSERT INTO settings (var_name, var_grp, var_value) VALUES ('effect', 'shopify_test_effect', '1')");
                    pg_query($connection, 'COMMIT');
                    pg_query($connection, 'BEGIN');
                    return ['outcome' => 'done'];
                });
            });
            shopify_worker_register($registry, 'order.tags', function () use ($connection) {
                pg_query($connection, 'BEGIN');
                shopify_db_rows("INSERT INTO settings (var_name, var_grp, var_value) VALUES ('effect', 'shopify_test_effect', '2')");
                return ['outcome' => 'done'];
            });
            if ($arg === 'enqueue') {
                shopify_queue_enqueue('order.import', null, 'hml:misuse', [], 10);
                shopify_queue_enqueue('order.tags', null, 'hml:open', [], 10);
            }
            $summary = shopify_worker_run(['db' => $testDb, 'shops' => [], 'heartbeat_file' => null], $registry, [], 20);
            out(['summary' => array_diff_key($summary, ['jobs' => 1]), 'jobs' => $summary['jobs']]);
            // no break: out() exits
        case 'busy_yield':
            $calls = 0;
            $registry = [];
            shopify_worker_register($registry, 'order.import', function () use (&$calls) {
                $calls++;
                return ['outcome' => 'yield', 'checkpoint' => ['n' => $calls]];
            });
            shopify_worker_register($registry, 'catalogue.seed', fn() => ['outcome' => 'done']);
            shopify_queue_enqueue('order.import', null, 'hml:always-yields', [], 10);
            shopify_queue_enqueue('catalogue.seed', null, 'tenant:after', [], 60);
            $summary = shopify_worker_run(['db' => $testDb, 'shops' => [], 'heartbeat_file' => null], $registry, [], 7);
            $rows = shopify_db_rows("SELECT type, state, checkpoint, next_run_at > now() AS deferred FROM shopify_jobs ORDER BY id");
            out(['calls' => $calls, 'jobs' => $rows, 'summary' => array_diff_key($summary, ['jobs' => 1])]);
            // no break: out() exits
        case 'backlog':
            $registry = [];
            shopify_worker_register($registry, 'catalogue.seed', function () {
                usleep(1500000);
                return ['outcome' => 'done'];
            });
            for ($i = 0; $i < 10; $i++) {
                shopify_queue_enqueue('catalogue.seed', null, "tenant:chunk$i", [], 60);
            }
            $summary = shopify_worker_run(['db' => $testDb, 'shops' => [], 'heartbeat_file' => null], $registry, [], 8);
            out(['summary' => array_diff_key($summary, ['jobs' => 1]), 'left' => (int)shopify_db_rows("SELECT count(*) AS n FROM shopify_jobs WHERE state = 'queued'")[0]['n']]);
            // no break: out() exits
        default:
            fwrite(STDERR, "unknown mode $mode\n");
            exit(2);
    }
} catch (ShopifyBootstrapError | ShopifyDbError | ShopifyQueueError $e) {
    out(['error' => $e->getMessage(), 'class' => get_class($e)], 1);
}
