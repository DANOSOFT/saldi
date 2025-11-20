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
include(get_relative() . "includes/grid.php");



if ($menu == 'T') {
	$title = "Konti";
} else {
	$title = "Kreditorliste";
}

$id = if_isset($_GET['id']);
$returside = if_isset($_GET['returside']);

$valg = strtolower(if_isset($_GET['valg']));
$sort = if_isset($_GET['sort']);
$start = if_isset($_GET['start']);
$nysort = if_isset($_GET['nysort']);
$kreditor1 = lcfirst(findtekst(1169, $sprog_id)); #20210331
$brisk1 = findtekst(944, $sprog_id);

$aa = findtekst(360, $sprog_id);
$firmanavn = ucfirst(str_replace(' ', '_', $aa));

if (!$valg) $valg = "$kreditor1";
#echo "$kreditor1";
$sort = str_replace("adresser.", "", $sort);
if ($sort && $nysort == $sort) $sort = $sort . " desc";
elseif ($nysort) $sort = $nysort;
$r = db_fetch_array(db_select("select box7 from grupper where art = 'DIV' and kodenr = '2'", __FILE__ . " linje " . __LINE__));
$jobkort = $r['box7'];

#>>>>>>>>>>>>>>>>>>>>>
function select_valg($valg, $box)
{
	global $kreditor1, $sprog_id;
	if ($valg == "$kreditor1") {
		switch ($box) {
			case "box3":
				return "kontonr" . chr(9) . "firmanavn" . chr(9) . "addr1" . chr(9) . "addr2" . chr(9) . "postnr" . chr(9) . "bynavn" . chr(9) . "kontakt" . chr(9) . "tlf" . chr(9) . "kontoansvarlig";
				break;
			case "box5":
				return "right" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left";
				break;
			case "box4":
				return "5" . chr(9) . "35" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10";
				break;
			case "box6":
				return "" . findtekst(284, $sprog_id) . "" . chr(9) . "" . findtekst(360, $sprog_id) . "" . chr(9) . "Adresse" . chr(9) . "Adresse 2" . chr(9) . "" . findtekst(144, $sprog_id) . "" . chr(9) . "By" . chr(9) . "" . findtekst(502, $sprog_id) . "" . chr(9) . "" . findtekst(37, $sprog_id) . "";
			default:
				return "choose a box";
				break;
		}
	} else {

		switch ($box) {
			case "box3":
				return "kontonr" . chr(9) . "firmanavn" . chr(9) . "addr1" . chr(9) . "addr2" . chr(9) . "postnr" . chr(9) . "bynavn" . chr(9) . "kontakt" . chr(9) . "tlf" . chr(9) . "kontoansvarlig";
				break;
			case "box5":
				return "right" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left" . chr(9) . "left";
				break;
			case "box4":
				return "5" . chr(9) . "35" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10" . chr(9) . "10";
				break;
			case "box6":
				return "" . findtekst(284, $sprog_id) . "" . chr(9) . "" . findtekst(360, $sprog_id) . "" . chr(9) . "Adresse" . chr(9) . "Adresse 2" . chr(9) . "" . findtekst(144, $sprog_id) . "" . chr(9) . "By" . chr(9) . "" . findtekst(502, $sprog_id) . "" . chr(9) . "" . findtekst(37, $sprog_id) . ""; #20210705
			default:
				return "choose a box";
				break;
		}
	}
}

$box5 = select_valg("$valg", "box5");
$box3 = select_valg("$valg", "box3");
$box4 = select_valg("$valg", "box4");
$box6 = select_valg("$valg", "box6");
#>>>>>>>>>>>>>>>>>>>>>


if (!$r = db_fetch_array(db_select("select id from grupper where art = 'KLV' and kode='$valg' and kodenr = '$bruger_id'", __FILE__ . " linje " . __LINE__))) {
	#	db_modify("update grupper set box2='$returside' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
	#} else { ".findtekst(360,$sprog_id)."
	// if ($valg=="$kreditor1") { #20210331
	// 	#$box3="kontonr".chr(9)."firmanavn".chr(9)."addr1".chr(9)."addr2".chr(9)."postnr".chr(9)."bynavn".chr(9)."kontakt".chr(9)."tlf".chr(9)."kontoansvarlig"; 
	// 	$box3= "".findtekst(284,$sprog_id)."".chr(9)."".findtekst(360,$sprog_id)."".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."".findtekst(144,$sprog_id)."".chr(9)."By".chr(9)."".findtekst(502,$sprog_id)."".chr(9)."".findtekst(64,$sprog_id)."";
	// 	$box5="right".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left";
	// 	$box4="5".chr(9)."35".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10";
	// 	#$box6="Kontonr".chr(9)."Firmanavn".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."Postnr".chr(9)."By".chr(9)."Kontakt".chr(9)."Telefon".chr(9)."S&aelig;lger";
	// 	$box6="".findtekst(284,$sprog_id)."".chr(9)."".findtekst(360,$sprog_id)."".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."".findtekst(144,$sprog_id)."".chr(9)."By".chr(9)."".findtekst(502,$sprog_id)."".chr(9)."".findtekst(37,$sprog_id)."";
	// } else {
	// 	#$box3="kontonr".chr(9)."firmanavn".chr(9)."addr1".chr(9)."addr2".chr(9)."postnr".chr(9)."bynavn".chr(9)."kontakt".chr(9)."tlf".chr(9)."kontoansvarlig";
	// 	$box3= "".findtekst(284,$sprog_id)."".chr(9)."".findtekst(360,$sprog_id)."".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."".findtekst(144,$sprog_id)."".chr(9)."By".chr(9)."".findtekst(502,$sprog_id)."".chr(9)."".findtekst(64,$sprog_id)."";
	// 	$box5="right".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left";
	// 	$box4="5".chr(9)."35".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10";
	// 	#$box6="Kontonr".chr(9)."Firmanavn".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."Postnr".chr(9)."By".chr(9)."Kontakt".chr(9)."Telefon".chr(9)."S&aelig;lger";
	// 	$box6 = "".findtekst(284,$sprog_id)."".chr(9)."".findtekst(360,$sprog_id)."".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."".findtekst(144,$sprog_id)."".chr(9)."By".chr(9)."".findtekst(502,$sprog_id)."".chr(9)."".findtekst(37,$sprog_id)."";
	// }

	######


	db_modify("insert into grupper(beskrivelse,kode,kodenr,art,box3,box4,box5,box6,box7)values('$brisk1','$valg','$bruger_id','KLV','$box3','$box4','$box5','$box6','100')", __FILE__ . " linje " . __LINE__);
} else {

	if ($h1 = db_fetch_array(db_select("select*from grupper where art='KLV' and kode='$valg' and kodenr = '$bruger_id' ", __FILE__ . " linje " . __LINE__))) $q = $h1['box6']; #20210331

	if ($q !== "" || false) {
		if (!in_array(trim("$firmanavn"), explode(chr(9), $q))) {

			$qtxt = "update grupper set beskrivelse='$brisk1',kode='$valg',kodenr='$bruger_id',box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='100' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}

		######
	} else {
		$qtxt = "update grupper set box3='$box3',box6='$box6' where art = 'KLV' and kode='$valg' and kodenr = '$bruger_id'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}

	$r = db_fetch_array(db_select("select box1,box2,box7,box9,box10,box11 from grupper where art = 'KLV' and kode='$valg' and kodenr = '$bruger_id'", __FILE__ . " linje " . __LINE__));
	$dg_liste = explode(chr(9), $r['box1']);
	if (!empty($r["box2"])) {
		$cat_liste = explode(chr(9), $r['box2']);
	}
	$skjul_lukkede = $r['box11'];
	$linjeantal = $r['box7'];
	if (!$sort) $sort = $r['box9'];
	$find = explode("\n", $r['box10']);
	// var_dump($box6);
	// var_dump($firmanavn);
}
if ($valg == "$kreditor1") {
	$valg = 'kreditor';
} #20210705
$backUrl = isset($_GET['returside'])
	? $_GET['returside']
	: '../index/menu.php';

if ($popup) $returside = "../includes/luk.php";
else $returside = $backUrl;

db_modify("update grupper set box9='$sort' where art = 'KLV' and kode='$valg' and kodenr = '$bruger_id'", __FILE__ . " linje " . __LINE__);

$tidspkt = date("U");

// Handle search submit like debitor
if ($search = if_isset($_POST, NULL, 'search')) {
	$find = if_isset($_POST['find']);
	$valg = if_isset($_POST['valg']);
	$sort = if_isset($_POST['sort']);
	$nysort = if_isset($_POST['nysort']);
	$firma = if_isset($_POST['firma']);
}


if (!$valg) $valg = "kreditor";
if (!$sort) $sort = "firmanavn";

$sort = str_replace("adresser.", "", $sort);
$sortering = $sort;

if ($menu=='T') {
	include_once 'kredLstIncludes/topLine.php';
} elseif ($menu=='S') include_once 'kredLstIncludes/topLine.php';
else {

	include_once 'kredLstIncludes/topLine.php';
}
$vis_felt = array();
$qtxt = "select box3,box4,box5,box6,box8,box11 from grupper where art = 'KLV' and kodenr = '$bruger_id' and kode='$valg'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$vis_felt = explode(chr(9), $r['box3']);
	$feltbredde = explode(chr(9), $r['box4']);
	$justering = explode(chr(9), $r['box5']);
	$feltnavn = explode(chr(9), $r['box6']);
	$select = explode(chr(9), $r['box8']);
	$skjul_lukkede=$r['box11'];
}

$vis_feltantal=count($vis_felt);
$y=0;
for ($x=0;$x<=$vis_feltantal;$x++) {
	if (isset($select[$x]) && isset($vis_felt[$x]) && $select[$x] && $vis_felt[$x]) {
		$selectfelter[$y]=$vis_felt[$x];
		$y++;
	}
}

$numfelter=array("rabat","momskonto","kreditmax","betalingsdage","gruppe","kontoansvarlig","postnr","kontonr");


// Build columns array for grid
$columns = array();

// Get ansatte for kontoansvarlig field
$ansat_id=array();
$ansat_init=array();
$y=0;
$qtxt = "select distinct(ansatte.id) as ansat_id,ansatte.initialer as initialer from ansatte,adresser where ";
$qtxt.= "adresser.art='S' and ansatte.konto_id=adresser.id order by ansatte.initialer";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
    $y++;
    $ansat_id[$y]=$r['ansat_id'];
    $ansat_init[$y]=$r['initialer'];
}
$ansatantal=$y;

$status_id=array();
$status_beskrivelse=array();
$qtxt = "select box3,box4 from grupper where art='KredInfo'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
if ($r) {
    $status_id=explode(chr(9),$r['box3']);
    $status_beskrivelse=explode(chr(9),$r['box4']);
}
$status_antal=count($status_id);

for ($x=0;$x<count($vis_felt);$x++) {
    if (!isset($vis_felt[$x]) || !$vis_felt[$x]) continue;
    if (substr($vis_felt[$x],0,4) == 'cat_') continue; 
    
    $field = $vis_felt[$x];
    $headerName = isset($feltnavn[$x]) ? $feltnavn[$x] : $field;
    $width = isset($feltbredde[$x]) && $feltbredde[$x] ? ($feltbredde[$x] / 100) : 1;
    $align = isset($justering[$x]) ? $justering[$x] : 'left';
    $isSearchable = true; 
    
    $column = array(
        "field" => $field,
        "headerName" => $headerName,
        "width" => $width,
        "align" => $align,
        "searchable" => $isSearchable,
        "sqlOverride" => "a.$field"
    );
    
 
    if ($field == 'kontonr' || $field == 'postnr') {
        $column["type"] = "text";
    } elseif (in_array($field, $numfelter)) {
        $column["type"] = "number";
        if ($align == 'left') $column["align"] = "right";
    } else {
        $column["type"] = "text";
    }
    
    // Special renderers
    if ($field == 'kontoansvarlig') {
        $column["render"] = function ($value, $row, $column) use ($ansat_id, $ansat_init, $ansatantal) {
            $display = '';
            for ($y=1;$y<=$ansatantal;$y++) {
                if (isset($ansat_id[$y]) && $ansat_id[$y]==$value) {
                    $display = stripslashes($ansat_init[$y]);
                    break;
                }
            }
            return "<td align='{$column['align']}'>$display</td>";
        };
    } elseif ($field == 'status') {
        $column["render"] = function ($value, $row, $column) use ($status_id, $status_beskrivelse, $status_antal) {
            $display = '';
            for ($y=0;$y<$status_antal;$y++) {
                if (isset($status_id[$y]) && $status_id[$y]==$value) {
                    $display = stripslashes($status_beskrivelse[$y]);
                    break;
                }
            }
            return "<td align='{$column['align']}'>$display</td>";
        };
    } elseif ($field == 'kontakt') {
        $column["generateSearch"] = function ($column, $term) {
            $term = db_escape_string($term);
            return "a.id in (select konto_id from ansatte where LOWER(navn) like LOWER('%$term%'))";
        };
    } elseif ($field == 'kontonr' || $field == 'postnr') {
        // kontonr and postnr are text identifiers, use text search
        $column["generateSearch"] = function ($column, $term) {
            $field = $column['sqlOverride'];
            $term = db_escape_string($term);
            return "$field::text ILIKE '%$term%'";
        };
    } elseif (in_array($field, $numfelter)) {
        $column["generateSearch"] = function ($column, $term) {
            $field = $column['sqlOverride'];
            $term = db_escape_string($term);
            if (strstr($term, ':')) {
                list($num1, $num2) = explode(":", $term, 2);
                return "$field >= '".usdecimal($num1)."' AND $field <= '".usdecimal($num2)."'";
            } else {
                $term = usdecimal($term);
                return "$field >= $term AND $field <= $term";
            }
        };
    }
    
    $columns[] = $column;
}

foreach ($columns as &$column) {
    if ($column['field'] == 'kontonr') {
        $column["render"] = function ($value, $row, $column) use ($valg) {
            $url = "kreditorkort.php?tjek={$row['id']}&id={$row['id']}&returside=kreditor.php";
            return "<td align='{$column['align']}' onclick=\"window.location.href='$url'\" style='cursor:pointer'><a href='$url'>$value</a></td>";
        };
        break;
    }
}

$filters = array();

if ($skjul_lukkede) {
    $filters[] = array(
        "filterName" => "Misc",
        "joinOperator" => "and",
        "options" => array(
            array(
                "name" => "Vis udgået",
                "checked" => "",
                "sqlOn" => "",
                "sqlOff" => "(a.lukket IS NULL OR a.lukket = '0' or a.lukket = '')",
            )
        )
    );
}

$q=db_select("select kodenr, MIN(beskrivelse) as beskrivelse from grupper where art = 'DG' group by kodenr order by kodenr",__FILE__ . " linje " . __LINE__);
$dg_options = array();
$seen_kodenr = array(); // Track seen kodenr values to prevent duplicates
$seen_names = array(); // Also track by name to catch any remaining duplicates
while ($r=db_fetch_array($q)) {
    $kodenr = (int)$r['kodenr']; // Ensure it's an integer
    $name = trim($r['beskrivelse']);
    // Check both kodenr and name to prevent duplicates
    $key = $kodenr . '|' . $name;
    if (!in_array($kodenr, $seen_kodenr) && !in_array($name, $seen_names)) {
        $seen_kodenr[] = $kodenr;
        $seen_names[] = $name;
        $dg_options[] = array(
            "name" => $name,
            "checked" => "",
            "sqlOn" => "a.gruppe = $kodenr",
            "sqlOff" => "",
        );
    }
}
if (count($dg_options)) {
    $filters[] = array(
        "filterName" => "Leverandørgrupper",
        "joinOperator" => "or",
        "options" => $dg_options
    );
}


$r=db_fetch_array(db_select("select box1,box2 from grupper where art='KredInfo'",__FILE__ . " linje " . __LINE__));
if ($r && $r['box1'] && $r['box2']) {
    $cat_id=explode(chr(9),$r['box1']);
    $cat_beskrivelse=explode(chr(9),$r['box2']);
    $cat_antal=count($cat_id);
    $cat_options = array();
    for ($y=0;$y<$cat_antal;$y++) {
        if (isset($cat_id[$y]) && isset($cat_beskrivelse[$y]) && $cat_id[$y] && $cat_beskrivelse[$y]) {
            $cat_options[] = array(
                "name" => $cat_beskrivelse[$y],
                "checked" => "",
                "sqlOn" => "(a.kategori = '{$cat_id[$y]}' or a.kategori LIKE '{$cat_id[$y]}".chr(9)."%' or a.kategori LIKE '%".chr(9)."{$cat_id[$y]}' or a.kategori LIKE '%".chr(9)."{$cat_id[$y]}".chr(9)."%')",
                "sqlOff" => "",
            );
        }
    }
    if (count($cat_options)) {
        $filters[] = array(
            "filterName" => "Kategorier",
            "joinOperator" => "or",
            "options" => $cat_options
        );
    }
}

$select_fields = array();
foreach ($columns as $col) {
    $select_fields[] = $col['sqlOverride'] . " AS " . $col['field'];
}
$select_fields[] = "a.id AS id";

$query = "SELECT " . implode(",\n    ", $select_fields) . "
FROM adresser a
WHERE a.art = 'K' AND {{WHERE}}
ORDER BY {{SORT}}";


$rowStyleFn = function ($row) {
    if (isset($row['lukket']) && $row['lukket'] == 'on') {
        return "color: #f00;";
    }
    return "";
};

// grid data array
$data = array(
    "table_name" => "kreditor",
    "query" => $query,
    "columns" => $columns,
    "filters" => $filters,
    "rowStyle" => $rowStyleFn,
    "metaColumn" => null,
);

// Render grid
$table_id = 'kredlist';

// Render grid
print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
create_datagrid($table_id, $data);
print "</div>";

if ($menu=='T') {
    include_once '../includes/topmenu/footer.php';
} else {
    include_once '../includes/oldDesign/footer.php';
}
