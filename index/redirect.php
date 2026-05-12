<?php
$saldi = array('saldi');
$asIs  = $_POST['regnskab'];
$low = mb_strtolower($_POST['regnskab'],'UTF-8');
$up = mb_strtoupper($_POST['regnskab'],'UTF-8');
if (in_array($asIs,$saldi) || in_array($low,$saldi) || in_array($up,$saldi)) {
	$action="https://ssl3.saldi.dk/finans/index/login.php";
} else {
	$action="https://ssl3.saldi.dk/pos/index/login.php";
}
?> 
