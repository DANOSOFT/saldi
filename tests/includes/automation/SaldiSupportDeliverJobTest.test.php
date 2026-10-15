<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiSupportDeliverJobTest.test.php --- ai/mcp-assistant --- 2026.10.03
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
// Regression coverage for approved support custody and transport receipts.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Add regression coverage for the confirmed Assist review findings.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiReachTestCase.php';
require_once __DIR__ . '/../../../includes/automation/jobs/SaldiSupportDeliverJob.php';
require_once __DIR__ . '/../../../includes/automation/SaldiJobRunner.php';

final class SaldiSupportDeliverJobTest extends SaldiReachTestCase
{
    private function approvedJob(): array
    {
        $this->company();
        $item = $this->inbox()->create($this->env->ctx(), 'support.report', [
            'category' => 'question', 'summary' => 'Spørgsmål om kassekladden',
            'details' => 'Kun godkendt syntetisk tekst med æ, ø og å.',
        ]);
        $applied = $this->approve($item);
        return $this->env->db->one('SELECT * FROM automation_jobs WHERE id = ?', [$applied['result']['job_id']]);
    }

    private function configure(): string
    {
        $secret = TestCredentials::password('support_hmac');
        putenv('SALDI_SUPPORT_INTAKE_URL=http://127.0.0.1:9876/intake');
        putenv('SALDI_SUPPORT_INTAKE_SECRET=' . $secret);
        return $secret;
    }

    public function testSignatureCoversRawUtf8BodyAndSuccessfulReceiptIsIdempotent(): void
    {
        $secret = $this->configure();
        $job = $this->approvedJob();
        $calls = 0;
        $transport = static function (
            string $url,
            string $body,
            array $headers,
            int $timeout,
            bool $redirects
        ) use (
            $secret,
            &$calls
        ): array {
            $calls++;
            self::assertSame('http://127.0.0.1:9876/intake', $url);
            self::assertSame(10, $timeout);
            self::assertFalse($redirects);
            self::assertContains('X-Saldi-Signature: sha256=' . hash_hmac('sha256', substr($headers[2], strlen('X-Saldi-Timestamp: ')) . '.' . $body, $secret), $headers);
            self::assertMatchesRegularExpression('/^X-Saldi-Timestamp: [0-9]+$/', $headers[2]);
            $payload = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
            self::assertCount(11, $payload);
            self::assertSame('Kun godkendt syntetisk tekst med æ, ø og å.', $payload['details']);
            self::assertNull($payload['screen']);
            self::assertArrayNotHasKey('email', $payload);
            return ['status' => 201, 'body' => '{"case_id":"SST-123"}'];
        };
        $result = SaldiSupportDeliverJob::run($this->env->db, $job, $transport);
        self::assertSame(['status' => 'done', 'result' => ['case_id' => 'SST-123']], $result);
        $payload = json_decode($job['payload'], true, 64, JSON_THROW_ON_ERROR);
        $item = $this->inbox()->get($this->env->ctx(), $payload['item_id']);
        self::assertSame('SST-123', $item['result']['support_case']);
        self::assertTrue($item['result']['queued']);
        self::assertSame($result, SaldiSupportDeliverJob::run($this->env->db, $job, $transport));
        self::assertSame(1, $calls);
    }

    public function testUnconfiguredDeliveryIsSkippedAndShowsAnHonestCard(): void
    {
        $job = $this->approvedJob();
        $runner = new SaldiJobRunner($this->env->db, SaldiJobRegistry::default());
        $claimed = $runner->claim();
        self::assertSame((int)$job['id'], (int)$claimed['id']);
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
        self::assertSame('skipped', $runner->run($worker, $claimed)['status']);
        $saved = $this->env->db->one('SELECT status, last_error FROM automation_jobs WHERE id = ?', [$job['id']]);
        self::assertSame(['status' => 'skipped', 'last_error' => 'not configured'], $saved);
        $payload = json_decode($job['payload'], true, 64, JSON_THROW_ON_ERROR);
        $item = $this->inbox()->get($ctx, $payload['item_id']);
        self::assertSame('not_configured', $item['result']['support_case']);
        self::assertContains('Ikke sendt: support er ikke sat op', array_column($item['card']['notices'], 'text'));
    }

    public function testFailuresDoNotStoreUntrustedResponseBodiesAndCanRetry(): void
    {
        $this->configure();
        $job = $this->approvedJob();
        foreach (
            [['status' => 302, 'body' => 'redirect'], ['status' => 500, 'body' => 'private response'],
            ['status' => 200, 'body' => 'not json'], ['status' => 200, 'body' => '{"case_id":"bad case"}']] as $response
        ) {
            self::assertSame('failed', SaldiSupportDeliverJob::run(
                $this->env->db,
                $job,
                static fn(): array => $response
            )['status']);
        }
        self::assertSame(['status' => 'failed', 'error' => 'support delivery failed'], SaldiSupportDeliverJob::run(
            $this->env->db,
            $job,
            static function (): never {
                throw new RuntimeException(TestCredentials::password('support_response'));
            }
        ));
        self::assertSame('done', SaldiSupportDeliverJob::run(
            $this->env->db,
            $job,
            static fn(): array => ['status' => 200, 'body' => '{"case_id":"SST-124"}']
        )['status']);
    }

    public function testUnapprovedAndChangedDescriptionsCannotReachTheTransport(): void
    {
        $this->configure();
        $job = $this->approvedJob();
        $payload = json_decode($job['payload'], true, 64, JSON_THROW_ON_ERROR);
        $payload['details'] = 'Changed after approval';
        $transport = static function (): never {
            self::fail('Unapproved content must never be delivered');
        };
        self::assertSame('failed', SaldiSupportDeliverJob::run(
            $this->env->db,
            array_replace($job, ['payload' => saldi_runtime_json($payload)]),
            $transport
        )['status']);
        $this->env->db->exec("UPDATE inbox_items SET status = 'open' WHERE id = ?", [$payload['item_id']]);
        self::assertSame('failed', SaldiSupportDeliverJob::run($this->env->db, $job, $transport)['status']);
        self::assertSame('failed', SaldiSupportDeliverJob::run(
            $this->env->db,
            ['payload' => 'invalid json'],
            $transport
        )['status']);
    }
    public function testFinalFailurePersistsNeutralNoticeThroughRunnerRollbackAndLaterRetry(): void
    {
        $this->configure();
        $job = $this->approvedJob();
        $payload = json_decode($job['payload'], true, 64, JSON_THROW_ON_ERROR);
        $registry = new SaldiJobRegistry();
        $registry->register('support.deliver', static fn(SaldiTenantContext $ctx, SaldiDb $db, array $job): array => SaldiSupportDeliverJob::run(
            $db, $job, static fn(): array => ['status' => 503, 'body' => 'private response']
        ));
        $runner = new SaldiJobRunner($this->env->db, $registry);
        $job = $runner->claim();
        self::assertSame('failed', $runner->run($this->env->ctx(), $job)['status']);
        self::assertArrayNotHasKey('support_case', $this->inbox()->get($this->env->ctx(), $payload['item_id'])['result']);
        $this->env->db->exec("UPDATE automation_jobs SET attempts = max_attempts - 1, run_after = ? WHERE id = ?", [gmdate('Y-m-d H:i:s'), $job['id']]);
        $job = $runner->claim();
        self::assertSame('failed', $runner->run($this->env->ctx(), $job)['status']);
        self::assertSame('failed', $this->env->db->value('SELECT status FROM automation_jobs WHERE id = ?', [$job['id']]));
        $item = $this->inbox()->get($this->env->ctx(), $payload['item_id']);
        self::assertSame('failed', $item['result']['support_case']);
        self::assertContains(['kind' => 'info', 'text' => 'Ikke sendt: kunne ikke nå Saldi-support. Prøv igen senere eller kontakt support.'], $item['card']['notices']);
        self::assertSame('done', SaldiSupportDeliverJob::run($this->env->db, $job, static fn(): array => ['status' => 200, 'body' => '{"case_id":"SST-125"}'])['status']);
    }

}
