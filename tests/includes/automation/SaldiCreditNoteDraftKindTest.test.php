<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiCreditNoteDraftKindTest.test.php --- ai/mcp-assistant --- 2026.10.05
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
// Credit-note sign, source linkage, one-per-invoice and undo regressions.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Add regression coverage for companion review corrections.
// 20261005 CDX/LH Check Danish credit quantities on proposed and applied cards.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiSalesTestCase.php';

final class SaldiCreditNoteDraftKindTest extends SaldiSalesTestCase
{
    public function testFullCreditCopiesHeaderPricesDiscountsAndTextWithoutPosting(): void
    {
        $source = $this->invoice();
        $db = $this->env->db;
        $item = $this->inbox()->create(
            $this->env->ctx(),
            'credit_note.draft',
            ['invoice_order_id' => $source['id'], 'reason' => 'Returneret ÆØÅ']
        );
        $a = $this->approve($item);
        self::assertSame('-2,5', $item['card']['rows'][0][2]);
        self::assertSame('-2,5', $a['card']['rows'][0][2]);
        $header = $db->one('SELECT * FROM ordrer WHERE id=?', [$a['result']['order_id']]);
        $rows = $this->inserted($a);
        self::assertSame('DK', $header['art']);
        self::assertSame(0, (int)$header['status']);
        self::assertSame($source['id'], (int)$header['kred_ord_id']);
        self::assertNull($header['fakturanr']);
        self::assertNull($header['fakturadate']);
        self::assertNull($header['betalt']);
        self::assertSame('Test kunde ÆØÅ', $header['firmanavn']);
        self::assertSame('-2.500', $rows[0]['antal']);
        self::assertSame($source['line_id'], (int)$rows[0]['kred_linje_id']);
        self::assertSame('125.005', $rows[0]['pris']);
        self::assertSame('12.50000', $rows[0]['rabat']);
        self::assertSame('25.000', $rows[0]['momssats']);
        self::assertSame('156.256', $rows[0]['vat_price']);
        self::assertNull($rows[0]['leveret']);
        self::assertSame('0.000', $rows[0]['leveres']);
        self::assertSame('Tekstlinje', $rows[1]['beskrivelse']);
        self::assertNull($rows[1]['antal']);
        self::assertTrue($a['can_undo']);
        $existing = $this->error('CONFLICT', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'credit_note.draft',
            ['invoice_order_id' => $source['id'], 'reason' => 'Igen']
        ));
        self::assertSame($a['result']['order_id'], $existing->detail['order_id']);
        self::assertStringContainsString((string)$a['result']['order_number'], $existing->getMessage());
        self::assertSame('undone', $this->inbox()->undo(
            $this->env->ctx(),
            $a['id'],
            $this->env->ctx()->tenantHash()
        )['status']);
        self::assertSame(3, (int)$db->value('SELECT status FROM ordrer WHERE id=?', [$source['id']]));
        self::assertSame('2.500', $db->value('SELECT antal FROM ordrelinjer WHERE id=?', [$source['line_id']]));
    }

    public function testPartialCreditAndConcurrentOnePerInvoice(): void
    {
        $source = $this->invoice();
        $input = ['invoice_order_id' => $source['id'], 'reason' => 'Delvis returneret',
            'lines' => [['line_id' => $source['line_id'], 'quantity' => '1.125']]];
        $results = $this->workers('credit_note.draft', $input);
        $successful = array_values(array_filter($results, fn(array $r): bool => isset($r['result'])));
        $failed = array_values(array_filter($results, fn(array $r): bool => isset($r['error'])));
        self::assertCount(1, $successful);
        self::assertCount(1, $failed);
        self::assertSame('CONFLICT', $failed[0]['error']);
        self::assertSame('-1.125', $this->env->db->value(
            'SELECT antal FROM ordrelinjer WHERE ordre_id=?',
            [$successful[0]['result']['order_id']]
        ));
        self::assertSame(1, (int)$this->env->db->value("SELECT COUNT(*) FROM ordrer WHERE art='DK'"));
    }

    public static function invalidCredits(): array
    {
        return ['too many' => ['excess'], 'foreign line' => ['foreign'], 'duplicate line' => ['duplicate'],
            'zero quantity' => ['zero'], 'not invoiced' => ['unposted'],
            'wrong art' => ['purchase'], 'nonpositive source' => ['nonpositive']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCredits')]
    public function testCreditEligibility(string $case): void
    {
        $source = $this->invoice();
        $input = ['invoice_order_id' => $source['id'], 'reason' => 'Test',
            'lines' => [['line_id' => $source['line_id'], 'quantity' => '1']]];
        if ($case === 'excess') {
            $input['lines'][0]['quantity'] = '2.501';
        } elseif ($case === 'foreign') {
            $input['lines'][0]['line_id'] = 99999999;
        } elseif ($case === 'duplicate') {
            $input['lines'][] = $input['lines'][0];
        } elseif ($case === 'zero') {
            $input['lines'][0]['quantity'] = '0';
        } elseif ($case === 'nonpositive') {
            unset($input['lines']);
            $this->env->db->exec('UPDATE ordrelinjer SET antal=-1 WHERE id=?', [$source['line_id']]);
        } else {
            $this->env->db->exec(
                'UPDATE ordrer SET status=?,art=? WHERE id=?',
                [$case === 'unposted' ? 1 : 3, $case === 'purchase' ? 'KO' : 'DO', $source['id']]
            );
        }
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'credit_note.draft',
            $input
        ));
        self::assertSame(0, (int)$this->env->db->value("SELECT COUNT(*) FROM ordrer WHERE art='DK'"));
    }

    public static function creditVat(): array
    {
        return ['free' => ['on', '', '25.000', '0.000'],
            'reverse' => ['', 'on', '25.000', '0.000'],
            'capped' => ['', '', '30.000', '25.000'],
            'header fallback' => ['', '', '0.000', '25.000']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('creditVat')]
    public function testSourceVatRules(string $free, string $reverse, string $rate, string $expected): void
    {
        $source = $this->invoice();
        $this->env->db->exec(
            'UPDATE ordrelinjer SET momsfri=?,omvbet=?,momssats=? WHERE id=?',
            [$free, $reverse, $rate, $source['line_id']]
        );
        $a = $this->approve($this->inbox()->create(
            $this->env->ctx(),
            'credit_note.draft',
            ['invoice_order_id' => $source['id'], 'reason' => 'Test']
        ));
        self::assertSame($expected, $this->inserted($a)[0]['momssats']);
    }

    public function testChangedSourceBlocksApprovalAndChangedCreditBlocksUndo(): void
    {
        $source = $this->invoice();
        $input = ['invoice_order_id' => $source['id'], 'reason' => 'Test'];
        $item = $this->inbox()->create($this->env->ctx(), 'credit_note.draft', $input);
        $this->env->db->exec('UPDATE ordrelinjer SET rabat=1 WHERE id=?', [$source['line_id']]);
        $this->error('STALE_CONTEXT', fn() => $this->approve($item));
        $a = $this->approve($this->inbox()->create($this->env->ctx(), 'credit_note.draft', $input));
        $this->env->db->exec('UPDATE ordrer SET status=3 WHERE id=?', [$a['result']['order_id']]);
        self::assertFalse($this->inbox()->get($this->env->ctx(), $a['id'])['can_undo']);
        $this->error('CONFLICT', fn() => $this->inbox()->undo(
            $this->env->ctx(),
            $a['id'],
            $this->env->ctx()->tenantHash()
        ));
    }
    public function testPricedServiceLinesAreCreditedWithVatAndAccounts(): void
    {
        $source = $this->invoice();
        $db = $this->env->db;
        $service = $db->insert('ordrelinjer', ['ordre_id' => $source['id'], 'posnr' => 3, 'vare_id' => 0,
            'beskrivelse' => 'Arbejde', 'antal' => '2', 'pris' => '50', 'rabat' => '10', 'bogf_konto' => 2000,
            'momssats' => '25', 'vat_account' => '6900']);
        $item = $this->inbox()->create($this->env->ctx(), 'credit_note.draft', ['invoice_order_id' => $source['id'], 'reason' => 'Tilbagefør arbejde']);
        self::assertSame('-2', $item['card']['rows'][2][2]);
        $applied = $this->approve($item);
        $row = $this->inserted($applied)[2];
        self::assertSame('-2.000', $row['antal']);
        self::assertSame('50.000', $row['pris']);
        self::assertSame(0, (int)$row['vare_id']);
        self::assertSame($service, (int)$row['kred_linje_id']);
        self::assertSame(2000, (int)$row['bogf_konto']);
        self::assertSame('25.000', $row['momssats']);
        $header = $db->one('SELECT sum,moms FROM ordrer WHERE id=?', [$applied['result']['order_id']]);
        self::assertSame('-363.450', $header['sum']);
        self::assertSame('-90.863', $header['moms']);
        self::assertTrue($applied['can_undo']);
    }

    public function testRevenueRestrictionBlocksNewAndExistingCreditCards(): void
    {
        $source = $this->invoice();
        $input = ['invoice_order_id' => $source['id'], 'reason' => 'Test'];
        $item = $this->inbox()->create($this->env->ctx(), 'credit_note.draft', $input);
        $db = $this->env->db;
        $db->insert('settings', ['var_grp' => 'finans', 'var_name' => 'hideRevenue', 'var_value' => 'on']);
        $hidden = $this->env->ctx(910001, '1011111111111111');
        $this->error('PERMISSION_DENIED', fn() => $this->inbox()->create($hidden, 'credit_note.draft', $input));
        $this->error('NOT_FOUND', fn() => $this->inbox()->get($hidden, $item['id']));
        self::assertSame([], $this->inbox()->list($hidden, ['kind' => 'credit_note.draft']));
        self::assertSame(0, $this->inbox()->countOpen($hidden));
        $this->error('PERMISSION_DENIED', fn() => (new SaldiCreditNoteDraftKind())->render($hidden, $db, $item));
    }

    public function testOpeningSourceAndCreditLeavesRevisionAndUndoIntact(): void
    {
        $source = $this->invoice();
        $db = $this->env->db;
        $item = $this->inbox()->create($this->env->ctx(), 'credit_note.draft', ['invoice_order_id' => $source['id'], 'reason' => 'Test']);
        $db->exec('UPDATE ordrer SET hvem=?,tidspkt=? WHERE id=?', [$this->env->ctx()->username, (string)time(), $source['id']]);
        $applied = $this->approve($item);
        $db->exec('UPDATE ordrer SET hvem=?,tidspkt=? WHERE id=?', [$this->env->ctx()->username, (string)time(), $applied['result']['order_id']]);
        self::assertTrue($this->inbox()->get($this->env->ctx(), $applied['id'])['can_undo']);
    }

    public function testMixedVatServiceCreditTotalsRemainUndoableAfterDisplay(): void
    {
        $source = $this->invoice();
        $db = $this->env->db;
        $db->insert('ordrelinjer', ['ordre_id' => $source['id'], 'posnr' => 3, 'vare_id' => 0,
            'beskrivelse' => 'Arbejde', 'antal' => '2', 'pris' => '50', 'rabat' => '10',
            'bogf_konto' => 2000, 'momssats' => '10']);
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'credit_note.draft',
            ['invoice_order_id' => $source['id'], 'reason' => 'Blandet moms']));
        $id = $item['result']['order_id'];
        $header = $db->one('SELECT sum,moms,kostpris FROM ordrer WHERE id=?', [$id]);
        self::assertSame('-363.450', $header['sum']);
        self::assertSame('-77.360', $header['moms']);
        $db->exec('UPDATE ordrer SET sum=-363.45,moms=-77.36,kostpris=-50 WHERE id=?', [$id]);
        self::assertTrue($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
    }

    public function testSavedQuantityDiscountContributesToCreditHeaderTotals(): void
    {
        $source = $this->invoice();
        $db = $this->env->db;
        $db->exec('UPDATE ordrelinjer SET rabat=0,m_rabat=5,rabatgruppe=0 WHERE id=?', [$source['line_id']]);
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'credit_note.draft',
            ['invoice_order_id' => $source['id'], 'reason' => 'Mængderabat']));
        $id = $item['result']['order_id'];
        $header = $db->one('SELECT sum,moms,kostpris FROM ordrer WHERE id=?', [$id]);
        self::assertSame('-300.010', $header['sum']);
        self::assertSame('-75.003', $header['moms']);
        $db->exec('UPDATE ordrer SET sum=-300.01,moms=-75.0025,kostpris=-50 WHERE id=?', [$id]);
        self::assertTrue($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
    }

}
