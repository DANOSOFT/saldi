<?php
$id=$_GET['id'];
$filnavn=$id."_ok.php";
unlink($filnavn);
$txt="Betalingen er godkendt";
print "<BODY onLoad=\"javascript:alert('$txt')\">";
?>
 
