<?php
// kontoopslag_data.php

ob_clean();
@session_start();
$s_id = session_id();

include_once('../includes/connect.php');
include_once('../includes/online.php');

// Get parameters with defaults
$sprog_id = $_GET['sprog_id'] ?? 1;

$sort = $_GET['sort'] ?? 'kontonr';
$page = max(1, (int)($_GET['page'] ?? 1));
$fokus = $_GET['fokus'] ?? 'kontonr';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rowsPerPage = max(1, (int)($_GET['rowsPerPage'] ?? 100));

// Allowed columns for sorting and filtering
$validColumns = ['kontonr','firmanavn','addr1','addr2','postnr','bynavn','land','kontakt','tlf'];
if (!in_array($sort, $validColumns)) {
    $sort = 'firmanavn';
}

// Base where clause
$whereClauses = ["art IN ('D', 'K')", "lukket != 'on'"];

// Loop over all filterable columns and add conditions if present in $_GET
foreach ($validColumns as $col) {
    if (!empty($_GET[$col])) {
        $value = db_escape_string($_GET[$col]);
        if ($col === 'kontonr') {
            // Exact match for kontonr, if that is the desired behavior
            $whereClauses[] = "$col = '$value'";
        } else {
            // Partial LIKE match for other columns
            $whereClauses[] = "$col LIKE '%$value%'";
        }
    }
}

// Combine all WHERE parts
$where = '';
if (count($whereClauses) > 0) {
    $where = ' WHERE ' . implode(' AND ', $whereClauses);
}

// Count total matching rows for pagination
$countSql = "SELECT COUNT(*) as total FROM adresser $where";
$countResult = db_select($countSql, __FILE__ . " linje " . __LINE__);
$countRow = db_fetch_array($countResult);
$totalRows = $countRow['total'] ?? 0;

$offset = ($page - 1) * $rowsPerPage;

// Fetch data rows with limit, order, and offset
$sql = "SELECT id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf
        FROM adresser
        $where
        ORDER BY $sort
        LIMIT $rowsPerPage OFFSET $offset";

$result = db_select($sql, __FILE__ . " linje " . __LINE__);

$data = [];
while ($row = db_fetch_array($result)) {
    $data[] = $row;
}

error_log("Account lookup: sort=$sort, page=$page, rowsPerPage=$rowsPerPage, totalRows=$totalRows, Focus: $fokus, ID: $id");  

ob_end_clean();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'totalRows' => $totalRows,
    'page' => $page,
    'rowsPerPage' => $rowsPerPage,
    'fokus' => $fokus,
    'id' => $id,
    'data' => $data,
]);

