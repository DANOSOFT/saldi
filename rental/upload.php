<?php
// rental/upload.php
//
// History:
// 20261105 CL/LH A row for days its stand has another booking on is not imported, with the stand locked from that check until the booking is committed (rental_begin_stand_lock()), as in every save of a booking.
//                It goes to customer.txt with the other rows that are not imported, and so does a row whose period ends before it starts.
//
    // get uploaded file
    @session_start();
    $s_id=session_id();
    $header = "nix";
    $bg = "nix";
    include("../includes/connect.php");
    $permission_key = 'debitor.konti';
    include("../includes/online.php");
    include_once(__DIR__ . "/../includes/stdFunc/rentalPeriodTaken.php");
    if(isset($_POST["submit"])){
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
    
    // Attempt to move the uploaded file to your desired location
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
        echo "The file ". basename( $_FILES["fileToUpload"]["name"]). " has been uploaded.";
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
    
    
    echo $target_file;
    // get data from comma separated file and make multilevel array with the data
    $file = fopen($target_file, "r");
    if ($file === false) {
        die("Failed to open $target_file");
    }
    $data = array();
    while (($line = fgetcsv($file)) !== FALSE) {
        $data[] = $line;
    }
    fclose($file);

    array_shift($data);
    

    // send data to db
    while(!empty($data)){
        $row = array_shift($data);
        $row = explode(";", $row[0]);
        $query = db_select("SELECT id FROM adresser WHERE kontonr = '$row[0]'", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) == 0){
            // store all $row info in file for later integration
            $file = fopen("customer.txt", "a");
            fwrite($file, $row[0] . "," . $row[1] . "," . $row[2] . "," . $row[3] . "," . $row[4] . "," . $row[5] . "," . $row[6] . "," . $row[7] . "\n");
            fclose($file);
            continue;
        }else{
            $res = db_fetch_array($query);
            $customer_id = $res['id'];
        }
        $query = db_select("SELECT id FROM rentalitems WHERE LOWER(item_name) = LOWER('$row[4]')", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) == 0){
            if($row[3] == "Gulvplads"){
                $row[3] = "Gulvstand (1 uge)";
            }
            if($row[3] == "Gulv - én ting"){
                $row[3] = "Gulv - Én ting";
            }
            $query = db_select("SELECT id FROM varer WHERE LOWER(beskrivelse) ~* LOWER('$row[3]') LIMIT 1", __FILE__ . " linje " . __LINE__);
            if(db_num_rows($query) < 1){
                continue;
            }
            $res = db_fetch_array($query);
            $product_id = $res['id'];
            $qtxt = "INSERT INTO rentalitems (item_name, product_id) VALUES ('$row[4]', $product_id)";
            db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            $query = db_select("SELECT id FROM rentalitems WHERE item_name = '$row[4]'", __FILE__ . " linje " . __LINE__);
            $res = db_fetch_array($query);
            $item_id = $res['id'];
        }else{
            $res = db_fetch_array($query);
            $item_id = $res['id'];
        }
        // make row[6] and row[7] to unix date format they are in DD-MM-YYYY
        $from = strtotime($row[6]);
        $to = strtotime($row[7]);
        if($from === false || $to === false || $to < $from){
            $file = fopen("customer.txt", "a");
            fwrite($file, $row[0] . "," . $row[1] . "," . $row[2] . "," . $row[3] . "," . $row[4] . "," . $row[5] . "," . $row[6] . "," . $row[7] . "\n");
            fclose($file);
            continue;
        }
        // The stand stays locked from the checks below until the booking is committed, as in every save of a booking,
        // so a public or staff booking of it at the same time waits and then finds this one.
        if(!rental_begin_stand_lock($item_id)){
            continue;
        }
        // check if it is already in db
        $query = db_select("SELECT id FROM rentalperiod WHERE item_id = $item_id AND cust_id = $customer_id AND rt_from = $from AND rt_to = $to", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) > 0){
            transaktion('rollback');
            continue;
        }
        // Another booking of the stand on one of the row's days: the row is not imported.
        if(rental_period_booked($item_id, $from, $to)){
            transaktion('rollback');
            $file = fopen("customer.txt", "a");
            fwrite($file, $row[0] . "," . $row[1] . "," . $row[2] . "," . $row[3] . "," . $row[4] . "," . $row[5] . "," . $row[6] . "," . $row[7] . "\n");
            fclose($file);
            continue;
        }
        db_modify("INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id) VALUES ($item_id, $customer_id, $from, $to, 0)", __FILE__ . " linje " . __LINE__);
        // get last inserted id
        $query = db_select("SELECT id FROM rentalperiod WHERE item_id = $item_id AND cust_id = $customer_id AND rt_from = $from AND rt_to = $to", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        transaktion('commit');
        $period_id = $res['id'];
        // save the id in a file seperated by comma
        $file = fopen("period_id.txt", "a");
        fwrite($file, $period_id . ",");
        fclose($file);
        if($row[5] == "ja"){
            db_modify("INSERT INTO rentalreserved (item_id, \"from\", \"to\", comment) VALUES ($item_id, $from, $to, '')", __FILE__ . " linje " . __LINE__);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>saldi</title>
</head>
<body>
    <form action="upload.php" method="post" enctype="multipart/form-data">
        Select file to upload:
        <input type="file" name="fileToUpload" id="fileToUpload">
        <input type="submit" value="Upload File" name="submit">
    </form>
</body>
</html>
