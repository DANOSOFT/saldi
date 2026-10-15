<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/T2GoldenAccountingTest.php --- ver 5.0.0 --- 2026.10.05
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

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
require_once __DIR__ . '/support/T2Seed.php';

/** Fixed monetary expectations exercised through real REST handlers and the stub webhook. */
// A fresh process also gives each golden a fresh tenant name and engine-log directory.
// Shared CLI/web log ownership from earlier REST files must not leak into these accounting baselines.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class T2GoldenAccountingTest extends TestCase
{
    private ?T2Seed $s = null;

    protected function setUp(): void
    {
        if (($reason=RestApiEnv::unavailableReason()) !== null) {
            $this->markTestSkipped($reason);
        }
        $this->s = new T2Seed(true);
    }

    protected function tearDown(): void
    {
        $this->s?->close();
        $this->s = null;
    }

    private function golden(string $file): array
    {
        return json_decode(file_get_contents(__DIR__.'/fixtures/t2/golden/'.$file),true,512,JSON_THROW_ON_ERROR);
    }

    private function ok(string $method,string $path,?array $body=null,array $headers=[]): array
    {
        $r = $this->s->request($method,$path,$body,'full',$headers);
        self::assertContains($r['status'],[200,201],$r['body']);
        self::assertTrue($r['json']['success']);
        return $r['json']['data'];
    }

    private function finalize(array $order,string $date): array
    {
        return $this->ok('POST','debitor/sales/transition.php',['client_id'=>T2Seed::uuid(),
            'order_id'=>$order['id'],'to'=>'faktura','invoice_date'=>$date],['If-Match: '.$order['etag']]);
    }

    private function ledger(string $where,array $params): array
    {
        return array_map(static fn(array $r): array=>['account'=>(int)$r['kontonr'],
            'debit'=>T2Seed::money($r['debet']),'credit'=>T2Seed::money($r['kredit'])],
            $this->s->sql('SELECT kontonr,sum(debet) AS debet,sum(kredit) AS kredit FROM transaktioner WHERE '.$where.' GROUP BY kontonr ORDER BY kontonr',$params));
    }

    private function balances(array $accounts,int $year): array
    {
        return $this->s->sql('SELECT kontonr,regnskabsaar,saldo,primo FROM kontoplan WHERE kontonr=ANY($1::integer[]) AND regnskabsaar IN ($2,$3) ORDER BY regnskabsaar,kontonr',
            ['{'.implode(',',$accounts).'}',$year,$year+1]);
    }

    private function deltas(array $before,array $after): array
    {
        self::assertCount(count($before),$after);
        $deltas = [];
        foreach ($before as $i=>$row) {
            self::assertSame([$row['kontonr'],$row['regnskabsaar']],[$after[$i]['kontonr'],$after[$i]['regnskabsaar']]);
            $deltas[$row['regnskabsaar']][$row['kontonr']] = ['saldo'=>T2Seed::money((float)$after[$i]['saldo']-(float)$row['saldo']),
                'primo'=>T2Seed::money((float)$after[$i]['primo']-(float)$row['primo'])];
        }
        return $deltas;
    }

    private function assertInvoiceRows(array $order,array $golden): void
    {
        $row = $this->s->sql('SELECT status,fakturanr,sum,moms FROM ordrer WHERE id=$1',[$order['id']])[0];
        self::assertSame($golden['status'],(int)$row['status']);
        self::assertNotEmpty($row['fakturanr']);
        self::assertSame($order['fakturanr'],$row['fakturanr']);
        self::assertSame([$golden['totals']['net'],$golden['totals']['vat']],
            [T2Seed::money($row['sum']),T2Seed::money($row['moms'])]);
        $posts = $this->s->sql('SELECT amount,refnr,konto_id,udlignet FROM openpost WHERE refnr=$1',[$order['id']]);
        self::assertCount(1,$posts);
        self::assertSame($golden['totals']['gross'],T2Seed::money($posts[0]['amount']));
        self::assertSame($order['konto']['id'],(int)$posts[0]['konto_id']);
        self::assertSame('0',$posts[0]['udlignet']);
    }

    public function testG1PercentDiscountInvoice(): void
    {
        $g = $this->golden('G1-invoice.json');
        $f = $this->s->sale();
        $body = $this->s->saleBody($f);
        $beforeQuote = $this->postingState();
        $q = $this->ok('POST','debitor/sales/quote.php',$body);
        self::assertSame($beforeQuote,$this->postingState(),'A quote must roll back every business row');
        self::assertSame($g['totals'],$q['totals']);
        $o = $this->ok('POST','debitor/sales/index.php',$body);
        self::assertSame($q['totals'],$o['totals']);
        $before = $this->balances([7861,7900,7901],$f['year']);
        $number = (int)$this->s->sql("SELECT COALESCE(max(fakturanr::bigint),0)+1 AS n FROM ordrer WHERE art IN ('DO','DK') AND fakturanr ~ '^[0-9]+$'")[0]['n'];
        $o = $this->finalize($o,T2Seed::today());
        self::assertSame((string)$number,$o['fakturanr']);
        self::assertTrue($o['posted']);
        self::assertSame($g['totals'],$o['totals']);
        self::assertSame($g['ledger'],$this->ledger('ordre_id=$1',[$o['id']]));
        $this->assertInvoiceRows($o,$g);
        $p = $this->ok('GET','debitor/open-posts/index.php?id='.$o['openpost_id']);
        self::assertSame($g['totals']['gross'],$p['rest']);
        $d = $this->deltas($before,$this->balances([7861,7900,7901],$f['year']));
        foreach ($g['saldo_delta'] as $account=>$expected) {
            self::assertSame($expected,$d[$f['year']][$account]['saldo']);
        }
    }

    public function testG2MixedVatExemptAndAmountDiscount(): void
    {
        $g = $this->golden('G2-mixed-vat.json');
        $f = $this->s->sale();
        // Amount discounts are web-only inputs. Read/finalize this persisted web-shaped order through REST.
        $this->s->sql("UPDATE ordrelinjer SET rabatart='amount',rabat=10 WHERE id=$1",[$f['line_id']]);
        foreach ([7862=>12.5,7863=>0] as $group=>$rate) {
            $this->s->sql("INSERT INTO grupper(art,kodenr,fiscal_year,box1,box2,box4,box7,box8) VALUES
                ('VG',$1::integer,$2,'','',($1::integer)::text,$3,'on'),('SM',$1,$2,'7901',$4,'','','')",[$group,$f['year'],$rate===0 ? 'on' : '',(string)$rate]);
            $this->s->sql("INSERT INTO kontoplan(kontonr,beskrivelse,kontotype,regnskabsaar,moms) VALUES($1,'Mixed VAT','D',$2,$3)",[$group,$f['year'],$rate===0 ? '' : 'S'.$group]);
            $product = (int)$this->s->sql("INSERT INTO varer(varenr,beskrivelse,enhed,salgspris,kostpris,gruppe,beholdning) VALUES($1,'Mixed VAT','stk',$2,30,$3,20) RETURNING id",['X1-'.$group,$rate===0 ? 50 : 100,$group])[0]['id'];
            $this->s->sql('INSERT INTO batch_kob(ordre_id,linje_id,vare_id,antal,rest,lager,kobsdate,pris) VALUES(0,0,$1,20,20,1,CURRENT_DATE,30)',[$product]);
            $this->s->sql('INSERT INTO lagerstatus(vare_id,lager,beholdning) VALUES($1,1,20)',[$product]);
            SalesFixture::line($this->s->tenant,[RestApiEnv::class,'rows'],$f,['posnr'=>$group-7860,'vare_id'=>$product,
                'varenr'=>'X1-'.$group,'antal'=>1,'pris'=>$rate===0 ? 50 : 100,'rabat'=>0,'momssats'=>$rate,
                'momsfri'=>$rate===0 ? 'on' : '', 'bogf_konto'=>$group,'vat_account'=>$rate===0 ? 0 : 7901,'leveres'=>1]);
        }
        $this->s->sql('UPDATE kontoplan SET saldo=COALESCE(saldo,0),primo=COALESCE(primo,0)');
        $this->s->sql('UPDATE ordrer SET sum=330,moms=57.5,kostpris=120 WHERE id=$1',[$f['order_id']]);
        $o = $this->ok('GET','debitor/sales/index.php?id='.$f['order_id']);
        self::assertFalse($o['editable']);
        self::assertSame($g['totals'],$o['totals']);
        $before = $this->balances([7861,7862,7863,7900,7901],$f['year']);
        $o = $this->finalize($o,T2Seed::today());
        $this->assertInvoiceRows($o,$g);
        self::assertSame($g['totals'],$o['totals']);
        self::assertSame($g['ledger'],$this->ledger('ordre_id=$1',[$o['id']]));
        self::assertSame($g['totals']['gross'],$this->ok('GET','debitor/open-posts/index.php?id='.$o['openpost_id'])['rest']);
        $d = $this->deltas($before,$this->balances([7861,7862,7863,7900,7901],$f['year']));
        foreach ($g['saldo_delta'] as $account=>$expected) {
            self::assertSame($expected,$d[$f['year']][$account]['saldo']);
        }
    }

    public function testG3WebCreditNoteInAging(): void
    {
        $g = $this->golden('G3-credit-note.json');
        $f = $this->s->sale();
        $this->s->sql("UPDATE ordrer SET art='DK',sum=-180,moms=-45,kostpris=-60 WHERE id=$1",[$f['order_id']]);
        $this->s->sql('UPDATE ordrelinjer SET antal=-2,leveres=-2 WHERE id=$1',[$f['line_id']]);
        $before = $this->balances([7861,7900,7901],$f['year']);
        $this->s->webInvoice($f['order_id'],$f['year']);
        $o = $this->ok('GET','debitor/sales/index.php?id='.$f['order_id']);
        self::assertSame('kreditnota',$o['kind']);
        $this->assertInvoiceRows($o,$g);
        self::assertSame($g['totals'],$o['totals']);
        self::assertSame($g['ledger'],$this->ledger('ordre_id=$1',[$o['id']]));
        $p = $this->ok('GET','debitor/open-posts/index.php?konto_id='.$f['konto_id']);
        self::assertSame($g['open_total'],$p['summary']['open_total']);
        self::assertSame('credit_note',$p['items'][0]['type']);
        self::assertSame($g['open_total'],$p['items'][0]['rest']);
        $d = $this->deltas($before,$this->balances([7861,7900,7901],$f['year']));
        foreach ($g['saldo_delta'] as $account=>$expected) {
            self::assertSame($expected,$d[$f['year']][$account]['saldo']);
        }
    }

    /** Seed summary accounts and the full following-year chart; expected deltas remain independent constants. */
    private function paymentChart(): array
    {
        $f = PaymentLinkTestEnv::$fixture;
        $year = $f['year'];
        $this->s->sql("INSERT INTO kontoplan(kontonr,beskrivelse,kontotype,regnskabsaar,fra_kto,saldo,primo) VALUES
            (980000,'X1 Z','Z',$1,$2,0,0),(980001,'X1 R','R',$1,980000,0,0)",[$year,$f['bank']]);
        $this->s->sql("INSERT INTO grupper(art,kodenr,box1,box2,box3,box4,box10) VALUES ('RA',$1,'1','2027','12','2027','')",[$year+1]);
        $columns = $this->s->sql("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name='kontoplan' AND column_name<>'id' ORDER BY ordinal_position");
        $names = array_map(fn(array $r): string=>pg_escape_identifier($this->s->tenant,$r['column_name']),$columns);
        $select = array_map(fn(array $r): string=>$r['column_name']==='regnskabsaar' ? (string)($year+1) : pg_escape_identifier($this->s->tenant,$r['column_name']),$columns);
        $this->s->sql('INSERT INTO kontoplan('.implode(',',$names).') SELECT '.implode(',',$select).' FROM kontoplan WHERE regnskabsaar=$1',[$year]);
        return $this->balances([$f['bank'],$f['collective'],980000,980001],$year);
    }

    private function paymentDelta(array $before,array $g): void
    {
        $f = PaymentLinkTestEnv::$fixture;
        $d = $this->deltas($before,$this->balances([$f['bank'],$f['collective'],980000,980001],$f['year']));
        foreach ([980000=>'Z',980001=>'R'] as $account=>$kind) {
            self::assertSame($g['summary_saldo_delta'][$kind],$d[$f['year']][$account]['saldo']);
        }
        foreach (['bank'=>$f['bank'],'debtor'=>$f['collective']] as $kind=>$account) {
            self::assertSame($g['following_year_primo_delta'][$kind],$d[$f['year']+1][$account]['primo']);
            self::assertSame($g[$kind.'_saldo_delta'] ?? $g['following_year_primo_delta'][$kind],$d[$f['year']][$account]['saldo']);
        }
    }

    public function testG4PartialPartialFinalPayment(): void
    {
        $g = $this->golden('G4-payments.json');
        $before = $this->paymentChart();
        $post = $this->s->invoice();
        foreach ([['400.00','600.00'],['300.00','300.00'],['300.00','0.00']] as [$amount,$rest]) {
            $r = $this->ok('POST','payments/register.php',['client_id'=>T2Seed::uuid(),'openpost_id'=>$post,
                'amount'=>$amount,'method'=>'bank','date'=>$g['settlement_date']]);
            self::assertSame($rest,$r['rest']);
            self::assertSame($rest==='0.00',$r['aligned']);
        }
        $rows = $this->paymentRows($post);
        self::assertSame($g['openpost_amounts'],array_column($rows,'amount'));
        self::assertSame(['1'],array_values(array_unique(array_column($rows,'udlignet'))));
        self::assertCount(1,array_unique(array_column($rows,'udlign_id')));
        self::assertGreaterThan(0,(int)$rows[0]['udlign_id']);
        self::assertSame([$g['settlement_date']],array_values(array_unique(array_column($rows,'udlign_date'))));
        $f = PaymentLinkTestEnv::$fixture;
        self::assertSame([['account'=>$f['collective'],'debit'=>'0.00','credit'=>'1000.00'],
            ['account'=>$f['bank'],'debit'=>'1000.00','credit'=>'0.00']],$this->ledger('kladde_id IN (SELECT kladde_id FROM payment_registrations WHERE openpost_id=$1)',[$post]));
        $this->paymentDelta($before,$g);
    }

    private function paymentRows(int $post): array
    {
        return array_map(static function (array $row): array {
            $row['amount'] = T2Seed::money($row['amount']);
            return $row;
        },$this->s->sql('SELECT amount,udlignet,udlign_id,udlign_date FROM openpost WHERE konto_id=(SELECT konto_id FROM openpost WHERE id=$1) AND faktnr=(SELECT faktnr FROM openpost WHERE id=$1) ORDER BY id',[$post]));
    }

    public function testG5EurPlaceholderRateAndRefusedPayment(): void
    {
        $g = $this->golden('G5-eur.json');
        $post = $this->s->invoice('100.00','EUR');
        $this->s->sql("DELETE FROM grupper WHERE art='VK' AND box1='EUR'");
        $this->s->sql("INSERT INTO grupper(art,kodenr,box1) VALUES('VK',9921,'EUR')");
        $this->s->sql("INSERT INTO valuta(gruppe,valdate,kurs) VALUES(9921,'2020-01-01',745)");
        $p = $this->ok('GET','debitor/open-posts/index.php?id='.$post);
        self::assertSame($g['rest'],$p['rest']);
        self::assertSame('EUR',$p['original_currency']);
        $before = $this->postingState();
        $this->refused('payments/register.php',['client_id'=>T2Seed::uuid(),'openpost_id'=>$post,'amount'=>'100.00',
            'method'=>'bank','date'=>'2026-03-15'],'currency_not_supported');
        self::assertSame($before,$this->postingState());
    }

    private function refused(string $path,array $body,string $reason,array $headers=[]): void
    {
        $r = $this->s->request('POST',$path,$body,'full',$headers);
        self::assertSame(422,$r['status'],$r['body']);
        self::assertSame('validation_failed',$r['json']['data']['error']);
        self::assertSame($reason,$r['json']['data']['details']['reason']);
    }

    private function postingState(): array
    {
        $state = [];
        foreach (['ordrer','ordrelinjer','openpost','transaktioner','kontoplan','kassekladde','kladdeliste',
            'payment_registrations','mobile_idempotency','batch_salg','batch_kob','reservation','lagerstatus','varer'] as $table) {
            $state[$table] = $this->s->sql("SELECT * FROM $table ORDER BY ".($table==='reservation' ? 'linje_id,batch_kob_id,batch_salg_id' : 'id'));
        }
        return $state;
    }

    public function testG6NonCalendarYearAndLockedPeriodAreAtomic(): void
    {
        $g = $this->golden('G6-payment-period.json');
        $f = $this->s->sale();
        $beforeBalances = $this->paymentChart();
        $post = $this->s->invoice();
        $this->s->sql("UPDATE grupper SET box1='7',box2='2025',box3='6',box4='2026' WHERE art='RA' AND kodenr=$1",[$f['year']]);
        $this->s->sql('UPDATE ordrer SET ordredate=\'2026-06-15\',levdate=\'2026-06-15\',fakturadate=\'2026-06-15\' WHERE id=$1',[$f['order_id']]);
        // The current date is outside this non-calendar year; fetch uses the persisted order date.
        $o = $this->ok('GET','debitor/sales/index.php?id='.$f['order_id']);
        foreach ([$g['refused_date']=>'outside_fiscal_year','2026-06-15'=>'period_locked'] as $date=>$reason) {
            if ($reason==='period_locked') {
                $this->s->sql("INSERT INTO moms_periode_luk(kalender_aar,kalender_maaned,status) VALUES(2026,6,'closed')");
            }
            $before = $this->postingState();
            $this->refused('payments/register.php',['client_id'=>T2Seed::uuid(),'openpost_id'=>$post,'amount'=>'1000.00',
                'method'=>'bank','date'=>$date],$reason);
            $this->refused('debitor/sales/transition.php',['client_id'=>T2Seed::uuid(),'order_id'=>$o['id'],'to'=>'faktura','invoice_date'=>$date],
                $reason,['If-Match: '.$o['etag']]);
            self::assertSame($before,$this->postingState());
        }
        $this->paymentDelta($beforeBalances,$g);
    }

    public function testG8R1AndR2PreviewMatchesPersistedRows(): void
    {
        $g = $this->golden('G8-dunning.json');
        $f = DunningFixture::seed($this->s->tenant);
        // Relative dates pin the interest interval independently of the date the test runs.
        $this->s->sql("UPDATE openpost SET transdate=(date_trunc('month',CURRENT_DATE)-interval '1 month 10 days')::date,forfaldsdate=(date_trunc('month',CURRENT_DATE)-interval '1 month')::date WHERE konto_id=$1",[$f['konto_id']]);
        foreach ([1,2] as $level) {
            $before = $this->postingState();
            $p = $this->ok('GET','debitor/dunning/preview.php?konto_id='.$f['konto_id']);
            self::assertSame($before,$this->postingState());
            $c = $this->ok('POST','debitor/dunning/index.php',['client_id'=>T2Seed::uuid(),'konto_id'=>$f['konto_id'],
                'expected_level'=>$level,'deliver'=>'none']);
            self::assertSame($level,$p['level']);
            self::assertSame($p['total'],$c['total']);
            self::assertSame($g['levels'][(string)$level]['fee'],$p['fee']);
            self::assertSame($g['levels'][(string)$level]['interest'],$p['interest']);
            self::assertSame($g['levels'][(string)$level]['total'],$p['total']);
            $o = $this->s->sql('SELECT art,status,sum,moms FROM ordrer WHERE id=$1',[$c['rykker_id']])[0];
            self::assertSame('R'.$level,$o['art']);
            self::assertSame('2',$o['status']);
            self::assertSame($g['levels'][(string)$level]['order_net'],T2Seed::money($o['sum']));
            self::assertSame('0.00',T2Seed::money($o['moms']));
            $lines = $this->s->sql('SELECT vare_id,antal,pris FROM ordrelinjer WHERE ordre_id=$1 AND vare_id>0 ORDER BY posnr',[$c['rykker_id']]);
            $normalized = array_map(static fn(array $line): array => [
                'kind'=>(int)$line['vare_id']===$f['fee_id'] ? 'fee' : 'interest',
                'quantity'=>T2Seed::money($line['antal']),'price'=>T2Seed::money($line['pris'])],$lines);
            usort($normalized,static fn(array $a,array $b): int => strcmp($a['kind'],$b['kind']));
            self::assertSame($g['levels'][(string)$level]['lines'],$normalized);
            self::assertSame($before['openpost'],$this->postingState()['openpost'],'Unposted reminders leave receivables unchanged');
            self::assertSame($before['kontoplan'],$this->postingState()['kontoplan']);
            self::assertSame([],$this->ledger('ordre_id=$1',[$c['rykker_id']]));
            if ($level===1) {
                $this->s->sql("UPDATE ordrer SET ordredate=date_trunc('month',CURRENT_DATE)::date-10,fakturadate=date_trunc('month',CURRENT_DATE)::date WHERE id=$1",[$c['rykker_id']]);
            }
        }
    }

    public function testG9NumberedButUnpostedInvoiceIsNotPayable(): void
    {
        $g = $this->golden('G9-unposted.json');
        $f = $this->s->sale(false);
        $o = $this->ok('POST','debitor/sales/index.php',$this->s->saleBody($f));
        $before = $this->balances([7861,7900,7901],$f['year']);
        $o = $this->finalize($o,T2Seed::today());
        self::assertFalse($o['posted']);
        self::assertNull($o['openpost_id']);
        self::assertNotEmpty($o['fakturanr']);
        self::assertSame($g['status'],$o['status']);
        self::assertSame($g['totals'],$o['totals']);
        self::assertSame([],$this->ledger('ordre_id=$1',[$o['id']]));
        self::assertSame([],$this->s->sql('SELECT * FROM openpost WHERE refnr=$1',[$o['id']]));
        self::assertSame($before,$this->balances([7861,7900,7901],$f['year']));
        $r = $this->s->request('POST','debitor/sales/deliver.php',['client_id'=>T2Seed::uuid(),'order_id'=>$o['id'],'channel'=>'email','attach_payment_link'=>true]);
        self::assertSame(422,$r['status'],$r['body']);
        self::assertSame('not_payable',$r['json']['data']['details']['reason']);
        self::assertSame([],$this->s->sql('SELECT * FROM payment_links'));
    }

    public function testG10OverviewReconcilesWithAllGoldenTenantOpenPosts(): void
    {
        $g = $this->golden('G10-overview.json');
        $this->s->invoice('1000.00');
        $this->s->invoice('-225.00');
        $p = $this->ok('GET','debitor/open-posts/index.php');
        $o = $this->ok('GET','dashboard/overview.php');
        self::assertSame($g['overdue_total'],$p['summary']['overdue_total']);
        self::assertSame($p['summary']['overdue_total'],T2Seed::money($o['overdue']['amount']));
        self::assertSame($p['summary']['overdue_count'],$o['overdue']['count']);
        self::assertSame($g['open_total'],$p['summary']['open_total']);
        self::assertSame((float)$p['summary']['open_total'],array_sum(array_map('floatval',$p['summary']['buckets'])));
    }

    /** G7 deliberately runs last, after G1-G6 and G8-G10. */
    public function testG7CapturedWebhookEqualsManualMobilepay(): void
    {
        $g = $this->golden('G7-payment-link.json');
        $before = $this->paymentChart();
        $manualPost = $this->s->invoice();
        $manual = $this->ok('POST','payments/register.php',['client_id'=>T2Seed::uuid(),'openpost_id'=>$manualPost,
            'amount'=>$g['amount'],'method'=>'mobilepay','date'=>T2Seed::today()]);
        $manualLedger = $this->ledger('kladde_id=$1',[$manual['kladde_id']]);
        self::assertSame($g['ledger_amounts'],array_map(static fn(array $row): array=>[$row['debit'],$row['credit']],$manualLedger));
        $this->paymentDelta($before,$g);
        $before = $this->balances([PaymentLinkTestEnv::$fixture['bank'],PaymentLinkTestEnv::$fixture['collective'],980000,980001],PaymentLinkTestEnv::$fixture['year']);
        $post = $this->s->invoice();
        $link = $this->ok('POST','payments/links.php',['client_id'=>T2Seed::uuid(),'openpost_id'=>$post,'provider'=>'vipps_epayment']);
        // Use the public page's actual GET/POST CSRF flow to start the local provider attempt.
        $local = RestApiEnv::baseUrl().'/debitor/payments/link/betal.php?'.parse_url($link['url'],PHP_URL_QUERY);
        $cookie = tempnam(sys_get_temp_dir(),'x1-cookie-');
        try {
            $ch = curl_init($local);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>40]);
            $page = curl_exec($ch);
            self::assertSame(200,(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE),(string)$page);
            curl_close($ch);
            self::assertSame(1,preg_match('/name="csrf" value="([^"]+)"/',(string)$page,$match));
            $ch = curl_init($local);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>http_build_query(['csrf'=>$match[1]]),CURLOPT_TIMEOUT=>40]);
            $page = curl_exec($ch);
            self::assertSame(303,(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE),(string)$page);
            curl_close($ch);
        } finally {
            unlink($cookie);
        }
        $attempt = $this->s->sql('SELECT * FROM payment_attempts WHERE link_id=$1',[$link['id']])[0];
        PaymentSettlementTestEnv::remote($attempt,'CAPTURED',(int)$attempt['amount_minor']);
        $event = PaymentSettlementTestEnv::event($attempt);
        self::assertSame(200,PaymentSettlementTestEnv::webhook($event)['status']);
        $deadline = microtime(true)+10;
        do {
            $status = $this->ok('GET','payments/links.php?id='.$link['id']);
            if ($status['state']==='paid') {
                break;
            }
            usleep(100000);
        } while (microtime(true)<$deadline);
        self::assertSame('paid',$status['state']);
        self::assertSame(200,PaymentSettlementTestEnv::webhook($event)['status']);
        $registrations = $this->s->sql('SELECT * FROM payment_registrations WHERE openpost_id=$1',[$post]);
        self::assertCount(1,$registrations);
        self::assertSame($manualLedger,$this->ledger('kladde_id=$1',[$registrations[0]['kladde_id']]));
        self::assertSame($g['openpost_amounts'],array_column($this->paymentRows($post),'amount'));
        self::assertSame('0.00',$this->ok('GET','debitor/open-posts/index.php?id='.$post)['rest']);
        $this->paymentDelta($before,$g);
        self::assertCount(1,$this->s->sql("SELECT * FROM notifications WHERE type='invoice_paid' AND ref_id=$1",[$post]));
    }
}
