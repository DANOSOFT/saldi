<?php
// 20261105 CL/LH Run includes/docsIncludes/poolContentHash.php's SQL on an isolated PostgreSQL fixture, one connection per request.
//                With $GLOBALS['poolHashHold'] set, the request's first write runs in a transaction that is left open and its later statements are only collected in $GLOBALS['poolHashHeld'].
//                That is a request still busy with that write: its effect is not visible yet and it has not gone on.

function poolHashFixtureRun($sql, $write) {
    $connection = $GLOBALS['poolHashConnection'];
    $GLOBALS['poolHashQueries'][] = $sql;
    if (!empty($GLOBALS['poolHashHold'])) {
        if (isset($GLOBALS['poolHashHeld'])) {
            $GLOBALS['poolHashHeld'][] = $sql;
            return false;
        }
        if ($write) {
            pg_query($connection, 'BEGIN');
            $GLOBALS['poolHashHeld'] = ['COMMIT'];
        }
    }
    $result = pg_query($connection, $sql);
    if ($result === false) throw new RuntimeException('Fixture statement failed: ' . pg_last_error($connection));
    return $result;
}

function db_select($sql, $source) {
    return poolHashFixtureRun($sql, false);
}

function db_fetch_array($result) {
    return $result instanceof \PgSql\Result ? pg_fetch_assoc($result) : false;
}

function db_modify($sql, $source) {
    poolHashFixtureRun($sql, true);
    return "0\tquery accepted";
}
