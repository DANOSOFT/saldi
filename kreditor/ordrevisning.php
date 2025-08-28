<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------kreditor/ordrevisning.php-----lap 4.1.0-------2025.08.26-----------
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$box4=NULL;

include("../includes/std_func.php");

// View selection: forslag, ordrer, faktura (aligned with kreditor/ordreliste.php)
if (isset($_GET['valg'])) $valg=($_GET['valg']);
else $valg="ordrer";

if ($valg=="forslag") {
$title=findtekst('827|forslag',$sprog_id); // reuse same key text
} elseif ($valg=="ordrer") {
$title=findtekst('546|Ordrevisning',$sprog_id);
} elseif ($valg=="faktura") {
$title=findtekst('544|Fakturavisning', $sprog_id);
} else {
$title=findtekst('813|Visning', $sprog_id);
}

$modulnr=6;

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/topline_settings.php");

$aa=findtekst('545|Tilbudsvisning',$sprog_id);
$bb=findtekst('546|Ordrevisning',$sprog_id);
$cc=findtekst('544|Fakturavisning',$sprog_id);

if (isset($_GET['valg'])) $valg=($_GET['valg']);
else $valg="ordrer";

if ($valg=="forslag") $title=$aa; // treat as tilbud
elseif ($valg=="ordrer") $title=$bb;
else $title=$cc;

$modulnr=6;

$sort=trim(if_isset($_GET['sort']));

// Save configuration
if (isset($_POST) && $_POST) {
$vis_feltantal=if_isset($_POST['vis_feltantal']);
$vis_linjeantal=if_isset($_POST['vis_linjeantal']);
$vis_felt=if_isset($_POST['vis_felt']);
$pos=if_isset($_POST['pos']);
$feltbredde=if_isset($_POST['feltbredde']);
$justering=if_isset($_POST['justering']);
$feltnavn=if_isset($_POST['feltnavn']);
$dropDown=if_isset($_POST['dropDown']);
for ($x=0;$x<=$vis_feltantal;$x++) {
if (!isset($dropDown[$x])) $dropDown[$x]=NULL;
if (!$feltbredde[$x]) $feltbredde[$x]=50;
if (!$pos[$x] && !$feltnavn[$x]) $pos[$x]=50;
}
$vis_felt=sorter($pos,$vis_felt,$vis_feltantal);
$feltbredde=sorter($pos,$feltbredde,$vis_feltantal);
$justering=sorter($pos,$justering,$vis_feltantal);
$feltnavn=sorter($pos,$feltnavn,$vis_feltantal);
$dropDown=sorter($pos,$dropDown,$vis_feltantal);
$box3='ordrenr';
$box5=$justering[0];
$box6=db_escape_string($feltnavn[0]);
$box7=$vis_linjeantal*1;
$box10=$dropDown[0];
if (!$vis_linjeantal) $vis_linjeantal=50;
for ($x=1;$x<=$vis_feltantal;$x++) {
if (!isset($pos[$x])) $pos[$x]=NULL;
if ($pos[$x]!='-') {
if (!isset($vis_felt[$x])) $vis_felt[$x]="";
$box3=$box3.",".$vis_felt[$x];
$feltbredde[$x]=$feltbredde[$x]*1;
$box4=$box4.",".$feltbredde[$x];
$box5=$box5.",".$justering[$x];
$box6=$box6.",".db_escape_string($feltnavn[$x]);
$box10=$box10.",".$dropDown[$x];
}
}
// Upsert into grupper art 'KOLV'
$qexists = "select id from grupper where art='KOLV' and kode='$valg' and kodenr='$bruger_id'";
if ($rexists=db_fetch_array(db_select($qexists,__FILE__ . " linje " . __LINE__))) {
    db_modify("update grupper set box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$vis_linjeantal',box10='$box10' where id='{$rexists['id']}'",__FILE__ . " linje " . __LINE__);
} else {
    $beskrivelse = 'Kreditor Ordrevisning';
    db_modify("insert into grupper (beskrivelse,kode,kodenr,art,box3,box4,box5,box6,box7,box10) values ('$beskrivelse','$valg','$bruger_id','KOLV','$box3','$box4','$box5','$box6','$vis_linjeantal','$box10')",__FILE__ . " linje " . __LINE__);
}
}

// Top layout
if ($menu=='T') {
$title="Ordrevisning • Leverandører";
$classtable2 ="class=dataTableForm";
include_once '../includes/top_header.php';
include_once '../includes/top_menu.php';
print "<div id=\"header\">";
print "<div class=\"headerbtnLft headLink\"><a href=ordreliste.php?valg=$valg&sort=$sort accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";
print "<div class=\"headerTxt\">$title</div>";
print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
print "</div>";
print "<div class='content-noside'>";
} elseif ($menu=='S') {
$classtable2 ="";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n   <table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"4\" cellpadding=\"0\"><tbody>\n   <td width=\"10%\" align=center><a href=ordreliste.php?valg=$valg&sort=$sort accesskey=L>\n   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">".findtekst('30|Tilbage',$sprog_id)."</button></a></td>\n   <td width='80%' align=center style='$topStyle'>$title</td>\n   <td width='10%' align=center style='$topStyle'><br></td></tr>\n   </tr>\n   </tbody></table>\n   </td></tr>";
print "<center>";
} else {
$classtable2 ="";
include("../includes/oldDesign/header.php");
print  "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"4\" cellpadding=\"0\"><tbody>\n<td width=\"10%\" align=center><div class=\"top_bund\"><a href=ordreliste.php?valg=$valg&sort=$sort accesskey=L>".findtekst('30|Tilbage',$sprog_id)."</a></div></td>\n<td width=\"80%\" align=center><div class=\"top_bund\">$title</a></div></td>\n<td width=\"10%\" align=center><div class=\"top_bund\"><br></div></td>\n</tr>\n</tbody></table>\n</td></tr>";
print "<center>";
}
print "<div class=\"maincontentLargeHolder\">\n";
print " <tr><td valign=\"top\" align=\"center\">\n<table $classtable2 cellpadding=\"1\" cellspacing=\"1\" border=\"0\" valign = \"top\">\n<tbody>";

print "<form name=ordrevisning action=ordrevisning.php?sort=$sort&valg=$valg method=post>";

// Build field list from table 'ordrer'
$i=0;
$q = db_select("select * from ordrer",__FILE__ . " linje " . __LINE__);
while ($i < db_num_fields($q)) {
$field = db_field_name($q, $i);
$felter[$i] = is_object($field) ? $field->name : (string)$field;
$i++;
}
$felter[$i] = 'sum_m_moms';
$i++;
$felter[$i] = 'kundegruppe'; // exists for debitor; harmless if unused
sort($felter);

print "<tr><td colspan='7' align='center'>".findtekst(537, $sprog_id)."</td></tr>";
print "<tr><td colspan='7' align='center'>".findtekst(538, $sprog_id)."</td></tr>";
if ($menu=='T') {
print "<tr><td colspan=7 class='border-hr-top'></td></tr>\n";
} else {
print "<tr><td colspan=7><hr></td></tr>\n";
}

// Ensure a default KOLV row exists with sensible defaults
$r = db_fetch_array(db_select("select id from grupper where art = 'KOLV' and kode ='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__));
if (!$r) {
    if ($valg=="forslag") {
        $d_box3 = "ordrenr,ordredate,kontonr,firmanavn,lev_navn,ref,sum";
        $d_box5 = "right,left,left,left,left,left,right";
        $d_box4 = "50,100,100,150,150,100,100";
        $d_box6 = findtekst('500|Ordrenr.', $sprog_id).",".findtekst('889|Tilbudsdato', $sprog_id).",".findtekst('804|Kontonr.', $sprog_id).",".findtekst('360|Firmanavn', $sprog_id).",".findtekst('814|Leveres til', $sprog_id).",".findtekst('884|Sælger', $sprog_id).",".findtekst('826|Forslagssum', $sprog_id);
    } elseif ($valg=="ordrer") {
        $d_box3 = "ordrenr,ordredate,levdate,kontonr,firmanavn,lev_navn,ref,sum";
        $d_box5 = "right,left,left,left,left,left,left,right";
        $d_box4 = "50,100,100,100,150,150,100,100";
        $d_box6 = findtekst('500|Ordrenr.', $sprog_id).",".findtekst('881|Ordredato', $sprog_id).",".findtekst('941|Modt.dato', $sprog_id).",".findtekst('804|Kontonr.', $sprog_id).",".findtekst('360|Firmanavn', $sprog_id).",".findtekst('814|Leveres til', $sprog_id).",".findtekst('884|Sælger', $sprog_id).",".findtekst('887|Ordresum', $sprog_id);
    } else { // faktura
        $d_box3 = "ordrenr,ordredate,modtagelse,fakturanr,fakturadate,kontonr,firmanavn,lev_navn,ref,sum";
        $d_box5 = "right,left,right,right,left,left,left,left,left,right";
        $d_box4 = "50,100,80,100,100,150,150,150,100,100";
        $d_box6 = findtekst('500|Ordrenr.', $sprog_id).",".findtekst('881|Ordredato', $sprog_id).",".findtekst('940|Modt.nr.', $sprog_id).",".findtekst('882|Fakt.nr.', $sprog_id).",".findtekst('883|Fakt.dato', $sprog_id).",".findtekst('804|Kontonr.', $sprog_id).",".findtekst('360|Firmanavn', $sprog_id).",".findtekst('814|Leveres til', $sprog_id).",".findtekst('884|Sælger', $sprog_id).",".findtekst('885|Fakturasum', $sprog_id);
    }
    db_modify("insert into grupper (beskrivelse,kode,kodenr,art,box3,box4,box5,box6,box7,box10) values ('Kreditor Ordrevisning','$valg','$bruger_id','KOLV','$d_box3','$d_box4','$d_box5','$d_box6','100','')",__FILE__ . " linje " . __LINE__);
}
// Load existing configuration for kreditor views from grupper KOLV
$r = db_fetch_array(db_select("select box3,box4,box5,box6,box7,box10 from grupper where art = 'KOLV' and kode ='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__));
$vis_felt=explode(",",$r['box3']);
$feltbredde=explode(",",$r['box4']);
$justering=explode(",",$r['box5']);
$feltnavn=explode(",",$r['box6']);
$vis_linjeantal=$r['box7'];
$dropDown=explode(",",$r['box10']);
$vis_feltantal=count($feltbredde);
if (!isset($feltnavn[$vis_feltantal])) $vis_feltantal--;

// Defaults for kreditor if not configured
if (count($feltbredde)<=1) {
if ($valg=="forslag") {
$vis_felt="ordrenr,ordredate,kontonr,firmanavn,lev_navn,ref,sum";
$justering="right,left,left,left,left,left,right";
$feltbredde="50,100,100,150,150,100,100";
$feltnavn=findtekst('888|Tilbudsnr.', $sprog_id).";".findtekst('889|Tilbudsdato', $sprog_id).";".findtekst('804|Kontonr.', $sprog_id).";".findtekst('360|Firmanavn', $sprog_id).";".findtekst('814|Leveres til', $sprog_id).";".findtekst('884|Sælger', $sprog_id).";".findtekst('826|Forslagssum', $sprog_id);
} elseif ($valg=="ordrer") {
$vis_felt="ordrenr,ordredate,levdate,kontonr,firmanavn,lev_navn,ref,sum";
$justering="right,left,left,left,left,left,left,right";
$feltbredde="50,100,100,100,150,150,100,100";
$feltnavn=findtekst('500|Ordrenr.', $sprog_id).",".findtekst('881|Ordredato', $sprog_id).",".findtekst('941|Modt.dato', $sprog_id).",".findtekst('804|Kontonr.', $sprog_id).",".findtekst('360|Firmanavn', $sprog_id).",".findtekst('814|Leveres til', $sprog_id).",".findtekst('884|Sælger', $sprog_id).",".findtekst('887|Ordresum', $sprog_id);
} elseif ($valg=="faktura") {
$vis_felt=array("ordrenr","ordredate","modtagelse","fakturanr","fakturadate","kontonr","firmanavn","lev_navn","ref","sum");
$justering=array("right","left","right","right","left","left","left","left","left","right");
$feltbredde=array("50","100","80","100","100","150","150","150","100","100");
$feltnavn=array(findtekst('500|Ordrenr.', $sprog_id),findtekst('881|Ordredato', $sprog_id),findtekst('940|Modt.nr.', $sprog_id),findtekst('882|Fakt.nr.', $sprog_id),findtekst('883|Fakt.dato', $sprog_id),findtekst('804|Kontonr.', $sprog_id),findtekst('360|Firmanavn', $sprog_id),findtekst('814|Leveres til', $sprog_id),findtekst('884|Sælger', $sprog_id),findtekst('885|Fakturasum', $sprog_id));
}
$vis_feltantal=count($feltbredde);
$vis_linjeantal=100;
}

print "<table width=100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\" valign = \"top\" class='table-Ordrevisning-no-title'><tbody>";
print "<tr><td colspan=\"3\" ><b>".findtekst(535, $sprog_id)."</b></td><td><input class=\"inputbox\" type=text style=\"text-align:right\" size=2 name=vis_feltantal value=$vis_feltantal></td></tr>";
print "<tr><td colspan=\"3\"><b>".findtekst(536, $sprog_id)."</b></td><td><input class=\"inputbox\" type=text style=\"text-align:right\" size=2 name=vis_linjeantal value=$vis_linjeantal></td></tr>";
if ($menu=='T') {
print "<tr><td colspan=7 class='border-hr-top'></td></tr>\n";
} else {
print "<tr><td colspan=7><hr></td></tr>\n";
}
print "<tr><td ><b>Pos</b></td><td colspan=\"2\"><b>".findtekst('543|Felt', $sprog_id)."</b></td><td><b>".findtekst('539|Valgfri overskrift', $sprog_id)."</b></td><td align=\"right\"><b>".findtekst('540|Feltbredde', $sprog_id)."</b></td><td><b>".findtekst('541|Justering', $sprog_id)."</b></td><td><b>".findtekst('542|DropDown', $sprog_id)."</b></td></tr>";
if ($menu=='T') {
print "<tr><td colspan=7 class='border-hr-bottom'></td></tr>\n";
} else {
print "<tr><td colspan=7><hr></td></tr>\n";
}
if (!$feltnavn[0]) $feltnavn[0]="Ordrenr";
if (!$feltbredde[0]) $feltbredde[0]=50;
if ($feltbredde[0]<=10) $feltbredde[0]*=10;
print "<tr><td>".findtekst('2178|Posnr', $sprog_id)."</td>";
print "<td colspan=\"2\">".findtekst('500|Ordrenr.', $sprog_id)."</td>";
print "<td><input class=\"inputbox\" type=text name=feltnavn[0] size=20 value=$feltnavn[0]></td>";
print "<td align=\"right\" width=\"200px\"><input class=\"inputbox\" type=text name=feltbredde[0] style=\"text-align:right;width:$feltbredde[0]px;\"  value=$feltbredde[0]></td>";
print "<td><SELECT class=\"inputbox\" NAME=justering[0]>";
if ($justering[0]) print "<option>$justering[0]</option>";
if ($justering[0] != "L") print "<option value=\"left\" style=\"text-align:left\">left</option>";
if ($justering[0] != "C") print "<option value=\"center\" style=\"text-align:center\">center</option>";
if ($justering[0] != "R") print "<option value=\"right\" style=\"text-align:right\">Right</option>";
print "</SELECT>";
print "<input type='hidden' name='dropDown[0]' value=''></td></tr>";
for ($x=1;$x<=$vis_feltantal;$x++) {
if (!$feltnavn[$x]) $feltnavn[$x]=$vis_felt[$x];
if (!isset($feltbredde[$x])) $feltbredde[$x]=100;
if ($feltbredde[$x]<=10) $feltbredde[$x]*=10;
print "<tr><td><input class=\"inputbox\" type=text name=pos[$x] style=\"text-align:right;width:40px;\" value=$x></td>";
print "<td colspan=2><SELECT class=\"inputbox\" NAME=vis_felt[$x]>";
print "<option>$vis_felt[$x]</option>";
for ($y=0;$y<count($felter);$y++) {
if ($felter[$y]!=$vis_felt[$x]) print "<option>$felter[$y]</option>";
}
print "</SELECT></td>";
print "<td><input class=\"inputbox\" type=text name=feltnavn[$x] size=20 value=$feltnavn[$x]></td>";
print "<td align=\"right\"><input class=\"inputbox\" type=text name=feltbredde[$x] size=2 style=\"text-align:right;width:$feltbredde[$x]px;\" value=$feltbredde[$x]></td>";
print "<td><SELECT class=\"inputbox\" NAME=justering[$x]>";
if ($justering[$x]) print "<option value=\"$justering[$x]\">$justering[$x]</option>";
if ($justering[$x] != "L") print "<option value=\"left\" style=\"text-align:left\">left</option>";
if ($justering[$x] != "C") print "<option value=\"center\" style=\"text-align:center\">center</option>";
if ($justering[$x] != "R") print "<option value=\"right\" style=\"text-align:right\">right</option>";
($dropDown[$x])?$dropDown[$x]='checked':$dropDown[$x]='';
print "</SELECT></td>";
print "<td align='center'><label class='checkContainerVisning'><input class='inputbox' type='checkbox' name='dropDown[$x]' $dropDown[$x]><span class='checkmarkVisning'></span></label></td></tr>";
}
if ($menu=='T') {
print "<tr><td colspan=7 class='border-hr-bottom'></tr>\n";
} else {
print "<tr><td colspan=7><hr></td></tr>\n";
}
print "<tr><td colspan='10' align = 'center'><input type='submit' accesskey='a' value='OK' name='submit'> &nbsp;•&nbsp; <input type='button' onclick=\"location.href='ordreliste.php?valg=$valg&sort=$sort'\" accesskey='L' value='".findtekst('30|Tilbage',$sprog_id)."'></td></tr>\n";
print "</form>";

function sorter($pos,$var,$vis_feltantal) {
$swapped = true;
while ($swapped){
$swapped = false;
for ($i=0;$i<=$vis_feltantal;$i++){
if (!isset($pos[$i]))$pos[$i]=0;
$pos[$i]=str_replace(",",".",$pos[$i]);
if ($i && ($pos[$i-1] > $pos[$i])) {
$tmp=$pos[$i-1];
$pos[$i-1]=$pos[$i];
$pos[$i]=$tmp;
$tmp=$var[$i-1];
$var[$i-1]=$var[$i];
$var[$i]=$tmp;
$swapped = true;
}
}
}
return($var);
}

print "</tbody></table>";

if ($menu=='T') {
include_once '../includes/topmenu/footer.php';
} else {
include_once '../includes/oldDesign/footer.php';
}

?>


