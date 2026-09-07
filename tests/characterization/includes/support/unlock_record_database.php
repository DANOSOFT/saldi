<?php
// 20260907 CDX/LH Execute unlock statements against disposable in-memory fixture tables.

/**
 * @return int Number of rows affected in the isolated fixture database.
 */
function db_modify(string $query, string $source): int
{
    return $GLOBALS['unlockRecordTestDb']->exec($query);
}
