<?php
// Add modern CSS styling
print "<style>
.product-card {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 8px;
}

.product-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.product-section {
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.input-group {
    margin-bottom: 15px;
}

.input-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #444;
}

.input-group input,
.input-group select,
.input-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.button-group {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 20px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: background-color 0.2s;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.tab-container {
    margin-bottom: 20px;
}

.tab-buttons {
    display: flex;
    gap: 2px;
    margin-bottom: 15px;
}

.tab-button {
    padding: 10px 20px;
    border: none;
    background: #eee;
    cursor: pointer;
}

.tab-button.active {
    background: #007bff;
    color: white;
}

.tab-content {
    display: none;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
}

.tab-content.active {
    display: block;
}
</style>";
$opener = null;
$varenr = "#KDL23";
/* $noEdit = ; */
$returside = null;
// Update the form structure
print "<div class='product-card'>";
print "<form name='varekort' action='varekort.php?opener=$opener' method='post'>";

// Product header section
print "<div class='product-header'>
    <h2> - $varenr</h2>
    <div class='button-group'>
        <button type='submit' class='btn btn-primary' name='saveItem' onclick='javascript:docChange = false;'>
            
        </button>
        <button type='button' class='btn btn-secondary' onclick='window.location=\"$returside\"'>
            
        </button>
    </div>
</div>";

// Tab navigation
print "<div class='tab-container'>
    <div class='tab-buttons'>
        <button type='button' class='tab-button active' onclick='showTab(\"basic\")'>Basic Info</button>
        <button type='button' class='tab-button' onclick='showTab(\"pricing\")'>Pricing</button>
        <button type='button' class='tab-button' onclick='showTab(\"inventory\")'>Inventory</button>
        <button type='button' class='tab-button' onclick='showTab(\"variants\")'>Variants</button>
    </div>";
	$stregkode = "Stregkode";
	$beskrivelse[0] = "Beskrivelse";
// Basic Info Tab
print "<div id='basic' class='tab-content active'>
    <div class='product-grid'>
        <div class='input-group'>
            <label></label>
            <input type='text' name='varenr' value='$varenr'>
        </div>
        <div class='input-group'>
            <label></label>
            <input type='text' name='stregkode' value='$stregkode'>
        </div>
        <div class='input-group'>
            <label>Description</label>
            <textarea name='beskrivelse0'>$beskrivelse[0]</textarea>
        </div>
    </div>
</div>";
$salgspris = 1000;
$kostpris[0] = 225;
$retail_price = 875;
// Pricing Tab
print "<div id='pricing' class='tab-content'>
    <div class='product-grid'>
        <div class='input-group'>
            <label>Sales Price</label>
            <input type='number' step='0.01' name='salgspris' value='$salgspris'>
        </div>
        <div class='input-group'>
            <label>Cost Price</label>
            <input type='number' step='0.01' name='kostpris[0]' value='$kostpris[0]'>
        </div>
        <div class='input-group'>
            <label>Retail Price</label>
            <input type='number' step='0.01' name='retail_price' value='$retail_price'>
        </div>
    </div>
</div>";
$beholdning = 2;
$min_lager = 0;
$max_lager = 10;
$location = "MX20";
// Inventory Tab 
print "<div id='inventory' class='tab-content'>
    <div class='product-grid'>
        <div class='input-group'>
            <label>Stock</label>
            <input type='number' name='beholdning' value='$beholdning'>
        </div>
        <div class='input-group'>
            <label>Min Stock</label>
            <input type='number' name='min_lager' value='$min_lager'>
        </div>
        <div class='input-group'>
            <label>Max Stock</label>
            <input type='number' name='max_lager' value='$max_lager'>
        </div>
        <div class='input-group'>
            <label>Location</label>
            <input type='text' name='location' value='$location'>
        </div>
    </div>
</div>";

// Variants Tab
print "<div id='variants' class='tab-content'>
    <div class='product-section'>";
/* if (count($variantVarerId)) {
    print "<table class='data-table'>
        <thead>
            <tr>
                <th>Variant</th>
                <th>Barcode</th>
                <th>Stock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>";
    for ($x=0; $x<count($variantVarerId); $x++) {
        print "<tr>
            <td>$variantVarerText[$x]</td>
            <td>$variantVarerBarcode[$x]</td>
            <td>$var_beh[$x]</td>
            <td>
                <button type='button' class='btn btn-secondary btn-sm' onclick='editVariant($variantVarerId[$x])'>Edit</button>
                <button type='button' class='btn btn-danger btn-sm' onclick='deleteVariant($variantVarerId[$x])'>Delete</button>
            </td>
        </tr>";
    }
    print "</tbody></table>";
} else { */
    print "<p>No variants defined for this product.</p>
        <button type='button' class='btn btn-primary' onclick='addVariant()'>Add Variant</button>";
/* } */
print "</div></div>";

print "</div>"; // End tab-container
$id = 2;
// Hidden fields
print "<input type='hidden' name='id' value='$id'>
    <input type='hidden' name='oldDescription' value='$beskrivelse[0]'>";

print "</form></div>";

// Add JavaScript for tab functionality
print "<script>
function showTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabId).classList.add('active');
    document.querySelector(`button[onclick=\"showTab('${tabId}')\"]`).classList.add('active');
}

function editVariant(variantId) {
    // Implement variant editing
}

function deleteVariant(variantId) {
    if (confirm('Are you sure you want to delete this variant?')) {
        // Implement variant deletion
    }
}

function addVariant() {
    // Implement variant addition
}
</script>";

// ... (keep existing PHP closing code) ...
?>