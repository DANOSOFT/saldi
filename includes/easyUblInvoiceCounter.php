<?php
// 20261110 CDX/ASR Increment the invoice counter internally and atomically after tenant resolution.

/**
 * Counts an invoice for the tenant resolved by the EasyUBL company locator.
 * Uses db_modify's master-connection option without changing the tenant connection.
 *
 * @param string $tenant Trusted locator result, never a public endpoint parameter.
 * @return bool Whether the master database update succeeded.
 */
function easyubl_increment_invoice_counter(string $tenant): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $tenant)) {
        return false;
    }
    $tenantSql = db_escape_string($tenant);
    $result = db_modify(
        "UPDATE regnskab SET invoices = COALESCE(invoices, 0) + 1 WHERE db = '$tenantSql'",
        __FILE__ . ' line ' . __LINE__,
        true
    );
    return $result === true || (is_string($result) && str_starts_with($result, "0\t"));
}
