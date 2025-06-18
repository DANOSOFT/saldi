<?php
// -------- debitor/ansatte.php (modul nr. 6)----------lap 2.1.4 ----- 2010.03.26----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

if ($ansat_id > 0) {
	$query = db_select("select * from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__);
 	$row = db_fetch_array($query);
 	$konto_id=$row['konto_id'];
 	$navn=htmlentities($row['navn'],ENT_COMPAT,$charset);
 	$addr1=htmlentities($row['addr1'],ENT_COMPAT,$charset);
 	$addr2=htmlentities($row['addr2'],ENT_COMPAT,$charset);
 	$postnr=htmlentities($row['postnr'],ENT_COMPAT,$charset);
 	$bynavn=htmlentities($row['bynavn'],ENT_COMPAT,$charset);
 	$email=htmlentities($row['email'],ENT_COMPAT,$charset);
 	$tlf=htmlentities($row['tlf'],ENT_COMPAT,$charset);
 	$fax=htmlentities($row['fax'],ENT_COMPAT,$charset);
 	$mobil=htmlentities($row['mobil'],ENT_COMPAT,$charset);
 	$cprnr=htmlentities($row['cprnr'],ENT_COMPAT,$charset);
 	$notes=htmlentities($row['notes'],ENT_COMPAT,$charset);
	$gruppe=$row['gruppe'];
#echo "gruppe $gruppe<br>";
}
else $ansat_id=0;
?>
