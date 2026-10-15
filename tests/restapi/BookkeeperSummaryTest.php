<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/BookkeeperSummaryTest.php --- ver 5.0.0 --- 2026.10.05
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

final class BookkeeperSummaryTest extends T3B6TestCase
{
    private static int $journal;
    private static int $matchedBank;
    private static int $document;
    private static array $pending;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        RestApiEnv::rows(self::$tenant,'DELETE FROM openpost');
        RestApiEnv::rows(self::$second,'DELETE FROM openpost');
        self::$pending = self::pool(self::$tenant,'ubehandlet','pending',6);
        self::pool(self::$tenant,'ubehandlet','pending',2);
        self::pool(self::$tenant,'ubehandlet','rejected',3);
        self::pool(self::$tenant,'attached','approved',1);
        self::pool(self::$tenant,'staged','pending',0);
        self::pool(self::$tenant,'deleted','pending',8);
        self::pool(self::$second,'ubehandlet','pending',9);
        RestApiEnv::rows(self::$tenant,"INSERT INTO reminders(target_subject,message,created_by,created_at)
            VALUES('all','Fixture reminder',$1,now()-interval '1 hour')",[self::$subject]);
        self::$journal = (int)RestApiEnv::rows(self::$tenant,"INSERT INTO kladdeliste(id,bogfort)
            SELECT COALESCE(max(id),0)+1,'-' FROM kladdeliste RETURNING id")[0]['id'];
        $posted = self::$journal+1;
        RestApiEnv::rows(self::$tenant,"INSERT INTO kladdeliste(id,bogfort) VALUES($1,'V')",[$posted]);
        $line = (int)RestApiEnv::rows(self::$tenant,'INSERT INTO kassekladde(kladde_id,bilag) VALUES($1,1) RETURNING id',[self::$journal])[0]['id'];
        $postedLine = (int)RestApiEnv::rows(self::$tenant,'INSERT INTO kassekladde(kladde_id,bilag) VALUES($1,2) RETURNING id',[$posted])[0]['id'];
        foreach ([[$line,self::$journal,'new_line'],[$line,self::$journal,'bank_row'],[$postedLine,$posted,'bank_row'],
            [2147483647,self::$journal,'new_line'],[$line,self::$journal,'attach_only']] as [$rowId,$journal,$mode]) {
            RestApiEnv::rows(self::$tenant,'INSERT INTO mobile_journal_lines(kassekladde_id,kladde_id,pool_file_id,mode,currency,created_by,client_id)
                VALUES($1,$2,$3,$4,$5,$6,$7)',[$rowId,$journal,self::$pending['id'],$mode,'DKK',self::$subject,MobileAuth::uuid()]);
        }
        $import = (int)RestApiEnv::rows(self::$tenant,"INSERT INTO bank_line_imports(source,account_kontonr,kladde_id,imported_by)
            VALUES('csv',6471,$1,$2) RETURNING id",[self::$journal,self::$subject])[0]['id'];
        foreach ([['open','-100.25',true,false,false],['requested','-20.00',true,false,false],
            ['received','-30.00',true,true,false],['ignored','-40.00',true,false,false],
            ['open','-50.00',true,false,true],['open','-60.00',false,false,false],['open','70.00',true,false,false]]
            as [$state,$amount,$live,$receipt,$document]) {
            $bank = (int)RestApiEnv::rows(self::$tenant,"INSERT INTO bank_lines(account_kontonr,line_key,source,transdate,amount,currency,text,first_import_id,state)
                VALUES(6471,$1,'csv',CURRENT_DATE,$2,'DKK','Fixture bank line',$3,$4) RETURNING id",
                [hash('sha256',random_bytes(16)),$amount,$import,$state])[0]['id'];
            $journalRow = $live ? (int)RestApiEnv::rows(self::$tenant,'INSERT INTO kassekladde(kladde_id,bilag) VALUES($1,3) RETURNING id',[self::$journal])[0]['id'] : 2147483647;
            RestApiEnv::rows(self::$tenant,'INSERT INTO bank_line_journal_links(bank_line_id,kassekladde_id,import_id) VALUES($1,$2,$3)',[$bank,$journalRow,$import]);
            if ($receipt) {
                RestApiEnv::rows(self::$tenant,'UPDATE bank_lines SET pool_file_id=$1 WHERE id=$2',[self::$pending['id'],$bank]);
                RestApiEnv::rows(self::$tenant,'UPDATE pool_files SET bank_line_id=$1 WHERE id=$2',[$bank,self::$pending['id']]);
            }
            if ($document) {
                self::$matchedBank = $bank;
                self::$document = (int)RestApiEnv::rows(self::$tenant,"INSERT INTO documents(source,source_id,filename)
                    VALUES('kassekladde',$1,'fixture.pdf') RETURNING id",[$journalRow])[0]['id'];
            }
        }
        $debtor = (int)RestApiEnv::rows(self::$tenant,"INSERT INTO adresser(art,kontonr,firmanavn) VALUES('D','b6-debtor','B6 debtor') RETURNING id")[0]['id'];
        foreach ([['100.00','1'],['-25.00','1'],['70.00','2']] as [$amount,$invoice]) {
            RestApiEnv::rows(self::$tenant,"INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,transdate,forfaldsdate,valuta,valutakurs,udlignet,kladde_id)
                VALUES($1,'b6-debtor',$2,$3,CURRENT_DATE-20,CURRENT_DATE-10,'DKK',100,'0',0)",[$debtor,$invoice,$amount]);
        }
    }

    public function test_tenant_counts_match_live_relationships_and_existing_openposts_engine(): void
    {
        $summary = $this->service()->forTenant(self::$tenant);
        $this->assertSame(self::$tenantId,$summary['tenant_id']);
        $this->assertSame(RestApiEnv::accountOpen(),$summary['name']);
        $this->assertSame(2,$summary['pending_attachments']);
        $this->assertSame(6,$summary['oldest_pending_days']);
        $this->assertSame(1,$summary['rejected_open']);
        $this->assertSame(2,$summary['bank_lines_missing']);
        $this->assertSame('120.25',$summary['bank_lines_missing_total']);
        $this->assertSame(1,$summary['draft_lines_open']);
        $this->assertTrue($summary['needs_attention']);
        $this->assertNotNull($summary['last_upload_at']);
        $this->assertNotNull($summary['last_reminder_at']);
        $open = openposts_reader(self::$tenant)->summary();
        $this->assertSame(['count'=>$open['overdue_count'],'total'=>$open['overdue_total'],'currency'=>$open['currency']],$summary['overdue']);
        $this->assertSame('145.00',$summary['overdue']['total']);
        foreach (['last_upload_at','last_reminder_at','computed_at'] as $key) {
            $this->assertSame('+00:00',substr($summary[$key],-6));
        }
        $second = $this->service()->forTenant(self::$second);
        $this->assertSame(self::$secondId,$second['tenant_id']);
        $this->assertSame(1,$second['pending_attachments']);
        $this->assertSame(9,$second['oldest_pending_days']);
        $this->assertSame(0,$second['bank_lines_missing']);
        $this->assertSame('0.00',$second['bank_lines_missing_total']);
        $this->assertNull($second['last_reminder_at']);
        $this->assertSame(PGSQL_TRANSACTION_IDLE,pg_transaction_status(self::$tenant));
    }

    public function test_web_detach_and_posting_are_reflected_without_summary_hooks(): void
    {
        RestApiEnv::rows(self::$tenant,'BEGIN');
        try {
            RestApiEnv::rows(self::$tenant,'DELETE FROM documents WHERE id=$1',[self::$document]);
            RestApiEnv::rows(self::$tenant,'COMMIT');
            $summary = $this->service()->forTenant(self::$tenant);
            $this->assertSame(3,$summary['bank_lines_missing']);
            $this->assertSame('170.25',$summary['bank_lines_missing_total']);
        } finally {
            $link = RestApiEnv::rows(self::$tenant,'SELECT kassekladde_id FROM bank_line_journal_links WHERE bank_line_id=$1',[self::$matchedBank])[0];
            RestApiEnv::rows(self::$tenant,"INSERT INTO documents(id,source,source_id,filename) VALUES($1,'kassekladde',$2,'fixture.pdf')",[self::$document,$link['kassekladde_id']]);
        }
        RestApiEnv::rows(self::$tenant,"UPDATE kladdeliste SET bogfort='V' WHERE id=$1",[self::$journal]);
        try {
            $this->assertSame(0,$this->service()->forTenant(self::$tenant)['draft_lines_open']);
        } finally {
            RestApiEnv::rows(self::$tenant,"UPDATE kladdeliste SET bogfort='-' WHERE id=$1",[self::$journal]);
        }
    }

    public function test_http_summary_masks_overdue_using_current_permissions_and_refuses_capture_users(): void
    {
        $path = '/restapi/endpoints/v1/bookkeeper/summary.php';
        $full = RestApiEnv::http('GET',$path,null,self::$headers);
        $this->assertSame(200,$full['status'],$full['body']);
        $this->assertSame(2,$full['json']['data']['pending_attachments']);
        $this->assertSame('145.00',$full['json']['data']['overdue']['total']);
        RestApiEnv::rows(self::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',['0020000000000000',RestApiEnv::USER]);
        $limited = RestApiEnv::http('GET',$path,null,self::$headers);
        $this->assertSame(200,$limited['status'],$limited['body']);
        $this->assertNull($limited['json']['data']['overdue']);
        $this->assertSame(2,$limited['json']['data']['pending_attachments']);
        RestApiEnv::rows(self::$tenant,'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2',[str_repeat('0',16),RestApiEnv::USER]);
        $denied = RestApiEnv::http('GET',$path,null,self::$headers);
        $this->assertSame(403,$denied['status'],$denied['body']);
        $this->assertSame('permission_denied',$denied['json']['data']['error']);
        $this->assertSame(401,RestApiEnv::http('GET',$path)['status']);
        $this->assertSame(405,RestApiEnv::http('POST',$path,[],self::$headers)['status']);
    }

    public function test_registry_cache_is_reused_expires_and_has_no_user_or_registry_label(): void
    {
        $first = $this->service()->forRegistry((int)self::$user['id'],self::$subject);
        $this->assertSame('ok',$first['items'][0]['state']);
        $this->assertFalse($first['items'][0]['stale']);
        $cache = RestApiEnv::rows(self::$registry,'SELECT * FROM mobile_summary_cache WHERE tenant_id=$1',[self::$tenantId])[0];
        $payload = json_decode($cache['payload'],true,512,JSON_THROW_ON_ERROR);
        foreach (['name','subject','uploaded_by','user_id','tenant_id','db'] as $key) {
            $this->assertArrayNotHasKey($key,$payload);
        }
        $added = self::pool(self::$tenant);
        try {
            $second = $this->service()->forRegistry((int)self::$user['id'],self::$subject);
            $this->assertSame($first['items'],$second['items']);
            $this->assertSame($cache,RestApiEnv::rows(self::$registry,'SELECT * FROM mobile_summary_cache WHERE tenant_id=$1',[self::$tenantId])[0]);
            RestApiEnv::rows(self::$registry,"UPDATE mobile_summary_cache SET computed_at=now()-interval '121 seconds' WHERE tenant_id=$1",[self::$tenantId]);
            $third = $this->service()->forRegistry((int)self::$user['id'],self::$subject);
            $this->assertSame(3,$third['items'][0]['pending_attachments']);
            $this->assertFalse($third['items'][0]['stale']);
        } finally {
            RestApiEnv::rows(self::$tenant,"UPDATE pool_files SET status='deleted' WHERE id=$1",[$added['id']]);
        }
    }

    public function test_busy_cache_writer_returns_stale_or_pending_without_waiting(): void
    {
        $locker = RestApiEnv::connect(RestApiEnv::masterDb());
        $service = $this->service();
        $service->forRegistry((int)self::$user['id'],self::$subject);
        RestApiEnv::rows(self::$registry,"UPDATE mobile_summary_cache SET computed_at=now()-interval '3 minutes' WHERE tenant_id=$1",[self::$tenantId]);
        RestApiEnv::rows($locker,"SELECT pg_advisory_lock(hashtext('saldi:bookkeeper_summary'),$1)",[self::$tenantId]);
        try {
            $stale = $service->forRegistry((int)self::$user['id'],self::$subject);
            $this->assertTrue($stale['items'][0]['stale']);
            $this->assertSame('ok',$stale['items'][0]['state']);
            $this->assertSame(2,$stale['items'][0]['pending_attachments']);
            RestApiEnv::rows(self::$registry,'DELETE FROM mobile_summary_cache WHERE tenant_id=$1',[self::$tenantId]);
            $pending = $service->forRegistry((int)self::$user['id'],self::$subject);
            $this->assertSame('pending',$pending['items'][0]['state']);
            $this->assertNull($pending['items'][0]['pending_attachments']);
            $this->assertSame(1,$pending['pending_count']);
        } finally {
            pg_close($locker);
        }
    }

    public function test_empty_queue_uses_null_age_and_rights_do_not_enter_shared_cache(): void
    {
        RestApiEnv::rows(self::$second,"UPDATE pool_files SET review_state='rejected'");
        try {
            $empty = $this->service()->forTenant(self::$second,['debitorrapport'=>['read'=>false]]);
            $this->assertSame(0,$empty['pending_attachments']);
            $this->assertNull($empty['oldest_pending_days']);
            $this->assertFalse($empty['needs_attention']);
            $this->assertNull($empty['overdue']);
            $this->assertSame([],RestApiEnv::rows(self::$registry,'SELECT * FROM mobile_summary_cache WHERE tenant_id=$1',[self::$secondId]));
        } finally {
            RestApiEnv::rows(self::$second,"UPDATE pool_files SET review_state='pending'");
        }
    }

    public function test_blocked_tenant_query_obeys_eight_second_deadline_and_releases_cache_lock(): void
    {
        $locker=RestApiEnv::connect(RestApiEnv::testDb());
        RestApiEnv::rows($locker,'BEGIN');
        RestApiEnv::rows($locker,'LOCK TABLE pool_files IN ACCESS EXCLUSIVE MODE');
        $started=hrtime(true)/1e9;
        try {
            $result=$this->service()->forRegistry((int)self::$user['id'],self::$subject);
            $this->assertSame('pending',$result['items'][0]['state']);
            $this->assertSame(1,$result['pending_count']);
            $this->assertLessThan(10.0,hrtime(true)/1e9-$started);
            $this->assertSame([],RestApiEnv::rows(self::$registry,'SELECT * FROM mobile_summary_cache WHERE tenant_id=$1',[self::$tenantId]));
        } finally {
            RestApiEnv::rows($locker,'ROLLBACK');
            pg_close($locker);
        }
        $retry=$this->service()->forRegistry((int)self::$user['id'],self::$subject);
        $this->assertSame('ok',$retry['items'][0]['state']);
        $this->assertFalse($retry['items'][0]['stale']);
    }
}
