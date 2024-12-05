<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/debitorLookup.php--- lap 4.0.8 --- 2023-06-26 ---
// LICENS
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
function debitorLookup($find,$sort,$fokus,$opslag_id,$id,$kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$faktura,$belob,$momsfri,$afd,$projekt,$ansat,$valuta,$forfaldssato,$betalings_id,$lobenr) {

	global $bgcolor,$bgcolor2,$bgcolor5;
	global $fgcolor;
	global $linjebg;
	global $menu;
	global $sprog_id;
	global $top_bund;

	if (!isset ($x)) $x = NULL;
	if (!isset ($charset)) $charset = NULL;

	$beskrivelse=urlencode(stripslashes($beskrivelse));
#	$beskrivelse=(str_replace("&","!og!",$beskrivelse));
#	$beskrivelse=(str_replace("'","!apostrof!",$beskrivelse));
	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
	$faktura=urlencode(trim(stripslashes($faktura)));
	$belob=trim($belob);
	if ($menu=='T') {
		include_once '../includes/top_menu.php';
		include_once '../includes/top_header.php';
		print "<div id=\"header\"> 
		<div class=\"headerbtnLft\"><a href='../finans/kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd' class=\"button red small left\" accesskey=L>".findtekst(30, $sprog_id)."</a></div>
		<span class=\"headerTxt\">Debitorliste</span>";     
		print "<div class=\"headerbtnRght\"></div>";       
		print "</div><!-- end of header -->
			<div class=\"maincontentLargeHolder\">\n";
			print  "<table class='dataTable2' border='0' cellspacing='1' align='center';>";
	} else {
	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund><a href='../finans/kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd' accesskey=L>".findtekst(30, $sprog_id)."</a></td>";
	print"<td width=\"80%\" $top_bund>Debitorliste</td>";
	print "<td width=\"10%\" $top_bund align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"JavaScript:window.open('../debitor/debitorkort.php?returside=../includes/luk.php', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,resizable=yes');\"><a href='../finans/kassekladde.php?sort=$sort&funktion=debitorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'><u>".findtekst(39, $sprog_id)."</u></a></td>";
#	else print"<td width=\"10%\" $top_bund align=\"right\"><a href=../debitor/debitorkort.php?returside=../finans/kasseklade.php&id=$id accesskey=N>Ny</a></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
	print"<tr><td valign=\"top\">";
	print"<table class='dataTable2' cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	}
	print"<tbody><tr>";
	print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=kontonr&funktion=debitorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Kundenr</a></b></td>";
	print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=firmanavn&funktion=debitorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Navn</a></b></td>";
	print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=addr1&funktion=debitorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Adresse</a></b></td>";
	print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=addr2&funktion=debitorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Adresse2</a></b></td>";
	print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=postnr&funktion=debitorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Postnr</a></b></td>";
	print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=bynavn&funktion=debitorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>By</a></b></td>";
	print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=kontakt&funktion=debitorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Kontaktperson</a></b></td>";
	print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=tlf&funktion=debitorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Telefon</a></b></td>";
	print" </tr>\n";

	 $sort = $_GET['sort'];
	 if (!$sort) {$sort = "firmanavn";}
	if ($find && $find!='*') $qtxt="select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'D' and firmanavn like '%$find%' order by $sort";
	else $qtxt="select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'D' order by $sort";

	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
#	$query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'D' order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if (!$beskrivelse){
			$beskr=htmlentities(stripslashes($row['firmanavn']),ENT_QUOTES,$charset);
		}
		else {$beskr=$beskrivelse;}
		$kontonr=trim($row['kontonr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;$color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;$color='#000000';}
		print "<tr bgcolor=$linjebg>";
		if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty"))){$tmp="<a href='../finans/kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$kontonr&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>";}
		else {$tmp="<a href='../finans/kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kontonr&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>";}
		print "<td> $tmp $row[kontonr]</a><br></td>";
		print "<td> $tmp ". stripslashes($row['firmanavn']) ."</a><br></td>";
		print "<td> $tmp $row[addr1]</a><br></td>";
		print "<td> $tmp $row[addr2]</a><br></td>";
		print "<td> $tmp $row[postnr]</a><br></td>";
		print "<td> $tmp $row[bynavn]</a><br></td>";
		print "<td> $tmp $row[kontakt]</a><br></td>";
		print "<td> $tmp $row[tlf]</a><br></td>";
		print "</tr>\n";
	}

	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
?> 
