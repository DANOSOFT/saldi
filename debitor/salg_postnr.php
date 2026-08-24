<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------debitor/salg_postnr.php--------------2026-05-26--- 
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
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//20260526 LOE Created new report using new datagrid based on postnr.php where department column and date range filter have been added.
//20260709 SZ Replaced old top menu with Grid Framework header on Sales by Zip Code report
//20260709 SZ Fixed header/footer styling to match Finance -> Reports -> Balance

@session_start();

$s_id    = session_id();
$modulnr = 12;
$css     = "../css/standard.css";
$title   = "Salg pr. postnummer";

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

$dato_str = if_isset($_GET['search']['salg_postnr']['seneste_ordre'], '');
if ($dato_str && strpos($dato_str, ':') !== false) {
    list($fra_us, $til_us) = explode(':', $dato_str, 2);
} else {
    $fra_us = $default_fra;
    $til_us = $default_til;
}

$selected_afd = if_isset($_GET['search']['salg_postnr']['afd'], '');
if ($selected_afd !== '') {
    $selected_afd = (int)$selected_afd;
} else {
    $selected_afd = null;
}

$postnr_str = if_isset($_GET['search']['salg_postnr']['postnr'], '');
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
        o.postnr AS postnr,
        o.bynavn AS bynavn,
        o.afd AS afd,
        COUNT(*) AS antal_ordrer,
        SUM(o.sum * o.valutakurs / 100) AS belob,
        AVG(o.sum * o.valutakurs / 100) AS gns_ordre,
        MAX(o.fakturadate) AS seneste_ordre
    FROM ordrer o
    WHERE {{WHERE}}
      AND (o.art = 'DO' OR o.art = 'DK')
      AND o.status >= '3'
    GROUP BY o.postnr, o.bynavn, o.afd
    ORDER BY {{SORT}}
";

/* ============================================================
 * 4. PAGE HEADER
 * ============================================================ */
print "<center><table width='100%' cellpadding='0' cellspacing='0' border='0'><tbody>";
if ($menu == 'T') {
    $leftbutton = "<a class='button red small' href='../debitor/rapport.php' accesskey='L'>Luk</a>";
    include("../includes/top_header.php");
    include("../includes/top_menu.php");
    print "<div id='header'><div class='headerbtnLft'>$leftbutton</div><span class='headerTxt'>$title</span><div class='headerbtnRght'></div></div><div class='maincontentLargeHolder'>";
} elseif ($menu == 'S') {
    // Grid Framework header — same back-button/title-bar styling as Finance -> Reports -> Balance
    // (kontosaldo() in includes/rapportfunc.php). Sticky behaviour + footer/pagination for this
    // report are already handled by create_datagrid() below, so only the header visuals change here.
    $tilbage_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
    print "<tr><td colspan='5' height='8'><table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody><tr>
        <td width='10%' align='left'><a href='../debitor/rapport.php' accesskey='L'><button style='$buttonStyle; width:100%; display:flex; align-items:center; gap:5px; justify-content:flex-start; padding-left:3px;' onMouseOver=\"this.style.cursor='pointer'\">$tilbage_icon" . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>
        <td width='80%' align='center' style='$topStyle'>Sales by zip code</td>
        <td width='10%' style='$topStyle'><br></td>
        </tr></tbody></table></td></tr></tbody></table>";
} else {
    print "<tr><td colspan='4' height='8'><table width='100%' align='center' border='0' cellspacing='3' cellpadding='0'><tbody><tr>
        <td width='10%' $top_bund><a href='../debitor/rapport.php' accesskey='L'>Luk</a></td>
        <td width='80%' $top_bund>".findtekst('3360|Salg pr. postnummer', $sprog_id)."</td>
        <td width='10%' $top_bund></td>
    </tr></tbody>...</table></td></tr>";
}

/* ============================================================
 * 5. DATAGRID COLUMN DEFINITIONS
 * ============================================================ */
$grid_data = [
    'query'          => $query,
    'defaultRowCount'=> 100,
    'columns'        => [

        // Postnr — search cell doubles as the postnr range filter
       [
            'field'      => 'postnr',
            'headerName' => 'Postnr',
            'type'       => 'text',
            'width'      => '6',
            'align'      => 'left',
            'sortable'   => true,
            'searchable' => true,
            'generateSearch' => function($col, $term) {
                if (!$term || strpos($term, ':') === false) {
                    if ($term) {
                        $t = db_escape_string($term);
                        return "o.postnr ILIKE '%$t%'";
                    }
                    return '1=1';
                }
                list($fra, $til) = explode(':', $term, 2);
                $fra = (int)trim($fra);
                $til = (int)trim($til);
                if ($fra === 0 && $til === 0) return '1=1';
                $cond = "o.postnr != ''";
                /* for countries where postnr is numeric, we allow searching by range.
                Norway
                Denmark
                Germany 
                Below may break for countries like Germany, UK etc. so we assume postnr is numeric and strip non-numeric characters before comparing. This allows searching for Danish postnr like "1000:2500" even if the data contains "DK-1000", "DK-1050", "DK-2500" etc.
                */
                if ($fra > 0) $cond .= " AND NULLIF(regexp_replace(o.postnr, '[^0-9]', '', 'g'), '')::INTEGER >= $fra"; //for postnr with letters, we extract the number part and compare as integer
                if ($til > 0) $cond .= " AND NULLIF(regexp_replace(o.postnr, '[^0-9]', '', 'g'), '')::INTEGER <= $til";
                return $cond;
            }
        ],

        // Bynavn
        [
            'field'      => 'bynavn',
            'headerName' => 'By',
            'type'       => 'text',
            'width'      => '16',
            'align'      => 'center',
            'sortable'   => true,
            'searchable' => true,
        ],

        // Afdeling
        [
            'field'      => 'afd',
            'headerName' => 'Afdeling',
            'type'       => 'text',
            'width'      => '14',
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

        // Antal ordrer
        [
            'field'      => 'antal_ordrer',
            'headerName' => 'Antal ordrer',
            'type'       => 'number',
            'decimalPrecision' => 0,
            'width'      => '9',
            'align'      => 'center',
            'sortable'   => true,
            'searchable' => false,
            'defaultSort'=> true,
            'defaultSortDirection' => 'desc',
        ],

        // Omsætning
        [
            'field'      => 'belob',
            'headerName' => 'Beløb',
            'type'       => 'number',
            'decimalPrecision' => 2,
            'width'      => '12',
            'align'      => 'right',
            'sortable'   => true,
            'searchable' => false,
        ],

        // Gns. ordre
        [
            'field'      => 'gns_ordre',
            'headerName' => 'Avg. order DKK',
            'type'       => 'number',
            'decimalPrecision' => 2,
            'width'      => '12',
            'align'      => 'right',
            'sortable'   => true,
            'searchable' => false,
        ],

        // Seneste ordre
       [
            'field'       => 'seneste_ordre',
            'headerName'  => 'Fakturadato',
            'type'        => 'text',
            'width'       => '10',
            'align'       => 'center',
            'sortable'    => true,
            'searchable'  => true,
            'valueGetter' => function($v){ return $v ? dkdato($v) : ''; },
            'generateSearch' => function($col, $term) {
                if (!$term || strpos($term, ':') === false) {
                    return '1=1';
                }
                list($start, $end) = explode(':', $term, 2);
                $start = db_escape_string(trim($start));
                $end   = db_escape_string(trim($end));
                if (!$start || !$end) return '1=1';
                return "o.fakturadate BETWEEN '$start' AND '$end'";
            }
        ],

        // Fakturadato — search cell is the date range picker
        // [
        //     'field'       => 'fakturadate',
        //     'headerName'  => 'Fakturadato',
        //     'type'        => 'custom',
        //     'width'       => '1.6',
        //     'align'       => 'center',
        //     'sortable'    => false,
        //     'searchable'  => true,
        //     'description' => 'search by invoice date range',
        //     'valueGetter' => function($v) { return ''; },
        //     'render'      => function($v, $r, $c) { return '<td></td>'; },
        //     'generateSearch' => function($col, $term) use ($default_fra, $default_til) {
        //         if (!$term || strpos($term, ':') === false) {
        //             $start = $default_fra;
        //             $end   = $default_til;
        //         } else {
        //             list($start, $end) = explode(':', $term, 2);
        //         }
        //         $start = db_escape_string($start);
        //         $end   = db_escape_string($end);
        //         return "o.fakturadate BETWEEN '$start' AND '$end'";
        //     }
        // ],
    ],
    'filters' => [],
];

print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
create_datagrid('salg_postnr', $grid_data);

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
/* includes/grid.php's .datatable thead border-bottom uses $bgcolordark, which is
   never actually assigned anywhere in this codebase — it renders as an invalid/empty
   CSS color, so browsers fall back to a hard black border. Override with a light
   gray here rather than touching the shared global (affects every create_datagrid()
   report sitewide). */
#datatable-wrapper-salg_postnr .datatable thead {
    border-bottom: 2px solid #ccc;
}
/* Footer restyle to match salgsstat.php's Grid Framework footer bar look
   (background/border colour, pill-shaped nav buttons) — create_datagrid()'s
   own pagination markup/JS is left untouched, only its CSS is overridden. */
#datatable-wrapper-salg_postnr .datatable tfoot,
#datatable-wrapper-salg_postnr .datatable tfoot tr,
#datatable-wrapper-salg_postnr .datatable tfoot td {
    background-color: <?= $bgcolor ?>;
    border-top: 1px solid #b8bec8;
}
#datatable-wrapper-salg_postnr #footer-box {
    gap: 20px;
    line-height: 1;
}
#datatable-wrapper-salg_postnr .navbutton {
    height: 20px;
    min-width: 20px;
    padding: 0 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f0f0f0;
    color: #000;
    border: 1px solid #b8bec8;
    border-radius: 4px;
}
</style>

<script>
(function() {
    function upgradeSearchRow() {
        const wrapper = document.getElementById('datatable-wrapper-salg_postnr');
        if (!wrapper) return false;
        const thead = wrapper.querySelector('.datatable thead');
        if (!thead) return false;
        const rows = thead.querySelectorAll('tr');
        if (rows.length < 2) return false;

        const headerRow = rows[0];
        const searchRow = rows[1];
        const headerThs = headerRow.querySelectorAll('th');
        const searchThs = searchRow.querySelectorAll('th');

        let afdIdx = -1, postnrIdx = -1, dateIdx = -1;
        for (let i = 0; i < headerThs.length; i++) {
            const cls = headerThs[i].className;
            if (cls.includes('afd'))         afdIdx   = i;
            if (cls.includes('postnr') && !cls.includes('postnr_range')) postnrIdx = i;
            if (cls.includes('seneste_ordre')) dateIdx  = i; 
        }

        // 1) Upgrade afd column → dropdown
        if (afdIdx !== -1 && searchThs[afdIdx]) {
            const th = searchThs[afdIdx];
            const currentValue = "<?= (int)$selected_afd ?>";
            const options = <?= json_encode($afd_map) ?>;
            let selectHtml = `<select name="search[salg_postnr][afd]" class="custom-afd-select">`;
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
                        const offsetField = form.querySelector('input[name="offset[salg_postnr]"]');
                        if (offsetField) offsetField.value = '0';
                        form.submit();
                    }
                });
            }
        }

        // 2) Upgrade postnr column → single "fra:til" text input
        if (postnrIdx !== -1 && searchThs[postnrIdx]) {
            const th = searchThs[postnrIdx];
            let currentFra = "<?= addslashes($postnr_fra) ?>";
            let currentTil = "<?= addslashes($postnr_til) ?>";

            const input = document.createElement('input');
            input.type        = 'text';
            input.id          = 'postnr_range_input';
            input.placeholder = '1234:5628';
            input.className   = 'custom-date-range-input'; 
            input.value       = (currentFra && currentTil) ? currentFra + ' : ' + currentTil : '';

            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name  = 'search[salg_postnr][postnr]';
            hidden.id    = 'hidden_postnr_range';
            hidden.value = currentFra + ':' + currentTil;

            th.innerHTML = '';
            th.appendChild(input);
            th.appendChild(hidden);

            input.addEventListener('change', function() {
                const val = this.value.trim();
                // accept "1000:5000" or "1000 : 5000" or "1000 - 5000"
                const match = val.match(/^(\d*)\s*[:\-]\s*(\d*)$/);
                if (match) {
                    hidden.value = match[1] + ':' + match[2];
                } else {
                    hidden.value = val; // plain value, generateSearch handles it
                }
                const form = this.closest('form');
                if (form) {
                    const offsetField = form.querySelector('input[name="offset[salg_postnr]"]');
                    if (offsetField) offsetField.value = '0';
                    form.submit();
                }
            });
        }

        
        // 3) Upgrade seneste_ordre column → daterangepicker
        if (dateIdx !== -1 && searchThs[dateIdx]) {
            const th = searchThs[dateIdx];
            let currentRange = "<?= addslashes($dato_str) ?>";
            let startDate = null, endDate = null;
            if (currentRange && currentRange.indexOf(':') !== -1) {
                const parts = currentRange.split(':');
                startDate = moment(parts[0], 'YYYY-MM-DD');
                endDate   = moment(parts[1], 'YYYY-MM-DD');
            }
            if (!startDate || !startDate.isValid()) {
                startDate = moment('<?= $default_fra ?>', 'YYYY-MM-DD');
                endDate   = moment('<?= $default_til ?>', 'YYYY-MM-DD');
            }

            th.innerHTML = '';

            const input = document.createElement('input');
            input.type      = 'text';
            input.id        = 'dato_range_picker';
            input.className = 'custom-range-input';
            input.value     = startDate.format('DD-MM-YYYY') + ' : ' + endDate.format('DD-MM-YYYY');

            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name = 'search[salg_postnr][seneste_ordre]';
            hidden.id    = 'hidden_dato_range';
            hidden.value = startDate.format('YYYY-MM-DD') + ':' + endDate.format('YYYY-MM-DD');

            th.appendChild(input);
            th.appendChild(hidden);

            $(input).daterangepicker({
                startDate: startDate,
                endDate: endDate,
                linkedCalendars: false,
                ranges: {
                    'Sidste 30 dage':   [moment().subtract(29, 'days'), moment()],
                    'Sidste 3 måneder': [moment().subtract(3, 'months'), moment()],
                    'Sidste år':        [moment().subtract(1, 'year'), moment()],
                    'Dette år':         [moment().startOf('year'), moment()]
                },
                locale: {
                    format: 'DD-MM-YYYY',
                    applyLabel: 'Søg',
                    cancelLabel: 'Ryd',
                    customRangeLabel: 'Vælg periode',
                    daysOfWeek:  ['Sø','Ma','Ti','On','To','Fr','Lø'],
                    monthNames:  ['Januar','Februar','Marts','April','Maj','Juni',
                                'Juli','August','September','Oktober','November','December']
                },
                autoUpdateInput: false
            });

            $(input).on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('DD-MM-YYYY') + ' : ' + picker.endDate.format('DD-MM-YYYY'));
                document.getElementById('hidden_dato_range').value =
                    picker.startDate.format('YYYY-MM-DD') + ':' + picker.endDate.format('YYYY-MM-DD');
                const form = $(this).closest('form')[0];
                if (form) {
                    const off = form.querySelector('input[name="offset[salg_postnr]"]');
                    if (off) off.value = '0';
                    form.submit();
                }
            });

            $(input).on('cancel.daterangepicker', function() {
                $(this).val('');
                document.getElementById('hidden_dato_range').value = '';
                const form = $(this).closest('form')[0];
                if (form) {
                    const off = form.querySelector('input[name="offset[salg_postnr]"]');
                    if (off) off.value = '0';
                    form.submit();
                }
            });
        }

        return true;
    }

   
    const _originalHandleAction = handleActionsalg_postnr;
    handleActionsalg_postnr = function(action) {
        if (action === 'clear') {
            const selectFields = document.querySelectorAll('select[name^="search[salg_postnr]"]');
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