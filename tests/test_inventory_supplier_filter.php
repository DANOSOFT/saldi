<?php
// 20260921 CDX/LUI Exercise actual inventory supplier filtering with missing and restricted saved selections.
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
$source = file_get_contents(__DIR__ . '/../lager/varer.php');
$start = strpos($source, 'if ($lev_kto || $lev_navn) {');
$end = strpos($source, "\t\$x=0;\n\t\$lagergrupper", $start);
$rowStart = strpos($source, '// Her frasorteres varer som ikke kommer fra den valgte lev.');
$rowEnd = strpos($source, '// Her frasorteres varer i bestillingsforslag', $rowStart);
if ($start === false || $end === false || $rowStart === false || $rowEnd === false) {
    throw new RuntimeException('Production supplier-filter anchors changed');
}
// Transport is isolated; both supplier list construction and row predicates
// come directly from varer.php rather than a reimplementation of the repair.
eval('function filterInventory($selection, $productId, $suggestion = false) {
    $vis_K = $selection;
    $lev_kto = $lev_navn = null;
    $tmp = null;
    $makeSuggestion = $suggestion;
    $id = [$productId]; $v = 0; $z = 1; $vis1 = $vis2 = 1;
' . substr($source, $start, $end - $start) . "\n" . substr($source, $rowStart, $rowEnd - $rowStart) . '
    return [$vis1, $vis2, $z];
}');
$queries = [];
function db_select($sql, $where) {
    $GLOBALS['queries'][] = $sql;
    if (!preg_match('/^select distinct vare_id from vare_lev(?: where lev_id = \'([0-9]+)\'(?: or lev_id = \'([0-9]+)\')?)?\s*$/', $sql, $match)) {
        throw new RuntimeException('Unexpected query: ' . $sql);
    }
    $bindings = [10 => [1, 2], 20 => [2, 3]];
    $ids = [];
    foreach ($bindings as $supplier => $products) {
        if (!isset($match[1]) || $match[1] === '' || in_array((string)$supplier, array_slice($match, 1), true)) {
            $ids = array_merge($ids, $products);
        }
    }
    return (object)['rows' => array_map(fn($id) => ['vare_id' => $id], array_unique($ids))];
}
function db_fetch_array($result) { return array_shift($result->rows); }
function db_modify($sql, $where) { throw new RuntimeException('Supplier display must not write'); }
function checkFilter($selection, $visible, $message) {
    $actual = [];
    foreach ([1, 2, 3, 4] as $id) {
        [$show, $count, $position] = filterInventory($selection, $id);
        if ($show && $count) { $actual[] = $id; }
        if (!($show && $count) && $position !== 0) { throw new RuntimeException('Excluded normal row still counted'); }
    }
    if ($actual !== $visible) { throw new RuntimeException($message . ': ' . json_encode($actual)); }
    echo "PASS: $message\n";
}
checkFilter(null, [4], 'NULL selection is warning-free and retains no-supplier filtering');
checkFilter([], [4], 'Empty selection retains no-supplier filtering');
checkFilter([''], [4], 'Persisted blank selection retains no-supplier filtering');
checkFilter(['on'], [1, 2, 3, 4], 'Explicit all-suppliers setting retains every item');
checkFilter([null, '10'], [1, 2], 'Selected supplier excludes unrelated and unassigned items');
checkFilter([1 => '20'], [2, 3], 'Sparse selected-supplier array does not need an all flag');
checkFilter([null, '10', '20'], [1, 2, 3], 'Multiple supplier selection remains a union');
if (filterInventory(null, 1, true) !== [1, 0, 1] || filterInventory([null, '10'], 4, true) !== [1, 0, 1]) {
    throw new RuntimeException('Purchase suggestion counter/filter behavior changed');
}
echo "PASS: purchase-suggestion filtering keeps its separate visibility and counter semantics\n";
