<?php
// 20261105 CDX/NTR Isolated SQL adapters for journal approval regression tests.

namespace JournalApprovalFixture;

function db_select($sql, $trace) {
    $db = $GLOBALS['journalApprovalDb'];
    if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $sql = str_ireplace(' for update', '', $sql);
    }
    return $db->query($sql);
}
function db_fetch_array($query) {
    return $query->fetch(\PDO::FETCH_ASSOC);
}
function db_escape_string($value) {
    return substr($GLOBALS['journalApprovalDb']->quote((string)$value), 1, -1);
}
function db_modify($sql, $trace) {
    if (!empty($GLOBALS['journalApprovalFailMove']) && str_starts_with($sql, 'update kassekladde set kladde_id')) {
        $GLOBALS['db_modify_fejl'] = true;
        return "1\tfixture failure";
    }
    $GLOBALS['journalApprovalDb']->exec($sql);
    return "0\tquery accepted";
}
function transaktion($action) {
    $db = $GLOBALS['journalApprovalDb'];
    if ($action === 'begin') $db->beginTransaction();
    elseif ($action === 'rollback') $db->rollBack();
    else $db->commit();
}
function audit_log_details_json($details) {
    return json_encode($details);
}
function audit_log_write($action, $type, $id, $details) {
    $db = $GLOBALS['journalApprovalDb'];
    $db->exec('INSERT INTO approval_audit (action, details) VALUES (' . $db->quote($action) . ', ' . $db->quote($details) . ')');
}
function findtekst($key, $language) {
    return explode('|', $key, 2)[1];
}
function ifset($values, $key, $default = null) {
    return $values[$key] ?? $default;
}
function usdate($date) {
    return \DateTime::createFromFormat('d-m-Y', $date)->format('Y-m-d');
}
function usdecimal($amount) {
    return str_replace(',', '.', str_replace('.', '', $amount));
}
function poolVatColumnsReady() { return true; }
function poolVatCodes() { return array('K1'); }
function poolVatAccountCode($type, $account, $year, $codes) { return $account === '1610' ? 'K1' : ''; }
function poolVatChoose($posted, $default, $exempt, $other, $codes) { return $exempt ? '' : ($posted ?: $default); }
