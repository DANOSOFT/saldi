<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/T3GoldenDraftTest.php --- ver 5.0.0 --- 2026.10.06
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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/T3Seed.php';

/** D1–D14 use the immutable B2b goldens and the actual web save/posting engines. */
final class T3GoldenDraftTest extends TestCase
{
    private static ?T3Seed $seed = null;
    public static function setUpBeforeClass(): void
    {
        if ($reason = RestApiEnv::unavailableReason()) {
            self::markTestSkipped($reason);
        }
        self::$seed = new T3Seed();
    }
    public static function tearDownAfterClass(): void
    {
        self::$seed?->close();
        self::$seed = null;
    }
    private function receipt(array $values=[]): array { return self::$seed->receipt($values); }
    private function row(int $id): array { return self::$seed->row($id); }
    private function journal(string $posted='-'): int { return self::$seed->journal($posted); }
    private function bankReceipt(string $posted='-'): array { return self::$seed->bankReceipt($posted); }
    private function approve(array $row,array $fields=[]): array
    {
        return self::$seed->request('POST','attachment/approve.php',
            $fields+['id'=>(int)$row['id'],'client_id'=>T3Seed::uuid()], 'full', ['If-Match: "' . pool_review_version($row) . '"']);
    }
    private function web(string $runner,array $args): array
    {
        $result = self::$seed->web($runner,$args);
        $this->assertSame(0,$result['exit'],$result['stdout'] . $result['stderr']);
        $this->assertStringNotContainsString('PHP Fatal error:',$result['stderr']);
        return $result;
    }
    private function accounting(): array
    {
        $result = [];
        foreach (['kassekladde','transaktioner','openpost'] as $table) {
            $result[$table] = self::$seed->sql('SELECT * FROM ' . $table . ' ORDER BY id');
        }
        $result['saldo'] = self::$seed->sql('SELECT id,saldo FROM kontoplan ORDER BY id');
        return $result;
    }
    public static function goldenCases(): array
    {
        return ['D1'=>['D1',[],[]],'D2'=>['D2',[],[]],'D3'=>['D3',['currency'=>'EUR'],[]],
            'D9'=>['D9',['amount'=>'-125,00'],[]],
            'D10'=>['D10',[],['vat_code'=>'K988']], 'D11'=>['D11',[],['vat_exempt'=>true]],
            'D12'=>['D12',['currency'=>'EUR'],['vat_code'=>'E989']],
            'D13'=>['D13',['amount'=>'-125,00'],['vat_code'=>'K988']]];
    }
    private function golden(string $name,array $actual): void
    {
        $path=__DIR__.'/fixtures/t3/golden/'.$name.'.json';
        $this->assertSame(json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR),$actual,$name);
    }
    private function posting(int $journal): array
    {
        $before=RestApiEnv::rows(self::$seed->tenant,'SELECT kontonr,saldo FROM kontoplan WHERE regnskabsaar=$1 ORDER BY kontonr',[self::$seed->year]);
        $result=$this->web('run_bogfor_page.php',['bogfor',$journal,self::$seed->session]);
        $rows=RestApiEnv::rows(self::$seed->tenant,'SELECT kontonr,debet,kredit,moms,beskrivelse,faktura,valuta,valutakurs FROM transaktioner WHERE kladde_id=$1 ORDER BY id',[$journal]);
        $this->assertNotEmpty($rows,$result['stdout'].$result['stderr']);
        $open=RestApiEnv::rows(self::$seed->tenant,'SELECT konto_nr,faktnr,amount,transdate,valuta,valutakurs FROM openpost WHERE kladde_id=$1 ORDER BY id',[$journal]);
        $after=RestApiEnv::rows(self::$seed->tenant,'SELECT kontonr,saldo FROM kontoplan WHERE regnskabsaar=$1 ORDER BY kontonr',[self::$seed->year]);
        $deltas=[];
        foreach ($after as $i=>$row) {
            $delta=round((float)$row['saldo']-(float)$before[$i]['saldo'],3);
            if ($delta != 0) { $deltas[]=['kontonr'=>(int)$row['kontonr'],'delta'=>number_format($delta,3,'.','')]; }
        }
        return ['transaktioner'=>$rows,'openpost'=>$open,'saldo_delta'=>$deltas];
    }
    #[DataProvider('goldenCases')]
    public function testGoldenDraftMatchesWebSaveAndRealWebPosting(string $case,array $metadata,array $decision): void
    {
        RestApiEnv::rows(self::$seed->tenant,"DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        if ($case === 'D2') { [$row,$journal,$lineId]=$this->bankReceipt(); }
        else { $row=$this->receipt($metadata); }
        $before=$this->accounting();
        $response=$this->approve($row,$decision);
        $this->assertSame(200,$response['status'],$response['body']);
        $draft=$response['json']['data']['draft'];
        $line=RestApiEnv::rows(self::$seed->tenant,'SELECT * FROM kassekladde WHERE id=$1',[$draft['kassekladde_id']])[0];
        foreach (['transaktioner','openpost','saldo'] as $table) { $this->assertSame($before[$table],$this->accounting()[$table],$case.' approval never posts'); }
        if ($case === 'D3' || $case === 'D12') {
            $provenance=RestApiEnv::rows(self::$seed->tenant,'SELECT rate,rate_date,rate_source FROM mobile_journal_lines WHERE pool_file_id=$1',[$row['id']])[0];
            $this->assertSame(['rate'=>'746.456000','rate_date'=>'2026-06-01','rate_source'=>'saldi_valuta'],$provenance);
        }
        $webJournal=$this->journal();
        $webLine=(int)RestApiEnv::rows(self::$seed->tenant,"INSERT INTO kassekladde(kladde_id,bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,valuta,valutakurs,pos,debetvat,kreditvat,momsfri) VALUES ($1,$2,'2026-09-14','Original','F',987001,$3,$4,10,0,100,1,'K987','','') RETURNING id",[$webJournal,$line['bilag'],$case === 'D2' ? 'F' : 'K',$case === 'D2' ? 987010 : 987003])[0]['id'];
        $form=['save'=>'on','antal_ny'=>1,'antal_ex'=>1,'fokus'=>'besk1','tidspkt'=>microtime(),'id'=>[1=>$webLine],
            'bila1'=>$line['bilag'],'dato1'=>'14-09-2026','besk1'=>'T3 purchase','d_ty1'=>'F','debe1'=>'987001',
            'k_ty1'=>$case === 'D2' ? 'F' : 'K','kred1'=>$case === 'D2' ? '987010' : '987003',
            'belo1'=>$metadata['amount'] ?? '125,00','fakt1'=>'INV-T3','valu1'=>$metadata['currency'] ?? 'DKK',
            'afd_1'=>'','proj1'=>'','meda1'=>'','forf1'=>'','b_id1'=>'','dvat1'=>$decision['vat_code'] ?? '',
            'kvat1'=>'','dkka1'=>'125','kontrolkonto'=>'','kladdenote'=>'T3 bank'];
        if ($decision['vat_exempt'] ?? false) { $form['moms1']='on'; }
        $result=$this->web('run_kassekladde_page.php',[$webJournal,self::$seed->session,json_encode($form,JSON_THROW_ON_ERROR)]);
        $web=RestApiEnv::rows(self::$seed->tenant,'SELECT * FROM kassekladde WHERE id=$1',[$webLine])[0];
        $fields=['transdate','beskrivelse','d_type','debet','k_type','kredit','amount','faktura','valuta','valutakurs','debetvat','kreditvat','momsfri'];
        $pick=static fn(array $row)=>array_intersect_key($row,array_flip($fields));
        $this->assertSame($pick($web),$pick($line),$result['stdout'].$result['stderr']);
        $this->assertTrue(pool_audit_verify((int)$row['id']),$case . ' audit chain');
        $mobilePosted=$this->posting($draft['kladde_id']);
        // The comparison journal is an independent web save. Remove only this case's posting
        // so duplicate invoice checks do not prevent the second independently driven posting.
        RestApiEnv::rows(self::$seed->tenant,'DELETE FROM transaktioner WHERE kladde_id=$1',[$draft['kladde_id']]);
        RestApiEnv::rows(self::$seed->tenant,'DELETE FROM openpost WHERE kladde_id=$1',[$draft['kladde_id']]);
        foreach ($before['saldo'] as $account) { RestApiEnv::rows(self::$seed->tenant,'UPDATE kontoplan SET saldo=$1 WHERE id=$2',[$account['saldo'],$account['id']]); }
        $webPosted=$this->posting($webJournal);
        $this->assertSame($webPosted,$mobilePosted,$case.' actual web posting equivalence');
        $this->golden($case,['draft'=>$pick($line),'posting'=>$mobilePosted]);
    }
    public function testValidationFailuresRestoreFileAndLeaveAccountingUntouched(): void
    {
        $cases=[['D4',['currency'=>'EUR','file_date'=>'2026-01-01'],['date'=>'2026-01-01'],'missing_exchange_rate'],
            ['D5_closed',[],['kontonr'=>987002],'account_closed'],['D5_creditor',[],['contra'=>['type'=>'K','kontonr'=>'999992']],'kreditor_invalid'],
            ['D5_date',[],['date'=>'2099-01-01'],'outside_fiscal_year']];
        RestApiEnv::rows(self::$seed->tenant,"DELETE FROM valuta WHERE gruppe=987 AND valdate='2026-01-01'");
        $observed=[];
        try {
            foreach ($cases as [$case,$values,$decision,$reason]) {
                $row=$this->receipt($values); $before=$this->accounting();
                $response=$this->approve($row,$decision);
                $this->assertSame(422,$response['status'],$response['body']);
                $this->assertSame($reason,$response['json']['data']['details']['reason']);
                $this->assertSame($before,$this->accounting()); $this->assertSame($row,$this->row((int)$row['id']));
                $this->assertSame($row['sha256'],hash_file('sha256',self::$seed->root.'/'.$row['storage_path']));
                $this->assertSame('aborted',RestApiEnv::rows(self::$seed->tenant,'SELECT state FROM pool_file_moves WHERE pool_file_id=$1',[$row['id']])[0]['state']);
                $observed[$case]=$response['json']['data'];
            }
        } finally { RestApiEnv::rows(self::$seed->tenant,"INSERT INTO valuta(gruppe,valdate,kurs) VALUES (987,'2026-01-01',745.123)"); }
        $this->golden('D4',['error'=>$observed['D4']]); unset($observed['D4']); $this->golden('D5',$observed);
    }
    public function testUnbalancedApprovalAndWebPostingRefusal(): void
    {
        RestApiEnv::rows(self::$seed->tenant,"DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        $row=$this->receipt(); $before=$this->accounting();
        $response=$this->approve($row,['contra'=>['type'=>'none']]); $this->assertSame(200,$response['status'],$response['body']);
        $draft=$response['json']['data']['draft']; $this->assertFalse($draft['balanced']);
        $this->web('run_bogfor_page.php',['bogfor',$draft['kladde_id'],self::$seed->session]);
        foreach (['transaktioner','openpost'] as $table) { $this->assertSame($before[$table],$this->accounting()[$table]); }
        $this->golden('D6',['balanced'=>false,'approval_posts'=>false,'web_refuses'=>true]);
    }
    public function testBankLeasesIncludingOwnLeaseAndRollingJournalRotation(): void
    {
        $observed=[];
        foreach ([RestApiEnv::user(),'t3_other'] as $holder) {
            [$row,$journal]=$this->bankReceipt(); $before=$this->accounting();
            RestApiEnv::rows(self::$seed->tenant,'UPDATE kladdeliste SET hvem=$1,tidspkt=$2 WHERE id=$3',[$holder,microtime(),$journal]);
            $response=$this->approve($row); $this->assertSame(409,$response['status'],$response['body']);
            $this->assertSame($holder === RestApiEnv::user(),$response['json']['data']['details']['self']);
            $observed[$holder === RestApiEnv::user() ? 't3_reviewer' : $holder]=$response['json']['data']['details'];
            $this->assertSame($before,$this->accounting());
            RestApiEnv::rows(self::$seed->tenant,'UPDATE kladdeliste SET tidspkt=$1 WHERE id=$2',[(string)(time()-3601),$journal]);
            $this->assertSame(200,$this->approve($row)['status']);
        }
        $row=$this->receipt(); $response=$this->approve($row); $this->assertSame(200,$response['status'],$response['body']);
        $journal=$response['json']['data']['draft']['kladde_id'];
        RestApiEnv::rows(self::$seed->tenant,'UPDATE kladdeliste SET hvem=$1,tidspkt=$2 WHERE id=$3',[RestApiEnv::user(),microtime(),$journal]);
        $response=$this->approve($this->receipt()); $this->assertSame(200,$response['status'],$response['body']);
        $this->assertNotSame($journal,$response['json']['data']['draft']['kladde_id']);
        $observed['t3_reviewer']['locked_by']='t3_reviewer';
        $this->golden('D7',$observed);
    }
    public function testDuplicatePostedVoucherIsAWarning(): void
    {
        $row=$this->receipt();
        RestApiEnv::rows(self::$seed->tenant,"INSERT INTO transaktioner(kontonr,debet,kredit,moms,beskrivelse,faktura,transdate,kladde_id,bilag) VALUES (987001,125,0,0,'T3 purchase','INV-T3','2026-09-14',999999,42)");
        $response=$this->approve($row); $this->assertSame(200,$response['status'],$response['body']);
        $warnings=$response['json']['data']['warnings']; $this->assertContains('possible_duplicate',array_column($warnings,'code'));
        $this->golden('D8',['warning'=>'possible_duplicate','status'=>200]);
    }

    public function testD14PostedBankRowOnlyAttachesAndPreservesAllAccounting(): void
    {
        [$row,$journal,$line] = $this->bankReceipt('V');
        $before = $this->accounting();
        $response = $this->approve($row,['kontonr'=>'ignored','vat_code'=>[],'contra'=>false,'currency'=>'ZZZ']);
        $this->assertSame(200,$response['status'],$response['body']);
        $data = $response['json']['data'];
        $this->assertSame('attach_only',$data['draft']['mode']);
        $this->assertNull($data['draft']['balanced']);
        $this->assertSame($before,$this->accounting());
        $actual = $this->row((int)$row['id']);
        $this->assertSame('attached',$actual['status']);
        $this->assertSame($row['sha256'],hash_file('sha256',self::$seed->root . '/' . $actual['storage_path']));
        $this->assertSame([['source_id'=>(string)$line]],self::$seed->sql('SELECT source_id FROM documents WHERE pool_file_id=$1',[$row['id']]));
        $this->assertSame([['mode'=>'attach_only']],self::$seed->sql('SELECT mode FROM mobile_journal_lines WHERE pool_file_id=$1',[$row['id']]));
        $this->assertSame([],self::$seed->sql('SELECT id FROM classification_feedback WHERE pool_file_id=$1',[$row['id']]));
        $this->assertTrue(pool_audit_verify((int)$row['id']));
        $this->golden('D14',['mode'=>'attach_only','accounting_unchanged'=>true,'feedback'=>$data['feedback']]);
    }
    public function testAuditAndClassificationAreImmutableAndTamperingBreaksTheChain(): void
    {
        $s = self::$seed;
        $row = $s->receipt();
        $id = (int)$row['id'];
        $suggestion = $s->request('GET','classification/suggest.php?attachment_id=' . $id);
        $this->assertSame(200,$suggestion['status'],$suggestion['body']);
        $feedback = $s->request('POST','classification/feedback.php',
            ['client_id'=>T3Seed::uuid(),'attachment_id'=>$id,'kontonr'=>987001]);
        $this->assertSame(201,$feedback['status'],$feedback['body']);
        foreach (['pool_file_audit','classification_suggestions','classification_feedback'] as $table) {
            foreach (['UPDATE ' . $table . ' SET id=id','DELETE FROM ' . $table,'TRUNCATE ' . $table . ' CASCADE'] as $sql) {
                $s->sql('BEGIN');
                try {
                    // Async libpq exposes the actual SQLSTATE without suppressing expected errors.
                    $this->assertTrue(pg_send_query($s->tenant,$sql));
                    $result = pg_get_result($s->tenant);
                    $this->assertSame(PGSQL_FATAL_ERROR,pg_result_status($result),$sql);
                    $this->assertSame('55000',pg_result_error_field($result,PGSQL_DIAG_SQLSTATE),$sql);
                    while (pg_get_result($s->tenant) !== false) { }
                } finally {
                    $s->sql('ROLLBACK');
                }
            }
        }
        foreach ($s->sql('SELECT id FROM pool_files') as $fixture) {
            $this->assertTrue(pool_audit_verify((int)$fixture['id']),'Fixture audit ' . $fixture['id']);
        }
        $s->sql('BEGIN');
        try {
            $s->sql('ALTER TABLE pool_file_audit DISABLE TRIGGER mobile_audit_immutable');
            $s->sql("UPDATE pool_file_audit SET note='Forced edit for chain detection' WHERE pool_file_id=$1",[$id]);
            $this->assertFalse(pool_audit_verify($id));
        } finally {
            // Restore both the row and trigger even if a chain assertion fails.
            $s->sql('ROLLBACK');
        }
        $this->assertTrue(pool_audit_verify($id));
    }

}
