<?php
function logon($s_id,$regnskab,$brugernavn,$password,$sqhost,$squser,$sqpass,$sqdb) {
	$password=md5($password);
	$unixtime=date("U");
	include("../includes/db_query.php");
	include ("../includes/connect.php");
#	db_modify("delete from online where  session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
#cho "select * from regnskab where regnskab = '$regnskab'";
	if ($r=db_fetch_array(db_select("select * from regnskab where regnskab = '$regnskab'",__FILE__ . " linje " . __LINE__))) {
		if ($db = trim($r['db'])) {
			$connection = db_connect ($sqhost,$squser,$sqpass,$db);
			if ($connection) {
#cho "select id from brugere where brugernavn='PBS_TILMELDING'<br>";
				$r=db_fetch_array(db_select("select id from brugere where brugernavn='PBS_TILMELDING'",__FILE__ . " linje " . __LINE__));
				if ($r['id']) {
				#				db_modify("insert into online (session_id, brugernavn, db, dbuser) values ('$s_id', '$brugernavn', '$db', '$squser')",__FILE__ . " linje " . __LINE__);
#				include ("../includes/online.php");
#				if ($r = db_fetch_array(db_select("select * from brugere where brugernavn = '$brugernavn' and kode='$password'",__FILE__ . " linje " . __LINE__))) {
#					$rettigheder=trim($r['rettigheder']);
#					$regnskabsaar=$r['regnskabsaar']*1;
#				include ("../includes/connect.php");
#			$fp=fopen("../temp/.ht_$db.log","a");
#			fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": OK jeg er inde ".$s_id."\n");
#			fclose($fp);
#					db_modify("update online set regnskabsaar='$regnskabsaar', rettigheder='$rettigheder' where session_id='$s_id'",__FILE__ . " linje " . __LINE__);
					$return='0'.chr(9).$s_id;
				} else {
#					db_modify("delete from online where  session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
					$return="1".chr(9)."Username or password error";
				}	
			} else $return="1".chr(9)."Connection to database failed";
		} else $return="1".chr(9)."Unknown financial report";
	} else return $return="1".chr(9)."Unknown financial report";
	return ($return);
}

function tjek($metode,$belob,$bank_navn,$bank_reg,$bank_konto,$kontakt,$cvrnr,$firmanavn,$addr1,$postnr,$bynavn,$email,$tlf) {
	$alert='OK';
	if (!$belob || $belob<50) $alert="Beløb ($belob) skal være min. 50,-"; 
	if ($metode=='PBS') {
		if (!$bank_navn) $alert="Pengeinstitut navn ikke angivet";
		if (!$bank_reg) $alert="Reg nr. ikke angivet";
		if (!$bank_konto) $alert="Konto nr. ikke angivet";
		if (!$cvrnr && $firmanavn) $alert="Cvr nr. ikke angivet";
		elseif (!$cvrnr) $alert="Cpr nr. ikke angivet";
	}
	if (!$kontakt && $firmanavn) $alert="Kontakt ikke angivet";
	elseif (!$kontakt) $alert="Navn ikke angivet";
	if (!$addr1) $alert="Adresse ikke angivet"; 
	if (!$postnr) $alert="Postnr ikke angivet"; 
	if (!$bynavn) $alert="By ikke angivet"; 
	if (!$email) $alert="email ikke angivet"; 
	return ("$alert");
}
?>