<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kladdeliste.php --- patch 5.0.0 --- 2026.01.26 --- 
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2026 Saldi.dk ApS
// -----------------------------------------------------------------------------------
// 20150722 PHR Vis alle/egne gemmes nu som cookie. 
// 20181220 MSC - Rettet ny kladde knap til Ny
// 20190130 MSC - Rettet topmenu design til
// 20210211 PHR - Some cleanup
// 20211112 MSC - Implementing new design
// 20220627 MSC - Implementing new design
// 20220930 MSC - Changed new button text to a plus icon, if the design is topmenu
// 20230708 LOE - A minor modification
// 12/02/2025 PBLM - Added a new button to open the digital approver
// 16/05/2025 make sure the back button redirect too the previous page rather than going back to the dashboard
// 20251021 LOE Added pagination and static header and footer
// 20260126 PHR fixed $exitDraft

@session_start();
$s_id=session_id();

// delete functionality for cash journals - 2025-10-18
if (isset($_GET['delete_kladde'])) {
    $kladde_id = (int)$_GET['delete_kladde'];
    
    include("../includes/connect.php");
    include("../includes/std_func.php");
    
    $qtxt = "SELECT db FROM online WHERE session_id = '$s_id' ORDER BY logtime DESC LIMIT 1";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    $delete_error = null;
    
    if ($r = db_fetch_array($q)) {
        $db = trim($r['db']);
        
        if ($db && $db != $sqdb) {
            $connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " linje " . __LINE__);
        }
        
        $check_query = db_select("SELECT bogfort FROM kladdeliste WHERE id = $kladde_id", __FILE__ . " linje " . __LINE__);
        if ($check_row = db_fetch_array($check_query)) {
            if ($check_row['bogfort'] == '-' || $check_row['bogfort'] == '!') {
                $count_query = db_select("SELECT COUNT(*)::integer as entry_count FROM kassekladde WHERE kladde_id = $kladde_id", __FILE__ . " linje " . __LINE__);
                $count_row = db_fetch_array($count_query);
                $entry_count = intval($count_row['entry_count']);
                
                if ($entry_count == 0) {
                    db_modify("DELETE FROM kladdeliste WHERE id = $kladde_id", __FILE__ . " linje " . __LINE__);
                    header("Location: kladdeliste.php?reset_grid=kladdelst");
                    exit;
                } else {
                    $delete_error = "not_empty:$entry_count";
                }
            } else {
                $delete_error = "already_posted";
            }
        } else {
            $delete_error = "not_found";
        }
    } else {
        $delete_error = "session_expired";
    }
    
    header("Location: kladdeliste.php?delete_error=" . urlencode($delete_error));
    exit;
}

if (isset($_POST['delete_kladde'])) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    ob_start();
    
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
    
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error', 
                'message' => 'Fatal error: ' . $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        }
    });
    
    try {
        $kladde_id = (int)$_POST['delete_kladde'];
        
        if ($kladde_id <= 0) {
            throw new Exception("Invalid kladde_id: " . $_POST['delete_kladde']);
        }
        
        include("../includes/connect.php");
        include("../includes/std_func.php");
        
        $qtxt = "SELECT db FROM online WHERE session_id = '$s_id' ORDER BY logtime DESC LIMIT 1";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($r = db_fetch_array($q)) {
            $db = trim($r['db']);
            
            if ($db && isset($sqdb) && $db != $sqdb) {
                $connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " linje " . __LINE__);
            }
            
            $check_query = db_select("SELECT bogfort FROM kladdeliste WHERE id = $kladde_id", __FILE__ . " linje " . __LINE__);
            if ($check_row = db_fetch_array($check_query)) {
                if ($check_row['bogfort'] == '-' || $check_row['bogfort'] == '!') {
                    $count_query = db_select("SELECT COUNT(*)::integer as entry_count FROM kassekladde WHERE kladde_id = $kladde_id", __FILE__ . " linje " . __LINE__);
                    $count_row = db_fetch_array($count_query);
                    $entry_count = intval($count_row['entry_count']);
                    
                    if ($entry_count == 0) {
                        db_modify("DELETE FROM kladdeliste WHERE id = $kladde_id", __FILE__ . " linje " . __LINE__);
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'deleted', 'id' => $kladde_id]);
                        exit;
                    } else {
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'not_empty', 'entry_count' => $entry_count]);
                        exit;
                    }
                } else {
                    ob_end_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'already_posted', 'bogfort' => $check_row['bogfort']]);
                    exit;
                }
            } else {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['status' => 'not_found', 'id' => $kladde_id, 'db' => $db]);
                exit;
            }
        } else {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Session expired or not found', 'session_id' => $s_id]);
            exit;
        }
    } catch (Exception $e) {
        $buffered = ob_get_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => $e->getMessage(), 
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'buffered' => substr($buffered, 0, 500)
        ]);
        exit;
    }
}
	
$css="../css/standard.css";		
$modulnr=2;	
$title="kladdeliste";	
$backUrl = isset($_GET['returside'])
? $_GET['returside']
: '../index/menu.php';
include("../includes/connect.php");
include("../includes/std_func.php");
$query = db_select("SELECT * FROM settings WHERE var_name = 'apiKey' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
$apiKey = db_fetch_array($query)["var_value"];
include("../includes/online.php");
include("../includes/topline_settings.php"); 

print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/jquery-3.6.4.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/moment.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/daterangepicker.min.js\" defer></script>";
print '<link rel="stylesheet" type="text/css" href="../css/daterangepicker.css" />';

$exitDraft = if_isset($_GET['exitDraft']);
if ($exitDraft) {
	$qtxt = "update kladdeliste set hvem = '', tidspkt = NULL where id = '$exitDraft'";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

if (strpos(findtekst('639|Kladdeliste', $sprog_id),'undtrykke')) {
	$qtxt = "update tekster set tekst = '' where tekst_id >= '600'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

$valg = "Kladdeliste";
include("topLineFinans.php");
include("../includes/grid.php");

// Reset grid if requested
if (isset($_GET['reset_grid']) && $_GET['reset_grid'] == 'kladdelst') {
    $qtxt = "DELETE FROM datatables WHERE user_id = $bruger_id AND tabel_id='kladdelst'";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
    header("Location: kladdeliste.php");
    exit;
}

$tidspkt=date("U");
$columns = array();

$columns[] = array(
    "field" => "id",
    "headerName" => "ID",
    "width" => "0.5",
    "type" => "text",  
    "align" => "right",
    "sqlOverride" => "k.id",
    "valueGetter" => function ($value, $row, $column) {
        return $value;
    },
    "generateSearch" => function ($column, $term) {
        $field = $column['sqlOverride'] ? $column['sqlOverride'] : $column['field'];
        $term = db_escape_string(trim($term, "'"));
        
        if (empty($term)) {
            return "1=1";
        }
        
        // Cast integer field to text for ILIKE search
        return "$field::text ILIKE '%$term%'";
    },
    "render" => function ($value, $row, $column) use ($tidspkt, $popup, $jsvars, $brugernavn) {
        $id = $row['id']; // Use raw ID from row, not $value which may have highlighting
        $kladde = "kladde" . $id;
        $locked = false;
        
        if (isset($row['tidspkt'])) {
            if (strpos($row['tidspkt'], ' ')) {
                list($a, $b) = explode(" ", $row['tidspkt']);
            } elseif ($row['tidspkt']) {
                $b = $row['tidspkt'];
            } else {
                $b = 0;
            }
            $locked = !($tidspkt - trim(intval($b)) > 3600 || $row['hvem'] == $brugernavn);
        }
        
        if ($locked) {
            global $sprog_id;
            $url = "kassekladde.php?tjek=$id&kladde_id=$id&returside=kladdeliste.php";
            return "<td align='{$column['align']}' onclick=\"window.location.href='$url'\" style='cursor:pointer'><a href='$url' title='" . findtekst('1607|Kladde er låst af', $sprog_id) . " {$row['hvem']}' style='color:#FF0000'>$value</a></td>";
        }
        
        $url = "kassekladde.php?tjek=$id&kladde_id=$id&returside=kladdeliste.php";
        return "<td align='{$column['align']}' onclick=\"window.location.href='$url'\" style='cursor:pointer'><a href='$url'>$value</a></td>";
    },
);

$columns[] = array(
    "field" => "kladdedate",
    "headerName" => findtekst('635|Dato', $sprog_id),
    "width" => "1",
    "type" => "text",
    "sqlOverride" => "k.kladdedate",
    "render" => function ($value, $row, $column) {
        $url = "kassekladde.php?tjek={$row['id']}&kladde_id={$row['id']}&returside=kladdeliste.php";
        $formatted = dkdato($value);
        return "<td align='{$column['align']}' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$formatted</td>";
    },
    "generateSearch" => function ($column, $term) {
        $field = $column['sqlOverride'] ? $column['sqlOverride'] : $column['field'];
        $term = trim($term);
        
        if (empty($term)) {
            return "1=1";
        }
        
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $term, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            $sqlDate = "$year-$month-$day";
            $sqlDate = db_escape_string($sqlDate);
            return "$field = '$sqlDate'";
        }
        
        // date range (date1:date2)
        if (strpos($term, ':') !== false) {
            list($date1, $date2) = explode(":", $term, 2);
            $date1 = trim($date1);
            $date2 = trim($date2);
            
            if (empty($date1) || empty($date2)) {
                return "1=1";
            }
            
            if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $date1, $m1)) {
                $date1 = $m1[3] . '-' . str_pad($m1[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m1[1], 2, '0', STR_PAD_LEFT);
            }
            if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $date2, $m2)) {
                $date2 = $m2[3] . '-' . str_pad($m2[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m2[1], 2, '0', STR_PAD_LEFT);
            }
            
            $date1 = db_escape_string($date1);
            $date2 = db_escape_string($date2);
            
            return "$field >= '$date1' AND $field <= '$date2'";
        }
        
        $term = db_escape_string($term);
        return "$field::text ILIKE '%$term%'";
    },
);

$columns[] = array(
    "field" => "oprettet_af",
    "headerName" => findtekst('634|Ejer', $sprog_id),
    "width" => "1",
    "sqlOverride" => "k.oprettet_af",
    "render" => function ($value, $row, $column) {
        global $charset;
        $url = "kassekladde.php?tjek={$row['id']}&kladde_id={$row['id']}&returside=kladdeliste.php";
        $clean_value = strip_tags(stripslashes($value));
        $safe_value = htmlentities($clean_value, ENT_QUOTES, $charset);
        return "<td align='{$column['align']}' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$safe_value</td>";
    },
);

$columns[] = array(
    "field" => "kladdenote",
    "headerName" => findtekst('391|Bemærkning', $sprog_id),
    "width" => "3",
    "sqlOverride" => "k.kladdenote",
    "render" => function ($value, $row, $column) {
        $url = "kassekladde.php?tjek={$row['id']}&kladde_id={$row['id']}&returside=kladdeliste.php";
        return "<td align='{$column['align']}' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$value</td>";
    },
);

$columns[] = array(
    "field" => "bogfort",
    "headerName" => findtekst('637|Bogført', $sprog_id),
    "width" => "1",
    "align" => "center",
    "type" => "text",
    "sqlOverride" => "CASE WHEN k.bogforingsdate IS NOT NULL AND k.bogforingsdate != '' THEN k.bogforingsdate ELSE k.bogfort END",
    "render" => function ($value, $row, $column) {
        $url = "kassekladde.php?tjek={$row['id']}&kladde_id={$row['id']}&returside=kladdeliste.php";
        if ($row['bogforingsdate'] && $row['bogforingsdate'] != '') {
            $formatted = dkdato($row['bogforingsdate']);
            return "<td align='{$column['align']}' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$formatted</td>";
        }
        return "<td align='{$column['align']}' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$value</td>";
    },
    "generateSearch" => function ($column, $term) {
        $term = trim($term);
        
        if (empty($term)) {
            return "1=1";
        }
        
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $term, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            $sqlDate = "$year-$month-$day";
            $sqlDate = db_escape_string($sqlDate);
            return "k.bogforingsdate = '$sqlDate'";
        }
        
        // Handle short search terms (like '-' or '!')
        if (strlen($term) <= 2 && strpos($term, '-') === false && strpos($term, ':') === false && strpos($term, '/') === false) {
            $term = db_escape_string($term);
            return "(k.bogfort ILIKE '%$term%' OR k.bogforingsdate::text ILIKE '%$term%')";
        }
        
        if (strpos($term, ':') !== false) {
            list($date1, $date2) = explode(":", $term, 2);
            $date1 = trim($date1);
            $date2 = trim($date2);
            
            if (empty($date1) || empty($date2)) {
                return "1=1";
            }
            
            if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $date1, $m1)) {
                $date1 = $m1[3] . '-' . str_pad($m1[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m1[1], 2, '0', STR_PAD_LEFT);
            }
            if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $date2, $m2)) {
                $date2 = $m2[3] . '-' . str_pad($m2[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m2[1], 2, '0', STR_PAD_LEFT);
            }
            
            $date1 = db_escape_string($date1);
            $date2 = db_escape_string($date2);
            
            return "(k.bogforingsdate >= '$date1' AND k.bogforingsdate <= '$date2')";
        }
        
        $term = db_escape_string($term);
        return "(k.bogforingsdate::text ILIKE '%$term%' OR k.bogfort ILIKE '%$term%')";
    },
);

$columns[] = array(
    "field" => "bogfort_af",
    "headerName" => findtekst('638|Af', $sprog_id),
    "width" => "1",
    "align" => "center",
    "sqlOverride" => "k.bogfort_af",
    "render" => function ($value, $row, $column) {
        $url = "kassekladde.php?tjek={$row['id']}&kladde_id={$row['id']}&returside=kladdeliste.php";
        return "<td align='{$column['align']}' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$value</td>";
    },
);

$columns[] = array(
    "field" => "delete",
    "headerName" => findtekst('1099|Slet', $sprog_id),
    "width" => "0.8",
    "align" => "center",
    "sortable" => false,
    "searchable" => false,
    "sqlOverride" => "k.bogfort as delete_bogfort",
    "render" => function ($value, $row, $column) {
        global $sprog_id;
        // Only show delete button for non-posted AND empty journals
        $isNotPosted = ($row['bogfort'] == '-' || $row['bogfort'] == '!');
        $entryCount = isset($row['entry_count']) ? intval($row['entry_count']) : -1;
        $isEmpty = ($entryCount == 0);
        $kladdeId = intval($row['id']);
        
        if ($isNotPosted && $isEmpty) {
            return "<td align='{$column['align']}'>
                <button type='button' onclick=\"event.preventDefault(); event.stopPropagation(); deleteKladde({$kladdeId}); return false;\" style='
                    background-color: #dc3545;
                    color: white;
                    border: none;
                    padding: 6px 10px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 11px;
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    transition: background-color 0.2s ease;
                ' 
                onmouseover=\"this.style.backgroundColor='#c82333';\"
                onmouseout=\"this.style.backgroundColor='#dc3545';\"
                title='" . findtekst('1099|Slet', $sprog_id) . " kassekladde #{$kladdeId}'>
                <i class='fa fa-trash-o' style='font-size: 12px;'></i>
                " . findtekst('1099|Slet', $sprog_id) . "
                </button>
            </td>";
        } elseif ($isNotPosted && !$isEmpty) {
            if ($sprog_id == 2) {
                $notEmptyText = 'The cash journal is not empty and cannot be deleted';
            } elseif ($sprog_id == 3) {
                $notEmptyText = 'Kassekladden er ikke tom og kan derfor ikke slettes';
            } else {
                $notEmptyText = 'Kassekladden er ikke tom og kan derfor ikke slettes';
            }
            return "<td align='{$column['align']}' title='{$notEmptyText}' style='color:#999; font-size:10px;'>($entryCount)</td>";
        }
        return "<td align='{$column['align']}'></td>";
    },
);

###############
$q = "SELECT filter_setup 
      FROM datatables 
      WHERE user_id = $bruger_id AND tabel_id='kladdelst'";
$kAd = db_fetch_array(db_select($q, __FILE__ . " line " . __LINE__));
if ($kAd) {
    $filters = json_decode($kAd['filter_setup'], true);
    $vis = $filters[0]['options'][0]['name'] ?? 'egne';
}
#############
$filters = array();

if (isset($_GET['filter']) && in_array($_GET['filter'], ['alle','egne'], true)) {
    $saved_filter = $_GET['filter'];
}elseif($vis && in_array($vis, ['alle','egne'], true)){
    $saved_filter = $vis;
}else {
    $saved_filter = 'egne';
}

$filters[] = [
    'filterName' => 'filter',
    'options' => [
        [
            'name'    => $saved_filter,
            'checked' => true
        ]
    ],
];
$where = [];

if ($saved_filter === 'egne') {
    $where[] = "k.oprettet_af = '" . db_escape_string($brugernavn) . "'";
}

$sqlWhere = $where ? implode(' AND ', $where) : '1=1';

########################
$filterSetupJson = db_escape_string(json_encode($filters));

if($kAd){
    db_modify("
        UPDATE datatables
        SET filter_setup = '$filterSetupJson'
        WHERE user_id = $bruger_id
          AND tabel_id = 'kladdelst'"
    ,__FILE__ . " linje " . __LINE__);
}
########################


$data = array(
    'query' => "
SELECT 
    k.id,
    k.kladdedate,
    k.oprettet_af,
    k.kladdenote,
    k.bogfort,
    k.bogforingsdate,
    k.bogfort_af,
    k.tidspkt,
    k.hvem,
    -- Count entries in kassekladde to determine if journal is empty
    (SELECT COUNT(*) FROM kassekladde kk WHERE kk.kladde_id = k.id) as entry_count,
    -- Sort order: non-posted first (- and !), then posted (S, V, etc)
    CASE 
        WHEN k.bogfort IN ('-', '!') THEN 0
        ELSE 1
    END as sort_group
FROM kladdeliste k
WHERE $sqlWhere AND {{WHERE}}
ORDER BY sort_group, k.id DESC
",
    'rowStyle' => function ($row) {
        return "";
    },
    'columns' => $columns,
    'filters' => $filters,
);

print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
create_datagrid("kladdelst", $data);
print "</div>";

print "<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date pickers for each date field separately
    const kladdedateInput = document.querySelector(\"input[name='search[kladdelst][kladdedate]']\");
    const bogfortInput = document.querySelector(\"input[name='search[kladdelst][bogfort]']\");
    
    // Function to initialize a single datepicker
    function initDatepicker(input) {
        if (!input) return;
        
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
            autoApply: false,
            startDate: startDate,
            minYear: 1900,
            maxYear: parseInt(moment().format('YYYY'), 10) + 10,
            locale: {
                format: 'DD-MM-YYYY',
                cancelLabel: '".findtekst('2117|Ryd', $sprog_id)."',
                applyLabel: '".findtekst('913|Søg', $sprog_id)."',
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
        
        // Show date in field immediately when date is selected from calendar
        $(input).on('show.daterangepicker', function(ev, picker) {
            // Update field when date changes in the picker
            picker.container.find('.calendar-table').off('click.updateField').on('click.updateField', 'td.available', function() {
                setTimeout(function() {
                    var selectedDate = picker.startDate.format('DD-MM-YYYY');
                    $(input).val(selectedDate);
                }, 10);
            });
        });
        
        // When user clicks \"Søg\" (Apply/Search) button - submit the form
        $(input).on('apply.daterangepicker', function(ev, picker) {
            var selectedDate = picker.startDate.format('DD-MM-YYYY');
            $(this).val(selectedDate);
            
            // Submit the form
            var form = $(this).closest('form');
            if (form.length > 0) {
                form.submit();
            }
        });
        
        // When user clicks \"Ryd\" (Clear/Cancel) button
        $(input).on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            
            // Submit form to clear the filter
            var form = $(this).closest('form');
            if (form.length > 0) {
                form.submit();
            }
        });
    }
    
    // Initialize each datepicker separately
    initDatepicker(kladdedateInput);
    initDatepicker(bogfortInput);

    setTimeout(function() {
        // Find the first posted draft row and add a separator before it
        let foundSeparator = false;
        const rows = document.querySelectorAll('#datatable-kladdelst tbody tr');
        
        rows.forEach(function(row, index) {
            const cells = row.querySelectorAll('td');
            
            if (cells.length >= 7 && !foundSeparator) {
                // Check the 'Posted' column (5th column - index 4)
                const postedCell = cells[4];
                const postedText = postedCell.textContent.trim();
                
                // If this row has a posted date (not '-' or '!')
                // Check for date patterns: Danish dates like \"09-10-2017\" or \"02-05-2019\"
                const isPosted = postedText && 
                                postedText !== '-' && 
                                postedText !== '!' && 
                                postedText !== '' &&
                                /\d{2}[-\/]\d{2}[-\/]\d{4}/.test(postedText);
                
                if (isPosted) {
                    foundSeparator = true;
                    
                    // Create separator row with translated text
                    const separatorRow = document.createElement('tr');
                    separatorRow.style.backgroundColor = '#f0f0f0';
                    separatorRow.style.fontWeight = 'bold';
                    separatorRow.innerHTML = '<td colspan=\"2\" style=\"text-align: center; font-weight: bold; padding: 12px; background-color: #f0f0f0;\">".findtekst('1093|Bogførte kladder', $sprog_id)."</td><td colspan=\"5\" style=\"background-color: #f0f0f0; padding: 12px;\"><hr style=\"margin: 0; border: 0; border-top: 2px solid rgb(84, 99, 84);\"></td>';
                    
                    // Insert before this row
                    row.parentNode.insertBefore(separatorRow, row);
                }
            }
        });
    }, 0);
});
</script>";


if ($sprog_id == 2) {
    $deleteConfirmText = 'Do you want to delete this cash journal?';
} elseif ($sprog_id == 3) {
    $deleteConfirmText = 'Vil du slette denne kassekladden?';
} else {
    $deleteConfirmText = 'Vil du slette denne kassekladde?';
}

$deleteConfirmTextJS = json_encode($deleteConfirmText);

print("<script>
// Delete function for cash journals - Version 2
function deleteKladde(kladdeId) {
    console.log('deleteKladde v2 called with ID:', kladdeId);
    
    // Validate the ID
    kladdeId = parseInt(kladdeId, 10);
    if (!kladdeId || isNaN(kladdeId) || kladdeId <= 0) {
        console.error('Invalid kladde ID:', kladdeId);
        alert('Error: Invalid ID');
        return false;
    }
    
    var confirmMsg = {$deleteConfirmTextJS};
    console.log('Confirm message:', confirmMsg);
    if (confirm(confirmMsg + ' (ID: ' + kladdeId + ')')) {
        console.log('User confirmed deletion of ID:', kladdeId);
        
        // Use AJAX to delete with cache-busting
        var xhr = new XMLHttpRequest();
        var url = 'kladdeliste.php?_t=' + Date.now();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        xhr.setRequestHeader('Pragma', 'no-cache');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                console.log('Delete response status:', xhr.status);
                console.log('Delete response text:', xhr.responseText);
                console.log('Response length:', xhr.responseText ? xhr.responseText.length : 0);
                
                // Check if response is empty
                if (!xhr.responseText || xhr.responseText.trim() === '') {
                    console.error('Empty response from server');
                    console.error('Response headers:', xhr.getAllResponseHeaders());
                    alert('Server returned empty response. Please check server logs. Reloading...');
                    window.location.href = 'kladdeliste.php?_t=' + Date.now();
                    return;
                }
                
                try {
                    var result = JSON.parse(xhr.responseText);
                    console.log('Parsed result:', result);
                    
                    if (result.status === 'deleted') {
                        console.log('Successfully deleted ID:', result.id, 'Reloading page...');
                        // Force a fresh reload with new URL to bypass cache
                        window.location.href = 'kladdeliste.php?_t=' + Date.now();
                    } else if (result.status === 'not_empty') {
                        alert('Cannot delete: Cash journal has ' + result.entry_count + ' entries');
                    } else if (result.status === 'already_posted') {
                        alert('Cannot delete: Cash journal is already posted');
                    } else if (result.status === 'not_found') {
                        alert('Cannot delete: Cash journal not found (ID: ' + kladdeId + ', DB: ' + (result.db || 'unknown') + ')');
                    } else if (result.status === 'error') {
                        alert('Error: ' + result.message + (result.file ? ' in ' + result.file + ':' + result.line : ''));
                        console.error('Server error details:', result);
                    } else {
                        alert('Unexpected status: ' + result.status + ' - ' + JSON.stringify(result));
                        window.location.href = 'kladdeliste.php?_t=' + Date.now();
                    }
                } catch(e) {
                    console.error('JSON parse error:', e);
                    console.error('Response was:', xhr.responseText);
                    // Check if it looks like HTML (PHP error page)
                    if (xhr.responseText.indexOf('<') === 0 || xhr.responseText.indexOf('<!') === 0) {
                        alert('Server returned HTML instead of JSON. Check PHP error logs.');
                    } else {
                        alert('Failed to parse response: ' + xhr.responseText.substring(0, 300));
                    }
                }
            }
        };
        xhr.onerror = function() {
            console.error('XHR network error occurred');
            alert('Network error occurred');
        };
        xhr.ontimeout = function() {
            console.error('XHR timeout');
            alert('Request timed out');
        };
        xhr.timeout = 30000; // 30 second timeout
        console.log('Sending delete request for ID:', kladdeId, 'to URL:', url);
        xhr.send('delete_kladde=' + kladdeId);
    } else {
        console.log('User cancelled deletion');
    }
    return false;
}
</script>");





// Tutorial setup 
$steps = array();
$steps[] = array(
	"selector" => "#create-new",
	"content" => findtekst('2607|Opret ny kassekladde ved at klikke her', $sprog_id).".",
);

include(__DIR__ . "/../includes/tutorial.php");
create_tutorial("kladlist", $steps);


?>



<script>
// Toggle buttons
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {

      var footerBox = document.querySelector('tfoot tr td #footer-box');
        if (!footerBox) return;

        // Footer layout (right-aligned by default)
        footerBox.style.display = 'flex';
        footerBox.style.justifyContent = 'flex-end';
        footerBox.style.alignItems = 'center';
        footerBox.style.position = 'relative';

        // Create container
        var filterButtonContainer = document.createElement('div');
        filterButtonContainer.id = 'filter-buttons-container';

        // Center ONLY this container
        filterButtonContainer.style.cssText =
            'position:absolute;' +
            'left:50%;' +
            'transform:translateX(-50%);' +
            'display:flex;' +
            'align-items:center;' +
            'justify-content:center;';

        // Append container
        footerBox.appendChild(filterButtonContainer);



        
        var toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.id = 'filter-toggle-btn'; 
        toggleBtn.style.cssText =
            'padding:4px 8px; border:1px solid #999; border-radius:4px; height:22px; color:white; cursor:pointer; font-size:12px;';

       
        var sidebar = window.parent.document.querySelector('.sidebar');
        if (sidebar) {
            var sidebarBg = window.getComputedStyle(sidebar).backgroundColor;
            toggleBtn.style.backgroundColor = sidebarBg;
        } else {
            toggleBtn.style.backgroundColor = 'blue';
        }

        
        var serverFilter = '<?php echo $saved_filter; ?>';

        // Helpers
        function getCurrentFilter() {
            var urlParams = new URLSearchParams(window.location.search);
            var filter = urlParams.get('filter') || serverFilter || localStorage.getItem('kladde_filter') || 'alle';
            
            // Validate filter
            if (filter !== 'alle' && filter !== 'egne') {
                filter = 'alle';
            }
            return filter;
        }

        function setButtonText(filter) {
            toggleBtn.textContent = (filter === 'alle')
                ? '<?php echo findtekst("641|Vis egne", $sprog_id); ?>'
                : '<?php echo findtekst("636|Vis alle", $sprog_id); ?>';
        }

        function applyFilter(filter) {
            localStorage.setItem('kladde_filter', filter);

            var filterForm = document.querySelector('form[name^="filter_form"]');
            if (filterForm) {
                var inputs = filterForm.querySelectorAll('input[type="radio"], input[type="checkbox"]');
                inputs.forEach(function(input) {
                    input.checked = input.value && input.value.includes(filter);
                });
                filterForm.submit();
            } else {
                window.location.href = 'kladdeliste.php?filter=' + filter;
            }
        }

        // Initial state
        var currentFilter = getCurrentFilter();
        setButtonText(currentFilter);

        // Toggle behavior
        toggleBtn.onclick = function() {
            currentFilter = (currentFilter === 'alle') ? 'egne' : 'alle';
            setButtonText(currentFilter);
            applyFilter(currentFilter);
        };

        // Insert into footer
        filterButtonContainer.appendChild(toggleBtn);
        footerBox.insertBefore(filterButtonContainer, footerBox.firstChild);

    }, 300);
});
</script>













