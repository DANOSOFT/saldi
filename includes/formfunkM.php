<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------------includes/formfunk.php ------patch 4.0.3 ----2021-10-28--------------
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
// Copyright (c) 2003-2021 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 2012.09.06 Tilføjet mulighed for at vise momssats på ordrelinjer. 
// 2012.10.03 Søg 20121003
// 2012.10.04 Gmail afviser mails hvor 'from' ikke er *.saldi.dk søg 20121004
// 2012.10.16 Revision af ovenstående for bedre identifikation af afsender. søg 20121016
// 2012.11.09 Mulif for if($ordre_xxx) på alle variabler fra ordre tabellen søg 20121109
// 2013.02.10 Break ændret til break 1
// 2013.02.12 Tilføjet linjemoms og varemomssats, søg linjemoms eller varemomssats 
// 2013.03.19 Tilføjet udskrivning af kontokort
// 2013.03.20 Tilføjet mulighed for fravalg af logo på udskrift. Søg "PDF-tekst"
// 2013.05.08 Tilføjet kontrol af status og fakturanr inden udskrivning af faktura / kn. for at undgå udskrift af fakt. ved bogf. fejl 20130508
// 2013.06.18 Tilføjet udskrivning af indkøbsforslag, rekvisition & lev-faktura
// 2013.06.19 Rettet udskriv_til til udskriv_alle_til i funktion formularprint for at undgå udskrivning til email v. masseudskrivning til pdf
// 2013.12.02	Efter udskrivning af kreditorordre, åbnes ordre som debitorordre. Tilføjer $art. Søg 20131202.
// 2014.01.02 Fjernet ob_start i toppen. Havde ingen funktion og generede autofakturering fra shop. 
// 2014.01.12 Tilføjet alternative mailtekster og bilagshåndtering. Mest i funktion send_mails
// 2014.01.14 Tilføjet manglende lev_varenr så den kommer korrekt med på indkøbsordrer. Søg lev_varenr
// 2014.04.14 Ændret tegnsæt fra Latin1 til Latin9 så blandt andet Eurotegnet € er understøttet.
//            PostScript-skabelonen faktinit.ps er også ændret til understøttelse af Latin9 (Claus Agerskov).
// #2104.04.23 Indsat $id*=1 for at undgå fejlmelding ved udskrivning at bl.a. rykker.
// 2014.05.05 Indsat $posnr,$varenr,$dkantal,$enhed,$dkpris,$dkprocent,$serienr,$varemomssats så der kan anvendes variabler på ordrelinjer. Søg 20140505
// 2014.05.14 Ændret ovenstående så der erstattes både med og uden semikolon i enden af variablen. Søg 20140514
// 2014.06.28	Gebyr blev altid vist i dkk uanset valuta. Søg 20140628
// 2014.08.18 Fejl på momsgrundlag & momssum da momssum blev beregnet efter dkdecimal på linjesum. Dkdecimal flyttet til under beregning. Søg 20140818
// 2014.08.19 Fejl på "fed" og "italic" ved tekster med ombrydning. Rutiner nu som big & small . Søg fed eller italic.
// 2014.09.05 Konflikt med Global $fed og $italic ved tekstombrydning. Er ændret til $startfed & $startitalic. Søg startfed eller startitalic.
// 2014.12.11 Rentesum blev kun hentet fra en linje.
// 2015.01.30 Tilføjet erstatning af specifikke ISO-8859-1-tegn i UTF-8 til ekvivalente tegn i ISO-8859-15. Søg 20150130.
// 2015.01.31 Tilføjet sætpriser, søg $saet eller $rvnr. 
// 2015.03.02 Saet og rabat skal ikke med på følgeseddel.
// 2015.03.02	Hvis vis_saet er aktiv bruger felt 1-4 til betalingsinfo og felt 2 & 4 "dkdecimales". Hvis afd finved afd navn.
// 2015.03.05	Variabel $q ændret til $o da $q er reseveret til queries Søg $o
// 2015.04.11 Tilføjet lokation fra varekort på følgeseddel. Hvis der er afdeling på ordren og der er | i lokationen splittes lokationen. Søg lokation. 
// 2015.05.19 PHR Tilføjet brug af variabler i mail subjekt og tekster. 
// 2015.06.01	PHR SMTP kan nu anvendes med brugernavn, adgangskode og kryptering.
// 2016.11.25 PHR Indført html som formulargenerator som alternativ til postscript. Søg htmfp, .htm & weasyprint
// 2017.02.16	PHR	Fjernet tilsyneladende overføldig linje i funktion utf8_iso8859 20170216
// 2017.05.01	PHR Ved udskriv til 'Ingen' returneres med OK : 20170501
// 2017.08.22	PHR Tilføjet lokation og vare note på pluklister & følgesedler - Søg 'lokation' og 'vare_note'; 
// 2018.03.02 PHR Samlevarefelt sættes til '' hvis NULL da kommentarer tilføjet efter fakturering ellers ikke kommer med på følgesedler #20180302
// 2018.09.07 PHR Tilføjet function kontoprint i forbindelse med indførelse af mulighed for at sende kontoudtog som PDF
// 2018.12.11 PHR Diverse rettelser relateret til HTML udskrift / masseudskrivning 
// 2019.01.15 PHR Ændret lidt i query grundet manglende ordrelinje i udskrevet faktura 
// 2019.04.30 PHR Descritption changed #20190430
// 2019.08.16 PHR Varius changes in function kontoprint, related to PDF creation
// 2019.10.10	PHR function kontoprint. Changed '(file_exists($pdftk))' to 
//	              '(file_exists($pdftk) && file_exists("../logolib/$db_id/bg.pdf"))' as file was not sent if bg.pfd did not exist.
// 2019.11.05 PHR Varius cleanup in function kontoprint.
// 2019.11.07 PHR function 'find_form_tekst'. Added variables '$forfalden_sum' & 'skyldig_sum' at 'kontokort' Search 20191107
// 2019.11.16 PHR Added $netWeight & $grossWeight
// 2019.11.26 PHR function 'find_form_tekst' Changed error $formular=11 to $formular==11 at line changed at 20191107 
// 2019.12.04 PHR function kontoprint, changed "if ($valutakurs!=100)" to "if ($valutakurs!=100 && $valuta=='DKK')" 
// 2019.12.09 PHR function kontoprint, corrected error in 'primo' calculation when currency != 'DKK'. 
// 2019.12.16 PHR function send_mail. $mailbilag is set to 'on' in general attachment is uploaded in 'formular'  #20191216
// 2019.12.17 PHR function rykerprint. changed $r2['amount'] to $r1['amount']. # 20191217 
// 2019.12.22 PHR function find_form_tekst. Added konto_valuta # 20191222 
// 2020.01.22 PHR function send_mails. Added mail format check #20200122
// 2020.02.07 PHR	Changed above to support multiple mails seperated by ';' or ',' 
// 2020.04.05 PHR Function kontoprint. Due date (forfaldsdato) now read from openposttable if exist there. 
// 2020.05.05 PHR Insert if to avoid mutiple similar lines (saldi_390 reminder 1489 #2)  20200505
// 2020.06.08 PHR Some correction regarding currency / rate in reminders 
// 2021.10.28 PHR ordrer.notes will now linebreak if line length > 5 chars  

if (!function_exists('skriv')) {
function skriv($id,$str, $fed, $italic, $color, $tekst, $tekstinfo, $x, $y, $format, $form_font,$formular,$line) {

print "<!--function skriv start-->";
	global $side;
	global $connection;
	global $psfp;
	global $htmfp;
	global $htminitxt;
	global $mappe;
	global $printfilnavn;
	global $printerid;
	global $returside;
	global $form_id;
	global $subtotal;
#	global $id;
#cho __line__." $id $tekst<br>";
	global $sum;
	global $ref;
	global $transportsum;
	global $formularsprog;
	global $charset;
	global $deb_valuta;
	global $valuta;
	global $deb_valutakurs;
	global $valutakurs;
	global $logo;
	global $logoart;
	global $startfed;
	global $big;
	global $small;
	global $startitalic;
	global $slutfed;
	global $slutbig;
	global $slutsmall;
	global $slutitalic;
	
	$x1=0;
	$Opkt=0;

	$vare_note=$tekst1=NULL;
	if ($slutfed) $startfed=$slutfed=NULL;
	if ($slutbig) $big=$slutbig=NULL;
	if ($slutsmall) $small=$slutsmall=NULL;
	if ($slutitalic) $startitalic=$slutitalic=NULL;
	if (!isset($incr_x)) $incr_x=0;
	if (!isset($incr_y)) $incr_y=0;

	$format=strtoupper($format);
	$tekst=str_replace("(","\\(",$tekst);
	$tekst=str_replace(")","\\)",$tekst);

	if (substr($tekst,0,3)=="<b>") {
		$startfed='on';
		$tekst=str_replace("<b>","",$tekst);
	}
	if (substr($tekst,-4)=="</b>") {
		$slutfed='on';
		$tekst=str_replace("</b>","",$tekst);
	}
	if (substr($tekst,0,3)=="<i>") {
		$startitalic='on';
		$tekst=str_replace("<i>","",$tekst);
	}
	if (substr($tekst,-4)=="</i>") {
		$slutitalic='on';
		$tekst=str_replace("</i>","",$tekst);
	}
	if (substr($tekst,0,7)=="<small>") {
		$small='on';
		$tekst=str_replace("<small>","",$tekst);
#cho "$tekst<br>";	
	}
	if (substr($tekst,-8)=="</small>") {
		$slutsmall='on';
		$tekst=str_replace("</small>","",$tekst);
#cho "$tekst<br>";	
	}
	if (substr($tekst,0,5)=="<big>") {
		$big='on';
		$tekst=str_replace("<big>","",$tekst);
	}
	if (substr($tekst,-6)=="</big>") {
		$slutbig='on';
		$tekst=str_replace("</big>","",$tekst);
	}
	$tmp=strlen($color);
	for ($a=$tmp;$a<9;$a++) $color="0".$color;
	$tmp1=substr($color,-9,3);
	$tmp2=substr($color,-6,3);
	$tmp3=substr($color,-3,3);
	$htmp1=$tmp1*2.55;
	$htmp2=$tmp2*2.55;
	$htmp3=$tmp3*2.55;
	$htmp1=dechex($htmp1);
	$htmp2=dechex($htmp2);
	$htmp3=dechex($htmp3);
	if (strlen($htmp1)<2) $htmp1='0'.$htmp1;
	if (strlen($htmp2)<2) $htmp2='0'.$htmp2;
	if (strlen($htmp3)<2) $htmp3='0'.$htmp3;
	$htmcolor='#'.$htmp1.$htmp2.$htmp3;
	$tmp1=substr($color,-9,3)/100;
	$tmp2=substr($color,-6,3)/100;
	$tmp3=substr($color,-3,3)/100;
	$color="$tmp1 $tmp2 $tmp3 setrgbcolor";
	$x=$x*2.86;
	$y2=$y*2.86;

	if ($format=='H'){$format="$color dup stringwidth pop\nneg 0 rmoveto";}
	elseif ($format=='C'){$format="$color dup stringwidth pop\n2 div neg 0 rmoveto";}
	else {$format="$color";}

	if (($fed=='on'||$startfed=='on')&&($italic!='on')) $form_font=$form_font.'-Bold-ISOLatin9 findfont';
	elseif (($fed!='on'||$startfed=='on')&&($italic=='on')) $form_font=$form_font.'-Italic-ISOLatin9 findfont';
	elseif (($fed=='on'||$startfed=='on')&&($italic=='on')) $form_font=$form_font.'-BoldItalic-ISOLatin9 findfont';
	elseif ($form_font=="Times") $form_font=$form_font.'-Roman-ISOLatin9 findfont';
	else $form_font=$form_font.'-ISOLatin9 findfont';
	if (strstr($tekstinfo, 'ordrelinjer')) {
	list ($tekstinfo,$Opkt)=explode("_",$tekstinfo,2);
#cho "Y $y > $Opkt<br>";
		if ($y <= $Opkt) { # Sideskift
			global $tekst1;
			global $x1;

			$tekst1=$tekst; # Ellers forsvinder den tekst som skulle til at blive skrevet (rabat)
			$x1=$x;
			$preside = $side-1;
			$nextside = $side+1;
			$dk_transportsum=dkdecimal($transportsum,2);
			$query = db_select("select * from formularer where formular = $formular and side = '!S' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				if ($row['color']!='0'){
					$tmp=strlen($color);
					for ($a=$tmp;$a<9;$a++) $color="0".$color;
					$tmp1=substr($color,-9,3)/100;
					$tmp2=substr($color,-6,3)/100;
					$tmp3=substr($color,-3,3)/100;
					$color="$tmp1 $tmp2 $tmp3 setrgbcolor";
				}
				else {$color="0 setgray";}
				if ($row['justering']=='H'){$format="$color dup stringwidth pop\nneg 0 rmoveto";}
				elseif ($row['justering']=='C'){$format="$color dup stringwidth pop\n2 div neg 0 rmoveto";}
				else {$format="$color";}

				$z=-1;
				$n=0;
				$streng=array();
				for ($x=0; $x<strlen($row['beskrivelse']); $x++){
					if ((substr($row['beskrivelse'],$x,1)=="$")||($z==$n)){
						if ($z!=$n) $n++;
						$z=$n;
						if (substr($row['beskrivelse'],$x,1)!=" "){
							(isset($streng[$n]))?$streng[$n].=substr($row['beskrivelse'],$x,1):$streng[$n]=substr($row['beskrivelse'],$x,1);
						}	else {
							$n++;
							$streng[$n]=" ";
						}
					}
					else { 
						(isset($streng[$n]))?$streng[$n].=substr($row['beskrivelse'],$x,1):$streng[$n]=substr($row['beskrivelse'],$x,1);
					}
				}
				$streng_antal=$n;
				$ny_streng="";
				for ($i=0; $i<=$streng_antal; $i++){
					if (isset ($streng[$i]) && substr($streng[$i],0,1)=="$") {
						$streng[$i]=substr($streng[$i],1);
						list($tabel,$variabel)=explode("_",$streng[$i],2);
						if ($tabel=="ordre") {
							if ($variabel=="rykkerdate") $variabel="fakturadate";
							$q2 = db_select("select $variabel from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
						} elseif ($tabel=="eget" || $tabel=="egen") {
							$q2 = db_select("select $variabel from adresser where art='S'",__FILE__ . " linje " . __LINE__);
						} elseif (($tabel=="adresser") || ($tabel=="adresser")) {
#cho "select $variabel from adresser where id='$id'<br>";
							$q2 = db_select("select $variabel from adresser where id='$id'",__FILE__ . " linje " . __LINE__);
						} elseif ($tabel=="ansat" && $ref) {
							$r2 = db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__));
							$q2 = db_select("select $variabel from ansatte where konto_id=$r2[id] and navn = '$ref'",__FILE__ . " linje " . __LINE__);
						}
						if ($tabel!="formular") {
							$r2 = db_fetch_array($q2);
							$streng[$i]=$r2[$variabel];
						}
						else {
							if ($variabel=='ialt')				$streng[$i]=$ialt;
							if ($variabel=='moms') 				$streng[$i]=$moms;
							if ($variabel=='momsgrundlag')$streng[$i]=$momsgrundlag;
							if ($variabel=='preside') 		$streng[$i]=$preside;
							if ($variabel=='nextside') 		$streng[$i]=$nextside;
							if ($variabel=='side') 				$streng[$i]=$side;
							if ($variabel=='sum') 				$streng[$i]=$sum;
							if ($variabel=='transportsum')$streng[$i]=$dk_transportsum;
							if ($variabel=='forfaldsdato')$streng[$i]=$forfaldsdato;
						}
					}
					if (!isset($streng[$i])) $streng[$i]=NULL; 
					(isset($ny_streng))?$ny_streng=$ny_streng.$streng[$i]:$ny_streng=$streng[$i];
					#if ($charset=="UTF-8") $ny_streng=utf8_iso8859($ny_streng);
				}
				if ($ny_streng) {
					if ($small || $big){
						if ($small)$ny_str=$str-2;
						if ($big) $ny_str=$str+2;
					} else $ny_str=$str;
					# udskrivning af formular variabler
					if ($row['xa']) {
						fwrite($psfp,"/$form_font\n$row[str] scalefont\nsetfont\nnewpath\n".$row['xa']*2.86." ".$row['ya']*2.86." moveto (".utf8_iso8859($ny_streng).") $format show\n");
					#	fwrite($htmfp,"<div style=\"position:absolute;top:".$row['xa']."mm;left:".$row['xb']."mm;\">".__line__."$ny_streng</div>\n");
						$a=$row['xa'];
						$b=297-$row['ya'];
						$c=$ny_str*1.2;
						if (strpos($format,'neg')) {
							$a=210-$a;
							fwrite($htmfp,"<div style=\"position:absolute;right:".$a."mm;top:".$b."mm\"><span style=\"color:$htmcolor;font-family:Arial, Helvetica, sans-serif;font-size:".$c."px;\">".$ny_streng."</span></div>\n");
						} else fwrite($htmfp,"<div style=\"position:absolute;left:".$a."mm;top:".$b."mm\"><span style=\"color:$htmcolor;font-family:Arial, Helvetica, sans-serif;font-size:".$c."px;\">".$ny_streng."</span></div>\n");
#cho "$ny_streng<br>"; 
					}
				}
			}
			isset($ya)?$y=$ya:$y=NULL;
			$y=bundtekst($id);
			$y2=$y*2.86;

			$fsize=filesize("../includes/faktinit.htm");
			$initfil=fopen("../includes/faktinit.htm","r");
			$htm_ini=fread($initfil,$fsize);
			fclose($initfil);

			$tmp=$mappe;
			$tmp.="/";
			$tmp.=$printfilnavn;
			$tmp.="_";
			$tmp.=$side;
			$tmp.=".htm";
			$htmfp=fopen($tmp,"w");
			fwrite($htmfp,$htm_ini);
			if ($logoart=='PS') fwrite($psfp, $logo);
#cho __line__." $id<br>";			
			formulartekst($id,$formular,$formularsprog);
		}
	}
	if ($small || $big){
		if ($small)$ny_str=$str-2;
		if ($big) $ny_str=$str+2;
	} else $ny_str=$str;
	#if ($charset=="UTF-8") $tekst=utf8_iso8859($tekst);
	# herunder y2/2.86>Opkt tilfoejet 20110127 grundet 1 ordrelinje på ny side ogsaa skrives nder ordrelinjefelt v. ombrudt kommentar saldi_2 ordre id 3310
	if ($fed=='on'||$startfed=='on') {
		$f1="<b>";$f2="</b>";
	} else {
		$f1=NULL;$f2=NULL;
	}
	if ($italic=='on'||$startitalic=='on') {
		$i1="<i>";$i2="</i>";
	} else {
		$i1=NULL;$i2=NULL;
	}
	if ($x && $tekst && $y2/2.86>$Opkt) {
#cho "$tekst | $line<br>";
if ($tekst=='891,42') exit;
		if ($x!='22') fwrite($psfp,"/$form_font\n$str scalefont\nsetfont\nnewpath\n$x $y2 moveto (".utf8_iso8859($tekst).") $format show\n");
		$a=$x/2.86;
		$b=297-$y2/2.86;
		$c=$ny_str*1.2;
		if (strpos($format,'neg')) {
			$a=210-$a;
				fwrite($htmfp,"<div style=\"position:absolute;right:".$a."mm;top:".$b."mm\"><span style=\"color:$htmcolor;font-family:Arial, Helvetica, sans-serif;font-size:".$c."px;\">$f1$i1".$tekst."$f2$i2</span></div>\n");
		} else {
			fwrite($htmfp,"<div style=\"position:absolute;left:".$a."mm;top:".$b."mm\"><span style=\"color:$htmcolor;font-family:Arial, Helvetica, sans-serif;font-size:".$c."px;\">$f1$i1".$tekst."$f2$i2</span></div>\n");
		}
	}
#if ($tekst1) exit;
	return $y;
print "<!--function skriv slut-->";
}} #endfunc skriv();

if (!function_exists('ombryd')) {
function ombryd($id,$str, $fed, $italic, $color, $tekst, $tekstinfo, $x, $y, $format, $form_font, $laengde,$formular,$linespace) {
print "<!--function ombryd start-->";
	global $vare_note;
	
	$lokation=NULL;
	if (strpos($tekst,chr(9))) {
		list($tekst,$lokation,$vare_note)=explode(chr(9),$tekst);
#cho $tekst;
	}
	$tekst=wordwrap($tekst, $laengde, "\n", true);
	$nytekst="";
	if (strstr($tekstinfo, 'ordrelinjer')) {
		list ($tmp,$Opkt)=explode("_",$tekstinfo);
	}
	for ($z=1; $z<=strlen($tekst); $z++){
		 $tegn = substr($tekst,$z-1,1);
		 $nytekst = $nytekst . $tegn;
		 if (strstr($tegn,"\n")){
				$nytekst=trim($nytekst);
				if (strlen($nytekst)>=1){
					$tmp=$y;
					if ($y>=$Opkt) {
						$y=skriv($id,$str,$fed,$italic,$color,$nytekst,$tekstinfo,$x,$y,$format,$form_font,$formular,__LINE__);
					}
					if  ($y!=$tmp) { #aendret 2011.01.27 grundet manglende linje efter sideskift - saldi_2 ordre id 3310
#					if ($y<$Opkt) {
						# sikring af ombrudt tekst v. sideskift.
						$y=skriv($id,$str,$fed,$italic,$color,$nytekst,$tekstinfo,$x,$y,$format,$form_font,$formular,__LINE__);
					} 
					$y=$y-$linespace;
				}
				$nytekst="";
		 }
	}
#cho "nytext $nytekst<br>";
	$nytekst=trim($nytekst);
	if (strlen($nytekst)>=1){
		$y=skriv($id,$str,$fed,$italic,$color,$nytekst,$tekstinfo,$x,$y,$format,$form_font,$formular,__LINE__);
	}
	if ($lokation) {
		$y=$y-$linespace;
			$y=skriv($id,$str,$fed,$italic,$color,$lokation,$tekstinfo,$x,$y,$format,$form_font,$formular,__LINE__);
	}
	if ($vare_note) {
		$y=$y-$linespace;
			$y=skriv($id,$str,$fed,$italic,$color,$vare_note,$tekstinfo,$x,$y,$format,$form_font,$formular,__LINE__);
	}
	return $y;
print "<!--function ombryd slut-->";
}}

if (!function_exists('find_form_tekst')) {
function find_form_tekst($id, $sidetekst, $formular,$y_pos,$linjeafstand,$pos_part) {
print "<!--function find_form_tekst start-->";
	global $bruger_id,$brugsamletpris;
	global $deb_valuta,$deb_valutakurs;
	global $formularsprog;
	global $gebyr;
	global $htmfp;
	global $ialt;
	global $lev_nr;
#	global $linjeafstand;
	global $moms,$momsgrundlag;
	global $psfp;
	global $ref,$returside;
	global $side,$sum;
	global $transportsum;
	global $valuta,$valutakurs,$vis_saet;
	global $ya;


	$dk_transportsum=dkdecimal($transportsum,2);
	if (!$deb_valutakurs) $deb_valutakurs=100; #20140628

	$id*=1;
	$pre_xa="";
	$preside=$side-1;
	$udskriv=1;

#	if ($pospart==2) $y_pos = $y_pos - $linjeafstand;
	if ($pos_part) $pos_part="and ya = '".$pos_part."'";
	else $pos_part="";

	if ($side=="1") $qtxt = "select * from formularer where formular = $formular and (side = 'A' or side = '1') and lower(sprog)='$formularsprog'";
	if ($sidetekst=='S') $qtxt = "select * from formularer where formular = $formular and side ='S' and lower(sprog)='$formularsprog'";
	elseif ($side!="1") $qtxt = "select * from formularer where formular = $formular and (side = 'A' or side = '!1') and lower(sprog)='$formularsprog' $pos_part order by xb, xa";
	$query = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
#cho "B-----> ".$row['beskrivelse']."<br>";
		$z=-1;
		$y=0;
		$streng=array();
		$streng[$y]=NULL;
		$if=array();
		for ($x=0; $x<strlen($row['beskrivelse']); $x++){ # strengen loebes igennem
			if (((substr($row['beskrivelse'],$x,1)=="$")||($z==$y))&&(substr($row['beskrivelse'],0,4)!="+73<")){ #hvis strengen er en variabel eller en betalingskode
			if (!isset($if[$y]))$if[$y]=0;
			if ($if[$y]!='1' && $z!=$y) $y++; #Hvis enden af strengen ikke er naaet
				$z=$y;
#|| ($streng[$y]=="betalingsid" && substr($row['beskrivelse'],$x,1)=="(")
					if ((substr($row['beskrivelse'],$x,1)!=" " && substr($row['beskrivelse'],$x,1)!=";")) { # alm variabel opbygges
					if (!isset($if[$y]))$if[$y]=NULL;
#cho __line__." $if[$y]<br>";
#cho __line__." ".$row['beskrivelse']."<br>";
#cho __line__." ".substr($row['beskrivelse'],$x,1)."<br>";
						if (($if[$y]=='1')&&(substr($row['beskrivelse'],$x,1)==")")) { # opbygning af "if-variabel" slut  
#						$streng[$y]=substr($streng[$y],0,strlen($streng[$y])-1);
							list($if_tabel,$if_variabel) = explode("_",$streng[$y],2); #07.10.2007 --> 
							if (substr($if_tabel,1)=="ordre") {
								$qtxt="select $if_variabel from ordrer where id=$id";
								$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
								$tmp=$r[$if_variabel];
								if (!$tmp) { #Variablen er ikke fundet så der skal ikke udskrives noget.
									$udskriv=0;
									$row['beskrivelse']=""; 
									$x=0;
									$y=0;
								} # <-- 07.10.2007
							}
							$if_nr=$y; 
							$streng[$y]=''; 
#							$if[$y+1]='!'; # Kun 1 if accepteres. - 20121109
							$if[$y]='0';  
							$y++;
						}	else { # if-variabel opbygning fortsætter
							if (!isset($streng[$y]))$streng[$y]=NULL;
							$streng[$y]=$streng[$y].substr($row['beskrivelse'],$x,1);
						}
					} else { #alm variabel opbygning slut 
					$y++;
					$streng[$y]=NULL;
					if (substr($row['beskrivelse'],$x,1)=="(") $streng[$y]=";";
					if (substr($row['beskrivelse'],$x,1)!=";") $streng[$y]=" ";
				}
			} else { #Hivs strengen ikke er en variabel eller en batalingskode
				if (!isset($streng[$y]))$streng[$y]=NULL;
				$streng[$y].=substr($row['beskrivelse'],$x,1);
				if ($streng[$y]=='if(') { #Så skal der tjekkes om variablen mellem i parantesen har en værdi 
					$if[$y]='1'; # når $if[$y] skal variablen opbygges
					$streng[$y]=''; # 'if(' er ikke en del af variablen. 
				} 
				elseif ($streng[$y]=='if(!') {
					$if[$y]='2';
					$streng[$y]='';
				}# endif
			} # end if-else
		} # endfor ($x=0; $x<strlen($row['beskrivelse'])
#cho "Streng $streng[$y]<br>";
#cho $row['beskrivelse']."<br>";
		$streng_antal=$y;
		$ny_streng=$q2=NULL;
		for ($x=0; $x<=$streng_antal; $x++){
		$if[$x]=NULL;
#cho "-------------------------- Streng $streng[$x] -----------------------------------<br>";
			if (isset($streng[$x]) && substr($streng[$x],0,1)=="$") {
				$streng[$x]=substr($streng[$x],1);
				list($tabel, $variabel)=explode("_",$streng[$x],2);
				if (($formular==3)&&($tabel=="ordre")&&(($variabel=="lev_navn")||($variabel=="lev_addr1")||($variabel=="lev_addr2")||($variabel=="lev_postnr")||($variabel=="lev_bynavn")||($variabel=="lev_kontakt"))) {
					$variabel=tjek_lev_addr($variabel, $id);
				}
				if ($tabel=="ordre") {
					if ($variabel=="rykkerdate") $variabel="fakturadate";
					$qtxt = "select $variabel from ordrer where id=$id";
					$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				} elseif ($tabel=="eget" || $tabel=="egen") {
					$q2 = db_select("select $variabel from adresser where art='S'",__FILE__ . " linje " . __LINE__);
				} elseif ($tabel=="adresser" || $tabel=="konto") {
					if ($variabel=='valuta') {
						$r2 = db_fetch_array(db_select("select gruppe from adresser where id='$id'",__FILE__ . " linje " . __LINE__));
						$q2 = db_select("select box3 as valuta from grupper where art='DG' and kodenr='$r2[gruppe]'",__FILE__ . " linje " . __LINE__);
					} else {
						$q2 = db_select("select $variabel from adresser where id='$id'",__FILE__ . " linje " . __LINE__);
					}
				} elseif ($tabel=="kunde") {
					$q2 = db_select("select $variabel from adresser where art='D' and id=$id",__FILE__ . " linje " . __LINE__);
				} elseif ($tabel=="levering") {
					$q2 = db_select("select $variabel from batch_salg where ordre_id=$id and lev_nr=$lev_nr",__FILE__ . " linje " . __LINE__);
				}	elseif ($tabel=="ansat") {
					$r2 = db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__));
					$q2 = db_select("select $variabel from ansatte where konto_id=$r2[id] and navn='$ref'",__FILE__ . " linje " . __LINE__);
				}	elseif ($formular==11 && ($tabel=="forfalden" || $tabel=="skyldig")) { #20191107 finder forfalden_sum / skyldig_sum på kontokort
					$opensum=0;
					$dd=date("Y-m-d");
					$qtxt = "select * from openpost where konto_id='$id' and udlignet='0'";
					$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r2 = db_fetch_array($q2)) {
						if (!$r2['valuta']) $r2['valuta']='DKK';
						if (!$r2['valutakurs']) $r2['valutakurs']=100;
						$valuta=$r2['valuta'];
						$valutakurs=$r2['valutakurs']*1;
						$dkkamount=$r2['amount']*$valutakurs/100;
						if ($deb_valuta!="DKK" && $deb_valuta!=$valuta) $amount=$dkkamount*100/$deb_valutakurs;
						elseif ($deb_valuta==$valuta) $amount=$r2['amount'];
						else $amount=$dkkamount;
						if ($deb_valuta=='DKK') $amount=$dkkamount;
						if ($tabel=='skyldig' || $dd >= $r2['forfaldsdate']) $opensum+=afrund($amount,2);
					}
#cho __line__;				
					$q2=NULL;
					$sum=dkdecimal($opensum,2);
				}	elseif ($tabel=="forfalden" || $tabel=="rykker") {# finder forfalden_sum på rykker - $tabel=="rykker" indsat 14.04.08
 					$qtxt = "select * from varer where id IN (select xb from formularer where beskrivelse='GEBYR' and formular='$formular')";
					$r2 = db_fetch_array(db_select ($qtxt,__FILE__ . " linje " . __LINE__));
					$gebyr=$r2['salgspris']*1;
					if ($deb_valutakurs!='100') $gebyr*=100/$deb_valutakurs; #20140628
					$r2 = db_fetch_array(db_select ("select yb from formularer where beskrivelse='GEBYR' and formular='$formular'",__FILE__ . " linje " . __LINE__));
					$rentevare=$r2['yb']*1;	
					$rente=0; #20141211
					$q2=db_select("select * from ordrelinjer where ordre_id = '$id' and vare_id = '$rentevare'",__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) $rente+=$r2['pris'];
	        $forfalden=0;
					$q2 = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
					while ($r2 = db_fetch_array($q2)) {
						$amount=0;    #Tilføjet 20110822 grundet 2x forfaldent
 						$dkkamount=0; #Tilføjet 20110822 grundet 2x forfaldent
						if ($r2['enhed']) {
							$qtxt="select amount,valuta,valutakurs from openpost where id='$r2[enhed]'";
							$r3 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
							if (!$r3['valuta']) $r3['valuta']='DKK';
							if (!$r3['valutakurs']) $r3['valutakurs']=100;
							$valuta=$r3['valuta'];
							$valutakurs=$r3['valutakurs']*1;
							$dkkamount=$r3['amount']*$valutakurs/100;
							if ($deb_valuta!="DKK" && $deb_valuta!=$valuta) $amount=$dkkamount*100/$deb_valutakurs;
							elseif ($deb_valuta==$valuta) $amount=$r3['amount'];
							else $amount=$dkkamount;
						}
						if ($deb_valuta=='DKK') $amount=$dkkamount;
						$forfalden+=afrund($amount,2); #20140628 finder forfalden_sum;
					}
					$q2=NULL;
					$sum=dkdecimal($forfalden,2);
					$gebyr=dkdecimal($gebyr,2);
					$rente=dkdecimal($rente,2);
				}
				if ($q2 && $tabel!="formular" && $tabel!="forfalden" && $tabel!="skyldig" && $tabel!="rykker") {
					$r2 = db_fetch_array($q2);
					if (strstr($variabel, 'date')) {
						$streng[$x]=dkdato($r2[$variabel]);
					} elseif ($variabel=='momssats') {
						list($streng[$x],$tmp)=explode(",",dkdecimal($r2[$variabel],2));
						$tmp=$tmp*1;
						if ($tmp) $streng[$x].=",".$tmp;
					} elseif ($vis_saet && substr($variabel,0,5)=='felt_') { #20150302
						if ($variabel=="felt_2") {
							$streng[$x]=dkdecimal($r2[$variabel],2);
						} elseif ($variabel=="felt_3" || $variabel=="felt_4") {
							$r3=db_fetch_array(db_select("select felt_4 from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
							if ($r3['felt_4']*1==0) {
								$streng[$x]='';
							} elseif ($variabel=="felt_3") $streng[$x]=$r2[$variabel];
							elseif ($variabel=="felt_4") $streng[$x]=dkdecimal($r2[$variabel],2);
						} else $streng[$x]=$r2[$variabel];
					}	elseif ($variabel=="afd" && $r2[$variabel]) {
						$r3=db_fetch_array(db_select("select beskrivelse from grupper where art = 'AFD' and kodenr = '$r2[$variabel]'",__FILE__ . " linje " . __LINE__));
						($r3['beskrivelse'])?$streng[$x]=$r3['beskrivelse']:$streng[$x]=$r2[$variabel];
					} else $streng[$x]=$r2[$variabel];
				} else {
					if ($variabel=='date') $streng[$x]=date("d-m-Y");
					if ($variabel=='ialt') $streng[$x]=$ialt;
					if ($variabel=='ialt_heltal') list($streng[$x],$tmp)=explode(",",$ialt);
					if ($variabel=='ialt_decimal') list($tmp,$streng[$x])=explode(",",$ialt);
					if ($variabel=='ialt') $streng[$x]=$ialt;
					if ($variabel=='moms') $streng[$x]=$moms;
					if ($variabel=='momsgrundlag') $streng[$x]=$momsgrundlag;
					if ($variabel=='preside') $streng[$x]=$preside;
					if ($variabel=='nextside') $streng[$x]=$nextside;
					if ($variabel=='side') $streng[$x]=$side;
					if ($variabel=='sum') $streng[$x]=$sum;
					if ($variabel=='sum_heltal') list($streng[$x],$tmp)=explode(",",$sum);
					if ($variabel=='sum_decimal') list($tmp,$streng[$x])=explode(",",$sum);
					if ($variabel=='transportsum') $streng[$x]=$dk_transportsum;
					if ($variabel=='forfaldsdato') $streng[$x]=find_forfaldsdato($id);
					if ($variabel=='lev_nr') $streng[$x]=$lev_nr;
					if ($variabel=='gebyr') $streng[$x]=$gebyr;
					if ($variabel=='rente') $streng[$x]=$rente;
					if ($variabel=='kontosaldo') $streng[$x]=dkdecimal(find_saldo($id),2);
					if (strstr($variabel,'betalingsid')) $streng[$x]=modulus_10($id);
					if ($variabel=='grossWeight') $streng[$x]=dkdecimal(findWeight($id,'grossWeight',$lev_nr),2);
					if ($variabel=='netWeight') $streng[$x]=dkdecimal(findWeight($id,'netWeight',$lev_nr),2);
				}
			}
			if ($if[$x]=="!" && $if[$x]!="0") {
				$if[$x+1]==$streng[$x];
				$streng[$x]='';
			}
			elseif (($if[$x])&&($streng[$x])) {
				$streng[$x]=$if[$x]." ".$streng[$x];
			}
		if (isset($streng[$x])) $ny_streng.=$streng[$x];
		} #endfor ($x=0; $x<=$streng_antal; $x++)
#cho 	"$ny_streng && $udskriv<br>";	
		if ($ny_streng && $udskriv) {
			if ($y_pos) {
				if (!$pre_xa || $pre_xa >= $row['xa']) {
					$y_pos=$y_pos-$linjeafstand;
					$pre_xa=$row['xa'];
				}
				$row['ya']=$y_pos;
			}
			if ($variabel == 'notes') { #20211028
				$row['ya'] = ombryd($id,$row['str'],$row['fed'], $row['kursiv'], $row['color'], $ny_streng, 'tekster', $row['xa'], $row['ya'], $row['justering'], $row['font'],'75',$formular,'4');
			} else {
				skriv($id,"$row[str]","$row[fed]","$row[kursiv]","$row[color]","$ny_streng",'header',"$row[xa]","$row[ya]","$row[justering]","$row[font]","$formular",__line__ );
			}
		} # endif($ny_streng&&$udskriv)
		$udskriv=1;
	} # endwhile
	$y_pos = $y_pos - $linjeafstand;
	return($y_pos);
print "<!--function find_form_tekst slut-->";
}}

if (!function_exists('find_forfaldsdato')) {
function find_forfaldsdato($id) {
print "<!--function find_forfaldsdato start-->";
	$query = db_select("select fakturadate, betalingsbet, betalingsdage from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$fakturadate=$row['fakturadate'];
	$betalingsbet=$row['betalingsbet'];
	$betalingsdage=$row['betalingsdage'];

	list($faktaar, $faktmd, $faktdag) = explode("-", $fakturadate);
	$forfaldsaar=$faktaar;
	$forfaldsmd=$faktmd;
	$forfaldsdag=$faktdag;
	$slutdag=31;

	if (($fakturadate)&&($betalingsbet=="Netto"||$betalingsbet=="Lb. md.")) {
		while (!checkdate($forfaldsmd, $slutdag, $forfaldsaar)) {
			$slutdag--;
			if ($slutdag<27) break 1;
		}
		if ($betalingsbet!="Netto"){$forfaldsdag=$slutdag;} # Saa maa det vaere lb. md
		$forfaldsdag=$forfaldsdag+$betalingsdage;
		while ($forfaldsdag>$slutdag) {
			$forfaldsmd++;
			if ($forfaldsmd>12) {
				$forfaldsaar++;
				$forfaldsmd=1;
			}
			$forfaldsdag=$forfaldsdag-$slutdag;
			$slutdag=31;
			while (!checkdate($forfaldsmd, $slutdag, $forfaldsaar)) {
				$slutdag--;
				if ($slutdag<27) break 1;
			}
		}
	}
	$forfaldsdato=dkdato($forfaldsaar."-".$forfaldsmd."-".$forfaldsdag);
	return $forfaldsdato; 
print "<!--function find_forfaldsdato slut-->";
}}

if (!function_exists('tjek_lev_addr')) {
function tjek_lev_addr($variabel, $id) {
print "<!--function tjek_lev_addr start-->";
	 if ($variabel=="lev_navn") $tmp="firmanavn";
	 else $tmp=substr($variabel, 4);
	 $query = db_select("select $tmp from ordrer where id=$id and lev_navn!='' and lev_addr1!='' and lev_postnr!='' and lev_bynavn!=''",__FILE__ . " linje " . __LINE__);
	 if ($row=db_fetch_array($query)) {return $variabel;}
	 else {return $tmp;}
print "<!--function tjek_lev_addr slut-->";
}}

if (!function_exists('modulus_10')) {
function modulus_10($id) {
print "<!--function modulus_10 start-->";
# Genererer betalingsid for kortart 71.
# Kortart 71 bestaar af 15 cifrer, hvor det sidste er kontrolciffer.

	$faktlen=14;
	$kontolen=0;
	if ($r=db_fetch_array(db_select("select xa,ya from formularer where formular=4 and art=0",__FILE__ . " linje " . __LINE__))) {
		$faktlen=$r['xa']*1;
		$kontolen=$r['ya']*1;
	} elseif ($r=db_fetch_array(db_select("select beskrivelse from formularer where formular=4 and beskrivelse like '%betalingsid(%'",__FILE__ . " linje " . __LINE__))) {
		$streng=$r['beskrivelse'];
		$start=strpos($streng,'betalingsid(')+12; # 1 karakter efter startparantesen
		$slut=strpos($streng,')');
		$len=$slut-$start;
		$streng=substr($streng,$start,$len);
		list($kontolen,$faktlen)=explode(",",$streng);
	}

	$r=db_fetch_array(db_select("select fakturanr, kontonr from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$fakturanr=trim($r['fakturanr']);
	$kontonr=trim($r['kontonr']);
	if (strpos($fakturanr,'-')) $fakturanr='0';
	if (strlen($fakturanr)>$faktlen) $fakturanr=substr($fakturanr,-$kontolen);
	if (strlen($kontonr)>$kontolen) $kontonr=substr($kontonr,0,$kontolen);
	$y=0;

	
	
	while (strlen($fakturanr)<$faktlen) $fakturanr='0'.$fakturanr;
	while (strlen($kontonr)<$kontolen) $kontonr='0'.$kontonr;

	$betalingsid=$kontonr.$fakturanr;

	if (strlen($betalingsid)>14) $betalingsid=substr($betalingsid,-14); #betalingsid reduceres til 14 cifre;
	elseif (strlen($betalingsid)<14) {
		while (strlen($betalingsid)<14) $betalingsid='0'.$betalingsid; #betalingsid reduceres til 14 cifre;
	}

	$kontrol=array();
	for ($x=13;$x>=0;$x--) {
		if ($y==0) {
			$kontrol[$x]=substr($betalingsid,$x,1)*2;
			if ($kontrol[$x]>9) $kontrol[$x]=substr($kontrol[$x],0,1)+substr($kontrol[$x],1,1);
			$y=1;
		}	else {
			$kontrol[$x]=substr($betalingsid,$x,1);
			$y=0;
		}
		$kontrolsum=$kontrolsum+$kontrol[$x];
	}
	$kontrolciffer=10-substr($kontrolsum,-1,1);
	if ($kontrolciffer==10) $kontrolciffer=0;
	$betalingsid=$betalingsid.$kontrolciffer;
#		print "<BODY onLoad=\"JavaScript:alert('betalingsid = $betalingsid');\">";
	return ($betalingsid);
print "<!--function modulus_10 slut-->";
}}

if (!function_exists('formularprint')) {
function formularprint($id,$formular,$lev_nr,$charset,$udskriv_alle_til) {
print "<!--function formularprint start-->";

	($udskriv_alle_til=='inkasso')?$inkasso='on':$inkasso=NULL;

	include("../includes/std_func.php");
	include("../includes/var2str.php");

	global $bruger_id,$brugsamletpris;
	global $charset;
	global $db,$db_id,$dkdato;
	global $exec_path;
	global $form_id,$formularsprog,$psfp;
	global $htminitxt,$htmfp;
	global $gebyr;
	global $ialt;
#	global $id;
	global $lev_nr,$linjeafstand,$logo,$logoart;
	global $mailantal,$mappe,$moms,$momsgrundlag,$momssats;
	global $nextside;
	global $printerid,$printfilnavn;
	global $ref,$returside;
	global $s_id,$side,$sprog_id,$subtotal,$sum;
	global $transportsum;
	global $vis_saet;
	global $y,$ya;

	
	$bgr=$psfp1=$htmfp1=$kommentarprint=$rvnr=$serienr=$skjul_nul_lin=NULL;
	$folgeseddel=$mailantal=$nomailantal=0;
	$side_x=210;
	$side_y=297;

	$side_x=afrund($side_x*2.86,0);
	$side_y=afrund($side_y*2.86,0);

	$preview=NULL;
	$ps_ini="%!\n%%Creator: Saldi\n%%Pages: 1\n%%BoundingBox: 0 0 $side_x $side_y\n%%EndComments\n<< /PageSize [$side_x $side_y] >> setpagedevice\n";
#$ps_ini="%!\n%%Creator: Saldi\n%%Pages: 1\n%%EndComments\n";

	$form=array();

	$q = db_SELECT("select box2,box14 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__);
	$r=db_fetch_array($q);
	$rabatvare_id=$r['box2']*1;
	$brugsamletpris=$r['box14'];
	if ($vis_saet) $brugsamletpris='on';

	if ($id==-1){	# Saa er der flere fakturaer
		$ordre_antal = $_GET['ordre_antal'];
		$ordreliste=$_GET['skriv'];
		$ordre_id = explode(",",$ordreliste);
		if (strpos($formular,",")) {
			list($formular,$folgeseddel)=explode(",",$formular);
		}
	} elseif (!is_numeric($id)) {
		$preview=1;
		$ordre_id[0]=0;
		$ordre_antal=1;
	} else {
		$ordre_id[0]=$id;
		$ordre_antal=1;
	}
	if ($formular==3) $folgeseddel=1;
	if ($formular!=3 && $folgeseddel) {
		for ($q=0; $q<$ordre_antal; $q++) {
			$form[$q]=$formular;
			$r=db_fetch_array(db_select("select lev_addr1,lev_postnr from ordrer where id = $ordre_id[$q]",__FILE__ . " linje " . __LINE__));
			if ($r['lev_addr1'] && $r['lev_postnr']) {
				$form[$q]=3;
				$ordre_antal++;
				for ($z=$ordre_antal; $z>$q; $z--) {
					$ordre_id[$z]=$ordre_id[$z-1];
				}
				$q++;
				$form[$q]=$formular;
			}
		}
	}

	$r = db_fetch_array(db_select("select box6,box12 from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$vis_saet=trim($r['box12']);
	if ($vis_saet) $brugsamletpris='on';

	$r=db_fetch_array(db_select("select * from grupper where art='DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__));
	$incl_moms=$r['box1'];
	if ($folgeseddel) {
		$kommentarprint=$r['box3'];
		$skjul_nul_lin=$r['box8'];
	}
	$fsize=filesize("../includes/faktinit.ps");
	$initfil=fopen("../includes/faktinit.ps","r");
	$ps_ini=$ps_ini.fread($initfil,$fsize);
	fclose($initfil);
	$fsize=filesize("../includes/faktinit.htm");
	$initfil=fopen("../includes/faktinit.htm","r");
	$htm_ini=fread($initfil,$fsize);
	fclose($initfil);

	if ( ! file_exists("../temp/$db")) mkdir("../temp/$db", 0775);

	$mappe="../temp/$db/".abs($bruger_id);
	if (!$inkasso) system("rm -r $mappe");
	mkdir("$mappe", 0775);
	if ($ordre_antal>1) {
		$printfilnavn='udskrift';
		if ($formular<=1) $printfilnavn="tilbud";
		if ($formular==2) $printfilnavn="ordrebek";
		if ($formular==3) $printfilnavn="flg_seddel";
		if ($formular==4) $printfilnavn="faktura";
		if ($formular==9) $printfilnavn="plukliste";
		if ($formular==12) $printfilnavn="forslag";
		if ($formular==13) $printfilnavn="rekvisition";
		if ($formular==14) $printfilnavn="lev_fakt";
#		$psfp1=fopen("$mappe/$printfilnavn.ps","w");
#		$htmfp1=fopen("$mappe/$printfilnavn.htm","w");
	}
	for ($o=0; $o<$ordre_antal; $o++) {
#		$psfp=$psfp1;
#		$htmfp=$htmfp1;
		$ordre_id[$o]*=1;	
		if (isset($form[$o])) $formular=$form[$o];
		$varenr=array();$lev_varenr=array();$leveres=array(); $vare_id=array(); $linje_id=array(); $antal=array(); $tidl_lev=array(); $rest=array();
		$enhed=array(); $rabat=array(); $momssats=array(); $pris=array(); $l_sum=array(); $linjesum=array();
		$sum='';$transportsum=0;
		if ($preview) {
			$ref='Egen ref';
			$ordrenr='Ordrenummer';
			$fakturanr='Fakturanummer';
			$mail_fakt='';
			$email[0]='Kundens email';
			$pbs='';
		} else {
			$qtxt="select afd,status,email,ordrenr,fakturanr,mail_fakt,pbs,art,ref,sprog,udskriv_til,mail_subj,mail_text,dokument,procenttillag ";
			$qtxt.="from ordrer where id = '$ordre_id[$o]'";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($q);
			$status=$row['status'];
			$afd=$row['afd'];
			$art=$row['art'];
			$ref=$row['ref'];
		$ordrenr=$row['ordrenr'];
		if (!$udskriv_alle_til) $udskriv_til=$row['udskriv_til'];
		else $udskriv_til=$udskriv_alle_til;
		if ($udskriv_til=='ingen') { #20170501
			return 'OK';
			exit;
		}
#cho "UT $udskriv_til<br>";
		$fakturanr=$row['fakturanr'];
		($inkasso)?$mail_fakt='on':$mail_fakt=$row['mail_fakt'];
		$email[0]=$row['email'];
		if ($email[0] && $udskriv_alle_til=='email') $mail_fakt='on';
		$pbs=$row['pbs'];
		$mail_subj=$row['mail_subj'];
		$mail_text=$row['mail_text'];
		$mail_bilag=$row['dokument'];
		$procenttillag=$row['procenttillag']*1;
#	$ordresum=$row['sum'];
#	$ordremomssum=$row['moms'];
		$formularsprog=strtolower($row['sprog']);
		if (!$formularsprog) $formularsprog="dansk";
		if (($formular==4)||($formular==5)) {
			if (!$fakturanr) { #20130508
				return("Fakturering afbrudt ($ordre_id[$o] -> $ordrenr -> Fakturanr mangler) ");
			}
			elseif ($row['status']<3) { #20130508
				return("Fakturering afbrudt (Status ikke opdateret)");
			}
			elseif ($row['art']=="DK") $formular=5;
			else $formular=4;
		}
		$y=185;
		$antal_ordrelinjer=25;
		$x=0;
	
		$r=db_fetch_array(db_SELECT("select adresser.gruppe,ordrer.procenttillag from ordrer,adresser where ordrer.id = '$ordre_id[$o]' and adresser.id=ordrer.konto_id",__FILE__ . " linje " . __LINE__));
		$gruppe=$r['gruppe'];
		$procenttillag=$r['procenttillag'];
		$r=db_fetch_array(db_select("select box8 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
		$b2b=$r['box8'];
	}
	if ($afd) {
		$r=db_fetch_array(db_select("select beskrivelse,box1 from grupper where art = 'AFD' and kodenr = '$afd'",__FILE__ . " linje " . __LINE__));
		$afd_navn=$r['beskrivelse'];
		$afd_lager=$r['box1'];
	} else {
		$afd_navn=NULL;
		$afd_lager=NULL;
	}
	
	#$bgr="bg";
	if ($formular==5 && file_exists("../logolib/$db_id/kreditnota_bg.pdf")) $bgr="kreditnota_bg";
	elseif ($formular>=3 && file_exists("../logolib/$db_id/faktura_bg.pdf")) $bgr="faktura_bg";
	elseif ($formular==2 && file_exists("../logolib/$db_id/ordrer_bg.pdf")) $bgr="ordrer_bg";
	elseif ($formular<=1 && file_exists("../logolib/$db_id/tilbud_bg.pdf")) $bgr="tilbud_bg";
	elseif (file_exists("../logolib/$db_id/bg.pdf"))	$bgr="bg";
	#cho "formular: $formular bg: $bgr<br>"; 
	print "<!-- kommentar for at skjule uddata til siden \n";
	if (!file_exists("../logolib/$db_id")) mkdir("../logolib/$db_id"); 
	if (system("which pdftk") && file_exists("../logolib/$db_id/$bgr.pdf")) {
		$logoart='PDF';
	} elseif ($udskriv_til=='PDF-tekst') {
		$logoart='PDF';
	} elseif (file_exists("../logolib/$db_id/$formular.ps")) {
		$logo="../logolib/$db_id/$formular.ps";
		$logoart='PS';
	} elseif (file_exists("../logolib/$db_id/$bgr.ps")) {
		$logo="../logolib/$db_id/$bgr.ps";
		$logoart='PS';
	} else {
		$query = db_select("select * from formularer where formular = '$formular' and art = '1' and beskrivelse = 'LOGO' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {$logo_X=$row['xa']*2.86; 	$logo_Y=$row['ya']*2.86;}
		else {$logo_X=430; $logo_Y=758;}
		if (file_exists("../logolib/logo_$db_id.eps")) $logo="../logolib/logo_$db_id.eps";
			else $logo="../logolib/logo.eps";
		$logoart='EPS';
	}
	print "-->\n";

	if ($logoart != 'PDF') {
		if (file_exists($logo)) {
			$fsize=filesize($logo);
			$logofil=fopen($logo,"r");
			$translate=0;
			$logo="";
			while (!feof($logofil)) {
				$linje=fgets($logofil);
				if ($logoart=='EPS')	{
					if (substr($linje,0,2)!="%!") {
						if (strstr($linje, "translate")&&(!$translate)) {
							$linje="$logo_X $logo_Y translate \n";
							$translate=1;
						}
						$logo=$logo.$linje;
					} 
				} else {
					if (strstr($linje,'showpage')) $linje='';
					if (strstr($linje,'%%PageTrailer')) $linje='';
					if (strstr($linje,'%%Trailer')) $linje='';
					if (strstr($linje,'%%Pages:')) $linje='';
					if (strstr($linje,'%%EOF')) $linje='';
					$logo=$logo.$linje;
				}
			}
			fclose($logofil);
		}
	}
#cho "select * from formularer where formular = '$formular' and art = '3' and lower(sprog)='$formularsprog'<br>";
	$query = db_select("select * from formularer where formular = '$formular' and art = '3' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
	if ($row['beskrivelse']=='generelt') {
			$antal_ordrelinjer=$row['xa'];
			$ya=$row['ya'];
			$linjeafstand=$row['xb'];
	#		$Opkt=$y-($antal_ordrelinjer*$linjeafstand);
		}
		else {
			$x++;
			$variabel[$x]=$row['beskrivelse'];
#cho $variabel[$x]."<br>";
			
			$justering[$x]=$row['justering'];
			$xa[$x]=$row['xa'];
			$str[$x]=$row['str'];
			$laengde[$x]=$row['xb'];
			$color[$x]=$row['color'];
			$fed[$x]=$row['fed'];
			$kursiv[$x]=$row['kursiv'];
			$form_font[$x]=$row['font'];
		}
		$var_antal=$x;
	}
	if ($formular==3 && !$lev_nr) {
		$qtxt="select MAX(lev_nr) as lev_nr from batch_salg where ordre_id = $ordre_id[$o]";
		$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$lev_nr=$r2['lev_nr']*1;
	}
	if ($formular==3 || $formular==9) $udskriv_til='PDF';
	if ($mail_fakt && $formular!=3 && $udskriv_til=='email') {
		$mailantal++;
		if ($formular<=1) $printfilnavn="tilbud".$ordrenr;
		if ($formular==2) $printfilnavn="ordrebek".$ordrenr;
		if ($formular==4) $printfilnavn="fakt".$fakturanr;
		if ($formular==5) $printfilnavn="kn".$fakturanr;
		if ($formular==9) $printfilnavn="plukliste".$ordrenr;
		if ($formular==12) $printfilnavn="forslag".$ordrenr;
		if ($formular==13) $printfilnavn="rekvisition.$ordrenr";
		if ($formular==14) $printfilnavn="lev_fakt.$ordrenr";
		$email[$mailantal]=$email[0];
		$mailsprog[$mailantal]=$formularsprog;
		$form_nr[$mailantal]=$formular;
		$pfliste[$mailantal]=$printfilnavn;
		$pfnavn=$mappe."/".$printfilnavn;
		$psfp=fopen("$pfnavn.ps","w");
#		$psfp=$psfp2;
		$htmfp=fopen("$pfnavn.htm","w");
#		$htmfp=$htmfp2;
	} else {
		$nomailantal++;
#		if ($ordre_antal<=1) { #mere sigeende navn til udskrifter.
			if ($formular<=1) $printfilnavn="tilbud".$ordrenr;
			if ($formular==2) $printfilnavn="ordrebek".$ordrenr;
			if ($formular==3) $printfilnavn="flgs".$ordrenr."_".$lev_nr;
			if ($formular==3 && $fakturanr) $printfilnavn="flgs".$fakturanr."_".$lev_nr;
			if ($formular==4) $printfilnavn="fakt".$fakturanr;
			if ($formular==5) $printfilnavn="kn".$fakturanr;
			if ($formular==9) $printfilnavn="plukliste".$ordrenr;
			if ($formular==12) $printfilnavn="forslag".$ordrenr;
			if ($formular==13) $printfilnavn="rekvisition.$ordrenr";
			if ($formular==14) $printfilnavn="lev_fakt.$ordrenr";
			$pfnavn=$mappe."/".$printfilnavn;
			$psfp=fopen("$pfnavn.ps","w");
			$htmfp=fopen("$pfnavn.htm","w");
#		} else {
##cho __line__." $printfilnavn<br>";
#			fclose ($htmfp);
#			$pfnavn=$mappe."/".$printfilnavn.".".$ordrenr;
#			$htmfp=fopen($pfnavn.".htm","w");
#		}
	}

	$side=1;
	if ($logoart=='PS')	fwrite($psfp,$logo);
	fwrite($psfp,$ps_ini);
/*
	$htminitxt="<html>\n";
	$htminitxt.="<head>\n";
	$htminitxt.="<meta charset=\"UTF-8\"/>\n";
	$htminitxt.="<title>$printfilnavn</title>\n";
	$htminitxt.="<style>\n";
	$htminitxt.="@page {\n";
	$htminitxt.="size: A4;\n";
	$htminitxt.="margin: 0;\n";
	$htminitxt.="padding: 0;\n";
	$htminitxt.="}\n";
	$htminitxt.="body {\n";
	$htminitxt.="height: 297mm;\n";
	$htminitxt.="width: 210mm;\n";
	$htminitxt.="margin-left: auto;\n";
	$htminitxt.="margin-right: auto;\n";
	$htminitxt.="}\n";
	$htminitxt.="</style>\n";
	$htminitxt.="</head>\n";
	$htminitxt.="<body>\n";
*/
	fwrite($htmfp,$htm_ini);
	$rabat[0]=formulartekst($ordre_id[$o],$formular,$formularsprog);
	if ($ordre_id[$o]){
		$id=$ordre_id[$o];
		$x=0;
		$sum=0;
		$moms=0;
		$momssum=0;
		$tmp=0;
		$saetnr=0;
		$posnr=array();
		if ($preview) {
			for ($x=1;$x<=22;$x++){
				$posnr[$x]=$x;
				$varenr[$x]=utf8_iso8859('Varenr').$x;
				$projekt[$x]=utf8_iso8859('Projektnr');
				$beskrivelse[$x]=utf8_iso8859('Eksempel på en linjetekst');
			}
		} else {
		if ($brugsamletpris) {
			$qtxt="select varenr from ordrelinjer where ordre_id = $ordre_id[$o] and vare_id='$rabatvare_id'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['varenr']){
				$rabatvarenr=$r['varenr'];
				$rvnr=1;
			} else $rvnr=0;
		}
#		if ($rvnr) $qtxt="select * from ordrelinjer where ordre_id = '$ordre_id[$o]' order by saet,posnr";
#		else 
		#20150302 
		$qtxt="update ordrelinjer set samlevare='' where samlevare is NULL"; #20180302
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		if ($brugsamletpris && $formular==3) {
			$qtxt="select * from ordrelinjer where ordre_id = '$ordre_id[$o]' and samlevare != 'on' ";
			if ($rabatvarenr) $qtxt.="and varenr != '$rabatvarenr' ";
			$qtxt.="order by posnr";
		} else $qtxt="select * from ordrelinjer where ordre_id = '$ordre_id[$o]' order by posnr,id";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			$x=0;
// 20190115 herover: tilføjet ,id til 'order by' -- herunder: tilføjet  || $row['folgevare']
// grundet manglende varenr 9494600512 på fakt 4193 i saldi_401
			while($row = db_fetch_array($q)){
			if ($row['posnr']>0 && (!$row['samlevare'] || !is_numeric($row['samlevare'])) && (!in_array($row['posnr'],$posnr) || $row['folgevare'])) {
					$x++;
					$posnr[$x]=trim($row['posnr']);
					$varenr[$x]=trim($row['varenr']);
					$lev_varenr[$x]=trim($row['lev_varenr']);
					$projekt[$x]=($row['projekt']);
					$beskrivelse[$x]=trim($row['beskrivelse']);
					$enhed[$x]=trim($row['enhed']);
					$linje_id[$x]=$row['id'];
					$linjesum[$x]=0;
					$pris[$x]=$procent[$x]=$rabat[$x]=$saet[$x]=$samlevare[$x]=$lager[$x]=$varemomssats[$x]=NULL;
					if ($varenr[$x]){
						$vare_id[$x]=$row['vare_id'];
						$antal[$x]=$row['antal']*1;
						$leveres[$x]=$row['leveres']*1;
						$dkantal[$x]=str_replace(".",",",$antal[$x]);
						$momsfri[$x]=$row['momsfri'];
						$omvbet[$x]=$row['omvbet'];
						$saet[$x]=$row['saet'];
						$samlevare[$x]=$row['samlevare'];
						$lager[$x]=$row['lager']*1;
						if (!$lager[$x] && $afd_lager) $lager[$x] = $afd_lager;
						$varemomssats[$x]=$row['momssats']*1;
						if (!$momsfri[$x] && !$varemomssats[$x]) $varemomssats[$x]=$momssats;
						if ($varemomssats[$x] > $momssats) $varemomssats[$x]=$momssats;
							
						(isset($dkantal[$x]) && strpos($dkantal[$x],","))?list($a,$b)=explode(",",$dkantal[$x]):$b=0;
#						list($a,$b)=explode(",",$dkantal[$x]);
						if ($b) {
							$dkantal[$x]="$a,$b";
						} #else $dkantal[$x]=$a;
						if ($formular==5){
							$antal[$x]=$antal[$x]*-1;
							$dkantal[$x]=str_replace("-","",$dkantal[$x]);
						}
						if ($formular==3 || $formular==9){
							for ($z=0;$z<=count($variabel);$z++) {
								if (isset($variabel[$z]) && $variabel[$z]=='lokation') {
									$qtxt="select lok1 as location from lagerstatus where vare_id = '$vare_id[$x]' and lager = '$lager[$x]'";
									$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
									if ($r2['location']) {
											$beskrivelse[$x].=chr(9)."Lok: ".$r2['location'];
									}
								}
								if (isset($variabel[$z]) && $variabel[$z]=='vare_note') {
									$qtxt="select notes from varer where id='$vare_id[$x]'";
									$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
									if ($r2['notes']) {	
										$beskrivelse[$x].=chr(9)."(".$r2['notes'].")";
									}
								}
							}
						
						$lev_nr*=1;
						$lev_antal[$x]=0;
						$q2 = db_select("select antal from batch_salg where linje_id = $linje_id[$x] and lev_nr = $lev_nr",__FILE__ . " linje " . __LINE__);
						while ($r2 = db_fetch_array($q2)){$lev_antal[$x]=$lev_antal[$x]+$r2['antal'];}
						$tidl_lev[$x]=0;
						$q2 = db_select("select antal from batch_salg where linje_id = $linje_id[$x] and lev_nr < $lev_nr",__FILE__ . " linje " . __LINE__);
						while ($r2 = db_fetch_array($q2)){$tidl_lev[$x]=$tidl_lev[$x]+$r2['antal'];}
						$rest[$x]=$antal[$x]-$lev_antal[$x]-$tidl_lev[$x];
					}
					$enhed[$x]=$row['enhed'];
					$pris[$x]=$row['pris'];
#							if ($rvnr) {
#								if ($varenr[$x]=='R') list($pris[$x])=explode("|",$row['lev_varenr']);
#								$rabat[$x]=0;
#							} else {
					$rabat[$x]=$row['rabat'];
#							}
					$procent[$x]=($row['procent']);
					($row['rabat']!=0)?$m_rabat[$x]=0:$m_rabat[$x]=$row['m_rabat']*1;
							$rabatart[$x]=$row['rabatart'];
							if ($rabatart[$x]=="amount") {
								$l_sum[$x]=afrund(($pris[$x]-$rabat[$x])*$antal[$x],2);
								$linjesum[$x]=($pris[$x]-$rabat[$x])*$antal[$x];
								$linjemoms[$x]=$varemomssats[$x]/100 * (($pris[$x]-$rabat[$x])*$antal[$x]);
							} else {
								$l_sum[$x]=afrund($row['pris']*$antal[$x]-($pris[$x]*$antal[$x]*$rabat[$x]/100),2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
								$linjesum[$x]=$row['pris']*$antal[$x]-($pris[$x]*$antal[$x]*$rabat[$x]/100);
								$linjemoms[$x]=$varemomssats[$x]/100 * ($pris[$x]*$antal[$x]-($row['pris']*$antal[$x]*$rabat[$x]/100));
							}
							if ($momsfri[$x]=='on' || $omvbet[$x]) $linjemoms[$x]=0;
							if ($procent[$x] || $procent[$x]=='0'){
								$l_sum[$x]=afrund($l_sum[$x]-($l_sum[$x]*(100-$procent[$x])/100),2);
								$linjesum[$x]=afrund($linjesum[$x]-($linjesum[$x]*(100-$procent[$x])/100),2);
								$linjemoms[$x]=afrund($linjemoms[$x]-($linjemoms[$x]*(100-$procent[$x])/100),2);
							}
							$sum+=$linjesum[$x];
							if ($momsfri[$x]!='on' && !$omvbet[$x]) {
								$moms+=afrund($l_sum[$x]*$varemomssats[$x]/100,3); #Decimaltal aendret til 3 2010.12.17 grundet momsdiff (0,01 kr) i ordre id 371 i saldi_297
								$momssum+=afrund($linjesum[$x],2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
#cho __line__." ms $momssum<br>";
								if ($incl_moms && !$b2b) {
									$tmp=afrund($pris[$x]+$pris[$x]*$varemomssats[$x]/100,2);
									if ($rabatart[$x]=="amount") $linjesum[$x]=($tmp-$rabat[$x])*$antal[$x];
									else $linjesum[$x]=$tmp*$antal[$x]-($tmp*$antal[$x]*$rabat[$x]/100);
									$pris[$x]=$tmp;
								}
							}
							if ($rvnr) {
								if ($varenr[$x]==$rabatvarenr) list($pris[$x])=explode("|",$row['lev_varenr']);
								$rabat[$x]=0;
								$linjesum[$x]=($pris[$x]-$rabat[$x])*$antal[$x];
							}	
						} else {
							$dkantal[$x]=NULL;
							$varemomssats[$x]=NULL;
						}
						$beskrivelse[$x]=var2str($beskrivelse[$x],
						$ordre_id[$o],
						$posnr[$x],
						$varenr[$x],
						$dkantal[$x],
						$enhed[$x],
						$pris[$x],
						$procent[$x],
						$serienr[$x],
						$varemomssats[$x],
						$rabat[$x]);
						if ($formular==3 && $skjul_nul_lin && !$lev_antal[$x]) $varenr[$x]=NULL; #
					} else $dkantal[$x]=NULL;
					if ($saet[$x]) {
						if ($lev_varenr[$x]) {
							list($linjesum[$x])=explode("|",$lev_varenr[$x],2);
							$dkantal[$x]='';
						} else $linjesum[$x]='';
						$pris[$x]='';
/*
					if ($saetnr && $saetnr!=$saet[$x]) {
						$r=db_fetch_array(db_select("select beskrivelse from varer where id = '$svid'",__FILE__ . " linje " . __LINE__));
						$beskrivelse[$x]=$r['beskrivelse'];
						$linjesum[$x]=$saetpris;
						$saet[$x]=0;
						$saetpris=0;
						$saetpris=afrund($linjesum[$x-1],2);
					} else $saetpris+=afrund($linjesum[$x],2);
					$saetnr=$saet[$x];
*/					
					}
				}
/*
			if ($saet[$x] && $saetnr) {
				$x++;
				$r=db_fetch_array(db_select("select beskrivelse from varer where id = '$svid'",__FILE__ . " linje " . __LINE__));
				$beskrivelse[$x]=$r['beskrivelse'];
				$linjesum[$x]=$saetpris;
				$saetpris=0;
			}  
*/
				$tmp=$row['rabat']*1;
				if ($x>0 && $formular<4 && !$tmp && $m_rabat[$x] && $status < 3) {
					$y=$x;
					$x++;
					$posnr[$x]=$posnr[$y];
					$vare_id[$x]=$vare_id[$y];
					$varenr[$x]=$varenr[$y];
					$lev_varenr[$x]=$lev_varenr[$y];
					$antal[$x]=$antal[$y];
					$projekt[$x]=$projekt[$y];
	#				$beskrivelse[$x]=findtekst(366,$sprog_id);
					$dkantal[$x]=$dkantal[$y];
					$lev_antal[$x]=$lev_antal[$y];
					$leveres[$x]=$leveres[$y];
					$tidl_lev[$x]=$tidl_lev[$y];
					$rest[$x]=$rest[$y];
					$lokation[$x]=$lokation[$y];
					$enhed[$x]='';
					$rabat[$x]=$rabat[$y];
					($incl_moms && !$b2b)?$tmp=$m_rabat[$y]+$m_rabat[$y]*$varemomssats[$y]/100:$tmp=$m_rabat[$y];
					$pris[$x]=$tmp*-1;
					$rabatpct=afrund($tmp*100/usdecimal($pris[$y]),2);
					$l_sum[$x]=afrund($tmp*-$antal[$x],3);
					$linjesum[$x]=$l_sum[$x];
#				$linjesum[$x]=dkdecimal($l_sum[$x],2);
					if ($varemomssats[$y] > 0) $linjemoms[$x]=$varemomssats[$y]*$linjesum[$x]*0.01;
					else $linjemoms[$x]=$varemomssats[$y]*$linjesum[$x];
					$sum-=$m_rabat[$y]*$antal[$y];
#					$sum+=$l_sum[$x];
					if ($momsfri[$y]!='on' && !$omvbet[$x]) {
						$moms-=afrund($m_rabat[$y]*$antal[$x]*$varemomssats[$y]/100,3);
				$momssum-=afrund($m_rabat[$y]*$antal[$x],2);
			}
			($rabatart[$x]=='amount')?$beskrivelse[$x]=findtekst(466,$sprog_id):$beskrivelse[$x]=findtekst(467,$sprog_id);
			$beskrivelse[$x]=str_replace('$rabatpct',$rabatpct,$beskrivelse[$x]);
		} 
		$linjeantal=$x;
	}
	if ($status<3 && $procenttillag>0) {
		$r=db_fetch_array(db_select("select box13 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
		list($tmp,$procentvare)=explode(chr(9),$r['box13']);
		if ($procentvare && $r=db_fetch_array(db_select("select beskrivelse from varer where varenr='$procentvare'",__FILE__ . " linje " . LINE__))){
					$x++;
					$linjeantal++;
					$posnr[$x]=$posnr[$x-1]+1;
					$varenr[$x]=$procentvare;
					$beskrivelse[$x]=$r['beskrivelse'];
					$procent[$x]=$r['procent'];
					$dkantal[$x]=1;
					$linjesum[$x]=$sum*$procenttillag/100;
					if ($momssats) $momssum+=$linjesum[$x];
					$linjemoms[$x]=$linjesum[$x]/100*$momssats;
					$sum+=$sum*$procenttillag/100;
					$moms+=$linjemoms[$x];
					$pris[$x]=$linjesum[$x];
				}	
			}
#cho __line__." ms $momssum<br>";
			$y=$ya;
			$y_tjek=$y;
			$Opkt=$y-($antal_ordrelinjer*$linjeafstand);
			for ($x=1;$x<=$linjeantal; $x++) {
				if (!isset($l_sum[$x])) $l_sum[$x]=NULL;
				if (!isset($linjesum[$x])) $linjesum[$x]=NULL;
				if (!isset($linjemoms[$x])) $linjemoms[$x]=NULL;
				$beskrivelse[$x]=str_replace('$procenttillæg;',dkdecimal($procenttillag,2),$beskrivelse[$x]);
#				$beskrivelse[$x]=str_replace('$procenttillæg',dkdecimal	($procenttillag,2),$beskrivelse[$x]);
				$beskrivelse[$x]=str_replace('$antal;',$dkantal[$x],$beskrivelse[$x]);
				$beskrivelse[$x]=str_replace('$pris;',$pris[$x],$beskrivelse[$x]);
				$beskrivelse[$x]=str_replace('$linjesum;',$linjesum[$x],$beskrivelse[$x]);
				$beskrivelse[$x]=str_replace('$varenr;',$varenr[$x],$beskrivelse[$x]);
				$beskrivelse[$x]=str_replace('$rabat;',$rabat[$x],$beskrivelse[$x]);
				$beskrivelse[$x]=str_replace('$procent;',$procent[$x],$beskrivelse[$x]);
/*
				$beskrivelse[$x]=str_replace('$antal',$dkantal[$x],$beskrivelse[$x]);
				$beskrivelse[$x]=str_replace('$pris',$pris[$x],$beskrivelse[$x]);
				$beskrivelse[$x]=str_replace('$linjesum',$linjesum[$x],$beskrivelse[$x]);
				$beskrivelse[$x]=str_replace('$varenr',$varenr[$x],$beskrivelse[$x]);
				$beskrivelse[$x]=str_replace('$rabat',$rabat[$x],$beskrivelse[$x]);
				$beskrivelse[$x]=str_replace('$procent',$procent[$x],$beskrivelse[$x]);
*/
				if ($charset=="utf-8") {
				#$varenr[$x]=utf8_iso8859($varenr[$x]);
				#$lev_varenr[$x]=utf8_iso8859($lev_varenr[$x]);
				#$beskrivelse[$x]=utf8_iso8859($beskrivelse[$x]);
				}
/*
			if ($saet[$x]) {
				$linjesum[$x]='';
				$linjemoms[$x]='';
				$pris[$x]='';
				$rabat[$x]='';
				$procent[$x]='';
			} else {
*/			
				($varenr[$x])?$linjesum[$x]=dkdecimal($linjesum[$x],2):$linjesum[$x]=NULL;
				$linjemoms[$x]=dkdecimal($linjemoms[$x],2);
				$pris[$x]=dkdecimal($pris[$x],2);
				$rabat[$x]=dkdecimal($rabat[$x],2);
				$procent[$x]=dkdecimal($procent[$x],2);
#			}
				if ($x>1) $transportsum=$transportsum+$l_sum[$x-1];
				$skriv=0;
				if ($kommentarprint||$formular!=3||$varenr[$x]) $skriv=1; #Fordi tekst uden varenr ikke skal med paa foelgesedlen med mindre det er angivet i "formularprint"; 
#				if ($saet[$x] && $samlevare[$x]) $skriv=0; #Fordi tekst uden varenr ikke skal med paa foelgesedlen med mindre det er angivet i "formularprint"; 
				if ($skriv) {
					for ($z=1; $z<=$var_antal; $z++) {
						if (substr($variabel[$z],0,8)=="fritekst") {
							$tmp=substr($variabel[$z],9);
							$svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$tmp", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						}
						if ($y_tjek!=$y) { #Det først skrevne felt på en linje bliver "ædt" ved sideskift. Derfor dette dummy felt.
							$svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
							$y_tjek=$y;
						}
						if ($variabel[$z]=="posnr") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$posnr[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="varenr") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$varenr[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ ); # ellers kommer varenummer ikke med paa 1. linje paa side 2 . og 3
						elseif ($variabel[$z]=="lev_varenr") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$lev_varenr[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ ); # ellers kommer varenummer ikke med paa 1. linje paa side 2 . og 3
						elseif ($variabel[$z]=="leveres") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$leveres[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="projekt") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$projekt[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="antal") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$dkantal[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ ); #ellers kommer antal ikke med paa 1. linje paa side 2 . og 3
						elseif ($variabel[$z]=="lev_antal") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$lev_antal[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="tidl_lev") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$tidl_lev[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="lev_rest") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$rest[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="pris") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$pris[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="enhed") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$enhed[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="momssats") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$varemomssats[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="rabat") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$rabat[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="procent") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$procent[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="linjemoms") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$linjemoms[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
#						elseif ($variabel[$z]=="lokation") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$lokation[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="varemomssats") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$varemomssats[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="linjesum") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$linjesum[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]!="beskrivelse" && $variabel[$z]!="lokation" && $variabel[$z]!="vare_note") $svar=skriv($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$variabel[$z]", "fritekst_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
						elseif ($variabel[$z]=="beskrivelse") $skriv_beskriv[$x]=$z;
					}
					if ($z=$skriv_beskriv[$x]) {
						$y2=ombryd($id,"$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$beskrivelse[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]",$laengde[$z],$formular,$linjeafstand);
					}
					$y=$y2;
					if ($y==0) $y=$ya;
					$y=$y-$linjeafstand;
				}
				}
			if ($brugsamletpris) {
				$r=db_fetch_array(db_select("select sum,moms from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
				$sum=$r['sum'];
				$moms=$r['moms'];
			}
#cho __line__." ms $momssum<br>";

			$momssum=afrund($momssum,2);
			$sum=afrund($sum,2);
			$ialt=dkdecimal($sum+$moms,2);
			$momsgrundlag=dkdecimal($momssum,2);		
			$moms=dkdecimal($moms,2);
			$sum=dkdecimal($sum,2);

		}
#cho __line__." ms $momssum<br>";
		
		if ($id) find_form_tekst($id, 'S', $formular,0,$linjeafstand,""); # Sum paa sidste side.

		if ($ordre_id[$o]) bundtekst($ordre_id[$o]); # Uden denne skrives kun  side 1
#		if ($mail_fakt) fclose($psfp2);
		#cho "$o A $ordre_id[$o] $mappe/$pfliste[$o]<br>";
		fclose($psfp);
		fclose($htmfp);
	}
#xit; # UDSKRIVNING
	if ($mailantal>0) {
		if(!class_exists('phpmailer')) {
			ini_set("include_path", ".:../phpmailer");
			require("class.phpmailer.php");
		}
		include("../includes/connect.php");
		if (!isset($exec_path)) $exec_path="/usr/bin";
		$r=db_fetch_array(db_select("select var_value from settings where var_name='ps2pdf'",__FILE__ . " linje " . __LINE__));
		if ($r['var_value']) $ps2pdf=$r['var_value'];
		else $ps2pdf="$exec_path/ps2pdf";
		$r=db_fetch_array(db_select("select var_value from settings where var_name='pdftk'",__FILE__ . " linje " . __LINE__));
		if ($r['var_value']) $pdftk=$r['var_value'];
		else $pdftk="$exec_path/pdftk";
		$r=db_fetch_array(db_select("select var_value from settings where var_name='weasyprint'",__FILE__ . " linje " . __LINE__));
		if ($r['var_value']) $weasyprint=$r['var_value'];
		else $weasyprint="$exec_path/weasyprint";
		include("../includes/online.php");
		$r = db_fetch_array(db_select("select box3 from grupper where art='PV' and kodenr='1'",__FILE__ . " linje " . __LINE__));
		($r['box3'])?$formgen='html':$formgen='ps';
		for($x=1;$x<=$mailantal;$x++) {
#		print "<!-- kommentar for at skjule uddata til siden \n";
			if ($formgen=='html') {
				rename ($mappe."/".$pfliste[$x].".htm", $mappe."/".$pfliste[$x]."_1.htm");
				$i=1;
				while(file_exists($mappe."/".$pfliste[$x]."_".$i.".htm")) {
					$indfil=$mappe."/".$pfliste[$x]."_".$i.".htm";
					$udfil=$mappe."/".$pfliste[$x]."_".$i.".pdf";
					system ("weasyprint -e UTF-8 $indfil $udfil");
					$i++;	
				}
				system ("$pdftk ".$mappe."/".$pfliste[$x]."_*.pdf output $mappe/$pfliste[$x].pdf");
				if (file_exists($mappe."/".$pfliste[$x]."_*.htm")) unlink ($mappe."/".$pfliste[$x]."_*.htm");
#				unlink ($mappe."/".$pfliste[$x]."_*.pdf");
			} else {
				system ("$ps2pdf $mappe/$pfliste[$x].ps $mappe/$pfliste[$x].pdf");
			}
			#			print "--> \n";
			if ($logoart=='PDF') {
	#			print "<!-- kommentar for at skjule uddata til siden \n";
				$out=$mappe."/".$pfliste[$x]."x.pdf";
				system ("$pdftk $mappe/$pfliste[$x].pdf background ../logolib/$db_id/$bgr.pdf output $out");
				system  ("mv $out $mappe/$pfliste[$x].pdf");
#				print "--> \n";
			}
			$o=$x-1;
			if ($inkasso) {
				return("$mappe/$pfliste[$x].pdf");
				exit;
			}
			$svar=send_mails("$ordre_id[$o]","$mappe/$pfliste[$x].pdf",$email[$x],$mailsprog[$x],$form_nr[$x],"$mail_subj","$mail_text","$mail_bilag",$x);
		}
	} elseif ($nomailantal>0) {
		print "<big><b>Vent - Udskrift genereres</b></big><br>";
		$mappe=str_replace('../temp/','',$mappe);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$mappe/$printfilnavn&amp;id=$id&amp;udskriv_til=$udskriv_til&amp;art=$art&amp;bgr=$bgr&returside=$returside\">";#20131202
	} elseif ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	#else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	return('OK');
	print "<!--function formularprint slut-->";
}} #endfunc formularprint;

if (!function_exists('formulartekst')) {
function formulartekst($id,$formular,$formularsprog){
print "<!--function formulartekst start-->";
#	global $formular;
	global $momssats;
	global $dkdato;
	global $connection;
	global $psfp;
	global $htminitxt;
	global $htmfp;
	global $mappe;
	global $printfilnavn;
	global $returside;
	global $side;

$rabat=NULL;
	if ($id) {
		$rabat=0;

		include("../includes/ordreopslag.php");
		if ($art=="DO") {$art="Faktura";}
		else {$art="Kreditnota";}

		$query = db_select("select * from ordrelinjer where ordre_id = '$id' and rabat > '0'",__FILE__ . " linje " . __LINE__);
		if($row = db_fetch_array($query)) {$rabat="y";}

		$faktdato=dkdato($fakturadate);
		$query = db_select("select * from ordrelinjer where ordre_id = '$id' and rabat > '0'",__FILE__ . " linje " . __LINE__);
		if($row = db_fetch_array($query)) {$rabat="y";}
	}
	
	$qtxt="select * from formularer where formular = '$formular' and art = '1' and beskrivelse != 'LOGO' and lower(sprog)='$formularsprog'";
#cho "$qtxt<br>";
	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$xa=$row['xa']*2.86;
		$ya=$row['ya']*2.86;
		$xb=$row['xb']*2.86;
		$yb=$row['yb']*2.86;
		$lw=$row['str'];
		$color=$row['color'];
		$tmp=strlen($color);
		for ($a=$tmp;$a<9;$a++) $color="0".$color;
		$tmp1=substr($color,-9,3)/100;
		$tmp2=substr($color,-6,3)/100;
		$tmp3=substr($color,-3,3)/100;
		$color="$tmp1 $tmp2 $tmp3 setrgbcolor";

		if ($xa) {
			fwrite($psfp," $xa $ya moveto $xb $yb lineto $lw setlinewidth $color stroke \n");
			$a=297-$row['ya'];
			$b=$row['xa'];
			$a*=1.01;
			$a.='mm';
			$b.='mm';
			if ($ya==$yb) {#vandret linje
			$c=$row['xb']-$row['xa'];
			$c.='mm';
			
			fwrite($htmfp,"<hr style=\"position:absolute;top:$a;left:$b;border:0.2px solid black; width:$c;\">\n");
			}
			if ($xa==$xb) {#lodret linje
			$c=($row['ya']-$row['yb'])*1.01;
			$c.='mm';
			fwrite($htmfp,"<hr style=\"position:absolute;top:$a;left:$b;border:0.2px solid black; width:1; height:$c\">\n");
			}
			
			
			#			fwrite($htmfp,"<div style=\"position:absolute;top:".$xa/2.86 ."mm;left:".$xb/2.86 ."mm;\">.</div>\n");
		}
	}
#cho __line__." $id<br>";;
	if ($id) find_form_tekst($id, 'A', $formular,0,0,0);
	return $rabat;
print "<!--function formulartekst slut-->";
}}

if (!function_exists('bundtekst')) {
function bundtekst($id) {
print "<!--function bundtekst start-->";
global $logo;
global $logoart;
global $psfp;
global $htminitxt;
global $htmfp;
global $mappe;
global $nextside;
global $printfilnavn;
global $returside;

global $side;
global $y;
global $ya;
global $linjeafstand;

$y=$ya-$linjeafstand;
$side=$side+1;

#cho "side $side<br>";

if ($logoart!='EPS') fwrite($psfp,"showpage\n");
else fwrite($psfp, $logo);
fwrite($htmfp,"</body>\n</html>\n");
#fclose($htmfp);
#$htmfp=fopen($mappe."/".$printfilnavn."_$side.htm","w");
#fwrite($htmfp,$htminitxt);

#cho "Bundtekst returnerer: $y<br>";
return ($y); #indsat 2011.01.27 grundet tomme sider v. sideskift i kommentar. saldi_2 ordre id 3310
# fwrite($psfp,"showpage");
#cho 
print "<!--function bundtekst slut-->";
}}
if (!function_exists('send_mails')) include ("../includes/formFuncIncludes/sendMail.php");

if (!function_exists('find_saldo')) {
	function find_saldo($ordre_id) {
		print "<!--function find_saldo start-->";
		$r=db_fetch_array(db_select("select konto_id,ordredate,fakturadate from ordrer where id = '$ordre_id'",__FILE__ . " linje " . __LINE__));
		$konto_id=$r['konto_id'];
		$ordredate=$r['ordredate'];
		$fakturadate=$r['fakturadate'];
		($fakturadate)?$transdate=$fakturadate:$transdate=$ordredate;
	
		$r=db_fetch_array(db_select("select sum(amount) as saldo from openpost where konto_id = '$konto_id' and transdate<='$transdate'",__FILE__ . " linje " . __LINE__));
		$kontosaldo=$r['saldo']*1;
		return ($kontosaldo);
	print "<!--function find_saldo slut-->";
	}
}
if (!function_exists('utf8_iso8859')) {
# Konverterer tekst fra UTF til ISO 8859-X - i første omgang kun for X=15, men senere mere generelt
# Der kan angives hvilken udgave af ISO 8859 der skal konverteres til og ukendte tegn bliver 
# erstattet af tegnet ? (spørgsmålstegn). Angives 0 som udgave, forsøger funktionen selv at finde 
# den udgave, som passer bedst. Angives ingen benyttes udgave 15. 
# 2014-04-14 Claus Agerskov
function utf8_iso8859($utf8tekst, $iso8859=15) {
	$utf8tekst=str_replace("¤","€",$utf8tekst);
	$utf8tekst=str_replace("¦","|",$utf8tekst);
	$utf8tekst=str_replace("¨",chr(34),$utf8tekst);
	$utf8tekst=str_replace("´","'",$utf8tekst);
	$utf8tekst=str_replace("¸",",",$utf8tekst);
	$utf8tekst=str_replace("¼","1/4",$utf8tekst);
	$utf8tekst=str_replace("½","1/2",$utf8tekst);
	$utf8tekst=str_replace("¾","3/4",$utf8tekst);
  $isotekst=iconv("UTF-8", "ISO-8859-15//TRANSLIT", $utf8tekst);
  return ($isotekst);
#	print "<!--function utf8_iso8859 slut-->";
	}
}
if (!function_exists('rykkerprint')) {
function rykkerprint($konto_id,$rykker_id,$rykkernr,$maaned_fra,$maaned_til,$regnaar,$inkasso) {

	global $bg_fil,$bruger_id;
	global $db,$db_id,$deb_valuta,$deb_valutakurs;
	global $exec_path,$formularsprog,$psfp,$htmfp,$ialt,$valuta,$s_id;

#cho "$konto_id,$rykker_id,$rykkernr,$maaned_fra,$maaned_til,$regnaar,$inkasso<br>";
	
	$mailantal=0; 
	$nomailantal=0;
	$formular=$rykkernr+5;
	if ($formular<6) $formular=6;
	$bg="nix";

	$pfnavn=abs($bruger_id)."_".date("his")."/"."$rykkernr";
	
	$fsize=filesize("../includes/faktinit.ps");
	$fp=fopen("../includes/faktinit.ps","r");
#	$htmfp=fopen("../temp/$db/$pfnavn","w");

	$ps_ini=fread($fp,$fsize);
	fclose($fp);
	
	print "<!-- kommentar for at skjule uddata til siden \n";
	if (!file_exists("../logolib/$db_id")) mkdir("../logolib/$db_id"); 
	if (system("which pdftk") && file_exists("../logolib/$db_id/bg.pdf")) {
		$logoart='PDF';
	} elseif (file_exists("../logolib/$db_id/$formular.ps")) {
		$logo="../logolib/$db_id/$formular.ps";
		$logoart='PS';
	} elseif (file_exists("../logolib/$db_id/bg.ps")) {
		$logo="../logolib/$db_id/bg.ps";
		$logoart='PS';
	} else {
	$formularsprog=strtolower($formularsprog);
	if (!$formularsprog || $formularsprog=='dansk') $tmp="'dansk' or sprog=''";
	else $tmp="'".$formularsprog."'";
		$qtxt="select * from formularer where formular = '$formular' and art = '1' and beskrivelse = 'LOGO' and lower(sprog)=$tmp";
		$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {$logo_X=$row['xa']*2.86; 	$logo_Y=$row['ya']*2.86;}
		else {$logo_X=430; $logo_Y=758;}
		if (file_exists("../logolib/logo_$db_id.eps")) $logo="../logolib/logo_$db_id.eps";
		else $logo="../logolib/logo.eps";
		$logoart='EPS';
	}
	print "-->\n";
#cho "Logo $logo | $logoart<br>";;
	if ($logoart != 'PDF') {
		$fsize=filesize($logo);
		$logofil=fopen($logo,"r");
		$translate=0;
		$logo="";
	#cho "Logo $logo | $logoart<br>";;
		while (!feof($logofil)) {
			$linje=fgets($logofil);
			if ($logoart=='EPS')	{
				if (substr($linje,0,2)!="%!") {
					if (strstr($linje, "translate")&&(!$translate)) {
						$linje="$logo_X $logo_Y translate \n";
#cho "<br>Linle $linje<br>";
						$translate=1;
					}
					$logo=$logo.$linje;
				} 
			} else {
				if (strstr($linje,'showpage')) $linje='';
				if (strstr($linje,'%%PageTrailer')) $linje='';
				if (strstr($linje,'%%Trailer')) $linje='';
				if (strstr($linje,'%%Pages:')) $linje='';
				if (strstr($linje,'%%EOF')) $linje='';
				$logo=$logo.$linje;
			}
		}
		fclose($logofil);
	}

	$mappe="../temp/$db/$bruger_id"."_*";
	system("rm -r $mappe");
	$mappe="../temp/$db/".abs($bruger_id)."_".date("his");
	mkdir("$mappe", 0775);
#	if ($inkasso) $printfilnavn=abs($bruger_id)."_".date("his")."/"."$inkasso";
#	else
	if ($rykkernr[0]) $printfilnavn=abs($bruger_id)."_".date("his")."/"."$rykkernr[0]";
	else $printfilnavn=abs($bruger_id)."_".date("his")."/"."rykker";
	$psfp=fopen("../temp/$db/$printfilnavn.ps","w");
	$htmfp=fopen("../temp/$db/$printfilnavn.htm","w");
	$htminitxt="<html>\n";
	$htminitxt.="<head>\n";
	$htminitxt.="<meta charset=\"UTF-8\"/>\n";
	$htminitxt.="<title>$printfilnavn</title>\n";
	$htminitxt.="<style>\n";
	$htminitxt.="@page {\n";
	$htminitxt.="size: A4;\n";
	$htminitxt.="margin: 0;\n";
	$htminitxt.="padding: 0;\n";
	$htminitxt.="}\n";
	$htminitxt.="body {\n";
	$htminitxt.="height: 297mm;\n";
	$htminitxt.="width: 210mm;\n";
	$htminitxt.="margin-left: auto;\n";
	$htminitxt.="margin-right: auto;\n";
	$htminitxt.="}\n";
	$htminitxt.="</style>\n";
	$htminitxt.="</head>\n";
	$htminitxt.="<body>\n";
	fwrite($htmfp,$htminitxt);
	for ($q=0; $q<count($konto_id); $q++) {
#		$psfp=$psfp1;
#		$htmfp=$htmfp1;
		$x=0;
		$qtxt="select * from formularer where formular = $formular and art = 3";
		$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if ($row['beskrivelse']=='generelt') {	
				$antal_ordrelinjer=$row['xa'];
				$ya=$row['ya'];
				$linjeafstand=$row['xb'];
				$Opkt=$ya-($antal_ordrelinjer*$linjeafstand);	 
			} else {
				$x++;
				$variabel[$x]=$row['beskrivelse'];
				$justering[$x]=$row['justering'];
				$xa[$x]=$row['xa'];
				$str[$x]=$row['str'];
				$laengde[$x]=$row['xb'];
#cho "$str[$x] $laengde[$x]<br>";
				$color[$x]=$row['color'];
				$fed[$x]=$row['fed'];
				$kursiv[$x]=$row['kursiv'];
				$form_font[$x]=$row['font'];
			}
			$var_antal=$x;
		}
#cho "$str[$x] $laengde[$x]<br>";
		
		$side=1;
		$forfalden=0;
		if (($konto_id[$q])||($rykker_id[$q])) {
			$id=$rykker_id[$q];
			
			$qtxt="select ordrer.mail_fakt as mailfakt,ordrer.email as email,ordrer.art,ordrer.art as art,ordrer.ordredate as rykkerdate,";
			$qtxt.="ordrer.sprog as sprog, ordrer.valuta as valuta,ordrer.felt_5 as inkasso from ordrer, adresser, grupper";
			$qtxt.=" where ";
			$qtxt.="ordrer.id = $rykker_id[$q] and adresser.id=ordrer.konto_id and ".nr_cast("grupper.kodenr")." = adresser.gruppe and grupper.art = 'DG'";
#cho "$qtxt<br>";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			($inkasso)?$mailfakt='on':$mailfakt=$r['mailfakt'];
			if ($mailfakt) {
				$mailantal++;		
				$pfnavn="Rykker".$rykker_id[$q];
				$pfliste[$mailantal]=$pfnavn;
				$pfnavn=$db."/".$pfnavn;
				$psfp2=fopen("../temp/$pfnavn","w");
				$psfp=$psfp2;
#				if ($r['felt_5']) $email[$mailantal]=$r['felt_5'];
				$email[$mailantal]=$r['email'];
				$mailsprog[$mailantal]=strtolower($r['sprog']);
#			$form_nr[$mailantal]=$formular;
			} else $nomailantal++;
			fwrite($psfp,$ps_ini);
			$formularsprog=strtolower($r['sprog']);
			$art=$r['art'];
			$rykkerdate=$r['rykkerdate'];	
			$deb_valuta=$r['valuta'];
			if (!$valuta) $valuta='DKK';
			if (!$deb_valuta) $valuta='DKK';
			if ($art=='R2') $formular=7;
			elseif ($art=='R3') $formular=8;
			$form_nr[$mailantal]=$formular;
			if (!$formularsprog) $formularsprog="dansk";
			if ($deb_valuta != 'DKK') {
				$qtxt = "select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$deb_valuta' ";
				$qtxt.= "and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$rykkerdate' order by valuta.valdate desc";
			} else $qtxt=NULL;
			if ($qtxt && $r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $deb_valutakurs=$r2['kurs'];
			else $deb_valutakurs=100;
		$x=0;
		$sum=0;
		$momssum=0;
		$tmp=0;
		$y=$ya;
		$forfalden=0;
		$dkkforfalden=0;
		$amount=0;
		$qtxt = "select serienr as forfaldsdato, beskrivelse, pris as amount, enhed as openpost_id ";
		$qtxt.= "from ordrelinjer where ordre_id = '$rykker_id[$q]' order by serienr,varenr desc";
		$q1 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r1 = db_fetch_array($q1)) {
			if ($r1['openpost_id']) {
				$qtxt="select faktnr, amount, valuta, valutakurs, transdate from openpost where id = '$r1[openpost_id]'";
				if ($r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$faktnr=$r2['faktnr'];
					if (!$r2['valuta']) $r2['valuta']='DKK';
					if (!$r2['valutakurs']) $r2['valutakurs']=100;
					$valuta=$r2['valuta'];
					$valutakurs=$r2['valutakurs']*1;
					$dkkamount=$r2['amount']*$valutakurs/100;
					if ($deb_valuta!="DKK") $amount=$dkkamount*100/$deb_valutakurs;
					else $amount=$r2['amount'];
				}
			} else {
				$faktnr='';	
				$amount=$r1['amount'];
				if ($deb_valuta=="DKK") {
					$dkkamount=$r1['amount'];
					$amount=$dkkamount*100/$deb_valutakurs;
				}	else {
					$amount=$r1['amount'];
					$dkkamount=$amount*$deb_valutakurs/100;
				}
			}
			if ($deb_valuta=='DKK') $amount=$dkkamount;
			$forfalden+=afrund($amount,2); #20140628
			$dkkforfalden+=afrund($dkkamount,2); #20140628
			$belob=dkdecimal($amount,2);
			if ($amount) { #20200505
				for ($z=1; $z<=$var_antal; $z++) {
					if ($variabel[$z]=="dato") {
						$z_dato=$z;
						skriv($id,$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdato($r1['forfaldsdato']), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
					if ($variabel[$z]=="faktnr" && $faktnr) {
						$z_faktnr=$z;
						skriv($id,$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$faktnr", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
					if ($variabel[$z]=="beskrivelse") {
						$z_beskrivelse=$z;
						($laengde[$z])?$beskr=(substr($r1['beskrivelse'],0,$laengde[$z])):$beskr=$r1['beskrivelse']; #20190430
						skriv($id,$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$beskr", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
					if (strstr($variabel[$z],"bel") && $belob) {
						$z_belob=$z;
						skriv($id,$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", $belob, "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
				}	
				$y=$y-4;
			}
		}
		$ialt=dkdecimal($forfalden,2);
		formulartekst($rykker_id[$q],$formular,$formularsprog);
#		$ialt=dkdecimal($forfalden,2);
		find_form_tekst("$rykker_id[$q]", "S","$formular","0","$linjeafstand","");
		bundtekst($konto_id[$q]);
	}
	}
	fclose($psfp);
	if ($mailantal>0) {
		if(!class_exists('phpmailer')) {
			ini_set("include_path", ".:../phpmailer");
			require("class.phpmailer.php");
		}
		if (!isset($exec_path)) $exec_path="/usr/bin";
		if (!isset($exec_path)) $exec_path="/usr/bin";
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select var_value from settings where var_name='ps2pdf'",__FILE__ . " linje " . __LINE__));
		if ($r['var_value']) $ps2pdf=$r['var_value'];
		else $ps2pdf="$exec_path/ps2pdf";
		$r=db_fetch_array(db_select("select var_value from settings where var_name='pdftk'",__FILE__ . " linje " . __LINE__));
		if ($r['var_value']) $pdftk=$r['var_value'];
		else $pdftk="$exec_path/pdftk";
		include("../includes/online.php");
		for($x=1;$x<=$mailantal;$x++) {
			print "<!-- kommentar for at skjule uddata til siden \n";
			system ("$ps2pdf ../temp/$db/$pfliste[$x] ../temp/$db/$pfliste[$x].pdf");
			if ($logoart=='PDF') {
				$out="../temp/$db/".$pfliste[$x]."x.pdf";
				system ("$pdftk ../temp/$db/$pfliste[$x].pdf background ../logolib/$db_id/bg.pdf output $out");
				if (!$inkasso) {	
					unlink ("$mappe/$pfliste[$x].pdf");
					system  ("mv $out $mappe/$pfliste[$x].pdf");
				}
			} else {
				if (!$inkasso) {	
					unlink ("$mappe/$pfliste[$x].pdf");
					system ("mv ../temp/$db/$pfliste[$x].pdf $mappe/$pfliste[$x].pdf");
				}
			}
			print "--> \n";
			if ($inkasso) {
				return("../temp/$db/$pfliste[$x].pdf");
				exit;
			} else $svar=send_mails(0,"$mappe/$pfliste[$x].pdf",$email[$x],$mailsprog[$x],$form_nr[$x]);
		}
	}
	if ($nomailantal>0) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$db/$printfilnavn&id=$id&art=R&udskriv_til=PDF\">";
		exit;
	}
	print "<meta http-equiv=\"refresh\" content=\"0;URL=rapport.php?rapportart=openpost\">";
	exit;
}} #endfunc rykkerprint
#######################################################################################################
if (!function_exists('kontoprint')) {
function kontoprint($konto_fra,$konto_til,$dato_fra,$dato_til,$kontoart,$email) {
global $bruger_id;
global $db,$db_id;
global $formularsprog;
global $htmfp;
global $mappe;
global $printfilnavn,$psfp,$y;
global $s_id;

$dkkforfalden=$nomailantal=$mailantal=0;
$mailsprog='Dansk';
($dato_fra)?$dato_fra=usdate($dato_fra):$dato_fra="1970-01-01";
$dato_til=usdate($dato_til);

$formular=11;

$fsize=filesize("../includes/faktinit.ps");
$fp=fopen("../includes/faktinit.ps","r");
$ps_ini=fread($fp,$fsize);
fclose($fp);
$fp=fopen("../includes/faktinit.htm","r");
$htm_ini=fread($fp,$fsize);
fclose($fp);

if (!isset($formularsprog) || !$formularsprog) $formularsprog='dansk';
$r=db_fetch_array(db_select("select count(id) as antal from formularer where formular = '$formular' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__));
if ($r['antal']<5) {
	include("../includes/formularimport.php");
	formularimport("../importfiler/formular.txt",'11');
}
	$mappe="../temp/$db/".abs($bruger_id)."_*";
	system("rm -r $mappe");
	$mappe="../temp/$db/".abs($bruger_id)."_".date("his");
	if (!file_exists($mappe)) mkdir("$mappe", 0775);
	$printfilnavn="$mappe/"."kontoudtog";
	$psfp=fopen("$printfilnavn.ps","w");
	$htmfp=fopen("$printfilnavn.htm","w");
	fwrite($psfp,$ps_ini);
	fwrite($htmfp,$htm_ini);
	if (!$konto_til && $konto_fra) $konto_til = $konto_fra;
	if (!$konto_til) $konto_til = '9999999999';
	if (!$konto_fra) $konto_fra = '1';
	$x=0;
	if (is_numeric($konto_fra)) {
	#20161124
		if ($konto_fra != $konto_fra) $qtxt="select id from adresser where kontonr>='$konto_fra' and kontonr<='$konto_til' and art = '$kontoart' and lukket != 'on'";
		else $qtxt="select id,gruppe from adresser where kontonr='$konto_fra' and art = '$kontoart'";
	} elseif ($konto_fra && $konto_fra!='*') {
		$konto_fra=str_replace("*","%",$konto_fra);
		$tmp1=strtolower($konto_fra);
		$tmp2=strtoupper($konto_fra);
		$qtxt = "select id,gruppe from adresser where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art = '$kontoart' order by firmanavn";
	} else $qtxt = "select id,gruppe from adresser where art = '$kontoart' order by firmanavn";
	$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)){
		$konto_id[$x]=$r['id'];
		$kontoGruppe[$x]=$r['gruppe'];
		$x++;
	}
for ($i=0;$i<count($konto_id);$i++) {
	$udskrevet=NULL;
	$x=0;
	$qtxt="select * from formularer where formular = '$formular' and art = '3'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)) {
			if ($row['beskrivelse']=='generelt') {	
				$antal_ordrelinjer=$row['xa'];
				$ya=$row['ya'];
				$linjeafstand=$row['xb'];
				$Opkt=$ya-($antal_ordrelinjer*$linjeafstand);	 
			} else {
				$x++;
				$variabel[$x]=$row['beskrivelse'];
				$justering[$x]=$row['justering'];
				$xa[$x]=$row['xa'];
				$str[$x]=$row['str'];
				$laengde[$x]=$row['xb'];
				$color[$x]=$row['color'];
				$fed[$x]=$row['fed'];
				$kursiv[$x]=$row['kursiv'];
				$form_font[$x]=$row['font'];
		}
		$var_antal=$x;
	}
	$side=1;
	$forfalden=0;
	$qxt="select count(id) as postantal from openpost where konto_id='$konto_id[$i]' and transdate >= '$dato_fra'";
	$r = db_fetch_array(db_select("$qxt",__FILE__ . " linje " . __LINE__));
	$postantal[$i]=$r['postantal'];
	if ($konto_id[$i] && $postantal[$i]) {
		$q=db_select("select * from adresser where id=$konto_id[$i]",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$betalingsbet=$r['betalingsbet'];
			$betalingsdage=$r['betalingsdage'];
			$r2=db_fetch_array(db_select("select box3 from grupper where art='DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
			$kontovaluta=$r2['box3'];
			if (!$kontovaluta) $kontovaluta='DKK';
			if ($email) $mailantal++;
			else $nomailantal++;
			$debet=0;
			$kredit=0;
			$form_nr[$mailantal]=$formular;
			if (!$formularsprog) $formularsprog="dansk";
			$y=$ya;
			if ($kontovaluta == 'DKK') $dagskurs=100;
			else {
				$qtxt = "select kodenr from grupper where box1 = '$kontovaluta' and art='VK'";
				$r1 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$valutakode=$r1['kodenr'];
				$qtxt = "select kurs from valuta where gruppe ='$valutakode' and valdate <= '$dato_til' order by valdate desc limit 1";
				$r1 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$dagskurs=$r1['kurs'];
			}
			if ($dato_fra > '1970-01-01') {
				$saldo = 0;
				$qtxt = "select transdate,amount,valuta,valutakurs from openpost where konto_id='$konto_id[$i]' and transdate < '$dato_fra' order by transdate";
				$q1=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
				while ($r1 = db_fetch_array($q1)) {
					if ($kontovaluta==$r1['valuta']) $saldo+=$r1['amount'];
					else {
						$qtxt = "select kodenr from grupper where box1 = '$kontovaluta' and art='VK'";
						$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$valutakode=$r2['kodenr'];
						$qtxt = "select kurs from valuta where gruppe ='$valutakode' and valdate <= '$r1[transdate]' order by valdate desc limit 1";
						$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$dagskurs=$r2['kurs'];
						$saldo+=$r1['amount']*$r1['valutakurs']/$r2['kurs'];
					}
				}
				for ($z=1; $z<=$var_antal; $z++) {
					if ($variabel[$z]=="beskrivelse") {
						$z_beskrivelse=$z;
						skriv('0',$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "Primosaldo", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
					if ($variabel[$z]=="saldo") {
						$z_saldo=$z;
						$dksaldo=dkdecimal($saldo);
						if (!$dksaldo)$dksaldo="0,00"; 
						skriv('0',$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$dksaldo", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
				}
			} else {
				$saldo=0;
				$dksaldo="0,00";
			}
			$y=$y-4;
			$qxt="select * from openpost where konto_id='$konto_id[$i]'  and transdate >= '$dato_fra' and transdate <= '$dato_til' order by transdate,id";
			$q1 = db_select("$qxt",__FILE__ . " linje " . __LINE__);
			while ($r1 = db_fetch_array($q1)) {
			
#cho "PSFP $psfp<br>";
				$valuta=$r1['valuta'];
				$amount=$r1['amount'];
				$valutakurs=$r1['valutakurs']*1;
				$ordre_id=$r1['refnr'];
				if (!$valuta) $valuta='DKK';
				if (!$valutakurs) $valutakurs=100;
				$debet=0;
				$kredit=0;
				($amount>=0)?$debet=$amount:$kredit=$amount*-1;
				if ($valutakurs!=100 && $valuta=='DKK') {
					$debet*=$valutakurs/100;
					$kredit*=$valutakurs/100;
				}
#				$saldo+=$debet-$kredit;
				$dkkamount=$amount*100/$valutakurs;
				if ($r1['forfaldsdate']) $forfaldsdato=dkdecimal($r1['forfaldsdate']); #20200405
				elseif ($debet) $forfaldsdato=forfaldsdag($r1['transdate'], $betalingsbet, $betalingsdage);
		/*
			}
			if ($deb_valuta!="DKK" && $deb_valuta!=$valuta) $amount=$dkkamount*100/$deb_valutakurs;
			elseif ($deb_valuta==$valuta) $amount=$r2['amount'];
			else $amount=$dkkamount;
	*/		
				if ($kontovaluta==$r1['valuta']) $saldo+=afrund($r1['amount'],2);
					else {
						$qtxt = "select kodenr from grupper where box1 = '$kontovaluta' and art='VK'";
						$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$valutakode=$r2['kodenr'];
						$qtxt = "select kurs from valuta where gruppe ='$valutakode' and valdate <= '$r1[transdate]' order by valdate desc limit 1";
						$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$saldo+=afrund($r1['amount']*$r1['valutakurs']/$r2['kurs'],2);
#						$saldo=afrund($saldo,2);
					}
	#			$saldo+=$amount; 20150316
				$dkkforfalden+=$dkkamount;
				$belob=dkdecimal($amount,2);
				for ($z=1; $z<=$var_antal; $z++) {
					if ($variabel[$z]=="dato") {
						$z_dato=$z;
						$y = skriv('0',$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdato($r1['transdate']), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
					if ($variabel[$z]=="forfaldsdato") {
						$z_forfaldsdato=$z;
						$y = skriv('0',$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", $forfaldsdato, "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
					if ($variabel[$z]=="faktnr") {
						$z_faktnr=$z;
						$y = skriv('0',$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$r1[faktnr]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
					if ($variabel[$z]=="beskrivelse") {
						$z_beskrivelse=$z;
						$y = skriv('0',$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", substr($r1['beskrivelse'],0,$laengde[$z]), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
					if ($variabel[$z]=="debet") {
						$z_debet=$z;
						if ($debet) $y=skriv('0',$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdecimal($debet,2), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
					if ($variabel[$z]=="kredit") {
						$z_kredit=$z;
						if ($kredit) $y = skriv('0',$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdecimal($kredit,2), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
					if ($variabel[$z]=="saldo") {
						$z_saldo=$z;
						$dksaldo=dkdecimal($saldo,2);
						if (!$dksaldo)$dksaldo="0,00"; 
						$y = skriv('0',$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$dksaldo", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
					if (strstr($variabel[$z],"bel") && $belob) {
						$z_belob=$z;
						$y = skriv('0',$str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", $belob, "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular",__line__ );
					}
				}	
				$y=$y-4;
				if ($y<=$Opkt) {
					$y=formulartekst($konto_id[$i],$formular,$formularsprog); 		 
					$ialt=dkdecimal($forfalden,2);
					find_form_tekst(0, "S","$formular","0","$linjeafstand","");
					bundtekst(0);
					$qtxt="select * from formularer where formular = '$formular' and art = '3' and beskrivelse='generelt'";
					$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$y=$r['ya'];
					#cho __line__." Y $y<br>";					
					$udskrevet=$side-1; #20150410
#cho "$udskrevet=$side-1 <br> $y <= 	$Opkt<br>";
				}
	#cho "$udskrevet!=$side<br>";
			}
			if ($udskrevet!=$side) {
				formulartekst($konto_id[$i],$formular,$formularsprog); 		 
				$ialt=dkdecimal($forfalden,2);
				find_form_tekst(0, "S","$formular","0","$linjeafstand","");
				bundtekst(0);
			}
		}
	}
}
fclose($psfp);
fclose($htmfp);
#if ($mailantal>0) include("mail_faktura.php");
#cho "lukker nu<br>";
if ($mailantal>0) {
	if (!isset($exec_path)) $exec_path="/usr/bin";
#	$qtxt="select * from formularer where formular = '11' and art = '5' and sprog='Dansk' order by xa,id";
#	$r=db_fetch_array(db_select($qtxt",__FILE__ . " linje " . __LINE__));
	include("../includes/connect.php");
	$r=db_fetch_array(db_select("select var_value from settings where var_name='ps2pdf'",__FILE__ . " linje " . __LINE__));
	if ($r['var_value']) $ps2pdf=$r['var_value'];
	else $ps2pdf="$exec_path/ps2pdf";
	$r=db_fetch_array(db_select("select var_value from settings where var_name='pdftk'",__FILE__ . " linje " . __LINE__));
	if ($r['var_value']) $pdftk=$r['var_value'];
	else $pdftk="$exec_path/pdftk";
	include("../includes/online.php");
			ini_set("include_path", ".:../phpmailer");
			require_once("class.phpmailer.php");
			for($x=1;$x<=$mailantal;$x++) {
#				print "<!-- kommentar for at skjule uddata til siden \n";$db/$printfilnavn
				system ("$ps2pdf $printfilnavn.ps $printfilnavn.pdf");
				if (file_exists($pdftk) && file_exists("../logolib/$db_id/bg.pdf")) {
					$out=$printfilnavn."x.pdf";
					system ("$pdftk $printfilnavn.pdf background ../logolib/$db_id/bg.pdf output $out");
					if (file_exists("$printfilnavn.pdf")) unlink ("$printfilnavn.pdf");
					system  ("mv $out $printfilnavn.pdf");
#				} else {
#					if (file_exists("$printfilnavn.pdf")) unlink ("$printfilnavn.pdf");
#					system ("mv ../temp/$db/$printfilnavn.pdf $printfilnavn.pdf");
				}
				$svar=send_mails(0,"$printfilnavn.pdf",$email,$mailsprog,$formular,'','','',0);
				echo "$svar<br>";
			}
		} 
if ($nomailantal>0) {
 	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$printfilnavn&udskriv_til=PDF&udskrift=kontokort\">";
}
return;
#print "<a href=\"../includes/luk.php\">Luk</a>";
exit;

}} #endfunc kontoprint
function findWeight($id,$wType,$deliverNo) {
	$wType=strtolower($wType);
	$weight=0;
	if ($wType == 'netweight' || $wType == 'grossweight') {
		$qtxt = "select batch_salg.antal,varer.$wType as weight from batch_salg,varer where ";
		$qtxt.= "batch_salg.ordre_id='$id' and batch_salg.lev_nr='$deliverNo' and batch_salg.vare_id=varer.id";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$weight+=$r['antal']*$r['weight'];
		}
	}
	return "$weight";
}
?>
