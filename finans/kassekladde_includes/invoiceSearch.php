<?php

ob_start();

@session_start();
$s_id = session_id();
$title = "invoiceSearch";  
$modulnr = 0;
$bg = "nix";  
$header = "nix";
$webservice = true; 

chdir(dirname(__FILE__) . '/..');

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php"); 

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$accountNr = isset($_GET['account']) ? trim($_GET['account']) : '';
$accountType = isset($_GET['accountType']) ? trim($_GET['accountType']) : ''; // D or K
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 50; 
$offset = ($page - 1) * $limit;

$currentAmount = isset($_GET['currentAmount']) ? trim($_GET['currentAmount']) : '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : '';

// JS already sends clean decimal format (e.g. "4999.00"), just cast directly
$currentAmountNormalized = $currentAmount;
$currentAmountFloat = ($currentAmount !== '') ? floatval($currentAmount) : null;
if (!isset($regnaar) || empty($regnaar)) {
    echo json_encode(array('error' => 'Session expired'));
    exit;
}

$results = array();
$totalCount = 0;

$search_escaped = db_escape_string($search);
$accountNr_escaped = db_escape_string($accountNr);
// Sanitize accountType - only allow 'D' or 'K'
$accountType = strtoupper(substr($accountType, 0, 1));
if ($accountType !== 'D' && $accountType !== 'K') {
    $accountType = '';
}

$baseWhere = "(openpost.udlignet != '1' OR openpost.udlignet IS NULL)";

if ($accountNr !== '' && $accountType !== '') {
    $baseWhere .= " AND openpost.konto_nr = '$accountNr_escaped'";
    $ktoQuery = db_select("SELECT id FROM adresser WHERE kontonr = '$accountNr_escaped' AND art = '$accountType'", __FILE__ . " line " . __LINE__);
    if ($ktoRow = db_fetch_array($ktoQuery)) {
        $konto_id = $ktoRow['id'];
        $baseWhere .= " AND openpost.konto_id = '$konto_id'";
    }
}

// Add search filter
if ($search !== '') {
    $baseWhere .= " AND (
        CAST(openpost.faktnr AS TEXT) ILIKE '%$search_escaped%' 
        OR adresser.firmanavn ILIKE '%$search_escaped%'
        OR CAST(openpost.konto_nr AS TEXT) ILIKE '%$search_escaped%'
        OR openpost.beskrivelse ILIKE '%$search_escaped%'
    )";
}

$countQuery = db_select("
    SELECT COUNT(*) as cnt 
    FROM openpost 
    LEFT JOIN adresser ON openpost.konto_id = adresser.id
    WHERE $baseWhere
", __FILE__ . " line " . __LINE__);

if ($countQuery) {
    $countRow = db_fetch_array($countQuery);
    $totalCount = intval($countRow['cnt']);
}

//#########
if ($mode === 'open_post') {
    // --- open_post mode: fetch all, score, sort, then paginate ---
    $hintTokens = isset($_GET['hintTokens']) ? json_decode($_GET['hintTokens'], true) : [];
    $descWords  = isset($_GET['descWords'])  ? json_decode($_GET['descWords'], true)  : [];
    if (!is_array($hintTokens)) $hintTokens = [];
    if (!is_array($descWords))  $descWords  = [];

    $currentAmountFloat = ($currentAmount !== '') ? floatval($currentAmount) : null;

    // Fetch all matching rows (no LIMIT)
    $qtxt = "
        SELECT 
            openpost.id,
            openpost.konto_nr,
            openpost.konto_id,
            openpost.faktnr,
            openpost.amount,
            openpost.transdate,
            openpost.beskrivelse,
            adresser.firmanavn,
            adresser.art
        FROM openpost 
        LEFT JOIN adresser ON openpost.konto_id = adresser.id
        WHERE $baseWhere
        ORDER BY openpost.transdate DESC, openpost.faktnr
    ";
    $query = db_select($qtxt, __FILE__ . " line " . __LINE__);

    $allRows = [];
    while ($row = db_fetch_array($query)) {
        $rowAmount = floatval($row['amount']);
        
        // --- Score calculation (mirrors client side) ---
        $score = 0;
        
        // 1. Amount match
        $amountMatch = ($currentAmountFloat !== null) && (abs(abs($rowAmount) - abs($currentAmountFloat)) < 0.001);
        if ($amountMatch) $score += 40;
        
        // 2. Company name words in description words
        $firmanavn = trim($row['firmanavn']);
        if ($firmanavn && !empty($descWords)) {
            $nameWords = preg_split('/[\s\-\/\\.,;:_()[\]{}]+/', strtoupper($firmanavn));
            $nameWords = array_filter($nameWords, fn($w) => strlen($w) >= 3);
            $matchCount = 0;
            foreach ($nameWords as $nw) {
                if (in_array($nw, $descWords)) $matchCount++;
            }
            if ($matchCount > 0) $score += 30 + ($matchCount * 5);
        }
        
        // 3. Invoice number contains any hint token
        $faktnr = trim($row['faktnr']);
        if ($faktnr && !empty($hintTokens)) {
            $faktnrUpper = strtoupper($faktnr);
            foreach ($hintTokens as $tok) {
                if (strpos($faktnrUpper, $tok) !== false) {
                    $score += 20;
                    break;
                }
            }
        }
        
        // 4. Account number contains any hint token
        $kontonr = trim($row['konto_nr']);
        if ($kontonr && !empty($hintTokens)) {
            $kontonrUpper = strtoupper($kontonr);
            foreach ($hintTokens as $tok) {
                if (strpos($kontonrUpper, $tok) !== false) {
                    $score += 10;
                    break;
                }
            }
        }
        
        $allRows[] = [
            'id'          => $row['id'],
            'kontonr'     => $kontonr,
            'konto_id'    => $row['konto_id'],
            'faktnr'      => $faktnr,
            'amount'      => $rowAmount,
            'transdate'   => $row['transdate'],
            'firmanavn'   => stripslashes($firmanavn),
            'beskrivelse' => stripslashes($row['beskrivelse']),
            'art'         => trim($row['art']),
            'amountMatch' => $amountMatch,
            '_score'      => $score
        ];
    }
    
    // Sort by score DESC, then date DESC, then faktnr
    usort($allRows, function($a, $b) {
        if ($a['_score'] != $b['_score']) return $b['_score'] - $a['_score'];
        if ($a['transdate'] != $b['transdate']) return strcmp($b['transdate'], $a['transdate']);
        return strcmp($a['faktnr'], $b['faktnr']);
    });
    
    $totalCount = count($allRows);
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $pageResults = array_slice($allRows, $offset, $limit);
    
    // Remove temporary _score
    foreach ($pageResults as &$r) unset($r['_score']);
    
    $response = [
        'results' => $pageResults,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'hasMore' => ($offset + count($pageResults)) < $totalCount
        ]
    ];
    echo json_encode($response);
    exit;
}


//########

$qtxt = "
    SELECT 
        openpost.id,
        openpost.konto_nr,
        openpost.konto_id,
        openpost.faktnr,
        openpost.amount,
        openpost.transdate,
        openpost.beskrivelse,
        openpost.valuta,
        adresser.firmanavn,
        adresser.art
    FROM openpost 
    LEFT JOIN adresser ON openpost.konto_id = adresser.id
    WHERE $baseWhere
    ORDER BY openpost.transdate DESC, openpost.faktnr
    LIMIT $limit OFFSET $offset
";

$query = db_select($qtxt, __FILE__ . " line " . __LINE__);

if ($query) {
    while ($row = db_fetch_array($query)) {
        // Get the offset account from grupper table
        $offsetAccount = '';
        $accountArt = isset($row['art']) ? trim($row['art']) : '';
        if ($accountArt && isset($row['konto_id'])) {
            // Get the group for this specific account
            $grpQuery = db_select("SELECT gruppe FROM adresser WHERE id = '" . db_escape_string($row['konto_id']) . "'", __FILE__ . " line " . __LINE__);
            if ($grpQuery && $grpRow = db_fetch_array($grpQuery)) {
                $grp = trim($grpRow['gruppe']);
                if ($grp) {
                    $grpArt = $accountArt . 'G'; // DG or KG
                    $offsetQuery = db_select("SELECT box5 FROM grupper WHERE art = '$grpArt' AND kodenr = '$grp' AND fiscal_year = '$regnaar'", __FILE__ . " line " . __LINE__);
                    if ($offsetQuery && $offsetRow = db_fetch_array($offsetQuery)) {
                        $offsetAccount = trim($offsetRow['box5']);
                    }
                }
            }
        }
        
        $rowAmount = floatval($row['amount']);
        $amountMatch = ($currentAmountFloat !== null) && (abs(abs($rowAmount) - abs($currentAmountFloat)) < 0.001);


        $results[] = array(
            'id' => $row['id'],
            'kontonr' => trim($row['konto_nr']),
            'konto_id' => $row['konto_id'],
            'faktnr' => trim($row['faktnr']),
            'amount' => $rowAmount,
            'transdate' => $row['transdate'],
            'firmanavn' => trim(stripslashes($row['firmanavn'])),
            'beskrivelse' => isset($row['beskrivelse']) ? trim(stripslashes($row['beskrivelse'])) : '',
            'art' => $accountArt,
            'valuta' => isset($row['valuta']) ? trim($row['valuta']) : '',
            'offsetAccount' => $offsetAccount,
            'amountMatch' => $amountMatch
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
