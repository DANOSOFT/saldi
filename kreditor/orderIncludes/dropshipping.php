<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/orderIncludes/dropshipping.php ---patch 4.0.9 --2025-05-03--
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------

@session_start();
$s_id = session_id();
$title = "Dropshipping";
$css = "../../css/standard.css";
$modulnr = 7;
include("../../includes/connect.php");
include("../../includes/online.php");
include("../../includes/std_func.php");
include("../../includes/grid.php");

// Get parameters
$id = if_isset($_GET, null, "id");
$adresse = if_isset($_GET["adresse"], null);
$search = if_isset($_GET["search"], "");

// Handle the table display
if ($adresse === null) {
    ?>
    <style>
        .dropshipping-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #374151;
        }

        .dropshipping-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .dropshipping-header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }

        .cancel-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background-color: white;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            color: #374151;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        .cancel-btn:hover {
            background-color: #f9fafb;
            border-color: #9ca3af;
            color: #111827;
        }

        .cancel-btn svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .info-banner {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #1e40af;
            font-size: 0.875rem;
        }

        .info-banner svg {
            width: 1.25rem;
            height: 1.25rem;
            flex-shrink: 0;
        }

        .grid-card {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
    </style>
        <div class="dropshipping-header">
            <h1>Adresseopslag</h1>
            <a href="../ordre.php?id=<?php echo $id; ?>" class="cancel-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Annuller
            </a>
        </div>

        <?php
        $grid_data = array(
            "query" => "SELECT id, kontonr, firmanavn, addr1, addr2, bynavn, postnr FROM adresser WHERE {{WHERE}} ORDER BY {{SORT}}",
            "filters" => array(),
            "extraParams" => array("id" => $id),
            "rowStyle" => function($row) {
                return "cursor: pointer;";
            },
            "defaultRowCount" => 50,
            "columns" => array(
                array(
                    "field" => "kontonr",
                    "headerName" => "Kontonr",
                    "width" => "1",
                    "render" => function ($value, $row, $col) {
                        global $id;
                        $adresseId = $row['id'];
                        return "<td align='{$col['align']}' onclick=\"window.location='?id=$id&adresse=$adresseId'\">$value</td>";
                    }
                ),
                array(
                    "field" => "firmanavn",
                    "headerName" => "Firmanavn",
                    "width" => "2",
                    "render" => function ($value, $row, $col) {
                        global $id;
                        $adresseId = $row['id'];
                        return "<td align='{$col['align']}' onclick=\"window.location='?id=$id&adresse=$adresseId'\">$value</td>";
                    }
                ),
                array(
                    "field" => "addr1",
                    "headerName" => "Adresse",
                    "width" => "2",
                    "render" => function ($value, $row, $col) {
                        global $id;
                        $adresseId = $row['id'];
                        return "<td align='{$col['align']}' onclick=\"window.location='?id=$id&adresse=$adresseId'\">$value</td>";
                    }
                ),
                array(
                    "field" => "addr2",
                    "headerName" => "Adresse 2",
                    "width" => "2",
                    "render" => function ($value, $row, $col) {
                        global $id;
                        $adresseId = $row['id'];
                        return "<td align='{$col['align']}' onclick=\"window.location='?id=$id&adresse=$adresseId'\">$value</td>";
                    }
                ),
                array(
                    "field" => "postnr",
                    "headerName" => "Postnr",
                    "width" => "0.5",
                    "render" => function ($value, $row, $col) {
                        global $id;
                        $adresseId = $row['id'];
                        return "<td align='{$col['align']}' onclick=\"window.location='?id=$id&adresse=$adresseId'\">$value</td>";
                    }
                ),
                array(
                    "field" => "bynavn",
                    "headerName" => "By",
                    "width" => "1",
                    "render" => function ($value, $row, $col) {
                        global $id;
                        $adresseId = $row['id'];
                        return "<td align='{$col['align']}' onclick=\"window.location='?id=$id&adresse=$adresseId'\">$value</td>";
                    }
                )
            )
        );
        
        print "<div style='height: calc(89%)'>";
        create_datagrid("dropshipping_grid_v2", $grid_data);
        print "</div>";
        ?>
    <?php
} else {
    $adresse = db_escape_string($adresse);
	$q = db_select("select firmanavn, addr1, addr2, bynavn, postnr from adresser where id=$adresse",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($q)) {
        $qtxt = "update ordrer set 
            lev_navn='".db_escape_string($row["firmanavn"])."',
            lev_addr1='".db_escape_string($row["addr1"])."',
            lev_addr2='".db_escape_string($row["addr2"])."',
            lev_postnr='".db_escape_string($row["postnr"])."',
            lev_bynavn='".db_escape_string($row["bynavn"])."'
        where id=".db_escape_string($id);
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        header("Location: ../ordre.php?id=$id");
        exit;
    } else {
        // Redirect to address selection page if address not found
        header("Location: ?id=$id");
        exit;
    }
}