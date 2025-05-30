<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/varesync.php --- lap 4.1.0 --- 2024.03.18 ---
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
// Copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------
//
// 2018.04.24 Omskrevet variant delen så det inditificeres på variant_id i stedet for på stregkode så det er muligt at ændre stregkode på shop.
// 2018.06.26 Kontrol for stregkodedubletter. søg $strktjek
// 2019.06.19 Forbedret dubletkontrol.
// 2019.11.04 Added utf8_encode to $stregkode[$y] 20191104
// 2020.10.27 if new items is created in Saldi stock qty is inserted from shop. 'Lagerreguler'
// 2020.11.19 Some changes in 'Lagerreguler'
// 2021-03-12 PHR filenames for products is now randomized to avoid same caches file to be fetched.
// 2022-02-09 PHR added kostpris;
// 2024-03-18	PHR Speed optimation.
// 2024-03-27 PHR Some improvemnets
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding


function varesync($valg) {
	global $brugernavn,$db;
	# if ($db == 'pos_8') transaktion('begin');
	$showtxt=NULL;
	$newItemsId = $vare_id = $vnrtjek = array();
	db_modify("update shop_varer set saldi_variant='0' where saldi_variant is NULL",__FILE__ . " linje " . __LINE__);
	db_modify("update shop_varer set shop_variant='0' where shop_variant is NULL",__FILE__ . " linje " . __LINE__);
	$x=0;
	$qtxt="select * from shop_varer order by saldi_id,shop_id,saldi_variant,shop_variant";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if ($x) {
			if ($r['saldi_id']==$a && $r['shop_id']==$b && $r['saldi_variant']==$c && $r['shop_variant']==$d) {
				db_modify("delete from shop_varer where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			}
		}
		$a=$r['saldi_id'];$b=$r['shop_id'];$c=$r['saldi_variant'];$d=$r['shop_variant'];
		$x++;
	}
#	if (file_exist("../temp/$db/productList.txt")) unlink("../temp/$db/productList.txt");
	$plf = fopen("../temp/$db/productList.txt",'w');
	$i = 0;
	$qtxt="select * from varer order by id"; #20240327. File is read by client, and existing products is excluded from list
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$sProductId[$i]      = $r['id'];
		$sProductNo[$i]      = db_escape_string($r['varenr']);
		$sProductPrice[$i]   = $r['salgspris'];
		$sProductCost[$i]    = $r['kostpris'];
		$sProductName[$i]    = db_escape_string($r['beskrivelse']);
		$sProductBarcode[$i] = $r['stregkode'];
		if ($valg == 1) fwrite($plf,"$sProductNo[$i]\n");
		$i++;
	}
	fclose($plf);

	$i = 0;
	$qtxt="select * from shop_varer order by saldi_id";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$svSaldiId[$i]      = $r['saldi_id'];
		$svSaldiVariant[$i] = $r['saldi_variant'];
		$svShopId[$i]       = $r['shop_id'];
		$svShopVariant[$i]  = $r['shop_variant'];
		$i++;
	}

	if ($valg==1) {
		$qtxt="select max(shop_id) as shop_id from shop_varer";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$next_id=$r['shop_id']+1;
	} else {
		$next_id=1;
	}
	$qtxt="select box4 from grupper where art='API'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$api_fil=trim($r['box4']);
	$tmparray=explode("/",$api_fil);
	$lagerfil='';
	for ($x=0;$x<count($tmparray)-1;$x++) {
		$lagerfil.=$tmparray[$x]."/";
	}
	$x=0;
	$varBarCode = array();
	$qtxt="select variant_stregkode from variant_varer";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$varBarCode[$x]=$r['variant_stregkode'];
	}
	$header="User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
	file_put_contents("../temp/$db/restApiVarer.log","db = $db\n", FILE_APPEND);
	if ($db== 'pos_95' || $db == 'pos_85' || $db == 'laja_7') {
		$pfn='prod_'.date('Hi');
		$lf=$lagerfil."files/$pfn.csv";
		$pStck='pStck_'.date('Hi');
		$sf=$lagerfil."files/$pStck.csv";
		file_put_contents( "../temp/$db/restApiVarer.log","lf = $lf, sf = $sf\n", FILE_APPEND);
		shell_exec("nohup curl '$api_fil?products_id=*&filename=$sf'\n");
		system ("cd ../temp/$db/\nwget $sf\n");
		$stockfile=file_get_contents("../temp/$db/$pStck.csv");
		unlink("../temp/$db/$pStck.csv");
		$stockline=explode("\n",$stockfile);
		for ($y=0;$y<count($stockline);$y++){
			list($stockItemNo[$y],$stock[$y])=explode(";",$stockline[$y]);
		}
		$stockfile=NULL;
	} else {
		if (file_exists("../temp/$db/shop_products.csv")) unlink("../temp/$db/shop_products.csv");
		$lf=$lagerfil."files/shop_products.csv";
		$pfn='shop_products';
	}
	shell_exec("nohup curl '$api_fil?products_id=*&filename=$pfn.csv'\n");
	system ("cd ../temp/$db/\nwget $lf\n");
	$indhold=file_get_contents("../temp/$db/$pfn.csv");
	$linje=explode("\n",$indhold);
	(substr($linje[0],-4,3) == 'qty')?$useQty=1:$useQty=0;
	(strpos($linje[0],'kostpris'))?$useCost=1:$useCost=0;
	$shop_encode='';
	for ($y=0;$y<count($linje);$y++){
		if ($y==0) {
			$vars=explode(";",$linje[$y]);
			$var_antal=count($vars);
		}
		$shop_id[$y]=0;
		if ($useQty) {
			list($shop_id[$y],$varenr[$y],$stregkode[$y],$salgspris[$y],$beskrivelse[$y],$gruppe[$y],$tilbud[$y],$notes[$y],$stock[$y])=explode(";",$linje[$y]);
			$stock[$y]=trim($stock[$y],'"');
			($stock[$y])?$stock[$y]*=1:$stock[$y]=0;
		} elseif ($useCost) {
			list($shop_id[$y],$varenr[$y],$stregkode[$y],$salgspris[$y],$kostpris[$y],$beskrivelse[$y],$gruppe[$y],$tilbud[$y],$notes[$y])=explode(";",$linje[$y]);
			$variant_qty[$y]=0;
		} else {
		list($shop_id[$y],$varenr[$y],$stregkode[$y],$salgspris[$y],$beskrivelse[$y],$gruppe[$y],$tilbud[$y],$notes[$y])=explode(";",$linje[$y]);
			$variant_qty[$y]=0;
		}

#		list($shop_id[$y],$varenr[$y],$stregkode[$y],$salgspris[$y],$beskrivelse[$y],$gruppe[$y],$tilbud[$y],$notes[$y])=explode(";",$linje[$y]);
		$shop_id[$y]=trim($shop_id[$y],'"');
		$varenr[$y]=trim($varenr[$y],'"');
		$stockItemNo[$y]=$varenr[$y];
		$salgspris[$y]=trim($salgspris[$y],'"');
		$kostpris[$y]=trim(if_isset($kostpris[$y],0),'"');
		if (!$kostpris[$y]) $kostpris[$y]=0;
		$gruppe[$y]=trim($gruppe[$y],'"');
		$beskrivelse[$y]=trim($beskrivelse[$y],'"');
		$stregkode[$y]=trim($stregkode[$y],'"');
		$notes[$y]=trim($notes[$y],'"');
		$tilbud[$y]=trim($tilbud[$y],'"');

		$newstock[$y]=0;
if ($brugernavn=='phr') echo "$varenr[$y]	$stregkode[$y]<br>";
		if (!$shop_id[$y]) {
			if (substr($stregkode[$y],0,3)=='EAN' && is_numeric(substr($stregkode[$y],3))) $shop_id[$y]=substr($stregkode[$y],3);
			elseif (is_numeric($varenr[$y])) $shop_id[$y]=$varenr[$y];
		}
		if (!$shop_encode) {
			$tmp=$beskrivelse[$y];
			($tmp=mb_convert_encoding($beskrivelse[$y], 'UTF-8', 'ISO-8859-1'));
			if (strpos($tmp,'æ') || strpos($tmp,'ø')  || strpos($tmp,'å')) $shop_encode='iso-8859';
			elseif (strpos($tmp,'Æ') || strpos($tmp,'Ø')  || strpos($tmp,'Å')) $shop_encode='iso-8859';
		}
	}
	if (!$shop_encode) $shop_encode = 'utf8';
	$strktjek=array();
	for ($y=0;$y<count($linje);$y++) {
		if ($shop_encode=='iso-8859') {
			$beskrivelse[$y]=mb_convert_encoding($beskrivelse[$y], 'UTF-8', 'ISO-8859-1');
			$varenr[$y]=mb_convert_encoding($varenr[$y], 'UTF-8', 'ISO-8859-1');
			$stregkode[$y]=mb_convert_encoding($stregkode[$y], 'UTF-8', 'ISO-8859-1'); #20191104
		}
		$dbvnr=NULL;
		if (in_array("'". $varenr[$y]. ",",$vnrtjek)) {
			for ($i=0;$i<count($vnrtjek);$i++) {
				if ($vnrtjek[$i]==$varenr[$y]) {
					$dbvnr=$varenr[$i];
					$dbbesk=$beskrivelse[$i];
				}
			}
#			alert("Varenr $varenr[$y]:$beskrivelse[$y] bruges også i $dbbesk\\n $dbbesk overskrives");
			$showtxt.="Varenr $varenr[$y]:$beskrivelse[$y] bruges også i $dbbesk-- $dbbesk overskrevet<br>";
		} elseif ($stregkode[$y] && in_array("$stregkode[$y]",$strktjek)) {
			for ($i=0;$i<count($strktjek);$i++) {
				if ($strktjek[$i]==$stregkode[$y]) {
					$dbvnr=$varenr[$i];
					$dbbesk=$beskrivelse[$i];
				}
			}
#			alert("Stregkode $stregkode[$y] bruges også i $dbvnr:$dbbesk\\n stregkode slettes for $varenr[$y]:$beskrivelse[$y]");
			$showtxt.="Stregkode $stregkode[$y] bruges også i $dbvnr:$dbbesk -- stregkode slettet for $varenr[$y]:$beskrivelse[$y]<br>";
			$stregkode[$y]=NULL;
		} elseif ($stregkode[$y] && in_array($stregkode[$y],$varBarCode)) {
			$showtxt.="Stregkode $stregkode[$y] bruges også i varianter,  -- stregkode slettet for $varenr[$y]:$beskrivelse[$y]";
			$stregkode[$y]=NULL;
		}
		$strktjek[$y]=$stregkode[$y];
		$vnrtjek[$y]="'". $varenr[$y] ."'";
		$beskrivelse[$y]=db_escape_string($beskrivelse[$y]);
		$varenr[$y]=db_escape_string($varenr[$y]);

		if (is_numeric($salgspris[$y]) && is_numeric($gruppe[$y])) {
			if ($valg=='1' && !in_array($varenr[$y],$sProductNo) && is_numeric($salgspris[$y]) && is_numeric($gruppe[$y])) {
				echo "Opretter $varenr[$y], $beskrivelse[$y]<br>";
				$query = db_select("SELECT var_value FROM settings WHERE var_name = 'min_beholdning' AND var_grp = 'productOptions'", __FILE__ . " linje " . __LINE__);
				if(db_num_rows($query) > 0){
					$r = db_fetch_array($query);
					$minBeholdning = (int)$r["var_value"];
					$qtxt = "insert into varer (varenr,stregkode,beskrivelse,salgspris,kostpris,gruppe,beholdning,lukket,min_lager) ";
					$qtxt.= "values ";
					$qtxt.= "('$varenr[$y]','$stregkode[$y]','$beskrivelse[$y]','$salgspris[$y]','$kostpris[$y]','$gruppe[$y]','0','0',$minBeholdning)";
				}else{
					$qtxt = "insert into varer (varenr,stregkode,beskrivelse,salgspris,kostpris,gruppe,beholdning,lukket) ";
					$qtxt.= "values ";
					$qtxt.= "('$varenr[$y]','$stregkode[$y]','$beskrivelse[$y]','$salgspris[$y]','$kostpris[$y]','$gruppe[$y]','0','0')";
				}
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt="select id from varer where varenr='$varenr[$y]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$vare_id[$y]=$r['id'];
				$n=count($newItemsId);
				$newItemsNo[$n]=$varenr[$y];
				$newItemsId[$n]=$vare_id[$y];
				if ($shop_id[$y]) {
					$newItemsShopId[$n]=$shop_id[$y];
					$qtxt = "insert into shop_varer (saldi_id,shop_id,saldi_variant,shop_variant) ";
					$qtxt.= "values ('$vare_id[$y]','$shop_id[$y]','0','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				for($s=0;$s<count($stockItemNo);$s++) {
					$stockItemNo[$s] = trim($stockItemNo[$s],'"');
					if ($stockItemNo[$s]==$varenr[$y]) {
						$newstock[$y]=$stock[$s];
						lagerreguler($vare_id[$y],$stock[$s],0,0,date("Y-m-d"),'0');
						if ($stock[$s]) echo "Sætter $varenr[$y] til ".dkdecimal($stock[$s])." stk<br>";
					}
				}
			} elseif ($valg=='2' && $salgspris[$y] && in_array($varenr[$y],$sProductNo)) {
				for ($i=0;$i<count($sProductId);$i++) {
					if ($sProductNo[$i] == $varenr[$y]) {
						$vare_id[$y] = $sProductId[$i];
						if ($beskrivelse[$y] != $sProductName[$i] || $stregkode[$y] !=  $sProductBarcode[$i]
							|| $salgspris[$y] - $sProductPrice[$i] || ($kostpris[$y] > 0 && $kostpris[$y] != $sProductCost[$i])) {
							$qtxt = "update varer set beskrivelse='$beskrivelse[$y]',";
							$qtxt.= "stregkode='$stregkode[$y]',salgspris='$salgspris[$y]',";
							if ($kostpris[$y] > 0) $qtxt.= "kostpris='$kostpris[$y]',";
							$qtxt.= "special_price='0' where id = '$sProductId[$i]'";

file_put_contents("../temp/$db/varesync.log","$qtxt\n",FILE_APPEND);
#if ($brugernavn=='phr') echo __line__." $qtxt<br>";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						}
						if ($tilbud[$y]) {
							$qtxt = "update varer set special_price='$tilbud[$y]',special_from_date='2018-01-01',";
							$qtxt.= "special_to_date='2099-12-31' where id = '$sProductId[$i]'";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						}
						if (!in_array($sProductId[$i],$svSaldiId)) {
							$qtxt = "insert into shop_varer (saldi_id,saldi_variant,shop_id,shop_variant) ";
							$qtxt.= "values ('$sProductId[$i]','0','$shop_id[$y]','0')";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						}
					}
				}
			}
		}
	}
	############################# Varianter #########################
	$shop_encode = NULL;
	$mangler = $s_variant_id = $strktjek = $var_id = $var_type = $vt_var = array();
		$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='variant_varer' and column_name='variant_kostpris'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER TABLE variant_varer add column	variant_kostpris numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE variant_varer add column	variant_salgspris numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE variant_varer add column	variant_vejlpris numeric(15,3)",__FILE__ . " linje " . __LINE__);
		db_modify("ALTER TABLE variant_varer add column	variant_id int4",__FILE__ . " linje " . __LINE__);
	}
	$x=0;
	$qtxt="select * from varianter order by id";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$var_id[$x]=$r['id'];
		$var_type[$x]=$r['beskrivelse'];
		$x++;
	}
	$x=0;
	$qtxt="select * from variant_typer order by variant_id,beskrivelse";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$vt_id[$x]=$r['id'];
		$vt_var_id[$x]=$r['variant_id'];
		$vt_var[$x]=$r['beskrivelse'];
		$x++;
	}
	if ($db=='pos_85') {
		$useQty=1;
		$vfn='var_'.date('Hi');
		$lf=$lagerfil."files/$vfn.csv";
		$sfn='vStck_'.date('Hi');
		$sf=$lagerfil."files/$sfn.csv";
		$header="User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
		shell_exec("nohup curl '$api_fil?variant=*&filename=$vfn.csv'\n");
echo 	"cd ../temp/$db/<br>wget $lf<br>";
		system ("cd ../temp/$db/\nwget $lf\n");
		#		$systxt="/usr/bin/wget --no-cache --no-check-certificate --spider --header='$header' '$api_fil?variant=*&filename=$vfn.csv' \n";
#		$result=system ($systxt);
		if (file_exists("../temp/$db/$sfn.csv")) unlink("../temp/$db/$sfn.csv");
		system ("cd ../temp/$db/\nwget $sf\n");
		if (!file_exists("../temp/$db/$vfn.csv")) exit;
		if (file_exists("../temp/$db/$sfn.csv")) {
			$stockfile=file_get_contents("../temp/$db/$sfn.csv");
#			unlink("../temp/$db/$sfn.csv");
			$stockline=explode("\n",$stockfile);
			for ($y=0;$y<count($stockline);$y++){
				list($stockVariant[$y],$stock[$y])=explode(";",$stockline[$y]);
				}
			$stockfile=NULL;
		}
	} else {
		$lf=$lagerfil."files/shop_variants.csv";
		$vfn='shop_variants';
		if (file_exists("../temp/$db/$vfn.csv")) unlink ("../temp/$db/$vfn.csv");
#echo "nohup curl '$api_fil??variant=*&filename=$vfn.csv'<br>";
#		shell_exec("nohup curl '$api_fil??variant=*&filename=$vfn.csv'\n");
		$systxt="/usr/bin/wget --no-cache --no-check-certificate --spider --header='$header' '$api_fil?variant=*&filename=$vfn.csv' \n";
		$result=system ($systxt);
		if (file_exists("../temp/$db/$vfn.csv")) unlink ("../temp/$db/$vfn.csv");
		$systxt="cd ../temp/$db/\nwget --no-cache --no-check-certificate --header='$header' '$lf'\n";
		$result=system ($systxt);
		if (!file_exists("../temp/$db/$vfn.csv")) exit;
	}
	$indhold=file_get_contents("../temp/$db/$vfn.csv");
#	unlink("../temp/$db/$vfn.csv");
	$linje=explode("\n",$indhold);
 	(substr($linje[0],-4,3) == 'qty')?$useQty=1:$useQty=0;
	for ($y=0;$y<count($linje);$y++){
		if ($y==0) {
			$vars=explode(";",$linje[$y]);
			$varCount=count($vars);
		}
		$shop_id[$y]=0;
		if ($useQty) {
			list($varenr[$y],$parent_id[$y],$variant_id[$y],$stregkode[$y],$variant[$y],$variant_type[$y],$variant_text[$y],$stock[$y])=explode(";",$linje[$y]);
			$stockVariant[$y]=trim($stregkode[$y],'"');
#if ($brugernavn=='phr') echo __line__." $varenr[$y],$parent_id[$y],$variant_id[$y],$stregkode[$y]<br>";
		} else {
		list($varenr[$y],$parent_id[$y],$variant_id[$y],$stregkode[$y],$variant[$y],$variant_type[$y],$variant_text[$y])=explode(";",$linje[$y]);
			if (!isset($stock[$y])) $stock[$y]=0;
#if ($brugernavn=='phr') echo __line__." $varenr[$y],$parent_id[$y],$variant_id[$y],$stregkode[$y]<br>";

		}
		$parent_id[$y]    = trim(trim($parent_id[$y],'"'));
		$varenr[$y]       = trim(trim($varenr[$y],'"'));
		$variant_id[$y]   = trim(trim($variant_id[$y],'"'));
		$stregkode[$y]    = trim(trim($stregkode[$y],'"'));
		$variant[$y]      = trim(trim($variant[$y],'"'));
		$variant_type[$y] = trim(trim($variant_type[$y],'"'));
		$variant_text[$y] = trim(trim($variant_text[$y],'"'));
		$stock[$y]        = trim(trim($stock[$y],'"'));
		if ($stregkode[$y] && in_array("$stregkode[$y]",$strktjek)) {
			$alert= "Stregkode $stregkode[$y] brugt i anden variant .\\n";
			$showtxt.="Stregkode $stregkode[$y] brugt i anden variant. -- ";

			if ($variant_id) {
				$alert.= "Stregkode rettet til EAN$variant_id[$y]";
				$showtxt.="Stregkode rettet til EAN$variant_id[$y]<br>";
				$stregkode[$y]="EAN".$variant_id[$y];
			} else {
				$alert.= "Varianten $variant[$y] for varenr: $varenr[$y] udeladt";
				$showtxt.="Varianten $variant[$y] for varenr: $varenr[$y] udeladt<br>";
			}
#			alert("$alert");
		} else {
			$strktjek[count($strktjek)]=$stregkode[$y];
		}
		$variant_id[$y] = (int)$variant_id[$y];
		if (!$shop_encode) {
			$tmp=$variant_text[$y];
#			if (strpos($tmp,'æ') || strpos($tmp,'ø')  || strpos($tmp,'å')) $shop_encode='utf8';
#			elseif (strpos($tmp,'Æ') || strpos($tmp,'Ø')  || strpos($beskrivelse[$y],'Å')) $shop_encode='utf8';
			$tmp=mb_convert_encoding($variant_text[$y], 'UTF-8', 'ISO-8859-1');
			if (strpos($tmp,'æ') || strpos($tmp,'ø')  || strpos($tmp,'å')) $shop_encode='iso-8859';
			elseif (strpos($tmp,'Æ') || strpos($tmp,'Ø')  || strpos($tmp,'Å')) $shop_encode='iso-8859';
		}
	}
	if (!$shop_encode) $shop_encode = 'utf8';
#if ($brugernavn=='phr') transaktion ('begin');
	$m = 0;
#	$mangler = $var_type = array();
	for ($y=1;$y<count($linje);$y++) {
		$saldi_var_id[$y] = 0;
		$s_var_id[$y]     = 0;
		$parent_id[$y]    = (int)$parent_id[$y];
		if ($variant_type[$y] && !in_array($variant_type[$y],$var_type) && !in_array($variant_type[$y],$mangler)) {
#			alert ("Varianten \"$variant_type[$y]\" ikke oprettet");
			$showtxt.="Varianten \"$variant_type[$y]\" ikke oprettet<br>";

			$mangler[$m]=$variant_type[$y];
			$m++;
		}
		if (!in_array($variant[$y],$vt_var)) {
			for ($z=0;$z<count($var_type);$z++) {
			if ($var_type[$z]==$variant_type[$y]) {
					$qtxt="insert into variant_typer (beskrivelse,variant_id) values ('$variant[$y]','$var_id[$z]')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt="select id from variant_typer where beskrivelse='$variant[$y]' and variant_id='$var_id[$z]'";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$v=count($vt_id);
					$vt_id[$v]=$r['id'];
					$vt_var_id[$v]=$var_id[$z];
					$vt_var[$v]=$variant[$y];
				}
			}
		}
		for ($x=0;$x<count($var_id);$x++) {
			if (isset($variant_type[$y]) && isset($var_type[$x]) && $variant_type[$y] == 	$var_type[$x]) {
				$s_var_id[$y]=$var_id[$x];
			} 
		}
		$s_variant[$y]=0;
		if ($parent_id[$y] && $s_var_id[$y]) {
			for ($x=0;$x<count($vt_id);$x++) {
				if ($s_var_id[$y]==$vt_var_id[$x] && $vt_var[$x]==$variant[$y]) {
					$s_variant[$y]=$vt_id[$x];
				}
			}
		}
		if ($parent_id[$y] && $variant_id[$y]) {
		if ($shop_encode=='iso-8859') {
			$variant_text[$y]=mb_convert_encoding($variant_text[$y], 'UTF-8', 'ISO-8859-1');
			$varenr[$y]=mb_convert_encoding($varenr[$y], 'UTF-8', 'ISO-8859-1');
		}
		$variant_text[$y]=db_escape_string($variant_text[$y]);
		$varenr[$y]=db_escape_string($varenr[$y]);
		$qtxt="select id from varer where varenr = '$varenr[$y]'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $saldiVareId[$y]=$r['id'];
		else $saldiVareId[$y]=NULL;
		$qtxt="select id,saldi_id,saldi_variant from shop_varer where shop_id='$parent_id[$y]' and shop_variant='$variant_id[$y]'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if (isset ($r['saldi_variant']) && in_array($r['saldi_variant'],$s_variant_id)) {
			$qtxt="delete from shop_varer where id = '$r[id]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} elseif(isset($r['saldi_id'])) {
			$variantVareId[$y]=$r['saldi_id'];
			$s_variant_id[$y]=$r['saldi_variant'];
		} else {
			$variantVareId[$y]=$s_variant_id[$y]=0;
		}
		if ($s_variant_id[$y] && $saldiVareId[$y] && $saldiVareId[$y] != $variantVareId[$y]) {
			$qtxt="update shop_varer set saldi_id = '$saldiVareId[$y]' where id='$s_variant_id[$y]'";
#if ($brugernavn=='phr') echo __line__." ".$qtxt."<br>";
			$variantVareId[$y] = $saldiVareId[$y];
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$qtxt="select id,saldi_id from shop_varer where shop_id='$parent_id[$y]'";
#if ($brugernavn == 'phr') echo __line__." $qtxt<br>";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#echo $r['saldi_id'] ." && $variantVareId[$y] != ". $r['saldi_id'] ."<br>";
		if (isset($r['saldi_id']) && $variantVareId[$y] != $r['saldi_id']) {
			$variantVareId[$y] = $r['saldi_id'];
			$qtxt = "select id from variant_typer where beskrivelse = '$variant[$y]'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt = "select id from variant_varer where vare_id = '$variantVareId[$y]' and variant_type ='$r[id]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($r['id']	&& $s_variant_id[$y] != $r['id']) {
					$s_variant_id[$y] = $r['id'];
					$z=0;
					$qtxt="select id from shop_varer where saldi_id = '$variantVareId[$y]' and saldi_variant ='$s_variant_id[$y]' order by id";
					while ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
						if ($z == 0) $qtxt = "update shop_varer set shop_id='$parent_id[$y]', shop_variant = '$variant_id[$y]' where id = '$r[id]'";
						else $qtxt = "delete from shop_varer where id = '$r[id]'";
						$z++;
					}
					if ($z == 0 && $s_variant_id[$y]) {
						$qtxt = "insert into shop_varer (saldi_id,saldi_variant,shop_id,shop_variant) values ";
						$qtxt.= "('$variantVareId[$y]','$s_variant_id[$y]','$parent_id[$y]','$variant_id[$y]')";
#if ($brugernavn == 'phr') echo __line__." $qtxt<br>";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
			}
		}
		if (isset($s_variant_id[$y]) && $s_variant_id[$y]) { # 20180918
			$qtxt="select variant_stregkode from variant_varer where id='$s_variant_id[$y]'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="update variant_varer set variant_stregkode='$stregkode[$y]' where id='$s_variant_id[$y]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} else $s_variant_id[$y]=NULL;
		} else {
			$qtxt="select saldi_id from shop_varer where shop_id='$parent_id[$y]'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$variantVareId[$y]=$r['saldi_id'];
				$s_variant_id[$y]=NULL;
			}
		}
		if ($variantVareId[$y] && $s_variant_id[$y]) {
			$qtxt="update variant_varer set vare_id='$variantVareId[$y]',variant_type='$s_variant[$y]' where id='$s_variant_id[$y]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} elseif ($variantVareId[$y]) {
			$qtxt="select id from variant_varer where variant_stregkode='$stregkode[$y]'";
#if ($brugernavn=='phr') echo $qtxt."<br>";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($r['id']) { #Har haft ændret denne til (!$r['']) og udkommenteret de næste 3 linjer. Det betød at autoindsatte varianter ikke blev korrekte.
					$s_variant_id[$y]=$r['id'];
					$qtxt="update variant_varer set vare_id='$variantVareId[$y]',variant_type=$s_variant[$y] where id='$s_variant_id[$y]'";
				} else {
					$qtxt="select id from variant_varer where vare_id = '$variantVareId[$y]' and variant_type = '$s_variant[$y]'";
						if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
						$qtxt = NULL;
 					} else {
						$qtxt = "insert into variant_varer";
						$qtxt.= "(vare_id,variant_type,variant_beholdning,variant_stregkode,lager,variant_salgspris,";
						$qtxt.= "variant_kostpris,variant_vejlpris,variant_id)";
						$qtxt.= "values ";
						$qtxt.= "('$variantVareId[$y]','$s_variant[$y]','1','$stregkode[$y]','0','0','0','0','1')";
					}
#if ($brugernavn=='phr') echo $qtxt."<br>";
					if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt="select id from variant_varer where variant_stregkode='$stregkode[$y]'";
#if ($brugernavn=='phr') echo $qtxt."<br>";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$s_variant_id[$y]=$r['id'];
#if ($brugernavn=='phr') echo $s_variant_id[$y]."<br>";
#if ($brugernavn=='phr') echo __line__ ." ". count($stockVariant) ."<br>";
#if ($brugernavn=='phr') echo "$s=0;$s<". count($stockVariant) ." ;$s++<br>";
					for($s=0;$s<count($stockVariant);$s++) {
#if ($brugernavn=='phr') echo __line__  ." $stockVariant[$s] == $stregkode[$y] <br>";
						if ($stockVariant[$s]==$stregkode[$y]) {
if ($db == 'pos_85') echo "lagerreguler($variantVareId[$y],$stock[$s],0,0,".date("Y-m-d").",$s_variant_id[$y])";
							lagerreguler($variantVareId[$y],$stock[$s],0,0,date("Y-m-d"),$s_variant_id[$y]);
							echo "Sætter $stockVariant[$s] til $stock[$s] stk<br>";
						}
					}
				}
				$qtxt="select id from shop_varer where shop_variant='$variant_id[$y]'";

				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$qtxt=NULL;
				if ($r['id']) {
					$qtxt="update shop_varer set saldi_variant='$s_variant_id[$y]',shop_variant=$variant_id[$y] where id='$r[id]'";
				} elseif ($variantVareId[$y] && $parent_id[$y] && $s_variant_id[$y] && $variant_id[$y]) {
					$qtxt="insert into shop_varer";
					$qtxt.="(saldi_id,shop_id,saldi_variant,shop_variant) ";
					$qtxt.="values ";
					$qtxt.="('$variantVareId[$y]','$parent_id[$y]','$s_variant_id[$y]','$variant_id[$y]')";
				}
				if ($qtxt && $s_variant_id[$y]) {
#if ($brugernavn=='phr') echo "$qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt="update varer set varianter='1' where id=$variantVareId[$y]";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	for($s=0;$s<count($vare_id);$s++) {
		if (!in_array($vare_id[$s],$variantVareId) && $newstock[$s]) {
			lagerreguler($vare_id[$s],$newstock[$s],0,0,date("Y-m-d"),'0');
			echo __line__." Sætter $varenr[$s] til $newstock[$s] stk<br>";
		}
	}
	echo $showtxt;
}

?>
