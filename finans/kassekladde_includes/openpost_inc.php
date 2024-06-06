<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/openpost_inc.php --- ver 4.0.8 --- 2023.08.28 ---
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
// Copyright (c) 2003-2023 saldi.dk ApS
// ----------------------------------------------------------------------
// 20220823 PHR Moved $offsetAccount lookup to reduce lookups
// 20221111 PHR More posts can now be aligned in one step
// 20230604 PHR Added offsetAccount to kredit,
// 20230828 PHR Gets Company name from inviince instead of order.

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
	if ($menu=='T') {
		include_once '../includes/top_menu.php';
		include_once '../includes/top_header.php';
		print "<div id=\"header\"><div class=\"headerbtnLft\">";
		print "<a href='../finans/kassekladde.php?$lnktxt' class=\"button red small left\" accesskey=L>Luk</a></div>";
		print "<span class=\"headerTxt\">Åbenposter</span>";
		print "<div class=\"headerbtnRght\"></div>";
		print "</div><!-- end of header --><div class=\"maincontentLargeHolder\">\n";
		print  "<table class='dataTable2' border='0' cellspacing='1' align='center';>";
	} else {
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		print "<td width=\"10%\" $top_bund><a href='../finans/kassekladde.php?$lnktxt' accesskey=L>Luk</a></td>";
		print "<td width=\"80%\" $top_bund>&Aring;benposter</td>";
		print "<td width=\"10%\" $top_bund align=\"right\"><br></td>";
		print "</tbody></table>";
		print "</td></tr>\n";
		print "<tr><td valign=\"top\">";
		print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"800px\" valign = \"top\">";
}
	print "<tbody >";
	print "<tr>";
	print "<td><b><a href='../finans/kassekladde.php?sort=konto_nr".$lnktxt."'>Kontonr</a></b></td>";
	print "<td><b><a href='../finans/kassekladde.php?sort=firmanavn".$lnktxt."'>Navn</a></b></td>";
	print "<td><b><a href='../finans/kassekladde.php?sort=faktnr".$lnktxt."'>Fakturanr</a></b></td>";
	print "<td><b><a href='../finans/kassekladde.php?sort=transdate".$lnktxt."'>Dato</a></b></td>";
	print "<td style = 'width:20px'><b><a href='../finans/kassekladde.php?sort=amount".$lnktxt."'>Bel&oslash;b</a></b></td>";
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
		$grp=$r['gruppe'];
	#	$grpArt=$art[$x]."G"; (20220823)
	#	$r2 = db_fetch_array(db_select("SELECT box5 FROM grupper WHERE art ='$grpArt' AND kodenr	= '$grp'",__FILE__ . " linje " . __LINE__));
	#		$modkonto[$x]=$r2['box5'];
		$x++;
	}
	$critB=$critC=$critD = NULL;
	# -> 2009.05.04
	$tmp1=$amount-0.005;$tmp2=$amount+0.005;$tmp3=($amount*-1)-0.005;$tmp4=($amount*-1)+0.005;
	$critA = "WHERE udlignet !='1'";
	if ($faktura) $critB = " AND faktnr ='".$faktura."'";
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
				$qtxt = "SELECT box5 FROM grupper WHERE art ='$grpArt' AND kodenr	= '$grp'";
				$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$offsetAccount[$x] = $r2['box5'];
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
					$qtxt = "SELECT box5 FROM grupper WHERE art ='$grpArt' AND kodenr	= '$grp'";
					$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$offsetAccount[$x] = $r2['box5'];
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
	print "<form name = 'OpenPost' action='kassekladde.php' method='post'>";
	print "<input type = 'hidden' name = 'fokus' value = '$fokus'>\n";
	print "<input type = 'hidden' name = 'postId'    value = '$id'>\n";
	for ($x=0;$x<count($accountId);$x++) {
		if (!$beskrivelse) {
#			$beskr=htmlentities($accountName,ENT_QUOTES,$charset);
#			if (!$faktnr && $faktura) $faktnr=$faktura;
			$beskr="$accountName[$x] - $invoiceNo[$x]";
		} else $beskr=$beskrivelse;

		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;}
		if ($openAamount[$x]<0) {
			if (!$kredit) $kredit=$offsetAccount[$x];
			($amount)?$newAmount=dkdecimal($belob):$newAmount=dkdecimal($openAamount[$x]*-1,2);
			$lnktxt = "kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr";
			$lnktxt.= "&d_type=$accountType[$x]&debet=$accountNo[$x]&k_type=$k_type&kredit=$kredit&faktura=$invoiceNo[$x]";
			$lnktxt.= "&belob=$newAmount&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$currency[$x]";
			$lnktxt.= "&lobenr=$lobenr&find=$find";
			$lnktxt=htmlentities($lnktxt);
			$tmp="<a href='$lnktxt'>";
			$newAmount=dkdecimal($openAamount[$x],2);
		} else {
			($amount)?$newAmount=dkdecimal($belob):$newAmount=dkdecimal($openAamount[$x],2);
			if (!$debet) $debet=$offsetAccount[$x];
			$lnktxt = "kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr";
			$lnktxt.= "&d_type=$d_type&debet=$debet&k_type=$accountType[$x]&kredit=$accountNo[$x]&faktura=$invoiceNo[$x]";
			$lnktxt.= "&belob=$newAmount&momsfri=$momsfri&afd=$afd&projekt=$projekt&ansat=$ansat&valuta=$currency[$x]";
			$lnktxt.= "&lobenr=$lobenr&find=$find";
			$lnktxt=htmlentities($lnktxt);
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
			print "<td style=\"text-align:center\"><input type = 'checkbox' name = 'alignThis[$x]' $checked></td>";
			print "<input type = 'hidden' name = 'openPostId[$x]'  value = '$openPostId[$x]'>\n";
			print "<input type = 'hidden' name = 'opptext[$x]'     value = '$opptext[$x]'>\n";
			print "<input type = 'hidden' name = 'accountNo[$x]'   value = '$accountNo[$x]'>\n";
			print "<input type = 'hidden' name = 'accountType[$x]' value = '$accountType[$x]'>\n";
			print "<input type = 'hidden' name = 'invoiceNo[$x]'   value = '$invoiceNo[$x]'>\n";
			print "<input type = 'hidden' name = 'openAamount[$x]' value = '$openAamount[$x]'>\n";
			print "<input type = 'hidden' name = 'currency[$x]'    value = '$currency[$x]'>\n";
		}
		print "</tr>\n";
	}
	if (count($openPostId)) {
		print "<input type = 'hidden' name = 'insertId' value = '$id'>\n";
		print "<input type = 'hidden' name = 'kladde_id' value = '$kladde_id'>\n";
		print "<tr><td colspan = '4'></td><td style='text-align:right'><b>". dkdecimal($oppSum). "</b></td>";
		print "<td style='text-align:center'>(". dkdecimal($checkedSum,2). ")</td><br>";
		print "<tr><td colspan = '4'><b>Difference</b></td><td style='text-align:right'><b>". dkdecimal($diff). "</b></td><br>";

		print "<td style='text-align:center'>";
		if ($diff) print "<input type = 'submit' name = 'calculateOpenPost' value = 'Beregn'>";
		else       print "<input type = 'submit' name = 'updateInvoiceField' value = 'Indsæt'>";
		print "</td></tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
?>
