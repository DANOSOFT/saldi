<?php
#/../includes/orderFuncIncludes/grid_account_lookup.php
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
        return dkdecimal($value, $column['decimalPrecision']);
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
 *
 * This function helps construct SQL conditions for search functionality.
 *
 * @param array $column The column definition (must include 'type', 'field', and 'sqlOverride').
 * @param string $term The search term.
 * @return string The generated SQL condition.
 */
function DEFAULT_GENERATE_SEARCH($column, $term) {
    $field = $column['sqlOverride'] == '' ? $column['field'] : $column['sqlOverride'];
    $term = db_escape_string($term);

    switch ($column["type"]) {
        case 'text':
            return "{$field} ILIKE '%$term%'";
        case 'number':
            # Check for number range
            if (strstr($term, ':')) {
                list($num1, $num2) = explode(":", $term, 2);
                return "round({$field}::numeric, {$column['decimalPrecision']}) >= '".usdecimal($num1)."' 
                        AND 
                        round({$field}::numeric, {$column['decimalPrecision']}) <= '".usdecimal($num2)."'";
            } else {
                $term = usdecimal($term);
                return "round({$field}::numeric, {$column['decimalPrecision']}) >= $term 
                        AND 
                        round({$field}::numeric, {$column['decimalPrecision']}) <= $term";
            }
        default:
            return "1=1";
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
   # $searchTerms = if_isset($_GET["search"][$id], $search_setup);
    $searchTerms1 = if_isset($_GET, NULL,"search");
    $searchTerms2 = if_isset($searchTerms1, NULL, $id);
    $searchTerms = if_isset($searchTerms2, NULL, $search_setup);
   
    $search_json  = db_escape_string(json_encode($searchTerms));
    log_grid_performance("JSON processing and search setup", $setup_processing_start);

    // Retrieve stored grid settings from the database
    $grid_settings_start = microtime(true);
    $q = "SELECT search_setup, rowcount, \"offset\", \"sort\" FROM datatables WHERE user_id = $bruger_id AND tabel_id='$id'";
    $r = db_fetch_array(db_select($q, __FILE__ . " line " . __LINE__));
    log_grid_performance("Grid settings query", $grid_settings_start);

    // Determine sorting, row count, and offset
   $sortArrayGet = if_isset($_GET, NULL, 'sort');
    $sortArrayDb = if_isset($r,NULL, 'sort');
    $sortId = if_isset($sortArrayGet, NULL, $id);
     if(!$sortId){
       $sort = if_isset($sortArrayDb, get_default_sort($columns_updated)); 
     }else{
        $sort = $sortId; 
     }

     $rowC1 = if_isset($_GET, NULL, "rowcount");
     $rowCId = if_isset($rowC1,NULL,$id);
     $rowCdb = if_isset($r, NULL,"rowcount"); //properly use the if_isset to prevent too many error logs
     #$selectedrowcount = if_isset($_GET["rowcount"][$id], if_isset($r["rowcount"], 100));
    $selectedrowcount = if_isset($rowCId, if_isset($rowCdb, 100));
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
            #save_column_setup($id);
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
       
        render_column_edit_style();
        render_move_script();

    } else if ($menu == "filtre") {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Save filter configuration
           # save_filter_setup($id);
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
       ## render_filter_setup($id, $filters_updated, $filters);
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
    // In create_datagrid function, after render_dropdown_script call:
    render_ajax_search_script($id);

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
        if(is_array($defaultValues) && !empty($defaultValues)) {
            foreach ($defaultValues as $key => $defaultValue) {
                if (!isset($column[$key])) {
                    $column[$key] = $defaultValue;
                }
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
HTML;

    // Auto-preserve all non-grid parameters from URL
    $preservedParams = [];
    foreach ($_GET as $key => $value) {
        // Skip grid-specific parameters (they start with grid-specific patterns)
        $isGridParam = preg_match('/^(sort|menu|search|offset|rowcount)\[/', $key) || 
                       (is_array($value) && isset($value[$id]));
        
        if (!$isGridParam && $key != 'menu') {
            $preservedParams[$key] = $value;
        }
    }

    // Add preserved parameters as hidden fields
    foreach ($preservedParams as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $subKey => $subValue) {
                if (is_array($subValue)) {
                    foreach ($subValue as $subSubKey => $subSubValue) {
                        echo "<input type='hidden' name='{$key}[$subKey][$subSubKey]' value='" . htmlspecialchars($subSubValue) . "'>";
                    }
                } else {
                    echo "<input type='hidden' name='{$key}[$subKey]' value='" . htmlspecialchars($subValue) . "'>";
                }
            }
        } else {
            echo "<input type='hidden' name='$key' value='" . htmlspecialchars($value) . "'>";
        }
    }
    global $fokus;
    // Continue with grid hidden fields
    echo <<<HTML
            <input type="hidden" name='sort[{$id}]' value='$sort'>
            <input type="hidden" name='menu[{$id}]' value='$menu'>
            <input type="hidden" name='fokus[{$id}]' value='$fokus'>
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
            $columnSearchTerm1 = if_isset($column, NULL, 'field');
            $columnSearchTerm = if_isset($searchTerms,NULL, $columnSearchTerm1);
            #$columnSearchTerm = if_isset($searchTerms[$column['field']], '');
            echo "<input class='inputbox' style='text-align: $column[align]' type='text' name='search[$id][{$column['field']}]' value='$columnSearchTerm' placeholder=''>";
        }
        echo "</th>";
    }

    global $sprog_id;
    $txt1 = findtekst('913|Søg', $sprog_id);
    $txt2 = findtekst('2755|Ryd søgning', $sprog_id);
    $txt3 = findtekst('2756|Eksportér til CSV', $sprog_id);
    $txt4 = findtekst('2757|Eksportér til PDF', $sprog_id);
    $txt5 = findtekst('2148|Redigér', $sprog_id);
    $txt6 = findtekst('2758|Kolonner', $sprog_id);
    $txt7 = findtekst('2759|Filtre', $sprog_id);
    $txt8 = findtekst('2760|Vis SQL', $sprog_id);

    echo <<<HTML
                        <th>
                            <div class="dropdown">
                                <svg id="turn-arrow" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="34px" fill="#000000"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>
                                <div class="dropdown-content">
                                    <button type="submit" class="dropdown-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/></svg>
                                        {$txt1}
                                    </button>
                                    <button type="button" onclick="handleAction{$id}('clear')">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>
                                        {$txt2}
                                    </button>
                                    <button type="button" onclick="handleAction{$id}('exportCSV')">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm240-240H200v160h240v-160Zm80 0v160h240v-160H520Zm-80-80v-160H200v160h240Zm80 0h240v-160H520v160ZM200-680h560v-80H200v80Z"/></svg>
                                        {$txt3}
                                    </button>
                                    <button type="button" onclick="handleAction{$id}('exportPDF')">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M320-240h320v-80H320v80Zm0-160h320v-80H320v80ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/></svg>
                                        {$txt4}
                                    </button>
                                    <div id='edit-button' class="has-secondary-dropdown">
                                        <span>
                                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>
                                            {$txt5}
                                        </span>

                                        <svg id="turn-arrow2" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="34px" fill="#000000"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>
                                        <div class="secondary-dropdown">
                                            <button type="button" onclick="handleAction{$id}('kolonner')">
                                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M121-280v-400q0-33 23.5-56.5T201-760h559q33 0 56.5 23.5T840-680v400q0 33-23.5 56.5T760-200H201q-33 0-56.5-23.5T121-280Zm79 0h133v-400H200v400Zm213 0h133v-400H413v400Zm213 0h133v-400H626v400Z"/></svg>
                                                {$txt6}
                                            </button>
                                            <button type="button" onclick="handleAction{$id}('filtre')">
                                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M440-160q-17 0-28.5-11.5T400-200v-240L168-736q-15-20-4.5-42t36.5-22h560q26 0 36.5 22t-4.5 42L560-440v240q0 17-11.5 28.5T520-160h-80Zm40-308 198-252H282l198 252Zm0 0Z"/></svg>
                                                {$txt7}
                                            </button>
                                        </div>
                                    </div>

                                    <button type="button" onclick="handleAction{$id}('showSQL')">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M560-160v-80h120q17 0 28.5-11.5T720-280v-80q0-38 22-69t58-44v-14q-36-13-58-44t-22-69v-80q0-17-11.5-28.5T680-720H560v-80h120q50 0 85 35t35 85v80q0 17 11.5 28.5T840-560h40v160h-40q-17 0-28.5 11.5T800-360v80q0 50-35 85t-85 35H560Zm-280 0q-50 0-85-35t-35-85v-80q0-17-11.5-28.5T120-400H80v-160h40q17 0 28.5-11.5T160-600v-80q0-50 35-85t85-35h120v80H280q-17 0-28.5 11.5T240-680v80q0 38-22 69t-58 44v14q36 13 58 44t22 69v80q0 17 11.5 28.5T280-240h120v80H280Z"/></svg>
                                        {$txt8}
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
function render_table_footer($id, $selectedrowcount, $totalItems, $rowCount, $offset) {
    // Define the possible row count options
    $rowCounts = [50, 100, 250, 500, 1000, 5000, 999999999];

    // Build the options dynamically
    $options = '';
    foreach ($rowCounts as $count) {
        $selected = ($selectedrowcount == $count) ? 'selected' : '';
        $label = ($count == 999999999) ? 'Alle' : $count; // Use "Alle" for the max value
        $options .= "<option value=\"$count\" $selected>$label</option>";
    }

    // Calculate pagination details
    $currentPage = floor($offset / $selectedrowcount) + 1;
    $totalPages = ceil($totalItems / $selectedrowcount);
    $offsetFrom = $offset + 1;
    $offsetTo = min(array($totalItems, $selectedrowcount + $offset));
    $nextpage = min(array($totalItems, $offset + $selectedrowcount));
    $lastpage = max(array(0, $offset - $selectedrowcount));

    $nextpagestatus = $nextpage == $totalItems ? 'disabled' : '';
    $lastpagestatus = $offset == 0 ? 'disabled' : '';

    // Generate a subset of page links
    $pageLinks = '';
    $pageRange = 2; // Number of pages to show around the current page
    $startPage = max(1, $currentPage - $pageRange);
    $endPage = min($totalPages, $currentPage + $pageRange);

    if ($startPage > 1) {
        $pageLinks .= "<button class='navbutton' type='button' onclick='setOffset$id(0)'>1</button>";
        if ($startPage > 2) {
            $pageLinks .= "<span>...</span>";
        }
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
        $pageOffset = ($i - 1) * $selectedrowcount;
        $isActiveStyle = ($i == $currentPage) ? "style='text-decoration: underline;'" : "";
        $pageLinks .= "<button type='button' onclick='setOffset$id($pageOffset)' $isActiveStyle class='navbutton'>$i</button>";
    }

    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $pageLinks .= "<span>...</span>";
        }
        $lastPageOffset = ($totalPages - 1) * $selectedrowcount;
        $pageLinks .= "<button type='button' onclick='setOffset$id($lastPageOffset)' class='navbutton'>$totalPages</button>";
    }

    // Output the footer with dynamic options
    global $sprog_id;
    $txt1 = lcfirst(findtekst('2767|Af', $sprog_id));
    $txt2 = findtekst('2125|Linjer pr. side', $sprog_id);

    echo <<<HTML
            <tr>
                <td colspan=100>
                    <input type='hidden' name="offset[$id]" value="$offset" size='4'>
                    <div id='footer-box'>
                        <span style='display: flex' id='page-status'>
                            $offsetFrom-$offsetTo&nbsp;{$txt1}&nbsp;$totalItems
                        </span>
                        |
                        <span>{$txt2}
                            <select name="rowcount[$id]">
                                $options
                            </select>
                        </span>
                        |
                        <span id='navbuttons'>
                            <button type='button' onclick="setOffset$id($lastpage)" $lastpagestatus>
                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z"/></svg>
                            </button>
                            $pageLinks
                            <button type='button' onclick="setOffset$id($nextpage)" $nextpagestatus>
                                <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000000"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>
                            </button>
                        </span>
                    </div>
                </td>
            </tr>
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
        .datatable-wrapper.loading {
                    position: relative;
                    opacity: 0.7;
                    pointer-events: none;
                }
        
        .datatable-wrapper.loading::after {
            content: "Loading...";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 10px 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
            z-index: 1000;
        }
        
        select[name^="rowcount"] {
            padding: 4px 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
            background-color: white;
        }
        
        select[name^="rowcount"]:focus {
            outline: none;
            border-color: #007cba;
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
            // display: inline-block;
            display: none;
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

                // Keep only the 'page-status' element in the footer-box
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
 * Outputs JavaScript code for real-time search functionality.
 *
 * This function adds real-time search capabilities to the grid system,
 * automatically submitting the form when users type in search fields.
 *
 * @param string $id The unique identifier for the table.
 * @return void Outputs the embedded JavaScript for real-time search.
 */

function render_ajax_search_script($id) {
    global $o_art, $fokus, $bgcolor, $bgcolor5;
    
    // Determine the correct href based on order type
    $href = ($o_art === 'PO' || $o_art == 'KO') ? "pos_ordre.php" : "ordre.php";
    
    echo <<<SCRIPT
    <script>
    $(document).ready(function() {
        var searchTimeout;
        var gridId = '$id';
        var currentRequest = null;
        var isInitialDataLoaded = false;
        
        // Add real-time AJAX search to grid inputs
        $(document).on('input', 'input[name^="search[' + gridId + ']"]', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                performAjaxSearch(gridId);
            }, 500);
        });
        
        // Handle Enter key in search fields
        $(document).on('keypress', 'input[name^="search[' + gridId + ']"]', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                clearTimeout(searchTimeout);
                performAjaxSearch(gridId);
            }
        });
        
        // AJAX search function
        function performAjaxSearch(gridId) {
            // Abort previous request if still running
            if (currentRequest !== null) {
                currentRequest.abort();
            }
            
            // Show loading indicator in table body only
            $('#datatable-' + gridId + ' tbody').html('<tr><td colspan="100" style="text-align:center; padding:20px;"><i>Loading...</i></td></tr>');
            
            // Collect ALL parameters
            var allParams = getAllParameters();
            allParams.ajax = '1';
            allParams.grid_id = gridId;
            
           
            
            // Perform AJAX request to the data endpoint
            currentRequest = $.ajax({
                url: '../debitor/accountLookupData.php',
                method: 'GET',
                data: allParams,
                dataType: 'json',
                success: function(response) {
                    currentRequest = null;
                    
                    
                    if (response.success && response.data) {
                       
                        if (response.data.length > 0) {
                            
                        }
                        updateTableBodyOnly(gridId, response.data, response.totalRows, response.offset, response.rowsPerPage);
                        isInitialDataLoaded = true;
                    } else {
                       
                        $('#datatable-' + gridId + ' tbody').html('<tr><td colspan="100" style="text-align:center; padding:20px; color:red;">No data found</td></tr>');
                    }
                },
                error: function(xhr, status, error) {
                    currentRequest = null;
                    if (status !== 'abort') {
                        console.error('AJAX Error:', error);
                        $('#datatable-' + gridId + ' tbody').html('<tr><td colspan="100" style="text-align:center; padding:20px; color:red;">Search error: ' + error + '</td></tr>');
                    }
                }
            });
        }
        
        // Function to update ONLY the table body (not footer)
        function updateTableBodyOnly(gridId, data, totalRows, offset, rowsPerPage) {
            var tbody = $('#datatable-' + gridId + ' tbody').css({'border-collapse': 'collapse'});
            tbody.empty();
            
            if (!data || data.length === 0) {
                tbody.html('<tr><td colspan="100" style="text-align:center; padding:20px;">No results found</td></tr>');
                updatePaginationInfo(gridId, 0, totalRows, offset, rowsPerPage);
                return;
            }
            
         
            
            // Build rows from data
            $.each(data, function(index, row) {
                var tr = $('<tr>');
                
                // Alternate row background color
                var rowColor = (index % 2 === 0) ? '$bgcolor' : '$bgcolor5';
                tr.css('background-color', rowColor);
                
               
                
                // Add each column as clickable cells
                var columns = ['kontonr', 'firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'land', 'kontakt', 'tlf'];
                
                $.each(columns, function(i, field) {
                    var td = $('<td>').css({
                        'cursor': 'pointer',
                        'padding': '4px',
                        'text-align': 'left'
                    });
                    
                    // Make kontonr a link, others plain text
                    if (field === 'kontonr') {
                        // Use row.id for konto_id parameter (this is the account ID from database)
                        var link = $('<a>')
                            .attr('href', '$href?fokus=$fokus&konto_id=' + (row.id || ''))
                            .text(row[field] || '')
                            .css('color', 'inherit')
                            .css('text-decoration', 'none')
                            .css('display', 'block');
                        td.append(link);
                    } else {
                        td.text(row[field] || '');
                    }
                    
                    tr.append(td);
                });
                
                // Add filler column if needed
                tr.append($('<td>').addClass('filler-row'));
                
                // Make entire row clickable (except the kontonr link)
                tr.on('click', function(e) {
                    // Don't trigger if clicking the kontonr link
                    if (!$(e.target).is('a') && !$(e.target).parents('a').length) {
                       
                        // Use row.id for konto_id parameter
                        var redirectUrl = '$href?fokus=$fokus&konto_id=' + (row.id || '');
                        
                        window.location.href = redirectUrl;
                    }
                });
                
                // Hover effect
                tr.hover(
                    function() { 
                        $(this).css('background-color', '#f5f5f5'); 
                    },
                    function() { 
                        $(this).css('background-color', rowColor); 
                    }
                );
                
                tbody.append(tr);
            });
            
            // Add filler rows if needed (to maintain table height)
            var currentRowCount = data.length;
            var selectedRowCount = parseInt($('select[name="rowcount[' + gridId + ']"]').val()) || 100;
            
            if (selectedRowCount < 1000 && currentRowCount < selectedRowCount) {
                for (var i = 0; i < selectedRowCount - currentRowCount; i++) {
                    var fillerRow = $('<tr style="background-color: unset; pointer-events: none;" class="filler-row">');
                    fillerRow.append($('<td colspan="100">').html('-&nbsp;'));
                    tbody.append(fillerRow);
                }
            }
            
            // Update pagination info without touching footer structure
            updatePaginationInfo(gridId, data.length, totalRows, offset, rowsPerPage);
        }
        
        // Function to update pagination information WITHOUT modifying footer structure
        function updatePaginationInfo(gridId, currentCount, totalRows, offset, rowsPerPage) {
            var offsetFrom = parseInt(offset) + 1;
            var offsetTo = Math.min(totalRows, parseInt(offset) + currentCount);
            
            // Update the page status display - find it within the existing footer
            var pageStatus = $('#datatable-' + gridId + ' tfoot #page-status');
            if (pageStatus.length) {
                pageStatus.text(offsetFrom + '-' + offsetTo + ' af ' + totalRows);
            }
            
            // Update offset hidden field in the footer
            var offsetField = $('#datatable-' + gridId + ' tfoot input[name="offset[' + gridId + ']"]');
            if (offsetField.length) {
                offsetField.val(offset);
            }
            
            // Update pagination buttons state
            updatePaginationButtons(gridId, offset, rowsPerPage, totalRows);
        }
        
        // Function to update pagination buttons state
        function updatePaginationButtons(gridId, offset, rowsPerPage, totalRows) {
            var currentPage = Math.floor(offset / rowsPerPage) + 1;
            var totalPages = Math.ceil(totalRows / rowsPerPage);
            
            // Update next/previous button states
            var nextButton = $('#datatable-' + gridId + ' tfoot button[onclick*="setOffset' + gridId + '"]').last();
            var prevButton = $('#datatable-' + gridId + ' tfoot button[onclick*="setOffset' + gridId + '"]').first();
            
            // Disable previous button on first page
            if (offset <= 0) {
                prevButton.prop('disabled', true).css('opacity', '0.5');
            } else {
                prevButton.prop('disabled', false).css('opacity', '1');
            }
            
            // Disable next button on last page
            if (offset + rowsPerPage >= totalRows) {
                nextButton.prop('disabled', true).css('opacity', '0.5');
            } else {
                nextButton.prop('disabled', false).css('opacity', '1');
            }
        }
        
        // Function to get ALL parameters
        function getAllParameters() {
            var params = {};
            
            // Get grid-specific parameters from form
            $('form').first().find('input[name^="search[' + gridId + ']"], input[name^="sort[' + gridId + ']"], input[name^="offset[' + gridId + ']"], input[name^="rowcount[' + gridId + ']"], input[name^="menu[' + gridId + ']"]').each(function() {
                var \$el = $(this);
                var name = \$el.attr('name');
                if (name) {
                    params[name] = \$el.val();
                }
            });
            
            return params;
        }
        
        // Override the setOffset function to use AJAX
        var originalSetOffset = window['setOffset' + gridId];
        if (originalSetOffset) {
            window['setOffset' + gridId] = function(offset) {
                // Update the offset field
                var offsetField = $('input[name="offset[' + gridId + ']"]');
                if (offsetField.length) {
                    offsetField.val(offset);
                }
                // Trigger search with new offset
                performAjaxSearch(gridId);
            };
        }
        
        // Override rowcount change to use AJAX
        $('select[name="rowcount[' + gridId + ']"]').off('change').on('change', function() {
            // Reset to first page when changing row count
            $('input[name="offset[' + gridId + ']"]').val(0);
            performAjaxSearch(gridId);
        });
        
        // REMOVED: Initial data load - let the PHP-generated content stay as is
        // The initial data is already loaded by PHP, only use AJAX for subsequent interactions
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
/**
 * Outputs JavaScript code for setting the offset value and row count via AJAX.
 */
/**
 * Outputs JavaScript code for AJAX pagination and row count
 */
function render_pagination_script($id) {
    echo <<<SCRIPT
    <script>
        function setOffset$id(offset) {
            const offsetBox = document.getElementsByName('offset[$id]')[0];
            if (offsetBox) {
                offsetBox.value = offset;
                submitGridForm$id();
            }
        }
        
        // Initialize event listeners when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            initializeRowCountHandler$id();
        });
        
        function initializeRowCountHandler$id() {
            const rowCountSelect = document.querySelector('select[name="rowcount[$id]"]');
            if (rowCountSelect) {
                // Store the current value before removing listeners
                const currentValue = rowCountSelect.value;
                
                // Remove all existing event listeners by replacing the element
                const newSelect = rowCountSelect.cloneNode(true);
                rowCountSelect.parentNode.replaceChild(newSelect, rowCountSelect); 
                
                // Set the value on the new select element
                newSelect.value = currentValue;
                
                // Add event listener to the new select
                newSelect.addEventListener('change', function() {
                     
                    
                    // Reset to first page when changing row count
                    const offsetBox = document.getElementsByName('offset[$id]')[0];
                    if (offsetBox) {
                        offsetBox.value = 0;
                    }
                    
                    // Submit via AJAX
                    submitGridForm$id();
                });
                
                
            }
        }
        
        function submitGridForm$id() {
            const form = document.querySelector('#datatable-wrapper-$id form');
            if (!form) {
                
                return;
            }
            
            // Show loading indicator
            showLoading$id(true);
            
            // Collect all form data
            const formData = new FormData(form);
            formData.append('ajax', '1');
            formData.append('grid_id', '$id');
            
            // Add any additional parameters that might be in URL
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.forEach((value, key) => {
                if (!formData.has(key)) {
                    formData.append(key, value);
                }
            });
            
            // Send AJAX request
            fetch('../debitor/accountLookupData.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    updateTableContent$id(data);
                } else {
                    
                    // Fallback to regular form submission
                    form.submit();
                }
            })
            .catch(error => {
                
                // Fallback to regular form submission
                form.submit();
            })
            .finally(() => {
                showLoading$id(false);
            });
        }
        
        function showLoading$id(show) {
            const wrapper = document.getElementById('datatable-wrapper-$id');
            const tbody = document.querySelector('#datatable-$id tbody');
            
            if (show) {
                // Add loading state
                wrapper.classList.add('loading');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="100" style="text-align:center; padding:20px;"><i>Loading...</i></td></tr>';
                }
            } else {
                wrapper.classList.remove('loading');
            }
        }
        
       function updateTableContent$id(data) {
            if (!data || !data.success) {
                console.error('AJAX request failed:', data);
                return;
            }
            
            // Use the raw data to build HTML on the frontend
            buildTableFromData$id(data.data, data.totalRows, data.offset, data.rowsPerPage);
        }
            ////////
            function buildTableFromData$id(data, totalRows, offset, rowsPerPage) {
                const tbody = document.querySelector('#datatable-$id tbody');
                if (!tbody) return;
                
                let html = '';
                
                if (!data || data.length === 0) {
                    html = '<tr><td colspan="100" style="text-align:center; padding:20px;">No results found</td></tr>';
                } else {
                    // Build table rows from raw data
                    data.forEach((row, index) => {
                        const rowColor = (index % 2 === 0) ? '#ffffff' : '#e0e0f0';
                        html += `<tr style="background-color: ${rowColor}">`;
                        
                        const columns = ['kontonr', 'firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'land', 'kontakt', 'tlf'];
                        columns.forEach(field => {
                            // Your existing cell rendering logic here
                            html += '<td>' + (row[field] || '') + '</td>';
                        });
                        
                        html += `<td class="filler-row"></td></tr>`;
                    });
                }
                
                tbody.innerHTML = html;
                updatePaginationInfo$id(data.length, totalRows, offset, rowsPerPage);
            }

            /// build table from data end
        
        function updatePaginationInfo$id(data) {
            // Update the page status display
            const offsetFrom = parseInt(data.offset) + 1;
            const offsetTo = Math.min(data.totalRows, parseInt(data.offset) + data.rowsPerPage);
            
            const pageStatus = document.querySelector('#datatable-$id tfoot #page-status');
            if (pageStatus) {
                pageStatus.textContent = offsetFrom + '-' + offsetTo + ' af ' + data.totalRows;
            }
            
            // Update pagination buttons state
            updatePaginationButtons$id(data.offset, data.rowsPerPage, data.totalRows);
        }
        
        function updatePaginationButtons$id(offset, rowsPerPage, totalRows) {
            const currentPage = Math.floor(offset / rowsPerPage) + 1;
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            
            // Update next/previous button states
            const nextButton = document.querySelector('#datatable-$id tfoot button:last-child');
            const prevButton = document.querySelector('#datatable-$id tfoot button:first-child');
            
            if (prevButton) {
                if (offset <= 0) {
                    prevButton.disabled = true;
                    prevButton.style.opacity = '0.5';
                } else {
                    prevButton.disabled = false;
                    prevButton.style.opacity = '1';
                }
            }
            
            if (nextButton) {
                if (offset + rowsPerPage >= totalRows) {
                    nextButton.disabled = true;
                    nextButton.style.opacity = '0.5';
                } else {
                    nextButton.disabled = false;
                    nextButton.style.opacity = '1';
                }
            }
        }
        
        function updateURL$id() {
            // Update browser URL without reloading
            if (history.pushState) {
                const url = new URL(window.location);
                const form = document.querySelector('#datatable-wrapper-$id form');
                
                if (form) {
                    const formData = new FormData(form);
                    
                    // Update URL parameters for this grid
                    for (let [key, value] of formData.entries()) {
                        if (key.includes('[$id]')) {
                            url.searchParams.set(key, value);
                        }
                    }
                    
                    history.pushState({}, '', url);
                }
            }
        }
        
        function saveScrollPosition() {
            var element = document.getElementById('datatable-wrapper-$id');
            if (element) {
                var scrollKey = 'scrollpos-datatable-$id';
                localStorage.setItem(scrollKey, element.scrollTop);
            }
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