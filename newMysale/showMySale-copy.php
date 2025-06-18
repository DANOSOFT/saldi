<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- mysale/showMysale.php --- lap 4.0.8 --- 2025-05-03 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2021 - 2025 saldi.dk aps
// ----------------------------------------------------------------------
// 20210829 PHR varoius minor changes
// 20220212 PHR replaced cookies with session vars
// 20220930 DAPE added productlimit
// 20230311 PHR Various updates according to PHP8 
// 20230325 PHR added memberShip to query and corrected an sols and for sale buttons in mobileView. 
// 28.08.2024 PBLM added sidebar and fixed email strtolower
// 20250503 LOE reordered mix-up text_id from tekster.csv in findtekst()

if ($from) $from = usdate($from);
if ($to) $to = usdate($to);
if ($sort && $newSort && $sort == $newSort) $sort = $sort . " desc";
else $sort = $newSort;
#if (isset($_COOKIE['mysale'])) list($f,$t,$s)=explode("|",$_COOKIE['mysale']);
if (isset($_SESSION['mySale'])) list($f, $t, $s) = explode("|", $_SESSION['mySale']);
(substr($_SERVER['PHP_SELF'],0,4)=='/no/')?$sprog_id=3:$sprog_id=1;
if (!$from) $from = $f;
if (!$to)   $to   = $t;
if (!$sort) $sort = $s;
$_SESSION['mySale'] = "$from|$to|$sort";
#setcookie("mysale","$from|$to|$sort");
if (!$sort) $sort = 'id desc';

?>
<link href='../css/jquery-ui.css' rel='stylesheet' />
<script type='text/javascript' src='../javascript/jquery.min.js'></script>
<script type='text/javascript' src='../javascript/jquery-ui.min.js'></script>
<!-- date format -->
<script>
	$(document).ready(function() {
		$('input[id$=tbDatefrom]').datepicker({
			dateFormat: 'dd-mm-yy'
		});
	});
</script>
<script>
	$(document).ready(function() {
		$('input[id$=tbDateto]').datepicker({
			dateFormat: 'dd-mm-yy'
		});
	});
</script>
<?php

if ($from) $dateFrom = $from;
elseif ($db == 'pos_111') {
	$m = date('m');
	$y = date('Y');
	if (date('d') < 16) $m--;
	if ($m < 1) {
		$m = 12;
		$y--;
	}
	$dateFrom = $y."-".$m."-15";
} else {
	$dateFrom = date('Y-m');
	$dateFrom .= '-1';
}
($to) ? $dateTo = $to : $dateTo = date('Y-m-d');

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='adresser' and column_name = 'productlimit'";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
	$qtxt = "ALTER TABLE adresser ADD COLUMN productlimit numeric(15,0)";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

$qtxt = "select var_value from settings where var_name='medlemSetting' or var_name='memberShip'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$medlem = $r['var_value'];
	$tilsalg = if_isset($_GET['tilsalg']);
}
$members = array('pos_76','pos_92','pos_111','pos_115');
if (in_array($db,$members)) {
	$medlem = 1;
	$tilsalg = if_isset($_GET['tilsalg']);
}
$access = $custName = NULL;
$qtxt = "SELECT * FROM adresser WHERE ";
if ($accountId) {
	$qtxt.= "(id='" . db_escape_string($accountId) . "' AND kontonr='" . db_escape_string($account) . "') ";
} else {
	$qtxt.= "(art = 'D' and kontonr='" . db_escape_string($account) . "' )";
}

$qtxt.= "or art = 'S'";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

while ($r = db_fetch_array($q)) {
	if ($r['art'] == 'S') {
		$store = $r['firmanavn'];
	} else {
		$custName = $r['firmanavn'];
		$productLimit = if_isset($r['productlimit'],0);
		$custNo = $r['kontonr'];
		$access = $r['mysale'];
	}
}
$qtxt = "select id from adresser where kontonr='$custNo' and art = 'D'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$medlemsIDtest = $r['id'];

$qtxt = "select var_value from settings where var_grp='debitor' and var_name='mySaleLabel'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $mySaleLabel = $r['var_value'];
else $mySaleLabel = NULL;

$tilsalgOprettet = $udbetalingArray = array();

$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='mylabel' and column_name='firstprint'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table mylabel ADD column firstprint varchar(15)",__FILE__ . " linje " . __LINE__);
	db_modify("UPDATE mylabel set firstprint = lastprint",__FILE__ . " linje " . __LINE__);
}
$showNew = $showUsed = 0;
$tmp = 'kn%'.$account;
$qtxt = "select id from ordrelinjer where varenr like '$tmp' limit 1";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $showNew = 1;
$tmp = 'kb%'.$account;
$qtxt = "select id from ordrelinjer where varenr = '$tmp' limit 1";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $showUsed = 1;
/*
if ($medlem) {
	$y = 0;
	$qtxt = "SELECT created,lastprint,account_id,barcode,id,description,price,firstprint ";
	$qtxt.= "FROM mylabel WHERE account_id = '$medlemsIDtest' AND sold = '0' AND hidden = 'f' ORDER BY $sort";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tilsalgCreated[$y] = $r['created'];
		$tilsalgOprettet[$y] = $r['firstprint'];
		$tilsalgAccount[$y] = $r['account_id'];
		$tilsalgId[$y] = $r['barcode'];
		$tilsalgBeskrivelse[$y] = $r['description'];
		$tilsalgPris[$y] = $r['price'];
		$tilsalgMedlemPris[$y] = $tilsalgPris[$y] * 0.80;
		$tilsalgLastprint[$y] = $r['lastprint'];
		$y++;
	}

	$vareLimit = $productLimit - count($tilsalgOprettet);

	$y = 0;
	$udbetalingArray[$y] = array();
	$qtxt = "select id,transdate,beskrivelse,amount from openpost where konto_id = '$medlemsIDtest' and amount <= '0' and transdate>='$dateFrom' and transdate<='$dateTo' and beskrivelse like '%afr:%' order by transdate desc";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$udbetalingArray[$y][0] = $r['transdate'];
		$udbetalingArray[$y][1] = $r['id'];
		$udbetalingArray[$y][2] = $r['beskrivelse'];
		$udbetalingArray[$y][3] = "";
		$udbetalingArray[$y][4] = $r['amount'];
		if (in_array($db,$members)) $udbetalingArray[$y][4] = 0;
		$y++;
	}
}  
*/
$urlPrefix = "https://ssl8.saldi.dk/laja/mysale/mysale.php?id=";
if (strpos($id, $urlPrefix) === 0) {
    // Remove the URL prefix
    $newId = str_replace($urlPrefix, '', $id);
}else{
	$newId = $id;
}
?>
<button data-drawer-target="default-sidebar" data-drawer-toggle="default-sidebar" aria-controls="default-sidebar" type="button" class="inline-flex items-center p-2 mt-2 ms-3 text-sm text-gray-500 rounded-lg lg:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600">
   <span class="sr-only">Åben sidebar</span>
   <svg class="w-20 h-20" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
   <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
   </svg>
</button>

<aside id="default-sidebar" class="fixed top-0 left-0 z-40 w-1/2 lg:w-64 h-screen transition-transform -translate-x-full lg:translate-x-0" aria-label="Sidebar">
   <div class="h-full px-3 py-4 overflow-y-auto bg-gray-50 dark:bg-gray-800">
      <ul class="space-y-2 font-medium">
         <li>
            <a href="mysale.php?id=<?php echo $newId ?>" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
				<svg class="w-16 h-16 lg:w-6 lg:h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
					<path d="M13.5 2c-.178 0-.356.013-.492.022l-.074.005a1 1 0 0 0-.934.998V11a1 1 0 0 0 1 1h7.975a1 1 0 0 0 .998-.934l.005-.074A7.04 7.04 0 0 0 22 10.5 8.5 8.5 0 0 0 13.5 2Z"/>
					<path d="M11 6.025a1 1 0 0 0-1.065-.998 8.5 8.5 0 1 0 9.038 9.039A1 1 0 0 0 17.975 13H11V6.025Z"/>
				</svg>
               <span class="ms-3 font-sans text-5xl lg:text-base">Oversigt</span>
            </a>
         </li>
         <li>
            <a href="mysale.php?id=<?php echo $newId ?>&condition=<?php echo $condition ?>&editProfile=1" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
				<svg class="w-16 h-16 lg:w-6 lg:h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
					<path fill-rule="evenodd" d="M17 10v1.126c.367.095.714.24 1.032.428l.796-.797 1.415 1.415-.797.796c.188.318.333.665.428 1.032H21v2h-1.126c-.095.367-.24.714-.428 1.032l.797.796-1.415 1.415-.796-.797a3.979 3.979 0 0 1-1.032.428V20h-2v-1.126a3.977 3.977 0 0 1-1.032-.428l-.796.797-1.415-1.415.797-.796A3.975 3.975 0 0 1 12.126 16H11v-2h1.126c.095-.367.24-.714.428-1.032l-.797-.796 1.415-1.415.796.797A3.977 3.977 0 0 1 15 11.126V10h2Zm.406 3.578.016.016c.354.358.574.85.578 1.392v.028a2 2 0 0 1-3.409 1.406l-.01-.012a2 2 0 0 1 2.826-2.83ZM5 8a4 4 0 1 1 7.938.703 7.029 7.029 0 0 0-3.235 3.235A4 4 0 0 1 5 8Zm4.29 5H7a4 4 0 0 0-4 4v1a2 2 0 0 0 2 2h6.101A6.979 6.979 0 0 1 9 15c0-.695.101-1.366.29-2Z" clip-rule="evenodd"/>
				</svg>
               <span class="flex-1 ms-3 whitespace-nowrap font-sans text-5xl lg:text-base">Profil</span>
            </a>
         </li>
         <li>
            <a href=mylabel.php?id=<?php echo $newId?>&condition=<?php echo $condition?> class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
				<svg class="w-16 h-16 lg:w-6 lg:h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
					<path d="M4 6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h11.613a2 2 0 0 0 1.346-.52l4.4-4a2 2 0 0 0 0-2.96l-4.4-4A2 2 0 0 0 15.613 6H4Z"/>
				</svg>
				<span class="flex-1 ms-3 whitespace-nowrap font-sans text-5xl lg:text-base"><?php echo findtekst(2364,$sprog_id) ?></span>
            </a>
         </li>
		 <li>
            <a href="remoteBooking.php?id=<?php echo $id?>&condition=<?php echo $condition?>" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
				<svg class="w-16 h-16 lg:w-6 lg:h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
					<path fill-rule="evenodd" d="M6 5V4a1 1 0 1 1 2 0v1h3V4a1 1 0 1 1 2 0v1h3V4a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v2H3V7a2 2 0 0 1 2-2h1ZM3 19v-8h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Zm5-6a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2H8Z" clip-rule="evenodd"/>
				</svg>
               <span class="flex-1 ms-3 whitespace-nowrap font-sans text-5xl lg:text-base">Book stand</span>
            </a>
         </li>
		 <li>
			<a href="mybooking.php?id=<?php echo $newId?>" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
				<svg class="w-16 h-16 lg:w-6 lg:h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
					<path fill-rule="evenodd" d="M6 5V4a1 1 0 1 1 2 0v1h3V4a1 1 0 1 1 2 0v1h3V4a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v2H3V7a2 2 0 0 1 2-2h1ZM3 19v-8h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Zm5-6a1 1 0 1 0 0 2h8a1 1 0 1 0 0-2H8Z" clip-rule="evenodd"/>
				</svg>
				<span class="flex-1 ms-3 whitespace-nowrap font-sans text-5xl lg:text-base">Mine bookinger</span>
			</a>
		</li>
         <li>
            <a href="mysale.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
				<svg class="w-16 h-16 lg:w-6 lg:h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
					<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12H4m12 0-4 4m4-4-4-4m3-4h2a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3h-2"/>
				</svg>
               <span class="flex-1 ms-3 whitespace-nowrap font-sans text-5xl lg:text-base">Logud</span>
            </a>
         </li>
      </ul>
   </div>
</aside>
<?php

if ($custName && $access) {
	$myLabel = 0;
	($mobile) ? $wh = 50 : $wh = 25;
	if ($link) $id = $link;
	/* print "<div style='float:right;'><a href = mysale.php?id=$id&condition=$condition&editProfile=1>";
	print "<img class='checkMobile' src=\"../img/profile.png\" style=\"border: 0px solid;width:$wh;height:$wh\"></a></div>"; */
	/* print "<center>";
	if ($mobile) print "Velkommen " . $custName . ", kundenr $custNo<br>";
	else print "<br>Velkommen " . $custName . ", kundenr $custNo<br><br>" . $store . ".<br>"; */
	?>
	<div class="flex flex-col items-center justify-center mt-4">
		<h3 class="text-3xl font-bold text-center">Velkommen <?php echo $custName ?>, kundenr <?php echo $custNo ?></h3>
		<p class="text-center">Her kan du se dine salg.</p>
	</div>
	<?php
	$qtxt = "select box1 from grupper where art='LABEL'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (strpos($r['box1'], '$minpris')) $myLabel = 1;
	else {
		$qtxt = "select labeltext from labels where account_id='0'";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (strpos($r['labeltext'], '$minpris')) $myLabel = 1;
		}
	}

	if ($myLabel) {
		($mobile) ? $style = "width:450px;font-size:25pt;'" : $style = "width:'200px'";
		if ($medlem) {
			if ($vareLimit <= 0) {

				$vareLimit = 0;
			}
#			print "<br >Du kan tilføje <b>$vareLimit</b> varer.</br><br><br>";
		}
		if ($medlem) {
			if (!isset($_SESSION['linkLog']) || !$mySaleLabel) {
				print "<a href=mylabel.php?id=$id&condition=$condition><button style=$style>". findtekst(2364,$sprog_id)."</button></a><br><br>";
			}
			($mobile) ? $style = "text-align:center;width:275px;font-size:25pt;margin:3px;" : 
									$style = "text-align:center;width:80px;margin:3px;";
			if ($tilsalg) {
				print "<a href=mysale.php?id=$id&tilsalg=0><button style='$style'>Solgt</button></a>";
				print "<a href=mysale.php?id=$id&tilsalg=1><button style='$style'><b>Til salg</b></button></a><br><br>";
			} else {
				print "<a href=mysale.php?id=$id&tilsalg=0><button style='$style'><b>Solgt</b></button></a>";
				print "<a href=mysale.php?id=$id&tilsalg=1><button style='$style'>Til salg</button></a><br><br>";
			}
		} else {
			print "<a href=mylabel.php?id=$id&condition=$condition><button style=$style>". findtekst(2364,$sprog_id)."</button></a><br><br>";
		}
	}
} else {
	/* print "<center><br><br>Velkommen " . $custName . ". Du har ikke adgang til 'Mit salg' hos " . $store . ".<br><br>";
	print "Kontakt " . $store . " for åbning af adgang.<br></style>"; */
	?>
	<div class="flex flex-col items-center justify-center mt-4">
		<h3 class="text-3xl font-bold text-center">Velkommen <?php echo $custName ?></h3>
		<p class="text-center">Du har ikke adgang til 'Mit salg' hos <?php echo $store ?>.</p>
		<p class="text-center">Kontakt <?php echo $store ?> for åbning af adgang.</p>
	</div>
	<?php
	exit;
}
$x = 0;
$fakturadate = $solgtArray = array();
$qtxt = "select batch_salg.fakturadate,batch_salg.id,batch_salg.ordre_id,batch_salg.antal,batch_salg.pris,ordrelinjer.kostpris,";
$qtxt .= "ordrelinjer.beskrivelse,ordrelinjer.barcode,varer.kostpris as provision from varer,batch_salg,ordrelinjer ";
$qtxt .= "where batch_salg.antal!=0 and varer.varenr like ";
($condition == 'new') ? $qtxt .= "'kn%$account' " : $qtxt .= "'kb%$account' ";
$qtxt .= "and batch_salg.vare_id=varer.id and batch_salg.fakturadate>='$dateFrom' and batch_salg.fakturadate<='$dateTo' ";
$qtxt .= "and ordrelinjer.id=batch_salg.linje_id order by batch_salg.$sort";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$fakturadate[$x] = $r['fakturadate'];
	$antal[$x] = $r['antal'];
	if($medlem && !strpos($r['beskrivelse'], '(brugt)')){
		$vareid[$x] = $r['barcode'];
	}
	else {
		$vareid[$x] = $r['id'];
	}
	$pris[$x] = $r['pris'];
	$beskrivelse[$x] = $r['beskrivelse'];
	$kostpris[$x] = $r['kostpris'] * 1;
	if (!$kostpris[$x]) $kostpris[$x] = $pris[$x] * 0.85;
	($pris[$x]) ? $provision[$x] = $kostpris[$x] * 100 / $pris[$x] : $provision[$x] = 0;
	if ($provision[$x] < 50) {
		$provision[$x] = 100 - $provision[$x];
		$kostpris[$x] = $pris[$x] * $provision[$x] / 100;
	}
	$qty += $antal[$x];
	$linePrice[$x] = $antal[$x] * $pris[$x];
	$totalPrice += $linePrice[$x];
	$yourPrice[$x] = $antal[$x] * $kostpris[$x];
	$yourTotalPrice += $yourPrice[$x];
	$solgtArray[$x][0] = $fakturadate[$x];
	$solgtArray[$x][1] = $vareid[$x];
	$solgtArray[$x][2] = $beskrivelse[$x];
	$solgtArray[$x][3] = $pris[$x];
	$solgtArray[$x][4] = $yourPrice[$x];
	$x++;
}
($totalPrice) ? $avgPct = $yourTotalPrice * 100 / $totalPrice : $avgPct = 100;
$from = date_format(date_create($dateFrom), "Y-m-d");
$to = date_format(date_create($dateTo), "Y-m-d");

if ($medlem) {
	for ($x = 0; $x < count($udbetalingArray); $x++) {
		$yourTotalPrice += if_isset($udbetalingArray[$x][4],0);
	}
}

if ($udbetalingArray) $testArray = array_merge($solgtArray, $udbetalingArray);
else $testArray = $solgtArray;

function date_compare($time1, $time2)
{
	global $sort;

	$datetime1 = strtotime($time1[0]);
	$datetime2 = strtotime($time2[0]);

	if (strpos($sort, 'desc')){
		return $datetime1 - $datetime2;
	}
	else{
		return $datetime2 - $datetime1;
	}

}

usort($testArray, 'date_compare');



#$x=0;
#$fakturadate=array();
#$qtxt = "select created,id,description,price,lastprint";
#$qtxt.= "where account_id = '$medlemsIDtest' ";
#$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
#while ($r=db_fetch_array($q)) {
#	$fakturadate[$x]=$r['fakturadate'];
#	$antal[$x]=$r['antal'];
#   $vareid[$x]=$r['id'];
#	$pris[$x]=$r['pris'];
#}





if ($tilsalg == 1) {
	print "<center>";
	print "<table border='0'>";
	print "<form action='mysale.php?id=$id&sort=$sort' method='post'>";
	print "<tr>";
	if ($medlem) {
	} else {
		if ($showNew && $showUsed) {
			print "<td><select name ='condition'>";
			if ($condition == 'new') print "<option value='new'>nyt</option>";
			print "<option value='used'>brugt</option>";
			if ($condition != 'new') print "<option value='new'>nyt</option>";
			print "</select></td>";
		} elseif ($showNew) print "<td><b>".findtekst(2363,$sprog_id)."</b></td>";
		else print "<td><b>".findtekst(2361,$sprog_id)."</b></td>";
	}
	
print "<td style='text-align:center'>Tilføj vare</td>";
if ($mobile) {
	print "<td style='width:225px'>";
 	print "<input class='inputbox' style='text-align:center; width:225px;' type='text' id='tbDatefrom' name='dateFrom' ";
	print "value='". dkdato($from) ."' onchange='this.form.submit()'></td>";
 	print "<td style='text-align:center;width:15px'>-</td>";
	print "<td style='width:225px'>";
	print "<input  class='inputbox' style='text-align:center;  width:225px;' type='text' id='tbDateto' name='dateTo' ";
	print "value='". dkdato($to) ."' onchange='this.form.submit()'></td>";
} else {
	print "<td width='80px'>";
	print "<input class='inputbox' style='text-align:left; width:150px;' type='text' id='beskrivelse' name='beskriv' ";
	print "placeholder='Beskrivelse' '></td>";
	print "<td width='40px'>";
	print "<input  class='inputbox' style='text-align:left;  width:100px;' type='text' id='normalPris' name='normalPris' ";
	print "placeholder='Normal pris' '></td>";
}

print "<td style='width:50px'><input style='text-align:center; width:50px;' type='button' name='update' value='Gem'></td>";
print "<td ><input style='text-align:center; width:90px;' type='button' name='update' value='Gem & print'></td>";

	print "</tr>";
	print "</form>";
	print "</table><table border='0' width='90%'>";
	print "<tr bgcolor='$lineColor'>";
	print "<td><a href='mysale.php?id=$id&sort=$sort&tilsalg=1&newSort=id'>Oprettet</a></td>";
	print "<td align='left'>Id</td><td style='width:50px'>Beskrivelse</td>";
	if ($mobile) print "<td align='right'>Salgspris</td>";
	else print "<td align='right'>Salgspris</td>";#<td align='right'>Medlemspris</td>";
	#print "<td align='right'>Printet</td><td align='right'>Print</td>";
	print "</tr>";
	if ($medlem) {
		for ($x = 0; $x < count($tilsalgOprettet); $x++) {
			($tilsalgOprettet[$x] > '1')?$showDate = $tilsalgOprettet[$x]:$showDate = $tilsalgCreated[$x];
#if ($bruger_id == '-1') echo $tilsalgOprettet[$x]."<br>";
				if ($tilsalgPris[$x] != 0) {
				($lineColor == $bgcolor) ? $lineColor = $bgcolor5 : $lineColor = $bgcolor;
				print "<tr bgcolor='$lineColor'>";
				print "<td>" . date("d.m.Y", $showDate) . "</td>";
				print "<td align='left'>$tilsalgId[$x]</td>";
				print "<td title='$tilsalgBeskrivelse[$x]' style='white-space:nowrap;overflow:hidden;'>";
				($mobile) ? print substr($tilsalgBeskrivelse[$x], 0, 15) : print $tilsalgBeskrivelse[$x];
				print "</td>";
				print "<td align='right'>" . number_format($tilsalgPris[$x], 2, ',', '.') . "</td>";
	#			print "<td align='right'>" . number_format($tilsalgMedlemPris[$x], 2, ',', '.') . "</td>";
			/*	if ($tilsalgOprettet[$x]) {
				print "&nbsp;<td align='center'><img src=\"../ikoner/checkmrk.png\" style=\"border: 0px solid; </td>\">";
				} else {
					print "&nbsp;<td align='center'><img src=\"../ikoner/slet.png\" style=\"border: 0px solid; </td>\">";
				}
				print "<td align='center'><input name=\"printItem\" type=\"checkbox\" $printed></td>"; */
				print "</tr>";
			}}
	}
} else {
	print "<center>";
	print "<table border='0'>";
	print "<form action='mysale.php?id=$id&sort=$sort' method='post'>";
	print "<tr>";
	if (!$medlem) {
		if ($showNew && $showUsed) {
			print "<td><select name ='condition'>";
			if ($condition == 'new') print "<option value='new'>nyt</option>";
			print "<option value='used'>brugt</option>";
			if ($condition != 'new') print "<option value='new'>nyt</option>";
			print "</select></td>";
		} elseif ($showNew) print "<td><b>".findtekst(2363,$sprog_id)."</b></td>";
		else print "<td><b>".findtekst(2361,$sprog_id)."</b></td>";
#		print "<td><select name ='condition'>";
#		if ($condition == 'new') print "<option value='new'>nyt</option>";
#		print "<option value='used'>brugt</option>";
#		if ($condition != 'new') print "<option value='new'>nyt</option>";
	}


	print "</select></td>";
	print "<td>Periode</td>";
	if ($mobile) {
		print "<td style='width:225px'>";
		print "<input class='inputbox' style='text-align:center; width:225px;' type='text' id='tbDatefrom' name='dateFrom' ";
		print "value='" . dkdato($from) . "' onchange='this.form.submit()'></td>";
		print "<td style='text-align:center;width:15px'>-</td>";
		print "<td style='width:225px'>";
		print "<input  class='inputbox' style='text-align:center;  width:225px;' type='text' id='tbDateto' name='dateTo' ";
		print "value='" . dkdato($to) . "' onchange='this.form.submit()'></td>";
	} else {
		print "<td width='80px'>";
		print "<input class='inputbox' style='text-align:center; width:131px;' type='text' id='tbDatefrom' name='dateFrom' ";
		print "value='" . dkdato($from) . "' onchange='this.form.submit()'></td>";
		print "<td align='center'> -</td>";
		print "<td width='80px'>";
		print "<input  class='inputbox' style='text-align:center;  width:131px;' type='text' id='tbDateto' name='dateTo' ";
		print "value='" . dkdato($to) . "' onchange='this.form.submit()'></td>";
	}
	print "<td align='right'><input class='submit' style='text-align:center;' type='submit' name='update' value='Ok'></td>";
	print "</tr>";
	print "</form>";
	print "</table><table border='0' width='90%'>";
	print "<tr bgcolor='$lineColor'>";
	print "<td><a href='mysale.php?id=$id&sort=$sort&newSort=id'>Dato</a></td>";
	if ($medlem) {
		print "<td align='left'>Id</td><td style='width:50px'>Beskrivelse</td>";
	} else {
		print "<td align='right'>". findtekst(916,$sprog_id) ."</td><td style='width:50px'>Beskrivelse</td>";
		print "<td align='right'><a href='mysale.php?id=$id&sort=$sort&newSort=pris'>Pris</a></td><td align='right'>I alt</td>";
	}
	if ($medlem) {
		if ($mobile) print "<td align='right'>Salgspris</td>";
		else print "<td align='right'>Salgspris</td><td align='right'>Din andel</td>";
	} else {
		if ($mobile) print "<td align='right'>Din del</td>";
		else print "<td align='right'>Din del</td><td align='right'>Din %</td>";
	}
	print "</tr>";

	if ($medlem) {
		for ($x = 0; $x < count($testArray); $x++) {
			if (isset($testArray[$x][2]) && substr($testArray[$x][2],0,3) != 'Afr')	 {
				($lineColor == $bgcolor) ? $lineColor = $bgcolor5 : $lineColor = $bgcolor;
				print "<tr bgcolor='$lineColor'>";
				$date = date_create("" . if_isset($testArray[$x][0],date('Y-m-d')) . "");
				print "<td>" . date_format($date, 'd.m.Y') . "</td>";
				print "<td align='left'>" . $testArray[$x][1] . "</td>";
				print "<td title='" . if_isset($testArray[$x][2],NULL) . "' style='white-space:nowrap;overflow:hidden;'>";
				($mobile) ? print substr($testArray[$x][2], 0, 15) : print $testArray[$x][2];
				print "</td>";
				print "<td align='right'>" . number_format(if_isset($testArray[$x][3],0), 2, ',', '.') . "</td>";
				print "<td align='right'>" . number_format($testArray[$x][4], 2, ',', '.') . "</td>";
				print "</tr>";
			}
		}
	} else {
		for ($x = 0; $x < count($fakturadate); $x++) {
			($lineColor == $bgcolor) ? $lineColor = $bgcolor5 : $lineColor = $bgcolor;
			print "<tr bgcolor='$lineColor'>";
			$date = date_create("$fakturadate[$x]");
			print "<td>" . date_format($date, 'd.m.Y') . "</td>";
			print "<td align='right'>" . number_format($antal[$x], 0, ',', '.') . "</td>";
			print "<td title='$beskrivelse[$x]' style='white-space:nowrap;overflow:hidden;'>";
			($mobile) ? print substr($beskrivelse[$x], 0, 15) : print $beskrivelse[$x];
			print "</td>";
			print "<td align='right'>" . number_format($pris[$x], 2, ',', '.') . "</td>";
			print "<td align='right'>" . number_format($linePrice[$x], 2, ',', '.') . "</td>";
			print "<td align='right'>" . number_format($yourPrice[$x], 2, ',', '.') . "</td>";
			if (!$mobile) print "<td align='right'>" . number_format($provision[$x], 0, ',', '.') . "</td>";
			print "</tr>";
		}
	}
	if ($medlem) {
		($lineColor == $bgcolor) ? $lineColor = $bgcolor5 : $lineColor = $bgcolor;
		print "<tr bgcolor='$lineColor'>";
		print "<td><b>tilgodehavende</b></td>";
		print "<td align='right'></td>";
		print "<td></td>";
		print "<td></td>";
		print "<td align='right'><b>" . number_format($yourTotalPrice, 2, ',', '.') . "</b></td>";
		print "</tr>";
	} else {
		($lineColor == $bgcolor) ? $lineColor = $bgcolor5 : $lineColor = $bgcolor;
		print "<tr bgcolor='$lineColor'>";
		print "<td><b>I alt</b></td>";
		print "<td align='right'><b>" . number_format($qty, 0, ',', '.') . "</b></td>";
		print "<td align='right'></td>";
		print "<td></td>";
		print "<td align='right'><b>" . number_format($totalPrice, 2, ',', '.') . "</b></td>";
		print "<td align='right'><b>" . number_format($yourTotalPrice, 2, ',', '.') . "</b></td>";
		if (!$mobile) print "<td align='right'><b>" . number_format($avgPct, 0, ',', '.') . "</b></td>";
		print "</tr>";
	}
	print "</table>";
}
?>
