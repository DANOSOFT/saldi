<?php
// 20260907 CDX/LH Execute unlock statements against disposable in-memory fixture tables.
// 20260908 SZ SST-755: stub db_escape_string() too - unlock_record() now calls it whenever
//                  a $brugernavn/$tidspkt match condition is passed in.

/**
 * @return int Number of rows affected in the isolated fixture database.
 */
function db_modify(string $query, string $source): int
{
    return $GLOBALS['unlockRecordTestDb']->exec($query);
}

if (!function_exists('db_escape_string')) {
    function db_escape_string(string $text): string
    {
        return str_replace("'", "''", $text);
    }
}
