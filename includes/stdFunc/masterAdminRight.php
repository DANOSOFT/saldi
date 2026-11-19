<?php
// 20261117 CL/SZ MB-163 The master user's admin flag, read once for the Admin Panel.
// 20261117 CDX/NTR Share the escaped user lookup between the Admin Panel and its navigation link.

/**
 * Look up a user's admin rights in the current master-database connection.
 * Call only after verifying that the current database is the master database.
 * @param string $username Unescaped username from the authenticated session.
 * @return bool False when the user is missing or has no admin flag.
 */
function masterUserHasAdminRights(string $username): bool
{
    $username = db_escape_string($username);
    $adminRow = db_fetch_array(db_select("SELECT rettigheder FROM brugere WHERE brugernavn = '$username'", __FILE__ . " linje " . __LINE__));
    return masterUserIsAdmin(ifset($adminRow, 'rettigheder', null));
}

/**
 * Whether a master-database user has admin rights.
 * The master brugere.rettigheder column holds "admin,oprette,slette,adgang_til"; admin is 'on' when ticked
 * (admin/admin_brugere.php) and empty otherwise. Read the same way as admin/slet_regnskab.php.
 * @param string|null $rettigheder The stored rights, or null when the user has no row.
 * @return bool True only when the first field is set.
 */
function masterUserIsAdmin(?string $rettigheder): bool
{
    $fields = explode(',', (string) $rettigheder, 2);
    return (bool) $fields[0];
}
