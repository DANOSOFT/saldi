<?php
function get_user_statuses($vismenu = null) {
    // Default to 'all' if no specific menu selected
    $vismenu = $vismenu ?? $_GET['vismenu'] ?? 'all';

    // Special cases for 'misc' and 'all'
    if (in_array($vismenu, ['misc', 'all'])) {
        return null;
    }

    // Try to fetch statuses from grupper table
    $query = $vismenu && is_numeric($vismenu) 
        ? "SELECT box2 FROM grupper WHERE id = '$vismenu' ORDER BY kodenr DESC LIMIT 1"
        : "SELECT id, box2 FROM grupper WHERE art = 'SKN' ORDER BY kodenr LIMIT 1";

    $result = db_select($query, __FILE__ . " linje " . __LINE__);
    $row = db_fetch_array($result);

    // If no specific statuses found, return null
    if (!$row) {
        return null;
    }

    // Return array of statuses or update vismenu
    return [
        'statuses' => explode("\t", $row['box2']),
        'vismenu' => $row['id'] ?? $vismenu
    ];
}

function display_top_buttons($current_vismenu = null) {
    global $ansat_id;

    echo "<div style='display: flex;'>";
    echo "<div style='height: 18px; width: 20px; border-color: #001; border-width: 0 0 2px 0; border-style: solid; box-sizing: border-box;'></div>";

    $button_options = [
        'all' => 'Alle',
        'custom_groups' => db_select("SELECT * FROM grupper WHERE art='SKN' ORDER BY kodenr", __FILE__ . " linje " . __LINE__),
    ];
    
    if (isset($ansat_id)) {
        $button_options['dine'] = 'Dine noter';
    }

    foreach ($button_options as $key => $option) {
        if ($key === 'custom_groups') {
            while ($r = db_fetch_array($option)) {
                render_top_button($r['id'], $r['box1'], $current_vismenu);
            }
        } else {
            render_top_button($key, $option, $current_vismenu);
        }
    }

    echo "<div style='height: 18px; width: 80px; border-color: #001; border-width: 0 0 2px 0; border-style: solid; box-sizing: border-box;'></div>";
    echo "</div>";
}

function render_top_button($id, $label, $current_vismenu) {
    $is_active = ($id == $current_vismenu);
    $style = "width: 200px; border-radius: 0px; border-color: #001; height: 18px; cursor: pointer;";
    
    if ($is_active) {
        $style .= "border-width: 2px 2px 0px 2px; background-color: #e9e9ed;";
    } else {
        $style .= "border-width: 0 0 2px 0; background-color: #f2f2f2;";
    }

    // Preserve existing GET arguments
    $current_query = $_GET;
    $current_query['vismenu'] = $id;
    $query_string = http_build_query($current_query);

    echo "<a href='?" . $query_string . "'>";
    echo "<button type='button' style=\"$style\">$label</button>";
    echo "</a>";
}

$status_info = get_user_statuses();
$vismenu = $_GET['vismenu'] ?? 'all';
$statuses = $status_info['statuses'] ?? null;

// Use $statuses for filtering in subsequent queries
display_top_buttons($vismenu);
print "<br>";
?>