<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/findBoxSale/findBoxSaleFunc.php --- lap 3.8.9----2020.03.05-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2020 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20190510 LN Get data from grupper depending on the $status
// 20200305	PHR added db_escape_string and set $f_vatAccount=0 if no Vat.

	$x=0;
	if ($status<3) {
		$r=db_fetch_array(db_select("select box8 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
		$rabatvareid=$r['box8'];
		$q=db_select("select * from ordrelinjer where ordre_id = '$id' order by rabatgruppe, id desc",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$x++;
			$linje_id[$x]=$r['id'];
			$vare_id[$x]=$r['vare_id'];
			$varenr[$x]=$r['varenr'];
			$pris[$x]=$r['pris'];
			$antal[$x]=$r['antal'];
			$momsfri[$x]=$r['momsfri'];
			$varemomssats[$x]=$r['momssats'];
			$folgevare[$x]=$r['folgevare'];
			$tilfravalg[$x]=$r['tilfravalg'];
			$rabat[$x]=$r['rabat'];
			$rabatart[$x]=$r['rabatart'];
			$rabatgruppe[$x]=$r['rabatgruppe'];
			$saet[$x]=$r['saet'];
			if ($rabatgruppe[$x]) {
				if ($rabatgruppe[$x]==$rabatgruppe[$x-1]) {
					$rabatantal[$x]=$antal[$x]+$rabatantal[$x-1];
					$rabatantal[$x-1]=0;
				} else $rabatantal[$x]=$antal[$x];
			} else $rabatantal[$x]=0;
			$m_rabat[$x]=$r['m_rabat']*-1;
		}
		$linjeantal=$x;
		$pos=0;
		$sum=0;
		$moms=0;
		$incl_moms=0;
		transaktion("begin");
		for($x=1;$x<=$linjeantal;$x++) {
			$pos++;
			db_modify("update ordrelinjer set posnr='$pos',projekt='$projekt' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			if ($rabatart[$x]=='amount') {
				$tmp=afrund(($pris[$x]-$rabat[$x])*$antal[$x],2);
			} else $tmp=afrund($pris[$x]*$antal[$x]-($pris[$x]*$antal[$x]/100*$rabat[$x]),2);
			$sum+=$tmp;
			if (!$momsfri[$x]) {
				$linjemoms[$x]=$tmp*$varemomssats[$x]/100;
				$moms+=$linjemoms[$x];
			} else $linjemoms[$x]=0;
				$linjesum[$x]=afrund($tmp+$linjemoms[$x],2);
				$incl_moms+=$linjesum[$x];
			if ($folgevare[$x]>0 || $tilfravalg[$x]) {
				if ($tilfravalg[$x]) $tfvare=explode(chr(9),$tilfravalg[$x]);
				else $tfvare[0]=$folgevare[$x];
				for($fv=0;$fv<count($tfvare);$fv++) {
					$pos++;
					$r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris,gruppe from varer where id = '$tfvare[$fv]'",__FILE__ . " linje " . __LINE__));
					$f_salgspris=$r['salgspris'];
					$r2 = db_fetch_array(db_select("select box4, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
					$f_bogfkto=$r2['box4'];
					$f_momsfri=$r2['box7'];
					$sum+=afrund($antal[$x]*$f_salgspris,2);
					if ($f_momsfri) {
						$f_momssats=$f_vatAccount=0;
					} else {
						$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$f_bogfkto' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
						$kodenr=substr($r2['moms'],1);
						$r2 = db_fetch_array(db_select("select box1,box2 from grupper where kodenr = '$kodenr' and art = 'SM'",__FILE__ . " linje " . __LINE__));
						$f_vatAccount=$r2['box1']*1;
						$f_momssats=$r2['box2']*1;
					}
					$f_moms=$f_salgspris*$f_momssats/100;
					$f_vatPrice=afrund($f_salgspris+$f_moms,2);
					$incl_moms+=$f_vatPrice*$antal[$x];
					$moms+=$f_moms*$antal[$x];
					$tmp="L:$linje_id[$x]";
					$qtxt ="insert into ordrelinjer ";
					$qtxt.="(ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momssats,momsfri,";
					$qtxt.="posnr,procent,vat_account,vat_price,projekt,kdo,saet,rabatgruppe,tilfravalg) values ";
					$qtxt.= "('$id',  '$tfvare[$fv]','". db_escape_string($r['varenr']) . "', '". db_escape_string($r['beskrivelse']) ."',";
					$qtxt.= "'$antal[$x]','0','$r[salgspris]','0','$f_momssats','$f_momsfri',";
					$qtxt.="'$pos','100','$f_vatAccount','$f_vatPrice','$projekt','1','0','0'         ,'$tmp')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
			if ($rabatantal[$x]) {
				list($grupperabat,$rabattype)=explode(";",grupperabat($rabatantal[$x],$rabatgruppe[$x]));
				if ($grupperabat) {
					$pos++;
					if ($rabatvareid && $r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris,gruppe from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__))){
						$r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
						$r_momsfri = $r2['box7'];
						$r_vare_id=$r['id'];
						$r_varenr=$r['varenr'];
						$r_beskrivelse=$r['beskrivelse'];
					} else {
						$r_momsfri = $momsfri[$x];
						$r_vare_id=$vare_id[$x];
						$r_varenr=$varenr[$x];
						$r_beskrivelse='rabat';
					}
					db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momsfri,posnr,projekt,kdo) values ('$id','$r_vare_id', '$r_varenr', '$r_beskrivelse', '$rabatantal[$x]','0','$grupperabat','0','$r_momsfri','$pos','$projekt','1')",__FILE__ . " linje " . __LINE__);
					$tmp=afrund($grupperabat*$rabatantal[$x],2);
					$sum+=$tmp;
					if (!$r_momsfri){
						$incl_moms+=afrund($tmp+$tmp*$varemomssats[$x]/100,2);
					}
				}
			} elseif ($m_rabat[$x] && !$rabatgruppe[$x]) {
				$pos++;
				if ($rabatvareid && $r=db_fetch_array(db_select("select id,varenr,beskrivelse,salgspris,gruppe from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__))) {
					$r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
					$r_momsfri = $r2['box7'];
					$r_vare_id=$r['id'];
					$r_varenr=$r['varenr'];
					$r_beskrivelse=$r['beskrivelse'];
				} else {
					$r_momsfri = $momsfri[$x];
					$r_vare_id=$vare_id[$x];
					$r_varenr=$varenr[$x];
					$r_beskrivelse='rabat';
				}
				if ($momsfri[$x]) $r_momsfri = $momsfri[$x]; #20160812
				$vatPrice[$x]=afrund($m_rabat[$x]+$m_rabat[$x]*$varemomssats[$x]/100,2);
				$qtxt="insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momsfri,posnr,projekt,kdo,vat_price)";
				$qtxt.=" values "; 
				$qtxt.="('$id','$r_vare_id', '$r_varenr', '$r_beskrivelse', '$antal[$x]','0','$m_rabat[$x]','0','$r_momsfri','$pos','$projekt','1','$vatPrice[$x]')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$rabatbelob=afrund($m_rabat[$x]*$antal[$x],2);
				$sum+=$rabatbelob;
				if ($r_momsfri) { 
					$incl_moms+=$rabatbelob; #20160812
				} else {
					$moms+=afrund($rabatbelob*$momssats/100,2); #20131015
					$incl_moms+=afrund($rabatbelob+$rabatbelob*$varemomssats[$x]/100,2);
				}
			}
		}
		$fakturanr=1;
		$q=db_select("select fakturanr from ordrer where art = 'PO' and status >='3'",__FILE__ . " linje " . __LINE__); #max(fakturanr) fungerer ikke da feltet ikke er numerisk
		while ($r=db_fetch_array($q)) {
		 if ($r['fakturanr']>=$fakturanr) $fakturanr=$r['fakturanr']+1;
		}
		db_modify ("update ordrer set fakturanr='$fakturanr' where id='$id'",__FILE__ . " linje " . __LINE__);
		usleep(rand(100000,500000));
		$qtxt="select id from ordrer where fakturanr='$fakturanr' and art = 'PO' and id != '$id'";
		while ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			usleep(rand(100000,500000));
			$fakturanr+=1;
			$qtxt="select id from ordrer where fakturanr='$fakturanr' and art = 'PO' and id != '$id'";
			db_modify ("update ordrer set fakturanr='$fakturanr' where id='$id'",__FILE__ . " linje " . __LINE__);
		}
		$sum*=1; $moms*=1;
		$betalt=$modtaget+$modtaget2;
		$retur=afrund($betalt-($sum+$moms),2); #20140613
		if ($konto_id && ($betalingsbet || $indbetaling)) {
			$saldo=0;
			$q=db_select("select * from openpost where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				$saldo=$saldo+$r['amount'];
			}
			$betaling2=$saldo;
			if ($indbetaling) {
			$modtaget2=$saldo-$indbetaling;
			$sum=$indbetaling;
			$moms='0';
		} else $modtaget2=$saldo+$sum;
	}
	$moms=afrund($moms,2);
	if ($betaling=='Kontant' && !$betaling2 && $retur) { 
		if (!$tmp){
			$afrundet=pos_afrund($sum+$moms,$difkto,'');
			$tmp=afrund($modtaget-($sum+$moms),2);
			if (!$tmp) {
				$betalt=$afrundet;
				$modtaget=$afrundet;
			} elseif ($modtaget == pos_afrund($modtaget,$difkto,'')) {
				$betalt=$afrundet;
			}
		}
	} elseif ($betaling=='Konto' && $betalingsbet && $modtaget!=$sum+$moms && $retur) {
		if ($modtaget!=$sum+$moms && $sum+$moms!=pos_afrund($sum+$moms,$difkto)) {
			$afrundet=pos_afrund($sum+$moms,$difkto,'');
			$betalt=$afrundet;
		}
	} elseif (pos_afrund($sum,$difkto,'')==0 && pos_afrund($modtaget,$difkto,'')==0) { #20150812
		$sum=0;
		$moms=0;
		$incl_moms=0;
		$modtaget=0;
	}
	$modtaget=afrund($modtaget,2);
	$modtaget2=afrund($modtaget2,2);

	if ($sum+$moms!=$incl_moms) {
// Denne rutine korrigerer for de differencer det kan opstå i totaler fordi momsberegningen på skærmen vises for den enkelte vare, mens databasen indeholder 
// summen at varer excl moms og momsen separat. Hvis der er difference på summen tillægges/frratrækkes de enkelte varer så mange tienedele ører som muligt
// uden at den afrundede værdi incl moms ændres, indtil summen ex. moms + moms svarer til summen af varer incl moms. 20131205
		$tmp=afrund($incl_moms-($sum+$moms),2);
	if (abs($tmp)<=$linjeantal/200) {#max 0,5 øre afrundingsfejl pr linje;
			$sum+=$tmp;
			for($x=1;$x<=$linjeantal;$x++) {
				if (!$saet[$x]) {
					$ny_pris[$x]=$pris[$x];
					$tmp2=afrund($pris[$x],2);
					$tmp3=afrund($tmp2*$antal[$x]-($tmp2*$antal[$x]/100*$rabat[$x]),2);
					while (afrund($tmp,2) && afrund($tmp3+$linjemoms[$x],2)==$linjesum[$x]) {
						($tmp>0)?$tmp2+=0.001:$tmp2-=0.001;
						$tmp3=afrund($tmp2*$antal[$x]-($tmp2*$antal[$x]/100*$rabat[$x]),2);
						if (afrund($tmp3+$linjemoms[$x],2)==$linjesum[$x]) {
							$ny_pris[$x]=$tmp2;
							($tmp>0)?$tmp-=0.001:$tmp+=0.001;
						}
					}
					if ($ny_pris[$x]!=$pris[$x]) {
						$qtxt="update ordrelinjer set pris='$ny_pris[$x]' where id = '$linje_id[$x]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}	
			}
		} elseif (!$indbetaling) {
			print "<BODY onLoad=\"javascript:alert('Fejl i øreafrunding, kontakt Saldi på telefon 46902208')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
			exit;
		}
	}
	
	$tidspkt=date("H:i");
	$qtxt="update ordrer set levdate = '$dd',fakturadate = '$dd',sum='$sum', moms='$moms', betalt='$betalt',status='2',felt_1='$betaling',felt_2='$modtaget',felt_3='$betaling2',felt_4='$modtaget2',felt_5='$kasse',tidspkt='$tidspkt',projekt='$projekt',ref='$brugernavn' where id='$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	setcookie("saldi_bet",'',time()-3600);
	if (is_numeric($modtaget) && $modtaget) {
		if ($betaling == 'Cash' || $betaling == 'Cash on amount') $betaling='Kontant';
		$qtxt="select id  from pos_betalinger where ordre_id='$id'and betalingstype ='!'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="update pos_betalinger set betalingstype='$betaling',amount='$modtaget',valuta='$betvaluta',valutakurs='$betvalkurs' ";	
			$qtxt.="where id='$r[id]'";
		} else {
			$qtxt="insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs) values ";
			$qtxt.="('$id','$betaling','$modtaget','$betvaluta','$betvalkurs')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	if (!$indbetaling) {
				$svar=levering($id,'on','','');
				if ($svar != 'OK') return ($svar);
				$svar=bogfor($id,'');
				if ($svar != 'OK') return ($svar);
		} else {
			$svar=bogfor_indbetaling($id,'');
			if ($svar != 'OK') return ($svar);			
		}
	}


?>

