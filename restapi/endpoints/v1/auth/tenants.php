<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/auth/tenants.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Resolve company labels from tenant addresses with registry-name fallbacks.
// 20261001 CDX/LH Close dedicated tenant metadata connections before continuing the registry listing.

require_once __DIR__ . '/../../../core/MobileAuthEndpoint.php';
require_once __DIR__ . '/../../../core/MobileTenantMetadata.php';

/** Registry membership and metadata shared by login, listing and the deprecated alias. */
final class MobileRegistryDirectory
{
    /**
     * Open memberships only. CVR is empty when the company address is unavailable.
     * @return list<array{id:int,name:string,cvr:string,db:string,company_name:string,email:string,closed:bool}>
     */
    public static function tenants(MobileAuth $auth, array $user): array
    {
        global $sqhost, $squser, $sqpass, $sqdb, $db_encode;
        $store = $auth->store();
        $rights = Rights::registry((string)$user['rettigheder']);
        if (!$rights['admin'] && $rights['tenant_ids'] === []) {
            return [];
        }
        $scope = $rights['admin'] ? '' : ' AND id IN (' . implode(',', array_map('intval', $rights['tenant_ids'])) . ')';
        $registry = $store->literal($sqdb);
        $rows = $store->rows("SELECT * FROM regnskab WHERE db<>$registry AND (lukket IS NULL OR lukket<>'on') AND (lukkes IS NULL OR lukkes='2099-12-31' OR lukkes>CURRENT_DATE)$scope ORDER BY regnskab,id");
        $tenants = [];
        $metadata = [];
        foreach ($rows as $row) {
            $db = (string)$row['db'];
            if (!isset($metadata[$db])) {
                $cvr = '';
                $email = '';
                $companyName = '';
                $name = $store->literal($db);
                // Some registry rows outlive a removed database; listing must still work.
                $available = $store->rows("SELECT datname FROM pg_database WHERE datname=$name AND has_database_privilege(datname,'CONNECT')");
                if ($available) {
                    $quote = static function (string $value): string {
                        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
                    };
                    $dsn = 'host=' . $quote($sqhost) . ' dbname=' . $quote($db) . ' user=' . $quote($squser) . ' password=' . $quote($sqpass);
                    $link = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
                    // A tenant may disappear between the catalog check and connection attempt.
                    // Keep its registry label when metadata is temporarily unavailable.
                    if ($link) {
                        try {
                            pg_set_client_encoding($link, $db_encode === 'UTF8' ? 'UTF8' : 'LATIN9');
                            $tenantStore = new MobileSchema($link);
                            $companyName = MobileTenantMetadata::companyName($link, '');
                            $columns = $tenantStore->rows("SELECT column_name FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='adresser' AND column_name IN ('art','cvrnr','email')");
                            $columns = array_column($columns, 'column_name');
                            if (in_array('art', $columns, true) && in_array('cvrnr', $columns, true)) {
                                $emailColumn = in_array('email', $columns, true) ? 'email' : "NULL AS email";
                                $address = ifset($tenantStore->rows("SELECT cvrnr,$emailColumn FROM adresser WHERE art='S' ORDER BY id LIMIT 1"), 0, []);
                                $cvr = trim((string)ifset($address, 'cvrnr', ''));
                                $email = (string)ifset($address, 'email', '');
                            }
                        } finally {
                            pg_close($link);
                        }
                    }
                }
                $metadata[$db] = ['cvr' => $cvr, 'email' => $email, 'company_name' => $companyName];
            }
            $tenants[] = ['id' => (int)$row['id'], 'name' => $row['regnskab'], 'cvr' => $metadata[$db]['cvr'], 'db' => $db,
                'company_name' => $metadata[$db]['company_name'] !== '' ? $metadata[$db]['company_name'] : $row['regnskab'],
                'email' => (string)(ifset($row, 'email', '') ?: $metadata[$db]['email']), 'closed' => false];
        }
        return $tenants;
    }

    /** @return list<array{id:int,name:string,db:string,cvr:string,company_name:string}> */
    public static function publicTenants(array $tenants): array
    {
        return array_map(static function (array $tenant): array {
            return ['id' => $tenant['id'], 'name' => $tenant['name'], 'db' => $tenant['db'], 'cvr' => $tenant['cvr'], 'company_name' => $tenant['company_name']];
        }, $tenants);
    }
}

class AuthTenantsEndpoint extends MobileAuthEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'auth', 'action' => 'self'];
    }

    protected function handleGet($id = null)
    {
        $this->authOperation(function (): void {
            if ($this->principal !== 'registry') {
                throw new MobileAuthError('permission_denied', 403);
            }
            $auth = new MobileAuth();
            $context = $auth->context('registry', (int)$this->userId, null);
            $tenants = MobileRegistryDirectory::tenants($auth, $context['user']);
            $this->sendResponse(true, $this->tenantData($tenants), 'Tenants retrieved successfully');
        });
    }

    /** @return array{tenants:list<array{id:int,name:string,db:string,cvr:string,company_name:string}>} */
    protected function tenantData(array $tenants): array
    {
        return ['tenants' => MobileRegistryDirectory::publicTenants($tenants)];
    }

    protected function handlePost($data)
    {
        $this->sendError('validation_failed', 'Use GET /auth/tenants.php', 405);
    }
}

if (realpath((string)ifset($_SERVER, 'SCRIPT_FILENAME', '')) === __FILE__) {
    (new AuthTenantsEndpoint())->handleRequestMethod();
}
