<?php
// 20260907 CDX/LH Execute the journal history queries against an isolated in-memory database.

function db_select($sql, $source) {
    return $GLOBALS['journalHistoryTestDb']->query($sql);
}

function db_fetch_array($statement) {
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function db_escape_string($value) {
    return str_replace("'", "''", (string)$value);
}

function dkdato($date) {
    return date('d-m-Y', strtotime($date));
}

function findtekst($text, $language) {
    return explode('|', $text, 2)[1];
}
