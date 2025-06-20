<?php
function openpost($find,$sort,$fokus,$opslag_id,$id,$kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$faktura,$belob,$momsfri,$afd,$projekt,$ansat,$valuta,$forfaldssato,$betailngs_id,$lobenr){
# ($find,$sort,$fokus,$opslag_id,$id,$kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$faktura,$belob,$momsfri,$afd,$projekt,$ansat,$valuta,$forfaldssato,$betailngs_id,$lobenr) {
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $top_bund;
	global $charset;
	global $menu;

	$linjebg=NULL;

	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
#	if ($faktura) $faktura=htmlentities($faktura,ENT_QUOTES,$charset);
	if ($faktura) $faktura=urlencode($faktura);
	$belob=str_replace("-","",trim($belob));
#	if ($beskrivelse) $beskrivelse=htmlentities($beskrivelse,ENT_QUOTES,$charset);
	if ($beskrivelse) $beskrivelse=urlencode($beskrivelse);

	if (!isset($x))$x=NULL;
	if (!isset($lobenr))$lobenr=NULL;
	if ($bilag=="-") $bilag=0;

#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td height = \"25\" align=\"center\" valign=\"top\">";
if ($menu=='T') {
	include_once '../includes/top_menu.php';
	include_once '../includes/top_header.php';
	print "<div id=\"header\"> 
	<div class=\"headerbtnLft\"><a href='../finans/kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find' class=\"button red small left\" accesskey=L>Luk</a></div>
	<span class=\"headerTxt\">Åbenposter</span>";     
	print "<div class=\"headerbtnRght\"></div>";       
	print "</div><!-- end of header -->
		<div class=\"maincontentLargeHolder\">\n";
		print  "<table class='dataTable2' border='0' cellspacing='1' align='center';>";
} else {
	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund><a href='../finans/kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find' accesskey=L>Luk</a></td>";
	print"<td width=\"80%\" $top_bund>&Aring;benposter</td>";
	print"<td width=\"10%\" $top_bund align=\"right\"><br></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
}
	print"<tbody><tr>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=konto_nr&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Kundenr</a></b></td>";
#	print"<td><b> <a 'href=kassekladde.php?sort=konto_nr&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Kundenr</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=firmanavn&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Navn</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=faktnr&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Fakturanr</a></b></td>";
	print"<td><b> <a href='../finans/kassekladde.php?sort=transdate&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Dato</a></b></td>";
	print"<td style=\"text-align:right\"><b> <a href='../finans/kassekladde.php?sort=amount&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find'>Bel&oslash;b</a></b></td>";
	print" </tr>\n";


	$sort = $_GET['sort'];
#	if ($sort=="transdate,bilag") $sort=NULL; # konflikter med sortering fra kassekladde.
	if (!$sort) {$sort = 'konto_nr';}

	$x=0;
	$qtxt="select kontonr, id, firmanavn, art, gruppe from adresser order by firmanavn";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($kredit==$r['kontonr'] && $k_type==$r['art']) $ktoId=$r['id']; #20120809
		if ($debet==$r['kontonr'] && $d_type==$r['art']) $ktoId=$r['id']; #20120809
		$konto_id[$x]=$r['id'];
		$kontonr[$x]=$r['kontonr'];
		$firmanavn[$x]=stripslashes($r['firmanavn']);
		$art[$x]=$r['art'];
		$grp=$r['gruppe'];
		$grpArt=$art[$x]."G";
		$r2 = db_fetch_array(db_select("SELECT box5 FROM grupper WHERE art ='$grpArt' AND kodenr	= '$grp'",__FILE__ . " linje " . __LINE__));
		$modkonto[$x]=$r2['box5'];
		$x++;
	}
# -> 2009.05.04
	$amount=usdecimal($belob,2);
	$tmp1=$amount-0.005;$tmp2=$amount+0.005;$tmp3=($amount*-1)-0.005;$tmp4=($amount*-1)+0.005;
	$kriterie="where udlignet !='1'";
	if ($faktura) $kriterie=$kriterie." and faktnr ='".$faktura."'";
	
	if ((($d_type=='K')|| ($d_type=='D')) and ($debet)) {
		if ($ktoId) $kriterie=$kriterie." and konto_id='".$ktoId."'"; #20120809
#		else $kriterie=$kriterie." and konto_nr='".$debet."'"; #20120809
		if ($amount != 0) $kriterie=$kriterie." and amount >= '".$tmp3."' and amount < '".$tmp4."'";
	}	elseif ((($k_type=='K')|| ($k_type=='D')) and ($kredit)) {
		if ($ktoId) $kriterie=$kriterie." and konto_id='".$ktoId."'"; #20120809
#		else $kriterie=$kriterie." and konto_nr='".$kredit."'"; #20120809
		if ($amount != 0) $kriterie=$kriterie." and amount >= '".$tmp1."' and amount < '".$tmp2."'";
	}	elseif ($amount != 0) {
		$kriterie=$kriterie." and ((amount >= '".$tmp1."' and amount <= '".$tmp2."') or (amount >= '".$tmp3."' and amount <= '".$tmp4."'))";
	}
	if ($sort=="firmanavn") $sort="konto_nr";
	$qtxt="select id, konto_id, konto_nr, faktnr, transdate, amount,valuta from openpost $kriterie order by $sort";
	$x=0;
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			for ($y=0;$y<count($konto_id);$y++) {
			if ($r['konto_id']==$konto_id[$y]) {
				$accountId[$x]=$konto_id[$y];
				$accountNo[$x]=$kontonr[$y];
				$accountName[$x]=$firmanavn[$y];
				$invoiceNo[$x]=$r['faktnr'];
				$accountType[$x]=$art[$y];
				$offsetAccount[$x]=$modkonto[$y];
				$openAamount[$x]=$r['amount'];
				$currency[$x]=$r['valuta'];
				$transdate[$x]=$r['transdate'];
				$x++;
			}
		}
	}
	for ($x=0;$x<count($accountId);$x++) {
		if (!$beskrivelse) {
#			$beskr=htmlentities($accountName,ENT_QUOTES,$charset);
#			if (!$faktnr && $faktura) $faktnr=$faktura;
			$beskr="$accountName[$x] - $invoiceNo[$x]";
		} else $beskr=$beskrivelse;

		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;}

		print "<tr bgcolor=\"$linjebg\">";

		if ($openAamount[$x]<0) {
			$belob=dkdecimal($openAamount[$x]*-1,2);
			if (!$kredit) {$kredit=$offsetAccount[$x];}
			$tmp="<a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$accountType[$x]&debet=$accountNo[$x]&k_type=$k_type&kredit=$kredit&faktura=$invoiceNo[$x]&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$currency[$x]&lobenr=$lobenr&find=$find'>";
			$belob=dkdecimal($openAamount[$x],2);
		}
		else 	{
			$belob=dkdecimal($openAamount[$x],2);
			if (!$debet) {$debet=$offsetAccount[$x];}
			$tmp="<a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$debet&k_type=$accountType[$x]&kredit=$accountNo[$x]&faktura=$invoiceNo[$x]&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$currency[$x]&lobenr=$lobenr&find=$find'>";
		}
			print "<td> $tmp $accountNo[$x]</a><br></td>";
#		print "<td> $r[id]<br></td>";
		#print "<td> $r[konto_nr]<br></td>";
		print "<td> $tmp $accountName[$x]</a><br></td>";
		print "<td> $tmp $invoiceNo[$x]</a><br></td>";
		print "<td> $tmp ".dkdato($transdate[$x])."</a><br></td>";
		print "<td style=\"text-align:right\"> $tmp $belob</a><br></td>";
		print "</tr>\n";
	}

	print "</tbody></table></td></tr></tbody></table>";
	exit;
}

?>
