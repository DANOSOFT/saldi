<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T4ClaimsTestCase.php --- ver 5.0.0 --- 2026.10.06
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
require_once __DIR__.'/RestApiEnv.php';
require_once __DIR__.'/../../../includes/std_func.php';
require_once __DIR__.'/../../../includes/db_query.php';
require_once __DIR__.'/../../../includes/mobileUpdates.php';
require_once __DIR__.'/../../../restapi/services/ClaimsService.php';
require_once __DIR__.'/../../../restapi/models/attachment/AttachmentModel.php';

/** Every class gets its own disposable tenant; no provider or production connection. */
abstract class T4ClaimsTestCase extends TestCase
{
    protected static $conn;
    protected static array $headers;
    protected static array $otherHeaders;
    protected static array $reviewerHeaders;
    protected static string $owner;
    protected static int $userId;
    protected static int $otherId;
    protected static int $employeeId;
    protected static string $root;
    private static array $savedGlobals;
    private static array $savedServer;
    public static function setUpBeforeClass(): void
    {
        if ($reason=RestApiEnv::unavailableReason()) { self::markTestSkipped($reason); }
        self::$savedServer=$_SERVER;
        $_SERVER['REQUEST_URI']='/tests/t4-b6a';
        $_SERVER['REMOTE_ADDR']='127.0.0.1';
        foreach (['connection','db_type','db','db_skriv_id','bruger_id','brugernavn','revisor','regnaar','db_transaktion_depth','db_modify_fejl'] as $key) {
            self::$savedGlobals[$key]=[array_key_exists($key,$GLOBALS),ifset($GLOBALS,$key)];
        }
        RestApiEnv::bootstrapTenant();
        self::$conn=RestApiEnv::connect(RestApiEnv::testDb());
        mobileTenantUpdates(self::$conn);
        self::sql('UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[str_repeat('0',16),RestApiEnv::user()]);
        self::$headers=RestApiEnv::authHeaders();
        self::$userId=(int)RestApiEnv::loginData()['user']['id'];
        self::$owner=Subject::build('account',self::$userId);
        $own=self::sql("SELECT id FROM adresser WHERE art='S' ORDER BY id LIMIT 1");
        $company=$own ? (int)$own[0]['id'] : (int)self::sql("INSERT INTO adresser(art,firmanavn) VALUES ('S','Claim fixture') RETURNING id")[0]['id'];
        self::$employeeId=(int)self::sql("INSERT INTO ansatte(navn,initialer,nummer,konto_id,lukket) VALUES ('Field employee','FE','42',$1,'') RETURNING id",[$company])[0]['id'];
        self::sql('UPDATE brugere SET ansat_id=$1 WHERE id=$2',[self::$employeeId,self::$userId]);
        foreach (['claims_other'=>str_repeat('0',16),'claims_reviewer'=>'0010000000000000'] as $name=>$rights) {
            $row=self::sql('INSERT INTO brugere(brugernavn,kode,email,rettigheder,status,ansat_id) VALUES ($1,$2,$3,$4,true,$5) RETURNING id',
                [$name,md5(TestCredentials::password($name)),$name.'@example.invalid',$rights,self::$employeeId])[0];
            $login=RestApiEnv::login($name,TestCredentials::password($name),RestApiEnv::accountOpen());
            if ($login['status']!==200) { throw new RuntimeException('Fixture login failed: '.$login['body']); }
            $headers=['Authorization: Bearer '.$login['json']['data']['access_token']];
            if ($name==='claims_other') { self::$otherHeaders=$headers; self::$otherId=(int)$row['id']; }
            else { self::$reviewerHeaders=$headers; }
        }
        $GLOBALS['connection']=self::$conn;
        $GLOBALS['db_type']='postgresql';
        $GLOBALS['db']=RestApiEnv::testDb();
        $GLOBALS['db_skriv_id']=0;
        $GLOBALS['bruger_id']=self::$userId;
        $GLOBALS['brugernavn']=RestApiEnv::user();
        $GLOBALS['revisor']=false;
        $GLOBALS['regnaar']=(int)self::sql("SELECT kodenr FROM grupper WHERE art='RA' AND box2='2026' ORDER BY id LIMIT 1")[0]['kodenr'];
        self::sql("INSERT INTO kontoplan(kontonr,regnskabsaar,kontotype,lukket) VALUES (987610,$1,'S',''),(987611,$1,'D','')",[$GLOBALS['regnaar']]);
        self::setting('claims_payable_account','987610');
        self::setting('claims_mileage_account','987611');
        self::setting('km_rate_2026','3.79');
        self::setting('km_rate_low_2026','2.23');
        self::setting('km_threshold_2026','20000');
        self::setting('km_rate_2027','4.00');
        self::setting('km_rate_low_2027','2.00');
        self::$root=dirname(poolPuljePath(RestApiEnv::testDb()));
        if (!is_dir(self::$root.'/pulje')) { mkdir(self::$root.'/pulje',0777,true); }
        chmod(self::$root,0777);
        chmod(self::$root.'/pulje',0777);
    }
    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            pg_close(self::$conn);
            foreach ([self::$root,dirname(__DIR__,3).'/temp/'.RestApiEnv::testDb()] as $path) {
                if (is_dir($path)) { self::remove($path); }
            }
            foreach (self::$savedGlobals as $key=>[$exists,$value]) {
                if ($exists) { $GLOBALS[$key]=$value; } else { unset($GLOBALS[$key]); }
            }
            $_SERVER=self::$savedServer;
        }
        RestApiEnv::teardownTenant();
    }
    private static function remove(string $path): void
    {
        foreach (new FilesystemIterator($path) as $file) {
            if ($file->isDir() && !$file->isLink()) { self::remove($file->getPathname()); }
            else { unlink($file->getPathname()); }
        }
        rmdir($path);
    }
    protected static function sql(string $sql,array $params=[]): array { return RestApiEnv::rows(self::$conn,$sql,$params); }
    protected static function setting(string $name,string $value): void
    {
        self::sql("DELETE FROM settings WHERE var_grp='mobile_marken' AND user_id=0 AND var_name=$1",[$name]);
        self::sql("INSERT INTO settings(var_grp,var_name,var_value,user_id) VALUES ('mobile_marken',$1,$2,0)",[$name,$value]);
    }
    protected function service(?int $user=null): ClaimsService
    {
        $id=$user ?? self::$userId;
        return new ClaimsService(self::$conn,RestApiEnv::testDb(),Subject::build('account',$id),'account',$id);
    }
    protected function body(array $fields=[]): array { return ['client_id'=>RequestId::resolve(null)]+$fields; }
    protected function tripBody(array $fields=[]): array
    {
        return $this->body($fields+['date'=>'2026-10-01','from'=>'Office','to'=>'Customer','km'=>'12.5','purpose'=>'Site visit']);
    }
    protected function fixture(string $status='ubehandlet',string $review='pending'): array
    {
        $name=RequestId::resolve(null).'.pdf';
        return self::sql('INSERT INTO pool_files(filename,uploaded_by,status,review_state,subject,description,amount,file_date,currency,storage_path)
            VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10) RETURNING *',
            [$name,self::$owner,$status,$review,'Supplier','Original','10.00','2026-10-01','DKK','pulje/'.$name])[0];
    }
    protected function request(string $method,string $route,?array $body=null,?array $headers=null): array
    {
        return RestApiEnv::http($method,'/restapi/endpoints/v1/'.$route,$body,$headers ?? self::$headers);
    }
    protected function assertStatus(int $status,array $response): array
    {
        $this->assertSame($status,$response['status'],$response['body']);
        return $response['json']['data'] ?? [];
    }
    protected function statement(string $scope,int $year,string $high,string $state='generating'): int
    {
        $id=(int)self::sql("INSERT INTO mobile_mileage_statements(client_id,subject,ansat_id,period_from,period_to,km_total,amount,rates_json,lines_json,state)
            VALUES ($1,$2,$3,$4,$4,$5,0,'{}','[]',$6) RETURNING id",[RequestId::resolve(null),self::$owner,self::$employeeId,$year.'-01-01',$high,$state])[0]['id'];
        self::sql('INSERT INTO mobile_mileage_allocations(statement_id,scope,year,km_high,km_low) VALUES ($1,$2,$3,$4,0)',[$id,$scope,$year,$high]);
        return $id;
    }
    protected function payload(): array
    {
        $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Resources << >> /Contents 4 0 R >>',"<< /Length 0 >>\nstream\n\nendstream"];
        $pdf="%PDF-1.4\n% claim\n";
        $offsets=[];
        foreach ($objects as $i=>$object) { $offsets[]=strlen($pdf); $pdf.=($i+1)." 0 obj\n".$object."\nendobj\n"; }
        $xref=strlen($pdf);
        $pdf.="xref\n0 5\n0000000000 65535 f \n";
        foreach ($offsets as $offset) { $pdf.=sprintf("%010d 00000 n \n",$offset); }
        $pdf.="trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
        return ['id'=>RequestId::resolve(null),'sha256'=>hash('sha256',$pdf),'size'=>strlen($pdf),'image_base64'=>base64_encode($pdf),
            'filename'=>'receipt.pdf','extracted_data'=>['total_amount'=>'612.50','invoice_date'=>'2026-10-01','currency'=>'DKK','vendor'=>'Claim receipt']];
    }
}
