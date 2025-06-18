<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- payments/flatpay.php --- lap 4.1.0 --- 2024.02.27 ---
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
// 20240227 PHR Added $printfile and call to saldiprint.php

@session_start();
$s_id = session_id();

include ("../includes/connect.php");
include ("../includes/online.php");
include ("../includes/std_func.php");
include ("../includes/stdFunc/dkDecimal.php");
include ("../includes/stdFunc/usDecimal.php");
$returside = if_isset($_GET["returside"], "../crmkalender.php");
print_r($_GET);
echo $returside;
$valg = "";
$date = $_GET["date"];

include ("crmIncludes/topLine.php");
include ("crmIncludes/getFilter.php");
include ("crmIncludes/displayTopButtons.php");

// Handle search query
$search = isset($_GET["query"]) ? trim($_GET["query"]) : "";

// Secure input to prevent SQL injection
$search = db_escape_string($search);

?>

<input type="text" id="search" placeholder="Søg efter firma eller medarbejder..." onkeyup="filterTable()" style="margin-bottom: 10px; width: 100%; padding: 8px;">

<table id="debtable">
    <tr>
        <th>Kontonr</th>
        <th>Firmanavn</th>
        <th>Cvrnr</th>
        <th>Kontaktperson</th>
    </tr>
    <tbody id="table-body">
        <!-- Results will be loaded here -->
        <?php
        // Fetch results based on the search query
        $query = "SELECT a.*, an.id as emp_id, an.navn as emp_name, an.email as emp_email, an.tlf as emp_phone 
                  FROM adresser a 
                  LEFT JOIN ansatte an ON a.id = an.konto_id
                  WHERE a.art = 'D' 
                  AND (
                        a.firmanavn ILIKE '%$search%' 
                        OR a.kontakt ILIKE '%$search%'
                        OR an.navn ILIKE '%$search%' 
                        OR an.email ILIKE '%$search%' 
                        OR an.tlf ILIKE '%$search%'
                  ) 
                  ORDER BY a.firmanavn, an.navn";

        $result = db_select($query, __FILE__ . " linje " . __LINE__);

        $currentCompany = null;

        while ($r = db_fetch_array($result)) {
            if ($currentCompany !== $r['id']) {
                echo "<tr class='company-row'>
                        <td>{$r['kontonr']}</td>
                        <td><strong>{$r['firmanavn']}</strong></td>
                        <td>{$r['cvrnr']}</td>
                        <td>{$r['kontakt']}</td>
                      </tr>";
                $currentCompany = $r['id'];
            }

            if ($r['emp_id']) {
                echo "<tr class='employee-row' onclick='window.location.href=\"historikkort.php?id={$r['id']}&employee={$r['emp_id']}&kontaktigen=$date&returside=$returside\"'>
                        <td colspan='1'></td>
                        <td colspan='10' style='padding-left: 10px;'>➡ {$r['emp_name']} / {$r['emp_email']} / {$r['emp_phone']}</td>
                      </tr>";
            }
        }
        ?>
    </tbody>
</table>

<script>
function filterTable() {
    let input = document.getElementById("search").value.trim();
    let tableBody = document.getElementById("table-body");

    // Make the GET request to update the search results
    fetch("<?php echo $_SERVER['REQUEST_URI']; ?>&query=" + encodeURIComponent(input))
        .then(response => response.text())
        .then(data => {
            // Only update the table body
            console.log(data);

            // Parse the response to get the new table body
            var parser = new DOMParser();
            var doc = parser.parseFromString(data, "text/html");

            // Extract and update the table body
            var newTableBody = doc.getElementById("table-body");
            if (newTableBody) {
                tableBody.innerHTML = newTableBody.innerHTML;
            }
        })
        .catch(error => console.error("Error fetching data:", error));
}

// Initial load when the page first loads
document.getElementById("search").dispatchEvent(new Event("input"));
</script>


<style>
    #debtable {
        width: 100%;
        border-collapse: collapse;
    }

    .company-row {
        background-color: #ddf;
        font-weight: bold;
    }

    .employee-row {
        background-color: #f5f5f5;
        cursor: pointer;
    }

    .employee-row:hover {
        background-color: #e0e0e0;
    }

    #search {
        font-size: 16px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }
</style>