<?php
// kreditor/accountLookupData.php
// 20260304 LOE Updated to work with grid framework (same pattern as debitor/accountLookupData.php)

header('Content-Type: application/json');

try {
    @session_start();
    $s_id = session_id();

    include_once('../includes/connect.php');
    include_once('../includes/online.php');
    global $bruger_id;

    if (!function_exists('db_escape_string')) {
        throw new Exception('Database functions not loaded');
    }

    // Get grid ID from request
    $grid_id = $_REQUEST['grid_id'] ?? 'kreditor_lookup';

    // Get all request parameters
    $requestParams = [
        'sprog_id' => $_REQUEST['sprog_id'] ?? 1,
        'fokus' => $_REQUEST['fokus'] ?? 'kontonr',
        'id' => isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0,
        'sort' => $_REQUEST['sort'][$grid_id] ?? '',
        'rowcount' => $_REQUEST['rowcount'][$grid_id] ?? null,
        'offset' => isset($_REQUEST['offset'][$grid_id]) ? (int)$_REQUEST['offset'][$grid_id] : 0,
        'search' => $_REQUEST['search'][$grid_id] ?? [],
        'menu' => $_REQUEST['menu'][$grid_id] ?? 'main',
        'o_art' => $_REQUEST['o_art'] ?? null,
        'direction' => $_REQUEST['direction'] ?? 'ASC',
        'ajax'  => $_REQUEST['ajax']  ?? '0',
        'clear' => $_REQUEST['clear'] ?? '0',
    ];

    $isClear = $requestParams['clear'] === '1';

    if ($isClear) {
        db_modify("UPDATE datatables SET search_setup = '{}' 
                WHERE tabel_id = '" . db_escape_string($grid_id) . "' 
                AND user_id = '$bruger_id'",
                __FILE__ . " linje " . __LINE__);
    }

    $hasSearchParams = !empty(array_filter($requestParams['search']));
    $shouldModifyDatabase = false;

    // Valid columns for sorting and searching
    $validColumns = ['kontonr','firmanavn','addr1','addr2','postnr','bynavn','land','kontakt','tlf'];

    // Handle sort parameter
    $sortParam = $requestParams['sort'];
    if (!empty($sortParam)) {
        $sortParts = explode(' ', $sortParam);
        $sort = in_array($sortParts[0] ?? '', $validColumns) ? $sortParts[0] : 'firmanavn';
        $direction = (isset($sortParts[1]) && strtoupper($sortParts[1]) === 'DESC') ? 'DESC' : 'ASC';
    } else {
        $direction = $requestParams['direction'] === 'DESC' ? 'DESC' : 'ASC';
        $sort = in_array($_REQUEST['sort'] ?? '', $validColumns) ? $_REQUEST['sort'] : 'firmanavn';
    }

    // Handle rowcount
    $ss = $_REQUEST['rowcount'][$grid_id] ?? null;
    $rowcount = $requestParams['rowcount'];
    if (is_numeric($ss) && $ss > 1200) {
        $rowcount = 1200;
    }
    if ($rowcount !== null) {
        $shouldModifyDatabase = true;
        db_modify("UPDATE datatables SET rowcount = '" . db_escape_string($rowcount) . "' 
                   WHERE tabel_id = '" . db_escape_string($grid_id) . "' AND user_id = '$bruger_id'", 
                   __FILE__ . " linje " . __LINE__);
    } else {
        $q = "SELECT rowcount FROM datatables WHERE tabel_id = '$grid_id' AND user_id = '$bruger_id' LIMIT 1";
        $r = db_fetch_array(db_select($q, __FILE__ . " linje " . __LINE__));
        $rowcount = $r['rowcount'] ?? 100;
    }

    // Handle offset
    $offset = $requestParams['offset'];
    if ($shouldModifyDatabase) {
        db_modify("UPDATE datatables SET \"offset\" = $offset 
                   WHERE tabel_id = '" . db_escape_string($grid_id) . "'", 
                   __FILE__ . " linje " . __LINE__);
    }

    // Handle "show all"
    $showAll = ($rowcount == 'Alle' || $rowcount == '999999999' || $rowcount === 'all');
    if ($showAll) {
        $rowsPerPageInt = 999999999;
        $limitClause = 'LIMIT 999999999';
        $offsetClause = '';
        $offset = 0;
    } else {
        $rowsPerPageInt = max(1, (int)$rowcount);
        $limitClause = "LIMIT $rowsPerPageInt";
        $offsetClause = $offset > 0 ? "OFFSET $offset" : '';
    }

    // Handle search parameters - only creditors (art = 'K')
    $searchParams = $requestParams['search'];
    $whereClauses = ["art = 'K'", "lukket != 'on' OR lukket IS NULL"]; 


    foreach ($validColumns as $col) {
        if (!empty($searchParams[$col])) {
            $value = db_escape_string($searchParams[$col]);
            $whereClauses[] = "CAST($col AS TEXT) ILIKE '%$value%'";
        }
    }

    // Support direct parameters for backward compatibility
    foreach ($validColumns as $col) {
        if (!empty($_REQUEST[$col]) && empty($searchParams[$col])) {
            $value = db_escape_string($_REQUEST[$col]);
            $whereClauses[] = "CAST($col AS TEXT) ILIKE '%$value%'";
        }
    }

    $where = count($whereClauses) > 0 ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

    // Count total rows
    $countSql = "SELECT COUNT(*) as total FROM adresser $where";
    $countResult = db_select($countSql, __FILE__ . " linje " . __LINE__);
    $countRow = db_fetch_array($countResult);
    $totalRows = (int)($countRow['total'] ?? 0);

    // Main query
    $sql = "SELECT id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf
            FROM adresser
            $where
            ORDER BY $sort $direction
            $limitClause $offsetClause";

    $result = db_select($sql, __FILE__ . " linje " . __LINE__);

    $data = [];
    while ($row = db_fetch_array($result)) {
        $data[] = $row;
    }

    // Clear any output buffers before JSON
    while (ob_get_level()) {
        ob_end_clean();
    }

    echo json_encode([
        'success' => true,
        'totalRows' => $totalRows,
        'rowsPerPage' => $rowsPerPageInt,
        'offset' => $offset,
        'currentCount' => count($data),
        'data' => $data,
        'requestParams' => $requestParams,
        'queryInfo' => [
            'sort' => "$sort $direction",
            'where' => $where,
            'searchParams' => $searchParams,
            'hasSearch' => $hasSearchParams,
            'modifiedDatabase' => $shouldModifyDatabase
        ]
    ]);

} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
