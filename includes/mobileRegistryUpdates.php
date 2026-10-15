<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/mobileRegistryUpdates.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Address B1 identity, migration and security review findings.
// 20261001 CDX/LH Append encrypted replay storage for random refresh jtis.
// 20261001 CDX/LH Expose the ordered migration steps for append-safe marker assertions.
// 20261001 CDX/LH Append indexes for bounded refresh lookup and stale auth-record cleanup.

// 20261001 CDX/LH Append Tier 2 buckets/cache and a non-auth registry bootstrap.
// 20261001 CDX/LH Append Tier 3 registry summary cache.

require_once __DIR__ . '/../restapi/core/MobileSchema.php';

/** Registry migration marker: settings mobile/mobile_registry_step, user_id=0. */
function mobileRegistryUpdates($connection = null): void
{
    $schema = new MobileSchema($connection);
    $schema->run('registry', mobileRegistryUpdateSteps());
}

/**
 * Ordered migrations shared by the runner and marker consumers.
 *
 * @return array<string, Closure(MobileSchema): void> Named steps in application order.
 */
function mobileRegistryUpdateSteps(): array
{
    return [
        'r01_refresh_sessions' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS refresh_sessions (
                id serial PRIMARY KEY, jti_hash char(64) NOT NULL UNIQUE, family_id varchar(36) NOT NULL,
                subject varchar(255) NOT NULL, principal varchar(8) NOT NULL CHECK (principal IN ('account','registry')),
                tenant_id integer, device_id varchar(128) NOT NULL, expires_at timestamptz NOT NULL,
                revoked_at timestamptz, last_used_at timestamptz, auth_version integer NOT NULL DEFAULT 1,
                created_at timestamptz NOT NULL DEFAULT now())");
            $s->index('refresh_sessions', 'mobile_session_subject_tenant', 'subject, tenant_id');
            $s->index('refresh_sessions', 'mobile_session_family', 'family_id');
        },
        'r02_password_bound_challenges' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS auth_challenges (
                id varchar(36) PRIMARY KEY, subject varchar(255) NOT NULL,
                principal varchar(8) NOT NULL CHECK (principal IN ('account','registry')), tenant_id integer,
                device_id varchar(128) NOT NULL, channel varchar(5) NOT NULL CHECK (channel IN ('sms','email')),
                code_hash char(64) NOT NULL, password_verified_at timestamptz NOT NULL,
                auth_version integer NOT NULL, expires_at timestamptz NOT NULL,
                attempts integer NOT NULL DEFAULT 0, consumed_at timestamptz, created_at timestamptz DEFAULT now())");
            $s->index('auth_challenges', 'mobile_challenge_expiry', 'expires_at');
        },
        'r03_auth_token_buckets' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS auth_rate_limits (
                bucket_type varchar(10) NOT NULL CHECK (bucket_type IN ('ip','account')),
                bucket_key varchar(255) NOT NULL, tokens numeric(12,6) NOT NULL,
                updated_at timestamptz NOT NULL, blocked_until timestamptz,
                PRIMARY KEY(bucket_type, bucket_key))");
        },
        'r04_subject_auth_versions' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS auth_versions (
                subject varchar(255) NOT NULL, tenant_id integer NOT NULL DEFAULT 0,
                auth_version integer NOT NULL DEFAULT 1, updated_at timestamptz DEFAULT now(),
                PRIMARY KEY(subject, tenant_id))");
        },
        'r05_installation_identity' => function (MobileSchema $s): void {
            if (!$s->rows("SELECT id FROM settings WHERE var_grp='mobile' AND var_name='installation_id' AND user_id=0")) {
                $configured = getenv('SALDI_INSTALLATION_ID');
                $installation = $configured !== false && $configured !== '' ? $configured : 'install-' . bin2hex(random_bytes(16));
                if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $installation)) {
                    throw new InvalidArgumentException('Invalid SALDI_INSTALLATION_ID');
                }
                $value = $s->literal($installation);
                $s->execute("INSERT INTO settings (var_name,var_grp,var_value,user_id) VALUES ('installation_id','mobile',$value,0)");
            }
        },
        'r06_timezone_correction' => function (MobileSchema $s): void {
            foreach ([
                'refresh_sessions' => ['expires_at', 'revoked_at', 'last_used_at', 'created_at'],
                'auth_challenges' => ['password_verified_at', 'expires_at', 'consumed_at', 'created_at'],
                'auth_rate_limits' => ['updated_at', 'blocked_until'],
                'auth_versions' => ['updated_at'],
            ] as $table => $columns) {
                foreach ($columns as $column) {
                    $s->timezoneColumn($table, $column);
                }
            }
        },
        'r07_refresh_replay_ciphertext' => function (MobileSchema $s): void {
            $s->column('refresh_sessions', 'jti_ciphertext', 'text');
            // Session rows from earlier builds cannot recover random jtis for grace retries.
            $s->execute("UPDATE refresh_sessions SET revoked_at=COALESCE(revoked_at,now()) WHERE jti_ciphertext IS NULL");
        },
        'r08_auth_housekeeping_indexes' => function (MobileSchema $s): void {
            $s->index('refresh_sessions', 'mobile_session_family_scope_latest', 'family_id, tenant_id, id DESC');
            $s->index('refresh_sessions', 'mobile_session_expiry', 'expires_at');
            $s->index('refresh_sessions', 'mobile_session_revoked', 'revoked_at');
            $s->index('auth_challenges', 'mobile_challenge_consumed', 'consumed_at');
            $s->index('auth_rate_limits', 'mobile_bucket_updated', 'updated_at');
        },
        'r2_01_rate_buckets' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_rate_buckets (
                scope varchar(20) NOT NULL, bucket_key varchar(255) NOT NULL,
                tokens numeric(12,6) NOT NULL, updated_at timestamptz NOT NULL,
                PRIMARY KEY(scope, bucket_key))");
        },
        'r2_02_cvr_cache' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS cvr_cache (
                cvr char(8) PRIMARY KEY, payload text NOT NULL, fetched_at timestamptz NOT NULL)");
        },
        'r3_01_summary_cache' => function (MobileSchema $s): void {
            $s->execute("CREATE TABLE IF NOT EXISTS mobile_summary_cache (
                tenant_id integer PRIMARY KEY, payload text NOT NULL, computed_at timestamptz NOT NULL)");
        },
    ];
}

/** Non-auth registry consumers must bootstrap on their explicit registry connection before querying. */
function mobileT2RegistryStore($connection): MobileSchema
{
    mobileRegistryUpdates($connection);
    return new MobileSchema($connection);
}
