<?php
$id=$_GET['id'];
$filnavn=$id."_ok.php";
unlink($filnavn);
$txt="Tak for dit bidrag";
print "<BODY onLoad=\"javascript:alert('$txt')\">";
?>
 
