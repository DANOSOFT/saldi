<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kontospec.php --- rev 4.1.1 --- 2025.12.03 ---
// LICENSE
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
//
// Copyright (c) 2003-2025 saldi.dk ApS
// ----------------------------------------------------------------------
// 20150218 Tilføjet funktion lagerbev.
// 20210211 PHR some cleanup
// 20210708 LOE - Translated some of these texts from Danish to English and Norsk
// 20250113 PHR fiscal_year
// 20251203 LOE Updated the file to use grid framework


$fakturanr = array();
$ordrenr   = array();
$transdate = array();
$varekob = $varelager_i = $varelager_u = array();

$linjebg = NULL;

@session_start();
$s_id = session_id();
$title = "Kontospecifikation";
$css = "../css/standard.css";

global $menu;
    
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/row-hover-style.js.php");
include("../includes/grid.php");

print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/jquery-3.6.4.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/moment.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/daterangepicker.min.js\" defer></script>";
print '<link rel="stylesheet" type="text/css" href="../css/daterangepicker.css" />';

$kontonr = if_isset($_GET, NULL, 'kontonr');
$month = if_isset($_GET,NULL,'month');
$bilag = if_isset($_GET,NULL,'bilag');

$query = db_select("select * from grupper where art='RA' and kodenr='$regnaar'", __FILE__ . " linje " . __LINE__);
if ($row = db_fetch_array($query)) {
    $startaar = $row['box2'];
    $month = trim($month);
    if (!$month) {
        $start = $startaar . '-' . $row['box1'] . '-01';
        $slutdato = 31;
        $month = $row['box3'] * 1;
        $year = $row['box4'] * 1;
    } else {
        $month = $month - 1 + $row['box1'];
        $year = $row['box2'];
        while ($month > 12) {
            $year++;
            $month = $month - 12;
        }
        $year = $year;
        if ($month < 10) $month = '0' . $month * 1;
        $start = $year . '-' . $month . '-01';
    }
    $slutdato = 31;
    while (!checkdate($month, $slutdato, $year)) {
        $slutdato = $slutdato - 1;
    }
    if ($month < 10) $month = '0' . $month * 1;
    $slut = $year . '-' . $month . '-' . $slutdato;
    $start = trim($start);
    $slut = trim($slut);
}

($startaar >= '2015') ? $aut_lager = 'on' : $aut_lager = NULL;
if ($aut_lager) {
    $x = 0;
    $varekob = array();
    $qtxt = "select box1,box2,box3 from grupper where art = 'VG' and box8 = 'on' and fiscal_year = '$regnaar'";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        if ($r['box1'] && $r['box2'] && !in_array($r['box3'], $varekob)) {
            $varelager_i[$x] = $r['box1'];
            $varelager_u[$x] = $r['box2'];
            $varekob[$x] = $r['box3'];
            $x++;
        }
    }
}

$txt2131 = findtekst('2131|konto', $sprog_id);
$txt2132 = findtekst('2132|bilag', $sprog_id);

// Top navigation
if ($menu == 'T') {
    include_once '../includes/top_header.php';
    include_once '../includes/top_menu.php';
    print "<div id=\"header\">"; 
    print "<div class=\"headerbtnLft headLink\"><a href=regnskab.php accesskey=L title='Klik for at komme tilbage til regnskab'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst(30, $sprog_id) . "</a></div>";     
    print "<div class=\"headerTxt\">" . findtekst(1196, $sprog_id) . " ";
    if ($kontonr) print "$txt2131: $kontonr";
    if ($bilag) print "$txt2132: $bilag";
    print "</div>";     
    print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
    print "</div>";
    print "<div class='content-noside'>";
} elseif ($menu == 'S') {
    ############################
     $icon_back = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="24" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';

    ##########################
	$center = "";
	$width = "width=10%";
	print "<table width='100%' border='0' cellspacing='0' cellpadding='0'><tbody>\n"; #tabel1 start
	print "<tr><td align='center' valign='top' height='1%'>\n";
	print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody>\n"; #tabel2a start

    print "<td width='10%'><a href=regnskab.php accesskey=L title='Klik for at komme tilbage til regnskab'>
           <button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">" .$icon_back  . findtekst(30, $sprog_id) . "</button></a></td>";

    print "<td width='80%' align='center' style='$topStyle'>" . findtekst(1196, $sprog_id) . " ";
    if ($kontonr) print "$txt2131: $kontonr";
    if ($bilag) print "$txt2132: $bilag";

    print " </td><td width='10%' align='center' style='$topStyle'><br></td>";
    print "</tbody></table>";
    print "</td></tr>";
    print "<tr><td valign=\"top\">";
	####
    print "</td></tr>\n";

    print "</tbody></table>\n";  # tabel1 slut
    #####
	 ?>
    <style>
    .headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none;
		gap: 5px;
	}
    </style>
    <?php

} else {
    print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td height = \"25\" align=\"center\" valign=\"top\">";
    print "<table width=100% align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
    print "<td width=\"10%\" $top_bund><a href=regnskab.php accesskey=L title='Klik for at komme tilbage til regnskab'>" . findtekst(30, $sprog_id) . "</a></td>";
    print "<td width=\"80%\" $top_bund>" . findtekst(1196, $sprog_id) . " ";
    if ($kontonr) print "$txt2131: $kontonr";
    if ($bilag) print "$txt2132: $bilag";
    print " </td><td width=\"10%\" $top_bund><br></td>";
    print "</tbody></table>";
    print "</td></tr>";
    print "<tr><td valign=\"top\">";
}


// Process transactions and inventory movements
$allTransactions = array();

if ($kontonr) {
    // Get inventory transactions
    list($transdate, $faktura, $ordrenr, $bilag_arr, $beskrivelse, $debet, $kredit) = lagerbev($kontonr, $varekob, $varelager_i, $varelager_u, $start, $slut);
    $valg = "and kontonr = '$kontonr'";
    
    // Add inventory transactions to array
    if (is_array($transdate) && count($transdate) > 0) {
        for ($i = 0; $i < count($transdate); $i++) {
            $allTransactions[] = array(
                'transdate' => isset($transdate[$i]) ? $transdate[$i] : '',
                'bilag' => isset($bilag_arr[$i]) ? $bilag_arr[$i] : '',
                'beskrivelse' => isset($beskrivelse[$i]) ? $beskrivelse[$i] : '',
                'kontonr' => $kontonr,
                'debet' => isset($debet[$i]) ? $debet[$i] : 0,
                'kredit' => isset($kredit[$i]) ? $kredit[$i] : 0,
                'faktura' => isset($faktura[$i]) ? $faktura[$i] : '',
                'ordrenr' => isset($ordrenr[$i]) ? $ordrenr[$i] : 0,
                'kladde_id' => '',
                'afd' => '',
                'projekt' => ''
            );
        }
    }
} elseif ($bilag) {
    $valg = "and bilag = '$bilag'";
}

// Fetch regular transactions
$qtxt = "select * from transaktioner where transdate >= '$start' and transdate <= '$slut' $valg order by transdate";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    if ($r['debet'] || $r['kredit']) {
        $allTransactions[] = array(
            'transdate' => $r['transdate'],
            'bilag' => $r['bilag'],
            'beskrivelse' => $r['beskrivelse'],
            'kontonr' => $kontonr ? $kontonr : $r['kontonr'],
            'debet' => $r['debet'],
            'kredit' => $r['kredit'],
            'faktura' => $r['faktura'],
            'ordrenr' => 0,
            'kladde_id' => $r['kladde_id'],
            'afd' => $r['afd'],
            'projekt' => $r['projekt']
        );
    }
}

// Sort by date if we have transactions
if (!empty($allTransactions)) {
    usort($allTransactions, function($a, $b) {
        return strcmp($a['transdate'], $b['transdate']);
    });
}

// Create temporary table for grid
$tempTableName = "temp_kontospec_" . abs($bruger_id) . "_" . time();

// Clean up any existing temp table first
db_modify("DROP TABLE IF EXISTS $tempTableName", __FILE__ . " linje " . __LINE__);


$createTempTable = "CREATE TEMPORARY TABLE $tempTableName (
    id SERIAL PRIMARY KEY,
    transdate DATE,
    bilag VARCHAR(100),
    beskrivelse TEXT,
    kontonr VARCHAR(20),
    debet DECIMAL(15,2),
    kredit DECIMAL(15,2),
    faktura VARCHAR(100),
    ordrenr VARCHAR(100),
    kladde_id VARCHAR(100),
    afd VARCHAR(100),
    projekt VARCHAR(100)
)";

db_modify($createTempTable, __FILE__ . " linje " . __LINE__);

// Insert data into temp table
if (!empty($allTransactions)) {
    foreach ($allTransactions as $trans) {
        // Truncate long values to fit in columns
        $bilag_value = substr($trans['bilag'], 0, 100);
        $beskrivelse_value = substr($trans['beskrivelse'], 0, 500);
        $faktura_value = substr($trans['faktura'], 0, 100);
        $ordrenr_value = substr((string)$trans['ordrenr'], 0, 100);
        $kladde_id_value = substr($trans['kladde_id'], 0, 100);
        $afd_value = substr($trans['afd'], 0, 100);
        $projekt_value = substr($trans['projekt'], 0, 100);
        
        $insertSQL = "INSERT INTO $tempTableName (transdate, bilag, beskrivelse, kontonr, debet, kredit, faktura, ordrenr, kladde_id, afd, projekt) VALUES (
            '" . db_escape_string($trans['transdate']) . "',
            '" . db_escape_string($bilag_value) . "',
            '" . db_escape_string($beskrivelse_value) . "',
            '" . db_escape_string($trans['kontonr']) . "',
            " . (floatval($trans['debet']) ? floatval($trans['debet']) : 0) . ",
            " . (floatval($trans['kredit']) ? floatval($trans['kredit']) : 0) . ",
            '" . db_escape_string($faktura_value) . "',
            '" . db_escape_string($ordrenr_value) . "',
            '" . db_escape_string($kladde_id_value) . "',
            '" . db_escape_string($afd_value) . "',
            '" . db_escape_string($projekt_value) . "'
        )";
        db_modify($insertSQL, __FILE__ . " linje " . __LINE__);
    }
} else {
    // Insert a dummy row to avoid empty table errors
    $insertSQL = "INSERT INTO $tempTableName (transdate, bilag, beskrivelse, kontonr, debet, kredit) VALUES ('2000-01-01', '', 'Ingen transaktioner fundet', '', 0, 0)";
    db_modify($insertSQL, __FILE__ . " linje " . __LINE__);
}

// Define grid columns
$columns = [
    [
        'field' => 'bilag',
        'headerName' => findtekst(671, $sprog_id), // "Bilag"
        'type' => 'text',
        'width' => '1',
        'sortable' => true,
        'searchable' => true,
        'align' => 'left',
        'render' => function($value, $row, $column) {
            if ($value) {
                 return "<td><a href='kontospec.php?bilag=" . urlencode($value) . "' style='text-decoration: underline;'>$value</a></td>";
            }
            return "<td></td>";
        }
    ],
    [
        'field' => 'transdate',
        'headerName' => findtekst(635, $sprog_id), // "Dato"
        'type' => 'date',
        'width' => '1',
        'sortable' => true,
        'searchable' => true,
        'align' => 'left',
        'defaultSort' => true,
        'defaultSortDirection' => 'asc',
        'valueGetter' => function($value, $row, $column) {
            return dkdato($value);
        },
        'render' => function($value, $row, $column) {
            return "<td>$value</td>";
        },
        'generateSearch' => function ($column, $term) {
            $field = $column['sqlOverride'] ? $column['sqlOverride'] : $column['field'];
            $term = trim($term);
            
            if (empty($term)) {
                return "1=1";
            }
            
            // Only handle properly formatted dates DD-MM-YYYY
            if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $term, $matches)) {
                $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $year = $matches[3];
                $sqlDate = "$year-$month-$day";
                $sqlDate = db_escape_string($sqlDate);
                return "$field = '$sqlDate'";
            }
            
            // For anything else, return no results (or return 1=1 to show all)
            return "1=0"; 
        }
    ],
        [
        'field' => 'beskrivelse',
        'headerName' => findtekst(1068, $sprog_id), // "Beskrivelse"
        'type' => 'text',
        'width' => '3',
        'sortable' => true,
        'searchable' => true,
        'align' => 'left',
        'render' => function($value, $row, $column) {
            // Truncate very long descriptions for display
            if (strlen($value) > 100) {
                $short = substr($value, 0, 100) . '...';
                return "<td title='$value'>$short</td>";
            }
            return "<td>$value</td>";
        }
    ],
    [
        'field' => 'kontonr',
        'headerName' => findtekst(804, $sprog_id), // "Kontonr"
        'type' => 'text',
        'width' => '1',
        'sortable' => true,
        'searchable' => true,
        'align' => 'right',
         'render' => function($value, $row, $column) use ($bilag) {
        // If we're viewing by invoice (bilag), make account numbers clickable
        if ($value && $bilag) {
            return "<td align='right'><a href='kontospec.php?kontonr=$value' style='text-decoration: underline;'>$value</a></td>";
        } else {
            return "<td align='right'>$value</td>";
        }
    }
    ],
    [
        'field' => 'debet',
        'headerName' => findtekst(1000, $sprog_id), // "Debet"
        'type' => 'number',
        'width' => '1.2',
        'sortable' => true,
        'searchable' => true,
        'align' => 'right',
        'decimalPrecision' => 2,
        'valueGetter' => function($value, $row, $column) {
            if (floatval($value) == 0) return '';
            return dkdecimal($value, 2);
        },
        'render' => function($value, $row, $column) {
            if ($value === '' || $value === null) {
                return "<td align='right'></td>";
            }
            return "<td align='right'>$value</td>";
        }
    ],
    [
        'field' => 'kredit',
        'headerName' => findtekst(1001, $sprog_id), // "Kredit"
        'type' => 'number',
        'width' => '1.2',
        'sortable' => true,
        'searchable' => true,
        'align' => 'right',
        'decimalPrecision' => 2,
        'valueGetter' => function($value, $row, $column) {
            if (floatval($value) == 0) return '';
            return dkdecimal($value, 2);
        },
        'render' => function($value, $row, $column) {
            if ($value === '' || $value === null) {
                return "<td align='right'></td>";
            }
            return "<td align='right'>$value</td>";
        }
    ],
    [
        'field' => 'faktura',
        'headerName' => findtekst(828, $sprog_id), // "Faktura"
        'type' => 'text',
        'width' => '1',
        'sortable' => true,
        'searchable' => true,
        'align' => 'right',
        'render' => function($value, $row, $column) {
            $title = isset($row['ordrenr']) && $row['ordrenr'] ? "title='Ordrenr: {$row['ordrenr']}'" : '';
            return "<td align='right' $title>$value</td>";
        }
    ],
    [
        'field' => 'kladde_id',
        'headerName' => findtekst(1197, $sprog_id), // "Kladde"
        'type' => 'text',
        'width' => '1',
        'sortable' => true,
        'searchable' => true,
        'align' => 'right',
        'render' => function($value, $row, $column) {
			if ($value) {
				return "<td align='right'><a href='kassekladde.php?kladde_id=" . urlencode($value) . "&returside=kontospec.php' style='text-decoration: underline;'>$value</a></td>";
			}
			return "<td align='right'></td>";
       }
    ],
    [
        'field' => 'afd',
        'headerName' => findtekst(1198, $sprog_id), // "Afd"
        'type' => 'text',
        'width' => '0.8',
        'sortable' => true,
        'searchable' => true,
        'align' => 'right',
        'render' => function($value, $row, $column) {
            return "<td align='right'>$value</td>";
        }
    ],
    [
        'field' => 'projekt',
        'headerName' => findtekst(1199, $sprog_id), // "Projekt"
        'type' => 'text',
        'width' => '1',
        'sortable' => true,
        'searchable' => true,
        'align' => 'right',
        'render' => function($value, $row, $column) {
            return "<td align='right'>$value</td>";
        }
    ]
];

// Build SQL query
$baseQuery = "SELECT * FROM $tempTableName WHERE {{WHERE}} ORDER BY {{SORT}}";

// Grid configuration
$grid_data = [
    'query' => $baseQuery,
    'columns' => $columns,
    'filters' => [
        [
            'filterName' => 'Transaktionstype',
            'joinOperator' => 'OR',
            'options' => [
                ['name' => 'Debet', 'sqlOn' => "debet > 0", 'sqlOff' => '', 'checked' => 'checked'],
                ['name' => 'Kredit', 'sqlOn' => "kredit > 0", 'sqlOff' => '', 'checked' => 'checked'],
                ['name' => 'Lagertransaktioner', 'sqlOn' => "beskrivelse LIKE '%lagertransaktion%'", 'sqlOff' => '', 'checked' => 'checked']
            ]
        ]
    ],
    'rowStyle' => function($row) use ($bgcolor, $bgcolor5) {
        static $current_bg = null;
        
        // Alternate row colors
        if ($current_bg !== $bgcolor) {
            $current_bg = $bgcolor;
            return "background-color: $bgcolor;";
        } else {
            $current_bg = $bgcolor5;
            return "background-color: $bgcolor5;";
        }
    }
];

// Render the grid
print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
print "<div class='outer-datatable-wrapper'>";

// print "<div style='margin: 10px;'>";
$rows = create_datagrid('kontospec', $grid_data);
// print "</div>";

 //Date picker implementation: 
 ####
 // Date picker implementation:
print "<script>
document.addEventListener('DOMContentLoaded', function() {
    // set the input name
    const transdateInput = document.querySelector(\"input[name='search[kontospec][transdate]']\");
    
    if (transdateInput) {
        var existingValue = transdateInput.value.trim();
        var startDate = moment(); // Default to today
        
        // Parse existing value if it exists
        if (existingValue !== '') {
            var parsed = moment(existingValue, 'DD-MM-YYYY', true);
            if (parsed.isValid()) {
                startDate = parsed;
            }
        }
        
        $(transdateInput).daterangepicker({
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
                monthNames: ['Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni', 
                             'Juli', 'August', 'September', 'Oktober', 'November', 'December'],
                firstDay: 1
            }
        });
        
        // Set initial value if exists
        if (existingValue !== '') {
            $(transdateInput).val(existingValue);
        }
        
        // Update field when date is selected
        $(transdateInput).on('show.daterangepicker', function(ev, picker) {
            picker.container.find('.calendar-table').off('click.updateField').on('click.updateField', 'td.available', function() {
                setTimeout(function() {
                    var selectedDate = picker.startDate.format('DD-MM-YYYY');
                    $(transdateInput).val(selectedDate);
                }, 10);
            });
        });
        
        $(transdateInput).on('apply.daterangepicker', function(ev, picker) {
            var selectedDate = picker.startDate.format('DD-MM-YYYY');
            $(this).val(selectedDate);
            
            // Submit the form
            var form = $(this).closest('form');
            if (form.length > 0) {
                form.submit();
            }
        });
        
        // When user clicks \"Ryd\" button
        $(transdateInput).on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            
            // Submit form to clear the filter
            var form = $(this).closest('form');
            if (form.length > 0) {
                form.submit();
            }
        });
    }
});
</script>";

 ####



// Clean up temp table
db_modify("DROP TABLE IF EXISTS $tempTableName", __FILE__ . " linje " . __LINE__);

function lagerbev($kontonr, $varekob, $varelager_i, $varelager_u, $regnstart, $regnslut) {
    global $regnaar;

    $beskrivelse = $bilag = $debet = $fakturanr = $kredit = $ordrenr = $transdate = array();
    
    $r = db_fetch_array(db_select("select kontotype from kontoplan where kontonr='$kontonr' order by regnskabsaar desc limit 1", __FILE__ . " linje " . __LINE__));
    $kontotype = $r ? $r['kontotype'] : '';
    
    if (in_array($kontonr, $varekob) || in_array($kontonr, $varelager_i) || in_array($kontonr, $varelager_u)) {
        $z = 0;
        $lager = array();
        $gruppe = array();
        $qtxt = "select kodenr,box1,box2 from grupper where art = 'VG' and box8 = 'on' and ";
        $qtxt .= "(box1 = '$kontonr' or box2 = '$kontonr' or box3 = '$kontonr') and fiscal_year = $regnaar";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        while ($r = db_fetch_array($q)) {
            if ($r['box1']) {
                $gruppe[$z] = $r['kodenr'];
                $z++;
            }
        }
        
        $y = 0;
        $vare_id = array();
        for ($z = 0; $z < count($gruppe); $z++) {
            $q = db_select("select id,kostpris from varer where gruppe = '$gruppe[$z]' order by id", __FILE__ . " linje " . __LINE__);
            while ($r = db_fetch_array($q)) {
                $vare_id[$y] = $r['id'];
                $kostpris[$y] = $r['kostpris'];
                $y++;
            }
        }
        
        $z = 0;
        $kobsdate = array();
        $kobsdebet = array();
        $kobskredit = array();
        $k_fakturanr = array();
        $k_ordrenr = array();
        
        $q = db_select("select vare_id,ordre_id,antal,kobsdate,fakturanr,ordrenr from batch_kob,ordrer where kobsdate >= '$regnstart' and kobsdate <= '$regnslut' and ordrer.id=batch_kob.ordre_id order by kobsdate,vare_id", __FILE__ . " linje " . __LINE__);
        while ($r = db_fetch_array($q)) {
            if ($z && isset($kobsdate[$z]) && $r['kobsdate'] == $kobsdate[$z]) {
                for ($y = 0; $y < count($vare_id); $y++) {
                    if ($r['vare_id'] == $vare_id[$y]) {
                        if ($kontotype == 'D') {
                            if ($r['antal'] > 0) $kobskredit[$z] += $r['antal'] * $kostpris[$y];
                            else $kobsdebet[$z] -= $r['antal'] * $kostpris[$y];
                        } elseif (in_array($kontonr, $varelager_i)) {
                            if ($r['antal'] > 0) $kobsdebet[$z] += $r['antal'] * $kostpris[$y];
                            else $kobskredit[$z] -= $r['antal'] * $kostpris[$y];
                        }
                    }
                }
            } else {
                for ($y = 0; $y < count($vare_id); $y++) {
                    if ($r['vare_id'] == $vare_id[$y]) {
                        if ($kontotype == 'D') {
                            $k_fakturanr[$z] = $r['fakturanr'];
                            $k_ordrenr[$z] = $r['ordrenr'];
                            $kobsdate[$z] = $r['kobsdate'];
                            if ($r['antal'] > 0) {
                                $kobskredit[$z] = $r['antal'] * $kostpris[$y];
                                $kobsdebet[$z] = 0;
                            } else {
                                $kobsdebet[$z] = $r['antal'] * $kostpris[$y] * -1;
                                $kobskredit[$z] = 0;
                            }
                            $z++;
                        } elseif (in_array($kontonr, $varelager_i)) {
                            $k_fakturanr[$z] = $r['fakturanr'];
                            $k_ordrenr[$z] = $r['ordrenr'];
                            $kobsdate[$z] = $r['kobsdate'];
                            if ($r['antal'] > 0) {
                                $kobsdebet[$z] = $r['antal'] * $kostpris[$y];
                                $kobskredit[$z] = 0;
                            } else {
                                $kobskredit[$z] = $r['antal'] * $kostpris[$y] * -1;
                                $kobsdebet[$z] = 0;
                            }
                            $z++;
                        }
                    }
                }
            }
        }
        
        $z = 0;
        $salgsdate = array();
        $salgsdebet = array();
        $salgskredit = array();
        $s_fakturanr = array();
        $s_ordrenr = array();
        
        $q = db_select("select ordre_id,vare_id,antal,salgsdate,fakturanr,ordrenr from batch_salg,ordrer where salgsdate >= '$regnstart' and salgsdate <= '$regnslut' and ordrer.id=batch_salg.ordre_id order by salgsdate,ordre_id", __FILE__ . " linje " . __LINE__);
        while ($r = db_fetch_array($q)) {
            if ($z && isset($salgsdate[$z]) && $r['salgsdate'] == $salgsdate[$z]) {
                for ($y = 0; $y < count($vare_id); $y++) {
                    if ($r['vare_id'] == $vare_id[$y]) {
                        if ($kontotype == 'D') {
                            if ($r['antal'] > 0) $salgsdebet[$z] += $r['antal'] * $kostpris[$y];
                            else $salgskredit[$z] -= $r['antal'] * $kostpris[$y];
                        } elseif (in_array($kontonr, $varelager_u)) {
                            if ($r['antal'] > 0) $salgskredit[$z] += $r['antal'] * $kostpris[$y];
                            else $salgsdebet[$z] -= $r['antal'] * $kostpris[$y];
                        }
                    }
                }
            } else {
                for ($y = 0; $y < count($vare_id); $y++) {
                    if ($r['vare_id'] == $vare_id[$y]) {
                        if ($kontotype == 'D') {
                            $s_fakturanr[$z] = $r['fakturanr'];
                            $s_ordrenr[$z] = $r['ordrenr'];
                            $salgsdate[$z] = $r['salgsdate'];
                            if ($r['antal'] > 0) {
                                $salgsdebet[$z] = $r['antal'] * $kostpris[$y];
                                $salgskredit[$z] = 0;
                            } else {
                                $salgskredit[$z] = $r['antal'] * $kostpris[$y] * -1;
                                $salgsdebet[$z] = 0;
                            }
                            $z++;
                        } elseif (in_array($kontonr, $varelager_u)) {
                            $s_fakturanr[$z] = $r['fakturanr'];
                            $s_ordrenr[$z] = $r['ordrenr'];
                            $salgsdate[$z] = $r['salgsdate'];
                            if ($r['antal'] > 0) {
                                $salgskredit[$z] = $r['antal'] * $kostpris[$y];
                                $salgsdebet[$z] = 0;
                            } else {
                                $salgsdebet[$z] = $r['antal'] * $kostpris[$y] * -1;
                                $salgskredit[$z] = 0;
                            }
                            $z++;
                        }
                    }
                }
            }
        }
        
        $dato = $regnstart;
        $y = 0;
        $kd = 0;
        $sd = 0;
        $trd = array();
        $bil = array();
        $fakt = array();
        $ordre = array();
        $besk = array();
        $deb = array();
        $kre = array();
        
        while ($dato <= $regnslut) {
            while (isset($kobsdate[$kd]) && $kobsdate[$kd] == $dato) {
                $trd[$y] = $dato;
                $bil[$y] = 0;
                $fakt[$y] = isset($k_fakturanr[$kd]) ? $k_fakturanr[$kd] : '';
                $ordre[$y] = isset($k_ordrenr[$kd]) ? $k_ordrenr[$kd] : 0;
                $besk[$y] = "lagertransaktion - Køb";
                $deb[$y] = isset($kobsdebet[$kd]) ? $kobsdebet[$kd] : 0;
                $kre[$y] = isset($kobskredit[$kd]) ? $kobskredit[$kd] : 0;
                $kd++;
                $y++;
            }
            while (isset($salgsdate[$sd]) && $salgsdate[$sd] == $dato) {
                $trd[$y] = $dato;
                $bil[$y] = 0;
                $fakt[$y] = isset($s_fakturanr[$sd]) ? $s_fakturanr[$sd] : '';
                $ordre[$y] = isset($s_ordrenr[$sd]) ? $s_ordrenr[$sd] : 0;
                $besk[$y] = "lagertransaktion - Salg";
                $deb[$y] = isset($salgsdebet[$sd]) ? $salgsdebet[$sd] : 0;
                $kre[$y] = isset($salgskredit[$sd]) ? $salgskredit[$sd] : 0;
                $sd++;
                $y++;
            }
            list($yy, $mm, $dd) = explode("-", $dato);
            $dd++;
            if (!checkdate($mm, $dd, $yy)) {
                $dd = 1;
                $mm++;
                if ($mm > 12) {
                    $mm = 1;
                    $yy++;
                }
            }
            $dd *= 1;
            $mm *= 1;
            if (strlen($dd) < 2) $dd = '0' . $dd;
            if (strlen($mm) < 2) $mm = '0' . $mm;
            $dato = $yy . "-" . $mm . "-" . $dd;
        }
        for ($y = 0; $y < count($trd); $y++) {
            $transdate[$y] = $trd[$y];
            $fakturanr[$y] = isset($fakt[$y]) ? $fakt[$y] : '';
            $ordrenr[$y] = isset($ordre[$y]) ? $ordre[$y] : 0;
            $bilag[$y] = isset($bil[$y]) ? $bil[$y] : '';
            $beskrivelse[$y] = isset($besk[$y]) ? $besk[$y] : '';
            $debet[$y] = isset($deb[$y]) ? $deb[$y] : 0;
            $kredit[$y] = isset($kre[$y]) ? $kre[$y] : 0;
        }
    }
    return array($transdate, $fakturanr, $ordrenr, $bilag, $beskrivelse, $debet, $kredit);
}

print "</td></tr></tbody></table>";


if ($menu == 'T') {
    include_once '../includes/topmenu/footer.php';
} else {
    include_once '../includes/oldDesign/footer.php';
}
?>
<style>

    /* Replace the existing .outer-datatable-wrapper styles */
.outer-datatable-wrapper {
    display: grid;
    grid-template-rows: auto 1fr auto;
    height: 100%;
    width: 100%;
}

.datatable thead {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #f4f4f4;
}

.datatable tbody {
    overflow-y: auto;
    max-height: calc(100vh - 200px);
}

.datatable tfoot {
    position: sticky;
    bottom: 0;
    z-index: 10;
    background-color: #f4f4f4;
}
	
    a:link{
		text-decoration: none;
	}
</style>