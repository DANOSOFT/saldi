<?php 
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/lager/varekort.php---------lap 3.9.0---2020-03-12	-----
// LICENS
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 2013.02.10 Break ændret til break 1
// 2013.10.07	Kontrol for cirkulær reference indsat. Søg 20131007
// 2014.06.03 Indsat visning af stregkode. Søg 20140603
// 2014.06.17 ekstra felt "indhold" til angivelse af vægt / rumfang mm. samt automatisk beregning af kg/liter/ pris mm. Søg indhold
// 2104.09.09	Fejltekst fra "barcode" hvis stregkode ikke kan genereres bliver nu undertrykt. #20140909
// 2015.02.10 Beholdninger og lokationer vises nu fra hvert lager.
// 2015.05.21 Shopurl tilrettet til "nyt api". Søg shopurl
// 2015.10.28 understøtter ny også tbarcode
// 2016.01.19 PHR Tilføjet varefoto og regulering af varebeholdning ved gruppeændring fra ikke lagerført til lagerført og vice versa
// 2016.01.30 PHR Tilføjet "tilbudsdage" så man kan vælge hvilke ugedage et tilbud skal være aktivt.
// 2016.03.08 PHR Tilføjet "link til slet shop id dom fjerner binding til shop vare. Søg slet_shopbinding.php og fjernet mulighed for publicering.
// 2016.06.06 PHR	Opdat shop_vare erstattet af opdat behold...
// 2016.10.06 PHR hvis prisen er 0.001 vises prisen med 3 decimaler. Hvis brugt i pos indsættes varen til 0 kr oden at der hoppes til pris. 20161006
// 2017.01.06 PHR Mrabat trak moms fra på procentrabat. Ændret "%" til "percent"
// 2017.02.10	PHR - Aktivering af nyt API 20170210 
// 2017.10.30	PHR	Overføldige paramatrre i kald til og funktion kontoopslag fjernet. 
// 2017.11.06 PHR	Stregkodegenereing flyttet i funktion barcode og der kan nu udskrives labels fra varianter. 
// 2018.01.23 PHR	En del rettelser i i forhold til varianter og flere lagre. Beholdninger rettes nu kun i varianter, hvis varianter.
// 2018.02.04 PHR Styklister deaktiveres, hvis der anvendes varianter.
// 2018.02.14 PHR	Varianter kan ikke mere slettes hvis der er varianter på lager.
// 2018.03.14 PHR Lager var fast til 1 i kald til 'vareproduktion'. 
// 2019.01.17 MSC - Rettet topmenu design til og rettet isset fejl
// 2019.01.18 PHR - Adjusted for different VAT rates. VAT is defined by 'gruppe' -> 'kontoplan'
// 2019.01.18 PHR - Items can no longer be changed from item with variants or vice versa if there are items in stock.
// 2019.01.23 PHR - Barcode line can now hold more than one barcode. Seperate by ';'
// 2019.01.29 PHR	- PHR Incl_moms changed according to'$vatOnItemCard' to fullfill demands for different Vat Rates
// 2019.02.20 MSC - Rettet topmenu design og isset fejl
// 2019.03.21 PHR - Added 'read-only'. Look for $noEdit
// 2019.04.04  LN - Added call to varios functions in varekort_includes/percentageField and created percentage field.
// 2019.04.09 PHR - Added call to function vatPercent in varekort_includes/itemVat.php and moved vat calculation there.
// 2019.04.21 PHR - Added confirmDiscriptionChange, can be set in 'Indstillinger' > 'VareValg'. 
// 2019.03.21 PHR - Edited 'read-only'. Changed from [10] to [9]
// 2019.10.22 PHR - Added copy option, search 'Kopier'.
// 2019.11.06 PHR - Added $netWeight & $grossWeight
// 2019.12.09 PHR	- Added quantity incomming and outgoing. Search incomming / outgoing
// 2020.02.11 PHR	- Removed htmlentities varius 
// 2020.03.10 PHR - Changed (!count($lagernavn)) to (count($lagernavn) <= 1)  20200310-1
// 2020.03.10 PHR - Changed = to += 20200310-2
// 2020.03.12 PHR - Added 'order by posnr' in 'stykliste' queries


ob_start(); //Starter output buffering

@session_start();
$s_id=session_id();


$begin=0;
$folgevare=0;
$modulnr=9;
$styklister=1;
$title="Varekort";

$ant_indg_i=NULL;
$beholdning=$beskrivelse[0]=$betalingsdage=$betegnelse=NULL;
$ean13=$enhed=NULL;
$fejl=$find=$fokus=$folgevarenr=NULL;
$grossWeight=$gruppe=NULL;
$kategori=NULL;
$lager=$lagerantal=$lev=$lev_ant=NULL;
$m_antal=$m_rabat=$montage=$demontage=NULL;
$netWeight=$ny_beholdning=NULL;
$oldDescription=$ordre_id=NULL;
$shop_id=$sort=NULL;
$rabatgruppe=$ref=$returside=NULL;
$varenr=$variantVarerVariantId=$vis_kost=NULL;
$tilbudgruppe=NULL;

$beskrivelse=$lagerbeh=$variant_varer_id=array();

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/vareopslag.php"); # 2009.05.14
include("../includes/stykliste.php");
include("../includes/fuld_stykliste.php");
include("varekort_includes/itemVat.php");
include("varekort_includes/percentageField.php");

#if (!isset ($_POST['montage'])) $_POST['montage'] = NULL;
#if (!isset ($_POST['demontage'])) $_POST['demontage'] = NULL;
if (!isset ($_POST['oldDescription'])) $_POST['oldDescription'] = NULL;

($rettigheder[9] == '2')?$noEdit="disabled=true":$noEdit=NULL;

print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

$qtxt="select id,var_value from settings where var_name = 'confirmDiscriptionChange' and var_grp = 'items'";
$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$confirmDiscriptionChange=$r['var_value'];

$r=db_fetch_array(db_select("select box2 from grupper where art = 'DIV' and kodenr = '5' ",__FILE__ . " linje " . __LINE__));
$shopurl=trim($r['box2']);

$opener=if_isset($_GET['opener']);
$id = if_isset($_GET['id'])*1;
if(isset($_GET['returside']) && $returside= $_GET['returside']){
	$ordre_id = if_isset($_GET['ordre_id'])*1;
	$fokus = if_isset($_GET['fokus']);
	$vare_lev_id = if_isset($_GET['leverandor']);
	$vis_samlevarer =  if_isset($_GET['vis_samlevarer']);
	setcookie("saldi",$returside,$ordre_id,$fokus,$vare_lev_id);
}
if ($funktion=if_isset($_GET['funktion'])) {
	$funktion(if_isset($_GET['sort']), if_isset($_GET['fokus']), $id,  if_isset($_GET['vis_kost']), '',if_isset($_GET['find']), 'varekort.php');
}
if ($konto_id=if_isset($_GET['konto_id'])) {
	 db_modify("insert into vare_lev (lev_id, vare_id, posnr) values ('$konto_id', '$id', '1')",__FILE__ . " linje " . __LINE__);
 }
if (isset($_GET['vare_id']) && cirkeltjek($_GET['vare_id'])==0) {
	$vare_id=$_GET['vare_id'];
	if ($vare_id != $id) {
		db_modify("insert into styklister (vare_id, indgaar_i, antal) values ('$vare_id', '$id', '1')",__FILE__ . " linje " . __LINE__);
		db_modify("update varer set delvare =  'on' where id = '$vare_id'",__FILE__ . " linje " . __LINE__);
	}
}
if ($delete_category=if_isset($_GET['delete_category'])) {
	db_modify("delete from grupper where id = '$delete_category'",__FILE__ . " linje " . __LINE__);
}
if ($delete_var_type=if_isset($_GET['delete_var_type'])) {
	db_modify("delete from variant_varer where id = '$delete_var_type'",__FILE__ . " linje " . __LINE__);
	db_modify("delete from lagerstatus where vare_id='$id' and variant_id = '$delete_var_type'",__FILE__ . " linje " . __LINE__);
}
$rename_category=if_isset($_GET['rename_category']);
$show_subcat=if_isset($_GET['show_subcat']);

$submit=if_isset($_POST['submit']);	
if ($submit=trim($submit)) {
	$id=if_isset($_POST['id']);
	$beskrivelse=if_isset($_POST['beskrivelse']);
	$beskrivelse[0]=db_escape_string(trim(if_isset($_POST['beskrivelse0']))); # fordi fokus ikke fungerer på array navne
	$grossWeight=usdecimal($_POST['grossWeight']);
	$varenr=db_escape_string(trim(if_isset($_POST['varenr'])));
	$stregkode=db_escape_string(trim(if_isset($_POST['stregkode'])));
	$oldDescription=db_escape_string(trim(if_isset($_POST['oldDescription'])));
	$enhed=db_escape_string(trim(if_isset($_POST['enhed'])));
	$enhed2=db_escape_string(trim(if_isset($_POST['enhed2'])));
	$forhold=usdecimal(if_isset($_POST['forhold']),2);
	$salgspris=usdecimal(if_isset($_POST['salgspris']),2);
	$salgspris2=usdecimal(if_isset($_POST['salgspris2']),2);
	$kostpris=if_isset($_POST['kostpris']);
	$gl_kostpris=if_isset($_POST['gl_kostpris']);
	$provisionPercentage = if_isset($_POST['provisionPercentage']);
	$kostpris[0]=usdecimal($kostpris[0],2);
	$kostpris2=if_isset($_POST['kostpris2']);
	$montage=usdecimal(if_isset($_POST['montage']),2); # montagepris til stillads
	$demontage=usdecimal(if_isset($_POST['demontage']),2); # demontagepris til stillads
	$netWeight=usdecimal($_POST['netWeight']);
	$indhold=usdecimal(if_isset($_POST['indhold']),2);
	$provisionsfri=trim(if_isset($_POST['provisionsfri']));
	$publiceret=if_isset($_POST['publiceret']);
	$publ_pre=if_isset($_POST['publ_pre']);
	list ($leverandor) = explode(':', if_isset($_POST['leverandor']));
	$vare_lev_id=if_isset($_POST['vare_lev_id']);
	$lev_varenr=if_isset($_POST['lev_varenr']);
	$lev_antal=if_isset($_POST['lev_antal']);
	$lev_pos=if_isset($_POST['lev_pos']);
	$gruppe=if_isset($_POST['gruppe']);
	$ny_gruppe=if_isset($_POST['ny_gruppe']);
	$dvrg_nr[0]=if_isset($_POST['dvrg'])*1; # DebitorVareRabatGruppe
	$prisgruppe=if_isset($_POST['prisgruppe'])*1;
	$tilbudgruppe=if_isset($_POST['tilbudgruppe'])*1;
	$rabatgruppe=if_isset($_POST['rabatgruppe'])*1;
	$operation=if_isset($_POST['operation'])*1;
	$min_lager= if_isset($_POST['min_lager']); 
	$max_lager= if_isset($_POST['max_lager']);
	$beholdning=if_isset($_POST['beholdning']);
	$ny_beholdning=if_isset($_POST['ny_beholdning']);
	$lukket=if_isset($_POST['lukket']);
	$serienr=db_escape_string(trim(if_isset($_POST['serienr'])));
#	list ($gruppe) = explode (':', if_isset($_POST['gruppe']));
	$notes=db_escape_string(trim(if_isset($_POST['notes'])));
	$ordre_id=if_isset($_POST['ordre_id']);
	$returside=if_isset($_POST['returside']);
	$fokus=if_isset($_POST['fokus']);
	$vare_sprogantal=if_isset($_POST['vare_sprogantal']);
	$vare_sprog_id=if_isset($_POST['vare_sprog_id']);
	$vare_tekst_id=if_isset($_POST['vare_tekst_id']);
	$trademark=db_escape_string(trim(if_isset($_POST['trademark'])));
	$retail_price=usdecimal(if_isset($_POST['retail_price']),2);
	$special_price=usdecimal(if_isset($_POST['special_price']),2);
	$tier_price=usdecimal(if_isset($_POST['tier_price']),2);
	$special_from_date=usdate(if_isset($_POST['special_from_date']));
	$special_to_date=usdate(if_isset($_POST['special_to_date']));
	$special_from_time=if_isset($_POST['special_from_time']);
	$special_to_time=if_isset($_POST['special_to_time']);
	$colli=usdecimal(if_isset($_POST['colli']),2);
	$outer_colli=usdecimal(if_isset($_POST['outer_colli']),2);
	$open_colli_price=usdecimal(if_isset($_POST['open_colli_price']),2);
	$outer_colli_price=usdecimal(if_isset($_POST['outer_colli_price']),2);
	$campaign_cost=usdecimal(if_isset($_POST['campaign_cost']),2);
	$folgevarenr=db_escape_string(trim(if_isset($_POST['folgevarenr'])));
	$location=db_escape_string(trim(if_isset($_POST['location'])));
	$lagerantal=if_isset($_POST['lagerantal']);
	$lagerid=if_isset($_POST['lagerid']);
	$lagerlok=if_isset($_POST['lagerlok']);
	$m_type=if_isset($_POST['m_type']);
	$m_rabat_array=if_isset($_POST['m_rabat_array']);
	$m_antal_array=if_isset($_POST['m_antal_array']);
	$kat_valg=if_isset($_POST['kat_valg']);
	$kat_id=if_isset($_POST['kat_id']);
	$kat_antal=if_isset($_POST['kat_antal']);
	$ny_kategori=if_isset($_POST['ny_kategori']);
	$rename_category=if_isset($_POST['rename_category']);
	$vare_varianter=if_isset($_POST['vare_varianter']);
	$varianter_id=if_isset($_POST['varianter_id']);
	$var_type=if_isset($_POST['var_type']);
	$var_type_beh=if_isset($_POST['var_type_beh']);
	$var_type_stregk=if_isset($_POST['var_type_stregk']);
	$variant_vare_id=if_isset($_POST['variant_vare_id']);
	$variant_vare_stregkode=if_isset($_POST['variant_vare_stregkode']);
	$variant_vare_beholdning=if_isset($_POST['variant_vare_beholdning']);
	$lagerbeh=if_isset($_POST['lagerbeh']);
	$ny_lagerbeh=if_isset($_POST['ny_lagerbeh']);

	######### Kategorier #########
	for ($x=1;$x<=$kat_antal;$x++) {
#cho "KV $kat_valg[$x]<br>";
		if ($kat_valg[$x]) {
			($kategori)?$kategori.=chr(9).$kat_id[$x]:$kategori=$kat_id[$x];
		}
	}
	$cat=explode("|",$ny_kategori);
	$niveau=(count($cat)-1);
	$ny_kategori=$cat[$niveau];
	if ($niveau) {
		$master=$cat[0];
	} else $master=NULL;
	$tmp=findtekst(343,$sprog_id);
	$ny_kategori=trim($ny_kategori);
	$master=trim($master);
	
	$qtxt="select box4 from grupper where art='API'";
#cho __line__." $qtxt<br>";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$api_fil=trim($r['box4']);
#cho __line__." $api_fil<br>";
	transaktion('begin');
	$begin=1;
	if ($ny_kategori && $ny_kategori!=$tmp) {
		$x=0;
		$r=db_fetch_array($q=db_select("select id,box2 from grupper where art='V_CAT' and box1='".db_escape_string($ny_kategori)."' and box2='$master'",__FILE__ . " linje " . __LINE__));
#		$master_id=$r['id']*1;
		if (!$rename_category && $r=db_fetch_array($q=db_select("select id from grupper where art='V_CAT' and lower(box1) = '".db_escape_string(strtolower($ny_kategori))."' and box2='$master'",__FILE__ . " linje " . __LINE__))) {
			$alerttekst=findtekst(344,$sprog_id);
			$alerttekst=str_replace('$ny_kategori',$ny_kategori,$alerttekst);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\">\n";
		} elseif ($rename_category) { 
			db_modify("update grupper set box1='".db_escape_string($ny_kategori)."' where id='$rename_category'",__FILE__ . " linje " . __LINE__); 
			$rename_category=0;
		} else {
		if ($master) $r=db_fetch_array($q=db_select("select box2 from grupper where id='$master'",__FILE__ . " linje " . __LINE__));
			if ($r['box2']) $master=$r['box2'].chr(9).$master;
			db_modify("insert into grupper(beskrivelse,art,box1,box2) values ('Varekategorier','V_CAT','".db_escape_string($ny_kategori)."','$master')",__FILE__ . " linje " . __LINE__); 
			$r=db_fetch_array($q=db_select("select id from grupper where art='V_CAT' and lower(box1)= '".db_escape_string(strtolower($ny_kategori))."' and box2='$master'",__FILE__ . " linje " . __LINE__));
			($kategori)?$kategori.=chr(9).$r['id']:$kategori=$r['id'];
		}		$r=db_fetch_array(db_select("select box4 from grupper where art='API'",__FILE__ . " linje " . __LINE__));
		if ($shopurl && $shopurl!="!") {
			$url=$shopurl."/opdat_shop_kat.php";
#cho $url;
#			print "<body onload=\"javascript:window.open('$url','opdat:kat');\">";
		}
	}
	if (is_array($lagerlok)) {
		for ($x=1;$x<=count($lagerlok);$x++) {
			$qtxt="select id from lagerstatus where vare_id='$id' and lager='$x' limit 1";
			if ($r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="update lagerstatus set lok1='".db_escape_string($lagerlok[$x])."' where vare_id='$id' and lager='$x'";
			} else $qtxt="insert into lagerstatus (vare_id,lager,lok1) values ('$id','$x','$lagerlok[$x]')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}

	######### varianter #########
// følgende er en forglemmelse i opdat til 3.7.0 og kan fjernes efter opdat til 3.7.1. 
	$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='lagerstatus' and column_name='variant_id'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER TABLE lagerstatus add column	variant_id int",__FILE__ . " linje " . __LINE__);
	}

	$variant=NULL;
	for ($x=0;$x<=count($varianter_id);$x++) {
	if (isset($vare_varianter[$x]) && $vare_varianter[$x]) {
			($variant)?$variant.=chr(9).$varianter_id[$x]:$variant=$varianter_id[$x];
		}
	}
	if (!$variant) {
		db_modify("update batch_kob set variant_id='0' where vare_id='$id' and variant_id!='0'",__FILE__ . " linje " . __LINE__);
		db_modify("update batch_salg set variant_id='0' where vare_id='$id' and variant_id!='0'",__FILE__ . " linje " . __LINE__);
		db_modify("update lagerstatus set variant_id='0' where vare_id='$id' and variant_id!='0'",__FILE__ . " linje " . __LINE__);
	}
	for ($x=0;$x<count($variant_vare_id);$x++) {
		for ($l=1;$l<=count($variant_vare_beholdning[$x]);$l++) {
			$variant_vare_beholdning[$x][$l]=usdecimal($variant_vare_beholdning[$x][$l],2)*1;
			if ($variant_vare_stregkode[$x]) {
				$qtxt="select vare_id from variant_varer where variant_stregkode='$variant_vare_stregkode[$x]' and id !='$variant_vare_id[$x]'";
				if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$qtxt="select varenr,beskrivelse from varer where id = '$r[vare_id]'";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					alert("Stregkode allerede er brugt i varenr: $r[varenr] $r[beskrivelse]");
				} else {
					$qtxt="update variant_varer set variant_stregkode='$variant_vare_stregkode[$x]' where id='$variant_vare_id[$x]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			} else alert('Stregkode må ikke slettes');
			$qtxt="select id,beholdning from lagerstatus where vare_id='$id' and variant_id='$variant_vare_id[$x]' and lager='$l'"; 
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			#if ($r['beholdning'] != $variant_vare_beholdning[$x][$l]) {
				transaktion ('begin');
				lagerreguler($id,$variant_vare_beholdning[$x][$l],$kostpris[0],$lager,date('Y-m-d'),$variant_vare_id[$x]);
				transaktion ('commit');
			#}
			if ($api_fil) {
				$qtxt="select shop_variant from shop_varer where saldi_id='$id' and saldi_variant='".$variant_vare_id[$x]."'";
				if ($r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$header="User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
					$txt="/usr/bin/wget --spider --no-check-certificate --header='$header' '$api_fil?update_stock=$r[shop_variant]&stock=".$variant_vare_beholdning[$x][$l]."'";
					exec ("nohup $txt > /dev/null 2>&1 &\n");
				}
			}
		}
	}
	if ($var_type_beh || $var_type_stregk) {
		$ny_variant_type=NULL;
		for ($x=0;$x<count($var_type);$x++) {
			($ny_variant_type)?$ny_variant_type.=chr(9).$var_type[$x]:$ny_variant_type=$var_type[$x];
		}
		$qtxt="select id from variant_varer where vare_id='$id' and (variant_type='$ny_variant_type' or variant_stregkode='$var_type_stregk')";
		if($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$tmp=$r['id'];
		} else {
			($variantsum)?$var_type_beh=0:$var_type_beh=$beholdning*1;
			if (!$ny_variant_type) $ny_variant_type=1;
			$qtxt="insert into variant_varer(vare_id,variant_type,variant_stregkode,variant_beholdning) values ('$id','$ny_variant_type','$var_type_stregk','$var_type_beh')"; 
#cho __line__." $qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
			$qtxt="select id from variant_varer where vare_id='$id' and variant_type='$ny_variant_type' and variant_stregkode = '$var_type_stregk'"; 
#cho __line__." $qtxt<br>";
			$r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$ny_variant_id=$r['id'];
#cho "$ny_variant_id=$r[id]<br>";;
			$qtxt="insert into lagerstatus (vare_id,variant_id,lager,beholdning) values ('$id','$ny_variant_id','1','$var_type_beh')";
#cho __line__." $qtxt<br>";
#xit;
			db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
		}
	}
	if (($publ_pre || $publiceret) && $shopurl) {
		$kategorier=explode(chr(9),$kategori);
		$shop_kat_id='';
		for ($x=0;$x<count($kategorier);$x++){
#cho "select box3 from grupper where id='$kategorier[$x]'<br>";
#cho "select box3 from grupper where id='$kategorier[$x]'<br>";
			if($kategorier[$x] && $r=db_fetch_array($q=db_select("select box3 from grupper where id='$kategorier[$x]'",__FILE__ . " linje " . __LINE__))){
#cho "ZZZZZZZZ $r[box3]<br>";
				($shop_kat_id)?$shop_kat_id.=chr(9).$r['box3']:$shop_kat_id=$r['box3'];
			}
		}
	}
	######### Følgevarer #########
	if ($folgevarenr) {
		$r=db_fetch_array(db_select("select id from varer where varenr = '$folgevarenr'",__FILE__ . " linje " . __LINE__));
		if ($r['id']) $folgevare=$r['id']*1;
		elseif (substr($folgevarenr,0,4)=='MENU') {
			$folgevare=substr($folgevarenr,4)*-1;
		}	else print "<BODY onLoad=\"javascript:alert('Varenummer $folgevarenr eksisterer ikke!')\">";
	}
	if ($rabatgruppe) {
		$r=db_fetch_array(db_select("select * from grupper where art='VRG' and kodenr = '$rabatgruppe'",__FILE__ . " linje " . __LINE__));
		$m_type=$r['box1'];
		$m_rabat_array=explode(";",$r['box2']);
		$m_antal_array=explode(";",$r['box3']);		
	}
	if ($ny_gruppe && $ny_gruppe != $gruppe) {
		$r=db_fetch_array(db_select("select box8 from grupper where art='VG' and kodenr = '$ny_gruppe'",__FILE__ . " linje " . __LINE__));
		$tmp1= ($r['box8']);
		$r=db_fetch_array(db_select("select box8 from grupper where art='VG' and kodenr = '$gruppe'",__FILE__ . " linje " . __LINE__));
		$tmp2= ($r['box8']);
		if ($tmp1=='on' && $tmp1 != $tmp2) {
			$r=db_fetch_array(db_select("select sum(antal) as antal from batch_kob where vare_id='$id'",__FILE__ . " linje " . __LINE__));
			$ny_beholdning=$r['antal']*1;
			$r=db_fetch_array(db_select("select sum(antal) as antal from batch_salg where vare_id='$id'",__FILE__ . " linje " . __LINE__));
			$ny_beholdning=$ny_beholdning-$r['antal'];
			db_modify("update varer set beholdning='$ny_beholdning' where id = '$id'",__FILE__ . " linje " . __LINE__);
			$beholdning=$ny_beholdning;
		} elseif ($tmp1!='on') {
			$r = db_fetch_array(db_select("select box12 from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
				if (trim($r['box12'])) { 
				db_modify("update varer set beholdning='0' where id = '$id'",__FILE__ . " linje " . __LINE__);
				$beholdning=0;
				$ny_beholdning=0;
			}
		} else db_modify("update varer set beholdning='0' where id = '$id'",__FILE__ . " linje " . __LINE__);
		db_modify("update varer set gruppe = '$ny_gruppe' where id = '$id'",__FILE__ . " linje " . __LINE__);
		$gruppe=$ny_gruppe;
	}
	######### Moms #########

	if ($provisionPercentage >= 0) {
		$kostpris[0] = calculateCost($kostpris[0], $salgspris, $provisionPercentage);
	}

	$incl_moms=0;
	$qtxt="select var_value from settings where var_name = 'vatOnItemCard' and var_grp = 'items'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($vatOnItemCard=$r['var_value']) {
		$incl_moms=vatPercent($gruppe);
		$salgspris*=100/(100+$incl_moms);
		$salgspris2*=100/(100+$incl_moms);
		$special_price*=100/(100+$incl_moms);
	}


	if ($api_fil && !count($variant_vare_id)) { #20170210
		$qtxt="select shop_id from shop_varer where saldi_id='$id'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $shop_id=$r['shop_id'];
		else $shop_id.=urlencode("$varenr");
		$txt="/usr/bin/wget  -O - -q  --no-check-certificate --header='User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0' '$api_fil";
		$txt.="?update_stock=$shop_id";
		for ($x=1;$x<=$lagerantal;$x++) {
			$qtxt="select beholdning from lagerstatus where vare_id='$id' and lager = '$x'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$txt2=$txt."&stock=".$r['beholdning']*1;
			$txt2.="&stockno=$x'";
#cho "$txt2<br>";
			exec ("nohup $txt2 > /dev/null 2>&1 &\n");
		}
			
#			$qtxt="select shop_id from shop_varer where saldi_id='$id'";
#			$qtxt="select beholdning from lagerstatus where vare_id='$id' and lager = '$x'";
#			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $txt.=$r['shop_id'];
#			else $txt.=urlencode("$varenr");
#		$txt.="&stock=$afd";
#cho "$txt<br>";
#		exec ("nohup $txt > /dev/null 2>&1 &\n");
	} else { # skal udfases
		if (($publ_pre || $publiceret) && $shopurl && $shopurl != '!') {
#cho "$publ_pre || $publiceret) && $shopurl<br>";
			$shop_beholdning=$ny_beholdning;
			$r=db_fetch_array(db_select("select sum(ordrelinjer.antal-ordrelinjer.leveret) as antal from ordrer,ordrelinjer where ordrelinjer.vare_id = '$id' and ordrelinjer.ordre_id = ordrer.id and (ordrer.art='DO' or ordrer.art='DK') and (ordrer.status='1' or ordrer.status='2')",__FILE__ . " linje " . __LINE__));
			$shop_beholdning-=$r['antal'];
			$r=db_fetch_array($q=db_select("select shop_id from shop_varer where saldi_id='$id'",__FILE__ . " linje " . __LINE__));
			$shop_id=$r['shop_id'];
			$a=urlencode($varenr);
			$b=urlencode($beskrivelse[0]);
			$c=urlencode($notes);
			$d=urlencode($shop_kat_id);
			$e=urlencode($kategori);
			if (strpos($shopurl,'saldiapi')) {
				$url=str_replace("/?","/opdat_behold.php?",$shopurl); #20150521
				$saldiurl="://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
				if ($_SERVER['HTTPS']) $saldiurl="s".$saldiurl;
				$saldiurl="http".$saldiurl;
				$url.="&vare_id=$id&varenr=$a&beskrivelse=$b&notes=$c&saldiurl=$saldiurl&salgspris=$salgspris&special_price=$special_price";
				$url.="&shop_kat_id=$d&kat=$e&shop_id=$shop_id&beholdning=$shop_beholdning&publiceret=$publiceret";
			} elseif (strpos($shopurl,'opdat_behold')) {
				$url=$shopurl."/opdat_behold.php?vare_id=$id&varenr=$a&beskrivelse=$b&notes=$c&salgspris=$salgspris&shop_kat_id=$d&kat=$e&shop_id=$shop_id&beholdning=$shop_beholdning&publiceret=$publiceret";
			}
#xit;
			if ($url) print "<body onload=\"javascript:window.open('$url','opdat:behold');\">";
		}
	}
# Genererer tekststrenge med maengderabatter - decimaltaltal rettes til "us" og felter med antal "0" fjernes.
	for ($x=0;$x<count($m_rabat_array);$x++) {
		if (!isset($m_rabat_array[$x])) $m_rabat_array[$x]=NULL;
		if (!isset($m_antal_array[$x])) $m_antal_array[$x]=NULL;
		$tmp1=usdecimal($m_rabat_array[$x],2)*1;
		$tmp2=usdecimal($m_antal_array[$x],2)*1;
		if ($incl_moms && $m_type!="percent") $tmp1*=100/(100+$incl_moms);
		if ($tmp2) {
			if ($m_antal) {
				$m_rabat=$m_rabat.";".$tmp1;
				$m_antal=$m_antal.";".$tmp2;
			} else {
				$m_rabat=$tmp1;
				$m_antal=$tmp2;
			}
		}
	}
	if ($prisgruppe) {
		$r = db_fetch_array(db_select("select * from grupper where art='VPG' and kodenr = '$prisgruppe'",__FILE__ . " linje " . __LINE__));
		if ($r['box1']*1) $kostpris[0]=$r['box1']*1;
		if ($r['box2']*1) $salgspris=$r['box2']*1;
		if ($r['box3']*1) $retail_price=$r['box3']*1;
		if ($r['box4']*1) $tier_price=$r['box4']*1;
	}
######## Styklister ->
	$delvare=if_isset($_POST['delvare']);
	$samlevare=if_isset($_POST['samlevare']);
	$fokus=if_isset($_POST['fokus']);
	$be_af_ant=if_isset($_POST['be_af_ant']);
	$be_af_id=if_isset($_POST['be_af_id']);
	$ant_be_af=if_isset($_POST['ant_be_af']);
	$indg_i_id=if_isset($_POST['indg_i_id']);
	$indg_i_ant=if_isset($_POST['indg_i_ant']);
	$ant_indg_i=if_isset($_POST['ant_indg_i']);
	$indg_i_pos=if_isset($_POST['indg_i_pos']);
	$be_af_pos=if_isset($_POST['be_af_pos']);
	$be_af_vare_id=if_isset($_POST['be_af_vare_id']);
	$be_af_vnr=if_isset($_POST['be_af_vnr']);
	$be_af_beskrivelse=if_isset($_POST['be_af_beskrivelse']);

	if ($submit=="Slet") {
		db_modify("delete from varer where id = $id",__FILE__ . " linje " . __LINE__);
		db_modify("delete from shop_varer where saldi_id = $id",__FILE__ . " linje " . __LINE__);
	}	else {
		if (($salgspris == 0)&&($salgspris2 > 0)&&($forhold > 0)) $salgspris=$salgspris2*$forhold;
		if ($salgspris >= 100000000000) {
			print tekstboks("Salgspris ".dkdecimal($salgspris,2)." for høj - nulstillet");
			$salgspris=0;
		}
		for($x=1; $x<=$lev_antal; $x++) {
			if (($lev_pos[$x]!="-")&&($lev_pos[$x])) {
				$lev_pos[$x]=$lev_pos[$x]*1;
				if (($kostpris[$x] == 0)&&($kostpris2[$x] > 0)&&($forhold > 0)) $kostpris[$x]=$kostpris2[$x]*$forhold;
				$kostpris[$x]=usdecimal($kostpris[$x],2);
				$lev_varenr[$x]=db_escape_string(trim($lev_varenr[$x]));
				db_modify("update vare_lev set posnr = $lev_pos[$x], lev_varenr = '$lev_varenr[$x]', kostpris = '$kostpris[$x]' where id = '$vare_lev_id[$x]'",__FILE__ . " linje " . __LINE__);
			} elseif (!$lev_pos[$x]) {print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte et - (minus) som pos nr for at slette en leverand&oslash;r!')\">";}
			else {db_modify("delete from vare_lev where id = '$vare_lev_id[$x]'",__FILE__ . " linje " . __LINE__);}
		}
		for ($x=1;$x<=$vare_sprogantal;$x++) {
			$tmp=db_escape_string($beskrivelse[$x]);
			if ($vare_tekst_id[$x]) db_modify("update varetekster set tekst='$tmp' where id='$vare_tekst_id[$x]'",__FILE__ . " linje " . __LINE__);
			elseif($vare_sprog_id[$x]) {
				db_modify("insert into varetekster(vare_id,sprog_id,tekst) values ('$id','$vare_sprog_id[$x]','$tmp')",__FILE__ . " linje " . __LINE__); 
			}
		}
		if (!$min_lager)$min_lager='0';
		else $min_lager=usdecimal($min_lager,2);
		if (!$max_lager) $max_lager='0';
		else $max_lager=usdecimal($max_lager,2);
		if (!$lukket) $lukket='0';
		else $lukket='1';
		 if (strlen(trim($indg_i_ant[0]))>1) {
			list ($x) = explode(':',$indg_i_ant[0]);
#			$fejl=cirkeltjek($x, 'vare_id');
		}
		if (isset($be_af_ant[0]) && strlen(trim($be_af_ant[0]))>1) {
			list ($x) = explode(':',$be_af_ant[0]);
		}
		if (($delvare=='on')&&($gl_kostpris-$kostpris[0]!=0)) {
			$diff=$kostpris[0]-$gl_kostpris;
			prisopdat($id, $diff);
		}	
		if (!$fejl) {
		if (($samlevare!='on')&&($ant_be_af>0)) {
			print "Du skal s&aelig;tte antal til 0 p&aring; samtlige varer som denne vare best&aring;r af, f&oslash;r du fjerner fluebenet i \"samlevare\"!<br>";
			$samlevare='on';
		}
		if(!$betalingsdage){$betalingsdage=0;}
		if ($id==0) {
			$query = db_select("select id from varer where lower(varenr) = '".strtolower($varenr)."' or  upper(varenr) = '".strtoupper($varenr)."'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			if ($row['id']) {
				print "<BODY onLoad=\"javascript:alert('Der findes allerede en vare med varenr: $varenr!')\">";
				$varenr='';
				$id=0;
			} elseif ($varenr) {
#				db_modify("insert into varer (varenr, text, enhed, enhed2, forhold, salgspris, gruppe, serienr, lukket, notes, samlevare, delvare, min_lager) values ('$varenr', '$text', '$enhed', '$enhed2', '$forhold', '$salgspris', '$gruppe', '$serienr', '$lukket', '$notes', '$samlevare', '$delvare', '$min_lager')",__FILE__ . " linje " . __LINE__);
				$defaultProvision = defaultProvision()*1;
				db_modify("insert into varer (varenr, lukket,provision) values ('$varenr', '0', '$defaultProvision')",__FILE__ . " linje " . __LINE__);
				$query = db_select("select id from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$id = $row['id'];
				if ($vare_lev_id) {db_modify("insert into vare_lev (lev_id, vare_id, posnr) values ($vare_lev_id, $id, 1)",__FILE__ . " linje " . __LINE__);}
			} else print "<BODY onLoad=\"javascript:alert('Skriv et varenummer i feltet og pr&oslash;v igen!')\">";
		}
		elseif ($id > 0) {
			
			if (!$leverandor) $leverandor='0';
			if ($stregkode) {
				if($r=db_fetch_array(db_select("select varenr,beskrivelse from varer where stregkode='$stregkode' and id !='$id'",__FILE__ . " linje " . __LINE__))) {
					print "<BODY onLoad=\"javascript:alert('Varenr: $r[varenr] | $r[beskrivelse] har samme stregkode')\">";
					$stregkode='';
				}
			}
			if ($indhold!=round($indhold,0)) { # 
				$tmp=$indhold;
				db_modify("update varer set indhold='$tmp' where id = '$id'",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select indhold from varer where id='$id'",__FILE__ . " linje " . __LINE__));
				if ($r['indhold']!=$indhold){
					db_modify("ALTER TABLE varer ALTER COLUMN indhold TYPE numeric(15,3)",__FILE__ . " linje " . __LINE__);
				}
			}
			if ($special_from_time) {
				list($t,$m)=explode(":",$special_from_time);
				$t*=1;$t*=1;
				if ($t>23) $t=23;
				if ($m>59) $m=59;
				$special_from_time=$t.":".$m.":00";
				list($t,$m)=explode(":",$special_to_time);
				$t*=1;$t*=1;
				if ($t>23) $t=23;
				if ($m>59) $m=59;
				if ($m<59) $s='00';
				else $s=59;
				$special_to_time=$t.":".$m.":".$s;
			} else {
				$special_from_time="00:00:00";
				$special_to_time="00:00:00";
			}
			$qtxt="update varer set stregkode = '$stregkode',enhed='$enhed',enhed2='$enhed2',";
			$qtxt.="indhold='$indhold',forhold='$forhold',salgspris = '$salgspris',kostpris = '$kostpris[0]',provisionsfri = '$provisionsfri',";
			$qtxt.="gruppe = '$gruppe',prisgruppe = '$prisgruppe',tilbudgruppe = '$tilbudgruppe',rabatgruppe = '$rabatgruppe',serienr = '$serienr',";
			$qtxt.="lukket = '$lukket',notes = '$notes',samlevare='$samlevare',min_lager='$min_lager',max_lager='$max_lager',trademark='$trademark',";
			$qtxt.="retail_price='$retail_price',special_price='$special_price',tier_price='$tier_price',special_from_date='$special_from_date',";
			$qtxt.="special_to_date='$special_to_date',special_from_time='$special_from_time',special_to_time='$special_to_time',";
			$qtxt.="colli='$colli',outer_colli='$outer_colli',open_colli_price='$open_colli_price',outer_colli_price='$outer_colli_price',";
			$qtxt.="campaign_cost='$campaign_cost',location='$location',folgevare='$folgevare',montage='$montage',demontage='$demontage',";
			$qtxt.="m_type='$m_type',m_antal='$m_antal',m_rabat='$m_rabat',dvrg='$dvrg_nr[0]',kategori='$kategori',varianter='$variant',";
			$qtxt.="publiceret='$publiceret',operation='$operation',netweight='$netWeight',grossweight='$grossWeight' where id = '$id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			if ($submit=='Kopier') {
				$r=db_fetch_array(db_select("select id from varer where varenr='kopi af $varenr'",__FILE__ . " linje " . __LINE__));
				if ($r['id']) alert ("ret varenr på kopi af `$varenr` først"); 
				else {
					db_modify("insert into varer (varenr,beskrivelse) values ('kopi af $varenr','$beskrivelse[0]')",__FILE__ . " linje " . __LINE__);
					$r=db_fetch_array(db_select("select id from varer where varenr='kopi af $varenr'",__FILE__ . " linje " . __LINE__));
					$copy_id=$r['id'];
					$qtxt=str_replace("where id = '$id'","where id = '$copy_id'",$qtxt);
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					transaktion('commit');
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ret_varenr.php?id=$copy_id\">";
					exit;
				}
			}
			$dd=date("Y-m-d");
			$qtxt="select id,kostpris,transdate from kostpriser where vare_id='$id' order by transdate desc limit 1";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$qtxt=NULL;
			if ($r['transdate'] != $dd && $r['kostpris'] != $kostpris[0]) {
				$qtxt="insert into kostpriser (vare_id,kostpris,transdate) values ('$id','$kostpris[0]','$dd')";
			} elseif ($r['transdate'] == $dd && $r['kostpris'] != $kostpris[0]) {
				$qtxt="update kostpriser set kostpris=$kostpris[0] where id = '$r[id]'";
			}
			if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__); 

			if (($operation)&&($r=db_fetch_array(db_select("select varenr from varer where operation = '$operation' and id !=$id",__FILE__ . " linje " . __LINE__)))) {
				print "<BODY onLoad=\"javascript:alert('Operationsnr: $operation er i brug af $r[varenr]! Operationsnr ikke &aelig;ndret')\">";
			} elseif ($operation) {
				$r=db_fetch_array(db_select("select box10 from grupper where art='VG' and kodenr = '$gruppe'",__FILE__ . " linje " . __LINE__));
				if ($r[box10]!='on') $operation=0;
				db_modify("update varer set operation = '$operation' where id = '$id'",__FILE__ . " linje " . __LINE__);
			}
	
######################################## Stykliste ############################################
			if ($samlevare=='on') {
				for ($x=1; $x<=$ant_be_af; $x++) {
					$be_af_ant[$x]=usdecimal($be_af_ant[$x],2);
					if ($be_af_pos[$x]=="-") $be_af_ant[$x]=0; 
					if (($be_af_ant[$x]>0)&&($be_af_pos[$x])) {
						$be_af_pos[$x]=round($be_af_pos[$x]);
						$qtxt="update styklister set antal = $be_af_ant[$x], posnr = $be_af_pos[$x] where id = '$be_af_id[$x]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
					else {
					db_modify("delete from styklister where id = '$be_af_id[$x]'",__FILE__ . " linje " . __LINE__);}
				}
				if (($be_af_vnr[0])||($be_af_beskrivelse[0])) {
					$be_af_pos[0]=round($be_af_pos[0]);
					if (($be_af_vnr[0])&&($be_af_beskrivelse[0])) $query = db_select("select id from varer where varenr = '$be_af_vnr[0]' or beskrivelse = '$be_af_beskrivelse[0]'",__FILE__ . " linje " . __LINE__);
					elseif ($be_af_vnr[0]) $query = db_select("select id from varer where varenr = '$be_af_vnr[0]'",__FILE__ . " linje " . __LINE__);
					elseif ($be_af_beskrivelse[0]) $query = db_select("select id from varer where beskrivelse = '$be_af_beskrivelse[0]'",__FILE__ . " linje " . __LINE__);
					if ($row = db_fetch_array($query)) {
						if (($row['id']==$id)||(in_array($row['id'],$be_af_vare_id))) {}
						elseif (cirkeltjek($row['id'])==0) {
							db_modify("insert into styklister (vare_id, indgaar_i, antal, posnr) values ('$row[id]', '$id', '1', '$be_af_pos[0]')",__FILE__ . " linje " . __LINE__);
							db_modify("update varer set delvare =  'on' where id = '$row[id]'",__FILE__ . " linje " . __LINE__);
						}
					}
					elseif (($be_af_vnr[0])&&($be_af_beskrivelse[0])) {
						if (!strpos($be_af_vnr[0],"*")) $be_af_vnr[0]="*".$be_af_vnr[0]."*";
						if (!strpos($be_af_beskrivelse[0],"*")) $be_af_beskrivelse[0]="*".$be_af_beskrivelse[0]."*";
						$fokus="varenr";
						$find="'".$be_af_vnr[0]."' and beskrivelse like '".$be_af_beskrivelse[0]."'";
					}
					elseif ($be_af_vnr[0]) {
						if (!strpos($be_af_vnr[0],"*")) $be_af_vnr[0]="*".$be_af_vnr[0]."*";
						$fokus="varenr";
						$find="'".$be_af_vnr[0]."'";
					}
					else {
						if (!strpos($be_af_beskrivelse[0],"*")) $be_af_beskrivelse[0]="*".$be_af_beskrivelse[0]."*";
						$fokus="beskrivelse0";
						$find="'".$be_af_beskrivelse[0]."'";
					}
				}
				$kostpris[0]=stykliste($id,0,'')*1;
				db_modify("update varer set kostpris = '$kostpris[0]' where id = '$id'",__FILE__ . " linje " . __LINE__);

			}
/*
			if ($delvare=='on') {
				for ($x=1; $x<=$ant_indg_i; $x++)	{
					if ($indg_i_ant[$x]>0) {
					#	$indg_i_ant[$x]=round($indg_i_ant[$x]);
						db_modify("update styklister set antal = $indg_i_ant[$x] where id = '$indg_i_id[$x]'",__FILE__ . " linje " . __LINE__);
					}
					else {db_modify("delete from styklister where id = '$indg_i_id[$x]'",__FILE__ . " linje " . __LINE__);}
				}
				if (strlen(trim($indg_i_ant[0]))>1) {
					list ($x) = explode(':',$indg_i_ant[0]);
					$x=trim($x);
					$query = db_select("select id from varer where varenr = '$x'",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					db_modify("insert into styklister (vare_id, indgaar_i, antal) values ($id, $row[id], 1)",__FILE__ . " linje " . __LINE__);
					db_modify("update varer set samlevare='on' where id = $row[id]",__FILE__ . " linje " . __LINE__);
				}
			}
*/
#############################################################################################
		}
		$leverandor=trim($leverandor);
		if ($leverandor) {
			$query = db_select("select id from adresser where kontonr='$leverandor' and art = 'K'",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {
				db_modify("insert into vare_lev (lev_id, vare_id) values ('$row[id]', '$id')",__FILE__ . " linje " . __LINE__);
			}
		}
	 }
	}
} elseif ($id && isset($_POST['ChangeDescription']) && $_POST['ChangeDescription']=='Ja') {
	$newDecsription=db_escape_string($_POST['newDecsription']);
	$qtxt="update varer set beskrivelse='$newDecsription' where id='$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}


if (!isset ($ny_lagerbeh)) $ny_lagerbeh = NULL;
if (!isset ($submit)) $submit = NULL;

for($x=1;$x<=count($ny_lagerbeh);$x++) {
	if ($ny_lagerbeh[$x]!=$lagerbeh[$x]) {
		if($samlevare) {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=vareproduktion.php?id=$id&antal=1&lager=$x&ny_beholdning=$ny_lagerbeh[$x]&samlevare=$samlevare\">";
			exit;
		} else {
			lagerreguler($id,$ny_lagerbeh[$x],$kostpris[0],$x,date("Y-m-d"),'0');
		}
	}
} #elseif ($ny_beholdning != $beholdning) {
#	else lagerreguler($id,$ny_beholdning,$kostpris[0],0,date("Y-m-d"));

	#	else print "<meta http-equiv=\"refresh\" content=\"0;URL=lagerregulering.php?id=$id&antal=1&ny_beholdning=$ny_beholdning\">";
#	sleep(10); 
#}
#exit;

if ($popup && !$returside) $returside="../includes/luk.php";
elseif (!$returside) $returside="varer.php";
$tekst=findtekst(154,$sprog_id);

if ($begin) transaktion('commit');

if (strstr($submit, "Leverand")) kontoopslag("navn", $fokus, $id);
elseif (strstr($submit, "Vare")) {
	if (!$sort) $sort="varenr"; if (!$fokus) $fokus="varenr";
	vareopslag ($sort, $fokus, $id, $vis_kost, $ref, $find, "varekort.php");
}
if ($submit && $beskrivelse[0] != $oldDescription) {
	if ($confirmDiscriptionChange) include ("varekort_includes/changeDescription.php");
	else {
		db_modify("update varer set beskrivelse = '$beskrivelse[0]' where id= '$id'",__FILE__ . " linje " . __LINE__);
	}
}
################################################## OUTPUT ####################################################
print "<center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
if ($menu=='T') {
	include_once '../includes/top_menu.php';
	include_once '../includes/top_header.php';
	print "<div id=\"header\"> 
	<div class=\"headerbtnLft\"><a title=\"Klik her for at lukke varekortet\" class=\"button red small left\" href=$returside accesskey=\"L\">Luk</a></div>
	<span class=\"headerTxt\">$title</span>";     
	print "<div class=\"headerbtnRght\"></div>";       
	print "</div><!-- end of header -->
		<div class=\"maincontentLargeHolder\">\n";
	print  "<center><table border='0' cellspacing='1' width='75%' align='center';>";
} else {
	print "<tr><td align=\"center\" valign=\"top\">\n";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
	$tmp = ($popup) ? "onClick=\"javascript=opener.location.reload();\"" : ""; 
	if ($opener!='varer.php') print "<td width=\"10%\" $top_bund><a href=\"javascript:confirmClose('$returside?id=$ordre_id&fokus=$fokus&varenr=$varenr&vare_id=$id','$tekst')\" accesskey=L>Luk</a></td>\n";
	else print "<td width=\"10%\" $tmp $top_bund> <a href=\"javascript:confirmClose('$returside?','$tekst')\" accesskey=L>Luk</a></td>\n";
	print "<td width=\"80%\" $top_bund align=\"center\"> varekort</td>\n";
	if ($id) print "<td width=\"10%\" $top_bund align=\"right\"><a href=\"javascript:confirmClose('varekort.php?opener=$opener&returside=$returside&ordre_id=$id','$tekst')\" accesskey=N>Ny</a>\n";
	print "</td></tbody></table>\n";
	print "</td></tr>\n";
	print "<td align = center valign = center>\n";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"80%\"><tbody>\n";
}
$vare_varianter=array();
if ($id > 0) {
	$query = db_select("select * from varer where id = '$id'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$varenr=$row['varenr'];
	$stregkode=$row['stregkode'];
	$beskrivelse[0]=$row['beskrivelse'];
#cho "$beskrivelse[0]<br>";
	$enhed=$row['enhed'];
	$enhed2=$row['enhed2'];
	$indhold=if_isset($row['indhold']);
	$forhold=if_isset($row['forhold']);
	$salgspris=if_isset($row['salgspris']);
	$kostpris[0]=if_isset($row['kostpris']);
	$montage=$row['montage']; # montagepris til stillads
	$demontage=$row['demontage']; # demontagepris til stillads
	$provisionsfri=if_isset($row['provisionsfri']); 
	$publiceret=if_isset($row['publiceret']); 
	$gruppe=if_isset($row['gruppe'])*1;
	$prisgruppe=if_isset($row['prisgruppe'])*1;
	$rabatgruppe=if_isset($row['rabatgruppe'])*1;
	$dvrg_nr[0]=if_isset($row['dvrg'])*1; # DebitorVareRabatGruppe
	$serienr=if_isset($row['serienr']);
	$lukket=if_isset($row['lukket']);
	$notes=if_isset($row['notes']);
	$delvare=if_isset($row['delvare']);
	$samlevare=if_isset($row['samlevare']);
	$min_lager=dkdecimal(if_isset($row['min_lager']),2);
	$max_lager=dkdecimal(if_isset($row['max_lager']),2);
	$beholdning=if_isset($row['beholdning'])*1;
	$operation=if_isset($row['operation'])*1;
	$trademark=if_isset($row['trademark']);
	$location=if_isset($row['location']);
	$folgevare=if_isset($row['folgevare'])*1;
	$special_price=if_isset($row['special_price']);
	$campaign_cost=if_isset($row['campaign_cost']);
	$special_from_date=if_isset($row['special_from_date']);
	$special_to_date=if_isset($row['special_to_date']);
	$special_from_time=substr(if_isset($row['special_from_time']),0,5);
	$special_to_time=substr(if_isset($row['special_to_time']),0,5);
	$retail_price=if_isset($row['retail_price']);
	$tier_price=if_isset($row['tier_price']);
	$colli=if_isset($row['colli']);
	$outer_colli=if_isset($row['outer_colli']);
	$open_colli_price=if_isset($row['open_colli_price']);
	$outer_colli_price=if_isset($row['outer_colli_price']);
	$campaign_cost=if_isset($row['campaign_cost']);
	$m_type=if_isset($row['m_type']);
	$m_rabat_array=explode(";",if_isset($row['m_rabat']));
	$m_antal_array=explode(";",if_isset($row['m_antal']));
	$kategori=explode(chr(9),if_isset($row['kategori']));
	$kategori_antal=count($kategori);
	$fotonavn=if_isset($row['fotonavn']);
	$grossWeight=if_isset($row['grossweight']);
	$netWeight=if_isset($row['netweight']);
	
	if ($ny_beholdning) $beholdning=$ny_beholdning; 
	if ($row['varianter']) $vare_varianter=explode(chr(9),$row['varianter']);

	if ($r=db_fetch_array($q=db_select("select shop_id from shop_varer where saldi_id='$id'",__FILE__ . " linje " . __LINE__))) {
		$shop_id=$r['shop_id'];
		$publiceret='on';
	} else {
		$shop_id=NULL;
#		$publiceret=NULL;
	}
	$qtxt="select var_value from settings where var_name = 'vatOnItemCard' and var_grp = 'items'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$vatOnItemCard=$r['var_value'];
	if (!$vatOnItemCard) { // remove this after rel 3.7.6
		$r = db_fetch_array(db_select("select id,box1 from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__));
		if ($r['box1']) {
			db_modify("update grupper set box1='' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
			$qtxt="select id from settings where var_name = 'vatOnItemCard' and var_grp = 'items'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if (!$r['id']) {
				$qtxt="insert into settings(var_grp,var_name,var_value,var_description,user_id) values ";
				$qtxt.="('items','vatOnItemCard','on','Hvis sat, vises salgspriser incl. moms på varekort ','0')"; 
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				db_modify("update grupper set box1 = '' where id='$id'",__FILE__ . " linje " . __LINE__);
			}
		}
	}
// <-
	$incl_moms=0;
	if ($vatOnItemCard) {
		$qtxt="select box4,box7 from grupper where art='VG' and kodenr='$gruppe' and box7!='on'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$konto = $r['box4'];
			$qtxt="select moms from kontoplan where kontonr='$r[box4]' order by id desc limit 1";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$momskode=str_replace("S","",$r['moms']);	
			$qtxt="select box2 from grupper where art='SM' and kodenr = '$momskode'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$incl_moms=$r['box2']*1;
		}
	} 
	$salgspris*=(100+$incl_moms)/(100);
	$special_price*=(100+$incl_moms)/(100);

	if ($folgevare > 0) {
		$r=db_fetch_array(db_select("select varenr from varer where id = '$folgevare'",__FILE__ . " linje " . __LINE__));
		$folgevarenr=$r['varenr'];
	} elseif ($folgevare < 0) {
		if ($r=db_fetch_array(db_select("select id from grupper where kodenr = '".abs($folgevare)."'",__FILE__ . " linje " . __LINE__))) {
			$folgevarenr='MENU'.abs($folgevare);
		}
	}
	$x=0;
	$q=db_select("select * from grupper where art='DVRG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)){ 
		$x++;
		$dvrg_nr[$x]=$r['kodenr']*1;
		$dvrg_navn[$x]=$r['box1'];
	}
	$r=db_fetch_array(db_select("select * from grupper where art='VPG' and kodenr = '$prisgruppe'",__FILE__ . " linje " . __LINE__));
	$p_grp_kostpris=$r['box1']*1;
	$p_grp_salgspris=$r['box2']*1;
	$p_grp_retail_price=$r['box3']*1;
	$p_grp_tier_price=$r['box4']*1;

	if ($tilbudgruppe) {
		$r=db_fetch_array(db_select("select * from grupper where art='VTG' and kodenr = '$tilbudgruppe'",__FILE__ . " linje " . __LINE__));
		$campaign_cost=$r['box1']*1;
		$special_price=$r['box2']*1;
		$special_from_date=$r['box3']*1;
		$special_to_date=$r['box4']*1;
	}
	if ($rabatgruppe) {
		$r=db_fetch_array(db_select("select * from grupper where art='VRG' and kodenr = '$rabatgruppe'",__FILE__ . " linje " . __LINE__));
		$m_type=$r['box1'];
		$m_rabat_array=explode(";",$r['box2']);
		$m_antal_array=explode(";",$r['box3']);
	}

#	$kpris=dkdecimal($row['kostpris']);
	$query = db_select("select * from grupper where art='VG' and kodenr = '$gruppe'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$box8=$row['box8'];
	$box9=$row['box9'];
} else {
	$gruppe=1;
	$leverandor=0;
}
if (!isset ($min_lager)) $min_lager = NULL;
if (!isset ($max_lager)) $max_lager = NULL;

if (!$min_lager) $min_lager=0;
if (!$max_lager) $max_lager=0;

if (!$lagerantal) {
	$lagerantal=1;
	$qtxt="select kodenr from grupper where art='LG'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['kodenr']>$lagerantal) $lagerantal=$r['kodenr'];
	}
}
$x=0;
$q=db_select("select * from grupper where art = 'VSPR' order by kodenr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$x++;
	$vare_sprog_id[$x]=$r['kodenr'];
	$vare_sprog[$x]=$r['box1'];
	$r2=db_fetch_array(db_select("select * from varetekster where vare_id='$id' and sprog_id = '$vare_sprog_id[$x]'",__FILE__ . " linje " . __LINE__));
	$vare_tekst_id[$x]=$r2['id']*1;
	$beskrivelse[$x]=$r2['tekst'];
}
$vare_sprogantal=$x;

$x=0;
$q=db_select("select * from varianter",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$x++;
	$varianter_id[$x]=$r['id'];
	$varianter_beskrivelse[$x]=$r['beskrivelse'];
}
$variant_antal=$x;

if($r=db_fetch_array(db_select("select id from grupper where art = 'LABEL' and box1 != ''",__FILE__ . " linje " . __LINE__))) $labelprint=1;
else $labelprint=NULL;

################################# output #####################################

if (!isset ($publiceret)) $publiceret = NULL;
if (!isset ($vare_lev_id)) $vare_lev_id = NULL;
if (!isset ($fifo)) $fifo = NULL;
if (!isset ($varianter)) $varianter = NULL;
if (!isset ($ant_be_af)) $ant_be_af = NULL;

print "<form name=varekort action=varekort.php?opener=$opener method=post>";

print "<input type=hidden name=id value='$id'>";
print "<input type=hidden name=ordre_id value='$ordre_id'>";
print "<input type=hidden name=returside value='$returside'>";
print "<input type=hidden name=fokus value='$fokus'>";
print "<input type=hidden name=leverandor value='$lev'>";
print "<input type=hidden name=lagerantal value='$lagerantal'>";
print "<input type=hidden name=vare_sprogantal value='$vare_sprogantal'>";
print "<input type=hidden name=publ_pre value='$publiceret'>";
for ($x=1;$x<=$vare_sprogantal;$x++) {
	print "<input type=hidden name=vare_sprog_id[$x] value='$vare_sprog_id[$x]'>";
}

($noEdit)?$href=NULL:$href="ret_varenr.php?id=$id";
print "<tr><td colspan=\"3\" align=\"center\"><b>Varenr: <a href=\"$href\">$varenr</a></b></td></tr>";
if (!$varenr) {
	$fokus="varenr";
	print "<input type=\"hidden\" name=\"vare_lev_id\" value=\"$vare_lev_id\">";
	print "<td colspan=\"3\" align=\"center\"><input class=\"inputbox\" type=\"text\" size=\"25\" name=\"varenr\" value=\"$varenr\" onchange=\"javascript:docChange = true;\"></td></tr>";
} else {
	print "<input type=\"hidden\" name=\"varenr\" value=\"$varenr\">";
	print "<tr><td colspan='4' width='100%'><table bordercolor='#FFFFFF' border='1' cellspacing='5' width='100%'><tbody>";
	print "<tr><td colspan='2' valign='top'><table border='0' width='100%'><tbody>"; # Pris enhedstabel ->
	if (!$beskrivelse[0]) $fokus="beskrivelse0";
	print "<tr><td width='17%'>Beskrivelse</td><td>";
	print "<input type='hidden' name='oldDescription' value=\"$beskrivelse[0]\">";
	print "<input class='inputbox' type='text' style='text-align:left;width:400px;' name=beskrivelse0 value=\"$beskrivelse[0]\" onchange=\"javascript:docChange = true;\"></td>";
#	print "<a href=changeDescription.php?id=$id>$beskrivelse[0]</a></td>";
	print "<td rowspan='6' valign='top'>";
	$r=db_fetch_array(db_select("select box6 from grupper where art = 'bilag'",__FILE__ . " linje " . __LINE__));
	if ($r['box6']) {
		if ($fotonavn) {
			$fotourl="../owncloud/".$db."/varefotos/".$id;
			($noEdit)?$href=NULL:$href="varefoto.php?id=$id&fotonavn=".urlencode($fotonavn);
			print "<a href=\"$href\"><img style=\"border:0px solid;height:100px\" alt=\"$fotonavn\" src=\"$fotourl\"></a>";
		} else {
			($noEdit)?$href=NULL:$href="varefoto.php?id=$id";
			print "<a href=\"$href\">tilknyt billede</a>";
		}
	}
	print "</td></tr>";
	for ($x=1;$x<=$vare_sprogantal;$x++) {
		print "<input type=\"hidden\" name=\"vare_tekst_id[$x]\" value=\"$vare_tekst_id[$x]\">";
		print "<tr><td>$vare_sprog[$x]</td><td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:400px;\" name=\"beskrivelse[$x]\" value=\"$beskrivelse[$x]\" onchange=\"javascript:docChange = true;\"></td></tr>";
	}
	print "<tr><td>Varem&aelig;rke</td><td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:400px;\" name=\"trademark\" value=\"$trademark\" onchange=\"javascript:docChange = true;\"></td></tr>";
################# VARIANTER #######################
	if ($vare_varianter) {
		print "<tr><td colspan=\"2\"><hr></td></tr>";
		print "<tr><td colspan=\"2\">Varianter</td></tr>";
		print "<tr><td colspan=\"2\"><table border=\"0\"><tbody>";

		print "<tr>";
		for ($i=0;$i<count($vare_varianter);$i++) {
			$qtxt="select beskrivelse from varianter where id = '$vare_varianter[$i]'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			print "<td>$r2[beskrivelse]</td>";
		}
		print "</tr>";
		$x=0;
		$qtxt="select * from variant_varer where vare_id = '$id'";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$variant_varer_id[$x]=$r['id'];
			$variant_varer_stregkode[$x]=$r['variant_stregkode'];
			$variant_varer_type[$x]=explode(chr(9),$r['variant_type']);
			$variantVarerVariantId[$x]=$r['variant_id'];
			$x++;
		}
		$x=0;
		$qtxt="select * from lagerstatus where vare_id='$id' order by lager, variant_id";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if (in_array($r['variant_id'],$variant_varer_id)) {
				$variant_lager_id[$x]=$r['id'];
				$variant_lager[$x]=$r['lager'];
				for ($l=1;$l<=($lagerantal);$l++) {
					$variant_lagerbeh[$x][$l]=$r['beholdning'];
					$x++;
				}
			}
		}
		array_multisort($variant_varer_id,$variant_varer_stregkode,$variant_varer_type);
		
		for ($x=0;$x<count($variant_varer_id);$x++) {
			$var_beh[$x]=0;
			for($l=1;$l<=$lagerantal;$l++) {
				$variant_beholdning[$x][$l]=0;
				$qtxt="select beholdning from lagerstatus where vare_id='$id' and lager='$l' and variant_id=$variant_varer_id[$x]";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($r2['beholdning']) $variant_beholdning[$x][$l]=$r2['beholdning'];
				$var_beh[$x]+=$variant_beholdning[$x][$l];
				#cho "VB ".$variant_beholdning[$x][$l]."<br>";
			}
			
			print "<tr>";
			for ($i=0;$i<count($variant_varer_type[$x]);$i++) {
				$variant_varer_type[$x][$i]*=1;
				$qtxt="select beskrivelse from variant_typer where id = '".$variant_varer_type[$x][$i]."'";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				print "<td width=50\"px\">$r2[beskrivelse]</td>";
			}
			print "<input type=\"hidden\" name=\"variant_vare_id[$x]\" value=\"$variant_varer_id[$x]\">";
			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:400px;\" name=\"variant_vare_stregkode[$x]\" value=\"$variant_varer_stregkode[$x]\" onchange=\"javascript:docChange = true;\"></td>";
			for ($l=1;$l<=$lagerantal;$l++) {
				if ($r2['beskrivelse']) {
					print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=\"variant_vare_beholdning[$x][$l]\" value=\"".dkdecimal($variant_beholdning[$x][$l],2)."\" onchange=\"javascript:docChange = true;\"></td>";
				} else print "<td></td>"; 
			}
			if ($var_beh[$x]) { 
				print "<td></td>";
			} else {
				print "<td title=\"".findtekst(397,$sprog_id)."\">";
				print "<!--tekst 396--><a href=\"varekort.php?id=$id&delete_var_type=$variant_varer_id[$x]\" onclick=\"return confirm('Vil du slette denne variant fra listen?')\">";
				print "<img src=../ikoner/delete.png border=0></a></td>\n";
			}
			$png=barcode($variant_varer_stregkode[$x]);
			if ($png) {
				if ($labelprint && file_exists($png)) print "<td align=\"right\"><a href=\"../lager/labelprint.php?id=$id&beskrivelse=".urlencode($beskrivelse[0])."&stregkode=".urlencode($variant_varer_stregkode[$x])."&src=$png&pris=$salgspris&enhed=$enhed&indhold=$indhold\" target=\"blank\"><img src=\"../ikoner/print.png\" style=\"border: 0px solid;\"></a></td>";
			} 
			print "</tr>";
		}
		print "</tbody></table></td>";
	} else {
		print "<tr><td>Stregkode</td><td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:400px;\" name=\"stregkode\" value=\"$stregkode\" onchange=\"javascript:docChange = true;\"></td>";
	}
	print "</tbody></table></td>";
	#print "<tr><td>Varianter</td><td>";
	if (!count($vare_varianter) || !$box8){ 	
		($stregkode)?$tmp=$stregkode:$tmp=$varenr;
		$png=barcode($tmp);
		if ($png) {
			print "<td align=\"center\"><table width=\"100%\"><tbody>";
			if ($labelprint && file_exists($png)) print "<tr><td align=\"right\"><a href=\"../lager/labelprint.php?id=$id&beskrivelse=".urlencode($beskrivelse[0])."&stregkode=".urlencode($tmp)."&src=$png&pris=$salgspris&enhed=$enhed&indhold=$indhold\" target=\"blank\"><img src=\"../ikoner/print.png\" style=\"border: 0px solid;\"></a></td></tr>";
#		print "<tr><td align=\"center\">$beskrivelse[0]</td></tr>";
			if (file_exists($png)) print "<tr><td align=\"center\"><img style=\"border:0px solid;\" alt=\"\" src=\"$png\"></td></tr>";
			else print "<tr><td align=\"center\" title=\"Stregkode kan ikke generes.&#xA;varenr. indeholder ugyldige tegn\"><big>!</big></td></tr>";
#		if ($labelprint && file_exists($png)) print "<tr><td align=\"right\"><a href=\"../lager/labelprint.php?stregkode=".urlencode($tmp)."&src=$png&pris=$salgspris\" target=\"blank\"><img src=\"../ikoner/print.png\" style=\"border: 0px solid;\"></a></td></tr>";
			print "</tbody></table>";
		} else print "</tr>";
	} else print "</tr>";
	print "</tr>";
######### ==> tabel 4
#print "<tr><td colspan=4 width=100%><table border=1 width=100%><tbody>";
	print "<tr><td width=\"33%\" valign=top><table border=\"0\" width=\"100%\"><tbody>"; # Pris enhedstabel ->
	print "<tr><td height=\"20%\"><b>Priser</b></td><td width=\"33%\" align=\"center\">$enhed</td><td width=\"33%\" align=\"center\">$enhed2</td></tr>";
	if ($p_grp_salgspris) $type="readonly=readonly";
	else $type="type=text";
	if (round($salgspris,3)==0.001) $tmp=dkdecimal($salgspris,3); #20161006
	else $tmp=dkdecimal($salgspris,2);
	if (isset($provisionPercentage)) {
        setPercentageField($type, $tmp, $enhed2, $salgspris, $forhold, $incl_moms, $id, $provisionPercentage);
    } else {
        setPercentageField($type, $tmp, $enhed2, $salgspris, $forhold, $incl_moms, $id, null);    
    }
	if ($p_grp_tier_price) $type="readonly=readonly";
	else $type="type=text";
 $tmp=dkdecimal($tier_price,2);
	print "<tr><td>B2B salgspris</td><td><input $type style=text-align:right size=8 name=tier_price value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td>";
	if ($enhed2) {
		$tmp=dkdecimal($tier_price/$forhold,2);
		print "<td><INPUT class=\"inputbox\" READONLY=readonly style=text-align:right size=8 value=\"$tmp\"></td>";
	}
	print "</tr>";
	if ($p_grp_retail_price) $type="readonly=readonly";
	else $type="type=text";
	$tmp=dkdecimal($retail_price,2);
	print "<tr><td>Vejl.pris</td><td><input $type style=text-align:right size=8 name=retail_price value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td>";
	if ($enhed2) {
		$tmp=dkdecimal($retail_price/$forhold,2);
		print "<td><INPUT class=\"inputbox\" READONLY=readonly style=text-align:right size=8 value=\"$tmp\"></td>";
	}
	print "</tr>";

	if ($box8) {
		$r=db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
		$fifo=$r['box6'];
	}
#if ($samlevare!='on')  {
	if ($p_grp_kostpris || $samlevare) $type="readonly=readonly";
	elseif ($fifo && $beholdning != 0) $type="readonly=readonly";
	else $type="type=text";
	$tmp=dkdecimal($kostpris[0],2);	
	print "<tr><td> Kostpris</td><td><input $type style=text-align:right size=8 name=kostpris[0] value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td>";
#} else print "<tr><td> Kostpris</td><td><br></td><td><INPUT class=\"inputbox\" READONLY=readonly style=text-align:right size=8 name=kostpris[0] value=\"$x\"></td>";
#if ($enhed2) {
#	$tmp=dkdecimal($kostpris[0]/$forhold);
#	print "<td><INPUT class=\"inputbox\" READONLY=readonly style=text-align:right size=8 value=\"$tmp\"></td>";
#}
	print "</tr>";

	print "</tbody></table></td>"; #<- Pris enhedstabel
	print "<td width=33% valign=top><table border=0 width=100%><tbody>"; # Tilbudstabel ->
	print "<tr><td colspan=\"2\" height=\"20%\"><b>Tilbud</b></td><td colspan=\"2\"><a href=\"happyhour.php?vare_id=$id\">Avanceret</a></td></tr>";
	$tmp=dkdecimal($special_price,2);
	if ($incl_moms) {
		$tekst="(i/m)";
		$title="Incl. moms";
	} else {
		$tekst="";
		$title="";
	}
	print "<tr><td>Salgspris</td><td title=\"$title\"><input class=\"inputbox\" type=text style=text-align:right size=8 name=special_price value=\"$tmp\" onchange=\"javascript:docChange = true;\">$tekst</td>";
	$tmp=dkdecimal($campaign_cost,2);
	print "<td height=20%>Kostpris</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=campaign_cost value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td></tr>";
	if ($special_price!=0) $tmp=dkdato($special_from_date);
	else $tmp='';
	print "<tr><td height=20%>Dato start</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=special_from_date value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td>";
	print "<td height=20%>Tid start</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=special_from_time value=\"$special_from_time\" onchange=\"javascript:docChange = true;\"></td></tr>";
	if ($special_price!=0) $tmp=dkdato($special_to_date);
	else $tmp='';
	print "<tr><td height=20%>Dato slut</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=special_to_date value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td>";
	print "<td height=20%>Tid slut</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=special_to_time value=\"$special_to_time\" onchange=\"javascript:docChange = true;\"></td></tr>";
	print "</tbody></table></td>";# <- Tilbudstabel 
	print "<td valign=top width=33%><table border=0 width=100%><tbody>"; # Collitabel ->
	print "<tr><td colspan=3 height=20%><b>Colli</b></td></tr>";
	$tmp=dkdecimal($colli,2);
	print "<tr><td width=34%>St&oslash;rrelse</td><td width=33%><input class=\"inputbox\" type=text style=text-align:right size=8 name=colli value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td><td width=33%><br></td></tr>";
	$tmp=dkdecimal($outer_colli,2);
	print "<tr><td height=20%>Yder st&oslash;rrelse</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=outer_colli value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td></tr>";
	$tmp=dkdecimal($open_colli_price,2);
	print "<tr><td height=20%>Anbruds kostpris</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=open_colli_price value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td></tr>";
	$tmp=dkdecimal($outer_colli_price,2);
	print "<tr><td height=20%>Kostpris</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=outer_colli_price value=\"$tmp\" onchange=\"javascript:docChange = true;\"></td></tr>";
	print "</tbody></table></td></tr>";# <- Collitabel 
	print "<tr><td valign=top><table border=0 width=100%><tbody>"; # Enhedstabel ->
	print "<tr><td colspan=3><b>Enheder</b></td></tr>";
	print "<tr><td width=34%>Enhed</td>";
	print "<td width=33%><SELECT class=\"inputbox\" NAME=enhed style=\"width: 7em\">";
	print "<option>$enhed</option>";
	$query = db_select("select betegnelse from enheder order by betegnelse",__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$betegnelse[$x]=$row['betegnelse'];
	}
	$antal_enheder=$x;
	for ($x=0; $x<=$antal_enheder; $x++) {
		if (isset($betegnelse[$x]) && $enhed!=$betegnelse[$x]) {print "<option>$betegnelse[$x]</option>";}
	} 
	print "</SELECT></td><td width=33%><br></td></tr>";
	if ($antal_enheder>1) {
		print "<tr><td>Alternativ enh.</td>";
		print "<td><SELECT style=\"width: 7em\" NAME=enhed2><option>$enhed2</option>";
		for ($x=0; $x<=$antal_enheder; $x++) {
		if (isset($betegnelse[$x]) && $enhed2!=$betegnelse[$x]) {print "<option>$betegnelse[$x]</option>";}	
		}
		print "</SELECT></td></tr>";
		if ($forhold > 0){$x=dkdecimal($forhold,2);}
		else {$x='';}
		if (($enhed)&&($enhed2)) print "<tr><td> $enhed2/$enhed</td><td width=100><input class=\"inputbox\" type=text style=text-align:right size=8 name=forhold value=\"$x\" onchange=\"javascript:docChange = true;\"></td></tr>";
	}
	#print "<td width=100><input class=\"inputbox\" type=text size=2 name=enhed value='$enhed'>&nbsp; Alternativ enhed&nbsp;<input class=\"inputbox\" type=text size=2 name=enhed2 value='$enhed2'></td></tr>";
	if ($enhed && $indhold) {
		print "<tr><td height='20%'>Indhold  ($enhed)</td><td><input class=\"inputbox\" type=text style=text-align:right size=8 name=indhold value=\"".dkdecimal($indhold,2)."\" onchange=\"javascript:docChange = true;\"></td></tr>";
		print "<tr><td height='20%'>Pris pr $enhed</td><td><input class=\"inputbox\" type=text readonly=readonly style=text-align:right size=8 value=\"".dkdecimal($salgspris/$indhold,2)."\" onchange=\"javascript:docChange = true;\"></td></tr>";
	}
	print "<tr><td>Nettovægt</td><td><input class='inputbox' type='text' style='text-align:right' size='8' name='netWeight' value=\"".dkdecimal($netWeight,2)."\" onchange=\"javascript:docChange = true;\"></td></tr>";
	print "<tr><td>Bruttovægt</td><td><input class='inputbox' type='text' style='text-align:right' size='8' name='grossWeight' value=\"".dkdecimal($grossWeight,2)."\" onchange=\"javascript:docChange = true;\"></td></tr>";
	print "</tbody></table></td>";# <- Enhedstabel 
	print "<td valign=top><table border=0 width=100%><tbody>"; # Gruppe tabel ->
	print "<tr><td><b>Grupper</b></td></tr>";
	#varegruppe->
	print "<tr><td width=33%>Varegruppe</td>";
	if (!$gruppe) $gruppe=1;
	$qtxt="select beskrivelse,box10 from grupper where art='VG' and kodenr = '$gruppe'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['box10'] && !$operation) {
		$r2 = db_fetch_array(db_select("select MAX(operation) as operation from varer where lukket !='on'",__FILE__ . " linje " . __LINE__));
		$operation=$r2['operation']+1;
	}
	print "<td width=67%>
	<input type=\"hidden\" NAME=\"gruppe\" value=\"$gruppe\">
	<SELECT class=\"inputbox\" NAME=\"ny_gruppe\" style=\"width: 18em\">";
	print "<option value=\"$gruppe\">$gruppe $r[beskrivelse]</option>";
	if (!$beholdning || !$box9) { # box9 tilfoejet 20090210 saa gruppeskift mellem grupper med box8 er mulig.
		if ($samlevare=='on') $query = db_select("select * from grupper where art='VG' and kodenr!='$gruppe' and box8!='on' order by ".nr_cast('kodenr')."",__FILE__ . " linje " . __LINE__);
		elseif ($beholdning) $query = db_select("select * from grupper where art='VG' and kodenr!='$gruppe' and box8='on' order by ".nr_cast('kodenr')."",__FILE__ . " linje " . __LINE__);# tilfoejet 20090210 
		else $query = db_select("select * from grupper where art='VG' and kodenr!='$gruppe' order by ".nr_cast('kodenr')."",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			print "<option value=\"$row[kodenr]\">$row[kodenr] $row[beskrivelse]</option>";
		}
	}
	print "</SELECT></td></tr>";
#<- Varegruppe
	if (isset($dvrg_nr[1]) && $dvrg_nr[1]) {
		print "<tr><td>Debitorrabatgrp.</td>";
		print "<td><SELECT class=\"inputbox\" NAME=\"dvrg\" style=\"width: 18em\">";
		if (!$dvrg_nr[0]) print "<option value=\"0\"></option>";
		for ($x=1;$x<=count($dvrg_nr);$x++) {
			if ($dvrg_nr[0] && $dvrg_nr[0]==$dvrg_nr[$x]) print "<option value=\"$dvrg_nr[$x]\">$dvrg_nr[$x] $dvrg_navn[$x]</option>";
		}
		for ($x=1;$x<=count($dvrg_nr);$x++) {
			if ($dvrg_nr[0]!=$dvrg_nr[$x]) print "<option value=\"$dvrg_nr[$x]\">$dvrg_nr[$x] $dvrg_navn[$x]</option>";
		}
	}
	print "</SELECT></td></tr>";
# Prisgruppe->
	print "<tr><td>Prisgruppe</td>";
	if (!$prisgruppe) $prisgruppe=0;
	print "<td><SELECT class=\"inputbox\" NAME=prisgruppe value='$prisgruppe' style=\"width: 18em\">";
	$r = db_fetch_array(db_select("select * from grupper where art='VPG' and kodenr='$prisgruppe' order by kodenr",__FILE__ . " linje " . __LINE__));
	print "<option value=\"$prisgruppe\">$r[beskrivelse]</option>";
	$q = db_select("select * from grupper where art='VPG' and kodenr!='$prisgruppe' order by kodenr",__FILE__ . " linje " . __LINE__);
#if ($prisgruppe) print "<option value=\"0\"></option>";
	while ($r = db_fetch_array($q)) {
		print "<option value=\"$r[kodenr]\">$r[kodenr] $r[beskrivelse]</option>";
	}
	if ($prisgruppe) print "<option value=\"0\"></option>";
	print "</SELECT></td></tr>";
#<- Prisgruppe

# tilbudgruppe->
	print "<tr><td>Tilbudsgruppe</td>";
	if (!$tilbudgruppe) $tilbudgruppe=0;
	print "<td><SELECT class=\"inputbox\" NAME=tilbudgruppe value='$tilbudgruppe' style=\"width: 18em\">";
	$r = db_fetch_array(db_select("select * from grupper where art='VTG' and kodenr='$tilbudgruppe' order by kodenr",__FILE__ . " linje " . __LINE__));
	print "<option value=\"$tilbudgruppe\">$r[beskrivelse]</option>";
	$q = db_select("select * from grupper where art='VTG' and kodenr!='$tilbudgruppe' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		print "<option value=\"$r[kodenr]\">$r[kodenr] $r[beskrivelse]</option>";
	}
	if ($tilbudgruppe) print "<option value=\"0\"></option>";
	print "</SELECT></td></tr>";
	#<- tilbudgruppe
	# Rabatgruppe->
	print "<tr><td>Rabatgruppe</td>";
	if (!$rabatgruppe) $rabatruppe=0;
	print "<td><SELECT class=\"inputbox\" NAME=rabatgruppe value='$rabatgruppe' style=\"width: 18em\">";
	$r = db_fetch_array(db_select("select * from grupper where art='VRG' and kodenr='$rabatgruppe' order by kodenr",__FILE__ . " linje " . __LINE__));
	print "<option value=\"$rabatgruppe\">$r[beskrivelse]</option>";
	if ($rabatgruppe) print "<option value=\"0\"></option>";
	$q = db_select("select * from grupper where art='VRG' and kodenr!='$rabatgruppe' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		print "<option value=\"$r[kodenr]\">$r[beskrivelse]</option>";
	}
	print "</SELECT></td></tr>";
	#<- Rabatgruppe
	print "</tbody></table></td>";# <- Gruppe tabel 
	print "<td valign=\"top\"><table border=\"0\" width=\"100%\"><tbody>"; # M-rabat tabel ->
	print "<tr><td><b>M&aelig;ngderabatter</b></td><td align=\"right\"><SELECT class=\"inputbox\" NAME=m_type style=\"width: 4em\">";
	if ($m_type == 'amount') {
		print "<option value=\"amount\">kr</option>";
		print "<option value=\"percent\">%</option>";
	} else {
		print "<option value=\"percent\">%</option>";
		print "<option value=\"amount\">kr</option>";
	}
	print "</SELECT> pr</td>";
	print "<td> stk v. antal</td></tr>";
	for ($x=0;$x<	count($m_antal_array);$x++) {	
		if ($m_antal_array[$x]) {
			if ($incl_moms && $m_type!="percent") $m_rabat_array[$x]*=(100+$incl_moms)/100;
			($rabatgruppe)? $inputtype="readonly=\"readonly\"":$inputtype="type=\"text\"";
			print "<tr><td>Stk.rabat v. antal</td><td><input $inputtype size=\"5\" style=\"text-align:right\" name=\"m_rabat_array[$x]\" value=".dkdecimal($m_rabat_array[$x],3)."></td><td><input $inputtype size=\"5\" style=\"text-align:right\" name=\"m_antal_array[$x]\" value=\"".dkdecimal($m_antal_array[$x],3)."\"></td></tr>";
		}
	}
	#$x++;
	if (!$rabatgruppe) {
		print "<tr><td>Stk.rabat v. antal</td>";
		print "<td><input class='inputbox' type='text' size='5' style='text-align:right' name='m_rabat_array[$x]'";
		print "value='".dkdecimal($m_rabat_array[$x],3)."'></td>";
		print "<td><input class='inputbox' type='text' size='5' style=text-align:right name='m_antal_array[$x]'";
		print "value='".dkdecimal($m_antal_array[$x],3)."'></td></tr>";
	}
	print "</tbody></table></td></tr>";# <- M-rabat tabel 
	print "<tr><td valign=\"top\" height=\"200px\"><table border=\"0\" width=\"100%\"><tbody>"; # Diverse tabel ->
	print "<tr><td colspan=\"2\"><b>Diverse</b></td></tr>";
	if ($box8=='on'){
		$lagernavn[1]='';
		$x=0;
		$qtxt="select beskrivelse,kodenr from grupper where art='LG' order by kodenr";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$x++;
			$lagernavn[$x]=$r['beskrivelse'];
		}
		$qtxt="update batch_kob set lager = '1' where (lager = '0' or lager is NULL) and vare_id='$id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="update batch_salg set lager = '1' where (lager = '0' or lager is NULL) and vare_id='$id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="update lagerstatus set lager = '1' where (lager = '0' or lager is NULL) and vare_id='$id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$lagersum=0;
		for ($x=1;$x<=count($lagernavn);$x++) {	
			$qtxt="select sum(antal) as antal from batch_kob where vare_id = '$id' and lager = '$x'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$b_antal[$x]=$r2['antal'];
			$qtxt="select sum(antal) as antal from batch_salg where vare_id = '$id' and lager = '$x'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$b_antal[$x]-=$r2['antal'];
			$r2=db_fetch_array(db_select("select lok1 from lagerstatus where vare_id = '$id' and lager = '$x'",__FILE__ . " linje " . __LINE__));
			$lagerlok[$x]=$r2['lok1'];
			$qtxt="select sum(beholdning) as beholdning from lagerstatus where vare_id = '$id' and lager = '$x'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$lagerbeh[$x]=$r2['beholdning']*1;
			$lagersum+=$b_antal[$x];
			if ($lagerbeh[$x]!=$b_antal[$x] && !count($vare_varianter)) {
				$l=0;
				$qtxt="select id from lagerstatus where vare_id = '$id' and lager = '$x' order by id";
				$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($r2=db_fetch_array($q2)) {
					if ($l>=1) {
						$qtxt="delete from lagerstatus where id ='$r2[id]' and  vare_id = '$id' and lager = '$x'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
					$l++;
				}	
				$qtxt="update lagerstatus set beholdning='$b_antal[$x]' where vare_id = '$id' and lager = '$x'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$lagerbeh[$x]=$b_antal[$x]; 
			}			
		}
		if (count($lagernavn)) {
			print "<tr><td colspan=\"2\">
			</td><td><b>Lokation</b></td></tr>";
			for ($x=1;$x<=count($lagernavn);$x++) {
				if (!isset($lagerid[$x])) $lagerid[$x]=0; 
				print "<tr><td colspan=\"2\"><input type=\"hidden\" name=\"lagerid[$x]\" value=\"$lagerid[$x]\">$lagernavn[$x]</td>
				<td colspan=\"4\"><input class=\"inputbox\" type=\"text\" size=\"25\" name=\"lagerlok[$x]\" value=\"$lagerlok[$x]\" onchange=\"javascript:docChange = true;\"></td>";
			}
			print "<tr><td colspan=\"6\"><hr></td></tr>";
		} else {
			print "<tr><td colspan=\"2\">Lokation</td><td colspan=\"4\"><input class=\"inputbox\" type=\"text\" size=\"25\" name=\"location\" value=\"$location\" onchange=\"javascript:docChange = true;\"></td>";
		}
	}
	print "<tr><td colspan=\"2\">F&oslash;lgevare</td><td colspan=\"4\"><input class=\"inputbox\" type=text size=25 name=folgevarenr value=\"$folgevarenr\" onchange=\"javascript:docChange = true;\"></td>";
	if ($operation) {
		print "<tr><td colspan=\"2\">Montage</td><td colspan=\"4\">";
		print "<input class=\"inputbox\" type=text style='text-align:right;' size='8' name='montage' value=\"".dkdecimal($montage)."\"";
		print "onchange=\"javascript:docChange = true;\"></td>";
		print "<tr><td colspan=\"2\">Demontage</td><td colspan=\"4\">";
		print "<input class=\"inputbox\" type=text style='text-align:right;' size='8' name='demontage' value=\"".dkdecimal($demontage)."\"";
		print "onchange=\"javascript:docChange = true;\"></td>";
		print "<tr><td colspan=\"2\"> Operation nr:</td><td colspan=\"4\">";
		print "<input class=\"inputbox\" type=text size='5' style='text-align:right;' name='operation' value=\"$operation \">";
	}	
	elseif ($box8=='on'){
		$incomming=$outgoing=0;
		db_modify("update ordrelinjer set leveret=0 where leveret is NULL and vare_id=$id",__FILE__ . " linje " . __LINE__);
		$qtxt = "select (antal-leveret) as incomming from ordrer,ordrelinjer where ordrer.art = 'KO' and (ordrer.status='1' or ordrer.status='2') ";
		$qtxt.= "and ordrelinjer.ordre_id=ordrer.id and vare_id='$id'";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if (count($lagernavn) <= 1) { #20200310-1
			while ($r=db_fetch_array($q))	$incomming+=$r['incomming']; #20200310-2
			$qtxt = "select (antal-leveret) as outgoing from ordrer,ordrelinjer where ordrer.art = 'DO' and (ordrer.status='1' or ordrer.status='2') ";
			$qtxt.= "and ordrelinjer.ordre_id=ordrer.id and vare_id='$id'";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) $outgoing+=$r['outgoing'];
		}
		print "<tr><td>Beholdning</td><td>Min:</td><td width=\"5%\" align='right'><input class=\"inputbox\" type=\"text\" size=\"5\" style=\"text-align:right\" name=\"min_lager\" value=\"$min_lager\"></td>";
		print "<td width=\"5%\">Max:</td><td colspan=\"2\" align='right' ><input class=\"inputbox\" type=\"text\" size=\"5\" style=\"text-align:right\" name=\"max_lager\" value=\"$max_lager\"></td></tr>";
		if (count($lagernavn)) {
			if ($beholdning!=$lagersum) db_modify("update varer set beholdning='$lagersum' where id='$id'",__FILE__ . " linje " . __LINE__);
			for ($x=1;$x<=count($lagernavn);$x++) {
				($x==1)?print "<tr><td>Aktuel</td>":print "<tr><td></td>";
				if (($fifo && !$samlevare) || count($vare_varianter)) {
					print "<td>$lagernavn[$x]</td><td><INPUT class=\"inputbox\" READONLY=\"readonly\" size=\"5\" style=\"text-align:right\" name=\"ny_beholdning\" value=\"$lagerbeh[$x]\" onchange=\"javascript:docChange = true;\">";
				} else {
					print "<td>$lagernavn[$x]</td><td align='right'><input class=\"inputbox\" type=\"text\" size=\"5\" style=\"text-align:right\" name=\"ny_lagerbeh[$x]\" value=\"$lagerbeh[$x]\" onchange=\"javascript:docChange = true;\">";
				}
				print "<input type=\"hidden\" name=\"lagerbeh[$x]\" value=\"$lagerbeh[$x]\"></td></tr>";
			}
		} else {
			print "<tr><td></td>";
			if (($fifo && !$samlevare) || count($vare_varianter)) {
				print "<td>Aktuel</td><td><INPUT class=\"inputbox\" READONLY=\"readonly\" size=\"5\" style=\"text-align:right\" name=\"ny_beholdning\" value=\"$beholdning\" onchange=\"javascript:docChange = true;\">";
			} else {
				print "<td>Aktuel</td><td><input class=\"inputbox\" type=\"text\" size=\"5\" style=\"text-align:right\" name=\"ny_beholdning\" value=\"$beholdning\" onchange=\"javascript:docChange = true;\">";
			}
		}
			print "<input type=\"hidden\" name=\"beholdning\" value=\"$beholdning\"></td></tr>";
			$title='Antal ikke modtaget i godkendt købsordre';
			print "<tr><td>Købs&nbsp;ordre<td><td align='right' title='$title'>".dkdecimal($incomming)."&nbsp;</td></tr>";
			$title='Antal ikke leveret i godkendt salgsordre';
			print "<tr><td>Salgs&nbsp;ordrer<td><td align='right' title='$title'>".dkdecimal($outgoing)."&nbsp;</td></tr>";
			print "</td></tr>";
	}
	($provisionsfri)?$provisionsfri="checked":$provisionsfri=""; 
	print "<tr><td colspan=\"2\">Provisionsfri</td><td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"provisionsfri\" $provisionsfri></td>";
	if ($shopurl) {
#		($publiceret)?$publiceret="checked":$publiceret=""; 
#		print "<tr><td colspan=\"2\">Publiceret</td><td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"publiceret\" $publiceret></td>";
		if ($shop_id) {
			print "<tr><td colspan=\"2\">
			<input type=\"hidden\" name=\"publiceret\" value=\"$publiceret\">
			Shop ID (klik for at fjerne)</td><td align=\"center\"><a href=\"slet_shopbinding.php?id=$id\">$shop_id</a></td>";
		} else {
			($publiceret)?$publiceret="checked":$publiceret=""; 
			print "<tr><td colspan=\"2\">Publiceret</td><td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"publiceret\" $publiceret></td>";
		}
	}	
	print "</tbody></table></td>";#  <- Diverse tabel
#################### KATEGORIER ###########################
	print "<td valign=\"top\" height=\"200px\">";
	print "<div class=\"vindue\">";
	print "<table border=0 width=100%><tbody>"; # Kategori tabel ->
	$x=0;
	$kat_niveauer=0;
	$q=db_select("select id,box1,box2 from grupper where art='V_CAT' order by box1",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		$kat_id[$x]=$r['id'];
		$kat_beskrivelse[$x]=$r['box1'];
		($r['box2'])?$kat_masters[$x]=explode(chr(9),$r['box2']):$kat_masters[$x]=NULL;
		$kat_niveau[$x]=count($kat_masters[$x]);
		if ($kat_niveau[$x]>$kat_niveauer) $kat_niveauer=$kat_niveau[$x];
		$tmp=count($kat_masters[$x])-1;
		$kat_master[$x]=$kat_masters[$x][$tmp];
	}
	$kat_antal=$x;
	for ($x=1;$x<=$kat_antal;$x++) {
		if ($kat_master[$x] && !in_array($kat_master[$x],$kat_id)) {
			db_modify("delete from grupper where id = '$kat_id[$x]'",__FILE__ . " linje " . __LINE__);
		}
	}

	print "<tr><td colspan=\"4\" valign=\"top\"><b>".findtekst(388,$sprog_id)."<!--tekst 388--></b></td></tr>\n";
	$x=0;

$a=1;$b=0;$e=0;$f=0;
$used_id=array();
$brugt=array();
$pre=array();
while ($a <= $kat_antal) {
		$niveau=0;
#cho "A $a ID $kat_id[$a] Master $kat_master[$a]<br>";
	if (!$kat_master[$a] && !in_array($kat_id[$a],$used_id)) {
		$checked=NULL;
		for ($y=0;$y<$kategori_antal;$y++) {
			if ($kat_id[$a]==$kategori[$y]) $checked="checked";
		}
		print "<tr><td title=\"ID=$kat_id[$a]\">$kat_beskrivelse[$a]</td>\n";
		print "<td title=\"".findtekst(395,$sprog_id)."\" align=\"center\"><!--tekst 395--><input type=\"checkbox\" name=\"kat_valg[$a]\" $checked></td>\n";
		print "<td title=\"".findtekst(396,$sprog_id)."\"><!--tekst 396--><a href=\"varekort.php?id=$id&rename_category=$kat_id[$a]\" onclick=\"return confirm('Vil du omd&oslash;be denne kategori?')\"><img src=../ikoner/rename.png border=0></a></td>\n";
		if (in_array($kat_id[$a],$kat_master)) print "<td></td>"; 
		else print "<td title=\"".findtekst(397,$sprog_id)."\"><!--tekst 396--><a href=\"varekort.php?id=$id&delete_category=$kat_id[$a]\" onclick=\"return confirm('Vil du slette denne katagori?')\"><img src=../ikoner/delete.png border=0></a></td>\n";
		print "</tr>\n";
		print "<input type=\"hidden\" name=\"kat_id[$a]\" value=\"$kat_id[$a]\">\n";

#		print "$kat_beskrivelse[$a]<br>";
		$used_id[$b]=$kat_id[$a];
		$b++;
	}
	$c=$a; 
	$q=0;
	for ($d=1;$d<=$kat_antal;$d++) {
	$q++;
# Master_id skal være = master  & id må ikke være brugt før og master skal være sat.  
#cho "$q $kat_master[$d]()==$kat_id[$c]($kat_beskrivelse[$c]) $kat_beskrivelse[$d]<br>";
	if ($kat_master[$d]==$kat_id[$c] && !in_array($kat_id[$d],$used_id) && in_array($kat_master[$d],$used_id)) {
#cho "her $kat_beskrivelse[$d]<br>";
		$checked=NULL;
		for ($y=0;$y<$kategori_antal;$y++) {
			if ($kat_id[$d]==$kategori[$y]) $checked="checked";
		}
			print "<tr><td title=\"ID=$kat_id[$d]\">";
			for ($e=0;$e<$kat_niveau[$d];$e++) print "-&nbsp;";
#			print "$a | $c | $d | $kat_id[$d] | $kat_beskrivelse[$d] | $kat_master[$d]</td>\n";
			print "$kat_beskrivelse[$d]</td>\n";
			print "<td title=\"".findtekst(395,$sprog_id)."\" align=\"center\"><!--tekst 395--><input type=\"checkbox\" name=\"kat_valg[$d]\" $checked></td>\n";
			print "<td title=\"".findtekst(396,$sprog_id)."\"><!--tekst 396--><a href=\"varekort.php?id=$id&rename_category=$kat_id[$d]\" onclick=\"return confirm('Vil du omd&oslash;be denne kategori?')\"><img src=../ikoner/rename.png border=0></a></td>\n";
			if (in_array($kat_id[$d],$kat_master)) print "<td></td>";
			else print "<td title=\"".findtekst(397,$sprog_id)."\"><!--tekst 396--><a href=\"varekort.php?id=$id&delete_category=$kat_id[$d]\" onclick=\"return confirm('Vil du slette denne katagori?')\"><img src=../ikoner/delete.png border=0></a></td>\n";
			print "</tr>\n";
			print "<input type=\"hidden\" name=\"kat_id[$d]\" value=\"$kat_id[$d]\">\n";
		
			$used_id[$b]=$kat_id[$d];
			$nivau++;
			$pre[$niveau]=$c;
			$b++;
			$c=$d;
			$d=1;
#cho "$a | $c | $d | $kat_id[$c] | $kat_beskrivelse[$c] | $kat_master[$c]</br>\n";
		}
#cho "$d==$kat_antal && $c!=$a<br>";
		if ($d==$kat_antal && $c!=$a) {
#cho "skifter A $a B $c D $d<br>";
			$c=$a;
			if ($niveau && $pre[$niveau]) $c=$pre[$niveau];
			$d=1;
			$niveau--;
#cho "-> A $a B $c D $d<br>";
		}
#		if ($q>10000) {
#			break 1;
#		}
	}

	$a++;
}


/*
	$used_id=array();
	if (!$rename_category) {
		for ($x=1;$x<=$kat_antal;$x++) {
			if (!$kat_masters[$x]) { 
				kategorier($x,$a,$id,$kat_niveau[$x],$kategori_antal,$kat_id[$x],$kategori,$kat_beskrivelse[$x],$kat_masters[$x]);
				$z=($x);
				while ($kat_id[$z] || $kat_masters[$z]){
					$z++;
					for ($y=1;$y<=$kat_antal;$y++) {
						if (!isset($kat_masters[$y])) $kat_masters[$y]=array(); 
						if (in_array($kat_id[$x],$kat_masters[$y])) {
							if (!in_array($kat_id[$y],$used_id)) {
								$z=count($kat_masters[$y])-1;
								kategorier($y,$a,$id,$kat_niveau[$y],$kategori_antal,$kat_id[$y],$kategori,$kat_beskrivelse[$y],$kat_masters[$y][$z]);
								$used_id[count($used_id)]=$kat_id[$y];	
							}
						}
					}
				} 
			}
		}
	}
*/
	if (findtekst(390,$sprog_id)=="For at oprette en ny kategori skrives navnet p&aring; kategorien her") {
		db_modify("delete from tekster where tekst_id = '390'",__FILE__ . " linje " . __LINE__);
	}

	if ($rename_category){
		for ($x=1;$x<=$kat_antal;$x++) {
			if ($rename_category==$kat_id[$x]) $ny_kategori=$kat_beskrivelse[$x];
		}
		$tekst=findtekst(388,$sprog_id);
#		$tekst=str_replace('$ny_kategori',$ny_kategori,$tekst);
		print "<tr><td colspan=\"4\">Ret \"$ny_kategori\" til:</td></tr>\n";
		print "<input type=\"hidden\" name=\"rename_category\" value=\"$rename_category\">\n";
	#	print "<tr><td colspan=\"4\" title=\"".findtekst(390,$sprog_id)."\"><input type=\"text\" size=\"25\" name=\"ny_kategori\" value=\"$ny_kategori\"></td></tr>\n";
	} else $ny_kategori=''; 
	print "<tr><td colspan=\"4\" title=\"".findtekst(390,$sprog_id)."\"><!--tekst 390--><input class=\"inputbox\" type=\"text\" size=\"25\" name=\"ny_kategori\" placeholder=\"".findtekst(343,$sprog_id)."\" value=\"$ny_kategori\"></td></tr>\n";
	print "<input type=\"hidden\" name=\"kat_antal\" value=\"$kat_antal\">\n";
	print "</tbody></table></div></td>";#  <- Kategori tabel

####################################### VARIANTER #############################################
	print "<td valign=\"top\" height=\"200px\"><div class=\"vindue\"><table border=\"0\" width=\"100%\"><tbody>"; # Variant tabel ->
	print "<tr><td valign=\"top\"><b>".findtekst(472,$sprog_id)."<!--tekst 472--></b></td></tr>\n";
	for ($x=1;$x<=$variant_antal;$x++) {
		(in_array($variantVarerVariantId[$x],$vare_varianter))?$checked[$x]='checked':$checked[$x]='';
		if ($db=='bizsys_244' && ($id=='4757' || $id=='4679')) $checked[$x]='checked';
		print "<input type=\"hidden\" name=\"varianter_id[$x]\" value=\"$varianter_id[$x]\">";
		$title=findtekst(487,$sprog_id);
		print "<tr title='$title'><!--tekst 487--><td>$varianter_beskrivelse[$x]</td><td>";
		if ($beholdning) {# 20180118
			print "<input type=\"hidden\" name=\"vare_varianter[$x]\" value=\"$checked[$x]\">";
			$readonly='disabled';
			$title='Kan ikke ændres når der ar varer på lager';
		} else $readonly=NULL;
		print "<input title='$title' $readonly class=\"inputbox\" type=\"checkbox\" name=\"vare_varianter[$x]\" $checked[$x]>";
		print "</td></tr>\n";  
	}

	print "<tr><td colspan=\"2\"><table border=0><tbody><tr>";
	for ($x=0;$x<count($vare_varianter);$x++) {
		$r=db_fetch_array(db_select("select beskrivelse from varianter where id = '$vare_varianter[$x]'",__FILE__ . " linje " . __LINE__));
		print "<td align=\"center\">$r[beskrivelse]</td>";
	}
	print "</tr><tr>";
	for ($x=0;$x<count($vare_varianter);$x++) {
		print "<td><select name=var_type[$x]>";
		$q=db_select("select * from variant_typer where variant_id = '$vare_varianter[$x]' order by beskrivelse",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			print "<option value='$r[id]'>$r[beskrivelse]</option>";
		}
		print "</select></td>";
	}
	print "</tr></tbody></table></td></tr>";
#print "<tr><td>Antal</td><td><input type=\"text\" style=\"width:50px\" name=\"var_type_beh\"></td></tr>";
	if (count($vare_varianter)) print "<tr><td>Stregkode</td><td><input type=\"text\" style=\"width:250px\" name=\"var_type_stregk\"></td></tr>";
	print "</tbody></table></div></td></tr>";#  <- Variant tabel
####################################### NOTER/BESKRIVELSE #############################################
	print "<tr><td valign=top colspan=3><table border=0 width=100%><tbody>"; # Notetabel ->
	print "<tr><td valign=top colspan=2>Bem&aelig;rkning</td><td colspan=\"4\"><textarea name=\"notes\" rows=\"3\" cols=\"60\">$notes</textarea></td></tr>";
	print "<tr><td colspan=6><table width=100% border=0><tbody><tr>";
	if ($serienr == 'on') print "<td> Serienr.&nbsp;<input class=\"inputbox\" type=checkbox name=serienr checked></td>";
	elseif  ($box8 == 'on') print "<td> Serienr&nbsp;<input class=\"inputbox\" type=checkbox name=serienr></td>";
#	if (($styklister) && (!$lev_id[1])) { # /* Udeladt intil test af vareflow er afsluttet (2006-03-03)
		$r=db_fetch_array(db_select("select count(id) as lev_antal from vare_lev where vare_id='$id'",__FILE__ . " linje " . __LINE__));
		$lev_antal=$r['lev_antal']*1;
		($samlevare)?$checked='checked':$checked=''; 
		($beholdning || $lev_antal || $varianter)?$readonly='disabled':$readonly='';
		$title="Afmærk her hvis varen er en samlevare
			Feltet er låst, hvis beholdningen er forskellig fra 0, der er varianter på varen eller varen indgår i en uafsluttet ordre";
 		print "<td width=17%><span title=\"$title\">Samlevare</span></td><td><input $readonly title=\"$title\" class=\"inputbox\" type=checkbox name=\"samlevare\" $checked></td>";
 		if ($readonly) print "<input type=hidden name=\"samlevare\" value=\"$samlevare\">";
		#	if ($delvare == 'on') print "<td width=25%> Delvare&nbsp;<input class=\"inputbox\" type=checkbox name=delvare checked></td>";
		#	else {print "<td width=25%> Delvare&nbsp;<input class=\"inputbox\" type=checkbox name=delvare></td>";}
#	}
	if ($lukket==0) print "<td width=17%>Udg&aring;et</td><td><input class=\"inputbox\" type=checkbox name=lukket></td>";
	else print "<td width=17%>Udg&aring;et</td><td><input class=\"inputbox\" type=checkbox name=lukket checked></td>";
	print "</tbody></table></td></tr>";# <- Note tabel 
	print "</td></tr></tbody></table></td></tr>";
	# <== tabel 4
}

if (!isset ($samlevare)) $samlevare = NULL;

if ($samlevare!='on') {
	if ($id) {
		$x=0;
		$vare_lev_id=array();
		$query = db_select("select * from vare_lev where vare_id='$id' order by posnr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$x++;
			$vare_lev_id[$x]=$row['id'];
			$lev_id[$x]=$row['lev_id'];
			$lev_varenr[$x]=$row['lev_varenr'];
			$kostpris[$x]=$row['kostpris'];
			if ($x==1 && !$lev_varenr[$x] && !$kostpris[$x]) {
				$lev_varenr[$x]=$varenr;
				$kostpris[$x]=$kostpris[0]*1;
				db_modify("update vare_lev set lev_varenr='$lev_varenr[$x]',kostpris='$kostpris[$x]' where id='$row[id]'",__FILE__ . " linje " . __LINE__);
			}
		}
		$lev_ant=$x;
		if ($lev_ant) {
			print "<input type=hidden name=lev_antal value=$lev_ant>";
			print "<tr><td colspan=3><table border=0 width=100%><tbody>";
			print "<tr><td> Pos.</td><td> Leverand&oslash;r</td><td> Varenr.</td><td> Kostpris ($enhed)</td>";
			if (($enhed2)&&($forhold>0)) {print "<td> Kostpris ($enhed2)</td>";}
			print "</tr>";
			for ($x=1; $x<=$lev_ant; $x++) {
				$query = db_select("select kontonr, firmanavn from adresser where id='$lev_id[$x]'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$y=dkdecimal($kostpris[$x],2);
				print "<td><span title='Pos = minus sletter leverand&oslash;ren';><input class=\"inputbox\" type=text size=1 name=lev_pos[$x] value=$x onchange=\"javascript:docChange = true;\"></span></td><td> $row[kontonr]:".$row['firmanavn']."</td><td><input class=\"inputbox\" type=text style=text-align:right size=9 name=lev_varenr[$x] value=\"$lev_varenr[$x]\" onchange=\"javascript:docChange = true;\"></td><td style=text-align:right><input class=\"inputbox\" type=text style=text-align:right size=9 name=kostpris[$x] value=\"$y\" onchange=\"javascript:docChange = true;\"></td>";
				if (($enhed2)&&($forhold>0)) {
					$y=dkdecimal($kostpris[$x]/$forhold,2);
					print "<td><input class=\"inputbox\" type=text style=text-align:right size=9 name=kostpris2[$x] value=\"$y\" onchange=\"javascript:docChange = true;\"></td>";
				}
				print "</td></tr>";
				print "<input class=\"inputbox\" type=hidden name=vare_lev_id[$x] value=$vare_lev_id[$x]>";
			}
			print "</tbody></table>";
		}
	}
}
print "</tr></tbody></table></td></tr>";
print "<tr><td colspan=5 width=100%><table border=0 width=100%><tbody>";

$be_af_sum=0;
if ($samlevare=='on') {
	$query = db_select("select * from styklister where indgaar_i=$id order by posnr",__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$query2 = db_select("select * from varer where id = $row[vare_id]",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$be_af_vnr[$x]=$row2['varenr'];
		$be_af_beskrivelse[$x]=$row2['beskrivelse'];
		$be_af_enhed[$x]=$row2['enhed'];
		$be_af_ant[$x]=$row['antal'];
		$be_af_id[$x]=$row2['id'];
		$be_af_kostpris[$x]=$row2['kostpris'];
		$be_af_sum+=$be_af_kostpris[$x]*$be_af_ant[$x];
		print "<input type=hidden name=be_af_id[$x] value='$row[id]'>";
		print "<input type=hidden name=be_af_vare_id[$x] value='$row[vare_id]'>";
		print "<input type=hidden name=be_af_vnr[$x] value='$be_af_vnr[$x]'>";
		print "<input type=hidden name=be_af_beskrivelse[$x] value='$be_af_beskrivelse[$x]'>";
	}
	$ant_be_af=$x;
}

if (!isset ($delvare)) $delvare = NULL;

if ($delvare=='on') {
	$qtxt="select * from styklister where vare_id=$id order by posnr";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($row = db_fetch_array($q)) {
		$query2 = db_select("select * from varer where id = $row[indgaar_i]",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		if ($row2['id']==$id) { #20131007
			db_modify("delete from styklister where id='$row[id]'",__FILE__ . " linje " . __LINE__);
			$txt="Cirkulær reference konstateret, varenr.: $row2[varenr] fjernet fra stykliste";
			print "<BODY onLoad=\"javascript:alert('$txt')\">";
		} else {
			$x++;
			$indg_i_vnr[$x]=$row2['varenr'];
			$indg_i_beskrivelse[$x]=$row2['beskrivelse'];
			$indg_i_enhed[$x]=$row2['enhed'];
			$indg_i_ant[$x]=$row['antal'];
			$indg_i_id[$x]=$row2['id'];
			print "<input type=hidden name=indg_i_id[$x] value='$row[id]'>";
		}
	}
	if ($x==0) {
		print "<input type=hidden name=delvare value=''>";
		$delvare='';
	}
	$ant_indg_i=$x;
}
if ($samlevare=='on') {
	$be_af_pos[0]=0;
	($beholdning)?$readonly='readonly':$readonly='';
	print "<tr><td valign=top><table width=20%><tbody><tr><td> <a href=stykliste.php?id=$id>Stykliste</a></td></tr>";
#	print "<tr><td> <a href=fuld_stykliste.php?id=$id>Komplet</a></td></tr>";
	print "</tbody></table></td>";
	print "<td></td><td><table border=0 width=80%><tbody>";
	print "<tr><td> Pos.</td><td width=80> V.nr.</td><td width=300> Beskrivelse</td><td> Antal</td></tr>";
	for ($x=1; $x<=$ant_be_af; $x++){
		$dkantal=dkdecimal($be_af_ant[$x],2);
		if (substr($dkantal,-1)=='0') $dkantal=substr($dkantal,0,-1);
		if (substr($dkantal,-1)=='0') $dkantal=substr($dkantal,0,-2);
		print "<tr>";
		print "<td><input class=\"inputbox\" type=\"text\" size=2 style=\"text-align:right\" name=\"be_af_pos[$x]\" value=\"$x\" $readonly></td>";
		print "<td>$be_af_vnr[$x]</td><td>$be_af_beskrivelse[$x]</td>";
		print "<td><input class=\"inputbox\" type=\"text\" size=\"2\" style=\"text-align:right\" name=\"be_af_ant[$x]\" value=\"$dkantal\" $readonly>&nbsp;$be_af_enhed[$x]</td>";
		print "<td align='right'>".dkdecimal($be_af_kostpris[$x]*$be_af_ant[$x],2)."</td>";
		print "</tr>";
	}
	print print "<tr><td colspan='5' align='right'>".dkdecimal($be_af_sum,2)."</td><tr>";
	$be_af_pos[0]=$ant_be_af+1;
#	print 	"<tr><td><input class=\"inputbox\" type=text size=2 style=text-align:right name=be_af_pos[0] value=$be_af_pos[0]></td>";
#	print 	"<td><input class=\"inputbox\" type=text size=8 name=be_af_vnr[0] title='Indtast varenummer som skal tilf&oslash;jes styklisten'></td>";
#	print 	"<td><input class=\"inputbox\" type=text size=60 name=be_af_beskrivelse[0] title='Indtast varebsekrivelse p&aring; vare som skal tilf&oslash;jes styklisten'></td></tr>";
#	print "<input class=\"inputbox\" type=text size=2 style=text-align:right name=be_af_ant[0]</td></tr>";
/*
#	print "<tr><td><input class=\"inputbox\" type=text size=2 name=be_af_pos[0]] value=$x></td><td colspan=2><SELECT class=\"inputbox\" NAME=be_af_ant[0]>";
#	print "<option> $row[varenr]&nbsp;".substr($row[beskrivelse],0,60)."</option>";
#	$query = db_select("select * from varer where id != $id order by varenr",__FILE__ . " linje " . __LINE__);
#	while ($row = db_fetch_array($query)){
#		if ((!in_array($row['id'], $be_af_id))&&(!in_array($row['id'], $indg_i_id))){print "<option>$row[varenr] : ".substr($row['beskrivelse'],0,60)."</option>";}
#	}
#	print "</SELECT></td>";
*/
	print "</tr></tbody></table></td></tr>";
	print "<input type=hidden name=ant_be_af value='$ant_be_af'>";
}

if ($delvare=='on') {
	if ($vis_samlevarer) {
		print "<tr><td valign=top width=10%><span title='Klik her for at lukke oversigten'><a href=varekort.php?opener=$opener&id=$id&returside=$returside>Indg&aring;r i</a></td><td></td><td><table width=80% border=0><tbody>";
		print "<tr><td> Pos.</td><td width=80> V.nr.</td><td width=300> Beskrivelse</td><td> Antal</td></tr>";
		for ($x=1; $x<=$ant_indg_i; $x++) {
			print "<tr><td><input class=\"inputbox\" type=text size=2 name=indg_i_ant[$x] value=$x></td><td>$indg_i_vnr[$x]</td><td>$indg_i_beskrivelse[$x]</td><td align=\"right\">$indg_i_ant[$x]</td></tr>";
		}
		print "<input type=\"hidden\" name=\"vis_samlevarer\" value=\"on\">";
	} else { 
		print "<tr><td colspan=3><table width=100% border='0' cellspacing='1'><tbody>";
		print "<tr><td width=100% align=center><a href=varekort.php?opener=$opener&id=$id&returside=$returside&vis_samlevarer=on>Denne vare indg&aring;r i andre varer - Klik for oversigt</a></td></tr>";
	}
	
#	print "<tr><td><input class=\"inputbox\" type=text size=2 name=indg_i_ant[$x] value=$x></td><td colspan=2><SELECT class=\"inputbox\" NAME=indg_i_ant[0]>";
#	print "<option>$row[varenr]&nbsp;".substr($row['beskrivelse'],0,60)."</option>";
#	$query = db_select("select * from varer where id != $id order by varenr",__FILE__ . " linje " . __LINE__);
#	while ($row = db_fetch_array($query)) {
#		if ((!in_array($row['id'], $be_af_id))&&(!in_array($row['id'], $indg_i_id))){print "<option>$row[varenr] : ".substr($row['beskrivelse'],0,60)."</option>";}
#	}	
#	print "</SELECT></td>";
	print "</tr></tbody></table></td></tr>";
}

if (!isset ($kostpris)) $kostpris = NULL;

if ($menu =='T') {
	$hrlinje = "<hr width=75%>";
} else {
	$hrlinje = "<hr width=100%>";
}
print "<tr><td colspan=4><center>$hrlinje</td></tr>";
print "<tr><td colspan=4 align=center><table width=100%><tbody>";

print "<input type=hidden name=ant_indg_i value='$ant_indg_i'>";
print "<input type=hidden name=returside value='$returside'>";
print "<input type=hidden name=fokus value='$fokus'>";
print "<input type=hidden name=ordre_id value='$ordre_id'>";
print "<input type=hidden name=delvare value='$delvare'>";
print "<input type=hidden name=gl_kostpris value='$kostpris[0]'>";

print "<tr><td align = center><input class='button green medium' style='width:150px;' type=submit accesskey=\"g\" value=\"Gem\" name=\"submit\" onclick=\"javascript:docChange = false;\" $noEdit></td>";

($beholdning || $noEdit)?$disabled='disabled':$disabled='';
if ( $varenr && $samlevare=='on') {
	print "<td align = center><input class='button blue medium' style='width:150px;' type=submit title='Inds&aelig;t varer i stykliste' accesskey=\"l\" value=\"Vareopslag\" name=\"submit\" onclick=\"javascript:docChange = false;\" $disabled></td>";
} elseif ($varenr) {
		print "<td align = center><input class='button blue medium' style='width:150px;' type=submit accesskey=\"k\" value=\"Kopier\" name=\"submit\"></td>";
		print "<td align = center><input class='button blue medium' style='width:150px;' type=submit accesskey=\"l\" value=\"Leverand&oslash;ropslag\" name=\"submit\" onclick=\"javascript:docChange = false;\" $noEdit></td>";
}
if ($id) {
	$q = db_select("select distinct(id) from ordrelinjer where vare_id = $id",__FILE__ . " linje " . __LINE__);
	if (!db_fetch_array($q) && $lev_ant < 1 && $ant_be_af < 1 && $ant_indg_i < 1) {
		print "<td align=center><input style='width:150px;' class='button red medium' type=submit value=\"Slet\" name=\"submit\" onclick=\"javascript:docChange = false;\" $noEdit></td>";
	}
}

print "</tr></tbody></table></td></tr>";
print "</tr></tbody></table></td></tr>";

function prisopdat($id, $diff) {
	$x=0;
	$y=0;
#cho "select * from styklister where vare_id =$id<br>";
	$q1 = db_select("select * from styklister where vare_id =$id",__FILE__ . " linje " . __LINE__);
	while ($r1 = db_fetch_array($q1)) {
		$x++;
		$indgaar_i[$x]=$r1['indgaar_i'];
		$belob=$r1['antal']*$diff;
#cho "update varer set kostpris=kostpris+$belob where id=$indgaar_i[$x]<br>";
		db_modify("update varer set kostpris=kostpris+$belob where id=$indgaar_i[$x]",__FILE__ . " linje " . __LINE__);
	}
	$y=$x;
	for ($y=1; $y<=$x; $y++) {
#cho "select * from styklister where vare_id=$indgaar_i[$y]<br>";
		$q1 = db_select("select * from styklister where vare_id=$indgaar_i[$y]",__FILE__ . " linje " . __LINE__);
		while ($r1 = db_fetch_array($q1)) {
			if ($row['indgaar_i']!=$id) {
				$x++;
				$vare_id[$x]=$r1['id'];
				$indgaar_i[$x]=$r1['indgaar_i'];
				$antal[$x]=$r1['antal'];
#cho "update varer set kostpris=kostpris+$diff*$antal[$x] where id=$vare_id[$x]<br>";
				db_modify("update varer set kostpris=kostpris+$diff*$antal[$x] where id=$vare_id[$x]",__FILE__ . " linje " . __LINE__);
			} else {
				$r2 = db_fetch_array(db_select("select varenr from varer where id=$vare_id[$y]",__FILE__ . " linje " . __LINE__));
				db_modify("delete from styklister where id=$r1[id]",__FILE__ . " linje " . __LINE__);
				print "<BODY onLoad=\"javascript:alert('Cirkul&aelig;r reference registreret varenr.: $r2[varenr] fjernet fra styklisten')\">";
			}
		}
	}
}


function prisopdat_xx($id) {

	$x=0;
	$query = db_select("select id from varer where delvare = 'on' and samlevare != 'on'",__FILE__ . " linje " . __LINE__); #finder varer paa laveste nevieu
	while ($row = db_fetch_array($query)) {
		$x++;
		$vare_id[$x]=$row['id'];
	}	
	$vareantal=$x;
		
	$x=0;
	$query = db_select("select * from styklister",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$s_id[$x]=$row['id'];
		$s_vare_id[$x]=$row['vare_id'];
		$s_antal[$x]=$row['antal'];
		$s_indgaar_i[$x]=$row['indgaar_i'];
	}
	$antal_s=$x;
	$kontrol=array();
	$x=0;
	for ($a=1; $a<=$vareantal; $a++) {
		$kostpris=0;
		for ($b=1; $b<=$antal_s; $b++) {
			if ($vare_id[$a]==$s_indgaar_i[$b]) {
				 $query = db_select("select kostpris from vare_lev where vare_id = $s_vare_id[$b] order by posnr",__FILE__ . " linje " . __LINE__); #finder varer 1 nivaau lavere
				 if ($row = db_fetch_array($query)){$kostpris=$kostpris+$row['kostpris']*$s_antal[$b];}
				 else {
					 $query = db_select("select kostpris from varer where id = $s_vare_id[$b]",__FILE__ . " linje " . __LINE__); #finder varer 1 nivaau lavere
					 $row = db_fetch_array($query);
					 $kostpris=$kostpris+$row['kostpris']*$s_antal[$b];
				 }
			}
			if ($vare_id[$a]==$s_vare_id[$b]) {
				 $vareantal++;
				 $vare_id[$vareantal]=$s_indgaar_i[$b];
			}
		}
		if ($kostpris>0) {
		db_modify("update varer set kostpris='$kostpris' where id=$vare_id[$a]",__FILE__ . " linje " . __LINE__);
		}
	}
	for ($a=1; $a<=$vareantal; $a++)	{
	}
}

function kategorier($x,$id,$kat_niveau,$kategori_antal,$kat_id,$kategori,$kat_beskrivelse,$kat_master) {
global $sprog_id;
	$checked="";
	for ($y=0;$y<$kategori_antal;$y++) {
		if ($kat_id==$kategori[$y]) $checked="checked";
	}
	print "<tr><td title=\"ID=$kat_id\">";
	for ($i=0;$i<$kat_niveau;$i++) {
		print "-&nbsp;";
	}
#	if ($kat_niveau) print " ";
	print "$kat_master -> $kat_beskrivelse";
#			if ($show_subcat!=$kat_id[$x]) print "&nbsp;<a href=\"varekort.php?id=$id&show_subcat=$kat_id[$x]\">&nbsp;-></a>";
	print "</td>\n";
	$tekst=findtekst(395,$sprog_id);
#				$tekst=str_replace('$firmanavn',$firmanavn,$tekst);	
	print "<td title=\"$tekst\" align=\"center\"><!--tekst 395--><input type=\"checkbox\" name=\"kat_valg[$x]\" $checked></td>\n";
	print "<td title=\"".findtekst(396,$sprog_id)."\"><!--tekst 396--><a href=\"varekort.php?id=$id&	=$kat_id\" onclick=\"return confirm('Vil du omd&oslash;be denne kategori?')\"><img src=../ikoner/rename.png border=0></a></td>\n";
	print "<td title=\"".findtekst(397,$sprog_id)."\"><!--tekst 396--><a href=\"varekort.php?id=$id&delete_category=$kat_id\" onclick=\"return confirm('Vil du slette denne katagori?')\"><img src=../ikoner/delete.png border=0></a></td>\n";
	print "</tr>\n";
	print "<input type=\"hidden\" name=\"kat_id[$x]\" value=\"$kat_id\">\n";
} # endfunc kategorier

function samletjek($id){
	$x=0;
	$indgaar_i=array();
	$vare_id=array();
	$query = db_select("select vare_id, indgaar_i from styklister where vare_id != $id",__FILE__ . " linje " . __LINE__); 
	while ($row = db_fetch_array($query)) {
		$x++;
		$indgaar_i[$x]=$row[indgaar_i];
		$vare_id[$x]=$row[vare_id];
	}
	$query = db_select("select id from varer where id != $id and samlevare='on'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if (!in_array($row[id], $indgaar_i)) {db_modify("update varer set samlevare = '' where id=$row[id]",__FILE__ . " linje " . __LINE__);}
		else {db_modify("delete from vare_lev where vare_id=$row[id]",__FILE__ . " linje " . __LINE__);}
	}
	$query = db_select("select id from varer where id != $id and delvare='on'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if (!in_array($row[id], $vare_id)) {db_modify("update varer set delvare = '' where id=$row[id]",__FILE__ . " linje " . __LINE__);}
	}
}

function cirkeltjek($vare_id) 
{
	global $id;
	$x=0;
	$fejl=0;
	$query = db_select("select styklister.vare_id as vare_id, varer.samlevare as samlevare from styklister, varer where indgaar_i=$vare_id and varer.id=$vare_id",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		if ($id==$row[vare_id]) {
			print "<BODY onLoad=\"javascript:alert('Cirkulær reference registreret')\">";
			$x=0;
			$fejl=1;
			break 1;
		} elseif (($row['samlevare']=='on') && ($fejl!=1)) {
			$x++;
			$s_vare_id[$x]=$row['vare_id'];
		}
	}
	for ($a=1; $a<=$x; $a++)	{
		$query = db_select("select styklister.vare_id as vare_id, varer.samlevare as samlevare from styklister, varer where indgaar_i=$s_vare_id[$a] and varer.id=$s_vare_id[$a]",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if ($id==$row[vare_id]) {
				print "<BODY onLoad=\"javascript:alert('Cirkulær reference registreret')\">";
				$a=$x;
				$fejl=1;
				break 1;
			} elseif (($row['samlevare']=='on') && ($fejl!=1)) {
				$x++;
				$s_vare_id[$x]=$row[vare_id];
			}
		}
	}
	if ($fejl>0) return $fejl;
}

######################################################################################################################################
function kontoopslag($sort, $fokus, $id)
{
	global $bgcolor2;
	global $top_bund;
	global $returside;
	global $ordre_id;
	global $fokus;
	global $vare_lev_id;
	global $opener;
	global $menu;
	
	if ($menu=='T') {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"headerSmallWindow\"> 
		<div class=\"headerLittlebtnLft\"><a class=\"button red small right\" href=$returside accesskey=L>Luk</a></div>
		<span class=\"headerLittleTxt\">Varekort</span>";     
		print "<div class=\"headerLittlebtnRght\"></div>";       
		print "</div><!-- end of header -->
			<div class=\"maincontentLargeHolder\">\n";
	print"<table class='dataTable2' cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr><td colspan=8>";
	print "		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
} else {
	print "<table width='100%'><tbody>";
	print "			<td width=\"10%\" $top_bund><a href=varekort.php?opener=$opener&returside=$returside&ordre_id=$ordre_id&vare_id=$id&id=$id&fokus=$fokus accesskey=L>Luk</a></td>";
	print "			<td width=\"80%\" $top_bund align=\"center\">Varekort</td>";
	print "<td width=\"10%\" $top_bund align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"JavaScript:window.open('../kreditor/kreditorkort.php?returside=../includes/luk.php', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,resizable=yes');\"><u>Ny</u></td>";
	print "		</tbody></table></td></tr>";
	}
	print "<table width='100%'><tbody>";
	print"<td><b><a href=varekort.php?opener=$opener&sort=kontonr&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Kontonr</b></td>";
	print"<td><b><a href=varekort.php?opener=$opener&sort=firmanavn&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Navn</b></td>";
	print"<td><b><a href=varekort.php?opener=$opener&sort=addr1&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Adresse</b></td>";
	print"<td><b><a href=varekort.php?opener=$opener&sort=addr2&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Adresse2</b></td>";
	print"<td><b><a href=varekort.php?opener=$opener&sort=postnr&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Postnr</b></td>";
	print"<td><b><a href=varekort.php?opener=$opener&sort=bynavn&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>bynavn</b></td>";
	print"<td><b><a href=varekort.php?opener=$opener&sort=land&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>land</b></td>";
#	print"<td><b><a href=varekort.php?opener=$opener&sort=kontakt&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Kontaktperson</b></td>";
	print"<td><b><a href=varekort.php?opener=$opener&sort=tlf&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Telefon</b></td>";
		print" </tr>";

	 if (!isset ($_GET['sort'])) $_GET['sort'] = null;
	 $sort = $_GET['sort'];
	 if (!$sort) {$sort = 'firmanavn';}

	$q = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'K' order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)) {
		$kontonr=str_replace(" ","",$row['kontonr']);
		print "<tr>";
		print "<td><a href=varekort.php?id=$id&konto_id=$row[id]&returside=$returside&vare_lev_id=$row[id]>$row[kontonr]</a></td>";
		print "<td>$row[firmanavn]</td>";
		print "<td>$row[addr1]</td>";
		print "<td>$row[addr2]</td>";
		print "<td>$row[postnr]</td>";
		print "<td>$row[bynavn]</td>";
		print "<td>$row[land]</td>";
#		print "<td>$row[kontakt]</td>";
		print "<td>$row[tlf]</td>";
		print "</tr>";
	}

	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
function barcode($stregkode) {
	global $bruger_id,$db,$exec_path;

	if (!isset ($ean13)) $ean13 = NULL;
	#(strpos($stregkode,';'))?$stregkoder=explode(";",$stregkode):$stregkoder[0]=$stregkode;
	$stregkoder=explode(";",$stregkode);
	$png=NULL;
	if (file_exists($exec_path."/barcode")||(file_exists($exec_path."/tbarcode")) && file_exists($exec_path."/convert")) { #20140603
		$dan_kode=1;
		if (strpos($stregkoder[0],'æ')) $dan_kode=0; 
		if (strpos($stregkoder[0],'Æ')) $dan_kode=0; 
		if (strpos($stregkoder[0],'ø')) $dan_kode=0; 
		if (strpos($stregkoder[0],'Ø')) $dan_kode=0; 
		if (strpos($stregkoder[0],'å')) $dan_kode=0; 
		if (strpos($stregkoder[0],'Å')) $dan_kode=0;
		if (strpos($stregkoder[0],' ')) $dan_kode=0;
		if ($dan_kode)	{
			$eps="../temp/$db/$stregkoder[0].eps";
			$png="../temp/$db/$stregkoder[0].png";
		if (strlen($stregkoder[0])==13) {
			$a=substr($stregkoder[0],11,1)+substr($stregkoder[0],9,1)+substr($stregkoder[0],7,1)+substr($stregkoder[0],5,1)+substr($stregkoder[0],3,1)+substr($stregkoder[0],1,1);
			$a*=3;
			$a+=substr($stregkoder[0],10,1)+substr($stregkoder[0],8,1)+substr($stregkoder[0],6,1)+substr($stregkoder[0],4,1)+substr($stregkoder[0],2,1)+substr($stregkoder[0],0,1);
			$b=0;
			while(!is_int(($a+$b)/10)) $b++;
			($b==substr($stregkoder[0],12,1))?$ean13=1:$ean13=0; 
		}
			if (file_exists("../temp/$db/".abs($bruger_id)."_*.eps")) unlink("../temp/$db/".abs($bruger_id)."_*.eps");
			if (file_exists($exec_path."/barcode")) {
				$barcodgen=$exec_path."/barcode";
				($ean13)?$ean='ean13':$ean='128';
				$ms=date("is");
				$barcodtxt=$barcodgen." -n -E -e $ean -g 200x40 -b $stregkoder[0] -o $eps\n".$exec_path."/convert $eps $png\n".$exec_path."/rm $eps\n";
			} else {
				$barcodgen=$exec_path."/tbarcode";
				($ean13)?$ean='13':$ean='20';
				$barcodtxt=$barcodgen." --format=ps --barcode=$ean --text=hide --width=80 --height=15 --data=$stregkoder[0] > $eps\n".$exec_path."/convert $eps $png\n".$exec_path."/rm $eps\n";
			}
			system ($barcodtxt);
#			print "<!--"; #20140909
#			print "-->";
		} else $png=NULL;	
	}
	return($png);
}
if ($menu=='T') {
	print "</tbody>
	</table>
	</td></tr>
	<tr><td align = \"center\" valign = \"bottom\">
			<table width=\"75%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
				<td width=\"75%\"><br></td>
			</tbody></table>
	</td></tr>
	</tbody></table></body></html>
	";
} else {
	print "</tbody>
	</table>
	</td></tr>
	<tr><td align = \"center\" valign = \"bottom\">
			<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
				<td width=\"100%\" $top_bund><br></td>
			</tbody></table>
	</td></tr>
	</tbody></table></body></html>
	";
}
if (!$fokus) $fokus="varenr";
print "<script language=\"javascript\">
document.varekort.$fokus.focus();
</script>";
?>
