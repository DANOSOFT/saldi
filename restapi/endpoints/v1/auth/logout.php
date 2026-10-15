<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/auth/logout.php --- ver 5.0.0 --- 2026.10.10
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
// 20261010 CDX/LH Revoke Assist grants after single-device logout commits.

require_once __DIR__ . '/../../../core/MobileAuthEndpoint.php';

class AuthLogoutEndpoint extends MobileAuthEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'auth', 'action' => 'self'];
    }

    protected function handlePost($data)
    {
        $this->authOperation(function () use ($data): void {
            $all = ifset($data, 'all', false);
            if (!is_bool($all)) {
                throw new MobileAuthError('validation_failed', 400);
            }
            $payload = JWTAuth::validateToken();
            $auth = new MobileAuth();
            $db = $auth->store();
            $subject = $db->literal($this->subject);
            $device = $db->literal($payload['device_id']);
            $tenantId = empty($payload['tenant_id']) ? null : (int)$payload['tenant_id'];
            $scope = $this->principal === 'account' ? ' AND tenant_id=' . (int)$tenantId : '';
            // Save push-cleanup tenants before revocation, including registry exchanges.
            $tenants = $db->rows("SELECT DISTINCT tenant_id FROM refresh_sessions WHERE subject=$subject$scope" . ($all ? '' : " AND device_id=$device") . ' AND tenant_id IS NOT NULL');
            if ($all) {
                revokeSessions($this->subject, 'logout_all', $this->principal === 'registry' ? null : $tenantId);
            } else {
                $db->execute('BEGIN');
                try {
                    // Serialize with refresh; a row inserted during rotation must also be revoked.
                    $auth->version($this->subject, $this->principal === 'registry' ? null : $tenantId, true);
                    $familyScope = $this->principal === 'account' ? ' AND tenant_id=$3' : '';
                    $parameters = [$this->subject, $payload['device_id']];
                    if ($this->principal === 'account') {
                        $parameters[] = $tenantId;
                    }
                    $result = pg_query_params(MobileAuth::connectRegistry(), 'SELECT DISTINCT family_id,tenant_id FROM refresh_sessions WHERE subject=$1 AND device_id=$2' . $familyScope . ' AND tenant_id IS NOT NULL', $parameters);
                    if ($result === false) {
                        throw new RuntimeException('Mobile logout families unavailable');
                    }
                    $families = pg_fetch_all($result);
                    $db->execute("UPDATE refresh_sessions SET revoked_at=COALESCE(revoked_at,now()) WHERE subject=$subject$scope AND device_id=$device");
                    $db->execute("UPDATE auth_challenges SET consumed_at=COALESCE(consumed_at,now()) WHERE subject=$subject$scope AND device_id=$device");
                    $db->execute('COMMIT');
                } catch (Throwable $e) {
                    $db->execute('ROLLBACK');
                    throw $e;
                }
                mobileRevokeAssistGrants($families);
            }
            foreach ($tenants as $row) {
                $this->deletePush($auth, (int)$row['tenant_id'], $payload['device_id'], $all);
            }
            // Remove historical registry registrations too, if that table exists.
            $this->deletePushRows($db, $payload['device_id'], $all);
            $this->sendResponse(true, ['logged_out' => true, 'all' => $all], 'Logged out');
        });
    }

    /** Clean the tenant's push registration even when logout-all invalidated its session first. */
    private function deletePush(MobileAuth $auth, int $tenantId, string $device, bool $all): void
    {
        $rows = $auth->store()->rows('SELECT db FROM regnskab WHERE id=' . $tenantId);
        if (!$rows) {
            return;
        }
        global $sqhost, $squser, $sqpass, $connection;
        $previous = $connection;
        try {
            $link = db_connect($sqhost, $squser, $sqpass, $rows[0]['db'], __FILE__ . ' linje ' . __LINE__);
            $this->deletePushRows(new MobileSchema($link), $device, $all);
        } finally {
            $connection = $previous;
        }
    }

    /** Legacy tables without subject/device cannot be unambiguously assigned to a device. */
    private function deletePushRows(MobileSchema $db, string $device, bool $all): void
    {
        $columns = $db->rows("SELECT column_name FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='push_tokens'");
        $names = array_column($columns, 'column_name');
        if (!in_array('subject', $names, true) || !in_array('device_id', $names, true)) {
            return;
        }
        $s = $db->literal($this->subject);
        $d = $db->literal($device);
        $db->execute("DELETE FROM push_tokens WHERE subject=$s" . ($all ? '' : " AND device_id=$d"));
    }

    protected function handleGet($id = null)
    {
        $this->sendError('validation_failed', 'Use POST /auth/logout.php', 405);
    }
}

(new AuthLogoutEndpoint())->handleRequestMethod();
