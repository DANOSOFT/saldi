<?php
// --- kreditor/ordreliste.php -----patch 4.1.1 ----2025-12-05---------
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
// Copyright (c) 2003-2025 Saldi.dk ApS
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
// Configure datagrid based on valg
if ($valg == 'forslag') {
    $columns = [
        [
            'field' => 'ordrenr',
            'headerName' => findtekst('500|Ordrenr.', $sprog_id),
            'type' => 'number',
            'width' => 1,
            'align' => 'right',
            'sortable' => true,
            'searchable' => true,
            'defaultSort' => true,
            'defaultSortDirection' => 'desc',
            'decimalPrecision' => 0,
            'valueGetter' => function($value, $row, $column) {
                return $value; // Return raw number without formatting
            }
        ],
        
        [
            'field' => 'ordredate',
            'headerName' => findtekst('889|Tilbudsdato', $sprog_id),
            'type' => 'date',
            'width' => 1,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true,
            'valueGetter' => function($value, $row, $column) {
                return dkdato($value);
            }
        ],
        [
            'field' => 'kontonr',
            'headerName' => findtekst('804|Kontonr.', $sprog_id),
            'type' => 'number',
            'width' => 1,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true,
            'decimalPrecision' => 0,
            'valueGetter' => function($value, $row, $column) {
                return $value; // Return raw number without formatting
            }
        ],
        [
            'field' => 'firmanavn',
            'headerName' => findtekst('360|Firmanavn', $sprog_id),
            'type' => 'text',
            'width' => 2,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ],
        [
            'field' => 'lev_navn',
            'headerName' => findtekst('814|Leveres til', $sprog_id),
            'type' => 'text',
            'width' => 2,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ],
        [
            'field' => 'ref',
            'headerName' => findtekst('884|Sælger', $sprog_id),
            'type' => 'text',
            'width' => 1,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ],
        [
            'field' => 'sum',
            'headerName' => findtekst('826|Forslagssum', $sprog_id),
            'type' => 'number',
            'width' => 1,
            'align' => 'right',
            'sortable' => true,
            'searchable' => true,
            'valueGetter' => function($value, $row, $column) {
                $sum = $value;
                if ($row['valutakurs'] && $row['valutakurs'] != 100) {
                    $sum = $sum * $row['valutakurs'] / 100;
                }
                return dkdecimal($sum);
            }
        ]
    ];

    $query = "SELECT ordrer.id, ordrer.ordrenr, ordrer.ordredate, ordrer.kontonr, 
              ordrer.firmanavn, ordrer.lev_navn, ordrer.ref, ordrer.sum, 
              ordrer.valutakurs, ordrer.art, ordrer.email,
              CASE WHEN ordrer.art = 'KK' THEN CONCAT('(KN) ', ordrer.ordrenr::text) 
                   ELSE ordrer.ordrenr::text END as display_ordrenr
              FROM ordrer 
              WHERE (art = 'KO' OR art = 'KK') AND $status AND {{WHERE}}
              ORDER BY {{SORT}}";

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
    $columns = [
        [
            'field' => 'ordrenr',
            'headerName' => findtekst('500|Ordrenr.', $sprog_id),
            'type' => 'number',
            'width' => 1,
            'align' => 'right',
            'sortable' => true,
            'searchable' => true,
            'defaultSort' => true,
            'defaultSortDirection' => 'desc',
            'decimalPrecision' => 0,
            'valueGetter' => function($value, $row, $column) {
                return $value; // Return raw number without formatting
            }
        ],
        [
            'field' => 'ordredate',
            'headerName' => findtekst('881|Ordredato', $sprog_id),
            'type' => 'date',
            'width' => 1,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true,
            'valueGetter' => function($value, $row, $column) {
                return dkdato($value);
            }
        ],
        [
            'field' => 'levdate',
            'headerName' => findtekst('941|Modt.dato', $sprog_id),
            'type' => 'date',
            'width' => 1,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true,
            'valueGetter' => function($value, $row, $column) {
                return dkdato($value);
            }
        ],
        [
            'field' => 'kontonr',
            'headerName' => findtekst('804|Kontonr.', $sprog_id),
            'type' => 'number',
            'width' => 1,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true,
            'decimalPrecision' => 0,
            'valueGetter' => function($value, $row, $column) {
                return $value; // Return raw number without formatting
            }
        ],
        [
            'field' => 'firmanavn',
            'headerName' => findtekst('360|Firmanavn', $sprog_id),
            'type' => 'text',
            'width' => 2,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ],
        [
            'field' => 'lev_navn',
            'headerName' => findtekst('814|Leveres til', $sprog_id),
            'type' => 'text',
            'width' => 2,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ],
        [
            'field' => 'ref',
            'headerName' => findtekst('884|Sælger', $sprog_id),
            'type' => 'text',
            'width' => 1,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ],
        [
            'field' => 'sum',
            'headerName' => findtekst('887|Ordresum', $sprog_id),
            'type' => 'number',
            'width' => 1,
            'align' => 'right',
            'sortable' => true,
            'searchable' => true,
            'valueGetter' => function($value, $row, $column) {
                $sum = $value;
                if ($row['valutakurs'] && $row['valutakurs'] != 100) {
                    $sum = $sum * $row['valutakurs'] / 100;
                }
                return dkdecimal($sum);
            }
        ]
    ];

    $query = "SELECT ordrer.id, ordrer.ordrenr, ordrer.ordredate, ordrer.levdate,
              ordrer.kontonr, ordrer.firmanavn, ordrer.lev_navn, ordrer.ref, 
              ordrer.sum, ordrer.valutakurs, ordrer.art, ordrer.email
              FROM ordrer 
              WHERE (art = 'KO' OR art = 'KK') AND $status AND {{WHERE}}
              ORDER BY {{SORT}}";

    $metaColumn = function($row) {
        return "<td>
            <div style='display:flex;gap:5px;'>
                <a href='formularprint.php?id={$row['id']}&formular=13&udskriv_til=PDF' target='_blank' title='Klik for at printe ordre' onclick='event.stopPropagation();'>
                    <svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'>
                        <path d='M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z'/>
                    </svg>
                </a>" 
                .
               
            "</div>
        </td>";
    };

} else { // faktura
    $columns = [
        [
            'field' => 'ordrenr',
            'headerName' => findtekst('500|Ordrenr.', $sprog_id),
            'type' => 'number',
            'width' => 1,
            'align' => 'right',
            'sortable' => true,
            'searchable' => true,
            'defaultSort' => true,
            'defaultSortDirection' => 'desc',
            'decimalPrecision' => 0,
            'valueGetter' => function($value, $row, $column) {
                return $value; // Return raw number without formatting
            }
        ],
        [
            'field' => 'ordredate',
            'headerName' => findtekst('881|Ordredato', $sprog_id),
            'type' => 'date',
            'width' => 1,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true,
            'valueGetter' => function($value, $row, $column) {
                return dkdato($value);
            }
        ],
        [
            'field' => 'modtagelse',
            'headerName' => findtekst('940|Modt.nr.', $sprog_id),
            'type' => 'number',
            'width' => 1,
            'align' => 'right',
            'sortable' => true,
            'searchable' => true,
            'decimalPrecision' => 0,
            'valueGetter' => function($value, $row, $column) {
                return $value; // Return raw number without formatting
            }
        ],
        [
            'field' => 'fakturanr',
            'headerName' => findtekst('882|Fakt.nr.', $sprog_id),
            'type' => 'text',
            'width' => 1,
            'align' => 'right',
            'sortable' => true,
            'searchable' => true
        ],
        [
            'field' => 'fakturadate',
            'headerName' => findtekst('883|Fakt.dato', $sprog_id),
            'type' => 'date',
            'width' => 1,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true,
            'valueGetter' => function($value, $row, $column) {
                return dkdato($value);
            }
        ],
        [
            'field' => 'kontonr',
            'headerName' => findtekst('804|Kontonr.', $sprog_id),
            'type' => 'number',
            'width' => 1,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true,
            'decimalPrecision' => 0,
            'valueGetter' => function($value, $row, $column) {
                return $value; // Return raw number without formatting
            }
        ],
        [
            'field' => 'firmanavn',
            'headerName' => findtekst('360|Firmanavn', $sprog_id),
            'type' => 'text',
            'width' => 2,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ],
        [
            'field' => 'lev_navn',
            'headerName' => findtekst('814|Leveres til', $sprog_id),
            'type' => 'text',
            'width' => 2,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ],
        [
            'field' => 'ref',
            'headerName' => findtekst('884|Sælger', $sprog_id),
            'type' => 'text',
            'width' => 1,
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ],
        [
            'field' => 'sum',
            'headerName' => findtekst('885|Fakturasum', $sprog_id),
            'type' => 'number',
            'width' => 1,
            'align' => 'right',
            'sortable' => true,
            'searchable' => true,
            'valueGetter' => function($value, $row, $column) {
                $sum = $value;
                if ($row['valutakurs'] && $row['valutakurs'] != 100) {
                    $sum = $sum * $row['valutakurs'] / 100;
                }
                return dkdecimal($sum);
            }
        ]
    ];

    $query = "SELECT ordrer.id, ordrer.ordrenr, ordrer.ordredate, ordrer.modtagelse,
              ordrer.fakturanr, ordrer.fakturadate, ordrer.kontonr, 
              ordrer.firmanavn, ordrer.lev_navn, ordrer.ref, ordrer.sum, 
              ordrer.valutakurs, ordrer.art, ordrer.email
              FROM ordrer 
              WHERE (art = 'KO' OR art = 'KK') AND $status AND {{WHERE}}
              ORDER BY {{SORT}}";

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

// Calculate total sum for footer
$totalSum = 0;
foreach ($rows as $row) {
    $sum = $row['sum'];
    if ($row['valutakurs'] && $row['valutakurs'] != 100) {
        $sum = $sum * $row['valutakurs'] / 100;
    }
    $totalSum += $sum;
}

// Add total row after the grid
print "<table width='100%' style='margin-top: -2px;'>";
print "<tr style='background-color: #f4f4f4; border-top: 2px solid #ddd;'>";
print "<td colspan='100' align='right' style='padding: 10px;'>";
print "<b>" . findtekst('942|Samlet omsætning (excl. moms.)', $sprog_id) . ":</b> ";
print "<b>" . dkdecimal($totalSum) . "</b>";
print "</td>";
print "</tr>";
print "</table>";

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
                window.location.href = 'ordre.php?id=' + orderId + '&returside=ordreliste.php';
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