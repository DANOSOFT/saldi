<?php
    include("../includes/connect.php");
    $qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='regnskab' and column_name='invoices'";
    if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
        db_modify("ALTER table ordrer ADD column invoices int",__FILE__ . " linje " . __LINE__);
    }
    if($_GET["db"]){
        $db = $_GET["db"];
        $query = db_select("SELECT * FROM regnskab WHERE db = '$db'", __FILE__ . " linje " . __LINE__);
        $invoices = db_fetch_array($query)["invoices"];
        if($invoices == null || $invoices == ""){
            $invoices = 0;
        }else{
            $invoices = (int)$invoices + 1;
        }
        $query = db_modify("UPDATE regnskab SET invoices = $invoices WHERE db = '$db'", __FILE__ . " linje " . __LINE__);
        if($query){
            echo json_encode(["msg" => "invoice number increased", "db" => $db, "success" => true]);
        }else{
            echo json_encode(["msg" => "invoice number not increased", "db" => $db, "success" => false]);
        }
    }
