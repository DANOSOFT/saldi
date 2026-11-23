<?php
    @session_start();
    $s_id=session_id();
    include("../includes/connect.php");
    // 20261018 Sawaneh Review 2 (PR #803): changes the company's Peppol/EasyUBL registration - integrations write, POST and token.
    $permission_key = 'settings.integrations';
    $permission_level = 'write';
    include("../includes/online.php");
    perm_require_post_csrf(true);
    $companyID = json_decode(file_get_contents('php://input'), true);
    // 20261001 Sawaneh One row per setting: the new company id replaces the old one; the value is escaped.
    db_modify("DELETE FROM settings WHERE var_name = 'companyID' AND var_grp = 'peppol'", __FILE__ . " linje " . __LINE__);
    $query = db_modify("INSERT INTO settings (var_name, var_value, var_grp) VALUES ('companyID', '".db_escape_string((string) $companyID["companyID"])."', 'peppol')", __FILE__ . " linje " . __LINE__);
    if($query){
        echo json_encode(["succes" => true]);
    }else{
        echo json_encode(["succes" => false]);
    }