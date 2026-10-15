<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileEventScannersTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Regress unfinished webshop totals, concurrent overdue settlement and cumulative digest/seed consumption across aging dates.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/T3EventTestEnv.php';
require_once __DIR__ . '/../../restapi/services/MobileEventScanners.php';

final class MobileEventScannersTest extends TestCase
{
    private int $customer;
    private DateTimeImmutable $now;

    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        T3EventTestEnv::setup();
    }

    public static function tearDownAfterClass(): void
    {
        T3EventTestEnv::teardown();
    }

    protected function setUp(): void
    {
        T3EventTestEnv::reset();
        foreach (['mobile_scanner_state','mobile_low_stock_state','bank_line_journal_links','bank_lines','bank_line_imports',
            'payment_registrations','openpost','ordrelinjer','shop_ordrer','ordrer','lagerstatus','varer'] as $table) {
            $this->rows('DELETE FROM '.$table);
        }
        $this->rows("DELETE FROM grupper WHERE art='VG' OR (art IN ('DG','POS') AND kodenr IN (1,2,98763))");
        $this->rows("INSERT INTO grupper(art,kodenr,box8) VALUES('VG',98761,'on'),('VG',98762,'off')");
        $this->rows("DELETE FROM settings WHERE var_name='min_beholdning' AND var_grp='productOptions'");
        $this->rows("INSERT INTO settings(var_name,var_grp,var_value) VALUES('min_beholdning','productOptions','5')");
        $this->customer = (int)$this->rows("INSERT INTO adresser(art,kontonr,firmanavn,gruppe) VALUES('D','SC1','Scanner customer',98763) RETURNING id")[0]['id'];
        $this->rows("DELETE FROM settings WHERE var_name='baseCurrency'");
        $this->rows("INSERT INTO settings(var_name,var_grp,var_value) VALUES('baseCurrency','globals','DKK')");
        $this->now = new DateTimeImmutable('today 08:00', new DateTimeZone('Europe/Copenhagen'));
    }

    private function rows(string $sql, array $params = [], $link = null): array
    {
        return RestApiEnv::rows($link ?? T3EventTestEnv::$tenant,$sql,$params);
    }

    private function scan(?DateTimeImmutable $now = null, $link = null): array
    {
        $time = $now ?? $this->now;
        return (new MobileEventScanners($link ?? T3EventTestEnv::$tenant,T3EventTestEnv::$registry,
            T3EventTestEnv::$tenantId,static fn(): DateTimeImmutable => $time))->run();
    }

    /** @return array{id:string,order_id:string} A posted DO ledger invoice with a known amount and due date. */
    private function invoice(string $nr = '101', string $amount = '100.00', int $days = 0): array
    {
        $order = $this->rows("INSERT INTO ordrer(art,konto_id,kontonr,firmanavn,ordrenr,fakturanr,fakturadate,ordredate,sum,moms,valuta,status)
            VALUES('DO',$1,'SC1','Scanner customer',$2::text::integer,$2::text,current_date,current_date,$3,0,'DKK',4) RETURNING id", [$this->customer,$nr,$amount])[0];
        $due = $this->now->modify('-'.$days.' days')->format('Y-m-d');
        $post = $this->rows("INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,transdate,forfaldsdate,kladde_id,refnr,valuta,valutakurs)
            VALUES($1,'SC1',$2,$3,'Invoice','0',current_date,$4,0,$5,'DKK',100) RETURNING id", [$this->customer,$nr,$amount,$due,$order['id']])[0];
        return ['id'=>$post['id'],'order_id'=>$order['id']];
    }

    private function settle(array $invoice, int $group, string $amount = '-100.00', int $journal = 31, $link = null): int
    {
        $this->rows("UPDATE openpost SET udlignet='1',udlign_id=$1,udlign_date=current_date WHERE id=$2", [$group,$invoice['id']],$link);
        return (int)$this->rows("INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,transdate,kladde_id,refnr,udlign_id,valuta,valutakurs)
            VALUES($1,'SC1','payment',$2,'Payment','1',current_date,$3,0,$4,'DKK',100) RETURNING id", [$this->customer,$amount,$journal,$group],$link)[0]['id'];
    }

    private function events(?string $type = null): array
    {
        return $this->rows('SELECT * FROM mobile_events'.($type === null ? '' : ' WHERE type=$1').' ORDER BY id',$type === null ? [] : [$type]);
    }

    public function testSeedEmitsNothingForHistoricalRowsAndBelowStock(): void
    {
        $invoice = $this->invoice('101','100.00',1);
        $this->settle($invoice,100);
        $this->invoice('102','100.00',1);
        $this->shop('mapped');
        $this->item(2);
        self::assertSame(5,$this->scan()['seeded']);
        self::assertSame([],$this->events());
        self::assertCount(1,$this->rows('SELECT * FROM mobile_low_stock_state'));
        self::assertSame(0,$this->scan()['seeded']);
        self::assertSame([],$this->events());
    }

    public function testGroupedPaymentOnceAndNoPartialOrCreditNoteNotification(): void
    {
        $paid = $this->invoice();
        $partial = $this->invoice('102');
        $credit = $this->invoice('103');
        $this->scan();
        $this->settle($paid,101);
        $this->rows("INSERT INTO openpost(konto_id,faktnr,amount,udlignet,transdate,kladde_id,udlign_id) VALUES($1,'102',-40,'0',current_date,31,102)",[$this->customer]);
        $creditPayment = $this->settle($credit,103,'-100.00',0);
        $creditOrder = $this->rows("INSERT INTO ordrer(art,konto_id,fakturanr,sum,moms) VALUES('DK',$1,'CN1',-100,0) RETURNING id",[$this->customer])[0]['id'];
        $this->rows("UPDATE openpost SET refnr=$1,faktnr='CN1' WHERE id=$2",[$creditOrder,$creditPayment]);
        self::assertSame(1,$this->scan()['invoice_paid']);
        self::assertSame('invoice_paid:'.$paid['id'],$this->events()[0]['dedupe_key']);
        self::assertSame(['faktnr'=>'101','amount'=>'100.00','customer_name'=>'Scanner customer'],json_decode($this->events()[0]['payload'],true));
        self::assertSame(0,$this->scan()['invoice_paid']);
        self::assertSame(2,T3EventTestEnv::fanout()->run()['recipients']);
        self::assertSame(0,T3EventTestEnv::fanout()->run()['fanned_out']);
    }

    public function testRegistrationEvidenceUsesInvoiceIdentityEvenWithNonJournalPayment(): void
    {
        $invoice = $this->invoice();
        $this->scan();
        $this->settle($invoice,101,'-100.00',0);
        $this->rows("INSERT INTO payment_registrations(client_id,source,openpost_id,konto_id,faktnr,amount,currency,method,account_kontonr,transdate,kladde_id,bilag,aligned,created_by)
            VALUES($1,'app',$2,$3,'101',100,'DKK','bank',6500,current_date,0,0,true,$4)",
            [MobileAuth::uuid(),$invoice['id'],$this->customer,T3EventTestEnv::$subject]);
        self::assertSame(1,$this->scan()['invoice_paid']);
        self::assertCount(1,$this->events());
    }

    public function testPosMatchingUsesTenantBaseCurrencyAndRejectsDifferentCurrency(): void
    {
        $wrong = $this->invoice('101');
        $right = $this->invoice('102');
        $this->rows("DELETE FROM settings WHERE var_name='baseCurrency'");
        $this->rows("INSERT INTO settings(var_name,var_grp,var_value) VALUES('baseCurrency','globals','EUR')");
        $this->rows("UPDATE openpost SET valuta='USD' WHERE id=$1",[$wrong['id']]);
        $this->rows("UPDATE openpost SET valuta='' WHERE id=$1",[$right['id']]);
        $this->scan();
        $order = $this->rows("INSERT INTO ordrer(art) VALUES('PO') RETURNING id")[0]['id'];
        $this->rows("INSERT INTO openpost(konto_id,amount,beskrivelse,udlignet,kladde_id,refnr,valuta)
            VALUES($1,-100,'Indbetaling konto: SC1','1',0,$2,'EUR')",[$this->customer,$order]);
        $this->rows("UPDATE openpost SET udlignet='1' WHERE id IN ($1,$2)",[$wrong['id'],$right['id']]);
        self::assertSame(1,$this->scan()['invoice_paid']);
        self::assertSame('invoice_paid:'.$right['id'],$this->events()[0]['dedupe_key']);
    }

    /** @return int Actual legacy bogfor_indbetaling() through an isolated POS child process. */
    private function payPos(string $amount = '100.00'): int
    {
        $year = (int)$this->rows("SELECT max(kodenr) AS year FROM grupper WHERE art='RA'")[0]['year'];
        $this->rows("INSERT INTO grupper(art,kodenr,box2,fiscal_year) SELECT 'DG',98763,'6500',$1
            WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art='DG' AND kodenr=98763)",[$year]);
        $this->rows("INSERT INTO grupper(art,kodenr,box2,box6,fiscal_year) SELECT 'POS',1,'5800','5800',$1
            WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art='POS' AND kodenr=1)",[$year]);
        $this->rows("INSERT INTO grupper(art,kodenr,box6,fiscal_year) SELECT 'POS',2,'5800',$1
            WHERE NOT EXISTS (SELECT 1 FROM grupper WHERE art='POS' AND kodenr=2)",[$year]);
        $order = $this->rows("INSERT INTO ordrer(art,konto_id,kontonr,firmanavn,ordrenr,fakturanr,fakturadate,sum,moms,valuta,projekt,felt_1,felt_2,felt_5)
            VALUES('PO',$1,'SC1','Scanner customer',9999,'POS',current_date,$2::text::numeric,0,'DKK',0,'Kontant',$2::text,'1') RETURNING id",[$this->customer,$amount])[0];
        $log = tempnam('/tmp','scanner-pos-');
        $process = proc_open([PHP_BINARY,__DIR__.'/support/run_scanner_pos_payment.php',RestApiEnv::testDb(),
            (string)T3EventTestEnv::$tenantId,(string)$order['id']], [0=>['pipe','r'],1=>['file',$log,'w'],2=>['file',$log.'.err','w']],$pipes);
        fclose($pipes[0]);
        $exit = proc_close($process);
        $output = file_get_contents($log);
        $errors = file_get_contents($log.'.err');
        unlink($log);
        unlink($log.'.err');
        self::assertSame(0,$exit,$output.$errors);
        self::assertStringContainsString('"result":"OK"',$output);
        $post = $this->rows('SELECT id,udlign_id,udlignet FROM openpost WHERE refnr=$1',[$order['id']])[0];
        self::assertSame('1',$post['udlignet']);
        self::assertSame(0,(int)$post['udlign_id']);
        return (int)$post['id'];
    }

    public function testRealPosInvoiceSettlementWithoutGroupIsFoundAndReplayIsEmpty(): void
    {
        $invoice = $this->invoice();
        $this->scan();
        $payment = $this->payPos();
        self::assertSame('1',$this->rows('SELECT udlignet FROM openpost WHERE id=$1',[$invoice['id']])[0]['udlignet']);
        self::assertSame(1,$this->scan()['invoice_paid']);
        self::assertSame($payment,json_decode($this->events()[0]['payload'],true)['pos_payment_id']);
        self::assertSame(0,$this->scan()['invoice_paid']);
    }

    public function testSeededHistoricalPosInvoiceCannotTakeANewPaymentsMatch(): void
    {
        $this->invoice('101');
        $this->payPos();
        $current = $this->invoice('102');
        $this->scan();
        self::assertSame([],$this->events());
        $this->payPos();
        self::assertSame(1,$this->scan()['invoice_paid']);
        self::assertSame('invoice_paid:'.$current['id'],$this->events()[0]['dedupe_key']);
        self::assertSame(0,$this->scan()['invoice_paid']);
    }

    public function testOnePosPaymentCannotBeReusedForAnotherSameAmountInvoice(): void
    {
        $first = $this->invoice();
        $second = $this->invoice('102');
        $this->scan();
        $this->payPos();
        self::assertSame(1,$this->scan()['invoice_paid']);
        self::assertSame('invoice_paid:'.$first['id'],$this->events()[0]['dedupe_key']);
        // Even another writer marking the second invoice settled cannot reuse the POS row.
        $this->rows("UPDATE openpost SET udlignet='1' WHERE id=$1",[$second['id']]);
        self::assertSame(0,$this->scan()['invoice_paid']);
        self::assertCount(1,$this->events());
    }

    public function testBothShapesAndT2LinkShareOneInvoiceEvent(): void
    {
        $invoice = $this->invoice();
        $this->scan();
        $this->payPos();
        $this->settle($invoice,101);
        MobileEventOutbox::emit(T3EventTestEnv::$tenant,'invoice_paid','invoice_paid:'.$invoice['id'],
            'openpost',(int)$invoice['id'],['faktnr'=>'101','amount'=>'100.00','customer_name'=>'Scanner customer','via'=>'mobilepay'],null);
        self::assertSame(0,$this->scan()['invoice_paid']);
        self::assertSame(0,$this->scan()['invoice_paid']);
        self::assertCount(1,$this->events());
        self::assertSame('mobilepay',json_decode($this->events()[0]['payload'],true)['via']);
    }

    public function testSeparateLinkAndPosPaymentsOnEqualInvoicesBothEmit(): void
    {
        $linkInvoice = $this->invoice();
        $posInvoice = $this->invoice('102');
        $this->scan();
        $this->settle($linkInvoice,101);
        MobileEventOutbox::emit(T3EventTestEnv::$tenant,'invoice_paid','invoice_paid:'.$linkInvoice['id'],
            'openpost',(int)$linkInvoice['id'],['faktnr'=>'101','amount'=>'100.00','customer_name'=>'Scanner customer','via'=>'mobilepay'],null);
        $this->payPos();
        self::assertSame(1,$this->scan()['invoice_paid']);
        self::assertSame(['invoice_paid:'.$linkInvoice['id'],'invoice_paid:'.$posInvoice['id']],array_column($this->events(),'dedupe_key'));
        self::assertSame(0,$this->scan()['invoice_paid']);
    }

    public function testBothShapesWithAnotherEqualInvoiceDoNotDoubleCount(): void
    {
        $invoice = $this->invoice();
        $other = $this->invoice('102');
        $this->scan();
        $this->payPos();
        $this->settle($invoice,101);
        self::assertSame(1,$this->scan()['invoice_paid']);
        self::assertCount(1,$this->events());
        self::assertSame(0,$this->scan()['invoice_paid']);
    }

    public function testNextPosPaymentAfterWatermarkAdvanceFindsTheNextInvoice(): void
    {
        $first = $this->invoice();
        $this->scan();
        $this->payPos();
        $this->settle($first,101);
        self::assertSame(1,$this->scan()['invoice_paid']);
        $second = $this->invoice('102');
        $this->payPos();
        self::assertSame(1,$this->scan($this->now->modify('+75 minutes'))['invoice_paid']);
        self::assertSame(['invoice_paid:'.$first['id'],'invoice_paid:'.$second['id']],array_column($this->events(),'dedupe_key'));
        self::assertSame(0,$this->scan($this->now->modify('+90 minutes'))['invoice_paid']);
    }

    public function testLateCommitBelowNewerSettlementIsStillScanned(): void
    {
        $late = $this->invoice();
        $newer = $this->invoice('102');
        $this->scan();
        $link = RestApiEnv::connect(RestApiEnv::testDb());
        try {
            $this->rows('BEGIN',[],$link);
            $this->settle($late,101,'-100.00',31,$link);
            $this->settle($newer,102);
            self::assertSame(1,$this->scan($this->now->modify('+15 minutes'))['invoice_paid']);
            $this->rows('COMMIT',[],$link);
            self::assertSame(1,$this->scan($this->now->modify('+30 minutes'))['invoice_paid']);
            self::assertCount(2,$this->events());
            self::assertSame(0,$this->scan($this->now->modify('+75 minutes'))['invoice_paid']);
        } finally {
            pg_close($link);
        }
    }

    public function testLatePosPaymentAndWebshopCommitBelowNewerIdsAreFound(): void
    {
        $invoice = $this->invoice();
        $this->scan();
        $link = RestApiEnv::connect(RestApiEnv::testDb());
        try {
            $this->rows('BEGIN',[],$link);
            $order = $this->rows("INSERT INTO ordrer(art,shop_id,ordrenr,firmanavn,sum,moms) VALUES('DO',123,55,'Late shop',100,25) RETURNING id",[],$link)[0]['id'];
            $paymentOrder = $this->rows("INSERT INTO ordrer(art) VALUES('PO') RETURNING id",[],$link)[0]['id'];
            $this->rows("INSERT INTO openpost(konto_id,amount,beskrivelse,udlignet,kladde_id,refnr,valuta) VALUES($1,-100,'Indbetaling konto: SC1','1',0,$2,'DKK')",[$this->customer,$paymentOrder],$link);
            $this->rows("UPDATE openpost SET udlignet='1' WHERE id=$1",[$invoice['id']],$link);
            $this->shop('mapped');
            $this->rows("INSERT INTO openpost(konto_id,amount,beskrivelse,udlignet,kladde_id) VALUES($1,-99,'Indbetaling konto: ignored','0',0)",[$this->customer]);
            self::assertSame(1,$this->scan($this->now->modify('+15 minutes'))['webshop_order']);
            $this->rows('COMMIT',[],$link);
            $stats = $this->scan($this->now->modify('+30 minutes'));
            self::assertSame(1,$stats['invoice_paid']);
            self::assertSame(1,$stats['webshop_order']);
            self::assertContains('webshop_order:'.$order,array_column($this->events(),'dedupe_key'));
        } finally {
            pg_close($link);
        }
    }

    public function testOverdueAfterSevenOncePerInvoiceAcrossDays(): void
    {
        $this->scan($this->now->modify('-2 hours'));
        $this->invoice('101','100.00',1);
        $this->invoice('102','100.00',4);
        $this->invoice('103','100.00',0);
        $this->invoice('104','-100.00',1);
        self::assertSame(0,$this->scan($this->now->modify('-90 minutes'))['invoice_overdue']);
        self::assertSame(1,$this->scan()['invoice_overdue']);
        self::assertSame(0,$this->scan()['invoice_overdue']);
        self::assertSame(0,$this->scan($this->now->modify('+1 day'))['invoice_overdue']);
        self::assertCount(1,$this->events());
    }

    public function testExactlyTenOverdueRemainIndividualEvents(): void
    {
        $this->scan($this->now->modify('-1 day'));
        for ($i=0; $i<10; $i++) {
            $this->invoice((string)(101+$i),'100.00',1);
        }
        $stats = $this->scan();
        self::assertSame(10,$stats['invoice_overdue']);
        self::assertSame(0,$stats['invoice_overdue_digest']);
        self::assertSame(0,$this->scan()['invoice_overdue']);
        self::assertCount(10,$this->events());
    }

    public function testMoreThanTenOverdueBecomeOneDigestAndNoNextDayRepeat(): void
    {
        $this->scan($this->now->modify('-1 day'));
        for ($i=0; $i<11; $i++) {
            $this->invoice((string)(101+$i),'100.00',1);
        }
        $stats = $this->scan();
        self::assertSame(0,$stats['invoice_overdue']);
        self::assertSame(1,$stats['invoice_overdue_digest']);
        self::assertSame(11,json_decode($this->events()[0]['payload'],true)['count']);
        self::assertSame(2,T3EventTestEnv::fanout()->run()['recipients']);
        self::assertSame(0,$this->scan($this->now->modify('+1 day'))['invoice_overdue_digest']);
        self::assertSame(0,$this->scan($this->now->modify('+1 day'))['invoice_overdue']);
        self::assertCount(1,$this->events());
    }

    public function testOverdueSnapshotSurvivesSettlementAfterTheFirstHundredItems(): void
    {
        $this->scan($this->now->modify('-1 day'));
        $invoices = [];
        for ($i = 0; $i < 101; $i++) {
            $invoices[] = $this->invoice((string)(101 + $i),'100.00',3);
        }
        $other = RestApiEnv::connect(RestApiEnv::testDb());
        $ledgerReads = 0;
        $decorated = 0;
        $settled = false;
        try {
            $reader = new OpenPostsReader(function (string $sql, array $params) use ($other, $invoices,
                &$ledgerReads, &$decorated, &$settled): array {
                $rows = $this->rows($sql,$params);
                if (str_contains($sql,'FROM openpost o JOIN adresser')) {
                    $ledgerReads++;
                }
                if (str_contains($sql,'FROM ordrer r LEFT JOIN ordrelinjer') && ++$decorated === 100) {
                    // Commit on another connection while the first page is being decorated.
                    $this->rows('BEGIN',[],$other);
                    $this->settle($invoices[0],101,'-100.00',31,$other);
                    $this->rows('COMMIT',[],$other);
                    $settled = true;
                }
                return $rows;
            },'DKK');
            $stats = (new MobileEventScanners(T3EventTestEnv::$tenant,T3EventTestEnv::$registry,
                T3EventTestEnv::$tenantId,fn(): DateTimeImmutable => $this->now,$reader))->run();
            self::assertTrue($settled);
            self::assertSame('1',$this->rows('SELECT udlignet FROM openpost WHERE id=$1',[$invoices[0]['id']])[0]['udlignet']);
            self::assertSame(1,$stats['invoice_overdue_digest']);
            // The run delivers its initial snapshot, including the invoice settled during it.
            self::assertSame(101,json_decode($this->events('invoice_overdue_digest')[0]['payload'],true)['count']);
            $seen = json_decode($this->rows("SELECT cursor FROM mobile_scanner_state WHERE scanner='invoice_overdue'")[0]['cursor'],true)['seen'];
            foreach (range(102,201) as $nr) {
                self::assertContains('invoice_overdue:'.$this->customer.':'.$nr,$seen);
            }
            self::assertSame(1,$ledgerReads,'Read and group the ledger once for the entire overdue run');
            self::assertSame(0,$this->scan()['invoice_overdue_digest']);
        } finally {
            if (pg_transaction_status($other) !== PGSQL_TRANSACTION_IDLE) {
                $this->rows('ROLLBACK',[],$other);
            }
            pg_close($other);
        }
    }

    public function testDigestMembershipSurvivesSettlementAndReversalAcrossAgingDates(): void
    {
        $this->scan($this->now->modify('-1 day'));
        $invoices = [];
        for ($i = 0; $i < 11; $i++) {
            $invoices[] = $this->invoice((string)(101 + $i),'100.00',1);
        }
        self::assertSame([1],array_values(array_unique(array_column(openposts_reader(T3EventTestEnv::$tenant)
            ->items(['status'=>'overdue'],100,0)['items'],'days_overdue'))));
        self::assertSame(1,$this->scan()['invoice_overdue_digest']);
        self::assertSame(11,json_decode($this->events()[0]['payload'],true)['count']);
        $payment = $this->settle($invoices[0],101);
        // The shared reader uses real today(), so age the fixture dates as well as the scanner clock.
        $this->rows('UPDATE openpost SET forfaldsdate=forfaldsdate-1 WHERE kladde_id=0');
        self::assertSame([2],array_values(array_unique(array_column(openposts_reader(T3EventTestEnv::$tenant)
            ->items(['status'=>'overdue'],100,0)['items'],'days_overdue'))));
        self::assertSame(0,$this->scan($this->now->modify('+1 day'))['invoice_overdue']);
        $this->rows('DELETE FROM openpost WHERE id=$1',[$payment]);
        $this->rows("UPDATE openpost SET udlignet='0',udlign_id=NULL,udlign_date=NULL WHERE id=$1",[$invoices[0]['id']]);
        $this->rows('UPDATE openpost SET forfaldsdate=forfaldsdate-1 WHERE kladde_id=0');
        self::assertSame([3],array_values(array_unique(array_column(openposts_reader(T3EventTestEnv::$tenant)
            ->items(['status'=>'overdue'],100,0)['items'],'days_overdue'))));
        $stats = $this->scan($this->now->modify('+2 days'));
        self::assertSame(0,$stats['invoice_overdue']);
        self::assertSame(0,$stats['invoice_overdue_digest']);
        self::assertCount(1,$this->events('invoice_overdue_digest'));
        self::assertSame([],$this->events('invoice_overdue'));
    }

    public function testQuietlySeededOverdueInvoiceStaysConsumedAfterSettlementReversal(): void
    {
        $invoice = $this->invoice('101','100.00',1);
        $this->scan();
        self::assertSame([],$this->events());
        $payment = $this->settle($invoice,101);
        $this->rows('UPDATE openpost SET forfaldsdate=forfaldsdate-1 WHERE kladde_id=0');
        self::assertSame(0,$this->scan($this->now->modify('+1 day'))['invoice_overdue']);
        $this->rows('DELETE FROM openpost WHERE id=$1',[$payment]);
        $this->rows("UPDATE openpost SET udlignet='0',udlign_id=NULL,udlign_date=NULL,forfaldsdate=forfaldsdate-1 WHERE id=$1",[$invoice['id']]);
        $stats = $this->scan($this->now->modify('+2 days'));
        self::assertSame(0,$stats['invoice_overdue']);
        self::assertSame(0,$stats['invoice_overdue_digest']);
        self::assertSame([],$this->events('invoice_overdue'));
    }

    private function shop(string $shape): int
    {
        $order = $this->rows("INSERT INTO ordrer(art,ordrenr,firmanavn,sum,moms,shop_id) VALUES('DO',55,'Shop customer',100,25,$1) RETURNING id",[$shape === 'v2' ? 123 : 0])[0]['id'];
        if ($shape !== 'v2') {
            $this->rows('INSERT INTO shop_ordrer(saldi_id,shop_id) VALUES($1,$2)',[$order,random_int(1000,999999)]);
        }
        return (int)$order;
    }

    public function testAllThreeWebshopInsertShapesAndGrossAmount(): void
    {
        $this->scan();
        $ids = [$this->shop('rest_api'),$this->shop('hent_ordrer'),$this->shop('v2')];
        $this->rows("INSERT INTO ordrer(art,shop_id) VALUES('DK',123),('DO',0)");
        self::assertSame(3,$this->scan()['webshop_order']);
        self::assertSame(array_map(static fn(int $id): string => 'webshop_order:'.$id,$ids),array_column($this->events(),'dedupe_key'));
        self::assertSame('125.00',json_decode($this->events()[0]['payload'],true)['amount']);
        self::assertSame(0,$this->scan()['webshop_order']);
    }

    public function testUnfinishedMappedWebshopOrderWaitsForTotalsBeyondTheWatermark(): void
    {
        $this->scan();
        // hent_ordrer.php commits the header and mapping before its final totals update.
        $order = (int)$this->rows("INSERT INTO ordrer(art,ordrenr,firmanavn,shop_id)
            VALUES('DO',55,'Shop customer',0) RETURNING id")[0]['id'];
        $this->rows('INSERT INTO shop_ordrer(saldi_id,shop_id) VALUES($1,123)',[$order]);
        self::assertSame(0,$this->scan()['webshop_order']);
        self::assertSame([],$this->events('webshop_order'));
        $newer = $this->shop('v2');
        self::assertSame(1,$this->scan($this->now->modify('+15 minutes'))['webshop_order']);
        self::assertSame(0,$this->scan($this->now->modify('+90 minutes'))['webshop_order']);
        $cursor = json_decode($this->rows("SELECT cursor FROM mobile_scanner_state WHERE scanner='webshop_order'")[0]['cursor'],true);
        self::assertGreaterThanOrEqual($newer,$cursor['floor']);
        // A populated net amount alone must still wait for VAT, even below the floor.
        $this->rows('UPDATE ordrer SET sum=100 WHERE id=$1',[$order]);
        self::assertSame(0,$this->scan($this->now->modify('+105 minutes'))['webshop_order']);
        $this->rows('UPDATE ordrer SET moms=25 WHERE id=$1',[$order]);
        self::assertSame(1,$this->scan($this->now->modify('+120 minutes'))['webshop_order']);
        $events = $this->rows('SELECT * FROM mobile_events WHERE dedupe_key=$1',['webshop_order:'.$order]);
        self::assertCount(1,$events);
        self::assertSame('125.00',json_decode($events[0]['payload'],true)['amount']);
        self::assertSame(0,$this->scan($this->now->modify('+135 minutes'))['webshop_order']);
    }

    public function testCompletedZeroWebshopTotalsAreReady(): void
    {
        $this->scan();
        $order = $this->shop('v2');
        $this->rows('UPDATE ordrer SET sum=0,moms=0 WHERE id=$1',[$order]);
        self::assertSame(1,$this->scan()['webshop_order']);
        self::assertSame('0.00',json_decode($this->events()[0]['payload'],true)['amount']);
        self::assertSame(0,$this->scan()['webshop_order']);
    }

    private function item(int $stock, int $minimum = 3, bool $tracked = true): int
    {
        return (int)$this->rows('INSERT INTO varer(varenr,beskrivelse,enhed,beholdning,min_lager,gruppe) VALUES($1,$2,$3,$4,$5,$6) RETURNING id',
            ['ST-'.bin2hex(random_bytes(4)),'Stock fixture','stk',$stock,$minimum,$tracked ? 98761 : 98762])[0]['id'];
    }

    public function testStockCrossingsRecoveryWarehousePrecedenceAndStandardMinimum(): void
    {
        $item = $this->item(10);
        $standard = $this->item(7,0);
        $untracked = $this->item(0,3,false);
        $this->scan();
        $this->rows('INSERT INTO lagerstatus(vare_id,lager,beholdning) VALUES($1,1,1),($1,2,1)',[$item]);
        $this->rows('UPDATE varer SET beholdning=4 WHERE id=$1',[$standard]);
        self::assertSame(2,$this->scan()['low_stock']);
        self::assertSame(0,$this->scan()['low_stock']);
        $this->rows('UPDATE lagerstatus SET beholdning=3 WHERE vare_id=$1',[$item]);
        self::assertSame(0,$this->scan()['low_stock']);
        $this->rows('UPDATE lagerstatus SET beholdning=0 WHERE vare_id=$1',[$item]);
        self::assertSame(1,$this->scan()['low_stock']);
        self::assertContains('low_stock:'.$item.':2',array_column($this->events(),'dedupe_key'));
        self::assertNotContains('low_stock:'.$untracked.':1',array_column($this->events(),'dedupe_key'));
        self::assertSame(2.0,(float)json_decode($this->events('low_stock')[0]['payload'],true)['stock']);
        self::assertSame(10.0,(float)$this->rows('SELECT beholdning FROM varer WHERE id=$1',[$item])[0]['beholdning']);
    }

    public function testNewAlreadyLowItemSeedsQuietlyAndUntrackingCountsAsRecovery(): void
    {
        $this->scan();
        $item = $this->item(1);
        self::assertSame(0,$this->scan()['low_stock']);
        $this->rows('UPDATE varer SET gruppe=98762 WHERE id=$1',[$item]);
        self::assertSame(0,$this->scan()['low_stock']);
        $this->rows('UPDATE varer SET gruppe=98761 WHERE id=$1',[$item]);
        self::assertSame(1,$this->scan()['low_stock']);
    }

    public function testWebMatchResolvesEveryRecipientWithoutStoringMatched(): void
    {
        $import = $this->rows("INSERT INTO bank_line_imports(source,account_kontonr,kladde_id,imported_by) VALUES('csv',5800,31,$1) RETURNING id",[T3EventTestEnv::$subject])[0]['id'];
        $bank = $this->rows("INSERT INTO bank_lines(account_kontonr,line_key,source,transdate,amount,currency,text,first_import_id,state)
            VALUES(5800,$1,'csv',current_date,-100,'DKK','Bank fixture',$2,'requested') RETURNING id",[hash('sha256',random_bytes(8)),$import])[0]['id'];
        $journal = $this->rows("INSERT INTO kassekladde(kladde_id,transdate,beskrivelse,amount) VALUES(31,current_date,'Bank fixture',100) RETURNING id")[0]['id'];
        $this->rows('INSERT INTO bank_line_journal_links(bank_line_id,kassekladde_id,import_id) VALUES($1,$2,$3)',[$bank,$journal,$import]);
        $reminder = (new ReminderService(T3EventTestEnv::$tenant,T3EventTestEnv::$registry,T3EventTestEnv::$tenantId))->create(
            T3EventTestEnv::$subject,['client_id'=>MobileAuth::uuid(),'target'=>'all','message'=>'Missing receipt']);
        $this->rows('UPDATE reminders SET bank_line_id=$1 WHERE id=$2',[$bank,$reminder['id']]);
        $this->scan();
        self::assertSame(0,$this->scan()['resolved']);
        $this->rows("INSERT INTO documents(source,source_id,filename,filepath) VALUES('kassekladde',$1,'fixture.pdf','fixture')",[$journal]);
        self::assertSame(2,$this->scan()['resolved']);
        self::assertSame('requested',$this->rows('SELECT state FROM bank_lines WHERE id=$1',[$bank])[0]['state']);
        self::assertSame(0,$this->scan()['resolved']);
        self::assertCount(0,$this->rows('SELECT * FROM notification_recipients WHERE resolved_at IS NULL'));
    }

    public function testFailureRollsBackEventsCrossingsAndAllScannerState(): void
    {
        $invoice = $this->invoice();
        $item = $this->item(10);
        $this->scan();
        $state = $this->rows('SELECT * FROM mobile_scanner_state ORDER BY scanner');
        $this->settle($invoice,101);
        $this->rows('UPDATE varer SET beholdning=0 WHERE id=$1',[$item]);
        $this->rows("CREATE FUNCTION scanner_failure() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'injected scanner failure'; END \$\$");
        $this->rows("CREATE TRIGGER scanner_failure BEFORE INSERT ON mobile_low_stock_state FOR EACH ROW WHEN (NEW.below=true) EXECUTE FUNCTION scanner_failure()");
        try {
            $this->scan();
            self::fail('Injected event failure must escape');
        } catch (RuntimeException $error) {
            self::assertSame('Scanner database command failed',$error->getMessage());
        } finally {
            $this->rows('DROP TRIGGER scanner_failure ON mobile_low_stock_state');
            $this->rows('DROP FUNCTION scanner_failure()');
        }
        self::assertSame([],$this->events());
        self::assertSame($state,$this->rows('SELECT * FROM mobile_scanner_state ORDER BY scanner'));
        self::assertSame('0',$this->rows('SELECT crossings FROM mobile_low_stock_state WHERE vare_id=$1',[$item])[0]['crossings']);
        self::assertSame(1,$this->scan()['invoice_paid']);
        self::assertSame(1,count($this->events('low_stock')));
    }

    public function testOverlappingWorkerReturnsBusyAndCallerTransactionIsRejected(): void
    {
        $other = RestApiEnv::connect(RestApiEnv::testDb());
        try {
            $this->rows('BEGIN',[],$other);
            $this->rows("SELECT pg_advisory_xact_lock(hashtext(current_database()),hashtext('mobile_scanners_worker'))",[],$other);
            self::assertTrue($this->scan()['busy']);
            self::assertSame([],$this->rows('SELECT * FROM mobile_scanner_state'));
            $this->rows('ROLLBACK',[],$other);
            $this->rows('BEGIN');
            try {
                $this->scan();
                self::fail('Caller transaction must be refused');
            } catch (LogicException $error) {
                self::assertSame(PGSQL_TRANSACTION_INTRANS,pg_transaction_status(T3EventTestEnv::$tenant));
            } finally {
                $this->rows('ROLLBACK');
            }
        } finally {
            pg_close($other);
        }
    }

    public function testLargestPaymentFixtureFindsEveryInvoiceAndRerunsQuietly(): void
    {
        $this->scan();
        $this->rows("INSERT INTO ordrer(art,konto_id,kontonr,firmanavn,ordrenr,fakturanr,fakturadate,sum,moms,valuta,status)
            SELECT 'DO',$1,'SC1','Load customer',20000+n,(20000+n)::text,current_date,100,0,'DKK',4
            FROM generate_series(1,1200) n",[$this->customer]);
        $this->rows("INSERT INTO openpost(konto_id,faktnr,amount,udlignet,transdate,kladde_id,refnr,udlign_id,valuta,valutakurs)
            SELECT konto_id,fakturanr,100,'1',current_date,0,id,ordrenr,'DKK',100 FROM ordrer");
        $this->rows("INSERT INTO openpost(konto_id,faktnr,amount,udlignet,transdate,kladde_id,udlign_id,valuta,valutakurs)
            SELECT konto_id,fakturanr,-100,'1',current_date,31,ordrenr,'DKK',100 FROM ordrer");
        $started = microtime(true);
        self::assertSame(1200,$this->scan()['invoice_paid']);
        fwrite(STDERR,sprintf("Scanner payment fixture: 1200 invoices, %.3f seconds\n",microtime(true)-$started));
        self::assertSame(0,$this->scan()['invoice_paid']);
        self::assertCount(1200,$this->events());
    }

    public function testLargestStockFixtureSeedsAndCrossesEachItemOnce(): void
    {
        $this->rows("INSERT INTO varer(varenr,beskrivelse,enhed,beholdning,min_lager,gruppe)
            SELECT 'LOAD-'||n,'Load fixture','stk',10,3,98761 FROM generate_series(1,1200) n");
        $this->scan();
        self::assertSame([],$this->events());
        $this->rows('UPDATE varer SET beholdning=2');
        $started = microtime(true);
        self::assertSame(1200,$this->scan()['low_stock']);
        fwrite(STDERR,sprintf("Scanner stock fixture: 1200 items, %.3f seconds\n",microtime(true)-$started));
        self::assertSame(0,$this->scan()['low_stock']);
        self::assertCount(1200,$this->events());
    }

    public function testCronIsCliOnlyAndSkipsTenantsWithoutLiveSessions(): void
    {
        $http = RestApiEnv::http('GET','/cron/mobile_scanners.php');
        self::assertSame(403,$http['status']);
        $log = tempnam('/tmp','scanner-cron-');
        $command = [PHP_BINARY,dirname(__DIR__,2).'/cron/mobile_scanners.php','--tenant-id='.T3EventTestEnv::$tenantId];
        $run = static function () use ($command,$log): array {
            $process = proc_open($command,[0=>['pipe','r'],1=>['file',$log,'w'],2=>['file',$log.'.err','w']],$pipes);
            fclose($pipes[0]);
            return [proc_close($process),file_get_contents($log),file_get_contents($log.'.err')];
        };
        try {
            [$code,$output,$errors] = $run();
            self::assertSame(0,$code,$errors);
            self::assertSame(5,json_decode(trim($output),true)['seeded']);
            self::assertSame([],$this->events());
            $this->rows('DELETE FROM refresh_sessions WHERE tenant_id=$1',[T3EventTestEnv::$tenantId],T3EventTestEnv::$registry);
            [$code,$output,$errors] = $run();
            self::assertSame(0,$code,$errors);
            self::assertSame('',$output);
        } finally {
            unlink($log);
            unlink($log.'.err');
        }
    }
}
