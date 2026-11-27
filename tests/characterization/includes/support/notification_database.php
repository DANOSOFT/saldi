<?php
// 20261105 CL/LH Run includes/notifications.php's SQL for NotificationUpsertRaceTest.
//                With $GLOBALS['notifConnection'] set it runs on that PostgreSQL connection, one connection per request.
//                With $GLOBALS['notifHold'] set as well, the request's first write runs in a transaction that is left open and its later statements are only collected in $GLOBALS['notifHeld'].
//                That is a request still busy with its insert: the row is not visible yet and the request has not gone on.
//                Without a connection the answers are scripted, as for MySQL in CI: GET_LOCK() answers from $GLOBALS['notifLockAnswers'] and no notification is stored.
//                Every statement is recorded in $GLOBALS['notifQueries'].

function notifFixtureRun($sql, $write) {
    $GLOBALS['notifQueries'][] = $sql;
    $connection = $GLOBALS['notifConnection'] ?? null;
    if ($connection === null) {
        if (stripos($sql, 'get_lock(') !== false) {
            return new ArrayIterator([['locked' => array_shift($GLOBALS['notifLockAnswers'])]]);
        }
        return new ArrayIterator([]);
    }
    if (!empty($GLOBALS['notifHold'])) {
        if (isset($GLOBALS['notifHeld'])) {
            $GLOBALS['notifHeld'][] = $sql;
            return false;
        }
        if ($write) {
            pg_query($connection, 'BEGIN');
            $GLOBALS['notifHeld'] = ['COMMIT'];
        }
    }
    $result = pg_query($connection, $sql);
    if ($result === false) {
        throw new RuntimeException('Fixture statement failed: ' . pg_last_error($connection));
    }
    return $result;
}

function db_select($sql, $source) {
    return notifFixtureRun($sql, false);
}

function db_modify($sql, $source) {
    notifFixtureRun($sql, true);
    return "0\tquery accepted";
}

function db_fetch_array($result) {
    if ($result instanceof \PgSql\Result) {
        return pg_fetch_assoc($result);
    }
    if ($result instanceof ArrayIterator && $result->valid()) {
        $row = $result->current();
        $result->next();
        return $row;
    }
    return false;
}

function db_escape_string($text) {
    return str_replace("'", "''", (string) $text);
}
