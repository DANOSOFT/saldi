<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------kreditor/kreditor.php---lap 4.0.8------2025-04-15----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 2018.03.08 Indhold kopieret dra debitor/debitor.php og tilrettet til kreditor
// 20210331 LOE Translated some of these texts to English
// 20210705 LOE Created switch case function for box6 to translate langue and also reassigned valg variable for creditor
// 20230323 PBLM Fixed minor errors
// 20230522 PHR php8
// 01072025 PBLM Added openKreditorKort function to open creditor card in same window
// 20251127 LOE Modified to use grid table structure. 
// 20260506 sawaneh fixed not directing to new account creation when the look for that account does not exist
// 20261008 Sawaneh Settings 4e: the old top-menu ($menu=='T') branches removed; the else legs stay (spec §7.6 R3).
// 20261008 Sawaneh The list on the new page head (page_bar): title, Kreditorer tab, Ny kreditor as the primary button.

#ob_start();
@session_start();
$s_id = session_id();

global $menu;

$check_all = $ny_sort = $skjul_lukkede = NULL;
$dg_id = $dg_liste = $dg_navn = $find = $selectfelter = array();

print "
<script LANGUAGE=\"JavaScript\">
<!--
function MasseFakt(tekst)
{
	var agree = confirm(tekst);
	if (agree)
		return true ;
	else
    return false ;
}
function openKreditorKort(kreditorId) {
    // Open creditor card in same window
    window.location.href = 'kreditorkort.php?id=' + kreditorId + '&returside=kreditor.php';
}
// -->
</script>
";
$css = "../css/standard.css";
$modulnr = 6;
$firmanavn = NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");
include("../includes/topline_settings.php");
include("../includes/row-hover-style.js.php");
include("../includes/grid.php"); // Include the datagrid system
include("../includes/license_func.php");

// Check if kreditor feature is licensed
if (!is_feature_licensed('kreditor')) {
	show_upgrade_message('Kreditor');
	exit;
}

$title = "Kreditorliste";

$id = if_isset($_GET,NULL,'id');
$returside = if_isset($_GET,NULL,'returside');
$valg = strtolower(if_isset($_GET,NULL,'valg'));
$kreditor1 = lcfirst(findtekst(1169, $sprog_id));
$brisk1 = findtekst(944, $sprog_id);

$aa = findtekst(360, $sprog_id);
$firmanavn = ucfirst(str_replace(' ', '_', $aa));

if (!$valg) $valg = "$kreditor1";

$backUrl = isset($_GET['returside'])
	? $_GET['returside']
	: '../index/menu.php';

if ($popup) $returside = "../includes/luk.php";
else $returside = $backUrl;

// Top menu rendering
if ($menu == 'S') {
	// 20261008 Sawaneh The new page head (topbar addendum §5, prototype_dashboard_tema v5) instead of the blue bar.
	include_once("../includes/stdFunc/pageBar.php");
	page_bar(array(
		'title' => findtekst('607|Kreditorer', $sprog_id),
		'tabs' => array(array(findtekst('607|Kreditorer', $sprog_id), 'kreditor.php', true)),
		'primary' => array(findtekst('7002|Ny kreditor', $sprog_id), 'kreditorkort.php?returside=kreditor.php'),
		'back' => $returside,
	));
	print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>\n";
	print "<tr><td align=\"center\" valign=\"top\" width=\"100%\">";
	?>
	<style>
	a:link{
		text-decoration: none;
	}
	</style>
	<?php
} else {
	print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>\n";
	print "<tr><td height = 25 align=center valign=top>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>\n";
	print "<tr><td width=10% $top_bund><a href=$returside accesskey=L>" . findtekst(30, $sprog_id) . "</a></td>";
	print "<td width = 80% align=center $top_bund>" . findtekst(607, $sprog_id) . "</td>";
	print "<td width=5% $top_bund><a accesskey=V href=kreditorvisning.php?valg=$valg>" . findtekst(813, $sprog_id) . "</a></td>\n";
	print "<td width=5%  $top_bund><a href=kreditorkort.php?returside=kreditor.php>" . findtekst(39, $sprog_id) . "</a></td></tr>\n";
	print "</tbody></table>";
	print " </td></tr>\n<tr><td align=\"center\" valign=\"top\" width=\"100%\">";
}

############

#############


// Get all available employee initials for the dropdown
function getEmployeeInitials() {
    $initials = array();
    $q = db_select("SELECT DISTINCT(ansatte.initialer) as initialer 
                    FROM ansatte, adresser 
                    WHERE adresser.art='S' AND ansatte.konto_id=adresser.id 
                    ORDER BY ansatte.initialer", __FILE__ . " line " . __LINE__);
    while ($r = db_fetch_array($q)) {
        if ($r['initialer']) {
            $initials[] = $r['initialer'];
        }
    }
    return $initials;
}

// Get all employee names for contact dropdown
function getEmployeeNames() {
    $names = array();
    $q = db_select("SELECT DISTINCT(ansatte.navn) as navn 
                    FROM ansatte, adresser 
                    WHERE adresser.art='S' AND ansatte.konto_id=adresser.id 
                    ORDER BY ansatte.navn", __FILE__ . " line " . __LINE__);
    while ($r = db_fetch_array($q)) {
        if ($r['navn']) {
            $names[] = $r['navn'];
        }
    }
    return $names;
}


// Reset offset to 0 when a search is performed
if (isset($_GET['search']['kreditor_list'])) {
    $hasSearchTerms = false;
    foreach ($_GET['search']['kreditor_list'] as $term) {
        if (!empty(trim($term))) {
            $hasSearchTerms = true;
            break;
        }
    }
    
    if ($hasSearchTerms && (!isset($_GET['offset']['kreditor_list']) || $_GET['offset']['kreditor_list'] != 0)) {
        $_GET['offset']['kreditor_list'] = 0;
    }
}


// Define the datagrid configuration


// Get status options
function getStatusOptions() {
    $statuses = array();
    $r = db_fetch_array(db_select("SELECT box3, box4 FROM grupper WHERE art='KredInfo'", __FILE__ . " line " . __LINE__));
    if ($r) {
        $status_id = explode(chr(9), $r['box3']);
        $status_beskrivelse = explode(chr(9), $r['box4']);
        for ($i = 0; $i < count($status_id); $i++) {
            if ($status_beskrivelse[$i]) {
                $statuses[] = $status_beskrivelse[$i];
            }
        }
    }
    return $statuses;
}

// Define the datagrid configuration
$grid_data = array(
    'query' => "
        SELECT 
            adresser.id,
            adresser.kontonr,
            adresser.firmanavn,
            adresser.addr1,
            adresser.addr2,
            adresser.postnr,
            adresser.bynavn,
            adresser.kontakt,
            adresser.tlf,
            adresser.email,
            adresser.cvrnr,
            adresser.gruppe,
            adresser.lukket,
            adresser.kontoansvarlig,
            (SELECT initialer FROM ansatte WHERE id = adresser.kontoansvarlig LIMIT 1) as ansvarlig_initialer
        FROM adresser
        WHERE adresser.art = 'K' AND ({{WHERE}})
        ORDER BY {{SORT}}
    ",
    'columns' => array(
        array(
            'field' => 'kontonr',
            'headerName' => findtekst(284, $sprog_id), // Account number
            'type' => 'text',
            'width' => '0.5',
            'align' => 'right',
            'sortable' => true,
            'searchable' => true,
            'defaultSort' => true,
            'defaultSortDirection' => 'asc'
        ),
        array(
            'field' => 'firmanavn',
            'headerName' => findtekst(360, $sprog_id), // Company name
            'type' => 'text',
            'width' => '2',
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ),
        array(
            'field' => 'addr1',
            'headerName' => 'Adresse',
            'type' => 'text',
            'width' => '1.5',
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ),
        array(
            'field' => 'postnr',
            'headerName' => findtekst(144, $sprog_id), // Postal code
            'type' => 'text',
            'width' => '0.7',
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ),
        array(
            'field' => 'bynavn',
            'headerName' => 'By',
            'type' => 'text',
            'width' => '1',
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ),
        array(
            'field' => 'kontakt',
            'headerName' => findtekst(502, $sprog_id), // Contact
            'type' => 'dropdown',
            'width' => '1',
            'align' => 'left',
            'sortable' => true,
            'searchable' => true,
            'dropdownOptions' => function() {
                return getEmployeeNames();
            },
			'generateSearch' => function($column, $term) {
				$term = db_escape_string($term);
				return "adresser.kontakt ILIKE '%$term%'"; // Search in the actual kontakt field
			}
        ),
        array(
            'field' => 'tlf',
            'headerName' => findtekst(37, $sprog_id), // Phone
            'type' => 'text',
            'width' => '1',
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ),
        array(
            'field' => 'email',
            'headerName' => 'Email',
            'type' => 'text',
            'width' => '1.5',
            'align' => 'left',
            'sortable' => true,
            'searchable' => true
        ),
        array(
            'field' => 'ansvarlig_initialer',
            'headerName' => 'Ansvarlig',
            'type' => 'dropdown',
            'width' => '0.7',
            'align' => 'left',
            'sortable' => true,
            'searchable' => true,
            'sqlOverride' => 'adresser.kontoansvarlig',
            'dropdownOptions' => function() {
                return getEmployeeInitials();
            },
			 'generateSearch' => function($column, $term) {
				$field = $column['sqlOverride'] == '' ? $column['field'] : $column['sqlOverride'];
				$term = db_escape_string($term);
				return "{$field} ILIKE '%$term%'";
			}
        )
    ),
    'filters' => array(
        array(
            'filterName' => 'Status',
            'joinOperator' => 'OR',
            'options' => array(
                array(
                    'name' => 'Aktive',
                    'checked' => 'checked',
                    'sqlOn' => "(adresser.lukket IS NULL OR adresser.lukket != 'on')",
                    'sqlOff' => ""
                ),
                array(
                    'name' => 'Lukkede',
                    'checked' => '',
                    'sqlOn' => "adresser.lukket = 'on'",
                    'sqlOff' => ""
                )
            )
        )
    ),
    'rowStyle' => function($row) {
        // Add styling for closed accounts
        if ($row['lukket'] == 'on') {
            return 'opacity: 0.6;';
        }
        return '';
    },
    'metaColumn' => function($row) {
        // Add data attribute for row click handling
        return "<td style='display:none;' data-kreditor-id='{$row['id']}'></td>";
    }
);

// Create the datagrid
$rows = create_datagrid('kreditor_list', $grid_data);

// Add click handler for rows
print "<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.querySelector('#datatable-kreditor_list tbody');
    if (tbody) {
        tbody.addEventListener('click', function(e) {
            // Don't interfere with other interactive elements
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'BUTTON') {
                return;
            }
            
            const row = e.target.closest('tr');
            if (row && row.parentElement.tagName === 'TBODY' && !row.classList.contains('filler-row')) {
                const kreditorCell = row.querySelector('[data-kreditor-id]');
                if (kreditorCell) {
                    const kreditorId = kreditorCell.getAttribute('data-kreditor-id');
                    openKreditorKort(kreditorId);
                }
            }
        });
    }
});
</script>";

// Close the main content wrapper
print "</td></tr></tbody></table>";

include_once '../includes/oldDesign/footer.php';
?>