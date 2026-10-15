<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiDigestJobTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Regression coverage for the owned procedure, mail and digest services.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiReachTestCase.php';
require_once __DIR__ . '/../../../includes/automation/jobs/SaldiDigestJob.php';
require_once __DIR__ . '/../../../includes/automation/SaldiJobRunner.php';

final class SaldiDigestJobTest extends SaldiReachTestCase
{
    public function testCopenhagenScheduleLatestOptInAndDailyDedupe(): void
    {
        $this->setting('assistant', 'digest', 'daily', 910001);
        $before = new DateTimeImmutable('2026-10-02T04:59:59Z');
        $after = new DateTimeImmutable('2026-10-02T05:00:00Z');
        self::assertFalse(SaldiDigestJob::shouldEnqueue($this->env->db, $before));
        self::assertTrue(SaldiDigestJob::shouldEnqueue($this->env->db, $after));
        self::assertFalse(SaldiDigestJob::shouldEnqueue($this->env->db, new DateTimeImmutable('2026-12-02T05:59:59Z')));
        self::assertTrue(SaldiDigestJob::shouldEnqueue($this->env->db, new DateTimeImmutable('2026-12-02T06:00:00Z')));
        $runner = new SaldiJobRunner($this->env->db, SaldiJobRegistry::default());
        self::assertSame(1, $runner->enqueue('2026-10-02', $after));
        self::assertSame(0, $runner->enqueue('2026-10-02', $after));
        self::assertSame('digest:2026-10-02', $this->env->db->value(
            "SELECT dedupe_key FROM automation_jobs WHERE job_type = 'digest.daily'"
        ));
        $this->setting('assistant', 'digest', 'off', 910001);
        self::assertFalse(SaldiDigestJob::shouldEnqueue($this->env->db, $after));
        $this->setting('assistant', 'digest', 'daily', 910001);
        putenv('SALDI_DIGEST_DISABLED=1');
        self::assertFalse(SaldiDigestJob::shouldEnqueue($this->env->db, $after));
        self::assertSame('skipped', SaldiDigestJob::run($this->env->db, [])['status']);
    }

    public function testDigestIsUserScopedBoundedAndIdempotent(): void
    {
        $this->company();
        $this->setting('assistant', 'digest', 'daily', 910001);
        $this->setting('assistant', 'digest', 'daily', 910002);
        $this->setting('assistant', 'digest_email', 'digest@example.test', 910001);
        $this->env->db->exec('UPDATE brugere SET email = ? WHERE id = ?', ['other@example.test', 910002]);
        for ($index = 0; $index < 12; $index++) {
            $this->inbox()->create($this->env->ctx(), 'followup.task', ['title' => 'Task ' . $index]);
        }
        $this->inbox()->create($this->env->ctx(910002), 'followup.task', ['title' => 'Other private title']);
        $calls = [];
        $mailer = new SaldiMailer(static function (array $config, array $message) use (&$calls): bool {
            $calls[] = $message;
            return true;
        });
        putenv('SALDI_PUBLIC_URL=http://localhost/saldi');
        $job = ['payload' => '{"scheduled_date":"2026-10-02"}'];
        $overview = static function (SaldiTenantContext $ctx): array {
            return ['nothing_to_do' => true] + ($ctx->userId === 910001 ? [
                'overdue_debtors' => ['count' => 2, 'amount' => '1250.00', 'currency' => 'DKK'],
            ] : []);
        };
        $context = fn(int $userId): SaldiTenantContext => $this->env->ctx($userId, '');
        $outcome = SaldiDigestJob::run($this->env->db, $job, $mailer, $overview, $context);
        self::assertSame(2, $outcome['result']['sent']);
        self::assertCount(2, $calls);
        self::assertSame('digest@example.test', $calls[0]['to']);
        self::assertSame('other@example.test', $calls[1]['to']);
        self::assertStringContainsString('12 åbne opgaver', $calls[0]['text']);
        self::assertStringContainsString('1250.00 DKK', $calls[0]['text']);
        self::assertStringNotContainsString('Other private title', $calls[0]['text']);
        self::assertStringNotContainsString('- Task 0', $calls[0]['text']);
        self::assertStringNotContainsString('- Task 1' . "\n", $calls[0]['text']);
        self::assertStringContainsString('Åbn Saldi: http://localhost/saldi', $calls[0]['text']);
        self::assertStringContainsString('<a href="http://localhost/saldi">Åbn Saldi</a>', $calls[0]['html']);
        self::assertStringContainsString('dagligt overblik', $calls[0]['text']);
        self::assertStringNotContainsString('1250.00', $calls[1]['text']);
        self::assertStringNotContainsString('Task 11', $calls[1]['text']);
        self::assertSame(2, (int)$this->env->db->value(
            "SELECT COUNT(*) FROM assist_events WHERE event = 'digest_sent'"
        ));
        self::assertSame(0, SaldiDigestJob::run($this->env->db, $job, $mailer, $overview, $context)['result']['sent']);
        self::assertCount(2, $calls);
    }

    public function testEmptyOverviewAndDisabledMailNeverSend(): void
    {
        $this->setting('assistant', 'digest', 'daily', 910001);
        $mailer = new SaldiMailer(static function (): never {
            self::fail('Empty digests must not send');
        });
        $job = ['payload' => '{"scheduled_date":"2026-10-02"}'];
        $result = SaldiDigestJob::run(
            $this->env->db,
            $job,
            $mailer,
            static fn(): array => ['nothing_to_do' => true],
            fn(int $userId): SaldiTenantContext => $this->env->ctx($userId)
        );
        self::assertSame(['value' => 'none', 'sent' => 0, 'empty' => 1], $result['result']);
        self::assertSame(0, (int)$this->env->db->value(
            "SELECT COUNT(*) FROM assist_events WHERE event = 'digest_sent'"
        ));
        putenv('SALDI_MAIL_DISABLED=1');
        self::assertSame('skipped', SaldiDigestJob::run($this->env->db, $job, $mailer)['status']);
    }

    public function testRealOverviewRunsWithoutAWebSessionOrTransport(): void
    {
        $this->company();
        $this->setting('assistant', 'digest', 'daily', 910001);
        $this->setting('assistant', 'digest_email', 'digest@example.test', 910001);
        $this->inbox()->create($this->env->ctx(), 'followup.task', ['title' => 'CLI overview task']);
        $sent = 0;
        $mailer = new SaldiMailer(static function (array $config, array $message) use (&$sent): bool {
            $sent++;
            self::assertStringContainsString('CLI overview task', $message['text']);
            return true;
        });
        self::assertSame('done', SaldiDigestJob::run(
            $this->env->db,
            ['payload' => '{"scheduled_date":"2026-10-02"}'],
            $mailer
        )['status']);
        self::assertSame(1, $sent);
    }

    public function testSuccessfulUserReceiptSurvivesWorkerRollbackAndRetry(): void
    {
        $this->company();
        foreach ([910001, 910002] as $userId) {
            $this->setting('assistant', 'digest', 'daily', $userId);
            $this->setting('assistant', 'digest_email', $userId . '@example.test', $userId);
        }
        $attempts = [];
        $mailer = new SaldiMailer(static function (array $config, array $message) use (&$attempts): bool {
            $address = $message['to'];
            $attempts[$address] = ($attempts[$address] ?? 0) + 1;
            return $address !== '910002@example.test' || $attempts[$address] > 1;
        });
        $registry = SaldiJobRegistry::default();
        $registry->register(
            'digest.daily',
            fn(SaldiTenantContext $ctx, SaldiDb $db, array $job): array => SaldiDigestJob::run(
                $db,
                $job,
                $mailer,
                static fn(): array => ['nothing_to_do' => false],
                fn(int $userId): SaldiTenantContext => $this->env->ctx($userId)
            )
        );
        $runner = new SaldiJobRunner($this->env->db, $registry);
        $runner->enqueue('2026-10-02', new DateTimeImmutable('2026-10-02T07:00:00+02:00'));
        $claimed = $runner->claim();
        $ctx = $this->env->ctx();
        $worker = SaldiTenantContext::forSystem(
            $ctx->installId,
            $ctx->regnskabId,
            $ctx->db,
            $ctx->dbType,
            'system:worker',
            $ctx->fiscalYear,
            $ctx->baseCurrency
        );
        self::assertSame('failed', $runner->run($worker, $claimed)['status']);
        self::assertSame(1, (int)$this->env->db->value(
            "SELECT COUNT(*) FROM assist_events WHERE event = 'digest_sent'"
        ));
        $this->env->db->exec('UPDATE automation_jobs SET run_after = ? WHERE id = ?', [
            gmdate('Y-m-d H:i:s', time() - 1), $claimed['id'],
        ]);
        self::assertSame('done', $runner->run($worker, $runner->claim())['status']);
        self::assertSame(['910001@example.test' => 1, '910002@example.test' => 2], $attempts);
        self::assertSame(2, (int)$this->env->db->value(
            "SELECT COUNT(*) FROM assist_events WHERE event = 'digest_sent'"
        ));
    }
}
