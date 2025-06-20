<?php
ob_start(); //Starter output buffering
// ---------------------includes/formfunk.php ------patch 3.0.2----2010-05-31--------------
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

function skriv($str, $fed, $italic, $color, $tekst, $tekstinfo, $x, $y, $format, $form_font,$formular)
{
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
		list ($tekstinfo, $Opkt)=split("_", $tekstinfo);
		if ($y <= $Opkt) {
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
						list($tabel, $variabel)=split("_",$streng[$i],2);
						if ($tabel=="ordre") {
							if ($variabel=="rykkerdate") $variabel="fakturadate";							
							$q2 = db_select("select $variabel from ordrer where id=$id",__FILE__ . " linje " . __LINE__);}
						elseif (($tabel=="eget") || ($tabel=="egen")) {
							$q2 = db_select("select $variabel from adresser where art='S'",__FILE__ . " linje " . __LINE__);}
						elseif (($tabel=="ansat")&&($ref)) {
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
					fwrite($fp,"/$form_font\n$row[str] scalefont\nsetfont\nnewpath\n".$row[xa]*2.86." ".$row[ya]*2.86." moveto ($ny_streng) $format show\n");
				}
			}
			$y=$ya;
			$y=bundtekst($id);
			formulartekst($id,$formular,$formularsprog);
		} 
	}
	if ($charset=="UTF-8") $tekst=utf8_decode($tekst);
	fwrite($fp,"/$form_font\n$str scalefont\nsetfont\nnewpath\n$x $y2 moveto (".$tekst.") $format show\n");
	return $y;
}

function ombryd($str, $fed, $italic, $color, $tekst, $tekstinfo, $x, $y, $format, $form_font, $laengde,$formular,$linespace) {
	$tekst=wordwrap($tekst, $laengde,"\n ");
	$nytekst="";
	if (strstr($tekstinfo, 'ordrelinjer')) {
		list ($tmp,$Opkt)=split("_",$tekstinfo);
	}
	for ($z=1; $z<=strlen($tekst); $z++){
		 $tegn = substr($tekst,$z-1,1);
		 $nytekst = $nytekst . $tegn;
		 if (strstr($tegn,"\n")){
				$nytekst=trim($nytekst);
				if (strlen($nytekst)>=1){
					$y=skriv($str,$fed,$italic,$color,$nytekst,$tekstinfo,$x,$y,$format,$form_font,$formular);
					if ($y<$Opkt) {
						# sikring af ombrudt tekst v. sideskift.
						$y=skriv($str,$fed,$italic,$color,$nytekst,$tekstinfo,$x,$y,$format,$form_font,$formular);
					}
					$y=$y-$linespace;
				}
				$nytekst="";
		 }
	}
	$nytekst=trim($nytekst);
	if (strlen($nytekst)>=1){
		$y=skriv($str,$fed,$italic,$color,$nytekst,$tekstinfo,$x,$y,$format,$form_font,$formular);
	}
	return $y;
}

function find_form_tekst($id, $sidetekst, $formular,$y_pos,$linjeafstand,$pos_part) 
{
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
		
	$dk_transportsum=dkdecimal($transportsum);
	
	$pre_xa="";
	$preside=$side-1;
	$udskriv=1;

#	if ($pospart==2) $y_pos = $y_pos - $linjeafstand;
	if ($pos_part) $pos_part="and ya = '".$pos_part."'";
	else $pos_part="";
	
	if ($side=="1") $query = db_select("select * from formularer where formular = $formular and side = 'A' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
	if ($sidetekst=='S') $query = db_select("select * from formularer where formular = $formular and side ='S' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
	elseif ($side!="1") $query = db_select("select * from formularer where formular = $formular and (side = 'A' or side = '!1') and lower(sprog)='$formularsprog' $pos_part order by xb, xa",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$z=-1;
		$y=0; 
		$streng=array(); 
		$if=array();
		for ($x=0; $x<strlen($row['beskrivelse']); $x++){ # strengen loebes igennem
			if (((substr($row['beskrivelse'],$x,1)=="$")||($z==$y))&&(substr($row['beskrivelse'],0,4)!="+73<")){ #hvis strengen er en variabel eller en betalingskode
				if (($if[$y]!='1')&&($z!=$y)) $y++; #Hvis enden af strengen ikke er naaet
				$z=$y;
#|| ($streng[$y]=="betalingsid" && substr($row['beskrivelse'],$x,1)=="(")
					if ((substr($row['beskrivelse'],$x,1)!=" " && substr($row['beskrivelse'],$x,1)!=";")) { #slut paa en variabel	
					if (($if[$y]=='1')&&(substr($row['beskrivelse'],$x,1)==")")) { #variablen i if strengen fjernes
#						$streng[$y]=substr($streng[$y],0,strlen($streng[$y])-1);
						list($if_tabel,$if_variabel) = split("_",$streng[$y],2); #07.10.2007 -->
						if (substr($if_tabel,1)=="ordre") {
							$r=db_fetch_array(db_select("select $if_variabel from ordrer where id=$id",__FILE__ . " linje " . __LINE__));
							$tmp=$r[$if_variabel];
							if (!$tmp) {
								$udskriv=0;
								$row['beskrivelse']="";
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
					if (substr($row['beskrivelse'],$x,1)=="(") $streng[$y]=";";
					if (substr($row['beskrivelse'],$x,1)!=";") $streng[$y]=" ";
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
					if ($variabel=="rykkerdate") $variabel="fakturadate";							
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
							$forfalden=$forfalden+$r3['amount'];
						}
					}
					$sum=dkdecimal($forfalden);
					$gebyr=dkdecimal($gebyr);
				}
				if (($tabel!="formular")&&($tabel!="forfalden")&&($tabel!="rykker")) {
					$r2 = db_fetch_array($q2);
					if (strstr($variabel, 'date')) {
						$streng[$x]=dkdato($r2[$variabel]);
					}
					elseif ($variabel=='momssats') {
						list($streng[$x],$tmp)=split(",",dkdecimal($r2[$variabel]));
						$tmp=$tmp*1;
						if ($tmp) $streng[$x].=",".$tmp;
					}	else {
						$streng[$x]=$r2[$variabel];}
				} else {
					if ($variabel=='date') $streng[$x]=date("d-m-Y");
					if ($variabel=='ialt') $streng[$x]=$ialt;
					if ($variabel=='ialt_heltal') list($streng[$x],$tmp)=split(",",$ialt);
					if ($variabel=='ialt_decimal') list($tmp,$streng[$x])=split(",",$ialt);
					if ($variabel=='ialt') $streng[$x]=$ialt;
					if ($variabel=='moms') $streng[$x]=$moms;
					if ($variabel=='momsgrundlag') $streng[$x]=$momsgrundlag;
					if ($variabel=='preside') $streng[$x]=$preside;
					if ($variabel=='nextside') $streng[$x]=$nextside;
					if ($variabel=='side') $streng[$x]=$side;
					if ($variabel=='sum') $streng[$x]=$sum;
					if ($variabel=='sum_heltal') list($streng[$x],$tmp)=split(",",$sum);
					if ($variabel=='sum_decimal') list($tmp,$streng[$x])=split(",",$sum);
					if ($variabel=='transportsum') $streng[$x]=$dk_transportsum;
					if ($variabel=='forfaldsdato') $streng[$x]=find_forfaldsdato($id);
					if ($variabel=='lev_nr') $streng[$x]=$lev_nr;
					if ($variabel=='gebyr') $streng[$x]=$gebyr;
					if (strstr($variabel,'betalingsid')) $streng[$x]=modulus_10($id);
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
			if ($y_pos) {
				if (!$pre_xa || $pre_xa >= $row['xa']) {
					$y_pos=$y_pos-$linjeafstand;
					$pre_xa=$row['xa'];
				} 
				$row['ya']=$y_pos;
			}
			skriv("$row[str]","$row[fed]","$row[kursiv]","$row[color]","$ny_streng",'header',"$row[xa]","$row[ya]","$row[justering]","$row[font]","$formular");
		}
		$udskriv=1;
	}
	$y_pos = $y_pos - $linjeafstand;
	return($y_pos);
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
		list($kontolen,$faktlen)=split(",",$streng);
	}
	
	$r=db_fetch_array(db_select("select fakturanr, kontonr from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$fakturanr=trim($r['fakturanr']);
	$kontonr=trim($r['kontonr']);
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
function formularprint($id,$formular,$lev_nr,$subjekt,$mailtekst) {
	
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
	
$fp1=NULL;$kommentarprint=NULL;$skjul_nul_lin=NULL;
$folgeseddel=0;$mailantal=0;$nomailantal=0;
$side_x=210;
$side_y=297; 

$side_x=afrund($side_x*2.86,0);
$side_y=afrund($side_y*2.86,0);

#$initext="%!\n%%Creator: Saldi\n%%Pages: 1\n%%BoundingBox: 0 0 $side_x $side_y\n%%EndComments\n<< /PageSize [$side_x $side_y] >> setpagedevice\n";
$initext="%!\n%%Creator: Saldi\n%%Pages: 1\n%%EndComments\n";

$form=array();

if ($id==-1){	# Saa er der flere fakturaer
	$ordre_antal = $_GET['ordre_antal'];
	$ordre_id = explode(",", $_GET['skriv']);
	if (strpos($formular,",")) {
	 list($formular,$folgeseddel)=split(",",$formular);
	}
} else {
	$ordre_id[0]=$id;
	$ordre_antal=1;	
}
if ($formular==3) $folgeseddel=1;
if ($formular!=3 && $folgeseddel) {
	for ($q=0; $q<$ordre_antal; $q++) {
		$form[$q]=$formular;
		$r=db_fetch_array(db_select("select lev_addr1, lev_postnr from ordrer where id = $ordre_id[$q]",__FILE__ . " linje " . __LINE__));
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
		
if ( ! file_exists("../temp/$db") ) mkdir("../temp/$db", 0775);

if ($ordre_antal>1) {
	if ($formular<=1) $printfilnavn="tilbud";
	if ($formular==2) $printfilnavn="ordrebek";
	if ($formular==3) $printfilnavn="flg_seddel";
	if ($formular==4) $printfilnavn="faktura";
	$fp1=fopen("../temp/$db/$printfilnavn","w");
}
for ($q=0; $q<$ordre_antal; $q++) {
	$fp=$fp1;
	if (isset($form[$q])) $formular=$form[$q];
	$varenr=array(); $vare_id=array(); $linje_id=array(); $antal=array(); $tidl_lev=array(); $rest=array();
	$enhed=array(); $rabat=array(); $pris=array(); $l_sum=array(); $linjesum=array(); 
	$sum='';$transportsum=0;
	$query = db_select("select email,ordrenr,fakturanr,mail_fakt,pbs,art,ref,sprog from ordrer where id = '$ordre_id[$q]'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$ref=$row['ref'];
	$ordrenr=$row['ordrenr'];
	$fakturanr=$row['fakturanr'];
	$mail_fakt=$row['mail_fakt'];
	$email[0]=$row['email'];
	$pbs=$row['pbs'];
	$formularsprog=strtolower($row['sprog']);
	if (!$formularsprog) $formularsprog="dansk";
	if (($formular==4)||($formular==5)) {
		if ($row['art']=="DK") $formular=5;
		else $formular=4;
	}
	$y=185;
	$antal_ordrelinjer=25;
	$x=0;
	
	$query = db_select("select * from formularer where formular = '$formular' and art = '1' and beskrivelse = 'LOGO' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {$logo_X=$row['xa']*2.86; 	$logo_Y=$row['ya']*2.86;}
	else {$logo_X=430; $logo_Y=758;}
	if (file_exists("../logolib/logo_$db_id.eps")){$logo="../logolib/logo_$db_id.eps";}
	else {$logo="../logolib/logo.eps";}
	$fsize=filesize($logo);
	$logofil=fopen($logo,"r");
	$translate=0;
	$logo="";
	while (!feof($logofil)) {
		 $linje=fgets($logofil);
		 if (substr($linje,0,2)!="%!") {
			 if (strstr($linje, "translate")&&(!$translate)) {
				 $linje="$logo_X $logo_Y translate \n"; 
				 $translate=1;
			 }
			 $logo=$logo.$linje;
		} 
	}
	fclose($logofil);
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
	if ($mail_fakt && $formular!=3) {
		$mailantal++;		
		if ($formular<=1) $pfnavn="tilbud".$ordrenr;
		if ($formular==2) $pfnavn="ordrebek".$ordrenr;
		if ($formular==4) $pfnavn="fakt".$fakturanr;
		if ($formular==5) $pfnavn="kn".$fakturanr;
		$email[$mailantal]=$email[0];
		$mailsprog[$mailantal]=$formularsprog;
		$form_nr[$mailantal]=$formular;
		$pfliste[$mailantal]=$pfnavn;
		$pfnavn="../temp/".$db."/".$pfnavn;
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
			$pfnavn="../temp/".$db."/".$printfilnavn;
			$fp=fopen("$pfnavn","w");
		}
	}
	$side=1;
	fwrite($fp,$initext);
	$rabat[0]=formulartekst($ordre_id[$q],$formular,$formularsprog); 
	if ($ordre_id[$q]){
		$id=$ordre_id[$q];
		$x=0;
		$sum=0;
		$moms=0;
		$momssum=0;
		$tmp=0;
		$query = db_select("select * from ordrelinjer where ordre_id = $ordre_id[$q] order by posnr",__FILE__ . " linje " . __LINE__);
		while($row = db_fetch_array($query)){
			if ($row[posnr]>0){
				$x++;
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
					$linje_id[$x]=$row[id];
					$antal[$x]=$row['antal']*1;
					$dkantal[$x]=str_replace(".",",",$antal[$x]);
					$momsfri[$x]=$row['momsfri'];
					$varemomssats[$x]=$row['momssats']*1;
					if (!$momsfri[$x] && !$varemomssats[$x]) $varemomssats[$x]=$momssats;
					if ($varemomssats[$x] > $momssats) $varemomssats[$x]=$momssats;
					list($a,$b)=split(",",$dkantal[$x]);
					$b=$b*1;
					if ($b) $dkantal[$x]="$a,$b";
					else $dkantal[$x]=$a;
					if ($formular==5){$antal[$x]=$antal[$x]*-1;}
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
					$l_sum[$x]=afrund($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row[rabat]/100),2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
					$linjesum[$x]=dkdecimal($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row[rabat]/100));
					if ($momsfri[$x]!='on') {
						$moms+=afrund($l_sum[$x]*$varemomssats[$x]/100,2);
						$momssum=$momssum+afrund($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row[rabat]/100),2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
						if ($incl_moms) {
							$tmp=afrund($row['pris']+$row['pris']*$varemomssats[$x]/100,2);
							$pris[$x]=dkdecimal($tmp);
							$linjesum[$x]=dkdecimal($tmp*$antal[$x]-($tmp*$antal[$x]*$row[rabat]/100));
						}
					}
					$sum=$sum+afrund($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row[rabat]/100),2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
					if ($formular==3 && $skjul_nul_lin && !$lev_antal[$x]) $varenr[$x]=NULL; #  
				}
			}
			$linjeantal=$x;
		}
		$y=$ya;
		$Opkt=$y-($antal_ordrelinjer*$linjeafstand);	 
		for ($x=1;$x<=$linjeantal; $x++) {
			$transportsum=$transportsum+$l_sum[$x-1];
			if ($kommentarprint=='on'||$formular!=3||$varenr[$x]) {	#Fordi tekst uden varenr ikke skal med paa foelgesedlen med mindre det er angivet i "formularprint";
				for ($z=1; $z<=$var_antal; $z++) {
					if ($variabel[$z]=="posnr")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$posnr[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="varenr") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular"); # ellers kommer varenummer ikke med paa 1. linje paa side 2 . og 3
					if ($variabel[$z]=="varenr") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$varenr[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="projekt")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$projekt[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="antal")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$dkantal[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular"); #ellers kommer antal ikke med paa 1. linje paa side 2 . og 3
					if ($variabel[$z]=="lev_antal")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$lev_antal[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="tidl_lev")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$tidl_lev[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="lev_rest")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$rest[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="pris")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$pris[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="enhed")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$enhed[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="rabat")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$rabat[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="linjesum")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$linjesum[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="beskrivelse") $skriv_beskriv[$x]=$z; 
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
	find_form_tekst($id, 'S', $formular,0,$linjeafstand,""); # Sum paa sidste side.
	
	bundtekst($ordre_id[$q]); # Uden denne skrives kun  side 1
	if ($mail_fakt) fclose($fp2);
}
fclose($fp);
if ($mailantal>0) {
	ini_set("include_path", ".:../phpmailer");
	require("class.phpmailer.php");
	for($x=1;$x<=$mailantal;$x++) {
		system ("/usr/bin/ps2pdf ../temp/$db/$pfliste[$x] ../temp/$db/$pfliste[$x].pdf");
		$svar=send_mails("../temp/$db/$pfliste[$x].pdf",$email[$x],$mailsprog[$x],$form_nr[$x],'$subjekt','$mailtext');	
		return($svar);
	}
}
#if ($mailantal>0) include("mail_faktura.php");
if ($nomailantal>0) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$db/$printfilnavn&id=$id\">";
elseif ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
#else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
return($svar);
} #endfunc formularprint;

function formulartekst($id,$formular,$formularsprog){
	
#	global $formular;
	global $momssats;
	global $dkdato;
	global $connection;
	global $fp;
	global $side;
#	global $formularsprog;
	
	$rabat=0;
	include("../includes/ordreopslag.php");
	
	if ($art=="DO") {$art="Faktura";}
	else {$art="Kreditnota";}
	
	$query = db_select("select * from ordrelinjer where ordre_id = '$id' and rabat > '0'",__FILE__ . " linje " . __LINE__);
	if($row = db_fetch_array($query)) {$rabat="y";}

	$faktdato=dkdato($fakturadate);
	$query = db_select("select * from ordrelinjer where ordre_id = '$id' and rabat > '0'",__FILE__ . " linje " . __LINE__);
	if($row = db_fetch_array($query)) {$rabat="y";}

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

		fwrite($fp," $xa $ya moveto $xb $yb lineto $lw setlinewidth $color stroke \n");
	}

	find_form_tekst($id, 'A', $formular,0,0,0);

return $rabat;	
}
function bundtekst($id)
{
global $logo;
global $fp;
global $nextside;
global $side;
global $y;
global $ya;
global $linjeafstand;

$y=$ya-$linjeafstand;
$side=$side+1;

fwrite($fp, $logo);
return $y;
# fwrite($fp,"showpage");
}

function send_mails($filnavn,$email,$mailsprog,$form_nr,$subjekt,$mailtext) {
	global $db;
	global $mailantal;
	global $charset;
	global $formularsprog;
	
	$q=db_select("select * from formularer where formular='$form_nr' and art='5' and lower(sprog)='".strtolower($formularsprog)."'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['xa']=='1') $subjekt=$r['beskrivelse'];	
		elseif ($r['xa']=='2') $mailtext=$r['beskrivelse'];
	}
	$row = db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	$afsendermail=$row['email'];
	$afsendernavn=$row['firmanavn'];
	if (!$afsendermail || !$afsendernavn) {
		return("Missing sender mail");
#		print "<BODY onLoad=\"javascript:alert('Firmanavn eller e-mail for afsender ikke udfyldt.\\nSe (Indstillinger -> stamdata).\\nMail ikke afsendt!')\">";
#		return;
	}
	
	if ($charset=="UTF-8") {
		$subjekt=utf8_decode($subjekt);
		$mailtext=utf8_decode($mailtext);
		$afsendernavn=utf8_decode($afsendernavn);
	}
	
	$mail = new PHPMailer();

	$mail->IsSMTP();                                   // send via SMTP
	$mail->Host  = "localhost"; // SMTP servers
	$mail->SMTPAuth = false;     // turn on SMTP authentication
			#	$mail->Username = "jswan";  // SMTP username
			#	$mail->Password = "secret"; // SMTP password
			
	$mail->From     = $afsendermail;
	$mail->FromName = $afsendernavn;
	$mail->AddAddress($email); 
	$mail->AddBCC($afsendermail); 
	#	$mail->AddAddress("ellen@site.com");               // optional name
	#	$mail->AddReplyTo("info@site.com","Information");
	
$mail->WordWrap = 50;  // set word wrap
#	$mail->AddAttachment("../temp/$db/mailtext.html");
	$mail->AddAttachment("$filnavn");      // attachment
#	$mail->AddAttachment("/tmp/image.jpg", "new.jpg"); 
	$mail->IsHTML(true);                               // send as HTML

	$ren_text=html_entity_decode($mailtext,ENT_COMPAT,$charset);

	$mail->Subject  =  "$subjekt";
	$mail->Body     =  "$mailtext";
	$mail->AltBody  =  "$ren_text";
	

	if(!$mail->Send()){
		echo "Fejl i afsendelse til $email<p>";
 		echo "Mailer Error: " . $mail->ErrorInfo;
		exit;
	}
#	if ($mailantal==1) print "<BODY onLoad=\"javascript:alert('Mail sendt til $email')\">";
#	else 
# echo "Mail sendt til $email<br>";
	return("Mail sent to $email");
}	
?>
