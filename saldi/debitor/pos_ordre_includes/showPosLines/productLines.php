<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/showPosLines/productLines.php ---------- lap 3.7.7----2019.05.08-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190508 Move function vis_pos_linjer here

	for ($x=1;$x<=$varelinjer;$x++) {
		($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
		if ($posnr[$x]) {
			if (!$samlevare[$x]) {
				print "<tr bgcolor=\"$linjebg\">";
				print "<td style=\"width: 19%\">$varenr[$x]</td>";
				print "<td style=\"width:7%;text-align:right\">".dkdecimal($antal[$x],2)."</td>";
				if ($lagerantal>1) print "<td style=\"width:7%;text-align:center\">$lager[$x]</td>";
				print "<td>$beskrivelse[$x]</td>";
				print "<td style=\"width:10%;text-align:right\" title=\"Kostpris ex. moms: ".dkdecimal($kostpris[$x],2)."\">";
				print dkdecimal($pris[$x],2);
				print "</td>";
			}
			if ($saet[$x]) {
				if ($saet[$x-1] != $saet[$x]) $saetpris=0;
				if ($samlevare[$x]) print "<td align=\"right\"><br></td><td style=\"width:50px;height:30px;text-align:right\">\n";
				$saetpris+=afrund($pris[$x]*$antal[$x]-($pris[$x]*$antal[$x]*$rabat[$x]/100),2); #20150214
				$pris[$x]=0;
			} else {
				print "<td style=\"width:10%;text-align:right\">".dkdecimal($pris[$x]*$antal[$x],2)."</td>";
				print "<td style=\"width:50px;height:30px;text-align:right\">\n";
			}
			if ($del_bord) {
				print "<select style=\"width:60px;height:30px;text-align:right;font-size:20px;\" name=\"delflyt[$x]\">\n";
				for ($a=0;$a<=$antal[$x];$a++) print "<option style=\"text-align:right\" value=\"$linje_id[$x]:$vare_id[$x]:$a\">$a</option>";
				print "</select>";
			} elseif ($status<'3' && !$varenr_ny && !$saet[$x]) {
				$href="pos_ordre.php?id=$id&ret=$linje_id[$x]&leveret=$leveret[$x]";
				print "<input type=\"button\" onclick=\"window.location.href='$href'\" $stil value=\"Ret\">\n";
			}
			print "</td></tr>\n";
		}
		if ($rabat[$x] && !$saet[$x] && !$rvnr) {
			($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
			if ($rabatart[$x]=="amount") {
				if ($varemomssats[$x] & $momsfri[$x]!='on') $tmp=afrund($rabat[$x]+$rabat[$x]/100*$varemomssats[$x],2)*-1;
				else $tmp=afrund($rabat[$x],2)*-1;
				if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($antal[$x],2)."</td><td>Rabat</td><td align=\"right\">".dkdecimal($tmp,2)."</td><td align=\"right\">".dkdecimal($tmp*$antal[$x],2)."</td></tr>\n";
				$sum+=afrund($tmp*$antal[$x],2);
			} else {
				$tmp=afrund($pris[$x]*$rabat[$x]/-100,2);
				if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($antal[$x],2)."</td><td>$rabat[$x]% rabat</td><td align=\"right\">".dkdecimal($tmp,2)."</td><td align=\"right\">".dkdecimal($tmp*$antal[$x],2)."</td></tr>\n";
			}
		} elseif ($saetpris && ($saet[$x+1]!=$saet[$x]) && $saet[$x]) {
			($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
			$saetpris=afrund($saetpris,2);
			$r2=db_fetch_array(db_select("select id,antal,varenr,beskrivelse,lev_varenr from ordrelinjer where samlevare='on' and ordre_id='$id' and saet='$saet[$x]'",__FILE__ . " linje " . __LINE__));
			list($lev_vnr,$srbt)=explode("|",$r2['lev_varenr']);
			$lev_vnr*=$r2['antal'];
			print "<tr bgcolor=\"$linjebg\"><td></td><td></td><td></td><td>$r2[beskrivelse]</td><td></td><td title=$srbt\" align=\"right\">".dkdecimal($lev_vnr,2)."</td>\n";
			if ($r['varenr']!=$svnr) {
				$href="pos_ordre.php?id=$id&ret=$r2[id]&saet=$saet[$x]&leveret=$leveret[$x]";
				print "<td><!--<input type=\"button\" onclick=\"window.location.href='$href'\" $stil value=\"Ret\">--></td></tr>\n";
			}
		}
		if ($status < 3) {   #|| $tilfravalg[$x] fjernet 20190424
 			if ($folgevare[$x] > 0 || $tilfravalg[$x]) {
				if ($tilfravalg[$x]) $tfvare=explode(chr(9),$tilfravalg[$x]);
				else $tfvare[0]=$folgevare[$x];
				for($fv=0;$fv<count($tfvare);$fv++) {
					($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
					if ($tfvare[$fv]>0) {
						$r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris,gruppe from varer where id = '$tfvare[$fv]'",__FILE__ . " linje " . __LINE__));
						$r2 = db_fetch_array(db_select("select box4, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
						$f_bogfkto=$r2['box4'];
						$f_momsfri=$r2['box7'];
					if ($f_momsfri){
						$f_momssats=0;
						$f_pris=$r['salgspris'];
					} else {
						$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$f_bogfkto' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
						$kodenr=substr($r2['moms'],1);
						$r2 = db_fetch_array(db_select("select box2 from grupper where kodenr = '$kodenr' and art = 'SM'",__FILE__ . " linje " . __LINE__));
						$f_momssats=$r2['box2']*1;
						$f_pris=$r['salgspris']+$r['salgspris']*$f_momssats/100;
					}
				}
				if ($posnr[$x] && $r['varenr']) print "<tr bgcolor=\"$linjebg\"><td>$r[varenr]</td><td align=\"right\">".dkdecimal($antal[$x],2)."</td><td>".stripslashes($r['beskrivelse'])."</td><td align=\"right\">".dkdecimal($f_pris,2)."</td><td align=\"right\">".dkdecimal($antal[$x]*$f_pris,2)."</td>\n";
				$sum+=afrund($antal[$x]*$f_pris,2);
				}
			}
			if ($rabatantal[$x]) {
				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				list($grupperabat,$rabattype)=explode(";",grupperabat($rabatantal[$x],$rabatgruppe[$x]));
				if ($grupperabat) {
					if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($rabatantal[$x],2)."</td><td>Rabat</td><td align=\"right\">".dkdecimal($grupperabat,2)."</td><td align=\"right\">".dkdecimal($grupperabat*$rabatantal[$x],2)."</td>\n";
					$sum+=afrund($grupperabat*$rabatantal[$x],2);
				}
			} elseif ($m_rabat[$x] && !$rabatgruppe[$x]) {
				if ($posnr[$x]) {
					($r['beskrivelse'])?$tmp=$r['beskrivelse']:$tmp='Rabat';
					print "<tr bgcolor=\"$linjebg\"><td>$r[varenr]</td><td align=\"right\">".dkdecimal($antal[$x],2)."</td><td>".stripslashes($tmp)."</td><td align=\"right\">".dkdecimal($m_rabat[$x],2)."</td><td align=\"right\">".dkdecimal($antal[$x]*$m_rabat[$x],2)."</td>\n";
				}
				 $sum+=afrund($m_rabat[$x]*$antal[$x],2);
			}
		}
	}




?>
