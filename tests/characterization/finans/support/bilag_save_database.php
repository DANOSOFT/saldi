<?php
// 20261105 CDX/NTR Run the journal save against connection-local PostgreSQL fixtures.

/** @return PgSql\Result */
function db_select($sql, $source = '')
{
    $result = pg_query($GLOBALS['bilagSaveTestDb'], $sql);
    if ($result === false) {
        throw new RuntimeException(pg_last_error($GLOBALS['bilagSaveTestDb']));
    }
    return $result;
}

/** @return array<string, string|null>|false */
function db_fetch_array($result)
{
    return pg_fetch_assoc($result);
}

/** @return void */
function db_modify($sql, $source = '')
{
    db_select($sql, $source);
}

/** @return string */
function db_escape_string($value)
{
    return pg_escape_string($GLOBALS['bilagSaveTestDb'], (string)$value);
}

/** @return void */
function transaktion($action)
{
    db_select($action);
}
