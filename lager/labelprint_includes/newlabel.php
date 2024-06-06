<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/lager//labelprint_includes/newlabel.php--lap 4.1.0---2024-01-23	---
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
// Copyright (c) 2020-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 
// 20200702 PHR Added support for more labels.
// 20200812 PHR Changed _b to kb and _n to kn 
// 20200812 PHR Added ' and hidden is 'FALSE' 
// 20201205 PHR timestamp written to lastprint when printed
// 20240123 PHR Added $firstprint & $lastprint.

$line=explode("\n",$txt);
$top=$txt='';
$cols=$rows=1;
$txtlen=100;
$endbottom=$endtop=0;
$createdate=$ip=$ipLine=NULL;
for ($x=0;$x<count($line);$x++) {
	if (substr($line[$x],0,3)=='$ip') {
		list($tmp,$ip)=explode("=",$line[$x]);
		$ipLine=$line[$x];
	} 
	if (substr($line[$x],0,5)=='$cols') {
		list($tmp,$cols)=explode("=",$line[$x]);
		$cols=(int)$cols;
	} elseif (substr($line[$x],0,5)=='$rows') {
		list($tmp,$rows)=explode("=",$line[$x]);
		$rows=(int)$rows;
	} elseif (substr($line[$x],0,7)=='$txtlen') {
		list($tmp,$txtlen)=explode("=",$line[$x]);
		$txtlen=(int)$txtlen;
	} elseif (trim($line[$x])=='<top>') {
		$top = $line[$x];
#cho __line__ ." ". urlencode($line[$x]) ."<br>";
	} elseif ($top && $endtop==0) {
		$top.= $line[$x];
#cho __line__ ." ". urlencode($line[$x]) ."<br>";
		if (trim($line[$x])=='</top>') $endtop=1;
	} elseif (trim($line[$x])=='<bottom>') {
		$bottom = $line[$x];
	} elseif ($bottom && $endbottom==0) {
		$bottom.= $line[$x];
		if (trim($line[$x])=='</bottom>') $bottom=1;
	} elseif (trim($line[$x])) {
		$txt.= $line[$x];
	}
}
if ($ipLine) $txt = str_replace($ipLine.'\n','',$txt);
if ($qty) {
	$qty = substr($qty,0,3);
	$qty*=1;
	if (!$qty) $qty=1;
	while (strlen($qty) < 3) $qty = '0'.$qty;
	$txt = str_replace('$qty',$qty,$txt);
}
if ($printIds) {
	$labels=explode(',',$printIds);
} else $labels[0]=$labelId;

if (($varenr || $stregkode) && (!$account || !$condition)) {
	if ($varenr) {
		$a = substr($varenr,1,1);
		$b = substr($varenr,5,4);
	} else {
		$a = substr($stregkode,1,1);
		$b = substr($stregkode,5,4);
	}
	if (($a == 'b' || $a == 'n')  && is_numeric($b)) {
		$condition=$a;
		$account=$b;
	}
}

for ($a=1;$a<=$rows;$a++) {
	for ($b=1;$b<=$cols;$b++) {
		$barcode[$a][$b]=NULL;
		$description[$a][$b]=NULL;
		$price[$a][$b]=NULL;
	}
}
$fp=fopen($filename,'w');
fwrite ($fp, $top);
for ($l=0;$l<count($labels);$l++) {
	if ($account && $condition) {
		$qtxt =" select id from adresser where kontonr = '$account' and art='D'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$accountId=$r['id'];
		if ($id) $qtxt =" select id,varenr from varer where id='$id'";
		else {
			$qtxt =" select id,varenr from varer where varenr like lower('";
			($condition=='new')?$qtxt.= "kn___$account":$qtxt.= "kb___$account";
			$qtxt.="')";
		}
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$id=$r['id'];
		$varenr=$r['varenr'];
		$qtxt=NULL;
		if ($labels[$l]) {
			$qtxt = "select * from mylabel ";
			$qtxt.= "where account_id = '$accountId' and id='$labels[$l]' and hidden = FALSE";
		} elseif ($page) {
			$qtxt = "select * from mylabel ";
			$qtxt.= "where account_id = '$accountId' and page='$page' and condition='$condition' and hidden = FALSE order by row,col";
		}
		if ($qtxt) {
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				if ($labels[$l]) {
					$row=$col=1;
				} else {
					$row=$r['row'];
					$col=$r['col'];
				}
				$barcode[$row][$col]=$r['barcode'];
				$createdate[$row][$col]=date('dmy',$r['created']);
				$description[$row][$col]=$r['description'];
				if ($r['firstprint']) $firstprint[$row][$col]=date("dmy",$r['firstprint']);
				else $firstprint[$row][$col] = NULL;
				if ($r['lastprint']) $lastprint[$row][$col]=date("dmy",$r['lastprint']);
				else $lastprint[$row][$col] = NULL;
				$price[$row][$col]=$r['price'];
				if ($price[$row][$col]) {
					$price[$row][$col]=dkdecimal($price[$row][$col]);
					$qtxt="update mylabel set lastprint='". date('U') ."' where id = '$r[id]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	$r=db_fetch_array(db_select("select * from varer where id='$id'",__FILE__ . " linje " . __LINE__));
	$momsfri='on';
	$salgspris=$r['salgspris']; #20160505
	$gruppe=$r['gruppe'];
#$salgspris2=$r['salgspris2'];
	$special_price=$r['special_price'];
	$qtxt="select var_value from settings where var_name = 'vatOnItemCard' and var_grp = 'items'";
	$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$vatOnItemCard=$r2['var_value'];
	$qtxt="select box4,box7 from grupper where art='VG' and kodenr='$gruppe' and box7!='on'";
	if ($vatOnItemCard && $r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {	
		$konto = $r2['box4'];
		$qtxt="select moms from kontoplan where kontonr='$r2[box4]' order by id desc limit 1";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$momskode=str_replace("S","",$r2['moms']);	
		$qtxt="select box2 from grupper where art='SM' and kodenr = '$momskode'";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$incl_moms=$r2['box2']*1;
		$salgspris*=(100+$incl_moms)/(100);
		$special_price*=(100+$incl_moms)/(100);
	}
	if (!$img && !$brotherTD) { #20200407
		if ($stregkode) $img=barcode($stregkode);
		elseif ($varenr) $img=barcode($varenr);
	}
	if (!$brotherTD) fwrite ($fp, "<div id=\"main\">\n");
	for ($a=1;$a<=$rows;$a++) {
		for ($b=1;$b<=$cols;$b++) {
			$labelTxt=$txt;
			$dkkpris=str_replace(',00',',-',dkdecimal($salgspris,2));
			$labelTxt=str_replace('$minbeskrivelse',$description[$a][$b],$labelTxt);
			$labelTxt=str_replace('$beskrivelse',$r['beskrivelse'],$labelTxt);
			$labelTxt=str_replace('$minpris',$price[$a][$b],$labelTxt);
			$labelTxt=str_replace('$varenr',$r['varenr'],$labelTxt);
			$labelTxt=str_replace('$trademark',$r['trademark'],$labelTxt);
			$labelTxt=str_replace('$barcode',$barcode[$a][$b],$labelTxt);
			$labelTxt=str_replace('$createdate',if_isset($createdate[$a][$b],NULL),$labelTxt);
			$labelTxt=str_replace('$firstprint',if_isset($firstprint[$a][$b],NULL),$labelTxt);
			$labelTxt=str_replace('$lastprint',if_isset($lastprint[$a][$b],NULL),$labelTxt);
			if ($brotherTD) $labelTxt=str_replace('$stregkode',$barcode[$a][$b],$labelTxt);
			elseif ($stregkode) {
				$labelTxt=str_replace('$stregkode',$stregkode,$labelTxt);
			} else {
				if ($r['stregkode']) $labelTxt=str_replace('$stregkode',$r['stregkode'],$labelTxt);
				else $labelTxt=str_replace('$stregkode',$r['varenr'],$labelTxt);
			}
			if (strpos($labelTxt,'$enhedspris/$enhed')) {
				if ($r['enhed'] && $r['indhold']) {
					$labelTxt=str_replace('$enhedspris',dkdecimal(($r['pris']/$r['indhold']),2),$labelTxt);
					$labelTxt=str_replace('$enhed',$r['enhed'],$labelTxt);
				} else {
					$labelTxt=str_replace('($enhedspris/$enhed)','',$labelTxt);
					$labelTxt=str_replace('$enhedspris/$enhed','',$labelTxt);
				}
			} else {
				if ($r['salgspris'] && $r['indhold']!=0) {
					$labelTxt=str_replace('$enhedspris',dkdecimal(($r['salgspris']/$r['indhold']),2),$labelTxt);
				} elseif ($r['salgspris']) $labelTxt=str_replace('$enhedspris',dkdecimal(($r['salgspris']),2),$labelTxt);
				$labelTxt=str_replace('$enhed',$r['enhed'],$labelTxt);
			}
			if (strpos($labelTxt,'$lev_varenr[')) { #20170628
				$x=1;
				$qtxt="select * from vare_lev where vare_id='$id' order by posnr";
				$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while($r2=db_fetch_array($q2)) {
					$labelTxt=str_replace('$lev_varenr['.$x.']',$r2['lev_varenr'],$labelTxt);
					$x++;
				}
			}
			if (strpos($labelTxt,'$variant')) { #20170628
				$qtxt="select variant_id,variant_type from variant_varer where variant_stregkode='$stregkode'";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$variant_id=$r2['variant_id'];
				if ($variant_id) {
					$variant_type_id=explode(chr(9),$r2['variant_type']);
					$qtxt="select beskrivelse from varianter where id='$variant_id'";
					$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$variant=$r2['beskrivelse'];
					$qtxt="select beskrivelse from variant_typer where id='$variant_type_id[0]'";
					$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$variant_type=$r2['beskrivelse'];
				} else $variant=$variant_type=NULL;
			}  
			$labelTxt=str_replace('$variant',$variant." ".$variant_type,$labelTxt);
			$labelTxt=str_replace('$kostpris',$r['kostpris'],$labelTxt);
			if ($barcode[$a][$b]) {
					$myImg=barcode($barcode[$a][$b]);
#cho "$myImg<br>";				
					$labelTxt=str_replace('$img',$myImg,$labelTxt);
			} else $labelTxt=str_replace('$img',$img,$labelTxt);
			if ($price[$a][$b]) $labelTxt=str_replace('$beskrivelse',$price[$a][$b],$labelTxt);
			$labelTxt=str_replace('$pris',dkdecimal($salgspris,2),$labelTxt);
			$labelTxt=str_replace('$dkkpris',$dkkpris,$labelTxt);
			$labelTxt=str_replace('$enhed',$r['enhed'],$labelTxt);
			$labelTxt=str_replace('$location','$lokation',$labelTxt); #20170628
			if (strpos($labelTxt,'$lokation')) { #20170628
				$qtxt="select lok1 from lagerstatus where vare_id=$id and lager=1";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$labelTxt=str_replace('$lokation',$r2['lok1'],$labelTxt); #20180323
			}
			$labelTxt=str_replace('$varemrk',$r['trademark'],$labelTxt);
			$labelTxt=str_replace('$indhold',$r['indhold'],$labelTxt);
			$labelTxt=str_replace('$notes',$r['notes'],$labelTxt);
			$labelTxt=str_replace('$special_pris',$special_price,$labelTxt);
			$labelTxt=str_replace('$special_fra_dato',dkdato($r['special_from_date']),$labelTxt);
			$labelTxt=str_replace('$special_til_dato',dkdato($r['special_to_date']),$labelTxt);
			$labelTxt=str_replace('$special_fra_tid',substr($r['special_from_time'],0,5),$labelTxt);
			$labelTxt=str_replace('$special_til_tid',substr($r['special_to_time'],0,5),$labelTxt);
			fwrite($fp,$labelTxt);
		} 
		if ($brotherTD) fwrite ($fp,"\n");
		else fwrite ($fp,"<br>");
	}	
	if (!$brotherTD) fwrite ($fp, "</div>\n");
}
fclose($fp);
?>
