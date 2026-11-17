<?php
// 20261113 CDX/NTR Rename roles.system to is_built_in before permission helpers read built-in roles.

require_once __DIR__ . '/databaseDialect.php';

/**
 * Preserve built-in/custom role flags when upgrading the former system column.
 * Run before role seeding. The metadata check is scoped to the current tenant;
 * a database-scoped lock and a recheck serialize concurrent first logins.
 * @param string $dbType Connection driver from includes/connect.php.
 * @return void Already-upgraded and newly created tables need no change.
 */
function migrateRoleBuiltInFlag(string $dbType): void
{
    $oldColumn = "SELECT column_name FROM information_schema.columns WHERE table_name = 'roles' AND column_name = 'system'"
        . opdat_schema_filter($dbType);
    if (!db_fetch_array(db_select($oldColumn, __FILE__ . ' linje ' . __LINE__))) {
        return;
    }

    $mysql = opdat_is_mysql($dbType);
    if ($mysql) {
        $lock = "CONCAT('saldi:roles_builtin:', MD5(DATABASE()))";
        $acquired = db_fetch_array(db_select("SELECT GET_LOCK($lock, 30) AS acquired", __FILE__ . ' linje ' . __LINE__));
        if ((int) ($acquired['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Could not acquire the built-in role migration lock.');
        }
        $unlock = "SELECT RELEASE_LOCK($lock)";
    } else {
        // Reentrant when betweenUpdates.php already holds this session lock.
        db_select("SELECT pg_advisory_lock(hashtext('saldi_roles_migration'))", __FILE__ . ' linje ' . __LINE__);
        $unlock = "SELECT pg_advisory_unlock(hashtext('saldi_roles_migration'))";
    }

    try {
        if (!db_fetch_array(db_select($oldColumn, __FILE__ . ' linje ' . __LINE__))) {
            return;
        }
        $newColumn = "SELECT column_name FROM information_schema.columns WHERE table_name = 'roles' AND column_name = 'is_built_in'"
            . opdat_schema_filter($dbType);
        if (db_fetch_array(db_select($newColumn, __FILE__ . ' linje ' . __LINE__))) {
            throw new RuntimeException('Both roles.system and roles.is_built_in exist; cannot rename the role flag safely.');
        }
        // CHANGE COLUMN also supports MySQL/MariaDB releases predating RENAME COLUMN.
        $query = $mysql
            ? 'ALTER TABLE roles CHANGE COLUMN `system` is_built_in boolean DEFAULT false'
            : 'ALTER TABLE roles RENAME COLUMN "system" TO is_built_in';
        db_modify($query, __FILE__ . ' linje ' . __LINE__);
    } finally {
        db_select($unlock, __FILE__ . ' linje ' . __LINE__);
    }
}
