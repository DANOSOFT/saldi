<?php
// -------------debitor/ubl2ordre.php----------lap 3.2.9-----2012-12-20----
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
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

// 2012.12.20 Fejl v. NULL værdi for antal og pris, søg 20121220

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="OIOUBL import";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

$dir = '../ublfiler/ind/';
$filer = scandir($dir);
$fejllog=$dir."fejl.log";
if (file_exists("$fejllog")) unlink("$fejllog");
for ($x=0;$x<count($filer);$x++) {
	if (substr($filer[$x],-3)=='xml') {
		echo "importerer $filer[$x]<br>";
		$filnavn="../ublfiler/ind/".$filer[$x];
		$svar=importer($filnavn,$fejllog);
		echo "Svar $svar<br>";
		if ($svar=='OK') {
			$flyt_til="../ublfiler/ok/".$filer[$x];
			rename($filnavn,$flyt_til);
		} else echo "Import af $filer[$x] fejlet<br>$fejl<br>";
	}
} 
if (file_exists("$fejllog")) {
	echo "<a href=\"$fejllog\" target=\"blank\">Se fejllog</a><br>";
}

function importer($filnavn,$fejllog){
	global $charset;
	global $bgcolor;
	global $bgcolor5;
	
	$accountingsupplierparty=NULL;
	$cvrnr=NULL;
	$fejl=NULL;$firmanavn=NULL;
	$linjenr=0;
	$orderreference=NULL;
	$party=null; $partyname=NULL;	

	$indhold=null;						
	$ean=NULL;

	$sekliste=array('ID');

	$fp=fopen($filnavn,'r');
	if ($fp) {
#		$x=0;
		$indhold=NULL;
		while($linje[$x]=fgets($fp)) $indhold.=$linje[$x];
#		$linjeantal=$x;
		fclose($fp);
	}
	$ordrenr=find_var($indhold,'','id');
	$ordredate=find_var($indhold,'','issuedate');
	$k_cvrnr=find_var($indhold,'accountingsupplierparty,party','endpoinid schemeid="dk:cvr"');
	$k_firmanavn=find_var($indhold,'accountingsupplierparty,partyname','name');
	$k_vejnavn=find_var($indhold,'accountingsupplierparty,postaladdress','streetname');
	$k_husnr=find_var($indhold,'accountingsupplierparty,postaladdress','buildingnumber');
	$k_bynavn=find_var($indhold,'accountingsupplierparty,postaladdress','cityname');
	$k_postnr=find_var($indhold,'accountingsupplierparty,postaladdress','postalzone');
	$k_land=find_var($indhold,'accountingsupplierparty,country','identificationcode');
	$k_telefon=find_var($indhold,'accountingsupplierparty,contact','telephone');
	$k_email=find_var($indhold,'accountingsupplierparty,contact','electronicmail');

	$d_cvrnr=find_var($indhold,'accountingcustomerparty,party','endpoinid schemeid="dk:cvr"');
	$d_firmanavn=find_var($indhold,'accountingcustomerparty,partyname','name');
	$d_vejnavn=find_var($indhold,'accountingcustomerparty,postaladdress','streetname');
	$d_husnr=find_var($indhold,'accountingcustomerparty,postaladdress','buildingnumber');
	$d_bynavn=find_var($indhold,'accountingcustomerparty,postaladdress','cityname');
	$d_postnr=find_var($indhold,'accountingcustomerparty,postaladdress','postalzone');
	$d_land=find_var($indhold,'accountingcustomerparty,country','identificationcode');
	$d_telefon=find_var($indhold,'accountingcustomerparty,contact','telephone');
	$d_email=find_var($indhold,'accountingcustomerparty,contact','electronicmail');

	$o_sum=find_var($indhold,'legalmonetarytotal','lineextensionamount');
	$o_totalsum=find_var($indhold,'paymentterms','amount');
	$o_moms=find_var($indhold,'taxtotal','taxamount');

	$pos=explode(",",find_antal_vars($indhold,'<cac:InvoiceLine>'));
	$antal_ordrelinjer=count($pos);

	$sum=0;
	$momssum=0;
	$d_momssats=0;
	for ($x=0;$x<$antal_ordrelinjer;$x++) {
		$string=substr($indhold,$pos[$x]);
		$l_varenr[$x]=find_var($string,'invoiceline,sellersitemidentification','id');
		$l_antal[$x]=find_var($string,'invoiceline','invoicedquantity');
		$l_tekst[$x]=find_var($string,'invoiceline,item','name');
		$l_pris[$x]=find_var($string,'invoiceline,price','priceamount');
		$l_momssats[$x]=find_var($string,'invoiceline','percent');
		$sum+=$l_pris[$x];
		$momssum+=$l_pris[$x]/100*$l_momssats[$x];
		if ($l_momssats[$x]>$d_momssats) $d_momssats=$l_momssats[$x];
	}

	if ($r=db_fetch_array(db_select("select * from adresser where art = 'D' and kontonr = '$d_telefon'",__FILE__ . " linje " . __LINE__))) $d_konto_id=$r['id'];
	else {
		$fejl="Debitor $d_firmanavn, med kontonummer: $d_telefon eksisterer ikke";
		fejllog("$fejl","$fejllog");
		return ($fejl);
	}

	for ($x=0;$x<$antal_ordrelinjer;$x++) {
		if ($l_varenr[$x] != 'TEXT') {
			if ($r=db_fetch_array(db_select("select * from varer where varenr = '$l_varenr[$x]'",__FILE__ . " linje " . __LINE__))) $l_vare_id[$x]=$r['id'];
			else {
				$fejl="Vare $l_varenr[$x] eksisterer ikke";
				fejllog("$fejl","$fejllog");
				return ($fejl);
			}
		}
	}
	$tidspkt=date('U');
	$d_addr1=$d_vejnavn." ".$d_husnr;
	$d_addr2='';
	$betalingsbet='Netto';
	$betalingsdage=8;
	$valuta='DKK';
	$valutakurs='100';
	$sprog='Dansk';

	transaktion('begin');

	if (!$fejl) {
		$r=db_fetch_array(db_select("select max(ordrenr) as ordrenr from ordrer where art = 'DO' or art = 'DK'",__FILE__ . " linje " . __LINE__));
		$d_ordrenr=$r['ordrenr']+1;
		db_modify("insert into ordrer(ordrenr,konto_id,kontonr,firmanavn,addr1,postnr,bynavn,land,email,ordredate,levdate,fakturadate,art,betalingsbet,betalingsdage,status,tidspkt,valuta,valutakurs,momssats,sum,moms)values('$d_ordrenr','$d_konto_id','$d_telefon','$d_firmanavn','$d_addr1','$d_postnr','$d_bynavn','$d_land','$d_email','$ordredate','$ordredate','$ordredate','DO','$betalingsbet','$betalingsdage','1','$tidspkt','$valuta','$valutakurs','$d_momssats','$o_sum','$o_moms')",__FILE__ . " linje " . __LINE__);
		if ($r=db_fetch_array(db_select("select id from ordrer where art = 'DO' and kontonr = '$d_telefon' and tidspkt = '$tidspkt'",__FILE__ . " linje " . __LINE__))) $d_ordre_id=$r['id'];
		else {
			$fejl="Ordre ikke oprettet (Debitor $d_firmanavn, med kontonummer: $d_telefon)";
			fejllog("$fejl","$fejllog");
			return ($fejl);
		}
	}
	if (!$fejl) {
		for ($x=0;$x<$antal_ordrelinjer;$x++) {
				$pos=$x+1;
			if (strtolower($l_varenr[$x]) == 'text') { 
				db_modify("insert into ordrelinjer(posnr,ordre_id,beskrivelse)values('$pos','$d_ordre_id','$l_tekst[$x]')",__FILE__ . " linje " . __LINE__);
			} else {
				$l_antal[$x]*=1; #20121220
				$l_pris[$x]*=1; #20121220
				$l_momssats[$x]*=1;
				($l_momssats[$x])?$momsfri='':$momsfri='on';
				db_modify("insert into ordrelinjer(posnr,ordre_id,vare_id,varenr,antal,beskrivelse,pris,momssats,momsfri,kostpris)values('$pos','$d_ordre_id','$l_vare_id[$x]','$l_varenr[$x]','$l_antal[$x]','$l_tekst[$x]','$l_pris[$x]','$l_momssats[$x]','$momsfri','0')",__FILE__ . " linje " . __LINE__);
			}
		}
	}

	if (!$fejl) {
		db_modify("update ordrer set tidspkt= '' where id='$d_ordre_id'",__FILE__ . " linje " . __LINE__);
		transaktion('commit');
	}
#echo "Fejl >$fejl<<br>";
	if ($fejl) return ("$fejl");
	else return ("OK");
/*
	$linjebg=$bgcolor5;
	print "<tr><td valign=\"top\" align=\"center\"><table valign=\"top\"><tbody>";
	print "<tr bgcolor=\"$bgcolor5\"><td width=\"100px\"><b>Firmanavn:</b></td><td width=\"200px\">$k_firmanavn<br></td><td width=\"100px\"><b>Ordrenr:</b></td><td width=\"200px\">$ordrenr<br></td></tr>\n";
	print "<tr><td><b>Adresse:</b></td><td>$k_vejnavn $k_husnr<br></td><td><b>Ordredate:</td><td>$ordredate<br></td></tr>\n";
	print "<tr bgcolor=\"$bgcolor5\"><td><b>Adresse:</b></td><td>$k_postnr $k_bynavn<br></td><td><b>Fakturanr:</b></td><td>$k_fakturanr<br></td></tr>\n";
	print "<tr><td><b>Land:</b></td><td>$k_land<br></td><td><b>Cvr:</b></td><td>$k_cvrnr<br></td></tr>\n";
	print "<tr bgcolor=\"$bgcolor5\"><td bgcolor=\"$bgcolor5\"><b>Telefon:</b></td><td>$k_telefon<br></td><td><b></b></td><br><td><br></td></tr>\n";
	print "</tbody></table></td></tr>";
	print "<tr><td valign=\"top\" align=\"center\"><table valign=\"top\"><tbody>";
	print "<tr bgcolor=\"$bgcolor5\"><td width=\"100px\"><b>(Firma)navn:</b></td><td width=\"200px\">$d_firmanavn<br></td><td width=\"100px\"><b>Ordrenr:</b></td><td width=\"200px\">$ordrenr<br></td></tr>\n";
	print "<tr><td><b>Adresse:</b></td><td>$d_vejnavn $d_husnr<br></td><td><b>Ordredate:</td><td>$ordredate<br></td></tr>\n";
	print "<tr bgcolor=\"$bgcolor5\"><td><b>Adresse:</b></td><td>$d_postnr $d_bynavn<br></td><td><b>Fakturanr:</b></td><td>$fakturanr<br></td></tr>\n";
	print "<tr><td><b>Land:</b></td><td>$land<br></td><td><b>Cvr:</b></td><td>$d_cvrnr<br></td></tr>\n";
	print "<tr bgcolor=\"$bgcolor5\"><td bgcolor=\"$bgcolor5\"><b>Telefon:</b></td><td>$d_telefon<br></td><td><b></b></td><br><td><br></td></tr>\n";
	print "</tbody></table></td></tr>";
	print "<tr><td valign=\"top\" align=\"center\"><table valign=\"top\"><tbody>";
	print "<tr bgcolor=\"$linjebg\"><td width=\"100px\"><b>Varenr</b></td><td width=\"50px\" align=\"right\"><b>Antal</b></td><td width=\"500px\"><b>Beskrivelse</b></td><td width=\"50px\"><b>Købspris</b></td><td width=\"70px\" align=\"right\"><b>Moms %</b></td>\n";
	for ($x=0;$x<$antal_ordrelinjer;$x++) {
	if ($linjebg!=$bgcolor) {
		$linjebg=$bgcolor;
	} else {
		$linjebg=$bgcolor5;
	}
		$l_posnr[$x]=$x+1;
		print "<tr bgcolor=\"$linjebg\"><td>$l_varenr[$x]</td><td align=\"right\">".dkdecimal($l_antal[$x])."</td><td>$l_tekst[$x]</td><td align=\"right\">".dkdecimal($l_pris[$x])."</td><td align=\"right\">".dkdecimal($l_momssats[$x])."</td>\n";
#		echo "Pos $l_posnr[$x], vnr $l_varenr[$x], antal $l_antal[$x], tekst $l_tekst[$x], pris $l_pris[$x], momssats $l_momssats[$x]<br>";
	}
	print "<tr><td colspan=\"5\"><hr></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";	
*/
}


function find_var($indhold,$sektioner,$var,$test) {

# echo "Sektioner $sektioner<br>";
$string="_".$indhold;
$sektion=array($sektioner);
$sektion=explode(",",$sektioner);
#echo "<textarea name=\"notes\" rows=\"6\" cols=\"85\">$string</textarea><br>";

for($i=0;$i<count($sektion);$i++) {
#echo "sektion $sektion[$i]<br>";
	if ($a=stripos($string,"<cac:$sektion[$i]")) {
#echo "A $a<br>";
		if ($b=stripos($string,"</cac:$sektion[$i]")) {
#echo "B $b<br>";
			$a+=strlen("<cac:$sektion[$i]>");
		}
	}	
	if ($sektion[$i]) $string=substr($string,$a,$b-$a);
}

if (!$sektion[0]) $string=$indhold;
#echo "<textarea name=\"notes\" rows=\"6\" cols=\"85\">$string</textarea><br>";
#echo "<textarea name=\"notes\" rows=\"1\" cols=\"85\"> cbc:$var</textarea><br>";
	$a=stripos($string,"<cbc:$var");
	if ($a || $a==0) {
		$value='';
		$a+=strlen("<cbc:".$var.">");
		$b=strlen($string);
		while(substr($string,$a,1)!='<' && $a<=$b) {
			$value.=substr($string,$a,1);
			if (substr($string,$a,1)=='>') $value='';
			$a++;
		}
	}
#echo "Vaerdi $value<br>";
	return ($value);
}

function find_antal_vars($indhold,$var) {
	$string=$indhold;
	$i=0;
	$nextpos=0;
	while ($a=stripos($string,"$var")) {
		$i++;
		$nextpos+=$a;
		($i==1)?$pos=$nextpos:$pos.=",".$nextpos;
		$nextpos++; # 20121220 - Forhindrer at pos rykker 1 plads for hver linje
		$string=substr($string,$a+1);
	}
	return ($pos);
}

function fejllog($fejl,$fejllog) {
	$fp=fopen($fejllog,'a');
	fwrite($fp,"$fejl\n");
	fclose($fp);
} 
