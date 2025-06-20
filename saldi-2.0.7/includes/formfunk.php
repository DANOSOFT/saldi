<?php
// ---------------------includes/formfunk.php ------patch 2.0.7-----2009.04.27--------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------



function skriv($str, $fed, $italic, $color, $tekst, $tekstinfo, $x, $y, $format, $form_font)
{
	global $formular;
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

	if (!$incr_x) $incr_x=0;
	if (!$incr_y) $incr_y=0;
	
	$format=strtoupper($format);

	$tekst=str_replace("(","\\(",$tekst);
	$tekst=str_replace(")","\\)",$tekst);

#	if ($color!='0'){
		$tmp=strlen($color);
		for ($a=$tmp;$a<9;$a++) $color="0".$color;
		$tmp1=substr($color,-9,3)/100;
		$tmp2=substr($color,-6,3)/100;
		$tmp3=substr($color,-3,3)/100;
		$color="$tmp1 $tmp2 $tmp3 setrgbcolor";
#	}
#	else {$color="0 setgray";}

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
		list ($tekstinfo, $Opkt)=split("_", $tekstinfo);
		if ($y <= $Opkt) {
			$preside = $side -1;
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
				if ($row['placering']=='H'){$format="$color dup stringwidth pop\nneg 0 rmoveto";}
				elseif ($row['placering']=='C'){$format="$color dup stringwidth pop\n2 div neg 0 rmoveto";}
				else {$format="$color";}

				$z=-1;
				$n=0; 
				$streng=array(); 
				for ($x=0; $x<strlen($row['beskrivelse']); $x++){
					if ((substr($row['beskrivelse'],$x,1)=="$")||($z==$n)){
						if ($z!=$n) {$n++;} 
						$z=$n;
						if (substr($row['beskrivelse'],$x,1)!=" "){$streng[$n]=$streng[$n].substr($row['beskrivelse'],$x,1);}
						else { 
							$n++;
							$streng[$n]=" ";
						}
					}
					else { $streng[$n]=$streng[$n].substr($row['beskrivelse'],$x,1);}
				}
				$streng_antal=$n;	
				$ny_streng="";
				for ($x=0; $x<=$streng_antal; $x++){
					if (substr($streng[$x],0,1)=="$") {
						$streng[$x]=substr($streng[$x],1);
						list($tabel, $variabel)=split("_",$streng[$x],2);
						if ($tabel=="ordre") {
							$q2 = db_select("select $variabel from ordrer where id=$id",__FILE__ . " linje " . __LINE__);}
						elseif (($tabel=="eget") || ($tabel=="egen")) {
							$q2 = db_select("select $variabel from adresser where art='S'",__FILE__ . " linje " . __LINE__);}
						elseif (($tabel=="ansat")&&($ref)) {
							$r2 = db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__));
							$q2 = db_select("select $variabel from ansatte where konto_id=$r2[id] and navn = '$ref'",__FILE__ . " linje " . __LINE__);
						}
						if ($tabel!="formular") {
							$r2 = db_fetch_array($q2);
							$streng[$x]=$r2[$variabel];
						}
						else {
							if ($variabel=='ialt')				$streng[$x]=$ialt;
							if ($variabel=='moms') 				$streng[$x]=$moms;
							if ($variabel=='momsgrundlag')$streng[$x]=$momsgrundlag;
							if ($variabel=='preside') 		$streng[$x]=$preside;
							if ($variabel=='nextside') 		$streng[$x]=$nextside;
							if ($variabel=='side') 				$streng[$x]=$side;
							if ($variabel=='sum') 				$streng[$x]=$sum;
							if ($variabel=='transportsum')$streng[$x]=$dk_transportsum;
							if ($variabel=='forfaldsdato')$streng[$x]=$forfaldsdato;
						}
					}		 
					$ny_streng=$ny_streng.$streng[$x];
					if ($charset=="UTF-8") $ny_streng=utf8_decode($ny_streng);
				}
				if ($ny_streng) {
					fwrite($fp,"/$form_font\n$row[str] scalefont\nsetfont\nnewpath\n".$row[xa]*2.86." ".$row[ya]*2.86." moveto ($ny_streng) $format show\n");
				}
			}		 
			$y=$ya;
			$y=bundtekst($id);
			formulartekst($id);
		}
	}
	if ($charset=="UTF-8") $tekst=utf8_decode($tekst);
	fwrite($fp,"/$form_font\n$str scalefont\nsetfont\nnewpath\n$x $y2 moveto (".$tekst.") $format show\n");
	return $y;
}

function ombryd($str, $fed, $italic, $color, $tekst, $tekstinfo, $x, $y, $format, $form_font, $laengde) 
{
	$tekst=wordwrap($tekst, $laengde,"\n ");
	$nytekst="";
	for ($z=1; $z<=strlen($tekst); $z++)
	{
		 $tegn = substr($tekst,$z-1,1);
		 $nytekst = $nytekst . $tegn;
		 if (strstr($tegn,"\n"))
		 {
				$nytekst=trim($nytekst);
				if (strlen($nytekst)>=1)
				{
					$y=skriv($str, $fed, $italic, $color, $nytekst, $tekstinfo, $x, $y, $format, $form_font, $l_sum);
					$y=$y-4;
				}
				$nytekst="";
		 }
	}
	$nytekst=trim($nytekst);
	if (strlen($nytekst)>=1) 
	{
		$y=skriv($str, $fed, $italic, $color, $nytekst, $tekstinfo, $x, $y, $format, $form_font, $l_sum);
#		$y=$y-4;
	}
	return $y;
}

function find_form_tekst($id, $sidetekst, $formular) 
{
	global $sum;
	global $moms;
	global $momsgrundlag;
	global $ialt;
	global $side;
	global $lev_nr;
	global $ref;
	global $transportsum;
	global $ya;
	global $linjeafstand;
	global $gebyr;
	global $formularsprog;
	
	$dk_transportsum=dkdecimal($transportsum);
	
	$preside=$side-1;
	$udskriv=1;

	if ($side=="1") $query = db_select("select * from formularer where formular = $formular and side = 'A' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
	if ($sidetekst=='S') $query = db_select("select * from formularer where formular = $formular and side ='S' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
	elseif ($side!="1") $query = db_select("select * from formularer where formular = $formular and (side = 'A' or side = '!1') and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$z=-1;
		$y=0; 
		$streng=array(); 
		$if=array();
		for ($x=0; $x<strlen($row['beskrivelse']); $x++){ # strengen loebes igennem
			if (((substr($row['beskrivelse'],$x,1)=="$")||($z==$y))&&(substr($row['beskrivelse'],0,4)!="+73<")){ #hvis strengen er en variabel eller en betalingskode
				if (($if[$y]!='1')&&($z!=$y)) $y++; #Hvis enden af strengen ikke er naaet
				$z=$y; 
				if ((substr($row['beskrivelse'],$x,1)!=" ")&&(substr($row['beskrivelse'],$x,1)!=";")) { #slut på en variabel	
					if (($if[$y]=='1')&&(substr($row['beskrivelse'],$x,1)==")")) { #variablen i if strengen fjernes
#						$streng[$y]=substr($streng[$y],0,strlen($streng[$y])-1);
						list($if_tabel,$if_variabel) = split("_",$streng[$y],2); #07.10.2007 -->
						if (substr($if_tabel,1)=="ordre") {
							$r=db_fetch_array(db_select("select $if_variabel from ordrer where id=$id",__FILE__ . " linje " . __LINE__));
							$tmp=$r[$if_variabel];
							if (!$tmp) {
								$udskriv=0;
								$row[beskrivelse]="";
								$x=0;
								$y=0; 
							} # <-- 07.10.2007
						}
						$if_nr=$y;
						$streng[$y]=''; 
						$if[$y+1]='!';
						$if[$y]='0';
						$y++;	
					}
					else $streng[$y]=$streng[$y].substr($row['beskrivelse'],$x,1);
				} else { 
					$y++;
					if (substr($row['beskrivelse'],$x,1)!=";") {$streng[$y]=" ";}
				}
			} else {
				$streng[$y]=$streng[$y].substr($row['beskrivelse'],$x,1);
				if ($streng[$y]=='if(') {
					$if[$y]='1';
					$streng[$y]='';
				} 
			}
		}

		$streng_antal=$y;	
		$ny_streng="";
		for ($x=0; $x<=$streng_antal; $x++){
			if (substr($streng[$x],0,1)=="$") {
				$streng[$x]=substr($streng[$x],1);
				list($tabel, $variabel)=split("_",$streng[$x],2);
				if (($formular==3)&&($tabel=="ordre")&&(($variabel=="lev_navn")||($variabel=="lev_addr1")||($variabel=="lev_addr2")||($variabel=="lev_postnr")||($variabel=="lev_bynavn")||($variabel=="lev_kontakt"))) {
					$variabel=tjek_lev_addr($variabel, $id);
				}
				if ($tabel=="ordre") {
					$q2 = db_select("select $variabel from ordrer where id=$id",__FILE__ . " linje " . __LINE__);}
				elseif (($tabel=="eget") || ($tabel=="egen")) {
					$q2 = db_select("select $variabel from adresser where art='S'",__FILE__ . " linje " . __LINE__);}
				elseif ($tabel=="kunde") {$q2 = db_select("select $variabel from adresser where art='D' and id=$id",__FILE__ . " linje " . __LINE__);}
				elseif ($tabel=="levering") {$q2 = db_select("select $variabel from batch_salg where ordre_id=$id and lev_nr=$lev_nr",__FILE__ . " linje " . __LINE__);}
				elseif ($tabel=="ansat") {
					$r2 = db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__));
					$q2 = db_select("select $variabel from ansatte where konto_id=$r2[id] and navn='$ref'",__FILE__ . " linje " . __LINE__);
				}
				elseif ($tabel=="forfalden" || $tabel=="rykker") {# $tabel=="rykker" indsat 14.04.08
				        $forfalden=0;
					$q2 = db_select("select * from ordrelinjer where ordre_id = $id",__FILE__ . " linje " . __LINE__);
					while ($r2 = db_fetch_array($q2)) {
						if ($r2[pris]) $gebyr=$r2['pris'];
						if ($r2[enhed]) {
							$q3 = db_select("select amount from openpost where id='$r2[enhed]'",__FILE__ . " linje " . __LINE__);
							$r3 = db_fetch_array($q3);
							$forfalden=$forfalden+$r3[amount];
						}
					}
					$sum=dkdecimal($forfalden);
					$gebyr=dkdecimal($gebyr);
				}
				if (($tabel!="formular")&&($tabel!="forfalden")&&($tabel!="rykker")) {
					$r2 = db_fetch_array($q2);
					if (strstr($variabel, 'date')) {$streng[$x]=dkdato($r2[$variabel]);}
					else {
						$streng[$x]=$r2[$variabel];}
				} else {
					if ($variabel=='date') {$streng[$x]=date("d-m-y");}
					if ($variabel=='ialt') {$streng[$x]=$ialt;}
					if ($variabel=='moms') {$streng[$x]=$moms;}
					if ($variabel=='momsgrundlag') {$streng[$x]=$momsgrundlag;}
					if ($variabel=='preside') {$streng[$x]=$preside;}
					if ($variabel=='nextside') {$streng[$x]=$nextside;}
					if ($variabel=='side') {$streng[$x]=$side;}
					if ($variabel=='sum') {$streng[$x]=$sum;}
					if ($variabel=='transportsum') {$streng[$x]=$dk_transportsum;}
					if ($variabel=='forfaldsdato') {$streng[$x]=find_forfaldsdato($id);}
					if ($variabel=='lev_nr') {$streng[$x]=$lev_nr;}
					if ($variabel=='gebyr') {$streng[$x]=$gebyr;}
#echo Ahem1;							
							if ($variabel=='betalingsid') $streng[$x]=modulus_10($id);
#echo Ahem2;							
				}
			}		 

			if ($if[$x]=="!") {
				$if[$x+1]=$streng[$x];
				$streng[$x]=''; 
			}	
			elseif (($if[$x])&&($streng[$x])) {
				$streng[$x]=$if[$x]." ".$streng[$x];
			}

		$ny_streng=$ny_streng.$streng[$x];
		}
		if ($ny_streng&&$udskriv) {
			skriv ("$row[str]", "$row[fed]", "$row[kursiv]", "$row[color]", "$ny_streng", 'header', "$row[xa]", "$row[ya]", "$row[placering]", "$row[font]");
		}
		$udskriv=1;
	}
}

function find_forfaldsdato($id) 
{
	$query = db_select("select fakturadate, betalingsbet, betalingsdage from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$fakturadate=$row[fakturadate];
	$betalingsbet=$row[betalingsbet];
	$betalingsdage=$row[betalingsdage];

	list($faktaar, $faktmd, $faktdag) = split("-", $fakturadate);
	$forfaldsaar=$faktaar;
	$forfaldsmd=$faktmd;
	$forfaldsdag=$faktdag;
	$slutdag=31;

	if (($fakturadate)&&($betalingsbet=="Netto"||$betalingsbet=="Lb. md.")) {
		while (!checkdate($forfaldsmd, $slutdag, $forfaldsaar)) {
			$slutdag--;
			if ($slutdag<27) {break;}
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
				if ($slutdag<27) {break;}
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
	
# genererer betalings id for kortart 71. 
# Kortart 71 bestaar af 15 cifrer hvor det sidste er kontrolciffer.
	$r=db_fetch_array(db_select("select fakturanr from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$fakturanr=trim($r['fakturanr']);
#	$fakturanr=2684014996532;
	$y=0;
	$kontrol=array();
	if (strlen ($fakturanr)>14) {
		print "<BODY onLoad=\"JavaScript:alert('Fakturanummer m&aring; ikke overstige 14 karakterer - betalingsid kan ikke genereres');\">";
		return ("000000000000000");
	}	
	while(strlen($fakturanr)<14) $fakturanr="0".$fakturanr;  #fakturanr oeges til 14 cifre.
	for ($x=13;$x>=0;$x--) {
		if ($y==0) {
			$kontrol[$x]=substr($fakturanr,$x,1)*2;
			if ($kontrol[$x]>9) $kontrol[$x]=substr($kontrol[$x],0,1)+substr($kontrol[$x],1,1); 
			$y=1;
		}	else {
			$kontrol[$x]=substr($fakturanr,$x,1);
			$y=0;
		}
		echo "kontrol $kontrol[$x]<br>";
		$kontrolsum=$kontrolsum+$kontrol[$x];
	}
	$kontrolciffer=10-substr($kontrolsum,-1,1);
	if ($kontrolciffer==10) $kontrolciffer=0;
	$betalingsid=$fakturanr.$kontrolciffer;
#		print "<BODY onLoad=\"JavaScript:alert('betalingsid = $betalingsid');\">";
	return ($betalingsid);
}

?>