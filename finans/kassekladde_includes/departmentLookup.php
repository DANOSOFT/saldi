<?php
function departmentLookup($fokus,$x) {

	global $afd, $ansat;
	global $belob, $beskrivelse, $bgcolor, $bgcolor2, $bgcolor5, $bilag;
	global $charset;
	global $d_type, $dato, $debet;
	global $faktura, $fgcolor;
	global $id;
	global $kladde_id, $k_type, $kredit;
	global $menu, $momsfri;
	global $projekt;
	global $regnaar;
	global $sprog_id;
	global $top_bund;
	global $valuta;

	$title = "Afdeling opslag";

	if (!isset ($lobenr[$x])) $lobenr[$x] = null;
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
		print "<div id=\"header\">";
		print "<div class=\"headerbtnLft headLink\"><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";
		print "<div class=\"headerTxt\">$title</div>";
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
		print "</div>";
		print "<div class='content-noside'>";
		print  "<table class='dataTable' border='0' cellspacing='1' align='center';>";
	} else {
			print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
			print"<td width=\"10%\" $top_bund><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=L>".findtekst(30, $sprog_id)."</a></td>";
			print"<td width=\"80%\" $top_bund>Afd. opslag</td>";
			print"<td width=\"10%\" $top_bund align=\"right\"><br></td>";
			print"</tbody></table>";
			print"</td></tr>\n";
	}
?>
		<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=20%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Afdeling nr.</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Beskrivelse</a></b></td>
	</tr>
	<?php
	$query = db_select("select kodenr, beskrivelse from grupper where art='AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$kodenr=trim($row['kodenr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;$color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;$color='#000000';}
		print "<tr bgcolor=$linjebg>";
		print "<td><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$kodenr&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>  $kodenr</a><br></td>";
		print "<td>  $row[beskrivelse]<br></td>";
		print "</tr>\n";

	}
	exit;
} #endfunc afd_opslag
?>
