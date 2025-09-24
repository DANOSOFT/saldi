<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/ordre.php --- patch 4.1.1 --- 2025-09-04 ---
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------

// 20120822 Tilrettet til NETS leverandørservice - søg 20120822
// 20121213 Fejl i kostpris hvis køb er fordelt over flere ordrer. Søg 20121213
// 20130227 Fejl i kostpris hvis køb er fordelt over flere ordrer. Søg 20130227
// 20130320 Tilføjet mulighed for fravalg af logo på udskrift. Søg "PDF-tekst"
// 20130506 Tilføjet kontrol for om det er tilføjet varenúmmer uden at gemme inden fakturering. Søg 20130506
// 20130816 Projekt kommer ikke med ved kopiering af ordrer. Søg 20130816
// 20130820 Div tekstændringer ved kreditnota.
// 20130906 Varepris ved indsættelse af ny varelinje v. indtast af varenummer er nu '' istedet for 0.00. # søg 20130906
//  Bunder i ændring af funktionen opret_ordrelinje som nu ikke trækker pris fra varetabel hvis pris er sat til anden end '' 
// 20131004 Indsat afrunding da fakturering ikke kunne foretages grindet diff på meget lille brøk (php fejl) Søg  20131004
// 20131004 Addslashes erstattet med db_escape_string & stripslashes erstattet med HtmlEntities overalt. 
// 20131017 Indsat opslag på ekstrafelter v. opreoprettelse m. kontonummer skrever i stedet for opslag.Søg 20131017
// 20140112 Tilføjet individuelle mailemner/tekster og vedhæftning af bilag Søg mail_subject
// 20140112 Tilføjet mulighed for at ændre debitor ved opslag søg 20140112
// 20140112 Visning af kostpris forkert grundet ombytning af 100 & $valutakurs. Kun visning på ordre. Søg 20140116
// 20140130 Omskrevet kostprisberegningen grundet risiko for fejl ved køb i en fremmed valuta og salg i en anden fremmed valuta, når 
//            salg gennemføres før bogføring af købsordre 
// 20140324 Ændring på query til visning af notes fra adresser, så <tr> ikke vises med mindre der er note fra kundekort.Søg 20142403-1
// 20140324 lavet variable til meta-tag og placeret det i head-tag. Se ordrefunc.php function sidehoved. Søg 20142403-2
// 20140403 Momssats sættes til hvis ingen momsgruppe istedet for fejlmelding. 20140403
// 20140414 PHR - Fjernet udkommentering af javascript.
// 20140424 PHR - Rabat blev ikke fratrukket ved db beregning. 20140424
// 20140424 PHR - Mail bilag felt skal kun vises på udvikling og ssl3.
// 20140428 PHR - Formularsprog sættes før kald til opret_ordrelinje v. vareindsættelse fra opslag. Søg 20140428
// 20140502 PK - Diverse html rettelser i faktura. Søg 20140502
// 20140502 PK - Udkommenteret javascript er flyttet til 'top_header.php', 'top_header_sager.php' og 'online.php'
// 20140505 PHR - sag_id,sagsnr,tilbudnr,datotid,nr&returside medtages ved kopiering og kreditering af ordre. Søg Sagsnr eller sag_id 
// 20140505 PHR - Indsat $posnr,$varenr,$dkantal,$enhed,$dkpris,$dkprocent,$serienr,$varemomssats i kald til var2str så der kan anvendes variabler på ordrelinjer. Søg var2str
// 20140507 PK - Tilføjet 'input type hidden' med sag_id, så det kommer med i submit. Søg 20140507-1
// 20140507 PK - Indsat returside under slet, hvis der er sag_id: Søg 20140507-2
// 20140515 PK - rettet colspan='10' til colspan='12' i Tabel 4.5.1, så det passer i bredden.
// 20140716 PK - Tilbud bliver kopieret når det godkendes til ordrebekræftelse. Art i ordrer.php bliver lavet om til 'OT'. Søg 20140716
// 20140730 PK - Oprettet en ny funktion 'opret_ordre_kopi' i ordrefunc.php. Søg 20140730
// 20140826 PK - Kunde-kontakt kan vælges med dropdown eller skrives. Søg 20140826
// 20141002 PHR - Tilføjet omvendt betalingspligt - Søg omvbet, omkunde og omvare.
// 20141023 PHR - Fejl i kostprisberegning ved afsluttet ordre. Kostpris blev ganget med antal for at finde snitpris, men ikke divideret igen
// 20141211 PHR- Negativt antal i kreditnotaer er nu tilladt.
// 20150122 PHR - Tilføjet sætpriser -Søg saetpris & $saet.
// 20150130 PHR - Rettet moms og sum sammentælling til 3 decimaler for større nøjagtighed. 20150130
// 20150131 PHR - Mange rettelser til saetpriser.
// 20150222 PHR - Flere rettelser i forb. med sætpriser
// 20150302 PHR - En ordre må ikke kunne godkendes hvis der ikke er sat debitor på. 
//    Samtidig er der ikke moms på priser når der ikke er debitor på selvom der er momssats på ordren. 20150302
// 20150302 PHR - Afd blev nulsstillet ved ændring af debitor. : 20150302
// 20150304 PHR - Fejl hvis intet ordre id : 20150304
// 20150313 PHR - Advarsel ved fakturering hvis betalingsbet er netto eller lb.mb og der er betalt med kort eller kontant. 20150313
// 20150307 PHR - Ændret søgning efter varenr 'R' til $rvnr, insdat mulighed for nulstilling af rabat ved at sætte samlet pris til '-'
//             og ændret 'if (!$samlevare) $ny_pos++' til 'if (!$saet || !$samlevare) $ny_pos++' da den ellers slettede samlevarer som ikke indgik i sæt #20150317
// 20150318 PHR - Samlet rabat fungerer nu også på momsfrie ordrer.
// 20150407 PHR - Ved indsættelse fra vareopslag skrives antal, beskrivelse & pris som "placeholder" 20150407
// 20150409 PK - Ved indsættelse af flere linjetekster til tilbud, laves nyt posnr til hver linje.
// 20150412 PHR - Der kan tilføjes tekstlinjer på afsluttede ordrer. Søg 20150412
// 20150424 PHR - Tilføjet: "and posnr >= 0" 20150524
// 20150602 PHR - Udfaset oioxml - Søg oioxml
// 20150602 PHR - Returside skal kun hentes fra tabel hvis den ikke er sat. 20150602 
// 20150829 PHR - Tilføjet $incl_moms til opret_saet.
// 20150829 PHR - Tilføjet $brugsamletpris så sætfunktion kan bruges uden sammenhæng med butik.
// 20150914 PHR - Kommentarer blev lagt i bund hvis brugsamletpris var slået til. 20150914
// 20150917 PHR - Linjepriser blev vist incl. moms på faktureredet ordrer med samlevare. indsat $incl_moms i if sætning. 
// 20151019 PHR - Ved indsættelse af varenummer hopper cursor nu til antal og hvis pris=0 til pris, ellers til varenummer på ny linje 20151019
// 20160112 PHR - Lidt designrettelser vedr vis_projekt og kdo på ordrelinjer. Tak til Asbjørn, Musalk.
// 20160129 PK  - Har tilføjet kontakt_tlf. Tlf hentes fra kontakt ved valg fra select, ellers indtastes tlf i felt. Søg. #20160129
// 20160217 PHR - Fejl v. kreditering hvis kundes kontonr er blevet ændret. Søg #20160217
// 20160303 PK - Har ændret E-mail til dropdown + textfield. E-mail fra kunde vises stadig, mens e-mails fra kundekontakter vises i dropdown. Kan stadig skrive e-mail i textfield. Søg. #20160303
// 20160913 PHR - Efter indsættelse af vare med følgevare fik næste vare samme antal som vare med følgevare. Søg 20160913.
// 20160913 PHR - OIOUBL blev danne som PDF vet fakturering Søg 20160913
// 20160915 PHR - Fokus fungerede ikke med følgevarer på ordrer da der var flere forekomster af name='anta0' Søg 20160915
// 20161011 PHR - Tilføjet hidden varenr - er tidligere blevet fjernet, ved ikke hvorfor men det ødelægger 'samlet pris' 21061011
// 20170103 PHR - Funktion find_nextfakt flyttet til ../includes/ordrefunc.php
// 20170307 PHR - Diverse rettelser i forhold til flerlagerstyring. søg $lagernr.
// 20170308 PHR - Hvis lagernavn er på et tegn vises dette i stedet for lagernr.
// 20170318 PHR - En del ændreinger i visning or sortering af sæt. 20170318
// 20170323 PHR - Integreret kortbetaling. Søg $terminal_ip
// 20170419 PHR - Sælger (Vor ref)) skal vælges hvis vis_saet 20170419
// 20170421 PHR - $terminal_ip sættes kun hvis der er der er terminal i afdelingen. 20170421
// 20170501 PHR - Automatisk genkendelse af kort ved integreret terminal. Søg 'kortnavn'
// 20170627 PHR - Tilføjet lager[0] til opret_saet. Søg 20170627
// 20170703 PHR - Kostpriser opdateres løbende ved åbne ordrer. Søg 20170703
// 20170907 PHR - Ovenstående rettet så den kun gælder ved % kostpriser da der ellers ødelægger mulighed for ændring af kostpris ved at skrive ny kostpris i parantes efter pris.
// 20171004 PHR - En del rettelser omkring betailngskort - primært styres betalingsbet nu fra valg af betalingsmåde når vis_saet er aktiv.
// 20171009 PHR - GLS funktion ændret - skal tilrettes så gls værdier kan sættes under ordrerelaterede valg- søg $gls_user
// 20171026 PHR - $db ændret til $dkb(dækningsbidrag) da $db bruges til databaseinfo. 
// 20180116 PHR - Mulighed for at medsende standardbilag. Søg std_bilag & mail_bilag
// 20180302 PHR - Betalings_id vises nu hvis feltet er udfyldt - Søg betalings_id
// 20180305 PHR - htmlentities foran beskrivelse og varenr. 20180305
// 20180316 PHR - Kasse kan nu være andet end afd -- 20180316'.
// 20180725 PHR - $sum += flyttet til over 'if ($incl_moms)' da ex_moms sum blev reduceret med m_rabat incl moms. 20180725
// 20180822 PHR - Ændret valg af betalingsbet ved kreditering / vis_saet  20180822
// 20180913 PHR - Tilføjet mulighed for at trække levering tilbage ved at sætte negativt antal i 'lever' på ordre 20180913.
// 20181216 PHR - dkdecimal på felt 1 & 2 da beløb blev forkerte ved kopiering eller kreditering når vis_saet er aktiveret. 20181216
// 20181217 PHR - Tilføjet knap 'Skift' v. debitor kontonr. Søg swap_account
// 20181218 PHR - Rettet fejl i valg af betalingsbet ved kontosalg. 20181218
// 20181221 MSC - Rettet isset fejl
// 20190104 PHR - Customer can now be created from customerorder. seek create_debtor.
// 20190110 PHR - '$reseveret' was defined as both slngle variable og array - changed to array only 
// 20190212 MSC - Rettet db_modify fejl og rettet topmenu design til
// 20190213 MSC - Rettet topmenu design til
// 20190416 PHR - Added localPrint for printing through local webserver (raspberry) 
// 20190502 PHR - Changed GLS label to include invoice att if delivery att not set.   
// 20190520 PHR - Changed GLS label to include Contact ID. $gls_ctId
// 20190521 PHR - Adde extra control to aviod faulty registration when submitting invoice #20190521
// 20190703 PHR - EAN can now be changed after invoice creation.
// 20191004 PHR - Field 1-5 will not be copied if the order copied from is a 'POS' order as it disturbs'endofday' counting 20191004 
// 20191104 PHR - Added possibility to delete orderline if non stock item is delivered. 20191104
// 20191105 PHR - Added quantity field to add more items at a time. $insetQty.
// 20200211 PHR - Check for valid VAT no format 20200211
// 20200308 PHR - Added copy option for status < 3.
// 20200317 PHR - Changed bordercolor for tables where border='1'.
// 20200407 PHR - Added id lookup by $_GET['kunderordnr'] 20200407
// 20200917 PHR - Added missing email in create_debtor
// 20201115 PHR - Added weights and measures and adjusted GLS & Fedex | $tGrossWeight etc.
// 20201116 PHR - Added missing fields in call to function 'opret_ordrelinje' 20201116
// 20201120 PHR - Somehow a line 'db_modify("update ordrelinjer set lev_varenr ......' was deleted in 20201116 update.???
// 20201215 PHR - $restordre now set to 0 if not set. 20201215 
// 20210302 PHR - Added phone and cleaned for notices. 
// 20210303 CA  - Added reservation of consignment for Danske Fragtmænd - search dfm_
// 20210305 CA  - Added the selection to use debtor number as phone number in orders - search div2
// 20210305 CA  - Changed the phone field so it can be changed also when status is invoice or credit note
// 20210310 PHR - If '@' in $phone and email is empty $phonevalue is moved to email. Find strpos($phone,'@')
// 20210312 PHR - Removed '#' in front of line - don't why it was set. 20210312
// 20210503 PHR - Qty now red if stock below minimum stock 20210503
// 20210506 PHR - Phone set to account no if not set when sending to 'DFM' 20210506
// 20210510 PHR - Added '?id=$id&tabel=ordrer' to luk.php 20210510
// 20210629 LOE - Translated some of these texts from Danish to English and Norsk
// 20210630 LOE - Set the width of these button to auto as given width was covering texts.
// 20210715 LOE - Fixed a bug of  A non-numeric value encountered for $rabatvare_id, and also for $sag_id, for $ref text translation for titles
// 20210719 LOE - Translated confirm texts, $tidspkt is set with some more variables,$posnr_ny[$x] is converted to integer
// 20210801 CA  - Added the selection to use order notes in ordre_valg - search orderNoteEnabled
// 20210806 LOE - Translated some texts.
// 20210809 LOE - Translated alert texts
// 20210816 CA  - Order note also for invoiced orders
// 20210817 MSC - Implementing new design
// 20211021 PHR/MSC orderNote hidden until link clicked
// 20211028 PHR - Corrected some text errors in 'Danske Fragtmænd'
// 20211028 PHR - Changed 'text id 924 & 1465 to 1062 & 1063'
// 20211118 MSC - Implementing new design
// 20211124 PHR - Changed Vat check as NL has a B as digit 10. 
// 20211129 PHR - When making a creditnote from an order with variants, the variant id was not transferred. 
// 20220119 PHR - $id was not set when returning from create debtor???
// 20220301 PHR - Minor change in order locking
// 20220630 MSC - Implementing new design
// 20220706 MSC - Implementing new design
// 20220712 MSC - Implementing new design
// 20230505 PHR - php8
// 20230612 PBLM - Added functionality to handle the new 'EasyUBL' module (peppol)
// 20230719 LOE - Minor modifications; it was throwing error when kontonr not set
// 20230829 MSC - Copy pasted new design into code
// 20231215 PBLM - Made some adjustments to EasyUBL  
// 20240201 PBLM - Made some adjustments to EasyUBL
// 20240303 PHR - Changed $rabat decimal precision from 3 to 5 
// 20240416 LOE - $std_txt initialized to null
// 20240423 LOE - Some bugs fixed "'" omitted in some database calls added
// 20240425 LOE - Added some classes to control some of the tr
// 20240426 LOE - Fixed the style error in the td display
// 20240630 MMK - debitoripad
// 20240703 PHR - Removed "and samlevare != 'on'" as it blocked invoice for orders with 'samlevare'
// 20240725 PHR - Replaced 'DKK' with $baseCurrency.
// 20240728 PHR - Moved texts to tekster.scv
// 20240815 PHR	-  $title 
// 20240828 PHR - Corrected 'change customer' to search for customer no first and then phone. 
// 20240925	PHR Some translations
// 20241003 PHR More transations
// 20241018 PBLM fixed del ordre
// 20241213 MMK added warning to delete line
// 20241222 LOE Added $_GET methods for some variables to query OPSLAG implementation directly+20241223 added formdata query to vareopslag function
// 20241228 LOE Added a way to check if pricelist url is either GET method or from pricelist file.
// 20241229 LOE Option to select between external and internal pricelist added, and isset($_GET['varenr']) is included to enable this
// 20250213 PHR Vat rate defined by department(afd)
// 20250228 LOE Casting of some variables to explicit int instead of using '*1'
// 20250404 PHR Added call to updateOrderCost
// 20250421 LOE A lot of array values updated with if_isset function to prevent numerous undefined errors, and some clean ups.
// 20250705 PHR $afd is now set if afd exist
// 20250811 PHR Corrected wrong text numbers
// 20250819 LOE $afd checked strictly before usage
// 20250903 LOE Enabled order to still work with account lookup when 'Offer' is active

@session_start();
$s_id=session_id();

$antal=$beskrivelse=$enhed=$lagernr=$ordreliste=$pris=$reserveret=$varenr=array();
$afd_lager=$antal[0]=$art=NULL;
$brugernavn=NULL;
$default_procenttillag=$digital=NULL;
$fakturadate=$fakturadato=$felt_1=$felt_2=$felt_3=$felt_4=$felt_5=$firmanavn=$fglv=NULL;
$genfakt=$gl_id=$gruppe=NULL;
$konto_id=$kontonr=$kred_ord_id=$krediteret=$kundeordnr=0;
$lager[0]=$lev_kontakt=$levdate=$lev_navn=$lev_email=$localPrint=NULL;
$masterprojekt=$mail_fakt=$modtaget=$moms=$momsfri[0]=NULL;
$nextfakt=$notes=NULL;
$oioxml=$oioubl=$ordrenr=NULL;
$pbs=$phone=$prev_id=$pris[0]=$procenttillag=$procentvare=NULL;
$qtext=NULL;
$ref=$restordre=$rvnr=NULL;
$status=$swap_account=NULL;
$tdlv=NULL;
$valgt=$varenr[0]=$valuta=$vis_lev_addr=$vis_projekt=NULL; 
$width=NULL;
$fast_db=array();
$sletslut=$sletstart=0;

$modulnr=5;

$css="../css/standard.css";
#$css="../css/ordre.css";
include("../includes/std_func.php");


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/var2str.php");
include("../includes/ordrefunc.php");
include("../includes/tid2decimal.php");

$title = findtekst('1092|Kundeordre', $sprog_id);
$txt370 = findtekst('370|Kontant',$sprog_id);
$txt283 = findtekst('283|Kreditkort',$sprog_id);

$localPrint=if_isset($_COOKIE, NULL,'localPrint');
#print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>\n";
#print "<script type=\"text/javascript\" src=\"https://code.jquery.com/jquery-latest.min.js\"></script>\n";
#print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/arrowkey.js\"></script>\n";
$funktion=if_isset($_GET,NULL,'funktion');
$tidspkt=date("U"); #20210719

$id        = if_isset($_GET, NULL, 'id');
$funktion  = if_isset($_GET, NULL, 'funktion');
$sag_id    = if_isset($_GET, NULL, 'sag_id'); // 20241126
$konto_id  = if_isset($_GET, NULL, 'konto_id');
$showLocalPrint='';
$qtxt="select box1 from grupper where art='PV'";




if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)) && $r['box1']=='on') $showLocalPrint='on';$title=findtekst('1092|Kundeordre', $sprog_id);


if (isset($_POST['create_debtor'])) {
	$kontonr=(int)if_isset($_POST['kontonr'],0);
	$firmanavn=if_isset($_POST['firmanavn']);
	$addr1=if_isset($_POST['addr1']);
	$addr2=if_isset($_POST['addr2']);
	$postnr=if_isset($_POST['postnr']);
	$bynavn=if_isset($_POST['tlf']);
	$email=if_isset($_POST['email']);
	$phone=if_isset($_POST['phone']);
	if (substr($email, 0, 11 ) == "debitoripad") {
		$q = db_select("select email, firmanavn, phone, addr1, postnr, bynavn from ordrer where id='$_GET[id]'",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q);
		$email = $r["email"];
		$phone = $r["phone"];
		$firmanavn = $r["firmanavn"];
		$addr1 = $r["addr1"];
		$postnr = $r["postnr"];
		$bynavn = $r["bynavn"];
	}
	$cvrnr=if_isset($_POST['cvrnr']);
	$grp=if_isset($_POST['grp']);
	$ean=if_isset($_POST['ean']);
	$betalingsbet=if_isset($_POST['betalingsbet'],'netto');

	if(!$grp){ //if not checked, failed for new accounts. //20250903
		if (!$grp) {
			echo "<script>
				alert('Group is required Goto: System->Settings->Debitor/Creditor Group');
				window.location.href = '" . ($_SERVER['HTTP_REFERER'] ?? 'index.php') . "';
			</script>";
			exit;
		}
	}
  if ($betalingsbet == findtekst('370|Kontant',$sprog_id)) $betalingsbet = 'Kontant';
	$betalingsdage=(int)if_isset($_POST['betalingsdage'],8);
	$kontakt=if_isset($_POST['kontakt']);

	$konto_id=create_debtor($kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$email,$phone,$cvrnr,$grp,$ean,$betalingsbet,$betalingsdage,$kontakt);
	if (!$konto_id) $konto_id=0;
	if (!$id && isset($_GET['id'])) $id=$_GET['id']; #20221019
	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id&konto_id=$konto_id\">\n";
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_active_pricelist_file'])) {
    $selectedId = $_POST['change_active_pricelist_file'];
    db_modify("UPDATE grupper SET box4 = '' WHERE art = 'PL'", __FILE__ . " linje " . __LINE__);
    db_modify("UPDATE grupper SET box4 = 'Yes' WHERE id = '" .$selectedId. "'", __FILE__ . " linje " . __LINE__);
    error_log( "Updated successfully for ID: " . htmlspecialchars($selectedId));
	exit;
}


if (isset($_GET['id']) && isset($_POST['insertItems']))	{
	$insertId=$_POST['insertId'];
	$insertQty=$_POST['insertQty'];
	for ($x=0;$x<count($insertId);$x++){
	if ($insertQty[$x]) {
	opret_ordrelinje($_GET['id'],$insertId[$x],'',$insertQty[$x],'','','','','DO','','','','','','','','','','','','');
		}
	}
}
$q = db_SELECT("select box1,box2,box4,box9,box12,box13,box14 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__);
$r=db_fetch_array($q);
$incl_moms=$r['box1'];

#$rabatvare_id=$r['box2']*1; #20150317
$rb = $r['box2'];
$rabatvare_id = (int)$rb; #20210715
$hurtigfakt=$r['box4'];
$negativt_lager=$r['box9'];
$procentfakt=$r['box12'];
($r['box13'])?$box13=$r['box13']:$box13='0'.chr(9).''; 
list($default_procenttillag,$procentvare)=explode(chr(9),$box13);
$default_procenttillag=str_replace(",",".",$default_procenttillag);
$brugsamletpris=$r['box14'];
if ($brugsamletpris) {
	$r=db_fetch_array(db_SELECT("select box8 from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__));
	$svid=$r['box8']*1;
	$r=db_fetch_array(db_SELECT("select varenr from varer where id = '$svid'",__FILE__ . " linje " . __LINE__));
	$svnr=$r['varenr'];
} else $svnr=NULL;
if ($rabatvare_id) { #20150317
	$query = db_select("select varenr from varer where id = '$rabatvare_id'",__FILE__ . " linje " . __LINE__);
	if(pg_num_rows($query) > 0){
		$r=db_fetch_array($query);
		$rabatvare_nr=$r['varenr'];
	}
} else $rabatvare_nr=NULL;
$qtxt = "select box2 from grupper where art='OreDif'";
($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$difkto=$r['box2']:$difkto=0;
$qtxt = "select box12 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'";
($r=db_fetch_array(db_select($qtxt,__FILE__ . "linje " . __LINE__)))?$vis_saet=$r['box12']:$vis_saet='';
if ($vis_saet) $brugsamletpris='on';

$l=0;
$qtxt = "select kodenr,beskrivelse from grupper where art='LG'"; // No fiscal_year @ art = LG
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$lagernr[$l]=$r['kodenr'];
	$lagernavn[$l]=$r['beskrivelse'];
	$l++;
}
$lagerantal=$l;

if (empty($sag_id)) { #20210715
	$id=if_isset($_GET['id']);
	if (!$id) $id=if_isset($_GET['ordre_id']);
}
if ((!$id) && $funktion=='opret_ordre') {
	$id = opret_ordre($sag_id,$konto_id);
}
if ((!$id) && $funktion=='opret_ordre_kopi') { #20140730
	$id = opret_ordre_kopi($sag_id,$konto_id);
}

if($id && isset($_POST['tilfoj']) && $ekstratekst=(if_isset($_POST['ekstratekst']))) { #20150412
	$r=db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id='$id'",__FILE__ . " linje " . __LINE__));
	db_modify("insert into ordrelinjer (posnr,beskrivelse,ordre_id) values ('$r[posnr]'+1,'".db_escape_string($ekstratekst)."','$id')",__FILE__ . " linje " . __LINE__); 
}
if (isset($_GET['kundeordnr']) && $_GET['kundeordnr']) { #20200407
	$qtxt="select max(id) as id from ordrer where kundeordnr ='". db_escape_string($_GET['kundeordnr']) ."'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
}
$returside=if_isset($_GET,NULL,'returside');
if (isset($sag_id)) { // Returside sættes til 'sager' fra sager.php #20210715
#  $returside=urlencode("../sager/sager.php?funktion=vis_sag&amp;sag_id=$sag_id");
}
if ($popup) $returside="../includes/luk.php?id=$id&tabel=ordrer";

if (($ret_tekst=if_isset($_GET,NULL,'ret_tekst')) && ($id=if_isset($_GET,NULL,'id'))) tekstopslag($sort,$id);

if (($tekst_id=if_isset($_GET,NULL,'tekst_id')) && ($id=if_isset($_GET,NULL,'id'))) {
	if ($slet_tekst=if_isset($_GET,NULL,'slet_tekst')) {
		db_modify("delete from ordretekster where id = '$slet_tekst'",__FILE__ . " linje " . __LINE__);
		header("location:ordre.php?id=$id&ret_tekst=$id"); 
		exit();
	} elseif ($r=db_fetch_array(db_select("select tekst from ordretekster where id = '$tekst_id'",__FILE__ . " linje " . __LINE__))) {
		db_modify("insert into ordrelinjer (ordre_id,beskrivelse,posnr) values ('$id','".db_escape_string($r['tekst'])."','9999')",__FILE__ . " linje " . __LINE__);
	}
}
if (($tekst_sag_id=if_isset($_GET,NULL,'tekst_sag_id')) && ($id=if_isset($_GET,NULL,'id'))) {
	$r=db_fetch_array(db_select("select omfang from sager where id = '$tekst_sag_id'",__FILE__ . " linje " . __LINE__));
	db_modify("insert into ordrelinjer (ordre_id,beskrivelse,posnr) values ('$id','".db_escape_string($r['omfang'])."','9999')",__FILE__ . " linje " . __LINE__);
}
if ($ny_linjetekst=if_isset($_POST,NULL,'ny_linjetekst')) {
	$ny_linjetekst=db_escape_string($ny_linjetekst);	
	if (!$r=db_fetch_array(db_select("select id from ordretekster where tekst = '$ny_linjetekst'",__FILE__ . " linje " . __LINE__))){
		db_modify("insert into ordretekster (tekst) values ('$ny_linjetekst')",__FILE__ . " linje " . __LINE__);
	}
	if ($id=if_isset($_POST,NULL,'id')) {
		db_modify("insert into ordrelinjer (ordre_id,beskrivelse,posnr) values ('$id','$ny_linjetekst','9999')",__FILE__ . " linje " . __LINE__);
	}
}
// Her hentes flere linjetekster til tilbud
$posnrstart=NULL;
if((isset($_POST['linjetekster']))&& ($id=if_isset($_POST,NULL,'id'))) {
	$r=db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__));
	$posnrstart=$r['posnr']+100;
	foreach($_POST['linjetekster'] as $linjetekster){
		$nyposnr=$posnrstart+=1;
		if($r=db_fetch_array(db_select("select tekst from ordretekster where id = '$linjetekster'",__FILE__ . " linje " . __LINE__))) {
			db_modify("insert into ordrelinjer (ordre_id,beskrivelse,posnr) values ('$id','".db_escape_string($r['tekst'])."','$nyposnr')",__FILE__ . " linje " . __LINE__);
		}
	} 
}
#$alert = findtekst(15)
if ($tjek=if_isset($_GET,NULL,'tjek')){
	$qtxt="select tidspkt,hvem from ordrer where status < 3 and id = $tjek and hvem != '$brugernavn'"; #20220301
  if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))  {
		if ($r['tidspkt'] && $tidspkt-($r['tidspkt'])<3600 && $r['hvem']){
			print "<BODY onLoad=\"javascript:alert('".findtekst('1542|Ordren er i brug af', $sprog_id)." $r[hvem]')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
		} else {
			db_modify("update ordrer set hvem = '$brugernavn',tidspkt='$tidspkt' where id = '$tjek'",__FILE__ . " linje " . __LINE__);
		}
	}
}
if (!$id) $id=if_isset($_GET['ordre_id']);
$sort=if_isset($_GET,NULL,'sort');
$fokus     = if_isset($_GET, NULL, 'fokus');
$b_submit  = if_isset($_GET, NULL, 'funktion');
$vis_kost  = if_isset($_GET, NULL, 'vis_kost');
if ($sort && $fokus && $b_submit=='vareOpslag') {
	$qtxt="update settings set var_value='$sort' where var_name='vareOpslag' and var_grp='deb_ordre' and user_id='$bruger_id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
  #  sidehoved($id,"ordre.php","","","Vareopslag");
	vareopslag($art,$sort,$fokus,$id,$vis_kost,$ref,0); 
} elseif ($sort && $fokus && $b_submit=='kontoOpslag') {
	sidehoved($id,"ordre.php","","","Kontoopslag");
	kontoopslag($art,$sort,$fokus,$id,$vis_kost,$ref,0); 
}
$bogfor=1;

$gruppe = 0;
if ($id) {
	$r=db_fetch_array(db_select("SELECT adresser.gruppe,ordrer.status,ordrer.sprog FROM ordrer,adresser WHERE ordrer.id = '$id' AND adresser.id=ordrer.konto_id",__FILE__ . " linje " . __LINE__));
	$status=if_isset($r,0,'status');
	$gruppe=if_isset($r,0,'gruppe');
	$formularsprog=if_isset($r,'Dansk','sprog');
} 
$qtxt = "select id from grupper where art='DG' and kodenr='$gruppe' and box8='on'";
if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$incl_moms=NULL; #hvis box8 er 'on' er det en b2b kunde og priser vises ex. moms
}
if (isset($_GET['vis_lev_addr']) && $id) {
  if ($_GET['vis_lev_addr']) $qtxt = "update ordrer set vis_lev_addr='on' where id='$id'";
  else $qtxt = "update ordrer set vis_lev_addr='' where id='$id'";
  db_modify ($qtxt,__FILE__ . " linje " . __LINE__);

}
if (($kontakt=if_isset($_GET,NULL,'kontakt'))&&($id)) {
	db_modify("update ordrer set kontakt='$kontakt' where id='$id'",__FILE__ . " linje " . __LINE__);
	if (isset($_GET['email']) && $_GET['email']) {
		db_modify("update ordrer set email='$_GET[email]' where id='$id'",__FILE__ . " linje " . __LINE__);
	}
}

////// Tutorial //////
if($status < 3){
	$steps = array();
	$steps[] = array(
		"selector" => "[name=kontonr]",
		"content" => findtekst('2613|Når dette felt er aktivt, kan du trykke Enter med det samme for at søge i kundekataloget, eller du kan indtaste et nyt kontonummer og derefter trykke Enter for at oprette en ny kunde', $sprog_id)."."
	);
	$steps[] = array(
		"selector" => "#debitorkort",
		"content" => findtekst('2614|Når du har oprettet en ordre, kan du klikke her for at se kundens stamdata', $sprog_id)."."
	);
	$steps[] = array(
		"selector" => "[name=vare0]",
		"content" => findtekst('2615|Her kan du indtaste et varenummer for at tilføje en vare til ordren', $sprog_id)."."
	);
	$steps[] = array(
		"selector" => "[name=lookUp]",
		"content" => findtekst('2616|Når et varenummerfelt er aktivt, kan du foretage et opslag af alle dine varer ved at klikke her', $sprog_id)."."
	);
	$steps[] = array(
		"selector" => "[name=udskriv_til]",
		"content" => findtekst('2617|Her kan du vælge, hvordan ordren skal udskrives, når du fakturerer den – f.eks. til mail, som PDF eller sendt gennem NemHandel med digital fakturering', $sprog_id)."."
	);
	$steps[] = array(
		"selector" => "[name=betalingsbet]",
		"content" => findtekst('2618|Her kan du vælge dine betalingsbetingelser. Disse trækkes automatisk fra kundens opsætning.', $sprog_id)
	);
	$steps[] = array(
		"selector" => "[name=betalingsdage]",
		"content" => findtekst('2619|Her kan du vælge dine betalingsdage. Disse trækkes automatisk fra kundens opsætning.', $sprog_id)
	);
	$steps[] = array(
		"selector" => "[name=ean]",
		"content" => findtekst('2620|Her kan du indtaste et identifikationsnummer (f.eks. GLN eller EAN), for eksempel hvis du fakturerer offentlige instanser såsom kommuner eller bruger digitale faktureringssystemer. Angiv typen og nummeret, f.eks. \'GLN:123456789102\'', $sprog_id)
	);

	include(__DIR__ . "/../includes/tutorial.php");
	create_tutorial("deb-order", $steps);
}
////// Tutorial end //////


// $other_actions = ['print_to', 'copy', 'print', 'credit', 'save', 'submit', 'localPrint',];
// $has_other_action = false;
// foreach ($other_actions as $action) {
//     if (isset($_POST[$action])) {
// 		
//         $has_other_action = true;
//         break;
//     }
// }

// if (isset($_POST['invoice_dragdrop']) && $_POST['invoice_dragdrop'] == '1' && $id && !$has_other_action) {
//     $linjeantal = (int)if_isset($_POST, 0, 'linjeantal');
    
//     for ($x = 1; $x <= $linjeantal; $x++) {
//         $linje_id_val = if_isset($_POST, NULL, "linje_id")[$x] ?? 0;
//         $posn_val = if_isset($_POST, NULL, "posn$x") ?? 0;
        
//         if ($linje_id_val && is_numeric($posn_val)) {
//             $qtxt = "UPDATE ordrelinjer SET posnr = '$posn_val' WHERE id = '$linje_id_val' AND ordre_id = '$id'";
//             db_modify($qtxt, __FILE__ . " linje " . __LINE__);
//         }
//     }
    
//     
//     if (function_exists('updateOrderCost')) {
//         updateOrderCost($id);
//     }

// 	 if (isset($_POST['sprog'])) {
//         $formularsprog = db_escape_string($_POST['sprog']);
//         db_modify("UPDATE ordrer SET sprog = '$formularsprog' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
//     }

//     if (isset($_POST['pbs'])) {
//         $pbs_val = db_escape_string($_POST['pbs']);
//         db_modify("UPDATE ordrer SET pbs = '$pbs_val' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
//     }

// 	 if (isset($_POST['email'])) {
//         $email_val = db_escape_string($_POST['email']);
//         db_modify("UPDATE ordrer SET email = '$email_val' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
//     }

// 	  if (isset($_POST['phone'])) {
//         $phone_val = db_escape_string($_POST['phone']);
//         db_modify("UPDATE ordrer SET phone = '$phone_val' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
//     }

//     if (isset($_POST['udskriv_til'])) {
//         $udskriv_til_val = db_escape_string($_POST['udskriv_til']);
//         db_modify("UPDATE ordrer SET udskriv_til = '$udskriv_til_val' WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
//     }



	


// 	if (isset($_POST['genfakt'])) {
// 			$genfakt = trim($_POST['genfakt']);
// 			if ($genfakt !== '') {
// 				if ($genfakt !== '-') {
// 					db_modify("UPDATE ordrer SET nextfakt='".usdate($genfakt)."' WHERE id='$id'", __FILE__ . " linje " . __LINE__);
// 				} else {
// 					db_modify("UPDATE ordrer SET nextfakt=NULL WHERE id='$id'", __FILE__ . " linje " . __LINE__);
// 				}
// 			}
// 		}

    
// 
//     // print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id&returside=$returside\">\n";
//     // exit;
// }
if (!strstr($fokus,'lev_') && isset($_GET['konto_id']) && is_numeric($_GET['konto_id'])) { # <- 2008.05.11  Bliver kaldt ved skift af kontonr for ordrern
	$konto_id=$_GET['konto_id'];
	$q = db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$kontonr=$r['kontonr'];
		$firmanavn=db_escape_string($r['firmanavn']);
		$addr1=db_escape_string($r['addr1']);
		$addr2=db_escape_string($r['addr2']);
		$postnr=trim($r['postnr']);
		$bynavn = trim($r['bynavn']);
		$pbs = trim(if_isset($r['pbs']),NULL);
		if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
		$bynavn = db_escape_string($bynavn);
		$postnr=db_escape_string($postnr);
		$land=db_escape_string($r['land']);
		$betalingsdage=$r['betalingsdage'];
		$betalingsbet=$r['betalingsbet'];
		if ($vis_saet) {
			$betalingsdage=0;
			$betalingsbet='Kontant';
		}
		$cvrnr=db_escape_string($r['cvrnr']);
		$ean=db_escape_string($r['ean']);
		$institution=db_escape_string($r['institution']);
		$email=db_escape_string($r['email']);
		$mail_fakt=$r['mailfakt'];
		$phone=$r['tlf'];
		if ( empty($phone) ) {
			$r_div2=db_fetch_array(db_select("select box5 from grupper WHERE art = 'DIV' and kodenr='2'",__FILE__ . " linje " . __LINE__));
			if ( $r_div2['box5'] === "on" ) $phone=$kontonr;
		}
		if ($r['pbs_nr']>0) {
			$pbs_nr=$r['pbs_nr'];
			$pbs='bs';
		}
		$kontakt=db_escape_string($r['kontakt']);
		$notes=db_escape_string($r['notes']);
		$gruppe=db_escape_string($r['gruppe']);
		$kontoansvarlig=db_escape_string($r['kontoansvarlig']);

		$lev_firmanavn=db_escape_string($r['lev_firmanavn']);
		$lev_addr1=db_escape_string($r['lev_addr1']);
		$lev_addr2=db_escape_string($r['lev_addr2']);
		$lev_postnr=trim($r['lev_postnr']);
		$lev_bynavn = trim($r['lev_bynavn']);
		if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
		$lev_bynavn = db_escape_string($lev_bynavn);
		$lev_postnr=db_escape_string($lev_postnr);
		$lev_land=db_escape_string($r['lev_land']);
		$lev_kontakt=db_escape_string($r['lev_kontakt']);
		$lev_email=db_escape_string($r['lev_email']);

		(findtekst('244|Ordrefelt 1', $sprog_id) == findtekst('255|Ekstrafelt 1', $sprog_id))?$felt_1=db_escape_string($r['felt_1']):$felt_1='';
		(findtekst('245|Ordrefelt 2', $sprog_id) == findtekst('256|Ekstrafelt 2', $sprog_id))?$felt_2=db_escape_string($r['felt_2']):$felt_2='';
		(findtekst('246|Ordrefelt 3', $sprog_id) == findtekst('257|Ekstrafelt 3', $sprog_id))?$felt_3=db_escape_string($r['felt_3']):$felt_3='';
		(findtekst('247|Ordrefelt 4', $sprog_id) == findtekst('258|Ekstrafelt 4', $sprog_id))?$felt_4=db_escape_string($r['felt_4']):$felt_4='';
		(findtekst('248|Ordrefelt 5', $sprog_id) == findtekst('259|Ekstrafelt 5', $sprog_id))?$felt_5=db_escape_string($r['felt_5']):$felt_5='';
	}

	if (!isset ($afd)) $afd = NULL;
	if (!isset ($ansat_navn)) $ansat_navn = NULL;
	if (!$afd && $id) { #20150302+04
		$r = db_fetch_array(db_select("select afd,ref from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$afd=if_isset($r,NULL,'afd');
		if(!$afd) error_log("afd is not set for ordre ");
	}

	if ($kontoansvarlig){
		$query = db_select("select navn,afd from ansatte where id='$kontoansvarlig'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$ansat_navn=$row['navn'];
		if (!$afd) $afd=$row['afd'];
	} else {
		$qtxt = "select ansat_id from brugere where brugernavn = '$brugernavn'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)) && if_isset($r,NULL,'ansat_id')) {
			
				$r = db_fetch_array(db_select("select navn,afd from ansatte where id = '$r[ansat_id]'",__FILE__ . " linje " . __LINE__));
				$ansat_navn=$row['navn'];
				if (!$afd) $afd=$row['afd'];
				
		}else{
			$ansat_navn = $brugernavn; #20250528
			error_log("ansat_id is not set in the database for brugernavn: $brugernavn");
		}
	}

	if ($ansat_navn) $ref=$ansat_navn;

	$afd*=1;
	if ($gruppe){
		$r = db_fetch_array(db_select("select box1,box3,box4,box6,box8,box9 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
		$tmp = (int)substr($r['box1'],1,1);
		$rabatsats=(float)$r['box6'];
		$formularsprog=$r['box4'];
		$valuta=$r['box3'];
		$b2b=$r['box8'];
		($r['box9'])?$omkunde='on':$omkunde='';
		$qtxt="select box2 from grupper where art='SM' and kodenr='$tmp'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$momssats=(float)$r['box2'];
	} elseif ($konto_id) {
		    $alert1= findtekst('1822|Debitoren er ikke tilknyttet en debitorgruppe', $sprog_id); #20210806
			print "<BODY onLoad=\"javascript:alert('$alert1')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?id=$konto_id&returside=../debitor/ordre.php&ordre_id=$id&fokus=$fokus?id=$id\">\n";
			exit;
	}

	if ($id) {
		$r=db_fetch_array(db_select("select konto_id from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		if ($konto_id && $r['konto_id']!=$konto_id) {
      $qtxt = "update ordrer set konto_id='$konto_id',kontonr='$kontonr',kundeordnr='$kundeordnr',";
      $qtxt.= "firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',";
      $qtxt.= "land='$land',kontakt='$kontakt',lev_navn='$lev_navn',lev_addr1='$lev_addr1',";
      $qtxt.= "lev_addr2='$lev_addr2',lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_email='$lev_email',lev_land='$lev_land',";
      $qtxt.= "lev_kontakt='$lev_kontakt',vis_lev_addr='$vis_lev_addr',betalingsdage='$betalingsdage',";
      $qtxt.= "betalingsbet='$betalingsbet',cvrnr='$cvrnr',ean='$ean',momssats='$momssats',";
      $qtxt.= "institution='$institution',email='$email',mail_fakt='$mail_fakt',phone='$phone',";
      $qtxt.= "udskriv_til='$udskriv_til',notes='$notes',tidspkt='$tidspkt',pbs='$pbs',afd='$afd',restordre='0' ";
      $qtxt.= "where id='$id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__); #20140112
      if ($felt_1) db_modify("update ordrer set felt_1='$felt_1'",__FILE__ . " linje " . __LINE__);
      if ($felt_2) db_modify("update ordrer set felt_2='$felt_2'",__FILE__ . " linje " . __LINE__);
      if ($felt_3) db_modify("update ordrer set felt_3='$felt_3'",__FILE__ . " linje " . __LINE__);
      if ($felt_4) db_modify("update ordrer set felt_4='$felt_4'",__FILE__ . " linje " . __LINE__);
      if ($felt_5) db_modify("update ordrer set felt_5='$felt_5'",__FILE__ . " linje " . __LINE__);
		}	
	}
} elseif (strstr($fokus,'lev_') && isset($_GET['konto_id']) && is_numeric($_GET['konto_id']) && $id) { # <- 2011.03.29
	$konto_id=$_GET['konto_id'];
	$q = db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$lev_navn=db_escape_string($r['firmanavn']);
		$lev_addr1=db_escape_string($r['addr1']);
		$lev_addr2=db_escape_string($r['addr2']);
		$lev_postnr=trim($r['postnr']);
		$lev_bynavn = trim($r['bynavn']);
		if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
		$lev_bynavn = db_escape_string($lev_bynavn);
		$lev_postnr=db_escape_string($lev_postnr);
		$lev_kontakt=db_escape_string($r['kontakt']);
    $lev_land=db_escape_string($r['land']);
    db_modify("update ordrer set lev_navn='$lev_navn',lev_addr1='$lev_addr1',lev_addr2='$lev_addr2',lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_kontakt='$lev_kontakt', lev_land='$lev_land' where id=$id",__FILE__ . " linje " . __LINE__);
	}
}
if (!$id && $konto_id && $kontonr) {
	if (!is_numeric($default_procenttillag)) $default_procenttillag=0;
	$r=db_fetch_array(db_select("select max(ordrenr) as ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc",__FILE__ . " linje " . __LINE__));
	$ordrenr=$r['ordrenr']+1;
	if (strlen($phone) > 15) $phone = substr($phone,0,15); 
	$ordredate=date("Y-m-d");
	($lev_firmanavn)?$vis_lev_addr='on':$vis_lev_addr='';
	$qtxt="insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,betalingsdage,betalingsbet,";
	$qtxt.="cvrnr,ean,institution,email,mail_fakt,phone,notes,art,ordredate,momssats,hvem,tidspkt,ref,";
  $qtxt.="valuta,sprog,kontakt,pbs,afd,status,restordre,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_land,lev_email,";
	$qtxt.="lev_kontakt,vis_lev_addr,felt_1,felt_2,felt_3,felt_4,felt_5,procenttillag,omvbet)";
	$qtxt.=" values ";
	$qtxt.="($ordrenr,'$konto_id','$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$betalingsdage','$betalingsbet',";
	$qtxt.="'$cvrnr','$ean','$institution','$email','$mail_fakt','$phone','$notes','DO','$ordredate','$momssats','$brugernavn','$tidspkt','$ref',";
  $qtxt.="'$valuta','$formularsprog','$kontakt','$pbs','$afd','0','0','$lev_firmanavn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_land','$lev_email',";
	$qtxt.="'$lev_kontakt','$vis_lev_addr','$felt_1','$felt_2','$felt_3','$felt_4','$felt_5','$default_procenttillag','$omkunde')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);

	# Porto vare system
	## Create a new order line on creation from the specified sku
	$sku = get_settings_value("porto_varnr", "ordre", null);
	if ($sku != null) {
		$query = db_select("select id from ordrer where ordrenr='$ordrenr' and art='DO' limit 1",__FILE__ . " linje " . __LINE__);
		$ordre = db_fetch_array($query);
		$query = db_select("select * from varer where varenr='$sku' or varenr_alias='$sku' or stregkode='$sku' limit 1",__FILE__ . " linje " . __LINE__);
		$vare = db_fetch_array($query);
		opret_ordrelinje($ordre["id"], $vare["id"], $vare["varenr"], 1, $vare["beskrivelse"], $vare["salgspris"], 0, 0, "DO", "", "", "", "", "", "", "", "", "", "", "", "");
	}

	$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) $id=$row['id'];
} elseif($status<3 && $id && $firmanavn) {
	$query = db_select("select tidspkt,firmanavn from ordrer where id=$id and hvem='$brugernavn'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		if (!$row['firmanavn']) { # <- 2009.05.13 Eller overskrives v. kontaktopslag.
			if (!$restordre) $restordre = 0; # 20201215
			$qtxt = "update ordrer set kontonr='$kontonr',kundeordnr='$kundeordnr',firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',";
			$qtxt.= "postnr='$postnr',bynavn='$bynavn',land='$land',lev_navn='$lev_navn',lev_addr1='$lev_addr1',lev_addr2='$lev_addr2',";
      $qtxt.= "lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_kontakt='$lev_kontakt','$lev_land','$lev_email',vis_lev_addr='$vis_lev_addr',";
			$qtxt.= "felt_1='$felt_1',felt_2='$felt_2',felt_3='$felt_3',felt_4='$felt_4',felt_5='$felt_5',betalingsdage='$betalingsdage',";
			$qtxt.= "betalingsbet='$betalingsbet',cvrnr='$cvrnr',ean='$ean',momssats='$momssats',institution='$institution',email='$email',";
			$qtxt.= "mail_fakt='$mail_fakt',phone='$phone',udskriv_til='$udskriv_til',notes='$notes',hvem = '$brugernavn',tidspkt='$tidspkt',";
			$qtxt.= "pbs='$pbs',afd='$afd',restordre='$restordre' where id='$id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	 	}
	} else {
		$query = db_select("select hvem from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
		$alert = findtekst('1823|Ordren er overtaget af', $sprog_id);
		if ($row = db_fetch_array($query) && $row['hvem']) {print "<BODY onLoad=\"javascript:alert('$alert $row[hvem]')\">\n";}
		elseif ($row['hvem']) {
			$alert1 = findtekst('1824|Du er blevet smidt af', $sprog_id); #20210809
			print "<BODY onLoad=\"javascript:alert('$alert1')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
		}
	}
}
if ($id && $status<3 && isset($_GET['vare_id'])) {
	$vare_id[0]=if_isset($_GET,0,'vare_id');
	$lager[0]=if_isset($_GET,NULL,'lager');
	$query = db_select("select grupper.box6 as box6,ordrer.valuta as valuta,ordrer.ordredate as ordredate,ordrer.status as status from ordrer,adresser,grupper where ordrer.id='$id' and adresser.id=ordrer.konto_id and grupper.art='DG' and ".nr_cast("grupper.kodenr")."=adresser.gruppe",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	if (if_isset($row,NULL,'status')>2) {
		print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">\n";
		exit;
	} else {
		// Check if $vare_id[0] is not an array and can be a valid database ID
		if (!is_array($vare_id[0]) && (is_int($vare_id[0]) || is_numeric($vare_id[0]))) {
			if ($r=db_fetch_array(db_select("select id,varenr,samlevare,salgspris from varer where id = '$vare_id[0]'",__FILE__ . " linje " . __LINE__))) {
				$varenr[0]=$r['varenr'];
				gendan_saet($id);
			}
		}
	}
}
if (isset($_POST['orderNoteText']) && $id) {
	$orderNoteText=$_POST['orderNoteText'];
	$notes=$orderNoteText;
	$qtxt = "update ordrer set notes='". db_escape_string($orderNoteText) ."' where id=$id";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}elseif($id){ 
	$qtxt="select notes from ordrer where id='$id'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$orderNoteText=if_isset($r,NULL,'notes');
}

if (isset($_POST['copy']) && $_POST['copy']) $b_submit = 'Kopier';
elseif (isset($_POST['credit']) && $_POST['credit']) $b_submit = 'Krediter';
elseif (isset($_POST['delete']) && $_POST['delete']) $b_submit = 'Slet';
elseif (isset($_POST['doInvoice']) && $_POST['doInvoice']) $b_submit = 'doInvoice';
elseif (isset($_POST['lookUp']) && $_POST['lookUp']) $b_submit = 'Opslag';
elseif (isset($_POST['print']) && $_POST['print']) $b_submit = 'Udskriv';
elseif (isset($_POST['save']) && $_POST['save']) $b_submit = 'Gem';
elseif (isset($_POST['send']) && $_POST['send']) $b_submit = 'Send';
else $b_submit  = if_isset($_POST,NULL,'b_submit');
if ($b_submit == 'Credit') $b_submit = 'Krediter';
if (($b_submit || isset($_POST['udskriv_til'])) && $id = $_POST['id']) {
	$id = $_POST['id'];
	$sum=if_isset($_POST,NULL,'sum');

	$phone=trim($_POST['phone']);
	$phone=str_replace(' ','',$phone);
	$phone = db_escape_string($phone);

	$email=trim($_POST['email']);
	$email=str_replace(' ','',$email);
	$email = db_escape_string($email);
	if (substr($email, 0, 11 ) == "debitoripad") {
		$q = db_select("select email, firmanavn, phone, addr1, postnr, bynavn from ordrer where id='$_GET[id]'",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q);
		$email = $r["email"];
		$phone = $r["phone"];
		$firmanavn = $r["firmanavn"];
		$addr1 = $r["addr1"];
		$postnr = $r["postnr"];
		$bynavn = $r["bynavn"];
	}

	if (strpos($phone,'@') && !$email) {
		$email = $phone;
		$phone = '';
	} 
	if (strlen($phone) > 15) {
		alert ("".findtekst('1825|telefonummer må maks være på 15 cifre', $sprog_id)."");
		$phone = substr($phone,0,15);
	}
	$udskriv_til=$_POST['udskriv_til'];
	if ($udskriv_til=='localPrint') {
		setcookie('localPrint', 'on', time()+10000000000, '/', 'saldi.dk');
		$localPrint='on';
		$udskriv_til='PDF';
	} else {
		setcookie('localPrint', 'off', time()+10, '/', 'saldi.dk');
		$localPrint=NULL;
	}
	$formularsprog = if_isset($_POST,NULL,'sprog'); # 2022113 Tilføjet 'sprog
	$mail_bilag=if_isset($_POST,NULL,'mail_bilag'); # 20131122 Tilføjet 'mail_bilag'
	$genfakt=if_isset($_POST,NULL,'genfakt');
	if ($genfakt=='') $genfakt='-';
	$ean = db_escape_string(trim($_POST['ean']));
	if (strpos($email,"@") && strpos($email,".") && strlen($email)>5 && $udskriv_til=='email') $mail_fakt = 'on';
	elseif($udskriv_til=='email') {
		$alert = findtekst('1826|e-mail ikke gyldig Faktura kan ikke sendes', $sprog_id);
		print "<BODY onLoad=\"javascript:alert('$alert')\">\n";
		$udskriv_til="PDF";
	}
	if (($udskriv_til=='oioxml' || $udskriv_til=='oioubl') && strlen($ean)!=13) {
		$alert1 = findtekst('1827|EAN-nr. ikke gyldigt Ikke', $sprog_id);
		$alert2 = findtekst('1828|men 13 cifre i alt. Der kan ikke udskrives til', $sprog_id);
		print "<BODY onLoad=\"javascript:alert('$alert1 ".strlen($ean).", $alert2 $udskriv_til.')\">\n";
		$udskriv_til="PDF";
	}
	if ($sum<0 && strstr($udskriv_til,'PBS')) {
	  $udskriv_til='PDF';
	  $pbs='';
	}
	if (substr($udskriv_til,0,3)=='PBS') {
		$udskriv_til='PBS';
		$pbs="BS";
	}
	if ($udskriv_til=='oioubl') $oioubl="on";

	$qtxt = "update ordrer set sprog = '$formularsprog', email='$email',mail_fakt='$mail_fakt',phone='$phone',pbs='$pbs',";
  $qtxt.= "udskriv_til='$udskriv_til',  mail_bilag='$mail_bilag',ean='$ean' where id='$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($genfakt && $genfakt!='-') db_modify("update ordrer set nextfakt='".usdate($genfakt)."' where id='$id'",__FILE__ . " linje " . __LINE__);
	elseif ($genfakt=='-') {
		db_modify("update ordrer set nextfakt=NULL where id='$id'",__FILE__ . " linje " . __LINE__);
		$genfakt=NULL;
	}
}
if (isset($_POST['opdat_mailtext'])) {
	$id = $_POST['id'];
	$mail_subj=db_escape_string(if_isset($_POST['mail_subj']));
	$mail_text=db_escape_string(str_replace("\n","<br>",if_isset($_POST['mail_text'])));
	db_modify("update ordrer set mail_subj='$mail_subj',mail_text='$mail_text' where id='$id'",__FILE__ . " linje " . __LINE__);
}

if (isset($_POST['newAccountNo']) && $newAccountNo = $_POST['newAccountNo']) {
	if (strtolower($newAccountNo=='n')) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?returside=../debitor/ordre.php&ordre_id=$id&fokus=kontonr\">\n";
		exit;
	} elseif (strtolower($newAccountNo=='o')) {
		kontoopslag('DO','firmanavn','kontonr',$id,'','','','','','','','','','','','','');
	} elseif (is_numeric($newAccountNo)) {
    $qtxt = "select id from adresser where art='D' and kontonr='". db_escape_string($newAccountNo) ."' ";
    $qtxt2 = "select id from adresser where art='D' and tlf='". db_escape_string($newAccountNo) ."' ";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
      print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?fokus=kontonr&id=$id&konto_id=$r[id]\">\n";
      exit;
    } elseif ($r=db_fetch_array(db_select($qtxt2,__FILE__ . " linje " . __LINE__))) {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?fokus=kontonr&id=$id&konto_id=$r[id]\">\n";
			exit;
		} else kontoopslag('DO','kontonr','kontonr',$id,$newAccountNo,'','','','','','','','','','','','');
	} elseif($newAccountNo) {
		$x=0;
		$qtxt = "select id from adresser where art='D' ";
		$qtxt.= " and (lower(firmanavn) like '". db_escape_string(strtolower($newAccountNo)) ."')";
		$qtxt.= "or (upper(firmanavn) like '". db_escape_string(strtoupper($newAccountNo)) ."')";
		$qtxt.= "or (firmanavn like '". db_escape_string($newAccountNo) ."')";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$ids[$x]=$r['id'];
			$x++;
		}
		if ($x==1) print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?fokus=kontonr&id=$id&konto_id=$ids[0]\">\n";
		else kontoopslag('DO','firmanavn','firmanavn',$id,'',$newAccountNo,'','','','','','','','','','','');
#    else {
#      print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?";
#      print "returside=../debitor/ordre.php&ordre_id=$id&fokus=kontonr\">\n";
#      exit;
#    }
	}
}



if ($b_submit) {
	$fokus=if_isset($_POST['fokus']);
	if (strstr($b_submit,"Del ordre")) $b_submit="del_ordre";
	$ordrenr = $_POST['ordrenr'];
	$kred_ord_id = $_POST['kred_ord_id'];
	$art = $_POST['art'];
	$kontonr = (int)if_isset($_POST['kontonr'],0);
	$rb = if_isset($_POST,0,'konto_id');
	$konto_id = (int)$rb; #20210719
	if ($id && $kontonr && !$konto_id) { #20150222
		$r=db_fetch_array(db_select("select id from adresser where kontonr = '$kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__));
		if ($konto_id=$r['id']) db_modify("update ordrer set konto_id='$r[id]' where id='$id'",__FILE__ . " linje " . __LINE__);
		else $kontonr='0';
	}
	$firmanavn = isset($_POST['firmanavn']) ? db_escape_string(trim($_POST['firmanavn'])) : '';
	$addr1 = isset($_POST['addr1']) ? db_escape_string(trim($_POST['addr1'])) : '';
	$addr2 = isset($_POST['addr2']) ? db_escape_string(trim($_POST['addr2'])) : '';
	$postnr = isset($_POST['postnr']) ? trim($_POST['postnr']) : '';
	$bynavn = isset($_POST['bynavn']) ? trim($_POST['bynavn']) : '';

	if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
	else $bynavn = db_escape_string($bynavn);
	$postnr = db_escape_string($postnr);
	$land = db_escape_string(trim($_POST['land']));
	$email = db_escape_string(trim($_POST['email']));
	$phone=if_isset($_POST['phone']);

	if (strpos($phone,'@') && !$email) {
		$email = $phone;
		$phone = '';
	} 
	if (strlen($phone) > 15) {
		alert ("".findtekst(1825, $sprog_id)."");
		$phone = substr($phone,0,15);
	}
	if (substr($email, 0, 11 ) == "debitoripad") {
		$q = db_select("select email, firmanavn, phone, addr1, postnr, bynavn from ordrer where id='$_GET[id]'",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q);
		$email = $r["email"];
		$phone = $r["phone"];
		$firmanavn = $r["firmanavn"];
		$addr1 = $r["addr1"];
		$postnr = $r["postnr"];
		$bynavn = $r["bynavn"];
	}

	$ean = isset($_POST['ean']) ? db_escape_string(trim($_POST['ean'])) : ''; // Default value if not set
	$kontakt = isset($_POST['kontakt']) ? db_escape_string(trim($_POST['kontakt'])) : '';
	$kontakt_tlf = isset($_POST['kontakt_tlf']) ? db_escape_string(trim($_POST['kontakt_tlf'])) : '';
	$kundeordnr = isset($_POST['kundeordnr']) ? db_escape_string(trim($_POST['kundeordnr'])) : '';
	$lev_navn   = db_escape_string(trim(if_isset($_POST, NULL, 'lev_navn')));
	$lev_addr1  = db_escape_string(trim(if_isset($_POST, NULL, 'lev_addr1')));
	$lev_addr2  = db_escape_string(trim(if_isset($_POST, NULL, 'lev_addr2')));
	$lev_postnr = trim(if_isset($_POST, NULL, 'lev_postnr'));
	$lev_bynavn = trim(if_isset($_POST, NULL, 'lev_bynavn'));
	$lev_email  = db_escape_string(trim(if_isset($_POST, NULL, 'lev_email')));
	$lev_land   = db_escape_string(trim(if_isset($_POST, NULL, 'lev_land')));



	if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
	else $lev_bynavn = db_escape_string($lev_bynavn);
	$lev_kontakt = if_isset($_POST,NULL,'lev_kontakt')? db_escape_string(trim($_POST['lev_kontakt'])) : '';
	$vis_lev_addr=if_isset($_POST,NULL,'vis_lev_addr');
	update_settings_value("vis_lev_addr", "ordrer", $vis_lev_addr, "If the adress field should be showen as standard value", $bruger_id);

	$felt_1 = db_escape_string(trim($_POST['felt_1']));
	$felt_2 = db_escape_string(trim($_POST['felt_2']));
	$felt_3 = db_escape_string(trim($_POST['felt_3']));
	$felt_4 = db_escape_string(trim($_POST['felt_4']));
	$felt_5 = db_escape_string(trim(if_isset($_POST['felt_5'])));
	if ($vis_saet) {
		$felt_2=usdecimal($felt_2,2);
		$felt_4=usdecimal($felt_4,2);
		$felt_5=db_escape_string(trim(if_isset($_POST['kasse'])));
	}
	$ordredate = usdate(if_isset($_POST['ordredato']));
	$levdato = trim(if_isset($_POST,NULL,'levdato'));
#  $genfakt = trim(if_isset($_POST['genfakt']));
	$fakturadato    = trim(if_isset($_POST, NULL, 'fakturadato'));
	$cvrnr          = db_escape_string(trim(if_isset($_POST, NULL, 'cvrnr')));
	$procenttillag  = usdecimal($procenttillag, 2); 
	$institution    = db_escape_string(trim(if_isset($_POST, NULL, 'institution')));
	$moms           = if_isset($_POST, NULL, 'moms') * 1;
	$betalingsbet   = if_isset($_POST, NULL, 'betalingsbet');
	$betalingsdage  = if_isset($_POST, NULL, 'betalingsdage') * 1;
	$valuta         = if_isset($_POST, NULL, 'valuta');
	$ny_valuta      = if_isset($_POST, NULL, 'ny_valuta');
	$projekt        = if_isset($_POST, NULL, 'projekt');
	if (!isset($projekt[0])) $projekt[0]=0;
	$formularsprog   = if_isset($_POST, NULL, 'sprog');
	$lev_adr         = trim(if_isset($_POST, NULL, 'lev_adr'));
	$sum             = if_isset($_POST, NULL, 'sum');
	$linjeantal      = if_isset($_POST, NULL, 'linjeantal');
	$linje_id        = if_isset($_POST, NULL, 'linje_id');
	$kred_linje_id   = if_isset($_POST, NULL, 'kred_linje_id');
	$posnr           = if_isset($_POST, NULL, 'posnr');
	$sag_id          = if_isset($_POST, NULL, 'sag_id');
	$sagsnr          = if_isset($_POST, NULL, 'sagsnr');
	$tilbudnr        = if_isset($_POST, NULL, 'tilbudnr');
	$datotid         = if_isset($_POST, NULL, 'datotid');
	$nr              = if_isset($_POST, NULL, 'nr');

	$returside = if_isset($_POST,NULL,'returside');
	if ($status<3) $status = if_isset($_POST,NULL,'status');
	$godkend = if_isset($_POST,NULL,'godkend');
	$restordre = if_isset($_POST,NULL,'restordre');
	($restordre)? $restordre="1":$restordre="0";
	$omdan_t_fakt = if_isset($_POST, NULL, 'omdan_t_fakt');
	$kreditnota   = if_isset($_POST, NULL, 'kreditnota');
	$ref          = trim(if_isset($_POST, NULL, 'ref'));
	$extAfd       = if_isset($_POST, NULL, 'extAfd');
	$afd          = if_isset($_POST, NULL, 'afd');
	$afd_lager    = if_isset($_POST, NULL, 'afd_lager');
	$fakturanr    = trim(if_isset($_POST, NULL, 'fakturanr'));
	$momssats = usdecimal($_POST['momssats'],2);
	$procenttillag = usdecimal(if_isset($_POST,0,'procenttillag'),2);
	$mail_subj = db_escape_string(trim(if_isset($_POST,NULL,'mail_subj')));
	$mail_text=db_escape_string(str_replace("\n","<br>",if_isset($_POST,NULL,'mail_text')));
	$enhed         = if_isset($_POST, NULL, 'enhed');
	$vare_id       = if_isset($_POST, NULL, 'vare_id');
	$antal         = if_isset($_POST, NULL, 'antal');
	$serienr       = if_isset($_POST, NULL, 'serienr');
	$samlevare     = if_isset($_POST, NULL, 'samlevare');
	$folgevare     = if_isset($_POST, NULL, 'folgevare');
	$momsfri       = if_isset($_POST, NULL, 'momsfri');
	$tidl_lev      = if_isset($_POST, NULL, 'tidl_lev');
	$kdo           = if_isset($_POST, NULL, 'kdo');
	$rabatart      = if_isset($_POST, NULL, 'rabatart');
	$varemomssats  = if_isset($_POST, NULL, 'varemomssats');
	$omkunde       = if_isset($_POST, NULL, 'omkunde');
	$omvbet        = if_isset($_POST, NULL, 'omvbet');
	$lev_varenr    = if_isset($_POST, NULL, 'lev_varenr');
	$kostpris      = if_isset($_POST, NULL, 'kostpris');
	$saet          = if_isset($_POST, NULL, 'saet');
	$fast_db       = if_isset($_POST, 0, 'fast_db');
	$samlet_pris   = if_isset($_POST, NULL, 'samlet_pris');

	if ($samlet_pris!='-') $samlet_pris=usdecimal($samlet_pris,2); #20150317
	$bruttosum = if_isset($_POST, NULL, 'bruttosum');
	$ordresum  = if_isset($_POST, NULL, 'ordresum');
	$lager     = if_isset($_POST, NULL, 'lager');

	
  if ($extAfd && $afd && $extAfd != $afd) {
    $qtxt = "select var_value from settings where var_name = 'maxDepVatRate_". $afd ."'";
    if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
      db_modify("update ordrelinjer set vare_id = '0' where vare_id is NULL and ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
      $qtxt = "select box1,box2 from grupper where fiscal_year = '$regnaar' and kodenr = '$afd' and art = 'SM'";
      if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
        $momssats = $r['box2'];
        $qtxt = "update ordrer set momssats='$momssats' where id='$id'";
        db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        $qtxt = "update ordrelinjer set momssats='$momssats', vat_account = '$r[box1]' where vare_id != '0' and ordre_id = '$id'";
        db_modify($qtxt,__FILE__ . " linje " . __LINE__);
      }
    }
  }
	if (!isset($momsfri[0])) $momsfri[0]='';
	if (strstr($b_submit,"Kred") && $status < 3) $b_submit="doInvoice";
	if (strstr($b_submit,'Modtag')) $b_submit="Lever";
	if ($art=='PO' && $status<3) {
		$art='DO';
		db_modify("update ordrer set art='DO' where id='$id'",__FILE__ . " linje " . __LINE__);
	}
	if (($godkend == "on") && ($status==0) && ($art=='DO' || $art == 'DK') && $sag_id) { # Kopi af original Tilbud. 20140716
		$r=db_fetch_array(db_select("select tilbudnr from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		//cho "tilbudnr: $r[tilbudnr]"; exit();
		
		$x=0;
		$q = db_select("select art from ordrer where tilbudnr = '$r[tilbudnr]'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$art_tjk[$x]=$r['art'];
			$x++;
		}
		//print_r ($art_tjk); exit();
		if (!$konto_id) { #20150302
      if ($incl_moms) {
        $qtxt = "grupper.box1 from grupper,adresser where";
        $qtxt =  " grupper.kodenr = adresser.gruppe and ";
        $qtxt =  " grupper.art = DG";
	      $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
        $mKode = substr($r['box1'],1);
        $qtxt = "select box2 from grupper where art = 'SM' and kodenr = '$mKode'";
        $qtxt = "order by box2 desc limit 1";
        $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
        $momssats = $r['box2'];
      }
			$status=0;
			$godkend=NULL;
		}
		
		if (!in_array("OT", $art_tjk)) {
		
		
			$r=db_fetch_array(db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		
			$qtxt="insert into ordrer (konto_id,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,kontakt_tlf,email,mail_fakt,phone,udskriv_til,kundeordnr,";
      $qtxt.="lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_land,lev_email,lev_kontakt,lev_email,lev_land,ean,institution,betalingsbet,betalingsdage,kontonr,cvrnr,art,";
			$qtxt.="valuta,valutakurs,sprog,projekt,ordredate,";
			if ($r['levdate']) $qtxt.="levdate,";
			if ($r['fakturadate']) $qtxt.="fakturadate,";
			$qtxt.="notes,ordrenr,sum,momssats,status,ref,fakturanr,";
			if ($r['modtagelse']) $qtxt.="modtagelse,"; 
			if ($r['kred_ord_id']) $qtxt.="kred_ord_id,"; 
			$qtxt.="lev_adr,kostpris,moms,hvem,tidspkt,betalt,";
			if ($r['nextfakt']) $qtxt.="nextfakt,"; 
			$qtxt.="pbs,afd,mail,mail_cc,mail_bcc,mail_subj,mail_text,";
			$qtxt.="felt_1,felt_2,felt_3,felt_4,felt_5,vis_lev_addr,restordre,sag_id,tilbudnr,datotid,nr,returside,sagsnr,betalings_id,mail_bilag,";
			$qtxt.="dokument,procenttillag) values ";
			$qtxt.="(";
			$qtxt.="'".db_escape_string($r['konto_id'])."','".db_escape_string($r['firmanavn'])."','".db_escape_string($r['addr1'])."',";
			$qtxt.="'".db_escape_string($r['addr2'])."','".db_escape_string($r['postnr'])."','".db_escape_string($r['bynavn'])."',";
			$qtxt.="'".db_escape_string($r['land'])."','".db_escape_string($r['kontakt'])."','".db_escape_string($r['kontakt_tlf'])."',";
			$qtxt.="'".db_escape_string($r['email'])."','".db_escape_string($r['mail_fakt'])."',";
			$qtxt.="'".db_escape_string($r['phone'])."','".db_escape_string($r['udskriv_til'])."',";
			$qtxt.="'".db_escape_string($r['kundeordnr'])."','".db_escape_string($r['lev_navn'])."','".db_escape_string($r['lev_addr1'])."',";
			$qtxt.="'".db_escape_string($r['lev_addr2'])."','".db_escape_string($r['lev_postnr'])."','".db_escape_string($r['lev_bynavn'])."',";
      $qtxt.="'".db_escape_string($r['lev_land'])."','".db_escape_string($r['lev_email'])."',";
      $qtxt.="'".db_escape_string($r['lev_kontakt'])."','".db_escape_string($r['lev_email'])."','".db_escape_string($r['lev_land'])."',";
      $qtxt.="'".db_escape_string($r['ean'])."','".db_escape_string($r['institution'])."',";
			$qtxt.="'".db_escape_string($r['betalingsbet'])."','".db_escape_string($r['betalingsdage'])."','".db_escape_string($r['kontonr'])."',";
			$qtxt.="'".db_escape_string($r['cvrnr'])."','OT','".db_escape_string($r['valuta'])."',";
			($r['valutakurs'])?$qtxt.="'".db_escape_string($r['valutakurs'])."',":$qtxt.="'100',";
			$qtxt.="'".db_escape_string($r['sprog'])."','".db_escape_string($r['projekt'])."','".db_escape_string($r['ordredate'])."',";
			if ($r['levdate']) $qtxt.="'".db_escape_string($r['levdate'])."',";
			if ($r['fakturadate']) $qtxt.="'".db_escape_string($r['fakturadate'])."',";
			$qtxt.="'".db_escape_string($r['notes'])."','".db_escape_string($r['ordrenr'])."',";
			$qtxt.="'".db_escape_string($r['sum'])."','".db_escape_string($r['momssats'])."','0','".db_escape_string($r['ref'])."',";
			$qtxt.="'".db_escape_string($r['fakturanr'])."',";
			if ($r['modtagelse'])  $qtxt.="'".db_escape_string($r['modtagelse'])."',";
			if ($r['kred_ord_id']) $qtxt.="'".db_escape_string($r['kred_ord_id'])."',";
			$qtxt.="'".db_escape_string($r['lev_adr'])."',";
			$qtxt.="'".db_escape_string($r['kostpris'])."','".db_escape_string($r['moms'])."','".db_escape_string($r['hvem'])."',";
			$qtxt.="'".db_escape_string($r['tidspkt'])."','".db_escape_string($r['betalt'])."',";
			if ($r['nextfakt']) $qtxt.="'".db_escape_string($r['nextfakt'])."',";
			$qtxt.="'".db_escape_string($r['pbs'])."','".db_escape_string($r['afd']*1)."',";
			$qtxt.="'".db_escape_string($r['mail'])."','".db_escape_string($r['mail_cc'])."','".db_escape_string($r['mail_bcc'])."',";
			$qtxt.="'".db_escape_string($r['mail_subj'])."','".db_escape_string($r['mail_text'])."','".db_escape_string($r['felt_1'])."',";
			$qtxt.="'".db_escape_string($r['felt_2'])."','".db_escape_string($r['felt_3'])."','".db_escape_string($r['felt_4'])."',";
			$qtxt.="'".db_escape_string($r['felt_5'])."','".db_escape_string($r['vis_lev_addr'])."','".db_escape_string($r['restordre'])."',";
			$qtxt.="'".db_escape_string($r['sag_id'])."','".db_escape_string($r['tilbudnr'])."','".db_escape_string($r['datotid'])."',";
			$qtxt.="'".db_escape_string($r['nr'])."','".db_escape_string($r['returside'])."','".db_escape_string($r['sagsnr'])."',";
			$qtxt.="'".db_escape_string($r['betalings_id'])."','".db_escape_string($r['mail_bilag'])."','".db_escape_string($r['dokument'])."',";
			$qtxt.="'".db_escape_string($r['procenttillag'])."')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		
			$r=db_fetch_array(db_select("select max(id) as id from ordrer where sag_id = '$sag_id'",__FILE__ . " linje " . __LINE__));
			$ordre_id=$r['id'];
			
			$x=0;
			$q=db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$x++;
				$ordrelinje_id[$x]=$r['id'];
				$varenr_OT[$x]=db_escape_string($r['varenr']);
				$beskrivelse_OT[$x]=db_escape_string(trim($r['beskrivelse']));
				$enhed_OT[$x]=db_escape_string($r['enhed']);
				$posnr_OT[$x]=$r['posnr'];
				$pris_OT[$x]=$r['pris'];
				$rabat_OT[$x]=$r['rabat'];
				$lev_varenr_OT[$x]=db_escape_string($r['lev_varenr']);
				//$ordre_id[$x]=$r['ordre_id'];
				$serienr_OT[$x]=db_escape_string($r['serienr']);
				$vare_id_OT[$x]=trim($r['vare_id']);
				$antal_OT[$x]=$r['antal'];
				$leveres_OT[$x]=$r['leveres'];
				$leveret_OT[$x]=$r['leveret'];
				$bogf_konto_OT[$x]=$r['bogf_konto'];
				$oprettet_af_OT[$x]=db_escape_string(trim($r['oprettet_af']));
				$bogfort_af_OT[$x]=db_escape_string(trim($r['bogfort_af']));
				$hvem_OT[$x]=db_escape_string($r['hvem']);
				$tidspkt_OT[$x]=db_escape_string($r['tidspkt']);
				$kred_linje_id_OT[$x]=$r['kred_linje_id'];
				$momsfri_OT[$x]=$r['momsfri'];
				$momssats_OT[$x]=$r['momssats'];
				$kostpris_OT[$x]=$r['kostpris'];
				$samlevare_OT[$x]=$r['samlevare'];
				$projekt_OT[$x]=db_escape_string($r['projekt']);
				$m_rabat_OT[$x]=$r['m_rabat'];
				$rabatgruppe_OT[$x]=$r['rabatgruppe'];
				$folgevare_OT[$x]=$r['folgevare'];
				$kdo_OT[$x]=$r['kdo'];
				$rabatart_OT[$x]=$r['rabatart'];
				$variant_id_OT[$x]=db_escape_string($r['variant_id']);
				$procent_OT[$x]=$r['procent'];
			}
			$ordrelinjeantal=$x;
			
			for ($x=1;$x<=$ordrelinjeantal;$x++) {
				if ($ordrelinje_id[$x]) {
					//print_r($ordrelinje_id);exit();
					if ($pris_OT[$x] != NULL) {
						$pris_tbl[$x] = "pris,";
						$pris_value[$x] = "'$pris_OT[$x]',";
					} else {
						$pris_tbl[$x] = NULL;
						$pris_value[$x] = NULL;
					}
					if ($rabat_OT[$x] != NULL) {
						$rabat_tbl[$x] = "rabat,";
						$rabat_value[$x] = "'$rabat_OT[$x]',";
					} else {
						$rabat_tbl[$x] = NULL;
						$rabat_value[$x] = NULL;
					}
					if ($vare_id_OT[$x] != NULL) {
						$vare_id_tbl[$x] = "vare_id,";
						$vare_id_value[$x] = "'$vare_id_OT[$x]',";
					} else {
						$vare_id_tbl[$x] = NULL;
						$vare_id_value[$x] = NULL;
					}
					if ($antal_OT[$x] != NULL) {
						$antal_tbl[$x] = "antal,";
						$antal_value[$x] = "'$antal_OT[$x]',";
					} else {
						$antal_tbl[$x] = NULL;
						$antal_value[$x] = NULL;
					}
					if ($leveres_OT[$x] != NULL) {
						$leveres_tbl[$x] = "leveres,";
						$leveres_value[$x] = "'$leveres_OT[$x]',";
					} else {
						$leveres_tbl[$x] = NULL;
						$leveres_value[$x] = NULL;
					}
					if ($leveret_OT[$x] != NULL) {
						$leveret_tbl[$x] = "leveret,";
						$leveret_value[$x] = "'$leveret_OT[$x]',";
					} else {
						$leveret_tbl[$x] = NULL;
						$leveret_value[$x] = NULL;
					}
					if ($bogf_konto_OT[$x] != NULL) {
						$bogf_konto_tbl[$x] = "bogf_konto,";
						$bogf_konto_value[$x] = "'$bogf_konto_OT[$x]',";
					} else {
						$bogf_konto_tbl[$x] = NULL;
						$bogf_konto_value[$x] = NULL;
					}
					if ($kred_linje_id_OT[$x] != NULL) {
						$kred_linje_id_tbl[$x] = "kred_linje_id,";
						$kred_linje_id_value[$x] = "'$kred_linje_id_OT[$x]',";
					} else {
						$kred_linje_id_tbl[$x] = NULL;
						$kred_linje_id_value[$x] = NULL;
					}
					if ($momssats_OT[$x] != NULL) {
						$momssats_tbl[$x] = "momssats,";
						$momssats_value[$x] = "'$momssats_OT[$x]',";
					} else {
						$momssats_tbl[$x] = NULL;
						$momssats_value[$x] = NULL;
					}
					if ($kostpris_OT[$x] != NULL) {
						$kostpris_tbl[$x] = "kostpris,";
						$kostpris_value[$x] = "'$kostpris_OT[$x]',";
					} else {
						$kostpris_tbl[$x] = NULL;
						$kostpris_value[$x] = NULL;
					}
					if ($m_rabat_OT[$x] != NULL) {
						$m_rabat_tbl[$x] = "m_rabat,";
						$m_rabat_value[$x] = "'$m_rabat_OT[$x]',";
					} else {
						$m_rabat_tbl[$x] = NULL;
						$m_rabat_value[$x] = NULL;
					}
					if ($rabatgruppe_OT[$x] != NULL) {
						$rabatgruppe_tbl[$x] = "rabatgruppe,";
						$rabatgruppe_value[$x] = "'$rabatgruppe_OT[$x]',";
					} else {
						$rabatgruppe_tbl[$x] = NULL;
						$rabatgruppe_value[$x] = NULL;
					}
					if ($folgevare_OT[$x] != NULL) {
						$folgevare_tbl[$x] = "folgevare,";
						$folgevare_value[$x] = "'$folgevare_OT[$x]',";
					} else {
						$folgevare_tbl[$x] = NULL;
						$folgevare_value[$x] = NULL;
					}
						db_modify("insert into ordrelinjer (varenr,beskrivelse,enhed,posnr,$pris_tbl[$x] $rabat_tbl[$x] lev_varenr,ordre_id,serienr,$vare_id_tbl[$x] $antal_tbl[$x] $leveres_tbl[$x] $leveret_tbl[$x] $bogf_konto_tbl[$x] oprettet_af,bogfort_af,hvem,tidspkt,$kred_linje_id_tbl[$x] momsfri,$momssats_tbl[$x] $kostpris_tbl[$x] samlevare,projekt,$m_rabat_tbl[$x] $rabatgruppe_tbl[$x] $folgevare_tbl[$x] kdo,rabatart,variant_id,procent) values ('$varenr_OT[$x]','$beskrivelse_OT[$x]','$enhed_OT[$x]','$posnr_OT[$x]',$pris_value[$x] $rabat_value[$x] '$lev_varenr_OT[$x]','$ordre_id','$serienr_OT[$x]',$vare_id_value[$x] $antal_value[$x] $leveres_value[$x] $leveret_value[$x] $bogf_konto_value[$x] '$oprettet_af_OT[$x]','$bogfort_af_OT[$x]','$hvem_OT[$x]','$tidspkt_OT[$x]',$kred_linje_id_value[$x] '$momsfri_OT[$x]',$momssats_value[$x] $kostpris_value[$x] '$samlevare_OT[$x]','$projekt_OT[$x]',$m_rabat_value[$x] $rabatgruppe_value[$x] $folgevare_value[$x] '$kdo_OT[$x]','$rabatart_OT[$x]','$variant_id_OT[$x]','$procent_OT[$x]')",__FILE__ . " linje " . __LINE__);
						//db_modify("insert into ordrelinjer (varenr,beskrivelse,enhed,posnr,pris,rabat,lev_varenr,ordre_id,serienr,vare_id,antal,leveres,leveret,bogf_konto,oprettet_af,bogfort_af,hvem,tidspkt,kred_linje_id,momsfri,momssats,kostpris,samlevare,projekt,m_rabat,rabatgruppe,folgevare,kdo,rabatart,variant_id,procent) values ('$varenr[$x]','$beskrivelse[$x]','$enhed[$x]','$posnr[$x]','$pris[$x]','$rabat[$x]','$lev_varenr[$x]','$ordre_id','$serienr[$x]','$vare_id[$x]','$antal[$x]','$leveres[$x]','$leveret[$x]','$bogf_konto[$x]','$oprettet_af[$x]','$bogfort_af[$x]','$hvem[$x]','$tidspkt[$x]','$kred_linje_id[$x]','$momsfri[$x]','$momssats[$x]','$kostpris[$x]','$samlevare[$x]','$projekt[$x]','$m_rabat[$x]','$rabatgruppe[$x]','$folgevare[$x]','$kdo[$x]','$rabatart[$x]','$variant_id[$x]','$procent[$x]')",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	for ($x=0; $x<=$linjeantal;$x++) {
		if (!isset($antal[$x]))        $antal[$x]   = 0;
		if (!isset($tidl_lev[$x]))     $tidl_lev[$x]= 0;
		if (!isset($omvbet[$x]))       $omvbet[$x]= NULL;
		if (!isset($varemomssats[$x])) $varemomssats[$x]= 0;
		
		$antaldiff[$x]    = 0;
		$varemomssats[$x] = (float)$varemomssats[$x];

		$y="posn".$x;
/* removed 20211021
		#$posnr_ny[$x]=trim(if_isset($_POST[$y])); 
		$rb = trim(if_isset($_POST[$y]));
		$posnr_ny[$x] = (int)$rb; #20210719
*/
		$posnr_ny[$x]=trim(if_isset($_POST[$y],0));
		if ($posnr_ny[$x]!="-" && $posnr_ny[$x]!="->" && $posnr_ny[$x]!="<-" && !strpos($posnr_ny[$x],'+')) {
			if ($posnr_ny[$x]=='0') $posnr_ny[$x]="0,01";
			$posnr_ny[$x]=afrund((100*str_replace(",",".",$posnr_ny[$x])),0);
#      if ($x==0 && $posnr_ny[$x]) $posnr_ny[$x]*=100;  
		}
		$y="vare".$x;
		$varenr[$x]=db_escape_string(trim(if_isset($_POST[$y])));
#    if (!$x && !$varenr[$x])$y="vare_".$x;
#    $varenr[$x]=db_escape_string(trim(if_isset($_POST[$y])));
		$y="dkan".$x;
		$dkantal[$x]=trim(isset($_POST[$y]) ? $_POST[$y] : 0);
		if ($x==0 && $dkantal[$x] == '') $antal[$x]=1; #20160913
		if ($dkantal[$x] || $dkantal[$x]=='0'){
			if ( strstr($dkantal[$x], ":") ) $dkantal[$x]=tid2decimal($dkantal[$x], "t");
			$tmp=usdecimal($dkantal[$x],2);
			$antaldiff[$x]=$tmp-$antal[$x];
			$antal[$x]=usdecimal($dkantal[$x],2);
			if ($art=='DK') $antal[$x]=$antal[$x]*-1;
			elseif (($tidl_lev[$x]<0) && ($tidl_lev[$x] < $antal[$x])) $antal[$x]=$tidl_lev[$x];
		} elseif(!$varenr[$x]) $vare_id[$x]=0;
		$y="lagr".$x;
		if (isset($_POST[$y]) && strlen($_POST[$y])) $lager[$x]=$_POST[$y];
		elseif(!isset($lager[$x])) $lager[$x]=NULL;
		if (!$lager[$x] && $afd_lager) $lager[$x]=$afd_lager;
		if (!is_numeric($lager[$x])) {
			for ($l=0;$l<count($lagernr);$l++) {
				if (strtolower($lager[$x])==strtolower($lagernavn[$l])) $lager[$x]=$lagernr[$l];
			}
		}
 		$lager[$x] = (int)$lager[$x];
		$y="leve".$x;
		if ($hurtigfakt=='on') $leveres[$x]=$antal[$x];
		else {
			$leveres[$x]=trim(if_isset($_POST[$y]));
			if ($leveres[$x]){
			$leveres[$x]=usdecimal($leveres[$x],2);
			if ($art=='DK') {$leveres[$x]=$leveres[$x]*-1;}
			}
		}
		$y="beskrivelse".$x;
		$beskrivelse[$x]=db_escape_string(trim(if_isset($_POST[$y])));
		$beskrivelse[$x]=str_replace(chr(9),' ',$beskrivelse[$x]);
		$y="pris".$x;
		if ($x!=0||(isset($_POST[$y]) && strlen($_POST[$y]))) {
			if(strpos($_POST[$y],"(") && strpos($_POST[$y],")")) {
				list($pris[$x],$kp)=explode("(",$_POST[$y]);
				$pris[$x]=usdecimal($pris[$x],2);
				$kp=str_replace(")","",$kp);
				if ($kp=="!") {
					if ($vare_id[$x]) { #20170906
						$r=db_fetch_array(db_select("select kostpris from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
						$kostpris[$x]=$r['kostpris']; # *100/$valutakurs;
					}
				} else $kostpris[$x]=usdecimal($kp,2);
#        if ($kostpris[$x] && $linje_id[$x]) {
#          db_modify("update ordrelinjer set kostpris='$kostpris[$x]' where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
#        }
			} else $pris[$x]=usdecimal($_POST[$y],2);
			if ($incl_moms && !$momsfri[$x] && !$omvbet[$x]) {
				$pris[$x]=afrund(($pris[$x]/(100+$varemomssats[$x])*100),3);
			}
		}
		$y="raba".$x;
		$rabat[$x]=usdecimal(if_isset($_POST[$y]),5);
		if (($x>0)&&(!$rabat[$x]))$rabat=0;
		$y="proc".$x;
		$procent[$x]=usdecimal(if_isset($_POST[$y]),2);
		if (($x>0)&&(!$procent[$x]))$procent[$x]=100;
		$y="ialt".$x;
		$ialt[$x]=if_isset($_POST,NULL,$y);
		if (($godkend == "on")&&($status==0)) {
			if ($vis_saet) $fakturadato=date("d-m-Y");
			$leveres[$x]=$antal[$x];
			if (isset($linje_id[$x]) && $varenr[$x]) batch($linje_id[$x]);
		}
		if (!$sletslut && $posnr_ny[$x]=='->') $sletstart=$x;
		if ($sletstart && $posnr_ny[$x]=='<-') $sletslut=$x;
	}
	if (($sletstart)&&($sletslut)&&($sletstart<$sletslut)) {
		for ($x=$sletstart; $x<=$sletslut; $x++) $posnr_ny[$x]="-";
	}
}
if ($status<3 && $b_submit) {
	$rabatsats=0;
	if(isset($kontonr)){
		$qtxt = "select gruppe from adresser,grupper where kontonr='$kontonr' ";
		$qtxt.= "and adresser.art='D'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if(isset($r['gruppe'])){
			$grp = (int)$r['gruppe'];
			$qtxt = "select box6 from grupper where art='DG' and kodenr='$grp'";
			($kontonr && $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$rabatsats=$r['box6']:$rabatsats==0;
		}	
	}else{
		print "<BODY onLoad=\"javascript:alert('Kontonr must not be empty')\">\n";
		// Reload the current page.
		header("Refresh:0");
		exit;
	}
	if (strstr($b_submit,'Slet')) {
		slet_ordre($id);
		if ($sag_id) { #20140507-2
			header("location:../sager/sager.php?funktion=vis_sag&sag_id=$sag_id");
		} else {
			if (!$returside) $returside=if_isset($_GET['returside']);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
			exit;
		}
	}
	if ($b_submit == 'doInvoice' && $vis_saet) {  #20190521
		if ($felt_1 =='Konto' && ($betalingsbet == 'Kreditkort' || $betalingsbet == 'Kontant')) $b_submit='Gem';
		$qtxt="select moms from ordrer where id='$id'";
		$r= db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if (afrund($sum + $r['moms'] - ($felt_2 + $felt_4),2)) $b_submit='Gem';
 	}
	if ($id && $ny_valuta!=$valuta && $status<3) {
    if ($ny_valuta!=$baseCurrency) {
			if ($r= db_fetch_array(db_select("select valuta.kurs from valuta,grupper where grupper.art='VK' and grupper.box1='$ny_valuta' and valuta.gruppe=".nr_cast("grupper.kodenr")." and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
				$valutakurs=$r['kurs']*1;
				if ($status<3) db_modify("update ordrer set valuta='$ny_valuta',valutakurs='$valutakurs' where id='$id'",__FILE__ . " linje " . __LINE__);
			} else {
				$tmp = dkdato($ordredate);
				$alert = findtekst('1694|Der er ikke nogen valutakurs for', $sprog_id);
				print "<BODY onLoad=\"javascript:alert('$alert $valuta den $ordredate')\">\n";
			}
		} else {
			$valutakurs = 100;
			db_modify("update ordrer set valuta='$ny_valuta',valutakurs='$valutakurs' where id='$id'",__FILE__ . " linje " . __LINE__);
		}
		$valuta=$ny_valuta;
	}
	transaktion("begin");
	if ($levdato) $levdate=usdate($levdato);
	if ($fakturadato) $fakturadate=usdate($fakturadato);
#  if (($konto_id)&&(!$ref)&&($status<3)) {
#    print "<BODY onLoad=\"javascript:alert('Vor ref. SKAL udfyldes')\">\n";
#  }
	$bogfor=1;
	if ($godkend == "on"||$omdan_t_fakt == "on"||($status==0&&$hurtigfakt=="on")) $status++;
	if ($status==1) {
		if ($levdato) $levdate=usdate($levdato);
		if (!$levdate) {
			if ($hurtigfakt!='on') {
 				$alert = findtekst('1829|Leveringsdato sat til dags dato.', $sprog_id);
				print "<BODY onLoad=\"javascript:alert('$alert')\">\n";
				$levdate=date("Y-m-d");
			} else $levdate=$ordredate;;
		}
		elseif ($levdate<$ordredate) {
			$alert1 = findtekst('1679|Leveringsdato er før ordredato', $sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alert1')\">\n";
			$status=0;
		}
	}
	if (strstr($b_submit,"Kred")) {
		$art='DK';
		$query = db_select("select id from ordrer where kred_ord_id = $id",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$row[id]\">\n";
			exit;
		} elseif ($kred_ord_id) {
			$id='';
			$status=0;	
		} else {
			$kred_ord_id=$id;
			$id='';
			$status=0;
		}
	} elseif (strstr($b_submit,"Kopi")){
		$gl_id=$id;
		$id='';
		$status=0;
  }  elseif (!$art) $art='DO';
	if (strlen($ordredate)<6) $ordredate=date("Y-m-d");
	if (($kontonr&&!$firmanavn)||($kontonr&&$gl_id)) {
		$query = db_select("select * from adresser where kontonr = '$kontonr' and art='D'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			if ($row['lukket'] == 'on') {
				$alert1 = findtekst('1830|er lukket', $sprog_id);
				$alert2 = findtekst('440|Konto', $sprog_id);
				alert("$alert2 $kontonr $alert1");
				print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">\n";
				exit;
			}
			$konto_id=$row['id'];
			$firmanavn=db_escape_string($row['firmanavn']);
			$addr1=db_escape_string($row['addr1']);
			$addr2=db_escape_string($row['addr2']);
			$postnr=db_escape_string($row['postnr']);
			$bynavn=db_escape_string($row['bynavn']);
			$land=db_escape_string($row['land']);
			$kontakt=db_escape_string($row['kontakt']);
			$betalingsdage=$row['betalingsdage'];
			$betalingsbet=$row['betalingsbet'];
			$cvrnr=$row['cvrnr'];
			$notes=db_escape_string($row['notes']);
			if (empty($phone)) $phone=$row['tlf']; //added by CA for DFM consignment 20210219 dfm_
			if (empty($phone)) {
				$r_div2=db_fetch_array(db_select("select box5 from grupper WHERE art = 'DIV' and kodenr='2'",__FILE__ . " linje " . __LINE__));
				if ( $r_div2['box5'] === "on" ) {
					$phone=$kontonr;
					db_modify ("update ordrer set phone = '$phone' where id = '$id'",__FILE__ . " linje " . __LINE__); #20210506
				}
			}
			$email=$row['email'];
			$ean=$row['ean'];
			$institution=$row['institution'];
			$mail_fakt=$row['mailfakt'];
			$gruppe=$row['gruppe'];
			
			$lev_firmanavn=db_escape_string($row['lev_firmanavn']); #20190618 ->
			$lev_addr1=db_escape_string($row['lev_addr1']);
			$lev_addr2=db_escape_string($row['lev_addr2']);
			$lev_postnr=trim($row['lev_postnr']);
			$lev_bynavn = trim($row['lev_bynavn']);
			if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
			$lev_bynavn = db_escape_string($lev_bynavn);
			$lev_postnr=db_escape_string($lev_postnr);
			$lev_land=db_escape_string($row['lev_land']);
			$lev_kontakt=db_escape_string($row['lev_kontakt']);
      $lev_email=db_escape_string($row['lev_email']);

			($lev_firmanavn)?$vis_lev_addr='on':$vis_lev_addr=NULL; # <- 20190618
			
			(findtekst('244|Ordrefelt 1', $sprog_id) == findtekst('255|Ekstrafelt 1', $sprog_id))?$felt_1=db_escape_string($row['felt_1']):$felt_1='';
			(findtekst('245|Ordrefelt 2', $sprog_id) == findtekst('256|Ekstrafelt 2', $sprog_id))?$felt_2=db_escape_string($row['felt_2']):$felt_2='';
			(findtekst('246|Ordrefelt 3', $sprog_id) == findtekst('257|Ekstrafelt 3', $sprog_id))?$felt_3=db_escape_string($row['felt_3']):$felt_3='';
			(findtekst('247|Ordrefelt 4', $sprog_id) == findtekst('258|Ekstrafelt 4', $sprog_id))?$felt_4=db_escape_string($row['felt_4']):$felt_4='';
			(findtekst('248|Ordrefelt 5', $sprog_id) == findtekst('259|Ekstrafelt 5', $sprog_id))?$felt_5=db_escape_string($row['felt_5']):$felt_5='';
		
			if ($gruppe) {
				$qtxt = "select box1,box3,box4,box6 from grupper ";
				$qtxt.= "where art='DG' and kodenr='$gruppe' and fiscal_year = '$regnaar'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$tmp= (int)substr($r['box1'],1,1);
				$std_rabat=(float)$r['box6'];
				if (!$gl_id) {# valuta & sprog skal beholdes v. ordrekopiering.
					$formularsprog=$r['box4'];
			 		$valuta=$r['box3'];
				}
				$qtxt = "select box2 from grupper where art='SM' and kodenr='$tmp' and fiscal_year = '$regnaar'";
					if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { #20130227
					$momssats=$r['box2'];
				} elseif ($tmp) { #20140403 tilføjet if ($tmp)
					$alert = findtekst('1831|Debitorgrupper forkert opsat', $sprog_id); #20210809
					print "<BODY onLoad=\"javascript:alert('$alert')\">\n";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">\n";
					exit;
				} else  $momssats=0; #20140403
			} else { $alert1 = findtekst('1822|Debitoren er ikke tilknyttet en debitorgruppe', $sprog_id); 
				print "<BODY onLoad=\"javascript:alert('$alert1')\">\n";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?id=$konto_id&returside=../debitor/ordre.php&ordre_id=$id&fokus=$fokus?id=$id\">\n";
				exit;
			}
		}
	}
	
  if (!$id && !$gl_id && $konto_id && $firmanavn ){  # Opretter ny ordre fra konto id
		$phone = str_replace('','',$phone); 
		if (strlen($phone) > 15) {
			alert ("".findtekst('1825|telefonummer må maks være på 15 cifre', $sprog_id)."");
			$phone = substr($phone,0,15);
		}

		$query = db_select("select ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $ordrenr=$row['ordrenr']+1;
		else $ordrenr=1;
    $r = db_fetch_array(db_select("select box1 from grupper where art = 'POS' and kodenr = '3' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__));
		$brugervalg=$r['box1']; # 20170419
		if ($brugervalg) $ref='';
		$afd = (int)$afd;
		$qtxt = "INSERT INTO ordrer (";
		$qtxt.= "ordrenr, konto_id, kontonr, kundeordnr, firmanavn, addr1, addr2, postnr, ";
			$qtxt.= "bynavn, land, kontakt, lev_navn, lev_addr1, lev_addr2, lev_postnr, ";
			$qtxt.= "lev_bynavn, lev_kontakt, lev_land, lev_email, betalingsdage, betalingsbet, ";
		$qtxt.= "cvrnr, ean, institution, email, mail_fakt, phone, notes, art, ordredate,";
			$qtxt.= " momssats, status, ref, lev_adr, valuta, projekt, sprog, pbs, afd, restordre, " ;
			$qtxt.= "felt_1, felt_2, felt_3, felt_4, felt_5, vis_lev_addr";
			$qtxt.= ") VALUES (";
		$qtxt.= "'$ordrenr', '$konto_id', '$kontonr', '$kundeordnr', '$firmanavn', '$addr1', '$addr2', '$postnr', ";
		$qtxt.= "'$bynavn', '$land', '$kontakt', '$lev_firmanavn', '$lev_addr1', '$lev_addr2', '$lev_postnr', ";
		$qtxt.= "'$lev_bynavn', '$lev_kontakt', '$lev_land', '$lev_email', '$betalingsdage', '$betalingsbet', ";
			$qtxt.= "'$cvrnr', '$ean', '$institution', '$email', '$mail_fakt', '$phone', '$notes', '$art', '$ordredate', ";
			$qtxt.= "'$momssats', $status, '$ref', '$lev_adr', '$valuta', '$masterprojekt', '$formularsprog', '$pbs', '$afd', ";
			$qtxt.= "'0', '$felt_1', '$felt_2', '$felt_3', '$felt_4', '$felt_5', '$vis_lev_addr')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);	

		# Porto vare system
		## Create a new order line on creation from the specified sku
		$sku = get_settings_value("porto_varnr", "ordre", null);
		if ($sku != null) {
			$query = db_select("select id from ordrer where ordrenr='$ordrenr' and art='DO' limit 1",__FILE__ . " linje " . __LINE__);
			$ordre = db_fetch_array($query);
			$query = db_select("select * from varer where varenr='$sku' or varenr_alias='$sku' or stregkode='$sku' limit 1",__FILE__ . " linje " . __LINE__);
			$vare = db_fetch_array($query);
			opret_ordrelinje($ordre["id"], $vare["id"], $vare["varenr"], 1, $vare["beskrivelse"], $vare["salgspris"], 0, 0, "DO", "", "", "", "", "", "", "", "", "", "", "", "");
		}

		$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$id=$row['id'];
			if ($gl_id) {
				$r=(db_fetch_array(db_select("select levdate,ordredate,fakturadate,nextfakt from ordrer where id='$gl_id'",__FILE__ . " linje " . __LINE__)));
				if ($r['nextfakt']) {
					$nextfakt=find_nextfakt($r['fakturadate'],$r['nextfakt']);
					db_modify("update ordrer set levdate='$r[nextfakt]',fakturadate='$r[nextfakt]',nextfakt='$nextfakt',ordredate='$r[ordredate]' where id = $id",__FILE__ . " linje " . __LINE__);
				}
			}
		}
  }  elseif(($kontonr)&&($status<3)) {
		$sum=0;
		for($x=1;$x<=$linjeantal;$x++) {
#      $antal[$x]*=1;
			$vare_id[$x]*=1;
			if ($lagerantal > 1) {
				$qtxt = "select sum(beholdning) as qty from lagerstatus where vare_id = '$vare_id[$x]' and lager = '$lager[$x]'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$stockQty = $r['qty'];
			}
			$qtxt = "select gruppe,beholdning from varer where id = $vare_id[$x]";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$beholdning[$x] = $r['beholdning'];
				$vare_grp[$x]   = $r['gruppe'];
			} else $beholdning[$x] = $vare_grp[$x] = 0;
			if ($lagerantal >1) $beholdning[$x] = $stockQty;
			if (!$varenr[$x]) {$antal[$x]=0; $pris[$x]=0; $rabat[$x]=0;} 
# -> udkommenteret 20180913	
#      elseif ((($antal[$x]>=0)&&($leveres[$x]<0))||(($antal[$x]<=0)&&($leveres[$x]>0))) {
#        print "<BODY onLoad=\"javascript:alert('Der skal v&aelig;re samme fortegen i antal og l&eacute;ver! (Position $posnr_ny[$x] nulstillet)')\">\n";
#        $leveres[$x]=0;
#      } 
      elseif ($vare_id[$x] || isset($_GET['varenr'])) { #20241229
        if ($art=='DK') { # DK = Kreditnota
#          if ($antal[$x]>0) {
#            $antal[$x]=$antal[$x]*-1;
#            print "<BODY onLoad=\"javascript:alert('Der kan ikke krediteres et negativt antal. Antal reguleret (Varenr: $varenr[$x])')\">\n";
#          }

          $kred_linje_id[$x]*=1;
          if (!$folgevare[$x] || $folgevare[$x]>0) {
            $qtxt="select antal from ordrelinjer where id = '$kred_linje_id[$x]' and (vare_id='$vare_id[$x]' or vare_id='0')"; #Vare_id er med for ikke at taelle delvarer med v. samlevarer.
            $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
            if ($antal[$x]+$r['antal']<0) {
              $antal[$x]=$r['antal']*-1;
              $alert = findtekst('1832|Der kan højst krediteres', $sprog_id);
              $alert1 = findtekst('1833|Antal reguleret', $sprog_id);
              $alert2 = findtekst('917|Varenr.', $sprog_id);
              print "<BODY onLoad=\"javascript:alert('$alert ".dkdecimal($row['antal'],2).". $alert1 ($alert2: $varenr[$x])')\">\n";
            }
          }
          if ($antaldiff[$x]) db_modify("update ordrelinjer set antal=$antal[$x] where id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
        } elseif (($antal[$x]<0)&&($kred_linje_id[$x]>0)) {
          $query = db_select("select antal from ordrelinjer where id = $kred_linje_id[$x] and vare_id=$vare_id[$x]",__FILE__ . " linje " . __LINE__);
          $row = db_fetch_array($query);
          if ($antal[$x]+$row['antal']<0) {
            $antal[$x]=$row['antal']*-1;
            $alert = findtekst('1834|Der kan højst tages', $sprog_id);
            print "<BODY onLoad=\"javascript:alert('$alert $row[antal] retur. $alert1 ($alert2: $varenr[$x])')\">\n";
          }
        } elseif ($antaldiff[$x] && (!$samlevare[$x] || abs($antal[$x]))) {
          $svar=opret_ordrelinje($id,"$vare_id[$x]","$varenr[$x]","$antaldiff[$x]","$beskrivelse[$x]","$pris[$x]","$rabat[$x]","$procent[$x]","$art","$momsfri[$x]","$posnr_ny[$x]","$linje_id[$x]","$incl_moms","","$rabatart[$x]","0",'','','',$lager[$x],__LINE__);
          if (!is_numeric($svar)) print "<BODY onLoad=\"javascript:alert('$svar')\">";
        }
        if (!$negativt_lager && $leveres[$x]>$beholdning[$x] && (!$hurtigfakt || $b_submit=="doInvoice") && $leveres[$x]>$beholdning[$x] && $leveres[$x]>0 &&
            db_fetch_array(db_select("select id from grupper where kodenr='$vare_grp[$x]' and art='VG' and box8='on' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__))) {
          if ($beholdning[$x]<=0) $leveres[$x]=0;
          else $leveres[$x]=$beholdning[$x]*1;
          $tmp=$posnr_ny[$x]/100;
          if ($hurtigfakt) {
            $tekst="".findtekst('1500|Lagerbeholdning', $sprog_id).": ".dkdecimal($beholdning[$x],2).". ".findtekst('1835|Der kan højest leveres', $sprog_id)." $leveres[$x] ".findtekst('1836|fra linjen med', $sprog_id)." pos.nr. $tmp)";
          } else{
            $tekst="".findtekst('1500|Lagerbeholdning', $sprog_id).": ".dkdecimal($beholdning[$x],2).". ".findtekst('1835|Der kan højest leveres', $sprog_id)." $leveres[$x]. ".findtekst('1833|Antal reguleret', $sprog_id)." (pos.nr. $tmp)";
          }
          Print "<BODY onLoad=\"javascript:alert('$tekst')\">\n";
          if ($b_submit=="doInvoice") $b_submit="Gem";
        }
        $tidl_lev[$x]=0;
        $qtxt="select antal from batch_salg where linje_id = $linje_id[$x] and vare_id=$vare_id[$x]";
        $q = db_select($qtxt,__FILE__ . " linje " . __LINE__); #Vare_id er med for ikke at taelle delvarer med v. samlevarer.
        while ($r= db_fetch_array($q)) $tidl_lev[$x]=$tidl_lev[$x]+$r['antal'];
        if ((($tidl_lev[$x]<0)&&($antal[$x]>$tidl_lev[$x]))||(($tidl_lev[$x]>0)&&($antal[$x]<$tidl_lev[$x]))){
          $antal[$x]=$tidl_lev[$x];
#          print "<BODY onLoad=\"javascript:alert('Der er allerede leveret $tidl_lev[$x]. Antal reguleret (varenr. $varenr[$x])')\">\n"; udkommenteret  20180913
        } elseif ($antal>0) {
          if (($tidl_lev[$x]<$antal[$x])&&($status>1)) { $alert = findtekst('1837|Du kan ikke fakturere, før alt er leveret', $sprog_id);
            if ($omdan_t_fakt == "on") {print "<BODY onLoad=\"javascript:alert('$alert')\">\n";}
            $status=1;
          }
          if (!isset($reserveret[$x])) $reserveret[$x]=0;
          $query = db_select("select antal from reservation where linje_id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
          while ($row = db_fetch_array($query)) $reserveret[$x]+=$row['antal'];
          if (($antal[$x]<$tidl_lev[$x]+$reserveret[$x])&&($antal[$x]>0)) {
            $diff=$tidl_lev[$x]+$reserveret[$x]-$antal[$x];
            while ($diff>0) {
              $query = db_select("select * from reservation where linje_id = $linje_id[$x] order by batch_kob_id desc",__FILE__ . " linje " . __LINE__);
              if ($row = db_fetch_array($query)) {
                if ($diff < $row['antal']) {
                  $temp = $row['antal'] - $diff;
                  if ($row['batch_kob_id']) $qtxt="update reservation set antal = '$temp' where linje_id='$linje_id[$x]' and batch_kob_id='$row[batch_kob_id]' and antal='$row[antal]' and vare_id='$row[vare_id]'";
                  else $qtxt="update reservation set antal = '$temp' where linje_id='$linje_id[$x]' and antal='$row[antal]' and vare_id='$row[vare_id]'";
                  db_modify("$qtxt",__FILE__ . " linje " . __LINE__);
                  $diff=0;
                } elseif ($diff >= $row['antal']) {

                  if ($row['batch_kob_id']) $qtxt="delete from reservation where linje_id='$linje_id[$x]' and batch_kob_id='$row[batch_kob_id]' and antal='$row[antal]' and vare_id='$row[vare_id]'";
                  else $qtxt="delete from reservation where linje_id='$linje_id[$x]' and antal='$row[antal]' and vare_id='$row[vare_id]'";
                  db_modify("$qtxt",__FILE__ . " linje " . __LINE__);
                  $diff=$diff - $row['antal'];
                }
              } else $diff=0;
            }
          }
        }
        if (!isset($modtaget[$x]))$modtaget[$x]=0;
        $query = db_select("select antal from batch_salg where linje_id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
        while ($row = db_fetch_array($query)) $modtaget[$x]=$modtaget[$x]+$row['antal'];
        if (($antal[$x]>$modtaget[$x])&&($modtaget[$x]<0)) {
          $antal[$x]=$modtaget[$x];
          $alert = findtekst('1838|Der er allerede modtaget', $sprog_id);
          $alert1 = findtekst('1833|Antal reguleret', $sprog_id);
          $alert2 = findtekst('917|Varenr.', $sprog_id);
          print "<BODY onLoad=\"javascript:alert('$alert $temp. $alert1 ($alert2. $varenr[$x])')\">\n";
        }
      }
      if (!is_numeric($posnr_ny[$x]) && $posnr_ny[$x]=='-') {
        $lagerfort=NULL;
        if ($vare_id[$x]) {
          $qtxt="select gruppe from varer where id='$vare_id[$x]'";
          $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
          $qtxt="select box8 from grupper where art='VG' and kodenr = ". (int)$r['gruppe'] ." and fiscal_year = '$regnaar'";
          $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
          $lagerfort=$r['box8'];
        }
        if ($lagerfort) {
          $query = db_select("select * from batch_kob where linje_id = $linje_id[$x] and antal != 0",__FILE__ . " linje " . __LINE__);
          if ($row = db_fetch_array($query)) {
            $txt=findtekst('1839|Du kan ikke slette en varelinje, når der ér modtaget', $sprog_id).$row['antal'].' varer fra linjen.';
            alert("$txt");
          } else { #20191104
            $qtxt="select batch_salg.vare_id,varer.gruppe from batch_salg,varer where batch_salg.linje_id = $linje_id[$x] and batch_salg.antal != 0";
            $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
            $qtxt="select box8 from grupper where art='VG' and kodenr='$r[gruppe]' and fiscal_year = '$regnaar'";
            $tmp=NULL; 
            if ($r['vare_id'] && $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $tmp=$r['box8'];
            if ($tmp) { 
              $txt=findtekst('1840|Du kan ikke slette en varelinje, når der ér leveret varer fra linjen.', $sprog_id);
              alert($txt);
            } elseif ($linje_id[$x]) {
              db_modify("delete from batch_kob where linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
              db_modify("delete from batch_salg where linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
              db_modify("delete from reservation where linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
              db_modify("delete from reservation where batch_salg_id='-$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
              db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
              if ($folgevare[$x]) {
                $tmp=$linje_id[$x]*-1;
                db_modify("delete from ordrelinjer where folgevare='$tmp' and ordre_id='$id'",__FILE__ . " linje " . __LINE__);
              }
              db_modify("delete from ordrelinjer where samlevare='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
              db_modify("update serienr set salgslinje_id = 0 where salgslinje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
            }
          }
        } elseif ($linje_id[$x]) {
          db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
        }
      } elseif ( !strstr($b_submit,"Kopi") && !strstr($b_submit,"Udskriv") && !strstr($b_submit,"Send")) {
				#$alert2 = findtekst('916|Antal', $sprog_id);
				#$alert3 = findtekst('1842|er for stort, reducer antal', $sprog_id);
				if ((!strpos($posnr_ny[$x],'+'))&&($id)) {
					$posnr_ny[$x]=afrund($posnr_ny[$x],0);
					if ($posnr_ny[$x]>=1) {
						$alert = str_replace("'","`",findtekst('1841|Hint! Du skal sætte tegnet - (minus) i feltet Pos for at slette en varelinje.', $sprog_id));
#						if ($varenr[$x]==$rvnr) $posnr_ny[$x]+=1000;
						db_modify("update ordrelinjer set posnr='$posnr_ny[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					} else print "<BODY onLoad=\"javascript:alert('$alert')\">\n";
				}
				if (!isset($projekt[$x])) $projekt[$x]=0;
				if (!isset($kdo[$x])) $kdo[$x]=NULL;
				if ($linje_id[$x]) {
					if (!$antal[$x]) $antal[$x]=0;
					$sum=$sum+($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
					if (!$leveres[$x]) $leveres[$x]=0;
					elseif ($antal[$x] > 0 && $leveres[$x] > $antal[$x]) {
						$leveres[$x] = $antal[$x];
					}
					if (!$rabat[$x]) $rabat[$x]=0;
					if (!$kostpris[$x]) $kostpris[$x]=0;
					if ($projekt[0]) $projekt[$x]=$projekt[0];
					else $projekt[$x]=$projekt[$x];
					if ($saet[$x] || ($varenr[$x] && $varenr[$x]==$rvnr)) {
						$qtxt="update ordrelinjer set leveres='$leveres[$x]' where id='$linje_id[$x]'";
					} else {
						$qtxt="update ordrelinjer set varenr='$varenr[$x]',antal=$antal[$x],beskrivelse='$beskrivelse[$x]',leveres='$leveres[$x]',";
						$qtxt.="pris='$pris[$x]',kostpris='$kostpris[$x]',rabat='$rabat[$x]',procent='$procent[$x]',projekt='$projekt[$x]',";
						$qtxt.="kdo='$kdo[$x]',omvbet='$omvbet[$x]',saet='0',samlevare='$samlevare[$x]',lager='$lager[$x]' where id='$linje_id[$x]'";
					}
					if ($antal[$x] < 100000000000) {
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					} else {
						$alert = findtekst('916|Antal', $sprog_id)." (". dkdecimal($antal[$x]) .") ".findtekst('1842|er for stort, reducer antal', $sprog_id);
						print "<BODY onLoad=\"javascript:alert('$alert')\">";
					}
					if ($samlevare[$x]) {
						if ($antal[$x]) {
							$q=db_select("SELECT id,antal FROM ordrelinjer WHERE samlevare = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
							while($r=db_fetch_array($q)) {
								db_modify("update ordrelinjer set leveres=antal*$leveres[$x]/$antal[$x] where id='$r[id]'",__FILE__ . " linje " . __LINE__); 
							}
						} else db_modify("update ordrelinjer set leveres='0' where samlevare='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					}
				}
				if ((strpos($posnr_ny[$x],'+'))&&($id)) indsaet_linjer($id,$linje_id[$x],$posnr_ny[$x]);
			}
		}
		if (($posnr_ny[0])&&(!strstr($b_submit,'Opslag'))) {
      if (!$varenr[0] && $beskrivelse[0] && $pris[0]) {
        $i= $tmp = 0;
        $qtxt = "select varenr from varer limit 2";
        $q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
        while ($r = db_fetch_array($q)) {
          if ($r['varenr']) {
            $tmp=$r['varenr'];
            $i++;
          }
        }
        if ($i == 1) {
          $varenr[0] = $tmp;
          if (!$antal[0]) $antal[0] = 1;
        }
      }
	if ($varenr[0]) {
		$samlevare[0]='';
		if ($brugsamletpris) {
			$r=db_fetch_array(db_select("SELECT id,samlevare,salgspris FROM varer WHERE varenr = '$varenr[0]' or varenr_alias = '$varenr[0]' or stregkode = '$varenr[0]'",__FILE__ . " linje " . __LINE__));
			$samlevare[0]=$r['samlevare'];
		}
		if ($brugsamletpris && $samlevare[0]) {
			if ($incl_moms) $salgspris[0]=$r['salgspris']+$r['salgspris']*$momssats/100;
			opret_saet($id,$r['id'],$salgspris[0],$momssats,$antal[0],$incl_moms,$lager[0]);#20170627
		} else {
			$svar=opret_ordrelinje($id,"",$varenr[0],$antal[0],$beskrivelse[0],$pris[0],$rabat[0],$procent[0],$art,$momsfri[0],$posnr_ny[0],0,$incl_moms,"","",0,"","","",$lager[0],__LINE__);
			
			if (!is_numeric($svar)) print "<BODY onLoad=\"javascript:alert('$svar')\">";
			if (!$antal[0] && !isset($_POST['indsat'])) { #20151019
				$fokus='dkan'.$x;
			}
		}
      } elseif ($beskrivelse[0] && is_numeric($posnr_ny[0])) {
        db_modify("insert into ordrelinjer (ordre_id,posnr,beskrivelse,lager) values ('$id','$posnr_ny[0]','$beskrivelse[0]','$lager[0]')",__FILE__ . " linje " . __LINE__);
      }
		}
		if ($id) {
			$timestamp = $who = NULL;
			$qtxt="select tidspkt,hvem from ordrer where status < 3 and id = $id and hvem != '$brugernavn'";
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$timestamp = trim($r['tidspkt']);
				$who       = $row['hvem'];
			}
			if ($tidspkt && $who) {
				if ($tidspkt- $timestamp < 3600 && $who) { $alert = findtekst('1843|Ordren er overtaget af', $sprog_id);
					print "<BODY onLoad=\"javascript:alert('$alert $who')\">\n";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
				}
			} else {
				$tmp="";
				if (strlen($levdate)>6) $tmp=",levdate='$levdate'";
				if (strlen($fakturadate)>6) $tmp=$tmp.",fakturadate='$fakturadate'";
				if ($genfakt) $tmp=$tmp.",nextfakt='".usdate($genfakt)."'";
				$afd    = (int)$afd;
				$status = (int)$status;
				$qtxt = "update ordrer set kontonr='$kontonr',kundeordnr='$kundeordnr',firmanavn='$firmanavn',";
				$qtxt.= "addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',land='$land',kontakt='$kontakt',";
				$qtxt.= "kontakt_tlf='$kontakt_tlf',lev_navn='$lev_navn',lev_addr1='$lev_addr1',lev_addr2='$lev_addr2',";
        $qtxt.= "lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_email='$lev_email',lev_land='$lev_land',lev_kontakt='$lev_kontakt',vis_lev_addr='$vis_lev_addr',";
				$qtxt.= "felt_1='$felt_1',felt_2='$felt_2',felt_3='$felt_3',felt_4='$felt_4',felt_5='$felt_5',";
				$qtxt.= "betalingsdage='$betalingsdage',betalingsbet='$betalingsbet',cvrnr='$cvrnr',momssats='$momssats',";
				$qtxt.= "procenttillag='$procenttillag',ean='$ean',institution='$institution',email='$email',mail_fakt='$mail_fakt',";
				$qtxt.= "phone='$phone',udskriv_til='$udskriv_til',notes='". db_escape_string($notes) ."', ";
				$qtxt.= "ordredate='$ordredate',status='$status',ref='$ref',";
				$qtxt.= "fakturanr='$fakturanr',lev_adr='$lev_adr',hvem='$brugernavn',tidspkt='$tidspkt',projekt='$projekt[0]',";
				$qtxt.= "sprog='$formularsprog',pbs='$pbs',afd='$afd',restordre='$restordre',mail_subj='$mail_subj',mail_text='$mail_text' $tmp ";
				$qtxt.= "where id=$id";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				if ($vis_saet && $afd && !$felt_5) { #20180316
					$qtxt="select box1,box3 from grupper where art='POS' and kodenr='1'";
					$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$tmparray=explode(chr(9),$r['box3']);
					for ($x=0;$x<count($tmparray);$x++) {
						if ($tmparray[$x]==$afd) $kasse=$x+1;
					}
					$qtxt="update ordrer set felt_5 = '$kasse' where id = '$id'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}

	if ($samlet_pris=='-') { #201503170
		gendan_saet($id);
		$samlet_pris = $ordresum;
	}
	if ($rabatvare_id && $samlet_pris && $ordresum && $samlet_pris != $ordresum) {
		gendan_saet($id);
		$samlet_rabat=0;
		$rvnr=$rabatvare_nr;
		$rvid=$rabatvare_id;
		if ($rvid && $rvnr) { #20150317
			db_modify("delete from ordrelinjer where vare_id='$rvid' and ordre_id='$id'",__FILE__ . " linje " . __LINE__);
			$bruttosum=0;
			$bruttosaetsum=0;
			$alert = findtekst('1844|Intet varenummer til kontering af øredifferencer ved rabat', $sprog_id);
			$q=db_select("select * from ordrelinjer where ordre_id = '$id' and varenr != '$rvnr'",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				$ms=$r['momssats'];
				if ($momssats<$ms) $ms=$momssats; 
				$bruttosum+=afrund($r['antal']*($r['pris']+$r['pris']*$ms/100),3);
				if (!$r['saet']) $bruttosaetsum+=$r['antal']*($r['pris']+$r['pris']*$ms/100);
				elseif ($r['samlevare']) {
					list($tmp)=explode("|",$r['lev_varenr']);
					$bruttosaetsum+=$tmp;
				}	
			}
			$samlet_rabat=$bruttosum-$samlet_pris;
			($bruttosum)?$samlet_rabatpct=afrund(($samlet_rabat)*100/$bruttosum,3):$samlet_rabatpct=0;
		} else print "<BODY onLoad=\"javascript:alert('$alert')\">"; #fejl($id,'Intet varenummer "R" til kontering af øredifferencer ved rabat');
			if ($bruttosaetsum==$samlet_pris)$samlet_rabatpct=0;
		if ($samlet_rabatpct) {
			db_modify("update ordrelinjer set rabat=$samlet_rabatpct where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		}
  }  transaktion("commit");
}
########################## KOPIER #################################
if ((strstr($b_submit,'Kopi'))||(strstr($b_submit,'Kred')))  {
	if (strstr($b_submit,"Kred")) {
		$art='DK';
		$query = db_select("select id from ordrer where kred_ord_id = $id",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$row[id]\">\n";
			exit;
		} elseif ($kred_ord_id) {
			$id='';
			$status=0;
		} else {
			$kred_ord_id=$id;
			$id='';
			$status=0;
		}
		if ($vis_saet) {
			$felt_2*=-1;
			$felt_4*=-1;
		}
	} elseif (strstr($b_submit,"Kopi")){
		$gl_id=$id;
		$id='';
		$status=0;
	}
	if ((!$id)&&($konto_id)){ 
		$qtxt="select kontonr from adresser where id='$konto_id'"; #20160217
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$kontonr=$r['kontonr']*1;
		
		$qtxt="select ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc limit 1";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $ordrenr=$r['ordrenr']+1;
		else $ordrenr=1;
		
    $tilbudnr = (int)if_isset($tilbudnr, 0);
    $sag_id = (int)if_isset($sag_id, NULL); #20250528
    $sagsnr = (int)if_isset($sagsnr, 0);
    $nr = (int)if_isset($nr, 0);
		$qtxt="insert into ordrer"; 
		$qtxt.="(ordrenr,konto_id,kontonr,kundeordnr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,lev_navn,";
    $qtxt.="lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,lev_email,lev_land,betalingsdage,betalingsbet,cvrnr,ean,institution,";
		$qtxt.="email,mail_fakt,phone,notes,art,ordredate,momssats,status,ref,lev_adr,valuta,projekt,sprog,";
		$qtxt.="pbs,afd,restordre,procenttillag,sag_id,sagsnr,tilbudnr,datotid,nr,returside,omvbet,felt_1,felt_2,felt_3,felt_4,felt_5)";
		$qtxt.=" values "; $qtxt.="($ordrenr,'$konto_id','$kontonr','$kundeordnr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt',";
    $qtxt.="'$lev_navn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt','$lev_email','$lev_land','$betalingsdage','$betalingsbet',";
		$qtxt.="'$cvrnr','$ean','$institution','$email','$mail_fakt','$phone','$notes','$art','$ordredate','$momssats','$status','$ref','$lev_adr',";
		$qtxt.="'$valuta','$projekt[0]','$formularsprog','$pbs','$afd','0','$procenttillag','$sag_id','$sagsnr','$tilbudnr','$datotid',";
		$qtxt.="'$nr','$returside','$omkunde',";
		($art=='PO')?$qtxt.="'','','','','')":$qtxt.="'$felt_1','$felt_2','$felt_3','$felt_4','$felt_5')"; #20191004
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$id=$r['id'];
			if ($gl_id) {
				$qtxt="select levdate,ordredate,fakturadate,nextfakt from ordrer where id='$gl_id'";
				$r=(db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)));
				if ($r['nextfakt']) {
					$nextfakt=find_nextfakt($r['fakturadate'],$r['nextfakt']);
					$qtxt="update ordrer set levdate='$r[nextfakt]',fakturadate='$r[nextfakt]',nextfakt='$nextfakt',ordredate='$r[ordredate]' where id = $id";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	if ($id && strstr($b_submit,'Kred') && $kred_ord_id) {
		db_modify("update ordrer set kred_ord_id='$kred_ord_id' where id='$id'",__FILE__ . " linje " . __LINE__);
	}		
	for($x=1; $x<=$linjeantal; $x++) {
		if (!$vare_id[$x] && $antal[$x] && $varenr[$x]) {
			$qtxt = "select id from varer where varenr = '$varenr[$x]' or varenr_alias = '$varenr[$x]' or stregkode = '$varenr[$x]'";
			$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) $vare_id[$x]=$row['id'];
		}
		if ($vare_id[$x]){
			$variantId = 0;
			$vareNr = ''; #20211129  ->
			$qtxt = "select variant_id from ordrelinjer where id = '$linje_id[$x]'";
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $variantId=$r['variant_id'];
			if ($variantId) {
				$qtxt = "select variant_stregkode from variant_varer where id = '$variantId'";
				if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $vareNr=$r['variant_stregkode'];
			} # <- 20211129 
			(strstr($b_submit,'Kopi'))?$tmp=$antal[$x]*1:$tmp=$antal[$x]*-1;
			(strstr($b_submit,'Kred'))?$tmp2=$linje_id[$x]:$tmp2='0';
			if (!$momsfri[$x] && !$varemomssats[$x]) $varemomssats[$x]=$momssats;
			if ($varemomssats[$x] > $momssats) $varemomssats[$x]=$momssats;
			if ($momsfri[$x] || $omvbet[$x]) $varemomssats[$x]=0;
			if ($incl_moms) $pris[$x]+=$pris[$x]*$varemomssats[$x]/100;
			if ($procenttillag && $procentvare && $varenr[$x] == $procentvare) {
				$tmp=NULL; # der skal bare stå et eller andet :-)
      }  elseif ((!$kdo[$x] || strstr($b_submit,'Kred')) && (!$folgevare[$x] || $folgevare[$x]>=0)) {
			$svar=opret_ordrelinje($id,"$vare_id[$x]","$vareNr","$tmp","$beskrivelse[$x]","$pris[$x]","$rabat[$x]","$procent[$x]",
			"$art","$momsfri[$x]","$posnr[$x]","$tmp2","","","$rabatart[$x]","1",$saet[$x],$fast_db[$x],$lev_varenr[$x],$lager[$x],__LINE__);
				if (!is_numeric($svar)) print "<BODY onLoad=\"javascript:alert('$svar')\">";
				elseif ($vare_id[$x] && ($folgevare[$x] || $projekt[$x] || $fast_db[$x])) {
						$r=db_fetch_array(db_select("select max(id) as id from ordrelinjer where ordre_id='$id' and vare_id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__)); 
						if ($r['id']) {
						if ($fast_db[$x]) db_modify("update ordrelinjer set fast_db = '$fast_db[$x]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
						if ($folgevare[$x]) db_modify("update ordrelinjer set folgevare='$folgevare[$x]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
            if ($projekt[$x])  db_modify("update ordrelinjer set projekt='$projekt[$x]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__); #20130816
            if ($samlevare[$x])  db_modify("update ordrelinjer set samlevare='$samlevare[$x]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__); #20130816
					}
				}
			}
		}
		else {
			$qtxt = "insert into ordrelinjer (ordre_id,posnr,beskrivelse) values ('$id','$posnr_ny[$x]','$beskrivelse[$x]')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
#xit;
}
##########################UDSKRIFT#################################

if ((strstr($b_submit,"Udskriv"))||(strstr($b_submit,"Send"))) {
	$lagervarer=if_isset($_POST['lagervarer']);
	if ($status>=3)  {
		$temp="aktura"; $formular=4; $ps_fil="formularprint.php";
	}
	elseif($status>=1) {
		if ($udskriv_til=='historik') {
      $temp="rdrebek";  $formular=2; $ps_fil="formularprint.php";
    }  else {
			if (db_fetch_array(db_select("select lev_nr from batch_salg where ordre_id=$id and lev_nr=1",__FILE__ . " linje " . __LINE__))) {
				$formular=3;
				$ps_fil="udskriftsvalg.php";
			} elseif (db_fetch_array(db_select("select leveres from ordrelinjer where ordre_id=$id and leveres>0",__FILE__ . " linje " . __LINE__))) {
				$formular=9;
				$ps_fil="udskriftsvalg.php";
      } else {$temp="rdrebek";  $formular=2; $ps_fil="formularprint.php";}
		}
	} else {$temp="ilbud"; $formular=1; $ps_fil="formularprint.php";}
	if($udskriv_til=="oioubl" && $status >=3) {
		if($art=="DO") $oioubl='faktura';
		else $oioubl='kreditnota';
		if ($popup) print "<BODY onLoad=\"JavaScript:window.open('oioubl_dok.php?id=$id&doktype=$oioubl' ,'' ,'$jsvars');\">\n";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=oioubl_dok.php?id=$id&doktype=$oioubl\">\n";
	} elseif($udskriv_til=="edifakt") {
		if($art=="DO") $oioubl='faktura';
		else $oioxml='kreditnota';
		if ($popup) print "<BODY onLoad=\"JavaScript:window.open('oioxml_dok.php?id=$id&doktype=$oioxml' ,'' ,'$jsvars');\">\n";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=oioxml_dok.php?id=$id&doktype=$oioxml\">\n";
	} elseif (strstr($udskriv_til,'PBS')) {
			include("pbsfakt.php");
			pbsfakt($id);
	}elseif($udskriv_til=="Digitalt" && $status >=3 && $art=="DO"){
		$query = db_select("SELECT * FROM settings WHERE var_name = 'companyID' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
		if(db_num_rows($query) <= 0){
		?>
		<script>
		
		if(confirm('Ved at sende fakture digitalt, vil du blive oprettet i nemhandel') == true)
			window.open('peppol.php?id=<?php echo $id;?>&type=invoice' ,'_blank')
		</script>
		<?php
		}else{
		$query = db_select("SELECT digital_status FROM ordrer WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$digital_status = $row['digital_status']; 
		if($digital_status == "Sent"){
			?>
			<script>
			if(confirm('fakturen er allerede sendt digitalt vil du sende igen?') == true)
				window.open('peppol.php?id=<?php echo $id;?>&type=invoice' ,'_blank');
			</script>
			<?php
		}else{
		?>
		<script>
		window.open('peppol.php?id=<?php echo $id;?>&type=invoice' ,'_blank')
		</script>
		<?php
		}
		}
  }elseif($udskriv_til=="Digitalt" && $art=="DO" && ($status==2 || $status==1)){
    $query = db_select("SELECT * FROM settings WHERE var_name = 'companyID' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
    if(db_num_rows($query) <= 0){
    ?>
    <script>
      if(confirm('Ved at sende fakture digitalt, vil du blive oprettet i nemhandel') == true)
      window.open('peppol.php?id=<?php echo $id;?>&type=invoice' ,'_blank')
    </script>
    <?php
    }else{
    	$query = db_select("SELECT digital_status FROM ordrer WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$digital_status = $row['digital_status']; 
		if($digital_status == "Sent"){
			?>
			<script>
			if(confirm('fakturen er allerede sendt digitalt vil du sende igen?') == true)
				window.open('peppol.php?id=<?php echo $id;?>&type=invoice' ,'_blank');
			</script>
			<?php
		}else{
		?>
		<script>
		window.open('peppol.php?id=<?php echo $id;?>&type=invoice' ,'_blank')
		</script>
		<?php
		}
		}
  }elseif($udskriv_til=="Digitalt" && $art=="DK" && $status >=3){
    $query = db_select("SELECT * FROM settings WHERE var_name = 'companyID' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
    if(db_num_rows($query) <= 0){
    ?>
    <script>
      
      if(confirm("ved at sende faktura/kreditnote digitalt, vil du blive oprettet i nemhandel") == true)
        window.open('peppol.php?id=<?php echo $id;?>&type=creditnote' ,'_blank')
    </script>
    <?php
    }else{
    $query = db_select("SELECT digital_status FROM ordrer WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$digital_status = $row['digital_status']; 
		if($digital_status == "Sent"){
			?>
			<script>
			if(confirm('fakturen er allerede sendt digitalt vil du sende igen?') == true)
				window.open('peppol.php?id=<?php echo $id;?>&type=invoice' ,'_blank');
			</script>
			<?php
		}else{
		?>
		<script>
		window.open('peppol.php?id=<?php echo $id;?>&type=invoice' ,'_blank')
		</script>
		<?php
		}
		}
  }elseif($udskriv_til=="Digitalt" && $art=="DK" && $status <=2){
    $query = db_select("SELECT * FROM settings WHERE var_name = 'companyID' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
    if(db_num_rows($query) <= 0){
    ?>
    <script>
      if(confirm("ved at sende faktura/kreditnote digitalt, vil du blive oprettet i nemhandel") == true)
        window.open('peppol.php?id=<?php echo $id;?>&type=creditnote' ,'_blank')
    </script>
    <?php
    }else{
    $query = db_select("SELECT digital_status FROM ordrer WHERE id = '$id'", __FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$digital_status = $row['digital_status']; 
		if($digital_status == "Sent"){
			?>
			<script>
			if(confirm('fakturen er allerede sendt digitalt vil du sende igen?') == true)
				window.open('peppol.php?id=<?php echo $id;?>&type=invoice' ,'_blank');
			</script>
			<?php
		}else{
		?>
		<script>
		window.open('peppol.php?id=<?php echo $id;?>&type=invoice' ,'_blank')
		</script>
		<?php
		}
		}
  }else{
		$oioxml='';
		$oioubl='';
		$edifakt='';
#    if ($udskriv_til!='historik') $udskriv_til='';
	 	if ($popup) print "<BODY onLoad=\"JavaScript:window.open('$ps_fil?id=$id&formular=$formular&udskriv_til=$udskriv_til&lagervarer=$lagervarer' ,'' ,',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,location=1');\">\n";
		else {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$ps_fil?id=$id&formular=$formular&udskriv_til=$udskriv_til&lagervarer=$lagervarer\">\n";
		}
	}
}
##########################OPSLAG###Angiv#############################
#+++++++++++++++++++++++++++++++++
$formData = $_POST;
$queryString = http_build_query($formData);
$href = "ordre.php?";
$Urlc = $href . $queryString;
#global $db;echo __LINE__.'URL;;;'.$Urlc;
$directory = "temp/$db";  // Directory path
$priceListUrl = "$directory/pricelisturl.txt";
$option = NULL;
// Ensure the directory exists
if (!is_dir($directory)) {
    if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
        echo "Error: Could not create the directory.";
        exit;
    }
}

// Read or create the file and check if empty
if (!file_exists($priceListUrl)) {
    file_put_contents($priceListUrl, $Urlc);
   
} 
    $fileContents = file_get_contents($priceListUrl);

    $lookUpValue = '';  // Default to empty if 'lookUp' is not found
    $priceLL = '';      // Default to empty if 'pricelist' is not found

    if (preg_match('/[?&]lookUp=([^&]*)/', $fileContents, $matches)) {
        $lookUpValue = $matches[1];
    }

    if (preg_match('/[?&]pricelist=([^&]*)/', $fileContents, $matches)) {
        $priceLL = $matches[1];
    }

    if ((empty($lookUpValue) && strpos($fileContents, 'lookUp') === false) ||
        (empty($priceLL) && strpos($fileContents, 'pricelist') === false) || 
        strpos($fileContents, 'kontonr=') !== false || strpos($fileContents, 'vare0=') == false
        ) {
        $priceL = "&pricelist=pricelist";
        if(isset($formData)){
          if(strpos($fileContents, 'kontonr=') !== false){
            $newContent = $Urlc . $priceL;
            file_put_contents($priceListUrl, $newContent);
          }elseif(strpos($fileContents, 'vare0=') !== false){
            $newContent = $Urlc . $priceL;
            file_put_contents($priceListUrl, $newContent);
          }else{
              $newContent = $Urlc . $priceL;
              file_put_contents($priceListUrl, $newContent);
             # echo __LINE__.'in ordre...';
              
          }
         
        }
        
    }

$Urlc = file_get_contents($priceListUrl);
if ($Urlc === false || empty(trim($Urlc))) {
    echo "Error: Could not read URL from file or file is empty.";
    exit;
}

$originalUrl = $Urlc;
if (!filter_var($Urlc, FILTER_VALIDATE_URL)) {
    if (strpos($Urlc, 'https://') !== 0) {
		// Add 'https://' to $Urlc only if it doesn't already start with it
		$Urlc = 'https://' . $Urlc;
	}
}

$parsedUrl = parse_url($Urlc);
if (isset($parsedUrl['query']) && !empty($parsedUrl['query'])) {
    parse_str($parsedUrl['query'], $queryParams);


} else {
    file_put_contents('test1.txt', 'Error: URL does not contain a query string.', FILE_APPEND);
}

$queryParamA = !empty($_POST['pricelist']) ? $_POST : $_GET;


$Urlc = $originalUrl;
// Now use the $queryParams to set the variables
  if (isset($_GET['fokus']) && ($_GET['fokus']=='vare0')) {
    $queryParams = $_GET; // Use GET values when 'vare0' is set.
  }
   if ( isset($queryParams['lookUp']) && isset($queryParamA['lookUp']) && isset($queryParamA['pricelist'])) {
   // if ( isset($queryParams['lookUp']) && isset($_GET['lookUp']) && isset($_GET['pricelist'])) {
if(if_isset($queryParams, NULL, 'ordrenr')) $ordrenr = $queryParams['ordrenr'];
if(if_isset($queryParams, NULL, 'status')) $status = $queryParams['status'];
if(if_isset($queryParams, NULL, 'id')) $id = $queryParams['id'];
if(if_isset($queryParams, NULL, 'art')) $art = $queryParams['art'];
if(if_isset($queryParams, NULL, 'kred_ord_id')) $kred_ord_id = $queryParams['kred_ord_id'];
if(if_isset($queryParams, NULL, 'konto_id')) $kred_ord_id = $queryParams['konto_id'];
if(if_isset($queryParams, NULL, 'sag_id')) $sag_id = $queryParams['sag_id'];
if(if_isset($queryParams, NULL, 'afd_lager')) $afd_lager = $queryParams['afd_lager'];
if(if_isset($queryParams, NULL, 'kontonr')) $kontonr = $queryParams['kontonr'];
if(if_isset($queryParams, NULL, 'firmanavn')) $firmanavn = $queryParams['firmanavn'];
if(if_isset($queryParams, NULL, 'addr1')) $addr1 = $queryParams['addr1'];
if(if_isset($queryParams, NULL, 'addr2')) $addr2 = $queryParams['addr2'];
if(if_isset($queryParams, NULL, 'postnr')) $postnr = $queryParams['postnr'];
if(if_isset($queryParams, NULL, 'bynavn')) $bynavn = $queryParams['bynavn'];
if(if_isset($queryParams, NULL, 'land')) $land = $queryParams['land'];
if(if_isset($queryParams, NULL, 'kontakt')) $kontakt = $queryParams['kontakt'];
if(if_isset($queryParams, NULL, 'kundeordnr')) $kundeordnr = $queryParams['kundeordnr'];
if(if_isset($queryParams, NULL, 'cvrnr')) $cvrnr = $queryParams['cvrnr'];
if(if_isset($queryParams, NULL, 'ean')) $ean = $queryParams['ean'];
if(if_isset($queryParams, NULL, 'phone')) $phone = $queryParams['phone'];
if(if_isset($queryParams, NULL, 'institution')) $institution = $queryParams['institution'];
if(if_isset($queryParams, NULL, 'email')) $email = $queryParams['email'];
if(if_isset($queryParams, NULL, 'udskriv_til')) $udskriv_til = $queryParams['udskriv_til'];
if(if_isset($queryParams, NULL, 'momssats')) $momssats = $queryParams['momssats'];
if(if_isset($queryParams, NULL, 'mail_bilag')) $mail_bilag = $queryParams['mail_bilag'];
if(if_isset($queryParams, NULL, 'ordredato')) $ordredato = $queryParams['ordredato'];
if(if_isset($queryParams, NULL, 'betalingsbet')) $betalingsbet = $queryParams['betalingsbet'];
if(if_isset($queryParams, NULL, 'betalingsdage')) $betalingsdage = $queryParams['betalingsdage'];
if(if_isset($queryParams, NULL, 'ny_valuta')) $ny_valuta = $queryParams['ny_valuta'];
if(if_isset($queryParams, NULL, 'ref')) $ref = $queryParams['ref'];
if(if_isset($queryParams, NULL, 'felt_1')) $felt_1 = $queryParams['felt_1'];
if(if_isset($queryParams, NULL, 'felt_2')) $felt_2 = $queryParams['felt_2'];
if(if_isset($queryParams, NULL, 'felt_3')) $felt_3 = $queryParams['felt_3'];
if(if_isset($queryParams, NULL, 'felt_4')) $felt_4 = $queryParams['felt_4'];
if(if_isset($queryParams, NULL, 'felt_5')) $felt_5 = $queryParams['felt_5'];
if(if_isset($queryParams, NULL, 'lev_navn')) $lev_navn = $queryParams['lev_navn'];
if(if_isset($queryParams, NULL, 'lev_addr1')) $lev_addr1 = $queryParams['lev_addr1'];
if(if_isset($queryParams, NULL, 'lev_addr2')) $lev_addr2 = $queryParams['lev_addr2'];
if(if_isset($queryParams, NULL, 'lev_postnr')) $lev_postnr = $queryParams['lev_postnr'];
if(if_isset($queryParams, NULL, 'lev_bynavn')) $lev_bynavn = $queryParams['lev_bynavn'];
if(if_isset($queryParams, NULL, 'lev_kontakt')) $lev_kontakt = $queryParams['lev_kontakt'];
if(if_isset($queryParams, NULL, 'linjeantal')) $linjeantal = $queryParams['linjeantal'];
if(if_isset($queryParams, NULL, 'lagervarer')) $lagervarer = $queryParams['lagervarer'];
if(if_isset($queryParams, NULL, 'fokus')) $fokus = $queryParams['fokus'];
if(if_isset($queryParams, NULL, 'orderNoteText')) $orderNoteText = $queryParams['orderNoteText'];
if(if_isset($queryParams, NULL, 'valutakurs')) $valutakurs = $queryParams['valutakurs'];
if(if_isset($queryParams, NULL, 'option')) $option = $queryParams['option'];

    if (isset($queryParams['lookUp']) && ($queryParams['lookUp'] == 'Lookup') || $queryParams['lookUp'] == 'Opslag'){
        $b_submit = 'Opslag';
    }
}

#+++++++++++++++++++++++++++++


/*
$swap_account = if_isset($_GET['swap_account']);
if ($swap_account) {
	$art=if_isset($_GET['art']);
	$id=if_isset($_GET['id']);
	$fokus='kontonr';
	$b_submit='Opslag';
}
*/

  if ($swap_account || strstr($b_submit,'Opslag') || strstr($b_submit,'Gem')&&(!$id)) {
   
    if (!$id && ($fokus=='kontakt' || $fokus=='kontonr' || $fokus=='firmanavn' || $fokus=='addr1' || $fokus=='addr2' || $fokus=='postnr' || $fokus=='bynavn' || $fokus=='land' || $fokus=='cvrnr' || $fokus=='ean' || $fokus=='betalingsdage')) {
      kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$kontakt,$email,$cvrnr,$ean,$betalingsbet,$betalingsdage);
    } elseif ((strstr($fokus,'kontonr'))&&(!$status || $hurtigfakt || $swap_account)) {
      kontoopslag($art,$sort,$fokus,$id,'','','','','','','','','','','','','');
    // }elseif(!$hurtigfakt){
	}elseif((strstr($fokus,'kontonr')) && ($status >= 1 && $status <= 3) && $art == 'DO'){
		kontoopslag($art,$sort,$fokus,$id,'','','','','','','','','','','','','');
		// echo "<script>
		// 		alert('At least quick invoice is required Go to: System->Settings->Miscellaneous->Order related choices->Use fast invoices');
		// 		window.location.href = '" . ($_SERVER['HTTP_REFERER'] ?? 'index.php') . "';
		// 	</script>";
		// 	exit;
	}else{
		error_log("sortxxxxxxxxxxx: $sort...........fokus: $fokus and artmmmmmmmmm: $art, id: $id, status: $status"); 
	}
  
/*    
    if ((strstr($fokus,'firmanavn'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
    if ((strstr($fokus,'addr1'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
    if ((strstr($fokus,'addr2'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
    if ((strstr($fokus,'postnr'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
    if ((strstr($fokus,'bynavn'))&&(!$id)) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
*/
    if ((strstr($fokus,'lev_navn'))&&($id)) kontoopslag("$art","$sort","$fokus","$id","$lev_navn",'','','','','');
#    elseif (strstr($fokus,'kontakt')) kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt);
	$q=db_select("select box2 from grupper where art = 'PL' AND box12='Yes'",__FILE__ . " linje " . __LINE__);
	if($s = db_fetch_array($q)){
		$option= if_isset($option,NULL).':'.$s[0];
	}	

    if ((strstr($fokus,'vare'))&&($art!='DK')) vareopslag($art,$sort,'varenr',$id,$vis_kost,$ref,$varenr[0],$Urlc,$option);
    if (strstr($fokus,'besk') && $beskrivelse[0] && $art!='DK') vareopslag($art,$sort,'beskrivelse',$id,$vis_kost,$ref,$beskrivelse[0]);
    if (strstr($fokus,'besk')) tekstopslag($sort,$id);
    if ((strstr($fokus,'kontakt'))&&($id)) ansatopslag($sort,$fokus,$id,$vis,$kontakt);
    
  }
  elseif ($b_submit && !$kontonr && $id) {
    
    kontoopslag($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$kontakt,$email,$cvrnr,$ean,$betalingsbet,$betalingsdage);
    exit;
  }

########################## del_ordre  - SKAL VAERE PLACERET FOER "FAKTURER" ################################
	if ($b_submit=='del_ordre') {
		$sum=0; $moms=0;
		$ny_sum=0; $ny_moms=0;
		transaktion("begin");
		$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__)); #20210312
    $qtxt = "insert into ordrer "; 
    $qtxt.= "(ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,kundeordnr,";
    $qtxt.= "betalingsdage,betalingsbet,cvrnr,ean,institution,notes,art,ordredate,momssats,tidspkt,ref,status,";
    $qtxt.= "lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,valuta,projekt,sprog,email,mail_fakt,";
    $qtxt.= "phone,pbs,afd,restordre,omvbet,felt_1,felt_2,felt_3,felt_4,felt_5) ";
    $qtxt.= "values "; 
    $qtxt.= "('$r[ordrenr]','$r[konto_id]','$r[kontonr]','".db_escape_string($r['firmanavn'])."',";
    $qtxt.= "'".db_escape_string($r['addr1'])."','".db_escape_string($r['addr2'])."',";
    $qtxt.= "'".db_escape_string($r['postnr'])."','".db_escape_string($r['bynavn'])."',";
    $qtxt.= "'".db_escape_string($r['land'])."','".db_escape_string($r['kontakt'])."',";
    $qtxt.= "'".db_escape_string($r['kundeordnr'])."','$r[betalingsdage]','$r[betalingsbet]',";
    $qtxt.= "'".db_escape_string($r['cvrnr'])."','".db_escape_string($r['ean'])."',";
    $qtxt.= "'".db_escape_string($r['institution'])."','".db_escape_string($r['notes'])."','$r[art]',";
    $qtxt.= "'$r[ordredate]','$r[momssats]','$r[tidspkt]','".db_escape_string($r['ref'])."','$r[status]',";
    $qtxt.= "'".db_escape_string($r['lev_navn'])."','".db_escape_string($r['lev_addr1'])."',";
    $qtxt.= "'".db_escape_string($r['lev_addr2'])."','".db_escape_string($r['lev_postnr'])."',";
    $qtxt.= "'".db_escape_string($r['lev_bynavn'])."','".db_escape_string($r['lev_kontakt'])."',";
    $qtxt.= "'$r[valuta]','$r[projekt]','".db_escape_string($r['sprog'])."',";
    $qtxt.= "'".db_escape_string($r['email'])."','$r[mail_fakt]','$r[phone]','$r[pbs]','$r[afd]','1','$r[omvbet]',";
    $qtxt.= "'".db_escape_string($r['felt_1'])."','".db_escape_string($r['felt_2'])."',";
    $qtxt.= "'".db_escape_string($r['felt_3'])."','".db_escape_string($r['felt_4'])."',";
    $qtxt.= "'".db_escape_string($r['felt_5'])."')";
    $r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__)); #20210312
    db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$q = db_select("select id from ordrer where ordrenr=$ordrenr and art='$art' and tidspkt='$tidspkt' order by id desc",__FILE__ . " linje " . __LINE__);
    if ($r = db_fetch_array($q)) $ny_id=$r['id'];
		for($x=1; $x<=$linjeantal; $x++) {
			if ($vare_id[$x]){
#        if ($r1=db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[$x]'"))) {
#          if ($r2=db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'"))) {$momsfri[$x] = $r2['box7'];}
#        }
				$r3=db_fetch_array(db_select("select momsfri,leveret,samlevare,kostpris from ordrelinjer where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__));
        $ny_antal=$antal[$x]-$r3['leveret']; 
        $antal[$x]=$r3['leveret'];
				$sum=$sum+$antal[$x]*$pris[$x];
				$ny_sum=$ny_sum+$ny_antal*$pris[$x];
        if ($r3['momsfri']!='on') {
					$moms=$moms+$antal[$x]*$pris[$x]/100*$momssats;
					$ny_moms=$ny_moms+$ny_antal*$pris[$x]/100*$momssats;
				}
				if ($ny_antal) {
					if ($antal[$x]) {
             $qtxt = "insert into ordrelinjer ";
            $qtxt.= "(ordre_id,posnr,varenr,vare_id,beskrivelse,enhed,antal,pris,kostpris,rabat,";
            $qtxt.= "lev_varenr,serienr,kred_linje_id,momsfri,samlevare,projekt) ";
            $qtxt.= "values ";
            $qtxt.= "('$ny_id','$posnr_ny[$x]','$varenr[$x]','$vare_id[$x]','$beskrivelse[$x]','$enhed[$x]',";
            $qtxt.= "$ny_antal,'$pris[$x]','$kostpris[$x]','$rabat[$x]','$lev_varenr[$x]','$serienr[$x]',";
            $qtxt.= "'$linje_id[$x]','$momsfri[$x]','$r3[samlevare]','$projekt[$x]')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
            $qtxt = "update ordrelinjer set antal='$antal[$x]' where id=$linje_id[$x]";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
          } else {
						db_modify("update ordrelinjer set ordre_id='$ny_id' where id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
					}
				}
			}
			else db_modify("insert into ordrelinjer (ordre_id,posnr,beskrivelse) values ('$ny_id','$posnr_ny[$x]','$beskrivelse[$x]')",__FILE__ . " linje " . __LINE__);
		}
		db_modify("update ordrer set sum = '$sum',moms = '$moms',status='2' where id='$id'",__FILE__ . " linje " . __LINE__);
		db_modify("update ordrer set sum = '$ny_sum',moms = '$ny_moms',hvem = '',tidspkt= '' where id='$ny_id'",__FILE__ . " linje " . __LINE__);

#exit;
         $alert = findtekst('1845|Der er oprettet en ny ordre med samme ordrenr', $sprog_id);
		print "<BODY onLoad=\"javascript:alert('$alert')\">\n";
		#$b_submit='doInvoice';
		transaktion("commit");
	}
########################## FAKTURER   - SKAL VAERE PLACERET EFTER "del_ordre" ################################
	if ($b_submit=='doInvoice' && $status<3) {
		if (!$fakturadate) {
			$fakturadate=date("Y-m-d");
			db_modify("update ordrer set fakturadate='$fakturadate' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}	
		if($udskriv_til=="oioubl") {
			if($art=="DO") $oioubl='faktura';
			else $oioubl='kreditnota';
		} else $oioubl=NULL;
		if($udskriv_til=="Digitalt") {
			if($art=="DO") $digital='faktura';
			else $digital='kreditnota';
		} else $digital=NULL;
		if ($hurtigfakt=='on') {
			#20150424
			$alert = findtekst('1846|Du kan ikke fakturere uden ordrelinjer', $sprog_id);
			$qtxt = "select count(id) as linjeantal from ordrelinjer ";
      $qtxt.= "where ordre_id = '$id' and posnr >= '0'"; #20240703
			$row = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if (!$row['linjeantal']) {
				print "<BODY onLoad=\"javascript:alert('$alert')\">\n";
			} elseif ($row['linjeantal']==$linjeantal) { #20130506
				for ($x=1;$x<=$linjeantal;$x++) {
					$tmp=$linje_id[$x]*-1;
					if ($linje_id[$x] && $leveres[$x] && $folgevare[$x]>0 && !in_array($tmp,$folgevare)) {
						if($r=db_fetch_array(db_select("select varenr from varer where id = '$folgevare[$x]'",__FILE__ . " linje " . __LINE__))) {
							opret_ordrelinje("$id","$folgevare[$x]","$r[varenr]","$antal[$x]","","","","100","$art","$momsfri[0]","$posnr_ny[$x]","0","$incl_moms","$kdo[$x]","","$kopi","0","","",$lager[$x],__LINE__);#2021116
							$r=db_fetch_array(db_select("select max(id) as id from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__));
							db_modify("update ordrelinjer set leveres='$leveres[$x]',folgevare='$tmp' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
						}
					}
				}
				print "<meta http-equiv=\"refresh\" content=\"0;URL=levering.php?id=$id&hurtigfakt=on&mail_fakt=&pbs=$pbs&oioubl=$oioubl\">\n";
			} 
		} elseif ( $bogfor!=0 ) {
			$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
			if (!$row = db_fetch_array($query)) Print "Du kan ikke fakturere uden ordrelinjer";
			else {
					print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?id=$id&mail_fakt=$mail_fakt&pbs=$pbs&digital=$digital&oioubl=$oioubl\">\n";
			}	
		}
	}
  ############################ LEVER ################################

	if (strstr($b_submit,'Lev') && $bogfor!=0 && $status<3) {
		$x=0;
/*
		$q=db_select("select * from ordrelinjer where ordre_id = '$id' order by posnr,id",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$antal[$x]      = $r['antal'];
			$leveres[$x]    = $r['leveres'];
			$folgevare[$x]  = $r['folgevare'];
			$vare_id[$x]    = $r['vare_id'];
			$saet[$x]       = $r['saet'];
			$fast_db[$x]    = $r['fast_db'];
			$lev_varenr[$x] = $r['lev_varenr'];
		}
*/		
		$q=db_select("select * from ordrelinjer where ordre_id = '$id' order by posnr,id",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$linje_id[$x]   = $r['id'];
			$posnr[$x]      = $r['posnr'];
			$antal[$x]      = $r['antal'];
			$leveres[$x]    = $r['leveres'];
			$folgevare[$x]  = $r['folgevare'];
			$vare_id[$x]    = $r['vare_id'];
			$saet[$x]       = $r['saet'];
			$fast_db[$x]    = $r['fast_db'];
			$lev_varenr[$x] = $r['lev_varenr'];
		}
		$linjeantal=$x;
		for ($x=1;$x<=$linjeantal;$x++) {
		$tmp=$linje_id[$x]*-1;
		
			if ($linje_id[$x] && $leveres[$x] && $folgevare[$x]>0 && !in_array($tmp,$folgevare)) {
				if($r=db_fetch_array(db_select("select varenr from varer where id = '$folgevare[$x]'",__FILE__ . " linje " . __LINE__))) {
					$svar=opret_ordrelinje("$id","$folgevare[$x]","$r[varenr]","$antal[$x]","","","","100","$art","$momsfri[0]","$posnr_ny[$x]","0","$incl_moms","","","0",$saet[$x],$fast_db[$x],$lev_varenr[$x],$lager[$x],__LINE__);
					$r=db_fetch_array(db_select("select max(id) as id from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__));
					db_modify("update ordrelinjer set leveres='$leveres[$x]',folgevare='$tmp' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
				}
			}
		}
		$alert = findtekst('1847|Du kan ikke levere uden ordrelinjer', $sprog_id);
		if (!$x) print "<BODY onLoad=\"javascript:alert('$alert')\">\n";
		else {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=levering.php?id=$id\">\n";
		}
	}
	$meta_returside = "<meta http-equiv=\"refresh\" content=\"3600;URL=$returside\">\n"; #20142403-2
	//print "<meta http-equiv=\"refresh\" content=\"3600;URL=$returside\">\n";

	if ($id && $brugsamletpris && $rabatvare_nr && db_fetch_array(db_select("select lev_varenr from ordrelinjer where ordre_id = '$id' and varenr = '$rabatvare_nr'",__FILE__ . " linje " . __LINE__))) {
		$rvnr=$rabatvare_nr;
		$rvid=$rabatvare_id;
	}

	if ($status < 3 && $vis_saet && $id) { #20170318
		$saets=array();
		$x=0;
		$qtxt="select id,saet,samlevare,posnr from ordrelinjer where ordre_id=$id and saet>0 order by saet,samlevare,id";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if (in_array($r['saet'],$saets)) {
				$posnr[$x]++;
				db_modify("update ordrelinjer set posnr='$posnr[$x]' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
			} else {
				$x++;
				$saets[$x]=$r['saet'];
				$posnr[$x]=$r['posnr'];
			}
		}
	}
	if ($status < 3 && isset($_GET['updateOrderCost']) && $_GET['updateOrderCost']) {
		include ('orderIncludes/updateOrderCost.php');
		updateOrderCost('$id');
	}

###########################################################################
ordreside($id,$regnskab);
function ordreside($id,$regnskab) {
#  print "<!--Function ordreside start-->";
	global $afd_lager,$art;
	global $b_submit,$bgcolor,$bgcolor5,$bogfor,$bruger_id,$brugsamletpris,$brugervalg,$brugsamletpris;
	global $baseCurrency,$brugernavn,$bruttosum,$bruttosaetsum;
	global $charset;
	global $db,$db_encode,$db_id,$difkto;
	global $fokus,$fakturadate,$fakturadato;
	global $genfakt,$gls_ctId,$gls_user,$gls_pass,$gls_id;
	global $hurtigfakt;
	global $incl_moms;
	global $lagerantal,$lagernavn,$lagernr,$localPrint;
	global $oio,$oioubl,$omkunde,$ordresum;
	global $popup,$procentfakt,$procenttillag,$procentvare;
	global $regnaar,$returside,$rvid,$rvnr;
	global $samlet_pris,$samlet_rabat,$samlet_rabatpct,$showLocalPrint,$sprog_id,$sprog,$svnr;
	global $txt370,$txt283;
	global $varenr,$vis_projekt,$vis_saet; #20150306 varenr
	global $width;
	global $menu;
	global $fast_db;
	

	if ($menu=='T') {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
#  } else {
	}
	
	$dkb=0;#dækningsbidrag;
		
	$dbi=$vare_id=array();
	$betalt=$disabled=$dfm_user=$landekode=NULL;
  $mail_bilag=$std_txt_title=$tekst2=$phone=$temp=$value=$std_txt=NULL;
	$dkprocent = $kobs_ordre_pris = $tekstcolor = $tGrossWeight = 0;
	$leveret = array();
	$beskrivelse[0] = NULL;
	$kobs_ordre_id[0] = $lager[0] = $pris[0] = 0;
	if (!isset ($masterprojekt)) $masterprojekt = NULL;
	
	$id = (int)$id; //ensure Id is numeric first.
	$id*=1;

	$r=db_fetch_array(db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	
	$sag_id = if_isset($r,NULL,'sag_id')*1; #20210719
	if ($sag_id) {
		$returside=urlencode("../sager/sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id");
	}
	if (!$returside) {
		if ($popup) $returside="../includes/luk.php?id=$id&tabel=ordrer";
		else $returside="ordreliste.php";
	}
	$addr1=$addr2=NULL;
	$batchvare=$betalingsbet=$betalingsdage=$bynavn=NULL;
	$debitorkort=$dbsum=$dkantal=NULL;
	$cvrnr=$ean=$email=NULL;
	$felt_1=$felt_2=$felt_3=$felt_4=$felt_5=$firmanavn=NULL;
	$institution=NULL;
	$kontakt=$kontakt_tlf=$konto_id=$kontonr=$kostsum=$kred_ord_id=$krediteret=$kundeordnr=NULL;
	$land=$levdato=$levdiff=$lev_addr1=$lev_addr2=$lev_bynavn=$lev_kontakt=$lev_max=$lev_navn=$lev_postnr=$lev_pbs=$lev_pbs_nr=$linjebg=NULL;
	$mail_fakt=$momssats=$momssum=NULL;
	$oio_fakt=$ordredato=$ordrenr=NULL;
	$pbs_nr=$phone=$postnr=$prev_id=NULL;
	$reserveret=NULL;
	$status=NULL;
	$tlf=$tidl_lev=NULL;
	$udskriv_til=NULL;
	$valutakurs=$vis_lev_addr=$betalings_id=NULL;
	$y=NULL;
	$ko_ant=$momsfri=array();
	if (!$id) $fokus='kontonr';
	if ($id) {
		$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$konto_id = if_isset($row, NULL, 'konto_id') * 1;
		$kontonr = if_isset($row, NULL, 'kontonr');
		$firmanavn = if_isset($row, NULL, 'firmanavn');
		$addr1 = if_isset($row, NULL, 'addr1');
		$addr2 = if_isset($row, NULL, 'addr2');
		$postnr = if_isset($row, NULL, 'postnr');
		$bynavn = if_isset($row, NULL, 'bynavn');
		$land = if_isset($row, NULL, 'land');
		$kontakt = if_isset($row, NULL, 'kontakt');
		$kontakt_tlf = if_isset($row, NULL, 'kontakt_tlf');
		$kundeordnr = if_isset($row, NULL, 'kundeordnr');
		$lev_navn = if_isset($row, NULL, 'lev_navn');
		$lev_addr1 = if_isset($row, NULL, 'lev_addr1');
		$lev_addr2 = if_isset($row, NULL, 'lev_addr2');
		$lev_postnr = if_isset($row, NULL, 'lev_postnr');
		$lev_bynavn = if_isset($row, NULL, 'lev_bynavn');
		$lev_land = if_isset($row, NULL, 'lev_land');
		$lev_kontakt = if_isset($row, NULL, 'lev_kontakt');
		$lev_email = if_isset($row, NULL, 'lev_email');
		$vis_lev_addr = if_isset($row, NULL, 'vis_lev_addr');
		$felt_1 = if_isset($row, NULL, 'felt_1');
		$felt_2 = if_isset($row, NULL, 'felt_2');
		$felt_3 = if_isset($row, NULL, 'felt_3');
		$felt_4 = if_isset($row, NULL, 'felt_4');
		$felt_5 = if_isset($row, NULL, 'felt_5');

		$cvrnr = trim(if_isset($row, NULL, 'cvrnr'));
		$ean = if_isset($row, NULL, 'ean');
		$institution = if_isset($row, NULL, 'institution');
		$email = if_isset($row, NULL, 'email');
		$mail_fakt = if_isset($row, NULL, 'mail_fakt');
		$phone = if_isset($row, NULL, 'phone');
		$udskriv_til = if_isset($row, NULL, 'udskriv_til');
		$mail_bilag = if_isset($row, NULL, 'mail_bilag'); // #20131122 tilføj $mail_bilag til visning
		$betalingsbet = trim(if_isset($row, NULL, 'betalingsbet'));
		$betalingsdage = if_isset($row, NULL, 'betalingsdage');
		$betalings_id = if_isset($row, NULL, 'betalings_id');
		$valuta = if_isset($row, NULL, 'valuta');
		$valutakurs = if_isset($row, NULL, 'valutakurs') * 1;
		if (!$valutakurs) $valutakurs=100;
		$projekt[0] = if_isset($row, NULL, 'projekt');
		$formularsprog = if_isset($row, NULL, 'sprog');
		$pbs = if_isset($row, NULL, 'pbs');
		$afd = if_isset($row, NULL, 'afd');
		$sum = if_isset($row, NULL, 'sum');
		$moms = if_isset($row, NULL, 'moms');
		$ref = trim(if_isset($row, NULL, 'ref'));
		$fakturanr = if_isset($row, NULL, 'fakturanr');
		$lev_adr = if_isset($row, NULL, 'lev_adr');
		$ordrenr = if_isset($row, NULL, 'ordrenr');
		$kred_ord_id = if_isset($row, NULL, 'kred_ord_id') * 1;
		$restordre = if_isset($row, NULL, 'restordre');
    	$digitalStatus = if_isset($row,NULL,'digital_status');
		$ordredate = if_isset($row, null, 'ordredate') ?? date("y-m-d");

		$ordredato=dkdato($ordredate);
		if (if_isset($row, NULL, 'levdate')) {
			$levdato = dkdato(if_isset($row, NULL, 'levdate'));
		}

		if (if_isset($row, NULL, 'fakturadate')) {
			$fakturadate = if_isset($row, NULL, 'fakturadate');
			$fakturadato = dkdato(if_isset($row, NULL, 'fakturadate'));
		}

/*
			$gls_username = "2080050875";
			$gls_pass = "50875";
			$gls_id = "2080050875";
*/			
		// Gls label setup
		if (isset($_REQUEST['gls_go'])){  // BZ
			db_modify ("update ordrer set gls_label = true where id = '$id'",__FILE__ . " linje " . __LINE__);
			$tGrossWeight=$_POST['tGrossWeight']*1;
			$qtxt="select var_name,var_value from settings where var_grp='GLS'";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				if ($r['var_name']=='gls_id')   $gls_id   = $r['var_value'];
				if ($r['var_name']=='gls_user') $gls_user = $r['var_value'];
				if ($r['var_name']=='gls_pass') $gls_pass = $r['var_value'];
				if ($r['var_name']=='gls_ctId') $gls_ctId = $r['var_value'];
			}
			gls_label($gls_user,$gls_pass,$gls_id,$gls_ctId,$ordrenr,$kundeordnr,$firmanavn,$addr1,$postnr,$bynavn,$land,$email,$lev_navn,$lev_addr1,$lev_postnr,$lev_bynavn,$lev_land,$kontakt,$tGrossWeight);
		}
		// Fedex label setup
		if (isset($_REQUEST['dfm_go'])){   
			db_modify ("update ordrer set fedex_label = true where id = '$id'",__FILE__ . " linje " . __LINE__);
			$tGrossWeight=1;
			$qtxt="select var_name,var_value from settings where var_grp='GLS'";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if (!isset($r['var_name'])) continue;  
				$var_name = $r['var_name'];            
				$var_value = if_isset($r, NULL, 'var_value');  

				switch ($var_name) {
					case 'dfm_id':              $dfm_id              = $var_value; break;
					case 'dfm_user':            $dfm_user            = $var_value; break;
					case 'dfm_pass':            $dfm_pass            = $var_value; break;
					case 'dfm_agree':           $dfm_agree           = $var_value; break;
					case 'dfm_hub':             $dfm_hub             = $var_value; break;
					case 'dfm_ship':            $dfm_ship            = $var_value; break;
					case 'dfm_good':            $dfm_good            = $var_value; break;
					case 'dfm_pay':             $dfm_pay             = $var_value; break;
					case 'dfm_url':             $dfm_url             = $var_value; break;
					case 'dfm_delrem':          $dfm_delrem          = $var_value; break;
					case 'dfm_gooddes':         $dfm_gooddes         = $var_value; break;
					case 'dfm_sercode':         $dfm_sercode         = $var_value; break;
					case 'dfm_pickup_addr':     $dfm_pickup_addr     = $var_value; break;
					case 'dfm_pickup_name1':    $dfm_pickup_name1    = $var_value; break;
					case 'dfm_pickup_name2':    $dfm_pickup_name2    = $var_value; break;
					case 'dfm_pickup_street1':  $dfm_pickup_street1  = $var_value; break;
					case 'dfm_pickup_street2':  $dfm_pickup_street2  = $var_value; break;
					case 'dfm_pickup_town':     $dfm_pickup_town     = $var_value; break;
					case 'dfm_pickup_zipcode':  $dfm_pickup_zipcode  = $var_value; break;
				}
			}

		
			$form_prodcode = if_isset($_POST['form_prodcode']);
			$form_gooddes = if_isset($_POST['form_gooddes']);

			if (empty($form_prodcode)) { 
				$dfm_prodcode = "DayB";
			} else {
				$dfm_prodcode = $form_prodcode;
			}

			if (empty($form_gooddes)) { // Delete  this when a field is added in the dfm_go form with the same parameter
				if (empty($dfm_gooddes)) { 
					$dfm_gooddes="Beskriv godset..."; // Delete this when a field is added in the dfm_go form with the same parameter
				}
			} else {
				$dfm_gooddes = $form_gooddes;
			}
			if (empty($phone)) { #20210506
				$phone=$kontonr;
					db_modify ("update ordrer set phone = '$kontonr' where id = '$id'",__FILE__ . " linje " . __LINE__);
			}
			include "func/dfm_consignment.php";
			$dfm_go= dfm_consignment($konto_id,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$phone,$email,$kontakt,
				$lev_navn,$lev_addr1,$lev_addr2,$lev_postnr,$lev_bynavn,$lev_land,$lev_kontakt,$id,$ordrenr,$fakturanr,$kundeordnr,
				$tGrossWeight,$dfm_id,$dfm_user,$dfm_pass,$dfm_agree,$dfm_hub,$dfm_ship,$dfm_good,$dfm_pay,$dfm_url,$dfm_delrem,$dfm_gooddes,
				$dfm_prodcode,$dfm_sercode,$dfm_pickup_addr,$dfm_pickup_name1,$dfm_pickup_name2,$dfm_pickup_street1,$dfm_pickup_street2,
				$dfm_pickup_town,$dfm_pickup_zipcode);
		}

		if (if_isset($row, false, 'nextfakt')) {
			$genfakt = dkdato($row['nextfakt']);
		}

		$momssats = if_isset($row, 0, 'momssats');
		$procenttillag = if_isset($row, 0, 'procenttillag') * 1;
		$status = if_isset($row, 0, 'status');
		$kontonr = if_isset($row, '', 'kontonr');
		$art = if_isset($row, '', 'art');
		$mail_subj = if_isset($row, '', 'mail_subj');
		$mail_text = str_replace("<br>", "\n", if_isset($row, '', 'mail_text'));
		$dokument = if_isset($row, '', 'dokument');
		$sag_id = if_isset($row, 0, 'sag_id') * 1;
		$sagsnr = if_isset($row, 0, 'sagsnr') * 1;
		$tilbudnr = if_isset($row, '', 'tilbudnr');
		$datotid = if_isset($row, '', 'datotid');
		$nr = if_isset($row, 0, 'nr') * 1;
		if (!$returside && if_isset($row, false, 'returside')) {
			$returside = $row['returside'];
		}
		$omkunde = if_isset($row, '', 'omvbet') ? 'on' : '';
		$betalt = if_isset($row, 0, 'betalt');


		if ($udskriv_til=='ingen' && $status >= '3') $udskriv_til="PDF";
#    if ($brugernavn && !$ref) $ref=$brugernavn; #flyttet til efter 'ikke faktureret'

#    if ($returside=='../includes/luk.php' && !$popup) $returside='';  
		$q=db_select("select art,pbs_nr,pbs from adresser where art = 'S' or id = '$konto_id'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['art']=='S') {
				$lev_pbs_nr=$r['pbs_nr'];
				$lev_pbs=$r['pbs'];
			} else $pbs_nr=$r['pbs_nr'];
		}
		$x=0;
		$krediteret='';
		$query = db_select("select id,ordrenr from ordrer where kred_ord_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($row2 = db_fetch_array($query)) {
			$x++;
			if ($x>1) {$krediteret=$krediteret.",";}
			$krediteret=$krediteret."<a href=\"ordre.php?id=$row2[id]\">$row2[ordrenr]</a>";
		}
		if ($status<3) {
			if ($fokus=='vare0') $fokus='dkan'.count($varenr); #20151019
			elseif (substr($fokus,0,4)!='dkan' && substr($fokus,0,4)!='pris') $fokus='vare0'; #20151019
		} else $fokus='vare0';
	} else {
		$r=db_fetch_array(db_select("select ansatte.navn as ref,ansatte.afd as afd from ansatte,brugere where ansatte.id = ".nr_cast("brugere.ansat_id")." and brugere.brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__));
		$ref=if_isset($r['ref']); #20210719 error thrown here when they are not set
		$afd=if_isset($r['afd']);
	}
	$afd*=1;
	$afd_navn=NULL;
	if ($r=db_fetch_array(db_select("select beskrivelse,box1 from grupper where art = 'AFD' and kodenr = '$afd'",__FILE__ . " linje " . __LINE__))) {
		$afd_navn=$r['beskrivelse'];
		$afd_lager=$r['box1'];
	}
	$qtxt = "select * from grupper where ART = 'bilag' and (box6 ='on' or (box1 !='' and box2 !='' and box3 !=''))";
	($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$bilag=1:$bilag=0;
	$qtxt = "select * from grupper where art = 'DIV' and kodenr = '2' and box7='on'";
	if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$url="jobkort.php?returside=ordre.php&konto_id=$konto_id&ordre_id=$id";
		$jobkort = "<a href=$url style=\"text-decoration:none\">";
		$jobkort.= "<input type=\"button\" style=\"width:125px\" value=\"".lcfirst(findtekst('1098|Jobkort', $sprog_id))."\" ";
		$jobkort.= "onClick=\"window.navigate('$url')\"></a>"; #20210630
	} else $jobkort=NULL;
		$url="debitorkort.php?returside=ordre.php&konto_id=$konto_id&ordre_id=$id";
		$debitorkort = "<a href=$url style=\"text-decoration:none\">";
		$debitorkort.= "<input type=\"button\" id='debitorkort' style=\"width:125px\" value=\"".findtekst('356|Debitorkort', $sprog_id)."\" ";
		$debitorkort.= "onClick=\"window.navigate('$url')\"></a>";
	if ($status < 3 && $cvrnr && !is_numeric(substr($cvrnr,2,9))) {
		$alert = findtekst('1848|Kontroller CVR nr', $sprog_id);
		if (substr($db,0,6)=='rotary') $cvrnr=NULL;
		else alert("$alert"); #20200211
	}
	
	######### pile ########## tilfoejet 20080210
		if ($status==0) $tmp="tilbud";
		elseif($status>=3) $tmp="faktura";
		else $tmp="ordrer";

		$r=db_fetch_array(db_select("select box1 from grupper where art = 'OLV' and kodenr = '$bruger_id' and  kode='$tmp'",__FILE__ . " linje " . __LINE__));
		$ordreliste=explode(",",if_isset($r['box1']));
		$x=0; $next_id=0;
		while(isset($ordreliste[$x])) {
			if ($ordreliste[$x]==$id) {
				if (isset($ordreliste[$x-1])) $prev_id=$ordreliste[$x-1];
				else $prev_id=NULL;
				if (isset($ordreliste[$x+1])) $next_id=$ordreliste[$x+1];
				else $next_id=NULL;
			}
			$x++;
		}
######### elip ##########
$kundeordre = findtekst('1092|Kundeordre', $sprog_id);
	if ($art=='DK') {
		 
		$query = db_select("select ordrenr from ordrer where id = '$kred_ord_id'",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query);
		if ($kred_ord_id) sidehoved($id,"$returside","","","Kunde kreditnota $ordrenr (kreditering af ordre nr: <a href=\"ordre.php?id=$kred_ord_id\">$row2[ordrenr]</a>)");
		else sidehoved($id,"$returside","","","Kunde kreditnota $ordrenr");

		
	}
	elseif ($krediteret) {sidehoved($id,"$returside","","","$kundeordre $ordrenr ( krediteret p&aring; KN nr: $krediteret )");}
	else {
		if ($returside=="ordreliste.php") sidehoved($id,"$returside","","","$kundeordre $ordrenr - $temp");
		else sidehoved($id,"$returside","","","$kundeordre $ordrenr - $temp");

	}
  if (!$status)  $status=0;


	if ($status>=3) {
	print '<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>';
    $actionUrl = "ordre.php?id=$id";



	if ($b_submit == 'Kopier' || isset($_POST['copy'])) {
		$formAction = "ordre.php?sag_id=$sag_id&amp;returside=$returside"; 
	} else {
		$formAction = "ordre.php?id=$id&amp;sag_id=$sag_id&amp;returside=$returside";
	}
	print "<form name=\"ordre\" id=\"1\" action=\"$formAction\" method=\"post\">\n"; 

// print "<form name=\"ordre\" id=\"$formId\" action=\"$formAction\" method=\"post\">\n";
// print "<form name=\"ordre\" id=\"1\" action=\"ordre.php?id=$id&amp;sag_id=$sag_id&amp;returside=$returside\" method=\"post\">\n";

		print '<input type="hidden" name="dragdrop_json" id="dragdrop_json">';
		print '<input type="hidden" name="invoice_dragdrop" value="10">';

		print "<input type=\"hidden\" name=\"ordrenr\" value=\"$ordrenr\">";
		print "<input type=\"hidden\" name=\"status\" value=\"$status\">";
		print "<input type=\"hidden\" name=\"id\" value=\"$id\">";
		print "<input type=\"hidden\" name=\"art\" value=\"$art\">";
		print "<input type=\"hidden\" name=\"kred_ord_id\" value=\"$kred_ord_id\">\n";
	
		print "<input type=\"hidden\" name=\"konto_id\" value=\"$konto_id\">";
		print "<input type=\"hidden\" name=\"kontonr\" value=\"$kontonr\">";
		print "<input type=\"hidden\" name=\"firmanavn\" value=\"$firmanavn\">";
		print "<input type=\"hidden\" name=\"addr1\" value=\"$addr1\">";
		print "<input type=\"hidden\" name=\"addr2\" value=\"$addr2\">";
		print "<input type=\"hidden\" name=\"postnr\" value=\"$postnr\">";
		print "<input type=\"hidden\" name=\"bynavn\" value=\"$bynavn\">";
		print "<input type=\"hidden\" name=\"land\" value=\"$land\">";
		print "<input type=\"hidden\" name=\"kontakt\" value=\"$kontakt\">";
		print "<input type=\"hidden\" name=\"kontakt_tlf\" value=\"$kontakt_tlf\">";
		print "<input type=\"hidden\" name=\"kundeordnr\" value=\"$kundeordnr\">\n";
		print "<input type=\"hidden\" name=\"lev_navn\" value=\"$lev_navn\">";
		print "<input type=\"hidden\" name=\"lev_addr1\" value=\"$lev_addr1\">";
		print "<input type=\"hidden\" name=\"lev_addr2\" value=\"$lev_addr2\">";
		print "<input type=\"hidden\" name=\"lev_postnr\" value=\"$lev_postnr\">";
		print "<input type=\"hidden\" name=\"lev_bynavn\" value=\"$lev_bynavn\">";
		print "<input type=\"hidden\" name=\"lev_kontakt\" value=\"$lev_kontakt\">";
		print "<input type=\"hidden\" name=\"levdato\" value=\"$levdato\">\n";
		print "<input type=\"hidden\" name=\"genfakt\" value=\"$genfakt\">";
		print "<input type=\"hidden\" name=\"cvrnr\" value=\"$cvrnr\">";
		print "<input type=\"hidden\" name=\"ean\" value=\"$ean\">";
		print "<input type=\"hidden\" name=\"institution\" value=\"$institution\">";
		print "<input type=\"hidden\" name=\"email\" value=\"$email\">";
		print "<input type=\"hidden\" name=\"phone\" value=\"$phone\">";
		print "<input type=\"hidden\" name=\"betalingsbet\" value=\"$betalingsbet\">";
		print "<input type=\"hidden\" name=\"betalingsdage\" value=\"$betalingsdage\">";
		print "<input type=\"hidden\" name=\"betalings_id\" value=\"$betalings_id\">";
		print "<input type=\"hidden\" name=\"momssats\" value=\"".dkdecimal($momssats,2)."\">";
		print "<input type=\"hidden\" name=\"procenttillag\" value=\"".dkdecimal($procenttillag,2)."\">";
		print "<input type=\"hidden\" name=\"ref\" value=\"$ref\">";
		print "<input type=\"hidden\" name=\"fakturanr\" value=\"$fakturanr\">";
		print "<input type=\"hidden\" name=\"lev_adr\" value=\"$lev_adr\">";
		print "<input type=\"hidden\" name=\"valuta\" value=\"$valuta\">";
		print "<input type=\"hidden\" name=\"valutakurs\" value=\"$valutakurs\">";
		print "<input type=\"hidden\" name=\"projekt[0]\" value=\"$projekt[0]\">"; #20130816
		print "<input type=\"hidden\" name=\"sprog\" value=\"$formularsprog\">";
		print "<input type=\"hidden\" name=\"pbs\" value=\"$pbs\">";
		print "<input type=\"hidden\" name=\"afd\" value=\"$afd\">";
		print "<input type=\"hidden\" name=\"sum\" value=\"$sum\">";
		print "<input type=\"hidden\" name=\"sag_id\" value=\"$sag_id\">";
		print "<input type=\"hidden\" name=\"sagsnr\" value=\"$sagsnr\">";
		print "<input type=\"hidden\" name=\"tilbudnr\" value=\"$tilbudnr\">";
		print "<input type=\"hidden\" name=\"datotid\" value=\"$datotid\">";
		print "<input type=\"hidden\" name=\"nr\" value=\"$nr\">";
		print "<input type=\"hidden\" name=\"returside\" value=\"$returside\">";
		print "<input type=\"hidden\" name=\"omkunde\" value=\"$omkunde\">";
		print "<input type=\"hidden\" name=\"felt_1\" value=\"$felt_1\">";
		print "<input type=\"hidden\" name=\"felt_3\" value=\"$felt_3\">";
		print "<input type=\"hidden\" name=\"felt_5\" value=\"$felt_5\">";
		if ($vis_saet) { #20181216
			print "<input type=\"hidden\" name=\"felt_2\" value=\"".dkdecimal($felt_2)."\">";
			print "<input type=\"hidden\" name=\"felt_4\" value=\"".dkdecimal($felt_4)."\">";
		} else {
			print "<input type=\"hidden\" name=\"felt_2\" value=\"$felt_2\">";
			print "<input type=\"hidden\" name=\"felt_4\" value=\"$felt_4\">";
		}
		if ($mail_fakt) $mail_fakt="checked";

##### pile ######## tilfoejet 20080210
		$alerttekst=findtekst('154|Dine ændringer er ikke blevet gemt! Tryk OK for at forlade siden uden at gemme.', $sprog_id);
		$spantekst=findtekst('198|Klik her for at skifte til forrige ordre på ordrelisten - husk at gemme eventuelle ændringer først.', $sprog_id);
		print "<table cellpadding=\"0\" cellspacing=\"12\" border=\"0\" width=\"100%\" valign = \"top\"><tbody>\n"; #Tabel 1 ->
    if ($prev_id)  print "<tr><td width=\"50%\" title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$prev_id&returside=$returside','$alerttekst')\"><img src=\"../ikoner/left.png\" style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td>\n";
		else print "<tr><td width=\"50%\"></td>\n";
		$spantekst=findtekst('199|Klik her for at skifte til næste ordre på ordrelisten - husk at gemme eventuelle ændringer først.', $sprog_id);
		if ($next_id) print "<td width=\"50%\" align=\"right\" title=\"$spantekst\"><a href=\"javascript:confirmClose('ordre.php?id=$next_id&returside=$returside','$alerttekst')\"><img src=\"../ikoner/right.png\" style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td></tr>\n";
		else print "<tr><td width=\"50%\"></td>\n";
		print "</tbody></table>\n"; # <- Tabel 1
##### pile ########
    $txt140 = findtekst('140|Adresse', $sprog_id);
    $txt666 = findtekst('666|Postnr & by', $sprog_id);
		print "<table class='dataTableForm' cellpadding='0' cellspacing='0' bordercolor='#FFFFFF' width='100%' border='1' valign = 'top'><tbody>\n"; #Tabel 2 ->
		$ordre_id=$id;
		print "<tr><td width='31%' valign='top'><table cellpadding='0' cellspacing='0' border='0' width='100%'>\n"; #Tabel 2.1 ->
		print "<tr class='tableTexting'><td width='100'><b>".findtekst('43|Kontonr.', $sprog_id)."</b></td><td width='100'>$kontonr</td></tr>\n";
		print "<tr class='tableTexting2'><td><b>".findtekst('1045|Firmanavn', $sprog_id)."</b></td><td>$firmanavn</td></tr>\n";
		print "<tr class='tableTexting'><td><b>$txt140</b></td><td>$addr1</td></tr>\n";
		print "<tr class='tableTexting2'><td></td><td>$addr2</td></tr>\n";
		print "<tr class='tableTexting'><td><b>$txt666</b></td><td>$postnr $bynavn</td></tr>\n";
		print "<tr class='tableTexting2'><td><b>".findtekst('47|Land', $sprog_id)."</b></td><td>$land</td></tr>\n";
		print "<tr class='tableTexting'><td><b>".findtekst('2530|Att.', $sprog_id)."</b></td><td>$kontakt</td></tr>\n";
		print "<tr class='tableTexting2'><td><b>".findtekst('500|Ordrenr.', $sprog_id)."</b></td><td>$kundeordnr</td></tr>\n";
		print "<tr class='tableTexting'><td><b>".findtekst('48|CVR-nr.', $sprog_id)."</b></td><td>$cvrnr</td></tr>\n";
		print "<tr class='tableTexting2'><td><b>".findtekst('379|EAN-nr.', $sprog_id)."</b></td><td>$ean</td></tr>\n";
		print "<tr class='tableTexting'><td><b>".findtekst('55|Institution', $sprog_id)."</b></td><td>$institution</td></tr>\n";
		print "</tbody></table></td>\n"; #  <- Tabel 2.1 
		print "<td width='38%' valign='top'><table cellpadding='0' cellspacing='0' border='0' width='100%'>\n"; #Tabel 2.2 ->
		$alerttekst=findtekst('1849|Husk at opdatere ved at klikke på [OK] til højre for feltet du har ændret!', $sprog_id);
		print "<tr><td><b>".findtekst('49|Tlf',$sprog_id)."</b></td>";
		print "<td><input class='inputbox' style='text-align:left;width:130px' type='text' name='phone' ";
		print "value=\"$phone\" $disabled onchange='javascript:this.form.submit()'></td>\n";
		print "<td style='width:60px'><b>".findtekst('52|E-mail', $sprog_id)."</b> </td><td style='width:130px'><input class='inputbox' type='text' name='email' style='width:130px' value='$email' onchange='javascript:this.form.submit()'></td></tr>\n";
		print "<tr><td style='color:$tekstcolor;'><b>EAN</b></td><td><input class='inputbox' type='text' style='width:130px' name='ean' value='$ean' onchange='javascript:this.form.submit()' $disabled></td>";    
		if (db_fetch_array(db_select("select distinct sprog from formularer where sprog != 'Dansk'",__FILE__ . " linje " . __LINE__))) {
			print "<td title=\"".findtekst('1468|Sprog som skal anvendes på dokumenter som tilbud, ordrer, fakturaer med videre.', $sprog_id)."\"><b>".findtekst('801|Sprog', $sprog_id)."</b></span></td>\n";
			print "<td><select class = 'inputbox' style=\"width:130px\" name=\"sprog\" onchange='this.form.submit()'>\n";
			print "<option>$formularsprog</option>\n";
			$q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) print "<option>$r[sprog]</option>\n";
			print "</SELECT></td></tr>";
		} else print "</td></tr>";

		print "<tr><td><b>".findtekst('2104|Udskriv til',$sprog_id)."</b></td>";
#		if ($email)
		#if(isset($levered)){$leveret = (int)$leveret;}else{$leveret=0;}print "<tr class='tableTexting2'><td title='".findtekst('1447|Vælg på hvilken måde dokumentet skal udskrives, gemmes eller sendes.', $sprog_id)."'><b>".findtekst('880|Udskriv', $sprog_id)." ".findtekst('904|til', $sprog_id)."</b></td>\n";

		if ($mail_fakt) $udskriv_til="email";
#    if ($oioxml) $udskriv_til="oioxml";
		if ($oioubl) $udskriv_til="oioubl";
		if ($lev_pbs_nr) {
			if ($pbs) $udskriv_til="PBS";
		}
		if (!$udskriv_til) $udskriv_til="PDF";
		print "<td><select class='inputbox' style='width:130px' name='udskriv_til' onchange='this.form.submit()'>\n";
/*
		print "<option>$udskriv_til</option>\n";
		if ($udskriv_til!="PDF") print "<option>PDF</option>\n";
		if ($udskriv_til!="email" && $email) print "<option>email</option>\n";
		if ($udskriv_til!="oioxml" && strlen($ean)==13) print "<option title=\"Kun ved fakturering/kreditering.\">oioxml</option>\n";
		print "</SELECT></td></tr>\n";
*/	
		if ($showLocalPrint && $localPrint == 'on') {
			$udskriv_til='localPrint';
			print "<option value=\"localPrint\">".findtekst('2531|Lokal printer', $sprog_id)."</option>\n";
		} else print "<option>$udskriv_til</option>\n";
		if ($udskriv_til!="PDF") print "<option>PDF</option>\n";
		if ($showLocalPrint && $localPrint != 'on') print "<option value=\"localPrint\">".findtekst('2531|Lokal printer', $sprog_id)."</option>\n";
		if ($udskriv_til!="PDF-tekst") print "<option value='PDF-tekst' title=\"".findtekst('1448|Udskrives som PDF uden baggrund', $sprog_id)."\">".findtekst('1449|PDF-tekst', $sprog_id)."</option>\n";
		if ($udskriv_til!="email") print "<option value='email' title=\"".findtekst('1450|Sendes som PDF via e-mail', $sprog_id)."\">".findtekst('652|E-mail', $sprog_id)."</option>\n";
#		if ($udskriv_til!="oioxml") print "<option title=\"Kun ved fakturering/kreditering.\">oioxml</option>\n"; #PHR 20090803
		if (($pbs || $lev_pbs_nr) && $udskriv_til!="PBS") print "<option value=\"PBS\">PBS</option>\n";
#		if ($udskriv_til!="ingen") print "<option>ingen</option>\n"; #PHR 20170501
		if ($udskriv_til!="oioubl") print "<option value='oioubl' title=\"".findtekst('1451|Kun ved fakturering/kreditering', $sprog_id)."\">oioubl</option>\n"; #PHR 20090803
		if ($udskriv_til!="Digitalt") print "<option value='Digitalt' title='".findtekst('1451|Kun ved fakturering/kreditering', $sprog_id)."'>".findtekst('2532|Digitalt', $sprog_id)."</option>\n"; #PBLM 12/06-2023
#		if ($udskriv_til!="edifakt") print "<option title=\"".findtekst('1451|Kun ved fakturering/kreditering', $sprog_id)."\">edifakt</option>\n"; #20140201
		$tmp=if_isset($pbs_nr,0);
# 20120822	
		if ($lev_pbs_nr) {
			if ($tmp == 'L') {
				if ($pbs) print "<option value=\"PBS\">PBS</option>\n";
				elseif ($tmp && $udskriv_til!="PBS" && $lev_pbs=='B') print "<option title=\"".findtekst('1452|Opkræves via betalingsservice', $sprog_id)."\">BS</option>\n";
			}
		}
		$qtxt="select * from grupper where ART = 'bilag' and (box6 ='on' or (box1 !='' and box2 !='' and box3 !=''))";
		if ($udskriv_til!="historik" && db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			print "<option title=\"".findtekst('1453|Gem en kopi og vedhæft kundens historik', $sprog_id)."\">".findtekst('907|Historik', $sprog_id)."</option>\n";
		}
		print "</SELECT></td></tr>";
		
/*
		print "<tr><td><b>Fakt som mail</td><td><input class=\"inputbox\" type=\"checkbox\" name=\"mail_fakt\" $mail_fakt></td></tr>\n";
		if ($lev_pbs_nr) {
			if ($pbs $tidspkt== "FI") $pbs_fi='checked';
			elseif ($pbs == "BS") $pbs_bs='checked';
			$title="PBS udsender FI-indbetalingskort";
			if (!$pbs_bs) {
				print "<td colspan=\"2\" title=\"$title\">Faktura via PBS (FI)</td><td title=\"$title\"><input class=\"inputbox\" type=\"checkbox\" name=\"pbs_fi\" $pbs_fi onchange=\"javascript:docChange = true;\"></td></tr>\n";
				if ($pbs_nr && !$pbs_fi) print "<tr>\n";
			}
			$title="Opkr&aelig;ves via PBS's betalingsservice";
			if ($pbs_nr && !$pbs_fi) print "<td colspan=\"2\" title=\"$title\">Opkr&aelig;v via PBS (BS)</td><td title=\"$title\"><input class=\"inputbox\" type=\"checkbox\" name=\"pbs_bs\" \"$pbs_bs\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		} else print "</tr>\n";
*/
		print "<tr class='tableTexting'><td width=\"100\"><b>".findtekst('881|Ordredato', $sprog_id)."</b></td><td width=\"100\">$ordredato</td>\n"; #20210629
		print "<td><b>&nbsp;".findtekst('886|Lev. dato', $sprog_id)."</b></td><td>$levdato</td></tr>\n";
		print "<tr class='tableTexting'><td><b>".findtekst('1094|Fakturadato', $sprog_id)."</b></td><td>$fakturadato</td>";
		if ($digitalStatus != null || $digitalStatus != "") {
			print "<td><b>&nbsp;Digital Status</b></td><td>$digitalStatus</td></tr>\n";
		} else {
			print "</tr>\n";
		}
		print "<tr class='tableTexting2'><td><b>".findtekst('82|Genfakturer', $sprog_id)."</b></td><td><input class = 'inputbox' type = 'text' name=\"genfakt\" size=\"7\" value=\"$genfakt\">&nbsp;<input class='button gray small' type=\"submit\" value=\"OK\" name=\"b_submit\"></td></tr>\n";
		print "<tr class='tableTexting'><td><b>".findtekst('935|Betaling', $sprog_id)."</b></td><td>";
		if ($betalingsbet == 'Kreditkort') print $txt283;
		elseif ($betalingsbet == 'Kontant') print $txt370;
		else print $betalingsbet;
		print "&nbsp;+&nbsp;$betalingsdage\n";
		print "</td></tr>";
		print "<tr class='tableTexting2'><td><b>".findtekst('1097|Vor ref.', $sprog_id)."</b></td><td>$ref &nbsp; $afd_navn</td></tr>\n";
		print "<tr class='tableTexting'><td><b>".findtekst('828|Fakturanr.', $sprog_id)."</b></td><td>$fakturanr</td></tr>\n";
		$tmp=dkdecimal($valutakurs,2);
		if ($valuta) print "<tr class='tableTexting2'><td><b>".findtekst('552|Valuta / Kurs', $sprog_id)."</b></td><td>$valuta / $tmp</td></tr>\n";
		if ($projekt[0]) print "<tr class='tableTexting'><td><b>".findtekst('553|Projekt', $sprog_id)."</b></td><td>$projekt[0]</td></tr>\n";
		if ($vis_saet) print "<tr class='tableTexting2'><td><b>".findtekst('931|Kasse', $sprog_id)."</b></td><td>$felt_5</td></tr>\n";
		print "</tbody></table></td>\n"; # <- Tabel 2.2
    print "<td width=\"31%\" valign = 'top'><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" valign = 'top'>\n"; #Tabel 2.3 ->
  $vis_addr = get_settings_value("vis_lev_addr", "ordrer", "off", $bruger_id);
    if (!$kontonr || $vis_lev_addr == "on") {
      $txt28 = findtekst('28|Firmanavn',$sprog_id);
      $txt140 = findtekst('140|Adresse', $sprog_id);
			print "<tr class='tableTexting'><td><b>".findtekst('554|Leveringsadresse', $sprog_id)."</b><br />&nbsp;</td><td align=\"center\">$jobkort $debitorkort</td></tr>\n";
			print "<tr><td colspan=\"2\"><b><hr></b></tr>\n";
			print "<tr class='tableTexting2'><td><b>$txt28</b></td><td colspan=\"2\">$lev_navn</td></tr>\n";
			print "<tr class='tableTexting'><td valign = 'top'><b>$txt140</b></td><td colspan=\"2\">$lev_addr1</td></tr>\n";
			print "<tr class='tableTexting2'><td></td><td colspan=\"2\">$lev_addr2</td></tr>\n";
			print "<tr class='tableTexting'><td><b>$txt666</b></td><td>$lev_postnr $lev_bynavn</td></tr>\n";
			print "<tr class='tableTexting2'><td><b>".findtekst('2530|Att.', $sprog_id)."</b></td><td colspan=\"2\">$lev_kontakt</td></tr>\n";
			print "<tr><td colspan=\"2\"><b><hr></b></tr>\n";
			print "<tr><td class='tableTexting' colspan=\"2\"><a href=\"ordre.php?id=$id&returside=$returside&vis_lev_addr=0\">".findtekst('2533|Vis ekstrafelter', $sprog_id)."</tr>\n";
		} else {
			print "<tr class='tableTexting'><td colspan = '1'><b>".findtekst('243|Ekstrafelter', $sprog_id)."</b></td>";
			print "<td align='center' colspan = '2'>$jobkort<br>$debitorkort</td></tr>\n";
			print "<tr><td colspan='2'><b><hr></b></tr>\n";
			if ($vis_saet) {
        if ($felt_1 == 'Kontant') $felt_1 = $txt370;
        if ($felt_3 == 'Kontant') $felt_3 = $txt370;
				print "<tr class='tableTexting2'><td><b>$felt_1</b></td><td align=\"right\">".dkdecimal($felt_2,2)."</td></tr>"; 
				print "<tr class='tableTexting'><td><b>$felt_3</b></td><td align=\"right\">".dkdecimal($felt_4,2)."</td></tr>"; 
			} else {
				if (findtekst('244|Ordrefelt 1', $sprog_id)) print "<tr class='tableTexting2'><td><b>".findtekst('244|Ordrefelt 1', $sprog_id)."</b></td><td>$felt_1</td></tr>\n";
				if (findtekst('245|Ordrefelt 2', $sprog_id)) print "<tr class='tableTexting'><td><b>".findtekst('245|Ordrefelt 2', $sprog_id)."</b></td><td>$felt_2</td></tr>\n";
				if (findtekst('246|Ordrefelt 3', $sprog_id)) print "<tr class='tableTexting2'><td><b>".findtekst('246|Ordrefelt 3', $sprog_id)."</b></td><td>$felt_3</td></tr>\n";
				if (findtekst('247|Ordrefelt 4', $sprog_id)) print "<tr class='tableTexting'><td><b>".findtekst('247|Ordrefelt 4', $sprog_id)."</b></td><td>$felt_4</td></tr>\n";
				if (findtekst('248|Ordrefelt 5', $sprog_id)) print "<tr class='tableTexting2'><td><b>".findtekst('248|Ordrefelt 5', $sprog_id)."</b></td><td>$felt_5</td></tr>\n";
			}
			if ($betalings_id) print "<tr class='tableTexting2'><td><b>".findtekst('2534|Betalings-ID', $sprog_id)."</b></td><td align=\"right\">&nbsp;$betalings_id</td></tr>";
			print "<tr><td colspan=\"2\"><b><hr></b></tr>\n";
			print "<tr class='tableTexting'><td colspan=\"2\"><a href=\"ordre.php?id=$id&returside=$returside&vis_lev_addr=1\">".findtekst('355|Vis leveringsadresse', $sprog_id)."</td></tr>\n";
		}
		$lev_max=0;
		$q = db_select("select lev_nr from batch_salg where ordre_id = $id",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($r['lev_nr']>$lev_max) {$lev_max=$r['lev_nr'];}
		}
		if ($lev_max > 0) {
			print "<tr class='tableTexting2'><td colspan=\"2\">&nbsp;</td></tr>\n";
			for ($levnr=1; $levnr<=$lev_max; $levnr++) {
				print "<tr class='tableTexting'><td colspan=\"2\"> <a href='udskriftsvalg.php?id=$id&valg=$levnr&formular=3'>".findtekst('576|Følgeseddel', $sprog_id)." $levnr</a></td></tr>\n";
			}
		}
		if (!$formularsprog) $formularsprog='Dansk';
		($art=='DO')?$form_nr=4:$form_nr=5;
    $qtxt = "select * from formularer where formular='$form_nr' and art='5' ";
    $qtxt.= "and lower(sprog)='".strtolower($formularsprog)."'";
    $q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($r['xa']=='1') $std_subj=$r['beskrivelse'];
			elseif ($r['xa']=='2') $std_txt_title=$r['beskrivelse'];
			else {
				if (strpos($std_txt_title,'<br>')) list($std_txt,$tmp)=explode("<br>",$std_txt_title);
				else $std_txt=$std_txt_title;
			}	
      ($mail_text)?$std_txt_title=$mail_text:$std_txt_title=str_replace("<br>","\r\n",$std_txt_title);
		}
		print "</tbody></table></td></tr>\n"; # -< Tabel 2.3
		if ($udskriv_til=='email') {
			print "<tr><td align=\"center\" colspan=\"3\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\"><tbody>\n"; #Tabel 2.4 ->
			print "<tr><td width=\"120px\">".findtekst('1476|Mail emne', $sprog_id)."</td><td><input class = 'inputbox' type = 'text' style=\"width:1000px;\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_subj\" placeholder=\"$std_subj\" value=\"$mail_subj\" onchange=\"javascript:docChange = true;\"></td>";
			if ($bilag) { 
				if ($dokument) print "<td title=\"".findtekst('1454|klik her for at åbne bilaget', $sprog_id).": $dokument\"><a href=\"../includes/bilag.php?kilde=ordrer&filnavn=$dokument&bilag_id=$id&bilag=$dokument&kilde_id=$id\"><img style=\"border: 0px solid\" alt=\"clip_m_papir\" src=\"../ikoner/paper.png\"></a></td>";
				else print "<td title=\"".findtekst('1455|klik her for at vedhæfte et bilag', $sprog_id)."\"><a href=\"../includes/bilag.php?kilde=ordrer&bilag_id=$id&bilag=$dokument&ny=ja&kilde_id=$id\"><img  style=\"border: 0px solid\" alt=\"clip\" src=\"../ikoner/clip.png\"></a></td>";
			}
			print "</tr><tr><td valign = 'top'>".findtekst('585|Mail tekst', $sprog_id)."</td><td title='$std_txt_title'>";
			if ($mail_text) {
				print "<textarea style=\"width:1000px;\" rows=\"2\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_text\" ";
				print "onchange=\"javascript:docChange = true;\">$mail_text</textarea>\n";
			} else {
        		print "<input class = 'inputbox' type = 'text' style=\"width:1000px;\" ";
				print "onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_text\" placeholder=\"$std_txt\" value=\"$mail_text\" onchange=\"javascript:docChange = true;\">";
			}
			print "</td><td><input type=\"submit\" value=\"OK\" name=\"opdat_mailtext\"></td></tr></tbody></table></td></tr>\n"; # <- Tabel 2.4
		}
		print "<tr><td align='center' colspan='3'><table class='dataTableForm' cellpadding='0' cellspacing='0' bordercolor='#FFFFFF' border='1' width='100%'><tbody>\n"; #Tabel 2.5 ->
		//print "<tr><td colspan='7'></td></tr>\n<tr>\n"; # udkommenteret 20140502
		// print "<td align='center' class='tableHeader'><b>Pos.</b></td><td align='center' class='tableHeader'><b>".findtekst('917|Varenr.', $sprog_id)."</b></td>
	
print "<td align='center' class='tableHeader' style='width:30px;'><b>⋮⋮</b></td><td align='center' class='tableHeader'><b>Pos.</b></td><td align='center' class='tableHeader'><b>".findtekst('917|Varenr.', $sprog_id)."</b></td>";
// print "<td align='center' class='tableHeader' style=''><b>⋮⋮</b></td>";
// print "<td align='center' class='tableHeader'><b>Pos.</b></td>";
// print "<td align='center' class='tableHeader'><b>".findtekst('917|Varenr.', $sprog_id)."</b></td>";
print "<td align='center' class='tableHeader'><b>".findtekst('916|Antal', $sprog_id)."</b></td>";
print "<td align='center' class='tableHeader'><b>".findtekst('945|Enhed', $sprog_id)."</b></td>";
if ($lagerantal>1) print "<td class='tableHeader' style='text-align:center'><b>".findtekst('608|Lager', $sprog_id)."</b></td>";
print "<td class='tableHeader' align='center'><b>".findtekst('914|Beskrivelse', $sprog_id)."</b></td>";
print "<td class='tableHeader' align='center'><b>".findtekst('915|Pris', $sprog_id)."</b></td>";
print "<td align='center' class='tableHeader'><b>".findtekst('428|Rabat', $sprog_id)."</b></td>";
		// print "<td align='center' class='tableHeader'><b>".findtekst('916|Antal', $sprog_id)."</b></td>";
		// print "<td align=\"center\" class='tableHeader'><b>".findtekst('945|Enhed', $sprog_id)."</b></td>";
		// if ($lagerantal>1) print "<td class='tableHeader' style=\"text-align:center\"><b>".findtekst('608|Lager', $sprog_id)."</b></td>";
		// print "<td class='tableHeader' align=\"center\"><b>".findtekst('914|Beskrivelse', $sprog_id)."</b></td><td class='tableHeader' align=\"center\"><b>".findtekst('915|Pris', $sprog_id)."</b></td><td align=\"center\" class='tableHeader'><b>".findtekst('428|Rabat', $sprog_id)."</b></td>\n";
#    print "<td align=\"center\"><b>Pos.</b></td><td align=\"center\"><b>Varenr.</b></td><td align=\"center\"><b>Antal</b></td><td align=\"center\"><b>Enhed</b></td><td align=\"center\"><b>Beskrivelse</b></td><td align=\"center\"><b>Pris</b></td><td align=\"center\"><b>Rabat</b></td>";
		if ($procentfakt) print "<td class='tableHeader' align=\"center\"><b>".findtekst('1481|Procent', $sprog_id)."</b></td>\n";
		print "<td align=\"center\" class='tableHeader'><b>".findtekst('2373|I alt', $sprog_id)."</b></td>\n";
		if (db_fetch_array(db_select("select * from grupper where art = 'PRJ' order by kodenr",__FILE__ . " linje " . __LINE__))) {
			$vis_projekt='on';
		}
		if ($vis_projekt && !$projekt[0]) print "<td class='tableHeader' align=\"center\" title=\"".findtekst('1456|Projektnummer. Vises for ordrelinjer tilknyttet et projekt.', $sprog_id)."\"><b>Proj.</b></td>\n";
#		else //print "<td></td>\n"; # udkommenteret 20140502
		if ($genfakt) print "<td class='tableHeader' align=\"center\" title=\"".findtekst('1457|Kun denne ordre: når dette felt er afmærket udelades ordrelinjen ved genfakturering.', $sprog_id)."\"><b>".findtekst('2535|KDO', $sprog_id)."</b></td>\n";
		if ($omkunde) print "<td class='tableHeader' align=\"center\" title =\"".findtekst('1458|Hvis feltet vises er kunden underlagt reglerne for omvendt betalingspligt.', $sprog_id)."\"><b>".findtekst('2536|O/B', $sprog_id)."</b></td>";
		print "</tr>\n";
		$x=0;
		$k_sum=0;
		if (!$ordre_id) $ordre_id=0;
		$kostpris[0] = 0;
		$query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr",__FILE__ . " linje " . __LINE__);
    	while ($row = db_fetch_array($query))  {
			if (($row['posnr']>0)) {
				$x++;
				$linje_id[$x]=$row['id'];
				$vare_id[$x]=$row['vare_id'];
				$posnr[$x]=$x;
				$varenr[$x]=$row['varenr'];
				$lev_varenr[$x]=$row['lev_varenr'];
				$beskrivelse[$x]=$row['beskrivelse'];
				$enhed[$x]=$row['enhed'];
				$lager[$x]=(int)$row['lager'];
				$pris[$x]=(float)$row['pris'];
				$rabat[$x]=(float)$row['rabat'];
				$rabatart[$x]=$row['rabatart'];
				$procent[$x]=$row['procent'];
				$antal[$x]=$row['antal'];
				$momsfri[$x]=$row['momsfri'];
				$varemomssats[$x]=$row['momssats'];
				$folgevare[$x]=$row['folgevare'];
				$saet[$x]=$row['saet'];
				$samlevare[$x]=$row['samlevare'];
				$fast_db[$x]=$row['fast_db'];
				$lineCost[$x] = 0;
				($row['omvbet'])?$omvbet[$x]='checked':$omvbet[$x]=''; #omvendt betalingspligt
				if (!$varemomssats[$x] || $varemomssats[$x] > $momssats) $varemomssats[$x]=$momssats;
				if ($momsfri[$x] || $omvbet[$x]) $varemomssats[$x]=0;
				$serienr[$x]=$row['serienr'];
				$kostpris[$x]=$row['kostpris'];
				if (!$samlevare[$x] || !$vis_saet) $k_sum+=$kostpris[$x]*$antal[$x]; #20190625
				$projekt[$x]=(int)$row['projekt'];
				$omvbet[$x]=$row['omvbet'];
				$lev_varenr[$x]=$row['lev_varenr'];
				($row['kdo'])?$kdo[$x]='checked':$kdo[$x]='';
				$dbi[$x]=0;
			#/*
				if (!$brugsamletpris){
					if ($vare_id[$x]) {
						list($koordpr,$koordnr,$koordant,$koordid,$koordart)=explode(chr(9),find_kostpris($vare_id[$x],$linje_id[$x]));
						$kobs_ordre_pris=explode(",",$koordpr);
						$ko_ant[$x]=count($kobs_ordre_pris);
						$kobs_ordre_id=explode(",",$koordid);
						$kobs_ordre_antal=explode(",",$koordant);
						$kobs_ordre_art=explode(",",$koordart);
						$kostpris[$x]=0;
						for($y=0;$y<$ko_ant[$x];$y++) {
              				$kostpris[$x]+=(int)$kobs_ordre_pris[$y];
							if ($valutakurs && $valutakurs!=100) $kostpris[$x]*=100/$valutakurs;
						}
						$kostpris[$x]/=$ko_ant[$x]; #20141023
						$lineCost[$x]+=$kostpris[$x]*$antal[$x];
#						$kostsum=$kostpris[$x]*$antal[$x];
					# db_modify("update ordrelinjer set kostpris='$kostpris[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
						$dbi[$x]=($pris[$x]-$kostpris[$x])*$antal[$x];
						if ($pris[$x]!=0) $dg[$x]=$dbi[$x]*100/$pris[$x];
						else $dg[$x]=0;
						$dk_db[$x]=dkdecimal($dbi[$x],2);
						$dk_dg[$x]=dkdecimal($dg[$x],2);
					}
				}
				if (($art=='DK')&&($antal[$x]<0)) $bogfor==0;
				if ($serienr[$x]) {
					$serienumre[$x]=NULL;
					$q2 = db_select("select serienr from serienr where salgslinje_id='$linje_id[$x]' order by serienr",__FILE__ . " linje " . __LINE__);
					while ($r2 = db_fetch_array($q2)) ($serienumre[$x])?$serienumre[$x].=','.$r['serienr']:$serienumre[$x]=$r['serienr'];
				}
#*/
				if ($brugsamletpris && $linje_id[$x]) db_modify("update ordrelinjer set posnr='$x' where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
		$linjeantal=$x;
		print "<input type=\"hidden\" name=\"linjeantal\" value=\"$x\">\n";
		$totalrest=0;
		for ($x=1; $x<=$linjeantal; $x++) {
			$dkantal[$x] = $dk_kostpris[$x] = $lineCost[$x] = 0;
			if (!$vare_id[$x]) {
				$query = db_select("select id from varer where varenr = '$varenr[$x]' or varenr_alias = '$varenr[$x]' or stregkode = '$varenr[$x]'",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) {$vare_id[$x]=$row['id'];}
			}
			if (($varenr[$x])&&($vare_id[$x])) {
				$row = db_fetch_array(db_select("select gruppe,provisionsfri from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
				$provisionsfri[$x]=$row['provisionsfri'];
				$row = db_fetch_array(db_select("select box8,box9 from grupper where art='VG' and kodenr='$row[gruppe]' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__));
				($row['box8']=='on')?$lagervare=1:$lagervare=0;
				($row['box9']=='on')?$batchvare=1:$batchvare=0;
				if ($rabatart[$x]=='amount') $ialt=($pris[$x]-$rabat[$x])*$antal[$x];
				else $ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				if ($provisionsfri[$x]) {
// 					if ($art=='DO') $kostsum=$ialt;
				}
#				if ($valutakurs)$kostsum*=$valutakurs/100; #20140116
				$lineCost[$x] = $kostpris[$x] * $antal[$x];
				$dbi[$x]=$ialt-$lineCost[$x];
				$ialt=afrund($ialt,3);
				if ($ialt!=0) {
					$dg[$x]=$dbi[$x]*100/$ialt;
					$dk_dg[$x]=dkdecimal($dg[$x],2);					
				}
				$dk_kostpris[$x]=dkdecimal($kostpris[$x],2);
				if ($art=='DO') {
					$dk_db[$x]=dkdecimal($dbi[$x],2);
					$dk_lineCost[$x]=dkdecimal($lineCost[$x],2);
				}	else {
					$dk_db[$x]=dkdecimal($dbi[$x]*-1,2);
					$dk_lineCost[$x]=dkdecimal($lineCost[$x]*-1,2);
				}
				$dkpris=dkdecimal($pris[$x],2);
				($rabat[$x])?$dkrabat=dkdecimal($rabat[$x],5):$dkrabat=NULL;
				$dkprocent=dkdecimal($procent[$x],2);
				if ($momsfri[$x]!='on' && !$omvbet[$x] ) {
					if($incl_moms) $dkpris=dkdecimal($pris[$x]+$pris[$x]*$varemomssats[$x]/100,2);
				}
				if ($antal[$x]) {
					if ($art=='DK') $dkantal[$x]=dkdecimal($antal[$x]*-1,2);
					else $dkantal[$x]=dkdecimal($antal[$x],2);
					if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-1);}
					if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-2);}
				} 
				if ($saet[$x] || $rvnr || $lev_varenr[$x]) {
					$dkrabat=NULL;
					if ($lev_varenr[$x]) $dkpris=NULL;
				}
			} else {$antal[$x]='';$dkpris='';$dkrabat='';$dkprocent='';$ialt='';}
			if ($rabat[$x] == 0 && $varenr[$x] == "R") {
				continue;
			}
			$title=var2str($beskrivelse[$x],$id,$posnr[$x],$varenr[$x],$dkantal[$x],$enhed[$x],$dkpris,$dkprocent,$serienr[$x],$varemomssats[$x],$rabat[$x]);
			// print "<tr  class='ordrelinje'  >\n";
			print "<tr class='ordrelinje' bgcolor='$linjebg' data-line-id='$x'>\n";

			print "<td class='drag-handle' style='cursor: move; text-align: center;'>⋮⋮</td>\n"; // Drag handle

			// print "<td class='drag-handle' style='cursor: move; text-align: center; padding: 4px;'>☰</td>\n";
			print "<input type='hidden' name='linje_id[$x]' value='$linje_id[$x]'>\n";
			print "<input type='hidden' name='posn$x' value='$posnr[$x]'><td align='right'>$posnr[$x]</td>\n";
 			print "<input type='hidden' name='vare$x' value='$varenr[$x]'><td>$varenr[$x]<br></td>\n";
      print "<input type='hidden' name='dkan$x' value='".$dkantal[$x]."'><td align='right'>$dkantal[$x]<br></td>\n";
			print "<input type='hidden' name='enhed[$x]' value='$enhed[$x]'><td align='right'>$enhed[$x]<br></td>\n";
			print "<input type='hidden' name='lager[$x]' value='$lager[$x]'>";
			if ($lagerantal>1) print "<td align='right'>$lager[$x]<br></td>\n";
			print "<input type='hidden' name='beskrivelse$x' ";
			if (strpos($beskrivelse[$x],'"')) print "value='$beskrivelse[$x]'>";
			else print "value=\"$beskrivelse[$x]\"'>";
			
			print "<td title='$title'>".str_replace("\n","<br>",$beskrivelse[$x])."&nbsp;</td>\n";
			print "<input type='hidden' name='pris$x' value='".dkdecimal($pris[$x],3)."'><td align='right' title='Kostpris $dk_kostpris[$x]'>$dkpris<br></td>\n";
#			print "<input type='hidden' name='pris$x' value='$dkpris'><td align='right'>$dkpris<br></td>\n";
			print "<input type='hidden' name='raba$x' value='".dkdecimal($rabat[$x],5)."'><td align='right'>$dkrabat<br></td>\n";
			print "<input type='hidden' name='proc$x' value='$dkprocent'>";
			if ($procentfakt) print "<td align='right'>$dkprocent<br></td>\n";
			print "<input type='hidden' name='serienr[$x]' value='$serienr[$x]'>\n";
			print "<input type='hidden' name='vare_id[$x]' value='$vare_id[$x]'>\n";
			print "<input type='hidden' name='lev_varenr[$x]' value='$lev_varenr[$x]'>\n";
			print "<input type='hidden' name='kdo[$x]' value='$kdo[$x]'>\n";
			print "<input type='hidden' name='rabatart[$x]' value='$rabatart[$x]'>\n";
			print "<input type='hidden' name='momsfri[$x]' value='$momsfri[$x]'>\n";
			print "<input type='hidden' name='varemomssats[$x]' value='$varemomssats[$x]'>\n";
			print "<input type='hidden' name='samlevare[$x]' value='$samlevare[$x]'>\n";
			print "<input type='hidden' name='folgevare[$x]' value='$folgevare[$x]'>\n";
			print "<input type='hidden' name='omvbet[$x]' value='$omvbet[$x]'>\n";
			print "<input type='hidden' name='saet[$x]' value='$saet[$x]'>\n";
			print "<input type='hidden' name='fast_db[$x]' value='$fast_db[$x]'>\n";
			if ($brugsamletpris) {
				$dbsum=$sum-$k_sum;
			} else $dbsum=$dbsum+$dbi[$x];
			if ($ialt) {
				if ($procentfakt) $ialt*=$procent[$x]/100;
				if ($varenr[$x]) {
					if ($incl_moms && !$momsfri[$x] && !$omvbet[$x]) {
						$tmp=$ialt+$ialt*$momssats/100;
					}	else $tmp=$ialt;
					if ($brugsamletpris) {
						if ($saet[$x] || $varenr[$x]==$rvnr) {
							if ($lev_varenr[$x]) list($tmp)=explode("|",$lev_varenr[$x],2);
							else $tmp='';
						} elseif ($rvnr && $incl_moms) { #20150917
							$tmp=$antal[$x]*($pris[$x]+$pris[$x]*$momssats/100);
						}
					}
					if ($art=='DK') $tmp*=-1;
					$tmp=dkdecimal($tmp,2);
				}
				print "<td align=\"right\" title=\"Kostpris $dk_lineCost[$x] * db: $dk_db[$x] * dg: $dk_dg[$x]%\">".$tmp."</td>\n";
			} else print "<td>&nbsp;</td>\n";
			print "<input type=\"hidden\" name=\"projekt[$x]\" value=\"$projekt[$x]\">\n";
			if ($vis_projekt && !$projekt[0] && $projekt[$x]) {
				$qtxt = "select beskrivelse from grupper where art = 'PROJ' and kodenr='$projekt[$x]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				print "<td align=\"right\" title=\"'$r[projekt]'\">$projekt[$x]</td>\n";
			} // else print "<td></td>"; # udkommenteret 20140502
				print $kdo[$x];
			if ($genfakt) {
				print "<td align=\"center\">";
				if ($kdo[$x]) print "<b>&radic;</b>";
				print "</td>";
			}
			if ($omvbet[$x]) $omvbet[$x]="&radic;";
			if ($omkunde) print "<td align=\"center\">$omvbet[$x]</td>\n";
			if ($kobs_ordre_id[0] && $art!='DK' && $ko_ant[$x]>=1) {
				for ($y=0; $y<$ko_ant[$x]; $y++) {
					$spantekst="K&oslash;bsordre&nbsp;$kobs_ordre_nr[$y] \n antal:&nbsp;$kobs_ordre_antal[$y]&nbsp;&aacute;&nbsp;".dkdecimal($kobs_ordre_pris[$y],2);
					if ($kobs_ordre_art[$y]=='KO') $link="../kreditor/ordre.php?id=$kobs_ordre_id[$y]";
					else $link="../debitor/ordre.php?id=$kobs_ordre_id[$y]";
					print "<td align=\"right\" onClick=\"javascript:k_ordre=window.open('$link','ordre' ,'left=10,top=10,width=800,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no');k_ordre.focus();\"onMouseOver=\"this.style.cursor = 'pointer'\" title=\"'$spantekst'\"><img src=\"../ikoner/opslag.png\"></td>\n";
				}
			}
			else //print "<td><br></td>\n"; # udkommenteret 20140502
			if ($serienr[$x]) {print "<td onClick=\"serienummer($linje_id[$x])\" onMouseOver=\"this.style.cursor = 'pointer'\" align=\"right\" title=\"".findtekst('1497|Serienummer', $sprog_id)." \"><img alt=\"".findtekst('1497|Serienummer', $sprog_id)."\" src=\"../ikoner/serienr.png\"></td>\n";}
		}
# 20150412
		if ($brugsamletpris) {
			print "<tr><td></td><td></td><td></td><td></td><td> <td></td>";
			if ($lagerantal>1) print "</td><td>";
			print "<textarea class=\"autosize inputbox ordreText comment\" id=\"comment\" rows=\"1\" cols=\"58\" ";
			print "name=\"ekstratekst\" onfocus=\"document.forms[0].fokus.value=this.name; var val=this.value; this.value=''; this.value= val;\">";
			print "</textarea></td><td colspan=\"3\"><input style=\"width:100%\" type=\"submit\" name=\"tilfoj\" value=\"".findtekst('1175|Tilføj', $sprog_id)."\"></td></tr>";
		}
#		$tmp=$momssum/100*$momssats; #ellers runder den ned ved v. 0,5 re ??
#		$moms=afrund($tmp,3);
		$kostpris[0]*=1;
		if ($b_submit=='del_ordre'||$b_submit=='doInvoice') db_modify("update ordrer set sum='$sum',kostpris='$kostpris[0]',moms='$moms' where id='$id'",__FILE__ . " linje " . __LINE__);
		if ($art=='DK') {
			$sum=$sum*-1;
			$momssum=$momssum*-1;
			$moms=$moms*-1;
		}
		
#		$tmp=$momssum/100*$momssats; #ellers runder den ned ved v. 0,5 ??
#		$moms=afrund($tmp,3);
		$ialt=$sum+$moms;
		print "<tr><td colspan='11'><br></td></tr>\n";
		print "<tr><td colspan='11'><table bordercolor='#FFFFFF' border='1' cellspacing='0' cellpadding='0' width='100%'><tbody>\n"; #Tabel 2.5.1 ->
		print "<tr class='tableTexting'>\n";
#		print "<td align=\"center\">".dkdecimal($procenttillag,2)."% tillæg ".dkdecimal($tillag,2)." </td>\n";
		print "<td width=\"20%\" align=\"center\">".findtekst('2372|Nettosum', $sprog_id).": ".dkdecimal($sum,2)."</td>\n";
		print "<td width=\"20%\" align=\"center\" title=\"".findtekst('2537|Dækningsbidrag', $sprog_id).": ".dkdecimal($dbsum,2)."\">";
		if (!$vis_saet) print findtekst('2537|Dækningsbidrag', $sprog_id).": ".dkdecimal($dbsum,2);
		print "</td>\n";
		if ($sum*1) $dg_sum=($dbsum*100/$sum);
		else $dg_sum=dkdecimal(0,2);
		print "<td width=\"20%\" align=\"center\" title=\"".findtekst('2538|Dækningsgrad', $sprog_id).": ".dkdecimal($dg_sum,2)."%\">";
		if (!$vis_saet) print findtekst('2538|Dækningsgrad', $sprog_id).": ".dkdecimal($dg_sum,2)."%";
		$confirm1 = findtekst('1523|Dokumentet sendes som email til', $sprog_id);
		print "</td>\n";
		print "<td align=\"center\">".findtekst('770|Moms', $sprog_id).": ".dkdecimal($moms,2)."</td>\n";
		print "<td align=\"center\" title=\"".findtekst('2538|Dækningsgrad', $sprog_id).": ".dkdecimal($dg_sum,2)."%\">".findtekst('2373|I alt', $sprog_id).": ".dkdecimal($ialt,2)."</td>\n";
		print "</tbody></table></td></tr>\n"; #<- Tabel 2.5.1
		print "<tr><td align=\"center\" colspan=\"11\">\n";
		print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>\n"; #Tabel 2.5.2 ->

		$qtxt="select var_value from settings where var_name='orderNoteEnabled'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			if ($r['var_value']) {
				$qtxt="select notes from ordrer where id='$id'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$orderNoteText=$r['notes'];
				($orderNoteText)?$color='red':$color='black';
				print "<div class='textareaBeholder'>";
				print "<title='".findtekst('1713|Udvid tekstfeltet ved at hive i hjørnet', $sprog_id)."'>";
				print "<a href='#nav'><span style='color:$color'>".findtekst('1712|Bemærkning til ordre', $sprog_id)."</span></a><br />\n";
				print "<div class='expandable' id='nav'>";
				print "<textarea rows='3'cols='100' name='orderNoteText' onchange='javascript:this.form.submit()'>".$orderNoteText."</textarea>";
				print "</div>\n";
				print "</div>\n";
			}
		}	
		if ($art!='DK') {
			print "<td align=\"center\"><input type=\"submit\" class=\"button gray medium\" "; 
			// print "value=\"".findtekst('1100||Kopier', $sprog_id)."\" name=\"b_submit\" ";
			print "value=\"".findtekst('1100||Kopier', $sprog_id)."\" name=\"copy\" ";
			print "title=\"".findtekst('1459|Kopiér til ny ordre med samme indhold', $sprog)."\"></td>\n";
		}
		if ($mail_fakt) $tmp="value=\"&nbsp;Send&nbsp;\" onclick=\"return confirm('$confirm1 $email')\" title=\"".findtekst('1460|Send som e-mail med vedhæftet PDF-fil. Andre typer behandlinger er valgt fra \'Udskriv til\'', $sprog_id)."\"";
		else if($udskriv_til == "Digitalt") $tmp="value=\"Send\" title=\"".findtekst('2539|Send faktura digitalt', $sprog_id)."\"";
		else $tmp="value=\"".findtekst('880|Udskriv',$sprog_id)."\" title=\"".findtekst('1461|Åbn et PDF-dokument, som kan gemmes eller viderebehandles på anden vis.', $sprog_id)."\"";
    print "<td align=\"center\"><input type=\"submit\" class=\"button gray medium\" name=\"print\" $tmp></td>\n";
		if (($art!='DK')&&(!$krediteret)) {
			$title = findtekst('1462|Klik her for at oprette en kreditnota, som hel eller delvist krediterer denne faktura. Kreditnotaen oprettes som en kreditnotaordre, som kan redigeres inden bogføring. Eksempelvis hvis kun en enkelt faktureret vare skal krediteres.', $sprog_id);
			print "<td align=\"center\" title=\"$title\"><input type=\"submit\" class=\"button gray medium\" value=\"".findtekst('1001|Kredit', $sprog_id)."\" name=\"b_submit\"></td>\n";
		}
		print "</tbody></table></td></tr>\n"; #<- Tabel 2.5.2
		print "</tbody></table></td></tr>\n"; #<- Tabel 2.5
		print "</tbody></table></td></tr>\n"; #<- Tabel 2
		print "</form>\n";

	} else { ############################# ordren er ikke faktureret #################################
		if ($id && $brugervalg && !$ref) {
			print tekstboks('Vælg sælger (Vor ref)'); #20170419
		} elseif ($brugernavn && !$ref) $ref=$brugernavn;
		$disabled='disabled';
		if (!$konto_id) { #20150302
      if ($incl_moms) {
        $qtxt = "select box2 from grupper where art = 'SM' ";
        $qtxt.= "order by box2 desc limit 1";
        $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
        $momssats = $r['box2'];
      }
			$status=0;
		}
		if ($rvnr || $brugsamletpris) {
			$x=1;
			$linje_id=array();

			
			
			$qtxt="select id from ordrelinjer where ordre_id = '$id' and saet > 0 and varenr!='$rvnr' order by saet,samlevare,posnr,id";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				$linje_id[$x]=$r['id'];
				$x++;		
			}
			$qtxt="select id,saet from ordrelinjer where ordre_id = '$id' and rabat > 0 and varenr!='$rvnr' order by saet,samlevare,posnr,id";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				if (!$r['saet']) {
					$linje_id[$x]=$r['id'];
					$x++;	
				}
			}
			$qtxt="select * from ordrelinjer where ordre_id = '$id' order by posnr";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				if (!in_array($r['id'],$linje_id)) {
					$linje_id[$x]=$r['id'];
					$x++;	
				}
			}
			$qtxt="select id from ordrelinjer where ordre_id = '$id' and vare_id > '0' and (varenr='$svnr' or varenr='$rvnr')";
			if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {#20150914 Skal kun afvikles hvis der er rabat eller sæt vnr på ordren.
				$qtxt="select id,varenr from ordrelinjer where ordre_id = '$id' and vare_id > '0' and varenr='$rvnr'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($linje_id[$x]=$r['id']) $x++;
				$qtxt="select * from ordrelinjer where ordre_id = '$id' order by posnr";
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					if ((!$r['saet'] && $r['rabat']*1==0 && $r['varenr'] != $rvnr) || !$r['varenr']) { 
						$linje_id[$x]=$r['id'];
						$x++;		
					}
				}
				for ($x=1;$x<=count($linje_id);$x++) {
					if ($linje_id[$x]) {
						// $qtxt="update ordrelinjer set posnr='$x' where id='$linje_id[$x]'";
						// db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
			}
		}
		$qtxt="select max(antal) as antal from ordrelinjer where ordre_id = '$id' and vare_id > '0'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		($r['antal']<0)?$dan_kn=1:$dan_kn=NULL; 
		print "<form name=\"ordre\" action=\"ordre.php?id=$id&amp;sag_id=$sag_id&amp;returside=$returside\" method=\"post\">\n"; 
		print '<input type="hidden" name="dragdrop_json" id="dragdrop_json">';
		print "<input type=\"hidden\" name=\"update_positions\" value=\"0\" id=\"update_positions\">\n"; #20140716
		print '<input type="hidden" name="dragdrop_json" id="dragdrop_json">';
		print "<input type=\"hidden\" name=\"ordrenr\" value=\"$ordrenr\">\n";
		print "<input type=\"hidden\" name=\"status\" value=\"$status\">\n";
		print "<input type=\"hidden\" name=\"id\" value=\"$id\">\n";
		print "<input type=\"hidden\" name=\"art\" value=\"$art\">\n";
		print "<input type=\"hidden\" name=\"kred_ord_id\" value=\"$kred_ord_id\">\n";
		print "<input type=\"hidden\" name=\"sag_id\" value=\"$sag_id\">\n"; #20140507-1
		print "<input type=\"hidden\" name=\"afd_lager\" value=\"$afd_lager\">\n";
		if ($art=='OT') { // Når input fields er 'disabled' bliver de ikke opdateret, derfor tilføjes hidden fields #20140716
			print "<input type=\"hidden\" name=\"kontonr\" value=\"$kontonr\">\n";
			print "<input type=\"hidden\" name=\"ref\" value=\"$ref\">\n";
			print "<input type=\"hidden\" name=\"procenttillag\" value=\"".dkdecimal($procenttillag,2)."\">";
			
			print "<input type=\"hidden\" name=\"felt_1\" style=\"width:200px\" value=\"$felt_1\">\n";
			print "<input type=\"hidden\" name=\"felt_2\" style=\"width:200px\" value=\"$felt_2\">\n";
			print "<input type=\"hidden\" name=\"felt_3\" style=\"width:200px\" value=\"$felt_3\">\n";
			print "<input type=\"hidden\" name=\"felt_4\" style=\"width:200px\" value=\"$felt_4\">\n";
			print "<input type=\"hidden\" name=\"felt_5\" style=\"width:200px\" value=\"$felt_5\">\n";
			
			print "<input type=\"hidden\" name=\"lev_navn\" value=\"$lev_navn\">\n";
			print "<input type=\"hidden\" name=\"lev_addr1\" value=\"$lev_addr1\"><input type=\"hidden\" name=\"lev_addr2\" value=\"$lev_addr2\">\n";
			print "<input type=\"hidden\" name=\"lev_postnr\" value=\"$lev_postnr\"><input type=\"hidden\" name=\"lev_bynavn\" value=\"$lev_bynavn\">\n";
			print "<input type=\"hidden\" name=\"lev_kontakt\" value=\"$lev_kontakt\">\n";
		}
		#intiering af variabler
		$antal_ialt=0; #10.10.2007
		$leveres_ialt=0; #10.10.2007
		$tidl_lev_ialt=0; #10.10.2007
		$konto_id*=1;
		$r=db_fetch_array(db_select("select * from adresser where id=$konto_id",__FILE__ . " linje " . __LINE__));
	
		if(!empty($r) || $r == false ){
			#$k_firmanavn     = if_isset($r['firmanavn']); #20210719 	
			$k_firmanavn     = if_isset($r, NULL, 'firmanavn'); #20210719 	
			$k_addr1         = if_isset($r, NULL, 'addr1');
			$k_addr2         = if_isset($r, NULL, 'addr2');	
			$k_postnr        = if_isset($r, NULL, 'postnr');
			$k_land          = if_isset($r, NULL, 'land');
			$k_bynavn        = if_isset($r, NULL, 'bynavn');
			$k_cvrnr         = if_isset($r, NULL, 'cvrnr');		
			$k_betalingsbet  = if_isset($r, NULL, 'betalingsbet');		
			$k_betalingsdage = if_isset($r, NULL, 'betalingsdage');		
			$k_email         = if_isset($r, NULL, 'email');		
			$k_ean           = if_isset($r, NULL, 'ean');    
			$k_institution   = if_isset($r, NULL, 'institution');

		
		}
		
		// Query til kunde kontakt
		$x=0; #20140826
		$a_email[$x] = $a_kontakt = array();
		$q=db_select("select * from ansatte where konto_id='$konto_id' order by posnr",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$a_kontakt[$x]=htmlspecialchars($r['navn']);
			$a_mobil[$x]=$r['mobil'];
			$a_email[$x]=$r['email'];
			$x++;
		}
		##### pile ######## tilfoejet 20080210
		$alerttekst=findtekst('154|Dine ændringer er ikke blevet gemt! Tryk OK for at forlade siden uden at gemme.', $sprog_id);
		$spantekst=findtekst('198|Klik her for at skifte til forrige ordre på ordrelisten - husk at gemme eventuelle ændringer først.', $sprog_id);
		
		if ($menu=='T') {
			$widthTable = 'width=100%';
			$styleTable = "class='dataTableForm'";
		} else {
			$widthTable = '';
			$styleTable = "bordercolor=\"#FFFFFF\" border=\"1\"";
		}
		print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" valign = \"top\"><tbody>\n"; #Tabel 3 ->

	    if ($prev_id)  print "<tr><td width=\"50%\" title=\"$spantekst\" class='imgNoTextDeco' style='margin-left: 5px;'><a href=\"javascript:confirmClose('ordre.php?id=$prev_id&returside=$returside','$alerttekst')\"><img class='imgInvert imgFade' src=\"../ikoner/left.png\" style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td>\n";
		else print "<tr><td width=\"50%\"></td>\n";
		$spantekst=findtekst('199|Klik her for at skifte til næste ordre på ordrelisten - husk at gemme eventuelle ændringer først.', $sprog_id);
		if ($next_id) print "<td width=\"50%\" align=\"right\" title=\"$spantekst\" class='imgNoTextDeco' style='padding-right: 5px;'><a href=\"javascript:confirmClose('ordre.php?id=$next_id&returside=$returside','$alerttekst')\"><img class='imgInvert imgFade' src=\"../ikoner/right.png\" style=\"border: 0px solid; width: 15px; height: 15px;\"></a></span></td></tr>\n";
		else print "<tr><td width=\"50%\"></td>\n";
		print "</tbody></table>\n"; # <- Tabel 3
##### pile ########
		print "<table cellpadding=\"0\" cellspacing=\"0\" $styleTable $widthTable valign=\"top\"><tbody>\n"; #Tabel 4 ->
		$ordre_id=$id;
		$ret=0;
		($art=='OT')?$disabled="disabled='disabled'":$disabled=NULL; #20140716
		print "<tr><td width=\"31%\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n"; #Tabel 4.1 ->
		print "<tr><td witdh=\"100\">".findtekst('43|Kontonr.', $sprog_id)."</td><td colspan=\"2\">\n";
		if (trim($kontonr)) {
			if ($status>2 || !$id) {
			 print "<input class='inputbox' type='text' style='width:200px;background-color:#ddd;' name='kontonr' ";
			 print "readonly='readonly' onfocus='document.forms[0].fokus.value=this.name;' value=\"$kontonr\" $disabled>\n";
			} elseif (isset($_GET['swap_account']) && $_GET['swap_account']) {
				print "<input class='inputbox' type='text' style='width:150px;background-color:#ddd;' name='newAccountNo'";
				print "onfocus='document.forms[0].fokus.value=this.name;' placeholder='$kontonr' value=''>";
				print "<input type='hidden' name='kontonr' value='$kontonr'>";  
				$title = findtekst('1463|Klik her for at skifte kunde på denne ordre', $sprog_id);
				print "<a style='text-decoration: none' href='ordre.php?id=$id&amp;sag_id=$sag_id&amp;returside=$returside&art=$art&swap_account=swap'>";
				print "<input class='button gray small' type='submit' title='$title' value=".findtekst('436|Skift', $sprog_id)." style='width:50px;'>";
				$fokus = 'newAccountNo';
			} else {
				print "<input class='inputbox' type='text' readonly='readonly' style='width:150px;background-color:#ddd;' name='kontonr'";
				print "onfocus='document.forms[0].fokus.value=this.name;' value=\"$kontonr\">";
				$title = findtekst('1463|Klik her for at skifte kunde på denne ordre', $sprog_id);
				print "<a style='text-decoration: none' href='ordre.php?id=$id&amp;sag_id=$sag_id&amp;returside=$returside&art=$art&swap_account=swap'>";
				// print "<a style='text-decoration: none' href='ordre.php?id=$id&returside=$returside'>";
				print "<button class='button gray small' type='button' title='$title' style='width:50px;'>".findtekst('436|Skift', $sprog_id)."";
			}
    	}  else {
			print "<input class='inputbox' type='text' style='width:200px' name='kontonr' onfocus='document.forms[0].fokus.value=this.name;'";
			print "value=\"$kontonr\" onchange='javascript:docChange = true;'>";
		}
		print "</td></tr>\n";
		if ($firmanavn==$k_firmanavn) $tekstcolor="#444444";
		else {$tekstcolor="#ff0000";$ret=1;};
		print "<tr><td style=\"color:$tekstcolor;\" title=\"$k_firmanavn\">".findtekst('28|Firmanavn', $sprog_id)."</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:200px\" name=\"firmanavn\" onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$firmanavn\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		if ($addr1==$k_addr1 && $addr2==$k_addr2) $tekstcolor="#444444";
		else {$tekstcolor="#ff0000";$ret=1;};
		$txt140 = findtekst('140|Adresse', $sprog_id);
		print "<tr><td style=\"color:$tekstcolor;\" title=\"$k_addr1,$k_addr2\">$txt140</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:200px\" name=\"addr1\" onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$addr1\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		print "<tr><td></td><td colspan=\"2\" style=\"color:$tekstcolor;\" ><input class = 'inputbox' type = 'text' style=\"width:200px\" name=\"addr2\" onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$addr2\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		if ($postnr==$k_postnr) $tekstcolor="#444444";
		else {$tekstcolor="#ff0000";$ret=1;};
		print "<tr><td><span style=\"color:$tekstcolor;\" title=\"$k_postnr\">".findtekst('650|Postnr.', $sprog_id)."</span> &amp; ";
		if ($bynavn==$k_bynavn) $tekstcolor="#444444";
		else {$tekstcolor="#ff0000";$ret=1;};
		$txt46 = findtekst('46|By', $sprog_id);
		print "<span style=\"color:$tekstcolor;\" title=\"$k_bynavn\">$txt46</span></td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:45px;\" name=\"postnr\" onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$postnr\" onchange=\"javascript:docChange = true;\" $disabled><input class = 'inputbox' type = 'text' style=\"width:152px;margin-left:3px;\" name=\"bynavn\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$bynavn\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		if ($land==$k_land) $tekstcolor="#444444";
		else {$tekstcolor="#ff0000";$ret=1;};
		print "<tr><td style=\"color:$tekstcolor;\" title=\"$k_land\">".findtekst('593|Lande', $sprog_id)."</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:200px\" name=\"land\" onfocus=\"document.forms[0].fokus.value=this.name;\"  value=\"$land\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		if (!$sag_id) { #20140826
			print "<tr><td>".findtekst('2530|Att.', $sprog_id)."</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:200px\" name=\"kontakt\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontakt\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		} else {
			print "<tr><td>".findtekst('2530|Att.', $sprog_id)."</td><td colspan=\"2\"><div class=\"ddbox\"><input class=\"inputbox ddtext\" type = 'text' name=\"kontakt\" id=\"Textbox\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontakt\" onchange=\"javascript:docChange = true;\" $disabled>\n"; // DropDownIndexClear('DropDownExTextbox');
			print "<select name=\"DropDownExTextbox\" id=\"DropDownExTextbox\" tabindex=\"1000\" class=\"inputbox ddselect\" $disabled>\n"; // onchange=\"DropDownTextToBox(this,'Textbox');\"
			for ($y=0;$y<=count($a_kontakt);$y++) {
				print "<option value=\"$a_kontakt[$y]\" data-kontakt_tlf=\"$a_mobil[$y]\">$a_kontakt[$y]</option>\n";
			}
			print "</select></div></td></tr>\n";
			print "<tr><td>".findtekst('2530|Att.', $sprog_id)." ".strtolower(findtekst('49|Tlf', $sprog_id))."</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:200px\" name=\"kontakt_tlf\" id=\"kontakt_tlf\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontakt_tlf\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n"; #20160129
			
			print "<script language=\"javascript\" type=\"text/javascript\">
			
							DropDownIndexClear(\"DropDownExTextbox\");
							
							$('#DropDownExTextbox').on('change', function () {
									
									var select = $(this).find('option:selected').val()
									var selectTlf = $(this).find('option:selected').attr('data-kontakt_tlf')
									$('#Textbox').val(select)
									$('#kontakt_tlf').val(selectTlf)
									DropDownIndexClear(\"DropDownExTextbox\");
							});
							
						</script>\n";
		}
		if (!$kundeordnr) $kundeordnr = '';
		print "<tr><td title=\"".findtekst('1464|Kundens ordrenummer som refererence', $sprog_id)."\">".findtekst('1092|Kundeordre', $sprog_id)."</td>";
		print "<td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:200px\" name=\"kundeordnr\" ";
		print "onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kundeordnr\" ";
		print "onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
    	if ($cvrnr!=$k_cvrnr || $ean!=$k_ean || ($email!=$k_email && substr( $email, 0, 11 ) != "debitoripad") || $institution!=$k_institution) $ret=1;
		if ($ret) {
			print "<!-- 1062 Klik her for at synkronisere debitorkort med debitor-informationer fra ordren -->\n";
			print "<tr><td></td><td align=\"center\">
				   <a href=\"sync_stamkort.php?konto_id=$konto_id&ordre_id=$id&retning=op\">
        		   <img src=\"../ikoner/up.png\" title=\"".findtekst('1062|Klik her for at synkronisere debitorkort med debitor-informationer fra ordren', $sprog_id)."\" style=\"border: 0px solid; width: 25px; height: 25px;\">
				   </a></td>";
			print "<!-- 1063 Klik her for at synkronisere ordren med debitor-informationer fra debitorkort -->\n";
			print "<td align=\"center\"><a href=\"sync_stamkort.php?konto_id=$konto_id&ordre_id=$id&retning=ned\">
        		   <img src=\"../ikoner/down.png\" title=\"".findtekst('1063|Klik her for at synkronisere ordren med debitor-informationer fra debitorkort', $sprog_id)."\" 
				   style=\"border: 0px solid; width: 25px; height: 25px;\"></a></td></tr>\n";
		}
		print "</tbody></table></td>\n\n"; # <- Tabel 4.1
		print "<td width=\"38%\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"250\">\n"; #Tabel 4.2 ->
		($cvrnr==$k_cvrnr)?$tekstcolor="#444444":$tekstcolor="#ff0000";
		print "<tr><td style=\"color:$tekstcolor;\" title=\"$k_cvrnr\">".findtekst('376|CVR-nr.', $sprog_id)."</td><td><input class = 'inputbox' type = 'text' style=\"width:130px\" name=\"cvrnr\" value=\"$cvrnr\" onchange=\"javascript:docChange = true;\" $disabled></td>\n";
		($ean==$k_ean)?$tekstcolor="#444444":$tekstcolor="#ff0000";
		print "<td>&nbsp;</td><td style=\"color:$tekstcolor;\">".findtekst('379|EAN-nr.', $sprog_id)."</td><td><input class = 'inputbox' type = 'text' style=\"width:130px\" name=\"ean\" value=\"$ean\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		print "<tr><td>".findtekst('49|Tlf', $sprog_id)."</td>";
		print "<td><input class='inputbox' style='text-align:left;width:130px' type='text' name='phone' ";
		print "value=\"$phone\" onchange='javascript:docChange = true;' $disabled></td>\n";
		($institution==$k_institution)?$tekstcolor="#444444":$tekstcolor="#ff0000";
    	print "<td></td><td style=\"color:$tekstcolor;\" title=\"$k_institution\">".findtekst('55|Institution', $sprog_id)."</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:130px\" name=\"institution\" value=\"$institution\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		($email==$k_email)?$tekstcolor="#444444":$tekstcolor="#ff0000";
		if (!$sag_id) { #20160303
		$debitoripad = get_settings_value("debitoripad", "ordre", "off");
			if ($debitoripad === "on") {
				print "<tr><td style=\"color:$tekstcolor;\" title=\"$k_email\">".findtekst('52|E-mail', $sprog_id)."<svg onclick=\"document.location.href = '../debitoripad/choose.php?id=$ordre_id'\" title=\"".findtekst('2540|Send til en debitor iPad', $sprog_id)."\" xmlns=\"http://www.w3.org/2000/svg\" height=\"14px\" viewBox=\"0 -960 960 960\" width=\"24px\" fill=\"#090909\"><path d=\"m600-200-56-57 143-143H300q-75 0-127.5-52.5T120-580q0-75 52.5-127.5T300-760h20v80h-20q-42 0-71 29t-29 71q0 42 29 71t71 29h387L544-624l56-56 240 240-240 240Z\"/></svg></td><td><input class = 'inputbox' type = 'text' style=\"width:130px\" name=\"email\" value=\"$email\" onchange=\"javascript:docChange = true;\"></td>\n";
			} else {
				print "<tr><td style=\"color:$tekstcolor;\" title=\"$k_email\">".findtekst('52|E-mail', $sprog_id)."</td><td><input class=\"inputbox\" type=\"text\" style=\"width:130px\" name=\"email\" value=\"$email\" onchange=\"javascript:docChange = true;\"></td>\n";
			}
		} else {
     		print "<tr><td style=\"color:$tekstcolor;\" title=\"$k_email\">".findtekst('52|E-mail', $sprog_id)."</td><td><div class=\"ddbox2\"><input class=\"inputbox ddtext2\" type = 'text' name=\"email\" id=\"Textbox2\" value=\"$email\" onchange=\"javascript:docChange = true;\">\n";
			print "<select name=\"DropDownExTextbox2\" id=\"DropDownExTextbox2\" tabindex=\"1000\" class=\"inputbox ddselect2\">\n";
			if ($k_email) {
				print "<option value=\"$k_email\">".findtekst('35|Kunde', $sprog_id).": $k_email</option>\n";
				print "<option style=\"font-size: 1px; background-color: #cccccc;\" disabled></option>";
			}
			for ($y=0;$y<count($a_email);$y++) {
				print "<option value=\"$a_email[$y]\">$a_kontakt[$y]:&nbsp;&nbsp;$a_email[$y]</option>\n";
			}
			print "<option>&nbsp;</option>\n";
			print "</select></div></td>\n";
			
			print "<script language=\"javascript\" type=\"text/javascript\">
			
							DropDownIndexClear(\"DropDownExTextbox2\");
							
							$('#DropDownExTextbox2').on('change', function () {
									
									var select = $(this).find('option:selected').val()
									$('#Textbox2').val(select)
									DropDownIndexClear(\"DropDownExTextbox2\");
							});
							
						</script>\n";
		}
		print "<td>&nbsp;</td><td>".findtekst('880|Udskriv', $sprog_id)." ".findtekst('904|til', $sprog_id)."</td>\n";
		if (!$udskriv_til) {
			if ($mail_fakt) $udskriv_til="email";
	#      if ($oio_fakt) $udskriv_til="oioxml";
			if ($lev_pbs_nr) {
				if ($pbs) $udskriv_til="PBS";
			}
		}
    	print "<td><select class = 'inputbox' style=\"width:130px\" name=\"udskriv_til\">\n";
		if (!$udskriv_til) $udskriv_til="PDF";
		if ($showLocalPrint && $localPrint == 'on' ) {
			$udskriv_til='localPrint';
			print "<option value=\"localPrint\">".findtekst('2531|Lokal printer', $sprog_id)."</option>\n";
		} elseif ($udskriv_til=="PBS" && $lev_pbs!='B') print "<option value=\"PBS\">BS</option>\n";
		else print "<option>$udskriv_til</option>\n";
		if ($udskriv_til!="PDF") print "<option>PDF</option>\n";
		if ($showLocalPrint && $localPrint != 'on') print "<option value='localPrint'>".findtekst('2531|Lokal printer', $sprog_id)."</option>\n";
		if ($udskriv_til!="PDF-tekst") print "<option value='PDF-tekst' title=\"".findtekst('1448|Udskrives som PDF uden baggrund', $sprog_id)."\">".findtekst('1449|PDF-tekst', $sprog_id)."</option>\n";
		if ($udskriv_til!="email") print "<option value='email' title=\"".findtekst('1450|Sendes som PDF via e-mail', $sprog_id)."\">".findtekst('652|E-mail', $sprog_id)."</option>\n";
		if ($udskriv_til!="ingen") print "<option value='ingen'>".findtekst('2541|Ingen', $sprog_id)."</option>\n"; #PHR 20170501
#		if ($udskriv_til!="oioxml") print "<option title=\"Kun ved fakturering/kreditering.\">oioxml</option>\n"; #PHR 20090803
		#if ($udskriv_til!="oioubl") print "<option value='oioubl' title=\"".findtekst('1451|Kun ved fakturering/kreditering.', $sprog_id)."\">oioubl</option>\n"; #PHR 20090803
		if ($udskriv_til!="Digitalt") print "<option value='Digitalt' title='".findtekst('1451|Kun ved fakturering/kreditering.', $sprog_id)."'>Digitalt</option>\n"; #PBLM 12/06-2023
#		if ($udskriv_til!="edifakt") print "<option title=\"Kun ved fakturering/kreditering.\">edifakt</option>\n"; #PHR 20140201
		if ($udskriv_til!="historik" && db_fetch_array(db_select("select * from grupper where ART = 'bilag' and (box6='on' or (box1 !='' and box2 !='' and box3 !=''))",__FILE__ . " linje " . __LINE__))) {
			print "<option title=\"".findtekst('1453|Gem en kopi og vedhæft kundens historik', $sprog_id)."\">".findtekst('907|Historik', $sprog_id)."</option>\n";
		}
		(is_numeric($pbs_nr))?$tmp=$pbs_nr:$tmp=0;
# 20120822
		if ($lev_pbs_nr) {
			if ($lev_pbs == 'L') {
				if ($tmp) print "<option value=\"PBS\">PBS</option>\n";
			} else {
				if ($udskriv_til!="PBS" && $lev_pbs!='B') print "<option value=\"PBS_FI\">PBS</option>\n";
				elseif ($tmp && $udskriv_til!="PBS" && $lev_pbs=='B') print "<option title=\"".findtekst('1452|Opkræves via betalingsservice', $sprog_id)."\">BS</option>\n";
			}
		}
		print "</SELECT></td></tr>\n";
		if (db_fetch_array(db_select("select distinct sprog from formularer where sprog != 'Dansk'",__FILE__ . " linje " . __LINE__))) {
			print "<tr><td title=\"".findtekst('1468|Sprog som skal anvendes på dokumenter som tilbud, ordrer, fakturaer med videre.', $sprog_id)."\">".findtekst('801|Sprog', $sprog_id)."</span></td>\n";
			print "<td><select class = 'inputbox' style=\"width:130px\" name=\"sprog\">\n";
			print "<option>$formularsprog</option>\n";
			$q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) print "<option>$r[sprog]</option>\n";
			print "</SELECT></td>";
		} else print "<tr><td colspan=\"2\"></td>";

		print "<tr><td>".findtekst('1095|Momssats', $sprog_id)."</td><td>";
		print "<input class='inputbox' style='text-align:right;width:60px' type='text' name='momssats' ";
		print "value=\"".dkdecimal($momssats,2)."\" onchange='javascript:docChange = true;' $disabled>%</td></tr>\n";
		/*
    print "<tr><td colspan=2>Send pr. mail&nbsp;</td><td><input class = 'inputbox' type=\"checkbox\" name=\"mail_fakt\" onchange=\"javascript:docChange = true;\" $mail_fakt></td>\n";
		if ($lev_pbs_nr) {
			if ($pbs == "FI") $pbs_fi='checked';
			elseif ($pbs == "BS") $pbs_bs='checked';
			$title="PBS udsender FI indbetalingskort";
			if (!$pbs_bs) { #naeste linje ingen apostrof omkring $pbs_fi
        print "<td colspan=\"2\" title=\"$title\">Faktura via PBS (FI)</td><td title=\"$title\"><input class = 'inputbox' type=\"checkbox\" name=\"pbs_fi\" $pbs_fi onchange=\"javascript:docChange = true;\"></td></tr>\n";
				if ($pbs_nr && !$pbs_fi) print "<tr><td colspan=\"2\"><td>\n";
			}
			$title="Opkr&aelig;ves via PBS betalingsservice";
			if ($pbs_nr && !$pbs_fi) print "<td colspan=\"2\" title=\"$title\">Opkr&aelig;v via PBS (BS)</td><td title=\"$title\"><input class = 'inputbox' type=\"checkbox\" name=\"pbs_bs\" \"$pbs_bs\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		} else print "</tr>\n";
*/
		if(!$hurtigfakt && $status<=1) $std_bilag="tilbud"; 
		elseif($status<=2) $std_bilag="ordrer";
		elseif($status>=4) $std_bilag="faktura";
		else $std_bilag=NULL;

		if ($std_bilag && !file_exists("../logolib/".$db_id."/".$std_bilag."_bilag.pdf")) $std_bilag=NULL;

		($mail_bilag=='on')?$checked="checked='checked'":$checked=NULL;
		if ($std_bilag && $udskriv_til=="email") {
		$titletext= findtekst('1466|Afmærk her for at medsende standard bilag', $sprog_id); 
		print "<tr><td title='$titletext'>".findtekst('1467|Mail bilag', $sprog_id)."</td><td title='$titletext'><input type=\"checkbox\" name=\"mail_bilag\" $checked></td>"; #20131122 Checkbox til mail_bilag
		} else print "<tr><td colspan=\"2\"><input type=\"hidden\" name=\"mail_bilag\" value=\"$mail_bilag\"></td>";
		if ($procentvare) print "<td>&nbsp;</td><td>".findtekst('683|Procenttillæg', $sprog_id)."&nbsp;</td><td><input class = 'inputbox' style=\"text-align:right;width:35px\" type = 'text' name=\"procenttillag\" value=\"".dkdecimal($procenttillag,2)."\" onchange=\"javascript:docChange = true;\" $disabled>%</td></tr>\n";
		else print "</tr>\n";
		print "<tr><td colspan=\"5\"><hr></td></tr>\n";
		print "<tr><td width=\"20%\">".findtekst('1096|Ordredato', $sprog_id)."</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:130px\" name=\"ordredato\" value=\"$ordredato\" onchange=\"javascript:docChange = true;\" $disabled></td>\n";
		if ($hurtigfakt=='on') print "<td></td></tr>\n";
		else {
			if ($art=='DK') print "<td title=\"".findtekst('1469|Dato for returnering', $sprog_id)."\">".findtekst('941|Modt. dato', $sprog_id)."</td>";
			else print "<td title=\"".findtekst('886|Dato for levering', $sprog_id)."\">".findtekst('550|Lev. dato', $sprog_id)."</td>";
			print "<td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:130px\" name=\"levdato\" value=\"$levdato\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
		}
		if ($fakturadato||$status>0) {
			$dd=date("d-m-Y");
			print "<tr><td ";
			if ($art!='DK') print "title=\"".findtekst('1094|Fakturadato', $sprog_id)."\">".findtekst('883|Fakt. dato', $sprog_id)."";
			else print "title=\"".findtekst('1470|Dato for kreditnota', $sprog_id)."\">".findtekst('1471|KN. dato', $sprog_id)."";
			print "</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:130px\" placeholder=\"$dd\" name=\"fakturadato\" value=\"$fakturadato\" onchange=\"javascript:docChange = true;\"></td>\n";
			$tmp=findtekst('1476|Mail emne', $sprog_id);
			if ($art=='DO') print "<td width=\"20%\" title=\"$tmp\">".findtekst('82|Genfakturer', $sprog_id)."</span></td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:130px\" name=\"genfakt\" value=\"$genfakt\" onchange=\"javascript:docChange = true;\"></td>\n";
		}
		$kontobet=array('Efterkrav','Forud','Netto','Lb. md.');
		print "<tr><td>".findtekst('935|Betaling', $sprog_id)."</td>\n";
		if ($vis_saet) {
			$r = db_fetch_array(db_select("select box3,box4,box5 from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__));
			$pos_afd=explode(chr(9),$r['box3']); #20170421
			$kortantal=$r['box4']*1;
			$korttyper=explode(chr(9),$r['box5']);
			if ($art=='DK') { #20180822
				$betalingsdage='0';
				if ($felt_1 == 'Konto') $betalingsbet='Konto';
				elseif ($felt_1 == 'Kontant') $betalingsbet='Kontant';
				elseif (in_array($felt_1,$korttyper) || $felt_1 == 'Betalingskort') $betalingsbet='Kreditkort';
				print "<td>";
				print "<input type='hidden' name='betalingsbet' value='$betalingsbet'>";
				print "<input type='hidden' name='betalingsdage' value='0'>";
				print "$betalingsbet";
				print "</td>";
				if ($betalingsbet='Netto') print "<td></td>";
			} else {
				if ($felt_1 == 'Konto') { # Rettet 20181218 
					if (!in_array($betalingsbet,$kontobet)) {
						$qtxt="select betalingsbet,betalingsdage from adresser where id='$konto_id'";
						$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
						$betalingsbet=$r['betalingsbet'];
						$betalingsdage=$r['betalingsdage'];
					}
					if (!in_array($betalingsbet,$kontobet)) $betalingsbet='Netto';
          			print "<td colspan='2'><select class = 'inputbox' style=\"width:96px\" name='betalingsbet'>";
					for ($x=0;$x<count($kontobet);$x++){
						if ($kontobet[$x]==$betalingsbet) print "<option value='$kontobet[$x]'>$kontobet[$x]</option>";
					}
					for ($x=0;$x<count($kontobet);$x++){
						if ($kontobet[$x]!=$betalingsbet) print "<option value='$kontobet[$x]'>$kontobet[$x]</option>";
					}
					print "</select>&nbsp;";
					print "<input type='text' style='text-align:right;width:25px' name='betalingsdage' value='$betalingsdage'></td>";
        		}  elseif ($felt_1 == 'Kontant') $betalingsbet='Kontant';
				elseif (in_array($felt_1,$korttyper)) $betalingsbet='Kreditkort';
				if ($felt_1 != 'Konto') {
					$betalingsdage=0;
					if ($betalingsbet == 'Kreditkort') print "<td colspan='2'>$txt283";
					elseif ($betalingsbet == 'Kontant') print "<td colspan='2'>$txt370";
					else print "<td colspan='2'>$betalingsbet";
					print "<input type='hidden' name='betalingsbet' value='$betalingsbet'>";
					print "<input type='hidden' name='betalingsdage' value='0'>";
					print "</td>";
				}
			}
			$r = db_fetch_array(db_select("select var_value from settings where var_name = 'card_enabled'",__FILE__ . " linje " . __LINE__));
			$card_enabled=explode(chr(9),$r['var_value']);
#   	   $card_enabled="'on'.chr(9).'on'.chr(9).'on'.chr(9).'on'.chr(9).'on'.chr(9).'on'.chr(9).'on'.chr(9).'on'.chr(9).'on'.chr(9).'on'.chr(9).'on'.chr(9).'on'.chr(9).'on'.chr(9).'on'.chr(9).'on'";
		}
		if (!$vis_saet) {
			if (!$betalingsbet) $betalingsbet="Netto";
			if ($art=='DK') {
			print "<td colspan=\"2\"><select style=\"width:097px;\" class = 'inputbox' style=\"width:130px\" name=\"betalingsbet\">\n";

			if ($betalingsbet=='Kontant')    print "<option value='Kontant'>$txt370</option>\n";
			if ($betalingsbet=='Netto')      print "<option value='Netto'>".findtekst('440|Konto', $sprog_id)."</option>\n";
			if ($betalingsbet!='Kontant')    print "<option value='Kontant'>$txt370</option>\n";
			if ($betalingsbet!='Netto')      print "<option value='Netto'>".findtekst('372|Netto', $sprog_id)."</option>\n";
				if ($betalingsbet=='Kontant'||$betalingsbet=='Efterkrav'||$betalingsbet=='Forud'||$betalingsbet=='Kreditkort') $betalingsdage='';
				else $betalingsdage=0;
				print "</SELECT></td>\n";
			} else {
				if (!$betalingsbet) $betalingsbet="Netto";
        print "<td colspan=\"2\"><select class = 'inputbox' style=\"width:96px\" name=\"betalingsbet\" $disabled>\n";
        print "<option style='color: red !important;' value = '$betalingsbet'>";
        if ($betalingsbet == 'Kreditkort') print $txt283;
        elseif ($betalingsbet == 'Kontant') print $txt370;
        else print $betalingsbet;
        print "</option>\n";
			if (!$betalt) {
			if ($betalingsbet!='Forud')      print "<option>Forud</option>\n";
			if ($betalingsbet!='Kontant')    print "<option value = 'Kontant'>$txt370</option>\n";
			if ($betalingsbet!='Kreditkort') print "<option value = 'Kreditkort'>$txt283</option>\n";
			if ($betalingsbet!='Efterkrav')  print "<option value = 'Efterkrav'>Efterkrav</option>\n";
			if ($betalingsbet!='Netto')      print "<option value = 'Netto'>Netto</option>\n";
			if ($betalingsbet!='Lb. md.')    print "<option value = 'Lb. md.'>Lb. md.</option>\n";
			}
			if ($betalingsbet=='Kontant'||$betalingsbet=='Efterkrav'||$betalingsbet=='Forud'||$betalingsbet=='Kreditkort') $betalingsdage='';
			elseif (!$betalingsdage) $betalingsdage='Nul';
        if ($betalingsdage)  {
			if ($betalingsdage=='Nul') $betalingsdage=0;
          print "</SELECT>+<input class = 'inputbox' type = 'text' style=\"text-align:right;width:25px\" name=\"betalingsdage\" value=\"$betalingsdage\" onchange=\"javascript:docChange = true;\" $disabled></td>\n";
				}
			}
		}
		$list=array();
		$beskriv=array();
    $list[0]=$baseCurrency;
		$x=0;
		$q = db_select("select * from grupper where art = 'VK' order by box1 ",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			$x++;
			$list[$x]=$r['box1'];
			$beskriv[$x]=$r['beskrivelse'];
		}
		$tmp=$x;
		if ($x>0) {
      $list[0]=$baseCurrency;
			$beskriv[0]='Danske kroner';
			print "<td>".findtekst('1069|Valuta', $sprog_id)." </td>\n";
			print "<td><select style=\"width:125px;\" class = 'inputbox' NAME=\"ny_valuta\">\n";
			$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$valuta=if_isset($row['valuta']); #20210719 valuta value mixed up to this place: Hence throwing undefined variable error..So it had to be redeclared
			for ($x=0; $x<=$tmp; $x++) {
				if ($valuta!=$list[$x]) print "<option title=\"$beskriv[$x]\">$list[$x]</option>\n";
				else print "<option title=\"$beskriv[$x]\" selected=\"selected\">$list[$x]</option>\n";
			}
			print "</SELECT></td><td>";
			if ($valutakurs != 100) print "($valutakurs)";
			print "</td>\n";
			
		} else //print "<tr><td colspan=\"2\" width=\"200\">\n"; # udkommenteret 15052014
		print "</tr>\n";
		$r=db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
		if(isset($r['id'])){
			$adr_id=intval($r['id']);
			$x=0;
			$ansat=array();
			$a_afd=array();
			$q=db_select("select navn,afd from ansatte where konto_id = '$adr_id' and lukket != 'on' order by navn",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$ansat[$x]=$r['navn'];
				$a_afd[$x]=$r['afd'];
				$x++;
			}
			if (!in_array($ref,$ansat)) {
				$r=db_fetch_array(db_select("select navn from ansatte,brugere where brugernavn='$ref' and ansatte.id=".nr_cast('brugere.ansat_id')."",__FILE__ . " linje " . __LINE__));
				if (!empty($r['navn'])) $ref=$r['navn']; #20210715
					
			}
			
			for ($x=0;$x<count($ansat);$x++) {
				if (!$x) {
					print "<tr><td>".findtekst('1097|Vor ref.', $sprog_id)."</td>\n";
					print "<td><select style=\"width:125px;\" class = 'inputbox' name=\"ref\" $disabled>\n";
					print "<option>$ref</option>\n";
				}
				if ($ref!=$ansat[$x]) print "<option> $ansat[$x]</option>\n";
			}
		}	
		print "</select>\n";
		$x=0;
		$afd_navn=array();
		$afd_nr[$x]=array();	
		$q = db_select("select * from grupper where art = 'AFD'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)){
			$afd_nr[$x]=$r['kodenr'];
			$afd_navn[$x]=$r['beskrivelse'];
			$x++;
		}
		print "</td></tr>\n";
		$kasseantal=0;
		if ($vis_saet && $afd) {
			if ($r=db_fetch_array(db_select("select box1,box3 from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__))) {
				$kasseafd=explode(chr(9),$r['box3']);
				for ($x=0;$x<count($kasseafd);$x++) {
					if ($kasseafd[$x]==$afd) $kasseantal++; 
				}
			}
			if ($kasseantal == 1) {
				for ($x=0; $x<count($kasseafd); $x++) {
					$y=$x+1;
					if ($felt_5==$y && ($kasseafd[$x]==$afd)) print "<input='hidden' name='kasse' value='$y'>";
				}
			}
			if ($kasseantal > 1) {
        		print "<tr><td colspan='3'></td><td>".findtekst('931|Kasse', $sprog_id)."</td><td><select style=\"width:125px;\" class = 'inputbox' name=\"kasse\">\n";
				for ($x=0; $x<count($kasseafd); $x++) {
					$y=$x+1;
					if ($felt_5==$y && ($kasseafd[$x]==$afd)) print "<option>$y</option>\n";
				}
				for ($x=0; $x<count($kasseafd); $x++) {
					$y=$x+1;
					if ($felt_5!=$y && ($kasseafd[$x]==$afd)) print "<option>$y</option>\n";
				}
				print "</select></td></tr>\n";
			}
		}
		$list=array();
		$beskriv=array();
		$x=0;
		$q = db_select("select * from grupper where art = 'PRJ' and kodenr != '0' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			$x++;
			$list[$x]=$r['kodenr'];
			$beskriv[$x]=$r['beskrivelse'];
		}
		$projektantal=$x;
		if ($x>0) {
			$vis_projekt='on';
			print "<td title=\"".findtekst('1473|Hvis hele ordren skal registreres på et projekt, vælges projektet her. Ellers anvendes projektfeltet på ordrelinjen.', $sprog_id)."\">".findtekst('553|Projekt', $sprog_id)."</td>\n";
			print "<td><select class = 'inputbox' name=\"projekt[0]\">\n";
			for ($x=0; $x<=$projektantal; $x++) {
				if ($projekt[0]!=$list[$x]) print "<option title=\"$beskriv[$x]\">$list[$x]</option>\n";
				else print "<option title=\"$beskriv[$x]\" selected=\"selected\">$list[$x]</option>\n";
			}
			print "</select>";
			print "<input type = 'hidden' name='extAfd' value='$afd'>";
		if (count($afd_nr)>1) {
			print "</td><td></td>\n";
			print "<td>".findtekst('1198|Afd.', $sprog_id)."</td><td><select style=\"width:125px;\" class = 'inputbox' name=\"afd\">";
			for ($x=0;$x<count($afd_nr);$x++) {
				if ($afd_nr[$x]==$afd) print "<option value=\"$afd_nr[$x]\">$afd_nr[$x] $afd_navn[$x]</option>";
			} 
			for ($x=0;$x<count($afd_nr);$x++) {
				if ($afd_nr[$x]!=$afd) print "<option value=\"$afd_nr[$x]\">$afd_nr[$x] $afd_navn[$x]</option>";
			} 
			print "</select>";
		} elseif (count($afd_nr) === 1 && isset($afd_nr[0]) && !is_array($afd_nr[0])) {
			$value = htmlspecialchars($afd_nr[0]);
			print "<input type='hidden' name='afd' value='$value'>"; #20250816
		}
			print "</td></tr>\n";
		} else print "<tr><td colspan=\"2\" width=\"200\"></tr>\n";
		$txt555 = findtekst('555|Godkend', $sprog_id);
		if ($status==0&&$hurtigfakt!="on") print "<tr><td>$txt555</td><td><input class = 'inputbox' type=\"checkbox\" name=\"godkend\" $disabled></td></tr>\n";
		elseif ($status<3&&$hurtigfakt!="on") {
			
			if ($restordre) $restordre="checked";
			else $restordre = "";
      		print "<tr><td>".findtekst('2542|Restordre', $sprog_id)."</td><td><input class = 'inputbox' type=\"checkbox\" name=\"restordre\" $restordre></td>\n";
		}
		print "</tbody></table></td>\n"; # <- Tabel 4.2
		print "<td width=\"31%\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" valign = 'top'>\n"; # Tabel 4.3 ->
		$vis_addr = get_settings_value("vis_lev_addr", "ordrer", "off", $bruger_id);
		if ($vis_addr == "on") {
			$txt28 = findtekst('28|Firmanavn', $sprog_id);
			$txt140 = findtekst('140|Adresse', $sprog_id);
			$txt666 = findtekst('666|Postnr & by', $sprog_id);
			print "<tr><td align=\"center\">$jobkort $debitorkort</td><td align=\"left\">".findtekst('355|Vis leveringsadresse', $sprog_id)." <input class='checkmark' type=\"checkbox\" name=\"vis_lev_addr\" checked=\"checked\"><td></tr>\n";
			print "<tr><td colspan=\"2\"><hr><td></tr>\n";
			print "<tr><td colspan=\"2\" align=\"center\"><b>".findtekst('554|Leveringsadresse', $sprog_id)."</b></td></tr>\n";
			print "<tr><td colspan=\"2\"><hr></b></tr>\n";
			print "<tr><td>$txt28</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:200px\" onfocus=\"document.forms[0].fokus.value=this.name;\" name=\"lev_navn\" value=\"$lev_navn\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
			print "<tr><td>$txt140</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:200px\" onfocus=\"document.forms[0].fokus.value=this.name;\" name=\"lev_addr1\" value=\"$lev_addr1\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
			print "<tr><td></td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:200px\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"lev_addr2\" value=\"$lev_addr2\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
			print "<tr><td>$txt666</td><td><input class = 'inputbox' type = 'text' style=\"width:45px\" onfocus=\"document.forms[0].fokus.value=this.name;\" name=\"lev_postnr\" value=\"$lev_postnr\" $disabled><input class = 'inputbox' type = 'text' style=\"width:150px;margin-left:3px;\" name=\"lev_bynavn\" value=\"$lev_bynavn\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
			print "<tr><td>".findtekst('47|Land', $sprog_id)."</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:200px\" onfocus=\"document.forms[0].fokus.value=this.name;\" name=\"lev_land\" value=\"$lev_land\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
			print "<tr><td>".findtekst('2530|Att.', $sprog_id)."</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:200px\" onfocus=\"document.forms[0].fokus.value=this.name;\" name=\"lev_kontakt\" value=\"$lev_kontakt\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
			print "<tr><td>".findtekst('398|Kontakt', $sprog_id)."</td><td colspan=\"2\"><input class = 'inputbox' type = 'text' style=\"width:200px\" onfocus=\"document.forms[0].fokus.value=this.name;\" name=\"lev_email\" value=\"$lev_email\" onchange=\"javascript:docChange = true;\" $disabled></td></tr>\n";
			print "<input type=\"hidden\" name=\"felt_1\" style=\"width:200px\" value=\"$felt_1\">\n";
			print "<input type=\"hidden\" name=\"felt_2\" style=\"width:200px\" value=\"$felt_2\">\n";
			print "<input type=\"hidden\" name=\"felt_3\" style=\"width:200px\" value=\"$felt_3\">\n";
			print "<input type=\"hidden\" name=\"felt_4\" style=\"width:200px\" value=\"$felt_4\">\n";
			#print "<input type=\"hidden\" name=\"felt_5\" style=\"width:200px\" value=\"$felt_5\">\n";
		} else {
			print "<tr><td align=\"center\">$jobkort $debitorkort</td><td align=\"left\">".findtekst('355|Vis leveringsadresse', $sprog_id)." <input class='checkmark' type=\"checkbox\" name=\"vis_lev_addr\"><td></tr>\n";
			print "<tr><td colspan=\"2\"><hr><td></tr>\n";
			print "<tr><td colspan=\"2\" align=\"center\"><b>".findtekst('243|Ekstrafelter', $sprog_id)."</b></tr>\n";
			print "<tr><td colspan=\"2\"><hr></b></tr>\n";
			if ($vis_saet) {
				$qtxt = "select box4,box5,box6 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$tmp = (int)$felt_5 - 1;
				if (!isset($pos_afd[$tmp])) $pos_afd[$tmp]=0;
				($pos_afd[$tmp]==$afd)?$terminal_ip=explode(chr(9),$r['box4']):$terminal_ip=NULL;
				$betalingskort=explode(chr(9),$r['box5']); 
				$div_kort_kto=trim($r['box6']);
				(isset($felt_2))?$felt_2=(float)$felt_2:$felt_2=0;
				(isset($felt_4))?$felt_4=(float)$felt_4:$felt_4=0;
				if ($fakturadate) {
					if ($terminal_ip[(int)$felt_5-1] && !$felt_2) {
						$felt_2=$betalt;
					} else {	
						if (!$felt_2) $felt_2=$sum+$moms;
					}
					($felt_2<$sum+$moms)?$felt_4=$sum+$moms-$felt_2:$felt_4=0;
				}
				
				if ($felt_1 == 'Konto') $felt_4=0;
				if (isset($terminal_ip[(int)$felt_5-1]) && !$betalt) $felt_2=$sum+$moms-$felt_4;
				$dkfelt_2=dkdecimal($felt_2,2);
				$dkfelt_4=dkdecimal($felt_4,2);
				if (if_isset($_GET['godkendt'])=='OK' && usdecimal(if_isset($_GET['modtaget']),2)==usdecimal($dkfelt_2,2)) {
					$betalt=usdecimal($_GET['modtaget'],2);
					if ($_GET['kortnavn']) $felt_1=$_GET['kortnavn'];
					db_modify("update ordrer set betalt='$betalt',felt_1='$felt_1' where id = '$id'",__FILE__ . " linje " . __LINE__);

#        }  elseif ($r=db_fetch_array(db_select("select betalt from ordrer where id=$id and betalt='$dkfelt_2'",__FILE__ . " linje " . __LINE__))) {
#          $betalt=$r['modtaget'];
#        } else $betalt=NULL;
				}
				if ($betalt) {
					$disabled='disabled';
					$felt_2=$betalt;
					$dkfelt_2=dkdecimal($betalt,2);
				} else $disabled=$disabled;
        print "<tr><td><select style='width:110px' name='felt_1' $disabled>";
#        if ($betalingsbet=='Kreditkort') {
          if ($felt_1) {
            print "<option value='$felt_1'>";
            ($felt_1 == 'Kontant')?print $txt370:print $felt_1;
            print "</option>";
          }
#          if (!in_array($felt_1,$korttyper) && $felt_1 != 'Betalingskort' && $terminal_ip[$felt_5-1]) $felt_1=NULL;
#          elseif (!in_array($felt_1,$korttyper) && !$terminal_ip[$felt_5-1]) $felt_1=NULL;
					if ($terminal_ip[(int)$felt_5-1]) {
// #            if ($felt_1) print "<option value='$felt_1'>$felt_1</option>";
				if ($felt_1!='Betalingskort') print "<option value='Betalingskort'>".findtekst('710|Betalingskort', $sprog_id)."</option>";
					for ($x=0;$x<$kortantal;$x++) {
						if ($felt_1!=$korttyper[$x] && $card_enabled[$x] && !$betalingskort[$x]) print "<option value='$korttyper[$x]'>$korttyper[$x]</option>";
					}
				} else {
					if ($felt_1) print "<option value='$felt_1'>$felt_1</option>";
					for ($x=0;$x<$kortantal;$x++) {
						if ($felt_1!=$korttyper[$x] && $card_enabled[$x]) print "<option value='$korttyper[$x]'>$korttyper[$x]</option>";
					}
				}
				if ($felt_1 != 'Kontant') print "<option value='Kontant'>$txt370</option>";
				if ($felt_1 != 'Konto') print "<option value='Konto'>".findtekst('440|Konto', $sprog_id)."</option>";
			#        } else {
			#          ($betalingsbet=='Kontant')?$felt_1='Kontant':$felt_1='Konto';
			#          print "<option value='$felt_1'>$felt_1</option>";
			#        }
				print "</select></td>";
        		print "<td><input class = 'inputbox' type = 'text' name='felt_2' style='text-align:right;width:200px' value='$dkfelt_2' $disabled></td>";
				print "</tr>\n";	
				if ($felt_1 == 'Konto') {
				print "<tr><td><input type='hidden'  name='felt_3' value='Kontant'>";
				print "<input type='hidden'  name='felt_4' value='0'><td></tr>";
				} else {
					if ($felt_3 == $felt_1) $felt_3=NULL;
					if ($felt_3!='Kontant' && $felt_4 == 0 && $felt_1!='Kontant') $felt_3='Kontant';
					print "<tr><td><select style='width:110px' name='felt_3'>";
					if ($felt_3) {
						print "<option value='$felt_3'>";
						($felt_3 == 'Kontant')?print $txt370:print $felt_3;
						print "</option>";
					}
					if ($felt_3!='Kontant' && $felt_1!='Kontant') print "<option value='Kontant'>$txt370</value>";
					for($x=0;$x<$kortantal;$x++) {
						if ($felt_3!=$korttyper[$x] && $felt_1!=$korttyper[$x] && !$betalingskort[$x]) print "<option value='$korttyper[$x]'>$korttyper[$x]</option>";
					}
					print "</select></td>";
          			print "<td><input class = 'inputbox' type = 'text' name=\"felt_4\" style=\"text-align:right;width:200px\" value=\"$dkfelt_4\" $disabled>";
					if ($disabled) print "<input type=\"hidden\" name=\"felt_4\" value=\"$dkfelt_4\">";
					print "</td></tr>\n\n";
				}
				if (isset($terminal_ip[(int)$felt_5-1])) {
					if ($felt_1=='Konto' || $felt_1=='Kontant') $terminal_ip[(int)$felt_5-1]=NULL;
				}
				if ($disabled) {
				print "<input type='hidden' name='felt_1' value='$felt_1'>\n";
				print "<input type='hidden' name='felt_2' value='$dkfelt_2'>\n";
				}
				if (isset($terminal_ip[(int)$felt_5-1])) {
					if ($_SERVER['HTTPS']) $url='https://';
					else $url='http://';
					$url.=$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
					if (isset($_COOKIE['salditerm'])) $terminal_ip[(int)$felt_5-1]=$_COOKIE['salditerm'];
					if ($terminal_ip[(int)$felt_5-1]=='box' || $terminal_ip[(int)$felt_5-1]=='saldibox') {
						$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
						if ($fp=fopen($filnavn,'r')) {
							$terminal_ip[(int)$felt_5-1]=trim(fgets($fp));
							fclose ($fp);
						}
					}
					$tidspkt=date('U');
					$dkfelt_2=str_replace('.','',$dkfelt_2);
					if($felt_1 == ""){
						$felt_1 = "Betalingskort";
					}
					($felt_1=='Betalingskort')?$vis_betalingslink=1:$vis_betalingslink=0;
					for($x=0;$x<$kortantal;$x++) {
						if ($felt_1==$korttyper[$x] && $betalingskort[$x]) $vis_betalingslink=1;
					}
					if ($vis_betalingslink==1) {
						$href="http://".$terminal_ip[(int)$felt_5-1]."/pointd/kvittering.php?url=$url&id=$id&&kommando=kortbetaling&";
						$href.="belob=$dkfelt_2&betaling=&modtaget=$dkfelt_2&modtaget2=0&indbetaling=&tidspkt=".date("U");
						print "<tr><td><br></td></tr><tr><td colspan='2' align='center'>";
						print "<input type='button' style='width:100%' onclick=\"window.location.href='$href'\" value='Kortbetaling'>";
						print "</td></tr>\n";
					}
				}
			} else {
				if (substr(findtekst('244|Ordrefelt 1', $sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst('249|Denne tekst kan rettes under <i>Indstillinger</i> -> <i>Diverse</i> -> <i>Sprog</i><br>Find Id 244 & 249.', $sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst('244|Ordrefelt 1', $sprog_id)."</span></td><td><input class = 'inputbox' type = 'text' name=\"felt_1\" style=\"width:200px\" value=\"$felt_1\" $disabled></td></tr>\n";
				if (substr(findtekst('245|Ordrefelt 2', $sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst('250|Denne tekst kan rettes under <i>Indstillinger</i> -> <i>Diverse</i> -> <i>Sprog</i><br>Find Id 245 & 250.', $sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst('245|Ordrefelt 2', $sprog_id)."</span></td><td><input class = 'inputbox' type = 'text' name=\"felt_2\" style=\"width:200px\" value=\"$felt_2\" $disabled></td></tr>\n";
				if (substr(findtekst('246|Ordrefelt 3', $sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst('251|Denne tekst kan rettes under <i>Indstillinger</i> -> <i>Diverse</i> -> <i>Sprog</i><br>Find Id 246 & 251.', $sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst('246|Ordrefelt 3', $sprog_id)."</span></td><td><input class = 'inputbox' type = 'text' name=\"felt_3\" style=\"width:200px\" value=\"$felt_3\" $disabled></td></tr>\n";
				if (substr(findtekst('247|Ordrefelt 4', $sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst('252|Denne tekst kan rettes under <i>Indstillinger</i> -> <i>Diverse</i> -> <i>Sprog</i><br>Find Id 247 & 252.', $sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst('247|Ordrefelt 4', $sprog_id)."</span></td><td><input class = 'inputbox' type = 'text' name=\"felt_4\" style=\"width:200px\" value=\"$felt_4\" $disabled></td></tr>\n";
				if (substr(findtekst('248|Ordrefelt 5', $sprog_id),0,1)!="#") print "<tr><td><span onmouseover=\"return overlib('".findtekst('253|Denne tekst kan rettes under <i>Indstillinger</i> -> <i>Diverse</i> -> <i>Sprog</i><br>Find Id 248 & 253.', $sprog_id)."',WIDTH=600);\" onmouseout=\"return nd();\">".findtekst('248|Ordrefelt 5', $sprog_id)."</span></td><td><input class = 'inputbox' type = 'text' name=\"felt_5\" style=\"width:200px\" value=\"$felt_5\" $disabled></td></tr>\n";
			}
			if ($betalings_id) print "<tr><td>".findtekst('2534|Betalings-ID', $sprog_id).":</td><td>&nbsp;$betalings_id</td></tr>";
			print "<input type=\"hidden\" name=\"lev_navn\" value=\"$lev_navn\">\n";
			print "<input type=\"hidden\" name=\"lev_addr1\" value=\"$lev_addr1\"><input type=\"hidden\" name=\"lev_addr2\" value=\"$lev_addr2\">\n";
			print "<input type=\"hidden\" name=\"lev_postnr\" value=\"$lev_postnr\"><input type=\"hidden\" name=\"lev_bynavn\" value=\"$lev_bynavn\">\n";
			print "<input type=\"hidden\" name=\"lev_kontakt\" value=\"$lev_kontakt\">\n";
		}
		print "</td></tr></tbody></table></td></tr>\n"; #<- Tabel 4.3
    	$kontonr=(int)$kontonr;
		$row2 = db_fetch_array(db_select("select notes from adresser where kontonr = '$kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__)); #20142403-1
		$notes=str_replace("\n","<br>",if_isset($row2,NULL,'notes'));
		if ($notes) print "<tr><td colspan=\"3\" witdh=\"100%\" style=\"color: rgb(255,0,0)\">$notes</td></tr>\n";
		/*
		$query = db_select("select notes from adresser where kontonr = '$kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__);
		if ($row2 = db_fetch_array($query) ) {
			$notes=str_replace("\n","<br>",$row2['notes']);
			print "<tr><td colspan=\"3\" witdh=\"100%\" style=\"color: rgb(255,0,0)\">$notes</td></tr>\n";
		}
		*/
		if ($udskriv_til=='email') {
			if (!$formularsprog) $formularsprog='Dansk';
			($status<1)?$form_nr=1:$form_nr=2;
			if ($art=='DK')$form_nr=5;
			$q = db_select("select * from formularer where formular='$form_nr' and art='5' and lower(sprog)='".strtolower($formularsprog)."'",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if ($r['xa']=='1') $std_subj=$r['beskrivelse'];
				elseif ($r['xa']=='2') $std_txt_title=$r['beskrivelse'];
			}
			if ($art!='DK')
			$q = db_select("select * from formularer where formular='4' and art='5' and lower(sprog)='".strtolower($formularsprog)."'",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if ($r['xa']=='1') $fak_subj=$r['beskrivelse'];
				elseif ($r['xa']=='2') $fak_text=str_replace("<br>","",$r['beskrivelse']);
			}
			$subj_title='';
			if (!$mail_subj && $art!='DK') $subj_title="".findtekst('1474|Ved fakturering ændres emneteksten til', $sprog_id).":\n\n$fak_subj";
			$text_title='';
			if (!$mail_text && $art!='DK') $text_title="".findtekst('1475|Ved fakturering ændres mailteksten til', $sprog_id).":\n\n$fak_text";
			if($tmp) list($std_txt,$tmp)=explode("<br>",$std_txt_title);
			($mail_text)?$std_txt_title=$mail_text:$std_txt_title=str_replace("<br>","",$std_txt_title);

			print "<tr><td align=\"center\" colspan=\"3\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\"><tbody>\n"; #Tabel 4.4 ->
			if (!$mail_subj && !$mail_text && $art!='DK') print "<tr><td></td><td colspan=\"1\" align=\"left\"><small>".findtekst('2543|Nedenstående tekster ændres ved fakturering, hold musen over beskrivelsen til venstre for at se ændringen.', $sprog_id)."</small></td>";
			print "<tr><td width=\"120px\" title=\"$subj_title\">".findtekst('1476|Mail emne', $sprog_id)."</td><td title=\"$std_subj\"><input class = 'inputbox' type = 'text' style=\"width:1000px;\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_subj\" placeholder=\"$std_subj\" value=\"$mail_subj\" onchange=\"javascript:docChange = true;\"></td>";
			if ($bilag) { 
				if ($dokument) print "<td title=\"".findtekst('1454|klik her for at åbne bilaget', $sprog_id).": $dokument\"><a href=\"../includes/bilag.php?kilde=ordrer&filnavn=$dokument&bilag_id=$id&bilag=$dokument&kilde_id=$id\"><img style=\"border: 0px solid\" alt=\"clip_m_papir\" src=\"../ikoner/paper.png\"></a></td>";
				else print "<td title=\"".findtekst('1455|klik her for at vedhæfte et bilag', $sprog_id)."\"><a href=\"../includes/bilag.php?kilde=ordrer&bilag_id=$id&bilag=$dokument&ny=ja&kilde_id=$id\"><img  style=\"border: 0px solid\" alt=\"clip\" src=\"../ikoner/clip.png\"></a></td>";
			}
			print "</tr><tr><td valign = 'top'  title=\"$text_title\">'".findtekst('585|Mail tekst', $sprog_id)."'</td><td title='\"$std_txt_title\"'>";
			if ($mail_text) print "<textarea style=\"width:1000px;\" rows=\"2\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_text\" onchange=\"javascript:docChange = true;\">$mail_text</textarea>\n";
			else print "<input class = 'inputbox' type = 'text' style=\"width:1000px;\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_text\" placeholder=\"$std_txt\" value=\"$mail_text\" onchange=\"javascript:docChange = true;\">";
			print "</td></tr></tbody></table></td></tr>\n"; # <- Tabel 4.4	
		}
		print "<tr><td align=\"center\" colspan=\"3\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n"; # Tabel 4.5 ->
 		if ($kontonr) {
			print "<tr class=\"tr4-spacing\" >";
			print "<td class=\"pos-spacing\" align=\"center\"><b>⋮⋮</b></td>";


			print "<td class=\"pos-spacing\" align=\"center\" title=\"".findtekst('1477|Positionsnummer. Rækkefølgen ændres ved at overskrive positionsnumrene (1,5 hvis mellem 1 og 2). En enkelt linje slettes ved at skrive minustegn som positionsnummer.', $sprog_id)."\">";
			print "Pos.</td>";
			
			print "<td class=\"varenr-spacing\" align=\"center\" title=\"".findtekst('1478|Varenummer. Skriv hele varenumret eller klik på Opslag for at vælge. Hvis du vil vælge mellem varenumre startende med t, så skriv t* i feltet og klik på Opslag.', $sprog_id)."\">";
			print findtekst('917|Varenr.', $sprog_id)."</td>";
			print "<td class=\"antal-spacing\" align=\"center\" title=\"".findtekst('1479|Antal enheder. Timer og minutter kan angives med : som skilletegn. Eksempelvis 5:45 som bliver til 5,75.', $sprog_id)."\">";
			print findtekst('916|Antal', $sprog_id)."</td>";
			print "<td class=\"enhed-spacing\" style = 'width:4px;'  align=\"center\">".findtekst('945|Enhed', $sprog_id)."</td>"; //Enhed #20240425
			if ($lagerantal > 1) print "<td align=\"center\">".findtekst('608|Lager', $sprog_id)."</td>";
			print "<td class=\"besk-spacing\"  align=\"center\" title=\"".findtekst('1480|Brug [Shift]+[Enter] for et indsætte et linjeskift i en beskrivelseslinje', $sprog_id)."\">".findtekst('914|Beskrivelse', $sprog_id)."</td><td class=\"rabat-spacing\" align=\"center\">".findtekst('915|Pris', $sprog_id)."</td><td align=\"center\">".findtekst('428|Rabat', $sprog_id)."</td>"; //Beskrivelse, pris,  rabat...
			if ($procentfakt) print "<td align=\"center\">".findtekst('1481|Procent', $sprog_id)."</td>";
			print "<td align=\"center\">".findtekst('2373|I alt', $sprog_id)."</td>";
			if ($vis_projekt && !$projekt[0]) print "<td align=\"center\">Proj.</td>";
			if ($genfakt) print "<td align=\"center\" title=\"".findtekst('1482|\'Kun denne ordre\'. Afmærk dette felt hvis ordrelinjen ikke skal med ved genfakturering eller kopiering af ordren.', $sprog_id)."\">".findtekst('2535|KDO', $sprog_id)."</td>\n";
			if ($status>=1 && $hurtigfakt!='on')  {
				if ($art!='DK') {
					$tmp=findtekst('1483|Levér', $sprog_id);
					$tmp2=findtekst('1484|Indtastningsfeltet herunder er det antal, som leveres ved klik på Levér. Antallet i parantes er det, som allerede er leveret.', $sprog_id);
				} else {
					$tmp= findtekst('1485|Modtag', $sprog_id);
					$tmp2= findtekst('1486|Indtastningsfeltet herunder er det antal, som modtages ved klik på Modtag. Antallet i parantes er det, som allerede er modtaget.', $sprog_id);
				}
				print "<td colspan=\"2\" align=\"center\" title=\"$tmp2\">$tmp</td>";
			}
		}
		if ($omkunde) print "<td title =\"".findtekst('1487|Hvis feltet vises er kunden underlagt reglerne for omvendt betalingspligt. Er varen ligeledes omfattet vil feltet herunder være afmærket pr default og momsen vil være undertrykt for den pågældende vare.', $sprog_id)."\">".findtekst('2536|O/B', $sprog_id)."</td>";
		print "</tr>\n";
		if (!$status) $status=0;
		print "<input type=\"hidden\" name=\"status\" value=\"$status\">";
		print "<input type=\"hidden\" name=\"id\" value=\"$id\">\n";

		$x=0;
		if (!$ordre_id) $ordre_id=0;
		$kostpris[0]=$kostsum=0;$blandet_moms=$lagervarer=$tGrossWeight=$tNetWeight=$tVolume=0;
		
			$qtxt="select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($q)) {
			if ($row['posnr']>0 && !is_numeric($row['samlevare'])) {  #Hvis "samlevare" er numerisk,indgaar varen i den ordrelinje,der refereres til - hvis "on" er varen en samlevare.
				$x++;
				$linje_id[$x]        = $row['id'];
				$kred_linje_id[$x]   = $row['kred_linje_id'];
				$posnr[$x]           = $row['posnr'];
				$varenr[$x]          = HtmlEntities(trim($row['varenr']),ENT_COMPAT,$charset);     // Do not remove 'HtmlEntities'
				$beskrivelse[$x]     = HtmlEntities(trim($row['beskrivelse']),ENT_COMPAT,$charset);// Needed with csvimport.
				if ($beskrivelse[$x]==".") $beskrivelse[$x]=""; 
				$enhed[$x]           = HtmlEntities(trim($row['enhed']),ENT_COMPAT,$charset); 
				$lager[$x]           = $row['lager'];
				$pris[$x]            = $row['pris'];
				$rabat[$x]           = $row['rabat']*1;
				$rabatart[$x]        = $row['rabatart'];
				$procent[$x]         = $row['procent']*1;
				$antal[$x]           = $row['antal']*1;
				$leveres[$x]         = $row['leveres'];				
				$vare_id[$x]         = $row['vare_id'];
				$momsfri[$x]         = $row['momsfri'];
				$rabatgruppe[$x]     = $row['rabatgruppe'];
				$m_rabat[$x]         = $row['m_rabat']*-1;
				$folgevare[$x]       = $row['folgevare']*1;
				$varemomssats[$x]    = $row['momssats']*1;
				$fast_db[$x]         = $row['fast_db']*1;
				$saet[$x]            = $row['saet'];
				$lev_varenr[$x]      = $row['lev_varenr'];
				$kostpris[$x]        = $row['kostpris'];
				if ($vare_id[$x]){
					$qtxt = "select netweight,netweightunit,grossweight,grossweightunit,length,width,height from ";
					$qtxt.= "varer where id='$vare_id[$x]'";
					$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$grossWeight[$x]     = $r2['grossweight'];
					$netWeight[$x]       = $r2['netweight'];
					$grossWeightUnit[$x] = $r2['grossweightunit'];
					$netWeightUnit[$x]   = $r2['netweightunit'];
					$itemLength[$x]          = $r2['length'];
					$itemWidth[$x]           = $r2['width'];
					$itemHeight[$x]          = $r2['height'];
					$volume[$x]              = $itemLength[$x]*$itemWidth[$x]*$itemHeight[$x];
				} else {
					$grossWeight[$x]=$netWeight[$x]=$volume[$x]=$itemLength[$x]=$itemWidth[$x]=$itemHeight[$x]=0;
					$grossWeightUnit[$x]=$netWeightUnit[$x]='kg';
				}
				if ($grossWeightUnit[$x] == 'g') $grossWeight[$x] /= 1000;
				if ($netWeightUnit[$x] == 'g')   $netWeight[$x] /= 1000;
				
				$tGrossWeight+=$grossWeight[$x]*$antal[$x];
				$tNetWeight+=$netWeight[$x]*$antal[$x];
				$tVolume+=$volume[$x]*$antal[$x];
				
				($row['omvbet'])?$omvbet[$x]='checked':$omvbet[$x]=NULL;
				if (!$momsfri[$x] && !$varemomssats[$x]) $varemomssats[$x]=$momssats;
				elseif ($varemomssats[$x] > $momssats) $varemomssats[$x]=$momssats;
				elseif ($momsfri[$x] || $omvbet[$x]) $varemomssats[$x]=0;
				$serienr[$x]=trim($row['serienr']);
				$samlevare[$x]=$row['samlevare'];
				$projekt[$x]=$row['projekt'];
				($row['kdo'])?$kdo[$x]='checked':$kdo[$x]=NULL;
				$dbi[$x]=$dg[$x]=$dk_db[$x]=$dk_dg[$x]=$ko_ant[$x]=0;
				if ($vare_id[$x]) { # 20170703
#          $r2=db_fetch_array(db_select("select kostpris from varer where id='$vare_id[$x]'")); # udkommenteret 20170906
#          $kostpris[$x]=$r2['kostpris']*100/$valutakurs; 
#        }
#        if ($vare_id[$x]) {
					if ($fast_db[$x]) {
						$kostpris[$x]=$pris[$x]*$fast_db[$x]; 
#          } else {
#            $r2=db_fetch_array(db_select("select kostpris from varer where id='$vare_id[$x]'"));
#            $kostpris[$x]=$r2['kostpris']*100/$valutakurs;
					}
					if (!$samlevare[$x] || !$vis_saet) $kostsum+=$kostpris[$x]*$antal[$x]; #20170703 Tilføjet if (!$samlevare[$x] || !$vis_saet)
					
					if (!$lager[$x] && $afd_lager) {
						$lager[$x]=$afd_lager;
						db_modify("update ordrelinjer set lager = '$lager[$x]' where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					}
					/*					
				db_modify("update ordrelinjer set kostpris='$kostpris[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
*/
				if ($rabatart[$x]=='amount') $dbi[$x]=$pris[$x]-$rabat[$x]; #20140424 -= 
					else $dbi[$x]=$pris[$x]-($pris[$x]*$rabat[$x]/100); #20140424 -= 
					$dbi[$x]-=$kostpris[$x]; #20140424 -= 
					if ($pris[$x]!=0) $dg[$x]=$dbi[$x]*100/$pris[$x];
					else $dg[$x]=0;
					$dk_db[$x]=dkdecimal($dbi[$x],2);
					$dk_dg[$x]=dkdecimal($dg[$x],2);
				}
				if (($art=='DK')&&($antal[$x]<0)) $bogfor==0;
				if ($serienr[$x]) {
					$serienumre[$x]=NULL;
					$q2 = db_select("select serienr from serienr where salgslinje_id='$linje_id[$x]' order by serienr",__FILE__ . " linje " . __LINE__);
					while ($r2 = db_fetch_array($q2)) ($serienumre[$x])?$serienumre[$x].=','.$r2['serienr']:$serienumre[$x]=$r2['serienr'];
				}
				if (!$lagervarer && $vare_id[$x]) {
					$qtxt = "select grupper.box8 from varer,grupper where varer.id = '$vare_id[$x]' ";
					$qtxt.= "and grupper.art='VG' and grupper.kodenr=varer.gruppe";
					$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					if ($r2['box8']) $lagervarer=1;
				}
			}
		}
		$linjeantal=$x;
		$moms=0;
		$sum=0;
		$ny_pos=0;
		$saetnr=0;
		$saetpris=0;
		for ($x=1; $x<=$linjeantal; $x++) {
		if ($saet[$x]) {
			if ($saetnr && $saetnr!=$saet[$x]) { # tilføjer linjen for sætpris # udeladt 20170318
#        $qtxt="select beskrivelse from ordrelinjer where saet = '$saetnr' and ordre_id='$id' and samlevare='on'";
#        $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#        list($sum,$dbsum,$blandet_moms,$moms)=explode(chr(9),ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,'0','0','0','0',$linje_id[$x],'0','','',$r['beskrivelse'],'',$r['lager'],$saetpris,$rabat[$x],'percent','100','1','0',$vare_id[$x],'','0','0',$momssats,'','on','','','','','','','','','','','0','','0',$saetnr,__LINE__));
				$saetpris=0;
			}
      if (!$saetpris)  print "<tr><td><br></td></tr>";
			$linjesum=$pris[$x]*$antal[$x];
			$linjesum-=$linjesum*$rabat[$x]/100;
			$linjesum+=$linjesum*$varemomssats[$x]/100;
			$saetpris+=afrund($linjesum,3);
			$saetnr=$saet[$x];
		} elseif ($saetnr) { #udeladt 21070318  
#      $r=db_fetch_array(db_select("select beskrivelse from ordrelinjer where saet = '$saetnr' and ordre_id='$id' and samlevare='on'",__FILE__ . " linje " . __LINE__));
#      list($sum,$dbsum,$blandet_moms,$moms)=explode(chr(9),ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,'0','0','0','0','0','0','','',$r['beskrivelse'],'',$r['lager'],$saetpris,'0','percent','100','1','0','0','','0','0','0','','','','','','','','','','','','','0','','0',$saetnr,__LINE__));
			$saetpris=0;
			$saetnr=0;
			print "<tr><td><br></td></tr>";
		}
		if (!$folgevare[$x] || $folgevare[$x]>=0) {
			if (!isset($leveret[$x])) $leveret[$x]=0;
			list($sum,$dbsum,$blandet_moms,$moms,$antal_ialt,$leveres_ialt,$tidl_lev_ialt,$tidl_lev[$x],$levdiff)=
			explode(chr(9),ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,$leveres_ialt,$tidl_lev_ialt,$levdiff,$projekt[0],
			$linje_id[$x],$kred_linje_id[$x],$posnr[$x],$varenr[$x],$beskrivelse[$x],$enhed[$x],$lager[$x],$pris[$x],$rabat[$x],$rabatart[$x],
			$procent[$x],$antal[$x],$leveres[$x],$leveret[$x],$vare_id[$x],$momsfri[$x],$rabatgruppe[$x],$m_rabat[$x],$varemomssats[$x],
			$serienr[$x],$samlevare[$x],$folgevare[$x],$projekt[$x],$kdo[$x],$kobs_ordre_pris,$ko_ant[$x],$kostpris[$x],$dbi[$x],$dg[$x],
			$dk_db[$x],$dk_dg[$x],'0',$omvbet[$x],$saet[$x],$saetnr,$grossWeight[$x],$netWeight[$x],$itemLength[$x],$itemWidth[$x],
			$itemHeight[$x],$volume[$x],__LINE__));
		}
		if ($samlevare[$x]=='on') {
			$q = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' and samlevare = '$linje_id[$x]' order by id",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$antal_ialt+=$r['antal'];
				if ($r['antal']>0) {
					$tmp=0;
					$q2 = db_select("select antal from batch_salg where linje_id = '$r[id]' and ordre_id='$id' and vare_id = '$r[vare_id]'",__FILE__ . " linje " . __LINE__);
					while($r2 = db_fetch_array($q2)) {
						$tmp=$tmp+$r2['antal'];
					}
					if ($art=='DK') $dkantal=dkdecimal($r['antal']*-1,2);
					else $dkantal=dkdecimal($r['antal'],2);
					if (substr($dkantal,-1)=='0') $dkantal=substr($dkantal,0,-1);
					if (substr($dkantal,-1)=='0') $dkantal=substr($dkantal,0,-2);
				}
				$tidl_lev_ialt+=$tmp;
				print "<tr><td></td><td>$r[varenr]</td><td align=\"right\">$dkantal</td><td>$r[enhed]</td><td>$r[lager]</td><td>$r[beskrivelse]</td></tr>";
			}
		}
			print "<input type=\"hidden\" name=\"samlevare[$x]\" value=\"$samlevare[$x]\">\n";
			if ($folgevare[$x]>0) {
				$x_nr=0;
				$fv_linje_id=0;
				for ($i=1;$i<=$linjeantal;$i++) {
					$tmp=$linje_id[$x]*-1;
					if ($tmp==$folgevare[$i]) { 
						$x_nr=$i;
						$fv_linje_id=$linje_id[$i]*1;
						$fv_varenr=$varenr[$i];
						$fv_salgspris=$pris[$i];
						$fv_kostpris=$kostpris[$i];
						$fv_enhed=$enhed[$i];
						$fv_beskrivelse=$beskrivelse[$i];
						$fv_varemomssats=$varemomssats[$i];
						$fv_db=$fv_salgspris-$fv_kostpris;
						($fv_salgspris!=0)?$fv_dg=$fv_db*100/$fv_salgspris:$fv_dg=0;
					}
				}
				if (!$fv_linje_id) {
					global $regnaar;
					$qtxt = "select varer.varenr,varer.beskrivelse,varer.enhed,varer.salgspris,varer.kostpris,varer.grossweight,";
					$qtxt.= "varer.netweight,varer.grossweightunit,varer.netweightunit,varer.length,varer.width,varer.height,";
					$qtxt.= "grupper.box4,grupper.box7 from varer,grupper where ";
					$qtxt.= "varer.id = '$folgevare[$x]' and grupper.art='VG' and grupper.kodenr=varer.gruppe";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$fv_linje_id            = 0;
					$fv_varenr              = $r['varenr'];
					$fv_salgspris           = $r['salgspris'];
					$fv_kostpris            = $r['kostpris'];
					$fv_enhed               = $r['enhed'];
					$fv_beskrivelse         = $r['beskrivelse'];
					$fv_momsfri             = $r['box7'];
					$fv_grossWeight         = $r['grossweight'];
					$fv_netWeight           = $r['netweight'];
					$fv_grossWeightUnit     = $r['grossweightunit'];
					$fv_netWeightUnit       = $r['netweightunit'];
					$fv_itemLength          = $r['length'];
					$fv_itemWidth           = $r['width'];
					$fv_itemHeight          = $r['height'];
					$fv_volume              = $fv_itemLength*$fv_itemWidth*$fv_itemHeight;

          if ($fv_grossWeightUnit == 'g') $fv_grossWeight /= 1000;
          if ($fv_netWeightUnit == 'g')   $fv_netWeight /= 1000;
        
          $tGrossWeight+=$fv_grossWeight*$antal[$x];
          $tNetWeight+=$fv_netWeight*$antal[$x];
          $tVolume+=$fv_volume*$antal[$x];
          $fv_db=$fv_salgspris-$fv_kostpris;
          ($fv_salgspris!=0)?$fv_dg=$fv_db*100/$fv_salgspris:$fv_dg=0;
          $qtxt="select moms from kontoplan where kontonr = '$r[box4]' and regnskabsaar = '$regnaar'";
          $r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
          if ($tmp=trim($r2['moms'])) { # f.eks S3
            $tmp=substr($tmp,1); #f.eks 3
            $qtxt="select box2 from grupper where art = 'SM' and kodenr = '$tmp' and fiscal_year = '$regnaar'";
            $r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
            if ($r2['box2']) $fv_varemomssats=$r2['box2']*1;
          }  else $fv_varemomssats=$momssats;
        }
        $fv_dk_db=dkdecimal($fv_db,2);
        $fv_dk_dg=dkdecimal($fv_dg,2);
        list($sum,$dbsum,$blandet_moms,$moms)=explode(chr(9),ordrelinjer($x_nr,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,$leveres_ialt,$tidl_lev_ialt,$levdiff,$projekt[0],$fv_linje_id,0,$x,$fv_varenr,$fv_beskrivelse,$fv_enhed,$lager[$x],$fv_salgspris,0,'percent',$procent[$x],$antal[$x],$leveres[$x],$leveret[$x],$folgevare[$x],$fv_momsfri,0,0,$fv_varemomssats,0,0,0,$projekt[$x],$kdo[$x],0,0,$fv_kostpris,$fv_db,$fv_dg,$fv_dk_db,$fv_dk_dg,'1','',$saet[$x],$saetnr,$fv_grossWeight,$fv_netWeight,$fv_itemLength,$fv_itemWidth,$fv_itemHeight,$fv_volume,__LINE__));
      }
      
      print "<input type=\"hidden\" name=\"folgevare[$x]\" value=\"$folgevare[$x]\">\n";
      if ($saet[$x] && $saetpris) {
          $y=$x+1;
          if (!$samlevare[$x] && $saet[$x] && ($saet[$x+1]!=$saet[$x] || $samlevare[$x+1])) {
          $r=db_fetch_array(db_select("select id,beskrivelse,lager from ordrelinjer where saet = '$saet[$x]' and ordre_id='$id' and samlevare='on'",__FILE__ . " linje " . __LINE__));
          list($sum,$dbsum,$blandet_moms,$moms)=explode(chr(9),ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,'0','0','0','0','0','0','','',$r['beskrivelse'],'',$r['lager'],$saetpris,'0','percent','100','1','0','0','0','','0','0','0','','','','','','','','','','','','','0','','0',$saetnr,$grossWeight[$x],$netWeight[$x],$itemLength[$x],$itemWidth[$x],$itemHeight[$x],$volume[$x],__LINE__));
          $saetnr=0;
        }
      }
    }
    if ($brugsamletpris && $samlet_rabat && $samlet_pris && $samlet_pris != $ordresum) {
      $x++;
#cho __line__." $sum<br>";
      $diff=afrund($samlet_pris-($sum+$moms),3);
      $tmp=$sum+$moms;
      if ($samlet_rabat) {
        $ms=afrund($moms*100/($sum+$moms),2); #20150318
        $r=db_fetch_array(db_select("select id,beskrivelse from varer where varenr = '$rvnr' or varenr_alias = '$rvnr' or stregkode = '$rvnr'",__FILE__ . " linje " . __LINE__));
        opret_ordrelinje($id,$r['id'],$rvnr,1,$r['beskrivelse'],$diff,$ms,100,'DO','','','0','','','','','','0' ,'0'  ,$lager[0],__LINE__);
        $r=db_fetch_array(db_select("select * from ordrelinjer where ordre_id = '$id' and varenr = '$rvnr'",__FILE__ . " linje " . __LINE__));
        $vist_rabat=$samlet_pris-$bruttosaetsum."|".$samlet_pris;
#cho __line__." $sum<br>";
        db_modify("update ordrelinjer set lev_varenr='$vist_rabat' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
#cho __line__." $sum<br>";
        list($sum,$dbsum,$blandet_moms,$moms)=explode(chr(9),ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,'0','0','0','0',$r['id'] ,'0','99',$rvnr,$r['beskrivelse'],'',$r['lager'],$r['pris'],$r['rabat'],'percent','100','1','0','0',$r['vare_id'],'','0','0',$momssats,'','','','','','','','','','','','','0','','0','0',$grossWeight[$x],$netWeight[$x],$itemLength[$x],$itemWidth[$x],$itemHeight[$x],$volume[$x],__LINE__));
      }
    }
    $sum=afrund($sum,2);
    $moms=afrund($moms,2);
    print "<input type=\"hidden\" name=\"linjeantal\" value=\"$linjeantal\">\n";
    print "<input type=\"hidden\" name=\"lagervarer\" value=\"$lagervarer\">\n";
    if ($status>=1&&$bogfor!=0 && !$leveres_ialt && $tidl_lev_ialt && $antal_ialt != $tidl_lev_ialt) $del_ordre = 'on';
    else $del_ordre = '';
    if ($kontonr) { # && !$disabled
      $x++;
      $antal[0]=1;
      $posnr[0]=$linjeantal+1;
      if ($varenr[0] && isset($_GET['vare_id']) || isset($_GET['varenr'])) { //20150407+20241229
        $fokus="dkan0"; #20150306 + value i dkan0
        $lager[0]=(int)if_isset($_GET,NULL,'lager',0);

        if(isset($_GET['varenr'])){
          //+++++ Insert into varer table if not exist 
          // $rsva = $_GET['varenr'];
          // $qr = db_select("select * from varer where varenr = '$rsva'",__FILE__ . " linje " . __LINE__);
          // if (!$rq = db_fetch_array($qr)) {
          //   echo "Nothing here";
          // }
          //+++++
          $beskrivelse = isset($_GET['beskrivelse']) ? [mb_convert_encoding($_GET['beskrivelse'], 'UTF-8', 'ISO-8859-1')] : null;
          $pris = isset($_GET['salgspris']) ? [mb_convert_encoding($_GET['salgspris'], 'UTF-8', 'ISO-8859-1')] : null;

          $gruppe = isset($_GET['gruppe']) ? $_GET['gruppe'] : null;
          $gruppe =  mb_convert_encoding($gruppe, 'UTF-8', 'ISO-8859-1');
          
          #print "<input type=\"hidden\" name=\"indsat\" value=\"".$_GET['vare_id']."\">"; //For external file, this would be from?
        }else{
          $r=db_fetch_array(db_select("select * from varer where varenr='$varenr[0]' or varenr_alias='$varenr[0]' or stregkode='$varenr[0]'",__FILE__ . " linje " . __LINE__));
          $beskrivelse[0]=$r['beskrivelse'];
          $gruppe = $r['gruppe'];
          $pris[0]=$r['salgspris'];
          print "<input type=\"hidden\" name=\"indsat\" value=\"".$_GET['vare_id']."\">";

        }
        if ($incl_moms || isset($_GET['moms'])) {
          if(isset($_GET['varenr']) || isset($_GET['moms'])){
            //from external source
          }else{
            $qtxt = "select box7 from grupper where art='VG' and kodenr='$gruppe' and box7='on' and fiscal_year = '$regnaar'";
            if($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
              $momsfri[0] = $r2['box7'];
            } else {
              $pris[0]+=$pris[0]*$momssats/100;
            }
          }
        }
       
        
      } 
      else {
        $varenr[0]=NULL;
#        $antal[0]=NULL;
      }
      if (!$lager[0] && $afd_lager) $lager[0]=$afd_lager;
      if ($lagerantal>1) {
        $stockId = $lager[0];
         for ($l=0;$l<count($lagernr);$l++) {
          if ($lagernr[$l]==$lager[0] && strlen($lagernavn[$l])==1) $lager[0]=$lagernavn[$l];
        }
      }
      if ($art != 'OT') { // ordrelinje til indtastning behøves ikke at vises ved 'Original tilbud' #20140716
        $fast_db1=if_isset(if_isset($fast_db, 0, 0), 0, 0);
		// print "<tr class='ordrelinje'>\n";
print "<tr class='ordrelinje' data-line-id='$x'>\n";
        #print "<td colspan = '2' valign = 'top'><input class = 'inputbox' type = 'text' style=\"text-align:right;width:50px;\" name=\"posn0\" value=\"$posnr[0]\"></td>\n";
        // print "<td colspan = '0' valign = 'top'><input class = 'inputbox' type = 'text' style=\"text-align:right;width:50px;\" name=\"posn0\" value=\"$posnr[0]\"></td>\n"; #20240426
//  print "<td valign = 'top'>";

print "<td style='background:#f9f9f9;'></td>"; // Empty cell for input row
print "<td valign='top'><input class='inputbox' type='text' style='text-align:right;width:50px;' name='posn0' value='$posnr[0]'></td>\n";
if ($art=='DK') print "<td valign = 'top'><input class = 'inputbox' readonly=\"readonly\" size=\"12\" name=\"vare0\" onfocus=\"document.forms[0].fokus.value=this.name;\"></td>\n";

        if ($art=='DK') print "<td valign = 'top'><input class = 'inputbox' readonly=\"readonly\" size=\"12\" name=\"vare0\" onfocus=\"document.forms[0].fokus.value=this.name;\"></td>\n";
        else  print "<td valign = 'top'><input class = 'inputbox' style=\"padding:2px;\" type = 'text' size=\"12\" name=\"vare0\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"".$varenr[0]."\"></td>\n"; #20180305
        print "<td valign = 'top'><input class = 'inputbox' type = 'text' style=\"text-align:right;width:50px\" name=\"dkan0\" placeholder=\"$antal[0]\"></td>\n";
        print "<td valign = 'top'><input class = 'inputbox' type = 'text' style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=\"readonly\" size=\"4px\"></td>\n"; #enhed0
        //print "<td valign = 'top'><input class = 'inputbox' type = 'text' size=\"58\" name=\"beskrivelse0\" onfocus=\"document.forms[0].fokus.value=this.name;\"></td>\n";
        if ($lagerantal>1) {
          print "<td valign = 'top'>";
          print "<input type=\"hidden\" name=\"lager[0]\" value=\"$lager[0]\">";
          print "<input class = 'inputbox' type = 'text' style=\"text-align:right;width:35px\" name=\"lagr0\" placeholder=\"$lager[0]\"></td>\n";
        }
        print "<td valign = 'top'><textarea class=\"autosize inputbox ordreText comment\" id=\"comment\" rows=\"1\" cols=\"58\" name=\"beskrivelse0\" placeholder=\"".$beskrivelse[0]."\" onfocus=\"document.forms[0].fokus.value=this.name; var val=this.value; this.value=''; this.value= val;\"></textarea></td>\n"; #2013.11.27 Ændret til textarea, så hele texten vises #2013.11.29 indsat ny onfocus da chrome ikke satte curser efter tekst
        print "<td valign = 'top'><input class = 'inputbox' type = 'text' style=\"text-align:right\" size=\"10\" name=\"pris0\" placeholder=\"".dkdecimal($pris[0],2)."\"></td>\n";
        print "<td valign = 'top'><input class = 'inputbox' type = 'text' style=\"text-align:right\" size=\"4\" name=\"raba0\">\n";
        if ($procentfakt) print "</td><td valign = 'top'><input class = 'inputbox' type = 'text' style=\"text-align:right\" size=\"4\" name=\"proc0\" value=\"100,00\">\n";
        else print "<input type=\"hidden\" name=\"proc0\" value=\"100,00\">\n";
        print "<input type = 'hidden' name = 'fast_db[0]' value = '".$fast_db1."'></td>";
        print "<td valign='top'>
        <input class='inputbox' 
        type='text' 
        style='background: none repeat scroll 0 0 #e4e4ee' 
        readonly='readonly' 
        size='10'>
        </td>\n";
        if ($vis_projekt && !$masterprojekt) print "<td></td>";  
        if ($genfakt) print "<td title=\"".findtekst('1488|Afmærk dette felt hvis ordrelinjen ikke skal med ved genfakturering / kopiering.', $sprog_id)."\"><input class = 'inputbox' name=\"kdo[0]\" type=\"checkbox\"></td>\n";
		if($status  < 2){

			print "<td> </td>"; // Empty cell for input row
			print "<td> </td>"; // Empty cell for input row
		}
        $txt1489 = str_replace('\n', "\n", findtekst('1489|Indsættes for at lave fed tekst. Sæt cursoren imellem <b> og </b>. (F.eks. <b>Lorem ipsum</b>)', $sprog_id));
        $txt1490 = str_replace('\n', "\n", findtekst('1490|Indsættes for at lave kursiv tekst. Sæt cursoren imellem <i> og </i>. (F.eks. <i>Lorem ipsum</i>) Kan også bruges til tom linje. Her insættes <i></i> uden tekst.', $sprog_id));
        print "<td valign = 'top' colspan=\"2\"><input type=\"button\" name=\"insert\" class=\"button white small bold\" value=\"B\" onClick=\"this.form.beskrivelse0.value=this.form.beskrivelse0.value.concat('<b></b>'); this.form.beskrivelse0.focus();\" title=\"$txt1489\">\n"; #2013.11.29 Sætter fokus på felt ved clik
        print "<input type=\"button\" name=\"insert\" class=\"button white small italic\" value=\"I\" onClick=\"this.form.beskrivelse0.value=this.form.beskrivelse0.value.concat('<i></i>'); this.form.beskrivelse0.focus();\" title='$txt1490'></td>\n";
        print "</tr>\n";
      }
      if ($procenttillag) {
        $r=db_fetch_array(db_select("select beskrivelse from varer where varenr = '$procentvare' or varenr_alias = '$procentvare' or stregkode = '$procentvare'",__FILE__ . " linje " . __LINE__));
        $tillag=$sum*$procenttillag/100;
        $beskr=var2str($r['beskrivelse'],$id,$posnr[$x],$varenr[$x],$dkantal[$x],$enhed[$x],$dkpris,$dkprocent,$serienr[$x],$varemomssats[$x],$dkrabat[$x]);
        $beskr=str_replace('$procenttillæg;',dkdecimal($procenttillag,2),$beskr);
        print "<tr>\n";
        print "<td></td>\n";
        print "<td>$procentvare</td>\n";
        print "<td></td>\n";
        print "<td></td>\n";
        print "<td>$beskr</td>\n";
        print "<td></td>\n";
        print "<td></td>\n";
        print "<td></td>\n";
        print "<td align=right>".dkdecimal($tillag,2)."</td>\n";
        if ($art!='OT') print "<td></td>\n"; #20140716
        print "</tr>\n";
        $sum+=$tillag;
        $dbsum+=$tillag;
        $moms+=$tillag/100*$momssats;
      }
      print "<input type=\"hidden\" name=\"sum\" value=\"$sum\">\n";
      if (!$blandet_moms && !$incl_moms) $moms=$sum*$momssats/100; #tilfojet 20100923 grundet afrundingsfejl på ordre med rabat
      $moms=afrund($moms*1,3);
      $kostpris[0]*=1;
      if ($sum < 100000000000 && $kostsum < 100000000000) {
        db_modify("update ordrer set sum=$sum,kostpris=$kostsum,moms=$moms where id=$id",__FILE__ . " linje " . __LINE__);
      } elseif ($sum >= 100000000000) print tekstboks("Beløbet (".dkdecimal($sum).") er for stort, reducer pris eller vareantal");
      else print tekstboks("Kostsummen (".dkdecimal($kostsum).") er for stor, reducer kostpris eller vareantal");
      if ($art=='DK') {
        $sum=$sum*-1;
        $moms=$moms*-1;
      }
      $ialt=($sum+$moms);

      $r=db_fetch_array(db_select("select sum(antal) as total from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__));
      
      print "<tr><td colspan='12'><table bordercolor='#FFFFFF' border='1' cellspacing='0' cellpadding='0' width='100%'><tbody>\n"; # Tabel 4.5.1 ->
      print "<tr>\n";
      print "<td width=\"14.2%\" align=\"center\">Nt/Bt ". number_format($tNetWeight, 1, ',', '.') ."/";
      print number_format($tGrossWeight, 1, ',', '.') ." Kg</td>\n";
      print "<td width=\"14.2%\" align=\"center\">";
      print findtekst("2373|I alt",$sprog_id)." ".rtrim(rtrim(dkdecimal($r["total"], 3), '0'), ",")." / ";
      print number_format($tVolume, 0, ',', '.') ." cm&sup3;</td>\n";
      print "<td align=\"center\">";
      print findtekst('2372|Nettosum',$sprog_id) ." ".dkdecimal($sum,2)."</td>\n";
      if ($vis_saet) $dkb=$sum-$kostsum;
      else $dkb=$dbsum;
      print "<td width=\"14.2%\" align=\"center\"  title=\"DB: $baseCurrency ".dkdecimal($dkb*$valutakurs/100,2)."\">";
      if (!$vis_saet) print "DB: ".dkdecimal($dkb,2);
      print "</td>\n";
      if ($sum) $dg_sum=($dkb*100/$sum);
      else $dg_sum=dkdecimal(0,2);
      print "<td width=\"14.2%\" align=\"center\"  title=\"DG:".dkdecimal($dg_sum,2)."%\">";
      if (!$vis_saet) print "DG: ".dkdecimal($dg_sum,2)."%";
      print "</td>\n";
      print "<td width=\"14.2%\" align=\"center\" align=\"center\">";
      print findtekst('770|Moms', $sprog_id).":&nbsp;".dkdecimal($moms,2)."</td>\n";
      print "<td width=\"14.2%\" align=\"center\" align=\"center\" title=\"DG:".dkdecimal($dg_sum,2)."%\">";
      print findtekst('2373|I alt',$sprog_id) .":";
      if ($brugsamletpris && $art=='DO') {
        print "<input type=\"hidden\" name=\"ordresum\" value=\"".afrund($ialt,2)."\">";
        print "<input style=\"width:100px;text-align:right\" type = 'text' name=\"samlet_pris\" value=\"".dkdecimal($ialt,2)."\">";
      } else print dkdecimal($ialt,2);
      print "</td>\n";
    }
    print "</tbody></table></td></tr>\n"; # <- Tabel 4.5.1
    if ($fokus!='dkan'.count($vare_id)) print "<input type=\"hidden\" name=\"fokus\">\n"; #20151019
    print "<tr><td align=\"center\" colspan=\"12\">\n";
    print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>\n"; # Tabel 4.5.2 ->

    $qtxt="select var_value from settings where var_name='orderNoteEnabled'";
    if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
      if ($r['var_value']) {
        $qtxt="select notes from ordrer where id='$id'";
        $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
        $orderNoteText=$r['notes'];
        ($orderNoteText)?$color='red':$color='black';
        print "<div class='textareaBeholder'>";
        print "<a href='#nav'><span title='".findtekst('1713|Udvid tekstfeltet ved at hive i hjørnet', $sprog_id)."' style='color:$color; font-weight:bold;'>".findtekst('1712|Bemærkning til ordre', $sprog_id)."</span></a><br />\n";
        print "<div class='expandable' id='nav'><div class='textwrapper'>";
        print "<textarea rows='3' style='width:100%;' name='orderNoteText'>".$orderNoteText."</textarea>";
        print "</div>\n";
        print "</div>\n";
        print "</div>\n";
      }
    }

		if ($status < 3) {
			if ($levdiff) $status=1;
			elseif ($status==1) $status++;
			//if ($status<1) $width="33%";
			//elseif ($sum!=0) $width="25%";
			if ($hurtigfakt=='on' && $fakturadato) print "<input type=\"hidden\" name=\"levdato\" value=\"$fakturadato\">\n";
			print "<input type=\"hidden\" name=\"valutakurs\" value=\"$valutakurs\">\n";
			print "<input type=\"hidden\" name=\"status\" value=\"$status\">\n";
			$txt = findtekst('3|Gem', $sprog_id);
			print "<td align=\"center\" width=$width><input type=\"submit\" class=\"button green medium\" id=\"submit\" style=\"width:75px;\" accesskey=\"g\" value=\"$txt\" name = 'save' onclick=\"javascript:docChange = false;\"></td>\n";
			
			if ($art!='OT') { # Fjerner knappen opslag hvis art er = OT (original tilbud) #20140716
			$txt = findtekst('644|Opslag', $sprog_id);
			print "<td align=\"center\" width=$width><input type=\"submit\" class=\"button blue medium\" style=\"width:75px;\" accesskey=\"o\" value=\"$txt\" name ='lookUp' "; #
				if ( $art == "DK" ) print "disabled=\"disabled\" ";
				print "onclick=\"javascript:docChange = false;\"></td>\n";
			}
		
			if ($status==1&&$bogfor!=0 && $hurtigfakt!='on' && $leveres_ialt) {
				if ($art== 'DO') print "<td align=\"center\" width=$width><input type=\"submit\" class=\"button gray medium\" style=\"width:75px;\" accesskey=\"l\" value=\"".findtekst('1483|Levér', $sprog_id)."\" name=\"b_submit\" onclick=\"javascript:docChange = false;\"></td>\n";
				else print "<td align=\"center\" width=$width title=\"".findtekst('1491|Klik her for at tage varer retur', $sprog_id)."\"><input type=\"submit\"  class=\"button gray medium\" style=\"width:75px;\" accesskey=\"l\" value=\"".findtekst('1485|Modtag', $sprog_id)."\" name=\"b_submit\" onclick=\"javascript:docChange = false;\"></td>\n";
			}
				$confirm2  = findtekst('1524|Faktura sendes som email til', $sprog_id); #Faktura sendes som email til
				$confirm3  = findtekst('1525|Faktura tilføjes PBS/Nets liste!', $sprog_id);
				$confirm4  = findtekst('1526|Faktura genereres som OIOUBL fil!', $sprog_id); #Faktura genereres som OIOUBL fil!
				$confirm5  = findtekst('1527|Fakturadato sættes til', $sprog_id);
				$confirm6  = findtekst('1528|Betalingsbetingelser er sat til', $sprog_id);
				$confirm7  = findtekst('1529|dage og betaling til', $sprog_id);
				$confirm8  = findtekst('1530|Vil du det?', $sprog_id);
				$confirm9  = findtekst('1531|Fakturadato er ikke idag!', $sprog_id);
				$confirm10 = findtekst('1533|Ordre omdannes til kreditnota ved fakturering!', $sprog_id);
				$confirm11 = findtekst('1534|Kreditnota sendes pr. mail til', $sprog_id);
				$confirm12 = findtekst('1535|Tilbud udskrives som PDF. Ved fakturering udskrives til OIOUBL', $sprog_id);
				$confirm13 = findtekst('1536|Ordrebekrftelse udskrives som PDF. Ved fakturering udskrives til OIOUBL', $sprog_id);
				$confirm14 = findtekst('1537|Tilbud sendes pr mail til', $sprog_id);
				$confirm15 = "";
				$confirm16 = findtekst('1538|Ordrebekræftelse sendes pr mail til', $sprog_id);
				$txt1	   = findtekst('1539|Klik her for at opdele ordren i 2.<br>Den ene vil indeholde ikke leverede varer<br>Den anden vil indeholde leverede varer', $sprog_id);
				$txt2      = findtekst('1540|Handicappet', $sprog_id);
				$txt3      = findtekst('1541|Vælg betalingsmåde', $sprog_id);

				$tiltext   = findtekst('1532|Beløb til betaling stemmer ikke', $sprog_id);
			if (($status==2&&$bogfor!=0)||($status>0&&$hurtigfakt=='on')) {
				$disabled=NULL;
				$titletext='';
				$tmp="";
				$dd=date("d-m-Y");
				
				if ($art!='DK' && !$dan_kn) {
					if ($udskriv_til=='email') $tmp="onclick=\"return confirm('$confirm2 $email')\"";
					elseif ($udskriv_til=='PBS') $tmp="onclick=\"return confirm('$confirm3')\"";
					elseif ($udskriv_til=='oioubl') $tmp="onclick=\"return confirm('$confirm4')\"";
					elseif (!$fakturadato) $tmp="onclick=\"return confirm('$confirm5 $dd!')\"";
					else $tmp="";
					if ($vis_saet) {
						if (($betalingsbet=='Netto' || $betalingsbet=='Lb.md') && is_numeric($felt_2) && $felt_2!=0) { #20150313
							$tmp="onclick=\"return confirm('$confirm6 $betalingsbet $betalingsdage $confirm7 $felt_1!\\\n $confirm8')\"";
						} else {
							if ($fakturadate && $fakturadate!=date('Y-m-d')) $tmp="onclick=\"return confirm('$confirm9\\\n $confirm8')\"";
						}
						$diff=abs($felt_2+$felt_4 - ($sum+$moms));						
						if ($diff > 0.01) {
							$disabled='disabled';
							$titletext="$tiltext ($felt_2+$felt_4 - $sum+$moms = $diff)";
						}	
						$query = db_select("SELECT * FROM settings WHERE var_name = 'showPaymentLink' AND var_grp = 'deb_ordre' AND var_value = 'on'",__FILE__ . " linje " . __LINE__);
						if($r = db_fetch_array($query)){
							$vis_betalingslink = 1;
						}else{
							$vis_betalingslink = 0;
						}
						if (!$betalt1 && $vis_betalingslink) $disabled='disabled';
					} 
					// Made for Havemøbelshoppen
					if($ref == "Magento" || $felt_1 == "Konto" || $felt_1 == "Kontant" || $afd_navn == "Webshop"){
						$disabled='';
					}
					$txt = findtekst('2374|Fakturér',$sprog_id); 
					print "<td align='center' width='$width' title='$titletext'><input $disabled type='submit' class='button gray medium' style='width:75px;' accesskey='f' value='$txt' name='doInvoice' $tmp></td>\n";
				} else {
					if ($vis_saet) {
						$disabled=NULL;$titletext='';
						if ($art=='DO') $diff=afrund(($felt_2+$felt_4)-($sum+$moms),2);
						else $diff=afrund(($felt_2+$felt_4)+($sum+$moms),2);
						if ($diff) {
							$disabled =  $txt2;
							$titletext= $txt3;
						}
					}
					if ($art=='DO' && $dan_kn) $tmp="onclick=\"return confirm('$confirm10')\"";
					if ($mail_fakt) $tmp="onclick=\"return confirm('$confirm11 $email')\"";
					print "<td align=\"center\" width=\"$width\" title=\"$titletext\"><input $disabled type=\"submit\" class=\"button gray medium\" style=\"width:75px;\" accesskey=\"f\" value=\"Kredit&eacute;r\" name=\"b_submit\" $tmp></td>\n";
				}
			} elseif ($del_ordre == 'on') {
				$txt="$txt1";
				print "<td align=\"center\" width=\"$width\" >
					<span onmouseover=\"return overlib('$txt',WIDTH=800);\" onmouseout=\"return nd();\">
					<input type=\"submit\" class=\"button gray medium\" accesskey=\"f\" value=\"Del ordre\" name=\"b_submit\" style=\"width:75px;\" onclick=\"javascript:docChange = false;\"></span></td>\n";
			}
			if ($linjeantal>0 && $konto_id && ($art=='DO' || $art=='OT')) { # skal også med ved 'original tilbud' (OT) #20140716
				if ($udskriv_til=='oioubl' && $status <= 1) $tmp="onclick=\"return confirm('$confirm12')\"";
				elseif ($udskriv_til=='oioubl' && $status < 3) $tmp="onclick=\"return confirm('$confirm13')\"";
				elseif ($mail_fakt && $status < 1) $tmp="onclick=\"return confirm('$confirm14 $email')\"";
				elseif ($mail_fakt && $hurtigfakt && $status < 3) $tmp="onclick=\"return confirm('$confirm15 $email')\"";
				elseif ($mail_fakt && $status < 2) $tmp="onclick=\"return confirm('$confirm16 $email')\"";
				else $tmp="";
				if ($udskriv_til=='email') {
					$b_name = 'send';
					$txt  = findtekst('2310|Send', $sprog_id);
				} else {
					$b_name = 'print';
					$txt = findtekst('880|Udskriv', $sprog_id);
				}
        
        print "<td align=\"center\" width=$width><input type=\"submit\" class=\"button gray medium\" style=\"width:75px;\" value=\"$txt\" name=\"$b_name\" $tmp title=\"$tekst2\" onclick=\"javascript:docChange = false;\"></td>\n";
			}
		if ($art!='DK') print "<td align=\"center\"><input type=\"submit\" class=\"button gray medium\" style=\"width:75px;\" value=\"".findtekst('1100|Kopier', $sprog_id)."\" name=\"copy\" title=\"".findtekst('1459|Kopiér til ny ordre med samme indhold', $sprog_id)."\"></td>\n";
		$txt = findtekst('2375|Sæt',$sprog_id);
		if ($status<3 && !$betalt && $vis_saet && $konto_id) {
			print "<td align=\"center\" width=$width><input type=\"button\" class=\"button gray medium\" style=\"width:75px;\" value=\"$txt\" name=\"ret_saet\" title=\"".findtekst('1498|Klik her for at oprette eller rette i varesæt', $sprog_id)."\" onclick=\"jacascript:window.location.href='saetpris.php?id=$id'\"></td>\n";
		} elseif ($status<3 && $brugsamletpris && $svnr && $konto_id) {
			print "<td align=\"center\" width=$width><input type=\"button\" class=\"button gray medium\" style=\"width:75px;\" value=\"$txt\" name=\"ret_saet\" title=\"".findtekst('1498|Klik her for at oprette eller rette i varesæt', $sprog_id)."\" onclick=\"jacascript:window.location.href='saetpris.php?id=$id'\"></td>\n";
		}
		$tekst=findtekst('155|Vil du slette denne ordre?', $sprog_id);
		$tekst2=findtekst('156|Klik her for at slette hele ordren. Hvis du blot vil slette en ordrelinje skal du sætte - (minus) som pos.nr.', $sprog_id);
		if(count($leveret)==0 && !$betalt && $art!='OT' && $id) {
			print "<td align=\"center\"><input type=\"submit\" class=\"button rosy medium\" style=\"width:75px;\" value=\"".findtekst('1099|Slet', $sprog_id)."\" name=\"delete\" onclick=\"return confirm('$tekst')\" title=\"$tekst2\"></td>\n";
		}
		#this is such incase lerevet is aleady array, it won't return wrong value
	#    }
		if ($art=='OT' && $sag_id) {
			print "<td align=\"center\"><a class=\"button gray medium mozMedium\" style=\"\" ";
			print "title=\"".findtekst('1492|klik her for at kopiér tilbud til ny sag', $sprog_id)."\" ";
			print "href=\"../sager/sager.php?funktion=kopi_ordre&amp;sag_id=$sag_id&amp;konto_id=$konto_id&amp;";
			print "ordre_id=$id&amp;returside=ordre\">".findtekst('1493|Kopiér', $sprog_id)."</a></td>\n";
		}
			print "</tbody></table></td></tr>\n"; # <- Tabel 4.5.2
			print "</form>\n";
			print "</tbody></table></td></tr>\n"; # <- Tabel 4.5
		
			//print "<tr><td></td></tr>\n";
		} # end if ($status < 3)
		if ($konto_id) $r=db_fetch_array(db_select("select kreditmax from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		if ($kreditmax=if_isset($r['kreditmax'])*1) { #20210719 checked whether it is set as it was throwing a boolean error
		if ($valutakurs) $kreditmax=$kreditmax*100/$valutakurs;
			$q=db_select("select * from openpost where konto_id = '$konto_id' and udlignet='0'",__FILE__ . " linje " . __LINE__);
			$tilgode=0;
			while($r=db_fetch_array($q)) {
        if (!$r['valuta']) $r['valuta']=$baseCurrency;
				if (!$r['valutakurs']) $r['valutakurs']=100;
        if ($valuta==$baseCurrency && $r['valuta']!=$baseCurrency) $opp_amount=$r['amount']*$r['valutakurs']/100;
        elseif ($valuta!=$baseCurrency && $r['valuta']==$baseCurrency) {
					if ($r3=db_fetch_array(db_select("select kurs from grupper,valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = grupper.kodenr and valuta.valdate <= '$r[transdate]' order by valuta.valdate desc"))) {
						$opp_amount=$r['amount']*100/$r3['kurs'];
						$alert = findtekst('1850|Ingen valutakurs for faktura', $sprog_id);
					} elseif ($valuta) print "<BODY onLoad=\"javascript:alert('$alert $r[faktnr]')\">\n";
					}
        elseif ($valuta!=$baseCurrency && $r['valuta']!=$baseCurrency && $r['valuta']!=$valuta) {
					$tmp==$r['amount']*$r['valuta']/100;
		 			$opp_amount=$tmp*100/$r['valutakurs'];
        }  else $opp_amount=$r['amount'];
				$tilgode=$tilgode+$opp_amount;
			}
			if ($kreditmax<$ialt+$tilgode) {
				$tmp = dkdecimal(($ialt+$tilgode)-$kreditmax,2);
				$alert1 = findtekst('1851|Kreditloft overskrides med', $sprog_id); #20210809
				print "<big><span style='color:#FF0000';>$alert1 $valuta $tmp</span></big><br>\n";
			}
		}# end  if ($kreditmax....
		print "</tbody></table></td></tr>\n"; # <- Tabel 4
		print "</form>\n"; # 
	}# end else for (if ($status>=3))

	# Malene, dette er enden

	# ADD LINK TO GLS!! 
	$qtxt="select var_name,var_value from settings where var_grp='GLS'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if ($r['var_name']=='gls_id')    $gls_id    = $r['var_value'];
		if ($r['var_name']=='gls_user')  $gls_user  = $r['var_value'];
		if ($r['var_name']=='gls_pass')  $gls_pass  = $r['var_value'];
		if ($r['var_name']=='gls_ctId')  $gls_ctId  = $r['var_value'];
		if ($r['var_name']=='dfm_id')    $dfm_id    = $r['var_value'];
		if ($r['var_name']=='dfm_user')  $dfm_user  = $r['var_value'];
		if ($r['var_name']=='dfm_pass')  $dfm_pass  = $r['var_value'];
		if ($r['var_name']=='dfm_agree') $dfm_agree = $r['var_value'];
		if ($r['var_name']=='dfm_hub')   $dfm_hub   = $r['var_value'];
		if ($r['var_name']=='dfm_type')  $dfm_type  = $r['var_value'];
	}

	if ( ($gls_user) || (($dfm_user) && ($status>=3)) ) print "<tr><td align=\"center\"><br>";
	if ($gls_user) {
		$q=db_select("select gls_label, fedex_label from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array($q);

		$gls_stil = $r["gls_label"] == "t" ? "background-color: #6bff92; border: 1px #8f8f9d solid; border-radius: 3px;" : "";
		$fedex_stil = $r["fedex_label"] == "t" ? "background-color: #6bff92; border: 1px #8f8f9d solid; border-radius: 3px;" : "";

		 //print "<form name=\"form\" action=\"http://api.gls.dk/ws/\"  method=\"POST\">".
		print "<form name=\"GLS\"  method=\"POST\">";
		print "<input type=\"hidden\" name=\"tGrossWeight\" value=\"$tGrossWeight\">\n";
		print "\n<input type=\"submit\" name=\"gls_go\" value=\"GLS Label\" style='$gls_stil' onclick=\"this.style.color = 'gray'\"></form>"; 
/* GLS knap slut */
    	print "<form name=\"fedexlabel_form\" action=\"https://www.fedex.com/shipping/shipEntryAction.do\" target=\"_blank\" method=\"POST\">";
	  	$txtWeight=ceil($tGrossWeight);
		if ($txtWeight < 1) $txtWeight=1;
		print
			"\n<input type=\"hidden\" name=\"txtAction\" value=\"70120\">".            //this is a must!
			"\n<input type=\"hidden\" name=\"txtConsigneeNo\" value=\"".$kontonr."\">".        //this is a must!
			"\n<input type=\"hidden\" name=\"txtWeight\" value=\"$txtWeight\">".
			"\n<input type=\"hidden\" name=\"txtCountryNum\" value=\"208\">".        //country codes can be fund in source on GLS site.
			"\n<input type=\"hidden\" name=\"billingData.referenceData.yourReference\" value=\"".$ordrenr."\">".  //ordre ref.
			"\n<input type=\"hidden\" name=\"notificationData.recipientNotifications.email\" value=\"".$email."\">".
			"\n<input type=\"hidden\" name=\"notificationData.recipientNotifications..pickupNotificationFlag\" value=\"true\">".
			"\n<input type=\"hidden\" name=\".companyName\" value=\"".$firmanavn."\">".
			"\n<input type=\"hidden\" name=\"toData.addressLine1\" value=\"".$addr1."\">".
			"\n<input type=\"hidden\" name=\"toData.addressLine2\" value=\"".$addr2."\">".
			"\n<input type=\"hidden\" name=\"toData.city\" value=\"".$bynavn."\">".
			"\n<input type=\"hidden\" name=\"toData.zipPostalCode\" value=\"".$postnr."\">".
			"\n<input type=\"hidden\" name=\"toData.contactName\" value=\"".$kontakt."\">".
			"\n<input type=\"hidden\" name=\"toData.countryCode\" value=\"$landekode\">".
			"\n<input type=\"hidden\" name=\"toData.taxID\" value=\"".$cvrnr."\">".   //kunde org.nr
			"\n<input type=\"hidden\" name=\"toData.phoneNumber\" value=\"".$tlf."\">".  //tlf.nummer
			"\n<input type=\"hidden\" name=\"psdData.numberOfPackages\" value=\"1\">".  //antal pakker
			"\n<input type=\"hidden\" name=\"psdData.mpsRowDataList[0].weight\" value=\"1\">".  //pakkens vægt i kg
			"\n<input type=\"hidden\" name=\"psdData.serviceType\" value=\"International Economy\">".  //fedex fragttype
			"\n<input type=\"hidden\" name=\"psdData.packageType\" value=\"Your Packaging\">".  //emballage type
			"\n<input type=\"hidden\" name=\"psdData.serviceType\" value=\"International Economy\">".  //
			"\n<input type=\"hidden\" name=\"billingData.selectedBillDutiesAndTaxIndex\" value=\"R\">".  //modtager betaler afgifter
			"\n<input type=\"hidden\" name=\"billingData.referenceData.invoiceNumber\" value=\"$fakturanr\">";  //fedex fragttype
			"\n<input type=\"hidden\" name=\"commodityData.totalCustomsValue\" value=\"$sum\">".  //fedex fragttype
			"\n<input type=\"hidden\" name=\"commodityData.documentShipping\" value=\"false\">";  //
			# 20190502
		print "\n<input type=\"hidden\" name=\"toData.addressData.countryCode\" value=\"$landekode\">"; 
		if(!empty($lev_navn)) print "\n<input type=\"hidden\" name=\"toData.addressData.companyName\" value=\"".$lev_navn."\">";
		else print "\n<input type=\"hidden\" name=\"toData.addressData.companyName\" value=\"".$firmanavn."\">";
		if(!empty($lev_postnr)) {
			print "\n<input type=\"hidden\" name=\"toData.addressData.zipPostalCode\" value=\"".$lev_postnr."\">";
			print "\n<input type=\"hidden\" name=\"toData.addressData.city\" value=\"".$lev_bynavn."\">";
			print "\n<input type=\"hidden\" name=\"toData.addressData.addressLine1\" value=\"".$lev_addr1."\">";
			print "\n<input type=\"hidden\" name=\"toData.addressData.addressLine2\" value=\"".$lev_addr2."\">";
		} else {
			print "\n<input type=\"hidden\" name=\"toData.addressData.zipPostalCode\" value=\"".$postnr."\">";
			print "\n<input type=\"hidden\" name=\"toData.addressData.city\" value=\"".$bynavn."\">";
			print "\n<input type=\"hidden\" name=\"toData.addressData.addressLine1\" value=\"".$addr1."\">";
			print "\n<input type=\"hidden\" name=\"toData.addressData.addressLine2\" value=\"".$addr2."\">";
		}
		if(!empty($lev_kontakt)) print "\n<input type=\"hidden\" name=\"toData.addressData.contactName\" value=\"".$lev_kontakt."\">";
		else print "\n<input type=\"hidden\" name=\"toData.addressData.contactName\" value=\"".$kontakt."\">";
		print "\n<input type=\"submit\" value=\"Send til Fedex\" style='$fedex_stil' onclick=\"this.style.color = 'gray'\"></form>"; 
		print "</td></tr>";
	}	
	if (($dfm_user) && ($status>=3)) {
		$dfm_q=db_select("select consignmentid from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
		if ($dfm_row = db_fetch_array($dfm_q)) $confignmentnr=$dfm_row['consignmentid'];
		if ( strlen($confignmentnr) > 1 ) {  // Confignment number exists
			print "<tr><td align=\"center\">\n";
			print "<span style='font-weight:bold; font-size: 16px;'>".findtekst('1057|Danske Fragtmænd', $sprog_id)."</span><!--Danske Fragtmænd--><br />\n";
			print "\n\n<p>";
			print findtekst('1037|Fragtbrev', $sprog_id).": ".$confignmentnr;
			print "</p>\n\n";
		} else { 
			print "<tr><td align=\"center\">\n";
			print "<span style='font-weight:bold; font-size: 16px;'>".findtekst('1057|Danske Fragtmænd', $sprog_id)."</span><!--Danske Fragtmænd--><br />\n";

			$qtxt="select var_name,var_value from settings where var_grp='GLS'";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				if ($r['var_name']=='dfm_gooddes') $form_gooddes = $r['var_value'];
			}

			if ( ! empty($dfm_go) ) { 
				print "\n\n<p>";
				print $dfm_go;
				if ( stristr($dfm_go,"catest") ) $form_gooddes="catest";
				print "</p>\n\n";
			}
			print "<form name=\"DFM\"  method=\"POST\">\n";
			print "<center><table width=50% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
			print "<tr><td>".findtekst('1042|Levering til privatadresse', $sprog_id).": </td>\n";
			print "<td><input type=\"checkbox\" name=\"form_prodcode\" checked value=\"DayP\"></td>\n</tr>\n";
			print "<tr>\n<td>";
			print "".findtekst('1041|Beskrivelse af gods som standard', $sprog_id).": </td>\n";
			print "<td><input type = 'text' name=\"form_gooddes\" value=\"$form_gooddes\">";
			print "</td>\n</tr>\n</table></center>\n";
			print "<input type=\"hidden\" name=\"tGrossWeight\" value=\"$tGrossWeight\">\n";
			print "<center><br><input type=\"submit\" name=\"dfm_go\" value=\"Opret fragtbrev til Danske Fragtmænd\"></center></form>"; ; 
		}
	}
	if ( ($gls_user) || (($dfm_user) && ($status>=3)) ) print "</tr></td>\n";

	print "<!--Function ordreside slut-->";
}

function ordrelinjer($x,$sum,$dbsum,$blandet_moms,$moms,$antal_ialt,$leveres_ialt,$tidl_lev_ialt,$levdiff,$masterprojekt,$linje_id,$kred_linje_id,$posnr,$varenr,$beskrivelse,$enhed,$lager,$pris,$rabat,$rabatart,$procent,$antal,$leveres,$leveret,$vare_id,$momsfri,$rabatgruppe,$m_rabat,$varemomssats,$serienr,$samlevare,$folgevare,$projekt,$kdo,$kobs_ordre_pris,$ko_ant,$kostpris,$dkb,$dg,$dk_db,$dk_dg,$readonly,$omvbet,$saet,$saetnr,$grossWeight,$netWeight,$itemLength,$itemWidth,$itemHeight,$volume,$linje) {
	print "<!--function ordrelinjer start-->";
	global $art;
	global $brugsamletpris;
	global $difkto;
	global $genfakt;
	global $fokus,$flgv;
	global $hurtigfakt;
	global $incl_moms,$id;
	global $lagerantal,$lagernavn,$lagernr;
	global $momssats;
	global $ny_pos;
	global $omkunde;
	global $procentfakt;
	global $reserveret,$rvnr;
	global $status;
	global $valuta,$valutakurs,$vis_projekt,$vis_saet;
	global $tdlv,$txt370;
	global $sprog_id;

	if (!isset($reserveret[$x])) $reserveret[$x]=0;
	$beskrivelse=str_replace("&lt;br&gt;","\r\n",$beskrivelse);
	$beskrivelse=str_replace("&lt;BR&gt;","\r\n",$beskrivelse);
	$dkantal=$dkpris=$dkprocent=$dkrabat=$tidl_lev=0;
	# if (!$x) $x--; #20160915
	if ($folgevare) $flgv=$folgevare;
	if (!$samlevare || !$brugsamletpris) $ny_pos++; #20150317 
	#lse cho "$beskrivelse $pris<br>";
#  if (!$ny_pos) $ny_pos=1;
	if ($readonly) $readonly="readonly=\"readonly\"";
	if ($varenr) {
		if ($rabatart=='amount') $ialt=($pris-$rabat)*$antal;
		else $ialt=($pris-($pris/100*$rabat))*$antal;
		if ($procentfakt) {
			$ialt*=$procent/100;
		} else $procent=100; 
		$ialt=afrund($ialt,3); # 20150130 rettet til 3 decimaler
		$sum+=$ialt;
	
		$dkpris=dkdecimal($pris,2);
		$dkrabat=dkdecimal($rabat,5);
		while(substr($dkrabat,-1) == '0') $dkrabat = trim($dkrabat,'0');
		if ((substr($dkrabat,0,1) == ','))  $dkrabat = '0'.$dkrabat;	
		if ((substr($dkrabat,-1) == ','))  $dkrabat = trim($dkrabat,',');
		$dkprocent=dkdecimal($procent,2);
		if ($momsfri!='on') {
			$moms+=afrund($ialt*$varemomssats/100,3); # 20150130 rettet til 3 decimaler
			if ($varemomssats!=$momssats) $blandet_moms=1;#tilfojet 20100923 grundet afrundingsfejl på ordre med rabat
			if($incl_moms)$dkpris=dkdecimal($pris+$pris*$varemomssats/100,2);
		} else $blandet_moms=1;#tilfojet 20100923 grundet afrundingsfejl på ordre med rabat
		if ($antal) {
			if ($art=='DK') $dkantal=dkdecimal($antal*-1,2);
			else $dkantal=dkdecimal($antal,2);
			if (substr($dkantal,-1)=='0') $dkantal=substr($dkantal,0,-1);
			if (substr($dkantal,-1)=='0') $dkantal=substr($dkantal,0,-2);
		}
  	}  else {$antal=0; $dkantal=''; $dkpris=''; $dkrabat=''; $ialt='';}
	($art=='OT' || $saetnr || ($rvnr && $rabat))?$disabled='disabled':$disabled=NULL; // Her disables inputfield hvis art er OT. #20140716
#  if ($x) {
		print "<input type=\"hidden\" name=\"linje_id[$x]\" value=\"$linje_id\">\n";
		print "<input type=\"hidden\" name=\"kred_linje_id[$x]\" value=\"$kred_linje_id\">\n";
		print "<input type=\"hidden\" name=\"vare_id[$x]\" value=\"$vare_id\">\n";
		print "<input type=\"hidden\" name=\"antal[$x]\" value=\"$antal\">\n";
		print "<input type=\"hidden\" name=\"serienr[$x]\" value=\"$serienr\">\n";
		print "<input type=\"hidden\" name=\"momsfri[$x]\" value=\"$momsfri\">\n";
		print "<input type=\"hidden\" name=\"varemomssats[$x]\" value=\"$varemomssats\">\n";
		print "<input type=\"hidden\" name=\"proc$x\" value=\"$procent\">\n";
		print "<input type=\"hidden\" name=\"saet[$x]\" value=\"$saet\">\n";
		print "<input type=\"hidden\" name=\"samlevare[$x]\" value=\"$samlevare\">\n";
		print "<input type=\"hidden\" name=\"kostpris[$x]\" value=\"$kostpris\">\n"; #20170906
		print "<input type=\"hidden\" name=\"lager[$x]\" value=\"$lager\">\n";
#  if ($art=='OT' || $saetnr || ($rvnr && $rabat)) { // Når input fields er 'disabled' bliver de ikke opdateret, derfor tilføjes hidden fields
		print "<input type=\"hidden\" name=\"beskrivelse$x\" value=\"$beskrivelse\">\n";
		if ($fokus != "pris$x") print "<input type=\"hidden\" name=\"pris$x\" value=\"$dkpris\">\n";
		print "<input type=\"hidden\" name=\"raba$x\" value=\"$dkrabat\">\n";
		print "<input type=\"hidden\" name=\"vare$x\" value=\"$varenr\">\n"; #Tilføjet 20161011 Hvis fjernes fungerer "samlet pris ikke"
		print "<input type=\"hidden\" name=\"posn$x\" value=\"$ny_pos\">\n";
		if ($fokus=='dkan'.$x) { #20151019
			print "<input type=\"hidden\" name=\"dkantal[$x]\" value=\"$dkantal\">\n";
			print "<input type=\"hidden\" name=\"fokus\" value=\"pris$x\">\n";
		}
#  }
	$prplho=NULL;

	print "<td></td>";

	if ($fokus=='pris'.$x) { #20151019
		if ($pris == 0) $prplho="placeholder=\"0,00\"";
		else $fokus='vare0';
	} 
    #  }
	if ($saet && $samlevare) {
		print "<input type=\"hidden\" name=\"posn$x\" value=\"$ny_pos\">\n";
		print "<input type=\"hidden\" name=\"vare$x\" value=\"$varenr\">\n";
    print "<input type=\"hidden\" name=\"dkan$x\" value=\"$dkantal\" onfocus='this.select()'>\n";
  }  else {
		$txtColor = 'black';
		$qtyTitle = '';
		$stockQty = $min_lager = 0;
		if ($lager && $vare_id) {
			$g = 0;
			$stockGrp = array();
			$qtxt = "select kodenr as grp from grupper where art = 'VG' and box8 = 'on'";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$stockGrp[$g] = $r['grp'];
				$g++;
			}
			$qtxt = "select min_lager,gruppe from varer where varer.id = '$vare_id'"; #20210503
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				if (in_array($r['gruppe'],$stockGrp) && $r['min_lager'] > 0) {
					$min_lager = $r['min_lager'];
					$qtxt = "select sum(beholdning) as qty from lagerstatus where vare_id = '$vare_id'";
					($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$stockQty=$r['qty']:$stockQty=0;
				} 
			}
			if ($stockQty < $min_lager) {
				$txtColor ='red';
				$qtyTitle = "Obs!! Beholdning (". dkdecimal($stockQty,0) .") mindre end ". dkdecimal($min_lager,0);
      }  elseif ($stockQty) $qtyTitle = "Beholdning: ". dkdecimal($stockQty,0);
		}	
		($x)?$y=NULL:$y='_';
    // print "<tr class='ordrelinje'>\n";
print "<tr class='ordrelinje' data-line-id='$x'>\n";
#    print "<td valign = 'top'><div onClick='this.form.submit();'><a>X</a></div></td>";
    // print "<input class = 'inputbox' type = 'text' $readonly style=\"text-align:right;width:50px;\" name=\"posn$x\" value=\"$ny_pos\" $disabled></td>\n";
    print "<td class='drag-handle' style='cursor:move; text-align:center; vertical-align:middle; width:30px; background:#f5f5f5;'>⋮⋮</td>";

// print "<td class='drag-handle' style='cursor:move; text-align:center; vertical-align:middle; width:30px; background:#f5f5f5;'>⋮⋮</td>";
print "<td valign='top'><input class='inputbox' type='text' style='text-align:right;width:50px;' name='posn$x' value='$ny_pos' $disabled></td>\n";
		$title = "Nt/Bt ". number_format($grossWeight, 1, ',', '.') ."/". number_format($netWeight, 1, ',', '.') ." kg. ";
		$title.= "L: ". number_format($itemLength, 0, ',', '.') ." B: ". number_format($itemWidth, 0, ',', '.') ." ";
		$title.= "H: ". number_format($itemHeight, 0, ',', '.') ." =  ". number_format($volume, 0, ',', '.') ." cm&sup3;";
		print "<td valign='top' title='$title'>";
		print "<input class='inputbox' type='text' style='background: none repeat scroll 0 0 #e4e4ee' readonly=\"readonly\" ";
		print "size=\"12\" name=\"vare$y$x\" onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$varenr\" ";
		print "onchange=\"javascript:docChange = true;\" $disabled></td>\n";
		if ($fokus=='dkan'.$x) {
      		print "<td valign = 'top' title = '$qtyTitle'><input class = 'inputbox' type = 'text' ";
      		print "style=\"color:$txtColor;text-align:right;width:50px\" $readonly name=\"dkan$x\" placeholder=\"$dkantal\" value=\"\" $disabled onfocus='this.select()'></td>\n";
    	} else {
      		print "<td valign = 'top' title = '$qtyTitle'><input class = 'inputbox' type = 'text' style=\"color:$txtColor;text-align:right;width:50px;\" $readonly name=\"dkan$x\" value=\"$dkantal\" $disabled onfocus='this.select()'></td>\n";
		}
    	print "<td valign = 'top'><input class = 'inputbox' type = 'text' style=\"background: none repeat scroll 0 0 #e4e4ee\" readonly=\"readonly\" size=\"4px\" value=\"$enhed\" onchange=\"javascript:docChange = true;\" $disabled></td>\n";
		$lagerId = $lager;
		if ($lagerantal > 1) {
			for ($l=0;$l<count($lagernr);$l++) {
				if ($lagernr[$l]==$lager && strlen($lagernavn[$l])==1) {
					$lager=$lagernavn[$l];
				}
			}
      		print "<td valign = 'top'><input class = 'inputbox' type = 'text' style=\"text-align:right;width:35px\" name=\"lagr$x\" value=\"$lager\" onchange=\"javascript:docChange = true;\" $disabled></td>\n";
		}
		$title=var2str($beskrivelse,$id,$posnr,$varenr,$dkantal,$enhed,$dkpris,$dkprocent,$serienr,$varemomssats,$dkrabat);
    	//print "<td valign = 'top' title=\"$title\"><input class = 'inputbox' type = 'text' $readonly size=\"58\" name=\"beskrivelse$x\" value=\"$beskrivelse\" onchange=\"javascript:docChange = true;\"></td>\n";

		if (($rvnr && $varenr==$rvnr) || ($saetnr && $samlevare)) $dis=$disabled;
		elseif ($saetnr || ($rvnr && $rabat)) $dis=NULL;
		else $dis=$disabled;
    	print "<td valign = 'top' title=\"$title\"><textarea class=\"autosize inputbox ordreText comment\" $readonly rows=\"1\" cols=\"58\" name=\"beskrivelse$x\" onchange=\"javascript:docChange = true;\" $dis>$beskrivelse</textarea></td>\n";
	}
	if ($saet) {
		print "<td><input type=\"hidden\" name=\"pris$x\" value=\"$dkpris\"></td><td>
     		<input class = 'inputbox' type=\"hidden\" name=\"raba$x\" value=\"$dkrabat\"></td>
			<input type=\"hidden\" name=\"posn$x\" value=\"$ny_pos\">\n";
	} elseif ($saetnr) {
   		print "<td><input type=\"hidden\" name=\"pris$x\" value=\"".dkdecimal($pris,2)."\"></td><td><input class = 'inputbox' type=\"hidden\" name=\"raba$x\" value=\"0\"></td>";
	} elseif (!$rvnr) {
		print "<td valign = 'top' title=\"".findtekst('1499|Kost', $sprog_id).": ".dkdecimal($kostpris,2)." - db: $dk_db - dg: $dk_dg%\"><input class = 'inputbox' type = 'text' $readonly style=\"text-align:right;\" size=\"10\" $prplho name=\"pris$x\" value=\"$dkpris\" onchange=\"javascript:docChange = true;\" onfocus=\"if(this.value == '0,00') {this.value=''}\" onblur=\"if(this.value == ''){this.value ='0,00'}\" $disabled></td>\n"; #2013.11.29 Fjerner 0,00 ved fokus, og tilføjer 0,00 hvis feltet er tomt
		$title=$dkantal."*".dkdecimal(($rabat/100)*$pris,2)."% = ".dkdecimal($antal*($rabat/100)*$pris,2);
    	print "<td valign = 'top' title=\"$title\"><input class = 'inputbox' type = 'text' $readonly style=\"text-align:right\" size=\"4\" name=\"raba$x\" value=\"$dkrabat\" onchange=\"javascript:docChange = true;\" onfocus=\"if(this.value == '0,00') {this.value=''}\" onblur=\"if(this.value == ''){this.value ='0,00'}\" $disabled></td>\n";
	} else print "<td></td><td></td>";
		
	if ($procentfakt) {
    	print "<td valign = 'top' title=\"$title\"><input class = 'inputbox' type = 'text' $readonly style=\"text-align:right\" size=\"4\" name=\"proc$x\" value=\"$dkprocent\" onchange=\"javascript:docChange = true;\" $disabled></td>\n";
		$dkb=$dkb-((100-$procent)/100*$pris);
	}
	$dkb=(float)$dkb*(float)$antal;
	if ($ialt && $ialt!=0) $dg=$dkb*100/$ialt;
	else $dg=$ialt=0;
	$dbsum=$dbsum+$dkb;
	$dk_db=dkdecimal($dkb,2);
	$dk_dg=dkdecimal($dg,2);
	if ($art=='DK') $ialt=(float)$ialt*-1;
	if ($varenr) {
		if ($rvnr) {
			$disabled='disabled';
			if ($incl_moms && !$momsfri) $tmp=dkdecimal($antal*($pris+$pris*$varemomssats/100),2);
			else $tmp=dkdecimal($pris,2);
		} else {
			if ($incl_moms && !$momsfri) $tmp=dkdecimal($ialt+$ialt*$varemomssats/100,2);
			else $tmp=dkdecimal($ialt,2);
		}
	}
	else $tmp=NULL;
	if ($saet) {
		print "<td></td>"; 
	} elseif ($saetnr || $varenr==$rvnr) {
		if ($saetnr) {
			$qtxt="select lev_varenr from ordrelinjer where samlevare='on' and saet='$saetnr' and ordre_id='$id'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		} else {
			$qtxt="select lev_varenr from ordrelinjer where varenr='$rvnr' and ordre_id='$id'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		}	
		list($lev_vnr)=explode("|",$r['lev_varenr']);	
    	print "<td valign = 'top' align=\"right\" title=\"db: $dk_db - dg: $dk_dg%\"><input class = 'inputbox' type = 'text' readonly=\"readonly\" style=\"background: none repeat scroll 0 0 #e4e4ee; text-align:right\" size=\"10\" value=\"".dkdecimal($lev_vnr,2)."\" disabled></td>\n";
	} else {
    	print "<td valign = 'top' align=\"right\" title=\"db: $dk_db - dg: $dk_dg%\"><input class = 'inputbox' type = 'text' readonly=\"readonly\" style=\"background: none repeat scroll 0 0 #e4e4ee; text-align:right\" size=\"10\" value=\"$tmp\" $disabled></td>\n";
	}
	if ($vis_projekt && !$masterprojekt) {
    	print "<td><select class = 'inputbox' name=\"projekt[$x]\">\n";
		$list=array();
		$beskriv=array();
		$z=0;
		$q = db_select("select * from grupper where art = 'PRJ' and kodenr != '0' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)){
			$z++;
			$list[$z]=$r['kodenr'];
			$beskriv[$z]=$r['beskrivelse'];
		}
		for ($a=0; $a<=$z; $a++) {
			if ($projekt!=$list[$a]) print "<option  value=\"$list[$a]\" title=\"$beskriv[$a]\">$list[$a]</option>\n";
			else print "<option value=\"$list[$a]\" title=\"$beskriv[$a]\" selected=\"selected\">$list[$a]</option>\n";
		}
		print "</select></td>";
	}
	if ($genfakt) print "<td title=\"".findtekst('1494|Afmærk dette felt hvis ordrelinjen ikke skal med ved genfakturering / kopiering.', $sprog_id)."\"><input class = 'inputbox' name=\"kdo[$x]\" type=\"checkbox\" $kdo></td>\n";

#       }
#      else print "<td></td>";
	if ($status>=1&&$hurtigfakt!='on') {
		if ($vare_id || $varenr){
			$batch="?";
#          print "<td title=\"kostpris\">Projekt</span></td>\n";
			$tidl_lev=0;
			if ($lagerantal > 1) {
				$qtxt = "select sum(beholdning) as qty from lagerstatus where vare_id = '$vare_id' and lager = '$lagerId'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$stockQty = $r['qty'];
			}
			$qtxt = "select gruppe,beholdning from varer where id = $vare_id";
			$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$beholdning=$row['beholdning'];
			if ($lagerantal >1) $beholdning = $stockQty;
			$query = db_select("select box6,box8,box9 from grupper where art='VG' and kodenr='$row[gruppe]'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			($row['box6']=='on')?$omvare=1:$omvare=0; # vare som er omfattet af omvendt betalingspligt 
			($row['box8']=='on')?$lagervare=1:$lagervare=0;
			($row['box9']=='on')?$batchvare=1:$batchvare=0;
				$q = db_select("select * from batch_salg where linje_id = '$linje_id' and ordre_id=$id and vare_id = $vare_id",__FILE__ . " linje " . __LINE__);
				while($r = db_fetch_array($q)) {
					$y++;
					$batch='V';
					$tidl_lev=$tidl_lev+$r['antal'];
				if ($batchvare) {
					$z=0;
					$query = db_select("select * from reservation where vare_id = $vare_id",__FILE__ . " linje " . __LINE__);
          			while ($row = db_fetch_array($query))  {
					 if (($row['linje_id']==$linje_id)||($row['batch_salg_id']==$linje_id*-1)) {
							$z=$z+$row['antal'];
							$batch="V";
						}
						elseif ($row['batch_kob_id']<0) $reserveret[$x]+=+$row['antal'];
#            elseif ($row['batch_salg_id']==0) $paavej=$paavej+$row['antal'];
					}
					if($z+$tidl_lev<$antal) $batch="?";
				}
				else $batch="";
				if (($tidl_lev<$antal)||($batch=="?")) $status=1;
				if ($folgevare) $tdlv=$tidl_lev;
				elseif ($flgv) {
					$tidl_lev=$tdlv;
					$flgv=NULL;
				}
			}
			if ($art=='DK') {
				$dklev=dkdecimal($leveres*-1,2);
				$dk_tidl_lev=dkdecimal($tidl_lev*-1,2);
				$lever_modtag="modtag";
			} else {
				$dklev=dkdecimal($leveres,2);
				$dk_tidl_lev=dkdecimal($tidl_lev,2);
				$lever_modtag="lever";
			}

			if (substr($dklev,-1)=='0') $dklev=substr($dklev,0,-1);
			if (substr($dklev,-1)=='0') $dklev=substr($dklev,0,-2);
			if (substr($dk_tidl_lev,-1)=='0') $dk_tidl_lev=substr($dk_tidl_lev,0,-1);
			if (substr($dk_tidl_lev,-1)=='0') $dk_tidl_lev=substr($dk_tidl_lev,0,-2);
			print "<input type=\"hidden\" name=tidl_lev[$x] value=\"$dk_tidl_lev\">\n";
			$temp=$beholdning-$reserveret[$x];
			$status=2;
			$beholdning=$beholdning*1;
			$beholdning=dkdecimal($beholdning,2);
			if (substr($beholdning,-1)=='0') $beholdning=substr($beholdning,0,-1);
			if (substr($beholdning,-1)=='0') $beholdning=substr($beholdning,0,-2);
			if (!$lagervare) $beholdning="ikke lagerført";
			$tmp=afrund(abs($antal)-abs($tidl_lev),2); #20131004
			if ($samlevare && $saet) {

				$tmp=NULL;
			} else {
				if ($tmp) {
					if (abs($antal)!=abs($tidl_lev)) {
						print "<td title=\"".findtekst('1500|Lagerbeholdning', $sprog_id).": $beholdning. Mangler fortsat at ".$lever_modtag."e resten.\"><input class = 'inputbox' $readonly type = 'text' style=\"background: none repeat scroll 0 0 #ffa; text-align:right\" size=\"4\" name=\"leve$x\" value=\"$dklev\" onchange=\"javascript:docChange = true;\"></td>\n";
					} else {
						print "<td title=\"".findtekst('1500|Lagerbeholdning', $sprog_id).": $beholdning. Intet ".$lever_modtag."et endnu.\"><input class = 'inputbox' $readonly type = 'text' style=\"text-align:right\" size=\"4\" name=\"leve$x\" value=\"$dklev\" onchange=\"javascript:docChange = true;\"></td>\n";
					}
					print "<td title=\"".findtekst('1495|Tidligere', $sprog_id)." ".$lever_modtag."et $dk_tidl_lev på denne ordre.\">($dk_tidl_lev)</td>\n";
					if ($batchvare && $antal>0) print "<td align=\"center\" onClick=\"batch($linje_id)\" title=\"".findtekst('1496|Vælg fra købsordre', $sprog_id)."\"><img alt=\"".findtekst('1497|Serienummer', $sprog_id)."\" src=\"../ikoner/serienr.png\"></td>\n";
					elseif ($serienr) print "<td align=\"center\" onClick=\"serienummer($linje_id)\" title=\"".findtekst('1501|Vælg serienr', $sprog_id)."\"><img alt=\"".findtekst('1497|Serienummer', $sprog_id)."\" src=\"../ikoner/serienr.png\"></td>\n";
					$levdiff=1;
				} else {
					if ($antal==$tidl_lev) $dklev=0;
					print "<td title=\"".findtekst('1500|Lagerbeholdning', $sprog_id).": $beholdning. Alt ".$lever_modtag."et.\"><input class = 'inputbox' type = 'text' readonly=\"readonly\" style=\"background: none repeat scroll 0 0 #e4e4ee; text-align:right\" size=\"4\" name=\"leve$x\" value=\"$dklev\" onchange=\"javascript:docChange = true;\"></td>\n";
					print "<td title=\"".findtekst('1495|Tidligere', $sprog_id)." ".$lever_modtag."et $dk_tidl_lev på denne ordre.\">($dk_tidl_lev)</td>\n";
				}

				if ($linje_id && $leveret!=$tidl_lev) db_modify("update ordrelinjer set leveret=$tidl_lev where id=$linje_id",__FILE__ . " linje " . __LINE__);
			}

		}
     else {
    print "<td></td>";
    print "<td></td>";

	 }
 
  } elseif ($serienr) { 

    print "<td align=\"center\" onClick=\"serienummer($linje_id)\" title=\"".findtekst('1501|Vælg serienr', $sprog_id)."\"><img alt=\"".findtekst('1497|Serienummer', $sprog_id)."\" src=\"../ikoner/serienr.png\"></td>\n"; #20210715
	
	}

	else {
    // print "<td></td>";

	}

#      if ($samlevare=='on') print "<td align=\"center\" onClick=\"stykliste($vare_id)\" title=\"Vis stykliste\"><img alt=\"Stykliste\" src=\"../ikoner/stykliste.png\"></td>\n";
	if (!$saetnr) {
    $txt2130 = findtekst('2130|Slet ordrelinje', $sprog_id);
    print "<td valign = 'top' align='right' title='$txt2130'>";
    print "<button type='button' style='background: red; color: #fff;' ";
    print "onclick=\"if (confirm('Slet linje $x?')) { document.getElementsByName('posn$x')[1].value='-'; ";
    print "document.getElementsByName('ordre')[0].submit.click(); }\">X</button></td>\n";
	} else print "<td></td>";
	if (!$rabat && $m_rabat && !$rabatgruppe) {
		print "</tr><tr>\n";
		print "<td><input class = 'inputbox' readonly=\"readonly\" style=\"text-align:right;width:50px;\" value=$x></td>\n";
		print "<td><input class = 'inputbox' readonly=\"readonly\" size=\"12\" value=\"\"></td>\n";
		print "<td><input class = 'inputbox' readonly=\"readonly\" style=\"text-align:right;width:50px\" value=\"$dkantal\"></td>\n";
		print "<td><input class = 'inputbox' readonly=\"readonly\" size=\"3\" value=\"$enhed\"></td>\n";
		#print "<td><input class = 'inputbox' readonly=\"readonly\" style=\"text-align:right;width:35px\" value=\"$lager\"></td>\n";
		$rabatpct=afrund($m_rabat*100/$pris,2);
		($rabatart=='amount')?$rabattxt=findtekst('466|Mængderabat', $sprog_id):$rabattxt=findtekst('467|Mængderabat $rabatpct %', $sprog_id);
		$rabattxt=str_replace('$rabatpct',$rabatpct,$rabattxt);
		$title=var2str($rabattxt,$id,$posnr,$varenr,$dkantal,$enhed,$dkpris,$dkprocent,$serienr[$x],$varemomssats,$dkrabat);
    	print "<td title=\"$title\"><input class = 'inputbox' readonly=\"readonly\" size=\"58\" value=\"$rabattxt\"></td>\n";
		if ($momsfri!='on') {
			$moms+=afrund($m_rabat*$antal*$varemomssats/100,2);
		  if ($varemomssats!=$momssats) $blandet_moms=1;#tilfojet 20100923 grundet afrundingsfejl på ordre med rabat
		} 
		$sum+=afrund($m_rabat*$antal,2); #20180725
		if ($incl_moms) $m_rabat+=$m_rabat*$varemomssats/100;
		print "<td><input class = 'inputbox' readonly=\"readonly\" style=\"text-align:right\" size=\"10\" value=\"".dkdecimal($m_rabat,2)."\"></td>\n";
		print "<td><input class = 'inputbox' readonly=\"readonly\" style=\"text-align:right\" size=\"4\" value=\"\" onchange=\"javascript:docChange = true;\"></td>\n";
		print "<td><input class = 'inputbox' readonly=\"readonly\" style=\"text-align:right\" size=\"10\" value=\"".dkdecimal($m_rabat*$antal,2)."\"></td>\n";
		
	}

  	if ($omkunde) print "<td valign = 'top'><input class = 'inputbox' type=\"checkbox\" style=\"background: none repeat scroll 0 0 #e4e4ee\" name=\"omvbet[$x]\" onchange=\"javascript:docChange = true;\" $omvbet></td>\n";
      
	print "</tr>\n";
	if ($readonly) {
		print "<input type=\"hidden\" name=\"posn$x\" value=\"$ny_pos\">\n";
		#print "<input type=\"hidden\" name=\"vare$x\" value=\"$varenr\">\n";
		print "<input type=\"hidden\" name=\"dkan$x\" value=\"$dkantal\">\n";
		print "<input type=\"hidden\" name=\"beskrivelse$x\" value=\"$beskrivelse\">\n";
		print "<input type=\"hidden\" name=\"pris$x\" value=\"$dkpris\">\n";
		print "<input type=\"hidden\" name=\"raba$x\" value=\"$dkrabat\">\n";
	}
		$antal_ialt=$antal_ialt+$antal; #10.10.2007
	$leveres_ialt=$leveres_ialt+abs($leveres); #abs tilfoejet 2009.01.26 grundet manglende lev_mulighed med ens antal positive og negative leveringer i ordre 98 i saldi_104
	$tidl_lev_ialt=$tidl_lev_ialt+$tidl_lev; #10.10.2007
	return($sum.chr(9).$dbsum.chr(9).$blandet_moms.chr(9).$moms.chr(9).$antal_ialt.chr(9).$leveres_ialt.chr(9).$tidl_lev_ialt.chr(9).$tidl_lev.chr(9).$levdiff);
	print "<!--function ordrelinjer slut-->";
} # endfunc ordrelinjer;

function find_vare_id ($varenr) {
	$query = db_select("select id from varer where varenr = '$varenr' or varenr_alias = '$varenr' or stregkode = '$varenr'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {return $row[id];}
}

######################################################################################################################################
function find_konto_id ($kontonr) {
	$query = db_select("select id from adresser where kontonr = '$kontonr'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {return $row[id];}
}
######################################################################################################################################
function find_betalingsdage ($konto_idnr) {
	$query = db_select("select betalingsdage from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {return $row[betalingsdage];}
}
###########################################################################################################################
/*
function batch ($linje_id) {
	$leveres=0;
	$query = db_select("select * from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$antal=$row['antal'];
		$leveres=$row['leveres'];
		$posnr=$row['posnr'];
		$vare_id=$row['vare_id'];
		$varenr=$row['varenr'];
		$serienr=$row['serienr'];
		$query = db_select("select status,art,konto_id,ref from ordrer where id = '$row[ordre_id]'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$konto_id=$row['konto_id'];
		$status=$row['status'];
		$art=$row['art'];

		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$row[ref]'",__FILE__ . " linje " . __LINE__))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager=$row['kodenr']*1;}
		}
	}

	$query = db_select("select * from batch_salg where linje_id = $linje_id",__FILE__ . " linje " . __LINE__);
	while($row = db_fetch_array($query)) $leveres=$antal-$row[antal];

	if (($antal>=0)&&($art!="DK")&&($vare_id)){
		$x=0;
		$rest=array();
		$lev_rest=$leveres;

		if (isset($lager)) $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 and lager = $lager order by kobsdate",__FILE__ . " linje " . __LINE__);
		else $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 order by kobsdate",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$x++;
			$batch_kob_id=$row['id'];
			$kobsdate=$row['kobsdate'];
			$rest=$row['rest'];
			$reserveret=0;
#      $pris=$row[pris];
			$q2 = db_select("select ordrenr from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
			$r2 = db_fetch_array($q2);
			$ordrenr=$r2[ordrenr];
			if ($rest>=$lev_rest) {
				$valg=$lev_rest;
				$lev_rest=0;
			}
			else {
				$valg=$rest;
				$lev_rest=$lev_rest-$rest;
			}
		}
		$batch_antal=$x;
	}
	if ($lev_rest==0) {
		 db_modify("delete from reservation where linje_id=$linje_id",__FILE__ . " linje " . __LINE__);
		 $temp=$linje_id*-1;
		 db_modify("delete from reservation where batch_salg_id=$temp",__FILE__ . " linje " . __LINE__);
		 for ($x=1; $x<=$batch_antal; $x++){
			 $lager=$lager*1;
			 if (($valg>0)&&(!$res_linje_id)) {db_modify("insert into reservation (linje_id,vare_id,batch_kob_id,antal,lager) values ($linje_id,$vare_id,$batch_kob_id,$valg,$lager)",__FILE__ . " linje " . __LINE__);}
			 elseif (($valg>0)&&($res_linje_id)) {db_modify("insert into reservation (linje_id,vare_id,batch_salg_id,antal,lager) values ($res_linje_id,$vare_id,$temp,$valg,$lager)",__FILE__ . " linje " . __LINE__);}
		 }
	}
}
*/
##############################################################################
function indsaet_linjer($ordre_id,$linje_id,$posnr) {
	$posnr = str_replace('+',':',$posnr); #jeg ved ikke hvorfor,men den vil ikke splitte med "+"
	list ($posnr,$antal) = explode (':',$posnr);
	if (is_numeric($posnr) && is_numeric($antal)) {
		db_modify("update ordrelinjer set posnr='$posnr' where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		for ($x=1; $x<=$antal; $x++) {
			db_modify("insert into ordrelinjer (posnr,ordre_id) values ('$posnr','$ordre_id')",__FILE__ . " linje " . __LINE__);
		}
	}
}
##############################################################################
if ($fokus) {
	print "<script language=\"javascript\">
	document.ordre.$fokus.focus();
	</script>";
}
print "</tbody></table>";

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
?>
<!--  -->

<script src="../javascript/tablenav.js"></script>



<link rel="stylesheet" href="orderIncludes/ordre_dragdrop.css">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="orderIncludes/ordre_dragdrop.js"></script>
<!-- <script src ="orderIncludes/invoice_dragdrop.js"></script> -->




