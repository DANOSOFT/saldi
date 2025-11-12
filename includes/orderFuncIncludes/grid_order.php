<?php
//../includes/grid_order.php
/**
 * Extracts values from a specific column in a multi-dimensional array.
 *
 * This function mimics PHP's built-in array_column function for PHP 5.
 *
 * @param array $array The input array.
 * @param mixed $column_key The column key whose values will be extracted.
 * @param mixed|null $index_key (Optional) The column key to use as the index.
 * @return array The resulting array containing extracted values.
 */
function array_column_php5($array, $column_key, $index_key = null) {
    $result = [];
    

    foreach ($array as $row) {
        if (is_array($row)) {
            // Get the value of the column
            $value = isset($row[$column_key]) ? $row[$column_key] : null;

            // If an index key is provided, use it to index the result array
            if ($index_key !== null && isset($row[$index_key])) {
                $result[$row[$index_key]] = $value;
            } else {
                $result[] = $value;
            }
        }
    }

    return $result;
}


/**
 * Default value getter function.
 *
 * Formats numeric values based on the column definition.
 *
 * @param mixed $value The value to format.
 * @param array $row The row data.
 * @param array $column The column definition.
 * @return mixed The formatted value.
 */
function DEFAULT_VALUE_GETTER($value, $row, $column) {
    
    if ($column["type"] == "number") {
         if($column['field'] != 'ordrenr'){ //ensure the decimal doesn't follow the ordrenr
          return dkdecimal($value, $column['decimalPrecision']);
         }
    }

    return $value;
}

/**
 * Default cell renderer function.
 *
 * Wraps a value in an HTML table cell with alignment.
 *
 * @param mixed $value The cell value.
 * @param array $row The row data.
 * @param array $column The column definition (must include 'align').
 * @return string The rendered HTML table cell.
 */
function DEFAULT_CELL_RENDERE($value, $row, $column) {
    
    return "<td align='{$column['align']}'>{$value}</td>";
}

/**
 * Generates a search query condition for a given column and term.
 * This is a grid-compatible version of the legacy udvaelg() function.
 *
 * @param array $column The column definition (must include 'type', 'field', and 'sqlOverride').
 * @param string $term The search term.
 * @return string The generated SQL condition.
 */
function DEFAULT_GENERATE_SEARCH($column, $term) {
    
    $field = $column['sqlOverride'] == '' ? $column['field'] : $column['sqlOverride'];
    $originalTerm = $term;
    $term = db_escape_string(trim($term, "'"));
    
    // Handle empty terms
    if ($term === '' && $term !== '0') {
        return "1=1";
    }
    
    mb_internal_encoding('UTF-8');
    $term = strtolower($term);
    
    // Determine the art (type) for udvaelg logic
    $art = '';
    switch ($column["type"]) {
        case 'number':
            $art = 'BELOB';
            break;
        case 'date':
            $art = 'DATO';
            break;
        case 'text':
            $art = 'TEXT';
            break;
        case 'dropdown':
            $art = 'TEXT';
            break;
        default:
            $art = '';
    }
    
    // Replace commas and semicolons with colons for range syntax
    if ($art && $art != 'BELOB') {
        $term = str_replace(",", ":", $term);
    }
    $term = str_replace(";", ":", $term);
    
    // Special handling for single BELOB (number) values - create narrow range
    if ($art == 'BELOB' && strpos($term, ':') === false) {
        $numValue = usdecimal($term);
        $tmp1 = $numValue - 0.005;
        $tmp2 = $numValue + 0.004;
        $term = number_format($tmp1, 3, ',', '') . ":" . number_format($tmp2, 3, ',', '');
    }
    
    // Handle RANGE searches (contains colon)
    if (strpos($term, ':') !== false && ($art != 'TID' && $art != 'TEXT')) { 
        list($tmp1, $tmp2) = explode(":", $term, 2);
        
        if ($art == "DATO") {
            $tmp1 = usdate($tmp1);
            $tmp2 = usdate($tmp2);
            return "({$field} >= '$tmp1' AND {$field} <= '$tmp2')";
            
        } elseif ($art == "BELOB") {
            $tmp1 = usdecimal($tmp1);
            $tmp2 = usdecimal($tmp2);
            $precision = $column['decimalPrecision'];
            return "(round({$field}::numeric, {$precision}) >= $tmp1 AND round({$field}::numeric, {$precision}) <= $tmp2)";
            
        } elseif ($art == "NR") {
            $tmp1 = round($tmp1 * 1, 2);
            $tmp2 = round($tmp2 * 1, 2);
            return "({$field} >= '$tmp1' AND {$field} <= '$tmp2')";
            
        } else {
            // Generic range for other types
            return "({$field} >= '$tmp1' AND {$field} <= '$tmp2')";
        }
    }
    
    // Handle SINGLE VALUE searches
    else {
        // Special handling for TIME fields
        if ($art == "TID") {
            if (strpos($term, ':') === false) {
                $term = floatval($term);
                $term = str_replace(".", ":", $term);
                if (strpos($term, ':') === false) {
                    $term = $term . ":";
                }
            }
            return "({$field}::text LIKE '$term%')";
        }
        
        // Convert dates
        elseif ($art == "DATO") {
            $term = usdate($term);
            return "({$field} = '$term')";
        }
        
        // Generic text search (no art specified)
        if (!$art) {
            $term = str_replace("*", "%", $term);
            $term = db_escape_string($term);
            return "(lower({$field}) LIKE '$term')";
        }
        
        // TEXT type with wildcard support
        elseif ($art == "TEXT") {
            if (strpos($term, '*') !== false) {
                // Wildcard search
                $term = str_replace('*', '%', $term);
                return "(({$field} LIKE '$term' OR lower({$field}) LIKE '" . mb_strtolower($term) . "' OR upper({$field}) LIKE '" . mb_strtoupper($term) . "'))";
            } else {
                // Exact and partial match search
                $termLower = mb_strtolower($term);
                $termUpper = mb_strtoupper($term);
                return "(({$field} = '$term' OR lower({$field}) LIKE '$termLower' OR upper({$field}) LIKE '$termUpper' OR lower({$field}) LIKE '%$termLower%' OR upper({$field}) LIKE '%$termUpper%'))";
            }
        }
        
        // Default: prefix match with type cast
        else {
            return "({$field}::text LIKE '$term%')";
        }
    }
}


/**
 * Default column properties for a data grid.
 *
 * This array defines the default settings for a column, which can be overridden per column.
 * These properties control sorting, searching, rendering, and alignment.
 *
 * @var array $defaultValues
 */
$defaultValues = [
    /**
     * The field name that gets picked from the provided SQL query.
     * Use the '{columnName} AS {field}' syntax.
     *
     * @var string
     */
    'field' => '',

    /**
     * The field name displayed to the end user.
     *
     * @var string
     */
    'headerName' => '',

    /**
     * The datatype of the column.
     * Supported values: 'text', 'number', 'date'.
     * Determines the type of filtering and rendering applied.
     *
     * @var string
     */
    'type' => 'text',

    /**
     * The width of the column in fractions.
     * This is used for flexible column sizing.
     *
     * @var string
     */
    'width' => '1',

    /**
     * A description of the data in the column displayed to the user.
     *
     * @var string
     */
    'description' => '',

    /**
     * Defines if the column can be sorted by the user.
     *
     * @var bool
     */
    'sortable' => true,

    /**
     * Defines if the column can be searched by the user.
     *
     * @var bool
     */
    'searchable' => true,

    /**
     * A function that retrieves the display value for a cell.
     * It takes the value, row, and column properties as parameters.
     *
     * @var callable
     */
    'valueGetter' => function ($value, $row, $column) {
        return DEFAULT_VALUE_GETTER($value, $row, $column);
    },

    /**
     * A function that renders the value in the cell.
     * Can be used to format or transform the value before display.
     *
     * @var callable
     */
    'render' => function ($value, $row, $column) {
        return DEFAULT_CELL_RENDERE($value, $row, $column); 
    },

    /**
     * A function that generates the search condition used in the WHERE SQL statement.
     * It takes the column and search term as parameters.
     *
     * @var callable
     */
    'generateSearch' => function ($column, $term) {
        return DEFAULT_GENERATE_SEARCH($column, $term);
    },

    /**
     * A different column identifier to use for search and sorting.
     * Useful if the column is a generated or calculated field.
     *
     * @var string
     */
    'sqlOverride' => '',

    /**
     * The alignment of the column ('left', 'center', 'right').
     *
     * @var string
     */
    'align' => 'left',

    /**
     * Determines if this column is the default sorting column.
     *
     * @var bool
     */
    'defaultSort' => false,

    /**
     * The direction of the default sort.
     * Possible values: 'asc' or 'desc'.
     *
     * @var string
     */
    'defaultSortDirection' => "asc",

    /**
     * Determines if the column is hidden by default.
     *
     * @var bool
     */
    'hidden' => false,

    /**
     * The decimal precision for number-based columns.
     * Used in rendering and searching functions.
     *
     * @var int
     */
    'decimalPrecision' => 2,
];

/**
 * Creates and renders a data grid with dynamic columns, filtering, searching, and sorting.
 *
 * This function normalizes column properties, fetches stored configurations,
 * processes search inputs, builds and executes queries, and renders the data grid.
 *
 * @param string $id The unique identifier for the data grid.
 * @param array $grid_data Configuration data for the grid, including columns, filters, and styling.
 * @return array The fetched rows from the database for display.
 */
function create_datagrid($id, $grid_data) {
    global $defaultValues;
    global $bruger_id;
    global $db;
    // Performance logging for grid creation
    $grid_start_time = microtime(true);
    $log_file = "../../temp/$db/vareliste_performance.log";
    
    function log_grid_performance($message, $start_time = null) {
        global $log_file;
        
        // Safety check - if log_file is still empty, skip logging
        if (empty($log_file)) {
            return microtime(true);
        }
        
        $current_time = microtime(true);
        if ($start_time) {
            $elapsed = round(($current_time - $start_time) * 1000, 2);
            $message .= " (took {$elapsed}ms)";
        }
        $timestamp = date('Y-m-d H:i:s');
        
        // Use error suppression to prevent fatal errors
        @file_put_contents($log_file, "[$timestamp] GRID: $message\n", FILE_APPEND | LOCK_EX);
        return $current_time;
    }

    log_grid_performance("Grid creation started");

    // Normalize columns with default values
    $normalize_start = microtime(true);
    $columns = normalize_columns($grid_data['columns'], $defaultValues);
    log_grid_performance("Column normalization", $normalize_start);
    log_grid_performance("Column normalization", $normalize_start);
    
    $filter_start = microtime(true);
    $columns_filtered = array_filter($columns, function ($item) {
        return !$item["hidden"];
    });

    // Retrieve filters
    $filters = $grid_data["filters"];
    log_grid_performance("Column filtering and filters setup", $filter_start);

    // Fetch stored grid setup from the database
    $fetch_setup_start = microtime(true);
    list($columns_setup, $search_setup, $filter_setup) = fetch_grid_setup(
        $id,
        $columns_filtered,
        if_isset($_GET["search"][$id], array()),
        $filters
    );
    log_grid_performance("Fetch grid setup from database", $fetch_setup_start);
    log_grid_performance("Fetch grid setup from database", $fetch_setup_start);
    
    $setup_processing_start = microtime(true);
    $columns_setup = json_decode($columns_setup, true);
    $columns_updated = fill_missing_values($columns_setup, $columns);

    // Process search input
    $search_setup = json_decode($search_setup, true);
    $searchTerms = if_isset($_GET["search"][$id], $search_setup);
    $search_json  = db_escape_string(json_encode($searchTerms));
    log_grid_performance("JSON processing and search setup", $setup_processing_start);

    // Retrieve stored grid settings from the database
    $grid_settings_start = microtime(true);
    $q = "SELECT search_setup, rowcount, \"offset\", \"sort\" FROM datatables WHERE user_id = $bruger_id AND tabel_id='$id'";
    $r = db_fetch_array(db_select($q, __FILE__ . " line " . __LINE__));
    log_grid_performance("Grid settings query", $grid_settings_start);

    // Determine sorting, row count, and offset
    $sort = if_isset($_GET["sort"][$id], if_isset($r["sort"], get_default_sort($columns_updated)));
    $selectedrowcount = if_isset($_GET["rowcount"][$id], if_isset($r["rowcount"], 100));

    // Use isset to avoid zero triggering if
    $offset =   isset($_GET["offset"][$id]) ? $_GET["offset"][$id] : (
                isset($r["offset"]) ? $r["offset"] : 
                0
                );

    // Reset scroll position if search parameters changed
    if (isset($_GET["search"][$id]) && 
        ($r["search_setup"] != $search_json || $r["offset"] != $offset || $r["sort"] != $sort || $r["rowcount"] != $selectedrowcount)) {
        print "
        <script>
            var scrollKey = 'scrollpos-datatable-$id';
            localStorage.setItem(scrollKey, 0);
        </script>";
    }

    // Update search configuration in the database
    if (isset($_GET["search"][$id])) {
        $qtxt = "UPDATE datatables SET search_setup='".($search_json)."', rowcount=$selectedrowcount, \"offset\"=$offset, \"sort\"='".str_replace("'", "''", $sort)."' WHERE user_id = $bruger_id AND tabel_id='$id'";
        db_modify($qtxt, __FILE__ . " line " . __LINE__);
    }

    // Process filters
    $filters_setup = json_decode($filter_setup, true);
    $filters_updated = updateCheckedValues($filters, $filters_setup);

    // Get additional configurations
    $rowStyleFn = if_isset($grid_data['rowStyle'], null);
    $metaColumnFn = if_isset($grid_data['metaColumn'], null);
    $totalWidth = calculate_total_width($columns_updated);
    $menu = if_isset($_GET["menu"][$id], "main"); // ['main', 'kolonner', 'filtre']

    $rows = array();
    $query = "";

    // Handle different menu options
    if ($menu == "main") {
        // Build and execute the main query
        $query_build_start = microtime(true);
        $query = build_query($id, $grid_data, $columns_updated, $filters_updated, $searchTerms, $sort, $selectedrowcount, $offset);
        log_grid_performance("Query building", $query_build_start);
        
        // Log the actual query being executed
        $query_length = strlen($query);
        log_grid_performance("Built query with length: {$query_length} characters");
        
        print "<!-- \n DEBUG QUERY \n\n$query -->";
        
        $main_query_start = microtime(true);
        $sqlquery = db_select($query, __FILE__ . " line " . __LINE__);
        log_grid_performance("Main SQL query execution", $main_query_start);
        
        $fetch_rows_start = microtime(true);
        $rows = fetch_rows_from_query($sqlquery);
        log_grid_performance("Fetching rows from query", $fetch_rows_start);

        // Fetch total row count
        $count_query_start = microtime(true);
        $countQuery = build_count_query($grid_data, $columns_updated, $filters_updated, $searchTerms, $sort);
        $count_query_length = strlen($countQuery);
        log_grid_performance("Built count query with length: {$count_query_length} characters");
        
        $countResult = db_select($countQuery, __FILE__ . " line " . __LINE__);
        $totalItems = db_fetch_array($countResult)["total_items"];
        $totalRows = count($rows);
        log_grid_performance("Count query execution", $count_query_start);

        // Render the data grid
        $render_start = microtime(true);
        render_datagrid(
            $id,
            $columns_updated, 
            $rows, 
            $totalWidth, 
            $searchTerms, 
            $rowStyleFn, 
            $metaColumnFn,
            $query, 
            $sort,
            $selectedrowcount,
            $totalItems,
            $totalRows,
            $offset,
            $menu
        );
        log_grid_performance("Grid rendering", $render_start);

        // Render additional styles and scripts
        $styles_start = microtime(true);
        render_search_style();
        render_dropdown_style();
        render_pagination_script($id);
        render_sort_script($id);
        log_grid_performance("Rendering styles and scripts", $styles_start);

    } else if ($menu == "kolonner") {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Save column configuration
            save_column_setup($id);
            // Refetch column setup
            list($columns_setup, $search_setup, $filter_setup) = fetch_grid_setup(
                $id,
                $columns_filtered,
                if_isset($_GET["search"][$id], array()),
                $filters
            );
            $columns_setup = json_decode($columns_setup, true);
            $filters_setup = json_decode($filter_setup, true);
            $filters_updated = updateCheckedValues($filters, $filters_setup);
        }

        // Render column setup interface
        render_column_setup($id, $columns_setup, $columns);
        render_column_edit_style();
        render_move_script();

    } else if ($menu == "filtre") {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Save filter configuration
            save_filter_setup($id);
            // Refetch filter setup
            list($columns_setup, $search_setup, $filter_setup) = fetch_grid_setup(
                $id,
                $columns_filtered,
                if_isset($_GET["search"][$id], array()),
                $filters
            );
            $columns_setup = json_decode($columns_setup, true);
            $filters_setup = json_decode($filter_setup, true);
            $filters_updated = updateCheckedValues($filters, $filters_setup);
        }

        // Render filter setup interface
        render_filter_setup($id, $filters_updated, $filters);
        render_filter_edit_style();
        render_move_script();
    }

    // Render dropdown script for interactions
    $dropdown_start = microtime(true);
    render_dropdown_script($id, $query);
    log_grid_performance("Dropdown script rendering", $dropdown_start);

    // Final grid performance log
    $total_grid_time = microtime(true) - $grid_start_time;
    log_grid_performance("TOTAL grid creation completed in " . round($total_grid_time * 1000, 2) . "ms");

    return $rows;
}


/**
 * Fetches or initializes the grid setup for a specific table and user.
 *
 * This function retrieves the column setup, search setup, and filter setup 
 * for a given table ID (`$id`) and user (`$bruger_id`). If no existing 
 * configuration is found, it initializes and saves a new setup based on 
 * the provided parameters.
 *
 * @param string $id The table ID for which the grid setup is fetched.
 * @param array $columns_filtered An array of filtered column configurations.
 * @param array $search_setup An array defining the search setup.
 * @param array $filters An array containing filter configurations.
 * 
 * @global int $bruger_id The current user's ID.
 * 
 * @return array|null Returns an associative array with `column_setup`, 
 *                    `search_setup`, and `filter_setup` if found. 
 *                    Returns `null` if the query fails.
 */
function fetch_grid_setup($id, $columns_filtered, $search_setup, $filters) {
    global $bruger_id;

    // Query to retrieve the existing grid setup for the user and table
    $q = "SELECT column_setup, search_setup, filter_setup 
          FROM datatables 
          WHERE user_id = $bruger_id AND tabel_id='$id'";
    $r = db_fetch_array(db_select($q, __FILE__ . " line " . __LINE__));

    // If no existing setup is found, initialize and save a new one
    if (!$r) {
        // Prepare column setup, retaining only necessary fields
        $columns_save = array_map(
            function ($column) {
                return [
                    'field' => if_isset($column['field'], null),
                    'headerName' => if_isset($column['headerName'], null),
                    'description' => if_isset($column['description'], null),
                    'width' => if_isset($column['width'], null),
                    'align' => if_isset($column['align'], null),
                ];
            },
            $columns_filtered
        );

        // Encode configurations as JSON for storage
        $columns_json = db_escape_string(json_encode($columns_save));
        $filters_json = db_escape_string(json_encode($filters));
        $search_json  = db_escape_string(json_encode($search_setup));

        // Insert the new grid setup into the database
        db_modify("INSERT INTO datatables (user_id, column_setup, search_setup, filter_setup, tabel_id) 
                   VALUES ($bruger_id, '$columns_json', '$search_json', '$filters_json', '$id')", 
                   __FILE__ . " line " . __LINE__);

        // Re-fetch the newly inserted setup
        $q = "SELECT column_setup, search_setup, filter_setup 
              FROM datatables 
              WHERE user_id = $bruger_id AND tabel_id='$id'";
        $r = db_fetch_array(db_select($q, __FILE__ . " line " . __LINE__));
    }

    return $r;
}


/**
 * Fills missing values in the first array using values from the second array based on matching 'field' values.
 *
 * This function iterates over each item in the first array and looks for a matching 'field' in the second array.
 * When a match is found, it fills in any missing or empty values in the first array item with the corresponding values
 * from the second array item.
 *
 * @param array $firstArray The first array containing items that may have missing values.
 * @param array $secondArray The second array providing default values for missing fields.
 * @return array The first array with missing values filled from the second array.
 */
function fill_missing_values($firstArray, $secondArray) {
    foreach ($firstArray as &$firstItem) {
        foreach ($secondArray as $secondItem) {
            if ($firstItem['field'] === $secondItem['field']) {
                foreach ($secondItem as $key => $value) {
                    if (!isset($firstItem[$key]) || $firstItem[$key] === "") {
                        $firstItem[$key] = $value;
                    }
                }
                break;
            }
        }
    }
    unset($firstItem);
    return $firstArray;
}

/**
 * Updates the 'checked' values for options in the first array based on the second array.
 *
 * This function loops through the filters in the first array and updates the 'checked' state of each option
 * based on the values found in the second array, where the key corresponds to the filter's name.
 *
 * @param array $firstArray The first array containing filters with options to be updated.
 * @param array $secondArray The second array providing the updates for each filter.
 * @return array The updated first array with the 'checked' values for options updated.
 */
function updateCheckedValues(array $firstArray, array $secondArray) {
    foreach ($firstArray as &$filter) {
        $filterName = $filter['filterName'];
        if (isset($secondArray[$filterName])) {
            $updatesForFilter = $secondArray[$filterName];
            foreach ($filter['options'] as &$option) {
                $option['checked'] = isset($updatesForFilter[$option['name']]) ? $updatesForFilter[$option['name']] : '';
            }
        } else {
            foreach ($filter['options'] as &$option) {
                $option['checked'] = '';
            }
        }
    }
    return $firstArray;
}

/**
 * Normalizes an array of columns by ensuring that all columns have values for all specified keys.
 *
 * This function checks if each column in the array contains all the keys from the default values array.
 * If a column is missing a key, it assigns the corresponding default value.
 *
 * @param array $columns The array of columns to be normalized.
 * @param array $defaultValues The default values to be used for missing keys in each column.
 * @return array The normalized array of columns with default values for missing keys.
 */
function normalize_columns($columns, $defaultValues) {
    $normalizedColumns = [];
    foreach ($columns as $column) {
        foreach ($defaultValues as $key => $defaultValue) {
            if (!isset($column[$key])) {
                $column[$key] = $defaultValue;
            }
        }
        $normalizedColumns[] = $column;
    }
    return $normalizedColumns;
}


/**
 * Gets the default sort key from the columns array.
 * If no default sort key is set, the first sortable column is returned.
 *
 * @param array $columns An array of column definitions, each containing a field, sortable status, and default sort direction.
 * @return string|null The default sort key in the format "field direction", or null if no sortable column is found.
 */
function get_default_sort($columns) {
    $default = null;
    

    foreach ($columns as $column) {
        if ($column['sortable'] && ($default == null || $column['defaultSort'])) {
            $default = $column['field'] ." ". $column["defaultSortDirection"];
        }
    }

    return $default;
}

/**
 * Builds a query string based on filters, search terms, sorting, pagination, and other parameters.
 *
 * @param string $id The ID used to reference the query.
 * @param array $grid_data An array containing the base query and other data for constructing the query.
 * @param array $columns An array of column definitions used for sorting, searching, and filtering.
 * @param array $filters An array of filter definitions.
 * @param array $searchTerms (optional) An associative array of search terms where the key is the column field name and the value is the search term.
 * @param string $sort The sorting condition (e.g., "field ASC" or "field DESC").
 * @param int $rowCount The number of rows to return.
 * @param int $offset The starting point for the result set (used for pagination).
 * @return string The final SQL query with all conditions applied.
 */
function build_query($id, $grid_data, $columns, $filters, $searchTerms = [], $sort, $rowCount, $offset) {
    $query = $grid_data['query'];
    

    $filterstring = "";
    $i=0;
    foreach ($filters as $filter) {
        $i++;
        $tmp = "(";
        foreach ($filter["options"] as $filterItem) {
            if ($filterItem["checked"] == "checked" && $filterItem["sqlOn"] != "") {
                $tmp .= $filterItem["sqlOn"];
                $tmp .= " " .$filter['joinOperator']. " ";
            }
            if ($filterItem["checked"] == "" && $filterItem["sqlOff"] != "") {
                $tmp .= $filterItem["sqlOff"];
                $tmp .= " " .$filter['joinOperator']. " ";
            }
        }
        $tmp = rtrim($tmp, " " .$filter['joinOperator']. " ");
        $tmp .= ")";
        if ($tmp != "()") {
            $filterstring .= $tmp;
            $filterstring .= " AND ";
        }
    }
    $filterstring = rtrim($filterstring, " AND ");
    if ($filterstring == "") {
        $filterstring = "1=1";
    }

    // Add search condition if searchTerm is provided
    if (!empty($searchTerms)) {
        // Filter columns that are searchable
        $searchableColumns = array_filter($columns, function ($col) {
            return if_isset($col['searchable'], false);
        });
        

        // Build the search condition
        $searchConditions = [];
        foreach ($searchableColumns as $column) {
            if (!empty($searchTerms[$column['field']]) || $searchTerms[$column['field']] == 0) {
                $term = addslashes($searchTerms[$column['field']]);
                // Convert both the column value and the search term to lowercase
                if ($term) {
                    $searchConditions[] = $column['generateSearch']($column, $term);
                }
            }
        }

        // Combine search conditions
        $search = !empty($searchConditions) ? "(" . implode(" AND ", $searchConditions) . ")" : "1=1";
        $query = str_replace("{{WHERE}}", $filterstring." AND ".$search, $query);
    } else {
        // Default WHERE clause if no search term
        $query = str_replace("{{WHERE}}", $filterstring == "" ? "1=1" : $filterstring, $query);
    }

    # Is always set due to get_default_sort
    $query = str_replace("{{SORT}}", $sort, $query);

    $query .= " LIMIT $rowCount OFFSET $offset"; // Add limit for performance

    return $query;
}


/**
 * Builds a count query to count the total number of rows based on filters, search terms, and other parameters.
 *
 * @param array $grid_data An array containing the base query and other data for constructing the query.
 * @param array $columns An array of column definitions used for sorting, searching, and filtering.
 * @param array $filters An array of filter definitions.
 * @param array $searchTerms (optional) An associative array of search terms where the key is the column field name and the value is the search term.
 * @param string $sort The sorting condition (although not needed for counting, it's included for consistency).
 * @return string The final count query to return the total number of rows.
 */
function build_count_query($grid_data, $columns, $filters, $searchTerms = [], $sort) {
    // Start with the original query and modify it for counting rows
    $query = $grid_data['query'];

    $filterstring = "";
    $i=0;
    foreach ($filters as $filter) {
        $i++;
        $tmp = "(";
        foreach ($filter["options"] as $filterItem) {
            if ($filterItem["checked"] == "checked" && $filterItem["sqlOn"] != "") {
                $tmp .= $filterItem["sqlOn"];
                $tmp .= " " .$filter['joinOperator']. " ";
            }
            if ($filterItem["checked"] == "" && $filterItem["sqlOff"] != "") {
                $tmp .= $filterItem["sqlOff"];
                $tmp .= " " .$filter['joinOperator']. " ";
            }
        }
        $tmp = rtrim($tmp, " " .$filter['joinOperator']. " ");
        $tmp .= ")";
        if ($tmp != "()") {
            $filterstring .= $tmp;
            $filterstring .= " AND ";
        }
    }
    $filterstring = rtrim($filterstring, " AND ");
    if ($filterstring == "") {
        $filterstring = "1=1";
    }

    // Add search condition if searchTerm is provided
    if (!empty($searchTerms)) {
        // Filter columns that are searchable
        $searchableColumns = array_filter($columns, function ($col) {
            return if_isset($col['searchable'], false);
        });
        
        // Build the search condition
        $searchConditions = [];
        foreach ($searchableColumns as $column) {
            if (!empty($searchTerms[$column['field']]) || $searchTerms[$column['field']] == 0) {
                $term = addslashes($searchTerms[$column['field']]);
                if ($term) {
                    $searchConditions[] = $column['generateSearch']($column, $term);
                }
            }
        }

        // Combine search conditions
        $search = !empty($searchConditions) ? "(" . implode(" AND ", $searchConditions) . ")" : "1=1";
        $query = str_replace("{{WHERE}}", $filterstring." AND ".$search, $query);
    } else {
        // Default WHERE clause if no search term
        $query = str_replace("{{WHERE}}", $filterstring == "" ? "1=1" : $filterstring, $query);
    }

    // Replace sort placeholder with an empty string (count query doesn't need sorting)
    $query = str_replace("{{SORT}}", $sort, $query);

    // Remove the LIMIT clause to count all rows
    $query = preg_replace('/LIMIT \d+/', '', $query);  // Remove LIMIT

    // Add a COUNT wrapper to the query to count the number of rows in the subquery
    $query = "SELECT COUNT(*) as total_items FROM ($query) as subquery";

    return $query;
}


/**
 * Fetches all rows from the provided query and returns them as an array.
 *
 * This function executes a database query and retrieves the result rows one by one,
 * storing each row in an array. Once all rows are fetched, the array is returned.
 *
 * @param string $query The SQL query to execute.
 * 
 * @return array An array of rows fetched from the query result. Each row is an associative array representing a record.
 */
function fetch_rows_from_query($query) {
    $rows = [];
    while ($row = db_fetch_array($query)) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Calculates the total width of a set of columns.
 *
 * This function sums up the 'width' property from each column in the provided array
 * and returns the total width. If the total width is zero, the function returns 1
 * to avoid division by zero in further calculations.
 *
 * @param array $columns An array of column data where each element is an associative array
 *                       containing a 'width' key representing the column's width.
 * 
 * @return int The total width of the columns. If the sum of widths is zero, 1 is returned.
 */
function calculate_total_width($columns) {
    $totalWidth = array_sum(array_column_php5($columns, 'width'));
    return $totalWidth == 0 ? 1 : $totalWidth; // Avoid division by zero
}


/**
 * Renders an HTML datagrid table with pagination, search, and dynamic content.
 *
 * This function generates the full HTML structure for a data table, including the table headers,
 * rows, footer, and necessary form fields for sorting, menu selection, and search functionality. 
 * It utilizes helper functions to render specific parts of the table like headers, rows, and footer.
 * The function also supports dynamic styling for rows and additional metadata columns via callback functions.
 *
 * @param string $id The unique identifier for the table. Used to generate dynamic IDs for the table and form elements.
 * @param array $columns An array of column definitions. Each column typically includes properties such as label and key.
 * @param array $rows An array of data rows to be displayed in the table. Each row is an associative array of column key-value pairs.
 * @param int $totalWidth The total width for the table, typically representing the sum of column widths.
 * @param array $searchTerms An array of search terms used to filter table data. Each term corresponds to a column.
 * @param callable|null $rowStyleFn A callback function to define dynamic styles for each row. Receives a row and returns a style string.
 * @param callable|null $metaColumnFn A callback function to render metadata for each row. Receives a row and returns an HTML string.
 * @param array $query The query parameters used for filtering and sorting the table data. Typically includes search, sort, and pagination data.
 * @param string $sort The current sorting criteria, e.g., the column and direction (e.g., 'column_name ASC').
 * @param int $selectedrowcount The total number of rows selected by the user for some action (e.g., bulk action).
 * @param int $totalItems The total number of items in the data set, used for pagination.
 * @param int $rowCount The number of rows to display per page.
 * @param int $offset The current page offset, which determines which set of rows to display.
 * @param string $menu The current menu option selected in the data grid, used to handle different menu actions.
 *
 * @return void Outputs the full HTML structure of the datagrid, including a table and necessary form fields for interaction.
 */
function render_datagrid($id, $columns, $rows, $totalWidth, $searchTerms, $rowStyleFn, $metaColumnFn, $query, $sort, $selectedrowcount, $totalItems, $rowCount, $offset, $menu) {
    // Start table wrapper and form
    echo <<<HTML
    <div class="datatable-wrapper" id="datatable-wrapper-$id">
        <form method="GET" action="">
            <input type="hidden" name='sort[{$id}]', value='$sort'>
            <input type="hidden" name='menu[{$id}]', value='$menu'>
            <div class="datatable-search-wrapper">
                <table class="datatable" id="datatable-$id" style="width: 100%;">
                    <thead>
HTML;

    // Render table headers
    render_table_headers($columns, $searchTerms, $totalWidth, $id);

    echo <<<HTML
                </thead>
                <tbody>
HTML;

    // Render table rows
    foreach ($rows as $row) {
        $rowStyle = $rowStyleFn ? $rowStyleFn($row) : '';
        $metaColumn = $metaColumnFn ? $metaColumnFn($row) : '';
        echo "<tr style='{$rowStyle}'>";
        render_table_row($columns, $row, $searchTerms);
        echo "$metaColumn</tr>";
    }

    if ($selectedrowcount < 1000) {
        for ($i=0; $i < $selectedrowcount - $rowCount; $i++) {
            echo "<tr style='background-color: unset; pointer-events: none;' class='filler-row'>";
            echo "<td>-&nbsp;</td>";
            echo "</tr>";
        }
    }

    echo <<<HTML
                </tbody>
                <tfoot>
HTML;

    // Render table footer
    render_table_footer($id, $selectedrowcount, $totalItems, $rowCount, $offset);

    echo <<<HTML
                </tfoot>
            </table>
        </form>
    </div>
HTML;
}

/**
 * Renders the header row and search row for a datagrid table.
 *
 * This function generates the table header with dynamic columns, sortable headers, and optional 
 * descriptions for each column. It also renders the search input fields below each column if 
 * they are defined as searchable. The function supports custom sorting by clicking on column headers 
 * and includes a dropdown menu with additional actions like export and filter options.
 *
 * @param array $columns An array of column definitions. Each column typically includes properties such as:
 *                       - 'field': The field name for the column.
 *                       - 'headerName': The display name of the column.
 *                       - 'width': The width of the column.
 *                       - 'sortable': A boolean indicating if the column is sortable.
 *                       - 'searchable': A boolean indicating if the column is searchable.
 *                       - 'align': The text alignment for the column.
 *                       - 'description': An optional description for the column.
 *                       - 'sqlOverride': Optional SQL override for sorting.
 * @param array $searchTerms An array of search terms used for filtering each column. The keys should match the column field names.
 * @param int $totalWidth The total width of the table, used to calculate the width percentage of each column.
 * @param string $id The unique identifier for the table. Used to generate dynamic IDs and actions related to sorting and interactions.
 *
 * @return void Outputs the full HTML structure for the table header and the search row, including sorting functionality and search inputs.
 */
function render_table_headers($columns, $searchTerms, $totalWidth, $id) {
    print "<tr>";
    foreach ($columns as $column) {
        $width = ($column['width'] / $totalWidth) * 100;
        if ($column["sortable"]) {
            echo "<th 
                class='$column[field] sortable-td' 
                style='cursor: pointer; text-align: {$column['align']}; width: {$width}%;' 
                onclick=\"setSort$id('$column[field]')\"
            >";
            echo "<span class='sortable'>$column[headerName]</span>";
        } else {
            echo "<th class='$column[field]' style='text-align: {$column['align']}; width: {$width}%;'>";
            echo "<span>$column[headerName]</span>";
        }
        if ($column["description"]) {
            echo "<br><span style='font-weight: normal;'>$column[description]</span>";
        }
        echo "</th>";
    }
    print "<th class='filler-row'></th>";
    print "</tr>";
   print "<tr style='background-color: #f4f4f4'>";
    foreach ($columns as $column) {
        echo "<th class='$column[field]'>";
        if ($column["searchable"]) {
            $columnSearchTerm = if_isset($searchTerms[$column['field']], '');
            
            if ($column["type"] == "dropdown" && isset($column['dropdownOptions'])) {
                // Dropdown select for ref/sælger field AND date fields
                echo "<select class='inputbox' style='text-align: $column[align]; width: 100%;' name='search[$id][{$column['field']}]' onchange='this.form.submit()'>";
                echo "<option value=''></option>";
                
                $options = $column['dropdownOptions']();
                foreach ($options as $option) {
                    $selected = ($option == $columnSearchTerm) ? 'selected' : '';
                    echo "<option value='$option' $selected>$option</option>";
                }
                echo "</select>";
                
            } elseif ($column["type"] == "date") {
                // Date field with date picker
                // echo "<input class='inputbox date-picker' style='text-align: $column[align]; width: 100%;' type='text' name='search[$id][{$column['field']}]' value='$columnSearchTerm' placeholder='dd-mm-yyyy eller dd-mm-yyyy:dd-mm-yyyy'>";
                 echo "<input class='inputbox date-picker' style='text-align: $column[align]; width: 100%;' type='text' name='search[$id][{$column['field']}]' value='$columnSearchTerm' placeholder=''>";
            } else {
                // Regular text input
                echo "<input class='inputbox' style='text-align: $column[align]; width: 100%;' type='text' name='search[$id][{$column['field']}]' value='$columnSearchTerm' placeholder=''>";
            }
        }
        echo "</th>";
    }
    echo <<<HTML
                        <th>
                            <div class="dropdown">
                                <svg id="turn-arrow" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="34px" fill="#000000"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>
                                <div class="dropdown-content">
                                    <button type="submit" class="dropdown-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/></svg>
                                        Søg
                                    </button>
                                    <button type="button" onclick="handleAction{$id}('clear')">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                                        Ryd søgning
                                    </button>
                                    <button type="button" onclick="handleAction{$id}('exportCSV')">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm240-240H200v160h240v-160Zm80 0v160h240v-160H520Zm-80-80v-160H200v160h240Zm80 0h240v-160H520v160ZM200-680h560v-80H200v80Z"/></svg>
                                        Export til CSV
                                    </button>
                                    <button type="button" onclick="handleAction{$id}('exportPDF')">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M320-240h320v-80H320v80Zm0-160h320v-80H320v80ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/></svg>
                                        Export til PDF
                                    </button>
                                    <div id='edit-button' class="has-secondary-dropdown">
                                        <span>
                                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>
                                            Rediger
                                        </span>

                                        <svg id="turn-arrow2" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="34px" fill="#000000"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>
                                        <div class="secondary-dropdown">
                                            <button type="button" onclick="handleAction{$id}('kolonner')">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M121-280v-400q0-33 23.5-56.5T201-760h559q33 0 56.5 23.5T840-680v400q0 33-23.5 56.5T760-200H201q-33 0-56.5-23.5T121-280Zm79 0h133v-400H200v400Zm213 0h133v-400H413v400Zm213 0h133v-400H626v400Z"/></svg>
                                                Kolonner
                                            </button>
                                            <button type="button" onclick="handleAction{$id}('filtre')">
                                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M440-160q-17 0-28.5-11.5T400-200v-240L168-736q-15-20-4.5-42t36.5-22h560q26 0 36.5 22t-4.5 42L560-440v240q0 17-11.5 28.5T520-160h-80Zm40-308 198-252H282l198 252Zm0 0Z"/></svg>
                                                Filtre
                                            </button>
                                        </div>
                                    </div>

                                    <button type="button" onclick="handleAction{$id}('showSQL')">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M560-160v-80h120q17 0 28.5-11.5T720-280v-80q0-38 22-69t58-44v-14q-36-13-58-44t-22-69v-80q0-17-11.5-28.5T680-720H560v-80h120q50 0 85 35t35 85v80q0 17 11.5 28.5T840-560h40v160h-40q-17 0-28.5 11.5T800-360v80q0 50-35 85t-85 35H560Zm-280 0q-50 0-85-35t-35-85v-80q0-17-11.5-28.5T120-400H80v-160h40q17 0 28.5-11.5T160-600v-80q0-50 35-85t85-35h120v80H280q-17 0-28.5 11.5T240-680v80q0 38-22 69t-58 44v14q36 13 58 44t22 69v80q0 17 11.5 28.5T280-240h120v80H280Z"/></svg>
                                        Vis SQL
                                    </button>
                                </div>
                            </div>
                        </th>
                    </tr>
HTML;
}

/**
 * Renders the footer of a datagrid table, including pagination controls and row count options.
 *
 * This function generates the HTML for the footer of the datagrid, including:
 * - A row count dropdown to allow users to select how many rows to display per page.
 * - Pagination controls to navigate between pages of data.
 * - A display of the current page status, showing the range of items currently displayed and the total item count.
 *
 * The function dynamically calculates the pagination details, such as the current page, total pages, and navigation status.
 * It also generates links to other pages, showing a range of pages around the current one, and includes "next" and "previous" buttons.
 *
 * @param string $id The unique identifier for the table. Used to generate dynamic IDs for the footer elements and form fields.
 * @param int $selectedrowcount The number of rows selected to display per page. Used to calculate pagination and to update the row count dropdown.
 * @param int $totalItems The total number of items in the dataset. Used to calculate the total number of pages and display the current item range.
 * @param int $rowCount The number of rows to display per page.
 * @param int $offset The current page offset, which determines the starting item in the current page.
 *
 * @return void Outputs the HTML structure of the table footer, including row count options, pagination controls, and page status.
 */
// function render_table_footer($id, $selectedrowcount, $totalItems, $rowCount, $offset) {
//     // Define the possible row count options
//     $rowCounts = [50, 100, 250, 500, 1000, 5000, 999999999];

//     // Build the options dynamically
//     $options = '';
//     foreach ($rowCounts as $count) {
//         $selected = ($selectedrowcount == $count) ? 'selected' : '';
//         $label = ($count == 999999999) ? 'Alle' : $count; // Use "Alle" for the max value
//         $options .= "<option value=\"$count\" $selected>$label</option>";
//     }

//     // Calculate pagination details
//     $currentPage = floor($offset / $selectedrowcount) + 1;
//     $totalPages = ceil($totalItems / $selectedrowcount);
//     $offsetFrom = $offset + 1;
//     $offsetTo = min(array($totalItems, $selectedrowcount + $offset));
//     $nextpage = min(array($totalItems, $offset + $selectedrowcount));
//     $lastpage = max(array(0, $offset - $selectedrowcount));

//     $nextpagestatus = $nextpage == $totalItems ? 'disabled' : '';
//     $lastpagestatus = $offset == 0 ? 'disabled' : '';

//     // Generate a subset of page links
//     $pageLinks = '';
//     $pageRange = 2; // Number of pages to show around the current page
//     $startPage = max(1, $currentPage - $pageRange);
//     $endPage = min($totalPages, $currentPage + $pageRange);

//     if ($startPage > 1) {
//         $pageLinks .= "<button class='navbutton' type='button' onclick='setOffset$id(0)'>1</button>";
//         if ($startPage > 2) {
//             $pageLinks .= "<span>...</span>";
//         }
//     }

//     for ($i = $startPage; $i <= $endPage; $i++) {
//         $pageOffset = ($i - 1) * $selectedrowcount;
//         $isActiveStyle = ($i == $currentPage) ? "style='text-decoration: underline;'" : "";
//         $pageLinks .= "<button type='button' onclick='setOffset$id($pageOffset)' $isActiveStyle class='navbutton'>$i</button>";
//     }

//     if ($endPage < $totalPages) {
//         if ($endPage < $totalPages - 1) {
//             $pageLinks .= "<span>...</span>";
//         }
//         $lastPageOffset = ($totalPages - 1) * $selectedrowcount;
//         $pageLinks .= "<button type='button' onclick='setOffset$id($lastPageOffset)' class='navbutton'>$totalPages</button>";
//     }

//     // Output the footer with dynamic options
//     echo <<<HTML
//             <tr>
//                 <td colspan=100>
//                     <input type='hidden' name="offset[$id]" value="$offset" size='4'>
//                     <div id='footer-box'>
//                         <span style='display: flex' id='page-status'>
//                             $offsetFrom-$offsetTo&nbsp;af&nbsp;$totalItems
//                         </span>
//                         |
//                         <span>Linjer pr. side 
//                             <select name="rowcount[$id]" onchange="this.form.submit()">
//                                 $options
//                             </select> 
//                         </span>
//                         |
//                         <span id='navbuttons'>
//                             <button type='button' onclick="setOffset$id($lastpage)" $lastpagestatus>
//                                 <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z"/></svg>
//                             </button>
//                             $pageLinks
//                             <button type='button' onclick="setOffset$id($nextpage)" $nextpagestatus>
//                                 <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>
//                             </button>
//                         </span>
//                     </div>
//                 </td>
//             </tr>
// HTML;
// }
############################
function render_table_footer($id, $selectedrowcount, $totalItems, $rowCount, $offset) {
    // Define the possible row count options
    $rowCounts = [50, 100, 250, 500, 1000, 5000, 999999999];

    // Build the options dynamically
    $options = '';
    foreach ($rowCounts as $count) {
        $selected = ($selectedrowcount == $count) ? 'selected' : '';
        $label = ($count == 999999999) ? 'Alle' : $count;
        $options .= "<option value=\"$count\" $selected>$label</option>";
    }

    // Calculate pagination details
    $currentPage = floor($offset / $selectedrowcount) + 1;
    $totalPages = ceil($totalItems / $selectedrowcount);
    $offsetFrom = $offset + 1;
    $offsetTo = min(array($totalItems, $selectedrowcount + $offset));
    $nextpage = min(array($totalItems, $offset + $selectedrowcount));
    $lastpage = max(array(0, $offset - $selectedrowcount));

    $nextpagestatus = $nextpage >= $totalItems ? 'disabled' : '';
    $lastpagestatus = $offset == 0 ? 'disabled' : '';

    // Generate a subset of page links - FIXED: Use submit buttons instead of JavaScript
    $pageLinks = '';
    $pageRange = 2;
    $startPage = max(1, $currentPage - $pageRange);
    $endPage = min($totalPages, $currentPage + $pageRange);

    if ($startPage > 1) {
        $pageLinks .= "<button class='navbutton' type='submit' name='offset[$id]' value='0'>1</button>";
        if ($startPage > 2) {
            $pageLinks .= "<span>...</span>";
        }
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
        $pageOffset = ($i - 1) * $selectedrowcount;
        $isActiveStyle = ($i == $currentPage) ? "style='text-decoration: underline;'" : "";
        $pageLinks .= "<button type='submit' name='offset[$id]' value='$pageOffset' $isActiveStyle class='navbutton'>$i</button>";
    }

    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $pageLinks .= "<span>...</span>";
        }
        $lastPageOffset = ($totalPages - 1) * $selectedrowcount;
        $pageLinks .= "<button type='submit' name='offset[$id]' value='$lastPageOffset' class='navbutton'>$totalPages</button>";
    }

    // Output the footer with dynamic options - FIXED: Ensure proper form structure
    echo <<<HTML
            <tr>
                <td colspan=100>
                    <div id='footer-box'>
                        <span style='display: flex' id='page-status'>
                            $offsetFrom-$offsetTo&nbsp;af&nbsp;$totalItems
                        </span>
                        |
                        <span>Linjer pr. side 
                            <select name="rowcount[$id]" onchange="this.form.submit()">
                                $options
                            </select> 
                        </span>
                        |
                        <span id='navbuttons'>
                            <button type='submit' name='offset[$id]' value='$lastpage' $lastpagestatus>
                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z"/></svg>
                            </button>
                            $pageLinks
                            <button type='submit' name='offset[$id]' value='$nextpage' $nextpagestatus>
                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>
                            </button>
                        </span>
                    </div>
                </td>
            </tr>
HTML;
}

########################

/**
 * Renders a row of the data table without highlighting search terms.
 *
 * This function iterates over the columns of the table, retrieves the appropriate value for each column from the data row, 
 * and applies a value getter and rendering function if defined. This version does not perform any search term highlighting.
 *
 * @param array $columns An array of column definitions. Each column typically includes properties such as `field`, `valueGetter` 
 *                       (a callback for custom value extraction), and `render` (a callback for custom rendering).
 * @param array $row The data row, which is an associative array where keys are column field names and values are the cell data.
 * @param array $searchTerms An associative array of search terms for each column, where the key is the column field and the value is the search term.
 *
 * @return void Outputs the HTML for each column's cell without any search term highlighting.
 */
/*
function render_table_row($columns, $row, $searchTerms) {
    foreach ($columns as $column) {
        $value = $column['valueGetter'](
            if_isset($row[$column['field']], ''), 
            $row,
            $column
        );
        $data = $column['render'](
            $value,
            $row, 
            $column
        );
        echo $data;
    }
}*/

/**
 * Renders a column setup form for a data table.
 *
 * This function generates an HTML form that allows users to configure which columns
 * should be visible in a table. It includes column position, field name, optional headers,
 * descriptions, width settings, and alignment options. The form also provides buttons 
 * to save changes or close the setup interface.
 *
 * @param string $id          The unique identifier for the table.
 * @param array  $columns     An array of currently selected columns for the table.
 * @param array  $all_columns An array of all available columns that can be displayed.
 * 
 * @return void Outputs the HTML structure directly.
 */
function render_column_setup($id, $columns, $all_columns) {
    echo <<<HTML
    <div class="datatable-wrapper" id="datatable-wrapper-$id">
        <form method="POST" action="">
            <div class="datatable-search-wrapper">
                <table class="datatable" id="datatable-$id" style="width: 100%;">
                    <tr><td colspan=100>Vælg hvilke felter der skal være synlige i tablelen</td></tr>
                    <tr><td colspan=100><hr></td></tr>
                    <tr>
                        <th>Pos</th>
                        <th>Felt</th>
                        <th>Valgfri overskrift</th>
                        <th>Valgfri beskrivelse</th>
                        <th align='right' style='text-align: right;'>Feltbredde</th>
                        <th>Justering</th>
                    </tr>
                    <tr><td colspan=100><hr></td></tr>
HTML;

    // Render table headers and input fields for column setup
    render_columns($id, $columns, $all_columns);

    echo <<<HTML
            <tr>
                <td colspan="100" align="right">
                    <button>Gem</button>
                    <button type='button' onclick='updateQueryParameter("menu[$id]", "menu[$id]", "main");'>Luk</button>
                </td>
            </tr>
                </table>
            </div>
        </form>
    </div>
HTML;
}


// Render table with highligthing
/*
* This function iterates over the columns of the table, retrieves the appropriate value for each column from the data row, 
* applies a value getter and rendering function if defined, and highlights any text that matches the search terms for the 
* relevant column. If no search terms are provided, it simply renders the value for each column.
*
* @param array $columns An array of column definitions. Each column typically includes properties such as `field`, `type`, 
*                       `valueGetter` (a callback for custom value extraction), and `render` (a callback for custom rendering).
* @param array $row The data row, which is an associative array where keys are column field names and values are the cell data.
* @param array $searchTerms An associative array of search terms for each column, where the key is the column field and the value is the search term.
*
* @return void Outputs the HTML for each column's cell, potentially with highlighted text based on search terms.
*/
function render_table_row($columns, $row, $searchTerms) {
    foreach ($columns as $column) {
        $field = $column["field"];
        $term = isset($searchTerms[$field]) ? strtolower($searchTerms[$field]) : '';
        $rawValue = isset($row[$field]) ? $row[$field] : '';

        // Optimize valueGetter call
        $value = isset($column['valueGetter']) && is_callable($column['valueGetter'])
            ? $column['valueGetter']($rawValue, $row, $column)
            : $rawValue;

        // Optimize text search and highlighting
        if ($column["type"] == "text" && $term !== '' && mb_stripos($value, $term, 0, 'UTF-8') !== false) {
            $value = preg_replace_callback(
                '/' . preg_quote($term, '/') . '/iu',
                function ($match) {
                    return '<span style="background-color:#FF0">' . $match[0] . '</span>';
                },
                $value
            );
        }

        // Render the final data
        $data = isset($column['render']) && is_callable($column['render'])
            ? $column['render']($value, $row, $column)
            : htmlspecialchars($value);

        echo $data;
    }
}



/**
 * Renders a form that allows users to configure visible columns in a data table.
 *
 * This function generates the HTML structure for a table configuration form, where users can select which fields (columns)
 * should be visible in the data table. It includes options to modify column position, optional column headers and descriptions,
 * field width, and alignment. The form submits via POST to save the configuration.
 *
 * @param string $id The unique identifier for the table configuration. Used to generate dynamic IDs for form elements and buttons.
 * @param array $columns An array of columns that are currently visible in the table. This array contains column settings like field name, type, and visibility.
 * @param array $all_columns An array of all available columns in the data table, including those that may not be visible. This is used to allow toggling visibility.
 *
 * @return void Outputs the HTML for the column setup form, including the configuration table and buttons for saving and closing.
 */
function render_columns($id, $columns, $all_columns) {
    // Create all column options as a select
    $selectOptions = "";
    foreach ($all_columns as $column) {
        $selectOptions .= "<option value='$column[field]'>$column[field]</option>";
    }

    $i = 0;
    foreach ($columns as $column) {
        $i++;
        $width = $column['width'] * 100;
        $widthstyle = $column['width'] * 15;
        echo <<<HTML
            <tr>
                <td>
                    <div style="display: flex">
                        <input type='text' name='rows[$id][$i][pos]' value='{$i}' class="inputbox" size='4'>
                        <!-- UP -->
                        <button type="button" onclick="adjustValue('rows[$id][$i][pos]', -2)" class="move-buttons">
                            <svg xmlns="http://www.w3.org/2000/svg" height="15px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M444-192v-438L243-429l-51-51 288-288 288 288-51 51-201-201v438h-72Z"/></svg>
                        </button>
                        <!-- DOWN -->
                        <button type="button" onclick="adjustValue('rows[$id][$i][pos]', 2)" class='move-buttons'>
                            <svg xmlns="http://www.w3.org/2000/svg" height="15px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M444-768v438L243-531l-51 51 288 288 288-288-51-51-201 201v-438h-72Z"/></svg>
                        </button>
                        <!-- DELETE -->
                        <button type="button" onclick="document.getElementsByName('rows[$id][$i][pos]')[0].value='-'; document.getElementsByName('rows[$id][$i][pos]')[0].form.submit();" class='move-buttons'>
                            <svg xmlns="http://www.w3.org/2000/svg" height="15px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M312-144q-29.7 0-50.85-21.15Q240-186.3 240-216v-480h-48v-72h192v-48h192v48h192v72h-48v479.57Q720-186 698.85-165T648-144H312Zm336-552H312v480h336v-480ZM384-288h72v-336h-72v336Zm120 0h72v-336h-72v336ZM312-696v480-480Z"/></svg>
                        </button>
                    </div>
                </td>
                <td>
                    <select name='rows[$id][$i][field]' class="inputbox">
                        <option value='$column[field]'>$column[field]</option>
                        {$selectOptions}
                    </select>
                </td>
                <td>
                    <input type='text' name='rows[$id][$i][headerName]' value='{$column['headerName']}' class="inputbox">
                </td>
                <td>
                    <input type='text' name='rows[$id][$i][description]' value='{$column['description']}' class="inputbox">
                </td>
                <td align='right'>
                    <input type='number' name='rows[$id][$i][width]' value='{$width}' size='{$widthstyle}' class="inputbox" onchange="this.size = this.value*0.15;">
                </td>
                <td align='left'>
                    <select name='rows[$id][$i][align]' class="inputbox">
                        <option value='$column[align]'>$column[align]</option>
                        <option value='left'>left</option>
                        <option value='center'>center</option>
                        <option value='right'>right</option>
                    </select>
                </td>
                <td style="width: 100%;"></td> <!-- Empty cell takes up all the space -->
            </tr>
HTML;
    }
    
    // Newline for new items
    $i++;
    echo <<<HTML
    <tr><td colspan=100><hr></td></tr>
    <tr>
        <td>
            <input type='text' name='rows[$id][$i][pos]' value='{$i}' class="inputbox" size='4'>
        </td>
        <td>
            <select name='rows[$id][$i][field]' class="inputbox">
                {$selectOptions}
            </select>
        </td>
        <td>
            <input type='text' name='rows[$id][$i][headerName]' class="inputbox">
        </td>
        <td>
            <input type='text' name='rows[$id][$i][description]' class="inputbox">
        </td>
        <td align='right'>
            <input type='number' name='rows[$id][$i][width]' value="100" size='10' class="inputbox">
        </td>
        <td align='left'>
            <select name='rows[$id][$i][align]' class="inputbox">
                <option value='left'>left</option>
                <option value='center'>center</option>
                <option value='right'>right</option>
            </select>
        </td>
    </tr>
HTML;
}

/**
 * Saves the column setup configuration for a user.
 *
 * This function processes the submitted column configuration, filters out invalid rows,
 * sorts them by their position, and then stores the updated configuration in the database 
 * under the user's settings. The function assumes that the column data is sent via a 
 * POST request, specifically the 'rows' parameter, which is an array of column definitions.
 * 
 * The columns are filtered to ensure they have a valid 'pos' value (numeric and not null),
 * sorted by 'pos', and then the 'pos' field is removed before the data is saved.
 * Additionally, the width of each column is normalized by dividing it by 100.
 * 
 * @param string $id The unique identifier for the column setup. This is used to access specific column data in the $_POST array.
 * 
 * @return void Outputs nothing, but updates the column setup in the database for the current user.
 */
function save_column_setup($id) {
    global $bruger_id;

    $rows = $_POST['rows'][$id];

    // Filter out rows where 'pos' is null
    $rows = array_filter($rows, function ($row) {
        return is_numeric($row['pos']) && $row['headerName'] && $row['pos'] !== null;
    });

    // Sort the array by 'pos'
    usort($rows, function ($a, $b) {
        if ($a['pos'] == $b['pos']) {
            return 0;
        }
        return ($a['pos'] < $b['pos']) ? -1 : 1;
    });
    

    // Remove the 'pos' key from each sub-array
    $rows = array_map(function ($row) {
        unset($row['pos']);
        $row["width"] = $row["width"] / 100;
        return $row;
    }, $rows);

    // Print the result
    $columns_json = db_escape_string(json_encode($rows));
    db_modify("UPDATE datatables SET column_setup = '$columns_json' WHERE user_id = $bruger_id", __FILE__ . " line " . __LINE__);
}

/**
 * Renders the filter setup form for selecting active filters.
 *
 * This function generates an HTML form that allows the user to select which filters should be active for a data table.
 * It includes checkboxes for each available filter and a "Save" button to save the selected filter setup. 
 * The function utilizes the `render_filters` helper function to display the filters.
 *
 * @param string $id The unique identifier for the filter setup. Used for dynamically generating element IDs and names.
 * @param array $filters An array of filters currently applied or active, including filter names and options.
 * @param array $all_filters An array of all available filters to choose from.
 *
 * @return void Outputs the full HTML form for setting up filters. No value is returned.
 */
function render_filter_setup($id, $filters, $all_filters) {
    echo <<<HTML
    <div class="datatable-wrapper" id="datatable-wrapper-$id">
        <form method="POST" action="">
            <div class="datatable-search-wrapper">
                Vælg hvilke filtre der skal være aktive <br><hr>
                <div id='filter-wrapper'>
HTML;

    // Render table headers
    render_filters($id, $filters, $all_filters);

    echo <<<HTML
                </div>
                <hr>
                <div style='display: flex; justify-content: flex-end; gap: 5px;'>
                    <button class="select">Marker alle</button>
                    <button class="deselect">Fjern alle</button>
                    <button>Gem</button>
                    <button type='button' onclick='updateQueryParameter("menu[$id]", "menu[$id]", "main");'>Luk</button>
                    <script>
                        const checkboxes = document.querySelectorAll('[type="checkbox"]');
                        document.querySelector('.select').addEventListener('click', function(event) {
                            event.preventDefault();
                            checkboxes.forEach(checkbox => checkbox.checked = true);
                        });
                        document.querySelector('.deselect').addEventListener('click', function(event) {
                            event.preventDefault();
                            checkboxes.forEach(checkbox => checkbox.checked = false);
                        });
                    </script>
                </div>
                <div style="height: 2rem;"></div>
            </div>
        </form>
    </div>
HTML;
}

/**
 * Renders individual filters and their options as checkboxes.
 *
 * This function outputs the HTML for each filter, displaying its name, join operator, and a list of options with checkboxes.
 * Each option represents a potential filter to be applied to the data table.
 * 
 * @param string $id The unique identifier for the filter setup.
 * @param array $filters An array of active filters, each containing a name, join operator, and options.
 * @param array $all_filters An array of all available filters to choose from (not directly used in this function).
 *
 * @return void Outputs the HTML for displaying the filters with checkboxes.
 */
function render_filters($id, $filters, $all_filters) {
    $i = 0;
    foreach ($filters as $filter) {
        $i++;
        echo <<<HTML
        <div>
            <span><b>{$filter["filterName"]} ({$filter["joinOperator"]})</b></span>
HTML;
        foreach ($filter["options"] as $filterItem) {
            print "<div><label><input type='checkbox' $filterItem[checked] name='filter[$id][$filter[filterName]][$filterItem[name]]'>$filterItem[name]</label></div>";
        }

        echo <<<HTML
        </div>
HTML;
    }
}

/**
 * Saves the selected filter setup to the database.
 *
 * This function processes the POST data to determine which filters have been selected by the user.
 * It updates the filter setup in the database by encoding the selected filters into JSON format.
 * The function recursively replaces any "on" values with "checked" for the filter options before saving.
 *
 * @param string $id The unique identifier for the filter setup, used to access the corresponding filter data in the POST request.
 *
 * @return void Outputs nothing but updates the filter setup in the database for the current user.
 */
function save_filter_setup($id) {
    global $bruger_id;

    // Get the filter data from POST
    $rows = if_isset($_POST['filter'][$id], array());

    // Recursive function to replace "on" with "checked"
    foreach ($rows as $key => &$value) {
        if (is_array($value)) {
            foreach ($value as $subKey => &$subValue) {
                if (is_array($subValue)) {
                    foreach ($subValue as $optionKey => &$optionValue) {
                        if ($optionValue === "on") {
                            $optionValue = "checked";
                        }
                    }
                } elseif ($subValue === "on") {
                    $subValue = "checked";
                }
            }
        } elseif ($value === "on") {
            $value = "checked";
        }
    }

    // Encode the updated rows as JSON and escape it for the database
    $filter_json = db_escape_string(json_encode($rows));

    // Save the updated JSON to the database
    db_modify("UPDATE datatables SET filter_setup = '$filter_json' WHERE user_id = $bruger_id", __FILE__ . " line " . __LINE__);
}

/**
 * Outputs CSS styles for the data table and search interface.
 *
 * This function generates a block of CSS to style the data table and its components, including 
 * the table wrapper, header, body, and footer. It also adds custom styles for various table 
 * states such as hover effects, alternating row colors, and search input fields.
 * 
 * - The table header and footer are styled to remain sticky at the top and bottom of the table, respectively.
 * - Alternating row colors are applied for better readability.
 * - A hover effect is applied to rows for interaction feedback.
 * - The "sortable" class is applied to table headers to indicate that columns are sortable.
 * 
 * The generated CSS is embedded within the page to directly style the table and related elements.
 *
 * @return void Outputs the embedded CSS for styling the data table and search functionality.
 */
function render_search_style() {
    echo <<<STYLE
    <style>
        .datatable-wrapper {
            margin-bottom: 5px;
            overflow-x: auto;
            position: relative;

            height: 100%;
            width: 100%;
        }
        .datatable {
            border-collapse: collapse;
            width: 100%;
        }
        .datatable tfoot {
            position: sticky;
            bottom: 0; /* Stick to the bottom */
            z-index: 1; /* Ensure it stays above other content */
            background-color: #f4f4f4; /* Background color for the footer */
            border-top: 2px solid #ddd; /* Optional: separate footer visually */
        }
        .datatable thead {
            position: sticky;
            top: 0; /* Stick to the top */
            z-index: 1; /* Ensure it stays above other content */
            background-color: #f4f4f4; /* Background color for the header */
            text-align: left;
            border-bottom: 2px solid #ddd;
        }
        .datatable tbody tr:nth-child(2n) {
            background-color: #e0e0f0;
        }
        .datatable tbody tr:hover {
            outline: 2px solid #b2b2b2;
            background-color: #f9f9f9;
            cursor: pointer;
        }
        .datatable-search-wrapper input {
            width: 100%;
        }
        .datatable tr td {
            padding-right: 4px;
        }
        .datatable tr a {
            color: inherit;
        }
        .datatable .sortable {
            text-decoration: underline;
            cursor: pointer;
            -webkit-touch-callout: none; /* iOS Safari */
              -webkit-user-select: none; /* Safari */
               -khtml-user-select: none; /* Konqueror HTML */
                 -moz-user-select: none; /* Old versions of Firefox */
                  -ms-user-select: none; /* Internet Explorer/Edge */
                      user-select: none; /* Non-prefixed version, currently
                                            supported by Chrome, Edge, Opera and Firefox */
        }
        .datatable .sortable-td:hover .sortable {
            color: #114691;
            transition: color 0.2s ease-in-out;
        }

    </style>
STYLE;
}

/**
 * Outputs CSS styles for dropdown and table footer elements.
 *
 * This function generates a block of CSS that defines the styling for dropdown menus, 
 * including the main dropdown and secondary dropdowns within a table. It also styles 
 * elements in the table footer and ensures that dropdown menus are responsive and 
 * accessible within the viewport.
 * 
 * - Dropdown menus are styled to appear with a box shadow and to show on hover.
 * - A secondary dropdown is positioned relative to the parent dropdown, and it appears when hovering over the parent.
 * - The footer is styled with navigation buttons that adjust their cursor behavior based on their disabled state.
 * - Arrows for dropdown indicators are animated to rotate on hover.
 * 
 * The generated CSS is embedded directly into the page for styling the dropdowns, footer, 
 * and other table-related UI elements.
 *
 * @return void Outputs the embedded CSS for styling the dropdown menus and table footer.
 */
function render_dropdown_style() {
    echo <<<STYLE
    <style>
        tbody {
            min-height: 300px;
        }
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            left: 0; /* Align dropdown to the left by default */
            background-color: white;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 100;
            min-width: 150px; /* Increase width slightly for readability */
        }
        .dropdown-content button, .dropdown-content #edit-button {
            font-weight: normal;
            background: none;
            border: none;
            padding: 10px;
            box-sizing: border-box;
            text-align: left;
            width: 100%;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        .dropdown-content button:hover, .dropdown-content #edit-button:hover {
            background-color: #f1f1f1;
        }
        .dropdown-content button svg, .dropdown-content #edit-button svg {
            height: 17px;
        }
        .dropdown:hover .dropdown-content {
            display: block;
        }
        /* Ensure the dropdown stays within the viewport */
        .dropdown-content {
            right: auto; /* Ensure it's not forced to align right */
            transform: translateX(0); /* Default translation */
        }
        .dropdown:hover .dropdown-content {
            left: auto; /* Reset alignment if it's clipped */
            right: 0; /* Move to the right edge if needed */
        }
        tfoot tr td #footer-box {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: flex-end;
        }
        tfoot tr td #footer-box button {
            padding: 0;
        }

        tfoot tr td #footer-box button:not(:disabled) {
            cursor: pointer;
        }
        tfoot tr td #footer-box button.navbutton {
            height: 24px;
            width: 24px;
        }
        tfoot tr td #footer-box #navbuttons {
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .has-secondary-dropdown {
            position: relative;
            display: flex;
            justify-content: space-between;
        }
        .has-secondary-dropdown span {
            display: flex;
            aling-items: center;
        }
        .has-secondary-dropdown .secondary-dropdown {
            display: none;
            position: absolute;
            left: -150px; /* Align secondary dropdown to the left */
            top: 0;
            background-color: white;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 2;
            min-width: 150px;
        }
        .has-secondary-dropdown:hover .secondary-dropdown {
            display: block;
        }
        .secondary-dropdown button {
            background: none;
            border: none;
            padding: 10px;
            text-align: left;
            width: 100%;
            cursor: pointer;
        }
        .secondary-dropdown button:hover {
            background-color: #f1f1f1;
        }
        #turn-arrow, #turn-arrow2 {
            transition: transform 0.1s ease-in-out;
        }
        .dropdown:hover #turn-arrow, .has-secondary-dropdown:hover #turn-arrow2 {
            transform: rotate(90deg);
        }

    </style>
STYLE;
}

/**
 * Outputs CSS styles for the filter editing layout.
 *
 * This function generates a block of CSS that defines the layout and style for the 
 * filter editing interface. The `#filter-wrapper` is styled to be a flexible container 
 * with space between filter items, making it responsive and properly spaced.
 * 
 * - The filters are arranged using Flexbox with a gap of 2em between elements.
 * 
 * The generated CSS is embedded directly into the page to style the filter editing layout.
 *
 * @return void Outputs the embedded CSS for styling the filter editing layout.
 */
function render_filter_edit_style() {
    echo <<<STYLE
    <style>
        #filter-wrapper {
            display: flex;
            width: 100%;
            gap: 2em;
            /*justify-content: space-between*/
        }
    </style>
STYLE;
}

/**
 * Outputs CSS styles for column editing interface buttons.
 *
 * This function generates a block of CSS to style the buttons used for column movement 
 * in the editing interface. The `.move-buttons` class is styled for minimal visual 
 * presence, while still providing a functional button for column adjustment.
 * 
 * - The buttons are styled to have no background or border, with a small height and 
 *   a pointer cursor to indicate interactivity.
 * 
 * The generated CSS is embedded directly into the page to style the column editing buttons.
 *
 * @return void Outputs the embedded CSS for styling the column edit buttons.
 */
function render_column_edit_style() {
    echo <<<STYLE
    <style>
        .move-buttons {
            margin: 0;
            padding: 0;
            border: none;
            background-color: #00000000;
            height: 15px;
            cursor: pointer;
            /*display: none;*/
        }
    </style>
STYLE;
}


/**
 * Outputs JavaScript code for handling various actions within the dropdown menu.
 *
 * This function generates a block of JavaScript code that provides functionalities
 * for different actions within a dropdown interface associated with a specific table.
 * It includes handling for showing SQL queries, clearing search fields, exporting
 * data to CSV and PDF, and managing the scroll position of the table.
 *
 * Supported actions:
 * - 'showSQL': Displays the SQL query in a table.
 * - 'clear': Clears the input fields matching the search pattern and submits the form.
 * - 'exportCSV': Exports the table data to a CSV file.
 * - 'exportPDF': Exports the table data to a PDF file.
 * - 'kolonner': Submits a form with the action to manage columns.
 * - 'filtre': Submits a form with the action to manage filters.
 *
 * Additionally, this function handles saving and restoring the scroll position
 * of the table to ensure a consistent user experience.
 *
 * @param string $id The unique identifier for the table, used to target specific elements in the DOM.
 * @param string $query The SQL query to display when the 'showSQL' action is triggered.
 * @return void Outputs the embedded JavaScript for handling dropdown actions and table behavior.
 */
function render_dropdown_script($id, $query) {
    $escapedQuery = addslashes($query);

    echo <<<SCRIPT
    <script>
        function handleAction$id(action) {
            if (action === 'showSQL') {
                const tbody = document.querySelector('div#datatable-wrapper-$id.datatable-wrapper form div.datatable-search-wrapper table#datatable-$id.datatable tbody');
                tbody.innerHTML = `<pre>$escapedQuery</pre>`;
                tbody.innerHTML += '<b><a href="' + window.location.href.split('?')[0] + '">back</a></b>';

            } else if (action === 'clear') {
                 // Select all input fields matching the name pattern `search[test][...]`
                const searchFields = document.querySelectorAll('input[name^="search[$id]"]');
                console.log(searchFields);

                // Loop through each field and clear its value
                searchFields.forEach(field => {
                    field.value = "";
                });

                searchFields[0].form.submit();

            } else if (action === 'exportCSV') {
                // Get the table element
                const table = document.querySelector('div#datatable-wrapper-$id.datatable-wrapper form div.datatable-search-wrapper table#datatable-$id.datatable');

                if (!table) {
                    alert('Error: Table not found.');
                    return;
                }

                // Clone the table to avoid modifying the original
                const cleanTable = table.cloneNode(true);

                // Remove the second <tr> in the <thead>
                const thead = cleanTable.querySelector('thead');
                if (thead) {
                    const rows = thead.querySelectorAll('tr');
                    if (rows.length > 1) {
                        thead.removeChild(rows[1]); // Remove the second <tr>
                    }
                }

                // Remove filler rows
                const fillerRows = cleanTable.getElementsByClassName('filler-row');
                while (fillerRows[0]) {
                    fillerRows[0].parentNode.removeChild(fillerRows[0]);
                }

               
                const footerBox = cleanTable.querySelector('#footer-box');
                if (footerBox) {
                    const pageStatus = footerBox.querySelector('#page-status');
                    footerBox.innerHTML = ''; // Clear all content
                    if (pageStatus) {
                        footerBox.appendChild(pageStatus); // Keep only the 'page-status'
                    }
                }

                // Extract rows from the cleaned table
                const rows = Array.from(cleanTable.querySelectorAll('tr'));

                // Convert rows to CSV format
                const csvContent = rows
                    .map(row => {
                        const cells = Array.from(row.querySelectorAll('th, td'));
                        return cells.map(cell => {
                            // Escape quotes and handle special characters
                            return '"' + cell.textContent.replace(/"/g, '""') + '"';
                        }).join(',');
                    })
                    .join('\\n'); // Escape newline properly

                // Create a Blob from the CSV content
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });

                // Create a download link
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'datagrid_$id.csv';
                a.style.display = 'none';

                // Append the link to the document, click it, and remove it
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);

            } else if (action === 'exportPDF') {
                const table = document.querySelector('div#datatable-wrapper-$id.datatable-wrapper form div.datatable-search-wrapper table#datatable-$id.datatable');

                if (!table) {
                    alert('Error: Table not found.');
                    return;
                }

                const PDFname = prompt('PDF tekst');

                // Clone the table to avoid modifying the original
                const printableTable = table.cloneNode(true);

                // Remove the second <tr> in the <thead>
                const thead = printableTable.querySelector('thead');
                if (thead) {
                    const rows = thead.querySelectorAll('tr');
                    if (rows.length > 1) {
                        thead.removeChild(rows[1]); // Remove the second <tr>
                    }
                }

                var paras = printableTable.getElementsByClassName('filler-row');

                while(paras[0]) {
                    paras[0].parentNode.removeChild(paras[0]);
                }


                // Remove all elements except the one with id 'page-status'
                const footerBox = printableTable.querySelector('#footer-box');
                if (footerBox) {
                    const pageStatus = footerBox.querySelector('#page-status');
                    footerBox.innerHTML = ''; // Clear all content
                    if (pageStatus) {
                        footerBox.appendChild(pageStatus); // Keep only the 'page-status'
                    }
                }
                
                // Create a new print window
                const printWindow = window.open('', '_blank');
                printWindow.document.open();

                const html = printableTable.outerHTML;

                // Write the HTML content for the print window
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Export PDF</title>
                        <style>
                            table {
                                width: 100%;
                                border-collapse: collapse;
                            }
                            th, td {
                                border: 1px solid black;
                                padding: 8px;
                                text-align: left;
                            }
                            th {
                                background-color: #f2f2f2;
                            }
                        </style>
                    </head>
                    <body>
                        <h1>\${PDFname}</h1>
                        \${html}
                    </body>
                    </html>
                `);

                // Print the content
                printWindow.print();

                // Close the print window after printing
                printWindow.onmousemove = (e) => printWindow.close();
            } else if (action === 'kolonner') {
                const field = document.getElementsByName('menu[$id]')[0];
                field.value = 'kolonner';
                field.form.submit();

            } else if (action === 'filtre') {
                const field = document.getElementsByName('menu[$id]')[0];
                field.value = 'filtre';
                field.form.submit();
            }

        }

        document.addEventListener("DOMContentLoaded", function() { 
            console.log('CONTENT LOAD');
            var element = document.getElementById('datatable-wrapper-$id');
            var scrollpos = localStorage.getItem('scrollpos-datatable-$id');
            if (scrollpos && element) {
                element.scrollTo(0, parseInt(scrollpos, 10));
            }
        });

function saveScrollPosition() {
    var element = document.getElementById('datatable-wrapper-$id');
    if (element) {
        var scrollKey = 'scrollpos-datatable-$id';
        localStorage.setItem(scrollKey, element.scrollTop);
        console.log('Scroll position saved:', element.scrollTop);
    }
}

document.addEventListener("visibilitychange", function() {
    if (document.visibilityState === 'hidden') {
        saveScrollPosition();
    }
});

window.addEventListener("beforeunload", function() {
    saveScrollPosition();
});


    </script>
SCRIPT;
}

/**
 * Outputs JavaScript code for setting the offset value in pagination.
 *
 * This function generates JavaScript code that allows for setting a new offset value
 * for pagination. It retrieves the offset value, updates the corresponding input field,
 * and submits the form to apply the change in pagination.
 *
 * @param string $id The unique identifier for the table, used to target specific pagination elements in the DOM.
 * @return void Outputs the embedded JavaScript for updating the pagination offset.
 */
function render_pagination_script($id) {
    echo <<<SCRIPT
    <script>
        function setOffset$id(offset) {
            const offsetBox = document.getElementsByName('offset[$id]')[0];
            offsetBox.value=offset;
            offsetBox.form.submit();
        }
    </script>
SCRIPT;
}

/**
 * Outputs JavaScript code for setting the sort order for a table column.
 *
 * This function generates JavaScript code to toggle the sort order of a table column
 * based on the user's interaction. It updates the sort input field with the selected
 * column and the desired sort direction (ascending or descending), and submits the form
 * to apply the sorting.
 *
 * @param string $id The unique identifier for the table, used to target specific sort elements in the DOM.
 * @return void Outputs the embedded JavaScript for updating the sort order.
 */
function render_sort_script($id) {
    echo <<<SCRIPT
    <script>
        function setSort$id(header) {
            const sortBox = document.getElementsByName('sort[$id]')[0];
            if (sortBox.value !== header) {
                sortBox.value=header;
            } else if (sortBox.value === header) {
                sortBox.value=header + " desc";
            }
            sortBox.form.submit();
        }
    </script>
SCRIPT;
}

/**
 * Outputs JavaScript code for adjusting input values and updating query parameters.
 *
 * This function generates JavaScript code that provides two functionalities:
 * 1. `adjustValue`: Adjusts the value of a specified input field by a given delta and submits the form.
 * 2. `updateQueryParameter`: Updates a specific query parameter in the URL without reloading the page.
 *
 * These functions enable dynamic value adjustments and URL modifications without requiring
 * page reloads, which enhances user experience.
 *
 * @return void Outputs the embedded JavaScript for adjusting input values and managing query parameters.
 */
function render_move_script() {
    print <<<SCRIPT
    <script>
        function adjustValue(inputId, delta) {
            const input = document.getElementsByName(inputId)[0];
            if (input) {
                input.value = parseInt(input.value || 0) + delta;
                input.form.submit();
            }
        }

        function updateQueryParameter(oldParam, newKey, newValue) {
            // Parse the current URL
            const url = new URL(window.location.href);

            // Remove the old parameter
            url.searchParams.delete(oldParam);

            // Add the new parameter
            url.searchParams.set(newKey, newValue);

            // Update the URL without reloading the page
            window.location.href = url.toString();
        }
    </script>
SCRIPT;
}

?>
