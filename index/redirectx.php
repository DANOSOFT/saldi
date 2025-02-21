<?php
$ssl4POS=array('Klunserkongen','Kræmmergården Dragør','LoppeWorld');
$ssl4no = array('DGH Franchise AS test','DGH Stord','DGH Stord test','Gerners','Green Kids Second Hand','Grønn Sirkel','Grønne Byggevare');
array_push($ssl4no,'Grønne Byggevare test','Juggel og Juveler','Juggel og Juveler test','Kidza SecondHand','Kidza SecondHand test');
array_push($ssl4no,'Kvito','Kvito test','no','Notodden'); 
if (in_array($_POST['regnskab'],$ssl4POS)) $action="https://ssl4.saldi.dk/POS/index/login.php";
elseif (in_array($_POST['regnskab'],$ssl4no)) $action="https://ssl4.saldi.dk/no/index/login.php";
elseif ($_POST['regnskab']=='Mariagerfjord Udlejerforening') $action="https://ssl5.saldi.dk/finans/index/login.php";
else $action="https://ssl4.saldi.dk/pos/index/login.php";
?> 
