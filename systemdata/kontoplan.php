<?php
// -----------systemdata/kontoplan.php-----patch 5.0.0 ----2026-02-04-------
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
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20160116     Tilføjet valuta  
// 20160129	    Valutakode og kurs blev ikke sat ved oprettelse af ny driftskonti.
// 20210707 LOE Translated these texts with findtekst function 
// 20220607 MSC Implementing new design
// 20260204 LOE Updated design with grid design format

@session_start();
$s_id=session_id();
$title="Kontoplan";
$css="../css/standard.css";
$modulnr="0";
$linjebg='';
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/grid.php");
include("../includes/topline_settings.php");
include("../includes/row-hover-style.js.php");

$backUrl = isset($_GET['returside'])
? $_GET['returside']
: '../index/menu.php';
if ($popup) $returside="../includes/luk.php";
else $returside=$backUrl;

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
	print "<div id=\"leftmenuholder\">\n";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"rightContent\">\n";
} elseif ($menu=='S') {
	print "<div align=\"center\">";
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height=\"25\" align=\"center\" valign=\"top\">";
	print "<table id=\"topHeader\" width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";

	print "<td width=\"10%\" align=\"left\"><a href=\"#\" onclick=\"return false;\"><button style='$buttonStyle; width:100%' >&nbsp;</button></a></td>";
	print "<td width=\"80%\" style='$topStyle' align=\"center\">".findtekst(113, $sprog_id)."</td>";

	print "<td width=\"10%\" align=\"right\"><a href=kontokort.php accesskey=N>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst(39,$sprog_id)."</button></a></td>";

	print "</tbody></table>";
	print "</td></tr>";
	print "<tr><td valign=\"top\">";
} else {
	print "<div align=\"center\">";
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height=\"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund align=\"left\"><a href=$returside accesskey=L>".findtekst(30,$sprog_id)."</a></td>";
	print "<td width=\"80%\" $top_bund align=\"center\">".findtekst(113,$sprog_id)."</td>";
	print "<td width=\"10%\" $top_bund align=\"right\"><a href=kontokort.php accesskey=N>".findtekst(39,$sprog_id)."</a></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<tr><td valign=\"top\">";
}

// Prepare valuta lookup arrays
$valutakode[0] = 0;
$valutanavn[0] = 'DKK';
$x = 1;	
$q = db_select("select kodenr, box1 from grupper where art='VK' order by kodenr", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$valutakode[$x] = $r['kodenr'];
	$valutanavn[$x] = $r['box1'];
	$x++;
}

if (!$regnaar) {
	$regnaar = 1;
}


// GRID CONFIGURATION FOR KONTOPLAN


$grid_data = [
	'query' => "
		SELECT 
			k.id,
			k.kontonr,
			k.beskrivelse,
			k.kontotype,
			k.fra_kto,
			k.til_kto,
			k.moms,
			k.saldo,
			k.valuta,
			k.valutakurs,
			k.genvej,
			k.map_to,
			k.lukket
		FROM kontoplan k
		WHERE k.regnskabsaar = '$regnaar'
		AND {{WHERE}}
		ORDER BY {{SORT}}
	",
	'columns' => [
		[
			'field' => 'kontonr',
			'headerName' => findtekst(43, $sprog_id), // "Kontonr"
			'type' => 'text',
			'width' => '1',
			'align' => 'left',
			'sortable' => true,
			'searchable' => true,
			'defaultSort' => true,
			'defaultSortDirection' => 'asc',
			 "sqlOverride" => "k.kontonr",
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
			'render' => function($value, $row, $column) {
				return "<td align='{$column['align']}'><a href='kontokort.php?id={$row['id']}'>{$value}</a></td>";
			}
		],
		[
			'field' => 'beskrivelse',
			'headerName' => findtekst(805, $sprog_id), // "Beskrivelse"
			'type' => 'text',
			'width' => '3',
			'align' => 'left',
			'sortable' => true,
			'searchable' => true,
			'valueGetter' => function($value, $row, $column) {
				if ($row['lukket'] == 'on') {
					return "Lukket ! - " . stripslashes($value);
				}
				return stripslashes($value);
			}
		],
		[
			'field' => 'kontotype',
			'headerName' => 'Type',
			'type' => 'text',
			'width' => '1.5',
			'align' => 'left',
			'sortable' => true,
			'searchable' => true,
			'valueGetter' => function($value, $row, $column) {
				switch($value) {
					case 'H': return '';
					case 'D': return 'Drift';
					case 'S': return 'Status';
					case 'Z': return "Sum {$row['fra_kto']} - {$row['til_kto']}";
					case 'R': return "Resultat = {$row['fra_kto']}";
					default: return 'Sideskift';
				}
			},
			'generateSearch' => function($column, $term) {
				$field = $column['sqlOverride'] ? $column['sqlOverride'] : $column['field'];
				$term = strtolower(trim(db_escape_string($term)));
				
				if (empty($term)) {
					return "1=1";
				}
				
				// Map search terms to database values
				$conditions = [];
				
				if (stripos('drift', $term) !== false) {
					$conditions[] = "$field = 'D'";
				}
				if (stripos('status', $term) !== false) {
					$conditions[] = "$field = 'S'";
				}
				if (stripos('sum', $term) !== false) {
					$conditions[] = "$field = 'Z'";
				}
				if (stripos('resultat', $term) !== false) {
					$conditions[] = "$field = 'R'";
				}
				if (stripos('sideskift', $term) !== false) {
					$conditions[] = "$field NOT IN ('H', 'D', 'S', 'Z', 'R')";
				}
				
				// If no conditions matched, search in the raw field as fallback
				if (empty($conditions)) {
					return "$field ILIKE '%$term%'";
				}
				
				return '(' . implode(' OR ', $conditions) . ')';
			}
		],
		[
			'field' => 'moms',
			'headerName' => findtekst(770, $sprog_id), // "Moms"
			'type' => 'text',
			'width' => '1',
			'align' => 'center',
			'sortable' => true,
			'searchable' => true
		],
		[
			'field' => 'saldo',
			'headerName' => findtekst(1073, $sprog_id), // "Saldo"
			'type' => 'number',
			'width' => '1.5',
			'align' => 'right',
			'sortable' => true,
			'searchable' => true,
			'decimalPrecision' => 2,
			'valueGetter' => function($value, $row, $column) {
				if ($row['kontotype'] == 'H' || $row['kontotype'] == 'X') {
					return '';
				}
				if ($row['valutakurs']) {
					return dkdecimal($value * 100 / $row['valutakurs'], 2);
				}
				return dkdecimal($value, 2);
			},
			'render' => function($value, $row, $column) {
				if ($row['kontotype'] == 'H' || $row['kontotype'] == 'X') {
					return "<td></td>";
				}
				$dkkValue = dkdecimal($row['saldo'] * 1, 2);
				return "<td align='right' title='DKK {$dkkValue}'>{$value}</td>";
			}
		],
		[
			'field' => 'valuta',
			'headerName' => findtekst(1069, $sprog_id), // "Valuta"
			'type' => 'text',
			'width' => '1',
			'align' => 'center',
			'sortable' => true,
			'searchable' => false,
			'valueGetter' => function($value, $row, $column) {
				global $valutanavn;
				return $valutanavn[$value];
			}
		],
		[
			'field' => 'genvej',
			'headerName' => findtekst(1191, $sprog_id), // "Genvej"
			'type' => 'text',
			'width' => '1',
			'align' => 'center',
			'sortable' => true,
			'searchable' => true
		],
		[
			'field' => 'map_to',
			'headerName' => 'Map til',
			'type' => 'text',
			'width' => '1',
			'align' => 'center',
			'sortable' => true,
			'searchable' => true,
			'valueGetter' => function($value, $row, $column) {
				return $value ? $value : '';
			}
		],
		[
			'field' => 'print_column',
			'headerName' => '', // Empty header for print button column
			'type' => 'text',
			'width' => '0.5', // Half the width of a regular column
			'align' => 'center',
			'sortable' => false,
			'searchable' => false,
			'render' => function($value, $row, $column) {
				return "<td></td>"; // Empty cell for data rows
			}
		]
	],
	'filters' => [],
	'rowStyle' => function($row) {
		global $bgcolor, $bgcolor4, $bgcolor5;
		static $linjebg = null;
		
		if ($row['kontotype'] == 'H') {
			return "background-color: $bgcolor4;";
		}
		
		if ($linjebg != $bgcolor) {
			$linjebg = $bgcolor;
		} elseif ($linjebg != $bgcolor5) {
			$linjebg = $bgcolor5;
		}
		
		return "background-color: $linjebg;";
	}
];

// Create the datagrid
create_datagrid('kontoplan', $grid_data);

// Add custom print button CSS and script
print <<<PRINTBUTTON
<style>
	
	.print-button svg {
		height: 20px;
	}
	
	/* Position the button in the thead */
	.datatable thead {
		position: relative;
	}
	
	@media print {


		* {
			-webkit-print-color-adjust: exact !important; 
			print-color-adjust: exact !important;
			color-adjust: exact !important;
		}
    
		/* Ensure table cell backgrounds print */
		.datatable tbody tr,
		.datatable tbody td,
		.datatable thead tr,
		.datatable thead th {
			-webkit-print-color-adjust: exact !important;
			print-color-adjust: exact !important;
			color-adjust: exact !important;
		}

		.print-button,
		.dropdown,
		.datatable thead tr:nth-child(2), /* Hide search row */
		.datatable tfoot {
			display: none !important;
		}
		
		.datatable-wrapper {
			overflow: visible !important;
			height: auto !important;
		}
		
		.datatable tbody tr.filler-row {
			display: none !important;
		}

		#topHeader {
			display: none;
		}
	}


/* Hide dropdown if not needed */
.dropdown {
    display: none !important;
}

/* Fix sticky header  */
#datatable-kontoplan thead {
    position: sticky !important;
    top: 0 !important;
    z-index: 100 !important;
}


/* Print button with hover effect */
.print-button {
    background: none;
    border: none;
    padding: 5px;
    cursor: pointer;
    display: flex;
    align-items: center;
    position: absolute;
    right: 10px;
    top: 5px;
    z-index: 10;
}
.print-button:hover {
    background-color: #f1f1f1;
    border-radius: 1px;
}



</style>

<script>
	document.addEventListener("DOMContentLoaded", function() {
		// Add print button to thead
		const thead = document.querySelector('#datatable-kontoplan thead');
		if (thead) {
			const printBtn = document.createElement('button');
			printBtn.className = 'print-button';
			printBtn.type = 'button';
			printBtn.title = 'Print';
			printBtn.onclick = function() {
				window.print();
			};
			printBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M640-640v-120H320v120h-80v-200h480v200h-80Zm-480 80h640-640Zm560 100q17 0 28.5-11.5T760-500q0-17-11.5-28.5T720-540q-17 0-28.5 11.5T680-500q0 17 11.5 28.5T720-460Zm-80 260v-160H320v160h320Zm80 80H240v-160H80v-240q0-51 35-85.5t85-34.5h560q51 0 85.5 34.5T880-520v240H720v160Zm80-240v-160q0-17-11.5-28.5T760-560H200q-17 0-28.5 11.5T160-520v160h80v-80h480v80h80Z"/></svg>';
			
			// Insert as first child of thead
			const firstRow = thead.querySelector('tr');
			if (firstRow) {
				const th = firstRow.querySelector('th:last-child');
				if (th) {
					th.style.position = 'relative';
					th.appendChild(printBtn);
				}
			}
		}
	});
</script>
PRINTBUTTON;

if (!$menu == 'T') {
	print "</td></tr>";
	print "</tbody></table>";
} else {
	print "</div>";
}

if ($menu == 'T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

