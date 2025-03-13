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

include ("../../includes/std_func.php");
include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/stdFunc/dkDecimal.php");

$valg = "Indkøb";
include ("topLineVarer.php");

include (get_relative()."includes/grid.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    ################################################################
    #
    # Genbestil Opret bestillingsordre
    #
    ################################################################
    if (isset($_POST['genbestil_data'])) {
        $genbestil_data = json_decode($_POST['genbestil_data'], true);

        foreach ($genbestil_data as $item) {
            $vare_id = $item['id'];
            $amount = usdecimal($item['value']);

            genbestil($vare_id, $amount);
        }
    }
}

################################################################
#
# Genbestil setup
#
################################################################

?>
<form id="bestilForm" method="POST">
    <!-- Add a hidden input to hold the collected data -->
    <input type="hidden" name="genbestil_data" id="genbestilData">
    <div style="width:100%; display: flex; justify-content: flex-end">
        <button type="button" id="autoudfyldBtn" style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="m422-232 207-248H469l29-227-185 267h139l-30 208ZM320-80l40-280H160l360-520h80l-40 320h240L400-80h-80Zm151-390Z"/></svg>
            Autoudfyld
        </button>
        &nbsp;
        <button type="button" id="bestilBtn" style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M280-80q-33 0-56.5-23.5T200-160q0-33 23.5-56.5T280-240q33 0 56.5 23.5T360-160q0 33-23.5 56.5T280-80Zm400 0q-33 0-56.5-23.5T600-160q0-33 23.5-56.5T680-240q33 0 56.5 23.5T760-160q0 33-23.5 56.5T680-80ZM246-720l96 200h280l110-200H246Zm-38-80h590q23 0 35 20.5t1 41.5L692-482q-11 20-29.5 31T622-440H324l-44 80h480v80H280q-45 0-68-39.5t-2-78.5l54-98-144-304H40v-80h130l38 80Zm134 280h280-280Z"/></svg>
            Bestil
        </button>
    </div>
</form>

<script>
    document.getElementById('autoudfyldBtn').addEventListener('click', function () {
        // Get all inputs with class 'genbestil-inp'
        const inputs = document.querySelectorAll('.genbestil-inp');

        inputs.forEach(input => {
            console.log(input);
            const genb = input.getAttribute('genbestil');
            if (genb != 0) {
                input.value=genb;
            }
        });
    });
    document.getElementById('bestilBtn').addEventListener('click', function () {
        // Get all inputs with class 'genbestil-inp'
        const inputs = document.querySelectorAll('.genbestil-inp');
        const data = [];

        inputs.forEach(input => {
            const value = input.value.trim(); // Trim to remove any extra spaces
            if (value) {
                const id = input.id.replace('genb-', ''); // Extract the $row[id]
                data.push({ id, value });
            }
        });

        if (data.length > 0) {
            // Convert the collected data to a JSON string
            const jsonData = JSON.stringify(data);
            // Set the hidden input value
            document.getElementById('genbestilData').value = jsonData;
            // Submit the form
            document.getElementById('bestilForm').submit();
        } else {
            alert('No values to submit!');
        }
    });
</script>


<?php

################################################################
#
# Datasetup
#
################################################################

// Columnconfig

$columns = array();

$columns[] =    array(
    "field" => "varenr",
    "headerName" => "Vare Nr.",
    "render" => function ($value, $row, $column) {
        $url = "../../lager/varekort.php?id=$row[id]&returside=../lager/lister/indkøb.php";
        return "<td align='$column[align]'><a href='$url'>$value</a></td>";
    },
    "sqlOverride" => "v.varenr"
);
$columns[] =    array(
    "field" => "beskrivelse",
    "headerName" => "Navn",
    "width" => "3",
    "sqlOverride" => "v.beskrivelse"
);
$columns[] =    array(
    "field" => "trademark",
    "headerName" => "Varemærke",
    "hidden" => false,
    "sqlOverride" => "v.trademark"
);
$columns[] =    array(
    "field" => "varegruppe",
    "headerName" => "Varegruppe",
    "sqlOverride" => "vg.beskrivelse",
    "hidden" => false,
);
$columns[] =    array(
    "field" => "momssats",
    "headerName" => "Momssats",
    "width" => "0.5",
    "sqlOverride" => "sm.box2",
    "hidden" => true,
);
$columns[] =    array(
    "field" => "stregkode",
    "headerName" => "Stregkode",
    "sqlOverride" => "v.stregkode"
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
                $url = "../../kreditor/kreditorkort.php?id=$id&returside=../lager/lister/indkøb.php";
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

// Add in_sales_offer field
function renderColumn($value, $row, $column, $type, $idField, $orderField, $amountField, $dateField, $style = "") {
    if ($value == "0,00" || !$value) {
        return "<td align='$column[align]' style='$style'>0,00</td>";
    }

    // Extract the details for ordrenr, antal, and date from $row
    $ids = $row[$idField];
    $orders = $row[$orderField];
    $amounts = $row[$amountField];
    $dates = $row[$dateField];

    // Create a dropdown with table details
    $details = '';
    if ($orders && $amounts && $dates) {
        $idList = explode(', ', $ids);
        $ordrenrList = explode(', ', $orders);
        $antalList = explode(', ', $amounts);
        $dateList = explode(', ', $dates);

        $details .= '<table>';
        $details .= '<thead><tr><th>Ordrenr</th><th>Antal</th><th>Dato</th><th>('.count($idList).')</th></tr></thead>';
        $details .= '<tbody>';
        foreach ($ordrenrList as $index => $ordrenr) {
            $id = $idList[$index];
            $antal = $antalList[$index];
            $date = $dateList[$index];
            $url = "../../$type/ordre.php?id=$id&returside=../lager/lister/ordrestatus.php";
            $details .= "<tr><td><a href='$url'>$ordrenr</a></td><td>".dkdecimal($antal)."</td><td colspan=2>".dkdato($date)."</td></tr>";
        }
        $details .= '</tbody></table>';
    }

    // Return the table with hover dropdown
    return "
        <td align='$column[align]' style='$style; position: relative; cursor: context-menu'>
            <b>$value</b>
            <div class='hover-dropdown'>
                $details
            </div>
        </td>";
}

$columns[] = array(
    "field" => "in_sales_offer",
    "headerName" => "Tilbud",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "COALESCE(os.in_sales_offer, 0)",
    "render" => function ($value, $row, $column) {
        return renderColumn($value, $row, $column, 'debitor', 'sales_offer_orders_id', 'sales_offer_orders', 'sales_offer_orders_antal', 'sales_offer_orders_date', 'border-left: 1px black solid');
    }
);
$columns[] = array(
    "field" => "in_sales_order",
    "headerName" => "Ordre",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "COALESCE(os.in_sales_order, 0)",
    "render" => function ($value, $row, $column) {
        return renderColumn($value, $row, $column, 'debitor', 'sales_order_orders_id', 'sales_order_orders', 'sales_order_orders_antal', 'sales_order_orders_date');
    }
);
$columns[] = array(
    "field" => "in_buy_proposal",
    "headerName" => "Indkøbsforslag",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "COALESCE(os.in_buy_proposal, 0)",
    "render" => function ($value, $row, $column) {
        return renderColumn($value, $row, $column, 'kreditor', 'buy_proposal_orders_id', 'buy_proposal_orders', 'buy_proposal_orders_antal', 'buy_proposal_orders_date');
    }
);
$columns[] = array(
    "field" => "in_buy_order",
    "headerName" => "Indkøbsordre",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "COALESCE(os.in_buy_order, 0)",
    "render" => function ($value, $row, $column) {
        return renderColumn($value, $row, $column, 'kreditor', 'buy_order_orders_id', 'buy_order_orders', 'buy_order_qty', 'buy_order_date', 'border-right: 1px black solid');
    }
);
// Add lager_total field
$columns[] = array(
    "field" => "lager_total",
    "headerName" => "Beholdn.",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "COALESCE(ls.lager_total, 0)",
    "render" => function ($value, $row, $column) {
        if ($value == "0,00" || !$value) {
            return "<td align='$column[align]'>0,00</td>";
        }
        return "<td align='$column[align]'><b>$value</b></td>";
    }
);

$columns[] = array(
    "field" => "min_lager",
    "headerName" => "Min.",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "v.min_lager",
    "render" => function ($value, $row, $column) {
        if ($value == "0,00" || !$value) {
            return "<td align='$column[align]'>0,00</td>";
        }
        return "<td align='$column[align]'><b>$value</b></td>";
    }
);
$columns[] = array(
    "field" => "max_lager",
    "headerName" => "Max.",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "v.max_lager",
    "render" => function ($value, $row, $column) {
        if ($value == "0,00" || !$value) {
            return "<td align='$column[align]'>0,00</td>";
        }
        return "<td align='$column[align]'><b>$value</b></td>";
    }
);
$columns[] = array(
    "field" => "volume_lager",
    "headerName" => "Vol.",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "CAST(GREATEST(v.volume_lager, 1) AS NUMERIC)",
    "render" => function ($value, $row, $column) {
        if ($value == "1,00" || !$value) {
            return "<td align='$column[align]'>1,00</td>";
        }
        return "<td align='$column[align]'><b>$value</b></td>";
    }
);
$columns[] = array(
    "field" => "genbestil",
    'defaultSort' => true,
    'defaultSortDirection' =>'desc',
    "headerName" => "Gen.",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "CASE 
            WHEN (COALESCE(ls.lager_total, 0) - COALESCE(os.in_sales_order, 0) 
                + COALESCE(os.in_buy_proposal, 0) + COALESCE(os.in_buy_order, 0)) < v.min_lager THEN
                CEIL(
                    (v.max_lager - 
                     (COALESCE(ls.lager_total, 0) - COALESCE(os.in_sales_order, 0) 
                     + COALESCE(os.in_buy_proposal, 0) + COALESCE(os.in_buy_order, 0))) 
                    / GREATEST(v.volume_lager, 1)
                ) * GREATEST(v.volume_lager, 1)
            ELSE 0
        END::numeric",
    "render" => function ($value, $row, $column) {
        if ($value == "0,00" || !$value) {
            return "<td align='$column[align]'>0,00</td>";
        }
        return "<td align='$column[align]' style='cursor:pointer;' onclick=\"document.getElementById('genb-$row[id]').value='$value'\"><b><u>$value</u></b></td>";
    }
);

// Continue adding other fields if needed
$columns[] = array(
    "field" => "salgspris",
    "headerName" => "Salgspris u.m",
    "type" => "number",
    "align" => "right",
    "width" => "0.5",
    "hidden" => true,
    "sqlOverride" => "v.salgspris"
);
$columns[] = array(
    "field" => "momspris",
    "headerName" => "Salgspris",
    "description" => "(incl.moms)",
    "hidden" => true,
    "type" => "number",
    "align" => "right",
    "width" => "0.5",
    "sqlOverride" => "CASE 
        WHEN vg.box7 = 'on' THEN v.salgspris
        ELSE (100+sm.box2::float)/100*v.salgspris
    END"
);

$columns[] = array(
    "field" => "dg",
    "headerName" => "DG",
    "type" => "number",
    "align" => "right",
    "hidden" => true,
    "sqlOverride" => "
    ROUND(CASE 
               WHEN v.salgspris = 0 THEN 0 
               ELSE (v.salgspris - v.kostpris) / v.salgspris * 100 
           END, 2)",
    "width" => "0.5",
    "valueGetter" => function ($value, $row, $column) {
        return dkdecimal($value, 1)."%";
    },
    "decimalPrecision" => 1,
);

$columns[] = array(
    "field" => "sales_last_6_months",
    "headerName" => "6md.",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "COALESCE(ss.sales_last_6_months, 0)",
    "render" => function ($value, $row, $column) {
        return "<td align='{$column['align']}' style='border-left: 1px black solid'>{$value}</td>";
    }
);
$columns[] = array(
    "field" => "sales_last_3_months",
    "headerName" => "3md.",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "COALESCE(ss.sales_last_3_months, 0)",
);
$columns[] = array(
    "field" => "sales_last_1_month",
    "headerName" => "1md.",
    "type" => "number",
    "align" => "right",
    "width" => "0.2",
    "sqlOverride" => "COALESCE(ss.sales_last_1_month, 0)",
    "render" => function ($value, $row, $column) {
        return "<td align='{$column['align']}' style='border-right: 1px black solid'>{$value}</td>";
    }
);


$columns[] = array(
    "field" => "kostpris",
    "headerName" => "Kostpris",
    "type" => "number",
    "align" => "right",
    "width" => "0.5",
    "sqlOverride" => "v.kostpris"
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

    "query" => "WITH levs AS (
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
),
sales_summary AS (
    SELECT 
        v.id AS vare_id,
        SUM(CASE WHEN subquery.ordredate >= CURRENT_DATE - INTERVAL '1 month' THEN subquery.antal ELSE 0 END) AS sales_last_1_month,
        SUM(CASE WHEN subquery.ordredate >= CURRENT_DATE - INTERVAL '3 months' THEN subquery.antal ELSE 0 END) AS sales_last_3_months,
        SUM(CASE WHEN subquery.ordredate >= CURRENT_DATE - INTERVAL '6 months' THEN subquery.antal ELSE 0 END) AS sales_last_6_months
    FROM varer v
    LEFT JOIN (
        SELECT 
            ol.vare_id, 
            ol.antal, 
            o.ordredate 
        FROM ordrelinjer ol
        INNER JOIN ordrer o 
            ON o.id = ol.ordre_id AND o.art = 'DO'
        WHERE o.ordredate >= CURRENT_DATE - INTERVAL '6 months'
    ) subquery ON v.id = subquery.vare_id
    GROUP BY v.id
),
categorized_orders AS (
    SELECT 
        ol.vare_id AS vare_id,
        o.ordrenr::text AS ordrenr,
        o.id::text AS ordreid,
        o.status AS status,
        o.art AS art,
        ol.antal AS antal,
        ol.leveret AS leveret,
        o.ordredate AS ordredate,
        CASE 
            WHEN o.status < 1 AND o.art = 'DO' THEN 'sales_offer_qty'
            WHEN o.status < 3 AND o.art = 'DO' THEN 'sales_order_qty'
            WHEN o.status < 1 AND o.art = 'KO' THEN 'buy_proposal_qty'
            WHEN o.status < 3 AND o.art = 'KO' THEN 'buy_order_qty'
        END AS category
    FROM ordrelinjer ol
    INNER JOIN ordrer o 
        ON ol.ordre_id = o.id
    WHERE o.status IN (0,1,2)
    ORDER BY o.ordredate
),
order_summary AS (
    SELECT 
        vare_id,
        COALESCE(SUM(CASE WHEN category = 'sales_offer_qty' THEN antal ELSE 0 END), 0)                                      AS in_sales_offer,
        STRING_AGG(CASE WHEN category   = 'sales_offer_qty' THEN ordrenr ELSE NULL END, ', ')                               AS sales_offer_orders,
        STRING_AGG(CASE WHEN category   = 'sales_offer_qty' THEN ordreid ELSE NULL END, ', ')                               AS sales_offer_orders_id,
        STRING_AGG(CASE WHEN category   = 'sales_offer_qty' THEN antal::text ELSE NULL END, ', ')                           AS sales_offer_orders_antal,
        STRING_AGG(CASE WHEN category   = 'sales_offer_qty' THEN ordredate::text ELSE NULL END, ', ')                       AS sales_offer_orders_date,

        COALESCE(SUM(CASE WHEN category = 'sales_order_qty' THEN (antal - COALESCE(leveret, 0)) ELSE 0 END), 0)             AS in_sales_order,
        STRING_AGG(CASE WHEN category   = 'sales_order_qty' THEN ordrenr ELSE NULL END, ', ')                               AS sales_order_orders,
        STRING_AGG(CASE WHEN category   = 'sales_order_qty' THEN ordreid ELSE NULL END, ', ')                               AS sales_order_orders_id,
        STRING_AGG(CASE WHEN category   = 'sales_order_qty' THEN (antal - COALESCE(leveret, 0))::text ELSE NULL END, ', ')  AS sales_order_orders_antal,
        STRING_AGG(CASE WHEN category   = 'sales_order_qty' THEN ordredate::text ELSE NULL END, ', ')                       AS sales_order_orders_date,

        COALESCE(SUM(CASE WHEN category = 'buy_proposal_qty' THEN antal ELSE 0 END), 0)                                     AS in_buy_proposal,
        STRING_AGG(CASE WHEN category   = 'buy_proposal_qty' THEN ordrenr ELSE NULL END, ', ')                              AS buy_proposal_orders,
        STRING_AGG(CASE WHEN category   = 'buy_proposal_qty' THEN ordreid ELSE NULL END, ', ')                              AS buy_proposal_orders_id,
        STRING_AGG(CASE WHEN category   = 'buy_proposal_qty' THEN antal::text ELSE NULL END, ', ')                          AS buy_proposal_orders_antal,
        STRING_AGG(CASE WHEN category   = 'buy_proposal_qty' THEN ordredate::text ELSE NULL END, ', ')                      AS buy_proposal_orders_date,

        COALESCE(SUM(CASE WHEN category = 'buy_order_qty' THEN (antal - COALESCE(leveret, 0)) ELSE 0 END), 0)               AS in_buy_order,
        STRING_AGG(CASE WHEN category   = 'buy_order_qty' THEN ordrenr ELSE NULL END, ', ')                                 AS buy_order_orders,
        STRING_AGG(CASE WHEN category   = 'buy_order_qty' THEN ordreid ELSE NULL END, ', ')                                 AS buy_order_orders_id,
        STRING_AGG(CASE WHEN category   = 'buy_order_qty' THEN (antal - COALESCE(leveret, 0))::text ELSE NULL END, ', ')    AS buy_order_qty,
        STRING_AGG(CASE WHEN category   = 'buy_order_qty' THEN ordredate::text ELSE NULL END, ', ')                         AS buy_order_date
    FROM categorized_orders
    GROUP BY vare_id
)
SELECT 
    v.id AS id,                     
    v.varenr AS varenr,             
    v.lukket AS lukket,             
    v.beskrivelse AS beskrivelse,   
    v.trademark AS trademark,       
    v.stregkode AS stregkode,       
    v.enhed AS enhed,               
    v.min_lager AS min_lager,
    v.max_lager AS max_lager,
    GREATEST(v.volume_lager, 1) AS volume_lager, -- Pakningsmængde
    CASE 
        WHEN COALESCE(ls.lager_total, 0) - COALESCE(os.in_sales_order, 0) 
            + COALESCE(os.in_buy_proposal, 0) + COALESCE(os.in_buy_order, 0) < v.min_lager
            THEN CEIL((v.max_lager - 
                    (COALESCE(ls.lager_total, 0) - COALESCE(os.in_sales_order, 0) 
                        + COALESCE(os.in_buy_proposal, 0) + COALESCE(os.in_buy_order, 0))) 
                    / GREATEST(v.volume_lager, 1)) * GREATEST(v.volume_lager, 1)
        ELSE 0
    END AS genbestil,


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
    levs.lev AS leverandør,                         

    COALESCE(ss.sales_last_1_month, 0) AS sales_last_1_month,
    COALESCE(ss.sales_last_3_months, 0) AS sales_last_3_months,
    COALESCE(ss.sales_last_6_months, 0) AS sales_last_6_months,

    COALESCE(os.in_sales_offer, 0) AS in_sales_offer,
    os.sales_offer_orders AS sales_offer_orders,
    os.sales_offer_orders_id AS sales_offer_orders_id,
    os.sales_offer_orders_antal AS sales_offer_orders_antal,
    os.sales_offer_orders_date AS sales_offer_orders_date,

    COALESCE(os.in_sales_order, 0) AS in_sales_order,
    os.sales_order_orders AS sales_order_orders,
    os.sales_order_orders_id AS sales_order_orders_id,
    os.sales_order_orders_antal AS sales_order_orders_antal,
    os.sales_order_orders_date AS sales_order_orders_date,

    COALESCE(os.in_buy_proposal, 0) AS in_buy_proposal,
    os.buy_proposal_orders AS buy_proposal_orders,
    os.buy_proposal_orders_id AS buy_proposal_orders_id,
    os.buy_proposal_orders_antal AS buy_proposal_orders_antal,
    os.buy_proposal_orders_date AS buy_proposal_orders_date,

    COALESCE(os.in_buy_order, 0) AS in_buy_order,
    os.buy_order_orders AS buy_order_orders,
    os.buy_order_orders_id AS buy_order_orders_id,
    os.buy_order_qty AS buy_order_qty,
    os.buy_order_date AS buy_order_date

FROM varer v
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
LEFT JOIN levs ON v.id = levs.vare_id
LEFT JOIN sales_summary ss ON v.id = ss.vare_id
LEFT JOIN order_summary os ON v.id = os.vare_id
WHERE {{WHERE}} AND levs.lev IS NOT NULL AND v.samlevare != 'on' AND vg.box8 = 'on'
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
    'metaColumn' => function ($row) {
        return "<td><input 
            id='genb-$row[id]' 
            type='text' 
            style='width: 75px; text-align: right;' 
            class='inputbox genbestil-inp' 
            placeholder='0,00'
            genbestil='$row[genbestil]'
        ></td>";
    },
    "columns" => $columns,
    "filters" => $filters,
);


print "<div style='width: 100%; height: calc(100vh - 30px - 34px - 16px);'>";
create_datagrid("indkøb", $data);
print "</div>";

function genbestil($vare_id, $antal) {
	global $brugernavn,$db,$regnaar,$sprog_id;
	
	# Hent ansant til ordre ref
	$r = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
	if ($r) {
		$r = db_fetch_array(db_select("select navn from ansatte where id = $r[ansat_id]",__FILE__ . " linje " . __LINE__));
		($r['navn'])?$ref=$r['navn']:$ref=NULL;
	} else {
        $ref = NULL;
    }

	# Find leverandøre til vare id'et
	$qtxt="select * from vare_lev where vare_id = '$vare_id' order by posnr";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$lev_id=$r['lev_id'];
		$lev_varenr=$r['lev_varenr'];
		$pris=(int)$r['kostpris'];
		$ordredate=date("Y-m-d");

		# Se om der er et åben't forslag med ordredato i dag
		$qtxt="select id, sum from ordrer where konto_id = $lev_id and art = 'KO' and status < 1 and ordredate = '$ordredate'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$sum=(int)$r['sum'];
			$ordre_id=$r['id'];
		} else {
			# Get latest ordrenr
			$qtxt="select ordrenr from ordrer where art='KO' or art='KK' order by ordrenr desc";
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $ordrenr=$r['ordrenr']+1;
			else $ordrenr=1;
			
			# Fetch info on the kreditor
			$qtxt="select * from adresser where id = $lev_id";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			# Check if the kreditor is part of a kreditor group to see what momssats should be used
			if ($r['gruppe']) {
				$qtxt = "select box1 from grupper ";
				$qtxt.= "where kode = 'K' and art = 'KG' and kodenr = '$r[gruppe]' and fiscal_year = '$regnaar'";
				$r1 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				# Fetch the momskode
				$kode=substr($r1['box1'],0,1); 
                $kodenr=substr($r1['box1'],1);
			}	else {
				$qtxt="select varenr from varer where id = '$vare_id'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				print "<BODY onLoad=\"javascript:alert('Leverand&oslash;rgruppe ikke korrekt opsat for varenr $r[varenr]')\">";
                return;
			}

			# Fetch the momssats from the momskode
            if ($kode) {
                $qtxt = "select box2 from grupper where art = 'KM' and kode = '$kode' and kodenr = '$kodenr' and fiscal_year = '$regnaar'";
                $r1 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                $momssats=(int)$r1['box2'];
            } else {
                $momssats = 0;
            }

			# Create the order
			$qtxt="insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,"; #218180822
			$qtxt.="betalingsdage, betalingsbet, cvrnr, notes, art, ordredate, momssats, status, ref)";
			$qtxt.=" values ";
			$qtxt.="('$ordrenr','$r[id]','$r[kontonr]','".db_escape_string($r['firmanavn'])."','".db_escape_string($r['addr1'])."',";
			$qtxt.= "'".db_escape_string($r['addr2'])."','".db_escape_string($r['postnr'])."','".db_escape_string($r['bynavn'])."',";
			$qtxt.= "'".db_escape_string($r['land]'])."','$r[betalingsdage]','$r[betalingsbet]','$r[cvrnr]','".db_escape_string($r['notes'])."',";
			$qtxt.= "'KO','$ordredate','$momssats','0','".db_escape_string($ref)."')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);

			# Fetch the dynamically generated ordrenr
			$qtxt="select id from ordrer where ordrenr='$ordrenr' and art = 'KO'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$ordre_id=$r['id'];
		}
		# Get the vare information
		$qtxt="select varer.varenr as varenr,varer.beskrivelse as beskrivelse,varer.enhed as enhed,";
		$qtxt.="vare_lev.lev_varenr as lev_varenr,grupper.box7 as momsfri ";
		$qtxt.="from varer,vare_lev,grupper where ";
		$qtxt.="varer.id='$vare_id' and vare_lev.vare_id='$vare_id' and grupper.art='VG' and grupper.kodenr=varer.gruppe"; #20190313
	
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$varenr=db_escape_string($r['varenr']);
		$lev_varenr=db_escape_string($r['lev_varenr']);
		$enhed=db_escape_string($r['enhed']);
		$beskrivelse=db_escape_string($r['beskrivelse']);
		$momsfri=$r['momsfri'];
		
		# Add the vare to the order
		$qtxt="insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, pris, lev_varenr, antal, momsfri)";
		$qtxt.=" values ";
		$qtxt.="('$ordre_id', '1000', '$varenr', '$vare_id', '$beskrivelse', '$enhed', '$pris', '$lev_varenr', '$antal', '$momsfri')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$sum=$sum+$pris*$antal;	
		db_modify("update ordrer set sum = '$sum' where id = $ordre_id",__FILE__ . " linje " . __LINE__);	
	} else { 
		# Ingen leverandør
		$r = db_fetch_array(db_select("select varenr from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
		print "".findtekst(951,$sprog_id)." findes ikke (Varenr: $r[varenr])<br>";
	}
}
?>

<style>
    .hover-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        background: #fff;
        border: 1px solid #ccc;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        z-index: 10;
        max-height: 300px;
        overflow: scroll;
    }
    td:hover .hover-dropdown {
        display: block;
    }
    .hover-dropdown table {
        border-collapse: collapse;
        width: 100%;
    }
    .hover-dropdown th, .hover-dropdown td {
        border: 1px solid #ddd;
        padding: 2px;
        text-align: left;
    }
    .hover-dropdown th {
        background-color: #f2f2f2;
    }
</style>

<?php
$steps = array();
$steps[] = array(
    "selector" => "#back-btn",
    "content" => "Klik her for at vende tilbage til varelisten."
);
$steps[] = array(
    "selector" => ".leverandør",
    "content" => "Når du laver en ny indkøbsliste, vises kun varer med en tilknyttet leverandør. <br><br>Bestillingsforslag vil automatisk blive knyttet til den øverste leverandør på varekortet, hvis der er flere leverandører."
);
$steps[] = array(
    "selector" => ".in_sales_offer,.in_buy_order",
    "content" => "Viser hvor mange varer der er i tilbud, ordre eller indkøbsforslag. <br><br>Hold musen over et beholdningstal for at se, hvilke ordrer varen er inkluderet i. Du får en liste med ordrenumre og datoer."
);
$steps[] = array(
    "selector" => ".lager_total",
    "content" => "Viser, hvor mange enheder du har på lager af varen."
);
$steps[] = array(
    "selector" => ".min_lager,.max_lager",
    "content" => "Disse bruges til genbestilling. Når beholdningen falder under minimum, foreslår systemet at genbestille nok til at nå maksimum."
);
$steps[] = array(
    "selector" => ".volume_lager",
    "content" => "Hvis varen skal bestilles i bestemte mængder, kan du sætte systemet op til at bestille i f.eks. batches af 8."
);
$steps[] = array(
    "selector" => ".genbestil",
    "content" => "Her ser du, hvor meget systemet anbefaler, at du genbestiller. Dette beregnes ud fra lagerbeholdning, ordre og andre faktorer."
);
$steps[] = array(
    "selector" => ".sales_last_6_months,.sales_last_1_month",
    "content" => "Se, hvor meget du har solgt af varen over de sidste 6 måneder, 3 måneder eller 1 måned."
);
$steps[] = array(
    "selector" => "#autoudfyldBtn",
    "content" => "Klik for at autoudfylde alle beregnede genbestillingsværdier."
);
$steps[] = array(
    "selector" => "#bestilBtn",
    "content" => "Opret et indkøbsforslag baseret på de indtastede vareværdier. Det opretter en ny ordre til leverandøren eller tilføjer varen til et åbent indkøbsforslag med dagens dato."
);

include (get_relative()."includes/tutorial.php");
create_tutorial("vareliste", $steps);

?>