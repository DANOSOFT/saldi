
<body>
    <?php
        include_once("api.php");
        // $id = 7024;
        // $name = sendInvoice($id, "invoice");
        // echo json_encode($name);
        echo "updateCompany Return: " . json_encode(updateCompany(), JSON_PRETTY_PRINT);
        
    ?>
</body>