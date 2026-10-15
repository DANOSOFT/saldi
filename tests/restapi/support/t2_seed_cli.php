<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/t2_seed_cli.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Drive isolated app contracts with local providers and cleanup on stdin EOF.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/T2Seed.php';
require_once __DIR__ . '/../../../includes/docsIncludes/poolPaths.php';

/** Long-lived JSON-line fixture process; passwords remain in process memory. */
final class T2ContractSeed
{
    private ?T2Seed $seed = null;
    private array $servers = [];
    private array $buckets = [];
    private array $cache = [];
    private string $directory;
    private int $tenantId;
    private array $sale;

    public function __construct()
    {
        putenv('SALDI_REST_TEST_DB=saldi_t2c1');
        putenv('SALDI_REST_KEEP_TENANT=');
        $this->directory = sys_get_temp_dir() . '/saldi-t2c1-' . bin2hex(random_bytes(8));
        if (!mkdir($this->directory, 0700)) {
            throw new RuntimeException('Cannot create fixture directory');
        }
    }

    /** @return array{account:string,tenant_id:int,today:string,users:array,sale:array,dunning:array,posts:array,cvr_port:int} */
    public function start(): array
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            throw new RuntimeException($reason);
        }
        $this->seed = new T2Seed(true);
        $this->tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        $users = ['full'=>['username'=>RestApiEnv::user(),'password'=>RestApiEnv::password()]];
        foreach (['seller'=>5,'customer'=>6,'product'=>9,'report'=>12,'receipt'=>null] as $role=>$offset) {
            $rights = str_repeat('0', 16);
            if ($offset !== null) {
                $rights[$offset] = '1';
            }
            $user = ['username'=>'t2c1_' . $role . '_' . bin2hex(random_bytes(4)),
                'password'=>TestCredentials::password('t2c1_' . $role)];
            $this->seed->sql("INSERT INTO brugere(brugernavn,kode,rettigheder,status,twofactor,ip_address) VALUES($1,$2,$3,true,false,'')",
                [$user['username'],md5($user['password']),$rights]);
            $users[$role] = $user;
        }
        foreach ($users as $user) {
            $this->buckets[] = MobileAuth::bucketKey('account',RestApiEnv::accountOpen(),$user['username']);
        }
        $this->sale = $this->seed->sale();
        $recipient = 't2c1-sale-' . bin2hex(random_bytes(8)) . '@example.invalid';
        $this->seed->sql('UPDATE adresser SET email=$1 WHERE id=$2',[$recipient,$this->sale['konto_id']]);
        $this->sale['recipient'] = $recipient;
        $dunning = DunningFixture::seed($this->seed->tenant);
        $posts = ['manual'=>$this->seed->invoice(), 'link'=>$this->seed->invoice()];
        $stubPort = $this->server(__DIR__ . '/../../fixtures/cvr/cvrapi_stub.php');
        $apiPort = $this->server(null, ['SALDI_CVR_API_URL'=>'http://127.0.0.1:' . $stubPort . '/api']);
        return ['account'=>RestApiEnv::accountOpen(),'tenant_id'=>$this->tenantId,'today'=>T2Seed::today(),
            'users'=>$users,'sale'=>$this->sale,'dunning'=>$dunning,'posts'=>$posts,'cvr_port'=>$apiPort];
    }

    /** Serve unchanged endpoint code with process-local provider configuration. @return int */
    private function server(?string $router, array $overrides = []): int
    {
        $socket = stream_socket_server('tcp://0.0.0.0:0');
        if ($socket === false) {
            throw new RuntimeException('Cannot reserve local stub port');
        }
        $port = (int)substr(strrchr(stream_socket_get_name($socket,false), ':'), 1);
        fclose($socket);
        $command = [PHP_BINARY,'-S','0.0.0.0:' . $port];
        if ($router !== null) {
            $command[] = $router;
        } else {
            array_push($command,'-t',dirname(__DIR__,3));
        }
        $process = proc_open($command,[0=>['file','/dev/null','r'],1=>['file',$this->directory . '/server.log','a'],
            2=>['file',$this->directory . '/server.log','a']],$pipes,null,array_merge(getenv(),$overrides));
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start local fixture server');
        }
        $this->servers[] = $process;
        for ($attempt=0;$attempt<100;$attempt++) {
            $ch = curl_init('http://127.0.0.1:' . $port . '/');
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>1]);
            curl_exec($ch);
            $ready = curl_getinfo($ch,CURLINFO_RESPONSE_CODE) > 0;
            curl_close($ch);
            if ($ready) {
                return $port;
            }
            usleep(20000);
        }
        throw new RuntimeException('Local fixture server unavailable');
    }

    /** Fixture mutations/observations only; business requests come from the real app client. @return array */
    public function command(array $request): array
    {
        switch ($request['command']) {
            case 'counts':
                $result = [];
                foreach (['ordrer','ordrelinjer','openpost','payment_registrations','payment_provider_events','document_deliveries'] as $table) {
                    $result[$table] = (int)$this->seed->sql('SELECT count(*) AS n FROM ' . pg_escape_identifier($this->seed->tenant,$table))[0]['n'];
                }
                return $result;
            case 'mail':
                $recipient = (string)$request['recipient'];
                if (!preg_match('/^t2c1-sale-|^dunning-/', $recipient)) {
                    throw new RuntimeException('Recipient is not owned by this fixture');
                }
                $messages = DocumentFixture::messages($recipient);
                $details = [];
                foreach ($messages['messages'] as $message) {
                    $url = 'http://' . getenv('SMTP_HOST') . ':8025/api/v1/message/' . rawurlencode($message['ID']);
                    $details[] = json_decode(file_get_contents($url),true,512,JSON_THROW_ON_ERROR);
                }
                return ['total'=>$messages['total'],'messages'=>$details];
            case 'product-change':
                $this->seed->sql("UPDATE varer SET beskrivelse='T2 delta æøå',salgspris=125 WHERE id=$1",[$this->sale['product_id']]);
                return ['id'=>$this->sale['product_id']];
            case 'product-delete':
                $row = $this->seed->sql("INSERT INTO varer(varenr,beskrivelse,gruppe,salgspris) VALUES($1,'Delta deletion',7861,10) RETURNING id",
                    ['T2-DELETE-' . bin2hex(random_bytes(4))])[0];
                return ['id'=>(int)$row['id']];
            case 'delete':
                $id = (int)$request['id'];
                $this->seed->sql("DELETE FROM varer WHERE id=$1 AND varenr LIKE 'T2-DELETE-%'",[$id]);
                return ['deleted'=>true];
            case 'webhook':
                $linkId = (int)$request['link_id'];
                $attempt = $this->seed->sql('SELECT * FROM payment_attempts WHERE link_id=$1 ORDER BY id DESC LIMIT 1',[$linkId])[0];
                PaymentSettlementTestEnv::remote($attempt,'AUTHORIZED');
                $previous = RestApiEnv::baseUrl();
                putenv('SALDI_REST_BASE_URL=http://127.0.0.1:5110/saldi');
                try {
                    return PaymentSettlementTestEnv::signed(PaymentSettlementTestEnv::event($attempt,'authorized'));
                } finally {
                    putenv('SALDI_REST_BASE_URL=' . $previous);
                }
            case 'provider':
                return PaymentLinkTestEnv::control();
            case 'cvr':
                // Unique cache keys ensure the first app lookup actually calls the stub.
                do {
                    $cvr = (string)random_int(10000000,79999999);
                } while (RestApiEnv::rows($this->seed->registry,'SELECT cvr FROM cvr_cache WHERE cvr=$1',[$cvr]));
                $this->cache[] = $cvr;
                return ['cvr'=>$cvr];
            default:
                throw new RuntimeException('Unknown fixture command');
        }
    }

    /** Cleanup on explicit close, stdin EOF, or a test assertion failure. @return void */
    public function close(): void
    {
        foreach ($this->servers as $process) {
            proc_terminate($process);
            proc_close($process);
        }
        $this->servers = [];
        if ($this->seed !== null) {
            foreach ($this->cache as $cvr) {
                RestApiEnv::rows($this->seed->registry,'DELETE FROM cvr_cache WHERE cvr=$1',[$cvr]);
            }
            foreach ($this->buckets as $bucket) {
                RestApiEnv::rows($this->seed->registry,"DELETE FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$1",[$bucket]);
            }
            foreach (['refresh_sessions','auth_versions','auth_challenges'] as $table) {
                RestApiEnv::rows($this->seed->registry,"DELETE FROM $table WHERE tenant_id=$1",[$this->tenantId]);
            }
            $database = RestApiEnv::testDb();
            $this->seed->close();
            $this->seed = null;
            foreach ([dirname(__DIR__,3) . '/temp/' . $database,dirname(poolPuljePath($database))] as $path) {
                $this->remove($path);
            }
        } else {
            RestApiEnv::teardownTenant();
        }
        $this->remove($this->directory);
    }

    /** Remove only the disposable fixture's files, without following symlinks. @return void */
    private function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            if (!unlink($path)) {
                throw new RuntimeException('Cannot remove fixture file');
            }
        } elseif (is_dir($path)) {
            foreach (new DirectoryIterator($path) as $entry) {
                if (!$entry->isDot()) {
                    $this->remove($entry->getPathname());
                }
            }
            if (!rmdir($path)) {
                throw new RuntimeException('Cannot remove fixture directory');
            }
        }
    }
}

$fixture = new T2ContractSeed();
try {
    echo json_encode(['data'=>$fixture->start()],JSON_THROW_ON_ERROR) . PHP_EOL;
    while (($line = fgets(STDIN)) !== false) {
        $request = json_decode($line,true,512,JSON_THROW_ON_ERROR);
        if (($request['command'] ?? '') === 'close') {
            break;
        }
        try {
            echo json_encode(['data'=>$fixture->command($request)],JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (Throwable $error) {
            fwrite(STDERR,$error . PHP_EOL);
            echo json_encode(['error'=>'Fixture command failed']) . PHP_EOL;
        }
    }
} finally {
    $fixture->close();
}
