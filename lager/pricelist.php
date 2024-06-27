<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------------lager/pricelist.php--- lap 4.0.1 --- 2021-07-28 ----
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
// Copyright (c) 2003-2021 saldi.dk aps
// ----------------------------------------------------------------------

 
@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Prisliste";

$linjebg=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$returside="rapport.php";

$zero_stock  		= false;
$show_all_products	= false;
$show_antal    		= false;
$show_enhed    		= false;
$show_kostpris    	= true;
$show_tier_price    = true;
$show_salgspris 	= true;
$show_retail_price  = true;
$custom_text		= "";

$varegruppe = if_isset($_GET['varegruppe']);
if ($varegruppe == "0:Alle") $varegruppe=NULL;
else {
	setcookie("saldi_pricelist", $varegruppe);
	$returside="rapport.php?varegruppe=$varegruppe";
}
if (isset($_POST['submit'])) {
	$submit 			= $_POST['submit'];
	$varegruppe 		= $_POST['varegruppe'];
	$lagervalg  		= $_POST['lagervalg'];
	$zero_stock     	= $_POST['zero_stock'];
	$show_all_products 	= $_POST['show_all_products'];
	$show_antal    		= $_POST['show_antal'];
	$show_enhed    		= $_POST['show_enhed'];
	$show_kostpris    	= $_POST['show_kostpris'];
	$show_tier_price    = $_POST['show_tier_price'];
	$show_salgspris 	= $_POST['show_salgspris'];
	$show_retail_price  = $_POST['show_retail_price'];
	$custom_text  		= $_POST['custom_text'];
	setcookie("saldi_pricelist", $varegruppe);
} elseif (isset($_GET['csv']) || isset($_GET['autoprint'])) {
	$csv 				= if_isset($_GET['csv']);
	$autoprint 			= $_GET['autoprint'];
	$varegruppe 		= $_GET['varegruppe'];
	$lagervalg  		= $_GET['lagervalg'];
	$zero_stock     	= $_GET['zero_stock'];
	$show_all_products	= $_GET['show_all_products'];
	$show_antal    		= $_GET['show_antal'];
	$show_enhed    		= $_GET['show_enhed'];
	$show_kostpris    	= $_GET['show_kostpris'];
	$show_tier_price    = $_GET['show_tier_price'];
	$show_salgspris 	= $_GET['show_salgspris'];
	$show_retail_price  = $_GET['show_retail_price'];
	$custom_text  		= $_GET['custom_text'];
	//setcookie("saldi_pricelist", $varegruppe);
} elseif (!$varegruppe)  {
	$varegruppe=($_COOKIE['saldi_pricelist']);
	if (!$varegruppe) $varegruppe="0:Alle";
}

$x=0;
$q1= db_select("select kodenr, box9 from grupper where art = 'VG' and box8 = 'on'",__FILE__ . " linje " . __LINE__);
while ($r1=db_fetch_array($q1)) {
	$x++;
	$lagervare[$x]=$r1['kodenr'];
}
$lager[1]=1;
$lagernavn[1]='';
$x=0;
$q1= db_select("select kodenr,beskrivelse from grupper where art = 'LG' order by kodenr",__FILE__ . " linje " . __LINE__);
while ($r1=db_fetch_array($q1)) {
	$x++;
	$lager[$x]=$r1['kodenr'];
	$lagernavn[$x]=$r1['beskrivelse'];
}
if (count($lager)>=1) {
	$lager[0]=0;
	$lagernavn[0]='Alle';
	db_modify("update batch_kob set lager='1' where lager='0' or lager is NULL",__FILE__ . " linje " . __LINE__);
	db_modify("update batch_salg set lager='1' where lager='0' or lager is NULL",__FILE__ . " linje " . __LINE__);
}

$x=0;
list($a,$b)=explode(":",$varegruppe);

if ($a) {
	if ($lagervalg) {
		$qtxt = "select varer.id,varer.varenr,varer.enhed,varer.beskrivelse,varer.salgspris,varer.kostpris,varer.tier_price,varer.retail_price,varer.varianter,varer.gruppe,";
		$qtxt.= "pricelist.beholdning ";
		$qtxt.= "from varer,pricelist where ".((!$show_all_products)?"on_price_list='1' and ":"")." varer.gruppe='$a' and pricelist.vare_id=varer.id and pricelist.lager='$lagervalg' ";
		if (!$zero_stock) $qtxt.= "and pricelist.beholdning != '0' ";
		$qtxt.="order by varer.varenr";
	} else {
	   $qtxt = "select * from varer where ".((!$show_all_products)?"on_price_list='1' and ":"")." gruppe='$a' ";
	   if (!$zero_stock) $qtxt.= "and beholdning != '0' ";
	    $qtxt.= "order by varenr";
	}
} else {
	if ($lagervalg) {
		$qtxt =" select varer.id,varer.varenr,varer.enhed,varer.beskrivelse,varer.salgspris,varer.kostpris,varer.tier_price,varer.retail_price,varer.varianter,varer.gruppe,";
		$qtxt.= "pricelist.beholdning ";
		$qtxt.= "from varer,pricelist where ".((!$show_all_products)?"on_price_list='1' and ":"")." pricelist.vare_id=varer.id and pricelist.lager='$lagervalg' ";
		if (!$zero_stock) $qtxt.= "and pricelist.beholdning != '0' ";
		$qtxt.= " order by varer.varenr";
	} else {
	    $qtxt = "select * from varer ";
	    if (!$zero_stock) {
	    	$qtxt.= "where ".((!$show_all_products)?"on_price_list='1' and ":"")." beholdning != '0' ";
	    } elseif (!$show_all_products) {
	    	$qtxt.=" where on_price_list='1' ";
		}
	    $qtxt.= "order by varenr";
	}
}
$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r2=db_fetch_array($q2)){
	if (in_array($r2['gruppe'], $lagervare)) {
		$x++;
		$vare_id[$x] 		= $r2['id'];
		$varenr[$x] 		= stripslashes($r2['varenr']);
		$enhed[$x] 			= stripslashes($r2['enhed']);
		$beholdning[$x] 	= $r2['beholdning'];
		$varianter[$x] 		= $r2['varianter']; #20180204
		$beskrivelse[$x] 	= stripslashes($r2['beskrivelse']);
		$salgspris[$x] 		= $r2['salgspris'];
		$kostpris[$x] 		= $r2['kostpris'];
		$retail_price[$x] 	= $r2['retail_price'];
		$tier_price[$x] 	= $r2['tier_price'];
	}
}
$vareantal=$x;

$url_part = "varegruppe=$varegruppe&lagervalg=$lagervalg&zero_stock=$zero_stock&show_all_products=$show_all_products&show_kostpris=$show_kostpris&show_antal=$show_antal&show_enhed=$show_enhed&show_tier_price=$show_tier_price&show_salgspris=$show_salgspris&show_retail_price=$show_retail_price&custom_text=".urlencode($custom_text)."";

if ($autoprint) {// Print friendly
	print "<table border=0 cellpadding=0 cellspacing=0 width=100%><tbody>";
	print "<tr><td><table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><tr><td width=100% $top_bund align=center>".ucfirst(findtekst(2082, $sprog_id))."</td></td></tr></tbody></td></tr></table></td></tr>";
	if ($custom_text) print "<tr><td><table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><tr><td width=100% $top_bund align=center>".$custom_text."</td></td></tr></tbody></td></tr></table></td></tr>";
	print "<tr><td><hr></td></tr>";
	print "</tbody></table>";

} else {
	print "<table border=0 cellpadding=0 cellspacing=0 width=100%><tbody>";
	print "<tr><td>";
	print "  <table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><tr>";
	print "    <td width=10% $top_bund><a href=$returside accesskey=L>".findtekst(30, $sprog_id)."</a></td>"; #20210708
	print "    <td width=80% $top_bund align=center>".ucfirst(findtekst(2082, $sprog_id))."</td>";
	print "    <td width=10% $top_bund><a href='pricelist.php?csv=1&".$url_part."' title='".findtekst(2084, $sprog_id)."'>CSV</a></td>";
	print "  </tr></tbody></table>\n";
	if ($custom_text) print "<tr><td><table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><tr><td width=100% $top_bund align=center>".$custom_text."</td></td></tr></tbody></td></tr></table></td></tr>";
	print "</td></tr>";

	print "<tr><td align=\"center\"><form action=pricelist.php method=post>";
	if (count($lager)) {
		print " Lager: <select class=\"inputbox\" name=\"lagervalg\">";
		for ($x=0;$x<=count($lager);$x++){
			if ($lagervalg==$lager[$x]) print "<option value='$lager[$x]'>$lagernavn[$x]</option>";
		}
		for ($x=0;$x<=count($lager);$x++){
			if ($lagervalg!=$lager[$x]) print "<option value='$lager[$x]'>$lagernavn[$x]</option>";
		}
		print "</select>";
	}
	print " Varegruppe: <select class=\"inputbox\" name=\"varegruppe\">";
	if ($varegruppe) print "<option>$varegruppe</option>";
	if ($varegruppe!="0:Alle") print "<option>0:Alle</option>";

	$q = db_select("select * from grupper where art = 'VG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)){
		if ($varegruppe!=$row['kodenr'].":".$row['beskrivelse']) {print "<option>$row[kodenr]:$row[beskrivelse]</option>";}
	}
	print "</select>";

	print " &nbsp".findtekst(2099, $sprog_id).": <input type='text' name='custom_text' value='$custom_text'>";

	($zero_stock)?$zero_stock="checked='checked'":$zero_stock=NULL;
	print " &nbsp"."<span title='".findtekst(1656, $sprog_id)."'><input type='checkbox' name='zero_stock' $zero_stock>".findtekst(2096, $sprog_id)."</span>";

	($show_all_products)?$show_all_products="checked='checked'":$show_all_products=NULL;
	print " &nbsp"."<input type='checkbox' name='show_all_products' $show_all_products>".findtekst(2090, $sprog_id);

	//($show_kostpris)?$show_kostpris="checked='checked'":$show_kostpris=NULL;
	//print " &nbsp"."<input type='checkbox' name='show_kostpris' $show_kostpris>".findtekst(2095, $sprog_id);

	($show_antal)?$show_antal="checked='checked'":$show_antal=NULL;
	print " &nbsp"."<input type='checkbox' name='show_antal' $show_antal>".findtekst(2097, $sprog_id);

	($show_enhed)?$show_enhed="checked='checked'":$show_enhed=NULL;
	print " &nbsp"."<input type='checkbox' name='show_enhed' $show_enhed>".findtekst(3000, $sprog_id);

	($show_tier_price)?$show_tier_price="checked='checked'":$show_tier_price=NULL;
	print " &nbsp"."<input type='checkbox' name='show_tier_price' $show_tier_price>".findtekst(2091, $sprog_id);

	($show_salgspris)?$show_salgspris="checked='checked'":$show_salgspris=NULL;
	print " &nbsp"."<input type='checkbox' name='show_salgspris' $show_salgspris>".findtekst(2092, $sprog_id);

	($show_retail_price)?$show_retail_price="checked='checked'":$show_retail_price=NULL;
	print " &nbsp"."<input type='checkbox' name='show_retail_price' $show_retail_price>".findtekst(2093, $sprog_id);

	print " &nbsp"."<input type='submit' name='submit' value='".findtekst(2087, $sprog_id)."'>";
	
	print " &nbsp"."<a href='pricelist.php?autoprint=1&".$url_part."' target='_blank'>".findtekst(2098, $sprog_id)."</a>";
	
	print "</form></td></tr>";
	
	print "<tr><td><hr></td></tr>";
	print "</tbody></table>";
}

print "<table border=0 cellpadding=0 cellspacing=0 width=100%><tbody>";
print "<tr>";
print "<td width=8%>".findtekst(917, $sprog_id).".</td>";
if ($show_enhed) print "<td width=5%>".findtekst(945, $sprog_id)."</td>";
print "<td>".findtekst(914, $sprog_id)."</td>";
if ($show_antal) print "<td align=right width=8%>".findtekst(916, $sprog_id)."</td>";
//print "".(($show_kostpris)?"<td align=right width=8%>".findtekst(950, $sprog_id)."</td>":"")."";
//print "".(($show_kostpris)?"<td align=right width=8%>".findtekst(950, $sprog_id)."</td>":"")."";
if ($show_tier_price) print "<td align=right width=8%>".findtekst(2088, $sprog_id)."</td>";
if ($show_salgspris) print "<td align=right width=8%>".findtekst(949, $sprog_id)."</td>";
if ($show_retail_price) print "<td align=right width=8%>".findtekst(2085, $sprog_id)."</td>";
print "</tr>";

if ($csv) {
	$fp=fopen("../temp/$db/pricelist.csv","w");
	$linje =	"Varenr".";".
				(($show_enhed)?"Enhed".";":"").
				"Beskrivelse".";".
				(($show_antal)?"Antal".";":"").
				//(($show_kostpris)?"Købspris".";":"").
				//(($show_kostpris)?"Kostpris".";":"").
				(($show_tier_price)?"B2B pris".";":"").
				(($show_salgspris)?"Salgspris".";":"").
				(($show_retail_price)?"Vejl.pris".";":"").
				"";
	$linje=utf8_decode($linje);
	fwrite($fp,"$linje\n");
}
 
for($x=1; $x<=$vareantal; $x++) {
	$batch_k_antal[$x]=0;$batch_t_antal[$x]=0;$batch_pris[$x]=0;$batch_s_antal[$x]=0;
	$qtxt="select sum(antal) as antal from batch_kob where vare_id=$vare_id[$x]";
	if ($lagervalg) $qtxt.=" and lager='$lagervalg'";
	$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$batch_k_antal[$x]=$r1['antal'];
	$batch_t_antal[$x]=$r1['antal'];
	$qtxt="select sum(antal) as antal from batch_salg where vare_id=$vare_id[$x]";
	if ($lagervalg) $qtxt.=" and lager='$lagervalg'";
	$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$batch_s_antal[$x]=$r1['antal'];
	$batch_t_antal[$x]-=$r1['antal'];

	if ($batch_k_antal[$x]||$batch_s_antal[$x]||$beholdning[$x]) {
		if ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		else {$linjebg=$bgcolor; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td>".$varenr[$x]."<br></td>";
		if ($show_enhed) print "<td>".$enhed[$x]."<br></td>";
		print "<td>".$beskrivelse[$x]."<br></td>";
		if ($show_antal) print "<td align=right>".str_replace(".",",",$batch_t_antal[$x]*1)."<br></td>";
		//if ($show_kostpris) print "<td align=right>".dkdecimal($batch_pris[$x])."<br></td>";
		//if ($show_kostpris) print "<td align=right>".dkdecimal($kostpris[$x])."<br></td>";
		if ($show_tier_price) print "<td align=right>".dkdecimal($tier_price[$x])."<br></td>";
		if ($show_salgspris) print "<td align=right>".dkdecimal($salgspris[$x])."<br></td>";
		if ($show_retail_price) print "<td align=right>".dkdecimal($retail_price[$x])."<br></td>";
		print "</tr>";
		if ($csv) {
			$linje = 	"$varenr[$x]".";".
						(($show_enhed)?"".$enhed[$x].";":"").
						"$beskrivelse[$x]".";".
						(($show_antal)?"".dkdecimal($batch_t_antal[$x]).";":"").
						//(($show_kostpris)?"".dkdecimal($batch_pris[$x]).";":"").
						//(($show_kostpris)?"".dkdecimal($kostpris[$x]).";":"").
						(($show_tier_price)?"".dkdecimal($tier_price[$x]).";":"").
						(($show_salgspris)?"".dkdecimal($salgspris[$x]).";":"").
						(($show_retail_price)?"".dkdecimal($retail_price[$x]).";":"").
						"";
			$linje=utf8_decode($linje);
			fwrite($fp,"$linje\n");
		}
	} 
}
if ($csv){ 
	fclose($fp);
	print "<BODY onLoad=\"JavaScript:window.open('../temp/$db/pricelist.csv' ,'' ,'$jsvars');\">\n";
}
print "<tr><td colspan=9><hr></td></tr>";
?>
</tbody></table>
</body></html>
