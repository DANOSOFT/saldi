<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ finans/rapport_includes/regnskab.php -------- lap 3.8.2 --- 2019-09-24 ---
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
// 20190430 PHR Definition of undefined variables
// 20190506 PHR Added lastYearBegin line as it was missing.
// 20190923 PHR Removed '&& $rapportart!='lastYear'. 30109023  
// 20190924 PHR Added option 'Poster uden afd". when "afdelinger" is used. $afd='0' 

function regnskab($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering,$lagerbev) {
	print "<!--Function regnskab start-->\n";
	global $connection;
	global $top_bund;
	global $md;
	global $ansatte;
	global $ansatte_id;
	global $afd_navn;
	global $prj_navn_fra;
	global $prj_navn_til;
	global $bgcolor;
	global $bgcolor4;
	global $bgcolor5;
	global $menu;

	$budget=NULL;
	$kto_periode=$periodesum=$varekob=$varelager_i=$varelager_u=array();
	$lastYearPeriodSum=$lastYearYearSum=array();
	
	$dim='';
	if (($afd||$afd=='0'||$ansat_fra||$projekt_fra) && $rapportart!='budget') { #20190923
		if ($afd||$afd=='0') $dim = "and afd = '$afd' ";
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

#cho "942 $projekt_fra $prj_navn_fra - $projekt_til $prj_navn_til<br>"; 

	if ($rapportart=='budget') {
		$budget=1;
		$cols1=2;$cols2=3;$cols3=4;$cols4=5;$cols5=6;$cols6=7;
	} elseif ($rapportart=='lastYear') {
		$lastYear=1;
		$cols1=2;$cols2=3;$cols3=4;$cols4=5;$cols5=6;$cols6=7;
	} else {
		$budget=$lastYear=0;
		$cols1=1;$cols2=2;$cols3=3;$cols4=4;$cols5=5;$cols6=6;
	}
	
	$qtxt="select firmanavn from adresser where art='S'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $firmanavn=$r['firmanavn'];
	$qtxt="select beskrivelse from grupper where art='AFD' and kodenr='$afd'";
	if (($afd||$afd=='0')&&($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))) $afd_navn=$r['beskrivelse'];

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);
	$konto_fra=trim($konto_fra);
	$konto_til=trim($konto_til);

	$mf=$maaned_fra;
	$mt=$maaned_til;

	for ($x=1; $x<=12; $x++){
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
		if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
		if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
		if (strlen($dato_fra)==1){$dato_fra="0".$dato_fra;}
		if (strlen($dato_til)==1){$dato_til="0".$dato_til;}
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
#	$regnaar=$row[kodenr];
	$startmaaned=$row['box1']*1;
	$startaar=$row['box2']*1;
	$slutmaaned=$row['box3']*1;
	$slutaar=$row['box4']*1;
	$slutdato=31;

	if ($rapportart=='budget') {
		$startmd=$maaned_fra-$startmaaned+1;
		$slutmd=$maaned_til-$startmaaned+1;
		if ($slutaar>$startaar && $maaned_fra>$maaned_til) $slutmd=$slutmd+12;
	}

	if (strlen($startmaaned)==1) $startmaaned="0".$startmaaned;
	if (strlen($slutmaaned)==1) $slutmaaned="0".$slutmaaned;

	$regnAarStart= $startaar . "-" . $startmaaned . "-" . '01';
	$lastYearBegin= $startaar-1 . "-" . $startmaaned . "-" . '01';

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
#cho "1008 $projekt_fra $prj_navn_fra - $projekt_til $prj_navn_til<br>"; 

	$regnstart = $aar_fra. "-" . $startmaaned . "-" . $startdato;
	$regnslut = $aar_til . "-" . $slutmaaned . "-" . $slutdato;
	$lastYearBegin = $aar_fra - 1 . "-" . $startmaaned . "-" . $startdato; #20190506
	$lastYearEnd = $aar_til-1 . "-" . $slutmaaned . "-" . $slutdato;

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
	$x=0;
	$valdate=array();
	$valkode=array();
	$q=db_select("select * from valuta order by gruppe,valdate desc",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$y=$x-1;
		if (!isset($valkode[$x])) $valkode[$x]=NULL;
		if ((!$x) || $r['gruppe']!=$valkode[$x] || $valdate[$x]>=$regnstart) {
			$valkode[$x]=$r['gruppe'];
			$valkurs[$x]=$r['kurs'];
			$valdate[$x]=$r['valdate'];
			$x++;
		}
	}
	if ($menu=='T') {
		$leftbutton="<a class='button red small' title=\"Klik her for at komme til forsiden af rapporter\" ";
		$leftbutton.="href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra";
		$leftbutton.="&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra";
		$leftbutton.="&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\" ";
		$leftbutton.="accesskey=\"L\">Luk</a>";
		$rightbutton="";
		include("../includes/top_header.php");
		include("../includes/top_menu.php");
		print "<div id=\"header\"> 
		<div class=\"headerbtnLft\">$leftbutton</div>
		<span class=\"headerTxt\">Rapport - $rapportart</span>";     
		print "<div class=\"headerbtnRght\"></div>";       
		print "</div><!-- end of header -->
			<div class=\"maincontentLargeHolder\">\n";
			print  "<table class='dataTable2' border='0' cellspacing='1' width='100%'>";
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<table width=100% cellpadding=\"0\" cellspacing=\"1px\" border=\"0\" valign = \"top\" align='center'> ";
		print "<tr><td colspan=\"$cols6\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering&lagerbev=$lagerbev\">Luk</a></td>";
		print "<td width=\"80%\" $top_bund> Rapport - $rapportart </td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>";
	}		
	if ($rapportart=='resultat') {
		($simulering)?$tmp="Simuleret resultat":$tmp="Resultat";
	} elseif ($rapportart=='budget') {
		($simulering)?$tmp="Simuleret resultat/budget":$tmp="Resultat/budget";
	} elseif ($rapportart=='lastYear') {
		($simulering)?$tmp="Simuleret resultat/sidste år":$tmp="Resultat/sidste år";
	} elseif ($rapportart=='balance') {
		($simulering)?$tmp="Simuleret Balance":$tmp="Balance";
	} else {
		($simulering)?$tmp="Simuleret Regnskab":$tmp="Regnskab";
	}
	print "<tr><td colspan=\"$cols4\"><big><big>$tmp</span></big></big></td>";

	print "<td colspan=\"$cols2\" align=right><table style=\"text-align: left; width: 100%;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
	if ($afd||$afd=='0') {
		print "<td>Afdeling</span></td>";
		print "<td>$afd: $afd_navn</span></td></tr>";
	}
	print "<td>Regnskabs&aring;r</span></td>";
	print "<td>$regnaar.</span></td></tr>";
	print "<tr><td>Periode</span></td>";
	if ($startdato < 10) $startdato="0".$startdato*1;
	print "<td>Fra ".$startdato.". $mf $aar_fra<br />Til ".$slutdato.". $mt $aar_til</span></td></tr>";
	if ($ansat_fra) {
		if (!$ansat_til || $ansat_fra==$ansat_til) print "<tr><td>Medarbejder</span></td><td>$ansatte</span></td></tr>";
		else print "<tr><td>Medarbejdere</span></td><td>$ansatte</span></td></tr>";
	}
	if ($afd||$afd=='0') print "<tr><td>Afdeling</span></td><td>$afd_navn</span></td></tr>";
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
	print "<tr><td colspan=\"4\"><big><b>$firmanavn</b></big></td>";
	print "<td align=right> Perioden </td>";
	if ($rapportart=='budget') {
		print "<td align=right> Budget </td><td align=right> Afvigelse </td></tr>";
	} elseif ($rapportart=='lastYear') {
		print "<td align=right> Sidste år </td></tr>";
	}else {
		print "<td align=right> &Aring;r til dato </td></tr>";
	}
	print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
	$x=0;
	$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$kontonr[$x]=$row['kontonr']*1;
		$ktonr[$x]=$kontonr[$x];
		$kontobeskrivelse[$x]=$row['beskrivelse'];
		$kontotype[$x]=$row['kontotype'];
		$fra_kto[$x]=$row['fra_kto']*1;
		$primo[$x]=afrund($row['primo'],2);
		$saldo[$x]=$row['saldo']*1;
		$lukket[$x]=$row['lukket']; #20120927
		$aarsum[$x]=0;
		$kto_aar[$x]=0;
		$kto_periode[$x]=0;
		$vis_kto[$x]=0;
		$kontovaluta[$x]=$row['valuta'];
		$kontokurs[$x]=$row['valutakurs'];
		if (!$dim && $kontotype[$x]=="S") $primo[$x]=afrund($row['primo'],2);
		else $primo[$x]=0;
		if ($primo[$x] && $kontovaluta[$x]) {
			for ($y=0;$y<=count($valkode);$y++){
				if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $slutdato) {
					$kontokurs[$x]=$valkurs[$y];
					break 1;
				}
			}
		} else $primokurs[$x]=100;
		
	}
		$kto_antal=$kontoantal=$x;

	$x=0;

	for ($x=1; $x<=$kontoantal; $x++) {
		$qtxt="select * from transaktioner where transdate>='$regnAarStart' and transdate<='$regnslut' $dim and kontonr=$ktonr[$x]";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$vis_kto[$x]=1;
		}
		$qtxt="select * from transaktioner where transdate>='$regnAarStart' and transdate<='$regnslut' and kontonr='$ktonr[$x]' $dim";
		if(db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$vis_kto[$x]=1;
		}
		if ($simulering) {
			$qtxt="select * from simulering where transdate>='$regnAarStart' and transdate<='$regnslut' $dim and kontonr=$ktonr[$x]";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$vis_kto[$x]=1;
			}
			$qtxt="select * from simulering where transdate>='$regnAarStart' and transdate<='$regnslut' and kontonr='$ktonr[$x]' $dim";
			if(db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$vis_kto[$x]=1;
			}
		}
		if ($aut_lager && $lagerbev) {
			if (in_array($kontonr[$x],$varekob)) $vis_kto[$x]=1; 
			if (in_array($kontonr[$x],$varelager_i)) $vis_kto[$x]=1; 
			if (in_array($kontonr[$x],$varelager_u)) $vis_kto[$x]=1; 
		}
		if ($kontotype[$x]=='R') $vis_kto[$x]=1;
	}
	if ($rapportart=='budget') {
		for ($x=1; $x<=$kontoantal; $x++) {
			if (!$lukket[$x]) { #20120927	
				$qtxt = "select sum(amount) as amount from budget where regnaar='$regnaar' and kontonr='$ktonr[$x]' ";
				$qtxt.= "and md >= '$startmd' and md <= '$slutmd'";
				if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$vis_kto[$x]=1;
				}
			}
		}
	}
	for ($x=1; $x<=$kontoantal; $x++) {
		$kto_aar[$x]=0;
		$kto_periode[$x]=0;  # Herunder tilfoejes primovaerdi.
		$qtxt="select primo from kontoplan where regnskabsaar='$regnaar' and kontonr=$ktonr[$x] and kontotype='S'";
		if ((($rapportart=='balance' || $rapportart=='regnskab')&&!$afd && $afd!='0' && !$projekt_fra && !$ansat_fra) && ($r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))) {
			$kto_aar[$x]=afrund($r2['primo'],2);
		}
		$qtxt="select * from transaktioner where transdate>='$regnAarStart' and transdate<='$regnslut' and kontonr='$ktonr[$x]' $dim";
		$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if ($row['transdate']>=$regnstart) {
				$kto_periode[$x]=$kto_periode[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
			}
			if ($rapportart!='budget') {
				$kto_aar[$x]=$kto_aar[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
			}
		}
		if ($simulering) {
			$query = db_select("select * from simulering where transdate>='$regnAarStart' and transdate<='$regnslut' and kontonr='$ktonr[$x]' $dim",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				if ($row['transdate']>=$regnstart) $kto_periode[$x]=$kto_periode[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
				if ($rapportart!='budget') {
					$kto_aar[$x]=$kto_aar[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
				}
			}
		}
		if ($aut_lager && $lagerbev) {
			if (in_array($ktonr[$x],$varekob)) {
				$l_a_primo[$x]=find_lagervaerdi($ktonr[$x],$regnAarStart,'start');
				$l_a_sum[$x]=find_lagervaerdi($ktonr[$x],$regnslut,'slut');
				$l_p_primo[$x]=find_lagervaerdi($ktonr[$x],$regnstart,'start');
			# Varekøb (debet) debiteres lager primo og krediteres lager saldo. Dvs tallet mindskes hvis lager øges 
				$kto_aar[$x]+=$l_a_primo[$x];				
				$kto_aar[$x]-=$l_a_sum[$x];		
				$kto_periode[$x]+=$l_p_primo[$x];
				$kto_periode[$x]-=$l_a_sum[$x];
			}
			if (in_array($ktonr[$x],$varelager_i) || in_array($ktonr[$x],$varelager_u)) {
				$l_a_primo[$x]=find_lagervaerdi($ktonr[$x],$regnAarStart,'start');
				$l_a_sum[$x]=find_lagervaerdi($ktonr[$x],$regnslut,'slut');
				$l_p_primo[$x]=find_lagervaerdi($ktonr[$x],$regnstart,'start');
				$kto_aar[$x]-=$l_a_primo[$x]; #20150125 + næste 3 linjer
				$kto_aar[$x]+=$l_a_sum[$x];
				$kto_periode[$x]-=$l_p_primo[$x];
				$kto_periode[$x]+=$l_a_sum[$x];
			}
		}
	}
	if ($rapportart=='lastYear') {
		for ($x=1; $x<=$kontoantal; $x++) {
			$lastYearYear[$x]=0;
			$lastYearPeriod[$x]=0;  # Herunder tilfoejes primovaerdi.
			$query = db_select("select * from transaktioner where transdate>='$lastYearBegin' and transdate<='$lastYearEnd' and kontonr='$ktonr[$x]' $dim",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				if ($row['transdate']>=$lastYearBegin) {
					$lastYearYear[$x]+=afrund($row['debet'],2)-afrund($row['kredit'],2);
				}
			}
			if ($aut_lager && $lagerbev) {
				if (in_array($ktonr[$x],$varekob)) {
#				$lastYearPrimo[$x]=find_lagervaerdi($ktonr[$x],$regnAarStart,'start');
					$lastYearSum[$x]=find_lagervaerdi($ktonr[$x],$regnslut,'slut');
#					$lastYearPeriodPrimo[$x]=find_lagervaerdi($ktonr[$x],$regnstart,'start');
				# Varekøb (debet) debiteres lager primo og krediteres lager saldo. Dvs tallet mindskes hvis lager øges 
#					$lastYearYear[$x]+=$lastYearPrimo[$x];				
					$lastYearYear[$x]-=$lastYearSum[$x];		
#					$lastYearPeriod[$x]+=$lastYearPeriodPrimo[$x];
					$lastYearPeriod[$x]-=$lastYearSum[$x];
				}
			}
		}
	}	elseif ($rapportart=='budget') {
		for ($x=1; $x<=$kontoantal; $x++) {
			if ($vis_kto[$x] && $kontotype[$x]=='D') { #20120927 + 20181031
				$qtxt="select sum(amount) as amount from budget where ";
				$qtxt.="regnaar='$regnaar' and kontonr='$ktonr[$x]' and md >= '$startmd' and md <= '$slutmd'";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$kto_aar[$x]=afrund($r2['amount'],2);
			}
		}
		$kto_antal=$kontoantal;
	} 

	for ($x=1; $x<=$kontoantal; $x++) { # Her fanges konti med primovaerdi og ingen bevaegelser i perioden.
		if (!in_array($kontonr[$x], $ktonr)&& !$afd && $afd!='0' && !$projekt_fra && !$ansat_fra) {
			if ($primo[$x]) {
				$kto_antal++;
				$ktonr[$kto_antal]=$kontonr[$x];
				$kto_aar[$kto_antal]=$primo[$x];
#				if (in_array($ktonr[$kto_antal],$varekob)) {
#			$l_a_primo[$kto_antal]=find_lagervaerdi($ktonr[$kto_antal],$varekob,$regnstart);
#			$l_a_sum[$kto_antal]=find_lagervaerdi($ktonr[$kto_antal],$varekob,$regnslut);
#				$l_p_primo[$x]=find_lagervaerdi($kontonr[$x],$varekob,$regnAarStart);
#			$kto_aar[$kto_antal]-=$l_a_primo[$kto_antal];
#			$kto_aar[$kto_antal]+=$l_a_sum[$kto_antal];
#				$periodesum[$x]-=$l_p_primo[$x];
#				$periodesum[$x]+=$l_a_sum[$x]; 
#		}
			}
		}
	}
	for ($x=1; $x<=$kontoantal; $x++) { # Her fanges konti med lagerrelation & primovaerdi og ingen bevaegelser i perioden.
		if (in_array($kontonr[$x], $varelager_i) || in_array($kontonr[$x], $varelager_u)) {
			if (in_array($kontonr[$x], $ktonr)) {
				$kto_antal++;
				$ktonr[$kto_antal]=$kontonr[$x];
				$kto_aar[$kto_antal]=0;
			}
		}
	}

	for ($x=1; $x<=$kontoantal; $x++) {
		if ($kontotype[$x]=='R') {
			for ($y=1; $y<=$kontoantal; $y++) { #20140825
				if ($ktonr[$y]==$fra_kto[$x]) {
					$aarsum[$x]=$aarsum[$y];
					$periodesum[$x]=$periodesum[$y];
					$kto_aar[$x]=$aarsum[$x]; #20140909 rettet fra = $kto_aar[$y] 
					$kto_periode[$x]=$periodesum[$x]; #20140909 rettet fra = $kto_periode[$y]
				}
			}
		}
		if (!isset($periodesum[$x])) $periodesum[$x]=0;
		for ($y=1; $y<=$kto_antal; $y++) {
			if (!isset($kto_periode[$y])) $kto_periode[$y]=0;
			if (($kontotype[$x] == 'D')||($kontotype[$x] == 'S')) {
				if ($kontonr[$x]==$ktonr[$y]) {
					$aarsum[$x]=$aarsum[$x]+$kto_aar[$y];
					$periodesum[$x]=$periodesum[$x]+$kto_periode[$y];
				}
			} elseif ($kontotype[$x] == 'Z') {
				if (($fra_kto[$x]<=$ktonr[$y])&&($kontonr[$x]>=$ktonr[$y])&&($kontonr[$x]!=$ktonr[$y])) {
					$aarsum[$x]=$aarsum[$x]+$kto_aar[$y];
					$periodesum[$x]=$periodesum[$x]+$kto_periode[$y];
				}
			}
		}
	}
	if ($lastYear) {
		for ($x=1; $x<=$kontoantal; $x++) {
			if (!isset($lastYearPeriodSum[$x])) $lastYearPeriodSum[$x]=0;
			if (!isset($lastYearYearSum[$x])) $lastYearYearSum[$x]=0;
			for ($y=1; $y<=$kto_antal; $y++) {
				if (!isset($lastYearPeriod[$y])) $lastYearPeriod[$y]=0;
				if (($kontotype[$x] == 'D')||($kontotype[$x] == 'S')) {
					if ($kontonr[$x]==$ktonr[$y]) {
						$lastYearYearSum[$x]+=$lastYear[$y];
						$lastYearPeriodSum[$x]+=$lastYearPeriod[$y];
					}
				} elseif ($kontotype[$x] == 'Z') {
					if (($fra_kto[$x]<=$ktonr[$y])&&($kontonr[$x]>=$ktonr[$y])&&($kontonr[$x]!=$ktonr[$y])) {
						$lastYearYearSum[$x]+=$lastYearYear[$y];
						$lastYearPeriodSum[$x]+=$lastYearPeriod[$y];
					}
				}
#cho "$kontonr[$x] lYPS $lastYearYearSum[$x] $lastYearPeriodSum[$x]<br>";						
				
			}
		}
	}
	for ($x=1; $x<=$kontoantal; $x++) {
		if ($kontonr[$x]>=$konto_fra && $kontonr[$x]<=$konto_til && ($aarsum[$x] || $periodesum[$x] || $kontotype[$x] == 'H' || $kontotype[$x] == 'R' || ($kontotype[$x] == 'Z' && $x==$kontoantal))) { #20190220
			if ($kontotype[$x] == 'H') {
				$linjebg=$bgcolor;
				print "<tr><td><br></td></tr>";
				$tmp = kontobemaerkning($kontobeskrivelse[$x]);
				print "<tr bgcolor=\"$bgcolor5\"><td $tmp colspan=\"$cols6\"><b>$kontobeskrivelse[$x]</b></td>";
				print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
			} elseif ($kontotype[$x] == 'Z') {
				print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
				$tmp = kontobemaerkning($kontobeskrivelse[$x]);
				if (!$budget && !$lastYear) print "<td><br></td>";
				print "<td $tmp colspan=\"$cols3\"><b> $kontobeskrivelse[$x] </b></td>";
				if ($kontovaluta[$x]) {
					for ($y=0;$y<=count($valkode);$y++){
						if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $slutdate) {
							$transkurs[$x]=$valkurs[$y];
							break 1;
						}
					}
					$tmp=$periodesum[$x]*100/$kontokurs[$y];
					$title="DKK ".dkdecimal($periodesum[$x],2)." Kurs: ".dkdecimal($kontokurs[$x],2);
				} else {
					$tmp=$periodesum[$x];
					$title=NULL;
				}
#cho $aarsum[$x]."<br>";
				print "<td align=\"right\" title=\"$title\"><b>".dkdecimal($tmp,2)."</b></td>";
				if ($kontovaluta[$x]) {
					$tmp=$aarsum[$x]*100/$kontokurs[$x];
					$title="DKK ".dkdecimal($aarsum[$x],2)." Kurs: ".dkdecimal($kontokurs[$x],2);
				} else {
					if ($lastYear) $tmp=$lastYearYearSum[$x];
					else $tmp=$aarsum[$x];
					$title=NULL;
				}
				print "<td align=\"right\" title=\"$title\"><b>".dkdecimal($tmp,2)."</b></td>";
				if ($rapportart=='budget') {
					if ($kontovaluta[$x]) {
						$tmp=$aarsum[$x]*100/$kontokurs[$x];
						$title="DKK ".dkdecimal($aarsum[$x],2)." Kurs: ".dkdecimal($kontokurs[$x],2);
					} else {
						if ($aarsum[$x]) $tmp=($periodesum[$x]-$aarsum[$x])*100/$aarsum[$x];
						else $tmp="--";
						$title=NULL;
					}
					print "<td align=\"right\" title=\"$title\"><b>".dkdecimal($tmp,2)."%</b></td>";
				}
				print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
			} else {
				if (in_array($kontonr[$x],$varekob)) {
					$title="Heraf på lager: ".dkdecimal($l_a_sum[$x]-$l_p_primo[$x],2);
				} else $title='';
				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				print "<tr bgcolor=\"$linjebg\"><td>$kontonr[$x]</td>";
				$tmp = kontobemaerkning($kontobeskrivelse[$x]);
				print "<td $tmp colspan=\"3\">$kontobeskrivelse[$x]</td>";
				if ($kontovaluta[$x]) {
					$tmp=$periodesum[$x]*100/$kontokurs[$x];
					$title="DKK ".dkdecimal($periodesum[$x],2)." Kurs: ".dkdecimal($kontokurs[$x],2);
				} else {
					$tmp=$periodesum[$x];
					$title=NULL;
				}
				print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
				if ($kontovaluta[$x]) {
					$tmp=$aarsum[$x]*100/$kontokurs[$x];
					$title="DKK ".dkdecimal($aarsum[$x],2)." Kurs: ".dkdecimal($kontokurs[$x],2);
				} else {
					$tmp=$aarsum[$x];
					$title=NULL;
				}
				if ($lastYear) $tmp=dkdecimal($lastYearYear[$x],2); #aar til dato
				else $tmp=dkdecimal($aarsum[$x],2);
				print "<td align=\"right\" title=\"$title\">".dkdecimal($tmp,2)."</td>";
				if ($rapportart=='budget') {
					if ($kontovaluta[$x] && $aarsum[$x]) {
						$tmp=(($periodesum[$x]-$aarsum[$x])*100/$aarsum[$x])*100/$kontokurs[$x];
						$title="DKK ".dkdecimal($periodesum[$x],2)." Kurs: ".dkdecimal($kontokurs[$x],2);
					} elseif ($aarsum[$x]) {
						$tmp=($periodesum[$x]-$aarsum[$x])*100/$aarsum[$x];
						$title=NULL;
					}	else $tmp="--";
					print "<td align=\"right\">".dkdecimal($tmp,2)."%</td>"; #afvigelse fra budget
				}
				print "</tr>";
			}
		}
	}
	print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
	print "</tbody></table>";
	print "<!--Function regnskab slut-->\n";
}
#################################################################################################
?>
