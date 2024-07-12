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
        if($data["from"] === "" || $data["to"] === "" || $data["id"] === ""){
            echo json_encode("Udfyld alle felter");
            exit();
        }
        $id = db_escape_string($data["id"]);
        $from = db_escape_string($data["from"]);
        $to = db_escape_string($data["to"]);
        $data["booking_id"] = $id;
        $query = db_select("SELECT * FROM rentalperiod WHERE id = $id", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $data["item_id"] = $res["item_id"];
        $order_id = $res["order_id"];
        $res = db_select("SELECT status FROM ordrer WHERE id = $order_id", __FILE__ . " linje " . __LINE__);
        if($res["status"] >= 3){
            $query = db_select("SELECT id FROM ordrer WHERE kred_ord_id = $order_id", __FILE__ . " linje " . __LINE__);
            if(db_num_rows($query) > 0){
                $query = db_modify("UPDATE rentalperiod SET rt_from = $from, rt_to = $to WHERE id = $id", __FILE__ . " linje " . __LINE__);
                echo json_encode("Booking opdateret");
                exit();
            }
            echo json_encode("Booking kan ikke opdateres, da den er faktureret");
            exit();
        }else{
            db_modify("UPDATE rentalperiod SET rt_from = $from, rt_to = $to WHERE id = $id", __FILE__ . " linje " . __LINE__);
            echo json_encode("Booking opdateret");
        }
    }

    if(isset($_GET["getBooking"])){
        $id = db_escape_string($_GET["getBooking"]);
        $query = db_select("SELECT * FROM rentalperiod WHERE id = $id", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $booking["id"] = $res["id"];
        $booking["item_id"] = $res["item_id"];
        $booking["customer_id"] = $res["cust_id"];
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
        $query = db_select("SELECT * FROM rentalperiod WHERE item_id = $id", __FILE__ . " linje " . __LINE__);
        while($row = db_fetch_array($query)){
            $query2 = db_modify("DELETE FROM rentalperiod WHERE item_id = $id", __FILE__ . " linje " . __LINE__);
        }
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
        $res = db_fetch_array($query);
        echo json_encode($res);
    }

    if(isset($_GET["updateSettings"])){
        $data = json_decode(file_get_contents('php://input'), true);
        $query = db_select("SELECT * FROM rentalsettings", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) <= 0)
            $query = db_modify("INSERT INTO rentalsettings (booking_format, search_cust_name, search_cust_number, search_cust_tlf, start_day, deletion, find_weeks, end_day, put_together, pass, use_password, invoice_date) VALUES ('$data[booking_format]', '$data[search_cust_name]', '$data[search_cust_number]', '$data[search_cust_tlf]', '$data[start_day]', '$data[deletion]', '$data[find_weeks]', '$data[end_day]', '$data[put_together]', '$data[password]', '$data[use_password]', '$data[invoice_date]')", __FILE__ . " linje " . __LINE__);
        else
            $query = db_modify("UPDATE rentalsettings SET booking_format = $data[booking_format], search_cust_name = $data[search_cust_name], search_cust_number = $data[search_cust_number], search_cust_tlf = $data[search_cust_tlf], start_day = $data[start_day], deletion = $data[deletion], find_weeks = $data[find_weeks], end_day = $data[end_day], put_together = $data[put_together], use_password = $data[use_password], pass = '$data[password]', invoice_date = $data[invoice_date]", __FILE__ . " linje " . __LINE__);
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
            if($period <= $weeks){
                $i++;
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
        $query = db_modify("INSERT INTO ordrelinjer (varenr, posnr, vare_id, antal, pris, ordre_id, beskrivelse, enhed, rabat, rabatart) VALUES ('$product[product_number]', 1, $product[product_id], $paidWeeks, $basePrice, $order_id, '$product[description]', '$enhed', $discount, '$rabatart')", __FILE__ . " linje " . __LINE__);
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
