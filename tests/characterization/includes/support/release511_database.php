<?php
// tests/characterization/includes/support/release511_database.php
//
// 20261110 CDX/NTR Route updater DB calls to the release test's disposable PostgreSQL schemas.
// 20261111 CDX/ASR Provide SQL escaping for the independent CRM migration readiness probe.

function db_connect($host, $user, $password, $database, $location)
{
    return $GLOBALS['release511Tenant'];
}

function db_select_database($connection, $database)
{
    // Each fixture connection already has its tenant or registry search_path.
}

function db_select($sql, $location, $global = false)
{
    // Model a busy MySQL signup lock while retaining real PostgreSQL version reads.
    if ($GLOBALS['release511MysqlLockBusy'] ?? false) {
        if (str_contains($sql, "GET_LOCK('saldi:signup_schema'")) {
            return ['acquired' => '0'];
        }
        if (str_contains($sql, 'information_schema.columns')) {
            return str_contains($sql, "column_name='tekst_key'") ? ['column_name' => 'tekst_key'] : false;
        }
        if (str_contains($sql, 'information_schema.statistics')) {
            return ['index_name' => 'idx_tekster_sprog_key'];
        }
        if (str_contains($sql, 'openpost_udlign_date_backfilled')) {
            return ['var_value' => 'yes'];
        }
    }
    $connection = $global ? $GLOBALS['release511Registry'] : $GLOBALS['release511Tenant'];
    $result = pg_query($connection, $sql);
    if ($result === false) {
        throw new RuntimeException(pg_last_error($connection));
    }
    return $result;
}

function db_fetch_array($result)
{
    return is_array($result) || $result === false ? $result : pg_fetch_array($result);
}

function db_escape_string($value)
{
    return pg_escape_string($GLOBALS['release511Tenant'], (string) $value);
}

function db_modify($sql, $location, $global = false)
{
    if ($GLOBALS['release511Failure'] !== null && str_contains($sql, $GLOBALS['release511Failure'])) {
        throw new RuntimeException('Simulated migration failure');
    }
    $GLOBALS['release511Writes'][] = [$global, $sql];
    db_select($sql, $location, $global);
}
