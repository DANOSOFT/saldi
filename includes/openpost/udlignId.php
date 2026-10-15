<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/openpost/udlignId.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Add udlign_next_id_db() so Saldi Assist settlements share the sequence.

require_once __DIR__ . '/../../restapi/core/MobileSchema.php';

/**
 * Allocate a tenant-wide settlement group, including from autocommit web pages.
 * PostgreSQL sequence gaps are intentional. MySQL keeps the legacy non-concurrent fallback.
 * @return int Positive group id, above the migration-time legacy maximum.
 */
function udlign_next_id($connection = null): int
{
    $connection = $connection ?? ifset($GLOBALS, 'connection');
    $store = new MobileSchema($connection);
    if ($connection instanceof mysqli) {
        return (int)$store->rows('SELECT COALESCE(MAX(udlign_id),0)+1 AS id FROM openpost')[0]['id'];
    }
    if ($store->rows("SELECT to_regclass('openpost_udlign_seq') AS name")[0]['name'] === null) {
        $owned = pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE;
        if ($owned) {
            $store->execute('BEGIN');
        } elseif (pg_transaction_status($connection) !== PGSQL_TRANSACTION_INTRANS) {
            throw new LogicException('Settlement allocation requires a usable connection');
        }
        try {
            $store->execute("SELECT pg_advisory_xact_lock(hashtext('saldi:mobile:udlign'))");
            if ($store->rows("SELECT to_regclass('openpost_udlign_seq') AS name")[0]['name'] === null) {
                // Do not advance the full migration marker from inside a business transaction.
                udlign_initialize_sequence($store);
            }
            if ($owned) {
                $store->execute('COMMIT');
            }
        } catch (Throwable $exception) {
            if ($owned) {
                $store->execute('ROLLBACK');
            }
            throw $exception;
        }
    }
    return (int)$store->rows("SELECT nextval('openpost_udlign_seq') AS id")[0]['id'];
}

/**
 * Seed/resume the PostgreSQL sequence under the caller's transaction and table/sequence locks.
 * Called by the new m2_10 schema step and the missing-sequence bootstrap only.
 */
function udlign_initialize_sequence(MobileSchema $store): void
{
    foreach (udlign_sequence_statements() as $sql) {
        $store->execute($sql);
    }
}

/** @return list<string> PostgreSQL statements that create and seed openpost_udlign_seq. */
function udlign_sequence_statements(): array
{
    return [
        'LOCK TABLE openpost IN SHARE ROW EXCLUSIVE MODE',
        'CREATE SEQUENCE IF NOT EXISTS openpost_udlign_seq',
        'ALTER SEQUENCE openpost_udlign_seq INCREMENT BY 1',
        // Preserve reservations too when an explicitly reset marker replays this step.
        "SELECT setval('openpost_udlign_seq', GREATEST(
        COALESCE((SELECT MAX(udlign_id) FROM openpost),0)+1,
        (SELECT last_value + CASE WHEN is_called THEN 1 ELSE 0 END FROM openpost_udlign_seq), 1), false)",
    ];
}

/**
 * udlign_next_id() for the Saldi Assist runtime handle. Must run inside $db->transaction().
 * Takes the same advisory lock name, so both allocators serialize sequence bootstrap.
 */
function udlign_next_id_db(SaldiDb $db): int
{
    if (!$db->isPostgres()) {
        return (int)$db->value('SELECT COALESCE(MAX(udlign_id),0)+1 FROM openpost');
    }
    if ($db->value("SELECT to_regclass('openpost_udlign_seq')") === null) {
        $db->lock('saldi:mobile:udlign');
        if ($db->value("SELECT to_regclass('openpost_udlign_seq')") === null) {
            foreach (udlign_sequence_statements() as $sql) {
                $db->exec($sql);
            }
        }
    }
    return (int)$db->value("SELECT nextval('openpost_udlign_seq')");
}
