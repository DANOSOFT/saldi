<?php

$move_up = isset($_GET['move_up']) ? $_GET['move_up'] : null;
$move_down = isset($_GET['move_down']) ? $_GET['move_down'] : null;
$move_bilag = isset($_GET['move_bilag']) ? $_GET['move_bilag'] : null;
$move_date = isset($_GET['move_date']) ? $_GET['move_date'] : null;
$move_pos = isset($_GET['move_pos']) ? $_GET['move_pos'] : null;
$kladde_id_from_get = isset($_GET['kladde_id']) ? $_GET['kladde_id'] : null;

if (($move_up || $move_down) && $move_bilag && $move_date && $move_pos && $kladde_id_from_get) {
    $direction = $move_up ? 'up' : 'down';
    moveEntryByBilag($move_bilag, $move_date, $move_pos, $direction, $kladde_id_from_get);
    header("Location: kassekladde.php?kladde_id=$kladde_id_from_get&tjek=$kladde_id_from_get");
    exit;
}

function moveEntryByBilag($bilag, $date, $current_pos, $direction, $kladde_id) {
    if ($direction == 'up') {
        $target_pos = $current_pos - 1;
        if ($target_pos < 1) return; 
    } else {
        $target_pos = $current_pos + 1;
    }
    
    $max_pos_query = db_select("SELECT MAX(pos) as max_pos FROM kassekladde WHERE kladde_id = '$kladde_id' AND bilag = '$bilag' AND transdate = '$date'", __FILE__ . " linje " . __LINE__);
    $max_pos_row = db_fetch_array($max_pos_query);
    $max_pos = $max_pos_row['max_pos'];
    
    if ($direction == 'up' && $current_pos <= 1) return;
    if ($direction == 'down' && $current_pos >= $max_pos) return;
    
    $check_qtxt = "SELECT id FROM kassekladde WHERE kladde_id = '$kladde_id' AND bilag = '$bilag' AND transdate = '$date' AND pos = '$target_pos'";
    $check_query = db_select($check_qtxt, __FILE__ . " linje " . __LINE__);
    
    if (!db_fetch_array($check_query)) {
        if ($direction == 'down') {
            db_modify("UPDATE kassekladde SET pos = pos + 1 WHERE kladde_id = '$kladde_id' AND bilag = '$bilag' AND transdate = '$date' AND pos >= '$target_pos'", __FILE__ . " linje " . __LINE__);
        }
        db_modify("UPDATE kassekladde SET pos = '$target_pos' WHERE kladde_id = '$kladde_id' AND bilag = '$bilag' AND transdate = '$date' AND pos = '$current_pos'", __FILE__ . " linje " . __LINE__);
    } else {
        $swap1 = "UPDATE kassekladde SET pos = 0 WHERE kladde_id = '$kladde_id' AND bilag = '$bilag' AND transdate = '$date' AND pos = '$current_pos'";
        $swap2 = "UPDATE kassekladde SET pos = '$current_pos' WHERE kladde_id = '$kladde_id' AND bilag = '$bilag' AND transdate = '$date' AND pos = '$target_pos'";
        $swap3 = "UPDATE kassekladde SET pos = '$target_pos' WHERE kladde_id = '$kladde_id' AND bilag = '$bilag' AND transdate = '$date' AND pos = 0";
        db_modify($swap1, __FILE__ . " linje " . __LINE__);
        db_modify($swap2, __FILE__ . " linje " . __LINE__);
        db_modify($swap3, __FILE__ . " linje " . __LINE__);
    }
}

function initializePositions($kladde_id) {
    db_modify("UPDATE kassekladde SET pos = 0 WHERE kladde_id = '$kladde_id' AND pos IS NULL", __FILE__ . " linje " . __LINE__);
    $qtxt = "SELECT id, bilag, transdate FROM kassekladde WHERE kladde_id = '$kladde_id' ORDER BY bilag, transdate, pos, id";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    
    $current_bilag = null;
    $current_date = null;
    $position = 1;
    
    while ($row = db_fetch_array($q)) {
        if ($row['bilag'] != $current_bilag || $row['transdate'] != $current_date) {
            $position = 1;
            $current_bilag = $row['bilag'];
            $current_date = $row['transdate'];
        }
        
        db_modify("UPDATE kassekladde SET pos = '$position' WHERE id = '{$row['id']}'", __FILE__ . " linje " . __LINE__);
        $position++;
    }
}
?>
