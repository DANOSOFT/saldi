<?php
// 20260916 CDX/LH Cover grid sort validation and the inventory DISTINCT query regression.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
require_once __DIR__ . '/../../includes/grid.php';
$columns = [];
foreach (['varegruppe' => 'vg.beskrivelse', 'stregkode' => 'v.stregkode', 'leverandør' => 'ol.lev', 'lager_total' => 'COALESCE(lt.lager_total, 0)', 'momspris' => 'momspris', 'kostpris' => 'v.kostpris', 'dg' => 'dg'] as $field => $sql) {
    $columns[] = ['field' => $field, 'sqlOverride' => $sql, 'sortable' => true];
    foreach (['asc', 'desc'] as $direction) {
        $expected = $sql . ' ' . $direction . ($direction === 'desc' ? ' NULLS LAST' : '');
        if (apply_sort_sqlOverride("$field $direction", $columns) !== $expected) throw new RuntimeException("Incorrect sort: $field $direction");
    }
}
foreach (['missing_column', 'dg; DROP TABLE varer', ['dg'], null, ''] as $invalid) {
    if (apply_sort_sqlOverride($invalid, $columns) !== '1') throw new RuntimeException('Invalid sort accepted');
}
echo "PASS: 14 supported sorts and 5 malformed/unknown sorts\n";
