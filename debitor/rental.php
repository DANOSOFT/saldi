<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/rental.php --- lap 4.0.1 --- 2021-03-25 ---
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
// Copyright (c) 2020-2021 Saldi.dk ApS
// ----------------------------------------------------------------------
/*print "<!-- debitor/rental.php -->\n"*/;
// 20220926 MSC - Implementing new design

ob_start();
@session_start();
$s_id=session_id();
$title="Udlejning";
$modulnr=12;
$css="../css/standard.css";	
$hreftext=0;
$cancel=$rtPeriodCustomer=$udskriv=$valg=NULL;
$rtId = array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");

global $menu;

echo $db;

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\"><a href=../lager/varer.php accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
} else {
	print "";
}

$rtItemId = if_isset($_GET['rtItemId']);
$addItem  = if_isset($_GET['addItem']);
$newRt    = if_isset($_GET['newRt']);
$modRt    = if_isset($_GET['modRt']);
$thisRpId = if_isset($_GET['thisRpId']);
$thisRtId = if_isset($_GET['thisRtId']);
$editRpId = if_isset($_GET['editRpId']);
$page     = if_isset($_GET['page']);
$showPeriodFrom = if_isset($_GET['showPeriodFrom']);
$subItemId = if_isset($_GET['subItemId']);
$rtPeriodFrom = if_isset($_GET['rtPeriodFrom']);
$rtPeriodTo = if_isset($_GET['rtPeriodTo']);
$customerId = if_isset($_GET['customerId']);

if (!is_numeric($rtItemId) && !$subItemId) {
	$x=0;
	$qtxt = "select var_name,var_value from settings where var_grp = 'rental' order by var_value";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$rtItemId = str_replace ('rental_','',$r['var_name']);
		$x++;
	}
}
if (!is_numeric($rtItemId) && $subItemId) {
	$qtxt = "select rt_item_id from rentalitems where item_id = '$subItemId' order by id"; 
	if($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$rtItemId=$r['rt_item_id'];
	}	else include ("rentalIncludes/addItem2Rt.php");
}
if (!is_numeric($rtItemId)) $rtItemId = '0'; 

if ($editRpId) {
	$qtxt = "select * from rentalperiod where id = '$editRpId' order by id"; 
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { 
		$rtPeriodFrom = $r['rt_from'];
		$rtPeriodTo   = $r['rt_to'];
		$rtCustId        = $r['rt_cust_id'];
		$qtxt = "select kontonr, firmanavn from adresser where id = '$rtCustId'"; 
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
		$rtCustNo        = $r['kontonr'];
		
		if ($rtCustNo && $rtPeriodFrom && $rtPeriodTo) include ("rentalIncludes/rtPeriod.php");
	}
}
if ($page == 'rtSettings') include ("rentalIncludes/rtSettings.php");

$rtName =   if_isset($_POST['rtName']);
$rtPeriod = if_isset($_POST['rtPeriod']);

if (isset($_POST['rtPeriodCustomer'])) $rtPeriodCustomer = $_POST['rtPeriodCustomer'];
if (isset($_POST['newRtFrom']))        $newRtFrom        = $_POST['newRtFrom'];
if (isset($_POST['newRtTo']))          $newRtTo          = $_POST['newRtTo'];
if (isset($_POST['editRpId']))         $editRpId         = $_POST['editRpId'];
if (isset($_POST['delete']))           $delete           = $_POST['delete'];
if (isset($_POST['newRtName']))        $newRtName        = $_POST['newRtName'];
if (isset($_POST['newRtQty']))         $newRtName        = $_POST['newRtQty'];
if (isset($_POST['cancel']))           $cancel           = $_POST['cancel'];

if ($newRt)                       include ("rentalIncludes/rtItem.php");
elseif ($modRt && !$newRtName)    include ("rentalIncludes/rtItem.php");
if ($rtPeriodFrom && $rtPeriodTo) include ("rentalIncludes/rtPeriod.php");

 #cho __file__." ".__line__." $thisRpId<br>";

if ($cancel) $rtPeriodCustomer = NULL;
if ($rtPeriodCustomer && $newRtFrom && $newRtTo) {
	$rtPeriodFrom=strtotime(usdate($newRtFrom));
	$rtPeriodTo=strtotime(usdate($newRtTo));
	$qtxt="select id from adresser where kontonr='$rtPeriodCustomer' and art = 'D'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
	if ($NewRtCustId=$r['id']) {
		$rtPeriodTo+=60*60*24-1;
		if ($editRpId && !$cancel) {
			if ($delete) {
				$qtxt = "delete from rentalperiod where id = '$editRpId'";
			} else {
				$qtxt = "update rentalperiod set rt_cust_id = '$NewRtCustId', rt_from = '$rtPeriodFrom', rt_to = '$rtPeriodTo' ";
				$qtxt.= "where id = '$editRpId'";
			}
		} else {
			$qtxt = "insert into rentalperiod (rt_id,rt_cust_id,rt_from,rt_to) ";
			$qtxt.= "values ('$thisRtId','$NewRtCustId','$rtPeriodFrom','$rtPeriodTo')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	} else {
		alert("Konto $rtPeriodCustomer ikke fundet");
		include ("rentalIncludes/rtPeriod.php");
	}
	$rtPeriodFrom=$rtPeriodTo=NULL;
}

if ($newRtName && $newRtQty) {
	for ($x=1;$x<=$newRtQty;$x++) {
		if ($modRt) $qtxt="update rental set rt_name = '$newRtName' where id = '$modRt'";
		else $qtxt="insert into rental (rt_item_id,rt_name,rt_no) values ('$rtItemId','$newRtName','$x')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	if ($addItem && is_numeric($addItem)) {
		for ($x=0;$x<=$addItem;$x++) {
			$qtxt="insert into rental (rt_item_id,rt_name) values ('$rtItemId','$newRtName')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
}

echo "Cust Id $customerId<br>";
$x=0;
$qtxt = "select distinct(rt_item_id) as rt_item_id from rental order by rt_item_id";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$rtItId[$x] = $r['rt_item_id'] ."<br>";
	$x++;
}
for ($x=0;$x<=count($rtItId);$x++) {
$var_name = "rental_".$rtItId[$x];
$qtxt = "select var_value from settings where var_name = '$var_name' and var_grp = 'rental'"; 
echo "$qtxt<br>";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { 
	$itemName=$r['var_value'];
} elseif ($subItemId) {
	$qtxt = "select beskrivelse from varer where id = '$subItemId'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { 
		$itemName=$r['beskrivelse'];
		$qtxt = "insert into settings (var_name,var_grp,var_value,var_description,user_id) values ";
		$qtxt.= "('$var_name','rental','". db_escape_string($itemName) ."','Given header name for rental items','0')";  
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
} else $itemName ='Unnamed item';

if (!$showPeriodFrom) $showPeriodFrom=date('U');
$H24=60*60*24;
if ($maxTo < $showPeriodFrom*$H24*30) $maxTo = $showPeriodFrom+$H24*60;
$prePeriod=$showPeriodFrom-60*60*24*60;
$nextPeriod=$showPeriodFrom+60*60*24*60;
$y1=date('Y',$showPeriodFrom);
$y2=date('Y',$nextPeriod);
if ($y1 != $y2) $txt="$y1 / $y2";
else $txt="$y1";

if (!$newRtFrom) $newRtFrom = date ('U');
if (!$newRtTo) $newRtTo = date ('U')+60*06*24*30;
if ($customerId == '*') {
	$x=0;
	$qtxt = "select id,kontonr,firmanavn from adresser where lukket != 'on' order by firmanavn";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__); 
	while ($r=db_fetch_array($q)) {
		$rpCustId[$x]=$r['id'];
		$rpCustNo[$x] = $r['kontonr'];
		$rpCustName[$x] = $r['firmanavn'];
		$y=0;
		$qtxt = "select * from rentalperiod where rt_from	>= '$newRtFrom' and rt_to	>= '$newRtTo' and rt_cust_id = $rpCustId[$x]"; 
		$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__); 
		while ($r2=db_fetch_array($q2)) {
			$rpId[$x][$y]          = $r2['id'];
			$rpRtId[$x][$y]        = $r2['rt_id'];
			$rpFrom[$x][$y]        = $r2['rt_from'];
			$rpTo[$x][$y]          = $r2['rt_to'];
			if ($end[$x][$y] > $maxTo) $maxTo = $rpTo[$x][$y];
			$y++;
		}
		$x++;
	}
	include ("rentalIncludes/showCustomerList.php");
} else { 
	if ($customerId) {
		$qtxt = "select id, kontonr, firmanavn from adresser where id = '$customerId'"; 
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__); 
		if ($r=db_fetch_array($q)) {
			$customerNo = $r['kontonr'];
			$customerName = $r['firmanavn'];
		}
	}
	$x=0;
	$qtxt = "select * from rental where rt_item_id = '$rtItemId' order by rt_name"; 
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$rtId[$x]=$r['id']*1;
echo "$rtId[$x]<br>";		
		$rtName[$x] = $r['rt_name'];
		if (!$rtName[$x]) $rtName[$x] = $x;
		$x++;
	}
	for ($xa=0;$xa<count($rtId);$xa++) {
		$y=0;
		$qtxt = "select * from rentalperiod where rt_id = '$rtId[$xa]' order by id"; 
echo "$qtxt<br>";		
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__); 
		while ($r=db_fetch_array($q)) {
			$rpId[$x][$y]          = $r['id'];
			$rpCustId[$x][$y]			 = $r['rt_cust_id'];
			$rpFrom[$x][$y]        = $r['rt_from'];
			$rpTo[$x][$y]          = $r['rt_to'];
			if ($end[$x][$y] > $maxTo) $maxTo = $rpTo[$x][$y];
			$qtxt = "select kontonr,firmanavn from adresser where id = '". $rpCustId[$x][$y] ."'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
			$rpCustNo[$x][$y]      = $r2['kontonr'];
			($r2['firmanavn'])?$rpCustName[$x][$y] = $r2['firmanavn']:$rpCustName[$x][$y] = 'NN';
			$y++;
		}
	}
/*
	if (!$showPeriodFrom) $showPeriodFrom=date('U');
	$H24=60*60*24;
	if ($maxTo < $showPeriodFrom*$H24*30) $maxTo = $showPeriodFrom+$H24*60;
	$prePeriod=$showPeriodFrom-60*60*24*60;
	$nextPeriod=$showPeriodFrom+60*60*24*60;
	$y1=date('Y',$showPeriodFrom);
	$y2=date('Y',$nextPeriod);
	if ($y1 != $y2) $txt="$y1 / $y2";
	else $txt="$y1";
*/
	include ("rentalIncludes/showItemList.php");
}
}

?>
</body></html>

