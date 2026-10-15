<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiSalesOrderDraftKindTest.test.php --- ai/mcp-assistant --- 2026.10.05
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
// Debtor draft price, discount, VAT, allocation and undo regressions.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Add regression coverage for companion review corrections.
// 20261005 CDX/LH Cover Danish quantities on proposed and applied sales cards.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiSalesTestCase.php';

final class SaldiSalesOrderDraftKindTest extends SaldiSalesTestCase
{
    public function testQuantityIsFormattedOnBothProposalAndAppliedCards(): void
    {
        $item = $this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales(['quantity' => '2.500']));
        self::assertSame('2,5', $item['card']['rows'][0][2]);
        self::assertSame('2,5', $this->approve($item)['card']['rows'][0][2]);
    }

    public static function prices(): array
    {
        return [
            'given wins' => [['unit_price' => '45.555'], true, true, '45.555'],
            'given zero retained' => [['unit_price' => '0.000'], true, true, '0.000'],
            'tier beats campaign' => [[], true, true, '80.000'],
            'campaign in window' => [[], false, true, '60.000'],
            'ordinary outside window' => [[], false, false, '100.125'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('prices')]
    public function testPricePrecedence(array $line, bool $b2b, bool $window, string $expected): void
    {
        $db = $this->env->db;
        $db->exec("UPDATE grupper SET box8=? WHERE art='DG'", [$b2b ? 'on' : '']);
        if (!$window) {
            $db->exec("UPDATE varer SET special_to_date='2026-01-02' WHERE id=?", [$this->product]);
        }
        $item = $this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales($line));
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM ordrer'));
        $applied = $this->approve($item);
        self::assertSame($expected, $this->inserted($applied)[0]['pris']);
        self::assertTrue($applied['can_undo']);
    }

    public function testDiscountFallbackAndExplicitZero(): void
    {
        $db = $this->env->db;
        $db->insert('rabat', ['vare' => 1, 'debitor' => 1, 'rabat' => '7.125', 'rabatart' => 'pct']);
        $a = $this->approve($this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales()));
        self::assertSame('7.13000', $this->inserted($a)[0]['rabat']);
        self::assertSame('pct', $this->inserted($a)[0]['rabatart']);
        $b = $this->approve($this->inbox()->create(
            $this->env->ctx(),
            'sales_order.draft',
            $this->sales(['discount_pct' => '0'])
        ));
        self::assertSame('0.00000', $this->inserted($b)[0]['rabat']);
    }

    public function testNamedDiscountGroupsAndPercentCampaign(): void
    {
        $db = $this->env->db;
        $this->groupRow('DRG', 3);
        $this->groupRow('DVRG', 4);
        $db->exec('UPDATE adresser SET rabatgruppe=3 WHERE id=?', [$this->customer]);
        $db->exec('UPDATE varer SET dvrg=4 WHERE id=?', [$this->product]);
        $db->insert('rabat', ['vare' => 4, 'debitor' => 3, 'rabat' => '10.000', 'rabatart' => 'pct']);
        $a = $this->approve($this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales()));
        self::assertSame('10.00000', $this->inserted($a)[0]['rabat']);
        $db->exec(
            "UPDATE varer SET specialtype='percent', special_price='15.000' WHERE id=?",
            [$this->product]
        );
        $b = $this->approve($this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales()));
        self::assertSame('15.00000', $this->inserted($b)[0]['rabat']);
        self::assertSame('100.125', $this->inserted($b)[0]['pris']);
    }

    public static function vatCases(): array
    {
        return ['normal' => ['', '', '', '25.000'],
            'free' => ['on', '', '', '0.000'],
            'reverse' => ['', 'on', 'on', '0.000'],
            'customer only' => ['', 'on', '', '25.000']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('vatCases')]
    public function testVatAndReverseCharge(string $free, string $customer, string $product, string $vat): void
    {
        $db = $this->env->db;
        $db->exec("UPDATE grupper SET box7=?,box6=? WHERE art='VG'", [$free, $product]);
        $db->exec("UPDATE grupper SET box9=? WHERE art='DG'", [$customer]);
        $a = $this->approve($this->inbox()->create(
            $this->env->ctx(),
            'sales_order.draft',
            $this->sales(['unit_price' => '100.000'])
        ));
        $row = $this->inserted($a)[0];
        self::assertSame($vat, $row['momssats']);
        self::assertSame($vat === '0.000' ? '100.000' : '125.000', $row['vat_price']);
        self::assertSame($customer && $product ? 'on' : '', $row['omvbet']);
        self::assertSame(2000, (int)$row['bogf_konto']);
    }

    public function testHeaderTextLinesOrderStatusAndUndo(): void
    {
        $p = $this->sales([], 'order');
        $p['lines'][] = ['description' => 'Aftale ÆØÅ', 'quantity' => '1'];
        $p += ['reference' => 'Testref', 'note' => 'Ordrenote', 'delivery_date' => '2026-06-20'];
        $db = $this->env->db;
        $db->insert('kontakt_emails', ['konto_id' => $this->customer, 'email_type' => ' FAKTURA ',
            'email' => 'faktura@example.invalid']);
        $a = $this->approve($this->inbox()->create($this->env->ctx(), 'sales_order.draft', $p));
        $header = $db->one('SELECT * FROM ordrer WHERE id=?', [$a['result']['order_id']]);
        self::assertSame(1, (int)$header['status']);
        self::assertSame('DO', $header['art']);
        self::assertSame('Testref', $header['ref']);
        self::assertSame('Ordrenote', $header['notes']);
        self::assertSame('faktura@example.invalid', $header['email']);
        self::assertSame('123456789012345', $header['phone']);
        self::assertSame('Levering', $header['lev_navn']);
        self::assertSame('BS', $header['pbs']);
        self::assertNull($header['fakturanr']);
        $lines = $this->inserted($a);
        self::assertNull($lines[1]['vare_id']);
        self::assertNull($lines[1]['antal']);
        self::assertSame('Aftale ÆØÅ', $lines[1]['beskrivelse']);
        self::assertSame('0.000', $lines[0]['leveres']);
        self::assertNull($lines[0]['leveret']);
        self::assertSame('undone', $this->inbox()->undo(
            $this->env->ctx(),
            $a['id'],
            $this->env->ctx()->tenantHash()
        )['status']);
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM ordrer'));
    }

    public function testSourceChangesBlockApprovalAndRatesResolveAtApproval(): void
    {
        $db = $this->env->db;
        $item = $this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales());
        $db->exec('UPDATE varer SET salgspris=? WHERE id=?', ['101.000', $this->product]);
        $this->error('STALE_CONTEXT', fn() => $this->approve($item));
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM ordrer'));
        $this->groupRow('VK', 2, ['box1' => 'EUR']);
        $db->exec("UPDATE grupper SET box3='EUR' WHERE art='DG'");
        $rate = $db->insert('valuta', ['gruppe' => 2, 'kurs' => '750.000', 'valdate' => '2026-01-01']);
        $item = $this->inbox()->create(
            $this->env->ctx(),
            'sales_order.draft',
            $this->sales(['unit_price' => '100.000'])
        );
        $db->exec('UPDATE valuta SET kurs=? WHERE id=?', ['800.000', $rate]);
        $a = $this->approve($item);
        self::assertSame('800.000', $a['result']['currency_rate']);
        self::assertSame('13.333', $this->inserted($a)[0]['pris']);
    }

    public function testConcurrentDoDkNumbering(): void
    {
        $db = $this->env->db;
        $db->insert('ordrer', ['art' => 'DK', 'ordrenr' => 70]);
        $db->insert('ordrer', ['art' => 'KO', 'ordrenr' => 900]);
        $results = $this->workers('sales_order.draft', $this->sales());
        $numbers = array_map(fn(array $r): int => $r['result']['order_number'], $results);
        sort($numbers);
        self::assertSame([71, 72], $numbers);
    }

    public static function undoCases(): array
    {
        return ['status' => ['status'], 'header' => ['header'], 'line' => ['line'],
            'delivery' => ['delivery'], 'batch' => ['batch']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('undoCases')]
    public function testUndoGuards(string $case): void
    {
        $db = $this->env->db;
        $a = $this->approve($this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales()));
        $id = $a['result']['order_id'];
        if ($case === 'status') {
            $db->exec('UPDATE ordrer SET status=1 WHERE id=?', [$id]);
        } elseif ($case === 'header') {
            $db->exec("UPDATE ordrer SET notes='Ændret' WHERE id=?", [$id]);
        } elseif ($case === 'line') {
            $db->exec('UPDATE ordrelinjer SET pris=1 WHERE ordre_id=?', [$id]);
        } elseif ($case === 'delivery') {
            $db->exec('UPDATE ordrelinjer SET leveret=1 WHERE ordre_id=?', [$id]);
        } else {
            $db->insert('batch_salg', ['ordre_id' => $id, 'vare_id' => $this->product, 'antal' => '1']);
        }
        self::assertFalse($this->inbox()->get($this->env->ctx(), $a['id'])['can_undo']);
        $e = $this->error('CONFLICT', fn() => $this->inbox()->undo(
            $this->env->ctx(),
            $a['id'],
            $this->env->ctx()->tenantHash()
        ));
        self::assertSame('undo_changed', $e->detail['reason']);
        self::assertNotNull($db->value('SELECT id FROM ordrer WHERE id=?', [$id]));
    }
    public function testWeeklyOfferTierFallbackAndVatAccountPrecedence(): void
    {
        $db = $this->env->db;
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen'));
        $db->insert('varetilbud', ['vare_id' => $this->product, 'ugedag' => (int)$now->format('N'),
            'startdag' => (string)($now->getTimestamp() - 3600), 'slutdag' => (string)($now->getTimestamp() + 3600),
            'starttid' => '00:00:00', 'sluttid' => '23:59:59', 'salgspris' => '50.000', 'kostpris' => '9.000']);
        $db->exec("UPDATE grupper SET box8='on' WHERE art='DG'");
        $db->exec('UPDATE varer SET tier_price=0 WHERE id=?', [$this->product]);
        $this->groupRow('SM', 2, ['box1' => '6902', 'box2' => '10']);
        $db->exec("UPDATE kontoplan SET moms='S2' WHERE kontonr=2000");
        $a = $this->approve($this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales()));
        $row = $this->inserted($a)[0];
        self::assertSame('50.000', $row['pris']);
        self::assertSame('9.000', $row['kostpris']);
        self::assertSame('10.000', $row['momssats']);
        self::assertSame('6902', $row['vat_account']);
        self::assertSame('55.000', $row['vat_price']);
    }

    public function testApprovalDepartmentWarehouseAndMaximumDepartmentVat(): void
    {
        $db = $this->env->db;
        $employee = $db->insert('ansatte', ['navn' => 'Test ansat', 'afd' => 2]);
        $db->exec('UPDATE brugere SET ansat_id=? WHERE id=?', [$employee, 910001]);
        $this->groupRow('AFD', 2, ['box1' => '3']);
        $this->groupRow('SM', 2, ['box1' => '6902', 'box2' => '10']);
        $db->insert('settings', ['var_name' => 'maxDepVatRate_2', 'var_value' => 'on']);
        $a = $this->approve($this->inbox()->create(
            $this->env->ctx(),
            'sales_order.draft',
            $this->sales(['unit_price' => '100.000'])
        ));
        self::assertSame('Dansk', $db->value('SELECT sprog FROM ordrer WHERE id=?', [$a['result']['order_id']]));
        self::assertSame(2, $a['result']['department']);
        self::assertSame(3, $a['result']['warehouse']);
        self::assertSame('10.000', $this->inserted($a)[0]['momssats']);
        self::assertSame(3, (int)$this->inserted($a)[0]['lager']);
    }
    public function testExplicitZeroOverridesPercentageCampaign(): void
    {
        $db = $this->env->db;
        $db->exec("UPDATE varer SET specialtype='percent', special_price='15.000' WHERE id=?", [$this->product]);
        $item = $this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales(['discount_pct' => '0.000']));
        self::assertSame('0.000', $item['payload']['lines'][0]['pricing']['rabat']);
        $applied = $this->approve($item);
        self::assertSame('0.00000', $this->inserted($applied)[0]['rabat']);
    }

    public function testCampaignExpiryKeepsThePriceAndDiscountShownOnTheCard(): void
    {
        $db = $this->env->db;
        $end = (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->modify('+2 seconds');
        $db->exec('UPDATE varer SET special_to_date=?, special_to_time=? WHERE id=?', [$end->format('Y-m-d'), $end->format('H:i:s'), $this->product]);
        $item = $this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales(), ['request_id' => 'pinned-promotion']);
        self::assertSame('60.000', $item['payload']['lines'][0]['pricing']['pris']);
        self::assertSame('60.00', $item['card']['rows'][0][3]);
        sleep(3);
        $replayed = $this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales(), ['request_id' => 'pinned-promotion']);
        self::assertSame($item['id'], $replayed['id']);
        $applied = $this->approve($item);
        self::assertSame('60.000', $this->inserted($applied)[0]['pris']);
        self::assertSame($item['card']['rows'][0][3], $applied['card']['rows'][0][3]);
    }

    public function testStockChangesAndOpeningOrderDoNotInvalidateSalesDraft(): void
    {
        $db = $this->env->db;
        $item = $this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales());
        $db->exec('UPDATE varer SET beholdning=987 WHERE id=?', [$this->product]);
        $db->exec('UPDATE kontoplan SET saldo=1234 WHERE kontonr=2000');
        $applied = $this->approve($item);
        $id = $applied['result']['order_id'];
        $db->exec('UPDATE ordrer SET hvem=?, tidspkt=? WHERE id=?', [$this->env->ctx()->username, (string)time(), $id]);
        self::assertTrue($this->inbox()->get($this->env->ctx(), $applied['id'])['can_undo']);
        self::assertSame('undone', $this->inbox()->undo($this->env->ctx(), $applied['id'], $this->env->ctx()->tenantHash())['status']);
    }

    public function testSalesHeaderTotalsMatchLegacyRoundingAndRemainUndoable(): void
    {
        $db = $this->env->db;
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales(['unit_price' => '125.005', 'discount_pct' => '12.500'])));
        $id = $item['result']['order_id'];
        $header = $db->one('SELECT sum,moms,kostpris FROM ordrer WHERE id=?', [$id]);
        self::assertSame('273.450', $header['sum']);
        self::assertSame('68.363', $header['moms']);
        self::assertSame('50.000', $header['kostpris']);
        $db->exec('UPDATE ordrer SET sum=273.45,moms=68.363,kostpris=50 WHERE id=?', [$id]);
        self::assertTrue($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
    }

    public function testVatInclusiveTotalsKeepLegacyRounding(): void
    {
        $db = $this->env->db;
        $db->insert('settings', ['var_grp' => 'ordre', 'var_name' => 'vatPrivateCustomers', 'var_value' => 'on', 'user_id' => 0, 'pos_id' => 0]);
        $item = $this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales(['unit_price' => '125.005', 'discount_pct' => '12.5']));
        $applied = $this->approve($item);
        $header = $db->one('SELECT sum,moms,kostpris FROM ordrer WHERE id=?', [$applied['result']['order_id']]);
        self::assertSame('273.450', $header['sum']);
        self::assertSame('68.360', $header['moms']);
        self::assertTrue($this->inbox()->get($this->env->ctx(), $applied['id'])['can_undo']);
    }

    public function testPercentageCampaignExpiryKeepsReviewedDiscount(): void
    {
        $db = $this->env->db;
        $end = (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->modify('+2 seconds');
        $db->exec("UPDATE varer SET specialtype='percent',special_price='15',special_to_date=?,special_to_time=? WHERE id=?", [$end->format('Y-m-d'), $end->format('H:i:s'), $this->product]);
        $item = $this->inbox()->create($this->env->ctx(), 'sales_order.draft', $this->sales());
        self::assertSame('15.000', $item['payload']['lines'][0]['pricing']['rabat']);
        sleep(3);
        $applied = $this->approve($item);
        self::assertSame('15.00000', $this->inserted($applied)[0]['rabat']);
        self::assertSame('15', rtrim(rtrim($item['card']['rows'][0][4], '0'), '.'));
        self::assertSame('15', rtrim(rtrim($applied['card']['rows'][0][4], '0'), '.'));
    }

    public function testHeaderCostAndSurchargeRoundOnlyAfterAccumulation(): void
    {
        $db = $this->env->db;
        $db->exec('UPDATE varer SET kostpris=0.001 WHERE id=?', [$this->product]);
        $input = $this->sales();
        $input['lines'] = array_fill(0, 2, ['product_id' => $this->product, 'quantity' => '0.500', 'unit_price' => '1']);
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'sales_order.draft', $input));
        self::assertSame('0.001', $db->value('SELECT kostpris FROM ordrer WHERE id=?', [$item['result']['order_id']]));
        $db->exec("DELETE FROM grupper WHERE art='DIV' AND kodenr=3");
        $this->groupRow('DIV', 3, ['box13' => '2.333']);
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'sales_order.draft',
            $this->sales(['unit_price' => '125.005', 'discount_pct' => '12.500'])));
        $id = $item['result']['order_id'];
        $row = $db->one('SELECT sum,moms FROM ordrer WHERE id=?', [$id]);
        self::assertSame('279.830', $row['sum']);
        self::assertSame('69.957', $row['moms']);
        $db->exec('UPDATE ordrer SET sum=279.8295885,moms=69.957397125,kostpris=0.0025 WHERE id=?', [$id]);
        self::assertTrue($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
    }

}
