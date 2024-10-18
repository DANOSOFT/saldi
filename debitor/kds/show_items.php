<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- payments/vibrant.php --- lap 4.1.0 --- 2024.02.09 ---
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
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20240209 PHR Added indbetaling
// 20240301 PHR Added $printfile and call to saldiprint.php

@session_start();
$s_id = session_id();

$css = "../../css/kds.css";

include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/std_func.php");
include ("../../includes/stdFunc/dkDecimal.php");
include ("../../includes/stdFunc/usDecimal.php");

$active = if_isset($_GET["active"], -1);

$row_height = get_settings_value("height", "KDS", 20);
$row_style = "height: " . $row_height . "px";

# Generate column style
$columns = get_settings_value("columns", "KDS", 5);
$display_style = "grid-template-columns: ";
for ($i = 0; $i < $columns; $i++) {
    $display_style .= "1fr ";
}
$display_style = trim($display_style) . ";";

$colors = array();
$q = db_select("select var_value from settings where var_name='color' and var_grp='KDS'", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$row = explode("-", $r["var_value"]);
	array_push($colors, $row);
}

# Get orders
$orders = array();
$ids = array();
$rushes = array();
$q=db_select("select id, data, rush from kds_records where bumped = false order by timestamp",__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)) {
	array_push($orders, json_decode($r['data']));
	array_push($ids, json_decode($r['id']));
	if ($r["rush"] == "t") array_push($rushes, json_decode(TRUE));
	else array_push($rushes, json_decode(FALSE));
}

#print_r($orders);

print "<div id='kds-display' style='$display_style'>";

foreach ($orders as $index => $order) {
	if ($order->køkken == $_COOKIE["kitchen"]) {
		$time = time() - $order->tidspunkt;
		$minutes = sprintf('%02d', floor($time/60));
		$seconds = sprintf('%02d', $time - floor($time/60)*60);

		# Get color
		$timecolor = "1";
		for ($i = 0; $i < count($colors); $i++) {
			if ($colors[$i][0] <= $minutes) {
				$timecolor = $colors[$i][1];
			} else {
				break;
			}
		}


		$rush = $rushes[$index];
		if ($rush) {
			$rushed = "rushed";
			$timecolor ="";
		} else $rushed = "";

		$itemid = $ids[$index];
		if ($active == $itemid) {
			$activated = "active";
			print "<script>var selected = '$active';</script>";
		} else $activated = "";
		print "<div class='kds-item $activated' onclick='window.location=\"?active=$itemid\"'>";

		# Print header
		print "	<div class='kds-header-time $rushed' style='background-color: $timecolor;$row_style'>";
		print "		<span>" . $minutes . ":" . $seconds . "</span>";
		print "	</div>";
		print "	<div class='kds-header-user $rushed' style='background-color: $timecolor;$row_style'>";
		print "		<span>" . $order->bruger . "</span> <span>" . $order->bord . "</span>";
		print "	</div>";

		$linjer = array();

		if ($order->besked) {
			array_push($linjer, array("", $order->besked, "#f7ff12"));
		}

		foreach ($order->varer as $vareindex => $vare) {
			array_push($linjer, array($vare->antal . "x ", $vare->navn, "#ffffff00"));
			
			for ($i = 0; $i < count($vare->tilfravalg); $i++) {
				array_push($linjer, array("", $vare->tilfravalg[$i], "#13ff4e"));
			}
			if ($vare->note) {
				array_push($linjer, array("", $order->note, "#f7ff12"));
			}
		}

		print "<table>";
		for ($i = 0; $i < count($linjer); $i++) {
			print "<tr style='background-color: ". $linjer[$i][2] .";$row_style'>";
			print "<td>". $linjer[$i][0] ."</td>";
			print "<td>". $linjer[$i][1] ."</td>";
			print "</tr>";
		}
		print "</table>";

		print "</div>";
	}
}

print '</div>';

print "<script>setTimeout(() => {window.location.reload()}, 5000);</script>";

?>
