<?php
// 20261116 CL/MMR SST-962 Child process for BankImportColumnsTest: one bank_import_save_columns() call on its own connection, so two of them can race for the same user and number of columns.
//                 Arguments: database, schema, user id, last column index, comma-separated column names. Prints "saved" on success.

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
[, $database, $schema, $userId, $last, $columns] = $argv;
require_once __DIR__ . '/../../support/database/TestDatabase.php';
$GLOBALS['connection'] = TestDatabase::connect($database);
$GLOBALS['db_type'] = 'postgres';
$GLOBALS['db'] = $database;
pg_query($GLOBALS['connection'], 'SET search_path TO ' . pg_escape_identifier($GLOBALS['connection'], $schema));
require_once __DIR__ . '/bank_import_settings_database.php';
require_once __DIR__ . '/../../../../includes/std_func.php';
require_once __DIR__ . '/../../../../includes/stdFunc/bankImportColumns.php';
echo bank_import_save_columns((int)$userId, explode(',', $columns), (int)$last) ? "saved\n" : "not saved\n";
