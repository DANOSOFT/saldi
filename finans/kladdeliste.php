<?php
// --- finans/kladdeliste.php -------- patch 4.1.1 --- 2025.10.27 --- 
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2025 Saldi.dk ApS
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
@session_start();
$s_id=session_id();
	
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

// delete functionality for cash journals - 2025-10-18
if (isset($_POST['delete_kladde'])) {
    $kladde_id = (int)$_POST['delete_kladde'];
    
    $check_query = db_select("SELECT bogfort FROM kladdeliste WHERE id = $kladde_id", __FILE__ . " linje " . __LINE__);
    if ($check_row = db_fetch_array($check_query)) {
        if ($check_row['bogfort'] == '-' || $check_row['bogfort'] == '!') {
            db_modify("DELETE FROM kladdeliste WHERE id = $kladde_id", __FILE__ . " linje " . __LINE__);
            
            db_modify("DELETE FROM kassekladde WHERE kladde_id = $kladde_id", __FILE__ . " linje " . __LINE__);
            
            header("Location: kladdeliste.php");
            exit;
        }
    }
}

print "<meta http-equiv=\"refresh\" content=\"150;URL=kladdeliste.php\">";
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
    "render" => function ($value, $row, $column) use ($tidspkt, $popup, $jsvars, $brugernavn) {
        $kladde = "kladde" . $value;
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
            return "<td align='{$column['align']}'><span title='" . findtekst('1607|Kladde er låst af', $sprog_id) . " {$row['hvem']}'>$value</span></td>";
        }
        
        $url = "kassekladde.php?tjek=$value&kladde_id=$value&returside=kladdeliste.php";
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
        if ($row['bogfort'] == '-' || $row['bogfort'] == '!') {
            return "<td align='{$column['align']}'>
                <button onclick=\"deleteKladde({$row['id']}); event.stopPropagation();\" style='
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
                title='" . findtekst('1099|Slet', $sprog_id) . " kassekladde'>
                <i class='fa fa-trash-o' style='font-size: 12px;'></i>
                " . findtekst('1099|Slet', $sprog_id) . "
                </button>
            </td>";
        }
        return "<td align='{$column['align']}'></td>";
    },
);

$filters = array();
$filters[] = array(
    "label" => findtekst('634|Ejer', $sprog_id),
    "options" => array(
        array("value" => "alle", "label" => findtekst('636|Vis alle', $sprog_id), "checked" => true),
        array("value" => "egne", "label" => findtekst('641|Vis egne', $sprog_id), "checked" => false),
    ),
    "generateSQL" => function ($filter) {
        global $brugernavn;
        $selected = array();
        foreach ($filter["options"] as $option) {
            if ($option["checked"]) {
                $selected[] = $option["value"];
            }
        }
        
        if (in_array("egne", $selected)) {
            return "k.oprettet_af = '" . db_escape_string($brugernavn) . "'";
        }
        
        return "1=1";
    },
);

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
    -- Sort order: non-posted first (- and !), then posted (S, V, etc)
    CASE 
        WHEN k.bogfort IN ('-', '!') THEN 0
        ELSE 1
    END as sort_group
FROM kladdeliste k
WHERE {{WHERE}}
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

// Delete script
print("<script>
function deleteKladde(kladdeId) {
    if (confirm(\"".findtekst('155|Vil du slette denne ordre?', $sprog_id)."\")) {
        // Create a form and submit it
        var form = document.createElement(\"form\");
        form.method = \"POST\";
        form.action = \"kladdeliste.php\";

        var input = document.createElement(\"input\");
        input.type = \"hidden\";
        input.name = \"delete_kladde\";
        input.value = kladdeId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
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

exit;

// if ($menu=='T') {
// 	include_once '../includes/top_header.php';
// 	include_once '../includes/top_menu.php';
// 	print "<div id=\"header\">"; 
// 	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
// 	print "<div class=\"headerTxt\">$title</div>";     
// 	print "<div class=\"headerbtnRght headLink\"><a href=kassekladde.php?returside=kladdeliste.php&tjek=-1 accesskey=N title='Opret ny kassekladde'><i class='fa fa-plus-square fa-lg'></i></a></div>";     
// 	print "</div>";
// 	print "<div class='content-noside'>";
// 	print  "<table class='dataTable' border='0' cellspacing='1' width='100%'>";
// } elseif ($menu=='S') {
// 	print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>
// 		   <tr class='sc-body'><td height = '25' align='center' valign='top'>
// 		   <table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";

// 	print "<td width='10%'  title='".findtekst('1599|Klik her for at lukke kladdelisten', $sprog_id)."'>"; #20210721
// 	print "<a href='$backUrl' accesskey='L'><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";
// 	print "<td width=70% style='$topStyle' align=center>".findtekst('639|Kladdeliste', $sprog_id)."</td>";
// 	print "<td id='tutorial-help' width=5% style=$buttonStyle>
// 	<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
// 		".findtekst('2564|Hjælp', $sprog_id)."
// 	</button></td>";
// 	$query = db_select("SELECT var_value FROM settings WHERE var_name = 'companyID'", __FILE__ . " linje " . __LINE__);
// 	if(db_num_rows($query) > 0){
// 		print "<td width='5%'><form method='post' name='digital'>";
// 		print "<button type='submit' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\" name='digital' value='digital'>";
// 		print "Digital";
// 		print "</button>";
// 		print "</form></td>";
// 	}
// 	if(isset($_POST['digital'])) {
// 		$query = db_select("SELECT var_value FROM settings WHERE var_name = 'companyID'", __FILE__ . " linje " . __LINE__);
// 		$companyID = db_fetch_array($query)["var_value"];
// 		$ch = curl_init();
// 		curl_setopt($ch, CURLOPT_URL, "https://easyubl.net/api/Tools/TemporaryKey/$companyID/3");
// 		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', "Authorization: ".$apiKey));
// 		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// 		$res = curl_exec($ch);
// 		curl_close($ch);
// 		?>
// 		<script>
// 			window.open('https://approver.easyubl.eu/?tempKey=<?php echo $res; ?>', '_blank');
// 			// Optionally close the current window or redirect it
// 			// window.location.href = 'your-return-url.php'; // redirect current window
// 			// window.close(); // close current window
// 		</script>
// 		<?php
// 	}
// 	print "<td width='5%' title='".findtekst('1600|Klik her for at oprette en ny kassekladde', $sprog_id)."'>";
// 	print "<a href=kassekladde.php?returside=kladdeliste.php&tjek=-1 accesskey=N><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\" id='ny'>".findtekst('39|Ny', $sprog_id)."</button></a></td>";
// 	print "</tbody></table></td></tr><tr class='sc-body'><td valign='top'><table cellpadding='1' cellspacing='1' border='0' width='100%' valign = 'top'>";
// } else {
// #	if ($menu=='S') {
// #		print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
// #		print "<tr><td style = 'width:150px;'>";
// #		include ('../includes/sidemenu.php');
// #		print "</td><td><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
// #	}
// 	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
// 	<tr><td height = \"25\" align=\"center\" valign=\"top\">
// 	<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
// 	print "<td width=\"10%\"  title=\"".findtekst('1599|Klik her for at lukke kladdelisten', $sprog_id)."\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">"; #20210721
// 	if ($popup) print "<a href=../includes/luk.php accesskey=L>".findtekst('30|Tilbage', $sprog_id)."</a></td>";
// 	else print "<a href=../index/menu.php accesskey=L>".findtekst('30|Tilbage', $sprog_id)."</a></td>";
// 	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">".findtekst('639|Kladdeliste', $sprog_id)."</td>";
// 	if ($popup) print "<td width=\"10%\" title=\"".findtekst('1600|Klik her for at oprette en ny kassekladde', $sprog_id)."\" $top_bund onClick=\"javascript:kladde=window.open('kassekladde.php?returside=kladdeliste.php&tjek=-1','kladde','$jsvars');kladde.focus();\"><a href=kladdeliste.php?sort=$sort&rf=$rf&vis=$vis accesskey=N id='ny'>".findtekst('39|Ny', $sprog_id)."</a></td>";
// 	else print "<td width=\"10%\" title=\"".findtekst('1600|Klik her for at oprette en ny kassekladde', $sprog_id)."\" $top_bund><a href=kassekladde.php?returside=kladdeliste.php&tjek=-1 accesskey=N>".findtekst('39|Ny', $sprog_id)."</a></td>";
// 	print "</tbody></table></td></tr><tr><td valign=\"top\"><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
// }


// print '<style>
//  /* Sticky second header/row with an offset */
//       .header-row {
// 	  margin: 8px;
//       position: fixed;
//       top: 0;
//       left: 0;
//       width: 99%; 
//       background-color: #f1f1f1;
//       z-index: 11; 
//       display: table; 
// 	  padding-right: 17px;
//   }

//   /* Table row (tr) */
//   .header-row .tr {
//       display: table-row; /* Makes this a row in the table */
//   }

//   /* Table cells (td) */
//   .header-row .cell {
//       display: table-cell; /* Makes this a table cell */
//       padding: 10px;
//       text-align: center;
//       border: 1px solid #ddd; /* adds borders to cells */
//   }

//   /* Set the width for each column */
//   .header-row .cell:nth-child(1) {
//       width: 10%; 
//   }

//   .header-row .cell:nth-child(2) {
//       width: 70%; 
//   }



//   .sticky-header-a {
//         position: sticky;
//         top: 29px;
//         background-color: #f1f1f1; 
//         z-index: 9; 
// 		width: 100%;
//     }

//     /* Sticky header */
//     .sticky-header {
//         position: sticky;
//         top: 44px;
//         background-color: #f1f1f1; 
//         z-index: 10; 
// 		margin-bottom: 40px; 
//     }
  
// .table-con {
//     position: fixed;
//     top: 0;
//     left: 0;
//     width: 100%;
//     height: 70px;  
//     background-color: #f1f1f1; 
//     z-index: 8; 
//     border-bottom: 1px solid #ccc;  
// }

	
	
// </style>';

// print"<div class='table-con'></div>";
// // Now print the table header with sticky positioning

// print "<tr class='sticky-header' bgcolor=\"$linjebg\" >\n";

// if (($sort == 'id') && (!$rf)) {
//     print "<td width = 5%><b><a href='kladdeliste.php?sort=id&rf=desc&vis=$vis'>Id</a></b></td>\n";
// } else {
//     print "<td width = 5% title='".findtekst('1602|Klik her for at sortere på ID', $sprog_id)."'><b><a href='kladdeliste.php?sort=id&vis=$vis'>ID</a></b></td>\n";
// }

// if (($sort == 'kladdedate') && (!$rf)) {
//     print "<td width = 10%><b><a href='kladdeliste.php?sort=kladdedate&rf=desc&vis=$vis'>".findtekst('635|Dato', $sprog_id)."</a></b></td>\n";
// } else {
//     print "<td width = 10% title='".findtekst('1603|Klik her for at sortere på dato', $sprog_id)."'><b><a href='kladdeliste.php?sort=kladdedate&vis=$vis'>".findtekst('635|Dato', $sprog_id)."</a></b></td>\n";
// }

// if (($sort == 'oprettet_af') && (!$rf)) {
//     print "<td><b><a href='kladdeliste.php?sort=oprettet_af&rf=desc&vis=$vis'>".findtekst('634|Ejer', $sprog_id)."</a></b></td>\n";
// } else {
//     print "<td title='".findtekst('1604|Klik her for at sortere på ejer (den der har oprettet kassekladden)', $sprog_id)."'><b><a href='kladdeliste.php?sort=oprettet_af&vis=$vis'>".findtekst('634|Ejer', $sprog_id)."</a></b></td>\n";
// }

// if (($sort == 'kladdenote') && (!$rf)) {
//     print "<td width = 70%><b><a href='kladdeliste.php?sort=kladdenote&rf=desc&vis=$vis'>".findtekst('391|Bemærkning', $sprog_id)."</a></b></td>\n";
// } else {
//     print "<td width = 70% title='".findtekst('1605|Klik her for at sortere på bemærkning', $sprog_id)."'><b><a href='kladdeliste.php?sort=kladdenote&vis=$vis'>".findtekst('391|Bemærkning', $sprog_id)."</a></b></td>\n";
// }
// if (($sort == 'bogforingsdate') && (!$rf)) {
//     print "<td align='center'><b><a href='kladdeliste.php?sort=bogforingsdate&rf=desc&vis=$vis'>".findtekst('637|Bogført', $sprog_id)."</a></b></td>\n";
// } else {
//     print "<td align='center'><b><a href='kladdeliste.php?sort=bogforingsdate&vis=$vis'>".findtekst('637|Bogført', $sprog_id)."</a></b></td>\n";
// }
// if (($sort == 'bogfort_af') && (!$rf)) {
//     print "<td><b><a href='kladdeliste.php?sort=bogfort_af&rf=desc&vis=$vis'>Af</a></b></td>\n";
// } else {
//     print "<td title='".findtekst('1606|Klik her for at sortere på bogført af', $sprog_id)."' align='center'><b><a href='kladdeliste.php?sort=bogfort_af&vis=$vis'>".findtekst('638|Af', $sprog_id)."</a></b></td>\n";
// }

// print "<td align='center'><b>".findtekst('1099|Slet', $sprog_id)."</b></td>\n"; // delete column header added- 2025-10-18
// print "        </tr>\n";

// ################
// if ($vis=='alle') {
// 	print "<tr class='sticky-header-a'>";
// 	print "<td colspan=1 align=left></td>";
// 	print "<td colspan=4 align=center><a href=kladdeliste.php?sort=$sort&rf=$rf>".findtekst('641|Vis egne', $sprog_id)."</a></td>";
// 	print "<td colspan=2 align=right class='imgNoTextDeco'></td>";
// 	print "</tr>";
// }else {
// 	print "<tr class='sticky-header-a'><td colspan=6 align=center title='".findtekst('1601|Klik her for at se alle kladder', $sprog_id)."'><a href=kladdeliste.php?sort=$sort&rf=$rf&vis=alle id='visalle'>".findtekst('636|Vis alle', $sprog_id)."</a></td></tr>";}
// 	if ((!isset($linjebg))||($linjebg!=$bgcolor)) {$linjebg=$bgcolor; $color='#000000';
// }
// else {$linjebg=$bgcolor5; $color='#000000';}

// print "<tr class='table-row-hover' style='height: 50px;'><td colspan='7'></td></tr>";

// 	########search box 
// 	// print "<tr>";
// 	// print "<td colspan='7' style='text-align: left; padding: 5px;'>";
// 	// print "<input type='text' id='searchInput' name='search' value='" . htmlspecialchars($search, ENT_QUOTES) . "' placeholder='Søg i kladdeliste...' style='width: 300px; padding: 5px; font-size: 14px;' />";
// 	// print "</td>";
// 	// print "</tr>";
	
// 	// print '<div id="searchResults"></div>';
// 	##############
// $tjek=0;
// #$sqhost = "localhost";
// 	###########
// 	// Defaults
// 	$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
// 	$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
// 	$offset = ($page - 1) * $limit;

// 	// If "all" is selected
// 	if ($limit == -1) {
// 		$limitClause = ""; // No limit
// 	} else {
// 		$limitClause = "LIMIT $limit OFFSET $offset";
// 	}

// 	if ($vis == 'alle' ) $vis = ''; 
// 	else $vis="and oprettet_af = '".$brugernavn."'";
// 	$tidspkt=date("U");
// 	$encoded_vis = urlencode($vis);
// 	########
	
// 	########
// 	#$qtxt = "select * from kladdeliste where bogfort = '-' $vis order by $sort $rf";
// 	//#
// 	$countQuery = "SELECT COUNT(*) AS total FROM kladdeliste WHERE bogfort = '-' $vis";
// 	$countResult = db_select($countQuery, __FILE__ . " linje " . __LINE__);
// 	$countRow = db_fetch_array($countResult);
// 	$totalRows = $countRow['total'];
// 	$totalPages = ($limit > 0) ? ceil($totalRows / $limit) : 1;
	
// 	$qtxt = "SELECT * FROM kladdeliste WHERE bogfort = '-' $vis ORDER BY $sort $rf $limitClause";
// 	$totalRRows =0;
// 	if ($totalRows >0 && $vis !='') {
// 		$totalRRows  = $totalRows;
// 	}
// 	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	
// 	while ($row = db_fetch_array($query)) {
// 		$tjek++;
// 		$kladde="kladde".$row['id'];
// 		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
// 		else {$linjebg=$bgcolor5; $color='#000000';}
// 		print "<tr bgcolor=\"$linjebg\"  class='table-row-hover' >";
// 		if (strpos(' ',$row['tidspkt'])) list ($a,$b)=explode(" ",$row['tidspkt']);
// 		elseif ($row['tidspkt']) $b=$row['tidspkt'];
// 		else $b = 0;
// 			if ($tidspkt - trim(intval($b)) > 3600 || $row['hvem'] == $brugernavn) {
// 			if ($popup) print "<td onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kladde=window.open('kassekladde.php?tjek=$row[id]&kladde_id=$row[id]&returside=kladdeliste.php','$kladde','".$jsvars."');$kladde.focus();\"><span style=\"text-decoration: underline;\">$row[id]</a></span></td>";
// 			else print "<td><a href=kassekladde.php?tjek=$row[id]&kladde_id=$row[id]&returside=kladdeliste.php'>$row[id]</a></td>";
// 		}
// 		else {print "<td><span title= '".findtekst('1607|Kladde er låst af', $sprog_id)." $row[hvem]'>$row[id]</span></td>";}
// 		$kladdedato=dkdato($row['kladdedate']);
// 		print "<td>$kladdedato<br></td>";
// 		print "<td>".htmlentities(stripslashes($row['oprettet_af']),ENT_QUOTES,$charset)."<br></td>";
// 		print "<td>".htmlentities(stripslashes($row['kladdenote']),ENT_QUOTES,$charset)."<br></td>";
// 		print "<td align = center>$row[bogfort]<br></td>";
// 	    //  print "<td></td>";

// 		print "<td></td>";
 
// 		print "<td align='center'>";
// 		if ($row['bogfort'] == '-') {
// 			print "<button onclick=\"deleteKladde($row[id])\" style='
// 				background-color: #dc3545;
// 				color: white;
// 				border: none;
// 				padding: 6px 10px;
// 				border-radius: 4px;
// 				cursor: pointer;
// 				font-size: 11px;
// 				display: inline-flex;
// 				align-items: center;
// 				gap: 4px;
// 				transition: background-color 0.2s ease;
// 			' 
// 			onmouseover=\"this.style.backgroundColor='#c82333';\"
// 			onmouseout=\"this.style.backgroundColor='#dc3545';\"
// 			title='".findtekst('1099|Slet', $sprog_id)." kassekladde'>
// 			<i class='fa fa-trash-o' style='font-size: 12px;'></i>
// 			".findtekst('1099|Slet', $sprog_id)."
// 			</button>";
// 		}
// 		print "</td>";

// 		print "</tr>";
// 	}
// #	print "<tr><td colspan=6><hr></td></tr>";
// 	#$query = db_select("select * from kladdeliste where bogfort = '!' $vis order by $sort $rf",__FILE__ . " linje " . __LINE__);
// 	############  Get total number of rows
// 	$countQuery = "SELECT COUNT(*) AS total FROM kladdeliste WHERE bogfort = '!' $vis";
// 	$countResult = db_select($countQuery, __FILE__ . " linje " . __LINE__);
// 	$countRow = db_fetch_array($countResult);
// 	$totalRows = $countRow['total'];
// 	$totalPages = ($limit > 0) ? ceil($totalRows / $limit) : 1;

// 	############ 
// 	$qtxt = "SELECT * FROM kladdeliste WHERE bogfort = '!' $vis ORDER BY $sort $rf $limitClause";
// 	$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    
// 	while ($row = db_fetch_array($query)) {
// 		$kladde="kladde".$row[id];
// 		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
// 		else {$linjebg=$bgcolor5; $color='#000000';}
// 		print "<tr bgcolor=\"$linjebg\" class='table-row-hover'>";
// 		if (($tidspkt-($row[tidspkt])>3600)||($row[hvem]==$brugernavn)) {
// 		if ($popup) print "<td  onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kladde=window.open('kassekladde.php?kladde_id=$row[id]&returside=kladdeliste.php','$kladde','".$jsvars."');$kladde.focus();\"><span style=\"text-decoration: underline;\">$row[id]</a></span></td>";
// 		else print "<td><a href=kassekladde.php?tjek=$row[id]&kladde_id=$row[id]&returside=kladdeliste.php'>$row[id]</a></td>";
// 		}
// 		else {print "<td><span title= '".findtekst('1607|Kladde er låst af', $sprog_id)." $row[hvem]'>$row[id]</span></td>";}#		print "<tr>";
// #		print "<td> $row[id]<br></td>";
// 		$kladdedato=dkdato($row['kladdedate']);
// 		print "<td>$kladdedato<br></td>";
// 		print "<td>".htmlentities(stripslashes($row['oprettet_af']),ENT_QUOTES,$charset)."<br></td>";
// 		print "<td>".htmlentities(stripslashes($row['kladdenote']),ENT_QUOTES,$charset)."<br></td>";
// 		print "<td align = center>$row[bogfort]<br></td>";
// 		print "<td></td>";
// 		print "<td align='center'>";
// 		if ($row['bogfort'] == '-') {
// 			print "<button onclick=\"deleteKladde($row[id])\" style='
// 				background-color: #dc3545;
// 				color: white;
// 				border: none;
// 				padding: 6px 10px;
// 				border-radius: 4px;
// 				cursor: pointer;
// 				font-size: 11px;
// 				display: inline-flex;
// 				align-items: center;
// 				gap: 4px;
// 				transition: background-color 0.2s ease;
// 			' 
// 			onmouseover=\"this.style.backgroundColor='#c82333';\"
// 			onmouseout=\"this.style.backgroundColor='#dc3545';\"
// 			title='".findtekst('1099|Slet', $sprog_id)." kassekladde'>
// 			<i class='fa fa-trash-o' style='font-size: 12px;'></i>
// 			".findtekst('1099|Slet', $sprog_id)."
// 			</button>";
// 		}
// print "</td>";
//         print "</td>";
// 		print "</tr>";
// 	}
// 	#startUse?
// 	#$query = db_select("select * from kladdeliste where bogfort = 'S' $vis order by $sort $rf",__FILE__ . " linje " . __LINE__);
// 	############
// 	$countQuery = "SELECT COUNT(*) AS total FROM kladdeliste WHERE bogfort = 'S' $vis";
// 	$countResult = db_select($countQuery, __FILE__ . " linje " . __LINE__);
// 	$countRow = db_fetch_array($countResult);
// 	$totalRows = $countRow['total'];
// 	$totalPages = ($limit > 0) ? ceil($totalRows / $limit) : 1;
// 	###########
	
// 	$qtxt = "SELECT * FROM kladdeliste WHERE bogfort = 'S' $vis ORDER BY $sort $rf $limitClause";
// 	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	
// 	$hr=$tjek;
// 	while ($row = db_fetch_array($query)){
// 		if ($hr==$tjek) {
// 			// print "<tr><td colspan=\"2\" align=\"center\"><b>".findtekst('1089|Simulerede kladder', $sprog_id)."</b></td><td colspan=\"4\"><hr></td></tr>";
// 			print "<tr><td colspan=\"2\" align=\"center\"><b>".findtekst('1089|Simulerede kladder', $sprog_id)."</b></td><td colspan=\"5\"><hr></td></tr>";
// 		}
// 		$tjek++;
// 		$kladde="kladde".$row['id'];
// 		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
// 		else {$linjebg=$bgcolor5; $color='#000000';}
// 		print "<tr bgcolor=\"$linjebg\" class='table-row-hover'>";
// 		if ($popup) print "<td  onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kladde=window.open('kassekladde.php?kladde_id=$row[id]&tjek=$row[id]&returside=kladdeliste.php','$kladde','".$jsvars."');$kladde.focus();\"><span style=\"text-decoration: underline;\">$row[id]</a></span></td>";
// 		else print "<td><a href=kassekladde.php?kladde_id=$row[id]&tjek=$row[id]&returside=kladdeliste.php>$row[id]</a><br></td>";
// 		$kladdedato=dkdato($row['kladdedate']);
// 		print "<td>$kladdedato<br></td>";
// 		print "<td>".htmlentities(stripslashes($row['oprettet_af']),ENT_QUOTES,$charset)."<br></td>";
// 		print "<td>".htmlentities(stripslashes($row['kladdenote']),ENT_QUOTES,$charset)."<br></td>";
// 		print "<td></td>";

// ## Da der ikke blev sat bogfringsdato foer ver. 0.23 skal det saettes hak ved kladder bogfrt fr denne version...
// 		if ($row['bogforingsdate']){
// 			$bogforingsdato=dkdato($row['bogforingsdate']);
// 			print "<td align = center>$bogforingsdato<br></td>";
// 		}
// 		else {print "<td align = center>$row[bogfort]<br></td>";}
// 		print "<td>$row[bogfort_af]<br></td>";
//         print "<td></td>"; 

// 		print "</tr>";
// 	}
// 	#$query = db_select("select * from kladdeliste where bogfort = 'V' $vis order by $sort $rf",__FILE__ . " linje " . __LINE__);
	
// 	#########
// 	$countQuery = "SELECT COUNT(*) AS total FROM kladdeliste WHERE bogfort = 'V' $vis";
// 	$countResult = db_select($countQuery, __FILE__ . " linje " . __LINE__);
// 	$countRow = db_fetch_array($countResult);
// 	$totalRows = $countRow['total'];
// 	$totalPages = ($limit > 0) ? ceil($totalRows / $limit) : 1;


// 	$qtxt = "SELECT * FROM kladdeliste WHERE bogfort = 'V' $vis ORDER BY $sort $rf $limitClause";
// 	$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);

// 	#######
// 	$hr=$tjek;
// 	while ($row = db_fetch_array($query)){
// 		if ($hr==$tjek) {
// 			print "<tr><td colspan=\"2\" align=\"center\"><b>".findtekst('1093|Bogførte kladder', $sprog_id)."</b></td><td colspan=\"5\"><hr></td></tr>";
// 		}
// 		$tjek++;
// 		$kladde="kladde".$row['id'];
// 		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
// 		else {$linjebg=$bgcolor5; $color='#000000';}
// 		print "<tr bgcolor=\"$linjebg\" class='table-row-hover'>";
// 		if ($popup) print "<td  onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kladde=window.open('kassekladde.php?kladde_id=$row[id]&returside=kladdeliste.php','$kladde','".$jsvars."');$kladde.focus();\"><span style=\"text-decoration: underline;\">$row[id]</a></span></td>";
// 		else print "<td><a href=kassekladde.php?kladde_id=$row[id]&returside=kladdeliste.php>$row[id]</a><br></td>";
// 		$kladdedato=dkdato($row['kladdedate']);
// 		print "<td>$kladdedato<br></td>";
// 		print "<td>".htmlentities(stripslashes($row['oprettet_af']),ENT_QUOTES,$charset)."<br></td>";
// 		print "<td>".htmlentities(stripslashes($row['kladdenote']),ENT_QUOTES,$charset)."<br></td>";
// ## Da der ikke blev sat bogfringsdato foer ver. 0.23 skal det saettes hak ved kladder bogfrt fr denne version...
// 		if ($row['bogforingsdate']){
// 			$bogforingsdato=dkdato($row['bogforingsdate']);
// 			print "<td align = center>$bogforingsdato<br></td>";
// 		}
// 		else {print "<td align = center>$row[bogfort]<br></td>";}
// 		print "<td>$row[bogfort_af]<br></td>";
//         print "<td></td>";

// 		print "</tr>"; 
// 	}
// 	######enduse
// 	if ($menu=='T') {
// 		$newbutton= "<i class='fa fa-plus-square fa-lg'></i>";
// 	} else {
// 		$newbutton= "<u>".findtekst('39|Ny', $sprog_id)."</u>";
// 	}
// 	if (!$tjek) {
// 		print "<tr><td colspan=5 height=25> </td></tr>"; 
// 		print "<tr><td colspan=3 align=right>TIP 1: </td><td>".findtekst('640|Du opretter en ny kassekladde ved at klikke på', $sprog_id)." $newbutton ".findtekst('642|øverst til højre', $sprog_id).".</td></tr>"; 
// 		if (db_fetch_array(db_select("select * from kladdeliste",__FILE__ . " linje " . __LINE__))) {
// 			print "<tr><td colspan=3 align=right>TIP 2: </td><td>".findtekst('597|Du kan se dine kollegers kladder ved at klikke på', $sprog_id)." <u>".findtekst('636|Vis alle', $sprog_id)."</u>.</td></tr>"; 
// 		}
// 	}

// ################



// // Start by printing the styles
// print "
// <style>
//   #fixedFooter {
//     position: fixed;
//     bottom: 0;
//     left: 0;
//     width: 100%;
//     background: #f0f0f0;
//     border-top: 1px solid #ccc;
//     padding: 10px 10px;
//     display: flex;
//     align-items: center;
//     justify-content: space-between;
//     font-weight: bold;
//     z-index: 1000;
//   }
//   #fixedFooter .left, #fixedFooter .center, #fixedFooter .right {
//     display: flex;
//     align-items: center;
//   }
//   #fixedFooter .left a, #fixedFooter .left span.disabled {
//     margin-right: 10px;
//     text-decoration: none;
//     color: #0a0a0aff;
//   }
//   #fixedFooter .left span.disabled {
//     color: #6e6565ff;
//   }
//   #fixedFooter .center {
//     flex: 1;
//     justify-content: center;
//     text-align: center;
//   }
//   #fixedFooter form {
//     margin: 0;
//   }
//   #fixedFooter select {
//     padding: 4px 8px;
//     font-weight: normal;
//   }
//   body {
//     padding-bottom: 70px; /* prevent content hidden behind footer */
//   }
// </style>

// <div id='fixedFooter'>

//   <div class='left'>";

// if ($page > 1) {
//     print "<a href='?page=" . ($page - 1) . "&limit=$limit'>Previous</a>";
// } else {
//     print "<span class='disabled'>Previous</span>";
// }

// if ($page < $totalPages && $limit != -1) {
//     print "<a href='?page=" . ($page + 1) . "&limit=$limit'>Next</a>";
// } else {
//     print "<span class='disabled'>Next</span>";
// }
// if($totalRRows !=0) {
// 	//totalRows is the same as rows returned
// 	$totalRows=$totalRRows;
// }
// print "</div>

//   <div class='center'>
//     Page $page of $totalPages | Rows per page: $limit | Total records: $totalRows
//   </div>

//   <div class='right' style='padding-right: 25px;'>
//     <form method='GET' action='kladdeliste.php' id='limitForm'>
//       <label for='limit'>Vis:</label>
//       <select name='limit' id='limit' onchange='document.getElementById(\"limitForm\").submit()'>
//         <option value='50'" . ($limit == 50 ? " selected" : "") . ">50</option>
//         <option value='100'" . ($limit == 100 ? " selected" : "") . ">100</option>
//         <option value='200'" . ($limit == 200 ? " selected" : "") . ">200</option>
//         <option value='-1'" . ($limit == -1 ? " selected" : "") . ">Alle</option>
//       </select>
//       <input type='hidden' name='page' value='" . htmlspecialchars($page) . "'>
//     </form>
//   </div>


// </div>";


// print "</div>";


// ##############




// $steps = array();
// $steps[] = array(
// 	"selector" => "#ny",
// 	"content" => findtekst('2607|Opret ny kassekladde ved at klikke her', $sprog_id).".",
// );
// $steps[] = array(
// 	"selector" => "[name=digital]",
// 	"content" => findtekst('2608|Digital godkendelse af fakturaer gennem NemHandel', $sprog_id).".",
// );
// $steps[] = array(
// 	"selector" => "#visalle",
// 	"content" => findtekst('2609|Du kan se dine kollegers kladder ved at klikke her', $sprog_id).".",
// );


// print("<script>
// function deleteKladde(kladdeId) {
//     if (confirm(\"".findtekst('155|Vil du slette denne ordre?', $sprog_id)."\")) {
//         // Create a form and submit it
//         var form = document.createElement(\"form\");
//         form.method = \"POST\";
//         form.action = \"kladdeliste.php\";

//         var input = document.createElement(\"input\");
//         input.type = \"hidden\";
//         input.name = \"delete_kladde\";
//         input.value = kladdeId;
        
//         form.appendChild(input);
//         document.body.appendChild(form);
//         form.submit();
//     }
// }
// </script>");


// include(__DIR__ . "/../includes/tutorial.php");
// create_tutorial("kladlist", $steps);

// print <<<HTML

// <style>
// .row-clickable:hover td {
//   background-color: #f9f9f9;
//   cursor: pointer;
// }

// .row-clickable:hover {
//   outline: 2px solid #b2b2b2;
// }

// /* tr.header-row,
// tr.header-row td {
//   cursor: default !important;
// }
// */
// tr.header-row:hover td {
//   cursor: default !important;
// } 

// tr.nav-row,
// tr.nav-row:hover,
// tr.nav-row:hover td {
//   background-color: inherit !important;
//   cursor: default !important;
// }

// /* Button styling */
// .table-row-hover button,
// .hover-highlight button {
//   color: white !important;
//   border: none !important;
//   padding: 6px 10px !important;
//   border-radius: 4px !important;
//   cursor: pointer !important;
//   font-size: 11px !important;
//   display: inline-flex !important;
//   align-items: center !important;
//   gap: 4px !important;
//   transition: background-color 0.2s ease !important;
// }

// /* Ensure delete button maintains its own cursor */
// .row-clickable:hover td:last-child button {
//   cursor: pointer !important;
// }

// </style>


// <script>
// document.addEventListener('DOMContentLoaded', function () {
 

//   const headerRgbColors = [
//     'rgb(17, 70, 145)', 
//     'rgba(17, 70, 145, 1)'
//   ];

//   document.querySelectorAll('table tr').forEach(row => { 
//     const tds = Array.from(row.querySelectorAll('td'));
//     if (tds.length <= 1) return;
	
//     let isHeaderRow = false;

//     for (const td of tds) {
//       const inline = (td.getAttribute('style') || '').toLowerCase();
//       if (inline.includes('background-color') || inline.includes('border-radius')) {
//         isHeaderRow = true;
//         break;
//       }
//       const cs = window.getComputedStyle(td);
//       if (cs && cs.backgroundColor) {
//         const bg = cs.backgroundColor.replace(/\s+/g,'').toLowerCase();
//         for (const c of headerRgbColors) {
//           if (bg === c.replace(/\s+/g,'').toLowerCase()) {
//             isHeaderRow = true;
//             break;
//           }
//         }
//         if (isHeaderRow) break;
//       }
//     }

//     if (isHeaderRow) {
//       row.classList.add('header-row');
//       row.classList.remove('row-clickable', 'table-row-hover', 'hover-highlight');
//       row.dataset.saldiHeader = '1';
//       tds.forEach(td => {
//         td.style.cursor = 'default';
//       });
//     }
//   });

//   document.querySelectorAll('table tr').forEach(row => {
//     const tds = row.querySelectorAll('td');
//     if (tds.length <= 1) return;

//     if (row.dataset.saldiHeader === '1' || row.classList.contains('header-row')) {
//       row.style.cursor = 'default';
//       return;
//     }

//     if (row.offsetParent === null) return;

//     let targetHref = null;
//     const a = row.querySelector('a[href*="kassekladde.php"]');
//     if (a) {
//       const anchorInNestedTable = !!a.closest('table') && a.closest('table') !== row.closest('table');
//       if (!anchorInNestedTable) targetHref = a.getAttribute('href');
//       else return;
//     }

//     if (!targetHref) {
//       const onclickTd = Array.from(tds).find(td => td.getAttribute('onClick') || td.getAttribute('onclick'));
//       if (onclickTd) {
//         const onclick = onclickTd.getAttribute('onClick') || onclickTd.getAttribute('onclick') || '';
//         const match = onclick.match(/['"]([^'"]*kassekladde\.php[^'"]*)['"]/i);
//         if (match && match[1]) targetHref = match[1];
//       }
//     }

//     if (!targetHref) return;

//     try { targetHref = (new URL(targetHref, window.location.href)).href; } catch (e) {}

//     row.classList.add('row-clickable');
//     row.style.cursor = 'pointer';

//     row.addEventListener('click', function (ev) {
//       if (ev.target.closest('button') || ev.target.closest('a')) return;
//       window.location.href = targetHref;
//     });
//   });
// });

// </script>
// HTML;
// ?>





