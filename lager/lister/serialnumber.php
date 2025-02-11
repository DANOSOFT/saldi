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
$title = "Serienr";

include ("../../includes/std_func.php");
include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/stdFunc/dkDecimal.php");

$valg = "Serienumre";
include ("topLineVarer.php");

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST["serienr_id"]) && isset($_POST["vare_id"])) {
    if (isset($_POST['rename']) && $_POST['rename']) {
        $name = db_escape_string($_POST['rename']);
        $serienr_id = db_escape_string($_POST['serienr_id']);
        $vare_id = db_escape_string($_POST['vare_id']);
        db_modify("UPDATE serienr SET serienr = '$name' WHERE id=$serienr_id AND vare_id=$vare_id", __FILE__ . " line " . __LINE__);
    }
    if (isset($_POST['delete']) && $_POST['delete']) {
        $serienr_id = db_escape_string($_POST['serienr_id']);
        $vare_id = db_escape_string($_POST['vare_id']);
        db_modify("DELETE FROM serienr WHERE id=$serienr_id AND vare_id=$vare_id", __FILE__ . " line " . __LINE__);
    }
}

include (get_relative()."includes/grid.php");

// Columnconfig

$columns = array();

$columns[] =    array(
    "field" => "id",
    "headerName" => "Serienummer ID",
    "sqlOverride" => "sn.id",
    "type" => "number",
    "valueGetter" => function ($value, $row, $column) {
        return $value; 
    },
    "hidden" => true
);
$columns[] =    array(
    "field" => "varenr",
    "headerName" => "Vare Nr.",
    "render" => function ($value, $row, $column) {
        $url = "../../lager/varekort.php?id=$row[vare_id]&returside=../lager/lister/serialnumber.php";
        return "<td align='$column[align]'><a href='$url'>$value</a></td>";
    },
    "sqlOverride" => "v.varenr",
    "width" => "0.5",
);
$columns[] =    array(
    "field" => "beskrivelse",
    "headerName" => "Vare navn",
    "width" => "3",
    "sqlOverride" => "v.beskrivelse"
);
$columns[] =    array(
    "field" => "stregkode",
    "headerName" => "Stregkode",
    "sqlOverride" => "v.stregkode"
);
$columns[] =    array(
    "field" => "serienr",
    "headerName" => "Serienummer",
    "sqlOverride" => "sn.serienr"
);
/*$columns[] =    array(
    "field" => "kobs_kontonr1",
    "headerName" => "Købs kontor",
    "sqlOverride" => "ko.kontonr",
    "render" => function ($value, $row, $column) {
        $url = "../../kreditor/kreditorkort.php?id=$row[kobs_konto]&returside=../includes/datagrid/serialnumber.php";
        return "<td align='$column[align]'><a href='$url'>$value</a></td>";
    },
    "align" => "right",
    "width" => 0.5,
    "hidden" => true,
);
$columns[] =    array(
    "field" => "kobs_firmanavn",
    "headerName" => "Købs firmanavn",
    "sqlOverride" => "ko.firmanavn",
    "hidden" => true,
);*/
/*$columns[] = array(
    "field" => "salgs_kontonr1",
    "headerName" => "Salgs kontor",
    "sqlOverride" => "so.kontonr",
    "render" => function ($value, $row, $column) {
        $url = "../../debitor/debitorkort.php?id=$row[salgs_konto]&returside=../includes/datagrid/serialnumber.php";
        return "<td align='$column[align]'><a href='$url'>$value</a></td>";
    },
    "align" => "right",
    "width" => 0.5,
    "hidden" => true,
);
$columns[] = array(
    "field" => "salgs_firmanavn",
    "headerName" => "Salgs firmanavn",
    "sqlOverride" => "so.firmanavn",
    "hidden" => true,
);*/
$columns[] = array(
    "field" => "kobs_kontonr",
    "headerName" => "Leverandør",
    "width" => "1.5",
    "sqlOverride" => "LOWER(ko.kontonr)",
    "generateSearch" => function ($column, $term) {
        return "(LOWER(ko.kontonr) LIKE LOWER('%$term%') OR LOWER(ko.firmanavn) LIKE LOWER('%$term%'))";
    },
    "render" => function ($value, $row, $column) {
        $html = "<td align='$column[align]'>";
        if ($value) {
            $url = "../../kreditor/kreditorkort.php?id=$row[kobs_konto]&returside=../lager/lister/serialnumber.php";
            $html .= "<span><a href='$url'>$row[kobs_kontonr]</a> : $row[kobs_firmanavn]</span><br>";
        }
        $html .= "</td>";
        return $html;
    },
);
$columns[] = array(
    "field" => "kobs_ordre",
    "headerName" => "Ordre",
    "sqlOverride" => "ko.ordrenr",
    "render" => function ($value, $row, $column) {
        $url = "../../kreditor/ordre.php?id=$row[kobs_ordre]&returside=../lager/lister/serialnumber.php";
        return "<td align='$column[align]'><a href='$url'>$row[kobs_ordre_nr]</a></td>";
    },
    "valueGetter" => function ($value, $row, $column) {
        return $value;  // Disable the default dkdecimal for numbers
    },
    "type" => "number",
    "width" => 0.5,
);

$columns[] = array(
    "field" => "salgs_kontonr",
    "headerName" => "Køber",
    "width" => "1.5",
    "sqlOverride" => "LOWER(so.kontonr)",
    "generateSearch" => function ($column, $term) {
        return "(LOWER(so.kontonr) LIKE LOWER('%$term%') OR LOWER(so.firmanavn) LIKE LOWER('%$term%'))";
    },
    "render" => function ($value, $row, $column) {
        $html = "<td align='$column[align]'>";
        if ($value) {
            $url = "../../kreditor/kreditorkort.php?id=$row[salgs_konto]&returside=../lager/lister/serialnumber.php";
            $html .= "<span><a href='$url'>$row[salgs_kontonr]</a> : $row[salgs_firmanavn]</span><br>";
        }
        $html .= "</td>";
        return $html;
    },
);
$columns[] = array(
    "field" => "salgs_ordre_nr",
    "headerName" => "Ordre",
    "render" => function ($value, $row, $column) {
        $url = "../../debitor/ordre.php?id=$row[salgs_ordre]&returside=../lager/lister/serialnumber.php";
        return "<td align='$column[align]'><a href='$url'>$row[salgs_ordre_nr]</a></td>";
    },
    "valueGetter" => function ($value, $row, $column) {
        return $value;  // Disable the default dkdecimal for numbers
    },
    "type" => "number",
    "sqlOverride" => "so.ordrenr",
    "width" => 0.5,
);


$filters = array();

// Vargrupper
$query = "SELECT * FROM grupper WHERE art='VG' AND fiscal_year=$regnaar ORDER BY beskrivelse";
$q = db_select($query, __FILE__ . " line " . __LINE__);
$VGs = array();
while ($row = db_fetch_array($q)) {
    $VGs[] = array(
        "name" => $row["beskrivelse"],
        "checked" => "",
        "sqlOn" => "v.gruppe = $row[kodenr]",
        "sqlOff" => "",
    );
}
$filters[] = array(
    "filterName" => "Varegrupper",
    "joinOperator" => "or",
    "options" => $VGs
);

$filters[] = array(
    "filterName" => "Misc",
    "joinOperator" => "and",
    "options" => array(
        array(
            "name" => "Vis tomme serienr værdier",
            "checked" => "",
            "sqlOn" => "",
            "sqlOff" => "sn.serienr != '' AND sn.serienr IS NOT NULL",
        ),
        array(
            "name" => "Vis kun serienumre der ikke er solgt",
            "checked" => "",
            "sqlOn" => "so.ordrenr IS NULL",
            "sqlOff" => "",
        )
    )
);

$data = array(
    "table_name" => "serienr",

    "query" => "SELECT 
    sn.id AS id, 
    sn.vare_id AS vare_id, 
    v.varenr AS varenr,
    v.beskrivelse AS beskrivelse,
    v.stregkode AS stregkode,
    sn.serienr AS serienr, 

    kl.ordre_id AS kobs_ordre, 
    ko.ordrenr AS kobs_ordre_nr,
    ko.konto_id AS kobs_konto,
    ko.kontonr AS kobs_kontonr,
    ko.firmanavn AS kobs_firmanavn,

    sl.ordre_id AS salgs_ordre,
    so.ordrenr AS salgs_ordre_nr,
    so.konto_id AS salgs_konto,
    so.kontonr AS salgs_kontonr,
    so.firmanavn AS salgs_firmanavn
FROM serienr sn
LEFT JOIN varer v ON sn.vare_id = v.id

LEFT JOIN ordrelinjer kl ON sn.kobslinje_id = kl.id
LEFT JOIN ordrer ko ON kl.ordre_id = ko.id

LEFT JOIN ordrelinjer sl ON sn.salgslinje_id = sl.id
LEFT JOIN ordrer so ON sl.ordre_id = so.id

WHERE 
    {{WHERE}} 
ORDER BY 
    {{SORT}}
",

    'rowStyle' => function ($row) {
        if ($row['salgs_ordre'] == "") {
            return "color: #555;";
        } else {
            return ""; 
        }
    },
    // Only show metaColumn if settings rettighedder
    'metaColumn' => substr($rettigheder,1,1) ? function ($row) {
        if ($row['salgs_ordre'] == "") {

            return <<<HTML
            <td class='filler-row'> <!-- Automatically gets removed on export -->
                <div style='display: flex;'>
                    <svg
                        onclick="
                            const name = prompt('Hvad skal serienummeret omnavngives til?\\nSerienummer: {$row['serienr']}');

                            document.getElementsByName('serienr_id')[0].value='{$row['id']}';
                            document.getElementsByName('vare_id')[0].value='{$row['vare_id']}';
                            document.getElementsByName('rename')[0].value=name;

                            if (confirm('Omdøb {$row['serienr']} til '+name+'?')) {
                                document.getElementsByName('rename')[0].form.submit();
                            } else {
                                document.getElementsByName('rename')[0].value='';
                            }

                            "
                        style="cursor: pointer"
                        xmlns="http://www.w3.org/2000/svg" height="22px" viewBox="0 -960 960 960" width="22px" fill="#000000"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/>
                    </svg>
                    <svg 
                        onclick="
                            document.getElementsByName('serienr_id')[0].value='{$row['id']}';
                            document.getElementsByName('vare_id')[0].value='{$row['vare_id']}';
                            document.getElementsByName('delete')[0].value=1;

                            if (confirm('Slet {$row['serienr']}?')) {
                                document.getElementsByName('rename')[0].form.submit();
                            } else {
                                document.getElementsByName('delete')[0].value='';
                            }

                            "
                        style="cursor: pointer"
                        xmlns="http://www.w3.org/2000/svg" height="22px" viewBox="0 -960 960 960" width="22px" fill="#000000"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/>
                    </svg>
                </div>
            </td>
HTML;
        } else {
            return "<td></td>"; 
        }
    } : NULL,

    "columns" => $columns,
    "filters" => $filters,
);


print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
create_datagrid("serienr", $data);
print "</div>";

print <<<HTML
    <form method='post' action=''>
        <input name="serienr_id" type="hidden">
        <input name="vare_id" type="hidden">
        <input name="rename" type="hidden">
        <input name="delete" type="hidden">
</form>
HTML;
?>

<?php
$steps = array();
$steps[] = array(
    "selector" => "#back-btn",
    "content" => "Klik her for at vende tilbage til varelisten."
);
$steps[] = array(
    "selector" => ".serienr",
    "content" => "Søg efter et eksisterende serienummer i dit system."
);
$steps[] = array(
    "selector" => ".lager_total",
    "content" => "Viser, hvor mange enheder af varen der er på lager."
);
$steps[] = array(
    "selector" => ".kobs_kontonr,.kobs_ordre",
    "content" => "Her kan du se, hvor varen blev købt, hvilken leverandør det var, og ordrenummeret."
);
$steps[] = array(
    "selector" => ".salgs_kontonr,.salgs_ordre_nr",
    "content" => "Her kan du finde oplysninger om, hvor varen blev solgt, hvem køberen var, og ordrenummeret."
);
$steps[] = array(
    "selector" => ".salgs_kontonr,.salgs_ordre_nr,#tmp",
    "content" => "Hvis serienummeret ikke er solgt, kan du slette eller redigere det."
);


include (get_relative()."includes/tutorial.php");
create_tutorial("vareliste", $steps);

?>