<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/debitor.php -----patch 4.1.0 ----2025-04-15--------------
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20130210 Break ændret til break 1
// 20160218 Udvælg fungerer nu også hvis debitor er med i flere kategorier. Søg 20160218
// 20160606 Tilføjet mulighed for at skjule lukkede debitorer Søg box11 / skjul_lukkede
// 20181205 Definering af variabler.
// 20181217 msc Rettet design til
// 20190107 MSC Rettet topmenu design til
// 20190213 MSC - Rettet topmenu design til
// 20190920 PHR - All search fiels was set to '0' if not set. Chanced to NULL
// 20200514	PHR - Added option 'Kommission' 
// 20200531	PHR - replaced 'addslashes' with 'db_escape_string' 
// 20200623	PHR - various changes related to 'kommission' 
// 20201025	PHR	- Added option for creating own mailtext for mySale - $mailText
// 20201111	PHR	- Added ordinary mail til customers not using MySale
// 20210113	PHR	- Added links written to file if mysale is active.
// 20210125 PHR - Removed last change - link now written to table mysale in master DB. 
// 20210312 PHR - added 'postnr' to numfelter.
// 20210701 LOE - Translated these texts with findtekst function
// 20210728 LOE - Updated some texts with translated ones 
// 20210812 MSC - Implementing new top menu design 
// 20210904 PHR - Sets cookie mySalePw to allow pawwwordless access t mySale 
// 20210907 MSC - Implementing new design
// 20211102 MSC - Implementing new design
// 20220226 PHR - Added: 	$mail->CharSet = "$charset";
// 20220824 MSC - Implementing new design
// 20220824 MSC - $title moved further down the file search //WEBPAGE TITLE
// 20220912 MSC - Implementing new design
// 20230111 MSC - Implementing new design
// 20230402 PHR - Added  '&& $cat_liste[0] != '0'' 
// 20230611 PHP - Fixed missing pre & nextpil 
// 20230717 PBLM - Added link to booking on line 375
// 20231128 MSC - Copy pasted new design into code
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20250415 LOE Updated some variables using if_isset and some clean up.

#ob_start();
@session_start();
$s_id=session_id();

$adresseantal=$check_all=$hrefslut=$javascript=$kontoid=$linjebg=$linjetext=NULL;
$nextpil=$ny_sort=$prepil=$skjul_lukkede=$tidspkt=$understreg=$udv2=NULL;
$cat_liste=$dg_liste=$find=$dg_id=$dg_navn=$selectfelter=array();

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
// -->
</script>
";
$css="../css/standard.css";
$modulnr=6;
$title="Debitorliste";
$firmanavn=NULL; 
$ansat_id = array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");
include("../includes/row-hover-style.js.php");
include(get_relative() . "includes/grid.php");

$id = if_isset($_GET,NULL,'id');
$returside=if_isset($_GET,NULL,'returside');


$valg= strtolower(if_isset($_GET,NULL,'valg'));
// Route to separate files for historik and kommission
if ($valg == 'historik') {
	include('debitor_historik.php');
	exit;
}
if ($valg == 'kommission') {
	include('debitor_kommission.php');
	exit;
}
// Default to debitor view
if (!$valg || $valg == 'rental') $valg = $valg ? $valg : "debitor";

$sort = if_isset($_GET, NULL, 'sort');
$start = if_isset($_GET, NULL, 'start');
$nysort = if_isset($_GET, NULL, 'nysort');

$sort=str_replace("adresser.","",$sort);
if ($sort && $nysort==$sort) $sort=$sort." desc";
elseif ($nysort) $sort=$nysort;
$r=db_fetch_array(db_select("select box7 from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
$jobkort=$r['box7'];
$qtxt = "select var_value from settings where var_grp='debitor' and var_name='mySale'";
($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$showMySale=trim($r['var_value']):$showMySale=NULL;
$qtxt = "select var_value from settings where var_grp='rental'";
($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$showRental=trim($r['var_value']):$showRental=NULL;
$x = 0;
$qtxt = "select id,box3,box6 from grupper where art = 'DLV' and kode='$valg' and kodenr = '$bruger_id'";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($x > 0) db_modify("delete from grupper where id='$r[id]'",__FILE__ . " linje " . __LINE__);
	elseif ($valg=='kommission' && date('Y-m') == '2020-06' && substr($r['box6'],-4)=='lger') {
		$box3 = "kontonr".chr(9)."firmanavn".chr(9)."addr1".chr(9)."addr2".chr(9)."postnr".chr(9);
		$box3.= "bynavn".chr(9)."kontakt".chr(9)."tlf".chr(9)."invoiced";
		$box6 = "Kontonr".chr(9)."Firmanavn".chr(9)."Adresse".chr(9)."Adresse 2".chr(9);
		$box6.= "Postnr".chr(9)."By".chr(9)."Kontakt".chr(9)."Telefon".chr(9)."Sidste faktura";
		db_modify("update grupper set box3 = '$box3',box6 = '$box6' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
	} 
	$x++;
}
if ($x == 0) {
	$box7 = 100;
	if ($valg=='debitor') {
		$box3 = "kontonr".chr(9)."firmanavn".chr(9)."addr1".chr(9)."addr2".chr(9)."postnr".chr(9);
		$box3.= "bynavn".chr(9)."kontakt".chr(9)."tlf";
		$box5 = "right".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left";
		$box4 = "5".chr(9)."35".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10";
		$box6 = "Kontonr".chr(9)."Firmanavn".chr(9)."Adresse".chr(9)."Adresse 2".chr(9);
		$box6.= "Postnr".chr(9)."By".chr(9)."Kontakt".chr(9)."Telefon";
	} elseif ($valg=='rental') {
		$box3 = "kontonr".chr(9)."firmanavn".chr(9)."addr1".chr(9)."addr2".chr(9)."postnr".chr(9)."bynavn";
		$box4 = "5".chr(9)."35".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10";
		$box5 = "right".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left";
		$box7 = 50;
		$box6 = "Kontonr".chr(9)."Firmanavn".chr(9)."Adresse".chr(9)."Adresse 2".chr(9)."Postnr".chr(9)."By";
	}
	$qtxt = "insert into grupper(beskrivelse,kode,kodenr,art,box3,box4,box5,box6,box7) values ";
	$qtxt.= "('debitorlistevisning','$valg','$bruger_id','DLV','$box3','$box4','$box5','$box6','$box7')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
} else {
	$qtxt="select box1,box2,box7,box9,box10,box11 from grupper where art = 'DLV' and kode='$valg' and kodenr = '$bruger_id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
	$dg_liste=explode(chr(9),$r['box1']);
	$cat_liste=explode(chr(9),$r['box2']);
	$skjul_lukkede=$r['box11'];
	$linjeantal=$r['box7'];
	if (!$sort) $sort=$r['box9'];
	$find=explode("\n",$r['box10']);
}
	
if ($popup) $returside= "../includes/luk.php";
else $returside= "../index/menu.php";

db_modify("update grupper set box9='$sort' where art = 'DLV' and kode='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__);

$tidspkt=date("U");
 
if ($search = if_isset($_POST, NULL, 'search')) {
	$find = if_isset($_POST, NULL, 'find');
	$valg = if_isset($_POST, NULL, 'valg');
	$sort = if_isset($_POST, NULL, 'sort');
	$nysort = if_isset($_POST, NULL, 'nysort');
	$firma = if_isset($_POST, NULL, 'firma');
}



if (!$valg) $valg = "debitor";
if (!$sort) $sort = "firmanavn";

$sort=str_replace("adresser.","",$sort);
$sortering=$sort;

if ($menu=='T') {
	if ($valg=='debitor') {
		$title = "".findtekst(117,$sprog_id)."";
	} elseif ($valg=='rental') {
		$title= "".findtekst(1116,$sprog_id)."";
	} else {
		$title = "".findtekst(117,$sprog_id)."";
	}
} else {
	$title="Debitorliste";
}

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";   
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">";
	if ($valg=='rental') {
		print "";
	} else {
		print "<a accesskey=V href='debitorvisning.php?valg=$valg' title='Ændre visning'><i class='fa fa-gear fa-lg'></i></a> &nbsp; ";
	}

	print "<a accesskey=N href='ordre.php?konto_id=$konto_id&returside=ordreliste.php?konto_id=$konto_id' title='Opret nyt kundekort'><i class='fa fa-plus-square fa-lg'></i></a></div>";     
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu=='S') include_once 'debLstIncludes/topLine.php';
else include_once 'debLstIncludes/oldTopLine.php';

// Skip grid conversion for rental view - use old system
if ($valg == 'rental') {
    $r = db_fetch_array(db_select("select box3,box4,box5,box6,box8,box11 from grupper where art = 'DLV' and kodenr = '$bruger_id' and kode='$valg'",__FILE__ . " linje " . __LINE__));
    $vis_felt=explode(chr(9),$r['box3']);
    $feltbredde=explode(chr(9),$r['box4']);
    $justering=explode(chr(9),$r['box5']);
    $feltnavn=explode(chr(9),$r['box6']);
    $vis_feltantal=count($vis_felt);
    $select=explode(chr(9),$r['box8']);
} else {
    // Use grid system for debitor, kommission, and historik views
$r = db_fetch_array(db_select("select box3,box4,box5,box6,box8,box11 from grupper where art = 'DLV' and kodenr = '$bruger_id' and kode='$valg'",__FILE__ . " linje " . __LINE__));
$vis_felt=explode(chr(9),$r['box3']);
$feltbredde=explode(chr(9),$r['box4']);
$justering=explode(chr(9),$r['box5']);
$feltnavn=explode(chr(9),$r['box6']);
$vis_feltantal=count($vis_felt);
$select=explode(chr(9),$r['box8']);
    $skjul_lukkede=$r['box11'];
}

$y=0;
for ($x=0;$x<=$vis_feltantal;$x++) {
	if (isset($select[$x]) && isset($vis_felt[$x]) && $select[$x] && $vis_felt[$x]) {
		$selectfelter[$y]=$vis_felt[$x];
		$y++;
	}
}
$numfelter=array("rabat","momskonto","kreditmax","betalingsdage","gruppe","kontoansvarlig","postnr","kontonr");

// If rental view, use old system
if ($valg == 'rental') {
####################################################################################
$udvaelg=NULL;
$tmp=trim(if_isset($find[0],NULL));
for ($x=1;$x<$vis_feltantal;$x++) {
	if (isset($find[$x])) {
		$tmp=$tmp."\n".trim($find[$x]);
	}
}
$qtxt="update grupper set box10='". db_escape_string($tmp) ."' where art = 'DLV' and kode='$valg' and kodenr = '$bruger_id'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);

if ($skjul_lukkede) $udvaelg = " and lukket != 'on'";

for ($x=0;$x<$vis_feltantal;$x++) {
    if (isset($find[$x])) {
        $find[$x]=trim($find[$x]);
        $tmp=$vis_felt[$x];
        if ($tmp) {
            if ($find[$x] && in_array($tmp, array('invoiced', 'kontaktet', 'kontaktes'))) {
                $tmp2="adresser.".$tmp;
                $udvaelg.=udvaelg($find[$x],$tmp2, 'DATO');
            } elseif ($tmp == 'kontakt' && $find[$x]) {
                $udvaelg.=" and adresser.id in (select konto_id from ansatte where LOWER(navn) like LOWER('%".db_escape_string($find[$x])."%'))";
            } elseif ($find[$x] && !in_array($tmp,$numfelter)) {
                $searchTerm = "*" . str_replace(" ", "*", $find[$x]) . "*";
                $tmp2="adresser.".$tmp;
                $udvaelg.=udvaelg($searchTerm,$tmp2, 'TEXT');
            } elseif ($find[$x]||$find[$x]=="0") {
                $tmp2="adresser.".$tmp;
                $udvaelg.=udvaelg(db_escape_string($find[$x]),$tmp2, 'NR');
            }
        }
    }
}

if (count($dg_liste)) {
	$x=0;
	$q=db_select("select * from grupper where art = 'DG' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		$dg_id[$x]=$r['id'];
		$dg_kodenr[$x]=$r['kodenr']*1;
		$dg_navn[$x]=$r['beskrivelse'];
	}
	$dg_antal=$x;
}

if (count($cat_liste)) {
	$r=db_fetch_array(db_select("select box1,box2 from grupper where art='DebInfo'",__FILE__ . " linje " . __LINE__));
	$cat_id=explode(chr(9),$r['box1']);
	$cat_beskrivelse=explode(chr(9),$r['box2']);
	$cat_antal=count($cat_id);
}

$sortering="adresser.".$sortering;
$ialt=0;
$lnr=0;
if (!$linjeantal) $linjeantal=100;
$slut=$start+$linjeantal;
$adresserantal=0;
$qtxt = "select count(id) as antal from adresser where art = 'D' $udvaelg";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
    $antal=$r['antal'];

    // Continue with old table rendering for rental view
if ($menu=='T'){
	print "<table class='dataTableBooking' style='overflow: hidden; text-overflow: ellipsis; white-space: nowrap;' cellpadding='1' cellspacing='1' border='0' valign='top' width='100%'><thead>\n<tr>";
} else {
	print "<table cellpadding='1'  style='overflow: hidden; text-overflow: ellipsis; white-space: nowrap;' cellspacing='1' border='0' valign='top' width='100%'><tbody>\n<tr>";
}
    // ... rest of old table code for rental view ...
    include ("../debitor/debLstIncludes/debRentalLst.php");
#print "<table border=0 width=100%><tbody>";

#print "</tbody></table></td>";
#print "<tr><td colspan=$colspan><hr></td></tr>\n";

if ($menu=='T') {
	print "
</tfoot>
</table>
</td></tr>
</tbody></table>
";
include_once '../includes/topmenu/footer.php';
} else {
	print "
</tbody>
</table>
</td></tr>
</tbody></table>
";

include_once '../includes/oldDesign/footer.php';
    }
} else {
    // GRID SYSTEM IMPLEMENTATION
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

    // Get status options
    $status_id=array();
    $status_beskrivelse=array();
    $qtxt = "select box3,box4 from grupper where art='DebInfo'";
    $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
    if ($r) {
        $status_id=explode(chr(9),$r['box3']);
        $status_beskrivelse=explode(chr(9),$r['box4']);
    }
    $status_antal=count($status_id);

    // Build columns from stored configuration
    for ($x=0;$x<$vis_feltantal;$x++) {
        if (!isset($vis_felt[$x]) || !$vis_felt[$x]) continue;
        if (substr($vis_felt[$x],0,4) == 'cat_') continue; // Skip category columns for now
        
        $field = $vis_felt[$x];
        $headerName = isset($feltnavn[$x]) ? $feltnavn[$x] : $field;
        $width = isset($feltbredde[$x]) && $feltbredde[$x] ? ($feltbredde[$x] / 100) : 1;
        $align = isset($justering[$x]) ? $justering[$x] : 'left';
        $isSearchable = true; // All fields are searchable in grid
        
        $column = array(
            "field" => $field,
            "headerName" => $headerName,
            "width" => $width,
            "align" => $align,
            "searchable" => $isSearchable,
            "sqlOverride" => "a.$field"
        );
        
        // Determine field type
        // kontonr and postnr are identifiers, not numeric values, so treat as text
        if ($field == 'kontonr' || $field == 'postnr') {
            $column["type"] = "text";
        } elseif (in_array($field, $numfelter)) {
            $column["type"] = "number";
            if ($align == 'left') $column["align"] = "right";
        } elseif (in_array($field, array('invoiced', 'kontaktet', 'kontaktes'))) {
            $column["type"] = "date";
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
        } elseif (in_array($field, array('invoiced', 'kontaktet', 'kontaktes'))) {
            $column["render"] = function ($value, $row, $column) {
                if ($value=='1970-01-01' || !$value) return "<td align='{$column['align']}'></td>";
                return "<td align='{$column['align']}'>".dkdato($value)."</td>";
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
        } elseif (in_array($field, array('invoiced', 'kontaktet', 'kontaktes'))) {
            $column["generateSearch"] = function ($column, $term) {
                $field = $column['sqlOverride'];
                $term = db_escape_string($term);
                if (strstr($term, ':')) {
                    list($date1, $date2) = explode(":", $term, 2);
                    return "$field >= '".usdate($date1)."' AND $field <= '".usdate($date2)."'";
                } else {
                    return "$field = '".usdate($term)."'";
                }
            };
        }
        
        $columns[] = $column;
    }

    // Add clickable row renderer for kontonr
    foreach ($columns as &$column) {
        if ($column['field'] == 'kontonr') {
            $column["render"] = function ($value, $row, $column) {
                $url = "debitor"."kort.php?tjek={$row['id']}&id={$row['id']}&returside=debitor.php";
                return "<td align='{$column['align']}' onclick=\"window.location.href='$url'\" style='cursor:pointer'><a href='$url'>$value</a></td>";
            };
            break;
        }
    }

    // Build filters
    $filters = array();

    // Hide closed filter
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

    // Debtor groups filter - show all available groups (group by kodenr to avoid duplicates)
    // Use GROUP BY to ensure unique kodenr values, taking the first beskrivelse for each
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
            "filterName" => "Kundegrupper",
            "joinOperator" => "or",
            "options" => $dg_options
        );
    }

    // Categories filter - show all available categories
    $r=db_fetch_array(db_select("select box1,box2 from grupper where art='DebInfo'",__FILE__ . " linje " . __LINE__));
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

    // Build query
    $select_fields = array();
    foreach ($columns as $col) {
        $select_fields[] = $col['sqlOverride'] . " AS " . $col['field'];
    }
    $select_fields[] = "a.id AS id";

    $query = "SELECT " . implode(",\n    ", $select_fields) . "
FROM adresser a
WHERE a.art = 'D' AND {{WHERE}}
ORDER BY {{SORT}}";

    // Row styling
    $rowStyleFn = function ($row) {
        if (isset($row['lukket']) && $row['lukket'] == 'on') {
            return "color: #f00;";
        }
        return "";
    };

    // Meta column not used in default debitor view
    $metaColumnFn = null;

    // Create grid data array
    $data = array(
        "table_name" => "debitor_list",
        "query" => $query,
        "columns" => $columns,
        "filters" => $filters,
        "rowStyle" => $rowStyleFn,
        "metaColumn" => $metaColumnFn,
    );

    // Render grid - use unique table_id to prevent conflicts with other grid views
    $table_id = 'debitor_list';
    
    // Render grid - match vareliste.php structure exactly
    print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
    create_datagrid($table_id, $data);
    print "</div>";
    
    if ($menu=='T') {
        include_once '../includes/topmenu/footer.php';
    } else {
        include_once '../includes/oldDesign/footer.php';
    }
}




?>