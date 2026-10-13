<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/InvoicePaymentOperationsTest.php --- 2026.10.12
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
// 20261012 CL/LH Immutable requests, tenant/mode isolation, committed dispatch and replay expiration.
// 20261012 CL/LH Distinguish definitive Stripe rejections from transport/conflict/throttle/server ambiguity.
// 20261012 CL/LH Regress sticky ambiguity across later rejections, repaired credentials and interrupted dispatches.

use PHPUnit\Framework\TestCase;
use Saldi\Stripe\InvoicePaymentOperations;
use Saldi\Stripe\InvoicePaymentSchema;
use Saldi\Stripe\InvoicePaymentStore;

require_once __DIR__ . '/support/InvoicePaymentDatabase.php';
require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentOperations.php';

final class InvoicePaymentOperationsTest extends TestCase
{
    use InvoicePaymentDatabase;
    private int $now = 1791360000;

    protected function setUp(): void
    {
        $this->openDatabase();
        $this->db = new InvoicePaymentStore($this->db->pdo, fn() => $this->now);
        foreach (InvoicePaymentSchema::statements($this->db->driver()) as $sql) $this->db->query($sql);
        $this->db->query('CREATE TABLE settings (var_grp TEXT,var_name TEXT,var_value TEXT)');
        $this->db->query("INSERT INTO settings VALUES ('stripe','mode','test')");
    }

    public function testPreparePreservesFirstPayloadKeyAndCardGeneration(): void
    {
        $operations = new InvoicePaymentOperations($this->db, 'tenant-a');
        $a = $operations->prepare('intent_create', '1', '/v1/payment_intents', ['amount' => 12345, 'customer' => 'cus_a'], 10, 12345, 2, 1);
        $b = $operations->prepare('intent_create', '1', '/v1/payment_intents', ['amount' => 999, 'customer' => 'cus_changed'], 10, 999, 3, 1);
        $this->assertSame($a, $b);
        $this->assertSame(['amount' => 12345, 'customer' => 'cus_a'], json_decode($b['request_json'], true));
        $this->assertSame(2, (int)$b['card_generation']);
        $this->assertNull($b['first_dispatch_at']);
        $this->assertStringStartsWith('saldi-' . hash('sha256', 'tenant-a') . '-test-', $b['idempotency_key']);
    }

    public function testKeysAreNamespacedByModeAndTenant(): void
    {
        $a = (new InvoicePaymentOperations($this->db, 'tenant-a'))->prepare('customer_create', '1', '/v1/customers', []);
        // Another database has the same local id, but a different Stripe idempotency namespace.
        $this->db->query('DELETE FROM stripe_invoice_operations');
        $b = (new InvoicePaymentOperations($this->db, 'tenant-b'))->prepare('customer_create', '1', '/v1/customers', []);
        $this->db->query("UPDATE settings SET var_value='live'");
        $c = (new InvoicePaymentOperations($this->db, 'tenant-b'))->prepare('customer_create', '1', '/v1/customers', []);
        $this->assertCount(3, array_unique([$a['idempotency_key'], $b['idempotency_key'], $c['idempotency_key']]));
    }

    public function testLostResponseReplaysCommittedOriginalRequestAndKey(): void
    {
        $operations = new InvoicePaymentOperations($this->db, 'tenant-a');
        $op = $operations->prepare('intent_create', '1', '/v1/payment_intents', ['amount' => 12345], 10, 12345, 2, 1);
        $calls = [];
        $api = function ($method, $path, $params, $key) use (&$calls) {
            $this->assertFalse($this->db->inTransaction());
            $stored = $this->db->row('SELECT * FROM stripe_invoice_operations WHERE idempotency_key=?', [$key]);
            $this->assertSame($this->now - (count($calls) ? 600 : 0), (int)$stored['first_dispatch_at']);
            $calls[] = [$method, $path, $params, $key];
            if (count($calls) === 1) throw new RuntimeException('Response lost');
            return ['id' => 'pi_existing', 'client_secret' => 'not_persisted'];
        };
        try { $operations->dispatch((int)$op['id'], $api, static function () {}); $this->fail('Expected lost response'); } catch (RuntimeException $e) { }
        $this->now += 600;
        $this->assertSame('pi_existing', $operations->dispatch((int)$op['id'], $api, static function () {})['id']);
        $this->assertSame($calls[0], $calls[1]);
        $stored = $this->db->row('SELECT * FROM stripe_invoice_operations');
        $this->assertSame('succeeded', $stored['status']);
        $this->assertSame('pi_existing', $stored['result_id']);
        $this->assertStringNotContainsString('client_secret', InvoicePaymentStore::json($stored));
    }

    public function testReplayBoundaryMarksNeedsReconcileAndCommitsAlert(): void
    {
        $operations = new InvoicePaymentOperations($this->db, 'tenant-a');
        $op = $operations->prepare('intent_create', '1', '/v1/payment_intents', ['amount' => 12345], 10);
        try { $operations->dispatch((int)$op['id'], static function () { throw new RuntimeException('Timeout'); }, static function () {}); } catch (RuntimeException $e) { }
        $this->now += InvoicePaymentOperations::REPLAY_SECONDS;
        try {
            $operations->dispatch((int)$op['id'], static function () { throw new LogicException('Must not POST after expiry'); }, function ($row) {
                $this->assertTrue($this->db->inTransaction());
                $this->db->audit('operation', (int)$row['id'], 'needs_reconcile', 'worker');
            });
            $this->fail('Expired operation must block');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('afstemmes', $e->getMessage());
        }
        $this->assertSame('needs_reconcile', $this->db->row('SELECT status FROM stripe_invoice_operations')['status']);
        $this->assertSame(1, (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_invoice_audit')['n']);
        $this->assertFalse($this->db->inTransaction());
    }

    public function testDispatchRefusesUncommittedPreparation(): void
    {
        $operations = new InvoicePaymentOperations($this->db, 'tenant-a');
        $this->db->pdo->beginTransaction();
        try {
            $op = $operations->prepare('intent_create', '1', '/v1/payment_intents', []);
            try {
                $operations->dispatch((int)$op['id'], static function () { throw new LogicException('Must not dispatch'); }, static function () {});
                $this->fail('Dispatch must require a committed operation');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('uden åben transaktion', $e->getMessage());
            }
        } finally { $this->db->pdo->rollBack(); }
        $this->assertSame(0, (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_invoice_operations')['n']);
    }
    /** @return list<array{int,bool,bool}> HTTP status, error object present, definitive rejection. */
    public static function stripeRejections(): array
    {
        return [[400, true, true], [401, true, true], [402, true, true], [403, true, true], [404, true, true],
            [409, true, false], [429, true, false], [500, true, false], [0, false, false], [400, false, false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stripeRejections')]
    public function testDefinitiveErrorsAllowFreshKeyAfterCutoffWhileAmbiguousErrorsBlock(int $status, bool $errorObject, bool $rejected): void
    {
        require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentService.php';
        $operations = new InvoicePaymentOperations($this->db, 'tenant-a');
        $op = $operations->prepare('intent_create', '1', '/v1/payment_intents', ['amount' => 12345], 10, 12345, 2, 1);
        try {
            $operations->dispatch((int)$op['id'], static function () use ($status, $errorObject) {
                throw new \Saldi\Stripe\InvoicePaymentApiError('Stripe rejected or timed out', $status === 0 ? 'network' : 'invalid_request_error', $errorObject ? ['error' => ['type' => 'invalid_request_error']] : [], $status);
            }, static function () {});
            $this->fail('Expected Stripe failure');
        } catch (\Saldi\Stripe\InvoicePaymentApiError $e) {
            $this->assertSame($status, $e->status);
        }
        $stored = $this->db->row('SELECT * FROM stripe_invoice_operations WHERE id=?', [$op['id']]);
        $this->assertSame($rejected ? 'rejected' : 'dispatched', $stored['status']);
        $this->now += InvoicePaymentOperations::REPLAY_SECONDS + 1;
        $calls = [];
        $alerts = 0;
        try {
            $response = $operations->dispatch((int)$op['id'], function ($method, $path, $params, $key) use (&$calls) {
                $this->assertFalse($this->db->inTransaction());
                $calls[] = [$params, $key];
                return ['id' => 'pi_repaired'];
            }, function () use (&$alerts) { $alerts++; });
            $this->assertTrue($rejected);
            $this->assertSame('pi_repaired', $response['id']);
        } catch (RuntimeException $e) {
            $this->assertFalse($rejected);
            $this->assertStringContainsString('afstemmes', $e->getMessage());
        }
        if ($rejected) {
            $this->assertSame(0, $alerts);
            $this->assertCount(1, $calls);
            $this->assertSame(['amount' => 12345], $calls[0][0]);
            $this->assertNotSame($op['idempotency_key'], $calls[0][1]);
            $original = $this->db->row('SELECT * FROM stripe_invoice_operations WHERE id=?', [$op['id']]);
            $this->assertSame($stored, $original, 'Original rejection and immutable key remain auditable');
            $fresh = $operations->prepare('intent_create', '1', '/v1/payment_intents', ['amount' => 999]);
            $this->assertSame('succeeded', $fresh['status']);
            $this->assertSame($this->now, (int)$fresh['first_dispatch_at']);
        } else {
            $this->assertSame(1, $alerts);
            $this->assertSame([], $calls);
            $this->assertSame('needs_reconcile', $this->db->row('SELECT status FROM stripe_invoice_operations WHERE id=?', [$op['id']])['status']);
        }
    }

    /** @return list<array{int}> Definitive rejection statuses after an earlier unknown outcome. */
    public static function laterRejections(): array
    {
        return [[400], [401], [402], [403], [404]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('laterRejections')]
    public function testLaterRejectionCannotEraseAmbiguityOrAllowFreshPost(int $status): void
    {
        require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentService.php';
        $operations = new InvoicePaymentOperations($this->db, 'tenant-a');
        $op = $operations->prepare('intent_create', '1', '/v1/payment_intents', ['amount' => 12345], 10, 12345);
        $keys = [];
        $stripeObjects = [];
        try {
            $operations->dispatch((int)$op['id'], function ($method, $path, $params, $key) use (&$keys, &$stripeObjects) {
                $keys[] = $key;
                $stripeObjects[$key] = ['id' => 'pi_original', 'status' => 'succeeded'];
                $this->assertSame(1, (int)$this->db->row('SELECT ambiguous_dispatch FROM stripe_invoice_operations WHERE idempotency_key=?', [$key])['ambiguous_dispatch']);
                throw new RuntimeException('Timeout after success');
            }, static function () {});
            $this->fail('Expected lost success response');
        } catch (RuntimeException $e) {
            $this->assertSame('Timeout after success', $e->getMessage());
        }
        $this->now += 600;
        $alerts = 0;
        // Reconstruct the adapter: ambiguity must survive a new request/process.
        $operations = new InvoicePaymentOperations($this->db, 'tenant-a');
        try {
            $operations->dispatch((int)$op['id'], function ($method, $path, $params, $key) use (&$keys, $status) {
                $keys[] = $key;
                throw new \Saldi\Stripe\InvoicePaymentApiError('Rejected retry', 'invalid_request_error', ['error' => ['type' => 'invalid_request_error']], $status);
            }, function () use (&$alerts) {
                $this->assertTrue($this->db->inTransaction());
                $alerts++;
            });
            $this->fail('Expected retry rejection');
        } catch (\Saldi\Stripe\InvoicePaymentApiError $e) {
            $this->assertSame($status, $e->status);
        }
        $this->assertSame([$op['idempotency_key'], $op['idempotency_key']], $keys);
        $this->assertSame(1, $alerts);
        $stored = $operations->resume((int)$op['id']);
        $this->assertSame('needs_reconcile', $stored['status']);
        $this->assertSame(1, (int)$stored['ambiguous_dispatch']);
        $this->assertSame($op['idempotency_key'], $operations->prepare('intent_create', '1', '/v1/payment_intents', ['amount' => 999])['idempotency_key']);
        foreach ([0, InvoicePaymentOperations::REPLAY_SECONDS] as $advance) {
            $this->now += $advance;
            try {
                $operations->dispatch((int)$op['id'], static function () { throw new LogicException('No POST after repair'); }, static function () {});
                $this->fail('An ambiguous rejected retry must stay blocked');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('afstemmes', $e->getMessage());
            }
        }
        $this->assertCount(1, $stripeObjects);
        $this->assertSame(1, (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_invoice_operations')['n']);
    }

    public function testInterruptedDispatchRetainsAmbiguityOnNextRejection(): void
    {
        require_once __DIR__ . '/../../includes/stripeIncludes/InvoicePaymentService.php';
        $operations = new InvoicePaymentOperations($this->db, 'tenant-a');
        $op = $operations->prepare('intent_create', '1', '/v1/payment_intents', ['amount' => 12345]);
        // 20261012 CL/LH State committed before the POST, with no catch handler after a process crash.
        $this->db->query("UPDATE stripe_invoice_operations SET status='dispatched',first_dispatch_at=?,ambiguous_dispatch=1 WHERE id=?", [$this->now, $op['id']]);
        try {
            $operations->dispatch((int)$op['id'], static function () {
                throw new \Saldi\Stripe\InvoicePaymentApiError('Credentials changed', 'api_key_invalid', ['error' => ['type' => 'authentication_error']], 401);
            }, static function () {});
            $this->fail('Expected authentication failure');
        } catch (\Saldi\Stripe\InvoicePaymentApiError $e) {
            $this->assertSame('needs_reconcile', $operations->resume((int)$op['id'])['status']);
        }
    }

}
