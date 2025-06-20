<?php
@session_start();
$s_id=session_id();

// --------------------------------/admin/aaben_regnskab.php-----patch 2.0.5------2009.02.26--------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

#$tjek=isset($_GET['tjek'])? $_GET['tjek']:NULL;
$css="../css/standard.css";
$title="Aaben regnskab";
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/version.php");
include("../includes/opdat_2.0.php");

if ($db != $sqdb) {
	print "<BODY onLoad=\"javascript:alert('Hmm du har vist ikke noget at g&oslash;re her! Dit IP nummer, brugernavn og regnskab er registreret!')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
	exit;
}

$db=if_isset($_GET['db_id']);

$r=db_fetch_array(db_select("select db from regnskab where id = '$db'",__FILE__ . " linje " . __LINE__));
db_modify("update online set db='$r[db]', revisor='1' where session_id='$s_id'");
	include("../includes/online.php");
#echo "select regnskabsaar from brugere where brugernavn = '$brugernavn'<br>";	
if ($r=db_fetch_array(db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__))) {
#echo "rrr	$r[regnskabsaar]<br>";
		$regnskabsaar=$r['regnskabsaar']*1;
		include("../includes/connect.php");
		db_modify("update online set regnskabsaar='$regnskabsaar', revisor='1' where session_id='$s_id'");
		include("../includes/online.php");
		$r=db_fetch_array(db_select("select box1 from grupper where art = 'VE'",__FILE__ . " linje " . __LINE__));
		$dbver=$r['box1'];
		$tmp = str_replace(".",";",$dbver);		
		list($a, $b, $c)=split(";", trim($tmp));
#echo "$db_ver < $version<br>";
#exit;		 
		if ($db_ver<$version) opdat_2_0($b,$c);	
		print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/menu.php\">";
	} else {
		print "<BODY onLoad=\"javascript:alert('Brugeren $brugernavn er ikke oprettet i regnskab $regnskab')\">";
		print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
		
	}


?>
</tbody></table>
</body></html>
