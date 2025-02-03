<?php
@session_start();
$s_id = session_id();

// --- admin/vis_regnskaber.php --- patch 4.0.4 --- 2021.09.16 ---
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
// Copyright (c) 2003-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 20210328 PHR Some cleanup.
// 20210916 LOE Translated some texts

$css = "../css/standard.css";
$title = "vis regnskaber";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$saldiregnskab = NULL;
$lukket = array();

$rediger    = if_isset($_GET['rediger']);
$showClosed = if_isset($_GET['showClosed']);
$beregn     = if_isset($_GET['beregn']);
$sort       = if_isset($_GET['sort']);
$sort2      = if_isset($_GET['sort2']);
$desc       = if_isset($_GET['desc']);
$modulnr    = 102;

if ($db != $sqdb) {
	$alert = findtekst(1905, $sprog_id); #20210916
	print "<BODY onLoad=\"javascript:alert('$alert')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
	exit;
}
if (isset($_POST['submit'])) {
	$rediger = "on";
	$db_antal = if_isset($_POST['db_antal']);
	$id = if_isset($_POST['id']);
	$gl_brugerantal = if_isset($_POST['gl_brugerantal']);
	$gl_posteringer = if_isset($_POST['gl_posteringer']);
	$brugerantal = if_isset($_POST['brugerantal']);
	$posteringer = if_isset($_POST['posteringer']);
	$gl_lukket = if_isset($_POST['gl_lukket']);
	$lukket = if_isset($_POST['lukket']);
	$gl_lukkes = if_isset($_POST['gl_lukkes']);
	$lukkes = if_isset($_POST['lukkes']);
	$gl_betalt_til = if_isset($_POST['gl_betalt_til']);
	$betalt_til = if_isset($_POST['betalt_til']);
	$gl_logintekst = if_isset($_POST['gl_logintekst']);
	$logintekst = if_isset($_POST['logintekst']);


	for ($x = 1; $x <= $db_antal; $x++) {
		if (!isset($lukket[$x]) || !$lukkes[$x]) $lukkes[$x] = "2099-12-31";
		else $lukkes[$x] = usdate($lukkes[$x]);
		if (!isset($betalt_til[$x]) || !$betalt_til[$x]) $betalt_til[$x] = "2099-12-31";
		else $betalt_til[$x] = usdate($betalt_til[$x]);
		if (
			$gl_brugerantal[$x] != $brugerantal[$x] ||
			$gl_posteringer[$x] != $posteringer[$x] ||
			$gl_lukket[$x] != $lukket[$x] ||
			$gl_lukkes[$x] != $lukkes[$x] ||
			$gl_betalt_til[$x] != $betalt_til[$x] ||
			$gl_logintekst[$x] != $logintekst[$x]
		) {
			if ($saldiregnskab) $qtxt = "update regnskab set brugerantal='$brugerantal[$x]',posteringer='$posteringer[$x]',lukket='$lukket[$x]',lukkes='$lukkes[$x]',betalt_til='$betalt_til[$x]',logintekst='$logintekst[$x]' where id = '$id[$x]'";
			else $qtxt = "update regnskab set	brugerantal='$brugerantal[$x]',posteringer='$posteringer[$x]',lukket='$lukket[$x]'where id = '$id[$x]'";
			if ($id[$x]) db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	}
} else { # 2020090 can be removed  
	$qtxt = "update regnskab set lukket='' where lukket is NULL";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}


print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\" height=\"25\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=../index/admin_menu.php accesskey=L>" . findtekst(30, $sprog_id) . "</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\">" . findtekst(340, $sprog_id) . "</td>"; #Vis regnskaber
print "<td width=\"5%\" $top_bund align = \"center\">";
if ($showClosed) print "<a href='vis_regnskaber.php?sort=$sort&rediger=$rediger'>" . findtekst(1906, $sprog_id) . " </a>"; #Skjul Luk
else print "<a href='vis_regnskaber.php?sort=$sort&rediger=$rediger&showClosed=on'>" . findtekst(1907, $sprog_id) . " </a>"; #Vis Luk
print "</td><td $top_bund align = \"center\">";
if ($rediger) print "<a href='vis_regnskaber.php?sort=$sort&showClosed=$showClosed' > " . findtekst(1908, $sprog_id) . "</a>"; #Lås
else print "<a href='vis_regnskaber.php?sort=$sort&showClosed=$showClosed&rediger=on' accesskey=R> " . findtekst(1206, $sprog_id) . "</a>"; #Ret
print "</td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align = center valign = center>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";

$id = array();
$regnskab = array();
$db_navn = array();

if (!$sort) $sort = 'regnskab';
if (!$sort2) $sort2 = 'id';
if ($sort == $sort2) {
	if (!$desc) {
		$order = "order by $sort desc";
		$desc = 'on';
	} else {
		$order = "order by $sort";
		$desc = '';
	}
} else {
	$order = "order by $sort,$sort2";
	$desc = '';
}

print "<tr><td><b><a href=vis_regnskaber.php?sort=id&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>id</a></b></td>
	<td><b><a href=vis_regnskaber.php?sort=regnskab&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>" . findtekst(849, $sprog_id) . "</a></b></td> 
	<td><a href=vis_regnskaber.php?sort=brugerantal&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>" . findtekst(909, $sprog_id) . "</a></td>
	<td><a href=vis_regnskaber.php?sort=posteringer&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>" . findtekst(1910, $sprog_id) . "</a></td>
	<td><a href=vis_regnskaber.php?sort=posteret&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>" . findtekst(1911, $sprog_id) . "</a></td>
	<td><a href=vis_regnskaber.php?sort=sidst&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>" . findtekst(1912, $sprog_id) . "</a></td>";

if ($showClosed) print "<td><a href=vis_regnskaber.php?sort=lukket&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>" . findtekst(387, $sprog_id) . "</a></td>";
if ($saldiregnskab) {
	print "<td><a href=vis_regnskaber.php?sort=lukkes&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>" . findtekst(1913, $sprog_id) . "</a></td>
		<td><a href=vis_regnskaber.php?sort=betalt_til&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>" . findtekst(1914, $sprog_id) . "</a></td>
		<td><a href=vis_regnskaber.php?sort=logintekst&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>" . findtekst(1915, $sprog_id) . "</a></td>";
}
print "</tr>";

$q = db_select("select * from brugere where brugernavn = '$brugernavn'", __FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
list($admin, $oprette, $slette, $tmp) = explode(",", $r['rettigheder'], 4);
$adgang_til = explode(",", $tmp);
$x = 0;
$qtxt = "select * from regnskab where db != '$sqdb'";
if (!$showClosed) $qtxt .= " and lukket != 'on'";
$qtxt .= " $order";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if ($admin || in_array($r['id'], $adgang_til)) {
		$id[$x] = $r['id'];
		$regnskab[$x] = $r['regnskab'];
		$db_navn[$x] = $r['db'];
		$posteringer[$x] = $r['posteringer'] * 1;
		$posteret[$x] = $r['posteret'] * 1;
		$brugerantal[$x] = $r['brugerantal'] * 1;
		$sidst[$x] = $r['sidst'];
		$email[$x] = $r['email'];
		#		$oprettet[$x]=date("d-m-Y",$r['oprettet']);
		($r['lukket'] == 'on') ? $lukket[$x] = 'X' : $lukket[$x] = NULL;
		($r['lukkes'] == 'on') ? $lukkes[$x] = 'X' : $lukkes[$x] = NULL;
		$betalt_til[$x] = if_isset($r['betalt_til']);
		$logintekst[$x] = if_isset($r['logintekst']);
		if ($lukkes[$x]) $lukkes[$x] = dkdato($lukkes[$x]);
		if ($betalt_til[$x]) $betalt_til[$x] = dkdato($betalt_til[$x]);
		$x++;
	}
}
if ($beregn) {
	$tempScriptPath = "../temp/$sqdb/tmp.sh";
	$tempDbListPath = "../temp/dbliste.txt";

	// Create a temporary shell script to list databases
	file_put_contents($tempScriptPath, "#!/bin/sh\nexport PGPASSWORD='$sqpass'\npsql --username=$squser -l > $tempDbListPath\n");
	chmod($tempScriptPath, 0700);

	// Execute the script and remove it
	system("/bin/sh '$tempScriptPath'");
	unlink($tempScriptPath);

	// Read database list and clean up
	$dbEntries = file($tempDbListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	unlink($tempDbListPath);

	// Extract database names containing an underscore
	$existingDatabases = array_filter(array_map(function ($entry) {
		$parts = explode("|", $entry, 2);
		return (isset($parts[0]) && strpos($parts[0], "_") !== false) ? trim($parts[0]) : null;
	}, $dbEntries));

	$existingDatabases = array_values(array_filter($existingDatabases));
	print_r($existingDatabases);

	// Determine the cutoff date for transaction counting
	$cutoffDate = date("Y-m-d", strtotime("-1 year"));

	foreach ($existingDatabases as $index => $dbName) {
		if (in_array($dbName, $existingDatabases)) {
			$query = "SELECT datname FROM pg_database WHERE datname = '$dbName'";
			echo "$query<br>";

			if (db_fetch_array(db_select($query, __FILE__ . " linje " . __LINE__))) {
				echo "$dbName eksisterer<br>";
				db_connect($sqhost, $squser, $sqpass, $dbName, __FILE__ . " linje " . __LINE__);

				$tableCheckQuery = "SELECT * FROM pg_tables WHERE tablename='transaktioner'";
				if (db_fetch_array(db_select($tableCheckQuery, __FILE__ . " linje " . __LINE__))) {
					$transactionCountQuery = "SELECT count(id) AS transantal FROM transaktioner WHERE logdate >= '$cutoffDate'";
					$result = db_fetch_array(db_select($transactionCountQuery, __FILE__ . " linje " . __LINE__));
					$posteringer[$index] = (int) $result['transantal'];

					$lastLogDateQuery = "SELECT max(logdate) AS logdate FROM transaktioner";
					if ($result = db_fetch_array(db_select($lastLogDateQuery, __FILE__ . " linje " . __LINE__))) {
						$sidst[$index] = strtotime($result['logdate']);
					}

					$lastBatchSaleQuery = "SELECT * FROM batch_salg ORDER BY id DESC LIMIT 1";
					if ($result = db_fetch_array(db_select($lastBatchSaleQuery, __FILE__ . " linje " . __LINE__))) {
						if (!empty($result['modtime']) && strtotime($result['modtime']) > $sidst[$index]) {
							$sidst[$index] = strtotime($result['modtime']);
						}
					}
				} else {
					$sidst[$index] = null;
				}

				include("../includes/connect.php");
			} else {
				echo "Opretter $dbName<br>";
				db_create($dbName);
			}
		} else {
			echo "Opretter $dbName<br>";
			db_create($dbName);
			$sidst[$index] = null;
		}
	}
}



if ($rediger)	print "<form name=regnskaber action=vis_regnskaber.php method=post>";
for ($x = 0; $x < count($id); $x++) {
	if (!$sidst[$x]) $sidst[$x] = 0;
	if ($rediger && isset($id[$x])) {
		print "<input type=hidden name=\"id[$x]\" value=\"$id[$x]\">";
		print "<input type=hidden name=\"gl_lukket[$x]\" value=\"$lukket[$x]\">";
		print "<input type=hidden name=\"gl_lukkes[$x]\" value=\"$lukkes[$x]\">";
		print "<input type=hidden name=\"gl_brugerantal[$x]\" value=\"$brugerantal[$x]\">";
		print "<input type=hidden name=\"gl_posteringer[$x]\" value=\"$posteringer[$x]\">";
		print "<input type=hidden name=\"gl_betalt_til[$x]\" value=\"$betalt_til[$x]\">";
		print "<input type=hidden name=\"gl_logintekst[$x]\" value=\"$logintekst[$x]\">";
		print "<tr><td align='right'> $id[$x]</td><td><a href=aaben_regnskab.php?db_id=$id[$x]>$regnskab[$x]</a></td>";
		print "<td><input type=text size=\"5\" style=\"text-align:right\" name=\"brugerantal[$x]\" value=\"$brugerantal[$x]\"></td>";
		print "<td><input type=text size=\"5\" style=\"text-align:right\" name=\"posteringer[$x]\" value=\"$posteringer[$x]\"</td>";
		print "<td align='right'>$posteret[$x]</td>";
		print "<td align='right'>" . date("d-m-Y", $sidst[$x]) . "</td>";
		if ($lukket[$x]) $lukket[$x] = "checked";
		if ($showClosed) print "<td align=center><input type=checkbox name=lukket[$x] $lukket[$x]></td>";
		if ($saldiregnskab) {
			print "<td><input type=text size='8' style=\"text-align:right\" name=\"lukkes[$x]\" value=\"$lukkes[$x]\"</td>";
			print "<td><input type=text size='8' style=\"text-align:right\" name=\"betalt_til[$x]\" value=\"$betalt_til[$x]\"</td>";
			print "<td><input type=text size='25' style=\"text-align:right\" name=\"logintekst[$x]\" value=\"$logintekst[$x]\"</td>";
		}
		print "</tr>";
	} else {
		#				if ($admin || in_array($r['id'],$adgang_til)) {
		#					if ($beregn) echo "update regnskab set posteret='$posteringer[$x]' sidst='$sidst[$x]' where id='$id[$x]'<br>";
		if ($beregn) {
			$qtxt = "update regnskab set posteret='$posteringer[$x]',sidst='$sidst[$x]' where id='$id[$x]'";
			echo "$qtxt<br>";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		print "<tr><td align='right'> $id[$x]</td><td><a href=aaben_regnskab.php?db_id=$id[$x]>$regnskab[$x]</a></td>";
		print "<td>$brugerantal[$x]<br></td>";
		print "<td>$posteringer[$x]<br></td>";
		print "<td align='right'>$posteret[$x]<br></td>";
		print "<td align='right'>" . date("d-m-Y", $sidst[$x]) . "<br></td>";
		print "<td align='center'>$lukket[$x]<br></td>";
		if ($saldiregnskab) {
			print "<td align='right'>$betalt_til[$x]<br></td>";
			// 						print "<td align='right'>$lukkes[$x]<br></td>";
			print "<td align='right'>$logintekst[$x]<br></td>";
		}
		print "</tr>";
		#				}
	}
	print "<input type=\"hidden\" name=\"db_antal\" value=\"$x\">";
	#		}
	#	}
}
if ($rediger) {
	if ($saldiregnskab) $colspan = 10;
	else $colspan = 7;
	print "<input type=hidden name=\"db_antal\" value=\"$x\">";
	print "<tr><td colspan=\"$colspan\" align=\"center\"><input type=\"submit\" value=\"Opdater\" name=\"submit\"></td></tr>";
	print "</form></tbody></table>";
} else {
	print "</tbody></table>";
	print "<a href=\"vis_regnskaber.php?beregn=1\">" . findtekst(1916, $sprog_id) . "</a>";
}
?>
</body>

</html>