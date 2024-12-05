<?php
    @session_start();
    $s_id=session_id();
    $header = "nix";
    $bg = "nix";
    include("../includes/connect.php");
    include("../includes/online.php");

    $query = db_select("SELECT varenr, kostpris, gruppe FROM varer", __FILE__ . " linje " . __LINE__);
    $file = fopen("../magento/data.csv", "w");
    fwrite($file, "id,item_group_id,cost_of_goods_sold");
    while($row = db_fetch_array($query)){
        $cost = $row['kostpris'];
        $group = $row['gruppe'];
        $id = $row['varenr'];
        fwrite($file, "\n" . $id . "," . $group . "," . $cost . " DKK");
    }
?>