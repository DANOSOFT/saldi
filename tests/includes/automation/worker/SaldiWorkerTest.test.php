<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/worker/SaldiWorkerTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// Clone-backed routine, lease, scheduling and CLI regressions.
//
// 20261001 CDX/LH Created.
// 20261002 CL/LH Expired lease on the last attempt fails; overdue routine uses the shared rate conversion.

declare(strict_types=1);

require_once __DIR__ . '/../../runtime/SaldiRuntimeDbTestCase.php';
require_once __DIR__ . '/../../../../includes/automation/SaldiJobRunner.php';

final class SaldiWorkerTest extends SaldiRuntimeDbTestCase
{
    private function registry(): SaldiJobRegistry
    {
        $r = SaldiJobRegistry::default();
        $r->bindPermissions();
        return $r;
    }

    private function worker(): SaldiTenantContext
    {
        $c = $this->env->ctx();
        return SaldiTenantContext::forSystem(
            $c->installId,
            $c->regnskabId,
            $c->db,
            $c->dbType,
            'system:worker',
            $c->fiscalYear,
            'DKK'
        );
    }

    private function clearBooks(): void
    {
        foreach (['openpost', 'kassekladde', 'kladdeliste', 'varer'] as $table) {
            $this->env->db->exec('DELETE FROM ' . $table);
        }
    }

    private function enable(string $name): void
    {
        SaldiSettings::set($this->env->db, $this->env->ctx(), 'automation', 'routine.' . $name, 'on');
    }

    private function job(string $type, int $max = 3): int
    {
        return $this->env->db->insert('automation_jobs', [
            'job_type' => $type,
            'payload' => '{"scheduled_date":"2026-10-01"}',
            'status' => 'pending',
            'run_after' => gmdate('Y-m-d H:i:s'),
            'max_attempts' => $max,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function product(int $id, string $stock = '1', string $minimum = '5', string $closed = ''): void
    {
        $this->env->db->insert('varer', [
            'id' => $id,
            'varenr' => 'P' . $id,
            'beskrivelse' => 'Product ' . $id,
            'beholdning' => $stock,
            'min_lager' => $minimum,
            'lukket' => $closed,
        ]);
    }

    public function testOverdueDebtorsGroupsRanksConvertsAndUsesStrictCutoff(): void
    {
        $this->clearBooks();
        $db = $this->env->db;
        $db->exec('DELETE FROM adresser');
        foreach (range(1, 23) as $id) {
            $db->insert('adresser', [
                'id' => $id,
                'kontonr' => (string)$id,
                'firmanavn' => 'Party ' . $id,
                'art' => $id === 23 ? 'K' : 'D',
            ]);
            $db->insert('openpost', [
                'konto_id' => $id,
                'amount' => (string)$id,
                'valuta' => 'DKK',
                'valutakurs' => '100',
                'forfaldsdate' => '2026-09-23',
                'udlignet' => '',
            ]);
        }
        $db->insert('openpost', [
            'konto_id' => 1,
            'amount' => '1.005',
            'valuta' => 'EUR',
            'valutakurs' => '750',
            'forfaldsdate' => '2026-09-01',
            'udlignet' => null,
        ]);
        foreach ([['2026-09-24', ''], ['2026-09-23', '1']] as [$date, $settled]) {
            $db->insert('openpost', [
                'konto_id' => 1,
                'amount' => '999',
                'valuta' => '',
                'forfaldsdate' => $date,
                'udlignet' => $settled,
            ]);
        }
        $routine = new SaldiOverdueDebtorsRoutine();
        $f = $routine->run($this->worker(), $db, '2026-10-01');
        self::assertSame(22, $f['count']);
        self::assertCount(20, $f['rows']);
        self::assertSame(['amount' => '260.54', 'currency' => 'DKK', 'converted' => true], $f['total']);
        self::assertSame('22.00', $f['rows'][0]['amount']);
        self::assertSame('debitor/debitorkort.php?id=22', $f['rows'][0]['screen']);
        $party = array_values(array_filter(
            $f['rows'],
            fn(array $r): bool => $r['screen'] === 'debitor/debitorkort.php?id=1'
        ));
        self::assertSame('8.54', $party[0]['amount']);
        $this->enable('overdue_debtors');
        $runner = new SaldiJobRunner($db, $this->registry());
        $this->job('routine.overdue_debtors');
        $first = $runner->run($this->worker(), $runner->claim());
        self::assertSame('finding', $first['result']['value']);
        $stored = $db->one('SELECT payload, evidence FROM inbox_items WHERE id = ?', [$first['result']['item_id']]);
        self::assertSame('260.54', json_decode($stored['payload'], true)['total']['amount']);
        self::assertStringContainsString('basisvaluta (DKK)', json_decode($stored['evidence'], true)['explanation']);
        $this->job('routine.overdue_debtors');
        self::assertSame(
            ['value' => 'unchanged', 'item_id' => $first['result']['item_id']],
            $runner->run($this->worker(), $runner->claim())['result']
        );
        $db->exec('DELETE FROM openpost');
        self::assertNull($routine->run($this->worker(), $db, '2026-10-01'));
    }

    public function testStaleJournalsRequireRowsOpenStatusAndStrictAge(): void
    {
        $this->clearBooks();
        $db = $this->env->db;
        foreach (
            [
                [1, '2026-09-16', null, true],
                [2, '2026-09-17', '-', true],
                [3, '2026-09-01', 'V', true],
                [4, '2026-09-01', '-', false],
            ] as [$id, $date, $status, $lines]
        ) {
            $db->insert('kladdeliste', [
                'id' => $id,
                'kladdedate' => $date,
                'kladdenote' => 'Journal',
                'bogfort' => $status,
            ]);
            if ($lines) {
                $db->insert('kassekladde', ['kladde_id' => $id, 'amount' => '10']);
            }
        }
        $routine = new SaldiStaleOpenJournalsRoutine();
        $f = $routine->run($this->worker(), $db, '2026-10-01');
        self::assertSame(1, $f['count']);
        self::assertSame('finans/kassekladde.php?kladde_id=1', $f['rows'][0]['screen']);
        $db->exec('DELETE FROM kassekladde');
        self::assertNull($routine->run($this->worker(), $db, '2026-10-01'));
    }

    public function testLowStockAndFindingKeepReplaceEmptyLifecycle(): void
    {
        $this->clearBooks();
        $this->enable('low_stock');
        $this->product(1);
        $this->product(2, '5');
        $this->product(3, '0', '0');
        $this->product(4, '0', '5', 'on');
        $db = $this->env->db;
        $registry = $this->registry();
        $runner = new SaldiJobRunner($db, $registry);
        $routine = new SaldiLowStockRoutine();
        self::assertSame(1, $routine->run($this->worker(), $db, '2026-10-01')['count']);
        $rule = SaldiTenantContext::forSystem('local', 2, $db->name(), $db->dbType(), 'rule:low_stock', 4, 'DKK');
        self::assertSame(['company.read', 'products.read'], SaldiPermissionService::effective($rule, $db));
        $this->job('routine.low_stock');
        $first = $runner->run($this->worker(), $runner->claim());
        self::assertSame('finding', $first['result']['value']);
        $item = $db->one('SELECT * FROM inbox_items WHERE id = ?', [$first['result']['item_id']]);
        self::assertSame('rule:low_stock', $item['created_by']);
        self::assertSame('ambient', $item['attention']);
        self::assertSame('products.read', $item['assignee_permission']);
        self::assertEqualsWithDelta(7 * 86400, strtotime($item['expires_at']) - strtotime($item['created_at']), 2);
        $this->job('routine.low_stock');
        $second = $runner->run($this->worker(), $runner->claim());
        self::assertSame(['value' => 'unchanged', 'item_id' => $first['result']['item_id']], $second['result']);
        $db->exec('UPDATE varer SET beholdning = 2 WHERE id = 1');
        $this->job('routine.low_stock');
        $third = $runner->run($this->worker(), $runner->claim());
        self::assertNotSame($first['result']['item_id'], $third['result']['item_id']);
        self::assertSame(
            'cancelled',
            $db->value('SELECT status FROM inbox_items WHERE id = ?', [$first['result']['item_id']])
        );
        $db->exec('UPDATE varer SET beholdning = 5 WHERE id = 1');
        self::assertNull($routine->run($this->worker(), $db, '2026-10-01'));
        $this->job('routine.low_stock');
        self::assertSame(['value' => 'none'], $runner->run($this->worker(), $runner->claim())['result']);
        self::assertSame(0, (int)$db->value("SELECT COUNT(*) FROM inbox_items WHERE status = 'open'"));
        self::assertSame(2, (int)$db->value(
            "SELECT COUNT(*) FROM decision_log WHERE event = 'cancelled' AND actor = 'rule:low_stock'"
        ));
        self::assertSame(2, (int)$db->value('SELECT COUNT(*) FROM inbox_items'));
    }

    public function testSchedulingIsIdempotentAndPinsLocalDateAndDisabledJobsSkip(): void
    {
        $runner = new SaldiJobRunner($this->env->db, $this->registry());
        self::assertFalse($runner->hasWork());
        self::assertSame(0, $runner->enqueue('2026-10-01'));
        $this->enable('low_stock');
        self::assertSame(1, $runner->enqueue('2026-10-01'));
        self::assertSame(0, $runner->enqueue('2026-10-01'));
        self::assertSame(1, $runner->enqueue('2026-10-02'));
        $jobs = $this->env->db->all('SELECT * FROM automation_jobs ORDER BY id');
        self::assertSame('routine:low_stock:2026-10-01', $jobs[0]['dedupe_key']);
        self::assertSame(['scheduled_date' => '2026-10-01'], json_decode($jobs[0]['payload'], true));
        SaldiSettings::set($this->env->db, $this->env->ctx(), 'automation', 'routine.low_stock', 'off');
        self::assertSame('skipped', $runner->run($this->worker(), $runner->claim())['status']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
    }

    public function testClaimSkipsLockedJobAndReclaimsExpiredLease(): void
    {
        $db = $this->env->db;
        $runner = new SaldiJobRunner($db, $this->registry());
        $first = $this->job('custom');
        $second = $this->job('custom');
        $other = SaldiDb::connect(saldi_runtime_config(), $this->env->name);
        try {
            $otherRunner = new SaldiJobRunner($other, $this->registry());
            $db->transaction(function () use ($db, $otherRunner, $first, $second): void {
                $db->one('SELECT id FROM automation_jobs WHERE id = ? FOR UPDATE', [$first]);
                $claim = $otherRunner->claim();
                self::assertSame($second, (int)$claim['id']);
            });
            $stale = $runner->claim();
            self::assertSame($first, (int)$stale['id']);
            self::assertNull($otherRunner->claim());
            $db->exec('UPDATE automation_jobs SET locked_until = ? WHERE id = ?', ['2000-01-01 00:00:00', $first]);
            $fresh = $otherRunner->claim();
            self::assertSame($first, (int)$fresh['id']);
            self::assertSame(2, (int)$fresh['attempts']);
            self::assertNotSame($stale['lease_token'], $fresh['lease_token']);
            self::assertSame('lease_lost', $runner->complete($stale, ['status' => 'done'])['result']['value']);
            self::assertSame('done', $otherRunner->complete($fresh, ['status' => 'done'])['status']);
        } finally {
            $other->close();
        }
    }

    public function testStaleWorkerRollsBackHandlerWritesAfterAnotherWorkerReclaims(): void
    {
        $db = $this->env->db;
        $registry = $this->registry();
        $other = SaldiDb::connect(saldi_runtime_config(), $this->env->name);
        try {
            $registry->register('test.fence', function ($ctx, SaldiDb $db, array $job) use ($other, $registry): array {
                $db->insert('settings', ['var_name' => 'stale_worker_marker']);
                $other->exec('UPDATE automation_jobs SET locked_until = ? WHERE id = ?', [
                    '2000-01-01 00:00:00',
                    $job['id'],
                ]);
                self::assertNotNull((new SaldiJobRunner($other, $registry))->claim());
                return ['status' => 'done'];
            });
            $runner = new SaldiJobRunner($db, $registry);
            $this->job('test.fence');
            $job = $runner->claim();
            self::assertSame('lease_lost', $runner->run($this->worker(), $job)['result']['value']);
            self::assertNull($db->value("SELECT id FROM settings WHERE var_name = 'stale_worker_marker'"));
            self::assertSame('running', $db->value('SELECT status FROM automation_jobs WHERE id = ?', [$job['id']]));
        } finally {
            $other->close();
        }
    }

    public function testFailuresBackOffExhaustAndUnknownJobsFailPermanently(): void
    {
        $db = $this->env->db;
        $registry = $this->registry();
        $registry->register('test.failure', function ($ctx, SaldiDb $db): array {
            $db->insert('settings', ['var_name' => 'failed_worker_marker']);
            throw new RuntimeException('Do not store arbitrary exception text');
        });
        $runner = new SaldiJobRunner($db, $registry);
        $id = $this->job('test.failure');
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $db->exec('UPDATE automation_jobs SET run_after = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $id]);
            $start = time();
            self::assertSame('failed', $runner->run($this->worker(), $runner->claim())['status']);
            $row = $db->one('SELECT * FROM automation_jobs WHERE id = ?', [$id]);
            self::assertSame($attempt, (int)$row['attempts']);
            self::assertSame($attempt === 3 ? 'failed' : 'pending', $row['status']);
            if ($attempt < 3) {
                self::assertEqualsWithDelta(60 * 2 ** $attempt, strtotime($row['run_after']) - $start, 2);
                self::assertNull($row['finished_at']);
            } else {
                self::assertNotNull($row['finished_at']);
            }
            self::assertNull($db->value("SELECT id FROM settings WHERE var_name = 'failed_worker_marker'"));
        }
        $unknown = $this->job('unknown');
        self::assertSame('failed', $runner->run($this->worker(), $runner->claim())['status']);
        self::assertSame(
            'unknown job type',
            $db->value('SELECT last_error FROM automation_jobs WHERE id = ?', [$unknown])
        );
        self::assertSame('failed', $db->value('SELECT status FROM automation_jobs WHERE id = ?', [$unknown]));
    }

    public function testReturnedFailureRollsBackAndPreservesOutcomeAndFence(): void
    {
        $db = $this->env->db;
        $registry = $this->registry();
        $registry->register('test.returned_failure', function ($ctx, SaldiDb $db): array {
            $db->insert('settings', ['var_name' => 'returned_failure_marker']);
            return ['status' => 'failed', 'error' => 'rule unavailable', 'result' => ['value' => 'retry']];
        });
        $runner = new SaldiJobRunner($db, $registry);
        $id = $this->job('test.returned_failure');
        $job = $runner->claim();
        $outcome = $runner->run($this->worker(), $job);
        self::assertSame('rule unavailable', $outcome['error']);
        self::assertSame('rule unavailable', $db->value('SELECT last_error FROM automation_jobs WHERE id = ?', [$id]));
        $result = $db->value('SELECT result FROM automation_jobs WHERE id = ?', [$id]);
        self::assertSame(['value' => 'retry'], json_decode($result, true));
        self::assertNull($db->value("SELECT id FROM settings WHERE var_name = 'returned_failure_marker'"));
        $stale = $runner->complete($job, ['status' => 'failed', 'error' => 'stale']);
        self::assertSame('lease_lost', $stale['result']['value']);
        self::assertSame('rule unavailable', $db->value('SELECT last_error FROM automation_jobs WHERE id = ?', [$id]));
    }

    public function testWorkerExpiresItemsWithAllRoutinesOffAndSkipsMissingInbox(): void
    {
        $db = $this->env->db;
        $item = $this->inbox()->create(
            $this->env->ctx(),
            'followup.task',
            ['title' => 'Expired task'],
            ['expires_at' => '2000-01-01 00:00:00']
        );
        self::assertTrue((new SaldiJobRunner($db, $this->registry()))->hasWork());
        self::assertSame([0, ''], $this->cli('--once --tenant=' . $this->env->name));
        self::assertSame('expired', $db->value('SELECT status FROM inbox_items WHERE id = ?', [$item['id']]));
        $id = $this->job('unknown');
        $db->exec('DROP TABLE inbox_items');
        self::assertSame([0, ''], $this->cli('--once --tenant=' . $this->env->name));
        self::assertFalse($db->hasTable('inbox_items'));
        self::assertSame('pending', $db->value('SELECT status FROM automation_jobs WHERE id = ?', [$id]));
    }

    /** @return array{int,string} */
    private function cli(string $options, bool $disabled = false): array
    {
        $env = getenv();
        $env['SALDI_AUTOMATION_DISABLED'] = $disabled ? '1' : '0';
        $pipes = [];
        $process = proc_open([
            PHP_BINARY,
            'cron/automation_worker.php',
            ...explode(' ', $options),
        ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 4), $env);
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $out];
    }

    public function testCliDryRunDisabledSkipTablesOffExpiryLimitAndFailureExit(): void
    {
        $db = $this->env->db;
        $this->clearBooks();
        $this->enable('low_stock');
        $this->product(1);
        $ctx = $this->env->ctx();
        $expired = $this->inbox()->create(
            $ctx,
            'followup.task',
            ['title' => 'Expire this'],
            ['expires_at' => '2000-01-01 00:00:00']
        );
        $before = $db->all('SELECT * FROM inbox_items ORDER BY id');
        self::assertSame([0, ''], $this->cli('--once --dry-run --tenant=' . $this->env->name));
        self::assertSame($before, $db->all('SELECT * FROM inbox_items ORDER BY id'));
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM automation_jobs'));
        self::assertSame([0, "Automation disabled\n"], $this->cli('--once --tenant=' . $this->env->name, true));
        [$code, $out] = $this->cli('--once --tenant=' . $this->env->name . ' --limit=1');
        self::assertSame(0, $code, $out);
        $pattern = '/^' . $this->env->name . "\troutine.low_stock\t[0-9]+\tdone\t[0-9]+\n$/";
        self::assertMatchesRegularExpression($pattern, $out);
        fwrite(STDOUT, "Worker CLI clone output: " . $out);
        self::assertSame('expired', $db->value('SELECT status FROM inbox_items WHERE id = ?', [$expired['id']]));
        SaldiSettings::set($db, $ctx, 'automation', 'routine.low_stock', 'off');
        self::assertSame([0, ''], $this->cli('--once --tenant=' . $this->env->name));
        $this->job('unknown');
        $this->job('unknown');
        [$code, $out] = $this->cli('--once --tenant=' . $this->env->name . ' --limit=1');
        self::assertSame(1, $code);
        self::assertStringContainsString("\tfailed\t", $out);
        self::assertSame(1, (int)$db->value("SELECT COUNT(*) FROM automation_jobs WHERE status = 'pending'"));
        $db->exec('DROP TABLE automation_jobs');
        self::assertSame([0, ''], $this->cli('--once --tenant=' . $this->env->name));
        self::assertFalse($db->hasTable('automation_jobs'));
        $help = "Usage: php cron/automation_worker.php [--once] [--tenant=<db>] "
            . "[--limit=1..500] [--dry-run] [--help]\n";
        self::assertSame([0, $help], $this->cli('--help'));
        foreach (['--limit=0', '--limit=501', '--tenant=bad/name', '--unknown'] as $arg) {
            self::assertSame(1, $this->cli($arg)[0]);
        }
    }

    public function testExpiredLeaseOnTheLastAttemptFailsInsteadOfRetrying(): void
    {
        $db = $this->env->db;
        $runner = new SaldiJobRunner($db, $this->registry());
        $poison = $this->job('custom');
        $retry = $this->job('custom');
        $db->exec("UPDATE automation_jobs SET status = 'running', attempts = 3, lease_token = 'dead', locked_until = ? WHERE id = ?", ['2000-01-01 00:00:00', $poison]);
        $db->exec("UPDATE automation_jobs SET status = 'running', attempts = 2, lease_token = 'dead', locked_until = ? WHERE id = ?", ['2000-01-01 00:00:00', $retry]);
        $claim = $runner->claim();
        self::assertSame($retry, (int)$claim['id']);
        self::assertSame(3, (int)$claim['attempts']);
        $row = $db->one('SELECT * FROM automation_jobs WHERE id = ?', [$poison]);
        self::assertSame('failed', $row['status']);
        self::assertSame('lease expired', $row['last_error']);
        self::assertSame(3, (int)$row['attempts']);
        self::assertNotNull($row['finished_at']);
        self::assertNull($row['lease_token']);
        self::assertNull($row['locked_until']);
        self::assertNull($runner->claim());
        // A live lease on the last attempt is left alone.
        $live = $this->job('custom');
        $db->exec("UPDATE automation_jobs SET status = 'running', attempts = 3, lease_token = 'alive', locked_until = ? WHERE id = ?", [gmdate('Y-m-d H:i:s', time() + 300), $live]);
        self::assertNull($runner->claim());
        self::assertSame('running', $db->value('SELECT status FROM automation_jobs WHERE id = ?', [$live]));
    }

    public function testOverdueDebtorsResolvesPlaceholderRatesAndLeavesUnconvertibleItemsOut(): void
    {
        $this->clearBooks();
        $db = $this->env->db;
        $db->exec('DELETE FROM adresser');
        $db->exec("DELETE FROM grupper WHERE art = 'VK'");
        $db->exec('DELETE FROM valuta');
        $db->insert('grupper', ['art' => 'VK', 'kodenr' => 7, 'box1' => 'SEK', 'beskrivelse' => 'Svenske kroner']);
        $db->insert('valuta', ['gruppe' => 7, 'valdate' => '2026-08-01', 'kurs' => '65.00']);
        foreach ([1 => ['SEK', '100', '100'], 2 => ['-', '0', '30'], 3 => ['NOK', null, '1000'], 4 => ['DKK', '100', '10']] as $id => [$currency, $rate, $amount]) {
            $db->insert('adresser', ['id' => $id, 'kontonr' => (string)$id, 'firmanavn' => 'Party ' . $id, 'art' => 'D']);
            $db->insert('openpost', ['konto_id' => $id, 'amount' => $amount, 'valuta' => $currency, 'valutakurs' => $rate, 'transdate' => '2026-09-01', 'forfaldsdate' => '2026-09-10', 'udlignet' => '']);
        }
        $f = (new SaldiOverdueDebtorsRoutine())->run($this->worker(), $db, '2026-10-01');
        self::assertSame(3, $f['count']);
        self::assertSame(['amount' => '105.00', 'currency' => 'DKK', 'converted' => false], $f['total']);
        self::assertSame(['65.00', '30.00', '10.00'], array_column($f['rows'], 'amount'));
        self::assertNotContains('debitor/debitorkort.php?id=3', array_column($f['rows'], 'screen'));
    }
}
