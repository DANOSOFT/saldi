<?php


    include ("cashDraw.php");


    $cashDraw = if_isset($_GET['makeCashDraw']);

    if (isset($cashDraw)) {
        $arrayNb = $_GET['arrayNb'];
        makeCashDraw($paperflowArray, $cashDraw, $arrayNb);
    }



?>