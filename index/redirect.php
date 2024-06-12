<?php
$gratis = array('Greendal ApS','Greendal.dk','MH Solfilm & Skilte ApS');
$pos = array('Sanderum Pub');
if ($_POST['regnskab']=='pos' || in_array($_POST['regnskab'],$pos)) $action="https://ssl4.saldi.dk/pos/index/login.php";
elseif (in_array($_POST['regnskab'],$gratis)) $action="https://ssl2.saldi.dk/gratis/index/login.php";
else $action='login.php';
?> 
