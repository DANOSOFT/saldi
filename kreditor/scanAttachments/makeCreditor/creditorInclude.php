<?php



    include("insertAccountNumber.php");
    include("makeCreditor.php");
    include("dbInsert.php");

    $makeNewCreditor = if_isset($_GET['makeNewCreditor']);
    $makeCreditor = if_isset($_GET['makeCreditor']);
    $checkAccountNumber = if_isset($_COOKIE['checkAccountNumber']);
    $insertedAccountNumber = if_isset($_GET['useAccountNumber']);

    if($makeNewCreditor == 1) {
        makeNewCreditor($paperflowArray);
    } elseif (isset($makeCreditor)) {
        makeCreditor($makeCreditor, $paperflowArray, $sort, $hreftext);
    } elseif($checkAccountNumber == true) {
        accountNumberCheck($paperflowArray);
    } elseif(isset($insertedAccountNumber)) {
        useInsertedAccountNumber($paperflowArray);
    }



?>