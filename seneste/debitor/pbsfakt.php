<?php
#@session_start();
#$s_id=session_id();
// ------------debitor/pbsfakt.php------- patch 2.1.2 ---2010.02.22------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
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
if (!function_exists('pbsfakt')) {
	function pbsfakt($id) {

		if ($id && $id>0) {
			if ($r=db_fetch_array(db_select("select id from pbs_liste where afsendt = ''",__FILE__ . " linje " . __LINE__))) $liste_id = $r['id'];
			else {
				$liste_date=date("Y-m-d");
				$afsendt=NULL;
				db_modify("insert into pbs_liste (liste_date,afsendt) values ('$liste_date','$afsendt')",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select id from pbs_liste where afsendt = ''",__FILE__ . " linje " . __LINE__));
				$liste_id = $r['id'];
			}
			if (db_fetch_array(db_select("select id from pbs_ordrer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__))) {
				echo "Faktura nr $r[fakturanr] findes allerede i PBS liste<br>";
			}	else {
				$r=db_fetch_array(db_select("select fakturanr, konto_id from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
				$konto_id=$r['konto_id'];
				db_modify("insert into pbs_ordrer (liste_id,ordre_id) values ('$liste_id','$id')",__FILE__ . " linje " . __LINE__);
				echo "Faktura nr $r[fakturanr] tilf&oslash;jet til PBS liste<br>";	
			}
		}	
	}	
}
######################################################################################################################################
?>
</body></html>
