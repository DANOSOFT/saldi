<?php
ob_start(); //Starter output buffering
// ---------------------includes/formfunk.php ------patch 3.2.9----2013-05-08--------------
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2013 DANOSOFT ApS
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


function skriv($str, $fed, $italic, $color, $tekst, $tekstinfo, $x, $y, $format, $form_font,$formular) {
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

#cho "$str,$fed,$italic,$color,$tekst,$tekstinfo,$x,$y,$format,$form_font,$formular<br>";
	
#if ($tekst == "printer flytning, firewall opsætning, 4") echo "skriv $x $y $tekst<br>";

	if (!isset($incr_x)) $incr_x=0;
	if (!isset($incr_y)) $incr_y=0;

	$format=strtoupper($format);
	$tekst=str_replace("(","\\(",$tekst);
	$tekst=str_replace(")","\\)",$tekst);

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

	if (($fed=='on')&&($italic!='on')) {$form_font=$form_font.'-Bold-ISOLatin1 findfont';}
	elseif (($fed!='on')&&($italic=='on')) {$form_font=$form_font.'-Italic-ISOLatin1 findfont';}
	elseif (($fed=='on')&&($italic=='on')) {$form_font=$form_font.'-BoldItalic-ISOLatin1 findfont';}
	elseif ($form_font=="Times") {$form_font=$form_font.'-Roman-ISOLatin1 findfont';}
	else {$form_font=$form_font.'-ISOLatin1 findfont';}
	if (strstr($tekstinfo, 'ordrelinjer')) {
		list ($tekstinfo, $Opkt)=explode("_",$tekstinfo,2);
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
					if ($charset=="UTF-8") $ny_streng=utf8_decode($ny_streng);
				}
				if ($ny_streng) {
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
	if ($charset=="UTF-8") $tekst=utf8_decode($tekst);
	# herunder y2/2.86>Opkt tilfoejet 20110127 grundet 1 ordrelinje på ny side ogsaa skrives nder ordrelinjefelt v. ombrudt kommentar saldi_2 ordre id 3310

	if ($x1 && $tekst1 && $y2/2.86>$Opkt) {
		fwrite($fp,"/$form_font\n$str scalefont\nsetfont\nnewpath\n$x1 $y2 moveto (".$tekst1.") $format show\n");
	}

	if ($x && $tekst && $y2/2.86>$Opkt) {
		fwrite($fp,"/$form_font\n$str scalefont\nsetfont\nnewpath\n$x $y2 moveto (".$tekst.") $format show\n");
	}
	return $y;
} #endfunc skriv();

function ombryd($str, $fed, $italic, $color, $tekst, $tekstinfo, $x, $y, $format, $form_font, $laengde,$formular,$linespace) {
#cho "text $tekst<br>";

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
#cho "returnerer $y<br>";
	return $y;
}

function find_form_tekst($id, $sidetekst, $formular,$y_pos,$linjeafstand,$pos_part) {
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

	$dk_transportsum=dkdecimal($transportsum);

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
#cho "$qtxt<br>";
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
				}
				elseif ($tabel=="forfalden" || $tabel=="rykker") {# $tabel=="rykker" indsat 14.04.08
					$r2 = db_fetch_array(db_select ("select * from varer where id IN (select xb from formularer where beskrivelse='GEBYR' and formular='$formular')",__FILE__ . " linje " . __LINE__));
					$gebyr=$r2['salgspris']*1;	
#cho "select * from varer where id IN (select yb from formularer where beskrivelse='GEBYR' and formular='$formular')<br>";
					$r2 = db_fetch_array(db_select ("select yb from formularer where beskrivelse='GEBYR' and formular='$formular'",__FILE__ . " linje " . __LINE__));
					$rentevare=$r2['yb']*1;	

					$r2=db_fetch_array(db_select("select * from ordrelinjer where ordre_id = '$id' and vare_id = '$rentevare'",__FILE__ . " linje " . __LINE__));
					$rente=$r2['pris'];
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
						$forfalden+=$amount;
					}

					$sum=dkdecimal($forfalden);
					$gebyr=dkdecimal($gebyr);
					$rente=dkdecimal($rente);
				}
				if (($tabel!="formular")&&($tabel!="forfalden")&&($tabel!="rykker")) {
					$r2 = db_fetch_array($q2);
					if (strstr($variabel, 'date')) {
						$streng[$x]=dkdato($r2[$variabel]);
					}
					elseif ($variabel=='momssats') {
						list($streng[$x],$tmp)=explode(",",dkdecimal($r2[$variabel]));
						$tmp=$tmp*1;
						if ($tmp) $streng[$x].=",".$tmp;
					}	else {
						$streng[$x]=$r2[$variabel];}
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
}

function find_forfaldsdato($id) {
	$query = db_select("select fakturadate, betalingsbet, betalingsdage from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$fakturadate=$row[fakturadate];
	$betalingsbet=$row[betalingsbet];
	$betalingsdage=$row[betalingsdage];

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
}
function tjek_lev_addr($variabel, $id) {
	 if ($variabel=="lev_navn"){$tmp="firmanavn";}
	 else {$tmp=substr($variabel, 4);}
	 $query = db_select("select $tmp from ordrer where id=$id and lev_navn!='' and lev_addr1!='' and lev_postnr!='' and lev_bynavn!=''",__FILE__ . " linje " . __LINE__);
	 if ($row=db_fetch_array($query)) {return $variabel;}
	 else {return $tmp;}
}

function modulus_10($id) {
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
}
function formularprint($id,$formular,$lev_nr,$charset,$udskriv_til) {
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

$fp1=NULL;$kommentarprint=NULL;$skjul_nul_lin=NULL;
$folgeseddel=0;$mailantal=0;$nomailantal=0;
$side_x=210;
$side_y=297;

$side_x=afrund($side_x*2.86,0);
$side_y=afrund($side_y*2.86,0);

$preview=NULL;
#$initext="%!\n%%Creator: Saldi\n%%Pages: 1\n%%BoundingBox: 0 0 $side_x $side_y\n%%EndComments\n<< /PageSize [$side_x $side_y] >> setpagedevice\n";
$initext="%!\n%%Creator: Saldi\n%%Pages: 1\n%%EndComments\n";

$form=array();

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
	if ($formular<=1) $printfilnavn="tilbud";
	if ($formular==2) $printfilnavn="ordrebek";
	if ($formular==3) $printfilnavn="flg_seddel";
	if ($formular==4) $printfilnavn="faktura";
	if ($formular==9) $printfilnavn="plukliste";
	$fp1=fopen("$mappe/$printfilnavn","w");
}
for ($q=0; $q<$ordre_antal; $q++) {
	$fp=$fp1;
	if (isset($form[$q])) $formular=$form[$q];
	$varenr=array();$leveres=array(); $vare_id=array(); $linje_id=array(); $antal=array(); $tidl_lev=array(); $rest=array();
	$enhed=array(); $rabat=array(); $momssats=array(); $pris=array(); $l_sum=array(); $linjesum=array();
	$sum='';$transportsum=0;
 	if ($preview) {
#echo "p $preview<br>";
#exit;
		$ref='Egen ref';
		$ordrenr='Ordrenummer';
		$fakturanr='Fakturanummer';
		$mail_fakt='';
		$email[0]='Kundens email';
		$pbs='';
	} else {
		$query = db_select("select status,email,ordrenr,fakturanr,mail_fakt,pbs,art,ref,sprog,udskriv_til from ordrer where id = '$ordre_id[$q]'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$ref=$row['ref'];
		$ordrenr=$row['ordrenr'];
		$udskriv_til=$row['udskriv_til'];
		$fakturanr=$row['fakturanr'];
		$mail_fakt=$row['mail_fakt'];
		$email[0]=$row['email'];
		$pbs=$row['pbs'];
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
	
		$r=db_fetch_array(db_SELECT("select adresser.gruppe from ordrer,adresser where ordrer.id = '$ordre_id[$q]' and adresser.id=ordrer.konto_id",__FILE__ . " linje " . __LINE__));
		$gruppe=$r['gruppe'];
		$r=db_fetch_array(db_select("select box8 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
		$b2b=$r['box8'];
	}
	print "<!-- kommentar for at skjule uddata til siden \n";
	if (!file_exists("../logolib/$db_id")) mkdir("../logolib/$db_id"); 
	if (system("which pdftk") && file_exists("../logolib/$db_id/bg.pdf")) {
		$logoart='PDF';
	} elseif ($udskriv_til=='PDF-tekst') {
		$logoart='PDF';
	} elseif (file_exists("../logolib/$db_id/$formular.ps")) {
		$logo="../logolib/$db_id/$formular.ps";
		$logoart='PS';
	} elseif (file_exists("../logolib/$db_id/bg.ps")) {
		$logo="../logolib/$db_id/bg.ps";
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
		$r2 = db_fetch_array(db_select("select MAX(lev_nr) as lev_nr from batch_salg where ordre_id = $ordre_id[$q]",__FILE__ . " linje " . __LINE__));
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
			$pfnavn=$mappe."/".$printfilnavn;
			$fp=fopen("$pfnavn","w");
		}
	}
	$side=1;
	if ($logoart=='PS')	fwrite($fp,$logo);
	fwrite($fp,$initext);
	$rabat[0]=formulartekst($ordre_id[$q],$formular,$formularsprog);
	if ($ordre_id[$q]){
		$id=$ordre_id[$q];
		$x=0;
		$sum=0;
		$moms=0;
		$momssum=0;
		$tmp=0;
#cho "$formular<br>";
#exit;
		if ($preview) {
			for ($x=1;$x<=22;$x++){
				$posnr[$x]=$x;
				$varenr[$x]=utf8_decode('Varenr').$x;
				$projekt[$x]=utf8_decode('Projektnr');
				$beskrivelse[$x]=utf8_decode('Eksempel på en linjetekst');
			}
		} else {	
			$query = db_select("select * from ordrelinjer where ordre_id = $ordre_id[$q] order by posnr",__FILE__ . " linje " . __LINE__);
			while($row = db_fetch_array($query)){
				if ($row['posnr']>0 && (!$row['samlevare'] || !is_numeric($row['samlevare'])) && (!in_array($row['posnr'],$posnr) || ($formular!=3))){
					$x++;
#cho "Antal $antal[$x]<br>";
					$posnr[$x]=trim($row['posnr']);
					$varenr[$x]=trim($row['varenr']);
					$projekt[$x]=($row['projekt']);
					$beskrivelse[$x]=stripslashes(trim($row['beskrivelse']));
					if ($charset=="utf-8") {
						$varenr[$x]=utf8_decode($varenr[$x]);
						$beskrivelse[$x]=utf8_decode($beskrivelse[$x]);
					}
					if (strpos($beskrivelse[$x],"\$ultimo")||strpos($beskrivelse[$x],"\$maaned")||strpos($beskrivelse[$x],"\$aar")){
						$beskrivelse[$x]=var2str($beskrivelse[$x],$ordre_id[$q]);
					}
					if ($varenr[$x]){
						$vare_id[$x]=$row['vare_id'];
						$linje_id[$x]=$row['id'];
						$antal[$x]=$row['antal']*1;
						$leveres[$x]=$row['leveres']*1;
#cho "$beskrivelse[$x] $leveres[$x]<br>"; 
						$dkantal[$x]=str_replace(".",",",$antal[$x]);
						$momsfri[$x]=$row['momsfri'];
						$varemomssats[$x]=$row['momssats']*1;
						if (!$momsfri[$x] && !$varemomssats[$x]) $varemomssats[$x]=$momssats;
						if ($varemomssats[$x] > $momssats) $varemomssats[$x]=$momssats;
						list($a,$b)=explode(",",$dkantal[$x]);
#						$b=$b*1; # remmet 20100729 - 0,5 og 0,05 bliver begge til 0,05
						if ($b) {
#						if ($b<1) $b="0".$b; # remmet 20100729 - 0,5 og 0,05 bliver begge til 0,05
							$dkantal[$x]="$a,$b";
						} else $dkantal[$x]=$a;
						if ($formular==5){
							$antal[$x]=$antal[$x]*-1;
							$dkantal[$x]=str_replace("-","",$dkantal[$x]);
						}
						if ($formular==3){
							$lev_antal[$x]=0;
							$q2 = db_select("select antal from batch_salg where linje_id = $linje_id[$x] and lev_nr = $lev_nr",__FILE__ . " linje " . __LINE__);
							while ($r2 = db_fetch_array($q2)){$lev_antal[$x]=$lev_antal[$x]+$r2['antal'];}
							$tidl_lev[$x]=0;
							$q2 = db_select("select antal from batch_salg where linje_id = $linje_id[$x] and lev_nr < $lev_nr",__FILE__ . " linje " . __LINE__);
							while ($r2 = db_fetch_array($q2)){$tidl_lev[$x]=$tidl_lev[$x]+$r2['antal'];}
							$rest[$x]=$antal[$x]-$lev_antal[$x]-$tidl_lev[$x];
						}
						$enhed[$x]=$row['enhed'];
						$pris[$x]=dkdecimal($row['pris']);
						$rabat[$x]=dkdecimal($row['rabat']);
						($row['rabat']!=0)?$m_rabat[$x]=0:$m_rabat[$x]=$row['m_rabat']*1;
						$rabatart[$x]=$row['rabatart'];
						if ($rabatart[$x]=="amount") {
							$l_sum[$x]=afrund(($row['pris']-$row['rabat'])*$antal[$x],2);
							$linjesum[$x]=dkdecimal(($row['pris']-$row['rabat'])*$antal[$x]);
							$linjemoms[$x]=dkdecimal($varemomssats[$x]/100 * (($row['pris']-$row['rabat'])*$antal[$x]) );
							} else {
							$l_sum[$x]=afrund($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row['rabat']/100),2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
							$linjesum[$x]=dkdecimal($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row['rabat']/100));
							$linjemoms[$x]=dkdecimal($varemomssats[$x]/100 * ($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row['rabat']/100)) );
						}
						if ($momsfri[$x]!='on') {
							$moms+=afrund($l_sum[$x]*$varemomssats[$x]/100,3); #Decimaltal aendret til 3 2010.12.17 grundet momsdiff (0,01 kr) i ordre id 371 i saldi_297
							$momssum+=afrund($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row['rabat']/100),2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
							if ($incl_moms && !$b2b) {
#cho "$varemomssats[$x] pris $row[pris]<br>";
								$tmp=afrund($row['pris']+$row['pris']*$varemomssats[$x]/100,2);
#							$tmp=afrund($row['pris']*=$varemomssats[$x]/100,2);
#cho "tmp $tmp<br>";
								if ($rabatart[$x]=="amount") $linjesum[$x]=dkdecimal(($tmp-$row['rabat'])*$antal[$x]);
								else $linjesum[$x]=dkdecimal($tmp*$antal[$x]-($tmp*$antal[$x]*$row['rabat']/100));
#										$linjesum[$x]=dkdecimal($tmp*$antal[$x]-($tmp*$antal[$x]*$row[rabat]/100));
								$pris[$x]=dkdecimal($tmp);
#cho "pris $pris[$x]<br>";
#cho "pris $linjesum[$x]<br>";
							}
						}
					} else {
						$dkantal[$x]=NULL;
						$varemomssats[$x]=NULL;
					}
					if ($rabatart[$x]=="amount") $sum+=afrund(($row['pris']-$row['rabat'])*$antal[$x],2); 
					else $sum+=afrund($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row['rabat']/100),2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
#               $sum=$sum+afrund($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row[rabat]/100),2);
#cho "Sum $sum<br>";;
					if ($formular==3 && $skjul_nul_lin && !$lev_antal[$x]) $varenr[$x]=NULL; #
				} else $dkantal[$x]=NULL;
			}
			$tmp=$row['rabat']*1;

#cho "status $status<br>";
#exit;

			if ($x>0 && $formular<4 && !$tmp && $m_rabat[$x] && $status < 3) {
				$y=$x;
				$x++;
				$posnr[$x]=$posnr[$y];
				$vare_id[$x]=$vare_id[$y];
				$varenr[$x]=$varenr[$y];
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
				$pris[$x]=dkdecimal($tmp*-1);
				$rabatpct=afrund($tmp*100/usdecimal($pris[$y]),2);
				$l_sum[$x]=afrund($tmp*-$antal[$x],3);
				$linjesum[$x]=dkdecimal($l_sum[$x]);
				if ($varemomssats[$y] > 0) $linjemoms[$x]=dkdecimal($varemomssats[$y]*$linjesum[$x]*0.01);
				else $linjemoms[$x]=dkdecimal($varemomssats[$y]*$linjesum[$x]);
				$sum+=$l_sum[$x];
					if ($momsfri[$y]!='on') {
#cho $moms."<br>";
						$moms+=afrund($m_rabat[$y]*$antal[$x]*$varemomssats[$y]/-100,3);
#cho $moms."<br>";
						$momssum=$momssum+afrund($m_rabat[$y]*-$antal[$x],2);
					}
				($rabatart[$x]=='amount')?$beskrivelse[$x]=findtekst(466,$sprog_id):$beskrivelse[$x]=findtekst(467,$sprog_id);
				$beskrivelse[$x]=str_replace('$rabatpct',$rabatpct,$beskrivelse[$x]);
#				if ($rabatart[$y]=='amount') $beskrivelse[$x]="rabat";
#				else $beskrivelse[$x]="rabat: ".$rabatpct."% på vnr: ".$varenr[$y];
#cho $sum.$l_sum[$x];
#					if ($momsfri[$y]!='on') {
#						$moms+=afrund($l_sum[$x]*$varemomssats[$y]/100,3);
#						$momssum=$momssum+afrund($tmp*$antal[$x],2);
#						if ($incl_moms && !$b2b) {
#							$tmp=afrund($m_rabat[$y]+$m_rabat[$y]*$varemomssats[$x]/100,2);
#							$linjesum[$x]=dkdecimal($tmp*$antal[$x]);
#							$pris[$x]=dkdecimal($tmp);
#						}
#					}
			} 
			$linjeantal=$x;
		}
#		if ($sum!=$ordresum) $sum=$ordresum;
#		if ($momssum!=$ordremomssum) $momssum=$ordremomssum;
		$y=$ya;
		$y_tjek=$y;
		$Opkt=$y-($antal_ordrelinjer*$linjeafstand);
		for ($x=1;$x<=$linjeantal; $x++) {
			$transportsum=$transportsum+$l_sum[$x-1];
			if ($kommentarprint=='on'||$formular!=3||$varenr[$x]) {	#Fordi tekst uden varenr ikke skal med paa foelgesedlen med mindre det er angivet i "formularprint";
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
					elseif ($variabel[$z]=="linjemoms") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$linjemoms[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="varemomssats") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$varemomssats[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="linjesum") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$linjesum[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]!="beskrivelse") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$variabel[$z]", "fritekst_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					elseif ($variabel[$z]=="beskrivelse") $skriv_beskriv[$x]=$z;
				}
				if ($z=$skriv_beskriv[$x]) $y2=ombryd("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$beskrivelse[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]",$laengde[$z],$formular,$linjeafstand);
				$y=$y2;
				if ($y==0) $y=$ya;
				$y=$y-$linjeafstand;
			}
		}
		$momssum=afrund($momssum,2);
		$sum=afrund($sum,2);
		$ialt=dkdecimal($sum+$moms);
		$momsgrundlag=dkdecimal($momssum);
		$moms=dkdecimal($moms);
		$sum=dkdecimal($sum);
	}
	if ($id) find_form_tekst($id, 'S', $formular,0,$linjeafstand,""); # Sum paa sidste side.

	if ($ordre_id[$q]) bundtekst($ordre_id[$q]); # Uden denne skrives kun  side 1
	if ($mail_fakt) fclose($fp2);
}
fclose($fp);
#xit;
if ($mailantal>0) {
	ini_set("include_path", ".:../phpmailer");
	require("class.phpmailer.php");
	if (!isset($exec_path)) $exec_path="/usr/bin";
	for($x=1;$x<=$mailantal;$x++) {
		print "<!-- kommentar for at skjule uddata til siden \n";
		system ("$exec_path/ps2pdf $mappe/$pfliste[$x] $mappe/$pfliste[$x].pdf");
		print "--> \n";
		if ($logoart=='PDF') {
				print "<!-- kommentar for at skjule uddata til siden \n";
				$out=$mappe."/".$pfliste[$x]."x.pdf";
				system ("$exec_path/pdftk $mappe/$pfliste[$x].pdf background ../logolib/$db_id/bg.pdf output $out");
				unlink ("$mappe/$pfliste[$x].pdf");
				system  ("mv $out $mappe/$pfliste[$x].pdf");
				print "--> \n";
			}

		$svar=send_mails("$mappe/$pfliste[$x].pdf",$email[$x],$mailsprog[$x],$form_nr[$x],'$subjekt','$mailtext',$x);
#		return($svar);
	}
}
#if ($mailantal>0) include("mail_faktura.php");
if ($nomailantal>0) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$mappe/$printfilnavn&id=$id&udskriv_til=$udskriv_til\">";
elseif ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
#else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
return('OK');

} #endfunc formularprint;

function formulartekst($id,$formular,$formularsprog){

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
}
function bundtekst($id)
{
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
}

function send_mails($filnavn,$email,$mailsprog,$form_nr,$subjekt,$mailtext,$mailnr) {

	global $db;
	global $db_id;
	global $mailantal;
	global $charset;
	global $formularsprog;
	global $webservice;
	global $ansat_id;

	$brugermail='';
	
	$emails=array();
	$email=str_replace(",",";",$email);
	if (strpos($email,";")) {
		$emails=explode(";",$email);
	} else $emails[0]=$email;
	
	$q=db_select("select * from formularer where formular='$form_nr' and art='5' and lower(sprog)='".strtolower($formularsprog)."'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['xa']=='1') $subjekt=$r['beskrivelse'];
		elseif ($r['xa']=='2') $mailtext=$r['beskrivelse'];
	}
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

	if ($charset=="UTF-8" || $webservice) {
		$subjekt=utf8_decode($subjekt);
		$mailtext=utf8_decode($mailtext);
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
		if (strpos($_SERVER['SERVER_NAME'],'ssl')) $mail->From = 'sslmailer.'.$db.'@saldi.dk';
		elseif (strpos($_SERVER['SERVER_NAME'],'ssl2')) $mail->From = 'ssl2mailer.'.$db.'@saldi.dk';
		else $mail->From = 'mailer.'.$db.'@saldi.dk';
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
		}	else echo "Mail sendt til $email<br>";
		if ($mailantal>1 && $mailnr==$mailantal) {
			if ($brugermail) $tekst="$mailantal mails sendt\\nBCC til $afsendermail\\nBCC til $brugermail.";
			else $tekst="$mailantal mails sendt\\nBCC til $afsendermail.";
			print "<BODY onLoad=\"javascript:alert('$tekst')\">";
		}
	}
	return("Mail sent to $email");
}
function find_saldo($id) {
	$r=db_fetch_array(db_select("select konto_id,ordredate,fakturadate from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['konto_id'];
	$ordredate=$r['ordredate'];
	$fakturadate=$r['fakturadate'];
	($fakturadate)?$transdate=$fakturadate:$transdate=$ordredate;
	
	$r=db_fetch_array(db_select("select sum(amount) as saldo from openpost where konto_id = '$konto_id' and transdate<='$transdate'",__FILE__ . " linje " . __LINE__));
	$kontosaldo=$r['saldo']*1;
	return ($kontosaldo);
}


?>
