<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/divFuncs/drawer/drawerStatusFunc.php --- lap 4.0.8 --- 2023.10.08 ---
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
// Copyright (c) 2019-2023 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20190708 LN Check drawer status and tell to close if open
// 20201010 PHR function getDrawerStatus - added: ?returside=$returside
// 20211202 PHR function getPrintserver - Chech for cookie and now it works with 'box' too
// 20211203 PHR replaced alert("LUK SKUFFEN") with echo
// 20231002 PHR set $printserver to NULL if undefined.

function getDrawerStatus() {
	global $bgcolor;
	$bg=urlencode($bgcolor);
	$printserver = getPrintserver();
	$drawerStatusLocation = 'http://' . $printserver . '/drawerstatus.php';
	$url="://".$_SERVER['SERVER_NAME']. $_SERVER['PHP_SELF'];
	$url=str_replace("/debitor/pos_ordre.php","",$url);
	if ($_SERVER['HTTPS']) $url="s".$url;
	$url="http".$url;
	$returside=$url."/debitor/pos_ordre.php";
	print "<meta http-equiv=\"refresh\" ";
	print "content=\"0;URL=" . ($printserver == 'android' ? "saldiprint://" : "http://$printserver") . "/drawerstatus.php?bg=$bg&returside=$returside\">\n";
	exit;	#usleep(10000);
}

function preDrawerCheck() {
	if(getCountry() == "Norway") {
		usleep(250000);
		if (isset($_POST['betaling']) || (isset($_POST['skuffe']) && $_POST['skuffe'] == 'Skuffe')) {
	    $_SESSION['drawerstatus'] = true;
		} elseif (isset($_SESSION['drawerstatus']) && $_SESSION['drawerstatus'] == true) {
			session_destroy();
			getDrawerStatus();
		} elseif (isset($_GET['drawerstatus']) && $_GET['drawerstatus'] == 0) {
			unset($_GET['drawerstatus']);
#			alert("LUK SKUFFEN");
			echo "<center><br><br><br><br>LUK SKUFFEN</center>";
			getDrawerStatus();
		}
	}
}

function getPrintserver() {
	if (isset($_COOKIE['saldi_printserver'])) $printserver=$_COOKIE['printserver'];
	else $printserver = NULL;
	if (!$printserver) {
		$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
		$printer_ip=explode(chr(9),$r['box3']);
		$printserver=$printer_ip[0];
	}
	if (strtolower(substr($printserver,0,1))=='n') {
		return NULL;
		exit;
	} elseif (strtolower($printserver)=='box') {
		$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
		if ($fp=fopen($filnavn,'r')) {
			$printserver=trim(fgets($fp));
			fclose ($fp);
			if ($printserver) setcookie("saldi_printserver",$printserver,time()+60*60*24*7,'/');
		} 
	}
	if ($printserver=='box' || !$printserver) $printserver=$_COOKIE['saldi_printserver'];
	if (!$printserver) {
		alert ("Printserver ikke fundet");
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
		exit;
	}
	return $printserver;
}

?>
