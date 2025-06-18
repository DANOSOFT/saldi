<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- sager/loenIncludes/vis_liste.php --- lap 4.0.8 --- 2023-09-27 ---
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
// 20230703 PHR array tr_tjek moved up, as it was reset when it should not. 
// 20230703 PHR reactiveate $sum1 - no idea why it was deactivated.
// 20230915 PHR if transport has been on list and all itens with transport is removed, transportlines are deleted from
//              loen_enheder
// 20230915 PHR tent surcharges were not shown on completed bills.

function vis_liste($id,$listevalg,$afsluttet,$godkendt,$telt_antal) {

	global $brugernavn,$bgcolor,$db;
	
/*
 ($listevalg >= 10)?$nyListe = 1:$nyListe = 0;
	if ($db == 'stillads_11' && $listevalg == 8) $nyListe = 1;
	if ($db == 'laja_17' && $listevalg == 8) $nyListe = 1;
*/
	$nyListe = 1;

	$tr_sum = 0;
	$l_liste = $l_vare_id = $vare_nr = array();

	$bgcolor2="#ffffff";
	$linjebg=$bgcolor2;

	$x=0;
#	$q=db_select("select id,kodenr,art,box1 from grupper where art ='V_CAT' order by box1",__FILE__ . " linje " . __LINE__);
#	while ($r=db_fetch_array($q)) {
#		$cat_id[$x]=$r['kodenr'];
#		$cat_navn[$x]=$r['box1'];
#		$x++;
#	}
	$q=db_select("select id,kodenr,beskrivelse from grupper where art ='VG' and box10='on' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$cat_id[$x]=$r['kodenr'];
		$cat_navn[$x]=$r['beskrivelse'];
		$cat_nr[$x]='';
		for ($i=0;$i<strlen($cat_navn[$x]);$i++) {
			if (is_numeric(substr($cat_navn[$x],$i,1))) $cat_nr[$x].=substr($cat_navn[$x],$i,1);
			else break 1;
		}
		$x++;
	}

	$q=db_select("select id,kodenr from grupper where art ='V_CAT' and lower(box1)='transport'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$transport_nr=$r['kodenr']; #udfases.
		$transport_id=$r['id'];
	}

	$x = $y = 0;
	$tr_id = array();
	$qtxt="SELECT * FROM loen_enheder WHERE loen_id = '$id' ORDER BY varenr,tekst";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__); // Har fjernet 'order by tekst' og ændret til 'order by vare_id'. Vil gerne have den samme rækkefølge som original liste | tilbageført 20140326 | tilføjet varenr 20140328
	while ($r = db_fetch_array($q)) {
		if ($r['vare_id']>0) {
			$l_id[$x]=$r['id'];
			$l_vare_id[$x]  = (int)$r['vare_id'];
			$l_vare_nr[$x]  = $r['varenr'];
			$l_op[$x]       = (int)$r['op'];
			$l_ned[$x]      = (int)$r['ned'];
			$l_op_25[$x]    = (int)$r['op_25'];
			$l_ned_25[$x]   = (int)$r['ned_25'];
			$l_op_30[$x]    = (int)$r['op_30'];
			$l_ned_30[$x]   = (int)$r['ned_30'];
			$l_op_40[$x]    = (int)$r['op_40'];
			$l_ned_40[$x]   = (int)$r['ned_40'];
			$l_op_60[$x]    = (int)$r['op_60'];
			$l_ned_60[$x]   = (int)$r['ned_60'];
			$l_op_70[$x]    = (int)$r['op_70'];
			$l_ned_70[$x]   = (int)$r['ned_70'];
			$l_op_100[$x]    = (int)$r['op_100'];
			$l_ned_100[$x]   = (int)$r['ned_100'];
			$l_op_160[$x]    = (int)$r['op_160'];
			$l_ned_160[$x]   = (int)$r['ned_160'];
			$l_op_30m[$x]   = (int)$r['op_30m'];
			$l_ned_30m[$x]  = (int)$r['ned_30m'];
			$l_pris_op[$x]  = (float)$r['pris_op'];
			$l_pris_ned[$x] = (float)$r['pris_ned'];

			list($l_liste[$x],$l_tekst[$x])=explode("|",$r['tekst']);
			$l_liste[$x] = if_isset($l_liste[$x],0);
#			if (!$afsluttet) {
				$r2=db_fetch_array(db_select("SELECT beskrivelse,gruppe FROM varer WHERE id = '$l_vare_id[$x]'",__FILE__ . " linje " . __LINE__));
				if($r2['gruppe']) $l_liste[$x]=$r2['gruppe'];
				if($r2['beskrivelse']) $l_tekst[$x]=$r2['beskrivelse'];
				for ($c=0;$c<count($cat_id);$c++) {
					if ($l_liste[$x]==$cat_id[$c]) {
						$tmp=$cat_navn[$c]." - ";
						$l_tekst[$x]=str_replace($tmp,"",$l_tekst[$x]);
						$l_tekst[$x]="$cat_navn[$c] - $l_tekst[$x]";
					} 
				}	
#			}
			$x++;
		}	elseif($r['vare_id']=='-1') {
			$tr_id[$y]=$r['id'];
			$tr_antal[$y]=$r['op']*1;
			$tr_pris[$y]=afrund($r['pris_op'],2);
			list($tr_liste[$y],$tr_tekst[$y])=explode("|",$r['tekst']);
			for ($c=0;$c<count($cat_id);$c++) {
				if ($tr_liste[$y]==$cat_id[$c]) {
					$tr_navn[$y]=$cat_navn[$c];
				} 
			}
			$tr_sum=$tr_antal[$y]*$tr_pris[$y];
			$y++;
		} elseif($r['vare_id']=='-2') {
			$telt_id=$r['id'];
			$telt_antal=$r['op']*1;
			$telt_pris=$r['pris_op']*1;
			for ($c=0;$c<count($cat_id);$c++) {
				if ($cat_id[$c]==7) {
					$telt_navn=$cat_navn[$c];
				} 
			}
			#			list($telt_pct,$telt_tekst)=explode("|",$r['tekst']);
			$z++;
		}
		#		if (is_numeric($l_liste[$x])) {
#			for ($y=0;$y<count($cat_id);$y++){
#				if($cat_id[$y]==$l_liste[$x]) $l_liste[$x]=$cat_navn[$y];
#			}
#		}
	}
	if (!$afsluttet) {
		$x=0;
		$tr_sum=0;
		$tmp=array();
		#		$q = db_select("SELECT * FROM varer WHERE id > '0' AND kategori LIKE '%$listevalg%' ORDER BY varenr ASC",__FILE__ . " linje " . __LINE__);
		$qtxt = "SELECT * FROM varer WHERE id > '0' AND gruppe = '$listevalg' ORDER BY varenr ASC";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$tmp=explode(chr(9),$r['kategori']);
			$kategori[$x]=NULL;
			if (in_array($transport_nr,$tmp)) { #fjernes 20200701 
				for ($t=0;$t<count($tmp);$t++) {
					if ($transport_nr == $tmp[$t]) $tmp[$t]=$transport_id;
					($kategori[$x])?$kategori[$x].=$tmp[$t]:$kategori[$x]=$tmp[$t];
				}
				if ($kategori[$x]) {
					$qtxt="update varer set kategori='$kategori[$x]' where id='$r[id]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
 				}
			} else $kategori[$x]=$r['kategori'];
			$vare_id[$x]=$r['id'];
			$vare_nr[$x]=$r['varenr'];
			$vare_beskrivelse[$x]=$r['beskrivelse'];
			$montagepris[$x]=afrund($r['montage'],2);
			$demontagepris[$x]=afrund($r['demontage'],2);
			$op[$x]=NULL;		
			$ned[$x]=NULL;		
			$enhed_id[$x]=NULL;
			if (in_array($vare_id[$x],$l_vare_id)) {
				for ($y=0;$y<count($l_vare_id);$y++) {
					if ($vare_id[$x]==$l_vare_id[$y]){
						if (in_array($transport_id,$tmp)) {
							$tr_sum+=$l_op[$y]*$montagepris[$x]*0.07;
							$tr_sum+=$l_ned[$y]*$demontagepris[$x]*0.14;
						}
						$enhed_id[$x]=$l_id[$y];
						$op[$x]=$l_op[$y];
						$ned[$x]=$l_ned[$y];
						$op_25[$x]=$l_op_25[$y];
						$ned_25[$x]=$l_ned_25[$y];
						$op_30[$x]=$l_op_30[$y];
						$ned_30[$x]=$l_ned_30[$y];
						$op_40[$x]=$l_op_40[$y];
						$ned_40[$x]=$l_ned_40[$y];
						$op_60[$x]=$l_op_60[$y];
						$ned_60[$x]=$l_ned_60[$y];
						$op_70[$x]=$l_op_70[$y];
						$ned_70[$x]=$l_ned_70[$y];
						$op_100[$x]=$l_op_100[$y];
						$ned_100[$x]=$l_ned_100[$y];
						$op_160[$x]=$l_op_160[$y];
						$ned_160[$x]=$l_ned_160[$y];
						$op_30m[$x]=$l_op_30m[$y];
						$ned_30m[$x]=$l_ned_30m[$y];
					}
				}	
			}
			$x++;
		}
		if ($afsluttet || $godkendt) {
			$readonly="readonly=\"readonly\"";
			$vis_alle=0;
		} else {
			$readonly=NULL;
			$vis_alle=1;
		}
	} else { #if ($afsluttet)
		$readonly="readonly=\"readonly\"";
		$vis_alle=0;
		for ($y=0;$y<count($l_vare_id);$y++) {
			$vare_id[$y]=$l_vare_id[$y];
			$vare_nr[$y] = $l_vare_nr[$y];
			$montagepris[$y]=$l_pris_op[$y];
			$demontagepris[$y]=$l_pris_ned[$y];
			$op[$y]      = $l_op[$y];
			$ned[$y]     = $l_ned[$y];
			$op_25[$y]   = $l_op_25[$y];
			$ned_25[$y]  = $l_ned_25[$y];
			$op_30[$y]   = $l_op_30[$y];
			$ned_30[$y]  = $l_ned_30[$y];
			$op_40[$y]   = $l_op_40[$y];
			$ned_40[$y]  = $l_ned_40[$y];
			$op_60[$y]   = $l_op_60[$y];
			$ned_60[$y]  = $l_ned_60[$y];
			$op_70[$y]   = $l_op_70[$y];
			$ned_70[$y]  = $l_ned_70[$y];
			$op_100[$y]  = $l_op_100[$y];
			$ned_100[$y] = $l_ned_100[$y];
			$op_160[$y]  = $l_op_160[$y];
			$ned_160[$y] = $l_ned_160[$y];
			$op_30m[$y]  = $l_op_30m[$y];
			$ned_30m[$y] = $l_ned_30m[$y];
			$vare_beskrivelse[$y] = "$l_tekst[$y]";
			list($kategori[$y]) = explode(".",$vare_beskrivelse[$y],2); #Skal bruges til at finde kat 03. telt
			$kategori[$y] = (int)$kategori[$y];
		}
	}
	$sum=0;
	$z=0;
	$vist=array();
	$sum1=$telt_tillaeg=$teltsum=0;
	
	for ($x=0;$x<count($vare_id);$x++) {
		$demontagepris[$x] = (float)$demontagepris[$x];
		$ned[$x]      = if_isset($ned[$x],0);
		$ned_25[$x]   = if_isset($ned_25[$x],0);
		$ned_30[$x]   = if_isset($ned_30[$x],0);
		$ned_40[$x]   = if_isset($ned_40[$x],0);
		$ned_60[$x]   = if_isset($ned_60[$x],0);
		$ned_70[$x]   = if_isset($ned_70[$x],0);
		$ned_100[$x]  = if_isset($ned_100[$x],0);
		$ned_160[$x]  = if_isset($ned_160[$x],0);
		$ned_30m[$x]  = if_isset($ned_30m[$x],0);
		$op[$x]       = if_isset($op[$x],0);
		$op_25[$x]    = if_isset($op_25[$x],0);
		$op_30[$x]    = if_isset($op_30[$x],0);
		$op_40[$x]    = if_isset($op_40[$x],0);
		$op_60[$x]    = if_isset($op_60[$x],0);
		$op_70[$x]    = if_isset($op_70[$x],0);
		$op_100[$x]   = if_isset($op_100[$x],0);
		$op_160[$x]   = if_isset($op_160[$x],0);
		$op_30m[$x]   = if_isset($op_30m[$x],0);
		
		if ($vis_alle || $op[$x] || $ned[$x]) {
			$linjesum1[$x]=$op[$x]*$montagepris[$x];
			$linjesum1[$x]+=$ned[$x]*$demontagepris[$x];

			$linjesum2[$x]=
			$linjesum1[$x]+
			$op_25[$x]*
			$montagepris[$x]*0.25+
			$op_30[$x]*
			$montagepris[$x]*0.3+
			$op_40[$x]*
			$montagepris[$x]*0.4+
			$op_60[$x]*
			$montagepris[$x]*0.6+
			$op_70[$x]*
			$montagepris[$x]*0.7+
			$op_100[$x]*
			$montagepris[$x]*1+
			$op_160[$x]*
			$montagepris[$x]*1.6+
			$op_30m[$x]*
			$montagepris[$x]*0.06;
			$linjesum2[$x]+=
			$ned_25[$x]*
			$demontagepris[$x]*0.25+
			$ned_30[$x]*
			$demontagepris[$x]*0.3+
			$ned_40[$x]*
			$demontagepris[$x]*0.4+
			$ned_60[$x]*
			$demontagepris[$x]*0.4+
			$ned_60[$x]*
			$demontagepris[$x]*0.6+
			$ned_70[$x]*
			$demontagepris[$x]*0.7+
			$ned_100[$x]*
			$demontagepris[$x]*1+
			$ned_160[$x]*
			$demontagepris[$x]*1.6+
			$ned_30m[$x]*
			$demontagepris[$x]*0.06;
			if (isset($l_liste[$x]) && $l_liste[$x]==7) {
				$teltsum+=$linjesum1[$x];
			}
			$sum1+=$linjesum1[$x];
			$sum+= $linjesum2[$x];
			($linjebg == $bgcolor)?$linjebg=$bgcolor2:$linjebg=$bgcolor;
			($op[$x])?$op[$x]           = str_replace(".",",",$op[$x]):$op[$x] = '';
			($ned[$x])?$ned[$x]         = str_replace(".",",",$ned[$x]):$ned[$x] = '';
			($op_25[$x])?$op_25[$x]     = str_replace(".",",",$op_25[$x]):$op_25[$x] = '';
			($ned_25[$x])?$ned_25[$x]   = str_replace(".",",",$ned_25[$x]):$ned_25[$x] = '';
			($op_30[$x])?$op_30[$x]     = str_replace(".",",",$op_30[$x]):$op_30[$x] = '';
			($ned_30[$x])?$ned_30[$x]   = str_replace(".",",",$ned_30[$x]):$ned_30[$x] = '';
			($op_40[$x])?$op_40[$x]     = str_replace(".",",",$op_40[$x]):$op_40[$x] = '';
			($ned_40[$x])?$ned_40[$x]   = str_replace(".",",",$ned_40[$x]):$ned_40[$x] = '';
			($op_60[$x])?$op_60[$x]     = str_replace(".",",",$op_60[$x]):$op_60[$x] = '';
			($ned_60[$x])?$ned_60[$x]   = str_replace(".",",",$ned_60[$x]):$ned_60[$x] = '';
			($op_70[$x])?$op_70[$x]     = str_replace(".",",",$op_70[$x]):$op_70[$x] = '';
			($ned_70[$x])?$ned_70[$x]   = str_replace(".",",",$ned_70[$x]):$ned_70[$x] = '';
			($op_100[$x])?$op_100[$x]   = str_replace(".",",",$op_100[$x]):$op_100[$x] = '';
			($ned_100[$x])?$ned_100[$x] = str_replace(".",",",$ned_100[$x]):$ned_100[$x] = '';
			($op_160[$x])?$op_160[$x]   = str_replace(".",",",$op_160[$x]):$op_160[$x] = '';
			($ned_160[$x])?$ned_160[$x] = str_replace(".",",",$ned_160[$x]):$ned_160[$x] = '';
			($op_30m[$x])?$op_30m[$x]   = str_replace(".",",",$op_30m[$x]):$op_30m[$x] = '';
			($ned_30m[$x])?$ned_30m[$x] = str_replace(".",",",$ned_30m[$x]):$ned_30m[$x] = '';
			print "<tr id=\"$vare_id[$x]\" bgcolor=\"$linjebg\" style=\"border-right: 1px solid #d3d3d3;border-left: 1px solid #d3d3d3;\">
				<td class=\"printBorderRight\"><input type=\"text\" $readonly style=\"width:30px; text-align:right;\" class=\"printBorderNone\" name=\"op[$z]\" value=\"$op[$x]\"></td>
				<td class=\"printBorderRight\"><input type=\"text\" $readonly style=\"width:30px; text-align:right;\" class=\"printBorderNone printBorderRight\" name=\"ned[$z]\" value=\"$ned[$x]\"></td>
				<td style=\"padding-left: 5px;\">$vare_beskrivelse[$x]</td>
				<td class=\"alignRight\" width=\"80\">".dkdecimal($montagepris[$x],2)."</td>
				<td class=\"alignRight\" width=\"80\">".dkdecimal($demontagepris[$x],2)."</td>
				<td class=\"alignRight printBorderRight\" style=\"padding: 0px 1px 0px 10px;\">".dkdecimal($linjesum1[$x],2)."</td>";
			if ($nyListe == 0) {
				print "
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"op_25[$z]\" value=\"$op_25[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"ned_25[$z]\" value=\"$ned_25[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"op_40[$z]\" value=\"$op_40[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"ned_40[$z]\" value=\"$ned_40[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"op_60[$z]\" value=\"$op_60[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"ned_60[$z]\" value=\"$ned_60[$x]\"></td>";
		} else {
			print "
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"op_30[$z]\" value=\"$op_30[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"ned_30[$z]\" value=\"$ned_30[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"op_70[$z]\" value=\"$op_70[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"ned_70[$z]\" value=\"$ned_70[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"op_100[$z]\" value=\"$op_100[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"ned_100[$z]\" value=\"$ned_100[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"op_160[$z]\" value=\"$op_160[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"ned_160[$z]\" value=\"$ned_160[$x]\"></td>";
		} print "
				<td class=\"alignRight\" style=\"padding-right: 1px;\">".dkdecimal($linjesum2[$x],2)."";

			print "<input type=\"hidden\" name=\"vare_id[$z]\" value=\"$vare_id[$x]\">\n";
			print "<input type=\"hidden\" name=\"varenr[$z]\" value=\"$vare_nr[$x]\">\n"; // indsat 20142803
			print "<input type=\"hidden\" name=\"enhed_id[$z]\" value=\"$enhed_id[$x]\">\n";
			print "<input type=\"hidden\" name=\"pris_op[$z]\" value=\"$montagepris[$x]\">\n";
			print "<input type=\"hidden\" name=\"pris_ned[$z]\" value=\"$demontagepris[$x]\">\n";
			print "<input type=\"hidden\" name=\"vare_tekst[$z]\" value=\"$listevalg|$vare_beskrivelse[$x]\">\n";
			print "</td></tr>\n";
			$z++;
			if ($afsluttet && $l_liste[$x]!=$l_liste[$x+1]) {
				for ($tr=0;$tr<count($tr_id);$tr++) {
					if ($tr_liste[$tr]==$l_liste[$x]) {
						print "<tr class=\"akkordListeTrans\"><td colspan=\"1\" style=\"text-align:right;\">$tr_antal[$tr]</td><td></td><td style=\"padding-left: 5px;\" colspan=\"12\">$tr_navn[$tr] Transport</td><td style=\"text-align:right;\">".dkdecimal($tr_antal[$tr]*$tr_pris[$tr],2)."</td></tr>";
						$sum+=$tr_antal[$tr]*$tr_pris[$tr];
					}
				}
				if ($l_liste[$x]=='7' || $l_liste[$x]=='11') {
					print "<tr class=\"akkordListeTrans\">
						<td colspan=\"1\" style=\"text-align:right;\">1</td>
						<td></td>
						<td style=\"padding-left: 5px;\" colspan=\"12\">$l_liste[$x]. Telt tillæg ".($telt_antal*100)."%</td>
						<td align=\"right\">".dkdecimal($telt_antal*$telt_pris,2)."</td></tr>
						<!--<td align=\"right\">".dkdecimal($telt_antal*$teltsum,2)."</td>-->
					</tr>"; #20140810 ændret telt_pris til sum1 også 2 linjer herunder
					$telt_tillaeg=1;
					$sum+=$telt_antal*$telt_pris;
					$sum+=$telt_antal*$teltsum;
				}
			}
		}
	}
	if (!$afsluttet) {
		print "<tr>
			<td colspan=\"2\" class=\"tableSagerBorder\"></td><td colspan=\"3\" class=\"tableSagerBorder\" style=\"padding-left: 5px;\"><b>Sum</b></td><td align=\"right\" class=\"tableSagerBorder\" style=\"padding-right: 1px;\"><b>".dkdecimal($sum1,2)."</b></td>
			<td colspan=\"9\" align=\"right\" class=\"tableSagerBorder\" style=\"padding-right: 1px;\"><b>".dkdecimal($sum,2)."</b></td>
		</tr>";
		$trans_antal=0;
		$trans_id=0;
	}
	if ($tr_sum && !$afsluttet){
		for ($tr=0;$tr<count($tr_id);$tr++) {
			if ($tr_liste[$tr]==$listevalg) {
				$trans_antal=$tr_antal[$tr];	
				$trans_id=$tr_id[$tr];
				$sum+=$tr_antal[$tr]*$tr_sum;
			}
		}
		print "<tr>
			<td colspan=\"2\" class=\"tableSagerBorder\">
				<input type=\"text\" $readonly style=\"width:25px; text-align:right;\" name=\"tr_antal\" value=\"$trans_antal\" class=\"printBorderNone\">
				<input type=\"hidden\" $readonly name=\"tr_id\" value=\"$trans_id\">
				<input type=\"hidden\" $readonly name=\"tr_pris\" value=\"$tr_sum\">
			</td>
			<td colspan=\"3\" class=\"tableSagerBorder\" style=\"padding-left: 5px;\"><b>Transport</b></td><td align=\"right\" class=\"tableSagerBorder\" style=\"padding-right: 1px;\"><b>".dkdecimal($trans_antal*$tr_sum,2)."</b></td>
			<td colspan=\"9\" class=\"tableSagerBorder\">&nbsp;</td>
		</tr>
		<tr>
		<td colspan=\"2\"></td><td colspan=\"3\" style=\"padding-left: 5px;\"><b>Sum incl. transport</b></td>
		<td colspan=\"10\" align=\"right\" style=\"padding-right: 1px;\"><b>".dkdecimal($sum,2)."</b></td>
		</tr>";
	} elseif (!$tr_sum) { // 20230915
		$qtxt = "delete from loen_enheder where loen_id = '$id' and vare_id = '-1' and tekst = '$listevalg|Transport'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}

	if ($listevalg=='7' && !$telt_tillaeg) {
		include ('loenIncludes/addon7.php');
	} elseif ($db != 'stillads_5' && $listevalg=='11' && !$telt_tillaeg) {
		include ('loenIncludes/addon11.php');
	}	elseif ($db == 'stillads_5' && $listevalg=='13' && !$telt_tillaeg) {
		include ('loenIncludes/addon11.php');
	}
	$tr_tjek = array();
	if (!$afsluttet) {
#	} else {
	$c_id=$c_sum=0;
	for ($x=0;$x<count($l_vare_id);$x++) {
		if (is_numeric($l_liste[$x]) && !in_array($l_vare_id[$x],$vare_id)){
			for ($c=0;$c<count($cat_id);$c++) {
				if ($l_liste[$x]==$cat_id[$c]) {
					$c_navn=$cat_navn[$c];
					$c_id=$cat_id[$c];
				} 
			}	
				$l_linjesum[$x] = $l_op[$x]*$l_pris_op[$x];
				$l_linjesum[$x]+= $l_op_25[$x]   * $l_pris_op[$x]  * 0.25;
				$l_linjesum[$x]+= $l_op_30[$x]   * $l_pris_op[$x]  * 0.30;
				$l_linjesum[$x]+= $l_op_40[$x]   * $l_pris_op[$x]  * 0.4;
				$l_linjesum[$x]+= $l_op_60[$x]   * $l_pris_op[$x]  * 0.6;
				$l_linjesum[$x]+= $l_op_70[$x]   * $l_pris_op[$x]  * 0.7;
				$l_linjesum[$x]+= $l_op_100[$x]  * $l_pris_op[$x]  * 1;
				$l_linjesum[$x]+= $l_op_160[$x]  * $l_pris_op[$x]  * 1.6;
				$l_linjesum[$x]+= $l_ned[$x]     * $l_pris_ned[$x];
				$l_linjesum[$x]+= $l_ned_25[$x]  * $l_pris_ned[$x] * 0.25;
				$l_linjesum[$x]+= $l_ned_30[$x]  * $l_pris_ned[$x] * 0.30;
				$l_linjesum[$x]+= $l_ned_40[$x]  * $l_pris_ned[$x] * 0.4;
				$l_linjesum[$x]+= $l_ned_60[$x]  * $l_pris_ned[$x] * 0.6;
				$l_linjesum[$x]+= $l_ned_70[$x]  * $l_pris_ned[$x] * 0.7;
				$l_linjesum[$x]+= $l_ned_100[$x] * $l_pris_ned[$x] * 1;
				$l_linjesum[$x]+= $l_ned_160[$x] * $l_pris_ned[$x] * 1.6;
				$sum+=$l_linjesum[$x];
				$c_sum+=$l_linjesum[$x];
				($l_op[$x])?$l_op[$x]=str_replace(".",",",$l_op[$x]):$l_op[$x]=NULL;
				($l_ned[$x])?$l_ned[$x]=str_replace(".",",",$l_ned[$x]):$l_ned[$x]=NULL;
				print "<input type=\"hidden\" name=\"vare_id[$z]\" value=\"$l_vare_id[$x]\">";
				print "<input type=\"hidden\" name=\"varenr[$z]\" value=\"$l_vare_nr[$x]\">"; // indsat 20142803
				print "<input type=\"hidden\" name=\"enhed_id[$z]\" value=\"$l_id[$x]\">";
				print "<input type=\"hidden\" name=\"pris_op[$z]\" value=\"$l_pris_op[$x]\">";
				print "<input type=\"hidden\" name=\"pris_ned[$z]\" value=\"$l_pris_ned[$x]\">";
				print "<input type=\"hidden\" name=\"vare_tekst[$z]\" value=\"$l_tekst[$x]\">";
				print "<input type=\"hidden\" name=\"op[$z]\" value=\"$l_op[$x]\">";
				print "<input type=\"hidden\" name=\"ned[$z]\" value=\"$l_ned[$x]\">";
				print "<input type=\"hidden\" name=\"op_25[$z]\" value=\"$l_op_25[$x]\">";
				print "<input type=\"hidden\" name=\"ned_25[$z]\" value=\"$l_ned_25[$x]\">";
				print "<input type=\"hidden\" name=\"op_30[$z]\" value=\"$l_op_30[$x]\">";
				print "<input type=\"hidden\" name=\"ned_30[$z]\" value=\"$l_ned_30[$x]\">";
				print "<input type=\"hidden\" name=\"op_40[$z]\" value=\"$l_op_40[$x]\">";
				print "<input type=\"hidden\" name=\"ned_40[$z]\" value=\"$l_ned_40[$x]\">";
				print "<input type=\"hidden\" name=\"op_60[$z]\" value=\"$l_op_60[$x]\">";
				print "<input type=\"hidden\" name=\"ned_60[$z]\" value=\"$l_ned_60[$x]\">";
				print "<input type=\"hidden\" name=\"op_70[$z]\" value=\"$l_op_70[$x]\">";
				print "<input type=\"hidden\" name=\"ned_70[$z]\" value=\"$l_ned_70[$x]\">";
				print "<input type=\"hidden\" name=\"op_100[$z]\" value=\"$l_op_100[$x]\">";
				print "<input type=\"hidden\" name=\"ned_100[$z]\" value=\"$l_ned_100[$x]\">";
				print "<input type=\"hidden\" name=\"op_160[$z]\" value=\"$l_op_160[$x]\">";
				print "<input type=\"hidden\" name=\"ned_160[$z]\" value=\"$l_ned_160[$x]\">";
				if ($l_op[$x] || $l_ned[$x]) {
					($linjebg==$bgcolor)?$linjebg=$bgcolor2:$linjebg=$bgcolor;
					print "<tr id=\"$vare_id[$x]\" bgcolor=\"$linjebg\" style=\"border: 1px solid #d3d3d3;\">
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned[$x]</td>
					<td style=\"padding-left: 5px;\">$l_tekst[$x]</td>
					<td class=\"alignRight\" width=\"80\">".dkdecimal($l_pris_op[$x],2)."</td>
					<td class=\"alignRight\" width=\"80\">".dkdecimal($l_pris_ned[$x],2)."</td>
				<td class=\"alignRight\" style=\"border-right: 1px solid #d3d3d3;text-align:right;padding: 0px 1px 0px 10px;\">".dkdecimal($l_linjesum[$x],2)."</td>";
				if ($nyListe == 0) { print "
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op_25[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned_25[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op_40[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned_40[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op_60[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned_60[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op_30m[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned_30m[$x]</td>";
				} else { print "
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op_30[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned_30[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op_70[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned_70[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op_100[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned_100[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op_160[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned_160[$x]</td>";
				}
				print "<td class=\"alignRight\" style=\"padding-right: 1px;\">".dkdecimal($l_linjesum[$x],2)."</td>";
				print "</tr>";
			} else {
				print "<input type=\"hidden\" name=\"op[$z]\" value=\"0\">";
				print "<input type=\"hidden\" name=\"ned[$z]\" value=\"0\">";
			}
			$z++;
#		}
		}
		if ($l_liste[$x]!=$l_liste[$x+1]) {
			if (($c_id=='7' || $c_id=='11') && !$telt_tillaeg) { # 20140810 indsat $telt_tillag for at undgå dobbentprint.
				$telt_tillaeg=1;
				if (!$telt_antal)$telt_antal='0.47';
				if (!$telt_pris)$telt_pris=$c_sum;
				print "<tr>
					<td colspan=\"1\" style=\"#border-style:solid;#border-width:1px;text-align:right;\">1</td>
					<td></td>
					<td colspan=\"3\" style=\"padding-left: 5px;\"><b>$c_navn - Telt tillæg ".($telt_antal*100)."%</b></td>
					<td align=\"right\"><b>".dkdecimal($telt_antal*$telt_pris,2)."</b></td>
				</tr>";
				$c_sum+=$telt_antal*$telt_pris;
				$sum+=$telt_antal*$telt_pris;
			}
			for ($tr=0;$tr<count($tr_id);$tr++) {
			if ($tr_liste[$tr]==$c_id && !in_array($c_id,$tr_tjek)) {
				$i = count($tr_tjek);
				$tr_tjek[$i]=$c_id;
				print "<tr>
				<td colspan=\"1\" style=\"#border-style:solid;#border-width:1px;text-align:right;padding-right: 1px;\">$tr_antal[$tr]</td>
				<td></td>
				<td colspan=\"3\" style=\"padding-left: 5px;\"><b>$c_navn - Transport</b></td>
				<td align=\"right\" style=\"padding-right: 1px;\"><b>".dkdecimal($tr_antal[$tr]*$tr_pris[$tr])."</b></td>
				</tr>";
				$c_sum+=$tr_antal[$tr]*$tr_pris[$tr];
				$sum+=$tr_antal[$tr]*$tr_pris[$tr];
			}
		}
		# 20140810 $c_id fjernet fra "print"  
		if ($c_id && $c_sum) print "<tr><td colspan=\"2\"></td><td colspan=\"12\" style=\"padding-left: 5px;\"><b>$c_navn i alt</b></td><td align=\"right\" style=\"padding-right: 1px;\"><b>".dkdecimal($c_sum)."</b></td></tr>";
		$c_sum=0;
		}
		}
	}
	return $sum;
}
?>
