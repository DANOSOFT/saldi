<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T3EventTestEnv.php --- ver 5.0.0 --- 2026.10.05
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
require_once __DIR__ . '/../../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../../restapi/services/MobileEventOutbox.php';
require_once __DIR__ . '/../../../restapi/services/MobileEventFanout.php';
require_once __DIR__ . '/../../../restapi/services/PushDispatchWorker.php';

/** Disposable event fixture. All sessions/users and stub processes are owned by this class. */
final class T3EventTestEnv
{
    public static $tenant;
    public static $registry;
    public static int $tenantId;
    public static array $account;
    public static array $registryUser;
    public static string $subject;
    public static string $registrySubject;
    private static array $globals = [];

    public static function setup(): void
    {
        RestApiEnv::bootstrapTenant();
        foreach (['sqhost'=>RestApiEnv::pgHost(),'squser'=>RestApiEnv::pgUser(),'sqpass'=>RestApiEnv::pgPass(),
            'sqdb'=>RestApiEnv::masterDb(),'db_type'=>'postgresql','db_encode'=>'UTF8'] as $key=>$value) {
            self::$globals[$key] = [array_key_exists($key, $GLOBALS), ifset($GLOBALS, $key)];
            $GLOBALS[$key] = $value;
        }
        RestApiEnv::signToken(['type'=>'access','user_id'=>1]);
        RestApiEnv::accessToken();
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        self::$registry = RestApiEnv::connect(RestApiEnv::masterDb());
        self::$tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        mobileTenantUpdates(self::$tenant);
        self::$account = RestApiEnv::rows(self::$tenant, 'SELECT * FROM brugere WHERE brugernavn=$1', [RestApiEnv::USER])[0];
        self::$subject = Subject::build('account', (int)self::$account['id']);
        // Legacy saldikrypt() repeats a hash per user id; keep the shared registry's max small.
        // A concurrent fixture can take our max(id) as its next id, even with isolated tenants.
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $id = random_int(100, 999);
            $users = RestApiEnv::rows(self::$registry,
                'INSERT INTO brugere(id,brugernavn,kode,rettigheder,status) VALUES($1,$2,$3,$4,true)
                    ON CONFLICT (id) DO NOTHING RETURNING *',
                [$id,'event_' . bin2hex(random_bytes(5)),md5(TestCredentials::password('t3_events')),
                    ',,,' . self::$tenantId]);
            if ($users !== []) {
                self::$registryUser = $users[0];
                break;
            }
        }
        if ($users === []) {
            throw new RuntimeException('Event test user allocation failed');
        }
        self::$registrySubject = Subject::build('registry', $id);
    }

    public static function reset(): void
    {
        RestApiEnv::rows(self::$tenant, 'DELETE FROM mobile_events');
        foreach (['push_dispatch','notification_recipients','notifications','notification_preferences','push_tokens','mobile_idempotency'] as $table) {
            RestApiEnv::rows(self::$tenant, 'DELETE FROM ' . $table);
        }
        RestApiEnv::rows(self::$tenant, 'UPDATE pool_files SET reminder_id=NULL WHERE reminder_id IS NOT NULL');
        RestApiEnv::rows(self::$tenant, 'UPDATE bank_lines SET pool_file_id=NULL');
        RestApiEnv::rows(self::$tenant, 'DELETE FROM reminders');
        RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET rettigheder=$1,status=true WHERE id=$2', [str_repeat('1',16),self::$account['id']]);
        RestApiEnv::rows(self::$registry, 'UPDATE brugere SET rettigheder=$1,status=true WHERE id=$2', [',,,' . self::$tenantId,self::$registryUser['id']]);
        RestApiEnv::rows(self::$registry, 'DELETE FROM refresh_sessions WHERE tenant_id=$1', [self::$tenantId]);
        self::session(self::$subject);
        self::session(self::$registrySubject);
    }

    public static function teardown(): void
    {
        RestApiEnv::rows(self::$registry, 'DELETE FROM refresh_sessions WHERE tenant_id=$1 OR subject=$2', [self::$tenantId,self::$registrySubject]);
        RestApiEnv::rows(self::$registry, 'DELETE FROM auth_versions WHERE tenant_id=$1 OR subject=$2', [self::$tenantId,self::$registrySubject]);
        RestApiEnv::rows(self::$registry, 'DELETE FROM brugere WHERE id=$1', [self::$registryUser['id']]);
        pg_close(self::$registry);
        pg_close(self::$tenant);
        RestApiEnv::teardownTenant();
        foreach (self::$globals as $key=>[$exists,$value]) {
            if ($exists) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }

    /** Add a live session snapshot independent of push permission. */
    public static function session(string $subject): void
    {
        $identity = Subject::parse($subject);
        RestApiEnv::rows(self::$registry, "INSERT INTO refresh_sessions(jti_hash,family_id,subject,principal,tenant_id,device_id,expires_at)
            VALUES($1,$2,$3,$4,$5,'event-test',now()+interval '1 day')",
            [hash('sha256',random_bytes(32)),MobileAuth::uuid(),$subject,$identity['principal'],self::$tenantId]);
    }

    /** Issue a real modern token for endpoint tests. */
    public static function token(bool $registry = false): string
    {
        return (new MobileAuth())->create($registry ? self::$registryUser : self::$account,
            $registry ? 'registry' : 'account',self::$tenantId,'preferences-test')['access_token'];
    }

    public static function fanout($connection = null): MobileEventFanout
    {
        return new MobileEventFanout($connection ?? self::$tenant,self::$registry,self::$tenantId);
    }

    public static function emit(string $type = 'invoice_paid', ?string $target = null, array $payload = []): int
    {
        $key = $type . ':' . bin2hex(random_bytes(8));
        MobileEventOutbox::emit(self::$tenant,$type,$key,'openpost',31,
            $payload + ['faktnr'=>'101','amount'=>'612.50','customer_name'=>'Private Customer'],$target);
        return (int)RestApiEnv::rows(self::$tenant,'SELECT id FROM mobile_events WHERE dedupe_key=$1',[$key])[0]['id'];
    }

    /** @return resource A CLI process with captured output owned by the calling test. */
    public static function cron(string $log)
    {
        $process = proc_open([PHP_BINARY,dirname(__DIR__,3) . '/cron/mobile_events.php','--tenant-id=' . self::$tenantId],
            [0=>['pipe','r'],1=>['file',$log,'w'],2=>['file',$log . '.err','w']],$pipes);
        fclose($pipes[0]);
        return $process;
    }
}
