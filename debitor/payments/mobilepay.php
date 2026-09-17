<?php
@session_start();
$s_id = session_id();

include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/std_func.php");
include ("../../includes/stdFunc/dkDecimal.php");
include ("../../includes/stdFunc/usDecimal.php");

include ("mobilepay/mobilepay.php");
?>
