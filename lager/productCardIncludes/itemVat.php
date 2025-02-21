<?php 
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- /lager/varekort_includes/itemVat.php --- lap 4.0.7 --- 2023-04-25 ---
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
// Copyright (c) 2019-2023 saldi.dk aps
// ----------------------------------------------------------------------
// 20230425 PHR Extracting vatType and vatCode for use in query.

function vatPercent($group) {
	$group=(int)$group;
	$qtxt="select box4,box7 from grupper where art='VG' and kodenr='$group' and box7!='on'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$accountNo = $r['box4'];
		$qtxt="select moms from kontoplan where kontonr='$accountNo' order by id desc limit 1";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$vatType=substr($r['moms'],0,1).'M';  #20230425
		$vatCode=substr($r['moms'],1);
		if ($vatType && $vatCode) {
			$qtxt="select box2 from grupper where art='$vatType' and kodenr = '$vatCode'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$vatIncl=$r['box2']*1;
		} else $vatIncl = 0;
		return($vatIncl);
	} 
}













?>
