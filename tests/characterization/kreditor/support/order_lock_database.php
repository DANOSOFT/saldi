<?php
// 20260908 CDX/LH Run creditor order-lock SQL against an explicitly configured isolated PostgreSQL fixture.

function db_select($sql, $source) {
    $GLOBALS['creditorLockQueries'][] = $sql;
    $result = pg_query($GLOBALS['creditorLockConnection'], $sql);
    if ($result === false) throw new RuntimeException('Fixture read failed: ' . pg_last_error($GLOBALS['creditorLockConnection']));
    return $result;
}

function db_fetch_array($result) {
    return pg_fetch_assoc($result);
}

function db_modify($sql, $source) {
    $GLOBALS['creditorLockQueries'][] = $sql;
    $result = pg_query($GLOBALS['creditorLockConnection'], $sql);
    if ($result === false) throw new RuntimeException('Fixture write failed: ' . pg_last_error($GLOBALS['creditorLockConnection']));
    return "0\tquery accepted";
}
