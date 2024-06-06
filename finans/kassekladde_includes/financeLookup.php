<?php
if (!function_exists('financeLookup')) {
function financeLookup($find,$sort,$fokus,$opslag_id,$id,$kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$faktura,$belob,$momsfri,$afd,$projekt,$ansat,$valuta,$forfaldsdato,$betal_id,$lobenr) {

#	global $afd,$ansat;
	global $bgcolor,$bgcolor2,$bgcolor5;
	global $charset;
	global $fgcolor;
	global $menu;
	global $regnaar;
	global $sprog_id;
	global $top_bund;

	$linjebg=NULL;
	$spantekst=NULL;

	if (!isset($lobenr))$lobenr=NULL;
	if (!isset ($datodato)) $datodato = 0;
	if (!isset ($x)) $x = NULL;
#	$beskrivelse=htmlentities($beskrivelse,ENT_QUOTES,$charset);
	$beskrivelse=urlencode($beskrivelse);
	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
#	$faktura=htmlentities($faktura,ENT_QUOTES,$charset);
	$faktura=urlencode($faktura);
	$belob=trim($belob);
	if ($bilag=="-") $bilag="0"; #<- 2009.05.14
	if ($menu=='T') {
		include_once '../includes/top_menu.php';
		include_once '../includes/top_header.php';
		print "<div id=\"header\">
		<div class=\"headerbtnLft\"><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$datodato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr' class=\"button red small left\" accesskey=L>".findtekst(30, $sprog_id)."</a></div>
		<span class=\"headerTxt\">Finansopslag</span>";
		print "<div class=\"headerbtnRght\"></div>";
		print "</div><!-- end of header -->
			<div class=\"maincontentLargeHolder\">\n";
			print  "<table class='dataTable2' border='0' cellspacing='1' align='center';>";
	} else {
	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id' accesskey=L>".findtekst(30, $sprog_id)."</a></td>\n";
	print"<td width=\"80%\" $top_bund>Finansopslag</td>\n";
	print"<td width=\"10%\" $top_bund align=\"right\"><br></td>\n";
	print"</tbody></table>";
	print"</td></tr>\n";
	}
	?>
	<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=10%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Kontonr</b></td>
		<td width=35%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Beskrivelse</a></b></td>
		<td align="center"><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Moms</a></b></td>
		<td align="center"><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Genvej</a></b></td>
		<td align="right"><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Saldo</a></b></td>
		</tr>
		<tr><td colspan="5"><hr></td></tr>
		<?php
		$i=0;
		$q = db_select("select * from grupper",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (substr(trim($r['art']),1,1)=='M') {
				$i++;
				$moms[$i]=$r['kode'].$r['kodenr'];
				$momstekst[$i]=$r['beskrivelse'];
			}
			$momsantal=$i;
	}
	$y=0;
	$kontonr = array();
	$qtxt = "select kontotype, kontonr, beskrivelse, moms, genvej, lukket, saldo from kontoplan where ";
	$qtxt.= "(kontotype ='D' or kontotype ='S'or kontotype ='H') and regnskabsaar='$regnaar' order by kontonr";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['lukket']!='on') {
			if (!$find || (is_array($find) && in_array ($r['kontonr'],$find)) || $r['kontotype'] == 'H') {
				$momskode[$y]=$r['moms'];
				$kontotype[$y]=trim($r['kontotype']);
				$kontonr[$y]=trim($r['kontonr']);
				$kontobeskrivelse[$y]=trim(stripslashes($r['beskrivelse']));
				$genvej[$y]=trim($r['genvej']);
				$saldo[$y]=trim($r['saldo']);
				$y++;
			}
		}
	}
	for($y=0;$y<count($kontonr);$y++) {
		($find && ($kontotype[$y]=='H' && $kontotype[$y+1]== 'H'))?$vis=0:$vis=1;
		if ($vis) {
			if ($momskode[$y]){
				for ($i=1;$i<=$momsantal;$i++){
					if (!isset($momstekst[$i])) $momstekst[$i]=NULL; if (!isset($moms[$i])) $moms[$i]=NULL;

					if ($moms[$i]==$momskode[$y]) $spantekst=$momstekst[$i];
				}
			}
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor;$color='#000000';}
			elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;$color='#000000';}
			print "<tr bgcolor=$linjebg>";
#			$faktura[$x]=trim($faktura[$x]);
#			$beskrivelse[$x]=urlencode(trim($beskrivelse[$x]));

			if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty")))	{
				$href="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$kontonr[$y]&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr' accesskey=$genvej[$y]>";
			}
			if ((strstr($fokus,"kred"))||(strstr($fokus,"k_ty"))) {
			$href="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kontonr[$y]&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr' accesskey=$genvej[$y]>";
			}
			if ($kontotype[$y]=='H') print "<td colspan=\"5\"><b>$kontobeskrivelse[$y]</font></b></td>\n";
			else {
				print "<td><span title='".findtekst(1551, $sprog_id)."'>$href $kontonr[$y]</font></a><span></td>\n";
				print "<td><span title='".findtekst(1551, $sprog_id)."'>$href $kontobeskrivelse[$y]</font></a><span></td>\n";
				print "<td align=\"center\"><span title='$spantekst'>$momskode[$y]</font></span></td>\n";
				print "<td align=\"center\"><span title='".findtekst(1551, $sprog_id)."'>$href $genvej[$y]</font></a><span></td>\n";
				print "<td align=\"right\">".dkdecimal($saldo[$y],2)."</font></td>\n";
			}
			print "</tr>\n";
		}
	}
	exit;
}
}
?> 
