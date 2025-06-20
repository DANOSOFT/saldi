<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/posTxtPrint/varHandle.php ---------- lap 3.7.4----2019.05.08-------
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
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// 07.08.2019 LN Check drawer status and tell to close if open

function getDrawerStatus()
{
		$printserver = getPrintserver();
		$drawerStatusLocation = 'http://' . $printserver . '/drawerstatus.php';

		$url="://".$_SERVER['SERVER_NAME']. $_SERVER['PHP_SELF'];
		$url=str_replace("/debitor/pos_ordre.php","",$url);
		if ($_SERVER['HTTPS']) $url="s".$url;
		$url="http".$url;
		$returside=$url."/debitor/pos_ordre.php";

		//print "<meta http-equiv=\"refresh\" content=\"0; URL=$drawerStatusLocation\">\n";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=http://$printserver/drawerstatus.php\">\n";
		usleep(10000);
}

function preDrawerCheck()
{
		usleep(250000);
		if(getCountry() == "Norway") {
				if (isset($_POST['betaling']) || (isset($_POST['skuffe']) && $_POST['skuffe'] == 'Skuffe')) {
				    $_SESSION['drawerstatus'] = true;
				} elseif (isset($_SESSION['drawerstatus']) && $_SESSION['drawerstatus'] == true) {
					session_destroy();
					getDrawerStatus();
				} elseif (isset($_GET['drawerstatus']) && $_GET['drawerstatus'] == 0) {
					unset($_GET['drawerstatus']);
					 alert("LUK SKUFFEN");
					 getDrawerStatus();
				}
		}
}

 function getPrintserver()
 {
	 $r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	 $printer_ip=explode(chr(9),$r['box3']);
	 $printserver=$printer_ip[0];
	 return $printserver;
 }

?>
