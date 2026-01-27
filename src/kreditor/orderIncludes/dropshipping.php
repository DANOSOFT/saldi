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

// Get parameters
$id = $_GET["id"];
$adresse = if_isset($_GET["adresse"], null);
$search = if_isset($_GET["search"], "");

// Handle AJAX request for autocomplete
if (isset($_GET['action']) && $_GET['action'] === 'autocomplete') {
    // We'll return plain HTML for the autocomplete results
    // This avoids JSON parsing issues with headers, etc.
    $term = if_isset($_GET['term'], '');
    $html = '';
    $data = array();
    
    if (!empty($term)) {
        $searchTerm = "%" . db_escape_string($term) . "%";
        $qtxt = "SELECT id, kontonr, firmanavn, addr1, addr2, bynavn, postnr FROM adresser 
                WHERE firmanavn ILIKE '$searchTerm' 
                OR kontonr ILIKE '$searchTerm' 
                OR addr1 ILIKE '$searchTerm' 
                OR addr2 ILIKE '$searchTerm' 
                OR bynavn ILIKE '$searchTerm' 
                OR postnr ILIKE '$searchTerm'
                LIMIT 10";
        
        $query = db_select($qtxt, __FILE__ . " line " . __LINE__);
        
        while ($row = db_fetch_array($query)) {
            $label = htmlspecialchars($row['kontonr'] . ': ' . $row['firmanavn'] . ' - ' . $row['addr1'] . ', ' . $row['postnr'] . ' ' . $row['bynavn']);
            $html .= '<div class="autocomplete-item" data-id="' . $row['id'] . '">' . $label . '</div>';
            $data[] = $row['id']; // We'll just store the IDs in order
        }
    }
    
    // Return HTML content directly
    echo $html;
    exit;
}

// Handle the table display
if ($adresse === null) {
    ?>
        <style>
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }
            .search-container {
                margin-bottom: 20px;
                position: relative;
            }
            .search-bar {
                padding: 8px;
                width: 300px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .search-button {
                padding: 8px 15px;
                background-color: #4CAF50;
                border: none;
                color: white;
                border-radius: 4px;
                cursor: pointer;
                margin-right: 5px;
            }
            .cancel-button {
                padding: 8px 15px;
                background-color: #f44336;
                border: none;
                color: white;
                border-radius: 4px;
                cursor: pointer;
                text-decoration: none;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                padding: 6px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }
            tr:hover {
                background-color: #f5f5f5;
            }
            .action-link {
                display: inline-block;
                padding: 6px 12px;
                background-color: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 4px;
            }
            .action-link:hover {
                background-color: #0056b3;
            }
            /* Autocomplete styles */
            .autocomplete-items {
                position: absolute;
                border: 1px solid #ddd;
                border-top: none;
                z-index: 99;
                width: 300px;
                max-height: 300px;
                overflow-y: auto;
                background-color: white;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            }
            .autocomplete-item {
                padding: 10px;
                cursor: pointer;
                border-bottom: 1px solid #ddd;
            }
            .autocomplete-item:hover {
                background-color: #e9e9e9;
            }
            .autocomplete-active {
                background-color: #007bff !important;
                color: white;
            }
        </style>
        <div class="container">
            <h1>Adresseopslag</h1>
            
            <!-- Search form -->
            <div class="search-container">
                <form id="searchForm" method="GET" action="">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <input type="text" autofocus autocomplete="off" id="searchInput" name="search" placeholder="Søg efter adresse..." class="search-bar" value="<?php echo htmlspecialchars($search); ?>">
                    <div id="autocomplete-results" class="autocomplete-items"></div>
                    <button type="submit" class="search-button">Søg</button>
                    <a href="../ordre.php?id=<?php echo $id; ?>" class="cancel-button">Annuller</a>
                </form>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Kontonr</th>
                        <th>Firmanavn</th>
                        <th>Adresse</th>
                        <th></th>
                        <th>Postnr.</th>
                        <th>By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Build the query with search functionality
                    $qtxt = "SELECT id, kontonr, firmanavn, addr1, addr2, bynavn, postnr FROM adresser";
                    
                    // Add search condition if search parameter exists
                    if (!empty($search)) {
                        $searchTerm = "%" . db_escape_string($search) . "%";
                        $qtxt .= " WHERE firmanavn ILIKE '$searchTerm' 
                                   OR kontonr ILIKE '$searchTerm' 
                                   OR addr1 ILIKE '$searchTerm' 
                                   OR addr2 ILIKE '$searchTerm' 
                                   OR bynavn ILIKE '$searchTerm' 
                                   OR postnr ILIKE '$searchTerm'";
                    }
                    
                    $query = db_select($qtxt, __FILE__ . " line " . __LINE__);
                    
                    // Check if we have any results
                    if (db_num_rows($query) > 0) {
                        while ($row = db_fetch_array($query)) {
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row["kontonr"]); ?></td>
                                <td><?php echo htmlspecialchars($row["firmanavn"]); ?></td>
                                <td><?php echo htmlspecialchars($row["addr1"]); ?></td>
                                <td><?php echo htmlspecialchars($row["addr2"]); ?></td>
                                <td><?php echo htmlspecialchars($row["postnr"]); ?></td>
                                <td><?php echo htmlspecialchars($row["bynavn"]); ?></td>
                                <td>
                                    <a href="?id=<?php echo $id; ?>&adresse=<?php echo $row['id']; ?>" class="action-link">Vælg</a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="6">Ingen adresser fundet. Prøv venligst en anden søgning.</td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <script>
            // Autocomplete functionality
            document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('searchInput');
                const autocompleteResults = document.getElementById('autocomplete-results');
                let currentSelection = -1;
                let addressData = [];
                
                // Add input event listener for search input
                searchInput.addEventListener('input', debounce(function() {
                    const searchTerm = searchInput.value.trim();
                    if (searchTerm.length < 2) {
                        autocompleteResults.innerHTML = '';
                        return;
                    }
                    
                    fetchAutocompleteResults(searchTerm);
                }, 300));
                
                // Handle keyboard navigation for autocomplete
                searchInput.addEventListener('keydown', function(e) {
                    if (!autocompleteResults.hasChildNodes()) return;
                    
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        currentSelection++;
                        setActiveItem();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        currentSelection--;
                        setActiveItem();
                    } else if (e.key === 'Enter' && currentSelection > -1) {
                        e.preventDefault();
                        if (autocompleteResults.children[currentSelection]) {
                            const selectedId = addressData[currentSelection].id;
                            window.location.href = `?id=<?php echo $id; ?>&adresse=${selectedId}`;
                        }
                    } else if (e.key === 'Escape') {
                        autocompleteResults.innerHTML = '';
                        currentSelection = -1;
                    }
                });
                
                // Close autocomplete when clicking outside
                document.addEventListener('click', function(e) {
                    if (e.target !== searchInput && e.target !== autocompleteResults) {
                        autocompleteResults.innerHTML = '';
                        currentSelection = -1;
                    }
                });
                
                // Fetch autocomplete results from server
                function fetchAutocompleteResults(term) {
                    // Create a new XMLHttpRequest instead of using fetch API for better compatibility
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', `?id=<?php echo $id; ?>&action=autocomplete&term=${encodeURIComponent(term)}`, true);
                    
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            // Set the HTML content directly
                            autocompleteResults.innerHTML = xhr.responseText;
                            
                            // Reset current selection
                            currentSelection = -1;
                            
                            // Add event listeners to all autocomplete items
                            const items = autocompleteResults.querySelectorAll('.autocomplete-item');
                            addressData = [];
                            
                            items.forEach((item, index) => {
                                // Store the ID
                                addressData.push({
                                    id: item.getAttribute('data-id')
                                });
                                
                                // Add click handler
                                item.addEventListener('click', function() {
                                    const itemId = this.getAttribute('data-id');
                                    window.location.href = `?id=<?php echo $id; ?>&adresse=${itemId}`;
                                });
                                
                                // Add mouseover handler
                                item.addEventListener('mouseover', function() {
                                    removeActive();
                                    currentSelection = index;
                                    this.classList.add('autocomplete-active');
                                });
                            });
                        }
                    };
                    
                    xhr.send();
                }
                
                // Set active item in autocomplete list
                function setActiveItem() {
                    removeActive();
                    const items = autocompleteResults.querySelectorAll('.autocomplete-item');
                    
                    if (currentSelection >= items.length) currentSelection = 0;
                    if (currentSelection < 0) currentSelection = items.length - 1;
                    
                    if (items[currentSelection]) {
                        items[currentSelection].classList.add('autocomplete-active');
                        items[currentSelection].scrollIntoView({ block: 'nearest' });
                    }
                }
                
                // Remove active class from all items
                function removeActive() {
                    const items = autocompleteResults.querySelectorAll('.autocomplete-item');
                    for (let i = 0; i < items.length; i++) {
                        items[i].classList.remove('autocomplete-active');
                    }
                }
                
                // Debounce function to limit API calls
                function debounce(func, delay) {
                    let timeout;
                    return function() {
                        const context = this;
                        const args = arguments;
                        clearTimeout(timeout);
                        timeout = setTimeout(() => func.apply(context, args), delay);
                    };
                }
            });
        </script>
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