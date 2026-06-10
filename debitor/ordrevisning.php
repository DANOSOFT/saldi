<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------debitor/ordrevisning.php-----lap 4.0.3-------2021.09.14-----------
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
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// 2018.11.28	PHR Tilføjet kundegruppe som søgefelt
// 2098.05.02	PHR Corrected error in first time '$vis_feltantal' 20190502
// 2019.07.03 PHR - Users can now choose whether they want dropdown. Search $dropDown
// 2021.04.20 LOE - Translated these table data #20210420
// 20210720 MSC - Implementing new top menu design
// 20210721 MSC - Implementing new top menu design 
// 20210906 MSC - Implementing new top menu design 
	
@session_start();
$s_id=session_id();
$box4=NULL;


include("../includes/std_func.php");

if (isset($_GET['valg'])) $valg=($_GET['valg']); //???
else $valg="ordrer";

if ($valg=="tilbud") {
	$title=findtekst('545|Tilbudsvisning',$sprog_id);
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


$aa=findtekst('545|Tilbudsvisning',$sprog_id); #20210420
$bb=findtekst('546|Ordrevisning',$sprog_id);
$cc=findtekst('544|Fakturavisning',$sprog_id);

if (isset($_GET['valg'])) $valg=($_GET['valg']);
else $valg="ordrer";

if ($valg=="tilbud") $title= $aa;
elseif ($valg=="ordrer") $title= $bb;
else $title= $cc;

$modulnr=6;



$sort=trim(if_isset($_GET['sort']));

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

# echo "update grupper set box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$vis_linjeantal' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'<br>";
#exit;
	db_modify("update grupper set box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$vis_linjeantal',box10='$box10' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__);
}

if ($menu=='T') {
	$title="Ordrevisning • Kunder";
	$classtable2 ="class=dataTableForm";
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\"><a href=ordreliste.php?valg=$valg&sort=$sort accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
#} elseif ($menu=='S') {
#	include("../includes/sidemenu.php");
#	$classtable2 ="";
} elseif ($menu=='S') {
	$classtable2 ="";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">
		   <table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"4\" cellpadding=\"0\"><tbody>

		   <td width=\"10%\" align=center><a href=ordreliste.php?valg=$valg&sort=$sort accesskey=L>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">".findtekst('30|Tilbage',$sprog_id)."</button></a></td>

		   <td width='80%' align=center style='$topStyle'>$title</td>
		   <td width='10%' align=center style='$topStyle'><br></td></tr>
		   </tr>
		   </tbody></table>
		   </td></tr>";
	print "<center>";
} else {
	$classtable2 ="";
	include("../includes/oldDesign/header.php");
	print  "<tr><td height = \"25\" align=\"center\" valign=\"top\">
			<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"4\" cellpadding=\"0\"><tbody>
			<td width=\"10%\" align=center><div class=\"top_bund\"><a href=ordreliste.php?valg=$valg&sort=$sort accesskey=L>".findtekst('30|Tilbage',$sprog_id)."</a></div></td>
			<td width=\"80%\" align=center><div class=\"top_bund\">$title</a></div></td>
			<td width=\"10%\" align=center><div class=\"top_bund\"><br></div></td>
			</tr>
			</tbody></table>
	</td></tr>";
	print "<center>";
}
print "<div class=\"maincontentLargeHolder\">\n";
 print "<tr><td valign=\"top\" align=\"center\">
<table $classtable2 cellpadding=\"1\" cellspacing=\"1\" border=\"0\" valign = \"top\">
<tbody>";

print "<form name=ordrevisning action=ordrevisning.php?sort=$sort&valg=$valg method=post>";
# $felter=array("konto_id","firmanavn","addr1","addr2","postnr","bynavn","land","kontakt","kundeordnr","lev_navn","lev_addr1","lev_addr2","lev_postnr","lev_bynavn","lev_kontakt","ean","institution","betalingsbet","betalingsdage","kontonr","cvrnr","art","ordredate","levdate","fakturadate","notes","sum","momssats","status","ref","fakturanr","modtagelse","kred_ord_id","lev_adr","kostpris","moms","hvem","tidspkt","nextfakt","betalt","projekt","valuta","valutakurs","sprog","email","mail_fakt","pbs","mail","mail_cc","mail_bcc","mail_subj","mail_text","felt_1","felt_2","felt_3","felt_4","felt_5","vis_lev_addr","udskriv_til","restordre");

$i=0;
$q = db_select("select * from ordrer",__FILE__ . " linje " . __LINE__);
while ($i < db_num_fields($q)) { 
	$field = db_field_name($q, $i);
	$felter[$i] = is_object($field) ? $field->name : (string)$field;
	$i++; 
}
$felter[$i] = 'sum_m_moms';
$i++;
$felter[$i] = 'kundegruppe';
sort($felter);
#$feltantal=count($felter);
print "<tr><td colspan='7' align='center'>".findtekst(537, $sprog_id)."</td></tr>"; #20210420
print "<tr><td colspan='7' align='center'>".findtekst(538, $sprog_id)."</td></tr>";
if ($menu=='T') {
	print "<tr><td colspan=7 class='border-hr-top'></td></tr>\n";
} else {
	print "<tr><td colspan=7><hr></td></tr>\n";
}

#box1, 2, 8 & 9 er reserveret se ordrevisning.php
$r = db_fetch_array(db_select("select box3,box4,box5,box6,box7,box10 from grupper where art = 'OLV' and kode ='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__));
$vis_felt=explode(",",$r['box3']);
$feltbredde=explode(",",$r['box4']);
$justering=explode(",",$r['box5']);
$feltnavn=explode(",",$r['box6']);
$vis_linjeantal=$r['box7'];
$dropDown=explode(",",$r['box10']);
$vis_feltantal=count($feltbredde);
if (!isset($feltnavn[$vis_feltantal])) $vis_feltantal--; #20190502 ('-1' removed from line above)
if (count($feltbredde)<=1) {
	if ($valg=="tilbud") {
		$vis_felt="ordrenr,ordredate,kontonr,firmanavn,ref,sum";
		$justering="right,left,left,left,left,right";
		$feltbredde="50,100,100,150,100,100";
		$feltnavn=findtekst('888|Tilbudsnr.', $sprog_id).",".findtekst('889|Tilbudsdato', $sprog_id).",".findtekst('43|Kontonr.', $sprog_id).",".findtekst('28|Firmanavn', $sprog_id).",".findtekst('884|Sælger', $sprog_id).",".findtekst('890|Tilbudssum', $sprog_id);
	} elseif ($valg=="ordrer") {
		$vis_felt="ordrenr,ordredate,levdate,kontonr,firmanavn,ref,sum";
		$justering="right,left,left,left,left,left,right";
		$feltbredde="50,100,100,100,150,100,100";
		$feltnavn="Ordrenr.,Ordredato,Levdato,Kontonr.,Firmanavn,S&aelig;lger,Ordresum";
	} elseif ($valg=="faktura") {
		$vis_felt=array("ordrenr","ordredate","fakturanr","fakturadate","nextfakt","kontonr","firmanavn","ref","sum");
		$justering=array("right","left","right","left","left","left","left","left","right");
		$feltbredde=array("50","100","100","100","100","150","100","100","100");
		$feltnavn=array("Ordrenr.","Ordredato","Fakt.nr.","Fakt.dato","Genfakt.","Kontonr.","Firmanavn","S&aelig;lger","Fakturasum");
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
		if (!isset($pos[$i]))	$pos[$i]=0;
		$pos[$i]=str_replace(",",".",$pos[$i]);
			if ($i && ($pos[$i-1] > $pos[$i])) {
				$tmp=$pos[$i-1];
				$pos[$i-1]=$pos[$i];
				$pos[$i]=$tmp;
				$tmp=$var[$i-1];
				$var[$i-1]=$var[$i];
				$var[$i]=$tmp;
				$swapped = true;
			} #end if
# echo "$pos[$i] $var[$i]<br>";
		} #end for
	} # end while
	return($var);
}

print "</tbody></table>";

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>
