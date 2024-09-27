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

if (isset($_GET["recall"])) {
	$id=$_GET["recall"];
	$time = time();
	db_modify("UPDATE kds_records SET last_undo = FALSE",__FILE__ . " linje " . __LINE__);
	db_modify("update kds_records set bumped=false, last_undo=true, time_to_complete=-1 where id=$id",__FILE__ . " linje " . __LINE__);
}

$row_height = 20;
$row_style = "height: " . $row_height . "px";
$vis = if_isset($_GET["vis"], -1);

print "<button id='back' onclick='location.href = \"../kds\"'>Tilbage</button>";
print "<table id='recall-table'>";
print "<tr>
	<th>ID</th>
	<th>Køkken</th>
	<th>Bord</th>
	<th>Bruger</th>
	<th>Alder</th>
	<th>Aktiv tid</th>
	<th style='width: 160px'>Vis varer</th>
	<th style='width: 160px'>Recall</th>
       </tr>";
$q=db_select("select id, data, rush, time_to_complete, time_to_complete - timestamp as time from kds_records where bumped = true order by time_to_complete desc",__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)) {
	$order = json_decode($r["data"]);
	$koekken = $order->køkken;
	$bord = $order->bord;
	$bruger = $order->bruger;
	$time = round($r["time"]/60);
	$timesince = round((time() - $r["time_to_complete"])/60);

	$vistxt = "";
	if ($vis == $r["id"]) {
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

		$vistxt = $vistxt. "<table>";
		for ($i = 0; $i < count($linjer); $i++) {
			$vistxt = $vistxt. "<tr style='background-color: ". $linjer[$i][2] .";$row_style'>";
			$vistxt = $vistxt. "<td>". $linjer[$i][0] ."</td>";
			$vistxt = $vistxt. "<td>". $linjer[$i][1] ."</td>";
			$vistxt = $vistxt. "</tr>";
		}
		$vistxt = $vistxt. "</table>";
		$visfunk = "window.location = \"?\"";
		$color = "#f4cccc";
	} else {
		$visfunk = "window.location = \"?vis=$r[id]\"";
		$color = "";
	}

	print "<tr>
		<td>$r[id]</td>
		<td>$koekken</td>
		<td>$bord</td>
		<td>$bruger</td>
		<td>$timesince min</td>
		<td>$time min</td>
		<td><button onclick='$visfunk' style='background-color: $color'>Vis varer</button>$vistxt</td>
		<td><button onclick='window.location = \"?recall=$r[id]\"'>Recall</button></td>
	       </tr>";
}
print "</table>";

?>


