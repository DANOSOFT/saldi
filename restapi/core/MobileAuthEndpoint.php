<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/MobileAuthEndpoint.php --- ver 5.0.0 --- 2026.10.01
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

require_once __DIR__ . '/BaseEndpoint.php';
require_once __DIR__ . '/MobileAuth.php';
require_once __DIR__ . '/MobileAuthRevocation.php';

/** Shared error mapping for auth endpoints only. */
abstract class MobileAuthEndpoint extends BaseEndpoint
{
    protected function handlePut($data)
    {
        $this->sendError('validation_failed', 'Method not allowed', 405);
    }

    protected function handlePatch($data)
    {
        $this->sendError('validation_failed', 'Method not allowed', 405);
    }

    protected function handleDelete($data)
    {
        $this->sendError('validation_failed', 'Method not allowed', 405);
    }

    /** Self endpoints always return machine-readable authentication failures. */
    protected function checkAuthorization()
    {
        if (!JWTAuth::validateToken()) {
            $this->sendError('invalid_token', 'Valid access token required', 401);
        }
        return parent::checkAuthorization();
    }

    /** Execute domain auth operations using the foundation's machine-readable envelope. */
    protected function authOperation(callable $operation): void
    {
        try {
            $operation();
        } catch (MobileAuthError $e) {
            if ($e->status === 429) {
                header('Retry-After: 60');
            }
            $messages = ['invalid_credentials' => 'Invalid username or password', 'account_closed' => 'Account is closed', 'user_disabled' => 'User is disabled', 'ip_rejected' => 'IP address is not allowed', '2fa_failed' => 'Invalid or expired challenge', 'rate_limited' => 'Too many login attempts', 'session_revoked' => 'Session revoked. Login again', 'invalid_token' => 'Invalid or expired token', 'permission_denied' => 'Permission denied', 'validation_failed' => 'Invalid request fields'];
            $this->sendError($e->error, ifset($messages, $e->error, 'Authentication failed'), $e->status);
        }
    }
}
