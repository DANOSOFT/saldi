<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport_includes/kontokort_moms.php -- ver 4.0.8 -- 2023-09-08 --
// 								LICENSE
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ------------------------------------------------------------------------------
//
// 20190924 PHR Added option 'Poster uden afd". when "afdelinger" is used. $afd='0' 
// 20210107 PHR Added totals for each account. 
// 20210107 PHR Corrected error in 'deferred financial year'.
// 20210125 PHR Added csv option.
// 20210211 PHR some cleanup

function kontokort_moms($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering,$lagerbev) {

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
	if ($row = db_fetch_array($query)) {$firmanavn=$row['firmanavn'];}
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

#	print "  <a accesskey=L href=\"rapport.php?rapportart=Kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&afd=$afd\">Luk</a><br><br>";
	if ($menu=='T') {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">"; 
		print "<div class=\"headerbtnLft headLink\"><a href=rapport.php?rapportart=kontokort_moms&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev accesskey=L title='Klik her for at komme tilbage til rapporter'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
		print "<div class=\"headerTxt\">$title</div>";     
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
		print "</div>";
		print "<div class='content-noside'>";
			print  "<table class='dataTable' border='0' cellspacing='1' width='100%'>";
#	} elseif ($menu=='S') {
#		include("../includes/sidemenu.php");
	} else {
		print "<table width=100% cellpadding=\"0\" cellspacing=\"1px\" border=\"0\" valign = \"top\" align='center'> ";
		print "<tr><td colspan=\"6\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=kontokort_moms&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\">Luk</a></td>";
		print "<td width=\"80%\" $top_bund> Rapport - kontokort med moms</td>";
		print "<td width=\"10%\" $top_bund><a href='$csvfile'>csv</a></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>";
		print "<tr><td colspan=\"4\"><big><big><big>".findtekst(516,$sprog_id)."</span></big></big></big></td>";
	}
	if (!isset ($sprog_id)) $sprog_id = NULL;

	if ($menu=='T') {
		print "<tr>";
	} else {
		print "";
	}

	print "<td colspan=8 align=left><table style=\"text-align: left; width: 50%; \" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>";
	print "<td colspan='1'><b>Regnskabs&aring;r:</b></span></td>";
	print "<td colspan='1'>$regnaar.</span></td></tr>";
	print "<tr><td><b>Periode:</b></span></td>";
	## Finder start og slut paa regnskabsaar
	if ($startdato < 10) $startdato="0".$startdato*1;	
	print "<td colspan='2'>Fra ".$startdato.". $mf<br />Til ".$slutdato.". $mt</span></td></tr>";
	if ($ansat_fra) {
		if (!$ansat_til || $ansat_fra==$ansat_til) print "<tr><td><b>Medarbejder:</b></span></td><td>$ansatte</span></td></tr>";
		else print "<tr><td><b>Medarbejdere:</b></span></td><td>$ansatte</span></td></tr>";
	}
	if ($afd||$afd=='0') print "<tr><td><b>Afdeling:</b></span></td><td>$afd_navn</span></td></tr>";
	if ($projekt_fra) {
		print "<td>Projekt:</td><td>";
#		print "<tr><td>Projekt $prj_navn_fra</td>";
		if (!strstr($projekt_fra,"?")) {
			if ($projekt_til && $projekt_fra != $projekt_til) print "Fra: $projekt_fra, $prj_navn_fra<br>Til : $projekt_til, $prj_navn_til";
			else print "$projekt_fra, $prj_navn_fra"; 
		} else print "$projekt_fra, $prj_navn_fra";
		print "</td></tr>";
	}
	print "</tbody></table></td></tr>";
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
			$kontonr[$x]=$row['kontonr']*1;
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
/*
	$fejltxt='';
	$qtxt = "select distinct(kontonr) from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' ";
	$qtxt.= "and kontonr>='$konto_fra' and kontonr<='$konto_til' and (debet != 0 or kredit != 0) $dim order by kontonr";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		if ($r['kontonr'] && !in_array($r['kontonr'],$kontonr)) {
			$fejltxt.='kontonummer :'. $r['kontonr'] .' findes ikke i kontoplanen!<br>';
		}
	}
	if ($fejltxt) print tekstboks($fejltxt);
*/
	$ktonr=array();
	$x=0;
	$qtxt = "select kontonr,projekt from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' order by transdate,bilag,id";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
#cho "$row[projekt]<br>";
		if (!in_array($r['kontonr'],$ktonr)) {
			$x++;
			$ktonr[$x]=$r['kontonr'];
		}
	}
	$qtxt = "select kontonr from simulering where transdate>='$regnaarstart' and transdate<'$regnslut' order by transdate,bilag,id";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		if (!in_array($r['kontonr'],$ktonr)) {
			$x++;
			$ktonr[$x]=$r['kontonr'];
		}
	}
	
	$kontosum=0;

	$founddate=false;
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr><td width=\"100px\">Dato</td><td width=\"60px\">Bilag</td><td>Tekst</td>";
	print "<td width=\"100px\" align=\"right\">Bel&oslash;b</td><td width=\"80px\" align=\"right\"> Moms</td>";
	print "<td width=\"100px\" align=\"right\">Incl. moms</td></tr>";
	fwrite($csv, "\"Dato\";\"Bilag\";\"Tekst\";\"". utf8_decode('Beløb') ."\";\"Moms\";\"Incl. moms\"\n");
	for ($x=1; $x<=$kontoantal; $x++){
		$linjebg=$bgcolor5;
		if (in_array($kontonr[$x],$ktonr) || $primo[$x]){
			print "<tr><td colspan=6><hr></td></tr>";
			print "<tr bgcolor=\"$bgcolor5\"><td></td><td></td><td colspan=4>$kontonr[$x] : $kontobeskrivelse[$x] : $kontomoms[$x]</tr>";
			fwrite($csv, "\"\";\"\";\"". utf8_decode("$kontonr[$x] : $kontobeskrivelse[$x] : $kontomoms[$x]") ."\";\"\";\"\"\n");
			print "<tr><td colspan=6><hr></td></tr>";
			$kontosum=$primo[$x];
			$xMomsSum=$momsSum=0;
			$query = db_select("select debet, kredit from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)){
			 	$kontosum+=afrund($row['debet'],2)-afrund($row['kredit'],2);
			}
			$query = db_select("select debet, kredit from simulering where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnslut' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
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
#cho "$valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $transdate[$tr]<br>";
						if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $sim_transdate[$tr]) {
							$sim_transkurs[$tr]=$valkurs[$y];
							break 1;
						}
					}
				} else $sim_transkurs[$tr]=100; 
			}	
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

			if (!count($transdate) && in_array($kontonr[$x],$sim_kontonr)) {
				for ($sim=0;$sim<count($sim_transdate);$sim++) {
					if ($sim_kontonr[$sim] == $kontonr[$x]) {
					print "<tr bgcolor=\"$linjebg\"><td>  ".dkdato($sim_transdate[$sim])." </td>";
				 if ($sim_kladde_id[$sim]) {
						print "<td onMouseOver=\"this.style.cursor = 'pointer'\"; ";
						print "onClick=\"javascript:kassekladde=window.open('kassekladde.php?kladde_id=$sim_kladde_id[$sim]&returside=../includes/luk.php',";
						print "'kassekladde','$jsvars')\">$sim_bilag[$sim]</td>";
					} else print "<td></td>";
					print "<td>$sim_kontonr[$sim] : $sim_beskrivelse[$sim] (simuleret) </td>";
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
					$xMomsSum+=$tmp;
					print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
					if ($kontovaluta[$x]) {
						$tmp=$sim_moms[$sim]*100/$sim_transkurs[$sim];
						$title="DKK ".dkdecimal($sim_kredit[$sim]*1,2)." Kurs: ".dkdecimal($sim_transkurs[$sim],2);
					}	else {
						$tmp=$sim_moms[$sim];
						$title=NULL;
					}
					$momsSum+=$tmp;
					print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
					$kontosum=$kontosum+afrund($sim_debet[$sim],2)-afrund($sim_kredit[$sim],2);
					if ($kontovaluta[$x]) {
						$tmp=$kontosum*100/$sim_transkurs[$sim];
						$title="DKK ".dkdecimal($kontosum*1,2)." Kurs: ".dkdecimal($sim_transkurs[$sim],2);
					}	else {
						$tmp=$kontosum;
						$title=NULL;
					}
					print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
				}
			}}
			
			for ($tr=0;$tr<count($transdate);$tr++) {		
				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				print "<tr bgcolor=\"$linjebg\"><td>  ".dkdato($transdate[$tr])."</td>";
				if ($kladde_id[$tr]) {
					print "<td onMouseOver=\"this.style.cursor = 'pointer'\"; ";
					print "onClick=\"javascript:kassekladde=window.open('kassekladde.php?kladde_id=$kladde_id[$tr]&returside=../includes/luk.php',";
					print "'kassekladde','$jsvars')\">$bilag[$tr]</td>";
				} else print "<td></td>";
				print "<td>$kontonr[$x] : $beskrivelse[$tr]</td>";
				fwrite($csv, "\"". dkdato($transdate[$tr]) ."\";\"\";\"". utf8_decode("$kontonr[$x] : $beskrivelse[$tr]"). "\";");
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
#cho "$r2[kontonr] == $momskonto[$i] && $tmp<0.1<br>";
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
				if (in_array($kontonr[$x],$sim_kontonr) && $transdate[$tr]!=$transdate[$tr+1]) {
					for ($sim=0;$sim<count($sim_kontonr);$sim++) {
						if ($kontonr[$x]==$sim_kontonr[$sim] && $transdate[$tr] == $sim_transdate[$sim]) {
							print "<tr bgcolor=\"$linjebg\"><td>  ".dkdato($sim_transdate[$sim])." </td><td>$sim_bilag[$sim] </td><td>$sim_kontonr[$sim] : $sim_beskrivelse[$sim] (simuleret) </td>";
							if ($kontovaluta[$x]) {
								if ($transvaluta[$tr]=='-1') $tmp=0;
								else $tmp=$sim_debet[$sim]*100/$transkurs[$tr];
								$title="DKK ".dkdecimal($sim_debet[$sim]*1,2)." Kurs: ".dkdecimal($transkurs[$tr],2);
							}	else {
								$tmp=$sim_debet[$sim];
								$title=NULL;
							}
							$xMomsSum+=$tmp;
							print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
							if ($kontovaluta[$x]) {
								if ($transvaluta[$tr]=='-1') $tmp=0;
								else $tmp=$sim_kredit[$sim]*100/$transkurs[$tr];
								$title="DKK ".dkdecimal($sim_kredit[$sim]*1,2)." Kurs: ".dkdecimal($transkurs[$tr],2);
							}	else {
								$tmp=$sim_kredit[$sim];
								$title=NULL;
							}
							$momsSum+=$tmp;
							print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
							$kontosum=$kontosum+afrund($sim_debet[$sim],2)-afrund($sim_kredit[$sim],2);
							if ($kontovaluta[$x]) {
								if ($transvaluta[$tr]=='-1') $tmp=0;
								else $tmp=$kontosum*100/$transkurs[$tr];
								$title="DKK ".dkdecimal($kontosum*1,2)." Kurs: ".dkdecimal($transkurs[$tr],2);
							}	else {
								$tmp=$kontosum;
								$title=NULL;
							}
							print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
						}
					}
				}
			}
			print "<tr><td colspan='2'></td><td><b>$kontonr[$x] : $kontobeskrivelse[$x] : $kontomoms[$x]</b></td>";
			fwrite($csv, "\"Sum\";\"\";\"". utf8_decode("$kontonr[$x] : $kontobeskrivelse[$x] : $kontomoms[$x]") ."\";");
			print "<td align='right'><b>". dkdecimal($xMomsSum,2) ."</b></td>";
			fwrite($csv, "\"".dkdecimal($xMomsSum,2)."\";");
			print "<td align='right'><b>". dkdecimal($momsSum,2) ."</b></td>";
			fwrite($csv, "\"".dkdecimal($momsSum,2)."\";");
			print "<td align='right'><b>". dkdecimal($xMomsSum+$momsSum,2) ."</b></td>";
			fwrite($csv, "\"".dkdecimal($xMomsSum+$momsSum,2)."\"\n\n");
			print "</tr>";
		}
	}
	print "<tr><td colspan=6><hr></td></tr>";
	print "</tbody></table>";
	fclose($csv);

	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}
}
 
#################################################################################################
?>
