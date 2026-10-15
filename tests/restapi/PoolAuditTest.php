<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/PoolAuditTest.php --- ver 5.0.0 --- 2026.10.05
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

require_once __DIR__ . '/support/T3B2aTestCase.php';

final class PoolAuditTest extends T3B2aTestCase
{
    public function testWebAndRevisorMetadataActorsAndNestedScopesRestore(): void
    {
        $row=$this->fixture();
        poolMetadataSave($row['filename'],['description'=>'Web edit'],true,poolMetadataVersion($row),
            (new EngineContext(self::$conn,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RestApiEnv::user(),self::$userId,'2026-09-14'))->regnaar);
        $events=pool_audit_read((int)$row['id'])['items'];
        $this->assertSame(self::$owner,end($events)['actor']);
        $GLOBALS['revisor']=true;
        $GLOBALS['bruger_id']=-1;
        $GLOBALS['brugernavn']='Registry reviewer';
        $row=$this->row((int)$row['id']);
        poolMetadataSave($row['filename'],['description'=>'Revisor edit'],true,poolMetadataVersion($row),
            (new EngineContext(self::$conn,RestApiEnv::testDb(),RestApiEnv::regnskabId(RestApiEnv::accountOpen()),RestApiEnv::user(),self::$userId,'2026-09-14'))->regnaar);
        $events=pool_audit_read((int)$row['id'])['items'];
        $this->assertSame('web:Registry reviewer',end($events)['actor']);
        $GLOBALS['revisor']=false;
        $GLOBALS['bruger_id']=self::$userId;
        $GLOBALS['brugernavn']=RestApiEnv::user();
        pool_audit_actor_scope('outer',null,function () {
            try { pool_audit_actor_scope('inner',RequestId::resolve(null),static function () { throw new RuntimeException('test'); }); }
            catch (RuntimeException $error) { $this->assertSame('test',$error->getMessage()); }
            $this->assertSame('outer',RestApiEnv::rows(self::$conn,"SELECT current_setting('saldi.actor',true) AS actor")[0]['actor']);
        });
        $this->assertSame('',RestApiEnv::rows(self::$conn,"SELECT current_setting('saldi.actor',true) AS actor")[0]['actor']);
        $this->assertTrue(pool_audit_verify((int)$row['id']));
        $visible=$this->service()->audit((int)$row['id'],[])['items'];
        $this->assertSame(['name'=>RestApiEnv::user(),'kind'=>'app'],$visible[0]['actor']);
        $this->assertSame(['name'=>RestApiEnv::user(),'kind'=>'web'],$visible[1]['actor']);
        $this->assertSame(['name'=>'Registry reviewer','kind'=>'web'],$visible[2]['actor']);
    }
    public function testRestoreResetsReviewAndTriggersOneReturnedWithWebActor(): void
    {
        $row=$this->fixture('attached','approved');
        RestApiEnv::rows(self::$conn,"UPDATE pool_files SET reviewed_by=$1,reviewed_at=now(),rejection_code='other',rejection_note='Old',resubmitted_at=now() WHERE id=$2",[self::$reviewer,$row['id']]);
        $source=self::$root.'/'.$row['storage_path'];
        file_put_contents($source,'fixture');
        $target=self::$root.'/pulje/returned-'.$row['filename'];
        $this->assertTrue(PoolFileTransitions::restore(RestApiEnv::testDb(),$source,$target));
        $after=$this->row((int)$row['id']);
        $this->assertSame('pending',$after['review_state']);
        foreach (['reviewed_by','reviewed_at','rejection_code','rejection_note','resubmitted_at'] as $field) { $this->assertNull($after[$field]); }
        $returns=array_values(array_filter(pool_audit_read((int)$row['id'])['items'],static fn($event)=>$event['event']==='returned'));
        $this->assertCount(1,$returns);
        $this->assertSame(self::$owner,$returns[0]['actor']);
        $this->assertTrue(pool_audit_verify((int)$row['id']));
    }
    public function testLifecycleScopesUseWebUserAndPreserveAppRegistryScope(): void
    {
        $row=$this->fixture();
        $path=self::$root.'/'.$row['storage_path'];
        file_put_contents($path,'fixture');
        PoolFileTransitions::attach(RestApiEnv::testDb(),$path,self::$userId);
        $events=pool_audit_read((int)$row['id'])['items'];
        $this->assertSame(self::$owner,end($events)['actor']);
        $registry=Subject::build('registry',self::$userId);
        pool_audit_actor_scope($registry,RequestId::resolve(null),static fn()=>PoolFileTransitions::delete(RestApiEnv::testDb(),$path));
        $events=pool_audit_read((int)$row['id'])['items'];
        $this->assertSame($registry,end($events)['actor']);
        $this->assertSame('deleted',end($events)['event']);
    }
    public function testAuditAccessPagingAndFullChainDetectsTamperingOutsidePage(): void
    {
        $row=$this->fixture();
        pool_audit_append((int)$row['id'],'rejected','web:Reviewer',[],'other','Please fix');
        $route='/restapi/endpoints/v1/attachment/audit.php?id='.$row['id'].'&limit=1&offset=1';
        foreach ([self::$ownerHeaders,self::$reviewerHeaders] as $headers) {
            $response=RestApiEnv::http('GET',$route,null,$headers);
            $this->assertSame(200,$response['status'],$response['body']);
            $data=$response['json']['data'];
            $this->assertSame(2,$data['total']);
            $this->assertCount(1,$data['items']);
            $this->assertSame(['name'=>'Reviewer','kind'=>'web'],$data['items'][0]['actor']);
            $this->assertTrue($data['chain_ok']);
            $this->assertArrayNotHasKey('row_sha256',$data['items'][0]);
        }
        $this->assertSame(404,RestApiEnv::http('GET',$route,null,self::$otherHeaders)['status']);
        RestApiEnv::rows(self::$conn,'ALTER TABLE pool_file_audit DISABLE TRIGGER mobile_audit_immutable');
        try {
            RestApiEnv::rows(self::$conn,"UPDATE pool_file_audit SET note='Tampered' WHERE id=(SELECT min(id) FROM pool_file_audit WHERE pool_file_id=$1)",[$row['id']]);
            $this->assertFalse(pool_audit_verify((int)$row['id']));
            $response=RestApiEnv::http('GET',$route,null,self::$ownerHeaders);
            $this->assertFalse($response['json']['data']['chain_ok']);
        } finally { RestApiEnv::rows(self::$conn,'ALTER TABLE pool_file_audit ENABLE TRIGGER mobile_audit_immutable'); }
    }
    public function testApplyRequiresTransactionAndCallerRollbackRemovesAudit(): void
    {
        $row=$this->fixture();
        try { poolMetadataApply($row,[],false,null,1); $this->fail('No transaction accepted'); }
        catch (LogicException $error) { $this->assertStringContainsString('transaction',$error->getMessage()); }
        $before=pool_audit_read((int)$row['id'])['total'];
        RestApiEnv::rows(self::$conn,'BEGIN');
        try {
            $locked=RestApiEnv::rows(self::$conn,'SELECT * FROM pool_files WHERE id=$1 FOR UPDATE',[$row['id']])[0];
            poolMetadataApply($locked,['description'=>'Rolled back'],false,null,1);
            $this->assertSame(PGSQL_TRANSACTION_INTRANS,pg_transaction_status(self::$conn));
            $this->assertSame($before+1,pool_audit_read((int)$row['id'])['total']);
        } finally { RestApiEnv::rows(self::$conn,'ROLLBACK'); }
        $this->assertSame($row,$this->row((int)$row['id']));
        $this->assertSame($before,pool_audit_read((int)$row['id'])['total']);
    }
}
