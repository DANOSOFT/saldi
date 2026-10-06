<?php

ob_start();

@session_start();
$s_id = session_id();
$title = "accountSearch"; 
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
$type = isset($_GET['type']) ? trim($_GET['type']) : 'finance';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$exact = isset($_GET['exact']) ? intval($_GET['exact']) : 0;
// SST-814 The COUNT(*) below costs as much as the search itself and only one of the
// three callers reads it: accountAutocomplete.js renders "viser 1-50 af N" and prev/next
// buttons, so it asks for it with count=1. ordreAutocomplete.js and
// kreditorOrdreAutocomplete.js ignore the whole pagination block, and the exact=1 lookup
// wants a single row - none of them should pay for a second full pass over adresser on
// every keystroke. Off unless asked for.
$withCount = isset($_GET['count']) ? intval($_GET['count']) : 0;
$limit = 50;
$offset = ($page - 1) * $limit;
// Fetch one more row than we show: that is enough to know whether a next page exists,
// so hasMore stays truthful for callers that do not ask for a count.
$fetchLimit = $limit + 1;

// SST-814 review: the minimum lives here as well as in the clients. This endpoint is
// shared and is the only thing that touches the database, so a request that bypasses the
// JS - a direct GET, or a future caller - would otherwise still run the scan the client
// gate exists to prevent. A trigram index cannot match fewer than three characters, so
// below that the query is a full table scan however adresser is indexed.
//
// Only the branches that search adresser are gated. finance searches kontoplan, which is
// small and whose account numbers are legitimately one or two characters. exact=1 is an
// equality lookup on a full account number and is indexable at any length. An empty term
// is still served: with no term there is no ILIKE at all, only a filtered first page,
// requested once per focus rather than once per keystroke.
$minSearchLength = 3;
// exact=1 is deliberately NOT exempted. Only the finance branch below honours it, with an
// equality test on kontoplan.kontonr; the debitor and kreditor branches ignore it and run
// the substring ILIKE either way, so exempting them let a one-character
// ?type=debitor&exact=1&search=a past this gate and into the very scan it exists to stop.
// The one caller that sends exact=1 is the VAT lookup in accountAutocomplete.js, which
// uses type=finance and is unaffected.
$searchableType = ($search !== '')
    && in_array($type, array('debitor', 'kreditor'), true);
$searchTooShort = $searchableType && mb_strlen($search, 'UTF-8') < $minSearchLength;
// SST-814 review: pg_trgm takes its trigrams from runs of letters and digits, so a term
// made only of punctuation or spaces yields no index keys at all and can never be served
// by the index however selective it looks. Measured on 200,000 rows: '---' seq scans at
// 142 ms and matches nothing. This is narrower than "the index cannot help" - that depends
// on how selective the extracted keys are against the actual data, not on the term's shape,
// which is why the length check above cannot be replaced by one. See the PR discussion.
$searchHasNoTrigram = $searchableType && !preg_match('/[\p{L}\p{N}]/u', $search);
if ($searchTooShort || $searchHasNoTrigram) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'results' => array(),
        'pagination' => array(
            'page' => $page,
            'limit' => $limit,
            'total' => null,
            'hasMore' => false,
            // So a caller can tell this apart from "no matches" and say why.
            'minLength' => $minSearchLength,
        ),
    ));
    exit;
}


// Sanitize type - only allow specific values
if (!in_array($type, array('finance', 'debitor', 'kreditor', ''))) {
    $type = 'finance';
}

if (!isset($regnaar) || empty($regnaar)) {
    echo json_encode(array('error' => 'Session expired'));
    exit;
}

$results = array();
$totalCount = null;
$countTable = '';
$countWhere = '';

// SST-814 A search term's own '%' and '_' have to be neutralised before the term is
// wrapped in the caller's '%...%' wildcards, or typing a single '%' matches the whole
// column - which on adresser means a full scan returning everything. Same treatment
// includes/grid.php gives its text-column search; Postgres takes '\' as LIKE's escape
// character by default, so no ESCAPE clause is needed.
$search_like = db_escape_string(db_escape_like_pattern($search));

if ($type === 'finance' || $type === '') {
    $search_escaped = db_escape_string($search);

    $baseWhere = "(kontotype = 'D' OR kontotype = 'S' OR kontotype = 'H')
             AND regnskabsaar = '$regnaar'
             AND (lukket IS NULL OR lukket != 'on')";

    if ($search !== '' && $exact) {
        $baseWhere .= " AND CAST(kontonr AS TEXT) = '$search_escaped'";
    } elseif ($search !== '') {
        // kontoplan.kontonr is numeric, so this cast is load-bearing - unlike the one on
        // adresser.kontonr below, which is already character varying.
        $baseWhere .= " AND (CAST(kontonr AS TEXT) ILIKE '%$search_like%' OR beskrivelse ILIKE '%$search_like%' OR genvej ILIKE '%$search_like%')";
    }

    $countTable = 'kontoplan';
    $countWhere = $baseWhere;

    $qtxt = "SELECT kontotype, kontonr, beskrivelse, moms, genvej, saldo
             FROM kontoplan
             WHERE $baseWhere
             ORDER BY kontonr LIMIT $fetchLimit OFFSET $offset";

    $query = db_select($qtxt, __FILE__ . " line " . __LINE__);

    if ($query) {
        while ($row = db_fetch_array($query)) {
            $results[] = array(
                'kontonr' => trim($row['kontonr']),
                'beskrivelse' => trim(stripslashes($row['beskrivelse'])),
                'moms' => isset($row['moms']) ? trim($row['moms']) : '',
                'genvej' => isset($row['genvej']) ? trim($row['genvej']) : '',
                'saldo' => isset($row['saldo']) ? floatval($row['saldo']) : 0,
                'kontotype' => trim($row['kontotype']),
                'type' => 'finance'
            );
        }
    }
} elseif ($type === 'debitor') {
    $baseWhere = "art = 'D'";

    if ($search !== '') {
        // SST-814 adresser.kontonr is character varying(30), so CAST(... AS TEXT) was a
        // no-op that only stopped an index on the column from being usable. Dropped.
        $baseWhere .= " AND (kontonr ILIKE '%$search_like%' OR firmanavn ILIKE '%$search_like%')";
    }

    $countTable = 'adresser';
    $countWhere = $baseWhere;

    $qtxt = "SELECT id, kontonr, firmanavn, kontakt
             FROM adresser
             WHERE $baseWhere
             ORDER BY kontonr LIMIT $fetchLimit OFFSET $offset";

    $query = db_select($qtxt, __FILE__ . " line " . __LINE__);
    
    if ($query) {
        while ($row = db_fetch_array($query)) {
            $results[] = array(
                'id' => $row['id'],
                'kontonr' => trim($row['kontonr']),
                'beskrivelse' => trim(stripslashes($row['firmanavn'])),
                'kontakt' => isset($row['kontakt']) ? trim($row['kontakt']) : '',
                'type' => 'debitor'
            );
        }
    }
} elseif ($type === 'kreditor') {
    $baseWhere = "art = 'K'";

    if ($search !== '') {
        // SST-814 See the debitor branch: the CAST was a no-op on this column.
        $baseWhere .= " AND (kontonr ILIKE '%$search_like%' OR firmanavn ILIKE '%$search_like%')";
    }

    $countTable = 'adresser';
    $countWhere = $baseWhere;

    $qtxt = "SELECT id, kontonr, firmanavn, kontakt
             FROM adresser
             WHERE $baseWhere
             ORDER BY kontonr LIMIT $fetchLimit OFFSET $offset";

    $query = db_select($qtxt, __FILE__ . " line " . __LINE__);
    
    if ($query) {
        while ($row = db_fetch_array($query)) {
            $results[] = array(
                'id' => $row['id'],
                'kontonr' => trim($row['kontonr']),
                'beskrivelse' => trim(stripslashes($row['firmanavn'])),
                'kontakt' => isset($row['kontakt']) ? trim($row['kontakt']) : '',
                'type' => 'kreditor'
            );
        }
    }
}

// SST-814 The extra row fetched above answers "is there a next page?" without a second
// pass over the table. Drop it again so the caller still gets at most $limit rows.
$hasMore = false;
if (count($results) > $limit) {
    $results = array_slice($results, 0, $limit);
    $hasMore = true;
}

// Only the caller that displays a total pays for counting one.
if ($withCount && $countTable !== '') {
    $countQuery = db_select("SELECT COUNT(*) as cnt FROM $countTable WHERE $countWhere", __FILE__ . " line " . __LINE__);
    if ($countQuery) {
        $countRow = db_fetch_array($countQuery);
        $totalCount = intval($countRow['cnt']);
    }
}

$response = array(
    'results' => $results,
    'pagination' => array(
        'page' => $page,
        'limit' => $limit,
        // null, not 0, when no count was requested: 0 would read as "no matches".
        'total' => $totalCount,
        'hasMore' => $hasMore
    )
);

echo json_encode($response);
exit;
?>
