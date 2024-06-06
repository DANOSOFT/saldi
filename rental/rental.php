<?php
    if ($_GET["password"] !== "tZm8uofwtuW3n20"){
        echo json_encode("Du har ikke adgang til denne side");
        exit();
    }

    @session_start();
    $s_id=session_id();
    include("../includes/connect.php");

    $query = db_select("SELECT * FROM online WHERE session_id = '$s_id' ORDER BY logtime DESC LIMIT 1", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $db = trim($res["db"]);
    $connection = db_connect($sqhost, $squser, $sqpass, $db);

    if(isset($_GET["customers"])){
        $query = db_select("SELECT * FROM rentalperiod", __FILE__ . " linje " . __LINE__);
        $i = 0;
        if(db_num_rows($query) === 0){
            echo json_encode("Der er ingen bookinger");
            exit();
        }
        while($res = db_fetch_array($query)){
            $query2 = db_select("SELECT id, firmanavn, kontonr FROM adresser WHERE id = $res[cust_id]", __FILE__ . " linje " . __LINE__);
            $res2 = db_fetch_array($query2);

            $customers[$i]["name"] = $res2["firmanavn"];
            $customers[$i]["account_number"] = $res2["kontonr"];
            $customers[$i]["from"] = $res["rt_from"];
            $customers[$i]["to"] = $res["rt_to"];
            $customers[$i]["item_id"] = $res["item_id"];
            $customers[$i]["id"] = $res2["id"];
            $customers[$i]["booking_id"] = $res["id"];
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
        $query = db_select("SELECT id, firmanavn FROM adresser ORDER BY firmanavn ASC", __FILE__ . " linje " . __LINE__);
        $i = 0;

        while($res = db_fetch_array($query)){
            $customers[$i]["id"] = $res["id"];
            $customers[$i]["name"] = $res["firmanavn"];
            $i++;
        }

        echo json_encode($customers);
    }

    if(isset($_GET["getAllItems"])){
        $query = db_select("SELECT * FROM rentalitems", __FILE__ . " linje " . __LINE__);
        $i = 0;
        while($res = db_fetch_array($query)){
            $items[$i]["id"] = $res["id"];
            $items[$i]["item_name"] = $res["item_name"];
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

        $query = db_select("INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to) VALUES ($item_id, $customer_id, $from, $to)", __FILE__ . " linje " . __LINE__);
        if($query){
            echo json_encode("Booking oprettet");
        }else{
            echo json_encode("Booking kunne ikke oprettes");
        }
    }

    if(isset($_GET["deleteBooking"])){
        $id = db_escape_string($_GET["deleteBooking"]);
        $query = db_select("DELETE FROM rentalperiod WHERE id = $id", __FILE__ . " linje " . __LINE__);
        if($query){
            echo json_encode("Booking slettet");
        }else{
            echo json_encode("Booking kunne ikke slettes");
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
        $query = db_select("UPDATE rentalperiod SET rt_from = $from, rt_to = $to WHERE cust_id = $id", __FILE__ . " linje " . __LINE__);
        if($query){
            echo json_encode("Booking opdateret");
        }else{
            echo json_encode("Booking kunne ikke opdateres");
        }
    }

    if(isset($_GET["getBooking"])){
        $id = db_escape_string($_GET["getBooking"]);
        $query = db_select("SELECT * FROM rentalperiod WHERE cust_id = $id", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $booking["id"] = $res["id"];
        $booking["item_id"] = $res["item_id"];
        $booking["customer_id"] = $res["cust_id"];
        $query2 = db_select("SELECT firmanavn FROM adresser WHERE id = $res[cust_id]", __FILE__ . " linje " . __LINE__);
        $res2 = db_fetch_array($query2);
        $booking["name"] = $res2["firmanavn"];
        $booking["from"] = $res["rt_from"];
        $booking["to"] = $res["rt_to"];
        echo json_encode($booking);
    }

    // req from notesEtc.php line 32 in lager/varekort_includes folder
    if(isset($_GET["subItemId"])){
        $id = db_escape_string($_GET["subItemId"]);
        $query = db_select("SELECT * FROM rentalitems WHERE product_id = $id", __FILE__ . " linje " . __LINE__);
        
        if(pg_num_rows($query) > 0){
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
                $query = db_select("INSERT INTO rentalitems (item_name, product_id) VALUES ('$name', $item[id])", __FILE__ . " linje " . __LINE__);
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
            $query = db_select("INSERT INTO rentalitems (item_name, product_id) VALUES ('$item[item_name]', $item[id])", __FILE__ . " linje " . __LINE__);
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
        $query = db_select("UPDATE rentalitems SET item_name = '$item_name' WHERE id = $id", __FILE__ . " linje " . __LINE__);
        if($query){
            echo json_encode("Varen er nu opdateret");
        }else{
            echo json_encode("Der skete en fejl");
        }
    }
    
    if(isset($_GET["deleteItem"])){
        $id = db_escape_string($_GET["deleteItem"]);
        $query = db_select("DELETE FROM rentalitems WHERE id = $id", __FILE__ . " linje " . __LINE__);
        if($query){
            echo json_encode("Varen er nu fjernet fra udlejning");
        }else{
            echo json_encode("Der skete en fejl");
        }
    }
?>