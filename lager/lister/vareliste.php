<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/main.php --- lap 4.1.0 --- 2024.02.09 ---
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
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 17042024 MMK - Added suport for reloading page, and keeping current URI, DELETED old system that didnt work
// 17-10-2024 PBLM - Added link to booking

@session_start();
$s_id = session_id();

$css = "../../css/standard.css?v=20";

include("../../includes/std_func.php");
include("../../includes/connect.php");
include("../../includes/online.php");
include("../../includes/stdFunc/dkDecimal.php");

$valg = "Vareliste";
include("topLineVarer.php");

include(get_relative() . "includes/grid.php");
$vatOnItemCard = get_settings_value("vatOnItemCard", "items", "on") == "on"
    ? true : false;

// Columnconfig

$columns = array();

$columns[] = array(
    "field" => "varenr",
    "headerName" => "Vare Nr.",
    "render" => function ($value, $row, $column) {
        $url = "../../lager/varekort.php?id=$row[id]&returside=lister/vareliste.php";

        $notes = htmlspecialchars($row['notes'] ?? '', ENT_QUOTES, 'UTF-8');
        return "<td title='$notes' align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'><a href='$url'>$value</a></td>";
    },
    "sqlOverride" => "v.varenr"
);
$columns[] = array(
    "field" => "beskrivelse",
    "headerName" => "Navn",
    "width" => "3",
    "render" => function ($value, $row, $column) {
        $url = "../../lager/varekort.php?id=$row[id]&returside=lister/vareliste.php";

        $notes = htmlspecialchars($row['notes'] ?? '', ENT_QUOTES, 'UTF-8');
        return "<td title='$notes' align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$value</td>";
    },
    "sqlOverride" => "v.beskrivelse"
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
    "sqlOverride" => "levs.lev",
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
$query = "SELECT kodenr FROM grupper WHERE art='LG' GROUP BY kodenr ORDER BY kodenr";
$SQLLagerFetch = "";
$SQLLagerJoin = "";
$lagere = array();

$q = db_select($query, __FILE__ . " line " . __LINE__);
while ($row = db_fetch_array($q)) {
    $SQLLagerFetch .= "COALESCE(ls$row[kodenr].beholdning, 0) AS lager$row[kodenr],\n";
    $SQLLagerJoin .= "LEFT JOIN LagerSummary ls$row[kodenr] ON v.id = ls$row[kodenr].vare_id AND ls$row[kodenr].lager = $row[kodenr]\n";
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

// Add lager_total field
$columns[] = array(
    "field" => "lager_total",
    "headerName" => "I alt",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "COALESCE(ls.lager_total, 0)",
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


// Filtersetup
$filters = array();

// Vargrupper
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
        "sqlOn" => "levs.kontonr_concat LIKE '%$row[kontonr]%'", // Use EXISTS for efficient lookups
        "sqlOff" => "",
    );
}
$filters[] = array(
    "filterName" => "Leverandøre",
    "joinOperator" => "or",
    "options" => $levs
);

// Misc
$filters[] = array(
    "filterName" => "Misc",
    "joinOperator" => "and",
    "options" => array(
        array(
            "name" => "Vis udgået",
            "checked" => "checked",
            "sqlOn" => "",
            "sqlOff" => "(v.lukket IS NULL OR v.lukket = '0')",
        )
    )
);


$data = array(
    "table_name" => "varer",

    "query" => "WITH LagerSummary AS (
    SELECT 
        vare_id,          
        lager,            
        beholdning,       
        SUM(beholdning) OVER (PARTITION BY vare_id) AS lager_total  
    FROM lagerstatus
    GROUP BY vare_id, lager, beholdning
),
levs AS (
    SELECT 
        vl.vare_id, 
        string_agg(a.kontonr::TEXT, ' ') AS kontonr_concat, -- Cast to TEXT
        string_agg(a.id || '\t' || a.kontonr::TEXT || '\t' || a.firmanavn, '\n') AS lev
    FROM 
        vare_lev vl
    LEFT JOIN 
        adresser a 
    ON 
        vl.lev_id = a.id AND a.art = 'K' -- No syntax issue here
    GROUP BY 
        vl.vare_id
)
SELECT 
    v.id AS id,                     
    v.varenr AS varenr,             
    v.lukket AS lukket,             
    v.beskrivelse AS beskrivelse,   
    v.trademark AS trademark,       
    v.stregkode AS stregkode,       
    v.enhed AS enhed,               
    v.notes as notes,
    v.notesinternal as notes_internal,
    v.samlevare AS samlevare,
    $SQLLagerFetch
    COALESCE(ls.lager_total, 0) AS lager_total,  
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
    levs.lev as leverandør                          -- Supplier information
FROM varer v
$SQLLagerJoin
LEFT JOIN (
    SELECT 
        vare_id, 
        SUM(beholdning) AS lager_total  
    FROM lagerstatus
    GROUP BY vare_id
) ls ON v.id = ls.vare_id
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
LEFT JOIN levs ON v.id = levs.vare_id  -- Join to include supplier information
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


print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
create_datagrid("varelst$vatOnItemCard", $data);
print "</div>";

$steps = array();
$steps[] = array(
    "selector" => ".navbtn-top",
    "content" => "<b>Vareliste:</b> Den liste du ser forneden.<br><br><b>Ordrevisning:</b> Se, hvilke ordrer dine varer indgår i.<br><br><b>Indkøb:</b> Opret hurtigt indkøbslister automatisk eller manuelt.<br><br><b>Serienumre:</b> Sporing og administration af serienummer-varer."
);
$steps[] = array(
    "selector" => "#create-new",
    "content" => "Klik her for at oprette en ny vare."
);
$steps[] = array(
    "selector" => ".varenr,.beskrivelse",
    "content" => "Din vareliste vises her. Klik på et varenummer for at åbne det."
);
if ($lagere) {
    $steps[] = array(
        "selector" => "." . implode(",.", $lagere),
        "content" => "Klik på et lagerbeholdningstal for at lave en intern overførsel af lagerværdier"
    );
}
$steps[] = array(
    "selector" => ".lager_total",
    "content" => "Søg på lagerbeholdning. For eksempel:<br><b>'10'</b> - Vis varer med præcis 10 på lager.<br><b>'1:10'</b> - Vis varer med lager mellem 1 og 10."
);
$steps[] = array(
    "selector" => ".dg",
    "content" => "Undersøg dækningsgraden for dine vare, for at finde eventuelle optimeringer."
);



include(get_relative() . "includes/tutorial.php");
create_tutorial("vareliste", $steps);
