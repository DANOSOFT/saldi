<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/PaymentLinkTestEnv.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Invoice payment links.

require_once __DIR__ . '/RestApiEnv.php';
require_once __DIR__ . '/../../characterization/finans/support/PaymentFixture.php';
require_once __DIR__ . '/../../../restapi/services/payments/PaymentLinkService.php';
require_once __DIR__ . '/../../fixtures/easyubl/DocumentFixture.php';

/** Disposable tenant and local-only ePayment server; no credential literals. */
final class PaymentLinkTestEnv
{
    public static $tenant;
    public static $registry;
    public static array $fixture;
    public static string $base;
    private static $stub;
    private static string $directory;
    private static int $serial = 88000;

    public static function setup(): void
    {
        RestApiEnv::bootstrapTenant();
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        self::$registry = RestApiEnv::connect(RestApiEnv::masterDb());
        mobileTenantUpdates(self::$tenant);
        self::$fixture = PaymentFixture::seed(self::$tenant);
        RestApiEnv::accessToken();
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($socket,false),':'),1);
        fclose($socket);
        self::$base = 'http://127.0.0.1:'.$port;
        self::$directory = '/tmp/saldi-epayment-'.bin2hex(random_bytes(8));
        mkdir(self::$directory,0700);
        $env = array_merge(getenv(),['SALDI_EP_STUB_STATE'=>self::$directory.'/state.json']);
        self::$stub = proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,__DIR__.'/../../fixtures/mobilepay/vipps_epayment_stub.php'],
            [0=>['pipe','r'],1=>['file',self::$directory.'/stub.log','a'],2=>['file',self::$directory.'/stub.log','a']],$pipes,null,$env);
        fclose($pipes[0]);
        usleep(200000);
        self::configure();
    }

    public static function configure(): void
    {
        $id = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        RestApiEnv::rows(self::$tenant,"DELETE FROM settings WHERE var_grp IN ('mobilepay','mobile_betaling')");
        foreach (['mobile_betaling'=>['account_mobilepay'=>(string)self::$fixture['bank'],'public_base_url'=>'https://pay.example.invalid/saldi','link_lifetime_days'=>'14'],
            'mobilepay'=>['client_id'=>bin2hex(random_bytes(16)),'client_secret'=>bin2hex(random_bytes(32)),
                'subscriptionKey'=>bin2hex(random_bytes(32)),'MSN'=>'1234567','epayment_api_base'=>self::$base,
                'invoice_webhook_secret'=>bin2hex(random_bytes(32)),
                'invoice_webhook_url'=>'https://pay.example.invalid/saldi/debitor/payments/link/webhook.php?t='.$id]] as $group=>$settings) {
            foreach ($settings as $name=>$value) {
                RestApiEnv::rows(self::$tenant,'INSERT INTO settings(var_grp,var_name,var_value,user_id) VALUES($1,$2,$3,0)',[$group,$name,$value]);
            }
        }
        self::control(['mode'=>'']);
    }

    public static function teardown(): void
    {
        if (is_resource(self::$stub)) {
            proc_terminate(self::$stub);
            proc_close(self::$stub);
        }
        if (isset(self::$directory)) {
            foreach (glob(self::$directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir(self::$directory);
        }
        if (self::$tenant) {
            pg_close(self::$tenant);
        }
        if (self::$registry) {
            $key = hash('sha256',RestApiEnv::regnskabId(RestApiEnv::accountOpen()).':'.self::subject());
            RestApiEnv::rows(self::$registry,"DELETE FROM mobile_rate_buckets WHERE scope='paylink_create' AND bucket_key=$1",[$key]);
            pg_close(self::$registry);
        }
        RestApiEnv::teardownTenant();
    }

    public static function uuid(): string
    {
        $v = bin2hex(random_bytes(16));
        return substr($v,0,8).'-'.substr($v,8,4).'-'.substr($v,12,4).'-'.substr($v,16,4).'-'.substr($v,20);
    }

    public static function subject(): string
    {
        $part = explode('.',RestApiEnv::accessToken())[1];
        return json_decode(base64_decode(strtr($part,'-_','+/')),true)['sub'];
    }

    public static function service($connection = null): PaymentLinkService
    {
        $context = new EngineContext($connection ?? self::$tenant,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RestApiEnv::USER,
            (int)RestApiEnv::loginData()['user']['id'],'2026-10-03');
        return new PaymentLinkService($context,self::subject());
    }

    public static function body(string $currency = 'DKK'): array
    {
        return ['client_id'=>self::uuid(),'openpost_id'=>PaymentFixture::invoice(self::$tenant,self::$fixture,(string)++self::$serial,'1000.00',$currency),
            'provider'=>'vipps_epayment'];
    }

    /** @return array<string,mixed> */
    public static function control(array $body = []): array
    {
        $ch = curl_init(self::$base.'/__control');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_TIMEOUT=>5]);
        $raw = curl_exec($ch);
        curl_close($ch);
        return json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    }

    /** @return array{status:int,body:string,json:array|null,headers:array<string,string>} */
    public static function request(string $method, string $path, ?array $body = null, array $headers = []): array
    {
        $responseHeaders = [];
        $ch = curl_init(RestApiEnv::baseUrl().$path);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>40,
            CURLOPT_HTTPHEADER=>array_merge(RestApiEnv::authHeaders(),['Content-Type: application/json'],$headers),
            CURLOPT_HEADERFUNCTION=>static function ($ch,string $line) use (&$responseHeaders): int {
                if (str_contains($line,':')) {
                    [$key,$value] = explode(':',$line,2);
                    $responseHeaders[strtolower(trim($key))] = trim($value);
                }
                return strlen($line);
            }]);
        if ($body !== null) {
            curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));
        }
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status'=>$status,'body'=>(string)$raw,'json'=>json_decode((string)$raw,true),'headers'=>$responseHeaders];
    }
}
