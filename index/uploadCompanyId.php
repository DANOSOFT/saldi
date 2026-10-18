<?php
    @session_start();
    $s_id=session_id();
    include("../includes/connect.php");
    $permission_key = 'any';
    include("../includes/online.php");
    $companyID = json_decode(file_get_contents('php://input'), true);
    // 20261001 Sawaneh One row per setting: the new company id replaces the old one; the value is escaped.
    db_modify("DELETE FROM settings WHERE var_name = 'companyID' AND var_grp = 'peppol'", __FILE__ . " linje " . __LINE__);
    $query = db_modify("INSERT INTO settings (var_name, var_value, var_grp) VALUES ('companyID', '".db_escape_string((string) $companyID["companyID"])."', 'peppol')", __FILE__ . " linje " . __LINE__);
    if($query){
        echo json_encode(["succes" => true]);
    }else{
        echo json_encode(["succes" => false]);
    }