<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- sager/loenIncludes/footer.php --- lap 4.0.8 --- 2023-10-05 ---
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
// Copyright (c) 2003-2023 saldi.dk aps
// ----------------------------------------------------------------------
// 20231005 PHR php8

function fod_log($string)
{
/*
	file_put_contents('/home/mols/logs/fodlog', $string, FILE_APPEND);
*/
}

function fod_laeg_steder_sammen($obj)
{
	$tmpudfaddrs = array_unique($obj->udfaddrs);
	$tmpudftitles = array_unique($obj->udftitles);

	$obj->udfaddr = '';
	foreach($tmpudfaddrs as $tmpudfaddr)
	{
		if ($obj->udfaddr)
			$obj->udfaddr .= ", ";

		$obj->udfaddr .= $tmpudfaddr;
	}

	$obj->udftitle = '';
	foreach($tmpudftitles as $tmpudftitle)
	{
		if ($obj->udftitle)
			$obj->udftitle .= ",\n";

		$obj->udftitle .= $tmpudftitle;
	}
}

function fod_dkdecimal($tal, $visallefelter)
{
	return dkdecimal($tal || $visallefelter ? $tal : "", 2);
}

function fod_felter_laeg_til($destobj, $srcobj)
{
	$destobj->akk_loen += $srcobj->akk_loen;
	$destobj->akktimer += $srcobj->akktimer;
	$destobj->ansat_sum += $srcobj->ansat_sum;
	$destobj->antal_100pct += $srcobj->antal_100pct;
	$destobj->antal_50pct += $srcobj->antal_50pct;
	$destobj->antal_barn_syg += $srcobj->antal_barn_syg;
	$destobj->antal_plads += $srcobj->antal_plads;
	$destobj->antal_skole += $srcobj->antal_skole;
	$destobj->antal_sygdom += $srcobj->antal_sygdom;
	$destobj->barn_syg += $srcobj->barn_syg;
	$destobj->dt_timer += $srcobj->dt_timer;
	$destobj->korsel_km += $srcobj->korsel_km;
	$destobj->korsel_kr += $srcobj->korsel_kr;
	$destobj->loen_100pct += $srcobj->loen_100pct;
	$destobj->loen_50pct += $srcobj->loen_50pct;
	$destobj->loen_dyrtid += $srcobj->loen_dyrtid;
	$destobj->loen_timeant += $srcobj->loen_timeant;
	$destobj->loen_timer += $srcobj->loen_timer;
	$destobj->mentor += $srcobj->mentor;
	$destobj->plads += $srcobj->plads;
	$destobj->skole += $srcobj->skole;
	$destobj->skur1 += $srcobj->skur1;
	$destobj->skur2 += $srcobj->skur2;
	$destobj->sygdom += $srcobj->sygdom;
	$destobj->timeant50 += $srcobj->timeant50;
	$destobj->trainee += $srcobj->trainee;
	$destobj->tr_akktimer += $srcobj->tr_akktimer;
}

function fod_felter_nulstil($obj)
{
	$obj->akk_loen = 0;
	$obj->akktimer = 0;
	$obj->ansat_sum = 0;
	$obj->antal_100pct = 0;
	$obj->antal_50pct = 0;
	$obj->antal_barn_syg = 0;
	$obj->antal_mentor = 0;
	$obj->antal_plads = 0;
	$obj->antal_skole = 0;
	$obj->antal_sygdom = 0;
	$obj->barn_syg = 0;
	$obj->dt_timer = 0;
	$obj->korsel_km = 0;
	$obj->korsel_kr = 0;
	$obj->loen_100pct = 0;
	$obj->loen_50pct = 0;
	$obj->loen_dyrtid = 0;
	$obj->loen_timeant = 0;
	$obj->loen_timer = 0;
	$obj->mentor = 0;
	$obj->plads = 0;
	$obj->skole = 0;
	$obj->skur1 = 0;
	$obj->skur2 = 0;
	$obj->sygdom = 0;
	$obj->timeant50 = 0;
	$obj->trainee = 0;
	$obj->tr_akktimer = 0;
}

function fod_felter_vis_ansat($fodansat, $periode, $vis) {
	print "<td class=\"tableSagerEllipsis\" style=\"max-width:190px;\"><a href=\"loen.php?funktion=loenafregning&amp;ansat_id=$fodansat->id&amp;periode=$periode&amp;vis=$vis\" title=\"$fodansat->navn\">".str_replace(" ","&nbsp;",$fodansat->navn)."&nbsp;</a></td>\n";
}

function fod_felter_vis_dato($dato) {
	print "<td style=\"white-space:nowrap;\">".dkdato($dato)."&nbsp;</td>";
}

function fod_felter_vis_sted($obj, $bold) {
	$startbold = $bold?"<b>":"";
	$endbold = $bold?"</b>":"";

	$udfaddr = isset($obj->udfaddr)?$obj->udfaddr:null;
	$udftitle = isset($obj->udftitle)?$obj->udftitle:null;

	print "<td title=\"$udftitle\">$startbold$udfaddr&nbsp;$endbold</td>";
}

function fod_felter_vis_titel($titel, $bold) {
	$startbold = $bold?"<b>":"";
	$endbold = $bold?"</b>":"";
	print "<td>$startbold$titel$endbold</td>";
}

function fod_felter_vis_beloeb($obj, $visallefelter, $bold) {
	$startbold = $bold?"<b>":"";
	$endbold = $bold?"</b>":"";

	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->loen_timeant, $visallefelter)."\">
	$startbold".fod_dkdecimal($obj->loen_timer, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" 
	title=\"Sum: ".fod_dkdecimal($obj->akktimer, $visallefelter)."|".fod_dkdecimal($obj->dt_timer, $visallefelter)."\">
	$startbold".fod_dkdecimal($obj->loen_dyrtid, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" 
	title=\"Sum: ".fod_dkdecimal($obj->antal_50pct, $visallefelter)."\">
	$startbold".fod_dkdecimal($obj->loen_50pct, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->antal_100pct, $visallefelter)."\">
	$startbold".fod_dkdecimal($obj->loen_100pct, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Timer: ".fod_dkdecimal($obj->akktimer, $visallefelter)."\">
	$startbold".fod_dkdecimal($obj->akk_loen, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Km: ".fod_dkdecimal($obj->korsel_km, $visallefelter)."\">
	$startbold".fod_dkdecimal($obj->korsel_kr, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->skur1, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->skur2, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->mentor, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->antal_plads, $visallefelter)."\">
	$startbold".fod_dkdecimal($obj->plads, $visallefelter)."&nbsp;$endbold</td>";
#	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->antal_mentor, $visallefelter)."\">
#	$startbold".fod_dkdecimal($obj->mentor, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->antal_sygdom, $visallefelter)."\">
	$startbold".fod_dkdecimal($obj->sygdom, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->antal_barn_syg, $visallefelter)."\">
	$startbold".fod_dkdecimal($obj->barn_syg, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->antal_skole, $visallefelter)."\">
	$startbold".fod_dkdecimal($obj->skole, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->ansat_sum, $visallefelter)."&nbsp;$endbold</td>";
}

function fod_felter_vis_timer($obj, $visallefelter, $bold) {
	$startbold = $bold?"<b>":"";
	$endbold = $bold?"</b>":"";

	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->loen_timer, $visallefelter)."\">$startbold".fod_dkdecimal($obj->loen_timeant, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->loen_dyrtid, $visallefelter)."\">$startbold".fod_dkdecimal($obj->dt_timer, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->loen_50pct, $visallefelter)."\">$startbold".fod_dkdecimal($obj->antal_50pct, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->loen_100pct, $visallefelter)."\">$startbold".fod_dkdecimal($obj->antal_100pct, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Timer: ".fod_dkdecimal($obj->akktimer, $visallefelter)."\">$startbold".fod_dkdecimal($obj->akk_loen, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Km: ".fod_dkdecimal($obj->korsel_km, $visallefelter)."\">$startbold".fod_dkdecimal($obj->korsel_kr, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->skur1, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->skur2, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->mentor, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->plads, $visallefelter)."\">$startbold".fod_dkdecimal($obj->antal_plads, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->sygdom, $visallefelter)."\">$startbold".fod_dkdecimal($obj->antal_sygdom, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->barn_syg, $visallefelter)."\">$startbold".fod_dkdecimal($obj->antal_barn_syg, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->skole, $visallefelter)."\">$startbold".fod_dkdecimal($obj->antal_skole, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->ansat_sum, $visallefelter)."&nbsp;$endbold</td>";
}

function fod_indsaml_data($periode) {
// 	udfaddrs : udførelsesadresse

	global $overtid_50pct;
	global $overtid_100pct;

	$funcstarttime = microtime(true);

	fod_log('fod_indsaml_data:' . "\n");

	$predatoer = null;

	$alle_ansatte_id =  NULL;
	if (0 && $ansatvalg) {
		$qtxt = "SELECT * FROM ansatte WHERE id = '$ansatvalg'";
	} else {
		$qtxt = "SELECT * FROM ansatte ORDER BY navn";
	}

	$ansat_navn_by_id = array();

	$x = 0;
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ansatte_id[$x] = $r['id'];
		$ansat_navn_by_id[$r['id']] = $r['navn'];
		$x++;
	}

	$x = 0;
	$r = db_fetch_array(db_select("select box4 from grupper where art='loen'",__FILE__ . " linje " . __LINE__));
	$p_start[$x] = strtotime($r['box4']);
	while ($p_start[$x] <= date("U")+1209600) {
		$x++;
		$p_start[$x] = $p_start[$x-1]+1209600;
	}

	if (!$periode)
		$periode = $p_start[$x-2];

	$startdate = date("Y-m-d",$periode);
	$slutdate = date("Y-m-d",$periode+1209600);

	fod_log("startdate: " . $startdate . "\n");

	$x = 0;

	$tmp = $periode;
	for ($d = 0;$d<14;$d++) {
		$datoliste[$d] = date("Y-m-d",$tmp);
		$tmp += 86400;
	}
	$x = 0;
	$y = 0;
	$pre_d = array();
	$post_d = array();
	# 20130604 tilføjet: and afvist<'1'
	$qtxt = "SELECT * FROM loen WHERE ";
	$qtxt.= "art='akk_afr' and loendate>='$startdate' and loendate<'$slutdate' and ";
	$qtxt.= "godkendt>='1' and (afvist<'1' or afvist is NULL)";
	if (count($ansatte_id)==1) $qtxt.=" and ansatte like '%$ansatte_id[0]%'";
	$qtxt.=" order by loendate";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ad = array();
		$datoer[$x] = $r['datoer'];
		$ad = explode(chr(9),$datoer[$x]);
		for ($d = 0;$d<count($ad);$d++) {
			if ($ad[$d] && $ad[$d]<$startdate && (!in_array($ad[$d],$pre_d))) {
				$pre_d[$x] = $ad[$d];
				$x++;
			}
		}
		for ($d = 0;$d<count($ad);$d++) {
			if ($ad[$d] && $ad[$d]>=$slutdate && (!in_array($ad[$d],$post_d))) {
				$post_d[$y] = $ad[$d];
				$y++;
			}
		}
	}
	sort($pre_d);
	sort($post_d);
	for ($d = 0;$d<count($pre_d);$d++) {
		$predatoer.=$pre_d[$d];
		$datoliste[$d] = $pre_d[$d];
#cho "D1 $datoliste[$d]<br>";
	}
	$tmp = $periode;
	for ($d = count($pre_d);$d<14+count($pre_d);$d++) {
		$datoliste[$d] = date("Y-m-d",$tmp);
#cho "D2 $datoliste[$d]<br>";
		$tmp += 86400;
	}
	
	$a = count($datoliste);
	$b = count($datoliste)+count($post_d);
	for ($d = $a;$d<$b;$d++) {
		$postdatoer.=$post_d[$d-$a];
		$datoliste[$d] = $post_d[$d-$a];
#cho "D3 $datoliste[$d]<br>";
	}

	$foddage = $loen_id = $sag_id = array();

	$datoantal = count($datoliste);

	# 20130228 tilføjet: or art='akktimer'
	# 20130604 tilføjet: and afvist<'1'
	$x = 0;
	$qtxt = "SELECT id FROM loen";
	$qtxt.=" WHERE (art='akk_afr' or art='akktimer' or art='akkord' or art='timer' ";
	$qtxt.=" or art='plads' or art='sygdom' or art='barn_syg' or art='skole')";
	$qtxt.=" and loendate>='$startdate' and loendate<'$slutdate' and godkendt>='1' and (afvist<'1' or afvist is NULL)";

	if (count($ansatte_id)==1)
		$qtxt.=" and ansatte like '%$ansatte_id[0]%'";

	$qtxt.=" order by loendate";
	//if(something) $qtxt = "SELECT * FROM loen WHERE id='0'"

	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);

	while ($r = db_fetch_array($q)) {
		$loen_id[$x] = $r['id'];
		$x++;
	}

	fod_log('count($loen_id): ' . count($loen_id) . "\n");

	list($l_enh_id, $l_akkord) = fod_indsaml_loenenheder_gammel();

	fod_log('count($l_akkord): ' . count($l_akkord) . "\n");

	$x = 0;
	$qtxt = "SELECT * FROM loen";
	$qtxt.=" WHERE (art='akk_afr' or art='akktimer' or art='akkord' or art='timer' or art='plads' or art='sygdom' or art='barn_syg' or art='skole')";
	$qtxt.=" and loendate>='$startdate' and loendate<'$slutdate' and godkendt>='1' and (afvist<'1' or afvist is NULL)";
	if (count($ansatte_id)==1) $qtxt.=" and ansatte like '%$ansatte_id[0]%'";
	$qtxt.=" order by loendate";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		fod_log(
			'loenloop id ' . $r['id'] . "\n" . 
			'   sag_id: ' . $r['sag_id']  . "\n" .
			'   sag_nr: ' . $r['sag_nr']  . "\n" .
			'   opg_id: ' . $r['opg_id']  . "\n" .
			'   opg_nr: ' . $r['opg_nr']  . "\n" .
			'   loendate: ' . $r['loendate'] . "\n" .
			'   sum: ' . $r['sum'] . "\n" .
			'   ansatte: ' . $r['ansatte'] . "\n" .
			'   fordeling: ' . $r['fordeling'] . "\n" .
			'   kategori: ' . $r['kategori'] . "\n" .
			'   datoer: ' . $r['datoer'] . "\n");

		$ad = array();
		$ac = array();
		$af = array();
		$am = array();
		$ans = array();
		$afregnet = $r['afregnet'];
		$afregnet_af = $r['afregnet_af'];
		$loen_id[$x] = $r['id'];
		$loen_nr[$x] = $r['nummer']*1;
#cho "No: $loen_nr[$x]<br>";
		$loen_tekst[$x] = $r['tekst'];
		$fordeling[$x] = $r['fordeling'];
		$loen_art[$x] = $r['art'];
		$hvem[$x] = $r['hvem'];
		$sag_id[$x] = $r['sag_id']*1;
		$sag_nr[$x] = $r['sag_nr']*1;
		$fod_opg_id[$x] = $r['opg_id']*1;
		$opg_nr[$x] = $r['opg_nr']*1;
		$mentor[$x] = $r['mentor'];
		$loendato[$x] = dkdato($r['loendate']);
		($r['oprettet'])?$oprettet[$x] = $r['oprettet']:$oprettet[$x] = '';
		($r['afsluttet'])?$afsluttet[$x] = $r['afsluttet']:$afsluttet[$x] = '';
		($r['godkendt'])?$godkendt[$x] = $r['godkendt']:$godkendt[$x] = '';
		($r['afvist'])?$afvist[$x] = $r['afvist']:$afvist[$x] = '';
#cho "GS $godkendt[$x] $sag_nr[$x]<br>";
		$sum[$x] = $r['sum'];
		$ansatte[$x] = $r['ansatte'];
		$fordeling[$x] = $r['fordeling'];
		$kategori[$x] = $r['kategori'];
#		($predatoer)?$datoer[$x] = $predatoer.chr(9).$r['datoer']:$datoer[$x] = $r['datoer'];
		$datoer[$x] = $r['datoer'];
		$ans = explode(chr(9),$ansatte[$x]);
		//print_r($ans);
#cho "ansatte: $ansatte[$x]<br>";
		
		
#cho "ans $ans[0] $ansatte[$x]<br>";
		$aa = explode(chr(9),$r['loen']);
		$ad = explode(chr(9),$datoer[$x]);
#	for ($d = 0;$d<count($ad);$d++) {
#cho ", $ad[$d]";
#	}
#cho "<br>";
		$at = explode(chr(9),$r['timer']);
		$af = explode(chr(9),$fordeling[$x]);
		$am = explode(chr(9),$mentor[$x]);
		#cho "AT".$r['timer']."<br>";
		$a50=explode(chr(9),$r['t50pct']);
		$a100=explode(chr(9),$r['t100pct']);
		$loentimer[$x] = 0;
		$tr_timer[$x] = 0; #tr=trainee;
		$akkord[$x] = 0;
		for ($y = 0;$y<count($at);$y++) {
			if (!isset($ad[$y])) $ad[$y] = $r['loendate'];
			$loentimer[$x] += $at[$y];
		}
		list($km,$km_sats,$km_fra)=explode("|",$r['korsel']);
		$ak = explode(chr(9),$km);
		list($s1,$s2)=explode("|",$r['skur']);
		$as1=explode(chr(9),$s1);
		$as2=explode(chr(9),$s2);
		$am = explode(chr(9),$r['mentor']);
		$mentorRate = (float)$r['mentor_rate'];
		$timer[$x] = 0;
		$akkord[$x] = 0;
		$telt = 0;
		$teltsum = 0;
		
		if (isset($l_enh_id)) {
			for ($l = 1;$l<=count($l_enh_id);$l++) {
				if (isset($loen_id[$x]) && isset($l_akkord[$l]) && $loen_id[$x]==$l_enh_id[$l])
					$akkord[$x] += $l_akkord[$l];
			}
		}
		if ($loentimer[$x]) $akk_timeloen[$x] = $akkord[$x]/$loentimer[$x];
		$trSum[$x] = 0;
		$fordelingssum[$x] = 0;
		$fordelingstimer[$x] = 0;
		for ($y = 0;$y<count($at);$y++) {
			if ($af[$y]!=100) {
				$tr_timer[$x] += $at[$y];
				$trtl = $akk_timeloen[$x]*80/100;
				$trSum[$x] += $trtl*$at[$y];
				$fordelingssum[$x] += $at[$y]*($akk_timeloen[$x]-$trtl);
				$fordelingstimer[$x] = $tr_timer[$x]*0.4+($loentimer[$x]-$tr_timer[$x])*0.6;
			}
			$am[$y] = (float)if_isset($am[$y],0);
			$am[$y]*= $mentorRate;
		}
		for ($c = 0;$c<count($ans);$c++) {
			if (!$ad[$c]) $ad[$c] = $r['loendate'];
			$tmptrainee = 0;
			$fodtmpakktimer = 0;
			$fodtmpkorsel_km = 0;

			if (!array_key_exists($ad[$c], $foddage)) {
				$foddage[$ad[$c]] = new stdClass();
				$foddage[$ad[$c]]->ansatte = array();
			}

			$foddag = $foddage[$ad[$c]];

			if (!array_key_exists($ans[$c], $foddag->ansatte)) {
				$foddag->ansatte[$ans[$c]] = new stdClass();
				$foddag->ansatte[$ans[$c]]->sager = array();
				$foddag->ansatte[$ans[$c]]->id = $ans[$c];
				$foddag->ansatte[$ans[$c]]->navn = $ansat_navn_by_id[$ans[$c]];
			}

			$fodansat = $foddag->ansatte[$ans[$c]];

			if (!array_key_exists($sag_id[$x], $fodansat->sager)) {
				$fodansat->sager[$sag_id[$x]] = new stdClass();
				$fodansat->sager[$sag_id[$x]]->opgaver = array();
				$fodansat->sager[$sag_id[$x]]->sag_nr = $sag_nr[$x];
			}

			$fodsag = $fodansat->sager[$sag_id[$x]];

			if (!array_key_exists($fod_opg_id[$x], $fodsag->opgaver)) {
				$fodsag->opgaver[$fod_opg_id[$x]] = new stdClass();
				$fodsag->opgaver[$fod_opg_id[$x]]->opg_nr = $opg_nr[$x];
				$fodsag->opgaver[$fod_opg_id[$x]]->udfaddrs = array();
				$fodsag->opgaver[$fod_opg_id[$x]]->udftitles = array();

				$fodopgave = $fodsag->opgaver[$fod_opg_id[$x]];
				fod_felter_nulstil($fodopgave);
			}

			$fodopgave = $fodsag->opgaver[$fod_opg_id[$x]];

			if ($sag_id[$x] == 0) {
				array_push($fodopgave->udfaddrs, $loen_art[$x]);
				array_push($fodopgave->udftitles, $loen_art[$x]);
			}

			if ($loen_art[$x]=='akktimer' || $loen_art[$x]=='akkord') {
				$fodopgave->loen_dyrtid += $aa[$c]*$at[$c];
				$fodopgave->dt_timer += $at[$c];
				$fodopgave->loen_50pct += $overtid_50pct*$a50[$c];
				$fodopgave->loen_100pct += $overtid_100pct*$a100[$c];
				$fodopgave->antal_50pct += $a50[$c];
				$fodopgave->antal_100pct += $a100[$c];
				$fodopgave->timeant50 += $at[$c];
				$fodopgave->mentor += $am[$c];
			}
			if ($loen_art[$x]=='akk_afr' || $loen_art[$x]=='akkord') { #20130305  Ændret elseif til if og tilføjet: "|| $loen_art[$x]=='akkord'"
				$fodtmpakktimer += $at[$c];

				if($af[$c]<100) {
					$fodopgave->tr_akktimer += $at[$c];
					$fodopgave->trainee = 1;
					$tmptrainee = 1;
				}
			} elseif ($loen_art[$x]=='timer') {
				$fodopgave->loen_timeant += $at[$c];
				$fodopgave->loen_timer += $aa[$c]*$at[$c];
				$fodopgave->loen_50pct += $overtid_50pct*$a50[$c];
				$fodopgave->loen_100pct += $overtid_100pct*$a100[$c];
				$fodopgave->antal_50pct += $a50[$c];
				$fodopgave->antal_100pct += $a100[$c];
				$fodopgave->mentor += $am[$c];
			} elseif ($loen_art[$x]=='plads') {
				$fodopgave->plads += $aa[$c]*$at[$c];
				$fodopgave->antal_plads += $at[$c];
			} elseif ($loen_art[$x]=='sygdom') {
				$fodopgave->sygdom += $aa[$c]*$at[$c];
				$fodopgave->antal_sygdom += $at[$c];
			} elseif ($loen_art[$x]=='barn_syg') {
				$fodopgave->barn_syg += $aa[$c]*$at[$c];
				$fodopgave->antal_barn_syg += $at[$c];
			} elseif ($loen_art[$x]=='skole') {
				$fodopgave->skole += $aa[$c]*$at[$c];
				$fodopgave->antal_skole += $at[$c];
			} if ($loen_art[$x]!='akk_afr') {
				$fodtmpkorsel_km += $ak[$c];
				$fodopgave->skur1 += $as1[$c];
				$fodopgave->skur2 += $as2[$c];
#				$fodopgave->mentor += $am[$c]*$at[$c];
			}
			$fodopgave->akktimer += $fodtmpakktimer;

			if ($tmptrainee){
				$fodopgave->akk_loen += ($akkord[$x]/$fordelingstimer[$x])*0.4*$fodtmpakktimer;
			} elseif ($fordelingstimer[$x]) {
				$fodopgave->akk_loen += ($akkord[$x]/$fordelingstimer[$x])*0.6*$fodtmpakktimer;
			} else {
				if ($loentimer[$x]) $fodopgave->akk_loen += $akkord[$x]*$fodtmpakktimer/$loentimer[$x];
			}

			if ($fodtmpkorsel_km<$km_fra) {
				$fodtmpkorsel_km = 0;
				$fodtmpkorsel_kr = 0;
			} else {
				$fodtmpkorsel_km-=$km_fra;
				$fodtmpkorsel_kr = $fodtmpkorsel_km*$km_sats;
			}

			$fodopgave->korsel_km += $fodtmpkorsel_km;
			$fodopgave->korsel_kr += $fodtmpkorsel_kr;
		}

		$x++;
	}

	$fodtotal = new stdClass();
	fod_felter_nulstil($fodtotal);

	$fodtotal->dage = $foddage;

	foreach($foddage as $foddagid => $foddag) {
		fod_felter_nulstil($foddag);

		foreach($foddag->ansatte as $fodansatid => $fodansat) {
			fod_felter_nulstil($fodansat);

			foreach($fodansat->sager as $fodsagid => $fodsag) {
				fod_felter_nulstil($fodsag);
				$fodsag->udfaddrs = array();
				$fodsag->udftitles = array();

				foreach($fodsag->opgaver as $fodopgaveid => $fodopgave) {
					$fodopgave->ansat_sum = $fodopgave->loen_timer + $fodopgave->loen_dyrtid + $fodopgave->loen_50pct + $fodopgave->loen_100pct + $fodopgave->akk_loen + $fodopgave->skur1 + $fodopgave->skur2  + $fodopgave->mentor + $fodopgave->plads + $fodopgave->sygdom + $fodopgave->barn_syg + $fodopgave->skole + $fodopgave->korsel_kr;

					if ($fodsagid != 0) {
						$r = db_fetch_array(db_select("SELECT udf_addr1 FROM sager WHERE id='$fodsagid'", __FILE__ . " linje " . __LINE__));
						array_push($fodopgave->udfaddrs, htmlspecialchars($r['udf_addr1']));
						array_push($fodopgave->udftitles, "Sag: $fodsag->sag_nr - Opgave: $fodopgave->opg_nr - " . htmlspecialchars($r['udf_addr1']));
					}

					$fodsag->udfaddrs = array_merge($fodsag->udfaddrs, $fodopgave->udfaddrs);
					$fodsag->udftitles = array_merge($fodsag->udftitles, $fodopgave->udftitles);

					fod_laeg_steder_sammen($fodopgave);
					fod_felter_laeg_til($fodsag, $fodopgave);
				}

				fod_laeg_steder_sammen($fodsag);
				fod_felter_laeg_til($fodansat, $fodsag);
			}

			fod_felter_laeg_til($foddag, $fodansat);
		}

		fod_felter_laeg_til($fodtotal, $foddag);
	}

	fod_log('count($sag_id): ' . count($sag_id) . "\n");
	for($fod=0;$fod<count($sag_id);$fod++) {
		fod_log('   $sag_id: ' . $sag_id[$fod] . "\n");
	}

	foreach($foddage as $foddagid => $foddag) {
		fod_log('dag ' . $foddagid . ':' . "\n");

		foreach($foddag->ansatte as $fodansatid => $fodansat) {
			fod_log('   fodansat ' . $fodansatid . ':' . "\n");

			fod_log('      navn: ' . $fodansat->navn . "\n");
			fod_log('      loen_dyrtid: ' . $fodansat->loen_dyrtid . "\n");

			foreach($fodansat->sager as $fodsagid => $fodsag) {
				fod_log('      sag ' . $fodsagid . ':' . "\n");

				fod_log('         sag_nr: ' . $fodsag->sag_nr . "\n");

				foreach($fodsag->opgaver as $fodopgaveid => $fodopgave) {
					fod_log('         opgave ' . $fodopgaveid . ':' . "\n");

					fod_log('            opg_nr: ' . $fodopgave->opg_nr . "\n");

					fod_log('            loen_dyrtid: ' . $fodopgave->loen_dyrtid . "\n");
					fod_log('            udfaddr: ' . $fodopgave->udfaddr . "\n");
					fod_log('            udftitle: ' . $fodopgave->udftitle . "\n");
				}
			}
		}
	}

	$funcendtime = microtime(true);

	fod_log('fod_indsaml_data: end: ' . ($funcendtime - $funcstarttime) . ' seconds' . "\n");

	return $fodtotal;
}

function fod_indsaml_loenenheder_gammel()
{
	$funcstarttime = microtime(true);

	$l_enh_id = array();
	$l_akkord = array();
	$l = 0;
	$q2=db_select("SELECT * FROM loen_enheder order by loen_id", __FILE__ . " linje " . __LINE__);

	fod_log('fod_indsaml_loenenheder_gammel: query done in: ' . (microtime(true) - $funcstarttime) . ' seconds' . "\n");

	while ($r2 = db_fetch_array($q2))
	{
		if ($l && isset($r2['loen_id']) && isset($l_enh_id[$l]) && $r2['loen_id']==$l_enh_id[$l])
		{
			$l_akkord[$l] += $r2['op']      * $r2['pris_op'];
			$l_akkord[$l] += $r2['op_25']   * $r2['pris_op']  * 0.25;
			$l_akkord[$l] += $r2['op_30']   * $r2['pris_op']  * 0.3;
			$l_akkord[$l] += $r2['op_40']   * $r2['pris_op']  * 0.4;
			$l_akkord[$l] += $r2['op_60']   * $r2['pris_op']  * 0.6;
			$l_akkord[$l] += $r2['op_70']   * $r2['pris_op']  * 0.7;
			$l_akkord[$l] += $r2['op_100']  * $r2['pris_op']  * 1;
			$l_akkord[$l] += $r2['op_160']  * $r2['pris_op']  * 1.6;
			$l_akkord[$l] += $r2['op_30m']  * $r2['pris_op']  * 0.1;
			$l_akkord[$l] += $r2['ned']     * $r2['pris_ned'];
			$l_akkord[$l] += $r2['ned_25']  * $r2['pris_ned'] * 0.25;
			$l_akkord[$l] += $r2['ned_30']  * $r2['pris_ned'] * 0.3;
			$l_akkord[$l] += $r2['ned_40']  * $r2['pris_ned'] * 0.4;
			$l_akkord[$l] += $r2['ned_60']  * $r2['pris_ned'] * 0.6;
			$l_akkord[$l] += $r2['ned_70']  * $r2['pris_ned'] * 0.7;
			$l_akkord[$l] += $r2['ned_100'] * $r2['pris_ned'] * 1;
			$l_akkord[$l] += $r2['ned_160'] * $r2['pris_ned'] * 1.6;
			$l_akkord[$l] += $r2['ned_30m'] * $r2['pris_ned'] * 0.1;
		}
		else
		{
			$l++;
			$l_enh_id[$l] = $r2['loen_id'];
			$l_akkord[$l] = $r2['op']        * $r2['pris_op'];
			$l_akkord[$l] += ($r2['op_25']   * $r2['pris_op']  * 0.25);
			$l_akkord[$l] += ($r2['op_30']   * $r2['pris_op']  * 0.3);
			$l_akkord[$l] += ($r2['op_40']   * $r2['pris_op']  * 0.4);
			$l_akkord[$l] += ($r2['op_60']   * $r2['pris_op']  * 0.6);
			$l_akkord[$l] += ($r2['op_70']   * $r2['pris_op']  * 0.7);
			$l_akkord[$l] += ($r2['op_100']  * $r2['pris_op']  * 1);
			$l_akkord[$l] += ($r2['op_160']  * $r2['pris_op']  * 1.6);
			$l_akkord[$l] += ($r2['op_30m']  * $r2['pris_op']  * 0.1);
			$l_akkord[$l] += $r2['ned']      * $r2['pris_ned'];
			$l_akkord[$l] += ($r2['ned_25']  * $r2['pris_ned'] * 0.25);
			$l_akkord[$l] += ($r2['ned_30']  * $r2['pris_ned'] * 0.3);
			$l_akkord[$l] += ($r2['ned_40']  * $r2['pris_ned'] * 0.4);
			$l_akkord[$l] += ($r2['ned_60']  * $r2['pris_ned'] * 0.6);
			$l_akkord[$l] += ($r2['ned_70']  * $r2['pris_ned'] * 0.7);
			$l_akkord[$l] += ($r2['ned_100'] * $r2['pris_ned'] * 1);
			$l_akkord[$l] += ($r2['ned_160'] * $r2['pris_ned'] * 1.6);
			$l_akkord[$l] += ($r2['ned_30m'] * $r2['pris_ned'] * 0.1);
		}
	}

	$funcendtime = microtime(true);

	fod_log('fod_indsaml_loenenheder_gammel: end: ' . ($funcendtime - $funcstarttime) . ' seconds' . "\n");

	return array($l_enh_id, $l_akkord);
}
?>
