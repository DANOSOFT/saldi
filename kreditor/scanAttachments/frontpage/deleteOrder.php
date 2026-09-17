<?php

    if(isset($_GET['deleteId'])) {
        $id = $_GET['deleteId'];
        //print "<h2> To be deleted, with id: $id </h2>";

        $checkvoucher = inputCheck($id);

        $curl = curl_init();

        curl_setopt_array($curl,
            array(
                CURLOPT_URL => "https://api.bilagscan.dk/v1/vouchers/$checkvoucher/pdf",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_HTTPHEADER => array(
                    "Accept: application/json",
                    "Authorization: Bearer 5ce1ec7e3d2752c24fbbc7cecfef4a10752a80ba97f46e16"
                ),
            )
        );

        $response = curl_exec($curl);

        curl_close($curl);

        if(http_response_code(200)){
            echo "Voucher successfully deleted";
        } else {
            echo "error while deleting voucher";
        }
    }

    function inputCheck($name)
    {
        $data = trim($name);
        $data = stripslashes($name);
        $data = htmlspecialchars($name); //to check that malicious content is not inserted to the database
        return $name;
    }

?>