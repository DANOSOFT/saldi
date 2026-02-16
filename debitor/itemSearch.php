<?php

ob_start();

@session_start();
$s_id = session_id();
$title = "itemSearch"; 
$modulnr = 0;  
$bg = "nix";   
$header = "nix";
$webservice = true; 

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// VAT parameters for price display
$incl_moms = isset($_GET['incl_moms']) ? $_GET['incl_moms'] : '';
$momssats = isset($_GET['momssats']) ? floatval(str_replace(',', '.', $_GET['momssats'])) : 25;

// If incl_moms is not provided via GET, check the system setting for VAT on orders
if ($incl_moms === '' || $incl_moms === null) {
    $vatPrivateCustomers = get_settings_value("vatPrivateCustomers", "ordre", "");
    if ($vatPrivateCustomers === 'on') {
        $incl_moms = 'on';
    }
}

if (!isset($regnaar) || empty($regnaar)) {
    echo json_encode(array('error' => 'Session expired'));
    exit;
}

// Get VAT-free product groups
$momsfri_grupper = array();
$q = db_select("SELECT kodenr FROM grupper WHERE art='VG' AND box7 = 'on' AND fiscal_year = '$regnaar'", __FILE__ . " line " . __LINE__);
while ($r = db_fetch_array($q)) {
    $momsfri_grupper[] = $r['kodenr'];
}

$results = array();
$totalCount = 0;
$sql_error = null;

$search_escaped = db_escape_string($search);

// Check if beskrivelse_alias column exists, create if not
$has_beskrivelse_alias = false;
$colCheck = db_select("SELECT column_name FROM information_schema.columns WHERE table_name='varer' AND column_name='beskrivelse_alias'", __FILE__ . " line " . __LINE__);
if ($colCheck && db_fetch_array($colCheck)) {
    $has_beskrivelse_alias = true;
} else {
    // Try to create the column
    db_modify("ALTER table varer ADD column beskrivelse_alias VARCHAR(255)", __FILE__ . " line " . __LINE__);
    $has_beskrivelse_alias = true;
}

// Also check varenr_alias column
$colCheck2 = db_select("SELECT column_name FROM information_schema.columns WHERE table_name='varer' AND column_name='varenr_alias'", __FILE__ . " line " . __LINE__);
if (!$colCheck2 || !db_fetch_array($colCheck2)) {
    db_modify("ALTER table varer ADD column varenr_alias VARCHAR(255)", __FILE__ . " line " . __LINE__);
}

$baseWhere = "(lukket IS NULL OR lukket != '1')";

if ($search !== '') {
    $words = explode(' ', $search_escaped);
    foreach ($words as $word) {
        if (trim($word) === '') continue;
        $searchCondition = "(varenr ILIKE '%$word%' OR COALESCE(varenr_alias,'') ILIKE '%$word%' OR beskrivelse ILIKE '%$word%' OR COALESCE(stregkode,'') ILIKE '%$word%' OR COALESCE(trademark,'') ILIKE '%$word%')";
        if ($has_beskrivelse_alias) {
            $searchCondition = "(varenr ILIKE '%$word%' OR COALESCE(varenr_alias,'') ILIKE '%$word%' OR beskrivelse ILIKE '%$word%' OR COALESCE(beskrivelse_alias,'') ILIKE '%$word%' OR COALESCE(stregkode,'') ILIKE '%$word%' OR COALESCE(trademark,'') ILIKE '%$word%')";
        }
        $baseWhere .= " AND " . $searchCondition;
    }
}

$countQuery = db_select("SELECT COUNT(*) as cnt FROM varer WHERE $baseWhere", __FILE__ . " line " . __LINE__);
if ($countQuery) {
    $countRow = db_fetch_array($countQuery);
    $totalCount = intval($countRow['cnt']);
}

// Include gruppe (product group) in query for VAT-free check
$qtxt = "SELECT id, varenr, beskrivelse, salgspris, kostpris, enhed, beholdning, gruppe 
         FROM varer 
         WHERE $baseWhere
         ORDER BY varenr ASC LIMIT $limit OFFSET $offset";

$query = db_select($qtxt, __FILE__ . " line " . __LINE__);

if ($query) {
    while ($row = db_fetch_array($query)) {
        $salgspris = floatval($row['salgspris']);
        
        // Calculate price with VAT if incl_moms is enabled and product is not VAT-free
        if ($incl_moms && $incl_moms !== '' && $incl_moms !== '0') {
            $gruppe = $row['gruppe'];
            if (!in_array($gruppe, $momsfri_grupper)) {
                $salgspris = $salgspris + ($salgspris * $momssats / 100);
            }
        }
        
        $results[] = array(
            'id' => $row['id'],
            'varenr' => trim($row['varenr']),
            'beskrivelse' => trim(stripslashes($row['beskrivelse'])),
            'salgspris' => $salgspris,
            'kostpris' => floatval($row['kostpris']),
            'enhed' => trim($row['enhed']),
            'beholdning' => floatval($row['beholdning'])
        );
    }
}

$response = array(
    'results' => $results,
    'pagination' => array(
        'page' => $page,
        'limit' => $limit,
        'total' => $totalCount,
        'hasMore' => ($offset + count($results)) < $totalCount
    )
);

echo json_encode($response);
exit;
?>
