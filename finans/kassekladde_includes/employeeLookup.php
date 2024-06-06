<?php
function employeeLookup($fokus,$x) {

	global $afd, $ansat;
	global $belob, $beskrivelse, $bgcolor, $bgcolor2, $bgcolor5, $bilag;
	global $charset;
	global $d_type, $dato, $debet;
	global $faktura, $fgcolor;
	global $id;
	global $k_type, $kladde_id, $kredit;
	global $momsfri;
	global $projekt;
	global $regnaar;
	global $sprog_id;
	global $top_bund;
	global $valuta;

#	$beskrivelse[$x]=htmlentities($beskrivelse[$x],ENT_QUOTES,$charset);
	$beskrivelse[$x]=urlencode($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=htmlentities($faktura[$x],ENT_QUOTES,$charset);
	$belob[$x]=trim($belob[$x]);

	$r=db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$egen_kto_id = $r['id']*1;


	 print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=L>".findtekst(30, $sprog_id)."</a></td>";
	print"<td width=\"80%\" $top_bund>Projekt opslag</td>";
	print"<td width=\"10%\" $top_bund align=\"right\"><br></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
?>
		<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr><td><br></td></tr><tr>
		<td width=20%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Initialer</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Navn</a></b></td>
	</tr><tr><td><br></td></tr>
	<?php
	$query = db_select("select id, navn, initialer from ansatte where konto_id='$egen_kto_id' and lukket!='on' order by posnr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;$color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;$color='#000000';}
		print "<tr bgcolor=$linjebg>";
		print "<td><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&ansat=$row[initialer]&projekt=$projekt[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>  $row[initialer]</a><br></td>";
		print "<td><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&ansat=$row[initialer]&projekt=$projekt[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>  $row[navn]</a><br></td>";
		print "</tr>\n";
	}
	exit;
} #endfunc employeeLookup
?>
