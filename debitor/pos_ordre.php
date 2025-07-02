<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre.php -----patch 4.1.1 ----2025-07-02--------------
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
//
// 2013-03-10 - Tilføjet mulighed for at give rabat på varer uden pris ved at skrive "r" efter prisen. Søg 20130310
// 2013.05.07 - Tilføjet visning af kostpris v. mus over pris.
// 2013.08.24 - Rettet i bogføringsrutine for funktion posbogfor så optælling af primosaldo kun fra start af aktivt regnskabsår,
//	alternativt henter kasseprimo fra grupper (sættes under diverse > POS valg).
// 2013.08.24	-	Rettet i funktion kassebeholdning så salg på kort nu ikke tæller fra alle kassers salg. (Tilføjet kasse_nr i transaktioner)
// 2013.08.27	-	Fejl i funktion pos_txt_print "dkdecimal($dkkmodtaget2)" rettet til dkdecimal($modtaget2)
// 2013.10.15	-	Manglende momsberegning v. mængderabat. Søg	20131015
// 2013.12.05 -	En række tilretning vedr kasseintegration og afrunding til hele 50 ører. 
// 2013.12.10	- Betalingsterminal aktiveres kun hvis kort er afmærket som betalingskort under diverse/pos_valg. Søg 20131210
// 2014.01.29 - Indsat automatisk genkendelse af registrerede betalingskort, (Kun med integreret betalingsterminal) Søg 20140129, $kortnavn eller 'Betalingskort'
// 2014.03.18 - Omdøbt funktion opret_ordre + div. kald til opret_posordre grundet konflikt med opret_ordre fra sagssystem
// 2014.04.26 - Indsat vare id foran varenr i kald til opret_ordrelinje grundet ændring i funktionen (PHR - Danosoft) Søg 20140426 
// 2014.05.08 - Indsat diverse til bordhåndtering, bruger nr fra ordrer til bordnummer (PHR - Danosoft) Søg 20140508 eller $bordnr 
// 2014.05.26 - Indsat hentning af momssats fra ordre hvis den ikke er sat inden indsættelse af ordrelinje (PHR - Danosoft) Søg 20140526
// 2014.05.27 - Flyttet hentning af momssats til under "opret_posordre" (PHR - Danosoft) Søg 20140527
// 2014.06.03 - Tilføjet afd_navn til skærmtekst samme med kassenr (PHR - Danosoft) Søg 20140603
// 2014.06.10	-	Tilføjet bordplan (PHR - Danosoft) - søg bordplan.
// 2014.06.12	-	Lidt designændringer på bordplan (PHR - Danosoft) - søg bordplan.
// 2014.06.12	-	Bordknapper disables hvis der er indsat varenummer i inputfelt fra pos knap eller vareopslag (PHR - Danosoft) - søg disabled og bord.
// 2014.06.13 - Bogføring blev ikke afbrudt ved diff i posteringssum. 20140613
// 2014.06.13 - Ordre kunne ikke afsluttes med ørediff aktiv. 20140613
// 2014.06.13 - Div småting relateret til pos_ordrer - bl. a. momsdiff ved salg til kr. 27,12 og betaling med Dankort +100. 20140613+$retur
// 2014.06.16 - Mange ændringer i kasseoptælling og afslutings funktion. Bla. bogføring af kasse -> bank
// 2014.06.24 - Mulighed for at redigere i i pos linjer. (PHR - Danosoft) - søg $ret og _old.
// 2014.07.02 - Ovenstående gav fejl v scanning efter scanning uden enter  - pris fra vare 1 blev hængende
//		samt fejl v. menuklig efter scanning - vare forsvandt (PHR - Danosoft) - søg 20140702
// 2014.07.04	-	For visse varer ligger prisen i stregkoden. Det kan vi nu også. - søg 20140704
// 2014.07.08 - Ændret $bon til $tmp da den ellers ikke selv hopper videre til ny kunde.  - søg 20140708
// 2014.07.09	- Rettet kasseoptællingsrutine så differencer og 'udtages' bogføres når optælassist godkendes. 20140709
// 2014.08.11	- Rettet kald til kortterminal så den også fungerer på ekstern box. søg 'saldibox'
// 2014.08.14 - Diverse rettelser af ændring fra 20140624. Ved klik på pris eller rabat blev antal sat til 1 og ikke taget fra $antal_old mm. # 20140814
// 2014.08.14 - usdecimal sat foran 'rabat' ved 'opret_ordrelinje' da rabat ellers blev afrundet til heltal.
// 2014.08.14 - Funktion find_kasse - tjekker nu for om den kasse som hentes fra cookie eksisterer
// 2014.08.21 - Tilføjet knap for køkkenprint & kald til samme, Søg koekken.
// 2014.08.28 - Indsat strlen( da det undertiden gav fejl ved bordvalg.
// 2014.08.28 - Ved * efter rabat sættes rabatten på alle varer hvor rabat=0 PHR -- 20140828-2
// 2014.08.29 - Tilføjet "udskriv" knap ved siden af "køkken". Fungerer kun for restauranter. PHR 
// 2014.09.25 - Rettet $bord=NULL til $koekkenprinter=NULL. PHR 20140925
// 2014.10.25 - Mængrerabatter blev ikke medtaget på foreløbig udskrift -20141025
// 2014.11.12 - Mange designændringer. Søg efter find_stil
// 2014.11.13 - Leveret antal blev nulstillet ved "Ret" så køkkenprint foreslog det fulde antal.Søg $leveret
// 2014.12.09 - Bord blev ikke markeret som optaget ved deling til eksisterende tom ordre.
// 2015.01.01 - Betalinger lægges nu i tabellen pos_betalinger.php så samme bon kan betales af et ubegrænset antal kort. Søg "pos_betalinger"
// 2015.01.02 - Funktionen "Find_bon" finder nu bon med højeste bon nr i stedet for bon med højeste ID
// 2015.01.11 - Tilføjet sætpriser - søg $saet.
// 2015.01.12 - Tilføjet "gem som tilbud som ændrer art til 'DO' og finder højeste ordrenr. Søg "gem".
// 2015.01.21 - function "afslut" Skrivning til pos_betaliger flyttet til efter bogføring så der ikke skrives hvis bogføring ikke sker. 20150121a 
// 2015.01.21 - function "find_kassesalg" Det skal kun søges i transaktioner hvis det anvendes "straksbogfor" søges kun  20150121b 
// 2015.01.21 - function "afslut" ref opdaters med brugernavn ved afskutning. Søg $brugernavn i funktion afslut 
// 2015.01.31 - Mange rettelset til sætpriser. 
// 2015.02.14 -	Afrunder hver sæt lijne for at undgå øreafvigelser
// 2015.03.02 -	Afd blev ikke gemt ved "gem som tilbud" 20150302
// 2015.03.05 - Matcher brugernavn med bordnavn ved ny ordre, 20150305
// 2015.03.06 - Tilføjet  "or betalingsbet='Forud'" - 20150306
// 2015.03.10 - Fjernet søgning efter betalingsbet da alt skal bogføres gennem pos 20150310
// 2015.04.24	-	Sætter cookie for bordnr - søg cookie & bordnr
// 2015.05.05	-	rettet	"if ($nettosum" til "if (($nettosum || $nettosum == 0)" da 0 bon ellers ikke kunne afsluttes. Søg 20150505
// 2015.05.05	- Flyttet "$bordnr=$_COOKIE['saldi_bordnr'];" så den kun sættes hvis der ikke er brugerskift. Søg 20150505-2
// 2015.05.19	- Indbetalinger bogføres med det samme og gav derfor differ ved kasseoptælling Dette tages der nu højde for. 20150519
// 2015.05.20 - Diverse småfejl vedr indbetalinger.
// 2015.05.20 - Fjernet "and kontonr = '$kassekonti[$k]'" da den medtog tidligere kortsalg, hvis der ikke kavde været kassesalg. 20150520
// 2015.05.20 - Ændret "order by logdate desc, logtime desc" til "order by id desc" da det giver lige så god mening. 20150520
// 2015.05.22 - Ændret '&& $afslut=="Afslut"' til '&& ($afslut=="Afslut" || $betaling)' så der er mulighed for at "trække over" v. nulbon. 20150522
// 2015.05.27	- Der skal aldrig føres 2 x i pos_betalinger. Det skete ved indbetalinger hvor saldo blev ført som betaling og gav diff dagen efter. 
// 2015.06.01 - Fejl hvis ingen tidligere transaktioner. 20150601 
// 2015.06.13 - Køkkenknap bliver nu rød hvid det mangler bestillinger til køkken og grøn når alt er bestilt. Søg $kstil & 20150613
// 2015.08.12 - Småbeløb blev ikke afrundet ved 0 bon. 
// 2015.11.03	-	Indsat return confirm v.optælling for at sikre mod dobbelklik #20151103
// 2016.01.16	-	Udeladt 'and kasse_nr'.... ved kasseoptælling#20160116
// 2016.01.21 -	'på beløb' tog hele beløbet selvom der var delbetalt. # 20150121
// 2016.01.31	- Understøttelse af knapperne, kontoudtog,stamkunder & udskriv sidste. Udskriv udskriver nu sidste hvis intet id. 
// 2016.02.08	- 'stamkunder' reagerer nu også på "$_GET"
// 2016.02.11	-	Diverse tilretninger vedr. indbetaling (til støvlen);
// 2016.02.11 - Skuffe åbnes hvis det ikke er kontokøb og print er fravalgt.
// 2016.02.15 - Fjernet sidste katakter fra $sum hvis denne ikke er numerisk - årsag skal undersøges nærmere. Søg 20160215 
// 2016.02.15 -	Indbetalinger kom ikke med i beholdninger ved fast morgenbeholdning. Søg 20160215-2
// 2016.02.20	- Variantvarer blev ikke fundet. 20160220
// 2016.02.20	- Ved indbetaling og der blev skrevet et mindre beløb i i modtaget end betaling og det blev klikket betalingsform gik det galt "20160220-2 
// 2016.02.20	- Indbetalinger kom ikke med ved årets 1. kasseoptælling. 20160220-3
// 2016.02.23 - ændret "%" til " - kassenr: $kasse" da alle kasser valgte fra datoen for sidste optælling for alle kasser. Søg 20160223
// 2016.04.18 - Fejl ved indbetaling hvis cursor i modtaget og fokus i betalt. Søg 20160418  
// 2016.04.18 - "Tilbage" fungerer ikke under indbetaling. Søg 20160418-2
// 2016.06.07	-	Indført valuta - mange ændringer. 
// 2016.06.11 - Indsat '&& sum > 0' da man ellers ikke kunne tage varer retur. 20160611 
// 2016.06.12 -	Udtages fra kasse blev ført for hver betalingsform hvilket gav store kassediffer 20160612 
// 2016.08.12	-	Ved m_rabat på momsfri varer blev der ført moms på rabatten, hvis rabatvaren ikke var momsfri. 20160812
// 2016.08.17 - Delbetaling fungerede ikke efter indførelse af valuta. derfor dette hack. 20160817
// 2016.08.24	-	Fejlhåndtering for manglende valutakonti 20160824
// 2016.09.02 -	Tilføjet $indbetaling da den ellers ikke ville acceptere negativ indbetaling 
// 2016.10.01 - En del ændringer som muliggør kontantbetaling mm selvom der er en kundekonto på ordren hvis kundens betalingsbet er kontant.
// 2016.10.10 - Valuta i pos_betalinger sættes til DKK hvis NULL eller ''. 20161010
// 2016.10.12 - Tilføjet $rest af hensyn til sæt.
// 2016.10.13 - tilføjet $pris_ny ellers fungerer rabat ikke med 0 pris 20161013 
// 2016.10.14 - PHR ($betalt && is_numeric($betalt)) rettet til ($afslut=='on' && is_numeric($betalt)) da det ellers ikke er muligt at afslutte en bon hvor der er delbetalt og sum derefter er reduceret 20161014
// 2016.10.14 - PHR fjernet 3 linjer da den forhindrer delbetaling efter tilføjelse af "$rest=if_isset($_POST['rest']); #20161014-2
// 2016.10.14 - PHR Tilføjet elseif (!$id && $varenr_ny=='a') Så det kan hæves på kort uden køb og fejl 'varenumer ikke fundet' undgås #20161014-3
// 2016.10.14 - PHR Tilføjet || (!$id && $_POST['afslut']) Så der oprettes ordre inden der hæves på kort. #20161014-4
// 2016.10.17 - PHR $afslut=='on' rettet til ($betalt || ($afslut=='on') da man ellers ikke kan afskutte kontokøb
// 2016.11.10 - PHR ved kassopgørelse skrives en linje i transakioner med kontonr = '0' for entydig identifikation af tidspkt.
// 2016.11.16 - PHR tilføjet kladde_id != '0' or" så rettelser bogført efter sidste afstemning kommer med. 20161116 
// 2016.12.05 - PHR tilføjet modtaget = indbetaling hvis modtaget ikke sat da den ellers ikke kom videre efter 'enter' på indbetaling. #20161205
// 2016.12.14	- PHR Ved delbetaling med forskellige kort blev alle kortbetalinger registreret på samme kort. 20161214
// 2017.01.02-	PHR	Ved årets 1. kasseopgørelse blev morgenbeholdning øget med dagens indbetalingeer. 20170102
// 2017.01.07-	PHR	Delbatalinger på kort blev bogført uden betaling ved integreret kortterminal. 20170107
// 2017.01.09 - PHR Fejl på delbet vi ikke integreret kort. 20171019
// 2017 02.17 - PHR Tilføjet individuelt lager pr ordrelinje. Søg $lager
// 2017.02.23	- PHR logtime blev sat 2 x i samme funktion, hvilket kunne give forkert morgenbeholdning. #20170223 
// 2017.03.14 - PHR Tilføjet mulighed for at sætte 'udtages fra kasse' til 0 som default.
// 2017.03.17 - PHR Sum for "andre kort" blev ikke vist på kasseoptælling. #20170317
// 2017.03.18	-	PHR Samlet pris Nulstilles hvis der indsættes ny vare. Søg 20170318 
// 2017.03.27	-	PHR Initierer box12 i grupper/POSBUT fjernes 20170401 - Søg 20170104
// 2017.03.27 - PHR Kundedisplay viser nu linjesum (pris*antal i stedet for stykpris). Søg kundedisplay
// 2017.04.12	-	PHR Indsat parameter 2 på alle forekomster af dkdecimal & usdecimal
// 2017.04.17	-	PHR	Ved valg af Ekspedient sættes ID til 0 hvis status er >=3 således at der startes en ny ordre. 20170417
// 2017.06.22	-	PHR	Fejl på 'samlet pris' v kreditering at ordre med 'samlet pris'.  Søg 20170622-1  
// 2017.06.22	-	PHR	Følgevarer ikke på skærm og fejl på bon ved status >= 3.  Søg 20170622-2  
// 2017.07.19	-	PHR	Ved ordrer med samlet pris hvor der blev solgt og taget retur på samme bon blev rabatterne extreme men resultat korrekt. Søg $over0 & $under0 
// 2017.07.21	-	PHR Afrunding ændret fra 2 til 3 decimaler. 20170721.
// 2017.08.16	-	PHR Tilføjet strtolower i function find_kassesalg i så alle kort med samme navn køres på korrekt konto - Søg 20170816
// 2017.09.14 - PHR Tilføjet && !strpos($betaling,'på beløb') da 'på beløb' ikke fungerede #20170914
// 2017.10.10 - PHR	Tilføjet individuel font size. Søg $pfs (PosFontSize) 
// 2017.11.23 - PHR Tilføjet afd='$afd',felt_5='$kasse' (før blev det først skrevet i db ved afslutning ) søg 20171123
// 2018.01.26	-	PHR Tilføjet $vare_id[$x]=$r['vare_id']; & $varepris[$x]=$r['pris']; til brug for pos_print_194 (udsalgstekst) 20180126
// 2018.03.13	-	PHR Kontoopslag ændret til debitorposlag og kreditoropslag tilføjet.
// 2018.03.14	-	PHR Kundedisplay hentes nu fra grupper art = 'POS' kodenr = '3'
// 2018.05.02	- PHR	Hack for at scanner skipper det 1. 0 hvis 13 EAN stregkode starter med 00. Søg efter '0$varenr'
// 2018.06.28 - PHR Ekstra kontrol for om kortbetaling er gennemført ved integreret termimal. Søg pos_bet_id.
// 2018.07.04 - PHR Forfra afviser hvis der er betalt på ordren. #20180704 
// 2018.07.25 - PHR Trækker nu nyeste ordre med status > 3 ved bordvalg for at undgå at den trækker gammel uafsluttet.  #20180725 
// 2018.08.16 - PHR Styrket log af kortbetalinger. 20180816
// 2018.08.22 -	PHR Kontrol for at der ikke er ændret i kasseoptælling hvis der godkendes uden der er klikket beregn. # 20180822
// 2018.09.29 -	PHR Vejledning til kasseoptælling. vejl_kasseopt.html
// 2018.10.24 -	PHR Sikring mod dobbelt ordreoprettelse ved dobbeltklik 20181024
// 2018-12-10 - CA  Gavekortfunktioner indlæses ved opstart 20181210
// 2019-01-06 - PHR Tilføjet mulighed for totalrabat - Søg 'totalrabat'  
// 2019-01-07 - PHR Kortbeløb kan nu rettes ved kasseoptælling - Søg 'change_cardvalue'  
// 2019-01-11 - PHR Decimalfejl i $udtages.   
// 2019-01-23 - LN	(pos_txt_print) Udtræk af momssatser fra de enkelte varelinjer til bruge for detaljeret bon. 20190123
// 2019-02-15 - CA Moved the function varescan (itemscan in English) to the independent file debitor/func/pos_ordre_itemscan.php 20190215
// 2019-03-12 - LN Call newly made functions, to make and print x-report, z-report, and added argument to betaling
// 2019-03-14 - PHR Varius minor changes related to 'kasseoptælling'.
// 2019-03-14	- PHR	Varius changes in function 'kassebeholdning' according to 'change_cardvalue'
// 2019-03-18 LN Inlude new file to set txt on pos_ordre frontpage
// 2019-03-19 LN Add new check such that pos_txt_print is not called wrong if it is a report
// 2019-03-19 LN Call new function to get unique box id
// 2019-03-20 LN Set Z-report button on box count after pressing accept when country is Norway
// 2019-04-24 PHR various changes related to tilfravalg as added item was without price at 'bon' and addes items was listed trible after finished. // 2019-05-03 LN Moved pos_txt_print function to separate file.
// 2019-08-13 PHR Added 'refresh to pos_ordre.php' at end of function 'posbogfor' to avoid repeated 'cash takeout' 20190813
// 2019-09-03 PHR Added $varemomssats in function pos_txt_print. Pos prints had errors in item rabate on different VAT items. 20190903
// 2019-10-02 PHR Function posbogfor Added accounting of VAT if diff account is taxed. look for $diffVat.
// 2019-10-30 PHR Function posbogfor Corrected accounting of VAT if diff account is taxed.
// 2019-11-30 PHR Function posbogfor Corrected accounting of diff in different currency.
// 20200112 PHR	Added $initId to avoid double accounting if refreshing after return from printserver not present 
// 20200603	PHR	Added '$ny_beskrivelse' to opret_ordrelinje. 
// 20200619 PHR Added "&& !$vare_id_ny && !$folger" to 20200112
// 20200804 PHR	Minor changes to $pfs (PosFontSize)
// 20200929 PHR  Added "or stregkode = '$varenr_ny'" - 20200929
// 20201029	PHR If using 'on amount' when new item is about to be inserted and no price is given, finish is canceled and alert shown. 
// 20201109 PHR In 'skift_bruger' password is required even if same user.
// 20201114 PHR Enhanged 'tilfravalg' add/remove to food items, (fx. extra bacon or no tomatoes in burger) $tilfravalgNy
// 20201117 PHR replaced 'localhost' by $printserver to make customer display usable with Raspberry 
// 20201206 PHR Corrected params in call to 'kundedisplay'
// 20210112 PHR Lots of changes related to 'settleCommission'
// 20210125 PHR Lots of changes related to 'voucher'
// 20210225 PHR Function find_kassesalg. line starting with '($o_liste)?' was by mistake commented out.
// 20210310 PHR Fixed some errors created by cleanup in undefined vars. related to '$bordnr'. 
// 20210311 PHR Fixed more errors in acount sale and printing last receipt. 
// 20210320 PHR Made it possible to change qty in items with add ons 202103220
// 20210403 PHR If barcode is scanned in dicsount box, it is now treaded as new item. 20210403
// 20210410 PHR Added createPayList
// 20210429 PHR Changer box5 to felt_5 #20210429
// 20210623 PR Rewritten routine as order id sometimes changed after inserting first item.
// 20210810 LOE Changed this variable name to beskrivelse_ny for a 't' value substring
// 20210811 LOE $fokus!="beskrivelse_ny added to this block of code
// 20210812 LOE Added $beskrivelse_old variable;
// 20210813 LOE Added this block of code to take care of text 
// 20210815 LOE Set up $default_discounttxt for initial rabat on the frontend
// 20210817 LOE Translated some texts
// 20210820 LOE Translated alert texts
// 20210822 PHR	rewritten some parts of discounttxt and added $newDiscounttxt.	
// 20210823 PHR	Initialization of $kasse. (line 219)
// 20210826 PHR	changed elseif to if ad it sometimes got an order from another 'kasse' after account lookup. 
// 20210903 PHR changed from max (id) to sort by tidspkt desc limit 1 as it is not always the highest ID 
// 20210906 PHR Added textNew - check same date in pos_ordre_itemscan.
// 20220222 PHR Added Rounded checksum in X-report
// 20220614 PHR Tilfravalg was reset when changing price or rebate. See pos_ordre_itemscan.php too. 
// 20220726	PHR Added barcodeNew to be inserted into orderline colunm 'barcode'
// 20230617 PHR php8
// 20231223 PHR find_kassesalg omdøbt og flyttet til findBoxSale
// 20240415 PHR Moved function delbetal to pos_ordre_includes/paymentFunc/partPayment.php
// 20250526 PHR added "if ($returside == 'kassespor.php') .... " to function primary_menu as 'retur til kassespor' didn't work
// 20250619 PHR proforma button can nov be called anything - not nessecary 'proforma'
// 20250701 PHR Updated call to 'moveToOwnAccount' who work without 'moveToCustomerAccount' set
// 20250701 PHR Check if order exits. if not set id to 0
@session_start();
$s_id = session_id();
ob_start();
$modulnr = 5;
$title = "POS_ordre";
$css = "../css/pos.css";
$addRemove = $afd = $afslut = $antal_ny = $afd_lager = $afd_navn = NULL;
$barcodeNew = $beskrivelse_ny = $betaling = $betaling2 = $bordnavn = $bordnr = NULL;
$country = NULL;
$delayLoad = $del_bord = $delbetaling = NULL;
$kasse = $konto_id = NULL;
$fokus = "varenr_ny";
$id = $initId = $indbetaling = NULL;
$modtaget = $modtaget2 = NULL;
$next_varenr = $ny_bruger = NULL;
$obstxt = NULL;
#$printserver="localhost";
$pre_bordnr = $pris_ny = NULL;
$rabat_ny = $ref = NULL;
$saldi_bet = $skift_bruger = $status = $svar = $svnr = NULL;
$valuta = 'DKK';
$valutakurs = '100';
$vare_id = $vis_kassenr = NULL;

$bord = $koekkenprinter = $lagernr = $lagernavn = array();

(isset($_COOKIE['saldi_pfs'])) ? $pfs = $_COOKIE['saldi_pfs'] : $pfs = 10;
$ifs = $pfs * 1.3;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (get_settings_value("mobilepos", "POS", "off", NULL, $kasse = $_COOKIE["saldi_pos"]) == "on") {
	$width = get_settings_value("mobilwidth", "POS", "510", null, $_COOKIE["saldi_pos"]);
	$zoom = usdecimal(get_settings_value("mobilzoom", "POS", "1.0", null, $_COOKIE["saldi_pos"]));
	print "<meta name='viewport' content='width=$width, initial-scale=$zoom, maximum-scale=$zoom, user-scalable=0'>";
} else {
	print '<meta name="viewport" content="width=device-width, initial-scale=1">';
}


include("../includes/ordrefunc.php");
include("../includes/posmenufunc.php");
include("../debitor/func/pos_ordre_itemscan.php"); # 20190215

include("pos_ordre_includes/boxCountMethods/boxCount.php"); #20190219
include("pos_ordre_includes/boxCountMethods/printBoxCount.php"); #20190219
include("pos_ordre_includes/boxCountMethods/boxCountText.php"); #20190219

include("pos_ordre_includes/frontpage/itemTxt.php"); #20190219 

#include("pos_ordre_includes/voucherFunc/setup.php"); # 20181220
include("pos_ordre_includes/voucherFunc/checkVoucher.php"); # 13-11-2024
#include("pos_ordre_includes/divFuncs/takeAway/setup.php");
include("pos_ordre_includes/report/reportSetup.php");

include("pos_ordre_includes/helperMethods/helperFunc.php"); #20190219 
include("pos_ordre_includes/helperMethods/helperFuncII.php"); #20190219 

include("pos_ordre_includes/posTxtPrint/posTxtPrintFunc.php"); #20190503

include("pos_ordre_includes/showPosLines/showPosLinesFunc.php"); #20190510 

include("pos_ordre_includes/exitFunc/exit.php"); #20190510

if(isset($_GET["payment_id"])){
	$_SESSION["payment_id"] = $_GET['payment_id'];
}

if (get_settings_value("mobilepos", "POS", "off", NULL, $kasse = $_COOKIE["saldi_pos"]) == "on") {
	$width = get_settings_value("mobilwidth", "POS", "510", null, $_COOKIE["saldi_pos"]);
	$zoom = usdecimal(get_settings_value("mobilzoom", "POS", "1.0", null, $_COOKIE["saldi_pos"]));
	print "<meta name='viewport' content='width=$width, initial-scale=$zoom, maximum-scale=$zoom, user-scalable=0'>";
}

if ($menu == 'T') {
	if (!$bgcolor)
		$bgcolor = "#000000";
	print "<body bgcolor=\"$bgcolor\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\">\n";
	print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
	<html>\n
	<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset;\">\n
	<meta http-equiv=\"content-language\" content=\"da\">\n
	<meta name=\"google\" content=\"notranslate\">\n";
	if ($meta_returside)
		print "$meta_returside"; #20140502
	if ($css)
		print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">\n";
	else
		print "<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/saldimenu.css\"/>\n";
	if (substr($title, 0, 3) == 'POS') { # 21071009
		($title == 'POS_ordre' && isset($_COOKIE['saldi_pfs'])) ? $pfs = $_COOKIE['saldi_pfs'] : $pfs = 10;
		print "<style> body {font-family: Arial, Helvetica, sans-serif;font-size: " . $pfs . "pt;} </style>";
		print "<style> table {font-family: Arial, Helvetica, sans-serif;font-size: " . $pfs . "pt;} </style>";
	}
	print "<script type=\"text/javascript\" src=\"../javascript/jquery-1.8.0.min.js\"></script>\n"; #20140502
	print "<script type=\"text/javascript\" src=\"../javascript/jquery.autosize.js\"></script>\n"; #20140502
	print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/overlib.js\"></script>\n";
	print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>\n"; #20140502
#	print "<script src=\"../javascript/sweetalert.min.js\"></script>";
#	print "<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/sweetalert.css\">";

	#print "<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/main.css\"/>\n";
	print "
	<script type=\"text/javascript\">
	
	var linje_id=0;
	var vare_id=0;
	var antal=0;
	function serienummer(linje_id,antal){
		window.open(\"serienummer.php?linje_id=\"+ linje_id,\"\",\"left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no\")
	}
	function batch(linje_id,antal){
		window.open(\"batch.php?linje_id=\"+ linje_id,\"\",\"left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no\")
	}
	function stykliste(vare_id){
		window.open(\"../lager/fuld_stykliste.php?id=\"+ vare_id,\"\",\"left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no\")
	}
	
	</script>";
	#20140502 -->
	?>

	<script type="text/javascript">
		// jQuery funktion til autosize på textarea 
		$(document).ready(function () {
			$('.autosize').autosize();
		});
		// jQuery funktion til ordrelinjer i ordre.php. Ved tryk på enter submitter formen og ved shift+enter laver den ny linje i textarea
		$(function () {
			$('textarea.comment').keyup(function (e) {
				if (e.which == 13 && !e.shiftKey) {
					$("#submit").click();
				}
			});
		});
		// $(document).on('focus', 'textarea', function(){
		//            autosize($('textarea'));  //20201218
		//});	
	</script>
	<?php
	# <-- 20140502
	print "</head>\n";
}

$receipt_id = if_isset($_GET['receipt_id'], 0);

$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrelinjer' and ";
$qtxt.= "column_name = 'barcode'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE ordrelinjer ADD COLUMN barcode varchar(20)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}


$logfile = "../temp/$db/posOrder.log";
if (file_exists($logfile))
	chmod($logfile, 0666);
#$tracelog = fopen($logfile,"a");
$tracelog = NULL;
if ($tracelog)
	fwrite($tracelog, date("Ymd His") . "\n");
$calculatedCashTxt = setCashCountText();

include("pos_ordre_includes/divFuncs/drawer/drawerStatusFunc.php");
#takeAwaySetup();
#voucherSetup();
preDrawerCheck();
global $initial_price; #from debitor/pos_ordre_includes/showPosLines/productLines.php

ini_set('display_errors', '0');

// Projekt kan knytttes til menu, f.eks dag og aften så man kan trække en rapport på hvor man har sin indtjening. 
// Projektet knyttes til varen så det både kan være dag og aften på samme bon. 
if (isset($_GET['printXreport']) && $_GET['printXreport']) {
	udskriv_kasseopg($_GET['id'], $_GET['kasse'], "../temp/$db/Xreport" . $_GET['kasse'] . ".txt");
}
if (isset($_GET['udskriv_kasseopg']) && $_GET['udskriv_kasseopg']) {
	$id = if_isset($_GET['id'], 0);
	if ($tracelog) {
		fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls udskriv_kasseopg($id,$_GET[kasse]),$_GET[udskriv_kasseopg]) (udskriv_kasseopg)\n");
	}
	udskriv_kasseopg($id, $_GET['kasse'], $_GET['udskriv_kasseopg']);
}
if (isset($_GET['xRapport']) && $_GET['xRapport']) {
	$id = $_GET['id'];
	if ($tracelog) {
		fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls udskriv_kasseopg($id,$_GET[kasse],$_GET[udskriv_xrapport]) (udskriv_xrapport)\n");
	}
	udskriv_kasseopg($id, $_GET['kasse'], $_GET['udskriv_xrapport']);
}
$projekt = NULL;
$tid = date("H:i");
$qtxt = "select box9 from grupper where art='POSBUT' and (box7 < box8) and (box7<'$tid' and box8>'$tid')";
if ($afd)
	$qtxt .= " and (box12='$afd' or box12='') order by box12 desc limit 1";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$projekt = $r['box9'];
if (!$projekt) {
	$qtxt = "select box9 from grupper where art='POSBUT' and (box7 > box8) and ((box7>'$tid' and box8>'$tid') or ";
	$qtxt .= "(box7<'$tid' and box8<'$tid'))";
	if ($afd)
		$qtxt .= " and (box12='$afd' or box12='') order by box12 desc limit 1";
	($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) ? $projekt = $r['box9'] : $projekt = '';
}
if (isset($_GET['skift_bruger'])) {
	if ($_GET['skift_bruger'] == 1)
		skift_bruger(null, null, 1);
	elseif ($_GET['skift_bruger'] == 2 && $_GET['brugernavn'])
		skift_bruger($_GET['brugernavn'], null, 2);
	exit;
}
if (isset($_POST['bon']) && $_POST['bon']) {
	$id = find_bon($_POST['bon']);
}
if (isset($_GET['find_bon']) && $_GET['find_bon'] == 1) {
	find_bon(null);
	exit;
}
if ($bordvalg = if_isset($_POST['bordvalg'])) {
	$bordnr = NULL;
	if (isset($_POST['varenr_ny']) && $_POST['varenr_ny'] && is_numeric($_POST['varenr_ny']) && $_POST['varenr_ny'] >= 0) {
		$bordnr_ny = $_POST['varenr_ny'];
		$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
		if ($r['box7'])
			$bord = explode(chr(9), $r['box7']); #20140508
		if (count($bord) >= $bordnr_ny) {
			$bordnr = $bordnr_ny - 1;
		}
	}
	$_POST['varenr_ny'] = NULL;
	$fokus = NULL;
	# Check if the old system is in use.
	$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	if ($r[0]) {
		$bord = explode(chr(9), str_replace("\n", "  ", $r[0]));
	} else {
		$bord = array();
	}
	if (count($bord) == 0) {
		if ($bordnr || $bordnr == '0')
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?bordnr=$bordnr\">\n";
		else
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../bordplaner/table_plan.php?id=$id\">\n";
	} else {
		if ($bordnr || $bordnr == '0')
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?bordnr=$bordnr\">\n";
		else
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../bordplaner/bordplan.php?id=$id\">\n";
	}
	exit;
}
$gem = if_isset($_POST['gem']);
$vouherNumber = if_isset($_POST['vouherNumber']);
if (isset($_GET['id']) && $_GET['id'])
	$id = $_GET['id'];
if (isset($_POST['kasse']) && $_POST['kasse']) {
	$kasse = $_POST['kasse']; #20150402
	setcookie('saldi_pos', $kasse, time() + 60 * 60 * 24 * 30, '/');
	if (isset($pfs) && $pfs)
		setcookie('saldi_pfs', $pfs, time() - 60, '/');
} elseif (!$kasse && isset($_COOKIE['saldi_pos']))
	$kasse = $_COOKIE['saldi_pos'];
$qtxt = "select box7 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if ($r['box7'])
	$bord = explode(chr(9), $r['box7']); #20140508
#if ($bruger_id == '-1') echo $_GET['bordnr'];
if (isset($_GET['bordnr']))
	$bordnr = $_GET['bordnr'];
if (isset($_POST['id']) && $_POST['id'])
	$id = $_POST['id'];
if (!$id && !$bordnr && $bordnr != '0') { #20150305
	if (isset($_POST['ny_bruger']) && $_POST['ny_bruger'])
		skift_bruger($_POST['ny_bruger'], $_POST['kode'], 1); #20150402
	for ($i = 0; $i < count($bord); $i++) {
		if (strstr(strtolower($bord[$i]), strtolower($brugernavn))) {
			$bordnr = $i;
			$konto_id = if_isset($_GET['konto_id']);
			setcookie("saldi_bordnr", $bordnr, time() + 60 * 60 * 24 * 30); #20150505-2
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?bordnr=$bordnr&konto_id=$konto_id\">\n"; #20150401
			exit;
			break 1;
		}
	}
}
if (!$bordnr && $bordnr != '0')
	$bordnr = $_COOKIE['saldi_bordnr']; #20150505-2
if (isset($_GET['flyt_til']) && $id) { #20140508
	if ($bruger_id == '-1')
		echo $_GET['flyt_til'];
	$bordnr = $_GET['flyt_til'];
	$r = db_fetch_array(db_select("select momssats,felt_5 from ordrer where id='$id'", __FILE__ . " linje " . __LINE__));
	$momssats = $r['momssats'];
	$kasse = $r['felt_5']; #20210429
	$delflyt = if_isset($_GET['delflyt']);
	if ($delflyt) {
		if ($r = db_fetch_array(db_select("select id from ordrer where art='PO' and status < '3' and nr = '$bordnr'", __FILE__ . " linje " . __LINE__))) {
			$ny_id = $r['id'];
		} else {
			$ny_id = opret_posordre(NULL, $kasse);
		}
		$a = array();
		$a = explode("|", $delflyt);
		for ($x = 0; $x < count($a); $x++) {

			list($df_linje_id[$x], $df_vare_id[$x], $df[$x]) = explode(":", $a[$x]);
		}
		$a = NULL;
		$ny_vare_id = array();
		$x = 0;
		$qtxt = "select id,vare_id from ordrelinjer where ordre_id = '$ny_id'";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['vare_id'], $ny_vare_id)) {
				$ny_linje_id[$x] = $r['id'];
				$ny_vare_id[$x] = $r['vare_id'];
				$x++;
			}
		}
		/*
				$x=0;
				$qtxt="select * from ordrelinjer where ordre_id = '$id'";
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while($r=db_fetch_array($q)) {
					$linje_id[$x]=$r['id'];
					$vare_id[$x]=$r['vare_id'];
					$varenr[$x]=$r['varenr'];
					$antal[$x]=$r['antal'];
					$pris[$x]=$r['pris'];
					$rabat[$x]=$r['rabat'];
					$x++;
				}
			*/
		transaktion('begin');
		for ($x = 0; $x < count($df_vare_id); $x++) {
			if ($df[$x] && in_array($df_vare_id[$x], $ny_vare_id)) {
				for ($n = 0; $n < count($ny_vare_id); $n++) {
					if ($ny_vare_id[$n] == $df_vare_id[$x]) {
						$qtxt = "update ordrelinjer set antal=antal+$df[$x] where id='$ny_linje_id[$n]'";
						db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					}
				}
			} elseif ($df[$x]) {
				$qtxt = "select * from ordrelinjer where id=$df_linje_id[$x]";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				opret_ordrelinje($ny_id, $r['vare_id'], $r['varenr'], $df[$x], '', $r['pris'], $r['rabat'], 100, 'PO', '', '', '0', '', '', '', '0', '0', '', '', $r['lager'], __LINE__);
			}
		}
		for ($x = 0; $x < count($df_vare_id); $x++) {
			if ($df[$x]) {
				$qtxt = "select * from ordrelinjer where id=$df_linje_id[$x]";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				if ($r['antal'] == $df[$x])
					$qtxt = "delete from ordrelinjer where id='$df_linje_id[$x]'";
				else
					$qtxt = "update ordrelinjer set antal=antal-$df[$x] where id='$df_linje_id[$x]'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
			#			$qtxt="select * from ordrelinjer where id=df_linje_id[$x]";
#			if ($antal[$x]==$df[$x]) $qtxt="delete from ordrelinjer where id='$linje_id[$x]'";
		}
		transaktion('commit');
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$ny_id\">\n";
	} else {
		$qtxt = "select id from ordrer where art='PO' and status < '3' and nr = '$bordnr'";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			db_modify("update ordrelinjer set ordre_id='$r[id]' where ordre_id='$id'", __FILE__ . " linje " . __LINE__);
			db_modify("update ordrer set hvem='' where id='$id'", __FILE__ . " linje " . __LINE__);
			$id = $r['id'];
		} else {
			db_modify("update ordrer set nr='$bordnr',hvem='$brugernavn' where id='$id'", __FILE__ . " linje " . __LINE__);
		}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
	}
} elseif (!$id && (count($bord) < 1 || (count($bord) >= 1 && ($bordnr >= '0' || isset($_GET['bordnr']))))) { #20210623
	if (isset($_GET['bordnr']))
		$bordnr = $_GET['bordnr'];
	if ($bordnr || $bordnr == '0') {
		setcookie("saldi_bordnr", $bordnr, time() + 60 * 60 * 24 * 30);
	}
	$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='pos_events'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		include("../includes/betweenUpdates.php");
	}
	$qtxt = "select ev_type from pos_events order by ev_id desc limit 1";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (isset($r['ev_type']) && $r['ev_type'] == '3002') {
		$qtxt = "insert into pos_events (ev_type,ev_time,cash_register_id,employee_id,order_id,file,line) ";
		$qtxt.= "values ";
		$qtxt.= "('13001','" . date('U') . "','$kasse','$bruger_id','0','" . __FILE__ . "','" . __LINE__ . "')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	$qtxt = "select max(id) as id from ordrer where art='PO' and status < '3'";
	$qtxt.= " and (hvem = '$brugernavn' or ordredate < '" . date("Y-m-d") . "')";

	# Check if the old system is in use.
	$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	if ($r[0]) {
		if (count($bord) >= 1 && $bordnr || $bordnr == '0')
			$qtxt .= " and nr = '$bordnr' ";
	} else {
		if ($r = db_fetch_array(db_select("select id from table_plan", __FILE__ . " linje " . __LINE__))) {
			if (count($r) >= 0 && $bordnr || $bordnr == '0') {
				$qtxt .= " and nr = '$bordnr' ";
			}
		}
	}

	if ($kasse)
		$qtxt .= " and felt_5 = '$kasse' "; #20210826
	($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) ? $id = $r['id'] : $id = 0;
	if ($id && !count($bord)) {
		$qtxt = "select ordredate from ordrer where id='$id'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if (strtotime($r['ordredate']) < date('U') - 60 * 60 * 48) {
			$qtxt = "update ordrer set konto_id='0', kontonr='0',firmanavn='',addr1='',addr2='',postnr='',bynavn='',land='',betalingsdage='0',";
			$qtxt.= "betalingsbet='Kontant',cvrnr='',ean='',institution='',email='',kontakt='',art='PO',valuta='DKK',valutakurs='100',";
			$qtxt.= "kundeordnr='',ordredate='" . date("Y-m-d") . "',hvem='',ref='',nr=NULL where id = '$id'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			db_modify("delete from ordrelinjer where ordre_id='$id'", __FILE__ . " linje " . __LINE__);
		}
		#	} elseif ($id) {
#		$qtxt="update ordrer set nr='$bordnr' where id = '$id'";
#		db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
/*
if (isset($_POST['kasse']) && $_POST['kasse']) {
	$kasse=$_POST['kasse']; #20150402
	setcookie("saldi_pos",$kasse,time()+60*60*24*30);
	setcookie($_COOKIE['saldi_pfs'],$pfs,time()-60);
	setcookie($_COOKIE['saldi_pfs'],$pfs,time()-60,'/');
}
if (isset($_POST['id']) && $_POST['id']) $id=$_POST['id'];
if (!$id && !$bordnr && $bordnr != '0') { #20150305
	if (isset($_POST['ny_bruger']) && $_POST['ny_bruger']) skift_bruger($_POST['ny_bruger'],$_POST['kode'],1); #20150402
	for ($i=0;$i<count($bord);$i++) {
		if (strstr(strtolower($bord[$i]),strtolower($brugernavn))) {
			$bordnr=$i;
			$konto_id=if_isset($_GET['konto_id']);
			setcookie("saldi_bordnr",$bordnr,time()+60*60*24*30); #20150505-2
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?bordnr=$bordnr&konto_id=$konto_id\">\n"; #20150401
			exit;
			break 1;
		}
	}
} if (!$bordnr && $bordnr != '0') $bordnr=$_COOKIE['saldi_bordnr']; #20150505-2
*/
if (!$id && !$bordnr && $bordnr != '0' && count($bord)) { #20141210 + #20150305
	if (!$kasse)
		$kasse = if_isset($_POST['kasse']);
	if (!$kasse)
		$kasse = find_kasse(0);
	$qtxt = "select id,nr,felt_5 from ordrer where art='PO' and status < '3' and hvem= '$brugernavn' and ordredate >= '2014-12-10'";
	$qtxt.= " and (felt_5='$kasse' or felt_5='' or felt_5 is NULL)";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$id = $r['id'];
	$bordnr = $r['nr']; #20140822
	if ($id && !$r['felt_5'])
		db_modify("update ordrer set felt_5='$kasse' where id='$id'", __FILE__ . " linje " . __LINE__);
}


if (strlen($bordnr) == 0 && count($bord) && $id) { #20150323
// This means tables is enables but this order does not have a table assigned why it is assignet to the first free table.
	$qtxt = "select nr from ordrer where id = '$id'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if ($r['nr'] || $r['nr'] == '0')
		$bordnr = $r['nr'];
	else {
		$x = 0;
		$qtxt = "select id,nr,hvem from ordrer where art = 'PO' and status < 3 order by nr";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		$optaget = array();
		while ($r = db_fetch_array($q)) {
			if ($r['hvem'] && is_numeric($r['nr'])) {
				$optaget[$x] = $r['nr'];
				$x++;
			}
		}
		$bordnr = 0;
		while (in_array($bordnr, $optaget))
			$bordnr++;
		if ($id)
			db_modify("update ordrer set nr='$bordnr' where id='$id'", __FILE__ . " linje " . __LINE__);
		#	print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id&bordnr=$bordnr\">\n";
		#	exit;
	}
}

$l = 0;
$q = db_select("select * from grupper where art='LG'", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$lagernr[$l] = $r['kodenr'];
	$lagernavn[$l] = $r['beskrivelse'];
	$l++;
}
$lagerantal = $l;

$r = db_fetch_array(db_select("select box2 from grupper where art='OreDif'", __FILE__ . " linje " . __LINE__));
$difkto = if_isset($r['box2'], NULL);
$returside = (if_isset($_GET['returside']));
if (!$returside) {
	if ($popup)
		$returside = "../includes/luk.php";
	else
		$returside = "../index/menu.php";
}
$qtxt = "select box3 from grupper where art = 'POS' and kodenr = '3' and fiscal_year = '$regnaar'";
($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) ? $kundedisplay = $r['box3'] : $kundedisplay = NULL;

print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>\n";
$luk = (if_isset($_GET['luk']));
if ($luk) {
	if ($kundedisplay)
		kundedisplay('****   Lukket   ****', '', 1);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">\n";
}
$kasse = if_isset($_GET['kasse']);
$menu_id = if_isset($_GET['menu_id']);
if (isset($_POST['sidemenu']))
	$sidemenu = if_isset($_POST['sidemenu']);
else
	$sidemenu = if_isset($_GET['sidemenu']);
$bundmenu = if_isset($_GET['bundmenu']);
$kassebeholdning = if_isset($_GET['kassebeholdning']);
if ($kasse && $kassebeholdning && !isset($_POST['zRapport'])) {
	$calc = setCashCountText($country)['calculate'];
	if (isset($_POST['optael']) && ($_POST['optael'] == $calculatedCashTxt['accept'] || $_POST['optael'] == $calculatedCashTxt['calculate'])) {
		$cookievalue = $_POST['ore_50'] . chr(9) . $_POST['kr_1'] . chr(9) . $_POST['kr_2'] . chr(9) . $_POST['kr_5'] .
			chr(9) . $_POST['kr_10'] . chr(9) . $_POST['kr_20'] . chr(9) . $_POST['kr_50'] . chr(9) . $_POST['kr_100'] .
			chr(9) . $_POST['kr_200'] . chr(9) . $_POST['kr_500'] . chr(9) . $_POST['kr_1000'] .
			chr(9) . usdecimal($_POST['kr_andet'], 2) . chr(9) . if_isset($_POST['rappen_5'], 0) .
			chr(9) . if_isset($_POST['rappen_10'], 0) . chr(9) . if_isset($_POST['rappen_20'], 0);
		$optval = if_isset($_POST['optval'], array());
		$reportNumber = if_isset($_POST['reportNumber']);
		if (count($optval)) {
			for ($x = 0; $x < count($optval); $x++) {
				$optval[$x] = usdecimal($optval[$x]);
				$cookievalue .= chr(9) . $optval[$x];
			}
		}
		setcookie("saldi_kasseoptael", $cookievalue, time() + 600);
		$optalt = (int) $_POST['ore_50'] * 0.5 +
			(int) $_POST['kr_1'] +
			(int) $_POST['kr_2'] * 2 +
			(int) $_POST['kr_5'] * 5 +
			(int) $_POST['kr_10'] * 10 +
			(int) $_POST['kr_20'] * 20 +
			(int) $_POST['kr_50'] * 50 +
			(int) $_POST['kr_100'] * 100 +
			(int) $_POST['kr_200'] * 200 +
			(int) $_POST['kr_500'] * 500 +
			(int) $_POST['kr_1000'] * 1000 +
			(float) usdecimal($_POST['kr_andet'], 2);
		(int) $_POST['rappen_5'] * 0.05 +
			(int) $_POST['rappen_10'] * 0.1 +
			(int) $_POST['rappen_20'] * 0.2;
		($_POST['optael'] == $calculatedCashTxt['accept']) ? $godkendt = 1 : $godkendt = 0;
		if ($godkendt && round($optalt - $_POST['optalt'], 2) != 0) { #20180822 + 20220222 
			$godkendt = 0;
			$alert = findtekst(1862, $sprog_id); #20210820
			alert("$alert");
		}
		if ($tracelog)
			fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls kassebeholdning($kasse,$optalt,$godkendt,$cookievalue)\n");
		include_once("pos_ordre_includes/boxCountMethods/cashBalance.php");
		cashBalance($kasse, $optalt, $godkendt, $cookievalue);
	} elseif (!isset($_POST['optael'])) {
		if ($tracelog)
			fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls kassebeholdning($kasse,0,0,'')\n");
		include_once("pos_ordre_includes/boxCountMethods/cashBalance.php");
		cashBalance($kasse, 0, 0, '');
	}
}
if (!$kasse || $kasse == "?")
	$kasse = find_kasse($kasse);
elseif ($kasse == "opdat") {
	$kasse = $_POST['kasse'];
	setcookie("saldi_pos", $kasse, time() + 60 * 60 * 24 * 30);
}
if (!isset($_COOKIE['saldi_pfs']) || !$_COOKIE['saldi_pfs'] || !$id) {
	if (!$id)
		$old_pfs = $_COOKIE['saldi_pfs'];
	$qtxt = "select box2 from grupper where art = 'POS' and kodenr = '3' and fiscal_year = '$regnaar'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$tmparray = explode(chr(9), $r['box2']);
	if ($tmparray[$kasse - 1])
		$pfs = $tmparray[$kasse - 1];
	setcookie('saldi_pfs', $pfs, time() - 60);
	setcookie('saldi_pfs', $pfs, time() + 60 * 60 * 24 * 365, '/');
	if ($pfs && $pfs != $old_pfs)
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n"; # 20140424b
}
$ifs = $pfs * 1.3;

if ($kasse = trim($kasse)) {
	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='1' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	$kasseantal = (int) $r['box1'];
	$a = explode(chr(9), $r['box3']);
	$b = $kasse - 1;
	$afd = (int) $a[$b];
	if ($r = db_fetch_array(db_select("select * from grupper where art = 'AFD' and kodenr='$afd'", __FILE__ . " linje " . __LINE__))) {
		$afd_navn = $r['beskrivelse'];
		$afd_lager = $r['box1'];
	}
}
$initId = $id;
$godkendt = if_isset($_GET['godkendt']); # 20131205
if ($godkendt == 'OK') { # 20131205
	$id = if_isset($_GET['id']);

	$betaling = if_isset($_GET['betaling']);
	if (!$betaling && isset($_GET['cardscheme']))
		$betaling = if_isset($_GET['cardscheme']);

	if (!$betaling && isset($_GET['amount']))
		$betaling = if_isset($_GET['amount']);
	$betaling2 = if_isset($_GET['betaling2']);

	$modtaget = if_isset($_GET['modtaget']);
	if (!$modtaget && isset($_GET['amount']))
		$modtaget = if_isset($_GET['amount']);
	$modtaget2 = if_isset($_GET['modtaget2']);

	$indbetaling = if_isset($_GET['indbetaling']);

	$kortnavn = if_isset($_GET['kortnavn']);
	if (!$kortnavn && isset($_GET['cardscheme']))
		$kortnavn = if_isset($_GET['cardscheme']);

	$delbetaling = if_isset($_GET['delbetaling']);

	$gf = fopen("../temp/$db/godkendt.txt", "a"); # 20180816

	fwrite($gf, "\n" . __FILE__ . " " . __LINE__ . " " . date("H:i:s"));
	fwrite($gf, "betaling:$betaling,betaling2:$betaling2,modtaget:$modtaget,modtaget2:$modtaget2,");
	fwrite($gf, "indbetaling:$indbetaling,kortnavn:$kortnavn,delbetaling:$delbetaling\n");
	fwrite($gf, "\n" . __FILE__ . " " . __LINE__ . " " . date("H:i:s") . " " . $_SERVER['HTTP_REFERER'] . "\n");

	if ($delbetaling) {
		fwrite($gf, "delbetal($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling,$godkendt,$kortnavn,$receipt_id, __line__)\n");
		fclose($gf);
		include_once("pos_ordre_includes/paymentFunc/partPayment.php");
		delbetal($id, $betaling, $betaling2, $modtaget, $modtaget2, $indbetaling, $godkendt, $kortnavn, $receipt_id, __LINE__);
	} else {
		fwrite($gf, "afslut($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling,$godkendt,$kortnavn, __line__)\n");
		fclose($gf);
		afslut($id, $betaling, $betaling2, $modtaget, $modtaget2, $indbetaling, $godkendt, $kortnavn, __LINE__); #20140129 Tilføjet $kortnavn
	}

	#} elseif ($godkendt) {
#	setcookie("saldi_bet",$cookietxt,time()-3600);
#} elseif(!$godkendt && isset($_COOKIE['saldi_bet']) && $tmp=$_COOKIE['saldi_bet']){#
	#print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
} elseif ($godkendt == 'afvist' || $godkendt == 'afbrudt' || $godkendt == 'Afstemning er ikke foretaget' || $godkendt == 'Terminal ikke startet' || strpos($godkendt, 'afbrudt')) {
	$id = if_isset($_GET['id']);
	$qtxt = "delete from pos_betalinger where ordre_id = '$id' and betalingstype='!'";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$gf = fopen("../temp/$db/godkendt.txt", "a"); # 20180816
	fwrite($gf, "\n" . __FILE__ . " " . __LINE__ . " " . date("H:i:s") . " " . $_SERVER['HTTP_REFERER'] . "\n");
	fwrite($gf, $godkendt . "\n" . $qtxt . "\n");
	fclose($gf);
}

$bon = trim(strtoupper(if_isset($_POST['bon'])));
$tilbage = if_isset($_POST['tilbage']);
if ($tilbage && $kundedisplay)
	kundedisplay('', '', '1');
if (isset($_GET['id']) && $_GET['id'] && !isset($_POST['bordvalg']) && $_POST['bordvalg']) { #20140822
	$id = $_GET['id'];
	if ($r = db_fetch_array(db_select("select nr from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__)))
		$bordnr = $r['nr'];
}
(isset($_GET['vare_id'])) ? $vare_id = $_GET['vare_id'] : $vare_id = 0; #20210320    
(isset($_GET['lager'])) ? $lager_ny = $_GET['lager'] : $lager_ny = 0; #20210320
(isset($_GET['vare_id_ny'])) ? $vare_id_ny = $_GET['vare_id_ny'] : $vare_id_ny = 0; #20210320
$folger = (int) if_isset($_GET['folger']);
if (isset($_GET['antal_ny']) && $_GET['antal_ny'])
	$antal_ny = $_GET['antal_ny']; #20210320
$giftcardAntal = (int) if_isset($_GET['giftcardAntal']);
$giftcardPris = (float) if_isset($_GET['giftcardPris']);
$totalrabat = if_isset($_POST['totalrabat']);
if ($totalrabat && $id) {
	$discountTxt = $t = str_replace('%', '', $totalrabat);
	$totalrabat = NULL;
	for ($x = strlen($t); $x >= strlen($t) - 2; $x--) {
		if (is_numeric(substr($t, $x, 1)))
			$totalrabat = substr($t, $x, 1) . $totalrabat;
	}
	if ($totalrabat) {
		$qtxt = "select id,beskrivelse,rabat from ordrelinjer where ordre_id = '$id' and vare_id >= 0";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($r['rabat']) {
				$newDiscount = $totalrabat + $r['rabat'] - ($r['rabat'] * $totalrabat / 100);
				$newDiscountTxt = $r['beskrivelse'] . "+$discountTxt";
			} else {
				$newDicsount = $totalrabat;
				$newDiscountTxt = $discountTxt;
			}
			$qtxt = " update ordrelinjer set rabat = '$newDiscount' where id='$r[id]'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	}
}
if ($vare_id_ny && !$vare_id) {
	if ($folger) {
		$qtxt = "select max(id) as linje_id from ordrelinjer where ordre_id='$id' and vare_id='$folger'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$tmp = (int) $r['linje_id'];
		$r = db_fetch_array(db_select("select tilfravalg from ordrelinjer where id='$tmp'", __FILE__ . " linje " . __LINE__));
		($r['tilfravalg']) ? $tmp2 = $r['tilfravalg'] . chr(9) . $vare_id_ny : $tmp2 = $vare_id_ny;
		db_modify("update ordrelinjer set tilfravalg='$tmp2' where id='$tmp'", __FILE__ . " linje " . __LINE__);
		$vare_id_ny = NULL;
	}
	$vare_id = $vare_id_ny;
} elseif (($vare_id_ny && $vare_id) || (!$id && isset($_POST['afslut']) && $_POST['afslut'])) { #20161014-4
	if (!$id || $id == 0)
		$id = opret_posordre(NULL, $kasse);
	if (!isset($momssats)) { #20140526
		$r = db_fetch_array(db_select("select momssats from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__));
		$momssats = $r['momssats'];
	}
	$qtxt = "select varenr,beskrivelse,salgspris,samlevare from varer where id = '$vare_id'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if ($r['samlevare']) {
		opret_saet($id, $vare_id, $pris_ny, $momssats, $antal_ny, $incl_moms, $lager_ny);
		#	} elseif (isset($giftcardPris) && isset($giftcardAntal)) {  #2020114
#		$sum=opret_ordrelinje($id,$vare_id,$r['varenr'],$giftcardAntal,'',$giftcardPris,0,100,'PO','','','0','on','','','','','','0',$lager_ny,__LINE__);
	} else {
		$sum = opret_ordrelinje($id, $vare_id, $r['varenr'], 1, '', $pris_ny, 0, 100, 'PO', '', '', '0', 'on', '', '', '', '', '', '0', $lager_ny, __LINE__); #20140426
		if ($folger && $vare_id_ny) {
			$qtxt = "select max(id) as linje_id from ordrelinjer where ordre_id='$id' and vare_id='$vare_id'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			if (isset($_GET['tilfravalgNy']) && $_GET['tilfravalgNy'])
				$tilfravalgNy = str_replace('|', chr(9), $_GET['tilfravalgNy']);
			($tilfravalgNy) ? $tilfravalgNy .= chr(9) . $vare_id_ny : $tilfravalgNy = $vare_id_ny;
			$qtxt = "update ordrelinjer set tilfravalg='$tilfravalgNy'";
			if ($antal_ny)
				$qtxt .= ", antal='$antal_ny'"; #20210320
			$qtxt .= " where id='$r[linje_id]'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			$vare_id_ny = NULL;
			$folger = $vare_id;
		}
	}
	if ($id && !$vare_id_ny && !$folger && $initId == '0') { #20200112
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">";
		exit;
	}
	$vare_id = $vare_id_ny;
}
$funktion = if_isset($_GET['funktion']);
if ($funktion) {
	$sort = (int) if_isset($_GET['sort']);
	$funktion('PO', $sort, $fokus, $id, "", "", "");
}
$spec_func = if_isset($_GET['spec_func']);
if ($spec_func) {
	$kode = if_isset($_POST['kode']);
	include("../includes/spec_func.php");
	$svar = $spec_func('xx', $id, $kode);
	if (!is_numeric($svar)) {
		print "<BODY onLoad=\"javascript:alert('$svar')\">\n";
	} else
		$konto_id = $svar;
}
#20140508 ->
/*
if (!$_POST['bordvalg'] && isset($_GET['bordnr']) && $_GET['bordnr']) $bordnr=$_GET['bordnr']*1;
if (isset($_POST['bordnr']) && $_GET['bordnr']) $bordnr=$_POST['bordnr']*1;
if (isset($_POST['pre_bordnr'])) $pre_bordnr=$_POST['pre_bordnr'];
if (($pre_bordnr || $pre_bordnr=='0') && ($bordnr || $bordnr=='0') && $pre_bordnr != $bordnr && !$bordnr_ny) {
	$r=db_fetch_array(db_select("select max(id) as id from ordrer where nr='$bordnr'",__FILE__ . " linje " . __LINE__));
	$id=$r['id']*1;
}
*/
if (isset($_POST['flyt_bord'])) {
	# Check if the old system is in use.
	$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	if ($r[0]) {
		$bord = explode(chr(9), str_replace("\n", "  ", $r[0]));
	} else {
		$bord = array();
	}
	if (count($bord) == 0) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../bordplaner/table_plan.php?id=$id&flyt=$bordnr\">\n";
	} else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../bordplaner/bordplan.php?id=$id&flyt=$bordnr\">\n";
	}

}
$df = if_isset($_POST['delflyt']);
$delflyt = NULL;
if ($df) {
	$flyt = 0;
	for ($x = 1; $x <= count($df); $x++) {
		list($a, $b, $c) = explode(':', $df[$x]);
		if ($c)
			$flyt = 1;
		if ($x == 1)
			$delflyt = $df[$x];
		else
			$delflyt .= "|" . $df[$x];
	}
}
if ($delflyt && $flyt) {
	# Check if the old system is in use.
	$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	if ($r[0]) {
		$bord = explode(chr(9), str_replace("\n", "  ", $r[0]));
	} else {
		$bord = array();
	}

	if (count($bord) == 0) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../bordplaner/table_plan.php?id=$id&flyt=$bordnr&delflyt=$delflyt\">\n";
	} else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../bordplaner/bordplan.php?id=$id&flyt=$bordnr&delflyt=$delflyt\">\n";
	}
}
if (!$id && $kasse && !isset($_GET['bordnr'])) {
	$qtxt = "select box13 from grupper where art='POS' and kodenr = '2' and fiscal_year = '$regnaar'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if ($r['box13']) {
		$tmparray = explode(chr(9), $r['box13']);
		$bordnr = $tmparray[$kasse - 1];
	}
}
#$del_bord=if_isset($_POST['del_bord']);
# <- 20140508
$kontonr = if_isset($_POST['kontonr']);
if (!$kontonr)
	$kontonr = '0';
if (!$konto_id)
	$konto_id = if_isset($_GET['konto_id']);
if ($konto_id || $kontonr) {
	$konto_id *= 1;
	$id = opdater_konto($konto_id, $kontonr, $id);
	$r = db_fetch_array(db_select("select momssats,sum,betalt,betalingsbet from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__));
	$betalingsbet = $r['betalingsbet'];
	$momssats = (float) $r['momssats'];
	if ($betalingsbet != 'Kontant')
		$modtaget = (float) $r['betalt'];
	$sum = (float) $r['sum'];
	$betaling = 'ukendt';
	#	if ($modtaget <= $sum) $id=afslut($id,'konto',$modtaget);
#	else $betaling='ukendt';
}
if (if_isset($_POST['koekken'])) {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=koekkenprint.php?id=$id&bordnr=$bordnr&bordnavn=$bordnavn\">\n";
} elseif (if_isset($_POST['send_koekken'])) {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=koekkenprint.php?id=$id&bordnr=$bordnr&bordnavn=$bordnavn&send_nu=1\">\n";
} elseif (if_isset($_POST['kor_bord'])) {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=koekkenprint.php?id=$id&bordnr=$bordnr&bordnavn=$bordnavn&kor_bord=1\">\n";
}
if (if_isset($_POST['saet'])) {
	gendan_saet($id);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=saetpris.php?id=$id\">\n";
}
$r = db_fetch_array(db_select("select box6,box12 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
$div_kort_kto = $r['box6'];
$vis_saet = trim($r['box12']);
if ($vare_id) {
	$r = db_fetch_array(db_select("select varenr from varer where id = '$vare_id'", __FILE__ . " linje " . __LINE__));
	$varenr_ny = $r['varenr'];
} elseif (sizeof($_POST) > 1) {
	$ny_bruger = if_isset($_POST['ny_bruger']);
	$kode = if_isset($_POST['kode']);
	if (isset($_SESSION['creditType'])) {
		countCorrection($id, $kasse);
	}
	$indbetal = if_isset($_POST['indbetal']);
	if ($indbetal || $afslut) {
		$qtxt = "select kodenr from grupper where art = 'POSBUT' and box6='A'";
		if ($afd)
			$qtxt .= " and (box12='$afd' or box12='') order by box12 desc limit 1";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
			$sidemenu = $r['kodenr'];
	} else
		$indbetaling = if_isset($_POST['indbetaling']);
	$sum = if_isset($_POST['sum']);
	$afrundet = if_isset($_POST['afrundet']);
	$betaling = if_isset($_POST['betaling']);
	if (substr($betaling, 0, 9) == "Betalings" && !strpos($betaling, 'på beløb'))
		$betaling = 'Betalingskort'; #20170914
	elseif (substr($betaling, 0, 8) == "Bet.kort")
		$betaling = 'Betalingskort på beløb';
	$betaling2 = if_isset($_POST['betaling2']);
	$kontonr = if_isset($_POST['kontonr']);
	$modtaget = if_isset($_POST['modtaget']);
	$betvaluta = if_isset($_POST['betvaluta']);
	$betvalkurs = if_isset($_POST['betvalkurs']);
	$rest = if_isset($_POST['rest']); #20161010
#	$modtaget2=if_isset($_POST['modtaget2']);
	$kundeordnr = if_isset($_POST['kundeordnr']);
	$fokus = if_isset($_POST['fokus']);
	$varenr_ny = db_escape_string(trim(if_isset($_POST['varenr_ny'])));
	$lager_ny = if_isset($_POST['lager_ny']);
	$tilfravalgNy = if_isset($_POST['tilfravalgNy']);
	if (count($lagernr)) {
		if ($lager_ny && !is_numeric($lager_ny)) {
			for ($l = 0; $l < count($lagernr); $l++) {
				if (strtolower($lager_ny) == strtolower($lagernavn[$l]))
					$lager_ny = $lagernr[$l];
			}
		}
		if ($lager_ny && !is_numeric($lager_ny)) {
			print tekstboks("Lager >$lager_ny< ikke fundet");
			$lager_ny = $afd_lager;
		}
		if (!$lager_ny)
			$lager_ny = $afd_lager;
		$lager_ny = (int) $lager_ny;
	}
	if ($varenr_ny == 't') {
		$varenr_ny = NULL;
		$sidemenu = NULL;
	} else
		$afslut = if_isset($_POST['afslut']);

	$leveret = if_isset($_POST['leveret']);
	if (isset($_POST['antal_ny']))
		$antal_ny = strtolower(trim($_POST['antal_ny']));
	#checkVoucherBuy($antal_ny);
	if (if_isset($_POST['antal'], NULL)) { #20140623
		if (!$antal_ny && $antal_ny != '0')
			$antal_ny = $_POST['antal'];
		elseif ($antal_ny == 't' || $antal_ny == 'p' || $antal_ny == 'r' || $antal_ny == 'a')
			$antal_ny = $_POST['antal'] . $antal_ny;
		if ($varenr_ny != 'v')
			$fokus = 'antal_ny';
	}
	$pris_ny = if_isset($_POST['pris_ny']);
	if (!$pris_ny && if_isset($_POST['pris_old'])) {
		$pris_ny = $_POST['pris_old'];
	}
	if (if_isset($_POST['pris']) || $pris_ny) { #20140814 -> 20161013 tilføjet $pris_ny ellers fungerer den ikke med 0 pris?
		if (isset($_POST['pris_old']))
			countPriceCorrectionSetup($pris_ny, $_POST['pris_old']);
		if (!$pris_ny && $pris_ny != '0')
			$pris_ny = $_POST['pris'];
		elseif ($pris_ny == 'p' || $pris_ny == 'r' || $pris_ny == 'a') {
			$antal_ny .= $pris_ny;
			$pris_ny = $_POST['pris'];
		} elseif (substr($pris_ny, -1) == 'p' || substr($pris_ny, -1) == 'r' || substr($pris_ny, -1) == 'a') {
			$antal_ny .= substr($pris_ny, -1);
			$pris_ny = substr($pris_ny, 0, strlen($pris_ny) - 1);
		}
		if ($varenr_ny != 'v')
			$fokus = 'antal_ny';
	}
	$beskrivelse_ny = db_escape_string(trim(if_isset($_POST['beskrivelse_ny'])));
	$beskrivelse_old = db_escape_string(trim(if_isset($_POST['beskrivelse_old']))); #20210812
	$barcodeNew = db_escape_string(trim(if_isset($_POST['barcodeNew'])));
	$momssats = (if_isset($_POST['momssats']));
	$rabat_ny = if_isset($_POST['rabat_ny']);
	if (!$rabat_ny && $rabat_ny != '0' && if_isset($_POST['rabat_old']))
		$rabat_ny = $_POST['rabat_old'];
	if (strpos($betaling, 'på beløb') && strlen($varenr_ny) > 1) { #20201029
		$priceNew = (float) usdecimal($pris_ny);
		if ($varenr_ny && !$priceNew) {
			$betaling = NULL;
		}
	}
	if (strpos($betaling, 'på beløb')) {
		if (!$id || $id == 0)
			$id = opret_posordre(NULL, $kasse);
		$antal_ny = 1;
		if ($id && $varenr_ny && strlen($varenr_ny) > 1) {
			$qtxt = "select id,salgspris,beskrivelse,samlevare from varer where varenr = '$varenr_ny' or stregkode='$varenr_ny'";
			if (strlen($varenr_ny) == 12 && is_numeric($varenr_ny))
				$qtxt .= " or stregkode='0$varenr_ny'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			if ($r['samlevare']) {
				opret_saet($id, $r['id'], $pris_ny, $momssats, $antal_ny, $lager_ny);
			} else
				$linje_id = opret_ordrelinje($id, $r['id'], $varenr_ny, 1, '', usdecimal($pris_ny, 2), 0, 100, 'PO', '', '', '0', 'on', '', '', '', '', '', '0', $lager_ny, __LINE__); #20140226
			if ($linje_id && $tilfravalgNy) {
				db_modify("update ordrelinjer set tilfravalg = '$tilfravalgNy' where id = '$linje_id'", __FILE__ . " linje " . __LINE__);
			}
		}
		$varenr_ny = NULL;
		if ($kundedisplay) {
			kundedisplay($r['beskrivelse'], $r['pris_ny'] * $r['antal'], 0);
			#			kundedisplay('Subtotal',$sum,0);
		}
	}
	if (strtolower($antal_ny) == 'a') {
		$antal_ny = 1;
		$afslut = NULL;
	}
	$sum *= 1;
	if ($kundeordnr && $id)
		db_modify("update ordrer set kundeordnr = '$kundeordnr' where id='$id'", __FILE__ . " linje " . __LINE__);

	if (strstr($pris_ny, ",")) { #Skaerer orebelob ned til 2 cifre.
		list($kr, $ore) = explode(",", $pris_ny);
		$ore = substr($ore, 0, 2);
		$pris_ny = $kr . "," . $ore;
	}
	if (isset($_POST['ny']) && $_POST['ny'] == "Ny kunde") {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php\">\n";
		exit;
		#		$id=0;
#		$kontonr=0;
#		$menu_id=NULL;
#		$bon=NULL;
	}
	$xReport = (isset($_POST['xRapport']) && $_POST['xRapport'] == "X-Rapport") ? True : False;
	$zReport = (isset($_POST['zRapport']) && $_POST['zRapport'] == "Z-Rapport") ? True : False;
	if (!$id && !$varenr_ny && $kundedisplay)
		kundedisplay('**** Velkommen ****', '', '1');
	if ((isset($_POST['kopi']) && $_POST['kopi'] == "Kopier") || (isset($_POST['proforma']) && $_POST['proforma']) || (isset($_POST['udskriv']) && $_POST['udskriv'] == "Udskriv") || $xReport || $zReport) {
		$momssats = (float) $momssats;
		if ($id && (!$xReport && !$zReport)) {
			if ($tracelog)
				fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls: pos_txt_print($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling)\n");
			$delayLoad = pos_txt_print($id, $betaling, $betaling2, $modtaget, $modtaget2, $indbetaling);
			if ($tracelog)
				fwrite($tracelog, __FILE__ . " " . __LINE__ . " delayLoad = $delayLoad\n");
		} elseif (isset($_POST['kopier']) && $_POST['kopier'] == "Kopier" && $linjeantal > 0) {
			$tmp = $kasse;
			if (!$tmp)
				$tmp = 1;
			$qtxt = "select max(id) as id from ordrer where art = 'PO' and status >= '3' and felt_5 = '$tmp'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			if ($tracelog)
				fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls: pos_txt_print($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling)\n");
			$delayLoad = pos_txt_print($r['id'], $betaling, $betaling2, $modtaget, $modtaget2, $indbetaling);
			if ($tracelog)
				fwrite($tracelog, __FILE__ . " " . __LINE__ . " delayLoad = $delayLoad\n");
		} elseif ($xReport || $zReport) {
			$reportVar = setReportType($xReport, $zReport);
			if ($tracelog)
				fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls: pos_txt_print($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling,$reportVar)\n");
			pos_txt_print($id, $betaling, $betaling2, $modtaget, $modtaget2, $indbetaling, $reportVar);
		} elseif (getCountry() == "Norway") {
			printWarningMessage("proforma");
		} elseif (!$id || $id = '0') {
			$tmp = $kasse;
			if (!$tmp)
				$tmp = 1;
			$qtxt = "select max(id) as id from ordrer where art = 'PO' and status >= '3' and felt_5 = '$tmp'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			if ($tracelog)
				fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls: pos_txt_print($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling)\n");
			$delayLoad = pos_txt_print($r['id'], $betaling, $betaling2, $modtaget, $modtaget2, $indbetaling);
		}
	} elseif (isset($_POST['udskriv_sidste']) && $_POST['udskriv_sidste']) {
		$momssats = (float) $momssats;
		$tmp = $kasse;
		if (!$tmp)
			$tmp = 1;
		$qtxt = "select id from ordrer where art = 'PO' and fakturadate = '" . date('Y-m-d') . "' and status >= '3' and felt_5 = '$tmp' ";
		$qtxt .= "order by tidspkt desc limit 1"; #20210903
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($tracelog)
			fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls: pos_txt_print($r[id],$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling)\n");
		$delayLoad = pos_txt_print($r['id'], $betaling, $betaling2, $modtaget, $modtaget2, $indbetaling);
		if ($tracelog)
			fwrite($tracelog, __FILE__ . " " . __LINE__ . " delayLoad = $delayLoad\n");
	}
	if (isset($_POST['skuffe'])) { #LN 20190218 Remove check of what the skuffe index equals, because we now have different languages
		aabn_skuffe($id, $kasse);
	}
	if (isset($_POST['krediter'])) {
		list($ny_id, $samlet_pris) = explode(";", krediter_pos($id)); #20170622-1
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$ny_id&samlet_pris=$samlet_pris\">\n"; #20170622-1
		$_SESSION['creditType'] = 'krediter';		# LN 20190206
	} elseif (isset($_POST['return'])) {		# LN 20190206
		list($ny_id, $samlet_pris) = explode(";", krediter_pos($id)); #20170622-1		
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$ny_id&samlet_pris=$samlet_pris\">\n"; #20170622-1		
		$_SESSION['creditType'] = 'return';
	} elseif (isset($_POST['Udskriv'])) {
		$_SESSION['creditType'] = 'printReceipt';
	}
	if ($fokus == "antal_ny" && $antal_ny != '0' && !$pris_ny && substr($antal_ny, -1) != 't')
		$antal_ny .= "p";
	if ($fokus == "pris_ny" && $pris_ny != 'f' && substr($pris_ny, -1) != 'r')
		$fokus = "antal_ny"; #20130310 tilføjet: "&& substr($pris_ny,-1)!='r'" samt 2 næste linjer
	if ($fokus == "pris_ny" && $pris_ny != 'f' && substr($pris_ny, -1) == 'r') {
		$pris_ny = str_replace("r", "", $pris_ny);
		$fokus = 'rabat_ny';
	} elseif ($fokus == "rabat_ny" && $pris_ny != 'f')
		$fokus = "antal_ny";
	if ($fokus == "antal_ny" && (substr($antal_ny, -1) == 'p' || substr($antal_ny, -1) == 'r') || substr($antal_ny, -1) == 't') {
		if (substr($antal_ny, -1) == 'p')
			$fokus = 'pris_ny';
		elseif (substr($antal_ny, -1) == 't')
			$fokus = 'beskrivelse_ny'; #20210810 
		else
			$fokus = 'rabat_ny';
		if (strlen($antal_ny) > 1)
			$antal_ny = substr($antal_ny, 0, strlen($antal_ny) - 1);
		else
			$antal_ny = 1;
	} elseif ($fokus == "varenr_ny" && ($varenr_ny == 'a' || $varenr_ny == 'v' || strlen($varenr_ny) > 1)) {
		if ($varenr_ny == 'v') {
			vareopslag('PO', "", 'varenr', $id, "", "$ref", "");
		} elseif (!$id && $varenr_ny == 'a') { #20161014-3
			$varenr_ny = NULL;
			/*	
					} elseif (strlen($varenr_ny)>1) {
						$qtxt="SELECT id,vare_id,variant_type FROM variant_varer WHERE variant_stregkode = '$varenr_ny'"; #20160220
						if (strlen($varenr_ny)==12 && is_numeric($varenr_ny)) $qtxt.=" or variant_stregkode='0$varenr_ny'";
						if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							$qtxt="select id from varer where varenr = '$varenr_ny' or lower(varenr) = '".strtolower($varenr_ny)."'";
							$qtxt.=" or lower(stregkode) = '".strtolower($varenr_ny)."'";
							if (strlen($varenr_ny)==12 && is_numeric($varenr_ny)) $qtxt.=" or stregkode='0$varenr_ny'";
							if(!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							vareopslag('PO',"",'beskrivelse', $id,"","$ref","*$varenr_ny*");
							}
						}
			*/
		}
	}

	if ($fokus == "pris_ny" && substr($pris_ny, -1) == 'r') {
		$pris_ny = substr($pris_ny, 0, strlen($pris_ny) - 1);
		$fokus = "rabat_ny";
	} elseif (isset($_POST['forfra']) && $id) {
		if (isset($_SESSION['creditType'])) {
			unset($_SESSION['creditType']);
		}
		$id *= 1;
		hent_shop_ordrer('', '');
		$r = db_fetch_array(db_select("select sum(amount) as amount from pos_betalinger where ordre_id = '$id'", __FILE__ . " linje " . __LINE__));
		if ($r['amount']) { #20180704
			print "<table align='center' width='100%'><tbody>";
			print "<tr><td><br></td></tr>";
			print "<tr><td align='center'><big>Der er modtaget " . dkdecimal($r['amount']) . " på denne bestilling</big></td></tr>";
			print "<tr><td><br></td></tr>";
			print "<tr><td align='center'><big>Bestillingen kan ikke nulstilles</big></td></tr>";
			print "<tr><td><br></td></tr>";
			print "<tr><td align='center'><input type=\"button\" style=\"width:100px;\" onclick=\"window.location.href='pos_ordre.php?id=$id'\" value=\"OK\"></td></tr>\n";
			print "</tbody></table>";
			exit;
		} elseif ($_POST['sum']) {
			$price = (float) $_POST['sum'];
			db_modify("insert into deleted_order (price, kasse, ordre_id) values ('$price', '$kasse', '$id')", __FILE__ . " linje " . __LINE__);
		}
		$r = db_fetch_array(db_select("select status from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__));
		$status = $r['status'];
		if ($status < 3) {
			$qtxt = "select * from grupper where art = 'POS' and kodenr = '1' and fiscal_year ='$regnaar'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$moms = explode(chr(9), $r['box7']);
			$x = $kasse - 1;
			if ($moms[$x]) {
				$r = db_fetch_array(db_select("select * from grupper where art = 'SM' and kodenr = '$moms[$x]'", __FILE__ . " linje " . __LINE__));
				$momssats = $r['box2'];
			} else
				$momssats = '0';
			#$nr*=1;
			$dd = date("Y-m-d");
			$qtxt = "update ordrer set konto_id='0', kontonr='0',firmanavn='',addr1='',addr2='',postnr='',bynavn='',land='',betalingsdage='0',";
			$qtxt .= "betalingsbet='Kontant',cvrnr='',ean='',institution='',email='',kontakt='',art='PO',valuta='DKK',valutakurs='100',";
			$qtxt .= "kundeordnr='',ordredate='$dd',hvem='',momssats='$momssats',ref='' where id = '$id'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			db_modify("delete from ordrelinjer where ordre_id='$id'", __FILE__ . " linje " . __LINE__);
			$varenr_ny = '';
			$antal_ny = '';
			$modtaget = '';
			$betaling = '';
			$indbetaling = '';
			$fokus = "varenr_ny";
			$r = db_fetch_array(db_select("select id from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__));
			$id = $r['id'];
		}
		if ($kundedisplay)
			kundedisplay('', '', '1');
	} elseif (substr($modtaget, -1) == 't' || substr($modtaget2, -1) == 't')
		$betaling = "";
	#	elseif (substr($modtaget,-1)=='d' && !$betaling) $betaling="creditcard";
	elseif (substr($modtaget, -1) == 'c' && !$betaling)
		$betaling = "kontant";
	elseif (substr($modtaget, -1) == 'g' && !$betaling)
		$betaling = "voucher";
	elseif (substr($modtaget, -1) == 'k' || $betaling == "konto") {
		if (substr($modtaget, 0, 1) == '+')
			$modtaget = $sum + usdecimal(substr($modtaget, 1, strlen($modtaget) - 1), 2);
		elseif (!is_numeric(substr($modtaget, -1)))
			$modtaget = substr($modtaget, 0, strlen($modtaget) - 1);
		if (!$modtaget || !$kontonr)
			pos_kontoopslag('PO', "", $fokus, $id, "", "", "");
	} elseif (isset($_POST['debitoropslag']) || isset($_POST['kreditoropslag'])) {
		(isset($_POST['debitoropslag'])) ? $tmp = 'PO' : $tmp = 'KO';
		kontoopslag($tmp, "", "varenr_ny", $id, "", "", "", "", "", "", "", "", "", "", "", "", "");
	} elseif (isset($_POST['stamkunder']) || isset($_GET['stamkunder'])) {
		stamkunder('PO', "", "varenr_ny", $id, "", "", "", "", "", "", "", $sum);
	} elseif (isset($_POST['kontoudtog'])) {
		kontoudtog($id, $konto_id);
	} elseif (isset($_POST['vouchersale'])) {
		vouchersale($id, $konto_id);
	} elseif (isset($_POST['voucherstatus'])) {
		voucherstatus($id, $konto_id);
	}

	if ($indbetaling) {
		$indbetaling = str_replace("a", "", $indbetaling);
		if ($fokus == 'indbetaling') { #20160220-2
			if (!is_numeric(str_replace(",", "", $indbetaling))) {
				$b = substr($indbetaling, -1);
				if ($b == 't') { #20160418-2
					print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
					exit;
				}
				$i = str_replace($b, '', $indbetaling);
				$usi = (str_replace(".", "", $i));
				$usi = (str_replace(",", ".", $usi));
				if (is_numeric($usi)) {
					$m = (float) usdecimal($modtaget, 2);
					if ($usi > $m && $modtaget != '') {
						$alert1 = findtekst(1863, $sprog_id);
						print "<BODY onLoad=\"javascript:alert('$alert1')\">\n";
						$indbetaling = 'Indbetaling konto';
						$modtaget = 0;
					} elseif (!$modtaget) {
						$indbetaling = $i;
						$modtaget = $i;
					} elseif ($usi == $m) {
						$fokus = 'modtaget';
						$tmp = $modtaget;
						$modtaget = $indbetaling;
						$indbetaling = $tmp;
					} else {
						$indbetaling = $i;
					}
				}
			}
		} elseif ($fokus == 'modtaget') { #20160418
			if ($indbetaling && $m == '')
				$modtaget = $indbetaling; #20161205
			if (!is_numeric(str_replace(",", "", $modtaget))) {
				$b = substr($modtaget, -1);
				if ($b == 't') { #20160418-2
					print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
					exit;
				}
				$m = str_replace($b, '', $modtaget);
				$usm = (str_replace(".", "", $m));
				$usm = (str_replace(",", ".", $usm)); # 20151205 rettet usi til usm
				if (is_numeric($usm)) {
					$i = (float) usdecimal($indbetaling, 2);
					if ($i > $usm && $m != '') {
						$alert = findtekst(1863, $sprog_id);
						print "<BODY onLoad=\"javascript:alert('$alert')\">\n";
						$indbetaling = 'Indbetaling konto';
						$modtaget = 0;
					} elseif (!$m) {
						$indbetaling = $i;
						$modtaget = $i;
					} elseif ($usm == $i) {
						$fokus = 'modtaget';
						$modtaget = $indbetaling;
						$indbetaling = $m;
					}
				}
			}
		}
		$tmp = trim(str_replace(".", "", $indbetaling));
		$tmp = str_replace(",", ".", $tmp);
		if (is_numeric($tmp)) {
			$indbetaling = (float) usdecimal($indbetaling, 2);
			$modtaget = (float) usdecimal($modtaget, 2);
			if ($indbetaling < 0 && $modtaget != $indbetaling) { #20160902
				$alert1 = findtekst(1864, $sprog_id);
				print "<BODY onLoad=\"javascript:alert('$alert1')\">\n";
				$indbetaling = 'Indbetaling konto'; #20160220-2
				$modtaget = 0; #20160220-2
			}
			if ($indbetaling > $modtaget && $modtaget != 0) {
				$alert = findtekst(1863, $sprog_id);
				print "<BODY onLoad=\"javascript:alert('$alert')\">\n";
				#				$indbetaling=$modtaget;
				$indbetaling = 'Indbetaling konto'; #20160220-2
				$modtaget = 0; #20160220-2
			}
		}
	} elseif ($indbetal) {
		$indbetaling = $indbetal;
		#exit;
	} elseif ($betaling && ($betaling != 'ukendt' || substr($modtaget, 0, 1) == '/')) { #20160817
		if (substr($modtaget, 0, 1) == '/') { #Delbetaling
			$modtaget = substr($modtaget, 1);
			if (!is_numeric(substr($modtaget, -1))) {
				$delbetaling = usdecimal(substr($modtaget, 0, strlen($modtaget) - 1));
				$sluttegn = substr($modtaget, -1);
			} else {
				$delbetaling = $modtaget;
				$sluttegn = '';
			}
			$modtaget = dkdecimal($sum / $delbetaling, 2);
			$modtaget .= $sluttegn;
		}# else $delbetaling=if_isset($_POST['delbetaling']);
		if (substr($modtaget, 0, 1) == '+')
			$modtaget = $sum + usdecimal(substr($modtaget, 1, strlen($modtaget) - 1), 2);
		elseif (strlen($modtaget) == 1 && !is_numeric($modtaget))
			$modtaget = '';
		elseif (!is_numeric(substr($modtaget, -1)))
			$modtaget = usdecimal(substr($modtaget, 0, strlen($modtaget) - 1), 2);
		else
			$modtaget = usdecimal($modtaget, 2);
		if (!$modtaget) {
			$modtaget = $sum;
			$r = db_fetch_array(db_select("select sum(amount) as amount from pos_betalinger where ordre_id='$id'", __FILE__ . " linje " . __LINE__));
			$modtaget -= $r['amount'];
			$rest = $modtaget;
			if ($betaling == 'Kontant')
				$modtaget = pos_afrund($modtaget, $difkto, '');
			if ($betvalkurs) {
				$modtaget *= 100 / $betvalkurs;
				$rest = $modtaget;
			}
		} #else $modtaget*=100/$betvalkurs;
		if (substr($modtaget2, 0, 1) == '+')
			$modtaget2 = $sum + usdecimal(substr($modtaget2, 1, strlen($modtaget2) - 1), 2);
		elseif (!is_numeric(substr($modtaget2, -1)))
			$modtaget2 = usdecimal(substr($modtaget2, 0, strlen($modtaget2) - 1), 2);
		else
			$modtaget2 = usdecimal($modtaget2, 2);
		$modtaget2 = (float) $modtaget2;
		#		if (!$modtaget2) $modtaget2=$sum;
	} else
		$modtaget = usdecimal($modtaget, 2);
	$modtaget *= 1;
	$betalt = $modtaget + $modtaget2;
	if ($betaling == 'Konto' && $sum && !$modtaget * 1)
		$modtaget = $sum;

	if ($delbetaling) {
		if ($betaling == 'Kontant')
			$modtaget = pos_afrund($modtaget, $difkto, '');
	}
	if (($betalt || ($afslut == 'on') && is_numeric($betalt)) || (!$sum && ($afslut || $betaling))) { #20150522 + 20161014 20161017
		if (!$indbetaling && !$sum && $afslut == "Afslut" && !$betaling) {
			$betaling = "ukendt";
		}
		$afslut = "OK";
		if (!is_numeric($sum))
			$afslut = NULL;
		if (!$sum && !$betaling)
			$afslut = NULL;
		#20161014-2 -> 3 linjer		
#		if  ($betaling == 'Kontant' && $sum > 0 && $betalt < pos_afrund($rest,$difkto,'') && !$indbetaling) $afslut=NULL; #20160611
#		elseif ($betaling == 'Konto' && $betalingsbet == 'Kontant' && $betalt < pos_afrund($rest,$difkto,'') && !$indbetaling) $afslut=NULL;
#		elseif ($betaling != 'Kontant' && $betalt < $rest && !$indbetaling) $afslut=NULL; # 20130613 Indsat $betaling != 'Kontant'		
		if (!$betaling)
			$afslut = NULL;
		if (strpos($betaling, 'på beløb'))
			$afslut = NULL;
		if ($betaling == "ukendt")
			$afslut = NULL;
		if ($betaling2 && $betaling2 == "ukendt")
			$afslut = NULL;
		if ($modtaget2 && (!$betaling2 || $betaling2 == "ukendt"))
			$afslut = NULL;
		if ($indbetaling && !$modtaget)
			$afslut = NULL;
		if ($afslut == "OK") {
			$svar = afslut($id, $betaling, $betaling2, $modtaget, $modtaget2, $indbetaling, NULL, NULL, $receipt_id, __LINE__);
			if ($svar)
				print "<BODY onLoad=\"javascript:alert('$svar')\">\n";
			else {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=$php_self\">\n";
			}
		} elseif ($delbetaling && $betaling != 'ukendt') {
			include_once("pos_ordre_includes/paymentFunc/partPayment.php");
			$svar = delbetal($id, $betaling, $betaling2, $modtaget, $modtaget2, $indbetaling, NULL, NULL, $receipt_id, __LINE__);
			if ($svar)
				print "<BODY onLoad=\"javascript:alert('$svar')\">\n";
			else {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=$php_self\">\n";
			}
		}
	} else {
		if ($rabat_ny) {
			$tmp = str_replace(",", "", $rabat_ny);
			$tmp = str_replace(".", "", $tmp);
			$tmp = str_replace("*", "", $tmp);
			if (!is_numeric($tmp) || $tmp > 9999999) { #20210403
				$next_varenr = $tmp;
				$rabat_ny = 0;
			}
		}
		$tmp = str_replace(",", ".", $antal_ny);
		if ($varenr_ny == "a") {
			$betaling = "ukendt";
			$varenr_ny = NULL;
		} elseif ($antal_ny == "a") {
			$betaling = "ukendt";
			$antal_ny = 1;
		} elseif ($antal_ny && !is_numeric($tmp) || $tmp > 99999) { # Så er der skannet et varenummer ind som antal
			$next_varenr = $antal_ny;
			$antal_ny = 1;
		} elseif ($fokus == "antal_ny") {
			if ($antal_ny == "0")
				$varenr_ny = NULL;
			elseif (!strlen($antal_ny))
				$antal_ny = 1;
			else
				$antal_ny = usdecimal($antal_ny, 2);
		} elseif ($antal_ny == "0" && if_isset($_POST['antal']))
			$varenr_ny = NULL; #20140623
		#if ($varenr_ny && $antal_ny && $fokus!="pris_ny" && $fokus!="rabat_ny") {
		if ($varenr_ny && $antal_ny && $fokus != "pris_ny" && $fokus != "rabat_ny" && $fokus != "beskrivelse_ny") {# 20210811 beskrivelse_ny added 
			if (!$id || $id == 0) {
				$id = opret_posordre(NULL, $kasse);
			}
			if ($id && !is_numeric($id)) {
				alert("$id");
			} else {
				if (strlen($rabat_ny) > 1 && substr($rabat_ny, -1) == '*') { #20140828-2
					$rabat_ny *= 1;
					db_modify("update ordrelinjer set rabat='$rabat_ny' where ordre_id='$id' and vare_id >'0' and rabat=0", __FILE__ . " linje " . __LINE__);
				}
				$qtxt = "select id,samlevare from varer where varenr = '$varenr_ny' or stregkode = '$varenr_ny'"; #20200929
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				if ($r['samlevare'])
					opret_saet($id, $r['id'], usdecimal($pris_ny, 2), $momssats, $antal_ny, 'on', $lager_ny);
				else {
					($beskrivelse_ny) ? $textNew = $beskrivelse_ny : $textNew = $beskrivelse_old; #20210906
					$svar = opret_ordrelinje($id, '', $varenr_ny, $antal_ny, $textNew, usdecimal($pris_ny, 2), usdecimal($rabat_ny, 2), 100, 'PO', '', '', '0', 'on', '', '', '', '', '', '0', $lager_ny, __LINE__); #20140226 + 20140814 + 20200603
				}
				if (usdecimal($pris_ny, 2) == 0.00)
					$obstxt = "Obs, vare $varenr_ny sælges til kr 0,00";
				if ($svar && !is_numeric($svar)) {
					print "<BODY onLoad=\"javascript:alert('$svar')\">\n";
					$fokus = "pris_ny";
				} else {
					$r = db_fetch_array(db_select("select max(id) as linje_id from ordrelinjer where ordre_id = '$id' and varenr='$varenr_ny'", __FILE__ . " linje " . __LINE__));
					if ($r['linje_id'] && isset($leveret[0]) && is_numeric($leveret[0]))
						db_modify("update ordrelinjer set leveret='$leveret[0]' where id='$r[linje_id]'", __FILE__ . " linje " . __LINE__);
					$varenr_ny = $next_varenr;
					$tmp = $antal_ny; #Til kundedisplay
					$antal_ny = NULL;
					#			$sum=0;
				}
				/*
								if ($kundedisplay) {
									 kundedisplay($beskrivelse_ny,usdecimal($pris_ny,2)*$tmp,0);
				#					kundedisplay('Subtotal',$sum+$pris_ny*$tmp,0);
								}
				*/
			}
		} elseif ($varenr_ny)
			$sum = find_pris($varenr_ny);
		#		else $sum=0;
	}
}

############################
$x = 0;
if ($id) {
	$qtxt = "select id from ordrer where id = '$id' and art = 'PO'";
	if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $id = 0;
}
if ($id && $gem) {
	if (!$afd) { #20150302
		$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='1' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
		$kasseantal = (int) $r['box1'];
		$afdelinger = explode(chr(9), $r['box3']);
		$tmp = $kasse - 1;
		$afd = (int) $afdelinger[$tmp];
	}
	$r = db_fetch_array(db_select("select max(ordrenr) as ordrenr from ordrer where art = 'DO'", __FILE__ . " linje " . __LINE__));
	$ordrenr = $r['ordrenr'] + 1;
	if (db_fetch_array(db_select("select id from adresser where kontonr = '1'", __FILE__ . " linje " . __LINE__)))
		$kontonr = 1;
	else
		$kontonr = 0;
	db_modify("update ordrer set art='DO',afd='$afd',ordrenr='$ordrenr',kontonr='$kontonr' where id='$id'", __FILE__ . " linje " . __LINE__);
	db_modify("update ordrelinjer set posnr=posnr*-1 where ordre_id='$id'", __FILE__ . " linje " . __LINE__);
	db_modify("update ordrelinjer set posnr=posnr+100 where ordre_id='$id'", __FILE__ . " linje " . __LINE__);
	#print "<BODY onLoad=\"javascript:alert('Tilbud gemt')\">\n";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php\">\n";
}
if (!$id || $id == 0) {
	$r = db_fetch_array(db_select("select box7,box10 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	if ($r['box7'])
		$bord = explode(chr(9), $r['box7']); #20140508
	if ($r['box10'])
		$koekkenprinter = explode(chr(9), $r['box10']); #20140820 + 20140925
	$dd = date("Y-m-d");
	$vis_kassenr = 1;

	if (is_numeric($bordnr) && (count($bord) || $bordnr)) { #20210930
		$qtxt = "select max(id) as id from ordrer where status < '3' and art = 'PO' and nr='$bordnr'";  #20140508
	} else
		$qtxt = "select max(id) as id from ordrer where status < '3' and art = 'PO' and ref = '$brugernavn'";
	$qtxt .= " and ordredate >= '2021-03-01'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if ($r['id'] && $id = $r['id']) {  #20140508 + 20140828 
		$r = db_fetch_array(db_select("select nr from ordrer where id='$id'", __FILE__ . " linje " . __LINE__));
		$bordnr = (int) $r['nr'];
	}
}
#exit;

if ($id) {
	$qtxt = "select * from pos_betalinger where ordre_id = '$id' and betalingstype='!'";
	if (!$godkendt && $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		registrer_betaling($r['id'], $r['ordre_id'], $r['betalingstype'], $r['amount'], $r['valuta'], $r['valutakurs'], $terminal_ip); #ordrefunc
		exit;
	}
}

/*
if ($vis_kassenr) {
	$kasse=trim($kasse);
	$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='1'",__FILE__ . " linje " . __LINE__));
	$kasseantal=$r['box1']*1;
	$afd=explode(chr(9),$r['box3']);
	$tmp=$kasse-1;
	$r = db_fetch_array(db_select("select * from grupper where art = 'AFD' and kodenr='$afd[$tmp]'",__FILE__ . " linje " . __LINE__));
	$afd_navn=$r['beskrivelse'];
	$afd_lager=$r['box1'];
}
*/
if ($ny_bruger && $ny_bruger != $brugernavn || $skift_bruger == 1)
	skift_bruger($ny_bruger, $kode, 1);
if (!isset($momssats))
	$momssats = find_momssats($id, $kasse);
# Overordnet tabel
$tilfravalgNy = str_replace('|', chr(9), if_isset($_GET['tilfravalgNy']));
$delFrTfv = if_isset($_GET['delFrTfv']);
if ($tilfravalgNy && ($delFrTfv || $delFrTfv == '0')) {
	$tfv = explode(chr(9), $tilfravalgNy);
	$tilfravalgNy = NULL;
	for ($x = 0; $x < count($tfv); $x++) {
		if ($x != $delFrTfv)
			$tilfravalgNy .= $tfv[$x] . chr(9);
	}
	$tilfravalgNy = trim($tilfravalgNy, chr(9));
} else
	$tilfravalgNy = if_isset($_POST['tilfravalgNy']); #20220614

print "<form name='pos_ordre' action='pos_ordre.php?id=$id&bundmenu=$bundmenu&sidemenu=$sidemenu&bordnr=$bordnr";
print "&del_bord=$del_bord&tilfravalgNy=" . str_replace(chr(9), '|', $tilfravalgNy) . "' method='post' autocomplete='off'>\n";
print "<table width=\"100%\" height=\"100%\" bordercolor=\"#ffffff\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\"><tbody>\n"; # Tabel 1 ->
# 1 kvadrat.
print "<tr><td valign=\"bottom\"><table width=\"100%\" height=\"100%\" border=\"0\"><tbody>\n"; # Tabel 1.1a -> 
print "<tr><td width=\"100%\" height=\"10%\" valign=\"top\">\n";
# inputfelter til varenr mm. i 1 kvadrat
print "<table width=\"100%\" border=\"0\"><tbody>\n"; # Tabel 1.2 -> 
if ($id && isset($_GET['betaling']) && $_GET['betaling'] == 'ukendt')
	$betaling = 'ukendt';
# if ($id && $betaling) $sum=betaling($id,$momssats,$betaling,$betaling2,$modtaget,$modtaget2, $kasse);
#/*
if ($id && $betaling) {
	include('../debitor/pos_ordre_includes/voucherFunc/voucherPay.php');
	voucherPay($id, $betaling, $modtaget);
	if (isset($_COOKIE['giftcard']) && $_COOKIE['giftcard'] == true) {
		if ($tracelog)
			fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls betaling($id,$momssats,$betaling,$betaling2,$modtaget,$modtaget2, $kasse)\n");
		$sum = betaling($id, $momssats, $betaling, $betaling2, $modtaget, $modtaget2, $kasse);
		$qtxt = "select sum (amount*valutakurs/100) as paid from pos_betalinger where ordre_id='$id'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$leftToPay = $sum - $r['paid'];
		if (afrund($leftToPay, 2) == 0)
			include("../debitor/pos_ordre_includes/exitFunc/settlePOS.php"); #20190510
		if ($svar == 'OK') { #20150213
			transaktion("commit");
			if ($tracelog)
				fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls: pos_txt_print($id,'','','','','')\n");
			pos_txt_print($id, '', '', '', '', '');
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
			exit;
		}
	} else {
		setcookie('giftcard', '', time() - 3600);
		if ($tracelog)
			fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls betaling($id,$momssats,$betaling,$betaling2,$modtaget,$modtaget2, $kasse)\n");
		$sum = betaling($id, $momssats, $betaling, $betaling2, $modtaget, $modtaget2, $kasse);

		#		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
#		exit(0);
	}
} elseif (!$indbetaling) {
	list($varenr_ny, $pris_ny, $status) = explode(chr(9), varescan($id, $momssats, $varenr_ny, $antal_ny, $pris_ny, $beskrivelse_ny, $rabat_ny, $lager_ny));#20210811
} else
	indbetaling($id, $indbetaling, $modtaget, $modtaget2, $betaling);
if (strpos($betaling, onAmount())) {
	if (substr($betaling, 0, 7) == "Kontant" || substr($betaling, 0, 7) == "Cash")
		$betaling = 'Kontant';
	elseif (substr($betaling, 0, 13) == "Betalingskort")
		$betaling = 'Betalingskort';
	else {
		$r = db_fetch_array(db_select("select box5 from grupper where art = 'POS' and kodenr='1' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
		$korttyper = explode(chr(9), $r['box5']);
		for ($x = 0; $x < count($korttyper); $x++) {
			if (strtolower($korttyper[$x]) == str_replace(' på beløb', '', strtolower($betaling))) {
				$betaling = str_replace(' på beløb', '', $betaling);
			} elseif (strtolower($korttyper[$x]) == str_replace(' on amount', '', strtolower($betaling))) {
				$betaling = str_replace(' on amount', '', $betaling);
			}
		}
	}
	if (!$indbetaling) {
		$tmp = 0;
		$q = db_select("select * from pos_betalinger where ordre_id = '$id' order by id", __FILE__ . " linje " . __LINE__); #20160121
		while ($r = db_fetch_array($q)) {
			$tmp += $r['amount'];
		}
		$modtaget = $sum - $tmp;
	}
	$svar = afslut($id, $betaling, NULL, $modtaget, 0, NULL, NULL, NULL, NULL, $receipt_id, __LINE__);
	if ($svar)
		print "<BODY onLoad=\"javascript:alert('$svar')\">\n";
	else
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
}
if ($varenr_ny == 'fejl')
	fejl($id, "$status");

print "</tbody></table></td></tr>\n"; # <- Tabel 1.2
print "</tbody></table></td>\n"; # <- Tabel 1.1a
print "<td valign=\"top\" align=\"center\">\n";
$omv_menu = get_settings_value("omv_menu", "POS", "off", null, $kasse);
function primary_menu() {
	print "\n<!-- Function primary_menu (start)-->\n";
	global $status, $fokus, $bundmenu, $sidemenu, $afslut, $kasse, $id, $vare_id, $afd, $returside;

	# If there is not share table
	if ($afslut || $status >= 3 || $fokus == 'modtaget') {
		if ($status >= 3)
			$qtxt = "select kodenr from grupper where art = 'POSBUT' and kode='H' and box6='B'";
		else
			$qtxt = "select kodenr from grupper where art = 'POSBUT' and kode='H' and box6='A'";
		if ($returside == 'kassespor.php') menubuttons($id, $r['kodenr'], $vare_id, 'H');
		elseif ($afd)
			$qtxt .= " and (box12='$afd' or box12='') order by box12 desc limit 1";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
			menubuttons($id, $r['kodenr'], $vare_id, 'H');
		else {
			print "<table border=\"0\" CELLPADDING=\"2px\" CELLSPACING=\"2px\"><tbody>"; # Tabel 1.1b ->
			tastatur($kasse, $status, 'H');
			print "</tbody></table>"; # <- Tabel 1.1b 
		}
	} elseif ($sidemenu) {
		menubuttons($id, $sidemenu, $vare_id, 'H');
	} else {
		if ($afd) db_modify("update grupper set box12= '' where art='POSBUT' and box12 is NULL", __FILE__ . " linje " . __LINE__); #fjernes 20170104
		$qtxt = "select kodenr from grupper where art = 'POSBUT' and kode='H' and (box6='on' or box6='H')";
		if ($afd) $qtxt .= " and (box12='$afd' or box12='') order by box12 desc limit 1";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			menubuttons($id, $r['kodenr'], $vare_id, 'H');
		} else {
			print "<table border=\"0\" CELLPADDING=\"2px\" CELLSPACING=\"2px\"><tbody>"; # Tabel 1.1b ->
			tastatur($kasse, $status);
			print "</tbody></table>"; # <- Tabel 1.1b 
		}
	}
	print "</td></tr>\n";
	print "<tr><td colspan=\"2\" valign=\"middle\" align=\"center\">"; #<table border=\"2\"><tbody>\n";
	print "\n<!-- Function primary_menu (slut)-->\n";
}
function secondary_menu() {
	print "\n<!-- Function secondary_menu (start)-->\n";
	global $status, $fokus, $bundmenu, $id, $vare_id, $afd;

	if ($status < 3 && $fokus != 'modtaget' && $fokus != 'modtaget2') {
		if ($bundmenu)
			menubuttons($id, $bundmenu, $vare_id, 'B');
		else {
			$qtxt = "select kodenr from grupper where art = 'POSBUT' and kode='B' and (box6='on' or box6='H')";
			if ($afd)
				$qtxt .= " and (box12='$afd' or box12='') order by box12 desc limit 1";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
				menubuttons($id, $r['kodenr'], $vare_id, 'B');
			else
				menubuttons($id, NULL, $vare_id, 'B');
		}
	}
	print "\n<!-- Function secondary_menu (slut)-->\n";
}
# Primarry menu gets swapped for mobile users
$omv_menu!="on" ? primary_menu() : secondary_menu();
print "</td></tr>\n";
print "<tr><td colspan=\"2\" valign=\"middle\" align=\"center\">"; #<table border=\"2\"><tbody>\n";
$omv_menu!="on" ? secondary_menu() : primary_menu();

#print "</td></tbody></table></td></tr>\n";
print "</td></tbody></table></td></tr>\n";
print "</FORM>\n";
ob_end_flush();
if ($delayLoad == true) {
	sleep(3);
}

function betaling($id, $momssats, $betaling, $betaling2, $modtaget, $modtaget2, $kasse)
{
	print "\n<!-- Function betaling (start)-->\n";
	global $betalingsbet;
	global $fokus;
	global $ifs;
	global $kontonr;
	global $db, $difkto;
	global $delbetaling;
	global $pfs, $regnaar;
	global $sprog_id;
	global $vis_saet;
	global $tracelog;
	global $sprog_id;

	$retur = NULL;

	/*
		$betvaluta=if_isset($_POST['betvaluta']);
		$betvalsum=if_isset($_POST['betvalsum']);
		$prevalkurs=if_isset($_POST['betvalkurs']);	
		if (!$prevalkurs) $prevalkurs=100;
		
		if ($betvaluta && $betvaluta!='DKK') {
			$dd=date("Y-m-d");
			$qtxt="select kodenr from grupper where art = 'VK' and box1 = '$betvaluta'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$qtxt="select kurs from valuta where valdate <='$dd' and gruppe='$r[kodenr]' order by valdate desc limit 1";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$betvalkurs=$r['kurs'];
		} else $betvalkurs=100;
		if ($betvalkurs!=$prevalkurs) $modtaget=NULL;
		elseif ($betvalkurs!=100) $modtaget*=$betvalkurs/100; 
	*/
	list($modtaget, $valmodt, $betvaluta, $betvalkurs) = explode(chr(9), posvaluta($modtaget));

	$fokus = "modtaget";
	if ($id) {
		$qtxt = "select * from ordrer where id = '$id'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$konto_id = (int) $r['konto_id'];
		$kontonr = $r['kontonr'];
		$firmanavn = $r['firmanavn'];
		$addr1 = $r['addr1'];
		$post_by = $r['postnr'] . " " . $r['bynavn'];
		$kundeordnr = $r['kundeordnr'];
		$status = $r['status'];
		$betalingsbet = $r['betalingsbet'];
		$sum = $r['sum'];
		$moms = $r['moms'];
		$ref = $r['ref'];
		#		if ($betalingsbet =='Kontant') $betalingsbet=NULL; 20160928
		if ($status > 2) { #20150324
			$alert1 = findtekst(1865, $sprog_id);
			alert("$alert1 $ref");
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
		}
		if ($konto_id) {
			print "<tr><td><b>$kontonr</b>\n";
			if ($kundeordnr)
				print "&nbsp;&nbsp;&nbsp; " . findtekst('2129|Rekv. nr.', $sprog_id) . ": $kundeordnr";
			print "</td></tr>\n";
			print "<tr><td colspan=\"2\"><b>D $firmanavn</b></td></tr>\n";
			if (!$vis_saet) {
				if ($betalingsbet != 'Kontant') { #20160928
					list($betalingsbet, $kreditmax, $saldo) = explode(";", find_saldo($konto_id, $sum, $moms));
				}
				if ($betalingsbet == 'Kontant')
					print "<tr><td colspan=\"2\"><b>" . findtekst(1866, $sprog_id) . "</b></td>\n";
			}
		}
		(isset($betaling)) ? $show = 0 : $show = 1;
		if ($sum + $moms != $modtaget || $sum == 0)
			$show = 1;
		if ($show) {
			print "<tr><td><table border=\"0\" width=\"100%\"><tbody>\n";
			#		print "<tr><td colspan='6'>Gavenr $vouherNumber</td></tr>";
			print "<tr><td>" . findtekst('320|Varenummer', $sprog_id) . "</td><td align=\"right\">" . findtekst('916|Antal', $sprog_id) . "</td><td>" . findtekst('967|Varenavn', $sprog_id) . "</td><td align=\"right\">" . findtekst('915|Pris', $sprog_id) . "</td><td align=\"right\">Sum</td></tr>\n";
			print "<tr><td colspan=\"6\"><hr></td></tr>\n";
		}
		if ($tracelog)
			fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls vis_pos_linjer($id,$momssats,$status,NULL,$show)\n");
		list($sum, $rest, $afrundet, $kostsum) = explode(chr(32), vis_pos_linjer($id, $momssats, $status, NULL, $show));
		if ($betalingsbet != 'Kontant')
			$modtaget = $sum;
		elseif ($modtaget == '' && $rest != $sum)
			$modtaget = $rest;
		if ($modtaget && $afrundet)
			$retur = $modtaget - $afrundet;
		elseif ($modtaget)
			$retur = $modtaget - $rest;
		if ($betaling != 'ukendt' && $sum <> 0 && $sum + $moms == $modtaget && $retur == $modtaget - $rest) {
			return $sum;
			exit;
		}
	}
	print "<input type=\"hidden\" name=\"fokus\" value=\"$fokus\" />\n";
	print "<input type=\"hidden\" name=\"betaling\" value=\"$betaling\" />\n";
	print "<input type=\"hidden\" name=\"sum\" value=\"$sum\" />\n";
	print "<input type=\"hidden\" name=\"delbetaling\" value=\"$delbetaling\" />\n";
	print "<input type=\"hidden\" name=\"rest\" value=\"$rest\" />\n";

	#	elseif ($prevalkurs && $prevalkurs!=$betvalkurs) $modtaget*=$sum*100/$betvalkurs; 
	if ($valmodt)
		$tmp = $valmodt;
	elseif ($modtaget && $betvalkurs != '100')
		$tmp = $modtaget * 100 / $betvalkurs;
	elseif ($modtaget)
		$tmp = $modtaget;
	else
		$tmp = "";

	if ($betalingsbet && $betalingsbet != 'Kontant') {
		if ($tmp)
			print "<input type=\"hidden\" name=\"modtaget\" value=\"" . dkdecimal($tmp, 2) . "\">\n";
		else
			print "<input type=\"hidden\" name=\"modtaget\" value=\"\">\n";
	} elseif (substr($betaling, 0, 9) != 'Kontant p') {
		if ($betvaluta != 'DKK') {
			$betaling = 'Kontant';
		}
		if ($delbetaling) {
			$tmp = $rest / $delbetaling;
			print "<tr><td>".findtekst('2428|Delbetaling)', $sprog_id)." 1/$delbetaling ".strtolower(findtekst('638|Af)', $sprog_id))." $rest</td><td colspan= \"4\" align=\"right\">\n";
		} else {
			print "<tr><td>$betaling $betvaluta";
			/*
						if ( $betaling=='voucher' ) { 
							print "</td>";
							print "<td colspan=\"3\">Gavekortnummer: <input class=\"inputbox\" type=\"text\" style=\"width:$w;font-size:$ifs;text-align:right\" name = \"recievedVoucherNumber\" value=\"$recievedVoucherNumber\"></td>\n";
							print "<td align=\"right\">\n";
						} else 
			*/
			print "</td><td colspan=\"4\" align=\"right\">\n";
		}
		if ($tmp)
			$tmp = dkdecimal($tmp, 2);
		$betvalsum = $sum * 100 / $betvalkurs;
		print "<input type=\"hidden\" name=\"delbetaling\" value=\"on\">";
		print "<input type=\"hidden\" name=\"betvalkurs\" value=\"$betvalkurs\">";
		print "<input type=\"hidden\" name=\"afslut\" value=\"on\">";
		$w = 100 / 10 * $pfs;
		print "<input class=\"inputbox\" type=\"text\" style=\"width:$w;font-size:$ifs;text-align:right\" name = \"modtaget\" placeholder=\"" . dkdecimal($rest, 2) . "\" value=\"$tmp\"></td></tr>\n";
		if ($betaling != "ukendt" && ($retur < 0 || $modtaget2)) {
			$color = "color: rgb(255, 0, 0);";
			if ($modtaget2)
				$tmp = dkdecimal($modtaget2, 2);
			else
				$tmp = "";
			#			if (!$betaling2) $betaling2="ukendt";
#			$fokus="modtaget2";
#			$retur=$retur+$modtaget2;
#			print "<tr><td>$betaling2</td><td colspan= \"4\" align=\"right\"><input class=\"inputbox\" type=\"text\" style=\"width:100px\" style=\"text-align:right\" name = \"modtaget2\" value=\"$tmp\"></td></tr>\n";
		} else {
			$color = "color: rgb(0, 0, 0);";
		}
		#		$retur=pos_afrund($retur);
		if ($betvalkurs && $betvalkurs != 100) {
			#			$retur=$modtaget*100/$betvalkurs-$betvalsum;
#			$retur=pos_afrund($retur*$betvalkurs/100);
		}
		#		if ($retur >= 0) {
		print "<tr><td>".findtekst('2296|Retur', $sprog_id);
		if ($betvaluta != 'DKK')
			print " (DKK)<br>";
		$retur = pos_afrund($retur, $difkto, '');
		print "</td><td colspan= \"4\" align=\"right\"><span style=\"$color\">" . dkdecimal($retur, 2) . "</span></td></tr>\n";
		#		}
	}
	print "<tr><td colspan=\"6\"><input STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
	print "</tbody></table>\n";
	countPriceCorrection($id, $sum, $kasse);
	print "\n<!-- Function betaling (slut)-->\n";
	return ($sum);
} #endfunc betaling

function skift_bruger($ny_bruger, $kode, $pwtjek)
{
	global $brugernavn;
	global $s_id;
	global $db;
	global $ifs;
	global $sprog_id;

	if (!$ny_bruger && !$kode) {
		$x = 0;
		$q = db_select("select brugernavn from brugere order by brugernavn", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$loginnavn[$x] = $r['brugernavn'];
			$x++;
		}
		print "<table><tbody>\n";
		print "<tr><td colspan=\"2\" align=\"center\">\n";
		print "<form name=pos_ordre action=\"pos_ordre.php\" method=\"post\" autocomplete=\"off\">\n";
		print "<big><b>" . findtekst('2251|Vælg brugernavn og angiv adgangskode', $sprog_id) . "</b></big>\n";
		print "</td></tr>\n";
		$stil = find_stil('select', 2, 0);
		print "<tr><td><big>" . findtekst('2252|Ekspedient', $sprog_id) . ": </big><select class=\"inputbox\" style=\"width:100px;font-size:$ifs;\" NAME=\"ny_bruger\">\n";
		print "<option>$brugernavn</option>\n";
		for ($x = 0; $x < count($loginnavn); $x++) {
			if ($loginnavn[$x] != $brugernavn)
				print "<option>$loginnavn[$x]</option>\n";
		}
		print "</option></td>\n";
		print "<td><input class=\"inputbox\" type=\"password\" style=\"width:100px;font-size:$ifs;\" name=\"kode\" value=\"\"></td></tr>\n";
		print "<tr><td colspan=\"2\" align=center>\n";
		$stil = find_stil('knap', 3, 0);
		print "<input type=\"submit\" style=\"width:400px;font-size:$ifs;\" name=\"skift_password\" value=\"OK\">\n";
		print "</form>\n";
		print "</td></tr>\n";
		print "</tbody></table>\n";
	} elseif ($pwtjek == 2) {
		$id = if_isset($_GET['id']);
		$menu_id = if_isset($_GET['menu_id']);
		$qtxt = "select brugernavn from brugere where brugernavn = '$ny_bruger' or lower(brugernavn) = '" . strtolower($ny_bruger) . "' or upper(brugernavn) = '" . strtoupper($ny_bruger) . "'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($brugernavn = $r['brugernavn']) {
			if ($id) { #20170417
				$qtxt = "select status from ordrer where id = '$id'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				if ($r['status'] >= 3)
					$id = 0;
			}
			include("../includes/connect.php");
			$qtxt = "update online set brugernavn='" . db_escape_string($brugernavn) . "' where session_id='$s_id' and db = '$db'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id&menu_id=$menu_id\">\n";
		} else {
			$alert1 = findtekst(1867, $sprog_id);
			print "<BODY onLoad=\"javascript:alert('$ny_bruger $alert1')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id&menu_id=$menu_id\">\n";
		}
	} else {
		$r = db_fetch_array(db_select("select id from brugere where brugernavn ='$ny_bruger'", __FILE__ . " linje " . __LINE__));
		$pw1 = md5($kode);
		$pw2 = saldikrypt($r['id'], $kode);
		if (db_fetch_array(db_select("select id from brugere where brugernavn ='$ny_bruger' and (kode = '$pw1' or kode ='$pw2')", __FILE__ . " linje " . __LINE__))) {
			include("../includes/connect.php");
			db_modify("update online set brugernavn='$ny_bruger' where session_id='$s_id' and db = '$db'", __FILE__ . " linje " . __LINE__);
			$brugernavn = $ny_bruger;
			print "<input type=\"hidden\" name=\"brugernavn\" value=\"$brugernavn\">\n";
			include("../includes/online.php");
		} else {
			$alert = findtekst(1868, $sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alert')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?skift_bruger=1\">\n";
			exit;
		}
	}
}

function find_bon($bon)
{
	global $db;
	global $sprog_id;

	if ($bon) {
		$bon = strtoupper($bon);
		if ($bon == 'S') {
			$kasse = stripslashes($_COOKIE['saldi_pos']);
			$faktnr = 0;
			$q = db_select("select id,fakturanr from ordrer where felt_5='$kasse' and art = 'PO' and status >= '3'", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if ($r['fakturanr'] > $faktnr) {
					$faktnr = $r['fakturanr'];
					$id = (int) $r['id'];
				}
			}
			if (!$id) {
				$r = db_fetch_array(db_select("select max(id) as id from ordrer where art = 'PO' and status >= '3'", __FILE__ . " linje " . __LINE__));
				$id = (int) $r['id'];
			}
		} elseif ($bon) {
			$r = db_fetch_array(db_select("select id,nr from ordrer where fakturanr = '" . db_escape_string($bon) . "' and art = 'PO'", __FILE__ . " linje " . __LINE__));
			$id = $r['id'];
		}
		$_SESSION['receiptId'] = $bon;
		return ($id);
		exit;
	} else {
		print "<table><tbody>\n";
		print "<tr><td colspan=\"2\" alingn=\"center\">\n";
		print "<form name=find_bon action=\"pos_ordre.php\" method=\"post\" autocomplete=\"off\">\n";
		print "<big><b>" . findtekst('2249|Skriv bonnummer eller S for sidste bon', $sprog_id) . ":</b></big>\n";
		print "</td></tr>\n";
		#	if ($status>=3 && !$bon && $id) { #20140708
#		$r=db_fetch_array($q=db_select("select fakturanr from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
#		$tmp=$r['fakturanr'];
#	}
#	else $tmp=$bon;
		$stil = find_stil('select', 2, 0);
		print "<td><span title=\"" . findtekst(1861, $sprog_id) . "\"><big>" . findtekst('2250|Bon', $sprog_id) . ": </big><input class=\"inputbox\" style=\"width:100px;font-size:$ifs;\" type=\"text\" name=\"bon\" size=\"6\" value=\"$tmp\"></span>\n";
		$stil = find_stil('knap', 1, 0);
		print "<input type=\"submit\" $stil name=\"find_bon\" value=\"OK\">\n";
		print "</form>\n";
		print "</td></tr>\n";
		print "</tbody></table>\n";
		print "<script language=\"javascript\">\n";
		print "document.find_bon.bon.focus();";
		print "</script>\n";
		exit;
	}
}

function opret_posordre($konto_id, $kasse)
{
	global $bordnr, $bruger_id;
	$brugernavn;
	global $db;
	global $firmanavn;
	global $kontonr;
	global $momssats;
	global $notes, $regnaar;
	global $varenr_ny;

	if (!$kasse)
		$kasse = find_kasse(0);
	if (!$kasse)
		$kasse = 1;

	if (file_exists("../temp/$db/$kasse.tid") && file_get_contents("../temp/$db/$kasse.tid") >= date("U")) { #20181024
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php\">\n";
		exit;
	} else
		file_put_contents("../temp/$db/$kasse.tid", date("U") + 1);

	hent_shop_ordrer('', '');

	$r = db_fetch_array(db_select("select box4 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	$x = $kasse - 1;
	$tmp = explode(chr(9), $r['box4']);
	$terminal_ip = trim($tmp[$x]);
	if (isset($_COOKIE['salditerm']) && $_COOKIE['salditerm'])
		$terminal_ip = $_COOKIE['salditerm'];
	if ($terminal_ip == 'box' || $terminal_ip == 'saldibox') {
		$filnavn = "http://saldi.dk/kasse/" . $_SERVER['REMOTE_ADDR'] . ".ip";
		if ($fp = fopen($filnavn, 'r')) {
			$terminal_ip = trim(fgets($fp));
			fclose($fp);
		}
	}
	if ($terminal_ip) {
		setcookie("salditerm", $terminal_ip, time() + 3600, '/');
	}
	if ($kasse && !$_GET['bordnr'] && !isset($_GET['flyt_til'])) {
		$qtxt = "select box13 from grupper where art='POS' and kodenr = '2' and fiscal_year = '$regnaar'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r['box13']) {
			$tmparray = explode(chr(9), $r['box13']);
			$bordnr = $tmparray[$kasse - 1];
		}
	}

	if (!$bordnr && $bordnr != '0')
		$bordnr = if_isset($_GET['bordnr']);
	if (!$bordnr && $bordnr != '0')
		$bordnr = if_isset($_GET['flyt_til']);
	if (!$varenr_ny && ($bordnr || $bordnr == '0')) {
		$qtxt = "select id from ordrer where art='PO' and nr = '$bordnr' and felt_5='$kasse' and status < '3'";
		if ($r = db_fetch_array($q = db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$r[id]\">\n";
		}
	}
	if ($r = db_fetch_array($q = db_select("select ordrenr from ordrer where art='PO' order by ordrenr desc", __FILE__ . " linje " . __LINE__))) {
		$ordrenr = $r['ordrenr'] + 1;
	} else
		$ordrenr = 1;
	$ordredate = date("Y-m-d");
	$tidspkt = date("U");
	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	#	$id=$r['id'];
#	$kasseantal=$r['box1']*1;
	$moms = explode(chr(9), $r['box7']);
	$x = $kasse - 1;
	if ($moms[$x]) {
		$r = db_fetch_array(db_select("select * from grupper where art = 'SM' and kodenr = '$moms[$x]'", __FILE__ . " linje " . __LINE__));
		$momssats = $r['box2'];
	} else
		$momssats = '0';
	if (!is_numeric($bordnr))
		$bordnr = 0;
	if (!is_numeric($kontonr))
		$kontonr = 0;
	# 20141210 Tilføjet felt_5
	
	$qtxt = "insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,";
	$qtxt .= "betalingsdage,betalingsbet,cvrnr,ean,institution,email,mail_fakt,notes,art,";
	$qtxt .= "ordredate,momssats,hvem,tidspkt,ref,valuta,sprog,kontakt,pbs,status,nr,felt_5)";
	$qtxt .= "	values ";
	$qtxt .= "('$ordrenr','0','$kontonr','$firmanavn','','','','','','0','Kontant','','','','','','$notes','PO',";
	$qtxt .= "'$ordredate','$momssats','$brugernavn','$tidspkt','$brugernavn','DKK','','','','0','$bordnr','$kasse')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "select id from ordrer where hvem='$brugernavn' and tidspkt='$tidspkt' order by id desc";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$id = $r['id'];
	$qtxt = "insert into pos_events (ev_type,ev_time,cash_register_id,employee_id,order_id,file,line) ";
	$qtxt .= "values ";
	$qtxt .= "('13003','" . date('U') . "','$kasse','$bruger_id','$id','" . __FILE__ . "','" . __LINE__ . "')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);

	return ($id);
} # endfunc opret_posordre()

function find_saldo($konto_id, $sum, $moms)
{ #20160928
	global $db;

	$konto_id *= 1;
	$qtxt = "select kreditmax,betalingsbet from adresser where id = '$konto_id'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$kreditmax = (float) $r['kreditmax'];
	$betalingsbet = $r['betalingsbet'];
	$qtxt = "select sum(amount) as saldo from openpost where konto_id='$konto_id' and udlignet !='1'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$saldo = $r['saldo'];
	if ($betalingsbet == 'Forud' && $saldo - $sum + $moms > 0)
		$betalingsbet = 'Kontant';
	elseif ($kreditmax && $kreditmax - $saldo < $sum + $moms)
		$betalingsbet = 'Kontant';
	return ("$betalingsbet;$kreditmax;$saldo");
}

function indbetaling($id, $indbetaling, $modtaget, $modtaget2, $betaling)
{

	global $fokus;
	global $ifs;
	global $sprog_id,$status;
	if ($fokus == 'indbetaling')
		$fokus = 'modtaget';
	else
		$fokus = 'indbetaling';
	$saldo = 0;
	$r = db_fetch_array(db_select("select * from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__));
	$konto_id = $r['konto_id'];
	$status = $r['status'];
	$kontonr = $r['kontonr'];
	$firmanavn = $r['firmanavn'];
	$addr1 = $r['addr1'];
	$addr2 = $r['addr2'];
	$postnr_by = $r['postnr'] . " " . $r['bynavn'];
	if ($status < 3) {
		$q = db_select("select * from openpost where konto_id = '$konto_id'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$saldo = $saldo + $r['amount'];
		}
		list($a, $b) = explode(",", $indbetaling);
		if (!$indbetaling || !is_numeric($indbetaling)) {
			$indbetaling = $saldo;
			$modtaget = '';
			$modtaget2 = '';
		}
		$modtaget = (float) $modtaget;
		$modtaget2 = (float) $modtaget2;
		$indbetaling = (float) $indbetaling;
		if ($modtaget + $modtaget2 - $indbetaling > 0)
			$retur = dkdecimal($modtaget + $modtaget2 - $indbetaling, 2);
		else
			$retur = "0,00";
	} else {
		$saldo = $r['felt_3'];
		$indbetaling = $r['sum'];
		$retur = dkdecimal($r['felt_2'] - $indbetaling, 2);
	}
	$ny_saldo = dkdecimal($saldo - $indbetaling, 2);
	$saldo = dkdecimal($saldo);
	if ($indbetaling != 0)
		$indbetaling = dkdecimal($indbetaling, 2);
	else
		$indbetaling = NULL;
	if ($modtaget)
		$fokus = "modtaget";
	if ($modtaget != 0)
		$modtaget = dkdecimal($modtaget, 2);
	else
		$modtaget = NULL;
	if ($modtaget2) {
		$modtaget2 = dkdecimal($modtaget2, 2);
		$fokus = "modtaget";
	}
	($indbetaling < 0) ? $color = "#FF0000" : $color = "#FFFFFF";
	print "<input type=\"hidden\" name=\"id\" value=\"$id\">\n";
	print "<input type=\"hidden\" name=\"fokus\" value=\"$fokus\">\n";
	print "<input type=\"hidden\" name=\"sidemenu\" value=\"1\">\n";
	print "<tr><td><b>$kontonr</b></td></tr>\n";
	print "<tr><td><b>$firmanavn</b></td></tr>\n";
	print "<tr><td><b>$addr1</b></td></tr>\n";
	print "<tr><td><b>$addr2</b></td></tr>\n";
	print "<tr><td><b>$postnr_by</b></td></tr>\n";
	print "<tr><td colspan=2 width=400px><hr></td></tr>\n";
	#	while (strlen($saldo) < 10) $saldo=" ".$saldo;
	$txt1073 = findtekst('1073|Saldo', $sprog_id);
	print "<tr><td>$txt1073</td><td align=\"right\">$saldo</td></tr>\n";
	print "<tr><td colspan=\"2\"><br></td></tr>";
	print "<tr><td><b>".findtekst('2567|Indbetaling', $sprog_id)."</b>";
	if ($status < 3) {
		$txt2568 = findtekst('2568|Det beløb, der skal indsættes på kontoen', $sprog_id);
		print " - $txt2568!</td><td rowspan=\"2\" align=\"right\">";
		print "<input class=\"inputbox\" type=\"text\" style=\"width:80px;text-align:right;font-size:$ifs;background-color:$color\" ";
		print "name=\"indbetaling\" value=\"$indbetaling\"></td></tr>\n";
		$txt2569 = findtekst('2569|Ved udbetaling skal beløbet være negativt', $sprog_id);
		print "<td> $txt2569!</td></tr>";
	} else {
		print "</td><td align=\"right\">$indbetaling</td></tr>\n";
	}
	if ($status < 3) {
		print "<tr><td colspan=\"2\"><br></td></tr>";
		$txt1265 = findtekst('1265|Betalt', $sprog_id);
		$txt2570 = findtekst('2570|Det beløb der betales f.eks. hvis der betales', $sprog_id);
		$txt2571 = findtekst('2571|med kort og kunden samtidig vil hæve kontant', $sprog_id);
		print "<tr><td><b>$txt1265</b> - $txt2570</td></tr><tr><td>$txt2571.</td>";
		print "<td rowspan=\"3\" valign=\"top\" align=\"right\">";
		if (!$modtaget && $indbetaling && $fokus == 'modtaget') {
			$placeholder = "placeholder=\"$betalt\"";
		} else
			$placeholder = NULL;
		print "<input class=\"inputbox\" $placeholder type=\"text\" style=\"width:80px;text-align:right\" name=\"modtaget\" value=\"$modtaget\"></td></tr>\n";
		$txt2572 = findtekst('2572|Feltet kan efterlades tomt for ind-/udbetaling på beløb', $sprog_id);
		$txt2573 = findtekst('2573|Ved udbetaling <b>skal</b> feltet efterlades tomt', $sprog_id);
		print "<tr><td>$txt2572.</td></tr>";
		print "<tr><td>$txt2573!</td></tr>";
	} else
		print "<tr><td>Betalt</td><td align=\"right\">$modtaget</td></tr>\n";
	print "<tr><td colspan=\"2\"><br></td></tr>";
	print "<tr><td>".findtekst('2298|Ny saldo', $sprog_id)."</td><td align=\"right\">$ny_saldo</td></tr>\n";
	print "<tr><td>".findtekst('2296|Retur', $sprog_id)."</td><td align=\"right\">$retur</td></tr>\n";
	print "<td colspan=\"6\"><input STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
}




function opdater_konto($konto_id, $kontonr, $id)
{
	#Opdaterer kontoinformation på ordren
	global $db, $kasse;
	global $kundeordnr;

	if (!$id || $id == 0)
		$id = opret_posordre(0, $kasse);
	$konto_id *= 1;
	$kontonr *= 1;
	$r = db_fetch_array(db_select("select status from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__));
	$status = $r['status'];
	if ($status < 3) {
		if ($konto_id)
			$r = db_fetch_array(db_select("select * from adresser where id = '$konto_id'", __FILE__ . " linje " . __LINE__));
		else
			$r = db_fetch_array(db_select("select * from adresser where kontonr = '$kontonr' and art = 'D'", __FILE__ . " linje " . __LINE__));
		$konto_id = $r['id'];
		if ($r['lukket']) {
			$betalingsbet = 'Kontant';
			$betalingsdage = '0';
		} else {
			($r['betalingsbet']) ? $betalingsbet = $r['betalingsbet'] : $betalingsbet = 'Kontant';
			$betalingsdage = (int) $r['betalingsdage'];
		}
		$konto_id *= 1;
		$qtxt = "update ordrer set konto_id='$konto_id', kontonr='$r[kontonr]',firmanavn='" . db_escape_string($r['firmanavn']) . "',";
		$qtxt .= "addr1='" . db_escape_string($r['addr1']) . "',addr2='" . db_escape_string($r['addr2']) . "',postnr='" . db_escape_string($r['postnr']) . "',";
		$qtxt .= "bynavn='" . db_escape_string($r['bynavn']) . "',land='" . db_escape_string($r['land']) . "',";
		$qtxt .= "betalingsdage='$betalingsdage',betalingsbet='$betalingsbet',cvrnr='" . db_escape_string($r['cvrnr']) . "',";
		$qtxt .= "ean='" . db_escape_string($r['ean']) . "',institution='" . db_escape_string($r['institution']) . "',";
		$qtxt .= "email='" . db_escape_string($r['email']) . "',kontakt='" . db_escape_string($r['kontakt']) . "',art='PO',valuta='DKK',valutakurs='100' ";
		$qtxt .= "where id = '$id'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	return ($id);
} # endfunc opdater_konto()


function find_kasse($kasse)
{
	global $afd, $db, $id, $regnaar, $sprog_id;

	$id *= 1;

	if ($kasse != "?" && isset($_COOKIE['saldi_pos'])) {
		$kasse = stripslashes($_COOKIE['saldi_pos']);
		$qtxt = "select * from grupper where art = 'POS' and kodenr='1' and fiscal_year = '$regnaar'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($kasse > $r['box1'])
			$kasse = '?';
		else
			return ($kasse);
	}
	if (!$kasse || $kasse == "?") {
		print "<form name=pos_ordre action=\"pos_ordre.php?kasse=opdat&del_bord=$del_bord&id=$id\" method=\"post\" autocomplete=\"off\">\n";
		$qtxt = "select * from grupper where art = 'POS' and kodenr='1' and fiscal_year = '$regnaar'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$kasseantal = (int) $r['box1'];
		$afd = explode(chr(9), $r['box3']);
		if ($id) {
			$r = db_fetch_array(db_select("select felt_5,afd from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__));
			$nuv_kasse = $r['felt_5'];
			$nuv_afd = $r['afd'];
		} elseif (isset($_COOKIE['saldi_pos'])) {
			$nuv_kasse = stripslashes($_COOKIE['saldi_pos']);
		}
		if (!$nuv_kasse)
			$nuv_kasse = 1;
		#		(isset($_COOKIE['saldi_pfs']))?$pfs=$_COOKIE['saldi_pfs']:$pfs=10;

		$afd_nr = $afd_navn = array();
		if ($kasseantal) {
			$x = 0;
			$q = db_select("select * from grupper where art = 'AFD' order by kodenr", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$afd_nr[$x] = $r['kodenr'];
				$afd_navn[$x] = $r['beskrivelse'];
				$x++;
			}
		}
		if (!$afd[0])
			$afd[0] = 1;

		$stil = find_stil('select', 2, 1);
		print "<center><table><tbody>";
#		print "<tr><td title='".findtekst(766, $sprog_id)."'>".findtekst(765, $sprog_id)."</td>";
#		print "<td><input class='inputbox' type='text' style='text-align:right;font-size:$ifs;width:25px' name='pfs' value='$pfs'></td></tr>";
		if ($db == 'pos_73') {
			for ($x = 0; $x < count($afd); $x++) {
				$kasse = $x + 1;
				print "<tr><td><input type = 'submit' style = 'width:200px;height:50px;' name = 'kasse' value='$kasse'></td></tr>";
			}
		} else {
			print "<tr><td>" . findtekst('2253|Vælg kasse', $sprog_id) . "</td><td><SELECT $stil NAME=\"kasse\">\n";
			for ($x = 0; $x < count($afd); $x++) {
				$kasse = $x + 1;
				if (!count($afd_nr) && $kasse == $nuv_kasse)
					print "<option value=\"$kasse\">$kasse</option>\n";
				for ($y = 0; $y < count($afd_nr); $y++) {
					if ($kasse == $nuv_kasse && $afd[$x] == $afd_nr[$y])
						print "<option value=\"$kasse\">$kasse: $afd_navn[$y]</option>\n";
				}
			}
			for ($x = 0; $x < count($afd); $x++) {
				$kasse = $x + 1;
				if (!count($afd_nr) && $kasse != $nuv_kasse)
					print "<option value=\"$kasse\">$kasse: $afd_navn[$y]</option>\n";
				for ($y = 0; $y < count($afd_nr); $y++) {
					if ($kasse != $nuv_kasse && $afd[$x] == $afd_nr[$y])
						print "<option value=\"$kasse\">$kasse: $afd_navn[$y]</option>\n";
				}
			}
			print "</SELECT></td></tr>\n";
		}
		/*
		  if (!$kasse || $kasse=="?") {
				print "<form name=pos_ordre action=\"pos_ordre.php?kasse=opdat&del_bord=$del_bord\" method=\"post\" autocomplete=\"off\">\n";
				$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='1'",__FILE__ . " linje " . __LINE__));
				$kasseantal=$r['box1']*1;
				$afd=explode(chr(9),$r['box3']);
				if ($kasseantal) {
					$x=0;
					$q = db_select("select * from grupper where art = 'AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$afdnr[$x]=$r['kodenr'];
						$afdnavn[$x]=$r['beskrivelse'];
						$x++;
					}
				}
				if ($id) {
					$r=db_fetch_array(db_select("select felt_5,afd from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
					$nuv_kasse=$r['felt_5'];
					$nuv_afd=$r['afd'];
				} elseif (isset($_COOKIE['saldi_pos'])) {
					$nuv_kasse=stripslashes($_COOKIE['saldi_pos']);
				}
				if (!$nuv_kasse) $nuv_kasse=1;

				$stil=find_stil('select',2,1);
				print "V&aelig;lg kasse<SELECT $stil NAME=\"kasse\">\n";
		#		if (count($afd)) {
					for($y=0;$y<count($afdnr);$y++) {
						if ($nuv_kasse==$afdnr[$y]) $afd_navn=$afdnavn[$y];
					}
					print	"<option value=\"$nuv_kasse\">$nuv_kasse $afd_navn</option>\n";
					for($x=1;$x<=count($afd);$x++) {
						for($y=0;$y<count($afdnr);$y++) {
							if ($afd[$x-1]==$afdnr[$y]) $afd_navn=$afdnavn[$y];
						}
						if ($nuv_kasse!=$afd[$x-1])	print	"<option value=\"$x\">$x $afd_navn</option>\n";
					}
		#		} else {
		#			if ($nuv_kasse) print print	"<option value=\"$nuv_kasse\">$nuv_kasse</option>\n";
		#			for($x=1;$x<=count($kasseantal);$x++) {
		#				if ($nuv_kasse!=$x)	print	"<option value=\"$x\">$x</option>\n";
		#			}
		#		}
				print "</SELECT></td>\n";;
		*/
		$stil = find_stil('knap', 1, 3);
		print "<tr><td colspan='2'><hr></td></tr>\n";
		print "<tr><td colspan='2' align='center'><INPUT TYPE=\"submit\" style=\"width:100%\" NAME=\"submit\" VALUE=\"OK\"></td></tr>\n";
		print "</tbody></table>";
		print "</form>\n";
	}
	exit;
}

function fejl($id, $fejltekst)
{

	alert($fejltekst);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$php_self\">\n";

}

function posbogfor($kasse, $regnstart, $reportNumber)
{
	global $afd;
	global $bruger_id, $brugernavn;
	global $db;
	global $regnaar, $reportNumber;
	global $vis_saet;
	global $tracelog;

	$dd = date("Y-m-d");
	$logtime = date("H:i:s");
	$udtages = if_isset($_POST['udtages']);
	$kassediff = if_isset($_POST['kassediff']);
	$kassediff = afrund($kassediff, 2);
	if ($udtages)
		$udtages = (float) usdecimal($udtages, 2);
	$valuta = if_isset($_POST['valuta'], array());
	$ValutaUdtages = if_isset($_POST['ValutaUdtages']);
	$ValutaKasseDiff = if_isset($_POST['ValutaKasseDiff']);
	$ValutaTilgang = if_isset($_POST['ValutaTilgang']);
	$settleCommission = if_isset($_POST['settleCommission']);
	$createPayList = if_isset($_POST['createPayList']);
	for ($x = 0; $x < count($valuta); $x++) {
		$ValutaUdtages[$x] = (float) usdecimal($ValutaUdtages[$x], 2);
		$ValutaKasseDiff[$x] = (int) $ValutaKasseDiff[$x];
		$ValutaTilgang[$x] = (int) $ValutaTilgang[$x];
	}
	$qtxt = "select var_value from settings where var_name = 'change_cardvalue'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$change_cardvalue = $r['var_value'];

	$qtxt = "select id,var_value from settings where var_name = 'commissionAccountUsed' and var_grp = 'items'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$commissionAccountUsed = $r['var_value'];
	$qtxt = "select id,var_value from settings where var_name = 'commissionAccountNew' and var_grp = 'items'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$commissionAccountNew = $r['var_value'];

	$qtxt = "select id,var_value from settings where var_name = 'customerCommissionAccountNew' and var_grp = 'items'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$customerCommissionAccountNewId = $r['id'];
	$customerCommissionAccountNew = $r['var_value'];

	$qtxt = "select id,var_value from settings where var_name = 'customerCommissionAccountUsed' and var_grp = 'items'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$customerCommissionAccountUsedId = $r['id'];
	$customerCommissionAccountUsed = $r['var_value'];

	$qtxt = "select id,var_value from settings where var_name = 'ownCommissionAccountNew' and var_grp = 'items'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$ownCommissionAccountNewId = $r['id'];
	$ownCommissionAccountNew = $r['var_value'];

	$qtxt = "select id,var_value from settings where var_name = 'ownCommissionAccountUsed' and var_grp = 'items'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$ownCommissionAccountUsedId = $r['id'];
	$ownCommissionAccountUsed = $r['var_value'];

	$r = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'", __FILE__ . " linje " . __LINE__));
	$ansat_id = (int) $r['ansat_id'];

	$kassekonti = explode(chr(9), $r['box2']);
	$kassekonto = $kassekonti[$kasse - 1];
	$afdelinger = explode(chr(9), $r['box3']);
	$afd = (int) $afdelinger[$kasse - 1];

	$r = db_fetch_array(db_select("select box2,box3 from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	$kassekonti = explode(chr(9), $r['box2']);
	$kassekonto = $kassekonti[$kasse - 1];
	$afdelinger = explode(chr(9), $r['box3']);
	$afd = (int) $afdelinger[$kasse - 1];

	# --> 20140709
	$r = db_fetch_array(db_select("select box8,box9 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	$mellemkonti = explode(chr(9), $r['box8']);
	$mellemkonto = $mellemkonti[$kasse - 1];
	$diffkonti = explode(chr(9), $r['box9']);
	$diffkonto = $diffkonti[$kasse - 1];
	# <-- 20140709
	$diffVatAccount = $diffVatRate = 0;

	if ($diffkonto) {
		$qtxt = "select moms from kontoplan where kontonr = '$diffkonto' and regnskabsaar = '$regnaar'";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			if ($tmp = trim($r['moms'])) { # f.eks S3
				$tmp1 = substr($tmp, 0, 1) . 'M'; #f.eks 3
				$tmp2 = substr($tmp, 1); #f.eks 3
				$qtxt = "select box1,box2 from grupper where art = '$tmp1' and kodenr = '$tmp2'";
				$r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				if ($r2['box1'])
					$diffVatAccount = (int) $r2['box1'];
				if ($r2['box2'])
					$diffVatRate = (int) $r2['box2'];
			}
		}
	}
	$x = 0;
	$fakturadate = array();
	$qtxt = "select distinct(fakturadate) as fakturadate from ordrer where felt_5='$kasse' ";
	if ($vis_saet)
		$qtxt .= "and (art = 'PO' or art like 'D%') and status='3' ";
	else
		$qtxt .= "and (konto_id='0' or betalingsbet='Kontant') and art = 'PO' and status='3' ";
	$qtxt .= "and fakturadate >= '$regnstart' order by fakturadate";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['fakturadate']) {
			$fakturadate[$x] = $r['fakturadate'];
			$x++;
		}
	}

	for ($x = 0; $x < count($fakturadate); $x++) {
		$y = 0;
		$betaling[$x] = array();
		$qtxt = "select distinct(pos_betalinger.betalingstype) as betaling from pos_betalinger,ordrer where ";
		$qtxt.= "ordrer.felt_5='$kasse' and ordrer.status='3' and ordrer.fakturadate >= '$regnstart' and ";
		$qtxt.= "ordrer.id=pos_betalinger.ordre_id order by pos_betalinger.betalingstype";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($r['betaling']) {
				$betaling[$x][$y] = $r['betaling'];
				$y++;
			}
		}
	}#xit;	

	$x = 0;
	$k = $kasse - 1;
	for ($x = 0; $x < count($valuta); $x++) {
		$r = db_fetch_array(db_select("select * from grupper where art = 'VK' and box1 = '$valuta[$x]'", __FILE__ . " linje " . __LINE__));
		$tmp = explode(chr(9), $r['box4']);
		$ValutaKonti[$x] = $tmp[$k];
		$tmp = explode(chr(9), $r['box5']);
		$ValutaMlKonti[$x] = $tmp[$k];
		$tmp = explode(chr(9), $r['box6']);
		$ValutaDifKonti[$x] = $tmp[$k];
		$kodenr = $r['kodenr'];
		$r2 = db_fetch_array(db_select("select kurs from valuta where gruppe = '$kodenr' and valdate <= '$dd' order by valdate desc limit 1", __FILE__ . " linje " . __LINE__));
		$valutakurs[$x] = $r2['kurs'];
		$ValutaUdtages[$x] *= $valutakurs[$x] / 100;
		$ValutaKasseDiff[$x] *= $valutakurs[$x] / 100;
		$ValutaTilgang[$x] *= $valutakurs[$x] / 100;
		$ValutaDiffVatAccount[$x] = $ValutaDiffVatRate[$x] = 0;
		if ($ValutaDifKonti[$x]) {
			$qtxt = "select moms from kontoplan where kontonr = '$ValutaDifKonti[$x]' and regnskabsaar = '$regnaar'";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				if ($tmp = trim($r['moms'])) { # f.eks S3
					$tmp = substr($tmp, 1); #f.eks 3
					$qtxt = "select box1,box2 from grupper where art = 'SM' and kodenr = '$tmp'";
					$r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
					if ($r2['box1'])
						$ValutaDiffVatAccount[$x] = (int) $r2['box1'];
					if ($r2['box2'])
						$ValutaDiffVatRate[$x] = (int) $r2['box2'];
				}
			}
		}
	}
	$x = count($valuta);
	$valuta[$x] = 'DKK';
	db_modify("update pos_betalinger set valuta = 'DKK' where valuta is NULL or valuta = ''", __FILE__ . " linje " . __LINE__);

	#20161010
/*
	$k=0;
	$qtxt="select id from adresser where art='K'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$kr_id[$k]=$r['id'];
		$k++;
	}
*/

	$ko_id = NULL; #Salg på konto
	transaktion('begin');
	$qtxt = "insert into pos_events (ev_type,ev_time,cash_register_id,employee_id,order_id,file,line) ";
	$qtxt.= "values ";
	$qtxt.= "('13009','" . date('U') . "','$kasse','$bruger_id','0','" . __FILE__ . "','" . __LINE__ . "')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$dd = date("Y-m-d");
	$qtxt = "select max(report_number) as repno from report";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$reportNumber = $r['repno'] + 1;
	$qtxt = "insert into report (date,type,description,count,total,report_number) ";
	$qtxt.= "values ('$dd','Head line','Cash count, box $kasse','0','0','$reportNumber')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	if (count($fakturadate) && (($ownCommissionAccountNew) || ($ownCommissionAccountUsed))) {
		include('pos_ordre_includes/settleCommission/moveToOwnAccount.php');
	}
	if (($settleCommission || $createPayList) && (($customerCommissionAccountNew && $commissionAccountNew) || ($customerCommissionAccountUsed && $commissionAccountUsed))) {
		include('pos_ordre_includes/settleCommission/moveToCustomerAccount.php');
	}
	for ($z = 0; $z < count($valuta); $z++) { #201606132 Flyttet fra nederst (af de 3 for løkker) til øverst"
		for ($x = 0; $x < count($fakturadate); $x++) {
			for ($y = 0; $y < count($betaling[$x]); $y++) {
				$id = $kto_id = NULL;
				$k = 0;

				$qtxt = "select ordrer.id,ordrer.konto_id from ordrer,pos_betalinger where ordrer.felt_5='$kasse' ";
				$qtxt .= "and ordrer.fakturadate='$fakturadate[$x]' ";
				$qtxt .= "and pos_betalinger.betalingstype='" . $betaling[$x][$y] . "' ";
				$qtxt .= "and pos_betalinger.valuta='$valuta[$z]' and ordrer.status='3' ";
				$qtxt .= "and ordrer.id=pos_betalinger.ordre_id"; #20150306 + 20150310
				$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					if (strtolower($betaling[$x][$y]) == 'konto') {
						#						($id)?$id.=",".$r['id']:$id=$r['id'];
						($ko_id) ? $ko_id .= "," . $r['id'] : $ko_id = $r['id']; # salg på konto
					} else {
						($id) ? $id .= "," . $r['id'] : $id = $r['id'];
						($kto_id) ? $kto_id .= "," . $r['konto_id'] : $kto_id = $r['konto_id'];
					}
					#					$oid[$k]=$r['id'];
#					$kto_id[$k]=$r['konto_id'];
#					$k++;
				}
				$qtxt = "select box9 from grupper where art='POS' and kodenr='1' and fiscal_year = '$regnaar'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				if ($id) {
					#				$svar='OK';
					$svar = bogfor_nu("$id", "Dagsafslutning");
					if ($svar == 'OK') {
						echo '';
					} else {
						$alert1 = findtekst(1869, $sprog_id);
						$txt1 = findtekst(1870, $sprog_id);
						$txt2 = findtekst(1871, $sprog_id);
						#						echo "$svar<br>\n";
#						print "$txt1, ID $ordre_id ordre $ordrenr, d=$d_kontrol, k=$k_kontrol $txt2";
						print "<BODY onLoad=\"javascript:alert('$alert1')\">\n";
						exit;
						print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
						exit;
					}
				}
			} # 20160612 Flyttet fra under nedenstående blok
		} # 20160612 Flyttet fra under nedenstående blok
		if ($ko_id) {
			$k_oid = explode(',', $ko_id);
			for ($k = 0; $k < count($k_oid); $k++) {
				if ($k_oid[$k]) {
					$svar = bogfor_nu($k_oid[$k], '');
					if ($svar != 'OK') {
						echo $svar . "<br>";
						exit;
					}
				}
			}
		}
		if ($valuta[$z] == 'DKK' || !$valuta[$z]) {
			if ($kassekonto && $mellemkonto && $udtages) {
				# --> 20140709
				$qtxt = "select beskrivelse from kontoplan where kontonr = '$mellemkonto' and regnskabsaar = '$regnaar'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$mellemnavn = db_escape_string($r['beskrivelse']);
				# <-- 20140709 + *-1 ved udtages hvis < 0
				if ($udtages > 0) {
					$debet = 0;
					$kredit = $udtages;
				} else {
					$debet = $udtages * -1;
					$kredit = 0;
				}
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
				$qtxt.= "('0','$dd','Overført til $mellemnavn fra kasse $kasse','$kassekonto','0','$debet','$kredit',";
				$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
				$qtxt.= "('0','$dd','Overført til $mellemnavn fra kasse $kasse','$mellemkonto','0','$kredit',";
				$qtxt.= "'$debet',0,'$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
			if ($kassekonto && $diffkonto && $kassediff) {
				#$logtime=date("H:i");#20170223
				if ($diffVatRate) {
					$diffExVat = afrund($kassediff / (1 + $diffVatRate / 100), 2);
					$diffVat = $kassediff - $diffExVat;
				} else {
					$diffExVat = $kassediff;
					$diffVat = 0;
				}
				if ($kassediff > 0) {
					$debet = $kassediff;
					$kredit = 0;
				} else {
					$debet = 0;
					$kredit = $kassediff * -1;
				}
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number,moms) values ";
				$qtxt.= "('0','$dd','Kassedifference, kasse $kasse','$kassekonto','0','$debet','$kredit',";
				$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber','0')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				if ($diffExVat > 0) {
					$debet = 0;
					$kredit = $diffExVat;
				} else {
					$debet = $diffExVat * -1;
					$kredit = 0;
				}
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number,moms) values ";
				$qtxt.= "('0','$dd','Kassedifference, kasse $kasse','$diffkonto','0','$debet','$kredit',";
				$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber','" . $diffVat * -1 . "')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				if ($diffVat) {
					if ($diffVat > 0) {
						$debet = 0;
						$kredit = $diffVat;
					} else {
						$kredit = 0;
						$debet = $diffVat * -1;
					}
					$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
					$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number,moms) values ";
					$qtxt.= "('0','$dd','Kassedifference, kasse $kasse','$diffVatAccount','0','$debet','$kredit',";
					$qtxt.= "0,'$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber','0')";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
			}
		} else {
			if ($ValutaKonti[$z] && $ValutaTilgang[$z]) {
				if ($ValutaTilgang[$z] > 0) {
					$debet = $ValutaTilgang[$z];
					$kredit = 0;
				} else {
					$debet = 0;
					$kredit = $ValutaTilgang[$z] * -1;
				}
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
				$qtxt.= "('0','$dd','Kassesalg $valuta[$z], kasse $kasse','$ValutaKonti[$z]','0','$debet','$kredit',";
				$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
				$qtxt.= "('0','$dd','Kassesalg $valuta[$z], kasse $kasse','$kassekonto','0','$kredit','$debet',";
				$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
			if ($ValutaKonti[$z] && $ValutaMlKonti[$z] && $ValutaUdtages[$z]) {
				$r = db_fetch_array(db_select("select beskrivelse from kontoplan where kontonr = '$ValutaMlKonti[$z]' and regnskabsaar = '$regnaar'", __FILE__ . " linje " . __LINE__));
				$mellemnavn = db_escape_string($r['beskrivelse']);
				#<-- 20140709 + *-1 ved udtages hvis < 0
				if ($ValutaUdtages[$z] > 0) {
					$debet = 0;
					$kredit = $ValutaUdtages[$z];
				} else {
					$debet = $ValutaUdtages[$z] * -1;
					$kredit = 0;
				}
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
				$qtxt.= "('0','$dd','Overført til $mellemnavn fra kasse $kasse','$ValutaKonti[$z]','0','$debet','$kredit',";
				$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
				$qtxt.= "('0','$dd','Overført til $mellemnavn fra kasse $kasse','$ValutaMlKonti[$z]','0','$kredit','$debet',";
				$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
			# --> 20140709
			if ($ValutaKonti[$z] && $ValutaDifKonti[$z] && $ValutaKasseDiff[$z]) {
				if ($ValutaDiffVatRate[$z]) {
					$ValutaDiffExVat[$z] = afrund($ValutaKasseDiff[$z] / (1 + $ValutaDiffVatRate[$z] / 100), 2);
					$ValutaDiffVat[$z] = $ValutaKasseDiff[$z] - $ValutaDiffExVat[$z];
				} else
					$ValutaDiffVat[$z] = 0;
				if ($ValutaKasseDiff[$z] > 0) {
					$debet = $ValutaKasseDiff[$z];
					$kredit = 0;
				} else {
					$debet = 0;
					$kredit = $ValutaKasseDiff[$z] * -1;
				}
				$qtxt = "insert into transaktioner  (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number,moms) values ";
				$qtxt.= "('0','$dd','Kassedifference $valuta[$z], kasse $kasse','$ValutaKonti[$z]','0','$debet','$kredit',";
				$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber','$ValutaDiffVat[$z]')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				if ($ValutaKasseDiff[$z] < 0) {
					$kredit = $ValutaKasseDiff[$z] * -1;
					$debet = 0;
				} else {
					$kredit = 0;
					$debet = $ValutaKasseDiff[$z];
				}
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number,moms) values ";
				$qtxt.= "('0','$dd','Kassedifference $valuta[$z], kasse $kasse','$ValutaDifKonti[$z]','0','$kredit','$debet',";
				$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber','" . $ValutaDiffVat[$z] * -1 . "')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
			if ($ValutaDiffVat[$z]) {
				if ($ValutaDiffVat[$z] > 0) {
					$debet = $ValutaDiffVat[$z];
					$kredit = 0;
				} else {
					$debet = 0;
					$kredit = $ValutaDiffVat[$z] * -1;
				}
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number,moms) values ";
				$qtxt.= "('0','$dd','Kassedifference, kasse $kasse','$ValutaDiffVatAccount[$z]','0','$debet','$kredit',";
				$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber','0')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}

		if ($change_cardvalue && $kassekonto) {
			$diffsum = 0;
			$ny_kortsum = if_isset($_POST['ny_kortsum']);
			$kortsum = if_isset($_POST['kortsum']);
			$kortnavn = if_isset($_POST['kortnavn']);
			$kontkonto = if_isset($_POST['kontkonto']);
			for ($y = 0; $y < count($kortnavn); $y++) {
				$ny_kortsum[$y] = usdecimal($ny_kortsum[$y], 2);
				if ($diff = $ny_kortsum[$y] - $kortsum[$y]) {
					$debet = 0;
					$kredit = 0;
					($diff > 0) ? $debet = $diff : $kredit -= $diff;
					$diffsum += $diff;
					$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
					$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
					$qtxt.= "('0','$dd','Efterpost - bet.kort kasse $kasse','$kontkonto[$y]','0','$debet','$kredit',";
					$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber')";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				}
			}
			if (abs(afrund($diffsum, 2)) >= 0.01) {
				$debet = 0;
				$kredit = 0;
				($diffsum > 0) ? $kredit = $diffsum : $debet -= $diffsum;
				$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
				$qtxt.= "kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
				$qtxt.= "('0','$dd','Efterpost - bet.kort kasse $kasse','$kassekonto','0','$debet','$kredit',";
				$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}
		$logtime = date("U") + 60;
		$logtime = date("H:i:s", $logtime);
		$qtxt = "insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,";
		$qtxt.= " kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr,report_number) values ";
		$qtxt.= "('0','$dd','Kasseoptaelling,kasse $kasse','0','0','0','0',";
		$qtxt.= "'0','$afd','$dd','$logtime','','$ansat_id','0','$kasse','$reportNumber')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__); # 20161116
	}
	#	transaktion('rollback');
	$qtxt = "insert into pos_events (ev_type,ev_time,cash_register_id,employee_id,order_id,file,line) ";
	$qtxt.= "values ";
	$qtxt.= "('13006','" . date('U') . "','$kasse','$bruger_id','0','" . __FILE__ . "','" . __LINE__ . "')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	transaktion('commit');
	# <-- 20140709
	setcookie("saldi_kasseoptael", NULL, time() - 10); #20200112
	$pfnavn = "../temp/" . $db . "/kasseopg" . str_replace("-", "", $kasse) . ".txt";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?udskriv_kasseopg=$pfnavn&kasse=$kasse\">\n"; #20190813
} #?id=$id&udskriv_kasseopg=$pfnavn&kasse=$kasse

function kasseoptalling(
	$kasse,
	$optalt,
	$ore_50,
	$kr_1,
	$kr_2,
	$kr_5,
	$kr_10,
	$kr_20,
	$kr_50,
	$kr_100,
	$kr_200,
	$kr_500,
	$kr_1000,
	$kr_andet,
	$optval,
	$fiveRappen = 0,
	$tenRappen = 0,
	$twentyRappen = 0
) {
	$ore_50 = (int) $ore_50;
	$kr_1 = (int) $kr_1;
	$kr_2 = (int) $kr_2;
	$kr_5 = (int) $kr_5;
	$kr_10 = (int) $kr_10;
	$kr_20 = (int) $kr_20;
	$kr_50 = (int) $kr_50;
	$kr_100 = (int) $kr_100;
	$kr_200 = (int) $kr_200;
	$kr_500 = (int) $kr_500;
	$kr_1000 = (int) $kr_1000;
	$kr_andet = (float) $kr_andet;

	global $bordnr, $bruger_id;
	global $db;
	global $id, $ifs;
	global $log;
	global $optalt;
	global $regnaar;
	global $sprog_id; #20210817

	$color = 0;
	$country = getCountry();
	$udtages = if_isset($_POST['udtages']);
	if ($udtages) {
		$udtages = usdecimal($udtages);
		setcookie("saldiPOSfromBox", $udtages, time() + 600);
	} else
		$udtages = if_isset($_COOKIE["saldiPOSfromBox"]);
	$optplusbyt = if_isset($_POST['optplusbyt']);
	$ny_kortsum = if_isset($_POST['ny_kortsum']);
	$tidl_optalt = if_isset($_POST['tidl_optalt']);

	$change_cardvalue = $useCommission = $comissionSetteTime = NULL;
	$qtxt = "select var_value from settings where var_name = 'change_cardvalue' limit 1";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
		$change_cardvalue = $r['var_value'];
	$qtxt = "select var_value from settings where var_name = 'useCommission' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
		$useCommission = $r['var_value'];
	$qtxt = "select var_value from settings where var_name = 'comissionSetteTime' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
		$comissionSetteTime = $r['var_value'];

	$commissionAccountUsed = $commissionAccountNew = $customerCommissionAccountNew = $customerCommissionAccountUsed = NULL;
	$qtxt = "select id,var_value from settings where var_name = 'commissionAccountUsed' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
		$commissionAccountUsed = $r['var_value'];
	$qtxt = "select id,var_value from settings where var_name = 'commissionAccountNew' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
		$commissionAccountNew = $r['var_value'];
	$qtxt = "select id,var_value from settings where var_name = 'customerCommissionAccountNew' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
		$customerCommissionAccountNew = $r['var_value'];
	$qtxt = "select id,var_value from settings where var_name = 'customerCommissionAccountUsed' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
		$customerCommissionAccountUsed = $r['var_value'];

	$qtxt = "select id from grupper where art = 'POS' and kodenr='2' and box7 != '' and fiscal_year = '$regnaar'";
	if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$qtxt = "select ordrer.id,ordrer.nr from ordrer,ordrelinjer where ordrer.art = 'PO' and ordrer.status < 3 and ";
		$qtxt.= "ordrer.nr >= '0' and ordrer.felt_5 = '$kasse' and ordrelinjer.ordre_id=ordrer.id and ordrelinjer.id > 0";
		$txt = '';
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		$chk = array();
		$i = 0;
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['id'], $chk)) {
				$txt = $i + 1;
				$chk[$i] = $r['id'];
				$i++;
			}
		}
		if ($txt) {
			$txt = "" . findtekst(1852, $sprog_id) . ": $txt";
			print tekstboks($txt);
		}
	}
	include_once("pos_ordre_includes/boxCountMethods/findBoxSale.php");
	$svar = findBoxSale($kasse, $optalt, 'DKK');
	$byttepenge = $svar[0];
	$tilgang = $svar[1];
	$diff = $svar[2];
	$kortantal = $svar[3];
	$kontkonto = explode(chr(9), $svar[4]);
	$kortnavn = explode(chr(9), $svar[5]);
	$kortsum = explode(chr(9), $svar[6]);
	$kontosum = $svar[7];
	($svar[10]) ? $vatRates = explode(chr(9), $svar[10]) : $vatRates = array();
	($svar[11]) ? $vatAmounts = explode(chr(9), $svar[11]) : $vatAmounts = array();
	$accountPayment = $svar[12];
	$omsatning = $tilgang + $kontosum;

	$r = db_fetch_array(db_select("select box8,box9,box14 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	$mellemkonti = explode(chr(9), $r['box8']);
	$mellemkonto = $mellemkonti[$kasse - 1];
	$diffkonti = explode(chr(9), $r['box9']);
	$diffkonto = $diffkonti[$kasse - 1];
	($r['box14']) ? $udtag0 = 'on' : $udtag0 = NULL;

	$kortdiff = 0;
	if ($change_cardvalue) {
		for ($x = 0; $x < count($kortsum); $x++) {
			$kortdiff += $kortsum[$x] - usdecimal($ny_kortsum[$x], 2);
		}
		$kortdiff = afrund($kortdiff, 2);
	}

	$x = 0;
	$k = $kasse - 1;
	$tmp = $valuta = array();
	$q = db_select("select * from grupper where art = 'VK' order by box1", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$valuta[$x] = $r['box1'];
		$tmp = explode(chr(9), $r['box4']);
		$ValutaKonti[$x] = $tmp[$k];
		$tmp = explode(chr(9), $r['box5']);
		$ValutaMlKonti[$x] = $tmp[$k];
		$tmp = explode(chr(9), $r['box6']);
		$ValutaDifKonti[$x] = $tmp[$k];
		$kodenr = $r['kodenr'];
		$r2 = db_fetch_array(db_select("select kurs from valuta where gruppe = '$kodenr' order by valdate desc limit 1", __FILE__ . " linje " . __LINE__));
		$valutakurs[$x] = $r2['kurs'];
		$x++;
	}

	print "<table><tbody>\n";
	print "<tr><td width='30%'>";
	print "<table><tbody>\n";
	print "<form name=\"optael\" action=\"pos_ordre.php?id=$id&kasse=$kasse&kassebeholdning=on&bordnr=$bordnr\" method=\"post\" autocomplete=\"off\">\n";

	print "<input type=\"hidden\" name=\"byttepenge\" value=\"$byttepenge\">\n";
	print "<input type=\"hidden\" name=\"optalt\" value=\"$optalt\">\n";
	print "<input type=\"hidden\" name=\"tilgang\" value=\"$tilgang\">\n";
	#	print "<input type=\"hidden\" name=\"kontosalg\" value=\"$kontosalg\">\n";
	print "<input type=\"hidden\" name=\"kontosum\" value=\"$kontosum\">\n";
	for ($x = 0; $x < count($kontkonto); $x++) {
		print "<input type=\"hidden\" name=\"kontkonto[$x]\" value=\"$kontkonto[$x]\">\n";
		print "<input type=\"hidden\" name=\"kortnavn[$x]\" value=\"$kortnavn[$x]\">\n";
		print "<input type=\"hidden\" name=\"kortsum[$x]\" value=\"$kortsum[$x]\">\n";
		$omsatning += $kortsum[$x];
	}
	if (!$optalt && $_COOKIE['saldi_kasseoptael']) {
		$kr = array(0.5, 1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 1);
		$ot = explode(chr(9), $_COOKIE['saldi_kasseoptael']);
		for ($o = 0; $o < count($ot); $o++) {
			if (!isset($kr[$o]))
				$kr[$o] = 0;
			$optalt += (float) $kr[$o] * (float) $ot[$o];
		}
		$kr = $ot = NULL;
	}
	$kassediff = $optalt - ($byttepenge + $tilgang);
	$kassediff -= $kortdiff;

	if (!$optalt) {
		$optalt = $ore_50 * 0.5 + $kr_1 + $kr_2 * 2 + $kr_5 * 5 + $kr_10 * 10 + $kr_20 * 20 + $kr_50 * 50 + $kr_100 * 100 + $kr_200 * 200 + $kr_500 * 500 + $kr_1000 * 1000 + $kr_andet + $fiveRappen * 0.05 + $tenRappen * 0.1 + $twentyRappen * 0.2;
	}
	if ((!$optalt && $optalt != '0') || $optalt != $tidl_optalt) {
		($udtag0) ? $udtages = 0 : $udtages = $tilgang + $kassediff;
	}

	$forventet = $byttepenge + $tilgang + $kortdiff;
	(isset($_POST['optael']) && $_POST['optael']) ? $ny_morgen = $optalt - $udtages : $ny_morgen = 0; #20200112
	specifyAmount($omsatning, $kassediff, $optalt, $db, $kasse, $ifs, $ore_50, $kr_1, $kr_2, $kr_5, $kr_10, $kr_20, $kr_50, $kr_100, $kr_200, $kr_500, $kr_1000, $kr_andet, $fiveRappen, $tenRappen, $twentyRappen);
	if ($valuta[0]) {
		for ($x = 0; $x < count($valuta); $x++) {
			print "<tr><td align=\"right\">$valuta[$x]</td><td></td><td align=\"right\">";
			print "<input style=\"width:100;text-align:right;font-size:$ifs;\" name=\"optval[$x]\" value=\"" . dkdecimal($optval[$x], 2) . "\"></td></tr>\n";
			if ($log)
				file_put_contents($logfil, "$valuta[$x] $optval[$x]\n", FILE_APPEND);
		}
	}
	$pfnavn = "../temp/" . $db . "/kasseopg" . str_replace("-", "", $kasse) . ".txt";
	cashCountResult($pfnavn, $kasse, $id, $byttepenge, $ny_morgen, $tilgang, $forventet, $optalt, $kassediff, $color, $mellemkonto, $udtages);
	$logfil = "../temp/" . $db . "/kasseopg" . str_replace("-", "", $kasse) . ".log";

	for ($x = 0; $x < count($valuta); $x++) {
		if ($valuta[$x]) {
			$svar = findBoxSale($kasse, $optval[$x] * $valutakurs[$x] / 100, $valuta[$x]);
			if (is_array($svar)) { #20160824
				$byttepenge = $svar[0] * 100 / $valutakurs[$x];
				$omsatning += $svar[1];
				$tilgang = $svar[1] * 100 / $valutakurs[$x];
				$diff = $svar[2] * 100 / $valutakurs[$x];
				$ValutaKasseDiff[$x] = $optval[$x] - ($byttepenge + $tilgang);
				print "<tr><td colspan=\"3\" align=\"center\">";
				print "<input type=\"hidden\" name=\"kontosum\" value=\"$valuta[$x]\">\n";
				print "<input type=\"hidden\" name=\"valuta[$x]\" value=\"$valuta[$x]\">\n";
				print "<input type=\"hidden\" name=\"ValutaKasseDiff[$x]\" value=\"$ValutaKasseDiff[$x]\">\n";
				print "<input type=\"hidden\" name=\"ValutaByttePenge[$x]\" value=\"$byttepenge\">\n";
				print "<input type=\"hidden\" name=\"ValutaTilgang[$x]\" value=\"$tilgang\">\n";
				print "<b>--- $valuta[$x] ---</b></td></tr>\n";
				print "<tr><td colspan=\"2\"><b>Morgenbeholdning</b></td><td align=\"right\"><b>" . dkdecimal($byttepenge, 2) . "</b> $valuta[$x]</td></tr>\n";
				print "<tr><td colspan=\"2\"><b>Dagens tilgang</b></td><td align=\"right\"><b>" . dkdecimal($tilgang, 2) . "</b> $valuta[$x]</td></tr>\n";
				print "<tr><td colspan=\"2\"><b>Forventet beholdning</b></td><td align=\"right\"><b>" . dkdecimal($byttepenge + $tilgang, 2) . "</b> $valuta[$x]</td></tr>\n";
				print "<tr><td colspan=\"2\"><b>Optalt beholdning</b></td><td align=\"right\"><b>" . dkdecimal($optval[$x], 2) . "</b> $valuta[$x]</td></tr>\n";
				print "<tr><td colspan=\"2\"><b>Difference</b></td><td align=\"right\">";
				print "<b>" . dkdecimal($ValutaKasseDiff[$x], 2) . "</b> $valuta[$x]</td></tr>\n";
				file_put_contents($logfil, "Morgenbeholdning $byttepenge\n", FILE_APPEND);
				file_put_contents($logfil, "Dagens tilgang $tilgang\n", FILE_APPEND);
				file_put_contents($logfil, "Forventet beholdning " . $byttepenge + $tilgang . "\n", FILE_APPEND);
				file_put_contents($logfil, "Optalt beholdning $optalt DKK\n", FILE_APPEND);
				file_put_contents($logfil, "Difference $ValutaKasseDiff[$x]\n", FILE_APPEND);
			} else { #20160824
				print "<tr><td colspan=\"2\" align=\"center\">$svar</td></tr>\n";
			}
			if ($optalt || $optalt == '0') {
				if ($ValutaMlKonti[$x]) {
					print "<tr><td colspan=\"2\"><b>Udtages fra kasse</b></td>";
					print "<td align=\"right\">";
					print "<input type=\"text\" style=\"width:100;text-align:right;font-size:$ifs;\" name=\"ValutaUdtages[$x]\" value=\"" . dkdecimal(pos_afrund($optval[$x] - $byttepenge, '', ''), 2) . "\">";
					print "$valuta[$x]</td></tr>\n";
				} else
					($ValutaUdtages[$x] = 0);
				if ($log)
					file_put_contents($logfil, "Udtages $ValutaUdtages[$x] $valuta[$x]\n", FILE_APPEND);
			}
		}
	}
	$calcTxtArr = setCashCountText();
	if (($optalt || $optalt == '0') && $_POST['optael'] == $calcTxtArr['calculate']) { #LN 20190219
#		if($kortdiff) {
#			$disabled='disabled';
#			$title='Der kan ikke godkendes når der er differencer på betalingskort';
#		} else {
		$disabled = NULL;
		$title = findtekst(1853, $sprog_id);
		#		}
		$acceptPrint = acceptPrint();

		if ($useCommission && (($customerCommissionAccountNew && $commissionAccountNew) || ($customerCommissionAccountUsed && $commissionAccountUsed))) {
			$commissionFromDate = $commissionToDate = NULL;
			$qtxt = "select var_value as settletime from settings where var_name = 'commissionFromDate' and var_grp = 'items'";
			if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))
				$lastSettle = dkdato($r['settletime']);
			else
				$commissionFromDate = NULL;
			$title = findtekst(1854, $sprog_id);
			if ($lastSettle)
				$title .= "\n" . findtekst(1856, $sprog_id) . ": " . $lastSettle . "";
			if (isset($_POST['commissionFromDate'])) {
				$commissionFromDate = dkdato(usdate($_POST['commissionFromDate']));
			}
			if (isset($_POST['commissionToDate'])) {
				$commissionToDate = dkdato(usdate($_POST['commissionToDate']));
			}
			if (!$commissionFromDate)
				$commissionFromDate = $lastSettle;
			if (!$commissionFromDate)
				$commissionFromDate = date("d-m-Y");
			if (strlen($commissionFromDate) == 10)
				$commissionFromDate = substr($commissionFromDate, 0, 6) . substr($commissionFromDate, -2);
			if (!$commissionToDate)
				$commissionToDate = date("d-m-y");
			$title1 = findtekst(1856, $sprog_id);
			$title2 = findtekst(1857, $sprog_id);
			$title3 = findtekst(1858, $sprog_id);
			print "<tr><td title = '$title'>";
			print "" . findtekst(1859, $sprog_id) . "</td><td title = '$title'><input type = 'checkbox' name = 'settleCommission'></td><td align = 'right'>";
			print "<span title = '$title1'>";
			print "<input type = 'text' style = 'width:62px;' name = 'commissionFromDate' value = '$commissionFromDate'>";
			print "</span> - <span title = '$title2'>";
			print "<input type = 'text' style = 'width:62px;' name = 'commissionToDate' value = '$commissionToDate'>";
			print "</span></td></tr>";
			print "<tr><td>" . findtekst(1860, $sprog_id) . "";
			print "</td><td><input type = 'checkbox' name = 'createPayList'></td>";
		} else
			print "<tr><td colspan = '2'><b>Z-Rapport</b></td>";
		$title = findtekst(1853, $sprog_id);
		print "<td align = 'right' colspan = '1' title = '$title'>";// Accept(Godkend) button
		print "<input $disabled style = 'width:135px;' type = 'submit' name = 'optael' value = \"$calcTxtArr[accept]\" ";
		print "onclick = \"javascript:return confirm('$acceptPrint')\"></td></tr>\n";
	}
	if (!count($valuta) && $tilgang != '0,00')
		displayLine('Kontant', $tilgang, 'DKK');
	if ($kontosum) {
		print "<tr><td colspan='2'><b>" . findtekst(592, $sprog_id) . "</b></td><td align='right'><b>" . dkdecimal($kontosum, 2) . "</b> DKK</td></tr>\n";
		if ($log)
			file_put_contents($logfil, "Konto $kontosum\n", FILE_APPEND);
	}
	setCreditCards($kontkonto, $kortnavn, $change_cardvalue, $kortsum, $ny_kortsum, $vatRates, $vatAmounts, $accountPayment, $ifs, $kortdiff, $omsatning, $log, $id);
	print "</tr></tbody></table>\n";
	exit;
}

/*
function flyt_bord($id,$bordnr,$delflyt) { #20140508
	global $brugernavn;
	global $s_id;
	global $db;

	print "<a href=\"pos_ordre.php?id=$id&bordnr=$bordnr\">Fortryd</a><br>\n";
	
	$x=0;
	$optaget=array();
	$q=db_select("select id,nr from ordrer where art = 'PO' and status<'3'",__FILE__ . " linje " . __LINE__); 
	while($r=db_fetch_array($q)){
		if($r['nr'] && $r2=db_fetch_array(db_select("select id from ordrelinjer where ordre_id='$r[id]'",__FILE__ . " linje " . __LINE__))){ 
			$optaget[$x]=$r['nr'];
			$x++;
		}
	}

	$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr='2'",__FILE__ . " linje " . __LINE__)); 
	$bord=explode(chr(9),$r['box7']); #20140507
	for ($x=0;$x<count($bord);$x++){
		$tmp=$x+1;
		if (!in_array($tmp,$optaget) || $delflyt) {
			if ($delflyt) print "<a href=\"pos_ordre.php?id=$id&flyt_til=$tmp&delflyt=$delflyt\">$bord[$x]</a><br>\n";
			else print "<a href=\"pos_ordre.php?id=$id&flyt_til=$tmp\">$bord[$x]</a><br>\n";
		}
	}
	exit;
}
*/


function kundedisplay($beskrivelse, $pris, $ryd)
{
	global $db, $fast_morgen, $kasse;
	global $printserver, $regnaar;
	global $kundedisplay;

	if (!$printserver) {
		$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
		$printer_ip = explode(chr(9), $r['box3']);
		$tmp = $kasse - 1;
		$printserver = $printer_ip[$tmp];
		if (!$printserver || strtolower($printserver) == 'box') {
			$filnavn = "http://saldi.dk/kasse/" . $_SERVER['REMOTE_ADDR'] . ".ip";
			if ($fp = fopen($filnavn, 'r')) {
				$printserver = trim(fgets($fp));
				fclose($fp);
			}
		}
	}
	if ($printserver) {
		$params = "scrollbars=no,resizable=no,status=no,location=no,toolbar=no,menubar=no,";
		$params .= "width=100,height=100,top=1000,left=2000";
		$href = "" . ($printserver == 'android' ? "saldiprint://" : "http://$printserver") . "/kundedisplay.php?tekst=" . urlencode($beskrivelse) . "&pris=" . dkdecimal($pris, 2) . "&ryd=$ryd";
		print "<script type=\"text/javascript\">window.open('$href','','$params');</script>";
	}
}


###############################################################

function find_stil($type, $nr, $fs)
{
	global $bgcolor, $bgcolor2, $bgcolor3, $bgcolor5;
	global $db, $pfs;

	if (!$pfs)
		$pfs = 10;

	$r = db_fetch_array(db_select("select * from grupper where art='POSBUT' and kodenr='0'", __FILE__ . " linje " . __LINE__));
	$cols = $r['box2'];
	$rows = $r['box3'];
	$height = $r['box4'];
	$width = $r['box5'];
	$radius = $r['box11'];
	$fontsize = $r['box10'];
	if (!$fontsize)
		$fontsize = $height * $width / 200;
	if (!$width)
		$width = 100;
	if (!$height)
		$height = 40;
	if (!$fontsize)
		$fontsize = 25;

	if ($fs)
		$fontsize *= $fs;

	if ($nr > 1) {
		$width = $width * $nr;
		$width += $nr * 4;
	}

	if ($type == 'knap') {
		$stil = "STYLE=\"
			display: table-cell;
			moz-border-radius:" . $radius . "px;
			-webkit-border-radius:" . $radius . "px;
			width:" . $width / 10 * $pfs . "px;
			height:" . $height / 10 * $pfs . "px;
			text-align:center;
			vertical-align:middle;
			font-size:" . $fontsize / 10 * $pfs . "px;
			color: black;
			border: 1px solid $bgcolor2;
			white-space: normal;
			background-color: $bgcolor;\"";
	} elseif ($type == 'select' || $type == 'input') {
		$stil = "STYLE=\"
			width:" . $width . "px;
			height:" . $height . "px;
			text-align:center;
			white-space: normal;
			font-size:" . $fontsize . "px;\"";
	}
	return ("$stil");
}
function aabn_skuffe($id, $kasse)
{
	global $bruger_id, $db;
	global $regnaar, $tracelog;

	$qtxt = "select box3,box4,box5,box6 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$x = $kasse - 1;
	$tmp = explode(chr(9), $r['box3']);
	$printserver = trim($tmp[$x]);
	if (!$printserver)
		$printserver = 'localhost';
	if ($printserver == 'box' || $printserver == 'saldibox') {
		$filnavn = "http://saldi.dk/kasse/" . $_SERVER['REMOTE_ADDR'] . ".ip";
		if ($fp = fopen($filnavn, 'r')) {
			$printserver = trim(fgets($fp));
			fclose($fp);
		}
	}
	$url = "://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
	$url = str_replace("/debitor/pos_ordre.php", "", $url);
	if ($_SERVER['HTTPS'])
		$url = "s" . $url;
	$url = "http" . $url;
	countDrawOpening($kasse);
	if ($tracelog)
		fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls $printserver/saldiprint.php (openDrawer)\n");
	print "<meta http-equiv=\"refresh\" content=\"0;URL=" . ($printserver == 'android' ? "saldiprint://" : "http://$printserver") . "/saldiprint.php?url=$url&bruger_id=$bruger_id&id=$id&skuffe=1&returside=$url/debitor/pos_ordre.php\">\n";
	exit;
}

function udskriv_kasseopg($id, $kasse, $pfnavn)
{
	global $db;
	global $bruger_id;
	global $regnaar, $tracelog;

	$bon = '';
	$fp = fopen("$pfnavn", "r");
	while ($linje = fgets($fp)) {
		$bon .= $linje;
	}
	$bon = urlencode($bon);
	$r = db_fetch_array(db_select("select box3,box4,box5,box6 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
	$x = $kasse - 1;
	$tmp = explode(chr(9), $r['box3']);
	$printserver = trim($tmp[$x]);
	if (!$printserver)
		$printserver = 'localhost';
	if ($printserver == 'box' || $printserver == 'saldibox') {
		$filnavn = "http://saldi.dk/kasse/" . $_SERVER['REMOTE_ADDR'] . ".ip";
		if ($fp = fopen($filnavn, 'r')) {
			$printserver = trim(fgets($fp));
			fclose($fp);
		}
	}
	$url = "://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
	$url = str_replace("/debitor/pos_ordre.php", "", $url);
	if ($_SERVER['HTTPS'])
		$url = "s" . $url;
	$url = "http" . $url;
	if ($tracelog)
		fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls $printserver/saldiprint.php\n");
	if ($printpopup) {
		print "<BODY onLoad=\"JavaScript:window.open('" . ($printserver == 'android' ? "saldiprint://" : "http://$printserver") . "/saldiprint.php?";
		print "printfil=$pfnavn&url=$url&bruger_id=$bruger_id&bonantal=1&bon=$bon&skuffe=1&gem=1' , '' , '$jsvars');\">\n";
	} else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=" . ($printserver == 'android' ? "saldiprint://" : "http://$printserver") . "/saldiprint.php?printfil=$pfnavn&url=$url&";
		print "bruger_id=$bruger_id&bonantal=$bonantal&id=$id&returside=$url/debitor/pos_ordre.php&bon=$bon&skuffe=1&gem=1\">\n";
		exit;
	}
	exit;
}


function posvaluta($modtaget)
{
	global $db;

	$betvaluta = if_isset($_POST['betvaluta']);
	$betvalsum = if_isset($_POST['betvalsum']);
	$prevalkurs = if_isset($_POST['betvalkurs']);
	if (!$prevalkurs)
		$prevalkurs = 100;
	$valmodt = $modtaget;
	if ($betvaluta && $betvaluta != 'DKK') {
		$dd = date("Y-m-d");
		$qtxt = "select kodenr from grupper where art = 'VK' and box1 = '$betvaluta'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$qtxt = "select kurs from valuta where valdate <='$dd' and gruppe='$r[kodenr]' order by valdate desc limit 1";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$betvalkurs = $r['kurs'];
	} else {
		$betvalkurs = 100;
		$betvaluta = 'DKK';
	}
	if ($betvalkurs != $prevalkurs) {
		$modtaget = NULL;
		$valmodt = NULL;
	} elseif ($betvalkurs != 100)
		$modtaget *= $betvalkurs / 100;

	return ($modtaget . chr(9) . $valmodt . chr(9) . $betvaluta . chr(9) . $betvalkurs);
}



if (!$varenr_ny && $fokus != 'modtaget' && $fokus != 'modtaget2' && $fokus != 'indbetaling' && $fokus != 'delflyt')
	$fokus = "varenr_ny";
if ($obstxt)
	alert($obstxt);

?>

</body>

</html>

<script language="javascript">

	document.pos_ordre.<?php echo $fokus ?>.focus();

</script>
<!--
<script type="text/javascript">
cellh = (document.getElementById('varelin').offsetHeight);
alert(cellh);
document.getElementById('vindue').style.height = cellh;
</script>
-->