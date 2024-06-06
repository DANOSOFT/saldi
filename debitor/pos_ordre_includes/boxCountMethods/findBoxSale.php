<?php
function findBoxSale ($kasse,$optalt,$valuta) {
	echo "<!-- function findBoxSale begin -->"; 
	#cho "Valuta $valuta<br>";
  global $db,$regnaar;
	global $sprog_id,$straksbogfor;
	global $vis_saet;
	
	$retur=0;
	$dd=date("Y-m-d");
	$r=db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr = '$regnaar'",__FILE__ . " linje " . __LINE__));
	$startmd=$r['box1'];
	$startaar=$r['box2'];
	$slutmd=$r['box1'];
	$slutaar=$r['box2'];

	($startaar && $startmd)?$regnstart=$startaar.'-'.$startmd.'-01':$regnstart='2000-01-01';
	($slutaar && $slutmd)?$regnslut=$slutaar.'-'.$slutmd.'-31':$regnslut=date('Y').'-12-31';
#	if (($regnstart > $dd || $regnslut < $dd) && substr($dd,4) !='-01-01') {
#		print tekstboks("Du er ikke i aktivt regnskabsår, morgenbeholdning kan være misvisende");
#	}
	$alert1 = findtekst(1872, $sprog_id);
	if ($regnstart=='2000-01-01') alert($alert1);
	$qtxt = "select * from grupper where art = 'POS' and kodenr = '1'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$kassekonti=explode(chr(9),$r['box2']);
	$kortantal=(int)$r['box4'];
	$kortnavne=$r['box5'];
	$kortnavn   = explode(chr(9),$kortnavne);
	$l_cardName = explode(chr(9),strtolower($kortnavne));
	$kortkonti=$r['box6'];
	$kortkonto=explode(chr(9),$kortkonti);
	$straksbogfor=$r['box9'];
	$o_liste=NULL; #20150519
	if ($valuta!='DKK') {
		$kortantal=0;
		$kortnavn=array();
	}
	$qtxt = "select box1,box6 from grupper where art = 'POS' and kodenr = '2'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	($byttepenge=$r['box1'])?$fast_morgen=1:$fast_morgen=0;
	$otherCardsAccount = (int)$r['box6'];

/* 20231210 replaced by above lines
	$qtxt = "select * from grupper where art = 'POS' and kodenr = '2'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($byttepenge=$r['box1']) $fast_morgen=1; # 20160215-2 Tilføjet 'if' & $fast_morgen
	else $fast_morgen=0;
	$betalingskort=explode(chr(9),$r['box5']);
	if (in_array('on',$betalingskort)){ #20170317
		$x=count($kortnavn);
		$kortnavn[$x]='Betalingskort';
		$kortnavne.=chr(9).$kortnavn[$x];
		$kortsum[$x]=0;
		$kortkonto[$x]=$r['box6'];
		$kortkonti.=chr(9).$kortkonto[$x];
		$kortantal++;
	}
*/
	$acountExists = $kortsum = array();
	for ($i=0;$i<count($kortnavn);$i++) {
		if (!in_array($kortkonto[$i],$acountExists)) {
			$qtxt = "select id from kontoplan where regnskabsaar = '$regnaar' and kontonr = '$kortkonto[$i]' ";
			$qtxt.= "and kontotype = 'S'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				alert ("Fejl i kontoopsætning. Konto $kortkonto[$i] $kortnavn[$i] eksisterer ikke");
				print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php\">\n";
				exit;
			} else $acountExists[$i] = $kortkonti[$i];
		}
		$kortsum[$i] = 0;
	}
	if ($valuta!='DKK') {
		$r = db_fetch_array(db_select("select * from grupper where art = 'VK' and box1='$valuta'",__FILE__ . " linje " . __LINE__));
		$kassekonti=explode(chr(9),$r['box4']);
		if (!$kassekonti[$kasse-1]) {
			return("Kontonr mangler for $valuta"); #20160824 
			exit;
		}
	}
	$k=$kasse-1;
	if (!$fast_morgen) {
		$kassekonti[$k]*=1;
		$qtxt="select primo from kontoplan where regnskabsaar = '$regnaar' and kontonr = '$kassekonti[$k]'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$byttepenge=$r['primo'];
		} # 20160215-2 Flyttet sluttuborg fra under '(if (!$fast_morgen))' længere nede
		if ($straksbogfor) $qtxt="select sum(debet) as debet,sum(kredit) as kredit from transaktioner where transdate < '$dd' and transdate >= '$regnstart' and kontonr = '$kassekonti[$k]'"; # and kasse_nr='$kasse'
		else { #20150519 -->
			$qtxt="select logdate,logtime from transaktioner where transdate >= '$regnstart' and ";
			$qtxt.="beskrivelse like 'Kasseoptaelling%' and kontonr='0' and debet='0' and kredit='0' and kasse_nr='$kasse' order by id desc limit 1"; #20161116
			if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="select logdate,logtime from transaktioner where transdate >= '$regnstart' and ";
				$qtxt.="(beskrivelse like 'Dagsafslutning - kasse $kasse' or beskrivelse like 'Overført til Kasse - pengeskab $valuta fra kasse $kasse'";
				$qtxt.=" or beskrivelse like 'Overført til Kasse - pengeskab fra kasse $kasse') order by id desc limit 1"; #20160607 + 20160223 ændret "%" til " - kassenr: $kasse"
			}
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['logdate'] && $r['logtime']) { #20150601 + 20160220-3
				$logdate=$r['logdate'];
				$logtime=$r['logtime'];
			} else { #20170102 
				$logdate=$regnstart;
				$logtime='00:00';
			}
			$qtxt = "select distinct(ordre_id) from transaktioner where (logdate > '$logdate' or (logdate = '$logdate' and logtime > '$logtime')) ";
			$qtxt.= "and kasse_nr='$kasse'";
#cho __line__." $db $qtxt<br>";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			$o=0;
			$oList=array();
			while($r=db_fetch_array($q)) {
				if ($r['ordre_id']) {
					($o_liste)?$o_liste.=" or ordrer.id='$r[ordre_id]'":$o_liste.="ordrer.id='$r[ordre_id]'"; #20151211 Tilføjet 
					$oList[$o]=$r['ordre_id'];
#cho __line__." OL $oList[$o]<br>";
					$o++;
				}
			}
			$v = 0;
			$vatValue = array();
#cho __line__." ".count($oList)."<br>";			
			for ($o=0;$o<count($oList);$o++) {
				$chkSum=0;
				$qtxt = "select * from ordrelinjer where ordre_id = '$oList[$o]'";
#cho "$qtxt<br>";			
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__); 
				while($r=db_fetch_array($q)) {
					if (!in_array($r['momssats'],$vatValue)) {
						$vatValue[$v] = $r['momssats'];
						$v++;
					}
					for($i==0;$i<count($vatValue);$i++) {
						if ($r['momssats'] == $vatValue[$i]) {
							$turnover[$i] = $r['antal']*$r['pris'];
							if ($r['rabat']) {
								if ($r['rabarart'] == 'amount') $turnover[$i]-= ($r['rabat']*$r['antal']);
								else $turnover[$i]-= $turnover[$i]*$r['rabat']/100; 
							} elseif ($r['m_rabat']) {
								if ($r['rabarart'] == 'amount') $turnover[$i]-= ($r['rabat']*$r['antal']);
								else $turnover[$i]-= $turnover[$i]*$r['rabat']/100; 
							}	
						}
						$chkSum += $turnover[$i];
					}
				}
#				if ($chkSum != $osum[$o]) echo __line__." $chkSum != $oSum[$o]<br>";
#				else echo __line__." $chkSum = $oSum[$o]<br>";
			}
			# <-- 20150519
#			} # 20170102
			$qtxt="select sum(debet) as debet,sum(kredit) as kredit from transaktioner where transdate >= '$regnstart' and kontonr = '$kassekonti[$k]'"; # and kasse_nr='$kasse'
			if ($logdate && $logtime) $qtxt.=" and (kladde_id != '0' or (logdate < '$logdate' or (logdate = '$logdate' and logtime <= '$logtime')))"; # 20161116 #20150519 #20151211 Tilføjet if...
		}
#cho __line__." $qtxt<br>";		
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if (!$fast_morgen) $byttepenge+=$r['debet']-$r['kredit']; # 20160215-2 Tilføjet 'if (!$fast_morgen))' 
	#	} # 20160215-2 Sluttuborg flyttet op
	if ($straksbogfor) {
		$qtxt="select sum(debet) as debet,sum(kredit) as kredit from transaktioner ";
		$qtxt.="where transdate >= '$regnstart' and transdate = '$dd' and kontonr = '$kassekonti[$k]'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); # and kasse_nr='$kasse'
		$tilgang=$r['debet']-$r['kredit'];
	} else {
		$tilgang=0;
		$oid=0;
		db_modify("update ordrer set felt_3='' where felt_3 is NULL and status='3'",__FILE__ . " linje " . __LINE__);
		$b = $v = 0;
		$oid=$osum=$oVat=array();
#		$kontosalg='';
		$kontosum = 0;
		$tmp=NULL;
		($o_liste)?$tmp=" and (ordrer.status='3' or $o_liste)":$tmp=" and ordrer.status='3'"; #20150519
		$qtxt="select pos_betalinger.*,ordrer.sum,ordrer.moms,ordrer.status from pos_betalinger,ordrer ";
		$qtxt.="where ordrer.felt_5='$kasse' $tmp and ordrer.fakturadate >= '$regnstart' ";
		$qtxt.="and ordrer.fakturadate <= '$dd' and ordrer.valuta = '$valuta' ";
		if (count($oList)) $qtxt.= "and ordrer.status >= '3' ";
		else $qtxt.= "and ordrer.status = ' 3'";
		$qtxt.="and ordrer.id=pos_betalinger.ordre_id order by pos_betalinger.betalingstype, ordrer.id";
#cho __line__." $qtxt<br>";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['status'] == '3' || in_array($r['ordre_id'],$oList)) {
				$betalingstype=$r['betalingstype']; #20170317
#cho __line__." $betalingstype<br>";
				if (substr($betalingstype,0,14)=='Betalingskort|')$betalingstype='Betalingskort';	
				if (!in_array($r['ordre_id'],$oid)) {
					$oid[$b]  = $r['ordre_id'];
					$oVat[$b] = (float)$r['moms'];
					$osum[$b] = $r['sum']+$r['moms'];
#$tsum+=$osum[$b];
#cho __line__." $betalingstype $osum[$b]<i> $oVat</i> $tsum+=$osum[$b]<br>";
					$bvaluta[$b] = $r['valuta'];
					$b++;
				}
#					if (strtolower($r['betalingstype'])=='konto') $kontosalg.=$oid[$b].chr(9);
##cho "OID $oid[$b] $osum[$b] <b>$tsum</b><br>";
				if (strtolower($r['betalingstype'])=='konto') $kontosum+=$r['amount'];
				elseif ($valuta=='DKK' && $r['betalingstype']=='Kontant' && ($r['valuta'] == $valuta || $r['valuta'] == '')) {
					$tilgang+=$r['amount'];
				} elseif ($valuta!='DKK' && $r['betalingstype']=='Kontant' && $r['valuta'] == $valuta) {
					$tilgang+=$r['amount'];
				} else {
					if (!in_array(strtolower($betalingstype),$l_cardName)) {
						$i = count($kortnavn);
						$l_cardName[$i] = strtolower($betalingstype);
						$kortnavn[$i]   = $betalingstype;
						$kortkonto[$i]  = $otherCardsAccount;
						$kortsum[$i]    = 0;
					}
					for ($x=0;$x<count($kortnavn);$x++) {
						if (strtolower($betalingstype)==strtolower($kortnavn[$x])) { # 20170816
							$kortsum[$x]+=$r['amount'];
#cho __line__." $r[ordre_id] ".dkdecimal($r['sum']+$r['moms'],2)." <b>$kortnavn[$x]</b> ".dkdecimal($r['amount'])." \n<br>"; 
						}
					}
				}
			}
		}
#cho __line__." Tsum $tsum<br>"; 
	$vatRate  = array();
	$accountPayment = 0;
	for ($b=0;$b<count($oid);$b++) {
		$lineCount=0;
		$qtxt="select sum(amount) as amount from pos_betalinger where ordre_id=$oid[$b]";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$retur+=($r['amount']-$osum[$b]);
		$qtxt="select * from ordrelinjer where ordre_id=$oid[$b]";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$lineCount++;
#cho __line__." $r[ordre_id]|$r[beskrivelse]|$r[pris]|$r[antal]|$r[rabat]|$r[momssats]\n<br>";
			if ($r['momsfri']) $r['momssats'] = 0;
			if (in_array($r['momssats'],$vatRate)) {
				for ($i=0;$i<count($vatRate);$i++) {
					if ($vatRate[$i] == $r['momssats']) {
						if ($r['rabat'] && $r['rabatart'] == 'amount') $discount = $r['rabat'] * $r['antal'];
						elseif ($r['rabat']) $discount = $r['pris']  * $r['antal'] * $r['rabat'] / 100;
						else $discount = 0;
						$vatAmount[$i] += $r['pris']*$r['antal']-$discount;
#if ($vatRate[$i] == 0) #cho __line__." Oid $oid[$b] Lid $r[id] MS $r[momssats] VR $vatRate[$i] VA $vatAmount[$i]<br>"; 						
					}
				}
			} else {
				$i=count($vatRate);
				if ($r['rabat'] && $r['rabatart'] == 'amount') $discount = $r['rabat'] * $r['antal'];
				elseif ($r['rabat']) $discount = $r['pris']  * $r['antal'] * $r['rabat'] / 100;
				else $discount = 0;
				$vatAmount[$i] = $r['pris']*$r['antal']-$discount;
				$vatRate[$i] = (float)$r['momssats'];
#if ($vatRate[$i] == 0) #cho __line__." Oid $oid[$b] Lid $r[id] VR $vatRate[$i] VA $vatAmount[$i]<br>"; 						
			}
		}
		if (!$lineCount && !$oVat[$b]) $accountPayment+= $osum[$b]; 
	}
	$vatRates = $vatAmounts = '';
	
	for ($i=0;$i<count($vatRate);$i++) {
		if ($i) {
			$vatRates.= chr(9).$vatRate[$i];
			$vatAmounts.= chr(9).$vatAmount[$i];
		} else {
			$vatRates = $vatRate[$i];
			$vatAmounts = $vatAmount[$i];
		}
	}
	if ($valuta=='DKK') $tilgang-=$retur;
	}
	if ($straksbogfor && $kortantal) { #20150121b
		for ($x=0;$x<$kortantal;$x++) {
			if ($kortkonto[$x]) {
				$qtxt = "select sum(debet) as debet,sum(kredit) as kredit from transaktioner ";
				$qtxt.= "where transdate = '$dd' and kontonr = '$kortkonto[$x]'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); # and kasse_nr = '$kasse'
				$kortsum[$x]+=$r['debet']-$r['kredit'];
			}
		}
	}
#	$kassesum=dkdecimal($byttepenge+$tilgang);
#	$byttepenge=dkdecimal($byttepenge);
#	$tilgang=dkdecimal($tilgang);
	$kortsummer = NULL;
	$diff=$optalt-($byttepenge+$tilgang);
	($diff<0)?$prefix=NULL:$prefix="+";
	for ($x=0;$x<count($kortnavn);$x++) {
		if ($x) {
			$kortnavne.=chr(9).$kortnavn[$x];
			$kortkonti.=chr(9).$kortkonto[$x];
			$kortsummer.=chr(9).$kortsum[$x];
		} else {
			$kortnavne=$kortnavn[0];
			$kortkonti=$kortkonti[0];
			$kortsummer=$kortsum[0];
		}
	}
#cho __line__." Kortsum ".array_sum($kortsum)."<br>";
#cho __line__." Kontosum ".$kontosum."<br>";
#cho __line__." Tilgang ".$tilgang."<br>";
#cho __line__." totalsum ".$kontosum+array_sum($kortsum)+$tilgang."<br>";

	$valutaer='';
	$valutasummer=0;
	/*	
	$valutasummer=$valutasum[0];
	for ($x=1;$x<count($valuta);$x++) {
		$valutaer.=chr(9).$valutasum[$x];
		$valutasummer.=chr(9).$valutasum[$x];
	}
*/	

#	if ($kontosalg) $kontosalg=trim($kontosalg,chr(9));
	return array($byttepenge,$tilgang,$diff,$kortantal,$kortkonti,$kortnavne,$kortsummer,$kontosum,$valutaer,$valutasummer,$vatRates,$vatAmounts,$accountPayment);
} # endfunc findBoxSale
	echo "<!-- function findBoxSale end -->"; 
?>
