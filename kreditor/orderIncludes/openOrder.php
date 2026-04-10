<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/orderIncludes/openOrder.php --- lap 5.0.0 --- 2026.04.01 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2026 saldi.dk ApS
// ------------------------------------------------
//
// 20230111 MSC - Implementing new design
// 20231219 MSC - Copy pasted new design into code
// 20240626 PHR Added 'fiscal_year' in queries
// 20260217 PHR Added 'kundeordrnr'
// 20260312 PHR Added Afd, depNumbers, depNames, oldDep, employees & oldRef
// 20060401 PHR Minor correction so it finds correct stock when creating order

global $menu;

print "<!-- BEGIN orderIncludes/openOrder.php -->";
if ($menu=='T') {
	print "<table cellpadding='1' cellspacing='0' bordercolor='#ffffff' border='1' valign = 'top' width=100%' class='dataTableForm'><tbody>";
} else {
	print "<table cellpadding='1' cellspacing='0' bordercolor='#ffffff' border='1' valign = 'top' width=80%'><tbody>";
}
(isset($_POST['oldRef'])) ? $oldRef = $_POST['oldRef'] : $oldRef = NULL;
(isset($_POST['oldDep'])) ? $oldDep = $_POST['oldDep'] : $oldDep = NULL;
if ($ref && $oldRef && $ref != $oldRef) {
	$qtxt = "select afd from ansatte where navn = '$ref'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$afd = $r['afd'];
	}
	if ($afd) {
		$qtxt = "select box1 from grupper where art = 'AFD' and kodenr = $afd";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$lager = $r['box1'];
		}
	}
}

if (!$ref) {
	$qtxt = "select ansat_id from brugere where brugernavn = '$brugernavn'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$r = db_fetch_array(db_select("select navn, afd from ansatte where id = $r[ansat_id]",__FILE__ . " linje " . __LINE__));
		$ref = $r['navn'];
		$afd = $r['afd'];
	}
}
if (($afd && $oldDep && $afd != $oldDep) || ($afd && !$lager)) {
	$qtxt = "select box1 from grupper where art = 'AFD' and kodenr = $afd";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$lager = $r['box1'];
	}
}
$i = 0;
$employees = array();
$qtxt = "select ansatte.navn from ansatte where ansatte.slutdate > '". date('Y-m-d') ."' order by ansatte.navn";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q))	{
	$employees[$i] = $r['navn'];
	$i++;
}
$i = 0;
$depNumbers = array();
$qtxt = "select kodenr,beskrivelse from grupper where art = 'AFD' order by kodenr";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q))	{
	$depNumbers[$i] = $r['kodenr'];
	$depNames[$i]   = $r['beskrivelse'];
	$i++;
}

include ("orderIncludes/openOrderData.php");
$x=0;
$qtxt = "select * from ordrelinjer where ordre_id = '$id' order by posnr";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q))	{
	if ($r['posnr']>0) {
		$x++;
		$linje_id[$x]      = $r['id'];
		$kred_linje_id[$x] = $r['kred_linje_id'];
		$posnr[$x]         = $r['posnr'];
		$varenr[$x]        = trim($r['varenr']);
		$lev_varenr[$x]    = trim($r['lev_varenr']);
		$beskrivelse[$x]   = trim($r['beskrivelse']);
		$pris[$x]          = $r['pris'];
		$rabat[$x]         = $r['rabat'];
		$antal[$x]         = $r['antal'];
		$leveres[$x]       = $r['leveres'];
		$enhed[$x]         = $r['enhed'];
		$vare_id[$x]       = $r['vare_id'];
		$momsfri[$x]       = $r['momsfri'];
		$projekt[$x]       = $r['projekt'];
		$serienr[$x]       = $r['serienr'];
		$samlevare[$x]     = $r['samlevare'];
		($r['omvbet'])?$omvbet[$x]='checked':$omvbet[$x]='';
	}
}
$linjeantal=$x;
if (isset($_GET['vare_id']) && $_GET['vare_id'] && $linjeantal > 0) {
	$fokus = 'anta' . $linjeantal;
}
print "<input type='hidden' name='linjeantal' value='$linjeantal'>";
$sum=0;
#if ($status==1){$status=2;}

print "<!-- END orderIncludes/openOrder.php -->";

?>
	
