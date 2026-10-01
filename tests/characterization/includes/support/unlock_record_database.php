<?php
// 20260907 CDX/LH Execute unlock statements against disposable in-memory fixture tables.
// 20260908 SZ SST-755: stub db_escape_string() too - unlock_record() now calls it whenever
//                  a $brugernavn/$tidspkt match condition is passed in.
// 20260924 SZ SST-755 (CodeRabbit): stub db_select()/db_fetch_array() too -
//                  lock_token_column_exists() now calls them before every unlock_record()/
//                  refresh_lock_token(). Controlled via $GLOBALS['unlockRecordLockTokenColumnExists']
//                  (default true, so existing fixtures behave exactly as before); a dedicated
//                  test sets it false to exercise the pre-migration degrade path.

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

if (!function_exists('db_select')) {
    function db_select(string $query, string $source) {
        if (str_contains($query, "column_name = 'lock_token'")) {
            return ($GLOBALS['unlockRecordLockTokenColumnExists'] ?? true) ? 'has-column' : false;
        }
        return false;
    }
}

if (!function_exists('db_fetch_array')) {
    function db_fetch_array($result) {
        return $result ? ['column_name' => 'lock_token'] : false;
    }
}
