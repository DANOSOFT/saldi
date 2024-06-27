<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/lager//labelprint_includes/oldlabel.php--lap 3.9.9---2021-02-04	---
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
// Copyright (c) 2016-2021 saldi.dk aps
// ----------------------------------------------------------------------

$r=db_fetch_array(db_select("select * from varer where id='$id'",__FILE__ . " linje " . __LINE__));
$momsfri='on';
$salgspris=$r['salgspris']; #20160505
$gruppe=(int)$r['gruppe'];
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
$dkkpris=str_replace(',00',',-',dkdecimal($salgspris,2));
$txt=str_replace('$beskrivelse',$r['beskrivelse'],$txt);
$txt=str_replace('$varenr',$r['varenr'],$txt);
$txt=str_replace('$trademark',$r['trademark'],$txt);
if ($stregkode) {
	$txt=str_replace('$stregkode',$stregkode,$txt);
} else {
	if ($r['stregkode']) $txt=str_replace('$stregkode',$r['stregkode'],$txt);
	else $txt=str_replace('$stregkode',$r['varenr'],$txt);
}
if (strpos($txt,'$enhedspris/$enhed')) {
	if ($r['enhed'] && $r['indhold']) {
		$txt=str_replace('$enhedspris',dkdecimal(($r['pris']/$r['indhold']),2),$txt);
		$txt=str_replace('$enhed',$r['enhed'],$txt);
	} else {
		$txt=str_replace('($enhedspris/$enhed)','',$txt);
		$txt=str_replace('$enhedspris/$enhed','',$txt);
	}
} else {
	if ($r['indhold']) $txt=str_replace('$enhedspris',dkdecimal(($r['pris']/$r['indhold']),2),$txt);
	else $txt=str_replace('$enhedspris',dkdecimal(($r['pris']),2),$txt);
	$txt=str_replace('$enhed',$r['enhed'],$txt);
}
if (strpos($txt,'$lev_varenr[')) { #20170628
	$x=1;
	$qtxt="select * from vare_lev where vare_id='$id' order by posnr";
	$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r2=db_fetch_array($q2)) {
		$txt=str_replace('$lev_varenr['.$x.']',$r2['lev_varenr'],$txt);
		$x++;
	}
}
if (strpos($txt,'$variant')) { #20170628
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
$txt=str_replace('$variant',$variant." ".$variant_type,$txt);
$txt=str_replace('$kostpris',$r['kostpris'],$txt);
$txt=str_replace('$img',$img,$txt);
$txt=str_replace('$pris',dkdecimal($salgspris,2),$txt);
$txt=str_replace('$dkkpris',$dkkpris,$txt);
$txt=str_replace('$enhed',$r['enhed'],$txt);
$txt=str_replace('$location','$lokation',$txt); #20170628
if (strpos($txt,'$lokation')) { #20170628
	$qtxt="select lok1 from lagerstatus where vare_id=$id and lager=1";
	$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$txt=str_replace('$lokation',$r2['lok1'],$txt); #20180323
}
$txt=str_replace('$varemrk',$r['trademark'],$txt);
$txt=str_replace('$indhold',$r['indhold'],$txt);
$txt=str_replace('$notes',$r['notes'],$txt);
$txt=str_replace('$special_pris',$special_price,$txt);
$txt=str_replace('$special_fra_dato',dkdato($r['special_from_date']),$txt);
$txt=str_replace('$special_til_dato',dkdato($r['special_to_date']),$txt);
$txt=str_replace('$special_fra_tid',substr($r['special_from_time'],0,5),$txt);
$txt=str_replace('$special_til_tid',substr($r['special_to_time'],0,5),$txt);

$fp=fopen($filename,'w');
fwrite($fp,$txt);
fclose($fp);
?>
