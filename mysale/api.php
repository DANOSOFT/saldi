<?php 
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- mysale/api.php --- ver 5.1.1 --- 2026-11-24 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20261016 SS Review (PR #803): a discount row from the old page (DG/VG) still applies when the pair has no matrix row (NR).
// 20261029 CL/MMR SST-840: The account name from the request is validated, and every request value is cast or escaped before it reaches SQL.
//                 The includes in the entry block now use __DIR__.
// 20261030 CL/MJ SST-876 Drain deferred webshop updates only after the caller commits successfully.
// 20261030 CL/MJ SD-784 opret_ordrelinje() writes the momslog.log debug line only when the file opened.
//                An unwritable temp/<db>/momslog.log no longer stops the order line with a TypeError on PHP 8.
// 20261105 CL/LH getClosedDates answers an empty list when no day is closed, instead of a message object the booking calendar cannot map over.
// 20261105 CL/LH createBooking refuses a period that is not one, a stand that does not exist, and a stand that is booked or blocked on any calendar day of the period (rental_period_taken()), with the stand locked from that check until its hold is committed (rental_begin_stand_lock()).
// 20261105 CL/LH updateBooking deletes a rejected hold's order lines (ordrelinjer) with its order, instead of leaving them behind.
// 20261105 CL/LH getOrder and getUser are removed: nothing called them, and they answered anyone with the full order or customer row for an id.
//                getAllBookingsByUser answers only the customer the Mit salg session signed in at this regnskab (mySaleSignedInAs()), and 403 with an empty list otherwise.
// 20261105 CL/LH CreateOrder gives opret_ordrelinje() the order's VAT rate (global $momssats), so the item line gets the item's rate (25%) instead of 0% and approving the booking invoices its order, which fakturer_ordre() refused while the lines had no VAT.
//                When no fiscal year covers today, the script takes the newest one for every request, the highest number also where grupper.kodenr is text, so CreateOrder finds the item's rate there, opening the order in debitor/ordre.php no longer drops its VAT, and approving the booking posts its invoice there instead of stopping at an empty fiscal year.
//                CreateOrder looks the item group and the VAT code up by a quoted number, which Postgres compares with a text kodenr too.
//                opret_ordrelinje() comes from includes/ordrefunc.php, as in remoteBooking/api.php, instead of an old copy here, because fakturer_ordre() needs levering() and bogfor() from there and stopped with a fatal error once the order's VAT matched its lines.
// 20261105 CL/LH fakturer_ordre() casts the paid amount (felt_2) to a number, so a Kreditkort order whose felt_2 is '' is invoiced instead of stopping with a TypeError (float - ''), as api/rest_api.php did with HTTP 500.
//                It casts the order id and escapes the stored tidspkt in its SQL.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
// 20261116 CL/ASR createBooking books only for the customer Mit salg signed in at this regnskab in the visitor's session (mySaleSignedInAs()), and answers 403 otherwise before it holds the stand.
//                 It records the booking in the session (rental_booking_note_in_session()), and updateBooking acts only on a booking the session made while Mit salg still has its customer signed in; any other is answered 403 with nothing written.
//                 Mit salg keeps its bookings in the session apart from the booking page's (rental_booking_mysale_notes()): a booking made through remoteBooking/api.php is answered 403 here, and one made here is answered 403 there.
//                 A rejection never deletes an invoiced order (status 3 or more, or a fakturanr): it is answered 409 and the hold, the order and its lines stay.
//                 An approval of an invoiced order answers approved and writes nothing more; when fakturer_ordre() refuses the order, the answer is an error (500) and the hold keeps its expiry.
//                 $regnaar starts at 0, so a request logs no undefined-variable warning.
//                 updateBooking asks about the id as the request sent it and casts it for SQL only after that, so a list, a float, true or text such as '41abc' is answered 403.
//                 It answers 403 to a signed-out session also when the booking is gone: it then asks for the customer the session is signed in as.
//                 An approval of an invoiced order also ends its hold's expiry (expiry_time NULL), as an approval that invoices does, so rental/rental.php no longer removes the hold of an order staff invoiced while it waited.
// 20261124 CL/ASR Comments state the current rule; no code change.

$header = "nix";
$bg = "nix";
include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/publicTenantDb.php");
include_once(__DIR__ . "/../includes/stdFunc/rentalPeriodTaken.php");
include_once(__DIR__ . "/../includes/stdFunc/rentalBookingCustomer.php");
include_once(__DIR__ . "/shopLookup.php");
$db = public_tenant_db(ifset($_GET, "db", ""));
include(__DIR__ . "/../includes/ordrefunc.php"); // opret_ordrelinje(), and levering() and bogfor() for fakturer_ordre()

$connection = db_connect($sqhost, $squser, $sqpass, $db);
$query = db_select("SELECT box1, box2, box3, box4, kodenr FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__);
$currentYear = date('Y');
$currentMonth = date('m');
$regnaar = 0;
while($row = db_fetch_array($query)){
    $box1 = $row['box1']; // Starting month
    $box2 = $row['box2']; // Starting year
    $box3 = $row['box3']; // Ending month
    $box4 = $row['box4']; // Ending year
    $kodenr = $row['kodenr'];

    // Check if the current year and month fall within the range
    if (($currentYear > $box2 || ($currentYear == $box2 && $currentMonth >= $box1)) &&
        ($currentYear < $box4 || ($currentYear == $box4 && $currentMonth <= $box3))) {
        // The current year and month fall within the range
        $regnaar = $kodenr;
    }
}
if (!$regnaar) { // no fiscal year covers today: the newest, where a booking's order finds the item's VAT rate (CreateOrder, opret_ordrelinje()) and its approval posts the invoice (fakturer_ordre())
    $r_ra = db_fetch_array(db_select("SELECT max(" . db_cast("kodenr", "integer") . ") AS kodenr FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__)); // a number also where kodenr is text, so 10 is newer than 9
    $regnaar = $r_ra ? (int)$r_ra["kodenr"] : 0;
}

$baseCurrency = get_settings_value("baseCurrency", "globals", "");
if($baseCurrency == ""){
    $baseCurrency = "DKK";
}
if(isset($_GET["getAllProducts"])){
    $query = db_select("SELECT * FROM rentalremote WHERE is_active = 1", __FILE__ . " linje " . __LINE__);
        $res = [];
        while ($row = db_fetch_array($query)) {
            // Filter out numeric keys
            $row = array_filter($row, function($key) {
                return !is_numeric($key);
            }, ARRAY_FILTER_USE_KEY);
        
            $productId = $row['product_id'];
        
            // Fetch product details
            $productQuery = db_select("SELECT beskrivelse, varenr, enhed, salgspris, fotonavn, m_antal, m_rabat, m_type FROM varer WHERE id = $productId", __FILE__ . " linje " . __LINE__);
            $res2 = db_fetch_array($productQuery);
            $row["product_name"] = $res2["beskrivelse"];
            $row["product_number"] = $res2["varenr"];
            $row["unit"] = $res2["enhed"];
            $row["price"] = $res2["salgspris"]*1.25;
            $row["m_antal"] = $res2["m_antal"];
            $row["m_rabat"] = $res2["m_rabat"];
            $row["m_type"] = $res2["m_type"];
            $row["sku"] = $res2["varenr"];
    
            // Fetch rental periods
            $periodsQuery = db_select("SELECT id, amount FROM rentalremoteperiods WHERE rentalremote_id = $row[id]", __FILE__ . " linje " . __LINE__);
            $row["periods"] = [];
            if ($periodsQuery) {
                while ($res3 = db_fetch_array($periodsQuery)) {
                    // Filter out numeric keys
                    $res3 = array_filter($res3, function($key) {
                        return !is_numeric($key);
                    }, ARRAY_FILTER_USE_KEY);
                    $row["periods"][] = $res3;
                }
            }
            $res[] = $row;
        }
        echo json_encode($res);
}

if(isset($_GET["getAllDates"])){
    $productId = (int)$_GET["getAllDates"];
    // Move the condition related to rentalperiods into the ON clause of the LEFT JOIN
    $query = db_select("SELECT ri.id, rp.rt_from, rp.rt_to FROM rentalitems ri LEFT JOIN rentalperiod rp ON ri.id = rp.item_id AND (rp.rt_to > EXTRACT(EPOCH FROM CURRENT_DATE) OR rp.rt_to IS NULL) WHERE ri.product_id = $productId", __FILE__ . " linje " . __LINE__);
    
    $results = [];
    $itemIds = [];
    while($row = db_fetch_array($query)){
        // Filter out numeric keys
        $filtered_row = array();
        foreach ($row as $key => $value) {
            if (!is_numeric($key)) {
                $filtered_row[$key] = $value;
            }
        }
        $row = $filtered_row;
        $itemIds[] = $row["id"];
        $results[] = $row;
    }

    $query = db_select("SELECT item_id, rr_from, rr_to FROM rentalreserved WHERE item_id = ANY(ARRAY[" . implode(",", array_map("intval", $itemIds)) . "])", __FILE__ . " linje " . __LINE__);

    while($row = db_fetch_array($query)){
        // Filter out numeric keys
        $filtered_row = array();
        foreach ($row as $key => $value) {
            if (!is_numeric($key)) {
                $filtered_row[$key] = $value;
            }
        }
        $row = $filtered_row;
        $row['rt_from'] = $row['rr_from'];
        $row["rt_to"] = $row["rr_to"];
        $row["id"] = $row["item_id"];
        unset($row["rr_to"]);
        unset($row['rr_from']);
        unset($row["item_id"]);
        $results[] = $row;
    }
    
    echo json_encode($results);
}

if(isset($_GET["createBooking"])){
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = array();
    $data["item_id"] = (int)ifset($data, "item_id", 0);
    $data["cust_id"] = (int)ifset($data, "cust_id", 0);
    $data["weeks"] = (float)ifset($data, "weeks", 0);
    if ($data["item_id"] <= 0 || $data["cust_id"] <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid booking"]);
        exit;
    }
    // make timestamp to unix timestamp
    $from = strtotime((string)ifset($data, "start_date", ""));
    $to = strtotime((string)ifset($data, "end_date", ""));
    if ($from === false || $to === false || $to < $from) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid booking"]);
        exit;
    }
    // Only for the customer Mit salg signed in at this regnskab (mySaleSignIn() in mysale.php): remote.js sends the
    // customer of the page's Mit salg link (getDB.php). The session is only read here.
    if (session_status() === PHP_SESSION_NONE && isset($_COOKIE[session_name()])) {
        @session_start(['read_and_close' => true]);
    }
    if (!mySaleSignedInAs($_SESSION ?? array(), session_id(), $data["cust_id"], $db)) {
        http_response_code(403);
        echo json_encode(["error" => "Not signed in"]); // remote.js sends the visitor to mysale.php to sign in
        exit;
    }
    $expiryTime = time() + 240;
    $expiryTime = date('Y-m-d H:i:s', $expiryTime);
    // if there is closed dates between the selected dates extend the end date by the number of days closed
    while($row = db_fetch_array($query)){
        $closedDate = strtotime($row["day"]);
        if($closedDate >= $from && $closedDate <= $to){
            $to = strtotime("+1 day", $to);
        }
    }
    // The calendar only offers free days, but the request can name any stand and period: check the period as extended above.
    // The stand stays locked from the check until its hold is committed, so a second request for it waits and then finds the hold.
    if (!rental_begin_stand_lock($data["item_id"])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid booking"]);
        exit;
    }
    if (rental_period_taken($data["item_id"], $from, $to)) {
        transaktion('rollback');
        http_response_code(409);
        echo json_encode(["error" => "The stand is not free in that period"]);
        exit;
    }
    db_modify("INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id, expiry_time) VALUES ($data[item_id], $data[cust_id], $from, $to, 0, TO_TIMESTAMP('$expiryTime', 'YYYY-MM-DD HH24:MI:SS'))", __FILE__ . " linje " . __LINE__);
    $query = db_select("SELECT id FROM rentalperiod WHERE item_id = $data[item_id] AND cust_id = $data[cust_id] AND rt_from = $from AND rt_to = $to", __FILE__ . " linje " . __LINE__);
    $data["booking_id"] = db_fetch_array($query)["id"];
    // updateBooking approves or rejects only a booking this session made: without the record in the session there is no hold.
    // Mit salg keeps it apart from the booking page's (remoteBooking/api.php), so only this page acts on it.
    if (!rental_booking_note_in_session(rental_booking_mysale_notes($db), $data["booking_id"])) {
        transaktion('rollback');
        http_response_code(400);
        echo json_encode(["error" => "Invalid booking"]);
        exit;
    }
    transaktion('commit');
    if($data["unit"] == "Dag"){
        $data["days"] = $data["weeks"];
    }else{
        $data["days"] = $data["weeks"] * 7;
    }
    $data["fromDate"] = date("Y-m-d", $from);
    $data["toDate"] = date("Y-m-d", $to);
    CreateOrder($data);
    echo json_encode(["id" => $data["booking_id"]]);
}

if(isset($_GET["updateBooking"])){
    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) $data = array();
    $status = ifset($data, "status", "");
    // Only a booking this visitor's session made through Mit salg (createBooking recorded it there). The session stays
    // open until the answer, so a second request of the session about the booking waits for this one and finds what it did.
    // The id is asked about as the request sent it and cast only after that, so a list, a float or text such as '41abc'
    // is answered 403.
    if (!rental_booking_noted_in_session(rental_booking_mysale_notes($db), ifset($data, "id", 0))) {
        http_response_code(403);
        echo json_encode(["error" => "Unknown booking"]);
        exit;
    }
    $bookingId = (int)$data["id"];
    $query = db_select("SELECT * FROM rentalperiod WHERE id = $bookingId", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $orderId = $res ? (int)$res["order_id"] : 0;
    // and only while Mit salg has the booking's customer signed in at this regnskab in the session. A booking that is gone
    // (its hold expired and was removed) has no customer to ask about: then the customer the session is signed in as.
    if (!mySaleSignedInAs($_SESSION, session_id(), $res ? $res["cust_id"] : ($_SESSION["mySaleAcId"] ?? 0), $db)) {
        http_response_code(403);
        echo json_encode(["error" => "Not signed in"]);
        exit;
    }
    if($status != "approved"){
        // An invoiced order is never deleted. Its row is locked before it is read, so an invoicing of the order at the
        // same time is either seen here or waits until the order is gone.
        transaktion('begin');
        $order = $orderId > 0 ? db_fetch_array(db_select("SELECT status, fakturanr FROM ordrer WHERE id = $orderId FOR UPDATE", __FILE__ . " linje " . __LINE__)) : false;
        if ($order && ((int)$order["status"] >= 3 || trim((string)$order["fakturanr"]) !== "")) {
            transaktion('rollback');
            http_response_code(409);
            echo json_encode(["error" => "The booking is invoiced"]);
            exit;
        }
        if ($orderId > 0) { // order_id is 0 for a hold whose order was not made: it has no lines to delete
            db_modify("DELETE FROM ordrelinjer WHERE ordre_id = $orderId", __FILE__ . " linje " . __LINE__);
        }
        db_modify("DELETE FROM ordrer WHERE id = $orderId", __FILE__ . " linje " . __LINE__);
        db_modify("DELETE FROM rentalperiod WHERE id = $bookingId", __FILE__ . " linje " . __LINE__);
        transaktion('commit');
        rental_booking_unnote($_SESSION, rental_booking_mysale_notes($db), $bookingId); // saved with the session when the request ends
        echo json_encode(["status" => "deleted"]);
        exit;
    }else{
        $order = $orderId > 0 ? db_fetch_array(db_select("SELECT status, fakturanr FROM ordrer WHERE id = $orderId", __FILE__ . " linje " . __LINE__)) : false;
        if (!$order) { // no hold, or a hold whose order was not made: nothing to invoice
            http_response_code(409);
            echo json_encode(["error" => "The booking has no order"]);
            exit;
        }
        if ((int)$order["status"] >= 3 || trim((string)$order["fakturanr"]) !== "") {
            // Invoiced already, by an earlier approval or by staff while the hold waited: nothing more to invoice. The
            // hold is a booking now and no longer expires, so rental/rental.php does not remove it.
            db_modify("UPDATE rentalperiod SET expiry_time = NULL WHERE id = $bookingId AND expiry_time IS NOT NULL", __FILE__ . " linje " . __LINE__);
            echo json_encode(["status" => "approved"]);
            exit;
        }
        // fakturer_ordre() answers the order id when it invoiced the order, and otherwise what stopped it.
        if (fakturer_ordre($orderId, "email", 0) !== $orderId) {
            http_response_code(500);
            echo json_encode(["error" => "The booking could not be invoiced"]);
            exit;
        }
        db_modify("UPDATE rentalperiod SET expiry_time = NULL WHERE id = $bookingId", __FILE__ . " linje " . __LINE__);
        echo json_encode(["status" => "approved"]);
    }

}

if(isset($_GET["getClosedDates"])){
    $query = db_select("SELECT * FROM rentalclosed", __FILE__ . " linje " . __LINE__);
    $i = 0;
    $closedDays = []; // a list even when no day is closed: remote.js maps over it to build the calendar
    while($res = db_fetch_array($query)){
        $closedDays[$i]["id"] = $res["id"];
        $closedDays[$i]["date"] = $res["day"];
        $i++;
    }
    echo json_encode($closedDays);
}

if(isset($_GET["getAllBookingsByUser"])){
    $id = (int)$_GET["getAllBookingsByUser"];
    // Only for the customer Mit salg signed in at this regnskab (mySaleSignIn() in mysale.php, once it has accepted the
    // customer's link, and the password when the customer has one): the same id at another shop is another customer.
    // The session is only read.
    if (session_status() === PHP_SESSION_NONE && isset($_COOKIE[session_name()])) {
        @session_start(['read_and_close' => true]);
    }
    if (!mySaleSignedInAs($_SESSION ?? array(), session_id(), $id, $db)) {
        http_response_code(403);
        echo json_encode([]); // a list all the same: mybooking.js sends the visitor to mysale.php to sign in
        exit;
    }
    // make a join for rentalitem on item_id from rentalperiod to id in rentalitems
    $query = db_select("SELECT rp.id, rp.rt_from, rp.rt_to, ri.item_name FROM rentalperiod rp LEFT JOIN rentalitems ri ON rp.item_id = ri.id WHERE rp.cust_id = $id", __FILE__ . " linje " . __LINE__);
    $bookings = [];
    while($res = db_fetch_array($query)){
        $bookings[] = $res;
    }
    echo json_encode($bookings);
}

if(isset($_GET["getTerms"])){
    $query = db_select("SELECT trade_conditions FROM rentalpayment", __FILE__ . " linje " . __LINE__);
    $terms = db_fetch_array($query)["trade_conditions"];
    echo json_encode(["terms" => $terms]);
}

function CreateOrder($data){
    global $regnaar;
    global $momssats; // opret_ordrelinje() gives the item line no higher rate than the order's, which it reads from here
    $query = db_select("SELECT box1, box2, box3, box4, kodenr FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__);
    $currentYear = date('Y');
    $currentMonth = date('m');
    $momssats = 0;

    $query = db_select("SELECT gruppe FROM varer WHERE varenr = '" . db_escape_string((string)ifset($data, "sku", "")) . "'", __FILE__ . " linje " . __LINE__);
    $gruppe = db_fetch_array($query)["gruppe"];

    $q_vg = db_select("SELECT beskrivelse, kodenr, box4, box7 FROM grupper WHERE fiscal_year = " . (int)$regnaar . " AND art = 'VG' AND kodenr = '" . (int)$gruppe . "'", __FILE__ . " linje " . __LINE__);
    $r_vg = db_fetch_array($q_vg);

    if ($r_vg && is_array($r_vg)) {
        # Get momssats, checks if it is not momsfri
        if ($r_vg["box7"] != "on") {
            $q_konto = db_select("SELECT moms FROM kontoplan WHERE regnskabsaar = " . (int)$regnaar . " AND kontonr = " . (int)$r_vg['box4'], __FILE__ . " linje " . __LINE__);
            $konto_result = db_fetch_array($q_konto);
            $momstype = $konto_result ? $konto_result["moms"] : null;

            if ($momstype) {
                $momstype = trim($momstype, 'S');
                $q_moms = db_select("SELECT box2 FROM grupper WHERE fiscal_year = " . (int)$regnaar . " AND kode = 'S' AND kodenr = '" . (int)$momstype . "' AND art = 'SM'", __FILE__ . " linje " . __LINE__);
                $moms_result = db_fetch_array($q_moms);
                $momssats = $moms_result ? $moms_result["box2"] : 0;
            } else {
                $momssats = 0;
            }
        } else {
            $momssats = 0;
        }
    } else {
        // Handle the case where $r_vg is not an array
        error_log("Failed to fetch data for gruppe: $gruppe");
    }
    
    $query = db_select("SELECT * FROM rentalitems WHERE id = " . (int)$data["item_id"], __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $query = db_select("SELECT invoice_date FROM rentalsettings", __FILE__ . " linje " . __LINE__);
    $invoiceDate = db_fetch_array($query)["invoice_date"];
    $product["product_id"] = $res["product_id"];
    $product["name"] = $res["item_name"];
    $query = db_select("SELECT * FROM varer WHERE id = " . (int)$product["product_id"], __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $product["description"] = $res["beskrivelse"];
    $product["product_number"] = $res["varenr"];
    $product["disc_type"] = $res["m_type"];
    $product["unit"] = $res["enhed"];
    if($product["unit"] == "Dag"){
        $weeks = floor($data["days"]);
    }else{
        $weeks = floor($data["days"]/7);
    }
    if(strtolower($res["enhed"]) == "dag"){
        $paidWeeks = $data["days"];
    }else{
        $paidWeeks = number_format($data["days"]/7, 2);
    }
    $discountPeriods = array();
    $discountArray = array();
        
    if($res["m_antal"] != "" && $res["m_rabat"] != "" && $res["m_antal"] != "0" && $res["m_rabat"] != "0"){
    if(strpos($res["m_antal"], ";")){
        $discountPeriods = explode(";", $res["m_antal"]);
        $discountArray = explode(";", $res["m_rabat"]);
    }else{
        $discountPeriods[0] = $res["m_antal"];
        $discountArray[0] = $res["m_rabat"];
    }
    $i = -1;
    foreach($discountPeriods as $period){
        if($period <= $weeks){
            $i++;
        }
    }
    if($i > -1){
        if($product["disc_type"] == "percent"){
            if(strtolower($res["enhed"]) == "dag"){
                $discount = $discountArray[$i];
                $product["price"] = $res["salgspris"] * $data["days"];
                $discountAmount = ($product["price"] * $discount) / 100;
                $rabatart = "percent";
            }else{
                $discount = $discountArray[$i];
                $product["price"] = $res["salgspris"] * $paidWeeks;
                $discountAmount = ($product["price"] * $discount) / 100;
                $rabatart = "percent";
            }
        }else{
            if(strtolower($res["enhed"]) == "dag"){
                $discount = $discountArray[$i];
                $product["price"] = $res["salgspris"] * $data["days"];
                $discountAmount = $discount * $data["days"];
                $rabatart = "amount";
            }else{
                $discount = $discountArray[$i];
                $product["price"] = $res["salgspris"] * $paidWeeks;
                $discountAmount =  $discount * $paidWeeks;
                $rabatart = "amount";
            }
        }
    }else{
        if(strtolower($res["enhed"]) == "dag"){
            $product["price"] = $res["salgspris"] * $data["days"];
            $discountAmount = 0.00;
            $discount = 0.00;
            $rabatart = "";
        }else{
            $product["price"] = $res["salgspris"] * $paidWeeks;
            $discountAmount = 0.00;
            $discount = 0.00;
            $rabatart = "";
        }
    }
    }else{
        if(strtolower($res["enhed"]) == "dag"){
            $product["price"] = $res["salgspris"] * $data["days"];
            $discountAmount = 0.00;
            $discount = 0.00;
            $rabatart = "";
        }else{
            $product["price"] = $res["salgspris"] * $paidWeeks;
            $discountAmount = 0.00;
            $discount = 0.00;
            $rabatart = "";
        }
    }
    $sum = ($product["price"] - $discountAmount);
    $moms = ($product["price"] - $discountAmount) * 0.25;

    $basePrice = $res["salgspris"];
    
    
    $query = db_select("SELECT * FROM adresser WHERE id = " . (int)$data["cust_id"], __FILE__ . " linje " . __LINE__);
    $res2 = db_fetch_array($query);
    $customer["id"] = $res2["id"];
    $customer["name"] = $res2["firmanavn"];
    $customer["account_number"] = $res2["kontonr"];
    $customer["phone"] = $res2["tlf"];
    $customer["email"] = $res2["email"];
    $customer["address"] = $res2["addr1"];
    $customer["zip"] = $res2["postnr"];
    $customer["city"] = $res2["bynavn"];
    $customer["art"] = "DO";
    $customer["valuta"] = "DKK";
    $customer["payment_condition"] = "netto";
    $customer["payment_days"] = 1;
    $customer["konto_id"] = (int)$res2["id"];
    $enhed = $res["enhed"];
    if(strtolower($enhed) == "dag" && $data["days"] > 1){
        $enhed = "Dage";
    }elseif(strtoLower($enhed) == "uge" && $paidWeeks > 1){
        $enhed = "Uger";
    }
    $esc = array();
    foreach (array("name", "address", "zip", "city", "email", "account_number") as $key) {
        $esc[$key] = db_escape_string((string)$customer[$key]);
    }
    $date = date("Y-m-d");
    $ordrenr = get_next_order_number('DO');
    if($invoiceDate){
        $query = db_modify("INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsdage, kontonr, art, valuta, ordredate, fakturadate, levdate, ordrenr, sum, status, konto_id, momssats, nextfakt, moms) VALUES ('$esc[name]', '$esc[address]', '$esc[zip]', '$esc[city]', '$esc[email]', $customer[payment_days], '$esc[account_number]', '$customer[art]', '$customer[valuta]', '$date', '$data[fromDate]', '$data[fromDate]', $ordrenr, $sum, 1, $customer[konto_id], $momssats, '$data[toDate]', $moms)", __FILE__ . " linje " . __LINE__);
    }else{
        $query = db_modify("INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsdage, kontonr, art, valuta, ordredate, levdate, ordrenr, sum, status, konto_id, momssats, nextfakt, moms) VALUES ('$esc[name]', '$esc[address]', '$esc[zip]', '$esc[city]', '$esc[email]', $customer[payment_days], '$esc[account_number]', '$customer[art]', '$customer[valuta]', '$date', '$data[fromDate]', $ordrenr, $sum, 1, $customer[konto_id], $momssats, '$data[toDate]', $moms)", __FILE__ . " linje " . __LINE__);
    }
    $query = db_select("SELECT id FROM ordrer WHERE ordrenr = $ordrenr AND art LIKE 'D%'", __FILE__ . " linje " . __LINE__);
    $order_id = db_fetch_array($query)["id"];
    opret_ordrelinje($order_id, $product["product_id"], $product["product_number"], $paidWeeks, $product["description"], $basePrice, $discount, '100', 'D', '', '1', '', '', '', $rabatart, '0', '', '', '', '', __LINE__);
    db_modify("INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 2, 0, 0, 0, $order_id, '" . db_escape_string("Stand: " . $product["name"]) . "')", __FILE__ . " linje " . __LINE__);
    db_modify("INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 3, 0, 0, 0, $order_id, 'Udlejning: Fra $data[fromDate] til $data[toDate]')", __FILE__ . " linje " . __LINE__);
    if($discountAmount > 0){
        // reduce decimal to 2 digits
        $discountAmount = number_format($discountAmount, 2);
        // change from . to ,
        $discountAmount = str_replace(".", ",", $discountAmount);
        db_modify("INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 4, 0, 0, 0, $order_id, 'Rabat $discountAmount kr.')", __FILE__ . " linje " . __LINE__);
    }
    db_modify("UPDATE rentalperiod SET order_id = " . (int)$order_id . " WHERE id = " . (int)$data["booking_id"], __FILE__ . " linje " . __LINE__);
}

function fakturer_ordre($saldi_id,$udskriv_til,$pos_betaling) {
	global $db,$db_skriv_id,$regnaar;
	global $db_modify_fejl; #20260729 SZ (SD-595)
	global $webservice; #20260729 SZ was a local shadow, so db_modify() never saw webservice mode as active on write failure and exited hard instead of returning an error (SD-595)
	$brugernavn = "Booking";
	$webservice = 1;
	$saldi_id = (int)$saldi_id; // the order id goes into every query below
	
	/* include("../includes/ordrefunc.php"); */
	
	$log=fopen("../temp/$db/rest_api.log","a");
	fwrite($log,__line__." ".date("Y-m-d H:i:s")."\n");

	$qtxt="select * from ordrelinjer where ordre_id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$linjesum=0;
	while ($r=db_fetch_array($q)) {
		$linjesum+=$r['antal']*$r['pris']-($r['antal']*$r['pris']*$r['rabat']/100);
		fwrite($log,__line__." $linjesum+=$r[antal]*$r[pris]-($r[antal]*$r[pris]*$r[rabat]/100)\n");
	}
	$qtxt="select betalingsbet,tidspkt,sum,moms,felt_1,felt_2 from ordrer where id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$ordresum=$r['sum'];
	$ordremoms=$r['moms'];
	$betalingsbet=$r['betalingsbet'];
	$betalingstype=$r['felt_1'];
	$betalingsum=$r['felt_2'];
	$tidspkt=$r['tidspkt'];
#	$r=db_fetch_array(db_select("select * from ordrer where id = '$saldi_id'",__FILE__ . " linje " . __LINE__));
#	$betalt=$r['sum']+$r['moms'];
#	$korttype=$r['felt_1'];
	$varesum=$varemoms=0;
	$qtxt="select antal,pris,rabat,momssats from ordrelinjer where ordre_id='$saldi_id' and vare_id > 0";
	fwrite($log,__line__." $qtxt\n");
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$linjepris=$r['antal']*($r['pris']-$r['pris']*$r['rabat']/100);
		fwrite($log,__line__." Svar : $linjepris=$r[antal]*($r[pris]-$r[pris]*$r[rabat]/100)\n");
		$linjemoms=$linjepris*$r['momssats']/100;
		$varesum+=afrund($linjepris,3);
		$varemoms+=afrund($linjemoms,3);
		fwrite($log,__line__." $varesum -> $varemoms\n");
	}
	fwrite($log,__line__." abs($ordresum-$varesum)>0.01 || abs($ordremoms-$varemoms)>0.01)\n");
	if (abs($ordresum-$varesum)>0.01 || abs($ordremoms-$varemoms)>0.01) {
		$svar='Error in amount ('.$ordresum.'+'.$ordremoms.') vs. item amount ('.$varesum.'+'.$varemoms.')';
		fwrite($log,__line__." Svar : $svar\n");
		fclose($log);
		return($svar);
		exit;
	}
	transaktion('begin');
	$qtxt="update ordrer set fakturadate=ordredate where id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
	$qtxt="update ordrelinjer set leveres = antal where ordre_id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
	$svar=levering($saldi_id,'on',NULL,'on');
	fwrite($log,__line__." Betalingsbet: $betalingsbet\n");
	if ($betalingsbet!='Forud' && $betalingsbet!='Lb. Md' && $betalingsbet!='Netto') {
		$betalingsdiff=abs($ordresum+$ordremoms-(float)$betalingsum); // felt_2 is text, '' on an order that has no payment
		if ($pos_betaling && $betalingsdiff >= 0.01) {
			fwrite($log,__line__." Ordresum : $ordresum\n");
			fwrite($log,__line__." Ordremoms : $ordremoms\n");
			fwrite($log,__line__." Betalingssum : $betalingsum\n");
			$svar='Error in amount ('.$ordresum.'+'.$ordremoms.') vs. paid amount ('.$betalingsum.') : diff '.$betalingsdiff;
		}
	}
	if ($svar=='OK') {
/*
		if ($pos_betaling) { #20190123
			$qtxt="insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs)values('$saldi_id','$betalingstype','$betalingsum','DKK','100')";
			$qtxt=chk4utf8($qtxt);
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			fwrite($log,__line__." Ordre ID $saldi_id faktureret ($svar)\n");
		}
*/		
		$svar=bogfor($saldi_id,'on');
		if ($tidspkt) {	
			$qtxt="update ordrer set tidspkt='" . db_escape_string((string)$tidspkt) . "' where id='$saldi_id'";
			fwrite($log,__line__." $qtxt\n");
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
		}
	} 
	if ($svar != 'OK') {
		fwrite($log,__line__." Svar : $svar\n");
		fclose($log);
		transaktion('rollback'); #20260805 CL/SZ close the outer transaction on failure too, not just success - otherwise db_transaktion_depth never returns to 0 and the next invoice call in this request won't reset $db_modify_fejl (SD-595, CodeRabbit)
		return($svar);
	}
	fclose ($log);
	transaktion ('commit');
	if ($db_modify_fejl) { #20260729 SZ a write failed inside the posting transaction; do not report success (SD-595)
		return "Database write failed while posting order $saldi_id";
	}
	shopSyncDrainQueue();
	return($saldi_id);
}