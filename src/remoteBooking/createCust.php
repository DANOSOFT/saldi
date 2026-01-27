<?php
include "../includes/connect.php";

// get post data
$data = json_decode(file_get_contents('php://input'), true);
$custId = $data['id'];
$db = $data['db'];
$email = $data['email'];

$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
$randomString = '';
for ($i = 0; $i < 35; $i++) {
    $randomString .= $chars[rand(0, strlen($chars) - 1)];
}

$url = $_SERVER['REQUEST_URI'];
$pathSegments = array_filter(explode('/', $url));
$firstFolder = reset($pathSegments);
$url = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . "/" . $firstFolder . "/mysale/mysale.php?id=$randomString";
$query = db_select("SELECT * FROM mysale WHERE deb_id = '$custId' AND db = '$db'", __FILE__ . " linje " . __LINE__);

if(db_num_rows($query) > 0){
    echo "Customer already exists";
}else{
    db_modify("INSERT INTO mysale (deb_id, db, email, link) VALUES ($custId, '$db', '$email', '$url')", __FILE__ . " linje " . __LINE__);
}