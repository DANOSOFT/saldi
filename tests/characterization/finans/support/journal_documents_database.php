<?php
// 20261103 CL/MMR SST-929 Run the queries of finans/kassekladde_includes/journalDocumentsFunc.php against an isolated in-memory database.

function db_select($sql, $source) {
    return $GLOBALS['journalDocumentsTestDb']->query($sql);
}

function db_fetch_array($statement) {
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function db_modify($sql, $source) {
    return $GLOBALS['journalDocumentsTestDb']->exec($sql);
}
