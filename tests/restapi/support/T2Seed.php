<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T2Seed.php --- ver 5.0.0 --- 2026.10.05
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
// 20261005 CDX/LH Share disposable invoice logs between CLI and HTTP fixtures and restore their permissions.

require_once __DIR__ . '/PaymentSettlementTestEnv.php';
require_once __DIR__ . '/../fixtures/t2/InvoiceFixture.php';
require_once __DIR__ . '/../../fixtures/dunning/DunningFixture.php';

/** Shared disposable T2 tenant, real REST sessions and local provider fixtures. */
final class T2Seed
{
    public const V1 = '/restapi/endpoints/v1/';
    public const MODULES = ['kassekladde'=>2, 'debitorordre'=>5, 'debitorkonti'=>6, 'varer'=>9, 'debitorrapport'=>12];
    public $tenant;
    public $registry;
    public array $tokens = [];
    private array $users = [];
    private ?array $registryUser = null;
    private array $cacheIds = [];
    private array $webSessions = [];
    /** @var array<string,int|null> Original log modes; null identifies a file created by this fixture. */
    private array $logModes = [];

    public function __construct(bool $clean = false)
    {
        PaymentLinkTestEnv::setup();
        $this->tenant = PaymentLinkTestEnv::$tenant;
        $this->registry = PaymentLinkTestEnv::$registry;
        $this->tokens['full'] = RestApiEnv::accessToken();
        // Earlier classes reuse this process's tenant name and can leave root-owned CLI logs.
        foreach (['hmlog.log','momslog.log'] as $filename) {
            $path = dirname(__DIR__, 3) . '/temp/' . RestApiEnv::testDb() . '/' . $filename;
            $this->logModes[$path] = is_file($path) ? (fileperms($path) & 0777) : null;
            if ((!is_file($path) && !touch($path)) || !chmod($path, 0666)) {
                throw new RuntimeException('Cannot share the disposable invoice log');
            }
        }
        if ($clean) {
            foreach (['payment_provider_events','payment_attempts','payment_links','document_deliveries','mobile_order_origins',
                'mobile_idempotency','payment_registrations','notifications','openpost','transaktioner','kassekladde','kladdeliste',
                'reservation','batch_salg','batch_kob','lagerstatus','ordrelinjer','ordrer'] as $table) {
                $this->sql('DELETE FROM '.pg_escape_identifier($this->tenant,$table));
            }
            $this->sql('UPDATE kontoplan SET saldo=0,primo=0');
        }
        $this->sql('ALTER TABLE adresser ADD COLUMN IF NOT EXISTS stripe_fravalg text');
        DocumentFixture::configureMail($this->tenant);
        foreach (['bank','kasse'] as $method) {
            $this->sql("INSERT INTO settings(var_grp,var_name,var_value,user_id) VALUES ('mobile_betaling',$1,$2,0)",
                ['account_'.$method,(string)PaymentLinkTestEnv::$fixture['bank']]);
        }
    }

    /** @return list<array<string,string|null>> */
    public function sql(string $sql, array $params = []): array
    {
        return RestApiEnv::rows($this->tenant, $sql, $params);
    }

    public static function uuid(): string
    {
        return PaymentLinkTestEnv::uuid();
    }

    public static function money($value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    public static function today(): string
    {
        return (new DateTimeImmutable('now',new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
    }

    /** Issue actual password logins for precise rights; registry uses login then exchange. */
    public function personas(): void
    {
        $digits = ['receipt'=>str_repeat('0',16), 'unrelated'=>'1'.str_repeat('0',15)];
        foreach (self::MODULES as $module=>$offset) {
            foreach ([1,2] as $digit) {
                $rights = str_repeat('0',16);
                $rights[$offset] = (string)$digit;
                $digits[$module.$digit] = $rights;
            }
        }
        foreach ($digits as $name=>$rights) {
            $username = 'x1_'.bin2hex(random_bytes(6));
            $password = TestCredentials::password('t2x1_'.$name);
            $user = $this->sql('INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES($1,$2,$3,true) RETURNING *',
                [$username,md5($password),$rights])[0];
            $this->users[] = $user;
            $response = self::http('POST','auth/login.php',['account_name'=>RestApiEnv::accountOpen(),
                'username'=>$username,'password'=>$password,'device_id'=>'x1-'.$name],null);
            $this->tokens[$name] = self::token($response);
        }
        $username = 'x1r_'.bin2hex(random_bytes(6));
        $password = TestCredentials::password('t2x1_registry');
        $this->registryUser = RestApiEnv::rows($this->registry,
            'INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES($1,$2,$3,true) RETURNING *',
            [$username,md5($password),',,,'.RestApiEnv::regnskabId(RestApiEnv::accountOpen())])[0];
        $login = self::http('POST','auth/registry-login.php',['username'=>$username,'password'=>$password,'device_id'=>'x1-registry'],null);
        $exchange = self::http('POST','auth/tenant-token.php',['tenant_id'=>RestApiEnv::regnskabId(RestApiEnv::accountOpen())],self::token($login));
        $this->tokens['registry'] = self::token($exchange);
    }

    private static function token(array $response): string
    {
        if ($response['status'] !== 200 || !isset($response['json']['data']['access_token'])) {
            throw new RuntimeException('T2 seeded login failed: '.$response['body']);
        }
        return $response['json']['data']['access_token'];
    }

    /** @return array{status:int,json:?array,body:string,headers:array<string,string>} */
    public static function http(string $method, string $path, ?array $body = null, ?string $token = null, array $headers = []): array
    {
        $responseHeaders = [];
        $ch = curl_init(RestApiEnv::baseUrl().self::V1.$path);
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer '.$token;
        }
        $headers[] = 'Accept: application/json';
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));
        }
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>60,
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_HEADERFUNCTION=>static function ($ch,string $line) use (&$responseHeaders): int {
                if (str_contains($line,':')) {
                    [$key,$value] = explode(':',$line,2);
                    $responseHeaders[strtolower(trim($key))] = trim($value);
                }
                return strlen($line);
            }]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new RuntimeException('T2 HTTP request failed: '.curl_error($ch));
        }
        $status = (int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $json = json_decode($raw,true);
        return ['status'=>$status,'json'=>is_array($json) ? $json : null,'body'=>$raw,'headers'=>$responseHeaders];
    }

    public function request(string $method,string $path,?array $body=null,string $persona='full',array $headers=[]): array
    {
        return self::http($method,$path,$body,$this->tokens[$persona],$headers);
    }

    /** @return array{konto_id:int,product_id:int,order_id:int,year:int,line_id:int,batch_id:int} */
    public function sale(bool $posted = true): array
    {
        $fixture = InvoiceFixture::create($this->tenant,[RestApiEnv::class,'rows'],$posted);
        $this->sql('UPDATE kontoplan SET saldo=COALESCE(saldo,0),primo=COALESCE(primo,0)');
        $this->sql('UPDATE varer SET stregkode=$1 WHERE id=$2',['X1-'.$fixture['product_id'],$fixture['product_id']]);
        return $fixture;
    }

    public function saleBody(array $fixture): array
    {
        return ['client_id'=>self::uuid(),'konto_id'=>$fixture['konto_id'],'kind'=>'tilbud',
            'lines'=>[['vare_id'=>$fixture['product_id'],'quantity'=>'2','unit_price'=>'100.00','discount_pct'=>'10']]];
    }

    /** @return int A posted receivable with a unique invoice number, for payment/read operations. */
    public function invoice(string $amount='1000.00',string $currency='DKK'): int
    {
        return PaymentFixture::invoice($this->tenant,PaymentLinkTestEnv::$fixture,
            (string)random_int(10000000,99999999),$amount,$currency);
    }

    /** Seed an installation cache hit so CVR tests cannot call a real directory. */
    public function cvr(): string
    {
        do {
            $cvr = (string)random_int(10000000,99999999);
            $exists = RestApiEnv::rows($this->registry,'SELECT cvr FROM cvr_cache WHERE cvr=$1',[$cvr]);
        } while ($exists);
        $payload = ['cvr'=>$cvr,'name'=>'X1 cached company','addr1'=>'Testvej 1','postnr'=>'1000','city'=>'Testby',
            'phone'=>'12345678','email'=>'cache@example.invalid','industry'=>'Test','status'=>'active',
            'fetched_at'=>gmdate('Y-m-d\TH:i:s\Z')];
        RestApiEnv::rows($this->registry,'INSERT INTO cvr_cache(cvr,payload,fetched_at) VALUES($1,$2,now())',[$cvr,json_encode($payload,JSON_THROW_ON_ERROR)]);
        $this->cacheIds[] = $cvr;
        return $cvr;
    }

    /** Drive the existing web page runner in a child process, preserving its engine semantics. */
    public function webInvoice(int $orderId,int $year): void
    {
        $session = 'x1web'.bin2hex(random_bytes(10));
        $this->webSessions[] = $session;
        RestApiEnv::rows($this->registry,'INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id) VALUES($1,$2,$3,$4,$5,$6,$7,false,0)',
            [$session,RestApiEnv::user(),RestApiEnv::testDb(),RestApiEnv::pgUser(),str_repeat('1',16),$year,(string)time()]);
        $proc = proc_open([PHP_BINARY,__DIR__.'/../../characterization/support/run_debitor_bogfor_page.php',
            json_encode(['page'=>'bogfor','get'=>['id'=>$orderId]],JSON_THROW_ON_ERROR),$session],
            [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        if (proc_close($proc) !== 0) {
            throw new RuntimeException('T2 web invoice failed: '.$error.$output);
        }
    }

    public function close(): void
    {
        foreach ($this->logModes as $path=>$mode) {
            if (is_file($path)) {
                if ($mode === null) {
                    unlink($path);
                } else {
                    chmod($path, $mode);
                }
            }
        }
        foreach ($this->cacheIds as $cvr) {
            RestApiEnv::rows($this->registry,'DELETE FROM cvr_cache WHERE cvr=$1',[$cvr]);
        }
        foreach ($this->webSessions as $session) {
            RestApiEnv::rows($this->registry,'DELETE FROM online WHERE session_id=$1',[$session]);
            $file = sys_get_temp_dir().'/sess_'.$session;
            if (is_file($file)) {
                unlink($file);
            }
        }
        $subjects = [];
        foreach (array_merge([RestApiEnv::loginData()['access_token']],array_values($this->tokens)) as $token) {
            $payload = json_decode(base64_decode(strtr(explode('.',$token)[1],'-_','+/')),true,512,JSON_THROW_ON_ERROR);
            $subjects[$payload['sub']] = $payload['principal'];
        }
        foreach ($subjects as $subject=>$principal) {
            foreach (['refresh_sessions','auth_versions','auth_challenges'] as $table) {
                RestApiEnv::rows($this->registry,"DELETE FROM $table WHERE subject=$1 AND tenant_id=$2",
                    [$subject,$principal==='registry' && $table==='auth_versions' ? 0 : RestApiEnv::regnskabId(RestApiEnv::accountOpen())]);
            }
            RestApiEnv::rows($this->registry,"DELETE FROM mobile_rate_buckets WHERE bucket_key=$1 OR bucket_key=$2",
                [hash('sha256',RestApiEnv::regnskabId(RestApiEnv::accountOpen()).':'.$subject),
                    hash('sha256',json_encode([$subject,$principal==='registry' ? 0 : RestApiEnv::regnskabId(RestApiEnv::accountOpen())],JSON_UNESCAPED_SLASHES))]);
        }
        foreach ($this->users as $user) {
            $key = hash('sha256',json_encode(['account',mb_strtolower(trim(RestApiEnv::accountOpen())),mb_strtolower(trim($user['brugernavn']))],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            RestApiEnv::rows($this->registry,"DELETE FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$1",[$key]);
        }
        if ($this->registryUser !== null) {
            foreach (['refresh_sessions','auth_versions','auth_challenges'] as $table) {
                RestApiEnv::rows($this->registry,"DELETE FROM $table WHERE subject=$1",[array_search('registry',$subjects,true)]);
            }
            $key = hash('sha256',json_encode(['registry','',mb_strtolower(trim($this->registryUser['brugernavn']))],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            RestApiEnv::rows($this->registry,"DELETE FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$1",[$key]);
            RestApiEnv::rows($this->registry,'DELETE FROM brugere WHERE id=$1',[$this->registryUser['id']]);
        }
        PaymentLinkTestEnv::teardown();
    }
}
