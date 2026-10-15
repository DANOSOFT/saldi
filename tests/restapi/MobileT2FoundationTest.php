<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileT2FoundationTest.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Pin delivery completion lock order and concurrent replay on two connections.
// 20261002 CL/LH Check fragment loading with a never-installed key; real Tier 2 fragments now exist.
// 20261005 CDX/LH Derive the current tenant marker so appended migrations retain the replay checks.
// 20261005 CDX/LH Keep fixture cleanup compatible with retained T3 provenance and current migration markers.
// 20261006 CL/LH Registry principals keep employee-bound T4 permissions false after a tenant exchange.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../includes/mobileRegistryUpdates.php';
require_once __DIR__ . '/../../includes/openpost/udlignId.php';
require_once __DIR__ . '/../../restapi/core/Paging.php';
require_once __DIR__ . '/../../restapi/core/ETag.php';
require_once __DIR__ . '/../../restapi/core/RequestId.php';
require_once __DIR__ . '/../../restapi/services/legacy/LegacyEngine.php';

final class MobileT2FoundationTest extends TestCase
{
    private static ?string $registryDb = null;
    private $tenant;
    private array $globals;

    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        $name = 'm2_registry_' . bin2hex(random_bytes(4));
        RestApiEnv::rows($master, 'CREATE DATABASE ' . $name . ' TEMPLATE template0');
        self::$registryDb = $name;
        pg_close($master);
        $registry = RestApiEnv::connect($name);
        RestApiEnv::rows($registry, 'CREATE TABLE settings (id serial PRIMARY KEY,var_grp text,var_name text,var_value text,user_id integer)');
        pg_close($registry);
    }

    public static function tearDownAfterClass(): void
    {
        RestApiEnv::teardownTenant();
        if (self::$registryDb !== null) {
            $master = RestApiEnv::connect(RestApiEnv::masterDb());
            RestApiEnv::rows($master, 'DROP DATABASE ' . self::$registryDb);
            pg_close($master);
        }
    }

    protected function setUp(): void
    {
        $this->globals = [];
        foreach (['connection','db_type','db_transaktion_depth','db_modify_fejl','db','brugernavn','regnaar'] as $key) {
            $this->globals[$key] = [array_key_exists($key, $GLOBALS), ifset($GLOBALS, $key)];
        }
        $this->tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $GLOBALS['db_type'] = 'postgresql';
        mobileTenantUpdates($this->tenant);
    }

    protected function tearDown(): void
    {
        if (pg_transaction_status($this->tenant) !== PGSQL_TRANSACTION_IDLE) {
            RestApiEnv::rows($this->tenant, 'ROLLBACK');
        }
        pg_close($this->tenant);
        foreach ($this->globals as $key => [$exists,$value]) {
            if ($exists) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }

    public function test_schema_fresh_clone_append_replay_and_current_one_select(): void
    {
        $tables = ['mobile_idempotency','mobile_order_origins','document_deliveries','payment_links',
            'payment_attempts','payment_provider_events','payment_registrations','mobile_catalogue_changes'];
        foreach ($tables as $table) {
            $this->assertSame($table, RestApiEnv::rows($this->tenant, 'SELECT to_regclass($1) AS name', [$table])[0]['name']);
        }
        $id = udlign_next_id($this->tenant);
        $this->assertSame(array_key_last(mobileTenantUpdateSteps()), $this->marker($this->tenant, 'tenant'));
        RestApiEnv::rows($this->tenant, "UPDATE settings SET var_value='m08_pool_normalized_amount' WHERE var_grp='mobile' AND var_name='mobile_tenant_step'");
        mobileTenantUpdates($this->tenant);
        $this->assertGreaterThan($id, udlign_next_id($this->tenant), 'Reapplying migration cannot rewind reservations');
        RestApiEnv::rows($this->tenant, 'BEGIN READ ONLY');
        $before = $this->settingsScans($this->tenant);
        mobileTenantUpdates($this->tenant);
        $this->assertSame($before + 1, $this->settingsScans($this->tenant));
        RestApiEnv::rows($this->tenant, 'ROLLBACK');
        // All earlier steps can also be replayed on a cloned installed baseline.
        RestApiEnv::rows($this->tenant, "DELETE FROM settings WHERE var_grp='mobile' AND var_name='mobile_tenant_step'");
        mobileTenantUpdates($this->tenant);
        $this->assertSame(array_key_last(mobileTenantUpdateSteps()), $this->marker($this->tenant, 'tenant'));
    }

    private function marker($connection, string $scope): string
    {
        return RestApiEnv::rows($connection, "SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name=$1", ['mobile_' . $scope . '_step'])[0]['var_value'];
    }

    private function settingsScans($connection): int
    {
        return (int)RestApiEnv::rows($connection, "SELECT seq_scan+idx_scan AS scans FROM pg_stat_xact_user_tables WHERE relname='settings'")[0]['scans'];
    }

    public function test_registry_bootstrap_works_outside_auth_and_current_one_select(): void
    {
        $conn = RestApiEnv::connect(self::$registryDb);
        try {
            $store = mobileT2RegistryStore($conn);
            $this->assertSame(array_key_last(mobileRegistryUpdateSteps()), $this->marker($conn, 'registry'));
            $this->assertSame([], $store->rows('SELECT * FROM cvr_cache'));
            $this->assertSame([], $store->rows('SELECT * FROM mobile_rate_buckets'));
            RestApiEnv::rows($conn, 'BEGIN READ ONLY');
            $before = $this->settingsScans($conn);
            mobileRegistryUpdates($conn);
            $this->assertSame($before + 1, $this->settingsScans($conn));
            RestApiEnv::rows($conn, 'ROLLBACK');
            RestApiEnv::rows($conn, "UPDATE settings SET var_value='r08_auth_housekeeping_indexes' WHERE var_grp='mobile' AND var_name='mobile_registry_step'");
            mobileRegistryUpdates($conn);
            $this->assertSame(array_key_last(mobileRegistryUpdateSteps()), $this->marker($conn, 'registry'));
        } finally {
            pg_close($conn);
        }
    }

    public function test_replay_hash_scope_and_resource_rollback_are_atomic(): void
    {
        $body = ['client_id' => RequestId::resolve(null), 'lines' => [['quantity' => '2.000','vare_id' => 4]], 'konto_id' => 5];
        $subject = 'foundation/account/1';
        $counter = 0;
        $change = function () use (&$counter,$body,$subject): array {
            $counter++;
            RestApiEnv::rows($this->tenant, "INSERT INTO mobile_order_origins(ordre_id,client_id,created_by,kind) VALUES (987654,$1,$2,'ordre')", [$body['client_id'],$subject]);
            return ['status' => 201, 'body' => ['success' => true, 'message' => '', 'data' => ['id' => 987654]]];
        };
        RestApiEnv::rows($this->tenant, 'BEGIN');
        $helper = new Idempotency($this->tenant);
        $first = $helper->execute($subject, 'sales.create', null, $body, $change);
        $reordered = ['konto_id' => 5, 'lines' => [['vare_id' => 4,'quantity' => '2.000']], 'client_id' => strtoupper($body['client_id'])];
        $second = $helper->execute($subject, 'sales.create', null, $reordered, $change);
        $this->assertSame(1, $counter);
        $this->assertSame($first['body'], $second['body']);
        $this->assertSame(201, $second['status']);
        $this->assertSame(['Idempotent-Replayed' => 'true'], $second['headers']);
        $this->assertNull($helper->replay('foundation/registry/1', 'sales.create', null, $body));
        foreach ([['sales.create',1,$body], ['sales.update',null,$body], ['sales.create',null,array_replace($body,['konto_id' => 6])]] as [$operation,$id,$changed]) {
            try {
                $helper->replay($subject, $operation, $id, $changed);
                $this->fail('Changed request must conflict');
            } catch (MobileT2Error $error) {
                $this->assertSame('conflict', $error->error);
                $this->assertSame(409, $error->status);
                $this->assertSame(['reason' => 'client_id_reused'], $error->details);
            }
        }
        RestApiEnv::rows($this->tenant, 'ROLLBACK');
        $this->assertSame([], RestApiEnv::rows($this->tenant, 'SELECT id FROM mobile_idempotency WHERE client_id=$1', [$body['client_id']]));
        $this->assertSame([], RestApiEnv::rows($this->tenant, 'SELECT ordre_id FROM mobile_order_origins WHERE client_id=$1', [$body['client_id']]));
        RestApiEnv::rows($this->tenant, 'BEGIN');
        $helper->execute($subject, 'sales.create', null, $body, $change);
        RestApiEnv::rows($this->tenant, 'COMMIT');
        RestApiEnv::rows($this->tenant, 'BEGIN');
        $third = $helper->execute($subject, 'sales.create', null, $body, $change);
        $this->assertSame($first['body'], $third['body']);
        $this->assertSame(2, $counter);
        RestApiEnv::rows($this->tenant, 'ROLLBACK');
    }

    public function test_delivery_replay_blocks_sending_recovers_unknown_and_stores_completion(): void
    {
        RestApiEnv::rows($this->tenant, 'BEGIN');
        $helper = new Idempotency($this->tenant);
        $body = ['client_id'=>RequestId::resolve(null), 'order_id'=>123];
        $delivery = RestApiEnv::rows($this->tenant, "INSERT INTO document_deliveries(ordre_id,document,channel,created_by,client_id)
            VALUES (123,'faktura','email','delivery/account/1',$1) RETURNING id", [$body['client_id']])[0]['id'];
        $response = ['success'=>true,'message'=>'','data'=>['delivery'=>['id'=>(int)$delivery,'state'=>'sending']]];
        $this->assertNull($helper->replay('delivery/account/1','sales.deliver',123,$body));
        $helper->store('delivery/account/1','sales.deliver',123,$body,201,$response,'document_delivery',(int)$delivery);
        try {
            $helper->replay('delivery/account/1','sales.deliver',123,$body);
            $this->fail('A sending replay must not send again');
        } catch (MobileT2Error $error) {
            $this->assertSame(409,$error->status);
            $this->assertSame(['reason'=>'in_progress'],$error->details);
        }
        RestApiEnv::rows($this->tenant, "UPDATE document_deliveries SET created_at=now()-interval '11 minutes' WHERE id=$1", [$delivery]);
        $unknown = $helper->replay('delivery/account/1','sales.deliver',123,$body);
        $this->assertSame('unknown',$unknown['body']['data']['delivery']['state']);
        $this->assertSame('unknown',RestApiEnv::rows($this->tenant,'SELECT state FROM document_deliveries WHERE id=$1',[$delivery])[0]['state']);
        $response['data']['delivery']['state'] = 'sent';
        $helper->complete('delivery/account/1','sales.deliver',123,$body,200,$response);
        RestApiEnv::rows($this->tenant, "UPDATE document_deliveries SET state='sent',finished_at=now() WHERE id=$1", [$delivery]);
        $final = $helper->replay('delivery/account/1','sales.deliver',123,$body);
        $this->assertSame(200,$final['status']);
        $this->assertSame($response,$final['body']);
        $this->assertSame(['Idempotent-Replayed'=>'true'],$final['headers']);
    }

    public function test_delivery_completion_and_replay_serialize_in_both_orders(): void
    {
        foreach (['completion', 'replay'] as $first) {
            $subject = 'delivery-race/account/1';
            $body = ['client_id' => RequestId::resolve(null), 'order_id' => 123];
            $helper = new Idempotency($this->tenant);
            RestApiEnv::rows($this->tenant, 'BEGIN');
            $this->assertNull($helper->replay($subject, 'sales.deliver', 123, $body));
            $delivery = (int)RestApiEnv::rows($this->tenant, "INSERT INTO document_deliveries(ordre_id,document,channel,created_by,client_id)
                VALUES (123,'faktura','email',$1,$2) RETURNING id", [$subject, $body['client_id']])[0]['id'];
            $response = ['success' => true, 'message' => '', 'data' => ['delivery' => ['id' => $delivery, 'state' => 'sending']]];
            $helper->store($subject, 'sales.deliver', 123, $body, 201, $response, 'document_delivery', $delivery);
            RestApiEnv::rows($this->tenant, 'COMMIT');
            $response['data']['delivery']['state'] = 'sent';

            RestApiEnv::rows($this->tenant, 'BEGIN');
            if ($first === 'completion') {
                $helper->complete($subject, 'sales.deliver', 123, $body, 200, $response);
            } else {
                try {
                    $helper->replay($subject, 'sales.deliver', 123, $body);
                    $this->fail('A fresh sending delivery must be in progress');
                } catch (MobileT2Error $error) {
                    $this->assertSame('conflict', $error->error);
                    $this->assertSame(409, $error->status);
                    $this->assertSame(['reason' => 'in_progress'], $error->details);
                }
            }

            $code = 'require __DIR__ . "/tests/restapi/support/RestApiEnv.php"; require __DIR__ . "/restapi/core/Idempotency.php"; '
                . '$c=RestApiEnv::connect(' . var_export(RestApiEnv::testDb(), true) . '); '
                . '$body=' . var_export($body, true) . '; $response=' . var_export($response, true) . '; '
                . 'RestApiEnv::rows($c,"SET statement_timeout = \'10s\'"); RestApiEnv::rows($c,"BEGIN"); '
                . 'echo pg_get_pid($c),"\n"; flush(); $helper=new Idempotency($c); ';
            if ($first === 'completion') {
                $code .= '$result=$helper->replay("delivery-race/account/1","sales.deliver",123,$body); ';
            } else {
                $code .= '$helper->complete("delivery-race/account/1","sales.deliver",123,$body,200,$response); '
                    . 'RestApiEnv::rows($c,"UPDATE document_deliveries SET state=\'sent\',finished_at=now() WHERE id=$1",[' . $delivery . ']); '
                    . '$result=["status"=>200,"body"=>$response]; ';
            }
            $code .= 'RestApiEnv::rows($c,"COMMIT"); pg_close($c); echo json_encode($result,JSON_THROW_ON_ERROR);';
            $child = $this->startChild($code);
            try {
                stream_set_timeout($child[1][1], 10);
                $pid = (int)fgets($child[1][1]);
                $this->assertGreaterThan(0, $pid, 'Child must announce its database connection');
                $deadline = microtime(true) + 5;
                do {
                    $waiting = RestApiEnv::rows($this->tenant, "SELECT EXISTS (
                        SELECT 1 FROM pg_locks WHERE pid=$1 AND locktype='advisory' AND NOT granted
                    ) AND pg_backend_pid()=ANY(pg_blocking_pids($1)) AS waiting", [$pid])[0]['waiting'];
                    if ($waiting === 't') {
                        break;
                    }
                    usleep(10000);
                } while (microtime(true) < $deadline);
                $this->assertSame('t', $waiting, "$first must hold the advisory lock before the second operation proceeds");
                if ($first === 'completion') {
                    // The retry is already waiting; this UPDATE must not wait for a delivery lock it holds.
                    RestApiEnv::rows($this->tenant, "SET LOCAL statement_timeout = '5s'");
                    RestApiEnv::rows($this->tenant, "UPDATE document_deliveries SET state='sent',finished_at=now() WHERE id=$1", [$delivery]);
                }
                RestApiEnv::rows($this->tenant, 'COMMIT');
                $result = json_decode($this->finishChild($child), true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame(200, $result['status']);
                $this->assertSame($response, $result['body']);
                if ($first === 'completion') {
                    $this->assertSame(['Idempotent-Replayed' => 'true'], $result['headers']);
                }
                $this->assertSame(['state' => 'sent', 'finished' => 't'], RestApiEnv::rows($this->tenant,
                    'SELECT state,finished_at IS NOT NULL AS finished FROM document_deliveries WHERE id=$1', [$delivery])[0]);
                RestApiEnv::rows($this->tenant, 'BEGIN');
                $final = $helper->replay($subject, 'sales.deliver', 123, $body);
                $this->assertSame(['status' => 200, 'body' => $response, 'headers' => ['Idempotent-Replayed' => 'true']], $final);
                RestApiEnv::rows($this->tenant, 'ROLLBACK');
            } finally {
                if (pg_transaction_status($this->tenant) !== PGSQL_TRANSACTION_IDLE) {
                    RestApiEnv::rows($this->tenant, 'ROLLBACK');
                }
                if (is_resource($child[0])) {
                    proc_terminate($child[0]);
                    fclose($child[1][1]);
                    fclose($child[1][2]);
                    proc_close($child[0]);
                }
            }
        }
    }

    public function test_simultaneous_first_submissions_create_one_domain_row_and_one_replay_record(): void
    {
        $db = var_export(RestApiEnv::testDb(),true);
        $body = var_export(['client_id'=>RequestId::resolve(null)],true);
        $code = 'require __DIR__ . "/tests/restapi/support/RestApiEnv.php"; require __DIR__ . "/restapi/core/Idempotency.php"; '
            . '$c=RestApiEnv::connect(' . $db . '); $body=' . $body . '; pg_query($c,"BEGIN"); '
            . '$r=(new Idempotency($c))->execute("parallel/account/1","sales.create",null,$body,function()use($c,$body){ '
            . 'pg_query($c,"SELECT pg_sleep(0.2)"); '
            . '$r=pg_query_params($c,"INSERT INTO mobile_order_origins(ordre_id,client_id,created_by,kind) VALUES (987655,$1,$2,$3)",[$body["client_id"],"parallel/account/1","ordre"]); '
            . 'if(!$r){throw new RuntimeException("domain insert failed");} return ["status"=>201,"body"=>["success"=>true,"message"=>"","data"=>["id"=>987655]]]; }); '
            . 'pg_query($c,"COMMIT"); echo json_encode($r);';
        $a = $this->startChild($code);
        $b = $this->startChild($code);
        $responses = [json_decode($this->finishChild($a),true),json_decode($this->finishChild($b),true)];
        $this->assertSame($responses[0]['body'],$responses[1]['body']);
        $this->assertCount(1,array_filter($responses,static fn(array $response): bool => isset($response['headers']['Idempotent-Replayed'])));
        $this->assertSame('1',RestApiEnv::rows($this->tenant,"SELECT count(*) FROM mobile_idempotency WHERE subject='parallel/account/1'")[0]['count']);
        $this->assertSame('1',RestApiEnv::rows($this->tenant,"SELECT count(*) FROM mobile_order_origins WHERE created_by='parallel/account/1'")[0]['count']);
    }

    public function test_replay_requires_transaction_and_uuid(): void
    {
        $helper = new Idempotency($this->tenant);
        try {
            $helper->replay('foundation/account/1', 'sales.create', null, []);
            $this->fail('Autocommit replay is forbidden');
        } catch (LogicException $error) {
            $this->assertStringContainsString('caller-owned', $error->getMessage());
        }
        RestApiEnv::rows($this->tenant, 'BEGIN');
        try {
            $helper->replay('foundation/account/1', 'sales.create', null, ['client_id' => '../bad']);
            $this->fail('Invalid id must fail');
        } catch (MobileT2Error $error) {
            $this->assertSame(['reason' => 'client_id_required'], $error->details);
        }
    }

    public function test_canonical_json_preserves_lists_and_scalar_types(): void
    {
        $this->assertSame('{"a":{"x":1,"y":2},"z":[2,1]}', Idempotency::canonicalJson(['z'=>[2,1], 'a'=>['y'=>2,'x'=>1]]));
        $this->assertNotSame(Idempotency::requestHash('quote', null, ['n'=>'1']), Idempotency::requestHash('quote', null, ['n'=>1]));
        $this->assertNotSame(Idempotency::canonicalJson((object)[]), Idempotency::canonicalJson([]));
    }

    public function test_retention_deletes_at_most_one_hundred_old_records(): void
    {
        RestApiEnv::rows($this->tenant, 'BEGIN');
        RestApiEnv::rows($this->tenant, "INSERT INTO mobile_idempotency(subject,client_id,operation,request_sha256,http_status,response_json,created_at)
            SELECT 'retention',i::text,'test',repeat('a',64),200,'{}',now()-interval '31 days' FROM generate_series(1,105) i");
        $body = ['client_id' => RequestId::resolve(null)];
        (new Idempotency($this->tenant))->execute('retention/account/1','test',null,$body,
            static fn(): array => ['status'=>200,'body'=>['success'=>true,'message'=>'','data'=>[]]]);
        $this->assertSame('5', RestApiEnv::rows($this->tenant, "SELECT count(*) FROM mobile_idempotency WHERE subject='retention'")[0]['count']);
    }

    public function test_paging_and_stable_sort_reject_invalid_input(): void
    {
        $this->assertSame(['items'=>[],'total'=>19,'limit'=>50,'offset'=>0], (new Paging())->response([],19));
        $this->assertSame('LIMIT 100 OFFSET 7', (new Paging(['limit'=>'150','offset'=>'7']))->sql());
        $this->assertSame(500, (new Paging([],true))->limit);
        $this->assertSame(1000, (new Paging(['limit'=>'2000'],true))->limit);
        $this->assertSame('days_overdue DESC, id DESC', Paging::order('overdue',['overdue'=>'days_overdue'],'id',true));
        foreach ([['limit'=>'0'], ['offset'=>'-1'], ['limit'=>true], ['offset'=>['1']], ['offset'=>'1 OR 1=1']] as $query) {
            try {
                new Paging($query);
                $this->fail('Invalid paging accepted');
            } catch (MobileT2Error $error) {
                $this->assertSame(422, $error->status);
            }
        }
    }

    public function test_etag_tracks_header_every_line_and_customer_and_requires_strong_match(): void
    {
        $header = ['id'=>1,'status'=>1,'sum'=>'10.00','moms'=>'2.50','fakturanr'=>null,'hvem'=>'alice','tidspkt'=>'42'];
        $lines = [['id'=>2,'posnr'=>1,'vare_id'=>4,'antal'=>'1','pris'=>'10','rabat'=>'0'], ['id'=>3,'posnr'=>2,'vare_id'=>5,'antal'=>'2','pris'=>'10','rabat'=>'0']];
        $tag = ETag::order($header,$lines);
        $this->assertSame($tag, ETag::order($header,array_reverse($lines)));
        foreach (['status','sum','moms','kostpris','fakturanr','hvem','tidspkt'] as $field) {
            $this->assertNotSame($tag, ETag::order(array_replace($header,[$field=>'changed']),$lines));
        }
        foreach (['posnr','vare_id','antal','pris','rabat'] as $field) {
            $changed = $lines;
            $changed[1][$field] = 'changed';
            $this->assertNotSame($tag, ETag::order($header,$changed));
        }
        $this->assertNotSame(ETag::customer(['id'=>1,'modtime'=>'1']), ETag::customer(['id'=>1,'modtime'=>'2']));
        ETag::requireMatch('"other", ' . $tag, $tag);
        foreach ([null, 'W/' . $tag, '"stale"'] as $given) {
            try {
                ETag::requireMatch($given, $tag);
                $this->fail('Invalid validator accepted');
            } catch (MobileT2Error $error) {
                $this->assertSame($given === null ? 422 : 412, $error->status);
            }
        }
    }

    private function context(string $date = '2026-10-02'): EngineContext
    {
        return new EngineContext($this->tenant, RestApiEnv::testDb(), RestApiEnv::regnskabId(RestApiEnv::accountOpen()), RestApiEnv::user(), 1, $date);
    }

    public function test_engine_restores_cwd_globals_buffers_and_transaction_depth_on_success_and_error(): void
    {
        $context = $this->context();
        $cwd = getcwd();
        $GLOBALS['regnaar'] = 999;
        $GLOBALS['db_transaktion_depth'] = 7;
        $GLOBALS['connection'] = 'original';
        $depth = ob_get_level();
        RestApiEnv::rows($this->tenant, 'BEGIN');
        ob_start();
        try {
            $result = LegacyEngine::run($context, function () use ($context): string {
                $this->assertSame(realpath(__DIR__ . '/../../debitor'), getcwd());
                $this->assertSame($context->regnaar, $GLOBALS['regnaar']);
                $this->assertSame(7, $GLOBALS['db_transaktion_depth']);
                $this->assertSame(1, $GLOBALS['webservice']);
                echo '<html>engine output</html>';
                ob_flush();
                ob_start();
                echo 'nested buffer';
                return 'OK';
            });
            $this->assertSame('OK', $result);
            $this->assertSame('', ob_get_contents());
            $this->assertSame($depth+1, ob_get_level());
            try {
                LegacyEngine::run($context, static function (): void { echo 'failure output'; throw new RuntimeException('engine failure'); });
                $this->fail('Exception must propagate');
            } catch (RuntimeException $error) {
                $this->assertSame('engine failure', $error->getMessage());
            }
            $this->assertSame('', ob_get_contents());
        } finally {
            ob_end_clean();
        }
        $this->assertSame($cwd, getcwd());
        $this->assertSame(7, $GLOBALS['db_transaktion_depth']);
        $this->assertSame(999, $GLOBALS['regnaar']);
        $this->assertSame('original', $GLOBALS['connection']);
        $this->assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->tenant));
    }

    public function test_engine_rejects_transaction_ownership_violation_and_cleans_idle_leak(): void
    {
        $context = $this->context();
        foreach (['depth','begin'] as $case) {
            try {
                LegacyEngine::run($context, function () use ($case): void {
                    if ($case === 'depth') {
                        $GLOBALS['db_transaktion_depth']++;
                    } else {
                        RestApiEnv::rows($this->tenant, 'BEGIN');
                    }
                });
                $this->fail('Transaction ownership violation accepted');
            } catch (LogicException $error) {
                $this->assertStringContainsString('caller-owned', $error->getMessage());
            }
            $this->assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status($this->tenant));
        }
    }

    public function test_engine_date_selects_non_calendar_fiscal_year(): void
    {
        RestApiEnv::rows($this->tenant, 'BEGIN');
        RestApiEnv::rows($this->tenant, "DELETE FROM grupper WHERE art='RA'");
        RestApiEnv::rows($this->tenant, "INSERT INTO grupper(art,kodenr,box1,box2,box3,box4) VALUES ('RA',42,'7','2026','6','2027')");
        $this->assertSame(42, $this->context('2027-01-10')->regnaar);
        try {
            $this->context('2026-06-30');
            $this->fail('Date outside year accepted');
        } catch (MobileT2Error $error) {
            $this->assertSame(['reason'=>'outside_fiscal_year'], $error->details);
        }
    }

    /** Child processes keep colliding PHP functions out of the shared REST-suite process. */
    private function startChild(string $code): array
    {
        $root = realpath(__DIR__ . '/../..');
        $pipes = [];
        $process = proc_open([PHP_BINARY,'-r',$code], [1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
        $this->assertIsResource($process);
        return [$process,$pipes];
    }

    private function finishChild(array $child): string
    {
        [$process,$pipes] = $child;
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $error . $output);
        return trim($output);
    }

    public function test_engine_load_refuses_a_real_symbol_collision_before_including(): void
    {
        $db = var_export(RestApiEnv::testDb(),true);
        $code = 'require __DIR__ . "/tests/restapi/support/RestApiEnv.php"; require __DIR__ . "/restapi/services/legacy/LegacyEngine.php"; '
            . '$c=RestApiEnv::connect(' . $db . '); $ctx=new EngineContext($c,' . $db . ',1,"test",1,"2026-10-02"); '
            . 'LegacyEngine::run($ctx,function(){ LegacyEngine::load("orders"); LegacyEngine::load("orders"); '
            . 'try { LegacyEngine::load("openpost"); } catch (LogicException $e) { return; } throw new RuntimeException("collision accepted"); }); echo "guarded";';
        $this->assertSame('guarded', $this->finishChild($this->startChild($code)));
    }

    public function test_twenty_parallel_autocommit_allocations_are_distinct_above_legacy_maximum(): void
    {
        RestApiEnv::rows($this->tenant, 'INSERT INTO openpost(udlign_id) VALUES (900000)');
        RestApiEnv::rows($this->tenant, 'DROP SEQUENCE openpost_udlign_seq');
        $db = var_export(RestApiEnv::testDb(),true);
        $code = 'require __DIR__ . "/tests/restapi/support/RestApiEnv.php"; require __DIR__ . "/includes/openpost/udlignId.php"; '
            . '$c=RestApiEnv::connect(' . $db . '); echo udlign_next_id($c);';
        $children = [];
        for ($i=0; $i<20; $i++) {
            $children[] = $this->startChild($code);
        }
        $ids = array_map(fn(array $child): int => (int)$this->finishChild($child), $children);
        $this->assertCount(20, array_unique($ids));
        $this->assertGreaterThan(900000, min($ids));
        $next = udlign_next_id($this->tenant);
        $this->assertGreaterThan(max($ids), $next);
        $this->assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status($this->tenant));
    }

    public function test_schema_instant_types_and_dst_expiry_reuse_refill_agree(): void
    {
        $tenantColumns = ['mobile_idempotency'=>['created_at'], 'mobile_order_origins'=>['created_at'],
            'document_deliveries'=>['created_at','finished_at'], 'payment_links'=>['expires_at','created_at','paid_at','settled_at'],
            'payment_attempts'=>['last_checked_at','created_at','updated_at','captured_at'],
            'payment_provider_events'=>['received_at','processed_at'], 'payment_registrations'=>['created_at'],
            'mobile_catalogue_changes'=>['changed_at']];
        foreach ($tenantColumns as $table=>$columns) {
            foreach ($columns as $column) {
                $this->assertSame('timestamp with time zone', RestApiEnv::rows($this->tenant,
                    'SELECT data_type FROM information_schema.columns WHERE table_name=$1 AND column_name=$2', [$table,$column])[0]['data_type']);
            }
        }
        $registry = RestApiEnv::connect(self::$registryDb);
        mobileRegistryUpdates($registry);
        try {
            foreach (['mobile_rate_buckets'=>'updated_at','cvr_cache'=>'fetched_at'] as $table=>$column) {
                $this->assertSame('timestamp with time zone', RestApiEnv::rows($registry,
                    'SELECT data_type FROM information_schema.columns WHERE table_name=$1 AND column_name=$2', [$table,$column])[0]['data_type']);
            }
            $results = [];
            foreach (['UTC','Europe/Copenhagen'] as $zone) {
                RestApiEnv::rows($this->tenant, 'SELECT set_config(\'TimeZone\',$1,false)', [$zone]);
                RestApiEnv::rows($registry, 'SELECT set_config(\'TimeZone\',$1,false)', [$zone]);
                foreach (['2026-10-25T02:04:00+02:00','2026-10-25T02:04:00+01:00'] as $instant) {
                    // Explicitly offset fixtures span the repeated local hour, without changing the clock.
                    RestApiEnv::rows($this->tenant, 'BEGIN');
                    $link = RestApiEnv::rows($this->tenant, "INSERT INTO payment_links(token_sha256,provider,openpost_id,konto_id,faktnr,amount,currency,expires_at,created_by,client_id)
                        VALUES ($1,'stub',98765,1,'1',10,'DKK',$2,'time',$3) RETURNING id", [hash('sha256',random_bytes(16)),'2026-10-25T02:10:00+02:00',RequestId::resolve(null)])[0]['id'];
                    RestApiEnv::rows($this->tenant, "INSERT INTO payment_attempts(link_id,provider_reference,amount_minor,currency,created_at)
                        VALUES ($1,$2,1000,'DKK',$3)", [$link,bin2hex(random_bytes(8)),'2026-10-25T01:58:00+02:00']);
                    $results[$zone][$instant] = RestApiEnv::rows($this->tenant, "SELECT
                        expires_at > $1::timestamptz AS unexpired,
                        a.created_at > $1::timestamptz - interval '9 minutes' AS reusable,
                        EXTRACT(EPOCH FROM ($1::timestamptz-a.created_at))::int AS age
                        FROM payment_links l JOIN payment_attempts a ON a.link_id=l.id WHERE l.id=$2", [$instant,$link])[0];
                    RestApiEnv::rows($this->tenant, 'ROLLBACK');
                    RestApiEnv::rows($registry, "INSERT INTO mobile_rate_buckets(scope,bucket_key,tokens,updated_at) VALUES ('cvr','dst',0,$1)
                        ON CONFLICT(scope,bucket_key) DO UPDATE SET tokens=0,updated_at=excluded.updated_at", ['2026-10-25T01:58:00+02:00']);
                    $results[$zone][$instant]['tokens'] = RestApiEnv::rows($registry, "SELECT LEAST(100,tokens+EXTRACT(EPOCH FROM ($1::timestamptz-updated_at))/60)::numeric(12,6) AS tokens
                        FROM mobile_rate_buckets WHERE scope='cvr' AND bucket_key='dst'", [$instant])[0]['tokens'];
                }
                // Also exercise the actual now()-based production predicates.
                RestApiEnv::rows($registry, "UPDATE mobile_rate_buckets SET updated_at=now()-interval '6 minutes' WHERE scope='cvr' AND bucket_key='dst'");
                $this->assertSame('6', RestApiEnv::rows($registry, "SELECT round(EXTRACT(EPOCH FROM (now()-updated_at))/60)::int AS refill FROM mobile_rate_buckets WHERE bucket_key='dst'")[0]['refill']);
            }
            $this->assertSame($results['UTC'], $results['Europe/Copenhagen']);
            $this->assertSame(['unexpired'=>'t','reusable'=>'t','age'=>'360','tokens'=>'6.000000'], $results['UTC']['2026-10-25T02:04:00+02:00']);
            $this->assertSame(['unexpired'=>'f','reusable'=>'f','age'=>'3960','tokens'=>'66.000000'], $results['UTC']['2026-10-25T02:04:00+01:00']);
        } finally {
            pg_close($registry);
        }
    }

    public function test_catalogue_trigger_records_insert_update_delete_with_transaction_identity(): void
    {
        RestApiEnv::rows($this->tenant, 'BEGIN');
        $id = RestApiEnv::rows($this->tenant, "INSERT INTO varer(varenr,beskrivelse) VALUES ($1,'T2 fixture') RETURNING id", ['foundation-' . bin2hex(random_bytes(4))])[0]['id'];
        RestApiEnv::rows($this->tenant, "UPDATE varer SET beskrivelse='Changed' WHERE id=$1", [$id]);
        RestApiEnv::rows($this->tenant, 'DELETE FROM varer WHERE id=$1', [$id]);
        $rows = RestApiEnv::rows($this->tenant, 'SELECT deleted,txid FROM mobile_catalogue_changes WHERE vare_id=$1 ORDER BY id', [$id]);
        $this->assertSame(['f','f','t'], array_column($rows,'deleted'));
        $this->assertCount(1,array_unique(array_column($rows,'txid')));
    }

    public function test_registry_me_permissions_deny_without_tenant_and_allow_after_exchange(): void
    {
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        mobileRegistryUpdates($master);
        $username = 'foundation_' . bin2hex(random_bytes(6));
        $password = TestCredentials::password('t2foundationregistry');
        $tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        $user = RestApiEnv::rows($master, 'INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES ($1,$2,$3,true) RETURNING id',
            [$username,md5($password),',,,' . $tenantId])[0];
        $subject = null;
        try {
            $login = RestApiEnv::http('POST','/restapi/endpoints/v1/auth/registry-login.php',
                ['username'=>$username,'password'=>$password,'device_id'=>'t2foundation-registry']);
            $this->assertSame(200,$login['status'],$login['body']);
            $headers = ['Authorization: Bearer ' . $login['json']['data']['access_token']];
            $tenantless = RestApiEnv::http('GET','/restapi/endpoints/v1/auth/me.php',null,$headers);
            $this->assertSame(200,$tenantless['status'],$tenantless['body']);
            $subject = $tenantless['json']['data']['subject'];
            $this->assertNull($tenantless['json']['data']['tenant']);
            foreach ($tenantless['json']['data']['permissions'] as $permissions) {
                $this->assertSame(['read'=>false,'write'=>false],$permissions);
            }
            $exchange = RestApiEnv::http('POST','/restapi/endpoints/v1/auth/tenant-token.php',['tenant_id'=>$tenantId],$headers);
            $this->assertSame(200,$exchange['status'],$exchange['body']);
            $selected = RestApiEnv::http('GET','/restapi/endpoints/v1/auth/me.php',null,
                ['Authorization: Bearer ' . $exchange['json']['data']['access_token']]);
            $this->assertSame(200,$selected['status'],$selected['body']);
            $this->assertSame($subject,$selected['json']['data']['subject']);
            // T4: employee-bound permissions stay false for a registry principal, which has no employee link.
            $employeeBound = ['sager_loen'=>true,'sager_godkend'=>true,'bookings_all'=>true];
            foreach ($selected['json']['data']['permissions'] as $module => $permissions) {
                $this->assertSame(isset($employeeBound[$module]) ? ['read'=>false,'write'=>false] : ['read'=>true,'write'=>true],$permissions,$module);
            }
            $this->assertSame($tenantless['json']['data']['capabilities'],$selected['json']['data']['capabilities']);
        } finally {
            // The generated registry login never outlives this test, even when an assertion fails.
            foreach (['refresh_sessions','auth_challenges','auth_versions'] as $table) {
                RestApiEnv::rows($master,"DELETE FROM $table WHERE subject LIKE $1", ['%/registry/' . $user['id']]);
            }
            RestApiEnv::rows($master,'DELETE FROM brugere WHERE id=$1',[$user['id']]);
            pg_close($master);
        }
    }

    public function test_me_adds_precise_permissions_and_loads_only_installed_capability_fragments(): void
    {
        $me = RestApiEnv::http('GET','/restapi/endpoints/v1/auth/me.php',null,RestApiEnv::authHeaders());
        $this->assertSame(200,$me['status'],$me['body']);
        $this->assertSame(['read'=>true,'write'=>true],$me['json']['data']['permissions']['debitorordre']);
        $this->assertSame('v2',$me['json']['data']['capabilities']['attachments']);
        $this->assertArrayNotHasKey('foundation_test',$me['json']['data']['capabilities']);
        RestApiEnv::rows($this->tenant, "UPDATE brugere SET rettigheder='0000020000000000' WHERE brugernavn=$1", [RestApiEnv::user()]);
        try {
            $readOnly = RestApiEnv::http('GET','/restapi/endpoints/v1/auth/me.php',null,RestApiEnv::authHeaders());
            $this->assertSame(['read'=>true,'write'=>false],$readOnly['json']['data']['permissions']['debitorordre']);
            $this->assertSame(['read'=>false,'write'=>false],$readOnly['json']['data']['permissions']['debitorkonti']);
            $fragment = __DIR__ . '/../../restapi/core/capabilities.d/foundation_test_' . bin2hex(random_bytes(4)) . '.php';
            file_put_contents($fragment, "<?php return ['foundation_test' => true];\n");
            try {
                $withFlag = RestApiEnv::http('GET','/restapi/endpoints/v1/auth/me.php',null,RestApiEnv::authHeaders());
                $this->assertSame(200,$withFlag['status'],$withFlag['body']);
                $this->assertTrue($withFlag['json']['data']['capabilities']['foundation_test']);
            } finally {
                unlink($fragment);
            }
        } finally {
            RestApiEnv::rows($this->tenant, 'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2', [str_repeat('1',16),RestApiEnv::user()]);
        }
    }
}
