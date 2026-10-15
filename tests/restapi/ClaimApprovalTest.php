<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/ClaimApprovalTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Regress claim accounting on unposted bank rows and refused renames beside unrelated XML documents.

require_once __DIR__ . '/support/T4ClaimsTestCase.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolReviewVersion.php';

/** E1–E6 exercise the public claim/approval paths and compare real web accounting. */
final class ClaimApprovalTest extends T4ClaimsTestCase
{
    private static string $session;
    private static int $year;
    private static int $mask;
    public static function setUpBeforeClass(): void
    {
        self::$mask = umask(0);
        parent::setUpBeforeClass();
        self::$year = (int)$GLOBALS['regnaar'];
        self::sql("UPDATE kontoplan SET beskrivelse='Claim fixture',saldo=0,moms='' WHERE kontonr IN (987610,987611) AND regnskabsaar=$1", [self::$year]);
        self::sql("INSERT INTO kontoplan(kontonr,beskrivelse,kontotype,lukket,moms,saldo,regnskabsaar) VALUES (987612,'Expenses','D','','',0,$1)", [self::$year]);
        self::$session = bin2hex(random_bytes(16));
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows($master, 'INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id) VALUES ($1,$2,$3,$4,$5,$6,$7,false,1)',
            [self::$session,RestApiEnv::user(),RestApiEnv::testDb(),RestApiEnv::pgUser(),str_repeat('1',16),self::$year,time()]);
        pg_close($master);
    }
    public static function tearDownAfterClass(): void
    {
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows($master,'DELETE FROM online WHERE session_id=$1',[self::$session]);
        pg_close($master);
        parent::tearDownAfterClass();
        umask(self::$mask);
    }
    protected function setUp(): void
    {
        self::sql("UPDATE mobile_mileage_statements SET state='withdrawn'");
        self::sql('UPDATE mobile_mileage_trips SET deleted_at=now()');
        self::sql("DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        self::sql('UPDATE brugere SET ansat_id=$1 WHERE id=$2', [self::$employeeId,self::$userId]);
        self::setting('km_threshold_2026','20000');
        self::setting('km_rate_2026','3.79');
        self::setting('claims_payable_account','987610');
    }
    private function row(int $id): array { return self::sql('SELECT * FROM pool_files WHERE id=$1',[$id])[0]; }
    private function trip(string $date='2026-10-01',string $km='12.5',?int $user=null): int
    {
        return $this->service($user)->createTrip($this->tripBody(['date'=>$date,'km'=>$km]))['body']['data']['id'];
    }
    private function issue(string $from='2026-10-01',string $to='2026-10-31',?int $user=null): array
    {
        return $this->service($user)->issueStatement($this->body(['period_from'=>$from,'period_to'=>$to]))['body']['data'];
    }
    private function approve(array $row,array $decision=[]): array
    {
        return $this->request('POST','attachment/approve.php',$decision+$this->body(['id'=>(int)$row['id']]),
            array_merge(self::$reviewerHeaders,['If-Match: "'.pool_review_version($row).'"']));
    }
    private function expense(): array
    {
        $row=$this->fixture();
        file_put_contents(self::$root.'/'.$row['storage_path'],'%PDF-1.4 claim '.RequestId::resolve(null));
        chmod(self::$root.'/'.$row['storage_path'],0666);
        self::sql("UPDATE pool_files SET account='987612',amount='612.50',description='Employee expense',invoice_number='EXP-E1' WHERE id=$1",[$row['id']]);
        $this->assertStatus(201,$this->request('POST','employee/expenses.php',$this->body(['attachment_id'=>(int)$row['id']])));
        return $this->row((int)$row['id']);
    }
    private function golden(string $case,array $actual): void
    {
        $this->assertSame(json_decode(file_get_contents(__DIR__.'/fixtures/t4/golden/'.$case.'.json'),true,512,JSON_THROW_ON_ERROR),$actual,$case);
    }
    /** Full draft and posting equivalence, plus a stable financial golden independent of sequence ids. */
    private function equivalent(array $row,string $case,bool $own=true,array $decision=[]): array
    {
        self::sql("DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        $balances=self::sql('SELECT id,saldo FROM kontoplan ORDER BY id');
        $before=self::sql('SELECT * FROM transaktioner ORDER BY id');
        $result=$this->assertStatus(200,$this->approve($row,$decision));
        $this->assertSame($before,self::sql('SELECT * FROM transaktioner ORDER BY id'),'Approval only creates a draft');
        $draft=$result['draft'];
        $line=self::sql('SELECT * FROM kassekladde WHERE id=$1',[$draft['kassekladde_id']])[0];
        $this->assertSame($own ? (string)self::$employeeId : '0',$line['ansat']);
        $webJournal=(int)self::sql("INSERT INTO kladdeliste(id,kladdedate,kladdenote,bogfort,oprettet_af) VALUES ((SELECT COALESCE(MAX(id),0)+1 FROM kladdeliste),$1,'Claim comparison','-',$2) RETURNING id",[$row['file_date'],RestApiEnv::user()])[0]['id'];
        $webLine=(int)self::sql("INSERT INTO kassekladde(kladde_id,bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,valuta,valutakurs,pos,debetvat,kreditvat,momsfri) VALUES ($1,$2,$3,'Before web save','F',987612,'F',987610,10,0,100,1,'','','') RETURNING id",[$webJournal,$line['bilag'],$row['file_date']])[0]['id'];
        $form=['save'=>'on','antal_ny'=>1,'antal_ex'=>1,'fokus'=>'besk1','tidspkt'=>microtime(),'id'=>[1=>$webLine],
            'bila1'=>$line['bilag'],'dato1'=>(new DateTimeImmutable($row['file_date']))->format('d-m-Y'),'besk1'=>$row['description'],
            'd_ty1'=>'F','debe1'=>$line['debet'],'k_ty1'=>'F','kred1'=>$line['kredit'],
            'belo1'=>number_format((float)$row['amount'],2,',','.'),'fakt1'=>$row['invoice_number'],'valu1'=>'DKK',
            'afd_1'=>'','proj1'=>'','meda1'=>$own ? 'FE' : '','forf1'=>'','b_id1'=>'','dvat1'=>'','kvat1'=>'',
            'dkka1'=>$row['amount'],'kontrolkonto'=>'','kladdenote'=>'Claim comparison'];
        if ($line['momsfri']==='on') { $form['moms1']='on'; }
        $this->web('run_kassekladde_page.php',[$webJournal,self::$session,json_encode($form,JSON_THROW_ON_ERROR)]);
        $web=self::sql('SELECT * FROM kassekladde WHERE id=$1',[$webLine])[0];
        $fields=['transdate','beskrivelse','d_type','debet','k_type','kredit','amount','faktura','valuta','valutakurs','debetvat','kreditvat','momsfri','ansat'];
        $this->assertSame(array_intersect_key($web,array_flip($fields)),array_intersect_key($line,array_flip($fields)),'Actual web save');
        $posted=$this->posting($draft['kladde_id']);
        self::sql('DELETE FROM transaktioner WHERE kladde_id=$1',[$draft['kladde_id']]);
        self::sql('DELETE FROM openpost WHERE kladde_id=$1',[$draft['kladde_id']]);
        foreach ($balances as $balance) { self::sql('UPDATE kontoplan SET saldo=$1 WHERE id=$2',[$balance['saldo'],$balance['id']]); }
        $this->assertSame($this->posting($webJournal),$posted,'Actual web posting');
        $financial=['debet'=>(int)$line['debet'],'kredit'=>(int)$line['kredit'],'amount'=>number_format((float)$line['amount'],2,'.',''),
            'debetvat'=>$line['debetvat'],'kreditvat'=>$line['kreditvat'],'momsfri'=>$line['momsfri'],'employee'=>$own ? 'claim_employee' : null];
        if ($case !== '') { $this->golden($case,$financial); }
        return $financial;
    }
    private function posting(int $journal): array
    {
        $before=self::sql('SELECT kontonr,saldo FROM kontoplan WHERE regnskabsaar=$1 ORDER BY kontonr',[self::$year]);
        $output=$this->web('run_bogfor_page.php',['bogfor',$journal,self::$session]);
        $rows=self::sql('SELECT kontonr,debet,kredit,moms,beskrivelse,faktura,valuta,valutakurs,ansat FROM transaktioner WHERE kladde_id=$1 ORDER BY id',[$journal]);
        $this->assertNotEmpty($rows,$output['stdout'].$output['stderr']);
        $after=self::sql('SELECT kontonr,saldo FROM kontoplan WHERE regnskabsaar=$1 ORDER BY kontonr',[self::$year]);
        $delta=[];
        foreach ($after as $i=>$account) {
            $change=round((float)$account['saldo']-(float)$before[$i]['saldo'],3);
            if ($change !== 0.0) { $delta[]=['kontonr'=>$account['kontonr'],'delta'=>number_format($change,3,'.','')]; }
        }
        return ['transactions'=>$rows,'openpost'=>self::sql('SELECT konto_nr,faktnr,amount,transdate,valuta,valutakurs FROM openpost WHERE kladde_id=$1 ORDER BY id',[$journal]),'saldo_delta'=>$delta];
    }
    private function web(string $runner,array $args): array
    {
        $master=RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows($master,'UPDATE online SET logtime=$1 WHERE session_id=$2',[time(),self::$session]);
        pg_close($master);
        $path=__DIR__.'/../characterization/support/'.$runner;
        if ($runner==='run_t4_claim_docpool.php') { $path=__DIR__.'/support/'.$runner; }
        // File-backed capture keeps a rendered page's legacy diagnostics from filling a pipe.
        $stdoutPath=tempnam(sys_get_temp_dir(),'t4-claim-out-');
        $stderrPath=tempnam(sys_get_temp_dir(),'t4-claim-err-');
        try {
            $process=proc_open([PHP_BINARY,$path,...array_map('strval',$args)], [0=>['file','/dev/null','r'],1=>['file',$stdoutPath,'w'],2=>['file',$stderrPath,'w']],$pipes,null,['SALDI_CHAR_TEST_DB'=>RestApiEnv::testDb()]+getenv());
            $exit=proc_close($process);
            $stdout=file_get_contents($stdoutPath); $stderr=file_get_contents($stderrPath);
        } finally {
            unlink($stdoutPath); unlink($stderrPath);
        }
        $this->assertSame(0,$exit,$stdout.$stderr);
        $this->assertStringNotContainsString('PHP Fatal error:',$stderr);
        return ['stdout'=>$stdout,'stderr'=>$stderr];
    }
    public function testE1ExpenseDefaultsUseRecordedEmployeeAndMatchWebPosting(): void
    {
        $row=$this->expense();
        self::sql('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$userId]);
        $review=$this->assertStatus(200,$this->request('GET','attachment/review.php?id='.$row['id'],null,self::$reviewerHeaders));
        $this->assertSame(['kind'=>'udlaeg','employee'=>['ansat_id'=>self::$employeeId,'name'=>'Field employee','initials'=>'FE'],
            'defaults'=>['contra'=>['type'=>'F','kontonr'=>987610],'vat_exempt'=>false]],$review['claim']);
        $this->equivalent($row,'E1');
    }
    public function testE2StatementCrossingThresholdIsVatExemptAndMatchesWebPosting(): void
    {
        $this->statement($this->service()->allowanceScope(),2026,'19990.0','sent');
        $this->trip('2026-10-01','20.0');
        $statement=$this->issue();
        $this->assertSame('60.20',$statement['amount']);
        $this->assertSame('10.0',$statement['lines'][0]['km_high']);
        $this->assertSame('10.0',$statement['lines'][0]['km_low']);
        $row=$this->row($statement['attachment_id']);
        $review=$this->assertStatus(200,$this->request('GET','attachment/review.php?id='.$row['id'],null,self::$reviewerHeaders));
        $this->assertTrue($review['claim']['defaults']['vat_exempt']);
        $this->equivalent($row,'E2',true,['vat_exempt'=>false]);
    }
    public function testE3DeletingStatementReleasesTripsAndReplacementClaimsExactlyOnce(): void
    {
        $trip=$this->trip(); $first=$this->issue();
        $version=self::sql('SELECT version FROM mobile_mileage_trips WHERE id=$1',[$trip])[0]['version'];
        $this->assertStatus(200,$this->request('DELETE','attachment/?id='.$first['attachment_id']));
        $released=self::sql('SELECT statement_id,version FROM mobile_mileage_trips WHERE id=$1',[$trip])[0];
        $this->assertNull($released['statement_id']);
        $this->assertSame((int)$version+1,(int)$released['version']);
        $this->assertStatus(200,$this->request('DELETE','attachment/?id='.$first['attachment_id']));
        $this->assertSame($released,self::sql('SELECT statement_id,version FROM mobile_mileage_trips WHERE id=$1',[$trip])[0]);
        $replacement=$this->issue();
        $this->assertNotSame($first['statement_id'],$replacement['statement_id']);
        $this->assertSame([$trip],array_column($replacement['lines'],'trip_id'));
        $this->golden('E3',['old_state'=>self::sql('SELECT state FROM mobile_mileage_statements WHERE id=$1',[$first['statement_id']])[0]['state'],
            'replacement_amount'=>$replacement['amount'],'active_allocations'=>(int)self::sql("SELECT count(*) AS n FROM mobile_mileage_allocations a JOIN mobile_mileage_statements s ON s.id=a.statement_id WHERE s.state <> 'withdrawn'")[0]['n']]);
    }
    public function testE4ForeignEmployeeOmitsDimensionAndWarnsWhileKeepingPayable(): void
    {
        $foreign=(int)self::sql("INSERT INTO adresser(art,firmanavn) VALUES ('D','Foreign company') RETURNING id")[0]['id'];
        $employee=(int)self::sql("INSERT INTO ansatte(navn,initialer,konto_id,lukket) VALUES ('Foreign employee','FX',$1,'') RETURNING id",[$foreign])[0]['id'];
        self::sql('UPDATE brugere SET ansat_id=$1 WHERE id=$2',[$employee,self::$userId]);
        $row=$this->expense();
        $review=$this->assertStatus(200,$this->request('GET','attachment/review.php?id='.$row['id'],null,self::$reviewerHeaders));
        $this->assertSame($employee,$review['claim']['employee']['ansat_id']);
        // Inspect resolved warnings without consuming a second approval or posting side effects.
        $result=$this->assertStatus(200,$this->approve($row));
        $this->assertContains('not_own_company',array_column($result['warnings'],'code'));
        $line=self::sql('SELECT ansat,kredit,amount FROM kassekladde WHERE id=$1',[$result['draft']['kassekladde_id']])[0];
        $this->golden('E4',['ansat'=>(int)$line['ansat'],'kredit'=>(int)$line['kredit'],'amount'=>number_format((float)$line['amount'],2,'.',''),'warning'=>'not_own_company']);
        // A second foreign claim also traverses the actual web save and posting comparison.
        $this->equivalent($this->expense(),'',false);
    }
    public function testE5MissingRateWritesNoStatementPoolRowOrAllocation(): void
    {
        $this->trip('2026-10-01','42.0'); self::setting('km_rate_2026','');
        $tables=['mobile_mileage_statements','mobile_mileage_allocations','pool_files','mobile_expense_claims'];
        $before=[]; foreach ($tables as $table) { $before[$table]=self::sql('SELECT * FROM '.$table.' ORDER BY 1,2'); }
        $error=$this->assertStatus(422,$this->request('POST','employee/mileage/statements.php',$this->body(['period_from'=>'2026-10-01','period_to'=>'2026-10-31'])));
        foreach ($tables as $table) { $this->assertSame($before[$table],self::sql('SELECT * FROM '.$table.' ORDER BY 1,2')); }
        $this->golden('E5',$error);
    }
    public function testE6TwoLoginsReverseMonthsShareAnnualAllowanceAndMatchWebPosting(): void
    {
        $this->statement($this->service()->allowanceScope(),2026,'19900.0','sent');
        $this->trip('2026-01-01','60.0'); $this->trip('2026-02-01','60.0',self::$otherId);
        $february=$this->issue('2026-02-01','2026-02-28',self::$otherId);
        $january=$this->issue('2026-01-01','2026-01-31');
        $allocation=self::sql("SELECT sum(a.km_high)::text AS high,sum(a.km_low)::text AS low FROM mobile_mileage_allocations a JOIN mobile_mileage_statements s ON s.id=a.statement_id WHERE s.state <> 'withdrawn'")[0];
        $this->golden('E6',['february_amount'=>$february['amount'],'january_amount'=>$january['amount'],'allocation'=>$allocation]);
        $this->equivalent($this->row($february['attachment_id']),'');
        $this->equivalent($this->row($january['attachment_id']),'');
    }
    public function testGeneratedFieldsRefusedByOwnerApprovalWebAndSqlWithNoAccountingChanges(): void
    {
        $this->trip(); $statement=$this->issue(); $row=$this->row($statement['attachment_id']);
        $accounting=self::sql('SELECT * FROM kassekladde ORDER BY id');
        foreach (['amount'=>'999.00','date'=>'2099-01-01','currency'=>'EUR','description'=>'Changed','invoice_number'=>'Changed'] as $field=>$value) {
            $headers=array_merge(self::$headers,['If-Match: "'.pool_review_version($row).'"']);
            $owner=$this->assertStatus(409,$this->request('PATCH','attachment/metadata.php?id='.$row['id'],$this->body([$field=>$value]),$headers));
            $this->assertSame('generated_document',$owner['details']['reason']);
            $reviewer=$this->assertStatus(409,$this->approve($row,[$field=>$value]));
            $this->assertSame('generated_document',$reviewer['details']['reason']);
            $this->assertSame($row,$this->row((int)$row['id']));
            $metadata=[$field==='date' ? 'file_date' : $field=>$value];
            try { poolMetadataSave($row['filename'],$metadata,true,poolMetadataVersion($row),self::$year); $this->fail('Generated metadata accepted'); }
            catch (RuntimeException $error) { $this->assertSame(409,$error->getCode()); $this->assertSame('generated_document',$error->getMessage()); }
            $this->assertSame($row,$this->row((int)$row['id']));
        }
        foreach ([null,'','K1'] as $vat) {
            $error=$this->assertStatus(422,$this->approve($row,['vat_code'=>$vat]));
            $this->assertSame('vat_code_invalid',$error['details']['reason']);
        }
        foreach (['rename','direct'] as $branch) {
            $target=RequestId::resolve(null).'.pdf';
            if ($branch==='direct') { file_put_contents(self::$root.'/pulje/'.$target,'%PDF-1.4 occupied'); }
            self::sql("INSERT INTO settings(var_name,var_grp,var_value,user_id) VALUES ('skip_sync','docs',$1,0)",[(string)time()]);
            $form=['rename'=>'on','poolFile'=>$row['filename'],'newFileName'=>$target,'newAmount'=>'999.00','newAccount'=>$row['account'],'poolVersion'=>poolMetadataVersion($row)];
            $output=$this->web('run_t4_claim_docpool.php',[RestApiEnv::testDb(),self::$userId,RestApiEnv::user(),self::$year,json_encode($form,JSON_THROW_ON_ERROR)]);
            $this->assertStringContainsString('Kørselsbilag er lavet af Saldi og kan ikke rettes.',$output['stdout']);
            $this->assertStringContainsString('STATUS:409',$output['stdout']);
            $this->assertSame($row,$this->row((int)$row['id']),$branch);
            $this->assertFileExists(self::$root.'/'.$row['storage_path']);
            if ($branch==='direct') { unlink(self::$root.'/pulje/'.$target); }
        }
        self::sql('BEGIN');
        try {
            $this->assertTrue(pg_send_query_params(self::$conn,'UPDATE pool_files SET amount=$1 WHERE id=$2',['999.00',$row['id']]));
            $result=pg_get_result(self::$conn);
            $this->assertSame(PGSQL_FATAL_ERROR,pg_result_status($result));
            $this->assertSame('55000',pg_result_error_field($result,PGSQL_DIAG_SQLSTATE));
            $this->assertStringContainsString('generated_document',pg_result_error($result));
            while (pg_get_result(self::$conn)!==false) { }
        } finally { self::sql('ROLLBACK'); }
        $this->assertSame($row,$this->row((int)$row['id']));
        $this->assertSame($accounting,self::sql('SELECT * FROM kassekladde ORDER BY id'));
    }
    public function testStatementAccountAndDisplayChangesAreAllowedAndExplicitContraWins(): void
    {
        $this->trip(); $statement=$this->issue(); $row=$this->row($statement['attachment_id']);
        $this->assertStatus(200,$this->request('PATCH','attachment/metadata.php?id='.$row['id'],$this->body(['kontonr'=>987612,'vendor'=>'Display label']),
            array_merge(self::$headers,['If-Match: "'.pool_review_version($row).'"'])));
        $changed=$this->row((int)$row['id']);
        foreach (['amount','file_date','currency','description','invoice_number'] as $field) { $this->assertSame($row[$field],$changed[$field]); }
        $this->assertSame('987612',$changed['account']);
        $result=$this->assertStatus(200,$this->approve($changed,['contra'=>['type'=>'F','kontonr'=>987611]]));
        $line=self::sql('SELECT debet,kredit,momsfri FROM kassekladde WHERE id=$1',[$result['draft']['kassekladde_id']])[0];
        $this->assertSame(['debet'=>'987612','kredit'=>'987611','momsfri'=>'on'],$line);
    }
    public function testPostedBankStatementRefusesOverridesAndVatButCanAttachUnchanged(): void
    {
        $this->trip(); $statement=$this->issue(); $row=$this->row($statement['attachment_id']);
        $journal=(int)self::sql("INSERT INTO kladdeliste(id,kladdedate,kladdenote,bogfort,oprettet_af) VALUES ((SELECT COALESCE(MAX(id),0)+1 FROM kladdeliste),'2026-10-01','Posted claim','V','claims_reviewer') RETURNING id")[0]['id'];
        $line=(int)self::sql("INSERT INTO kassekladde(kladde_id,bilag,transdate,debet,kredit,d_type,k_type,amount) VALUES ($1,42,'2026-10-01',987611,987610,'F','F',47.38) RETURNING id",[$journal])[0]['id'];
        $import=(int)self::sql("INSERT INTO bank_line_imports(source,file_sha256,imported_by,account_kontonr,kladde_id) VALUES ('csv',$1,$2,987610,$3) RETURNING id",[hash('sha256',RequestId::resolve(null)),self::$owner,$journal])[0]['id'];
        $bank=(int)self::sql("INSERT INTO bank_lines(first_import_id,line_key,account_kontonr,source,amount,transdate,text,currency) VALUES ($1,$2,987610,'csv',-47.38,'2026-10-01','Claim','DKK') RETURNING id",[$import,hash('sha256',RequestId::resolve(null))])[0]['id'];
        self::sql('INSERT INTO bank_line_journal_links(bank_line_id,kassekladde_id,import_id) VALUES ($1,$2,$3)',[$bank,$line,$import]);
        self::sql('UPDATE pool_files SET bank_line_id=$1 WHERE id=$2',[$bank,$row['id']]); $row=$this->row((int)$row['id']);
        $before=self::sql('SELECT * FROM kassekladde ORDER BY id');
        foreach (['amount'=>'999.00','date'=>'2099-01-01','currency'=>'EUR','description'=>'Changed','invoice_number'=>'Changed'] as $field=>$value) {
            $error=$this->assertStatus(409,$this->approve($row,[$field=>$value]));
            $this->assertSame('generated_document',$error['details']['reason']);
            $this->assertSame($row,$this->row((int)$row['id']));
        }
        $error=$this->assertStatus(422,$this->approve($row,['vat_code'=>null])); $this->assertSame('vat_code_invalid',$error['details']['reason']);
        $result=$this->assertStatus(200,$this->approve($row)); $this->assertSame('attach_only',$result['draft']['mode']);
        $this->assertSame($before,self::sql('SELECT * FROM kassekladde ORDER BY id'));
    }

    /** @return array<string,array{string,int}> Claim kinds with and without an existing debit classification. */
    public static function bankClaimCases(): array
    {
        return ['expense empty debit'=>['udlaeg',0], 'expense retained debit'=>['udlaeg',987611],
            'mileage empty debit'=>['koersel',0], 'mileage retained debit'=>['koersel',987612]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bankClaimCases')]
    public function testUnpostedBankClaimsApplyEmployeeAndStatementExemption(string $kind,int $debit): void
    {
        if ($kind==='koersel') {
            $this->trip();
            $row=$this->row($this->issue()['attachment_id']);
        } else {
            $row=$this->expense();
        }
        // Approval uses the claim's recorded employee even after the login link changes.
        self::sql('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$userId]);
        $journal=(int)self::sql("INSERT INTO kladdeliste(id,kladdedate,kladdenote,bogfort,oprettet_af) VALUES ((SELECT COALESCE(MAX(id),0)+1 FROM kladdeliste),$1,'Bank claim','-','claims_reviewer') RETURNING id",[$row['file_date']])[0]['id'];
        $lineId=(int)self::sql("INSERT INTO kassekladde(kladde_id,bilag,transdate,beskrivelse,debet,kredit,d_type,k_type,amount,valuta,valutakurs,pos,debetvat,kreditvat,momsfri,ansat) VALUES ($1,42,$2,'Imported bank claim',$3,987610,'F','F',$4,0,100,1,'K1','S1','',0) RETURNING id",[$journal,$row['file_date'],$debit,$row['amount']])[0]['id'];
        $import=(int)self::sql("INSERT INTO bank_line_imports(source,file_sha256,imported_by,account_kontonr,kladde_id) VALUES ('csv',$1,$2,987610,$3) RETURNING id",[hash('sha256',RequestId::resolve(null)),self::$owner,$journal])[0]['id'];
        $bank=(int)self::sql("INSERT INTO bank_lines(first_import_id,line_key,account_kontonr,source,amount,transdate,text,currency) VALUES ($1,$2,987610,'csv',$3,$4,'Claim','DKK') RETURNING id",[$import,hash('sha256',RequestId::resolve(null)),-(float)$row['amount'],$row['file_date']])[0]['id'];
        self::sql('INSERT INTO bank_line_journal_links(bank_line_id,kassekladde_id,import_id) VALUES ($1,$2,$3)',[$bank,$lineId,$import]);
        self::sql('UPDATE pool_files SET bank_line_id=$1 WHERE id=$2',[$bank,$row['id']]);
        $before=self::sql('SELECT * FROM kassekladde WHERE id=$1',[$lineId])[0];
        $accounting=self::sql('SELECT * FROM transaktioner ORDER BY id');
        $lineCount=count(self::sql('SELECT id FROM kassekladde'));
        $result=$this->assertStatus(200,$this->approve($this->row((int)$row['id'])));
        $this->assertSame('bank_row',$result['draft']['mode']);
        $this->assertSame($lineId,$result['draft']['kassekladde_id']);
        $after=self::sql('SELECT * FROM kassekladde WHERE id=$1',[$lineId])[0];
        $expected=$before;
        $expected['ansat']=(string)self::$employeeId;
        if ($debit===0) {
            $expected['debet']=$row['account'];
            $expected['debetvat']='';
        } else {
            $this->assertContains('line_account_kept',array_column($result['warnings'],'code'));
        }
        if ($kind==='koersel') {
            $expected['debetvat']='';
            $expected['kreditvat']='';
            $expected['momsfri']='on';
        }
        $this->assertSame($expected,$after);
        $this->assertSame($lineCount,count(self::sql('SELECT id FROM kassekladde')));
        $this->assertSame($accounting,self::sql('SELECT * FROM transaktioner ORDER BY id'));
        $this->assertSame('attached',$this->row((int)$row['id'])['status']);
        $this->assertNotEmpty(self::sql("SELECT id FROM documents WHERE pool_file_id=$1 AND source='kassekladde' AND source_id=$2",[$row['id'],$lineId]));
    }

    public function testRefusedGeneratedRenameLeavesUnrelatedDestinationXmlUntouched(): void
    {
        $this->trip();
        $row=$this->row($this->issue()['attachment_id']);
        $targetBase=RequestId::resolve(null);
        $xmlName=$targetBase.'.xml';
        $xmlPath=self::$root.'/pulje/'.$xmlName;
        $xmlBytes='<?xml version="1.0"?><invoice>Unrelated destination document</invoice>';
        file_put_contents($xmlPath,$xmlBytes);
        chmod($xmlPath,0666);
        $xml=self::sql("INSERT INTO pool_files(filename,storage_path,status,subject,amount) VALUES ($1,$2,'ubehandlet','Unrelated XML','25.00') RETURNING *",[$xmlName,'pulje/'.$xmlName])[0];
        $originalHash=hash_file('sha256',self::$root.'/'.$row['storage_path']);
        $before=self::sql('SELECT * FROM pool_files ORDER BY id');
        $audit=self::sql('SELECT * FROM pool_file_audit ORDER BY id');
        self::sql("INSERT INTO settings(var_name,var_grp,var_value,user_id) VALUES ('skip_sync','docs',$1,0)",[(string)time()]);
        $form=['rename'=>'on','poolFile'=>$row['filename'],'newFileName'=>$targetBase.'.pdf','newAmount'=>'999.00',
            'newAccount'=>$row['account'],'poolVersion'=>poolMetadataVersion($row)];
        $output=$this->web('run_t4_claim_docpool.php',[RestApiEnv::testDb(),self::$userId,RestApiEnv::user(),self::$year,json_encode($form,JSON_THROW_ON_ERROR)]);
        $this->assertStringContainsString('Kørselsbilag er lavet af Saldi og kan ikke rettes.',$output['stdout']);
        $this->assertStringContainsString('STATUS:409',$output['stdout']);
        $this->assertFileExists($xmlPath);
        $this->assertSame($xmlBytes,file_get_contents($xmlPath));
        $this->assertSame($xml,$this->row((int)$xml['id']));
        $this->assertSame($before,self::sql('SELECT * FROM pool_files ORDER BY id'));
        $this->assertSame($audit,self::sql('SELECT * FROM pool_file_audit ORDER BY id'),'Refusal happens before any rename or metadata write');
        $this->assertSame($originalHash,hash_file('sha256',self::$root.'/'.$row['storage_path']));
        $this->assertFileDoesNotExist(self::$root.'/pulje/'.$targetBase.'.pdf');
        $this->assertFileDoesNotExist(self::$root.'/pulje/'.pathinfo($row['filename'],PATHINFO_FILENAME).'.xml');

        // The same destination remains usable when only the account/display name changes.
        $form['newAmount']=$row['amount'];
        $form['newAccount']='987612';
        $form['newSubject']='Mileage display label';
        $output=$this->web('run_t4_claim_docpool.php',[RestApiEnv::testDb(),self::$userId,RestApiEnv::user(),self::$year,json_encode($form,JSON_THROW_ON_ERROR)]);
        $this->assertStringNotContainsString('STATUS:409',$output['stdout']);
        $changed=$this->row((int)$row['id']);
        $this->assertSame($targetBase.'.pdf',$changed['filename']);
        $this->assertSame('pulje/'.$targetBase.'.pdf',$changed['storage_path']);
        $this->assertSame('987612',$changed['account']);
        $this->assertSame('Mileage display label',$changed['subject']);
        foreach (['amount','file_date','currency','description','invoice_number'] as $field) {
            $this->assertSame($row[$field],$changed[$field]);
        }
        $this->assertSame($originalHash,hash_file('sha256',self::$root.'/'.$changed['storage_path']));
        $this->assertFileDoesNotExist(self::$root.'/'.$row['storage_path']);
        $this->assertSame($xmlBytes,file_get_contents($xmlPath));
        $this->assertSame($xml,$this->row((int)$xml['id']));
    }

}
