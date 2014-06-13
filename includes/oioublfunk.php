<?php
// -------includes/oioublfunk.php-----patch 3.3.9-----2014-02-06--------
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
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
//
// 20140206 Afrundingsfejl,saldi_390 ordre id 16090 differ 1 øre på linjesum. 
//	Afrunding af pris flyttet ned efter beregning af linjesum. Søg 20140206


$oioxmlubl="OIOUBL";

# Funktion til baade faktura og kreditnotaer (negative fakturaer)
function oioubldoc_faktura ($l_ordreid="", $l_doktype="faktura", $l_testdoc="") {

	global $db_encode;

	include("../includes/forfaldsdag.php");

	if ($l_testdoc) $l_testdoc="TEST";

	if (!$l_ordreid) return "";
	
	if (strtolower($l_doktype) == "faktura" ) { 
		# Faktura
		$l_doctype = "Invoice";
		$l2_doctype = "Invoiced";
		$l_ptype="PIE";
	} else {
		# Kreditnota
		$l_doctype = "CreditNote";
		$l2_doctype = "Credited";
		$l_ptype="PCM";
	}
		
#	$l_doctype = "Invoice"; # Ogsaa selvom det er en kreditnota

#	$l_retur=oioubl_top($l_doctype, $l_ptype, $l_testdoc, $l_ordreid);
	
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
		$tlf=utf8_encode($r_faktura['tlf']);
		$email=utf8_encode($r_faktura['email']);
		$kontonr=utf8_encode($r_faktura['kontonr']);
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
		$tlf=$r_faktura['tlf'];
		$email=$r_faktura['email'];
		$kontonr=$r_faktura['kontonr'];
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
	$tlf=htmlspecialchars($tlf, ENT_QUOTES);
	$email=htmlspecialchars($email, ENT_QUOTES);

	if (!$kundeordnr) $kundeordnr='0'; # phr 20090803
	while (strlen($cvrnr)<8) $cvrnr="0".$cvrnr;

	$l_momsbeloeb=abs($r_faktura['moms']);
	$l_momssats=$r_faktura['momssats']*1;
	$l_sumbeloeb=abs($r_faktura['sum']);
	$l_momspligtigt=(100*$l_momsbeloeb)/$l_momssats;
	$l_momsfrit=$l_sumbeloeb-$l_momspligtigt;
	$l_forfaldsdate=usdate(forfaldsdag($r_faktura['fakturadate'], $r_faktura['betalingsbet'], $r_faktura['betalingsdage']));
#	$l_retur.="\t<com:ID>".$r_faktura['fakturanr']."</com:ID>\n";
#	$l_retur.="\t<com:IssueDate>".$r_faktura['fakturadate']."</com:IssueDate>\n";
#	$l_retur.="\t<com:TypeCode>".$l_ptype."</com:TypeCode>\n";

	if ($r_faktura['valuta']) { 
		$l_valutakode=$r_faktura['valuta']; 
		$l_valutakurs=$r_faktura['valutakurs'];
	} else {
		$l_valutakode="DKK";
	}

	if ($r_faktura['valutakurs']) {
		$l_valutakurs=$r_faktura['valutakurs'];
	} else {
		$l_valutakurs=100;
	}

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

	$l_retur.="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	$l_retur.="<".$l_doctype." xsi:schemaLocation=\"urn:oasis:names:specification:ubl:schema:xsd:".$l_doctype."-2 UBL-".$l_doctype."-2.0.xsd\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:".$l_doctype."-2\" xmlns:cac=\"urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\" xmlns:cbc=\"urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\" xmlns:ccts=\"urn:oasis:names:specification:ubl:schema:xsd:CoreComponentParameters-2\" xmlns:sdt=\"urn:oasis:names:specification:ubl:schema:xsd:SpecializedDatatypes-2\" xmlns:udt=\"urn:un:unece:uncefact:data:specification:UnqualifiedDataTypesSchemaModule:2\">\n";
	$l_retur.="<cbc:UBLVersionID>2.0</cbc:UBLVersionID>\n";
	$l_retur.="<cbc:CustomizationID>OIOUBL-2.01</cbc:CustomizationID>\n";
#	$l_retur.="<cbc:ProfileID schemeAgencyID=\"320\" schemeID=\"urn:oioubl:id:profileid-1.1\">Procurement-BilSim-1.0</cbc:ProfileID>\n"; 20210725
	$l_retur.="<cbc:ProfileID schemeAgencyID=\"320\" schemeID=\"urn:oioubl:id:profileid-1.2\">urn:www.nesubl.eu:profiles:profile5:ver2.0</cbc:ProfileID>\n";
	$l_retur.="<cbc:ID>".$r_faktura['fakturanr']."</cbc:ID>\n";
	$l_retur.="<cbc:CopyIndicator>false</cbc:CopyIndicator>\n";
	$l_retur.="<cbc:IssueDate>".$r_faktura['ordredate']."</cbc:IssueDate>\n";
	if ($l_doctype == "Invoice") $l_retur.="<cbc:".$l_doctype."TypeCode listAgencyID=\"320\" listID=\"urn:oioubl:codelist:invoicetypecode-1.1\">380</cbc:".$l_doctype."TypeCode>\n";
#	$l_retur.="<cbc:Note>".$l_doctype." note</cbc:Note>\n";
	$l_retur.="<cbc:DocumentCurrencyCode>$l_valutakode</cbc:DocumentCurrencyCode>\n";
	$l_retur.="<cbc:AccountingCost>$kontonr</cbc:AccountingCost>\n";
	$l_retur.="<cac:OrderReference>\n";
	$l_retur.="<cbc:ID>$kundeordnr</cbc:ID>\n";
	$l_retur.="<cbc:SalesOrderID>$l_ordreid</cbc:SalesOrderID>\n";
	$l_retur.="<cbc:IssueDate>".$r_faktura['ordredate']."</cbc:IssueDate>\n";
	$l_retur.="</cac:OrderReference>\n";
	$l_retur.="<cac:AccountingSupplierParty>\n";
	$l_retur.="<cac:Party>\n";
	$l_retur.="<cbc:EndpointID schemeID=\"DK:CVR\">DK".$egen_cvrnr."</cbc:EndpointID>\n";
	$l_retur.="<cac:PartyIdentification>\n";
	$l_retur.="<cbc:ID schemeID=\"DK:CVR\">DK".$egen_cvrnr."</cbc:ID>\n";
	$l_retur.="</cac:PartyIdentification>\n";
	$l_retur.="<cac:PartyName>\n";
	$l_retur.="<cbc:Name>".$egen_firmanavn."</cbc:Name>\n";
	$l_retur.="</cac:PartyName>\n";
	$l_retur.="<cac:PostalAddress>\n";
	$l_retur.="<cbc:AddressFormatCode listAgencyID=\"320\" listID=\"urn:oioubl:codelist:addressformatcode-1.1\">StructuredDK</cbc:AddressFormatCode>\n";
	$l_retur.="<cbc:StreetName>".oioubl_vej($egen_addr_1, "vejnavn")."</cbc:StreetName>\n";
	$l_retur.="<cbc:BuildingNumber>".oioubl_vej($egen_addr_1, "husnummer")."</cbc:BuildingNumber>\n";
	$l_retur.="<cbc:CityName>".$egen_bynavn."</cbc:CityName>\n";
	$l_retur.="<cbc:PostalZone>".$egen_postnr."</cbc:PostalZone>\n";
	$l_retur.="<cac:Country>\n";
	$l_retur.="<cbc:IdentificationCode>".oioubl_landekode($egen_land)."</cbc:IdentificationCode>\n";
	$l_retur.="</cac:Country>\n";
	$l_retur.="</cac:PostalAddress>\n";
	$l_retur.="<cac:PartyTaxScheme>\n";
	$l_retur.="<cbc:CompanyID schemeID=\"DK:SE\">DK".$egen_cvrnr."</cbc:CompanyID>\n";
	$l_retur.="<cac:TaxScheme>\n";
	$l_retur.="<cbc:ID schemeAgencyID=\"320\" schemeID=\"urn:oioubl:id:taxschemeid-1.1\">63</cbc:ID>\n";
	$l_retur.="<cbc:Name>Moms</cbc:Name>\n";
	$l_retur.="</cac:TaxScheme>\n";
	$l_retur.="</cac:PartyTaxScheme>\n";
	$l_retur.="<cac:PartyLegalEntity>\n";
	$l_retur.="<cbc:RegistrationName>".$egen_firmanavn."</cbc:RegistrationName>\n";
	$l_retur.="<cbc:CompanyID schemeID=\"DK:CVR\">DK".$egen_cvrnr."</cbc:CompanyID>\n";
	$l_retur.="</cac:PartyLegalEntity>\n";
	$l_retur.="</cac:Party>\n";
	$l_retur.="</cac:AccountingSupplierParty>\n";
	$l_retur.="<cac:AccountingCustomerParty>\n";
	$l_retur.="<cac:Party>\n";
	$l_retur.="<cbc:EndpointID schemeAgencyID=\"9\" schemeID=\"GLN\">".$r_faktura['ean']."</cbc:EndpointID>\n";
	$l_retur.="<cac:PartyIdentification>\n";
	$l_retur.="<cbc:ID schemeID=\"DK:CVR\">DK".$cvrnr."</cbc:ID>\n";
	$l_retur.="</cac:PartyIdentification>\n";
	$l_retur.="<cac:PartyName>\n";
	$l_retur.="<cbc:Name>".$firmanavn."</cbc:Name>\n";
	$l_retur.="</cac:PartyName>\n";
	$l_retur.="<cac:PostalAddress>\n";
	$l_retur.="<cbc:AddressFormatCode listAgencyID=\"320\" listID=\"urn:oioubl:codelist:addressformatcode-1.1\">StructuredDK</cbc:AddressFormatCode>\n";
	if (oioubl_vej($addr_1, "vejnavn")) $l_retur.="<cbc:StreetName>".oioubl_vej($addr_1, "vejnavn")."</cbc:StreetName>\n";
	elseif (oioubl_vej($addr_2, "vejnavn")) $l_retur.="<cbc:StreetName>".oioubl_vej($addr_2, "vejnavn")."</cbc:StreetName>\n";
	else $l_retur.="<cbc:StreetName>?</cbc:StreetName>\n";
	if (oioubl_vej($addr_1, "husnummer")) $l_retur.="<cbc:BuildingNumber>".oioubl_vej($addr_1, "husnummer")."</cbc:BuildingNumber>\n";
#	if (oioubl_vej($addr_2, "vejnavn")) $l_retur.="<cbc:AdditionalStreetName>".oioubl_vej($addr_2, "vejnavn")."</cbc:AdditionalStreetName>\n";
	elseif (!oioubl_vej($addr_1, "husnummer") && oioubl_vej($addr_2, "husnummer")) $l_retur.="<cbc:BuildingNumber>".oioubl_vej($addr_2, "husnummer")."</cbc:BuildingNumber>\n";
	else $l_retur.="<cbc:BuildingNumber>0</cbc:BuildingNumber>\n";
	$l_retur.="<cbc:CityName>".$bynavn."</cbc:CityName>\n";
	$l_retur.="<cbc:PostalZone>".$postnr."</cbc:PostalZone>\n";
	$l_retur.="<cac:Country>\n";
	$l_retur.="<cbc:IdentificationCode>".oioubl_landekode($land)."</cbc:IdentificationCode>\n";
	$l_retur.="</cac:Country>\n";
	$l_retur.="</cac:PostalAddress>\n";
	$l_retur.="<cac:PartyLegalEntity>\n";
	$l_retur.="<cbc:RegistrationName>".$firmanavn."</cbc:RegistrationName>\n";
	$l_retur.="<cbc:CompanyID schemeID=\"DK:CVR\">DK".$cvrnr."</cbc:CompanyID>\n";
	$l_retur.="</cac:PartyLegalEntity>\n";
	$l_retur.="<cac:Contact>\n";
	$l_retur.=oioubl_kontaktinfo($kontakt, "BuyerContact");
#	$l_retur.="<cbc:Telephone>".$tlf."</cbc:Telephone>\n";
#	$l_retur.="<cbc:ElectronicMail>".$email."</cbc:ElectronicMail>\n";
	$l_retur.="</cac:Contact>\n";
	$l_retur.="</cac:Party>\n";
	$l_retur.="</cac:AccountingCustomerParty>\n";
	if ($l_doctype == "Invoice") {
		$l_retur.="<cac:Delivery>\n";
		$l_retur.="<cbc:ActualDeliveryDate>".$r_faktura['ordredate']."</cbc:ActualDeliveryDate>\n";
		$l_retur.="</cac:Delivery>\n";
		$l_retur.="<cac:PaymentMeans>\n";
		$l_retur.="<cbc:ID>1</cbc:ID>\n";
		$l_retur.="<cbc:PaymentMeansCode>42</cbc:PaymentMeansCode>\n";
		$l_retur.="<cbc:PaymentDueDate>$l_forfaldsdate</cbc:PaymentDueDate>\n";
		$l_retur.="<cbc:PaymentChannelCode listAgencyID=\"320\" listID=\"urn:oioubl:codelist:paymentchannelcode-1.1\">DK:BANK</cbc:PaymentChannelCode>\n";
		$l_retur.="<cac:PayeeFinancialAccount>\n";
		$l_retur.="<cbc:ID>".$r_egen['bank_konto']."</cbc:ID>\n";
		$l_retur.="<cac:FinancialInstitutionBranch>\n";
		$l_retur.="<cbc:ID>".$r_egen['bank_reg']."</cbc:ID>\n";
		$l_retur.="</cac:FinancialInstitutionBranch>\n";
		$l_retur.="</cac:PayeeFinancialAccount>\n";
		$l_retur.="</cac:PaymentMeans>\n";
		$l_retur.="<cac:PaymentTerms>\n";
		$l_retur.="<cbc:ID>1</cbc:ID>\n";
		$l_retur.="<cbc:PaymentMeansID>1</cbc:PaymentMeansID>\n";
		$l_retur.="<cbc:Amount currencyID=\"$l_valutakode\">".sprintf("%01.2f", ($l_sumbeloeb+$l_momsbeloeb))."</cbc:Amount>\n";
		$l_retur.="</cac:PaymentTerms>\n";
	}
	$l_retur.="<cac:TaxTotal>\n";
	$l_retur.="<cbc:TaxAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_momsbeloeb)."</cbc:TaxAmount>\n";
	$l_retur.="<cac:TaxSubtotal>\n";
	$l_retur.="<cbc:TaxableAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_momspligtigt)."</cbc:TaxableAmount>\n";
	$l_retur.="<cbc:TaxAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_momsbeloeb)."</cbc:TaxAmount>\n";
	$l_retur.="<cac:TaxCategory>\n";
	$l_retur.="<cbc:ID schemeAgencyID=\"320\" schemeID=\"urn:oioubl:id:taxcategoryid-1.1\">StandardRated</cbc:ID>\n";
	$l_retur.="<cbc:Percent>".$l_momssats."</cbc:Percent>\n";
	$l_retur.="<cac:TaxScheme>\n";
	$l_retur.="<cbc:ID schemeAgencyID=\"320\" schemeID=\"urn:oioubl:id:taxschemeid-1.1\">63</cbc:ID>\n";
	$l_retur.="<cbc:Name>Moms</cbc:Name>\n";
	$l_retur.="</cac:TaxScheme>\n";
	$l_retur.="</cac:TaxCategory>\n";
	$l_retur.="</cac:TaxSubtotal>\n";
	$l_retur.="</cac:TaxTotal>\n";
	$l_retur.="<cac:LegalMonetaryTotal>\n";
	$l_retur.="<cbc:LineExtensionAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_sumbeloeb)."</cbc:LineExtensionAmount>\n";
	$l_retur.="<cbc:TaxExclusiveAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_momsbeloeb)."</cbc:TaxExclusiveAmount>\n";
	$l_retur.="<cbc:TaxInclusiveAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", ($l_sumbeloeb+$l_momsbeloeb))."</cbc:TaxInclusiveAmount>\n";
	$l_retur.="<cbc:PayableAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", ($l_sumbeloeb+$l_momsbeloeb))."</cbc:PayableAmount>\n";
	$l_retur.="</cac:LegalMonetaryTotal>\n";


# Ordrelinjer
	$query = db_select("select * from ordrelinjer where ordre_id = $l_ordreid and vare_id !='0' and antal != '0' order by posnr",__FILE__ . " linje " . __LINE__);

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
		$pris=$pris-($r_linje['rabat']*$pris)/100; #20140206 + næste 2 linjer
		$linjepris=afrund($r_linje['antal']*$pris,2);
		$pris=afrund($pris,2); 
		$linjemoms=afrund($linjepris/100*$varemomssats,2);

		if ($l_ptype=="PCM") {
			$l_fortegn=-1;
		} else {
			$l_fortegn=1;
		}
		$l_retur.="<cac:".$l_doctype."Line>\n";
		$l_retur.="<cbc:ID>".$r_linje['posnr']."</cbc:ID>\n";
		$l_retur.="<cbc:".$l2_doctype."Quantity unitCode=\"".oioubl_enhed($enhed)."\">".$l_fortegn*$r_linje['antal']."</cbc:".$l2_doctype."Quantity>\n";
		$l_retur.="<cbc:LineExtensionAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_fortegn*$linjepris)."</cbc:LineExtensionAmount>\n";
		$l_retur.="<cac:TaxTotal>\n";
		$l_retur.="<cbc:TaxAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $linjemoms)."</cbc:TaxAmount>\n";
		$l_retur.="<cac:TaxSubtotal>\n";
		$l_retur.="<cbc:TaxableAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_fortegn*$linjepris)."</cbc:TaxableAmount>\n";
		$l_retur.="<cbc:TaxAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $l_fortegn*$linjemoms)."</cbc:TaxAmount>\n";
		$l_retur.="<cac:TaxCategory>\n";
		if ($momsfri) $l_retur.="<cbc:ID schemeAgencyID=\"320\" schemeID=\"urn:oioubl:id:taxcategoryid-1.1\">ZeroRated</cbc:ID>\n";
		else $l_retur.="<cbc:ID schemeAgencyID=\"320\" schemeID=\"urn:oioubl:id:taxcategoryid-1.1\">StandardRated</cbc:ID>\n";
		$l_retur.="<cbc:Percent>".$varemomssats."</cbc:Percent>\n";
		$l_retur.="<cac:TaxScheme>\n";
		$l_retur.="<cbc:ID schemeAgencyID=\"320\" schemeID=\"urn:oioubl:id:taxschemeid-1.1\">63</cbc:ID>\n";
		$l_retur.="<cbc:Name>Moms</cbc:Name>\n";
		$l_retur.="</cac:TaxScheme>\n";
		$l_retur.="</cac:TaxCategory>\n";
		$l_retur.="</cac:TaxSubtotal>\n";
		$l_retur.="</cac:TaxTotal>\n";
		$l_retur.="<cac:Item>\n";
		$l_retur.="<cbc:Description>".$beskrivelse."</cbc:Description>\n";
		$tmp=substr(utf8_decode($beskrivelse),0,40);
		$tmp=utf8_encode($tmp);
		$l_retur.="<cbc:Name>".$tmp."</cbc:Name>\n";
#		$l_retur.="<cbc:Name>".substr($beskrivelse,0,15)."</cbc:Name>\n";
		$l_retur.="<cac:SellersItemIdentification>\n";
		$l_retur.="<cbc:ID>".$varenr."</cbc:ID>\n";
		$l_retur.="</cac:SellersItemIdentification>\n";
		$l_retur.="</cac:Item>\n";
		$l_retur.="<cac:Price>\n";
		$l_retur.="<cbc:PriceAmount currencyID=\"$l_valutakode\">".sprintf("%01.2f", $pris)."</cbc:PriceAmount>\n"; # 20120515
		$l_retur.="<cbc:BaseQuantity unitCode=\"ANN\">1</cbc:BaseQuantity>\n";
		$l_retur.="<cbc:OrderableUnitFactorRate>1</cbc:OrderableUnitFactorRate>\n";
		$l_retur.="</cac:Price>\n";
		$l_retur.="</cac:".$l_doctype."Line>\n";
	}
	$l_retur.="</".$l_doctype.">\n";



# $l_retur.=oioubl_bottom($l_doctype);

	return $l_retur;
}

function oioubl_top($l_doctype="", $l_ptype="", $l_testdoc="", $l_ordreid="") {
	
	if ((!$l_doctype)||(!$l_ptype)) return "";

	$l_ptype=strtolower($l_ptype);


	if ($l_testdoc) $l_retur.= oioubl_test($l_doctype, $l_ordreid);

	return $l_retur;
}

function oioubl_bottom($l_doctype="") {

	if (!$l_doctype) return "";

	$l_retur="</$l_doctype>\n";

	return $l_retur;
}

function oioubl_test($l_doctype="", $l_ordreid="") {

	global $version;
	global $oioxmlubl;

	if ((!$l_doctype)||($l_ordreid=="")) return "";

	$l_retur.="<!--\n";
	$l_retur.="********************************************************************************\n";
	$l_retur.="\n";
	$l_retur.="\ttitle=saldi-3.2.6-test-$l_doctype-$l_ordreid.xml\n";
	$l_retur.="\tpublisher=\"DANOSOFT ApS\"\n";
	$l_retur.="\tcreator=\"SALDI $version\n";
	$l_retur.="\tcreated=".date("Y-m-d")."\n";
	$l_retur.="\tmodified=".date("Y-m-d")."\n";
	$l_retur.="\tissued=".date("Y-m-d")."\n";
	$l_retur.="\tconformsTo=\"?\"\n";
	$l_retur.="\tdescription=\"This document is produced as a part of testing $oioxmlubl using SALDI\"\n";
	$l_retur.="\trights=\"For now - Copyright DANOSOFT ApS 2011\"\n";
	$l_retur.="\n";
	$l_retur.="\tAll terms derived from http://dublincore.org/documents/dcmi-terms/\n";
	$l_retur.="\n";
	$l_retur.="\tFor more information, see www.oioubl.dk and www.saldi.dk\n";
	$l_retur.="\tor email phr@saldi.dk and oioubl@itst.dk\n";
	$l_retur.="\n";
	$l_retur.="********************************************************************************\n";
	$l_retur.="-->\n";
	$l_retur.="<!-- The following TestInstance process instruction is for customization usage. ";
	$l_retur.="It indicates that the instance is for testing purposes -->\n";
/*	PHR 20111113 Maa ikke staa i xml fil.
	$l_retur.="<?TestInstance\n";
	$l_retur.="\tResponseTo=\"smtp:phr@saldi.dk\"\n";
	$l_retur.="\tdescription=\"Test document from the Danish Open Source accounting system SALDI - http://saldi.dk/\"\n";
	$l_retur.="?>\n";
*/
	return $l_retur;
}


function oioubl_momssats($l_momsfri="", $l_varenr) {

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


function oioubl_momskode($l_momsfri="") {

	if ($l_momsfri == "on" ) {
		return "ZERO-RATED";
	} else {
		return "VAT";
	} 
}


function oioubl_betalingsdato($l_dato="", $l_betingelse="", $l_dage=0) {

	if (!$l_dato) return "";

	$l_retur = $l_dato;

	if (strtolower($l_betingelse)=="netto") {
		list ($l_aar, $l_md, $l_dag) = explode("-", $l_dato);
		$l_tidsstempel=mktime(12, 00, 00, $l_md, $l_dag, $l_aar); # Saettes til 12:00 for at forhindre sommertidsproblemer
		if ($l_dage > 0) $l_tidsstempel=$l_tidsstempel+(24*60*60*$l_dage);
	} 

# Skal gennemtestes om den regner det rigtigt
	if (strtolower($l_betingelse)=="lb. md.") {
		list ($l_aar, $l_md, $l_dag) = explode("-", $l_dato);
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
function oioubl_typecodeid ($l_fi="", $l_bankreg="", $l_bankkonto="", $l_pbs="") {

	if ((!$l_fi) && (!$l_bankreg) && (!$l_bankkonto) && (!$l_pbs)) return "";

	$l_retur="";

	if ($l_bankreg && $l_bankkonto) $l_retur="null";
	if ($l_fi) $l_retur="71";
	if ($l_pbs) $l_retur="null";

	return $l_retur;
}


function oioubl_paymentchannelcode ($l_fi="", $l_bankreg="", $l_bankkonto="", $l_pbs="") {

	if ((!$l_fi) && (!$l_bankreg) && (!$l_bankkonto) && (!$l_pbs)) return "";

	$l_retur="";

	if ($l_bankreg && $l_bankkonto) $l_retur="KONTOOVERFØRSEL";
	if ($l_fi) $l_retur="INDBETALINGSKORT";
	if ($l_pbs) $l_retur="DIRECT DEBET";

	return $l_retur;
}

function oioubl_kontaktinfo ($l_id="", $l_type) { # $l_type = BuyerContact

	if (!$l_type) return "";
#	$l_retur="\t\t<cbc:$l_type>\n";
	if (!$l_id) { 
		$l_retur.="<cbc:ID>n/a</cbc:ID>\n";
	} else {
		if (is_numeric($l_id)) {
			# Slaa op i tabellen for medarbejdere/kontaktpersoner
			$query = db_select("select * from ansatte where id = '$l_id'",__FILE__ . " linje " . __LINE__);
			$r_kontakt = db_fetch_array($query);
			$l_kontaktid = "n/a";
			$l_kontaktinfo = "";
			if ($r_kontakt['navn']) {
				$l_kontaktinfo.="<cbc:Name>".$r_kontakt['navn']."</cbc:Name>\n";
				$l_kontaktid = $r_kontakt['navn'];
			}
			if ($r_kontakt['tlf']) { 
				$l_kontaktinfo.="<cbc:Phone>+45 ".$r_kontakt['tlf']."</cbc:Phone>\n";
			} elseif ($r_kontakt['mobil']) {
				$l_kontaktinfo.="<cbc:Phone>+45 ".$r_kontakt['mobil']."</cbc:Phone>\n";
			}

			if ($r_kontakt['initialer']) {
				$l_kontaktid = $r_kontakt['initialer'];
			}

			if ($r_kontakt['email']) {
				$l_kontaktinfo.="<cbc:E-Mail>".$r_kontakt['email']."</cbc:E-Mail>\n";
				$l_kontaktid = $r_kontakt['email'];
			}

			$l_retur.="<cbc:ID>".$l_kontaktid."</cbc:ID>\n";
			$l_retur.=$l_kontaktinfo;
		} else {
			if (eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $l_id)) $l_retur.="<cbc:E-Mail>".$l_id."</cbc:E-Mail>\n";
			$l_retur.="<cbc:ID>".$l_id."</cbc:ID>\n";
			if (!strtolower($l_id)=="n/a") $l_retur.="<cbc:Name>".$l_id."</cbc:Name>\n";
		}
	}

#	$l_retur.="\t\t</cbc:$l_type>\n";

	return $l_retur;
}

function oioubl_tlfnr($l_tlf="") {

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

function oioubl_vej ($l_addr1="", $l_del="vejnavn") {

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

function oioubl_landekode ($l_land="DK") {

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
		}
	}

	return $l_retur;
}

function oioubl_enhed ($l_enhed="") {

	return "EA";
#	if ($l_enhed) {
#		return $l_enhed;
#	} else {
#		return "enheder";
#	}

}
?>
