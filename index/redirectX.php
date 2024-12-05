<?php
$ssl4pos=array('bizsys','Klunserkongen','LoppeWorld');
$ssl4no = array('Bærekraftig handel','DGH Franchise AS test','DGH Stord','DGH Stord test','Gerners','Green Kids Second Hand');
array_push($ssl4no,'Grønn Sirkel','Grønne Byggevare','Grønne Byggevare test','Juggel og Juveler','Wenche');
array_push($ssl4no,'Kvito','Kvito test','no','Notodden','Repertoire','Juggel og Juveler test','Wenche');
array_push($ssl4no,'Kidza SecondHand','Kidza SecondHand test');
$ssl7pos=array('Kræmmergården Dragør');
$finans = array('DANOSOFT','Mariagerfjord Udlejerforening','saldi');
if (in_array($_POST['regnskab'],$ssl4pos)) $action="https://ssl4.saldi.dk/pos/index/login.php";
elseif (in_array($_POST['regnskab'],$ssl4no)) $action="https://ssl4.saldi.dk/no/index/login.php";
elseif (in_array($_POST['regnskab'],$ssl7pos)) $action="https://ssl7.saldi.dk/pos/index/login.php";
elseif (in_array($_POST['regnskab'],$finans)) $action="https://ssl5.saldi.dk/finans/index/login.php";
elseif ($_POST['regnskab'] == 'Gen&Brug') $action="https://ssl4.saldi.dk/flatpay/index/login.php";
else $action="https://ssl4.saldi.dk/pos/index/login.php";
?> 
