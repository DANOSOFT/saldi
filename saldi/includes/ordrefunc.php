<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//----------------- includes/ordrefunc.php -----ver 3.9.0---- 2020.03.12 ----------
// LICENS
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------

// 2012.07.30 søg 20120730
// 2012.06.13 søg 20120613
// 2012.09.05 søg 20120905
// 2102.10.01 søg 20121001
// 2012.12.17 søg 20121217
// 2013.04.08 Samlevarer blev ikke nedskrevet ved salg - || salmevare=='on' fjernet fra if Søg 20130408
// 2013.04.11 fejl ved genfakturering hvis ordredato > fakturadato Søg 20130411
// 2013.04.11 fejl v fakturering hvis slutmaaned er et cifret  Søg 20130411-2
// 2013.05.06	Fjernet transaktionskontrol fra leveringsfunktion og rettet lidt omkring fejlhåndtering ved bogføring.Søg 20130506
// 2013.08.13	Kald til beholdningsopdatering ved "intern webshop" undertrykt Søg 20130813
// 2013,08.20 Kontering på kassekonto og kreditkortkonto nu mulig v. kontantsalg. Søg variablen $openpost
// 2013.08.23 Fejl v. indbetaling på konto via POS ordre, hvis betaling med kreditkort og penge retur fra kasse. Søg $kassekonto i funktion bogfor_indbetaling.
// 2013.08.24	Indsat ($art!='PO' || $antal) i betingelser for opdatering af ordrelinje. Søg 20130824
// 2013.08.24	Tilføjet kasse_nr & $kasse i alle "insert into transaktioner" som vedrører POS ordrer.
// 2013.09.03	Ved insdættelse af vare fra shop med pris kr. 0 sættes pris fra vareliste. Er rettet. Søg 20130903
// 2013.09.16	Diverse ændringer i forhold til pos_ordrer.
// 2013.09.17 != ændret til == Søg #20130917
// 2013.10.01 Opdat beholdning blev ikke kaldt v. webshop.
// 2013.10.15 Tilføjet kontrol af status v. tilføjelse af ordrelinjer. 20131005
// 2013.11.05 Vender ikke tilbage til pos_ordre ved fejl. Søg 20131105
// 2013.11.11. Fejl i php på decimaltal, afrunding tilføjet inden diff beregning. søg 20131111
// 2013.11.29 Alle undervarer blev indsat ved kopiering af ordre med samlevare.Søg 20131129
// 2013.12.02 Sat afrunding på antal & leveret da det har været et tilfælde hvor levering ikke kunne ske. Søg 20131202
// 2014.01.15 Tilføjet && !$kontonr - Ellers bliver openpost ikke ført og der bogføres på kunden kontonummer i transaktioner og ikke på samlekontoen. Søg 20140115
// 2014.01.17 Rettet $kobs_valutakurs til $valutakurs da kursen ikke blev beregnet inden returnering af kostpris. Se også i ordrer.php. Sog 20140117 
// 2014.01.29 Indsat automatisk genkendelse af registrerede betalingskort, (Kun med integreret betalingsterminal) Søg 20140129, $kortnavn eller 'Betalingskort'
// 2014.01.31	Indsat popup ved som advarer ved indsættelse af vare som ikke kan leveres.20140131
// 2014.03.06	Oprettet funktion opret_ordre til sagstyring (PK - Herlev stilladser)
// 2014.03.06	Rettet funktion sidehoved, så sagsmenu virker (PK - Herlev stilladser) 
// 2014.03.06	Tilrettet funktion tekstopslag til sagstyring (PK - Herlev stilladser)
// 2014.04.24	Diverse tilretninger i forbindelse med procentfakturering (PHR - Danosoft) Søg 20140424a
// 2014.04.24	$art må ikke sættes her - bliver tom hvis der ikke er tilknyttet kunde (Pos kontantordre) (PHR - Danosoft) Søg 20140424b
// 2014.04.25 Fejl hvis sagssystem ikke er oprettet.(PHR - Danosoft) søg 20140425
// 2014.04.26 Tilføjet vare_id i opret_ordrelinje da kreditering eller kopiering af ordrer ikke fungerer hvis varenummer er ændret.(PHR - Danosoft) søg 20140426
// 2014.05.02 Udkommeneret header da denne også bliver sat i online.php. PHR	- Danosoft søg 20140502
// 2104.05.08	Diverse ændringer i funktion vareopslag i forbindelse med bordnr i pos_ordrer. PHR	- Danosoft Søg "bordnr" i "vareopslag". 
// 2014.06.13	Div småting relateret til pos_ordrer - bl. a. momsdiff ved salg til kr. 27,12 og betaling med Dankort +100. 20140613
// 2014.06.16 $kontonr ændret til $konto_id da alle kasser blev ført på samme konto. (PHR	- Danosoft) Søg 20140616
// 2014.06.28 Indsat bogføring af øredifferencer ved pos_ordrer for at undgå produktionsstop. (PHR	- Danosoft) Søg 20140628
// 2014.07.30 PK - Oprettet en ny funktion 'opret_ordre_kopi' som kopiere original ordre + ordrelinjer til ny sag. Søg 20140730
// 2014.08.21 PHR - tilføjet $tmp=$antal*-1 til erstatning for '-$antal' i "insert" Søg 20140821
// 2014.08.21 PK - Har ændret $kontakt ved oprettelse af ordre. Hvis $sag_id, er $kontakt fra sagen ellers er $kontakt fra kunde. Søg 20140821-1
// 2014.10.23 PHR - En række ændringer i levering og linjeopdat. Netagivt salg bliver nu fårt som negativ i batch_salg og ikke som før i batch_køb.
//            Samtidig findes den oprindelige linje i batch_køb og rest opskrives. (Hvis batch håndtering er aktiv) Eller opskrives rest på sidste 
//            linje med samme varenummer.
// 2014.11.05 PHR Indsat kontrol for om $id er sat ved kreditering af pos ordre. 20141105
// 2014.11.14 PHR Bordnr blev ikke sat ved kreditering af pos og var dermed ikke synlig. 
// 2014.11.18 PHR Fejl ved korrektion, hvis ingen borde.... 20141118 
// 2014.11.21 Fejl i funktion find_kostpris, hvis antal på linje = 0. Søg 20141121
// 2015.01.11 Søgning på varetekst i optimeret til brug med piletaster. Funktion vareopslag.
// 2015.01.12 Debitorkort kan nu også åbnes fra kontoopslag under pos_ordrer.
// 2015.01.19 Funktion find_kostpris. Søger nu kostpris fra batch køb selvom ej fifo. Finder seneste køb før fakturadato. 20150119 
// 2015.01.20 Funktion find_kostpris. Kostpris tages fra varekort hvis ej lagerført vare. Søg $lagerfort
// 2015.01.23 Oprettet funktion saet_afrund til adrunding af sætpriser. Søg saet_afrund
// 2015.01.31 Fjernet funktion saet_afrund igen. Ideen var alligevel ikke god.
// 2015.01.31 Oprettet funktion opret_saet til indsættelse af samlevarer som sæt i ordrer & pos_ordrer. Søg opret_saet
// 2015.02.14 Diverse ændringer i forbindelse med tilføjelse af lagerfelt på ordrelinjer. 
// 2015.02.15 Hvis der skrives en del af en varebeskrivelse i en debitorordre i varenr feltet søges på varetekst ved opslag, 
//	hvis teksten ikke findes som varenr. Søg 20150215  
// 2015.02.18 Rettelse af ovenstående. Forsøger vareopslag ved indsættelse at ordrelinjer fra shop via soap.
// 2015.01.31 Oprettet funktion gendan_saet til gendannelse af rabatter på saet. Søg gendan_saet
// 2015.02.27 Ændret afrundingfra 2 til 3 grundet ørediffer f sætpriser ig titalpriser. Søg 20150227
// 2015.05.06 Det skal ikke føres åbenpost ved betalingsbet Forud på pos_ordrer eller hvis id ikke er numerisk 20150306
// 2015.03.12 Meget underlige sætpriser ved indsættelse af sæt uden antal fra ordre.php 20150312
// 2015.03.31 Funtion vareopslag. Priser vises nu incl moms, hvis opslag kommer fra POS eller incl moms er 'on' 
// 2015.05.05 Diverse tilretninger til POS så betaling kan fordeles på flere kort. Søg 20150505 
// 2015.05.18 Fejl v. kortfordeling hvis betaling både med kort og kontakt blev kontant ført på kort = fejl i morgenbeholdning. 20150518
// 2015.05.21 Fejl v. kontosalg pga ovenstående, så den brugen kun hvis det ikke er kontosalg.
// 2015.05.22 Fejl v. fakturering hvis ingen momskonto - indsat fejlhåndtering 20150522
// 2015.06.27 Tilføjet tilfravalg på POS ordrer i opret_ordrelinje. Søg tilfravalg 
// 2015.07.24 Function pos_afrund. Hvis difkto ikke er sat slåes den op. 20150724
// 2015.08.10 Ordredate blev altid sat til fakturadate v hurtigfakt da variabel ikke blev sat. Søg 20150810
// 2015.08.12 Ændret 'if($ore>100)' til 'if(strlen($ore)>2)' da 080 øre blev til 80 øre istedet for 8 øre. # 20150812  
// 2015.08.20 Mulighed for tidsbestemte tilbud (Happy hour). Søg special_from_time.
// 2015.08.29 Funktion opret_saet. Tilføjet $incl_moms
// 2015.08.29 Funktion gendan_saet. Tilføjet opslag efter rabat vare_id.
// 2015.12.10 Funktion find_kostpris. Brugte altid kostpris fra varekort hvis der ikke er kobs_ordre_id of batch_salg. 
// 2015.12.23 Funktion pbsfakt. rettet echo ".. til print "<tr><td>.. osv.
// 2016.01.27 PK - Mobil fra ansatte hentes fra kontakt. Søg #20160127
// 2016.01.28 PHR - Tilføjet funktion stamkunder, som viser kontoopslag som knapper.
// 2016.01.29 PHR - Tilføjet funktion kontoudtog, som udskriver kontoudtog fra POS.
// 2016.02.01 PK - Ved kopi af ordre hentes mobil fra sag kontakt, ellers hentes mobil fra kundekontakt. Søg #20160201
// 2016.02.08 PHR - Tilrettet "stamkunder" med større knapper, sideskift og saldo/kreditmax på knap. Søg stamkunder
// 2016.02.17 PHR - Fejl ved kreditering, fokus vare0, funktion kontoopslag. Søg 20160217  
// 2016.08.04 PHR - $antal blev ikke sat... Søg 20160804
// 2016.08.10 PHR - m_rabat fungerer nu hvis varepris = 0 og pris er ændret. Søg 20160810
// 2016.08.15 PHR - Ved korrektion af ordrer med 'm_rabat' skal linjen med m_rabatten ikke med. #20160815
// 2016.08.24 PHR - Mængderabatter gav fejl i lagerrapport da rabatvaren blev blev talt som varesalg 20160824
// 2016.09.05 PHR - $mrabat ganges med en ellers er den 0.000 og if($mrabat) blive sand #20160905
// 2016.09.09 PHR - Tilbudspris fungerer ikke hvis tidspunkt > sluttidspkt. 20160909
// 2016.09.28 PHR - Ordrer blev omdannet til KB kvis der ikke var varer på ordren 20160928
// 2016.10.10 PHR - Først fundne openpost på indbetalt beløb udligned aut. ved indbetaling i kasse. 20161001
// 2016.10.11 PHR - Finder lager og opdaterer lagerstatus 20161011
// 2016.10.22 PHR - tilretning iht flere afd pr lager. 20161022
// 2016.10.27 PHR - Ved korrektion af bon med mængderabat gik der fejl i beløbene da den ikke kan finde ud af hvilken linjer der er rabatlinjen. 
//                - i stedet sættes mrabat til 0 og rabatlinjen kopieres med. Det ser ud til at virke - function krediter_pos 20161027 
// 2016.11.10 PHR - I vareopslag kan nu søges på flere ord adskilt af '+' #20161110
// 2016.11.24 PHR - opret_ordrelinjer. Tilbudspris 'special_price' blev aldrig fundet. 20161114 
// 2016.12.11 PHR - Tilføjer is_numric($id) da der eller kommer fejl når $id består af flere ordre # 20161211
// 2017.01.03 PHR - Tilføjet ekstra tjek for dubletter på fakturanr, 20170103
// 2017.02.07 PHR - Tilføjet if (!$momsfri) i funktion opret_orderlinje så det ikke sættes moms på momsfri varer fra API  20170207
// 2017.02.10 PHR - Aktivering af nyt API (funktion linjeopdat) 20170210
// 2107.02.17 PHR - Lager tilføjet i funktion opret_ordrelinje. Søg $lager 
// 2017.02.23 PK - Tilføjet kunde_ref_nr(kundeordnr) ved oprettelse af tilbud i sager. #20170223
// 2017.03.24 PHR - Ved bogføring sættes ref til 'ansat navn' så det er entydighed i kassespor. 20170324
// 2017.04.04 PHR - Straksbogfør skelner nu mellem debitor og kreditorordrer. Dvs debitor;kreditor - Søg # 20170404
// 2017.05.05 PHR - batch_kob.rest blev dobbeltændret Søg : 20170504
// 2017.05.29 PHR - Der belev indsat 0 i i batch_salg ved kreditering. Rettet $tmp2 til $antal. Søg 20170529
// 2017.06.01 PHR - Funktion tekstopslag - Indsat mulighed for at slette tekster også når der ikke er sag_id. Søg 20170601
// 2017.06.22 phr - Fejl ved kreditering af pos_ordrer' med 'samlet_pris' -Søg 20170622
// 2017.06.27 phr - Lagervalg var ikke muligt for samlevarer ved vareopslag  - Søg 20170627
// 2017.08.02 PHR - else rettet til elseif ($art!='PO') da der ellers kommer fejt ved optælling hvis kontonummer for kontantsalg ikke er sat #20170802
// 2017.08.16 PHR - Tilføjet strtolower så alle kort med samme navn køres på korrekt konto - Søg 20170816
// 2017.08.26 PHR - Trækker nu diff konto fra POS opsætning frem for fra 'diverse -> ørediff' #20170826
// 2017.10.04 PHR - Ordrer med betalingskortinfo behandles nu som pos ordrer #20171004
// 2017.10.04 PHR - indsat ekstra tjek for øredifferenser på ordrer #20171004
// 2017.10.09 PHR - Tilføjet funktion gls_label.
// 2017.10.31 PHR - Indsat faktura og leveringskontrol i funktion slet_ordre. Søg 20171031
// 2017.11.01 PHR - Hvis nextfaktdate blev sat til 0.11.17 gik rutinen i selvsving #20171101
// 2018.05.02 PHR - Hack for at scanner skipper det 1. 0 hvis 13 EAN stregkode starter med 00. Søg efter '0$varenr'
// 2018.05.09 PHR - Omskrivning af shop update rutine i så den fungerer med 'rigtige' stregkoder. Søg 20180509
// 2018.06.29 PHR - Ny funktion. registrer_betaling. Til minimering af fejl fra kortterminal.
// 2018.06.29 PHR - Find_kostpriser. Fjernet '>0' da den returnedere forkert kostpris på negativt antal på loppevarer   Søg 20180629
// 2018.08.15 PHR - Function krediter_pos: samlet pris nu incl moms. 20180815.
// 2018.08.16 PHR - Function registrer_betaling: Skrives nu i log hvis aktiveret 20180816
// 2018.08.24 PHR - Function registrer_betaling: Betaling registreres som indbetaling hvis der er konto_id og ordresum=0. 20180824
// 2018.08.24 PHR - Function krediter_pos: $samlet_pris=$b; udkommenteret da $samlet_pris bruges til samlet pris. 20180824.
// 2018.09.11 PHR - Ved dagsafslutning (POS) blev undertiden bog på forkert konto  20180912
// 2018.09.12 PHR - Debet blev negativ i transaktioner ved udbetaling til kreditor fra POS. 20180912
// 2018.09.13 PHR - Tilføjet mulighed for at trække levering tilbage ved at sætte negativt antal i 'lever' på ordre 20180913.
// 2018.09.14 PHR - Webordrer bliver 'straksbogført' for bizsys_49. Skal ændres til valg snart 20180914
// 2018.11.18 PHR - Div oprydning.
// 2018.11.27 PHR - Kontanthævning med 0 i sum og beløb på dankort blev ikke bogført, men kassediff til følge 20181127
// 2018.11.28 PHR - Funk bogfor_nu. Opslag efter korrekt bogf.konto ved webordrer med kortbet. på systemer,
//                  hvor salgsordre bruges som POS 20181128
// 2018.12.06 PHR - Funk bogfor_nu. Afdeling findes nu i ordrer. 20181206
// 2018.12.10 CA  - Gavekort understøttes når gavekortnummer og beløb er angivet. 20181210
// 2018.12.23 PHR	- Sikring mod indsættelse og levering af variantvarer uden variantinfo. 20181223
// 2019.01.04 PHR	- Function kontoopslag: Oprettelse af debitor direkte fra ordre. create_debtor mm.
// 2019.01.11 PHR	- Func opret_orderlinje. Fjernet "$art=='PO' &&" da der bliver lagt moms på pris når pris skrives før 'Enter' i ordrer 20190111
// 2019.01.16 MSC - Rettet Kunder - Ny ordre til Ny ordre
// 2019.01.16 PHR	- Udbetalinger til kreditor fra POS bogføres nu på korrekt samlekonto. 20190116 
// 2019.01.22 PHR	- Webordrer konteres nu på korrekt finanskontonr. 20190116 
// 2019.01.24 PHR - Korrekt fordeling af moms på respektive konti ved bogføring af POS $vatAccount eller $vatAmount
// 2019.02.12 MSC - Rettet topmenu design til
// 2019.02.20 PHR - Moms bogføres kun hvis der er moms 20190220
// 2019.02.25 PHR - $rabat changed to $lineDiscount. Temporary variable to hold the discount of an assembled product.
// 2019.03.11 PHR - PHR Added 'afrund' as 0.00099 in diff was not found. 20190311 
// 2019.03.12 MSC - Rettet isset fejl
// 2019.03.15 PHR - Added resurs in function 'batch' to avoid error om orderlines without 'vare_id' 20190315
// 2019.03.18 PHR -	Added brackets around '$del1 or $del2 or $del3' as the query was falty and returned wrong result #20190318
// 2019.04.21 PHR - Enhanged routine to aviod dublets of invoice number, in function 'bogfor'. 20190421
// 2019.04.28 PHR - Function 'bogfor_nu'. Added  "and box2 != '0.00'" as checking account w/o vat is not necessary.  20190428
// 2019.05.20 PHR - Changed GLS label to include Contact ID. $gls_ctId   
// 2019.06.21 PHR - function mrabat. Cost is corrected on orderline for items with 'cost as percent of salesprice' 
// 2019.07.29 PHR - function batch_salg: array_multisort sometimes makes a sorting error when more than 2 arrays. script changed, to sorts 2 		arrays and query the last afterwards.  20190729 
// 2019.08.09 PHR	- function vareopslag. Created searchfunction wo avoid all items to be listed which is cpu extensive. Look for $findStr
// 2019.09.01 PHR	- function bogfor_nu. Changed VAT handling from handling vat as a single post per order to making relation between each financial transaction and the belonging vat transaction 
// 2019.10.01 PHR - function bogfor_nu. Enhanched VAT handling
// 2019.11.05 PHR - function vareopslag. Added quantity field to add more items at a time. $insetQty. 
// 2019.11.27 PHR - function bogfor_nu. Changed vare_id to bogf_konto as qty.rebate has vare_id'0' 	 #20191127
// 									and moved '$linjemoms' line beneath '}'
// 2020.01.09 PHR - function opret_ordrelinje. Added $folger as 'tilfravalg' was added to former item if same item ID, even if
//									tilfravalg was not chosen on former item - 20200109
// 2020.03.12 PHR - function 'opret_saet'.  Added 'order by posnr' in 'stykliste' query


function levering($id,$hurtigfakt,$genfakt,$webservice) {
echo "<!--function levering start-->";
#cho "$id,$hurtigfakt,$genfakt,$webservice<br>";
# Denne funktion kontrollerer levering of kalder funktioner som registrerer salget i tabellerne varer,batch_salg og ect batch_kob

global $afd_lager;
global $regnaar;
global $levdate;
global $lev_nr;
global $db,$db_skriv_id;

$fejl=0;
$lager=array();
#$fp=fopen("../temp/ordrelev.log","a");
$r=db_fetch_array(db_select("select afd,art,ref from ordrer where id = $id",__FILE__ . " linje " . __LINE__));
$afd=$r['afd'];
$art=$r['art'];
$ref=$r['ref'];
if ($afd==''){
	if ($r['art']=='PO' && $r['felt_5']) { #20161011
		if ($r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='1'",__FILE__ . " linje " . __LINE__))){
		 $afdelinger=explode(chr(9),$r['box3']);
		 $tmp=$r['felt_5']-1;
		 $afd=$afdelinger[$tmp];
		}
	}
}

$variant_varer=array(); //20181223
$x=0;
$qtxt="SELECT distinct(vare_id) FROM variant_varer";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$variant_varer[$x]=$r['vare_id'];
	$x++;
}

if ($afd==''){
	if ($ref) {
		$r=db_fetch_array(db_select("select ansatte.afd from ansatte where navn='$ref'",__FILE__ . " linje " . __LINE__));
		$afd=$r['afd']*1;
		if ($afd=='') {
			$r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn='$ref'",__FILE__ . " linje " . __LINE__));
			$ansat_id=$r['ansat_id']*1;
			$r=db_fetch_array(db_select("select afd from ansatte where id='$ansat_id'",__FILE__ . " linje " . __LINE__));
			$afd=$r['afd']*1;
		$afd=$r['afd']*1;
		}
		db_modify("update ordrer set afd='$afd' where id='$id'",__FILE__ . " linje " . __LINE__);
	}
}
if ($afd) { #20161022
	$r=db_fetch_array(db_select("select box1 from grupper where kodenr='$afd' and art = 'AFD'",__FILE__ . " linje " . __LINE__));
	$afd_lager=$r['box1']*1;
	if (!$afd_lager) {
		$r=db_fetch_array(db_select("select kodenr from grupper where box1='$afd' and art = 'LG'",__FILE__ . " linje " . __LINE__));
		$afd_lager=$r['kodenr']*1;
	}
}
#cho __line__."Afd $afd Lager $lager<br>";
#exit;
#transaktion(commit);
#xit;
$q = db_select("select lev_nr from batch_salg where ordre_id = $id order by lev_nr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($lev_nr<=$r['lev_nr']){
		$lev_nr=$r['lev_nr']+1;
	}
}
if (!$lev_nr) $lev_nr=1;

$x=0;

db_modify("update variant_varer set variant_beholdning=0 where variant_beholdning is NULL",__FILE__ . " linje " . __LINE__);

$q=db_select("select id,lager from ordrelinjer where ordre_id = '$id' and posnr > '0' order by posnr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$x++;
	db_modify("update ordrelinjer set posnr='$x' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
}
$query = db_select("select * from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
$row =db_fetch_array($query);

$ref=$row['ref'];
$levdate=$row['levdate'];
$ordredate=$row['ordredate']; #20150810
$fakturadate=$row['fakturadate'];
$art=$row['art'];

$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
if ($row =db_fetch_array($query)) {
#	$year=substr(str_replace(" ","",$row['box2']),-2); #aendret 060308 - grundet mulighed for fakt i aar 2208
	$year=trim($row['box2']);
	$aarstart=str_replace(" ","",$year.$row['box1']);
#	$year=substr(str_replace(" ","",$row['box4']),-2);
	$year=trim($row['box4']);
	$aarslut=str_replace(" ","",$year.$row['box3']);
}

#if ($hurtigfakt && !$fakturadate) {
#	$fakturadate=date("Y-m-d");
#	db_modify("update ordrer set fakturadate = '$fakturadate' where id = $id",__FILE__ . " linje " . __LINE__);
#}

if ($hurtigfakt && $fakturadate && $fakturadate != $levdate) {
	db_modify("update ordrer set levdate = fakturadate where id = $id",__FILE__ . " linje " . __LINE__);
#cho "update ordrer set levdate = fakturadate where id = $id<br>";
# exit;
	$levdate=$fakturadate;
}
if ($hurtigfakt && $fakturadate && (!$ordredate || $ordredate>$fakturadate)) {
	db_modify("update ordrer set ordredate = fakturadate where id = $id",__FILE__ . " linje " . __LINE__);
	$ordredate=$fakturadate;
}
$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
if ($fakturadate && !$r['levdate']){
	if ($webservice) return('Manglende leveringsdato');  
	else print "<BODY onLoad=\"javascript:alert('Leveringsdato SKAL udfyldes')\">";
	exit;
} else {
	if (!$hurtigfakt && $r['levdate']<$r['ordredate']) {
		 print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato $r[levdate]<$r[ordredate]')\">";
		 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		 exit;
	}
	list ($year, $month, $day) = explode ('-', $r['levdate']);
	$year=trim($year);
	$tmp=date("Y");
	if (!$hurtigfakt && $art!='PO' && !$webservice && !$genfakt && ($year<$tmp-10||$year>$tmp+10)) {
		 print "<BODY onLoad=\"javascript:alert('Tjek leveringsdato $levdate')\">";
		 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		 exit;
	}
	if ($hurtigfakt=='on' && !$fakturadate) {
		$fakturadate=date('Y-m-d');
		db_modify("update ordrer set fakturadate='$fakturadate' where id='$id'",__FILE__ . " linje " . __LINE__);
#		 #print "<meta http-equiv=\"refresh\" content=\"0;URL=fakturadato.php?id=$id&returside=levering.php&hurtigfakt=on\">";
#		exit;
	}
	if ($fejl==0){
		$fakturanr=1;
		$x=0;

		$query = db_select("select * from ordrelinjer where ordre_id = '$id' order by posnr,id",__FILE__ . " linje " . __LINE__);
		while ($row =db_fetch_array($query)){
			if (($row['posnr']>0)&&(strlen(trim(($row['varenr'])))>0)){
				$x++;
				$linje_id[$x]=$row['id'];
				$folgevare[$x]=$row['folgevare'];
				$kred_linje_id[$x]=$row['kred_linje_id'];
				$vare_id[$x]=$row['vare_id'];
				$varenr[$x]=$row['varenr'];
				$antal[$x]=afrund($row['antal'],3); #20131202
				$leveres[$x]=afrund($row['leveres'],3); #20131202
				$pris[$x]=$row['pris'];
				$kostpris[$x]=$row['kostpris'];
				$rabat[$x]=$row['rabat'];
				$nettopris[$x]=$row['pris']-($row['pris']*$row['rabat']/100);
				$serienr[$x]=trim($row['serienr']);
				$posnr[$x]=$row['posnr'];
				$bogf_konto[$x]=$row['bogf_konto'];
				$variant_id[$x]=$row['variant_id']*1;
				if ($hurtigfakt=='on') $leveres[$x]=$antal[$x];
				$lager[$x]=$row['lager']*1;
				if (!$lager[$x]) $lager[$x]=1; 
			}
		}
		$linjeantal=$x;
		
		if (count($variant_varer) && $db != 'bizsys_245') { //20181223
			for ($x=1; $x<=$linjeantal; $x++) {
				if (!$variant_id[$x] && in_array($vare_id[$x],$variant_varer)) {
					alert("Ingen variantinformation for varenr: $varenr[$x], variantvarer skal indsættes med stregkode");
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
					exit;
				}
			}
		}
		for ($x=1; $x<=$linjeantal; $x++) {
			$tidl_lev=0;
			$qtxt="select antal from batch_salg where linje_id = $linje_id[$x]";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
#				echo $r['antal']."<br>";
				$tidl_lev=$tidl_lev+$r['antal'];
			}
			if ($hurtigfakt=='on') $leveres[$x]=$antal[$x]-$tidl_lev;
			if (($antal[$x]>0)&&($antal[$x]<$leveres[$x]+$tidl_lev)) {
				print "<BODY onLoad=\"javascript:alert('Der er sat for meget til levering (pos nr. $posnr[$x])')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				exit;
			}
			if (($leveres[$x]>0)&&($serienr[$x])) {
				$sn_antal[$x]=0;
				$query = db_select("select * from serienr where salgslinje_id = '$linje_id[$x]' and batch_salg_id=0",__FILE__ . " linje " . __LINE__);
				while ($row =db_fetch_array($query)) $sn_antal[$x]++; 
			 if ($leveres[$x]!=$sn_antal[$x]) {
					 print "<BODY onLoad=\"javascript:alert('Der er sat $leveres[$x] til levering men valgt $sn_antal[$x] serienumre (pos nr: $posnr[$x])')\">";
					 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
					 exit;
				}
			}
			if (($leveres[$x]<0)&&($serienr[$x])) {
				$sn_antal[$x]=0;
				if ($art=='DK') $qtxt = "select * from serienr where salgslinje_id = $kred_linje_id[$x]*-1";# 20121001 Rettet KO til DK 
				else $qtxt = "select * from serienr where salgslinje_id <0 and vare_id=$vare_id[$x]";
				$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($row =db_fetch_array($q)) {
					db_modify("insert into serienr (vare_id,kobslinje_id,salgslinje_id,batch_kob_id,batch_salg_id,serienr) values ('$vare_id[$x]','$linje_id[$x]','0','0','0','$row[serienr]')",__FILE__ . " linje " . __LINE__);
					db_modify("update serienr set salgslinje_id=abs(salgslinje_id) where id = '$row[id]'",__FILE__ . " linje " . __LINE__);
					$sn_antal[$x]++;
				}
			 if ($leveres[$x]+$sn_antal[$x]!=0){
					$tmp=$leveres[$x]*-1;
					print "<BODY onLoad=\"javascript:alert('Der er sat $tmp til returnering men valgt $sn_antal[$x] serienumre (pos nr: $posnr[$x])')\">";
					 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
					 exit;
				}
			}
			if ($leveres[$x]<0 && $art == 'DK') {
				 $tidl_lev=0;
				 $query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]' and ordre_id=$id",__FILE__ . " linje " . __LINE__);
				 while($row = db_fetch_array($query)) $tidl_lev=$tidl_lev-$row['antal'];
				 if ($leveres[$x]>$tidl_lev+$antal[$x]) $leveres[$x]=$antal[$x]-$tidl_lev;
			}
		}
			for ($x=1; $x<=$linjeantal; $x++)	{
			$sn_start=0;
			$query = db_select("select * from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
			$row =db_fetch_array($query);
#			$kostpris[$x]=$row['kostpris'];
			$gruppe[$x]=$row['gruppe'];
			if ($row['beholdning']) {$beholdning[$x]=$row['beholdning'];}
			else $beholdning[$x]=0;
			$beholdning[$x]=$beholdning[$x]-$leveres[$x];
			if (trim($row['samlevare'])=='on') {
#				samlevare($id,$art,$linje_id[$x], $vare_id[$x], $leveres[$x])
#				for ($a=1; $a<=$leveres[$x]; $a++) samlevare($vare_id[$x], $linje_id[$x]);
			}
			if (!$gruppe[$x]) {
				print "<BODY onLoad=\"javascript:alert('Vare tilh&oslash;rer ikke nogen varegruppe - kontroller vare og indstillinger! (pos nr: $posnr[$x])')\">";
				if ($art=='PO') print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">";
				else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				exit;
			}
			if ($vare_id[$x] && $leveres[$x])  {
				linjeopdat($id, $gruppe[$x], $linje_id[$x], $beholdning[$x], $vare_id[$x], $leveres[$x], $pris[$x], $nettopris[$x], $rabat[$x], $row['samlevare'], $x, $posnr[$x], $serienr[$x], $kred_linje_id[$x],$bogf_konto[$x],$variant_id[$x],$lager[$x]);
#				if (trim($row['samlevare'])=='on') {
#					$q2 = db_select("select * from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
#					while($r2 =db_fetch_array($q2)) 
#				}
			}
		}
	}
}
#fclose ($fp);
echo "<!--function levering slut-->";
#xit;
return("OK");
} #endfunc levering

#############################################################################################
function linjeopdat($id ,$gruppe, $linje_id, $beholdning, $vare_id, $antal, $pris, $nettopris, $rabat, $samlevare, $linje_nr, $posnr, $serienr, $kred_linje_id,$bogf_konto,$variant_id,$lager){

#cho "($id ,$gruppe, $linje_id, $beholdning, $vare_id, $antal, $pris, $nettopris, $rabat, $samlevare, $linje_nr, $posnr, $serienr, $kred_linje_id,$bogf_konto,$variant_id,$lager)<br>";
#xit;

	# Denne funktion finder de kontonumre fra kontoplanen som de elkelte ordrelinjer skal bogføres på, og tilføjer dem på ordrelinjen 
	# Kaldes fra funktionen levering - 

#cho "Linjeopdat: $antal - $id - $linje_id - $kred_linje_id<br>";

	global $art;
	global $db,$db_skriv_id;
	global $fakturadate,$fp;
	global $lev_nr,$levdate;
	global $ref;
	global $sn_id;

	$antal*=1;

	if (!$levdate) $levdate=date("Y-m-d");
	
// følgende er en forglemmelse i opdat til 3.7.0 og kan fjernes efter opdat til 3.7.1. 
	$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='lagerstatus' and column_name='variant_id'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER TABLE lagerstatus add column	variant_id int",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE shop_varer set saldi_variant='0',shop_variant='0'",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE batch_kob set variant_id='0' where variant_id is NULL",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE batch_salg set variant_id='0' where variant_id is NULL",__FILE__ . " linje " . __LINE__);
		db_modify("UPDATE lagerstatus set variant_id='0' where variant_id is NULL",__FILE__ . " linje " . __LINE__);
	}
	
	
	$query = db_select("select * from grupper where art='VG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__); #VG = Varegruppe
	if ($row =db_fetch_array($query)){
		$box1=trim($row['box1']); $box2=trim($row['box2']); $box3=trim($row['box3']); $box4=trim($row['box4']); $box8=trim($row['box8']); $box9=trim($row['box9']);
	} else {
		$r=db_fetch_array(db_select("select posnr from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__));
		print "<BODY onLoad=\"javascript:alert('Varegruppe ikke opsat korrekt, pos nr $r[posnr]')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	}
	if (!$box3 || !$box4) { # box3 & box4 er kontonumre for varekøb og varesalg
		$fejltekst="Varegruppe $gruppe mangler kontonummer for varek&oslash;b og/eller varesalg (Indstillinger -> Varegrp)";
		print "<BODY onLoad=\"javascript:alert('$fejltekst')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	}
	if ($box8!='on'){ #box 8 angiver om vare(gruppen) er lagerført #20130408
		if($bogf_konto) $box4=$bogf_konto; # hvis funktionen kaldes med en bogføringskonto overruler denne box4
		db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		$qtxt="select sum(antal) as leveret from batch_salg where linje_id='$linje_id'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['leveret'] > 0 && $antal < 0) { #20180913
			$qtxt="select id,antal,batch_kob_id from batch_salg where linje_id='$linje_id' and antal > 0 order by lev_nr desc";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)){
				if ($r['antal'] && $r['antal']+$antal<=0) {
				$qtxt="delete from batch_salg where id = '$r[id]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					if ($r['batch_kob_id']) {
						$qtxt="update batch_kob set rest=rest+$r[antal] where id = '$r[batch_kob_id]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
					$antal+=$r['antal'];
				} elseif ($r['antal'])  {
					$qtxt="update batch_salg set antal=antal+$antal where id = '$r[id]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					if ($r['batch_kob_id']) {
						$qtxt="update batch_kob set rest=rest-$antal where id = '$r[batch_kob_id]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
					$antal=0;
				}
			}
		}
		if ($antal) {
			if (!$levdate) $levdate=date("Y-m-d");
			$qtxt="insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr,lager,variant_id)";
			$qtxt.=" values ";
			$qtxt.="(0, '$vare_id', '$linje_id', '$levdate', '$id', '$antal', '$pris', '$lev_nr','$lager','$variant_id')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	} else {
		$r=db_fetch_array(db_select("select fast_db from ordrelinjer where id='$linje_id'",__FILE__ . " linje " . __LINE__));
		$fast_db=$r['fast_db'];
		if($bogf_konto) $box4=$bogf_konto; 
		db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		$qtxt="update varer set beholdning=$beholdning where id='$vare_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		if ($beholdning < 0) {
			$qtxt="select var_value from settings where var_name = 'DisItemIfNeg' and var_grp = 'varer'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['var_value']) {
				$qtxt="update varer set lukket='1' where id='$vare_id'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		$tmp=$antal*-1;
			$qtxt="select id,beholdning from lagerstatus where vare_id='$vare_id' and variant_id = '$variant_id' and lager='$lager'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); #20161011 + næste 2
			if ($lager > 0) $ny_beh=$r['beholdning']+$tmp;
			else $ny_beh=$beholdning;
		if ($r['id']) {
			$qtxt="delete from lagerstatus where vare_id='$vare_id' and variant_id = '$variant_id' and lager='$lager' and id !='$r[id]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="update lagerstatus set beholdning='$ny_beh' where id='$r[id]'";
		}
		else $qtxt="insert into lagerstatus (vare_id,variant_id,beholdning,lager) values ('$vare_id','$variant_id','$tmp','$lager')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		if ($variant_id) {
			db_modify("update variant_varer set variant_beholdning=variant_beholdning+$tmp where id='$variant_id'",__FILE__ . " linje " . __LINE__);
		}
		if ($box9=='on') { # #box 9 angiver om vare(gruppen) er underlagt batchkontrol
			if ($antal<0) krediter($id, $levdate, $beholdning, $vare_id, $antal*-1, $pris, $linje_id, $serienr, $kred_linje_id,$lager,$variant_id);
			else batch_salg_lev($id, $levdate, $fakturadate, $beholdning, $vare_id, $antal, $pris, $nettopris, $linje_id, $linje_n, $posnr, $serienr, $lager,$variant_id);
		} else {
			if($bogf_konto) $box4=$bogf_konto; 
			$qtxt="update ordrelinjer set bogf_konto=$box4 where id='$linje_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			if ($art=='DK' || $antal < 0) {
				$tmp=$antal*-1;
				$bk_id=0;
				if ($kred_linje_id) $qtxt="select * from batch_salg where linje_id = '$kred_linje_id' order by id desc"; #20170507
				else $qtxt="select * from batch_salg where linje_id = '$linje_id' and batch_kob_id != 0  order by id desc";
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r=db_fetch_array($q)) {
						$bs_antal=$r['antal'];
						$bs_id=$r['id'];
						$bk_id=$r['batch_kob_id']; # 20170507
						$qtxt="select antal,rest from batch_kob where id = '$bk_id'"; #20170507
						$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$tmp2 = $r2['antal']-$r2['rest']; #$tmp2 er det antal det kan lægges tilbage på linjen.
						$ny_rest=$r2['rest'];
						if ($tmp && $tmp2) { 
							if ($tmp2>=$tmp) { #Så kan alle være på samme linje
								$ny_rest=$r2['rest']+$tmp; 
								$tmp=0;
							} elseif ($tmp2) { #alle kan ikke være på samme linje så vi lægger det vi kan og går videre til næste linje.
								$ny_rest=$r2['antal']; 
								$tmp-=$tmp2;
							}
							if (!$kred_linje_id) {
								$qtxt="update batch_kob set rest='$ny_rest' where id = '$bk_id'";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);  
								if ($bs_antal-$tmp2==0) $qtxt="delete from batch_salg where id='$bs_id'";
								else $qtxt="update batch_salg set antal=antal+$ny_rest where id = '$bs_id'";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);  
							}
						}	
					}
					if ($kred_linje_id) {
						$bk_id*=1;
						$tmp2=$tmp*-1;
						$qtxt="insert into batch_salg(batch_kob_id,vare_id,linje_id,salgsdate,ordre_id,antal,pris,lev_nr,lager,variant_id)";
						$qtxt.=" values ";
						$qtxt.="('$bk_id', '$vare_id', '$linje_id', '$levdate', '$id', '$antal', '$pris', '$lev_nr','$lager','$variant_id')"; #20170529.
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$tmp=0;
					}

				if ($tmp) { # F.eks negativt salg uden købsreference
					$r=db_fetch_array(db_select("select kostpris from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
					$kostpris=$r['kostpris']*1;
					$tmp2=$tmp*-1;
					db_modify("update ordrelinjer set kostpris='$kostpris' where id ='$linje_id'",__FILE__ . " linje " . __LINE__);
					db_modify("insert into batch_kob(vare_id, linje_id, ordre_id, antal,rest,pris,lager,variant_id) values ('$vare_id', '0', '0','0','$tmp','$kostpris','$lager','$variant_id')",__FILE__ . " linje " . __LINE__);
					$r=db_fetch_array(db_select("select max(id) as id from batch_kob where vare_id='$vare_id' and linje_id='0'",__FILE__ . " linje " . __LINE__));
					db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr,lager,variant_id) values ('0', '$vare_id', '$linje_id', '$levdate', '$id', '$tmp2', '$pris', '$lev_nr','$lager','$variant_id')",__FILE__ . " linje " . __LINE__);
				}
			} else {
				$tmp=$antal;
			$qtxt="select * from batch_kob where vare_id = '$vare_id' and rest > '0' and lager = '$lager' order by id";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while($r=db_fetch_array($q)) {
					$ny_rest=$r['rest'];
					if ($tmp && $ny_rest) { 
						if ($ny_rest>=$tmp) { #Så kan alle være på samme linje
							$ny_rest=$ny_rest-$tmp; 
						$qtxt="insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr,lager,variant_id)";
						$qtxt.=" values ";
						$qtxt.="('$r[id]', '$vare_id', '$linje_id', '$levdate', '$id', '$tmp', '$pris', '$lev_nr','$lager','$variant_id')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							$tmp=0;
						} else { #alle kan ikke være på samme linje så vi lægger det vi kan og går videre til næste linje.
						if ($ny_rest){
							$qtxt="insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr,lager,variant_id)";
							$qtxt.=" values ";
							$qtxt.="('$r[id]', '$vare_id', '$linje_id', '$levdate', '$id', '$ny_rest', '$pris', '$lev_nr','$lager','$variant_id')";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						}
							$tmp-=$ny_rest;
							$ny_rest=0; 
 						}
					}
					db_modify("update batch_kob set rest='$ny_rest' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);  
				}
				if ($tmp) {
					$r=db_fetch_array(db_select("select kostpris from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
					$kostpris=$r['kostpris']*1;
					db_modify("update ordrelinjer set kostpris='$kostpris' where id ='$linje_id'",__FILE__ . " linje " . __LINE__);
					$tmp2=$tmp*-1;
					$qtxt="insert into batch_kob(vare_id, linje_id, ordre_id, antal,rest,pris,lager,variant_id) ";
					$qtxt.=" values ";
					$qtxt.="('$vare_id', '0', '0','0','$tmp2','$kostpris','$lager','$variant_id')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$r=db_fetch_array(db_select("select max(id) as id from batch_kob where vare_id='$vare_id' and linje_id=0",__FILE__ . " linje " . __LINE__));
					$qtxt="insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr,lager,variant_id)";
					$qtxt.=" values ";
					$qtxt.="('$r[id]', '$vare_id', '$linje_id', '$levdate', '$id', '$tmp', '$pris', '$lev_nr','$lager','$variant_id')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	sync_shop_vare($vare_id,$variant_id,$lager); # std_func. 
/* 
	if ($box8=='on') {
		$qtxt="select box4 from grupper where art='API'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$api_fil=trim($r['box4']);
#cho __line__." $api_fil<br>";		
		if ($api_fil) { #20170210
		$log=fopen("../temp/$db/rest_api.log","a");
			$header="User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
			if ($variant_id) {
#	cho __line__." $variant_id<br>";
				$qtxt="select shop_variant from shop_varer where saldi_variant='$variant_id'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$shop_id=$r['shop_variant'];
				$qtxt="select beholdning from lagerstatus where variant_id='$variant_id'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$variant_beholdning=$r['beholdning'];#-$antal;
				if (!$shop_id) {
					$qtxt="select variant_stregkode from variant_varer where id='$variant_id'";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$shop_id=str_replace("EAN","",$r['variant_stregkode']);
				}
				$qtxt="select shop_id from shop_varer where saldi_variant='$variant_id'";
#				$txt="/usr/bin/wget --spider --no-check-certificate --header='$header' '$api_fil?update_stock=$shop_id&stock=$variant_beholdning&stockno=$lager&stockvalue=$r[lagerbeh]'";
#				$qtxt="select variant_varer.variant_stregkode,variant_varer.variant_beholdning as beholdning,lagerstatus.beholdning as lagerbeh ";
#				$qtxt.="from variant_varer,lagerstatus  where variant_varer.id='$variant_id' and lagerstatus.variant_id=variant_varer.id";
#				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#				$shop_id=str_replace("EAN","",$r['variant_stregkode']);
#				$variant_beholdning=$r['beholdning'];#-$antal;
				$txt="/usr/bin/wget --spider --no-check-certificate --header='$header' '$api_fil?update_stock=$shop_id&stock=$variant_beholdning&stockno=$lager&stockvalue=$r[lagerbeh]'";
				fwrite($log,__file__." ".__line__." $txt\n");
				exec ("nohup $txt > /dev/null 2>&1 &\n");
			} else {
				$qtxt="select varer.beholdning,lagerstatus.beholdning as lagerbeh,shop_varer.shop_id from lagerstatus,shop_varer,varer where lagerstatus.vare_id='$vare_id' and lagerstatus.lager='$lager' and shop_varer.saldi_id='$vare_id' and varer.id='$vare_id'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$txt="/usr/bin/wget --spider --no-check-certificate --header='$header' '$api_fil?update_stock=$r[shop_id]";
				$txt.="&stock=$r[lagerbeh]&stockno=$lager'"; # resten bruges vist ikke: &stockvalue=$r[lagerbeh]'";
				fwrite($log,__file__." ".__line__." $txt\n");
				exec ("/usr/bin/nohup $txt > /dev/null 2>&1 &\n");
			}	
			fclose($log);
		} else { # skal udfases
			$r=db_fetch_array(db_select("select box2 from grupper where art = 'DIV' and kodenr = '5' ",__FILE__ . " linje " . __LINE__));
			$shopurl=trim($r['box2']);
			if (strlen($shopurl)>1) { #20130813 + 20131001
				global $db_id;
				$r=db_fetch_array($q=db_select("select shop_id from shop_varer where saldi_id='$vare_id'",__FILE__ . " linje " . __LINE__));
				$shop_id=$r['shop_id'];
				$r=db_fetch_array(db_select("select varenr,beholdning,publiceret from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
				$shop_beholdning=$r['beholdning'];
				$publiceret=$r['publiceret'];
				$varenr=$r['varenr'];
				if ($shop_id || $publiceret) {
					$r=db_fetch_array(db_select("select sum(ordrelinjer.antal-ordrelinjer.leveret) as antal from ordrer,ordrelinjer where ordrelinjer.vare_id = '$vare_id' and ordrelinjer.ordre_id = ordrer.id and (ordrer.art='DO' or ordrer.art='DK') and (ordrer.status='1' or ordrer.status='2') and ordrer.id!='$id'",__FILE__ . " linje " . __LINE__));
					$shop_beholdning-=$r['antal'];
					if (strpos($shopurl,'/?')) {
						$saldiurl="://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
						if ($_SERVER['HTTPS']) $saldiurl="s".$saldiurl;
						$saldiurl="http".$saldiurl;
						$url=str_replace("/?","/opdat_behold.php?vare_id=$vare_id&varenr=$varenr&saldiurl=$saldiurl&shop_id=$shop_id&beholdning=$shop_beholdning&",$shopurl);
					}
					else $url=$shopurl."/opdat_beholdning.php?vare_id=$vare_id&shop_id=$shop_id&beholdning=$shop_beholdning";
					print "<body onload=\"javascript:window.open('$url','opdat:beholdning');\">";
				}
			}
		}
	}
*/
#xit;
	$qtxt="update ordrelinjer set leveret = leveret+$antal,leveres=0 where id='$linje_id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
} # endfunc linjeopdat

#############################################################################################

function bogfor_levering($id,$gruppe,$linje_id,$antal,$box1,$box2,$box3,$box4) {

# Denne funktion bruges ikke.....

	global $levdate;
	global $ref;
	$r=db_fetch_array(db_select("select * from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__));
	$amount=$r['kostpris']*$antal; # OBS antal maa ikke hentes fra ordrelinjer da alt ikke nodvendigvis leveres 
	$projekt=$r['projekt']*1;
	$ansat=$r['ansat']*1;
	$afd=0;
	$beskrivelse="Levering ordre id $id";

	$r=db_fetch_array(db_select("select beholdning from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
	$beholdning=$r['beholdning'];
	
	if ($beholdning<$antal) $antal=$beholdning; #lagerværdi flyttes til varekøb for den del af leveringen som ér på lager

	if ($amount>0) {
		$konto1=$box2;
		$konto2=$box4;
	}	else {
		$konto1=$box2;
		$konto2=$box4;
		$amount*-1;
	}
	$logdate=date("Y-m-d");
	$logtime=date("H:i");

#cho "insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0','$levdate','$beskrivelse','$konto1','--','0','$amount','0','$afd','$logdate','$logtime','$projekt','$ansat','$id')<br>";
	db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('0','$levdate','$beskrivelse','$konto1','--','0','$amount','0','$afd','$logdate','$logtime','$projekt','$ansat','$id')",__FILE__ . " linje " . __LINE__);
#cho "insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0','$levdate','$beskrivelse','$konto2','--','$amount','0','0','$afd','$logdate','$logtime','$projekt','$ansat','$id')<br>";
	db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id) values ('0','$levdate','$beskrivelse','$konto2','--','$amount','0','0','$afd','$logdate','$logtime','$projekt','$ansat','$id')",__FILE__ . " linje " . __LINE__);
} # endfunc bogfor_levering

function batch_salg_lev($id, $levdate, $fakturadate, $beholdning, $vare_id, $antal, $pris, $nettopris, $linje_id, $linje_nr, $posnr, $serienr, $lager,$variant_id){

	# Denne funktion bruges til ved levering af varer som er underlagt batchkontrol 
	# Kaldes fra funktionen linjeopdat... 

	global $sn_id;
	global $lev_nr;
	global $fp;

	$rest=$antal;
	$sn_start=0;
	$kobsbelob=0;
	$a=0;
	$res_sum=0;
	$res_linje_antal=0;

	if (!db_fetch_array(db_select("select * from reservation where linje_id = $linje_id",__FILE__ . " linje " . __LINE__))) batch($linje_id);  #Hvis der ikke manuelt er reserveret varer tages automatisk fra den ldste indkbsordre
	$query = db_select("select * from reservation where linje_id = $linje_id",__FILE__ . " linje " . __LINE__); #Finder reserverede varer som er koebt hjem
	while (($row =db_fetch_array($query))&&($res_sum<$antal)) {
		$x++;
		$batch_kob_id[$x]=$row['batch_kob_id'];
		$res_antal[$x]=$row['antal'];
		$res_sum=$res_sum+$row['antal'];
		$lager=$row['lager'];
		if ($res_sum>=$antal){  #Indsat 091106 for
			$diff[$x]=$res_sum-$antal;
			$res_antal[$x]=$res_antal[$x]-$diff[$x];
			$res_sum=$antal;
		}
	}
	$res_linje_antal=$x;
	$rest=$rest-$res_sum;

	if ($rest>0) {  #Hvis ikke alle varer er koebt hjem eller reserveret saaaa....
		if ($r=db_fetch_array(db_select("select * from reservation where batch_salg_id = $linje_id*-1 and antal = $rest",__FILE__ . " linje " . __LINE__))) { #Finder reserverede varer som er bestilt hos lev.
			#Hvis linjen eksisterer indsættes en linje i batch_salg 
			db_modify("insert into batch_salg(vare_id, linje_id, salgsdate, ordre_id, antal, lev_nr,variant_id) values ($vare_id, $linje_id, '$levdate', $id, $rest, '$lev_nr','$variant_id')",__FILE__ . " linje " . __LINE__);
			$q2 = db_select("select id from batch_salg where vare_id=$vare_id and linje_id=$linje_id and salgsdate='$levdate' and ordre_id=$id and antal=$rest and	lev_nr='$lev_nr' order by id desc",__FILE__ . " linje " . __LINE__);
			$r2 =db_fetch_array($q2);
			$batch_salg_lev_id=$r2['id']; #Reservationen opdateres med ID fra batch salg   
			db_modify("update reservation set batch_salg_id='$batch_salg_lev_id' where batch_salg_id=$linje_id*-1",__FILE__ . " linje " . __LINE__);
			lagerstatus($vare_id, $variant_id, $lager, $rest); 
		} else {
			print "<BODY onLoad=\"javascript:alert('Reserveret antal stemmer ikke overens med antal til levering (pos nr: $posnr)')\">";
			$r2 =db_fetch_array(db_select("select art from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__)); #20131105
			if ($r2['art']=='PO') print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">";
			else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	}
	else $rest=$antal;

	for ($x=1; $x<= $res_linje_antal; $x++) {
		$query = db_select("select * from batch_kob where id=$batch_kob_id[$x]",__FILE__ . " linje " . __LINE__);
		if ($row =db_fetch_array($query)) {
			$kob_antal=$row['antal'];
			$kob_rest=$row['rest'];
			$kob_ordre_id=$row['ordre_id'];
			$kob_pris=$row['pris'];
			$lager=$row['lager'];
			if (!$kob_pris) {$kob_pris='0';}
			$kob_rest=$kob_rest-$res_antal[$x];
#cho "A update batch_kob set rest=$kob_rest where id=$batch_kob_id[$x]<br>";
			db_modify("update batch_kob set rest=$kob_rest where id=$batch_kob_id[$x]",__FILE__ . " linje " . __LINE__);
			db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, lev_nr,variant_id) values ($batch_kob_id[$x], $vare_id, $linje_id, '$levdate', $id, $res_antal[$x], '$lev_nr','$variant_id')",__FILE__ . " linje " . __LINE__);
			$query2 = db_select("select id from batch_salg where batch_kob_id=$batch_kob_id[$x] and vare_id=$vare_id and linje_id=$linje_id and salgsdate='$levdate' and ordre_id=$id and antal=$res_antal[$x] and	lev_nr='$lev_nr' order by id desc",__FILE__ . " linje " . __LINE__);
			$row2 =db_fetch_array($query2);
			if ($serienr) {db_modify("update serienr set batch_salg_id=$row2[id] where salgslinje_id=$linje_id",__FILE__ . " linje " . __LINE__);}
			db_modify("update ordrelinjer set leveres='0' where id='$linje_id'",__FILE__ . " linje " . __LINE__);
			if ($diff[$x]) db_modify("update reservation set antal='$diff[$x]' where linje_id='$linje_id' and vare_id='$vare_id' and batch_kob_id='$batch_kob_id[$x]'",__FILE__ . " linje " . __LINE__);
			else db_modify("delete from reservation where linje_id='$linje_id' and vare_id='$vare_id' and batch_kob_id='$batch_kob_id[$x]'",__FILE__ . " linje " . __LINE__);
			lagerstatus($vare_id, $variant_id, $lager, $rest);
			$rest=0;
		}	else {
			print "<BODY onLoad=\"javascript:alert('Hmm - Indkbsordre kan ikke findes - levering kan ikke foretages - Kontakt systemadministrator')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	}
} # endfunc batch_salg_lev
###############################################################
function lagerstatus ($vare_id, $variant_id, $lager, $antal) {
	global $ref;

	# Denne funktion bruges til regulering af lagerbeholdning i tilfælde hvor der er flere lagre 
	# Kaldes fra funktionen batch_salg_lev... 


	if (!$lager) {
		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'",__FILE__ . " linje " . __LINE__))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager=$row['kodenr'];}
		}
	}
	$lager=$lager*1;

	$query = db_select("select * from lagerstatus where vare_id='$vare_id' and variant_id='$variant_id' and lager='$lager'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$tmp=$row['beholdning']-$antal;
		db_modify("update lagerstatus set beholdning=$tmp where id=$row[id]",__FILE__ . " linje " . __LINE__);
	} else { #20140821
		$tmp=$antal*-1;
		db_modify("insert into lagerstatus (vare_id, variant_id, lager, beholdning) values ('$vare_id','$variant_id','$lager','$tmp')",__FILE__ . " linje " . __LINE__);
	}
}
###############################################################
function krediter($id,$levdate,$beholdning,$vare_id,$antal,$pris,$linje_id,$serienr,$kred_linje_id,$lager,$variant_id) {

#cho "$id,$levdate,$beholdning,$vare_id,$antal,$pris,$linje_id,$serienr,$kred_linje_id,$lager<br>";

	global $sn_id;
	global $lev_nr;
	global $fp;

	$rest=$antal;
	$sn_start=0;
	$kobsbelob=0;
	$a=0;
	$res_sum=0;

	$row =db_fetch_array(db_select("select posnr, kred_linje_id from ordrelinjer where id='$linje_id'",__FILE__ . " linje " . __LINE__));
	$kred_linje_id=$row['kred_linje_id'];
	$posnr=$row['posnr'];
	
	$x=0;
	$q = db_select("select * from batch_salg where linje_id=$kred_linje_id order by id desc",__FILE__ . " linje " . __LINE__);
	while ($r =db_fetch_array($q)) {
		$x++;
		$batch_kob_id[$x]=$r['batch_kob_id'];
		$batch_kob_antal[$x]=$r['antal'];
		if ($batch_kob_antal[$x]>$antal) $batch_kob_antal[$x]=$antal;
		if (!$batch_kob_id[$x]) {
			?>
				<script language="Javascript">
				<!--
				alert ("Der er observeret en uoverensstemmelse mellem mellem oprindelig ordre og denne (pos nr: <?php echo $posnr ?>)\nRapporter venligst til udviklingsteamet.  mail: fejl@saldi.dk")
				//-->
				</script>
			<?php
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		} 
	}
	if (count($batch_kob_id)) {
		db_modify("update batch_kob set rest = rest+$antal where id ='$batch_kob_id[1]'",__FILE__ . " linje " . __LINE__);
	}	
	$tmp=$antal*-1;	
	db_modify("insert into batch_salg(vare_id, linje_id, salgsdate, ordre_id, antal,variant_id) values ($vare_id, $linje_id, '$levdate', $id, $tmp,'$variant_id')",__FILE__ . " linje " . __LINE__);
	#	db_modify("insert into batch_kob(vare_id, linje_id, kobsdate, ordre_id, antal, rest) values ($vare_id, $linje_id, '$levdate', $id, $antal, $antal)",__FILE__ . " linje " . __LINE__);
#	$r=db_fetch_array(db_select("select max(id) as id from batch_kob where linje_id=$linje_id",__FILE__ . " linje " . __LINE__));
#	$q = db_select("select id from batch_kob where linje_id=$kred_linje_id",__FILE__ . " linje " . __LINE__);
#	$batch_kob_id=$r['id'];
	lagerstatus($vare_id, $variant_id, $lager,-$antal);
	if ($serienr || $serienr=='0') {
		$q = db_select("select * from serienr where salgslinje_id=-$kred_linje_id",__FILE__ . " linje " . __LINE__);
		while ($r =db_fetch_array($q)) {
			$serienr=$r['serienr'];
			db_modify("insert into serienr (kobslinje_id, vare_id, batch_kob_id, serienr, batch_salg_id, salgslinje_id) values ('$linje_id','$vare_id', $batch_kob_id, '$r[serienr]','0','0')",__FILE__ . " linje " . __LINE__);
		}
	}
#xit;
} # endfunc krediter

###############################################################
function krediter_pos($id) {
	global $brugernavn;
	global $vis_saet; #20170622

	$id*=1;
	if (!$id) { #20141105
		return(0); 
		exit;
	}
	$r=db_fetch_array(db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['konto_id']*1;
	$firmanavn=db_escape_string($r['firmanavn']);
	$addr1=db_escape_string($r['addr1']);
	$addr2=db_escape_string($r['addr2']);
	$postnr=db_escape_string($r['postnr']);
	$bynavn=db_escape_string($r['bynavn']);
	$land=db_escape_string($r['land']);
	$kontakt=db_escape_string($r['kontakt']);
	$email=db_escape_string($r['email']);
	$mail_fakt=db_escape_string($r['mail_fakt']);
	$udskriv_til=db_escape_string($r['udskriv_til']);
	$kundeordnr=db_escape_string($r['kundeordnr']);
	$lev_navn=db_escape_string($r['lev_navn']);
	$lev_addr1=db_escape_string($r['lev_addr1']);
	$lev_addr2=db_escape_string($r['lev_addr2']);
	$lev_postnr=db_escape_string($r['lev_postnr']);
	$lev_bynavn=db_escape_string($r['lev_bynavn']);
	$lev_kontakt=db_escape_string($r['lev_kontakt']);
	$ean=db_escape_string($r['ean']);
	$institution=db_escape_string($r['institution']);
	$betalingsbet=db_escape_string($r['betalingsbet']);
	$betalingsdage=$r['betalingsdage']*1;
	$kontonr=db_escape_string($r['kontonr']);
	$cvrnr=db_escape_string($r['cvrnr']);
	$art=db_escape_string($r['art']);
	$valuta=db_escape_string($r['valuta']);
	$valutakurs=$r['valutakurs']*1;
	$sprog=db_escape_string($r['sprog']);
	$ordredate=db_escape_string($r['ordredate']);
	$levdate=db_escape_string($r['levdate']);
	$fakturadate=db_escape_string($r['fakturadate']);
	$notes=db_escape_string($r['notes']);
	$ordrenr=$r['ordrenr']*1;
	$sum=$r['sum']*-1;
	$momssats=$r['momssats']*1;
#	$ref=db_escape_string($r['ref']);
	$fakturanr=$r['fakturanr']*1;
	$kred_ord_id=$r['kred_ord_id']*1;
	$lev_adr=db_escape_string($r['lev_adr']);
	$kostpris=$r['kostpris']*-1;
	$moms=$r['moms']*-1;
	$hvem=db_escape_string($r['hvem']);
	$uxtid=db_escape_string($r['uxtid']);
	$pbs=db_escape_string($r['pbs']);
	$mail=db_escape_string($r['mail']);
	$mail_cc=db_escape_string($r['mail_cc']);
	$mail_bcc=db_escape_string($r['mail_bcc']);
	$mail_subj=db_escape_string($r['mail_subj']);
	$mail_text=db_escape_string($r['mail_text']);
	$felt_1=db_escape_string($r['felt_1']);
	$felt_2=db_escape_string($r['felt_2']);
	$felt_3=db_escape_string($r['felt_3']);
	$felt_4=db_escape_string($r['felt_4']);
	$felt_5=db_escape_string($r['felt_5']);
	$vis_lev_addr=db_escape_string($r['vis_lev_addr']);
	$betalt=db_escape_string($r['betalt']);
	$projekt=db_escape_string($r['projekt']);
	$bordnr=$r['nr']; # 20141114
	$samlet_pris=$sum+$moms; #20180815
	
	if ($bordnr || $bordnr == '0') { # 20141114 
		$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr='2'",__FILE__ . " linje " . __LINE__)); 
		($r['box7'])?$bord=explode(chr(9),$r['box7']):$bord=NULL; #20140508
	
		if ($bord != NULL) { # 20141118
			$optaget=array();
			$x=0;
			$q=db_select("select id,nr,hvem from ordrer where art = 'PO' and status < 3",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				if ($r['hvem']) {
					$optaget[$x]=$r['nr'];
					$x++;
				} 
			}
			if (in_array($bordnr,$optaget)) {
				for($x=0;$x<count($bord);$x++) {
					if (!in_array($x,$optaget)) {
						$ny_nr=$x;
						$x=count($bord);
					}
				}
			} else ($ny_nr=$bordnr);
		} else $ny_nr=0;
	} else $ny_nr=0;
	if($vis_saet) { # 20170622
		$r=db_fetch_array(db_select("select varenr from ordrelinjer where varenr='R' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
		$rvnr=$r['varenr'];
	}
	$ny_nr*=1;
		db_modify("insert into ordrer (konto_id,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,email,mail_fakt,udskriv_til,kundeordnr,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,ean,institution,betalingsbet,betalingsdage,kontonr,cvrnr,art,valuta,valutakurs,sprog,ordredate,levdate,notes,ordrenr,sum,momssats,status,ref,kred_ord_id,lev_adr,kostpris,moms,hvem,tidspkt,pbs,mail,mail_cc,mail_bcc,mail_subj,mail_text,felt_1,felt_2,felt_3,felt_4,felt_5,vis_lev_addr,betalt,projekt,nr)
		values ('$konto_id','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$email','$mail_fakt','$udskriv_til','$kundeordnr','$lev_navn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt','$ean','$institution','$betalingsbet','$betalingsdage','$kontonr','$cvrnr','$art','$valuta','$valutakurs','$sprog','$ordredate','$levdate','$notes','$ordrenr','$sum','$momssats','1','$brugernavn','$kred_ord_id','$lev_adr','$kostpris','$moms','$hvem','$tidspkt','$pbs','$mail','$mail_cc','$mail_bcc','$mail_subj','$mail_text','$felt_1','$felt_2','$felt_3','$felt_4','$felt_5','$vis_lev_addr','$betalt','$projekt','$ny_nr')
	",__FILE__ . " linje " . __LINE__);
	$mrabat=NULL; #20160815
	$r=db_fetch_array(db_select("select max(id) as id from ordrer where ref = '$brugernavn'",__FILE__ . " linje " . __LINE__));
	$ny_id=$r['id'];
	$q=db_select("select * from ordrelinjer where ordre_id='$id' and posnr >= '0' order by posnr desc",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$posnr=$r['posnr']*1;
		$pris=$r['pris']*1;
		$rabat=$r['rabat']*1;
		$vare_id=$r['vare_id']*1;
		$antal=$r['antal']*-1;
		$leveres=$r['leveres']*1;
		$leveret=$r['leveret']*1;
		$bogf_konto=$r['bogf_konto']*1;
		$kred_linje_id=$r['kred_linje_id']*1;
		$momsfri=db_escape_string($r['momsfri']);
		$kostpris=$r['kostpris']*1;
		$samlevare=db_escape_string($r['samlevare']);
		$rabatgruppe=$r['rabatgruppe']*1;
		$folgevare=$r['folgevare']*1;
		$m_rabat=0; # 20161027
		$beskrivelse=db_escape_string($r['beskrivelse']);
		$bogfort_af=db_escape_string($r['bogfort_af']);
		$enhed=db_escape_string($r['enhed']);
		$hvem=db_escape_string($r['hvem']);
		$lev_varenr=db_escape_string($r['lev_varenr']);
		if ($vis_saet && strpos($lev_varenr,'|')) { #20170622
			list($a,$b)=explode('|',$lev_varenr);
			if (is_numeric($a) && is_numeric($b)) {
				$a*=-1;
				$b*=-1;
				$lev_varenr=$a.'|'.$b;
		#		$samlet_pris=$b; #20180824
			}
		}
		$oprettet_af=db_escape_string($r['oprettet_af']);
		$serienr=db_escape_string($r['serienr']);
		$tidspkt=db_escape_string($r['tidspkt']);
		$varenr=db_escape_string($r['varenr']);
		$momssats=$r['momssats']*1;
		$projekt=db_escape_string($r['projekt']);
		$variant_id=$r['variant_id']*1;
		$kdo=db_escape_string($r['kdo']);
		$rabatart=db_escape_string($r['rabatart']);
		$lager=$r['lager']*1;
		$saet=$r['saet']*1;
		$fast_db=$r['fast_db']*1;	
		$qtxt="insert into ordrelinjer ";
		$qtxt.="(posnr,pris,rabat,ordre_id,vare_id,antal,leveres,leveret,bogf_konto,kred_linje_id,momsfri,kostpris,samlevare,rabatgruppe,";
		$qtxt.="folgevare,m_rabat,beskrivelse,bogfort_af,enhed,hvem,lev_varenr,oprettet_af,serienr,tidspkt,varenr,momssats,projekt,variant_id,";
		$qtxt.="kdo,rabatart,saet,fast_db,lager)";
		$qtxt.=" values "; 
		$qtxt.="('$posnr','$pris','$rabat','$ny_id','$vare_id','$antal','$leveres','$leveret','$bogf_konto','$kred_linje_id','$momsfri','$kostpris','$samlevare','$rabatgruppe',";
		$qtxt.="'$folgevare','0','$beskrivelse','$bogfort_af','$enhed','$hvem','$lev_varenr','$oprettet_af','$serienr','$tidspkt','$varenr','$momssats','$projekt','$variant_id',";
		$qtxt.="'$kdo','$rabatart','$saet',$fast_db,$lager)";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	return($ny_id.";".$samlet_pris); # 20170622 tilføjet '.";".$samlet_pris' 
} # endfunc krediter_pos

###############################################################
function batch ($linje_id)
{
	$lager=$valgt=NULL;

	$leveres=0;
	$query = db_select("select * from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$antal=$row['antal']*1;
		$leveres=$row['leveres']*1;
		$posnr=$row['posnr'];
		$vare_id=$row['vare_id']*1;
		$varenr=$row['varenr'];
		$serienr=$row['serienr'];
		$query = db_select("select status, art, konto_id, ref from ordrer where id = '$row[ordre_id]'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$konto_id=$row['konto_id'];
		$status=$row['status'];
		$art=$row['art'];

		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$row[ref]'",__FILE__ . " linje " . __LINE__))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager=$row['kodenr']*1;}
		}
	} else return; #20190315
	
	$query = db_select("select * from batch_salg where linje_id = $linje_id",__FILE__ . " linje " . __LINE__);
	while($row = db_fetch_array($query)) $leveres=$antal-$row['antal'];

	if (($antal>=0)&&($art!="DK")){
		$x=0;
		$rest=array();
		$lev_rest=$leveres;
		if ($lager) $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 and lager = $lager order by kobsdate",__FILE__ . " linje " . __LINE__);
		else $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 order by kobsdate",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$x++;
			$batch_kob_id[$x]=$row['id'];
			$kobsdate[$x]=$row['kobsdate'];
			$rest[$x]=$row['rest'];
			$reserveret[$x]=0;
#			$pris[$x]=$row[pris];
			$q2 = db_select("select ordrenr from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
			$r2 = db_fetch_array($q2);
			$ordrenr[$x]=$r2['ordrenr'];
			$q2 = db_select("select * from reservation where batch_kob_id=$row[id]",__FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				if ($r2['linje_id']!=$linje_id) {$reserveret[$x]=$reserveret[$x]+$r2['antal'];}
				else {
					$valg[$x]=$r2['antal'];
					$valgt.=$r2['antal'];
				}
			}
			$k_ordreantal=$x;
			if (!$valgt) {
				if ($rest[$x]>=$lev_rest) {
					$valg[$x]=$lev_rest;
					$lev_rest=0;
				}
				else {
					$valg[$x]=$rest[$x];
					$lev_rest=$lev_rest-$rest[$x];
				}
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
			 if (($valg[$x]>0)&&(!$res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_kob_id, antal, lager) values ($linje_id, $vare_id, $batch_kob_id[$x], $valg[$x], $lager)",__FILE__ . " linje " . __LINE__);}
			 elseif (($valg[$x]>0)&&($res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_salg_id, antal, lager) values ($res_linje_id[$x], $vare_id, $temp, $valg[$x], $lager)",__FILE__ . " linje " . __LINE__);}
		 }
	}
} # endfinc batch
###############################################################
function samlevare($id,$art,$linje_id, $v_id, $leveres) {
# Denne funktion bruges (vist) ikke!
	if ($art=='DO' || $art=='PO') {
		include ("../includes/fuld_stykliste.php");
		list($vare_id, $stk_antal, $antal) = fuld_stykliste($v_id, '', 'basisvarer');
		for ($x=1; $x<=$antal; $x++) {
#cho "select * from varer where id='$vare_id[$x]'<br>";
			if ($r=db_fetch_array(db_select("select * from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__))) {
				$stk_antal[$x]=$stk_antal[$x]*$leveres;
#cho "insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, samlevare, posnr) values ('$id', '$r[varenr]', '$vare_id[$x]', '$r[beskrivelse]', '$stk_antal[$x]', '$stk_antal[$x]', '0', '$linje_id', '100' )<br>";
				db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, samlevare, posnr) values ('$id', '$r[varenr]', '$vare_id[$x]', '$r[beskrivelse]', '$stk_antal[$x]', '$stk_antal[$x]', '0', '$linje_id', '100' )",__FILE__ . " linje " . __LINE__);
			}
		}
	} else {
#cho "select antal,posnr from ordrelinjer where id='$linje_id'<br>";
		$r=db_fetch_array(db_select("select antal,posnr,kred_linje_id from ordrelinjer where id='$linje_id'",__FILE__ . " linje " . __LINE__));
		$antal=$r['antal']*1;
		$posnr=$r['posnr']*1;
		$kred_linje_id=$r['kred_linje_id']*1;
#cho "$antal select id,antal from ordrelinjer where id='$kred_linje_id'<br>";
		if ($antal && $r=db_fetch_array(db_select("select id,antal from ordrelinjer where id='$kred_linje_id'",__FILE__ . " linje " . __LINE__))) {
			$org_antal=$r['antal'];
#cho "select * from ordrelinjer where samlevare='$r[id]'<br>";
			$q=db_select("select * from ordrelinjer where samlevare='$r[id]'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				$ny_antal=afrund($r['antal']*$org_antal/$antal,2);
#cho "insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, samlevare, posnr) 
#					values 
#				('$id', '$r[varenr]', '$r[vare_id]', '$r[beskrivelse]', '$ny_antal', '$ny_antal', 0, $linje_id, '$r[posnr]' )<br>";
				db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, samlevare, posnr) 
					values 
				('$id', '$r[varenr]', '$r[vare_id]', '$r[beskrivelse]', '$ny_antal', '$ny_antal', 0, $linje_id, '$r[posnr]' )",__FILE__ . " linje " . __LINE__);
			}
		}
	}
#exit;
} # endfunc samlevare
###############################################################
function bogfor($id,$webservice) {
echo "<!--function bogfor start-->";

	global $brugernavn;
	global $db,$db_skriv_id;
	global $fakturadate;
	global $mail_fakt,$momssats;
	global $pbs;
	global $regnaar,$retur;
	global $sprog_id;
	global $valutakurs;
	
	
	$fejl=0;
	
#	$fp=fopen("../temp/ordrefunc.log","a");
#	$linje="select * from ordrer where id = $id";
#	fwrite($fp,$linje."\n");

	$query = db_select("select * from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$konto_id=$row['konto_id'];
	$ordredate=$row['ordredate'];
	$levdate=$row['levdate'];
	$fakturadate=$row['fakturadate'];
	$nextfakt=$row['nextfakt'];
	$art=$row['art'];
	$kred_ord_id=$row['kred_ord_id'];
	$valuta=$row['valuta'];
	$art=$row['art'];
	$afd=$row['afd'];
	$fakturanr=$row['fakturanr'];
	$procenttillag=$row['procenttillag']*1;
	$momssats=$row['momssats'];
	if ($art=='PO') {
		$sum=$row['sum'];
		$moms=afrund($row['moms'],2);
		$betaling=$row['felt_1'];
		$betalt=$row['felt_2'];
		$betaling2=$row['felt_3'];
		$betalt2=$row['felt_4'];
		$kasse=$row['felt_5'];
		$betalingsbet=$row['betalingsbet'];
		if ($betalingsbet=='Kontant') $konto_id='0';
		$ref=$row['ref'];
		$r=db_fetch_array(db_select("select box2 from grupper where art='OreDif'",__FILE__ . " linje " . __LINE__));
		$difkto=$r['box2'];
	} else {
		$ref=NULL;
		if (is_numeric($row['felt_2']) && is_numeric($row['felt_4']) && is_numeric($row['felt_5'])) { #20191001
			if ($row['felt_2']*1 != 0) {
				$qtxt="insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs) values ('$id','$row[felt_1]','$row[felt_2]','DKK','100')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			if ($row['felt_4']*1 != 0) {
				$qtxt="insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs) values ('$id','$row[felt_3]','$row[felt_4]','DKK','100')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$kasse=$row['felt_5'];
		} else $kasse=NULL;
	}
	if ($ref) { #20170324
		$r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn='$ref'",__FILE__ . " linje " . __LINE__));
		if ($r['ansat_id']){
			$r=db_fetch_array(db_select("select navn from ansatte where id='$r[ansat_id]'",__FILE__ . " linje " . __LINE__));
			if ($r['navn']) {
				db_modify("update ordrer set ref='$r[navn]' where id = '$id'",__FILE__ . " linje " . __LINE__);
			}
		}
	}

	if ($row['status']>'2'){
		return("invoice allready created for order id $id");
	}

	if ($procenttillag) {
		$sum=0;
		$q=db_select("select * from ordrelinjer where ordre_id='$id' order by posnr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($r['vare_id']) {
				$linjesum=$r['antal']*$r['pris'];
				if ($r['rabat']) $linjesum-=$linjesum*$r['rabat']/100;
				$linjesum-=$linjesum*(100-$r['procent'])/100;
				$sum+=$linjesum;
			}
			$posnr=$r['posnr'];
		}	
		$posnr++;
		$tillag=$sum/100*$procenttillag;
		$qtxt="select box13 from grupper where art = 'DIV' and kodenr = '3'";
#cho "$qtxt<br>";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		list($tmp,$procentvare)=explode(chr(9),$r['box13']);
#cho "box13 $r[box13] -> $procentvare<br>";
		if ($procentvare) {
			$qtxt="select * from varer where varenr = '$procentvare'";
#cho "$qtxt<br>";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
#cho "$id,$r[varenr],1,$r[beskrivelse],$tillag,0,100,$art,'',$posnr,'','','on','percent',''<br>";
				$tmp=str_replace('$procenttillæg;',$procenttillag,$r['beskrivelse']);
				opret_ordrelinje($id,$r['id'],$r['varenr'],1,$tmp,$tillag,0,100,$art,'',$posnr,'','','on','percent','',$lager); #20140426
				$r=db_fetch_array(db_select("select max(id) as linje_id from ordrelinjer where ordre_id='$id'",__FILE__ . " linje " . __LINE__));
				db_modify("update ordrelinjer set leveres='1' where id='$r[linje_id]'",__FILE__ . " linje " . __LINE__);
				levering($id,'','','');				
				$sum+=$tillag;

#cho "update ordrer set sum=sum+$tillag, moms=moms+$tillag/100*$momssats where id = '$id'<br>";
				db_modify("update ordrer set sum=sum+$tillag, moms=moms+$tillag/100*$momssats where id = '$id'",__FILE__ . " linje " . __LINE__);
#xit;	
			} else return('Manglende vare til procenttillæg');
		} else return('Manglende vare til procenttillæg -- '.$procentvare);
	}
#	$x=0;
#	$saet=array();
	$z=0;
	$a=0;
	$diff=0;
	$saetsum=0;
	$saetmoms=0;
	$q=db_select("select * from ordrelinjer where ordre_id='$id' and antal!='0' order by saet",__FILE__ . " linje " . __LINE__); #20160928 indsat "and antal!='0'" 
	($art=='DO')?$dan_kn=1:$dan_kn=0;
	while ($r=db_fetch_array($q)) {
		$z++;
		if ($r['antal'] < 0) $a+=$r['antal'];
		if ($r['vare_id'] && $r['antal'] >= 0) $dan_kn=0;  
		if ($r['saet']) {
			$tmp=$r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100);
			$saetsum+=$tmp;
			if (!$r['momsfri'] && $momssats) $saetmoms+=$tmp*$momssats/100;
		}
		if ($dan_kn && !$a) $dan_kn=0;
	} 
	if ($dan_kn && !$z) $dan_kn=0; #20160928
	if ($saetsum) {
		$r=db_fetch_array(db_select("select box2 from grupper where art='OreDif'",__FILE__ . " linje " . __LINE__));
		$difkto=$r['box2'];
		$tmp=afrund($saetsum+$saetmoms,2);
		$diff=$tmp-pos_afrund($tmp,$difkto);
#		if (afrund($diff,2)) saet_afrund($id,$saetsum,$saetmoms,$difkto);
	}
	
#		$q=db_select("select * from ordrelinjer where ordre_id='$id' order by posnr",__FILE__ . " linje " . __LINE__);
#		while ($r = db_fetch_array($q)) #cho "Ordrelinje $r[posnr] $r[ordre_id] $r[vare_id] $r[varenr] $r[beskrivelse] >$r[projekt]< $r[bogf_konto]<br>";
	
	
#xit;	
	$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);

	if ($row = db_fetch_array($query)){
		$year=$row['box2']*1;
		$month=$row['box1']*1; #20130411-2 
		if ($month<10) $month="0".$month; #20130411-2
 		$aarstart=str_replace(" ","",$year.$month);
		$year=$row['box4']*1;
		$month=$row['box3']*1;
		if ($month<10) $month="0".$month;
		$aarslut=str_replace(" ","",$year.$month);
	}
	$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);

	if (!$fakturadate){
		if ($webservice) {
			return("missing invoicedate for order $id");
		} else {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/fakturadato.php?id=$id&pbs=$pbs&mail_fakt=$mail_fakt&returside=bogfor.php\">";
			exit;
		}
	}
	if ($valuta && $valuta!='DKK') {
		if ($r= db_fetch_array(db_select("select valuta.kurs as kurs, grupper.box3 as difkto from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=".nr_cast("grupper.kodenr")." and valuta.valdate <= '$fakturadate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
			$valutakurs=$r['kurs']*1;
			$difkto=$r['difkto']*1;
			if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))) {
				if ($webservice) return("Kontonr $difkto (kursdiff) eksisterer ikke");
				else {
					return("Kontonr $difkto (kursdiff) eksisterer ikke");
				}
			}
		} else {
			$tmp = dkdato($fakturadate);
			return("Der er ikke nogen valutakurs for $valuta den $tmp (fakturadatoen).");
		}
	} else {
		$valuta='DKK';
		$valutakurs=100;
	}
	if (!$levdate){
		if ($webservice) return ("Missing deliverydate");
		else return ("Leveringsdato SKAL udfyldes");
	}
	if ($levdate<$ordredate){
		if ($webservice) return ("Deliverydate prior to orderdate");
		else return ("Leveringsdato er f&oslash;r ordredato");
	}

#	if ($fakturadate<$levdate)	{
#		if ($webservice) return ("Invoicedate prior to orderdate");
#		else return ("Fakturadato er f&oslash;r leveringsdato");
#	}

	if (($nextfakt)&& ($nextfakt<=$fakturadate)){
		if ($webservice) return ("Next_invoicedate prior to invoicedate");
		else return ("Genfaktureringsdato skal v&aelig;re efter fakturadato");
	}
	list ($year, $month, $day) = explode ('-', $fakturadate);
	$year=trim($year);
	$ym=$year.$month;

	if ($art!='PO' && !$webservice && ($ym<$aarstart || $ym>$aarslut))	{
		print "<BODY onLoad=\"javascript:alert('Fakturadato udenfor regnskabs&aring;r')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	}
	if ($valuta && $valuta!='DKK') {
		if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=".nr_cast("grupper.kodenr")." and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
			$valutakurs=$r['kurs'];
		} else {
			$tmp = dkdato($ordredate);
			return("Der er ikke nogen valutakurs for $valuta den $ordredate (ordredatoen)");
		}
	}
	$qtxt="select * from ordrelinjer where pris != '0' and m_rabat != '0' and rabat = '0' and ordre_id='$id'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
		$rabatkontonr=$r['bogf_konto'];
		$rabatvareid=$r['id'];
		$rabatvarenr=$r['varenr'];
		if ($r=db_fetch_array(db_select("select box2 from grupper where art = 'DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__))) {
			if ($rabatvareid=$r['box2']*1) {
				$r=db_fetch_array(db_select("select varenr from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__));
				$rabatvarenr=$r['varenr'];
			} # else $fejl="Manglende varenummer for rabat (Indstillinger -> Diverse -> Ordrerelaterede valg)";
		} # else $fejl="Manglende varenummer for rabat (Indstillinger -> Diverse -> Ordrerelaterede valg)";
	}
#exit;
	if (!$fejl) {
 		#ransaktion("begin"); 20130506
		if ($art!="PO") {
			$fakturanr=1;
			# select max kan ikke bruges da fakturanr felt ikke er numerisk;
			#20170103
			$q = db_select("select fakturanr from ordrer where (art = 'DO' or art = 'DK') and id != '$id' and fakturanr != '' order by fakturadate desc limit 100",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)){
				if ($fakturanr <= $r['fakturanr']*1) $fakturanr = $r['fakturanr']+1;
			}
			db_modify("update ordrer set fakturanr='$fakturanr' where id='$id'",__FILE__ . " linje " . __LINE__);
			usleep(rand(100000,500000)); #20190421
			$qtxt="select id from ordrer where (art = 'DO' or art = 'DK') and fakturanr='$fakturanr' and id != '$id'";
			while ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
				$fakturanr++;
				db_modify("update ordrer set fakturanr='$fakturanr' where id='$id'",__FILE__ . " linje " . __LINE__);
				usleep(rand(100000,500000));
				$qtxt="select id from ordrer where (art = 'DO' or art = 'DK') and fakturanr='$fakturanr' and id != '$id'";
			}
			$r=db_fetch_array(db_select("select box1 from grupper where art = 'RB' and kodenr='1'",__FILE__ . " linje " . __LINE__));
			if ($fakturanr<$r['box1']) $fakturanr=$r['box1'];
			if ($fakturanr < 1) $fakturanr = 1;
			$ny_id=array();
			$x=0;
			$q=db_select("select * from ordrelinjer where pris != '0' and m_rabat != '0' and rabat = '0' and ordre_id='$id'",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
			$x++;
				$linje_id[$x]=$r['id']*1;
				$linje_m_rabat[$x]=$r['m_rabat'];
				$linje_pris[$x]=$r['pris'];
				$linje_rabatart[$x]=$r['rabatart'];
				$linje_varenr[$x]=$r['varenr'];
				$linje_posnr[$x]=$r['posnr'];
			} 
			$linjeantal=$x;
			for ($x=1;$x<=$linjeantal;$x++) {
				$ny_id[$x]=copy_row("ordrelinjer",$linje_id[$x]);
				$pris=$linje_m_rabat[$x];
				$pris*=-1;
				$rabatpct=afrund($linje_m_rabat[$x]*100/$linje_pris[$x],2);
				($linje_rabatart[$x]=='amount')?$beskrivelse=findtekst(466,$sprog_id):$beskrivelse=findtekst(467,$sprog_id);
				$beskrivelse=str_replace('$rabatpct',$rabatpct,$beskrivelse);
#cho "update ordrelinjer set posnr=posnr+0.1,varenr='$rabatvarenr',vare_id='$rabatvareid',pris='$pris',kostpris='0',m_rabat='0',beskrivelse='$beskrivelse',bogf_konto='$rabatkontonr',kdo='on' where id=$ny_id[$x]<br>";
#exit;
				db_modify("update ordrelinjer set posnr=posnr+0.1,varenr='$rabatvarenr',vare_id='$rabatvareid',pris='$pris',kostpris='0',m_rabat='0',beskrivelse='$beskrivelse',bogf_konto='$rabatkontonr',kdo='on' where id=$ny_id[$x]",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select * from ordrelinjer where id='$ny_id[$x]'",__FILE__ . " linje " . __LINE__));
#cho "$r[id],$r[ordre_id],$r[posnr],$r[varenr],$r[vare_id],$r[pris],$r[kostpris],$r[m_rabat],$r[beskrivelse],$r[bogf_konto]<br>--<br>";
			}
		} else { # Så er det en POS ordre
#cho "$konto_id==0 && $sum+$moms=!$betalt<br>";
			$diff=0;
			if ($valuta!='DKK' && $betaling=='Kontant' && !$betaling2) {
				$tmp1=afrund($sum+$moms,2);
				$tmp2=pos_afrund($sum+$moms,$difkto);
				
				if ($afrunding=$tmp2-$tmp1) {
#cho "A $afrunding $tmp2-$tmp1<br>";
					if ($moms) {
						$afrundingsmoms=afrund($afrunding*($moms*100/$sum)/100,2);
						$afrunding=afrund($afrunding-$afrundingsmoms,2);
						$sum+=$afrunding;
						$moms+=$afrundingsmoms;
					} 
#cho "A1 $afrunding $tmp2-$tmp1<br>";
#					$diff=1;
					$diff=$afrunding;
					
				}
			}
$tmp=afrund($sum+$moms,2)+$retur;
			if (afrund($sum+$moms,2)+$retur!=$betalt) { #20140613
				$returdiff=afrund($sum+$moms,2)+$retur-$betalt;
#cho __line__." $returdiff=afrund($sum+$moms,2)+$retur-$betalt;<br>";
				if (afrund($returdiff,2)==0.01) $moms=$moms-0.01;
				elseif (afrund($returdiff,2)==-0.01) $moms=$moms+0.01;
				db_modify("update ordrer set sum = '$sum',moms='$moms' where id = '$id'",__FILE__ . " linje " . __LINE__);
			}
			$a=afrund($sum+$moms,2); #20131111 + næste 3 linjer rettet grundet php fejl??
			$b=afrund($betalt+$betalt2,2); 
#cho __line__." A $a B $b<br>";
#xit;
			if ($konto_id==0 && $a!=$b) {
#				$retur=$a-$b; Hentes fra global.
				$tmp2=round($retur,2);
				$tmp1=pos_afrund($retur,$difkto);
#cho "B $afrunding $tmp2-$tmp1<br>";
				if ($afrunding=afrund($tmp2-$tmp1,2)) {
#cho "B $afrunding $tmp2-$tmp1<br>";
					if ($moms) {
						$afrundingsmoms=afrund($afrunding*($moms*100/$sum)/100,2);
						$afrunding=afrund($afrunding-$afrundingsmoms,2);
						$sum+=$afrunding;
						$moms+=$afrundingsmoms;
					}
					$diff+=$afrunding;
				}
			}
#cho __line__." sum $sum Moms $moms<br>";
			
#xit;
			#cho "DK $difkto && DI $diff && AF $afrunding Moms $moms<br>";		
			$diff=afrund($diff,2); #20140613
			if ($difkto && $diff) {
					$linje_posnr[$x]+=0.1;
						
					# #cho "POI insert into ordrelinjer (posnr,antal,pris,rabat,procent,ordre_id,bogf_konto,beskrivelse,projekt) values ('0','1', '$diff', 0,100, '$id', '$difkto','Afrunding','$projekt')<br>";
						db_modify("insert into ordrelinjer (posnr,antal,pris,rabat,procent,ordre_id,bogf_konto,beskrivelse,projekt) values ('0','1', '$diff', 0,100, '$id', '$difkto','Afrunding','$projekt')",__FILE__ . " linje " . __LINE__);
						db_modify("update ordrer set sum = '$sum',moms='$moms' where id = '$id'",__FILE__ . " linje " . __LINE__);
					}
				}
#cho "A select * from ordrer where id='$id'<br>";
	$q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q); 
#cho "Sum $r[sum] $r[moms]<br>";		
		batch_kob($id, $art);
#cho "B select * from ordrer where id='$id'<br>";
	$q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q); 
#cho "Sum $r[sum] $r[moms]<br>";		
	batch_salg($id);
#cho "D select * from ordrer where id='$id'<br>";
	$q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q); 
#cho "Sum $r[sum] $r[moms]<br>";		
#cho "update ordrer set status=3, fakturanr=$fakturanr, valutakurs=$valutakurs where id=$id<br>";
		$tidspkt=date("H:i");
		db_modify("update ordrer set status='3', fakturanr='$fakturanr', tidspkt='$tidspkt', valutakurs='$valutakurs' where id='$id'",__FILE__ . " linje " . __LINE__);
		if ($afd) db_modify("update ordrer set felt_5='$afd' where id='$id' and felt_5 =''",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select box5 from grupper where art='DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__));
		if (strstr($r['box5'],';')) list($straksbogfor,$tmp)=explode(';',$r['box5']); # 20170404 
		else $straksbogfor=$r['box5'];
		if (!$straksbogfor && $kasse) {
			$k=$kasse-1;
			$qtxt="select var_value from settings where var_name='postEachSale'";
#cho "$qtxt<br>";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$postEachSale=explode(chr(9),$r['var_value']);
			$straksbogfor=$postEachSale[$k];
#cho "$straksbogfor=$postEachSale[$k]<br>";
		}
		$svar=momsupdat($id);
		if ($art=='PO' && !$konto_id) {
			$r = db_fetch_array(db_select("select box9 from grupper where art='POS' and kodenr='1'",__FILE__ . " linje " . __LINE__));
			$straksbogfor=$r['box9'];
		}
#$linje="Straksbogfor $straksbogfor ($id,$webservice)<br>";
#fwrite($fp,$linje."\n");
if ($dan_kn) db_modify("update ordrer set art = 'DK' where id = '$id'",__FILE__ . " linje " . __LINE__);
if ($straksbogfor) $svar=bogfor_nu($id,$webservice);
elseif (($db=='bizsys_49' || $db=='udvikling_5')  && $webservice) $svar=bogfor_nu($id,'webservice'); #20180914
#$linje="Svar $svar<br>";
#fwrite($fp,$linje."\n");
		if ($svar != "OK") {
			return($svar);
			exit;
		} else {
#			exit;
			#ransaktion("commit"); 20130506
		}
	} elseif (!$svar) $svar = $fejl;
	echo "<!--function bogfor slut-->";
	return($svar);
} #endfunc bogfor
#############################################################################################################################
function momsupdat($id) {
	# Denne funktion løber ordren igennem og tjekker om alle ordrelinjer er momsbelagte og om alle ordrelinjer har samme momssats som ordren.
	# Hvis begge betingelser er opfyldt beregnes momsen ud fra det totale beløb og hvis ikke beregnes momsen for hver ordrelinje og summeres til sidst.
	global $db,$db_skriv_id;
	global $brugernavn;
	$sum=0;
	$moms=0;
	$antal_diff_moms=0; #indfort 2011.03.23 grundet momsafvigelse paa 3 ore i faktura 30283 regnskab 329

#cho "select momssats from ordrer where id = $id<br>";
	$r=db_fetch_array(db_select("select art,momssats,sum,moms from ordrer where id = $id",__FILE__ . " linje " . __LINE__));
	$art=$r['art'];
	$momssats=$r['momssats']*1;
	$ordresum=$r['sum'];
	$ordremoms=$r['moms'];
#cho "$ordresum $ordremoms momssats=$momssats<br>";

	$q=db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['rabatart']=='amount') $linjesum=($r['pris']-$r['rabat'])*$r['antal'];
		else $linjesum=($r['pris']-($r['pris']/100*$r['rabat']))*$r['antal'];
		if ($r['procent'] || $r['procent']=='0') $linjesum*=$r['procent']/100;
#cho "$sum+=afrund($linjesum,3)<br>";
		
		$sum+=afrund($linjesum,3);#20150227
		#cho "vare id $r[vare_id] momsfri $r[momsfri]<br>";
		if ($r['vare_id'] && $r['momsfri']!='on' && !$r['omvbet']) {
		if ($r['momssats'] > 0 && $r['momssats'] < $momssats) $varemomssats=$r['momssats']; 
			else {
				if ($r['momssats'] != $momssats) db_modify ("update ordrelinjer set momssats=$momssats where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
				$varemomssats=$momssats;
			}
			if ($varemomssats!=$momssats) $antal_diff_moms++;
			if ($r['rabatart']=='amount') $linjemoms=($r['pris']-$r['rabat'])*$r['antal']/100*$varemomssats;
			else $linjemoms=($r['pris']-($r['pris']/100*$r['rabat']))*$r['antal']/100*$varemomssats;
			if ($r['procent'] || $r['procent']=='0') $linjemoms*=$r['procent']/100;
			$moms+=afrund($linjemoms,2);
		} else if ($r['vare_id']) $antal_diff_moms++;
	}
#cho "antal_diff_moms $antal_diff_moms<br>";
	#cho "MU1 $sum $moms $ordresum $ordremoms<br>";
	if (!$antal_diff_moms && $art !='PO') {
		#cho "$ordremoms != $ordresum/100*$momssats<br>";
		$moms=afrund($sum/100*$momssats,2);
		$sum=afrund($sum,2); # 20171003
		#cho "MU2 $sum $moms $ordresum $ordremoms<br>";
		$sum*=1; $moms*=1;
#cho "update ordrer set sum=$sum, moms=$moms where id = '$id'<br>";
#xit;
		db_modify("update ordrer set sum=$sum, moms=$moms where id = '$id'",__FILE__ . " linje " . __LINE__);
	}	
	return("OK");
}
###########################################################
function batch_salg($id) {
	global $fakturadate;
	global $valutakurs,$version;
	global $momsfri,$momssats;
	global $regnaar;

  $r=db_fetch_array(db_select("select art,cvrnr from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
  $cvrnr=$r['cvrnr'];
	$art=$r['art'];
	
  $r=db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
  $fifo=$r['box6'];

	$x=0;
	$batch_id=array();

	
	$q = db_select("select * from batch_salg where ordre_id = '$id' order by id",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		$qtxt="select posnr from ordrelinjer where id = '$r[linje_id]'";
		if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			db_modify("delete from batch_salg where id='$r[id]'",__FILE__ . " linje " . __LINE__);
		}
	}
	$q = db_select("select * from batch_salg where ordre_id = '$id' order by id",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		# Indsat 20101129 - Der bliver undertiden oprettet batch_salg linjer uden tilhorende ordrelinje hvilket giver fejl. Aarsag skal findes.
		$qtxt="select posnr from ordrelinjer where id = '$r[linje_id]'";
		if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$posnr[$x]=$r2['posnr'];
			$batch_id[$x]=$r['id'];
			$x++;
		} else {
			db_modify("delete from batch_salg where id='$r[id]'",__FILE__ . " linje " . __LINE__);
		}
	}
	array_multisort($posnr,$batch_id); # -> 20190729
	for ($x=0; $x<count($batch_id); $x++) {
		$r=db_fetch_array(db_select("select * from batch_salg where id = '$batch_id[$x]'",__FILE__ . " linje " . __LINE__));
		$vare_id[$x]=$r['vare_id'];
		$antal[$x]=$r['antal'];
		$batch_kob_id[$x]=$r['batch_kob_id'];
		$batch_linje_id[$x]=$r['linje_id'];
		$qtxt="select serienr from serienr where batch_salg_id = '$batch_id[$x]'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$serienr[$x]=$r['serienr'];
	} # <- 20190729
	$mrabatpris=NULL; #20160824
	$mrabatvare=NULL; #20160824
	for ($x=0; $x<count($batch_id); $x++) {
		$kostpris=0;
		$qtxt="select bogf_konto,kostpris,fast_db,id,m_rabat,procent,projekt,pris,rabat,saet,samlevare ";
		$qtxt.="from ordrelinjer where id = '$batch_linje_id[$x]'";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($q);
		$ordre_linje_id=$row['id'];
		$pris = $row['pris']-($row['pris']*$row['rabat']/100);
		if ($row['procent'] || $row['procent']=='0') $pris*=$row['procent']/100; #20150817
		$linjekostpris = $row['kostpris']*1;
		$projekt=$row['projekt'];
		$bogf_konto=$row['bogf_konto'];
		$fast_db=$row['fast_db'];
		$samlevare=$row['samlevare'];
		$saet=$row['saet'];
		$m_rabat=$row['m_rabat']*1; #20160905
		if ($valutakurs) {
			$pris=afrund($pris*$valutakurs/100,3);
			$linjekostpris=afrund($linjekostpris*$valutakurs/100,3);
		}
		if ($m_rabat && !$mrabatpris) { #20160824
			$mrabatpris = $pris-$m_rabat;
			$mrabatvare = $vare_id[$x];
			db_modify("update batch_salg set pris=$mrabatpris, fakturadate='$fakturadate' where id='$batch_id[$x]'",__FILE__ . " linje " . __LINE__);
		} elseif ($mrabatpris && $mrabatvare == $vare_id[$x]) {
			db_modify("update batch_salg set antal=0, fakturadate='$fakturadate' where id='$batch_id[$x]'",__FILE__ . " linje " . __LINE__);
			$mrabatpris = NULL;
			$mrabatvare = NULL;
		} else db_modify("update batch_salg set pris=$pris, fakturadate='$fakturadate' where id='$batch_id[$x]'",__FILE__ . " linje " . __LINE__);
		if ($batch_kob_id[$x]) {
			$query = db_select("select pris, ordre_id from batch_kob where id = '$batch_kob_id[$x]'",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {
				$kostpris=$row['pris'];
				if ($row['ordre_id']) {
					$query = db_select("select status from ordrer where id = '$row[ordre_id]'",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					if ($row['status']){$kobsstatus=$row['status'];}
				}
				else {$kobsstatus=0;}
			}
		}

		$query2 = db_select("select gruppe from varer where id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$gruppe=$row2['gruppe'];
		$query2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$box1=trim($row2['box1']); $box2=trim($row2['box2']); $box3=trim($row2['box3']); $box4=trim($row2['box4']); $box8=trim($row2['box8']); $box9=trim($row2['box9']);
		$box12=trim($row2['box12']);$box14=trim($row2['box14']);
		if ($box12 && cvrnr_omr(cvrnr_land($cvrnr,'')) == "EU") $bf_kto=$box12; 
		elseif ($box14 && cvrnr_omr(cvrnr_land($cvrnr,'')) == "UD") $bf_kto=$box14;
		elseif ($bogf_konto) $bf_kto=$bogf_konto;
		else $bf_kto=$box4;
		if ($bf_kto && !$momsfri) {
		if (!$regnaar) {
			$y=date('Y');
			$m=date('m');
			$qtxt="select kodenr from grupper where art='RA' and box2<='$y' and box3<='$m' order by box2 desc limit 1";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$regnaar=$r['kodenr']*1;
		}
		$qtxt="select moms from kontoplan where kontonr = '$bf_kto' and regnskabsaar = '$regnaar'";
			$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($vatAccName=trim($r2['moms'])) { # f.eks S3
				$tmp=substr($vatAccName,1); #f.eks 3
				$qtxt="select box1,box2 from grupper where art = 'SM' and kodenr = '$tmp'";
				$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$vatAccount=$r2['box1']*1;
				($r2['box2']<$momssats)?$varemomssats=$r2['box2']*1:$varemomssats=$momssats*1;
				if ($varemomssats && !$vatAccount) {
					$txt="Manglende kontonummer for momstype $vatAccName -> $varemomssats -> $momssats";
					alert($txt);
					exit;
				}
			}	else {
				$varemomssats=0;
				$vatAccount=0;
			}	
		} else {
			$varemomssats=0;
			$vatAccount=0;
		}
		$r=db_fetch_array(db_select("select box2 from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__));
		($r['box2'] >= '2015')?$aut_lager='on':$aut_lager=NULL;

		#		if ($bogf_konto) $box4=$bogf_konto;
		if (!$version)include("../includes/version.php");
		$qtxt="update ordrelinjer set bogf_konto='$bf_kto',momssats='$varemomssats',vat_account='$vatAccount',projekt='$projekt' where id='$ordre_linje_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		if ($fifo && !$box9) {
			$y=0;
			$mangler=$antal[$x];
			$kostsum=0;
			$qtxt="select * from batch_kob where rest>'0' and vare_id='$vare_id[$x]' and ordre_id!= '$id' order by fakturadate,id";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($mangler && $r=db_fetch_array($q)) {
				$rest=$r['rest'];
				if ($mangler && $rest>=$mangler) {
					$kostsum+=$mangler*$r['pris'];
					$rest=$rest-$mangler;
					$mangler=0;
				} elseif ($mangler && $rest < $mangler) {

					$kostsum+=$r['rest']*$r['pris'];
					$mangler=$mangler-$rest;
					$rest=0;
				} 
				$y++;
			}
			if ($y==0) {
				$qtxt="select kostpris from varer where id='$vare_id[$x]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$kostpris=$r['kostpris']*1;
				$kostsum=$kostpris*$mangler;

				}
			if ($antal[$x]-$mangler){
				$kostpris=$kostsum/($antal[$x]-$mangler); # -mangler tilfojet 20120607 grundet fejl i lager->varekob v. negativt lager (saldi_444) 
				$kostpris*=1;
				db_modify("update ordrelinjer set kostpris='$kostpris' where id='$ordre_linje_id'",__FILE__ . " linje " . __LINE__);
			}
			if ($mangler) { #så bliver lagerbeholdningen negativ
				db_modify("update ordrelinjer set kostpris='$kostpris' where id='$ordre_linje_id'",__FILE__ . " linje " . __LINE__);
			}
		}
		if ($box9=='on'){ # box 9 betyder at der anvendes batch styring
			if ($antal[$x] > 0) {
			if (!$batch_kob_id[$x]) { # saa er varen ikke paa lager, dvs at indkobsordren skal findes i tabellen reservation
				$query = db_select("select linje_id, lager from reservation where batch_salg_id = '$batch_id[$x]'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$res_antal=$res_antal+$row['antal'];
				$res_linje_id=$row['linje_id'];
				$lager=$row['lager'];
				$r1 = db_fetch_array(db_select("select ordre_id, pris, rabat, projekt from ordrelinjer where id = '$res_linje_id'",__FILE__ . " linje " . __LINE__));
				$kob_ordre_id = $r1['ordre_id'];
				$projekt = $r1['projekt'];
				$r2 = db_fetch_array(db_select("select valutakurs from ordrer where id = '$kob_ordre_id'",__FILE__ . " linje " . __LINE__));
				$kostpris = ($r1['pris']-($r1['pris']*$r1['rabat']/100))*$r2['valutakurs']/100;
				if ($valutakurs && $valutakurs != 100) $kostpris*=100/$valutakurs; 
				db_modify("update ordrelinjer set kostpris = '$kostpris' where id='$ordre_linje_id'",__FILE__ . " linje " . __LINE__);
			# Hvis levering er sket i flere omgange vil der vaere flere batch_salg linjer paa samme kobs linje, derfor nedenstaende.
				if ($row = db_fetch_array(db_select("select id from batch_kob where linje_id='$res_linje_id' and vare_id='$vare_id[$x]' and ordre_id='$kob_ordre_id'",__FILE__ . " linje " . __LINE__))) {
					$batch_kob_id[$x]=$row['id'];
				} else {
					db_modify("insert into batch_kob (linje_id, vare_id, ordre_id, pris, lager,variant_id) values ('$res_linje_id','$vare_id[$x]','$kob_ordre_id','$kostpris','$lager','$variant_id')",__FILE__ . " linje " . __LINE__); #Antal indsaettes ikke - dette styres i "reservation"
					$row = db_fetch_array(db_select("select id from batch_kob where linje_id='$res_linje_id' and vare_id='$vare_id[$x]' and ordre_id='$kob_ordre_id'",__FILE__ . " linje " . __LINE__));
					$batch_kob_id[$x]=$row['id'];
				}
				db_modify("update reservation set batch_kob_id='$batch_kob_id[$x]' where linje_id = '$res_linje_id'",__FILE__ . " linje " . __LINE__);
#cho "update batch_salg set batch_kob_id='$batch_kob_id[$x]' where id='$batch_id[$x]'<br>";
				db_modify("update batch_salg set batch_kob_id='$batch_kob_id[$x]' where id='$batch_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
			# Nedenstående er muligvis overflødig - skal testes.
			# 20120730 Remmet folgende 3 linjer da de giver forkert lagertræk v. salg før køb.
			$row = db_fetch_array(db_select("select pris,fakturadate from batch_kob where id='$batch_kob_id[$x]'",__FILE__ . " linje " . __LINE__)); # kostprisen findes..
			if ($row['fakturadate']) $kostpris=$row['pris']*1; #Hvis fakturadatoen ikker er sat, er købsordren ikke bogført og kostprisen fra ordrelinjer anvendes.
			else $kostpris=$linjekostpris;
				$row = db_fetch_array(db_select("select kostpris from varer where id=$vare_id[$x]",__FILE__ . " linje " . __LINE__));
				$kostpris=$row['kostpris']*1;
			}
			if (!$aut_lager && $box1 && $box2 && $kostpris) { #kostvaerdien flyttes fra "afgang varelager" til "varekob".- hvis der ikke bogfoeres direkte paa varekobs kontoen
				#	if ($valutakurs) $pris=$pris*100/$valutakurs;
#cho " insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1','$antal[$x]', '$kostpris', 0, '$id', '$box2','$projekt')<br>";
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1','$antal[$x]', '$kostpris', 0, '$id', '$box2','$projekt','0')",__FILE__ . " linje " . __LINE__);
				$kostpris=$kostpris*-1;
#cho "insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1','$antal[$x]', '$kostpris', 0, '$id', '$box3','$projekt')";
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1','$antal[$x]', '$kostpris', 0, '$id', '$box3','$projekt','0')",__FILE__ . " linje " . __LINE__);
			}
#xit;			
		} elseif ($box8=='on') { # hvis box8 er 'on' er varen lagerfoert
			$r=db_fetch_array(db_select("select pris,fast_db from ordrelinjer where id='$ordre_linje_id'",__FILE__ . " linje " . __LINE__));
			$fast_db=$r['fast_db']*1;
			if ($fast_db) {
				$kostpris=$r['pris']*$fast_db;
			} else {
				if ($samlevare && $saet) $kostpris=0;
				else {
					$qtxt="select kostpris from varer where id='$vare_id[$x]'";
					$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$kostpris=$r['kostpris']*1;
				}
			}
			if (!$fifo) {
				$qtxt="update ordrelinjer set kostpris = '$kostpris' where id='$ordre_linje_id'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			if (!$aut_lager && $box1 && $box2 && ($kostpris || $linjekostpris)) {
				if (!$kostpris) $kostpris=$linjekostpris;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt,saet) values ('-1','$antal[$x]', '$kostpris', '0', '$id', '$box2','$projekt','0')",__FILE__ . " linje " . __LINE__);
				$kostpris=$kostpris*-1;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt,saet) values ('-1','$antal[$x]', '$kostpris', '0', '$id', '$box3','$projekt','0')",__FILE__ . " linje " . __LINE__);
			}
		}
	}
	if ($art=='PO') { #201810112
		$qtxt="select * from ordrelinjer where ordre_id='$id' and m_rabat>0 order by posnr";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$pris=afrund($r['pris']*$r['antal']*$r['m_rabat']/100);
			$qtxt="select id from ordrelinjer where ordre_id='$id' and varenr='$r[varenr]' and pris = $r[m_rabat]*-1 ";
			$qtxt.="and beskrivelse='rabat' and m_rabat=0 and KDO='1'";
			$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			$r2=db_fetch_array($q2);
			if ($r2['id']) db_modify("update batch_salg set antal='0' where linje_id='$r2[id]'",__FILE__ . " linje " . __LINE__);
		}
	}
} # endfunc batch_salg

####### batch_kob anvendes hvis der krediteres en vare - og derfor betragtes som et varekoeb #######
function batch_kob($id, $art) {
	global $fakturadate;
	global $valutakurs;
	global $regnaar;

  $r=db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
  $fifo=$r['box6'];

	$query = db_select("select * from batch_kob where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		$x++;
		$batch_id=$row['id'];
		$vare_id=$row['vare_id'];
		$antal=$row['antal'];
		$projekt=$row['projekt'];
		$serienr=$row['serienr'];
		$batch_kob_id=$row['batch_kob_id'];
		$query2 = db_select("select id, pris, rabat, projekt, bogf_konto from ordrelinjer where id = $row[linje_id]",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$ordre_linje_id=$row2['id'];
		$bogf_konto=$row2['bogf_konto'];
		$pris = $row2[pris]-($row2['pris']*$row2['rabat']/100);
		if ($row['pris']) {$diff = $pris-$row['pris'];}
		db_modify("update batch_kob set pris=$pris, fakturadate='$fakturadate' where id=$batch_id",__FILE__ . " linje " . __LINE__);
 		$query2 = db_select("select gruppe from varer where id = $vare_id",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$gruppe=$row2['gruppe'];
		$query2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$box1=trim($row2['box1']); $box2=trim($row2['box2']); $box3=trim($row2['box3']); $box4=trim($row2['box4']); $box8=trim($row2['box8']); $box9=trim($row2['box9']);

		$r=db_fetch_array(db_select("select box2 from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__));
		($r['box2'] >= '2015')?$aut_lager='on':$aut_lager=NULL;
		if (!$regnaar) {
			echo "regnaar mangler";
			exit;
		}	
		if ($aut_lager) {
			$box8='';
			$box9='';
		}
		
		#cho "B update ordrelinjer set bogf_konto=$box4 where id=$ordre_linje_id<br>";
		if ($bogf_konto) $box4=$bogf_konto;
		db_modify("update ordrelinjer set bogf_konto=$box4 where id=$ordre_linje_id",__FILE__ . " linje " . __LINE__);
		if ($box9=='on' && $box1 && $box2){ # Batchkontrol og lagerværdi føres.
			$pris=$pris-$diff;
			$pris=$pris*1;
			if ($valutakurs && $pris) $pris=$pris*100/$valutakurs;
#cho "insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1','$antal','$pris','0','$id','$box3','$projekt')<br>";
			db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt,saet) values ('-1','$antal','$pris','0','$id','$box3','$projekt','0')",__FILE__ . " linje " . __LINE__);
			$pris=$pris*-1;
#cho "insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1','$antal','$pris','0','$id','$box2','$projekt')<br>";
			db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt,saet) values ('-1','$antal','$pris','0','$id','$box2','$projekt','0')",__FILE__ . " linje " . __LINE__);
		} elseif ($box8=='on' && $box1 && $box2){ # lagerværdi føres. Tilføjet 20120613 lager blev kun reguleret ved kreditering hvis batch var aktiv  !!!  
			$r=db_fetch_array(db_select("select kostpris from varer where id = $vare_id",__FILE__ . " linje " . __LINE__));
			$kostpris=$r['kostpris']*1;
			if ($valutakurs && $kostpris) $kostpris=$kostpris*100/$valutakurs;
#cho "insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1','$antal','$kostpris','0','$id','$box3','$projekt')<br>";
			db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt,saet) values ('-1','$antal','$kostpris','0','$id','$box3','$projekt','0')",__FILE__ . " linje " . __LINE__);
			$kostpris*=-1;
#cho "insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1','$antal','$kostpris','0','$id','$box2','$projekt')<br>";
			db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt,saet) values ('-1','$antal','$kostpris','0','$id','$box2','$projekt','0')",__FILE__ . " linje " . __LINE__);
		}
	}
} # endfunc batch_kob
###############################################################
function bogfor_indbetaling($id,$webservice) {
	include("../includes/genberegn.php");
	include("../includes/forfaldsdag.php");
	global $db,$db_skriv_id;
	global $regnaar;
	global $valuta;
	global $valutakurs;
	global $difkto;
	global $title;
	global $kasse;

	$q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$art=$r['art'];
		$konto_id=$r['konto_id'];
		$kundekontonr=str_replace(" ","",$r['kontonr']);
		$firmanavn=trim($r['firmanavn']);
		$modtagelse=$r['modtagelse'];
		$transdate=($r['fakturadate']);
		$fakturanr=$r['fakturanr'];
		$ordrenr=$r['ordrenr'];
#cho "$firmanavn | $ordrenr<br>";
		$valuta=$r['valuta'];
		$kred_ord_id=$r['kred_ord_id'];
		if (!$valuta) $valuta='DKK';
		$projekt[0]=$r['projekt'];
		$betalingsbet=$r['betalingsbet'];
		$betalingsdage=$r['betalingsdage']*1;
		$betalt=$r['betalt']*1;
		$sum=$r['sum']+$moms;
		$betaling=$r['felt_1'];
		$modtaget=$r['felt_2'];
		$kasse=$r['felt_5']*1;
#cho "kasse $kasse<br>";
		$ansat='0';
		$beskrivelse="Indbetaling konto: $kundekontonr";
		$uxtid=date("U");
		$tmp=$sum*-1;
		$qtxt="insert into openpost (konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,transdate,uxtid,kladde_id,refnr,valuta,valutakurs,projekt)";
		$qtxt.=" values ";
		$qtxt.="('$konto_id','$kundekontonr','$fakturanr','$tmp','$beskrivelse','0','$transdate','$uxtid','0','$id','$valuta','$valutakurs','$projekt[0]')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select max(id) as id from openpost where konto_id = '$konto_id' and faktnr = '$fakturanr' and refnr='$id'",__FILE__ . " linje " . __LINE__));
		$openpost_id=$r['id'];
		#20161001 ->
		$r = db_fetch_array(db_select("select id from openpost where konto_id = '$konto_id' and amount='$sum' and udlignet !='1' and id != '$openpost_id'",__FILE__ . " linje " . __LINE__));
		$udlign_id=$r['id'];
		if ($openpost_id && $udlign_id) db_modify ("update openpost set udlignet = '1' where id='$udlign_id' or id='$openpost_id'",__FILE__ . " linje " . __LINE__); 
		# <- 20161001
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$r = db_fetch_array(db_select("select beskrivelse, box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['box2']; # Kontonr aendres fra at vaere leverandoerkontonr til finanskontonr
		$tekst="Kontonummer for Debitorgruppe `$r[beskrivelse]` er ikke gyldigt";
		if (!$kontonr && $webservice) return($tekst);
		elseif(!$kontonr) print "<BODY onLoad=\"javascript:alert('$tekst')\">";
		if ($art=='PO' && $konto_id && is_numeric($konto_id)) { #20190116
			$k=0;
			$qtxt="select id,gruppe from adresser where art='K' and id='$konto_id'";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if ($r=db_fetch_array($q)){
				$qtxt="select box2 from grupper where art='KG' and kodenr='$r[gruppe]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$kontonr=$r['box2'];
				$qtxt="update ordrer set art='KO' where id='$id'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		$logdate=date("Y-m-d");
		$logtime=date("H:i");
		if ($sum) {
			if ($sum>0) {$kredit=$sum; $debet='0';}
			else {$kredit='0'; $debet=$sum*-1;}

			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			$debet=afrund($debet,2);
			$kredit=afrund($kredit,2);
#cho "insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', 0, '$logdate', '$logtime', '0', '0', '$id')<br>";
			db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kontonr','$fakturanr','$debet','$kredit','0',0,'$logdate','$logtime','0','0','$id','$kasse')",__FILE__ . " linje " . __LINE__);
		}
		$tmparray=array();
		$r=db_fetch_array(db_select("select box6 from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
		$div_kort_kto=trim($r['box6']);
		$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
		if ($betaling=='Kontant') {
			$tmparray=explode(chr(9),$r['box2']);
			$kassekonto=$tmparray[$kasse-1]*1;
		} else {
			$tmparray=explode(chr(9),$r['box3']);
			$afd=$tmparray[$kasse-1]*1;
			$tmparray=explode(chr(9),$r['box2']);
			$kassekonto=$tmparray[$kasse-1]*1;
			$kortantal=$r['box4']*1;
			$korttyper=explode(chr(9),$r['box5']);
			$kortkonti=explode(chr(9),$r['box6']);
			$kortnavn=NULL; 
			if ($div_kort_kto) { #20140129
				if (strpos($betaling,"|")) list($betaling,$kortnavn)=explode("|",$betaling);
				if (strpos($betaling2,"|")) list($betaling2,$kortnavn)=explode("|",$betaling2);
				$korttyper[$kortantal]='Betalingskort';
				$kortkonti[$kortantal]=$div_kort_kto;
				$kortantal++;
			}
			for($x=0;$x<$kortantal;$x++) {
				if ($betaling==$korttyper[$x]) {
					if ($modtaget>0) { #20180912
						$debet=afrund($modtaget,2);
						$kredit='0';
					} else {
						$debet='0';
						$kredit=afrund($modtaget,2)*-1;
					}
					$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
					$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kortkonti[$x]','$fakturanr','$debet','$kredit','0',$afd,'$logdate','$logtime','$projekt[0]','$ansat','$id','$kasse')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					if ($modtaget != $sum) {
						$debet=0;$kredit=0;
						($modtaget>$sum)?$kredit=afrund($modtaget-$sum,2):$debet=afrund($sum-$modtaget,2);
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
						$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kassekonto','$fakturanr','$debet','$kredit','0',$afd,'$logdate','$logtime','$projekt[0]','$ansat','$id','$kasse')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
					$sum=0;
				}
			}
		}
		if ($sum>0) {$debet=$sum; $kredit='0';}
		else {$debet='0'; $kredit=$sum*-1;}

		if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		$debet=afrund($debet,2);
		$kredit=afrund($kredit,2);
#cho "C insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kassekonto','$fakturanr','$debet','$kredit','0','0','$logdate','$logtime','0','0','$id','$kasse')<br>";
		if ($debet || $kredit) db_modify("insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kassekonto','$fakturanr','$debet','$kredit','0','0','$logdate','$logtime','0','0','$id','$kasse')",__FILE__ . " linje " . __LINE__);
		db_modify("update ordrer set status=4, valutakurs=$valutakurs where id=$id",__FILE__ . " linje " . __LINE__);
	}	
 #xit;
	transaktion("commit");
	return('OK');
}
######################################################################################################################################
function bogfor_nu($id,$kilde) {

include("../includes/genberegn.php");
	include("../includes/forfaldsdag.php");
 	global $brugernavn;
	global $db,$db_skriv_id;
	global $regnaar;
	global $valuta;
	global $valutakurs;
	global $difkto;
	global $title;

	($kilde=='webservice')?$webservice='webservice':$webservice=NULL;
	$webservice='webservice';
	$amount=$lineVatTotal=0;
	$indbetaling=$konto_id=$kontonr=$no_faktbill=NULL;
	$svar="OK";

	$regnaar=$regnaar*1;
	
	#20130820 -->
	$x=0;

	if ($webservice) $qtxt="select distinct(kontonr) as kontonr from kontoplan where (kontotype='D' or kontotype='S')";
	else $qtxt="select kontonr from kontoplan where regnskabsaar='$regnaar' and (kontotype='D' or kontotype='S')";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r = db_fetch_array($q)){
		$kontoplan[$x]=$r['kontonr'];
		$x++;
	}
	# <-- 20130820
	$d_kontrol=0;
	$k_kontrol=0;
	$logdate=date("Y-m-d");
	$logtime=date("H:i");
	$uxtid=date("U");
	$q = db_select("select box1, box2, box3, box4, box5 from grupper where art='RB'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		if (trim($r['box3'])=="on") $faktbill=1;
		else {$faktbill=0;}
		if (trim($r['box4'])=="on") $modtbill=1;
		else $modtbill=0;
		if (trim($r['box5'])=="on") {
			$no_faktbill=1;
			$faktbill=0;
		}
		else $no_faktbill=0;
	}
	$x=0;
	$qtxt = "select distinct(box1) from grupper where art = 'SM' and box2 != '0.00' order by box1"; #20190428
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$vatAccount[$x]=$r['box1'];
		$vatAmount[$x]=0;
		$exVatAmount[$x]=0;
		$x++;
	}

	$projekt=array();
	$idliste=array();
  if (is_numeric($id)) $tmp="id = '".$id."'";
	else {
	$idliste=explode(",",$id);
	$antal=count($idliste);
		$tmp="(id = '".$idliste[0]."'";
		for($x=1;$x<$antal;$x++) $tmp.=" or id = '".$idliste[$x]."'";
		$tmp.=")";
	} 
	$x=0;$moms=0;$sum=0;$modtaget=0;$modtaget2=0;
	$betaling=array(); #20150518 (Denne og de to næste er flyttet fra "over select * from pos_betalinger")
	$modtaget=array(); #20150518
	$bnr=-1; #20150518
	$qtxt="select * from ordrer where $tmp";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$art=$r['art'];
		$afd=$r['afd']*1; # 21081206
		if ($kilde=='Dagsafslutning') $art='PO';
		$ordre_id=$r['id']; #20120905
		$konto_id=$r['konto_id'];
		$kontonr=str_replace(" ","",$r['kontonr']);
		$kundeordnr=trim($r['kundeordnr']);
		$firmanavn=trim($r['firmanavn']);
		$modtagelse=$r['modtagelse'];
		$transdate=($r['fakturadate']);
		$fakturanr=$r['fakturanr'];
		$ordrenr=$r['ordrenr'];
		$momssats=$r['momssats']*1; 
		$valuta=$r['valuta'];
		$kred_ord_id=$r['kred_ord_id'];
		if (!$valuta) $valuta='DKK';
		$projekt[0]=$r['projekt'];
		$betalingsbet=$r['betalingsbet'];
		$betalingsdage=$r['betalingsdage']*1;
		$betalt=$r['betalt']*1;
		$felt_1=$r['felt_1'];
		$felt_2=$r['felt_2'];
		$felt_3=$r['felt_3'];
		$felt_4=$r['felt_4'];
		$betalings_id=$r['betalings_id'];
		if ($felt_1 && $felt_3 && is_numeric($felt_2) && is_numeric($felt_4)) { #20171004 Alm. ordre der behandles som pos
			$qtxt="select id from pos_betalinger where ordre_id='$ordre_id' limit 1";
			if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $art='PO'; 
			else {
				$qtxt="insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs)values('$id','$felt_1','$felt_2','DKK','100')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				if ($felt_4 > 0) {
					$qtxt="insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs)values('$id','$felt_3','$felt_4','DKK','100')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$art='PO';
			}
		}
		if ($art=='PO') { #20150505
			$qtxt="select * from pos_betalinger where ordre_id='$ordre_id' order by betalingstype";
			$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				if (in_array($r2['betalingstype'],$betaling)) {
					for ($c=0;$c<count($betaling);$c++) {
						if ($betaling[$c]==$r2['betalingstype']) {
							$modtaget[$c]+=$r2['amount'];
						}
					}
				} else {
					$bnr++;
					$betaling[$bnr]=$r2['betalingstype'];
					$modtaget[$bnr]=$r2['amount'];
				}
			} 
			$kasse=$r['felt_5']*1;
			if ($betalingsbet=='Kontant') {
				$konto_id=0;
				$kontonr=NULL;
			} 
		} else $kasse=0;
		if ($momssats) $moms+=$r['moms']*1;
		$sum+=$r['sum']+$r['moms'];
		$ordreantal=$x;
		$forfaldsdate=usdate(forfaldsdag($r['fakturadate'], $betalingsbet, $betalingsdage));
		if ($art=='PO') $r2= db_fetch_array(db_select("select id, afd from ansatte where initialer = '$r[ref]'",__FILE__ . " linje " . __LINE__));
		else $r2= db_fetch_array(db_select("select id, afd from ansatte where navn = '$r[ref]'",__FILE__ . " linje " . __LINE__));
#		$afd=$r2['afd']*1;#sikkerhed for at 'afd' har en vaerdi # udkommenteret 20181128
		$ansat=$r2['id']*1;
		if ($no_faktbill==1) $bilag='0';
		else $bilag=trim($fakturanr);
		$udlign=0;
		$qtxt="select pris,vat_price,antal,rabat,momssats,momsfri,vat_account from ordrelinjer where ordre_id='$ordre_id' order by vat_account";
		$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)) {
			if ($r2['momssats'] && !$r2['momsfri']) {
				if (!$r2['vat_account']) $r2['vat_account']=$vatAccount[0]; # kan fjernes fra ver 3.7.6 
				$lineTotal=afrund($r2['pris']*$r2['antal']-$r2['pris']*$r2['antal']*$r2['rabat']/100,2);
				$lineVat=afrund($lineTotal*$r2['momssats']/100,2);
				for ($v=0;$v<count($vatAccount);$v++) {
					if ($r2['vat_account']==$vatAccount[$v]) {
						$exVatAmount[$v]+=$lineTotal;
						$vatAmount[$v]+=$lineVat;
						$lineVatTotal+=$lineVat;
					}
				}
			}
		}
	}
	if ($kilde=='Dagsafslutning') { #20180911
		$konto_id='0';
		$kontonr=NULL;
	}
	if ($konto_id && $kontonr) {
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$debitorgruppe=$r['gruppe'];
		$r = db_fetch_array(db_select("select box1 from grupper where art='DG' and kodenr='$debitorgruppe'",__FILE__ . " linje " . __LINE__));
		$momskode=substr(trim($r['box1']),1,1);
		if ($moms && !$momskode) return("Debitorgruppe $debitorgruppe ikke tilnkyttet en momsgruppe"); 
	} else { #saa er det en kontantordre
		$tmparray=array();
		$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
		$tmparray=explode(chr(9),$r['box7']);
		$momskode=$tmparray[$kasse-1];
		if ($moms && !$momskode) return("Fejl i momskode for kasse $kasse!");
	}
	if (!is_numeric($id) || $betalingsbet=='Kontant' || $betalingsbet=='Kreditkort' || ($betalingsbet=='Forud' && $art=='PO' && $felt_1 != 'konto')) { #20130820+20150306
		$tmp=NULL;
		$openpost=NULL;
		$r=db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
		if ($betalingsbet=="Kontant") $tmp=$r['box7'];
		elseif ($betalingsbet=="Kreditkort") $tmp=$r['box10'];
		if ($tmp) {
			if (in_array($tmp,$kontoplan)) {
				$openpost=NULL;
				$kontonr=$tmp;
			} elseif ($art!='PO') {	#20170802
				  $tekst="Kontonummer for $betalingsbet er ikke gyldigt\\nTjek Indstillinger > Diverse >Ordrerelaterede valg";
				return($tekst);
			} else $openpost=NULL;
		} else $openpost=1;
	} elseif ($art=='PO' && !$kontonr) $openpost=NULL; #20130917 + #20140115 
	else $openpost=1;
	if (substr($art,1,1)=='K' && $openpost && $kred_ord_id) {
		$beskrivelse ="Kreditnota - ".$fakturanr;
		$r=db_fetch_array(db_select("select fakturanr,fakturadate from ordrer where id='$kred_ord_id'",__FILE__ . " linje " . __LINE__));
		$tmp=$sum*-1;
		if ($r2=db_fetch_array(db_select("select * from openpost where konto_id='$konto_id' and amount='$tmp' and faktnr='$r[fakturanr]' and transdate='$r[fakturadate]' and udlignet != '1'",__FILE__ . " linje " . __LINE__))) {
			($transdate>$r2['transdate'])?$udlign_date=$transdate:$udlign_date=$r2['transdate'];
			$r2=db_fetch_array(db_select("select max(udlign_id) as udlign_id from openpost",__FILE__ . " linje " . __LINE__)); 
			$udlign_id=$r2['udlign_id']+1;
			db_modify("update openpost set udlignet='1',udlign_date='$udlign_date',udlign_id='$udlign_id' where konto_id='$konto_id' and amount='$tmp' and faktnr='$r[fakturanr]' and transdate='$r[fakturadate]'",__FILE__ . " linje " . __LINE__);
			$udlign=1;
		}
	} elseif ($art=='PO') {
		(is_numeric($id))?$beskrivelse="Bon - ".$fakturanr:$beskrivelse="Kontantsalg kasse - ".$kasse;
		if ($kilde=='Dagsafslutning') $beskrivelse=$kilde." - kassenr: ".$kasse;
		elseif ($webservice && $betalings_id) $beskrivelse="Web: ". $kundeordnr .", Bet.ID: ".$betalings_id; #20181009
	} elseif ($openpost) $beskrivelse="Faktura - ".$fakturanr;
	elseif ($webservice) $beskrivelse="Web: ". $kundeordnr .", Fakt: ".$fakturanr; #20180914
	elseif  ($betalingsbet=="Kontant") $beskrivelse="Kontantsalg: Faktura - ".$fakturanr;
	else $beskrivelse="Kreditkort salg: Faktura - ".$fakturanr;
	$qtxt="select id,ordre_id from transaktioner where ordre_id='$ordre_id'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$tekst="Bogf&oslash;ring afbrudt - tjek kontrolspor (id $r[id] oid $r[ordre_id])";
		print "<BODY onLoad=\"javascript:alert('$tekst')\">";
		return($tekst);
	}
$hmlog=fopen("../temp/$db/hmlog.log","a");
	if (is_numeric($id)) $id*=1;
fwrite ($hmlog, __line__." $kontonr && $konto_id && $openpost && ". is_integer($id) ."\n");
#cho __line__." $kontonr && $konto_id && $openpost && ". is_integer($id) ."<br>";
	if ($kontonr && $konto_id && $openpost && is_integer($id)) { #20161211
		$tmp=$sum;
		$qtxt="select id from openpost where konto_id='$konto_id' and konto_nr='$kontonr' and faktnr='$fakturanr' and amount='$tmp' ";
		$qtxt.="and beskrivelse='$beskrivelse' and udlignet='$udlign' and transdate='$transdate' and kladde_id='$udlign' and refnr='$id' ";
		$qtxt.="and valuta='$valuta' and valutakurs='$valutakurs' and forfaldsdate='$forfaldsdate'";
fwrite ($hmlog, __line__."  $qtxt\n");
		if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$tekst="Bogf&oslash;ring afbrudt - tjek kontrolspor";
			print "<BODY onLoad=\"javascript:alert('$tekst')\">";
			return($tekst);
		}
		if ($udlign && $udlign_id && $udlign_date) { 
			$qtxt="insert into openpost (konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,udlign_id,udlign_date,transdate,uxtid,kladde_id,refnr,";
			$qtxt.="valuta,valutakurs,forfaldsdate,projekt) values ('$konto_id','$kontonr','$fakturanr','$tmp','$beskrivelse','$udlign','$udlign_id',";
			$qtxt.="'$udlign_date','$transdate','$uxtid','$udlign','$id','$valuta','$valutakurs','$forfaldsdate','$projekt[0]')";
		} else {
			$qtxt="insert into openpost (konto_id,konto_nr,faktnr,amount,beskrivelse,udlignet,transdate,uxtid,kladde_id,refnr,valuta,valutakurs,";
			$qtxt.="forfaldsdate,projekt) values ('$konto_id','$kontonr','$fakturanr','$tmp','$beskrivelse','$udlign','$transdate','$uxtid','$udlign',";
			$qtxt.="'$id','$valuta','$valutakurs','$forfaldsdate','$projekt[0]')";
		}
fwrite ($hmlog, __line__."  $qtxt\n");
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select max(id) as id from openpost where konto_id = '$konto_id' and faktnr = '$fakturanr' and refnr='$id'",__FILE__ . " linje " . __LINE__));
		$openpost_id=$r['id'];
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$r = db_fetch_array(db_select("select beskrivelse, box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['box2']; # Kontonr aendres fra at vaere leverandoerkontonr til finanskontonr
			
		$tekst="Kontonummer for Debitorgruppe `$r[beskrivelse]` er ikke gyldigt";
		if (!$kontonr && $webservice) return($tekst);
		elseif(!$kontonr) print "<BODY onLoad=\"javascript:alert('$tekst')\">";
	}
#	fwrite($log, __line__." if ($webservice)\n");
	if ($webservice) { # 20181128
		$log=fopen("../temp/$db/bfweb.log","a");
		fwrite ($log, "\n".date("H:s:i")."\n");
		$qtxt="select * from grupper where art = 'POS' and kodenr = '1'";
		fwrite ($log, __line__." $qtxt\n");
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$korttyper=explode(chr(9),$r['box5']);
		$kortkonti=explode(chr(9),$r['box6']);
		$txt= "if (";
		$txt.= count($korttyper);
		$txt.= " && ";
		$txt.= $felt_2;
		$txt.= " == ";
		$txt.=$sum;
		$txt.= ")";
		fwrite($log, __line__." $txt\n");
		if (count($korttyper) && $felt_2 == $sum) { # 20181128
			for ($b=0;$b<count($korttyper);$b++) {
				fwrite($log, __line__." if ($korttyper[$b]==$felt_1)\n");
				if (strtolower($korttyper[$b]) == strtolower($felt_1)) {
					$kontonr=$kortkonti[$b];
					fwrite($log, __line__." $kontonr=$kortkonti[$b]\n");
				}
			}
		}
	}
	fclose($log);

	if (!in_array($kontonr,$kontoplan) && $art!='PO' && !$webservice) {
		$tekst="Kontonummer $kontonr findes ikke i kontoplanen for regnskabsaar $regnaar";
		return($tekst);
		exit;
	}
# if "$art=='PO' && $sum<br>"; # 20181127 rettet fra ($art=='PO' && $sum) da der ikke er nogen sum ved hævning af kontanter!!
	if ($art=='PO') { #saa er det en kontantordre (POS) 
	$retur=$sum;
		$tmparray=array();
		$r=db_fetch_array(db_select("select box6 from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
		$div_kort_kto=trim($r['box6']);
		$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
		if (!$konto_id) { #20140616
			$tmparray=explode(chr(9),$r['box2']);
			$kassekto=$tmparray[$kasse-1];
			$kontonr=$kassekto;
		} #else
		$tmparray=explode(chr(9),$r['box3']);
#		if (!$afd) $afd=$tmparray[$kasse-1]*1; # udkommenteret 20181128
		$tmparray=explode(chr(9),$r['box5']);
		$kortantal=$r['box4']*1;
		$korttyper=explode(chr(9),$r['box5']);
		$kortkonti=explode(chr(9),$r['box6']);
		$kortnavn=NULL; 
		if ($div_kort_kto) { #20140129
			for ($b=0;$b<count($betaling);$b++) { #20150505
				if (strpos($betaling[$b],"|")) list($betaling[$b],$kortnavn)=explode("|",$betaling[$b]);
			}
			$korttyper[$kortantal]='Betalingskort';
			$kortkonti[$kortantal]=$div_kort_kto;
			$kortantal++;
		}
		for($x=0;$x<$kortantal;$x++) { #20150505
			for($b=0;$b<count($betaling);$b++) { 
				if (strtolower($betaling[$b])==strtolower($korttyper[$x])) { #20170816
					$kontonr=$kortkonti[$x]; #20150507
					$debet=afrund($modtaget[$b],2);
					$kredit='0';
					$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
					$retur=$retur-$modtaget[$b];
					$sum=$sum-$modtaget[$b];
					if ($debet) {
						if (is_numeric($id)) {
							$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kontonr','$fakturanr','$debet','$kredit','0',$afd,'$logdate','$logtime','$projekt[0]','$ansat','$id','$kasse')";
						} else {
							$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kontonr','0','$debet','$kredit','0',$afd,'$logdate','$logtime','$projekt[0]','$ansat','0','$kasse')";
						}
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt="update kontoplan set saldo=saldo+'$debet' where kontonr='$kontonr' and regnskabsaar='$regnaar'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
			}
		}
		if (!$konto_id) $kontonr=$kassekto; #20150518 (Ellers fortsætter den med at bogføre på samme kontonr) #20150521 
	}
#xit;
	$sum=afrund($sum,3);
	if ($sum) {
		if ($sum>0) {$debet=$sum; $kredit='0';}
		else {$debet='0'; $kredit=$sum*-1;}
		if ($valutakurs) {
			$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);
		} # Omregning til DKR.
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
	
		$debet=afrund($debet,2);
		$kredit=afrund($kredit,2);
		if (is_numeric($id)) {
			$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kontonr','$fakturanr','$debet','$kredit','0',$afd,'$logdate','$logtime','$projekt[0]','$ansat','$id','$kasse')";
		} else {
			$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kontonr','0','$debet','$kredit','0',$afd,'$logdate','$logtime','$projekt[0]','$ansat','0','$kasse')";
		}
fwrite ($hmlog, __line__."  $qtxt\n");
fclose ($hmlog);	
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$tmp=$debet-$kredit;
		$qtxt="update kontoplan set saldo=saldo+'$tmp' where kontonr='$kontonr' and regnskabsaar='$regnaar'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	if ($valutakurs) $maxdif=2; #Der tillades 2 oeres afrundingsdiff
	$p=0;
	$projektliste='';
	if (is_numeric($id)) $tmp="ordre_id = '".$id."'";
	else {
		$idliste=explode(",",$id);
		$antal=count($idliste);
		$tmp="(ordre_id = '".$idliste[0]."'";
		for($x=1;$x<$antal;$x++) $tmp.=" or ordre_id = '".$idliste[$x]."'";
		$tmp.=")";
	} 
	db_modify("update ordrelinjer set projekt='' where projekt is NULL and $tmp",__FILE__ . " linje " . __LINE__);
	$q = db_select("select distinct(projekt) from ordrelinjer where $tmp and vare_id >'0'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$p++;
		$projekt[$p]=trim($r['projekt']);
		($projektliste)?$projektliste.="<br>".$projekt[$p]:$projektliste=$projekt[$p];
	}
	($p)?$projektantal=$p:$projektantal=1;
	if ($projektliste && $openpost) {
		db_modify("update openpost set projekt='$projektliste' where id='$openpost_id'",__FILE__ . " linje " . __LINE__);
	}
	for ($t=1;$t<=2;$t++)	{
		for ($p=1;$p<=$projektantal;$p++) {
			$y=0;
			$tjek= array();
			$bogf_konto = array();
			if (is_numeric($id)) $tmp="ordre_id = '".$id."'";
			else {
				$idliste=explode(",",$id);
				$antal=count($idliste);
				$tmp="(ordre_id = '".$idliste[0]."'";
				for($x=1;$x<$antal;$x++) $tmp.=" or ordre_id = '".$idliste[$x]."'";
				$tmp.=")";
			} 
			if ($t==1) {
				$qtxt="select * from ordrelinjer where $tmp and projekt='$projekt[$p]' and posnr>='0' and bogf_konto > 0 order by bogf_konto,vat_account"; #20191127
			} else {
				$qtxt="select * from ordrelinjer where $tmp and projekt='$projekt[$p]' and posnr<'0' order by bogf_konto,vat_account";
			}
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
			if ($valutakurs && $valutakurs!=100) $maxdif=$maxdif+2; #Og yderligere 2 pr ordrelinje.
				$tmp=$r['bogf_konto']."|".$r['vat_account'];
#cho __line__." $tmp -> $r[momsfri] -> $r[beskrivelse]<br>";
#				if (!in_array($tmp,$bogf_vat) && $r['bogf_konto']) {

				if (!in_array($r['bogf_konto'],$bogf_konto)) {
					$y++;
					$bogf_konto[$y]=$r['bogf_konto'];
					$vat_account[$y]=$r['vat_account'];
#cho __line__." $bogf_konto[$y] $vat_account[$y]<br>";
					$bogf_vat[$y]=$bogf_konto[$y]."|".$vat_account[$y];
#cho __line__." $bogf_vat[$y]<br>";

					if ($r['rabatart']=='amount') {
						$linjesum=$r['pris']*$r['antal']-($r['rabat']*$r['antal']); #20140424b
						($r['procent'] || $r['procent']!='')?$pris[$y]=$linjesum*$r['procent']/100:$pris[$y]=$linjesum; #20140424b
						} else {
						$linjesum=$r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100); #20140424b
						($r['procent'] || $r['procent']!='')?$pris[$y]=$linjesum*$r['procent']/100:$pris[$y]=$linjesum; #20140424b
						if ($art=='PO') $pris[$y]=afrund($pris[$y],3);
						else $pris[$y]=afrund($pris[$y],3); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104 -- 2011.02.07 ændret til 2 decimaler ordre_id 1325 saldi_329
					}
					($r['momssats'] && !$r['momsfri'])?$linjemoms[$y]=afrund($linjesum/100*$r['momssats'],3):$linjemoms[$y]=0; #20191127

				}	else {
					for ($a=1; $a<=$y; $a++) {
#cho __line__." $bogf_konto[$y]==$r[bogf_konto] && $vat_account[$a] == $r[vat_account]<br>";
						if ($bogf_konto[$a]==$r['bogf_konto'] && $vat_account[$a] == $r['vat_account']) {
							if ($r['rabatart']=='amount') {
								$linjesum=$r['pris']*$r['antal']-($r['rabat']*$r['antal']); #20140424b
							} else {
								$linjesum=$r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100); #20140424b
							}
							($r['procent'] || $r['procent']!='')?$pris[$a]+=$linjesum*$r['procent']/100:$pris[$a]+=$linjesum; #20140424b
							if ($art=='PO') $pris[$a]=afrund($pris[$a],3);
							else $pris[$a]=afrund($pris[$a],3); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104 -- 2011.02.07 ændret til 2 decimaler ordre_id 1325 saldi_329
							if ($r['momssats'] && !$r['momsfri']) $linjemoms[$a]+=afrund($linjesum/100*$r['momssats'],3);
						}
					}
				}
			}
			$ordrelinjer=$y;
			if ($indbetaling) $ordrelinjer=0;
			for ($y=1;$y<=$ordrelinjer;$y++) {
				if ($bogf_konto[$y] && $pris[$y]) {
					if ($pris[$y]>0) {
					$kredit=$pris[$y];$debet=0;
						$vat=$linjemoms[$y]*-1;
					} else {
						$kredit=0; 
						$debet=$pris[$y]*-1;
						$vat=$linjemoms[$y]*-1;
					}
					if ($t==1 && $valutakurs) {
						$kredit=$kredit*$valutakurs/100;
						$debet=$debet*$valutakurs/100;
						$vat=$vat*$valutakurs/100;
					} # Omregning til DKR.
						$kredit=afrund($kredit,3);$debet=afrund($debet,3);
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
						$debet=afrund($debet,2);
						$kredit=afrund($kredit,2);
						$vat=afrund($vat,2);
#						$linjemoms[$y]=afrund($linjemoms[$y]*1,2);
						if (is_numeric($id)) {
							$qtxt = "insert into transaktioner ";
							$qtxt.= "(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
							$qtxt.= "projekt,ansat,ordre_id,kasse_nr,moms)";
							$qtxt.= " values ";
							$qtxt.= "('0','$transdate','$beskrivelse','$bogf_konto[$y]','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime',";
							$qtxt.= "'$projekt[$p]','$ansat','$id','$kasse',$vat)";
						} else {
							$qtxt="insert into transaktioner ";
							$qtxt.= "(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
							$qtxt.= "projekt,ansat,ordre_id,kasse_nr,moms)";
							$qtxt.= " values ";
							$qtxt.= "('0','$transdate','$beskrivelse','$bogf_konto[$y]','0','$debet','$kredit','0','$afd','$logdate',";
							$qtxt.= "'$logtime','$projekt[$p]','$ansat','0','$kasse',$vat)";
						}
#cho __line__." $qtxt<br>";						
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$tmp=$debet-$kredit;
						$qtxt="update kontoplan set saldo=saldo+'$tmp' where kontonr='$bogf_konto[$y]' and regnskabsaar='$regnaar'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						if ($linjemoms[$y]) {
							if ($linjemoms[$y]>0) {$kredit=$linjemoms[$y];$debet=0;}
							else {$kredit=0; $debet=$linjemoms[$y]*-1;}
							if ($t==1 && $valutakurs) {$kredit=$kredit*$valutakurs/100;$debet=$debet*$valutakurs/100;} # Omregning til DKR.
							$kredit=afrund($kredit,3);$debet=afrund($debet,3);
							$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
							$debet=afrund($debet,2);
							$kredit=afrund($kredit,2);
							if (is_numeric($id)) {
								$qtxt = "insert into transaktioner ";
								$qtxt.= "(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
								$qtxt.= "projekt,ansat,ordre_id,kasse_nr,moms)";
								$qtxt.= " values ";
								$qtxt.= "('0','$transdate','$beskrivelse','$vat_account[$y]','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime',";
								$qtxt.= "'$projekt[$p]','$ansat','$id','$kasse',0)";
							} else {
								$qtxt="insert into transaktioner ";
								$qtxt.= "(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
								$qtxt.= "projekt,ansat,ordre_id,kasse_nr,moms)";
								$qtxt.= " values ";
								$qtxt.= "('0','$transdate','$beskrivelse','$vat_account[$y]','0','$debet','$kredit','0','$afd','$logdate',";
								$qtxt.= "'$logtime','$projekt[$p]','$ansat','0','$kasse',0)";
							}
#cho __line__." $qtxt<br>";						
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							$tmp=$debet-$kredit;
							$qtxt="update kontoplan set saldo=saldo+'$tmp' where kontonr='$vat_account[$y]' and regnskabsaar='$regnaar'";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						}
					} elseif ($pris[$y]) {
						$svar="Fejl i kontoopsætning";
						if (!$webservice) print "<BODY onLoad=\"javascript:alert('$svar')\">";
						else return("$svar");
						exit;
					}
				}
			}
		}
		$moms=afrund($moms,2);
		$lineVatTotal=afrund($lineVatTotal,2);
		while (afrund($moms-$lineVatTotal,2) >= 0.01) { #20190311
			for ($v=0;$v<count($vatAccount);$v++) {
				if (afrund($moms-$lineVatTotal,2) >= 0.01) {
					$vatAmount[$v]+=0.01; 
					$lineVatTotal+=0.01;
					$moms=afrund($moms,2);
					$lineVatTotal=afrund($lineVatTotal,2);
				}
			}
		}
		while (afrund($lineVatTotal-$moms,2) >= 0.01) { #20190311
			for ($v=0;$v<count($vatAccount);$v++) {
				if (afrund($lineVatTotal-$moms,2) >= 0.01) {
					$vatAmount[$v]-=0.01; 
					$lineVatTotal-=0.01;
					$moms=afrund($moms,2);
					$lineVatTotal=afrund($lineVatTotal,2);
				}
			}
		}
		if (abs($moms-$lineVatTotal) >= 0.01) {
			alert("Momsfejl $moms != $lineVatTotal");
			return("Momsfejl $moms != $lineVatTotal");
			exit;
		}
#cho __line__." M $moms LM $lineVatTotal<br>"; 				
		
#xit;
#				$exVatAmount[$v]+=$lineTotal;
#				$vatAmount[$v]+=$lineVat;
#				$lineVatTotal+=$lineVat;
/*
		for ($v=0;$v<count($vatAccount);$v++) {
			if ($vatAmount[$v] > 0) {$kredit=$vatAmount[$v]; $debet='0';}
			else {$kredit='0'; $debet=$vatAmount[$v]*-1;}
			if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.
			$kredit=afrund($kredit,3);$debet=afrund($debet,3);
			$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
			$diff=afrund($d_kontrol-$k_kontrol,3);
			$absdiff=abs($diff);
			if ($vatAmount[$v] && $valutakurs && $valutakurs!=100 && $absdiff>=0.01 && $absdiff<=0.05) {
				if ($debet > 0) {
					$debet=$debet+$diff;
					$d_kontrol=$d_kontrol+$diff;
				} elseif ($kredit > 0) {
					$kredit=$kredit+$diff;
					$k_kontrol=$k_kontrol+$diff;
				}
			}
			if ($debet || $kredit) { # 20190220
				if (is_numeric($id)) {
					$qtxt ="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id,kasse_nr)";
					$qtxt.=" values ";
					$qtxt.="('0', '$transdate', '$beskrivelse', '$vatAccount[$v]', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id','$kasse')";
				} else {
					$qtxt ="insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id,kasse_nr)";
					$qtxt.=" values ";
					$qtxt.="('0', '$transdate', '$beskrivelse', '$vatAccount[$v]', '0', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '0','$kasse')";
				}
#cho __line__." $qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$tmp=$debet-$kredit;
				$qtxt="update kontoplan set saldo=saldo+'$tmp' where kontonr='$vatAccount[$v]' and regnskabsaar='$regnaar'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
*/		
/*		
		if ($momskode) {
			$query = db_select("select box1 from grupper where art='SM' and kodenr='$momskode'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			if ($box1=trim($row['box1'])) {# 20150522 
				if ($moms > 0) {$kredit=$moms; $debet='0';}
				else {$kredit='0'; $debet=$moms*-1;}
				if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.
				$kredit=afrund($kredit,3);$debet=afrund($debet,3);
				$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
				$diff=afrund($d_kontrol-$k_kontrol,3);
				$absdiff=abs($diff);
				if ($moms && $valutakurs && $valutakurs!=100 && $absdiff>=0.01 && $absdiff<=0.05) {
					if ($debet > 0) {
						$debet=$debet+$diff;
						$d_kontrol=$d_kontrol+$diff;
					} elseif ($kredit > 0) {
						$kredit=$kredit+$diff;
						$k_kontrol=$k_kontrol+$diff;
					}
				}
				$moms=afrund($moms,2);
				if ($moms) {
					if (is_numeric($id)) {
						$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id,kasse_nr) values ('0', '$transdate', '$beskrivelse', '$box1', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id','$kasse')";
					} else {
						$qtxt="insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id,kasse_nr) values ('0', '$transdate', '$beskrivelse', '$box1', '0', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '0','$kasse')";
					}
#cho __LINE__." $qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$tmp=$debet-$kredit;
					$qtxt="update kontoplan set saldo=saldo+'$tmp' where kontonr='$box1' and regnskabsaar='$regnaar'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$valutakurs=$valutakurs*1;
			} elseif ($moms) return ("Fejl i momsopsætning");
 
		}
*/		
		if (is_numeric($id)) {
			$tmp="id = '".$id."'";
		}
		else {
			$idliste=explode(",",$id);
			$antal=count($idliste);
			$tmp="(id = '".$idliste[0]."'";
			for($x=1;$x<$antal;$x++) $tmp.=" or id = '".$idliste[$x]."'";
			$tmp.=")";
		}
		$tidspkt=date("H:i");
		db_modify("update ordrer set status='4',valutakurs='$valutakurs' where $tmp",__FILE__ . " linje " . __LINE__);
		if (is_numeric($id)) $tmp="ordre_id = '".$id."'";
		else {
			$idliste=explode(",",$id);
			$antal=count($idliste);
			$tmp="(ordre_id = '".$idliste[0]."'";
			for($x=1;$x<$antal;$x++) $tmp.=" or ordre_id = '".$idliste[$x]."'";
			$tmp.=")";
		} 
		db_modify("delete from ordrelinjer where $tmp and posnr < 0",__FILE__ . " linje " . __LINE__);
		$d_kontrol=afrund($d_kontrol,2);
		$k_kontrol=afrund($k_kontrol,2);
	if ($diff=afrund($d_kontrol-$k_kontrol,2)) {
		$debet=0; $kredit=0;
		if ($diff<0) $debet=$diff*-1;
		else $kredit=$diff;
		$debet=afrund($debet,2);
		$kredit=afrund($kredit,2);
		if ($art=='PO') { #20140628
		$r=db_fetch_array(db_select("select box9 from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__)); #20170826 +4 linjer
		$diffkonti=explode(chr(9),$r['box9']);
		$difkto=$diffkonti[$kasse-1];
		$maxdiff=0.5;
/* 20170826
		$r= db_fetch_array(db_select("select box1,box2 from grupper where grupper.art='OreDif'",__FILE__ . " linje " . __LINE__));
		$difkto=$r['box2']*1;
		$maxdif=$r['box1']*100;
*/
		if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))) {
			return("Kontonr $difkto (Øredifferencer) eksisterer ikke");
		}
		$diff=0;
		$q=db_select("select debet,kredit from transaktioner where beskrivelse='$beskrivelse' and logdate='$logdate' and logtime='$logtime' and kasse_nr='$kasse'",__FILE__ . " linje " . __LINE__);
		while ($r= db_fetch_array($q)) {
			$d=afrund($r['debet'],2);
			$k=afrund($r['kredit'],2);
			$diff+=$d-$k;
		}
		if ($diff) {
			if ($diff<0) $debet=$diff*-1;
			else $kredit=$diff;
			$debet=afrund($debet,2);
			$kredit=afrund($kredit,2);
			if (is_numeric($id)) {
				$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$difkto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id','$kasse')";
			} else {
				$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$difkto','0','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','0','$kasse')";
			}
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$tmp=$debet-$kredit;
			db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
		}
	} elseif ($valuta!='DKK' && abs($diff)<=$maxdif) { #Der maa max vaere en afvigelse paa 1 oere pr ordrelinje m fremmed valuta;
 		$r= db_fetch_array(db_select("select box3 as kursdifkto from grupper where grupper.art='VK' and grupper.box1='$valuta'",__FILE__ . " linje " . __LINE__));
			$kursdifkto=$r['kursdifkto']*1;
			if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$kursdifkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))) {
				return("Kontonr $kursdifkto (kursdiff) eksisterer ikke");
			}
			if (is_numeric($id)) {
				$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kursdifkto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id','$kasse')";
			} else {
				$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$kursdifkto','0','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','0','$kasse')";
			}
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$tmp=$debet-$kredit;
			db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$kursdifkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
		} elseif (abs($diff) < 0.05) {
			$r = db_fetch_array(db_select("select * from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__));
			$difkto=$r['box2'];
			if (!$difkto) {
				return (__line__.' Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff');
			}
			$qtxt="select id from kontoplan where kontotype = 'D' and kontonr = '$difkto' and regnskabsaar='$regnaar'";
#cho __line__." $qtxt<br>";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				if (is_numeric($id)) {
					$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$difkto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','$id','$kasse')";
				} else {
					$qtxt="insert into transaktioner (bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr) values ('0','$transdate','$beskrivelse','$difkto','0','$debet','$kredit','0','$afd','$logdate','$logtime','$projekt[0]','$ansat','0','$kasse')";
				}
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$tmp=$debet-$kredit;
				db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
			} else {
				return (__line__.' Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff');
				}
		} else {
			$svar="Der er konstateret en uoverensstemmelse i posteringssummen, ID $ordre_id ordre $ordrenr, d=$d_kontrol, k=$k_kontrol kontakt DANOSOFT p&aring; telefon 4690 2208";
			if ($art=='PO') echo "$svar<br>";
			$message=$db." | Uoverensstemmelse i posteringssum: ordre_id=$id, d=$d_kontrol, k=$k_kontrol | ".__FILE__ . " linje " . __LINE__." | ".$brugernavn." ".date("Y-m-d H:i:s");
			$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'SALDI Fejl', $message, $headers);
			return("$svar"); #20130506
			exit;
		}
	}
	# 201701004 ->
	$qtxt="select debet,kredit from transaktioner where beskrivelse='$beskrivelse' and logdate='$logdate' and logtime='$logtime' and kasse_nr='$kasse'";
#cho __line__." $qtxt<br>";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$diff=0;
	while ($r= db_fetch_array($q)) {
		$diff+=afrund($r['debet'],2)-afrund($r['kredit'],2);
	}
	if ($diff=afrund($diff,2)) {
		if ($diff > 0) {
			$debet=0;
			$kredit=$diff;
		} else {
			$debet=$diff*-1;
			$kredit=0;
		}
		if (!$difkto) {
			$r = db_fetch_array(db_select("select * from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__));
			$difkto=$r['box2'];
			if (!$difkto) return ('Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff');
		}
		$qtxt="insert into transaktioner ";
		$qtxt.="(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,projekt,ansat,ordre_id,kasse_nr)";
		$qtxt.=" values ('0','$transdate','$beskrivelse','$difkto','$fakturanr','$debet','$kredit','0','$afd','$logdate','$logtime',";
		$qtxt.="'$projekt[0]','$ansat',";
		(is_numeric($id))?$qtxt.="'$id',":$qtxt.="'0',";
		$qtxt.="'$kasse')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$tmp=$debet-$kredit;
		db_modify("update kontoplan set saldo=saldo+'$tmp' where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
	}	
	# <- 201701004
	if ($konto_id) {
		$r=db_fetch_array(db_select("select sum(amount) as amount from openpost where udlignet!='1' and konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		$saldo=$r['amount']*1;
		db_modify("update adresser set saldo='$saldo' where id='$konto_id'",__FILE__ . " linje " . __LINE__);
	}
	if ($title != "Massefakturering" && !$webservice && $art !='PO') genberegn($regnaar);
#xit;
	
	return($svar);
} # endfunc bogfor_nu

######################################################################################################################################
function stamkunder($art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt,$sum) {
	$stil="STYLE=\"
		display: table-cell;
		moz-border-radius:10px;
		-webkit-border-radius:10px;
		width:109px;
		height:64px;
		text-align:center;
		vertical-align:middle;
		font-size:14px; 
		border: 1px solid ##BEBCCE;
		white-space: normal;
		background-color:";

	$x=0;
	$linje=0;
	$linjer=10;
	$kolonner=12;
	$start=0;
	if (isset($_GET['stamkunder']))$start=$_GET['stamkunder'];
	$qtxt="select * from adresser where lukket!='on' and art ='D' and kreditmax >= '1' and betalingsdage >= '1' order by firmanavn";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$konto_id[$x]=$r['id'];
		$kontonr[$x]=$r['kontonr'];		
		$firmanavn[$x]=$r['firmanavn'];
		$kreditmax[$x]=$r['kreditmax'];
		if ($r['betalingsdage']==0) $kreditmax[$x]=0;  
		$f1[$x]=strtolower(substr($firmanavn[$x],0,1));
		$r2=db_fetch_array(db_select("select sum(amount) as saldo from openpost where konto_id='$konto_id[$x]'",__FILE__ . " linje " . __LINE__));
		$saldo[$x]=$r2['saldo']*1;
		$x++;
	}

	$href="pos_ordre.php";
	$find="";
	$fokus="kontonr";

# and (betalingsdage > '0' or betalingsbet='Lb. md.')
	$style=$stil."$bgcolor\"";	
	$kontantknap="<input type=\"button\" $style onclick=\"window.location.href='$href?fokus=$fokus&id=$id&konto_id=slet'\" value=\"Kontantkunde\">";
	
	print "<table border=\"0\" width=\"100%\"><tbody>";
	for ($x=$start;$x<count($konto_id);$x++) {
		if ($linje==0 && $y==$kolonner) {
			print "<td>$kontantknap</td>";
			print "</tr><tr>";
			$linje++;
			if ($linje==$linjer) break 1;
			$y=0;
		}
		if ($y>=$kolonner) {
		print "</tr><tr>";
		$y=0;
	} elseif ($y && $y<$kolonner && $f1[$x] && $f1[$x]!=$f1[$x-1]) {
		if ($linje==0) {
			for ($i=$y+1;$i<$kolonner;$i++) print "<td style=\"width:110px\"></td>";
			print "<td>$kontantknap</td>";
		}
		if ($linje==$linjer-1) {
			for ($i=$y+1;$i<$kolonner;$i++) print "<td></td>";
				print "</tr><tr><td>";
				for ($i=1;$i<$kolonner;$i++) print "</td><td>";
				$style=$stil."yellow\"";
			$style=$stil."yellow\"";
			print "<input type=\"button\" $style onclick=\"window.location.href='$href?fokus=$fokus&id=$id&stamkunder=$x'\" value=\"Næste side\">";
			print "</td>";
		}
		print "</tr><tr>";
		$linje++;
		if ($linje==$linjer) {
#			print "<td>";
#			$style=$stil."yellow\"";
#			$tmp=0; #$x-($linjer*2);
#			print "<input type=\"button\" $style onclick=\"window.location.href='$href?fokus=$fokus&id=$id&stamkunder=$tmp'\" value=\"Forrige side\">";
#			print "</td>";
			break 1;
		}
		$y=0;
		if ($y>=$kolonner) {
			if ($linje==$linjer-1) {
				for ($i=$y+1;$i<$kolonner;$i++) print "<td></td>";
				print "</tr><tr><td>";
				for ($i=0;$i<$kolonner;$i++) print "</td><td>";
				$style=$stil."yellow\"";
				print "<input type=\"button\" $style onclick=\"window.location.href='$href?fokus=$fokus&id=$id&stamkunder=$x'\" value=\"Næste side\">";
				print "</td>";
			}
			print "</tr><tr>";
			$linje++;
			if ($linje==$linjer) {
#				if ($stamkunder) {
#					$style=$stil."yellow\"";
#					$tmp=0;
#					print "</td>";
#				}
				break 1;
			}
			$y=0;
		}
	} 
	$y++;
	($saldo[$x]>0)?$bgcolor='FF0000':$bgcolor='0000FF';
	$style=$stil."$bgcolor\"";
#	$firmanavn[$x]=str_replace("<br>"," \n ",$firmanavn[$x]);
	while (strlen($firmanavn[$x])<12) $firmanavn[$x]=" ".$firmanavn[$x]." ";  
	 print "<td title=\"Kreditmax:".dkdecimal($kreditmax[$x],2)." Saldo: ".dkdecimal($saldo[$x],2)."\">";
	 print "<input type=\"button\" $style onclick=\"window.location.href='$href?fokus=$fokus&id=$id&konto_id=$konto_id[$x]'\"";
		print " value=\"$firmanavn[$x]          ".dkdecimal($saldo[$x],0)."/".dkdecimal($kreditmax[$x],0)."\">";
	 print "</td>"; 
	}
	print "</tr>";
	if ($start) {
			print "<tr><td>";
			$style=$stil."yellow\"";
			$tmp=0;
#cho "$x $stamkunder<br>";
			print "<input type=\"button\" $style onclick=\"window.location.href='$href?fokus=$fokus&id=$id&stamkunder=$tmp'\" value=\"Forrige side\">";
			print "</td></tr>";
	}
	print "</tbody></table>";
	exit;
} # endfunc stamkunder
######################################################################################################################################
function kontoudtog($id) {
	global $db_id;
	global $printserver;
	global $db_encode;

	$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$k_konto_id=$r['konto_id'];
	$k_kontonr=$r['kontonr'];
	$k_firmanavn=$r['firmanavn'];
	$k_addr1=$r['addr1'];
	$k_addr2=$r['addr2'];
	$k_postnr=$r['postnr'];
	$k_k_bynavn=$r['bynavn'];
	$k_tlf=$r['tlf'];
	$k_cvrnr=$r['cvrnr'];
	
	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$printer_ip=explode(chr(9),$r['box3']);
	$tmp=$kasse-1;
	$printserver=$printer_ip[$tmp];
	if (!$printserver)$printserver='localhost';
	
	$x=0;
	$q=db_select("select faktnr,amount,uxtid from openpost where konto_id='$k_konto_id' order by uxtid",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$faktnr[$x]=$r['faktnr'];
		$amount[$x]=$r['amount'];
		$uxtid[$x]=$r['uxtid'];
		$sum+=$amount[$x];
		if ($sum) $x++;
		else $x=0;
	}
	$antal=$x;

	include("../includes/ConvertCharset.class.php");
	if ($db_encode=="UTF8") $FromCharset = "UTF-8";
	else $FromCharset = "iso-8859-15";
	$ToCharset = "cp865";
	$convert = new ConvertCharset();

	$pfnavn="../temp/".$db."/".$bruger_id.".txt";
	$fp=fopen("$pfnavn","w");
	$r=db_fetch_array(db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$firmanavn=$r['firmanavn'];
	$addr1=$r['addr1'];
	$addr2=$r['addr2'];
	$postnr=$r['postnr'];
	$bynavn=$r['bynavn'];
	$tlf=$r['tlf'];
	$cvrnr=$r['cvrnr'];
	$belob="beløb";
	if ($firmanavn) $firmanavn = $convert ->Convert($firmanavn, $FromCharset, $ToCharset);
	if ($addr1) $addr1 = $convert ->Convert($addr1, $FromCharset, $ToCharset);
	if ($addr2) $addr2 = $convert ->Convert($addr2, $FromCharset, $ToCharset);
	if ($bynavn) $bynavn = $convert ->Convert($bynavn, $FromCharset, $ToCharset);
	if ($tlf) $tlf = $convert ->Convert($tlf, $FromCharset, $ToCharset);
	if ($cvrnr) $cvrnr = $convert ->Convert($cvrnr, $FromCharset, $ToCharset);
	if ($belob) $belob = $convert ->Convert($belob, $FromCharset, $ToCharset);

	if ($k_firmanavn) $k_firmanavn = $convert ->Convert($k_firmanavn, $FromCharset, $ToCharset);
	if ($k_addr1) $k_addr1 = $convert ->Convert($k_addr1, $FromCharset, $ToCharset);
	if ($k_addr2) $k_addr2 = $convert ->Convert($k_addr2, $FromCharset, $ToCharset);
	if ($k_bynavn) $k_bynavn = $convert ->Convert($k_bynavn, $FromCharset, $ToCharset);
	if ($k_tlf) $k_tlf = $convert ->Convert($k_tlf, $FromCharset, $ToCharset);
	if ($k_cvrnr) $k_cvrnr = $convert ->Convert($k_cvrnr, $FromCharset, $ToCharset);
	
	if (file_exists("../debitor/pos_print/kontoprint_$db_id.php")) include ("../debitor/pos_print/kontoprint_$db_id.php");
	else {
		if ($k_kontonr) $bon="$k_kontonr\n";
		if ($k_firmanavn) $bon.="$k_firmanavn\n";
		if ($k_addr1) $bon.="$k_addr1\n";
		if ($k_postnr) $bon.="$k_postnr $k_bynavn\n";
		if ($k_tlf) $bon.="Tlf.: $k_tlf\n";
		if ($k_cvrnr )$bon.="CVR.: $k_cvrnr\n";
		$bon.="\n\n";
		$bon.="Dato  Kl.      Bon             $belob       Saldo\n";
		$bon.="------------------------------------------------\n";
		$saldo=0;
		for($x=0;$x<$antal;$x++) {
			$dkdato=date("d-m-y H:i",$uxtid[$x]);
			$saldo+=$amount[$x];
			$dkamount=dkdecimal($amount[$x],2);
			$dksaldo=dkdecimal($saldo,2);
			while (strlen($faktnr[$x])<10) $faktnr[$x].=" ";
			while (strlen($dkamount)<10) $dkamount=" ".$dkamount;
			while (strlen($dksaldo)<10) $dksaldo=" ".$dksaldo;
			$bon.="$dkdato $faktnr[$x] $dkamount $dksaldo\n";
		}
		$bon.="------------------------------------------------\n";
		$bon.="------------------------------------------------\n";
		$bon.="$firmanavn\n\n";
		$bon.="$addr1\n";
		$bon.="$postnr $bynavn\n";
		$bon.="Tlf.: $tlf\n";
		$bon.="CVR.: $cvrnr\n";
		$bon.="\n\n";
		$bon=urlencode($bon);
		if (!$printserver || $printserver=='box') {
    $filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
    if ($fp=fopen($filnavn,'r')) {
    	$printserver=trim(fgets($fp));
    	fclose ($fp);
    }
}
($fakturanr)?$skuffe=1:$skuffe=0;

$url="://".$_SERVER['SERVER_NAME'].=$_SERVER['PHP_SELF'];
$url=str_replace("/debitor/pos_ordre.php","",$url);
if ($_SERVER['HTTPS']) $url="s".$url;
$url="http".$url;
$returside=$url."/debitor/pos_ordre.php";

print "<meta http-equiv=\"refresh\" content=\"0;URL=http://$printserver/saldiprint.php?printfil=$tmp&url=$url&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&skuffe=$skuffe&returside=$returside\">\n";
exit;

	}
}
######################################################################################################################################
function kontoopslag($o_art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$land,$kontakt,$email,$cvrnr,$ean,$betalingsbet,$betalingsdage) {
#cho "$o_art,$sort,$fokus,$id,$kontonr,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$kontakt<br>";

	global $bgcolor,$bgcolor5,$land,$returside,$sag_id;
	$find=$href=$linjebg=$opret=NULL;
	
	if ($fokus=='kontonr') $find=$kontonr;
	elseif (strstr($fokus,'lev')) $find=$firmanavn;
	elseif ($fokus=='firmanavn') $find=$firmanavn;
	elseif ($fokus=='addr1') $find=$addr1;
	elseif ($fokus=='addr2') $find=$addr2;
	elseif ($fokus=='postnr') $find=$postnr;
	elseif ($fokus=='bynavn') $find=$bynavn;
	elseif ($fokus=='kontakt') $find=$kontakt;
	elseif ($fokus=='vare0') $fokus=NULL; #20160217 

	if ($find) $find=str_replace("*","%",$find);
	else $find="%";
#	if (substr($find,-1,1)!='%') $find=$find.'%';

		if($o_art=='DO'||$o_art=='DK') {
		sidehoved($id, "../debitor/ordre.php", "../debitor/debitorkort.php", $fokus, "Kundeordre $id - Kontoopslag");
		$href="ordre.php";
	} elseif ($o_art=='PO'||$o_art=='KO') {
		sidehoved($id, "../debitor/pos_ordre.php", "../debitor/debitorkort.php", $fokus, "POS ordre $id - Kontoopslag");
		$href="pos_ordre.php";
		$find="";
		$fokus="kontonr";
	}
#	sidehoved($id, "ordre.php", "../debitor/debitorkort.php", $fokus, "Kundeordre $id - Kontoopslag");
	print"<table class='dataTable2' cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign=\"top\">";
	print"<tbody><tr>";
	print"<td><b><a href=$href?sort=kontonr&funktion=kontoOpslag&fokus=$fokus&id=$id>";
	($o_art=='KO')?print "Leverandørnr":print "Kundenr";
	print "</b></td>";
	print"<td><b><a href=$href?sort=firmanavn&funktion=kontoOpslag&fokus=$fokus&id=$id>Navn</b></td>";
	print"<td><b><a href=$href?sort=addr1&funktion=kontoOpslag&fokus=$fokus&id=$id>Adresse</b></td>";
	print"<td><b><a href=$href?sort=addr2&funktion=kontoOpslag&fokus=$fokus&id=$id>Adresse2</b></td>";
	print"<td><b><a href=$href?sort=postnr&funktion=kontoOpslag&fokus=$fokus&id=$id>Postnr</b></td>";
	print"<td><b><a href=$href?sort=bynavn&funktion=kontoOpslag&fokus=$fokus&id=$id>bynavn</b></td>";
	print"<td><b><a href=$href?sort=land&funktion=kontoOpslag&fokus=$fokus&id=$id>land</b></td>";
	print"<td><b><a href=$href?sort=kontakt&funktion=kontoOpslag&fokus=$fokus&id=$id>Kontaktperson</b></td>";
	print"<td><b><a href=$href?sort=tlf&funktion=kontoOpslag&fokus=$fokus&id=$id>Telefon</b></td>";
	print" </tr>\n";
	if ($o_art=='PO'||$o_art=='KO')	{
	  print "<form NAME=\"kontoopslag\" action=\"pos_ordre.php?fokus=kontonr&id=$id\" method=\"post\">";
	  print "<tr><td><input name=\"kontonr\" size = \"4\"></td>";
	  print "<td><input  STYLE=\"width: 0.01em;height: 0.01em;\" type=submit name=\"Opdat\" value=\"\"></td></tr>";
	  print "</form>";
	}
	$sort = $_GET['sort'];
	if (!$sort) {$sort = "firmanavn";}
	if (strstr($fokus,'lev_')) $soeg='firmanavn';
	elseif ($firmanavn || $addr1 || $postnr || $bynavn) {
		$opret=1;
		if ($find=$firmanavn) $soeg='firmanavn';
		elseif ($find=$addr1) $soeg='addr1';
		elseif ($postnr=$addr1) $soeg='postnr';
		elseif ($find=$bynavn) $soeg='bynavn';
	} else $soeg=$fokus;
	($o_art=='KO')?$art='K':$art='D';
	$qtxt = "select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = '$art' and lukket != 'on' ";
	if ($soeg && $find) {
		$qtxt.= "and ($soeg like '%".db_escape_string($find)."%' or upper($soeg) like '%".strtoupper(db_escape_string($find))."%') ";
	}
	$qtxt.= "order by $sort";
	$fokus_id='id=fokus';
	$x=0;
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($q	)) {
		$x++;
		$kontonr=str_replace(" ","",$row['kontonr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href=$href?fokus=$fokus&id=$id&konto_id=$row[id] $fokus_id>$row[kontonr]</a></td>";
		$fokus_id='';
		print "<td>".stripslashes($row['firmanavn'])."</td>";
		print "<td>".stripslashes($row['addr1'])."</td>";
		print "<td>".stripslashes($row['addr2'])."</td>";
		print "<td>".stripslashes($row['postnr'])."</td>";
		print "<td>".stripslashes($row['bynavn'])."</td>";
		print "<td>".stripslashes($row['land'])."</td>";
		print "<td>".stripslashes($row['kontakt'])."</td>";
		print "<td>".stripslashes($row['tlf'])."</td>";
		print "</tr>\n";
	}
	if (!$x) {
		print "<tr><td colspan=9><hr></td></tr>";
#		print "<tr><td>$kontonr</td><td>$firmanavn</td><td>$addr1</td><td>$addr2</td><td>$postnr</td><td>$bynavn</td><td>$land</td><td>$kontakt</td><td>$tlf</td></tr>";
#		print "<tr><td colspan=9>Ovenst&aring;ende kunde er ikke oprettet. <a href=\"../debitor/debitorkort.php?kontonr=$kontonr&firmanavn=$firmanavn&addr1=$addr1&addr2=$addr2&postnr=$postnr&bynavn=$bynavn&land=$land&kontakt=$kontakt&tlf=$tlf&returside=../debitor/$href&ordre_id=&fokus=kontonr\">Klik her for at oprette denne kunde</a></td></tr>";
#		print "<tr><td colspan=9><hr></td></tr>";
	
		if (!$kontonr) $kontonr=get_next_number('adresser','D');

		$x=0;
		$qtxt="select * from grupper where art='DG' order by kodenr";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$grp_nr[$x]=$r['kodenr'];
			$grp_name[$x]=$r['beskrivelse'];
			$x++;
		}
		$pay_terms=array('Kontant','Netto','Lb. md.','Efterkrav','Forud');

		print "<form name=\"create_debtor\" action=\"ordre.php?id=$id&sag_id=$sag_id&returside=$returside\" method=\"post\">\n"; 
		print "<tr><td colspan='9' align='center' valign='top'><table><tbody>";
		print "<tr><td colspan = '2' align = 'center'><big><b>Opret Ny kunde</b></big></td></tr>";
		print "<tr><td colspan = '2'><hr></td></tr>";
		print "<tr><td>Kundenr</td><td><input style='width:150px;' type='text' name='kontonr' value=\"$kontonr\"></td></tr>";
		print "<tr><td style='width:100px;'>Navn</td><td><input style='width:150px;' type='text' name='firmanavn' value=\"$firmanavn\"></td></tr>";
		print "<tr><td style='width:100px;'>Adresse</td><td><input style='width:150px;' type='text' name='addr1' value=\"$addr1\"></td></tr>";
		print "<tr><td style='width:100px;'>Adresse</td><td><input style='width:150px;' type='text' name='addr2' value=\"$addr2\"></td></tr>";
		print "<tr><td style='width:100px;'>Postnr</td><td><input style='width:150px;' type='text' name='postnr' value=\"$postnr\"></td></tr>";
		print "<tr><td style='width:100px;'>By</td><td><input style='width:150px;' type='text' name='bynavn' value=\"$bynavn\"></td></tr>";
		print "<tr><td style='width:100px;'>Telefon</td><td><input style='width:150px;' type='text' name='tlf' value=\"\"></td></tr>";
		print "<tr><td style='width:100px;'>Kontakt</td><td><input style='width:150px;' type='text' name='kontakt' value=\"$kontakt\"></td></tr>";
		print "<tr><td style='width:100px;'>E-mail</td><td><input style='width:150px;' type='text' name='email' value=\"$email\"></td></tr>";
		print "<tr><td style='width:100px;'>Cvr</td><td><input style='width:150px;' type='text' name='cvrnr' value=\"$cvrnr\"></td></tr>";
		print "<tr><td style='width:100px;'>Ean</td><td><input style='width:150px;' type='text' name='ean' value=\"$ean\"></td></tr>";
		print "<tr><td style='width:100px;'>Betaling</td><td><select style='width:125px;' name='betalingsbet'>";
		for ($x=0;$x<count($pay_terms);$x++) {
			print "<option value='$pay_terms[$x]'>$pay_terms[$x]</option>";
		}	
		print "</select><input style='width:25px;text-align:right;' type='text' name='betalingsdage' value=\"0\">";
		print "<tr><td>gruppe</td>";
		print "<td><select style='width:150px;' name='grp'>";
		for ($x=0;$x<count($grp_nr);$x++) {
			print "<option value='$grp_nr[$x]'>$grp_nr[$x] : $grp_name[$x]</option>"; 
		}
		print "</select></td></tr>";
		print "<tr><td colspan='2' align='center'><input style='width:250px;' type='submit' name=create_debtor value=\"Opret\"></td></tr>";
		print "</tbody></table></td></tr>";
	}
	print "</tbody></table></td></tr>";
	if ($o_art=='PO')	print "<script language=\"javascript\">document.kontoopslag.kontonr.focus();</script>";
	else print "<BODY onLoad=\"javascript:document.getElementById('fokus').focus()\">";
	exit;
}
######################################################################################################################################
function ansatopslag($sort, $fokus, $id)
{
	global $bgcolor;
	global $bgcolor5;

	if (!$id) $id='0';
	$query = db_select("select konto_id from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$konto_id = $row['konto_id'];

	$fokus=$fokus."&konto_id=".$konto_id;

	sidehoved($id, "ordre.php", "../debitor/ansatte.php", $fokus, "Debitorordre $id",__FILE__ . " linje " . __LINE__);
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><b><a href=ordre.php?sort=navn&funktion=ansatOpslag&fokus=$fokus&id=$id>Navn</b></td>";
	print"<td><b><a href=ordre.php?sort=tlf&funktion=ansatOpslag&fokus=$fokus&id=$id>Lokal</b></td>";
	print"<td><b><a href=ordre.php?sort=mobil&funktion=ansatOpslag&fokus=$fokus&id=$id>Mobil</b></td>";
	print"<td><b><a href=ordre.php?sort=email&funktion=ansatOpslag&fokus=$fokus&id=$id>E-mail</b></td>";
	print" </tr>\n";


	$sort = $_GET['sort'];
	if (!$sort) {$sort = navn;}

	if (!$id) {exit;}
	$query = db_select("select * from ansatte where konto_id = $konto_id order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query))
	{
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href='ordre.php?fokus=$fokus&id=$id&kontakt=$row[navn]'>$row[navn]</a></td>";
		print "<td> $row[tlf]</td>";
		print "<td> $row[mobil]</td>";
		print "<td> $row[email]</td>";
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function opret_ordrelinje($id,$vare_id,$varenr,$antal,$beskrivelse,$pris,$rabat_ny,$procent,$art,$momsfri,$posnr,$linje_id,$incl_moms,$kdo,$rabatart,$kopi,$saet,$fast_db,$lev_varenr,$lager,$linje) { #20140426

	if (!$id) return("missing ordre ID");
	global $afd;
	global $db,$db_skriv_id;
	global $folger,$formularsprog; #20200109
	global $momssats;
	global $procentfakt;
	global $regnaar;
	global $sprog_id,$status;
	global $vis_saet;
	global $webservice;
	global $gavekortnummer;
	if ($procent=='') $procent=100;
	
	if (( $gavekortnummer > 0 ) && ( $pris > 0 )) opretgavekort($gavekortnummer,$id,$pris); # 20181210

	$saet*=1;
	$fast_db*=1;
	$dd=date("Y-m-d");
	$tt=date("H:i:s");
#	if (!is_numeric($pris)) $pris=0; #20130903 - fjernet 20140124
	if ($pris && $pris > 99999999) {
		return("Ulovlig v&aelig;rdi i prisfelt");
	}
#fwrite ($log, __line__." Regnaar $regnaar\n");
	if (!$regnaar) {
		$year=date("Y");
		$month=date("m");
		$del1="(box1<='$month' and box2<='$year' and box3>='$month' and box4>='$year')";
		$del2="(box1<='$month' and box2<='$year' and box3<'$month' and box4>'$year')";
		$del3="(box1>'$month' and box2<'$year' and box3>='$month' and box4>='$year')";
		$qtxt="select kodenr from grupper where art='RA' and ($del1 or $del2 or $del3)"; #20190318
	#fwrite ($log, __line__." $qtxt\n");
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$regnaar=$r['kodenr']*1;
		#fwrite ($log, __line__." Regnaar $regnaar\n");
		} elseif ($r=db_fetch_array(db_select("select max(kodenr) as kodenr from grupper where art='RA'",__FILE__ . " linje " . __LINE__))) {
			$regnaar=$r['kodenr']*1;
		} else $regnaar=1;
	}
	$r=db_fetch_array(db_select("select ordrer.art as art,ordrer.status as status,ordrer.valutakurs as valutakurs,ordrer.afd as afd,adresser.gruppe as debitorgruppe,adresser.rabatgruppe as debitorrabatgruppe from adresser,ordrer where ordrer.id='$id'and adresser.id=ordrer.konto_id",__FILE__ . " linje " . __LINE__));
	$debitorgruppe=$r['debitorgruppe']*1;
	$debitorrabatgruppe=$r['debitorrabatgruppe']*1;
	$valutakurs=$r['valutakurs']*1;
	$status=$r['status'];
	if (!$afd) $afd=$r['afd']*1;
	if (!$lager) {
		if ($afd) {
			$r=db_fetch_array(db_select("select box1 from grupper where kodenr='$afd' and art = 'AFD'",__FILE__ . " linje " . __LINE__));
			$lager=$r['box1'];
			if (!$lager) {
				$r=db_fetch_array(db_select("select kodenr from grupper where box1='$afd' and art = 'LG'",__FILE__ . " linje " . __LINE__));
				$lager=$r['kodenr']*1;
			} 
		} else $lager=0;
	}

	if (!$art) $art=$r['art']; #20140424b
	if ($status>=3) { #20131015
		return("Der kan ikke tilføjes linjer i en bogført ordre");
		exit;
	}
	$r=db_fetch_array(db_select("select box8,box9 from grupper where kodenr='$debitorgruppe' and art = 'DG'",__FILE__ . " linje " . __LINE__));
	$b2b=$r['box8'];
	$omkunde=$r['box9'];

	$varenr=db_escape_string($varenr);
	$varenr_low=strtolower($varenr);
	$varenr_up=strtoupper($varenr);

	$variant_varer=array(); //20181223
	$x=0;
	$qtxt="SELECT distinct(vare_id) FROM variant_varer";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$variant_varer[$x]=$r['vare_id'];
		$x++;
	}
	
	$qtxt="SELECT id,vare_id,variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr_up'";
	if (strlen($varenr)==12 && is_numeric($varenr)) $qtxt.=" or variant_stregkode='0$varenr'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['id'] && $r['vare_id'] && $r['variant_type']) {
		$vare_id=$r['vare_id'];
		$variant_type=$r['variant_type'];
		$variant_id=$r['id'];
	} else {
		$variant_id=0;
		$variant_type='';
	}
	
	$string=NULL;
	if (isset($vare_id) && $vare_id) $string="select * from varer where id='$vare_id'";
	elseif ($varenr) {
		$string="select * from varer where lower(varenr) = '$varenr_low' or upper(varenr) = '$varenr_up' or varenr LIKE '$varenr' or lower(stregkode) = '$varenr_low' or upper(stregkode) = '$varenr_up' or stregkode LIKE '$varenr'";
		if (strlen($varenr)==12 && is_numeric($varenr)) $string.=" or stregkode='0$varenr'";
	} elseif ($id && $beskrivelse && $posnr) {
		$qtxt="insert into ordrelinjer ";
		$qtxt.="(ordre_id,vare_id,varenr,enhed,beskrivelse,antal,rabat,rabatart,procent,m_rabat,pris,kostpris,momsfri,momssats,posnr,projekt,";
		$qtxt.="folgevare,rabatgruppe,bogf_konto,kred_linje_id,kdo,serienr,variant_id,leveres,samlevare,omvbet,saet,fast_db,tilfravalg,lager) ";
		$qtxt.="values ";
		$qtxt.="('$id','0','','','$beskrivelse','0','0','','100','0','0','0','','0','$posnr','0','0','0','0','0','','','0','0','','$omvbet',";
		$qtxt.="'$saet','$fast_db','',$lager)";
		fwrite($log, __linr__." $qtxt\n");
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	} else {
		return ("Manglende varenr eller beskrivelse");
		exit;
	}

	#	fwrite($log,__line__." Pris $pris\n");
	if ($string && $r=db_fetch_array(db_select("$string",__FILE__ . " linje " . __LINE__))) {
		$vare_id=$r['id'];
		$varenr=db_escape_string($r['varenr']);
		$enhed=db_escape_string($r['enhed']);
		$folgevare=$r['folgevare']*1;
#		$tilfravalg=$r['tilfravalg'];
		$rabatgruppe=$r['rabatgruppe']*1;
		$varegruppe=$r['gruppe']*1;
		$samlevare=$r['samlevare'];
		$varerabatgruppe=$r['dvrg']*1;
		if (!$pris && $b2b) $pris=$r['tier_price']*1;
		$special_price=$r['special_price']*1;
		$serienr=$r['serienr'];
		$beholdning=($r['beholdning'])*1;
		(strpos($r['m_antal'],';'))?list($m_antal,$temp)=explode(";",$r['m_antal'],2):$m_antal=$r['m_antal'];
		$m_antal=$m_antal*1;
		if (!$variant_id && in_array($vare_id,$variant_varer) && $db != 'bizsys_245') { //20181223
			return('Brug stregkode ved variant_varer');
			exit;
		}
		if ($folgevare) {
			$qtxt="select varenr from varer where id='$folgevare'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $folgevare=0;
		}
		if (!$beskrivelse) {
			$beskrivelse=db_escape_string(trim($r['beskrivelse']));
			if ($formularsprog) {
				$r2=db_fetch_array(db_select("select kodenr from grupper where art='VSPR' and box1 = '$formularsprog'",__FILE__ . " linje " . __LINE__));
				$kodenr=$r2['kodenr']*1;
				$r2=db_fetch_array(db_select("select tekst from varetekster where sprog_id='$kodenr' and vare_id='$vare_id'",__FILE__ . " linje " . __LINE__));
				if ($r2['tekst']) $beskrivelse=db_escape_string($r2['tekst']);
			}
		}
#		if (!$posnr && $art!='PO' && $r2=db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__))) {
		if (!$posnr && $r2=db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__))) {
			$posnr=$r2['posnr']+1;
		} elseif (!$posnr) $posnr=1;
		if (!$r2 = db_fetch_array(db_select("select box4,box6,box7,box8 from grupper where art = 'VG' and kodenr = '$varegruppe'",__FILE__ . " linje " . __LINE__))) {
			$alerttekst=findtekst(320,$sprog_id)." $varenr ".findtekst(321,$sprog_id);
			return ("$alerttekst");
		}
		$bogfkto = $r2['box4'];
		$omvare = $r2['box6'];
#cho __LINE__." $bogfkto = ".$r2['box4']."<br>";
		if (!$momsfri) $momsfri = $r2['box7']; #20170207
		$lagerfort = $r2['box8'];
		if (!$bogfkto) 	{
			$alerttekst=findtekst(319,$sprog_id)." ".$varegruppe."!";
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
		  return ("$alerttekst");
		}
#cho __LINE." P: ".$pris." ".$pris*1 ."<br>";
#		if (($r2['box6']!=NULL)&&($rabatsats>$r2['box6'])) $rabatsats=$r2['box6'];
		if ($bogfkto && !$momsfri) {
			$qtxt="select moms from kontoplan where kontonr = '$bogfkto' and regnskabsaar = '$regnaar'";
#fwrite($log,__line__." $qtxt\n");
			$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($tmp=trim($r2['moms'])) { # f.eks S3
				$tmp=substr($tmp,1); #f.eks 3
				$qtxt="select box1,box2 from grupper where art = 'SM' and kodenr = '$tmp'";
#fwrite($log,__line__." $qtxt\n");
				$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($r2['box1']) $vatAccount=$r2['box1']*1;
				if ($r2['box2']) $varemomssats=$r2['box2']*1;
#fwrite($log,__line__." Varemomssats $varemomssats\n");
			}	else {
				$varemomssats=$momssats;
				$vatAccount=0;
			}	
#fwrite($log,__line__." Varemomssats $varemomssats\n");
		} else {
			$varemomssats=0;
			$vatAccount=0;
		}
#		fwrite($log,__line__." Varemomssats $varemomssats\n");
#		fwrite($log,__line__." Pris $pris\n");
		if (!$pris) {
			$ugedag=date('N');
			$uxtid=date("U");
			$tidspkt=date("H:i:s");
			$qtxt="select salgspris,kostpris from varetilbud where vare_id='$vare_id' and ugedag='$ugedag' and startdag<='$uxtid' and slutdag >='$uxtid' and starttid <='$tidspkt' and sluttid >='$tidspkt'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r2['salgspris']) {
				$pris=$r2['salgspris'];
				$kostpris=$r2['kostpris'];
			} elseif ($special_price && ($r['special_from_date'] < $dd || ($r['special_from_date'] == $dd && $r['special_from_time'] <= $tt)) && ($r['special_to_date'] > $dd || ($r['special_to_date'] == $dd && $r['special_to_time'] >= $tt))) {# 20161114
				$pris=$special_price;
				$kostpris=$r['campaign_cost']*1;
				} else {
				if ($pris!='0') $pris=$r['salgspris']*1; #20140124
				$kostpris=$r['kostpris']*1;
				if ($pris==0 && $kostpris<1) $fast_db=$kostpris;
			}
		}	elseif ($momsfri) {
			$kostpris=$r['kostpris']*1;
		} else {
			$ms=$varemomssats;
			if ($momssats<$varemomssats) $varemomssats=$momssats;		
			if ($incl_moms && $varemomssats) $pris=$pris-($pris*$varemomssats/(100+$varemomssats)); # 20190111 fjernet: $art=='PO' && 
			else $pris*=1; #20140124
			$kostpris=$r['kostpris']*1;
		}	
#		fwrite($log,__line__." Pris $pris\n");
		if ($pris && $r['salgspris']==0 && $kostpris<1 && $kostpris>0) {
			$fast_db=$kostpris;
			$kostpris=$pris*$kostpris;
		} else $fast_db=0;
	} elseif (!$kopi) {
		if ($webservice) { #20150218
			if ($varenr) {
#				fwrite($log,__line__." Varenr: $varenr eksisterer ikke\n");
				return ("Varenr: $varenr eksisterer ikke");
			}	else {
				return ('0');
			}
			exit;
		} else {
			vareopslag($art,'varenr','beskrivelse',$id,'','','%'.$varenr.'%'); #20150215
			exit;
		}
		return ("Varenr: $varenr eksisterer ikke");
	}
#cho __LINE." P: ".$pris." ".$pris*1 ."<br>";
	$vare_id*=1;
	$m_rabat=0;
	$rabat_ny*=1;
	
	$r2 = db_fetch_array(db_select("select box11 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
	$advar_negativ_lager=$r2['box11'];
	if ($art=='DO' && $lagerfort && !$webservice && $advar_negativ_lager) {  #20140131
		$r=db_fetch_array(db_select("select beholdning from varer where id='$vare_id'",__FILE__ . " linje " . __LINE__));
		$beholdning=$r['beholdning'];
		$r=db_fetch_array(db_select("select sum(ordrelinjer.antal) as antal, sum(ordrelinjer.leveret) as leveret from ordrelinjer,ordrer where ordrelinjer.vare_id='$vare_id' and ordrelinjer.ordre_id=ordrer.id and ordrer.art='DO' and ordrer.status<3",__FILE__ . " linje " . __LINE__));
		$i_ordre=$r['antal']-$r['leveret'];
		$raadig=$beholdning-$i_ordre;
		$tmp=$antal*1;
		if (!$tmp) $tmp=1;
		if ($raadig<=$tmp) {
			$alerttxt="Beholdning:\\t".dkdecimal($beholdning,2)."\\nI ordre:\\t\\t".dkdecimal($i_ordre)."\\nTil rådighed:\\t".dkdecimal($raadig);
			alert($alerttxt);
		}
	}
	if ($linje_id && $art=='DO') $tmp="id='$linje_id'";
	elseif ($art=='PO') {
		$tmp= "vare_id = '$vare_id' and ordre_id='$id' and pris='$pris' and rabat='$rabat_ny' and variant_id='$variant_id' and tilfravalg=''";
	}
#	fwrite ($log,__line__." $tmp\n");
	$qtxt="select rabat,posnr,id,antal from ordrelinjer where $tmp";
#	fwrite($log,__line__." $qtxt\n");
	if(!$folger && !$saet && ((!$kopi && $linje_id && $art=='DO') || $art=='PO') && $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { #20200109
		$antaldiff=$antal;
		$antal=$r['antal']+$antal;
		if (($art!='PO' || $antal) && $antaldiff && $r['id']) {
			if (abs($antal) < 100000000000) {
				db_modify("update ordrelinjer set m_rabat='0', antal=antal+$antaldiff where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
				if ($samlevare == 'on') {
					db_modify("update ordrelinjer set antal=antal/$r[antal]*$antal where samlevare = '$linje_id'",__FILE__ . " linje " . __LINE__);
				}
				$r2=db_fetch_array(db_select("select sum(antal) as antal from ordrelinjer where vare_id='$vare_id' and pris='$pris' and rabat='0' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
				$tmpantal=$r2['antal'];
			} else print "<BODY onLoad=\"javascript:alert('Antal (".dkdecimal($antal).") er for stort, reducer antal')\">";
			if ($m_antal && $tmpantal >= $m_antal) {
				m_rabat($r['id'],$vare_id,$r['posnr'],$tmpantal,$id,$pris);
			} else {
				db_modify("update ordrelinjer set m_rabat='0' where ordre_id = '$id' and vare_id = '$vare_id'",__FILE__ . " linje " . __LINE__);
			}
		} elseif ($art=='PO' && $r['id']) db_modify("delete from ordrelinjer where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
	} else {
		if ($kopi || $rabat_ny) $rabat=$rabat_ny;
		else {
			if (!$debitorrabatgruppe && !db_fetch_array(db_select("select id from grupper where art='DRG'",__FILE__ . " linje " . __LINE__))){
				$debitorrabatgruppe=$debitorgruppe;
			}
			if (!$varerabatgruppe && !db_fetch_array(db_select("select id from grupper where art='DVRG'",__FILE__ . " linje " . __LINE__))){
				$varerabatgruppe=$varegruppe;
			}
			$r2=db_fetch_array(db_select("select rabat,rabatart from rabat where vare='$varerabatgruppe' and debitor='$debitorrabatgruppe'",__FILE__ . " linje " . __LINE__));
			$rabat=$r2['rabat']*1;
			$rabatart=$r2['rabatart'];
		}
#cho __LINE__." P: ".$pris." ".$pris*1 ."<br>";
 		($linje_id && $art=='DK')?$kred_linje_id=$linje_id:$kred_linje_id='0';
#cho "$momssats if (!$varemomssats && $varemomssats!='0')<br>";
		if (!$varemomssats && $varemomssats!='0') {
			($momsfri)?$varemomssats='0':$varemomssats=$momssats;
		}
		$varemomssats*=1;
#		fwrite($log,__line__." Varemomssats $varemomssats\n");
		#cho __LINE__." P: ".$pris." ".$pris*1 ." $valutakur s&& $valutakurs!=100<br>";
		if ($valutakurs && $valutakurs!=100) {
			$pris=$pris*100/$valutakurs;
			$kostpris=$kostpris*100/$valutakurs;
		}
		if ($momsfri) $VatPrice=$pris;
		else $VatPrice=$pris+$pris*$varemomssats/100;
#cho __LINE__." P: ".$pris." ".$pris*1 ."<br>";
#cho "rabarart $rabatart<br>";
		if ($variant_type) {
			$varianter=explode(chr(9),$variant_type);
			for ($y=0;$y<count($varianter);$y++) {
				$qtxt="select variant_typer.beskrivelse as vt_besk,varianter.beskrivelse as var_besk from variant_typer,varianter";
				$qtxt.=" where variant_typer.id = '$varianter[$y]' and variant_typer.variant_id=varianter.id";
				$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$beskrivelse.=", ".$r1['var_besk'].":".$r1['vt_besk'];
			}
		}
#cho __LINE__." P: ".$pris." ".$pris*1 ."<br>";
#cho "insert into ordrelinjer (ordre_id,vare_id,varenr,enhed,beskrivelse,antal,rabat,rabatart,m_rabat,pris,kostpris,momsfri,momssats,posnr,projekt,folgevare,rabatgruppe,bogf_konto,kred_linje_id,kdo,serienr,variant_id) values ('$id','$vare_id','$varenr','$enhed','$beskrivelse','$antal','$rabat','$rabatart','$m_rabat','$pris','$kostpris','$momsfri','$varemomssats','$posnr','0','$folgevare','$rabatgruppe','$bogfkto','$kred_linje_id','$kdo','$serienr','$variant_id')<br>";
# exit;
		($webservice) ?$leveres=$antal:$leveres=0; 
		if ($id && is_numeric($posnr)) {
			$momslog=fopen("../temp/$db/momslog.log","a");
			fwrite($momslog, "varenr $varenr - Varemoms $varemomssats Momskto $vatAccount\n");
			fclose ($momslog);
			if ($varemomssats && !$vatAccount) {
				$alerttxt="Manglende konto for salgsmoms (Varenr: $varenr indsat uden moms)";
				alert ($alerttxt);
				$varemomssats=0;
#				return ('0');
#				exit;
			}
			if (($samlevare && !$antal) || $antal=='') $antal=1;
			($omkunde && $omvare)?$omvbet='on':$omvbet='';
			$antal*=1;
			$leveres*=1;
			if ($lager<1) $lager=1; 
			$qtxt="insert into ordrelinjer ";
			$qtxt.="(ordre_id,vare_id,varenr,enhed,beskrivelse,antal,rabat,rabatart,procent,m_rabat,pris,vat_price,kostpris,momsfri,momssats,";
			$qtxt.="posnr,projekt,folgevare,rabatgruppe,bogf_konto,vat_account,kred_linje_id,kdo,serienr,variant_id,leveres,samlevare,omvbet,";
			$qtxt.="saet,fast_db,lev_varenr,tilfravalg,lager) ";
			$qtxt.="values ";
			$qtxt.="('$id','$vare_id','$varenr','$enhed','$beskrivelse','$antal','$rabat','$rabatart','$procent','$m_rabat','$pris',";
			$qtxt.="'$VatPrice','$kostpris','$momsfri','$varemomssats','$posnr','','$folgevare','$rabatgruppe','$bogfkto','$vatAccount',";
			$qtxt.="'$kred_linje_id','$kdo','$serienr','$variant_id','$leveres','$samlevare','$omvbet','$saet','$fast_db','$lev_varenr','','$lager')";
#			fwrite($log, __line__." $qtxt\n");
#cho __LINE__." $linje -> $varemomssats = $vatAccount<br>";
#cho __LINE__." $qtxt<br>";
			if (abs($antal) < 100000000000) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			else print "<BODY onLoad=\"javascript:alert('Antal (".dkdecimal($antal).") er for stort, reducer antal')\">";
			if ($samlevare && !$beholdning) {
				$r=db_fetch_array(db_select("select max(id) as id from ordrelinjer where vare_id='$vare_id' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
#				samlevare($id,$art,$r['id'],$vare_id,$antal); udkommenteret 20131129
			}
		}
#fclose($log);		
		# finder antal af varen på ordren.
#cho "select sum(antal) as antal from ordrelinjer where vare_id='$vare_id' and pris=$pris and ordre_id='$id<br>";
		$r=db_fetch_array(db_select("select sum(antal) as antal from ordrelinjer where vare_id='$vare_id' and pris=$pris and rabat='0' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
		$tmpantal=$r['antal'];
		if ($m_antal && $tmpantal >= $m_antal) {
			$r2=db_fetch_array(db_select("select max(id) as id from ordrelinjer where vare_id='$vare_id' and pris=$pris and rabat='0' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
			m_rabat($r2['id'],$vare_id,0,$tmpantal,$id,$pris);
		}	else {
#cho "update ordrelinjer set m_rabat='0' where ordre_id = '$id' and vare_id = '$vare_id'<br>";
			db_modify("update ordrelinjer set m_rabat='0' where ordre_id = '$id' and vare_id = '$vare_id'",__FILE__ . " linje " . __LINE__);
		}
	}
	if ($vis_saet && $status) db_modify("update ordrer set felt_2='0' where id = '$id'",__FILE__ . " linje " . __LINE__);
	$sum=$pris*$antal;
#cho "retur Sum $sum<br>";
	return($sum);
#	$varenr=$next_varenr;
#	$antal=NULL;
} # endfunc opret_orderlinje
######################################################################################################################################
function m_rabat($linje_id,$vare_id,$posnr,$antal,$ordre_id,$pris) {

# finder mængderabat på varen.
#cho "select m_type,m_rabat,m_antal,salgspris from varer where id = '$vare_id'<br>";
	$r=db_fetch_array(db_select("select m_type,m_rabat,m_antal,salgspris,kostpris from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
	$m_antal=explode(";",$r['m_antal']);
	$m_rabat=explode(";",$r['m_rabat']);
	$m_type=$r['m_type'];
	$varepris=$r['salgspris']*1;
	$varekost=$r['kostpris']*1;
	
	$x=0;
	if ($varepris && $pris) $pris=$varepris;  #20160810 
	while ($m_antal[$x+1] && $antal >= $m_antal[$x+1]) {
		$x++;
	}
	if ($m_type =='percent') {
		$m_rabat[$x]=$pris*$m_rabat[$x]/100;
		if ($varepris == 0 && $varekost >= 0.01 && $varekost <= 0.99) {
			$varekost=($pris-$m_rabat[$x])*$varekost;
		}
	}

	$qtxt = "update ordrelinjer set m_rabat = '$m_rabat[$x]', rabatart = '$m_type', kostpris = '$varekost' ";
	$qtxt.= "where ordre_id = '$ordre_id' and vare_id = '$vare_id'and pris = '$pris' and rabat = '0'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}# endfunc m_rabat
######################################################################################################################################
function find_pris($varenr) {
	global $momssats,$regnaar;
	
	$pris=NULL;
	$dd=date("Y-m-d");
	$tt=date("H:i:s");
	# 20140704 ->
	if (is_numeric($varenr) && strlen($varenr)=='13') {
		$tmp=substr($varenr,0,7)."XXXXXX";
#cho "select salgspris from varer where stregkode = '$tmp'";
		if ($r=db_fetch_array(db_select("select salgspris,gruppe from varer where stregkode = '$tmp'",__FILE__ . " linje " . __LINE__))){
			$pris=substr($varenr,7,5)/100;
			$vg=$r['gruppe'];
		}	else {
			$tmp=substr($varenr,0,6)."XXXXXXX";
			if ($r=db_fetch_array(db_select("select salgspris,gruppe from varer where stregkode = '$tmp'",__FILE__ . " linje " . __LINE__))){
				$pris=substr($varenr,7,5)/100;
				$vg=$r['gruppe'];
			}
		}
	}
	# <- 20140704 + !$pris && herunder.
	$qtxt="select * from varer where varenr = '$varenr' or stregkode='$varenr' limit 1";
	if (!$pris && $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$vare_id=$r['id'];
		$vg=$r['gruppe'];
		$special_price=$r['special_price']*1;
		$special_from_date=$r['special_from_date'];
		$special_to_date=$r['special_to_date'];
		$special_from_time=$r['special_from_time'];
		$special_to_time=$r['special_to_time'];
		if ($special_from_date<$dd && $special_from_time!='00:00:00') $special_from_time='00:00:00'; #20160909
		if ($special_to_date>$dd && $special_to_time=='00:00:00') $special_to_time='23:59:59'; #20160909
		$ugedag=date('N');
		$uxtid=date("U");
		$tidspkt=date("H:i:s");
		$qtxt="select salgspris,kostpris from varetilbud where vare_id='$vare_id' and ugedag='$ugedag' and startdag<='$uxtid' ";
		$qtxt.="and slutdag >='$uxtid' and starttid <='$tidspkt' and sluttid >='$tidspkt'";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r2['salgspris']) {
			$pris=$r2['salgspris'];
			$kostpris=$r2['kostpris'];
		} elseif ($special_price && $special_from_date <= $dd && $special_to_date >= $dd && $special_from_time <= $tt && $special_to_time >= $tt) {
#		if ($special_price && $r['special_from_date'] <= $dd && $dd <= $r['special_to_date']) {
			$pris=$r['special_price']*1;
			$kostpris=$r['campaign_cost']*1;
		} else {
			$pris=$r['salgspris']*1;
			$kostpris=$r['kostpris']*1;
		}
		$qtxt="select box4,box7 from grupper where art = 'VG' and kodenr = '$vg'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$momsfri = $r['box7'];
		if (!$momsfri) {
			$kontonr=$r['box4'];
			$qtxt="select moms from kontoplan where regnskabsaar='$regnaar' and kontonr = '$kontonr'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['moms']) {
				$art=substr($r['moms'],0,1).'M';
				$kodenr=substr($r['moms'],1);
				$qtxt="select box2 from  grupper where art = '$art' and kodenr='$kodenr'";
				$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$varemomssats=$r['box2'];
				if ($momssats < $varemomssats) $varemomssats=$momssats; 
				$pris+=$pris/100*$varemomssats;
	#			}
			}
		}
	}
	return($pris);
} # endfunc find_pris
######################################################################################################################################
function find_kostpris($vare_id,$linje_id) { #Returnerer kostpris i DKK

	$k_stk_ant=NULL;$kobs_ordre_art=NULL;$kobs_ordre_id=NULL;$kobs_ordre_nr=NULL;$kostpris=NULL;
	
	$r=db_fetch_array(db_select("select antal,kred_linje_id,ordre_id from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__));
	$ordre_id=$r['ordre_id']*1; #20150119
	if ($r['antal']<0 && $r['kred_linje_id'] > 0) { #20141022 Kreditnota, finder kostpris fra oprindelig ordre..
		$antal=$r['antal'];
		$kred_linje_id=$r['kred_linje_id'];
		$r=db_fetch_array(db_select("select kostpris from ordrelinjer where id = '$kred_linje_id'",__FILE__ . " linje " . __LINE__));
		$tmp=$r['kostpris'].chr(9).'0'.chr(9).$antal.chr(9).'0'.chr(9).'KO';
		return($tmp);
		exit;
	} elseif ($r['antal']) { #20151210 -> 20180629
		$antal=$r['antal']; #20160804
		$qtxt="select ordrelinjer.kostpris from ordrelinjer,ordrer where ";
		$qtxt.="ordrelinjer.id = '$linje_id' and ordrer.id=ordrelinjer.ordre_id and ordrer.fakturadate>='2015.01.01'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
			$tmp=$r['kostpris'].chr(9).'0'.chr(9).$antal.chr(9).'0'.chr(9).'DO';
			return($tmp);
			exit;
		}
	}
	$x=0;
	$qtxt="select * from batch_salg where linje_id = '$linje_id'";
#cho "$qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$koid[$x]=NULL;
		$salgsdate=$r['salgsdate'];
		$antal=$r['antal']; #20160804
		$batch_kob_id=$r['batch_kob_id']*1;
		if ($batch_kob_id) {
			$qtxt="select antal,ordre_id,pris,fakturadate,linje_id from batch_kob where id = $batch_kob_id";
			$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$koid[$x]=$r2['ordre_id'];
			$kostpris=$r2['pris'];
		} elseif ($salgsdate) {#20151210 
			$tmp=NULL;
			$qtxt="select antal,ordre_id,pris,fakturadate,linje_id from batch_kob where vare_id='$vare_id' and kobsdate <= '$salgsdate' order by kobsdate desc limit 1";
			if ($r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $tmp=$r2['pris'].chr(9).'0'.chr(9).$antal.chr(9).'0'.chr(9).'KO';
			else {
				$qtxt="select antal,ordre_id,pris,fakturadate,linje_id from batch_kob where vare_id='$vare_id' and kobsdate > '$salgsdate' order by kobsdate limit 1";
				if ($r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $tmp=$r2['pris'].chr(9).'0'.chr(9).$antal.chr(9).'0'.chr(9).'KO';
			}
			if ($tmp) {
				return($tmp);
				exit;
			}
		} 
		if ($koid[$x]) {
			$r3=db_fetch_array(db_select("select art,valutakurs from ordrer where id = $koid[$x]",__FILE__ . " linje " . __LINE__));
			if ($r3['valutakurs']) $valutakurs=$r3['valutakurs']; #20140117
			else $valutakurs=100;
			if ($r3['art']=='KO') {
				$r2=db_fetch_array(db_select("select pris from ordrelinjer where id = '$r2[linje_id]'",__FILE__ . " linje " . __LINE__));#}
				($k_stk_ant)?$k_stk_ant.=",".$r['antal']:$k_stk_ant=$r['antal'];
				($kostpris)?$kostpris.=",".$r2['pris']*$valutakurs/100:$kostpris=$r2['pris']*$valutakurs/100; #20140117
#				$kostpris[$x]=dkdecimal($r2['pris']*$valutakurs/100);
			} else $koid[$x]=0;
		}
		if (!$koid[$x]) {
			$r2 = db_fetch_array(db_select("select varer.kostpris,grupper.box8 from varer,grupper where varer.id = '$vare_id' and grupper.art=varer.gruppe",__FILE__ . " linje " . __LINE__));
			$lagerfort=$r2['box8'];
			#20150119 ->
			$r2 = db_fetch_array(db_select("select ordredate,fakturadate from ordrer where id = '$ordre_id'",__FILE__ . " linje " . __LINE__));
			$ordredate=$r2['ordredate'];
			$fakturadate=$r2['fakturadate'];
			if ($ordredate && $r2 = db_fetch_array(db_select("select pris from batch_kob where vare_id = '$vare_id' and fakturadate<'$ordredate' order by fakturadate desc",__FILE__ . " linje " . __LINE__))){
				$kostpris+=$r2['pris'];
				$k_stk_ant+=$r['antal'];
			} elseif ($fakturadate && $r2 = db_fetch_array(db_select("select pris from batch_kob where vare_id = '$vare_id' order by fakturadate",__FILE__ . " linje " . __LINE__))){
				$kostpris+=$r2['pris'];
				$k_stk_ant+=$r['antal'];
			} else {
				$r2 = db_fetch_array(db_select("select kostpris from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
				if ($r['antal']*1) { #20141121
					$kostpris=$r2['kostpris'];
					$k_stk_ant+=$r['antal'];
				}
			}
		}
		if ($koid[$x]) {
			$q3 = db_select("select ordrenr,art from ordrer where id = $koid[$x]",__FILE__ . " linje " . __LINE__);
			$r3 = db_fetch_array($q3);
			($kobs_ordre_nr)?$kobs_ordre_nr.=','.$r3['ordrenr']:$kobs_ordre_nr=$r3['ordrenr'];
			($kobs_ordre_id)?$kobs_ordre_id.=','.$koid[$x]:$kobs_ordre_id=$koid[$x];
			($kobs_ordre_art)?$kobs_ordre_art.=','.$r3['art']:$kobs_ordre_art=$r3['art'];
			
		}
	}
	if (!$x || !$lagerfort) {
		$r = db_fetch_array(db_select("select kostpris from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
		$kostpris=$r['kostpris'];
		$r = db_fetch_array(db_select("select antal from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__));
		$k_stk_ant=$r['antal']; #20121217 Rettet fra 1 i forb. med rettelse af debitor/ordre.php 20121213
	}

	$tmp=$kostpris.chr(9).$kobs_ordre_nr.chr(9).$k_stk_ant.chr(9).$kobs_ordre_id.chr(9).$kobs_ordre_art;
#cho "tmp ".str_replace(chr(9),"|",$tmp)."<br>";
	return($tmp);
}
######################################################################################################################################
function find_momssats($id,$kasse) {

	if ($id) $r=db_fetch_array(db_select("select momssats from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	elseif($kasse) {
		$tmp=array();
		$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
		$tmp=explode(chr(9),$r['box7']);
		$momsgrp=$tmp[$kasse-1]; # Kasseraekken starter med 1 og momsraekken med 0;
		$r = db_fetch_array(db_select("select box2 as momssats from grupper where art = 'SM' and kodenr = '$momsgrp'",__FILE__ . " linje " . __LINE__));
	}
	$momssats=$r['momssats']*1;
	return($momssats);
}
######################################################################################################################################
function grupperabat($antal,$rabatgruppe) {

	$r=db_fetch_array(db_select("select box1,box2,box3 from grupper where kodenr = '$rabatgruppe' and art = 'VRG'",__FILE__ . " linje " . __LINE__));
	$m_type=$r['box1'];
	$m_rabat=explode(";",$r['box2']);
	$m_antal=explode(";",$r['box3']);
	$x=0;

#	$r=db_fetch_array(db_select("select sum(antal) as antal from varer where rabatgruppe = '$rabatgruppe'",__FILE__ . " linje " . __LINE__));

	if ($antal>=$m_antal[$x]) {
		while ($m_antal[$x+1] && $antal >= $m_antal[$x+1]) {
			$x++;
		}
	} else $m_rabat[$x]=0;
#	if ($m_type =='percent') $m_rabat[$x]=$pris*$m_rabat[$x]/100;
	$m_rabat[$x]=$m_rabat[$x]*-1;
	return ($m_rabat[$x].";".$m_type);
} # endfunc grupperabat
######################################################################################################################################
function vareopslag($art,$sort,$fokus,$id,$vis_kost,$ref,$find) {
	global $afd,$afd_lager;
	global $bgcolor,$bgcolor5,$bordnr,$bruger_id,$brugernavn;
	global $id,$incl_moms;
	global $momssats;

	$cols='5';
	$findStr=trim($find,'*');
	$lg_nr=array();
	$rowheight="height=\"50\"";
		
	if ($art=='PO') {
		$incl_moms='on';
	} else print "<form action='ordre.php?id=$id' method='post'>";
	$qtxt=NULL;
	if ($sort && $bruger_id) {
		$qtxt="update settings set var_value='$sort' where var_name='itemLookup' and var_grp='deb_order' and user_id='$bruger_id'";
	} elseif ($bruger_id) {
		$qtxt="select var_value from settings where var_name='itemLookup' and var_grp='deb_order' and user_id='$bruger_id'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$sort=$r['var_value'];
		} else {
			$sort = 'beskrivelse';
			$qtxt="insert into settings (var_name,var_grp,var_value,var_description,user_id)";
			$qtxt.=" values ";
			$qtxt.=" ('itemLookup','deb_order','$sort','Sorting when doing lookup from debitor order','$bruger_id')";
		}
	}
	if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$lagernr=array();
	$lagernavn=array();

	$lager=NULL;$linjebg=NULL;
	
	$momsfri=array();
	$x=0;
	$q=db_select("select kodenr from grupper where art='VG' and box7 = 'on'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$momsfri[$x]=$r['kodenr'];
		$x++;
	}
	if (!$ref) $ref=$brugernavn;
	if (!$afd && $ref){
		$r=db_fetch_array(db_select("select ansatte.afd from ansatte where navn='$ref'",__FILE__ . " linje " . __LINE__));
		$afd=$r['afd']*1;
		if ($afd=='') {
			$r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn='$ref'",__FILE__ . " linje " . __LINE__));
			$ansat_id=$r['ansat_id']*1;
			$r=db_fetch_array(db_select("select afd from ansatte where id='$ansat_id'",__FILE__ . " linje " . __LINE__));
			$afd=$r['afd']*1;
		$afd=$r['afd']*1;
		}
		db_modify("update ordrer set afd='$afd' where id='$id'",__FILE__ . " linje " . __LINE__);
	}
	$x=0;
	$q=db_select("select beskrivelse,kodenr,box1 from grupper where art = 'LG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$lg_navn[$x]=$r['beskrivelse'];
		$lg_nr[$x]=$r['kodenr'];
		$x++;
	}
	if ($afd) { #20161022
	$r=db_fetch_array(db_select("select box1 from grupper where kodenr='$afd' and art = 'AFD'",__FILE__ . " linje " . __LINE__));
		$lager=$r['box1']*1;
		if (!$lager) {
			$r=db_fetch_array(db_select("select kodenr from grupper where box1='$afd' and art = 'LG'",__FILE__ . " linje " . __LINE__));
			$lager=$r['kodenr']*1;
		}
	}
	$lager*=1;
		
	if ($id && (!$art || !$ref)) {
		$r=db_fetch_array(db_select("select art,ref from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		if (!$art) $art=$r['art'];
		if (!$ref) $ref=$r['ref'];
	}
	if (!$ref) $ref=$brugernavn;
	if ($find) {
		$find=strtolower($find);
		if (strpos($find,'+')) { #20161110
			$find=str_replace("*","",$find);
			$ord=array();
			$ord=explode("+",$find);
			$find=NULL;
			for($f=0;$f<count($ord);$f++){
				if ($find) $find.="and lower($fokus) like '%$ord[$f]%'";
				else $find="and (lower($fokus) like '%$ord[$f]%'";
			}
			if ($find) $find.=")";
		}
		else $find="and lower($fokus) like '".str_replace("*","%",$find)."'";
#		$focus="lower($focus)";
	}
	if ($art=='PO' && !strpos($_SERVER['PHP_SELF'],'pos_ordre')) $art='DO';

	if($art=='DO'||$art=='DK') {
		sidehoved($id, "../debitor/ordre.php", "../lager/varekort.php", $fokus, "Kundeordre $id - Vareopslag");
		$href="ordre.php";
	} elseif ($art=='PO') {
#		print "<tr><td colspan=\"5\"><hr>";
#		sidehoved($id, "../debitor/pos_ordre.php", "", $fokus, "POS ordre $id - Vareopslag");
#		print "<hr></td></tr>";
		$href="pos_ordre.php";
	}
	print "<script type=\"text/javascript\" src=\"https://code.jquery.com/jquery-latest.min.js\"></script>\n";
	print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/arrowkey.js\"></script>\n";
	print "<script type=\"text/javascript\">
		$(document).ready(function () {
				$('input[type=\"text\"],textarea,a[href]').keyup(function (e) {
						if (e.which === 27) {
							window.location.href = '$href?id=$id';
						}
				});
		});
   </script>";
/*
  print "<script type=\"text/javascript\">
		var TableBackgroundNormalColor = \"$bgcolor\";
		var TableBackgroundMouseoverColor = \"$bgcolor5\";
	// These two functions need no customization.	
		function ChangeBackgroundColor(row) { row.style.backgroundColor = TableBackgroundMouseoverColor; }
		function RestoreBackgroundColor(row) { row.style.backgroundColor = TableBackgroundNormalColor; }
	</script>";
*/   
  print "<table class='dataTable2' cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\"><tbody>";
	$linjebg=$bgcolor; $color='#000000';
#	$linjebg=$bgcolor5; $color='#000000';
	print "<tr $linjebg>";
	
	if ($art!='PO') {
		$listeantal=0;
		$q=db_select("select id,beskrivelse from grupper where art='PL' and box4='on' order by beskrivelse",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$listeantal++;
			$prisliste[$listeantal]=$r['id'];
			$listenavn[$listeantal]=$r['beskrivelse'];
		}
		if ($listeantal) {
			print "<form name=\"prisliste\" action=\"../includes/prislister.php?start=0&ordre_id=$id&fokus=$fokus\" method=\"post\">";
			print "<td><select name=prisliste>";
			for($x=1;$x<=$listeantal;$x++) print "<option value=\"$prisliste[$x]\">$listenavn[$x]</option>";
			print "</select></td><td><input type=\"submit\" name=\"prislist\" value=\"Vis\"></td>";
		}


		if ($vis_kost) {
			$cols=9;
			print "<td colspan='$cols' align=center>";
			print "<a href=$href?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id>Udelad kostpriser</a></td></tr>";
		}	else {
			$cols=6;
			print "<td colspan='$cols' align=center>";
			print "<a href=$href?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=on>Vis kostpriser</a></td></tr>";
		}
		$rowheight=NULL;
	}
	($sort=='varenr')?$txt='<i>Varenr</i>':$txt='Varenr';
	print "<td><a href=$href?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost&bordnr=$bordnr><b>$txt</b></a></td>";
	print "<td><b> Enhed</b></td>";
	($sort=='beskrivelse')?$txt='<i>Beskrivelse</i>':$txt='Beskrivelse';
	print "<td><a href=$href?sort=beskrivelse&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost&bordnr=$bordnr><b>$txt</b></a></td>";
	($sort=='salgspris')?$txt='<i>Salgspris</i>':$txt='Salgspris';
	print "<td align=right><a href=$href?sort=salgspris&funktion=vareOpslag&fokus=$fokus&id=$id&bordnr=$bordnr><b>$txt</b></a></td>";
	if (count($lg_nr)>1){
		for ($x=0;$x<count($lg_nr);$x++) {
			$cols++;
			print "<td align=right><b>$lg_navn[$x]</b></td>";
		}
	} else {
		print "<td align=right><b><a href=$href?sort=beholdning&funktion=vareOpslag&fokus=$fokus&id=$id&bordnr=$bordnr>Beholdning</a></b></td>";
	}	
	if ($vis_kost) {print"<td align=right><b> Kostpris</b></td>";}
#	if ($art!='PO') print"<td align=right><b><a href=$href?sort=beholdning&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beh.</a></b></td>";
	if ($art=='PO') {
		print "<td><form name='vareopslag' action='$tmp?id=$id&fokus=varenr_ny' method='post'></td>";
		print " </tr>\n";
		print "<td colspan='2'><input type='hidden' name='fokus' value='varenr_ny'></td>";
		print "<td><input type='text' style='width:100%' name='varenr_ny' value='$findStr' id='opslag_0'></td>";
		print "<td><input type=submit name=\"OK\" value=\"Søg\"></form></td>";
		print " </tr>\n";
	}
	if (count($lg_nr)>1) {
		for ($x=0;$x<count($lg_nr);$x++) {
			$l=0;
			$qtxt="select vare_id,variant_id,beholdning from lagerstatus where lager = '$lg_nr[$x]' order by vare_id,variant_id";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				$ls_lager[$x][$l]=$lg_nr[$x];
				$ls_id[$x][$l]=$r['vare_id'];
				$ls_var_id[$x][$l]=$r['variant_id'];
				$ls_behold[$x][$l]=$r['beholdning'];
				$l++;
			}
		}
	}
		
	if ($ref){
		$r=db_fetch_array(db_select("select afd from ansatte where navn = '$ref' or initialer = '$ref'",__FILE__ . " linje " . __LINE__));
		$afd=$r['afd'];	
		$x=0;
#		$q=db_select("select max(kodenr) as lagerantal from grupper where art='LG'",__FILE__ . " linje " . __LINE__);
#		while ($r=db_fetch_array($q)) {
#			$lagernavn[$x]=$r['beskrivelse'];
#			$lagernr[$x]=$r['kodenr'];
#			$x++;
#		}
#		$r=db_fetch_array(db_select("select kodenr from grupper where box1='$afd' and art='LG'",__FILE__ . " linje " . __LINE__));
#		$lager=$r['kodenr']*1;
	}
	if (!$sort) $sort='id';
	if ($find) $qtxt="select * from varer where lukket != '1' $find order by $sort";
	elseif ($art=='PO') $qtxt="select * from varer where lukket != '1' order by $sort limit 100";
	else $qtxt="select * from varer where lukket != '1' order by $sort";
	if ($art=='PO'){
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
#		$colspan=5+count($lg_nr);
		print "<tr bgcolor=\"$linjebg\"  onclick=\"window.document.location='$href?id=$id&bordnr=$bordnr';\">";
		print "<td colspan=\"$cols\" $rowheight align=\"center\"><big><big>Tilbage</big></big></td></tr>\n";
	}
	$z=$x=0;
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)) {
		$vare_id=$row['id']*1;
		$beholdning=$row['beholdning']*1;
/*
		if ($lager) {
			for ($l=0;$l<count($ls_id);$l++) {
#cho "$l<br>";
				if ($ls_id[$l]==$vare_id) {
					$beholdning=$ls_behold[$l];
					
					break 1;
				}
			}
		}
*/
		$x++;
		$onclick = "onclick=\"window.document.location='$href?id=$id&vare_id=$row[id]&lager=$afd_lager&bordnr=$bordnr';\"";
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr  bgcolor=\"$linjebg\" >";
#		($art=='PO')?$hreftxt="$href?vare_id=$row[id]&fokus=$fokus&id=$id&bordnr=$bordnr":$hreftxt="";
		$hreftxt="$href?vare_id=$row[id]&fokus=$fokus&id=$id&bordnr=$bordnr&lager=$afd_lager";#"$href?vare_id=$row[id]&fokus=$fokus&id=$id&bordnr=$bordnr";
		print "<td $rowheight $onclick><a onfocus=\"this.style.fontSize = '20px';\" onblur=\"this.style.fontSize = '12px';\" id=\"opslag_$x\" href=\"$hreftxt\">$row[varenr]</a></td>";
		print "<td $onclick>$row[enhed]<br></td>";
		print "<td $onclick>$row[beskrivelse]<br></td>";
		if ($incl_moms && !in_array($row['gruppe'],$momsfri)) {
			$salgspris=$row['salgspris']+$row['salgspris']*$momssats/100;
		}	else $salgspris=$row['salgspris'];
		print "<td  $onclick align=right>".dkdecimal($salgspris,2)."<br></td>";
		if ($vis_kost=='on') {
			$query2 = db_select("select kostpris from vare_lev where vare_id = '$vare_id' order by posnr",__FILE__ . " linje " . __LINE__);
			$row2 = db_fetch_array($query2);
			$kostpris=dkdecimal($row2['kostpris'],2);
			print "<td  $onclick align='right'>$kostpris<br></td>";
		}
		$reserveret=0;
		if (!isset ($ls_id)) $ls_id = null;
		if (count($lg_nr)>1){
			for ($x=0;$x<count($lg_nr);$x++) {
				print "<td align=right>";
				for ($l=0;$l<count($ls_id[$x]);$l++) {
					if ($ls_id[$x][$l]==$row['id']) {
						print "<a href=$hreftxt&lager=$lg_nr[$x]><big>".dkdecimal($ls_behold[$x][$l],2)."</big></a>";
					} elseif ($row['samlevare'] && $l==0) { #20176127
						print "<a href=$hreftxt&lager=$lg_nr[$x]><button type='button' style='width:40px;height:20px;'>$lg_navn[$x]</button></a>";
					}
				}
				print "</td>";
			}
		} else {
			$q2 = db_select("select * from batch_kob where vare_id='$vare_id' and rest > 0",__FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				$q3 = db_select("select * from reservation where batch_kob_id=$r2[id]",__FILE__ . " linje " . __LINE__);
				while ($r3 = db_fetch_array($q3)) $reserveret=$reserveret+$r3['antal'];
			}
			$linjetext="<span title= 'Reserveret: $reserveret'>";
			print "<td align=right>$linjetext ".dkdecimal($beholdning,2)."</span></td>";
		}
		if ($art!='PO') {
			print "<td width='20px' align='center' title='Skriv antal her, hvis der skal indsættes flere varer ad gangen'><input type='hidden' name='insetId[$z]' value='$vare_id'>";
			print "<input type='text' style='width:30px;text-align:right;' name='insetQty[$z]'></td>";
		}
		print "</tr>\n";
		$z++;
	}
	if ($art!='PO') print "<tr><td colspan='$cols'><input style='width:100%;height:5px' type='submit' name='insetItems' value=''></td></tr>";
	print "</tbody></table>\n";
#	if ($findStr) print "<script language=\"javascript\">	document.vareopslag.varenr_ny.focus();</script>";
#	else 
	print "<body onload=\"document.links['opslag_1'].focus();\" >\n";

#	print "<script language=\"javascript\">
#		document.getElementById[\"opslag_1\"].focus();
#	</script>";
	exit;
} #endfunc vareopslag
######################################################################################################################################
function tekstopslag($sort, $id)
{
	global $bgcolor;
	global $bgcolor5;
	global $db,$db_skriv_id;
	global $sqhost;
	global $squser;
	global $sqpass;
	global $sag_id;
		
	$linjebg=NULL;
	
	if (!isset ($fokus)) $fokus = null;

	sidehoved($id, "ordre.php", "", $fokus, "Kundeordre $id - Vareopslag");
//#cho "host: $sqhost<br>dbname: $db<br>user: $squser<br>pasword: $sqpass";
	//print "<td width=100% align=\"center\">\n";
	print "<form name=\"ordre\" action=\"ordre.php?id=$id\" method=\"post\">\n";
	print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" class=\"ordretekstSort\">\n";
	//print "<tbody class=\"dataTableLink dataTableZebra\">\n";
	// indsæt sagens omfang her
	$r=db_fetch_array(db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$sag_id=$r['sag_id']*1; #20140425 indsat '*1'
	
if ($sag_id) { #20140425 Kaldes kun hvis sag_id
		$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$omfang=$r['omfang'];
		if ($omfang) {
			print "<tbody class=\"dataTableLink dataTableBottomBorderGray\">\n";
			print "<tr><td colspan=\"2\"><p style=\"text-align:center;\"><b>Omfang</b></p></td><td align=\"left\" title=\"".findtekst(491,$sprog_id)."\" style=\"width:800px;\"><!--tekst 491--><a href=\"ordre.php?id=$id&amp;tekst_sag_id=$sag_id\">$omfang</a></td>\n";
			print "<td colspan=\"2\">&nbsp;</td>\n";
			print "</tr>\n";
			print "</tbody>\n";
		}
	}
	print "<tbody class=\"dataTableLink dataTableZebra ordretekstListe\">\n";
	$x=0;
	$q = db_select("select * from ordretekster order by sort",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		print "<tr id=\"order_$r[id]\">\n";
		print "<td class=\"dragHandle\">&nbsp;</td>\n";
		print "<td align=\"center\">";
		print "	<input type=\"checkbox\" title=\"Afkryds ved insættelse af flere tekster\" name=\"linjetekster[]\" value=\"$r[id]\">";
		print "</td>";
		print "<td align=\"left\" title=\"".findtekst(491,$sprog_id)."\" style=\"width:800px;\">";
		print "	<!--tekst 491--><a href=\"ordre.php?id=$id&amp;tekst_id=$r[id]\">".nl2br($r['tekst'])."</a>";
		print "</td>\n";
		print "<td title=\"Ret tekst\" align=\"right\">";
		print "	<span style=\"width:16px; display:block;\"><a href=\"ret_tekst_ordre.php?id=$id&amp;tekst_id=$r[id]\">";
#		print "<!--Tekst 493--><img src=\"../img/cross_mark.png\"></a></td>\n";
		print "	<span class=\"checkmark\"></span></span></a>";
		print "</td>\n";
		print "<td title=\"".findtekst(492,$sprog_id)."\" align=\"left\"><!--tekst 492-->";
		print "<a href=\"ordre.php?id=$id&amp;tekst_id=$r[id]&amp;slet_tekst=$r[id]\" onclick=\"return confirm('".findtekst(493,$sprog_id)."')\"><!--Tekst 493-->";
		if ($sag_id) print "<span style=\"width:16px; display:block;\"><span class=\"xmark\"></span></a></td>\n"; #20170601 indsat 'if ($sag_id)'
		else print "<!--Tekst 493--><img src=\"../img/x_mark.png\"></a></td>\n";
		print "</tr>\n";
	}
	print "</tbody>\n";
	print "<tbody class=\"dataTableTopBorderGray\">\n";//<input class=\"inputbox\" type=\"text\" title=\"Hvis teksten skal være fed sættes <b> foran teksten og </b> efter teksten (F.eks. <b>Lorem ipsum</b>). Det samme gøres ved Italic, bare med <i> og </i>.\" style=\"text-align:left;width:800px;\" name=\"ny_linjetekst\">
	print "<tr><td valign=\"top\" colspan=\"2\"><input type=\"hidden\" name=\"id\" value=\"$id\"><span style=\"display:block;margin-top:3px;\">Ny fast tekst</span></td><td><textarea class=\"textAreaSager autosize kontrolskema_font\" name=\"ny_linjetekst\" rows=\"1\" cols=\"10\" style=\"height:16px;width:800px;\" title=\"Hvis teksten skal være fed sættes &lt;b&gt; foran teksten og &lt;/b&gt; efter teksten (F.eks. &lt;b&gt;Lorem ipsum&lt;/b&gt;). Det samme gøres ved Italic, bare med &lt;i&gt; og &lt;/i&gt;. Hvis der skal insættes en blank linje bruges &lt;i&gt;&lt;/i&gt; uden tekst imellem.\"></textarea></td>\n";
	print "<td colspan=\"2\" valign=\"bottom\"><input type=\"submit\" class=\"button gray medium\" accesskey=\"g\" value=\"Gem/Indsæt\" name=\"tekstlinjer\" onclick=\"javascript:docChange = false;\"></td>\n";
	print "</tr>\n";
	print "</tbody></table></form></td></tr></tbody></table>\n";
	print "</div>";
	print "</div><!-- end of wrapper -->\n";
	?>
	<script type="text/javascript">
			// Function som sortere i table
		$("document").ready(function() {
			$(".ordretekstSort .ordretekstListe").tableDnD({
				onDragClass: "highlight",
				onDrop: function(table, row) {
				var orders = $.tableDnD.serialize();
				$.post('../debitor/sort.php', { orders : orders });
				},
				dragHandle: ".dragHandle"
			});
		});
		$("document").ready(function() {
			$(".ordretekstListe tr").hover(function() {
							$(this.cells[0]).addClass('showDragHandle');
				}, function() {
							$(this.cells[0]).removeClass('showDragHandle');
				});
		});
		</script>
	<?php
	print "</body>\n";
	print "</html>\n";
	exit;
}
######################################################################################################################################
function sidehoved($id, $returside, $kort, $fokus, $tekst) {

	global $bgcolor2;
	global $top_bund;
	global $color;
	global $sprog_id;
	global $charset;
	global $sag_rettigheder;
	global $ansat_navn;
	#$returside; maa ikke vaere global
	global $menu;
	global $meta_returside;
	global $bordnr;
	global $popup;
	
	$sag_id=if_isset($_GET['sag_id']);
	$konto_id=if_isset($_GET['konto_id']);
	
	if (!$returside) {
		if ($popup) $returside="../includes/luk.php";  
		else $returside="ordreliste.php";
	}
	if ($id) {
		$r=db_fetch_array(db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		$sag_id=$r['sag_id'];
		$konto_id=$r['konto_id'];
	}
	if ($kort=="../lager/varekort.php" || $kort=="../debitor/debitorkort.php") $ny_id=$id;
	else $ny_id=0;

	$alerttekst=findtekst(154,$sprog_id);

		if ($menu=='T' && !$sag_id) {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		$leftbutton="<a class='button red small' title=\"Klik her for at komme tilbage til ordrelisten\" href=\"../debitor/ordreliste.php\" accesskey=\"L\">Luk</a>";
		print "<div id=\"header\"> \n
			<div class=\"headerbtnLft\">$leftbutton</div>\n
			<span class=\"headerTxt\">$tekst</span>\n";     
		print "<div class=\"headerbtnRght\"></div>";       
		print "</div><!-- end of header -->";
		print "<div class=\"maincontentLargeHolder glow\">\n";
		print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\">";
		} elseif ($sag_id){
		$bg="nix";
		$header='nix';

		$menu_sager='id="menuActive"';
		$menu_dagbog=NULL;
		$menu_kunder=NULL;
		$menu_loen=NULL;
		$menu_ansatte=NULL;
		
		$modulnr=0;
		
		global $brugernavn;
		global $db,$db_skriv_id;
		
		$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$sagsnr=$r['sagsnr'];
		$beskrivelse=htmlspecialchars($r['beskrivelse']);
		$udf_addr1=htmlspecialchars($r['udf_addr1']);
		$udf_postnr=$r['udf_postnr'];
		$udf_bynavn=htmlspecialchars($r['udf_bynavn']); 
		
		$r=db_fetch_array(db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		$nr=$r['nr'];
		$status=$r['status'];
		$fakturanr=$r['fakturanr'];
		$art=$r['art'];
		$kred_ord_id=$r['kred_ord_id'];
	
		include_once '../includes/top_header_sager.php';
		include_once '../includes/top_sagsmenu.php';
			print "<div id=\"breadcrumbbar\">

				<ul id=\"breadcrumb\">
					<li><a href=\"../sager/sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a></li>
					<!--<li><a href=\"#\" title=\"Sample page 1\">Sample page 1</a></li>-->
					<li><a href=\"../sager/sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id\" title=\"Sag: $sagsnr, $beskrivelse, $udf_addr1, $udf_postnr $udf_bynavn\">Tilbage til sag $sagsnr</a></li>\n";
					if (strstr($returside,"ordre.php")) {
						print "<li><a href=\"ordre.php?sag_id=$sag_id&amp;konto_id=$konto_id&amp;tjek=$id&amp;id=$id&amp;returside=sager\" title=\"Tilbud til sag: $sagsnr, $beskrivelse, $udf_addr1, $udf_postnr $udf_bynavn\">Tilbud $nr</a></li>\n";
						print "<li>Opslag</li>\n";
					} elseif ($status >= '3' && $kred_ord_id == NULL) {
						print "<li>Faktura $fakturanr</li>\n";
					} elseif ($status == '0' && $art == 'OT') {
						print "<li>Original tilbud $nr</li>\n";
					} elseif ($kred_ord_id > '0') {
						print "<li>Kreditnota $fakturanr</li>\n";
					} else {
						print "<li>Tilbud $nr</li>\n";
					}
				print "</ul>
			</div><!-- end of breadcrumbbar -->\n";
			
			print "<div class=\"maincontentLargeHolder glow\">\n";
			print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable2\">\n";
			print "<tbody>\n";
	
#	$rightbutton="<a href=\"#\">Ordremenu</a>\t";
#		$rightbutton.="<a href=\"../debitor/ordre.php?returside=../debitor/ordreliste.php?konto_id=$konto_id\">Ny ordre</a>";
#		$rightbutton.="\t<a accesskey=V href=ordrevisning.php?valg=$valg>Visning</a>";
#		include("../includes/topmenu.php");

		} elseif ($menu=='S' && !$sag_id) {
		include("../includes/sidemenu.php");
	} else {
/* 20140502 -> Bliver også sat i online.php
	print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n";
	print "<html><head>\n";
	print "<title>Kundeordre</title>\n";
	print "<meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\">\n";
	if($meta_returside) print "$meta_returside";
	print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/overlib.js\"></script>";
	print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>\n";
	print "</head>\n";
*/
	print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
	print "<div align=\"center\">";

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
		print "<tr><td height = \"25\" align=\"center\" valign=\"top\" colspan=\"6\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		if (!strstr($returside,"ordre.php")) print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$returside','$alerttekst')\" accesskey=L>Luk</a></td>";
		else print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$returside?id=$id','$alerttekst')\" accesskey=L>Luk</a></td>";
		print "<td width=\"80%\" $top_bund> $color$tekst</td>";
		print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$kort?returside=$returside&ordre_id=$ny_id&fokus=$fokus','$alerttekst')\" accesskey=N>Ny</a></td>";
		print "</tbody></table>";
		print "</td></tr>\n";
	}
	print "<tr><td valign=\"top\" align=center>\n";

}
######################################################################################################################################
if (!function_exists('pbsfakt')) {
	function pbsfakt($id) {

		if ($id && $id>0) {
			if ($r=db_fetch_array(db_select("select id from pbs_liste where afsendt = ''",__FILE__ . " linje " . __LINE__))) $liste_id = $r['id'];
			else {
				$liste_date=date("Y-m-d");
				$afsendt=NULL;
				db_modify("insert into pbs_liste (liste_date,afsendt) values ('$liste_date','$afsendt')",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select id from pbs_liste where afsendt = ''",__FILE__ . " linje " . __LINE__));
				$liste_id = $r['id'];
			}
			if (db_fetch_array(db_select("select id from pbs_ordrer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__))) {
				print "<tr><td>Faktura nr $r[fakturanr] findes allerede i PBS liste</td></tr>";
			}	else {
				$r=db_fetch_array(db_select("select fakturanr, konto_id from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
				$konto_id=$r['konto_id'];
				db_modify("insert into pbs_ordrer (liste_id,ordre_id) values ('$liste_id','$id')",__FILE__ . " linje " . __LINE__);
				print "<tr><td>Faktura nr $r[fakturanr] tilf&oslash;jet til PBS liste</td></tr>";	
			}
		}	
	}	
}
##################################################
function pos_afrund($sum,$difkto,$kurs) {
	if ($kurs && $kurs!='100') {
		return($sum);
	}
	if (!$difkto) { #20150724
		$r=db_fetch_array(db_select("select box2 from grupper where art='OreDif'",__FILE__ . " linje " . __LINE__));
		$difkto=$r['box2'];
		if (!$difkto) {
			return($sum);
			exit;
		}
	}
	$negativ=0;
	if ($sum<0) {
		$negativ=1;
		$sum*=-1;			
	}
	if (strpos($sum,".")) list($kr,$ore)=explode(".",$sum);
	else {
		$kr=$sum;
		$ore=0;
	}
if (strlen($ore)>2) { # 20150812
	$a=substr($ore,0,2);
	$b=substr($ore,2);
	$c=$a.".".$b;
	$ore=afrund($c,0);
} elseif (strlen($ore)==1) {
	$ore*=10;
}
#	$ore=substr($sum*100,-2);
	if ($ore<25) $ore=0;
	elseif ($ore>=25 && $ore<75) $ore=50;
	else {
		$kr++;
		$ore=0;
	}
	$sum=($kr*100+$ore)/100;
	if ($negativ) $sum*=-1;
	return($sum);
}
###############################################
//---------------- Sagstyring ---------------//

function opret_ordre($sag_id,$konto_id) {
//exit;
		global $brugernavn;
		global $db,$db_skriv_id;
		global $default_procenttillag;
		
		//#cho '(opret_ordre = '.$brugernavn.')';
		//#cho '(opret_ordre '.$sag_id.')';
		//#cho '(opret_ordre '.$konto_id.')';
		$sag_id=if_isset($_GET['sag_id']);
		$konto_id=if_isset($_GET['konto_id']);
		$tilbud_id=if_isset($_GET['tilbud_id']);
		$returside=urlencode("../sager/sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id");
//if (!strstr($fokus,'lev_') && isset($_GET['konto_id']) && is_numeric($_GET['konto_id'])) { # <- 2008.05.11
	//$konto_id=$_GET['konto_id'];
	
	$query = db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$kontonr=$row['kontonr'];
		$firmanavn=db_escape_string($row['firmanavn']);
		$addr1=db_escape_string($row['addr1']);
		$addr2=db_escape_string($row['addr2']);
		$postnr=trim($row['postnr']);
		$bynavn = trim($row['bynavn']);
		if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
		$bynavn = db_escape_string($bynavn);
		$postnr=db_escape_string($postnr);
		$land=db_escape_string($row['land']);
		$betalingsdage=$row['betalingsdage'];
		$betalingsbet=db_escape_string($row['betalingsbet']);
		$cvrnr=db_escape_string($row['cvrnr']);
		$ean=db_escape_string($row['ean']);
		$institution=db_escape_string($row['institution']);
		$email=db_escape_string($row['email']);
		$mail_fakt=$row['mailfakt'];
		if ($row['pbs_nr']>0) {
			$pbs_nr=$row['pbs_nr'];
			$pbs='bs';
		}
		if(!$sag_id) $kontakt=db_escape_string($row['kontakt']); # 20140821-1
		$notes=db_escape_string($row['notes']);
		$gruppe=db_escape_string($row['gruppe']);
		$kontoansvarlig=db_escape_string($row['kontoansvarlig']);

		$lev_firmanavn=db_escape_string($row['lev_firmanavn']);
		$lev_addr1=db_escape_string($row['lev_addr1']);
		$lev_addr2=db_escape_string($row['lev_addr2']);
		$lev_postnr=trim($row['lev_postnr']);
		$lev_bynavn = trim($row['lev_bynavn']);
		if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
		$lev_bynavn = db_escape_string($lev_bynavn);
		$lev_postnr=db_escape_string($lev_postnr);
		$lev_land=db_escape_string($row['lev_land']);
		$lev_kontakt=db_escape_string($row['lev_kontakt']);
		

		(findtekst(244,$sprog_id) == findtekst(255,$sprog_id))?$felt_1=db_escape_string($row['felt_1']):$felt_1='';
		(findtekst(245,$sprog_id) == findtekst(256,$sprog_id))?$felt_2=db_escape_string($row['felt_2']):$felt_2='';
		(findtekst(246,$sprog_id) == findtekst(257,$sprog_id))?$felt_3=db_escape_string($row['felt_3']):$felt_3='';
		(findtekst(247,$sprog_id) == findtekst(258,$sprog_id))?$felt_4=db_escape_string($row['felt_4']):$felt_4='';
		(findtekst(248,$sprog_id) == findtekst(259,$sprog_id))?$felt_5=db_escape_string($row['felt_5']):$felt_5='';
	}
	if ($kontoansvarlig){
		$query = db_select("select navn from ansatte where id='$kontoansvarlig'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$ref=$row['navn'];
	} else {
		$row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
		if ($row['ansat_id']) {
			$row = db_fetch_array(db_select("select navn from ansatte where id = $row[ansat_id]",__FILE__ . " linje " . __LINE__));
			if ($row['navn']) {$ref=$row['navn'];}
		}
	}
	if ($gruppe){
		$r = db_fetch_array(db_select("select box1,box3,box4,box6,box8 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
		$tmp= substr($r['box1'],1,1)*1;
#		$rabatsats=$r['box6']*1;
		$formularsprog=$r['box4'];
		$valuta=$r['box3'];
		$b2b=$r['box8'];
		$r = db_fetch_array(db_select("select box2 from grupper where art='SM' and kodenr='$tmp'",__FILE__ . " linje " . __LINE__));
		$momssats=$r['box2']*1;
	} elseif ($konto_id) {
			print "<BODY onLoad=\"javascript:alert('Debitoren er ikke tilknyttet en debitorgruppe')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?id=$konto_id&returside=../debitor/ordre.php&ordre_id=$id&fokus=$fokus?id=$id\">\n";
			exit;
	}
	// Her oprettes tilbudsnr
	if ($r=db_fetch_array(db_select("select * from ordrer where sag_id='$sag_id'",__FILE__ . " linje " . __LINE__))) {
		$r=db_fetch_array(db_select("select max(tilbudnr) as tilbudnr from ordrer where sag_id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$tilbudsnummer=$r['tilbudnr'];
		$ny_tilbudnr=explode("-",$tilbudsnummer); // Her fjerner jeg '-', og laver '$ny_tilbudnr' til et array
		$ny_tilbudnr[1]=sprintf("%02s", ($ny_tilbudnr[1]+1)); // Her lægges 1 til det sidste nummer. Bruger 'sprintf()' for at sikre et to cifret tal
		$tilbudnr=implode("-",$ny_tilbudnr); // Her sætter jeg begge numre sammen igen med bindestreg
	} else {
		$r=db_fetch_array(db_select("select sagsnr from sager where id = $sag_id",__FILE__ . " linje " . __LINE__));
		$tilbudnr=$r['sagsnr'].='-01'; // bindestreg foran tilbudsnr. database skal ændres fra integer til text
	}
	// Her laves nr. Nr bliver brugt til visning af tilbuds-liste i sager.php
	if ($r=db_fetch_array(db_select("select * from ordrer where sag_id='$sag_id'",__FILE__ . " linje " . __LINE__))) {
		$r=db_fetch_array(db_select("select max(nr) as nr from ordrer where sag_id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$tilbud_nr=$r['nr']+1;
	} else {
		$tilbud_nr='1';
	}
	// Her hentes oplysninger fra sager
	$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
	$sagsnr=$r['sagsnr'];
	$konto_id=$r['konto_id'];
	//$firmanavn=htmlspecialchars($r['firmanavn']);
	//$addr1=htmlspecialchars($r['addr1']);
	//$addr2=htmlspecialchars($r['addr2']);
	//$postnr=$r['postnr'];
	//$bynavn=htmlspecialchars($r['bynavn']);
	//$beskrivelse=htmlspecialchars($r['beskrivelse']);
	//$omfang=htmlspecialchars($r['omfang']);
	$udf_firmanavn=$r['udf_firmanavn']; // htmlspecialchars
	$udf_addr1=$r['udf_addr1']; // htmlspecialchars
	$udf_addr2=$r['udf_addr2']; // htmlspecialchars
	$udf_postnr=$r['udf_postnr'];
	$udf_bynavn=$r['udf_bynavn']; // htmlspecialchars
	$kundeordnr=$r['kunde_ref_nr']; #20170223
	if($sag_id) {
		$kontakt=$r['kontakt']; // htmlspecialchars # 20140821-1
		$r=db_fetch_array(db_select("select * from ansatte where navn='$kontakt' and konto_id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$kontakt_tlf=$r['mobil']; #20160127
	}
	//$ref=htmlspecialchars($r['ref']);
	//$oprettet_af=htmlspecialchars($r['oprettet_af']);
	//$dato=date("d-m-y",$r['tidspkt']);
	//$tid=date("H:i",$r['tidspkt']);
	//$status=$r['status'];
	
	if ((!$id)&&($firmanavn)) {
		$r=db_fetch_array(db_select("select max(ordrenr) as ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc",__FILE__ . " linje " . __LINE__));
		$ordrenr=$r['ordrenr']+1;

		$ordredate=date("Y-m-d");
		$tidspkt=date("U");
		$default_procenttillag*=1;
		($lev_firmanavn)?$vis_lev_addr='on':$vis_lev_addr='';	
		db_modify("insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,betalingsdage,betalingsbet,cvrnr,ean,institution,email,mail_fakt,notes,art,ordredate,momssats,hvem,tidspkt,ref,valuta,sprog,kontakt,kontakt_tlf,pbs,status,restordre,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,vis_lev_addr,felt_1,felt_2,felt_3,felt_4,felt_5,sag_id,tilbudnr,datotid,nr,returside,sagsnr,procenttillag,kundeordnr) values ($ordrenr,'$konto_id','$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$email','$mail_fakt','$notes','DO','$ordredate','$momssats','$brugernavn','$tidspkt','$ref','$valuta','$formularsprog','$kontakt','$kontakt_tlf','$pbs','0','0','$udf_firmanavn','$udf_addr1','$udf_addr2','$udf_postnr','$udf_bynavn','$lev_kontakt','$vis_lev_addr','$felt_1','$felt_2','$felt_3','$felt_4','$felt_5','$sag_id','$tilbudnr','$tidspkt','$tilbud_nr','$returside','$sagsnr','$default_procenttillag','$kundeordnr')",__FILE__ . " linje " . __LINE__);
		$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $id=$row['id'];
	} //#cho $id;
//} 
return($id);
}

function lagervaerdi ($ordre_id) {
	global $brugernavn;
	global $db,$db_skriv_id;
	global $regnaar;
	
	$x=0;
	$y=0;
	$vkob=array();
	$lagre=array();
	$q=db_select("select * from grupper where art = 'VG' order by box2",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if ($r['box2']) {
			if (!in_array($r['box2'],$lagre)) {
				$lagertilg[$x]=$r['box2'];
				$lagerafg[$x]=$r['box2'];
				$lagervaerdi[$x]=0;
				$x++;
			}
			if (!in_array($r['box3'],$vkob)) {
				$vkob[$y]=$r['box3'];
				$y++;
			}
		}	
	}
	for ($x=0;$x<count($lagre);$x++){
		$q=db_select("select kodenr from grupper where art = 'VG' and box2 = '$lagre[$x]'",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$q2=db_select("select beholdning,kostpris from varer where gruppe = '$r[kodenr]'",__FILE__ . " linje " . __LINE__);
			while($r2=db_fetch_array($q2)) {
				$lagervaerdi[$x]+=$r2['beholdning']*$r2['kostpris'];
			}
		}
	}
	for ($x=0;$x<count($lagre);$x++){
		$r=db_fetch_array($q=db_select("select saldo from kontoplan where kontonr = '$lagre[$x]' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
		if ($r['saldo']!=$lagervaerdi[$x]){
			echo "Lagerdiff! Værdi er:$r[saldo] bør være $lagervaerdi[$x]<br>";
		}
	}
}

function opret_ordre_kopi($sag_id,$konto_id) { #20140730
	global $brugernavn;
	global $db,$db_skriv_id;
	global $default_procenttillag;
		
	$konto_id=$_GET['konto_id'];
	$sag_id=$_GET['sag_id'];
	$ordre_id=$_GET['ordre_id'];
	$returside=urlencode("../sager/sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id");

	//#cho "(opret_ordre_kopi) konto_id: $konto_id<br>";
	//#cho "(opret_ordre_kopi) sag_id: $sag_id<br>";
	//#cho "(opret_ordre_kopi) ordre_id: $ordre_id";
	//exit();
	
	$query = db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$kontonr=$row['kontonr'];
		$firmanavn=db_escape_string($row['firmanavn']);
		$addr1=db_escape_string($row['addr1']);
		$addr2=db_escape_string($row['addr2']);
		$postnr=trim($row['postnr']);
		$bynavn = trim($row['bynavn']);
		if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
		$bynavn = db_escape_string($bynavn);
		$postnr=db_escape_string($postnr);
		$land=db_escape_string($row['land']);
		$betalingsdage=$row['betalingsdage'];
		$betalingsbet=db_escape_string($row['betalingsbet']);
		$cvrnr=db_escape_string($row['cvrnr']);
		$ean=db_escape_string($row['ean']);
		$institution=db_escape_string($row['institution']);
		$email=db_escape_string($row['email']);
		$mail_fakt=$row['mailfakt'];
		if ($row['pbs_nr']>0) {
			$pbs_nr=$row['pbs_nr'];
			$pbs='bs';
		}
		$kontakt=db_escape_string($row['kontakt']);
		$notes=db_escape_string($row['notes']);
		$gruppe=db_escape_string($row['gruppe']);
		$kontoansvarlig=db_escape_string($row['kontoansvarlig']);

		$lev_firmanavn=db_escape_string($row['lev_firmanavn']);
		$lev_addr1=db_escape_string($row['lev_addr1']);
		$lev_addr2=db_escape_string($row['lev_addr2']);
		$lev_postnr=trim($row['lev_postnr']);
		$lev_bynavn = trim($row['lev_bynavn']);
		if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
		$lev_bynavn = db_escape_string($lev_bynavn);
		$lev_postnr=db_escape_string($lev_postnr);
		$lev_land=db_escape_string($row['lev_land']);
		$lev_kontakt=db_escape_string($row['lev_kontakt']);
		

		(findtekst(244,$sprog_id) == findtekst(255,$sprog_id))?$felt_1=db_escape_string($row['felt_1']):$felt_1='';
		(findtekst(245,$sprog_id) == findtekst(256,$sprog_id))?$felt_2=db_escape_string($row['felt_2']):$felt_2='';
		(findtekst(246,$sprog_id) == findtekst(257,$sprog_id))?$felt_3=db_escape_string($row['felt_3']):$felt_3='';
		(findtekst(247,$sprog_id) == findtekst(258,$sprog_id))?$felt_4=db_escape_string($row['felt_4']):$felt_4='';
		(findtekst(248,$sprog_id) == findtekst(259,$sprog_id))?$felt_5=db_escape_string($row['felt_5']):$felt_5='';
	}
	if ($kontoansvarlig){
		$query = db_select("select navn from ansatte where id='$kontoansvarlig'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$ref=$row['navn'];
	} else {
		$row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
		if ($row['ansat_id']) {
			$row = db_fetch_array(db_select("select navn from ansatte where id = $row[ansat_id]",__FILE__ . " linje " . __LINE__));
			if ($row['navn']) {$ref=$row['navn'];}
		}
	}
	if ($gruppe){
		$r = db_fetch_array(db_select("select box1,box3,box4,box6,box8 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
		$tmp= substr($r['box1'],1,1)*1;
#		$rabatsats=$r['box6']*1;
		$formularsprog=$r['box4'];
		$valuta=$r['box3'];
		$b2b=$r['box8'];
		$r = db_fetch_array(db_select("select box2 from grupper where art='SM' and kodenr='$tmp'",__FILE__ . " linje " . __LINE__));
		$momssats=$r['box2']*1;
	} elseif ($konto_id) {
			print "<BODY onLoad=\"javascript:alert('Debitoren er ikke tilknyttet en debitorgruppe')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?id=$konto_id&returside=../debitor/ordre.php&ordre_id=$id&fokus=$fokus?id=$id\">\n";
			exit;
	}
	// Her oprettes tilbudsnr
	if ($r=db_fetch_array(db_select("select * from ordrer where sag_id='$sag_id'",__FILE__ . " linje " . __LINE__))) {
		$r=db_fetch_array(db_select("select max(tilbudnr) as tilbudnr from ordrer where sag_id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$tilbudsnummer=$r['tilbudnr'];
		$ny_tilbudnr=explode("-",$tilbudsnummer); // Her fjerner jeg '-', og laver '$ny_tilbudnr' til et array
		$ny_tilbudnr[1]=sprintf("%02s", ($ny_tilbudnr[1]+1)); // Her lægges 1 til det sidste nummer. Bruger 'sprintf()' for at sikre et to cifret tal
		$tilbudnr=implode("-",$ny_tilbudnr); // Her sætter jeg begge numre sammen igen med bindestreg
	} else {
		$r=db_fetch_array(db_select("select sagsnr from sager where id = $sag_id",__FILE__ . " linje " . __LINE__));
		$tilbudnr=$r['sagsnr'].='-01'; // bindestreg foran tilbudsnr. database skal ændres fra integer til text
	}
	// Her laves nr. Nr bliver brugt til visning af tilbuds-liste i sager.php
	if ($r=db_fetch_array(db_select("select * from ordrer where sag_id='$sag_id'",__FILE__ . " linje " . __LINE__))) {
		$r=db_fetch_array(db_select("select max(nr) as nr from ordrer where sag_id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$tilbud_nr=$r['nr']+1;
	} else {
		$tilbud_nr='1';
	}
	// Her hentes oplysninger fra sager
	$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
	$sagsnr=$r['sagsnr'];
	//$konto_id=$r['konto_id'];
	//$firmanavn=htmlspecialchars($r['firmanavn']);
	//$addr1=htmlspecialchars($r['addr1']);
	//$addr2=htmlspecialchars($r['addr2']);
	//$postnr=$r['postnr'];
	//$bynavn=htmlspecialchars($r['bynavn']);
	//$beskrivelse=htmlspecialchars($r['beskrivelse']);
	//$omfang=htmlspecialchars($r['omfang']);
	$udf_firmanavn=htmlspecialchars($r['udf_firmanavn']);
	$udf_addr1=htmlspecialchars($r['udf_addr1']);
	$udf_addr2=htmlspecialchars($r['udf_addr2']);
	$udf_postnr=$r['udf_postnr'];
	$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
	$sag_kontakt=htmlspecialchars($r['kontakt']);
	if ($sag_kontakt) { #20160201
		$kontakt=$sag_kontakt;
		$r=db_fetch_array(db_select("select * from ansatte where navn='$kontakt' and konto_id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$kontakt_tlf=$r['mobil'];
	} elseif ($kontakt && !$sag_kontakt) {
		$r=db_fetch_array(db_select("select * from ansatte where navn='$kontakt' and konto_id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$kontakt_tlf=$r['mobil'];
	}
	//$ref=htmlspecialchars($r['ref']);
	//$oprettet_af=htmlspecialchars($r['oprettet_af']);
	//$dato=date("d-m-y",$r['tidspkt']);
	//$tid=date("H:i",$r['tidspkt']);
	//$status=$r['status'];
	/*
	cho "konto_id: $konto_id<br>"; #exit();
	cho "kontakt: $kontakt<br>"; #exit();
	cho "sag_kontakt: $sag_kontakt<br>"; #exit();
	cho "kontakt_tlf: $kontakt_tlf<br>"; exit();
	*/
	if ((!$id)&&($firmanavn)) {
		$r=db_fetch_array(db_select("select max(ordrenr) as ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc",__FILE__ . " linje " . __LINE__));
		$ordrenr=$r['ordrenr']+1;

		$ordredate=date("Y-m-d");
		$tidspkt=date("U");
		$default_procenttillag*=1;
		($lev_firmanavn)?$vis_lev_addr='on':$vis_lev_addr='';	
		db_modify("insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,betalingsdage,betalingsbet,cvrnr,ean,institution,email,mail_fakt,notes,art,ordredate,momssats,hvem,tidspkt,ref,valuta,sprog,kontakt,kontakt_tlf,pbs,status,restordre,lev_navn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,vis_lev_addr,felt_1,felt_2,felt_3,felt_4,felt_5,sag_id,tilbudnr,datotid,nr,returside,sagsnr,procenttillag) values ($ordrenr,'$konto_id','$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$betalingsdage','$betalingsbet','$cvrnr','$ean','$institution','$email','$mail_fakt','$notes','DO','$ordredate','$momssats','$brugernavn','$tidspkt','$ref','$valuta','$formularsprog','$kontakt','$kontakt_tlf','$pbs','0','0','$udf_firmanavn','$udf_addr1','$udf_addr2','$udf_postnr','$udf_bynavn','$lev_kontakt','$vis_lev_addr','$felt_1','$felt_2','$felt_3','$felt_4','$felt_5','$sag_id','$tilbudnr','$tidspkt','$tilbud_nr','$returside','$sagsnr','$default_procenttillag')",__FILE__ . " linje " . __LINE__);
		
		$r=db_fetch_array(db_select("select max(id) as id from ordrer where sag_id = '$sag_id'",__FILE__ . " linje " . __LINE__));
		$nyordre_id=$r['id'];
		
		$x=0;
		$q=db_select("select * from ordrelinjer where ordre_id = '$ordre_id'",__FILE__ . " linje " . __LINE__);
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
			$lager_OT[$x]=$r['lager']*1;
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
					$qtxt ="insert into ordrelinjer ";
					$qtxt.="(varenr,beskrivelse,enhed,posnr,$pris_tbl[$x] $rabat_tbl[$x] lev_varenr,ordre_id,serienr,";
					$qtxt.="$vare_id_tbl[$x] $antal_tbl[$x] $leveres_tbl[$x] $leveret_tbl[$x] $bogf_konto_tbl[$x] oprettet_af,bogfort_af,hvem,tidspkt,";
					$qtxt.="$kred_linje_id_tbl[$x] momsfri,$momssats_tbl[$x] $kostpris_tbl[$x] samlevare,projekt,";
					$qtxt.="$m_rabat_tbl[$x] $rabatgruppe_tbl[$x] $folgevare_tbl[$x] kdo,rabatart,variant_id,procent,lager)";
					$qtxt.=" values ";
					$qtxt.="('$varenr_OT[$x]','$beskrivelse_OT[$x]','$enhed_OT[$x]','$posnr_OT[$x]',$pris_value[$x] $rabat_value[$x] '$lev_varenr_OT[$x]',";
					$qtxt.="'$nyordre_id','$serienr_OT[$x]',";
					$qtxt.="$vare_id_value[$x] $antal_value[$x] $leveres_value[$x] $leveret_value[$x] $bogf_konto_value[$x] '$oprettet_af_OT[$x]',";
					$qtxt.="'$bogfort_af_OT[$x]','$hvem_OT[$x]','$tidspkt_OT[$x]',$kred_linje_id_value[$x] '$momsfri_OT[$x]',";
					$qtxt.="$momssats_value[$x] $kostpris_value[$x] '$samlevare_OT[$x]','$projekt_OT[$x]',";
					$qtxt.="$m_rabat_value[$x] $rabatgruppe_value[$x] $folgevare_value[$x] '$kdo_OT[$x]',";
					$qtxt.="'$rabatart_OT[$x]','$variant_id_OT[$x]','$procent_OT[$x]',$lager_OT[$x])";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		
		$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $id=$row['id'];
	} //#cho $id;
//} 
return($id);
} #endfunc opret_ordre_kopi
/*
function saet_afrund($id,$sum,$moms,$difkto) {
	$diff=0;
	$tmp1=afrund($sum+$moms,2);
	$tmp2=pos_afrund($tmp1,$difkto);
	if ($afrunding=$tmp2-$tmp1) {
		if ($moms) {
			$afrundingsmoms=afrund($afrunding*($moms*100/$sum)/100,2);
			$afrunding=afrund($afrunding-$afrundingsmoms,2);
		} 
		$diff=$afrunding;
	}
	db_modify("insert into ordrelinjer (posnr,antal,pris,rabat,procent,ordre_id,bogf_konto,beskrivelse,projekt,saet) values ('0','1', '$afrunding', 0,100, '$id', '$difkto','Afrunding','0','0')",__FILE__ . " linje " . __LINE__);
	db_modify("update ordrer set sum = sum+$afrunding,moms=moms+$afrundingsmoms where id='$id'",__FILE__ . " linje " . __LINE__);
}
*/
function opret_saet($id,$master_id,$saetpris,$momssats,$antal_ny,$incl_moms,$lager) {
#cho __line__." ($id,$master_id,$saetpris,$momssats,$antal_ny,$incl_moms,$lager)<br>";
#	$log=fopen("../temp/opret_saet.log","a");
#	fwrite($log,"opret_saet($id,$master_id,$saetpris,$momssats,$antal_ny,$incl_moms,$lager)\n");
	gendan_saet($id);
#	$antal_ny*=1; remmet 20150312 da værdien skal være 1 hvis der ikke er nogen værdi!
 	if (!$antal_ny && $antal_ny!='0') $antal_ny=1;
	$antal_ny*=1;
	if (!$antal_ny) return 0;
	$saetpris*=$antal_ny;
	if (!$saetpris) {	
		$r=db_fetch_array(db_select("select salgspris from varer where id = '$master_id'",__FILE__ . " linje " . __LINE__));
		$saetpris=$r['salgspris'];
		if ($incl_moms) {
			$r=db_fetch_array(db_select("select momssats from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
			$momssats=$r['momssats'];
			$saetpris+=$saetpris*$momssats/100; 
		}
	}
	if (!$momssats && $incl_moms) {
		$r=db_fetch_array(db_select("select momssats from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$momssats=$r['momssats'];
		$saetpris+=$saetpris*$momssats/100; 
	}
	$r=db_fetch_array(db_select("select max(saet) as saetnr from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__));
	$saetnr=$r['saetnr']+1;
	$normalsum=0;
	$q=db_select("select vare_id,antal from styklister where indgaar_i = '$master_id' order by posnr",__FILE__ . " linje " . __LINE__);
	$x=0;
	while($r=db_fetch_array($q)) {
		$antal[$x]=$r['antal']*$antal_ny;
		$vare_id[$x]=$r['vare_id'];
		$r2=db_fetch_array(db_select("select varer.id,varer.varenr,varer.salgspris,varer.beskrivelse,varer.gruppe,grupper.box7 from varer,grupper where varer.id = '$r[vare_id]' and grupper.kodenr=varer.gruppe",__FILE__ . " linje " . __LINE__));
		$varenr[$x]=$r2['varenr'];
		$pris[$x]=afrund($r2['salgspris'],2);
		$beskrivelse[$x]=$r2['beskrivelse'];
		$momsfri[$x]=$r2['box7'];
#fwrite($log,__line__." $antal[$x],$pris[$x]$beskrivelse[$x] $momsfri[$x])\n");
		if ($incl_moms && $momssats && !$momsfri[$x]) {
			$pris[$x]+=afrund($pris[$x]*$momssats/100,3);
		}
		$linjepris[$x]=afrund($antal[$x]*$pris[$x],3);
		$normalsum+=$linjepris[$x];
#fwrite($log,__line__." $antal[$x],$pris[$x]$beskrivelse[$x] $momsfri[$x] )\n");
		$x++;
	}
	$rabat=$normalsum-$saetpris;
	$rabat=afrund($rabat*100/$normalsum,3);
	$tjeksum=0;
	for ($x=0;$x<count($vare_id);$x++) {
#fwrite($log,__line__." opret_ordrelinje($id,$vare_id[$x],$varenr[$x],$antal[$x],'',$pris[$x],$rabat,100,'PO','','','0',$incl_moms,'','','',$saetnr,'','',$lager,)\n");
		opret_ordrelinje($id,$vare_id[$x],$varenr[$x],$antal[$x],'',$pris[$x],$rabat,100,'PO','','','0',$incl_moms,'','','',$saetnr,'','',$lager,__LINE__);

		$linjesum=$antal[$x]*$pris[$x];
		$linjesum-=afrund($linjesum*$rabat/100,3);
#		if ($momssats && !$momsfri[$x]) $linjesum+=afrund($linjesum*$momssats/100,2);
		$tjeksum+=$linjesum;
	}
	$diff=afrund($saetpris-$tjeksum,3);
	$r=db_fetch_array(db_select("select id,varenr,salgspris,beskrivelse from varer where id = '$master_id'",__FILE__ . " linje " . __LINE__));
	$lineDiscount=$momssats*100/(100+$momssats); // Giver umiddelbart ikke mening, men det skyldes at selve samlevaren ikke er momsbelagt.
	opret_ordrelinje($id,$r['id'],$r['varenr'],1,'',$diff,$lineDiscount,100,'PO','','','0',$incl_moms,'','','',$saetnr,'','',$lager,__LINE__);
	$lev_varenr=$saetpris."|".$rabat;
	db_modify("update ordrelinjer set samlevare='on',lev_varenr='$lev_varenr',kostpris='0' where ordre_id='$id' and saet='$saetnr' and vare_id='$master_id'",__FILE__ . " linje " . __LINE__);
} #endfunc opret_saet

function gendan_saet($id) {
	$r=db_fetch_array(db_SELECT("select box2 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
	$rabatvare_id=$r['box2']*1;
	if ($r=db_fetch_array(db_select("select id from ordrelinjer where vare_id = '$rabatvare_id' and ordre_id = $id",__FILE__ . " linje " . __LINE__))) {
		db_modify("update ordrelinjer set rabat = '0' where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		db_modify("delete from ordrelinjer where id='$r[id]'",__FILE__ . " linje " . __LINE__);
		$q=db_select("select id,lev_varenr from ordrelinjer where ordre_id = '$id' and saet > '0' order by saet,samlevare desc",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['lev_varenr']) list($saetsum,$rabat)=explode("|",$r['lev_varenr']);
			if ($rabat*=1) {
				db_modify("update ordrelinjer set rabat = '$rabat' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			}
		}
	}
#fclose($log);
} # endfunc gendan_saet
function slet_ordre ($ordre_id) {
	#20171031 ->
	$qtxt="select status,ordrenr from ordrer where id='$ordre_id'"; 
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['status']>2) {
		$txt="Ordre $r[ordrenr] er faktureret og kan ikke slettes";
		print tekstboks($txt);
		return($txt);
		exit;
	} else $ordrenr=$r['ordrenr'];
	
	$qtxt="select id from batch_salg where ordre_id='$ordre_id' limit 1";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['id']>2) {
		$txt="Der er leveret varer på ordre $ordrenr - den kan ikke slettes";
		print tekstboks($txt);
		return($txt);
		exit;
	}
	# <- 20171031
	$x=0;
	$q = db_select("select kodenr from grupper where art = 'VG' and box8='on' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$gruppe[$x]=$r['kodenr'];
		$x++;
	}
	transaktion('begin');

	db_modify("delete from ordrelinjer where ordre_id=$ordre_id",__FILE__ . " linje " . __LINE__);
	db_modify("delete from ordrer where id=$ordre_id",__FILE__ . " linje " . __LINE__);
	db_modify("delete from shop_ordrer where saldi_id='$ordre_id'",__FILE__ . " linje " . __LINE__);

	$q = db_select("select id,batch_kob_id,antal,vare_id,lager from batch_salg where ordre_id = $ordre_id",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$batch_salg_id=$r['id'];
		$batch_kob_id=$r['batch_kob_id'];
		$antal=$r['antal'];
		$vare_id=$r['vare_id'];
		$lager=$r['lager']*1;
		
		$r=db_fetch_array(db_select("select gruppe from varer where id = $vare_id",__FILE__ . " linje " . __LINE__));
		if (in_array($r['gruppe'],$gruppe)) {
			if ($batch_kob_id) {
				$qtxt="update batch_kob set rest=rest+$antal where id ='$batch_kob_id'";
#cho $qtxt."<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			$qtxt="update varer set beholdning=beholdning+$antal where id ='$vare_id'";
#cho $qtxt."<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="update lagerstatus set beholdning=beholdning+$antal where vare_id ='$vare_id' and variant_id ='$variant_id' and lager='$lager'";
#cho $qtxt."<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$qtxt="delete from batch_salg where id ='$batch_salg_id'";
#cho $qtxt."<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}	
	transaktion('commit');
} #endfunc slet_ordre

if (!function_exists('find_nextfakt')) {
	function find_nextfakt($fakturadate,$nextfakt) {
// Denne funktion finder diff mellem fakturadate & nextfakt, tillaegger diff til nextfakt og returnerer denne vaerdi. Hvis baade
// fakturadate og netffaxt er sidste dag i de respektive maaneder vaelges ogsaa sidste dag i maaned i returvaerdien.
	list($faktaar,$faktmd,$faktdag) = explode("-",$fakturadate);
	list($nextfaktaar,$nextfaktmd,$nextfaktdag) = explode("-",$nextfakt);
	if (!checkdate($faktmd,$faktdag,$faktaar)) {
		echo "Fakturadato er ikke en gyldig dato<br>";
		exit;
	}
	if (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) {
		echo "Genfaktureringsadato er ikke en gyldig dato<br>";
		exit;
	}

	$faktultimo=0;
	$nextfaktultimo=0;
	$tmp=$faktdag+1;
	if (!checkdate($faktmd,$tmp,$faktaar)) $faktultimo=1; # hvis dagen efter fakturadag ikke findes fakureres ultimo"
	$tmp=$nextfaktdag+1;
	if (!checkdate($nextfaktmd,$tmp,$nextfaktaar)) $nextfaktultimo=1;
	$faktmd_len=31;
	while (!checkdate($faktmd,$faktmd_len,$faktaar)) $faktmd_len--; #finder antal dage i fakturamaaneden
	$dagantal=$nextfaktdag-$faktdag;
	$md_antal=$nextfaktmd-$faktmd;
	$aar_antal=$nextfaktaar-$faktaar;
	if ($dagantal<0) {
		$dagantal=$dagantal+$faktmd_len;
		$md_antal--;
	}
	while ($md_antal<0) {
		$aar_antal--;
		$md_antal=$md_antal+12;
	}
	$nextfaktaar=$nextfaktaar+$aar_antal;
	$nextfaktmd=$nextfaktmd+$md_antal;
	if ($nextfaktmd > 12) {
		$nextfaktaar++;
		$nextfaktmd=$nextfaktmd-12;
	}
	if ($faktultimo && $nextfaktultimo) {# fast faktura sidste dag i md.
		$nextfaktdag=31;
		if ($dagantal>27) $nextfaktmd++;
		while (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) $nextfaktdag--;
	} else {
		$nextfaktdag=$nextfaktdag+$dagantal;
		if ($nextfaktdag>$faktmd_len) {
			while (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) {
			$nextfaktmd++;
				if ($nextfaktmd > 12) {
					$nextfaktaar++;
					$nextfaktmd=1;
				}
				if ($nextfaktdag>$faktmd_len) {
					$faktmd_len=31; # 2017-01-03 + næste 3
					while (!checkdate($nextfaktmd,$faktmd_len,$nextfaktaar)) $faktmd_len--;
					$nextfaktdag-=$faktmd_len;
					if ($nextfaktdag < 1) $nextfaktdag=1; #20171101
				}
			}
		} else while (!checkdate($nextfaktmd,$nextfaktdag,$nextfaktaar)) $nextfaktdag--;
	}
	$nextfakt=$nextfaktaar."-".$nextfaktmd."-".$nextfaktdag;
	return($nextfakt);
}}# endfunc find_nextfakt							

if (!function_exists('gls_label')) {
 function gls_label($gls_username,$gls_password,$gls_customerid,$gls_ctId,$ordrenr,$kundeordnr,$firmanavn,$addr1,$postnr,$bynavn,$land,$email,$lev_navn,$lev_addr1,$lev_postnr,$lev_bynavn,$lev_land,$kontakt) {
		if($land == 'Sweden'){
			$code = '752';
		} else if($land == 'Sverige'){
			$code = '752';
		}else if($land == 'sverige'){
			$code = '752';
		} else if($land == 'Denmark'){
			$code = '208';
		} else if($land == 'Danmark'){
			$code = '208';
		} else {
			$code = '208';
		}
    if(!empty($lev_navn)){
			$name = $lev_navn;
			$strrrt1 = $lev_addr1;
			$city = $lev_bynavn;
			$zipcode = $lev_postnr;
		} else {
			$name = $firmanavn;
			$strrrt1 = $addr1;
			$city = $bynavn;
			$zipcode = $postnr;
		}
		$name=html_entity_decode($name, ENT_QUOTES, 'UTF-8');  
		$strrrt1=html_entity_decode($strrrt1, ENT_QUOTES, 'UTF-8');
		$zipcode=html_entity_decode($zipcode, ENT_QUOTES, 'UTF-8');
		$kontakt=html_entity_decode($kontakt, ENT_QUOTES, 'UTF-8');
		$city=html_entity_decode($city, ENT_QUOTES, 'UTF-8');
		$ary=[
			"UserName"=>$gls_username,
			"Password"=>$gls_password,
			"Customerid"=>$gls_customerid,
			"Contactid"=>$gls_ctId,
			"ShipmentDate"=>date('Ymd'),
			"Reference"=>$ordrenr,
			"Addresses"=>[
				"Delivery"=>[
					"Name1"=>substr($name, 0, 39),
					"Street1"=>$strrrt1,
					"CountryNum"=>$code,
					"ZipCode"=>$zipcode,
					"City"=>$city,
					"Contact"=>substr($kontakt, 0, 39),
					"Email"=>$email,
				]
			],
			"Parcels"=>[
				[
					"Weight"=>"1",
					"Reference"=>$kundeordnr,
				]
			]
		];
		$json=  json_encode($ary);
		$service_url = 'http://api.gls.dk/ws/DK/V1/CreateShipment/';
// jSON String for request
		$json_string = $json;
// Initializing curl
		$ch = curl_init( $service_url );
// Configuring curl options
		$options = array(
			CURLOPT_RETURNTRANSFER => true,
//CURLOPT_USERPWD => $username . ":" . $password,  // authentication
//CURLOPT_HTTPHEADER => array('Content-type: application/json; charset=ISO-8859-1') ,
			CURLOPT_HTTPHEADER => array('Content-type: application/json; charset=ISO-8859-1') ,
			CURLOPT_POSTFIELDS => $json_string
		);

// Setting curl options
		curl_setopt_array( $ch, $options );

// Getting results
		$result = curl_exec($ch); // Getting jSON result string
		$res=(json_decode($result));
		ob_clean();

/* GLS funktion slut */

		header('Content-Disposition: attachment; filename='.$ordrenr.'.pdf');
		header('Content-Type: text/html; charset: utf-8');
		header('Content-Description: File Transfer');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		echo base64_decode($res->PDF);
	}
}

if (!function_exists('registrer_betaling')) {
function registrer_betaling($betalings_id,$ordre_id,$betalingstype,$amount,$valuta,$valutakurs,$terminal_ip) {
	global $db,$db_skriv_id;
	
	$godkendt=NULL;
	
	if ($betalingstype == '!') {
		$termfile="../temp/$db/$ordre_id-$betalings_id.txt";
		
		if (file_exists($termfile)) {
			$tf=fopen($termfile,'r');
			list($korttype,$betalt)=explode(chr(9),trim(fgets($tf)));
			if ($betalt==$amount) $godkendt='OK';
		}
		$gf=fopen("../temp/$db/godkendt.txt","a"); #20180816
		if ($godkendt) {
			$txt="pos_ordre.php?id=$ordre_id&modtaget=$amount&korttype=$korttype&betaling=$korttype&godkendt=OK";
			fwrite($gf,"\n".__file__." ".__line__." ".date("H:i:s")." $txt\n");
			fclose($gf);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$txt\">\n";
			exit;
		} else {
			$qtxt="select box5 from grupper where art = 'POS' and kodenr = '1'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$korttyper=explode(chr(9),$r['box5']);
			$qtxt="select box5 from grupper where art = 'POS' and kodenr = '2'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$betalingskort=explode(chr(9),$r['box5']);
			$indbetaling=0;
			$qtxt="select konto_id from ordrer where id = '$ordre_id'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['konto_id']) {
				$qtxt="select count(id) as tmp from ordrelinjer where ordre_id = '$ordre_id'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if (!$r['tmp']) $indbetaling=$amount;
			}
			print "<table align='center'><tbody>";
			fwrite($gf,"\n".__file__." ".__line__." ".date("H:i:s"));
			fwrite($gf," Fejlet betaling: ID $ordre_id  kr. ".dkdecimal($amount,2)." \n");
			$txt="Der er registreret en påbegyndt betaling på kr. ".dkdecimal($amount,2).", hvor";
			print "<tr><td>$txt</td></tr>";
			$txt="svaret fra terminalen ikke er kommet retur tra transaktionen!";
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			$txt="Tjek betalingsudskriften om der står <b>AUTORISERET</b> i bunden";
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			$txt="Gør der det er transaktionen gennemført med det beløb der står på udskriften";
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			$txt="Vælg korrekt betalingsmetode, hvis den er gennemført";
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			$txt="eller vælg Afvist/Annulleret, hvis den er afvist eller annulleret";
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			$txt="";
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			$txt="Er der ingen udskrift, så klik på kortterminal og vælg 'Udskriv sidste'";
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			print "<tr><td><br></td></tr>";
			fclose($gf);
			$txt="Bekræft at korttransaktionen er gennemført";
			for ($x=0;$x<count($korttyper);$x++) {
				if ($betal_type!=$korttyper[$x] && $betalingskort[$x]) {
					print "<tr><td align='center'><a href='pos_ordre.php?id=$ordre_id&modtaget=$amount&korttype=$korttyper[$x]&betaling=$korttyper[$x]&indbetaling=$indbetaling&godkendt=OK' onclick=\"return confirm('$txt')\"><button style=\"width:250px;\">$korttyper[$x]</button></a></td></tr>";
				}
			}
			$txt="Bekræft at korttransaktionen er annulleret/afvist";
			print "<tr><td align='center'><hr style=\"width:250px;\"></td></tr>";
			print "<tr><td align='center'><a href='pos_ordre.php?id=$ordre_id&godkendt=afvist'onclick=\"return confirm('$txt')\"><button style=\"width:250px;\">Afvist/Annulleret</button></a></td></tr>";
			print "<tr><td align='center'><hr style=\"width:250px;\"></td></tr>";
			print "<tr><td align='center'><a href='pos_ordre.php?id=$ordre_id&godkendt=afvist'><button style=\"width:250px;\">Afstemning er ikke foretaget</button></a></td></tr>";
			print "<tr><td align='center'><hr style=\"width:250px;\"></td></tr>";
			print "<tr><td align='center'><a href='pos_ordre.php?id=$ordre_id&godkendt=afvist'><button style=\"width:250px;\">Terminal ikke startet</button></a></td></tr>";
			if ($_SERVER['HTTPS']) $url='https://';
			else $url='http://';
			$url.=$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
			if ($_COOKIE['salditerm']) $terminal_ip=$_COOKIE['salditerm'];
			if ($terminal_ip=='box' || $terminal_ip=='saldibox') {
				$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
				if ($fp=fopen($filnavn,'r')) {
					$terminal_ip=trim(fgets($fp));
					fclose ($fp);
				}
			} # else $terminal_ip=$printserver;
			print "<tr><td align='center'><hr style=\"width:250px;\"></td></tr>";
			print "<tr><td align='center'><a href='http://$terminal_ip/pointd/point.php?url=$url&id=$ordre_id'><button style=\"width:250px;\">Kortterminal</button></td></tr>";
			print "</tbody></table>";
		}
	}
	exit;
}}
?>
