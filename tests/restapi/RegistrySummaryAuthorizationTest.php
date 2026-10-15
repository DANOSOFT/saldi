<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/RegistrySummaryAuthorizationTest.php --- ver 5.0.0 --- 2026.10.05
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

require_once __DIR__ . '/support/T3B6TestCase.php';

final class RegistrySummaryAuthorizationTest extends T3B6TestCase
{
    public function test_registry_endpoint_reads_current_membership_and_excludes_closed_tenants(): void
    {
        $token = $this->pair()['access_token'];
        $member = $this->registryRequest('', $token);
        $this->assertSame(200,$member['status'],$member['body']);
        $this->assertSame([self::$tenantId],array_column($member['json']['data']['items'],'tenant_id'));
        RestApiEnv::rows(self::$registry,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',
            [',,,' . self::$secondId . ',' . self::$closedId,self::$user['id']]);
        $removed = $this->registryRequest('', $token);
        $this->assertSame(200,$removed['status'],$removed['body']);
        $this->assertSame([self::$secondId],array_column($removed['json']['data']['items'],'tenant_id'));
        $this->assertSame(1,$removed['json']['data']['total']);
        RestApiEnv::rows(self::$registry,'UPDATE regnskab SET lukkes=CURRENT_DATE WHERE id=$1',[self::$secondId]);
        $scheduled = $this->registryRequest('', $token);
        $this->assertSame(0,$scheduled['json']['data']['total']);
        RestApiEnv::rows(self::$registry,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',[',,,',self::$user['id']]);
        $empty = $this->registryRequest('', $token);
        $this->assertSame([],$empty['json']['data']['items']);
        $this->assertSame(0,$empty['json']['data']['totals']['tenants']);
    }

    public function test_account_principals_and_missing_tokens_are_denied_and_paging_is_validated(): void
    {
        $path = '/restapi/endpoints/v1/bookkeeper/registry-summary.php';
        $this->assertSame(401,RestApiEnv::http('GET',$path)['status']);
        $account = RestApiEnv::http('GET',$path,null,self::$headers);
        $this->assertSame(403,$account['status'],$account['body']);
        $this->assertSame('permission_denied',$account['json']['data']['error']);
        $token = $this->pair()['access_token'];
        foreach (['?limit=0','?offset=-1','?limit[]=1','?offset=1%20OR%201=1'] as $query) {
            $invalid = $this->registryRequest($query,$token);
            $this->assertSame(422,$invalid['status'],$invalid['body']);
            $this->assertSame('validation_failed',$invalid['json']['data']['error']);
            $this->assertSame('invalid_paging',$invalid['json']['data']['details']['reason']);
        }
        $this->assertSame(405,RestApiEnv::http('POST',$path,[],['Authorization: Bearer ' . $token])['status']);
    }

    public function test_admin_without_explicit_ids_sees_only_live_exchanges_and_admin_ids_are_bounded(): void
    {
        RestApiEnv::rows(self::$registry,"UPDATE brugere SET rettigheder='on,,,' WHERE id=$1",[self::$user['id']]);
        $token = $this->pair()['access_token'];
        $none = $this->registryRequest('',$token);
        $this->assertSame([],$none['json']['data']['items']);
        $this->pair(self::$tenantId);
        $one = $this->registryRequest('',$token);
        $this->assertSame([self::$tenantId],array_column($one['json']['data']['items'],'tenant_id'));
        $this->pair(self::$secondId);
        $this->assertSame(2,$this->registryRequest('',$token)['json']['data']['total']);
        RestApiEnv::rows(self::$registry,'UPDATE refresh_sessions SET revoked_at=now() WHERE subject=$1 AND tenant_id=$2',[self::$subject,self::$secondId]);
        $this->assertSame(1,$this->registryRequest('',$token)['json']['data']['total']);
        RestApiEnv::rows(self::$registry,"UPDATE refresh_sessions SET expires_at=now()-interval '1 second' WHERE subject=$1 AND tenant_id=$2",[self::$subject,self::$tenantId]);
        $this->assertSame(0,$this->registryRequest('',$token)['json']['data']['total']);
        RestApiEnv::rows(self::$registry,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',['on,,,' . self::$secondId,self::$user['id']]);
        $explicit = $this->registryRequest('',$token);
        $this->assertSame([self::$secondId],array_column($explicit['json']['data']['items'],'tenant_id'));
    }

    public function test_budget_is_25_uncached_tenants_with_pending_progress_and_201_is_truncated(): void
    {
        $ids=[];
        try {
            for ($i=0;$i<201;$i++) {
                $ids[]=(int)RestApiEnv::rows(self::$registry,"INSERT INTO regnskab(regnskab,db,lukket) VALUES($1,$2,'') RETURNING id",
                    [sprintf('b6-budget-%03d',$i) . '-' . bin2hex(random_bytes(4)),RestApiEnv::testDb()])[0]['id'];
            }
            RestApiEnv::rows(self::$registry,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',[',,,' . implode(',',$ids),self::$user['id']]);
            $service = new class(self::$registry) extends BookkeeperSummaryService {
                public int $computations=0;
                protected function computeTenant(array $tenant,float $deadline): array
                {
                    $this->computations++;
                    return $this->forTenant(RegistrySummaryAuthorizationTest::tenantLink(),null,$tenant);
                }
            };
            $first=$service->forRegistry((int)self::$user['id'],self::$subject,['limit'=>50]);
            $this->assertSame(25,$service->computations);
            $this->assertSame(25,$first['pending_count']);
            $this->assertTrue($first['truncated']);
            $this->assertSame(200,$first['total']);
            $this->assertSame('ok',$first['items'][24]['state']);
            $this->assertSame('pending',$first['items'][25]['state']);
            $second=$service->forRegistry((int)self::$user['id'],self::$subject,['limit'=>50]);
            $this->assertSame(50,$service->computations);
            $this->assertSame(0,$second['pending_count']);
            $this->assertSame($first['items'][0],$second['items'][0]);
            $page=$service->forRegistry((int)self::$user['id'],self::$subject,['limit'=>100,'offset'=>199]);
            $this->assertCount(1,$page['items']);
            $this->assertSame($ids[199],$page['items'][0]['tenant_id']);
            $empty=$service->forRegistry((int)self::$user['id'],self::$subject,['offset'=>200]);
            $this->assertSame([],$empty['items']);
            $this->assertSame(200,$empty['total']);
        } finally {
            if ($ids) {
                $param=['{' . implode(',',$ids) . '}'];
                RestApiEnv::rows(self::$registry,'DELETE FROM mobile_summary_cache WHERE tenant_id=ANY($1::int[])',$param);
                RestApiEnv::rows(self::$registry,'DELETE FROM regnskab WHERE id=ANY($1::int[])',$param);
            }
        }
    }

    /** Explicit link for the deterministic computation-count fixture above. */
    public static function tenantLink() { return self::$tenant; }

    public function test_eight_second_budget_returns_last_cache_or_pending_without_false_zeros(): void
    {
        RestApiEnv::rows(self::$registry,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',
            [',,,' . self::$tenantId . ',' . self::$secondId,self::$user['id']]);
        $now=100.0;
        $service=new class(self::$registry,static function() use (&$now): float { return $now; },$now) extends BookkeeperSummaryService {
            private $now;
            public int $computations=0;
            public function __construct($registry,callable $clock,float &$now)
            {
                parent::__construct($registry,$clock);
                $this->now=&$now;
            }
            protected function computeTenant(array $tenant,float $deadline): array
            {
                $result=$this->forTenant(RegistrySummaryAuthorizationTest::tenantLink(),null,$tenant);
                $this->now+=8.01;
                $this->computations++;
                return $result;
            }
        };
        $result=$service->forRegistry((int)self::$user['id'],self::$subject);
        $this->assertSame(1,$service->computations);
        $this->assertSame(1,$result['pending_count']);
        $this->assertSame(['ok','pending'],array_column($result['items'],'state'));
        $pending=$result['items'][1];
        $this->assertNull($pending['pending_attachments']);
        $cached=$this->service()->forTenant(self::$second);
        unset($cached['name'],$cached['tenant_id']);
        RestApiEnv::rows(self::$registry,"INSERT INTO mobile_summary_cache(tenant_id,payload,computed_at) VALUES($1,$2,now()-interval '3 minutes')",
            [self::$secondId,json_encode($cached,JSON_THROW_ON_ERROR)]);
        $ticks=0;
        $exhausted=$this->service(static function() use (&$ticks): float { return $ticks++ === 0 ? 0.0 : 9.0; });
        $stale=$exhausted->forRegistry((int)self::$user['id'],self::$subject);
        $this->assertTrue($stale['items'][1]['stale']);
        $this->assertSame('ok',$stale['items'][1]['state']);
        $this->assertSame($cached['computed_at'],$stale['items'][1]['computed_at']);
    }

    public function test_warmer_requires_live_registry_exchange_and_is_cli_only(): void
    {
        $this->pair();
        $this->assertSame([],$this->service()->warm(self::$tenantId));
        $this->pair(self::$tenantId);
        $result=$this->service()->warm(self::$tenantId);
        $this->assertSame([['tenant_id'=>self::$tenantId,'state'=>'ok','stale'=>false]],$result);
        $this->assertCount(1,RestApiEnv::rows(self::$registry,'SELECT * FROM mobile_summary_cache WHERE tenant_id=$1',[self::$tenantId]));
        RestApiEnv::rows(self::$registry,'DELETE FROM mobile_summary_cache WHERE tenant_id=$1',[self::$tenantId]);
        $command=[PHP_BINARY,dirname(__DIR__,2) . '/cron/bookkeeper_summary.php','--tenant-id=' . self::$tenantId];
        $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]);
        $output=stream_get_contents($pipes[1]);
        $error=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $this->assertSame(0,proc_close($process),$error);
        $this->assertSame('ok',json_decode(trim($output),true,512,JSON_THROW_ON_ERROR)['state']);
        $this->assertSame(403,RestApiEnv::http('GET','/cron/bookkeeper_summary.php')['status']);
    }

    public function test_unavailable_tenant_is_isolated_as_error_without_database_details(): void
    {
        $missingDb='b6_missing_' . bin2hex(random_bytes(4));
        $id=(int)RestApiEnv::rows(self::$registry,"INSERT INTO regnskab(regnskab,db,lukket) VALUES('Unavailable fixture',$1,'') RETURNING id",[$missingDb])[0]['id'];
        try {
            RestApiEnv::rows(self::$registry,'UPDATE brugere SET rettigheder=$1 WHERE id=$2',[',,,' . self::$tenantId . ',' . $id,self::$user['id']]);
            $response=$this->registryRequest();
            $this->assertSame(200,$response['status'],$response['body']);
            $items=array_column($response['json']['data']['items'],null,'tenant_id');
            $this->assertSame('ok',$items[self::$tenantId]['state']);
            $this->assertSame('error',$items[$id]['state']);
            $this->assertNull($items[$id]['pending_attachments']);
            $this->assertStringNotContainsString($missingDb,$response['body']);
            $this->assertArrayNotHasKey('error',$items[$id]);
        } finally {
            RestApiEnv::rows(self::$registry,'DELETE FROM mobile_summary_cache WHERE tenant_id=$1',[$id]);
            RestApiEnv::rows(self::$registry,'DELETE FROM regnskab WHERE id=$1',[$id]);
        }
    }

    public function test_parallel_http_summaries_share_one_cache_snapshot(): void
    {
        $token=$this->pair()['access_token'];
        $multi=curl_multi_init();
        $requests=[];
        try {
            for ($i=0;$i<2;$i++) {
                $request=curl_init(RestApiEnv::baseUrl() . '/restapi/endpoints/v1/bookkeeper/registry-summary.php');
                curl_setopt_array($request,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,
                    CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $token]]);
                $requests[]=$request;
                curl_multi_add_handle($multi,$request);
            }
            do {
                curl_multi_exec($multi,$running);
                if ($running) {
                    curl_multi_select($multi,0.1);
                }
            } while ($running);
            $ok=0;
            foreach ($requests as $request) {
                $body=curl_multi_getcontent($request);
                $this->assertSame(200,curl_getinfo($request,CURLINFO_RESPONSE_CODE),$body);
                $item=json_decode($body,true,512,JSON_THROW_ON_ERROR)['data']['items'][0];
                $this->assertContains($item['state'],['ok','pending']);
                $ok+=$item['state']==='ok' ? 1 : 0;
            }
            $this->assertGreaterThanOrEqual(1,$ok);
            $rows=RestApiEnv::rows(self::$registry,'SELECT * FROM mobile_summary_cache WHERE tenant_id=$1',[self::$tenantId]);
            $this->assertCount(1,$rows);
            $retry=$this->registryRequest('',$token);
            $this->assertFalse($retry['json']['data']['items'][0]['stale']);
        } finally {
            foreach ($requests as $request) {
                curl_multi_remove_handle($multi,$request);
                curl_close($request);
            }
            curl_multi_close($multi);
        }
    }
}
