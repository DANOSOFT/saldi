<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/T3B2cTestCase.php --- ver 5.0.0 --- 2026.10.05
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

require_once __DIR__ . '/T3B2aTestCase.php';
require_once __DIR__ . '/../../../restapi/services/AttachmentReviewService.php';

/** Disposable actors and fixtures for reviewer endpoints, independent of approval. */
abstract class T3B2cTestCase extends T3B2aTestCase
{
    protected static array $registryHeaders;
    protected static array $registryUser;
    protected static $registry;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$registry = RestApiEnv::connect(RestApiEnv::masterDb());
        $tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        self::$registryUser = RestApiEnv::rows(self::$registry,
            'INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES ($1,$2,$3,true) RETURNING *',
            ['b2c_registry_' . bin2hex(random_bytes(4)), md5(TestCredentials::password('b2c_registry')), ',,,' . $tenantId])[0];
        $login = RestApiEnv::http('POST', '/restapi/endpoints/v1/auth/registry-login.php',
            ['username'=>self::$registryUser['brugernavn'], 'password'=>TestCredentials::password('b2c_registry'), 'device_id'=>'b2c-registry']);
        self::assertSame(200, $login['status'], $login['body']);
        $exchange = RestApiEnv::http('POST', '/restapi/endpoints/v1/auth/tenant-token.php', ['tenant_id'=>$tenantId],
            ['Authorization: Bearer ' . $login['json']['data']['access_token']]);
        self::assertSame(200, $exchange['status'], $exchange['body']);
        self::$registryHeaders = ['Authorization: Bearer ' . $exchange['json']['data']['access_token']];
        $GLOBALS['connection'] = self::$conn;
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$registry)) {
            $subject = Subject::build('registry', (int)self::$registryUser['id']);
            RestApiEnv::rows(self::$registry, 'DELETE FROM refresh_sessions WHERE subject=$1', [$subject]);
            RestApiEnv::rows(self::$registry, 'DELETE FROM auth_versions WHERE subject=$1', [$subject]);
            RestApiEnv::rows(self::$registry, 'DELETE FROM brugere WHERE id=$1', [self::$registryUser['id']]);
            pg_close(self::$registry);
        }
        parent::tearDownAfterClass();
    }

    /** @return AttachmentReviewService Selected tenant and real authenticated reviewer. */
    protected function reviewService(): AttachmentReviewService
    {
        $id = Subject::parse(self::$reviewer)['user_id'];
        return new AttachmentReviewService(self::$conn, RestApiEnv::testDb(), self::$reviewer, 't3_reviewer', $id,
            RestApiEnv::regnskabId(RestApiEnv::accountOpen()), RequestId::resolve(null));
    }

    /** @return array Disposable web or app row, with normalized metadata used by queue totals. */
    protected function reviewFixture(bool $app = true, string $status = 'ubehandlet', string $state = 'pending'): array
    {
        $row = $this->fixture($status, $state);
        return RestApiEnv::rows(self::$conn, 'UPDATE pool_files SET client_id=$1,uploaded_by=$2,norm_amount=1234.56,
            amount=$3,description=$4 WHERE id=$5 RETURNING *',
            [$app ? RequestId::resolve(null) : null, $app ? self::$owner : null, '1.234,56', "Supplier's invoice", $row['id']])[0];
    }

    /** @return array Local HTTP rejection, using the version that the reviewer saw. */
    protected function rejectRequest(array $row, array $fields = [], ?array $headers = null): array
    {
        return RestApiEnv::http('POST', '/restapi/endpoints/v1/attachment/reject.php',
            $fields + ['client_id'=>RequestId::resolve(null), 'id'=>(int)$row['id'], 'reason_code'=>'other', 'message'=>"Please fix the supplier's amount."],
            array_merge($headers ?? self::$reviewerHeaders, ['If-Match: "' . pool_review_version($row) . '"']));
    }
}
