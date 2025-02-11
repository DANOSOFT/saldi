<?php
// -------- debitor/ansatte_save.php (modul nr. 6)----------lap 2.9.4 ----- 2010.03.26----------
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
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

 if ($_GET){
	if (isset($_GET['id'])) (int)$ansat_id=$_GET['id'];
	else $ansat_id = $_GET['ansat_id']*1;
 	$returside= $_GET['returside'];
 	$ordre_id = $_GET['ordre_id'];
 	$fokus = $_GET['fokus'];
	$konto_id=$_GET['konto_id'];
}

if ($_POST){
	if (isset($_POST['id'])) (int)$ansat_id=$_POST['id'];
 	else $ansat_id=(int)$_POST['ansat_id'];
 	$submit=db_escape_string(trim($_POST['submit']));
 	$konto_id=$_POST['konto_id'];
 	$navn=db_escape_string(trim($_POST['navn']));
 	$addr1=db_escape_string(trim($_POST['addr1']));
 	$addr2=db_escape_string(trim($_POST['addr2']));
 	$postnr=db_escape_string(trim($_POST['postnr']));
 	$bynavn=db_escape_string(trim($_POST['bynavn']));
 	$tlf=db_escape_string(trim($_POST['tlf']));
 	$fax=db_escape_string(trim($_POST['fax']));
 	$mobil=db_escape_string(trim($_POST['mobil']));
 	$email=db_escape_string(trim($_POST['email']));
 	$cprnr=db_escape_string(trim($_POST['cprnr']));
 	$notes=db_escape_string(trim($_POST['notes']));
 	$ordre_id = $_GET['ordre_id'];
 	$returside=$_POST['returside'];
 	$fokus=$_POST['fokus'];

 	if ($submit=="Slet") {
 	 	if ($ansat_id) db_modify("delete from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__); 
 		print "<meta http-equiv=\"refresh\" content=\"0;URL=kunder.php?funktion=ret_kunde&amp;konto_id=$konto_id\">";
 	} else {
		if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
 	 	if (($ansat_id==0)&&($navn)){
 	 	 	$query = db_modify("insert into ansatte (navn, konto_id, addr1, addr2, postnr, bynavn, tlf, fax, mobil, email, cprnr, notes, lukket) values ('$navn', '$konto_id', '$addr1', '$addr2', '$postnr', '$bynavn', '$tlf', '$fax', '$mobil', '$email', '$cprnr', '$notes', '')",__FILE__ . " linje " . __LINE__);
 	 	 	$query = db_select("select id from ansatte where konto_id = '$konto_id' and navn='$navn' order by id desc",__FILE__ . " linje " . __LINE__);
 	 	 	$row = db_fetch_array($query);
 	 	 	$ansat_id = $row['id'];
 	 	}
 	 	elseif ($ansat_id > 0){
			db_modify("update ansatte set navn = '$navn', konto_id = '$konto_id', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', email = '$email', tlf = '$tlf', fax = '$fax', mobil = '$mobil', cprnr = '$cprnr', notes = '$notes', lukket = '' where id = '$ansat_id'",__FILE__ . " linje " . __LINE__);
 	 	}
 	}
}
?>
