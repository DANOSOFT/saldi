<?php
@session_start();
$s_id=session_id();
// -----------debitor/bogfor.php--------patch 3.5.3----2015.03.05----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// --------------------------------------------------------------------------
// 2013.05.06 Tilføjet transaktionskontrol.
// 2015.03.05 Betalinger indsættes nu i POS_betalinger hvis betingelser er opfyldt - søg pos_betalinger

$id=NULL;
if (isset($_GET['id'])) $id=($_GET['id']);

if ($id && $id>0) {
	$modulnr=5;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	include("../includes/ordrefunc.php");
	include("pbsfakt.php");

	$genfakt=if_isset($_GET['genfakt']);
	$pbs=if_isset($_GET['pbs']);
	$oioxml=if_isset($_GET['oioxml']);
	$oioubl=if_isset($_GET['oioubl']);
	$mail_fakt=if_isset($_GET['mail_fakt']);
	transaktion('begin');
	$svar=bogfor($id,'');
	if ($svar && $svar!='OK') {
		echo "Svar $svar<br>";;
		print "<BODY onLoad=\"javascript:alert('$svar')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/ordre.php?id=$id\">";
		exit;
	} else {
		$r=db_fetch_array(db_select("select * from ordrer where id = $id",__FILE__ . " linje " . __LINE__));
		if ($r['felt_1'] && $r['felt_3'] && is_numeric($r['felt_2']) && is_numeric($r['felt_4'])){
			if ($r['felt_2']*1 != 0) db_modify("insert into pos_betalinger(ordre_id,betalingstype,amount) values ('$id','$r[felt_1]','$r[felt_2]')",__FILE__ . " linje " . __LINE__);
			if ($r['felt_4']*1 != 0) db_modify("insert into pos_betalinger(ordre_id,betalingstype,amount) values ('$id','$r[felt_3]','$r[felt_4]')",__FILE__ . " linje " . __LINE__);
		}
		transaktion('commit');
	}
	if (!$genfakt) {
		if ($pbs) {
			pbsfakt($id);
			print "<BODY onLoad=\"javascript:alert('Faktura er tilf&oslash;jet liste over PBS betalinger')\">";
		} elseif ($oioubl) {
			if ($popup) print "<BODY onLoad=\"JavaScript:window.open('oioubl_dok.php?id=$id&doktype=$oioubl' , '' , '$jsvars');\">";
			else {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=oioubl_dok.php?id=$id&doktype=$oioubl\">";
				exit;
			}
		} elseif ($oioxml) {
			if ($popup) print "<BODY onLoad=\"JavaScript:window.open('oioxml_dok.php?id=$id&doktype=$oioxml' , '' , '$jsvars');\">";
			else {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=oioxml_dok.php?id=$id&doktype=$oioxml\">";
				exit;
			}
		} else {
			if ($popup) print "<BODY onLoad=\"JavaScript:window.open('formularprint.php?id=$id&formular=4' , '' , '$jsvars');\">";
			else {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=formularprint.php?id=$id&formular=4\">";
				exit;
			}
		}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	} else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	}
}
?>
</body></html>

