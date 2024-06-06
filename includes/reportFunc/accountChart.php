<?php
if (!function_exists('accountchart')) {
function accountchart($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart) {

//	global $connection;
	global $bgcolor,$bgcolor5,$bruger_id,$db;
	global $md,$menu;
	global $popup;
	global $regnaar;
	global $sprog_id;
	global $top_bund;

	$title = "Kontokort";
	
	$email=$forfaldsum=$fromdate=$kto_fra=$kto_til=$returside=$todate=NULL;

		$unAlign = if_isset($_GET['unAlign'],NULL);
		$unAlignAccount = if_isset($_GET['unAlignAccount'],0);
		$unAlignId = if_isset($_GET['oppId'],0);
	if ($unAlign || $unAlignId) {
		$qtxt = "update openpost set udlignet='0',udlign_id='0' where konto_id = '$unAlignAccount'";
		if ($unAlign) $qtxt .= " and udlign_id='$unAlign'";
		elseif ($unAlignId) $qtxt.= " and id = '$unAlignId'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$r=db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
	$regnstart="01-".trim($r['box1'])."-".trim($r['box2']);
	$tmp=31;
	while(!checkdate(trim($r['box3']),$tmp,trim($r['box4']))) {
		$tmp--;
		if ($tmp<28) break 1;
	}
	$regnslut=$tmp."-".trim($r['box3'])."-".trim($r['box4']);

	$difflink=0;
	$kontoart=trim($kontoart);
	$kilde=if_isset($_GET['kilde']);
	$kilde_kto_fra=if_isset($_GET['kilde_kto_fra']);
	$kilde_kto_til=if_isset($_GET['kilde_kto_til']);

	if ($kontoart=='K') $returnpath="../kreditor/";
	else $returnpath="../debitor/";

	$tmp=$konto_fra;
	($kontoart=='D')?$tekst='DRV':$tekst='KRV';
	$qtxt = "select * from grupper where art = '$tekst' and kodenr = '$bruger_id'";
	if(isset($_GET['returside'])) $returside= $_GET['returside'];
	elseif ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
		$dato_fra=$r['box2'];
		$dato_til=$r['box3'];
		$konto_fra=$r['box4'];
		$konto_til=$r['box5'];
		$rapportart=$r['box6'];
	} 
	if ($r=db_fetch_array(db_select("select id from grupper where art = 'PRJ'",__FILE__ . " linje " . __LINE__))) $prj='Projekt';
	else $prj='';

	if ($tmp && $tmp!=$konto_fra && !$returside) {
		$returside="rapport.php?rapportart=$rapportart"; //&submit=ok&regnaar=$regnaar&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til";
		$konto_fra=$tmp;
		$konto_til=$konto_fra;
	} elseif (!$returside) $returside="rapport.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til";

	if ($dato_fra && $dato_til) {
		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_til);
	}	elseif ($dato_fra && !$dato_til) {
		$todate=usdate($dato_fra);
	}
	$kontonr=array();
	$kto_id=array();
	$x=0;
	if (is_numeric($konto_fra) && is_numeric($konto_til)) { #changed 20210816
#		$qtxt = "select id from adresser where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art = '$kontoart' order by ".nr_cast('kontonr')."";
		$qtxt = "select id,kontonr from adresser where art = '$kontoart' order by kontonr";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($konto_fra <= $r['kontonr'] && $konto_til >= $r['kontonr']) {
				$x++;
				$konto_id[$x]=$r['id'];
			}
		}
	} else {
		if ($konto_fra && $konto_fra!='*') {
			$konto_fra=str_replace("*","%",$konto_fra);
			$tmp1=strtolower($konto_fra);
			$tmp2=strtoupper($konto_fra);
			$qtxt = "select id from adresser where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or ";
			$qtxt = "upper(firmanavn) like '$tmp2') and art = '$kontoart' order by firmanavn";
		}	else $qtxt = "select id from adresser where art = '$kontoart' order by firmanavn";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$konto_id[$x]=$r['id'];
		}
	}
	$kontoantal=$x;
	$x=0;
	// finder alle konti med bevaegelser i den anfoerte periode eller aabne poster fra foer perioden
	if ($kontoantal==1) { //20140505 - Fjerner udligning hvis udligningssum er skæv.
		$y=0;
		$qtxt="select distinct(udlign_id) from openpost where udlignet = '1' and udlign_id>'0' and konto_id='$konto_id[1]'";
		$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$udlign_id[$y]=$r['udlign_id']*1;
			$y++;
		}
	}
	
	for ($y=1;$y<=$kontoantal;$y++) {
#		if ($todate) $qtxt="select amount from openpost where transdate<='$todate' and konto_id='$konto_id[$y]'";
#		else $qtxt="select amount from openpost where konto_id='$konto_id[$y]'";
#		$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
#		while ($r = db_fetch_array($q)) {
			if (!in_array($konto_id[$y],$kto_id)) {
				$x++;
				$kto_id[$x]=$konto_id[$y];
			}
#		}
	}
	$kontoantal=$x;
	for ($x=1; $x<=$kontoantal; $x++) {
		$q = db_select("select * from adresser where id=$kto_id[$x]",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q);
 		$art=trim($r['art'])."G";
		$betalingsbet=trim($r['betalingsbet']);
		$betalingsdage=$r['betalingsdage'];
	
		$r2 = db_fetch_array(db_select("select box3 from grupper where art='$art' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$valuta=trim($r2['box3']);
		if (!$valuta) $valuta='DKK';
		else {
			$r2 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art='VK'",__FILE__ . " linje " . __LINE__));
			$valutakode=(!empty($r2) ? $r2['kodenr'] : 0);
		}
		$valutakode*=1;//20140505 


				$kontosum=0;
		$primo=0;
		$oppId=array();
		$amount=array();
		$beskrivelse=array();
		$valutakurs=array();
		$oppvaluta=array();
		$faktnr=array();
		$forfaldsdag=array();
		$primoprint[$x]=0;
		$baggrund=$bgcolor;
		$dkksum=0;
		$firstdate=date("Y-m-d");
		$lastdate='1970-01-01';
		
		$y=0;
		$qtxt="select max(id) as max_valdif_id from openpost where konto_id='$kto_id[$x]' and abs(amount) = '0.001'";
		$r2 = db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
		$max_valdif_id=$r2['max_valdif_id'];
						
		if ($todate) $qtxt="select * from openpost where konto_id='$kto_id[$x]' and transdate<='$todate' order by transdate,id,faktnr,refnr"; //20160414
		else $qtxt= "select * from openpost where konto_id='$kto_id[$x]' order by transdate,id,faktnr,refnr"; //20160414
		$q2 = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$y++;
			($baggrund==$bgcolor)?$baggrund=$bgcolor5:$baggrund=$bgcolor;
			$oppId[$y]=$r2['id'];
			$amount[$y]=afrund($r2['amount'],2);
			$amount[$y]=$r2['amount'];
			$beskrivelse[$y]=$r2['beskrivelse'];
			$valutakurs[$y]=$r2['valutakurs']*1;
			$oppvaluta[$y]=$r2['valuta'];
			$faktnr[$y]=$r2['faktnr'];
			if (!$oppvaluta[$y]) {
				$oppvaluta[$y]='DKK';
				$valutakurs[$y]=100; //20140503
			}
			$forfaldsdag[$y]=$r2['forfaldsdate'];
			$kladde_id[$y]=$r2['kladde_id'];
			($r2['projekt'])?$projekt[$y]=$r2['projekt']:$projekt[$y]='';
			($r2['kladde_id'])?$refnr[$y]=$r2['refnr']:$refnr[$y]='';
			if (!strlen($valutakurs[$y])) $valutakurs[$y]=100;
			$transdate[$y]=$r2['transdate'];
			if ($firstdate > $transdate[$y]) $firstdate = $transdate[$y];
			if ($firstdate < $transdate[$y]) $lastdate = $transdate[$y];
			$udlignet[$y]=$r2['udlignet'];
			$udlign_id[$y]=$r2['udlign_id'];

			if ($oppvaluta[$y]!='DKK' && $valutakurs[$y]==100) {
				$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'",__FILE__ . " linje " . __LINE__));
				$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__));
					$valutakurs[$y]=$r3['kurs']*1;
					$dkkamount[$y]=$amount[$y]*$valutakurs[$y]/100;
					$beskrivelse[$y] = $r2['beskrivelse']." - (Omregnet fra DKK til $valuta".dkdecimal($dkkamount[$y],2).", kurs ".dkdecimal($valutakurs[$y],2).")";
			} elseif ($valuta!="DKK" && $valutakurs[$y]==100) {
				if ($r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
					$dkkamount[$y]=$amount[$y];
					$amount[$y]=$amount[$y]*100/$r3['kurs'];
					$beskrivelse[$y] = $r2['beskrivelse']." - (Omregnet til $valuta fra DKK ".dkdecimal($dkkamount[$y],2).", kurs ".dkdecimal($r3['kurs'],2).")";
				} elseif ($r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' order by valdate",__FILE__ . " linje " . __LINE__))) {
					$amount[$y]=$amount[$y]*100/$r3['kurs'];
					$beskrivelse[$y] = $r2['beskrivelse']." - (Omregnet til $valuta fra DKK ".dkdecimal($dkkamount[$y],2).", kurs ".dkdecimal($r3['kurs'],2).")";
				}
			} elseif (($oppvaluta[$y]!='DKK' && $valuta=="DKK" && $valutakurs[$y]!=100)) {
					$beskrivelse[$y] = $r2['beskrivelse']." - (Omregnet til DKK fra ".$oppvaluta[$y]." ".dkdecimal($amount[$y],2).", kurs ".dkdecimal($valutakurs[$y],2).")";
					$amount[$y]=$amount[$y]*$valutakurs[$y]/100;
			} elseif ($valuta!="DKK" && $valuta==$oppvaluta[$y] && $valutakurs[$y]!=100) {
				$valutakurs[$y]*=1;
				if (!$valutakurs[$y] && $oppvaluta[$y] && $oppvaluta[$y]!='-') {
					$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'",__FILE__ . " linje " . __LINE__));
					$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__));
					$valutakurs[$y]=$r3['kurs']*1;
				} 
				$dkkamount[$y]=$amount[$y]*$valutakurs[$y]/100;
					if ($oppvaluta[$y]!='-' && abs($amount[$y])>=0.005) {
						 if (!strpos($beskrivelse[$y],'Udligning af valutadiff')) $beskrivelse[$y] = $r2['beskrivelse']." - (DKK ".dkdecimal($dkkamount[$y],2).")";
					} elseif (abs($amount[$y])<0.005) $beskrivelse[$y] = $r2['beskrivelse'];
					else $beskrivelse[$y] = $r2['beskrivelse']." - (DKK ".dkdecimal($amount[$y],2).")";
					} elseif($oppvaluta[$y]!=$valuta && $oppvaluta[$y] != '-') {
				if (!$valutakurs[$y]) {
					$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'",__FILE__ . " linje " . __LINE__));
					$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__));
					$valutakurs[$y]=$r3['kurs']*1;
				}
				$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art='VK'",__FILE__ . " linje " . __LINE__));
				$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__));
				$dagskurs=$r3['kurs']*1;
				$beskrivelse[$y].=" $oppvaluta[$y] ".dkdecimal($amount[$y],2)." Kurs $valutakurs[$y]";
				$amount[$y]*=$valutakurs[$y]/$dagskurs;
				$dkkamount[$y]=$amount[$y]*$valutakurs[$y]/100;
			} else {
				$beskrivelse[$y] = $r2['beskrivelse'];
				$dkkamount[$y]=$amount[$y]; //20160503
			}
			if ($oppvaluta[$y]=="-") {
				$dkkamount[$y]=$amount[$y];
				$amount[$y]=0;
				$forfaldsdate[$y]='';
			}
		}

		$luk= "<a accesskey=L href=\"$returside\">";

	if ($menu=='T') {
		print "";
	} else {
		print "<center><table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	}
	if ($menu=='T' && $x==1) {
		include_once ("../includes/topmenu/header.php");
		print "<div class='$kund'>$title</div>
		<div class='content-noside'><br>";
	} elseif ($x==1) {
		include("../includes/oldDesign/header.php");
		print "<tr><td colspan=\"9\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; //B
		print "<td width=\"10%\" $top_bund>$luk ".findtekst(30,$sprog_id)."</a></td>";
		if ($kontoart=='K') $tekst = findtekst(1140,$sprog_id) ." - ". lcfirst(findtekst(133,$sprog_id));
		else $tekst= findtekst(1141,$sprog_id) ." - ". lcfirst(findtekst(133,$sprog_id));
		print "<td width=\"80%\" $top_bund>$tekst</td>";
		($kontoantal==1)?$w=5:$w=10;
		print "<td width=\"w%\" $top_bund onClick=\"javascript:kontoprint=window.open('kontoprint.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontoart=$kontoart','kontoprint','left=0,top=0,width=1000%,height=700%, scrollbars=yes,resizable=yes,menubar=no,location=no');\"onMouseOver=\"this.style.cursor = 'pointer'\" title=\"Udskriv kontoudtog som PDF (Åbner i popup)\">". findtekst(880,$sprog_id) ."</td>\n";
		if ($kontoantal==1) { # 2019-11-07
			if ($fromdate) $firstdate=$fromdate;
			if ($todate) $lastdate=$todate;
			print "<td width=\"$w%\" $top_bund onClick=\"javascript:kontoprint=window.open('mail_kontoudtog.php?dato_fra=".dkdato($firstdate);
			print "&dato_til=".dkdato($lastdate)."&kontoantal=1&kontoliste=$kto_id[$x]','kontomail' ,'left=0,top=0,width=1000%,height=700%,";
			print "scrollbars=yes,resizable=yes,menubar=no,location=no');\" onMouseOver=\"this.style.cursor = 'pointer'\"";
			print "title=\"Send som mail (Åbner i popup)\">Email</td>\n";
		}
		print "</tbody></table>"; //B slut
		print "</td></tr>\n";
	}

	if ($menu=='T') {

		print "<div class='sub-title-kund-radius'>".stripslashes($r['firmanavn'])." • $r[kontonr]</div>";
		print "<div class='dataTablediv'><table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\" class='dataTable'><tbody>"; //B
		print "<tr>";
		print "<td width='10%' align=right><b>Firmanavn:</b></td> <td width='70%'>".stripslashes($r['firmanavn'])."</td>";
		print "<td align=right><b>Konto nr.:</b></td>";
		print "<td align=left>$r[kontonr]</td>";
		print "</tr>";
		print "<tr>";
		print "<td width='10%' align=right><b>Adresse:</b></td> <td width='70%'> ".stripslashes($r['addr1'])."</td>";
		print "<td align=right><b>Dato:</b></td>";
		print "<td align=left>".date('d-m-Y')."</td>";
		print "</tr>";
		print "<tr>";
		print "<td width='10%' align=right><b>Adresse 2:</b></td> <td width='70%'> ".stripslashes($r['addr2'])."</td>";
		print "<td align=right><b>Valuta:</b></td>";
		print "<td align=left>$valuta</td>";
		print "</tr>";
		print "<tr>";
		print "<td width='10%' align=right><b>Postnr - By:</b></td> <td width='70%'>".stripslashes($r['postnr'])."&nbsp;".stripslashes($r['bynavn'])."</td>";
		print "<td colspan=2></td>";
		print "</tr>";
		print "<tr>";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class='dataTableNTH'><thead>";
		print "<tr><td colspan='20' class='border-hr-bottom'></td></tr>";
		print "<tr>";
		print "<th>".findtekst(635,$sprog_id)."</th>";
		print "<th>".findtekst(671,$sprog_id)."</th>";
		print "<th>".findtekst(643,$sprog_id)."</th>";
		print "<th>".findtekst(1163,$sprog_id)."</th>";
		print "<th>$prj</th>";
		print "<th>".findtekst(1164,$sprog_id)."</th>";
		print "<th align=right class='text-right'>".findtekst(1000,$sprog_id)."</th>";
		print "<th align=right class='text-right'>".findtekst(1001,$sprog_id)."</th>";
		print "<th align=right class='text-right'>".findtekst(1073,$sprog_id)."</th>";
		print "</tr></thead><tbody>";
	
		$kontosum=0;
		$primo=0;
		$pre_openpost=0;
		for ($y=1;$y<=count($oppId);$y++) {
			$diff=0;
			if ($transdate[$y]<$fromdate) {
				 $primoprint[$x]=0;
				 $kontosum+=$amount[$y];
				$dkksum+=$dkkamount[$y];
				} else {
				if ($primoprint[$x]==0) {
					$tmp=dkdecimal($kontosum,2);
					$tmp2="";
					if ($valuta!='DKK') $tmp2="&nbsp;&nbsp;&nbsp;-&nbsp;&nbsp;&nbsp;Bel&oslash;b kan v&aelig;re omregnet fra DKK";
					print "<tr><td><br></td><td><br></td><td><br></td><td>".findtekst(1165,$sprog_id)." $tmp2<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK ".dkdecimal($dkksum,2)."\">$tmp<br></td></tr>\n";
					$primoprint[$x]=1;
				}
				if ($kladde_id[$y]) {
					$js="<a style='cursor: pointer;' onclick=\"window.open('../finans/kassekladde.php?kladde_id=$kladde_id[$y]&visipop=on')\">";
					$rt="title='Kladde ID: $kladde_id[$y]'";
				} else {
					$js=NULL;
					$rt=NULL;
				}
				print "<tr><td valign=\"top\">".dkdato($transdate[$y])."<br></td><td valign=\"top\" $rt> $js $refnr[$y] </a><br></td><td valign=\"top\">$faktnr[$y]<br></td><td valign=\"top\">".stripslashes($beskrivelse[$y])."<br></td><td valign=\"top\">$projekt[$y]</td>";
				if ($amount[$y] < 0) $tmp=0-$amount[$y];
				else $tmp=$amount[$y];
				$tmp=dkdecimal($tmp,2);
				if (!$forfaldsdag[$y]) $forfaldsdag[$y]=usdate(forfaldsdag($transdate[$y], $betalingsbet, $betalingsdage));
				if ($amount[$y]>0) {// (($kontoart=='D' && $amount>0) || ($kontoart=='K' && $amount<0)) {
				($kontoart=='D')?$ffdag=dkdato($forfaldsdag[$y]):$ffdag=NULL;
				if ($udlignet[$y]!='1') {
						$pre_openpost=1;
						print "<td valign=\"top\">$ffdag<br></td><td valign=\"top\" align=\"right\" title=\"Klik her for at udligne &aring;bne poster\"><a href=\"../includes/udlign_openpost.php?post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\">$tmp</a><br></td><td style=\"text-align:right\">0</td>";
					} else {
						$titletag="Udlign id=$udlign_id[$y]. Klik for at ophæve udligning"; 
						$alink="rapport.php?rapportart=accountChart&kilde=openpost&kto_fra=$kto_fra&kilde=$kilde
						&kto_til=$kto_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til
						&submit=ok&unAlign=$udlign_id[$y]&oppId=$oppId[$y]&unAlignAccount=$kto_id[$x]";
						$onclick="return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id $udlign_id[$y]')";
						print "<td valign=\"top\">$ffdag<br></td><td title=\"$titletag\" valign=\"top\" align=\"right\"><a onclick=\"$onclick\" href=\"$alink\" >$tmp<br></a></td><td style=\";text-align:right\">0</td>";
					}
					$forfaldsum=$forfaldsum+$amount[$y];
				} else {
					($kontoart=='K')?$ffdag=dkdato($forfaldsdag[$y]):$ffdag=NULL;
					if ($udlignet[$y]!='1') {
						print "<td>$ffdag<br></td><td style=\";text-align:right\">0</td><td valign=\"top\" align=right title=\"Klik her for at udligne &aring;bne poster\"><a href=\"../includes/udlign_openpost.php?post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\">$tmp</a><br></td>";
						$pre_openpost=1;
					} else {
						$titletag="Udlign id=$udlign_id[$y]. Klik for at ophæve udligning"; 
						$alink="rapport.php?rapportart=accountChart&kilde=openpost&kto_fra=$kto_fra&kilde=$kilde
						&kto_til=$kto_til&dato_fra=$dato_fra=&dato_til=$dato_til&konto_fra=$konto_fra
						&konto_til=$konto_til&submit=ok&unAlign=$udlign_id[$y]&oppId=$oppId[$y]&unAlignAccount=$kto_id[$x]";
						$onclick="return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id $udlign_id[$y]')";
						print "<td>$ffdag<br></td><td style=\";text-align:right\">0</td><td title=\"$titletag\" valign=\"top\" align=\"right\"><a onclick=\"$onclick\" href=\"$alink\">$tmp<br></a></td>";
					}
				}
				$kontosum+=afrund($amount[$y],2);
				$dkksum+=$dkkamount[$y];
				$dkksum=afrund($dkksum,2);
				$tmp=dkdecimal($kontosum,2);
				$dkktmp=dkdecimal($dkksum,2);
				if ($valuta!='DKK' && $kontosum!=$dkksum) $title="DKK: $dkktmp";
				else $title="";
				if ($valuta!='DKK' && !$difflink) {
					if ($r=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
						$dagskurs=$r['kurs'];
						$chkamount=$kontosum*$dagskurs/100;
						$diff=afrund($chkamount-$dkksum,2);
					}
				}
				$regulering=afrund($diff,2);
				if($regulering && !$difflink && $valuta!='DKK' && ($oppvaluta[$y]!='-' || $y==count($oppId)) && $transdate[$y]>=usdate($regnstart) && $transdate[$y]<=usdate($regnslut)) { // && $transdate>=$regnstart && $transdate<=$regnslut
					$vis_difflink=1;
					for ($i=1;$i<=count($oppId);$i++){
						if ($transdate[$i]==$transdate[$y] && $oppvaluta[$i]=='-') $vis_difflink=0;
					}
					if ($y==count($oppId) && !$kontosum) $vis_difflink=1;
						if ($oppId[$y]>=$max_valdif_id && ($vis_difflink && (abs($regulering)>0.01 || $y==count($oppId)))) {
						$difflink=1;
						if ($regnstart<=date("Y-m-d") && $regnslut>=date("Y-m-d")) {
							$title.="Klik for at regulere værdien i DKK fra ".dkdecimal($dkksum,2)." til ".dkdecimal($dkksum+$regulering,2)." pr. ".dkdato($transdate[$y]);
							$tmp2="<a href=\"../includes/ret_valutadiff.php?bfdate=$transdate[$y]&";
							$tmp2.="valuta=$valuta&diff=$regulering&post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
							$tmp2.="konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\" ";
							$tmp2.="onclick=\"confirmSubmit($confirm)\">$tmp</a>";
							$tmp=$tmp2;
						} else $title=NULL;
					}
				} elseif ($y==count($oppId) && abs($tmp)<0.01 && abs($dkksum) > 0.01 && $regnslut>=date("Y-m-d")) {
					$title.="Klik for at regulere værdien i DKK fra ".dkdecimal($dkksum,2)." til ".dkdecimal($dkksum+$regulering,2)." pr. ".date("d-m-Y");
					$tmp2="<a href=\"../includes/ret_valutadiff.php?bfdate=".date("Y-m-d")."&";
					$tmp2.="valuta=$valuta&diff=$regulering&post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
					$tmp2.="konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\" ";
					$tmp2.="onclick=\"confirmSubmit($confirm)\">$tmp</a>";
					$tmp=$tmp2;
				}
				print "<td valign=\"top\" align=right title=\"$title\">$tmp<br></td>";
				print "</tr>\n";
			}
		}
		if ($primoprint[$x]==0) {
			$tmp=dkdecimal($kontosum,2);
			print "<tr><td><br></td><td><br></td><td><br></td><td>Primosaldo<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK sum $dkktmp\">$tmp<br></td></tr>\n";
		}

		print "</tbody><tfoot>";
		print "<tr><td colspan=10>";
		print "<center><input type='button' onclick=\"javascript:kontoprint=window.open('kontoprint.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontoart=$kontoart','kontoprint','left=0,top=0,width=1000%,height=700%, scrollbars=yes,resizable=yes,menubar=no,location=no');\"onMouseOver=\"this.style.cursor = 'pointer'\" title=\"Udskriv kontoudtog som PDF (Åbner i popup)\" accesskey='L' value='".findtekst(880,$sprog_id)."'></center>";
		print "</td></tr>";
		print "</tfoot></table></div><br>";

	} else {
			print "<tr><td colspan=9><hr></td></tr>\n";
			print "<tr><td><br></td></tr>\n";
			print "<tr><td><br></td></tr>\n";
			print "<tr><td colspan=3>".stripslashes($r['firmanavn'])."</td></tr>\n";
			print "<tr><td colspan=3>".stripslashes($r['addr1'])."</td></tr>\n";
			print "<tr><td colspan=3>".stripslashes($r['addr2'])."</td><td colspan=5 align=right>Konto nr.</td><td align=right>$r[kontonr]</td></tr>\n";
			print "<tr><td colspan=3>".stripslashes($r['postnr'])."&nbsp;".stripslashes($r['bynavn'])."</td><td colspan=5 align=right>Dato</td><td align=right>".date('d-m-Y')."</td></tr>\n";
			print "<tr><td colspan=8 align=right>Valuta</td><td align=right>$valuta</td></tr>\n";
			print "<tr><td><br></td></tr>\n";
			print "<tr><td><br></td></tr>\n";
			print "<tr><td>".(isset($sprog_id) ? findtekst(635,$sprog_id) : "")."</td><td>".(isset($sprog_id) ? findtekst(671,$sprog_id) : "")."</td><td>".(isset($sprog_id) ? findtekst(643,$sprog_id) : "")."</td><td>".(isset($sprog_id) ? findtekst(1163,$sprog_id) : "")."</td><td>$prj</td><td>".(isset($sprog_id) ? findtekst(1164,$sprog_id) : "")."</td><td align=right>".(isset($sprog_id) ? findtekst(1000,$sprog_id) : "")."</td><td align=right>".(isset($sprog_id) ? findtekst(1001,$sprog_id) : "")."</td><td align=right>".(isset($sprog_id) ? findtekst(1073,$sprog_id) : "")."</td></tr>\n";
			print "<tr><td colspan=9><hr></td></tr>\n";

			$kontosum=0;
			$primo=0;
			$pre_openpost=0;
			for ($y=1;$y<=count($oppId);$y++) {
				$diff=0;
				if ($transdate[$y]<$fromdate) {
					 $primoprint[$x]=0;
					 $kontosum+=$amount[$y];
					$dkksum+=$dkkamount[$y];
					} else {
					if ($primoprint[$x]==0) {
						$tmp=dkdecimal($kontosum,2);
						$tmp2="";
						if ($valuta!='DKK') $tmp2="&nbsp;&nbsp;&nbsp;-&nbsp;&nbsp;&nbsp;Bel&oslash;b kan v&aelig;re omregnet fra DKK";
						print "<tr><td><br></td><td><br></td><td><br></td><td>".(isset($sprog_id) ? findtekst(1165,$sprog_id) : "")." $tmp2<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK ".dkdecimal($dkksum,2)."\">$tmp<br></td></tr>\n";
						$primoprint[$x]=1;
					}
					if ($kladde_id[$y]) {
						$js="onclick=\"window.open('../finans/kassekladde.php?kladde_id=$kladde_id[$y]&visipop=on')\"";
						$rt="title='Kladde ID: $kladde_id[$y]'";
					} else {
						$js=NULL;
						$rt=NULL;
					}
					print "<tr bgcolor=\"$baggrund\"><td valign=\"top\">".dkdato($transdate[$y])."<br></td><td valign=\"top\" $rt $js>$refnr[$y]<br></td><td valign=\"top\">$faktnr[$y]<br></td><td valign=\"top\">".stripslashes($beskrivelse[$y])."<br></td><td valign=\"top\">$projekt[$y]</td>";
					if ($amount[$y] < 0) $tmp=0-$amount[$y];
					else $tmp=$amount[$y];
					$tmp=dkdecimal($tmp,2);
					if (!$forfaldsdag[$y]) $forfaldsdag[$y]=usdate(forfaldsdag($transdate[$y], $betalingsbet, $betalingsdage));
					if ($amount[$y]>0) {// (($kontoart=='D' && $amount>0) || ($kontoart=='K' && $amount<0)) {
					($kontoart=='D')?$ffdag=dkdato($forfaldsdag[$y]):$ffdag=NULL;
					if ($udlignet[$y]!='1') {
							$pre_openpost=1;
							print "<td valign=\"top\"><span style='color: rgb(255, 0, 0);'>$ffdag<br></td><td  valign=\"top\" align=\"right\" title=\"Klik her for at udligne &aring;bne poster\"><span style='color: rgb(255, 0, 0);'><a href=\"../includes/udlign_openpost.php?post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\">$tmp</a><br></td><td style=\"color:$baggrund;text-align:right\">0</td>";
						} else {
							$titletag="Udlign id=$udlign_id[$y]. Klik for at ophæve udligning"; 
							$alink="rapport.php?rapportart=accountChart&kilde=openpost&kto_fra=$kto_fra&kilde=$kilde
							&kto_til=$kto_til&&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra
							&konto_til=$konto_til&submit=ok&unAlign=$udlign_id[$y]&oppId=$oppId[$y]&unAlignAccount=$kto_id[$x]";
							$onclick="return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id $udlign_id[$y]')";
							print "<td valign=\"top\"><span style='color: rgb(0, 0, 0);'>$ffdag<br></td><td title=\"$titletag\" valign=\"top\" align=\"right\"><span style=\"color: rgb(0, 0, 0);\"><a onclick=\"$onclick\" href=\"$alink\"style=\"text-decoration:none;\" >$tmp<br></a></span></td><td style=\"color:$baggrund;text-align:right\">0</td>";
						}
						$forfaldsum=$forfaldsum+$amount[$y];
					} else {
						($kontoart=='K')?$ffdag=dkdato($forfaldsdag[$y]):$ffdag=NULL;
						if ($udlignet[$y]!='1') {
							print "<td><span style='color: rgb(255, 0, 0);'>$ffdag<br></td><td style=\"color:$baggrund;text-align:right\">0</td><td valign=\"top\" align=right title=\"Klik her for at udligne &aring;bne poster\"><span style='color: rgb(255, 0, 0);'><a href=\"../includes/udlign_openpost.php?post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\">$tmp</a><br></td>";
							$pre_openpost=1;
						} else {
							$titletag="Udlign id=$udlign_id[$y]. Klik for at ophæve udligning"; 
							$alink="rapport.php?rapportart=accountChart&kilde=openpost&kto_fra=$kto_fra&kilde=$kilde
							&kto_til=$kto_til&dato_fra=$dato_fra&&dato_til=$dato_til&konto_fra=$konto_fra
							&konto_til=$konto_til&submit=ok&unAlign=$udlign_id[$y]&oppId=$oppId[$y]&unAlignAccount=$kto_id[$x]";
							$onclick="return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id $udlign_id[$y]')";
							print "<td>$ffdag<br></td><td style=\"color:$baggrund;text-align:right\">0</td><td title=\"$titletag\" valign=\"top\" align=\"right\"><span style=\"color: rgb(0, 0, 0);\"><a onclick=\"$onclick\" href=\"$alink\"style=\"text-decoration:none;\" >$tmp<br></a></span></td>";
						}
					}
					$kontosum+=afrund($amount[$y],2);
					$dkksum+=$dkkamount[$y];
					$dkksum=afrund($dkksum,2);
					$tmp=dkdecimal($kontosum,2);
					$dkktmp=dkdecimal($dkksum,2);
					if ($valuta!='DKK' && $kontosum!=$dkksum) $title="DKK: $dkktmp";
					else $title="";
					if ($valuta!='DKK' && !$difflink) {
						if ($r=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
							$dagskurs=$r['kurs'];
							$chkamount=$kontosum*$dagskurs/100;
							$diff=afrund($chkamount-$dkksum,2);
						}
					}
					$regulering=afrund($diff,2);
					if($regulering && !$difflink && $valuta!='DKK' 
						&& ($oppvaluta[$y]!='-' || $y==count($oppId)) 
						&& $transdate[$y]>=usdate($regnstart) 
						&& $transdate[$y]<=usdate($regnslut)) { // && $transdate>=$regnstart && $transdate<=$regnslut
						$vis_difflink=1;
						for ($i=1;$i<=count($oppId);$i++){
							if ($transdate[$i]==$transdate[$y] && $oppvaluta[$i]=='-') $vis_difflink=0;
						}
						if ($y==count($oppId) && !$kontosum) $vis_difflink=1;
							if ($oppId[$y]>=$max_valdif_id && ($vis_difflink && (abs($regulering)>0.01 || $y==count($oppId)))) {
							$difflink=1;
							if ($regnstart<=date("Y-m-d") && $regnslut>=date("Y-m-d")) {
								$title.="Klik for at regulere værdien i DKK fra ".dkdecimal($dkksum,2)." til ".dkdecimal($dkksum+$regulering,2)." pr. ".dkdato($transdate[$y]);
								$tmp2="<a href=\"../includes/ret_valutadiff.php?bfdate=$transdate[$y]&";
								$tmp2.="valuta=$valuta&diff=$regulering&post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
								$tmp2.="konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\" ";
								$tmp2.="onclick=\"confirmSubmit($confirm)\">$tmp</a>";
								$tmp=$tmp2;
							} else $title=NULL;
						}
					} elseif ($y==count($oppId) && abs(intval($tmp))<0.01 && abs($dkksum) > 0.01 && $regnslut>=date("Y-m-d")) {
						$title.="Klik for at regulere værdien i DKK fra ".dkdecimal($dkksum,2)." til ".dkdecimal($dkksum+$regulering,2)." pr. ".date("d-m-Y");
						$tmp2="<a href=\"../includes/ret_valutadiff.php?bfdate=".date("Y-m-d")."&";
						$tmp2.="valuta=$valuta&diff=$regulering&post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
						$tmp2.="konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\" ";
						$tmp2.="onclick=\"confirmSubmit($confirm)\">$tmp</a>";
						$tmp=$tmp2;
					}
					print "<td valign=\"top\" align=right title=\"$title\">$tmp<br></td>";
					print "</tr>\n";
				}
			}
			if ($primoprint[$x]==0) {
				$tmp=dkdecimal($kontosum,2);
				print "<tr><td><br></td><td><br></td><td><br></td><td>Primosaldo<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK sum ".(isset($dkktmp) ? $dkktmp : "")."\">$tmp<br></td></tr>\n";
			}
			print "<tr><td colspan=9><hr></td></tr>\n";
		}
	}
	print "</tbody></table>";
	
	if ($menu=='T') {
		print "<center><input type='button' onclick=\"location.href='$returside'\" accesskey='L' value='".findtekst(30,$sprog_id)."'></center>";
		include_once '../includes/topmenu/footerDebRapporter.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}
}}
?> 
