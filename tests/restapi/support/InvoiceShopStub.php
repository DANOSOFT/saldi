<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/InvoiceShopStub.php --- ver 5.0.0 --- 2026.10.04
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
require_once __DIR__ . '/../../../includes/std_func.php';

/** Local webshop router and lifecycle; reads committed state over a separate DB connection. */
final class InvoiceShopStub
{
    private $process;
    private string $log;
    private string $diagnostics;
    private string $url;

    public function __construct(string $db)
    {
        $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
        if (!$socket) { throw new RuntimeException('Cannot allocate local stub port'); }
        $address=stream_socket_get_name($socket,false);
        fclose($socket);
        $this->url='http://'.$address;
        $this->log=tempnam(sys_get_temp_dir(),'invoice-shop-');
        $this->diagnostics=tempnam(sys_get_temp_dir(),'invoice-shop-server-');
        $environment=getenv(); $environment['SALDI_INVOICE_SHOP_LOG']=$this->log;
        $environment['SALDI_INVOICE_SHOP_DB']=$db;
        $this->process=proc_open([PHP_BINARY,'-S',$address,__FILE__],
            [0=>['file','/dev/null','r'],1=>['file',$this->diagnostics,'a'],2=>['file',$this->diagnostics,'a']],$pipes,null,$environment);
        $ready=false;
        for ($i=0;$i<100;$i++) {
            $probe=curl_init($this->url.'/ready');
            curl_setopt_array($probe,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT_MS=>100]);
            curl_exec($probe); $status=curl_getinfo($probe,CURLINFO_RESPONSE_CODE); curl_close($probe);
            if ($status===200) { $ready=true; break; }
            usleep(10000);
        }
        if (!$ready) { $this->stop(); throw new RuntimeException('Local shop stub did not start'); }
    }

    /** @return string Loopback endpoint carrying only owned fixture identities. */
    public function endpoint(int $orderId,int $productId): string
    {
        return $this->url.'/?'.http_build_query(['order_id'=>$orderId,'product_id'=>$productId]);
    }

    /** @return array<array{query:array,order:array,committed_stock:string}> */
    public function calls(): array
    {
        return array_map(static fn($line)=>json_decode($line,true,512,JSON_THROW_ON_ERROR),file($this->log,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES));
    }

    /** @return string Local server diagnostics, containing no credentials. */
    public function diagnostics(): string { return file_get_contents($this->diagnostics); }

    /** @return void Stop only this test's server and remove its temporary files. */
    public function stop(): void
    {
        if (is_resource($this->process)) { proc_terminate($this->process); proc_close($this->process); }
        foreach ([$this->log,$this->diagnostics] as $file) { if (is_file($file)) { unlink($file); } }
    }
}

if (PHP_SAPI==='cli-server') {
    $log=getenv('SALDI_INVOICE_SHOP_LOG');
    $db=getenv('SALDI_INVOICE_SHOP_DB');
    if (!$log || !$db || !preg_match('/^[a-z0-9_]+$/D',$db)) {
        http_response_code(400);
        exit;
    }
    if (parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/ready') { echo 'OK'; exit; }
    $conn=RestApiEnv::connect($db);
    $order=RestApiEnv::rows($conn,'SELECT status,fakturanr FROM ordrer WHERE id=$1',[(int)ifset($_GET,'order_id',0)])[0];
    $stock=RestApiEnv::rows($conn,'SELECT SUM(beholdning) AS stock FROM lagerstatus WHERE vare_id=$1 AND variant_id=0 AND lager=1',[(int)ifset($_GET,'product_id',0)])[0]['stock'];
    file_put_contents($log,json_encode(['query'=>$_GET,'order'=>$order,'committed_stock'=>$stock],JSON_THROW_ON_ERROR)."\n",FILE_APPEND|LOCK_EX);
    pg_close($conn);
    echo 'OK';
}
