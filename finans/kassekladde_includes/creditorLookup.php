<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/creditorLookup.php--- lap 4.0.8 --- 2023-06-26 ---
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
function creditorLookup($find,$sort,$fokus,$opslag_id,$id,$kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$faktura,$belob,$momsfri,$afd,$projekt,$ansat,$valuta,$forfaldssato,$betailngs_id,$lobenr) {

	echo "F $find<br>";
	
	global $bgcolor,$bgcolor2,$bgcolor5;
	global $charset;
	global $fgcolor;
	global $linjebg;
	global $menu;
	global $sprog_id;
	global $top_bund;
	global $x;
	
	if (!isset ($datodato)) $datodato = 0;

#	$beskrivelse=htmlentities($beskrivelse,ENT_QUOTES,$charset);
	$beskrivelse=urlencode($beskrivelse);
	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
#	$faktura=htmlentities($faktura,ENT_QUOTES,$charset);
	$faktura=urlencode($faktura);
	$belob=trim($belob);
	if ($menu=='T') {
		include_once '../includes/top_menu.php';
		include_once '../includes/top_header.php';
		print "<div id=\"header\"> 
		<div class=\"headerbtnLft\"><a href='../finans/kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find' class=\"button red small left\" accesskey=L>".findtekst(30, $sprog_id)."</a></div> 
		<span class=\"headerTxt\">Kreditorliste</span>";     
		print "<div class=\"headerbtnRght\"></div>";       
		print "</div><!-- end of header -->
			<div class=\"maincontentLargeHolder\">\n";
			print  "<table class='dataTable2' border='0' cellspacing='1' align='center';>";
	} else {
		print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		print"<td width=\"10%\" $top_bund><a href='../finans/kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find' accesskey='L'>".findtekst(30, $sprog_id)."</a></td>";
		print"<td width=\"80%\" $top_bund>Kreditorliste</td>";
		print "<td width=\"10%\" $top_bund align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"JavaScript:window.open('../kreditor/kreditorkort.php?returside=../includes/luk.php', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,resizable=yes');\"><a href='../finans/kassekladde.php?sort=$sort&funktion=creditorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'><u>".findtekst(39, $sprog_id)."</u></a></td>";
		print"</tbody></table>";
		print"</td></tr>\n";
		print"<tr><td valign=\"top\">";
		print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	}
		print"<tbody><tr>";
		print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=kontonr&funktion=creditorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Kreditornr</a></b></td>";
		print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=firmanavn&funktion=creditorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Navn</a></b></td>";
		print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=addr1&funktion=creditorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Adresse</a></b></td>";
		print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=addr2&funktion=creditorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Adresse2</a></b></td>";
		print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=postnr&funktion=creditorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Postnr</a></b></td>";
		print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=bynavn&funktion=creditorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>By</a></b></td>";
		print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=kontakt&funktion=creditorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Kontaktperson</a></b></td>";
		print"<td style='color:$fgcolor'><b> <a href='../finans/kassekladde.php?sort=tlf&funktion=creditorLookup&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Telefon</a></b></td>";
		print" </tr>\n";


	 $sort = $_GET['sort'];
	 if (!$sort) $sort = 'firmanavn';
	if ($find && $find!='*') $qtxt="select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'K' and firmanavn like '%$find%' order by $sort";
	else $qtxt="select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'K' order by $sort";

	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		if (!$beskrivelse){
			$beskr=htmlentities(stripslashes($row['firmanavn']),ENT_QUOTES,$charset);
		}
		else $beskr=$beskrivelse;
		$kontonr=trim($row['kontonr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;$color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;$color='#000000';}
		print "<tr bgcolor=$linjebg>";
		if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty"))){$tmp = "<a href='../finans/kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$kontonr&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>";}
		else {$tmp="<a href='../finans/kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kontonr&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>";}
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
