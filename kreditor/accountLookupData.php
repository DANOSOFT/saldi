<?php

ob_clean();
@session_start();
$s_id = session_id();

include_once('../includes/connect.php');
include_once('../includes/online.php');

// ─────────────────────────────────────────────
// Defaults and Parameters
// ─────────────────────────────────────────────
$sprog_id = $_GET['sprog_id'] ?? 1;
$fokus     = $_GET['fokus'] ?? 'kontonr';
$id        = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$page         = max(1, (int)($_GET['page'] ?? 1));
$rowsPerPage  = $_GET['rowsPerPage'] ?? 100;
$sort         = $_GET['sort'] ?? 'firmanavn';
$direction    = strtoupper($_GET['direction'] ?? 'ASC');

// Sanitize direction
$direction = ($direction === 'DESC') ? 'DESC' : 'ASC';

// ─────────────────────────────────────────────
// Filterable / Sortable Columns
// ─────────────────────────────────────────────
$validColumns = ['kontonr','firmanavn','addr1','addr2','postnr','bynavn','land','kontakt','tlf'];

if (!in_array($sort, $validColumns)) {
    $sort = 'firmanavn';
}

// ─────────────────────────────────────────────
// Pagination SQL clauses
// ─────────────────────────────────────────────
if ($rowsPerPage === 'all') {
    $limitClause  = '';
    $offsetClause = '';
    $rowsPerPage = 'all';
} else {
    $rowsPerPage = (int)$rowsPerPage;
    $offset = ($page - 1) * $rowsPerPage;

    $limitClause  = "LIMIT $rowsPerPage";
    $offsetClause = "OFFSET $offset";
}

// ─────────────────────────────────────────────
// WHERE Clauses
// ─────────────────────────────────────────────
$whereClauses = ["art = 'K'"];  // Only suppliers

// Loop over filterable columns and apply filters if set
foreach ($validColumns as $col) {
    if (!empty($_GET[$col])) {
        $value = db_escape_string($_GET[$col]);

        if ($col === 'kontonr') {
            $whereClauses[] = "$col = '$value'";
        } else {
            $whereClauses[] = "$col LIKE '%$value%'";
        }
    }
}

$whereSQL = count($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// ─────────────────────────────────────────────
// Total Rows for Pagination
// ─────────────────────────────────────────────
$countSQL = "SELECT COUNT(*) as total FROM adresser $whereSQL";
$countResult = db_select($countSQL, __FILE__ . " linje " . __LINE__);
$countRow = db_fetch_array($countResult);
$totalRows = $countRow['total'] ?? 0;

// ─────────────────────────────────────────────
// Fetch Data
// ─────────────────────────────────────────────
$dataSQL = "
    SELECT id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf
    FROM adresser
    $whereSQL
    ORDER BY $sort $direction
    $limitClause $offsetClause
";

$result = db_select($dataSQL, __FILE__ . " linje " . __LINE__);
$data = [];

while ($row = db_fetch_array($result)) {
    $row['kontonr'] = str_replace(' ', '', $row['kontonr']); // Clean kontonr
    $data[] = $row;
}

// ─────────────────────────────────────────────
// Output JSON
// ─────────────────────────────────────────────
ob_end_clean();
header('Content-Type: application/json');

echo json_encode([
    'totalRows'    => $totalRows,
    'page'         => (int)$page,
    'rowsPerPage'  => $rowsPerPage,
    'sort'         => $sort,
    'direction'    => $direction,
    'fokus'        => $fokus,
    'id'           => $id,
    'data'         => $data
]);
exit;
