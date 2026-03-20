<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- mysale/showMysale.php --- lap 4.0.8 --- 2023-03-25 ---
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
// Copyright (c) 2021 - 2023 saldi.dk aps
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
<body class="dark:bg-gray-700">
<?php
include_once("sidemenu.php");
?>
<div class="p-4 lg:ml-64 h-screen">
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
		<h3 class="text-5xl lg:text-3xl font-bold text-center dark:text-white">Velkommen <?php echo $custName ?>, kundenr <?php echo $custNo ?></h3>
		<p class="text-center text-5xl lg:text-2xl dark:text-white">Her kan du se dine salg.</p>
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
		/* if ($medlem) {
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
		} */
	}
} else {
	/* print "<center><br><br>Velkommen " . $custName . ". Du har ikke adgang til 'Mit salg' hos " . $store . ".<br><br>";
	print "Kontakt " . $store . " for åbning af adgang.<br></style>"; */
	?>
	<div class="flex flex-col items-center justify-center mt-4">
		<h3 class="text-3xl font-bold text-center dark:text-white">Velkommen <?php echo $custName ?></h3>
		<p class="text-center dark:text-white">Du har ikke adgang til 'Mit salg' hos <?php echo $store ?>.</p>
		<p class="text-center dark:text-white">Kontakt <?php echo $store ?> for åbning af adgang.</p>
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
	?>
	<div class="flex flex-col items-center justify-center mt-4">
		<form action='mysale.php?id=<?php echo $id ?>&sort=<?php echo $sort ?>' method='post'>
			<?php if (!$medlem) {
				if ($showNew && $showUsed) { ?>
					<select name='condition' class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
						<?php if ($condition == 'new') { ?>
							<option value='new'>nyt</option>
						<?php } ?>
						<option value='used'>brugt</option>
						<?php if ($condition != 'new') { ?>
							<option value='new'>nyt</option>
						<?php } ?>
					</select>
				<?php } elseif ($showNew) { ?>
					<div class="text-xl font-bold dark:text-white"><?php echo findtekst(2363,$sprog_id) ?></div>
				<?php } else { ?>
					<div class="text-xl font-bold dark:text-white"><?php echo findtekst(2361,$sprog_id) ?></div>
				<?php }
			} ?>
		</form>

		<div class="relative w-full lg:w-2/3 max-w-full">
			<div class="overflow-hidden">
				<table class="w-full text-4xl lg:text-sm text-left rtl:text-right text-gray-900 dark:text-gray-400 mb-4">
					<thead class="text-3xl lg:text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-500 dark:text-gray-300">
						<tr>
							<th scope="col" class="px-6 py-3">
								<a href='mysale.php?id=<?php echo $id ?>&sort=<?php echo $sort ?>&tilsalg=1&newSort=id' class="dark:text-white">Oprettet</a>
							</th>
							<th scope="col" class="px-6 py-3">Id</th>
							<th scope="col" class="px-6 py-3">Beskrivelse</th>
							<th scope="col" class="px-6 py-3">Salgspris</th>
						</tr>
					</thead>
					<tbody>
						<?php if ($medlem) {
							for ($x = 0; $x < count($tilsalgOprettet); $x++) {
								$showDate = ($tilsalgOprettet[$x] > '1') ? $tilsalgOprettet[$x] : $tilsalgCreated[$x];
								if ($tilsalgPris[$x] != 0) { ?>
									<tr class="bg-gray-50 dark:bg-gray-700">
										<td class="px-6 py-4 whitespace-nowrap"><?php echo date("d.m.Y", $showDate) ?></td>
										<td class="px-6 py-4 whitespace-nowrap"><?php echo $tilsalgId[$x] ?></td>
										<td class="px-6 py-4 whitespace-nowrap" title="<?php echo $tilsalgBeskrivelse[$x] ?>">
											<?php echo $mobile ? substr($tilsalgBeskrivelse[$x], 0, 15) : $tilsalgBeskrivelse[$x] ?>
										</td>
										<td class="px-6 py-4 whitespace-nowrap text-right">
											<?php echo number_format($tilsalgPris[$x], 2, ',', '.') ?>
										</td>
									</tr>
						<?php }
							}
						} ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<?php
} else {
	/* print "<center>";
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
	print "</tr>"; */
	?>
	<div class="flex flex-col items-center justify-center mt-4">
	<form action='mysale.php?id=<?php echo $id ?>&sort=<?php echo $sort ?>' method='post'>
		<?php if (!$medlem) {
			if ($showNew && $showUsed) {
		?>
			<!-- <select name ='condition' class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
				<option value='new'>nyt</option>
				<option value='used'>brugt</option>
				<option value='new'>nyt</option>
			</select> -->
			<?php } /* elseif ($showNew) print findtekst(2363,$sprog_id);
			else print findtekst(2361,$sprog_id); */ ?>
			<!-- <select name ='condition' class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
				<option value='new'>nyt</option>
				<option value='used'>brugt</option>
				<?php /* if ($condition != 'new'){ */ ?>
					<option value='new'>nyt</option>
				<?php /* } */ ?>
			</select> -->
			<?php }	?>
			<!-- <h3 class="mt-4 mb-4 text-xl font-bold">Periode</h3> -->
			<div class="grid lg:grid-cols-7 lg:gap-6 grid-cols-6 gap-3">
				<div class="relative z-0 w-full mb-5 group col-span-3">
					<label for="tbDatefrom" class="block mb-4 text-5xl lg:text-sm font-medium text-gray-900 dark:text-white text-center">Fra</label>
					<input id="tbDatefrom" 
						type="text"
						class="bg-gray-50 border border-gray-300 text-gray-900 text-4xl lg:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-6 lg:p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 min-h-[80px] md:min-h-0" 
						name="dateFrom"
						value="<?php echo dkdato($from) ?>"
						onchange="this.form.submit()">
				</div>
				<div class="relative z-0 w-full mb-5 group col-span-3">
					<label for="tbDateto" class="block mb-4 text-5xl lg:text-sm font-medium text-gray-900 dark:text-white text-center">Til</label>
					<input id="tbDateto"
						type="text" 
						class="bg-gray-50 border border-gray-300 text-gray-900 text-4xl lg:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-6 lg:p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 min-h-[80px] md:min-h-0"
						name="dateTo"
						value="<?php echo dkdato($to) ?>"
						onchange="this.form.submit()">
				</div>
			</div>
		</form>
		<div class="flex flex-col items-center mb-3">
			<div class="text-center">
				<label for="condition" class="block mb-4 text-5xl lg:text-xl font-medium text-gray-900 dark:text-white">Vælg mellem at se nyt / brugt</label>
			</div>
			<div class="text-center">
				<select name="condition" id="condition" onchange="javascript:this.form.submit()" class="bg-gray-50 border border-gray-300 text-gray-900 text-4xl lg:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-96 p-3 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
					<option value="used" <?php echo ($condition == 'used') ? 'selected' : ''; ?>>Brugt</option>
					<option value="new" <?php echo ($condition == 'new') ? 'selected' : ''; ?>>Nyt</option>
				</select>
			</div>
		</div>
		<script>
			const condition = document.getElementById('condition')
			condition.addEventListener('change', function() {
				const url = `mysale.php?id=<?php echo $newId ?>&condition=${condition.value}`;
				window.location.href = url;
			})
		</script>
		<?php 
			$qtxt = "select var_value from settings where var_name='medlemSetting' or var_name='memberShip'";
			if($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))){
		?>
		<div class="flex space-x-4 mb-4 justify-center">
			<a href="mysale.php?id=<?php echo $newId ?>&sort=<?php echo $sort ?>&tilsalg=1" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Til salg</a>
			<a href="mysale.php?id=<?php echo $newId ?>&sort=<?php echo $sort ?>&tilsalg=0" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Solgt</a>
		</div>
		<?php } ?>
		<h3 class="text-5xl lg:text-3xl font-bold text-center lg:mb-2 mb-4 dark:text-white">Salgsoversigt</h3>
		<div class="relative w-full lg:w-2/3 max-w-full">
			<div class="overflow-hidden">
				<table class="w-full text-4xl lg:text-sm text-left rtl:text-right text-gray-900 dark:text-gray-400 mb-4">
				<thead class="text-3xl lg:text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-500 dark:text-gray-300">
					<tr>
						<th scope="col" class="px-6 py-3"><a href='mysale.php?id=<?php echo $id ?>&sort=<?php echo $sort ?>&newSort=id'>Dato</a></th>
						<?php if ($medlem) { ?>
							<th scope="col" class="px-6 py-3">Id</th>
							<th scope="col" class="px-6 py-3">Beskrivelse</th>
						<?php } else { ?>
							<?php if($mobile) { ?>
								<th scope="col" class="px-6 py-3">#</th>
							<?php }else{ ?>
							<th scope="col" class="px-6 py-3"><?php echo findtekst(916,$sprog_id) ?></th>
							<?php } ?>
							<th scope="col" class="px-6 py-3">Beskrivelse</th>
							<th scope="col" class="px-6 py-3"><a href='mysale.php?id=$id&sort=$sort&newSort=pris'>Pris</a></th>
							<?php if(!$mobile) { ?>
							<th scope="col" class="px-6 py-3">I alt</th>
							<?php } ?>
						<?php } ?>
						<?php if ($medlem) { ?>
							<th scope="col" class="px-6 py-3">Salgspris</th>
							<th scope="col" class="px-6 py-3">Din andel</th>
						<?php } else { ?>
							<th scope="col" class="px-6 py-3">Din del</th>
							<?php if(!$mobile) { ?>
								<th scope="col" class="px-6 py-3">Din %</th>
							<?php } ?>
						<?php } ?>
					</tr>
				</thead>
				<tbody>
					<?php if($medlem){
						for($x = 0; $x < count($testArray); $x++){
					?>
						<tr class="bg-gray-50 dark:bg-gray-700">
							<td class="px-6 py-4 whitespace-nowrap"><?php echo dkdato($testArray[$x][0]) ?></td>	
							<td class="px-6 py-4 whitespace-nowrap"><?php echo $testArray[$x][1] ?></td>
							<td class="px-6 py-4 whitespace-nowrap"><?php echo $testArray[$x][2] ?></td>
							<td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($testArray[$x][3], 2, ',', '.') ?></td>
							<?php if(!$mobile) { ?>
							<td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($testArray[$x][4], 2, ',', '.') ?></td>
							<?php } ?>
						</tr>
						<?php }
							} else {
								for($x = 0; $x < count($fakturadate); $x++){
						?>
						<tr class="bg-gray-50 dark:bg-gray-700">
							<td class="px-6 py-4 whitespace-nowrap"><?php echo dkdato($fakturadate[$x]) ?></td>
							<td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($antal[$x], 0, ',', '.') ?></td>
							<?php if($mobile) { ?>
							<td class="px-6 py-4 whitespace-nowrap" title="<?php echo $beskrivelse[$x] ?>"><?php echo substr($beskrivelse[$x], 0, 10) . (strlen($beskrivelse[$x]) > 10 ? '...' : '') ?></td>
							<?php }else{ ?>
								<td class="px-6 py-4 whitespace-nowrap" title="<?php echo $beskrivelse[$x] ?>"><?php echo substr($beskrivelse[$x], 0, 30) . (strlen($beskrivelse[$x]) > 30 ? '...' : '') ?></td>
							<?php } ?>
							<td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($pris[$x], 2, ',', '.') ?></td>
							<?php if(!$mobile) { ?>
							<td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($linePrice[$x], 2, ',', '.') ?></td>
							<?php } ?>
							<td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($yourPrice[$x], 2, ',', '.') ?></td>
							<?php if(!$mobile) { ?>
							<td class="px-6 py-4 whitespace-nowrap"><?php echo number_format($provision[$x], 0, ',', '.') ?></td>
							<?php } ?>
						</tr>
						<?php }
					} ?>
					<?php if($medlem){ ?>
						<tr class="bg-gray-50 dark:bg-gray-700">
							<td class="px-6 py-4 whitespace-nowrap"></td>
							<td class="px-6 py-4 whitespace-nowrap"></td>
							<td class="px-6 py-4 whitespace-nowrap"></td>
							<td class="px-6 py-4 whitespace-nowrap"></td>
							<td class="px-6 py-4 whitespace-nowrap"><b><?php echo number_format($yourTotalPrice, 2, ',', '.') ?></b></td>
						</tr>
					<?php } else { ?>
						<tr class="bg-gray-50 dark:bg-gray-700">

							<td class="px-6 py-4">I alt</td>

							<td class="px-6 py-4 whitespace-nowrap"><b><?php echo number_format($qty, 0, ',', '.') ?></b></td>
							<td class="px-6 py-4 whitespace-nowrap"></td>
							<td class="px-6 py-4 whitespace-nowrap"></td>
							<?php if(!$mobile) { ?>
							<td class="px-6 py-4 whitespace-nowrap"><b><?php echo number_format($totalPrice, 2, ',', '.') ?></b></td>
							<?php } ?>
							<td class="px-6 py-4 whitespace-nowrap"><b><?php echo number_format($yourTotalPrice, 2, ',', '.') ?></b></td>
							<?php if (!$mobile) { ?>
								<td class="px-6 py-4 whitespace-nowrap"><b><?php echo number_format($avgPct, 0, ',', '.') ?></b></td>
							<?php } ?>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
	<?php
}
	/* if ($medlem) {
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
} */
?>
