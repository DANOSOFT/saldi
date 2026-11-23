<?php
// 20261113 CDX/NTR Share database dialect expressions and metadata queries used by update scripts.

/**
 * Whether an update uses the MySQL/MariaDB dialect (both legacy driver names).
 * @param string $dbType Connection driver from includes/connect.php.
 * @return bool True for mysql and mysqli; the other update dialect is PostgreSQL.
 */
function opdat_is_mysql(string $dbType): bool
{
    return in_array($dbType, ['mysql', 'mysqli'], true);
}

/**
 * Restrict a metadata query to the current tenant's database/schema.
 * @param string $dbType Connection driver.
 * @param string $schemaColumn Metadata column: table_schema or schemaname (pg_indexes).
 * @return string SQL AND clause suitable for appending to a metadata query's WHERE clause.
 */
function opdat_schema_filter(string $dbType, string $schemaColumn = 'table_schema'): string
{
    if (!in_array($schemaColumn, ['table_schema', 'schemaname'], true)) {
        throw new InvalidArgumentException('Unsupported metadata schema column.');
    }
    return ' AND ' . $schemaColumn . ' = ' . (opdat_is_mysql($dbType) ? 'DATABASE()' : 'current_schema()');
}

/**
 * Definition of the generated integer primary key named id in an update-created table.
 * @param string $dbType Connection driver.
 * @return string Complete id column definition, without a trailing comma.
 */
function opdat_id_column(string $dbType): string
{
    return opdat_is_mysql($dbType) ? 'id INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'id SERIAL PRIMARY KEY NOT NULL';
}

/**
 * Timestamp type and current-time default for an update-created column.
 * @param string $dbType Connection driver.
 * @param bool $notNull Whether the column explicitly refuses NULL.
 * @return string Column definition after its name, without a trailing comma.
 */
function opdat_timestamp_column(string $dbType, bool $notNull = false): string
{
    $mysql = opdat_is_mysql($dbType);
    return ($mysql ? 'DATETIME' : 'TIMESTAMP') . ($notNull ? ' NOT NULL' : '')
        . ' DEFAULT ' . ($mysql ? 'CURRENT_TIMESTAMP' : 'now()');
}

/**
 * Look up an index by table and name in the current tenant's metadata.
 * @param string $dbType Connection driver.
 * @param string $table Table name, escaped as a metadata value rather than an SQL identifier.
 * @param string $index Index name, escaped as a metadata value rather than an SQL identifier.
 * @return string SELECT query; a returned row means that the index exists.
 */
function opdat_index_query(string $dbType, string $table, string $index): string
{
    $table = db_escape_string($table);
    $index = db_escape_string($index);
    if (opdat_is_mysql($dbType)) {
        return "SELECT index_name FROM information_schema.statistics WHERE table_name = '$table' AND index_name = '$index'"
            . opdat_schema_filter($dbType);
    }
    return "SELECT indexname FROM pg_indexes WHERE tablename = '$table' AND indexname = '$index'"
        . opdat_schema_filter($dbType, 'schemaname');
}

/**
 * Convert a legacy text column to nullable integer, turning blank strings into NULL.
 * The caller checks the existing type and executes these statements in order.
 * @param string $dbType Connection driver.
 * @param string $table Unqualified update-owned table name (letters, digits and underscores).
 * @param string $column Update-owned column name (letters, digits and underscores).
 * @return list<string> Ordered SQL statements, preserving nonblank integer values and removing the old default.
 */
function opdat_nullable_integer_queries(string $dbType, string $table, string $column): array
{
    foreach ([$table, $column] as $identifier) {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $identifier)) {
            throw new InvalidArgumentException('Unsupported migration identifier.');
        }
    }
    if (opdat_is_mysql($dbType)) {
        return [
            "UPDATE `$table` SET `$column` = NULL WHERE TRIM(`$column`) = ''",
            "ALTER TABLE `$table` MODIFY `$column` integer NULL DEFAULT NULL",
        ];
    }
    return [
        "ALTER TABLE \"$table\" ALTER \"$column\" TYPE integer USING NULLIF(trim(\"$column\"), '')::integer,"
            . " ALTER \"$column\" DROP DEFAULT, ALTER \"$column\" DROP NOT NULL",
    ];
}
