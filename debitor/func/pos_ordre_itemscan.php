<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------- debitor/func/pos_ordre_itemscan.php ---- lap 4.1.0 -- 2024.01.12 --
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
// Copyright (c) 2014-2024 saldi.dk aps
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
// 2020.06.03	- PHR Added scan for 'mylabel' barcodes
// 2020.06.14	- PHR Added scan for new barcode from 'mylabel'
// 2020.11.14 PHR Enhanged 'tilfravalg' add/remove to food items, (fx. extra bacon or no tomatoes in burger) $tilfravalgNy
// 2021.01.27 PHR Some minor design changes
// 2021.05.03 PHR - Qty now red if stock below minimum stock 20210503
// 20210810 LOE renamed some variables to accomodate bekrivelse_ny as new description
// 20210811 LOE commented out some codes and updated for beskrivelse_ny
// 20210812 LOE More updates on $beskrivelse_ny
// 20210812 LOE Added $credit_type variable here from pos_ordre.php for rabat new
// 20210820 LOE Translated title texts
// 20210824 PHR Various minor changes related to 'beskrivelse_ny'.
// 20200829 PHR As above..
// 20200902 PHR Preserves $beskrivelse_ny if fokus is pris_ny or rabat_ny
// 20200903 PHR Error correction in 'bordnr'
// 20200906	PHR reversed 20200902 as it diden't include variations changes made in pos_ordre.php insteadt.
// 20211006	PHR changed handling in barcode changed as it inserted a wrong item.
// 20211024	PHR Added $low & $high to deal with an error in mylabel.php.
// 20211115	PHR reversed 20211006 and added $low & $high.
// 20211214	PHR_Removed alert "stregkode ikke genkendt";
// 20220413 PHR Added jump2price
// 20220427 PHR Disabled submit button when clicked to avoid double posting.
// 20220614 PHR Added hidden input tilfravalgNy as tilfravalg was reset when changing price or rebate. See pos_ordre.php too 
// 20220726 PHR Added barcodeNew to be inserted into orderline colunm 'barcode'
// 20230613 PHR php8
// 20231220 PHR added htmlentities to #20210829
// 20240112 PHR Set $myDe to '-' if empty 
// 20240729 PHR Various translations


function varescan($id,$momssats,$varenr_ny,$antal_ny,$pris_ny,$beskrivelse_ny,$rabat_ny,$lager_ny) {
	print "\n<!-- Function varescan (start)-->\n";
	global $afd_navn,$afslut;
	global $barcode,$beskrivelse_old,$betalingsbet,$betvaluta,$bordnr,$brugernavn;
	global $db,$difkto;
	global $credit_type; #20210813
	global $fokus;
	global $ifs;
	global $kontonr,$kundedisplay;
	global $lagerantal,$lagernavn,$lagernr;
	global $moms;
	global $pris,$regnaar;
	global $sprog_id,$status,$sum;
	global $tilfravalgNy,$tracelog;
	global $varenr_ny,$vatrate,$vis_saet,$voucherNumber;

  $barcodeNew = NULL;
  if (($fokus == 'rabat_ny' || $fokus == 'pris_ny') && $_POST['barcodeNew']) {
		$varenr_ny = $_POST['barcodeNew'];
	}
	
 	$beskrivelse[0] = $betaling2=$konto_id=$myAc=$myDe=$myPr=$vare_id=NULL;
	$pris[0]        = 0;
	#($fokus == 'textNew')?$textNew = 1:$textNew = 0;
	($fokus == 'beskrivelse_ny')?$textNew = 1:$textNew = 0; #20210810
	while (substr($varenr_ny,-1) == chr(92)) { #removes '\' if last character 20200814
		$l=strlen($varenr_ny)-1;
		$varenr_ny=substr($varenr_ny,0,$l);
	}
	
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
				db_modify("update ordrer set tidspkt = '$tmp' where id = '$id'",__FILE__ . " linje " . __LINE__);
			}
		} else $ref=$brugernavn;
		if ($bordnr || $bordnr=='0') { #20150415
			$r = db_fetch_array(db_select("select box2,box3,box4,box7,box10 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__));
			($r['box7'])?$bord=explode(chr(9),$r['box7']):$bord=NULL;
			($bord)?$bordnavn=$bord[$bordnr]:$bordnavn='';
		}
		if ($status >= 3) {
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
		$prisIkode=$lotPris=0;
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
			if (!$prisIkode && substr($varenr_ny,0,4) == 2141) {
				$lotPris = substr($varenr_ny,6,6)/100;
				$lotKode = substr($varenr_ny,4,2);
				if ($lotKode == '95') $lotPris *= -1;
				elseif ($lotKode == '96') $lotPris *= -1;
				$varenr_ny = 'LOT'.substr($varenr_ny,0,6);
			}
		}
		if (strlen($varenr_ny)==12 && ctype_xdigit(substr($varenr_ny,-6)) && is_numeric(substr($varenr_ny,0,6))) {
			$low=hexdec(substr($varenr_ny,-6))/100 - 0.01; #20211024 Can be replaced by exact amount in 2023
			$high=hexdec(substr($varenr_ny,-6))/100 + 0.01;#20211024 Can be replaced by exact amount in 2023
			$qtxt = "select * from mylabel where barcode='$varenr_ny' and price >= '$low'  and price <= '$high' ";
			$qtxt.= " and id='". substr($varenr_ny,0,6)*1 ."'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$myAc    = $r['account_id'];
				$myPr    = $r['price'];
				$myDe    = $r['description'];
				if (!$myDe) $myDe = '-';
				$myCo    = $r['condition'];
				$barcodeNew = $r['barcode'];
				$qtxt    = "select kontonr from adresser where id='$myAc'";
			} else {
				$qtxt = "select * from mylabel where id='". substr($varenr_ny,0,6)*1 ."' and price >= '$low'  and price <= '$high'";
				if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { #20211021
					$myAc    = $r['account_id'];
					$myPr    = $r['price'];
					$myDe    = $r['description'];
					if (!$myDe) $myDe = '-';
					$myCo    = $r['condition'];
					$barcodeNew = $r['barcode'];
					$qtxt    = "select kontonr from adresser where id='$myAc'";
					alert('Stregkode ændret, kontroller label');	
				} else $qtxt=NULL; #20211214
			} 
			if ($qtxt) {
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				($myCo=='new')?$vnr="kn___$r[kontonr]":$vnr="kb___$r[kontonr]";
				$qtxt="select varenr,kostpris from varer where varenr like '$vnr'";
				if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$varenr_ny=$r['varenr'];
					$pris[0]=$myPr;
					$beskrivelse[0]=$myDe;
				} else $myAc=$myDe=$myPr=NULL;
			} 
		}
		if ((substr($varenr_ny,0,2) == 'kb' || substr($varenr_ny,0,2) == 'kn') && is_numeric(substr($varenr_ny,5,4))) {
			$qtxt = "select salgspris,kostpris from varer where varenr = '$varenr_ny'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if (!$r['salgspris'] && $r['kostpris'] > 0 && $r['kostpris'] < 1) {
				$qtxt = "select id from adresser where art = 'D' and kontonr = '". substr($varenr_ny,5,4) ."'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if (!$r['id']) alert("Varenr $varenr_ny er ikke tilknyttet en kunde"); 
			}
		}
		$s=0;
		$stockGrp = array();
		$qtxt = "select kodenr from grupper where art = 'VG' and box8 = 'on'";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$stockGrp[$s]=$r['kodenr'];
			$s++;
		}
		if ( $prisIkode ) $vnr=$tmp;
		# <- 20140703 + else foran if herunder.
		elseif ($vare_id) $qtxt="select * from varer where id='$vare_id'";
		else {
			$qtxt = "select * from varer where lower(stregkode) = '$varenr_low' or upper(stregkode) = '$varenr_up' ";
			$qtxt.= "or stregkode LIKE '$varenr_ny' limit 1";
			if (!$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__))) {
				$qtxt="select * from varer where lower(varenr) = '$varenr_low' or upper(varenr) = '$varenr_up' or varenr LIKE '$varenr_ny' ";
				$qtxt.="or lower(stregkode) = '$varenr_low' or upper(stregkode) = '$varenr_up' or stregkode LIKE '$varenr_ny'";
				if (strlen($varenr_ny)==12 && is_numeric($varenr_ny)) $qtxt.=" or stregkode='0$varenr_ny'";
			}
		}
			if ($qtxt && $r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__))) {
			$beholdning[0] = $r['beholdning'];
			$min_lager[0]  = $r['min_lager'];
			$itemGroup[0]  = $r['gruppe'];
			$lukket[0]     = $r['lukket'];
			$kostpris[0]   = $r['kostpris'];
			$beskrivelse[0] = $r['beskrivelse'];
			if ($varenr_ny != if_isset($_POST['antal_ny'], 0)){ #Then a new item is NOT scannet into the quantity field
				if ($myDe) $beskrivelse[0] = $myDe; # Then the barcode is scanned from a mysale label
				elseif (isset($_POST['beskrivelse_old'])) { # Then it is a correction af an existing orderline
					$beskrivelse[0] = $_POST['beskrivelse_old']; # Test scan from mysale and contineous scan if you change this.
					$myPr = $_POST['pris_old'];
				}
#			} elseif ($_POST['barcodeNew']) {
#			$qtxt = "select description from mylabel where barcode = '$_POST[barcodeNew]'";
#			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { #20211021
#				echo "$r[description] == $beskrivelse[0]<br>";
#			}
			}
			if ($prisIkode) {
				$pris[0]=find_pris($varenr_ny)*1;
				$qtxt = "select box6, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'";
				$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$momsfri = $r2['box7'];
				if ($r2['momsfri']) $kostpris[0]=$pris[0]*$kostpris[0];
				else $kostpris[0]=$pris[0]*100/(100+$momssats)*$kostpris[0];
				$varenr_ny=$vnr;
			} elseif($lotPris) {
				$pris[0]   = $lotPris;
			} elseif ($myPr) $pris[0]=$myPr;
			else $pris[0]=find_pris($r['varenr'])*1;
			if ($pris[0]) {
				$pris[0]=dkdecimal($pris[0],2);
			} 
			else $pris[0]="";
			if ($fokus!="pris_ny" && $fokus!="rabat_ny" && $fokus!="beskrivelse_ny") $fokus="antal_ny"; #20210811 beskrivelse_ny added
		} elseif ($variant_id) {
			$qtxt="delete from variant_varer where id='$variant_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$txt="$varenr_ny tilhører en variant som er tilknyttet en slettet vare. Varianten slettes";
			return ($txt);
		} else {
			vareopslag('PO',"",'beskrivelse', $id,"","$ref","*$varenr_ny*");
			exit;
#			return ("fejl".chr(9)."".chr(9)."Varenr: $varenr_ny eksisterer ikke");
		}
		if ($lukket[0]) {
			alert("$varenr[0] $beskrivelse[0] er udgået!");
		}
		if ($variant_type) {
			$varianter=explode(chr(9),$variant_type);
			for ($y=0;$y<count($varianter);$y++) {
				$qtxt = "select variant_typer.beskrivelse as vt_besk,varianter.beskrivelse as var_besk from variant_typer,varianter ";
				$qtxt.= "where variant_typer.id = '$varianter[$y]' and variant_typer.variant_id=varianter.id";
				$r1 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$beskrivelse[0].=", ".$r1['vt_besk']; #20200830
			}
		}
	} else $fokus="varenr_ny";
	if ($kontonr) {
		print "<tr><td><b>$kontonr</b></td><td colspan=\"2\">\n";
		if ($status<3) print findtekst('2129|Rekv. nr.', $sprog_id).": <input type=\"text\" size=\"15\" name=\"kundeordnr\" value=\"$kundeordnr\">\n";
		elseif ($kundeordnr) print "&nbsp; ".findtekst('2129|Rekv. nr.', $sprog_id).":&nbsp; $kundeordnr</td>\n";
		($bordnavn)?$tmp=" ".findtekst('932|Bord', $sprog_id).": $bordnavn |":$tmp='';  
		if ($status>=3) print "</td><td colspan=\"2\" align=\"right\">".findtekst('2252|Ekspedient', $sprog_id).": $ref |$tmp ".findtekst('2250|Bon', $sprog_id).": $fakturanr</td>\n";
		print "</tr>\n<tr><td colspan=\"2\"><b>$firmanavn</b></td>\n";
		if ($status>=3) print "<td colspan=\"4\" align=\"right\">".findtekst('931|Kasse', $sprog_id).": $kasse | $fakturadato - $tidspkt</td></tr>\n";
		if (!$vis_saet) {
			if ($betalingsbet!='Kontant') list($betalingsbet,$kreditmax,$saldo)=explode(";",find_saldo($konto_id,$sum,$moms));
			if ($betalingsbet=='Kontant') print "<tr><td colspan=\"2\"><b>".findtekst('1866|Ingen kredit', $sprog_id)."</b></td>\n";
		}
	} elseif ($status>=3) {
		($bordnavn)?$tmp=" ".findtekst('932|Bord', $sprog_id).": $bordnavn |":$tmp='';  
		print "<tr><td colspan=\"6\" align=\"right\">".findtekst('2252|Ekspedient', $sprog_id).": $ref |$tmp ".findtekst('2250|Bon', $sprog_id).": $fakturanr</td></tr>\n";
		print "<tr><td colspan=\"6\" align=\"right\">".findtekst('931|Kasse', $sprog_id).": $kasse | $fakturadato - $tidspkt</td></tr>\n";
	}
	print "<tr><td style='width:20%;height:25px;' valign='bottom'>". findtekst('320|Varenummer',$sprog_id) ."</td>";
	print "<td style='width:7%' valign='bottom'>". findtekst('916|Antal',$sprog_id) ."</td>";
	if ($lagerantal) {
		print "<td style='width:7%'valign='bottom'>". findtekst('608|Lager',$sprog_id) ."</td>";
	}
	print "<td valign='bottom'>". findtekst('967|Varenavn',$sprog_id) ."</td>";
	print "<td style='width:10%' align='right' valign='bottom'>". findtekst('915|Pris',$sprog_id) ."</td>";
	print "<td style='width:10%' align='right' valign='bottom'>Sum</td><td style='width:50px'><br></td></tr>";
	print "<tr><td colspan='7'><hr></td></tr>";
	#setItemHeaderTxt($lagerantal, $fokus); //pos_ordre_includes/frontpage/itemTxt.php 	Varenavn etc.
	if ($status < 3) {
	$qtxt = "select var_value from settings where var_name = 'jump2price'";
	($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$jump2price = $r['var_value']:$jump2price = 0;

	if(isset($_GET['ret']) && is_numeric($_GET['ret']) && !$antal_ny) {
			$ret=$_GET['ret']*1;
			$qtxt = "select varenr,variant_id,antal,lager,beskrivelse,pris,kostpris,momssats,momsfri,rabat,leveret,tilfravalg,barcode ";
			$qtxt.= "from ordrelinjer where id = '$ret'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$varenr_ny=$r['varenr'];
			$lager_ny=$r['lager'];
			$antal_old=dkdecimal($r['antal'],2);
			$rabat_old=dkdecimal($r['rabat'],2);
			$beskrivelse[0]=$r['beskrivelse'];
			$leveret[0]=$r['leveret'];
			$barcodeNew=$r['barcode'];
			if ($barcodeNew) {
				$_SESSION['varenr_ny'] = $varenr_ny; 
				$varenr_ny = $barcodeNew;
			}
			$tilfravalgNy=$r['tilfravalg'];
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
			$leveret[0]=0;
		}
		$bgColor = 'white';
		$qtyTitle = '';
		if (isset($itemGroup[0]) && in_array($itemGroup[0],$stockGrp) && $beholdning[0] < $min_lager[0]) { #20210503
			$bgColor ='red';
			$qtyTitle = "Obs!! Beholdning (". dkdecimal($beholdning[0],0) .") mindre end ". dkdecimal($min_lager[0],0);
		}	elseif (isset ($beholdning[0]) && $beholdning[0]) $qtyTitle = "Beholdning: ". dkdecimal($beholdning[0],0);
		print "<input type=\"hidden\" name = \"fokus\" value=\"$fokus\">\n";
		print "<input type=\"hidden\" name = \"pre_bordnr\" value=\"$bordnr\">\n";  #20140508
		#print "<input type=\"hidden\" name = \"vare_id\" value=\"$vare_id[0]\">\n";
		print "<input type=\"hidden\" name = \"momssats\" value=\"$momssats\">\n";
		print "<input type='hidden' name = 'beskrivelse_old' value=\"". htmlentities($beskrivelse[0]) ."\">\n"; #20210829
		print "<input type='hidden' name = 'pris_old' value=\"$pris[0]\">\n"; #20210829
		if ($myDe) print "<input type='hidden' name = 'beskrivelse_ny' value=\"$myDe\">\n"; #20210829
		print "<input type='hidden' name = 'barcodeNew' value=\"$barcodeNew\">\n"; #20210829
		print "<input type=\"hidden\" name = \"leveret\" value=\"$leveret[0]\">\n";
		print "<input type=\"hidden\" name = \"antal\" value=\"$antal_old\">\n";
		print "<input type=\"hidden\" name = \"lager\" value=\"$lager_ny\">\n";
		print "<input type=\"hidden\" name = \"tilfravalgNy\" value=\"$tilfravalgNy\">\n"; # 20220614
		print "<input type=\"hidden\" name = \"timestamp\" value=".date("i:s").">\n"; # 20220614
		if ($fokus=='pris_ny') print "<input type=\"hidden\" name = \"pris\" value=\"$pris_old\">\n";
		if ($fokus=='pris_ny' || $fokus=='rabat_ny') { #20210902
#			print "<input type='hidden' name = 'beskrivelse_ny' value=\"$beskrivelse_ny\">\n"; #20210829
		}
#		if ($textNew) print "<input type=\"hidden\" name = \"itemText\" value=\"$beskrivelse[0]\">\n"; 
		#if ($textNew) print "<input type=\"hidden\" name = \"beskrivelse_ny\" value=\"$beskrivelse[0]\">\n"; #20210811 
		#if ($fokus == 'beskrivelse_ny') print "<input type=\"hidden\" name = \"beskrivelse_ny\" value=\"$beskrivelse_ny\">\n"; #20210811 commented out
		print "<tr><td width=\"19%\">";
		print "<input class=\"inputbox\" disableMobilePrompt='true' type=\"text\" style=\"width:120px;font-size:$ifs;\" name = \"varenr_ny\" value=\"$varenr_ny\">";
		print "</td>\n"; 	
		if ($varenr_ny) {
			if (!$antal_old && $antal_old!='0') $antal_old='1,00'; #20140814 
			print "<td width=\"7%\" title = '$qtyTitle'>";

			# Check for mobile agents
			$useragent=$_SERVER['HTTP_USER_AGENT'];
			if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4))) {

				print "<input class=\"inputbox\" onfocus='blur();' type=\"text\" style=\"background-color:$bgColor;text-align:right;font-size:$ifs;width:40px;";
				if ($_GET["fokus"] == "antal_ny" || true) {
					print " border: 2px #3a8eab solid;";
				}
				print "\" name=\"antal_ny\" placeholder=\"$antal_old\" value=\"$antal_ny\"";
				print ">";
			} else {
				print "<input class=\"inputbox\" type=\"text\" style=\"background-color:$bgColor;text-align:right;font-size:$ifs;width:40px\" ";
				print "name=\"antal_ny\" placeholder=\"$antal_old\" value=\"$antal_ny\">";
			}

			$lagerbeh = get_settings_value("show_stock", "POS", 0);
			if ($lagerbeh == "on") {
				$qtxt = "select varer.id,varer.gruppe,grupper.box8 from varer,grupper ";
				$qtxt.= "where varer.varenr = '$varenr_ny' and grupper.kodenr = varer.gruppe";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));

				# This will pass if the item is a normal item, if it is a variant it will fail
				if ($r['box8']) {
					$qtxt = "select sum(beholdning) from lagerstatus where vare_id = '$r[id]'";
					$beh = dkdecimal(db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))["sum"]);
					if ($beh) {
						print "($beh)";
					}
				} else {
					$qtxt = "select id, vare_id from variant_varer where variant_stregkode = '$varenr_ny'";
					$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					if ($r) {
						$qtxt = "select sum(beholdning) from lagerstatus where variant_id = '$r[id]' and vare_id = '$r[vare_id]'";
						$beh = dkdecimal(db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))["sum"]);
						print "($beh)";
					}
				}
			}
			print "</td>";

			if ($lagerantal>1) {
				for ($l=0;$l<count($lagernr);$l++){
					if ($lagernr[$l]==$lager_ny && strlen($lagernavn[$l])==1) $lager_ny=$lagernavn[$l]; 
				}
				print "<td align=\"center\" style=\"width: 7%\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right;font-size:$ifs;width:40px\" name=\"lager_ny\" placeholder=\"$lager_ny\" value=\"$lager_ny\"></td>\n";
			}
			if ($jump2price && $pris[0] == 0) {
				$antal_ny = 1;
				$fokus    = 'pris_ny';
				$pris_old = '';
			}
			if ($antal_ny) {
				if ($textNew) {
					print "<td style='width: 10%' align=\"right\" title='".findtekst(1873, $sprog_id)."'>"; #20210820
					print "<input class=\"inputbox\" type=\"text\" style=\"text-align:right;font-size:$ifs;width:80px\" ";
					#print "name=\"textNew\" placeholder=\"$beskrivelse[0]\" value=\"\"></td>\n";
					print "name=\"beskrivelse_ny\" value=\"$beskrivelse[0]\"></td>\n"; #20210810 + 20210812 + 20210829
				} else print "<td>".$beskrivelse[0]."</td>\n";
if (!$pris_old && $myPr) $pris_old = dkdecimal($myPr);
				$txt = "<input type=hidden name=\"pris_old\" value=\"$pris_old\">\n" ; #20140702
				$txt.= "<td style='width: 10%' align=\"right\" title=\"".findtekst(1874, $sprog_id).": ".dkdecimal($kostpris[0],2)."\">";
				$txt.= "<input class=\"inputbox\" disableMobilePrompt='true' type=\"text\" style=\"text-align:right;font-size:$ifs;width:60px\" ";
				$txt.= "name = \"pris_ny\"  placeholder=\"$pris_old\" value=\"\"></td>\n";
				print $txt;
			} else {
				print "<td>".$beskrivelse[0]."</td>\n";
				print "<input type=hidden name=\"pris_ny\" value=\"$pris[0]\" />\n";
				print "<input type=hidden name=\"tilfravalgNy\" value=\"$tilfravalgNy\" />\n";
				print "<td align=\"right\" title=\"".findtekst(1874, $sprog_id).": ".dkdecimal($kostpris[0],2)."\">$pris[0]</td>\n";
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
				if ($rabat_old && $rabat_old!='0,00') print "<td colspan=\"2\" align=\"right\">$rabat_old% $credit_type</td>\n"; #20210813 This not fully implemented
			}
		}
		print "<td style='width: 10%'></td>";
		print "<td style='width: 50px'></td>";
		print "</tr>\n";
	}
	if (!$moms)  $moms  = 0;
	if (!$saldo) $saldo = 0;
	if ($tracelog) fwrite ($tracelog, __file__." ".__line__." Calls vis_pos_linjer($id,$momssats,$status,$pris[0],1))\n");
	list($sum,$rest,$afrundet,$kostsum)=explode(chr(32),vis_pos_linjer($id,$momssats,$status,$pris[0],1));
	if ($konto_id && $kreditmax != 0 && $sum+$moms > $kreditmax - $saldo && $status < '3') {
		$ny_saldo=$saldo+$sum+$moms;
		$txt = findtekst('381|Kreditmax', $sprog_id).": ".dkdecimal($kreditmax,2).'\n'.findtekst('2297|Gl. saldo', $sprog_id).": ".dkdecimal($saldo,2).'\n'.findtekst('2298|Ny saldo', $sprog_id).": ".dkdecimal($ny_saldo,2);
		#print "<BODY onLoad=\"javascript:alert('$txt')\">\n";
		alert("$txt");
	}
	print "<input type=\"hidden\" name = \"sum\" value = \"$sum\" />\n";
	setRoundUpText($afrundet, $difkto, $rest, $betvaluta, $afrundet);
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
				print "<tr><td><b>".findtekst('2296|Retur', $sprog_id);
				if ($betvaluta!= '$baseCurrency') print " $baseCurrency";
				print "</b></td><td colspan=\"4\" align=\"right\"><b>$tmp</b></td></tr>\n";
				if ($kundedisplay) {
					kundedisplay('Modtaget',$modtaget+$modtaget2,0);
					kundedisplay('Retur',$tmp,0);
				}
			}
		}
	} elseif ($status >= 3) {
		$r=db_fetch_array($q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		print "<tr><td>".findtekst('1073|Saldo', $sprog_id)."</td><td colspan=\"4\" align=\"right\">".dkdecimal((float)$r['felt_3'],2)."</td></tr>\n";
		$indbetaling=($r['felt_4']-$r['felt_3'])*-1;
		print "<tr><td colspan=\"3\">".findtekst('2299|Indbetaling - (Det beløb der skal indsættes på kontoen!)', $sprog_id)."</td><td rowspan=\"2\" colspan=\"1\" align=\"right\">".dkdecimal($indbetaling,2)."</td></tr>\n";
		print "<tr><td colspan=\"3\">".findtekst('2299|Indbetaling - (Det beløb der skal indsættes på kontoen!)', $sprog_id)."</td>";
		print "<tr><td>$r[felt_1]</td><td colspan=\"4\" align=\"right\">".dkdecimal($r['felt_2'],2)."</td></tr>\n";
		$ny_saldo=$r['felt_4'];
		print "<tr><td>".findtekst('2298|Ny saldo', $sprog_id)."</td><td colspan=\"4\" align=\"right\">".dkdecimal($ny_saldo,2)."</td></tr>\n";
		$retur=pos_afrund($r['felt_2']+$r['felt_4']-$r['felt_3'],$difkto,'');
		print "<tr><td>".findtekst('2296|Retur', $sprog_id)."</td><td  colspan=\"4\" align=\"right\">".dkdecimal($retur,2)."</td></tr>\n";
	}
	print "<tr><td colspan=\"6\" align=\"right\"><button STYLE=\"width: 100%;height: 0.01em;\" ";
	print "onClick=\"this.form.submit(); this.disabled=true;\"></button></td></tr>\n"; #20220427
#	print "<tr><td colspan=\"6\" align=\"right\"><input  STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
	if ($kontonr && $status<3 && ($betalingsbet!='Kontant' || $saldo)) { #20161001
		print "<tr><td>".findtekst('2297|Gl. saldo', $sprog_id)."</td><td colspan=\"4\" align=\"right\">".dkdecimal($saldo,2)."</td></tr>\n";
		$ny_saldo=(float)$saldo+(float)$sum;
		print "<tr><td>".findtekst('2298|Ny saldo', $sprog_id)."</td><td colspan=\"4\" align=\"right\">".dkdecimal($ny_saldo,2);
		if ($pris[0]) print "<br>(".dkdecimal($ny_saldo+$pris[0],2).")";
		print "</td></tr>\n";
		if ($kreditmax) {
			print "<tr><td>".findtekst('381|Kreditmax', $sprog_id)."</td><td colspan=\"4\" align=\"right\">".dkdecimal($kreditmax,2)."</td></tr>\n";
		}
	}
	print "\n<!-- Function varescan (slut)-->\n";
	return ($varenr_ny.chr(9).$pris_ny.chr(9).$status);
} # endfunc varescan.

