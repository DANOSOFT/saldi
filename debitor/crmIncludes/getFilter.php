<?php

function get_filter() {
    global $ansat_id;
    global $vismenu;
    $select = "";

    // Start the OR condition group
    $conditions = array();
    $sql = "";

    switch ($vismenu) {
        case 'all': break;
        case '': break;
        case 'dine':
            $sql = "A.kontoansvarlig='$ansat_id'";
            break;
        
        default:
            # a specific tab has been opened, fetched and transformed into a query
            $query = db_select(
                "SELECT box2 FROM grupper WHERE id=$vismenu",
                __FILE__ . " linje " . __LINE__
            );
            
            # Create a list of ids
            $box2 = explode("\t", db_fetch_array($query)["box2"]);
            # Convert to SQL
            $box2 = array_map(function($item) {
                return "OR A.status = '$item' ";
            }, $box2);
            # Implode to create a single consistant string
            $box2 = implode("", $box2);
            # Trim leading or
            $box2 = ltrim($box2, "OR ");

            if (!empty($box2)) {
                $select = "($box2)";
            }

            break;
    }

    // Return or echo the final filter string
    return $select != "" ? $select : "1=1";
}
