<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ systemdata/sys_div_func.php ------- lap 3.9.0 -- 2020-03-16 --
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
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------------
// Kaldes fra systemdata/diverse.php
// 2013.11.01 Tilføjet fravalg af tjek for forskellige datoer på samme bilag i kasseklasse. Søg 20131101
// 2013.12.10	Tilføjet valg om kort er betalingskort som aktiver betalingsterminal. Søg 21031210
// 2013.12.13	Tilføjet "intern" bilagsopbevaring (box6 under ftp)
// 2014.01.29	Tilføjet valg til automatisk genkendelse af betalingskort (kun ved integreret betalingsterminal) Søg 20140129
// 2014.05.08	Tilføjet valg til bordhåndtering under pos_valg Søg 20140508
// 2014.06.16 Tilføjet mellemkonto til pos kasser. Søg mellemkonto.
// 2014.07.01	FTP ændret til bilag og intern bilagsopbevaring flyttet til owncloud
// 2015.01.05 I sqlquery_io er separator ændret fra <tab> til ; tekster utf8 decodes og der sættes " om.
// 2015.04.11 Tilføjet labelprint under vare_valg.
// 20150417 CA  Topmenudesign tilføjet for Prisliste               søg 20150417
// 20150522 CA  Oprydning i HTML-kode især input - omfattende så ingen søgning
// 20150529 CA  Håndtering af forskellige typer prislister         søg 20150529
// 20150608 PHR Tilføjet link til ../api/hent_varer.php            søg 20150608
// 20150612 CA  Slette prislister                                  søg 20150612
// 20150625 CA  Tilpasning til topmenu
// 20150814 CA  Link til opsætning af prisliste                    søg 20150815
// 20150907 PHR Sætpriser tilføjet under ordre_valg, Søg $saetvareid
// 20151002	PHR	Fjernet mulighed for at trække en brugerliste.
// 20151005	PHR	Labelprint fungerer kun hvis variablen labelprint er sat. (Midlertidig løsning)
// 20151006 PHR Labelprint ændret fra php til html.
// 20160116 PHR Ændret 'bilag' så inputfelter kun vises ved 'egen ftp'
// 20160226 CA  Tilføjet valg af leverandører under prislister.    søg 20160226
// 20160412 PHR Opdelt vare_valg i vare_valg, labels & shop_valg
// 20160601	PHR SMTP kan nu anvendes med brugernavn, adgangskode og kryptering.
// 20161118	PHR	Tilføjet default bord som option for kasse i funktion pos_valg. Søg bordvalg
// 20161125 PHR Indført html som formulargenerator som alternativ til postscript i funktion div_. Søg pv_box3
// 20170123 PHR Tilføjet API_valg
// 20170314 PHR POS Valg - tilføjet mulighed for at sætte 'udtages fra kasse' til 0 som default.
// 20170329 PHR ordre_valg - tilføjet gennemsnitspris til opdat_kostpris
// 20170404 PHR ordre_valg - Straksbogfør skelner nu mellem debitor og kreditorordrer. Dvs debitor;kreditor - Søg # 20170404
// 20170731 PHR Tilføjet 'Nulstil regnskab under kontoindstillinger - 20170731
// 20181029 CA  Tilføjet gavekort og tilgodehavende tilknyttet id  søg 20181029
// 20181126 PHR	Tilvalg - Marker vare som udgået når beholdning går i minus (vare_valg). Søg DisItemIfNeg
// 20181129 PHR	Tilføjet mulighed for at sætte tidszone i regnskabet. Søg DisItemIfNeg
// 20181216 PHR	Tilføjet 'card_enabled' på betalingskort (Pos_valg) og mulighed for ændring af rækkefølge. Søg '$card_enabled'
// 20190107 PHR	Tilføjet 'change_cardvalue' på betalingskort (Pos_valg) og mulighed for ændring af rækkefølge. Søg '$change_cardvalue'
// 20190129 PHR	(vare_valg) Changed 'Momskode for salgspriser på varekort' to 'Vis priser med moms på varekort'. Search '$vatOnItemCard'
// 2019.02.25 MSC - Rettet topmenu design til
// 2019.04.11 LN Set new field, which sets the default value for provision
// 20190421 PHR - Added confirmDiscriptionChange, in 'vare_valg'
// 20190614 LN Added argument to chooseProvisionForProductGroup -> $defaultProvision
// 20200316 PHR Fuinction sqlquery_io. Fixed save & delete sql query

	include("sys_div_func_includes/chooseProvision.php");

ini_set('display_errors','0');

function kontoindstillinger($regnskab,$skiftnavn) {
	global $bgcolor,$bgcolor5,$timezone;
#	if (isset($_COOKIE['timezone'])) $timezone=$_COOKIE['timezone'];
#	else {
	$r=db_fetch_array(db_select("select id,var_value from settings where var_name='timezone'",__FILE__ . " linje " . __LINE__));
	if ($r['var_value']) {
		$timezone=$r['var_value'];
		if ($timezone) {
			date_default_timezone_set($timezone);
			setcookie("timezone",$timezone,time()+60*60*24*30,'/');
		}
	}
	print "<tr><td colspan='6'><hr></td></tr>\n";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Kontoindstillinger</u></b></td></tr>\n";
	print "<tr><td colspan='6'><br></td></tr>\n";
	if (!$skiftnavn) {
		print "<tr><td colspan='6'>Dit regnskab hedder <span style='font-weight:bold'>$regnskab</span>. ";
		print "Klik <a href='diverse.php?sektion=kontoindstillinger&amp;skiftnavn=ja'>her</a> for at &aelig;ndre navnet.</td></tr>\n";
		print "<tr><td colspan='6'><hr></td></tr>\n";
		$tmp=date('U')-60*60*24*365;
		$tmp=date("Y-m-d",$tmp);
		$r=db_fetch_array(db_select("select count(id) as transantal from transaktioner where transdate>='$tmp'",__FILE__ . " linje " . __LINE__));
		$transantal=$r['transantal']*1;
		print "<tr><td>Der er foretaget $transantal posteringer de sidste 12 mdr.</td></tr>";
		$r=db_fetch_array(db_select("select felt_1,felt_2,felt_3,felt_4 from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
		print "<tr><td colspan='6'><hr></td></tr>\n";
		print "<form name='timezone' action='diverse.php?sektion=kontoindstillinger' method='post'>\n";
		$title="Vælg den tidszone der skal gælde for dette regnskab";
		$text="Tidszone";
		print "<tr><td title='$title'><!--tekst 434-->$text<!--tekst 435--></td>";
		print "<td title='$title'><select class='inputbox' style='width:200px' name='timezone'>";
		$tz=fopen("../importfiler/timezones.csv","r");
		$x=0;
		while($line=trim(fgets($tz))) {
			list($a,$b[$x],$c[$x])=explode(",",$line);
			$b[$x]=trim($b[$x],'"');$c[$x]=trim($c[$x],'"');
			$x++;
		}
		for ($x=0;$x<count($c);$x++) {
			if ($timezone==$c[$x]) print "<option value='$c[$x]'>$b[$x] $c[$x]</option>";
		}
		for ($x=0;$x<count($c);$x++) {
			if ($timezone!=$c[$x]) print "<option value='$c[$x]'>$b[$x] $c[$x]</option>";
		}
		print "</select></td></tr>";
		$text="Opdater tidszone";
		print "<td></td><td><input class='button gray medium' style='width:200px' type='submit' value='$text' name='opdat_tidszone'><!--tekst 436--></td></tr>\n";
		print "</form>";
		print "<tr><td colspan='6'><hr></td></tr>\n";
		print "<form name=diverse action='diverse.php?sektion=smtp' method='post'>\n";
		$tekst1=findtekst(434,$sprog_id);
		$tekst2=findtekst(435,$sprog_id);
		print "<tr><td title='$tekst1'><!--tekst 434-->$tekst2<!--tekst 435--></td>";
		print "<td title='$tekst1'><input class='inputbox' type='text' style='width:200px' name='smtp' value='$r[felt_1]'></td></tr>";
		$tekst1=findtekst(749,$sprog_id);
		$tekst2=findtekst(746,$sprog_id);
		print "<tr><td title='$tekst1'><!--tekst 749-->$tekst2<!--tekst 746--></td>";
		print "<td title='$tekst1'><input class='inputbox' type='text' style='width:200px' name='smtpuser' value='$r[felt_2]'></td></tr>";
		$tekst1=findtekst(750,$sprog_id);
		$tekst2=findtekst(747,$sprog_id);
		print "<tr><td title='$tekst1'><!--tekst 750-->$tekst2<!--tekst 747--></td>";
		print "<td title='$tekst1'><input class='inputbox' type='text' style='width:200px' name='smtppass' value='$r[felt_3]'></td></tr>";
		$tekst1=findtekst(751,$sprog_id);
		$tekst2=findtekst(748,$sprog_id);
		print "<tr><td title='$tekst1'><!--tekst 751-->$tekst2<!--tekst 748--></td>";
		print "<td title='$tekst1'><select class='inputbox' style='width:200px' name='smtpcrypt'>";
		print "<option value='$r[felt_4]'>$r[felt_4]</option>";
		if ($r['felt_4']) print "<option value=''></option>";
		if ($r['felt_4']!='ssl') print "<option value='ssl'>ssl</option>";
		if ($r['felt_4']!='tls') print "<option value='tls'>tls</option>";
		print "</select></td></tr>";
		$tekst1=findtekst(436,$sprog_id);
		print "<td></td><td><input class='button gray medium' style='width:200px' type='submit' value='$tekst1' name='submit'><!--tekst 436--></td></tr>\n";
		print "</form>\n";
		print "<tr><td colspan='6'><hr></td></tr>\n";
		print "<tr><td colspan='6'><br></td></tr>\n";
		print "<form name='nulstil_regnskab' action='diverse.php?sektion=kontoindstillinger' method='post'>\n"; #20170731 ->
		$tekst1=findtekst(756,$sprog_id);
		$tekst2=findtekst(757,$sprog_id);
		print "<tr><td title='$tekst2'><b>$tekst1</b></td></tr>";
		$tekst1=findtekst(758,$sprog_id);
		$tekst2=findtekst(759,$sprog_id);
		print "<tr><td title='$tekst2'>$tekst1</td><td title='$tekst2'><input type='checkbox' name='behold_debkred'></td></tr>";
		$tekst1=findtekst(760,$sprog_id);
		$tekst2=findtekst(761,$sprog_id);
		print "<tr><tr><td title='$tekst2'>$tekst1</td><td title='$tekst2'><input type='checkbox' name='behold_varer'></td></tr>";
		$tekst1=findtekst(762,$sprog_id);
		print "<tr><td></td><td><input class='button gray medium' style='width:200px' type='submit' name='nulstil' value='Nulstil' onclick=\"return confirm('$tekst1')\"></td></tr>";
		print "</form>\n"; # <- 20170731
		print "<tr><td colspan='6'><hr></td></tr>\n";
		print "<tr><td colspan='6'><br></td></tr>\n";
		print "<form name='slet_regnskab' action='diverse.php?sektion=kontoindstillinger' method='post'>\n"; #20170731 ->
		$tekst1=findtekst(852,$sprog_id);
		$tekst2=findtekst(853,$sprog_id);
		print "<tr><td title='$tekst2'><b>$tekst1: $regnskab</b></td><td title='$tekst2'><input type='checkbox' name='slet_regnskab'></td></tr>";
		$tekst1=findtekst(851,$sprog_id);
		print "<tr><td></td><td><input class='button gray medium' title='$tekst2' style='width:200px' type='submit' name='slet' value='Slet' onclick=\"return confirm('$tekst1')\"></td></tr>";
		print "</form>\n"; # <- 20170731
	} else  {
		print "<form name='diverse' action='diverse.php?sektion=kontoindstillinger' method='post'>\n";
		print "<tr><td colspan='6'>Skriv nyt navn p&aring; regnskab <input class='inputbox' type='text' style='width:400px' name='nyt_navn' value='$regnskab'> ";
		print "og klik <input class='button gray medium' style='width:75px' type='submit' value='Skift&nbsp;navn' name='submit'></td></tr>\n";
		print "</form>\n";
	}


	print "<tr><td colspan='6'><br></td></tr>\n";
} # endfunc kontoindstillinger

function provision() {
	global $bgcolor;
	global $bgcolor5;

	$bet=NULL; $ref=NULL; $kua=NULL; $smart=NULL;
	$kort=NULL; $batch=NULL;

	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '1'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse'];
	$kodenr=$r['kodenr'];
	$box1=$r['box1'];
	$box2=$r['box2'];
	$box3=$r['box3'];
	$box4=$r['box4'];

	if ($box1=='ref') $ref="checked";
	elseif ($box1=='kua') $kua="checked";
	else $smart="checked";

	if ($box2=='kort') $kort="checked";
	else $batch="checked";

	if ($box4=='bet') $bet="checked";
	else $fak="checked";

	print "<form name='diverse' action='diverse.php?sektion=provision' method='post'>\n";
	print "<tr><td colspan='6'><hr></td></tr>\n";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Grundlag for provisionsberegning</u></b></td></tr>\n";
	print "<tr><td colspan='6'><br></td></tr>\n";
	print "<input type='hidden' name='id' value='$id'>\n";
	print "<tr>\n<td>Beregn provision p&aring; ordrer som er faktureret eller faktureret og betalt</td>\n<td></td>\n<td align='center'>Faktureret</td>\n<td align='center'>Betalt</td></tr>\n";
	print "<tr>\n<td></td>\n<td></td>\n<td align='center'><input class='inputbox' type='radio' name='box4' value='fak' title='Provision beregnes p&aring; fakturerede ordrer' $fak></td>\n";
	print "<td align='center'><input class='inputbox' type=radio name='box4' value='bet' title='Provision beregnes p&aring; betalte ordrer' $bet></td>\n</tr>\n";
	print "<tr>\n<td>Kilde for personinfo</td>\n<td align='center'>Ref.</td>\n<td align='center'>Kundeans.</td>\n<td align='center'>Begge</td>\n</tr>\n";
	print "<tr>\n<td></td>\n";
	print "<td align='center'><input class='inputbox' type='radio' name='box1' value='ref' \n";
	print "    title='Provision tilfalder den der er angivet som referenceperson p&aring; de enkelte ordrer' $ref></td>\n";
	print "<td align='center'><input class='inputbox' type='radio' name='box1' value='kua' \n";
	print "    title='Provision tilfalder den kundeansvarlige' $kua></td>\n";
	print "<td align='center'><input class='inputbox' type='radio' name='box1' value='smart' \n";
	print "    title='Provision tilfalder den kundeansvarlige s&aring;fremt der er tildelt en s&aring;dan, ellers til den som er referenceperson p&aring; de enkelte ordrer' $smart></td>\n";
	print "</tr>\n";
	print "<tr><td>Kilde for kostpris</td><td></td><td align='center'>Indk&oslash;bspris</td><td align='center'>Varekort</td></tr>\n";
	print "<tr>\n<td></td>\n<td></td>\n";
	print "<td align=center><input class='inputbox' type='radio' name='box2' value='batch' \n";
	print "    title='Anvend varens reelle indk&oslash;bspris som kostpris.' $batch></td>\n";
	print "<td align='center'><input class='inputbox' type='radio' name='box2' value='kort' title='Anvend kostpris fra varekort.' $kort></td>\n</tr>\n";
	print "<tr>\n<td>Sk&aelig;ringsdato for provisionsberegning</td><td></td><td></td>\n";
	print "<td align=center><select class='inputbox' name='box3' \n";
	print "    title='Dato hvorfra og med (i foreg&aring;ende m&aring;ned) til (dato i indev&aelig;rende m&aring;ned)provisionsberegning foretages'>";
	if ($box3) print "<option>$box3</option>\n";
	for ($x=1; $x<=28; $x++) {
		print "<option>$x</option>\n";
	}
	print "</select></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td><br></td><td><br></td><td><br></td><td align='center'><input class='button green medium' type='submit' accesskey='g' value='Gem/opdat&eacute;r' name='submit'></td></tr>\n";
	print "</form>\n";
} # endfunc provision  # HTML renset hertil 20150522

function kontoplan_io() {
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	$q = db_select("select * from grupper where art = 'RA' order by  kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$kodenr[$x]=$r['kodenr'];
	}
	$antal_regnskabsaar=$x;
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Indl&aelig;s/udl&aelig;s kontoplan</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	if ($popup) {
		print "<form name=diverse action=diverse.php?sektion=kontoplan_io method=post>";
		print "<tr><td colspan='2'>Eksport&eacute;r kontoplan</td>\n";
		print "<td align=center><SELECT class='inputbox' NAME=regnskabsaar title='V&aelig;lg det regnskabs&aring;r hvor kontoplanen skal eksporteres fra'>";
#		if ($box3[$x]) print"\t<option>$box3[$x]</option>";
		for ($x=1; $x<=$antal_regnskabsaar; $x++) {
			print "\t<option>$kodenr[$x] : $beskrivelse[$x]</option>";
		}
		print "</select></td>";
		print "<td align = center><input type=submit style='width: 8em' accesskey='e' value='Eksport&eacute;r' name='submit'></td><tr>";
		print "<tr><td colspan='3'>Import&eacute;r kontoplan (erstatter kontoplanen for nyeste regnskabs&aring;r) </td>";
		print "<td align = center><input type=submit style='width: 8em' accesskey='i' value='Import&eacute;r' name='submit'></td><tr>";
		print "</form>";
	} else {
		print "<tr><td colspan='3'>Eksport&eacute;r kontoplan</td><td align=center title='V&aelig;lg det regnskabs&aring;r hvor kontoplanen skal eksporteres fra'>";
#		if ($box3[$x]) {
#			print "<form form name=exporter$kodenr[$x] action='exporter_kontoplan.php?aar=$box3[$x]' method='post'>\n";
#			print"<input type='submit' style='width: 8em' value='$box3[$x]'><br>\n";
#			print "</form>\n";
#		}
		for ($x=1; $x<=$antal_regnskabsaar; $x++) {
			print "";
			print "<form name=exporter$kodenr[$x] action=exporter_kontoplan.php?aar=$kodenr[$x] method=post><input class='button gray medium' type='submit' style='width: 8em' value='$beskrivelse[$x]'></form>\n";
		}	print "";
		print "</td></tr>\n\n";
		print "<tr><td colspan='3'>Import&eacute;r kontoplan (erstatter kontoplanen for nyeste regnskabs&aring;r) </td>";
		print "<td align = center><form action='importer_kontoplan.php'><input class='button blue medium' type='submit' style='width: 8em' value='Import&eacute;r' accesskey='i'></form></td><tr>";
#		print "<td align = center><a href='importer_kontoplan.php' style='text-decoration:none' accesskey='i'>Import&eacute;r</a></td><tr>";
	}
#	print "</tbody></table></td></tr>";

} # endfunc kontoplan_io

function kreditor_io() {
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Indl&aelig;s/udl&aelig;s kreditorer</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>Eksport&eacute;r kreditorer</td>";
	if ($popup)	print "<form name=diverse action=diverse.php?sektion=kreditor_io method=post>";
	else print "<form name=diverse action=exporter_kreditor.php method=post>";
	print "<td align = center><input class='button gray medium' type=submit style='width: 8em' value='Eksport&eacute;r' name='submit'></td><tr>\n\n";
	print "<tr><td colspan='3'>Import&eacute;r kreditorer </td>\n";
	print "</form>";
	if ($popup)	print "<form name=diverse action=diverse.php?sektion=kreditor_io method=post>";
	else print "<form name=diverse action=importer_kreditor.php method=post>";
	print "<td align = center><input class='button blue medium' type=submit style='width: 8em' value='Import&eacute;r' name='submit'></td><tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";

} # endfunc kreditor_io
function formular_io() {
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Indl&aelig;s formularer</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>Eksport&eacute;r formularer</td>";
	if ($popup)	print "<form name=diverse action=diverse.php?sektion=formular_io method=post>";
	else print "<form name=diverse action=exporter_formular.php method=post>";
	print "<td align = center><input class='button gray medium' type=submit style='width: 8em' value='Eksport&eacute;r' name='submit'></td><tr>\n\n";
	print "</form>";
	print "<tr><td><br></td></tr>";
	print "<tr><td colspan='3'>Import&eacute;r formularer</td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=formular_io method=post>";
	else print "<form name=diverse action=importer_formular.php method=post>";
	print "<td align = center><input class='button blue medium' type='submit' style='width: 8em' value='Import&eacute;r'></td></tr>\n\n";
	print "</form>";
} # endfunc formular_io

function varer_io() {
	global $bgcolor;
	global $bgcolor5;

	$x=0;
#	print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Indl&aelig;s varer</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>Eksport&eacute;r varer</td>";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	else print "<td align = center><a href='exporter_varer.php' style='text-decoration:none'><input class='button gray medium' type='button' style='width: 8em'  value='Eksport&eacute;r'></a></td></tr>\n\n";
	print "<tr><td colspan='3'>Import&eacute;r Varer</td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	else print "<form name=diverse action=importer_varer.php method=post>";
	print "<td align = center><input class='button blue medium' type='submit' style='width: 8em' value='Import&eacute;r'></td></tr>\n\n";
	print "</form>";
	$r=db_fetch_array(db_select("select count(id) lagerantal from grupper where art='LG'",__FILE__ . " linje " . __LINE__));
	if ($r['lagerantal']) {
		print "<tr><td colspan='3'>Import&eacute;r varelokationer</td>\n";
		print "<form name='diverse' action='importer_varelokationer.php' method='post'>";
		print "<td align = center><input class='button blue medium' type='submit' style='width: 8em' value='Import&eacute;r'></td></tr>\n\n";
		print "</form>";
	}
/*
	print "<tr><td colspan='3'>Import&eacute;r VVSpris fil fra Solar </td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=solar_io method=post>";
	else print "<form name=diverse action=solarvvs.php?sektion=solar_io method=post>";
	print "<td align = center><input type=submit style='width: 8em' value='Import&eacute;r' name='submit'></td><tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";
*/
} # endfunc varer_io
function variantvarer_io() {
	global $bgcolor;
	global $bgcolor5;

	$x=0;
#	print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Indl&aelig;s variantvarer</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>Eksport&eacute;r variantvarer</td>";
	if ($popup) print "<td align = center><input class='button gray medium' type=submit accesskey='e' value='Eksport&eacute;r' name='submit'></td><tr>\n\n";
	else print "<td align = center><a href='exporter_variantvarer.php' style='text-decoration:none'><input class='button gray medium' type='button' style='width: 8em'  value='Eksport&eacute;r'></a></td></tr>\n\n";
	print "<tr><td colspan='3'>Import&eacute;r variantvarer</td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=variantvarer_io method=post>";
	else print "<form name=diverse action=importer_variantvarer.php method=post>";
	print "<td align = center><input class='button blue medium' type='submit' style='width: 8em' value='Import&eacute;r'></td></tr>\n\n";
	print "</form>";
/*
	print "<tr><td colspan='3'>Import&eacute;r VVSpris fil fra Solar </td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=solar_io method=post>";
	else print "<form name=diverse action=solarvvs.php?sektion=solar_io method=post>";
	print "<td align = center><input type=submit style='width: 8em' value='Import&eacute;r' name='submit'></td><tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";
*/
} # endfunc variantvarer_io
function adresser_io() {
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Indl&aelig;s/udl&aelig;s debitorer/kreditorer</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>Eksport&eacute;r debitorer/kreditorer</td>";
	if ($popup) {
		print "<form name=diverse action=diverse.php?sektion=adresser_io method=post>";
		print "<td align = center><input type=submit accesskey='e' style='width: 8em' value='Eksport&eacute;r' name='submit'></td><tr>";
		print "<tr><td colspan='3'>Import&eacute;r debitorer/kreditorer</td>";
		print "<td align = center><input type=submit accesskey='i' style='width: 8em' value='Import&eacute;r' name='submit'></td><tr>";
		print "</form>";
	} else {
		print "<td align = center><form name=impdeb action='exporter_adresser.php'><input class='button gray medium' type='submit' style='width: 8em' value='Eksport&eacute;r'></form></td></tr>\n\n";
		print "<tr><td colspan='3'>Import&eacute;r debitorer/kreditorer</td>";
		print "<td align = center><form name=expdeb action='importer_adresser.php'><input class='button blue medium' type='submit' style='width: 8em' value='Import&eacute;r'></form></td></tr>\n\n";
	}
#	print "</tbody></table></td></tr>";

} # endfunc adresser_io

function sqlquery_io($sqlstreng) {
	global $bgcolor;
	global $bgcolor5;

$sqlQueryId = if_isset($_POST['sqlQueryId']);
$deleteQuery= if_isset($_POST['deleteQuery']);
if ($sqlQueryId) {
	if ($deleteQuery) {
		db_modify("delete from queries where id = '$sqlQueryId'",__FILE__ . " linje " . __LINE__);
	} else {
		$qtxt="select query from queries where id = '$sqlQueryId'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$sqlstreng=$r['query'];
	}
}	
$titletxt="Skriv en SQL forespørgsel uden 'select'. F.eks: * from varer eller: varenr,salgspris from varer where lukket != 'on'";
print "<form name=exportselect action=diverse.php?sektion=sqlquery_io method=post>";
print "<tr><td colspan='6'><hr></td></tr>";
print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Dataudtr&aelig;k</u></b></td></tr>";
print "<tr><td colspan='6'><br></td></tr>";
print "<input type=hidden name=id value='$id'>";
print "<tr><td valign='top' title='$titletxt'>SELECT</td><td colspan='2'><textarea name='sqlstreng' rows='5' cols='80'>$sqlstreng</textarea></td>";
print "<td align = center><input class='button blue medium' style='width: 8em' type=submit accesskey='s' value='Send' name='send'><br>";
print "<br><input class='button green medium' style='width: 8em' type=submit accesskey='g' value='Gem' name='gem'></td>";
print "</form>";
$gem=$sqlstreng=NULL;
if (isset($_POST['sqlstreng'])) {
	$sqlstreng=trim($_POST['sqlstreng']);
	$gem=$_POST['gem'];
}
if ($sqlstreng=trim($sqlstreng)) {
	global $db;
	global $bruger_id;

	$linje=NULL;
	$filnavn="../temp/$db/$bruger_id.csv";
	$fp=fopen($filnavn,"w");
#	$sqlstreng=strtolower($sqlstreng);
	list($del1,$del2)=explode("where",$sqlstreng,2);
	$fy_ord=array('brugere','grupper');
	for ($x=0;$x<count($fy_ord);$x++) {
		if (strpos($del1,$fy_ord[$x])) {
			print "<BODY onLoad=\"JavaScript:alert('Illegal værdi i søgestreng')\">";
			exit;
		}
	}
#cho "del 1 $del1<br>";
#cho "del2 $del2<br>";

	for($x=0;$x<strlen($del2);$x++){
		$t=substr($del2,$x,1);
		if (!$tilde) {
			if ($t=="'") {
				$tilde=1;
				$var='';
			} else $streng.=$t;
		}	else {
			if ($t=="'") {
				$tilde=0;
				$streng.="'".db_escape_string($var)."'";
			}
		}


	}
#cho "$sqlstreng<br>";
	$qtxt="select ".db_escape_string($del1);
#cho "$qtxt<br>";
	$qtxt="select ".$sqlstreng;
	#cho "$qtxt<br>";

	$r=0;
	$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__ . " funktion sqlquery_io");
	while ($r < db_num_fields($q)) {
		$fieldName[$r] = db_field_name($q,$r);
		$fieldType[$r] = db_field_type($q,$r);
		($linje)?$linje.='";"'.$fieldName[$r]."(".$fieldType[$r].")":$linje='"'.$fieldName[$r]."(".$fieldType[$r].")";
		$r++;
	}
	($linje)?$linje.='"':$linje=NULL;
	if ($fp) {
		fwrite ($fp, "$linje\n");
	}
	$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__ . " funktion sqlquery_io");
	while($r=db_fetch_array($q)) {
		$linje=NULL;
		$arraysize=count($r);
		for ($x=0;$x<$arraysize;$x++) {
		if (isset($fieldType[$x]) && $fieldType[$x]=='numeric') $r[$x]=dkdecimal($r[$x]);
			elseif(isset($r[$x])) $r[$x]=utf8_decode($r[$x]);
			($linje)?$linje.='";"'.$r[$x]:$linje='"'.$r[$x];
		}
		($linje)?$linje.='"':$linje=NULL;
		if ($fp) {
			fwrite ($fp, "$linje\n");
		}
	}
	fclose($fp);
	print "<tr><td></td><td align='left' colspan='3'> H&oslash;jreklik her: <a href='$filnavn'>Datafil</a> og v&aelig;lg 'gem destination som'</td></tr>";
	if ($gem) {
		$qtxt=NULL;
		if ($sql_id) $qtxt="update queries set query = '". db_escape_string($sqlstreng) ."' where id = '$sql_id'";
		else {
			$qtxt="select id from queries where query = '". db_escape_string($sqlstreng) ."'";
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $qxtx=NULL;
			else $qtxt="insert into queries (query,query_descrpition,user_id) values ('". db_escape_string($sqlstreng) ."','','0')";
		}
		if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
print "<form name='query' action='diverse.php?sektion=div_io' method='post'>";
print "<tr><td></td><td colspan='4'><select name='sqlQueryId' style='width:600px;'>";
$qtxt="select * from queries order by query";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	print "<option value='$r[id]'>$r[query]</option>";
}
print "</select>&nbsp;<input type='submit' name='query' value='Hent'>&nbsp;";
print "<input type='submit' name='deleteQuery' value='Slet' onclick=\"return confirm('Slet denne søgning?')\"></td></tr>";
print "</form>";

} # endfunc sqlquery_io

function sprog () {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	$q = db_select("select * from grupper where art = 'SPROG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$kodenr[$x]=$r['kodenr'];
		$sprogkode[$x]=$r['box1'];
		$x++;
	}
	$antal_sprog=$x;
	print "<form name=diverse action=diverse.php?sektion=sprog method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Sprog</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";

	if (isset($_POST['newLanguageName']) && $_POST['newLanguageName'])  {

		if (in_array($_POST['newLanguageName'],$beskrivelse)) {
			alert ($_POST['newLanguageName']." eksisterer allerede");
		} else {
			echo "opretter ". $_POST['newLanguageName']."<br>";
		}
	}
	if (isset($_POST['sprog']) && $_POST['sprog']=='addLanguage') {
		print "<tr><td title='Hvilket sprog vil du tilføje'>Nyt sprog</td>";
		print "<td title='Hvilket sprog vil du tilføje'><input name='newLanguageName' type='text' class='inputbox' style='width:200px'></td></tr>";
		print "<tr><td title='Hvilket sprog vil du anvende som skabelon'>Skabelon</td>";
		print "<td><SELECT class='inputbox' NAME='languageTemplate' title=''>";
		if ($box3[$x]) print"<option>$box3[$x]</option>";
		for ($x=0; $x<$antal_sprog; $x++) {
			print "<option>$beskrivelse[$x]</option>";
		}
		print "</SELECT></td></tr>";
	} else {
		$tekst1=findtekst(1,$sprog_id);
		$tekst2=findtekst(2,$sprog_id);
		print "<tr><td title='Klik her for at rette tekster'><a href=tekster.php?sprog_id=1>$tekst1</a></td>";
		print "<td><SELECT class='inputbox' NAME='sprog' title='$tekst2'>";
		if ($box3[$x]) print"<option>$box3[$x]</option>";
		for ($x=0; $x<$antal_sprog; $x++) {
			print "<option>$beskrivelse[$x]</option>";
		}
		$tekst1=findtekst(4,$sprog_id);
		print "<option value='addLanguage'>$tekst1</option>";
		print "</SELECT></td></tr>";
	}
	print "<tr><td><br></td></tr>";
	$tekst1=findtekst(3,$sprog_id);
	print "<tr><td align = right colspan='4'><input class='button green medium' type=submit value='$tekst1' name='submit'></td></tr>";
#	print "<td align = center><input type=submit value='$tekst2' name='submit'></td>";
#	print "<td align = center><input type=submit value='$tekst3' name='submit'></td><tr>";
/*
	print "</tbody></table></td></tr>";
*/
	print "</form>";
} # endfunc sprog

function jobkort () {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	$q = db_select("select * from grupper where art = 'JOBKORT' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$kodenr[$x]=$r['kodenr'];
		$sprogkode[$x]=$r['box1'];
	}
	$antal_sprog=$x;
	print "<form name=diverse action=diverse.php?sektion=sprog method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Sprog</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	$tekst1=findtekst(1,$sprog_id);
	$tekst2=findtekst(2,$sprog_id);
	print "<tr><td>	$tekst1</td><td><SELECT class='inputbox' NAME=sprog title='$tekst2'>";
	if ($box3[$x]) print"<option>$box3[$x]</option>";
	for ($x=1; $x<=$antal_sprog; $x++) {
		print "<option>$beskrivelse[$x]</option>";
	}
	print "</SELECT></td></tr>";
	print "<tr><td><br></td></tr>";
	$tekst1=findtekst(3,$sprog_id);
	print "<tr><td align = right colspan='4'><input type=submit value='$tekst1' name='submit'></td></tr>";
#	print "<td align = center><input type=submit value='$tekst2' name='submit'></td>";
#	print "<td align = center><input type=submit value='$tekst3' name='submit'></td><tr>";
/*
	print "</tbody></table></td></tr>";
*/
	print "</form>";

} # endfunc sprog


function personlige_valg() {
	global $sprog_id;
	global $popup;
	global $bruger_id;
	global $bgcolor;
	global $bgcolor5;
	global $nuance;

	$gl_menu=NULL;$sidemenu=NULL;$topnemu=NULL;

	$r = db_fetch_array(db_select("select * from grupper where art = 'USET' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$jsvars=$r['box1'];
	($r['box2'])?$popup='checked':$popup=NULL;
	if ($r['box3'] == 'S') $sidemenu='checked';
	elseif ($r['box3'] == 'T') $topmenu='checked';
	else $gl_menu='checked';
	($r['box4'])?$bgcolor=$r['box4']:$bgcolor=NULL;
	($r['box5'])?$nuance=$r['box5']:$nuance=NULL;

	$nuancefarver[0]=findtekst(418,$sprog_id); $nuancekoder[0]="+00-22-22";
	$nuancefarver[1]=findtekst(419,$sprog_id); $nuancekoder[1]="-22+00-22";
	$nuancefarver[2]=findtekst(420,$sprog_id); $nuancekoder[2]="-22-22+00";
	$nuancefarver[3]=findtekst(421,$sprog_id); $nuancekoder[3]="+00+00-33";
	$nuancefarver[4]=findtekst(422,$sprog_id); $nuancekoder[4]="+00-33+00";
	$nuancefarver[5]=findtekst(423,$sprog_id); $nuancekoder[5]="-33+00+00";

	print "<form name=personlige_valg action=diverse.php?sektion=personlige_valg&popup=$popup method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Personlige valg</u></b></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
#	print "<input type=hidden name=id value='$id'>";

	print "<tr><td title='".findtekst(207,$sprog_id)."'>".findtekst(208,$sprog_id)."</td><td><input class='inputbox' type='checkbox' name='popup' $popup></td></tr>";
#	if (strpos($_SERVER['SERVER_NAME'],'dvikling') || strpos($_SERVER['SERVER_NAME'],'sl3')) {
	#	print "<tr><td title='".findtekst(316,$sprog_id)."'><!--Tekst 523-->".findtekst(315,$sprog_id)."<!--Tekst 315--></td><td><input class='inputbox' type='radio' name='menu' value='sidemenu' $sidemenu></td></tr>";
		print "<tr><td title='".findtekst(523,$sprog_id)."'><!--Tekst 523-->".findtekst(522,$sprog_id)."<!--Tekst 522--></td><td><input class='inputbox' type='radio' name='menu' value='topmenu' $topmenu></td></tr>";
#	}	else $gl_menu='checked';
	print "<tr><td title='".findtekst(525,$sprog_id)."'><!--Tekst 525-->".findtekst(524,$sprog_id)."<!--Tekst 524--></td><td><input class='inputbox' type='radio' name='menu'  value='gl_menu' $gl_menu></td></tr>";
	print "<tr><td title='".findtekst(209,$sprog_id)."'>".findtekst(210,$sprog_id)."</td><td colspan='4'><input class='inputbox' type='text' style='width:600px' name='jsvars' value='$jsvars'></td></tr>";
	print "<tr><td title='".findtekst(318,$sprog_id)."'>".findtekst(317,$sprog_id)."</td><td colspan='4'><input class='inputbox' type='text' style='width:100px' name='bgcolor' value='".substr($bgcolor,1,6)."'></td></tr>";
	print "<tr><td title='".findtekst(416,$sprog_id)."'>".findtekst(415,$sprog_id)."</td><td colspan='4'><select name='nuance' title='".findtekst(417,$sprog_id)."'>\n";
	if ( ! $nuance ) {
		$valgt = "selected='selected'";
	} else {
		$valgt="";
	}
	print "   <option $valgt value='' style='background:$bgcolor'>Intet</option>\n";
	$antal_nuancer=count($nuancefarver);
	for ($x=0; $x<=$antal_nuancer;$x++) {
		if ( $nuance === $nuancekoder[$x] ) {
			$valgt = "selected='selected'";
		} else {
			$valgt="";
		}
		print "   <option $valgt value='$nuancekoder[$x]' style='background:".farvenuance($bgcolor, $nuancekoder[$x])."'>$nuancefarver[$x]</option>\n";
	}
	print "</select></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td><br></td><td><br></td><td><br></td><td align = center><input class='button green medium' type=submit accesskey='g' value='Gem/opdat&eacute;r' name='submit'></td></tr>\n";
	print "</form>";
} # endfunc personlige_valg

function div_valg() {
	global $sprog_id;
	global $docubizz;
	global $bgcolor;
	global $bgcolor5;

	$batch=$gls_id=$gls_pass=$gls_user=$gruppevalg=$jobkort=$kort=$kuansvalg=$ref=$kua=$smart=NULL;

	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse']; $kodenr=$r['kodenr'];
	$box1=$r['box1']; $box2=$r['box2']; $box3=$r['box3']; $box4=$r['box4'];$box5=$r['box5']; #ledig
	$box6=$r['box6'];$box7=$r['box7'];$box8=$r['box8'];$box9=$r['box9'];$box10=$r['box10'];
	if ($box1=='on') $gruppevalg="checked"; if ($box2=='on') $kuansvalg="checked"; if ($box3=='on') $extra_ansat="checked";
	if ($box4=='on') $forskellige_datoer="checked";

	if ($box6=='on') $docubizz="checked";
	if ($box7=='on') $jobkort="checked";if ($box8) $ebconnect="checked";if ($box9=='on') $ledig="checked";
	if ($box10=='on') $betalingsliste="checked";

	$r=db_fetch_array(db_select("select box1,box3 from grupper where art = 'PV' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	($r['box1'])?$direkte_print='checked':$direkte_print=NULL;
	($r['box3'])?$formgen='checked':$formgen=NULL;

	$qtxt="select var_name,var_value from settings where var_grp='GLS'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if ($r['var_name']=='gls_id')   $gls_id   = $r['var_value'];
		if ($r['var_name']=='gls_user') $gls_user = $r['var_value'];
		if ($r['var_name']=='gls_pass') $gls_pass = $r['var_value'];
		if ($r['var_name']=='gls_ctId') $gls_ctId = $r['var_value'];
	}

	print "<form name='diverse' action='diverse.php?sektion=div_valg' method='post'>\n";
	print "<tr style='background-color:#$bgcolor5'><td colspan='6'><b>Diverse valg</b></td></tr>\n";
	print "<tr><td colspan='2'>&nbsp;</td></tr>\n";
	print "<input name='is' type='hidden' value='$id'>\n";
	print "<tr bgcolor='$bgcolor5'>\n<td title='".findtekst(186,$sprog_id)."'>".findtekst(162,$sprog_id)."</td>\n";
	print "<td title='".findtekst(186,$sprog_id)."'>\n";
	print "    <input name='box1' class='inputbox' type='checkbox' $gruppevalg>\n";
	print "</td></tr>\n";
#	print "<td title='".findtekst(211,$sprog_id)."'>".findtekst(212,$sprog_id)."</td><td><input name='box7' class='inputbox' type='checkbox' $jobkort></td></tr>";
	print "<tr>\n<td title='".findtekst(187,$sprog_id)."'>".findtekst(163,$sprog_id)."</td>\n";
	print "<td title='".findtekst(187,$sprog_id)."'>\n";
	print "    <input name='box2' class='inputbox' type='checkbox' $kuansvalg>\n";
	print "</td></tr>\n";
	print "<tr bgcolor='$bgcolor5'>\n<td title='".findtekst(615,$sprog_id)."'>".findtekst(616,$sprog_id)."</td>\n";
	print "<td title='".findtekst(615,$sprog_id)."'>\n";
	print "    <input name='box3' class='inputbox' type='checkbox' $extra_ansat>\n";
	print "</td></tr>\n";
	print "<tr>\n<td title='".findtekst(185,$sprog_id)."'>".findtekst(184,$sprog_id)."</td>\n";
	print "<td title='".findtekst(185,$sprog_id)."'>\n";
	print "    <input name='box10' class='inputbox' type='checkbox' $betalingsliste>\n";
	print "</td></tr>\n";
	print "<tr bgcolor='$bgcolor5'>\n<td title='".findtekst(193,$sprog_id)."'>".findtekst(167,$sprog_id)."</td>\n";
	print "<td title='".findtekst(193,$sprog_id)."'>\n";
	print "    <input name='box6' class='inputbox' type='checkbox' $docubizz>\n";
	print "</td></tr>\n";
	print "<tr>\n<td title='".findtekst(194,$sprog_id)."'>".findtekst(168,$sprog_id)."</td>\n";
	print "<td title='".findtekst(194,$sprog_id)."'>\n";
	print "    <input name='box7' class='inputbox' type='checkbox' $jobkort>\n";
	print "</td></tr>\n";
	$externalContent = file_get_contents('http://checkip.dyndns.com/');
	preg_match('/Current IP Address: \[?([:.0-9a-fA-F]+)\]?/', $externalContent, $m);
	$externalIp = $m[1];
	$txt=str_replace('$myip',$externalIp,findtekst(764,$sprog_id));
	print "<tr bgcolor='$bgcolor5'>\n<td title='$txt'>".findtekst(763,$sprog_id)."</td>\n";
	print "<td title='$txt'>\n";
	print "    <input name='pv_box1' class='inputbox' type='checkbox' $direkte_print>\n";
	print "</td></tr>\n";
		print "<tr>\n<td title='".findtekst(817,$sprog_id)."'>".findtekst(818,$sprog_id)."</td>\n";
	print "<td title='".findtekst(817,$sprog_id)."'>\n";
	print "    <input name='pv_box3' class='inputbox' type='checkbox' $formgen>\n";
	print "</td></tr>\n";
	print "<tr bgcolor='$bgcolor5'>\n<td title='".findtekst(709,$sprog_id)."'>".findtekst(708,$sprog_id)."</td>\n";
	print "<td title='".findtekst(709,$sprog_id)."'>\n";
	print "    <input name='box4' class='inputbox' type='checkbox' $forskellige_datoer></td></tr>\n"; #20131101
		if (strpos(findtekst(841,$sprog_id),'kortet er et betalingskort')) {
		db_modify("delete from tekster where (tekst_id='841' or tekst_id='642') and sprog_id='$sprog_id'");
  }
#	print "<tr>\n<td title='".findtekst(642,$sprog_id)."'>".findtekst(841,$sprog_id)."</td>\n";
#	print "<td title='".findtekst(642,$sprog_id)."'>\n";
#	print "    <input name='box5' class='inputbox' type='text' style='width:150px;' placeholder='' value=\"$box5\">\n";
#	print "</td></tr>\n"; #20131101
	print "<tr>\n<td title='".findtekst(527,$sprog_id)."'>".findtekst(526,$sprog_id)."</td>\n";
	print "<td title='".findtekst(527,$sprog_id)."'>\n";
	print "    <input name='box8' class='inputbox' type='checkbox' $ebconnect>\n";
	print "</td></tr>\n";
	if ($box8) {
		list($oiourl,$oiobruger,$oiokode)=explode(chr(9),$box8);
		print "<tr bgcolor='$bgcolor5'>\n<td title=''>".findtekst(528,$sprog_id)."</td>\n";
		print "<td><input name='oiourl' class='inputbox' style='width:150px;' type='text' value='$oiourl'></td>\n</tr>\n";
		print "<tr>\n<td title=''>".findtekst(529,$sprog_id)."</td>\n";
		print "<td><input name='oiobruger' class='inputbox' style='width:150px;' type='text' value='$oiobruger'></td>\n</tr>\n";
		print "<tr>\n<td title=''>".findtekst(530,$sprog_id)."</td>\n";
		print "<td><input name='oiokode' class='inputbox' style='width:150px;' type='password' value='$oiokode'></td>\n</tr>\n";
	}
	$txt=findtekst(865,$sprog_id);
	$title=findtekst(866,$sprog_id);
	print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>$txt</td>\n";
	print "<td title='$title'><input name='gls_id' class='inputbox' style='width:150px;' type='text' value='$gls_id'></td>\n</tr>\n";
	if ($gls_id) {
		$txt=findtekst(867,$sprog_id);
		$title=findtekst(868,$sprog_id);
		print "<tr>\n<td title='$title'>$txt</td>\n";
		print "<td title='$title'><input name='gls_user' class='inputbox' style='width:150px;' type='text' value='$gls_user'></td>\n</tr>\n";
		$txt=findtekst(873,$sprog_id);
		$title=findtekst(874,$sprog_id);
		print "<tr>\n<td title='$title'>$txt</td>\n";
		print "<td title='$title'><input name='gls_ctId' class='inputbox' style='width:150px;' type='text' value='$gls_ctId'></td>\n</tr>\n";
		$txt=findtekst(869,$sprog_id);
		$title=findtekst(870,$sprog_id);
		print "<tr>\n<td title='$title'>$txt</td>\n";
		print "<td title='$title'><input name='gls_pass' class='inputbox' style='width:150px;' type='password' value='$gls_pass'></td>\n</tr>\n";
	}

	print "<tr><td colspan='2'>&nbsp;</td></tr>";
	print "<tr><td colspan='1'>&nbsp;</td><td style='text-align:center'>\n";
	print "     <input class='button green medium' name='submit' type=submit accesskey='g' value='Gem/opdat&eacute;r'>\n";
	print "</td></tr>\n";
	print "</form>\n\n";
} # endfunc div_valg

function ordre_valg() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$hurtigfakt=$incl_moms=$folge_s_tekst=$negativt_lager=$straks_bogf=$vis_nul_lev=NULL;

	$r=db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse'];
	$kodenr=$r['kodenr'];
	($r['box1']=='on')?$incl_moms="checked":$incl_moms=NULL;
	$rabatvareid=$r['box2'];
	($r['box3']=='on')?$folge_s_tekst="checked":$folge_s_tekst=NULL;
	($r['box4']=='on')?$hurtigfakt="checked":$hurtigfakt=NULL;
	if (strstr($r['box5'],';')) {
		list($straks_deb,$straks_kred)=explode(';',$r['box5']); #20170404
	} else {
		$straks_deb=$r['box5'];$straks_kred=$r['box5'];
	}
	($straks_deb=='on')?$straks_deb='checked':$straks_deb=NULL;
	($straks_kred=='on')?$straks_kred='checked':$straks_kred=NULL;
	($r['box6']=='on')?$fifo="checked":$fifo==NULL;
	$kontantkonto=$r['box7'];
	($r['box8']=='on')?$vis_nul_lev="checked":$vis_nul_lev=NULL;
	($r['box9']=='on')?$negativt_lager="checked":$negativt_lager=NULL;
	$kortkonto=$r['box10'];
	($r['box11']=='on')?$advar_lav_beh="checked":$advar_lav_beh=NULL;
	($r['box12']=='on')?$procentfakt="checked":$procentfakt=NULL;
	list($procenttillag,$procentvare)=explode(chr(9),$r['box13']);
	($r['box14']=='on')?$samlet_pris="checked":$samlet_pris=NULL;

	if ($rabatvareid) {
		$r=db_fetch_array(db_select("select varenr from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__));
		$rabatvarenr=$r['varenr'];
	}
	#	print "<tr><td colspan='6'><br></td></tr>";
#	print "<tr><td title='".findtekst(732,$sprog_id)."'>".findtekst(731,$sprog_id)."</td><td title='".findtekst(732,$sprog_id)."'>
#		<input name='box6' type='checkbox' $box6></td></tr>";

	$r=db_fetch_array(db_select("select box6,box8 from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__));
	# OBS $box1,2,3,4,5,7,9 bruges under shop valg!!
	$kostmetode=$r['box6']*1; #0=opdater ikke kostpris,1=snitpris;2=sidste_købspris
	$kostbeskrivelse[0]="Opdater ikke kostpris";
	$kostbeskrivelse[1]="Gennemsnitspris";
	$kostbeskrivelse[2]="Genanskaffelsespris";
	$saetvareid=$r['box8'];
	if ($saetvareid) {
		$r=db_fetch_array(db_select("select varenr from varer where id = '$saetvareid'",__FILE__ . " linje " . __LINE__));
		$saetvarenr=$r['varenr'];
	}

	print "<form name=diverse action=diverse.php?sektion=ordre_valg method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Ordrerelaterede valg</u></b></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title='".findtekst(197,$sprog_id)."'>".findtekst(196,$sprog_id)."</td><td><INPUT title='".findtekst(197,$sprog_id)."' class='inputbox' type='checkbox' name=box1 $incl_moms></td></tr>";
	print "<tr><td title='".findtekst(188,$sprog_id)."'>".findtekst(164,$sprog_id)."</td><td><INPUT title='".findtekst(188,$sprog_id)."' class='inputbox' type='checkbox' name=box3 $folge_s_tekst></td></tr>";
	print "<tr><td title='".findtekst(189,$sprog_id)."'>".findtekst(169,$sprog_id)."</td><td><INPUT title='".findtekst(189,$sprog_id)."' class='inputbox' type='checkbox' name=box8 $vis_nul_lev></td></tr>";
	$r=db_fetch_array(db_select("select id from grupper where art = 'VG' and box9='on'",__FILE__ . " linje " . __LINE__));
	if ($r['id']) $hurtigfakt="onclick='return false'";
	print "<tr><td title='".findtekst(190,$sprog_id)."'>".findtekst(165,$sprog_id)."</td><td><INPUT title='".findtekst(190,$sprog_id)."' class='inputbox' type='checkbox' name='box4' $hurtigfakt></td></tr>";
	print "<tr><td title='".findtekst(191,$sprog_id)."'>".findtekst(166,$sprog_id)."</td><td><INPUT title='".findtekst(191,$sprog_id)."' class='inputbox' type='checkbox' name='straks_deb' $straks_deb></td></tr>";
	print "<tr><td title='".findtekst(214,$sprog_id)."'>".findtekst(213,$sprog_id)."</td><td><INPUT title='".findtekst(214,$sprog_id)."' class='inputbox' type='checkbox' name='straks_kred' $straks_kred></td></tr>";
	print "<tr><td title='".findtekst(313,$sprog_id)."'>".findtekst(314,$sprog_id)."</td><td><INPUT title='".findtekst(313,$sprog_id)."' class='inputbox' type='checkbox' name='box6' $fifo></td></tr>";
	print "<tr><td title='".findtekst(732,$sprog_id)."'>".findtekst(731,$sprog_id)."</td><td colspan='1'><SELECT title='".findtekst(732,$sprog_id)."'class='inputbox' name='kostmetode'>";
	for($i=0;$i<3;$i++) {
		if ($i==$kostmetode) print "<option value=$i>$kostbeskrivelse[$i]</option>";
	}
	for($i=0;$i<3;$i++) {
		if ($i!=$kostmetode) print "<option value=$i>$kostbeskrivelse[$i]</option>";
	}
	print "</SELECT></td></tr>";
	if ($kostmetode>=1) {
		print "<tr><td></td><td colspan='2'><a href='../includes/opdat_kostpriser.php?metode=$kostmetode' target='blank'><INPUT title='".findtekst(738,$sprog_id)."' type='button' value='".findtekst(739,$sprog_id)."'></a></td>";
	}
	print "</tr>";
	print "<tr><td title='".findtekst(192,$sprog_id)."'>".findtekst(183,$sprog_id)."</td><td><INPUT title='".findtekst(192,$sprog_id)."' class='inputbox' type='checkbox' name='box9' $negativt_lager></td></tr>";
	print "<tr><td title='".findtekst(743,$sprog_id)."'>".findtekst(742,$sprog_id)."</td><td><INPUT title='".findtekst(743,$sprog_id)."' class='inputbox' type='checkbox' name='box14' $samlet_pris></td></tr>";
	print "<tr><td title='".findtekst(680,$sprog_id)."'>".findtekst(714,$sprog_id)."</td><td><INPUT title='".findtekst(680,$sprog_id)."' class='inputbox' type='checkbox' name='box11' $advar_lav_beh></td></tr>";
	print "<tr><td title='".findtekst(682,$sprog_id)."'>".findtekst(681,$sprog_id)."</td><td><INPUT title='".findtekst(682,$sprog_id)."' class='inputbox' type='checkbox' name='box12' $procentfakt></td></tr>";
	print "<tr><td title='".findtekst(684,$sprog_id)."'>".findtekst(683,$sprog_id)."</td><td><INPUT title='".findtekst(684,$sprog_id)."' class='inputbox' type='text' style='width:35px;text-align:right;' name='procenttillag' value='$procenttillag'>%</td></tr>";
	print "<tr><td title='".findtekst(686,$sprog_id)."'>".findtekst(685,$sprog_id)."</td><td><INPUT title='".findtekst(686,$sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='procentvare' value='$procentvare'></td></tr>";
	print "<tr><td title='".findtekst(288,$sprog_id)."'>".findtekst(287,$sprog_id)."</td><td><INPUT title='".findtekst(288,$sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='box2' value='$rabatvarenr'></td></tr>";
	if ($samlet_pris) print "<tr><td title='".findtekst(745,$sprog_id)."'>".findtekst(744,$sprog_id)."</td><td><INPUT title='".findtekst(745,$sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='saetvarenr' value='$saetvarenr'></td></tr>";
	print "<tr><td title='".findtekst(688,$sprog_id)."'>".findtekst(687,$sprog_id)."</td><td><INPUT title='".findtekst(688,$sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='box7' value='$kontantkonto'></td></tr>";
	print "<tr><td title='".findtekst(690,$sprog_id)."'>".findtekst(689,$sprog_id)."</td><td><INPUT title='".findtekst(690,$sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='box10' value='$kortkonto'></td></tr>";


	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input class='button green medium' type=submit accesskey='g' value='Gem/opdat&eacute;r' name='submit'></td>";
	print "</form>";
} # endfunc ordre_valg

function vare_valg($defaultProvision) {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	global $db;
	global $labelprint;

	db_modify("update settings set var_grp = 'items' where var_grp='varer'",__FILE__ . " linje " . __LINE__);

	$qtxt="select id,var_value from settings where var_name = 'vatOnItemCard' and var_grp = 'items'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$vatOnItemCard_id=$r['id'];
	($r['var_value'])?$vatOnItemCard='checked':$vatOnItemCard=NULL;

	if (!$vatOnItemCard_id) { // remove this after rel 3.7.6
		$q = db_select("select * from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__);
		# OBS $box2,3,4,5,7,9 bruges under shop valg!!
		# OBS $box8 bruges under ordrelaterede valg!!
		$r = db_fetch_array($q);
		$id=$r['id'];$beskrivelse=$r['beskrivelse'];$kodenr=$r['kodenr'];$box1=trim($r['box1']);
		($box1)?$vatOnItemCard_id='checked':$vatOnItemCard_id=NULL;
	}
	$qtxt="select id,var_value from settings where var_name = 'DisItemIfNeg' and var_grp = 'items'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$DisItemIfNeg_id=$r['id'];
	($r['var_value'])?$DisItemIfNeg='checked':$DisItemIfNeg=NULL;

	$qtxt="select id,var_value from settings where var_name = 'confirmDiscriptionChange' and var_grp = 'items'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$confirmDiscriptionChange_id=$r['id'];
	($r['var_value'])?$confirmDiscriptionChange='checked':$confirmDiscriptionChange=NULL;

	print "<form name='vare_valg' action='diverse.php?sektion=vare_valg' method='post'>";
	print "<tr><td colspan='6'><hr></td></tr>";
	$text=findtekst(470,$sprog_id);
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>$text<!--tekst 470--></u></b></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<input type=hidden name='id' value='$id'>";
	print "<input type=hidden name='vatOnItemCard_id' value='$vatOnItemCard_id'>";
	print "<input type=hidden name='confirmDiscriptionChange_id' value='$confirmDiscriptionChange_id'>";
	print "<input type=hidden name='DisItemIfNeg_id' value='$DisItemIfNeg_id'>";

	/*
	$text=findtekst(468,$sprog_id);
	$title=findtekst(469,$sprog_id);
	print "<tr><td title='$title'>$text</td><td title='$title'><SELECT class='inputbox' name='box1'>";
	$r=db_fetch_array(db_select("select * from grupper where art = 'SM' and kodenr = '$box1'",__FILE__ . " linje " . __LINE__));
	if ($box1) $value="S".$box1.":".$r['beskrivelse'];
	print "<option value='$box1'>$value</option>";
	$q=db_select("select * from grupper where art = 'SM' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$value="S".$r['kodenr'].":".$r['beskrivelse'];
		print "<option value='$r[kodenr]'>$value</option>";
	}
	print "<option></option>";
	print "</select></td></tr>";
	*/

	$text="Vis priser med moms på varekort";
	$title="Når dette felt er afmærket bliver varen vist incl. moms på varekortet";
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='vatOnItemCard' $vatOnItemCard></td></tr>";
	print "<td><br></td><td><br></td><td><br></td>";

	$text="Bekræft ændring af beskrivelse på varekort";
	$title="Når dette felt er afmærket bliver skal ændring af beskrivelse på varekort bekræftes";
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='confirmDiscriptionChange' $confirmDiscriptionChange></td></tr>";
	print "<td><br></td><td><br></td><td><br></td>";

	$text="Sæt vare til udgået, når beholdning bliver negativ";
	$title="Når dette felt er afmærket bliver varen markeret som udgået når beholdningen bliver netativ";
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='DisItemIfNeg' $DisItemIfNeg></td></tr>";
	chooseProvisionForProductGroup($defaultProvision);
	print "<td><br></td><td><br></td><td><br></td>";
	$text=findtekst(471,$sprog_id);
	print "<td align = center><input class='button green medium' type=submit accesskey='g' value='$text' name='submit'><!--tekst 471--></td>";
	print "<tr><td><br></td></tr>";
	print "</form>";
}

# ---------------------- varianter ----------------------

function variant_valg() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	global $db;

	print "<form name='diverse' action='diverse.php?sektion=variant_valg' method='post'>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".str_replace("php","html",findtekst(472,$sprog_id))."<!--tekst 472--></u></b></td></tr>";
	if ($delete_var_type=if_isset($_GET['delete_var_type'])) db_modify("delete from variant_typer where id = '$delete_var_type'",__FILE__ . " linje " . __LINE__);
	if ($delete_variant=if_isset($_GET['delete_variant'])) {
		db_modify("delete from variant_typer where variant_id = '$delete_variant'",__FILE__ . " linje " . __LINE__);
		db_modify("delete from varianter where id = '$delete_variant'",__FILE__ . " linje " . __LINE__);
	}
	if ($rename_var_type=if_isset($_GET['rename_var_type'])) {
		$r=db_fetch_array(db_select("select beskrivelse from variant_typer where id=$rename_var_type",__FILE__ . " linje " . __LINE__));
		print "<input type='hidden' name='rename_var_type' value='$rename_var_type'>";
		print "<tr><td>".findtekst(473,$sprog_id)."<!--tekst 473--></td><td></td><td><input type='text' name='var_type_beskrivelse' value = '$r[beskrivelse]'</td></tr>";
	}elseif ($rename_variant=if_isset($_GET['rename_variant'])) {
		$r=db_fetch_array(db_select("select beskrivelse from varianter where id=$rename_variant",__FILE__ . " linje " . __LINE__));
		print "<input type='hidden' name='rename_varianter' value='$rename_variant'>";
		print "<tr><td>".findtekst(474,$sprog_id)."<!--tekst 474--></td><td></td><td><input type='text' name='variant_beskrivelse' value = '$r[beskrivelse]'</td></tr>";
	} else {
		$x=0;
		$q=db_select("select * from varianter order by beskrivelse",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$x++;
			$variant_id[$x]=$r['id'];
			$variant_beskrivelse[$x]=$r['beskrivelse'];
			$y=0;
			$q2=db_select("select * from variant_typer where variant_id=$variant_id[$x] order by beskrivelse",__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)) {
				$y++;
				$var_type_id[$x][$y]=$r2['id'];
				$var_type_beskrivelse[$x][$y]=$r2['beskrivelse'];
			}
			$var_type_antal[$x]=$y;
		}
		$variant_antal=$x;
		print "<tr><td></td><td><b>".findtekst(475,$sprog_id)."<!--tekst 475--></b></td><td><b>".findtekst(476,$sprog_id)."<!--tekst 476--></b></td></tr>";
		for ($x=1;$x<=$variant_antal;$x++){
			print "<tr><td></td><td>$variant_beskrivelse[$x]</td></td><td>";
			print "<td><span title='".findtekst(477,$sprog_id)."'><!--tekst 477--><a href='diverse.php?sektion=variant_valg&rename_variant=".$variant_id[$x]."' onclick=\"return confirm('".findtekst(483,$sprog_id)."')\"><img src=../ikoner/rename.png border=0></a></span>\n";
			print "<span title='".findtekst(478,$sprog_id)."'><!--tekst 478--><a href='diverse.php?sektion=variant_valg&delete_variant=".$variant_id[$x]."' onclick=\"return confirm('".findtekst(481,$sprog_id)."')\"><img src=../ikoner/delete.png border=0></a></span></td></tr>\n";
			for ($y=1;$y<=$var_type_antal[$x];$y++){
#				if ($y>1)
				print "<tr></td><td><td></td>";
				print "<td>".$var_type_beskrivelse[$x][$y]."</td>";
				print "<td><span title='".findtekst(479,$sprog_id)."'><!--tekst 479--><a href='diverse.php?sektion=variant_valg&rename_var_type=".$var_type_id[$x][$y]."' onclick=\"return confirm('".findtekst(484,$sprog_id)."')\"><img src=../ikoner/rename.png border=0></a></span>\n";
				print "<span title='".findtekst(480,$sprog_id)."'><!--tekst 480--><a href='diverse.php?sektion=variant_valg&delete_var_type=".$var_type_id[$x][$y]."' onclick=\"return confirm('".findtekst(482,$sprog_id)."')\"><img src=../ikoner/delete.png border=0></a></span></td></tr>\n";
			}
			print "<input type='hidden' name='variant_id[$x]' value='$variant_id[$x]'>";
			print "<tr><td title='".findtekst(486,$sprog_id)."'><!--tekst 486-->".findtekst(473,$sprog_id)."<!--tekst 473--></td><td></td><td title='".findtekst(486,$sprog_id)."'><!--tekst 486--><input type='text' name='var_type_beskrivelse[$x]'</td></tr>";
		}
		print "<input type='hidden' name='variant_antal' value='$variant_antal'>";
		print "<tr><td title='".findtekst(485,$sprog_id)."'><!--tekst 485-->".findtekst(474,$sprog_id)."<!--tekst 474--></td><td title='".findtekst(485,$sprog_id)."'><!--tekst 485--><input type='text' name='variant_beskrivelse'</td></tr>";
	}
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey='g' value='".findtekst(471,$sprog_id)."' name='submit'><!--tekst 471--></td>";
	print "</form>";

} # endfunc vare_valg

function shop_valg() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	global $db;
	global $labelprint;

#	$hurtigfakt=NULL; $incl_moms=NULL; $folge_s_tekst=NULL; $negativt_lager=NULL; $straks_bogf=NULL; $vis_nul_lev=NULL;
	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse'];$kodenr=$r['kodenr'];$box2=trim($r['box2']);$box3=trim($r['box3']);$box4=trim($r['box4']);$box5=trim($r['box5']);$box7=trim($r['box7']);$box9=trim($r['box9']);
	# OBS $box1 bruges under vare_valg!!
	# OBS $box8 bruges under ordrelaterede valg!!

	print "<form name=diverse action=diverse.php?sektion=shop_valg method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";

	if ($box2=='!') $box3='1';
	print "<tr><td><br></td></tr>";
	print "<tr><td title='".findtekst(695,$sprog_id)."'><!--tekst 826-->".findtekst(695,$sprog_id)."<!--tekst 826--></td><td colspan='3' title='".findtekst(695,$sprog_id)."'><select style='text-align:left;width:300px;' name='box3'>";
	if (!$box3) print "<option value='0'>".findtekst(697,$sprog_id)."<!--tekst 697--></option>";
	if ($box3=='1') print "<option value='1'>".findtekst(698,$sprog_id)."<!--tekst 698--></option>";
	if ($box3=='2') print "<option value='2'>".findtekst(699,$sprog_id)."<!--tekst 829--></option>";
	if ($box3) print "<option value='0'>".findtekst(697,$sprog_id)."<!--tekst 697--></option>";
	if ($box3!='1') print "<option value='1'>".findtekst(698,$sprog_id)."<!--tekst 698--></option>";
	if ($box3!='2') print "<option value='2'>".findtekst(699,$sprog_id)."<!--tekst 829--></option>";
	print "</select></td></tr>";
	if ($box3=='2') {
		print "<tr><td title='".findtekst(503,$sprog_id)."'><!--tekst 503-->".findtekst(504,$sprog_id)."<!--tekst 504--></td><td colspan='3' title='".findtekst(503,$sprog_id)."'><!--tekst 503--><input type='text' style='text-align:left;width:300px;' name='box2' value = '$box2'</td></tr>";
		print "<tr><td title=''>".findtekst(733,$sprog_id)."<!--tekst 733--></td><td colspan='3' title='".findtekst(733,$sprog_id)."'><!--tekst 733--><select style='text-align:left;width:300px;' name='box7'>";
		if ($box7=='UTF-8') {
			print "<option>UTF-8</option>";
			print "<option>ISO-8859-1</option>";
		} else {
			print "<option>ISO-8859-1</option>";
			print "<option>UTF-8</option>";
		}
		print "</select></td></tr>";
		if ($apifil=$box2) {
			$filnavn=mt_rand().".csv";
			if (substr($apifil,0,4)=='http') { #20150608
				print "<tr><td title='".findtekst(740,$sprog_id)."'><!--tekst 740-->".findtekst(741,$sprog_id)."<!--tekst 741--></td><td colspan='3'  title='".findtekst(740,$sprog_id)."'><!--tekst 740--><a href=../api/hent_varer.php target='blank'><input style='text-align:center;width:300px;' type='button' value='".findtekst(741,$sprog_id)."'><!--tekst 749--></a></td></tr>";
				$apifil=str_replace("/?","sync_saldi_kat.php?",$apifil);
				$apifil=$apifil."&saldi_db=$db&filnavn=$filnavn";
#				print "<tr><td title='".findtekst(678,$sprog_id)."'><!--tekst 678-->".findtekst(679,$sprog_id)."<!--tekst 679--></td><td colspan='3'  title='".findtekst(678,$sprog_id)."'><!--tekst 678--><a href=$apifil target='blank'><input style='text-align:center;width:300px;' type='button' value='".findtekst(679,$sprog_id)."'><!--tekst 679--></a></td></tr>";
#				print "<tr><td colspan='3'><span title='Klik her for at hente nye ordrer fra shop'><a href=$apifil target='_blank'>SHOP import</a</span></td></tr>";
			}
		}
	} elseif ($box3=='1') {
		print "<tr><td title='".findtekst(691,$sprog_id)."'><!--tekst 821-->".findtekst(692,$sprog_id)."<!--tekst 822--></td><td colspan='3' title='".findtekst(691,$sprog_id)."'><!--tekst 621--><input type='text' style='text-align:left;width:300px;' name='box4' value = '$box4'</td></tr>";
		print "<tr><td title='".findtekst(752,$sprog_id)."'><!--tekst 752-->".findtekst(753,$sprog_id)."<!--tekst 753--></td><td colspan='3' title='".findtekst(752,$sprog_id)."'><!--tekst 752--><input type='text' style='text-align:left;width:300px;' name='box9' value = '$box9'</td></tr>";
		print "<tr><td title='".findtekst(693,$sprog_id)."'><!--tekst 823-->".findtekst(694,$sprog_id)."<!--tekst 824--></td><td colspan='3' title='".findtekst(693,$sprog_id)."'><!--tekst 823--><input type='text' style='text-align:left;width:300px;' name='box5' value = '$box5'</td></tr>";
	}
	print "<tr><td>";
	print "<br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey='g' value='".findtekst(471,$sprog_id)."' name='submit'><!--tekst 471--></td>";
	print "</form>";
	print "<tr><td colspan='6'><hr></td></tr>";
} # endfunc shop_valg

function api_valg() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	global $db;
	$r=db_fetch_array(db_select("select * from grupper where art = 'API' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$api_key=trim($r['box1']);
	$ip_list=trim($r['box2']);
	$api_bruger=trim($r['box3']);
	$api_fil=trim($r['box4']);

	$x=0;
	$q=db_select("select * from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)){
		if (strpos($r['rettigheder'],'1')===false) {
			$bruger_id[$x]=$r['id'];
			$brugernavn[$x]=$r['brugernavn'];
			$x++;
		}
	}
	if (!$api_key) {
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_!#+-*.:,;';
		$api_key='';
		for ($x=0;$x<36;$x++) $api_key .= substr($chars,rand(0,strlen($chars)-1),1);
	}

	print "<form name=diverse action=diverse.php?sektion=api_valg method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr><td><br></td></tr>";
	if (findtekst(819,$sprog_id)==findtekst(689,$sprog_id)) {
		db_modify("delete from tekster where tekst_id >= '819' or tekst_id <= '825'",__FILE__ . " linje " . __LINE__);
	}
	list($tmp,$folder,$tmp)=explode('/',$_SERVER['REQUEST_URI'],3);
	$url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/$folder/api";
	if (count($bruger_id)) {
		if ($api_bruger) {
			print "<tr><td title='".findtekst(832,$sprog_id)."'><!--tekst 832-->".findtekst(831,$sprog_id)."<!--tekst 831--></td><td colspan='3' title='".findtekst(832,$sprog_id)."'><!--tekst 832-->$db</td></tr>";
			print "<tr><td title='".findtekst(836,$sprog_id)."'><!--tekst 836-->".findtekst(835,$sprog_id)."<!--tekst 835--></td><td colspan='3' title='".findtekst(836,$sprog_id)."'><!--tekst 836-->$url</td></tr>";
			print "<tr><td title='".findtekst(820,$sprog_id)."'><!--tekst 820-->".findtekst(819,$sprog_id)."<!--tekst 819--></td><td colspan='3' title='".findtekst(819,$sprog_id)."'><!--tekst 819--><input type='text' style='text-align:left;width:300px;' name='api_key' value = '$api_key'></td></tr>";
			print "<tr><td title='".findtekst(822,$sprog_id)."'><!--tekst 822-->".findtekst(821,$sprog_id)."<!--tekst 821--></td><td colspan='3' title='".findtekst(822,$sprog_id)."'><!--tekst 822--><input type='text' style='text-align:left;width:300px;' name='ip_list' value = '$ip_list'></td></tr>";
			print "<tr><td title='".findtekst(830,$sprog_id)."'><!--tekst 830-->".findtekst(829,$sprog_id)."<!--tekst 829--></td><td colspan='3' title='".findtekst(830,$sprog_id)."'><!--tekst 822--><input type='text' style='text-align:left;width:300px;' name='api_fil' value = '$api_fil'></td></tr>";
		} else {
			print "<input type='hidden' style='text-align:left;width:300px;' name='api_key' value = '$api_key'>";
			print "<input type='hidden' style='text-align:left;width:300px;' name='ip_list' value = '$ip_list'>";
			print "<input type='hidden' style='text-align:left;width:300px;' name='api_fil' value = '$api_fil'>";
		}
		print "<tr><td title='".findtekst(824,$sprog_id)."'><!--tekst 824-->".findtekst(823,$sprog_id)."<!--tekst 823--></td><td colspan='3' title='".findtekst(824,$sprog_id)."'><!--tekst 824--><select style='text-align:left;width:300px;' name='api_bruger'>";
		if ($api_bruger) {
			for ($x=0;$x<count($bruger_id);$x++){
				if ($api_bruger==$bruger_id[$x]) print "<option value='$bruger_id[$x]'>$brugernavn[$x]</option>";
			}
		}
		print "<option value=''></option>";
		for ($x=0;$x<count($bruger_id);$x++){
			if ($api_bruger!=$bruger_id[$x]) print "<option value='$bruger_id[$x]'>$brugernavn[$x]</option>";
		}
		print "</select></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td><br></td><td colspan='1'><input type=submit style='text-align:center;width:300px;' accesskey='g' value='".findtekst(471,$sprog_id)."' name='submit'><!--tekst 471--></td></tr>";
		print "</form>";
		print "<tr><td colspan='6'><hr></td></tr>";
		print "<tr><td title='".findtekst(740,$sprog_id)."'><!--tekst 740-->".findtekst(741,$sprog_id)."<!--tekst 741--></td><td colspan='3' title='".findtekst(740,$sprog_id)."'><!--tekst 740--><a href=".$_SERVER['PHP_SELF']."?sektion=api_valg&varesync=1><input style='text-align:center;width:300px;' type='button' value='".findtekst(741,$sprog_id)."'><!--tekst 749--></a></td></tr>";
		print "<tr><td title='Opdaterer beskrivelse, stregkode og pris fra shop'><!--tekst 740-->Opdater fra shop<!--tekst 741--></td><td colspan='3' title='Opdaterer beskrivelse, stregkode og pris fra shop'><!--tekst 740--><a href=".$_SERVER['PHP_SELF']."?sektion=api_valg&varesync=2><input style='text-align:center;width:300px;' type='button' value='Opdater fra shop'><!--tekst 749--></a></td></tr>";
	} else print "<tr><td colspan='2'>".findtekst(825,$sprog_id)."</td></tr>";
	print "<tr><td colspan='6'><hr></td></tr>";
	if (isset($_GET['varesync']) && $_GET['varesync']) {
		include ("../api/varesync.php");
		varesync($_GET['varesync']);
	}
} # endfunc api_valg

function labels($valg) {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	global $db;
	global $labelprint;

	if ($valg){
		($valg=='box1')?$txt='Vare':$txt='Adresse';
		print "<form name='diverse' action='diverse.php?sektion=labels&valg=$valg' method='post'>";
		print "<tr bgcolor='$bgcolor5'><td colspan='6' title='".findtekst(737,$sprog_id)."'><!--tekst 737--><b><u>".findtekst(736,$sprog_id)."<!--tekst 736--> ($txt)</u></b></td></tr>";
		if($r=db_fetch_array(db_select("select $valg from grupper where art = 'LABEL'",__FILE__ . " linje " . __LINE__))) $label=$r['box1'];
		print "<tr><td align='center' colspan='4' title='".findtekst(503,$sprog_id)."'><!--tekst 503--><textarea name='label' rows='8' cols='100'>$r[$valg]</textarea></td></tr>";
		print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey='g' value='".findtekst(471,$sprog_id)."' name='submit'><!--tekst 471--></td>";
		print "</form>";
	} else {
		print "<tr><td>Klik på den labeltype du vil redigere</td><td>";
		print "<a href='diverse.php?sektion=labels&valg=box1'><input type='button'  style='width:100px' value='Vare'></a></td></tr>";
		print "<tr><td></td><td><a href=diverse.php?sektion=labels&valg=box2><input type='button' style='width:100px' value='Adresse'></a></td></tr>";
	}
} # endfunc labels

function prislister()
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$prislister=array();
	$antal=0;
	$q=db_select("select * from grupper where art = 'PL' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$antal++;
		$id[$antal]=$r['id'];
		$beskrivelse[$antal]=$r['beskrivelse'];
		$lev_id[$antal]=$r['box1'];
		$prisfil[$antal]=$r['box2'];
		$opdateret[$antal]=$r['box3'];
		$aktiv[$antal]=$r['box4'];
		$rabat[$antal]=$r['box6'];
		$gruppe[$antal]=$r['box8'];
		$filtype[$antal]=$r['box9'];
	}

	$vgrpantal=0;
	$q=db_select("select * from grupper where art = 'VG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$vgrpantal++;
		$vgrp[$vgrpantal]=$r['kodenr'];
		$vgbesk[$vgrpantal]=$r['beskrivelse'];
	}

	$filtyperantal=0;
	$q=db_select("select * from grupper where art = 'FT' order by kodenr",__FILE__ . " linje " . __LINE__);
	if ( db_fetch_array($q) ) {
		while ($r = db_fetch_array($q)) {
			$filtyperantal++;
			$filtyper[$filtyperantal]=$r['kodenr'];
			$filtyperbesk[$filtyperantal]=$r['beskrivelse'];
		}
	} else {
		$filtyperantal++;
		$filtyper[$filtyperantal]="csv";
		$filtypebeskrivelse[$filtyperantal]="Kommasepareret";
		$filtyperantal++;
		$filtyper[$filtyperantal]="tab";
		$filtypebeskrivelse[$filtyperantal]="Tabulator";
		$filtyperantal++;
		$filtyper[$filtyperantal]="sql";
		$filtypebeskrivelse[$filtyperantal]="Databasefil (SQL-dump)";
		$filtyperantal++;
		$filtyper[$filtyperantal]="html";
		$filtypebeskrivelse[$filtyperantal]="HTML-celler (td)";
	}

#	if (!in_array('Solar',$beskrivelse)) {
#		$antal++;
#		$beskrivelse[$antal]='Solar';
#		$prisfil[$antal]="../prislister/solar.txt";
#	}

        print "<tr bgcolor='$bgcolor5'><td colspan='10'><b><u>Prislister</u></b></td></tr>\n";
        print "<tr><td colspan='10'>\n";
#cho $q;
	print "<p>Prislisterne er lister med priser, som hentes fra en anden ressource eksempelvis en fil på en hjemmeside eller et ftp-sted.</p>\n";
	print "</td></tr>\n";

	print "<form name='diverse' action='diverse.php?sektion=prislister' method='post'>\n";
	print "<input type='hidden' name='antal' value='$antal'>\n";
	print "<tr><td colspan='10'><hr></td></tr>\n";
	print "<tr bgcolor='$bgcolor5'>\n";
	print "<td><b>".str_replace('er','e',findtekst(427,$sprog_id))."<!--tekst 427--></b></td>\n";
	print "<td><b>Leverandør</b></td>\n";
	print "<td><b>URL til prislisten</b></td>\n";
	print "<td><b>Filtype</b></td>\n";
	print "<td><b>".findtekst(428,$sprog_id)."<!--tekst 428--></b></td>\n";
	print "<td><b>".findtekst(429,$sprog_id)."<!--tekst 429--></b></td>\n";
	print "<td><b>Lev.rabat</b></td>\n";
	print "<td><b>".findtekst(430,$sprog_id)."<!--tekst 430--></b></td>\n"; # 20160226c start
	print "<td><b>Slet</b></td>\n";
	print "</tr>\n"; # 20160226c slut
	for ($x=1;$x<=$antal;$x++) {
		print "<input type='hidden' name='beskrivelse[$x]' value='$beskrivelse[$x]'>\n";
		print "<input type='hidden' name='prisfil[$x]' value='$prisfil[$x]'>\n";
		print "<input type='hidden' name='id[$x]' value='$id[$x]'>\n";
		print "<tr>\n";
		$title="Prislistens navn.";
		print "<td title='$title'><input class='inputbox' type='text' size='18' name='beskrivelse[$x]' value='".$beskrivelse[$x]."' /></td>\n";
		$title="Prislistens leverandør.";
		print "<td title='$title'><select class='inputbox' type='text' name='lev_id[$x]' />\n"; # 20120226d start
		$levvalg="";
		$q1 = db_select("select id, kontonr, firmanavn from adresser where art = 'K' order by firmanavn",__FILE__ . " linje " . __LINE__);
		while ($levrk = db_fetch_array($q1))
		{
			if ( $levrk['id'] == $lev_id[$x] ) {
				$levvalg.="    <option value='".$levrk['id']."' title='".$levrk['firmanavn']."'>";
				if ( strlen($levrk['firmanavn'])>20 ) {
					$levvalg.=substr($levrk['firmanavn'],0,20)."...";
				} else {
					$levvalg.=$levrk['firmanavn'];
				}
				$levvalg.="</option>\n";
			}
		}

		$q2 = db_select("select id, kontonr, firmanavn from adresser where art = 'K' order by firmanavn",__FILE__ . " linje " . __LINE__);
		while ($levrk = db_fetch_array($q2))
		{
			if ( strlen($levvalg) == 0 ) $levvalg="     <option value='0'>Ingen valgt - vælg en</option>\n";
			if ( $levrk['id'] != $lev_id[$x] ) {
				$levvalg.="    <option value='".$levrk['id']."' title='".$levrk['firmanavn']."'>";
				if ( strlen($levrk['firmanavn'])>20 ) {
					$levvalg.=substr($levrk['firmanavn'],0,20)."...";
				} else {
					$levvalg.=$levrk['firmanavn'];
				}
				$levvalg.="</option>\n";
			}
		}

		if ( strlen($levvalg) == 0 ) {
			$levvalg="     <option disabled='disabled'>Ingen at vælge</option>\n";
			$lev_findes=0;
		} else {
			$lev_findes=1;
		}
		print $levvalg;
		print "</select></td>\n"; # 20160226d

		$title="Prislistens filnavn som er en URL (internetadresse) til selve filen enten p&aring; en hjemmeside eller et ftp-sted.";
		print "<td title='$title'><input class='inputbox' type='text' size='24' name='prisfil[$x]' value='".$prisfil[$x]."' /></td>\n";
		$title="Prislistens type eksempelvis csv (kommasepareret) eller htmltabel.";
		print "<td title='$title'><!--tekst 432--><select class='inputbox' name='filtype[$x]'>\n";
		$filtypevalg="";
		for ($y=1;$y<=$filtyperantal;$y++) { # 20150529
			if ($filtyper[$y]==$filtype[$x]) $filtypevalg.="    <option value='$filtyper[$y]' title='$filtypebeskrivelse[$y]'>$filtyper[$y]</option>\n";
		}
		for ($y=1;$y<=$filtyperantal;$y++) {
			if ($filtyper[$y]!=$filtype[$x]) $filtypevalg.="    <option value='$filtyper[$y]' title='$filtypebeskrivelse[$y]'>$filtyper[$y]</option>\n";
		}
		print $filtypevalg;
		print "</select></td>\n";
		$title=str_replace('$beskrivelse',$beskrivelse[$x],findtekst(431,$sprog_id));
		print "<td title='$title'><!--tekst 431--><input class='inputbox' style='width:25px;text-align:right' type='text' name='rabat[$x]' value='$rabat[$x]'>%</td>\n";
		$title=str_replace('$beskrivelse',$beskrivelse[$x],findtekst(432,$sprog_id));
		print "<td title='$title'><!--tekst 432--><select class='inputbox' name='gruppe[$x]'>\n";
		for ($y=1;$y<=$vgrpantal;$y++) {
			if ($vgrp[$y]==$gruppe[$x]) print "<option value='$vgrp[$y]'>$vgrp[$y]: $vgbesk[$y]</option>\n";
		}
		for ($y=1;$y<=$vgrpantal;$y++) {
			if ($vgrp[$y]!=$gruppe[$x]) print "<option value='$vgrp[$y]'>$vgrp[$y]: $vgbesk[$y]</option>\n";
		}
		print "</select></td>\n";
		if ($aktiv[$x]) {
			if ( $lev_findes ) { # 20160226b start
				$aktiv[$x]="checked ";
			} else {
				$aktiv[$x]="disabled='disabled' ";
			}
			$slet[$x]="disabled";
			$title=findtekst(426,$sprog_id);
			print "<td title='$title'><!--tekst 426--><a href='lev_rabat.php?id=$id[$x]&amp;lev_id=$lev_id[$x]&amp;prisliste=$beskrivelse[$x]'>Lev.rabat</a></td>\n";
			print "<td>\n";
			print "    <input class='inputbox' type='checkbox' name='aktiv[$x]' $aktiv[$x] \n"; # 20150424
			print "        title='".str_replace('$beskrivelse',$beskrivelse[$x],findtekst(425,$sprog_id))."'><!--tekst 425-->&nbsp;\n";
			print "</td>\n<td><input type='checkbox' value='0' name='slet[$x]' $slet[$x] \n";
			print "        title='Sletter referencen til prislisten. Er kun muligt, n&aring;r prislisten ikke er aktiv.'>\n";
		} else {
			print "<td>-</td>\n";
			print "<td>\n";
			print "    <input class='inputbox' type='checkbox' name='aktiv[$x]' "; # 20150424 20160226
			if ( $lev_findes && $lev_id[$x] ) { # 20160226e start
				print "\n        title='".str_replace('$beskrivelse',$beskrivelse[$x],findtekst(425,$sprog_id))."'><!--tekst 425-->&nbsp;\n"; # 20160226e slut
			} else {
				print "disabled='disabled' \n";
				print "\n        title='Opret og angiv leverandør før prislisen kan gøres aktiv.'>\n"; # 20160226b slut
			}
			print "</td>\n<td><input type='checkbox' value='Slet' name='slet[$x]' \n";
			print "        title='Sletter referencen til prislisten. Er kun muligt, n&aring;r prislisten ikke er aktiv.'>\n";
		}
		print "</td>\n</tr>\n";
	}
#	print "<input type='hidden' name='aktiv[$x]' value='on'>\n"; # 20160226f
	print "<input type='hidden' name='antal' value='$x'>\n";
	print "<tr>\n";
	print "<td><input class='inputbox' type='text' size='20' name='beskrivelse[$x]' title='Nummer $x'></td>\n";

	$title="Vælg leverandør (husk at oprette den inden)";
	print "<td title='$title'><select class='inputbox' type='text' name='lev_id[$x]' />\n";
	$levvalg="";
	$q3 = db_select("select id, kontonr, firmanavn from adresser where art = 'K' order by firmanavn",__FILE__ . " linje " . __LINE__);
	while ($levrk = db_fetch_array($q3))
	{
		if ( $levrk['id'] != $lev_id[$x] ) {
			$levvalg.="    <option value='".$levrk['id']."' title='".$levrk['firmanavn']."'>";
			if ( strlen($levrk['firmanavn'])>20 ) {
				$levvalg.=substr($levrk['firmanavn'],0,20)."...";
			} else {
				$levvalg.=$levrk['firmanavn'];
			}
			$levvalg.="</option>\n";
		}
	}

	if ( strlen($levvalg) == 0 ) {
		$levvalg="     <option disabled='disabled' title='Opret leverandører først under Kreditorer'>Ingen at vælge</option>\n";
		$lev_findes=0;
	} else {
		$lev_findes=1;
	}
	print $levvalg;
	print "</select></td>\n";


	print "<td><input class='inputbox' type='text' size='24' name='prisfil[$x]'></td>\n";
	$title="Prislistens type eksempelvis csv (kommasepareret) eller htmltabel.";
	print "<td title='$title'><!--tekst 432--><select class='inputbox' name='filtype[$x]'>\n";
	$filtypevalg="";
	for ($y=1;$y<=$filtyperantal;$y++) { # 20150529
		if ($filtyper[$y]==$filtype[$x]) $filtypevalg.="    <option value='$filtyper[$y]' title='$filtypebeskrivelse[$y]'>$filtyper[$y]</option>\n";
	}
	for ($y=1;$y<=$filtyperantal;$y++) {
		if ($filtyper[$y]!=$filtype[$x]) $filtypevalg.="    <option value='$filtyper[$y]' title='$filtypebeskrivelse[$y]'>$filtyper[$y]</option>\n";
	}
	print $filtypevalg;
	print "</select></td>\n";
	print "<td title='".str_replace(' $beskrivelse','', findtekst(431,$sprog_id))." den prisliste, som er ved at blive oprettet.'><!--tekst 431-->\n";
	print "    <input class='inputbox' style='width:25px;text-align:right' type='text' name='rabat[$x]' min='0' max='100' value='0'>%</td>\n";
	print "<td title='".str_replace(' $beskrivelse','', findtekst(432,$sprog_id))." den prisliste, som er ved at blive oprettet.'><!--tekst 432-->\n";
	print "    <select class='inputbox' name='gruppe[$x]'>\n";
	for ($y=1;$y<=$vgrpantal;$y++) {
		print "    <option value='$vgrp[$y]'";
		if ( $y == 1 ) print " selected='selected'";
		print ">$vgrp[$y]: $vgbesk[$y]</option>\n";
	}
	print "<td \n";
	print "    title='Prislisten s&aelig;ttes automatisk til inaktiv ved oprettelse, da den f&oslash;rst skal specificeres mere deltaljeret, f&oslash;r den kan benyttes (aktiveres).'>\n";
	print "    &nbsp;\n</td>\n";
	print "</tr>\n";
	print "<tr><td><br></td><td><br></td><td><br></td><td align='center'><input class='button green medium type='submit' accesskey='g' value='Gem/opdat&eacute;r' name='submit'></td></tr>\n";
	print "</form>\n\n";
} # endfunc prislister

function rykker_valg()
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$box1=NULL;$box2=NULL;$box3=NULL;$box4=NULL;$box5=NULL;$box6=NULL;$box7=NULL;$box8=NULL;$box9=NULL;

	$r = db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '4'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$box1=$r['box1'];
	$box2=$r['box2'];
	if ($r['box3']) $box3=$r['box3']*1;
	$box4=$r['box4'];
	if ($r['box5']) $box5=$r['box5']*1;
	if ($r['box6']) $box6=$r['box6']*1;
	if ($r['box7']) $box7=$r['box7']*1;
#	$box8=$r['box8']; Box 8 bruger til resistrering af sidst sendte reminder.
	$box9=$r['box9']; # Inkasso.
	if ($box9) {
		$qtxt="select kontonr from adresser where id='$box9'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$box9=$r['kontonr'];
	}
	$box10=$r['box10'];

	$x=0;
	$q = db_select("select id,brugernavn from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$br_id[$x]=$r['id'];
		$br_navn[$x]=$r['brugernavn'];
		if ($box1==$br_id[$x]) $box1=$br_navn[$x];
	}
	$br_antal=$x;
/*
	if ($box3 || $box4) {
		if ($r=db_fetch_array(db_select("select beskrivelse from varer where varenr = '$box4'",__FILE__ . " linje " . __LINE__))) {
			$varetekst=htmlentities($r['beskrivelse']);
		} else print "<BODY onLoad=\"JavaScript:alert('Varenummer ikke gyldigt')\">";
	}
*/
	print "<form name='diverse action=diverse.php?sektion=rykker_valg' method='post'>\n";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b>Rykkerrelaterede valg</b></td></tr>\n";
	print "<input type='hidden' name=id value='$id'>\n";
	#Box1 Brugernavn for "rykkeransvarlig - Naar bruger logger ind adviseres hvis der skal rykkes - Hvis navn ikke angives adviseres alle..
	$title=""; # HERTIL
	print "<tr><td title='".findtekst(224,$sprog_id)."'>".findtekst(225,$sprog_id)."</td>\n";
	print "<td title='".findtekst(224,$sprog_id)."'><select class='inputbox' name='box1' style='width:80px'>\n";
	if ($box1) print "    <option>$box1</option>\n";
	print "<option value=''>- Alle -</option>\n";
	for ($x=1;$x<=$br_antal;$x++){
		if ($br_navn[$x]!=$box1) print "<option>$br_navn[$x]</option>\n";
	}
	print "</select></td></tr>\n";
	#Box2 Mailadresse for rykkeransvarlig hvis angivet sendes email naar der skal rykkes. (Naar nogen logger ind - uanset hvem)
	print "<tr><td title='".findtekst(226,$sprog_id)."'>".findtekst(227,$sprog_id)."</td>\n";
	print "<td title='".findtekst(226,$sprog_id)."'><input class='inputbox' type='text' size='30' name='box2' value='$box2'></td></tr>\n"; # 20150625
	#Box4 Varenummer for rente
#	print "<tr><td title='".findtekst(230,$sprog_id)."'>".findtekst(231,$sprog_id)."</td><td><input class='inputbox' type=text size=15 name=box4 value='$box4'></td></tr>";
	#Box3 Rentesats % pr paabegyndt md.
#	print "<tr><td title='".findtekst(228,$sprog_id)."'>".findtekst(229,$sprog_id)."</td><td><input class='inputbox' type=text style='text-align:right' size=1 name=box3 value='$box3'> %</td></tr>";
	#Box5 Dage betalingsfrist skal vaere overskredet foer der rykkes.
	print "<tr><td title='".findtekst(232,$sprog_id)."'>".findtekst(233,$sprog_id)."</td>\n";
	print "<td><input class='inputbox' type='text' style='text-align:right' size='3' name='box5' value='$box5'> dage</td></tr>\n";
	#Box6 Dage fra rykker 1 til rykker 2
	print "<tr><td title='".findtekst(234,$sprog_id)."'>".findtekst(235,$sprog_id)." </td>\n";
	print "<td><input class='inputbox' type='text' style='text-align:right' size='3' name='box6' value='$box6'> dage</td></tr>\n";
	#Box7 Dage fra rykker 2 til rykker 3
	print "<tr><td title='".findtekst(236,$sprog_id)."'>".findtekst(237,$sprog_id)." </td>\n";
	print "<td><input class='inputbox' type='text' style='text-align:right' size='3' name='box7' value='$box7'> dage</td></tr>\n";
	print "<td colspan='3'>&nbsp;</td>\n";
	if (!strpos(findtekst(833,$sprog_id),'inkasso')) db_modify("delete from tekster where tekst_id='833' and sprog_id='$sprog_id'");
	if (!strpos(findtekst(834,$sprog_id),'udfylde')) db_modify("delete from tekster where tekst_id='834' and sprog_id='$sprog_id'");
	print "<tr><td title='".findtekst(834,$sprog_id)."'>".findtekst(833,$sprog_id)." </td>\n";
	print "<td><input class='inputbox' type='text' style='text-align:right;width=20px;' name='box9' value='$box9'></td></tr>\n";
	print "<td colspan='3'>&nbsp;</td>\n";
	print "<td align='center'><input class='button green medium' type='submit' accesskey='g' value='Gem/opdat&eacute;r' name='submit'></td>\n";
	print "</form>\n";
} # endfunc rykker_valg


function tjekliste() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$ret=if_isset($_GET['ret']);

	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' order by fase",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$tjekpunkt[$x]=$r['tjekpunkt'];
		$fase[$x]=$r['fase']*1;
		$assign_id[$x]=$r['assign_id']*1;
		$punkt_id[$x]=0;
		$gruppe_id[$x]=0;
		$liste_id[$x]=$id[$x];
		$q2 = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$max_gruppe=$x;
			$id[$x]=$r2['id'];
			$tjekpunkt[$x]=$r2['tjekpunkt'];
			$assign_id[$x]=$r2['assign_id']*1;
			$fase[$x]=$fase[$x-1];
			$punkt_id[$x]=0;
			$gruppe_id[$x]=$id[$x];
			$liste_id[$x]=$liste_id[$x-1];
#cho "select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt<br>\n";
			$q3 = db_select("select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt",__FILE__ . " linje " . __LINE__);
			while ($r3 = db_fetch_array($q3)) {
				$x++;
				$id[$x]=$r3['id'];
				$tjekpunkt[$x]=$r3['tjekpunkt'];
				$assign_id[$x]=$r3['assign_id']*1;
				$fase[$x]=$fase[$x-1];
				$punkt_id[$x]=$id[$x];
				$gruppe_id[$x]=$gruppe_id[$x-1];
				$liste_id[$x]=$liste_id[$x-1];
			}
		}
	}
	$fasenr=0;
	print "<form name='diverse' action='diverse.php?sektion=tjekliste' method='post'>\n";
	print "<tr><td colspan='6'><hr></td></tr>\n";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>Tjeklister</u></b></td></tr>\n";
	for ($x=1;$x<=count($id);$x++) {
		if ($fase[$x]!=$fase[$x-1]) $fasenr++;
		print "<input type='hidden' name='tjekantal' value='".count($id)."'>\n";
		print "<input type='hidden' name='id[$x]' value='$id[$x]'>\n";
		print "<input type='hidden' name='fase[$x]' value='$fase[$x]'>\n";
		print "<input type='hidden' name='tjekpunkt[$x]' value='$tjekpunkt[$x]'>\n";
		if ($fase[$x]!=$fasenr) db_modify("update tjekliste set fase='$fasenr' where id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
		if (!$gruppe_id[$x] && !$punkt_id[$x]) {
			print "<tr><td colspan='6'><hr></td></tr>\n";
			if ($ret==$id[$x]) print "<tr><td colspan='1'><big><b><input class='inputbox' type='text' name='tjekpunkt[$x]' size='20' value='$tjekpunkt[$x]'></b></big></td><td><input class='inputbox' type='text' name='ny_fase[$x]' style='text-align:right;width:20px' value='$fasenr'></td></tr>\n";
			else print "<tr><td colspan='1'><span title='Klik for at ændre navnet'><big><b><a href='../systemdata/diverse.php?sektion=tjekliste&ret=$id[$x]' style='text-decoration:none'>$tjekpunkt[$x]</a></b></big></td><td><input class='inputbox' type='text' name='ny_fase[$x]' style='text-align:right;width:20px' value='$fasenr'></span></td></tr>\n";
			$l_id=$id[$x];
		}
		if ($gruppe_id[$x] && !$punkt_id[$x]) {
			print "<input type='hidden' name='tjekgruppe[$x]' value='$id[$x]'>\n";
			if ($ret==$id[$x]) print "<tr><td title='$assign_id[$x]==$l_id'><b><input class='inputbox' type='text' name='tjekpunkt[$x]' size='20' value='$tjekpunkt[$x]'></b></td><td><input class='inputbox' type='checkbox' name='aktiv[$x]'></td></tr>\n";
			else print "<tr><td title='$assign_id[$x]==$l_id'><span title='Klik for at ændre navnet'><b><a href='../systemdata/diverse.php?sektion=tjekliste&ret=$id[$x]' style='text-decoration:none'>".$tjekpunkt[$x]."</a></b></td><td><input class='inputbox' type='checkbox' name='aktiv[$x]'></span></td></tr>\n";
		}
		if ($punkt_id[$x]) {
			print "<input type='hidden' name='tjekgruppe[$x]' value='$id[$x]'>\n";
			if ($ret==$id[$x]) print "<tr><td title='$assign_id[$x]==$l_id'><input class='inputbox' type='text' name='tjekpunkt[$x]' size='20' value='$tjekpunkt[$x]'></td><td><input class='inputbox' type='checkbox' name='aktiv[$x]'></td></tr>\n";
			else print "<tr><td title='$assign_id[$x]==$l_id'><span title='Klik for at ændre navnet'><a href='../systemdata/diverse.php?sektion=tjekliste&ret=$id[$x]' style='text-decoration:none'>".$tjekpunkt[$x]."</a></td><td><input class='inputbox' type='checkbox' name='aktiv[$x]'></span></td></tr>\n";
		}
		if ($gruppe_id[$x] && $gruppe_id[$x] != $gruppe_id[$x+1]) {
			print "<input type='hidden' name='fase[$x]' value='$fase[$x]'>\n";
			print "<input type='hidden' name='gruppe_id[$x]' value='$gruppe_id[$x]'>\n";
#				print "<input type='hidden' name='assign_id[$x]' value='$assign_id[$x]'>\n";
			print "<tr><td>Nyt tjek punkt</td><td><input class='inputbox' type='text' name='nyt_tjekpunkt[$x]' size='20' value=''></td></tr>\n";
		}
		if ($liste_id[$x] && $liste_id[$x] != $liste_id[$x+1]) {
			print "<input type='hidden' name='fase[$x]' value='$fase[$x]'>\n";
			print "<input type='hidden' name='liste_id[$x]' value='$liste_id[$x]'>\n";
#			print "<input type='hidden' name='liste_id[$x]' value='$assign_id[$x]'>\n";
			print "<tr><td colspan='6'></td></tr>\n";
			print "<tr><td><b>Ny tjek gruppe</b></td><td><input class='inputbox' type='text' name='ny_tjekgruppe[$x]' size='20' value=''></td></tr>\n";
		}
	}
	print "<tr><td colspan='6'><hr></td></tr>\n";
#	$ny_fase=$fase[$x]+1;
	print "<input type='hidden' name='ret' value='$ret'>\n";
	print "<tr><td>Ny tjekliste</td><td><input class='inputbox' type='text' name='ny_tjekliste' size='20' value=''></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<td><br></td><td><br></td><td><br></td><td align = 'center'><input class='button green medium' type='submit' accesskey='g' value='Gem/opdat&eacute;r' name='submit'></td>\n";
	print "</form>\n";

} # endfunc tjeklister

function docubizz() {
	global $bgcolor;
	global $bgcolor5;

	?>
	<script Language="JavaScript">
	<!--
	function Form1_Validator(docubizz) {
		if (docubizz.box3.value != docubizz.pw2.value) {
		alert("Begge adgangskoder skal v&aelig;re ens.");
		docubizz.box3.focus();
		return (false);
		}
	}
	//--></script>

	<?php
	$q = db_select("select * from grupper where art = 'DocBiz'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$ftpsted=$r['box1'];
	$ftplogin=$r['box2'];
	$ftpkode=$r['box3'];
	$ftp_dnld_mappe=$r['box4'];
	$ftp_upld_mappe=$r['box5'];

	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b>DocuBizz</b></td></tr>\n";
	print "<tr><td colspan='6'><br></td></tr>\n";

	print "<form name='docubizz' action=diverse.php?sektion=docubizz method='post' onsubmit=\"return Form1_Validator(this)\">\n";
	print "<input type='hidden' name='id' value='$id'>\n";
	print "<tr><td>Navn eller IP-nummer p&aring; ftp-server</td>";
	print "<td colspan='2'><input class='inputbox' type='text' name='box1' size='25' value='$ftpsted'></td></tr>\n";
	print "<tr><td>Mappe til download p&aring; ftp-server</td>";
	print "<td colspan='2'><input class='inputbox' type='text' name='box4' size='25' value='$ftp_dnld_mappe'></td></tr>\n";
	print "<tr><td>Mappe til upload p&aring; ftp-server</td>";
	print "<td colspan='2'><input class='inputbox' type='text' name='box5' size='25' value='$ftp_upld_mappe'></td></tr>\n";
	print "<tr><td>Brugernavn p&aring; ftp-server</td>";
	print "<td colspan='2'><input class='inputbox' type='text' name='box2' size='25' value='$ftplogin'></td></tr>\n";
	print "<tr><td>Adgangskode til ftp-server</td>";
	print "<td colspan='2'><input class='inputbox' type='password' name='box3' size='25' value='$ftpkode'></td></tr>\n";
	print "<tr><td>Gentag adgangskode</td>";
	print "<td colspan='2'><input class='inputbox' type='password' name='pw2' size='25' value='$ftpkode'></td></tr>\n";
	print "<tr><td>&nbsp;</td></tr>\n";
	print "<tr><td>&nbsp;</td><td><br></td><td>&nbsp;</td>";
	print "<td align='center'><input class='button green medium' style='width:8em' type='submit' accesskey='g' value='Gem/opdat&eacute;r' name='submit'></td><tr>\n";
	print "</form>\n\n";
	print "<form name='upload_dbz' action='diverse.php?sektion=upload_dbz' method='post'>\n";
	print "<tr><td>&nbsp;</td></tr>\n";
	print "<tr><td colspan='3'>Opdater Docubizz server</td>";
	print "<td align='center'><input style='width:8em' type='submit' accesskey='g' value='Send data' name='submit'></td><tr>\n";
	print "</form>\n\n";

} # endfunc docubizz

function bilag() {
	global $s_id;
	global $bgcolor;
	global $bgcolor5;
	global $db;

	?>
	<script Language="JavaScript">
	<!--
	function Form1_Validator(ftp) {
		if (ftp.box3.value != ftp.pw2.value) {
		alert("Begge adgangskoder skal v&aelig;re ens.");
		ftp.box3.focus();
		return (false);
		}
	}
	//--></script>

	<?php
	$r=db_fetch_array(db_select("select * from grupper where art = 'bilag'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$ftpsted=$r['box1'];
	$ftplogin=$r['box2'];
	$ftpkode='********';
	$ftp_bilag_mappe=$r['box4'];
	$ftp_dokument_mappe=$r['box5'];
	if ($r['box6']=='on') {
		$intern_ftp='checked';
	} else {
		$intern_ftp=NULL;
		if (!$ftpsted && !$ftplogin) {
			$ftpsted=NULL;
			$ftplogin=NULL;
			$ftp_bilag_mappe=NULL;
			$ftp_dokument_mappe=NULL;
			$extern_ftp=NULL;
		} else $extern_ftp='checked';
	}
	if (!isset ($sprog_id)) $sprog_id = null;
	if (!isset ($onclick)) $onclick = null;
	if ($_POST['box6']=='extern_ftp') $extern_ftp=1;
	if (!$ftp_bilag_mappe) $ftp_bilag_mappe='bilag';
	if (!$ftp_dokument_mappe) $ftp_dokument_mappe='dokumenter';
	($r['box7'])?$google_docs='checked':$google_docs=NULL;

	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b>Bilagshåndtering</b></td></tr>\n";
	print "<tr><td colspan='6'><br></td></tr>\n";
	print "<tr><td colspan='6'>Denne sektion indeholder de informationer, som er n&oslash;dvendige for at kunne h&aring;ndtere scannede bilag</td></tr>\n";
	print "<tr><td colspan='6'>Du kan vælge at lade os opbevare dine scannede bilag for kr. 30,- pr. m&aring;ned pr. GB,</td></tr>\n";
	print "<tr><td colspan='6'>hvilket ligeledes giver mulighed for at sende indscannede bilag pr. e-mail til serveren</td></tr>\n";
	print "<tr><td colspan='6'>og efterf&oslash;lgende importere dem i kassekladden.</td></tr>\n";
	print "<tr><td colspan='6'>Bilag sendes til ";
	print "<a href='mailto:bilag_".$db."@".$_SERVER['SERVER_NAME']."'>";
	print "bilag_".$db."@".$_SERVER['SERVER_NAME']."</a>.</td></tr>\n";
	print "<tr><td colspan='6'>Du kan også vælge selv at sætte en ftp-server op til formålet eller benytte en eksisterende. Det koster ikke noget.</td></tr>\n";
	print "<tr><td colspan='6'>&nbsp;</td></tr>\n\n";
	print "<form name='ftp' action='diverse.php?sektion=bilag' method='post' onsubmit=\"return Form1_Validator(this)\">\n";
	print "<input type='hidden' name='id' value='$id'>\n";
	print "<tr><td>Opbevaring af bilag</td><td><select name=\"box6\">";
	if ($intern_ftp) print "<option value=\"intern_ftp\">Intern opbevaring</option>";
	elseif ($extern_ftp) print "<option value=\"extern_ftp\">Egen FTP server</option>";
	else print "<option value=\"\">Ingen opbevaring</option>";
	if (!$intern_ftp) print "<option value=\"intern_ftp\">Intern opbevaring</option>";
	if (!$extern_ftp) print "<option value=\"extern_ftp\">Egen FTP server</option>";
	if ($intern_ftp || $extern_ftp) print "<option value=\"\">Ingen opbevaring</option>";
	print "</select></td></tr>";
/*
	if ($intern_ftp) $onclick=NULL;
	else $onclick="onclick=\"return confirm('Intern bilagsopbevaring koster kr. 30,- pr. md. pr. GB.')\"";
	print "<tr>\n<td title='".findtekst(212,$sprog_id)."'>".findtekst(211,$sprog_id)."</td>\n";
	print "<td colspan='2' title='".findtekst(212,$sprog_id)."'>";
	print "<input $onclick class='inputbox' type='checkbox' name='box6' $intern_ftp></td>\n</tr>\n";
*/
	print "<tr>\n<td title='".findtekst(720,$sprog_id)."'>".findtekst(719,$sprog_id)."</td>\n";
	print "<td colspan='2' title='".findtekst(720,$sprog_id)."'>";
	print "<input $onclick class='inputbox' type='checkbox' name='box7' $google_docs></td>\n</tr>\n";
	if ($extern_ftp) {
		print "<tr>\n<td>Navn eller IP-nummer p&aring; ftp-server</td>\n";
		print "<td colspan='2'><input class='inputbox' type='text' name='box1' size='25' value='$ftpsted'></td>\n</tr>\n";
		print "<tr>\n<td>Brugernavn p&aring; ftpserver</td>\n";
		print "<td colspan='2'><input class='inputbox' type='text' name='box2' size='25' value='$ftplogin'></td>\n</tr>\n";
		print "<tr>\n<td>Adgangskode til ftpserver</td>\n";
		print "<td colspan='2'><input class='inputbox' type='password' name='box3' size='25' value='$ftpkode'></td>\n</tr>\n";
		print "<tr>\n<td>Gentag adgangskode</td>\n";
		print "<td colspan='2'><input class='inputbox' type='password' name='pw2' size='25' value='$ftpkode'></td>\n</tr>\n";
		print "<tr>\n<td>Mappe til bilag p&aring; ftpserver</td>";
		print "<td colspan='2'><input class='inputbox' type='text' name='box4' size='25' value='$ftp_bilag_mappe'></td>\n</tr>\n";
		print "<tr>\n<td>Mappe til dokumenter p&aring; ftpserver</td>\n";
		print "<td colspan='2'><input class='inputbox' type='text' name='box5' size='25' value='$ftp_dokument_mappe'></td>\n</tr>\n";
		print "<tr><td>&nbsp;</td></tr>\n";
	}
	print "<tr>\n<td colspan='3'>&nbsp;</td>\n";
	print "<td align='center'><input class='button green medium' style='width:8em' type='submit' accesskey='g' value='Gem/opdat&eacute;r' name='submit'></td>\n<tr>\n";
	print "</form>\n\n";
} # endfunc bilag

function orediff($diffkto)
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$q = db_select("select * from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$maxdiff=dkdecimal($r['box1']);
	if (!$diffkto) $diffkto=$r['box2'];

	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b>".findtekst(170,$sprog_id)."</b></td></tr>\n";
	print "<tr><td colspan='2'>&nbsp;</td></tr>\n";

	print "<form name='orediff' action='diverse.php?sektion=orediff' method='post' onsubmit=\"return Form1_Validator(this)\">\n";
	print "<input type='hidden' name='id' value='$id'>\n";
	print "<tr>\n<td title='".findtekst(171,$sprog_id)."'>".findtekst(172,$sprog_id)."</td>\n";
	print "<td colspan='1'><input class='inputbox' type='text' style='text-align:right' name='box1' size='3' value='$maxdiff'></td>\n</tr>\n";
	print "<tr>\n<td title='".findtekst(173,$sprog_id)."'>".findtekst(174,$sprog_id)."</td>\n";
	print "<td colspan='1'><input class='inputbox' type='text' style='text-align:right' name='box2' size='3' value='$diffkto'></td>\n</tr>\n";
	print "<tr><td colspan='1'>&nbsp;</td>\n";
	print "<td align='center'><input class='button green medium' style='width:8em' type='submit' accesskey='g' value='Gem/opdat&eacute;r' name='submit'></td>\n<tr>\n";
	print "</form>\n\n";
} # endfunc orediff.

function massefakt () {
	global $sprog_id;
	global $docubizz;
	global $bgcolor;
	global $bgcolor5;

	$folge_s_tekst=NULL;$gruppevalg=NULL;$kuansvalg=NULL;
	$ref=NULL; $kua=NULL; $smart=NULL;
	$kort=NULL; $batch=NULL;

	$q = db_select("select * from grupper where art = 'MFAKT' and kodenr = '1'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	if ($r['box1'] == 'on') $brug_mfakt='checked';
	else $brug_mfakt='';
	if ($r['box2'] == 'on') $brug_dellev='checked';
	else $brug_dellev='';
	$levfrist=$r['box3'];

	print "<form name='diverse' action='diverse.php?sektion=massefakt' method='post'>\n";
	print "<tr bgcolor='$bgcolor5'><td colspan='2'><b>".findtekst(200,$sprog_id)."</b></td></tr>\n";
	print "<tr><td colspan='6'>&nbsp;</td></tr>\n";
	print "<input name='id' type='hidden' value='$id'>\n";
	print "<tr>\n<td title='".findtekst(202,$sprog_id)."'>".findtekst(201,$sprog_id)."</td>\n";
	print "<td><input name='brug_mfakt' class='inputbox' type='checkbox' $brug_mfakt></td>\n</tr>\n";
	print "<tr>\n<td title='".findtekst(204,$sprog_id)."'>".findtekst(203,$sprog_id)."</td>\n";
	print "<td><input name='brug_dellev' class='inputbox' type='checkbox' $brug_dellev></td>\n</tr>\n";
	print "<tr>\n<td title='".findtekst(206,$sprog_id)."'>".findtekst(205,$sprog_id)."</td>\n";
	print "<td><input name='levfrist' class='inputbox' type='text' style='text-align:right' size='3' value='$levfrist'></td>\n</tr>\n";
	print "<tr>\n<td>&nbsp;</td>\n";
	print "<td style='text-align:center'><input class='button green medium' name='submit' type='submit' accesskey='g' value='Gem/opdat&eacute;r'></td>\n</tr>\n";
	print "</form>\n\n";
} # endfunc massefakt
#####################################################
function pos_valg () {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$kassekonti=array();
	$afd=array();

	$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$id1=$r['id'];
	$kasseantal=$r['box1']*1;
	$kassekonti=explode(chr(9),$r['box2']);
	$afd=explode(chr(9),$r['box3']);
	$kortantal=$r['box4']*1;
	$korttyper=explode(chr(9),$r['box5']);
	$kortkonti=explode(chr(9),$r['box6']);
	$moms=explode(chr(9),$r['box7']);
	$rabatvareid=$r['box8']*1;
	($r['box9'])?$straksbogfor='checked':$straksbogfor='';
	($r['box10'])?$udskriv_bon='checked':$udskriv_bon='';
	($r['box11'])?$vis_kontoopslag='checked':$vis_kontoopslag='';
	($r['box12'])?$vis_hurtigknap='checked':$vis_hurtigknap='';
	$timeout=$r['box13']*1;
	($r['box14'])?$vis_indbetaling='checked':$vis_indbetaling='';

	if ($r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__))) {
		$id2=$r['id'];
	} else {
		db_modify("insert into grupper(beskrivelse,kode,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14)values('Pos valg','','2','POS','0','','','','','','','','','','','','','')",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
		$id2=$r['id'];
	}
	$kasseprimo=dkdecimal($r['box1']);
	($r['box2'])?$optalassist='checked':$optalassist=NULL;
	$printer_ip=explode(chr(9),$r['box3']);
	$terminal_ip=explode(chr(9),$r['box4']);
	$betalingskort=explode(chr(9),$r['box5']); #20131210
	$div_kort_kto=$r['box6']; #20140129
	if ($r['box7']) $bord=explode(chr(9),str_replace("\n","  ",$r['box7'])); #20140506
	$mellemkonti=explode(chr(9),$r['box8']);
	$diffkonti=explode(chr(9),$r['box9']);
	$koekkenprinter=explode(chr(9),$r['box10']);
	$vare_id=$r['box11'];
	($r['box12'])?$vis_saet='checked':$vis_saet='';
	$bordvalg=explode(chr(9),$r['box13']);
	($r['box14'])?$udtag0='checked':$udtag0=NULL;
	if ($r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '3'",__FILE__ . " linje " . __LINE__))) {
		$id3=$r['id'];
	} else {
		$qtxt="insert into grupper(beskrivelse,kode,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14)";
		$qtxt.="values";
		$qtxt.="('Pos valg','','3','POS','0','10','','','','','','','','','','','','')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
		$id3=$r['id'];
	}
	($r['box1'])?$brugervalg='checked':$brugervalg=NULL;
	$pfs=explode(chr(9),$r['box2']);
	($r['box3'])?$kundedisplay='checked':$kundedisplay=NULL;
	$gavekort=explode(chr(9),$r['box4']); #20181029
	$gavekorttekst=explode(chr(9),$r['box5']); #20181029

	$qtxt="select var_value from settings where var_name='card_enabled'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$card_enabled=$r['var_value'];
	$enabled=explode(chr(9),$card_enabled); #20181215

	$qtxt="select var_value from settings where var_name='change_cardvalue'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	($r['var_value'])?$change_cardvalue='checked':$change_cardvalue=NULL;

	$qtxt="select var_value from settings where var_name='deactivateBonprint'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	($r['var_value'])?$deactivateBonprint='checked':$deactivateBonprint=NULL;

	$qtxt="select var_value from settings where var_name='postEachSale'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$postEachSale=explode(chr(9),str_replace('on','checked',$r['var_value']));
	} elseif ($straksbogfor) {
		for ($x=0;$x<count($kassekonti);$x++) {
			$postEachSale[$x]=$straksbogfor;
		}
	}

	/*
	$posbuttons=0;
	$q = db_select("select * from grupper where art = 'POSBUT'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $posbuttons++;
*/
	$x=0;
	$q = db_select("select * from grupper where art = 'VK' order by box1",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ValutaKode[$x]=$r['box1'];
		$ValutaKonti[$x]=explode(chr(9),$r['box4']);
		$ValutaMlKonti[$x]=explode(chr(9),$r['box5']);
		$ValutaDifKonti[$x]=explode(chr(9),$r['box6']);
		$x++;
	}

	if ($vare_id) {
		$r = db_fetch_array(db_select("select varenr from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
		$varenr=$r['varenr'];
	}
	if ($rabatvareid) {
		$r = db_fetch_array(db_select("select varenr from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__));
		$rabatvarenr=$r['varenr'];
	}

	$x=0;
	if ($kasseantal) {
		$q = db_select("select * from grupper where art = 'AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$afd_nr[$x]=$r['kodenr'];
			$afd_navn[$x]=$r['beskrivelse'];
		}
		$afd_antal=$x;
		$x=0;
		$q = db_select("select * from grupper where art = 'SM' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$moms_nr[$x]=$r['kodenr'];
			$moms_navn[$x]=$r['beskrivelse'];
		}
		$moms_antal=$x;
	}
#	(isset($_COOKIE['saldi_pfs']))?$pfs=$_COOKIE['saldi_pfs']:$pfs=10; #Pos Font Size

	print "<form name=diverse action=diverse.php?sektion=pos_valg method=post>\n";
	print "<tr><td width='100%'><hr></td></tr>\n";
	print "<tr><td><table><tbody>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(265,$sprog_id)."</u></b></td></tr>\n";
	print "<tr><td colspan='6'><br></td></tr>\n";
	print "<input type=hidden name=id1 value='$id1'>\n";
	print "<input type=hidden name=id2 value='$id2'>\n";
	print "<input type=hidden name=id3 value='$id3'>\n";
	print "<tr><td title='".findtekst(266,$sprog_id)."'>".findtekst(267,$sprog_id)."</td><td><input class='inputbox' type='text' style='text-align:right;width:70px;' name='kasseantal' value='$kasseantal'></td></tr>\n";
#	print "<tr><td title='".findtekst(285,$sprog_id)."'>".findtekst(285,$sprog_id)."</td>";
	if ($kasseantal) {
#		print "<tr><td title='".findtekst(730,$sprog_id)."'>".findtekst(729,$sprog_id)."</td><td><input class='inputbox' type='text' style='text-align:right;width:70px;' name='varenr' value='$varenr'></td></tr>";
		print "<tr><td title='".findtekst(288,$sprog_id)."'>".findtekst(287,$sprog_id)."</td><td><input class='inputbox' type='text' style='text-align:right;width:70px;' name='rabatvarenr' value='$rabatvarenr'></td></tr>\n";
		print "<tr><td colspan='6'><hr></td></tr>\n";
		print "<tr><td>".findtekst(272,$sprog_id)."</td>\n";
		if ($afd_antal) print "<td title='".findtekst(273,$sprog_id)."'>".findtekst(274,$sprog_id)."<!--Tekst 274--></td>\n";
		if ($moms_antal) print "<td title='".findtekst(285,$sprog_id)."'>".findtekst(286,$sprog_id)."<!--Tekst 286--></td>\n";
		$text=findtekst(276,$sprog_id);
		$title=findtekst(275,$sprog_id);
		print "<td title='$title'><!--Tekst 275-->$text<!--Tekst 276--></td>\n";
		print "<td title='".findtekst(716,$sprog_id)."'><!--Tekst 716-->".findtekst(715,$sprog_id)."<!--Tekst 715--></td>\n";
		print "<td title='".findtekst(722,$sprog_id)."'><!--Tekst 722-->".findtekst(721,$sprog_id)."<!--Tekst 721--></td>\n";
		print "<td title='".findtekst(705,$sprog_id)."'><!--Tekst 705-->".findtekst(704,$sprog_id)."<!--Tekst 704--></td>\n";
		print "<td title='".findtekst(707,$sprog_id)."'><!--Tekst 707-->".findtekst(706,$sprog_id)."<!--Tekst 706--></td>\n";
		print "<td title='".findtekst(726,$sprog_id)."'><!--Tekst 726-->".findtekst(725,$sprog_id)."<!--Tekst 725--></td>\n";
		if (count($bord)>1) print "<td title='".findtekst(755,$sprog_id)."'><!--Tekst 755-->".findtekst(754,$sprog_id)."<!--Tekst 754--></td>\n";
		$text=findtekst(765,$sprog_id);
		$title=findtekst(766,$sprog_id);
		if ($text='Font størrelse') {
			$text='Font str.';
			db_modify("update tekster set tekst='$text' where tekst_id='765'",__FILE__ . " linje " . __LINE__);
		}
		print "<td title='$title'><!--Tekst 766-->$text<!--Tekst 765--></td>\n";
		$text='Omg.bogf.';
		$title='Afmærkes her vil hvert salg blive bogført i finans straks ved salg. Bør kun bruges ved få daglige salg';
		print "<td title='$title'><!--Tekst 766-->$text<!--Tekst 765--></td>\n";

#		print "<tr><td colspan='2' title='".findtekst(765,$sprog_id)."'>".findtekst(765,	$sprog_id)."</td><td title='".findtekst(766,$sprog_id)."'><input class='inputbox' type='text' style='text-align:right;width:25px' name='pfs' value='$pfs'></td></tr>\n";

		print "</tr>\n";
		for($x=0;$x<$kasseantal;$x++) {
			if (!$pfs[$x]) $pfs[$x]=10;
			print "<tr bgcolor=$bgcolor5>";
			$tmp=$x+1;
			print "<td>$tmp</td>";
			if ($afd_antal) {
				print "<td title='".findtekst(273,$sprog_id)."'><SELECT class='inputbox' NAME=afd_nr[$x] title='".findtekst(273,$sprog_id)."'>\n";
				for($y=1;$y<=$afd_antal;$y++) {
					if ($afd[$x]==$afd_nr[$y]) print "<option value='$afd_nr[$y]'>$afd_navn[$y]</option>\n";
				}
				print "<option value='0'></option>";
				for($y=1;$y<=$afd_antal;$y++) {
					if ($afd[$x]!=$afd_nr[$y]) print "<option value='$afd_nr[$y]'>$afd_navn[$y]</option>\n";
				}
-				print "</SELECT></td>";
			}
			if ($moms_antal) {
				print "<td title='".findtekst(273,$sprog_id)."'><SELECT class='inputbox' NAME=moms_nr[$x] title='".findtekst(273,$sprog_id)."'>\n";
				for($y=1;$y<=$moms_antal;$y++) {
					if ($moms[$x]==$moms_nr[$y]) print "<option value='$moms_nr[$y]'>$moms_navn[$y]</option>\n";
				}
				print "<option value='0'></option>";
				for($y=1;$y<=$moms_antal;$y++) {
					if ($moms[$x]!=$moms_nr[$y]) print "<option value='$moms_nr[$y]'>$moms_navn[$y]</option>\n";
				}
-				print "</SELECT></td>\n";
			}
			print "<td><input class='inputbox' type='text' style='text-align:right;width:50px;' name='kassekonti[$x]' value='$kassekonti[$x]'></td>\n";
			print "<td><input class='inputbox' type='text' style='text-align:right;width:50px;' name='mellemkonti[$x]' value='$mellemkonti[$x]'></td>\n";
			print "<td><input class='inputbox' type='text' style='text-align:right;width:50px;' name='diffkonti[$x]' value='$diffkonti[$x]'></td>\n";
			if (!$printer_ip[$x])$printer_ip[$x]='localhost';
			print "<td><input class='inputbox' type='text' style='text-align:right;width:70px;' name='printer_ip[$x]' value='$printer_ip[$x]'></td>\n";
			print "<td align='center'><input class='inputbox' type='text' style='text-align:right;width:70px;' name='terminal_ip[$x]' value='$terminal_ip[$x]'></td>\n";
			print "<td align='center'><input class='inputbox' type='text' style='text-align:right;width:70px;' name='koekkenprinter[$x]' value='$koekkenprinter[$x]'></td>\n";
			if (count($bord)>1) {
				print "<td align='center'><select class='inputbox' style='width:70px;' name='bordvalg[$x]'>\n";
				if ($bordvalg[$x]) {#print "<option value='$bordvalg[$x]'>$bordvalg[$x] $bord[$x]</option>";
					for ($y=1;$y<=count($bord);$y++) {
						$b=$y+1;
						if ($y==$bordvalg[$x]) print "<option value='$y'>$b $bord[$y]</option>\n";
					}
				}
				print "<option value=''></option>\n";
				for ($y=1;$y<=count($bord);$y++) {
 					$b=$y+1;
					if ($y!=$bordvalg[$x]) print "<option value='$y'>$b $bord[$y]</option>\n";
				}
				print "</select></td>\n";
			}
			print "<td align='center'><input class='inputbox' type='text' style='text-align:right;width:50px' name='pfs[$x]' value='$pfs[$x]'></td>\n";
			print "<td align='center'><input type='checkbox' name='postEachSale[$x]' $postEachSale[$x]></td>\n";
			print "</tr>";
			for ($y=0;$y<count($ValutaKode);$y++) {
					print "<tr><td colspan=\"2\"><input type='hidden' name='ValutaKode[$y]' value='$ValutaKode[$y]'>Konti for $ValutaKode[$y]</td>\n";
					print "<td><input class='inputbox' type='text' style='text-align:right;width:50px;' name='ValutaKonti[$x][$y]' value='".$ValutaKonti[$y][$x]."'></td>\n";
					print "<td><input class='inputbox' type='text' style='text-align:right;width:50px;' name='ValutaMlKonti[$x][$y]' value='".$ValutaMlKonti[$y][$x]."'></td>\n";
					print "<td><input class='inputbox' type='text' style='text-align:right;width:50px;' name='ValutaDifKonti[$x][$y]' value='".$ValutaDifKonti[$y][$x]."'></td>\n";
			}
		}
	}
	print "</tbody></table></td></tr>";
	print "<tr><td><hr></td></tr>\n";
	print "<tr><td><table><tbody>";
	print "<tr><td colspan= '2' title='".findtekst(279,$sprog_id)."'><!--Tekst 726-->".findtekst(280,$sprog_id)."<!--Tekst 280-->&nbsp;<input class='inputbox' type='text' style='text-align:right' size='1' name='kortantal' value='$kortantal'></td></tr>\n";
	if ($kortantal) {
		print "<td title='".findtekst(859,$sprog_id)."'><!--Tekst 859-->".findtekst(862,$sprog_id)."<!--Tekst 862--></td>\n";
		print "<td title='".findtekst(281,$sprog_id)."'><!--Tekst 281-->".findtekst(283,$sprog_id)."<!--Tekst 283--></td>\n";
		print "<td align='center' title='".findtekst(282,$sprog_id)."'><!--Tekst 282-->".findtekst(284,$sprog_id)."<!--Tekst 284--></td>\n";
		print "<td align='center' title='".findtekst(711,$sprog_id)."'><!--Tekst 711-->".findtekst(710,$sprog_id)."<!--Tekst 710--></td>\n";
		print "<td align='center' title='".findtekst(855,$sprog_id)."'><!--Tekst 855-->".findtekst(854,$sprog_id)."<!--Tekst 854--></td>\n"; #20181029
		print "<td align='center' title='".findtekst(857,$sprog_id)."'><!--Tekst 855-->".findtekst(856,$sprog_id)."<!--Tekst 856--></td>\n"; #20181029
		print "<td align='center' title='".findtekst(861,$sprog_id)."'><!--Tekst 861-->".findtekst(860,$sprog_id)."<!--Tekst 860--></td></tr>\n"; #20181215
		print "<tr><td colspan='8'></td></tr>\n";
		for($x=0;$x<$kortantal;$x++) {
			($enabled[$x])?$enabled[$x]='checked':$enabled[$x]=''; // 20181215
			($betalingskort[$x])?$betalingskort[$x]='checked':$betalingskort[$x]=''; // 20131210
			($gavekort[$x])?$gavekort[$x]='checked':$gavekort[$x]=''; // 20181029
			print "<tr bgcolor=$bgcolor5>";
			$tmp=$x+1;
//			print "<td>$tmp</td>\n";
			print "<td title='".findtekst(859,$sprog_id)."'><!--Tekst 859-->";
			print "<input class='inputbox' type='text' style='text-align:right;width:20px;' name='kortno[$x]' value='$tmp'></td>\n";
			print "<td title='".findtekst(281,$sprog_id)."'>";
			print "<input class='inputbox' type='text' style='text-align:left;width:120px;' name='korttyper[$x]' value='$korttyper[$x]'></td>\n";
			print "<td title='".findtekst(282,$sprog_id)."'>";
			print "<input class='inputbox' type='text' style='text-align:right;width:120px;' name='kortkonti[$x]' value='$kortkonti[$x]'></td>\n";
			print "<td title='".findtekst(711,$sprog_id)."' align='center'>";
			print "<input class='inputbox' type='checkbox' style='text-align:right' name='betalingskort[$x]' $betalingskort[$x]></td>\n"; #20131210
			print "<td title='".findtekst(855,$sprog_id)."' align='center'>";
			print "<input class='inputbox' type='checkbox' style='text-align:right' name='gavekort[$x]' $gavekort[$x]></td>\n"; #20181029
			if ( $gavekort[$x]==="checked" ) {
				print "<td title='".findtekst(857,$sprog_id)."'>";
				print "<input class='inputbox' type='text' style='text-align:left' name='gavekorttekst[$x]' value='$gavekorttekst[$x]'></td>\n"; #20181029
			} else {
				print "<td title='".findtekst(858,$sprog_id)."'>";
				print "<input class='inputbox' type='text' style='text-align:left;background:".$bgcolor5."' name='gavekorttekst[$x]' value='$gavekorttekst[$x]' disabled='disabled'></td>\n"; #20181029
			}
			print "<td title='".findtekst(861,$sprog_id)."' align='center'><!--Tekst 861-->";
			print "<input class='inputbox' type='checkbox' style='text-align:right' name='enabled[$x]' $enabled[$x]></td></tr>\n"; // 20181215
		}
		$bet_term=NULL;
		for ($x=0;$x<count($terminal_ip);$x++) {
			if ($terminal_ip[$x]) $bet_term=1; #Så er der betalinggsterminal på min 1. kasse.
		}
		if ($bet_term) {
			$tmp++;
			print "<tr bgcolor=$bgcolor5>";
			print "<td>$tmp</td>\n";
			print "<td title='".findtekst(713,$sprog_id)."'>".findtekst(712,$sprog_id)."</td>\n";
			print "<td title='".findtekst(713,$sprog_id)."'><input class='inputbox' type='text' style='text-align:right' size='5' name='div_kort_kto' value='$div_kort_kto'></td>\n";
			print "<td title='".findtekst(713,$sprog_id)."' align='center'><INPUT DISABLED='disabled' class='inputbox' type='checkbox' style='text-align:right' checked></td></tr>\n";
		}
	}
	print "</tbody></table></td></tr>";
	print "<tr><td><hr></td></tr>\n";
	print "<tr><td><table><tbody>";
	# 20140508 ->
	$bordantal=count($bord);
	print "<tr><td title='".findtekst(673,$sprog_id)."'>".findtekst(674,$sprog_id)."</td><td><input class='inputbox' type='text' style='text-align:right' size='1' name='bordantal' value='$bordantal'></td></tr>\n";
	if ($bordantal) {
		print "<tr><td></td><td title='".findtekst(675,$sprog_id)."'>".findtekst(676,$sprog_id)."</td></tr>\n";
		print "<tr><td colspan='6'></td></tr>\n";
		for($x=0;$x<$bordantal;$x++) {
			print "<tr bgcolor=$bgcolor5>";
			$tmp=$x+1;
			print "<td>$tmp</td>\n";
			print "<td title='".findtekst(675,$sprog_id)."'><input class='inputbox' type='text' style='text-align:left' size='15' name='bord[$x]' value='$bord[$x]'></td></tr>\n";
		}
	}
	print "</tbody></table></td></tr>";
	print "<tr><td><hr></td></tr>\n";
	print "<tr><td><table><tbody>";
	# <- 20140508
	print "<tr><td colspan='2' title='".findtekst(453,$sprog_id)."'>".findtekst(454,$sprog_id)."</td><td title='".findtekst(453,$sprog_id)."'><input class='inputbox' type='checkbox' name='straksbogfor' $straksbogfor></td></tr>\n";
	print "<tr><td colspan='2' title='".findtekst(456,$sprog_id)."'>".findtekst(457,$sprog_id)."</td><td title='".findtekst(456,$sprog_id)."'><input class='inputbox' type='checkbox' name='udskriv_bon' $udskriv_bon></td></tr>\n";
	print "<tr><td colspan='2' title='".findtekst(458,$sprog_id)."'>".findtekst(459,$sprog_id)."</td><td title='".findtekst(458,$sprog_id)."'><input class='inputbox' type='checkbox' name='vis_hurtigknap' $vis_hurtigknap></td></tr>\n";
	print "<tr><td colspan='2' title='".findtekst(460,$sprog_id)."'>".findtekst(461,$sprog_id)."</td><td title='".findtekst(460,$sprog_id)."'><input class='inputbox' type='checkbox' name='vis_kontoopslag' $vis_kontoopslag></td></tr>\n";
	print "<tr><td colspan='2' title='".findtekst(464,$sprog_id)."'>".findtekst(465,$sprog_id)."</td><td title='".findtekst(464,$sprog_id)."'><input class='inputbox' type='checkbox' name='vis_indbetaling' $vis_indbetaling></td></tr>\n";
	print "<tr><td colspan='2' title='".findtekst(734,$sprog_id)."'>".findtekst(735,$sprog_id)."</td><td title='".findtekst(744,$sprog_id)."'><input class='inputbox' type='checkbox' name='vis_saet' $vis_saet></td></tr>\n";
	print "<tr><td colspan='2' title='".findtekst(462,$sprog_id)."'>".findtekst(463,$sprog_id)."</td><td title='".findtekst(462,$sprog_id)."'><input class='inputbox' type='text' style='text-align:right;width:25px' name='timeout' value='$timeout'></td></tr>\n";
	print "<tr><td colspan='2' title='".findtekst(701,$sprog_id)."'>".findtekst(700,$sprog_id)."</td><td title='".findtekst(701,$sprog_id)."'><input class='inputbox' type='text' style='text-align:right;width:70px' name='kasseprimo' value='$kasseprimo'></td></tr>\n";
	print "<tr><td colspan='2' title='".findtekst(837,$sprog_id)."'>".findtekst(838,$sprog_id)."</td><td title='".findtekst(837,$sprog_id)."'><input class='inputbox' type='checkbox' name='udtag0' $udtag0></td></tr>\n";
	print "<tr><td colspan='2' title='".findtekst(703,$sprog_id)."'>".findtekst(702,$sprog_id)."</td><td title='".findtekst(703,$sprog_id)."'><input class='inputbox' type='checkbox' name='optalassist' $optalassist></td></tr>\n";
	print "<tr><td colspan='2' title='".findtekst(839,$sprog_id)."'>".findtekst(840,$sprog_id)."</td><td title='".findtekst(839,$sprog_id)."'><input class='inputbox' type='checkbox' name='brugervalg' $brugervalg></td></tr>\n";
	print "<tr><td colspan='2' title='".findtekst(848,$sprog_id)."'>".findtekst(847,$sprog_id)."</td>";
	print "<td title='".findtekst(848,$sprog_id)."'><input class='inputbox' type='checkbox' name='kundedisplay' $kundedisplay></td></tr>\n";
	$title=findtekst(863,$sprog_id);
	$text=findtekst(864,$sprog_id);
	print "<tr><td colspan='2' title='$title'>$text</td>";
	print "<td title='$title'><input class='inputbox' type='checkbox' name='change_cardvalue' $change_cardvalue></td></tr>\n";
	$text = "Deaktiver bonprint";
	$title = "Afmærk, hvis bonprint skal deaktiveres. Salg vil stadig blive registreret og bogført.";
	print "<tr><td colspan='2' title='$title'>$text</td>";
	print "<td title='$title'><input class='inputbox' type='checkbox' name='deactivateBonprint' $deactivateBonprint></td></tr>\n";
#	print "<tr><td colspan='2' title='".findtekst(765,$sprog_id)."'>".findtekst(765,$sprog_id)."</td><td title='".findtekst(766,$sprog_id)."'><input class='inputbox' type='text' style='text-align:right;width:25px' name='pfs' value='$pfs'></td></tr>\n";
	print "<tr><td colspan='2'><br></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input class='button green medium' type=submit accesskey='g' value='Gem/opdat&eacute;r' name='submit'></td>\n";
	print "</form>\n";
	print "<tr><td><a href=posmenuer.php><input class='button blue medium' type='button' value='Ret POS menuer'></a></td></tr>\n";
	print "</tbody></table></td></tr>";
	print "<tr><td><hr></td></tr>\n";

} # endfunc pos
#####################################################
function testftp($box1,$box2,$box3,$box4,$box5,$box6) {
 	global $db;
	global $exec_path;
	if (!$exec_path) $exec_path="\usr\bin";

	if ($box6) {
		$fp=fopen("../temp/$db/ftpscript1","w");
		if ($fp) {
			fwrite ($fp,"set confirm-close no\nmkdir ".$_SERVER['SERVER_NAME']."\ncd ".$_SERVER['SERVER_NAME']."\nmkdir $db\nbye\n");
		}
		fclose($fp);

		$tmp=$_SERVER['SERVER_NAME']."/";
		$tmp=str_replace($tmp,'',$box1);
		$tmp1=$db."/";
		$tmp=str_replace($tmp1,'',$tmp);
		$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$tmp." < ftpscript1 > ftplog1\nrm testfil.txt\n";
		system ($kommando);
	}
	$fp=fopen("../temp/$db/testfil.txt","w");
	if ($fp) {
		fwrite ($fp,"testfil fra saldi\n");
	}
	fclose($fp);
	$fp=fopen("../temp/$db/ftpscript2","w");
	if ($fp) {
		fwrite ($fp, "mkdir $box4\nmkdir $box5\ncd $box4\nput testfil.txt\nbye\n");
	}
	fclose($fp);
	$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":'".$box3."'@".$box1." < ftpscript2 > ftplog2\nrm testfil.txt\n"; #rm testfil.txt\n
	system ($kommando);
	$fp=fopen("../temp/$db/ftpscript3","w");
	if ($fp) {
		fwrite ($fp, "get testfil.txt\ndel testfil.txt\nbye\n");
	}
	fclose($fp);
	$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":'".$box3."'@".$box1."/".$box4." < ftpscript3 > ftplog3\n"; #rm ftpscript\nrm ftplog\n";
	system ($kommando);
	($box6)?$tmp="Dokumentserver":$tmp="FTP";
	if (file_exists("../temp/$db/testfil.txt")) print "<BODY onLoad=\"JavaScript:alert('$tmp tilg&aelig;ngelig')\">";
	else print "<BODY onLoad=\"JavaScript:alert('$tmp ikke tilg&aelig;ngelig')\">";
}

?>
