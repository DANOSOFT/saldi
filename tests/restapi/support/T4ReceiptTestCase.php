<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T4ReceiptTestCase.php --- ver 5.0.0 --- 2026.10.06
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
require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../../restapi/services/GoodsReceiptService.php';
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../lager/lagerstatusValue.php';
require_once __DIR__ . '/../../../restapi/core/MobileAuth.php';

/** Receipt fixtures have isolated rows, tokens, web sessions and a disposable tenant. */
abstract class T4ReceiptTestCase extends TestCase
{
    protected static $conn;
    protected static array $user;
    protected static string $subject;
    protected static array $headers;
    protected static int $year;
    protected static string $session;
    protected array $f;

    public static function setUpBeforeClass(): void
    {
        $_SERVER['REQUEST_URI'] = '/saldi/kreditor/modtag.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        // Login applies the mobile schema and provisions an actual account session.
        self::$headers = RestApiEnv::authHeaders();
        self::$conn = RestApiEnv::connect(RestApiEnv::testDb());
        self::$user = RestApiEnv::rows(self::$conn, 'SELECT * FROM brugere WHERE brugernavn=$1', [RestApiEnv::user()])[0];
        self::$subject = Subject::build('account', (int)self::$user['id']);
        self::$year = (int)RestApiEnv::rows(self::$conn, "SELECT max(kodenr) AS y FROM grupper WHERE art='RA'")[0]['y'];
        RestApiEnv::rows(self::$conn, "DELETE FROM grupper WHERE art='API'");
        RestApiEnv::rows(self::$conn, "UPDATE grupper SET box1='01',box2='2026',box3='12',box4='2026' WHERE art='RA' AND kodenr=$1", [self::$year]);
        RestApiEnv::rows(self::$conn, "INSERT INTO grupper(art,kodenr,fiscal_year,box3,box8,box9,beskrivelse)
            VALUES('VG',98282,$1,'4000','on','','Receipt fixture'),('LG',2,$1,'','','','Receipt warehouse')", [self::$year]);
        self::$session = 't4b2b' . bin2hex(random_bytes(12));
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows($master, 'INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
            VALUES($1,$2,$3,$4,$5,$6,$7,false,0)', [self::$session,RestApiEnv::user(),RestApiEnv::testDb(),RestApiEnv::pgUser(),
                str_repeat('9',50),self::$year,(string)time()]);
        pg_close($master);
        $dir = dirname(__DIR__,3) . '/temp/' . RestApiEnv::testDb();
        if (!is_dir($dir)) {
            mkdir($dir,0777,true);
            chmod($dir,0777);
        }
        touch($dir . '/rest_api.log');
        chmod($dir . '/rest_api.log',0666);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            $master = RestApiEnv::connect(RestApiEnv::masterDb());
            RestApiEnv::rows($master,'DELETE FROM online WHERE session_id=$1',[self::$session]);
            pg_close($master);
            pg_close(self::$conn);
            self::$conn = null;
            RestApiEnv::teardownTenant();
        }
    }

    protected function rows(string $sql, array $params = []): array
    {
        return RestApiEnv::rows(self::$conn, $sql, $params);
    }

    protected function setUp(): void
    {
        $this->rows('UPDATE brugere SET rettigheder=$1,status=true WHERE id=$2',[str_repeat('1',16),self::$user['id']]);
        $this->rows("UPDATE grupper SET box8='on',box9='' WHERE art='VG' AND kodenr=98282");
        $this->rows("DELETE FROM settings WHERE var_name='confirmStockChange'");
        $this->f = $this->fixture();
    }

    /** @return array{item:int,order:int,line:int,variant:int} A pristine order with stock already on warehouse 1. */
    protected function fixture(): array
    {
        $item = (int)$this->rows("INSERT INTO varer(varenr,beskrivelse,enhed,gruppe,kostpris,salgspris,beholdning,serienr,lukket,has_due_date,stregkode,varenr_alias)
            VALUES($1,'Receipt item','stk',98282,12.5,20,4,'','',false,'5701234567890','ALT') RETURNING id",['RC-' . bin2hex(random_bytes(5))])[0]['id'];
        $order = (int)$this->rows("INSERT INTO ordrer(art,status,ordrenr,ordredate,levdate,kontonr,firmanavn,hvem,tidspkt,ref)
            VALUES('KO',1,98282,'2026-10-01','2026-10-06','1234','Receipt supplier','',0,$1) RETURNING id",[RestApiEnv::user()])[0]['id'];
        $line = (int)$this->rows("INSERT INTO ordrelinjer(ordre_id,posnr,varenr,vare_id,antal,leveres,leveret,serienr,variant_id,lager,kred_linje_id,beskrivelse,enhed,pris)
            SELECT $1,1,varenr,id,10,0,0,'',0,2,0,beskrivelse,enhed,12.5 FROM varer WHERE id=$2 RETURNING id",[$order,$item])[0]['id'];
        $this->rows('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES($1,0,1,4)',[$item]);
        $this->rows("INSERT INTO batch_kob(vare_id,variant_id,linje_id,ordre_id,kobsdate,antal,rest,lager,pris) VALUES($1,0,0,0,'2026-09-01',4,4,1,12.5)",[$item]);
        return ['item'=>$item,'order'=>$order,'line'=>$line,'variant'=>0];
    }

    protected function service($conn = null): GoodsReceiptService
    {
        return new GoodsReceiptService(new EngineContext($conn ?? self::$conn,RestApiEnv::testDb(),
            RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RestApiEnv::user(),(int)self::$user['id'],'2026-10-06'),self::$subject);
    }

    protected function body(string $quantity = '6'): array
    {
        return ['client_id'=>MobileAuth::uuid(),'order_id'=>$this->f['order'],'receipt_date'=>'2026-10-06',
            'lines'=>[['line_id'=>$this->f['line'],'quantity'=>$quantity]]];
    }

    /** @return array{status:int,json:?array,body:string,headers:string} Capture replay and ETag headers. */
    protected function request(string $method, string $path, ?array $body = null, ?string $etag = null, ?array $headers = null): array
    {
        $headers = $headers ?? self::$headers;
        if ($etag !== null) {
            $headers[] = 'If-Match: ' . $etag;
        }
        $curl = curl_init(RestApiEnv::baseUrl() . '/restapi/endpoints/v1/creditor/receipts/' . $path);
        $responseHeaders = '';
        curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,
            CURLOPT_HTTPHEADER=>array_merge($headers,['Accept: application/json','Content-Type: application/json']),
            CURLOPT_HEADERFUNCTION=>static function ($handle,string $line) use (&$responseHeaders): int {
                $responseHeaders .= $line;
                return strlen($line);
            }]);
        if ($body !== null) {
            curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));
        }
        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return ['status'=>$status,'json'=>json_decode((string)$raw,true),'body'=>(string)$raw,'headers'=>$responseHeaders];
    }

    protected function detail(): array
    {
        $response = $this->request('GET','orders.php?id=' . $this->f['order']);
        self::assertSame(200,$response['status'],$response['body']);
        return $response['json']['data'];
    }

    protected function send(array $body, ?string $etag = null): array
    {
        return $this->request('POST','index.php',$body,$etag ?? $this->detail()['etag']);
    }

    protected function refused(array $response, int $status, string $reason, string $code = 'validation_failed'): void
    {
        self::assertSame($status,$response['status'],$response['body']);
        self::assertSame($code,$response['json']['data']['error']);
        if ($reason !== '') {
            self::assertSame($reason,$response['json']['data']['details']['reason']);
        }
    }

    /** @return array Immutable business evidence, with volatile clocks and equivalent fixture ids removed. */
    protected function stock(array $f): array
    {
        $result = [];
        $batchIds = array_column($this->rows('SELECT id FROM batch_kob WHERE vare_id=$1 ORDER BY id',[$f['item']]),'id');
        foreach (['lagerstatus'=>'variant_id,lager,beholdning',
            'batch_kob'=>'variant_id,linje_id,ordre_id,kobsdate,fakturadate,antal,rest,pris,lager,due_date,batch_no',
            'batch_salg'=>'variant_id,linje_id,ordre_id,antal,batch_kob_id,lager',
            'stocklog'=>'username,initials,correction,reason',
            'reservation'=>'linje_id,batch_kob_id,batch_salg_id,antal,lager'] as $table=>$columns) {
            $field = $table === 'stocklog' ? 'item_id' : 'vare_id';
            $rows = $this->rows("SELECT $columns FROM $table WHERE $field=$1 ORDER BY 1,2,3",[$f['item']]);
            foreach ($rows as &$row) {
                foreach (['variant_id'=>'variant','linje_id'=>'line','ordre_id'=>'order'] as $column=>$key) {
                    if (isset($row[$column]) && (int)$row[$column] === $f[$key] && $f[$key] !== 0) {
                        $row[$column] = '<' . $key . '>';
                    }
                }
                foreach (['linje_id'=>'sale_line','ordre_id'=>'sale_order'] as $column=>$key) {
                    if (isset($f[$key],$row[$column]) && (string)$row[$column] === (string)$f[$key]) {
                        $row[$column] = '<' . $key . '>';
                    }
                }
                if (isset($row['batch_kob_id']) && (int)$row['batch_kob_id'] > 0) {
                    $position = array_search($row['batch_kob_id'],$batchIds,true);
                    if ($position !== false) {
                        $row['batch_kob_id'] = '<batch:' . $position . '>';
                    }
                }
            }
            unset($row);
            $result[$table] = $rows;
        }
        $result['varer'] = $this->rows('SELECT beholdning FROM varer WHERE id=$1',[$f['item']]);
        $result['variant_varer'] = $this->rows('SELECT variant_beholdning FROM variant_varer WHERE vare_id=$1 ORDER BY id',[$f['item']]);
        $result['ordrelinjer'] = $this->rows('SELECT leveres,bogf_konto FROM ordrelinjer WHERE id=$1',[$f['line']]);
        $result['serienr'] = $this->rows('SELECT serienr,batch_kob_id,batch_salg_id FROM serienr WHERE vare_id=$1',[$f['item']]);
        $batches = $this->rows(lagerstatusPurchaseValueSql($f['item'],2,'levdate',null));
        $quantity = (float)$this->rows('SELECT COALESCE(SUM(beholdning),0) AS n FROM lagerstatus WHERE vare_id=$1 AND lager=2',[$f['item']])[0]['n'];
        $result['purchase_value'] = lagerstatusPurchaseValue($batches,$quantity);
        return $result;
    }

    /** @return array Ledger rows must remain byte-for-byte unchanged by receipts. */
    protected function ledger(): array
    {
        $result = [];
        foreach (['transaktioner','openpost','kontoplan'] as $table) {
            $result[$table] = $this->rows('SELECT * FROM ' . $table . ' ORDER BY id');
        }
        return $result;
    }

    /** @return array Real web wrapper with local in-process transport stub. */
    protected function web(array $f, array $body): array
    {
        foreach ($body['lines'] as $line) {
            $this->rows('UPDATE ordrelinjer SET leveres=$1,batch_batch_no=$2,batch_due_date=$3 WHERE id=$4',
                [$line['quantity'],$line['batch_no'] ?? null,$line['due_date'] ?? null,$line['line_id']]);
        }
        $this->rows('UPDATE ordrer SET levdate=$1 WHERE id=$2',[$body['receipt_date'],$f['order']]);
        $process = proc_open([PHP_BINARY,__DIR__ . '/../../characterization/support/run_modtag_page.php',
            json_encode(['order_id'=>$f['order']],JSON_THROW_ON_ERROR),self::$session],
            [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0,proc_close($process),$err . $out);
        $result = json_decode($out,true,512,JSON_THROW_ON_ERROR);
        self::assertStringContainsString('URL=ordre.php?id=' . $f['order'],$result['html'],$err);
        return $result;
    }
}
