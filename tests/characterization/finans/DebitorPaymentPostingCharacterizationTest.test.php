<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/finans/DebitorPaymentPostingCharacterizationTest.test.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Characterise web payments before extracting the posting engine.
// 20261001 CDX/LH Protect existing settlement dates and pin the latest date across invoice references.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/support/PaymentFixture.php';

final class DebitorPaymentPostingCharacterizationTest extends TestCase
{
    private static $connection;
    private static array $fixture;
    public static function setUpBeforeClass(): void
    {
        if ($reason = CharacterizationEnv::unavailableReason()) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        PaymentFixture::session();
        self::$connection = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        self::$fixture = PaymentFixture::seed(self::$connection);
    }
    public static function tearDownAfterClass(): void
    {
        if (self::$connection) {
            pg_close(self::$connection);
            PaymentFixture::dropSession();
            CharacterizationEnv::teardownTenant();
        }
    }
    private function pay(string $invoice, string $amount, string $date = '2026-03-15'): int
    {
        $journal = PaymentFixture::journal(self::$connection, self::$fixture, $invoice, $amount, $date);
        $result = CharacterizationEnv::runChild(__DIR__ . '/../support/run_bogfor_page.php', ['bogfor',$journal,PaymentFixture::SESSION_ID]);
        self::assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
        self::assertSame('V', PaymentFixture::query(self::$connection, 'SELECT bogfort FROM kladdeliste WHERE id=$1', [$journal])[0]['bogfort'], $result['stderr'] . $result['stdout']);
        return $journal;
    }
    public function testExactPaymentUsesThePaymentDateAndKeepsHistoricalInvoiceOpen(): void
    {
        $id = PaymentFixture::invoice(self::$connection, self::$fixture, '101');
        $journal = $this->pay('101','1000.00');
        $rows = PaymentFixture::query(self::$connection, 'SELECT amount,udlignet,udlign_id,udlign_date FROM openpost WHERE id=$1 OR kladde_id=$2 ORDER BY id', [$id,$journal]);
        self::assertCount(2,$rows);
        self::assertSame(['1','1'],array_column($rows,'udlignet'));
        self::assertSame($rows[0]['udlign_id'],$rows[1]['udlign_id']);
        self::assertSame(['2026-03-15','2026-03-15'],array_column($rows,'udlign_date'));
        require_once __DIR__ . '/../../../includes/std_func.php';
        require_once __DIR__ . '/../../../includes/reportFunc/showOpenPosts.php';
        $posts = PaymentFixture::query(self::$connection, 'SELECT * FROM openpost WHERE id=$1 OR kladde_id=$2 ORDER BY id', [$id,$journal]);
        $GLOBALS['baseCurrency'] = 'DKK';
        $cache = [];
        require_once __DIR__ . '/../../../includes/forfaldsdag.php';
        $historical = openpost_account_aging(array_filter($posts, static fn(array $row): bool => $row['transdate'] <= '2026-02-15'), '2026-02-15', '2026-03-15', 'D', $cache);
        self::assertEquals(1000, $historical['openY']);
    }
    public function testPartialSeriesRemainsOpenEvenWhenItsSumReachesZero(): void
    {
        PaymentFixture::invoice(self::$connection, self::$fixture, '102');
        $this->pay('102','400.00');
        $rows = PaymentFixture::query(self::$connection,"SELECT amount,udlignet,faktnr FROM openpost WHERE konto_id=$1 AND faktnr='102' ORDER BY id",[self::$fixture['debtor']]);
        self::assertCount(2,$rows);
        self::assertEquals(600,array_sum(array_column($rows,'amount')));
        self::assertSame(['0','0'],array_column($rows,'udlignet'));
        $this->pay('102','600.00');
        $rows = PaymentFixture::query(self::$connection,"SELECT amount,udlignet FROM openpost WHERE konto_id=$1 AND faktnr='102' ORDER BY id",[self::$fixture['debtor']]);
        self::assertCount(3,$rows);
        self::assertEquals(0,array_sum(array_column($rows,'amount')));
        self::assertSame(['0','0','0'],array_column($rows,'udlignet'));
    }
    public function testSemicolonPaymentPinsExistingWebRows(): void
    {
        $a = PaymentFixture::invoice(self::$connection,self::$fixture,'103','400.00');
        $b = PaymentFixture::invoice(self::$connection,self::$fixture,'104','600.00');
        $journal = $this->pay('103;104','1000.00');
        $rows = PaymentFixture::query(self::$connection,'SELECT amount,udlignet,faktnr FROM openpost WHERE id IN ($1,$2) OR kladde_id=$3 ORDER BY id',[$a,$b,$journal]);
        self::assertCount(4,$rows); // legacy inserts the full payment once for each matched invoice
        self::assertSame(['1','1','1','1'],array_column($rows,'udlignet'));
        self::assertSame(['103','104','103;104','103;104'],array_column($rows,'faktnr'));
    }

    public function testPaymentPreservesExistingDatesWhenALegacyWriterReusesTheNextId(): void
    {
        $closed = PaymentFixture::invoice(self::$connection, self::$fixture, '105');
        $closedJournal = $this->pay('105', '1000.00', '2026-02-20');
        $nextId = PaymentFixture::query(self::$connection,
            'SELECT last_value + CASE WHEN is_called THEN 1 ELSE 0 END AS id FROM openpost_udlign_seq')[0]['id'];
        // A legacy MAX+1 writer can claim the next sequence value without advancing the sequence.
        PaymentFixture::query(self::$connection,
            'UPDATE openpost SET udlign_id=$1 WHERE id=$2 OR kladde_id=$3', [$nextId, $closed, $closedJournal]);
        $before = PaymentFixture::query(self::$connection,
            'SELECT id,udlign_id,udlign_date,udlignet,amount FROM openpost WHERE id=$1 OR kladde_id=$2 ORDER BY id',
            [$closed, $closedJournal]);
        self::assertSame(['2026-02-20', '2026-02-20'], array_column($before, 'udlign_date'));

        $invoice = PaymentFixture::invoice(self::$connection, self::$fixture, '106');
        $journal = $this->pay('106', '1000.00', '2026-04-15');
        self::assertSame($before, PaymentFixture::query(self::$connection,
            'SELECT id,udlign_id,udlign_date,udlignet,amount FROM openpost WHERE id=$1 OR kladde_id=$2 ORDER BY id',
            [$closed, $closedJournal]));
        $rows = PaymentFixture::query(self::$connection,
            'SELECT udlign_date,udlignet FROM openpost WHERE id=$1 OR kladde_id=$2 ORDER BY id', [$invoice, $journal]);
        self::assertSame(['1', '1'], array_column($rows, 'udlignet'));
        self::assertSame(['2026-04-15', '2026-04-15'], array_column($rows, 'udlign_date'));
    }

    public function testSemicolonPaymentUsesTheLatestDateForEveryParticipant(): void
    {
        $a = PaymentFixture::invoice(self::$connection, self::$fixture, '107', '400.00', 'DKK', '2026-02-20');
        $b = PaymentFixture::invoice(self::$connection, self::$fixture, '108', '600.00', 'DKK', '2026-04-15');
        $journal = $this->pay('107;108', '1000.00');
        $rows = PaymentFixture::query(self::$connection,
            'SELECT udlignet,udlign_id,udlign_date FROM openpost WHERE id IN ($1,$2) OR kladde_id=$3 ORDER BY id',
            [$a, $b, $journal]);
        self::assertCount(4, $rows);
        self::assertSame(['1', '1', '1', '1'], array_column($rows, 'udlignet'));
        self::assertCount(1, array_unique(array_column($rows, 'udlign_id')));
        self::assertSame(array_fill(0, 4, '2026-04-15'), array_column($rows, 'udlign_date'));
    }
}
