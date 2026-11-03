<?php
// tests/shopify/SandboxRunnerTest.php
//
// The sandbox runner (SD-766, support/sandbox/run.php) as a command: an unsafe tenant or a half
// credential exits 2 before anything runs, the sandboxes without credentials give SKIP, the fake
// target passes K05 and runs the cross-lane chain X01 on a cloned tenant (SKIP without a
// database), calibration without a dev store is SKIP, and the calibration's shape diff and
// shopify_await() work on two fakes and a fake clock.
//
// History:
// 20261103 CL/LH SD-766: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/sandbox/run.php';

final class SandboxRunnerTest extends TestCase
{
    /**
     * Runs the runner as a child: this process's environment without sandbox or dev-store
     * credentials, plus $env.
     *
     * @return array{exit: int, stdout: string, stderr: string, json: ?array}
     */
    private function runner(array $args, array $env = []): array
    {
        $base = array_filter(getenv(), fn(string $k): bool => !preg_match('/^SALDI_SHOPIFY_(SANDBOX|DEVSTORE)_/', $k), ARRAY_FILTER_USE_KEY);
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

    public function testTheFakeTargetPassesK05AndRunsTheChainAcrossTheLanes(): void
    {
        $tenant = (string)getenv('SALDI_SHOPIFY_TEST_DB') ?: 'saldi_shopify_test_ci';
        $run = $this->runner(['--target=fake', "--tenant=$tenant", '--json']);

        $this->assertSame(0, $run['exit'], $run['stderr'] . $run['stdout']);
        $results = $run['json']['results'];
        $this->assertSame(0, $run['json']['counts']['FAIL'], json_encode(array_filter($results, fn(array $r): bool => $r['status'] === 'FAIL')));
        $this->assertSame(['PASS', 'PASS', 'PASS'], array_column(array_filter($results, fn(array $r): bool => $r['id'] === 'K05'), 'status'));
        $chain = array_values(array_filter($results, fn(array $r): bool => $r['id'] === 'X01'));
        if (ShopifyEnv::unavailableReason() !== null) {
            $this->assertSame('SKIP', $chain[0]['status'], 'without a database the chain is skipped');
            return;
        }
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

        $fake = shopify_calibration_shape(['http' => ['code' => 200], 'data' => ['metafieldsSet' => ['metafields' => null, 'userErrors' => [['field' => ['metafields'], 'code' => 'LESS_THAN_OR_EQUAL_TO']]]]]);
        $real = shopify_calibration_shape(['http' => ['code' => 200], 'data' => ['metafieldsSet' => ['metafields' => null, 'userErrors' => [['field' => ['metafields', '25'], 'code' => 'INVALID_VALUE', 'extra' => 1]]]]]);
        $this->assertSame(['$.data.metafieldsSet.userErrors[0].code: fake =\'LESS_THAN_OR_EQUAL_TO\', real =\'INVALID_VALUE\'',
            '$.data.metafieldsSet.userErrors[0].extra: only the real store has it'], shopify_calibration_diff($fake, $real));
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
