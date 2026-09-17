<?php
    @session_start();
    $s_id=session_id();
    include("../includes/connect.php");
    include("../includes/online.php");
    $companyID = json_decode(file_get_contents('php://input'), true);
    $query = db_modify("INSERT INTO settings (var_name, var_value, var_grp) VALUES ('companyID', '".$companyID["companyID"]."', 'peppol')", __FILE__ . " linje " . __LINE__);
    if($query){
        echo json_encode(["succes" => true]);
    }else{
        echo json_encode(["succes" => false]);
    }