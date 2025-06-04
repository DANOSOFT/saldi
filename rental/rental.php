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
// 20240529 PHR Block for deleteting invoiced orders
// 20240603 PBLM Fixed booking deletion when invoice is credited

    @session_start();
    $s_id=session_id();
    $header = "nix";
    $bg = "nix";
    include("../includes/connect.php");
    include("../includes/online.php");
/*     $query = db_select("SELECT * FROM online WHERE session_id = '$s_id' ORDER BY logtime DESC LIMIT 1", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $db = trim($res["db"]);
    $connection = db_connect($sqhost, $squser, $sqpass, $db); */

    /* if(isset($_GET["customers"])){
        // To-do: get all customers that have a booking within the last month
        #$date = strtotime("-1 month");
        #$query = db_select("SELECT * FROM rentalperiod WHERE rt_to > $date", __FILE__ . " linje " . __LINE__);
        $query = db_select("SELECT * FROM rentalperiod", __FILE__ . " linje " . __LINE__);
        $i = 0;
        if(db_num_rows($query) <= 0){
            echo json_encode("Der er ingen bookinger");
            exit();
        }
        while($res = db_fetch_array($query)){
            $query2 = db_select("SELECT id, firmanavn, kontonr, tlf FROM adresser WHERE id = $res[cust_id]", __FILE__ . " linje " . __LINE__);
            $res2 = db_fetch_array($query2);
            $query3 = db_select("SELECT * FROM rentalitems WHERE id = $res[item_id]", __FILE__ . " linje " . __LINE__);
            $res3 = db_fetch_array($query3);
            if($res["order_id"] != 0){
                $query4 = db_select("SELECT status FROM ordrer WHERE id = $res[order_id]", __FILE__ . " linje " . __LINE__);
                $res4 = db_fetch_array($query4);
                if($res4["status"] >= 3){
                    $customers[$i]["order_status"] = 1;
                }else{
                    $customers[$i]["order_status"] = 0;
                }
            }else{
                $customers[$i]["order_status"] = 0;
            }

            $customers[$i]["tlf"] = $res2["tlf"];
            $customers[$i]["name"] = $res2["firmanavn"];
            $customers[$i]["account_number"] = $res2["kontonr"];
            $customers[$i]["from"] = $res["rt_from"];
            $customers[$i]["to"] = $res["rt_to"];
            $customers[$i]["item_id"] = $res["item_id"];
            $customers[$i]["id"] = $res2["id"];
            $customers[$i]["booking_id"] = $res["id"];
            $customers[$i]["item_name"] = $res3["item_name"];
            $customers[$i]["product_id"] = $res3["product_id"];
            $i++;
        }
        echo json_encode($customers);
    } */

    $query = db_select("SELECT column_name FROM information_schema.columns WHERE table_name = 'rentalsettings' AND column_name = 'toggle_order'", __FILE__ . " linje " . __LINE__);
    if(db_num_rows($query) < 1){
        db_modify("ALTER TABLE rentalsettings ADD COLUMN toggle_order INTEGER", __FILE__ . " linje " . __LINE__);
        db_modify("UPDATE rentalsettings SET toggle_order = 1", __FILE__ . " linje " . __LINE__);
    }

    // This function is for external booking only "IT IS NOT FOR DELETING BOOKINGS THAT ARE OLD"
    function cleanUpExpiredBookings(){
        $currentTime = time();
        $currentTimeFormatted = date('Y-m-d H:i:s', $currentTime); // Format current time as a string
    
        $query = "
            DELETE FROM rentalperiod
            WHERE expiry_time < TO_TIMESTAMP('$currentTimeFormatted', 'YYYY-MM-DD HH24:MI:SS')
        ";
        db_modify($query, __FILE__ . " linje " . __LINE__);
    }
    
    cleanUpExpiredBookings();

    function createProducts(){
        $query = db_select("SELECT id FROM varer WHERE id IN (SELECT DISTINCT product_id FROM rentalitems)", __FILE__ . " linje " . __LINE__);
        while($row = db_fetch_array($query)){
            // check that they dont already exist in rentalremote
            $query2 = db_select("SELECT * FROM rentalremote WHERE product_id = $row[id]", __FILE__ . " linje " . __LINE__);
            if(db_num_rows($query2) > 0){
                continue;
            }
            db_modify("INSERT INTO rentalremote (product_id, is_active) VALUES ($row[id], 0)", __FILE__ . " linje " . __LINE__);
            $query = db_select("SELECT id FROM rentalremote WHERE product_id = $row[id]", __FILE__ . " linje " . __LINE__);
            $res = db_fetch_array($query);
            $i = 1;
            while($i < 5){
                db_modify("INSERT INTO rentalremoteperiods (rentalremote_id, amount) VALUES ($res[id], $i)", __FILE__ . " linje " . __LINE__);
                $i++;
            }
        }
    }

    createProducts();

    if(isset($_GET["customers"])){
        // To-do: get all customers that have a booking within the last month
        $month = $_GET["month"]; // The month you want to filter by
        $year = $_GET["year"]; // The year you want to filter by

        $query = db_select("SELECT rp.*, a.id as aid, a.firmanavn, a.kontonr, a.tlf, ri.*, o.status as order_status,
        rp.id as booking_id, ri.item_name, ri.product_id
        FROM rentalperiod rp
        LEFT JOIN adresser a ON rp.cust_id = a.id
        LEFT JOIN rentalitems ri ON rp.item_id = ri.id
        LEFT JOIN ordrer o ON rp.order_id = o.id
        WHERE (
            (EXTRACT(YEAR FROM to_timestamp(rp.rt_from)) < $year OR 
            (EXTRACT(YEAR FROM to_timestamp(rp.rt_from)) = $year AND EXTRACT(MONTH FROM to_timestamp(rp.rt_from)) <= ($month + 1))) AND 
            (EXTRACT(YEAR FROM to_timestamp(rp.rt_from)) > $year OR 
            (EXTRACT(YEAR FROM to_timestamp(rp.rt_from)) = $year AND EXTRACT(MONTH FROM to_timestamp(rp.rt_from)) >= ($month - 1)))
        ) OR (
            (EXTRACT(YEAR FROM to_timestamp(rp.rt_to)) < $year OR 
            (EXTRACT(YEAR FROM to_timestamp(rp.rt_to)) = $year AND EXTRACT(MONTH FROM to_timestamp(rp.rt_to)) <= ($month + 1))) AND 
            (EXTRACT(YEAR FROM to_timestamp(rp.rt_to)) > $year OR 
            (EXTRACT(YEAR FROM to_timestamp(rp.rt_to)) = $year AND EXTRACT(MONTH FROM to_timestamp(rp.rt_to)) >= ($month - 1)))
        )", __FILE__ . " linje " . __LINE__);
        $i = 0;
        if(db_num_rows($query) <= 0){
            echo json_encode("Der er ingen bookinger");
            exit();
        }
        while($res = db_fetch_array($query)){
            if($res["order_id"] != 0){
                if($res["order_status"] >= 3){
                    $customers[$i]["order_status"] = 1;
                }else{
                    $customers[$i]["order_status"] = 0;
                }
            }else{
                $customers[$i]["order_status"] = 0;
            }
            $customers[$i]["tlf"] = $res["tlf"];
            $customers[$i]["name"] = $res["firmanavn"];
            $customers[$i]["account_number"] = $res["kontonr"];
            $customers[$i]["from"] = $res["rt_from"];
            $customers[$i]["to"] = $res["rt_to"];
            $customers[$i]["item_id"] = $res["item_id"];
            $customers[$i]["id"] = $res["aid"];
            $customers[$i]["booking_id"] = $res["booking_id"];
            $customers[$i]["item_name"] = $res["item_name"];
            $customers[$i]["product_id"] = $res["product_id"];
            $i++;
        }
        echo json_encode($customers);
    }

    if(isset($_GET["customer"])){
        $query = db_select("SELECT id, firmanavn FROM adresser WHERE kontonr = '$_GET[customer]'", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $query2 = db_select("SELECT * FROM rentalperiod WHERE cust_id = $res[id]", __FILE__ . " linje " . __LINE__);
        $res2 = db_fetch_array($query2);
        $customer["name"] = $res["firmanavn"];
        $customer["account_number"] = $_GET["customer"];
        $customer["from"] = $res2["rt_from"];
        $customer["to"] = $res2["rt_to"];

        echo json_encode($customer);
    }

    if(isset($_GET["getAllCustomers"])){
        $query = db_select("SELECT id, firmanavn, kontonr, tlf FROM adresser ORDER BY firmanavn ASC", __FILE__ . " linje " . __LINE__);
        $i = 0;

        while($res = db_fetch_array($query)){
            $customers[$i]["id"] = $res["id"];
            $customers[$i]["name"] = $res["firmanavn"];
            $customers[$i]["account_number"] = $res["kontonr"];
            $customers[$i]["phone"] = $res["tlf"];
            $i++;
        }

        echo json_encode($customers);
    }

    if(isset($_GET["getAllItems"])){
        $query = db_select("SELECT * FROM rentalitems ORDER BY id ASC", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) <= 0){
            echo json_encode("Der er ingen stande");
            exit();
        }
        $i = 0;
        while($res = db_fetch_array($query)){
            $query2 = db_select("SELECT beskrivelse FROM varer WHERE id=$res[product_id]", __FILE__ . " linje " . __LINE__);
            $items[$i]["id"] = $res["id"];
            $items[$i]["item_name"] = $res["item_name"];
            $items[$i]["product_id"] = $res["product_id"];
            $items[$i]["product_name"] = db_fetch_array($query2)["beskrivelse"];
            $i++;
        }

        echo json_encode($items);
    }

    if(isset($_GET["getAllItemsFromId"])){
        $id = $_GET["getAllItemsFromId"];
        $query = db_select("SELECT * FROM rentalitems WHERE product_id = $id ORDER BY item_name ASC", __FILE__ . " linje " . __LINE__);
        $i = 0;
        while($res = db_fetch_array($query)){
            $items[$i]["id"] = $res["id"];
            $items[$i]["item_name"] = $res["item_name"];
            $items[$i]["product_id"] = $res["product_id"];
            $i++;
        }

        echo json_encode($items);
    }

    if(isset($_GET["createBooking"])){
        $data = json_decode(file_get_contents('php://input'), true);
        if($data["from"] === "" || $data["to"] === "" || $data["item_id"] === "" || $data["customer_id"] === ""){
            echo json_encode("Udfyld alle felter");
            exit();
        }
        
        $item_id = db_escape_string($data["item_id"]);
        $customer_id = db_escape_string($data["customer_id"]);
        $from = db_escape_string($data["from"]);
        $to = db_escape_string($data["to"]);
        $to = intval($to);
        $from = intval($from);
        $customer_id = intval($customer_id);
        $item_id = intval($item_id);
        $query = db_select("SELECT * FROM rentalperiod WHERE item_id = $item_id AND cust_id = $customer_id AND rt_from = $from AND rt_to = $to", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) > 0){
            exit();
        }
        $query = db_modify("INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id) VALUES ($item_id, $customer_id, $from, $to, 0)", __FILE__ . " linje " . __LINE__);
        $query = db_select("SELECT id FROM rentalperiod WHERE item_id = $item_id AND cust_id = $customer_id AND rt_from = $from AND rt_to = $to", __FILE__ . " linje " . __LINE__);
        $id = db_fetch_array($query)["id"];
        if($query){
            echo json_encode(["id" => $id, "msg" => "Booking oprettet"]);
        }else{
            echo json_encode(["msg" => "Booking kunne ikke oprettes"]);
        }
    }

    if(isset($_GET["deleteBooking"])){
        $id = db_escape_string($_GET["deleteBooking"]);
        if(strpos($id, "\n") !== false)
            $id = str_replace("\n", "", $id);
        $query = db_select("SELECT order_id FROM rentalperiod WHERE id = $id", __FILE__ . " linje " . __LINE__);
        $order_id = db_fetch_array($query)["order_id"];
        $query = db_select("SELECT * FROM ordrer WHERE id = $order_id", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) > 0){
            $res = db_fetch_array($query);
            if($res["status"] >= 3){
                $query = db_select("SELECT id FROM ordrer WHERE kred_ord_id = $order_id", __FILE__ . " linje " . __LINE__);
                if(db_num_rows($query) > 0){
                    db_modify("DELETE FROM rentalperiod WHERE id = $id", __FILE__ . " linje " . __LINE__);
                    echo json_encode("Booking slettet");
                    exit();
                }else{
                    echo json_encode("Booking kan ikke slettes, da den er faktureret");
                    exit();
                }
            }else{
                db_modify("DELETE FROM rentalperiod WHERE id = $id", __FILE__ . " linje " . __LINE__);
                db_modify("DELETE FROM ordrer WHERE id = $order_id", __FILE__ . " linje " . __LINE__);
                db_modify("DELETE FROM ordrelinjer WHERE ordre_id = $order_id", __FILE__ . " linje " . __LINE__);
                echo json_encode("Booking slettet");
                exit();
            }
        }else{
            db_modify("DELETE FROM rentalperiod WHERE id = $id", __FILE__ . " linje " . __LINE__);
            echo json_encode("Booking slettet");
        }
    }

    if(isset($_GET["updateBooking"])){
        $data = json_decode(file_get_contents('php://input'), true);
        if($data["from"] === "" || $data["to"] === "" || $data["booking_id"] === ""){
            echo json_encode(["msg" => "Der gik noget galt!"]);
            exit();
        }
        $id = db_escape_string($data["booking_id"]);
        $from = db_escape_string($data["from"]);
        $to = db_escape_string($data["to"]);
        $query = db_select("SELECT * FROM rentalperiod WHERE id = $id", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $data["item_id"] = $res["item_id"];
        $order_id = $res["order_id"];
        $res = db_select("SELECT status FROM ordrer WHERE id = $order_id", __FILE__ . " linje " . __LINE__);
        if($res["status"] >= 3){
            $query = db_modify("UPDATE rentalperiod SET rt_from = $from, rt_to = $to WHERE id = $id", __FILE__ . " linje " . __LINE__);
            echo json_encode(["msg" => "Booking opdateret", "status" => 2]);
        }else{
            db_modify("UPDATE rentalperiod SET rt_from = $from, rt_to = $to WHERE id = $id", __FILE__ . " linje " . __LINE__);
            echo json_encode(["msg" => "Booking opdateret", "booking_id" => $id, "status" => 1]);
        }
    }

    if(isset($_GET["getBooking"])){
        $id = db_escape_string($_GET["getBooking"]);
        $query = db_select("SELECT * FROM rentalperiod WHERE id = $id", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $booking["id"] = $res["id"];
        $booking["item_id"] = $res["item_id"];
        $booking["customer_id"] = $res["cust_id"];
        $booking["order_id"] = $res["order_id"];
        $query2 = db_select("SELECT firmanavn, kontonr FROM adresser WHERE id = $res[cust_id]", __FILE__ . " linje " . __LINE__);
        $res2 = db_fetch_array($query2);
        $booking["name"] = $res2["firmanavn"];
        $booking["account_number"] = $res2["kontonr"];
        $booking["from"] = $res["rt_from"];
        $booking["to"] = $res["rt_to"];
        $query3 = db_select("SELECT * FROM rentalitems WHERE id = $res[item_id]", __FILE__ . " linje " . __LINE__);
        $res3 = db_fetch_array($query3);
        $booking["item_name"] = $res3["item_name"];
        if($res["order_id"] == 0 || $res["order_id"] == null){
            $booking["status"] = 0;
        }else{
            $query = db_select("SELECT * FROM ordrer WHERE id = $res[order_id]", __FILE__ . " linje " . __LINE__);
            $res = db_fetch_array($query);
            if($res["status"] >= 3){
                $booking["status"] = 1;
            }else{
                $booking["status"] = 0;
            }
        }   
        echo json_encode($booking);
    }

    if(isset($_GET["getBookingByCustomer"])){
        $id = db_escape_string($_GET["getBookingByCustomer"]);
        $query = db_select("SELECT * FROM rentalperiod WHERE cust_id = $id", __FILE__ . " linje " . __LINE__);
        $i = 0;
        while($res = db_fetch_array($query)){
            $query2 = db_select("SELECT * FROM rentalitems WHERE id = $res[item_id]", __FILE__ . " linje " . __LINE__);
            $res2 = db_fetch_array($query2);
            $query3 = db_select("SELECT * FROM adresser WHERE id = $res[cust_id]", __FILE__ . " linje " . __LINE__);
            $res3 = db_fetch_array($query3);
            $booking[$i]["id"] = $res["id"];
            $booking[$i]["item_id"] = $res["item_id"];
            $booking[$i]["customer_id"] = $res["cust_id"];
            $booking[$i]["from"] = $res["rt_from"];
            $booking[$i]["to"] = $res["rt_to"];
            $booking[$i]["item_name"] = $res2["item_name"];
            $booking[$i]["name"] = $res3["firmanavn"];
            $booking[$i]["account_number"] = $res3["kontonr"];
            $booking[$i]["order_id"] = $res["order_id"];
            $i++;
        }
        echo json_encode($booking);
    }

    // req from notesEtc.php line 32 in lager/varekort_includes folder
    if(isset($_GET["subItemId"])){
        $id = db_escape_string($_GET["subItemId"]);
        $query = db_select("SELECT * FROM rentalitems WHERE product_id = $id", __FILE__ . " linje " . __LINE__);
        
        if(db_num_rows($query) > 0){
            $response["success"] = false; 
            $response["message"] = "Varen er allerede sat til udlejning";
            echo json_encode($response);
            exit();
        }

        $query = db_select("SELECT * FROM varer WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $item["id"] = $res["id"];
        $item["item_name"] = $res["beskrivelse"];
        $item["item_qty"] = $res["min_lager"];
        if($item["item_qty"] > 1){
            for($i = 0; $i < $item["item_qty"]; $i++){
                $name = $item["item_name"] . " " . $i+1;
                $query = db_modify("INSERT INTO rentalitems (item_name, product_id) VALUES ('$name', $item[id])", __FILE__ . " linje " . __LINE__);
                if(!$query){
                    $response["success"] = false; 
                    $response["message"] = "Der skete en fejl";
                    echo json_encode($response);
                    exit();
                }
            }$response["success"] = true;
             $response["message"] = "Varen er nu sat til udlejning";
            echo json_encode($response);
        }else{
            $query = db_modify("INSERT INTO rentalitems (item_name, product_id) VALUES ('$item[item_name]', $item[id])", __FILE__ . " linje " . __LINE__);
            if(!$query){
                $response["success"] = false;
                $response["message"] = "Der skete en fejl";
                echo json_encode($response);
            }else{
                $response["success"] = true;
                 $response["message"] = "Varen er nu sat til udlejning";
                echo json_encode($response);
            }
        }
    }

    if(isset($_GET["getItem"])){
        $id = db_escape_string($_GET["getItem"]);
        $query = db_select("SELECT * FROM rentalitems WHERE id = $id", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $items["id"] = $res["id"];
        $items["item_name"] = $res["item_name"];
        echo json_encode($items);
    }

    if(isset($_GET["updateItem"])){
        $data = json_decode(file_get_contents('php://input'), true);
        if($data["item_name"] === ""){
            echo json_encode("Udfyld alle felter");
            exit();
        }
        $id = db_escape_string($data["id"]);
        $item_name = db_escape_string($data["item_name"]);
        $query = db_modify("UPDATE rentalitems SET item_name = '$item_name' WHERE id = $id", __FILE__ . " linje " . __LINE__);
        if($query){
            echo json_encode("Varen er nu opdateret");
        }else{
            echo json_encode("Der skete en fejl");
        }
    }
    
    if(isset($_GET["deleteItem"])){
        $id = db_escape_string($_GET["deleteItem"]);
        $query = db_modify("DELETE FROM rentalitems WHERE id = $id", __FILE__ . " linje " . __LINE__);
        /* $query = db_select("SELECT * FROM rentalperiod WHERE item_id = $id", __FILE__ . " linje " . __LINE__);
        while($row = db_fetch_array($query)){
            $query2 = db_modify("DELETE FROM rentalperiod WHERE item_id = $id", __FILE__ . " linje " . __LINE__);
        } */
        if($query){
            echo json_encode("Varen er nu fjernet fra udlejning");
        }else{
            echo json_encode("Der skete en fejl");
        }
    }

    if(isset($_GET["products"])){
        $ids = array();
        $query = db_select("SELECT * FROM rentalitems", __FILE__ . " linje " . __LINE__);
        $i = 0;
        while($res = db_fetch_array($query)){
            if(!in_array($res["product_id"], $ids)){
                $ids[$i] = $res["product_id"];
                $query2 = db_select("SELECT * FROM varer WHERE id = $res[product_id]", __FILE__ . " linje " . __LINE__);
                $res2 = db_fetch_array($query2);
                $query3 = db_select("SELECT * FROM rentalperiod WHERE item_id = $res[id]", __FILE__ . " linje " . __LINE__);
                $res3 = db_fetch_array($query3);
                $products[$i]["product_id"] = $res2["id"];
                $products[$i]["product_name"] = $res2["beskrivelse"];
                $i++;
            }
        }
        echo json_encode($products);
    }

    if(isset($_GET["productCount"])){
        $id = db_escape_string($_GET["productCount"]);
        $query = db_select("SELECT * FROM rentalitems WHERE product_id = $id", __FILE__ . " linje " . __LINE__);
        $count = db_num_rows($query);
        echo json_encode($count);
    }

    if(isset($_GET["getBookingsByCust"])){
        $id = db_escape_string($_GET["getBookingsByCust"]);
        // make id to int 
        $id = intval($id);
        // check if id is not empty
        if($id != "" && $id != null){
            $query = db_select("
                SELECT rp.*, ri.item_name, a.firmanavn, a.kontonr 
                FROM rentalperiod rp 
                JOIN rentalitems ri ON rp.item_id = ri.id 
                JOIN adresser a ON rp.cust_id = a.id 
                WHERE rp.cust_id = $id
            ", __FILE__ . " linje " . __LINE__);

            $i = 0;
            while($res = db_fetch_array($query)){
                $bookings[$i]["id"] = $res["id"];
                $bookings[$i]["item_id"] = $res["item_id"];
                $bookings[$i]["customer_id"] = $res["cust_id"];
                $bookings[$i]["from"] = $res["rt_from"];
                $bookings[$i]["to"] = $res["rt_to"];
                $bookings[$i]["item_name"] = $res["item_name"];
                $bookings[$i]["name"] = $res["firmanavn"];
                $bookings[$i]["account_number"] = $res["kontonr"];
                $bookings[$i]["order_id"] = $res["order_id"];
                $i++;
            }
            echo json_encode($bookings);
        }else{
            echo null;
        }
    }

    /* if(isset($_GET["productInfo"])){
        $query = db_select("SELECT * FROM rentalitems ORDER BY length(item_name), item_name ASC", __FILE__ . " linje " . __LINE__);
        $i = 0;
        if(db_num_rows($query) === 0){
            echo json_encode(["msg" => "Der er ingen bookinger", "success" => false]);
            exit();
        }
        // To-do: get all bookings that ended within the last month
        #$date = strtotime("-1 month");
        while($res = db_fetch_array($query)){
            // in case of lag in the system, only get the bookings that ended within the last month
            // $query2 = db_select("SELECT * FROM rentalperiod WHERE item_id = $res[id] AND rt_to > $date", __FILE__ . " linje " . __LINE__);
            $query2 = db_select("SELECT * FROM rentalperiod WHERE item_id = $res[id] ", __FILE__ . " linje " . __LINE__);
            while($res2 = db_fetch_array($query2)){
                $query3 = db_select("SELECT beskrivelse FROM varer WHERE id = $res[product_id]", __FILE__ . " linje " . __LINE__);
                $productInfo[$i]["product_name"] = db_fetch_array($query3)["beskrivelse"];
                $productInfo[$i]["reservation_id"] = $res2["id"];
                $productInfo[$i]["product_id"] = $res["product_id"];
                $productInfo[$i]["item_name"] = $res["item_name"];
                $productInfo[$i]["item_id"] = $res["id"];
                $productInfo[$i]["rental_id"] = $res2["id"];
                $productInfo[$i]["from"] = $res2["rt_from"];
                $productInfo[$i]["to"] = $res2["rt_to"];
                $productInfo[$i]["cust_id"] = $res2["cust_id"];
                $res3 = db_fetch_array(db_select("SELECT firmanavn, kontonr FROM adresser WHERE id = $res2[cust_id]", __FILE__ . " linje " . __LINE__));
                $productInfo[$i]["cust_name"] = str_replace("'", "´", $res3["firmanavn"]);
                $productInfo[$i]["kontonr"] = $res3["kontonr"];
                $i++;
            }
            if(db_num_rows($query2) === 0){
                $query3 = db_select("SELECT beskrivelse FROM varer WHERE id = $res[product_id]", __FILE__ . " linje " . __LINE__);
                $productInfo[$i]["product_name"] = db_fetch_array($query3)["beskrivelse"];
                $productInfo[$i]["product_id"] = $res["product_id"];
                $productInfo[$i]["item_id"] = $res["id"];
                $productInfo[$i]["item_name"] = $res["item_name"];
                $i++;
            }
        }
        echo json_encode($productInfo);
    } */

    if(isset($_GET["productInfo"])){
        $month = $_GET["month"]; // The month you want to filter by
        $year = $_GET["year"]; // The year you want to filter by

        $query = db_select("SELECT 
            rp.id as rental_id, 
            rp.rt_from, 
            rp.rt_to, 
            rp.cust_id, 
            ri.id as item_id, 
            ri.item_name, 
            ri.product_id, 
            v.beskrivelse as product_name, 
            a.firmanavn, 
            a.kontonr 
        FROM 
            rentalitems ri
        LEFT JOIN 
            varer v ON ri.product_id = v.id
        LEFT JOIN 
            rentalperiod rp ON rp.item_id = ri.id AND 
            ((EXTRACT(YEAR FROM to_timestamp(rp.rt_from)) < $year) OR 
            (EXTRACT(YEAR FROM to_timestamp(rp.rt_from)) = $year AND EXTRACT(MONTH FROM to_timestamp(rp.rt_from)) <= $month)) AND 
            ((EXTRACT(YEAR FROM to_timestamp(rp.rt_to)) > $year) OR 
            (EXTRACT(YEAR FROM to_timestamp(rp.rt_to)) = $year AND EXTRACT(MONTH FROM to_timestamp(rp.rt_to)) >= $month))
        LEFT JOIN 
            adresser a ON rp.cust_id = a.id 
        ORDER BY 
            length(ri.item_name), 
            ri.item_name ASC", __FILE__ . " linje " . __LINE__);
        $i = 0;
        if(db_num_rows($query) === 0){
            echo json_encode(["msg" => "Der er ingen bookinger", "success" => false]);
            exit();
        }
        while($res = db_fetch_array($query)){
            $productInfo[$i]["product_name"] = $res["product_name"];
            $productInfo[$i]["reservation_id"] = $res["rental_id"];
            $productInfo[$i]["product_id"] = $res["product_id"];
            $productInfo[$i]["item_name"] = $res["item_name"];
            $productInfo[$i]["item_id"] = $res["item_id"];
            $productInfo[$i]["rental_id"] = $res["rental_id"];
            $productInfo[$i]["from"] = $res["rt_from"];
            $productInfo[$i]["to"] = $res["rt_to"];
            $productInfo[$i]["cust_id"] = $res["cust_id"];
            $productInfo[$i]["cust_name"] = str_replace("'", "´", $res["firmanavn"]);
            $productInfo[$i]["kontonr"] = $res["kontonr"];
            $i++;
        }
        echo json_encode($productInfo);
    }

    /* if(isset($_GET["productInfos"])){
        $id = db_escape_string($_GET["productInfos"]);
        $query = db_select("SELECT * FROM rentalitems WHERE product_id = $id", __FILE__ . " linje " . __LINE__);
        $i = 0;
        while($res = db_fetch_array($query)){
            $query2 = db_select("SELECT * FROM rentalperiod WHERE item_id = $res[id]", __FILE__ . " linje " . __LINE__);
            while($res2 = db_fetch_array($query2)){
                $productInfo[$i]["product_id"] = $res["product_id"];
                $productInfo[$i]["item_name"] = $res["item_name"];
                $productInfo[$i]["item_id"] = $res["id"];
                $productInfo[$i]["rental_id"] = $res2["id"];
                $productInfo[$i]["from"] = $res2["rt_from"];
                $productInfo[$i]["to"] = $res2["rt_to"];
                $productInfo[$i]["cust_id"] = $res2["cust_id"];
                $res3 = db_fetch_array(db_select("SELECT * FROM adresser WHERE id = $res2[cust_id]", __FILE__ . " linje " . __LINE__));
                $productInfo[$i]["cust_name"] = $res3["firmanavn"];
                $i++;
            }
            if(db_num_rows($query2) === 0){
                $productInfo[$i]["product_id"] = $res["product_id"];
                $productInfo[$i]["item_id"] = $res["id"];
                $productInfo[$i]["item_name"] = $res["item_name"];
                $i++;
            }
        }
        echo json_encode($productInfo);
    } */
    
    if(isset($_GET["createItem"])){
        $data = json_decode(file_get_contents('php://input'), true);
        $query = db_modify("INSERT INTO rentalitems (item_name, product_id) VALUES ('$data[item_name]', $data[product_id])", __FILE__ . " linje " . __LINE__);
        echo json_encode("Standen er nu oprettet");
    }

    if(isset($_GET["insertClosedDay"])){
        $data = json_decode(file_get_contents('php://input'), true);
        $query = db_select("SELECT * FROM rentalclosed WHERE day = $data[day]", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) > 0){
            echo json_encode("Dagen er allerede lukket");
            exit();
        }
        $query = db_modify("INSERT INTO rentalclosed (day) VALUES ($data[day])", __FILE__ . " linje " . __LINE__);
        echo json_encode("Dagen er nu lukket");
    }

    if(isset($_GET["getClosedDays"])){
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

    if(isset($_GET["deleteClosedDay"])){
        $id = db_escape_string($_GET["deleteClosedDay"]);
        $query = db_modify("DELETE FROM rentalclosed WHERE id = $id", __FILE__ . " linje " . __LINE__);
        echo json_encode("Dagen er nu åben");
    }

    if(isset($_GET["bookingOrder"])){
        $id = db_escape_string($_GET["bookingOrder"]);
        $query = db_select("SELECT * FROM rentalperiod WHERE id = $id", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $query2 = db_select("SELECT * FROM ordrer WHERE id = $res[order_id]", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query2) < 1){
            echo json_encode(["success" => false, "msg" => "Ordren findes ikke"]);
            exit();
        }
        $status = db_fetch_array($query2)["status"];
        echo json_encode([$status]);
    }

    if(isset($_GET["createOrder"])){
        $data = json_decode(file_get_contents('php://input'), true);
        CreateOrder($data);
        echo json_encode("Ordren er nu oprettet");
    }

    if(isset($_GET["updateOrder"])){
        $data = json_decode(file_get_contents('php://input'), true);
        $query = db_select("SELECT order_id, item_id FROM rentalperiod WHERE id = $data[booking_id]", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $data["item_id"] = $res["item_id"];
        $order_id = $res["order_id"];
        $query = db_select("SELECT status FROM ordrer WHERE id = $order_id", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $status = $res["status"];
        if($status >= 3){
            echo json_encode(["status" => false]);
            exit();
        }else{
            db_modify("DELETE FROM ordrelinjer WHERE ordre_id = $order_id", __FILE__ . " linje " . __LINE__);
            db_modify("DELETE FROM ordrer WHERE id = $order_id", __FILE__ . " linje " . __LINE__);
            CreateOrder($data);
            echo json_encode(["status" => true]);
        }
    }

    if(isset($_GET["getItemBookings"])){
        $id = db_escape_string($_GET["getItemBookings"]);
        $query = db_select("SELECT * FROM rentalperiod WHERE item_id = $id", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) <= 0){
            echo json_encode(["success" => false, "msg" => "Der er ingen bookinger"]);
            exit();
        }
        $i = 0;
        while($res = db_fetch_array($query)){
            $query2 = db_select("SELECT * FROM adresser WHERE id = $res[cust_id]", __FILE__ . " linje " . __LINE__);
            $res2 = db_fetch_array($query2);
            $bookings[$i]["id"] = $res["id"];
            $bookings[$i]["customer_id"] = $res["cust_id"];
            $bookings[$i]["name"] = $res2["firmanavn"];
            $bookings[$i]["account_number"] = $res2["kontonr"];
            $bookings[$i]["from"] = $res["rt_from"];
            $bookings[$i]["to"] = $res["rt_to"];
            $i++;
        }
        echo json_encode($bookings);
    }

    if(isset($_GET["getAllProductNames"])){
        // get all unique product_id's from rentalitems
        $query = db_select("SELECT DISTINCT product_id FROM rentalitems", __FILE__ . " linje " . __LINE__);
        $i = 0;
        while($res = db_fetch_array($query)){
            $query2 = db_select("SELECT beskrivelse FROM varer WHERE id = $res[product_id]", __FILE__ . " linje " . __LINE__);
            $productNames[$i]["product_id"] = $res["product_id"];
            $productNames[$i]["product_name"] = db_fetch_array($query2)["beskrivelse"];
            $i++;
        }
        echo json_encode($productNames);
    }
    
    if(isset($_GET["getSettings"])){
        $query = db_select("SELECT * FROM rentalsettings", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) <= 0){
            echo json_encode(["msg" => "Der er ingen indstillinger", "success" => false]);
            exit();
        }
        $res = db_fetch_array($query);
        echo json_encode($res);
    }

    if(isset($_GET["updateSettings"])){
        $data = json_decode(file_get_contents('php://input'), true);
        $query = db_select("SELECT * FROM rentalsettings", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) <= 0)
            $query = db_modify("INSERT INTO rentalsettings (booking_format, search_cust_name, search_cust_number, search_cust_tlf, start_day, deletion, find_weeks, end_day, put_together, pass, use_password, invoice_date, toggle_order) VALUES ('$data[booking_format]', '$data[search_cust_name]', '$data[search_cust_number]', '$data[search_cust_tlf]', '$data[start_day]', '$data[deletion]', '$data[find_weeks]', '$data[end_day]', '$data[put_together]', '$data[password]', '$data[use_password]', '$data[invoice_date]', '$data[toggle_order]')", __FILE__ . " linje " . __LINE__);
        else
            $query = db_modify("UPDATE rentalsettings SET booking_format = $data[booking_format], search_cust_name = $data[search_cust_name], search_cust_number = $data[search_cust_number], search_cust_tlf = $data[search_cust_tlf], start_day = $data[start_day], deletion = $data[deletion], find_weeks = $data[find_weeks], end_day = $data[end_day], put_together = $data[put_together], use_password = $data[use_password], pass = '$data[password]', invoice_date = $data[invoice_date], toggle_order = $data[toggle_order]", __FILE__ . " linje " . __LINE__);
            echo json_encode("Indstillingerne er nu opdateret");
    }

    if(isset($_GET["deleteProduct"])){
        $id = db_escape_string($_GET["deleteProduct"]);
        $query = db_select("SELECT id FROM rentalitems WHERE product_id = $id", __FILE__ . " linje " . __LINE__);
        while($res = db_fetch_array($query)){
            $query2 = db_modify("DELETE FROM rentalperiod WHERE item_id = $res[id]", __FILE__ . " linje " . __LINE__);
        }
        $query = db_modify("DELETE FROM rentalitems WHERE product_id = $id", __FILE__ . " linje " . __LINE__);
        echo json_encode("Varen er nu fjernet fra udlejning");
    }

/*     if(isset($_GET["getBookings"])){
        $id = db_escape_string($_GET["getBookings"]);
        $query = db_select("SELECT * FROM rentalperiod WHERE item_id = $id", __FILE__ . " linje " . __LINE__);
        $i = 0;
        while($res = db_fetch_array($query)){
            $query2 = db_select("SELECT * FROM adresser WHERE id = $res[cust_id]", __FILE__ . " linje " . __LINE__);
            $res2 = db_fetch_array($query2);
            $bookings[$i]["id"] = $res["id"];
            $bookings[$i]["customer_id"] = $res["cust_id"];
            $bookings[$i]["customer_name"] = $res2["firmanavn"];
            $bookings[$i]["from"] = $res["rt_from"];
            $bookings[$i]["to"] = $res["rt_to"];
            $i++;
        }
    } */

    if(isset($_GET["createReservation"])){
        $data = json_decode(file_get_contents('php://input'), true);
        $query = db_modify("INSERT INTO rentalreserved (item_id, rr_from, rr_to, comment) VALUES ($data[item_id], $data[from], $data[to], '$data[text]')", __FILE__ . " linje " . __LINE__);
        if($query){
            echo json_encode("Spærringen er nu oprettet");
        }else{
            echo json_encode("Der skete en fejl");
        }
    }

    if(isset($_GET["getReservations"])){
        $query = db_select("SELECT * FROM rentalreserved", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) <= 0){
            echo json_encode(["msg" => "Der er ingen spærring", "success" => false]);
            exit();
        }
        $i = 0;
        while($res = db_fetch_array($query)){
            $reservations[$i]["id"] = $res["id"];
            $reservations[$i]["item_id"] = $res["item_id"];
            $reservations[$i]["from"] = $res["rr_from"];
            $reservations[$i]["to"] = $res["rr_to"];
            $reservations[$i]["text"] = $res["comment"];
            $i++;
        }
        echo json_encode($reservations);
    }

    if(isset($_GET["getReservationsByItem"])){
        $id = db_escape_string($_GET["getReservationsByItem"]);
        $query = db_select("SELECT * FROM rentalreserved WHERE item_id = $id", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) <= 0){
            echo json_encode(["msg" => "Der er ingen spærring", "success" => false]);
            exit();
        }
        $i = 0;
        while($res = db_fetch_array($query)){
            $reservations[$i]["id"] = $res["id"];
            $reservations[$i]["item_id"] = $res["item_id"];
            $reservations[$i]["from"] = $res["rr_from"];
            $reservations[$i]["to"] = $res["rr_to"];
            $reservations[$i]["text"] = $res["comment"];
            $i++;
        }
        echo json_encode($reservations);
    }

    if(isset($_GET["editReservationComment"])){
        $data = json_decode(file_get_contents('php://input'), true);
        file_put_contents("../temp/booking.txt", "data: " . print_r($data, true) . "\n", FILE_APPEND);
        $query = db_modify("UPDATE rentalreserved SET comment = '$data[text]' WHERE id = $data[id]", __FILE__ . " linje " . __LINE__);
        if($query){
            echo json_encode("spærringen er nu opdateret");
        }else{
            echo json_encode("Der skete en fejl");
        }
    }

    if(isset($_GET["editReservationDates"])){
        $data = json_decode(file_get_contents('php://input'), true);
        $query = db_modify("UPDATE rentalreserved SET rr_from = $data[from], rr_to = $data[to] WHERE id = $data[id]", __FILE__ . " linje " . __LINE__);
        if($query){
            echo json_encode("spærringen er nu opdateret");
        }else{
            echo json_encode("Der skete en fejl");
        }
    }

    if(isset($_GET["deleteReservation"])){
        $id = db_escape_string($_GET["deleteReservation"]);
        $query = db_modify("DELETE FROM rentalreserved WHERE id = $id", __FILE__ . " linje " . __LINE__);
        if($query){
            echo json_encode("spærringen er nu slettet");
        }else{
            echo json_encode("Der skete en fejl");
        }
    }

    if(isset($_GET["deleteReservationByItem"])){
        $id = db_escape_string($_GET["deleteReservationByItem"]);
        $query = db_modify("DELETE FROM rentalreserved WHERE item_id = $id", __FILE__ . " linje " . __LINE__);
        if($query){
            echo json_encode("spærringen er nu slettet");
        }else{
            echo json_encode("Der skete en fejl");
        }
    }
    
    if(isset($_GET["getBookingsForItems"])){
        // get current month
        $month = date("m");
        // get current year
        $year = date("Y");
/*         $data = array_map('intval', $data); // Ensure all IDs are integers to prevent SQL injection

        $dataList = implode(',', $data); // Convert the array to a comma-separated string */

        // Check if $dataList is empty
       /*  if (empty($dataList)) {
            echo json_encode(["msg" => "No items available", "success" => false]);
            exit();
        } */

        $query = db_select("SELECT 
                rp.id as rental_id, 
                rp.rt_from, 
                rp.rt_to, 
                rp.cust_id, 
                ri.id as item_id, 
                ri.item_name, 
                ri.product_id, 
                COALESCE(v.beskrivelse, '') as product_name, 
                COALESCE(a.firmanavn, '') as cust_name, 
                COALESCE(a.kontonr, '') as kontonr 
            FROM 
                rentalitems ri
            LEFT JOIN 
                varer v ON ri.product_id = v.id
            LEFT JOIN 
                rentalperiod rp ON rp.item_id = ri.id AND  
                (
                    (EXTRACT(YEAR FROM to_timestamp(rp.rt_to)) > $year) OR 
                    (EXTRACT(YEAR FROM to_timestamp(rp.rt_to)) = $year AND EXTRACT(MONTH FROM to_timestamp(rp.rt_to)) >= $month)
                )
            LEFT JOIN 
                adresser a ON rp.cust_id = a.id 
            ORDER BY 
                LENGTH(ri.item_name), 
                ri.item_name ASC", __FILE__ . " linje " . __LINE__);

        // Check if the query execution was successful
        if ($query === false) {
            echo json_encode(["msg" => "Query failed", "success" => false]);
            exit();
        }
    
        $i = 0;
        if(db_num_rows($query) === 0){
            echo json_encode(["msg" => "Der er ingen bookinger", "success" => false]);
            exit();
        }
        while($res = db_fetch_array($query)){
            $productInfo[$i]["product_name"] = $res["product_name"];
            $productInfo[$i]["reservation_id"] = $res["rental_id"];
            $productInfo[$i]["product_id"] = $res["product_id"];
            $productInfo[$i]["item_name"] = $res["item_name"];
            $productInfo[$i]["item_id"] = $res["item_id"];
            $productInfo[$i]["rental_id"] = $res["rental_id"];
            $productInfo[$i]["from"] = $res["rt_from"];
            $productInfo[$i]["to"] = $res["rt_to"];
            $productInfo[$i]["cust_id"] = $res["cust_id"];
            $productInfo[$i]["cust_name"] = str_replace("'", "´", $res["firmanavn"]);
            $productInfo[$i]["kontonr"] = $res["kontonr"];
            $i++;
        }
        echo json_encode($productInfo);
    }

    if(isset($_GET["getBookingsForItemsByType"])){
        // get current month
        $month = date("m");
        // get current year
        $year = date("Y");
        $type = db_escape_string($_GET["getBookingsForItemsByType"]);
        $query = db_select("SELECT 
                rp.id as rental_id, 
                rp.rt_from, 
                rp.rt_to, 
                rp.cust_id, 
                ri.id as item_id, 
                ri.item_name, 
                ri.product_id, 
                COALESCE(v.beskrivelse, '') as product_name, 
                COALESCE(a.firmanavn, '') as cust_name, 
                COALESCE(a.kontonr, '') as kontonr 
            FROM 
                rentalitems ri
            LEFT JOIN 
                varer v ON ri.product_id = v.id
            LEFT JOIN 
                rentalperiod rp ON rp.item_id = ri.id AND  
                (
                    (EXTRACT(YEAR FROM to_timestamp(rp.rt_to)) > $year) OR 
                    (EXTRACT(YEAR FROM to_timestamp(rp.rt_to)) = $year AND EXTRACT(MONTH FROM to_timestamp(rp.rt_to)) >= $month)
                )
            LEFT JOIN 
                adresser a ON rp.cust_id = a.id
            WHERE
                ri.product_id = '$type'
            ORDER BY 
                LENGTH(ri.item_name), 
                ri.item_name ASC", __FILE__ . " linje " . __LINE__);

        // Check if the query execution was successful
        if ($query === false) {
            echo json_encode(["msg" => "Query failed", "success" => false]);
            exit();
        }

        
        if(db_num_rows($query) === 0){
            echo json_encode(["msg" => "Der er ingen bookinger", "success" => false]);
            exit();
        }

        $i = 0;
        while($res = db_fetch_array($query)){
            $productInfo[$i]["product_name"] = $res["product_name"];
            $productInfo[$i]["reservation_id"] = $res["rental_id"];
            $productInfo[$i]["product_id"] = $res["product_id"];
            $productInfo[$i]["item_name"] = $res["item_name"];
            $productInfo[$i]["item_id"] = $res["item_id"];
            $productInfo[$i]["rental_id"] = $res["rental_id"];
            $productInfo[$i]["from"] = $res["rt_from"];
            $productInfo[$i]["to"] = $res["rt_to"];
            $productInfo[$i]["cust_id"] = $res["cust_id"];
            $productInfo[$i]["cust_name"] = str_replace("'", "´", $res["firmanavn"]);
            $productInfo[$i]["kontonr"] = $res["kontonr"];
            $i++;
        }
        echo json_encode($productInfo);
    }

    if(isset($_GET["getBookingsFromPeriod"])){
        $from_date = $_GET["from_date"]; // Start date in format YYYY-MM-DD
        $to_date = $_GET["to_date"];     // End date in format YYYY-MM-DD
        $query = db_select("SELECT 
            rp.id as rental_id,
            rp.rt_from,
            rp.rt_to,
            rp.cust_id,
            ri.id as item_id,
            ri.item_name,
            ri.product_id,
            v.beskrivelse as product_name,
            a.firmanavn,
            tlf,
            a.kontonr 
        FROM 
            rentalperiod rp
        LEFT JOIN 
            rentalitems ri ON rp.item_id = ri.id
        LEFT JOIN 
            varer v ON ri.product_id = v.id
        LEFT JOIN 
            adresser a ON rp.cust_id = a.id 
        WHERE
            -- Date range filter
            (to_timestamp(rp.rt_from) <= to_date('$to_date', 'YYYY-MM-DD')) AND 
            (to_timestamp(rp.rt_to) >= to_date('$from_date', 'YYYY-MM-DD'))
        ORDER BY 
            length(ri.item_name), 
            ri.item_name ASC", __FILE__ . " linje " . __LINE__);

        $i = 0;
        if(db_num_rows($query) === 0){
            echo json_encode(["msg" => "Der er ingen bookinger i dette tidsrum", "success" => false]);
            exit();
        }

        while($res = db_fetch_array($query)){
            $productInfo[$i]["product_name"] = $res["product_name"];
            $productInfo[$i]["reservation_id"] = $res["rental_id"];
            $productInfo[$i]["product_id"] = $res["product_id"];
            $productInfo[$i]["item_name"] = $res["item_name"];
            $productInfo[$i]["item_id"] = $res["item_id"];
            $productInfo[$i]["rental_id"] = $res["rental_id"];
            $productInfo[$i]["from"] = $res["rt_from"];
            $productInfo[$i]["to"] = $res["rt_to"];
            $productInfo[$i]["cust_id"] = $res["cust_id"];
            $productInfo[$i]["cust_name"] = str_replace("'", "´", $res["firmanavn"]);
            $productInfo[$i]["kontonr"] = $res["kontonr"];
            $productInfo[$i]["tlf"] = $res["tlf"];
            $i++;
        }
        echo json_encode($productInfo);
    }
/*     if(isset($_GET["getXCSRFToken"])){
        $data = json_decode(file_get_contents('php://input'), true);
        $query = db_select("SELECT * FROM settings", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        if($data["password"] === $res["password"]){
            echo json_encode(["msg" => "success", "success" => true]);
        }else{
            echo json_encode(["msg" => "Forkert kodeord", "success" => false]);
        }
    } */

    /* function generateCSRFToken($id){
        global $pdo;
        $token = bin2hex(random_bytes(32));
        $query = $pdo->prepare("UPDATE admin SET token = ? WHERE id = $id");
        $query->execute([$token]);
        return $token;
    } */

    if (isset($_GET["getAllProducts"])) {
        $query = db_select("SELECT * FROM rentalremote", __FILE__ . " linje " . __LINE__);
        $res = [];
        while ($row = db_fetch_array($query)) {
            // Filter out numeric keys
            $filtered = array();
            foreach($row as $key => $value) {
                if (!is_numeric($key)) {
                    $filtered[$key] = $value;
                }
            }
            $row = $filtered;
            
            $productId = $row['product_id'];
        
            // Fetch product details
            $productQuery = db_select("SELECT beskrivelse, varenr, enhed FROM varer WHERE id = $productId", __FILE__ . " linje " . __LINE__);
            $res2 = db_fetch_array($productQuery);
            $row["product_name"] = $res2["beskrivelse"];
            $row["product_number"] = $res2["varenr"];
            $row["unit"] = $res2["enhed"];
        
            // Fetch rental periods
            $periodsQuery = db_select("SELECT id, amount FROM rentalremoteperiods WHERE rentalremote_id = $row[id]", __FILE__ . " linje " . __LINE__);
            $row["periods"] = [];
            if ($periodsQuery) {
                while ($res3 = db_fetch_array($periodsQuery)) {
                    // Create new array without numeric keys
                    $filtered = array();
                    foreach($res3 as $key => $value) {
                        if (!is_numeric($key)) {
                            $filtered[$key] = $value;
                        }
                    }
                    $row["periods"][] = $filtered;
                }
            }
            $res[] = $row;
        }
        echo json_encode($res);
    }

    if(isset($_GET["updateMail"])){
        $data = json_decode(file_get_contents('php://input'), true);
        $query = db_select("SELECT * FROM rentalmail", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) > 0){
            $query = db_modify("UPDATE rentalmail SET username = '$data[username]', password = '$data[password]', host = '$data[host]' WHERE id = 1", __FILE__ . " linje " . __LINE__);
        }else{
            $query = db_modify("INSERT INTO rentalmail (username, password, host) VALUES ('$data[username]', '$data[password]', '$data[host]')", __FILE__ . " linje " . __LINE__);
        }
        if($query){
            echo json_encode("Mail indstillingerne er nu opdateret");
        }else{
            echo json_encode("Der skete en fejl");
        }
    }

    if(isset($_GET["getMailInfo"])){
        $query = db_select("SELECT * FROM rentalmail", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        echo json_encode($res);
    }

    if(isset($_GET["getPayment"])){
        $query = db_select("SELECT * FROM rentalpayment", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        echo json_encode($res);
    }

    if(isset($_GET["updatePayment"])){
        $data = json_decode(file_get_contents('php://input'), true);
        $query = db_select("SELECT * FROM rentalpayment", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) > 0){
            $query = db_modify("UPDATE rentalpayment SET apikey = '$data[apiKey]', trade_conditions = '$data[tradeConditions]' WHERE id = 1", __FILE__ . " linje " . __LINE__);
        }else{
            $query = db_modify("INSERT INTO rentalpayment (apikey, trade_conditions) VALUES ('$data[apiKey]', '$data[tradeConditions]')", __FILE__ . " linje " . __LINE__);
        }
        if($query){
            echo json_encode("Betalings indstillingerne er nu opdateret");
        }else{
            echo json_encode("Der skete en fejl");
        }
    }

    if (isset($_GET["updateRemoteProduct"])) {
        $data = json_decode(file_get_contents('php://input'), true);
    
        $productId = $data['id'];
        $productDesc = $data['product_desc'];
        $active = intval($data['is_active']);
        $choose = intval($data['choose_periods']);
        $max = intval($data['max']);
    
        db_modify("UPDATE rentalremote SET descript = '$productDesc', is_active = $active, choose_periods = $choose, max = $max WHERE id = $productId", __FILE__ . " linje " . __LINE__);
        
        $query = db_select("SELECT * FROM rentalremoteperiods WHERE rentalremote_id = $productId", __FILE__ . " linje " . __LINE__);
        while ($res = db_fetch_array($query)) {
            db_modify("DELETE FROM rentalremoteperiods WHERE id = $res[id]", __FILE__ . " linje " . __LINE__);
        }
    
        foreach ($data["periods"] as $period) {
            $amount = $period['amount'];
            db_modify("INSERT INTO rentalremoteperiods (rentalremote_id, amount) VALUES ($productId, $amount)", __FILE__ . " linje " . __LINE__);
        }
    
        echo json_encode("Produktet er nu opdateret");
    }

    if(isset($_GET["getRemoteLink"])){
        $serverName = $_SERVER['SERVER_NAME'];
        $firstFolder = explode('/', trim($_SERVER['REQUEST_URI'], '/'))[0];
        echo json_encode("https://".$serverName."/".$firstFolder."/remoteBooking/index.php?id=".$db);
    }

    function opret_ordrelinje($id,$vare_id,$varenr,$antal,$beskrivelse,$pris,$rabat_ny,$procent,$art,$momsfri,$posnr,$linje_id,$incl_moms,$kdo,$rabatart,$kopi,$saet,$fast_db,$lev_varenr,$lager,$linje) { #20140426

        if (!$id) return("missing ordre ID");
        global $afd,$barcodeNew;
        global $db,$db_skriv_id;
        global $folger,$formularsprog; #20200109
        global $kundedisplay;	
        global $momssats;
        global $procentfakt;
        global $regnaar;
        global $sprog_id,$status;
        global $tilfravalgNy;
        global $vis_saet;
        global $webservice;
        global $voucherNumber;
    
            if (file_exists("../temp/$db/pos$id.txt")) unlink ("../temp/$db/pos$id.txt");
    
        if (isset($_POST['timestamp']) && $_POST['timestamp']) { #20240924
            $timestamp = $_POST['timestamp']."|".$varenr;	
            $fn = "../temp/$db/timestamp".$bruger_id.".txt";
            $preTimestamp = file_get_contents($fn);
            if ($timestamp == $preTimestamp) {
                return;
                exit;
            } else {
                file_put_contents($fn,$timestamp);
            }
        }
        
        if (isset($_SESSION['varenr_ny']) && $_SESSION['varenr_ny']) {
            if ($varenr == $barcodeNew) $varenr = $_SESSION['varenr_ny'];
            unset($_SESSION["varenr_ny"]);
        }
    
        if ($tilfravalgNy && !strpos($tilfravalgNy,chr(9))) {
            if (is_numeric($tilfravalgNy)) { # don't use is_int - returns false ???
                $qtxt = "select id from varer where id = '$tilfravalgNy'";
                if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $tilfravalgNy = '';
            } else $tilfravalgNy = '';
        }	
        if (!is_numeric($saet)) $saet=0;
        if ($procent=='') $procent=100;
        if (!is_numeric($fast_db)) $fast_db = 0;
        if (!is_numeric($rabat_ny)) $rabat_ny = 0;
        $b2b=$debitorgruppe=$debitorrabatgruppe=$omkunde=$valutakurs=0;
        if (!$afd) $afd=0;
        $dd=date("Y-m-d");
        $tt=date("H:i:s");
    #	if (!is_numeric($pris)) $pris=0; #20130903 - fjernet 20140124
    
        if ($pris && $pris > 99999999) {
            return("Ulovlig v&aelig;rdi i prisfelt");
        }
    #fwrite ($log, __line__." Regnaar $regnaar\n");
        if (!$regnaar) {
            $year=date("Y");
            $month=date("m");
            $del1="(box1<='$month' and box2<='$year' and box3>='$month' and box4>='$year')";
            $del2="(box1<='$month' and box2<='$year' and box3<'$month' and box4>'$year')";
            $del3="(box1>'$month' and box2<'$year' and box3>='$month' and box4>='$year')";
            $qtxt="select kodenr from grupper where art='RA' and ($del1 or $del2 or $del3)"; #20190318
        #fwrite ($log, __line__." $qtxt\n");
            if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
                $regnaar=$r['kodenr']*1;
            #fwrite ($log, __line__." Regnaar $regnaar\n");
            } elseif ($r=db_fetch_array(db_select("select max(kodenr) as kodenr from grupper where art='RA'",__FILE__ . " linje " . __LINE__))) {
                $regnaar=$r['kodenr']*1;
            } else $regnaar=1;
        }
        $qtxt = "select ordrer.art as art,ordrer.status as status,ordrer.valutakurs as valutakurs,ordrer.afd as afd, ";
        $qtxt.= "adresser.gruppe as debitorgruppe,adresser.rabatgruppe as debitorrabatgruppe from ";
        $qtxt.= "adresser,ordrer where ordrer.id='$id'and adresser.id=ordrer.konto_id";
        if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
            $debitorgruppe=$r['debitorgruppe'];
            $debitorrabatgruppe=$r['debitorrabatgruppe'];
            $valutakurs=$r['valutakurs'];
            $status=$r['status'];
            if (!$afd && $r['afd']) $afd=$r['afd'];
        }
        if (!$lager) {
            if ($afd) {
                $r=db_fetch_array(db_select("select box1 from grupper where kodenr='$afd' and art = 'AFD'",__FILE__ . " linje " . __LINE__));
                $lager=$r['box1'];
                if (!$lager) {
                    $r=db_fetch_array(db_select("select kodenr from grupper where box1='$afd' and art = 'LG'",__FILE__ . " linje " . __LINE__));
                    $lager=$r['kodenr']*1;
                } 
            } else $lager=0;
        }
    
        if (!$art) $art=$r['art']; #20140424b
        if ($status>=3) { #20131015
            return("Der kan ikke tilføjes linjer i en bogført ordre");
            exit;
        }
        $qtxt = "select box8,box9 from grupper where kodenr='$debitorgruppe' and art = 'DG' and fiscal_year = '$regnaar'";
        if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
            $b2b=$r['box8'];
            $omkunde=$r['box9'];
        }
        $varenr=db_escape_string($varenr);
        $varenr_low=strtolower($varenr);
        $varenr_up=strtoupper($varenr);
    
        $variant_varer=array(); //20181223
        $x=0;
        $qtxt="SELECT distinct(vare_id) FROM variant_varer";
        $q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
        while ($r=db_fetch_array($q)) {
            $variant_varer[$x]=$r['vare_id'];
            $x++;
        }
        
        $qtxt="SELECT id,vare_id,variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr_up'";
        if (strlen($varenr)==12 && is_numeric($varenr)) $qtxt.=" or variant_stregkode='0$varenr'";
        if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
            $vare_id=$r['vare_id'];
            $variant_type=$r['variant_type']*1;
            $variant_id=$r['id'];
            $qtxt="SELECT beskrivelse FROM variant_typer WHERE id = '$variant_type'";
            $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
            $variantText=$r['beskrivelse'];
        } else {
            $variant_id=0;
            $variant_type=$variantText=NULL;
        }
        
        $string=NULL;
        if (isset($vare_id) && $vare_id) $string="select * from varer where id='$vare_id'";
        elseif ($varenr) {
            $string = "select * from varer where lower(varenr) = '$varenr_low' or upper(varenr) = '$varenr_up' ";
            $string.= "or varenr LIKE '$varenr' or lower(stregkode) = '$varenr_low' or upper(stregkode) = '$varenr_up' ";
            $string.= "or stregkode LIKE '$varenr'";
            if (strlen($varenr)==12 && is_numeric($varenr)) $string.=" or stregkode='0$varenr'";
        } elseif ($id && $beskrivelse && $posnr) {
            $qtxt="insert into ordrelinjer ";
            $qtxt.="(ordre_id,vare_id,varenr,enhed,beskrivelse,antal,rabat,rabatart,procent,m_rabat,pris,kostpris,momsfri,momssats,";
            $qtxt.="posnr,projekt,folgevare,rabatgruppe,bogf_konto,kred_linje_id,kdo,serienr,variant_id,leveres,samlevare,omvbet,";
            $qtxt.="saet,fast_db,tilfravalg,lager) values ";
            $qtxt.="('$id','0','','','$beskrivelse','0','0','','100','0','0','0','','0','$posnr','0','0','0','0','0','','','0','0',";
            $qtxt.="'','$omvbet','$saet','$fast_db','',$lager)";
            fwrite($log, __linr__." $qtxt\n");
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        } else {
            return ("Manglende varenr eller beskrivelse");
            exit;
        }
    
        #	fwrite($log,__line__." Pris $pris\n");
        if ($string && $r=db_fetch_array(db_select("$string",__FILE__ . " linje " . __LINE__))) {
            $vare_id=$r['id'];
            $varenr=db_escape_string($r['varenr']);
            $enhed=db_escape_string($r['enhed']);
            $folgevare=(int)$r['folgevare'];
    #		$tilfravalg=$r['tilfravalg'];
            $rabatgruppe=$r['rabatgruppe'];
            $varegruppe=(int)$r['gruppe'];
            $samlevare=$r['samlevare'];
            $varerabatgruppe=$r['dvrg']*1;
            if (!$pris && $b2b) $pris=(float)$r['tier_price'];
            $specialType=$r['specialtype'];
            $special_price=(float)$r['special_price'];
            $special_from_date=$r['special_from_date'];
            $special_to_date=$r['special_to_date'];
            $special_from_time=$r['special_from_time'];
            $special_to_time=$r['special_to_time'];
            $serienr=$r['serienr'];
            $beholdning=($r['beholdning'])*1;
            (strpos($r['m_antal'],';'))?list($m_antal,$temp)=explode(";",$r['m_antal'],2):$m_antal=$r['m_antal'];
            $m_antal=trim($m_antal);
            if (!is_numeric($m_antal)) $m_antal=0;
            if (!$varegruppe) {
                return("Varenr $varenr et ikke tilknyttet en varegruppe!");
            }
            if (!$variant_id && in_array($vare_id,$variant_varer)) { //20181223
                return('Brug stregkode ved variant_varer');
                exit;
            }
            if ($folgevare) {
                $qtxt="select varenr from varer where id='$folgevare'";
                if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $folgevare=0;
            }
            if (!$beskrivelse) {
                $beskrivelse=db_escape_string(trim($r['beskrivelse']));
                if ($formularsprog) {
                    $r2=db_fetch_array(db_select("select kodenr from grupper where art='VSPR' and box1 = '$formularsprog'",__FILE__ . " linje " . __LINE__));
                    $kodenr=$r2['kodenr']*1;
                    $r2=db_fetch_array(db_select("select tekst from varetekster where sprog_id='$kodenr' and vare_id='$vare_id'",__FILE__ . " linje " . __LINE__));
                    if ($r2['tekst']) $beskrivelse=db_escape_string($r2['tekst']);
                }
            }
    #		if (!$posnr && $art!='PO' && $r2=db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__))) {
            if (!$posnr && $r2=db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__))) {
                $posnr=$r2['posnr']+1;
            } elseif (!$posnr) $posnr=1;
            $qtxt = "select box4,box6,box7,box8 from grupper ";
            $qtxt.= "where art = 'VG' and kodenr = '$varegruppe' and fiscal_year = '$regnaar'";
            if (!$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
                $alerttekst=findtekst(320,$sprog_id)." $varenr ".findtekst(321,$sprog_id);
                return ("$alerttekst");
            }
            $bogfkto = $r2['box4'];
            $omvare = $r2['box6'];
    #cho __LINE__." $bogfkto = ".$r2['box4']."<br>";
            if (!$momsfri) $momsfri = $r2['box7']; #20170207
            $lagerfort = $r2['box8'];
            if (!$bogfkto) 	{
                $alerttekst=findtekst(319,$sprog_id)." ".$varegruppe."!";
              return ("$alerttekst");
            }
            if ($bogfkto && !$momsfri) {
                $qtxt="select moms from kontoplan where kontonr = '$bogfkto' and regnskabsaar = '$regnaar'";
                $r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                if ($tmp=(int)substr($r2['moms'],1)) {
                    $qtxt="select box1,box2 from grupper where art = 'SM' and kodenr = '$tmp' and fiscal_year = '$regnaar'";
                    $r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                    if ($r2['box1']) $vatAccount=$r2['box1']*1;
                    if ($r2['box2']) $varemomssats=$r2['box2']*1;
                }	else {
                    $varemomssats=$momssats;
                    $vatAccount=0;
                }	
            } else {
                $varemomssats=0;
                $vatAccount=0;
            }
            $SpecialPeriod=0;
    
            if (($special_from_date < $dd || ($special_from_date == $dd && $special_from_time <= $tt)) && 
                ($special_to_date > $dd || ($special_to_date == $dd && $special_to_time >= $tt))) $SpecialPeriod=1;
            if ($SpecialPeriod && $special_price && $specialType=='percent') {
                if ($rabat_ny == 0) $rabat_ny=$special_price;
                $special_price=0;
            }
    
            if (!$pris) {
                $ugedag=date('N');
                $uxtid=date("U");
                $tidspkt=date("H:i:s");
                $qtxt = "select salgspris,kostpris from varetilbud where vare_id='$vare_id' and ugedag='$ugedag' ";
                $qtxt.= "and startdag<='$uxtid' and slutdag >='$uxtid' and starttid <='$tidspkt' and sluttid >='$tidspkt'";
                if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
                    $pris=$r2['salgspris'];
                    $kostpris=$r2['kostpris'];
                } elseif ($SpecialPeriod && $special_price) {# 20161114
                    $pris=$special_price;
                    $kostpris=$r['campaign_cost']*1;
                } else {
                    if ($pris!='0') $pris = (float)$r['salgspris']; #20140124
                    $kostpris = (float)$r['kostpris'];
                    if ($pris == 0 && $kostpris < 1) $fast_db=$kostpris;
                }
            }	elseif ($momsfri) {
                $kostpris=(float)$r['kostpris'];
            } else {
                $ms=$varemomssats;
                if ($momssats<$varemomssats) $varemomssats=$momssats;		
                if ($incl_moms && $varemomssats) $pris=$pris-($pris*$varemomssats/(100+$varemomssats)); # 20190111 fjernet: $art=='PO' && 
                else $pris*=1; #20140124
                $kostpris=$r['kostpris']*1;
            }
    #		fwrite($log,__line__." Pris $pris\n");
    
            if ($pris && $r['salgspris']==0 && $kostpris<1 && $kostpris>0) {
                $fast_db=$kostpris;
                $kostpris=($pris-$pris*$rabat_ny/100)*$kostpris;
            } else $fast_db=0;
        } elseif (!$kopi) {
            if ($webservice) { #20150218
                if ($varenr) {
    #				fwrite($log,__line__." Varenr: $varenr eksisterer ikke\n");
                    return ("Varenr: $varenr eksisterer ikke");
                }	else {
                    return ('0');
                }
                exit;
            } else {
                vareopslag($art,'varenr','beskrivelse',$id,'','','%'.$varenr.'%'); #20150215
                exit;
            }
            return ("Varenr: $varenr eksisterer ikke");
        }
        if (!is_numeric($rabatgruppe)) $rabatgruppe = 0;
        if (!is_numeric($varerabatgruppe)) $varerabatgruppe = 0;
    
    #cho __LINE." P: ".$pris." ".$pris*1 ."<br>";
        $vare_id*=1;
        $m_rabat=0;
        $rabat_ny*=1;
        
        $r2 = db_fetch_array(db_select("select box11 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
        $advar_negativ_lager=$r2['box11'];
        if ($art=='DO' && $lagerfort && !$webservice && $advar_negativ_lager) {  #20140131
            $r=db_fetch_array(db_select("select beholdning from varer where id='$vare_id'",__FILE__ . " linje " . __LINE__));
            $beholdning=$r['beholdning'];
            $r=db_fetch_array(db_select("select sum(ordrelinjer.antal) as antal, sum(ordrelinjer.leveret) as leveret from ordrelinjer,ordrer where ordrelinjer.vare_id='$vare_id' and ordrelinjer.ordre_id=ordrer.id and ordrer.art='DO' and ordrer.status<3",__FILE__ . " linje " . __LINE__));
            $i_ordre=$r['antal']-$r['leveret'];
            $raadig=$beholdning-$i_ordre;
            $tmp=$antal*1;
            if (!$tmp) $tmp=1;
            if ($raadig<=$tmp) {
                $alerttxt="Beholdning:\\t".dkdecimal($beholdning,2)."\\nI ordre:\\t\\t".dkdecimal($i_ordre)."\\nTil rådighed:\\t".dkdecimal($raadig);
                alert($alerttxt);
            }
        }
        if ($linje_id && $art=='DO') $tmp="id='$linje_id'";
        elseif ($art=='PO') {
            $tmp = "vare_id = '$vare_id' and ordre_id='$id' and pris='$pris' and rabat='$rabat_ny' and variant_id='$variant_id' ";
            $tmp.= "and beskrivelse = '". db_escape_string($beskrivelse) ."' and tilfravalg='$tilfravalgNy' and barcode = '$barcodeNew'";
        }
    #	fwrite ($log,__line__." $tmp\n");
        $qtxt="select rabat,posnr,id,antal from ordrelinjer where $tmp";
    #	fwrite($log,__line__." $qtxt\n");
        if(!$folger && !$saet && ((!$kopi && $linje_id && $art=='DO') || $art=='PO') && $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { #20200109
            $antaldiff=$antal;
            $antal=$r['antal']+$antal;
            if (($art!='PO' || $antal) && $antaldiff && $r['id']) {
                if (abs($antal) < 100000000000) {
                    db_modify("update ordrelinjer set m_rabat='0', antal=antal+$antaldiff where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
                    if ($samlevare == 'on') {
                        db_modify("update ordrelinjer set antal=antal/$r[antal]*$antal where samlevare = '$linje_id'",__FILE__ . " linje " . __LINE__);
                    }
                    $r2=db_fetch_array(db_select("select sum(antal) as antal from ordrelinjer where vare_id='$vare_id' and pris='$pris' and rabat='0' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
                    $tmpantal=$r2['antal'];
                }
                if ($m_antal && $tmpantal >= $m_antal) {
                    m_rabat($r['id'],$vare_id,$r['posnr'],$tmpantal,$id,$pris);
                } else {
                    db_modify("update ordrelinjer set m_rabat='0' where ordre_id = '$id' and vare_id = '$vare_id'",__FILE__ . " linje " . __LINE__);
                }
            } elseif ($art=='PO' && $r['id']) db_modify("delete from ordrelinjer where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
        } else {
            if ($kopi || $rabat_ny) $rabat=$rabat_ny;
            else {
                if (!$debitorrabatgruppe && !db_fetch_array(db_select("select id from grupper where art='DRG'",__FILE__ . " linje " . __LINE__))){
                    $debitorrabatgruppe=$debitorgruppe;
                }
                if (!$varerabatgruppe && !db_fetch_array(db_select("select id from grupper where art='DVRG'",__FILE__ . " linje " . __LINE__))){
                    $varerabatgruppe=$varegruppe;
                }
                if (!is_numeric($debitorrabatgruppe)) $debitorrabatgruppe=0;
                if ( !is_numeric($varerabatgruppe)  ) $varerabatgruppe=0;
                if (!isset($rabat)) $rabat = 0;
                $qtxt = "select rabat,rabatart from rabat where vare='$varerabatgruppe' and debitor='$debitorrabatgruppe'";
                if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
                    $rabat=$r2['rabat'];
                    $rabatart=$r2['rabatart'];
                }
            }
    #cho __LINE__." P: ".$pris." ".$pris*1 ."<br>";
             ($linje_id && $art=='DK')?$kred_linje_id=$linje_id:$kred_linje_id='0';
    #cho "$momssats if (!$varemomssats && $varemomssats!='0')<br>";
            if (!$varemomssats && $varemomssats!='0') {
                ($momsfri)?$varemomssats='0':$varemomssats=$momssats;
            }
            $varemomssats*=1;
    #		fwrite($log,__line__." Varemomssats $varemomssats\n");
            #cho __LINE__." P: ".$pris." ".$pris*1 ." $valutakur s&& $valutakurs!=100<br>";
            if ($valutakurs && $valutakurs!=100) {
                $pris=$pris*100/$valutakurs;
                $kostpris=$kostpris*100/$valutakurs;
            }
            if ($momsfri) $VatPrice=$pris;
            else $VatPrice=$pris+$pris*$varemomssats/100;
    #cho __LINE__." P: ".$pris." ".$pris*1 ."<br>";
    #cho "rabarart $rabatart<br>";
    #		if ($variant_type) {
    #			$varianter=explode(chr(9),$variant_type);
    #			for ($y=0;$y<count($varianter);$y++) {
    #				$qtxt="select variant_typer.beskrivelse as vt_besk,varianter.beskrivelse as var_besk from variant_typer,varianter";
    #				$qtxt.=" where variant_typer.id = '$varianter[$y]' and variant_typer.variant_id=varianter.id";
    #				$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
    #				$beskrivelse.=", ".$r1['var_besk']; #.":".$r1['vt_besk'];
    #			}
    #		}
    #cho __LINE__." P: ".$pris." ".$pris*1 ."<br>";
    #cho "insert into ordrelinjer (ordre_id,vare_id,varenr,enhed,beskrivelse,antal,rabat,rabatart,m_rabat,pris,kostpris,momsfri,momssats,posnr,projekt,folgevare,rabatgruppe,bogf_konto,kred_linje_id,kdo,serienr,variant_id) values ('$id','$vare_id','$varenr','$enhed','$beskrivelse','$antal','$rabat','$rabatart','$m_rabat','$pris','$kostpris','$momsfri','$varemomssats','$posnr','0','$folgevare','$rabatgruppe','$bogfkto','$kred_linje_id','$kdo','$serienr','$variant_id')<br>";
    # exit;
            ($webservice) ?$leveres=$antal:$leveres=0; 
            if ($id && is_numeric($posnr)) {
                $momslog=fopen("../temp/$db/momslog.log","a");
                fwrite($momslog, "varenr $varenr - Varemoms $varemomssats Momskto $vatAccount\n");
                fclose ($momslog);
                if ($varemomssats && !$vatAccount) {
                    $alerttxt= __line__." Manglende konto for salgsmoms (Varenr: $varenr indsat uden moms)";
                    alert ($alerttxt);
                    $varemomssats=0;
    #				return ('0');
    #				exit;
                }
                if (($samlevare && !$antal) || $antal=='') $antal=1;
                ($omkunde && $omvare)?$omvbet='on':$omvbet='';
                $antal*=1;
                $leveres*=1;
                if ($lager<1) $lager=1; 
                $posnr = abs($posnr); #20200813
    #			if ($barcodeNew && !$serienr) $serienr = $barcodeNew;
                if ($art != 'PO' && $art != 'DK' && !$webservice && $variantText) $beskrivelse.= " $variantText"; #20211129
                $qtxt = "insert into ordrelinjer ";
                $qtxt.= "(ordre_id,vare_id,varenr,enhed,beskrivelse,antal,rabat,rabatart,procent,m_rabat,";
                $qtxt.= "pris,vat_price,kostpris,momsfri,momssats,posnr,projekt,folgevare,rabatgruppe,";
                $qtxt.= "bogf_konto,vat_account,kred_linje_id,kdo,serienr,variant_id,leveres,samlevare,";
                $qtxt.= "omvbet,saet,fast_db,lev_varenr,tilfravalg,lager,barcode) ";
                $qtxt.= "values ";
                $qtxt.= "('$id','$vare_id','$varenr','$enhed','$beskrivelse','$antal','$rabat','$rabatart','$procent','$m_rabat',";
                $qtxt.= "'$pris','$VatPrice','$kostpris','$momsfri','$varemomssats','$posnr','','$folgevare','$rabatgruppe',";
                $qtxt.= "'$bogfkto','$vatAccount','$kred_linje_id','$kdo','$serienr','$variant_id','$leveres','$samlevare',";
                $qtxt.= "'$omvbet','$saet','$fast_db','$lev_varenr','$tilfravalgNy','$lager','$barcodeNew')";
    #			fwrite($log, __line__." $qtxt\n");
                if (abs($antal) < 100000000000) {
                    db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                    if ($kundedisplay) {
                        kundedisplay($beskrivelse,$VatPrice*$antal,0); #20201206
                    }
                }
                if ($samlevare && !$beholdning) {
                    $r=db_fetch_array(db_select("select max(id) as id from ordrelinjer where vare_id='$vare_id' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
    #				samlevare($id,$art,$r['id'],$vare_id,$antal); udkommenteret 20131129
                }
            }
    #fclose($log);		
            # finder antal af varen på ordren.
    #cho "select sum(antal) as antal from ordrelinjer where vare_id='$vare_id' and pris=$pris and ordre_id='$id<br>";
            $qtxt = "select sum(antal) as antal from ordrelinjer where ";
            $qtxt.= "vare_id='$vare_id' and pris != 0 and rabat='0' and ordre_id='$id'";
            $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
            $tmpantal=$r['antal'];
            if ($m_antal && $tmpantal >= $m_antal) {
                $qtxt = "select max(id) as id from ordrelinjer where ";
                $qtxt.= "vare_id='$vare_id' and pris != 0 and rabat='0' and ordre_id='$id'";
                $r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                m_rabat($r2['id'],$vare_id,0,$tmpantal,$id,$pris);
            }	else {
    #cho "update ordrelinjer set m_rabat='0' where ordre_id = '$id' and vare_id = '$vare_id'<br>";
                db_modify("update ordrelinjer set m_rabat='0' where ordre_id = '$id' and vare_id = '$vare_id'",__FILE__ . " linje " . __LINE__);
            }
        }
        if ($vis_saet && $status) db_modify("update ordrer set felt_2='0' where id = '$id'",__FILE__ . " linje " . __LINE__);
        $sum=$pris*$antal;
    #cho "retur Sum $sum<br>";
        return($sum);
    #	$varenr=$next_varenr;
    #	$antal=NULL;
    } # endfunc opret_orderlinje

    function CreateOrder($data){
        file_put_contents("order.txt", "data: " . print_r($data, true) . "\n", FILE_APPEND);
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
        $weeks = floor($data["days"]/7);
        if(strtolower($res["enhed"]) == "dag"){
            $paidWeeks = $data["days"];
        }else{
            $paidWeeks = number_format($data["days"]/7, 2);
        }
        $discountPeriods = array();
        $discountAmount = array();
            
        if($res["m_antal"] != "" && $res["m_rabat"] != "" && $res["m_antal"] != "0" && $res["m_rabat"] != "0"){
        if(strpos($res["m_antal"], ";")){
            $discountPeriods = explode(";", $res["m_antal"]);
            $discountAmount = explode(";", $res["m_rabat"]);
        }else{
            $discountPeriods[0] = $res["m_antal"];
            $discountAmount[0] = $res["m_rabat"];
        }
        $i = -1;
        foreach($discountPeriods as $period){
            if(strtolower($res["enhed"]) == "dag"){
                if($period <= $data["days"]){
                    $i++;
                }
            }else{
                if($period <= $weeks){
                    $i++;
                }
            }
        }
        if($i > -1){
            if($product["disc_type"] == "percent"){
                if(strtolower($res["enhed"]) == "dag"){
                    $discount = $discountAmount[$i];
                    $product["price"] = $res["salgspris"] * $data["days"];
                    $discountAmount = ($product["price"] * $discount) / 100;
                    $rabatart = "percent";
                }else{
                    $discount = $discountAmount[$i];
                    $product["price"] = $res["salgspris"] * $paidWeeks;
                    $discountAmount = ($product["price"] * $discount) / 100;
                    $rabatart = "percent";
                }
            }else{
                if(strtolower($res["enhed"]) == "dag"){
                    $discount = $discountAmount[$i];
                    $product["price"] = $res["salgspris"] * $data["days"];
                    $discountAmount = $discount * $data["days"];
                    $rabatart = "amount";
                }else{
                    $discount = $discountAmount[$i];
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
        
        
        $query = db_select("SELECT * FROM adresser WHERE id = $data[customer_id]", __FILE__ . " linje " . __LINE__);
        $res2 = db_fetch_array($query);
        $customer["id"] = $res2["id"];
        $customer["name"] = pg_escape_string($res2["firmanavn"]);
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
        $query = db_select("SELECT ordrenr FROM ordrer WHERE art LIKE 'D%' ORDER BY ordrenr DESC LIMIT 1", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $ordrenr = $res["ordrenr"] + 1;
        if($invoiceDate){
            $query = db_modify("INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsdage, kontonr, art, valuta, ordredate, fakturadate, levdate, ordrenr, sum, status, konto_id, momssats, nextfakt, moms) VALUES ('$customer[name]', '$customer[address]', '$customer[zip]', '$customer[city]', '$customer[email]', $customer[payment_days], '$customer[account_number]', '$customer[art]', '$customer[valuta]', '$date', '$data[fromDate]', '$data[fromDate]', $ordrenr, $sum, 1, $customer[konto_id], 25, '$data[toDate]', $moms)", __FILE__ . " linje " . __LINE__);
        }else{
            $query = db_modify("INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsdage, kontonr, art, valuta, ordredate, levdate, ordrenr, sum, status, konto_id, momssats, nextfakt, moms) VALUES ('$customer[name]', '$customer[address]', '$customer[zip]', '$customer[city]', '$customer[email]', $customer[payment_days], '$customer[account_number]', '$customer[art]', '$customer[valuta]', '$date', '$data[fromDate]', $ordrenr, $sum, 1, $customer[konto_id], 25, '$data[toDate]', $moms)", __FILE__ . " linje " . __LINE__);
        }
        $query = db_select("SELECT id FROM ordrer WHERE ordrenr = $ordrenr AND art LIKE 'D%'", __FILE__ . " linje " . __LINE__);
        $order_id = db_fetch_array($query)["id"];
        opret_ordrelinje($order_id, $product["product_id"], $product["product_number"], $paidWeeks, $product["description"], $basePrice, $discount, '100', 'D', '', '1', '', '', '', $rabatart, '0', '', '', '', '', __LINE__);
/*         $query = db_modify("INSERT INTO ordrelinjer (varenr, posnr, vare_id, antal, pris, ordre_id, beskrivelse, enhed, rabat, rabatart) VALUES ('$product[product_number]', 1, $product[product_id], $paidWeeks, $basePrice, $order_id, '$product[description]', '$enhed', $discount, '$rabatart')", __FILE__ . " linje " . __LINE__); */
        $query = db_modify("INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 2, 0, 0, 0, $order_id, 'Stand: $product[name]')", __FILE__ . " linje " . __LINE__);
        $query = db_modify("INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 3, 0, 0, 0, $order_id, 'Udlejning: Fra $data[fromDate] til $data[toDate]')", __FILE__ . " linje " . __LINE__);
        if($discountAmount > 0){
            // reduce decimal to 2 digits
            $discountAmount = number_format($discountAmount, 2);
            // change from . to ,
            $discountAmount = str_replace(".", ",", $discountAmount);
            db_modify("INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 4, 0, 0, 0, $order_id, 'Rabat $discountAmount kr.')", __FILE__ . " linje " . __LINE__);
        }
        db_modify("UPDATE rentalperiod SET order_id = $order_id WHERE id = $data[booking_id]", __FILE__ . " linje " . __LINE__);
    }
?>
