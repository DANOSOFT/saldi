<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/auth/login.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Session families, password-bound 2FA, token buckets and web IP parity.
// 20261001 CDX/LH Preserve the legacy unknown-account 404 response.
// 20261001 CDX/LH Add the tenant company name while retaining the registry login name.
// 20261001 CDX/LH Reject off-network credentials uniformly and refund verified account-bucket debits.
require_once __DIR__ . '/../../../core/MobileAuthEndpoint.php';
require_once __DIR__ . '/../../../core/MobileTenantMetadata.php';
require_once __DIR__ . '/../../../core/MobileAuthChallenges.php';

class AuthLoginEndpoint extends MobileAuthEndpoint
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
            $auth = new MobileAuth();
            $device = MobileAuth::device($data);
            $challenge = ifset($data, 'challenge_id');
            if ($challenge !== null) {
                if (!is_string($challenge) || !is_string(ifset($data, 'code'))) {
                    throw new MobileAuthError('validation_failed', 400);
                }
                // The IP bucket also covers challenge guesses; each row has its own five-attempt bound.
                $key = $auth->store()->literal($challenge);
                $rows = $auth->store()->rows("SELECT subject,tenant_id FROM auth_challenges WHERE id=$key");
                $pending = ifset($rows, 0);
                if ($pending) {
                    $identity = Subject::parse($pending['subject']);
                    $context = $auth->context('account', $identity['user_id'], (int)$pending['tenant_id']);
                    $auth->rateLimit((string)ifset($_SERVER, 'REMOTE_ADDR', ''), $context['tenant']['regnskab'], $context['user']['brugernavn']);
                } else {
                    $auth->rateLimit((string)ifset($_SERVER, 'REMOTE_ADDR', ''), '', 'unknown-challenge');
                }
                $verified = (new MobileAuthChallenges($auth))->complete($challenge, $data->code, $device);
                $auth->verifiedLogin($context['tenant']['regnskab'], $context['user']['brugernavn']);
                $context = $auth->context('account', (int)$verified['user']['id'], $verified['tenant_id']);
                $this->success($auth->create($verified['user'], 'account', $verified['tenant_id'], $device, $verified['version']), $context);
                return;
            }
            foreach (['username', 'password', 'account_name'] as $field) {
                if (!is_string(ifset($data, $field)) || trim($data->$field) === '' || strlen($data->$field) > ($field === 'password' ? 4096 : ($field === 'username' ? 80 : 255))) {
                    throw new MobileAuthError('validation_failed', 400);
                }
            }
            $username = MobileAuth::normalize($data->username);
            $auth->rateLimit((string)ifset($_SERVER, 'REMOTE_ADDR', ''), $data->account_name, $username);
            $registry = $auth->store();
            $name = $registry->literal(trim($data->account_name));
            $tenants = $registry->rows("SELECT * FROM regnskab WHERE regnskab=$name LIMIT 1");
            $tenant = ifset($tenants, 0);
            if (!$tenant) {
                throw new MobileAuthError('invalid_credentials', 404);
            }
            global $sqhost, $squser, $sqpass, $connection;
            $connection = db_connect($sqhost, $squser, $sqpass, $tenant['db'], __FILE__ . ' linje ' . __LINE__);
            mobileTenantUpdates($connection);
            $tenantStore = new MobileSchema($connection);
            $u = $tenantStore->literal($username);
            $users = $tenantStore->rows("SELECT * FROM brugere WHERE lower(brugernavn)=$u LIMIT 1");
            $user = ifset($users, 0);
            if (!$user) {
                throw new MobileAuthError('invalid_credentials');
            }
            $subject = Subject::build('account', (int)$user['id']);
            // Capture epoch before password verification; a concurrent reset cannot issue an old login.
            $version = $auth->version($subject, (int)$tenant['id']);
            $user = ifset($tenantStore->rows('SELECT * FROM brugere WHERE id=' . (int)$user['id']), 0);
            if ($user) {
                MobileAuth::enforceIp($user, (string)ifset($_SERVER, 'REMOTE_ADDR', ''));
            }
            if (!$user || (!hash_equals((string)$user['kode'], md5($data->password)) && !hash_equals((string)$user['kode'], saldikrypt((int)$user['id'], $data->password)))) {
                throw new MobileAuthError('invalid_credentials');
            }
            $auth->verifiedLogin($data->account_name, $username);
            $context = $auth->context('account', (int)$user['id'], (int)$tenant['id']);
            MobileAuth::enforceIp($context['user'], (string)ifset($_SERVER, 'REMOTE_ADDR', ''));
            if (MobileAuth::enabled(ifset($context['user'], 'twofactor', false))) {
                $this->sendResponse(true, (new MobileAuthChallenges($auth))->create($context['user'], (int)$tenant['id'], $device, $version), 'Two-factor authentication required');
                return;
            }
            $this->success($auth->create($context['user'], 'account', (int)$tenant['id'], $device, $version), $context);
        });
    }

    /** Preserve the legacy login data fields, deriving admin from the shared rights decoder. */
    private function success(array $tokens, array $context): void
    {
        $user = $context['user'];
        $tenant = $context['tenant'];
        $rights = Rights::tenant((string)$user['rettigheder']);
        $tokens['user'] = ['id' => (int)$user['id'], 'username' => $user['brugernavn'], 'is_admin' => $rights['admin']];
        $tokens['tenant'] = MobileTenantMetadata::tenant($tenant, $context['connection']);
        $this->sendResponse(true, $tokens, 'Login successful');
    }

    protected function handleGet($id = null)
    {
        $this->sendError('validation_failed', 'Use POST /auth/login.php', 405);
    }
}

(new AuthLoginEndpoint())->handleRequestMethod();
