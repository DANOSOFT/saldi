<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiMemoryTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Private and company facts, UTF-8 caps under locks, secret rejection and retraction.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Cover F1 integration behavior and regression cases.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';

final class SaldiMemoryTest extends SaldiRuntimeDbTestCase
{
    public function testMemoryTitlesUseFactPreviewsBoundedInCharacters(): void
    {
        $ctx = $this->env->ctx();
        foreach (['Monthly reports', str_repeat('ø', 90)] as $fact) {
            $item = $this->inbox()->create($ctx, 'memory.fact', ['scope' => 'user', 'fact' => $fact]);
            self::assertSame('Husk: ' . mb_substr($fact, 0, 80), $item['title']);
            self::assertSame($item['title'], $item['card']['title']);
            self::assertSame('Gem oplysning', $item['card']['kind_label']);
            $applied = $this->approve($item);
            $retract = $this->inbox()->create($ctx, 'memory.retract', ['memory_id' => $applied['result']['memory_id']]);
            self::assertSame('Glem: ' . mb_substr($fact, 0, 80), $retract['title']);
            self::assertSame($fact, $retract['payload']['fact']);
        }
    }

    public function testPrivateFactsStayPrivateAndUndoRetracts(): void
    {
        $ctx = $this->env->ctx();
        $item = $this->inbox()->create($ctx, 'memory.fact', ['scope' => 'user', 'fact' => 'I prefer monthly reports']);
        $this->error('NOT_FOUND', fn() => $this->approve($item, $this->env->ctx(910002)));
        $applied = $this->approve($item);
        self::assertTrue($applied['can_undo']);
        self::assertCount(1, SaldiMemoryStore::active($this->env->db, $ctx->userId)['user']);
        self::assertSame([], SaldiMemoryStore::active($this->env->db, 910002)['user']);
        $id = $applied['result']['memory_id'];
        $this->error('NOT_FOUND', fn() => SaldiMemoryStore::retract($this->env->db, $this->env->ctx(910002), $id, 'mine'));
        $oldRevision = SaldiMemoryStore::active($this->env->db, $ctx->userId)['revision'];
        self::assertSame('undone', $this->inbox()->undo($ctx, $item['id'], $ctx->tenantHash())['status']);
        self::assertSame([], SaldiMemoryStore::active($this->env->db, $ctx->userId)['user']);
        self::assertNotSame($oldRevision, SaldiMemoryStore::active($this->env->db, $ctx->userId)['revision']);
        self::assertSame(1, (int)$this->env->db->value("SELECT COUNT(*) FROM decision_log WHERE event = 'memory_retracted'"));
    }

    public function testCompanyFactsNeedAdminAndRetractionIsApprovalGated(): void
    {
        $ctx = $this->env->ctx(910001, '2');
        $item = $this->inbox()->create($ctx, 'memory.fact', ['scope' => 'company', 'fact' => 'The company prefers monthly reports']);
        $this->error('PERMISSION_DENIED', fn() => $this->approve($item, $ctx));
        $applied = $this->approve($item, $this->env->ctx(910002));
        self::assertCount(1, SaldiMemoryStore::active($this->env->db, 910001)['company']);
        self::assertCount(1, SaldiMemoryStore::active($this->env->db, 910002)['company']);
        $this->error('PERMISSION_DENIED', fn() => SaldiMemoryStore::retract($this->env->db, $ctx, $applied['result']['memory_id'], 'remove'));
        $retract = $this->inbox()->create($ctx, 'memory.retract', ['memory_id' => $applied['result']['memory_id'], 'reason' => 'Outdated']);
        self::assertSame('company', $retract['payload']['target_scope']);
        // Asking again for the same fact returns the open retraction instead of a second one.
        $again = $this->inbox()->create($ctx, 'memory.retract', ['memory_id' => $applied['result']['memory_id'], 'reason' => 'Outdated']);
        self::assertSame($retract['id'], $again['id']);
        self::assertTrue($again['deduplicated']);
        self::assertTrue($this->approve($retract, $this->env->ctx(910002))['result']['retracted']);
        self::assertFalse($this->inbox()->get($ctx, $item['id'])['can_undo']);
        self::assertSame([], SaldiMemoryStore::active($this->env->db, 910001)['company']);
    }

    public function testCapsAreRecheckedAtApplyAndScopeLocksAreHeld(): void
    {
        $ctx = $this->env->ctx();
        $db = $this->env->db;
        $pending = $this->inbox()->create($ctx, 'memory.fact', ['scope' => 'user', 'fact' => str_repeat('ø', 300)]);
        foreach ([str_repeat('a', 300), str_repeat('b', 300)] as $fact) {
            $this->approve($this->inbox()->create($ctx, 'memory.fact', ['scope' => 'user', 'fact' => $fact]));
        }
        $this->error('MEMORY_FULL', fn() => $this->approve($pending));
        self::assertSame('open', $this->inbox()->get($ctx, $pending['id'])['status']);
        $this->error('MEMORY_FULL', fn() => $this->inbox()->create($ctx, 'memory.fact', ['scope' => 'user', 'fact' => str_repeat('c', 201)]));
        $this->approve($this->inbox()->create($ctx, 'memory.fact', ['scope' => 'user', 'fact' => str_repeat('d', 200)]));
        $other = SaldiDb::connect(saldi_runtime_config(), $db->name());
        try {
            $db->transaction(function () use ($ctx, $db, $other): void {
                SaldiMemoryStore::checkCap($db, 'company', null, 'Fact');
                self::assertFalse((bool)$other->value('SELECT pg_try_advisory_lock(hashtext(?))', ['saldi_memory:company:0']));
            });
        } finally {
            $other->close();
        }
        for ($i = 0; $i < 6; $i++) {
            $this->approve($this->inbox()->create($ctx, 'memory.fact', ['scope' => 'company', 'fact' => str_repeat(chr(65 + $i), 300)]));
        }
        $this->approve($this->inbox()->create($ctx, 'memory.fact', ['scope' => 'company', 'fact' => str_repeat('z', 200)]));
        $this->error('MEMORY_FULL', fn() => $this->inbox()->create($ctx, 'memory.fact', ['scope' => 'company', 'fact' => 'Extra fact']));
    }

    public function testSecretValidationControlsAndPrivateRetractionRules(): void
    {
        $ctx = $this->env->ctx();
        $secretLike = 'ExampleValue' . random_int(100000, 999999);
        foreach (['password: example', 'api_key=example', 'cpr 123', $secretLike, 'xx', str_repeat('x', 301)] as $fact) {
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($ctx, 'memory.fact', ['scope' => 'user', 'fact' => $fact]));
        }
        $item = $this->inbox()->create($ctx, 'memory.fact', ['scope' => 'user', 'fact' => "Monthly\x01 reports"]);
        self::assertSame('Monthly reports', $item['payload']['fact']);
        $applied = $this->approve($item);
        $this->error('NOT_FOUND', fn() => $this->inbox()->create($this->env->ctx(910002), 'memory.retract', ['memory_id' => $applied['result']['memory_id']]));
        $retract = $this->inbox()->create($ctx, 'memory.retract', ['memory_id' => $applied['result']['memory_id']]);
        self::assertTrue($this->approve($retract)['result']['retracted']);
        $this->error('NOT_FOUND', fn() => $this->inbox()->create($ctx, 'memory.retract', ['memory_id' => $applied['result']['memory_id']]));
    }
}
