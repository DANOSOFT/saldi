<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/ebSchema.php --- ver 5.1.1 --- 2026-11-19 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
//
// 20261006 CDX/LH Created Enable Banking ebSchema backend service.
// 20261007 CDX/LH Added company journal line cap settings with defaults, clamping and audit.
// 20261007 CDX/LH Installed date overrides idempotently and resolved tenant base currency.
// 20261022 CDX/LH Persist permanently ambiguous bank identification hashes.
// 20261028 CDX/LH Migrate active accounts and install the automatic import date fence.
// 20261116 CL/LH Pull model: no journal pointer, automatic import or journal line cap; the never-released upgrade steps are gone.
// 20261116 CL/LH ebEnsureSchema() moved to includes/opdat_func/ebSchemaInstall.php: the tables are created by the update chain only.
// 20261119 CL/LH bank_transactions.balance_after and day_position (the bank's order within a day), added to tables created before them.

require_once __DIR__ . '/ebDb.php';

/** @return array<string, string> Tenant table definitions for the active SQL driver. */
function ebSchemaTables(): array
{
    $id = ebMysql() ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'SERIAL PRIMARY KEY';
    $ts = ebMysql() ? 'DATETIME' : 'TIMESTAMP';
    $amount = ebMysql() ? 'DECIMAL(15,3)' : 'NUMERIC(15,3)';
    $trigger = ebMysql() ? '`trigger`' : '"trigger"';
    return [
        'bank_connections' => "id $id, provider VARCHAR(20) NOT NULL, environment VARCHAR(12) NOT NULL,
            aspsp_name VARCHAR(100), aspsp_country VARCHAR(2), psu_type VARCHAR(10), session_enc TEXT NOT NULL,
            session_hash VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL, valid_until $ts NULL,
            created_by VARCHAR(100), created_at $ts NOT NULL, updated_at $ts NOT NULL, disconnected_at $ts NULL,
            disconnected_by VARCHAR(100) NULL, last_error TEXT NULL",
        'bank_accounts' => "id $id, connection_id INT NULL, identification_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(200), display_identity VARCHAR(64), currency VARCHAR(3), cash_account_type VARCHAR(4),
            state VARCHAR(20) NOT NULL, ledger_account INT NULL, import_from DATE NULL,
            fetched_through DATE NULL, last_fetch_at $ts NULL, last_import_at $ts NULL,
            next_fetch_at $ts NULL, fail_count INT NOT NULL DEFAULT 0, last_error_code VARCHAR(60) NULL, last_error TEXT NULL,
            lease_owner VARCHAR(64) NULL, lease_until $ts NULL, last_manual_at $ts NULL,
            created_at $ts NOT NULL, updated_at $ts NOT NULL",
        'bank_account_aliases' => "id $id, account_id INT NOT NULL, identification_hash VARCHAR(255) NOT NULL",
        'bank_ambiguous_hashes' => "id $id, identification_hash VARCHAR(255) NOT NULL, created_at $ts NOT NULL",
        'bank_account_sessions' => "id $id, account_id INT NOT NULL, connection_id INT NOT NULL, account_uid VARCHAR(64) NOT NULL,
            active SMALLINT NOT NULL, created_at $ts NOT NULL",
        'bank_transactions' => "id $id, account_id INT NOT NULL, dedup_key VARCHAR(160) NOT NULL,
            entry_reference VARCHAR(255) NULL, fingerprint VARCHAR(64) NOT NULL, payload_hash VARCHAR(64) NOT NULL,
            booking_date DATE NULL, value_date DATE NULL, date_override DATE NULL, transaction_date DATE NULL, amount $amount NOT NULL,
            currency VARCHAR(3), description VARCHAR(255), counterparty VARCHAR(140) NULL, bank_reference VARCHAR(140) NULL,
            status VARCHAR(4) NOT NULL, review_state VARCHAR(20) NOT NULL, review_reason VARCHAR(40) NULL,
            identical_group SMALLINT NOT NULL DEFAULT 0, changed_payload TEXT NULL, link_id INT NULL,
            first_seen_at $ts NOT NULL, last_seen_at $ts NOT NULL, first_run_id INT NULL, "
            . implode(', ', array_map(static fn(string $column, string $type): string => "$column $type",
                array_keys(ebSchemaAddedColumns()['bank_transactions']), ebSchemaAddedColumns()['bank_transactions'])),
        'bank_import_links' => "id $id, transaction_id INT NOT NULL, account_id INT NOT NULL, kladde_id INT NOT NULL,
            kassekladde_id INT NOT NULL, bilag INT NOT NULL, disposition VARCHAR(20) NOT NULL, created_at $ts NOT NULL,
            created_by VARCHAR(100), disposition_at $ts NULL",
        'bank_sync_runs' => "id $id, account_id INT NOT NULL, $trigger VARCHAR(12) NOT NULL, started_at $ts NOT NULL,
            finished_at $ts NULL, status VARCHAR(20) NOT NULL, window_from DATE NULL, window_to DATE NULL,
            pages INT NOT NULL DEFAULT 0, fetched INT NOT NULL DEFAULT 0, new_count INT NOT NULL DEFAULT 0,
            changed_count INT NOT NULL DEFAULT 0, imported_count INT NOT NULL DEFAULT 0, review_count INT NOT NULL DEFAULT 0,
            error_code VARCHAR(60) NULL, error_message TEXT NULL",
        'bank_audit_log' => "id $id, at $ts NOT NULL, actor VARCHAR(100), action VARCHAR(40) NOT NULL,
            account_id INT NULL, connection_id INT NULL, detail VARCHAR(255) NULL",
        'bank_auth_states' => "id $id, state_hash VARCHAR(64) NOT NULL, user_id INT NOT NULL, username VARCHAR(100),
            environment VARCHAR(12), purpose VARCHAR(12), connection_id INT NULL, aspsp_name VARCHAR(100),
            aspsp_country VARCHAR(2), psu_type VARCHAR(10), created_at $ts NOT NULL, expires_at $ts NOT NULL, used_at $ts NULL",
    ];
}

/** @return array<string, array<string, string>> Columns added after the first tables, per table: name => type. */
function ebSchemaAddedColumns(): array
{
    return ['bank_transactions' => ['balance_after' => (ebMysql() ? 'DECIMAL(15,3)' : 'NUMERIC(15,3)') . ' NULL', 'day_position' => 'INT NULL']];
}

/** @return bool Whether a column exists in the current tenant schema. */
function ebColumnExists(string $table, string $column): bool
{
    return ebRow('SELECT 1 FROM information_schema.columns WHERE table_schema=' . (ebMysql() ? 'DATABASE()' : 'current_schema()')
        . ' AND table_name=' . ebSql($table) . ' AND column_name=' . ebSql($column)) !== null;
}

/** @return array<string, array{table:string, columns:string, unique:bool}> Required indexes. */
function ebSchemaIndexes(): array
{
    return [
        'bank_connection_session_uq' => ['table' => 'bank_connections', 'columns' => 'session_hash', 'unique' => true],
        'bank_account_identity_uq' => ['table' => 'bank_accounts', 'columns' => 'identification_hash', 'unique' => true],
        'bank_alias_identity_uq' => ['table' => 'bank_account_aliases', 'columns' => 'identification_hash', 'unique' => true],
        'bank_ambiguous_hash_uq' => ['table' => 'bank_ambiguous_hashes', 'columns' => 'identification_hash', 'unique' => true],
        'bank_session_uid_uq' => ['table' => 'bank_account_sessions', 'columns' => 'connection_id, account_uid', 'unique' => true],
        'bank_transaction_dedup_uq' => ['table' => 'bank_transactions', 'columns' => 'account_id, dedup_key', 'unique' => true],
        'bank_transaction_state_idx' => ['table' => 'bank_transactions', 'columns' => 'account_id, review_state', 'unique' => false],
        'bank_transaction_date_idx' => ['table' => 'bank_transactions', 'columns' => 'account_id, booking_date', 'unique' => false],
        'bank_import_line_uq' => ['table' => 'bank_import_links', 'columns' => 'kassekladde_id', 'unique' => true],
        'bank_auth_state_uq' => ['table' => 'bank_auth_states', 'columns' => 'state_hash', 'unique' => true],
    ];
}

/** @return bool Whether a required index exists in the current tenant schema. */
function ebIndexExists(string $name, string $table): bool
{
    return ebRow(ebMysql()
        ? 'SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=' . ebSql($table) . ' AND index_name=' . ebSql($name)
        : 'SELECT 1 FROM pg_indexes WHERE schemaname=current_schema() AND tablename=' . ebSql($table) . ' AND indexname=' . ebSql($name)) !== null;
}

/** @return bool Whether all tenant banking tables and indexes are present. */
function ebSchemaReady(): bool
{
    foreach (array_keys(ebSchemaTables()) as $table) {
        if (!ebTableExists($table)) {
            return false;
        }
    }
    foreach (ebSchemaIndexes() as $name => $index) {
        if (!ebIndexExists($name, $index['table'])) {
            return false;
        }
    }
    foreach (ebSchemaAddedColumns() as $table => $columns) {
        foreach (array_keys($columns) as $column) {
            if (!ebColumnExists($table, $column)) {
                return false;
            }
        }
    }
    return true;
}

/** @return bool Explicit company-level enablement, without a user settings fallback. */
function ebCompanyEnabled(): bool
{
    return ebRow("SELECT id FROM settings WHERE var_grp='enable_banking' AND var_name='enabled' AND user_id=0 AND var_value='on'") !== null;
}

/** @return string Company base currency, resolved from the same setting as includes/online.php. */
function ebBaseCurrency(): string
{
    $setting = ebRow("SELECT var_value FROM settings WHERE var_name='baseCurrency' LIMIT 1");
    return $setting === null ? 'DKK' : (string)$setting['var_value'];
}

/** @return void Set the company toggle and append an audit record. */
function ebSetCompanyEnabled(bool $on, string $actor): void
{
    ebNamedLock('company_setting', function () use ($on, $actor): void {
        ebAtomic(function () use ($on, $actor): void {
            $setting = ebRow("SELECT id FROM settings WHERE var_grp='enable_banking' AND var_name='enabled' AND user_id=0 FOR UPDATE");
            if ($setting) {
                ebWrite("UPDATE settings SET var_value=" . ebSql($on ? 'on' : '') . " WHERE var_grp='enable_banking' AND var_name='enabled' AND user_id=0");
            } else {
                ebInsert('settings', ['var_grp' => 'enable_banking', 'var_name' => 'enabled', 'user_id' => 0, 'var_value' => $on ? 'on' : '']);
            }
            ebAudit($actor, $on ? 'enabled' : 'disabled');
        });
    });
}
