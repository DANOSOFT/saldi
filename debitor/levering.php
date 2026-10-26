<?php
	@session_start();
	$s_id=session_id();

// --------------debitor/levering.php--------patch 4.0.8 ----2023-21-12-----
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20261022 CL/MJ SST-876 Names the items the webshops would not take, after the commit. A
//                 delivery that was rolled back reports nothing: the sale did not happen, so
//                 the shop figures are not what went wrong.
// ----------------------------------------------------------------------
// 2013.05.06 Rettet lidt i fejlh�ndtering.
// 2013.05.08 Tilf�jet "and status < 2" s� status ikke bliver sat tilbage ved klik p� tilbagenap p� mus.
// 2016.09.13 Tilf�jet oioubl S�g 20160913

$id=NULL;	
if (isset($_GET['id'])) $id=($_GET['id']);
echo __line__."<br>";
if ($id && $id>=1) { 
	$modulnr=5;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	include("../includes/ordrefunc.php");
	include("../includes/fuld_stykliste.php");
	$hurtigfakt=if_isset($_GET['hurtigfakt']);
	$genfakt=if_isset($_GET['genfakt']);
	$pbs=if_isset($_GET['pbs']);
	$mail_fakt=if_isset($_GET['mail_fakt']);
	$oioubl=if_isset($_GET['oioubl']); #20160913
	transaktion("begin");
	$svar=levering($id,$hurtigfakt,$genfakt,0);
	if ($svar=='OK') {
		transaktion("commit");
		# SST-876: after the commit, so a webshop that would not take the new figure cannot be
		# mistaken for a reason the delivery failed. Before the refresh below, because that
		# hands the page over and anything printed after it is never written.
		shopSyncAlertFailedItems();
		if ($hurtigfakt=='on') {
			db_modify("update ordrer set status= '2' where id='$id' and status < '2'",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?id=$id&genfakt=$genfakt&mail_fakt=$mail_fakt&pbs=$pbs&oioubl=$oioubl\">"; #20160913
			exit;
		} else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	} else {
		transaktion("rollback");
		# SST-876: the delivery did not happen, so the webshop figures are not the user's
		# problem and naming items here would only confuse what did go wrong.
		shopSyncClearFailedItems();
		print "<BODY onLoad=\"javascript:alert('$svar')\">";
	}
}

?>
</body></html>
