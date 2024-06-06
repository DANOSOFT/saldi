<?php
require_once '../vendor/autoload.php';
use QuickPay\QuickPay;

$quickpay = new QuickPay(":2520ab318ec5b5dd395a9302a3266b1b0e0621c76e1c0c9320423d9dc1801507");
$quickpay->mode = 'sandbox';

$res = $quickpay->request->get("/subscriptions/$_GET[id]")->asArray();
echo json_encode($res);
/* echo json_encode(["accepted" => $res["accepted"], "state" => $res["state"]]); */