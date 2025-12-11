<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/productLookup.php --- patch 4.1.1 --- 2025-12-11 ---
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
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// 20251210 LOE Moved from ordre.php and improved to use grid.php structure

@session_start();
$s_id = session_id();

$css = "../css/standard.css?v=27";

$include_start = microtime(true);
include("../includes/std_func.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/stdFunc/dkDecimal.php");
include("../includes/ordrefunc.php");

// Get parameters from order page
$id = if_isset($_GET, NULL, 'id');
$sort = if_isset($_GET, NULL, 'sort');
$fokus = if_isset($_GET, NULL, 'fokus');
$vis = if_isset($_GET, NULL, 'vis');
$find = if_isset($_GET, NULL, 'find');
$lager = if_isset($_GET, NULL, 'lager');
$konto_id = if_isset($_GET, NULL, 'konto_id');
$kontonr = if_isset($_GET, NULL, 'kontonr');

$x = if_isset($_GET, NULL, 'x');

global $bgcolor, $bgcolor5, $charset, $linjebg, $sprog_id, $menu;
if (isset($_COOKIE['valg'])) {
    $valg = $_COOKIE['valg']; // coming from ordreliste
}

// Handle product selection - redirect back to order
if (isset($_GET['vare_id'])) {
    $vare_id = $_GET['vare_id'];
    $url = "ordre.php?id=$id&vare_id=$vare_id&fokus=$fokus&konto_id=$konto_id&lager=$lager";
    header("Location: $url");
    exit;
}

// Include header based on menu type
if ($menu == 'T') {
    include_once '../includes/top_header.php';
    include_once '../includes/top_menu.php';
}



#############

##########
// Handle find parameter 
if ($find) {
    $find = str_replace("*", "%", $find);
}

// Get supplier info from order 
if (!$konto_id) {
    if ((!$kontonr) && ($id)) {
        $query = db_select("select kontonr from ordrer where id = $id", __FILE__ . " linje " . __LINE__);
        if ($row = db_fetch_array($query)) $kontonr = trim($row['kontonr']);
    }
    if ($kontonr) {
        $query = db_select("select id from adresser where kontonr = '$kontonr' and art = 'K'", __FILE__ . " linje " . __LINE__);
        if ($row = db_fetch_array($query)) $konto_id = $row['id'];
    }
}

// Set page header 
sidehoved($id, "../kreditor/ordre.php", "../lager/varekort.php", "$fokus&leverandor=$konto_id", "Leverand&oslash;rordre $id");

// Show price lists if available 
$listeantal = 0;
if ($id && $konto_id) {
    $q = db_select("select id,beskrivelse from grupper where art='PL' and box4='on' and box1='$konto_id' order by beskrivelse", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        $listeantal++;
        $prisliste[$listeantal] = $r['id'];
        $listenavn[$listeantal] = $r['beskrivelse'];
    }
    
    if ($listeantal) {
        echo "<table cellpadding='1' cellspacing='1' border='0' width='100%' valign='top' class='dataTable'><tbody><tr>";
        echo "<form name='prisliste' action='../includes/prislister.php?start=0&ordre_id=$id&fokus=$fokus' method='post'>";
        echo "<td><select name='prisliste'>";
        for ($x = 1; $x <= $listeantal; $x++) {
            echo "<option value='$prisliste[$x]'>$listenavn[$x]</option>";
        }
        echo "</select><input type='submit' style='width:120px;' name='prislist' value='Vis'></td>";
        echo "</form></tr></tbody></table>";
    }
}

// Set default sort
if (!$sort) $sort = 'varenr';

// Include grid system
include(get_relative() . "includes/grid.php");

// Custom search function for Danish number format (like in debitor file)
function danishNumberSearch($column, $term) {
    $field = $column['sqlOverride'] == '' ? $column['field'] : $column['sqlOverride'];
    $term = db_escape_string($term);
    
    // Check for number range (e.g., "10:50" or "10,50")
    if (strstr($term, ':') || strstr($term, ',')) {
        $term = str_replace(',', ':', $term);
        list($num1, $num2) = explode(":", $term, 2);
        $num1 = trim($num1);
        $num2 = trim($num2);
        return "round({$field}::numeric, 2) >= '".usdecimal($num1)."' 
                AND 
                round({$field}::numeric, 2) <= '".usdecimal($num2)."'";
    } else {
        $term = usdecimal($term);
        return "round({$field}::numeric, 2) >= $term 
                AND 
                round({$field}::numeric, 2) <= $term";
    }
}

// Column configuration
$columns = array();

$columns[] = array(
    "field" => "varenr",
    "headerName" => findtekst(917, $sprog_id),
    "sqlOverride" => "v.varenr",
    "width" => "1",
    "sortable" => true,
    "searchable" => true,
    "type" => "text",
    "render" => function ($value, $row, $column) use ($id, $fokus, $lager, $konto_id) {
        $lev_id = isset($row['lev_id']) ? $row['lev_id'] : $konto_id;
        $url = "ordre.php?vare_id=" . $row['vare_id'] . "&fokus=$fokus&konto_id=$lev_id&id=$id&lager=$lager";
        $display_value = trim($value);
        return "<td><a href='$url' style='text-decoration: underline; color: inherit;'>$display_value</a></td>";
    }
);

$columns[] = array(
    "field" => "enhed",
    "headerName" => findtekst(945, $sprog_id),
    "width" => "0.5",
    "sqlOverride" => "v.enhed",
    "type" => "text",
    "sortable" => false,
    "searchable" => false,
    "render" => function ($value, $row, $column) {
        return "<td>$value</td>";
    }
);

$columns[] = array(
    "field" => "beskrivelse",
    "headerName" => findtekst(914, $sprog_id),
    "width" => "3",
    "sqlOverride" => "v.beskrivelse",
    "type" => "text",
    "sortable" => true,
    "searchable" => true,
    "render" => function ($value, $row, $column) {
        return "<td>$value</td>";
    }
);

$columns[] = array(
    "field" => "salgspris",
    "headerName" => findtekst(949, $sprog_id),
    "type" => "number",
    "align" => "right",
    "width" => "0.8",
    "searchable" => true,
    "decimalPrecision" => 2,
    "sqlOverride" => "v.salgspris",
    "sortable" => true,
    "generateSearch" => function ($column, $term) {
        return danishNumberSearch($column, $term);
    },
    "render" => function ($value, $row, $column) {
        $formatted = dkdecimal($value, 2);
        return "<td align='right'>$formatted</td>";
    }
);

$columns[] = array(
    "field" => "kostpris",
    "headerName" => findtekst(950, $sprog_id),
    "type" => "number",
    "align" => "right",
    "width" => "0.8",
    "searchable" => true,
    "decimalPrecision" => 2,
    "sqlOverride" => "COALESCE(vl.kostpris, v.kostpris)",
    "sortable" => true,
    "generateSearch" => function ($column, $term) {
        return danishNumberSearch($column, $term);
    },
    "render" => function ($value, $row, $column) {
        $formatted = dkdecimal($value, 2);
        return "<td align='right'>$formatted</td>";
    }
);

$columns[] = array(
    "field" => "beholdning",
    "headerName" => findtekst(980, $sprog_id),
    "type" => "number",
    "align" => "right",
    "width" => "0.5",
    "searchable" => true,
    "decimalPrecision" => 2,
    "sortable" => true,
    "generateSearch" => function ($column, $term) {
        return danishNumberSearch($column, $term);
    },
    "render" => function ($value, $row, $column) use ($lager, $sprog_id) {
        if ($lager >= 1) {
            $reserveret = 0;
            $vare_id = $row['vare_id'];
            
            $q2 = db_select("select * from batch_kob where vare_id=$vare_id and rest>0 and lager=$lager", __FILE__ . " linje " . __LINE__);
            while ($r2 = db_fetch_array($q2)) {
                $q3 = db_select("select * from reservation where batch_kob_id=$r2[id]", __FILE__ . " linje " . __LINE__);
                while ($r3 = db_fetch_array($q3)) {
                    $reserveret += $r3['antal'];
                }
            }
            
            $formatted = dkdecimal($value, 2);
            if ($reserveret > 0) {
                return "<td align='right'><span title='" . findtekst(1520, $sprog_id) . ": $reserveret'>$formatted &nbsp;</span></td>";
            } else {
                return "<td align='right'>$formatted &nbsp;</td>";
            }
        } else {
            $formatted = dkdecimal($value, 2);
            return "<td align='right'>$formatted &nbsp;</td>";
        }
    }
);

$columns[] = array(
    "field" => "firmanavn",
    "headerName" => findtekst(966, $sprog_id),
    "width" => "0.8",
    "align" => "left",
    "type" => "text",
    "sqlOverride" => "a.firmanavn",
    "searchable" => true,
    "sortable" => true,
    "render" => function ($value, $row, $column) {
        return "<td>$value</td>";
    }
);

$columns[] = array(
    "field" => "vare_id",
    "headerName" => findtekst(566, $sprog_id) ?: "Ret",
    "width" => "0.3",
    "align" => "right",
    "type" => "text",
    "searchable" => true,
    "sortable" => false,
    "render" => function ($value, $row, $column) use ($id, $fokus, $lager) {
        $url = "../lager/varekort.php?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus&id=" . $row['vare_id'] . "&lager=$lager";
        return "<td align='right'><a href='$url'>Ret</a></td>";
    }
);

if ($kontonr) {
    $columns[] = array(
        "field" => "toggle_view",
        "headerName" => $vis 
            ? "<a href='productLookup.php?sort=$sort&id=$id&fokus=$fokus&lager=$lager&konto_id=$konto_id&kontonr=$kontonr'><span title='" . findtekst(1517, $sprog_id) . "'>" . findtekst(565, $sprog_id) . "</span></a>"
            : "<a href='productLookup.php?sort=$sort&id=$id&fokus=$fokus&vis=1&lager=$lager&konto_id=$konto_id&kontonr=$kontonr'><span title='" . findtekst(1518, $sprog_id) . "'>" . findtekst(1519, $sprog_id) . "</span></a>",
        "width" => "0.5",
        "sortable" => false,
        "searchable" => false,
        "render" => function ($value, $row, $column) {
            return "<td></td>";
        }
    );
}

// Build WHERE conditions
$where_conditions = array();

// Base condition
$where_conditions[] = "v.lukket != '1'";

// Handle $find parameter
if ($find) {
    if ($fokus == 'beskrivelse') {
        $where_conditions[] = "v.beskrivelse LIKE '$find'";
    } elseif ($fokus == 'varenr') {
        $where_conditions[] = "v.varenr LIKE '$find'";
    }
}

// Handle supplier visibility
if ($konto_id && $vis) {
    $where_conditions[] = "vl.lev_id = $konto_id";
} elseif ($konto_id && !$vis) {
    $where_conditions[] = "(vl.lev_id = $konto_id OR vl.lev_id IS NULL OR vl.lev_id = 0)";
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";

// Build query with {{SORT}} placeholder and COALESCE to prevent NULL values
$query = "
SELECT 
    v.id as vare_id,
    COALESCE(v.varenr, '') as varenr,
    COALESCE(v.enhed, '') as enhed,
    COALESCE(v.beskrivelse, '') as beskrivelse,
    COALESCE(v.salgspris, 0) as salgspris,
    COALESCE(vl.kostpris, v.kostpris, 0) as kostpris,
    COALESCE(v.beholdning, 0) as beholdning,
    v.samlevare,
    COALESCE(vl.lev_id, 0) as lev_id,
    COALESCE(a.firmanavn, '') as firmanavn
FROM varer v
LEFT JOIN vare_lev vl ON v.id = vl.vare_id
LEFT JOIN adresser a ON vl.lev_id = a.id
WHERE {{WHERE}}
ORDER BY {{SORT}}";

// Define filters for grid.php
$filters = array();

// Add base filter for not closed products
$filters[] = array(
    "type" => "custom",
    "filterName" => "base_filter",
    "joinOperator" => "AND",
    "options" => array(
        array(
            "name" => "not_closed",
            "checked" => "checked",
            "sqlOn" => "v.lukket != '1'",
            "sqlOff" => ""
        )
    )
);

// Add find filter if provided
if ($find && ($fokus == 'beskrivelse' || $fokus == 'varenr')) {
    $field = ($fokus == 'beskrivelse') ? "v.beskrivelse" : "v.varenr";
    $filters[] = array(
        "type" => "custom",
        "filterName" => "find_filter",
        "joinOperator" => "AND",
        "options" => array(
            array(
                "name" => "find_$fokus",
                "checked" => "checked",
                "sqlOn" => "$field LIKE '$find'",
                "sqlOff" => ""
            )
        )
    );
}

// Add supplier visibility filter
if ($konto_id) {
    if ($vis) {
        $filters[] = array(
            "type" => "custom",
            "filterName" => "supplier_filter",
            "joinOperator" => "AND",
            "options" => array(
                array(
                    "name" => "specific_supplier",
                    "checked" => "checked",
                    "sqlOn" => "vl.lev_id = $konto_id",
                    "sqlOff" => ""
                )
            )
        );
    } else {
        $filters[] = array(
            "type" => "custom",
            "filterName" => "supplier_filter",
            "joinOperator" => "AND",
            "options" => array(
                array(
                    "name" => "supplier_or_unassigned",
                    "checked" => "checked",
                    "sqlOn" => "(vl.lev_id = $konto_id OR vl.lev_id IS NULL OR vl.lev_id = 0)",
                    "sqlOff" => ""
                )
            )
        );
    }
}

// Create data grid configuration
$data = array(
    "table_name" => "varer",
    "query" => $query,
    "columns" => $columns,
    "sort_column" => $sort ?: "varenr",
    "sort_direction" => "ASC",
    "rows_per_page" => 50,
    "show_search" => true,
    "show_pagination" => true,
    "filters" => $filters
);


if ($kontonr && $x > 1) {
    echo "<td colspan='9'><hr></td>";
}

// Render grid 
print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
create_datagrid("KPductLookup_$valg", $data);
echo "</div>";

?>

<script>setTimeout(()=>{const f=document.querySelector('form'),i='<?php echo $id; ?>';if(f&&i&&!f.querySelector('input[name="id"]')){const e=document.createElement('input');e.type='hidden',e.name='id',e.value=i,f.appendChild(e)}},100);setTimeout(()=>{const k=document.querySelector('input[name*="kostpris"]'),s=document.querySelector('input[name*="salgspris"]');if(k?.value||s?.value){const n=v=>Number(v.replace(",","."));document.querySelectorAll('td[align="right"]').forEach(c=>{const r=c.textContent.trim(),v=n(r),kv=k?.value?n(k.value):null,sv=s?.value?n(s.value):null;if((kv!==null&&Math.abs(v-kv)<0.01)||(sv!==null&&Math.abs(v-sv)<0.01))c.innerHTML=`<mark style="background:#FF0">${r}</mark>`})}},250)</script>


