<?php
include __DIR__ . "/../includes/connect.php";
include_once(__DIR__ . "/../includes/publicTenantDb.php");

// get post data
$data = json_decode(file_get_contents('php://input'), true);
$custId = (int)($data['id'] ?? 0);
$db = public_tenant_db($data['db'] ?? '');
$email = db_escape_string((string)($data['email'] ?? ''));

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
    db_modify("INSERT INTO mysale (deb_id, db, email, link) VALUES ($custId, '$db', '$email', '" . db_escape_string($url) . "')", __FILE__ . " linje " . __LINE__);
}