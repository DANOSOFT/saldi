<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiPurchaseOrderDraftKindTest.test.php --- ai/mcp-assistant --- 2026.10.05
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
// Danish quantities on purchase approval cards, including stored rows and stale proposals.
//
// 20261005 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiPurchaseTestCase.php';

final class SaldiPurchaseOrderDraftKindTest extends SaldiPurchaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->env->db->exec("DELETE FROM grupper WHERE art = 'KG'");
        $this->env->db->insert('grupper', ['art' => 'KG', 'kodenr' => 1, 'fiscal_year' => 4, 'box3' => 'DKK']);
        $this->env->db->exec('UPDATE adresser SET gruppe = 1 WHERE id = ?', [$this->supplier]);
    }

    public function testQuantitiesAreDanishOnProposedAppliedAndStaleCards(): void
    {
        $ctx = $this->env->ctx();
        foreach (['2.000' => '2', '2.500' => '2,5', '1234.250' => '1.234,25'] as $stored => $expected) {
            $item = $this->inbox()->create($ctx, 'purchase_order.draft', [
                'supplier_account' => '2000',
                'lines' => [['product_id' => $this->product, 'quantity' => $stored]],
            ]);
            self::assertSame($expected, $item['card']['rows'][0][2]);
            $applied = $this->approve($item);
            self::assertSame($expected, $applied['card']['rows'][0][2]);
            self::assertSame($stored, $this->env->db->value('SELECT antal FROM ordrelinjer WHERE ordre_id = ?', [$applied['result']['order_id']]));
        }
        $item = $this->inbox()->create($ctx, 'purchase_order.draft', [
            'supplier_account' => '2000', 'lines' => [['product_id' => $this->product, 'quantity' => '2.500']],
        ]);
        $this->env->db->exec("UPDATE varer SET lukket = 'on' WHERE id = ?", [$this->product]);
        self::assertSame('2,5', $this->inbox()->preview($ctx, $item['id'])['card']['rows'][0][2]);
    }
}
