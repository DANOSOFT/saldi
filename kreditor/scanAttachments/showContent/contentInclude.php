<?php


    include("createItem.php");
    include("showContent.php");

    $contentId = if_isset($_GET['showContent']);
    $insertProduct = if_isset($_GET['insertProduct']);

    if (isset($contentId)) {
        showContent($paperflowArray, $contentId);
    } elseif (isset($insertProduct)) {
        prepareItemInsert($paperflowArray, $insertProduct);
    }








?>