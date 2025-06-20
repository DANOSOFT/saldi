<?php
// ---------------------includes/formfunk.php ------patch 3.5.3----2015-03-05--------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
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

if (!function_exists('skriv')) {
function skriv($str, $fed, $italic, $color, $tekst, $tekstinfo, $x, $y, $format, $form_font,$formular) {
print "<!--function skriv start-->";
	global $side;
	global $connection;
	global $fp;
	global $printerid;
	global $form_id;
	global $subtotal;
	global $id;
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
	
	if ($slutfed) {
		$startfed=NULL;
		$slutfed=NULL;
	}
	if ($slutbig) {
		$big=NULL;
		$slutbig=NULL;
	}
	if ($slutsmall) {
		$small=NULL;
		$slutsmall=NULL;
	}
	if ($slutitalic) {
		$startitalic=NULL;
		$slutitalic=NULL;
	}
#cho "$str,$fed,$italic,$color,$tekst,$tekstinfo,$x,$y,$format,$form_font,$formular<br>";
	
#if ($tekst == "printer flytning, firewall opsætning, 4") echo "skriv $x $y $tekst<br>";

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
	$tmp1=substr($color,-9,3)/100;
	$tmp2=substr($color,-6,3)/100;
	$tmp3=substr($color,-3,3)/100;
	$color="$tmp1 $tmp2 $tmp3 setrgbcolor";

	$x=$x*2.86;
	$y2=$y*2.86;

	if ($format=='H'){$format="$color dup stringwidth pop\nneg 0 rmoveto";}
	elseif ($format=='C'){$format="$color dup stringwidth pop\n2 div neg 0 rmoveto";}
	else {$format="$color";}

	if (($fed=='on'||$startfed=='on')&&($italic!='on')) {$form_font=$form_font.'-Bold-ISOLatin9 findfont';}
	elseif (($fed!='on'||$startfed=='on')&&($italic=='on')) {$form_font=$form_font.'-Italic-ISOLatin9 findfont';}
	elseif (($fed=='on'||$startfed=='on')&&($italic=='on')) {$form_font=$form_font.'-BoldItalic-ISOLatin9 findfont';}
	elseif ($form_font=="Times") {$form_font=$form_font.'-Roman-ISOLatin9 findfont';}
	else {$form_font=$form_font.'-ISOLatin9 findfont';}
	if (strstr($tekstinfo, 'ordrelinjer')) {
		list ($tekstinfo, $Opkt)=explode("_",$tekstinfo,2);
#cho "Y $y > $Opkt<br>";
		if ($y <= $Opkt) { # Sideskift
			global $tekst1;
			global $x1;
			$tekst1=$tekst; # Ellers forsvinder den tekst som slukke til at blive skrevet (rabat)
			$x1=$x;
			$preside = $side-1;
			$nextside = $side+1;
			$dk_transportsum=dkdecimal($transportsum);
			$query = db_select("select * from formularer where formular = $formular and side = '!S' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				if ($row[color]!='0'){
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
						if ($z!=$n) {$n++;}
						$z=$n;
						if (substr($row['beskrivelse'],$x,1)!=" "){
							$streng[$n]=$streng[$n].substr($row['beskrivelse'],$x,1);
						}	else {
							$n++;
							$streng[$n]=" ";
						}
					}
					else { $streng[$n]=$streng[$n].substr($row['beskrivelse'],$x,1);}
				}
				$streng_antal=$n;
				$ny_streng="";
				for ($i=0; $i<=$streng_antal; $i++){
					if (substr($streng[$i],0,1)=="$") {
						$streng[$i]=substr($streng[$i],1);
						list($tabel,$variabel)=explode("_",$streng[$i],2);
#cho "134 $tabel<br>";						
						if ($tabel=="ordre") {
							if ($variabel=="rykkerdate") $variabel="fakturadate";
							$q2 = db_select("select $variabel from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
						} elseif (($tabel=="eget") || ($tabel=="egen")) {
							$q2 = db_select("select $variabel from adresser where art='S'",__FILE__ . " linje " . __LINE__);
						} elseif (($tabel=="adresser") || ($tabel=="adresser")) {
#cho "select $variabel from adresser where id='$id'<br>";
							$q2 = db_select("select $variabel from adresser where id='$id'",__FILE__ . " linje " . __LINE__);
						} elseif (($tabel=="ansat")&&($ref)) {
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
					$ny_streng=$ny_streng.$streng[$i];
					if ($charset=="UTF-8") $ny_streng=utf8_iso8859($ny_streng);
				}
				if ($ny_streng) {
					if ($small || $big){
						if ($small)$ny_str=$str-2;
						if ($big) $ny_str=$str+2;
					} else $ny_str=$str;
					# udskrivning af formular variabler
					if ($row['xa']) {
						fwrite($fp,"/$form_font\n$row[str] scalefont\nsetfont\nnewpath\n".$row[xa]*2.86." ".$row[ya]*2.86." moveto ($ny_streng) $format show\n");
					}
				}
			}
			$y=$ya;
			$y=bundtekst($id);
			if ($logoart=='PS') fwrite($fp, $logo);
			formulartekst($id,$formular,$formularsprog);
		}
	}
	if ($small || $big){
		if ($small)$ny_str=$str-2;
		if ($big) $ny_str=$str+2;
	} else $ny_str=$str;
	if ($charset=="UTF-8") $tekst=utf8_iso8859($tekst);
	# herunder y2/2.86>Opkt tilfoejet 20110127 grundet 1 ordrelinje på ny side ogsaa skrives nder ordrelinjefelt v. ombrudt kommentar saldi_2 ordre id 3310

	if ($x1 && $tekst1 && $y2/2.86>$Opkt) {
		fwrite($fp,"/$form_font\n$str scalefont\nsetfont\nnewpath\n$x1 $y2 moveto (".$tekst1.") $format show\n");
	}

	if ($x && $tekst && $y2/2.86>$Opkt) {
		fwrite($fp,"/$form_font\n$str scalefont\nsetfont\nnewpath\n$x $y2 moveto (".$tekst.") $format show\n");
	}
	return $y;
print "<!--function skriv slut-->";
}} #endfunc skriv();

if (!function_exists('ombryd')) {
function ombryd($str, $fed, $italic, $color, $tekst, $tekstinfo, $x, $y, $format, $form_font, $laengde,$formular,$linespace) {
print "<!--function ombryd start-->";
#cho "text $tekst<br>";
	$lokation=NULL;
	if (strpos($tekst,chr(9))) {
		list($tekst,$lokation)=explode(chr(9),$tekst);
#		echo "T	$tekst,L $lokation<br>";
#	exit;
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
						$y=skriv($str,$fed,$italic,$color,$nytekst,$tekstinfo,$x,$y,$format,$form_font,$formular);
					}
					if  ($y!=$tmp) { #aendret 2011.01.27 grundet manglende linje efter sideskift - saldi_2 ordre id 3310
#					if ($y<$Opkt) {
						# sikring af ombrudt tekst v. sideskift.
						$y=skriv($str,$fed,$italic,$color,$nytekst,$tekstinfo,$x,$y,$format,$form_font,$formular);
					} 

					$y=$y-$linespace;
				}
				$nytekst="";
		 }
	}
#cho "nytext $nytekst<br>";
	$nytekst=trim($nytekst);
	if (strlen($nytekst)>=1){
		$y=skriv($str,$fed,$italic,$color,$nytekst,$tekstinfo,$x,$y,$format,$form_font,$formular);
	}
	if ($lokation) {
		$y=$y-$linespace;
			$y=skriv($str,$fed,$italic,$color,$lokation,$tekstinfo,$x,$y,$format,$form_font,$formular);
	}
#cho "returnerer $y<br>";
	return $y;
print "<!--function ombryd slut-->";
}}

if (!function_exists('find_form_tekst')) {
function find_form_tekst($id, $sidetekst, $formular,$y_pos,$linjeafstand,$pos_part) {
print "<!--function find_form_tekst start-->";
	global $bruger_id;
	global $sum;
	global $moms;
	global $momsgrundlag;
	global $ialt;
	global $side;
	global $lev_nr;
	global $ref;
	global $transportsum;
	global $ya;
#	global $linjeafstand;
	global $gebyr;
	global $formularsprog;
	global $deb_valuta;
	global $valuta;
	global $deb_valutakurs;
	global $valutakurs;
	global $vis_saet;
	global $brugsamletpris;

	$dk_transportsum=dkdecimal($transportsum);
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
		$z=-1;
		$y=0;
		$streng=array();
		$if=array();
#cho $row['beskrivelse']."<br>";
		for ($x=0; $x<strlen($row['beskrivelse']); $x++){ # strengen loebes igennem
		if (((substr($row['beskrivelse'],$x,1)=="$")||($z==$y))&&(substr($row['beskrivelse'],0,4)!="+73<")){ #hvis strengen er en variabel eller en betalingskode
				if (($if[$y]!='1')&&($z!=$y)) $y++; #Hvis enden af strengen ikke er naaet
				$z=$y;
#|| ($streng[$y]=="betalingsid" && substr($row['beskrivelse'],$x,1)=="(")
					if ((substr($row['beskrivelse'],$x,1)!=" " && substr($row['beskrivelse'],$x,1)!=";")) { # alm variabel opbygges
						if (($if[$y]=='1')&&(substr($row['beskrivelse'],$x,1)==")")) { # opbygning af "if-variabel" slut  
#						$streng[$y]=substr($streng[$y],0,strlen($streng[$y])-1);
							list($if_tabel,$if_variabel) = explode("_",$streng[$y],2); #07.10.2007 --> 
							if (substr($if_tabel,1)=="ordre") {
#cho "select $if_variabel from ordrer where id=$id<br>";
							$r=db_fetch_array(db_select("select $if_variabel from ordrer where id=$id",__FILE__ . " linje " . __LINE__));
								$tmp=$r[$if_variabel];
#cho "<br>TMP $tmp<br>";
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
							$streng[$y]=$streng[$y].substr($row['beskrivelse'],$x,1);
						}
					} else { #alm variabel opbygning slut 
					$y++;
					if (substr($row['beskrivelse'],$x,1)=="(") $streng[$y]=";";
					if (substr($row['beskrivelse'],$x,1)!=";") $streng[$y]=" ";
				}
			} else { #Hivs strengen ikke er en variabel eller en batalingskode
				$streng[$y]=$streng[$y].substr($row['beskrivelse'],$x,1);
				if ($streng[$y]=='if(') { #Så skal der tjekkes om variablen mellem i parantesen har en værdi 
					$if[$y]='1'; # når $if[$y] skal variablen opbygges
					$streng[$y]=''; # 'if(' er ikke en del af variablen. 
				} # endif
			} # end if-else
		} # endfor ($x=0; $x<strlen($row['beskrivelse'])
##cho "Streng $streng[$y]<br>";
##cho $row['beskrivelse']."<br>";

		$streng_antal=$y;
		$ny_streng="";
		for ($x=0; $x<=$streng_antal; $x++){
#cho "-------------------------- Streng $streng[$x] -----------------------------------<br>";
			if (substr($streng[$x],0,1)=="$") {
				$streng[$x]=substr($streng[$x],1);
				list($tabel, $variabel)=explode("_",$streng[$x],2);
				if (($formular==3)&&($tabel=="ordre")&&(($variabel=="lev_navn")||($variabel=="lev_addr1")||($variabel=="lev_addr2")||($variabel=="lev_postnr")||($variabel=="lev_bynavn")||($variabel=="lev_kontakt"))) {
					$variabel=tjek_lev_addr($variabel, $id);
				}
#cho "324 $tabel<br>";
				if ($tabel=="ordre") {
					if ($variabel=="rykkerdate") $variabel="fakturadate";
					$q2 = db_select("select $variabel from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
				} elseif (($tabel=="eget") || ($tabel=="egen")) {
					$q2 = db_select("select $variabel from adresser where art='S'",__FILE__ . " linje " . __LINE__);
				} elseif (($tabel=="adresser") || ($tabel=="adresser")) {
#cho "select $variabel from adresser where id='$id'<br>";				
					$q2 = db_select("select $variabel from adresser where id='$id'",__FILE__ . " linje " . __LINE__);
				} elseif ($tabel=="kunde") {
					$q2 = db_select("select $variabel from adresser where art='D' and id=$id",__FILE__ . " linje " . __LINE__);
				} elseif ($tabel=="levering") {
					$q2 = db_select("select $variabel from batch_salg where ordre_id=$id and lev_nr=$lev_nr",__FILE__ . " linje " . __LINE__);
				}	elseif ($tabel=="ansat") {
					$r2 = db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__));
					$q2 = db_select("select $variabel from ansatte where konto_id=$r2[id] and navn='$ref'",__FILE__ . " linje " . __LINE__);
				}	elseif ($tabel=="forfalden" || $tabel=="rykker") {# $tabel=="rykker" indsat 14.04.08
					$r2 = db_fetch_array(db_select ("select * from varer where id IN (select xb from formularer where beskrivelse='GEBYR' and formular='$formular')",__FILE__ . " linje " . __LINE__));
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
							$r3 = db_fetch_array(db_select("select amount,valuta,valutakurs from openpost where id='$r2[enhed]'",__FILE__ . " linje " . __LINE__));
							if (!$r3['valuta']) $r3['valuta']='DKK';
							if (!$r3['valutakurs']) $r3['valutakurs']=100;
							$valuta=$r3['valuta'];
							$valutakurs=$r3['valutakurs']*1;
							$dkkamount=$r3['amount']*100/$valutakurs;
							if ($deb_valuta!="DKK" && $deb_valuta!=$valuta) $amount=$dkkamount*100/$deb_valutakurs;
							elseif ($deb_valuta==$valuta) $amount=$r3['amount'];
							else $amount=$dkkamount;
						}
						if ($deb_valuta=='DKK') $amount=$dkkamount;
						$forfalden+=afrund($amount,2); #20140628
					}

					$sum=dkdecimal($forfalden);
					$gebyr=dkdecimal($gebyr);
					$rente=dkdecimal($rente);
				}
				if (($tabel!="formular")&&($tabel!="forfalden")&&($tabel!="rykker")) {
					$r2 = db_fetch_array($q2);
					if (strstr($variabel, 'date')) {
						$streng[$x]=dkdato($r2[$variabel]);
					} elseif ($variabel=='momssats') {
						list($streng[$x],$tmp)=explode(",",dkdecimal($r2[$variabel]));
						$tmp=$tmp*1;
						if ($tmp) $streng[$x].=",".$tmp;
					} elseif ($vis_saet && substr($variabel,0,5)=='felt_') { #20150302
						if ($variabel=="felt_2") {
							$streng[$x]=dkdecimal($r2[$variabel]);
						} elseif ($variabel=="felt_3" || $variabel=="felt_4") {
							$r3=db_fetch_array(db_select("select felt_4 from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
							if ($r3['felt_4']*1==0) {
								$streng[$x]='';
							} elseif ($variabel=="felt_3") $streng[$x]=$r2[$variabel];
							elseif ($variabel=="felt_4") $streng[$x]=dkdecimal($r2[$variabel]);
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
					if ($variabel=='kontosaldo') $streng[$x]=dkdecimal(find_saldo($id));
					if (strstr($variabel,'betalingsid')) $streng[$x]=modulus_10($id);
				}
			}
			if ($if[$x]=="!") {
				$if[$x+1]==$streng[$x];
				$streng[$x]='';
			}
			elseif (($if[$x])&&($streng[$x])) {
				$streng[$x]=$if[$x]." ".$streng[$x];
			}
#cho "U $udskriv G-Streng $streng[$x]<br>";
		$ny_streng.=$streng[$x];
#cho "<b>A</b> $ny_streng&&$udskriv<br>";
		} #endfor ($x=0; $x<=$streng_antal; $x++)
#cho "<b>B</b> $ny_streng&&$udskriv<br>";
		if ($ny_streng&&$udskriv) {
			if ($y_pos) {
				if (!$pre_xa || $pre_xa >= $row['xa']) {
					$y_pos=$y_pos-$linjeafstand;
					$pre_xa=$row['xa'];
				}
				$row['ya']=$y_pos;
			}
#cho "$row[str]"."$row[fed]"."$row[kursiv]"."$row[color]"."$ny_streng".'header'."$row[xa]"."$row[ya]"."$row[justering]"."$row[font]"."$formular<br>";
			skriv("$row[str]","$row[fed]","$row[kursiv]","$row[color]","$ny_streng",'header',"$row[xa]","$row[ya]","$row[justering]","$row[font]","$formular");
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
	$forfaldsdato=dkdato($forfaldsaar."-".$forfaldsmd."-".$forfaldsdag); // echo "fakturadato: $fakturadate<br>"; echo "betalingsbet: $betalingsbet<br>"; echo "forfaldsdato: $forfaldsdato"; exit();
	return $forfaldsdato; 
print "<!--function find_forfaldsdato slut-->";
}}

if (!function_exists('tjek_lev_addr')) {
function tjek_lev_addr($variabel, $id) {
print "<!--function tjek_lev_addr start-->";
	 if ($variabel=="lev_navn"){$tmp="firmanavn";}
	 else {$tmp=substr($variabel, 4);}
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

	include("../includes/std_func.php");
	include("../includes/var2str.php");

	global $db;
	global $db_id;
	global $dkdato;
	global $form_id;
	global $charset;
	global $formularsprog;
	global $fp;
	global $gebyr;
	global $ialt;
#	global $id;
	global $lev_nr;
	global $linjeafstand;
	global $logo;
	global $logoart;
	global $moms;
	global $momsgrundlag;
	global $momssats;
	global $nextside;
	global $printerid;
	global $ref;
	global $side;
	global $subtotal;
	global $sum;
	global $transportsum;
	global $y;
	global $ya;
	global $mailantal;
	global $bruger_id;
	global $sprog_id;
	global $exec_path;
	global $vis_saet;
	global $brugsamletpris;

	
$fp1=NULL;$kommentarprint=NULL;$skjul_nul_lin=NULL;
$folgeseddel=0;$mailantal=0;$nomailantal=0;
$side_x=210;
$side_y=297;

$side_x=afrund($side_x*2.86,0);
$side_y=afrund($side_y*2.86,0);

$preview=NULL;
$initext="%!\n%%Creator: Saldi\n%%Pages: 1\n%%BoundingBox: 0 0 $side_x $side_y\n%%EndComments\n<< /PageSize [$side_x $side_y] >> setpagedevice\n";
#$initext="%!\n%%Creator: Saldi\n%%Pages: 1\n%%EndComments\n";

$form=array();

$q = db_SELECT("select box2,box14 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__);
$r=db_fetch_array($q);
$rabatvare_id=$r['box2']*1;
$brugsamletpris=$r['box14'];
if ($vis_saet) $brugsamletpris='on';

if ($id==-1){	# Saa er der flere fakturaer
	$ordre_antal = $_GET['ordre_antal'];
	$ordre_id = explode(",", $_GET['skriv']);
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
$initext=$initext.fread($initfil,$fsize);
fclose($initfil);

if ( ! file_exists("../temp/$db")) mkdir("../temp/$db", 0775);
$mappe="../temp/$db/$bruger_id"."_*";
system("rm -r $mappe");
$mappe="../temp/$db/".abs($bruger_id)."_".date("his");
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
	$fp1=fopen("$mappe/$printfilnavn","w");
}

for ($o=0; $o<$ordre_antal; $o++) {
	$fp=$fp1;
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
		$query = db_select("select afd,status,email,ordrenr,fakturanr,mail_fakt,pbs,art,ref,sprog,udskriv_til,mail_subj,mail_text,dokument,procenttillag from ordrer where id = '$ordre_id[$o]'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$status=$row['status'];
		$afd=$row['afd'];
		$art=$row['art'];
		$ref=$row['ref'];
		$ordrenr=$row['ordrenr'];
		if (!$udskriv_alle_til) $udskriv_til=$row['udskriv_til'];
		else $udskriv_til=$udskriv_alle_til;
		$fakturanr=$row['fakturanr'];
		$mail_fakt=$row['mail_fakt'];
		$email[0]=$row['email'];
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
				return("Fakturering afbrudt (Fakturanr mangler)");
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
	$bgr="bg";
	if ($formular==5 && file_exists("../logolib/$db_id/kreditnota_bg.pdf")) $bgr="kreditnota_bg";
	elseif ($formular>=3 && file_exists("../logolib/$db_id/faktura_bg.pdf")) $bgr="faktura_bg";
	elseif ($formular==2 && file_exists("../logolib/$db_id/ordrer_bg.pdf")) $bgr="ordrer_bg";
	elseif ($formular<=1 && file_exists("../logolib/$db_id/tilbud_bg.pdf")) $bgr="tilbud_bg";
	#cho "formular: $formular bg: $bgr<br>"; 
#exit();
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
		$r2 = db_fetch_array(db_select("select MAX(lev_nr) as lev_nr from batch_salg where ordre_id = $ordre_id[$o]",__FILE__ . " linje " . __LINE__));
		$lev_nr=$r2['lev_nr']*1;
	}
	if ($formular==3 || $formular==9) $udskriv_til='PDF';
	if ($mail_fakt && $formular!=3 && $udskriv_til!='PDF') {
		$mailantal++;
		if ($formular<=1) $pfnavn="tilbud".$ordrenr;
		if ($formular==2) $pfnavn="ordrebek".$ordrenr;
		if ($formular==4) $pfnavn="fakt".$fakturanr;
		if ($formular==5) $pfnavn="kn".$fakturanr;
		if ($formular==9) $pfnavn="plukliste".$ordrenr;
		if ($formular==12) $printfilnavn="forslag".$ordrenr;
		if ($formular==13) $printfilnavn="rekvisition.$ordrenr";
		if ($formular==14) $printfilnavn="lev_fakt.$ordrenr";
		$email[$mailantal]=$email[0];
		$mailsprog[$mailantal]=$formularsprog;
		$form_nr[$mailantal]=$formular;
		$pfliste[$mailantal]=$pfnavn;
		$pfnavn=$mappe."/".$pfnavn;
		$fp2=fopen("$pfnavn","w");
		$fp=$fp2;
	} else {
		$nomailantal++;
		if ($ordre_antal<=1) { #mere sige navn til udskrifter.
			if ($formular<=1) $printfilnavn="tilbud".$ordrenr;
			if ($formular==2) $printfilnavn="ordrebek".$ordrenr;
			if ($formular==3) $printfilnavn="flgs".$ordrenr."_".$lev_nr;
			if ($formular==4) $printfilnavn="fakt".$fakturanr;
			if ($formular==5) $printfilnavn="kn".$fakturanr;
			if ($formular==9) $printfilnavn="plukliste".$ordrenr;
			if ($formular==12) $printfilnavn="forslag".$ordrenr;
			if ($formular==13) $printfilnavn="rekvisition.$ordrenr";
			if ($formular==14) $printfilnavn="lev_fakt.$ordrenr";
			$pfnavn=$mappe."/".$printfilnavn;
			$fp=fopen("$pfnavn","w");
		}
	}
	$side=1;
	if ($logoart=='PS')	fwrite($fp,$logo);
	fwrite($fp,$initext);
	$rabat[0]=formulartekst($ordre_id[$o],$formular,$formularsprog);
	if ($ordre_id[$o]){
		$id=$ordre_id[$o];
		$x=0;
		$sum=0;
		$moms=0;
		$momssum=0;
		$tmp=0;
		$saetnr=0;
#cho "$formular<br>";
#xit;
		if ($preview) {
			for ($x=1;$x<=22;$x++){
				$posnr[$x]=$x;
				$varenr[$x]=utf8_iso8859('Varenr').$x;
				$projekt[$x]=utf8_iso8859('Projektnr');
				$beskrivelse[$x]=utf8_iso8859('Eksempel på en linjetekst');
			}
		} else {
		if ($brugsamletpris) {
				
			$r=db_fetch_array(db_select("select varenr from ordrelinjer where ordre_id = $ordre_id[$o] and vare_id='$rabatvare_id'",__FILE__ . " linje " . __LINE__));
			if ($r['varenr']){
				$rabatvarenr=$r['varenr'];
				$rvnr=1;
			} else $rvnr=0;
		}
#		if ($rvnr) $qtxt="select * from ordrelinjer where ordre_id = '$ordre_id[$o]' order by saet,posnr";
#		else 
		#20150302 
		if ($brugsamletpris && $formular==3) $qtxt="select * from ordrelinjer where ordre_id = '$ordre_id[$o]' and samlevare != 'on' and varenr != '$rabatvarenr' order by posnr";
		else $qtxt="select * from ordrelinjer where ordre_id = '$ordre_id[$o]' order by posnr";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while($row = db_fetch_array($q)){
			if ($row['posnr']>0 && (!$row['samlevare'] || !is_numeric($row['samlevare'])) && (!in_array($row['posnr'],$posnr) || ($formular!=3))){
#					if ($x>=1 && $row['saet'] && !$saet[$x]) {
#						$x++;
#						$beskrivelse[$x]=" ";
#						$saet[$x]=0;
#						$samlevare[$x]=0;
#					} elseif ($x>=1 && $saet[$x] && $samlevare[$x]) {
#						$x--;
#					}
					$x++;
					$posnr[$x]=trim($row['posnr']);
					$varenr[$x]=trim($row['varenr']);
					$lev_varenr[$x]=trim($row['lev_varenr']);
					$projekt[$x]=($row['projekt']);
					$beskrivelse[$x]=stripslashes(trim($row['beskrivelse']));
					if ($varenr[$x]){
						$vare_id[$x]=$row['vare_id'];
						$linje_id[$x]=$row['id'];
						$antal[$x]=$row['antal']*1;
						$leveres[$x]=$row['leveres']*1;
						$dkantal[$x]=str_replace(".",",",$antal[$x]);
						$momsfri[$x]=$row['momsfri'];
						$omvbet[$x]=$row['omvbet'];
						$saet[$x]=$row['saet'];
						$samlevare[$x]=$row['samlevare'];
#cho "$posnr[$x] $saet[$x] $samlevare[$x]<br>";
						$varemomssats[$x]=$row['momssats']*1;
						if (!$momsfri[$x] && !$varemomssats[$x]) $varemomssats[$x]=$momssats;
						if ($varemomssats[$x] > $momssats) $varemomssats[$x]=$momssats;
						list($a,$b)=explode(",",$dkantal[$x]);
						if ($b) {
							$dkantal[$x]="$a,$b";
						} else $dkantal[$x]=$a;
						if ($formular==5){
							$antal[$x]=$antal[$x]*-1;
							$dkantal[$x]=str_replace("-","",$dkantal[$x]);
						}
						if ($formular==3 || $formular==9){
							if (in_array('lokation',$variabel)) {
								$r2=db_fetch_array(db_select("select location from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
								if ($r2['location']) {
									if ($afd && strstr($r2['location'],"|")) { # 20150417 Ændret strpos til strstr
										$lokation=array();
										$lokation=explode("|",$r2['location']);
										
										$beskrivelse[$x].=chr(9).$lokation[$afd-1];
									} else $beskrivelse[$x].=chr(9).$r2['location'];
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
#						if ($rvnr) {
#							if ($varenr[$x]=='R') list($pris[$x])=explode("|",$row['lev_varenr']);
#							$rabat[$x]=0;
#						} else {
							$rabat[$x]=$row['rabat'];
#						}
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
					$beskrivelse[$x]=var2str($beskrivelse[$x],$ordre_id[$o],$posnr[$x],$varenr[$x],$dkantal[$x],$enhed[$x],$pris[$x],$procent[$x],$serienr[$x],$varemomssats[$x],$rabat[$x]);
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
				$enhed[$x]='';
				$rabat[$x]=$rabat[$y];
				($incl_moms && !$b2b)?$tmp=$m_rabat[$y]+$m_rabat[$y]*$varemomssats[$y]/100:$tmp=$m_rabat[$y];
				$pris[$x]=$tmp*-1;
				$rabatpct=afrund($tmp*100/usdecimal($pris[$y]),2);
				$l_sum[$x]=afrund($tmp*-$antal[$x],3);
				$linjesum[$x]=$l_sum[$x];
#				$linjesum[$x]=dkdecimal($l_sum[$x]);
				if ($varemomssats[$y] > 0) $linjemoms[$x]=$varemomssats[$y]*$linjesum[$x]*0.01;
				else $linjemoms[$x]=$varemomssats[$y]*$linjesum[$x];
				$sum+=$l_sum[$x];
				if ($momsfri[$y]!='on' && !$omvbet[$x]) {
					$moms+=afrund($m_rabat[$y]*$antal[$x]*$varemomssats[$y]/-100,3);
					$momssum=$momssum+afrund($m_rabat[$y]*-$antal[$x],2);
				}
				($rabatart[$x]=='amount')?$beskrivelse[$x]=findtekst(466,$sprog_id):$beskrivelse[$x]=findtekst(467,$sprog_id);
				$beskrivelse[$x]=str_replace('$rabatpct',$rabatpct,$beskrivelse[$x]);
			} 
			$linjeantal=$x;
		}
		if ($status<3 && $procenttillag>0) {
			$r=db_fetch_array(db_select("select box13 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
			list($tmp,$procentvare)=explode(chr(9),$r['box13']);
			if ($procentvare && $r=db_fetch_array(db_select("select beskrivelse from varer where varenr='$procentvare'",__FILE__ . " linje " . __LINE__))){
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
		$y=$ya;
		$y_tjek=$y;
		$Opkt=$y-($antal_ordrelinjer*$linjeafstand);
		for ($x=1;$x<=$linjeantal; $x++) { 
			$beskrivelse[$x]=str_replace('$procenttillæg;',dkdecimal($procenttillag),$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$procenttillæg',dkdecimal($procenttillag),$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$antal;',$dkantal[$x],$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$pris;',$pris[$x],$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$linjesum;',$linjesum[$x],$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$varenr;',$varenr[$x],$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$rabat;',$rabat[$x],$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$procent;',$procent[$x],$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$antal',$dkantal[$x],$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$pris',$pris[$x],$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$linjesum',$linjesum[$x],$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$varenr',$varenr[$x],$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$rabat',$rabat[$x],$beskrivelse[$x]);
			$beskrivelse[$x]=str_replace('$procent',$procent[$x],$beskrivelse[$x]);
			if ($charset=="utf-8") {
				$varenr[$x]=utf8_iso8859($varenr[$x]);
				$lev_varenr[$x]=utf8_iso8859($lev_varenr[$x]);
				$beskrivelse[$x]=utf8_iso8859($beskrivelse[$x]);
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
				$linjesum[$x]=dkdecimal($linjesum[$x]);
				$linjemoms[$x]=dkdecimal($linjemoms[$x]);
				$pris[$x]=dkdecimal($pris[$x]);
				$rabat[$x]=dkdecimal($rabat[$x]);
				$procent[$x]=dkdecimal($procent[$x]);
#			}
			$transportsum=$transportsum+$l_sum[$x-1];
			$skriv=0;
			if ($kommentarprint=='on'||$formular!=3||$varenr[$x]) $skriv=1; #Fordi tekst uden varenr ikke skal med paa foelgesedlen med mindre det er angivet i "formularprint"; 
#			if ($saet[$x] && $samlevare[$x]) $skriv=0; #Fordi tekst uden varenr ikke skal med paa foelgesedlen med mindre det er angivet i "formularprint"; 
			if ($skriv) {
				for ($z=1; $z<=$var_antal; $z++) {
					if (substr($variabel[$z],0,8)=="fritekst") {
						$tmp=substr($variabel[$z],9);
						$svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$tmp", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					}
					if ($y_tjek!=$y) { #Det først skrevne felt på en linje bliver "ædt" ved sideskift. Derfor dette dummy felt.
						$svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
						$y_tjek=$y;
					}
					if ($variabel[$z]=="posnr") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$posnr[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="varenr") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$varenr[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular"); # ellers kommer varenummer ikke med paa 1. linje paa side 2 . og 3
					elseif ($variabel[$z]=="lev_varenr") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$lev_varenr[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular"); # ellers kommer varenummer ikke med paa 1. linje paa side 2 . og 3
					elseif ($variabel[$z]=="leveres") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$leveres[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="projekt") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$projekt[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="antal") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$dkantal[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular"); #ellers kommer antal ikke med paa 1. linje paa side 2 . og 3
					elseif ($variabel[$z]=="lev_antal") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$lev_antal[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="tidl_lev") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$tidl_lev[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="lev_rest") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$rest[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="pris") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$pris[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="enhed") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$enhed[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="momssats") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$varemomssats[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="rabat") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$rabat[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="procent") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$procent[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="linjemoms") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$linjemoms[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="varemomssats") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$varemomssats[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="linjesum") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$linjesum[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]!="beskrivelse" && $variabel[$z]!="lokation") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$variabel[$z]", "fritekst_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="beskrivelse") $skriv_beskriv[$x]=$z;
				}
				if ($z=$skriv_beskriv[$x]) $y2=ombryd("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$beskrivelse[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]",$laengde[$z],$formular,$linjeafstand);
				$y=$y2;
				if ($y==0) $y=$ya;
				$y=$y-$linjeafstand;
			}
		}
		if ($brugsamletpris) {
			$r=db_fetch_array(db_select("select sum,moms from ordrer where id = '$id'"));
			$sum=$r['sum'];
			$moms=$r['moms'];
		}
		$momssum=afrund($momssum,2);
		$sum=afrund($sum,2);
		$ialt=dkdecimal($sum+$moms);
		$momsgrundlag=dkdecimal($momssum);		
		$moms=dkdecimal($moms);
		$sum=dkdecimal($sum);

		}
	if ($id) find_form_tekst($id, 'S', $formular,0,$linjeafstand,""); # Sum paa sidste side.

	if ($ordre_id[$o]) bundtekst($ordre_id[$o]); # Uden denne skrives kun  side 1
	if ($mail_fakt) fclose($fp2);
	#cho "$o A $ordre_id[$o] $mappe/$pfliste[$o]<br>";
}
fclose($fp);
#cho "$mailantal -> $nomailantal<br>";
#xit;
if ($mailantal>0) {
	ini_set("include_path", ".:../phpmailer");
	require("class.phpmailer.php");
	if (!isset($exec_path)) $exec_path="/usr/bin";
	for($x=1;$x<=$mailantal;$x++) {
	#cho "$x B $ordre_id[$x] $mappe/$pfliste[$x]<br>";
		print "<!-- kommentar for at skjule uddata til siden \n";
		system ("$exec_path/ps2pdf $mappe/$pfliste[$x] $mappe/$pfliste[$x].pdf");
		print "--> \n";
		if ($logoart=='PDF') {
			print "<!-- kommentar for at skjule uddata til siden \n";
			$out=$mappe."/".$pfliste[$x]."x.pdf";
			system ("$exec_path/pdftk $mappe/$pfliste[$x].pdf background ../logolib/$db_id/$bgr.pdf output $out");
#			unlink ("$mappe/$pfliste[$x].pdf");
			system  ("mv $out $mappe/$pfliste[$x].pdf");
			print "--> \n";
		}
		$o=$x-1;
		$svar=send_mails("$ordre_id[$o]","$mappe/$pfliste[$x].pdf",$email[$x],$mailsprog[$x],$form_nr[$x],"$mail_subj","$mail_text","$mail_bilag",$x);
#		return($svar);
	}
}
#if ($mailantal>0) include("mail_faktura.php");
if ($nomailantal>0) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$mappe/$printfilnavn&amp;id=$id&amp;udskriv_til=$udskriv_til&amp;art=$art&amp;bgr=$bgr\">";#20131202
elseif ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
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
	global $fp;
	global $side;
#	global $formularsprog;

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
	
#cho "select * from formularer where formular = '$formular' and art = '1' and beskrivelse != 'LOGO' and lower(sprog)='$formularsprog'<br>";
	$query = db_select("select * from formularer where formular = '$formular' and art = '1' and beskrivelse != 'LOGO' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
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

		if ($xa) fwrite($fp," $xa $ya moveto $xb $yb lineto $lw setlinewidth $color stroke \n");
	}

	if ($id) find_form_tekst($id, 'A', $formular,0,0,0);

return $rabat;
print "<!--function formulartekst slut-->";
}}

if (!function_exists('bundtekst')) {
function bundtekst($id) {
print "<!--function bundtekst start-->";
global $logo;
global $logoart;
global $fp;
global $nextside;
global $side;
global $y;
global $ya;
global $linjeafstand;

$y=$ya-$linjeafstand;
$side=$side+1;

#cho "side $side<br>";

if ($logoart!='EPS') fwrite($fp,"showpage\n");
else fwrite($fp, $logo);
#cho "Bundtekst returnerer: $y<br>";
return ($y); #indsat 2011.01.27 grundet tomme sider v. sideskift i kommentar. saldi_2 ordre id 3310
# fwrite($fp,"showpage");
print "<!--function bundtekst slut-->";
}}

if (!function_exists('send_mails')) {
function send_mails($ordre_id,$filnavn,$email,$mailsprog,$form_nr,$subjekt,$mailtext,$mailbilag,$mailnr) {
print "<!--function send_mails start-->";
	global $db;
	global $db_id;
	global $mailantal;
	global $charset;
	global $formularsprog;
	global $webservice;
	global $ansat_id;
	global $bruger_id;
	global $exec_path;
	global $id; // hent 'mail_bilag' fra ordrer + leveringsaddr.
	global $formular; // bruges til valg af bilag


	#cho "OID $ordre_id,Filnavn: $filnavn,email: $email,$mailsprog,$form_nr,$subjekt,$mailtext,$mailbilag,$mailnr<br>";;
	
	$brugermail='';
	
	$id*=1; #21040423
	$r=db_fetch_array(db_select("select mail_bilag,lev_addr1,lev_postnr,lev_bynavn,sag_id from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$mail_bilag=$r['mail_bilag'];
	$lev_addr1=$r['lev_addr1']; # 2013.11.27 Henter leveringsaddr.
	$lev_postnr=$r['lev_postnr'];
	$lev_bynavn=$r['lev_bynavn'];
	$sag_id=$r['sag_id']; # 2013.11.27 Henter sags_id 
	//$mail_bilag='on'; // skal hentes fra ordrer
	
	#2013.11.19 Her finder vi hvilket bilag der skal hentes
	if($formular<=1) $bilag="tilbud_bilag"; 
	if($formular==2) $bilag="ordrer_bilag";
	if($formular==4) $bilag="faktura_bilag";
	
	if(!file_exists("../logolib/$db_id/$bilag.pdf")) { #2013.11.21 Hvis fil(bilag) IKKE eksistere sættes $mail_bilag til NULL, selvom $mail_bilag er sat til 'on'
		$mail_bilag=NULL;
	}
	
	$emails=array();
	$email=str_replace(",",";",$email);
	if (strpos($email,";")) {
		$emails=explode(";",$email);
	} else $emails[0]=$email;
	
#	$form_nr*=1;
	$q=db_select("select * from formularer where formular='$form_nr' and art='5' and lower(sprog)='".strtolower($formularsprog)."'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!$subjekt && $r['xa']=='1') $subjekt=$r['beskrivelse'];
		elseif (!$mailtext && $r['xa']=='2') $mailtext=$r['beskrivelse'];
		elseif ($r['xa']=='3') $bilagnavn=$r['beskrivelse']; #2013.11.21 Finder bilag-navn
	}
	($bilagnavn)?$bilagnavn=$bilagnavn:$bilagnavn="Bilag"; #2013.11.21 Hvis bilag-navn er tom, insættes 'Bilag' som navn
	if ($sag_id) $subjekt=$subjekt." vedr.: $lev_addr1, $lev_postnr $lev_bynavn"; #2013.11.27 Her tilføjes leveringsaddr. til subjekt hvis der er sag_id
	
	$row = db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	$afsendermail=$row['email'];
	$afsendernavn=$row['firmanavn'];
	($row['felt_1'])?$smtp=$row['felt_1']:$smtp='localhost';
	($row['felt_2'])?$smtp_user=$row['felt_2']:$smtp_user=NULL;
	($row['felt_3'])?$smtp_pwd=$row['felt_3']:$smtp_pwd=NULL;

	if ($row['mailfakt'] && $ansat_id) {
		$r = db_fetch_array(db_select("select * from ansatte where id='$ansat_id'",__FILE__ . " linje " . __LINE__));
		$brugermail=$r['email'];
	}
	if (!$afsendermail || !$afsendernavn) {
		if (!$webservice) {
			print "<BODY onLoad=\"javascript:alert('Firmanavn eller e-mail for afsender ikke udfyldt.\\nSe (Indstillinger -> stamdata).\\nMail ikke afsendt!')\">";
		}
		return("Missing sender mail");
	}

	$fakturanavn=basename($filnavn);
	
	if ($mailbilag && $ordre_id) {
		$ftpfilnavn="bilag_".$ordre_id;
		$r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
			if($box6=$r['box6']) {
			$mappe='bilag';
			$undermappe="ordrer";
			$bilagfilnavn="bilag_".$bilag_id;
			$google_docs=$r['box7'];
			$fra="../owncloud/".$db."/".$mappe."/".$undermappe."/".$ftpfilnavn;
			$til="../temp/".$db."/".$mailbilag;
			system ("cp '$fra' '$til'\n");
		} else {
			$r=db_fetch_array(db_select("select * from grupper where art='FTP'",__FILE__ . " linje " . __LINE__));
			$box1=$r['box1'];
			$box2=$r['box2'];
			$box3=$r['box3'];
			$mappe=$r['box4'];
			$undermappe="ordrer";
			$ftpfilnavn="bilag_".$ordre_id;
			$fp=fopen("../temp/$db/ftpscript.$bruger_id","w");
			if ($fp) {
			fwrite ($fp, "cd $mappe\ncd $undermappe\nget $ftpfilnavn\nbye\n");
			}
			fclose($fp);
			$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.$bruger_id > ftplog\nmv \"$ftpfilnavn\" \"$mailbilag\"\n";
			system ($kommando);
		}
	}
	#cho "B $mailbilag<br>";
	
	if ($charset=="UTF-8" || $webservice) {
		$subjekt=utf8_decode($subjekt);
		$mailtext=utf8_decode($mailtext);
		$bilagnavn=utf8_decode($bilagnavn);
		$afsendernavn=utf8_decode($afsendernavn);
	}
	$mail = new PHPMailer();
	$mail->IsSMTP();                                   // send via SMTP

	$mail->Host  = $smtp; // SMTP servers 
	if ($smtp_user) {
		$mail->SMTPAuth = true;     // turn on SMTP authentication
		$mail->Username = $smtp_user;  // SMTP username
		$mail->Password = $smtp_pwd; // SMTP password
	} else $mail->SMTPAuth = false;
#	if (strpos($_SERVER['SERVER_NAME'],'saldi.dk')) $mail->Sender = 'mailer@saldi.dk';
	if (strpos($_SERVER['SERVER_NAME'],'saldi.dk')) { #20121016
		if ($_SERVER['SERVER_NAME']=='ssl.saldi.dk') $mail->From = $db.'@ssl.saldi.dk'; #20130731
		elseif ($_SERVER['SERVER_NAME']=='ssl2.saldi.dk') $mail->From = $db.'@ssl2.saldi.dk'; #20130731
		else $mail->From = 'kanikkebesvares@saldi.dk';
		$mail->FromName = $afsendernavn;
	} else {
		$mail->From = $afsendermail;
		$mail->FromName = $afsendernavn;
	}
	$mail->FromName = $afsendernavn;
	$mail->AddReplyTo($afsendermail,$afsendernavn);
	$mail->AddAddress($emails[0]);
	for ($i=1;$i<count($emails);$i++) $mail->AddCC($emails[$i]); 
	$mail->AddBCC($afsendermail);
	if ($brugermail) $mail->AddBCC($brugermail);
	#	$mail->AddAddress("ellen@site.com");               // optional name
	#	$mail->AddReplyTo("info@site.com","Information");

	$mail->WordWrap = 50;  // set word wrap
#	$mail->AddAttachment("$mappe/mailtext.html");
	$mail->AddAttachment("$filnavn","$fakturanavn","base64","application/pdf");      // attachment
	if ($mailbilag) $mail->AddAttachment("../temp/$db/$mailbilag","$mailbilag","base64","application/pdf");      // attachment
	if ($mail_bilag) $mail->AddAttachment("../logolib/$db_id/$bilag.pdf","$bilagnavn.pdf"); // kun hvis checkbox er 'on'.
	#	$mail->AddAttachment("/tmp/image.jpg", "new.jpg");
	$mail->IsHTML(true);                               // send as HTML

	$ren_text=html_entity_decode($mailtext,ENT_COMPAT,$charset);
	$ren_text=str_replace("<br>","\n",$ren_text);
	$ren_text=str_replace("<b>","*",$ren_text);
	$ren_text=str_replace("</b>","*",$ren_text);
	$ren_text=str_replace("<hr>","------------------------------",$ren_text);

	$mail->Subject  =  "$subjekt";
	$mail->Body     =  "$mailtext";
	$mail->AltBody  =  "$ren_text";

	if(!$mail->Send()){
#		#cho "Fejl i afsendelse til $email<p>";
# 		#cho "Mailer Error: " . $mail->ErrorInfo;
 		$svar = "Mailer Error: " . $mail->ErrorInfo;
		return ($svar);
#		exit;
	}
	if (!$webservice) {
		if ($mailantal<=1) {
			if ($brugermail) $tekst="Mail sendt til $email\\nBCC til $afsendermail\\nBCC til $brugermail.";
			else $tekst="Mail sendt til $email\\nBCC til $afsendermail.";
			print "<BODY onLoad=\"javascript:alert('$tekst')\">";
		}	else print "Mail sendt til $email<br>";
		if ($mailantal>1 && $mailnr==$mailantal) {
			if ($brugermail) $tekst="$mailantal mails sendt\\nBCC til $afsendermail\\nBCC til $brugermail.";
			else $tekst="$mailantal mails sendt\\nBCC til $afsendermail.";
			print "<BODY onLoad=\"javascript:alert('$tekst')\">";	
		}
	}
	return("Mail sent to $email");
print "<!--function send_mails slut-->";
}}

if (!function_exists('find_saldo')) {
	function find_saldo($id) {
		print "<!--function find_saldo start-->";
		$r=db_fetch_array(db_select("select konto_id,ordredate,fakturadate from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
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


# Konverterer tekst fra UTF til ISO 8859-X - i første omgang kun for X=15, men senere mere generelt
# Der kan angives hvilken udgave af ISO 8859 der skal konverteres til og ukendte tegn bliver 
# erstattet af tegnet ? (spørgsmålstegn). Angives 0 som udgave, forsøger funktionen selv at finde 
# den udgave, som passer bedst. Angives ingen benyttes udgave 15. 
# 2014-04-14 Claus Agerskov
if (!function_exists('utf8_iso8859')) {
function utf8_iso8859($utf8tekst, $iso8859=15) {
	$utf8tekst=str_replace("¤","€",$utf8tekst);
	$utf8tekst=str_replace("¦","|",$utf8tekst);
	$utf8tekst=str_replace("¨",chr(34),$utf8tekst);
	$utf8tekst=str_replace("´","'",$utf8tekst);
	$utf8tekst=str_replace("¸",",",$utf8tekst);
	$utf8tekst=str_replace("¼","1/4",$utf8tekst);
	$utf8tekst=str_replace("½","1/2",$utf8tekst);
	$utf8tekst=str_replace("¾","3/4",$utf8tekst);
/*
	$pos1=strpos($utf8tekst,chr(194));
	$pos2=strpos($utf8tekst,chr(180));
	while ($pos1 == $pos2-1) {
		$a=substr($utf8tekst,0,$pos1);
		$b=substr($utf8tekst,$pos1+2);
		$utf8tekst=$a."'".$b;
		$pos1=strpos($utf8tekst,chr(194));
		$pos2=strpos($utf8tekst,chr(180));
	}
*/	
#	print "<!--function utf8_iso8859 start-->";
	if ( strpos($iso8859, "-", 4) !== false ) $iso8859=substr($iso8859, (strpos($iso8859, "-", 4)+1));
  $isotekst=iconv("UTF-8", "ISO-8859-15//TRANSLIT", $utf8tekst);
  return ($isotekst);
#	print "<!--function utf8_iso8859 slut-->";
	}
}

?>
