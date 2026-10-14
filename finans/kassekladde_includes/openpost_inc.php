<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/openpost_inc.php --- ver 5.0.0 --- 2026.10.12 ---
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
// Copyright (c) 2003-2025 saldi.dk ApS
// ----------------------------------------------------------------------
// 20220823 PHR Moved $offsetAccount lookup to reduce lookups
// 20221111 PHR More posts can now be aligned in one step
// 20230604 PHR Added offsetAccount to kredit,
// 20230828 PHR Gets Company name from inviince instead of order.
// 20250509 PHR Fiscal year
// 20250626 PHR '==' replaved by '='
// 20261012 CL/SZ SD-730 "Indsæt" posted to kassekladde.php, which has no handler for it, so the line reloaded from the
//                 database and lost what was typed before "Opslag". It now goes back the way an invoice row's link
//                 does, with the line's values and the ticked invoice numbers; the ticked sum and the difference are
//                 recalculated as boxes are ticked ("Beregn" posted to the same missing handler). The group's offset
//                 account is read with the matched account's own group (it used the last address of the whole list, and
//                 an empty group failed the query), and "Difference" uses text 2396 (3095 is "Slet ansat"). The row links
//                 encode Bilagstekst and the invoice number: a "#" or "&" in the text cut the link and blanked the columns.
//                 They also carry Forfaldsdato and Betalings-id, which kassekladde.php emptied on the way back (CodeRabbit).
//                 (CodeRabbit) Indsæt only when the ticked posts belong to one account, and the lookup finds the invoices of a line
//                 with a;b (split into an IN list, without the amount filter, which no single post of a sum matches).

function openpost($find,$sort,$fokus,$opslag_id,$id,$kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$faktura,$belob,$momsfri,$afd,$projekt,$ansat,$valuta,$forfaldssato,$betailngs_id,$lobenr){
# ($find,$sort,$fokus,$opslag_id,$id,$kladde_id,$bilag,$dato,$beskrivelse,$d_type,$debet,$k_type,$kredit,$faktura,$belob,$momsfri,$afd,$projekt,$ansat,$valuta,$forfaldssato,$betailngs_id,$lobenr) {

	include("../includes/topline_settings.php");

	global $bgcolor, $bgcolor2, $bgcolor5;
	global $charset;
	global $menu;
	global $regnaar;
	global $top_bund;
	global $sprog_id;

	$linjebg=NULL;

	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
	if ($faktura) $faktura=urlencode($faktura);
	$belob=str_replace("-","",trim($belob));
	$amount=usdecimal($belob,2)*1;
#	if ($beskrivelse) $beskrivelse=urlencode($beskrivelse);

	$ktoId = $oppSum = 0;
	$openPostId = array();
	if (!isset($x))$x = NULL;
	if (!isset($lobenr))$lobenr = NULL;
	if ($bilag=="-") $bilag = 0;

	if (isset($_POST['alignThis']) && $_POST['alignThis']) {
	  $alignThis = $_POST['alignThis'];
	  $id      = $_POST['postId'];
		$qtxt    = "select * from kassekladde where id = '$id'";
		$q       = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		$r       = db_fetch_array($q);
		$d_type  = $r['d_type'];
		$debet   = $r['debet'];
		$k_type  = $r['k_type'];
		$kredit  = $r['kredit'];
		$belob   = dkdecimal($r['amount'],2);
	} else $alignThis = array();
print "<center>";
#	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	$lnktxt = "&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&";
	$lnktxt.= "beskrivelse=".urlencode($beskrivelse)."&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura";
	$lnktxt.= "&belob=$belob&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$valuta&lobenr=$lobenr&find=$find";
	$lnktxt.= "&forfaldsdato=".urlencode((string)$forfaldssato)."&betal_id=".urlencode((string)$betailngs_id);
	if ($menu=='T') {
		include_once '../includes/top_menu.php';
		include_once '../includes/top_header.php';
		print "<div id=\"header\"><div class=\"headerbtnLft\">";
		print "<a href='../finans/kassekladde.php?$lnktxt' class=\"button red small left\" accesskey=L>".findtekst('2172|Luk', $sprog_id)."</a></div>";
		print "<span class=\"headerTxt\">".findtekst('441|Åbne poster', $sprog_id)."</span>";
		print "<div class=\"headerbtnRght\"></div>";
		print "</div><!-- end of header --><div class=\"maincontentLargeHolder\">\n";
		print  "<table class='dataTable2' border='0' cellspacing='1' align='center';>";
	} elseif ($menu=='S') {
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";

		print "<td width=\"10%\"><a href='../finans/kassekladde.php?$lnktxt' accesskey=L>
			   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">".findtekst('2172|Luk', $sprog_id)."</button></a></td>";
		print "<td width=\"80%\" style=$topStyle align='center'>".findtekst('441|Åbne poster', $sprog_id)."</td>";
		print "<td width=\"10%\" style=$topStyle align='center' align='right'><br></td>";

		print "</tbody></table>";
		print "</td></tr>\n";
		print "<tr><td valign=\"top\">";
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"800px\" valign = \"top\">";
	} else {
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		print "<td width=\"10%\" $top_bund><a href='../finans/kassekladde.php?$lnktxt' accesskey=L>".findtekst('2172|Luk', $sprog_id)."</a></td>";
		print "<td width=\"80%\" $top_bund>".findtekst('441|Åbne poster', $sprog_id)."</td>";
		print "<td width=\"10%\" $top_bund align=\"right\"><br></td>";
		print "</tbody></table>";
		print "</td></tr>\n";
		print "<tr><td valign=\"top\">";
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"800px\" valign = \"top\">";
}
	print "<tbody >";
	print "<tr>";
	print "<td><b><a href='../finans/kassekladde.php?sort=konto_nr".$lnktxt."'>".findtekst('43|Kontonr.', $sprog_id)."</a></b></td>";
	print "<td><b><a href='../finans/kassekladde.php?sort=firmanavn".$lnktxt."'>".findtekst('138|Navn', $sprog_id)."</a></b></td>";
	print "<td><b><a href='../finans/kassekladde.php?sort=faktnr".$lnktxt."'>".findtekst('828|Fakturanr.', $sprog_id)."</a></b></td>";
	print "<td><b><a href='../finans/kassekladde.php?sort=transdate".$lnktxt."'>".findtekst('438|Dato', $sprog_id)."</a></b></td>";
	print "<td style = 'width:20px'><b><a href='../finans/kassekladde.php?sort=amount".$lnktxt."'>".findtekst('934|Beløb', $sprog_id)."</a></b></td>";
	print " </tr>\n";
	print "<tr>";
	print "<td></td><td>$beskrivelse</td>";
	print "<td></td><td>$dato</td>";
	print "<td style = 'text-align:right'><b>$belob</b></td></tr>";
	print "<tr><td colspan = '5'><hr></td></tr>";
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
		$gruppe[$x]=(int)$r['gruppe'];
	#	$grpArt=$art[$x]."G"; (20220823)
	#	$r2 = db_fetch_array(db_select("SELECT box5 FROM grupper WHERE art ='$grpArt' AND kodenr	= '$grp'",__FILE__ . " linje " . __LINE__));
	#		$modkonto[$x]=$r2['box5'];
		$x++;
	}
	$critB=$critC=$critD = NULL;
	# -> 2009.05.04
	$tmp1=$amount-0.005;$tmp2=$amount+0.005;$tmp3=($amount*-1)-0.005;$tmp4=($amount*-1)+0.005;
	$critA = "WHERE udlignet !='1'";
	// A line can hold several invoice numbers (a;b, from "Indsæt"); each open post has one
	$invoiceList = $faktura ? array_values(array_filter(array_map('trim', explode(';', urldecode($faktura))), 'strlen')) : array();
	if ($invoiceList) $critB = " AND faktnr IN ('" . implode("','", array_map('db_escape_string', $invoiceList)) . "')";
	if ((($d_type=='K')|| ($d_type=='D')) AND ($debet)) {
		if ($ktoId) $critC = " AND konto_id='".$ktoId."'"; #20120809
		if ($amount != 0) $critD = " AND amount >= '".$tmp3."' AND amount < '".$tmp4."'";
	}	elseif ((($k_type=='K')|| ($k_type=='D')) AND ($kredit)) {
		if ($ktoId) $critC = " AND konto_id='".$ktoId."'"; #20120809
		else $kriterie=$kriterie." AND konto_nr='".$kredit."'"; #20120809
		if ($amount != 0) $critD = " AND amount >= '".$tmp1."' AND amount < '".$tmp2."'";
	}	elseif ($amount != 0) {
		$critD = " AND ((amount >= '".$tmp1."' AND amount <= '".$tmp2."') or (amount >= '".$tmp3."' AND amount <= '".$tmp4."'))";
	}
	// With several invoices the line's amount is their sum, so no single post matches it
	if (count($invoiceList) > 1) $critD = NULL;
	$criteria = $critA.$critB.$critC.$critD;
	if ($sort=="firmanavn") $sort="konto_nr";
	$qtxt = "select * from openpost $criteria order by $sort";
	$x = 0;
	$accountId = array();
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		for ($y=0;$y<count($konto_id);$y++) {
			if ($r['konto_id'] == $konto_id[$y]) {
				$accountId[$x]   = $konto_id[$y];
				$accountNo[$x]   = $kontonr[$y];
				$accountName[$x] = $firmanavn[$y];
				$openPostId[$x]  = $r['id'];
				$openAamount[$x] = $r['amount'];
				$opptext[$x]     = $r['beskrivelse'];
				$invoiceNo[$x]   = $r['faktnr'];
				$currency[$x]     = $r['valuta'];
				$transdate[$x]    = $r['transdate'];
				$accountType[$x] = $art[$y];
				($invoiceNo[$x] && $r['kladde_id'] == 0)?$odrerId[$x] = $r['refnr'] : $odrerId[$x] = 0;
				if ($orderId[$x]) {
					$qtxt = "select firmanavn from ordrer where id = '$orderId[$x]'";
					$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$accountName[$x] = $r2['firmanavn'];

				}
				$grpArt=$art[$y]."G"; #20220823
				$offsetAccount[$x] = NULL;
				if ($gruppe[$y]) {
					$qtxt = "SELECT box5 FROM grupper WHERE art ='$grpArt' AND kodenr = '$gruppe[$y]' and fiscal_year = '".(int)$regnaar."'";
					$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					if ($r2) $offsetAccount[$x] = $r2['box5'];
				}
				$x++;
			}
		}
	}
	if ($ktoId && $x == 0) {
		$criteria = $critA.$critB.$critC;
		if ($sort=="firmanavn") $sort="konto_nr";
		$qtxt = "select id, konto_id, konto_nr, faktnr, transdate, amount,valuta, beskrivelse ";
		$qtxt.= "from openpost $criteria order by $sort";
		$x = 0;
		$accountId = array();
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			for ($y=0;$y<count($konto_id);$y++) {
				if ($r['konto_id'] == $konto_id[$y]) {
					$accountId[$x]   = $konto_id[$y];
					$accountNo[$x]   = $kontonr[$y];
					$accountName[$x] = $firmanavn[$y];
					$accountType[$x] = $art[$y];
					$openPostId[$x]  = $r['id'];
					$invoiceNo[$x]   = $r['faktnr'];
					$openAamount[$x] = $r['amount'];
					$opptext[$x]     = $r['beskrivelse'];
					$currency[$x]    = $r['valuta'];
					$transdate[$x]   = $r['transdate'];
					$grpArt=$art[$y]."G"; #20220823
					$offsetAccount[$x] = NULL;
					if ($gruppe[$y]) {
						$qtxt = "SELECT box5 FROM grupper WHERE art ='$grpArt' AND kodenr = '$gruppe[$y]' and fiscal_year = '".(int)$regnaar."'";
						$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						if ($r2) $offsetAccount[$x] = $r2['box5'];
					}
					$qtxt = "SELECT art FROM adresser WHERE id ='$accountId[$x]'";
					$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$accountType[$x] = $r2['art'];
					$x++;
				}
			}
		}
	}
	$diff = usdecimal($belob,2);

	if (!count($alignThis)) {
		$alignThis[$x] = '';
		for ($x=0;$x<count($accountId);$x++) {
			if ($diff >= $openAamount[$x]) {
				$alignThis[$x] = 'on';
				$diff-= $openAamount[$x];
			}
		}
		if (afrund($diff,2) != 0) $alignThis = array();
	}
	$diff = usdecimal($belob,2);
	$checkedSum = 0;
	for ($x=0;$x<count($accountId);$x++) {
		if (!$beskrivelse) {
#			$beskr=htmlentities($accountName,ENT_QUOTES,$charset);
#			if (!$faktnr && $faktura) $faktnr=$faktura;
			$beskr="$accountName[$x] - $invoiceNo[$x]";
		} else $beskr=$beskrivelse;

		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;}
		if ($openAamount[$x]<0) {
			($kredit)?$newCredit=$kredit:$newCredit=$offsetAccount[$x];
			($amount)?$newAmount=dkdecimal($belob):$newAmount=dkdecimal($openAamount[$x]*-1,2);
			$lnktxt = "kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=".urlencode($beskr);
			$lnktxt.= "&d_type=$accountType[$x]&debet=$accountNo[$x]&k_type=$k_type&kredit=$newCredit&faktura=".urlencode((string)$invoiceNo[$x]);
			$lnktxt.= "&belob=$newAmount&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$currency[$x]";
			$lnktxt.= "&lobenr=$lobenr&find=$find&forfaldsdato=".urlencode((string)$forfaldssato)."&betal_id=".urlencode((string)$betailngs_id);
			$tmp="<a href='$lnktxt'>";
			$newAmount=dkdecimal($openAamount[$x],2);
		} else {
			($amount)?$newAmount=dkdecimal($belob):$newAmount=dkdecimal($openAamount[$x],2);
			($debet)?$newDebet=$debet:$newDebet=$offsetAccount[$x];
			$lnktxt = "kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=".urlencode($beskr);
			$lnktxt.= "&d_type=$d_type&debet=$newDebet&k_type=$accountType[$x]&kredit=$accountNo[$x]&faktura=".urlencode((string)$invoiceNo[$x]);
			$lnktxt.= "&belob=$newAmount&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$currency[$x]";
			$lnktxt.= "&lobenr=$lobenr&find=$find&forfaldsdato=".urlencode((string)$forfaldssato)."&betal_id=".urlencode((string)$betailngs_id);
			$tmp="<a href='$lnktxt'>";
			$newAmount=dkdecimal($openAamount[$x],2);
			$oppSum+= $openAamount[$x];
		}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td> $tmp $accountNo[$x]</a></td>";
		print "<td> $tmp $accountName[$x]</a></td>";
		print "<td> $tmp $invoiceNo[$x]</a></td>";
		print "<td> $tmp ".dkdato($transdate[$x])."</a></td>";
		print "<td style=\"text-align:right\"> $tmp $newAmount</a></td>";
		if (isset($openPostId[$x]) && $openPostId[$x]) {
			if ($alignThis[$x]) {
				$checked = 'checked';
				$diff-= $openAamount[$x];
				$checkedSum+= $openAamount[$x];
			} else $checked = NULL;
			// The row's own link is what "Indsæt" follows, with the invoice numbers of all ticked rows
			print "<td style=\"text-align:center\"><input type = 'checkbox' class = 'openPostAlign' $checked";
			print " data-href = '".htmlspecialchars($lnktxt, ENT_QUOTES)."' data-invoice = '".htmlspecialchars((string)$invoiceNo[$x], ENT_QUOTES)."'";
			print " data-amount = '".(float)$openAamount[$x]."' data-account = '".(int)$accountId[$x]."' onchange = 'openPostRecalc()'></td>";
		}
		print "</tr>\n";
	}
	if (count($openPostId)) {
		print "<tr><td colspan = '4'></td><td style='text-align:right'><b>". dkdecimal($oppSum). "</b></td>";
		print "<td style='text-align:center'>(<span id = 'openPostChecked'>". dkdecimal($checkedSum,2). "</span>)</td><br>";
		print "<tr><td colspan = '4'><b>".findtekst('2396|Difference', $sprog_id)."</b></td><td style='text-align:right'><b id = 'openPostDiff'>". dkdecimal($diff). "</b></td><br>";
		print "<td style='text-align:center'>";
		print "<input type = 'button' id = 'openPostInsert' value = '".findtekst('1415|Indsæt', $sprog_id)."' onclick = 'openPostInsert()'>";
		print "</td></tr>\n";
		$lineAmount = (float)usdecimal($belob,2);
		print "<script>
		// The ticked sum and the difference to the line's amount; \"Indsæt\" only when they match. A kreditor's open
		// posts are negative, so the line's amount is compared with the size of the sum.
		// A line without an amount takes the ticked sum, as an invoice row's link takes the invoice's amount.
		function openPostTicked() {
			return Array.prototype.slice.call(document.querySelectorAll('input.openPostAlign')).filter(function (b) { return b.checked; });
		}
		function openPostDk(n) {
			return n.toLocaleString('da-DK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
		}
		function openPostRecalc() {
			var ticked = openPostTicked(), sum = 0;
			ticked.forEach(function (b) { sum += parseFloat(b.dataset.amount); });
			var diff = $lineAmount ? $lineAmount - Math.abs(sum) : 0;
			document.getElementById('openPostChecked').textContent = openPostDk(sum);
			document.getElementById('openPostDiff').textContent = openPostDk(diff);
			// One line has one account, so the ticked posts must all belong to it
			var oneAccount = ticked.every(function (b) { return b.dataset.account === ticked[0].dataset.account; });
			document.getElementById('openPostInsert').disabled = !ticked.length || !oneAccount || Math.abs(diff) >= 0.005 || Math.abs(sum) < 0.005;
			return sum;
		}
		function openPostInsert() {
			var ticked = openPostTicked(), sum = openPostRecalc();
			if (!ticked.length || document.getElementById('openPostInsert').disabled) return;
			// The side (debet or kredit) comes from a ticked row with the sum's sign: an invoice and a credit note
			// ticked together follow the invoice, whichever is listed first
			var src = ticked.filter(function (b) { return (parseFloat(b.dataset.amount) < 0) === (sum < 0); })[0] || ticked[0];
			var url = new URL(src.dataset.href, window.location.href);
			url.searchParams.set('faktura', ticked.map(function (b) { return b.dataset.invoice; }).join(';'));
			if (!$lineAmount) url.searchParams.set('belob', openPostDk(Math.abs(sum)));
			window.location.href = url.href;
		}
		openPostRecalc();
		</script>";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
?>
