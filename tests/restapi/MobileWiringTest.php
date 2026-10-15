<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileWiringTest.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Assert durable reservation retention when reminder finalization fails.
// 20261005 CDX/LH Keep fixture cleanup compatible with retained T3 provenance and current migration markers.
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/services/ReminderService.php';
require_once __DIR__ . '/../../restapi/core/MobileAuthRevocation.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolFileTransitions.php';
require_once __DIR__ . '/../../restapi/models/attachment/DurableAttachmentModel.php';

final class MobileWiringTest extends TestCase
{
    private static $tenant;
    private static $master;
    private static int $tenantId;
    private static array $users = [];
    private static array $savedGlobals;
    private static string $root;
    private ReminderService $reminders;

    public static function setUpBeforeClass(): void
    {
        if ($reason = RestApiEnv::unavailableReason()) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        self::$savedGlobals = [];
        foreach (['sqhost','squser','sqpass','sqdb','db_type','db_encode','db_skriv_id','db','brugernavn','revisor','connection','_SERVER'] as $key) {
            self::$savedGlobals[$key] = $GLOBALS[$key] ?? null;
        }
        $_SERVER['REQUEST_URI'] = '/cli/mobile-wiring';
        foreach (['sqhost'=>RestApiEnv::pgHost(), 'squser'=>RestApiEnv::pgUser(), 'sqpass'=>RestApiEnv::pgPass(),
            'sqdb'=>RestApiEnv::masterDb(), 'db_type'=>'postgresql', 'db_encode'=>'UTF8', 'db_skriv_id'=>0,
            'db'=>RestApiEnv::testDb(), 'brugernavn'=>RestApiEnv::user(), 'revisor'=>false] as $key=>$value) {
            $GLOBALS[$key] = $value;
        }
        RestApiEnv::signToken(['type'=>'access', 'user_id'=>1]);
        self::$master = RestApiEnv::connect(RestApiEnv::masterDb());
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $GLOBALS['connection'] = self::$tenant;
        mobileTenantUpdates(self::$tenant);
        self::$tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        foreach (['keeper'=>'0010000000000000', 'capture'=>str_repeat('0',16)] as $name=>$rights) {
            self::$users[$name] = RestApiEnv::rows(self::$tenant,
                'INSERT INTO brugere(brugernavn,kode,rettigheder,status,regnskabsaar) VALUES($1,$2,$3,true,1) RETURNING *',
                ['wiring_' . $name, md5(TestCredentials::password('wiring_' . $name)), $rights])[0];
        }
        self::$root = dirname(poolPuljePath(RestApiEnv::testDb()));
        if (!is_dir(self::$root)) {
            mkdir(self::$root, 0777, true);
        }
        chmod(self::$root, 0777);
        self::loadPageHandlers('sager/ansatte.php');
        self::loadPageHandlers('sager/kunder.php');
    }

    /** Load actual named page controllers without rendering or invoking session bootstrap in CLI. @return void */
    private static function loadPageHandlers(string $path): void
    {
        $tokens = token_get_all(file_get_contents(dirname(__DIR__, 2) . '/' . $path));
        for ($i=0; $i<count($tokens); $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }
            $j = $i + 1;
            while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || !str_starts_with($tokens[$j][1], 'sager')) {
                continue;
            }
            $source = '';
            $depth = 0;
            $opened = false;
            do {
                $token = $tokens[$i++];
                $source .= is_array($token) ? $token[1] : $token;
                if ($token === '{') {
                    $opened = true;
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;
                }
            } while (!$opened || $depth > 0);
            $i--;
            eval($source);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$master) {
            foreach (['auth_challenges','refresh_sessions','auth_versions'] as $table) {
                RestApiEnv::rows(self::$master, "DELETE FROM $table WHERE tenant_id=$1", [self::$tenantId]);
            }
            self::removeDirectory(self::$root);
            $temp = dirname(__DIR__, 2) . '/temp/' . RestApiEnv::testDb();
            if (is_dir($temp)) {
                self::removeDirectory($temp);
            }
            pg_close(self::$tenant);
            pg_close(self::$master);
            foreach (self::$savedGlobals as $key=>$value) {
                if ($key !== 'GLOBALS') {
                    $GLOBALS[$key] = $value;
                }
            }
        }
        RestApiEnv::teardownTenant();
    }

    /** @return void Remove only this suite's randomly named tenant storage. */
    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (new FilesystemIterator($path) as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                self::removeDirectory($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($path);
    }

    protected function setUp(): void
    {
        $GLOBALS['connection'] = self::$tenant;
        foreach (self::$users as $name=>&$user) {
            $username = 'wiring_' . $name . '_' . bin2hex(random_bytes(4));
            RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET brugernavn=$1,kode=$2,ansat_id=NULL WHERE id=$3',
                [$username, md5(TestCredentials::password('wiring_' . $name)), $user['id']]);
            $login = RestApiEnv::http('POST', '/restapi/endpoints/v1/auth/login.php', [
                'account_name'=>RestApiEnv::accountOpen(), 'username'=>$username,
                'password'=>TestCredentials::password('wiring_' . $name), 'device_id'=>'wiring-' . $name]);
            $this->assertSame(200, $login['status'], $login['body']);
            $user['pair'] = $login['json']['data'];
            $user['subject'] = JWT::decode($user['pair']['access_token'])['sub'];
        }
        unset($user);
        // Preserve append-only T3 provenance while clearing mutable notification fixtures.
        foreach (['push_dispatch','notification_recipients','notifications'] as $table) {
            RestApiEnv::rows(self::$tenant, "DELETE FROM $table");
        }
        RestApiEnv::rows(self::$tenant, 'UPDATE pool_files SET reminder_id=NULL WHERE reminder_id IS NOT NULL');
        foreach (['reminders','push_tokens'] as $table) {
            RestApiEnv::rows(self::$tenant, "DELETE FROM $table");
        }
        $this->reminders = new ReminderService(self::$tenant, self::$master, self::$tenantId);
    }

    /** @return array{status:int,json:array|null,body:string} */
    private function http(string $method, string $path, ?array $body = null, string $actor = 'capture'): array
    {
        return RestApiEnv::http($method, '/restapi/endpoints/v1/' . $path, $body,
            ['Authorization: Bearer ' . self::$users[$actor]['pair']['access_token']]);
    }

    /** @return string Complete small PDF with a real xref, accepted by the bounded renderer. */
    private function pdf(): string
    {
        $stream = "0 0 1 rg 0 0 100 100 re f\n";
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Resources << >> /Contents 4 0 R >>',
            '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . 'endstream'];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i=>$object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i+1) . " 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 5\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        return $pdf . "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

    /** @return array{id:string,image_base64:string,extracted_data:object} */
    private function body($reminderId): array
    {
        return ['id'=>RequestId::resolve(null), 'image_base64'=>base64_encode($this->pdf()),
            'extracted_data'=>(object)['reminder_id'=>$reminderId]];
    }

    private function resolved(int $reminderId): array
    {
        return RestApiEnv::rows(self::$tenant, 'SELECT r.subject,r.resolved_at FROM notification_recipients r
            JOIN notifications n ON n.id=r.notification_id WHERE n.reminder_id=$1 ORDER BY r.subject', [$reminderId]);
    }

    public function test_target_upload_resolves_atomically_and_retry_preserves_first_resolution(): void
    {
        $reminder = $this->reminders->create(self::$users['keeper']['subject'],
            ['target'=>self::$users['capture']['subject'], 'message'=>'Upload your receipt']);
        $body = $this->body($reminder['id']);
        $res = $this->http('POST', 'attachment/', $body);
        $this->assertSame(201, $res['status'], $res['body']);
        $first = $this->resolved($reminder['id']);
        $this->assertNotNull($first[0]['resolved_at']);
        $retry = $this->http('POST', 'attachment/', $body);
        $this->assertSame(200, $retry['status'], $retry['body']);
        $this->assertSame($first, $this->resolved($reminder['id']));
        $this->assertSame($res['json']['data']['id'], $retry['json']['data']['id']);
    }

    public function test_foreign_missing_and_malformed_reminders_leave_no_pool_row_or_file(): void
    {
        $reminder = $this->reminders->create(self::$users['keeper']['subject'],
            ['target'=>self::$users['keeper']['subject'], 'message'=>'Keeper only']);
        foreach ([$reminder['id'], 2147483647, '1', 1.5, true, null, 0, -1, []] as $id) {
            $body = $this->body($id);
            $res = $this->http('POST', 'attachment/', $body);
            $expected = is_int($id) && $id>0 ? 403 : 422;
            $this->assertSame($expected, $res['status'], $res['body']);
            $this->assertSame($expected===403 ? 'permission_denied' : 'validation_failed', $res['json']['data']['error']);
            $this->assertSame([], RestApiEnv::rows(self::$tenant, 'SELECT id FROM pool_files WHERE client_id=$1', [$body['id']]));
            $this->assertFileDoesNotExist(poolPuljePath(RestApiEnv::testDb()) . '/' . $body['id'] . '.pdf');
        }
        $this->assertNull($this->resolved($reminder['id'])[0]['resolved_at']);
    }

    public function test_broadcast_upload_closes_each_recipient_separately(): void
    {
        $reminder = $this->reminders->create(self::$users['keeper']['subject'], ['target'=>'all', 'message'=>'Everyone']);
        $this->assertSame(201, $this->http('POST','attachment/',$this->body($reminder['id']))['status']);
        foreach ($this->resolved($reminder['id']) as $row) {
            if ($row['subject'] === self::$users['capture']['subject']) {
                $this->assertNotNull($row['resolved_at']);
            } else {
                $this->assertNull($row['resolved_at']);
            }
        }
        $this->assertSame(201, $this->http('POST','attachment/',$this->body($reminder['id']),'keeper')['status']);
        foreach ($this->resolved($reminder['id']) as $row) {
            $this->assertNotNull($row['resolved_at']);
        }
    }

    public function test_failed_finalization_keeps_reservation_and_reminder_unresolved(): void
    {
        $reminder = $this->reminders->create(self::$users['keeper']['subject'],
            ['target'=>self::$users['capture']['subject'], 'message'=>'Failed file']);
        $body = $this->body($reminder['id']);
        $body['image_base64'] = base64_encode('invalid PDF content');
        $res = $this->http('POST','attachment/',$body);
        $this->assertSame(415, $res['status'], $res['body']);
        $reserved = RestApiEnv::rows(self::$tenant, 'SELECT status,uploaded_by FROM pool_files WHERE client_id=$1', [$body['id']]);
        $this->assertCount(1, $reserved);
        $this->assertSame('staged', $reserved[0]['status']);
        $this->assertSame(self::$users['capture']['subject'], $reserved[0]['uploaded_by']);
        $this->assertNull($this->resolved($reminder['id'])[0]['resolved_at']);
    }

    public function test_reference_copy_and_last_unlink_preserve_downloadable_identity(): void
    {
        $res = $this->http('POST', 'attachment/', ['image_base64'=>base64_encode($this->pdf())]);
        $this->assertSame(201, $res['status'], $res['body']);
        $row = $res['json']['data'];
        $lines = [];
        foreach ([1,2] as $ignored) {
            $lines[] = (int)RestApiEnv::rows(self::$tenant, 'INSERT INTO kassekladde(kladde_id,bilag) VALUES(1,42) RETURNING id')[0]['id'];
        }
        $doc = (int)RestApiEnv::rows(self::$tenant, "INSERT INTO documents(filename,filepath,source,source_id) VALUES($1,'/pulje','kassekladde',$2) RETURNING id", [$row['filename'],$lines[0]])[0]['id'];
        PoolFileTransitions::attach(RestApiEnv::testDb(), poolPuljePath(RestApiEnv::testDb()) . '/' . $row['filename'], (int)self::$users['capture']['id']);
        PoolFileTransitions::copyDocument(RestApiEnv::testDb(), $doc, $lines[1], (int)self::$users['capture']['id']);
        PoolFileTransitions::copyDocument(RestApiEnv::testDb(), $doc, $lines[1], (int)self::$users['capture']['id']);
        $docs = RestApiEnv::rows(self::$tenant, 'SELECT id,pool_file_id,source_id FROM documents WHERE pool_file_id=$1 ORDER BY id', [$row['id']]);
        $this->assertCount(2,$docs);
        PoolFileTransitions::unlinkDocument($doc, 'kassekladde', $lines[1]);
        $this->assertCount(2,RestApiEnv::rows(self::$tenant, 'SELECT id FROM documents WHERE pool_file_id=$1', [$row['id']]));
        foreach ($docs as $reference) {
            PoolFileTransitions::unlinkDocument((int)$reference['id'], 'kassekladde', (int)$reference['source_id']);
            $stored = RestApiEnv::rows(self::$tenant, 'SELECT * FROM pool_files WHERE id=$1', [$row['id']])[0];
            $count = count(RestApiEnv::rows(self::$tenant, 'SELECT id FROM documents WHERE pool_file_id=$1', [$row['id']]));
            $this->assertCount($count,json_decode($stored['document_ids'],true));
            $this->assertSame('attached',$stored['status']);
            $download = $this->http('GET','attachment/?id=' . $row['id'] . '&download=true');
            $this->assertSame(200,$download['status'],$download['body']);
            $this->assertSame($this->pdf(),$download['body']);
        }
    }

    public function test_legacy_push_adoption_and_ambiguous_dispatch_quarantine(): void
    {
        $user = self::$users['capture'];
        $token = 'ExpoPushToken[' . bin2hex(random_bytes(16)) . ']';
        $legacy = RestApiEnv::rows(self::$tenant, "INSERT INTO push_tokens(user_id,token,db_name,subject,device_id) VALUES($1,$2,$3,NULL,NULL) RETURNING id", [$user['id'],$token,RestApiEnv::testDb()])[0]['id'];
        RestApiEnv::rows(self::$tenant, "INSERT INTO push_tokens(user_id,token,db_name,subject,device_id) VALUES($1,$2,NULL,NULL,NULL)", [$user['id'],$token]);
        $push = new PushService(self::$tenant);
        $before = $push->create([$user['subject']],$user['subject'],'reminder','Title','Text');
        $this->assertSame(0,$before['dispatch_count']);
        for ($i=0; $i<2; $i++) {
            $res = $this->http('POST','notifications/register.php',['token'=>$token,'device_id'=>'wiring-capture']);
            $this->assertSame(200,$res['status'],$res['body']);
        }
        $canonical = RestApiEnv::rows(self::$tenant,'SELECT id FROM push_tokens WHERE subject=$1',[$user['subject']]);
        $this->assertSame([['id'=>$legacy]],$canonical);
        $after = $push->create([$user['subject']],$user['subject'],'reminder','Title','Text');
        $this->assertSame(1,$after['dispatch_count']);
        $recipient = RestApiEnv::rows(self::$tenant,'SELECT id FROM notification_recipients WHERE notification_id=$1',[$before['notification_id']])[0]['id'];
        $ambiguous = RestApiEnv::rows(self::$tenant,'SELECT id FROM push_tokens WHERE subject IS NULL')[0]['id'];
        RestApiEnv::rows(self::$tenant,'INSERT INTO push_dispatch(recipient_id,device_id,push_token_id) VALUES($1,$2,$3)',[$recipient,'ambiguous',$ambiguous]);
        $this->assertSame(1,$push->quarantineAmbiguousDispatches(Subject::installation()));
        $this->assertSame(0,$push->quarantineAmbiguousDispatches(Subject::installation()));
        $this->assertSame('rejected',RestApiEnv::rows(self::$tenant,"SELECT state FROM push_dispatch WHERE device_id='ambiguous'")[0]['state']);
        $this->assertSame('stored',RestApiEnv::rows(self::$tenant,"SELECT state FROM push_dispatch WHERE device_id='wiring-capture'")[0]['state']);
    }

    /** @return array{status:int,json:array|null,body:string} Authenticated legacy web request with a disposable online row. */
    private function web(string $query, string $actor = 'keeper'): array
    {
        $session = bin2hex(random_bytes(16));
        $user = RestApiEnv::rows(self::$tenant, 'SELECT brugernavn,rettigheder FROM brugere WHERE id=$1', [self::$users[$actor]['id']])[0];
        $year = RestApiEnv::rows(self::$tenant, "SELECT kodenr FROM grupper WHERE art='RA' ORDER BY kodenr DESC LIMIT 1")[0]['kodenr'];
        RestApiEnv::rows(self::$master, 'INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,revisor,logtime,regnskabsaar,language_id) VALUES($1,$2,$3,$4,$5,false,$6,$7,1)',
            [$session,$user['brugernavn'],RestApiEnv::testDb(),RestApiEnv::pgUser(),$user['rettigheder'],(string)time(),$year]);
        try {
            return RestApiEnv::http('GET', '/includes/documents.php?' . $query, null, ['Cookie: PHPSESSID=' . $session]);
        } finally {
            RestApiEnv::rows(self::$master, 'DELETE FROM online WHERE session_id=$1', [$session]);
        }
    }

    public function test_pool_toolbar_links_reminders_only_for_current_bookkeeping_right(): void
    {
        $query = 'openPool=1&source=kassekladde&sourceId=0&kladde_id=1';
        $keeper = $this->web($query);
        $this->assertSame(200, $keeper['status'], $keeper['body']);
        $this->assertStringContainsString("href='../finans/bilag_paamindelse.php'", $keeper['body']);
        $capture = $this->web($query, 'capture');
        $this->assertSame(200, $capture['status'], $capture['body']);
        $this->assertStringNotContainsString("href='../finans/bilag_paamindelse.php'", $capture['body']);
    }

    public function test_document_page_copy_and_unlink_redirect_and_preserve_rest_download(): void
    {
        $res = $this->http('POST','attachment/',['image_base64'=>base64_encode($this->pdf())]);
        $this->assertSame(201,$res['status'],$res['body']);
        $row = $res['json']['data'];
        $lines = [];
        foreach ([1,2] as $ignored) {
            $lines[] = (int)RestApiEnv::rows(self::$tenant,'INSERT INTO kassekladde(kladde_id,bilag) VALUES(1,43) RETURNING id')[0]['id'];
        }
        $doc = (int)RestApiEnv::rows(self::$tenant, "INSERT INTO documents(filename,filepath,source,source_id) VALUES($1,'/pulje','kassekladde',$2) RETURNING id", [$row['filename'],$lines[0]])[0]['id'];
        PoolFileTransitions::attach(RestApiEnv::testDb(),poolPuljePath(RestApiEnv::testDb()) . '/' . $row['filename'],(int)self::$users['capture']['id']);
        $copy = $this->web(http_build_query(['doLink'=>1,'linkDocId'=>$doc,'source'=>'kassekladde','sourceId'=>$lines[1],'kladde_id'=>1]));
        $this->assertSame(303,$copy['status'],$copy['body']);
        $docs = RestApiEnv::rows(self::$tenant,'SELECT id,source_id FROM documents WHERE pool_file_id=$1 ORDER BY id',[$row['id']]);
        $this->assertCount(2,$docs);
        foreach ($docs as $reference) {
            $unlink = $this->web(http_build_query(['unlinkDoc'=>$reference['id'],'source'=>'kassekladde','sourceId'=>$reference['source_id'],'kladde_id'=>1]));
            $this->assertSame(303,$unlink['status'],$unlink['body']);
        }
        $stored = RestApiEnv::rows(self::$tenant,'SELECT document_ids,status FROM pool_files WHERE id=$1',[$row['id']])[0];
        $this->assertSame('[]',$stored['document_ids']);
        $this->assertSame('attached',$stored['status']);
        $download = $this->http('GET','attachment/?download=true&id=' . $row['id']);
        $this->assertSame(200,$download['status'],$download['body']);
        $this->assertSame($this->pdf(),$download['body']);
    }

    /** @return void Verify both access and refresh are refused after a production page handler runs. */
    private function assertRevoked(string $actor = 'capture'): void
    {
        $access = $this->http('GET','auth/me.php',null,$actor);
        $refresh = RestApiEnv::http('POST','/restapi/endpoints/v1/auth/refresh.php',[
            'refresh_token'=>self::$users[$actor]['pair']['refresh_token'],'device_id'=>'wiring-' . $actor]);
        foreach ([$access,$refresh] as $res) {
            $this->assertSame(401,$res['status'],$res['body']);
            $this->assertSame('session_revoked',$res['json']['data']['error']);
        }
    }

    public function test_employee_password_handler_revokes_only_affected_login(): void
    {
        $newPassword = TestCredentials::password('wiring_changed');
        sagerSaveEmployeePassword((int)self::$users['capture']['id'],md5($newPassword));
        $this->assertSame(md5($newPassword),RestApiEnv::rows(self::$tenant,'SELECT kode FROM brugere WHERE id=$1',[self::$users['capture']['id']])[0]['kode']);
        $this->assertRevoked();
        $this->assertSame(200,$this->http('GET','auth/me.php',null,'keeper')['status']);
    }

    /** @return int Seed a linked employee using only disposable tenant data. */
    private function employee(): int
    {
        $id = (int)RestApiEnv::rows(self::$tenant,"INSERT INTO ansatte(navn,konto_id) VALUES('Wiring contact',42) RETURNING id")[0]['id'];
        RestApiEnv::rows(self::$tenant,'UPDATE brugere SET ansat_id=$1 WHERE id=$2',[$id,self::$users['capture']['id']]);
        return $id;
    }

    public function test_employee_group_assignment_revokes_member(): void
    {
        $employee = $this->employee();
        sagerSaveEmployeeGroup($employee,73);
        $this->assertSame(73, (int)RestApiEnv::rows(self::$tenant,'SELECT gruppe FROM ansatte WHERE id=$1',[$employee])[0]['gruppe']);
        $this->assertRevoked();
    }

    public function test_employee_group_permissions_revoke_all_member_logins(): void
    {
        $employee = $this->employee();
        $group = (int)RestApiEnv::rows(self::$tenant,"INSERT INTO grupper(art,beskrivelse,box2) VALUES('brgrp','Wiring','11111111') RETURNING id")[0]['id'];
        RestApiEnv::rows(self::$tenant,'UPDATE ansatte SET gruppe=$1 WHERE id=$2',[$group,$employee]);
        sagerSaveEmployeeGroupRights($group,'Restricted','00000000');
        $this->assertSame('00000000',RestApiEnv::rows(self::$tenant,'SELECT box2 FROM grupper WHERE id=$1',[$group])[0]['box2']);
        $this->assertRevoked();
    }

    public function test_employee_closure_revokes_before_redirecting_legacy_saver(): void
    {
        $employee = $this->employee();
        sagerRevokeClosingEmployee($employee,['konto_id'=>42,'lukket'=>'on']);
        $this->assertRevoked();
        $this->assertSame(200,$this->http('GET','auth/me.php',null,'keeper')['status']);
    }

    public function test_customer_login_handler_resolves_contact_and_revokes_password_change(): void
    {
        $employee = $this->employee();
        $password = TestCredentials::password('wiring_customer_changed');
        $this->assertFalse(sagerSaveCustomerLogin($employee,999,'wrong_customer',$password));
        $this->assertSame(200,$this->http('GET','auth/me.php')['status']);
        $this->assertTrue(sagerSaveCustomerLogin($employee,42,'renamed_contact',$password));
        $this->assertSame(md5($password),RestApiEnv::rows(self::$tenant,'SELECT kode FROM brugere WHERE id=$1',[self::$users['capture']['id']])[0]['kode']);
        $this->assertRevoked();
        $this->assertSame(200,$this->http('GET','auth/me.php',null,'keeper')['status']);
    }

    public function test_customer_group_handler_revokes_contact_permissions(): void
    {
        $employee = $this->employee();
        sagerSaveCustomerGroup($employee,42,74);
        $this->assertSame(74, (int)RestApiEnv::rows(self::$tenant,'SELECT gruppe FROM ansatte WHERE id=$1',[$employee])[0]['gruppe']);
        $this->assertRevoked();
    }
}
