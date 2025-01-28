<?php
include "../includes/connect.php";

$id = $_GET['id'];
$query = db_select("SELECT db, deb_id FROM mysale WHERE link = '$id'", __FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$db = $row['db'];
$id = $row['deb_id'];
echo json_encode(["db" => $db, "id" => $id]);
?>