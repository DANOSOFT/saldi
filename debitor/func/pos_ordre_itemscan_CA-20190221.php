<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------- debitor/func/pos_ordre_itemscan.php ---- lap 3.7.5 -- 2019.02.15 --
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
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------------
//
// 2014.05.08 - Indsat diverse til bordhåndtering, bruger nr fra ordrer til bordnummer (PHR - Danosoft) Søg 20140508 eller $bordnr 
// 2014.07.02 - Ovenstående gav fejl v scanning efter scanning uden enter  - pris fra vare 1 blev hængende
//		samt fejl v. menuklig efter scanning - vare forsvandt (PHR - Danosoft) - søg 20140702
// 2014.07.04	-	For visse varer ligger prisen i stregkoden. Det kan vi nu også. - søg 20140704
// 2014.08.14 - Diverse rettelser af ændring fra 20140624. Ved klik på pris eller rabat blev antal sat til 1 og ikke taget fra $antal_old mm. # 20140814
// 2014.08.14 - usdecimal sat foran 'rabat' ved 'opret_ordrelinje' da rabat ellers blev afrundet til heltal.
// 2014.08.14 - Funktion find_kasse - tjekker nu for om den kasse som hentes fra cookie eksisterer
// 2015.03.10 - Fjernet søgning efter betalingsbet da alt skal bogføres gennem pos 20150310
// 2015.04.24	-	Sætter cookie for bordnr - søg cookie & bordnr
// 2015.05.05	-	rettet	"if ($nettosum" til "if (($nettosum || $nettosum == 0)" da 0 bon ellers ikke kunne afsluttes. Søg 20150505
// 2015.05.05	- Flyttet "$bordnr=$_COOKIE['saldi_bordnr'];" så den kun sættes hvis der ikke er brugerskift. Søg 20150505-2
// 2018-12-10 - CA  Gavekortfunktioner indlæses ved opstart 20181210
// 2019-01-06 - PHR Tilføjet mulighed for totalrabat - Søg 'totalrabat'  
// 2019-01-07 - PHR Kortbeløb kan nu rettes ved kasseoptælling - Søg 'change_cardvalue'  
// 2019-01-11 - PHR Decimalfejl i $udtages.   
// 2019-01-23 - LN	(pos_txt_print) Udtræk af momssatser fra de enkelte varelinjer til bruge for detaljeret bon. 20190123
// 2019-02-15 - CA  The function varescan (itemscan in English) created as an independent file.

function varescan ($id,$momssats,$varenr_ny,$antal_ny,$pris_ny,$rabat_ny,$lager_ny) {
	print "\n<!-- Function varescan (start)-->\n";
	global $afd_navn;
	global $afslut;
	global $brugernavn;
	global $fokus;
	global $ifs;
	global $kontonr;
	global $betalingsbet;
	global $difkto;
	global $bordnr;  #20140508
	global $kundedisplay;
	global $lagerantal,$lagernavn,$lagernr;
	global $pris;
	global $status,$sum;
	global $varenr_ny;
	global $vis_saet;
	global $vatrate;
	global $gavekortnummer;
		
	$konto_id=NULL;
	$vare_id=NULL;
	
	for ($l=0;$l<count($lagernr);$l++){
		if ($lager_ny==$lagernr[$l] && strlen($lagernavn[$l])==1) $lager_ny=$lagernavn[$l];
	}
	
	if ($id) {
		$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$konto_id=$r['konto_id'];
		$kontonr=$r['kontonr'];
		$firmanavn=$r['firmanavn'];
		$addr1=$r['addr1'];
		$post_by=$r['postnr']." ".$r['bynavn'];
		$status=$r['status'];
		$kundeordnr=$r['kundeordnr'];
		$betalingsbet=$r['betalingsbet'];
		$bordnr=$r['nr'];
		if (!$r['firmanavn']) $betalingsbet='Kontant';
		if ($status >= 3) {
			$ref=$r['ref'];
			$fakturanr=$r['fakturanr'];
			$kasse=$r['felt_5'];
			$fakturadato=dkdato(substr($r['fakturadate'],0,10));
			$tidspkt=substr($r['tidspkt'],-5);
			if (!$tidspkt) {
				$r2=db_fetch_array(db_select("select logtime from transaktioner where ordre_id = '$id'",__FILE__ . " linje " . __LINE__));
				$tidspkt=substr($r2['logtime'],0,5);
				$tmp=$r['fakturadate']." ".$tidspkt;
#cho "update ordrer set tidspkt = '$tmp' where id = '$id'<br>\n";
				db_modify("update ordrer set tidspkt = '$tmp' where id = '$id'",__FILE__ . " linje " . __LINE__);
			}
		} else $ref=$brugernavn;
		if ($bordnr || $bordnr=='0') { #20150415
			$r = db_fetch_array(db_select("select box2,box3,box4,box7,box10 from grupper where art = 'POS' and kodenr='2'",__FILE__ . " linje " . __LINE__)); 
			($r['box7'])?$bord=explode(chr(9),$r['box7']):$bord=NULL;
			$bordnavn=$bord[$bordnr];
		}
		if ($status >= 3) {
#			$betaling=$r['felt_1'];
#			$modtaget=$r['felt_2'];
#			$betaling2=$r['felt_3'];
#			$modtaget2=$r['felt_4'];
			$x=0;
			$q=db_select("select * from pos_betalinger where ordre_id = '$id' order by id",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				if ($x=='0') {
					$betaling=$r['betalingstype'];
					$modtaget=$r['amount'];
					$betvaluta=$r['valuta'];
					$betvalkurs=$r['valutakurs'];
				} elseif ($x=='1' && $betaling!='Konto') {
					$betaling2=$r['betalingstype'];
					$modtaget2=$r['amount'];
					$betvalkurs=$r['valutakurs'];
				}
				$x++;
			}
		} else {
			$fakturanr=NULL;
			$fakturadato=NULL;
			$kasse=NULL;
			$tidspkt=NULL;
		}

		if ($ref) {
			if ($r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$ref'",__FILE__ . " linje " . __LINE__))) {
				$ansat_id=$r['ansat_id']*1;
				if ($r=db_fetch_array(db_select("select navn from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__))) $ref=$r['navn'];
			}
		}
	}
	if ($konto_id && $betalingsbet!='Kontant') {
		$r=db_fetch_array(db_select("select kreditmax from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		$kreditmax=$r['kreditmax'];
		$r=db_fetch_array(db_select("select sum(amount) as saldo from openpost where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		$saldo=$r['saldo'];
	}

	if ($varenr_ny) {
		$varenr_ny=db_escape_string($varenr_ny);
		$varenr_low=strtolower($varenr_ny);
		$varenr_up=strtoupper($varenr_ny);
		$qtxt="SELECT id,vare_id,variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr_up'";
		if (strlen($varenr_ny)==12 && is_numeric($varenr_ny)) $qtxt.=" or variant_stregkode='0$varenr_ny'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$vare_id=$r['vare_id'];
			$variant_type=$r['variant_type'];
			$variant_id=$r['id'];
		} else {
			$variant_id=0;
			$variant_type='';
		}
		# 20140704 ->
		$prisIkode=0;
		if (!$vare_id && is_numeric($varenr_ny) && strlen($varenr_ny)=='13') {
			$tmp=substr($varenr_ny,0,7)."XXXXXX";

			$qtxt="select * from varer where stregkode = '$tmp'";
			if ($r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__))) {
				$prisIkode=1;
			}
			if (!$prisIkode) $tmp=substr($varenr_ny,0,6)."XXXXXXX";
			$qtxt="select * from varer where stregkode = '$tmp'";
			if ($r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__))) {
				$prisIkode=1;
			}
		}	
		if ($prisIkode) $vnr=$tmp;
		# <- 20140703 + else foran if herunder.
		elseif ($vare_id) $qtxt="select * from varer where id='$vare_id'";
		else {
			$qtxt="select * from varer where lower(varenr) = '$varenr_low' or upper(varenr) = '$varenr_up' or varenr LIKE '$varenr_ny' ";
			$qtxt.="or lower(stregkode) = '$varenr_low' or upper(stregkode) = '$varenr_up' or stregkode LIKE '$varenr_ny'";
			if (strlen($varenr_ny)==12 && is_numeric($varenr_ny)) $qtxt.=" or stregkode='0$varenr_ny'";
		}
		if ($r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__))) {
#		  $varenr_ny=db_escape_string($r['varenr']);
			$lukket[0]=$r['lukket'];
			$beskrivelse[0]=$r['beskrivelse'];
			$kostpris[0]=$r['kostpris'];
			if ($prisIkode) {
				$pris[0]=find_pris($varenr_ny)*1;
				$r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
				$momsfri = $r2['box7'];
				if ($r2['momsfri']) $kostpris[0]=$pris[0]*$kostpris[0];
				else $kostpris[0]=$pris[0]*100/(100+$momssats)*$kostpris[0];
				$varenr_ny=$vnr;
			} else $pris[0]=find_pris($r['varenr'])*1;
			if ($pris[0]) {
				$pris[0]=dkdecimal($pris[0],2);
			}
			else $pris[0]="";
			if ($fokus!="pris_ny" && $fokus!="rabat_ny") $fokus="antal_ny";
		} elseif ($variant_id) {
			$qtxt="delete from variant_varer where id='$variant_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$txt="$varenr_ny tilhører en variant som er tilknyttet en slettet vare. Varianten slettes";
			return ($txt);
		} else {
			return ("fejl".chr(9)."".chr(9)."Varenr: $varenr_ny eksisterer ikke");
		}
		if ($lukket[0]) {
			alert("$varenr[0] $beskrivelse[0] er udgået!");
		}
		if ($variant_type) {
			$varianter=explode(chr(9),$variant_type);
			for ($y=0;$y<count($varianter);$y++) {
				$r1=db_fetch_array(db_select("select variant_typer.beskrivelse as vt_besk,varianter.beskrivelse as var_besk from variant_typer,varianter where variant_typer.id = '$varianter[$y]' and variant_typer.variant_id=varianter.id",__FILE__ . " linje " . __LINE__));
				$beskrivelse[0].=", ".$r1['var_besk'].":".$r1['vt_besk'];
			}
		}
	} else $fokus="varenr_ny";
	if ($kontonr) {
		print "<tr><td><b>$kontonr</b></td><td colspan=\"2\">\n";
		if ($status<3) print "Rekv.nr:&nbsp; <input type=\"text\" size=\"15\" name=\"kundeordnr\" value=\"$kundeordnr\">\n";
		elseif ($kundeordnr) print "&nbsp; Rekv.nr:&nbsp; $kundeordnr</td>\n";
		($bordnavn)?$tmp=" Bord: $bordnavn |":$tmp='';  
		if ($status>=3) print "</td><td colspan=\"2\" align=\"right\">Ekspedient: $ref |$tmp Bon: $fakturanr</td>\n";
		print "</tr>\n<tr><td colspan=\"2\"><b>$firmanavn</b></td>\n";
		if ($status>=3) print "<td colspan=\"4\" align=\"right\">Kasse: $kasse | $fakturadato kl. $tidspkt</td></tr>\n";
		if (!$vis_saet) {
			if ($betalingsbet!='Kontant') list($betalingsbet,$kreditmax,$saldo)=explode(";",find_saldo($konto_id,$sum,$moms));
			if ($betalingsbet=='Kontant') print "<tr><td colspan=\"2\"><b>Ingen kredit</b></td>\n";
		}
	} elseif ($status>=3) {
		($bordnavn)?$tmp=" Bord: $bordnavn |":$tmp='';  
		print "<tr><td colspan=\"6\" align=\"right\">Ekspedient: $ref |$tmp Bon: $fakturanr</td></tr>\n";
		print "<tr><td colspan=\"6\" align=\"right\">Kasse: $kasse | $fakturadato kl. $tidspkt</td></tr>\n";
	}
  print "<tr><td colspan='6'>Gavenummer $gavekortnummer</td></tr>";
	print "<tr><td style=\"width:20%;height:25px;\" valign=\"bottom\">Varenummer</td>\n";
	print "<td style=\"width:7%\" valign=\"bottom\">Antal</td>\n";
	if ($lagerantal>1) print "<td style=\"width:7%\"valign=\"bottom\">Lager</td>\n";
	print "<td valign=\"bottom\">Varenavn</td>\n";
	print "<td style=\"width:13%\" align=\"right\" valign=\"bottom\">Pris</td>\n";
 	if ($fokus=="rabat_ny") print "<td colspan=\"2\" align=\"right\" valign=\"bottom\">Rabat</td></tr>\n";
 	print "<td style=\"width:13%\" align=\"right\" valign=\"bottom\">Sum</td><td style=\"width:80px\"><br></td></tr>\n";
	print "<tr><td colspan=\"7\"><hr></td></tr>\n";
	if ($status < 3) {
		if(isset($_GET['ret']) && is_numeric($_GET['ret']) && !$antal_ny) {
			$ret=$_GET['ret']*1;
			$qtxt="select varenr,variant_id,antal,lager,beskrivelse,pris,kostpris,momssats,momsfri,rabat,leveret from ordrelinjer where id = '$ret'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$varenr_ny=$r['varenr'];
			$lager_ny=$r['lager'];
			$antal_old=dkdecimal($r['antal'],2);
			$rabat_old=dkdecimal($r['rabat'],2);
			$beskrivelse[0]=$r['beskrivelse'];
			$leveret[0]=$r['leveret'];
			$fokus="antal_ny";
			if ($r['momsfri'] && $r['momssats']==0) $pris[0]=dkdecimal($r['pris'],2);
			else $pris[0]=dkdecimal($r['pris']+$r['pris']*$r['momssats']/100,2);
			$pris_old=$pris[0]; #20140814
			$kostpris[0]=$r['kostpris'];
			if ($r['variant_id']) {
				$qtxt="select variant_stregkode from variant_varer where id='$r[variant_id]'";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$varenr_ny=$r2['variant_stregkode'];
			}
			db_modify("delete from ordrelinjer where id = '$ret'",__FILE__ . " linje " . __LINE__);
			if (isset($_GET['saet']) && $saetnr=$_GET['saet']) {
				db_modify("delete from ordrelinjer where ordre_id='$id' and saet = '$saetnr'",__FILE__ . " linje " . __LINE__);
			}
		} else {
			$antal_old=$antal_ny;
			$rabat_old=$rabat_ny;
			$pris_old=$pris_ny; #20140702
#			$beskrivelse[0]=''; Må ikke aktiveres!
			$leveret[0]=0;
		}
		print "<input type=\"hidden\" name = \"fokus\" value=\"$fokus\">\n";
		print "<input type=\"hidden\" name = \"pre_bordnr\" value=\"$bordnr\">\n";  #20140508
		#print "<input type=\"hidden\" name = \"vare_id\" value=\"$vare_id[0]\">\n";
		print "<input type=\"hidden\" name = \"momssats\" value=\"$momssats\">\n";
		if (isset($beskrivelse[0])) print "<input type=\"hidden\" name = \"beskrivelse_ny\" value=\"$beskrivelse[0]\">\n";
		print "<input type=\"hidden\" name = \"leveret\" value=\"$leveret[0]\">\n";
		print "<input type=\"hidden\" name = \"antal\" value=\"$antal_old\">\n";
		print "<input type=\"hidden\" name = \"lager\" value=\"$lager_ny\">\n";
		if ($fokus=='pris_ny') print "<input type=\"hidden\" name = \"pris\" value=\"$pris_old\">\n";
		print "<tr><td width=\"30px\">";
		print "<input class=\"inputbox\" type=\"text\" style=\"width:120px;font-size:$ifs;\" name = \"varenr_ny\" value=\"$varenr_ny\">";
		print "</td>\n"; 	
		if ($varenr_ny) {
			if (!$antal_old && $antal_old!='0') $antal_old='1,00'; #20140814 
			print "<td width=\"7px\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right;font-size:$ifs;width:40px\" name=\"antal_ny\" placeholder=\"$antal_old\" value=\"$antal_ny\"></td>";
			if ($lagerantal>1) {
				for ($l=0;$l<count($lagernr);$l++){
					if ($lagernr[$l]==$lager_ny && strlen($lagernavn[$l])==1) $lager_ny=$lagernavn[$l]; 
				}
				print "<td align=\"center\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right;font-size:$ifs;width:40px\" name=\"lager_ny\" placeholder=\"$lager_ny\" value=\"$lager_ny\"></td>\n";
			}
			if ($antal_ny) {
				# Slår op om varen er et gavekort
				if ( er_gavekort($varenr_ny) ) {	# 20181202
					$fokus="pris_ny";		# 20190215
					print "<td >Gavekortnr: ";
					print "<input class=\"inputbox\" type=\"text\" style=\"background:orange;  text-align:right;font-size:$ifs;width:120px\" name=\"gavekortnummer\" value=\"".nytgavekortnummer($gavekortnummer)."\" /></td>\n"; # 20190215 > felt
					print "<input type=hidden name=\"pris_old\" value=\"$pris_old\">\n" ; #20140702
					print "<td align=\"right\" title=\"Kostpris ex. moms: ".dkdecimal($kostpris[0],2)."\">";
					print "<input class=\"inputbox\" type=\"text\" style=\"text-align:right;font-size:$ifs;width:60px\" name = \"pris_ny\"  placeholder=\"$pris_old\" >";
					print "</td>\n";
				} else {
					print "<td>".$beskrivelse[0]."</td>\n";
					print "<input type=hidden name=\"pris_old\" value=\"$pris_old\">\n" ; #20140702
					print "<td align=\"right\" title=\"Kostpris ex. moms: ".dkdecimal($kostpris[0],2)."\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right;font-size:$ifs;width:60px\" name = \"pris_ny\"  placeholder=\"$pris_old\" value=\"\"></td>\n";
				}
			} else {
				print "<td>".$beskrivelse[0]."</td>\n";
				print "<input type=hidden name=\"pris_ny\" value=\"$pris[0]\" />\n";
				print "<td align=\"right\" title=\"Kostpris ex. moms: ".dkdecimal($kostpris[0],2)."\">$pris[0]</td>\n";
			}
			if ($pris_ny && $fokus=="rabat_ny") {

#			$r=db_fetch_array(db_select("select box8 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
#				$rabatvareid=$r['box8']*1;
#				if (db_fetch_array(db_select("select varenr from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__))) {
					print "<input type=hidden name=\"rabat_old\" value=\"$rabat_old\" />\n";
					print "<td colspan=\"2\" align=\"right\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right;font-size:$ifs;width:40px\" name = \"rabat_ny\" placeholder=\"$rabat_old\"></td>\n";
#				} else {
#					$txt="Manglende varenr til rabat";
#					print "<BODY onLoad=\"javascript:alert('$txt')\">\n";
#					return($txt);
#				}
			} else {
				print "<input type=hidden name=\"rabat_ny\" value=\"$rabat_old\" />\n";
				if ($rabat_old && $rabat_old!='0,00') print "<td colspan=\"2\" align=\"right\">$rabat_old%</td>\n";
			}
		}
		print "</tr>\n";
	}

	list($sum,$rest,$afrundet,$kostsum)=explode(chr(32),vis_pos_linjer($id,$momssats,$status,$pris[0]));
	if ($konto_id && $kreditmax != 0 && $sum+$moms > $kreditmax - $saldo && $status < '3') {
		$ny_saldo=$saldo+$sum+$moms;
		$txt = "Kreditmax: ".dkdecimal($kreditmax,2)."<br>Gl. saldo :  ".dkdecimal($saldo,2)."<br>Ny saldo :  ".dkdecimal($ny_saldo,2);
		#print "<BODY onLoad=\"javascript:alert('$txt')\">\n";
		alert("$txt");
	}
	print "<input type=\"hidden\" name = \"sum\" value = \"$sum\" />\n";
	print "<input type=\"hidden\" name = \"afrundet\" value = \"$afrundet\" />\n";
	if ($sum || !$konto_id) {
		if (!$afrundet) $afrundet=$sum;
		if ($afslut || $status>=3) {
			if ($betaling=='Kontant' && !$betaling2) $tmp=dkdecimal($modtaget-$afrundet,2);
			elseif ($betalingsbet=='Kontant' && $modtaget+$modtaget2==pos_afrund($modtaget+$modtaget2,$difkto,'')) {
				$tmp=dkdecimal($modtaget+$modtaget2-$afrundet,2);
			}
			elseif ($betaling!='Kontant' && !$betaling2) {
				$tmp=dkdecimal($modtaget-$sum,2);
			}
			elseif ($betaling=='Kontant' && $betaling2 != 'Kontant') $tmp=dkdecimal($modtaget+$modtaget2-$sum,2);
// 			else $tmp=dkdecimal($modtaget-$afrundet);
			$tmp=dkdecimal(pos_afrund($rest*-1,$difkto,''),2);
			if ($betaling != "Konto" || $betalingsbet=='Kontant') {
				print "<tr><td><b>Retur";
				if ($betvaluta!= 'DKK') print " DKK";
				print "</b></td><td colspan=\"4\" align=\"right\"><b>$tmp</b></td></tr>\n";
				if ($kundedisplay) {
					kundedisplay('Modtaget',$modtaget+$modtaget2,0);
					kundedisplay('Retur',$tmp,0);
				}
			}
		}
	} elseif ($status >= 3) {
		$r=db_fetch_array($q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		print "<tr><td>Saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($r[felt_3],2)."</td></tr>\n";
		$indbetaling=($r['felt_4']-$r['felt_3'])*-1;
		print "<tr><td colspan=\"3\">Indbetaling - (Det beløb der skal indsættes på kontoen!)</td><td rowspan=\"2\" colspan=\"1\" align=\"right\">".dkdecimal($indbetaling,2)."</td></tr>\n";
		print "<tr><td colspan=\"3\">Indbetaling - (Det beløb der skal indsættes på kontoen!)</td>";
		print "<tr><td>$r[felt_1]</td><td colspan=\"4\" align=\"right\">".dkdecimal($r['felt_2'],2)."</td></tr>\n";
		$ny_saldo=$r['felt_4'];
		print "<tr><td>Ny saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($ny_saldo,2)."</td></tr>\n";
		$retur=pos_afrund($r['felt_2']+$r['felt_4']-$r['felt_3'],$difkto,'');
		print "<tr><td>Retur</td><td  colspan=\"4\" align=\"right\">".dkdecimal($retur,2)."</td></tr>\n";
	}
	print "<tr><td colspan=\"6\" align=\"right\"><input  STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
	if ($kontonr && $status<3 && ($betalingsbet!='Kontant' || $saldo)) { #20161001
		print "<tr><td>Gl. saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($saldo,2)."</td></tr>\n";
		$ny_saldo=$saldo+$sum;
		print "<tr><td>Ny saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($ny_saldo,2);
		if ($pris[0]) print "<br>(".dkdecimal($ny_saldo+$pris[0],2).")";
		print "</td></tr>\n";
		if ($kreditmax) {
			print "<tr><td>Kreditmax</td><td colspan=\"4\" align=\"right\">".dkdecimal($kreditmax,2)."</td></tr>\n";
		}
	}
	print "\n<!-- Function varescan (slut)-->\n";
	return ($varenr_ny.chr(9).$pris_ny.chr(9).$status);
} # endfunc varescan.

