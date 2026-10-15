<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T3Seed.php --- ver 5.0.0 --- 2026.10.06
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

require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../../restapi/core/RequestId.php';
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../restapi/services/AttachmentApproveService.php';

/** Disposable T3 tenant and real sessions; also used by the later app contract runner. */
final class T3Seed
{
    public const V1 = '/restapi/endpoints/v1/';
    public $tenant;
    public $registry;
    public int $tenantId;
    public int $year;
    public string $root;
    public string $session;
    public array $tokens = [];
    public array $subjects = [];
    public array $users = [];
    private array $savedGlobals = [];
    private array $savedServer;
    private int $umask;
    public array $contracts = [];

    public function __construct()
    {
        $this->savedServer = $_SERVER;
        $this->umask = umask(0);
        foreach (['connection','db_type','db','db_skriv_id','bruger_id','brugernavn','revisor','db_transaktion_depth','db_modify_fejl'] as $key) {
            $this->savedGlobals[$key] = [array_key_exists($key,$GLOBALS),ifset($GLOBALS,$key)];
        }
        $_SERVER['REQUEST_URI'] = '/tests/t3-x1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        RestApiEnv::bootstrapTenant();
        $this->tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $this->registry = RestApiEnv::connect(RestApiEnv::masterDb());
        mobileTenantUpdates($this->tenant);
        mobileRegistryUpdates($this->registry);
        $this->tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        $this->tokens['full'] = RestApiEnv::accessToken();
        $this->subjects['full'] = Subject::build('account',(int)RestApiEnv::loginData()['user']['id']);
        $GLOBALS['connection'] = $this->tenant;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db'] = RestApiEnv::testDb();
        $GLOBALS['db_skriv_id'] = 0;
        $GLOBALS['bruger_id'] = RestApiEnv::loginData()['user']['id'];
        $GLOBALS['brugernavn'] = RestApiEnv::user();
        $GLOBALS['revisor'] = false;
        $this->root = dirname(poolPuljePath(RestApiEnv::testDb()));
        if (!is_dir($this->root . '/pulje')) {
            mkdir($this->root . '/pulje',0777,true);
        }
        chmod($this->root,0777);
        chmod($this->root . '/pulje',0777);
        $this->year = (int)$this->sql("SELECT kodenr FROM grupper WHERE art='RA' AND box2='2026' ORDER BY kodenr DESC LIMIT 1")[0]['kodenr'];
        $this->sql("INSERT INTO kontoplan(kontonr,beskrivelse,kontotype,lukket,moms,regnskabsaar,saldo) VALUES
            (987001,'T3 purchase','D','','K987',$1,0),(987002,'Closed','D','on','',$1,0),
            (987010,'Bank','S','','',$1,0),(987011,'Purchase VAT','S','','',$1,0),
            (987012,'Reverse VAT','S','','',$1,0),(987013,'Creditors','S','','',$1,0)",[$this->year]);
        $this->sql("INSERT INTO grupper(kode,kodenr,beskrivelse,art,box1,box2,box3,fiscal_year) VALUES
            ('K',987,'Purchase VAT','KM','987011','25','',$1),('K',988,'Alternative VAT','KM','987011','10','',$1),
            ('E',989,'Reverse VAT','EM','987011','25','987012',$1),('',987,'EUR','VK','EUR','','',$1),
            ('',987,'Suppliers','KG','','987013','',$1)",[$this->year]);
        $this->sql("INSERT INTO valuta(gruppe,valdate,kurs) VALUES (987,'2026-01-01',745.123),(987,'2026-06-01',746.456),(987,'2026-12-01',999)");
        $this->sql("INSERT INTO adresser(kontonr,firmanavn,art,lukket,gruppe) VALUES ('987003','T3 supplier','K','',987)");
        foreach (['vendor_name'=>'text','vendor_cvr'=>'text','vendor_iban'=>'text','vendor_candidates'=>'text','vendor_match'=>'text','vendor_konto_id'=>'integer','vendor_score'=>'numeric'] as $field=>$type) {
            $this->sql('ALTER TABLE pool_files ADD COLUMN IF NOT EXISTS ' . $field . ' ' . $type);
        }
        $this->session = bin2hex(random_bytes(16));
        RestApiEnv::rows($this->registry,'INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id) VALUES ($1,$2,$3,$4,$5,$6,$7,false,1)',
            [$this->session,RestApiEnv::user(),RestApiEnv::testDb(),RestApiEnv::pgUser(),str_repeat('1',16),$this->year,time()]);
    }

    /** @return list<array<string,string|null>> Parameterized fixture queries on the owned tenant. */
    public function sql(string $sql,array $params=[]): array
    {
        return RestApiEnv::rows($this->tenant,$sql,$params);
    }

    public static function uuid(): string
    {
        return RequestId::resolve(null);
    }

    /** Seed receipt-only, unrelated, read-only, write and registry principals through actual logins. */
    public function personas(): void
    {
        foreach (['receipt'=>str_repeat('0',16),'other'=>str_repeat('0',16),'unrelated'=>'1'.str_repeat('0',15),
            'read'=>'0020000000000000','write'=>'0010000000000000'] as $persona=>$rights) {
            $name = 't3x1_' . bin2hex(random_bytes(6));
            $password = TestCredentials::password('t3x1_' . $persona);
            $row = $this->sql('INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES($1,$2,$3,true) RETURNING *',[$name,md5($password),$rights])[0];
            $this->users[$persona] = $row;
            $response = self::http('POST','auth/login.php',['account_name'=>RestApiEnv::accountOpen(),'username'=>$name,'password'=>$password,'device_id'=>'t3x1-' . $persona]);
            $this->tokens[$persona] = self::token($response);
            $this->subjects[$persona] = Subject::build('account',(int)$row['id']);
        }
        $name = 't3x1r_' . bin2hex(random_bytes(6));
        $password = TestCredentials::password('t3x1_registry');
        $this->users['registry'] = RestApiEnv::rows($this->registry,'INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES($1,$2,$3,true) RETURNING *',
            [$name,md5($password),',,,' . $this->tenantId])[0];
        $this->subjects['registry'] = Subject::build('registry',(int)$this->users['registry']['id']);
        $login = self::http('POST','auth/registry-login.php',['username'=>$name,'password'=>$password,'device_id'=>'t3x1-registry']);
        $this->tokens['registryRoot'] = self::token($login);
        $this->tokens['registry'] = self::token($this->request('POST','auth/tenant-token.php',['tenant_id'=>$this->tenantId],'registryRoot'));
    }

    private static function token(array $response): string
    {
        if ($response['status'] !== 200 || !isset($response['json']['data']['access_token'])) {
            throw new RuntimeException('T3 seeded login failed: ' . $response['body']);
        }
        return $response['json']['data']['access_token'];
    }

    /** @return array{status:int,json:?array,body:string,headers:array<string,string>} HTTP response with replay/correlation headers. */
    public static function http(string $method,string $path,?array $body=null,?string $token=null,array $headers=[]): array
    {
        $received = [];
        $ch = curl_init(RestApiEnv::baseUrl() . self::V1 . $path);
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $headers[] = 'Accept: application/json';
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));
        }
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>60,
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_HTTPHEADER=>$headers,CURLOPT_HEADERFUNCTION=>static function ($ch,string $line) use (&$received): int {
                if (str_contains($line,':')) {
                    [$name,$value] = explode(':',$line,2);
                    $received[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            }]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new RuntimeException('T3 HTTP failed: ' . curl_error($ch));
        }
        $status = (int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status'=>$status,'json'=>json_decode($raw,true),'body'=>$raw,'headers'=>$received];
    }

    public function request(string $method,string $path,?array $body=null,string $persona='full',array $headers=[]): array
    {
        return self::http($method,$path,$body,$this->tokens[$persona],$headers);
    }

    /** @return array<string,string|null> A retained receipt and contained file, with a certain supplier match. */
    public function receipt(array $values=[],string $persona='full'): array
    {
        $name = self::uuid() . '.pdf';
        $bytes = '%PDF-1.4 receipt ' . self::uuid();
        file_put_contents($this->root . '/pulje/' . $name,$bytes);
        chmod($this->root . '/pulje/' . $name,0666);
        $vendor = (int)$this->sql("SELECT id FROM adresser WHERE kontonr='987003' AND art='K'")[0]['id'];
        $row = $this->sql("INSERT INTO pool_files(filename,uploaded_by,status,review_state,subject,description,amount,norm_amount,file_date,currency,storage_path,account,sha256,invoice_number,vendor_name,vendor_match,vendor_score,vendor_konto_id,client_id)
            VALUES($1,$2,'ubehandlet','pending','T3 supplier','T3 purchase','125,00',125,'2026-09-14','DKK',$3,'987001',$4,'INV-T3','T3 supplier','cvr',1,$5,$6) RETURNING *",
            [$name,$this->subjects[$persona],'pulje/' . $name,hash('sha256',$bytes),$vendor,self::uuid()])[0];
        foreach ($values as $field=>$value) {
            if (!preg_match('/\A[a-z0-9_]+\z/',$field)) {
                throw new LogicException('Invalid fixture field');
            }
            $this->sql('UPDATE pool_files SET ' . pg_escape_identifier($this->tenant,$field) . '=$1 WHERE id=$2',[$value,$row['id']]);
        }
        return $this->row((int)$row['id']);
    }

    public function row(int $id): array
    {
        return $this->sql('SELECT * FROM pool_files WHERE id=$1',[$id])[0];
    }

    public function journal(string $posted='-'): int
    {
        return (int)$this->sql("INSERT INTO kladdeliste(id,kladdedate,kladdenote,bogfort,oprettet_af) VALUES ((SELECT COALESCE(MAX(id),0)+1 FROM kladdeliste),'2026-09-14','T3 bank',$1,$2) RETURNING id",[$posted,RestApiEnv::user()])[0]['id'];
    }

    /** @return array{array,int,int,int} Receipt, journal id, journal line id, durable bank identity. */
    public function bankReceipt(string $posted='-',string $persona='full'): array
    {
        $row = $this->receipt([],$persona);
        $journal = $this->journal($posted);
        $line = (int)$this->sql("INSERT INTO kassekladde(kladde_id,bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,valuta,valutakurs,pos,debetvat,kreditvat,momsfri,faktura) VALUES ($1,42,'2026-09-14','T3 purchase','F',0,'F',987010,125,0,100,1,'','','','INV-T3') RETURNING id",[$journal])[0]['id'];
        $import = (int)$this->sql("INSERT INTO bank_line_imports(source,file_sha256,imported_by,account_kontonr,kladde_id) VALUES ('csv',$1,$2,987010,$3) RETURNING id",[hash('sha256',self::uuid()),$this->subjects[$persona],$journal])[0]['id'];
        $bank = (int)$this->sql("INSERT INTO bank_lines(first_import_id,line_key,account_kontonr,source,amount,transdate,text,currency) VALUES ($1,$2,987010,'csv',-125,'2026-09-14','T3 purchase','DKK') RETURNING id",[$import,hash('sha256',self::uuid())])[0]['id'];
        $this->sql('INSERT INTO bank_line_journal_links(bank_line_id,kassekladde_id,import_id) VALUES ($1,$2,$3)',[$bank,$line,$import]);
        $this->sql('UPDATE pool_files SET bank_line_id=$1 WHERE id=$2',[$bank,$row['id']]);
        return [$this->row((int)$row['id']),$journal,$line,$bank];
    }

    /** Drive a real web runner in an isolated process against this owned tenant. */
    public function web(string $runner,array $args): array
    {
        RestApiEnv::rows($this->registry,'UPDATE online SET logtime=$1 WHERE session_id=$2',[time(),$this->session]);
        $process = proc_open([PHP_BINARY,__DIR__ . '/../../characterization/support/' . $runner,...array_map('strval',$args)],
            [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,['SALDI_CHAR_TEST_DB'=>RestApiEnv::testDb()]+getenv());
        if (!is_resource($process)) {
            throw new RuntimeException('Web runner unavailable');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['exit'=>proc_close($process),'stdout'=>$stdout,'stderr'=>$stderr];
    }

    public function close(): void
    {
        $output = getenv('SALDI_T3_CONTRACT_OUTPUT');
        if ($output && $this->contracts) {
            file_put_contents($output,json_encode(['cases'=>$this->contracts],JSON_THROW_ON_ERROR));
        }
        foreach ($this->subjects as $persona=>$subject) {
            foreach (['refresh_sessions','auth_versions','auth_challenges'] as $table) {
                $scope = $persona === 'registry' ? '' : ' AND tenant_id=$2';
                RestApiEnv::rows($this->registry,'DELETE FROM ' . $table . ' WHERE subject=$1' . $scope,
                    $persona === 'registry' ? [$subject] : [$subject,$this->tenantId]);
            }
        }
        foreach ($this->users as $persona=>$user) {
            RestApiEnv::rows($this->registry,"DELETE FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$1",
                [MobileAuth::bucketKey($persona === 'registry' ? 'registry' : 'account',$persona === 'registry' ? '' : RestApiEnv::accountOpen(),$user['brugernavn'])]);
        }
        if (isset($this->users['registry'])) {
            RestApiEnv::rows($this->registry,'DELETE FROM brugere WHERE id=$1',[$this->users['registry']['id']]);
        }
        RestApiEnv::rows($this->registry,'DELETE FROM online WHERE session_id=$1',[$this->session]);
        RestApiEnv::rows($this->registry,'DELETE FROM mobile_summary_cache WHERE tenant_id=$1',[$this->tenantId]);
        pg_close($this->tenant);
        pg_close($this->registry);
        RestApiEnv::teardownTenant();
        foreach ([$this->root,dirname(__DIR__,3) . '/temp/' . RestApiEnv::testDb()] as $path) {
            if (is_dir($path)) {
                self::remove($path);
            }
        }
        $sessionFile = sys_get_temp_dir() . '/sess_' . $this->session;
        if (is_file($sessionFile)) {
            unlink($sessionFile);
        }
        foreach ($this->savedGlobals as $key=>[$exists,$value]) {
            if ($exists) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
        $_SERVER = $this->savedServer;
        umask($this->umask);
    }

    private static function remove(string $path): void
    {
        foreach (new FilesystemIterator($path) as $file) {
            if ($file->isDir() && !$file->isLink()) {
                self::remove($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($path);
    }
}
