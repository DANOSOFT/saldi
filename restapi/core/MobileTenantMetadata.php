<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/MobileTenantMetadata.php --- ver 5.0.0 --- 2026.10.02
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

require_once __DIR__ . '/MobileSchema.php';

/** Company labels come from the tenant, while name remains the registry login name. */
final class MobileTenantMetadata
{
    /** @return string Own company address name, or the supplied registry fallback. */
    public static function companyName($tenantConnection, string $fallback): string
    {
        $store = new MobileSchema($tenantConnection);
        $columns = array_column($store->rows("SELECT column_name FROM information_schema.columns
            WHERE table_schema=current_schema() AND table_name='adresser' AND column_name IN ('art','firmanavn')"), 'column_name');
        if (!in_array('art', $columns, true) || !in_array('firmanavn', $columns, true)) {
            return $fallback;
        }
        $rows = $store->rows("SELECT firmanavn FROM adresser WHERE art='S' ORDER BY id LIMIT 1");
        $name = trim((string)ifset($rows, [0, 'firmanavn'], ''));
        return $name !== '' ? $name : $fallback;
    }

    /**
     * Resolve a registry-selected tenant, restoring the caller's selected connection.
     * @return array{id:int,name:string,db:string,company_name:string}
     */
    public static function tenant(array $tenant, $tenantConnection = null): array
    {
        global $sqhost, $squser, $sqpass, $connection;
        $previous = $connection;
        try {
            $link = $tenantConnection ?? db_connect($sqhost, $squser, $sqpass, $tenant['db'], __FILE__ . ' linje ' . __LINE__);
            return ['id' => (int)$tenant['id'], 'name' => $tenant['regnskab'], 'db' => $tenant['db'],
                'company_name' => self::companyName($link, $tenant['regnskab'])];
        } finally {
            $connection = $previous;
        }
    }
}
