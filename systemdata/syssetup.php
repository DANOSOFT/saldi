<?php
// --- systemdata/syssetup.php --- lap 4.1.0 -- 2024-06-04 --
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
// Copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20132127 Indsat kontrol for at kodenr er numerisk på momskoder.
// 20140621 Ændret "kontrol for at kodenr er numerisk på momskoder" til at acceptere "-".
// 20141002 Tilføjet felt for omvendt betaling på kunder og varer.
// 20141212 CA  Tekstbokse i CSS som erstatning for JavaScript Alert-bokse. Søg 20141212A
// 20141212 CA  Variablen spantilte ændret til spantitle. Søg 20141212B
// 20150130 CA  Test af Lager Tilgang og Lager Træk ved den anden angivet i stedet for ved Lagerført. Søg 20150130
// 20150424 CA  Omdøbt funktionen udskriv til skriv_formtabel og flyttet den til filen skriv_formtabel.inc.php
// 20160118 PHR $kode blev ikke sat 20160118 
// 20160808 PHR function nytaar $box3,$box3 rettet til box2,box3 (Tak til forumbruger 'ht'). 
// 20161022 PHR tilretning iht flere afd pr lager. 20161022
// 20170405 PHR	Ganger resultat med 1 for at undgå NULL værdier
// 20181102 PHR Oprydning, udefinerede variabler.
// 20181220 MSC - Rettet isset fejl
// 20190221 MSC - Rettet topmenu design
// 20190221 MSC - Rettet isset fejl
// 20190225 MSC - Rettet topmenu design
// 20200308 PHR	Added Mysqli
// 20200308 PHR Removed 'Lagertilgang', 'Lagertræk' & 'Lagerregulering' from 'Varegrupper' 
// 20200512 PHR	Removed $box5 from 3. instance of skriv_formtabel in 'varegrupper'
// 20200512 PHR	Different changes for changes 30300308 to look nice in Firefox
// 20210513 Loe	These texts were translated but not entered here previously
// 20220607 MSC - Implementing new design
// 20220614 MSC - Added div class divSys
// 20240407 PHR - save moved to syssetupIncludes/saveData.php
// 20240604 PHR PHP8

@session_start();
$s_id=session_id();

$nopdat=NULL;	

$modulnr=1;
$title="Systemsetup";
$css="../css/standard.css";
$genberegn=NULL;


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("skriv_formtabel.inc.php");
include("../includes/genberegn.php");

if (!isset ($fejl)) $fejl = NULL;
$dd=date("Y-m-d");

if ($menu=='T') {
	#	print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id='header'>"; 
	print "<div class='headerbtnLft headLink'>&nbsp;&nbsp;&nbsp;</div>";     
	print "<div class='headerTxt'>$title</div>";     
	print "<div class='headerbtnRght headLink'>&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
	print "<div id='leftmenuholder'>";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class='maincontentLargeHolder'>\n";
	print "<center><table border='0' cellspacing='0' id='dataTable' class='dataTableSys' width='100%' height='350px'><tbody>";
} elseif ($menu=='S') {
	include("top.php");
	print "<table cellpadding='1' cellspacing='1' border='1'><tbody>";
} else {
	include("oldTop.php");
	print "<table cellpadding='1' cellspacing='1' border='1'><tbody>";
}
$valg=if_isset($_GET['valg']);

include_once("syssetupIncludes/saveData.php");

##############################################################################################################################
if ($nopdat!=1) {
	$x=0;
($valg=="projekter")?$sort='kodenr desc':$sort='kodenr';
#	else {
#		if ($db_type=='mysql' || $db_type=='mysqli') $tmp="CAST(kodenr AS SIGNED)";
#		else $tmp="to_number(textcat('0',kodenr),text(99999999))";
#	} 
	$feltbredde=6;
	$stockIO=NULL;
	$qtxt = "SELECT * FROM grupper ";
	$qtxt.= "WHERE ((art = 'SM' OR art = 'KM'  OR art = 'EM' OR art = 'YM' OR art = 'MR' OR art = 'DG' OR art = 'KG' ";
	$qtxt.= "OR art = 'VG' OR art = 'POS' OR art = 'OreDif') and fiscal_year = '$regnaar') ";
	$qtxt.= "OR art = 'AFD' OR art = 'LG' OR art = 'VPG' OR art = 'VTG' OR art = 'VRG' ";
	$qtxt.= "order by kodenr";
	if ($valg=="projekter") $qtxt.=' desc';
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)){
		$x++;
		$id[$x]	=	$row['id'];
		$beskrivelse[$x]	=	htmlentities(stripslashes($row['beskrivelse']),ENT_COMPAT,$charset);
		$kodenr[$x]       = $row['kodenr'];
		if (strlen($kodenr[$x]) > $feltbredde) $feltbredde=strlen($kodenr[$x]); 
		$kode[$x]         = $row['kode'];
		$art[$x]          = $row['art'];
		$box1[$x]         = $row['box1'];
		$box2[$x]         = $row['box2'];
		$box3[$x]         = $row['box3'];
		$box4[$x]         = $row['box4'];
		$box5[$x]         = $row['box5'];
		$box6[$x]         = $row['box6'];
		$box7[$x]         = $row['box7'];
		$box8[$x]         = $row['box8'];
		$box9[$x]         = $row['box9'];
		$box10[$x]        = $row['box10'];
		$box11[$x]        = $row['box11'];
		$box12[$x]        = $row['box12'];
		$box13[$x]        = $row['box13'];
		$box14[$x]        = $row['box14'];
		if ($art[$x] == 'VG' && $box1[$x] && $box2[$x]) $stockIO=1;
	}
}
if (!$valg) $valg='moms';
$y=$x+1;
print "<tr><td valign = top><table border=0><tbody>";
print "<form name=syssetup action=syssetup.php method=post>";
if ($valg=='moms'){
	$spantxt1 = findtekst('2244|En beskrivende tekst efter eget valg', $sprog_id);
	$spantxt2 = findtekst('2245|Det nummer i kontoplanen som salgsmomsen skal konteres på.', $sprog_id);
	$spantxt3 = findtekst('770|Moms', $sprog_id).' %';
	$spantxt4 = findtekst('3039|Map til', $sprog_id);
	$spantxt5 = findtekst('2246|Momskode hos SKAT', $sprog_id);
	print "<tr><td></td><td colspan=3><b><span title='".findtekst('2247|Den moms du skal betale til SKAT', $sprog_id)."'>".findtekst('994|Salgsmoms (udgående moms)', $sprog_id)."</span></td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td><td align='center'><span title='$spantxt1'>".findtekst('914|Beskrivelse', $sprog_id)."</span></td>";
	print "<td align='center'><span title='$spantxt2'>".findtekst('440|Konto', $sprog_id)."</span></td>";
	print "<td align='center'><span title='$spantxt3'>".findtekst('995|Sats', $sprog_id)."</span></td>";
	print "<td></td><td align='center'><span title='$spantxt5'>$spantxt4</span></td></tr>\n";		#20210513
	$y=skriv_formtabel('SM',$x,$y,$art,$id,'S',$kodenr,$beskrivelse,$box1,'6' ,$box2,'6','','6',$box4,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
	print "<tr><td><br></td></tr>\n";
	$spantxt2=findtekst('2302|Det nummer i kontoplanen som købsmomsen skal konteres på.', $sprog_id);
	print "<tr><td></td><td colspan=3><b><span title='".findtekst('2303|Den moms du skal have retur fra SKAT)', $sprog_id)."'>".findtekst('996|Købsmoms (indgående moms)', $sprog_id)."</span></td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td><td align='center'><span title='$spantxt1'>".findtekst('914|Beskrivelse', $sprog_id)."</span></td>";
	print "<td align='center'><span title='$spantxt2'>".findtekst('440|Konto', $sprog_id)."<span></td>";
	print "<td align='center'><span title='$spantxt3'>".findtekst('995|Sats', $sprog_id)."</span></td>\n";
	print "<td></td><td align='center'><span title='$spantxt5'>$spantxt4</span></td></tr>\n";		#20210513
	$y=skriv_formtabel('KM',$x,$y,$art,$id,"K",$kodenr,$beskrivelse,$box1,'6',$box2,'6','','6',$box4,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
	print "<tr><td><br></td></tr>\n";
	$spantxty2=findtekst('2304|Konto til postering af salgsmoms for ydelseskøb i udlandet', $sprog_id);
	$spantxty4=findtekst('2305|Konto til postering af købsmoms for ydelseskøb i udlandet', $sprog_id);
	$spantxty5=findtekst('2316|Ved ydelseskøb i udlandet, skal der betales lokal moms sats på vegne af sælgeren.', $sprog_id)." \n".findtekst('2318|Samtidig kan købsmomsen trækkes fra, så resultatet bliver 0.', $sprog_id);

	print "<tr><td></td><td colspan=3><b><span title='$spantxty5'>".findtekst('997|Moms af ydelseskøb i udlandet', $sprog_id)."</td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td>";
	print "<td align='center'><span title='$spantxt1'>".findtekst('914|Beskrivelse', $sprog_id)."</span></td>";
	print "<td align='center'><span title='$spantxty2'>".findtekst('440|Konto', $sprog_id)."<span></td>";
	print "<td align='center'><span title='$spantxt3'>".findtekst('995|Sats', $sprog_id)."</span></td>";
	print "<td align='center'><span title='$spantxt4'>".findtekst('1013|Modkonto', $sprog_id)."</span></td>\n";
	print "<td align='center'><span title='$spantxt5'>$spantxt4</span></td></tr>\n";		#20210513
	$y=skriv_formtabel('YM',$x,$y,$art,$id,"Y",$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'6',$box4,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
	print "<tr><td><br></td></tr>\n";
	$spantxt2=findtekst('2306|Konto til postering af salgsmoms for køb i udlandet', $sprog_id);
	$spantxte4=findtekst('2307|Konto til postering af købsmoms for køb i udlandet', $sprog_id);
	$spantxte5=findtekst('2317|Ved varekøb i udlandet, skal der betales lokal moms sats på vegne af sælgeren.', $sprog_id)." \n".findtekst('2318|Samtidig kan købsmomsen trækkes fra, så resultatet bliver 0.', $sprog_id);

	print "<tr><td></td><td colspan=3><b><span title='$spantxte5'>".findtekst('998|Moms af varekøb i udlandet', $sprog_id)."</span></b></td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td><td align='center'><span title='$spantxt1'>".findtekst('914|Beskrivelse', $sprog_id)."</span></td>";
	print "<td align='center'><span title='$spantxt2'>".findtekst('440|Konto', $sprog_id)."<span></td>";
	print "<td align='center'><span title='$spantxt3'>".findtekst('995|Sats', $sprog_id)."</span></td>";
	print "<td align='center'><span title='$spantxt4'>".findtekst('1013|Modkonto', $sprog_id)."</span></td>\n";
	print "<td align='center'><span title='$spantxt5'>$spantxt4</span></td></tr>\n";		#20210513
	$y=skriv_formtabel('EM',$x,$y,$art,$id,"E",$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'6',$box4,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
	print "<tr><td><br></td></tr>\n";
	print "<tr><td></td><td colspan=3><b>".findtekst('1009|Momsrapport (konti som skal indgå i momsrapport)', $sprog_id)."</b></td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td>";
	print "<td align='center'><span title='$spantxt1'>".findtekst('914|Beskrivelse', $sprog_id)."</span></td>";
	print "<td align='center'><span title='".findtekst('2308|Første kontonummer som skal indgå i rapporten', $sprog_id)."'>".findtekst('903|fra', $sprog_id)."</span></td>";
	print "<td align='center'><span title='".findtekst('2309|Sidste kontonummer som skal indgå i rapporten', $sprog_id)."'>".findtekst('904|til', $sprog_id)."</span></td>";
	print "<td><span title='".findtekst('2310|Kontonummer for samlet varekøb i EU', $sprog_id)."'>".findtekst('2315|Rubrik', $sprog_id)." A1</span></td>";
	print "<td><span title='".findtekst('2311|Kontonummer for samlet ydelseskøb i EU', $sprog_id)."'>".findtekst('2315|Rubrik', $sprog_id)." A2</span></td>";
	print "<td><span title='".findtekst('2312|Kontonummer for samlet varesalg i EU', $sprog_id)."'>".findtekst('2315|Rubrik', $sprog_id)." B1</span></td>";
	print "<td><span title='".findtekst('2313|Kontonummer for samlet ydelsessalg i EU', $sprog_id)."'>".findtekst('2315|Rubrik', $sprog_id)." B2</span></td>";
	print "<td><span title='".findtekst('2314|Kontonummer for samlet vare- og ydelsessalg uden for EU', $sprog_id)."'>".findtekst('2315|Rubrik', $sprog_id)." C</span></td></tr>\n";
	$y=skriv_formtabel('MR',$x,$y,$art,$id,"R",$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'6',$box4,'6',$box5,'6',$box6,'6',$box7,'6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
}
elseif($valg=='debitor'){
	print "<tr><td>";
	print infoboks('<span style=\'font-size:80%; font-weigth:bold; padding:0px 2px 0px 2px; font-family:monospace; background: #0000ff; color: #ffffff\'>i</span>', '<h2>Debitorhjælp</h2><p>Her er lidt tekst omkring brugen af debitorgrupper.</p>', 'info', 'infoboks1');
	print "</td><td colspan=2><b>".findtekst('1008|Debitorgrupper', $sprog_id)."</td><td></td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td><td align='center'>".findtekst('914|Beskrivelse', $sprog_id)."</td>";
	print "<td align='center'><span title='".findtekst('2319|Momsgruppe som debitorgruppen skal tilknyttes', $sprog_id)."'>".findtekst('1011|Momsgrp', $sprog_id)."</span></td>";
	print "<td align='center'><span title='".findtekst('2321|Samlekonto for debitorgruppen', $sprog_id)."'>".findtekst('2320|Samlekt.', $sprog_id)."</span></td><td align='center'>".findtekst('776|Valuta', $sprog_id)."</td>";
	print "<td align='center'><span title='".findtekst('1010|Det sprog der skal anvendes ved fakturering', $sprog_id)."'>".findtekst('801|Sprog', $sprog_id)."</td>";
	print "<td align='center'><span title='".findtekst('2323|Modkonto ved udligning af åbne poster', $sprog_id)."'>".findtekst('1013|Modkonto', $sprog_id)."</td>";
#	$spantitle="RABAT!\nHer angives rabatsatsen i procent for kundegruppen."; # 20141212B spantilte -> spantitle (start)
#	print "<td align='center'><span title='".$spantitle."'>Rabat</td>";
	$spantitle=findtekst('2324|Provisionsprocent', $sprog_id)."!\n".findtekst('2325|Her angives hvor stor en procentdel af dækningsbidraget det medgår ved beregning af provision.', $sprog_id);
	print "<td align='center'><span title='".$spantitle."'>".findtekst('657|Antal enheder købt før den', $sprog_id)."</td>\n";
	$spantitle="Business to business!\n".findtekst('2326|Afmærk her, hvis der skal anvendes B2B priser ved salg til denne kundegruppe', $sprog_id);
	print "<td align='center'><span title='".$spantitle."'>B2B</td>\n";
	$spantitle=findtekst('2327|Omvendt betalingspligt', $sprog_id)."!\n".findtekst('2328|Afmærk her, hvis denne kundegruppe er omfattet af omvendt betalingspligt', $sprog_id);
 	print "<td align='center'><span title='".$spantitle."'>".findtekst('2329|OB', $sprog_id)."</td></tr>\n"; # 20141212B spantilte -> spantitle (slut)
#cho "$id[$x] $beskrivelse[$x]<br>";
	$y=skriv_formtabel('DG',$x,$y,$art,$id,'D',$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'10',$box4,'10',$box5,'6','-','4',$box7,'4',$box8,'checkbox',$box9,'checkbox','-','2','-','2','-','2','-','2','-','2');
	print "<tr><td><br></td></tr>\n";
	print "<tr><td></td><td colspan=2><b>".findtekst('2330|Kreditorgrupper', $sprog_id)."</td><td></td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td><td align='center'>".findtekst('914|Beskrivelse', $sprog_id)."</td>";
	print "<td align='center'><span title='".findtekst('2331|Momsgruppe som kreditorgruppen skal tilknyttes', $sprog_id)."'>".findtekst('1011|Momsgrp', $sprog_id)."</span></td>";
	print "<td align='center'><span title='".findtekst('2332|Samlekonto for kreditorgruppen', $sprog_id)."'>".findtekst('2320|Samlekt.', $sprog_id)."</span></td><td align='center'>".findtekst('776|Valuta', $sprog_id)."</td>";
	print "<td align='center'><span title='".findtekst('2333|Det sprog, der skal anvendes ved kommunikation med kreditoren', $sprog_id)."'>".findtekst('801|Sprog', $sprog_id)."</span></td>";
	print "<td align='center'><span title='".findtekst('2323|Modkonto ved udligning af åbne poster', $sprog_id)."'>".findtekst('1013|Modkonto', $sprog_id)."</span></td>";
	print "<td align='center'><span title='".findtekst('2334|Momsgruppe for salgsmoms ved omvendt betalingspligt', $sprog_id)."'>".findtekst('2335|S.moms grp.', $sprog_id)."</span></td>";
	print "<td align='center' title='".findtekst('2327|Omvendt betalingspligt', $sprog_id)."!\n".findtekst('2337|Afmærk her, hvis denne leverandørgruppe er omfattet af omvendt betalingspligt', $sprog_id)."'>".findtekst('2329|OB', $sprog_id)."<!-- box9 --></td></tr>\n";
#	print "<td align='center'><span title='Omvendt betaligspligt!'Afmærk her,hvis denne leverandørgruppe er omfattet af omvendt betalingspligt>O/B</span></td></tr>\n";
	$y=skriv_formtabel('KG',$x,$y,$art,$id,'K',$kodenr,$beskrivelse,$box1,'6',$box2,'6',$box3,'10',$box4,'10',$box5,'10',$box6,'6','-','6','-','6',$box9,'checkbox','-','6','-','6','-','6','-','6','-','2');
}
elseif($valg=='afdelinger'){
	print "<tr><td></td><td colspan=3 align='center'><b>".findtekst('772|Brug f.eks', $sprog_id)."</td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td><td>".findtekst('914|Beskrivelse', $sprog_id)."</td><td>".findtekst('608|Lager', $sprog_id)."</td></tr>\n";
	$y=skriv_formtabel('AFD',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'10',"-",'2',"-",'2','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
}
elseif($valg=='projekter'){
	print "<tr><td></td><td colspan=3 align='center'><b>".findtekst('773|Projekter', $sprog_id)."</td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td><td>".findtekst('914|Beskrivelse', $sprog_id)."</td></tr>\n";
	$y=skriv_formtabel('PRJ',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,'-','2',"-",'2',"-",'2','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
}
elseif($valg=='lagre'){
	print "<tr><td></td><td colspan=3 align='center'><b>".findtekst('3|Gem', $sprog_id)."</td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td><td>".findtekst('914|Beskrivelse', $sprog_id)."</td><td align='center'>".findtekst('2336|Afd.', $sprog_id)."</td></tr>\n";
	$y=skriv_formtabel('LG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'2',"-",'2',"-",'2','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','2');
}
elseif($valg=='varer'){
	$t6= findtekst('2338|Afmærk her, hvis denne varegruppe er omfattet af omvendt betalingspligt', $sprog_id);
	$q = db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box4='on'",__FILE__ . " linje " . __LINE__);
	if (db_fetch_array($q)){
		print "<tr><td></td><td colspan=10 align='center'><b>".findtekst('774|Varegrupper', $sprog_id)."</td></tr><tr><td colspan=13><hr></td></tr>\n";
		print "<tr>";
		print "<td align='center'></td><td></td><td></td>";
		if ($stockIO) print "<td align='center'>".findtekst('608|Lager', $sprog_id)."-</td><td align='center'>".findtekst('608|Lager', $sprog_id)."-</td>";
		print "<td align='center'><!--Køb--></td>";
		print "<td align='center'><!--".findtekst('1007|Salgs', $sprog_id)."--></td>";
		#<td align='center'>Lager-</td>";
		print "<td title='$t6' align='center'>Omvendt-</td><td align='center'>".findtekst('770|Moms', $sprog_id)."-</td>";
		print "<td align='center'>".findtekst('608|Lager', $sprog_id)."-</td>";
		print "<td align='center'>Batch-</td><td align='center'>Opera-</td>\n";
		print "<td title='Kontonummer for enten kø af Varekøb i EU (Rubrik A1) eller Ydelseskøb i EU (Rubrik A2) - se Indstillinger - Moms'>".findtekst('1012|Køb', $sprog_id)."</td>\n";
		print "<td title='Kontonummer for enten Varesalg til EU (Rubrik B1) eller Ydelsessalg til EU (Rubrik B2) - se Indstillinger - Moms'>".findtekst('1007|Salgs', $sprog_id)."</td>\n";
		print "<td title='Kontonummer for en af Varekøb uden for EU, Ydelseskøb uden for EU eller Vare- og ydelseskøb uden for EU.'>".findtekst('1012|Køb', $sprog_id)." uden</td>\n";
		print "<td title='Kontonummer for en af Varesalg uden for EU, Ydelsessalg uden for EU eller Vare- og ydelsessalg uden for EU (Rubrik C). Hvis en af de to første angives, så skal kontonummeret være blandt de kontonumre, som summeres til en samlekonto for Vare- og ydelsessalg uden for EU (Rubrik C).'>Salg uden</td></tr>\n";
		print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td>";
		print "<td align='center'>".findtekst('914|Beskrivelse', $sprog_id)."</td>";
		if ($stockIO) print "<td align='center'>tilgang</td><td align='center'>træk</td>";
		print "<td align='center'>".findtekst('1012|Køb', $sprog_id)."</td>";
		print "<td align='center'>".findtekst('2340|Salg', $sprog_id)."<!--".findtekst('1007|Salgs', $sprog_id)."--></td>";
		#<td align='center'>regulering</td>
		print "<td  title='$t6' align='center'>betaling</td>";
		print "<td align='center'>fri</td><td align='center'>ført</td>";
		print "<td>kontrol</td><td align='center'>tion</td>\n";
		print "<td title='Kontonummer for enten Varekøb i EU (Rubrik A1) eller Ydelseskøb i EU (Rubrik A2) - se Indstillinger - Moms'>i EU</td>\n";
		print "<td title='Kontonummer for enten Varesalg til EU (Rubrik B1) eller Ydelsessalg til EU (Rubrik B2) - se Indstillinger - Moms'>til EU</td>\n";
		print "<td title='Kontonummer for en af Varekøb uden for EU, Ydelseskøb uden for EU eller Vare- og ydelseskøb uden for EU.'>for EU</td>\n";
		print "<td title='Kontonummer for en af Varesalg uden for EU, Ydelsessalg uden for EU eller Vare- og ydelsessalg uden for EU (Rubrik C). Hvis en af de to første angives, så skal kontonummeret være blandt de kontonumre, som summeres til en samlekonto for Vare- og ydelsessalg uden for EU (Rubrik C).'>for EU</td></tr>\n";
		if ($stockIO) {
		$y=skriv_formtabel('VG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'4',$box2,'4',$box3,'4',$box4,'4','-','',$box6,'checkbox',$box7,'checkbox',$box8,'checkbox',$box9,'checkbox',$box10,'checkbox',$box11,'4',$box12,'4',$box13,'4',$box14,'4');
		} else {
			$y=skriv_formtabel('VG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,'-','','-','',$box3,'4',$box4,'4','-','',$box6,'checkbox',$box7,'checkbox',$box8,'checkbox',$box9,'checkbox',$box10,'checkbox',$box11,'4',$box12,'4',$box13,'4',$box14,'4');
		}
	} else {
		print "<tr><td colspan=20 align='center'><b>".findtekst('774|Varegrupper', $sprog_id)."</td></tr><tr><td colspan=20><hr></td></tr>\n";
		print "<tr><td  title='$t6' align='center'></td><td></td><td></td>";
		if ($stockIO) {
			print "<td align='center'>".findtekst('608|Lager', $sprog_id)."-</td><td align='center'>".findtekst('608|Lager', $sprog_id)."-</td>";
		}	
		print "<td align='center'>".findtekst('110|Varer', $sprog_id)."-</td><td align='center'>".findtekst('110|Varer', $sprog_id)."-</td>";
#		print "<td align='center'>Lager-</td>";
		print "<td align='center'>Omvendt-</td>";
		print "<td align='center'>".findtekst('770|Moms', $sprog_id)."-</td>";
		print "<td align='center'>".findtekst('608|Lager', $sprog_id)."-</td>";
		print "<td align='center'>Batch-</td>";
		print "<td align='center'>Opera-</td>\n";
		print "<td title='Kontonummer for enten kø af Varekøb i EU (Rubrik A1) eller Ydelseskøb i EU (Rubrik A2) - se Indstillinger - Moms'>".findtekst('1012|Køb', $sprog_id)."</td>\n";
		print "<td title='Kontonummer for enten Vare".findtekst('1007|Salgs', $sprog_id)." til EU (Rubrik B1) eller Ydelsessalg til EU (Rubrik B2) - se Indstillinger - Moms'>".findtekst('1007|Salgs', $sprog_id)."</td>\n";
		print "<td title='Kontonummer for en af Varekø uden for EU, Ydelseskø uden for EU eller Vare- og ydelseskø uden for EU.'>Køb uden</td>\n";
		print "<td title='Kontonummer for en af Varesalg uden for EU, Ydelsessalg uden for EU eller Vare- og ydelsessalg uden for EU (Rubrik C). Hvis en af de to første angives, så skal kontonummeret være blandt de kontonumre, som summeres til en samlekonto for Vare- og ydelsessalg uden for EU (Rubrik C).'>Salg uden</td></tr>\n";
		print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td>";
		print "<td>".findtekst('914|Beskrivelse', $sprog_id)."</td>";
		if ($stockIO) print "<td align='center'>tilgang</td>";
		print "<td align='center'>træk</td>";
		print "<td align='center'>".findtekst('1012|Køb', $sprog_id)."</td>";
		print "<td align='center'>".findtekst('1007|Salgs', $sprog_id)."</td>";
#		print "<td align='center'>regulering</td>";
		print "<td title='$t6' align='center'>betaling</td>";
		print "<td align='center'>fri</td>";
		print "<td align='center'>ført</td>";
		print "<td align='center'>kontrol</td>";
		print "<td align='center'>tion</td>\n";
		print "<td title='Kontonummer for enten kø af Varekøb i EU (Rubrik A1) eller Ydelseskøb i EU (Rubrik A2) - se Indstillinger - Moms'>i EU</td>\n";
		print "<td title='Kontonummer for enten Varesalg til EU (Rubrik B1) eller Ydelsessalg til EU (Rubrik B2) - se Indstillinger - Moms'>til EU</td>\n";
		print "<td title='Kontonummer for en af Varekø uden for EU, Ydelseskø uden for EU eller Vare- og ydelseskø uden for EU.'>for EU</td>\n";
		print "<td title='Kontonummer for en af Varesalg uden for EU, Ydelsessalg uden for EU eller Vare- og ydelsessalg uden for EU (Rubrik C). Hvis en af de to første angives, så skal kontonummeret være blandt de kontonumre, som summeres til en samlekonto for Vare- og ydelsessalg uden for EU (Rubrik C).'>for EU</td></tr>\n";
		if ($stockIO) {
			$y=skriv_formtabel('VG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'4',$box2,'4',$box3,'4',$box4,'4','-','',$box6,'checkbox',$box7,'checkbox',$box8,'checkbox',$box9,'checkbox',$box10,'checkbox',$box11,'4',$box12,'4',$box13,'4',$box14,'4');
		} else {
			$y=skriv_formtabel('VG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,'-','','-','',$box3,'4',$box4,'4','-','',$box6,'checkbox',$box7,'checkbox',$box8,'checkbox',$box9,'checkbox',$box10,'checkbox',$box11,'4',$box12,'4',$box13,'4',$box14,'4');
		}
	}
	print "<tr><td colspan=20 align='center'><hr><b>Prisgrupper</td></tr><tr><td colspan=20><hr></td></tr>\n";
	print "<tr><td colspan=20><table width='100%' align='center'><tbody><tr><td align='center'></td><td></td><td></td>";
	print "<td align='center'>Kost-</td>";
	print "<td align='center'>".findtekst('1007|Salgs', $sprog_id)."-</td>";
	print "<td align='center'>Vejl-</td>";
	print "<td align='center'>B2B-</td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td>";
	print "<td>".findtekst('914|Beskrivelse', $sprog_id)."</td>";
	print "<td align='center'>pris</td>";
	print "<td align='center'>pris</td>";
	print "<td align='center'>pris</td>";
	print "<td align='center'>pris</td></tr>\n";
	$y=skriv_formtabel('VPG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'4',$box2,'4',$box3,'4',$box4,'4','-','6','-','2','-','0','-','0','-','0','-','0','-','0','-','0','-','0','-','0');
	print "</tbody></table></td></tr>";
	print "<tr><td colspan=20 align='center'><hr><b>Tilbudsgrupper</td></tr><tr><td colspan=20><hr></td></tr>\n";
	print "<tr><td colspan=20><table width='100%'><tbody>";
	print "<tr><td align='center'></td><td></td><td></td>";
	print "<td align='center'>Kost-</td>";
	print "<td align='center'>".findtekst('1007|Salgs', $sprog_id)."-</td>";
	print "<td align='center'>Start-</td><td align='center'>Slut-</td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td>";
	print "<td>".findtekst('914|Beskrivelse', $sprog_id)."</td>";
	print "<td align='center'>pris</td>";
	print "<td align='center'>pris</td>";
	print "<td align='center'>dato</td>";
	print "<td align='center'>dato</td></tr>\n";
	$y=skriv_formtabel('VTG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'4',$box2,'4',$box3,'7',$box4,'7','-','6','-','2','-','0','-','0','-','0','-','0','-','0','-','0','-','0','-','0');
	print "</tbody></table></td></tr>";
	print "<tr><td colspan=20><table width='100%'><tbody>";
	// Rabatgrupper
	print "<tr><td colspan=20 align='center'><hr><b>".findtekst('1006|Rabatgrupper', $sprog_id)."</td></tr><tr><td colspan=20><hr></td></tr>\n";
	print "<tr><td></td><td>".findtekst('2248|Nr.', $sprog_id)."</td>";
	print "<td>".findtekst('914|Beskrivelse', $sprog_id)."</td>";
	print "<td align='center'>Type</td>";
	print "<td align='center'>Stk. rabat</td>";
	print "<td align='center'>v. antal</td></tr>\n";
	$y=skriv_formtabel('VRG',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'2',$box2,'20',$box3,'20','-','2','-','4','-','2','-','4','-','2','-','7','-','7','-','0','-','0','-','0','-','0');
	print "</tbody></table></td></tr>";
}
elseif($valg=='formularer'){
	print "<tr><td></td><td colspan=5 align='center'><b>".findtekst('780|Formularer', $sprog_id)."</td></tr>\n";
	print "<tr><td></td><td colspan=5 align='center'><a href=\"logoupload.php?upload=Yes\">".findtekst('1004|Hent logo', $sprog_id)."</a></td></tr>\n";
	print "<tr><td></td><td></td><td align='center'>".findtekst('914|Beskrivelse', $sprog_id)."</td>";
	print "<td align='center'>".findtekst('1005|Printkommando', $sprog_id)."</td>";
	print "<td align='center'>PDF-kommando</td><td align='center'></td><td align='center'></td></tr>\n";
	$y=skriv_formtabel('PV',$x,$y,$art,$id,'&nbsp;',$kodenr,$beskrivelse,$box1,'20',$box2,'20','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6','-','6');
}
print "<tr><td><br></td></tr>\n";
print "</tbody></table></td>";
print "<input type = 'hidden' name=antal value=$y><input type = 'hidden' name=valg value=$valg>";
print "<tr><td colspan = 3 align = center><input class='button green medium' type=submit accesskey='g' value='".findtekst('471|Gem/opdatér', $sprog_id)."' name='submit'></td></tr>\n";
print "</form>";
print "</div>";

###########################################################################################################################

###########################################################################################################################

###########################################################################################################################
function kontotjek ($konto) { 
	global $regnaar;
	$fejl=NULL;
	$konto = (int)$konto;
	if ($konto) {
		$qtxt="SELECT id FROM kontoplan WHERE kontonr = '$konto' and (kontotype = 'D' or kontotype = 'S') and regnskabsaar='$regnaar'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$konto_id=$r['id'];
if (!$konto_id=$r['id']){
			print tekstboks('Kontonr: '.$konto.' kan ikke anvendes!'); # 20141212A
			$fejl=1;
#			print "<BODY onLoad=\"javascript:alert('Kontonr: $konto kan ikke anvendes!!')\">";
		} else #cho "ID $r[id]<br>";
	return $fejl;
	}
}
###########################################################################################################################
function sprogtjek ($sprog) { 
	$fejl=NULL;
	if ($sprog) {
		$tmp=strtolower($sprog);
		$query = db_select("SELECT id FROM formularer WHERE lower(sprog) = '$tmp'",__FILE__ . " linje " . __LINE__);
		if (!db_fetch_array($query)) { 
			print tekstboks('Der eksisterer ikke nogen formular med '.$sprog.' som sprog!'); # 20141212A
			$fejl=1;
		}
	return $fejl;
	}
}
###########################################################################################################################
function momsktotjek ($art,$konto) {
	$fejl=NULL;
	if ($konto) {
		if ($art=='DG') {$momsart="art='SM'";}
		if ($art=='KG') {$momsart="(art='KM' or art='YM' or art='EM')";}
		$kode=substr($konto,0,1);
		$kodenr=substr($konto,1,1);
		$qtxt="SELECT id FROM grupper WHERE $momsart and kodenr = '$kodenr' and kode = '$kode'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if (!$r['id']) { 
			if ($art=='DG') print tekstboks('Salgsmomsgruppe: '.$konto.' findes ikke!');
			if ($art=='KG') print tekstboks('Købsmomskonto: '.$konto.' findes ikke!');
			$fejl=1;
		}
		return $fejl;
	}
}
###########################################################################################################################
function afdelingstjek ($konto) {
	$fejl=NULL;
	$qtxt="SELECT id FROM grupper WHERE art='AFD' and kodenr = '$konto'";
	$r=db_fetch_array(db_select("SELECT id FROM grupper WHERE art='AFD' and kodenr = '$konto'",__FILE__ . " linje " . __LINE__));
	if (!$r['id'])	{
		tekstboks('Afdeling: '.$konto.' findes ikke!');
		$fejl=1;
	}
	return $fejl;
}
###########################################################################################################################
function opdater_varer($kodenr,$art,$box1,$box2,$box3,$box4) {
	if ($art=='VPG' && $kodenr) {
		if ($box1)$box1=usdecimal($box1);
		if ($box2)$box2=usdecimal($box2);
		if ($box3)$box3=usdecimal($box3);
		if ($box4)$box4=usdecimal($box4);
		if ($kodenr != '-') {
			if ($box1) db_modify("update varer set kostpris='$box1' where prisgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
			if ($box2) db_modify("update varer set salgspris='$box2' where prisgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
			if ($box3) db_modify("update varer set retail_price='$box3' where prisgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
			if ($box4) db_modify("update varer set tier_price='$box4' where prisgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
			return($box1.";".$box2.";".$box3.";".$box4);
		}
	} 
	if ($art=='VTG' && $kodenr) {
		if ($box1)$box1=usdecimal($box1);
		if ($box2)$box2=usdecimal($box2);
		if ($box3)$box3=usdate($box3);
		if ($box4)$box4=usdate($box4);
		if ($kodenr != '-') {
			if ($box1) db_modify("update varer set special_price='$box1' where tilbudgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
			if ($box2) db_modify("update varer set campaign_cost='$box2' where tilbudgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
			if ($box3) db_modify("update varer set special_from_date='$box3' where tilbudgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
			if ($box4) db_modify("update varer set special_to_date='$box4' where tilbudgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
		}
		return($box1.";".$box2.";".$box3.";".$box4);
	} 
	if ($art=='VRG' && $kodenr) {
		if ($box2)$box2=usdecimal($box2);
		if ($box3)$box3=usdecimal($box3);
		if ($kodenr != '-') {
			if ($box1) {
				db_modify("update varer set m_type='$box1' where rabatgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
			}
			if ($box2) {
				db_modify("update varer set m_rabat='$box2' where rabatgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
			}
			if ($box3) {
				db_modify("update varer set m_antal='$box3' where rabatgruppe = '$kodenr'",__FILE__ . " linje " . __LINE__);
			}
		}
	}
}
function titletxt($art,$felt) {
	global $sprog_id;
	$titletxt=NULL;
	if ($art=='VG') {
		if ($felt=='box1') $titletxt="Skriv kontonummeret for lagertilgang. Dette felt skal kun udfyldes hvis varen er lagerført og lagerværdien skal reguleres automatisk";
		elseif ($felt=='box2') $titletxt="Skriv kontonummeret for lagerafgang. Dette felt skal kun udfyldes hvis varen er lagerført og lagerværdien skal reguleres automatisk";
		elseif ($felt=='box3') $titletxt="Skriv kontonummeret for varekøb. Dette felt SKAL udfyldes";
		elseif ($felt=='box4') $titletxt="Skriv kontonummeret for varesalg. Dette felt SKAL udfyldes";
		elseif ($felt=='box5') $titletxt="Skriv kontonummeret for lagerregulering. Dette felt skal udfyldes hvis varen er lagerført";
	}
	if ($art=='DG' || $art=='KG') {
		if ($felt=='box3') $titletxt=findtekst('2322|Valuta styres af samlekontoen', $sprog_id);
	}
	return($titletxt);
}

print "
</tbody>
</table>
</td></tr>
</tbody></table>
</div>
";


if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>
