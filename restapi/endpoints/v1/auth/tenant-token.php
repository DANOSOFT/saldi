<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/auth/tenant-token.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Add the selected tenant company name to registry exchanges.

require_once __DIR__ . '/../../../core/MobileAuthEndpoint.php';
require_once __DIR__ . '/../../../core/MobileTenantMetadata.php';

class AuthTenantTokenEndpoint extends MobileAuthEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'auth', 'action' => 'self'];
    }

    protected function handlePost($data)
    {
        $this->authOperation(function () use ($data): void {
            if ($this->principal !== 'registry') {
                throw new MobileAuthError('permission_denied', 403);
            }
            $tenantId = ifset($data, 'tenant_id');
            if (!is_int($tenantId) || $tenantId < 1) {
                throw new MobileAuthError('validation_failed', 400);
            }
            $auth = new MobileAuth();
            $user = $auth->context('registry', (int)$this->userId, null)['user'];
            $rights = Rights::registry((string)$user['rettigheder']);
            if (!$rights['admin'] && !in_array($tenantId, $rights['tenant_ids'], true)) {
                throw new MobileAuthError('permission_denied', 403);
            }
            global $sqdb;
            $registry = $auth->store()->literal($sqdb);
            if (!$auth->store()->rows("SELECT id FROM regnskab WHERE id=$tenantId AND db<>$registry")) {
                throw new MobileAuthError('permission_denied', 403);
            }
            $context = $auth->context('registry', (int)$this->userId, $tenantId);
            $payload = JWTAuth::validateToken();
            // B2 checks the parent family/epoch under its issuance lock. JWT enrichment migrates the tenant.
            $tokens = $auth->create($context['user'], 'registry', $tenantId, $payload['device_id'], (int)$payload['auth_version'], $payload['sid']);
            $tokens['user'] = ['id' => (int)$user['id'], 'username' => $user['brugernavn'], 'is_admin' => true];
            $tokens['tenant'] = MobileTenantMetadata::tenant($context['tenant']);
            $this->sendResponse(true, $tokens, 'Tenant token issued');
        });
    }

    protected function handleGet($id = null)
    {
        $this->sendError('validation_failed', 'Use POST /auth/tenant-token.php', 405);
    }
}

(new AuthTenantTokenEndpoint())->handleRequestMethod();
