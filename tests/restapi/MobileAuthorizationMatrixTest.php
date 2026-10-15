<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileAuthorizationMatrixTest.php --- ver 5.0.0 --- 2026.10.08
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
// 20261001 CDX/LH Deny configuration writes to inventory-only and read-only settings users.
// 20261001 CDX/LH Register Tier 2 catalogue read routes and expect their enabled capability.
// 20261001 CDX/LH Register document PDF reads and delivery writes in the authorization matrix.
// 20261002 CL/LH Load per-slice endpoint entries from authz-matrix.d/ so concurrent slices never edit this file.
// 20261002 CL/LH Assert the Tier 1 capabilities as a subset; installed capability fragments may add keys.
// 20261005 CL/LH Treat endpoints declared with the registry module as registry-only.
// 20261006 CL/LH Expect permission_denied on employee-bound T4 declarations for personas without an employee link.
// 20261008 CL/LH Cover additive mobile Assist rights and family migration.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/core/MobileAuth.php';
require_once __DIR__ . '/../../restapi/core/Rights.php';

final class MobileAuthorizationMatrixTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../restapi/endpoints/v1/';
    private const V1 = '/restapi/endpoints/v1/';
    private static $tenant;
    private static $master;
    private static ?array $registryUser = null;
    private static array $users = [];
    private static array $pairs = [];
    private static int $tenantId;
    private static ?string $originalUri;

    /** Public discovery scripts and the auth dispatcher are not BaseEndpoint resources. */
    private const ROUTERS = ['index.php', 'inventory/index.php', 'auth/index.php'];

    /** @return array<string,array{module:string,action:string,methods:list<string>}> */
    private static function declarations(): array
    {
        $modules = [
            'accountingYear/index.php' => 'admin', 'accounts/index.php' => 'kontoplan',
            'creditor/creditors/index.php' => 'kreditorkonti', 'creditor/orderlines/index.php' => 'kreditorordre',
            'creditor/orders/index.php' => 'kreditorordre', 'currencies/index.php' => 'kassekladde',
            'debitor/customers/index.php' => 'debitorkonti', 'debitor/groups/index.php' => 'debitorkonti',
            'debitor/external-paid-invoices/index.php' => 'debitorordre', 'debitor/invoices/index.php' => 'debitorordre',
            'debitor/orderlines/index.php' => 'debitorordre', 'debitor/orders/index.php' => 'debitorordre',
            'inventory/status/index.php' => 'varer', 'inventory/warehouses/index.php' => 'varer',
            'labels/index.php' => 'varer', 'products/groups/index.php' => 'varer',
            'products/index.php' => 'varer', 'vat/index.php' => 'admin',
        ];
        $matrix = [];
        foreach ($modules as $file => $module) {
            $matrix[$file] = ['module' => $module, 'action' => 'method', 'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']];
        }
        foreach (['products/groups/index.php', 'inventory/warehouses/index.php'] as $file) {
            $matrix[$file]['action'] = 'configuration';
        }
        foreach (['attachment/index.php', 'attachment/thumbnail.php', 'attachment/page.php',
            'accounts/posting-chart.php', 'vat-codes/index.php', 'dashboard/overview.php',
            'notifications/index.php', 'notifications/read.php', 'notifications/register.php', 'auth/ocr-token.php'] as $file) {
            $methods = match ($file) {
                'attachment/index.php' => ['GET', 'POST', 'DELETE'],
                'notifications/register.php' => ['POST', 'DELETE'],
                'notifications/read.php', 'auth/ocr-token.php' => ['POST'],
                default => ['GET'],
            };
            $matrix[$file] = ['module' => 'bilag', 'action' => 'own', 'methods' => $methods];
        }
        foreach (['auth/login.php', 'auth/refresh.php', 'auth/registry-login.php', 'auth/ocr-keys.php'] as $file) {
            $matrix[$file] = ['module' => 'auth', 'action' => 'public', 'methods' => [$file === 'auth/ocr-keys.php' ? 'GET' : 'POST']];
        }
        foreach (['auth/me.php', 'auth/logout.php', 'auth/tenants.php', 'auth/tenant-token.php', 'user/tenants.php'] as $file) {
            $matrix[$file] = ['module' => 'auth', 'action' => 'self', 'methods' => [in_array($file, ['auth/logout.php', 'auth/tenant-token.php'], true) ? 'POST' : 'GET']];
        }
        $matrix['dashboard/stats.php'] = ['module' => 'finans', 'action' => 'read', 'methods' => ['GET']];
        $matrix['app-users.php'] = ['module' => 'bilag', 'action' => 'read', 'methods' => ['GET']];
        $matrix['reminders/index.php'] = ['module' => 'bilag', 'action' => 'method', 'methods' => ['GET', 'POST']];
        foreach (['products/search.php', 'products/barcode.php', 'products/changes.php', 'products/detail.php'] as $file) {
            $matrix[$file] = ['module' => 'varer', 'action' => 'read', 'methods' => ['GET']];
        }
        $matrix['debitor/sales/products.php'] = ['module' => 'debitorordre', 'action' => 'read', 'methods' => ['GET']];
        $matrix['debitor/documents/pdf.php'] = ['module' => 'debitorordre', 'action' => 'read', 'methods' => ['GET']];
        $matrix['debitor/sales/deliver.php'] = ['module' => 'debitorordre', 'action' => 'write', 'methods' => ['POST']];
        // Later slices register their endpoints in one file each, so concurrent slices never edit this list.
        foreach (glob(__DIR__ . '/authz-matrix.d/*.php') ?: [] as $entryFile) {
            foreach (require $entryFile as $file => $spec) {
                $matrix[$file] = $spec;
            }
        }
        return $matrix;
    }

    /** @return list<string> Every PHP entry point, including the finite dispatcher exceptions. */
    private static function files(): array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = substr($file->getPathname(), strlen(self::ROOT));
            }
        }
        sort($files);
        return $files;
    }

    /** @return iterable<string,array{string,?array,string}> */
    public static function endpointCases(): iterable
    {
        $matrix = self::declarations();
        foreach (self::files() as $file) {
            $expected = ifset($matrix, $file);
            foreach ($expected === null ? ['GET'] : $expected['methods'] as $method) {
                yield $file . ' ' . $method => [$file, $expected, $method];
            }
        }
    }

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        self::$originalUri = ifset($_SERVER, 'REQUEST_URI');
        $_SERVER['REQUEST_URI'] = self::V1 . 'auth/me.php';
        RestApiEnv::bootstrapTenant();
        global $sqhost, $squser, $sqpass, $sqdb, $db_type, $db_encode, $connection;
        include __DIR__ . '/../../includes/connect.php';
        require_once __DIR__ . '/../../restapi/core/JwtSecretProvisioning.php';
        JWT::setClaimEnricher(null);
        self::$master = RestApiEnv::connect(RestApiEnv::masterDb());
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        self::$tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        $max = max((int)RestApiEnv::rows(self::$master, 'SELECT COALESCE(max(id),0) AS id FROM brugere')[0]['id'],
            (int)RestApiEnv::rows(self::$tenant, 'SELECT COALESCE(max(id),0) AS id FROM brugere')[0]['id']) + 1000;
        foreach (['capture' => str_repeat('0', 16), 'full' => str_repeat('1', 16)] as $persona => $digits) {
            self::$users[$persona] = RestApiEnv::rows(self::$tenant,
                'INSERT INTO brugere(id,brugernavn,kode,rettigheder,status) VALUES ($1,$2,$3,$4,true) RETURNING *',
                [$max++, 'b9a_' . $persona . '_' . bin2hex(random_bytes(4)), md5(TestCredentials::password('authorizationmatrix')), $digits])[0];
        }
        self::$registryUser = RestApiEnv::rows(self::$master,
            'INSERT INTO brugere(id,brugernavn,kode,rettigheder,status) VALUES ($1,$2,$3,$4,true) RETURNING *',
            [self::$users['capture']['id'], 'b9a_registry_' . bin2hex(random_bytes(4)),
                md5(TestCredentials::password('authorizationmatrixregistry')), ',,,' . self::$tenantId])[0];
        self::$users['registry'] = self::$registryUser;
        mobileTenantUpdates(self::$tenant);
        JWT::setSecret(_jwtLoadSecret());
        foreach (self::$users as $persona => $user) {
            $auth = new MobileAuth();
            self::$pairs[$persona] = $auth->create($user, $persona === 'registry' ? 'registry' : 'account', self::$tenantId, 'b9a-' . $persona);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$master) {
            foreach (self::$users as $persona => $user) {
                $subject = Subject::build($persona === 'registry' ? 'registry' : 'account', (int)$user['id']);
                foreach (['refresh_sessions', 'auth_versions', 'auth_challenges'] as $table) {
                    RestApiEnv::rows(self::$master, "DELETE FROM $table WHERE subject=$1", [$subject]);
                }
            }
            if (self::$registryUser !== null) {
                RestApiEnv::rows(self::$master, 'DELETE FROM brugere WHERE id=$1', [self::$registryUser['id']]);
            }
            pg_close(self::$master);
            pg_close(self::$tenant);
        }
        RestApiEnv::teardownTenant();
        if (self::$originalUri === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = self::$originalUri;
        }
    }

    /** @return Closure Declaration extracted with PHP tokens, independent of handler side effects. */
    private function declaration(string $file): Closure
    {
        $tokens = token_get_all(file_get_contents(self::ROOT . $file));
        $found = false;
        $depth = 0;
        $body = '';
        foreach ($tokens as $token) {
            if (!$found) {
                if (is_array($token) && $token[0] === T_STRING && $token[1] === 'requiredRight') {
                    $found = true;
                }
                continue;
            }
            if ($token === '{') {
                $depth++;
            }
            if ($depth > 0) {
                $body .= is_array($token) ? $token[1] : $token;
            }
            if ($token === '}' && --$depth === 0) {
                // Evaluate only the trusted declaration method, never the endpoint's include or handler.
                return eval('return static function () ' . $body . ';');
            }
        }
        $this->fail($file . ' must declare requiredRight() explicitly');
    }

    #[DataProvider('endpointCases')]
    public function test_every_endpoint_declaration_and_http_authorization(string $file, ?array $expected, string $method): void
    {
        if ($expected === null) {
            $this->assertContains($file, self::ROUTERS, 'A new endpoint must join the authorization matrix and declare requiredRight()');
            $this->assertDoesNotMatchRegularExpression('/class\s+\w+\s+extends\s+/', file_get_contents(self::ROOT . $file));
            foreach (array_keys(self::$pairs) as $persona) {
                $r = $this->request($persona, $file, $method);
                $this->assertNotSame(403, $r['status'], $file . ': ' . $r['body']);
            }
            return;
        }
        $original = ifset($_SERVER, 'REQUEST_METHOD');
        $_SERVER['REQUEST_METHOD'] = $method;
        try {
            $action = $expected['action'] === 'method' ? ($method === 'GET' ? 'read' : 'write') : $expected['action'];
            $module = $expected['module'];
            if ($expected['action'] === 'configuration') {
                $module = $method === 'GET' ? 'varer' : 'admin';
                $action = $method === 'GET' ? 'read' : 'write';
            }
            $this->assertSame(['module' => $module, 'action' => $action], ($this->declaration($file))());
        } finally {
            if ($original === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $original;
            }
        }
        foreach (['capture', 'full', 'registry'] as $persona) {
            $r = $this->request($persona, $file, $method);
            // Endpoints declared with the registry module answer only registry sessions.
            $registryOnly = in_array($file, ['auth/tenants.php', 'auth/tenant-token.php', 'user/tenants.php'], true)
                || $expected['module'] === 'registry';
            // T4 employee-bound declarations need brugere.ansat_id; none of these personas has one (allow cases live in MobileT4RightsMatrixTest).
            $employeeBound = in_array($expected['module'], ['employee', 'sager_loen', 'sager_godkend'], true);
            $denied = ($persona === 'capture' && $expected['module'] !== 'auth' && $expected['action'] !== 'own')
                || ($registryOnly && $persona !== 'registry') || $employeeBound;
            $context = $persona . ' ' . $method . ' ' . $file . ': ' . $r['body'];
            if ($denied) {
                $this->assertSame(403, $r['status'], $context);
                $this->assertSame(str_starts_with($file, 'assistant/') ? 'FORBIDDEN' : 'permission_denied', ifset($r, ['json', 'data', 'error']), $context);
            } else {
                $this->assertNotSame(403, $r['status'], $context);
                $this->assertNotSame(401, $r['status'], $context);
            }
            $this->assertIsArray($r['json'], $context);
            if (!in_array($file, self::ROUTERS, true)) {
                $this->assertArrayHasKey('success', $r['json'], $context);
                $this->assertArrayHasKey('message', $r['json'], $context);
                $this->assertArrayHasKey('data', $r['json'], $context);
            }
            if ($file === 'dashboard/overview.php' && $persona === 'capture') {
                $this->assertSame(200, $r['status'], $context);
                $this->assertSame(['as_of', 'money_cards_available', 'queue'], array_keys($r['json']['data']));
                $this->assertFalse($r['json']['data']['money_cards_available']);
                $this->assertSame('own', $r['json']['data']['queue']['scope']);
            }
        }
    }

    /** @return array{status:int,json:?array,body:string} Missing records/fields avoid business mutations. */
    private function request(string $persona, string $file, string $method): array
    {
        $token = self::$pairs[$persona]['access_token'];
        if ($file === 'auth/logout.php') {
            $pair = (new MobileAuth())->create(self::$users[$persona], $persona === 'registry' ? 'registry' : 'account', self::$tenantId, 'b9a-logout-' . $persona);
            $token = $pair['access_token'];
        }
        $query = in_array($file, ['accounts/posting-chart.php', 'vat-codes/index.php', 'dashboard/overview.php'], true)
            ? '' : '?id=2147483647';
        $data = $method === 'GET' || in_array($file, ['auth/logout.php', 'auth/ocr-token.php'], true) ? null : ['matrix_probe' => true];
        if ($file === 'auth/tenant-token.php') {
            $data = ['tenant_id' => self::$tenantId];
        }
        return RestApiEnv::http($method, self::V1 . $file . $query, $data, ['Authorization: Bearer ' . $token]);
    }

    public function test_read_only_digits_cannot_write_and_module_grants_are_precise(): void
    {
        $positions = ['kontoplan' => 0, 'admin' => 1, 'kassekladde' => 2, 'debitorordre' => 5,
            'debitorkonti' => 6, 'kreditorordre' => 7, 'kreditorkonti' => 8, 'varer' => 9];
        try {
            foreach (self::declarations() as $file => $expected) {
                if ($expected['action'] !== 'method' || !isset($positions[$expected['module']])) {
                    continue;
                }
                $digits = str_repeat('0', 16);
                $digits[$positions[$expected['module']]] = '2';
                RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [$digits, self::$users['capture']['id']]);
                $read = $this->request('capture', $file, 'GET');
                $this->assertNotSame(403, $read['status'], $file . ': ' . $read['body']);
                foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                    $write = $this->request('capture', $file, $method);
                    $this->assertSame(403, $write['status'], $method . ' ' . $file . ': ' . $write['body']);
                    $this->assertSame('permission_denied', ifset($write, ['json', 'data', 'error']));
                }
                $digits[$positions[$expected['module']]] = '0';
                $digits[($positions[$expected['module']] + 1) % 16] = '1';
                RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [$digits, self::$users['capture']['id']]);
                $wrongModule = $this->request('capture', $file, 'GET');
                $this->assertSame(403, $wrongModule['status'], $file . ': ' . $wrongModule['body']);
            }
        } finally {
            RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [str_repeat('0', 16), self::$users['capture']['id']]);
        }
    }

    public function test_inventory_only_users_can_read_but_cannot_mutate_configuration(): void
    {
        $digits = str_repeat('0', 16);
        $digits[9] = '1';
        try {
            RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [$digits, self::$users['capture']['id']]);
            foreach (['products/groups/index.php', 'inventory/warehouses/index.php'] as $file) {
                $read = $this->request('capture', $file, 'GET');
                $this->assertNotSame(403, $read['status'], $read['body']);
                foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                    $write = $this->request('capture', $file, $method);
                    $this->assertSame(403, $write['status'], $write['body']);
                    $this->assertSame('permission_denied', $write['json']['data']['error']);
                }
            }
            $digits[1] = '2';
            RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [$digits, self::$users['capture']['id']]);
            foreach (['products/groups/index.php', 'inventory/warehouses/index.php'] as $file) {
                $this->assertSame(403, $this->request('capture', $file, 'POST')['status']);
            }
            $digits[1] = '1';
            RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [$digits, self::$users['capture']['id']]);
            foreach (['products/groups/index.php', 'inventory/warehouses/index.php'] as $file) {
                $write = $this->request('capture', $file, 'POST');
                $this->assertNotSame(403, $write['status'], $write['body']);
                $this->assertNotSame(401, $write['status'], $write['body']);
            }
        } finally {
            RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [str_repeat('0', 16), self::$users['capture']['id']]);
        }
    }

    public function test_me_advertises_tested_capabilities_and_separates_equal_principal_ids(): void
    {
        foreach (['capture', 'full', 'registry'] as $persona) {
            $r = $this->request($persona, 'auth/me.php', 'GET');
            $this->assertSame(200, $r['status'], $r['body']);
            $principal = $persona === 'registry' ? 'registry' : 'account';
            $this->assertSame(Subject::build($principal, (int)self::$users[$persona]['id']), $r['json']['data']['subject']);
            $tier1 = ['api_version' => 1, 'attachments' => 'v2', 'overview' => 'v2',
                'multipart' => true, 'ocr_token' => true, 'push' => true, 'reminders' => true];
            $this->assertSame($tier1, array_intersect_key($r['json']['data']['capabilities'], $tier1));
            if ($persona === 'capture') {
                $this->assertSame(array_fill_keys(['finans', 'debitor', 'lager', 'sager', 'bilag', 'admin', 'assistant'], false), $r['json']['data']['rights']);
            }
            if ($persona === 'registry') {
                foreach (['finans', 'debitor', 'lager', 'bilag', 'admin', 'sager'] as $module) {
                    $this->assertTrue($r['json']['data']['rights'][$module]);
                }
            }
        }
    }
}
