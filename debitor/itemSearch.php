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
$konto_id = isset($_GET['konto_id']) ? intval($_GET['konto_id']) : 0;
$kreditor_order = isset($_GET['kreditor_order']) ? intval($_GET['kreditor_order']) : 0;
$search_field = isset($_GET['search_field']) ? $_GET['search_field'] : 'varenr';
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

$baseWhere = "(varer.lukket IS NULL OR varer.lukket != '1')";

if ($search !== '') {
    $words = explode(' ', $search_escaped);
    foreach ($words as $word) {
        if (trim($word) === '') continue;
        if ($search_field === 'lev_varenr') {
            $searchCondition = "vl.lev_varenr ILIKE '%$word%'";
        } else {
            $searchCondition = "(varer.varenr ILIKE '%$word%' OR COALESCE(varer.varenr_alias,'') ILIKE '%$word%' OR varer.beskrivelse ILIKE '%$word%' OR COALESCE(varer.stregkode,'') ILIKE '%$word%' OR COALESCE(varer.trademark,'') ILIKE '%$word%' OR COALESCE(vv.variant_stregkode,'') ILIKE '%$word%' OR COALESCE(vv.variant_text,'') ILIKE '%$word%')";
            if ($has_beskrivelse_alias) {
                $searchCondition = "(varer.varenr ILIKE '%$word%' OR COALESCE(varer.varenr_alias,'') ILIKE '%$word%' OR varer.beskrivelse ILIKE '%$word%' OR COALESCE(varer.beskrivelse_alias,'') ILIKE '%$word%' OR COALESCE(varer.stregkode,'') ILIKE '%$word%' OR COALESCE(varer.trademark,'') ILIKE '%$word%' OR COALESCE(vv.variant_stregkode,'') ILIKE '%$word%' OR COALESCE(vv.variant_text,'') ILIKE '%$word%')";
            }
        }
        $baseWhere .= " AND " . $searchCondition;
    }
}

// 20260728 MJ Fix: kreditor autocomplete returnerede varer.kostpris i stedet for leverandoerspecifik vl.kostpris
$leverandorJoin = '';
$levKostprisSelect = 'varer.kostpris';
$levDetailsSelect = '';
if ($konto_id > 0) {
    if ($kreditor_order) {
        $vareLevJoinType = ($search_field === 'lev_varenr') ? 'INNER JOIN' : 'LEFT JOIN';  
        $leverandorJoin = $vareLevJoinType . " vare_lev vl ON vl.vare_id = varer.id AND vl.lev_id = $konto_id LEFT JOIN adresser a ON a.id = vl.lev_id";
        $levKostprisSelect = "COALESCE(vl.kostpris, varer.kostpris)";
        $levDetailsSelect = ", COALESCE(vl.lev_id, 0) as lev_id, COALESCE(a.kontonr, '') as lev_kontonr, COALESCE(a.firmanavn, '') as lev_firmanavn";
    } else {
        $leverandorJoin = "INNER JOIN vare_lev vl ON vl.vare_id = varer.id AND vl.lev_id = $konto_id";
    }
} elseif ($search_field === 'lev_varenr') {
    $leverandorJoin = "INNER JOIN vare_lev vl ON vl.vare_id = varer.id";
}

$variantJoin = "LEFT JOIN variant_varer vv ON vv.vare_id = varer.id AND vv.variant_stregkode IS NOT NULL AND vv.variant_stregkode != ''";

$countQuery = db_select("SELECT COUNT(*) as cnt FROM varer $variantJoin $leverandorJoin WHERE $baseWhere", __FILE__ . " line " . __LINE__);
if ($countQuery) {
    $countRow = db_fetch_array($countQuery);
    $totalCount = intval($countRow['cnt']);
}

// Include gruppe (product group) in query for VAT-free check
$levVarenrSelect = $leverandorJoin ? ", COALESCE(vl.lev_varenr, '') as lev_varenr" : ", '' as lev_varenr";
$qtxt = "SELECT varer.id, varer.varenr, varer.beskrivelse, COALESCE(vv.variant_salgspris, varer.salgspris) AS salgspris, $levKostprisSelect AS kostpris, varer.enhed, varer.beholdning, varer.gruppe, vv.variant_stregkode AS vv_stregkode, vv.variant_text AS vv_variant_text $levVarenrSelect $levDetailsSelect
         FROM varer $variantJoin $leverandorJoin
         WHERE $baseWhere
         ORDER BY varer.varenr ASC LIMIT $limit OFFSET $offset";

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
            'lev_varenr' => trim($row['lev_varenr']),
            'beskrivelse' => trim(stripslashes($row['beskrivelse'])),
            'salgspris' => $salgspris,
            'kostpris' => floatval($row['kostpris']),
            'enhed' => trim($row['enhed']),
            'beholdning' => floatval($row['beholdning']),
            'vv_stregkode' => $row['vv_stregkode'] ? trim($row['vv_stregkode']) : null,
            'vv_variant_text' => $row['vv_variant_text'] ? trim($row['vv_variant_text']) : null,
            'lev_id' => isset($row['lev_id']) ? intval($row['lev_id']) : 0,
            'lev_kontonr' => isset($row['lev_kontonr']) ? trim($row['lev_kontonr']) : '',
            'lev_firmanavn' => isset($row['lev_firmanavn']) ? trim($row['lev_firmanavn']) : '',
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
