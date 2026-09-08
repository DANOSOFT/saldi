<?php
// 20260908 CDX/LH Execute creditor posting source blocks against isolated PostgreSQL fixtures.

function db_select($query, $location)
{
    return pg_query($GLOBALS['batchPostingDb'], $query);
}

function db_fetch_array($result)
{
    return pg_fetch_assoc($result);
}

function db_modify($query, $location)
{
    $result = pg_query($GLOBALS['batchPostingDb'], $query);
    $GLOBALS['batchPostingUpdates']++;
    $GLOBALS['batchPostingAffected'] += pg_affected_rows($result);
    return $result;
}

function transaktion($command)
{
    $GLOBALS['batchPostingTransactions'][] = $command;
    return pg_query($GLOBALS['batchPostingDb'], $command);
}
