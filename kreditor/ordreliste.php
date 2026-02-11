<?php
// --- kreditor/ordreliste.php -----patch 5.0.0 ----2026-02-11---------
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 2014.03.19 addslashes erstattet med db_escape_string
// 2104.09.16   Tilføjet oioublimport i bunden
// 20211125 PHR Added 'Skan Bilag'
// 20220728 MSC - Implementing new design
// 20221106 PHR - Various changes to fit php8 / MySQLi
// 20230317 LOE - Applied some translated texts, and Also fixed some undefined variable errors and some more.
// 20230525 PHR - php8
// 20231017 PHR Fixed an error in account selection ($firma);
// 20240510 LOE fixed Undefined array key 1...file. Added a condition to list call on $firma
// 20250415 LOE Updated some variables using if_isset and some clean up
// 20251118 LOE Added datagrid for better performance and more features
// 20260204 Saul Added more fields  in edit column for kreditor/order
// 20260211 LOE Added tjek back to url. 

ob_start();
@session_start();
$s_id = session_id(); 

$css = "../css/std.css";
$modulnr = 5;
$title = "Leverandører • Ordreliste";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");
include("../includes/topline_settings.php");
include (get_relative()."includes/kreditorOrderFuncIncludes/creditor_orderlist_grid.php");
$valg = if_isset($_GET, 'ordrer','valg');

// Fetch ALL columns from the ordrer table (same as debitor/ordreliste.php)
$all_db_columns = array();
$qtxt = "SELECT column_name, data_type 
         FROM information_schema.columns 
         WHERE table_schema = 'public' 
           AND table_name = 'ordrer' 
         ORDER BY ordinal_position";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    $all_db_columns[$r['column_name']] = $r['data_type'];
}
$returside= if_isset($_GET, NULL,'returside');
$returside = if_isset($returside, '../index/menu.php');

if ($returside == 'ordreliste.php') $returside = NULL;
if (!$returside) {
    if ($popup) $returside = "../includes/luk.php";
    else $returside = '../index/menu.php';
}



##################

// Store the original valg from URL parameter
$original_valg = $valg;

// Run the extraction loop
foreach ($_GET as $category => $data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (strpos($key, 'kredorliste_') === 0) {
                $extracted_valg = substr($key, strlen('kredorliste_'));
                // Only use extracted valg if it matches a valid option
                if (in_array($extracted_valg, ['forslag', 'ordrer', 'faktura'])) {
                    $valg = $extracted_valg;
                }
                break 2;
            }
        }
    }
}
// Fallback to original valg if extraction failed
if (!in_array($valg, ['forslag', 'ordrer', 'faktura'])) {
    $valg = $original_valg;
}

###############
if (isset($valg)) {
    // Set the cookie to store the 'valg' value
    setcookie("valg", $valg, time() + 3600 * 24, "/"); // Expires in 24 hours
}

# >> Date picker scripts <<
// Note: jQuery is already loaded by online.php - don't load again to avoid overwriting autosize plugin
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/moment.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/daterangepicker.min.js\" defer></script>";
print '<link rel="stylesheet" type="text/css" href="../css/daterangepicker.css" />';
include("../includes/row-hover-style-with-links.js.php");
include("../includes/datepkr.php");


##################

// Check for paperflow setting
$paperflow = NULL;
$qtxt = "select var_value from settings where var_grp='creditor' and var_name='paperflow'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $paperflow = $r['var_value'];
}

// Check if project column should be visible
$vis_projekt = 'off';
$qtxt = "select distinct id from ordrer where (art='DK' or art='KK') and projekt > '0' and (status = 1 or status = 2)";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $vis_projekt = 'on';
}

// Check for hurtigfakt
$hurtigfakt = 'off';
$qtxt = "select id from grupper where art = 'DIV' and kodenr = '3' and box4='on'";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $hurtigfakt = 'on';
}

ob_end_flush();

// Render header
if ($menu == 'T') {
    include_once '../includes/top_header.php';
    include_once '../includes/top_menu.php';
    print "<div id=\"header\">";
    print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";
    print "<div class=\"headerTxt\">$title</div>";
    print "<div class=\"headerbtnRght headLink\">";
    print "<a accesskey=N href='ordre.php?returside=ordreliste.php' title='Opret ny ordre'><i class='fa fa-plus-square fa-lg'></i></a>";
    print "</div>";
    print "</div>";
    print "<div class='content-noside'>";
} else {
 include_once '../includes/kreditorOrderFuncIncludes/topLine.php';
}

////// Tutorial //////

$steps = array();
$steps[] = array(
        "selector" => "#ordrer",
        "content" => findtekst('2610|Her ser du en liste af alle dine ordrer', $sprog_id)."."
);
$steps[] = array(
        "selector" => "#ny",
        "content" => findtekst('2611|For at oprette en ny ordre, klik her', $sprog_id)."."
);

include(__DIR__ . "/../includes/tutorial.php");
create_tutorial("kreOrdList", $steps);

////// Tutorial end //////


 print "</td></tr>\n<tr><td align=center valign=top>";

// Define status filter based on valg
if ($valg == "forslag") {
    $status = "status = 0";
} elseif ($valg == "faktura") {
    $status = "status >= 3";
} else {
    $status = "(status = 1 OR status = 2)";
}
$metaColumnHeaders = ['Handlinger'];

// Define custom columns with proper rendering (same structure as debitor/ordreliste.php)
$custom_columns = array(
    "ordrenr" => array(
        "field" => "ordrenr",
        "headerName" => findtekst('500|Ordrenr.', $sprog_id),
        "width" => "1",
        "align" => "right",
        "type"  => "number",
        "sortable" => true,
        "defaultSort" => true,
        "defaultSortDirection" => "desc",
        "searchable" => true,
        "decimalPrecision" => 0,
        "valueGetter" => function($value, $row, $column) {
            return $value;
        },
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
    
    "ordredate" => array(
        "field" => "ordredate",
        "headerName" => ($valg == "forslag") ? findtekst('889|Tilbudsdato', $sprog_id) : findtekst('881|Ordredato', $sprog_id),
        "width" => "1",
        "type" => "date",
        "searchable" => true,
        "sqlOverride" => "ordrer.ordredate",
        "valueGetter" => function($value, $row, $column) {
            return dkdato($value);
        },
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>" . $value . "</td>";
        }
    ),
    
    "levdate" => array(
        "field" => "levdate",
        "headerName" => findtekst('941|Modt.dato', $sprog_id),
        "width" => "1",
        "type" => "date",
        "searchable" => true,
        "valueGetter" => function($value, $row, $column) {
            return dkdato($value);
        },
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>" . $value . "</td>";
        }
    ),
    
    "modtagelse" => array(
        "field" => "modtagelse",
        "headerName" => findtekst('940|Modt.nr.', $sprog_id),
        "width" => "1",
        "align" => "right",
        "type" => "number",
        "searchable" => true,
        "decimalPrecision" => 0,
        "hidden" => ($valg != "faktura"),
        "valueGetter" => function($value, $row, $column) {
            return $value;
        },
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
    
    "fakturanr" => array(
        "field" => "fakturanr",
        "headerName" => findtekst('882|Fakt.nr.', $sprog_id),
        "width" => "1",
        "align" => "right",
        "type" => "text",
        "searchable" => true,
        "hidden" => ($valg != "faktura"),
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
    
    "fakturadate" => array(
        "field" => "fakturadate",
        "headerName" => findtekst('883|Fakt.dato', $sprog_id),
        "width" => "1",
        "type" => "date",
        "searchable" => true,
        "hidden" => ($valg != "faktura"),
        "valueGetter" => function($value, $row, $column) {
            return dkdato($value);
        },
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>" . $value . "</td>";
        }
    ),
    
    "kontonr" => array(
        "field" => "kontonr",
        "headerName" => findtekst('804|Kontonr.', $sprog_id),
        "width" => "1",
        "type" => "number",
        "align" => "left",
        "searchable" => true,
        "decimalPrecision" => 0,
        "sqlOverride" => "ordrer.kontonr",
        "valueGetter" => function($value, $row, $column) {
            return $value;
        },
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
    
    "firmanavn" => array(
        "field" => "firmanavn",
        "headerName" => findtekst('360|Firmanavn', $sprog_id),
        "width" => "2",
        "type" => "text",
        "searchable" => true,
        "sqlOverride" => "ordrer.firmanavn",
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
    
    "lev_navn" => array(
        "field" => "lev_navn",
        "headerName" => findtekst('814|Leveres til', $sprog_id),
        "width" => "2",
        "type" => "text",
        "searchable" => true,
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
    
    "ref" => array(
        "field" => "ref",
        "headerName" => findtekst('884|Sælger', $sprog_id),
        "width" => "1",
        "type" => "text",
        "searchable" => true,
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
    
    "sum" => array(
        "field" => "sum",
        "headerName" => ($valg == "faktura") ? findtekst('885|Fakturasum', $sprog_id) : (($valg == "forslag") ? findtekst('826|Forslagssum', $sprog_id) : findtekst('887|Ordresum', $sprog_id)),
        "width" => "1",
        "type" => "number",
        "align" => "right",
        "decimalPrecision" => 2,
        "searchable" => true,
        "valueGetter" => function($value, $row, $column) {
            $sum = $value;
            if ($row['valutakurs'] && $row['valutakurs'] != 100) {
                $sum = $sum * $row['valutakurs'] / 100;
            }
            return dkdecimal($sum);
        },
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
);

// Build the FINAL $columns array dynamically (same as debitor/ordreliste.php)
$columns = array();

// Define explicit default columns for each view type
$explicit_default_columns = array();
if ($valg == "forslag") {
    $explicit_default_columns = array("ordrenr", "ordredate", "kontonr", "firmanavn", "lev_navn", "ref", "sum");
} elseif ($valg == "faktura") {
    $explicit_default_columns = array("ordrenr", "ordredate", "modtagelse", "fakturanr", "fakturadate", "kontonr", "firmanavn", "lev_navn", "ref", "sum");
} else {
    $explicit_default_columns = array("ordrenr", "ordredate", "levdate", "kontonr", "firmanavn", "lev_navn", "ref", "sum");
}

// Check if user has saved column preferences
$qtxt = "select box3 from grupper where art = 'KLV' and kodenr = '$bruger_id' and kode='$valg'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$user_column_names = array();

if ($r && $r['box3']) {
    // User has custom column setup
    $user_column_names = explode(",", $r['box3']);
    $user_column_names = array_map('trim', $user_column_names);
    $active_column_names = $user_column_names;
} else {
    // Use explicit defaults based on view type
    $active_column_names = $explicit_default_columns;
}

// First, add all custom columns
foreach ($custom_columns as $field_name => $column_def) {
    // Set hidden property based on whether column is in active list
    $column_def['hidden'] = !in_array($field_name, $active_column_names);
    $columns[] = $column_def;
}

// Add all other database columns dynamically (same as debitor/ordreliste.php)
foreach ($all_db_columns as $field_name => $data_type) {
    // Skip technical/internal fields and fields already in custom_columns
    $skip_fields = ['id', 'hvem', 'tidspkt', 'copied', 'scan_id'];
    if (in_array($field_name, $skip_fields) || isset($custom_columns[$field_name])) {
        continue;
    }
    
    // Create automatic definition
    $column_def = array(
        "field" => $field_name,
        "headerName" => ucfirst(str_replace('_', ' ', $field_name)),
        "width" => "1.5",
        "type" => "text",
        "align" => "left",
        "sortable" => true,
        "searchable" => true,
        "hidden" => !in_array($field_name, $active_column_names),
        "sqlOverride" => "ordrer.$field_name", 
    );
    
    // AUTO-DETECT TYPE AND ADD BOTH valueGetter AND render FUNCTIONS
    // Date fields
    if (strpos($field_name, 'date') !== false || 
        in_array($field_name, ['ordredate', 'levdate', 'fakturadate', 'nextfakt', 'due_date', 'datotid', 'settletime'])) {
        
        $column_def['type'] = 'date';
        $column_def['align'] = 'left';
        
        $column_def['valueGetter'] = function ($value, $row, $column) {
            return $value;
        };
        
        $column_def['render'] = function ($value, $row, $column) {
            $formatted = $value ? dkdato($value) : '';
            return "<td align='{$column['align']}'>" . htmlspecialchars($formatted) . "</td>";
        };
    }
    // konto_id should be text, not number
    elseif ($field_name == 'konto_id') {
        $column_def['type'] = 'text';
        $column_def['align'] = 'left';
        
        $column_def['valueGetter'] = function ($value, $row, $column) {
            return $value !== null ? $value : '';
        };
        
        $column_def['render'] = function ($value, $row, $column) {
            return "<td align='{$column['align']}'>" . htmlspecialchars($value) . "</td>";
        };
    }
    // Numeric fields
    elseif ($data_type == 'numeric' || $data_type == 'integer' || 
            strpos($field_name, 'sum') !== false || 
            strpos($field_name, 'nr') !== false ||
            strpos($field_name, 'id') !== false ||
            in_array($field_name, ['kostpris', 'moms', 'procenttillag', 'netweight', 'grossweight', 'valutakurs', 'betalingsdage', 'kontakt_tlf', 'phone', 'report_number'])) {
        
        $column_def['type'] = 'number';
        $column_def['align'] = 'right';
        $column_def['decimalPrecision'] = ($data_type == 'integer') ? 0 : 2;
        
        $column_def['valueGetter'] = function ($value, $row, $column) {
            return is_numeric($value) ? $value : 0;
        };
        
        $column_def['render'] = function ($value, $row, $column) {
            if (is_numeric($value)) {
                $precision = isset($column['decimalPrecision']) ? $column['decimalPrecision'] : 2;
                $formatted = dkdecimal($value, $precision);
            } else {
                $formatted = '';
            }
            return "<td align='{$column['align']}'>" . htmlspecialchars($formatted) . "</td>";
        };
    } 
    // Boolean/status fields (0/1 values)
    elseif (in_array($field_name, ['betalt', 'restordre', 'vis_lev_addr', 'pbs', 'mail_fakt', 'omvbet'])) {
        
        $column_def['type'] = 'dropdown';
        $column_def['align'] = 'center';
        
        $column_def['valueGetter'] = function ($value, $row, $column) {
            return $value;
        };
        
        $column_def['render'] = function ($value, $row, $column) {
            if ($value == '1' || $value === true) {
                $display = '✓';
                $color = '#008000';
            } elseif ($value == '0' || $value === false) {
                $display = '✗';
                $color = '#FF0000';
            } else {
                $display = '';
                $color = '#000000';
            }
            return "<td align='{$column['align']}' style='color: $color;'>" . htmlspecialchars($display) . "</td>";
        };
    }
    // Default text fields
    else {
        $column_def['valueGetter'] = function ($value, $row, $column) {
            return $value !== null ? $value : '';
        };
        
        $column_def['render'] = function ($value, $row, $column) {
            return "<td align='{$column['align']}'>" . htmlspecialchars($value) . "</td>";
        };
    }
    
    // Add dropdown options for specific fields
    if (in_array($field_name, ['status', 'valuta', 'sprog', 'art', 'udskriv_til', 'betalt', 'restordre', 'shop_status', 'digital_status'])) {
        $column_def['type'] = 'dropdown';
        $column_def['dropdownOptions'] = function() use ($field_name, $valg) {
            $options = array();
            
            if ($valg == "forslag") {
                $status_condition = "status < 1";
            } elseif ($valg == "faktura") {
                $status_condition = "status >= 3";
            } else {
                $status_condition = "(status = 1 OR status = 2)";
            }
            
            $qtxt = "SELECT DISTINCT $field_name FROM ordrer 
                     WHERE $field_name IS NOT NULL 
                     AND trim($field_name::text) != '' 
                     AND (art = 'KO' OR art = 'KK')
                     AND $status_condition
                     ORDER BY $field_name";
            $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            while ($r = db_fetch_array($q)) {
                if (trim($r[$field_name]) != '') {
                    $options[] = $r[$field_name];
                }
            }
            return $options;
        };
    }
    
    $columns[] = $column_def;
}

// Build the SQL query to include ALL columns dynamically
$select_fields = "ordrer.id as id";
foreach ($all_db_columns as $field_name => $data_type) {
    if ($field_name != 'id') {
        $select_fields .= ", ordrer.$field_name";
    }
}

$query = "SELECT $select_fields
          FROM ordrer 
          WHERE (art = 'KO' OR art = 'KK') AND $status AND {{WHERE}}
          ORDER BY {{SORT}}";

// Configure metaColumn (action buttons) based on valg
if ($valg == 'forslag') {
    $metaColumn = function($row) {
        return "<td>
            <div style='display:flex;gap:5px;'>
                <a href='formularprint.php?id={$row['id']}&formular=12&udskriv_til=PDF' target='_blank' title='Klik for at printe tilbud' onclick='event.stopPropagation();'>
                    <svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'>
                        <path d='M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z'/>
                    </svg>
                </a>" .
                ($row['email'] ? "
                <a href='formularprint.php?id={$row['id']}&formular=12&udskriv_til=email' target='_blank' title='Klik for at sende tilbud via email' onclick='event.stopPropagation(); return confirm(\"Er du sikker på, at du vil sende fakturaen?\\nKundens mail: {$row['email']}\")'>
                    <svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'>
                        <path d='M168-192q-29.7 0-50.85-21.16Q96-234.32 96-264.04v-432.24Q96-726 117.15-747T168-768h624q29.7 0 50.85 21.16Q864-725.68 864-695.96v432.24Q864-234 842.85-213T792-192H168Zm312-240L168-611v347h624v-347L480-432Zm0-85 312-179H168l312 179Zm-312-94v-85 432-347Z'/>
                    </svg>
                </a>" : "") .
            "</div>
        </td>";
    };
} elseif ($valg == 'ordrer') {
    $metaColumn = function($row) {
        return "<td>
            <div style='display:flex;gap:5px;'>
                <a href='formularprint.php?id={$row['id']}&formular=13&udskriv_til=PDF' target='_blank' title='Klik for at printe ordre' onclick='event.stopPropagation();'>
                    <svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'>
                        <path d='M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z'/>
                    </svg>
                </a>
            </div>
        </td>";
    };
} else { // faktura
    $metaColumn = function($row) {
        return "<td>
            <div style='display:flex;gap:5px;'>
                <a href='formularprint.php?id={$row['id']}&formular=14&udskriv_til=PDF' target='_blank' title='Klik for at printe faktura' onclick='event.stopPropagation();'>
                    <svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'>
                        <path d='M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z'/>
                    </svg>
                </a>" .
                ($row['email'] ? "
                <a href='formularprint.php?id={$row['id']}&formular=14&udskriv_til=email' target='_blank' title='Klik for at sende faktura via email' onclick='event.stopPropagation(); return confirm(\"Er du sikker på, at du vil sende fakturaen?\\nKundens mail: {$row['email']}\")'>
                    <svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'>
                        <path d='M168-192q-29.7 0-50.85-21.16Q96-234.32 96-264.04v-432.24Q96-726 117.15-747T168-768h624q29.7 0 50.85 21.16Q864-725.68 864-695.96v432.24Q864-234 842.85-213T792-192H168Zm312-240L168-611v347h624v-347L480-432Zm0-85 312-179H168l312 179Zm-312-94v-85 432-347Z'/>
                    </svg>
                </a>" : "") .
            "</div>
        </td>";
    };
}

// Add row click handler to navigate to order
$rowStyle = function($row) {
    return "cursor: pointer;";
};
// Add row attributes including order ID
$rowAttributes = function($row) {
    return "data-order-id='{$row['id']}'";
};

// Create grid configuration
$grid_data = [
    'columns' => $columns,
    'query' => $query,
    'filters' => [], 
    'metaColumn' => $metaColumn,
    'metaColumnHeaders' => $metaColumnHeaders,
    'rowStyle' =>  $rowStyle,
    'rowAttributes' => $rowAttributes  
];

print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
// Render the datagrid
$rows = create_datagrid("kredorliste_$valg", $grid_data);

print "</td></tr>\n";
print "</tbody></table>";

// Add row click handler for navigation
print <<<SCRIPT
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('.datatable tbody tr:not(.filler-row)');
    rows.forEach(function(row, index) {
        row.addEventListener('click', function(e) {
            // Don't navigate if clicking on action buttons
            if (e.target.closest('a')) return;
            
            // Get the order ID from the data
            const orderId = row.dataset.orderId;
            if (orderId) {
                window.location.href = 'ordre.php?tjek='+orderId+'&id='+orderId+'&returside=ordreliste.php';
            }
        });
    });
});
</script>
SCRIPT;

// Add order ID as data attribute to rows
print <<<SCRIPT
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.querySelector('.datatable tbody');
    if (tbody) {
        const trs = tbody.querySelectorAll('tr:not(.filler-row)');
        trs.forEach(function(tr, index) {
SCRIPT;

foreach ($rows as $index => $row) {
    print "if (index === $index) tr.dataset.orderId = '{$row['id']}';\n";
}

print <<<SCRIPT
        });
    }
});
</script>
SCRIPT;


##################

##################

if ($menu == 'T') {
    include_once '../includes/topmenu/footer.php';
} else {
    include_once '../includes/oldDesign/footer.php'; 
}
?>

<script>
// Datepicker initialization code with AJAX support
function initializeDatePickers() {

    // All date inputs in the datagrid
    const dateInputs = document.querySelectorAll(
        "input[name^='search[kredorliste_'][name$='[ordredate]'], " +
        "input[name^='search[kredorliste_'][name$='[levdate]'], " +
        "input[name^='search[kredorliste_'][name$='[fakturadate]']"
    );

    dateInputs.forEach(function(input) {

        // Avoid double initialization
        if (input.hasAttribute('data-datepicker-initialized')) return;

        let existingValue = input.value.trim();
        let startDate = moment();

        // Parse existing date if valid
        if (existingValue !== '') {
            const parsed = moment(existingValue, 'DD-MM-YYYY', true);
            if (parsed.isValid()) startDate = parsed;
        }

        // Initialize daterangepicker
        $(input).daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            autoUpdateInput: false,
            autoApply: false,
            startDate: startDate,
            minYear: 1900,
            maxYear: parseInt(moment().format('YYYY'), 10) + 10,
            locale: {
                format: 'DD-MM-YYYY',
                cancelLabel: 'Ryd',
                applyLabel: 'Søg',
                daysOfWeek: ['Sø', 'Ma', 'Ti', 'On', 'To', 'Fr', 'Lø'],
                monthNames: [
                    'Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni',
                    'Juli', 'August', 'September', 'Oktober', 'November', 'December'
                ],
                firstDay: 1
            }
        });

        // Mark as initialized
        input.setAttribute('data-datepicker-initialized', 'true');

        // Show existing value
        if (existingValue !== '') {
            $(input).val(existingValue);
        }

        //
        //  APPLY (Søg)
        //
        $(input).on('apply.daterangepicker', function(ev, picker) {
            const selectedDate = picker.startDate.format('DD-MM-YYYY');
            $(this).val(selectedDate);

            const el = this;
            setTimeout(() => {
                el.focus(); // keep focus on apply
                el.setSelectionRange(el.value.length, el.value.length);
            }, 30);

            // Submit form
            const form = $(this).closest('form');
            if (form.length > 0) form.submit();
        });

        //
        //  CANCEL (Ryd)
        //
        $(input).on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');

            const el = this;
            setTimeout(() => {
                el.focus(); // keep focus on cancel
                el.setSelectionRange(0, 0);
            }, 30);

            // Submit form
            const form = $(this).closest('form');
            if (form.length > 0) form.submit();
        });

        //  DO NOT force focus when user clicks outside
        $(input).on('hide.daterangepicker', function(ev, picker) {
            if (picker.clickApply || picker.clickCancel) return;
            // clicking outside → do nothing
        });

    });
}

// Initialize after page load
document.addEventListener('DOMContentLoaded', function() {
    initializeDatePickers();
});
</script>



<script>
// Get the 'valg' parameter from the URL (if it exists)
let valgParam = new URLSearchParams(window.location.search).get('valg');

// If 'valg' isn't in the URL, check if PHP variable '$valg' is set (passed from PHP)
if (!valgParam) {
    <?php if (isset($valg)): ?>
        valgParam = "<?php echo addslashes($valg); ?>";  // Set from PHP
    <?php else: ?>
        valgParam = "ordrer";  // Default to 'ordrer' if neither is set
    <?php endif; ?>
}

// Add 'valg' parameter to all forms in the datagrid
function addValgToForms() {
    if (valgParam) {
        const forms = document.querySelectorAll('.datatable-wrapper form');
        
        forms.forEach(form => {
            let valgInput = form.querySelector('input[name="valg"]');
            if (!valgInput) {
                valgInput = document.createElement('input');
                valgInput.type = 'hidden';
                valgInput.name = 'valg';
                form.appendChild(valgInput);
            }
            valgInput.value = valgParam;
        });
    }
}

// Run on DOM load and after AJAX updates
document.addEventListener('DOMContentLoaded', function() {
    addValgToForms();
});

// Re-run after AJAX updates
if (typeof DynamicSearch !== 'undefined') {
    // Override or extend the updateGridContent method
    const originalUpdate = DynamicSearch.prototype.updateGridContent;
    DynamicSearch.prototype.updateGridContent = function(html) {
        originalUpdate.call(this, html);
        addValgToForms();
    };
}
</script>



<script>
//  Track last focused input
let lastFocusedSearchInputName = null;

//  Save focus whenever user clicks a search field
function registerSearchInputFocusTracking() {
    const searchInputs = document.querySelectorAll(".datatable-wrapper input[type='text']");
    searchInputs.forEach(input => {
        input.addEventListener("focus", () => {
            lastFocusedSearchInputName = input.name;
        });
    });
}


function focusFirstFilledSearch_onInitialLoad() {

    // If user already focused something, skip
    if (lastFocusedSearchInputName !== null) return;

    setTimeout(() => {
        const searchFields = document.querySelectorAll(".datatable-wrapper input[type='text']");
        if (!searchFields.length) return;

        // Focus first non-empty
        for (let input of searchFields) {
            if (input.value.trim() !== '') {
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
                return;
            }
        }

    }, 50);
}

//  Restore focus after AJAX reload
function restoreFocusAfterAjax() {
    if (!lastFocusedSearchInputName) return;

    const input = document.querySelector(
        `input[name="${CSS.escape(lastFocusedSearchInputName)}"]`
    );

    if (input) {
        setTimeout(() => {
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        }, 50);
    }
}

// INITIAL LOAD
document.addEventListener("DOMContentLoaded", function() {
    registerSearchInputFocusTracking();
    focusFirstFilledSearch_onInitialLoad();
});

// AJAX LOAD
if (typeof DynamicSearch !== "undefined") {
    const oldUpdate = DynamicSearch.prototype.updateGridContent;
    DynamicSearch.prototype.updateGridContent = function(html) {
        oldUpdate.call(this, html);

        // Reinitialize date pickers
        initializeDatePickers();

        // Re-register focus tracking
        registerSearchInputFocusTracking();

        // Restore cursor to same field
        restoreFocusAfterAjax();
    };
}
</script>