<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/debitor/DunningCreateCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Created.
// 20261007 CDX/LH Assert the restored tenant SQL trail on the web write path.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../fixtures/dunning/DunningFixture.php';

require_once __DIR__ . '/../support/ModificationTrail.php';

final class DunningCreateCharacterizationTest extends TestCase
{
    private static $tenant;
    private static string $session;
    private array $fixture;

    public static function setUpBeforeClass(): void
    {
        if (($reason = CharacterizationEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        self::$session = 'b2' . bin2hex(random_bytes(15));
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $year = CharacterizationEnv::one(self::$tenant, "SELECT max(kodenr) AS year FROM grupper WHERE art='RA'")['year'];
        CharacterizationEnv::rows($master, "INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
            VALUES($1,'chartest',$2,$3,$4,$5,$6,false,0)",
            [self::$session,CharacterizationEnv::testDb(),CharacterizationEnv::pgUser(),str_repeat('9',50),$year,(string)time()]);
        pg_close($master);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            CharacterizationEnv::rows($master,'DELETE FROM online WHERE session_id=$1',[self::$session]);
            pg_close($master);
            pg_close(self::$tenant);
            CharacterizationEnv::teardownTenant();
        }
    }

    protected function setUp(): void
    {
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM ordrelinjer WHERE ordre_id IN (SELECT id FROM ordrer WHERE art LIKE 'R%')");
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM ordrer WHERE art LIKE 'R%'");
        $this->fixture = DunningFixture::seed(self::$tenant);
    }

    private function page(int $base = 0): void
    {
        $child = CharacterizationEnv::runChild(__DIR__ . '/../support/run_ny_rykker_page.php',
            [$this->fixture['konto_id'],$base,self::$session]);
        self::assertSame(0, $child['exit'], $child['stderr']);
        self::assertStringContainsString('CHARTEST_PAGE_DONE', $child['stdout'], $child['stderr']);
    }

    /** Monthly compound interest uses the current clock, including the started month. @return float */
    private function interest(float $amount, string $date): float
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen'));
        $due = new DateTimeImmutable($date);
        $months = ((int)$now->format('Y')-(int)$due->format('Y'))*12 + (int)$now->format('n')-(int)$due->format('n');
        if ($now->format('d') >= $due->format('d')) {
            $months++;
        }
        return round($amount * (pow(1.01, max(0,$months))-1), 2);
    }

    public function testR1PersistsExactHeaderReferencesFeeAndInterest(): void
    {
        $trail = ModificationTrail::read(CharacterizationEnv::testDb());
        $this->page();
        ModificationTrail::assertWrite(CharacterizationEnv::testDb(), $trail, 'INSERT INTO ordrer\\b');
        ModificationTrail::assertWrite(CharacterizationEnv::testDb(), $trail, 'INSERT INTO ordrelinjer\\b');
        $orders = CharacterizationEnv::rows(self::$tenant, "SELECT * FROM ordrer WHERE konto_id=$1 AND art='R1'", [$this->fixture['konto_id']]);
        self::assertCount(1,$orders);
        $o = $orders[0];
        self::assertSame('1',$o['ordrenr']);
        self::assertSame('1-1',$o['fakturanr']);
        self::assertSame('2',$o['status']);
        self::assertSame('-',$o['betalt']);
        self::assertSame('DKK',$o['valuta']);
        self::assertSame(100.0,(float)$o['valutakurs']);
        self::assertSame('8',$o['betalingsdage']);
        self::assertSame('Netto',$o['betalingsbet']);
        $lines = CharacterizationEnv::rows(self::$tenant, 'SELECT * FROM ordrelinjer WHERE ordre_id=$1 ORDER BY posnr,id', [$o['id']]);
        self::assertCount(5,$lines);
        $interest = 0;
        foreach ([0,1] as $index) {
            $post = CharacterizationEnv::one(self::$tenant, 'SELECT * FROM openpost WHERE id=$1', [$this->fixture['posts'][$index]]);
            $line = $lines[$index*2];
            self::assertSame((string)$post['id'],$line['enhed']);
            self::assertSame($post['transdate'],$line['serienr']);
            self::assertSame($post['beskrivelse'],$line['beskrivelse']);
            self::assertEquals(0,$line['vare_id']);
            $amount = $this->interest((float)$post['amount'],$post['forfaldsdate']);
            self::assertSame($amount,(float)$lines[$index*2+1]['pris']);
            self::assertSame($this->fixture['interest_id'],(int)$lines[$index*2+1]['vare_id']);
            self::assertSame(1.0,(float)$lines[$index*2+1]['antal']);
            $interest += $amount;
        }
        self::assertSame($this->fixture['fee_id'],(int)$lines[4]['vare_id']);
        self::assertSame(100.0,(float)$lines[4]['pris']);
        self::assertSame(100+$interest,(float)$o['sum']);
    }

    public function testPostAlreadyOnAnOpenRykkerIsExcluded(): void
    {
        DunningFixture::base(self::$tenant,$this->fixture);
        $this->page();
        $order = CharacterizationEnv::one(self::$tenant, "SELECT id FROM ordrer WHERE konto_id=$1 AND ordrenr=91882",[$this->fixture['konto_id']]);
        self::assertNotNull($order);
        $lines = CharacterizationEnv::rows(self::$tenant, 'SELECT enhed FROM ordrelinjer WHERE ordre_id=$1 AND COALESCE(vare_id,0)=0',[$order['id']]);
        self::assertSame([['enhed'=>(string)$this->fixture['posts'][1]]],$lines);
    }

    public function testR2CopiesInvoiceAndPostedFeeAndRetainsLegacyHeaderSum(): void
    {
        $base = DunningFixture::base(self::$tenant,$this->fixture);
        $this->page($base);
        $order = CharacterizationEnv::one(self::$tenant, "SELECT * FROM ordrer WHERE konto_id=$1 AND art='R2'",[$this->fixture['konto_id']]);
        self::assertNotNull($order);
        self::assertSame('91881-2',$order['fakturanr']);
        self::assertSame('2',$order['status']);
        // ny_rykker writes the new level's sum to the BASE header, leaving the new header at its default.
        self::assertEquals(0,(float)$order['sum']);
        $lines = CharacterizationEnv::rows(self::$tenant, 'SELECT * FROM ordrelinjer WHERE ordre_id=$1 ORDER BY id',[$order['id']]);
        self::assertCount(4,$lines);
        self::assertSame((string)$this->fixture['posts'][0],$lines[0]['enhed']);
        $feePost = CharacterizationEnv::one(self::$tenant, 'SELECT id FROM openpost WHERE refnr=$1',[$base]);
        self::assertSame($feePost['id'],$lines[1]['enhed']);
        $date = CharacterizationEnv::one(self::$tenant,'SELECT fakturadate FROM ordrer WHERE id=$1',[$base])['fakturadate'];
        $interest = $this->interest(1100,$date);
        self::assertSame($interest,(float)$lines[2]['pris']);
        self::assertSame(100.0,(float)$lines[3]['pris']);
        self::assertSame(100+$interest,(float)CharacterizationEnv::one(self::$tenant,'SELECT sum FROM ordrer WHERE id=$1',[$base])['sum']);
    }

    public function testR3RefusesAnAdditionalLevel(): void
    {
        $base = DunningFixture::base(self::$tenant,$this->fixture,3);
        $before = CharacterizationEnv::rows(self::$tenant,'SELECT * FROM ordrer ORDER BY id');
        $this->page($base);
        self::assertSame($before,CharacterizationEnv::rows(self::$tenant,'SELECT * FROM ordrer ORDER BY id'));
    }
    public function testMixedBatchRetainsTheLegacyPerPageSumCarry(): void
    {
        $second=DunningFixture::seed(self::$tenant);
        $base=DunningFixture::base(self::$tenant,$second);
        $request=['kontoliste'=>$this->fixture['konto_id'].';'.$second['konto_id'],'rykker_id'=>'0;'.$base,'kontoantal'=>'1'];
        $child=CharacterizationEnv::runChild(__DIR__.'/../support/run_ny_rykker_page.php',
            [$this->fixture['konto_id'],0,self::$session,json_encode($request,JSON_THROW_ON_ERROR)]);
        self::assertSame(0,$child['exit'],$child['stderr']);
        self::assertStringContainsString('CHARTEST_PAGE_DONE',$child['stdout'],$child['stderr']);
        $first=CharacterizationEnv::one(self::$tenant,"SELECT sum FROM ordrer WHERE konto_id=$1 AND art='R1'",[$this->fixture['konto_id']]);
        $new=CharacterizationEnv::one(self::$tenant,"SELECT id,sum FROM ordrer WHERE konto_id=$1 AND art='R2'",[$second['konto_id']]);
        self::assertNotNull($new);
        $charges=CharacterizationEnv::one(self::$tenant,'SELECT sum(pris) AS sum FROM ordrelinjer WHERE ordre_id=$1 AND vare_id>0',[$new['id']]);
        $changed=CharacterizationEnv::one(self::$tenant,'SELECT sum FROM ordrer WHERE id=$1',[$base]);
        self::assertSame((float)$first['sum']+(float)$charges['sum'],(float)$changed['sum']);
        self::assertEquals(0,(float)$new['sum']);
    }

    public function testAlleAllocatesOneSequenceIdForTheZeroBalanceGroup(): void
    {
        CharacterizationEnv::rows(self::$tenant,'DELETE FROM openpost');
        $this->fixture=DunningFixture::seed(self::$tenant,0);
        CharacterizationEnv::rows(self::$tenant,"INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,udlignet,transdate,forfaldsdate)
            VALUES($1,91881,'B2-payment',-1500,'0',CURRENT_DATE,CURRENT_DATE)",[$this->fixture['konto_id']]);
        // The unchanged alle loop discovers zero-balance groups when it enters the next account.
        $sentinel=DunningFixture::seed(self::$tenant,0);
        CharacterizationEnv::rows(self::$tenant,'UPDATE openpost SET forfaldsdate=CURRENT_DATE+10 WHERE konto_id=$1',[$sentinel['konto_id']]);
        $child=CharacterizationEnv::runChild(__DIR__.'/../support/run_ny_rykker_page.php',['alle',0,self::$session]);
        self::assertSame(0,$child['exit'],$child['stderr']);
        self::assertStringContainsString('CHARTEST_PAGE_DONE',$child['stdout'],$child['stderr']);
        $rows=CharacterizationEnv::rows(self::$tenant,'SELECT udlignet,udlign_id FROM openpost WHERE konto_id=$1',[$this->fixture['konto_id']]);
        self::assertCount(3,$rows);
        self::assertSame(['1'],array_values(array_unique(array_column($rows,'udlignet'))));
        self::assertCount(1,array_unique(array_column($rows,'udlign_id')));
        self::assertGreaterThan(0,(int)$rows[0]['udlign_id']);
        $sequence=CharacterizationEnv::one(self::$tenant,'SELECT last_value FROM openpost_udlign_seq');
        self::assertSame($sequence['last_value'],$rows[0]['udlign_id']);
    }

}
