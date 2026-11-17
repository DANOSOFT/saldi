<?php
// 20261117 CL/SZ MB-163 The master user's admin flag, read once for the Admin Panel.

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
