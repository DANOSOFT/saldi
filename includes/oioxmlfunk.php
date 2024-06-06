<?php
// -------includes/oioxmlfunk.php-----patch 3.2.0-----2012-08-23--------
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

// 2012-08-23 Indregner rabat i stykpris og 'remmet' rabatafsnit

$oioxmlubl="OIOXML";

# Funktion til baade faktura og kreditnotaer (negative fakturaer)
function oioxmldoc_faktura ($l_ordreid="", $l_doktype="faktura", $l_testdoc="") {

global $db_encode;

	if ($l_testdoc) $l_testdoc="TEST";

	if (!$l_ordreid) return "";
	
	if (strtolower($l_doktype) == "faktura" ) { 
		# Faktura
		$l_ptype="PIE";
	} else {
		# Kreditnota
		$l_ptype="PCM";
	}
		
	$l_doctype = "Invoice"; # Ogsaa selvom det er en kreditnota

	$l_retur=oioxml_top($l_doctype, $l_ptype, $l_testdoc, $l_ordreid);
	
	$query = db_select("select * from ordrer where id = $l_ordreid",__FILE__ . " linje " . __LINE__);
	$r_faktura = db_fetch_array($query);
	if ($db_encode!="UTF8") {
		$firmanavn=utf8_encode($r_faktura['firmanavn']);
		$addr_1=utf8_encode($r_faktura['addr1']);
		$addr_2=utf8_encode($r_faktura['addr2']);
		$postnr=utf8_encode($r_faktura['postnr']);
		$bynavn=utf8_encode($r_faktura['bynavn']);
		$land=utf8_encode($r_faktura['land']);
		$kontakt=utf8_encode($r_faktura['kontakt']);
		$bank_navn=utf8_encode($r_faktura['bank_navn']);
		$kundeordnr=utf8_encode($r_faktura['kundeordnr']);
		$cvrnr=utf8_encode($r_faktura['cvrnr']);
	} else {
		$firmanavn=$r_faktura['firmanavn'];
		$addr_1=$r_faktura['addr1'];
		$addr_2=$r_faktura['addr2'];
		$postnr=$r_faktura['postnr'];
		$bynavn=$r_faktura['bynavn'];
		$land=$r_faktura['land'];
		$kontakt=$r_faktura['kontakt'];
		$bank_navn=$r_faktura['bank_navn'];
		$kundeordnr=$r_faktura['kundeordnr'];
		$cvrnr=$r_faktura['cvrnr'];
	} 
	$firmanavn=htmlspecialchars($firmanavn, ENT_QUOTES);
	$addr_1=htmlspecialchars($addr_1, ENT_QUOTES);
	$addr_2=htmlspecialchars($addr_2, ENT_QUOTES);
	$postnr=htmlspecialchars($postnr, ENT_QUOTES);
	$bynavn=htmlspecialchars($bynavn, ENT_QUOTES);
	$land=htmlspecialchars($land, ENT_QUOTES);
	$kontakt=htmlspecialchars($kontakt, ENT_QUOTES);
	$bank_navn=htmlspecialchars($bank_navn, ENT_QUOTES);
	$kundeordnr=htmlspecialchars($kundeordnr, ENT_QUOTES);
	$cvrnr=htmlspecialchars(str_replace(" ","",$cvrnr), ENT_QUOTES);

	if (!$kundeordnr) $kundeordnr='0'; # phr 20090803
	while (strlen($cvrnr)<8) $cvrnr="0".$cvrnr;

	$l_momsbeloeb=abs($r_faktura['moms']);
	$l_momssats=$r_faktura['momssats']*1;
	$l_sumbeloeb=abs($r_faktura['sum']);
	$l_momspligtigt=(100*$l_momsbeloeb)/$l_momssats;
	$l_momsfrit=$l_sumbeloeb-$l_momspligtigt;

	$l_retur.="\t<com:ID>".$r_faktura['fakturanr']."</com:ID>\n";
	$l_retur.="\t<com:IssueDate>".$r_faktura['fakturadate']."</com:IssueDate>\n";
	$l_retur.="\t<com:TypeCode>".$l_ptype."</com:TypeCode>\n";

	if ($r_faktura['valuta']) { 
		$l_valutakode=$r_faktura['valuta']; 
	} else {
		$l_valutakode="DKK";
	}

	if ($r_faktura['valutakurs']) {
		$l_valutakurs=$r_faktura['valutakurs'];
	} else {
		$l_valutakurs=100;
	}

	$l_retur.="\t<main:InvoiceCurrencyCode>".$l_valutakode."</main:InvoiceCurrencyCode>\n";
	$l_retur.="\t<com:BuyersReferenceID schemeID=\"EAN\">".$r_faktura['ean']."</com:BuyersReferenceID>\n";
	$l_retur.="\t<com:ReferencedOrder>\n";
	$l_retur.="\t\t<com:BuyersOrderID>".$kundeordnr."</com:BuyersOrderID>\n";
	$l_retur.="\t\t<com:SellersOrderID>".$l_ordreid."</com:SellersOrderID>\n";
	$l_retur.="\t\t<com:IssueDate>".$r_faktura['ordredate']."</com:IssueDate>\n";
	$l_retur.="\t</com:ReferencedOrder>\n";

	$l_retur.="\t<com:BuyerParty>\n";
	
	$l_retur.="\t\t<com:ID schemeID=\"CVR\">".$cvrnr."</com:ID>\n"; # phr 20090730
	$l_retur.="\t\t<com:AccountCode>".$r_faktura['kontonr']."</com:AccountCode>\n";
	$l_retur.="\t\t<com:PartyName>\n";
	$l_retur.="\t\t\t<com:Name>".$firmanavn."</com:Name>\n";
	$l_retur.="\t\t</com:PartyName>\n";
	$l_retur.="\t\t<com:Address>\n";
	$l_retur.="\t\t\t<com:ID>Fakturering</com:ID>\n";
	$l_retur.="\t\t\t<com:Street>".oioxml_vej($addr_1, "vejnavn")."</com:Street>\n";
	$l_retur.="\t\t\t<com:HouseNumber>".oioxml_vej($addr_1, "husnummer")."</com:HouseNumber>\n";
	$l_retur.="\t\t\t<com:CityName>".$bynavn."</com:CityName>\n";
	$l_retur.="\t\t\t<com:PostalZone>".$postnr."</com:PostalZone>\n";
	$l_retur.="\t\t\t<com:Country>\n";
	$l_retur.="\t\t\t\t<com:Code listID=\"ISO 3166-1\">".oioxml_landekode($land)."</com:Code>\n";
	$l_retur.="\t\t\t</com:Country>\n";
	$l_retur.="\t\t</com:Address>\n";
	$l_retur.=oioxml_kontaktinfo($kontakt, "BuyerContact"); 
	$l_retur.="\t</com:BuyerParty>\n";

	$query = db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__);
	$r_egen = db_fetch_array($query);
	if ($db_encode!="UTF8") {
		$egen_firmanavn=utf8_encode($r_egen['firmanavn']);
		$egen_addr_1=utf8_encode($r_egen['addr1']);
		$egen_addr_2=utf8_encode($r_egen['addr2']);
		$egen_postnr=utf8_encode($r_egen['postnr']);
		$egen_bynavn=utf8_encode($r_egen['bynavn']);
		$egen_land=utf8_encode($r_egen['land']);
		$egen_kontakt=utf8_encode($r_egen['kontakt']);
		$egen_bank_navn=utf8_encode($r_egen['bank_navn']);
	} else {
		$egen_firmanavn=$r_egen['firmanavn'];
		$egen_addr_1=$r_egen['addr1'];
		$egen_addr_2=$r_egen['addr2'];
		$egen_postnr=$r_egen['postnr'];
		$egen_bynavn=$r_egen['bynavn'];
		$egen_land=$r_egen['land'];
		$egen_kontakt=$r_egen['kontakt'];
		$egen_bank_navn=$r_egen['bank_navn'];
	} 
	$egen_firmanavn=htmlspecialchars($egen_firmanavn, ENT_QUOTES);
	$egen_addr_1=htmlspecialchars($egen_addr_1, ENT_QUOTES);
	$egen_addr_2=htmlspecialchars($egen_addr_2, ENT_QUOTES);
	$egen_postnr=htmlspecialchars($egen_postnr, ENT_QUOTES);
	$egen_bynavn=htmlspecialchars($egen_bynavn, ENT_QUOTES);
	$egen_land=htmlspecialchars($egen_land, ENT_QUOTES);
	$egen_kontakt=htmlspecialchars($egen_kontakt, ENT_QUOTES);
	$egen_bank_navn=htmlspecialchars($egen_bank_navn, ENT_QUOTES);
	$egen_cvrnr=str_replace(" ","",$r_egen['cvrnr']);
	
	$l_retur.="\t<com:SellerParty>\n";
	$l_retur.="\t\t<com:ID schemeID=\"CVR\">".$egen_cvrnr."</com:ID>\n";
	$l_retur.="\t\t<com:PartyName>\n";
	$l_retur.="\t\t\t<com:Name>".$egen_firmanavn."</com:Name>\n";
	$l_retur.="\t\t</com:PartyName>\n";
	$l_retur.="\t\t<com:Address>\n";
	$l_retur.="\t\t\t<com:ID>Betaling</com:ID>\n";
	$l_retur.="\t\t\t<com:Street>".oioxml_vej($egen_addr_1, "vejnavn")."</com:Street>\n";
	$l_retur.="\t\t\t<com:HouseNumber>".oioxml_vej($egen_addr_1, "husnummer")."</com:HouseNumber>\n";
	$l_retur.="\t\t\t<com:CityName>".$egen_bynavn."</com:CityName>\n";
	$l_retur.="\t\t\t<com:PostalZone>".$egen_postnr."</com:PostalZone>\n";
	$l_retur.="\t\t\t<com:Country>\n";
	$l_retur.="\t\t\t\t<com:Code listID=\"ISO 3166-1\">".oioxml_landekode($egen_land)."</com:Code>\n";
	$l_retur.="\t\t\t</com:Country>\n";
	$l_retur.="\t\t</com:Address>\n";
	$l_retur.="\t\t<com:PartyTaxScheme>\n";
	$l_retur.="\t\t\t<com:CompanyTaxID schemeID=\"CVR\">".$r_egen['cvrnr']."</com:CompanyTaxID>\n";
	$l_retur.="\t\t</com:PartyTaxScheme>\n";
	$l_retur.=oioxml_kontaktinfo($egen_kontakt, "OrderContact"); 
	$l_retur.="\t</com:SellerParty>\n";

	$l_retur.="\t<com:PaymentMeans>\n";
	# TypeCodeID: Prioriteret raekkefoelge PBS (null), FI-kort (71), bankoverfoersel (null)
	$l_retur.="\t\t<com:TypeCodeID>";
	$l_retur.=oioxml_typecodeid($r_egen['bank_fi'], $r_egen['bank_reg'], $r_egen['bank_konto'], $r_faktura['pbs']);
	$l_retur.="</com:TypeCodeID>\n";
	$l_retur.="\t\t<com:PaymentDueDate>";
	$l_retur.=oioxml_betalingsdato($r_faktura['fakturadate'], $r_faktura['betalingsbet'], $r_faktura['betalingsdage']);
	$l_retur.="</com:PaymentDueDate>\n";
	$l_retur.="\t\t<com:PaymentChannelCode>";
	$l_retur.=oioxml_paymentchannelcode($r_egen['bank_fi'], $r_egen['bank_reg'], $r_egen['bank_konto'], $r_faktura['pbs']);
	$l_retur.="</com:PaymentChannelCode>\n";
	# $l_retur.="\t\t<com:PaymentID>".$r_faktura['?']."</com:PaymentID>\n"; # Til OCR for indbetalingskort og er paa 0, 15 eller 16 cifre
	# if ($r_faktura['pbs']) $l_retur.="\t\t<com:JointPaymentID>".$r_egen['pbs_nr']."</com:JointPaymentID>\n"; # PBS-debitornummer mangler
	$l_retur.="\t\t<com:PayeeFinancialAccount>\n";
	$l_retur.="\t\t\t<com:ID>".sprintf("%010.0f", $r_egen['bank_konto'])."</com:ID>\n";
	$l_retur.="\t\t\t<com:TypeCode>BANK</com:TypeCode>\n"; # Skal kunne indeholde BANK", "GIRO", "FIK", "BANKGIROT", "POSTGIROT", "IBAN", eller "null"
	$l_retur.="\t\t\t<com:FiBranch>\n";
	$l_retur.="\t\t\t\t<com:ID>".$r_egen['bank_reg']."</com:ID>\n";
	$l_retur.="\t\t\t\t<com:FinancialInstitution>\n";
	# if ($r_egen['bank_swift?']) {
	#	$l_retur.="\t\t\t\t\t<com:ID>".$r_egen['bank_swift?']."</com:ID>\n";
	# } else {
	#	$l_retur.="\t\t\t\t\t<com:ID>null</com:ID>\n";
	# }
	$l_retur.="\t\t\t\t\t<com:ID>null</com:ID>\n";
	$l_retur.="\t\t\t\t\t<com:Name>".$egen_bank_navn."</com:Name>\n";
	$l_retur.="\t\t\t\t</com:FinancialInstitution>\n";
	$l_retur.="\t\t\t</com:FiBranch>\n";
	$l_retur.="\t\t</com:PayeeFinancialAccount>\n";
#	$l_retur.="\t\t<com:PaymentAdvice>\n";
#	$l_retur.="\t\t\t<com:LongAdvice><![CDATA[".$r_faktura['notes']."CDATA]]</com:LongAdvice>\n"; # skal i en funktion som begraenser til 41 linjer a 35 tegn
#	$l_retur.="\t\t\t<com:AccountToAccount>\n";
#	$l_retur.="\t\t\t\t<com:PayerNote>Fakt. ".$r_faktura['fakturanr']."</com:PayerNote>\n";
#	#$l_retur.="\t\t\t\t<com:PayeeNote>".$r_faktura['fakturanr']."</com:PayeeNote>\n"; # Ikke relevant pt.
#	$l_retur.="\t\t\t</com:AccountToAccount>\n";
#	$l_retur.="\t\t</com:PaymentAdvice>\n";
	$l_retur.="\t</com:PaymentMeans>\n";

	$l_retur.="\t<com:PaymentTerms>\n";
	$l_retur.="\t\t<com:ID>SPECIFIC</com:ID>\n"; # Opret felt paa faktura til at angive om det er en rammekontrakt (CONTRACT) eller ikke.
	$l_retur.="\t\t<com:RateAmount currencyID=\"".$l_valutakode."\">".$l_valutakurs."</com:RateAmount>\n";
	# Rentesats for rykkere ikke implementeret endnu
	# $l_retur.="\t\t<com:PenaltySurchargeRateNumeric>".$r_faktura['fakturanr']."</com:PenaltySurchargeRateNumeric>\n"; 
	$l_retur.="\t</com:PaymentTerms>\n";

	if ($l_momspligtigt > 0) {
		$l_retur.="\t<com:TaxTotal>\n";
		$l_retur.="\t\t<com:TaxTypeCode>VAT</com:TaxTypeCode>\n";
		$l_retur.="\t\t<com:TaxAmounts>\n";
		$l_retur.="\t\t\t<com:TaxableAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_momspligtigt)."</com:TaxableAmount>\n";
		$l_retur.="\t\t\t<com:TaxAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_momsbeloeb)."</com:TaxAmount>\n";
		$l_retur.="\t\t</com:TaxAmounts>\n";
		$l_retur.="\t\t<com:CategoryTotal>\n";
		$l_retur.="\t\t\t<com:RateCategoryCodeID>VAT</com:RateCategoryCodeID>\n";
		$l_retur.="\t\t\t<com:RatePercentNumeric>".$l_momssats."</com:RatePercentNumeric>\n";
		$l_retur.="\t\t\t<com:TaxAmounts>\n";
		$l_retur.="\t\t\t\t<com:TaxableAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_momspligtigt)."</com:TaxableAmount>\n";
		$l_retur.="\t\t\t\t<com:TaxAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_momsbeloeb)."</com:TaxAmount>\n";
		$l_retur.="\t\t\t</com:TaxAmounts>\n";
		$l_retur.="\t\t</com:CategoryTotal>\n";
		$l_retur.="\t</com:TaxTotal>\n";
	}

	if ($l_momsfrit > 0) {
		$l_retur.="\t<com:TaxTotal>\n";
		$l_retur.="\t\t<com:TaxTypeCode>ZERO-RATED</com:TaxTypeCode>\n";
		$l_retur.="\t\t<com:TaxAmounts>\n";
		$l_retur.="\t\t\t<com:TaxableAmount currencyID=\"$l_valutakode\">";
		$l_retur.=sprintf("%01.2f", $l_momsfrit);
		$l_retur.="</com:TaxableAmount>\n";
		$l_retur.="\t\t\t<com:TaxAmount currencyID=\"$l_valutakode\">0.00</com:TaxAmount>\n";
		$l_retur.="\t\t</com:TaxAmounts>\n";
		$l_retur.="\t\t<com:CategoryTotal>\n";
		$l_retur.="\t\t\t<com:RateCategoryCodeID>ZERO-RATED</com:RateCategoryCodeID>\n";
		$l_retur.="\t\t\t<com:RatePercentNumeric>0</com:RatePercentNumeric>\n";
		$l_retur.="\t\t\t<com:TaxAmounts>\n";
		$l_retur.="\t\t\t\t<com:TaxableAmount currencyID=\"$l_valutakode\">";
		$l_retur.=sprintf("%01.2f", $l_momsfrit);
		$l_retur.="</com:TaxableAmount>\n";
		$l_retur.="\t\t\t\t<com:TaxAmount currencyID=\"$l_valutakode\">0.00</com:TaxAmount>\n";
		$l_retur.="\t\t\t</com:TaxAmounts>\n";
		$l_retur.="\t\t</com:CategoryTotal>\n";
		$l_retur.="\t</com:TaxTotal>\n";
	}

	$l_retur.="\t<com:LegalTotals>\n";
	$l_retur.="\t\t<com:LineExtensionTotalAmount currencyID=\"$l_valutakode\">";
	$l_retur.=sprintf("%01.2f", $l_sumbeloeb);
	$l_retur.="</com:LineExtensionTotalAmount>\n";
	$l_retur.="\t\t<com:ToBePaidTotalAmount currencyID=\"$l_valutakode\">";
	$l_retur.=sprintf("%01.2f", ($l_sumbeloeb+$l_momsbeloeb));
	$l_retur.="</com:ToBePaidTotalAmount>\n";
	$l_retur.="\t</com:LegalTotals>\n";

	$query = db_select("select * from ordrelinjer where ordre_id = $l_ordreid order by posnr",__FILE__ . " linje " . __LINE__);

	while ($r_linje = db_fetch_array($query)) {
		
		if ($db_encode!="UTF8") {
			$varenr=utf8_encode($r_linje['varenr']);
			$enhed=utf8_encode($r_linje['enhed']);
			$beskrivelse=utf8_encode($r_linje['beskrivelse']);
		} else {
			$varenr=$r_linje['varenr'];
			$enhed=$r_linje['enhed'];
			$beskrivelse=$r_linje['beskrivelse'];
		} 
		$varenr=htmlspecialchars($varenr, ENT_QUOTES);
		$enhed=htmlspecialchars($enhed, ENT_QUOTES);
		$beskrivelse=htmlspecialchars($beskrivelse, ENT_QUOTES);
		$pris=$r_linje['pris']*1;
		$momsfri=$r_linje['momsfri'];
		$varemomssats=$r_linje['momssats']*1;
		if (!$momsfri && !$varemomssats) $varemomssats=$l_momssats;
		if ($varemomssats > $l_momssats) $varemomssats=$l_momssats;
		if (!$varenr) $varenr='0'; #phr 20080803
		$pris=afrund($pris-($r_linje['rabat']*$pris)/100,2);
		$linjepris=afrund($r_linje['antal']*$pris,2);
		$linjemoms=afrund($linjepris/100*$varemomssats,2);
		
		if ($l_ptype=="PCM") {
			$l_fortegn=-1;
		} else {
			$l_fortegn=1;
		}

		$l_retur.="\t<com:InvoiceLine>\n";
		$l_retur.="\t\t<com:ID>".$r_linje['posnr']."</com:ID>\n";
		if (($l_ptype=="PCP")||($l_ptype=="PIP")) { # Hvis papirbaseret
			$l_retur.="\t\t<com:InvoicedQuantity unitCode=\"".oioxml_enhed($r_linje['enhed'])."\" unitCodeListAgencyID=\"n/a\">0</com:InvoicedQuantity>\n";
			$l_retur.="\t\t<com:LineExtensionAmount currencyID=\"$l_valutakode\">0</com:LineExtensionAmount>\n";
		} else {
			$l_retur.="\t\t<com:InvoicedQuantity unitCode=\"".oioxml_enhed($enhed)."\" unitCodeListAgencyID=\"n/a\">".$l_fortegn*$r_linje['antal']."</com:InvoicedQuantity>\n";
			$l_retur.="\t\t<com:LineExtensionAmount currencyID=\"$l_valutakode\">";
			$l_retur.=sprintf("%01.2f", $l_fortegn*$linjepris); # ordrelinjepris fratrukket rabat
			$l_retur.="</com:LineExtensionAmount>\n";
		}	
		# $l_retur.="\t\t<com:Note>".$r_linje['posnr']."</com:Note>\n"; # Noter til ordrelinjer er ikke understoettet endnu
		# 
		# Start - Hvis rabat ...
#		$l_retur.="\t\t<com:AllowanceCharge>\n";
#		$l_retur.="\t\t\t<com:ID>Rabat</com:ID>\n";
#		$l_retur.="\t\t\t<com:ChargeIndicator>true</com:ChargeIndicator>\n";
#		$l_retur.="\t\t\t<com:MultiplierFactorQuantity unitCode=\"promille\" unitCodeListAgencyID=\"n/a\">".(10*$r_linje['rabat'])."</com:MultiplierFactorQuantity>\n";
#		$l_retur.="\t\t\t<com:AllowanceChargeAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_fortegn*($r_linje['rabat']*($r_linje['antal']*$pris)/100))."</com:AllowanceChargeAmount>\n";
#		$l_retur.="\t\t</com:AllowanceCharge>\n";
		# Slut - hvis rabat 
		#
		$l_retur.="\t\t<com:Item>\n";
		if (($l_ptype=="PCP")||($l_ptype=="PIP")) { # Hvis papirbaseret
			$l_retur.="\t\t\t<com:ID>null</com:ID>\n";
		} else {
			$l_retur.="\t\t\t<com:ID>".$varenr."</com:ID>\n"; # Skal have tilfoejet varenrtype i ordrelinjer
		}
		$l_retur.="\t\t\t<com:Description>".$beskrivelse."</com:Description>\n";
# PHR 2011.04.06 ->		
		$l_retur.="\t\t\t<com:Tax>\n";
		($momsfri || !$varemomssats)?$vat='ZERO-RATED':$vat='VAT';
		$l_retur.="\t\t\t\t<com:RateCategoryCodeID>".$vat."</com:RateCategoryCodeID>\n";
		$l_retur.="\t\t\t\t<com:TypeCode>".$vat."</com:TypeCode>\n";
		$l_retur.="\t\t\t\t	<com:RatePercentNumeric>".$varemomssats."</com:RatePercentNumeric>\n";
		$l_retur.="\t\t\t</com:Tax>\n";
		$l_retur.="\t\t</com:Item>\n";
# <- PHR

#		$l_retur.="\t\t<com:Tax>\n";
#		$l_retur.="\t\t\t<com:RateCategoryCodeID>".oioxml_momskode($r_linje['momsfri'])."</com:RateCategoryCodeID>\n";
#		$l_retur.="\t\t\t<com:TypeCode>".oioxml_momskode($r_linje['momsfri'])."</com:TypeCode>\n";
#		$l_retur.="\t\t\t<com:RatePercentNumeric>".oioxml_momssats($r_linje['momsfri'], $r_linje['varenr'])."</com:RatePercentNumeric>\n";
#		$l_retur.="\t\t</com:Tax>\n";
		$l_retur.="\t\t<com:BasePrice>\n";
		$l_retur.="\t\t\t<com:PriceAmount currencyID=\"$l_valutakode\">".($pris)."</com:PriceAmount>\n";
		# $l_retur.="\t\t\t<com:BaseQuantity>".($r_linje['momsfri'])."</com:BaseQuantity>\n"; # Ingen standardantal i pakninger understoettet i SALDI endnu
		$l_retur.="\t\t</com:BasePrice>\n";
		$l_retur.="\t</com:InvoiceLine>\n";
	}

        $l_retur.=oioxml_bottom($l_doctype);

	return $l_retur;
}

function oioxml_top($l_doctype="", $l_ptype="", $l_testdoc="", $l_ordreid="") {
	
	if ((!$l_doctype)||(!$l_ptype)) return "";

	$l_ptype=strtolower($l_ptype);

	$l_retur="<?xml version=\"1.0\" encoding=\"UTF-8\"?> \n";
	$l_retur.="<".$l_doctype." \n";
	$l_retur.="\txmlns=\"http://rep.oio.dk/ubl/xml/schemas/0p71/".$l_ptype."/\" \n";
	$l_retur.="\txmlns:com=\"http://rep.oio.dk/ubl/xml/schemas/0p71/common/\" \n";
	$l_retur.="\txmlns:main=\"http://rep.oio.dk/ubl/xml/schemas/0p71/maindoc/\" \n";
	$l_retur.="\txmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" \n";
	$l_retur.="\txsi:schemaLocation=\"http://rep.oio.dk/ubl/xml/schemas/0p71/".$l_ptype."/"; #Redigeret 20090730 PHR
	$l_retur.=" http://rep.oio.dk/ubl/xml/schemas/0p71/".$l_ptype."/pieStrict.xsd\"\n";
	$l_retur.=">\n";

	if ($l_testdoc) $l_retur.= oioxml_test($l_doctype, $l_ordreid);

	return $l_retur;
}

function oioxml_bottom($l_doctype="") {

	if (!$l_doctype) return "";

	$l_retur="</$l_doctype>\n";

	return $l_retur;
}

function oioxml_test($l_doctype="", $l_ordreid="") {

	global $version;
	global $oioxmlubl;

	if ((!$l_doctype)||($l_ordreid=="")) return "";

	$l_retur.="<!--\n";
	$l_retur.="********************************************************************************\n";
	$l_retur.="\n";
	$l_retur.="\ttitle=saldi-2.0.8-test-$l_doctype-$l_ordreid.xml\n";
	$l_retur.="\tpublisher=\"DANOSOFT ApS\"\n";
	$l_retur.="\tcreator=\"SALDI $version\n";
	$l_retur.="\tcreated=".date("Y-m-d")."\n";
	$l_retur.="\tmodified=".date("Y-m-d")."\n";
	$l_retur.="\tissued=".date("Y-m-d")."\n";
	$l_retur.="\tconformsTo=\"?\"\n";
	$l_retur.="\tdescription=\"This document is produced as a part of testing $oioxmlubl using SALDI\"\n";
	$l_retur.="\trights=\"For now - Copyright DANOSOFT 2009\"\n";
	$l_retur.="\n";
	$l_retur.="\tAll terms derived from http://dublincore.org/documents/dcmi-terms/\n";
	$l_retur.="\n";
	$l_retur.="\tFor more information, see www.oioubl.dk and www.saldi.dk\n";
	$l_retur.="\tor email ca@saldi.dk and oioubl@itst.dk\n";
	$l_retur.="\n";
	$l_retur.="********************************************************************************\n";
	$l_retur.="-->\n";
	$l_retur.="<!-- The following TestInstance process instruction is for customization usage. ";
	$l_retur.="It indicates that the instance is for testing purposes -->\n";
/*	PHR 20090730 Maa ikke staa i xml fil.
	$l_retur.="<?TestInstance \n";
	$l_retur.="\tResponseTo=\"smtp:ca@saldi.dk\"\n";
	$l_retur.="\tdescription=\"Test document from the Danish Open Source accounting system SALDI - http://saldi.dk/\"\n";
	$l_retur.="?>\n";
*/
	return $l_retur;
}


function oioxml_momssats($l_momsfri="", $l_varenr) {

	if ($l_momsfri == "on" ) {
		return "0";
	} 
	
	$l_retur="";

	$l_query = db_select("select gruppe from varer where varenr = '$l_varenr'",__FILE__ . " linje " . __LINE__);
	$r_gruppe = db_fetch_array($l_query);
#$l_retur.="<!-- $r_gruppe[0] -->";

	$l_query = db_select("select box4 from grupper where kodenr = '".$r_gruppe[0]."' and art = 'VG'",__FILE__ . " linje " . __LINE__);
	$r_kontonr = db_fetch_array($l_query);
#$l_retur.="<!-- $r_kontonr[0] -->";
        
	$l_query = db_select("select moms from kontoplan where kontonr = '".$r_kontonr[0]."'",__FILE__ . " linje " . __LINE__);
	$r_gruppe = db_fetch_array($l_query);
#$l_retur.="<!-- $r_gruppe[0] -->";
        
	$r_gruppe = substr($r_gruppe[0], 1);
#$l_retur.="<!-- $r_gruppe -->";

	$l_query = db_select("select box2 from grupper where kodenr = '$r_gruppe' and art = 'SM'",__FILE__ . " linje " . __LINE__);
	$r_momssats = db_fetch_array($l_query);
        
	$l_retur .= $r_momssats[0];

	return $l_retur;
}


function oioxml_momskode($l_momsfri="") {

	if ($l_momsfri == "on" ) {
		return "ZERO-RATED";
	} else {
		return "VAT";
	} 
}


function oioxml_betalingsdato($l_dato="", $l_betingelse="", $l_dage=0) {

	if (!$l_dato) return "";

	$l_retur = $l_dato;

	if (strtolower($l_betingelse)=="netto") {
		list ($l_aar, $l_md, $l_dag) = split("-", $l_dato);
		$l_tidsstempel=mktime(12, 00, 00, $l_md, $l_dag, $l_aar); # Saettes til 12:00 for at forhindre sommertidsproblemer
		if ($l_dage > 0) $l_tidsstempel=$l_tidsstempel+(24*60*60*$l_dage);
	} 

# Skal gennemtestes om den regner det rigtigt
	if (strtolower($l_betingelse)=="lb. md.") {
		list ($l_aar, $l_md, $l_dag) = split("-", $l_dato);
		$l_md++;

		if ($l_md==13) {
			$l_md=1;
			$l_aar++;
		}
		
		$l_tidsstempel=mktime(12, 00, 00, $l_md, 1, $l_aar); #Saettes til 12:00 for at forhindre sommertidsproblemer
		if ($l_dage >= 0) $l_tidsstempel=$l_tidsstempel+(24*60*60*($l_dage-1));
	} 

	
	if ($l_tidsstempel) $l_retur=date("Y-m-d", $l_tidsstempel);

	return $l_retur;
}



# TypeCodeID: Prioriteret raekkefoelge PBS (null), FI-kort (71), bankoverfoersel (null)
function oioxml_typecodeid ($l_fi="", $l_bankreg="", $l_bankkonto="", $l_pbs="") {

	if ((!$l_fi) && (!$l_bankreg) && (!$l_bankkonto) && (!$l_pbs)) return "";

	$l_retur="";

	if ($l_bankreg && $l_bankkonto) $l_retur="null";
	if ($l_fi) $l_retur="71";
	if ($l_pbs) $l_retur="null";

	return $l_retur;
}


function oioxml_paymentchannelcode ($l_fi="", $l_bankreg="", $l_bankkonto="", $l_pbs="") {

	if ((!$l_fi) && (!$l_bankreg) && (!$l_bankkonto) && (!$l_pbs)) return "";

	$l_retur="";

	if ($l_bankreg && $l_bankkonto) $l_retur="KONTOOVERFØRSEL";
	if ($l_fi) $l_retur="INDBETALINGSKORT";
	if ($l_pbs) $l_retur="DIRECT DEBET";

	return $l_retur;
}

function oioxml_kontaktinfo ($l_id="", $l_type) { # $l_type = BuyerContact

	if (!$l_type) return "";

	$l_retur="\t\t<com:$l_type>\n";

	if (!$l_id) { 
		$l_retur.="\t\t\t<com:ID>n/a</com:ID>\n";
	} else {

		if (is_numeric($l_id)) {
			# Slaa op i tabellen for medarbejdere/kontaktpersoner
			$query = db_select("select * from ansatte where id = '$l_id'",__FILE__ . " linje " . __LINE__);
			$r_kontakt = db_fetch_array($query);

			$l_kontaktid = "n/a";
			$l_kontaktinfo = "";

			if ($r_kontakt['navn']) {
				$l_kontaktinfo.="\t\t\t<com:Name>".$r_kontakt['navn']."</com:Name>\n";
				$l_kontaktid = $r_kontakt['navn'];
			}

			if ($r_kontakt['tlf']) { 
				$l_kontaktinfo.="\t\t\t<com:Phone>+45 ".$r_kontakt['tlf']."</com:Phone>\n";
			} elseif ($r_kontakt['mobil']) {
                                $l_kontaktinfo.="\t\t\t<com:Phone>+45 ".$r_kontakt['mobil']."</com:Phone>\n";
			}

			if ($r_kontakt['initialer']) {
				$l_kontaktid = $r_kontakt['initialer'];
			}

			if ($r_kontakt['email']) {
				$l_kontaktinfo.="\t\t\t<com:E-Mail>".$r_kontakt['email']."</com:E-Mail>\n";
				$l_kontaktid = $r_kontakt['email'];
			}

			$l_retur.="\t\t\t<com:ID>".$l_kontaktid."</com:ID>\n";
			$l_retur.=$l_kontaktinfo;
		} else {
			if (eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $l_id)) $l_retur.="\t\t\t<com:E-Mail>".$l_id."</com:E-Mail>\n";
			$l_retur.="\t\t\t<com:ID>".$l_id."</com:ID>\n";
			if (!strtolower($l_id)=="n/a") $l_retur.="\t\t\t<com:Name>".$l_id."</com:Name>\n";
		}
	}

	$l_retur.="\t\t</com:$l_type>\n";

	return $l_retur;
}

function oioxml_tlfnr($l_tlf="") {

	if (!$l_tlf) return "";

	$l_retur=trim($l_tlf);
	
	if (ereg("^(\+[0-9][0-9]*)", $l_retur, $l_regs)) {
	        $l_prefix=$l_regs[1];
	        $l_retur=substr($l_retur, strlen($l_prefix));
	}
	
	$l_retur=ereg_replace("[. -]","",$l_retur);
	
	if ((strlen($l_retur)==8)&&(!$l_prefix)) {
	        $l_prefix="+45";
	} elseif (!$l_prefix) {
	        if (ereg("^([0-9][0-9]*)", $l_tlf, $l_regs)) {
	                $l_prefix="+".$l_regs[1];
	                $l_retur=substr($l_tlf, strlen($l_regs[1]));
	                $l_retur=ereg_replace("[. -]","",$l_retur);
	        }
	}

	$l_retur="$l_prefix $l_retur";

	return $l_retur;
}

function oioxml_vej ($l_addr1="", $l_del="vejnavn") {

	if (!$l_addr1) return "";

	if (ereg("^([^0-9]*) ([0-9].*)$", $l_addr1, $regs)) { # Antager at foerste mellemrum efterfulgt af et tal er husnummeret
		if ($l_del=="vejnavn") $l_retur=$regs[1];
		if ($l_del=="husnummer") $l_retur=$regs[2];
	} else {
		if ($l_del=="vejnavn") $l_retur=$l_addr1;
		if ($l_del=="husnummer") $l_retur="";
	}
	return $l_retur;
}

function oioxml_landekode ($l_land="DK") {

	$l_land=strtolower(trim($l_land));

	if (!$l_land) $l_land="dk";

	if (strlen($l_land)==2) {
		if ($l_land=="da") $l_land="dk";
	} else {
		if ($l_land=="dan") $l_land="dk";
		if ($l_land=="den") $l_land="dk";
		if ($l_land=="danmark") $l_land="dk";
		if ($l_land=="denmark") $l_land="dk";
	}

	if ($l_land=="dk") {
		$l_retur=strtoupper($l_land);
	} else {
		if (strlen($l_land)>2) {
			print "\n<h1>Fejl i landekode</h1>\n\n";
			print "<p>Land sat til \"".$l_land."\"</p>\n\n";
			print "<p>Landekoden skal v&aelig;re p&aring; kun to bogstaver f.eks. DE for Tyskland.</p>\n\n";
			$l_retur="";
 		} else {
			print "\n<h1>Fejl!! Kun Danmark underst&oslash;ttes indtil videre</h1>\n\n";
			print "<p>Det er kun OIOXML, som underst&oslash;ttes indtil videre, ";
			print "og OIOXML benyttes kun af danske myndigheder.</p>\n\n";
			print "OIOUBL og UBL 2.0 er ved at blive implementeret, ";
			print "s&aring; andre lande ogs&aring; bliver underst&oslash;ttet.</p>\n\n";
		}
	}

	return $l_retur;
}

function oioxml_enhed ($l_enhed="") {

	if ($l_enhed) {
		return $l_enhed;
	} else {
		return "enheder";
	}

}
?>
