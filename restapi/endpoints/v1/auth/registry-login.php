<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/auth/registry-login.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Refund verified registry attempts and reject off-network password guesses uniformly.

require_once __DIR__ . '/tenants.php';
require_once __DIR__ . '/../../../core/MobileAuthChallenges.php';

/** Registry challenge storage adapts B2's account-only helper; delivery remains B2's implementation. */
class MobileRegistryChallenges extends MobileAuthChallenges
{
    private $registryAuth;
    private $registryStore;

    public function __construct(MobileAuth $auth)
    {
        parent::__construct($auth);
        $this->registryAuth = $auth;
        $this->registryStore = $auth->store();
    }

    /**
     * Registry challenges have no tenant. The parent signature's tenantId is unused.
     * @return array{2fa_required:bool,challenge_id:string,channel:string,hint:string}
     */
    public function create(array $user, int $tenantId, string $device, int $version): array
    {
        $channel = !empty($user['tlf']) ? 'sms' : 'email';
        $target = (string)ifset($user, $channel === 'sms' ? 'tlf' : 'email', '');
        if ($target === '' || ($channel === 'email' && !filter_var($target, FILTER_VALIDATE_EMAIL))) {
            throw new RuntimeException('2FA delivery target unavailable');
        }
        $db = $this->registryStore;
        $id = MobileAuth::uuid();
        $subject = Subject::build('registry', (int)$user['id']);
        $code = (string)random_int(100000, 999999);
        $hash = $db->literal(hash_hmac('sha256', $id . ':' . $code, _jwtLoadSecret()));
        $c = $db->literal($id);
        $s = $db->literal($subject);
        $d = $db->literal($device);
        $db->execute('BEGIN');
        try {
            if ($this->registryAuth->version($subject, null, true) !== $version) {
                throw new MobileAuthError('2fa_failed');
            }
            $db->execute("INSERT INTO auth_challenges(id,subject,principal,tenant_id,device_id,channel,code_hash,password_verified_at,auth_version,expires_at) VALUES ($c,$s,'registry',NULL,$d,'$channel',$hash,now(),$version,now()+INTERVAL '3 minutes')");
            $db->execute('COMMIT');
        } catch (Throwable $e) {
            $db->execute('ROLLBACK');
            throw $e;
        }
        try {
            // Registry login has no company to charge; B2's SMS cost update targets no row (id 0).
            $this->deliver($channel, $target, $code, 0);
        } catch (Throwable $e) {
            $db->execute("UPDATE auth_challenges SET consumed_at=now() WHERE id=$c");
            throw $e;
        }
        $hint = $channel === 'sms' ? '***' . substr($target, -4) : substr($target, 0, 1) . '***@' . substr(strrchr($target, '@'), 1);
        return ['2fa_required' => true, 'challenge_id' => $id, 'channel' => $channel, 'hint' => $hint];
    }

    /** @return array{user:array,tenant_id:null,version:int} */
    public function complete(string $id, string $code, string $device): array
    {
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $id)) {
            throw new MobileAuthError('2fa_failed');
        }
        $db = $this->registryStore;
        $c = $db->literal($id);
        $challenge = ifset($db->rows("SELECT * FROM auth_challenges WHERE id=$c AND principal='registry' AND tenant_id IS NULL"), 0);
        if (!$challenge) {
            throw new MobileAuthError('2fa_failed');
        }
        $db->execute('BEGIN');
        try {
            $version = $this->registryAuth->version($challenge['subject'], null, true);
            $challenge = $db->rows("SELECT *,expires_at>now() AS unexpired FROM auth_challenges WHERE id=$c FOR UPDATE")[0];
            $valid = $challenge['consumed_at'] === null && MobileAuth::enabled($challenge['unexpired'])
                && (int)$challenge['attempts'] < 5 && $challenge['device_id'] === $device && (int)$challenge['auth_version'] === $version;
            $matches = $valid && preg_match('/^[0-9]{6}$/D', $code) && hash_equals($challenge['code_hash'], hash_hmac('sha256', $id . ':' . $code, _jwtLoadSecret()));
            if ($challenge['consumed_at'] === null) {
                $consume = $matches || !$valid || (int)$challenge['attempts'] >= 4 ? ',consumed_at=now()' : '';
                $db->execute("UPDATE auth_challenges SET attempts=attempts+1$consume WHERE id=$c");
            }
            $db->execute('COMMIT');
        } catch (Throwable $e) {
            $db->execute('ROLLBACK');
            throw $e;
        }
        if (!$matches) {
            throw new MobileAuthError('2fa_failed');
        }
        $identity = Subject::parse($challenge['subject']);
        if ($identity['principal'] !== 'registry') {
            throw new MobileAuthError('2fa_failed');
        }
        $user = $this->registryAuth->context('registry', $identity['user_id'], null)['user'];
        MobileAuth::enforceIp($user, (string)ifset($_SERVER, 'REMOTE_ADDR', ''));
        return ['user' => $user, 'tenant_id' => null, 'version' => $version];
    }
}

class AuthRegistryLoginEndpoint extends MobileAuthEndpoint
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
            if (!is_object($data) || !is_string(ifset($data, 'device_id'))) {
                throw new MobileAuthError('validation_failed', 400);
            }
            $device = MobileAuth::device($data);
            $auth = new MobileAuth();
            $db = $auth->store();
            $ip = (string)ifset($_SERVER, 'REMOTE_ADDR', '');
            $challengeId = ifset($data, 'challenge_id');
            if ($challengeId !== null) {
                if (!is_string($challengeId) || !is_string(ifset($data, 'code'))) {
                    throw new MobileAuthError('validation_failed', 400);
                }
                $c = $db->literal($challengeId);
                $pending = ifset($db->rows("SELECT subject FROM auth_challenges WHERE id=$c AND principal='registry' AND tenant_id IS NULL"), 0);
                $username = 'unknown-challenge';
                if ($pending) {
                    $identity = Subject::parse($pending['subject']);
                    $user = ifset($db->rows('SELECT brugernavn FROM brugere WHERE id=' . (int)$identity['user_id']), 0, []);
                    $username = (string)ifset($user, 'brugernavn', $username);
                }
                $auth->rateLimit($ip, '', $username, 'registry');
                $verified = (new MobileRegistryChallenges($auth))->complete($challengeId, $data->code, $device);
                $auth->verifiedLogin('', $username, 'registry');
                $this->success($auth, $verified['user'], $device, $verified['version']);
                return;
            }
            foreach (['username', 'password'] as $field) {
                if (!is_string(ifset($data, $field)) || trim($data->$field) === '' || strlen($data->$field) > ($field === 'password' ? 4096 : 80)) {
                    throw new MobileAuthError('validation_failed', 400);
                }
            }
            $username = MobileAuth::normalize($data->username);
            $auth->rateLimit($ip, '', $username, 'registry');
            $u = $db->literal($username);
            $user = ifset($db->rows("SELECT * FROM brugere WHERE lower(brugernavn)=$u LIMIT 1"), 0);
            if (!$user) {
                throw new MobileAuthError('invalid_credentials');
            }
            $version = $auth->version(Subject::build('registry', (int)$user['id']), null);
            $user = ifset($db->rows('SELECT * FROM brugere WHERE id=' . (int)$user['id']), 0);
            if ($user) {
                MobileAuth::enforceIp($user, $ip);
            }
            if (!$user || (!hash_equals((string)$user['kode'], md5($data->password)) && !hash_equals((string)$user['kode'], saldikrypt((int)$user['id'], $data->password)))) {
                throw new MobileAuthError('invalid_credentials');
            }
            $auth->verifiedLogin('', $username, 'registry');
            $user = $auth->context('registry', (int)$user['id'], null)['user'];
            MobileAuth::enforceIp($user, $ip);
            if (MobileAuth::enabled(ifset($user, 'twofactor', false))) {
                $this->sendResponse(true, (new MobileRegistryChallenges($auth))->create($user, 0, $device, $version), 'Two-factor authentication required');
                return;
            }
            $this->success($auth, $user, $device, $version);
        });
    }

    /** Return no tenant token until a separate membership-checked exchange. */
    private function success(MobileAuth $auth, array $user, string $device, int $version): void
    {
        $tenants = MobileRegistryDirectory::publicTenants(MobileRegistryDirectory::tenants($auth, $user));
        $tokens = $auth->create($user, 'registry', null, $device, $version);
        $rights = Rights::registry((string)$user['rettigheder']);
        $tokens['user'] = ['id' => (int)$user['id'], 'username' => $user['brugernavn'], 'is_admin' => $rights['admin']];
        $tokens['tenant'] = null;
        $tokens['tenants'] = $tenants;
        $this->sendResponse(true, $tokens, 'Registry login successful');
    }

    protected function handleGet($id = null)
    {
        $this->sendError('validation_failed', 'Use POST /auth/registry-login.php', 405);
    }
}

if (realpath((string)ifset($_SERVER, 'SCRIPT_FILENAME', '')) === __FILE__) {
    (new AuthRegistryLoginEndpoint())->handleRequestMethod();
}
