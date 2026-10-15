<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiPurchaseToolsTest.test.php --- ai/mcp-assistant --- 2026.10.05
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
// Pool and line matching, signed sales velocity, open purchases and colli rounding.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Add regression coverage for companion review corrections.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiPurchaseTestCase.php';

final class SaldiPurchaseToolsTest extends SaldiPurchaseTestCase
{
    public function testExplicitSupplierReferenceAndLinesCompareReceivedRemainingAndPrices(): void
    {
        $this->receipt($this->line, '2.125');
        $this->order(['ordrenr' => 90, 'kontonr' => '3000']);
        $other = $this->order(['ordrenr' => 52, 'kundeordnr' => 'OTHER']);
        $before = $this->snapshot();
        $result = SaldiPurchaseTools::match($this->env->ctx(), $this->env->db, [
            'supplier_account' => '2000', 'order_number' => 'REF-51', 'lines' => [
                ['item_no' => 'sup-p1', 'quantity' => '8.000', 'unit_price' => '11.005'],
                ['description' => '  ANDEN   VARE ', 'quantity' => '8.000', 'unit_price' => '2.005'],
            ],
        ]);
        $this->validateOutput('match_purchase_order', $result);
        self::assertCount(2, $result['data']['orders']);
        $order = $result['data']['orders'][0];
        self::assertSame($this->order, $order['order_id']);
        self::assertSame($other, $result['data']['orders'][1]['order_id']);
        self::assertSame('10.000', $order['lines'][0]['ordered']);
        self::assertSame('2.125', $order['lines'][0]['received']);
        self::assertSame('7.875', $order['lines'][0]['remaining']);
        self::assertSame('0.125', $order['lines'][0]['quantity_difference']);
        self::assertSame('10.13', $order['lines'][0]['order_price']);
        self::assertSame('11.01', $order['lines'][0]['invoice_price']);
        self::assertSame(['quantity', 'unit_price'], array_column($order['differences'], 'field'));
        self::assertSame('0.88', $order['differences'][1]['difference']);
        self::assertSame('saldi:order:' . $this->order, $result['citations'][0]['id']);
        self::assertSame($before, $this->snapshot());
    }

    private function pool(array $values = []): int
    {
        $db = $this->env->db;
        $db->exec('ALTER TABLE pool_files ADD COLUMN IF NOT EXISTS vendor_konto_id integer');
        $db->exec('ALTER TABLE pool_files ADD COLUMN IF NOT EXISTS norm_amount numeric(15,3)');
        return $db->insert('pool_files', $values + ['filename' => 'supplier.pdf',
            'vendor_konto_id' => $this->supplier, 'norm_amount' => '125.000',
            'currency' => 'DKK', 'invoice_number' => 'INV-51', 'description' => 'Ordre REF-51',
            'file_date' => '2026-06-10', 'updated' => '2026-06-10 10:00:00']);
    }

    public function testPoolDocumentMatchesSupplierReferencesAndTotalsWithoutLines(): void
    {
        $id = $this->pool();
        $this->order(['ordrenr' => 90, 'kontonr' => '3000']);
        $this->order(['ordrenr' => 52, 'kundeordnr' => 'REF-52', 'sum' => '200.000']);
        $before = $this->snapshot();
        $result = SaldiPurchaseTools::match($this->env->ctx(), $this->env->db, ['document_ref' => 'pool:' . $id]);
        $this->validateOutput('match_purchase_order', $result);
        self::assertSame($this->order, $result['data']['orders'][0]['order_id']);
        self::assertSame([], $result['data']['orders'][0]['differences']);
        self::assertSame('total', $result['data']['orders'][1]['differences'][0]['field']);
        self::assertSame('-100.00', $result['data']['orders'][1]['differences'][0]['difference']);
        self::assertSame('saldi:pool_file:' . $id, $result['citations'][0]['id']);
        self::assertSame($before, $this->snapshot());
    }

    public function testUnmatchedAndDuplicateInvoiceLinesAreReported(): void
    {
        $result = SaldiPurchaseTools::match($this->env->ctx(), $this->env->db, [
            'lines' => [['item_no' => 'P1', 'quantity' => '10'],
                ['item_no' => 'P1', 'quantity' => '2'],
                ['item_no' => 'UNKNOWN', 'quantity' => '3', 'unit_price' => '1']],
        ]);
        $diffs = $result['data']['orders'][0]['differences'];
        self::assertCount(3, $diffs);
        self::assertSame([null, null, null], array_column($diffs, 'line_id'));
        $this->validateOutput('match_purchase_order', $result);
    }

    public function testMatchesOnlyOpenKoAndChecksPermissionsAndDocumentSupplier(): void
    {
        foreach ([['KO', 0], ['KO', 3], ['KK', 1], ['DO', 1]] as [$art, $status]) {
            $this->order(['art' => $art, 'status' => $status]);
        }
        self::assertCount(1, SaldiPurchaseTools::match($this->env->ctx(), $this->env->db, [])['data']['orders']);
        $this->error('PERMISSION_DENIED', fn() => SaldiPurchaseTools::match(
            $this->env->ctx(910001, '0000000001000000'),
            $this->env->db,
            []
        ));
        $pool = $this->pool();
        $this->error('VALIDATION_ERROR', fn() => SaldiPurchaseTools::match($this->env->ctx(), $this->env->db, [
            'document_ref' => 'pool:' . $pool, 'supplier_account' => '3000',
        ]));
        $this->error('NOT_FOUND', fn() => SaldiPurchaseTools::match($this->env->ctx(), $this->env->db, [
            'document_ref' => 'pool:999999',
        ]));
        $this->error('VALIDATION_ERROR', fn() => SaldiPurchaseTools::match($this->env->ctx(), $this->env->db, [
            'lines' => [['quantity' => '-1']],
        ]));
    }

    public function testVelocityIncludesSignedCreditsPosAndPostedOnlyWithinWindow(): void
    {
        $today = SaldiToolRuntime::today();
        $start = (new DateTimeImmutable($today))->modify('-29 days')->format('Y-m-d');
        $old = (new DateTimeImmutable($today))->modify('-30 days')->format('Y-m-d');
        $future = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
        $this->sale('30.000', [], $start);
        $this->sale('-6.000', ['art' => 'DK']);
        $this->sale('6.000', ['art' => 'PO']);
        $this->sale('1000.000', ['status' => 2]);
        $this->sale('1000.000', [], $old);
        $this->sale('1000.000', [], $future);
        $this->env->db->insert('batch_salg', ['ordre_id' => 0, 'vare_id' => $this->product,
            'salgsdate' => $today, 'antal' => '1000.000']);
        $this->receipt($this->line, '4.000');
        $closedOrder = $this->order(['status' => 3]);
        $this->line(['ordre_id' => $closedOrder, 'antal' => '500.000']);
        $draft = $this->order(['status' => 0]);
        $this->line(['ordre_id' => $draft, 'antal' => '500.000']);
        $before = $this->snapshot();
        $result = SaldiPurchaseTools::reorders($this->env->ctx(), $this->env->db, ['days' => 30]);
        $this->validateOutput('suggest_reorders', $result);
        $row = $result['data']['rows'][0];
        self::assertSame('1.000', $row['per_day']);
        self::assertSame('14.000', $row['on_order']);
        self::assertSame('16.000', $row['cover_days']);
        self::assertSame('30.000', $row['suggest_qty']);
        self::assertSame(['account' => '2000', 'name' => 'Testleverandør'], $row['supplier']);
        self::assertSame($before, $this->snapshot());
    }

    public function testColliUsesUnroundedVelocityAndGlobalLeadTimeSetting(): void
    {
        $db = $this->env->db;
        $db->exec('DELETE FROM ordrelinjer');
        $db->exec('UPDATE varer SET beholdning = 0, min_lager = 0, colli = 1 WHERE id = ?', [$this->product]);
        $this->sale('1.000');
        $result = SaldiPurchaseTools::reorders($this->env->ctx(), $db, ['days' => 90, 'lead_days' => 0]);
        self::assertSame('0.011', $result['data']['rows'][0]['per_day']);
        self::assertSame('1.000', $result['data']['rows'][0]['suggest_qty']);
        $db->insert('settings', ['var_grp' => 'assistant', 'var_name' => 'lead_days',
            'var_value' => '100', 'user_id' => 0, 'pos_id' => 0]);
        $result = SaldiPurchaseTools::reorders($this->env->ctx(), $db, ['days' => 30]);
        self::assertSame('5.000', $result['data']['rows'][0]['suggest_qty']);
    }

    public function testInactiveNonstockAndAdequatelyCoveredItemsAreExcluded(): void
    {
        $db = $this->env->db;
        $this->product(['varenr' => 'CLOSED', 'lukket' => 'on']);
        $this->product(['varenr' => 'NONSTOCK', 'gruppe' => 2]);
        $this->product(['varenr' => 'ENOUGH', 'beholdning' => '1000.000']);
        $db->exec('DELETE FROM ordrelinjer');
        $result = SaldiPurchaseTools::reorders($this->env->ctx(), $db, []);
        self::assertSame(1, $result['data']['total']);
        self::assertSame($this->product, $result['data']['rows'][0]['product_id']);
        self::assertNull($result['data']['rows'][0]['cover_days']);
        self::assertSame('6.000', $result['data']['rows'][0]['suggest_qty']);
        $this->validateOutput('suggest_reorders', $result);
    }

    public function testNoSalesNetReturnsAndOpenPurchasesNeverProduceNegativeSuggestions(): void
    {
        $this->sale('-90.000', ['art' => 'DK']);
        $result = SaldiPurchaseTools::reorders($this->env->ctx(), $this->env->db, []);
        self::assertSame([], $result['data']['rows']);
        self::assertSame(0, $result['data']['total']);
        $this->validateOutput('suggest_reorders', SaldiPurchaseTools::reorders(
            $this->env->ctx(),
            $this->env->db,
            []
        ));
    }

    public function testReorderValidationAndPermissions(): void
    {
        foreach ([['days' => 29], ['days' => 366], ['days' => '90'], ['lead_days' => -1]] as $args) {
            $this->error('VALIDATION_ERROR', fn() => SaldiPurchaseTools::reorders(
                $this->env->ctx(),
                $this->env->db,
                $args
            ));
        }
        $this->error('PERMISSION_DENIED', fn() => SaldiPurchaseTools::reorders(
            $this->env->ctx(910001, '0000000100000000'),
            $this->env->db,
            []
        ));
    }

    public function testOldDocumentReferencePrecedesBoundedRecentCandidates(): void
    {
        $pool = $this->pool();
        for ($i = 0; $i < 105; $i++) {
            $this->order(['ordrenr' => 100 + $i, 'kundeordnr' => 'NEW-' . $i]);
        }
        $result = SaldiPurchaseTools::match($this->env->ctx(), $this->env->db, [
            'document_ref' => 'pool:' . $pool,
        ]);
        self::assertSame($this->order, $result['data']['orders'][0]['order_id']);
        self::assertTrue($result['possibly_incomplete']);
        self::assertCount(25, $result['data']['orders']);
    }

    public function testLargeMatchOutputIsBoundedAndKeepsCitationsForReturnedOrders(): void
    {
        $db = $this->env->db;
        $text = str_repeat('æ', 500);
        $db->exec('UPDATE ordrelinjer SET beskrivelse = ?', [$text]);
        for ($i = 0; $i < 15; $i++) {
            $order = $this->order(['ordrenr' => 100 + $i]);
            for ($j = 0; $j < 30; $j++) {
                $this->line(['ordre_id' => $order, 'beskrivelse' => $text]);
            }
        }
        $result = SaldiPurchaseTools::match($this->env->ctx(), $db, []);
        self::assertTrue($result['possibly_incomplete']);
        self::assertLessThan(70 * 1024, strlen(SaldiToolRuntime::canonical($result)));
        self::assertTrue($this->toolCall('match_purchase_order', [])['ok']);
        self::assertCount(count($result['data']['orders']), $result['citations']);
        $this->validateOutput('match_purchase_order', $result);
    }

    public function testMcpReadAndDraftSuccessAndErrorContractsAndDraftIdempotency(): void
    {
        foreach (['match_purchase_order', 'suggest_reorders'] as $tool) {
            $result = $this->toolCall($tool, []);
            self::assertTrue($result['ok']);
            self::assertFalse($result['committed']);
            self::assertSame('done', $result['receipt']['state']);
        }
        $before = $this->snapshot();
        $args = $this->stage(['lines' => [['line_id' => $this->line,
            'receive_quantity' => '4.125', 'unit_price' => '11.01']]])
            + ['request_id' => 'invoice-mcp-test', 'explanation' => 'Syntetisk faktura'];
        $draft = $this->toolCall('propose_purchase_invoice_stage', $args);
        self::assertTrue($draft['ok'], json_encode($draft));
        self::assertSame('awaiting_approval', $draft['receipt']['state']);
        self::assertSame($before, $this->snapshot());
        $again = $this->toolCall('propose_purchase_invoice_stage', $args);
        self::assertSame($draft['data']['id'], $again['data']['id']);
        self::assertTrue($again['data']['deduplicated']);
        $applied = $this->approve($draft['data']);
        self::assertSame('applied', $this->toolCall('propose_purchase_invoice_stage', $args)['receipt']['state']);
        self::assertSame('10.125', $applied['result']['before']['lines'][0]['pris']);
        foreach (
            [['suggest_reorders', ['days' => 29]],
            ['match_purchase_order', ['document_ref' => 'pool:999999']],
            ['propose_purchase_invoice_stage', $this->stage(['order_id' => 999999])
                + ['request_id' => 'missing-order', 'explanation' => 'Syntetisk faktura']]] as [$name, $invalid]
        ) {
            $error = $this->toolCall($name, $invalid);
            self::assertFalse($error['ok']);
            self::assertArrayHasKey('code', $error['error']);
        }
    }

    public function testSingleLargeOrderKeepsItsHeaderAndMarksTruncatedLines(): void
    {
        $db = $this->env->db;
        $text = str_repeat('æ', 500);
        for ($i = 0; $i < 100; $i++) {
            $this->line(['beskrivelse' => $text]);
        }
        $result = $this->toolCall('match_purchase_order', []);
        self::assertTrue($result['ok']);
        self::assertTrue($result['possibly_incomplete']);
        self::assertSame($this->order, $result['data']['orders'][0]['order_id']);
        self::assertGreaterThan(0, count($result['data']['orders'][0]['lines']));
        self::assertLessThan(102, count($result['data']['orders'][0]['lines']));
    }

    public function testForeignCurrencyDocumentDoesNotCompareUnconvertedPricesOrTotals(): void
    {
        $pool = $this->pool(['currency' => 'EUR']);
        $result = SaldiPurchaseTools::match($this->env->ctx(), $this->env->db, [
            'document_ref' => 'pool:' . $pool, 'lines' => [
                ['item_no' => 'P1', 'quantity' => '8', 'unit_price' => '1.35'],
            ],
        ]);
        self::assertTrue($result['possibly_incomplete']);
        self::assertNull($result['data']['orders'][0]['lines'][0]['invoice_price']);
        self::assertSame('-2.000', $result['data']['orders'][0]['lines'][0]['quantity_difference']);
        self::assertSame(['quantity'], array_column($result['data']['orders'][0]['differences'], 'field'));
        $this->validateOutput('match_purchase_order', $result);
        $totals = SaldiPurchaseTools::match($this->env->ctx(), $this->env->db, [
            'document_ref' => 'pool:' . $pool,
        ]);
        self::assertTrue($totals['possibly_incomplete']);
        self::assertSame([], $totals['data']['orders'][0]['differences']);
    }
    public function testZeroDemandDeadStockCannotCrowdOutPositiveReorders(): void
    {
        $this->env->db->exec('DELETE FROM ordrelinjer');
        for ($i = 0; $i < 25; $i++) {
            $this->product(['varenr' => 'DEAD-' . $i, 'beholdning' => '0', 'min_lager' => '0']);
        }
        $result = SaldiPurchaseTools::reorders($this->env->ctx(), $this->env->db, []);
        self::assertSame(1, $result['data']['total']);
        self::assertSame($this->product, $result['data']['rows'][0]['product_id']);
        self::assertSame('6.000', $result['data']['rows'][0]['suggest_qty']);
        $this->validateOutput('suggest_reorders', $result);
    }

}
