<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/posOptions.php --- ver 4.1.0 -- 2025-05-03 ---
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
// Copyright (c) 2003-2025 Saldi.DK ApS
// -----------------------------------------------------------------------
// Kaldes fra systemdata/diverse.php
// 20131230 PHR addad fiscal year to groups.
// 20250503 LOE reordered mix-up text_id from tekster.csv in findtekst()

function posOptions () {
	global $bgcolor,$bgcolor5;
	global $regnaar;
	global $sprog_id;
	$postEachSale = $change_cardvalue = $deactivateBonprint = null ;         #20211022
	$kassekonti=array();
	$afd=array();

	$id=$kasseantal=$kortantal=$rabatvareid=$timeout=0;
	$rabatvarenr=$straksbogfor=$udskriv_bon=$vis_hurtigknap=$vis_indbetaling=$vis_kontoopslag=0;
	$afd=$bord=$kassekonti=$kortkonti=$korttyper=$moms=$ValutaKode=array();
	$qtxt = "select * from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$id1         = $r['id'];
		$kasseantal  = $r['box1'];
		$kassekonti  = explode(chr(9),$r['box2']);
		$afd         = explode(chr(9),$r['box3']);
		$kortantal   = $r['box4']*1;
		$korttyper   = explode(chr(9),$r['box5']);
		$kortkonti   = explode(chr(9),$r['box6']);
		$moms        = explode(chr(9),$r['box7']);
		$rabatvareid = $r['box8'];
		($r['box9'])?$straksbogfor='checked':$straksbogfor='';
		($r['box10'])?$udskriv_bon='checked':$udskriv_bon='';
		($r['box11'])?$vis_kontoopslag='checked':$vis_kontoopslag='';
		($r['box12'])?$vis_hurtigknap='checked':$vis_hurtigknap='';
		$timeout=$r['box13'];
		($r['box14'])?$vis_indbetaling='checked':$vis_indbetaling='';
		if (!$kasseantal)  $kasseantal  = 0;
		if (!$rabatvareid) $rabatvareid = 0; 
		if (!$timeout)     $timeout     = 0;
	}
	$qtxt = "select * from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$id2=$r['id'];
	} else {
		$qtxt = "insert into grupper (beskrivelse,kode,kodenr,art,box1,box2,box3,box4,box5,box6,box7,";
		$qtxt.= "box8,box9,box10,box11,box12,box13,box14,fiscal_year) ";
		$qtxt.= "values ('Pos valg','','2','POS','0','','','','','','','','','','','','','','$regnaar')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "select * from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$id2=$r['id'];
	}
	$kasseprimo=dkdecimal($r['box1']);
	($r['box2'])?$optalassist='checked':$optalassist=NULL;
	$printer_ip=explode(chr(9),$r['box3']);
	$terminal_ip=explode(chr(9),$r['box4']);
	$betalingskort=explode(chr(9),$r['box5']); #20131210
	$div_kort_kto=$r['box6']; #20140129
	if ($r['box7']) $bord=explode(chr(9),str_replace("\n","  ",$r['box7'])); #20140506
	$mellemkonti=explode(chr(9),$r['box8']);
	$diffkonti=explode(chr(9),$r['box9']);
	$koekkenprinter=explode(chr(9),$r['box10']);
	$vare_id=$r['box11'];
	($r['box12'])?$vis_saet='checked':$vis_saet='';
	$bordvalg=explode(chr(9),$r['box13']);
	($r['box14'])?$udtag0='checked':$udtag0=NULL;
	if ($r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '3' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__))) {
		$id3=$r['id'];
	} else {
		$qtxt = "insert into grupper(beskrivelse,kode,kodenr,art,box1,box2,box3,box4,box5,box6,box7";
		$qtxt.= ",box8,box9,box10,box11,box12,box13,box14,fiscal_year)";
		$qtxt.= "values ('Pos valg','','3','POS','0','10','','','','','','','','','','','','','$regnaar')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '3' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__));
		$id3=$r['id'];
	}
	($r['box1'])?$brugervalg='checked':$brugervalg=NULL;
	$pfs=explode(chr(9),$r['box2']);
	($r['box3'])?$kundedisplay='checked':$kundedisplay=NULL;
	$voucher=explode(chr(9),$r['box4']); #20181029
	$vouchertext=explode(chr(9),$r['box5']); #20181029

	$qtxt="select var_value from settings where var_name='card_enabled'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$card_enabled=$r['var_value'];
	$enabled=explode(chr(9),$card_enabled); #20181215

	$qtxt="select var_value from settings where var_name='change_cardvalue'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if($r){ #20211022
	($r['var_value'])?$change_cardvalue='checked':$change_cardvalue=NULL;
	}
	$qtxt="select var_value from settings where var_name='voucherItems'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if($r){#20211022
	$voucherItemId=explode(chr(9),$r['var_value']);
    
		for($x=0;$x<count($voucherItemId);$x++) {
			$voucherItemId[$x] = (int)$voucherItemId[$x];
			if ($voucherItemId[$x]) {
				$qtxt="select varenr from varer where id = '$voucherItemId[$x]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$voucherItemNo[$x]=$r['varenr'];
			} else $voucherItemNo[$x]='';
		}
	}
	$qtxt="select var_value from settings where var_name='deactivateBonprint'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	($r && $r['var_value'])?$deactivateBonprint='checked':$deactivateBonprint=NULL;
	$qtxt="select var_value from settings where var_name='postEachSale'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$postEachSale=explode(chr(9),str_replace('on','checked',$r['var_value']));
	} elseif ($straksbogfor) {
		for ($x=0;$x<count($kassekonti);$x++) {
			$postEachSale[$x]=$straksbogfor;
		}
	}
	$qtxt="select var_value from settings where var_name='jump2price'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	($r && $r['var_value'])?$jump2price='checked':$jump2price=NULL;

	$lagerbeh = get_settings_value("show_stock", "POS", "off");
	$lagerbeh = $lagerbeh == "on" ? "checked" : "";

	$kdsactive = get_settings_value("activated", "KDS", "off");
	$kdsactive = $kdsactive == "on" ? "checked" : "";

	$printactive = get_settings_value("activated", "kitchen-print", "on");
	$printactive = $printactive == "on" ? "checked" : "";

	$kdscolumns = get_settings_value("columns", "KDS", 5);
	$kdsheight = get_settings_value("height", "KDS", 20);


	/*
	$posbuttons=0;
	$q = db_select("select * from grupper where art = 'POSBUT'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $posbuttons++;
*/
	$x=0;
	$q = db_select("select * from grupper where art = 'VK' order by box1",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ValutaKode[$x]=$r['box1'];
		$ValutaKonti[$x]=explode(chr(9),$r['box4']);
		$ValutaMlKonti[$x]=explode(chr(9),$r['box5']);
		$ValutaDifKonti[$x]=explode(chr(9),$r['box6']);
		$x++;
	}

	if ($vare_id) {
		$r = db_fetch_array(db_select("select varenr from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
		$varenr=$r['varenr'];
	}
	if ($rabatvareid) {
		$r = db_fetch_array(db_select("select varenr from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__));
		$rabatvarenr=$r['varenr'];
	}

	$x=0;
	if ($kasseantal) {
		$q = db_select("select * from grupper where art = 'AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$afd_nr[$x]=$r['kodenr'];
			$afd_navn[$x]=$r['beskrivelse'];
		}
		$afd_antal=$x;
		$x=0;
		$qtxt = "select * from grupper where art = 'SM' and fiscal_year = '$regnaar' order by kodenr";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$moms_nr[$x]=$r['kodenr'];
			$moms_navn[$x]=$r['beskrivelse'];
		}
		$moms_antal=$x;
	}
#	(isset($_COOKIE['saldi_pfs']))?$pfs=$_COOKIE['saldi_pfs']:$pfs=10; #Pos Font Size

	print "<form name=diverse action=diverse.php?sektion=posOptions method=post>\n";
	print "<tr><td width='100%'><hr></td></tr>\n";
	print "<tr><td><table><tbody>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(265,$sprog_id)."</u></b></td></tr>\n";
	print "<tr><td colspan='6'><br></td></tr>\n";
	print "<input type=hidden name=id1 value='$id1'>\n";
	print "<input type=hidden name=id2 value='$id2'>\n";
	print "<input type=hidden name=id3 value='$id3'>\n";
	print "<tr><td title='".findtekst(266,$sprog_id)."'>".findtekst(267,$sprog_id)."</td><td><input class='inputbox' type='text' style='text-align:right;width:70px;' name='kasseantal' value='$kasseantal'></td></tr>\n";
#	print "<tr><td title='".findtekst(285,$sprog_id)."'>".findtekst(285,$sprog_id)."</td>";
	if ($kasseantal) {
#		print "<tr><td title='".findtekst(730,$sprog_id)."'>".findtekst(729,$sprog_id)."</td><td><input class='inputbox' type='text' style='text-align:right;width:70px;' name='varenr' value='$varenr'></td></tr>";
		print "<tr><td title='".findtekst(288,$sprog_id)."'>".findtekst(287,$sprog_id)."</td>";
		print "<td><input class='inputbox' type='text' style='text-align:right;width:70px;' name='rabatvarenr' value='$rabatvarenr'></td>";
		print "</tr>\n";
		print "<tr><td colspan='6'><hr></td></tr>\n";
		print "<tr><td>".findtekst(272,$sprog_id)."</td>\n";
		if ($afd_antal) print "<td title='".findtekst(273,$sprog_id)."'>".findtekst(274,$sprog_id)."<!--Tekst 274--></td>\n";
		if ($moms_antal) print "<td title='".findtekst(285,$sprog_id)."'>".findtekst(286,$sprog_id)."<!--Tekst 286--></td>\n";
		$text=findtekst(276,$sprog_id);
		$title=findtekst(275,$sprog_id);
		print "<td title='$title'><!--Tekst 275-->$text<!--Tekst 276--></td>\n";
		print "<td title='".findtekst(716,$sprog_id)."'><!--Tekst 716-->".findtekst(715,$sprog_id)."<!--Tekst 715--></td>\n";
		print "<td title='".findtekst(722,$sprog_id)."'><!--Tekst 722-->".findtekst(721,$sprog_id)."<!--Tekst 721--></td>\n";
		print "<td title='".findtekst(705,$sprog_id)."'><!--Tekst 705-->".findtekst(704,$sprog_id)."<!--Tekst 704--></td>\n";
		print "<td title='".findtekst(2313,$sprog_id)."'><!--Tekst 2313-->".findtekst(2312,$sprog_id)."<!--Tekst 2312--></td>\n";
		print "<td title='".findtekst(707,$sprog_id)."'><!--Tekst 707-->".findtekst(2316,$sprog_id)."<!--Tekst 2316--></td>\n";
		print "<td title='".findtekst(726,$sprog_id)."'><!--Tekst 726-->".findtekst(725,$sprog_id)."<!--Tekst 725--></td>\n";
		if (count($bord)>1) print "<td title='".findtekst(755,$sprog_id)."'><!--Tekst 755-->".findtekst(754,$sprog_id)."<!--Tekst 754--></td>\n";
		$text=findtekst(765,$sprog_id);
		$title=findtekst(766,$sprog_id);
		if ($text='Font størrelse') {
			$text='Font str.';
			db_modify("update tekster set tekst='$text' where tekst_id='765'",__FILE__ . " linje " . __LINE__);
		}
		print "<td title='$title'><!--Tekst 766-->$text<!--Tekst 765--></td>\n";
		$text='Omg.bogf.';
		$title=findtekst(1728, $sprog_id); #20210802
		print "<td title='$title'><!--Tekst 766-->$text<!--Tekst 765--></td>\n";

#		print "<tr><td colspan='2' title='".findtekst(765,$sprog_id)."'>".findtekst(765,	$sprog_id)."</td><td title='".findtekst(766,$sprog_id)."'><input class='inputbox' type='text' style='text-align:right;width:25px' name='pfs' value='$pfs'></td></tr>\n";

		print "</tr>\n";
		for($x=0;$x<$kasseantal;$x++) {
			if (!$pfs[$x]) $pfs[$x]=10;
			$terminal_type[$x] = if_isset($terminal_type[$x],NULL);
			print "<tr bgcolor=$bgcolor5>";
			$tmp=$x+1;
			print "<td>$tmp</td>";
			if ($afd_antal) {
				print "<td title='".findtekst(273,$sprog_id)."'><SELECT class='inputbox' NAME=afd_nr[$x] title='".findtekst(273,$sprog_id)."'>\n";
				for($y=1;$y<=$afd_antal;$y++) {
					if ($afd[$x]==$afd_nr[$y]) print "<option value='$afd_nr[$y]'>$afd_navn[$y]</option>\n";
				}
				print "<option value='0'></option>";
				for($y=1;$y<=$afd_antal;$y++) {
					if ($afd[$x]!=$afd_nr[$y]) print "<option value='$afd_nr[$y]'>$afd_navn[$y]</option>\n";
				}
				print "</SELECT></td>";
			}
			if ($moms_antal) {
				print "<td title='".findtekst(273,$sprog_id)."'><SELECT class='inputbox' NAME=moms_nr[$x] title='".findtekst(273,$sprog_id)."'>\n";
				for($y=1;$y<=$moms_antal;$y++) {
					if ($moms[$x]==$moms_nr[$y]) print "<option value='$moms_nr[$y]'>$moms_navn[$y]</option>\n";
				}
				print "<option value='0'></option>";
				for($y=1;$y<=$moms_antal;$y++) {
					if ($moms[$x]!=$moms_nr[$y]) print "<option value='$moms_nr[$y]'>$moms_navn[$y]</option>\n";
				}
				print "</SELECT></td>\n";
			}


			
      # Setup options for each POS terminal
			print "<td><input class='inputbox' type='text' style='text-align:right;width:50px;' name='kassekonti[$x]' value='$kassekonti[$x]'></td>\n";
			print "<td><input class='inputbox' type='text' style='text-align:right;width:50px;' name='mellemkonti[$x]' value='$mellemkonti[$x]'></td>\n";
			print "<td><input class='inputbox' type='text' style='text-align:right;width:50px;' name='diffkonti[$x]' value='$diffkonti[$x]'></td>\n";
			if (!$printer_ip[$x])$printer_ip[$x]='localhost';
			print "<td><input class='inputbox' type='text' style='text-align:right;width:70px;' name='printer_ip[$x]' value='$printer_ip[$x]'></td>\n";
      
      # Payment terminal implementation
      print "<td align='center'>
				<select class='inputbox' type='select' style='text-align:right;width:150px;'
				name='terminal_type[$x]' value='$terminal_type[$x]' onchange='type_change($x);'> ";

			$kasse_id = $x + 1;
			$posTermOption = NULL;
			$qtxt = "SELECT var_value FROM settings WHERE pos_id='$kasse_id' and var_name='terminal_type'";
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $posTermOption = $r[0];
			if ($posTermOption == "Ip baseret"){
				print "<option selected='selected' value='Ip baseret'>Ip baseret</option>
					<option value='Flatpay'>Flatpay</option>
					<option value='Move3500'>Move3500</option>
					<option value='Lane3000'>Lane3000</option>
					<option value='Vibrant'>Vibrant</option>";
			} else if ($posTermOption == "Flatpay") {
				print "<option value='Ip baseret'>Ip baseret</option>
					<option selected='selected' value='Flatpay'>Flatpay</option>
					<option value='Move3500'>Move3500</option>
					<option value='Lane3000'>Lane3000</option>
					<option value='Vibrant'>Vibrant</option>";
			} else if ($posTermOption == "Lane3000") {
				print "<option value='Ip baseret'>Ip baseret</option>
					<option value='Flatpay'>Flatpay</option>
					<option value='Move3500'>Move3500</option>
					<option selected='selected' value='Lane3000'>Lane3000</option>
					<option value='Vibrant'>Vibrant</option>";
			} else if ($posTermOption == "Move3500") {
				print "<option value='Ip baseret'>Ip baseret</option>
					<option value='Flatpay'>Flatpay</option>
					<option selected='selected' value='Move3500'>Move3500</option>
					<option value='Lane3000'>Lane3000</option>
					<option value='Vibrant'>Vibrant</option>";
			} else if ($posTermOption == "Vibrant") {
				print "<option value='Ip baseret'>Ip baseret</option>
					<option value='Flatpay'>Flatpay</option>
					<option value='Move3500'>Move3500</option>
					<option value='Lane3000'>Lane3000</option>
					<option selected='selected' value='Vibrant'>Vibrant</option>";
			# Handeled below
			} else {
				print "<option value='Ip baseret'>Ip baseret</option>
					<option value='Flatpay'>Flatpay</option>
					<option value='Move3500'>Move3500</option>
					<option value='Lane3000'>Lane3000</option>
					<option value='Vibrant'>Vibrant</option>";
			}
			print "<option value='t'></option>";
      $i = 0;

      print " </select>
            </td>\n";
			print "<td align='center'><input class='inputbox' type='text' style='text-align:right;width:100px;' name='terminal_ip[$x]' value='$terminal_ip[$x]'></td>\n";
      
      print "<td align='center'><input class='inputbox' type='text' style='text-align:right;width:70px;' name='koekkenprinter[$x]' value='$koekkenprinter[$x]'></td>\n";
      
      
      
      if (count($bord)>1) {
				print "<td align='center'><select class='inputbox' style='width:70px;' name='bordvalg[$x]'>\n";
				if ($bordvalg[$x]) {#print "<option value='$bordvalg[$x]'>$bordvalg[$x] $bord[$x]</option>";
					for ($y=1;$y<=count($bord);$y++) {
						$b=$y+1;
						if ($y==$bordvalg[$x]) print "<option value='$y'>$b $bord[$y]</option>\n";
					}
				}
				print "<option value=''></option>\n";
				for ($y=0;$y<count($bord);$y++) {
 					$b=$y+1;
					if ($y!=$bordvalg[$x]) 
					print "<option value='$y'>
					$b 
					$bord[$y]</option>\n";
				}
				print "</select></td>\n";
			}
			print "<td align='center'><input class='inputbox' type='text' style='text-align:right;width:50px' name='pfs[$x]' value='$pfs[$x]'></td>\n";
			if($postEachSale){ #20211022
			print "<td align='center'><input type='checkbox' name='postEachSale[$x]' $postEachSale[$x]></td>\n";
			}

			print "<td><span id='kasse-$x-ekstra-toggle' style='cursor: pointer' onclick='
				document.getElementById(\"kasse-$x-ekstra-toggle\").innerText = 
					document.getElementById(\"kasse-$x-ekstra-toggle\").innerText === \"∧\" ? \"∨\" : \"∧\";
				document.getElementById(\"kasse-$x-ekstra\").classList.toggle(\"hidden\");
			'>∨</span></td>\n";
			print "</tr>";
			print "<tr><td colspan='50'><table id='kasse-$x-ekstra' class='hidden'>";

			# Folding menu headers
			$text=findtekst(2409, $sprog_id);
			$title=findtekst(2410, $sprog_id);
			print "<tr><td title='$title'><!--Tekst 2410-->$text<!--Tekst 2409--></td>\n";
			$text=findtekst(2411, $sprog_id);
			$title=findtekst(2412, $sprog_id);
			print "<td title='$title'><!--Tekst 2411-->$text<!--Tekst 2412--></td>\n";
			$text=findtekst(2413, $sprog_id);
			$title=findtekst(2414, $sprog_id);
			print "<td title='$title'><!--Tekst 2413-->$text<!--Tekst 2414--></td>\n";
			$text=findtekst(2415, $sprog_id);
			$title=findtekst(2416, $sprog_id);
			print "<td title='$title'><!--Tekst 2413-->$text<!--Tekst 2414--></td>\n";
			print "</tr>";
			print "<tr>";

			# #################
			# Mobile layout
			# #################
			$mobilepos = get_settings_value("mobilepos", "POS", "off", null, $x+1);
			$checked = $mobilepos == "on" ? "checked" : "";
			print "<td align='center'><input type='checkbox' name='mobilpos[$x]' $checked></td>\n";
			if ($mobilepos == "on") {
				$width = get_settings_value("mobilwidth", "POS", "510", null, $x+1);
				print "<td align='center'><input type='text' name='mobilwidth[$x]' style='width: 40px;' value='$width'></td>\n";
				$zoom = dkdecimal(get_settings_value("mobilzoom", "POS", "1.0", null, $x+1));
				print "<td align='center'><input type='text' name='mobilzoom[$x]' style='width: 40px;' value='$zoom'></td>\n";
			} else {
				print "<td></td><td></td>";
			}
			# Flipped menu setup
			$omv_menu = get_settings_value("omv_menu", "POS", "off", null, $x+1);
			$checked = $omv_menu == "on" ? "checked" : "";
			print "<td align='center'><input type='checkbox' name='omv_menu[$x]' $checked></td>\n";

			# Valutakoder
			for ($y=0;$y<count($ValutaKode);$y++) {
					print "<tr><td colspan=\"2\"><input type='hidden' name='ValutaKode[$y]' value='$ValutaKode[$y]'>Konti for $ValutaKode[$y]</td>\n";
					print "<td><input class='inputbox' type='text' style='text-align:right;width:50px;' name='ValutaKonti[$x][$y]' value='".$ValutaKonti[$y][$x]."'></td>\n";
					print "<td><input class='inputbox' type='text' style='text-align:right;width:50px;' name='ValutaMlKonti[$x][$y]' value='".$ValutaMlKonti[$y][$x]."'></td>\n";
					print "<td><input class='inputbox' type='text' style='text-align:right;width:50px;' name='ValutaDifKonti[$x][$y]' value='".$ValutaDifKonti[$y][$x]."'></td></tr>\n";
			}
			print "</tr>";
			print "</table></td>";
			print "</tr>";
		}
		# Kassesetup done

    # Handle Vibrant terminal creation
    print "
<script>

function type_change(idx) {
  var elm = document.getElementsByName(`terminal_type[\${idx}]`)[0];
  if (elm.value == 'vibrant_new') {
    fetch('diverseIncludes/create_vibrant_term.php', {
      method: 'POST', 
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({'id': idx}), 
    })
    .then(res => res.text())
    .then((text) => {

      if (text.endsWith('ERROR')) {
        alert('Der skete en fejl, kontakt venligst saldi teamet på 46902208 hvis du sidder fast.\\n\\n' + text.split('\\n')[text.split('\\n').length-2]);
      } else {
        // Reload the page
        location.href = location.href;
      }
    });
  }
}

</script>";
	}
	print "</tbody></table></td></tr>";
	print "<tr><td><hr></td></tr>\n";
	print "<tr><td><table><tbody>";
	print "<tr><td colspan= '2' title='".findtekst(279,$sprog_id)."'><!--Tekst 726-->".findtekst(280,$sprog_id)."<!--Tekst 280-->&nbsp;<input class='inputbox' type='text' style='text-align:right' size='1' name='kortantal' value='$kortantal'></td></tr>\n";
	if ($kortantal) {
		for($x=0;$x<$kortantal;$x++) {
			if (!isset ($betalingskort[$x])) $betalingskort[$x] = '';
			if (!isset ($enabled[$x]))       $enabled[$x]       = '';
			if (!isset ($kortkonti[$x]))     $kortkonti[$x]     = '';
			if (!isset ($korttyper[$x]))     $korttyper[$x]     = '';
			if (!isset ($voucher[$x]))       $voucher[$x]       = '';
		}
		print "<td title='".findtekst(859,$sprog_id)."'><!--Tekst 859-->".findtekst(862,$sprog_id)."<!--Tekst 862--></td>\n";
		print "<td title='".findtekst(281,$sprog_id)."'><!--Tekst 281-->".findtekst(283,$sprog_id)."<!--Tekst 283--></td>\n";
		print "<td align='center' title='".findtekst(282,$sprog_id)."'><!--Tekst 282-->".findtekst(284,$sprog_id)."<!--Tekst 284--></td>\n";
		print "<td align='center' title='".findtekst(711,$sprog_id)."'><!--Tekst 711-->".findtekst(710,$sprog_id)."<!--Tekst 710--></td>\n";
		print "<td align='center' title='".findtekst(855,$sprog_id)."'><!--Tekst 855-->".findtekst(854,$sprog_id)."<!--Tekst 854--></td>\n";
		print "<td align='center' title='".findtekst(1729, $sprog_id)."'>".findtekst(320, $sprog_id)."</td>\n"; #20200116
#		print "<td align='center' title='".findtekst(857,$sprog_id)."'><!--Tekst 855-->".findtekst(856,$sprog_id)."<!--Tekst 856--></td>\n";
		print "<td align='center' title='".findtekst(861,$sprog_id)."'><!--Tekst 861-->".findtekst(860,$sprog_id)."<!--Tekst 860--></td>\n";
		print "</tr>\n";
		print "<tr><td colspan='8'></td></tr>\n";
		for($x=0;$x<$kortantal;$x++) {
			($enabled[$x])?$enabled[$x]='checked':$enabled[$x]=''; // 20181215
			($betalingskort[$x])?$betalingskort[$x]='checked':$betalingskort[$x]=''; // 20131210
			($voucher[$x])?$voucher[$x]='checked':$voucher[$x]=''; // 20181029
			print "<tr bgcolor=$bgcolor5>";
			$tmp=$x+1;
//			print "<td>$tmp</td>\n";
			print "<td title='".findtekst(859,$sprog_id)."'><!--Tekst 859-->";
			print "<input class='inputbox' type='text' style='text-align:right;width:20px;' name='kortno[$x]' value='$tmp'></td>\n";
			print "<td title='".findtekst(281,$sprog_id)."'>";
			print "<input class='inputbox' type='text' style='text-align:left;width:120px;' name='korttyper[$x]' value='$korttyper[$x]'></td>\n";
			print "<td title='".findtekst(282,$sprog_id)."'>";
			print "<input class='inputbox' type='text' style='text-align:right;width:120px;' name='kortkonti[$x]' value='$kortkonti[$x]'></td>\n";
			print "<td title='".findtekst(711,$sprog_id)."' align='center'>";
			print "<input class='inputbox' type='checkbox' name='betalingskort[$x]' $betalingskort[$x]></td>\n"; #20131210
			print "<td title='".findtekst(855,$sprog_id)."' align='center'>";
			print "<input class='inputbox' type='checkbox' name='voucher[$x]' $voucher[$x]></td>\n"; #20181029
			if ( $voucher[$x]==="checked" ) {
				print "<td title='$betalingskort[$x]'>";
				print "<input class='inputbox' type='text'  style='width:90px;' name='voucherItemNo[$x]' value=\"$voucherItemNo[$x]\"></td>\n";
#				print "<td title='".findtekst(857,$sprog_id)."'>";
#				print "<input class='inputbox' type='text' name='vouchertext[$x]' value='$vouchertext[$x]'></td>\n"; #20181029
			} else {
				print "<td></td>";
#				print "<td title='".findtekst(858,$sprog_id)."'>";
#				print "<input class='inputbox' type='text' style='text-align:left;background:".$bgcolor5."' name='vouchertext[$x]' v#alue='$vouchertext[$x]' disabled='disabled'></td>\n"; #20181029
			}
			print "<td title='".findtekst(861,$sprog_id)."' align='center'><!--Tekst 861-->";
			print "<input class='inputbox' type='checkbox' style='text-align:right' name='enabled[$x]' $enabled[$x]></td></tr>\n"; // 20181215
		}
		$bet_term=NULL;
		for ($x=0;$x<count($terminal_ip);$x++) {
			if ($terminal_ip[$x]) $bet_term=1; #Så er der betalinggsterminal på min 1. kasse.
		}
		if ($bet_term) {
			$tmp++;
			print "<tr bgcolor=$bgcolor5>";
			print "<td>$tmp</td>\n";
			print "<td title='".findtekst(713,$sprog_id)."'>".findtekst(712,$sprog_id)."</td>\n";
			print "<td title='".findtekst(713,$sprog_id)."'><input class='inputbox' type='text' style='text-align:right' size='5' name='div_kort_kto' value='$div_kort_kto'></td>\n";
			print "<td title='".findtekst(713,$sprog_id)."' align='center'><INPUT DISABLED='disabled' class='inputbox' type='checkbox' style='text-align:right' checked></td></tr>\n";
		}
	}
	print "</tbody></table></td></tr>";
	print "<tr><td><hr></td></tr>\n";
	print "<tr><td><table><tbody>";
	# 20140508 ->

  # Create the HTML for the plan table input area
  $pages_htm = "";
  $x = 1;
  $q = db_select("select id, name from table_pages ORDER BY id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$id=$r["id"];
		$name=$r["name"];
    
    $pages_htm = "$pages_htm<tr bgcolor=$bgcolor5><td>$id</td><td><input class='inputbox' type='text' style='text-align:left' size='15' name='plan[$x]' value='$name'></td></tr>";
		$x++;
	}

  # Get the amount of plans that we have, since it gets added at the end it will always be 1 above the real amount
  $plancount = $x-1;
  # Bordantal for the old table system
  $bordantal=count($bord);

  if ($bordantal != 0) {
    $txt = "Ikke i brug";
    $title = "Sæt gammelt bordplans system til 0 for at aktivere.";
  } else {
    $txt = "";
    $title = "";
  }

  print "
    <tr>
      <td title='$title' style='color: red'>
        $txt
      </td>
      <td>
        <button type='button' onclick='window.location.replace(\"../bordplaner/planner\")'>Åben bordplanlægger</button>
      </td>
    </tr>

    <tr>
      <td>
        Antal bordplaner
      </td>
      <td>
        <input class='inputbox' type='text' style='text-align:right' size='1' name='planamount' value='$plancount'>
      </td>
    </tr>
    
    <tr>
      <td>
        Id
      </td>
      <td>
        Navn
      </td>
    </tr>
    $pages_htm
";
  
  if ($bordantal != 0) {
    print "
      <tr>
        <td colspan=2><br>Gammelt Bordplansystem, sæt antal til 0 for at aktivere det nye.</td>
      </tr>
      ";
    print "<tr><td title='".findtekst(673,$sprog_id)."'>".findtekst(674,$sprog_id)."</td><td><input class='inputbox' type='text' style='text-align:right' size='1' name='bordantal' value='$bordantal'></td></tr>\n";
    if ($bordantal) {
      print "<tr><td></td><td title='".findtekst(675,$sprog_id)."'>".findtekst(676,$sprog_id)."</td></tr>\n";
      print "<tr><td colspan='6'></td></tr>\n";
      for($x=0;$x<$bordantal;$x++) {
        print "<tr bgcolor=$bgcolor5>";
        $tmp=$x+1;
        print "<td>$tmp</td>\n";
        print "<td title='".findtekst(675,$sprog_id)."'><input class='inputbox' type='text' style='text-align:left' size='15' name='bord[$x]' value='$bord[$x]'></td></tr>\n";
  }
		}
	}
	print "</tbody></table></td></tr>";
	print "<tr><td><hr></td></tr>\n";
	print "<tr><td><table border='0'><tbody>";
	# <- 20140508
	print "<tr><td title='".findtekst(453,$sprog_id)."'>".findtekst(454,$sprog_id)."</td>";
	print "<td style='width:60px;' title='".findtekst(453,$sprog_id)."'><input class='inputbox' type='checkbox' name='straksbogfor' $straksbogfor></td>\n";
	print "<td title='".findtekst(456,$sprog_id)."'>".findtekst(457,$sprog_id)."</td>";
	print "<td title='".findtekst(456,$sprog_id)."'><input class='inputbox' type='checkbox' name='udskriv_bon' $udskriv_bon></td></tr>\n";
	
	print "<tr><td title='".findtekst(458,$sprog_id)."'>".findtekst(459,$sprog_id)."</td>";
	print "<td title='".findtekst(458,$sprog_id)."'><input class='inputbox' type='checkbox' name='vis_hurtigknap' $vis_hurtigknap></td>\n";
	print "<td title='".findtekst(460,$sprog_id)."'>".findtekst(461,$sprog_id)."</td>";
	print "<td title='".findtekst(460,$sprog_id)."'><input class='inputbox' type='checkbox' name='vis_kontoopslag' $vis_kontoopslag></td></tr>\n";
	
	print "<tr><td title='".findtekst(464,$sprog_id)."'>".findtekst(465,$sprog_id)."</td>";
	print "<td title='".findtekst(464,$sprog_id)."'><input class='inputbox' type='checkbox' name='vis_indbetaling' $vis_indbetaling></td>\n";
	print "<td title='".findtekst(734,$sprog_id)."'>".findtekst(735,$sprog_id)."</td>";
	print "<td title='".findtekst(744,$sprog_id)."'><input class='inputbox' type='checkbox' name='vis_saet' $vis_saet></td></tr>\n";
	
	print "<tr><td title='".findtekst(462,$sprog_id)."'>".findtekst(463,$sprog_id)."</td>";
	print "<td title='".findtekst(462,$sprog_id)."'><input class='inputbox' type='text' style='text-align:right;width:25px' name='timeout' value='$timeout'></td>\n";
	print "<td title='".findtekst(701,$sprog_id)."'>".findtekst(700,$sprog_id)."</td>";
	print "<td title='".findtekst(701,$sprog_id)."'><input class='inputbox' type='text' style='text-align:right;width:70px' name='kasseprimo' value='$kasseprimo'></td></tr>\n";
	
	print "<tr><td title='".findtekst(837,$sprog_id)."'>".findtekst(838,$sprog_id)."</td>";
	print "<td title='".findtekst(837,$sprog_id)."'><input class='inputbox' type='checkbox' name='udtag0' $udtag0></td>\n";
	print "<td title='".findtekst(703,$sprog_id)."'>".findtekst(702,$sprog_id)."</td>";
	print "<td title='".findtekst(703,$sprog_id)."'><input class='inputbox' type='checkbox' name='optalassist' $optalassist></td></tr>\n";
	
	print "<tr><td title='".findtekst(839,$sprog_id)."'>".findtekst(840,$sprog_id)."</td>";
	print "<td title='".findtekst(839,$sprog_id)."'><input class='inputbox' type='checkbox' name='brugervalg' $brugervalg></td>\n";
	print "<td title='".findtekst(848,$sprog_id)."'>".findtekst(847,$sprog_id)."</td>";
	print "<td title='".findtekst(848,$sprog_id)."'><input class='inputbox' type='checkbox' name='kundedisplay' $kundedisplay></td></tr>\n";
	$title=findtekst(863,$sprog_id);
	$text=findtekst(864,$sprog_id);
	print "<td title='$title'>$text</td>";
	print "<td title='$title'><input class='inputbox' type='checkbox' name='change_cardvalue' $change_cardvalue></td>\n";
	$text = findtekst(1730, $sprog_id);
	$title = "".findtekst(1731, $sprog_id).".";
	print "<td title='$title'>$text</td>";
	print "<td title='$title'><input class='inputbox' type='checkbox' name='deactivateBonprint' $deactivateBonprint></td></tr>\n";
	$text = findtekst(1962, $sprog_id);
	$title = "".findtekst(1961, $sprog_id).".";
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input class='inputbox' type='checkbox' name='jump2price' $jump2price></td>\n";
	print "<td title='".findtekst(2368,$sprog_id)."'>".findtekst(2367,$sprog_id)."</td>";
	print "<td title='".findtekst(2368,$sprog_id)."'><input class='inputbox' type='checkbox' name='lagerbeh' $lagerbeh></td></tr>\n";
	$text = findtekst(2407, $sprog_id);
	$title = "".findtekst(2408, $sprog_id).".";
	$big = get_settings_value("show_big_sum", "POS", "off");
	$big = $big == "on" ? "checked" : "";
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input class='inputbox' type='checkbox' name='show_big_sum' $big></td>\n";
	print "<td><br></td></tr>\n";
	print "</tbody></table></td></tr>";
	print "<tr><td><hr></td></tr>\n";
	# KDS system settings
	print "<tr><td><table border='0'><tbody>";
	print "<tr><td>KDS / Køkkenprint Setup</td></tr>\n";
	print "<tr><td>Aktiveret KDS / Print</td><td><input class='inputbox' type='checkbox' name='kdsactive' $kdsactive><input class='inputbox' type='checkbox' name='printactive' $printactive></td></tr>\n";
	print "<tr><td>Antal kolonner</td><td><input class='inputbox' type='text' name='kdscolumns' value='$kdscolumns'></td></tr>\n";
	print "<tr><td>Linjehøjde</td><td><input class='inputbox' type='text' name='kdsheight' value='$kdsheight'></td></tr>\n";
	print "<tr><td><br></td></tr>\n";

	print "</tbody></table></td></tr>";
	print "<tr><td><table border='0'><tbody>";
	print "<tr><td>Antal minutter</td><td>Farve</td></tr>\n";
	$x = 0;
	$q = db_select("select var_value from settings where var_name='color' and var_grp='KDS' order by var_value", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$row = explode("-", $r["var_value"]);
		print "<tr><td><input class='inputbox' type='text' size='5' name='kdscolorindex[$x]' value='$row[0]'></td><td><input class='inputbox' type='color' name='kdscolor[$x]' value='$row[1]'></td></tr>\n";
		$x++;
	}
	print "<tr><td><input class='inputbox' type='text' size='5' name='kdscolorindex[$x]' value=''></td><td><input class='inputbox' type='color' name='kdscolor[$x]' value=''></td></tr>\n";

	print "<tr><td><br></td></tr>\n";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input class='button green medium' type=submit accesskey='g' value='".findtekst(471, $sprog_id)."' name='submit'></td>\n";
	print "</form>\n";
	print "<tr><td><a href=posmenuer.php><input class='button blue medium' type='button' value='Ret POS menuer'></a></td></tr>\n";
	print "</tbody></table></td></tr>";
	print "<tr><td><hr></td></tr>\n";

} # endfunc posOptions
?>

<style>
	.hidden {
		display: none;
	}
</style>