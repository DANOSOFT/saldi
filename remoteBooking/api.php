<?php 
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/ordreliste.php --- patch 4.1.0 --- 2024-05-29 ---
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
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
//


$header = "nix";
$bg = "nix";
include("../includes/connect.php");
include("../includes/std_func.php");
$db = $_GET["id"];
include("../includes/ordrefunc.php");

$connection = db_connect($sqhost, $squser, $sqpass, $db);
$query = db_select("SELECT box1, box2, box3, box4, kodenr FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__);
$currentYear = date('Y');
$currentMonth = date('m');
$regnaar;
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
        // Do something with $kodenr
        $regnaar = $kodenr;
    }
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
            /* $row = array_filter($row, function($key) {
                return !is_numeric($key);
            }, ARRAY_FILTER_USE_KEY); */
        
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
                    /* $res3 = array_filter($res3, function($key) {
                        return !is_numeric($key);
                    }, ARRAY_FILTER_USE_KEY); */
                    $row["periods"][] = $res3;
                }
            }
            $res[] = $row;
        }
        echo json_encode($res);
}

if(isset($_GET["getAllDates"])){
    $productId = $_GET["getAllDates"];
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

    $query = db_select("SELECT item_id, rr_from, rr_to FROM rentalreserved WHERE item_id = ANY(ARRAY[" . implode(",", $itemIds) . "])", __FILE__ . " linje " . __LINE__);

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
    // make timestamp to unix timestamp
    $from = strtotime($data["start_date"]);
    $to = strtotime($data["end_date"]);
    $expiryTime = time() + 360;
    $expiryTime = date('Y-m-d H:i:s', $expiryTime);
    $query = db_select("SELECT * FROM rentalclosed", __FILE__ . " linje " . __LINE__);
    // if there is closed dates between the selected dates extend the end date by the number of days closed
    while($row = db_fetch_array($query)){
        $closedDate = strtotime($row["day"]);
        if($closedDate >= $from && $closedDate <= $to){
            $to = strtotime("+1 day", $to);
        }
    }
    db_modify("INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id, expiry_time) VALUES ($data[item_id], $data[cust_id], $from, $to, 0, TO_TIMESTAMP('$expiryTime', 'YYYY-MM-DD HH24:MI:SS'))", __FILE__ . " linje " . __LINE__);
    $query = db_select("SELECT id FROM rentalperiod WHERE item_id = $data[item_id] AND cust_id = $data[cust_id] AND rt_from = $from AND rt_to = $to", __FILE__ . " linje " . __LINE__);
    $data["booking_id"] = db_fetch_array($query)["id"];
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

if(isset($_GET["getRentalReserved"])){
    $query = db_select("SELECT * FROM rentalreserved", __FILE__ . " linje " . __LINE__);
    $res = [];
    while($row = db_fetch_array($query)){
        $res[] = $row;
    }
    echo json_encode($res);
}

if(isset($_GET["createCust"])){
    $data = json_decode(file_get_contents("php://input"), true);
    $data["email"] = strtolower($data["email"]);
    // check if email already exists
    $query = db_select("SELECT id FROM adresser WHERE email = '{$data['email']}'", __FILE__ . " linje " . __LINE__);
    if(db_num_rows($query) > 0){
        $row = db_fetch_array($query);
        $id = $row["id"];
        $url = $_SERVER['REQUEST_URI'];
        $pathSegments = array_filter(explode('/', $url));
        $firstFolder = reset($pathSegments);
        $url = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . "/" . $firstFolder . "/remoteBooking/createCust.php";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        $data["id"] = $id;
        $data["db"] = $_GET["db"];
        $data["email"] = strtolower($data["email"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        echo json_encode(["id" => $id]);
        return;
    }
    $query = db_select("SELECT kontonr FROM adresser WHERE kontonr::integer BETWEEN 1000 AND 9999 ORDER BY kontonr ASC", __FILE__ . " linje " . __LINE__);
    $taken = [];
    while($row = db_fetch_array($query)){
        $taken[] = (int)$row["kontonr"];
    }

    $nextFreeKontonr = null;
    for ($i = 1000; $i <= 9999; $i++) {
        if (!in_array($i, $taken)) {
            $nextFreeKontonr = $i;
            break;
        }
    }
    
    $data["cust_nr"] = $nextFreeKontonr;
    // create date in format YYYY-MM-DD
    $date = date("Y-m-d");
    db_modify("INSERT INTO adresser (firmanavn, addr1, postnr, bynavn, tlf, email, kontonr, gruppe, mysale, art, oprettet, betalingsbet) VALUES ('{$data['name']}', '{$data['addr']}', '{$data['zip']}', '{$data['city']}', '{$data['tlf']}', '{$data['email']}', '{$data["cust_nr"]}', 1, 'on', 'D', '$date', 'Netto')", __FILE__ . " linje " . __LINE__);
    $query = db_select("SELECT id FROM adresser ORDER BY id DESC LIMIT 1", __FILE__ . " linje " . __LINE__);
    $id = db_fetch_array($query)["id"];

    $url = $_SERVER['REQUEST_URI'];
    $pathSegments = array_filter(explode('/', $url));
    $firstFolder = reset($pathSegments);
    $url = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . "/" . $firstFolder . "/remoteBooking/createCust.php";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    $data["id"] = $id;
    $data["db"] = $db;
    $data["email"] = strtolower($data["email"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    echo json_encode(["id" => $id]);
}

function fakturer_ordre($saldi_id,$udskriv_til,$pos_betaling) {
	global $db,$db_skriv_id,$regnaar;
	$brugernavn = "Booking";
	$webservice = 1;
	
	
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
		$betalingsdiff=abs($ordresum+$ordremoms-$betalingsum);
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
			$qtxt="update ordrer set tidspkt='$tidspkt' where id='$saldi_id'";
			fwrite($log,__line__." $qtxt\n");
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
		}
	} 
	if ($svar != 'OK') {
		fwrite($log,__line__." Svar : $svar\n");
		fclose($log);
		return($svar);
	}
	fclose ($log);
	transaktion ('commit');
	return($saldi_id); 
}

if(isset($_GET["updateBooking"])){
    $data = json_decode(file_get_contents("php://input"), true);
    $status = $data["status"];
    if($status != "approved"){
        $query = db_select("SELECT * FROM rentalperiod WHERE id = $data[id]", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $orderId = $res["order_id"];
        db_modify("DELETE FROM ordrer WHERE id = $orderId", __FILE__ . " linje " . __LINE__);
        db_modify("DELETE FROM rentalperiod WHERE id = $data[id]", __FILE__ . " linje " . __LINE__);
        echo json_encode(["status" => "deleted"]);
        exit;
    }else{
        $query = db_select("SELECT * FROM rentalperiod WHERE id = $data[id]", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $orderId = $res["order_id"];
        db_modify("UPDATE rentalperiod SET expiry_time = NULL WHERE id = $data[id]", __FILE__ . " linje " . __LINE__);
        fakturer_ordre($orderId, "email", 0);
        echo json_encode(["status" => "approved"]);
    }
}

if(isset($_GET["getOrder"])){
    $id = $_GET["getOrder"];
    $query = db_select("SELECT * FROM rentalperiod WHERE id = $id", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $query = db_select("SELECT * FROM ordrer WHERE id = $res[order_id]", __FILE__ . " linje " . __LINE__);
    $order = db_fetch_array($query);
    $query = db_select("SELECT * FROM ordrelinjer WHERE ordre_id = $res[order_id]", __FILE__ . " linje " . __LINE__);
    $orderLines = [];
    while($row = db_fetch_array($query)){
        $orderLines[] = $row;
    }
    $order["orderLines"] = $orderLines;
    echo json_encode($order);
}

if(isset($_GET["getClosedDates"])){
    $query = db_select("SELECT * FROM rentalclosed", __FILE__ . " linje " . __LINE__);
    $i = 0;
    if(db_num_rows($query) <= 0){
        echo json_encode(["msg" => "Der er ingen lukkede dage", "success" => false]);
        exit();
    }
    while($res = db_fetch_array($query)){
        $closedDays[$i]["id"] = $res["id"];
        $closedDays[$i]["date"] = $res["day"];
        $i++;
    }
    echo json_encode($closedDays);
}

function CreateOrder($data){
    global $regnaar;
    $query = db_select("SELECT box1, box2, box3, box4, kodenr FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__);
    $currentYear = date('Y');
    $currentMonth = date('m');
    $momssats = 0;

    $query = db_select("SELECT gruppe FROM varer WHERE varenr = '$data[sku]'", __FILE__ . " linje " . __LINE__);
    $gruppe = db_fetch_array($query)["gruppe"];

    $q_vg = db_select("SELECT beskrivelse, kodenr, box4, box7 FROM grupper WHERE fiscal_year = $regnaar AND art = 'VG' AND kodenr = $gruppe", __FILE__ . " linje " . __LINE__);
    $r_vg = db_fetch_array($q_vg);

    if ($r_vg && is_array($r_vg)) {
        # Get momssats, checks if it is not momsfri
        if ($r_vg["box7"] != "on") {
            $q_konto = db_select("SELECT moms FROM kontoplan WHERE regnskabsaar = $regnaar AND kontonr = {$r_vg['box4']}", __FILE__ . " linje " . __LINE__);
            $konto_result = db_fetch_array($q_konto);
            $momstype = $konto_result ? $konto_result["moms"] : null;

            if ($momstype) {
                $momstype = trim($momstype, 'S');
                $q_moms = db_select("SELECT box2 FROM grupper WHERE fiscal_year = $regnaar AND kode = 'S' AND kodenr = $momstype AND art = 'SM'", __FILE__ . " linje " . __LINE__);
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
    
    $query = db_select("SELECT * FROM rentalitems WHERE id = $data[item_id]", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $query = db_select("SELECT invoice_date FROM rentalsettings", __FILE__ . " linje " . __LINE__);
    $invoiceDate = db_fetch_array($query)["invoice_date"];
    $product["product_id"] = $res["product_id"];
    $product["name"] = $res["item_name"];
    $query = db_select("SELECT * FROM varer WHERE id = $product[product_id]", __FILE__ . " linje " . __LINE__);
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
    $incMoms = $sum + $moms;
    $basePrice = $res["salgspris"];
    
    
    $query = db_select("SELECT * FROM adresser WHERE id = $data[cust_id]", __FILE__ . " linje " . __LINE__);
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
    $customer["konto_id"] = $res2["id"];
    $enhed = $res["enhed"];
    if(strtolower($enhed) == "dag" && $data["days"] > 1){
        $enhed = "Dage";
    }elseif(strtoLower($enhed) == "uge" && $paidWeeks > 1){
        $enhed = "Uger";
    }
    $date = date("Y-m-d");
    $ordrenr = get_next_order_number('DO');
    if($invoiceDate){
        $query = db_modify("INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsdage, kontonr, art, valuta, ordredate, fakturadate, levdate, ordrenr, sum, status, konto_id, momssats, nextfakt, moms, betalingsbet, udskriv_til) VALUES ('$customer[name]', '$customer[address]', '$customer[zip]', '$customer[city]', '$customer[email]', $customer[payment_days], '$customer[account_number]', '$customer[art]', '$customer[valuta]', '$date', '$data[fromDate]', '$data[fromDate]', $ordrenr, $sum, 1, $customer[konto_id], $momssats, '$data[toDate]', $moms, 'Kreditkort', 'email')", __FILE__ . " linje " . __LINE__);
    }else{
        $query = db_modify("INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsdage, kontonr, art, valuta, ordredate, levdate, ordrenr, sum, status, konto_id, momssats, nextfakt, moms, betalingsbet, udskriv_til) VALUES ('$customer[name]', '$customer[address]', '$customer[zip]', '$customer[city]', '$customer[email]', $customer[payment_days], '$customer[account_number]', '$customer[art]', '$customer[valuta]', '$date', '$data[fromDate]', $ordrenr, $sum, 1, $customer[konto_id], $momssats, '$data[toDate]', $moms, 'Kreditkort', 'email')", __FILE__ . " linje " . __LINE__);
    }
    $query = db_select("SELECT id FROM ordrer WHERE ordrenr = $ordrenr AND art LIKE 'D%'", __FILE__ . " linje " . __LINE__);
    $order_id = db_fetch_array($query)["id"];
    opret_ordrelinje($order_id, $product["product_id"], $product["product_number"], $paidWeeks, $product["description"], $basePrice, $discount, '100', 'D', '', '1', '', '', '', $rabatart, '0', '', '', '', '', __LINE__, 25);
    db_modify("INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 2, 0, 0, 0, $order_id, 'Stand: $product[name]')", __FILE__ . " linje " . __LINE__);
    db_modify("INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 3, 0, 0, 0, $order_id, 'Udlejning: Fra $data[fromDate] til $data[toDate]')", __FILE__ . " linje " . __LINE__);
    if($discountAmount > 0){
        // reduce decimal to 2 digits
        $discountAmount = number_format($discountAmount, 2);
        // change from . to ,
        $discountAmount = str_replace(".", ",", $discountAmount);
        db_modify("INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 4, 0, 0, 0, $order_id, 'Rabat $discountAmount kr.')", __FILE__ . " linje " . __LINE__);
    }
    db_modify("UPDATE rentalperiod SET order_id = $order_id WHERE id = $data[booking_id]", __FILE__ . " linje " . __LINE__);
}
