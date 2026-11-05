<?php
// 20261105 CDX/NTR Isolated SQL adapters for journal approval regression tests.
// 20261105 CDX/NTR Document SQL adapters and deliberately limited date, translation, and VAT fixtures.

namespace JournalApprovalFixture;

/**
 * Run fixture SELECTs, removing PostgreSQL/MySQL row-lock syntax only for SQLite.
 *
 * @param string $sql Query from the production helper.
 * @param string $trace Production diagnostic context, unused by the adapter.
 * @return \PDOStatement Fixture query result.
 */
function db_select($sql, $trace) {
    $db = $GLOBALS['journalApprovalDb'];
    if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $sql = str_ireplace(' for update', '', $sql);
    }
    return $db->query($sql);
}
/**
 * Fetch the next fixture row using the associative shape expected by production helpers.
 *
 * @param \PDOStatement $query Fixture result.
 * @return array<string, mixed>|false Stored row, or false when exhausted.
 */
function db_fetch_array($query) {
    return $query->fetch(\PDO::FETCH_ASSOC);
}
/**
 * Escape a literal using the active fixture driver, without the surrounding SQL quotes.
 *
 * @param mixed $value Value to quote.
 * @return string Escaped literal content.
 */
function db_escape_string($value) {
    return substr($GLOBALS['journalApprovalDb']->quote((string)$value), 1, -1);
}
/**
 * Execute a fixture write or inject the SD-595 failure flag for the journal move.
 *
 * @param string $sql Statement from the production helper.
 * @param string $trace Production diagnostic context, unused by the adapter.
 * @return string Tab-separated result code and diagnostic message.
 */
function db_modify($sql, $trace) {
    if (!empty($GLOBALS['journalApprovalFailMove']) && str_starts_with($sql, 'update kassekladde set kladde_id')) {
        $GLOBALS['db_modify_fejl'] = true;
        return "1\tfixture failure";
    }
    $GLOBALS['journalApprovalDb']->exec($sql);
    return "0\tquery accepted";
}
/**
 * Map the application's transaction commands to the active fixture connection.
 *
 * @param string $action begin, rollback, or commit.
 * @return void
 */
function transaktion($action) {
    $db = $GLOBALS['journalApprovalDb'];
    if ($action === 'begin') $db->beginTransaction();
    elseif ($action === 'rollback') $db->rollBack();
    else $db->commit();
}
/**
 * Encode audit details without loading the production audit database/bootstrap.
 *
 * @param array<string, mixed> $details Event details assembled by the posting helper.
 * @return string|false JSON details, or false on an encoding error.
 */
function audit_log_details_json($details) {
    return json_encode($details);
}
/**
 * Write the event in the same fixture transaction as the move so rollback is observable.
 *
 * @param string $action Event name.
 * @param string $type Production entity type, unused by this fixture.
 * @param string $id Production entity id, unused by this fixture.
 * @param string $details Encoded event details.
 * @return void
 */
function audit_log_write($action, $type, $id, $details) {
    $db = $GLOBALS['journalApprovalDb'];
    $db->exec('INSERT INTO approval_audit (action, details) VALUES (' . $db->quote($action) . ', ' . $db->quote($details) . ')');
}
/**
 * Use the supplied fallback text so tests do not need tenant translation tables.
 *
 * @param string $key Translation key followed by a pipe and fallback.
 * @param int $language Production language id, unused by this fixture.
 * @return string Fallback text.
 */
function findtekst($key, $language) {
    return explode('|', $key, 2)[1];
}
/**
 * Return a posted fixture value or the supplied default, matching the used subset of ifset().
 *
 * @param array<string, mixed> $values Input fields.
 * @param string $key Field to read.
 * @param mixed $default Value for a missing or null field.
 * @return mixed Selected value.
 */
function ifset($values, $key, $default = null) {
    return $values[$key] ?? $default;
}
/**
 * Convert the fixture's Danish date input to the SQL date used in posting values.
 *
 * @param string $date Valid date in d-m-Y format.
 * @return string Date in Y-m-d format.
 */
function usdate($date) {
    return \DateTime::createFromFormat('d-m-Y', $date)->format('Y-m-d');
}
/**
 * Convert valid Danish fixture amounts without loading the application bootstrap.
 *
 * @param string $amount Danish amount with optional thousands separators.
 * @return string Decimal amount with a dot separator.
 */
function usdecimal($amount) {
    return str_replace(',', '.', str_replace('.', '', $amount));
}
/**
 * Declare the VAT columns present in the temporary journal schema.
 *
 * @return bool Always true for this fixture.
 */
function poolVatColumnsReady() { return true; }
/**
 * Supply the single default VAT code used by the fixture account.
 *
 * @return array<int, string> Fixture VAT codes.
 */
function poolVatCodes() { return array('K1'); }
/**
 * Resolve the fixture's VAT account while leaving the bank account without a default.
 *
 * @param string $type Account type, unused by the fixture lookup.
 * @param string $account Account number.
 * @param int $year Fiscal year, unused by the fixture lookup.
 * @param array<int, string> $codes Available fixture codes, unused by the lookup.
 * @return string K1 for account 1610, otherwise an empty code.
 */
function poolVatAccountCode($type, $account, $year, $codes) { return $account === '1610' ? 'K1' : ''; }
/**
 * Model only exemption and blank-to-default VAT filling needed by approval tests.
 *
 * The VAT resolver itself is exercised by the separate document-pool tests.
 *
 * @param string $posted Requested VAT code.
 * @param string $default Account default code.
 * @param bool $exempt Whether the line is VAT exempt.
 * @param string $other Opposite-side VAT code, unused by this limited fixture.
 * @param array<int, string> $codes Available codes, unused by this limited fixture.
 * @return string Effective fixture VAT code.
 */
function poolVatChoose($posted, $default, $exempt, $other, $codes) { return $exempt ? '' : ($posted ?: $default); }
