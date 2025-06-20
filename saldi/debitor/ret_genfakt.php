<?php
// ----------debitor/ret_genfakt.php----------lap 3.6.1-----2015-12-23-------
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------

// 16.08.2012 søg 20120816 - Udskriv_til i rødt hvis stamkort siger pbs og dette ikke er valgt.
// 12.12.2012.søg 20121212 - Kontrolfejl v. pbs fakturering i 12. måned.
// 17.01.2013 søg 20130117 - Fejl vi udskrivning til flere forskellige medier - f.eks alle til mail selvom 9 af 10 er til pbs.
// 16.09.2013 Søg 20130916 - Rettet opret_ordrelinjer så pris er NULL i stedet fot 0,00 hvis pris ikke sat.
// 01.10.2013 Søg 20131001 - Tilføjet opdatering af ordresum og moms ved "gem". 
// 15.03.2014 Søg procent - Tilføjet procent alle steder hvor der er 'rabat' i forbindelse med indførelse af procentfakturering
// 22.04.2014 Fejl v opdatering $r[procenttillag] rettet til $procenttillag[$y] Søg 20140422
// 2014.04.26 - Indsat '' foran varenr[$x] i kald til opret_ordrelinje grundet ændring i funktionen (PHR - Danosoft) Søg 20140426 
// 2015.01.06 Formularprint kaldes ikke længere som popup. 20150106
// 2015.12.23 oioubl kan nu også genfaktureres - søg oioubl

print "<script>
	function fokuser(that, fgcolor, bgcolor){
	that.style.color = fgcolor;
	that.style.backgroundColor = bgcolor;
	document.forms[0].fokus.value=that.name; }
	function defokuser(that, fgcolor, bgcolor){
	that.style.color = fgcolor;
	that.style.backgroundColor = bgcolor;}
</script>";
print "<script LANGUAGE=\"JavaScript\"  TYPE=\"text/javascript\" SRC=\"../javascript/overlib.js\"></script>";

@session_start();
$s_id=session_id();
$title="Ret abonnementsordrer";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");
include("../includes/var2str.php");
include("../includes/forfaldsdag.php");
include("../includes/oioublfunk.php");

$incl_moms='';
$art='DO';
$gl_dato=NULL;$ny_dato=NULL;
$gl_genfakt=NULL;$ny_genfakt=NULL;


$returside=if_isset($_GET['returside']);
if ($popup) $returside="../includes/luk.php";
elseif (!$returside) $returside="ordreliste.php";

$r=db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
$year=trim($r['box2']);
$aarstart=str_replace(" ","",$year.$r['box1']);
$year=trim($r['box4']);
$aarslut=str_replace(" ","",$year.$r['box3']);

$r=db_fetch_array(db_SELECT("select box12,box13 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
$procentfakt=$r['box12'];
list($default_procenttillag,$procentvare)=explode(chr(9),$r['box13']);
if ($procentvare) {
	$r=db_fetch_array(db_select("select beskrivelse from varer where varenr = '$procentvare'",__FILE__ . " linje " . __LINE__));
	$tillag=$sum*$procenttillag/100;
	$procentbeskr=var2str($r['beskrivelse'],$id);
#	$procentbeskr=str_replace('$procenttillæg;',$procenttillag,$procentbeskr);
}

print "<table name=\"tabel_1\" width=\"100%\" cellspacing=\"2\" border=\"0\"><tbody>\n"; #tabel 1 ->
print "<tr><td width=\"100%\"><table name=\"tabel_1.1\" width=\"100%\" cellspacing=\"2\"  border=\"0\"><tbody>\n"; # tabel 1.1 ->
print "<td width=10% $top_bund><a href=$returside accesskey=L>Luk</a></td>\n";
print "<td width=80% $top_bund>$title</td>\n";
print "<td width=10% $top_bund><br></td>\n";
print "</tbody></table name=\"tabel_1.1\"></td></tr>\n"; # <- tabel 1.1

$ordreliste=array();
$fejltekst=NULL;
if ($_GET['ordreliste']) {
	$ordreliste=explode(",",$_GET['ordreliste']);
	$ordreantal=sizeof($ordreliste);
} elseif ($_POST['ordreliste']) {
	$submit=$_POST['submit'];
	$fokus=$_POST['fokus'];
	$id[$x]=$_POST['id'];
#	$firmanavn=$_POST['firmanavn'];
#	$fakturadato=$_POST['fakturadato'];
#	$genfakt=$_POST['genfakt'];
#	$udskriv_til=$_POST['udskriv_til'];
#	$email=$_POST['email'];
	$ordreliste=$_POST['ordreliste'];
	$projekt=$_POST['projekt'];
	$ordreantal=sizeof($ordreliste);

#	$posnr=$_POST['posnr'];
	$linjeantal=$_POST['linjeantal'];
	$ordre_id=$_POST['ordre_id'];
	$linje_id=$_POST['linje_id'];
#	$varenr=$_POST['varenr'];
#	$dkantal=$_POST['dkantal'];
#	$beskrivelse=$_POST['beskrivelse'];
#	$dkpris=$_POST['dkpris'];
#	$dkrabat=$_POST['dkrabat'];
#	$kdo=$_POST['kdo'];
#	$linjeantal=sizeof($posnr)+sizeof($ordreliste);
	$linjeantal=sizeof($posnr)+$linjeantal;

	for ($x=0 ; $x<=$linjeantal ; $x++) {
		$posnr[$x]=$_POST['posnr_'.$x];
		$varenr[$x]=$_POST['varenr_'.$x];
		$dkantal[$x]=$_POST['dkantal_'.$x];
		$beskrivelse[$x]=$_POST['beskrivelse_'.$x];
		$dkpris[$x]=$_POST['dkpris_'.$x];
		$dkrabat[$x]=$_POST['dkrabat_'.$x];
		$dkprocent[$x]=$_POST['dkprocent_'.$x];
		$kdo[$x]=$_POST['kdo_'.$x];
	}

	for ($x=0 ; $x<=$ordreantal ; $x++) {
		if ($ordreliste[$x]) {
			$r=db_fetch_array(db_select("select ordrenr from ordrer where id = '$ordreliste[$x]' and status >= '3'",__FILE__ . " linje " . __LINE__));
			if ($r['ordrenr']) {
				$ordreliste[$x]=0;
				$fejltekst="Ordrenr $r[ordrenr] er allerede faktureret. Ordren er fjernet fra listen";
			}
		}
	}
	for ($x=0 ; $x<=$ordreantal ; $x++) {
		if ($ordreliste[$x]) {
			$firmanavn[$x]=$_POST['firmanavn_'.$x];
			$fakturadato[$x]=$_POST['fakturadato_'.$x];
			$sync_stamdata[$x]=if_isset($_POST['sync_stamdata_'.$x]);
#			if ($sync_stamdata[$x]) echo "synkroniserer stamdata<br>";
			if (substr($fakturadato[$x],-2)=="*=") {
				$r=db_fetch_array(db_select("select fakturadate from ordrer where id = '$ordreliste[$x]' and status<'3'",__FILE__ . " linje " . __LINE__));
				$gl_dato=dkdato($r['fakturadate']);
				$fakturadato[$x]=(str_replace("*=","",$fakturadato[$x]));
				$ny_dato=dkdato(usdate($fakturadato[$x]));
			} elseif ($gl_dato && $ny_dato && $fakturadato[$x]==$gl_dato) {
				$fakturadato[$x]=$ny_dato;
			}
			$genfakt[$x]=$_POST['genfakt_'.$x];
			if (substr($genfakt[$x],-2)=="*=") {
				$r=db_fetch_array(db_select("select nextfakt from ordrer where id = '$ordreliste[$x]' and status<'3'",__FILE__ . " linje " . __LINE__));
				$gl_genfakt=dkdato($r['nextfakt']);
				$genfakt[$x]=(str_replace("*=","",$genfakt[$x]));
				$ny_genfakt=dkdato(usdate($genfakt[$x]));
			} elseif ($gl_genfakt && $ny_genfakt && $genfakt[$x]==$gl_genfakt) {
				$genfakt[$x]=$ny_genfakt;
			}
			$udskriv_til[$x]=$_POST['udskriv_til_'.$x];
			$email[$x]=$_POST['email_'.$x];
			$sync_email[$x]=if_isset($_POST['sync_email_'.$x]);
			$betalingsbet[$x]=$_POST['betalingsbet_'.$x];
			$betalingsdage[$x]=$_POST['betalingsdage_'.$x]*1;
			$procenttillag[$x]=usdecimal($_POST['procenttillag_'.$x]);
			if (substr($betalingsdage[$x],-2)=="*=") {
				$r=db_fetch_array(db_select("select betalingsbet,betalingsdage from ordrer where id = '$ordreliste[$x]' and status<'3'",__FILE__ . " linje " . __LINE__));
				$gl_betalingsbet=$r['betalingsbet'];
				$gl_betalingsdage=$r['betalingsdage'];
				$betalingsdage[$x]=(str_replace("*=","",$betalingsdage[$x]));
				$ny_betalingsbet=$betalingsbet[$x];
				$ny_betalingsdage=$betalingsdage[$x];
			} elseif ($gl_betalingsbet && $ny_betalingsbet && $betalingsbet[$x]==$gl_betalingsbet && $betalingsdage[$x]==$gl_betalingsdage) {
				$betalingsbet[$x]=$ny_betalingsbet;
				$betalingsdage[$x]=$ny_betalingsdage;
			}
			if (!$betalingsbet[$x]) $betalingsbet[$x]='Netto';
			$firmanavn[$x]=db_escape_string($firmanavn[$x]);
			$email[$x]=db_escape_string($email[$x]);
			$fakturadate=usdate($fakturadato[$x]);
			$nextfakt=usdate($genfakt[$x]);
			($udskriv_til[$x]=='email')?$mail_fakt='on':$mail_fakt='';
			($udskriv_til[$x]=='PBS_BS' || $udskriv_til[$x]=='PBS_FI')?$pbs='on':$pbs='';
			if ($udskriv_til[$x]=='oioubl') {
				if($art=="DO") $oioubl='faktura';
				else $oioubl='kreditnota';
			} else $oioubl=NULL;
			db_modify("update ordrer set firmanavn='$firmanavn[$x]',fakturadate='$fakturadate',nextfakt='$nextfakt',email='$email[$x]',udskriv_til='$udskriv_til[$x]',mail_fakt='$mail_fakt',projekt='$projekt[$x]',betalingsbet='$betalingsbet[$x]',betalingsdage='$betalingsdage[$x]',procenttillag='$procenttillag[$x]' where id='$ordreliste[$x]'",__FILE__ . " linje " . __LINE__);
			if ($nextfakt<=$fakturadate) $fejltekst="Genfaktureringsdato skal v&aelig;re efter fakturadato ($firmanavn[$x])";
			if (!$fejltekst && strstr($udskriv_til[$x],'PBS')) {
				$betalingsdate=forfaldsdag($fakturadate, $betalingsbet[$x], $betalingsdage[$x]);
				list($b_y,$b_m,$b_d)=explode("-",$betalingsdate);
				$b_m*=1;
				$n_m=date("m")+1;
				if ($n_m > 12) $n_m = 1; # 20121212 
				if ($b_m != $n_m) $fejltekst="Betalingsdato skal v&aelig;re i $n_m. m&aring;ned til PBS ($firmanavn[$x])";
			}
			if (!$fejltekst) {
				list ($year, $month, $day) = explode ('-', $fakturadate);
				$year=trim($year);
				$ym=$year.$month;
				if ($ym<$aarstart || $ym>$aarslut) $fejltekst="Fakturadato udenfor aktivt regnskabs&aring;r ($firmanavn[$x])";
			}
		}
	}
	if ($fejltekst) print "<BODY onLoad=\"javascript:alert('$fejltekst')\">\n";
	for ($x=1 ; $x<=$linjeantal ; $x++) {
		if ($posnr[$x]=='-') {
			if ($linje_id[$x]) db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
		} else {
#			if ($ordreliste[$x])
			$posnr[$x]=$posnr[$x]*1;
			if ($dkantal[$x]=='') $dkantal[$x]=1; 
			$antal=usdecimal($dkantal[$x]);
			(strlen($dkpris[$x]))?$pris=usdecimal($dkpris[$x]):$pris=NULL; #20130916
			$rabat=usdecimal($dkrabat[$x]);
			$procent=usdecimal($dkprocent[$x]);
			$beskrivelse[$x]=trim(db_escape_string($beskrivelse[$x]));
			if (!$projekt[$x]) $projekt[$x]='';
# if ($linje_id[$x]) echo "update ordrelinjer set posnr='$posnr[$x]',antal='$antal',beskrivelse='$beskrivelse[$x]',pris='$pris',rabat='$rabat',kdo='$kdo[$x]',projekt='$projekt[$x]' where id='$linje_id[$x]'<br>";
			if ($linje_id[$x]) {
				$pris*=1;
				db_modify("update ordrelinjer set posnr='$posnr[$x]',antal='$antal',beskrivelse='$beskrivelse[$x]',pris='$pris',rabat='$rabat',procent='$procent',kdo='$kdo[$x]',projekt='$projekt[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			} elseif ($varenr[$x]) {
				opret_ordrelinje($ordre_id[$x],'',$varenr[$x],$antal,$beskrivelse[$x],$pris,$rabat,$procent,$art,$momsfri,$posnr[$x],$linje_id[$x],$incl_moms,$kdo[$x]); #20140426
#				indset_varenr($varenr[$x],$posnr[$x],$antal,$beskrivelse[$x],$pris,$rabat,$ordre_id[$x]);
			} elseif ($beskrivelse[$x])  {
				db_modify("insert into ordrelinjer (posnr,antal,beskrivelse,pris,rabat,ordre_id,kdo,procent) values ('$posnr[$x]','0','$beskrivelse[$x]','0','0','$ordre_id[$x]','$kdo[$x]','100')",__FILE__ . " linje " . __LINE__);
			} else $ordre_id[$x]=NULL;
		}   
	}
	
	for ($x=0 ; $x<=$ordreantal ; $x++) {
		if ($ordreliste[$x]) {
			if (!in_array($ordreliste[$x],$ordre_id)) $ordreliste[$x]=0;
			else { # 20131001 ->
				$r=db_fetch_array(db_select("select momssats from ordrer where id = '$ordreliste[$x]'",__FILE__ . " linje " . __LINE__));
				$momssats=$r['momssats'];
				$bl_moms=0;
				$momssum=0;
				$ordresum=0;
				$q=db_select("select antal,pris,rabat,momsfri from ordrelinjer where ordre_id = '$ordreliste[$x]' and vare_id>'0'",__FILE__ . " linje " . __LINE__);
				while($r=db_fetch_array($q)){
					$linjesum=afrund($r['antal']*($r['pris']-($r['pris']*$r['rabat']/100)),2);
					$ordresum+=$linjesum;
					if ($momssats && !$r['momsfri']) $momssum+=afrund($linjesum*$momssats/100,2);
					elseif ($momssats && $r['momsfri']) $bl_moms=1;
				}
				if($momssats && !$bl_moms) $momssum=$ordresum*$momssats/100;
				db_modify("update ordrer set sum='$ordresum',moms='$momssum' where id='$ordreliste[$x]' and status < '3'",__FILE__ . " linje " . __LINE__);
			}
		}
	}
	if ($submit=='Fakturer' && !$fejltekst) {
		$y=0;
		$udskriv=NULL;
		for ($x=0 ; $x<=$ordreantal ; $x++) {
			if ($ordreliste[$x]) {
				($udskriv_til[$x]=='email')?$mail_fakt='on':$mail_fakt='';  #20130117
				(strstr($udskriv_til[$x],'PBS'))?$pbs='on':$pbs='';  #20130117
				if ($udskriv_til[$x]=='oioubl') {
					if($art=="DO") $oioubl='faktura';
					else $oioubl='kreditnota';
				} else $oioubl=NULL;
				$y++;
				transaktion('begin');
				levering($ordreliste[$x],'on','on');
				$svar=bogfor($ordreliste[$x],'on','on');
				if ($svar=='OK') {
					if ($pbs) {
						pbsfakt($ordreliste[$x]);
						$y--;  #20130117
					}	elseif ($oioubl) {
						$printfilnavn="doktype-faktura_dokid-".$ordreliste[$x].".xml";
						$fp=fopen("../temp/$db/$printfilnavn","w");
						fwrite($fp,oioubldoc_faktura($ordreliste[$x],'faktura',NULL));
						fclose($fp);
#echo "'oioubl_dok.php?id=$ordreliste[$x]&doktype=$oioubl&genfakt=1' ,'' ,'$jsvars'<br>";
#						print "<BODY onLoad=\"JavaScript:window.open('oioubl_dok.php?id=$ordreliste[$x]&doktype=$oioubl&genfakt=1' ,'' ,'$jsvars');\">\n";
						$r=db_fetch_array(db_select("select box8 from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
						if ($r['box8']) {
							list($oiourl,$oiobruger,$oiokode)=explode(chr(9),$r['box8']);
							if ($oiourl&&$oiobruger&&$oiokode) {
								$fp=fopen("../temp/$db/oioftpscript1.$ordreliste[$x]","w");
								if ($fp) {
									fwrite ($fp, "set confirm-close no\nput $printfilnavn\nbye\n");
								}
								fclose($fp);
								$fp=fopen("../temp/$db/oioftplog.$ordreliste[$x]","w");
								fwrite ($fp, "cd ../temp/$db\n\r$exec_path/ncftp ftp://$oiobruger:$oiokode@$oiourl\n\r");
								fclose($fp);
								$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$oiobruger.":".$oiokode."@".$oiourl." < oioftpscript1.$ordreliste[$x] >> oioftplog.$ordreliste[$x]\n";
#								echo "<br>A ".str_replace("\n","<br>",$kommando)."<br>";
								system ($kommando);
								unlink("../temp/$db/$printfilnavn"); 
								$fp=fopen("../temp/$db/oioftpscript2.$ordreliste[$x]","w");
								if ($fp) fwrite ($fp, "set confirm-close no\nget $printfilnavn\nbye\n");
								fclose($fp);
								$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$oiobruger.":".$oiokode."@".$oiourl." < oioftpscript2.$ordreliste[$x] >> oioftplog.$ordreliste[$x]\n";
#								echo "<br>B ".str_replace("\n","<br>",$kommando)."<br>";
								system ($kommando);
								if (file_exists("../temp/$db/$printfilnavn")) {
									unlink("../temp/$db/$printfilnavn"); 
									#echo 'unlink(\"../temp/$db/$printfilnavn\")';
									print "<tr><td>$printfilnavn overført til $oiourl</td></tr>";
								} else {
									print "<BODY onLoad=\"javascript:alert('Afsendelse af $printfilnavn fejlet tjek brugernavn og adgangskode til ftp hos ebConnect')\">";
									exit;
								}
							} else {
								print "<tr><td>Gem <a href=\"../temp/$db/$printfilnavn\">$printfilnavn</a> på din computer og upload til din udbyder</td></td>"; 
							}
							$y--;
						}
					}	elseif ($udskriv) $udskriv.=",$ordreliste[$x]";
					else $udskriv="$ordreliste[$x]"; 
#xit;
					transaktion('commit');
				} elseif ($ordreantal) {
					if (strpos($svar,'invoicedate prior to')) $tekst="Genfaktureringsdato før fakturadato";
					else $tekst="Der er konstateret en ubalance i posteringssummen\\nfor ordre $ordreliste[$x]\\nkontakt venligst Danosoft på tlf. +45 46902208";
					print "<BODY onLoad=\"javascript:alert('$svar')\">\n";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
					exit;
				}
			}
		}
		if ($udskriv) {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=formularprint.php?id=-1&returside=ordreliste.php&ordre_antal=$y&skriv=$udskriv&formular=4\">";
#			print "<BODY onLoad=\"JavaScript:window.open('formularprint.php?id=-1&ordre_antal=$y&skriv=$udskriv&formular=4' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
			exit;
		}
	}
}
##########################################################################################################################################
$r=db_fetch_array(db_select("select art,pbs_nr,pbs from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
$lev_pbs_nr=$r['pbs_nr'];
$lev_pbs=$r['pbs'];

$y=-1;
for ($x=0;$x<$ordreantal;$x++) {
		if ($r=db_fetch_array(db_select("select * from ordrer where id = '$ordreliste[$x]' and status<'3'",__FILE__ . " linje " . __LINE__))) {
		$y++;
		$id[$y]=$r['id']*1;
		$konto_id[$y]=$r['konto_id']*1;
		$kontonr[$y]=$r['kontonr'];
		$firmanavn[$y]=stripslashes(htmlentities(trim($r['firmanavn']),ENT_COMPAT,$charset));
		$addr1[$y]=stripslashes(htmlentities(trim($r['addr1']),ENT_COMPAT,$charset));
		$addr2[$y]=stripslashes(htmlentities(trim($r['addr2']),ENT_COMPAT,$charset));
		$postnr[$y]=stripslashes(htmlentities(trim($r['postnr']),ENT_COMPAT,$charset));
		$bynavn[$y]=stripslashes(htmlentities(trim($r['bynavn']),ENT_COMPAT,$charset));
		
		$fakturadato[$y]=dkdato($r['fakturadate']);
		$genfakt[$y]=dkdato($r['nextfakt']);
		$betalingsbet[$y]=$r['betalingsbet'];
		$betalingsdage[$y]=$r['betalingsdage'];
		$udskriv_til[$y]=$r['udskriv_til'];
		$email[$y]=$r['email'];
		$projekt[$y]=$r['projekt'];
		$procenttillag[$y]=$r['procenttillag']*1;
		if (!$email[$y] && $udskriv_til[$y]=='email') $udskriv_til[$y]='PDF';
		$r=db_fetch_array(db_select("select pbs_nr,pbs from adresser where id = '$konto_id[$y]'",__FILE__ . " linje " . __LINE__));
		$pbs[$y]=$r['pbs'];
		$pbs_nr[$y]=$r['pbs_nr'];

		$r=db_fetch_array(db_select("select * from adresser where id = '$konto_id[$y]'",__FILE__ . " linje " . __LINE__));
		$stam_firmanavn[$y]=stripslashes(htmlentities(trim($r['firmanavn']),ENT_COMPAT,$charset));
		$stam_addr1[$y]=stripslashes(htmlentities(trim($r['addr1']),ENT_COMPAT,$charset));
		$stam_addr2[$y]=stripslashes(htmlentities(trim($r['addr2']),ENT_COMPAT,$charset));
		$stam_postnr[$y]=stripslashes(htmlentities(trim($r['postnr']),ENT_COMPAT,$charset));
		$stam_bynavn[$y]=stripslashes(htmlentities(trim($r['bynavn']),ENT_COMPAT,$charset));
		$stam_email[$y]=stripslashes(htmlentities(trim($r['email']),ENT_COMPAT,$charset));
		$stam_pbs[$y]=stripslashes(htmlentities(trim($r['pbs']),ENT_COMPAT,$charset));
		$stam_pbs_nr[$y]=stripslashes(htmlentities(trim($r['pbs_nr']),ENT_COMPAT,$charset));
		$stam_notes[$y]=stripslashes(htmlentities(trim($r['notes']),ENT_COMPAT,$charset));
		if ($sync_stamdata[$y]) { #20140422
			db_modify("update ordrer set firmanavn='".db_escape_string($r[firmanavn])."',addr1='".db_escape_string($r['addr1'])."',addr2='".db_escape_string($r['addr2'])."',postnr='".db_escape_string($r['postnr'])."',bynavn='".db_escape_string($r['bynavn'])."',procenttillag='$procenttillag[$y]' where id='$id[$y]'",__FILE__ . " linje " . __LINE__);
			$firmanavn[$y]=$stam_firmanavn[$y];
			$addr1[$y]=$stam_addr1[$y];
			$addr2[$y]=$stam_addr2[$y];
			$postnr[$y]=$stam_postnr[$y];
			$bynavn[$y]=$stam_bynavn[$y];
		}
		if ($sync_email[$y]) {
			db_modify("update ordrer set email='".db_escape_string($r['email'])."' where id='$id[$y]'",__FILE__ . " linje " . __LINE__);
			$email[$y]=$stam_email[$y];
		}
	}
} $ordreantal=$y;

##########################################################################################################################################
print "<form name=\"ret_genfakt\" action=\"ret_genfakt.php?returside=$returside\" method=\"post\">\n";
$onfocus="onfocus=\"fokuser(this,'#000000','#EFEFEF');\" onblur=\"defokuser(this,'#000000','#FFFFFF');\"";
print "<tr><td align=\"center\" width=\"100%\"><table border=\"1\"><tbody><tr><td>";
for ($x=0 ; $x<=$ordreantal ; $x++) {
	$spantekst1="$firmanavn[$x]<br>$addr1[$x]<br>";
	if ($addr2[$x]) $spantekst1.="$addr2[$x]<br>";
	$spantekst1.="$postnr[$x] $bynavn[$x]";
#	if ($email[$x]) $spantekst1.="<br>$email[$x]";
	$spantekst2="$stam_firmanavn[$x]<br>$stam_addr1[$x]<br>";
	if ($stam_addr2[$x]) $spantekst2.="$stam_addr2[$x]<br>";
	$spantekst2.="$stam_postnr[$x] $stam_bynavn[$x]";
#	if ($stam_email[$x]) $spantekst2.="<br>$stam_email[$x]";

	print "<input type=\"hidden\" name=\"ordreliste[$x]\" value=\"$id[$x]\">";
	print "<input type=\"hidden\" name=\"projekt[$x]\" value=\"$projekt[$x]\">";
	print "<tr><td width=\"100%\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"1\" width=\"100%\"><tbody>";
	print "<tr>";
	print "<td align=\"center\">Kontonr</td>";
	print "<td align=\"center\">Firmanavn</td>";
	print "<td align=\"center\">Fakturadato</td>";
	print "<td align=\"center\">Genfakt</td>";
	print "<td align=\"center\">Betalingsbet.</td>";
	print "<td align=\"center\">Email</td>";
	print "<td align=\"center\">udskriv_til</td>";
	if ($procentvare) print "<td align=\"center\">%tillæg</td>";
	print "</tr><tr>";
	print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"text-align:left;width:100px\" value=\"$kontonr[$x]\"></td>";

	if ($spantekst1 != $spantekst2) {
		$color="#FF0000";
		$spantekst="Kundeinfo på ordre er forskellig fra kundeinfo i stamdata<br><b>Ordre:</b><br>$spantekst1<br><b>Stamkort</b><br>$spantekst2<br>Afm&aelig;rk feltet til h&oslash;jre for at synkronisere ordre med stamkort<br>";
	} else { 
		$color="#000000";
		$spantekst=$spantekst1;
	}
	$style="text-align:left;width:300px;color:$color";
	$spantekst=db_escape_string($spantekst);
	print "<td style=\"height:18px;\"><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">
	<input style=\"$style\" class=\"inputbox\" onfocus=\"fokuser(this,'$color','#EFEFEF');\" onblur=\"defokuser(this,'$color','#FFFFFF');\" type=\"text\" name=\"firmanavn_$x\" value=\"$firmanavn[$x]\"></span>";
	if ($spantekst1 != $spantekst2) {
		print "<input class=\"inputbox\" type=\"checkbox\" name=\"sync_stamdata_$x\">";
	}
	print "</td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:center;width:90px\" name=\"fakturadato_$x\" value=\"$fakturadato[$x]\"></td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:center;width:90px\" name=\"genfakt_$x\" value=\"$genfakt[$x]\"></td>";
	print "<td><select class=\"inputbox\" $onfocus style=\"text-align:left;width:70px\" name=\"betalingsbet_$x\">";

	if (!$betalingsbet) $betalingsbet="Netto";
	print "<option>$betalingsbet[$x]</option>\n";
	if ($betalingsbet[$x]!='Forud') 	print "<option>Forud</option>\n";
	if ($betalingsbet[$x]!='Kontant')	print "<option>Kontant</option>\n";
	if ($betalingsbet[$x]!='Efterkrav')	print "<option>Efterkrav</option>\n";
	if ($betalingsbet[$x]!='Netto') print "<option>Netto</option>\n";
	if ($betalingsbet[$x]!='Lb. md.') print "<option>Lb. md.</option>\n";
	if (($betalingsbet[$x]=='Kontant')||($betalingsbet[$x]=='Efterkrav')||($betalingsbet[$x]=='Forud')) $betalingsdage[$x]='';
	elseif (!$betalingsdage[$x]) $betalingsdage[$x]='Nul';
	if ($betalingsdage[$x])	{
		if ($betalingsdage[$x]=='Nul') $betalingsdage[$x]=0;
		print "</SELECT>+<input class=\"inputbox\" $onfocus type=\"text\" size=\"1\" style=\"text-align:right\" name=\"betalingsdage_$x\" value=\"$betalingsdage[$x]\"></td>\n";
	}
	if ($email[$x] != $stam_email[$x]) {
		$spantekst="Email på ordre er forskellig fra email i stamdata ($stam_email[$x])<br>Afmærk feltet til højre for at synkronisere med email fra stamdata";
		print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\" style=\"color:#ff0000;\"><input class=\"inputbox\" $onfocus type=\"text\" style=\" text-align:center;width:90px;color:#ff0000\" name=\"email_$x\" value=\"$email[$x]\"></span>";
		print "<input class=\"inputbox\" type=\"checkbox\" name=\"sync_email_$x\"></td>";
	} else {
		print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:center;width:90px\" name=\"email_$x\" value=\"$email[$x]\"></td>";
	}

	# 20120816 Næste 7 linjer
	if ($stam_pbs[$x] && $stam_pbs_nr[$x] && substr($udskriv_til[$x],0,3) != "PBS") {
		$span="<span onmouseover=\"return overlib('Kunden er sat til PBS i stamdata<br>', WIDTH=800);\" onmouseout=\"return nd();\">"; 
		$tekstcolor="#ff0000";
	} else {
		$span="<span>";
		$tekstcolor="#000000";
	}
	print "<td>$span<select class=\"inputbox\" $onfocus style=\"text-align:left;width:70px;color:$tekstcolor\" name=\"udskriv_til_$x\">";
	if ($udskriv_til[$x]) print "<option>$udskriv_til[$x]</option>\n";
	if ($udskriv_til[$x]!="PDF") print "<option>PDF</option>\n";
	if ($udskriv_til[$x]!="email" && $email) print "<option>email</option>\n";
	if ($lev_pbs_nr) {
		$tmp=$pbs_nr[$x]*1;
		if ($udskriv_til[$x]!="PBS_FI" && $lev_pbs!='B') print "<option value=\"PBS_FI\">PBS</option>\n";
		elseif ($tmp && $udskriv_til[$x]!="PBS_BS") print "<option title=\"Opkr&aelig;ves via PBS betalingsservice\" value=\"PBS_BS\">PBS</option>\n";
	}
	if ($udskriv_til[$x]!="oioxml" && strlen($ean)==13) print "<option title=\"Kun ved fakturering/kreditering.\">oioxml</option>\n";
	print "</select</span></td>";
	if ($procentvare) print "<td align=\"center\"><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:center;width:50px\" name=\"procenttillag_$x\" value=\"".dkdecimal($procenttillag[$x])."\"></td>";
	if ($stam_notes[$x]) {
		$tmp=str_replace("\n","<br>",$stam_notes[$x]);
		$tmp=str_replace("\r","",$tmp);
		$obs="<span onmouseover=\"return overlib('$tmp', WIDTH=800);\" onmouseout=\"return nd();\">OBS!</span>";
	} else $obs=NULL;
	print "<td style=\"text-align:center;width:80px;color:ff0000\">$obs</td>";
	print "</tbody></table></td></tr>";
	print "<tr><td><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
	print "<tr>";
	print "<td align=\"center\">Pos</td>";
	print "<td align=\"center\">Varenr</td>";
	print "<td align=\"center\">Antal</td>";
	print "<td align=\"center\">Beskrivelse</td>";
	print "<td align=\"center\">Pris</td>";
	print "<td align=\"center\">Rabat</td>";
	if ($procentfakt) print "<td align=\"center\">Procent</td>";
	print "<td align=\"center\">I alt</td>";
	print "<td align=\"center\" title=\"Kun denne ordre! Hvis dette felt er afm&aelig;rket medtages linjen ikke ved n&aelig;ste genfakturering\">kdo</td>";
	print "</tr><tr>";
	$posnr=0;
	$ordresum=0;	
	$q=db_select("select * from ordrelinjer where ordre_id = '$id[$x]' order by posnr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)){
		$y++;
		$posnr++;
		$linje_id[$y]=$r['id'];
		$varenr[$y]=$r['varenr'];
		$dkantal[$y]=dkdecimal($r['antal']);
		$beskrivelse[$y]=stripslashes(htmlentities(trim($r['beskrivelse']),ENT_COMPAT,$charset));
		$dkpris[$y]=dkdecimal($r['pris']);
		$dkrabat[$y]=dkdecimal($r['rabat']);
		$dkprocent[$y]=dkdecimal($r['procent']);
		$projekt[$y]=$r['projekt'];
		if ($r['kdo']) $kdo[$y]='checked';
		else $kdo[$y]='';

		$linjesum=$r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100);
		if ($procentfakt)$linjesum*=$r['procent']/100;
		$ordresum+=$linjesum;
		$linjesum=dkdecimal($linjesum);
		print "<input type=\"hidden\" name=\"linje_id[$y]\" value=\"$linje_id[$y]\">";
		print "<input type=\"hidden\" name=\"ordre_id[$y]\" value=\"$id[$x]\">";
		print "<input type=\"hidden\" name=\"projekt[$y]\" value=\"$projekt[$y]\">";
		print "<tr>";
		print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:30px\" name=\"posnr_$y\" value=\"$posnr\"></td>";
		if ($varenr[$y]) {
			print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:left;width:80px\" name=\"varenr_$y\" value=\"$varenr[$y]\"></td>";
			print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:50px\" name=\"dkantal_$y\" value=\"$dkantal[$y]\"></td>";
		} else {
			print "<td><input class=\"inputbox\" $onfocus readonly=\"readonly\" style=\"width:80px\"></td>";
			print "<td><input class=\"inputbox\" $onfocus readonly=\"readonly\" style=\"width:50px\"></td>";
		}
		$title=var2str($beskrivelse[$y],$id[$x]);
		print "<td title=\"$title\"><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:left;width:600px\" name=\"beskrivelse_$y\" value='$beskrivelse[$y]'></td>";
		if ($varenr[$y]) {
			print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:80px\" name=\"dkpris_$y\" value=\"$dkpris[$y]\"></td>";
			print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:50px\" name=\"dkrabat_$y\" value=\"$dkrabat[$y]\"></td>";
			if ($procentfakt) print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:50px\" name=\"dkprocent_$y\" value=\"$dkprocent[$y]\"></td><td>";
			else print "<td><input type=\"hidden\" name=\"dkprocent_$y\" value=\"100\">";
			print "<input class=\"inputbox\" readonly=\"readonly\" style=\"text-align:right;width:80px\" value=\"$linjesum\"></td>";
		} else {
			print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"width:80px\"></td>";
			print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"width:50px\"></td>";
			print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"width:80px\"></td>";
		}
 		print "<td title=\"Hvis dette felt er afm&aelig;rket medtages linjen ikke ved n&aelig;ste genfakturering\" align = \"center\">
			<input class=\"inputbox\" type=\"checkbox\" $onfocus name=\"kdo_$y\" $kdo[$y]></td>";
		print "</tr>";
	}
	$y++;
	$posnr++;
	print "<input type=\"hidden\" name=\"ordre_id[$y]\" value=\"$id[$x]\">";
	print "<tr>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:30px\" name=\"posnr_$y\" value=\"$posnr\"></td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:left;width:80px\" name=\"varenr_$y\"></td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:50px\" name=\"dkantal_$y\"></td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:left;width:600px\" name=\"beskrivelse_$y\"></td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:80px\" name=\"dkpris_$y\"></td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:50px\" name=\"dkrabat_$y\"></td>";
	if ($procentfakt) print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:50px\" name=\"dkprocent_$y\" value=\"100\"></td><td>";
	else print "<td><input type=\"hidden\" name=\"dkprocent_$y\" value=\"100\">";
	print "<input class=\"inputbox\" readonly=\"readonly\" style=\"text-align:right;width:80px\"></td>";
	print "<td title=\"Hvis dette felt er afm&aelig;rket medtages linjen ikke ved n&aelig;ste genfakturering\" align = \"center\">
		<input class=\"inputbox\" $onfocus type=\"checkbox\" name=\"kdo_$y\" checked></td>";
	print "</tr>";
	if ($procenttillag[$x]) {
		$tillag=$ordresum*$procenttillag[$x]/100;
#echo htmlentities($procentbeskr)." -> $procenttillag[$x]<br>";
		$pctbesk=str_replace('$procenttillæg;',dkdecimal($procenttillag[$x]),$procentbeskr);
		print "<tr>";
		print "<td></td>";
		print "<td>$procentvare</td>";
		print "<td></td>";
		print "<td>$pctbesk</td>";
		print "<td colspan=\"2\"></td>";
		if ($procentfakt) print "<td></td>";
		print "<td align=\"right\">".dkdecimal($tillag)."</td>";
		print "<td</td>";
		print "</tr>";
	}
	print "</tbody></table></td></tr>";
}
print "<input type=\"hidden\" name=\"fokus\" id=\"fokus\">";
print "<input type=\"hidden\" name=\"linjeantal\" value=\"$y\">";
if ($submit!='Fakturer' || $fejltekst) {
	print "<td align=\"center\" width=$width><input type=\"submit\" style=\"width:80px\" accesskey=\"g\" value=\"Gem\" name=\"submit\" onclick=\"javascript:docChange = false;\">\n";
	if (!$fejltekst) print "&nbsp;<input type=\"submit\" style=\"width:80px\" accesskey=\"f\" value=\"Fakturer\" name=\"submit\" onclick=\"javascript:docChange = false;\">\n";
	print "</td>";
}
print "</tbody></table></td></tr>";
print "</tbody></table>";

print "<script language=\"javascript\">";
print "document.ret_genfakt.$fokus.focus()";;
print "</script>";
?>
