<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- lager/lister/styklister.php --- lap 5.0.0 --- 2026.02.20 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260220 LOE  - Created: Vareliste view filtered to samlevare (stykliste) items.

@session_start();
$s_id = session_id();

$css = "../../css/standard.css?v=20";

include("../../includes/std_func.php");
include("../../includes/connect.php");
include("../../includes/online.php");
include("../../includes/stdFunc/dkDecimal.php");

$valg = "Styklister";
$returside = if_isset($_GET, get_relative()."index/menu.php", "returside");
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
        $url = "../../lager/varekort.php?id=$row[id]&returside=lister/styklister.php";
        $notes = htmlspecialchars($row['notes'] ? $row["notes"] : '', ENT_QUOTES, 'UTF-8');
        return "<td title='$notes' align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'><a href='$url'>$value</a></td>";
    },
    "sqlOverride" => "v.varenr",
    "generateSearch" => function ($column, $term) {
        $term = db_escape_string($term);
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
        $url = "../../lager/varekort.php?id=$row[id]&returside=lister/styklister.php";
        $notes = htmlspecialchars($row['notes'] ? $row["notes"] : '', ENT_QUOTES, 'UTF-8');
        return "<td title='$notes' align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'><a href='$url'>$value</a></td>";
    },
    "sqlOverride" => "v.varenr_alias",
    "generateSearch" => function ($column, $term) {
        $term = db_escape_string($term);
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
        $url = "../../lager/varekort.php?id=$row[id]&returside=lister/styklister.php";
        $notes = htmlspecialchars($row['notes'] ? $row["notes"] : '', ENT_QUOTES, 'UTF-8');
        return "<td title='$notes' align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$value</td>";
    },
    "sqlOverride" => "v.beskrivelse",
    "generateSearch" => function ($column, $term) {
        $term = db_escape_string($term);
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
        $url = "../../lager/varekort.php?id=$row[id]&returside=lister/styklister.php";
        $notes = htmlspecialchars($row['notes'] ? $row["notes"] : '', ENT_QUOTES, 'UTF-8');
        return "<td title='$notes' align='$column[align]' onclick=\"window.location.href='$url'\" style='cursor:pointer'>$value</td>";
    },
    "sqlOverride" => "v.beskrivelse_alias",
    "generateSearch" => function ($column, $term) {
        $term = db_escape_string($term);
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
    "sqlOverride" => "v.trademark"
);
$columns[] = array(
    "field" => "varegruppe",
    "headerName" => "Varegruppe",
    "sqlOverride" => "vg.beskrivelse",
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
    "sqlOverride" => "ol.lev",
    "render" => function ($value, $row, $column) {
        $html = "<td align='$column[align]'>";
        if ($value) {
            foreach (explode("\n", $value) as $lev) {
                list($id, $kontonr, $name) = explode("\t", $lev);
                $url = "../../kreditor/kreditorkort.php?id=$id&returside=../lager/lister/styklister.php";
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

// Varegrupper
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
        "sqlOn" => "ol.kontonr_concat = '$row[kontonr]'",
        "sqlOff" => "",
    );
}
$filters[] = array(
    "filterName" => "Leverandøre",
    "joinOperator" => "or",
    "options" => $levs
);

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

$data = array(
    "table_name" => "varer",
    "query" => "WITH optimized_levs AS (
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
    v.notes AS notes,
    v.notesinternal AS notes_internal,
    v.salgspris AS salgspris,
    v.kostpris AS kostpris,
    CASE
        WHEN v.salgspris = 0 THEN 0
        ELSE (v.salgspris - v.kostpris) / v.salgspris * 100
    END AS dg,
    vg.beskrivelse AS varegruppe,
    CASE
        WHEN vg.box7 = 'on' THEN v.salgspris
        ELSE (100 + sm.box2::float) / 100 * v.salgspris
    END AS momspris,
    ol.lev AS leverandør
FROM varer v
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
LEFT JOIN optimized_levs ol ON v.id = ol.vare_id
WHERE v.samlevare = 'on' AND {{WHERE}}
ORDER BY {{SORT}}
",

    'rowStyle' => function ($row) {
        switch ($row['lukket']) {
            case '1':
                return "color: #f00;";
            default:
                return "";
        }
    },
    "columns" => $columns,
    "filters" => $filters,
);

print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
create_datagrid("styklister", $data);
print "</div>";
?>
