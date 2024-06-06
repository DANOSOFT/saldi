<?php

    include("showPdf.php");

    $showPdf = if_isset($_GET['showPdf']);
    if (isset($showPdf)) {
        showPdf($paperflowArray, $showPdf);
    }




?>