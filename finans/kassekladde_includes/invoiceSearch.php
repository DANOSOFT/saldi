<?php
// finans/kassekladde_includes/invoiceSearch.php --- 2026-09-22
// Copyright (c) 2026 Danosoft ApS
// 20260908 CDX/LH Require an exact customer/supplier filter for automatic settlement.
// 20260911 Sawaneh Return the order payment ID (ordrer.betalings_id) with each open post and
//                  allow searching on it, so auto settlement can show it again.
// 20260922 CDX/PHR Search across customer/supplier accounts when no filter is requested.
// 20260922 CL/NTR Bound the unfiltered open_post query with a SQL-level candidate signal
//                 and paged filler rows, instead of pulling every open post into PHP.

ob_start();

@session_start();
$s_id = session_id();
$title = "invoiceSearch";  
$modulnr = 0;
$bg = "nix";  
$header = "nix";
$webservice = true; 

chdir(dirname(__FILE__) . '/..');

include(__DIR__ . "/../../includes/connect.php");
include(__DIR__ . "/../../includes/online.php");
include(__DIR__ . "/../../includes/std_func.php");

include_once(__DIR__ . '/autoSettlement.php');

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$accountNr = is_string($_GET['account'] ?? null) ? trim($_GET['account']) : '';
$accountType = is_string($_GET['accountType'] ?? null) ? trim($_GET['accountType']) : ''; // D or K
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
// Sanitize accountType - only allow 'D' or 'K'
$accountType = strtoupper($accountType);
if ($accountType !== 'D' && $accountType !== 'K') {
    $accountType = '';
}

$baseWhere = "(openpost.udlignet != '1' OR openpost.udlignet IS NULL)";

// Payment ID lives on the invoiced order, never on the open post itself
$paymentIdMatch = "ordrer.konto_id = openpost.konto_id AND ordrer.fakturanr = openpost.faktnr"
    . " AND COALESCE(openpost.faktnr, '') != '' AND ordrer.art IN ('DO', 'DK', 'KO', 'KK')"
    . " AND COALESCE(ordrer.betalings_id, '') != ''";
$paymentIdSelect = "(SELECT MAX(ordrer.betalings_id) FROM ordrer WHERE $paymentIdMatch) AS betalings_id";

if ($mode === 'open_post') {
    $baseWhere .= ' AND (' . autoSettlementSearchWhere($_GET['account'] ?? '', $_GET['accountType'] ?? '') . ')';
} elseif ($accountNr !== '') {
    $baseWhere .= ' AND (' . autoSettlementAccountWhere($accountNr, $accountType) . ')';
}

if ($mode === 'open_post') {
    $baseWhere .= " AND TRIM(COALESCE(openpost.faktnr, '')) != ''";
}

// Add search filter
if ($search !== '') {
    $searchConds = array(
        "CAST(openpost.faktnr AS TEXT) ILIKE '%$search_escaped%'",
        "adresser.firmanavn ILIKE '%$search_escaped%'",
        "CAST(openpost.konto_nr AS TEXT) ILIKE '%$search_escaped%'",
        "openpost.beskrivelse ILIKE '%$search_escaped%'",
        "EXISTS (SELECT 1 FROM ordrer WHERE $paymentIdMatch AND ordrer.betalings_id ILIKE '%$search_escaped%')"
    );
    $amountSearch = str_replace(' ', '', $search);
    if (strpos($amountSearch, ',') !== false) {
        // Danish format: "2.985,00" -> "2985.00"
        $amountSearch = str_replace('.', '', $amountSearch);
        $amountSearch = str_replace(',', '.', $amountSearch);
    }
    if (preg_match('/^-?\d+(\.\d+)?$/', $amountSearch)) {
        // Prefix match on the absolute amount, so "985" does not hit "2985,00"
        $amountSearch_escaped = db_escape_string(ltrim($amountSearch, '-'));
        $searchConds[] = "CAST(ABS(openpost.amount) AS TEXT) LIKE '$amountSearch_escaped%'";
    }
    $baseWhere .= " AND (" . implode(" OR ", $searchConds) . ")";
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
    // --- open_post mode: fetch candidates, score, sort, then paginate ---
    $hintTokens = isset($_GET['hintTokens']) ? json_decode($_GET['hintTokens'], true) : [];
    $descWords  = isset($_GET['descWords'])  ? json_decode($_GET['descWords'], true)  : [];
    if (!is_array($hintTokens)) $hintTokens = [];
    if (!is_array($descWords))  $descWords  = [];

    $currentAmountFloat = ($currentAmount !== '') ? floatval($currentAmount) : null;

    // Without an account filter, $baseWhere spans every customer/supplier, so pulling every
    // row into PHP for scoring does not scale. Every scoring signal (autoSettlementAmountMatches
    // plus the hint-token/description-word checks below) can be restated as a SQL predicate that
    // is a superset of what would score >0, so a row that could ever beat the current best is
    // never excluded here; PHP re-applies the exact scoring rules afterwards. Zero-signal rows
    // can never win auto-selection or outrank a signal row, so they are paged directly in SQL
    // instead of being pulled into PHP in full.
    $signalWhere = autoSettlementCandidateSignalWhere($currentAmountFloat, $hintTokens, $descWords);

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
            adresser.art,
            $paymentIdSelect
        FROM openpost
        LEFT JOIN adresser ON openpost.konto_id = adresser.id
        WHERE $baseWhere AND ($signalWhere)
        ORDER BY openpost.transdate DESC, openpost.faktnr
    ";
    // Build a single scored row (mirrors client-side scoreCandidate()).
    $scoreRow = function($row) use ($currentAmountFloat, $descWords, $hintTokens) {
        $rowAmount = floatval($row['amount']);
        $score = 0;

        // 1. Amount match
        $amountMatch = autoSettlementAmountMatches($rowAmount, $currentAmountFloat);
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

        // 3. Invoice number contains any hint token or description word (mirrors client scoring)
        $faktnr = trim($row['faktnr']);
        if ($faktnr && (!empty($hintTokens) || !empty($descWords))) {
            $faktnrUpper = strtoupper($faktnr);
            $invoiceHit = false;
            foreach ($hintTokens as $tok) {
                if (strpos($faktnrUpper, (string)$tok) !== false) {
                    $invoiceHit = true;
                    break;
                }
            }
            if (!$invoiceHit) {
                foreach ($descWords as $dw) {
                    $dw = strtoupper((string)$dw);
                    if (strlen($dw) >= 3 && strpos($faktnrUpper, $dw) !== false) {
                        $invoiceHit = true;
                        break;
                    }
                }
            }
            if ($invoiceHit) {
                $score += 20;
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

        return [
            'id'          => $row['id'],
            'kontonr'     => $kontonr,
            'konto_id'    => $row['konto_id'],
            'faktnr'      => $faktnr,
            'betalings_id' => trim((string)$row['betalings_id']),
            'amount'      => $rowAmount,
            'transdate'   => $row['transdate'],
            'firmanavn'   => stripslashes($firmanavn),
            'beskrivelse' => stripslashes($row['beskrivelse']),
            'art'         => trim($row['art']),
            'amountMatch' => $amountMatch,
            '_score'      => $score
        ];
    };

    $query = db_select($qtxt, __FILE__ . " line " . __LINE__);

    // autoSettlementCandidateSignalWhere() is a deliberately loose SQL superset of the exact
    // PHP scoring rules, so some matched rows re-score to 0 here. Those are false positives of
    // the SQL predicate, not real signal rows: keep them out of $signalRows/$signalIds so they
    // stay eligible for the ordinary, transdate-ordered filler pagination below instead of
    // permanently occupying a page slot ahead of it.
    $signalRows = [];
    $signalIds = [];
    while ($row = db_fetch_array($query)) {
        $scored = $scoreRow($row);
        if ($scored['_score'] <= 0) continue;
        $signalIds[] = $row['id'];
        $signalRows[] = $scored;
    }

    // Signal rows always sort ahead of zero-signal rows (a positive score beats 0), so they own
    // the front of every page; only once a page runs past them do zero-signal rows appear, and
    // they are fetched with their own SQL LIMIT/OFFSET rather than in full.
    usort($signalRows, function($a, $b) {
        if ($a['_score'] != $b['_score']) return $b['_score'] - $a['_score'];
        if ($a['transdate'] != $b['transdate']) return strcmp($b['transdate'], $a['transdate']);
        if ($a['faktnr'] != $b['faktnr']) return strcmp($a['faktnr'], $b['faktnr']);
        return $a['id'] - $b['id'];
    });

    $autoSelectId = autoSettlementBestCandidateId($signalRows);
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $signalCount = count($signalRows);
    $pageResults = array_slice($signalRows, $offset, $limit);
    $remaining = $limit - count($pageResults);
    if ($remaining > 0) {
        $fillerOffset = max(0, $offset - $signalCount);
        $excludeIds = $signalIds ? implode(',', array_map('intval', $signalIds)) : '-1';
        $fillerWhere = "$baseWhere AND openpost.id NOT IN ($excludeIds)";
        $fillerQtxt = "
            SELECT
                openpost.id,
                openpost.konto_nr,
                openpost.konto_id,
                openpost.faktnr,
                openpost.amount,
                openpost.transdate,
                openpost.beskrivelse,
                adresser.firmanavn,
                adresser.art,
                $paymentIdSelect
            FROM openpost
            LEFT JOIN adresser ON openpost.konto_id = adresser.id
            WHERE $fillerWhere
            ORDER BY openpost.transdate DESC, openpost.faktnr, openpost.id
            LIMIT $remaining OFFSET $fillerOffset
        ";
        $fillerQuery = db_select($fillerQtxt, __FILE__ . " line " . __LINE__);
        while ($row = db_fetch_array($fillerQuery)) {
            $pageResults[] = $scoreRow($row);
        }
    }

    // Remove temporary _score
    foreach ($pageResults as &$r) unset($r['_score']);
    
    $response = [
        'results' => $pageResults,
        'autoSelectId' => $autoSelectId,
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
        adresser.art,
        $paymentIdSelect
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
            'betalings_id' => trim((string)$row['betalings_id']),
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
