<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/AttachmentApproveEndpointTest.php --- ver 5.0.0 --- 2026.10.05
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
require_once __DIR__.'/support/T3B2bTestCase.php';

final class AttachmentApproveEndpointTest extends T3B2bTestCase
{
    public function testApprovalDraftAndReplayNeverPost(): void
    {
        $row=$this->receipt(); $before=$this->accounting(); $client=RequestId::resolve(null);
        $response=$this->approve($row,['client_id'=>$client]);
        $this->assertSame(200,$response['status'],$response['body']);
        $data=$response['json']['data']; $this->assertSame('new_line',$data['draft']['mode']);
        $this->assertTrue($data['draft']['balanced']);
        $this->assertAttached($row);
        foreach (['transaktioner','openpost','saldo'] as $table) { $this->assertSame($before[$table],$this->accounting()[$table]); }
        $this->assertSame('- ',RestApiEnv::rows(self::$conn,'SELECT bogfort FROM kladdeliste WHERE id=$1',[$data['draft']['kladde_id']])[0]['bogfort'].' ');
        $this->assertSame($response['json'],$this->approve($row,['client_id'=>$client],null,'"stale"')['json']);
        $this->assertSame(409,$this->approve($row,['client_id'=>$client,'description'=>'Changed'])['status']);
    }
    public function testBankAndPostedAttachmentBranches(): void
    {
        [$row,$journal,$line]=$this->bankReceipt('-',true);
        $response=$this->approve($row); $this->assertSame(200,$response['status'],$response['body']);
        $this->assertSame($line,$response['json']['data']['draft']['kassekladde_id']);
        $this->assertAttached($row,2);
        $this->assertSame('987001',RestApiEnv::rows(self::$conn,'SELECT debet FROM kassekladde WHERE id=$1',[$line])[0]['debet']);
        [$row,$journal,$line]=$this->bankReceipt('V'); $before=$this->accounting();
        $response=$this->approve($row,['kontonr'=>'garbage','vat_code'=>[],'contra'=>false,'date'=>'2025-09-14','currency'=>'ZZZ']);
        $this->assertSame(200,$response['status'],$response['body']);
        $this->assertSame('attach_only',$response['json']['data']['draft']['mode']);
        $this->assertNull($response['json']['data']['feedback']);
        $this->assertSame($before,$this->accounting()); $this->assertAttached($row);
        $this->golden('D14',['mode'=>'attach_only','accounting_unchanged'=>true,'feedback'=>null]);
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
        if (getenv('SALDI_UPDATE_B2B_GOLDEN') === '1') { file_put_contents($path,json_encode($actual,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n"); }
        $this->assertSame(json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR),$actual,$name);
    }
    private function web(string $runner,array $args): array
    {
        $master=RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows($master,'UPDATE online SET logtime=$1 WHERE session_id=$2',[time(),self::$session]);
        pg_close($master);
        $result=$this->child(__DIR__.'/../characterization/support/'.$runner,$args,['SALDI_CHAR_TEST_DB'=>RestApiEnv::testDb()]);
        $this->assertSame(0,$result['exit'],$result['stdout'].$result['stderr']);
        $this->assertStringNotContainsString('PHP Fatal error:',$result['stderr']);
        return $result;
    }
    private function posting(int $journal): array
    {
        $before=RestApiEnv::rows(self::$conn,'SELECT kontonr,saldo FROM kontoplan WHERE regnskabsaar=$1 ORDER BY kontonr',[self::$year]);
        $result=$this->web('run_bogfor_page.php',['bogfor',$journal,self::$session]);
        $rows=RestApiEnv::rows(self::$conn,'SELECT kontonr,debet,kredit,moms,beskrivelse,faktura,valuta,valutakurs FROM transaktioner WHERE kladde_id=$1 ORDER BY id',[$journal]);
        $this->assertNotEmpty($rows,$result['stdout'].$result['stderr']);
        $open=RestApiEnv::rows(self::$conn,'SELECT konto_nr,faktnr,amount,transdate,valuta,valutakurs FROM openpost WHERE kladde_id=$1 ORDER BY id',[$journal]);
        $after=RestApiEnv::rows(self::$conn,'SELECT kontonr,saldo FROM kontoplan WHERE regnskabsaar=$1 ORDER BY kontonr',[self::$year]);
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
        RestApiEnv::rows(self::$conn,"DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        if ($case === 'D2') { [$row,$journal,$lineId]=$this->bankReceipt(); }
        else { $row=$this->receipt($metadata); }
        $before=$this->accounting();
        $response=$this->approve($row,$decision);
        $this->assertSame(200,$response['status'],$response['body']);
        $draft=$response['json']['data']['draft'];
        $line=RestApiEnv::rows(self::$conn,'SELECT * FROM kassekladde WHERE id=$1',[$draft['kassekladde_id']])[0];
        foreach (['transaktioner','openpost','saldo'] as $table) { $this->assertSame($before[$table],$this->accounting()[$table],$case.' approval never posts'); }
        if ($case === 'D3' || $case === 'D12') {
            $provenance=RestApiEnv::rows(self::$conn,'SELECT rate,rate_date,rate_source FROM mobile_journal_lines WHERE pool_file_id=$1',[$row['id']])[0];
            $this->assertSame(['rate'=>'746.456000','rate_date'=>'2026-06-01','rate_source'=>'saldi_valuta'],$provenance);
        }
        $webJournal=$this->journal();
        $webLine=(int)RestApiEnv::rows(self::$conn,"INSERT INTO kassekladde(kladde_id,bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,valuta,valutakurs,pos,debetvat,kreditvat,momsfri) VALUES ($1,$2,'2026-09-14','Original','F',987001,$3,$4,10,0,100,1,'K987','','') RETURNING id",[$webJournal,$line['bilag'],$case === 'D2' ? 'F' : 'K',$case === 'D2' ? 987010 : 987003])[0]['id'];
        $form=['save'=>'on','antal_ny'=>1,'antal_ex'=>1,'fokus'=>'besk1','tidspkt'=>microtime(),'id'=>[1=>$webLine],
            'bila1'=>$line['bilag'],'dato1'=>'14-09-2026','besk1'=>'T3 purchase','d_ty1'=>'F','debe1'=>'987001',
            'k_ty1'=>$case === 'D2' ? 'F' : 'K','kred1'=>$case === 'D2' ? '987010' : '987003',
            'belo1'=>$metadata['amount'] ?? '125,00','fakt1'=>'INV-T3','valu1'=>$metadata['currency'] ?? 'DKK',
            'afd_1'=>'','proj1'=>'','meda1'=>'','forf1'=>'','b_id1'=>'','dvat1'=>$decision['vat_code'] ?? '',
            'kvat1'=>'','dkka1'=>'125','kontrolkonto'=>'','kladdenote'=>'T3 bank'];
        if ($decision['vat_exempt'] ?? false) { $form['moms1']='on'; }
        $result=$this->web('run_kassekladde_page.php',[$webJournal,self::$session,json_encode($form,JSON_THROW_ON_ERROR)]);
        $web=RestApiEnv::rows(self::$conn,'SELECT * FROM kassekladde WHERE id=$1',[$webLine])[0];
        $fields=['transdate','beskrivelse','d_type','debet','k_type','kredit','amount','faktura','valuta','valutakurs','debetvat','kreditvat','momsfri'];
        $pick=static fn(array $row)=>array_intersect_key($row,array_flip($fields));
        $this->assertSame($pick($web),$pick($line),$result['stdout'].$result['stderr']);
        $mobilePosted=$this->posting($draft['kladde_id']);
        // The comparison journal is an independent web save. Remove only this case's posting
        // so duplicate invoice checks do not prevent the second independently driven posting.
        RestApiEnv::rows(self::$conn,'DELETE FROM transaktioner WHERE kladde_id=$1',[$draft['kladde_id']]);
        RestApiEnv::rows(self::$conn,'DELETE FROM openpost WHERE kladde_id=$1',[$draft['kladde_id']]);
        foreach ($before['saldo'] as $account) { RestApiEnv::rows(self::$conn,'UPDATE kontoplan SET saldo=$1 WHERE id=$2',[$account['saldo'],$account['id']]); }
        $webPosted=$this->posting($webJournal);
        $this->assertSame($webPosted,$mobilePosted,$case.' actual web posting equivalence');
        $this->golden($case,['draft'=>$pick($line),'posting'=>$mobilePosted]);
    }
    public function testValidationFailuresRestoreFileAndLeaveAccountingUntouched(): void
    {
        $cases=[['D4',['currency'=>'EUR','file_date'=>'2026-01-01'],['date'=>'2026-01-01'],'missing_exchange_rate'],
            ['D5_closed',[],['kontonr'=>987002],'account_closed'],['D5_creditor',[],['contra'=>['type'=>'K','kontonr'=>'999992']],'kreditor_invalid'],
            ['D5_date',[],['date'=>'2099-01-01'],'outside_fiscal_year']];
        RestApiEnv::rows(self::$conn,"DELETE FROM valuta WHERE gruppe=987 AND valdate='2026-01-01'");
        $observed=[];
        try {
            foreach ($cases as [$case,$values,$decision,$reason]) {
                $row=$this->receipt($values); $before=$this->accounting();
                $response=$this->approve($row,$decision);
                $this->assertSame(422,$response['status'],$response['body']);
                $this->assertSame($reason,$response['json']['data']['details']['reason']);
                $this->assertSame($before,$this->accounting()); $this->assertSame($row,$this->row((int)$row['id']));
                $this->assertSame($row['sha256'],hash_file('sha256',self::$root.'/'.$row['storage_path']));
                $this->assertSame('aborted',RestApiEnv::rows(self::$conn,'SELECT state FROM pool_file_moves WHERE pool_file_id=$1',[$row['id']])[0]['state']);
                $observed[$case]=$response['json']['data'];
            }
        } finally { RestApiEnv::rows(self::$conn,"INSERT INTO valuta(gruppe,valdate,kurs) VALUES (987,'2026-01-01',745.123)"); }
        $this->golden('D4',['error'=>$observed['D4']]); unset($observed['D4']); $this->golden('D5',$observed);
    }
    public function testUnbalancedApprovalAndWebPostingRefusal(): void
    {
        RestApiEnv::rows(self::$conn,"DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        $row=$this->receipt(); $before=$this->accounting();
        $response=$this->approve($row,['contra'=>['type'=>'none']]); $this->assertSame(200,$response['status'],$response['body']);
        $draft=$response['json']['data']['draft']; $this->assertFalse($draft['balanced']);
        $this->web('run_bogfor_page.php',['bogfor',$draft['kladde_id'],self::$session]);
        foreach (['transaktioner','openpost'] as $table) { $this->assertSame($before[$table],$this->accounting()[$table]); }
        $this->golden('D6',['balanced'=>false,'approval_posts'=>false,'web_refuses'=>true]);
    }
    public function testBankLeasesIncludingOwnLeaseAndRollingJournalRotation(): void
    {
        $observed=[];
        foreach (['t3_reviewer','t3_other'] as $holder) {
            [$row,$journal]=$this->bankReceipt(); $before=$this->accounting();
            RestApiEnv::rows(self::$conn,'UPDATE kladdeliste SET hvem=$1,tidspkt=$2 WHERE id=$3',[$holder,microtime(),$journal]);
            $response=$this->approve($row); $this->assertSame(409,$response['status'],$response['body']);
            $this->assertSame($holder === 't3_reviewer',$response['json']['data']['details']['self']);
            $observed[$holder]=$response['json']['data']['details'];
            $this->assertSame($before,$this->accounting());
            RestApiEnv::rows(self::$conn,'UPDATE kladdeliste SET tidspkt=$1 WHERE id=$2',[(string)(time()-3601),$journal]);
            $this->assertSame(200,$this->approve($row)['status']);
        }
        $row=$this->receipt(); $response=$this->approve($row); $this->assertSame(200,$response['status'],$response['body']);
        $journal=$response['json']['data']['draft']['kladde_id'];
        RestApiEnv::rows(self::$conn,'UPDATE kladdeliste SET hvem=$1,tidspkt=$2 WHERE id=$3',['t3_reviewer',microtime(),$journal]);
        $response=$this->approve($this->receipt()); $this->assertSame(200,$response['status'],$response['body']);
        $this->assertNotSame($journal,$response['json']['data']['draft']['kladde_id']);
        $this->golden('D7',$observed);
    }
    public function testDuplicatePostedVoucherIsAWarning(): void
    {
        $row=$this->receipt();
        RestApiEnv::rows(self::$conn,"INSERT INTO transaktioner(kontonr,debet,kredit,moms,beskrivelse,faktura,transdate,kladde_id,bilag) VALUES (987001,125,0,0,'T3 purchase','INV-T3','2026-09-14',999999,42)");
        $response=$this->approve($row); $this->assertSame(200,$response['status'],$response['body']);
        $warnings=$response['json']['data']['warnings']; $this->assertContains('possible_duplicate',array_column($warnings,'code'));
        $this->golden('D8',['warning'=>'possible_duplicate','status'=>200]);
    }
    public function testStaleVendorMatchAndEndpointInputStateErrors(): void
    {
        $row=$this->receipt();
        RestApiEnv::rows(self::$conn,'UPDATE pool_files SET vendor_name=$1 WHERE id=$2',['Rematch',$row['id']]);
        $this->assertSame(412,$this->approve($row)['status']);
        $row=$this->row((int)$row['id']);
        $this->assertSame(403,$this->approve($row,[],self::$ownerHeaders)['status']);
        $missing=RestApiEnv::http('POST','/restapi/endpoints/v1/attachment/approve.php',['id'=>(int)$row['id'],'client_id'=>RequestId::resolve(null)],self::$reviewerHeaders);
        $this->assertSame(422,$missing['status']);
        $this->assertSame('if_match_required',$missing['json']['data']['details']['reason']);
        foreach ([['kontonr'=>'bad'],['vat_code'=>'K999'],['vat_exempt'=>true,'vat_code'=>null],['contra'=>['type'=>'F','kontonr'=>0]],['amount'=>0.1+0.2]] as $fields) {
            $before=$this->accounting(); $response=$this->approve($row,$fields);
            $this->assertSame(422,$response['status'],$response['body']); $this->assertSame($before,$this->accounting());
        }
        foreach (['staged'=>404,'attached'=>409,'deleted'=>410] as $status=>$expected) { $this->assertSame($expected,$this->approve($this->fixture($status))['status']); }
    }

    public function testRealMobileUploadKeepsIdentityOnTargetAndVoucherSiblings(): void
    {
        $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Resources << >> /Contents 4 0 R >>',"<< /Length 0 >>\nstream\n\nendstream"];
        $pdf="%PDF-1.4\n% ".RequestId::resolve(null)."\n"; $offsets=[];
        foreach ($objects as $i=>$object) { $offsets[]=strlen($pdf); $pdf.=($i+1)." 0 obj\n".$object."\nendobj\n"; }
        $xref=strlen($pdf); $pdf.="xref\n0 5\n0000000000 65535 f \n";
        foreach ($offsets as $offset) { $pdf.=sprintf("%010d 00000 n \n",$offset); }
        $pdf.="trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
        $response=RestApiEnv::http('POST','/restapi/endpoints/v1/attachment/',
            ['id'=>RequestId::resolve(null),'sha256'=>hash('sha256',$pdf),'size'=>strlen($pdf),'image_base64'=>base64_encode($pdf),'filename'=>'uploaded.pdf',
                'extracted_data'=>['total_amount'=>'125.00','invoice_date'=>'2026-09-14','invoice_number'=>'INV-T3','invoice_description'=>'T3 purchase','currency'=>'DKK','vendor'=>'T3 supplier']],self::$ownerHeaders);
        $this->assertSame(201,$response['status'],$response['body']);
        [$unused,$journal,$line,$bank]=$this->bankReceipt('-',true);
        RestApiEnv::rows(self::$conn,'UPDATE pool_files SET bank_line_id=$1 WHERE id=$2',[$bank,$response['json']['data']['id']]);
        $row=$this->row($response['json']['data']['id']);
        $response=$this->approve($row,['kontonr'=>987001]); $this->assertSame(200,$response['status'],$response['body']);
        $actual=$this->assertAttached($row,2); $this->assertSame(self::$owner,$actual['uploaded_by']);
        $this->assertSame($line,$response['json']['data']['draft']['kassekladde_id']);
    }
    public function testActualRegistryReviewerUsesPrincipalNamespaceAndLegacyDocumentSentinel(): void
    {
        $master=RestApiEnv::connect(RestApiEnv::masterDb()); $name='t3b2b_'.bin2hex(random_bytes(6));
        $id=(int)RestApiEnv::rows($master,"INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES ($1,$2,$3,true) RETURNING id",
            [$name,md5(TestCredentials::password('t3b2b_registry')),',,,'.RestApiEnv::regnskabId(RestApiEnv::accountOpen())])[0]['id'];
        $subject=Subject::build('registry',$id);
        try {
            $login=RestApiEnv::http('POST','/restapi/endpoints/v1/auth/registry-login.php',['username'=>$name,'password'=>TestCredentials::password('t3b2b_registry'),'device_id'=>'t3b2b-registry']);
            $this->assertSame(200,$login['status'],$login['body']);
            $exchange=RestApiEnv::http('POST','/restapi/endpoints/v1/auth/tenant-token.php',['tenant_id'=>RestApiEnv::regnskabId(RestApiEnv::accountOpen())],['Authorization: Bearer '.$login['json']['data']['access_token']]);
            $this->assertSame(200,$exchange['status'],$exchange['body']);
            $row=$this->receipt(); $response=$this->approve($row,[],['Authorization: Bearer '.$exchange['json']['data']['access_token']]);
            $this->assertSame(200,$response['status'],$response['body']);
            $actual=$this->row((int)$row['id']); $this->assertSame($subject,$actual['attached_by']); $this->assertSame($subject,$actual['reviewed_by']);
            $docs=RestApiEnv::rows(self::$conn,'SELECT user_id FROM documents WHERE pool_file_id=$1',[$row['id']]);
            $this->assertSame([['user_id'=>'-1']],$docs); $this->assertSame($row['sha256'],hash_file('sha256',self::$root.'/'.$actual['storage_path']));
        } finally {
            foreach (['refresh_sessions','auth_challenges','auth_versions'] as $table) { RestApiEnv::rows($master,'DELETE FROM '.$table.' WHERE subject=$1',[$subject]); }
            RestApiEnv::rows($master,"DELETE FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$1",[MobileAuth::bucketKey('registry','',$name)]);
            RestApiEnv::rows($master,'DELETE FROM brugere WHERE id=$1',[$id]); pg_close($master);
        }
    }
    public function testAttachOnlyPreservesInvalidHistoricalAccountAndRefusesSameHash(): void
    {
        [$row,$journal,$line]=$this->bankReceipt('V');
        RestApiEnv::rows(self::$conn,"UPDATE pool_files SET account='old OCR text' WHERE id=$1",[$row['id']]); $row=$this->row((int)$row['id']);
        $response=$this->approve($row); $this->assertSame(200,$response['status'],$response['body']);
        $this->assertSame('old OCR text',$this->row((int)$row['id'])['account']);
        $duplicate=$this->receipt(['bank_line_id'=>$row['bank_line_id']]);
        $bytes=file_get_contents(self::$root.'/'.$this->row((int)$row['id'])['storage_path']);
        file_put_contents(self::$root.'/'.$duplicate['storage_path'],$bytes);
        RestApiEnv::rows(self::$conn,'UPDATE pool_files SET sha256=$1 WHERE id=$2',[hash('sha256',$bytes),$duplicate['id']]); $duplicate=$this->row((int)$duplicate['id']);
        $before=$this->accounting(); $response=$this->approve($duplicate); $this->assertSame(409,$response['status'],$response['body']);
        $this->assertSame($before,$this->accounting()); $this->assertFileExists(self::$root.'/'.$duplicate['storage_path']);
    }

    public function testReviewerCorrectionsAreAppliedBeforeRateAndDraftResolution(): void
    {
        RestApiEnv::rows(self::$conn,"DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        $row=$this->receipt();
        $response=$this->approve($row,['kontonr'=>987001,'date'=>'2026-05-15','amount'=>'250,00','currency'=>'EUR',
            'description'=>'Reviewer correction','invoice_number'=>'CORRECTED','contra'=>['type'=>'F','kontonr'=>987010],'vat_code'=>'K988']);
        $this->assertSame(200,$response['status'],$response['body']);
        $draft=$response['json']['data']['draft']; $this->assertSame('745.123000',$draft['rate']); $this->assertSame('2026-01-01',$draft['rate_date']);
        $actual=$this->row((int)$row['id']); $this->assertSame('2026-05-15',$actual['file_date']); $this->assertSame('250.000',$actual['norm_amount']);
        $this->assertSame('Reviewer correction',$actual['description']); $this->assertSame('CORRECTED',$actual['invoice_number']);
        $line=RestApiEnv::rows(self::$conn,'SELECT transdate,amount,beskrivelse,faktura,debetvat,k_type,kredit FROM kassekladde WHERE id=$1',[$draft['kassekladde_id']])[0];
        $this->assertSame(['transdate'=>'2026-05-15','amount'=>'250.000','beskrivelse'=>'Reviewer correction','faktura'=>'CORRECTED','debetvat'=>'K988','k_type'=>'F','kredit'=>'987010'],$line);
        $feedback=RestApiEnv::rows(self::$conn,'SELECT role,kontonr,vat_code,actor FROM classification_feedback WHERE pool_file_id=$1',[$row['id']]);
        $this->assertSame([['role'=>'reviewer','kontonr'=>'987001','vat_code'=>'K988','actor'=>self::$reviewer]],$feedback);
        $this->assertTrue(pool_audit_verify((int)$row['id'])); $this->assertAttached($row);
    }
}
