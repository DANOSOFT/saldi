<?php
/**
 * Order API - Handles creation of purchase orders for products
 */

session_start();
$s_id = session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/stdFunc/usDecimal.php");

// Input validation and sanitization
$vare_id = isset($_POST["vare_id"]) ? (int)$_POST["vare_id"] : null;
$antal = isset($_POST["antal"]) ? $_POST["antal"] : "1,00";

$antal = usdecimal($antal);

// Validate required input
if ($vare_id === null || $vare_id <= 0) {
    exit("Invalid product ID");
}

genbestil($vare_id, $antal);

/**
 * Creates a purchase order for the specified product
 * 
 * @param int $vare_id Product ID
 * @param float $antal Quantity to order
 */

function genbestil($vare_id, $antal) {
    global $brugernavn, $db, $regnaar, $sprog_id, $baseCurrency;
    
    // Get employee reference for order
    $ref = getEmployeeReference();
    
    // Find supplier for the product
    $supplier_info = getSupplierInfo($vare_id);
    if (!$supplier_info) {
        showError("No supplier found", $vare_id, $sprog_id);
        return;
    }
    
    $lev_id = $supplier_info['lev_id'];
    $lev_varenr = $supplier_info['lev_varenr'];
    $pris = (int)$supplier_info['kostpris'];
    $ordredate = date("Y-m-d");

    // Check for existing open order for today
    $existing_order = findExistingOrder($lev_id, $ordredate);
    
    if ($existing_order) {
        $ordre_id = handleExistingOrder($existing_order, $lev_id, $pris, $baseCurrency);
    } else {
        $ordre_id = createNewOrder($lev_id, $ref, $pris, $baseCurrency, $ordredate);
    }
    
    if (!$ordre_id) {
        return;
    }
    
    // Add product to order
    addProductToOrder($ordre_id, $vare_id, $pris, $antal);
}

/**
 * Get employee reference for the current user
 */
function getEmployeeReference() {
    global $brugernavn;
    
    $query = "SELECT ansat_id FROM brugere WHERE brugernavn = '" . db_escape_string($brugernavn) . "'";
    $result = db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
    
    if (!$result) {
        return null;
    }
    
    $query = "SELECT navn FROM ansatte WHERE id = " . (int)$result['ansat_id'];
    $employee = db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
    
    return $employee ? $employee['navn'] : null;
}

/**
 * Get supplier information for a product
 */
function getSupplierInfo($vare_id) {
    $query = "SELECT * FROM vare_lev WHERE vare_id = " . (int)$vare_id . " ORDER BY posnr";
    return db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
}

/**
 * Find existing open order for supplier and date
 */
function findExistingOrder($lev_id, $ordredate) {
    $query = "SELECT id, sum, valutakurs FROM ordrer 
              WHERE konto_id = " . (int)$lev_id . " 
              AND art = 'KO' 
              AND status < 1 
              AND ordredate = '" . db_escape_string($ordredate) . "'";
    return db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
}

/**
 * Handle existing order - get currency info and adjust price
 */
function handleExistingOrder($existing_order, $lev_id, &$pris, $baseCurrency) {
    global $regnaar, $vare_id;
    
    $ordre_id = $existing_order['id'];
    
    // Get supplier group information
    $supplier_data = getSupplierGroupData($lev_id);
    if (!$supplier_data) {
        showSupplierGroupError($vare_id);
        return null;
    }
    
    // Handle currency conversion
    $currency_info = getCurrencyInfo($supplier_data['group_info'], $baseCurrency);
    if ($currency_info['valuta'] != $baseCurrency) {
        $pris = $pris / ($currency_info['kurs'] / 100);
    }
    
    return $ordre_id;
}

/**
 * Get supplier group data
 */
function getSupplierGroupData($lev_id) {
    global $regnaar;
    
    $query = "SELECT * FROM adresser WHERE id = " . (int)$lev_id;
    $supplier = db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
    
    if (!$supplier || !$supplier['gruppe']) {
        return null;
    }
    
    $query = "SELECT box1, box3 FROM grupper 
              WHERE kode = 'K' 
              AND art = 'KG' 
              AND kodenr = '" . db_escape_string($supplier['gruppe']) . "' 
              AND fiscal_year = '" . db_escape_string($regnaar) . "'";
    $group_info = db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
    
    if (!$group_info) {
        return null;
    }
    
    return [
        'supplier' => $supplier,
        'group_info' => $group_info,
        'kode' => substr($group_info['box1'], 0, 1),
        'kodenr' => substr($group_info['box1'], 1)
    ];
}

/**
 * Get currency information and exchange rate
 */
function getCurrencyInfo($group_info, $baseCurrency) {
    $valuta = $group_info["box3"];
    
    if ($valuta != $baseCurrency) {
        $query = "SELECT kodenr FROM grupper WHERE art = 'VK' AND box1 = '" . db_escape_string($valuta) . "'";
        $currency_group = db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
        
        if ($currency_group) {
            $gruppe = $currency_group["kodenr"];
            $query = "SELECT kurs FROM valuta WHERE gruppe = '" . db_escape_string($gruppe) . "' ORDER BY valdate DESC LIMIT 1";
            $rate_result = db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
            
            return [
                'valuta' => $valuta,
                'kurs' => $rate_result ? $rate_result["kurs"] : 100
            ];
        }
    }
    
    return [
        'valuta' => $baseCurrency,
        'kurs' => 100
    ];
}

/**
 * Create a new order
 */
function createNewOrder($lev_id, $ref, &$pris, $baseCurrency, $ordredate) {
    global $regnaar, $vare_id;
    
    // Get next order number
    $ordrenr = getNextOrderNumber();
    
    // Get supplier group data
    $supplier_data = getSupplierGroupData($lev_id);
    if (!$supplier_data) {
        showSupplierGroupError($vare_id);
        return null;
    }
    
    $supplier = $supplier_data['supplier'];
    $kode = $supplier_data['kode'];
    $kodenr = $supplier_data['kodenr'];
    
    // Get currency information
    $currency_info = getCurrencyInfo($supplier_data['group_info'], $baseCurrency);
    $valuta = $currency_info['valuta'];
    $valutaKurs = $currency_info['kurs'];
    
    // Adjust price for currency
    if ($valuta != $baseCurrency) {
        $pris = $pris / ($valutaKurs / 100);
    }
    
    // Get VAT rate
    $momssats = getVatRate($kode, $kodenr);
    
    // Insert the order
    $ordre_id = insertOrder($ordrenr, $supplier, $ref, $ordredate, $momssats, $valuta, $valutaKurs);
    
    return $ordre_id;
}

/**
 * Get the next order number
 */
function getNextOrderNumber() {
    $query = "SELECT ordrenr FROM ordrer WHERE art='KO' OR art='KK' ORDER BY ordrenr DESC LIMIT 1";
    $result = db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
    return $result ? $result['ordrenr'] + 1 : 1;
}

/**
 * Get VAT rate from tax code
 */
function getVatRate($kode, $kodenr) {
    global $regnaar;
    
    if (!$kode) {
        return 0;
    }
    
    $query = "SELECT box2 FROM grupper 
              WHERE art = 'KM' 
              AND kode = '" . db_escape_string($kode) . "' 
              AND kodenr = '" . db_escape_string($kodenr) . "' 
              AND fiscal_year = '" . db_escape_string($regnaar) . "'";
    $result = db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
    
    return $result ? (int)$result['box2'] : 0;
}

/**
 * Insert order into database
 */
function insertOrder($ordrenr, $supplier, $ref, $ordredate, $momssats, $valuta, $valutaKurs) {
    $query = "INSERT INTO ordrer (
        ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land,
        betalingsdage, betalingsbet, cvrnr, notes, art, ordredate, momssats, status, ref, valuta, valutakurs
    ) VALUES (
        '" . db_escape_string($ordrenr) . "',
        '" . db_escape_string($supplier['id']) . "',
        '" . db_escape_string($supplier['kontonr']) . "',
        '" . db_escape_string($supplier['firmanavn']) . "',
        '" . db_escape_string($supplier['addr1']) . "',
        '" . db_escape_string($supplier['addr2']) . "',
        '" . db_escape_string($supplier['postnr']) . "',
        '" . db_escape_string($supplier['bynavn']) . "',
        '" . db_escape_string($supplier['land']) . "',
        '" . db_escape_string($supplier['betalingsdage']) . "',
        '" . db_escape_string($supplier['betalingsbet']) . "',
        '" . db_escape_string($supplier['cvrnr']) . "',
        '" . db_escape_string($supplier['notes']) . "',
        'KO',
        '" . db_escape_string($ordredate) . "',
        '" . db_escape_string($momssats) . "',
        '0',
        '" . db_escape_string($ref) . "',
        '" . db_escape_string($valuta) . "',
        '" . db_escape_string($valutaKurs) . "'
    )";
    
    db_modify($query, __FILE__ . " line " . __LINE__);
    
    // Get the created order ID
    $query = "SELECT id FROM ordrer WHERE ordrenr='" . db_escape_string($ordrenr) . "' AND art = 'KO'";
    $result = db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
    
    return $result ? $result['id'] : null;
}

/**
 * Add product to order
 */
function addProductToOrder($ordre_id, $vare_id, $pris, $antal) {
    // Get product information
    $product = getProductInfo($vare_id);
    if (!$product) {
        return;
    }
    
    $varenr = db_escape_string($product['varenr']);
    $lev_varenr = db_escape_string($product['lev_varenr']);
    $enhed = db_escape_string($product['enhed']);
    $beskrivelse = db_escape_string($product['beskrivelse']);
    $momsfri = $product['momsfri'];
    
    // Insert order line
    $query = "INSERT INTO ordrelinjer (
        ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, pris, lev_varenr, antal, momsfri
    ) VALUES (
        '" . db_escape_string($ordre_id) . "',
        '1000',
        '" . $varenr . "',
        '" . db_escape_string($vare_id) . "',
        '" . $beskrivelse . "',
        '" . $enhed . "',
        '" . db_escape_string($pris) . "',
        '" . $lev_varenr . "',
        '" . db_escape_string($antal) . "',
        '" . db_escape_string($momsfri) . "'
    )";
    
    db_modify($query, __FILE__ . " line " . __LINE__);
    
    // Update order total
    updateOrderSum($ordre_id, $pris, $antal);
}

/**
 * Get product information for order line
 */
function getProductInfo($vare_id) {
    $query = "SELECT 
        varer.varenr as varenr,
        varer.beskrivelse as beskrivelse,
        varer.enhed as enhed,
        vare_lev.lev_varenr as lev_varenr,
        grupper.box7 as momsfri 
    FROM varer, vare_lev, grupper 
    WHERE varer.id = " . (int)$vare_id . " 
        AND vare_lev.vare_id = " . (int)$vare_id . " 
        AND grupper.art = 'VG' 
        AND grupper.kodenr = varer.gruppe 
        AND vare_lev.posnr = 1";
    
    return db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
}

/**
 * Update order sum
 */
function updateOrderSum($ordre_id, $pris, $antal) {
    // Get current sum
    $query = "SELECT sum FROM ordrer WHERE id = " . (int)$ordre_id;
    $result = db_fetch_array(db_select($query, __FILE__ . " line " . __LINE__));
    $current_sum = $result ? (float)$result['sum'] : 0;
    
    // Calculate new sum
    $new_sum = $current_sum + ($pris * $antal);
    
    // Update order
    $query = "UPDATE ordrer SET sum = '" . db_escape_string($new_sum) . "' WHERE id = " . (int)$ordre_id;
    db_modify($query, __FILE__ . " line " . __LINE__);
}

/**
 * Show error message for missing supplier
 */
function showError($message, $vare_id, $sprog_id) {
    $product_query = "SELECT varenr FROM varer WHERE id = " . (int)$vare_id;
    $product = db_fetch_array(db_select($product_query, __FILE__ . " line " . __LINE__));
    $varenr = $product ? $product['varenr'] : 'Unknown';
    
    print "" . findtekst(951, $sprog_id) . " findes ikke (Varenr: $varenr)<br>";
}

/**
 * Show supplier group error
 */
function showSupplierGroupError($vare_id) {
    $product_query = "SELECT varenr FROM varer WHERE id = " . (int)$vare_id;
    $product = db_fetch_array(db_select($product_query, __FILE__ . " line " . __LINE__));
    $varenr = $product ? $product['varenr'] : 'Unknown';
    
    print "<BODY onLoad=\"javascript:alert('Leverand&oslash;rgruppe ikke korrekt opsat for varenr $varenr')\">";
}