<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/main.php --- lap 4.1.1 --- 2025.05.26 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2024-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 17042024 MMK  - Added suport for reloading page, and keeping current URI, DELETED old system that didnt work
// 17102024 PBLM - Added link to booking
// 26052025 LOE  - Sets v.lukket to '' instead of v.lukket.
// 17062025 PBLM - Fixed bug where you could not search for leverandør in vareliste.

@session_start();
$s_id = session_id();

$css = "../../css/standard.css?v=20";

$include_start = microtime(true);
include("../../includes/std_func.php");
include("../../includes/connect.php");
include("../../includes/online.php");
include("../../includes/stdFunc/dkDecimal.php");

$valg = "Vareliste";
include("topLineVarer.php");
// Performance logging
$start_time = microtime(true);
$log_file = "../../temp/$db/vareliste_performance.log";

function log_performance($message, $start_time = null) {
    global $log_file;
    $current_time = microtime(true);
    if ($start_time) {
        $elapsed = round(($current_time - $start_time) * 1000, 2);
        $message .= " (took {$elapsed}ms)";
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    return $current_time;
}

log_performance("Page load started");

$grid_start = microtime(true);
include(get_relative() . "includes/grid.php");
log_performance("Grid include loaded", $grid_start);

$vatOnItemCard = get_settings_value("vatOnItemCard", "items", "on") == "on"
    ? true : false;

// Columnconfig

$columns_start = microtime(true);
$columns = array();

$columns[] = array(
    "field" => "varenr",
    "headerName" => "Vare Nr.",
    "render" => function ($value, $row, $column) {
        $url = "../../lager/varekort.php?id=$row[id]&returside=lister/vareliste.php";

        $notes = htmlspecialchars($row['notes'] ? $row["notes"] : '', ENT_QUOTES, 'UTF-8');
        return "<td title='$notes' align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'><a href='$url'>$value</a></td>";
    },
    "sqlOverride" => "v.varenr",
    "generateSearch" => function ($column, $term) {
        $term = db_escape_string($term);
        // Split search term into words and match all words
        $words = preg_split('/\s+/', trim($term));
        $conditions = array();
        foreach ($words as $word) {
            if (!empty($word)) {
                $word = db_escape_string($word);
                $conditions[] = "(v.varenr ILIKE '%$word%' OR v.varenr_alias ILIKE '%$word%')";
            }
        }
        return !empty($conditions) ? "(" . implode(" AND ", $conditions) . ")" : "1=1";
    },
);

$columns[] = array(
    "field" => "varenr_alias",
    "headerName" => "Vare Nr. (alias)",
    "render" => function ($value, $row, $column) {
        $url = "../../lager/varekort.php?id=$row[id]&returside=lister/vareliste.php";

        $notes = htmlspecialchars($row['notes'] ? $row["notes"] : '', ENT_QUOTES, 'UTF-8');
        return "<td title='$notes' align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'><a href='$url'>$value</a></td>";
    },
    "sqlOverride" => "v.varenr",
    "generateSearch" => function ($column, $term) {
        $term = db_escape_string($term);
        // Split search term into words and match all words
        $words = preg_split('/\s+/', trim($term));
        $conditions = array();
        foreach ($words as $word) {
            if (!empty($word)) {
                $word = db_escape_string($word);
                $conditions[] = "(v.varenr ILIKE '%$word%' OR v.varenr_alias ILIKE '%$word%')";
            }
        }
        return !empty($conditions) ? "(" . implode(" AND ", $conditions) . ")" : "1=1";
    },
    "hidden" => true,
);

$columns[] = array(
    "field" => "beskrivelse",
    "headerName" => "Navn",
    "width" => "3",
    "render" => function ($value, $row, $column) {
        $url = "../../lager/varekort.php?id=$row[id]&returside=lister/vareliste.php";

        $notes = htmlspecialchars($row['notes'] ? $row["notes"] : '', ENT_QUOTES, 'UTF-8');
        return "<td title='$notes' align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$value</td>";
    },
    "sqlOverride" => "v.beskrivelse",
    "generateSearch" => function ($column, $term) {
        $term = db_escape_string($term);
        // Split search term into words and match all words
        $words = preg_split('/\s+/', trim($term));
        $conditions = array();
        foreach ($words as $word) {
            if (!empty($word)) {
                $word = db_escape_string($word);
                $conditions[] = "(v.beskrivelse ILIKE '%$word%' OR v.beskrivelse_alias ILIKE '%$word%')";
            }
        }
        return !empty($conditions) ? "(" . implode(" AND ", $conditions) . ")" : "1=1";
    },
);

$columns[] = array(
    "field" => "beskrivelse_alias",
    "headerName" => "Navn (alias)",
    "width" => "3",
    "render" => function ($value, $row, $column) {
        $url = "../../lager/varekort.php?id=$row[id]&returside=lister/vareliste.php";

        $notes = htmlspecialchars($row['notes'] ? $row["notes"] : '', ENT_QUOTES, 'UTF-8');
        return "<td title='$notes' align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$value</td>";
    },
    "sqlOverride" => "v.beskrivelse",
    "generateSearch" => function ($column, $term) {
        $term = db_escape_string($term);
        // Split search term into words and match all words
        $words = preg_split('/\s+/', trim($term));
        $conditions = array();
        foreach ($words as $word) {
            if (!empty($word)) {
                $word = db_escape_string($word);
                $conditions[] = "(v.beskrivelse ILIKE '%$word%' OR v.beskrivelse_alias ILIKE '%$word%')";
            }
        }
        return !empty($conditions) ? "(" . implode(" AND ", $conditions) . ")" : "1=1";
    },
    "hidden" => true,
);

$columns[] = array(
    "field" => "trademark",
    "headerName" => "Varemærke",
    "hidden" => false,
    "sqlOverride" => "v.trademark"
);
$columns[] = array(
    "field" => "varegruppe",
    "headerName" => "Varegruppe",
    "sqlOverride" => "vg.beskrivelse",
    "hidden" => false,
);
$columns[] = array(
    "field" => "momssats",
    "headerName" => "Momssats",
    "width" => "0.5",
    "sqlOverride" => "sm.box2",
    "hidden" => true,
);
$columns[] = array(
    "field" => "stregkode",
    "headerName" => "Stregkode",
    "sqlOverride" => "v.stregkode"
);
$columns[] = array(
    "field" => "notes",
    "headerName" => "Note",
    "sqlOverride" => "v.notes",
    "hidden" => true,
);
$columns[] = array(
    "field" => "notes_internal",
    "headerName" => "Intern note",
    "sqlOverride" => "v.notes_internal",
    "hidden" => true,
);
$columns[] = array(
    "field" => "leverandør",
    "headerName" => "Leverandør",
    "width" => "1.5",
    "sqlOverride" => "ol.lev", // Fixed: changed from "levs.lev" to "ol.lev"
    "render" => function ($value, $row, $column) {
        $html = "<td align='$column[align]'>";
        if ($value) {
            foreach (explode("\n", $value) as $lev) {
                list($id, $kontonr, $name) = explode("\t", $lev);
                $url = "../../kreditor/kreditorkort.php?id=$id&returside=../lager/lister/vareliste.php";
                $html .= "<span><a href='$url'>$kontonr</a> : $name</span><br>";
            }
        }
        $html .= "</td>";
        return $html;
    },
);
$columns[] = array(
    "field" => "enhed",
    "headerName" => "Enhed",
    "width" => "0.5",
    "sqlOverride" => "v.enhed"
);

// Loop to generate lager fields (lager1, lager2, lager3, ...)
$lager_query_start = microtime(true);
$query = "SELECT kodenr, beskrivelse FROM grupper WHERE art='LG' GROUP BY kodenr, beskrivelse ORDER BY kodenr";
$SQLLagerFetch = "";
$SQLLagerJoin = "";
$lagere = array();

$q = db_select($query, __FILE__ . " line " . __LINE__);
while ($row = db_fetch_array($q)) {
    $SQLLagerFetch .= "COALESCE(ls$row[kodenr].beholdning, 0) AS lager$row[kodenr],\n";
    $SQLLagerJoin .= "LEFT JOIN lagerstatus ls$row[kodenr] ON v.id = ls$row[kodenr].vare_id AND ls$row[kodenr].lager = $row[kodenr]\n";
    $lagere[] = "lager" . $row['kodenr'];

    $columns[] = array(
        "field" => "lager" . $row['kodenr'],
        "headerName" => (string) $row['beskrivelse'],
        "type" => "number",
        "align" => "right",
        "lagerId" => $row['kodenr'],
        "width" => "0.2",
        "sqlOverride" => "COALESCE(ls$row[kodenr].beholdning, 0)",
        "render" => function ($value, $row, $column) {
            if ($row["samlevare"] == "on") {
                return "<td></td>";
            }
            if (!$value) {
                return "<td align='$column[align]'>0,00</td>";
            }
            if ($value != "0,00") {
                $url = "../lagerflyt.php?lager=$column[lagerId]&vare_id=$row[id]&returside=../lager/lister/vareliste.php";
                return "<td align='$column[align]'><a href='$url'>$value</a></td>";
            } else {
                return "<td align='$column[align]'>$value</td>";
            }
        }
    );
}
log_performance("Lager fields query and setup", $lager_query_start);

// Add lager_total field
$columns[] = array(
    "field" => "lager_total",
    "headerName" => "I alt",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "COALESCE(lt.lager_total, 0)",
    "render" => function ($value, $row, $column) {
        if ($row["samlevare"] == "on") {
            return "<td></td>";
        }
        if (!$value) {
            return "<td align='$column[align]'>0,00</td>";
        }
        return "<td align='$column[align]'>$value</td>";
    }
);

// Continue adding other fields if needed
$columns[] = array(
    "field" => "salgspris",
    "headerName" => "Salgspris",
    "description" => "(excl.moms)",
    "type" => "number",
    "align" => "right",
    "width" => "0.5",
    "hidden" => (!$vatOnItemCard),
    "sqlOverride" => "v.salgspris"
);
$columns[] = array(
    "field" => "momspris",
    "headerName" => "Salgspris",
    "description" => "(incl.moms)",
    "type" => "number",
    "align" => "right",
    "width" => "0.5",
    "hidden" => $vatOnItemCard,
    "sqlOverride" => "CASE 
        WHEN vg.box7 = 'on' THEN v.salgspris
        ELSE (100+sm.box2::float)/100*v.salgspris
    END"
);

$columns[] = array(
    "field" => "kostpris",
    "headerName" => "Kostpris",
    "type" => "number",
    "align" => "right",
    "width" => "0.5",
    "sqlOverride" => "v.kostpris"
);

$columns[] = array(
    "field" => "dg",
    "headerName" => "DG",
    "type" => "number",
    "align" => "right",
    "sqlOverride" => "
    ROUND(CASE 
               WHEN v.salgspris = 0 THEN 0 
               ELSE (v.salgspris - v.kostpris) / v.salgspris * 100 
           END, 2)",
    "width" => "0.5",
    "valueGetter" => function ($value, $row, $column) {
        return dkdecimal($value, 1) . "%";
    },
    "decimalPrecision" => 1,
);

log_performance("Column configuration completed", $columns_start);

// Filtersetup
$filters_start = microtime(true);
$filters = array();

// Vargrupper
$varegrupper_start = microtime(true);
$query = "SELECT * FROM grupper WHERE art='VG' AND fiscal_year=$regnaar ORDER BY beskrivelse";
$q = db_select($query, __FILE__ . " line " . __LINE__);
$VGs = array();
while ($row = db_fetch_array($q)) {
    $VGs[] = array(
        "name" => $row["beskrivelse"],
        "checked" => "",
        "sqlOn" => "vg.kodenr = $row[kodenr]",
        "sqlOff" => "",
    );
}
$filters[] = array(
    "filterName" => "Varegrupper",
    "joinOperator" => "or",
    "options" => $VGs
);

log_performance("Varegrupper filter query", $varegrupper_start);

$leverandor_start = microtime(true);
$query = "SELECT kontonr, firmanavn
FROM 
    adresser a
INNER JOIN vare_lev vl ON vl.lev_id = a.id
WHERE 
    art='K'
GROUP BY a.kontonr, a.firmanavn
";
$q = db_select($query, __FILE__ . " line " . __LINE__);
$levs = array();
while ($row = db_fetch_array($q)) {
    $levs[] = array(
        "name" => $row["firmanavn"],
        "checked" => "",
        "sqlOn" => "ol.kontonr_concat = '$row[kontonr]'", // Fixed: changed from levs.lev to ol.kontonr_concat
        "sqlOff" => "",
    );
}
$filters[] = array(
    "filterName" => "Leverandøre",
    "joinOperator" => "or",
    "options" => $levs
);

log_performance("Leverandøre filter query", $leverandor_start);

// Misc
$filters[] = array(
    "filterName" => "Misc",
    "joinOperator" => "and",
    "options" => array(
        array(
            "name" => "Vis udgået",
            "checked" => "checked",
            "sqlOn" => "",
            "sqlOff" => "(v.lukket IS NULL OR v.lukket = '0' or v.lukket = '')",
        )
    )
);

log_performance("All filters setup completed", $filters_start);

$data_start = microtime(true);
$data = array(
    "table_name" => "varer",
    "query" => "WITH optimized_levs AS (
    -- Simplified supplier aggregation - only when needed
    SELECT 
        vl.vare_id, 
        string_agg(a.kontonr::TEXT, ' ') AS kontonr_concat,
        string_agg(a.id || '\t' || a.kontonr::TEXT || '\t' || a.firmanavn, '\n') AS lev
    FROM 
        vare_lev vl
    LEFT JOIN 
        adresser a ON vl.lev_id = a.id AND a.art = 'K'
    GROUP BY 
        vl.vare_id
),
lager_totals AS (
    -- Single calculation of lager totals
    SELECT 
        vare_id,
        SUM(beholdning) AS lager_total
    FROM lagerstatus
    GROUP BY vare_id
)
SELECT DISTINCT
    v.id AS id,                     
    v.varenr AS varenr,
    v.varenr_alias AS varenr_alias,
    v.lukket AS lukket,             
    v.beskrivelse AS beskrivelse,
    v.beskrivelse_alias AS beskrivelse_alias,
    v.trademark AS trademark,       
    v.stregkode AS stregkode,       
    v.enhed AS enhed,               
    v.notes as notes,
    v.notesinternal as notes_internal,
    v.samlevare AS samlevare,
    $SQLLagerFetch
    COALESCE(lt.lager_total, 0) AS lager_total,  
    v.salgspris AS salgspris,       
    v.kostpris AS kostpris,         
    CASE 
        WHEN v.salgspris = 0 THEN 0  
        ELSE (v.salgspris - v.kostpris) / v.salgspris * 100  
    END AS dg,                      
    vg.beskrivelse AS varegruppe,    
    CASE 
        WHEN vg.box7 = 'on' THEN NULL   
        ELSE kp.moms                    
    END AS momsgruppe,                
    CASE 
        WHEN vg.box7 = 'on' THEN NULL   
        ELSE sm.box2                    
    END AS momssats,                  
    CASE 
        WHEN vg.box7 = 'on' THEN v.salgspris  
        ELSE (100 + sm.box2::float) / 100 * v.salgspris  
    END AS momspris,                  
    ol.lev as leverandør                          
FROM varer v
$SQLLagerJoin
LEFT JOIN lager_totals lt ON v.id = lt.vare_id  -- Use optimized CTE
LEFT JOIN grupper vg ON vg.kodenr = v.gruppe AND vg.fiscal_year = $regnaar AND vg.art = 'VG'
LEFT JOIN kontoplan kp ON kp.kontonr::text = vg.box4 AND regnskabsaar = $regnaar AND vg.box7 != 'on'
LEFT JOIN grupper sm 
    ON sm.kodenr::text = 
        CASE 
            WHEN LENGTH(kp.moms) > 1 THEN SUBSTRING(kp.moms FROM 2 FOR LENGTH(kp.moms) - 1)  
            ELSE NULL  
        END
    AND sm.fiscal_year = $regnaar 
    AND sm.art = 'SM'
LEFT JOIN optimized_levs ol ON v.id = ol.vare_id  -- Use optimized CTE
WHERE {{WHERE}}  
ORDER BY {{SORT}}
",

    'rowStyle' => function ($row) {
        switch ($row['lukket']) {
            case '1':
                return "color: #f00;"; // Red
            default:
                return ""; // Yellow
        }
    },
    "columns" => $columns,
    "filters" => $filters,
);

log_performance("Data array configuration completed", $data_start);

$grid_render_start = microtime(true);
print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
create_datagrid("varelst$vatOnItemCard", $data);
print "</div>";
log_performance("Grid rendering completed", $grid_render_start);

$steps = array();
$steps[] = array(
    "selector" => ".navbtn-top",
    "content" => findtekst('2639|Vareliste: Den liste du ser forneden. Ordrevisning: Se, hvilke ordrer dine varer indgår i. Indkøb: Opret hurtigt indkøbslister automatisk eller manuelt. Serienumre: Sporing og administration af serienummer-varer', $sprog_id)
);
$steps[] = array(
    "selector" => "#create-new",
    "content" => findtekst('2640|Klik her for at oprette en ny vare', $sprog_id)."."
);
$steps[] = array(
    "selector" => ".varenr,.beskrivelse",
    "content" => findtekst('2641|Din vareliste vises her. Klik på et varenummer for at åbne det.', $sprog_id)
);
if ($lagere) {
    $steps[] = array(
        "selector" => "." . implode(",.", $lagere),
        "content" => findtekst('2642|Klik på et lagerbeholdningstal for at lave en intern overførsel af lagerenheder', $sprog_id)."."
    );
}
$steps[] = array(
    "selector" => ".lager_total",
    "content" => findtekst('2643|Søg på lagerbeholdning. For eksempel:<br><b>"10"</b> – Viser varer med lagerbeholdning på præcist 10.<br><b>"1:10"</b> – Viser varer med lagerbeholdning mellem 1 og 10.', $sprog_id)
);
$steps[] = array(
    "selector" => ".dg",
    "content" => findtekst('2644|Undersøg dækningsgraden for dine vare, for at finde eventuelle optimeringer', $sprog_id)."."
);



include(get_relative() . "includes/tutorial.php");
create_tutorial("vareliste", $steps);

// Final performance log
$total_time = microtime(true) - $start_time;
log_performance("Total page load completed in " . round($total_time * 1000, 2) . "ms");

// Output performance summary to browser console for debugging
echo "<script>console.log('Vareliste performance log written to /tmp/vareliste_performance.log');</script>";
?>
