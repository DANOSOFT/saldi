<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/MobileAuthChallenges.php --- ver 5.0.0 --- 2026.10.01
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

require_once __DIR__ . '/MobileAuth.php';

/** Password-bound challenges. Only a CLI test subclass replaces delivery; no HTTP/env code hook exists. */
class MobileAuthChallenges
{
    private $auth;
    private $db;

    public function __construct(MobileAuth $auth)
    {
        $this->auth = $auth;
        $this->db = $auth->store();
    }

    /** @return array{2fa_required:bool,challenge_id:string,channel:string,hint:string} */
    public function create(array $user, int $tenantId, string $device, int $version): array
    {
        $channel = !empty($user['tlf']) ? 'sms' : 'email';
        $target = (string)ifset($user, $channel === 'sms' ? 'tlf' : 'email', '');
        if ($target === '' || ($channel === 'email' && !filter_var($target, FILTER_VALIDATE_EMAIL))) {
            throw new RuntimeException('2FA delivery target unavailable');
        }
        $id = MobileAuth::uuid();
        $subject = Subject::build('account', (int)$user['id']);
        $code = (string)random_int(100000, 999999);
        $hash = $this->db->literal(hash_hmac('sha256', $id . ':' . $code, _jwtLoadSecret()));
        $s = $this->db->literal($subject);
        $d = $this->db->literal($device);
        $c = $this->db->literal($id);
        $this->db->execute('BEGIN');
        try {
            if ($this->auth->version($subject, $tenantId, true) !== $version) {
                throw new MobileAuthError('2fa_failed');
            }
            $this->db->execute("INSERT INTO auth_challenges(id,subject,principal,tenant_id,device_id,channel,code_hash,password_verified_at,auth_version,expires_at) VALUES ($c,$s,'account',$tenantId,$d,'$channel',$hash,now(),$version,now()+INTERVAL '3 minutes')");
            $this->db->execute('COMMIT');
        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
        try {
            $this->deliver($channel, $target, $code, $tenantId);
        } catch (Throwable $e) {
            $this->db->execute("UPDATE auth_challenges SET consumed_at=now() WHERE id=$c");
            throw $e;
        }
        $hint = $channel === 'sms' ? '***' . substr($target, -4) : substr($target, 0, 1) . '***@' . substr(strrchr($target, '@'), 1);
        return ['2fa_required' => true, 'challenge_id' => $id, 'channel' => $channel, 'hint' => $hint];
    }

    /** @return array{user:array,tenant_id:int,version:int} Consume once, including wrong-code attempts. */
    public function complete(string $id, string $code, string $device): array
    {
        if (!preg_match('/^[a-f0-9-]{36}$/D', $id)) {
            throw new MobileAuthError('2fa_failed');
        }
        $c = $this->db->literal($id);
        // Read identity first, then lock epoch and challenge in that order (same as revoke).
        $rows = $this->db->rows("SELECT * FROM auth_challenges WHERE id=$c");
        $challenge = ifset($rows, 0);
        if (!$challenge) {
            throw new MobileAuthError('2fa_failed');
        }
        $this->db->execute('BEGIN');
        try {
            $version = $this->auth->version($challenge['subject'], (int)$challenge['tenant_id'], true);
            $challenge = $this->db->rows("SELECT *, expires_at>now() AS unexpired FROM auth_challenges WHERE id=$c FOR UPDATE")[0];
            $valid = $challenge['consumed_at'] === null && MobileAuth::enabled($challenge['unexpired'])
                && (int)$challenge['attempts'] < 5 && $challenge['device_id'] === $device && (int)$challenge['auth_version'] === $version;
            $matches = $valid && preg_match('/^[0-9]{6}$/D', $code) && hash_equals($challenge['code_hash'], hash_hmac('sha256', $id . ':' . $code, _jwtLoadSecret()));
            if ($challenge['consumed_at'] === null) {
                $consume = $matches || !$valid || (int)$challenge['attempts'] >= 4 ? ',consumed_at=now()' : '';
                $this->db->execute("UPDATE auth_challenges SET attempts=attempts+1$consume WHERE id=$c");
            }
            $this->db->execute('COMMIT');
        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
        if (!$matches) {
            throw new MobileAuthError('2fa_failed');
        }
        $identity = Subject::parse($challenge['subject']);
        $context = $this->auth->context('account', $identity['user_id'], (int)$challenge['tenant_id']);
        MobileAuth::enforceIp($context['user'], (string)ifset($_SERVER, 'REMOTE_ADDR', ''));
        return ['user' => $context['user'], 'tenant_id' => (int)$challenge['tenant_id'], 'version' => $version];
    }

    /** Current web provider, without send_sms()'s session/UI includes; mail uses the configured sendmail transport. */
    protected function deliver(string $channel, string $target, string $code, int $tenantId): void
    {
        $message = "Din tofaktor kode: $code\nDen er valid i 3 minutter";
        if ($channel === 'email') {
            if (!mail($target, 'Saldi 2FA kode', $message, "From: Saldi <info@saldi.dk>\r\nContent-Type: text/plain; charset=UTF-8")) {
                throw new RuntimeException('2FA email delivery failed');
            }
            return;
        }
        $rows = $this->db->rows("SELECT var_value FROM settings WHERE var_name='apikey' AND var_grp='cpsms' ORDER BY id DESC LIMIT 1");
        $key = ifset($rows, [0, 'var_value']);
        if (!$key) {
            throw new RuntimeException('SMS provider unavailable');
        }
        $ch = curl_init('https://api.cpsms.dk/v2/send');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $key, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['to' => clean_phone_number($target), 'message' => $message, 'from' => 'Saldi']), CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $result = is_string($body) ? json_decode($body, true) : null;
        if ($status < 200 || $status >= 300 || ifset($result, ['success', 0, 'cost']) === null) {
            throw new RuntimeException('2FA SMS delivery failed');
        }
        $cost = (float)$result['success'][0]['cost'];
        $this->db->execute("UPDATE regnskab SET sms=COALESCE(sms,0)+$cost WHERE id=$tenantId");
    }
}
