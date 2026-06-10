<?php
// ------------- api/hent_ordrer.php ---------- lap 3.5.3----2015.04.21-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// dog under iagttagelse af følgende:
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
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

print "<html>";
print "<head><title>Hent ocs_ordrer</title><meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8;\">";
print "<meta http-equiv=\"content-language\" content=\"da\">";
print "<meta name=\"google\" content=\"notranslate\">";
print "</head><body>";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");

(isset($_GET['vare_id']))?$vare_id=$_GET['vare_id']:$vare_id=NULL;
(isset($_GET['shop_id']))?$shop_id=$_GET['shop_id']:$shop_id=NULL;

if ($vare_id && $shop_id) {
	$qtxt="select id from shop_varer where saldi_id='$vare_id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['id']) $qtxt="update shop_varer set shop_id='$shop_id' where id ='$r[id]'";
	else $qtxt="insert into shop_varer(saldi_id,shop_id) values ('$vare_id','$shop_id')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
print "</body></html>";
print "<body onload=\"javascript:window.close();\">";
?>