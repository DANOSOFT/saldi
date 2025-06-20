<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------------finans/regnskab.php----lap 3.7.2------2018-11-22------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2018 saldi.dk aps
// ----------------------------------------------------------------------

// 2012.10.11 Indsat "and (lukket != 'on' or saldo != 0)" søg 20121011
// 2012.11.06 Resultat føres ikke ned på resultatkonto. Søg 20121106
// 2013.02.10 Break ændret til break 1
// 2014.03.28	Rettet $modulnr til 3
// 2015.01.08 Div ændringer jvf. aut_lager. Søg $aut_lager
// 2015.01.25 Fejl i lagerberegning i statusrapport- lagetræk blev lagt til værdi, ombyttet + & - - Søg 20150125
// 2015.04.08 Fejl i lagerberegning i statusrapport- medtog sidste dag i foregående md - tilføjet 'start' til find_lagervaerdi Søg find_lagervaerdi
// 2016.01.16	Diverse i forbindelse med indførelse af valutakonti	Søg 'valuta'
// 2018.02.09	PHR Tilføjet ,2 i alle forekomster af dkdecimal.
// 2018.10.28 CA Tilføjet manglende / forrest i linje 27
// 2108.11.21 PHR Oprydning udefinerede variabler.
// 2018.11.22 PHR Knap for Beregn lagerværdi.
// 2018.12.14 MS Topmenu

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

$beregn_lager=if_isset($_POST['beregn_lager']);


if ($menu=='T') {
include_once '../includes/top_header.php';
include_once '../includes/top_menu.php';
print "<div id=\"header\">"; 
print "<span class=\"headerTxt\">Regnskab</span>";     
#print "<div class=\"headerbtnRght\"><!--<a href=\"index.php?page=debitor/ordre&amp;title=debitor\" class=\"button green small right\">Ny ordre</a>--></div>";       
print "</div><!-- end of header -->";
#print "<div class=\"maincontentLargeHolder\">\n";
#	$leftbutton="<a title=\"Klik her for at lukke kladdelisten\" href=\"../index/menu.php\" accesskey=\"L\">LUK</a>";
	$rightbutton="<a href=../finans/budget.php accesskey=b>Budget</a>";
} else {
	print "<center>";
#	print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print "	<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "		<table width='100%' height='20' align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "			<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\">";
	if ($popup) print "<a href=../includes/luk.php accesskey=L>Luk</a></td>";
	else print "<a href=\"../index/menu.php\" accesskey=\"L\">Luk</a></td>";
	print "<td width=\"80%\" $top_bund> Regnskab</td> ";
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
print "<table width=100% cellpadding=\"0\" cellspacing=\"1px\" border=\"0\" valign = \"top\"> ";
print "<tbody>";

if ($vis_medtag_lager) {
	$title="Klik for at beregne den altuelle lagerværdi i forhold lagertilgang og afgang reguleret på varekøb"; 
	print  "<tr>";
	print "<td colspan='$cols' align='center'>";
	print "<form name='stockvalue' method='post' action='regnskab.php'>";
	print "&nbsp;<input type='submit' title='$title' name='beregn_lager' value='Beregn lagerværdi'>";
	print "</form></td></tr>";
}
print "<tr><td><b> Kontonr.</b></td> ";
print "<td><b> Kontonavn</b></td> ";
fwrite($csv,"Kontonr;Kontonavn");
if ($vis_valuta) {
	print "<td align=\"center\"><b>Valuta</b></td>";
	fwrite($csv,";Valuta");
}
print "<td title=\"Saldi ved regnskabs&aring;rets begyndelse. De fleste overf&oslash;rt fra regnskabet &aring;ret f&oslash;r.\" align=right><b> Primo</a></b></td> ";
fwrite($csv,";Primo");
#for ($z=1; $z<=$maanedantal; $z++) {
#	print "<td title=\"$z. regnskabsm&aring;ned\" align=right><b> MD_$z<b><br></td>";
#}
$tmp=periodeoverskrifter($maanedantal, $startaar, $startmaaned, 1, "regnskabsmaaned", $regnskabsaar);
fwrite($csv,";". str_replace('"','',$tmp) ."I alt\n");
#$cols+=count(explode(";",$tmp));
print "<td align=right><b> I alt</a></b></td> ";
print "</tr>";
$y='';
for ($x=1; $x<=$kontoantal; $x++){
	if (!isset($ultimo[$x])) $ultimo[$x]=0;
	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
	else {$linjebg=$bgcolor5; $color='#000000';}
	print "<tr bgcolor=$linjebg>";
	if ($kontotype[$x]=='H' || $kontotype[$x]=='X') {
		print "<td><b> $kontonr[$x]<br></b></td>";
		fwrite($csv,"$kontonr[$x];". utf8_decode($beskrivelse[$x]) ."\n");
		print "<td colspan=\"$cols\"><b>$beskrivelse[$x]<br></b></td>";
	}	else {
#		if ($kontotype[$x]!='Z') {$link="<a href=kontospec.php?kontonr=$kontonr[$x]&month=";}
#		else {$link='';}
		print "<td>$kontonr[$x]<br></td>";
		print "<td>$beskrivelse[$x]<br></td>";
		fwrite($csv,"$kontonr[$x];". utf8_decode($beskrivelse[$x]) ."");
		if ($vis_valuta) print "<td align=\"center\">$valutanavn[$x]</td>";
		$title='';
		if ($kontovaluta[$x]) {
			$tal=dkdecimal($primo[$x]*100/$primokurs[$x],2);
			$title="DKK: ".dkdecimal($primo[$x],2)." Kurs: $mdkurs";
		} else $tal=dkdecimal($primo[$x],2);
		print "<td align=\"right\" title=\"$title\">$tal<br></td>";
		fwrite($csv,";$tal");
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
	}
	if ($row['kontotype']=='H') {$linjebg='#ffffff'; $color='#ffffff';}
}
if ($menu=='T') print "</div>";	
####################################################################################################
fclose($csv);
print "<tr><td><a href=\"../temp/$db/regnskab.csv\">regnskab.csv</a>";
print "</tbody></table>";
print "</body></html>";
?>
