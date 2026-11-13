<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/purchaseInvoicePosting.php --- ver 5.1.1 --- 2026-11-13 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// -------------------------------------------------------------------------
// 20261113 CDX/NTR Extract supplier invoice posting from kreditor/bogfor.php (SD-599).

/**
 * Post an eligible supplier invoice or credit note using the caller's transaction.
 *
 * The caller has locked and finalized the order and initialized the legacy database,
 * fiscal year, language, currency and exchange-difference globals. On success the
 * engine writes ledger/open-item rows and status 4. On validation failure it retains
 * the legacy redirect/rollback/exit behavior. It never commits the caller's transaction.
 *
 * @param int $id Supplier order ID (KO or KK).
 * @return void
 */
function post_purchase_invoice($id) {

	global $difkto;
	global $regnaar;
	global $sprog_id;
	global $valuta, $valutakurs;

	$afd=$ansat=$d_kontrol=$k_kontrol=$linjesum=$fakturasum=$momssum=$smoms=0;

	$logdate=date("Y-m-d");
	$logtime=date("H:i");
	$q = db_select("select box1, box2, box4, box5 from grupper where art='RB'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		if (trim($r['box4'])=="on") $modtbill=1;
		else $modtbill=0;
		if (trim($r['box5'])=="on") {
			$no_faktbill=1;
			$faktbill=0;
		}
		else $no_faktbill=0;
	}

	$x=0;
	$q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$art=$r['art'];
		$konto_id=$r['konto_id'];
		$lev_kontonr=str_replace(" ","",$r['kontonr']);
		$firmanavn=addslashes(trim($r['firmanavn']));
		$modtagelse=$r['modtagelse'];
		$transdate=($r['fakturadate']);
		$fakturanr=addslashes($r['fakturanr']);
		$ordrenr=$r['ordrenr'];
		$projekt[0]=$r['projekt'];
		$valuta=$r['valuta'];
		$valutakurs=$r['valutakurs']*1;
		$moms = $r['moms']*1;
		$momssats=$r['momssats']*1;
		$sum=$r['sum'];
		$omlev=$r['omvbet'];
		$ordreantal=$x;
		$qtxt = "select id,afd from ansatte where navn = '$r[ref]'";
		if ($r= db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$afd=$r['afd'];
			$ansat=$r['id'];
		}
		if (!$afd) $afd=0; #sikkerhed for at 'afd' har en vaerdi
		if (!$ansat) $$ansat=0;
		if ($no_faktbill==1) $bilag='0';
		else $bilag=trim($fakturanr);
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$qtxt = "select box1,box2 from grupper where art = 'KG' and kodenr='$r[gruppe]' and fiscal_year = '$regnaar'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$kontonr=$r['box2'];
		$box1=substr(trim($r['box1']),0,1);
		if ($box1 && ($box1!='E' || $box1!='Y')) $sum=$sum+$moms;	#moms tillaegges summen der ikke er eu moms.
########### OPENPOST	->
		if (substr($art,1,1)=='K') $beskrivelse ="Lev. kn.nr: ".$fakturanr.", modt. nr ".$modtagelse;
		else $beskrivelse ="Lev. fakt.nr:".$fakturanr.", modt.nr: ".$modtagelse;
		db_modify("insert into openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr, valuta, valutakurs,projekt) values ('$konto_id', '$lev_kontonr', '$fakturanr', $sum*-1, '$beskrivelse', '0', '$transdate', '0', '$id', '$valuta', '$valutakurs','$projekt[0]')",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select max(id) as id from openpost where konto_id = '$konto_id' and faktnr = '$fakturanr' and refnr='$id'",__FILE__ . " linje " . __LINE__));
		$openpost_id=$r['id'];
########### <- OPENPOST
		$tekst=findtekst(157,$sprog_id);
		if ($kontonr) {
			$r = db_fetch_array(db_select("select id from kontoplan where kontonr='$kontonr' and regnskabsaar = '$regnaar' and lukket!='on'",__FILE__ . " linje " . __LINE__));
			if (!$r['id']) {
				print "<BODY onLoad=\"javascript:alert('$tekst')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				transaktion("rollback");
				exit;
			}
		} else {
			print "<BODY onLoad=\"javascript:alert('$tekst')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			transaktion("rollback");
			exit;
		}
		if ($sum>0) {$kredit=$sum; $debet='0';}
		else {$kredit='0'; $debet=$sum*-1;}
		if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKK.
		$debet=afrund($debet,2);
		$kredit=afrund($kredit,2);
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		$fakturasum+=$debet-$kredit;
		if ($modtbill) $bilag=$modtagelse*1;
		else $bilag='0';
		if ($sum) {
			db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$kontonr','$fakturanr','$debet','$kredit','0',$afd,'$logdate','$logtime','$projekt[0]','$ansat','$id')",__FILE__ . " linje " . __LINE__);
		}
		if ($valutakurs) $maxdif=2; #Der tillades 2 oeres afrundingsdiff
		$p=0;
		$projektliste='';
		$q = db_select("select distinct(projekt) from ordrelinjer where ordre_id=$id and vare_id >	'0'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$p++;
			$projekt[$p]=$r['projekt'];
			($projektliste)?$projektliste.="<br>".$projekt[$p]:$projektliste=$projekt[$p];
		}
		$projektantal=$p;
		if ($projektantal) db_modify("update openpost set projekt='$projektliste' where id='$openpost_id'",__FILE__ . " linje " . __LINE__);

		for ($t=1;$t<=2;$t++)	{
			for ($p=1;$p<=$projektantal;$p++) {
				$y=0;
				$bogf_konto = array();
				if ($t==1) {
					$q = db_select("select * from ordrelinjer where ordre_id=$id and posnr>=0 and projekt='$projekt[$p]'",__FILE__ . " linje " . __LINE__);
				} else {
					$q = db_select("select * from ordrelinjer where ordre_id=$id and posnr<0 and projekt='$projekt[$p]'",__FILE__ . " linje " . __LINE__);
				}
				while ($r = db_fetch_array($q)) {
					if ($valutakurs) $maxdif=$maxdif+2; #Og yderligere 2 pr ordrelinje.
					if (!in_array($r['bogf_konto'],$bogf_konto)) {
						$y++;
						$bogf_konto[$y]=$r['bogf_konto'];
						$pos[$y]=$r['posnr'];
						$pris[$y]=afrund($r['pris']*$r['antal']-$r['pris']*$r['antal']*$r['rabat']/100,2); #20110124 afrund dec aendret fra 3 til 2 saldi_205 ordre_id 997
					}
					else {
						for ($a=1; $a<=$y; $a++) {
							if ($bogf_konto[$a]==$r['bogf_konto']) {
								$tmp= afrund($r['pris']*$r['antal']-$r['pris']*$r['antal']*$r['rabat']/100,2);  #20110124 afrund dec aendret fra 3 til 2 saldi_205 ordre_id 997
								$pris[$a]+=$tmp;
						}
						}
					}
				}
				if ($projekt[0] && !$projekt[$p]) $projekt[$p]=$projekt[0];
				$ordrelinjer=$y;
				for ($y=1;$y<=$ordrelinjer;$y++) {
					if ($bogf_konto[$y]) {
						if ($pris[$y]>0) {$debet=$pris[$y];$kredit=0;}
						else {$debet=0; $kredit=$pris[$y]*-1;}
						$tmp1=$kredit*$valutakurs/100;$tmp2=$debet*$valutakurs/100;
						if ($t==1 && $valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKK.
						$debet=afrund($debet,2);
						$kredit=afrund($kredit,2);
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
						$linjesum+=$debet-$kredit;
						$qtxt = "insert into transaktioner ";
						$qtxt.= "(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,";
						$qtxt.= "afd,logdate,logtime,projekt,ansat,ordre_id) values ";
						$qtxt.= "('$bilag','$transdate','$beskrivelse','$bogf_konto[$y]','$fakturanr','$debet','$kredit','0',";
						$qtxt.= "'$afd','$logdate','$logtime','$projekt[$p]','$ansat','$id')";
						if ($pris[$y]) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
			}
		}
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$qtxt = "select box1,box6 from grupper where art='KG' and kodenr='$r[gruppe]' and fiscal_year = '$regnaar'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$box1=(int)substr(trim($r['box1']),1,1);
		$smomsnr=substr(trim($r['box6']),1,1);
		if (!$box1)$moms=0;

#################### EU varekoeb moms ################
		if (substr(trim($r['box1']),0,1)=='E') {
		$qtxt = "select box1,box2,box3 from grupper where art='EM' and kodenr='$box1' and fiscal_year = '$regnaar'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$kmomskto=trim($r['box3']); # Ser lidt forvirrende ud,men den er go nok - fordi koebsmomsen ligger i box 3 v. udenlandsmoms.
			$emomskto=$r['box1'];
			$moms=$sum/100*$r['box2']; #moms af varekoeb i udland beregnes
			if ($moms > 0) {$kredit=$moms; $debet='0';}
			else {$kredit='0'; $debet=$moms*-1;}
			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKK.
			$momssum+=$debet-$kredit;
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			if ($moms) {
				db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$emomskto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[$p]','$ansat','$id')",__FILE__ . " linje " . __LINE__);
			}

#################### EU ydelseskoeb moms ################
		} elseif (substr(trim($r['box1']),0,1)=='Y') {
			$qtxt = "select box1,box2,box3 from grupper where art='YM' and kodenr='$box1' and fiscal_year = '$regnaar'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$kmomskto=trim($r['box3']); # Ser lidt forvirrende ud,men den er go nok - fordi koebsmomsen ligger i box 3 v. udenlandsmoms.
			$emomskto=$r['box1'];
			$moms=$sum/100*$r['box2']; #moms af varekoeb i udland beregnes
			if ($moms > 0) {$kredit=$moms; $debet='0';}
			else {$kredit='0'; $debet=$moms*-1;}
			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKK.
			$momssum+=$debet-$kredit;
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			if ($moms) {
				db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$emomskto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[$p]','$ansat','$id')",__FILE__ . " linje " . __LINE__);
			}
####################
		} else {
			if ($omlev && $smomsnr) {
			$qtxt = "select box1,box2,box3 from grupper where art='SM' and kodenr='$smomsnr' and fiscal_year = '$regnaar'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$smomskto=$r['box1'];
				$smoms=0;
				$q=db_select("select * from ordrelinjer where ordre_id='$id' and omvbet='on' and momsfri!='on'",__FILE__ . " linje " . __LINE__);
				while($r=db_fetch_array($q)) {
					$linjesum=afrund($r['pris']*$r['antal']-$r['pris']*$r['antal']*$r['rabat']/100,2);
					$smoms+=afrund($linjesum*$momssats/100,2);
				}
			}
			$moms+=$smoms;
			if ($smoms > 0) {$kredit=$smoms; $debet='0';}
			else {$kredit='0'; $debet=$smoms*-1;}
			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKK.
			$kredit=afrund($kredit,3);$debet=afrund($debet,3);
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			if ($smoms) {
			$tmp=$beskrivelse." (Omvendt betaling)";
				db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$tmp','$smomskto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id')",__FILE__ . " linje " . __LINE__);
			}
			$qtxt = "select box1 from grupper where art='KM' and kodenr='$box1' and fiscal_year = '$regnaar'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$kmomskto=trim($r['box1']);
		}
		if ($moms > 0) {$debet=$moms; $kredit='0';}
		else {$debet='0'; $kredit=$moms*-1;}
		if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKK.
		$kredit=afrund($kredit,3);$debet=afrund($debet,3);
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		$momssum+=$debet-$kredit;
		$moms=afrund($moms,2);
		if ($moms) {
			db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$kmomskto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id')",__FILE__ . " linje " . __LINE__);
		}

		db_modify("update ordrer set status=4,valutakurs=$valutakurs where id=$id",__FILE__ . " linje " . __LINE__);
		db_modify("delete from ordrelinjer where ordre_id=$id and posnr < 0",__FILE__ . " linje " . __LINE__);
	}
	$d_kontrol=afrund($d_kontrol,2);
	$k_kontrol=afrund($k_kontrol,2);
	if ($diff=afrund($d_kontrol-$k_kontrol,2)) {
		if ($valuta!='DKK' && abs($diff)<=$maxdif) { #Der maa max vaere en afvigelse paa 1 oere pr ordrelinje m fremmed valuta;
			$debet=0; $kredit=0;
			if ($diff<0) $debet=$diff*-1;
			else $kredit=$diff;
			$debet=afrund($debet,2);
			$kredit=afrund($kredit,2);
			db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('$bilag','$transdate','$beskrivelse','$difkto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id')",__FILE__ . " linje " . __LINE__);

		} else {
			print "<BODY onLoad=\"javascript:alert('Der er konstateret en uoverensstemmelse i posteringssummen, kontakt DANOSOFT p&aring; telefon 4690 2208')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			transaktion("rollback");
			exit;
		}
	}
#print "<BODY onLoad=\"javascript:alert('xxxxxxxxxxxxxxxxxxxx')\">";
#xit;
#	genberegn($regnaar);
}
