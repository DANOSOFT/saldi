<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ finans/rapport_includes/kontokort.php -------- lap 3.8.2 --- 2019-09-24 ---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2019 saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20190924 PHR Added option 'Poster uden afd". when "afdelinger" is used. $afd='0' 


function kontokort($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering,$lagerbev) {

	global $afd_navn,$ansatte,$ansatte_id;
	global $bgcolor,$bgcolor4,$bgcolor5;
	global $connection,$csv;
	global $md,$menu;
	global $prj_navn_fra,$prj_navn_til;
	global $top_bund;
	
	$query = db_select("select firmanavn from adresser where art='S'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) $firmanavn=$row['firmanavn'];

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

	##
	$regnaarstart= $startaar. "-" . $startmaaned . "-" . '01';
	
	($startaar >= '2015')?$aut_lager='on':$aut_lager=NULL;

	if ($aut_lager && $lagerbev) {
		$x=0;
		$varekob=array();
		$q=db_select("select box1,box2,box3 from grupper where art = 'VG' and box8 = 'on'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['box1'] && $r['box2'] && !in_array($r['box3'],$varekob)) {
				$varelager_i[$x]=$r['box1'];
				$varelager_u[$x]=$r['box2'];
				$varekob[$x]=$r['box3'];
				$x++;
			}
		}
		$q=db_select("select box1,box2,box11 from grupper where art = 'VG' and box8 = 'on' and box11 != ''",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['box1'] && $r['box2'] && !in_array($r['box11'],$varekob)) {
				$varelager_i[$x]=$r['box1'];
				$varelager_u[$x]=$r['box2'];
				$varekob[$x]=$r['box11'];
				$x++;
			}
		}
		$q=db_select("select box1,box2,box13 from grupper where art = 'VG' and box8 = 'on' and box13 != ''",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['box1'] && $r['box2'] && !in_array($r['box13'],$varekob)) {
				$varelager_i[$x]=$r['box1'];
				$varelager_u[$x]=$r['box2'];
				$varekob[$x]=$r['box13'];
				$x++;
			}
		}
	}

	
	
	if ($aar_fra) $startaar=$aar_fra;
	if ($aar_til) $slutaar=$aar_til;
	if ($maaned_fra) $startmaaned=$maaned_fra;
	if ($maaned_til) $slutmaaned=$maaned_til;
	if ($dato_fra) $startdato=$dato_fra;
	if ($dato_til) $slutdato=$dato_til;

	$startdato*=1;
	if ($startdato < 10) $startdato='0'.$startdato; 
	
	while (!checkdate($startmaaned,$startdato,$startaar)) {
		$startdato=$startdato-1;
		if ($startdato<28) break 1;
	}
	
	while (!checkdate($slutmaaned,$slutdato,$slutaar)) {
		$slutdato=$slutdato-1;
		if ($slutdato<28) break 1;
	}

	$regnstart = $startaar. "-" . $startmaaned . "-" . $startdato;
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

#	print "  <a accesskey=L href=\"rapport.php?rapportart=Kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&afd=$afd\">Luk</a><br><br>";
	if ($csv) $csvfil=fopen("../temp/$db/$rapportart.csv","w");
	if ($menu=='T') {
		$leftbutton="<a class='button red small' title=\"Klik her for at komme til forsiden af rapporter\" href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\" accesskey=\"L\">Luk</a>";
		$rightbutton="";
		include("../includes/top_header.php");
		include("../includes/top_menu.php");
		print "<div id=\"header\"> 
		<div class=\"headerbtnLft\">$leftbutton</div>
		<span class=\"headerTxt\">Rapport - Kontokort</span>";     
		print "<div class=\"headerbtnRght\"></div>";       
		print "</div><!-- end of header -->
			<div class=\"maincontentLargeHolder\">\n";
			print  "<table class='dataTable2' border='0' cellspacing='1' width='100%'>";
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<table width=100% cellpadding=\"0\" cellspacing=\"1px\" border=\"0\" valign = \"top\" align='center'> ";
		print "<tr><td colspan=\"6\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\">Luk</a></td>";
		print "<td width=\"80%\" $top_bund> Rapport - kontokort </td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>";
		($simulering)?$tmp="Simuleret kontokort":$tmp="Kontokort";
		print "<tr><td colspan=\"4\"><big><big><big>  $tmp</span></big></big></big></td>";
	if ($csv) fwrite($csvfil,"$tmp;");
	}
	print "<td colspan=6 align=right><table style=\"text-align: left; width: 100%;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
	print "<td colspan=0 width=20%>Regnskabs&aring;r</span></td>";
	print "<td colspan=6>$regnaar.</span></td></tr>";
	if ($csv) fwrite($csvfil,"Regnskabsår $regnaar\n");
	print "<tr><td colspan=0 width=20%>Periode</span></td>";
	## Finder start og slut paa regnskabsaar
	if ($startdato < 10) $startdato="0".$startdato*1;	
	print "<td colspan=6>Fra ".$startdato.". $mf<br />Til ".$slutdato.". $mt</span></td></tr>";
	if ($csv) fwrite($csvfil,"Fra ".$startdato.". $mf\nTil ".$slutdato.". $mt\n");
	if ($ansat_fra) {
		if (!$ansat_til || $ansat_fra==$ansat_til) print "<tr><td>Medarbejder</span></td><td>$ansatte</span></td></tr>";
		else print "<tr><td>Medarbejdere</span></td><td>$ansatte</span></td></tr>";
	}
	if ($afd || $afd=='0') print "<tr><td>Afdeling</span></td><td>$afd_navn</span></td></tr>";
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
	
	$x=0;
	$kontonr = array();
	$qtxt="select * from kontoplan where regnskabsaar='$regnaar' and kontonr>='$konto_fra' and kontonr<='$konto_til' order by kontonr";
	$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)){
		$kontonr[$x]=$row['kontonr']*1;
		$kontobeskrivelse[$x]=$row['beskrivelse'];
		$kontotype[$x]=$row['kontotype'];
		$kontomoms[$x]=$row['moms'];
		$kontovaluta[$x]=$row['valuta'];
		$kontokurs[$x]=$row['valutakurs'];
		if (!$dim && $kontotype[$x]=="S") $primo[$x]=afrund($row['primo'],2);
		else $primo[$x]=0;
		if ($primo[$x] && $kontovaluta[$x]) {
			for ($y=0;$y<=count($valkode);$y++){
				if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $regnstart) {
					$primokurs[$x]=$valkurs[$y];
					break 1;
				}
			}
		} else $primokurs[$x]=100;
		$x++;
	}
	$ktonr=array();
	$x=0;
	$qtxt = "select distinct(kontonr) as kontonr from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr>='$konto_fra' and kontonr<='$konto_til' $dim";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ktonr[$x]=$r['kontonr'];
		$x++;
	}
	if ($simulering) {
		$qtxt = "select distinct(kontonr) as kontonr from simulering where transdate>='$regnstart' and transdate<='$regnslut' and kontonr>='$konto_fra' and kontonr<='$konto_til' $dim";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['kontonr'],$ktonr)) {
				$ktonr[$x]=$r['kontonr'];
				$x++;
			}
		}
	}
	if ($aut_lager && $lagerbev) { 
		for ($i=0;$i<count($varekob);$i++) { 
			if (!in_array($varekob[$i],$ktonr)) {
				$ktonr[$x]=$varekob[$i];
				$x++;
			}
		}
		for ($i=0;$i<count($varelager_i);$i++) { 
			if (!in_array($varelager_i[$i],$ktonr)) {
				$ktonr[$x]=$varelager_i[$i];
				$x++;
			}
		}
		for ($i=0;$i<count($varelager_u);$i++) { 
			if (!in_array($varelager_u[$i],$ktonr)) {
				$ktonr[$x]=$varelager_u[$i];
				$x++;
			}
		}
	}

	sort($kontonr);
	$kontosum=0;
	$founddate=false;
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr><td width=\"100px\">Dato</td><td width=\"60px\">Bilag</td><td>Tekst</td><td width=\"100px\" align=\"right\">Debet</td><td width=\"100px\" align=\"right\">Kredit</td><td width=\"100px\" align=\"right\">Saldo</td></tr>";
	
	for ($x=0;$x<count($kontonr);$x++){
		$linjebg=$bgcolor5;
		if (in_array($kontonr[$x],$ktonr)||$primo[$x]){
			print "<tr><td colspan=6><hr></td></tr>";
			print "<tr bgcolor=\"$bgcolor5\"><td></td><td></td><td colspan=4>$kontonr[$x] : $kontobeskrivelse[$x] : $kontomoms[$x]</tr>";
			print "<tr><td colspan=6><hr></td></tr>";
			$kontosum=$primo[$x];
			$query = db_select("select debet, kredit from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)){
			 	$kontosum= $kontosum+afrund($row['debet'],2)-afrund($row['kredit'],2);
			}
			$query = db_select("select debet, kredit from simulering where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)){
			 	$kontosum=$kontosum+afrund($row['debet'],2)-afrund($row['kredit'],2);
			}
			if ($primokurs[$x]) $tmp=$kontosum*100/$primokurs[$x];
			else $tmp=$kontosum;
			#if (!$dim) #20180226 
			print "<tr bgcolor=\"$linjebg\"><td></td><td></td><td>  Primosaldo </td><td></td><td></td><td align=right>".dkdecimal($tmp,2)."</td></tr>";
			$print=1;
			$tr=0;
			$transdate=array();
			$qtxt="select * from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnstart' and transdate<='$regnslut' $dim ";
			$qtxt.="order by transdate,bilag,id";
			$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)){
				$transdate[$tr]=$row['transdate'];
				$bilag[$tr]=$row['bilag'];
				$kladde_id[$tr]=$row['kladde_id'];
				$beskrivelse[$tr]=$row['beskrivelse'];
				$debet[$tr]=$row['debet'];
				$kredit[$tr]=$row['kredit'];
				$transvaluta[$tr]=$row['valuta'];
				if ($kontovaluta[$x]) {
					for ($y=0;$y<=count($valkode);$y++){
#cho "$valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $transdate[$tr]<br>";
						if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $transdate[$tr]) {
							$transkurs[$tr]=$valkurs[$y];
							break 1;
						}
					}
				} else $transkurs[$tr]=100; 
#cho "TK1 $transkurs[$tr]<br>";
				$tr++;
			}

			if ($lagerbev && $aut_lager && (in_array($kontonr[$x],$varekob) || in_array($kontonr[$x],$varelager_i) || in_array($kontonr[$x],$varelager_u))) {
				$z=0;
				$lager=array();
				$gruppe=array();
				$q=db_select("select kodenr,box1,box2 from grupper where art = 'VG' and box8 = 'on' and (box1 = '$kontonr[$x]' or box2 = '$kontonr[$x]' or box3 = '$kontonr[$x]' or box11 = '$kontonr[$x]' or box13 = '$kontonr[$x]')",__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					if ($r['box1']) {
#						$lager_i[$z]=$r['box1'];
#						$lager_u[$z]=$r['box2'];
						$gruppe[$z]=$r['kodenr'];
						$z++;
					}
				} 
				$y=0;
				$vare_id=array();
				for ($z=0;$z<count($gruppe);$z++) {
					$q=db_select("select id,kostpris from varer where gruppe = '$gruppe[$z]' order by id",__FILE__ . " linje " . __LINE__);
					while ($r=db_fetch_array($q)) {
						$vare_id[$y]=$r['id'];
						$kostpris[$y]=$r['kostpris'];
						$y++;
					}
				}
				$z=-1;
				$kobsdate=array();
				$kobsdebet=array();
				$kobskredit=array();
				$q=db_select("select vare_id,ordre_id,antal,kobsdate from batch_kob where kobsdate >= '$regnstart' and kobsdate <= '$regnslut' order by kobsdate,vare_id",__FILE__ . " linje " . __LINE__); #20170516
				while ($r=db_fetch_array($q)) {
					if ($z>=0 && isset($kobsdate[$z]) && $r['kobsdate']==$kobsdate[$z] && $r['ordre_id'] && $r['ordre_id'] == $soid[$z]) {
						for ($y=0;$y<count($vare_id);$y++) {
							if($r['vare_id']==$vare_id[$y]) {
								if($kontotype[$x]=='D')	{ 
									if ($r['antal']>0) $kobskredit[$z]+=$r['antal']*$kostpris[$y];
									else $kobsdebet[$z]-=$r['antal']*$kostpris[$y];
									} elseif(in_array($kontonr[$x],$varelager_i)) {
									if ($r['antal']>0) $kobsdebet[$z]+=$r['antal']*$kostpris[$y];
									else $kobskredit[$z]-=$r['antal']*$kostpris[$y];
								}
							}
						}
					} else {
						for ($y=0;$y<count($vare_id);$y++) {
							if($r['vare_id']==$vare_id[$y]) {
								if($kontotype[$x]=='D')	{
									$z++;
									$koid[$z]=$r['ordre_id'];
									if (isset($koid[$z-1]) && $koid[$z]==$koid[$z-1]) $kobsfakt[$z]=$kobsfakt[$z-1];
									else {
										$r2=db_fetch_array(db_select("select fakturanr from ordrer where id='$koid[$z]'",__FILE__ . " linje " . __LINE__));
										$kobsfakt[$z]=$r2['fakturanr'];
									}
									$kobsdate[$z]=$r['kobsdate'];
									if ($r['antal']>0) {
										$kobskredit[$z]=$r['antal']*$kostpris[$y];
										$kobsdebet[$z]=0;
									} else {
										$kobsdebet[$z]=$r['antal']*$kostpris[$y]*-1;
										$kobskredit[$z]=0;
									}
#									$z++;
								} elseif(in_array($kontonr[$x],$varelager_i)) {
									$z++;		
									$koid[$z]=$r['ordre_id'];
									if (isset($koid[$z-1]) && $koid[$z]==$koid[$z-1]) $kobsfakt[$z]=$kobsfakt[$z-1];
									else {
										$r2=db_fetch_array(db_select("select fakturanr from ordrer where id='$koid[$z]'",__FILE__ . " linje " . __LINE__));
										$kobsfakt[$z]=$r2['fakturanr'];
									}
									$kobsdate[$z]=$r['kobsdate'];
									if ($r['antal']>0) {
										$kobsdebet[$z]=$r['antal']*$kostpris[$y];
										$kobskredit[$z]=0;
									} else {
										$kobskredit[$z]=$r['antal']*$kostpris[$y]*-1;
										$kobsdebet[$z]=0;
									}
#									$z++;
								}
							}
						}
					}
				}
				$z=-1;
				$salgsdate=array();
				$salgsdebet=array();
				$salgkredit=array();
				$q=db_select("select ordre_id,vare_id,antal,salgsdate from batch_salg where salgsdate >= '$regnstart' and salgsdate <= '$regnslut' order by salgsdate,vare_id",__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					if ($z>=0 && isset($salgsdate[$z]) && $r['salgsdate']==$salgsdate[$z] && $r['ordre_id'] && $r['ordre_id'] == $soid[$z]) {
						for ($y=0;$y<count($vare_id);$y++) {
							if($r['vare_id']==$vare_id[$y]) {
								if($kontotype[$x]=='D')	{ 
									if ($r['antal']>0) $salgsdebet[$z]+=$r['antal']*$kostpris[$y];
									else $salgskredit[$z]-=$r['antal']*$kostpris[$y];
								} elseif(in_array($kontonr[$x],$varelager_u)) {
									if ($r['antal']>0) $salgskredit[$z]+=$r['antal']*$kostpris[$y];
									else $salgsdebet[$z]-=$r['antal']*$kostpris[$y];
								}
							}
						}
					} else {

						for ($y=0;$y<count($vare_id);$y++) {
							if($r['vare_id']==$vare_id[$y]) {
								if($kontotype[$x]=='D')	{ 
									$z++;
									$soid[$z]=$r['ordre_id'];
									if ($soid[$z]==$soid[$z-1]) $salgsfakt[$z]=$salgsfakt[$z-1];
									else {
										$r2=db_fetch_array(db_select("select fakturanr from ordrer where id='$soid[$z]'",__FILE__ . " linje " . __LINE__));
										$salgsfakt[$z]=$r2['fakturanr'];
									}
									$salgsdate[$z]=$r['salgsdate'];
									if ($r['antal']>0) {
										$salgsdebet[$z]=$r['antal']*$kostpris[$y];
										$salgskredit[$z]=0;
									} else {
										$salgskredit[$z]=$r['antal']*$kostpris[$y]*-1;
										$salgsdebet[$z]=0;
									}
#									$z++;
								} elseif(in_array($kontonr[$x],$varelager_u)) { 
									$z++;
									$soid[$z]=$r['ordre_id'];
									if (isset($soid[$z-1]) && $soid[$z]==$soid[$z-1]) $salgsfakt[$z]=$salgsfakt[$z-1];
									else {
										$r2=db_fetch_array(db_select("select fakturanr from ordrer where id='$soid[$z]'",__FILE__ . " linje " . __LINE__));
										$salgsfakt[$z]=$r2['fakturanr'];
									}
									$salgsdate[$z]=$r['salgsdate'];
									if ($r['antal']>0) {
										$salgskredit[$z]=$r['antal']*$kostpris[$y];
										$salgsdebet[$z]=0;
									} else {
										$salgsdebet[$z]=$r['antal']*$kostpris[$y]*-1;
										$salgskredit[$z]=0;
									}
#									$z++;
								}
							}
						}
					}
				}
				$dato=$regnstart;
				$y=0;
				$tr=0;
				$kd=0;
				$sd=0;
				$trd=array();
				while ($dato<=$regnslut) {
				while (isset($transdate[$tr]) && $transdate[$tr]==$dato) {
						$trd[$y]=$dato;
						$bil[$y]=$bilag[$tr];
						$besk[$y]=$beskrivelse[$tr];
						$deb[$y]=$debet[$tr];
						$kre[$y]=$kredit[$tr];
						$tr++;
						$y++;
					}
					while (isset($kobsdate[$kd]) && $kobsdate[$kd]==$dato) {
						$trd[$y]=$dato;
						$bil[$y]=0;
						$besk[$y]="lagertransaktion - Køb  F: $kobsfakt[$kd]";
						$deb[$y]=$kobsdebet[$kd];
						$kre[$y]=$kobskredit[$kd];
						$kd++;
						$y++;
					}
					while (isset($salgsdate[$sd]) && $salgsdate[$sd]==$dato) {
						$trd[$y]=$dato;
						$bil[$y]=0;
						$besk[$y]="lagertransaktion - Salg  F: $salgsfakt[$sd]";
						$deb[$y]=$salgsdebet[$sd];
						$kre[$y]=$salgskredit[$sd];
						$sd++;
						$y++;
					}
					list($yy,$mm,$dd)=explode("-",$dato);
					$dd++;
					if (!checkdate($mm,$dd,$yy)) {
						$dd=1;
						$mm++;
						if ($mm>12) {
							$mm=1;
							$yy++;
						}
					}
					$dd*=1;
					$mm*=1;
					if (strlen($dd)<2) $dd='0'.$dd;
					if (strlen($mm)<2) $mm='0'.$mm;
					$dato=$yy."-".$mm."-".$dd;
				}
				for ($y=0;$y<count($trd);$y++){
					$transdate[$y]=$trd[$y];
					$bilag[$y]=$bil[$y];
					$beskrivelse[$y]=$besk[$y];
					$debet[$y]=$deb[$y];
					$kredit[$y]=$kre[$y];
				}
			}
			$sim_transdate=array();
			if ($simulering) {
				$sim=0;
				$sim_kontonr=array();
				$q = db_select("select * from simulering where kontonr='$kontonr[$x]' and transdate>='$regnstart' and transdate<='$regnslut' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)){
					$sim_id[$sim]=$r['id'];
					$sim_transdate[$sim]=$r['transdate'];
					$sim_bilag[$sim]=$r['bilag'];
					$sim_kontonr[$sim]=$r['kontonr'];
					$sim_beskrivelse[$sim]=$r['beskrivelse'];
					$sim_debet[$sim]=$r['debet'];
					$sim_kredit[$sim]=$r['kredit'];
					$a=0;
					while($a<=count($transdate) and $sim_transdate[$sim]>$transdate[$a]) $a++;
					for ($b=count($transdate);$b>$a;$b--) {
						$transdate[$b]=$transdate[$b-1];
						$bilag[$b]=$bilag[$b-1];
						$beskrivelse[$b]=$beskrivelse[$b-1];
						$debet[$b]=$debet[$b-1];
						$kredit[$b]=$kredit[$b-1];
					}
					$transdate[$b]=$sim_transdate[$sim];
					$bilag[$b]=$sim_bilag[$sim];
					$beskrivelse[$b]=$sim_beskrivelse[$sim]."(Simuleret)";
					$debet[$b]=$sim_debet[$sim];
					$kredit[$b]=$sim_kredit[$sim];
					$sim_transdate[$sim]=NULL;
					$sim++;
				}
			}
/* 20150825
			for ($tr=0;$tr<count($transdate)+count($sim_transdate);$tr++) {		
				if ($transdate[$tr]) {
					if ($bilag[$tr]==$bilag[$tr+1] && $transdate[$tr]==$transdate[$tr+1] && $beskrivelse[$tr]==$beskrivelse[$tr+1]) {
						$debet[$tr+1]+=$debet[$tr];
						$kredit[$tr+1]+=$kredit[$tr];
						$debet[$tr]=0;
						$kredit[$tr]=0;
					}
				}
			}
*/			
			for ($tr=0;$tr<count($transdate)+count($sim_transdate);$tr++) {
				if ($transdate[$tr] && ($debet[$tr] || $kredit[$tr])) {
					($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
					print "<tr bgcolor=\"$linjebg\"><td>  ".dkdato($transdate[$tr])." </td>";
					($kladde_id[$tr])?$js="onclick=\"window.open('kassekladde.php?kladde_id=$kladde_id[$tr]&visipop=on')\"":$js=NULL;
					print "<td title='Kladde: $kladde_id[$tr]' $js>$bilag[$tr]</td><td>$kontonr[$x] : $beskrivelse[$tr] </td>";
					if ($kontovaluta[$x]) {
						if ($transvaluta[$tr]=='-1') $tmp=0;
						else $tmp=$debet[$tr]*100/$transkurs[$tr];
						$title="DKK ".dkdecimal($debet[$tr]*1,2)." Kurs: ".dkdecimal($transkurs[$tr],2);
					} else {
						$tmp=$debet[$tr];
						$title=NULL;
					}
					print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
					if ($kontovaluta[$x]) {
						if ($transvaluta[$tr]=='-1') $tmp=0;
						else $tmp=$kredit[$tr]*100/$transkurs[$tr];
						$title="DKK ".dkdecimal($kredit[$tr]*1,2)." Kurs: ".dkdecimal($transkurs[$tr],2);
					} else {
						$tmp=$kredit[$tr];
						$title=NULL;
					}
					print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
					$kontosum=$kontosum+afrund($debet[$tr],2)-afrund($kredit[$tr],2);
					if ($kontovaluta[$x]) {
						$tmp=$kontosum*100/$transkurs[$tr];
						$title="DKK ".dkdecimal($kontosum,2)." Kurs: ".dkdecimal($transkurs[$tr],2);
					} else {
						$tmp=$kontosum;
						$title=NULL;
					}
					print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td></tr>";
				} 
/*
				if (in_array($kontonr[$x],$sim_kontonr) && ($transdate[$tr]!=$transdate[$tr+1])) {
				for ($sim=0;$sim<count($sim_kontonr);$sim++) {
						if ($kontonr[$x]==$sim_kontonr[$sim] && ($transdate[$tr] == $sim_transdate[$sim])) {
							($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
							print "<tr bgcolor=\"$linjebg\"><td>  ".dkdato($sim_transdate[$sim])." </td><td>$sim_bilag[$sim] </td><td>$sim_kontonr[$sim] : $sim_beskrivelse[$sim] (simuleret) </td>";
							$tmp=dkdecimal($sim_debet[$sim]);
							print "<td align=right>$tmp </td>";
							$tmp=dkdecimal($sim_kredit[$sim]);
							print "<td align=right>$tmp </td>";
							$kontosum=$kontosum+afrund($sim_debet[$sim],2)-afrund($sim_kredit[$sim],2);
							$tmp=dkdecimal($kontosum);
							print "<td align=right>$tmp </td></tr>";
						}
					}
				}
*/				
			}
		}
	}
	print "<tr><td colspan=6><hr></td></tr>";
	print "</tbody></table>";
} # endfunc kontokort
#################################################################################################
?>
