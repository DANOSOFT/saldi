<?php
@session_start();
$s_id = session_id();
include("../includes/connect.php");
include("../includes/online.php");

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id > 0 && !empty($brugernavn)) {
    $qtxt = "UPDATE ordrer SET hvem = '', tidspkt = '0' WHERE id = '$id' AND hvem = '" . db_escape_string($brugernavn) . "' AND art NOT IN ('DO','DK')";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
?>
