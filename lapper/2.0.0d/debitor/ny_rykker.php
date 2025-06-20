<?php #topkode_start
@session_start();
$s_id=session_id();

// -----------------------debitor/ny_rykker.php-----lap 2.0.0c---30.04.2008-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaetelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------

// --------------------- Bekrivelse ------------------------
// Ved generering af en rykker oprettes en ordre med art = R1. Hver ordre der indgår i rykkeren oprettes som en ordrelinje
// hvor feltet enhed indeholder id fra openpost tabellen og serienr indeholder forfaldsdatoen,.Beskrivelse indeholde beskrivelse.
// Da varenrfelt er tomt vil linjerne blive opfattes som kommentarlinjer ved bogføring
// Rykkergebyr tilføjes som en ordinær ordrelinje.med varenummer for rykkergebyr, og vil derfor blive behandlet som den eneste reelle ordlinje
// ved bogføring.
// Ved generering af rykker "2" medtages evt gebyr fra rykker 1 på samme måden som v. ovenstående. 
		
$topniveau=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/formfunk.php");
include("../includes/std_func.php");
include("../includes/db_query.php");
include("../includes/forfaldsdag.php");

$konto_antal=$_GET['kontoantal'];
$rykker_id=explode(";", $_GET['rykker_id']);
$konto_id =explode(";", $_GET['kontoliste']);

for ($i=0; $i<$konto_antal; $i++) {
	$forfalden=0;
	if (($konto_id[$i])||($rykker_id[$i])) {
		$rykkerdate=date("Y-m-d");
		if (!$rykker_id[$i]) {
			$r_ordrenr=0;
			if ($r = db_fetch_array(db_select("select MAX(ordrenr) as r_ordrenr from ordrer where art LIKE 'R%'"))) $r_ordrenr=$r['r_ordrenr'];
			$r_ordrenr++;
			$r= db_fetch_array(db_select("select * from adresser where id ='$konto_id[$i]'"));
			$r2 = db_fetch_array(db_select("select box1, box3, box4, box6 from grupper where art='DG' and kodenr='$r[gruppe]'"));
			$valuta=$r2['box3'];
			$sprog=$r2['box4'];
			if ($valuta && $valuta!='DKK') {
				if ($r2= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr and valuta.valdate <= '$rykkerdate' order by valuta.valdate"))) {
					$valutakurs=$r2['kurs'];
				} else {
					$tmp = dkdato($ordredate);
					print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
				}
			} else {
				$valuta='DKK';
				$valutakurs=100;
			}
			db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, levdate, fakturadate, momssats, hvem, tidspkt, ref, status, valuta) 
				values ('$r_ordrenr', '$konto_id[$i]', '$r[kontonr]', '$r[firmanavn]', '$r[addr1]', '$r[addr2]', '$r[postnr]', '$r[bynavn]', '$r[land]', '$r[betalingsdage]', '$r[betalingsbet]', '$r[cvrnr]', '$r[ean]', '$r[institution]', '$r[notes]', 'R1', '$rykkerdate', '$rykkerdate', '$rykkerdate', '0', '$brugernavn', '$tidspkt', '$r[ref]', '2', '$valuta')");
			$r= db_fetch_array(db_select("select id from ordrer where ordrenr ='$r_ordrenr' and art = 'R1'"));
			$rykker_id[$i]=$r['id'];
			$id=$konto_id[$i];
			$q2 = db_select("select * from openpost where konto_id = '$id' and udlignet != 1");
#			$r= db_fetch_array(db_select("select * from adresser where id ='$konto_id[$i]'"));
			while ($r2 = db_fetch_array($q2)) {
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
				$q3 = db_select("select betalingsbet, betalingsdage from ordrer where fakturanr='$r2[faktnr]'");
				$r3 = db_fetch_array($q3);
				$forfaldsdag=usdate(forfaldsdag($r2['transdate'], $r3['betalingsbet'], $r3['betalingsdage']));
				if ($forfaldsdag<date("Y-m-d")) {
					db_modify("insert into ordrelinjer (enhed, ordre_id, serienr, beskrivelse) values ('$r2[id]', '$rykker_id[$i]', '$r2[transdate]', '$beskrivelse')");
					$forfalden=$forfalden+$opp_amount;
				}
			} 
			$q2 = db_select ("select * from varer where id IN (select xb from formularer where beskrivelse='GEBYR' and formular='6')");
			if ($r2 = db_fetch_array($q2)) {				
				$gebyr=$r2['salgspris'];	
				if ($valutakurs)$gebyr=$gebyr*100/$valutakurs;
				db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, pris, serienr) values ('$rykker_id[$i]', '$r2[varenr]', '$r2[id]', '$r2[beskrivelse]', '1', '$gebyr' , '$rykkerdate')");
					$forfalden=$forfalden+$r2['salgspris'];
					db_modify("update ordrer set sum='$gebyr' where id=$rykker_id[$i]");
			}
		} else {
			$r = db_fetch_array(db_select("select * from ordrer where id = '$rykker_id[$i]'"));
			$rykkernr=substr($r['art'],-1);
			$rykkernr++;
			if ($rykkernr<=3) {
				$art="R".$rykkernr;
				db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, levdate, fakturadate, momssats, hvem, tidspkt, ref, status) 
					values ('$r[ordrenr]', '$r[konto_id]', '$r[kontonr]', '$r[firmanavn]', '$r[addr1]', '$r[addr2]', '$r[postnr]', '$r[bynavn]', '$r[land]', '$r[betalingsdage]', '$r[betalingsbet]', '$r[cvrnr]', '$r[ean]', '$r[institution]', '$r[notes]', '$art', '$rykkerdate', '$rykkerdate', '$rykkerdate', '0', '$brugernavn', '$tidspkt', '$r[ref]', '2')");
				$r= db_fetch_array(db_select("select id from ordrer where ordrenr ='$r[ordrenr]' and art = '$art'")); #Henter ordrelinjer fra basisrykker.
				$ny_rykker_id[$i]=$r['id'];
				$q2 = db_select("select * from ordrelinjer where ordre_id = '$rykker_id[$i]'");
				while ($r2=db_fetch_array($q2)) { #og indsætter dem i den nye rykker
				if (!$r2['vare_id']) db_modify("insert into ordrelinjer (enhed, ordre_id, serienr, beskrivelse) values ('$r2[enhed]', '$ny_rykker_id[$i]', '$r2[serienr]', '$r2[beskrivelse]')");
				}
				$r2=db_fetch_array(db_select("select * from ordrer where id = '$rykker_id[$i]'"));
				if ($r2['sum'])	{ # Henter gebyrinformation fra basisrykker.
				$r3=db_fetch_array(db_select("select beskrivelse from ordrelinjer where ordre_id = '$rykker_id[$i]' and vare_id > 0"));
				$r4=db_fetch_array(db_select("select id from openpost where refnr = '$rykker_id[$i]' and konto_id='$r2[konto_id]' and amount='$r2[sum]'"));
				# Og indsætter disse i den nye rykker.
				db_modify("insert into ordrelinjer (enhed, ordre_id, serienr, beskrivelse) values ('$r4[id]', '$ny_rykker_id[$i]', '$r2[fakturadate]', '$r3[beskrivelse]')");
				}
				$formular=$rykkernr+5; # fordi rykker 1 har formular nr. 6, rykekr 2 nr. 7 osv.
				# Og tilføjer rykkergebyr
				$q2 = db_select ("select * from varer where id IN (select xb from formularer where beskrivelse='GEBYR' and formular='$formular')");
				if ($r2 = db_fetch_array($q2)) {				
					$gebyr=$r2['salgspris'];
					if ($valutakurs)$gebyr=$gebyr*100/$valutakurs;	
					db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, pris, serienr) values ('$ny_rykker_id[$i]', '$r2[varenr]', '$r2[id]', '$r2[beskrivelse]', '1', '$gebyr' , '$rykkerdate')");
					db_modify("update ordrer set sum='$gebyr' where id=$rykker_id[$i]");
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
