<?php 
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/lager/varekort_includes/itemVat.php---------lap 3.7.9---2019-03-26	-----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------

function vatPercent($group) {
	$qtxt="select box4,box7 from grupper where art='VG' and kodenr='$group' and box7!='on'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$konto = $r['box4'];
		$qtxt="select moms from kontoplan where kontonr='$konto' order by id desc limit 1";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$momskode=str_replace("S","",$r['moms']);	
		$qtxt="select box2 from grupper where art='SM' and kodenr = '$momskode'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$incl_moms=$r['box2']*1;
		return($incl_moms);
	} 
}













?>
