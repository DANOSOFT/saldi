<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------index/menu.php------ ver 4.1.1 --- 2025-08-15 ---
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
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 20180807 Corrected query to check if'kasse' is activated 20180807
// 20210223 LOE replaced string Sikkerhedskopi with findtekst value
// 20210721 LOE Fixed a bug and alsoo updated some texts not translated
// 20210817 LOE Quotation mark added to some database variables where they were missing
// 20211011 PHR Removed paperflow link as it is in 'kreditor'
// 20230320 MSC Added redirect to mobile version
// 20230714 LOE Minor modification + 20230805
// 11122023 PBLM 
// 20240108 LOE Minor modification.
// 20250414 LOE $_SESSION['UserName'] added to query barcode for app
// 20250815 LOE Empty text at 110 changed to 609 for old menu [Goods]
// 20260904 Sawaneh WP-1.3: popup window.open links now carry popup=1 so the opened
//                  window is treated as a popup by request, not by user preference.
// 20260907 CDX/LH Mark the POS launcher as a popup when opening it in a new window.
// 20260930 Sawaneh Every user passes this page right after login: open to all, or Deny mode would refuse users without Kontoplan.
// 20261006 Sawaneh WP-6.1/6.2: Tidsreg only linked when the module is installed (quote fixed); the stock button's popup
//                  returns through luk.php and the inline button is no longer printed as well.
// 20261008 Sawaneh Settings 4e: the old top-menu ($menu=='T') branches removed; the else legs stay (spec §7.6 R3).
// 20261008 Sawaneh Settings 4e: the unreachable oldmenu() (the sidebar is always 'S') and its include of a missing sidemenu.php removed.

@session_start();	# Skal angives oeverst i filen??!!
$s_id=session_id();
(isset($_COOKIE['saldi_std']))?$regnskab = $_COOKIE['saldi_std']:$regnskab = NULL;

$title="$regnskab Oversigt";
$css="../css/standard.css";
$produktion=0; # Menucolumn PRODUKTION id disabled until module is reasy for use
$ansat_id=$popup=NULL;
if (isset($_GET['online'])) $online=$_GET['online'];
else $online=0;

if(!isset($regnskab)){
	//throw error and exit, wrong call made. Could happen when trying to access menu.php before installation
	//$alerttxt="An error occured. Please contact https://saldi.dk\\n";
	header('Location: index.php'); 
	//print "<BODY onLoad=\"javascript:alert('$alerttxt')\">";
	exit;
}
$modulnr=0;
$permission_key = 'any';
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
$menu = 'S';
$qtxt = "update grupper set box3 ='$menu' where  art = 'USET' and kodenr = '$bruger_id'"; 
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
$_SESSION['UserName'] = $brugernavn;
if ($menu == 'S') {
	$_SESSION['UserName'] = $brugernavn;
	print "<script>try {parent.location.href = '../index/main.php'} catch {window.location.href = '../index/main.php'}</script>";
	die();
} else {
	print "<script>
if(window.self !== window.top) {
//run this code if in an iframe
// alert('in frame');
parent.location.href = \"../index/menu.php\";
} 
</script>";
}
?>
<script>
function checkPopupBlocked() {
    var popup = window.open('', 'test', 'width=1,height=1');
    
    if (!popup || popup.closed || typeof popup.closed == 'undefined') {
        // Popup blocked
        return true;
    } else {
        // Popup allowed - close test popup
        popup.close();
        return false;
    }
}

const res = checkPopupBlocked();
if (res) {
	// Alert the user about the popup blocker (Dansk translation)
	alert("Din browser blokerer pop-up vinduer. Saldi bruger pop-up vinduer til en del funktioner, så for at de funktioner skal virke, bliver du nødt til at tillade dem.");
} else {
	// Proceed with the report functionality
	console.log("Pop-up allowed, proceeding with report functionality.");
}
</script>

<?php
$provision=0;
if (trim($ansat_id)) {
	$ansat_id=$ansat_id*1;
	$r = db_fetch_array(db_select("select * from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__));
	$provision = $r['provision'];
}
if (file_exists("../doc/vejledning.pdf")) $vejledning="../doc/vejledning.pdf";
else $vejledning="http://saldi.dk/dok/komigang.html";

include_once '../includes/oldDesign/footer.php';
?>
<script>
	// prompt user for bank account number
/* 	function promptBankAccount() {
		var bankAccount = prompt("I overensstemmelse med bogføringsloven skal der være en konto i kontoplanen, der repræsenterer banken. Venligst angiv dit valgte kontonummer.", "")
		if (bankAccount != null) {
			alert("Din konto nummer er opdateret")
		}
	}
	promptBankAccount() */
</script>
