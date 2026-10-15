<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/notifications/register.php --- ver 5.0.0 --- 2026.10.01
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
// 20260906 CDX/LH Reuse inherited endpoint properties; reject unsupported GET with 405.
// 20261001 CDX/LH Tenant subject/device registration, nullable native token and scoped logout deletion.
// 20261001 CDX/LH Adopt identifiable legacy account registrations without duplicate deliveries.

require_once __DIR__ . '/../../../services/MobilePushEndpoint.php';

final class NotificationsRegisterEndpoint extends MobilePushEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module'=>'bilag', 'action'=>'own'];
    }

    /** @return list<string> */
    protected function methods(): array
    {
        return ['POST', 'DELETE'];
    }

    /** Device id is stable across token refreshes; token-only callers keep a legacy fallback. */
    private function device($data, ?string $token): string
    {
        $device = ifset($data, 'device_id', $token === null ? null : 'legacy-' . hash('sha256', $token));
        if (!is_string($device) || strlen($device) > 128 || !preg_match('/^[A-Za-z0-9._:-]+$/D', $device)) {
            $this->sendError('validation_failed', 'device_id is required (1–128 letters, digits, . _ : -)', 422);
        }
        return $device;
    }

    protected function handlePost($data)
    {
        $token = ifset($data, 'token');
        $platform = ifset($data, 'platform', 'unknown');
        $native = ifset($data, 'native_token');
        if (!is_string($token) || !preg_match('/^(Expo|Exponent)PushToken\[[A-Za-z0-9_-]+\]$/D', $token)
            || strlen($token) > 255 || !in_array($platform, ['ios','android','unknown'], true)
            || ($native !== null && (!is_string($native) || $native === '' || strlen($native) > 4096 || str_contains($native, "\0")))) {
            $this->sendError('validation_failed', 'A valid Expo token, platform and optional native_token are required', 422);
        }
        $device = $this->device($data, $token);
        $store = new MobileSchema($this->conn);
        $owner = $store->literal($this->subject);
        $principal = $store->literal($this->principal);
        $push = $store->literal($token);
        $os = $store->literal($platform);
        $name = $store->literal($this->db);
        $dev = $store->literal($device);
        $nativeSql = $native === null ? 'NULL' : $store->literal($native);
        $user = (int)$this->userId;
        $store->execute('BEGIN');
        try {
            $store->rows("SELECT pg_advisory_xact_lock(hashtext(current_database()),hashtext($owner))");
            if ($this->principal === 'account') {
                $legacy = "user_id=$user AND token=$push AND db_name=$name AND principal='account'
                    AND (subject IS NULL OR subject=$owner) AND device_id IS NULL";
                $existing = $store->rows("SELECT id FROM push_tokens WHERE subject=$owner AND device_id=$dev FOR UPDATE");
                if ($existing === []) {
                    $rows = $store->rows("SELECT id FROM push_tokens WHERE $legacy ORDER BY id FOR UPDATE");
                    if ($rows !== []) {
                        $adoptId = (int)$rows[0]['id'];
                        $store->execute("UPDATE push_tokens SET subject=$owner,device_id=$dev WHERE id=$adoptId");
                    }
                }
                $store->execute("DELETE FROM push_tokens WHERE $legacy");
            }
            $store->execute("INSERT INTO push_tokens(user_id,token,platform,db_name,subject,principal,device_id,last_seen_at,native_token)
                VALUES($user,$push,$os,$name,$owner,$principal,$dev,now(),$nativeSql)
                ON CONFLICT(subject,device_id) DO UPDATE SET token=EXCLUDED.token,platform=EXCLUDED.platform,
                    last_seen_at=now(),native_token=EXCLUDED.native_token,principal=EXCLUDED.principal,
                    user_id=EXCLUDED.user_id,db_name=EXCLUDED.db_name");
            $store->execute('COMMIT');
        } catch (Throwable $error) {
            $store->execute('ROLLBACK');
            throw $error;
        }
        $this->removeLegacyRegistryToken($token);
        $this->sendResponse(true, ['registered'=>true], 'Device token registered successfully');
    }

    /** Remove only identifiable old account registrations; ambiguous NULL-db rows remain quarantined. */
    private function removeLegacyRegistryToken(?string $token): void
    {
        if ($this->principal !== 'account' || $token === null) {
            return;
        }
        $registry = new MobileSchema(MobileAuth::connectRegistry());
        if (!$registry->rows("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='push_tokens'")) {
            return;
        }
        $user = (int)$this->userId;
        $push = $registry->literal($token);
        $name = $registry->literal($this->db);
        $registry->execute("DELETE FROM push_tokens WHERE user_id=$user AND token=$push AND db_name=$name");
    }

    protected function handleDelete($data)
    {
        $token = ifset($data, 'token', ifset($_GET, 'token'));
        if ($token !== null && (!is_string($token) || strlen($token) > 255 || $token === '')) {
            $this->sendError('validation_failed', 'token must be a nonempty string', 422);
        }
        $store = new MobileSchema($this->conn);
        $owner = $store->literal($this->subject);
        if (ifset($data, 'device_id') !== null) {
            $device = $store->literal($this->device($data, $token));
            $filter = "device_id=$device";
        } elseif ($token !== null) {
            $filter = 'token=' . $store->literal($token);
        } else {
            $this->sendError('validation_failed', 'device_id or token is required', 422);
        }
        $store->execute("DELETE FROM push_tokens WHERE subject=$owner AND $filter");
        $this->removeLegacyRegistryToken($token);
        $this->sendResponse(true, ['unregistered'=>true], 'Device token unregistered successfully');
    }
}

(new NotificationsRegisterEndpoint())->handleRequestMethod();
