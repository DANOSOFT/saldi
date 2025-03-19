<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___|_\_\
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

// Include necessary files if not already included
if (!function_exists('get_settings_value')) {
    include ("../../includes/connect.php");
    include ("../../includes/online.php");
    include ("../../includes/std_func.php");
    include ("../../includes/stdFunc/dkDecimal.php");
    include ("../../includes/stdFunc/usDecimal.php");
}
 
$move   = isset($_GET["move"]) ? $_GET["move"] : null;
$active = isset($_GET["active"]) ? $_GET["active"] : -1;

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
$q = db_select("select id, data, rush from kds_records where bumped = false order by coalesce(sort_timestamp, timestamp)", __FILE__ . " linje " . __LINE__);
while($r = db_fetch_array($q)) {
    array_push($orders, json_decode($r['data']));
    array_push($ids, $r['id']);
    if ($r["rush"] == "t") array_push($rushes, true);
    else array_push($rushes, false);
}

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
        $activated = "";
        $move_button_style = "
            background-color: #999;
            padding: 10px;
        ";
        
        if ($move == null || $itemid == $move) {
            if ($itemid == $move) {
                $timecolor = "#999";
            }
            # Add data-itemid attribute for JavaScript selection
            print "<div class='kds-item $activated' data-itemid='$itemid' data-timestamp='" . (time() - $time) . "'>";

            # Print header
            print "    <div class='kds-header-time $rushed' style='background-color: $timecolor;$row_style'>";
            print "        <span>" . $minutes . ":" . $seconds . "</span>";
            print "    </div>";
            print "    <div class='kds-header-user $rushed' style='background-color: $timecolor;$row_style'>";
            print "        <span>" . $order->bruger . "</span> <span>" . $order->bord . "</span>";
            print "    </div>";
        } else {
            # Add data-itemid attribute for JavaScript selection
            print "<div class='kds-item $activated' data-itemid='$itemid' data-timestamp='" . (time() - $time) . "'>";
            print "<div style='display: flex;'>";
            print "    <div class='move-btn' style='$move_button_style' data-itemid='$itemid' data-direction='left'>&lt</div>";

            # Print header
            print "    <div style='flex: 1'>";
            print "        <div class='kds-header-time $rushed' style='background-color: $timecolor;$row_style'>";
            print "            <span>" . $minutes . ":" . $seconds . "</span>";
            print "        </div>";
            print "        <div class='kds-header-user $rushed' style='background-color: $timecolor;$row_style'>";
            print "            <span>" . $order->bruger . "</span> <span>" . $order->bord . "</span>";
            print "        </div>";
            print "    </div>";
            print "    <div class='move-btn' style='$move_button_style' data-itemid='$itemid' data-direction='right'>&gt</div>";
            print "</div>";
        }

        $linjer = array();

        if ($order->besked) {
            array_push($linjer, array("", $order->besked, "#f7ff12"));
        }

        foreach ($order->varer as $vareindex => $vare) {
            array_push($linjer, array($vare->antal . "x ", $vare->navn, "#ffffff00"));
            
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

// Add JavaScript for timer updates
print '<script>
    // Function to pad single-digit numbers with a leading zero
    function padZero(num) {
        return num.toString().padStart(2, "0");
    }

    // Function to update timers
    function updateTimers() {
        const items = document.querySelectorAll(".kds-header-time span");
        const currentTime = Math.floor(Date.now() / 1000);

        items.forEach(timerElement => {
            // Extract initial timestamp from the parent element\'s data attribute
            const initialTimestamp = parseInt(timerElement.closest(".kds-item").dataset.timestamp);
            
            // Calculate elapsed time
            const elapsedTime = currentTime - initialTimestamp;
            
            // Calculate minutes and seconds
            const minutes = padZero(Math.floor(elapsedTime / 60));
            const seconds = padZero(elapsedTime % 60);
            
            // Update timer display
            timerElement.textContent = `${minutes}:${seconds}`;
        });

        // Schedule the timer update every second
        setInterval(updateTimers, 1000);
    });

</script>';