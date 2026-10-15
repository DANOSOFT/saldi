<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/JournalDraftsEndpointTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Cover current journal currencies and credit-note account corrections.

require_once __DIR__ . '/support/T3B2cTestCase.php';

final class JournalDraftsEndpointTest extends T3B2cTestCase
{
    private int $year;

    protected function setUp(): void
    {
        $this->year = (int)RestApiEnv::rows(self::$conn,"SELECT kodenr FROM grupper WHERE art='RA' AND box2='2026' ORDER BY kodenr DESC LIMIT 1")[0]['kodenr'];
        RestApiEnv::rows(self::$conn,'UPDATE kontoplan SET beskrivelse=$1,moms=$2 WHERE kontonr=987001 AND regnskabsaar=$3', ['Expense','K1',$this->year]);
        foreach ([[987002,'Bank',''],[987003,'Purchase VAT','']] as [$nr,$label,$code]) {
            RestApiEnv::rows(self::$conn,'INSERT INTO kontoplan(kontonr,regnskabsaar,kontotype,lukket,beskrivelse,moms,saldo,primo)
                SELECT $1,$2,\'D\',NULL,$3,$4,0,0 WHERE NOT EXISTS(SELECT 1 FROM kontoplan WHERE kontonr=$1 AND regnskabsaar=$2)',[$nr,$this->year,$label,$code]);
        }
        foreach ([[1,25],[2,10]] as [$code,$rate]) {
            RestApiEnv::rows(self::$conn,"DELETE FROM grupper WHERE art='KM' AND kodenr=$1 AND fiscal_year=$2",[$code,$this->year]);
            RestApiEnv::rows(self::$conn,"INSERT INTO grupper(art,kode,kodenr,box1,box2,box3,fiscal_year) VALUES ('KM','K',$1,'987003',$2,'',$3)",[$code,(string)$rate,$this->year]);
        }
    }

    /** @return int Disposable journal with the server-owned mobile identity set only by the fixture. */
    private function journal(bool $mobile = true): int
    {
        $id = (int)RestApiEnv::rows(self::$conn,"SELECT coalesce(max(id),0)+1 AS id FROM kladdeliste")[0]['id'];
        RestApiEnv::rows(self::$conn,"INSERT INTO kladdeliste(id,kladdedate,kladdenote,bogfort,oprettet_af,mobile_approval_journal)
            VALUES ($1,'2026-09-14','Draft fixture','-','t3_reviewer',$2)",[$id,$mobile?'t':'f']);
        return $id;
    }

    /** @return array Mobile provenance and an existing journal row, without calling approval. */
    private function line(int $journal, int $voucher, ?string $code = 'K1', bool $exempt = false, bool $negative = false, bool $provenance = true, string $mode = 'new_line'): array
    {
        $receipt = $this->reviewFixture(true,'attached','approved');
        RestApiEnv::rows(self::$conn,"UPDATE pool_files SET account='987001',amount=$1,norm_amount=$2 WHERE id=$3",
            [$negative ? '-125,00' : '125,00', $negative ? -125 : 125, $receipt['id']]);
        $line = RestApiEnv::rows(self::$conn,"INSERT INTO kassekladde(kladde_id,bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,debetvat,kreditvat,momsfri,valuta,afd,ansat,projekt,ordre_id,faktura)
            VALUES ($1,$2,'2026-09-14','Draft VAT fixture','F',$3,'F',$4,125,$5,$6,$7,0,0,0,0,0,'') RETURNING *",
            [$journal,$voucher,$negative?987002:987001,$negative?987001:987002,$negative?'':$code,$negative?$code:'',$exempt?'on':''])[0];
        if ($provenance) {
            RestApiEnv::rows(self::$conn,"INSERT INTO mobile_journal_lines(kassekladde_id,kladde_id,pool_file_id,mode,currency,created_by,client_id)
                VALUES ($1,$2,$3,$4,'DKK',$5,$6)",[$line['id'],$journal,$receipt['id'],$mode,self::$reviewer,RequestId::resolve(null)]);
        }
        return ['line'=>$line,'receipt'=>$receipt];
    }

    /** @return array Local authenticated draft list. */
    private function get(?int $journal, string $query = '', ?array $headers = null): array
    {
        return RestApiEnv::http('GET','/restapi/endpoints/v1/journal/drafts.php?' . ($journal===null?'':'kladde_id='.$journal.'&') . $query,
            null,$headers ?? self::$reviewerHeaders);
    }

    public function testPagingTotalsProvenanceAndReadOnlyAccess(): void
    {
        $journal = $this->journal();
        $first = $this->line($journal,1);
        $this->line($journal,2,'K2');
        $this->line($journal,3,null,true);
        $this->line($journal,4,'K1',false,false,false);
        $this->line($journal,5,'K1',false,false,true,'attach_only');
        // Reapproval records another provenance row, never another item for the same journal row.
        RestApiEnv::rows(self::$conn,"INSERT INTO mobile_journal_lines(kassekladde_id,kladde_id,pool_file_id,mode,currency,created_by,client_id)
            VALUES ($1,$2,$3,'new_line','DKK',$4,$5)",[$first['line']['id'],$journal,$first['receipt']['id'],self::$reviewer,RequestId::resolve(null)]);
        $response = $this->get($journal,'limit=1&offset=1');
        $this->assertSame(200,$response['status'],$response['body']);
        $data = $response['json']['data'];
        $this->assertSame(3,$data['total']);
        $this->assertSame(['amount'=>'375.00','vat'=>'36.36'],$data['totals']);
        $this->assertCount(1,$data['items']);
        $this->assertSame('11.36',$data['items'][0]['vat_amount']);
        $this->assertSame('K2',$data['items'][0]['vat_code']);
        $this->assertSame(['type'=>'F','kontonr'=>'987002','name'=>'Bank'],$data['items'][0]['contra']);
        $this->assertSame(['kladde_id'=>$journal,'note'=>'Draft fixture','posted'=>false,'locked_by'=>null],$data['journal']);
        $this->assertStringNotContainsString(self::$reviewer,json_encode($data));
        RestApiEnv::rows(self::$conn,"UPDATE kladdeliste SET hvem='t3_reviewer',tidspkt=$1 WHERE id=$2",[(string)time(),$journal]);
        $this->assertSame('t3_reviewer',$this->get($journal)['json']['data']['journal']['locked_by']);
        $this->assertSame(200,$this->get($journal,'',self::$registryHeaders)['status']);
        $this->assertSame(403,$this->get($journal,'',self::$ownerHeaders)['status']);
        $this->assertSame(401,RestApiEnv::http('GET','/restapi/endpoints/v1/journal/drafts.php')['status']);
        RestApiEnv::rows(self::$conn,"UPDATE brugere SET rettigheder='0020000000000000' WHERE brugernavn='t3_reviewer'");
        try { $this->assertSame(200,$this->get($journal)['status']); }
        finally { RestApiEnv::rows(self::$conn,"UPDATE brugere SET rettigheder='0010000000000000' WHERE brugernavn='t3_reviewer'"); }
    }

    public function testVatEqualsWebPostingForDefaultOverrideExemptionAndNegativeReceipt(): void
    {
        foreach ([[null,false,false,'25.00'],['K2',false,false,'11.36'],[null,true,false,'0.00'],['K1',false,true,'-25.00']] as [$code,$exempt,$negative,$expected]) {
            $journal = $this->journal();
            $this->line($journal,1,$code,$exempt,$negative);
            $response = $this->get($journal);
            $this->assertSame(200,$response['status'],$response['body']);
            $item = $response['json']['data']['items'][0];
            $this->assertSame($expected,$item['vat_amount']);
            $this->assertSame($negative?'-125.00':'125.00',$item['amount']);
            $this->assertSame([],RestApiEnv::rows(self::$conn,'SELECT * FROM transaktioner WHERE kladde_id=$1',[$journal]));
            $this->postThroughWeb($journal);
            $ledgerVat = RestApiEnv::rows(self::$conn,'SELECT coalesce(sum(coalesce(debet,0)-coalesce(kredit,0)),0) AS vat FROM transaktioner WHERE kladde_id=$1 AND kontonr=987003',[$journal])[0]['vat'];
            $this->assertSame($expected,number_format((float)$ledgerVat,2,'.',''),json_encode([
                'ledger'=>RestApiEnv::rows(self::$conn,'SELECT kontonr,debet,kredit,moms FROM transaktioner WHERE kladde_id=$1',[$journal]),
                'line'=>RestApiEnv::rows(self::$conn,'SELECT debetvat,kreditvat,momsfri FROM kassekladde WHERE kladde_id=$1',[$journal])]));
            $posted = $this->get($journal);
            $this->assertTrue($posted['json']['data']['journal']['posted']);
            $this->assertSame($item['vat_amount'],$posted['json']['data']['items'][0]['vat_amount']);
        }
    }

    public function testMixedCurrencyTotalsUseDatedWebConversionWithoutMixingRowCurrencies(): void
    {
        $journal = $this->journal();
        $this->line($journal,1);
        $foreign = $this->line($journal,2);
        RestApiEnv::rows(self::$conn,"INSERT INTO grupper(art,kodenr,box1,box3,fiscal_year) VALUES ('VK',987,'EUR','',$1)",[$this->year]);
        RestApiEnv::rows(self::$conn,"INSERT INTO valuta(gruppe,valdate,kurs) VALUES
            (987,'2026-01-01',745.123),(987,'2026-06-01',746.456),(987,'2026-12-01',999)");
        RestApiEnv::rows(self::$conn,'UPDATE kassekladde SET valuta=987,valutakurs=746.456 WHERE id=$1',[$foreign['line']['id']]);
        RestApiEnv::rows(self::$conn,"UPDATE mobile_journal_lines SET currency='EUR' WHERE kassekladde_id=$1",[$foreign['line']['id']]);
        $response = $this->get($journal);
        $this->assertSame(200,$response['status'],$response['body']);
        $data = $response['json']['data'];
        $this->assertSame(['amount'=>'1058.07','vat'=>'211.61'],$data['totals']);
        $this->assertSame('EUR',$data['items'][1]['currency']);
        $this->assertSame('125.00',$data['items'][1]['amount']);
        $this->assertSame('25.00',$data['items'][1]['vat_amount']);
        $this->postThroughWeb($journal);
        $ledger = RestApiEnv::rows(self::$conn,'SELECT
            sum(CASE WHEN kontonr IN (987001,987003) THEN coalesce(debet,0)-coalesce(kredit,0) ELSE 0 END) AS amount,
            sum(CASE WHEN kontonr=987003 THEN coalesce(debet,0)-coalesce(kredit,0) ELSE 0 END) AS vat
            FROM transaktioner WHERE kladde_id=$1',[$journal])[0];
        $this->assertSame($data['totals'],array_map(static fn($v)=>number_format((float)$v,2,'.',''),$ledger));
        RestApiEnv::rows(self::$conn,'DELETE FROM valuta WHERE gruppe=987');
        $missing = $this->get($journal);
        $this->assertSame(422,$missing['status'],$missing['body']);
        $this->assertSame('missing_exchange_rate',$missing['json']['data']['details']['reason']);
    }

    public function testCurrencyFollowsWebEditsInsteadOfApprovalCurrency(): void
    {
        $journal = $this->journal();
        $fixture = $this->line($journal, 1);
        RestApiEnv::rows(self::$conn,"INSERT INTO grupper(art,kodenr,box1,box3,fiscal_year) VALUES ('VK',986,'EUR','',$1)",[$this->year]);
        RestApiEnv::rows(self::$conn,"INSERT INTO valuta(gruppe,valdate,kurs) VALUES (986,'2026-01-01',750)");
        RestApiEnv::rows(self::$conn,"UPDATE mobile_journal_lines SET currency='EUR' WHERE kassekladde_id=$1",[$fixture['line']['id']]);
        RestApiEnv::rows(self::$conn,'UPDATE kassekladde SET valuta=986,valutakurs=750 WHERE id=$1',[$fixture['line']['id']]);
        $this->assertSame('EUR', $this->get($journal)['json']['data']['items'][0]['currency']);
        // Saving the approved EUR row as DKK in the web leaves approval provenance intact.
        RestApiEnv::rows(self::$conn,'UPDATE kassekladde SET valuta=0,valutakurs=100,amount=250 WHERE id=$1',[$fixture['line']['id']]);
        $edited = $this->get($journal)['json']['data'];
        $this->assertSame('DKK', $edited['items'][0]['currency']);
        $this->assertSame('250.00', $edited['items'][0]['amount']);
        $this->assertSame(['amount'=>'250.00','vat'=>'50.00'], $edited['totals']);
        // The reverse edit also uses the current row even when approval said DKK.
        RestApiEnv::rows(self::$conn,"UPDATE mobile_journal_lines SET currency='DKK' WHERE kassekladde_id=$1",[$fixture['line']['id']]);
        RestApiEnv::rows(self::$conn,'UPDATE kassekladde SET valuta=986,valutakurs=750 WHERE id=$1',[$fixture['line']['id']]);
        $foreign = $this->get($journal)['json']['data'];
        $this->assertSame('EUR', $foreign['items'][0]['currency']);
        $this->assertSame(['amount'=>'1875.00','vat'=>'375.00'], $foreign['totals']);
    }

    public function testBankDraftUsesJournalCurrencyAndBaseCurrencySetting(): void
    {
        $journal = $this->journal();
        $fixture = $this->line($journal, 1, 'K1', false, false, true, 'bank_row');
        RestApiEnv::rows(self::$conn,"UPDATE pool_files SET currency='EUR',amount='-125,00',norm_amount=-125 WHERE id=$1",[$fixture['receipt']['id']]);
        RestApiEnv::rows(self::$conn,"UPDATE mobile_journal_lines SET currency='EUR' WHERE kassekladde_id=$1",[$fixture['line']['id']]);
        $response = $this->get($journal);
        $this->assertSame(200, $response['status'], $response['body']);
        $this->assertSame('DKK', $response['json']['data']['items'][0]['currency']);
        $this->assertSame('125.00', $response['json']['data']['items'][0]['amount']);
        RestApiEnv::rows(self::$conn,"INSERT INTO settings(var_grp,var_name,var_value,user_id) VALUES ('mobile','baseCurrency','USD',0) RETURNING id");
        try {
            $response = $this->get($journal);
            $this->assertSame(200, $response['status'], $response['body']);
            $this->assertSame('USD', $response['json']['data']['items'][0]['currency']);
            $this->assertSame(['amount'=>'125.00','vat'=>'25.00'], $response['json']['data']['totals']);
        } finally {
            RestApiEnv::rows(self::$conn,"DELETE FROM settings WHERE var_grp='mobile' AND var_name='baseCurrency' AND var_value='USD'");
        }
    }

    public function testNegativeFinanceReceiptKeepsExpenseSideAfterWebAccountCorrection(): void
    {
        RestApiEnv::rows(self::$conn,"INSERT INTO kontoplan(kontonr,regnskabsaar,kontotype,lukket,beskrivelse,moms,saldo,primo)
            VALUES (987004,$1,'D',NULL,'Corrected expense','K1',0,0)",[$this->year]);
        $receipt = $this->reviewFixture();
        $bytes = '%PDF-1.4 credit note ' . RequestId::resolve(null);
        file_put_contents(self::$root . '/' . $receipt['storage_path'], $bytes);
        chmod(self::$root . '/' . $receipt['storage_path'], 0666);
        RestApiEnv::rows(self::$conn,"UPDATE pool_files SET account='987001',amount='-125,00',norm_amount=-125,sha256=$1 WHERE id=$2",
            [hash('sha256', $bytes), $receipt['id']]);
        RestApiEnv::rows(self::$conn,"DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        $receipt = $this->row((int)$receipt['id']);
        $approval = RestApiEnv::http('POST', '/restapi/endpoints/v1/attachment/approve.php',
            ['id'=>(int)$receipt['id'],'client_id'=>RequestId::resolve(null),'kontonr'=>987001,'vat_code'=>'K1',
                'contra'=>['type'=>'F','kontonr'=>'987002']],
            array_merge(self::$reviewerHeaders, ['If-Match: "' . pool_review_version($receipt) . '"']));
        $this->assertSame(200, $approval['status'], $approval['body']);
        $draft = $approval['json']['data']['draft'];
        $journal = $draft['kladde_id'];
        // The journal engine swapped the negative receipt, including its VAT codes.
        $line = RestApiEnv::rows(self::$conn,'SELECT * FROM kassekladde WHERE id=$1',[$draft['kassekladde_id']])[0];
        $this->assertSame('987001', $line['kredit']);
        $this->assertSame('987002', $line['debet']);
        RestApiEnv::rows(self::$conn,'UPDATE kassekladde SET kredit=987004 WHERE id=$1',[$line['id']]);
        $response = $this->get($journal);
        $this->assertSame(200, $response['status'], $response['body']);
        $item = $response['json']['data']['items'][0];
        $this->assertSame(987004, $item['kontonr']);
        $this->assertSame('Corrected expense', $item['account_name']);
        $this->assertSame('-125.00', $item['amount']);
        $this->assertSame('-25.00', $item['vat_amount']);
        $this->assertSame('K1', $item['vat_code']);
        $this->assertSame(['type'=>'F','kontonr'=>'987002','name'=>'Bank'], $item['contra']);
        $this->assertSame(['amount'=>'-125.00','vat'=>'-25.00'], $response['json']['data']['totals']);
        // Later edits of receipt metadata cannot erase the approval's original negative direction.
        RestApiEnv::rows(self::$conn,"UPDATE pool_files SET amount='125,00',norm_amount=125,account='987004' WHERE id=$1",[$receipt['id']]);
        $this->assertSame($item, $this->get($journal)['json']['data']['items'][0]);
        RestApiEnv::rows(self::$conn,"UPDATE kassekladde SET kreditvat='K2' WHERE id=$1",[$line['id']]);
        $edited = $this->get($journal)['json']['data'];
        $this->assertSame('K2', $edited['items'][0]['vat_code']);
        $this->assertSame('-11.36', $edited['items'][0]['vat_amount']);
        $this->assertSame(['amount'=>'-125.00','vat'=>'-11.36'], $edited['totals']);
        $this->postThroughWeb($journal);
        $ledgerVat = RestApiEnv::rows(self::$conn,'SELECT sum(coalesce(debet,0)-coalesce(kredit,0)) AS vat
            FROM transaktioner WHERE kladde_id=$1 AND kontonr=987003',[$journal])[0]['vat'];
        $this->assertSame('-11.36', number_format((float)$ledgerVat, 2, '.', ''));
    }

    /** @return void Drive the unchanged browser posting runner under an owned, temporary online session. */
    private function postThroughWeb(int $journal): void
    {
        $session = bin2hex(random_bytes(16));
        RestApiEnv::rows(self::$registry,'INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
            VALUES ($1,$2,$3,$4,$5,$6,$7,false,0)',[$session,'t3_reviewer',RestApiEnv::testDb(),RestApiEnv::pgUser(),str_repeat('1',16),$this->year,(string)time()]);
        try {
            $process = proc_open([PHP_BINARY,__DIR__.'/../characterization/support/run_bogfor_page.php','bogfor',(string)$journal,$session],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
            $this->assertSame(0,proc_close($process),$stderr . $stdout);
            $this->assertNotEmpty(RestApiEnv::rows(self::$conn,'SELECT id FROM transaktioner WHERE kladde_id=$1',[$journal]),$stderr . $stdout);
        } finally {
            RestApiEnv::rows(self::$registry,'DELETE FROM online WHERE session_id=$1 AND db=$2',[$session,RestApiEnv::testDb()]);
            $file = sys_get_temp_dir().'/sess_'.$session;
            if (is_file($file)) { unlink($file); }
        }
    }

    public function testEmptyDefaultDoesNotCreateJournalAndRejectsRecycledPointer(): void
    {
        RestApiEnv::rows(self::$conn,"DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        $before = count(RestApiEnv::rows(self::$conn,'SELECT id FROM kladdeliste'));
        $empty = $this->get(null);
        $this->assertSame(200,$empty['status'],$empty['body']);
        $this->assertNull($empty['json']['data']['journal']);
        $this->assertSame(0,$empty['json']['data']['total']);
        $this->assertSame($before,count(RestApiEnv::rows(self::$conn,'SELECT id FROM kladdeliste')));
        $web = $this->journal(false);
        RestApiEnv::rows(self::$conn,"INSERT INTO settings(var_grp,var_name,var_value,user_id) VALUES ('mobile','approval_kladde_id',$1,0)",[(string)$web]);
        $this->assertNull($this->get(null)['json']['data']['journal']);
        $mobile = $this->journal();
        $this->line($mobile,1);
        RestApiEnv::rows(self::$conn,"UPDATE settings SET var_value=$1 WHERE var_grp='mobile' AND var_name='approval_kladde_id'",[(string)$mobile]);
        $this->assertSame($mobile,$this->get(null)['json']['data']['journal']['kladde_id']);
        $this->assertSame(404,$this->get(2147483647)['status']);
        foreach (['kladde_id=0','kladde_id[]=1','limit=0','offset=-1'] as $query) { $this->assertSame(422,$this->get(null,$query)['status']); }
        $this->assertSame(405,RestApiEnv::http('POST','/restapi/endpoints/v1/journal/drafts.php',[],self::$reviewerHeaders)['status']);
    }
}
