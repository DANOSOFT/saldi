<?php




    

    include("makeOrder.php");

    $makeOrder = if_isset($_GET['makeOrder']);


    if (isset($makeOrder)) {
        makeAsOrder($paperflowArray, $makeOrder);
    }






?>