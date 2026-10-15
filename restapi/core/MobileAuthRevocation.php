<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/MobileAuthRevocation.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Add the mobile Assist identity and delegation contract.

require_once __DIR__ . '/MobileAuth.php';

/** Revoke the subject, scoping account ids to their tenant; reason names the caller's change. */
function revokeSessions(string $subject, string $reason, ?int $tenantId = null): void
{
    (new MobileAuth())->revoke($subject, $reason, $tenantId);
}

/** Legacy write paths call this while their original database is selected. */
function mobileRevokeUser(int $userId, string $reason, ?string $database = null): void
{
    global $connection, $sqdb;
    $database = $database ?? pg_dbname($connection);
    $auth = new MobileAuth();
    if ($database === $sqdb) {
        revokeSessions(Subject::build('registry', $userId), $reason);
        return;
    }
    $db = $auth->store();
    $name = $db->literal($database);
    foreach ($db->rows("SELECT id FROM regnskab WHERE db=$name") as $tenant) {
        revokeSessions(Subject::build('account', $userId), $reason, (int)$tenant['id']);
    }
}

/** Account closure invalidates all families, including registry exchanges, and pending challenges. */
function mobileRevokeTenant(int $tenantId, string $reason = 'account_closed'): void
{
    $auth = new MobileAuth();
    $db = $auth->store();
    $rows = $db->rows('SELECT DISTINCT subject,principal FROM refresh_sessions WHERE tenant_id=' . $tenantId . ' UNION SELECT DISTINCT subject,principal FROM auth_challenges WHERE tenant_id=' . $tenantId);
    foreach ($rows as $row) {
        revokeSessions($row['subject'], $reason, $row['principal'] === 'account' ? $tenantId : null);
    }
}

/** Best-effort tenant cleanup after registry revocation. Liveness checks remain authoritative.
 * @param list<array{family_id:string,tenant_id:int|string}> $families
 */
function mobileRevokeAssistGrants(array $families): void
{
    require_once __DIR__ . '/../../includes/runtime/bootstrap.php';
    foreach ($families as $family) {
        $tenant = null;
        try {
            $entry = saldi_runtime_master()->one('SELECT db FROM regnskab WHERE id = ?', [(int)$family['tenant_id']]);
            if (!$entry) {
                continue;
            }
            $tenant = SaldiDb::connect(saldi_runtime_config(), $entry['db']);
            if ($tenant->hasTable('agent_grants') && $tenant->hasColumn('agent_grants', 'family_id')) {
                $tenant->exec("UPDATE agent_grants SET revoked_at = ? WHERE family_id = ? AND kind = 'mobile' AND revoked_at IS NULL", [gmdate('Y-m-d H:i:s'), $family['family_id']]);
            }
        } catch (Throwable $e) {
            error_log('Mobile Assist grant cleanup unavailable');
        } finally {
            if ($tenant !== null) {
                $tenant->close();
            }
        }
    }
}
