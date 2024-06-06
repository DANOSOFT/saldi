<?php

    print "Hello world";

    $data_array =  array(
        "accept" => "application/json",
        "authorization" => "E5hO8eA6oY7bY8sA1gG2oA6uC5cD6rG5nA2nK5cD5rK3tG5cY8",
        "correlationid" => "6e1a2dfe-21d6-4bbc-ba26-5f385ed26ef5",
        "x-ibm-client-id" => "3e4d6797-afaa-4ff3-ab43-9660497e1de3",
        "x-mobilepay-client-system-version" => "2.1.1",
        "x-mobilepay-merchant-vat-number" => "20756438"
    );

    $get_data =
        callAPI('GET', 'https://api.sandbox.mobilepay.dk/pos/v10/stores', $data_array);
        $response = json_decode($get_data, true);
        $errors = $response['response']['errors'];
        $data = $response['response']['data'][0];

    echo '<pre>'; print_r($response); echo '</pre>';
    echo '<pre>'; print_r($errors); echo '</pre>';
    echo '<pre>'; print_r($data); echo '</pre>';
?>