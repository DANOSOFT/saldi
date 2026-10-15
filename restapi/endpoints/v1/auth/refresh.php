<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/auth/refresh.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Rotate session-backed refresh families; remove tenant header fallback.
// 20261001 CDX/LH Preserve legacy refresh responses and validate tenant errors before session rejection.
require_once __DIR__ . '/../../../core/MobileAuthEndpoint.php';

class AuthRefreshEndpoint extends MobileAuthEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'auth', 'action' => 'public'];
    }

    protected function checkAuthorization()
    {
        return true;
    }

    protected function handlePost($data)
    {
        $this->authOperation(function () use ($data): void {
            if (!is_string(ifset($data, 'refresh_token')) || $data->refresh_token === '') {
                throw new MobileAuthError('validation_failed', 400);
            }
            $payload = JWT::decode($data->refresh_token);
            if (!$payload || ifset($payload, 'type') !== 'refresh' || !isset($payload['exp'])) {
                throw new MobileAuthError('invalid_token');
            }
            $auth = new MobileAuth();
            if (JWTAuth::isLegacyToken($payload)) {
                $tenantId = JWTAuth::tenantId($payload);
                if ($tenantId === null) {
                    throw new MobileAuthError('validation_failed', 400);
                }
                $tenant = ifset($auth->store()->rows('SELECT lukket FROM regnskab WHERE id=' . $tenantId), 0);
                if (!$tenant) {
                    throw new MobileAuthError('invalid_token');
                }
                if ($tenant['lukket'] === 'on') {
                    throw new MobileAuthError('account_closed', 403);
                }
                // Historical tenant-bound tokens remain revoked by E1.1. Keep only the
                // pre-identity, header-selected compatibility call without a device id.
                if (array_key_exists('tenant_id', $payload) || ifset($data, 'device_id') !== null) {
                    throw new MobileAuthError('session_revoked');
                }
                $context = $auth->context('account', (int)ifset($payload, 'user_id', 0), $tenantId);
                $subject = Subject::build('account', (int)$context['user']['id']);
                $version = $auth->version($subject, $tenantId);
                if ($version !== 1) {
                    throw new MobileAuthError('session_revoked');
                }
                $tokens = $auth->create($context['user'], 'account', $tenantId, MobileAuth::device($data), $version);
                unset($tokens['refresh_token']);
                $this->sendResponse(true, $tokens, 'Token refreshed successfully');
                return;
            }
            if (empty($payload['sid']) || empty($payload['jti'])) {
                throw new MobileAuthError('session_revoked');
            }
            $tokens = $auth->refresh($payload, MobileAuth::device($data), ifset($data, 'device_id') !== null);
            $this->sendResponse(true, $tokens, 'Token refreshed successfully');
        });
    }

    protected function handleGet($id = null)
    {
        $this->sendError('validation_failed', 'Use POST /auth/refresh.php', 405);
    }
}

(new AuthRefreshEndpoint())->handleRequestMethod();
