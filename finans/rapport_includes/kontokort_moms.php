<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport_includes/kontokort_moms.php -- ver 5.0.0 -- 2026-04-29 --
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ------------------------------------------------------------------------------
//
// 20190924 PHR Added option 'Poster uden afd". when "afdelinger" is used. $afd='0' 
// 20210107 PHR Added totals for each account. 
// 20210107 PHR Corrected error in 'deferred financial year'.
// 20210125 PHR Added csv option.
// 20210211 PHR some cleanup
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding 
// 20260210 PHR Miscalculating when simulating
// 20260320 PHR Typo
// 20260429 LOE Updated the top menu and made the report header sticky when scrolling.
// 20260430 LOE Created standalone version of kontokort report for easy navigation

function kontokort_moms($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
                        $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart,
                        $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,
                        $simulering, $lagerbev, $page = 1, $per_page = 50) {

	global $afd_navn,$ansatte,$ansatte_id;
	global $bgcolor,$bgcolor4,$bgcolor5;
	global $connection;
	global $db;
	global $md,$menu;
	global $prj_navn_fra,$prj_navn_til;
	global $sprog_id;
	global $top_bund;

	$title = 'Rapport • Kontokort med Moms';
	
	$csvfile="../temp/$db/rapport.csv";
	$csv=fopen($csvfile,"w");
	$query = db_select("select firmanavn from adresser where art='S'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) $firmanavn=$row['firmanavn'];
	$sim_kontonr=array();

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

#	list ($aar_fra, $maaned_fra) = explode(" ", $maaned_fra);
#	list ($aar_til, $maaned_til) = explode(" ", $maaned_til);

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);
	$aar_fra=trim($aar_fra);
	$aar_til=trim($aar_til);

	$konto_fra=trim($konto_fra);
	$konto_til=trim($konto_til);
	
	$mf=$maaned_fra;
	$mt=$maaned_til;

	for ($x=1; $x<=12; $x++){
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
		if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
		if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
#	$regnaar=$row[kodenr];
	$startmaaned=$row['box1']*1;
	$startaar=$row['box2']*1;
	$slutmaaned=$row['box3']*1;
	$slutaar=$row['box4']*1;
	$slutdato=31;

	if ($aar_fra < $aar_til) { #20210107
		if ($maaned_til > $slutmaaned ) $aar_til = $aar_fra;
		elseif ($maaned_fra < $startmaaned ) $aar_fra = $aar_til;
	}

	$regnaarstart= $startaar. "-" . $startmaaned . "-" . '01';

	if ($aar_fra) $startaar=$aar_fra;
	if ($aar_til) $slutaar=$aar_til;
	if ($maaned_fra) $startmaaned=$maaned_fra;
	if ($maaned_til) $slutmaaned=$maaned_til;
	if ($dato_fra) $startdato=$dato_fra;
	if ($dato_til) $slutdato=$dato_til;

	while (!checkdate($startmaaned,$startdato,$startaar)) {
		$startdato=$startdato-1;
		if ($startdato<28) break 1;
	}
	
	while (!checkdate($slutmaaned,$slutdato,$slutaar)) {
		$slutdato=$slutdato-1;
		if ($slutdato<28) break 1;
	}
	if (strlen($startdato)<2) $startdato='0'.$startdato; 
	if (strlen($slutdato)<2) $slutdato='0'.$slutdato ;

	$regnstart = $startaar. "-" . $startmaaned . "-" . $startdato;
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
	
	$x=0;
	$momsq=NULL;
	$q=db_select("select * from grupper where art='SM' or ART='KM' or art='EM' order by art",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		if (trim($r['box1'])) {
			$x++;
			$momsart[$x]=$r['kode'];
			$momskonto[$x]=trim($r['box1']);
			$momssats[$x]=$r['box2'];
			if (!strpos($momsq,$momskonto[$x])) {
				($momsq)?$momsq.=" or kontonr = '$momskonto[$x]'":$momsq.="and (kontonr = '$momskonto[$x]'"; 
			}	
		}
	}
	if ($momsq) $momsq.=")";
	$momsantal=$x;
	include("../includes/topline_settings.php");
	print "<div style=\"position: sticky; top: 0; z-index: 100; background-color: #eeeef0;\">";
		#########
		$tilbage_icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
		#########
		print "<table bgcolor='#eeeef0' width = 100% cellpadding='0' cellspacing='0' border='0' id='tableA'><tbody>";
		print "<tr><td colspan=8 align=center>";
		print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody>"; #B

	print "<td width=\"5%\">$color
			<a href=\"javascript:confirmClose('rapport.php?rapportart=kontokort_moms&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev','')\" accesskey=L>
			<button class='headerbtn' type='button' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
	print "$tilbage_icon" .findtekst('30|Tilbage', $sprog_id)."</button></a></td>";
	print "<td width='75%' align='center' style='$topStyle'>".findtekst('516|Rapport - kontokort med moms', $sprog_id)."</td>\n";
	print "<td width='5%' align='center' style='$buttonStyle'><a href='$csvfile' style='color:#ffffff'>csv</a></td>";
	print "</tbody></table>"; #B slut
	print "</td></tr>";
	#######################
		
		?>
			<style>
			/* Existing styles for buttons */
			.headerbtn, .center-btn {
				display: flex;
				align-items: center;
				text-decoration: none;
				gap: 5px;
			}
			a:link{
					text-decoration: none; 
				}

			</style>
		<?php

	#######################
	print "<table style='width:100%; border-collapse:collapse;'>";

/* HEADER */
print "<thead>";
print "<tr>";
print "<th colspan='3' style='text-align:left;'>
        <big><big><big>".findtekst(516,$sprog_id)."</big></big></big>
      </th>";

print "<th colspan='4' style='text-align:right; white-space:nowrap;'>
        <b>Regnskabs&aring;r </b>$regnaar
      </th>";
print "</tr>";
print "</thead>";

/* DATA */
print "<tbody>";
print "<tr>";

if ($startdato < 10) $startdato = "0".($startdato*1);

print "<td colspan='3'></td>";

print "<td colspan='4' style='text-align:right; white-space:nowrap;'>
        $startdato/$mf $startaar - $slutdato/$mt $slutaar
      </td>";

print "</tr>";
print "</tbody>";

print "</table>";
	if ($ansat_fra) {
		if (!$ansat_til || $ansat_fra==$ansat_til) print "<tr><td><b>Medarbejder:</b></td><td>$ansatte</td></tr>";
		else print "<tr><td><b>Medarbejdere:</b></td><td>$ansatte</td></tr>";
	}
	if ($afd||$afd=='0') print "<tr><td><b>Afdeling:</b></td><td>$afd_navn</td></tr>";
	if ($projekt_fra) {
		print "<td>Projekt:</td><td>";
#		print "<tr><td>Projekt $prj_navn_fra</td>";
		if (!strstr($projekt_fra,"?")) {
			if ($projekt_til && $projekt_fra != $projekt_til) print "Fra: $projekt_fra, $prj_navn_fra<br>Til : $projekt_til, $prj_navn_til";
			else print "$projekt_fra, $prj_navn_fra"; 
		} else print "$projekt_fra, $prj_navn_fra";
		print "</td></tr>";
	}
	print "<tr><td colspan=5><big><b>$firmanavn</b></big></td></tr>";
	
	$dim='';
	if ($afd||$afd=='0'||$ansat_fra||$projekt_fra) {
		if ($afd||$afd=='0') $dim = "and afd = $afd ";
		if ($ansat_fra && $ansat_til) {
			$tmp=str_replace(","," or ansat=",$ansatte_id);
			$dim = $dim." and (ansat=$tmp) ";
		}
		elseif ($ansat_fra) $dim = $dim."and ansat = '$ansat_fra' ";
		$projekt_fra=str2low($projekt_fra);
		$projekt_til=str2low($projekt_til);
		if ($projekt_fra && $projekt_til && $projekt_fra!=$projekt_til) $dim = $dim." and lower(projekt) >= '$projekt_fra' and lower(projekt) <= '$projekt_til' ";
		elseif ($projekt_fra) {
			$tmp=str_replace("?","_",$projekt_fra);
			if (substr($tmp,-1)=='_') {
				while (substr($tmp,-1)=='_') $tmp=substr($tmp,0,strlen($tmp)-1);
				$tmp=str2low($tmp)."%";
			}
			$dim = $dim."and lower(projekt) LIKE '$tmp' ";
		}
	}
	###########
	 // Pagination tracking (cross-account, same pattern as kontokort)
    $rows_to_skip = ($page - 1) * $per_page;
    $rows_printed = 0;
    $total_rows   = 0;

	#########
	$x=0;
	$valdate=array();
	$valkode=array();
	$q=db_select("select * from valuta order by gruppe,valdate desc",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$y=$x-1;	
		if ((!$x) || $r['gruppe']!=$valkode[$x] || $valdate[$x]>=$regnstart) {
			$valkode[$x]=$r['gruppe'];
			$valkurs[$x]=$r['kurs'];
			$valdate[$x]=$r['valdate'];
			$x++;
		}
	}

	$x=0;$kontonr=array();
	$qtxt="select * from kontoplan where regnskabsaar='$regnaar' and kontonr>='$konto_fra' and kontonr<='$konto_til' order by kontonr";
	$q= db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)){
		if (!in_array($row['kontonr'],$kontonr) && (trim($row['moms']) || $simulering)) {
			$x++;
			$kontonr[$x]=(int)$row['kontonr'];
			$kontobeskrivelse[$x]=$row['beskrivelse'];
			$kontomoms[$x]=$row['moms'];
			$kontovaluta[$x]=$row['valuta'];
			$kontokurs[$x]=$row['valutakurs'];
			if (!$dim && $row['kontotype']=="S") $primo[$x]=afrund($row['primo'],2);
			else $primo[$x]=0;
			if ($primo[$x] && $kontovaluta[$x]) {
				for ($y=0;$y<=count($valkode);$y++){
					if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $regnstart) {
						$primokurs[$x]=$valkurs[$y];
						break 1;
					}
				}
			} else $primokurs[$x]=100;
		}
	}
	$kontoantal=$x;
	$ktonr=array();
	$x=0;
	$qtxt = "select kontonr,projekt from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' order by transdate,bilag,id";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		if (!in_array($r['kontonr'],$ktonr)) {
			$x++;
			$ktonr[$x]=$r['kontonr'];
		}
	}
	if ($simulering) {
		$qtxt = "select kontonr from simulering where transdate>='$regnaarstart' and transdate<'$regnslut' order by transdate,bilag,id";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			if (!in_array($r['kontonr'],$ktonr)) {
				$x++;
				$ktonr[$x]=$r['kontonr'];
			}
		}
	}
	$kontosum=0;

	$founddate=false;
	

	#############
	print "</tbody></table>";
	print "</div>"; // closes sticky wrapper
	print "<div style=\"overflow-y: auto; max-height: calc(100vh - 140px);\">";
	print "<table width='100%' cellpadding='0' cellspacing='0' border='0' id='datapg' style='table-layout: fixed; border-collapse: collapse;'>";
	print "<thead style='position: sticky; top: 0; background: white; z-index: 10;'>";
	print "<tr>";
	print "  <th style='width: 100px; text-align: left;'>Dato</th>";
	print "  <th style='width: 80px; text-align: left;'>Bilag</th>";
	print "  <th style='text-align: left;'>Tekst</th>";
	print "  <th style='width: 100px; text-align: right;'>Beløb</th>";
	print "  <th style='width: 90px; text-align: right;'>Moms</th>";
	print "  <th style='width: 110px; text-align: right;'>Incl. moms</th>";
	print "</tr>";
	print "</thead>";
	print "<tbody>";
	#############
	fwrite($csv, "Dato;Bilag;Tekst;". mb_convert_encoding('Beløb', 'ISO-8859-1', 'UTF-8') .";Moms;Incl. moms\n");

		#######
		for ($x = 1; $x <= $kontoantal; $x++) {
				if (in_array($kontonr[$x], $ktonr) || $primo[$x]) {
					$cnt = db_fetch_array(db_select(
						"SELECT COUNT(*) as c FROM transaktioner
						WHERE kontonr=$kontonr[$x]
						AND transdate>='$regnstart' AND transdate<='$regnslut' $dim",
						__FILE__ . " linje " . __LINE__
					));
					$total_rows += (int)$cnt['c'];
				}
			}
			$total_pages = max(1, ceil($total_rows / $per_page));

		######

	for ($x=1; $x<=$kontoantal; $x++){
		$linjebg=$bgcolor5;
		if (in_array($kontonr[$x],$ktonr) || $primo[$x]){

            // Skip entire account if all its rows fall before our page window
            $acct_cnt = (int)db_fetch_array(db_select(
                "SELECT COUNT(*) as c FROM transaktioner
                 WHERE kontonr=$kontonr[$x]
                   AND transdate>='$regnstart' AND transdate<='$regnslut' $dim",
                __FILE__ . " linje " . __LINE__
            ))['c'];
            if ($rows_to_skip >= $acct_cnt) {
                $rows_to_skip -= $acct_cnt;
                continue;
            }
			print "<tr><td colspan=6><hr></td></tr>";

				print "<tr bgcolor=\"$bgcolor5\">
						<td></td>
						<td></td>
						<td colspan=4>
							<b>$kontonr[$x]</b> : 
							<b>$kontobeskrivelse[$x]</b> : 
							<b>$kontomoms[$x]</b>
						</td>
					</tr>";

				fwrite(
					$csv,
					";;" . mb_convert_encoding(
						"$kontonr[$x] : $kontobeskrivelse[$x] : $kontomoms[$x]",
						'ISO-8859-1',
						'UTF-8'
					) . "\n"
				);

			print "<tr><td colspan=6><hr></td></tr>";
	#		fwrite($csv, ";;;;;;;");
			$xMomsSum=$momsSum=0;
			$query = db_select("select debet, kredit from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)){
			 	$kontosum+=afrund($row['debet'],2)-afrund($row['kredit'],2);
			}

// From here simulation is calculated			
			
			if ($simulering) {
				$query = db_select("select debet, kredit from simulering where kontonr=$kontonr[$x] and transdate>='$regnaarstart' 	and transdate<'$regnslut' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
				while ($row = db_fetch_array($query)){
					$kontosum+=afrund($row['debet'],2)-afrund($row['kredit'],2);
				}
#			$tmp=dkdecimal($kontosum);
#			if (!$dim) print "<tr bgcolor=\"$linjebg\"><td></td><td></td><td>  Primosaldo </td><td></td><td></td><td align=right>$tmp </td></tr>";
			$print=1;
			$sim=0;
			$qtxt = "select * from simulering where kontonr=$kontonr[$x] and transdate>='$regnstart' and transdate<='$regnslut' $dim order by transdate,bilag,id";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)){
				$sim_kladde_id[$sim]=$r['kladde_id'];
				$sim_transdate[$sim]=$r['transdate'];
				$sim_bilag[$sim]=$r['bilag'];
				$sim_kontonr[$sim]=$r['kontonr'];
				$sim_beskrivelse[$sim]=$r['beskrivelse'];
				$sim_xmoms[$sim]=$r['debet']-$r['kredit'];
				$sim_moms[$sim]=$r['moms'];
				$sim_debet[$sim]=$r['debet'];
				$sim_kredit[$sim]=$r['kredit'];
				$sim++;
					if ($kontovaluta[$x]) {
						for ($y=0;$y<=count($valkode);$y++){
							if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $sim_transdate[$tr]) {
								$sim_transkurs[$tr]=$valkurs[$y];
								break 1;
							}
						}
					} else $sim_transkurs[$tr]=100; 
				}
			}
			
// To here simulation is calculated.			
			
			
			
			$tr=0;$transdate=array();
			$qtxt = "select * from transaktioner where kontonr='$kontonr[$x]' and transdate>='$regnstart' and transdate<='$regnslut' $dim order by transdate,bilag,id";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)){
				$transdate[$tr]=$r['transdate'];
				$bilag[$tr]=$r['bilag'];
				$beskrivelse[$tr]=$r['beskrivelse'];
				$debet[$tr]=$r['debet'];
				$kredit[$tr]=$r['kredit'];
				$kladde_id[$tr]=$r['kladde_id'];
				$moms[$tr]=$r['moms'];
				$logdate[$tr]=$r['logdate'];
				$logtime[$tr]=$r['logtime'];
				$transvaluta[$tr]=$r['valuta'];
				if ($kontovaluta[$x]) {
					for ($y=0;$y<=count($valkode);$y++){
						if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $transdate[$tr]) {
							$transkurs[$tr]=$valkurs[$y];
							break 1;
						}
					}
				} else $transkurs[$tr]=100; 
				$tr++;
			}
			
			
			
			if (!isset ($jsvars)) $jsvars = NULL;
			if (!isset ($sim_kontonr)) $sim_kontonr = array();;

// From here simulation is printed
			if (!count($transdate) && in_array($kontonr[$x],$sim_kontonr)) {
				for ($sim=0;$sim<count($sim_transdate);$sim++) {
					if ($sim_kontonr[$sim] == $kontonr[$x]) {
					print "<tr bgcolor=\"$linjebg\"><td>  ".dkdato($sim_transdate[$sim])." </td>";
					fwrite($csv, __line__.dkdato($sim_transdate[$sim]).";");
					if ($sim_kladde_id[$sim]) {
						print "<td onMouseOver=\"this.style.cursor = 'pointer'\"; ";
						print "onClick=\"javascript:kassekladde=window.open('kassekladde.php?kladde_id=$sim_kladde_id[$sim]&returside=../includes/luk.php',";
						print "'kassekladde','$jsvars')\">$sim_bilag[$sim]</td>";
						fwrite($csv, "$sim_bilag[$sim];");
					} else {
						print "<td></td>";
						fwrite($csv, ";");
					}
					print "<td>$sim_kontonr[$sim] : $sim_beskrivelse[$sim] (simuleret) </td>";
					fwrite($csv, "". mb_convert_encoding("$sim_kontonr[$sim] : $sim_beskrivelse[$sim] (simuleret)", 'ISO-8859-1', 'UTF-8').";");
					$xmoms=$sim_debet[$sim]-$sim_kredit[$sim];
					$xMomsSum+=$xmoms;
					if ($kontovaluta[$x]) {
						if ($transvaluta[$tr]=='-1') $tmp=0;
						else $tmp=$sim_debet[$sim]*100/$sim_transkurs[$sim];
						$title="DKK ".dkdecimal($sim_debet[$sim]*1,2)." Kurs: ".dkdecimal($sim_transkurs[$sim],2);
					}	else {
						$tmp=$sim_debet[$sim]-$sim_kredit[$sim];
						$title=NULL;
					}
#					$xMomsSum+=$tmp;
					print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
					fwrite($csv, "\"".dkdecimal($tmp,2)."\";");
					if ($kontovaluta[$x]) {
						$tmp=$sim_moms[$sim]*100/$sim_transkurs[$sim];
						$title="DKK ".dkdecimal($sim_kredit[$sim]*1,2)." Kurs: ".dkdecimal($sim_transkurs[$sim],2);
					}	else {
						$tmp=$sim_moms[$sim];
						$title=NULL;
					}
					$momsSum+=$tmp;
					print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
					fwrite($csv, "\"".dkdecimal($tmp,2)."\";");
					$kontosum = afrund($sim_debet[$sim],2)-afrund($sim_kredit[$sim],2);
					if ($kontovaluta[$x]) {
						$tmp=$kontosum*100/$sim_transkurs[$sim];
						$title="DKK ".dkdecimal($kontosum*1,2)." Kurs: ".dkdecimal($sim_transkurs[$sim],2);
					}	else {
						$tmp=$kontosum+$sim_moms[$sim];
						$title=NULL;
					}
					print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
					fwrite($csv, "\"".dkdecimal($tmp,2)."\";\n");
				}
			}}
			
// To here simulation is printed
			
			
			for ($tr=0;$tr<count($transdate);$tr++) {

                $debet_val  = afrund($debet[$tr], 2);
                $kredit_val = afrund($kredit[$tr], 2);

                if ($rows_to_skip > 0) {
                    $kontosum += $debet_val - $kredit_val;
                    $rows_to_skip--;
                    continue;
                }
                if ($rows_printed >= $per_page) break;

				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				print "<tr bgcolor=\"$linjebg\"><td>".dkdato($transdate[$tr])."</td>";
				if ($kladde_id[$tr]) {
					print "<td onMouseOver=\"this.style.cursor = 'pointer'\"; ";
					print "onClick=\"javascript:kassekladde=window.open('kassekladde.php?kladde_id=$kladde_id[$tr]&returside=../includes/luk.php',";
					print "'kassekladde','$jsvars')\">$bilag[$tr]</td>";
				} else print "<td></td>";
				print "<td>$kontonr[$x] : $beskrivelse[$tr]</td>";
				fwrite($csv, dkdato($transdate[$tr]) .";$bilag[$tr];". mb_convert_encoding("$kontonr[$x] : $beskrivelse[$tr]", 'ISO-8859-1', 'UTF-8'). "\";");
				$xmoms=$debet[$tr]-$kredit[$tr];
				$xMomsSum+=$xmoms;
				print "<td align=right>".dkdecimal($xmoms,2)."</td>";
				fwrite($csv, "\"".dkdecimal($xmoms,2)."\";");
#				$moms=$moms[$tr];
				if (!$moms[$tr] && $moms[$tr]!='0.000' && $bilag[$tr]&& $kladde_id[$tr]) {
					$qtxt = "select * from transaktioner where transdate='$transdate[$tr]' and bilag='$bilag[$tr]' and logdate='$logdate[$tr]' and logtime='$logtime[$tr]'and beskrivelse='$beskrivelse[$tr]' $momsq";
					$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)){
						$amount=$r2['debet']-$r2['kredit'];
						for ($i=1;$i<=$momsantal;$i++) {
							$tmp=round(abs($xmoms-$amount*100/$momssats[$i]),2);
							if ($r2['kontonr'] == $momskonto[$i] && $tmp<0.1) $moms=$amount; 
						}
					}
				}
				$momsSum+=$moms[$tr];
				print "<td align=right>".dkdecimal($moms[$tr],2)."</td>";
				fwrite($csv, "\"".dkdecimal($moms[$tr],2)."\";");
				$mmoms=$xmoms+$moms[$tr];
				print "<td align=right>".dkdecimal($mmoms,2)."</td></tr>";
				fwrite($csv, "\"".dkdecimal($mmoms,2)."\"\n");
				$rows_printed++;
				if (in_array($kontonr[$x],$sim_kontonr) && $transdate[$tr]!=$transdate[$tr+1]) {
					for ($sim=0;$sim<count($sim_kontonr);$sim++) {
						if ($kontonr[$x]==$sim_kontonr[$sim] && $transdate[$tr] == $sim_transdate[$sim]) {
							print "<tr bgcolor=\"$linjebg\"><td>  ".dkdato($sim_transdate[$sim])." </td><td>$sim_bilag[$sim] </td><td>$sim_kontonr[$sim] : $sim_beskrivelse[$sim] (simuleret) </td>";
							fwrite($csv, dkdato($sim_transdate[$sim]).";$sim_bilag[$sim];$sim_kontonr[$sim] : $sim_beskrivelse[$sim] (simuleret);");
							if ($kontovaluta[$x]) {
								if ($transvaluta[$tr]=='-1') $tmp=0;
								else $tmp=$sim_debet[$sim]*100/$transkurs[$tr]-$sim_kredit[$sim]*100/$transkurs[$tr];
								$title="DKK ".dkdecimal(($sim_debet[$sim]-$sim_kredit[$sim])*1,2)." Kurs: ".dkdecimal($transkurs[$tr],2);
							}	else {
								$tmp=$sim_debet[$sim]-$sim_kredit[$sim];
								$title=NULL;
							}
							$xMomsSum+=$tmp;
							print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
							fwrite($csv, dkdecimal($tmp,2).";");

/*
							if ($kontovaluta[$x]) {
								if ($transvaluta[$tr]=='-1') $tmp=0;
								else $tmp=$sim_kredit[$sim]*100/$transkurs[$tr];
								$title="DKK ".dkdecimal($sim_kredit[$sim]*1,2)." Kurs: ".dkdecimal($transkurs[$tr],2);
							}	else {
								$tmp=$sim_kredit[$sim];
								$title=NULL;
							}
*/
							$tmp = $sim_moms[$sim];
							$momsSum+=$tmp;
							print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
							fwrite($csv, dkdecimal($tmp,2).";");
							$kontosum=afrund($sim_debet[$sim],2)-afrund($sim_kredit[$sim],2);
							if ($kontovaluta[$x]) {
								if ($transvaluta[$tr]=='-1') $tmp=0;
								else $tmp=$kontosum*100/$transkurs[$tr];
								$title="DKK ".dkdecimal($kontosum*1,2)." Kurs: ".dkdecimal($transkurs[$tr],2);
							}	else {
								$tmp=$kontosum+$sim_moms[$sim];
								$title=NULL;
							}
							print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
							fwrite($csv, dkdecimal($tmp,2).";\n");
						}
					}
				}
			}
			if ($rows_printed >= $per_page) break; // stop processing further accounts
		#cho __line__." $xMomsSum<br>";
				if ($rows_printed > 0 || $xMomsSum != 0) { // only print summary if we actually rendered rows
				print "<tr><td colspan='2'></td><td><b>$kontonr[$x] : $kontobeskrivelse[$x] : $kontomoms[$x]</b></td>";
				fwrite($csv, "Sum;;". mb_convert_encoding("$kontonr[$x] : $kontobeskrivelse[$x] : $kontomoms[$x]", 'ISO-8859-1', 'UTF-8') .";");
				print "<td align='right'><b>". dkdecimal($xMomsSum,2) ."</b></td>";
				fwrite($csv, "".dkdecimal($xMomsSum,2).";");
				print "<td align='right'><b>". dkdecimal($momsSum,2) ."</b></td>";
				fwrite($csv, "".dkdecimal($momsSum,2).";");
				print "<td align='right'><b>". dkdecimal($xMomsSum+$momsSum,2) ."</b></td>";
				fwrite($csv, "".dkdecimal($xMomsSum+$momsSum,2)."\n\n");
				print "</tr>";
			}
			
		}
#if ($kontonr[$x] == 1010) exit;
	}
	#print "<tr><td colspan=6><hr></td></tr>";
	print "</tbody></table>";
	// --- Sticky pagination bar ---
    $base_url = "kontokort_moms_standalone.php?" . http_build_query([
        'regnaar'     => $regnaar,
        'maaned_fra'  => $maaned_fra,
        'maaned_til'  => $maaned_til,
        'aar_fra'     => $aar_fra,
        'aar_til'     => $aar_til,
        'dato_fra'    => $dato_fra,
        'dato_til'    => $dato_til,
        'konto_fra'   => $konto_fra,
        'konto_til'   => $konto_til,
        'rapportart'  => $rapportart,
        'ansat_fra'   => $ansat_fra,
        'ansat_til'   => $ansat_til,
        'afd'         => $afd,
        'projekt_fra' => $projekt_fra,
        'projekt_til' => $projekt_til,
        'simulering'  => $simulering,
        'lagerbev'    => $lagerbev,
    ]);

    $offsetFrom  = (($page - 1) * $per_page) + 1;
    $offsetTo    = min($total_rows, $page * $per_page);
    $prevUrl     = $base_url . '&per_page=' . $per_page . '&page=' . ($page - 1);
    $nextUrl     = $base_url . '&per_page=' . $per_page . '&page=' . ($page + 1);

    // Build rows-per-page options
    $rowCounts = [25, 50, 100, 250, 500];
    $rowCountOptions = '';
    foreach ($rowCounts as $count) {
        $sel = ($count === $per_page) ? 'selected' : '';
        $rowCountOptions .= "<option value='{$count}' {$sel}>{$count}</option>";
    }

    // Build page number buttons with ellipsis — matches render_table_footer pattern
    $pageRange = 2;
    $startPage = max(1, $page - $pageRange);
    $endPage   = min($total_pages, $page + $pageRange);

    $pageButtons = '';
    if ($startPage > 1) {
        $pageButtons .= "<a href='{$base_url}&per_page={$per_page}&page=1' class='navbutton'>1</a>";
        if ($startPage > 2) $pageButtons .= "<span>...</span>";
    }
    for ($p = $startPage; $p <= $endPage; $p++) {
        $activeStyle = ($p === $page) ? "style='text-decoration:underline; font-weight:bold;'" : "";
        $pageButtons .= "<a href='{$base_url}&per_page={$per_page}&page={$p}' class='navbutton' {$activeStyle}>{$p}</a>";
    }
    if ($endPage < $total_pages) {
        if ($endPage < $total_pages - 1) $pageButtons .= "<span>...</span>";
        $pageButtons .= "<a href='{$base_url}&per_page={$per_page}&page={$total_pages}' class='navbutton'>{$total_pages}</a>";
    }

    print "</div>"; // closes scrollable div — MUST be before the fixed bar

    echo "
    <div style='position:fixed; bottom:0; left:0; width:100%; background:#f4f4f4;
                border-top:2px solid #ddd; z-index:200; box-shadow:0 -2px 6px rgba(0,0,0,0.1);'>
        <div id='footer-box' style='display:flex; align-items:center; gap:10px;
                                    justify-content:flex-end; padding:6px 16px;'>
            <span id='page-status' style='display:flex;'>
                {$offsetFrom}-{$offsetTo}&nbsp;af&nbsp;{$total_rows}
            </span>
            |
            <span style='display:flex; align-items:center; gap:4px;'>
                <label style='font-size:0.9em; color:#666;'>Linjer pr. side</label>
                <select onchange=\"window.location.href='{$base_url}&page=1&per_page=' + this.value\"
                        style='height:24px; cursor:pointer;'>
                    {$rowCountOptions}
                </select>
            </span>
            |
            <span id='navbuttons' style='display:flex; align-items:center; gap:3px;'>
                <a href='{$prevUrl}' " . ($page <= 1 ? "style='pointer-events:none;opacity:0.4;'" : "") . ">
                    <svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'>
                        <path d='M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z'/>
                    </svg>
                </a>
                {$pageButtons}
                <a href='{$nextUrl}' " . ($page >= $total_pages ? "style='pointer-events:none;opacity:0.4;'" : "") . ">
                    <svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'>
                        <path d='M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z'/>
                    </svg>
                </a>
            </span>
        </div>
    </div>"; // closes scrollable div
	fclose($csv);

	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}
 
#################################################################################################
} //end function
?>
<style>
	
        .navbutton {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 24px;
            min-width: 24px;
            padding: 0 4px;
            border: 1px solid #ccc;
            background: #fff;
            color: #000;
            text-decoration: none;
            font-size: 0.85em;
            cursor: pointer;
            box-sizing: border-box;
        }
        .navbutton:hover {
            background-color: #e8e8e8;
        }
	#datapg td {
		padding-right: 8px;
	}
		#datapg td:nth-child(2), #datapg th:nth-child(2) {
		padding-right: 15px;
	}
	#datapg {
    border-collapse: collapse;
    width: 100%;
}
#datapg th, #datapg td {
    /* padding: 6px 8px; */
    vertical-align: top;
}
#datapg th {
    background-color: #eeeef0;
}
/* Right‑align numeric columns */
#datapg td:nth-child(4),
#datapg td:nth-child(5),
#datapg td:nth-child(6),
#datapg th:nth-child(4),
#datapg th:nth-child(5),
#datapg th:nth-child(6) {
    text-align: right;
}
/* Extra spacing for Bilag column */
#datapg td:nth-child(2), #datapg th:nth-child(2) {
    padding-right: 20px;
}

#tableA tr:nth-child(2) td:nth-child(2) {
    text-align: right;
    width: 100%;          /* pushes content to the right if the row uses table layout */
}
		
</style>