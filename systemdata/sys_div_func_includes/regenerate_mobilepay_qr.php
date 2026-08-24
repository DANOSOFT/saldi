<?php
@session_start();
$s_id = session_id();

include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/std_func.php");

$pos_id = (int) if_isset($_GET['pos_id'], 0);

if ($pos_id > 0) {
	$qtxt = "delete from settings where var_name = 'qrkodeuri' AND var_grp = 'mobilepay' AND pos_id = $pos_id";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

header("Location: ../diverse.php?sektion=div_valg");
exit;
?>
