<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------------lager/salg_report.php--- lap 5.0.0 --- 2026-05-26 ----
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
// Copyright (c) 2003-2026 saldi.dk aps
// ----------------------------------------------------------------------

// 20260526 LOE Created new report based on datagrid, with flexible search and sorting, to handle sales report based on postnr and departments.


@session_start();

$s_id    = session_id();
$modulnr = 12;
$title   = "Salg pr. postnummer";
$css     = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
include("../includes/db_query.php");
include("../includes/topline_settings.php");
include("../includes/grid.php");

$title = findtekst('3360|Salg pr. postnummer', $sprog_id);

/* ============================================================
 * 1. READ FILTERS FROM $_GET
 * ============================================================ */
// $default_fra = date('Y-m-d', strtotime('-1 year'));
// $default_til = date('Y-m-d');
$default_fra = '2000-01-01';
$default_til = '2099-12-31';

$dato_str = if_isset($_GET['search']['salg_rapport']['fakturadate'], '');
if ($dato_str && strpos($dato_str, ':') !== false) {
    list($fra_us, $til_us) = explode(':', $dato_str, 2);
} else {
    $fra_us = "$default_fra";
    $til_us = $default_til;
}

$selected_afd = if_isset($_GET['search']['salg_rapport']['afd'], '');
if ($selected_afd !== '') {
    $selected_afd = (int)$selected_afd;
} else {
    $selected_afd = null;
}

$postnr_str = if_isset($_GET['search']['salg_rapport']['postnr'], '');
if ($postnr_str && strpos($postnr_str, ':') !== false) {
    list($postnr_fra, $postnr_til) = explode(':', $postnr_str, 2);
} else {
    $postnr_fra = '';
    $postnr_til = '';
}

/* ============================================================
 * 2. GET AFDELINGER FROM GRUPPER
 * ============================================================ */
$afd_map = [];
$q = db_select("SELECT kodenr, beskrivelse FROM grupper WHERE art = 'AFD' ORDER BY kodenr", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    $afd_map[(int)$r['kodenr']] = $r['beskrivelse'];
}

/* ============================================================
 * 3. BASE QUERY
 * ============================================================ */
$query = "
   SELECT
    v.varenr,
    v.beskrivelse,
    ABS(SUM(bs.antal)) AS antal,
    bs.fakturadate,
    o.afd,
    a.postnr,
    a.bynavn,
    SUM(bs.pris * ABS(bs.antal) * o.valutakurs / 100) AS total_dkk

FROM batch_salg bs

INNER JOIN ordrer o
    ON bs.ordre_id = o.id

LEFT JOIN adresser a
    ON o.konto_id = a.id

LEFT JOIN varer v
    ON bs.vare_id = v.id

WHERE {{WHERE}}
  AND (o.art = 'DO' OR o.art = 'DK')
  AND o.status >= 3

GROUP BY
    v.varenr,
    v.beskrivelse,
    bs.fakturadate,
    o.afd,
    a.postnr,
    a.bynavn

ORDER BY {{SORT}}";

/* ============================================================
 * 4. PAGE HEADER
 * ============================================================ */
print "<center><table width='100%' cellpadding='0' cellspacing='0' border='0'><tbody>";
if ($menu == 'T') {
    $leftbutton = "<a class='button red small' href='../lager/rapport.php' accesskey='L'>Luk</a>";
    include("../includes/top_header.php");
    include("../includes/top_menu.php");
    print "<div id='header'><div class='headerbtnLft'>$leftbutton</div><span class='headerTxt'>$title</span><div class='headerbtnRght'></div></div><div class='maincontentLargeHolder'>";
} elseif ($menu == 'S') {
    print "<tr><td colspan='5' height='8'><table width='100%' align='center' border='0' cellspacing='3' cellpadding='0'><tbody><tr>
        <td width='10%'><a href='../lager/rapport.php' accesskey='L'><button style='$buttonStyle; width:100%'>" . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>
        <td width='80%' style='$topStyle' align='center'>$title</td>
        <td width='10%' style='$topStyle'><br></td>
    </table>";
} else {
    print "<tr><td colspan='4' height='8'><table width='100%' align='center' border='0' cellspacing='3' cellpadding='0'><tbody><tr>
        <td width='10%' $top_bund><a href='../lager/rapport.php' accesskey='L'>Luk</a></td>
        <td width='80%' $top_bund>$title</td>
        <td width='10%' $top_bund></td>
    </tr></tbody>...</td></tr>";
}

/* ============================================================
 * 5. DATAGRID COLUMN DEFINITIONS (compact widths)
 * ============================================================ */
$grid_data = [
    'query'          => $query,
    'defaultRowCount'=> 100,
    'columns' => [

        // FIRST COLUMN
        [
        'field'      => 'varenr',
        'headerName' => 'Varenr',
        'type'       => 'text',
        'width'      => '1.3',
        'align'      => 'left',
        'sortable'   => true,
        'searchable' => true,

        'generateSearch' => function($col, $term) {
                $term = db_escape_string($term);

                return "v.varenr ILIKE '%$term%'";
            }
        ],

       



       [
            'field'      => 'beskrivelse',
            'headerName' => 'Beskrivelse',
            'type'       => 'text',
            'width'      => '2.5',
            'align'      => 'left',
            'sortable'   => true,
            'searchable' => true,

            'generateSearch' => function($col, $term) {

                $term = db_escape_string($term);

                return "v.beskrivelse ILIKE '%$term%'";
            }
        ],

        [
            'field'      => 'afd',
            'headerName' => 'Afdeling',
            'type'       => 'text',
            'width'      => '1.2',
            'align'      => 'left',
            'sortable'   => true,
            'searchable' => true,
            'valueGetter' => function($v) use ($afd_map) {
                $id = (int)$v;
                return isset($afd_map[$id]) ? $afd_map[$id] : $id;
            },
            'generateSearch' => function($col, $term) {
                if ($term === '' || $term === null) return '1=1';
                $id = (int)$term;
                return "o.afd = $id";
            }
        ],
        [
            'field'      => 'postnr',
            'headerName' => 'Postnr',
            'type'       => 'text',
            'width'      => '0.8',
            'align'      => 'left',
            'sortable'   => true,
            'searchable' => true,   // enables a search cell in the second header row
            'generateSearch' => function($col, $term) {
                if (!$term) return '1=1';
                if (strpos($term, ':') === false) {
                    $t = db_escape_string($term);
                    return "a.postnr ILIKE '%$t%'"; 
                }
                list($fra, $til) = explode(':', $term, 2);
                $fra = (int)trim($fra);
                $til = (int)trim($til);
                if ($fra === 0 && $til === 0) return '1=1';
                $cond = "a.postnr != ''";
                /*
                Norway
                Denmark
                Germany 
                Below may break for countries like Germany, UK etc. so we assume postnr is numeric and strip non-numeric characters before comparing. This allows searching for Danish postnr like "1000:2500" even if the data contains "DK-1000", "DK-1050", "DK-2500" etc.
                */
                if ($fra > 0) $cond .= " AND NULLIF(regexp_replace(a.postnr, '[^0-9]', '', 'g'), '')::INTEGER >= $fra";
                if ($til > 0) $cond .= " AND NULLIF(regexp_replace(a.postnr, '[^0-9]', '', 'g'), '')::INTEGER <= $til";
                return $cond;
            }
        ],
        
       [
            'field'      => 'antal',
            'headerName' => 'Antal',
            'type'       => 'number',
            'decimalPrecision' => 0,
            'width'      => '0.8',
            'align'      => 'right',
            'sortable'   => true,
            'searchable' => false,
        ],
        [
            'field'      => 'total_dkk',
            'headerName' => 'Beløb (DKK)', // invoiced amount in DKK
            'type'       => 'number',
            'decimalPrecision' => 2,
            'width'      => '1.0',
            'align'      => 'right',
            'sortable'   => true,
            'searchable' => false,
            'valueGetter'=> function($v){ return $v ? dkdecimal($v,2) : '0,00'; },
        ],
        [
            'field'      => 'bynavn',
            'headerName' => 'By',
            'type'       => 'text',
            'width'      => '1.2',
            'align'      => 'center',
            'sortable'   => true,
            'generateSearch' => function($col, $term) {
                if (!$term) return '1=1';
                $t = db_escape_string($term);
                return "a.bynavn ILIKE '%$t%'";
            },
        ],
        
        // column: date range 
       
        [
            'field'       => 'fakturadate',
            'headerName' => 'Fakturadato',
            'type'        => 'custom',
            'width'       => '1.6',
            'align'       => 'center',
            'sortable'    => true,
            'searchable'  => true,
            'valueGetter' => function($v) {
                return $v ? date('d-m-Y', strtotime($v)) : '';
            },
            'generateSearch' => function($col, $term) {
                if (!$term || strpos($term, ':') === false) {
                    return '1=1';   // No filter — show everything
                }
                list($start, $end) = explode(':', $term, 2);
                $start = db_escape_string(trim($start));
                $end   = db_escape_string(trim($end));
                if (!$start || !$end) return '1=1';
                return "bs.fakturadate BETWEEN '$start' AND '$end'";
            }
        ],
    ],
    'filters' => [],
];

print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";

create_datagrid('salg_rapport', $grid_data);

/* ============================================================
 * 6. JAVASCRIPT FOR CUSTOM SEARCH CONTROLS
 * ============================================================ */
?>
<script src="../javascript/moment.min.js"></script>
<script src="../javascript/daterangepicker.min.js"></script>
<link rel="stylesheet" type="text/css" href="../css/daterangepicker.css">

<style>
.datatable-wrapper {
    margin-top: 0 !important;
    padding-top: 0 !important;
}
.datatable thead tr:first-child th {
    padding-top: 4px;
    padding-bottom: 4px;
}
.datatable thead tr:nth-child(2) th {
    padding-top: 2px;
    padding-bottom: 2px;
}
.custom-afd-select {
    width: 100%;
    padding: 2px;
    font-size: 0.85em;
}
.custom-postnr-range {
    display: flex;
    gap: 4px;
    align-items: center;
    justify-content: center;
}
.custom-postnr-range input {
    width: 65px;
    padding: 2px;
    font-size: 0.85em;
}
.custom-date-range-input {
    width: 110px;
    padding: 2px;
    font-size: 0.85em;
    text-align: center;
}
.custom-postnr-range-input {
    width: 100%;
    padding: 2px;
    font-size: 0.85em;
}
</style>

<script>
(function() {
    function upgradeSearchRow() {
        const wrapper = document.getElementById('datatable-wrapper-salg_rapport');
        if (!wrapper) return false;
        const thead = wrapper.querySelector('.datatable thead');
        if (!thead) return false;
        const rows = thead.querySelectorAll('tr');
        if (rows.length < 2) return false;
        
        const headerRow = rows[0];
        const searchRow = rows[1];
        const headerThs = headerRow.querySelectorAll('th');
        const searchThs = searchRow.querySelectorAll('th');
        
        let afdIdx = -1, postnrRangeIdx = -1, dateIdx = -1;
        for (let i = 0; i < headerThs.length; i++) {
            const cls = headerThs[i].className;
            if (cls.includes('afd')) afdIdx = i;
            if (cls.includes('postnr')) postnrRangeIdx = i;
            if (cls.includes('fakturadate')) dateIdx = i;
        }
        
        // 1) Upgrade afd column
        if (afdIdx !== -1 && searchThs[afdIdx]) {
            const th = searchThs[afdIdx];
            const currentValue = "<?= (int)$selected_afd ?>";
            const options = <?= json_encode($afd_map) ?>;
            let selectHtml = `<select name="search[salg_rapport][afd]" class="custom-afd-select">`;
            selectHtml += `<option value="">-- Alle afdelinger --</option>`;
            Object.entries(options).forEach(([afd, navn]) => {
                const selected = (currentValue == afd) ? 'selected' : '';
                selectHtml += `<option value="${afd}" ${selected}>${navn}</option>`;
            });
            selectHtml += `</select>`;
            th.innerHTML = selectHtml;
            const selectEl = th.querySelector('select');
            if (selectEl) {
                selectEl.addEventListener('change', function() {
                    const form = this.closest('form');
                    if (form) {
                        const offsetField = form.querySelector('input[name="offset[salg_rapport]"]');
                        if (offsetField) offsetField.value = '0';
                        form.submit();
                    }
                });
            }
        }
        
        // 2) Upgrade postnr_range column
        if (postnrRangeIdx !== -1 && searchThs[postnrRangeIdx]) {
            const th = searchThs[postnrRangeIdx];
            let currentFra = "<?= addslashes($postnr_fra) ?>";
            let currentTil = "<?= addslashes($postnr_til) ?>";

            th.innerHTML = '';  // clear existing input the grid put there

            const input = document.createElement('input');
            input.type        = 'text';
            input.id          = 'postnr_range_input';
            input.placeholder = '1234:5628'
            input.className   = 'custom-postnr-range-input';
            // Only pre-fill if there's an actual range saved
            input.value       = (currentFra !== '' || currentTil !== '')
                                ? currentFra + ' : ' + currentTil
                                : '';

            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name = 'search[salg_rapport][postnr]';
            hidden.id    = 'hidden_postnr_range';
            // Only pre-fill hidden if there's an actual range
            hidden.value = (currentFra !== '' || currentTil !== '')
                            ? currentFra + ':' + currentTil
                            : '';

            th.appendChild(input);
            th.appendChild(hidden);

            input.addEventListener('change', function() {
                const val = this.value.trim();
                if (val === '') {
                    hidden.value = '';
                } else {
                    const match = val.match(/^(\d*)\s*[:\-]\s*(\d*)$/);
                    if (match) {
                        hidden.value = match[1] + ':' + match[2];
                    } else {
                        hidden.value = val;
                    }
                }
                const form = this.closest('form');
                if (form) {
                    const offsetField = form.querySelector('input[name="offset[salg_rapport]"]');
                    if (offsetField) offsetField.value = '0';
                    form.submit();
                }
            });
        }
        
        // 3) Upgrade date range column
        if (dateIdx !== -1 && searchThs[dateIdx]) {
            const th = searchThs[dateIdx];
            let currentRange = "<?= addslashes($dato_str) ?>";
            let startDate = null, endDate = null;
            if (currentRange && currentRange.indexOf(':') !== -1) {
                let parts = currentRange.split(':');
                startDate = moment(parts[0], 'YYYY-MM-DD');
                endDate = moment(parts[1], 'YYYY-MM-DD');
            }
            if (!startDate || !startDate.isValid()) {
                startDate = moment('<?= $default_fra ?>', 'YYYY-MM-DD');
                endDate = moment('<?= $default_til ?>', 'YYYY-MM-DD');
            }
            const input = document.createElement('input');
            input.type = 'text';
            input.name  = 'search[salg_rapport][fakturadate]';
            input.id = 'dato_range_picker';
            input.className = 'custom-date-range-input';
           
            input.value = startDate.format('DD-MM-YYYY') + ' : ' + endDate.format('DD-MM-YYYY');
            
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'search[salg_rapport][fakturadate]';
            hidden.id = 'hidden_dato_range';
            hidden.value = startDate.format('YYYY-MM-DD') + ':' + endDate.format('YYYY-MM-DD');
           //hidden.value = '';
            th.innerHTML = '';
            th.appendChild(input);
            th.appendChild(hidden);
            
            $(input).daterangepicker({
                startDate: startDate,
                endDate: endDate,
                linkedCalendars: false,
                ranges: {
                    'Sidste 30 dage': [moment().subtract(29, 'days'), moment()],
                    'Sidste 3 måneder': [moment().subtract(3, 'months'), moment()],
                    'Sidste år': [moment().subtract(1, 'year'), moment()],
                    'Dette år': [moment().startOf('year'), moment()]
                },
                locale: {
                    format: 'DD-MM-YYYY',
                    applyLabel: 'Søg',
                    cancelLabel: 'Ryd',
                    customRangeLabel: 'Vælg periode',
                    daysOfWeek: ['Sø','Ma','Ti','On','To','Fr','Lø'],
                    monthNames: ['Januar','Februar','Marts','April','Maj','Juni','Juli','August','September','Oktober','November','December']
                },
                autoUpdateInput: false
            });
            $(input).on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('DD-MM-YYYY') + ' : ' + picker.endDate.format('DD-MM-YYYY'));
                document.getElementById('hidden_dato_range').value = picker.startDate.format('YYYY-MM-DD') + ':' + picker.endDate.format('YYYY-MM-DD');
                const form = $(this).closest('form')[0];
                if (form) {
                    const offsetField = form.querySelector('input[name="offset[salg_rapport]"]');
                    if (offsetField) offsetField.value = '0';
                    form.submit();
                }
            });
            $(input).on('cancel.daterangepicker', function() {
                $(this).val('');
                document.getElementById('hidden_dato_range').value = '';
                const form = $(this).closest('form')[0];
                if (form) {
                    const offsetField = form.querySelector('input[name="offset[salg_rapport]"]');
                    if (offsetField) offsetField.value = '0';
                    form.submit();
                }
            });
        }
        return true;
    }

    
    const _originalHandleAction = handleActionsalg_rapport;
    handleActionsalg_rapport = function(action) {
        if (action === 'clear') {
            const selectFields = document.querySelectorAll('select[name^="search[salg_rapport]"]');
            selectFields.forEach(field => {
                field.value = '';
            });
        }
        _originalHandleAction(action);
    };
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (!upgradeSearchRow()) setTimeout(upgradeSearchRow, 300);
        });
    } else {
        if (!upgradeSearchRow()) setTimeout(upgradeSearchRow, 300);
    }
})();
</script>

<?php
if ($menu == 'T') print "</div>";
print "</tbody></table></center>";
?>
