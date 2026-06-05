<?php
@session_start();
$s_id=session_id();
include("../includes/connect.php");
include("../includes/online.php");

echo "<h1>Starting Historic Price Update for Samlevare</h1>";

$max_iterations = 10;
$iteration = 0;
$total_changes = 0;

while ($iteration < $max_iterations) {
    $iteration++;
    $changes_in_this_iteration = 0;
    
    echo "<h3>--- Iteration $iteration ---</h3>";
    
    // Select all items that are samlevare
    $q = db_select("SELECT id, varenr, beskrivelse, kostpris, retail_price FROM varer WHERE samlevare='on'", __FILE__ . " linje " . __LINE__);
    
    while ($parent = db_fetch_array($q)) {
        $parent_id = $parent['id'];
        $old_cost = (float)$parent['kostpris'];
        $old_retail = (float)$parent['retail_price'];
        
        $new_cost = 0;
        $new_retail = 0;
        
        // Sum up the cost of its children
        $child_q = db_select("SELECT vare_id, antal FROM styklister WHERE indgaar_i='$parent_id'", __FILE__ . " linje " . __LINE__);
        while ($child_link = db_fetch_array($child_q)) {
            $child_id = $child_link['vare_id'];
            $antal = (float)$child_link['antal'];
            
            $child_data_q = db_select("SELECT kostpris, retail_price FROM varer WHERE id='$child_id'", __FILE__ . " linje " . __LINE__);
            if ($child_data = db_fetch_array($child_data_q)) {
                $new_cost += ((float)$child_data['kostpris'] * $antal);
                $new_retail += ((float)$child_data['retail_price'] * $antal);
            }
        }
        
        $new_cost = round($new_cost, 4);
        $new_retail = round($new_retail, 4);
        $old_cost = round($old_cost, 4);
        $old_retail = round($old_retail, 4);

        if ($new_cost != $old_cost || $new_retail != $old_retail) {
            echo "Updated Parent ID $parent_id ({$parent['varenr']} - {$parent['beskrivelse']})<br>";
            echo "Kostpris: $old_cost -> $new_cost <br>";
            echo "Retail: $old_retail -> $new_retail <br><br>";
            
            db_modify("UPDATE varer SET kostpris='$new_cost', retail_price='$new_retail' WHERE id='$parent_id'", __FILE__ . " linje " . __LINE__);
            
            $changes_in_this_iteration++;
            $total_changes++;
        }
    }
    
    if ($changes_in_this_iteration == 0) {
        echo "No changes in iteration $iteration. Price propagation complete.<br>";
        break;
    }
}

echo "<h2>Finished! Total updates made: $total_changes</h2>";
?>
