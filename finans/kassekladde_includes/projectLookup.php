<?php
function projectLookup($fokus,$x) {

	include("../includes/topline_settings.php");

	global $afd, $ansat, $belob, $beskrivelse, $bgcolor, $bgcolor2, $bgcolor5, $bilag;
	global $charset;
	global $dato, $d_type, $debet;
	global $faktura, $fgcolor;
	global $id;
	global $k_type, $kladde_id, $kredit;
	global $menu, $momsfri;
	global $projekt;
	global $regnaar;
	global $top_bund;
	global $sprog_id;
	global $valuta;

	$title = findtekst('2594|Projektopslag', $sprog_id);

	if (!isset ($lobenr[$x])) $lobenr[$x] = 0;
	if (!isset ($linjebg)) $linjebg = null;

#	$beskrivelse[$x]=htmlentities($beskrivelse[$x],ENT_QUOTES,$charset);
	$beskrivelse[$x]=urlencode($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=htmlentities($faktura[$x],ENT_QUOTES,$charset);
	$belob[$x]=trim($belob[$x]);
	if ($menu=='T') {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id='header'>";
		print "<div class='headerbtnLft headLink'><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst('30|Tilbage', $sprog_id)."</a></div>";
		print "<div class='headerTxt'>$title</div>";
		print "<div class='headerbtnRght headLink'>&nbsp;&nbsp;&nbsp;</div>";
		print "</div>";
		print "<div class='content-noside'>";
		print  "<center><table class='dataTable' border='0' cellspacing='1' align='center';>";
	} else if ($menu=='S') {
		print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";
		print "<td width='10%'><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=L>";
		print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";
		print "<td width='80%' align='center' style='$topStyle'>".findtekst('2594|Projektopslag', $sprog_id)."</td>";
		print "<td width='10%' align='right' style='$topStyle'><br></td>";
		print "</tbody></table>";
		print "</td></tr>\n";
	} else {
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";
	print "<td width='10%' $top_bund><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=L>".findtekst('30|Tilbage', $sprog_id)."</a></td>";
	print "<td width='80%' $top_bund>".findtekst('2594|Projektopslag', $sprog_id)."</td>";
	print "<td width='10%' $top_bund align='right'><br></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
	}
?>
		<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=20%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor?>"><?php echo findtekst('1199|Projektnr.', $sprog_id)?></b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor?>"><?php echo findtekst('914|Beskrivelse', $sprog_id)?></a></b></td>
	</tr>
	<?php
	$query = db_select("select kodenr, beskrivelse from grupper where art='PRJ' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$kodenr=trim($row['kodenr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;$color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;$color='#000000';}
		print "<tr bgcolor=$linjebg>";
		print "<td><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$kodenr&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>  $kodenr</a><br></td>";
		print "<td>  $row[beskrivelse]<br></td>";
		print "</tr>\n";
	}
	exit;
} #endfunc $projectLookup
?>
