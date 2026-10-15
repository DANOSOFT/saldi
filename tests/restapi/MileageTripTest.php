<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MileageTripTest.php --- ver 5.0.0 --- 2026.10.06
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


require_once __DIR__.'/support/T4ClaimsTestCase.php';
final class MileageTripTest extends T4ClaimsTestCase
{
    private const ROUTE='employee/mileage/trips.php';
    public function test_crud_owner_etags_and_idempotency(): void
    {
        $body=$this->tripBody();
        $created=$this->request('POST',self::ROUTE,$body);
        $trip=$this->assertStatus(201,$created);
        $this->assertSame('12.5',$trip['km']);
        $this->assertSame('47.38',$trip['amount_preview']);
        $this->assertSame($created['json'],$this->request('POST',self::ROUTE,$body)['json']);
        $this->assertStatus(409,$this->request('POST',self::ROUTE,array_replace($body,['km'=>'20.0'])));
        $curl=curl_init(RestApiEnv::baseUrl().'/restapi/endpoints/v1/'.self::ROUTE);
        $responseHeaders=[];
        curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>'POST',CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,
            CURLOPT_HTTPHEADER=>array_merge(self::$headers,['Content-Type: application/json']),
            CURLOPT_HEADERFUNCTION=>static function ($handle,string $line) use (&$responseHeaders): int {
                if (str_contains($line,':')) {
                    [$name,$value]=explode(':',$line,2);
                    $responseHeaders[strtolower(trim($name))]=trim($value);
                }
                return strlen($line);
            }]);
        try {
            $raw=curl_exec($curl);
            $this->assertSame(201,(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),(string)$raw);
            $this->assertSame('true',$responseHeaders['idempotent-replayed']);
            $this->assertSame($trip['etag'],$responseHeaders['etag']);
            $this->assertSame('private, no-store',$responseHeaders['cache-control']);
        } finally { curl_close($curl); }
        $url=self::ROUTE.'?id='.$trip['id'];
        $this->assertSame($trip,$this->assertStatus(200,$this->request('GET',$url)));
        $this->assertStatus(404,$this->request('GET',$url,null,self::$otherHeaders));
        $this->assertStatus(404,$this->request('PATCH',$url,$this->body(['km'=>'20.0']),array_merge(self::$otherHeaders,['If-Match: '.$trip['etag']])));
        $this->assertStatus(404,$this->request('DELETE',$url,$this->body(),array_merge(self::$otherHeaders,['If-Match: '.$trip['etag']])));
        $this->assertStatus(422,$this->request('PATCH',$url,$this->body(['km'=>'20.0'])));
        $this->assertStatus(412,$this->request('PATCH',$url,$this->body(['km'=>'20.0']),array_merge(self::$headers,['If-Match: "stale"'])));
        $patch=$this->body(['km'=>'20.0']);
        $headers=array_merge(self::$headers,['If-Match: '.$trip['etag']]);
        $updated=$this->assertStatus(200,$this->request('PATCH',$url,$patch,$headers));
        $this->assertNotSame($trip['etag'],$updated['etag']);
        $this->assertSame($updated,$this->assertStatus(200,$this->request('PATCH',$url,$patch,$headers)));
        $delete=$this->body();
        $headers=array_merge(self::$headers,['If-Match: '.$updated['etag']]);
        $deleted=$this->assertStatus(200,$this->request('DELETE',$url,$delete,$headers));
        $this->assertTrue($deleted['deleted']);
        $this->assertSame($deleted,$this->assertStatus(200,$this->request('DELETE',$url,$delete,$headers)));
        $this->assertStatus(404,$this->request('GET',$url));
        $this->assertSame(0,$this->assertStatus(200,$this->request('GET',self::ROUTE,null,self::$otherHeaders))['total']);
    }
    public function test_validation_missing_rates_and_unlinked_scope(): void
    {
        foreach (['0','2000.1','1.01','-1','1e2',12.5] as $km) {
            $this->assertStatus(422,$this->request('POST',self::ROUTE,$this->tripBody(['km'=>$km])));
        }
        foreach (['2026-02-30','2026-13-01','2026-1-1'] as $date) {
            $this->assertStatus(422,$this->request('POST',self::ROUTE,$this->tripBody(['date'=>$date])));
        }
        foreach (['from'=>'','to'=>str_repeat('x',201),'purpose'=>"unsafe\ntext"] as $field=>$value) {
            $this->assertStatus(422,$this->request('POST',self::ROUTE,$this->tripBody([$field=>$value])));
        }
        $trip=$this->assertStatus(201,$this->request('POST',self::ROUTE,$this->tripBody(['date'=>'2028-01-01','km'=>'2000.0'])));
        $this->assertNull($trip['rate_preview']);
        $this->assertNull($trip['amount_preview']);
        self::sql('UPDATE brugere SET ansat_id=NULL WHERE id=$1',[self::$otherId]);
        try {
            $service=$this->service(self::$otherId);
            $this->assertSame(Subject::build('account',self::$otherId),$service->allowanceScope());
            $result=$service->createTrip($this->tripBody());
            $this->assertNull(self::sql('SELECT ansat_id FROM mobile_mileage_trips WHERE id=$1',[$result['body']['data']['id']])[0]['ansat_id']);
        } finally { self::sql('UPDATE brugere SET ansat_id=$1 WHERE id=$2',[self::$employeeId,self::$otherId]); }
    }
    public function test_statement_assignment_prevents_changes_even_with_prior_etag(): void
    {
        $trip=$this->assertStatus(201,$this->request('POST',self::ROUTE,$this->tripBody()));
        $id=$this->statement($this->service()->allowanceScope(),2026,'12.5');
        self::sql('UPDATE mobile_mileage_trips SET statement_id=$1 WHERE id=$2',[$id,$trip['id']]);
        $url=self::ROUTE.'?id='.$trip['id'];
        $read=$this->assertStatus(200,$this->request('GET',$url));
        $this->assertSame(['id'=>$id,'state'=>'generating'],$read['statement']);
        $this->assertNotSame($trip['etag'],$read['etag']);
        foreach (['PATCH','DELETE'] as $method) {
            $response=$this->request($method,$url,$this->body(['km'=>'15.0']),array_merge(self::$headers,['If-Match: '.$trip['etag']]));
            $this->assertStatus(409,$response);
            $this->assertSame('trip_claimed',$response['json']['data']['details']['reason']);
        }
    }
}
