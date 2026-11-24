<?php
// tests/characterization/includes/support/release511_database.php
//
// 20261110 CDX/NTR Route updater DB calls to the release test's disposable PostgreSQL schemas.
// 20261115 CL/ASR Integration: tjek4opdat() now takes the testlab's update lock (tjek4opdat_laas()), which escapes $db with db_escape_string() and on MySQL asks GET_LOCK()/RELEASE_LOCK() of its own; the stub answers both, the lock is free.

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
        if (str_contains($sql, "LOCK(CONCAT('saldi:tjek4opdat:'")) {
            // tjek4opdat()'s own update lock is free: GET_LOCK() gives it, RELEASE_LOCK() lets go of it.
            return ['laast' => '1'];
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

function db_escape_string($text)
{
    // Every connection here is PostgreSQL, also where db_type models MySQL.
    return pg_escape_string($GLOBALS['release511Registry'], (string) $text);
}

function db_fetch_array($result)
{
    return is_array($result) || $result === false ? $result : pg_fetch_array($result);
}

function db_modify($sql, $location, $global = false)
{
    if ($GLOBALS['release511Failure'] !== null && str_contains($sql, $GLOBALS['release511Failure'])) {
        throw new RuntimeException('Simulated migration failure');
    }
    $GLOBALS['release511Writes'][] = [$global, $sql];
    db_select($sql, $location, $global);
}
