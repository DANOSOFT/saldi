<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/productcardIncludes/showLocations.php --- lap 4.0.8 --- 2023-09-10 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2023 saldi.dk aps
// ----------------------------------------------------------------------
// 20230910 PHR Added orderlookup for incoming and outgoing orders ($orderInOutput & $orderOutOutput)
// 20231018 PHR Inserted  '&& !count($variantVarerId)' to avoid deleteing variants from lagertatus
?>
<style>
.CellComment{
	top:0 !important;
	left:0 !important;'
	min-width: 20px !important;
}
</style>
<?php
	print "<tr><td colspan=\"2\"><b>".findtekst(782,$sprog_id)."</b></td></tr>";
	if ($stockItem) {
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
			$qtxt = "select lok1 from lagerstatus where vare_id = '$id' and lager = '$x'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$lagerlok[$x]=$r2['lok1'];
			$qtxt="select sum(beholdning) as beholdning from lagerstatus where vare_id = '$id' and lager = '$x'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$lagerbeh[$x]=$r2['beholdning']*1;
			$lagersum+=$b_antal[$x];
			if ($lagerbeh[$x]!=$b_antal[$x] && !count($vare_varianter) && !count($variantVarerId))  {
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
			</td><td><b>".findtekst(2045,$sprog_id)."</b></td></tr>";
			for ($x=1;$x<=count($lagernavn);$x++) {
				if (!isset($lagerid[$x])) $lagerid[$x]=0;
				print "<tr><td colspan=\"2\"><input type=\"hidden\" name=\"lagerid[$x]\" value=\"$lagerid[$x]\">$lagernavn[$x]</td>";
				print "<td colspan=\"4\"><input class=\"inputbox\" type=\"text\" size=\"25\" name=\"lagerlok[$x]\"";
				print "value=\"$lagerlok[$x]\" onchange=\"javascript:docChange = true;\"></td>";
			}
			print "<tr><td colspan=\"6\"><hr></td></tr>";
		} else {
			print "<tr><td colspan=\"2\">".findtekst(2045,$sprog_id)."</td>";
			print "<td colspan=\"4\"><input class=\"inputbox\" type=\"text\" size=\"25\" name=\"location\" ";
			print "value=\"$location\" onchange=\"javascript:docChange = true;\"></td>";
		}
	}
	print "<input type='hidden' name='stockItem' value='$stockItem'>";
	print "<tr><td colspan=\"2\">".findtekst(2046,$sprog_id)."</td>";
	print "<td colspan=\"4\"><input class=\"inputbox\" type=text size=25 name=folgevarenr value=\"$folgevarenr\" ";
	print "onchange=\"javascript:docChange = true;\"></td>";
	if ($operation) {
		print "<tr><td colspan=\"2\">Montage</td><td colspan=\"4\">";
		print "<input class=\"inputbox\" type=text style='text-align:right;' size='8' name='montage'
		value=\"".dkdecimal($montage)."\"";
		print "onchange=\"javascript:docChange = true;\"></td>";
		print "<tr><td colspan=\"2\">Demontage</td><td colspan=\"4\">";
		print "<input class=\"inputbox\" type=text style='text-align:right;' size='8' name='demontage'
		value=\"".dkdecimal($demontage)."\"";
		print "onchange=\"javascript:docChange = true;\"></td>";
		print "<tr><td colspan=\"2\"> Operation nr:</td><td colspan=\"4\">";
		print "<input class=\"inputbox\" type=text size='5' style='text-align:right;' name='operation' value=\"$operation \">";
	}	elseif ($stockItem) {
		$i = $incomming = $outgoing = 0;
		$qtxt = "update ordrelinjer set leveret=0 where leveret is NULL and vare_id=$id";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "select ordrer.id, ordrer.ordrenr, ordrer.levdate, (antal-leveret) as incomming from ordrer,ordrelinjer ";
		$qtxt.= "where ordrer.art = 'KO' and (ordrer.status='1' or ordrer.status='2') ";
		$qtxt.= "and ordrelinjer.ordre_id=ordrer.id and (antal-leveret) > 0 and vare_id='$id'";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		$orderInOutput = "<table style = 'width:166px' border = '1' align = 'center' bordercolor = '#FFFFFF'><tr>";
		$orderInOutput.= "<td>Nr</td><td align = 'center'>Antal</td><td align = 'center'>Dato</td></tr>\n";
		while ($r=db_fetch_array($q)) {
			$orderIdIn[$i]  = $r['id'];
			$orderNoIn[$i]  = $r['ordrenr'];
			$orderLdate[$i] = $r['levdate'];
			$orderqtyIn[$i] = $r['incomming'];
			$incomming     += $r['incomming']; #20200310-2
			$onclick = "window.open(\"../kreditor/ordre.php?id=$orderIdIn[$i]&ro=1&returside=../includes/luk.php\", \"_blank,\")";
			$orderInOutput.= "<tr><td align = 'right' onclick = '$onclick'><u>$orderNoIn[$i]</u></td>";
			$orderInOutput.= "<td align = 'right'>".dkdecimal($orderqtyIn[$i],0)."</td>";
			$orderInOutput.= "<td align = 'center'>".dkdato($orderLdate[$i])."</td></tr>\n";
			$i++;
		}
		$orderInOutput.= "</table>\n";
		#		if (count($lagernavn) >= 1) { #20200310-1
		$i = 0;
		$qtxt = "select ordrer.id, ordredate, levdate, ordrer.ordrenr, (antal-leveret) as outgoing from ordrer,ordrelinjer ";
		$qtxt.= "where ordrer.art = 'DO' and (ordrer.status='1' or ordrer.status='2') ";
		$qtxt.= "and ordrelinjer.ordre_id=ordrer.id  and (antal-leveret) > 0 and vare_id='$id'";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		$orderOutOutput = "<table style = 'width:166px' border = '1' align = 'center' bordercolor = '#FFFFFF'><tr>";
		$orderOutOutput.= "<td>Nr</td><td align = 'center'>Antal</td><td align = 'center'>Dato</td></tr>\n";
		while ($r=db_fetch_array($q)) {
			$orderIdOut[$i]  = $r['id'];
			$orderNoOut[$i]  = $r['ordrenr'];
			($r['levdate'])?$orderLdate[$i] =$r['levdate']:$orderLdate[$i] =$r['ordredate'];
			$orderqtyOut[$i] = $r['outgoing'];
			$outgoing       += $r['outgoing'];
			$onclick = "window.open(\"../debitor/ordre.php?id=$orderIdOut[$i]&ro=1&returside=../includes/luk.php\", \"_blank,\")";
			$mouseover = "onmouseover = 'style=\"cursor: pointer;\"'";
			$orderOutOutput.= "<tr><td align = 'right' onclick = '$onclick' $mouseover><u>$orderNoOut[$i]</u></a></td>";
			$orderOutOutput.= "<td align = 'right'>".dkdecimal($orderqtyOut[$i],0)."</td>";
			$orderOutOutput.= "<td>".dkdato($orderLdate[$i])."</td></tr>\n";
			$i++;
		}
		$orderOutOutput.= "</table>\n";
#		}
		print "<tr><td>".findtekst(2046,$sprog_id)."</td><td>Min:</td><td width=\"5%\" align='right'>";
		print "<input class=\"inputbox\" type=\"text\" size=\"5\" style=\"text-align:right\" name=\"min_lager\" ";
		print "value=\"". dkdecimal($min_lager,0). "\"></td>";
		print "<td width=\"5%\">Max:</td><td colspan=\"2\" align='right' >";
		print "<input class=\"inputbox\" type=\"text\" size=\"5\" style=\"text-align:right\" name=\"max_lager\" ";
		print "value=\"". dkdecimal($max_lager,0) ."\"></td></tr>";
		if (count($lagernavn)) {
			if ($beholdning!=$lagersum) {
				db_modify("update varer set beholdning='$lagersum' where id='$id'",__FILE__ . " linje " . __LINE__);
			}
			for ($x=1;$x<=count($lagernavn);$x++) {
				($x==1)?print "<tr><td>Aktuel</td>":print "<tr><td></td>";
				if (($fifo && !$samlevare) || count($variantVarerId)) {
				print "<td>$lagernavn[$x]</td>";
				print "<td align='right'><INPUT class='inputbox' READONLY='readonly' size='5' style='text-align:right' ";
				print "name='ny_beholdning' value='$lagerbeh[$x]' onchange='javascript:docChange = true;'>";
				print "<INPUT type = 'hidden' name='ny_beholdning' value='$lagerbeh[$x]'>"; #20210308
				print "<INPUT type = 'hidden' name='ny_lagerbeh[$x]' value='$lagerbeh[$x]'>"; #20210308
			} else {
				print "<td>$lagernavn[$x]</td><td align='right'>";
				print "<input class='inputbox' type='text' size='5' style='text-align:right' name='ny_lagerbeh[$x]' ";
				print "value='$lagerbeh[$x]' onchange='javascript:docChange = true;'>";
			}
			print "<input type='hidden' name='lagerbeh[$x]' value='$lagerbeh[$x]'></td>";
			if ($x==count($lagernavn)) {
				$qtxt="select * from stocklog where item_id = $id order by id desc limit 5";
					$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
					$usNa=array();
					$s=0;
					while ($r=db_fetch_array($q)) {
						$usNa[$s]=$r['username'];
						$init[$s]=$r['initials'];
							$reas[$s]=db_escape_string($r['reason']);
						$corr[$s]=dkdecimal($r['correction']);
						$daTi[$s]=date("d-m-Y H:i",$r['logtime']);
						$s++;
					}
					if ($s) {
						($linjebg!="bgcolor=$bgcolor")?$linjebg="bgcolor=$bgcolor":$linjebg="bgcolor=$bgcolor5";
						$txt = "<table><tr $linjebg><td>Bruger</td><td>Initialer</td><td>Antal</td><td>Tidspkt</td></tr>";
						for ($s=0;$s<count($usNa);$s++) {
							($linjebg!="bgcolor=$bgcolor")?$linjebg="bgcolor=$bgcolor":$linjebg="bgcolor=$bgcolor5";
							$txt.= "<tr $linjebg><td>$usNa[$s]</td>";
							$txt.= "<td>$init[$s]</td><td align=right>$corr[$s]</td><td>$daTi[$s]</td></tr>";
						}
						$txt.= "</table>";
						print "<td colspan='2'><td align= 'center'><a href='stockLog.php?id=$id'>";
						print "<span onmouseover=\"return overlib('$txt', WIDTH=800);\" onmouseout=\"return nd();\">";
						print "Log</span></td></a>";
					}
				}
				print "</tr>";
			}
		} else {
			print "<tr><td></td>";
			if (($fifo && !$samlevare) || count($vare_varianter)) {
				print "<td>Aktuel</td><td align='right'>";
				print "<INPUT class=\"inputbox\" READONLY=\"readonly\" size=\"5\" style=\"text-align:right\" ";
				print "name=\"ny_beholdning\" value=\"$beholdning\" onchange=\"javascript:docChange = true;\">";
			} else {
				print "<td>Aktuel</td><td align='right'><input class=\"inputbox\" type=\"text\" size=\"5\" ";
				print "style=\"text-align:right\" name=\"ny_beholdning\" value=\"$beholdning\" ";
				print "onchange=\"javascript:docChange = true;\">";
			}
		}
		print "<input type=\"hidden\" name=\"beholdning\" value=\"$beholdning\"></td></tr>";
#		$title='Antal ikke modtaget i godkendt købsordre';
		$overlib4="<span class='CellComment'>$orderInOutput</span>";
		print "<tr><td>Købs&nbsp;ordrer<td>";
		print "<td align='right' class='CellWithComment'><span >".dkdecimal($incomming,0)."&nbsp; $overlib4</span></td></tr>";
#		$title='Antal ikke leveret i godkendt salgsordre';
		$overlib4="<span class='CellComment'>$orderOutOutput</span>";
		print "<tr><td>Salgs&nbsp;ordrer<td>";
		print "<td align='right' class='CellWithComment'><span >".dkdecimal($outgoing,0)."&nbsp; $overlib4</span></td></tr>";
		print "</td></tr>";
	}
		($provisionsfri)?$provisionsfri="checked":$provisionsfri="";
		print "<tr><td colspan=\"2\" align='right'>".findtekst(2048,$sprog_id)."</td><td align=\"center\"><input class=\"inputbox\"
		type=\"checkbox\" name=\"provisionsfri\" $provisionsfri></td>";
		if ($shopurl) {
			if ($shop_id) {
				print "<tr><td colspan=\"2\">
				<input type=\"hidden\" name=\"publiceret\" value=\"$publiceret\">
				Shop ID (klik for at fjerne)</td><td align=\"center\"><a href=\"slet_shopbinding.php?id=$id\">$shop_id</a></td>";
			} else {
				($publiceret)?$publiceret="checked":$publiceret="";
				print "<tr><td colspan=\"2\">Publiceret</td><td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"publiceret\" $publiceret></td>";
			}
		}
?>
