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

if (!$_COOKIE["kitchen"]) {
	header('Location: kitchen.php');
}

if (isset($_GET["bump"])) {
	$id=$_GET["bump"];
	$time = time();
	db_modify("UPDATE kds_records SET last_undo = FALSE",__FILE__ . " linje " . __LINE__);
	db_modify("update kds_records set bumped=true, last_undo=true, time_to_complete=$time where id=$id",__FILE__ . " linje " . __LINE__);
}
if (isset($_GET["rush"])) {
	$id=$_GET["rush"];
	db_modify("update kds_records set rush=NOT rush where id=$id",__FILE__ . " linje " . __LINE__);
}
if (isset($_GET["undo"])) {
	db_modify("UPDATE kds_records SET bumped = NOT bumped WHERE last_undo IS TRUE",__FILE__ . " linje " . __LINE__);
}

?>

<iframe id="main-frame" src="./show_items.php" title="KDS"></iframe>
<div id='toolbar'>
	<div>
		<button onclick='window.location = `../../index/main.php`'>Luk</button>
		<button onclick='window.location = "kitchen.php"'>Skift køkken (<?php print $_COOKIE["kitchen"]; ?>)</button>
	</div>
	<div>
		<button 
		style='background-color: #d9ead3'
		 onclick='
			let selected = document.getElementById("main-frame").contentWindow.selected;
			if (selected != undefined) {
				window.location = `./?bump=${selected}`
			}
		 '>
			Bump
		 </button>
		<button 
		style='background-color: #f4cccc'
		 onclick='
			let selected = document.getElementById("main-frame").contentWindow.selected;
			if (selected != undefined) {
				window.location = `./?rush=${selected}`
			}
		 '>
			Rush
		 </button>
		<button onclick='window.location = `./?undo=1`'>Undo</button>
		<button onclick='window.location = "recall.php"'>Recall</button>
	</div>
</div>

