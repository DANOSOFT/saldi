<?php
require_once '../vendor/autoload.php';
use QuickPay\QuickPay;

$quickpay = new QuickPay(":2520ab318ec5b5dd395a9302a3266b1b0e0621c76e1c0c9320423d9dc1801507");
$quickpay->mode = 'sandbox';

$res = $quickpay->request->post("/subscriptions/$_GET[id]/recurring",[
    "amount" => $_GET["amount"],
    "auto_capture" => true,
    "order_id" => $_GET["order_id"],
    "auto_finalize" => true,
])->asArray();

echo json_encode($res);