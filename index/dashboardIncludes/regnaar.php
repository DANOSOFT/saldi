<?php
// Fiscal Year Dropdown with Activation Logic
// Place this in your target file

// Check if a fiscal year has been selected
if (isset($_GET['aktiver_regnaar']) && $_GET['aktiver_regnaar']) {
    $aktiver = $_GET['aktiver_regnaar'];
    $s_id = session_id();
    
    include __DIR__."/../../includes/connect.php";
    // Update the active fiscal year in the online table for the current session
    db_modify("update online set regnskabsaar = '$aktiver' where session_id = '$s_id'", __FILE__ . " linje " . __LINE__);
    
    // Handle special case for revisor users
    if ($revisor) {
        $qtxt = "update revisor set regnskabsaar = '$aktiver' where brugernavn = '$brugernavn' and db_id='$db_id'";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
    } else {
        // Update the user's default fiscal year
        db_modify("update brugere set regnskabsaar = '$aktiver' where id = '$bruger_id'", __FILE__ . " linje " . __LINE__);
    }
    
    // Reload the online.php to update session variables
    include("../includes/online.php");
    
    // Refresh the page to show the newly selected fiscal year
    print "<script>window.location.href = window.location.pathname;</script>";
    exit;
}

// Get all active fiscal years
$regnskabsaar = array();
$query = db_select("SELECT * 
FROM grupper 
WHERE art = 'RA' 
  AND box10 IS DISTINCT FROM 'on'
  AND box5 = 'on' 
ORDER BY box2 DESC, box1 DESC
", __FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
    $regnskabsaar[] = array(
        'kodenr' => $row['kodenr'],
        'beskrivelse' => $row['beskrivelse'],
        'is_active' => ($row['kodenr'] == $regnaar)
    );
}

// Create the dropdown if we have multiple fiscal years
if (count($regnskabsaar) > 1) {
    echo '<div class="fiscal-year-selector">';
    echo '<form id="changeRegnskabsaarForm" method="GET" action="' . $_SERVER['PHP_SELF'] . '">';
    
    // Preserve any other GET parameters
    foreach ($_GET as $key => $value) {
        if ($key != 'aktiver_regnaar') {
            echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
        }
    }
    
    echo '<label for="aktiverDropdown">' . findtekst(778, $sprog_id) . ':</label> ';
    echo '<select name="aktiver_regnaar" id="aktiverDropdown" onchange="confirmYearChange(this)">';
    
    foreach ($regnskabsaar as $year) {
        $selected = $year['is_active'] ? "selected" : "";
        echo '<option value="' . $year['kodenr'] . '" ' . $selected . '>' . $year['beskrivelse'] . '</option>';
    }
    
    echo '</select>';
    echo '</form>';
    echo '</div>';
    
    // Add JavaScript for confirmation
    echo '<script>
    function confirmYearChange(selectElement) {
        var selectedText = selectElement.options[selectElement.selectedIndex].text;
        if (confirm("' . findtekst(1796, $sprog_id) . ' " + selectedText + "?")) {
            document.getElementById("changeRegnskabsaarForm").submit();
        } else {
            // Reset to previously selected value if user cancels
            selectElement.value = "' . $regnaar . '";
        }
    }
    </script>';
}
?>