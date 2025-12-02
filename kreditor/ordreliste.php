<?php
// --- kreditor/ordreliste.php -----patch 4.1.1 ----2025-11-24----------
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
// 2104.09.16	Tilføjet oioublimport i bunden
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
// Loop through the GET array
// Function to find and set $valg for any key starting with 'kredorliste_'
// foreach ($_GET as $category => $data) {
//     // Check if $data is an array (since you expect nested arrays)
//     if (is_array($data)) {
//         foreach ($data as $key => $value) {
//             // Check if the key starts with "kredorliste_"
//             if (strpos($key, 'kredorliste_') === 0) {
//                 // Remove "kredorliste_" from the key
//                 $valg = substr($key, strlen('kredorliste_'));
//                 break 2; // Break out of both loops since we found the first match
//             }
//         }
//     }
// }
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


# >> Date picker scripts <<
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/jquery-3.6.4.min.js\"></script>";
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
    $status = "status = 1 or status = 2";
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
              WHERE (art = 'KO' or art = 'KK') AND $status AND {{WHERE}}
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
              WHERE (art = 'KO' or art = 'KK') AND $status AND {{WHERE}}
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
              WHERE (art = 'KO' or art = 'KK') AND $status AND {{WHERE}}
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

// Create grid configuration
$grid_data = [
    'columns' => $columns,
    'query' => $query,
    'filters' => [], 
    'metaColumn' => $metaColumn,
    'metaColumnHeaders' => $metaColumnHeaders,
    'rowStyle' => $rowStyle
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
    //datepicker initialization code
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date pickers for date fields
   
     const dateInputs = document.querySelectorAll(
        "input[name^='search[kredorliste_'][name$='[ordredate]'], " +
        "input[name^='search[kredorliste_'][name$='[levdate]'], " +
        "input[name^='search[kredorliste_'][name$='[fakturadate]']"
    );
    
    console.log('Found date inputs:', dateInputs.length); 

    dateInputs.forEach(function(input) {
        // Get existing value if any
        var existingValue = input.value.trim();
        var startDate = moment(); // Default to today
        
        // Parse existing value if it exists
        if (existingValue !== '') {
            var parsed = moment(existingValue, 'DD-MM-YYYY', true);
            if (parsed.isValid()) {
                startDate = parsed;
            }
        }
        
        // Initialize daterangepicker
        $(input).daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            autoUpdateInput: false,
            autoApply: false, // CHANGED: Show Apply/Cancel buttons
            startDate: startDate,
            minYear: 1900,
            maxYear: parseInt(moment().format('YYYY'), 10) + 10,
            locale: {
                format: 'DD-MM-YYYY',
                cancelLabel: 'Ryd',
                applyLabel: 'Søg',
                daysOfWeek: ['Sø', 'Ma', 'Ti', 'On', 'To', 'Fr', 'Lø'],
                monthNames: ['Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni', 
                             'Juli', 'August', 'September', 'Oktober', 'November', 'December'],
                firstDay: 1
            }
        });
        
        // Set initial value if exists
        if (existingValue !== '') {
            $(input).val(existingValue);
        }
        
        // When user clicks "Søg" (Apply) button
        $(input).on('apply.daterangepicker', function(ev, picker) {
            var selectedDate = picker.startDate.format('DD-MM-YYYY');
            console.log('Applied date:', selectedDate);
            $(this).val(selectedDate);
            
            // Submit the form automatically
            var form = $(this).closest('form');
            if (form.length > 0) {
                console.log('Submitting form...');
                form.submit();
            }
        });
        
        // When user clicks "Ryd" (Cancel) button
        $(input).on('cancel.daterangepicker', function(ev, picker) {
            console.log('Clearing date field');
            $(this).val('');
            
            // Submit form to clear the filter
            var form = $(this).closest('form');
            if (form.length > 0) {
                console.log('Submitting form to clear filter...');
                form.submit();
            }
        });
    });
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
document.addEventListener('DOMContentLoaded', function() {
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
});
</script>

