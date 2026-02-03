<?php

$move_up = isset($_GET['move_up']) ? $_GET['move_up'] : null;
$move_down = isset($_GET['move_down']) ? $_GET['move_down'] : null;
$move_id = isset($_GET['move_id']) ? $_GET['move_id'] : null;  // Use ID instead of bilag/date/pos
$move_bilag = isset($_GET['move_bilag']) ? $_GET['move_bilag'] : null;
$move_date = isset($_GET['move_date']) ? $_GET['move_date'] : null;
$move_pos = isset($_GET['move_pos']) ? $_GET['move_pos'] : null;
$kladde_id_from_get = isset($_GET['kladde_id']) ? $_GET['kladde_id'] : null;

if (($move_up || $move_down) && $move_id && $kladde_id_from_get) {
    $direction = $move_up ? 'up' : 'down';
    moveEntryById($move_id, $direction, $kladde_id_from_get);
    header("Location: kassekladde.php?kladde_id=$kladde_id_from_get&tjek=$kladde_id_from_get");
    exit;
}

function moveEntryById($entry_id, $direction, $kladde_id) {
    $current_q = db_select("SELECT pos FROM kassekladde WHERE id = '$entry_id' AND kladde_id = '$kladde_id'", __FILE__ . " linje " . __LINE__);
    $current_r = db_fetch_array($current_q);
    if (!$current_r) return;
    
    $current_pos = $current_r['pos'];
    
    if ($direction == 'up') {
        $target_pos = $current_pos - 1;
        if ($target_pos < 1) return;
    } else {
        // Get max position in journal
        $max_q = db_select("SELECT MAX(pos) as max_pos FROM kassekladde WHERE kladde_id = '$kladde_id'", __FILE__ . " linje " . __LINE__);
        $max_r = db_fetch_array($max_q);
        $max_pos = $max_r['max_pos'];
        
        $target_pos = $current_pos + 1;
        if ($target_pos > $max_pos) return;
    }
    
    $check_q = db_select("SELECT id FROM kassekladde WHERE kladde_id = '$kladde_id' AND pos = '$target_pos'", __FILE__ . " linje " . __LINE__);
    $check_r = db_fetch_array($check_q);
    
    if ($check_r) {
        $swap1 = "UPDATE kassekladde SET pos = 0 WHERE id = '$entry_id'";
        $swap2 = "UPDATE kassekladde SET pos = '$current_pos' WHERE kladde_id = '$kladde_id' AND pos = '$target_pos'";
        $swap3 = "UPDATE kassekladde SET pos = '$target_pos' WHERE id = '$entry_id'";
        db_modify($swap1, __FILE__ . " linje " . __LINE__);
        db_modify($swap2, __FILE__ . " linje " . __LINE__);
        db_modify($swap3, __FILE__ . " linje " . __LINE__);
    } else {
        db_modify("UPDATE kassekladde SET pos = '$target_pos' WHERE id = '$entry_id'", __FILE__ . " linje " . __LINE__);
    }
}

function moveEntryByBilag($bilag, $date, $current_pos, $direction, $kladde_id) {
    $q = db_select("SELECT id FROM kassekladde WHERE kladde_id = '$kladde_id' AND bilag = '$bilag' AND transdate = '$date' AND pos = '$current_pos'", __FILE__ . " linje " . __LINE__);
    $r = db_fetch_array($q);
    if ($r) {
        moveEntryById($r['id'], $direction, $kladde_id);
    }
}

function initializePositions($kladde_id) {
    $dup_check = db_select("SELECT pos, COUNT(*) as cnt FROM kassekladde WHERE kladde_id = '$kladde_id' AND pos > 0 GROUP BY pos HAVING COUNT(*) > 1 LIMIT 1", __FILE__ . " linje " . __LINE__);
    if (db_fetch_array($dup_check)) {
        renumberPositions($kladde_id, 'bilag, transdate, id');
        return;
    }
    

    $check_q = db_select("SELECT COUNT(*) as cnt FROM kassekladde WHERE kladde_id = '$kladde_id' AND (pos IS NULL OR pos = 0)", __FILE__ . " linje " . __LINE__);
    $check_r = db_fetch_array($check_q);
    
    if ($check_r['cnt'] > 0) {
     
        $max_q = db_select("SELECT COALESCE(MAX(pos), 0) as max_pos FROM kassekladde WHERE kladde_id = '$kladde_id' AND pos > 0", __FILE__ . " linje " . __LINE__);
        $max_r = db_fetch_array($max_q);
        $next_pos = $max_r['max_pos'] + 1;
        
        $qtxt = "SELECT id FROM kassekladde WHERE kladde_id = '$kladde_id' AND (pos IS NULL OR pos = 0) ORDER BY bilag, transdate, id";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        while ($row = db_fetch_array($q)) {
            db_modify("UPDATE kassekladde SET pos = '$next_pos' WHERE id = '{$row['id']}'", __FILE__ . " linje " . __LINE__);
            $next_pos++;
        }
    }
}


function renumberPositions($kladde_id, $sort_order = 'bilag, transdate, pos, id') {
    $qtxt = "SELECT id FROM kassekladde WHERE kladde_id = '$kladde_id' ORDER BY $sort_order";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    
    $position = 1;
    while ($row = db_fetch_array($q)) {
        db_modify("UPDATE kassekladde SET pos = '$position' WHERE id = '{$row['id']}'", __FILE__ . " linje " . __LINE__);
        $position++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['action']) && $input['action'] === 'reorder') {
        $ids = $input['ids'] ?? [];
        $kladde_id = (int)($input['kladde_id'] ?? 0);
        foreach ($ids as $pos => $id) {
            $id = (int)$id;
            db_modify("UPDATE kassekladde SET pos = " . ($pos + 1) . " WHERE id = $id AND kladde_id = $kladde_id", __FILE__ . " linje " . __LINE__);
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

?>
