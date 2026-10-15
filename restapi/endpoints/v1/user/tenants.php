<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/user/tenants.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Replace nonexistent membership columns and substring rights with the registry decoder.
// 20261001 CDX/LH Declare authorization explicitly on the inherited route.

require_once __DIR__ . '/../auth/tenants.php';

/** Deprecated alias; preserve the legacy list fields with registry-principal authorization. */
class UserTenantsEndpoint extends AuthTenantsEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'auth', 'action' => 'self'];
    }

    /** @return list<array{id:int,name:string,cvr:string,db:string,email:string,closed:bool}> */
    protected function tenantData(array $tenants): array
    {
        header('Deprecation: true');
        header('Link: <../auth/tenants.php>; rel="successor-version"');
        return $tenants;
    }
}

(new UserTenantsEndpoint())->handleRequestMethod();
