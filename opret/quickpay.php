<?php
require_once '../vendor/autoload.php';
use QuickPay\QuickPay;
$customerData = json_decode(file_get_contents("php://input"), true);

$quickpay = new QuickPay(":2520ab318ec5b5dd395a9302a3266b1b0e0621c76e1c0c9320423d9dc1801507");
$quickpay->mode = 'sandbox';

$allowedChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

$shuffledChars = str_shuffle($allowedChars);

$length = rand(4, 20);
$randomString = substr($shuffledChars, 0, $length);
$amount = $customerData["pris"]*100;

$sub = $quickpay->request->post('/subscriptions', [
    'order_id' => $randomString,
    'currency' => 'DKK',
    'description' => 'Monthly subscription',
])->asArray();

$res = $quickpay->request->put("/subscriptions/$sub[id]/link", [
    'amount' => $amount,
    "language" => "da",
    "deadline" => 180,
])->asArray();

// open link in a new window
echo "<script>window.open('$res[url]','','width=600, height=800');</script>";
$shuffledChars = str_shuffle($allowedChars);
$randomString = substr($shuffledChars, 0, $length);
?>
<script>
    const getStatus = () => {
        fetch(`get_status.php?id=${<?php echo $sub['id'] ?>}`)
        .then(res => res.json())
        .then(data => {
            console.log(data)
            if(data.state == "rejected"){
                console.log('Subscription is rejected')
                /* alert("Betaling er afvist") */
                // relocated user?
            }
            if(data.accepted){
                fetch(`create_recurring.php?id=${<?php echo $sub['id'] ?>}&amount=<?php echo $amount ?>&order_id=<?php echo $randomString ?>`)
                    .then(res => res.json())
                    .then(data => {
                        console.log(data)
                        if(data.accepted){
                            fetch("send_email.php", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json"
                                },
                                body: JSON.stringify(<?php echo $customerData ?>)
                            })
                        }else{
                            console.log('Payment is not accepted')
                        }
                    })
            }else{
                setTimeout(getStatus, 1000)
            }
        })
    }
    getStatus()
</script>