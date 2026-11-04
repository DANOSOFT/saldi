<?php
// tests/shopify/SandboxRunnerTest.php
//
// The sandbox runner (SD-766, support/sandbox/run.php) as a command: an unsafe tenant, a half
// credential or half the database settings exit 2 before anything runs, the sandboxes without
// credentials give SKIP, the fake target passes K05 and runs the cross-lane chain X01 on a cloned
// tenant (SKIP without database settings), a clone is dropped whatever fails while it is set up,
// calibration without a dev store is SKIP, and the calibration's comparison and shopify_await()
// work on two fakes and a fake clock.
//
// History:
// 20261103 CL/LH SD-766: created.
// 20261103 CL/LH SD-766: review fixes: calibration compares outcomes, every error code and the cost; the runner
//                without database settings, with half of them, with an overlong clone name and a failed setup.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/sandbox/run.php';

final class SandboxRunnerTest extends TestCase
{
    /**
     * Runs the runner as a child: this process's environment without sandbox or dev-store
     * credentials (and without database settings unless $database), plus $env.
     *
     * @return array{exit: int, stdout: string, stderr: string, json: ?array}
     */
    private function runner(array $args, array $env = [], bool $database = true): array
    {
        $drop = $database ? '/^SALDI_SHOPIFY_(SANDBOX|DEVSTORE)_/' : '/^SALDI_SHOPIFY_(SANDBOX_|DEVSTORE_|PG|TEMPLATE_DB|TEST_DB|MASTER_DB)/';
        $base = array_filter(getenv(), fn(string $k): bool => !preg_match($drop, $k), ARRAY_FILTER_USE_KEY);
        $proc = proc_open(array_merge([PHP_BINARY, __DIR__ . '/support/sandbox/run.php'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
            ShopifyEnv::repoRoot(), $env + $base);
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['exit' => proc_close($proc), 'stdout' => $stdout, 'stderr' => $stderr, 'json' => json_decode($stdout, true)];
    }

    public function testAnUnsafeTenantOrAHalfCredentialExits2BeforeAnythingRuns(): void
    {
        foreach (['pos_50' => 'live tenants are off limits', 'test_12' => 'live tenants are off limits', 'saldi_hml' => 'must match saldi_shopify_test_*'] as $tenant => $why) {
            foreach (['fake', 'sandbox'] as $target) {
                $run = $this->runner(["--target=$target", "--tenant=$tenant"]);
                $this->assertSame(2, $run['exit'], "$target $tenant");
                $this->assertStringContainsString($why, $run['stderr']);
                $this->assertSame('', $run['stdout'], 'nothing ran');
            }
        }
        $this->assertSame(2, $this->runner(['--target=fake'], ['SALDI_SHOPIFY_TEST_DB' => ''])['exit'], 'no tenant at all');
        $this->assertSame(2, $this->runner(['--target=prod', '--tenant=saldi_shopify_test_ci'])['exit']);
        $half = $this->runner(['--target=sandbox', '--tenant=saldi_shopify_test_ci'], ['SALDI_SHOPIFY_SANDBOX_HML_DOMAIN' => 'hml-sandbox.myshopify.com']);
        $this->assertSame(2, $half['exit'], 'a domain without its token is no fallback');
        $this->assertStringContainsString('SALDI_SHOPIFY_SANDBOX_HML_DOMAIN and _TOKEN go together', $half['stderr']);
        $this->assertSame(2, $this->runner(['--target=sandbox', '--tenant=saldi_shopify_test_ci'],
            ['SALDI_SHOPIFY_SANDBOX_HML_DOMAIN' => 'https://hml.example', 'SALDI_SHOPIFY_SANDBOX_HML_TOKEN' => 'x'])['exit']);
    }

    public function testTheSandboxesWithoutCredentialsAreSkippedNotPassedOrFailed(): void
    {
        $run = $this->runner(['--target=sandbox', '--tenant=saldi_shopify_test_ci', '--json']);

        $this->assertSame(0, $run['exit'], $run['stderr']);
        $this->assertSame(0, $run['json']['counts']['PASS'] + $run['json']['counts']['FAIL']);
        $k05 = array_values(array_filter($run['json']['results'], fn(array $r): bool => $r['id'] === 'K05' && isset($r['shop'])));
        $this->assertSame([['hml', 'SKIP'], ['hms', 'SKIP'], ['di', 'SKIP']], array_map(fn(array $r): array => [$r['shop'], $r['status']], $k05));
        $this->assertStringContainsString('SALDI_SHOPIFY_SANDBOX_HML_DOMAIN and _TOKEN are not set', $k05[0]['detail']);
        foreach ($run['json']['results'] as $r) {
            $this->assertContains($r['status'], ['SKIP', 'BLOCKED']);
            if ($r['status'] === 'BLOCKED') {
                $this->assertNotEmpty($r['owner'], 'BLOCKED names an owner');
            }
        }
        $this->assertContains(['K05', 'DTAILS'], array_map(fn(array $r): array => [$r['id'], $r['owner'] ?? ''], $run['json']['results']), 'the sandbox setup is DTAILS\'');
        $this->assertContains(['K03', 'SKIP', 'SALDI_SHOPIFY_SANDBOX_ENDPOINT is not set (endpoint_url for K03.php)'],
            array_map(fn(array $r): array => [$r['id'], $r['status'], $r['detail']], $run['json']['results']));
    }

    private function requireDatabase(): string
    {
        $why = ShopifyEnv::unavailableReason();
        if ($why !== null) {
            $this->markTestSkipped($why);
        }
        return (string)getenv('SALDI_SHOPIFY_TEST_DB');
    }

    /** @return array{0: bool, 1: bool} whether $db exists, and whether master regnskab registers it */
    private function tenantState(string $db): array
    {
        $master = ShopifyEnv::connect(ShopifyEnv::masterDb());
        try {
            return [ShopifyEnv::one($master, 'SELECT 1 AS x FROM pg_database WHERE datname = $1', [$db]) !== null,
                ShopifyEnv::one($master, 'SELECT 1 AS x FROM regnskab WHERE db = $1', [$db]) !== null];
        } finally {
            pg_close($master);
        }
    }

    public function testWithoutDatabaseSettingsTheFakePassesK05AndSkipsTheChain(): void
    {
        // on a PHP with pgsql, as CI's: the runner must not ask for the settings it was not given
        $run = $this->runner(['--target=fake', '--tenant=saldi_shopify_test_ci', '--json'], [], false);

        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $this->assertSame(['PASS', 'PASS', 'PASS'], array_column(array_filter($run['json']['results'], fn(array $r): bool => $r['id'] === 'K05'), 'status'));
        $this->assertSame([['SKIP', 'no test tenant: no database configured (SALDI_SHOPIFY_PGUSER, SALDI_SHOPIFY_PGPASS, SALDI_SHOPIFY_TEMPLATE_DB are not set)']],
            array_map(fn(array $r): array => [$r['status'], $r['detail']], array_values(array_filter($run['json']['results'], fn(array $r): bool => $r['id'] === 'X01'))));
        $this->assertNull($run['json']['clone']);
    }

    public function testHalfTheDatabaseSettingsExit2BeforeAnythingRuns(): void
    {
        foreach ([['SALDI_SHOPIFY_PGUSER' => 'user'], ['SALDI_SHOPIFY_PGUSER' => 'user', 'SALDI_SHOPIFY_PGPASS' => 'not-a-password'], ['SALDI_SHOPIFY_TEMPLATE_DB' => 'saldi_2']] as $env) {
            $run = $this->runner(['--target=fake', '--tenant=saldi_shopify_test_ci'], $env, false);
            $this->assertSame(2, $run['exit'], json_encode(array_keys($env)));
            $this->assertStringContainsString('unsafe config, nothing run: SALDI_SHOPIFY_PGUSER, SALDI_SHOPIFY_PGPASS, SALDI_SHOPIFY_TEMPLATE_DB go together', $run['stderr']);
            $this->assertSame('', $run['stdout'], 'nothing ran');
        }
    }

    public function testAnOverlongCloneNameExits2BeforeAnythingIsCloned(): void
    {
        $this->requireDatabase();
        $tenant = 'saldi_shopify_test_ci_long';
        $run = $this->runner(['--target=fake', "--tenant=$tenant"]);

        $this->assertSame(2, $run['exit'], $run['stdout']);
        $this->assertStringContainsString("the clone {$tenant}_sr would be longer than 25 characters", $run['stderr']);
        $this->assertSame('', $run['stdout']);
        $this->assertSame([false, false], $this->tenantState("{$tenant}_sr"), 'nothing was cloned');
    }

    public function testAFailedSetupStillDropsTheClone(): void
    {
        $tenant = $this->requireDatabase();
        $clone = "{$tenant}_sr";
        $messages = '';
        $seams = ['schema' => fn(string $db): array => ['exit' => 1, 'stderr' => 'injected schema failure'],
            'stderr' => function (string $message) use (&$messages): void {
                $messages .= $message;
            }];
        $main = function (array $seams): array {
            ob_start();
            try {
                return [shopify_sandbox_main(['run.php', '--target=fake', '--tenant=' . getenv('SALDI_SHOPIFY_TEST_DB'), '--json'], $seams), ob_get_contents()];
            } finally {
                ob_end_clean();
            }
        };

        $this->assertSame([2, ''], $main($seams));
        $this->assertStringContainsString("setup failed, nothing run: schema ensure failed on $clone: injected schema failure", $messages);
        $this->assertSame([false, false], $this->tenantState($clone), 'unregistered and dropped');

        $messages = '';
        try {
            $this->assertSame([2, ''], $main($seams + ['unregister' => function (): void {
                throw new RuntimeException('injected unregister failure');
            }]));
            $this->assertStringContainsString('cleanup failed: unregister: injected unregister failure', $messages);
            $this->assertSame([false, true], $this->tenantState($clone), 'dropped though the unregister failed');
        } finally {
            ShopifyEnv::unregisterTenants();
        }
        $this->assertSame([false, false], $this->tenantState($clone));
    }

    public function testTheFakeTargetPassesK05AndRunsTheChainAcrossTheLanes(): void
    {
        $tenant = $this->requireDatabase();
        $run = $this->runner(['--target=fake', "--tenant=$tenant", '--json']);

        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $results = $run['json']['results'];
        $this->assertSame(0, $run['json']['counts']['FAIL'], json_encode(array_filter($results, fn(array $r): bool => $r['status'] === 'FAIL')));
        $this->assertSame(['PASS', 'PASS', 'PASS'], array_column(array_filter($results, fn(array $r): bool => $r['id'] === 'K05'), 'status'));
        $chain = array_values(array_filter($results, fn(array $r): bool => $r['id'] === 'X01'));
        $this->assertNull(ShopifyEnv::one(ShopifyEnv::connect(ShopifyEnv::masterDb()), 'SELECT 1 AS x FROM pg_database WHERE datname = $1', [$tenant . '_sr']), 'the clone is dropped');
        $byStep = array_column($chain, 'status', 'step');
        foreach (['events: register-webhooks (SD-749)', 'catalogue: the item becomes a draft in the primary shop (SD-741)', 'webhooks: the order\'s deliveries reach the inbox (SD-748)',
            'customers: the web order\'s customer becomes a debtor (SD-792)', 'webhooks: the shipment\'s deliveries reach the inbox (SD-748)',
            'replay: the chain\'s webhooks sent again change nothing (NF-1)'] as $merged) {
            $this->assertSame('PASS', $byStep[$merged] ?? null, $merged);
        }
        $blocked = array_values(array_unique(array_column(array_filter($chain, fn(array $r): bool => $r['status'] === 'BLOCKED'), 'owner')));
        $this->assertSame(['SD-756', 'SD-743', 'SD-739', 'SD-760', 'SD-740'], $blocked, 'each missing link names its lane');
    }

    public function testCalibrationWithoutADevStoreIsSkippedAndTheShapeDiffFindsDrift(): void
    {
        $run = $this->runner(['--calibrate', '--tenant=saldi_shopify_test_ci', '--json']);
        $this->assertSame(0, $run['exit'], $run['stderr']);
        $this->assertSame([['CAL', 'SKIP']], array_map(fn(array $r): array => [$r['id'], $r['status']], $run['json']['results']));

        $side = function (string $key): array {
            $store = new FakeShopifyStore();
            $row = $store->addShop($key);
            $store->seedProduct($row['domain'], 'kalibrering', 'SD766-C1', [$store->locations($row['domain'])[0] => 1]);
            return ['row' => $row, 'opts' => fn(): array => $store->opts()];
        };
        $dir = sys_get_temp_dir() . '/sd766-cal-' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            $same = shopify_calibrate($side('calfake1'), $side('calfake2'), $dir);
            $this->assertSame(array_fill(0, count(shopify_calibration_probes()), 'PASS'), array_column($same, 'status'), json_encode($same));
            $recorded = (string)file_get_contents("$dir/shop.json");
            $this->assertStringNotContainsString('calfake2-fake.myshopify.com', $recorded, 'handles are redacted');
            $this->assertStringContainsString('gid://shopify/Shop/N', $recorded, 'ids are masked');
            $this->assertStringNotContainsString(FakeShopify::TOKEN, $recorded);
        } finally {
            array_map('unlink', glob("$dir/*.json"));
            rmdir($dir);
        }

        $this->assertContains('inventory_set_without_key', array_column($same, 'step'), 'B21 is probed');
    }

    public function testCalibrationComparesSuccessAgainstFailureAndEveryErrorCode(): void
    {
        $answer = fn(array $userErrors, array $errors = [], array $nodes = [['id' => 'gid://shopify/Product/1']]): array => ['http' => ['code' => 200],
            'data' => $errors === [] ? ['metafieldsSet' => ['metafields' => null, 'userErrors' => $userErrors], 'products' => ['nodes' => $nodes]] : null,
            'errors' => $errors, 'cost' => ['requestedQueryCost' => 12, 'actualQueryCost' => 12, 'throttleStatus' => ['maximumAvailable' => 2000.0, 'currentlyAvailable' => 1988, 'restoreRate' => 100.0]]];
        $error = fn(string $code): array => ['field' => ['metafields'], 'message' => 'refused', 'code' => $code];

        $this->assertSame([], shopify_calibration_compare($answer([]), $answer([], [], [])), 'a resource collection may be empty on one side');
        $this->assertSame(['outcome: fake ok, real userErrors INVALID_VALUE', '$.data.metafieldsSet.userErrors: fake none, real {"field":["string"],"message":"string","code":"=\'INVALID_VALUE\'"}'],
            shopify_calibration_compare($answer([]), $answer([$error('INVALID_VALUE')])), 'the fake accepts what Shopify refuses');
        $this->assertSame('outcome: fake userErrors INVALID_VALUE, real ok', shopify_calibration_compare($answer([$error('INVALID_VALUE')]), $answer([]))[0], 'the fake refuses what Shopify accepts');
        $this->assertSame('outcome: fake ok, real refused THROTTLED', shopify_calibration_compare($answer([]), $answer([], [['message' => 'Throttled', 'code' => 'THROTTLED']]))[0]);
        $this->assertNotSame([], shopify_calibration_compare($answer([$error('INVALID_VALUE'), $error('TAKEN')]), $answer([$error('INVALID_VALUE'), $error('BLANK')])),
            'every code counts, not the first');
        $this->assertSame([], shopify_calibration_compare($answer([$error('TAKEN'), $error('INVALID_VALUE')]), $answer([$error('INVALID_VALUE'), $error('TAKEN'), $error('TAKEN')])),
            'the same codes in another order or number');
        $this->assertSame(['$.data.metafieldsSet.userErrors: fake {"field":["string"],"message":"string","code":"=\'TAKEN\'"}, real {"field":["string"],"message":"string","code":"=\'TAKEN\'","elementIndex":"number"}'],
            shopify_calibration_compare($answer([$error('TAKEN')]), $answer([$error('TAKEN') + ['elementIndex' => 0]])), 'an error\'s shape counts too');
        $dearer = $answer([]);
        $dearer['cost']['requestedQueryCost'] = 15;
        $this->assertSame(['requested cost: fake 12, real 15 (B09)'], shopify_calibration_compare($answer([]), $dearer), 'the cost is kept and compared');
        $float = $answer([]);
        $float['cost']['throttleStatus']['currentlyAvailable'] = 1988.0;
        $this->assertSame([], shopify_calibration_compare($answer([]), $float), 'a JSON number is a number');
    }

    public function testAwaitObservesUntilAcceptedOrTheTimeoutOnItsClock(): void
    {
        $now = 0.0;
        $seams = ['clock' => function () use (&$now): float {
            return $now;
        }, 'sleep' => function (float $s) use (&$now): void {
            $now += $s;
        }, 'interval' => 10.0];
        $n = 0;
        $seen = shopify_await(function () use (&$n): int {
            return ++$n;
        }, fn(int $v): bool => $v >= 4, 300, $seams);
        $this->assertSame(['ok' => true, 'value' => 4, 'seconds' => 30.0, 'polls' => 4], $seen);

        $now = 0.0;
        $never = shopify_await(fn(): bool => false, fn(bool $v): bool => $v, 25, $seams);
        $this->assertSame([false, 25.0, 4], [$never['ok'], $never['seconds'], $never['polls']]);
    }
}
