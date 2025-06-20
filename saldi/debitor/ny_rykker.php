<?php #topkode_start
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------------------debitor/ny_rykker.php-----lap 3.6.7--2017.03.03-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2017 saldi.dk aps
// ----------------------------------------------------------------------

// 2012.11.05 - Fejl ved renteberegning af posteringer uden forfaldadato. Søg 20121105 
// 2014.06.28 - Valuta og valutakurs indsættes nu ved oprettesle af ny rykker. Søg 20140628 
// 2014.07.07 - Hvis ingen valuta sættes til DKK, hvis ingen kurs sættes til sættes til 100. Søg 20140707 
// 2014.11.06 - Indsat db_escape_string foran div variabler hvor de indsættes i tabeller.
// 2017.03.03 - PHR Tilføjet felt_5 til insert into ordrer aht inkasso. 

// --------------------- Bekrivelse ------------------------
// Ved generering af en rykker oprettes en ordre med art = R1. Hver ordre der indgår i rykkeren oprettes som en ordrelinje
// hvor feltet enhed indeholder id fra openpost tabellen og serienr indeholder forfaldsdatoen,.Beskrivelse indeholde beskrivelse.
// Da varenrfelt er tomt vil linjerne blive opfattes som kommentarlinjer ved bogføring
// Rykkergebyr tilføjes som en ordinær ordrelinje.med varenummer for rykkergebyr, og vil derfor blive behandlet som den eneste reelle ordrelinje
// ved bogføring.
// Ved generering af rykker "2" medtages evt gebyr fra rykker 1 på samme måden som v. ovenstående. 
		
@session_start();
$s_id=session_id();

$topniveau=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/formfunk.php");
include("../includes/forfaldsdag.php");
include("../includes/openpost.php");

$konto_antal=$_GET['kontoantal'];
if (isset($_GET['rykker_id'])) $rykker_id=explode(";", $_GET['rykker_id']);
else $rykker_id=NULL;
$konto_id =explode(";", $_GET['kontoliste']);

$rykkerdate=date("Y-m-d");

$rentesats_1=0;
$rentesats_2=0;
$rentesats_3=0;
$q=db_select("select formular, str from formularer where beskrivelse='GEBYR' and formular>='6' order by formular",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if ($r['formular']==6) $rentesats_1=$r['str'];
	elseif ($r['formular']==7) $rentesats_2=$r['str'];
	elseif ($r['formular']==8) $rentesats_3=$r['str'];
}
$r = db_fetch_array(db_select("select box5,box6,box7,box8 from grupper where art='DIV' and kodenr= '4'",__FILE__ . " linje " . __LINE__));
$ffdage1=$r['box5']*1;
$ffdage2=$r['box6']*1;
$ffdage3=$r['box7']*1;
$rykkerfrist1=usdate(forfaldsdag($dd,'netto',$ffdage1));

$r = db_fetch_array(db_select("select id from formularer where beskrivelse LIKE '%inkassotekst'",__FILE__ . " linje " . __LINE__));
if ($r['id']) {
	$inkassotekst='';
}

if ($konto_id[0]=="alle") { 
	$dd=date("Y-m-d");
	$x=0;
	$konto_id=array();
	$q=db_select("select * from openpost where udlignet = '0' order by konto_id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!in_array($r['konto_id'],$konto_id)) {
			if ($x && !$kontosum[$x]) $udlign[$x]=1;
			$x++;
			$konto_id[$x]=$r['konto_id'];	
			$kontosum[$x]=$r['amount'];
			$udlign[$x]=0;
		} else
		$kontosum[$x]=$kontosum[$x]+$r['amount'];
	}
	$konto_antal=$x;
	$r=db_fetch_array(db_select("select max(udlign_id) as udlign_id from openpost",__FILE__ . " linje " . __LINE__));
	$udlign_id=$r['udlign_id'];
	for ($x=1;$x<=$konto_antal;$x++) { # udligner alle åbne poster og sletter tilhørende åbne rykkere hvor udestaaende er 0;
		if ($udlign[$x]) {
			$udlign_id++;
			db_modify("update openpost set udlignet = '1', udlign_id = '$udlign_id' where konto_id='$konto_id[$x]' and udlignet = '0'",__FILE__ . " linje " . __LINE__);
			$q=db_select("select id from ordrer where konto_id='$konto_id[$x]' and art LIKE 'R%' and status < '3' and betalt != 'on'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				db_modify("delete from ordrelinjer where ordre_id='$r[id]'",__FILE__ . " linje " . __LINE__); 
				db_modify("delete from ordrer where id='$r[id]'",__FILE__ . " linje " . __LINE__); 
			}	
		}
	}
	# finder resterende aabne rykkere som ikke er i listen over konto over aabne poster og sletter disse.
	$q=db_select("select id, konto_id from ordrer where art LIKE 'R%' and status < '3' and betalt != 'on'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!in_array($r['konto_id'],$konto_id)) {
			db_modify("delete from ordrelinjer where ordre_id='$r[id]'",__FILE__ . " linje " . __LINE__); 
			db_modify("delete from ordrer where id='$r[id]'",__FILE__ . " linje " . __LINE__); 
		}
	}
	$konto_id=array();
	$x=0;
	$q=db_select("select openpost.* from openpost,adresser where openpost.udlignet = '0' and openpost.forfaldsdate<='$rykkerfrist1' and openpost.amount>'0' and adresser.id=openpost.konto_id and adresser.art = 'D' order by openpost.konto_id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!db_fetch_array(db_select("select id from ordrelinjer where enhed = '$r[id]'",__FILE__ . " linje " . __LINE__))) { #Tjekker om der allerede eksisterer en rykker på ordren.
			if (!in_array($r['konto_id'],$konto_id)) {
				$konto_id[$x]=$r['konto_id']; #Liste over konto id numre der skal rykkes
				$x++;
			} 
		}
	}
	$konto_antal=$x;
	$rykker_id=array();
	$utid=date('U');-$ffdage2*3600*24;
	$rykkerfrist2=date("Y-m-d",$utid);
	#finder aabne level_1 rykkere som har overskredet rykkerdato med ffdage2.og bogforer disse
	$q=db_select("select id,konto_id from ordrer where art = 'R1' and status < '3' and ordredate < '$rykkerfrist2'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		db_modify("update ordrer set betalt = 'on' where id = '$r[id]'",__FILE__ . " linje " . __LINE__); 
		bogfor_rykker($r['id']);
		$rykker_id[$x]=$r['id'];
	}
	$utid=date('U');-$ffdage3*3600*24;
	$rykkerfrist3=date("Y-m-d",$utid);
	#finder aabne level_2 rykkere som har overskredet rykkerdato med ffdage3.og bogforer disse
	$q=db_select("select id,konto_id from ordrer where art = 'R2' and status < '3' and ordredate < '$rykkerfrist3' and betalt != 'on'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		db_modify("update ordrer set betalt = 'on' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
		bogfor_rykker($r['id']);
		$rykker_id[$x]=$r['id'];
	}
	$konto_antal=$x;
} else {
	$autoryk=0;
	$rykkerfrist1=$rykkerdate;
}
for ($i=0; $i<=$konto_antal; $i++) {
	$forfalden=0;
	if (($konto_id[$i])||($rykker_id[$i])) {
		if (!$rykker_id[$i]) {
			$sum=0;
			$r_ordrenr=0;
			
			if ($r = db_fetch_array(db_select("select MAX(ordrenr) as r_ordrenr from ordrer where art LIKE 'R%'",__FILE__ . " linje " . __LINE__))) $r_ordrenr=$r['r_ordrenr'];
			$r_ordrenr++;
			$r_fakturanr=$r_ordrenr."-1";
			$r=db_fetch_array(db_select("select * from adresser where id ='$konto_id[$i]'",__FILE__ . " linje " . __LINE__));
			$kontonr=db_escape_string($r['kontonr']);
			$firmanavn=db_escape_string($r['firmanavn']);
			$addr1=db_escape_string($r['addr1']);
			$addr2=db_escape_string($r['addr2']);
			$postnr=db_escape_string($r['postnr']);
			$bynavn=db_escape_string($r['bynavn']);
			$land=db_escape_string($r['land']);
			$notes=db_escape_string($r['notes']);
			$ref=db_escape_string($r['kontoansvarlig']);
			$kontakt=db_escape_string($r['kontakt']);
			$email=db_escape_string($r['email']);
			if ($ffdage2) {
				$betalingsdage=$ffdage2;
				$betalingsbet='Netto'	;
			} else {	 
				$betalingsdage=db_escape_string($r['betalingsdage']);
				$betalingsbet=db_escape_string($r['betalingsbet']);
			} 
			$cvrnr=db_escape_string($r['cvrnr']);
			$ean=db_escape_string($r['ean']);
			$institution=db_escape_string($r['institution']);
			$r2 = db_fetch_array(db_select("select box1, box3, box4, box6 from grupper where art='DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
			$valuta=$r2['box3'];
			$sprog=$r2['box4'];
			if ($valuta && $valuta!='DKK') {
				if ($r2= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr::INT and valuta.valdate <= '$rykkerdate' order by valuta.valdate",__FILE__ . " linje " . __LINE__))) {
					$valutakurs=$r2['kurs'];
				} else {
					$tmp = dkdato($ordredate);
					print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
				}
			} else {
				$valuta='DKK';
				$valutakurs=100;
			}
			if ($ref) {
				$r2 = db_fetch_array(db_select("select navn from ansatte where id = '$ref'",__FILE__ . " linje " . __LINE__));
				$ref=$r['navn'];	
			}	
			$op_id=NULL;
			$x=0;
			$q=db_select("select * from openpost where udlignet = '0' and ((forfaldsdate<'$rykkerfrist1' and amount>'0') or amount < '0') and konto_id='$konto_id[$i]'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
#				Finder tidligere rykkere på samme ordre.
				if (!db_fetch_array(db_select("select ordrer.id from ordrer,ordrelinjer where ordrelinjer.enhed = '$r[id]' and ordrer.id = ordrelinjer.ordre_id and ordrer.betalt != 'on'",__FILE__ . " linje " . __LINE__)) && !db_fetch_array(db_select("select id from ordrer where id = '$r[refnr]' and art LIKE 'R%' and betalt != 'on'",__FILE__ . " linje " . __LINE__))) {
					$x++;
					if ($op_id) $op_id=$op_id." or id ='".$r['id']."'"; #Liste over open post id numre der skal rykkes  
					else $op_id="id ='".$r['id']."'";
					$amount[$x]=$r['amount'];
					$forfaldsdate[$x]=$r['forfaldsdate'];
					if (!$forfaldsdate[$x]) $forfaldsdate[$x]=$r['transdate']; #20121105
					if ($rentesats_1) $renteamount[$x] = find_rente($rentesats_1,$forfaldsdate[$x],$amount[$x]);
					else $renteamount[$x]=0;
				}
			}
			$ryk_antal=$x;
			if ($ryk_antal) {	
					db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, levdate, fakturadate, momssats, hvem, tidspkt, ref, status,valuta, valutakurs,fakturanr,email,betalt,kontakt,felt_5) 
					values ('$r_ordrenr', '$konto_id[$i]', '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$betalingsdage', '$betalingsbet', '$cvrnr', '$ean', '$institution', '$notes', 'R1', '$rykkerdate', '$rykkerdate', '$rykkerdate', '0', '$brugernavn', '$tidspkt', '$ref', '2', '$valuta', '$valutakurs','$r_fakturanr','$email','-','$kontakt','')",__FILE__ . " linje " . __LINE__);
				$r= db_fetch_array(db_select("select id from ordrer where ordrenr ='$r_ordrenr' and art = 'R1'",__FILE__ . " linje " . __LINE__));
				$rykker_id[$i]=$r['id'];
				$id=$konto_id[$i];
				$x=0;
				$pos=0;
				$q2 = db_select("select * from openpost where $op_id ",__FILE__ . " linje " . __LINE__);
				while ($r2 = db_fetch_array($q2)) {
					$x++;				
					if ($r2['valuta']) $opp_valuta=$r2['valuta'];
					else $opp_valuta='DKK';
					if ($r2['valutakurs']) $opp_valkurs=$r2['valutakurs'];
					else $opp_valkurs=100;
					if (($opp_valuta!='DKK' || $valuta!='DKK') && $opp_valuta!=$valuta)  $beskrivelse=$r2['beskrivelse']." (".$opp_valuta." ".dkdecimal($r2['amount']).")";
					else $beskrivelse=$r2['beskrivelse'];
					if ($valuta=='DKK'&& $opp_valuta!='DKK') $opp_amount=$r2['amount']*$opp_valkurs/100;
					elseif ($valuta!='DKK' && $opp_valuta=='DKK') $opp_amount=$r2['amount']*100/$opp_valkurs;
					elseif ($valuta!='DKK' && $opp_valuta!='DKK' && $opp_valuta!=$valuta) {
						$tmp==$r2['amount']*$opp_valkurs/100;
					 	$opp_amount=$tmp*100/$opp_valkurs;
					}
					else $opp_amount=$r2['amount'];
					$pos++;
					db_modify("insert into ordrelinjer (posnr,enhed, ordre_id, serienr, beskrivelse) values ('$pos','$r2[id]', '$rykker_id[$i]', '$r2[transdate]', '$beskrivelse')",__FILE__ . " linje " . __LINE__);
					$forfalden=$forfalden+$opp_amount;
					if ($renteamount[$x]) {
						$q3 = db_select ("select * from varer where id IN (select yb from formularer where beskrivelse='GEBYR' and formular='6')",__FILE__ . " linje " . __LINE__);
						if ($r3 = db_fetch_array($q3)) {
							$beskrivelse=db_escape_string($r3['beskrivelse']);				
							$dd=date("Y-m-d");
							$pos++;
							db_modify("insert into ordrelinjer (posnr,ordre_id,vare_id,varenr,serienr,beskrivelse,antal,pris) values ($pos,'$rykker_id[$i]','$r3[id]','$r3[varenr]', '$dd', '$beskrivelse','1','$renteamount[$x]')",__FILE__ . " linje " . __LINE__);
							$sum=$sum+$renteamount[$x];
						}
					}
				} 
				$q2 = db_select ("select * from varer where id IN (select xb from formularer where beskrivelse='GEBYR' and formular='6')",__FILE__ . " linje " . __LINE__);
				if ($r2 = db_fetch_array($q2)) {				
					$gebyr=$r2['salgspris'];	
					if ($valutakurs)$gebyr=$gebyr*100/$valutakurs;
					$pos++;
					db_modify("insert into ordrelinjer (posnr,ordre_id, varenr, vare_id, beskrivelse, antal, pris, serienr) values ('$pos','$rykker_id[$i]', '$r2[varenr]', '$r2[id]', '$r2[beskrivelse]', '1', '$gebyr' , '$rykkerdate')",__FILE__ . " linje " . __LINE__);
					$forfalden=$forfalden+$r2['salgspris'];
					$sum=$sum+$gebyr;
					db_modify("update ordrer set sum='$sum' where id=$rykker_id[$i]",__FILE__ . " linje " . __LINE__);
				}
			}
##########################################################################################################################		
		} else {
			$r = db_fetch_array(db_select("select * from ordrer where id = '$rykker_id[$i]'",__FILE__ . " linje " . __LINE__));
			$rykkernr=substr($r['art'],-1);
			$rentesum=$r['sum'];
			$valuta=$r['valuta'];
			$fakturadate=$r['fakturadate'];
			$rykkernr++;
			if ($valuta && $valuta!='DKK') { #20140707
				if ($r2= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr::INT and valuta.valdate <= '$rykkerdate' order by valuta.valdate",__FILE__ . " linje " . __LINE__))) {
					$valutakurs=$r2['kurs'];
				} else {
					$tmp = dkdato($ordredate);
					print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
				}
			} else {
				$valuta='DKK';
				$valutakurs=100;
			}
			if ($rykkernr<=3) {
				$art="R".$rykkernr;
				$r1=db_fetch_array(db_select("select * from adresser where id ='$r[konto_id]'",__FILE__ . " linje " . __LINE__));
				$r_fakturanr=$r['ordrenr']."-".$rykkernr;
					db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, levdate, fakturadate, momssats, hvem, tidspkt, ref,status,valuta,valutakurs,fakturanr,email,betalt,kontakt,mail_fakt) 
					values ('$r[ordrenr]', '$r[konto_id]', '$r1[kontonr]', '".db_escape_string($r1['firmanavn'])."', '".db_escape_string($r1['addr1'])."', '".db_escape_string($r1['addr2'])."', '".db_escape_string($r1['postnr'])."', '".db_escape_string($r1['bynavn'])."', '".db_escape_string($r1['land'])."', '$ffdage3', 'Netto', '".db_escape_string($r1['cvrnr'])."', '".db_escape_string($r1['ean'])."', '".db_escape_string($r1['institution'])."', '".db_escape_string($r1['notes'])."', '$art', '$rykkerdate', '$rykkerdate', '$rykkerdate', '0', '$brugernavn', '$tidspkt', '".db_escape_string($r['ref'])."', '2','$valuta', '$valutakurs','$r_fakturanr','".db_escape_string($r['email'])."','-','".db_escape_string($r['kontakt'])."','$r[mail_fakt]')",__FILE__ . " linje " . __LINE__);
				$r= db_fetch_array(db_select("select id from ordrer where ordrenr ='$r[ordrenr]' and art = '$art' and fakturanr = '$r_fakturanr' and betalt != 'on'",__FILE__ . " linje " . __LINE__)); #Henter ordrelinjer fra basisrykker.
				$ny_rykker_id[$i]=$r['id'];
				$pos=0;
				$q2 = db_select("select * from ordrelinjer where ordre_id = '$rykker_id[$i]' order by posnr",__FILE__ . " linje " . __LINE__);
				while ($r2=db_fetch_array($q2)) { #og indsætter dem i den nye rykker
					if (!$r2['vare_id'] && $r2['enhed']) {
						$pos++;
						db_modify("insert into ordrelinjer (posnr,enhed, ordre_id, serienr, beskrivelse) values ('$pos','$r2[enhed]', '$ny_rykker_id[$i]', '$r2[serienr]', '$r2[beskrivelse]')",__FILE__ . " linje " . __LINE__);
						$r3=db_fetch_array(db_select("select amount from openpost where id = '$r2[enhed]'",__FILE__ . " linje " . __LINE__));
						$rentesum=$rentesum+$r3['amount'];
					}
				}
				$r2=db_fetch_array(db_select("select * from ordrer where id = '$rykker_id[$i]'",__FILE__ . " linje " . __LINE__));
				if ($r2['sum'])	{ # Henter gebyrinformation fra basisrykker.
				$r3=db_fetch_array(db_select("select sum(pris) AS sum from ordrelinjer where ordre_id = '$rykker_id[$i]' and vare_id > 0",__FILE__ . " linje " . __LINE__));
				if ($r3['sum'] != $r2['sum']) {
					$tmp = $r3['sum']*1;
					db_modify("update ordrer set sum = '$tmp' where id = '$rykker_id[$i]'",__FILE__ . " linje " . __LINE__);
					$r2['sum'] = $tmp;
				}		
					$r4=db_fetch_array(db_select("select * from openpost where refnr = '$rykker_id[$i]' and konto_id='$r2[konto_id]' and amount='$r2[sum]'",__FILE__ . " linje " . __LINE__));
					# Og indsætter disse i den nye rykker.
					$pos++;
					db_modify("insert into ordrelinjer (posnr,enhed, ordre_id, serienr, beskrivelse) values ('$pos','$r4[id]', '$ny_rykker_id[$i]','$r2[fakturadate]', '".db_escape_string($r4['beskrivelse'])."')",__FILE__ . " linje " . __LINE__);
#					}
				}
				$formular=$rykkernr+5; # fordi rykker 1 har formular nr. 6, rykekr 2 nr. 7 osv.
				# Og tilføjer rykkergebyr
				if ($rykkernr==2 && $rentesats_2) $renteamount[$x] = find_rente($rentesats_2,$fakturadate,$rentesum);
				elseif ($rykkernr==3 && $rentesats_3) $renteamount[$x] = find_rente($rentesats_3,$fakturadate,$rentesum);
				else $renteamount[$x]=0;
				if ($renteamount[$x]) {
					$q3 = db_select ("select * from varer where id IN (select yb from formularer where beskrivelse='GEBYR' and formular='$formular')",__FILE__ . " linje " . __LINE__);
					if ($r3 = db_fetch_array($q3)) {
						$beskrivelse=db_escape_string($r3['beskrivelse']);				
						$dd=date("Y-m-d");
						$pos++;
						db_modify("insert into ordrelinjer (posnr,ordre_id,vare_id,varenr,serienr,beskrivelse,antal,pris) values ($pos,'$ny_rykker_id[$i]','$r3[id]','$r3[varenr]', '$dd', '$beskrivelse','1','$renteamount[$x]')",__FILE__ . " linje " . __LINE__);
						$sum=$sum+$renteamount[$x];
					}
				}
				$q2 = db_select ("select * from varer where id IN (select xb from formularer where beskrivelse='GEBYR' and formular='$formular')",__FILE__ . " linje " . __LINE__);
				if ($r2 = db_fetch_array($q2)) {				
					$gebyr=$r2['salgspris'];
					$sum=$sum+$gebyr;
					if ($valutakurs) {
						$gebyr=$gebyr*100/$valutakurs;r>
						$sum=$sum*100/$valutakurs;
					}	
					db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, pris, serienr) values ('$ny_rykker_id[$i]', '".db_escape_string($r2['varenr'])."', '$r2[id]', '".db_escape_string($r2['beskrivelse'])."', '1', '$gebyr' , '$rykkerdate')",__FILE__ . " linje " . __LINE__);
					db_modify("update ordrer set sum='$sum' where id=$rykker_id[$i]",__FILE__ . " linje " . __LINE__);
				}
			} else {
				if ($topniveau) $topniveau=$topniveau.", "; 
				$topniveau=$topniveau.$r['ordrenr'];
			}
		}
	}
}	 
if ($topniveau) print "<BODY onLoad=\"javascript:alert('Topniveau nået for rykkere med l&oslash;benr $topniveau')\">";
print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";

#############################################################################
function find_rente ($rentesats,$forfaldsdate,$amount) {
	list($f_year,$f_month,$f_day)=explode("-",$forfaldsdate);
	$year=date("Y");
	$month=date("m");
	$day=date("d");
	$maaneder=$month-$f_month;
	$maaneder=$maaneder+($year-$f_year)*12;
	if ($day>=$f_day) $maaneder=$maaneder+1;
	
	$rente=0;
	for ($x=1;$x<=$maaneder;$x++) {
		$rente=$rente+(($amount+$rente)*$rentesats/100);
	}
	$rente=round($rente,2);
	return($rente);
}
?>