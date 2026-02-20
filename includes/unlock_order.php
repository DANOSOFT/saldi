<?php
@session_start();
include("../includes/connect.php");
include("../includes/online.php");

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id > 0 && !empty($brugernavn)) {
    // Check if the current user is the one holding the lock
    $qtxt = "UPDATE ordrer SET hvem = '', tidspkt = '0' WHERE id = '$id' AND hvem = '" . db_escape_string($brugernavn) . "'";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
?>
