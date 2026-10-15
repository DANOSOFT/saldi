<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T3B2bTestCase.php --- ver 5.0.0 --- 2026.10.05
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

require_once __DIR__.'/T3B2aTestCase.php';
require_once __DIR__.'/../../../restapi/services/AttachmentApproveService.php';

abstract class T3B2bTestCase extends T3B2aTestCase
{
    protected static int $year;
    protected static string $session;
    protected static int $vendorId;
    private static int $umask;
    public static function setUpBeforeClass(): void
    {
        self::$umask=umask(0);
        parent::setUpBeforeClass();
        self::$year=(int)RestApiEnv::rows(self::$conn,"SELECT kodenr FROM grupper WHERE art='RA' AND box2='2026' ORDER BY kodenr DESC LIMIT 1")[0]['kodenr'];
        RestApiEnv::rows(self::$conn,"UPDATE kontoplan SET beskrivelse='T3 purchase',moms='K987',saldo=0,lukket='' WHERE kontonr=987001 AND regnskabsaar=$1",[self::$year]);
        RestApiEnv::rows(self::$conn,"UPDATE grupper SET box1='987011',box2='25',fiscal_year=$1 WHERE kode='K' AND kodenr=987 AND art='KM'",[self::$year]);
        RestApiEnv::rows(self::$conn,"INSERT INTO kontoplan(kontonr,beskrivelse,kontotype,lukket,moms,regnskabsaar,saldo) VALUES
            (987002,'Closed','D','on','',$1,0),(987010,'Bank','S','','',$1,0),
            (987011,'Purchase VAT','S','','',$1,0),(987012,'Reverse VAT','S','','',$1,0),(987013,'Creditors','S','','',$1,0)",[self::$year]);
        RestApiEnv::rows(self::$conn,"INSERT INTO grupper(kode,kodenr,beskrivelse,art,box1,box2,box3,fiscal_year) VALUES
            ('K',988,'Alternative VAT','KM','987011','10','',$1),('E',989,'Reverse VAT','EM','987011','25','987012',$1),
            ('',987,'EUR','VK','EUR','','',$1),('',987,'Suppliers','KG','','987013','',$1)",[self::$year]);
        RestApiEnv::rows(self::$conn,"INSERT INTO valuta(gruppe,valdate,kurs) VALUES (987,'2026-01-01',745.123),(987,'2026-06-01',746.456),(987,'2026-12-01',999)");
        RestApiEnv::rows(self::$conn,"INSERT INTO adresser(kontonr,firmanavn,art,lukket,gruppe) VALUES ('987003','T3 supplier','K','',987)");
        self::$vendorId=(int)RestApiEnv::rows(self::$conn,"SELECT id FROM adresser WHERE kontonr='987003' AND art='K'")[0]['id'];
        foreach (['vendor_name'=>'text','vendor_cvr'=>'text','vendor_iban'=>'text','vendor_candidates'=>'text','vendor_match'=>'text','vendor_konto_id'=>'integer','vendor_score'=>'numeric'] as $field=>$type) { RestApiEnv::rows(self::$conn,'ALTER TABLE pool_files ADD COLUMN IF NOT EXISTS '.$field.' '.$type); }
        self::$session=bin2hex(random_bytes(16));
        $master=RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows($master,'INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id) VALUES ($1,$2,$3,$4,$5,$6,$7,false,1)',
            [self::$session,'t3_reviewer',RestApiEnv::testDb(),RestApiEnv::pgUser(),str_repeat('1',16),self::$year,time()]);
        pg_close($master);
    }
    public static function tearDownAfterClass(): void
    {
        if (isset(self::$session)) {
            $master=RestApiEnv::connect(RestApiEnv::masterDb());
            RestApiEnv::rows($master,'DELETE FROM online WHERE session_id=$1',[self::$session]);
            pg_close($master);
        }
        parent::tearDownAfterClass();
        umask(self::$umask);
    }
    protected function receipt(array $values=[]): array
    {
        $row=$this->fixture();
        $bytes='%PDF-1.4 receipt '.RequestId::resolve(null);
        file_put_contents(self::$root.'/'.$row['storage_path'],$bytes);
        chmod(self::$root.'/'.$row['storage_path'],0666);
        $values+=['account'=>'987001','sha256'=>hash('sha256',$bytes),'norm_amount'=>'125','amount'=>'125,00','description'=>'T3 purchase','invoice_number'=>'INV-T3','subject'=>'T3 supplier','vendor_name'=>'T3 supplier','vendor_match'=>'cvr','vendor_score'=>'1','vendor_konto_id'=>self::$vendorId];
        foreach ($values as $field=>$value) {
            if (!preg_match('/\A[a-z0-9_]+\z/',$field)) { throw new LogicException('Invalid fixture field'); }
            RestApiEnv::rows(self::$conn,'UPDATE pool_files SET '.$field.'=$1 WHERE id=$2',[$value,$row['id']]);
        }
        return $this->row((int)$row['id']);
    }
    protected function approve(array $row,array $fields=[],?array $headers=null,?string $etag=null): array
    {
        return RestApiEnv::http('POST','/restapi/endpoints/v1/attachment/approve.php',
            $fields+['id'=>(int)$row['id'],'client_id'=>RequestId::resolve(null)],
            array_merge($headers ?? self::$reviewerHeaders,['If-Match: '.($etag ?? '"'.pool_review_version($row).'"')]));
    }
    protected function approvalService(?Closure $hook=null,?string $subject=null,?string $username=null): AttachmentApproveService
    {
        $subject ??= self::$reviewer;
        return new AttachmentApproveService(self::$conn,RestApiEnv::testDb(),$subject,$username ?? 't3_reviewer',Subject::parse($subject)['user_id'],
            RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RequestId::resolve(null),$hook);
    }
    protected function journal(string $posted='-'): int
    {
        return (int)RestApiEnv::rows(self::$conn,"INSERT INTO kladdeliste(id,kladdedate,kladdenote,bogfort,oprettet_af) VALUES ((SELECT COALESCE(MAX(id),0)+1 FROM kladdeliste),'2026-09-14','T3 bank',$1,'t3_reviewer') RETURNING id",[$posted])[0]['id'];
    }
    protected function bankReceipt(string $posted='-',bool $siblings=false): array
    {
        $row=$this->receipt();
        $journal=$this->journal($posted);
        $line=(int)RestApiEnv::rows(self::$conn,"INSERT INTO kassekladde(kladde_id,bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,valuta,valutakurs,pos,debetvat,kreditvat,momsfri,faktura) VALUES ($1,42,'2026-09-14','T3 purchase','F',0,'F',987010,125,0,100,1,'','','','INV-T3') RETURNING id",[$journal])[0]['id'];
        $import=(int)RestApiEnv::rows(self::$conn,"INSERT INTO bank_line_imports(source,file_sha256,imported_by,account_kontonr,kladde_id) VALUES ('csv',$1,$2,987010,$3) RETURNING id",[hash('sha256',RequestId::resolve(null)),self::$reviewer,$journal])[0]['id'];
        $bank=(int)RestApiEnv::rows(self::$conn,"INSERT INTO bank_lines(first_import_id,line_key,account_kontonr,source,amount,transdate,text,currency) VALUES ($1,$2,987010,'web',-125,'2026-09-14','T3 purchase','DKK') RETURNING id",[$import,hash('sha256',RequestId::resolve(null))])[0]['id'];
        RestApiEnv::rows(self::$conn,'INSERT INTO bank_line_journal_links(bank_line_id,kassekladde_id,import_id) VALUES ($1,$2,$3)',[$bank,$line,$import]);
        RestApiEnv::rows(self::$conn,'UPDATE pool_files SET bank_line_id=$1 WHERE id=$2',[$bank,$row['id']]);
        if ($siblings) {
            RestApiEnv::rows(self::$conn,"INSERT INTO kassekladde(kladde_id,bilag,transdate,beskrivelse,amount) VALUES ($1,42,'2026-09-14','Sibling',0)",[$journal]);
        }
        return [$this->row((int)$row['id']),$journal,$line,$bank];
    }
    protected function child(string $script,array $args,array $environment=[]): array
    {
        $pipes=[];
        $process=proc_open([PHP_BINARY,$script,...array_map('strval',$args)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$environment+getenv());
        if (!is_resource($process)) { throw new RuntimeException('Child unavailable'); }
        fclose($pipes[0]);
        $stdout=stream_get_contents($pipes[1]); $stderr=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return ['exit'=>proc_close($process),'stdout'=>$stdout,'stderr'=>$stderr];
    }
    protected function accounting(): array
    {
        $result=[];
        foreach (['kassekladde','transaktioner','openpost'] as $table) { $result[$table]=RestApiEnv::rows(self::$conn,'SELECT * FROM '.$table.' ORDER BY id'); }
        $result['saldo']=RestApiEnv::rows(self::$conn,'SELECT id,saldo FROM kontoplan ORDER BY id');
        return $result;
    }
    protected function assertAttached(array $original,int $lineCount=1): array
    {
        $row=$this->row((int)$original['id']);
        $this->assertSame('attached',$row['status']); $this->assertSame('approved',$row['review_state']);
        $this->assertSame(self::$reviewer,$row['attached_by']);
        $this->assertSame($original['sha256'],hash_file('sha256',self::$root.'/'.$row['storage_path']));
        $docs=RestApiEnv::rows(self::$conn,'SELECT * FROM documents WHERE pool_file_id=$1 ORDER BY id',[$row['id']]);
        $this->assertCount($lineCount,$docs);
        $this->assertSame(array_map(fn($doc)=>(int)$doc['id'],$docs),json_decode($row['document_ids'],true));
        foreach ($docs as $doc) { $this->assertSame('/'.dirname($row['storage_path']),$doc['filepath']); }
        $this->assertCount(1,RestApiEnv::rows(self::$conn,'SELECT id FROM pool_files WHERE id=$1',[$row['id']]));
        return $row;
    }
}
