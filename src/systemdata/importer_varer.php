<?php
//                      ___   _   _   ___  _     ___  _ _
//                     / __| / \ | | |   \| |   |   \| / /
//                     \__ \/ _ \| |_| |) | | _ | |) |  <
//                     |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/importer_varer.php --- 4.1.1 --- 2025-10-07 ---
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
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------------
// tilføjet vejl.pris til import.
// 2014.02.01 Gjort søgning på eksisterende varenumre case uafhængig 20140201
// 2014.07.18 Alle felter kan nu vælges v.import. Søg 20140718
// 20150612 CA  Mindre rettelse af tekster og oprydning af HTML-kode.
// 20151210 CA  Rettelse af stavefejl
// 20160219 PHR Indsat "and art = 'K'" ved adresseopslag så den ikke fejlagtigt finder en kunde. 20160219
// 20160224 PHR Ændret $id til $vare_id.
// 20161015 PHR Eksisterende varenumre som er numeriske og starter med 0 findes nu selvom et 0'et er fjernet af f.eks et regneark. 20161015
// 20170509 Tilføjet varemærke (trademark);
// 20171024 PHR Erstatter '<br>' med '\n' i notes 20171024
// 20180404 PHR Lokationer skrives nu også i lagerstatus. 20180404
// 20200602	PHR newlines (notes) in text is now handled. 20200602
// 20210714 LOE - Translated some text.
// 20220218 First item line was in somecases omittet.
// 20220628 PHR like 20220218 and corrected type in salgspris_ex_moms
// 20221004 MLH added filterOption, salesPriceFromPurchasePrice, salesPriceRoundingMethod, salesPriceMethod, tierPriceFromPurchasePrice, tierPriceRoundingMethod, tierPriceMethod
// 20221025 MLH fixed a programming issue regarding the value of POST variable "submit"
// 20230523 PHR php8
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20251007 PHR aliases

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Import&eacute;r varer";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
$returside="diverse.php?sektion=div_io";
$csvFile = NULL;
$retailPriceFromPurchasePrice = $salesPriceFromPurchasePrice = $tierPriceFromPurchasePrice = NULL;
$feltnavn = array();

print "<div align=\"center\">\n";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1

print "<td width=\"170px\"><a href=\"$returside\" accesskey=\"L\">
       <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst(30, $sprog_id)."</button></a></td>

       <td align='center' style='$topStyle'>".findtekst(1380, $sprog_id)."<br></td>

       <td width=\"170px\" style='$topStyle'><br></td></tr>
       </tbody></table></td></tr>"; # <- tabel 1.1.1

print "</tr></tbody></table></td></tr>";

$itemGroup = NULL;
$show_autocalculate = false;

$submit = if_isset($_POST['submit']);
$import = if_isset($_POST['import'],NULL);
if($submit || $import) {
	$filnavn				= if_isset($_POST['filnavn'],NULL);
	$splitter				= if_isset($_POST['splitter'],NULL);
	$feltnavn				= if_isset($_POST['feltnavn'],array());
	$feltantal			= if_isset($_POST['feltantal'],NULL);
	$varenr					= if_isset($_POST['varenr'],NULL);
	$csvFile				= if_isset($_POST['csvFile'],NULL);
	$tegnset				= if_isset($_POST['tegnset'],NULL);
	$itemGroup			= if_isset($_POST['itemGroup'],NULL);
	$show_autocalculate	= if_isset($_POST['show_autocalculate'],NULL); #20221004
	$import             = if_isset($_POST['import'],NULL);

	// BEGIN 20221004
	$filterOption="all_products";
	$tmp = if_isset($_POST['filterOption'],NULL);
	if (($tmp == "all_products")||($tmp == "only_existing_product")||($tmp == "only_unknown_products")) {
		$filterOption = $tmp;
	}
	if ($show_autocalculate) $salesPriceFromPurchasePrice=usdecimal(if_isset($_POST['salesPriceFromPurchasePrice']));
	$salesPriceMethod="percentage";
	$tmp = if_isset($_POST['salesPriceMethod'],NULL);
	if (($tmp=="percentage")||($tmp=="amount")){
		$salesPriceMethod=$tmp;
	}
	$salesPriceRoundingMethod="no_rounding";
	$tmp = if_isset($_POST['salesPriceRoundingMethod'],NULL);
	if (($tmp == "st_rounding")||($tmp == "rounding_up")||($tmp == "round_down")){
		$salesPriceRoundingMethod=$_POST['salesPriceRoundingMethod'];
	}
	//cho $salesPriceFromPurchasePrice." (salesPriceFromPurchasePrice)<br>";
	//cho myAddToPriceFunc(1000, $salesPriceRoundingMethod, $salesPriceFromPurchasePrice, $salesPriceMethod)." (myAddToPriceFunc med 1000 i købspris)<br><br>";
	if ($show_autocalculate) $tierPriceFromPurchasePrice=usdecimal(if_isset($_POST['tierPriceFromPurchasePrice']));
	$tierPriceMethod="percentage";
	$tmp = if_isset($_POST['tierPriceMethod'],NULL);
	if (($tmp=="percentage")||($tmp=="amount")){
		$tierPriceMethod=$tmp;
	}
	$tierPriceRoundingMethod="no_rounding";
	$tmp = if_isset($_POST['tierPriceRoundingMethod'],NULL);
	if (($tmp=="st_rounding")||($tmp=="rounding_up")||($tmp=="round_down")){
		$tierPriceRoundingMethod=$tmp;
	}
	//cho $tierPriceFromPurchasePrice." (tierPriceFromPurchasePrice)<br>";
	//cho myAddToPriceFunc(1000, $tierPriceRoundingMethod, $tierPriceFromPurchasePrice, $tierPriceMethod)." (myAddToPriceFunc med 1000 i købspris)<br><br>";
	if ($show_autocalculate) $retailPriceFromPurchasePrice=usdecimal(if_isset($_POST['retailPriceFromPurchasePrice']));
	$retailPriceMethod="percentage";
	$tmp = if_isset($_POST['retailPriceMethod'],NULL);
	if (($tmp=="percentage")||($tmp=="amount")){
		$retailPriceMethod=$tmp;
	}
	$retailPriceRoundingMethod="no_rounding";
	$tmp = if_isset($_POST['retailPriceRoundingMethod'],NULL);
	if (($tmp=="st_rounding")||($tmp=="rounding_up")||($tmp=="round_down")){
		$retailPriceRoundingMethod=$tmp;
	}
	//cho $retailPriceFromPurchasePrice." (retailPriceFromPurchasePrice)<br>";
	//cho myAddToPriceFunc(1000, $retailPriceRoundingMethod, $retailPriceFromPurchasePrice, $retailPriceMethod)." (myAddToPriceFunc med 1000 i købspris)<br><br>";
	//exit();
	// END 20221004



	if (in_array('gruppe',$feltnavn)) $itemGroup = NULL;
	if (isset ($_FILES['uploadedfile']['name']) && basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			vis_data($filnavn,'','','1',$varenr,$csvFile,$tegnset,$itemGroup,$filterOption,$show_autocalculate,$salesPriceFromPurchasePrice,$salesPriceMethod,$salesPriceRoundingMethod,$tierPriceFromPurchasePrice,$tierPriceMethod,$tierPriceRoundingMethod,$retailPriceFromPurchasePrice,$retailPriceMethod,$retailPriceRoundingMethod);
		} else echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
	} elseif($submit==findtekst(1133, $sprog_id)){ #20221025
		vis_data($filnavn,$splitter,$feltnavn,$feltantal,$varenr,$csvFile,$tegnset,$itemGroup,$filterOption,$show_autocalculate,$salesPriceFromPurchasePrice,$salesPriceMethod,$salesPriceRoundingMethod,$tierPriceFromPurchasePrice,$tierPriceMethod,$tierPriceRoundingMethod,$retailPriceFromPurchasePrice,$retailPriceMethod,$retailPriceRoundingMethod);
	}	elseif($import) { #20221025
		if (($filnavn)&&($splitter))	overfoer_data($filnavn,$splitter,$feltnavn,$feltantal,$tegnset,$itemGroup,$filterOption,$show_autocalculate,$salesPriceFromPurchasePrice,$salesPriceMethod,$salesPriceRoundingMethod,$tierPriceFromPurchasePrice,$tierPriceMethod,$tierPriceRoundingMethod,$retailPriceFromPurchasePrice,$retailPriceMethod,$retailPriceRoundingMethod);
		else vis_data($filnavn,$splitter,$feltnavn,$feltantal,$varenr,$csvFile,$tegnset,$itemGroup,$filterOption,$show_autocalculate,$salesPriceFromPurchasePrice,$salesPriceMethod,$salesPriceRoundingMethod,$tierPriceFromPurchasePrice,$tierPriceMethod,$tierPriceRoundingMethod,$retailPriceFromPurchasePrice,$retailPriceMethod,$retailPriceRoundingMethod);
	}
} else {
	$qtxt = "select box1, box2, beskrivelse from grupper where art='RA' order by kodenr desc";
	if (!$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	}
	upload($csvFile);
}
print "</tbody></table>";
print "</body></html>";
#####################################################################################################
function upload($csvFile){
	global $sprog_id;

	print "<form enctype='multipart/form-data' action='importer_varer.php' method='POST'>\n";
	print "<tr><td width='100%' align='center'><table width='100%' border='0' cellspacing='0' cellpadding='0'><tbody>\n";
	#print "<input type='hidden' name='MAX_FILE_SIZE' value='900000'>\n";
	print "<input type='hidden' name='MAX_FILE_SIZE' value='2900000'>\n";
	print "<input type='hidden' name='csvFile' value='$csvFile'>\n";
	print "<tr><td width='100%' align='center'> ".findtekst(1364, $sprog_id).": <input name='uploadedfile' type='file' /><br /></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td align='center'><input type='submit' name='submit' value='".findtekst(1078, $sprog_id)."' /></td></tr>\n";
	#print "</tbody></table>\n";
	#print "</td></tr>\n";
	print "<tr><td>&nbsp;</td></tr>\n";
	print "</form>\n";
} # end function upload

function vis_data($filnavn,$splitter,$feltnavn,$feltantal,$varenr,$csvFile,$tegnset,$itemGroup,$filterOption,$show_autocalculate,$salesPriceFromPurchasePrice,$salesPriceMethod,$salesPriceRoundingMethod,$tierPriceFromPurchasePrice,$tierPriceMethod,$tierPriceRoundingMethod,$retailPriceFromPurchasePrice,$retailPriceMethod,$retailPriceRoundingMethod) {
global $charset,$regnaar,$sprog_id;

$feltnavn = if_isset($feltnavn,array());
$komma = $semikolon = $tabulator = 0;

$fp=fopen("$filnavn","r");
if ($fp) {
	for ($y=1; $y<4; $y++) {
		$tmp=fgets($fp);
		if($tmp) $linje=$tmp;
	}
	if ($charset=='UTF-8' && $tegnset!='UTF-8') $linje=mb_convert_encoding($linje, 'UTF-8', 'ISO-8859-1');
	elseif ($charset!='UTF-8' && $tegnset=='UTF-8') $linje=mb_convert_encoding($linje, 'ISO-8859-1', 'UTF-8');
	$tmp=$linje;
	while ($tmp=substr(strstr($tmp,";"),1)) {$semikolon++;}
	$tmp=$linje;
	while ($tmp=substr(strstr($tmp,","),1)) {$komma++;}
	$tmp=$linje;
	while ($tmp=substr(strstr($tmp,chr(9)),1)) {$tabulator++;}
	$tmp='';
	if (($komma>$semikolon)&& ($komma>$tabulator)) {$tmp='Komma'; $feltantal=$komma;}
	elseif (($semikolon>$tabulator)&&($semikolon>$komma)) {$tmp='Semikolon'; $feltantal=$semikolon;}
	elseif (($tabulator>$semikolon)&&($tabulator>$komma)) {$tmp='Tabulator'; $feltantal=$tabulator;}
	if (!$splitter) {$splitter=$tmp;}
	$cols=$feltantal+1;
}
fclose($fp);
$tmp='';
if ($feltnavn) {
	for ($y=0; $y<=$feltantal; $y++) {
 		if ($tmp) $tmp=$tmp.";".$feltnavn[$y];
		else $tmp=$feltnavn[$y];
	}
	$tmp.="|$itemGroup";
	setcookie("saldi_vareimp",$tmp,time()+60*60*24*30);
} elseif (isset($_COOKIE['saldi_vareimp'])) {
	list($tmp,$itemGroup) =  explode("|",$_COOKIE['saldi_vareimp']);
	$feltnavn=explode(";",$tmp);
}
$v = 0;
$q = db_select("select varenr from varer",__FILE__ . " linje " . __LINE__); #20161015 -> 4 linjer
while($r=db_fetch_array($q)){
	$v_nr[$v]=$r['varenr'];
	$v++;
}
$vg = 0;
$qtxt = "select * from grupper where art = 'VG' and fiscal_year = '$regnaar' order by kodenr";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)){
	$vGrNr[$vg]=$r['kodenr'];
	$vGrTxt[$vg]=$r['beskrivelse'];
	$vg++;
}
print "<tr><td width='100%' align='center'><table width='100%' border='0' cellspacing='1' cellpadding='1'><tbody>\n";
print "<form enctype='multipart/form-data' action='importer_varer.php' method='POST'>\n";
#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
print "<tr><td colspan='$cols' align='left'>";
print "<span title='".findtekst(2074, $sprog_id)." ".findtekst(2073, $sprog_id)."'>";
print "&nbsp;Varegruppe<select name='itemGroup'>\n";
if (!$itemGroup) print "<option value = ''>".findtekst(2073, $sprog_id)."</option>\n";
for ($x=0;$x<count($vGrNr);$x++) {
	if ($vGrNr[$x] == $itemGroup) print "<option value = '$vGrNr[$x]'>$vGrNr[$x]: $vGrTxt[$x]</option>";
}
if ($itemGroup) print "<option value = ''>".findtekst(2073, $sprog_id)."</option>\n";
for ($x=0;$x<count($vGrNr);$x++) {
	if ($vGrNr[$x] != $itemGroup) print "<option value = '$vGrNr[$x]'>$vGrNr[$x]: $vGrTxt[$x]</option>";
}
print "</select></span>\n";

print "<span title='Angiv tegnsæt for import'>&nbsp;".findtekst(1376, $sprog_id)."<select name='tegnset'>\n";
if ($tegnset=='ISO-8859-1') print "    <option>ISO-8859-1</option>\n";
if ($tegnset=='UTF-8') print "    <option>UTF-8</option>\n";
if ($tegnset!='ISO-8859-1') print "    <option>ISO-8859-1</option>\n";
if ($tegnset!='UTF-8') print "    <option>UTF-8</option>\n";
print "</select></span>\n";

print "<span title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'>&nbsp;".findtekst(1377, $sprog_id)."<select name=splitter>\n";
if ($splitter) {print "    <option>$splitter</option>\n";}
if ($splitter!='Semikolon') print "    <option>".findtekst(1378, $sprog_id)."</option>\n";
if ($splitter!='Komma') print "    <option>Komma</option>\n";
if ($splitter!='Tabulator') print "    <option>Tabulator</option>\n";
print "</select></span>\n";

print "<span title='".findtekst(2071, $sprog_id)."'>&nbsp;".findtekst(2060, $sprog_id)."<select name='filterOption'>\n";
print "    <option value='all_products'".(($filterOption=='all_products')?" selected='selected'":"").">".findtekst(2061, $sprog_id)."</option>\n";
print "    <option value='only_existing_product'".(($filterOption=='only_existing_product')?" selected='selected'":"").">".findtekst(2062, $sprog_id)."</option>\n";
print "    <option value='only_unknown_products'".(($filterOption=='only_unknown_products')?" selected='selected'":"").">".findtekst(2072, $sprog_id)."</option>\n";
print "</select></span>\n";

print "<span title='".findtekst(2070, $sprog_id)."'>&nbsp;".findtekst(2081, $sprog_id)."<input type='checkbox' name='show_autocalculate' ". (($show_autocalculate)?"checked='checked'":"") ."></span>\n";

if ($show_autocalculate) {
	print "<br>\n";
	print "<span title='".findtekst(2070, $sprog_id)."'>\n";
	print "&nbsp;".findtekst(2076, $sprog_id)."<input name='salesPriceFromPurchasePrice'  type='text' style='text-align:right' size='8' value='".dkdecimal($salesPriceFromPurchasePrice)."' />\n";
	print "<select name='salesPriceMethod'>\n";
	print "    <option value='percentage'".(($salesPriceMethod=='percentage')?" selected='selected'":"").">".findtekst(2064, $sprog_id)."</option>\n";
	print "    <option value='amount'".(($salesPriceMethod=='amount')?" selected='selected'":"").">".findtekst(2065, $sprog_id)."</option>\n";
	print "</select>\n";
	print "<select name='salesPriceRoundingMethod'>\n";
	print "    <option value='no_rounding'".(($salesPriceRoundingMethod=='no_rounding')?" selected='selected'":"").">".findtekst(2066, $sprog_id)."</option>\n";
	print "    <option value='st_rounding'".(($salesPriceRoundingMethod=='st_rounding')?" selected='selected'":"").">".findtekst(2069, $sprog_id)."</option>\n";
	print "    <option value='rounding_up'".(($salesPriceRoundingMethod=='rounding_up')?" selected='selected'":"").">".findtekst(2067, $sprog_id)."</option>\n";
	print "    <option value='round_down'".(($salesPriceRoundingMethod=='round_down')?" selected='selected'":"").">".findtekst(2067, $sprog_id)."</option>\n";
	print "</select>\n";
	print "</span>\n";

	print "<br>\n";
	print "<span title='".findtekst(2070, $sprog_id)."'>\n";
	print "&nbsp;".findtekst(2075, $sprog_id)."<input name='tierPriceFromPurchasePrice'  type='text' style='text-align:right' size='8' value='".dkdecimal($tierPriceFromPurchasePrice)."' />\n";
	print "<select name='tierPriceMethod'>\n";
	print "    <option value='percentage'".(($tierPriceMethod=='percentage')?" selected='selected'":"").">".findtekst(2064, $sprog_id)."</option>\n";
	print "    <option value='amount'".(($tierPriceMethod=='amount')?" selected='selected'":"").">".findtekst(2065, $sprog_id)."</option>\n";
	print "</select>\n";
	print "<select name='tierPriceRoundingMethod'>\n";
	print "    <option value='no_rounding'".(($tierPriceRoundingMethod=='no_rounding')?" selected='selected'":"").">".findtekst(2066, $sprog_id)."</option>\n";
	print "    <option value='st_rounding'".(($tierPriceRoundingMethod=='st_rounding')?" selected='selected'":"").">".findtekst(2069, $sprog_id)."</option>\n";
	print "    <option value='rounding_up'".(($tierPriceRoundingMethod=='rounding_up')?" selected='selected'":"").">".findtekst(2067, $sprog_id)."</option>\n";
	print "    <option value='round_down'".(($tierPriceRoundingMethod=='round_down')?" selected='selected'":"").">".findtekst(2067, $sprog_id)."</option>\n";
	print "</select>\n";
	print "</span>\n";

	print "<br>\n";
	print "<span title='".findtekst(2070, $sprog_id)."'>\n";
	print "&nbsp;".findtekst(2080, $sprog_id)."<input name='retailPriceFromPurchasePrice'  type='text' style='text-align:right' size='8' value='".dkdecimal($retailPriceFromPurchasePrice)."' />\n";
	print "<select name='retailPriceMethod'>\n";
	print "    <option value='percentage'".(($retailPriceMethod=='percentage')?" selected='selected'":"").">".findtekst(2064, $sprog_id)."</option>\n";
	print "    <option value='amount'".(($retailPriceMethod=='amount')?" selected='selected'":"").">".findtekst(2065, $sprog_id)."</option>\n";
	print "</select>\n";
	print "<select name='retailPriceRoundingMethod'>\n";
	print "    <option value='no_rounding'".(($retailPriceRoundingMethod=='no_rounding')?" selected='selected'":"").">".findtekst(2066, $sprog_id)."</option>\n";
	print "    <option value='st_rounding'".(($retailPriceRoundingMethod=='st_rounding')?" selected='selected'":"").">".findtekst(2069, $sprog_id)."</option>\n";
	print "    <option value='rounding_up'".(($retailPriceRoundingMethod=='rounding_up')?" selected='selected'":"").">".findtekst(2067, $sprog_id)."</option>\n";
	print "    <option value='round_down'".(($retailPriceRoundingMethod=='round_down')?" selected='selected'":"").">".findtekst(2067, $sprog_id)."</option>\n";
	print "</select>\n";
	print "</span>\n";
	print "<br>\n";
}
print "<input type='hidden' name='filnavn' value='$filnavn'>\n";
print "<input type='hidden' name='feltantal' value='$feltantal'>\n";
print "&nbsp; <input type='submit' name='submit' value='" . findtekst('1133|Vis', $sprog_id) . "' />\n";

#20140718
$felt_navn=array("varenr","varenr_alias","stregkode","varemærke","beskrivelse","beskrivelse_alias","kostpris","salgspris_excl_moms","salgspris_incl_moms","vejl.pris","notes","enhed","enhed2","forhold","gruppe","provisionsfri","leverandor","min_lager","max_lager","lokation","lukket","serienr","samlevare","delvare","trademark","retail_price","netweight", "special_price","campaign_cost","tier_price","open_colli_price","colli","outer_colli","outer_colli_price", "special_from_date","special_to_date","komplementaer","circulate","operation","prisgruppe","tilbudgruppe","rabatgruppe", "dvrg","m_type","m_rabat","m_antal","folgevare", "kategori", "varianter", "publiceret","indhold","montage","demontage");


$felt_antal=count($felt_navn);
for ($y=0; $y<=$feltantal; $y++) {
	$feltnavn[$y] = if_isset($feltnavn[$y],NULL);
	for ($x=0; $x < $felt_antal; $x++) {
		$felt_navn[$x]  = if_isset($felt_navn[$x],NULL);
		$felt_aktiv[$x] = if_isset($felt_aktiv[$x],NULL);
		if ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x] && $felt_aktiv[$x]==1) {
			print "<body onLoad=\"javascript:alert('Der kan kun v&aelig;re &eacute;n kolonne med $felt_navn[$x]')\">"; #20151210
			$feltnavn[$y]='';
		} elseif ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x]) $felt_aktiv[$x]=1;
	}
	if ($feltnavn[$y]=='varenr') $varenr=1;
	if ($feltnavn[$y]=='beskrivelse') $beskrivelse=1;
}

if ($filnavn && $splitter && $varenr == 1) print "&nbsp; <input type=\"submit\" name=\"import\" value=\"".findtekst('1074|Importer', $sprog_id)."\" /></td></tr>\n";
print "<tr><td colspan=$cols><hr></td></tr>\n";
if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
elseif ($splitter=='Komma') {$splitter=',';}
elseif ($splitter=='Tabulator') {$splitter=chr(9);}
for ($y=0; $y<count($feltnavn); $y++) {
	$feltnavn[$y] = if_isset($feltnavn[$y],NULL);
	if ($feltnavn[$y]) print "<td><select name=feltnavn[$y]>\n";
	else  print "<td align=center><select name=feltnavn[$y]>\n";
	if ($feltnavn[$y] && $feltnavn[$y] != '-') print "<option>$feltnavn[$y]</option>\n";
		print "<option value = '-'></option>\n";
	for ($x=0; $x<=$felt_antal; $x++) {
		if ($feltnavn[$y]!=$felt_navn[$x]) print "<option>$felt_navn[$x]</option>\n";
	}
	print "</td>\n";
}

print "</form></td></tr>";
$fp=fopen("$filnavn","r");
if ($fp) {
	$preLine='';
	$colNum=1;
	$x=0;
	$kontonumre=array();
	while (!feof($fp)) {
		$skriv_linje=0;
		$varenr=NULL;
		if ($linje=fgets($fp)) {
			for($c=0;$c<strlen($linje);$c++) {
				if (substr($linje,$c,1)==$splitter) {
					$colNum++;
				}
			}
			if ($colNum < $cols) {
				$preLine.= $linje;
			}	else {
			$x++;
			if ($preLine) {
				$preLine.= $linje;
				$linje = $preLine;
			}
			$colNum=1;
			$preLine='';
			$skriv_linje=1;
			if ($charset=='UTF-8' && $tegnset!='UTF-8') $linje=mb_convert_encoding($linje, 'UTF-8', 'ISO-8859-1');
			elseif ($charset!='UTF-8' && $tegnset=='UTF-8') $linje=mb_convert_encoding($linje, 'ISO-8859-1', 'UTF-8');
			$felt=array();
			$felt = opdel($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$feltnavn[$y] = if_isset($feltnavn[$y],NULL);
				$feltfejl[$y]=0;
				$felt[$y]=trim($felt[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='varenr') { #20161015
					$varenr=trim($felt[$y]);
				}
				if ($feltnavn[$y]=='vejl_pris' || $feltnavn[$y]=='vejl.pris') $feltnavn[$y]='retail_price';
				if ($feltnavn[$y]=='varemærke') $feltnavn[$y]='trademark';
				if ($feltnavn[$y]=='kostpris')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
#				if ($feltnavn[$y]=='salgspris_ex_moms') $feltnavn[$y] = 'salgspris_excl_moms';
				if ($feltnavn[$y]=='salgspris_excl_moms')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='salgspris_incl_moms')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='retail_price')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='montage')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='demontage')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='min_lager')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='max_lager')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='netweight')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
				}
				if ($feltnavn[$y]=='gruppe')	{
					if (!$tmp=find_varegrp($felt[$y])) $feltfejl[$y]=1;
				}
				if ($feltnavn[$y]=='leverandor')	{
					if ($felt[$y] && !$tmp=find_lev_id($felt[$y])) $feltfejl[$y]=1;
				}
#				if ($feltnavn[$y]=='varenr'&&!is_numeric($felt[$y])) {
#					$skriv_linje=2;
#					print "<BODY onLoad=\"javascript:alert('R&oslash;de linjer indeholder fejl (kontonummer ikke numerisk) og bliver ikke importeret')\">";
#					print "<BODY onLoad=\"javascript:alert('varenrnummer skal v&aelig;re numerisk')\">";
#				}
			}
 		}
 		}
		if ($skriv_linje==2 || in_array($varenr,$v_nr)) { #20161015
			$txt=findtekst(1381, $sprog_id).findtekst(1382, $sprog_id);
			print "<BODY onLoad=\"javascript:alert('$txt')\">";
		}
		if ($skriv_linje>=1){
			print "<tr>";
#			print "<tr><td>$csvFile</td>";
			for ($y=0; $y<=$feltantal; $y++) {
				$color="#000000"; // black
				if (($skriv_linje==2)||($feltfejl[$y])) $color="#e00000"; // red
				elseif ((($filterOption=='only_unknown_products')&&(in_array($varenr,$v_nr)))||(($filterOption=='only_existing_product')&&(!in_array($varenr,$v_nr)))) $color="#e00000"; // red
				elseif (in_array($varenr,$v_nr)) $color="#00e000"; // green
				if ($feltnavn[$y]) {print "<td><span style=\"color: $color;\">$felt[$y]&nbsp;</span></td>";}
				else {print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";}
			}
			print "</tr>";
		}
	}
}
fclose($fp);
print "</tbody></table>";
print "</td></tr>";
} # end function vis_data

function overfoer_data($filnavn,$splitter,$feltnavn,$feltantal,$tegnset,$itemGroup,$filterOption,$show_autocalculate,$salesPriceFromPurchasePrice,$salesPriceMethod,$salesPriceRoundingMethod,$tierPriceFromPurchasePrice,$tierPriceMethod,$tierPriceRoundingMethod,$retailPriceFromPurchasePrice,$retailPriceMethod,$retailPriceRoundingMethod) {
global $charset,$regnaar;

if ($itemGroup) {
	$VATrate = 0;
	$qtxt = "select box4 from grupper where box4 != '' and art = 'VG' and kodenr = '$itemGroup' and fiscal_year = '$regnaar'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$qtxt = "select moms from kontoplan where kontonr = '$r[box4]' order by id desc limit 1";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$VATcode = substr($r['moms'],0,1).'M';
			$VATcodeNo = substr($r['moms'],1);
			$qtxt = "select box2 from grupper where art = '$VATcode' and kodenr = '$VATcodeNo' and fiscal_year = '$regnaar'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$VATrate = $r['box2'];
			}
		}
	}
}

$x=0;
$fp=fopen("../importfiler/postnr.csv","r");
if ($fp) {
	while (!feof($fp)) {
		$linje=fgets($fp);
		if ($linje) {
			$x++;
			list($postnr[$x],$bynavn[$x])=explode(chr(9),$linje);
		}
	}
}
fclose($fp);
$postnr_antal=$x;
$komma = $semikolon = $tabulator = 0;

$fp=fopen("$filnavn","r");
if ($fp) {
	for ($y=1; $y<4; $y++) {
		$tmp=fgets($fp);
		if($tmp) $linje=$tmp;
	}
	$tmp=$linje;
	while ($tmp=substr(strstr($tmp,";"),1)) {$semikolon++;}
	$tmp=$linje;
	while ($tmp=substr(strstr($tmp,","),1)) {$komma++;}
	$tmp=$linje;
	while ($tmp=substr(strstr($tmp,chr(9)),1)) {$tabulator++;}
	$tmp='';
	if (($komma>$semikolon)&& ($komma>$tabulator)) {$tmp='Komma'; $feltantal=$komma;}
	elseif (($semikolon>$tabulator)&&($semikolon>$komma)) {$tmp='Semikolon'; $feltantal=$semikolon;}
	elseif (($tabulator>$semikolon)&&($tabulator>$komma)) {$tmp='Tabulator'; $feltantal=$tabulator;}
	if (!$splitter) {$splitter=$tmp;}
	$cols=$feltantal+1;
}
fclose($fp);

for ($y=0; $y<count($feltnavn); $y++) {
	for ($x=0; $x<=$felt_antal; $x++) {
		if ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x]&& $felt_aktiv[$x]==1) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med $felt_navn[$x]')\">";
		$feltnavn[$y]='';
		} elseif ($felt_navn[$x] && $feltnavn[$y]==$felt_navn[$x]) $felt_aktiv[$x]=1;
	}
}
print "<tr><td colspan=$cols><hr></td></tr>\n";
if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
elseif ($splitter=='Komma') {$splitter=',';}
elseif ($splitter=='Tabulator') {$splitter=chr(9);}

transaktion('begin');
$v=0;
$q=db_select("select id,varenr from varer",__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)){
	$v_id[$v]=$r['id'];
	$v_nr[$v]=$r['varenr'];
	$v++;
}


$fp=fopen("$filnavn","r");
if ($fp) {
	$kontonumre=array();
	$x=0;
	$colNum=$imp_antal=$kostpris=$salgspris=$upd_antal=0;
	$lokation=0;
	$varenr="";
	$salgspris_isset=$tier_price_isset=$retail_price_isset=false;
	while (!feof($fp)) {
		$skriv_linje=0;
 		if ($linje=fgets($fp)) {
			$colNum = 0; # 20220628 Inserted this
#			$felt = explode($splitter,$linje);
#			$colNum = count($felt);
			$count = 1;
			for($c=0;$c<strlen($linje);$c++) {
				if ((substr($linje,$c,2)) == '"'.$splitter) $count=0; # 20220628 Inserted this
				if ((substr($linje,$c,2)) == $splitter.'"') $count=1; # 20220628 Inserted this
				if ($count && substr($linje,$c,1)==$splitter) {
					$colNum++;
				}
			}
			if ($colNum && $cols) { # 20220628 '>=' changed to '&&'
#				$preLine.= $linje;

#			}	else {
				$x++;
				if ($preLine) {
					$preLine.= $linje;
					$linje = $preLine;
				}
				$colNum=1;
				$preLine='';
				$skriv_linje=1;
				if ($charset=='UTF-8' && $tegnset!='UTF-8') $linje=mb_convert_encoding($linje, 'UTF-8', 'ISO-8859-1');
				elseif ($charset!='UTF-8' && $tegnset=='UTF-8') $linje=mb_convert_encoding($linje, 'ISO-8859-1', 'UTF-8');
				$vare_id=0;
				$felt=array();
				$felt = opdel($splitter, $linje);
				for ($y=0; $y<count($felt); $y++) {
					$medtag_felt[$y]=1;
					if (!trim($feltnavn[$y])) $medtag_felt[$y]=0;
					$felt[$y]=trim($felt[$y]);
					$feltnavn[$y]=strtolower($feltnavn[$y]);
					if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
					if ($feltnavn[$y]=='varenr') {
					$varenr = $felt[$y];
#					if ($varenr != $felt[$y]) $skriv_linje=0;
				}
				if ($feltnavn[$y]=='vejl.pris') $feltnavn[$y]='retail_price';
				if ($feltnavn[$y]=='varemærke') $feltnavn[$y]='trademark';
				if ($feltnavn[$y]=='lokation') {
					$feltnavn[$y]='location';
				}
				if ($feltnavn[$y]=='kostpris')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=0;
					elseif (!is_numeric($felt[$y])) $felt[$y]=usdecimal($felt[$y]);
					$kostpris=(float)$felt[$y];
				}
				if ($feltnavn[$y]=='salgspris_ex_moms') $feltnavn[$y] = 'salgspris_excl_moms';
				if ($feltnavn[$y]=='salgspris_excl_moms')	{
					$salgspris_isset=true;
					$nyt_feltnavn[$y] = 'salgspris';
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=0;
					elseif (!is_numeric($felt[$y])) $felt[$y]=usdecimal($felt[$y]);
					$salgspris=(float)$felt[$y];
				}
				if ($feltnavn[$y]=='salgspris_incl_moms')	{
					$salgspris_isset=true;
					$nyt_feltnavn[$y] = 'salgspris';
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=0;
					elseif (!is_numeric($felt[$y])) $felt[$y]=usdecimal($felt[$y]);
					if ($itemGroup && $VATrate) {
						$felt[$y] = (float)$felt[$y]*100/(100+$VATrate);
					}
					$salgspris=(float)$felt[$y];
				}
				if ($feltnavn[$y]=='retail_price')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=0;
					elseif (!is_numeric($felt[$y])) $felt[$y]=usdecimal($felt[$y]);
					$retail_price=(float)$felt[$y];
					$retail_price_isset=true;
				}
				if ($feltnavn[$y]=='montage')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=0;
					elseif (!is_numeric($felt[$y])) $felt[$y]=usdecimal($felt[$y]);
				}
				if ($feltnavn[$y]=='demontage')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=0;
					elseif (!is_numeric($felt[$y])) $felt[$y]=usdecimal($felt[$y]);
				}
				if ($feltnavn[$y]=='min_lager')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
					else $felt[$y]=usdecimal($felt[$y]);
				}
 				if ($feltnavn[$y]=='max_lager')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
					else $felt[$y]=usdecimal($felt[$y]);
				}
 				if ($feltnavn[$y]=='netweight')	{
					$tmp=str_replace(",","",$felt[$y]);
					$tmp=str_replace(".","",$tmp);
					if ($tmp && !is_numeric($tmp)) $skriv_linje=2;
					else $felt[$y]=usdecimal($felt[$y]);
				}
 				if ($feltnavn[$y]=='gruppe')	{
					if ($tmp=find_varegrp($felt[$y])) $felt[$y]=$tmp;
					else $felt[$y]=1;
				}
				if ($feltnavn[$y]=='leverandor')	{
					if ($felt[$y] && !$tmp=find_lev_id($felt[$y]));
					$leverandor=$tmp;
					$levfelt=$y;
				}
				if ($feltnavn[$y]=='notes') { #20171024
					$felt[$y]=str_replace("<br>","\n",$felt[$y]);
				}
				if ($feltnavn[$y]=='location') { #20180404
					$lokation=1;
					$location=$felt[$y];
				}
				if ($feltnavn[$y]=='tier_price') {
					$tier_price_isset=true;
				}
			}
 		}}
 		if ($skriv_linje==1) {
			$vare_a='';
			$vare_b='';
			$upd='';
			for ($y=0; $y<=$feltantal; $y++) {
				if ($feltnavn[$y] && $medtag_felt[$y] && $feltnavn[$y]!='leverandor' && $feltnavn[$y]!='-') {
					($nyt_feltnavn[$y])?$fName=$nyt_feltnavn[$y]:$fName=$feltnavn[$y];
					$felt[$y]=db_escape_string($felt[$y]);
					($vare_a)?$vare_a.=",".$fName:$vare_a=$fName;
					($vare_b)?$vare_b.=",'".$felt[$y]."'":$vare_b="'".$felt[$y]."'";
					($upd)?$upd.=",".$fName."='".$felt[$y]."'":$upd=$fName."='".$felt[$y]."'";
				}
			}
			$vare_a=$vare_a.",lukket";
			$vare_b=$vare_b.",''";
			$tmp = db_escape_string($varenr);
			$qtxt = "select id from varer where varenr='$tmp' or ";
			$qtxt.= "lower(varenr)='".strtolower($tmp)."' ";
			$qtxt.= "or upper(varenr)='".strtoupper($tmp)."'";
			if ($varenr && $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { #20140201
				$vare_id=$r['id'];
			}
			if (!$vare_id) {
				for ($v=0;$v<count($v_id);$v++) { #20161015
					if ($v_nr[$v]==$varenr)	$vare_id=$v_id[$v];
				}
			}
			if (!$vare_id) {
				for ($v=0;$v<count($v_id);$v++) { #20161015
					if (strtolower($v_nr[$v])==strtolower($varenr))	$vare_id=$v_id[$v];
				}
			}
			If (!$vare_id && is_numeric($varenr)) { #20161015
				for ($v=0;$v<count($v_id);$v++) {
				 if (is_numeric($v_nr[$v]) && $v_nr[$v]*1==$varenr*1)	$vare_id=$v_id[$v];
				}
			}
			if ($vare_id) {// update existing product
				if (($filterOption=='all_products')||($filterOption!='only_unknown_products')) {
					$upd_antal++;
					$qtxt="update varer set $upd where id='$vare_id'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				} else $vare_id=0; // added to avoid the update further down
			} elseif ($varenr) {// create new product
				if (($filterOption=='all_products')||($filterOption!='only_existing_product')){
					$imp_antal++;
					$query = db_select("SELECT var_value FROM settings WHERE var_name = 'min_beholdning' AND var_grp = 'productOptions'", __FILE__ . " linje " . __LINE__);
					if(db_num_rows($query) > 0){
						$r = db_fetch_array($query);
						$minBeholdning = (int)$r["var_value"];
						$vare_a .= ",min_lager";
						$vare_b .= ",".$minBeholdning;
					}
					$qtxt="insert into varer($vare_a) values ($vare_b)";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	 				$qtxt = "select id from varer where varenr='". db_escape_string($varenr) ."'";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$vare_id=$r['id'];
				}
			}
			if ($vare_id) {
				if ($itemGroup) {
						$qtxt = "update varer set gruppe = '$itemGroup' where id = '$vare_id'";
					if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				if ($lokation) { #20180404
					$qtxt="select id from lagerstatus where vare_id='$vare_id' and lager <= '1'";
					if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
						$qtxt="update lagerstatus set lok1 = '$location' where id = '$r[id]'";
					} elseif ($lokation) {
						$qtxt="insert into lagerstatus(vare_id,beholdning,lok1,lager) values ('$vare_id','0','$location','1')";
					} else $qtxt=NULL;
					if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$dd=date("Y-m-d");
				$qtxt="select id,kostpris,transdate from kostpriser where vare_id='$vare_id' order by transdate desc limit 1"; #20150224
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($r['transdate'] != $dd && $r['kostpris'] != $kostpris) {
					$qtxt="insert into kostpriser (vare_id,kostpris,transdate) values ('$vare_id','$kostpris','$dd')";
				}	elseif ($r['transdate'] == $dd && $r['kostpris'] != $kostpris) {
					$qtxt="update kostpriser set kostpris=$kostpris where id = '$r[id]'";
				} else $qtxt=NULL;
				if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				if ($leverandor) {
					if (!is_numeric($leverandor)) $leverandor = 0;
					if ($r=db_fetch_array(db_select("select id from vare_lev where vare_id='$vare_id' and lev_id='$leverandor'",__FILE__ . " linje " . __LINE__))) {
						db_modify("update vare_lev set kostpris='$kostpris' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
					} else {
						db_modify("insert into vare_lev (vare_id,lev_id,kostpris,posnr) values ($vare_id,'$leverandor','$kostpris','1')",__FILE__ . " linje " . __LINE__);
					}
				}
				if ($kostpris && !$salgspris_isset) {
					if ($salesPriceFromPurchasePrice>0 && $salesPriceMethod && $salesPriceRoundingMethod) {
						$salgspris = myAddToPriceFunc($kostpris, $salesPriceRoundingMethod, $salesPriceFromPurchasePrice, $salesPriceMethod);
						if ($salgspris) db_modify("update varer set salgspris='$salgspris' where id='$vare_id'",__FILE__ . " linje " . __LINE__);
					} else {
						$qtxt="select salgspris_multiplier,salgspris_method,salgspris_rounding from varer where id='$vare_id'";
						$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						if ($r['salgspris_multiplier']>0 && $r['salgspris_method'] && $r['salgspris_rounding']){
							$salgspris = myAddToPriceFunc($kostpris, $r['salgspris_rounding'], $r['salgspris_multiplier'], $r['salgspris_method']);
							if ($salgspris) db_modify("update varer set salgspris='$salgspris' where id='$vare_id'",__FILE__ . " linje " . __LINE__);
						}
					}
				}
				if ($kostpris && !$tier_price_isset) {
					if ($tierPriceFromPurchasePrice>0 && $tierPriceMethod && $tierPriceRoundingMethod) {
						$tier_price = myAddToPriceFunc($kostpris, $tierPriceRoundingMethod, $tierPriceFromPurchasePrice, $tierPriceMethod);
						if ($tier_price) db_modify("update varer set tier_price='$tier_price' where id='$vare_id'",__FILE__ . " linje " . __LINE__);
					} else {
						$qtxt="select tier_price_multiplier,tier_price_method,tier_price_rounding from varer where id='$vare_id'";
						$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						if ($r['tier_price_multiplier']>0 && $r['tier_price_method'] && $r['tier_price_rounding']){
							$tier_price = myAddToPriceFunc($kostpris, $r['tier_price_rounding'], $r['tier_price_multiplier'], $r['tier_price_method']);
							if ($tier_price) db_modify("update varer set tier_price='$tier_price' where id='$vare_id'",__FILE__ . " linje " . __LINE__);
						}
					}
				}
				if ($kostpris && !$retail_price_isset) {
					if ($retailPriceFromPurchasePrice>0 && $retailPriceMethod && $retailPriceRoundingMethod) {
						$retail_price = myAddToPriceFunc($kostpris, $retailPriceRoundingMethod, $retailPriceFromPurchasePrice, $retailPriceMethod);
						if ($retail_price) db_modify("update varer set retail_price='$retail_price' where id='$vare_id'",__FILE__ . " linje " . __LINE__);
					} else {
						$qtxt="select retail_price_multiplier,retail_price_method,retail_price_rounding from varer where id='$vare_id'";
						$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						if ($r['retail_price_multiplier']>0 && $r['retail_price_method'] && $r['retail_price_rounding']){
							$retail_price = myAddToPriceFunc($kostpris, $r['retail_price_rounding'], $r['retail_price_multiplier'], $r['retail_price_method']);
							if ($retail_price) db_modify("update varer set retail_price='$retail_price' where id='$vare_id'",__FILE__ . " linje " . __LINE__);
						}
					}
				}
			}
		}
	}
}
#xit;
fclose($fp);
#xit;
transaktion('commit');
print "</tbody></table>";
print "</td></tr>";
print "<BODY onLoad=\"javascript:alert('$imp_antal varer importeret, $upd_antal varer opdateret')\">";
#print "<BODY onLoad=\"javascript:alert('$imp_antal varer importeret')\">";
print "<meta http-equiv=\"refresh\" content=\"0;URL=diverse.php?sektion=div_io\">";
exit;
} # endfunc overfoer_data

function nummertjek ($nummer){
	$nummer=trim($nummer);
	$retur=1;
	$nummerliste=array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0", ",", ".", "-");
	for ($x=0; $x<strlen($nummer); $x++) {
		if (!in_array($nummer[$x], $nummerliste)) $retur=0;
	}
	if ($retur) {
		for ($x=0; $x<strlen($nummer); $x++) {
			if ($nummer[$x]==',') $komma++;
			elseif ($nummer[$x]=='.') $punktum++;
		}
		if ((!$komma)&&(!$punktum)) $retur='US';
		elseif (($komma==1)&&(substr($nummer,-3,1)==',')) $retur='DK';
		elseif (($punktum==1)&&(substr($nummer,-3,1)=='.')) $retur='US';
		elseif (($komma==1)&&(!$punktum)) $retur='DK';
		elseif (($punktum==1)&&(!$komma)) $retur='US';
	}
	return $retur=chr(32);
}
function opdel ($splitter,$linje){
	global $feltantal;
	$anftegn = $x = $y = 0;
	$var[$y] = '';

	if (substr($linje,0,1)==chr(34)) {
		$anftegn=1;
		$x++;
 }
	for($z=$x;$z<=strlen($linje);$z++) {
		$tegn=substr($linje,$z,1);
		if (!$anftegn && substr($linje,$z-1,1)==$splitter && $tegn==chr(34)) {
			$anftegn=1;
 		}
		if ($anftegn && $tegn==chr(34) && substr($linje,$z+1,1)==$splitter) {
			$y++;
			$z++;
			$anftegn=0;
			$var[$y] = '';
		} elseif (!$anftegn && substr($linje,$z,1)==$splitter) {
			$y++;
			$var[$y] = '';
		} elseif ($tegn!=chr(34)) {
			$var[$y]=$var[$y].substr($linje,$z,1);
		}
	}
	return $var;
}
function find_lev_id($kontonr) {
	global $regnaar;

	$kontonr=trim($kontonr);
	if (!is_numeric($kontonr)) $kontonr = 0;
	$qtxt="select id from adresser where kontonr='$kontonr' and art='K'"; #20160219
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) return ($r['id']);
	else return(0);
}
function find_varegrp($gruppe) {
	global $regnaar;

	$gruppe=trim($gruppe);
	if (!is_numeric($gruppe)) {
		$low=strtolower($gruppe);
		$up=strtoupper($gruppe);
		$qtxt = "select kodenr from grupper where art='VG' and (lower(beskrivelse)='$low' or upper(beskrivelse)='$up') and fiscal_year = '$regnaar'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) return ($r['kodenr']);
		else return(0);
	} elseif ($r=db_fetch_array(db_select("select id from grupper where art='VG' and kodenr = '$gruppe' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__))) {
	 return ($gruppe);
	} else return(0);
}
function myAddToPriceFunc($kostpris, $roundingMethod, $value, $CalculationMethod) {
	$price = 0;
	if ($CalculationMethod=="percentage") {
		$price = $kostpris + (($value * $kostpris) / 100);
	} elseif ($CalculationMethod=="amount") {
		$price = $kostpris + $value;
	}
	if ($price && $roundingMethod=="st_rounding") return round($price);
	if ($price && $roundingMethod=="rounding_up") return ceil($price);
	if ($price && $roundingMethod=="round_down") return floor($price);
	return $price;
}

