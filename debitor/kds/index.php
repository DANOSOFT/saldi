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

// Handle bump action via AJAX or direct request
if (isset($_GET["bump"])) {
    $id = $_GET["bump"];
    $time = time();
    db_modify("UPDATE kds_records SET last_undo = FALSE", __FILE__ . " linje " . __LINE__);
    db_modify("update kds_records set bumped=true, last_undo=true, time_to_complete=$time where id=$id", __FILE__ . " linje " . __LINE__);
    
    // If AJAX request, return success
    if (isset($_GET["ajax"])) {
        echo json_encode(['status' => 'success', 'action' => 'bump', 'id' => $id]);
        exit;
    }
}

// Handle rush action via AJAX or direct request
if (isset($_GET["rush"])) {
    $id = $_GET["rush"];
    db_modify("update kds_records set rush=NOT rush where id=$id", __FILE__ . " linje " . __LINE__);
    
    // If AJAX request, return success
    if (isset($_GET["ajax"])) {
        echo json_encode(['status' => 'success', 'action' => 'rush', 'id' => $id]);
        exit;
    }
}

// Handle undo action via AJAX or direct request
if (isset($_GET["undo"])) {
    db_modify("UPDATE kds_records SET bumped = NOT bumped WHERE last_undo IS TRUE", __FILE__ . " linje " . __LINE__);
    
    // If AJAX request, return success
    if (isset($_GET["ajax"])) {
        echo json_encode(['status' => 'success', 'action' => 'undo']);
        exit;
    }
}

// Transfer ticket to another kitchen
if (isset($_GET["transfer"])) {
    $id = $_GET["transfer"];
    $target_kitchen = $_GET["target"];
    
    // Fetch the current record
    $q = db_select("SELECT data FROM kds_records WHERE id = $id", __FILE__ . " linje " . __LINE__);
    $r = db_fetch_array($q);
    
    // Decode and modify the JSON data
    $order_data = json_decode($r['data']);
    $order_data->køkken = $target_kitchen;
    
    // Encode the updated JSON
    $updated_data = json_encode($order_data);
    
    // Update the record with new kitchen in JSON
    db_modify("UPDATE kds_records SET data = '$updated_data' WHERE id = $id", __FILE__ . " linje " . __LINE__);
    
    // If AJAX request, return success
    if (isset($_GET["ajax"])) {
        echo json_encode(['status' => 'success', 'action' => 'transfer', 'id' => $id, 'target' => $target_kitchen]);
        exit;
    }
}

// If this is an AJAX data request, return data only
if (isset($_GET["getData"])) {
    include("show_items.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Display System</title>
    <link rel="stylesheet" href="<?php echo $css; ?>">
    <style>
        #kds-container {
            flex: 1;
            overflow: auto;
        }
        #toolbar {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background-color: #f0f0f0;
            border-top: 1px solid #ccc;
			box-sizing: border-box;
        }
        body {
            display: flex;
            flex-direction: column;
            height: 100vh;
            margin: 0;
        }
    </style>
</head>
<body>
    <div id="kds-container"></div>
    <div id="toolbar">
        <div>
            <button onclick="window.location = '../../index/main.php'">Luk</button>
            <button onclick="window.location = 'kitchen.php'">Skift køkken (<?php print $_COOKIE["kitchen"]; ?>)</button>
            <?php
            // Fetch all available kitchens
            $q = db_select("SELECT box1 FROM grupper WHERE art = 'V_CAT' ORDER BY box1", __FILE__ . " linje " . __LINE__);
            while($r = db_fetch_array($q)) {
                // Skip the current kitchen
                if ($r['box1'] != $_COOKIE["kitchen"]) {
                    echo "<button onclick='transferOrder(\"$r[box1]\")'>Send to $r[box1]</button>";
                }
            }
            ?>
        </div>
        <div>
            <button style="background-color: #d9ead3" onclick="bumpOrder()">Bump</button>
            <button style="background-color: #f4cccc" onclick="rushOrder()">Rush</button>
            <button onclick="undoAction()">Undo</button>
            <button onclick="window.location = 'recall.php'">Recall</button>
        </div>
    </div>

    <script>
        let selectedItem = null;
        let refreshInterval = null;

        // Function to fetch and update the KDS display
        function fetchKdsData() {
            fetch('?getData=1')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('kds-container').innerHTML = data;
                    
                    // Reattach event listeners to items
                    attachItemListeners();
                    
                    // Ensure the selected item stays selected if it still exists
                    if (selectedItem) {
                        const item = document.querySelector(`.kds-item[data-itemid="${selectedItem}"]`);
                        if (item) {
                            item.classList.add('active');
                        } else {
                            selectedItem = null; // Reset if item no longer exists
                        }
                    }
                })
                .catch(error => console.error('Error fetching KDS data:', error));
        }

        // Function to attach event listeners to KDS items
        function attachItemListeners() {
            const items = document.querySelectorAll('.kds-item');
            items.forEach(item => {
                item.addEventListener('click', function(event) {
                    // Prevent any default actions
                    event.preventDefault();
                    event.stopPropagation();
                    
                    // Remove active class from all items
                    document.querySelectorAll('.kds-item').forEach(i => i.classList.remove('active'));
                    
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    // Get the item ID
                    selectedItem = this.getAttribute('data-itemid');
                });
            });
        }

        // Function to handle bump action
        function bumpOrder() {
            if (selectedItem) {
                fetch(`?bump=${selectedItem}&ajax=1`)
                    .then(data => {
                        fetchKdsData(); // Refresh the display
                    })
                    .catch(error => console.error('Error bumping order:', error));
            }
        }

        // Function to handle rush action
        function rushOrder() {
            if (selectedItem) {
                fetch(`?rush=${selectedItem}&ajax=1`)
                    .then(data => {
                        fetchKdsData(); // Refresh the display
                    })
                    .catch(error => console.error('Error rushing order:', error));
            }
        }

        // Function to handle undo action
        function undoAction() {
            fetch('?undo=1&ajax=1')
                .then(data => {
                    fetchKdsData(); // Refresh the display
                })
                .catch(error => console.error('Error undoing action:', error));
        }

        // Function to handle transfer action
        function transferOrder(targetKitchen) {
            if (selectedItem) {
                fetch(`?transfer=${selectedItem}&target=${targetKitchen}&ajax=1`)
                    .then(data => {
                        fetchKdsData(); // Refresh the display
                    })
                    .catch(error => console.error('Error transferring order:', error));
            }
        }

        // Initial data fetch
        document.addEventListener('DOMContentLoaded', () => {
            fetchKdsData();
            
            // Set interval for periodic updates
            refreshInterval = setInterval(fetchKdsData, 5000);
        });
    </script>
</body>
</html>