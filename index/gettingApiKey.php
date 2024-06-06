<?php
$query = db_select("SELECT var_value, var_name FROM settings WHERE var_grp = 'peppol'", __FILE__ . " linje " . __LINE__);
    while($res = db_fetch_array($query)){
        if($res["var_value"] !== ""){
            if($res["var_name"] == "apiKey"){
                $key = $res["var_value"];
            }elseif($res["var_name"] == "tenantId"){
                $tenantId = $res["var_value"];
            }
        }
    }
$apiKey = $tenantId . "&" . $key;
