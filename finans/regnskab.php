<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---finans/regnskab.php --- patch 4.1.1 --- 2025.05.10 ---
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// --------------------------------------------------------------------------

// 20121011 Indsat "and (lukket != 'on' or saldo != 0)" søg 20121011
// 20121106 Resultat føres ikke ned på resultatkonto. Søg 20121106
// 20130210 Break ændret til break 1
// 20140328	Rettet $modulnr til 3
// 20150108 Div ændringer jvf. aut_lager. Søg $aut_lager
// 20150125 Fejl i lagerberegning i statusrapport- lagetræk blev lagt til værdi, ombyttet + & - - Søg 20150125
// 20150408 Fejl i lagerberegning i statusrapport- medtog sidste dag i foregående md - tilføjet 'start' til find_lagervaerdi Søg find_lagervaerdi
// 20160116	Diverse i forbindelse med indførelse af valutakonti	Søg 'valuta'
// 20180209	PHR Tilføjet ,2 i alle forekomster af dkdecimal.
// 20181028 CA Tilføjet manglende / forrest i linje 27
// 21081121 PHR Oprydning udefinerede variabler.
// 20181122 PHR Knap for Beregn lagerværdi.
// 20181214 MS Topmenu
// 20210225 LOE replaced the text with value from findtekst function
// 20210225 LOE tranlated kontoplan.txt to English and implemented activeLanguage where Danish is the default
// 20210607 LOE updated the if function retrieving the data from kontoplan.txt file for Danish and English languages
// 20210721 LOE translated some texts here and also updated title texts with translated ones.
// 20220624 CA  rolled back retrieving data from kontoplan.txt DA and EN cause it overwrites existing accounting plans.
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20250113 PHR Syncronized with saldiupdates
// 20250510 LOE Text id changed from 3072 to 2373

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$modulnr=3;
$title="Regnskabsoversigt";

$linjebg=NULL;
$varelager_i=$varelager_u=$varekob=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/finansfunk.php");
include("../includes/topline_settings.php");
include("../includes/row-hover-style.js.php");

print '<script src="../javascript/chart.js"></script>';
$backUrl = isset($_GET['returside'])
? $_GET['returside']
: '../index/menu.php';
$beregn_lager=if_isset($_POST['beregn_lager']);
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	print "<div class=\"headerbtnLft\">&nbsp;&nbsp;&nbsp;</div>";
	print "<div class=\"headerTxt\">".findtekst(322, $sprog_id)."</div>";
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu=='S') {
	print "<center>";
	print "<table width='100%' height='20' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";

	print "<td width='10%' align='center'><a href='$backUrl' accesskey='L'>";
	print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">".findtekst('30|Tilbage',$sprog_id)."</button></a></td>";

	print "<td width='80%' align='center' style='$topStyle'>".findtekst('849|Regnskab',$sprog_id)."</td>";

	print "<td width='10%' align='center'><a href=\"budget.php\" accesskey=\"B\">";
	print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">Budget</button></a></td>";

	print "</tbody></table> ";
	print "</td></tr> ";
} else {
	print "<center>";
#	print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print "	<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width='100%' height='20' align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\">";
	if ($popup) print "<a href=../includes/luk.php accesskey=L>".findtekst('30|Tilbage',$sprog_id)."</a></td>";//20210225
	else print "<a href=\"../index/menu.php\" accesskey=\"L\">".findtekst('30|Tilbage',$sprog_id)."</a></td>";
	print "<td width=\"80%\" $top_bund> ".findtekst('849|Regnskab',$sprog_id)."</td>";
	print "<td width=\"10%\" $top_bund><a href=\"budget.php\" accesskey=\"B\">Budget</a></td> ";
	print "</tbody></table> ";
	print "</td></tr> ";
}
$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);

$row = db_fetch_array($query);
$startmaaned=$row['box1'];
$startaar=$row['box2'];
$slutmaaned=$row['box3'];
$slutaar=$row['box4'];
$slutdato=31;



($startaar >= '2015')?$aut_lager='on':$aut_lager=NULL;
$vis_medtag_lager=0;
if ($aut_lager) {
	$x=0;
	$varekob=array();
	$q=db_select("select box1,box2,box3 from grupper where art = 'VG' and box8 = 'on'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['box1'] && $r['box2'] && !in_array($r['box3'],$varekob)) {
			$varelager_i[$x]=$r['box1'];
			$varelager_u[$x]=$r['box2'];
			$varekob[$x]=$r['box3'];
			if ($varelager_i[$x]) $vis_medtag_lager='1';
			$x++;
		}
	}
}
if (!$vis_medtag_lager) $aut_lager=NULL;
if (!$beregn_lager) $aut_lager=NULL;

while (!checkdate($slutmaaned, $slutdato, $slutaar)) {
	$slutdato=$slutdato-1;
	if ($slutdato<28) break 1;
}
$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

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
		$r2=db_fetch_array(db_select("select box1 from grupper where art='VK' and kodenr='$valkode[$x]'",__FILE__ . " linje " . __LINE__));
		$valnavn[$x]=$r2['box1'];
		$x++;
	}
}

$tmpaar=$startaar;
$md[1][0]=$startmaaned;
$md[1][1]=$regnstart;
$md[1][2]=0;
$x=1;
while ($md[$x][1]<$regnslut) {
	$x++;
	$md[$x][0]=$md[$x-1][0]+1;
	if ($md[$x][0]>12) {
		$tmpaar++;
		$md[$x][0]=1;
	}
	if ($md[$x][0]<10) $tmp="0".$md[$x][0];
	else $tmp=$md[$x][0];
	$md[$x][1]=$tmpaar. "-" .$tmp."-01";
	$md[$x][2]=0;
}
$vis_valuta=0;
$maanedantal=$x-1;
$x=0;
$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' and (lukket != 'on' or saldo != 0) order by kontonr",__FILE__ . " linje " . __LINE__); #20121011
while ($row = db_fetch_array($query)) {
	$x++;
	$konto_id[$x]=$row['id'];
	$kontonr[$x]=trim($row['kontonr']);
	$kontotype[$x]=$row['kontotype'];
	$beskrivelse[$x]=$row['beskrivelse'];
	$fra_kto[$x]=$row['fra_kto'];
	$til_kto[$x]=$row['til_kto'];
	$kontovaluta[$x]=$row['valuta'];
	$kontokurs[$x]=$row['valutakurs'];
	if ($kontotype[$x]=="S") $primo[$x]=afrund($row['primo'],2);
	else $primo[$x]=0;
	if ($kontovaluta[$x]) {
		for ($y=0;$y<=count($valkode);$y++){
			if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $regnstart) {
				$primokurs[$x]=$valkurs[$y];
				$valutanavn[$x]=$valnavn[$y];
				$vis_valuta=1;
				break 1;
			}
		}
	} else {
		$primokurs[$x]=100;
		$valutanavn[$x]='DKK';
	}
	if ($row['kontotype']=='D' || $row['kontotype']=='S') {
		$primo[$x]=round($row['primo']+0.0001,2);
		$ultimo[$x]=round($row['primo']+0.0001,2);
		$q2 = db_select("select * from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr='$kontonr[$x]' order by transdate",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
		 	for ($y=1; $y<=$maanedantal; $y++) {
				if (!isset($belob[$x][$y])) $belob[$x][$y]=0;
				if (($md[$y][1]<=$r2['transdate'])&&($md[$y+1][1]>$r2['transdate'])) {
			 		$md[$y][2]=$md[$y][2]+round($r2['debet']+0.0001,2)-round($r2['kredit']+0.0001,2);
					$belob[$x][$y]=$belob[$x][$y]+round($r2['debet']+0.0001,2)-round($r2['kredit']+0.0001,2);
					$transdate[$x][$y]=$r2['transdate'];
				}
			}
			$ultimo[$x]=$ultimo[$x]+round($r2['debet']+0.0001,2)-round($r2['kredit']+0.0001,2);
		}
	}
	if ($aut_lager) {
		if (in_array($kontonr[$x],$varekob)) {
		for ($y=1; $y<=$maanedantal; $y++) {
			if ($md[$y][1]<=date("Y-m-d")) {
					if (!isset($belob[$x][$y]))	$belob[$x][$y] = 0;
					$l_m_sum[$x]=find_lagervaerdi($kontonr[$x],$md[$y+1][1],'start');
					$l_m_primo[$x]=find_lagervaerdi($kontonr[$x],$md[$y][1],'start');
	#				$l_p_primo[$x]=find_lagervaerdi($kontonr[$x],$regnaarstart);
					$ultimo[$x]+=$l_m_primo[$x]-$l_m_sum[$x];
					$md[$y][2]+=$l_m_primo[$x]-$l_m_sum[$x];
					$belob[$x][$y]+=$l_m_primo[$x]-$l_m_sum[$x];
					}
			}
		}
		if (in_array($kontonr[$x],$varelager_i) || in_array($kontonr[$x],$varelager_u)) {
		 	for ($y=1; $y<=$maanedantal; $y++) {
				if (!isset($belob[$x][$y])) $belob[$x][$y]=0;
				if ($md[$y][1]<=date("Y-m-d")) {
					$l_m_primo[$x]=find_lagervaerdi($kontonr[$x],$md[$y][1],'start');
					$l_m_sum[$x]=find_lagervaerdi($kontonr[$x],$md[$y+1][1],'start');
					$ultimo[$x]-=$l_m_primo[$x]-$l_m_sum[$x]; #20150125 + næste 3 linjer
					$md[$y][2]-=$l_m_primo[$x]-$l_m_sum[$x];
					$belob[$x][$y]-=$l_m_primo[$x]-$l_m_sum[$x];
				}
			}
		}
	}
}
$kontoantal=$x;

for ($x=1; $x<=$kontoantal; $x++) {
	if (!isset($ultimo[$x])) $ultimo[$x]=0;
	for ($y=1; $y<=$maanedantal; $y++) {
		if ($kontotype[$x]=='Z') {
			$primo[$x]=0;
 			$belob[$x][$y]=0;
			for ($z=1; $z<=$x; $z++){
				if (($kontonr[$z]>=$fra_kto[$x])&&($kontonr[$z]<=$kontonr[$x])&&($kontotype[$z]!='H')&&($kontotype[$z]!='Z')){
				if (isset($primo[$z])) $primo[$x]+=$primo[$z];
					if (isset($belob[$z][$y])) {
						$belob[$x][$y]+=$belob[$z][$y];
						$ultimo[$x]+=$belob[$z][$y];
					}
				}
			}
 		}
		if ($kontotype[$x]=='R') { #20121106
			$primo[$x]=0;
 			$belob[$x][$y]=0;
			for ($z=1; $z<=$x; $z++){
				if ($kontonr[$z]==$fra_kto[$x]){
				if (isset($primo[$z])) $primo[$x]+=$primo[$z];
					if (isset($belob[$z][$y])) {
						$belob[$x][$y]+=$belob[$z][$y];
						$ultimo[$x]+=$belob[$z][$y];
					}
				}
			}
 		}
 	}
}
$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$startmaaned=$row['box1']*1;
$startaar=$row['box2']*1;
$slutmaaned=$row['box3']*1;
$slutaar=$row['box4']*1;
$slutdato=31;
$regnskabsaar=$row['beskrivelse'];

while (!checkdate($slutmaaned,$slutdato,$slutaar)){
	$slutdato=$slutdato-1;
	if ($slutdato<28) break 1;
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

#######

// $t = activeLanguage();
// if($t == 'English'){
// 	echo "this is English";
// }else{

// }

########
$csv=fopen("../temp/$db/regnskab.csv","w");

$ktonr=array();
$x=0;
$query = db_select("select kontonr from transaktioner where transdate>'$regnstart' and transdate<'$regnslut' order by transdate",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)){
	$x++;
	$ktonr[$x]=$row['kontonr']*1;
}
($vis_valuta)?$cols=5:$cols=4;
$cols+=$maanedantal;
#print "<div style=\"position:relative;top:0px;left:0px;height:100%width:100%;overflow:auto;\">";
print "<table width=100% cellpadding=\"0\" cellspacing=\"1px\" border=\"0\" valign = \"top\" class='dataTable'> ";
print "<tbody>";

if ($vis_medtag_lager) {
	$title= findtekst(1624, $sprog_id);
	// print "<tr>";
	// print "<td colspan='$cols' align='center'>";
	// print "<form name='stockvalue' method='post' action='regnskab.php'>";
	// print "&nbsp;<input type='submit' title='$title' name='beregn_lager' value='".findtekst(595,$sprog_id)." ".findtekst(596,$sprog_id)."'>";
	// print "</form></td></tr>";
}

print "<tr><td width='8%'><b>".findtekst('804|Kontonr', $sprog_id)."</b></td>"; #20210721
print "<td><b> ".findtekst('805|Kontonavn', $sprog_id)."</b></td> ";
fwrite($csv,"Kontonr;Kontonavn");
if ($vis_valuta) {
	print "<td align=\"center\"><b>".findtekst(776, $sprog_id)."</b></td>";
	fwrite($csv,";Valuta");
}
print "<td title=\"".findtekst(1625, $sprog_id)."\" align=right><b> ".findtekst(1229, $sprog_id)."</a></b></td> ";
fwrite($csv,";Primo");
#for ($z=1; $z<=$maanedantal; $z++) {
#	print "<td title=\"$z. regnskabsm&aring;ned\" align=right><b> MD_$z<b><br></td>";
#}
$tmp=periodeoverskrifter($maanedantal, $startaar, $startmaaned, 1, "regnskabsmaaned", $regnskabsaar);
fwrite($csv,";". str_replace('"','',$tmp) ."I alt\n");
#$cols+=count(explode(";",$tmp));

$txt2373 = findtekst('2373|I alt', $sprog_id);
print "<td align=right><b>$txt2373</a></b></td> ";
print "</tr>";
$y='';
for ($x=1; $x<=$kontoantal; $x++){
	if (!isset($ultimo[$x])) $ultimo[$x]=0;

	# Find the background color depending on the user defined colors
	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
	else {$linjebg=$bgcolor5; $color='#000000';}

	# Highlight sum lines
	if ($kontotype[$x] == "Z") $linjebg_highlight = "#ffaaaa";
	else $linjebg_highlight = $linjebg;

	print "<tr bgcolor=$linjebg_highlight>";
	if ($kontotype[$x]=='H' || $kontotype[$x]=='X') {
		print "<td><b> $kontonr[$x]<br></b></td>";
		fwrite($csv,"$kontonr[$x];". mb_convert_encoding($beskrivelse[$x], 'ISO-8859-1', 'UTF-8') ."\n");
		print "<td colspan=\"$cols\"><b>$beskrivelse[$x]<br></b></td>";
	}	else {
#		if ($kontotype[$x]!='Z') {$link="<a href=kontospec.php?kontonr=$kontonr[$x]&month=";}
#		else {$link='';}
		if ($ultimo[$x] != 0) $cursor = "pointer";
		else $cursor = "no-drop";
		print "<td onclick=\"click_row($x)\" style='cursor: $cursor'>$kontonr[$x]<br></td>";

		if ($kontotype[$x] == "Z") $text = "Sumkonti $fra_kto[$x] - $til_kto[$x]";
		else $text = "";
		print "<td title='$text'>$beskrivelse[$x]<br></td>";
		fwrite($csv,"$kontonr[$x];". mb_convert_encoding($beskrivelse[$x], 'ISO-8859-1', 'UTF-8') ."");

		$konti_total = array();

		if ($vis_valuta) print "<td align=\"center\">$valutanavn[$x]</td>";
		$title='';
		if ($kontovaluta[$x]) {
			$tal=dkdecimal($primo[$x]*100/$primokurs[$x],2);
			$title="DKK: ".dkdecimal($primo[$x],2)." Kurs: $mdkurs";
		} else $tal=dkdecimal($primo[$x],2);

		# Primo
		print "<td align=\"right\" title=\"$title\">$tal<br></td>";
		fwrite($csv,";$tal");

		# Each month
		for ($z=1; $z<=$maanedantal; $z++) {
			$title='';
			if (!isset($belob[$x][$z])) $belob[$x][$z]=0;
			if ($kontovaluta[$x]) {
				for ($y=0;$y<=count($valkode);$y++){
					if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $transdate[$x][$z]) {
						$mdkurs=$valkurs[$y];
						break 1;
					}
				}
				$tal=dkdecimal($belob[$x][$z]*100/$mdkurs,2);
				$title="DKK: ".dkdecimal($belob[$x][$z],2)." Kurs: $mdkurs";
			}	else $tal=dkdecimal($belob[$x][$z],2); # if ($link) $y=$z.">";
			if ($kontotype[$x]!='Z') {
				print "<td align=\"right\" title=\"$title\"><a href=kontospec.php?kontonr=$kontonr[$x]&month=$z>$tal<br></a></td>";
			} else print "<td align=\"right\" title=\"$title\">$tal<br></td>";
			fwrite($csv,";$tal");
			$konti_total[] = usdecimal($tal);
		}
		if ($kontotype[$x]=='Z') $ultimo[$x]=$ultimo[$x]+$primo[$x];  # if indsat 20.11.07 grundet fejl i sammentaeling paa statuskonti
		$title='';
		if ($kontovaluta[$x]) {
				for ($y=0;$y<=count($valkode);$y++){
					if ($valkode[$y]==$kontovaluta[$x] && $valdate[$y] <= $regnslut[$x][$z]) {
						$mdkurs=$valkurs[$y];
						break 1;
					}
				}
				$tal=dkdecimal($ultimo[$x]*100/$mdkurs,2);
				$title="DKK: ".dkdecimal($ultimo[$x],2)." Kurs: $mdkurs";
			}	else $tal=dkdecimal($ultimo[$x],2); # if ($link) $y=$z.">";
#		$tal=dkdecimal($ultimo[$x]); # if ($link) {$y='13>';}
		print "<td align=\"right\" title=\"$title\">$tal<br></td>";
		fwrite($csv,";$tal\n");
		$y='';
		print "</tr>";

		# Print barchart
		## Check the sum to avoid zero division
		if (array_sum($konti_total) != 0 && isset($_GET["graph_display"])) {
			print "<tr class='tablerows' id='row-$x' bgcolor=$linjebg style='display: none; height: 200px'>";
			print "<td colspan=3></td>";
			print "<td colspan=12>";
			display_chart(
				$x,
				$beskrivelse[$x],
				$konti_total,
				$kontotype[$x] == "Z" ? $fra_kto[$x] : $kontonr[$x],
				$kontotype[$x] == "Z" ? $til_kto[$x] : 0
			);
			print "<td colspan=1></td>";
			print "</tr>";
		}
	}
	if (isset($row['kontotype']) && $row['kontotype']=='H') {$linjebg='#ffffff'; $color='#ffffff';}
}
if ($menu=='T') print "</div>";
####################################################################################################
fclose($csv);
print "<tr><td colspan='20'><center><input type='button' style='width: 200px' onclick=\"document.location='../temp/$db/regnskab.csv'\" value='Regnskab.CSV'></input></center></td></tr>";
print "</tbody></table>";
print "</body></html>";

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

function display_chart($x, $beskrivelse, $konti_total, $fra_kto, $til_kto) {
	/**
	 * Displays a chart comparing actual data with a budget over the months.
	 *
	 * @param int $x The unique identifier for the chart (used to differentiate multiple charts).
	 * @param string $beskrivelse A description label for the chart's actual data series.
	 * @param array $konti_total An array containing the actual amounts for each month.
	 * @param int $fra_kto The starting account number for the query.
	 * @param int|null $til_kto The ending account number for the query. If `null`, the query uses only `$fra_kto`.
	 */

	global $regnaar, $regnskabsaar;
	global $maanedantal;
	global $startaar, $startmaaned;

	// Generate the list of months starting from the input month
	$months = [];
	for ($i = 0; $i < 12; $i++) {
		// Calculate the current month and year
		$currentMonth = ($startmaaned + $i - 1) % 12 + 1;
		$currentYear = $startaar + floor(($startmaaned + $i - 1) / 12);

		// Format the month and year as "M'yy"
		$monthName = ucfirst(strtolower(date("M", mktime(0, 0, 0, $currentMonth, 1, $currentYear))));
		$monthYear = $monthName . "'" . substr($currentYear, 2, 2);

		// Add the formatted month-year to the array
		$months[] = $monthYear;
	}

	# Create the database query fetching all 12 months with the budget data
	$qtxt = "SELECT
			m.md AS month,
			COALESCE(SUM(b.amount), 0) AS amount
		FROM
			(SELECT 1 AS md UNION ALL
			SELECT 2 UNION ALL
			SELECT 3 UNION ALL
			SELECT 4 UNION ALL
			SELECT 5 UNION ALL
			SELECT 6 UNION ALL
			SELECT 7 UNION ALL
			SELECT 8 UNION ALL
			SELECT 9 UNION ALL
			SELECT 10 UNION ALL
			SELECT 11 UNION ALL
			SELECT 12) m
		LEFT JOIN
			budget b
		ON
			m.md = b.md
			AND b.regnaar = $regnaar
			";
	# If it is a sumkonto calculate budget by the sum
	if ($til_kto) $qtxt .= "AND b.kontonr >= $fra_kto AND b.kontonr <= $til_kto";
	else $qtxt .= "AND b.kontonr = $fra_kto";

	$qtxt .= " GROUP BY m.md ORDER BY m.md";
	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);

	$budget = array();
	while ($row = db_fetch_array($query)){
		$budget[] = $row["amount"];
	}

	# Create the chart using chartJS
	?>
	<canvas id='mixedChart<?php print $x; ?>'></canvas>
	<script>
		var ctx = document.getElementById('mixedChart<?php print $x; ?>').getContext('2d');
		var data = {
			datasets: [
				{
					type: 'bar',
					label: '<?php print $beskrivelse; ?>',
					data: [<?php print implode(",", $konti_total); ?>].map((x) => (x*-1)),
					borderWidth: 2
				},
				{
					type: 'line',
					label: 'Budget',
					data: [<?php print implode(",", $budget); ?>].map((x) => (x*-1)),
					borderColor: 'rgba(54, 162, 235, 1)',
					borderWidth: 2,
					fill: false,
					tension: 0.4 // Smoother line
				}
			],
			labels: ["<?php print implode("\",\"", $months); ?>"]
		};

		// Options for the chart
		var options = {
			responsive: true,
			interaction: {
				mode: 'index',
				intersect: false,
			},
			maintainAspectRatio: false,
			scales: {
				y: {
					beginAtZero: true,
				}
			},
			plugins: {
				legend: {
					display: false // Hides the legend
				}
			}
		};

		// Initialize the chart
		var mixedChart = new Chart(ctx, {
			type: 'bar',
			data: data,
			options: options
		});
	</script>

	<?php
}
?>

<script>
	// Fetches a get parameter
	function findGetParameter(parameterName) {
		var result = null,
			tmp = [];
		location.search
			.substr(1)
			.split("&")
			.forEach(function (item) {
			tmp = item.split("=");
			if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
			});
		return result;
	}

	// Function to toggle row display and store clicked row in URL
	function click_row(x) {
		console.log("Checkrow");

		if (!findGetParameter("graph_display")) {
			window.location.href = `?graph_display=on&row=${x}`;
		}

		const row = document.getElementById(`row-${x}`);
		if (!row) {
			return 0;
		}

		if (row.style.display == "") {
			row.style.display = "none";
		} else {
			row.style.display = "";
		}
	}

	// On page load, check if a row was clicked and toggle it
	window.onload = function() {
		const clickedRow = findGetParameter("row");
		if (clickedRow) {
			// Call click_row with the clicked row value from the URL
			click_row(clickedRow);
		}
	};
</script>
